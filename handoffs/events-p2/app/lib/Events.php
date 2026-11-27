<?php
// app/lib/Events.php — events a business posts (decisions/0134, its minimum before Jan 1: the note of 2026-11-27, option
// (a), piloted by the Hometown theater). One store, `events`, for host-posted events; the crawler's Live events stay in
// their own store for Jan 1 (that note: "Live events stay separate from host events", the merge moves to Q1).
//   Who posts:  a business's team on a claimed listing, with Catalog & content to edit (Team::can, which holds an unclaimed
//               public listing at view; 0134 §2's Owner, Manager, Creator through their roles' permissions).
//   What:       title, a short description, a kind (0134 §3), when (start, end or all day, in the venue's own time zone:
//               CLAUDE.md "Times where they happened"), who for (all ages, 18+, 21+), a cover photo (the one uploader,
//               target event_photo), showtimes as information only, and a link to one of the host's own published
//               Event tickets items (ItemKinds 'ticket', bought on the Marketplace path). Free events otherwise: no price.
//   Never:      links to outside ticket sellers or "pay at the door" notes (0134 §3; the note: showtimes are information
//               only, with no ticket link and no door note). Words the Marketplace holds or blocks are held the same way.
//   States:     draft → published ⇄ draft (Unpublish); a published event can be cancelled (it shows as cancelled, its way
//               back is Put back on); a draft can be deleted (kept 30 days with Put back). Each change of what a person
//               entered keeps the old value in its History (CLAUDE.md "Edit or Undo").
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Events
{
    /** 0134 §3's kinds. */
    public const KINDS = ['festival' => 'Festival', 'market' => 'Market', 'music' => 'Music', 'arts' => 'Arts', 'sports' => 'Sports & rodeo',
        'outdoors' => 'Outdoors', 'food' => 'Food & drink', 'family' => 'Family', 'faith' => 'Faith', 'learning' => 'Learning', 'meetup' => 'Meetup',
        'business' => 'Business', 'volunteering' => 'Volunteering', 'civic' => 'Civic'];
    /** 0134 §3, "Who for". */
    public const AGES = ['all' => 'All ages', '18' => '18+', '21' => '21+'];
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'deleted' => 'Deleted'];
    /** What a person entered: each change keeps the old value in the History. */
    public const FIELDS = ['title', 'about', 'kind', 'starts_at', 'ends_at', 'all_day', 'age', 'showtimes', 'ticket_item_id', 'gallery_item_id'];
    public const KEEP_DAYS = 30;

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM events LIMIT 0'); $pdo->query('SELECT 1 FROM event_history LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /** May this person post for this listing? [yes, why not]. The listing must be claimed; the role needs Catalog & content to edit. */
    public static function canPost(PDO $pdo, int $uid, int $entity): array
    {
        require_once __DIR__ . '/Team.php';
        $st = $pdo->prepare('SELECT status FROM entities WHERE id = ?'); $st->execute([$entity]); $status = (string) $st->fetchColumn();
        if ($status === 'unclaimed') return [false, 'Events are posted by a business that has claimed its listing. Claim it first.'];
        if (!Team::can($pdo, $uid, $entity, 'catalog', 'edit')) return [false, 'Your role can\'t post events for this business. The owner can change your role.'];
        return [true, ''];
    }

    /** The venue's time zone: its state (and its longitude where a state has two), as trips and hours read it. */
    public static function zone(PDO $pdo, int $entity): string
    {
        require_once __DIR__ . '/JourneyPlan.php';
        try {
            $st = $pdo->prepare('SELECT TRIM(z.state) AS state, e.longitude FROM entities e LEFT JOIN zip_coordinates z ON TRIM(z.zip) = TRIM(e.primary_zip) WHERE e.id = ? LIMIT 1');
            $st->execute([$entity]); $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            return JourneyPlan::timezone(['state' => (string) ($r['state'] ?? ''), 'lon' => $r['longitude'] !== null && $r['longitude'] !== '' ? (float) $r['longitude'] : -100]);
        } catch (Throwable) { return 'America/Phoenix'; }
    }

    /** The business's events, newest start first, deleted ones last (still within their 30 days). */
    public static function forHost(PDO $pdo, int $entity): array
    {
        $st = $pdo->prepare("SELECT * FROM events WHERE entity_id = ? AND (status <> 'deleted' OR deleted_at > UTC_TIMESTAMP() - INTERVAL " . self::KEEP_DAYS . " DAY)
                              ORDER BY status = 'deleted', starts_at DESC, id DESC");
        $st->execute([$entity]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM events WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The host's own published Event tickets items, for the event's ticket link: [id => title]. */
    public static function ticketItems(PDO $pdo, int $entity): array
    {
        try {
            $st = $pdo->prepare("SELECT id, title FROM market_items WHERE entity_id = ? AND status = 'published' AND kind IN ('ticket', 'class') ORDER BY published_at DESC");
            $st->execute([$entity]);
            return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'title', 'id');
        } catch (Throwable) { return []; }
    }

    /**
     * Save a new event (id 0) or an edit, from the form. Times come as the venue's local "Y-m-d\TH:i" (or a date for all
     * day) and are stored as written, with the venue's zone beside them. Returns the event's id; throws with the reason.
     */
    public static function save(PDO $pdo, int $uid, int $entity, array $in, int $id = 0, string $tz = ''): int
    {
        [$ok, $why] = self::canPost($pdo, $uid, $entity); if (!$ok) throw new InvalidArgumentException($why);
        $old = $id ? self::get($pdo, $id) : null;
        if ($id && (!$old || (int) $old['entity_id'] !== $entity)) throw new InvalidArgumentException('That event isn\'t this business\'s.');
        if ($old && $old['status'] === 'deleted') throw new InvalidArgumentException('That event was deleted. Put it back first.');
        $v = self::clean($pdo, $entity, $in);
        if ($old) {
            $changed = [];
            foreach (self::FIELDS as $f) if ((string) ($old[$f] ?? '') !== (string) ($v[$f] ?? '')) $changed[$f] = $old[$f];
            if (!$changed) return $id;
            $sets = implode(', ', array_map(fn ($f) => "$f = ?", array_keys($v)));
            $pdo->prepare("UPDATE events SET $sets, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute(array_merge(array_values($v), [$uid, $id]));
            $h = $pdo->prepare('INSERT INTO event_history (event_id, at, tz, user_id, field, old_value) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?)');
            foreach ($changed as $f => $was) $h->execute([$id, self::tzOk($tz), $uid, $f, $was === null ? null : (string) $was]);
            return $id;
        }
        $cols = array_merge(array_keys($v), ['entity_id', 'zone', 'status', 'created_by', 'created_at', 'created_tz', 'updated_by', 'updated_at']);
        $vals = array_merge(array_values($v), [$entity, self::zone($pdo, $entity), 'draft', $uid, gmdate('Y-m-d H:i:s'), self::tzOk($tz), $uid, gmdate('Y-m-d H:i:s')]);
        $pdo->prepare('INSERT INTO events (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        $new = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO event_history (event_id, at, tz, user_id, field, old_value) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, NULL)')->execute([$new, self::tzOk($tz), $uid, 'created']);
        return $new;
    }

    /**
     * Move an event: publish, unpublish (back to a draft), cancel, restore (a cancelled event published again), delete (a
     * draft or a cancelled event, kept 30 days), undelete. Returns the words for the notice.
     */
    public static function act(PDO $pdo, int $uid, int $entity, int $id, string $what, string $tz = ''): string
    {
        [$ok, $why] = self::canPost($pdo, $uid, $entity); if (!$ok) throw new InvalidArgumentException($why);
        $e = self::get($pdo, $id);
        if (!$e || (int) $e['entity_id'] !== $entity) throw new InvalidArgumentException('That event isn\'t this business\'s.');
        $s = (string) $e['status'];
        $moves = ['publish' => [['draft'], 'published', 'Published. It shows on its own page, your listing and your town\'s guide.'],
                  'unpublish' => [['published'], 'draft', 'Unpublished: back to a draft, seen only by your team.'],
                  'cancel' => [['published'], 'cancelled', 'Cancelled. It stays listed as cancelled, so anyone planning to come knows.'],
                  'restore' => [['cancelled'], 'published', 'Put back on: published again.'],
                  'delete' => [['draft', 'cancelled'], 'deleted', 'Deleted. It\'s kept ' . self::KEEP_DAYS . ' days in case you want it back.'],
                  'undelete' => [['deleted'], 'draft', 'Put back as a draft.']];
        if (!isset($moves[$what])) throw new InvalidArgumentException('Unknown action.');
        [$from, $to, $words] = $moves[$what];
        if (!in_array($s, $from, true)) return 'Already ' . strtolower(self::STATUSES[$s] ?? $s) . ': nothing to change.';
        if ($what === 'publish') {
            if (strtotime((string) ($e['ends_at'] ?: $e['starts_at'])) < strtotime('-1 day')) throw new InvalidArgumentException('This event is in the past. Change its date to publish it.');
            $hold = self::checkWords($pdo, (string) $e['title'], (string) $e['about'] . "\n" . (string) $e['showtimes']);
            if ($hold !== '') throw new InvalidArgumentException($hold);
        }
        $pdo->prepare('UPDATE events SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()' . ($to === 'deleted' ? ', deleted_at = UTC_TIMESTAMP()' : ($what === 'undelete' ? ', deleted_at = NULL' : '')) . ($to === 'published' && !$e['published_at'] ? ', published_at = UTC_TIMESTAMP()' : '') . ' WHERE id = ? AND status = ?')
            ->execute([$to, $uid, $id, $s]);
        $pdo->prepare('INSERT INTO event_history (event_id, at, tz, user_id, field, old_value) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?)')->execute([$id, self::tzOk($tz), $uid, $what, $s]);   // the move by name, with the state it left (the Design reviewer, 2026-11-27)
        return $words;
    }

    /** A History line's words: the move named, with the state it left in brackets, as the state line names it ("cancelled it (was Published)"). */
    public const MOVE_WORDS = ['publish' => 'published it', 'unpublish' => 'unpublished it', 'cancel' => 'cancelled it', 'restore' => 'put it back on', 'delete' => 'deleted it', 'undelete' => 'put it back'];
    public static function historyWords(array $x): string
    {
        $f = (string) $x['field']; $was = $x['old_value'];
        if ($f === 'created') return 'added it';
        if (isset(self::MOVE_WORDS[$f])) return self::MOVE_WORDS[$f] . ' (was ' . (self::STATUSES[(string) $was] ?? (string) $was) . ')';
        if (str_starts_with($f, 'part:')) { [$nm, $ow] = array_pad(explode('|', (string) $was, 2), 2, null); $mv = substr($f, 5);
            return ['invited' => 'invited ' . $nm, 'accepted' => $nm . ' accepted', 'declined' => $nm . ' declined', 'left' => $nm . ' left', 'rejoined' => $nm . ' came back', 'removed' => 'removed ' . $nm, 'put back' => 'put ' . $nm . ' back', 'offer' => $nm . ' changed its offer' . ($ow !== null && $ow !== '' ? ' (was ' . $ow . ')' : ' (it had none)')][$mv] ?? $mv; }
        if ($f === 'status') return 'changed its state (was ' . (self::STATUSES[(string) $was] ?? (string) $was) . ')';   // before the moves were named
        return 'changed ' . str_replace('_', ' ', $f) . ($was !== null && $was !== '' ? ' (was "' . mb_strimwidth((string) $was, 0, 80, '…') . '")' : '');
    }

    /** An event's History: [at, tz, who, what changed, the old value], newest first. */
    public static function history(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare('SELECT h.*, COALESCE(NULLIF(u.display_name, \'\'), u.email, \'\') AS who FROM event_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.event_id = ? ORDER BY h.id DESC LIMIT 100');
        try { $st->execute([$id]); return $st->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable) { $st = $pdo->prepare('SELECT h.*, \'\' AS who FROM event_history h WHERE h.event_id = ? ORDER BY h.id DESC LIMIT 100'); $st->execute([$id]); return $st->fetchAll(PDO::FETCH_ASSOC); }
    }

    /** "Sat Dec 19, 7:00 PM MST" in the venue's zone (CLAUDE.md "Times where they happened"), all day as the date alone. */
    public static function when(array $e): string
    {
        $zone = (string) ($e['zone'] ?: 'America/Phoenix');
        try { $z = new DateTimeZone($zone); } catch (Throwable) { $z = new DateTimeZone('America/Phoenix'); }
        $s = new DateTime((string) $e['starts_at'], $z);
        if ((int) $e['all_day']) {
            $out = $s->format('D M j, Y');
            if (!empty($e['ends_at']) && substr((string) $e['ends_at'], 0, 10) !== substr((string) $e['starts_at'], 0, 10)) $out .= ' to ' . (new DateTime((string) $e['ends_at'], $z))->format('D M j');
            return $out . ', all day';
        }
        $out = $s->format('D M j, Y, g:i A');
        if (!empty($e['ends_at'])) { $en = new DateTime((string) $e['ends_at'], $z); $out .= ' to ' . ($en->format('Y-m-d') === $s->format('Y-m-d') ? $en->format('g:i A') : $en->format('D M j, g:i A')); }
        return $out . ' ' . $s->format('T');
    }

    // ---- what the public sees (E2: the event's page, its listing's "Coming up here", its place's guide) ---------------------

    /** A public event: published or cancelled (shown as cancelled), its host listing up. Null otherwise. */
    public static function publicGet(PDO $pdo, int $id): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare("SELECT ev.*, e.name AS host_name, e.micro_cluster_id FROM events ev JOIN entities e ON e.id = ev.entity_id
                              WHERE ev.id = ? AND ev.status IN ('published', 'cancelled') AND e.is_suppressed = 0");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Has it ended (its end, else its start, more than a day ago in its own time)? */
    public static function over(array $e): bool
    {
        try { $z = new DateTimeZone((string) ($e['zone'] ?: 'America/Phoenix')); } catch (Throwable) { $z = new DateTimeZone('America/Phoenix'); }
        $end = new DateTime((string) ($e['ends_at'] ?: $e['starts_at']), $z);
        if ((int) $e['all_day'] || !$e['ends_at']) $end->setTime(23, 59);
        return $end->getTimestamp() < time();
    }

    /** What's coming up at a listing, soonest first: published and cancelled events not yet over. */
    public static function forEntity(PDO $pdo, int $entity, int $n = 5): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare("SELECT ev.*, e.name AS host_name FROM events ev JOIN entities e ON e.id = ev.entity_id WHERE ev.entity_id = ? AND ev.status IN ('published', 'cancelled')
                              AND e.is_suppressed = 0 AND COALESCE(ev.ends_at, ev.starts_at) >= UTC_TIMESTAMP() - INTERVAL 2 DAY ORDER BY ev.starts_at LIMIT " . max(1, min(40, $n * 2)));
        $st->execute([$entity]);
        return array_slice(array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn ($r) => !self::over($r))), 0, $n);
    }

    /**
     * What's coming up in a place's guide: events at listings in it. A town's guide (cluster) has its own listings'; an
     * area (geo hub) and a region (hub) everything in their towns. Other places (a city or neighborhood guide) have none yet.
     */
    public static function forPlace(PDO $pdo, array $p, int $n = 8): array
    {
        if (!self::ready($pdo) || !preg_match('/^(cluster|geo_hub|hub):(\d{1,9})$/', (string) ($p['key'] ?? ''), $m)) return [];
        $where = ['cluster' => 'e.micro_cluster_id = ?', 'geo_hub' => 'e.micro_cluster_id IN (SELECT id FROM micro_clusters WHERE geo_hub_id = ?)',
                  'hub' => 'e.micro_cluster_id IN (SELECT mc.id FROM micro_clusters mc JOIN geo_hubs g ON g.id = mc.geo_hub_id WHERE g.hub_id = ?)'][$m[1]];
        try {
            $st = $pdo->prepare("SELECT ev.*, e.name AS host_name FROM events ev JOIN entities e ON e.id = ev.entity_id WHERE ev.status IN ('published', 'cancelled') AND e.is_suppressed = 0
                                  AND $where AND COALESCE(ev.ends_at, ev.starts_at) >= UTC_TIMESTAMP() - INTERVAL 2 DAY ORDER BY ev.starts_at LIMIT " . max(1, min(60, $n * 2)));
            $st->execute([(int) $m[2]]);
        } catch (Throwable) { return []; }
        return array_slice(array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn ($r) => !self::over($r))), 0, $n);
    }

    /** The linked ticket item, when it's still published: [id, title, url]; null otherwise (the page then says no tickets here). */
    public static function ticketOf(PDO $pdo, array $e): ?array
    {
        if (empty($e['ticket_item_id'])) return null;
        try {
            $st = $pdo->prepare("SELECT id, title FROM market_items WHERE id = ? AND entity_id = ? AND status = 'published'"); $st->execute([(int) $e['ticket_item_id'], (int) $e['entity_id']]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) { return null; }
        return $r ? ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'url' => '/market/item.php?id=' . (int) $r['id']] : null;
    }

    /** The signed-in viewer's own Marketplace order holding tickets for this event: its token (for market/order.php?t=), or ''. */
    public static function buyerOrder(PDO $pdo, array $e, int $uid): string
    {
        if (!$uid || empty($e['ticket_item_id'])) return '';
        try {
            $st = $pdo->prepare('SELECT o.token FROM market_tickets t JOIN market_orders o ON o.id = t.order_id WHERE t.item_id = ? AND o.buyer_id = ? ORDER BY o.id DESC LIMIT 1');
            $st->execute([(int) $e['ticket_item_id'], $uid]);
            return (string) ($st->fetchColumn() ?: '');
        } catch (Throwable) { return ''; }
    }

    /** Events as cards, in the same look as the crawler's Live events (LiveEvents::html), each opening its own page. */
    public static function html(array $rows, bool $venue = true): string
    {
        if (!$rows) return '';
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $h = '<div class="lv-ev">';
        foreach ($rows as $r) {
            try { $z = new DateTimeZone((string) ($r['zone'] ?: 'America/Phoenix')); } catch (Throwable) { $z = new DateTimeZone('America/Phoenix'); }
            $s = new DateTime((string) $r['starts_at'], $z); $off = $r['status'] === 'cancelled';
            $h .= '<article class="lv-ev-c tv-bleed' . ($off ? ' ev-off' : '') . '"><time datetime="' . $e($s->format('c')) . '"><b>' . $e($s->format('j')) . '</b>' . $e($s->format('M')) . '</time><div>'
                . '<a class="lv-ev-n" href="/event/?id=' . (int) $r['id'] . '">' . (!empty($r['part_of']) ? 'Part of ' : '') . $e($r['title']) . '</a>'
                . '<p class="lv-ev-m">' . ($off ? '<b class="ev-x">Cancelled</b> · ' : '') . $e(self::when($r)) . ($venue || !empty($r['part_of']) ? ' · <a href="/listing/view.php?id=' . (int) $r['entity_id'] . '">' . $e($r['host_name'] ?? '') . '</a>' : '')
                . ' · ' . ($r['ticket_item_id'] ? 'Tickets' : 'Free') . '</p>'
                . ((string) $r['about'] !== '' ? '<p class="lv-ev-s">' . $e(mb_strimwidth((string) $r['about'], 0, 160, '…')) . '</p>' : '') . '</div></article>';
        }
        // the cards' look is Live events' own (LiveEvents::html), carried here too so a page with only host events has it
        return $h . '</div><style>.lv-ev{display:grid;gap:.5rem;margin:.4rem 0 .8rem}.lv-ev-c{display:flex;gap:.75rem;background:#FFFBF2;border:1px solid #EADFC4;border-radius:.7rem;padding:.6rem .7rem}
.lv-ev-c time{flex:0 0 3.1rem;text-align:center;background:#F7EFDC;border-radius:.5rem;padding:.3rem 0;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#8A7355;line-height:1.1}
.lv-ev-c time b{display:block;font:700 1.35rem var(--tv-font-head,Georgia,serif);color:#92400E;letter-spacing:0}.lv-ev-n{font:700 1.02rem var(--tv-font-head,Georgia,serif);color:#92400E;text-decoration:underline}
.lv-ev-m{margin:.15rem 0 0;font-size:.85rem;color:#5C4632}.lv-ev-m a{color:#92400E;text-decoration:underline}.lv-ev-s{margin:.25rem 0 0;font-size:.9rem}
@media (max-width:767px){.lv-ev{gap:3px}.lv-ev-c{border-radius:.5rem}}
.ev-off .lv-ev-n{text-decoration:line-through}.ev-x{color:var(--tv-crimson,#991B1B)}</style>';
    }

    // ---- businesses taking part (decisions/0134's note #1589, P1): the host invites, each answers, both can step back ----
    //   invited → accepted (with its offer) | declined; accepted → removed (by the host) | left (by the business); each of
    //   those two has Undo (back to accepted). A listing nobody has claimed can't answer: its invitation waits ('waiting')
    //   until we can reach unclaimed businesses by email (SES), and says so. Its offer (the CMO's ruling, 0134's note #1594):
    //   one of its own discount codes (BizCodes, decisions/0121; the business's Discount codes) and/or a short line in its
    //   owner's words, shown as written beside its GO Code. The code's words are kept with it (the event page can't read a
    //   business's walled data, 0110) and checked again each time its team opens Events: a code switched off or ended comes
    //   off, said there. Editorial (12) checks only for an offer that misleads; none reads as paid or ranked (0007).

    public const PART = ['waiting' => 'Waiting to be sent', 'invited' => 'Invited', 'accepted' => 'Taking part', 'declined' => 'Declined', 'removed' => 'Removed by the host', 'left' => 'Left'];

    public static function partsReady(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM event_participants LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /** An event's participants with their listing's name, newest first (for the host); $public: taking part only. */
    public static function participants(PDO $pdo, int $event, bool $public = false): array
    {
        if (!self::partsReady($pdo)) return [];
        $st = $pdo->prepare('SELECT p.*, e.name, e.status AS listing_status FROM event_participants p JOIN entities e ON e.id = p.entity_id WHERE p.event_id = ?' . ($public ? " AND p.status = 'accepted' AND e.is_suppressed = 0" : '') . ' ORDER BY ' . ($public ? 'e.name' : 'p.id DESC'));
        $st->execute([$event]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Invitations to a business (not its own events'), with the event and its host, soonest first. */
    public static function invitationsFor(PDO $pdo, int $entity): array
    {
        if (!self::partsReady($pdo)) return [];
        $st = $pdo->prepare("SELECT p.*, ev.title, ev.starts_at, ev.ends_at, ev.all_day, ev.zone, ev.status AS event_status, h.name AS host_name FROM event_participants p JOIN events ev ON ev.id = p.event_id JOIN entities h ON h.id = ev.entity_id
                              WHERE p.entity_id = ? AND p.status IN ('invited', 'accepted', 'declined', 'left', 'removed') AND ev.status IN ('published', 'cancelled', 'draft') ORDER BY ev.starts_at");
        $st->execute([$entity]);
        return array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn ($r) => !self::over($r)));
    }

    /** The host invites a listing. Returns the words for the notice. */
    public static function invite(PDO $pdo, int $uid, int $host, int $event, int $entity, string $tz = ''): string
    {
        [$ok, $why] = self::canPost($pdo, $uid, $host); if (!$ok) throw new InvalidArgumentException($why);
        $ev = self::get($pdo, $event);
        if (!$ev || (int) $ev['entity_id'] !== $host) throw new InvalidArgumentException('That event isn\'t this business\'s.');
        if (in_array($ev['status'], ['cancelled', 'deleted'], true) || self::over($ev)) throw new InvalidArgumentException('Invite businesses to an event that\'s still on.');
        if ($entity === $host) throw new InvalidArgumentException('Your own business is the host: it\'s part of it already.');
        $st = $pdo->prepare('SELECT id, name, status, is_suppressed FROM entities WHERE id = ?'); $st->execute([$entity]); $l = $st->fetch(PDO::FETCH_ASSOC);
        if (!$l || (int) $l['is_suppressed']) throw new InvalidArgumentException('Choose a business from the list as you type.');
        $st = $pdo->prepare('SELECT status FROM event_participants WHERE event_id = ? AND entity_id = ?'); $st->execute([$event, $entity]); $was = $st->fetchColumn();
        if ($was !== false && !in_array($was, ['declined'], true)) return $l['name'] . ' is already on the list (' . strtolower(self::PART[$was] ?? $was) . ').';
        $to = $l['status'] === 'unclaimed' ? 'waiting' : 'invited';
        $pdo->prepare('INSERT INTO event_participants (event_id, entity_id, status, invited_by, invited_at, changed_at, changed_tz) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)
                       ON DUPLICATE KEY UPDATE status = VALUES(status), prev_status = status, invited_by = VALUES(invited_by), invited_at = VALUES(invited_at), changed_at = VALUES(changed_at), changed_tz = VALUES(changed_tz)')
            ->execute([$event, $entity, $to, $uid, self::tzOk($tz)]);
        self::log($pdo, $event, $uid, 'invited', (string) $l['name'], $tz);
        if ($to === 'waiting') return 'Added. ' . $l['name'] . ' hasn\'t claimed its listing, so the invitation waits until we can email businesses that haven\'t: we\'ll send it then.';
        self::tell($pdo, $ev, (int) $l['id'], $uid);
        return 'Invited. ' . $l['name'] . '\'s team is told, and answers in their own business portal.';
    }

    /** The invited business answers: accept (with its offer line) or decline; or leaves, or undoes leaving. */
    public static function answer(PDO $pdo, int $uid, int $entity, int $pid, string $what, string $offer = '', string $tz = '', ?string $code = null): string
    {
        [$ok, $why] = self::canPost($pdo, $uid, $entity); if (!$ok) throw new InvalidArgumentException($why);
        $p = self::part($pdo, $pid); if (!$p || (int) $p['entity_id'] !== $entity) throw new InvalidArgumentException('That invitation isn\'t this business\'s.');
        $offer = trim(preg_replace('/\s+/', ' ', $offer));
        if (mb_strlen($offer) > 140) throw new InvalidArgumentException('Keep the offer to 140 characters.');
        if (preg_match('~(https?://|www\.)\S+~i', $offer)) throw new InvalidArgumentException('No links in the offer: its GO Code takes people to you.');
        $withOffer = in_array($what, ['accept', 'offer'], true);
        $c = ['', '', null];   // the code, its words, its last day
        if ($withOffer && $code !== null && trim($code) !== '') {
            $all = self::codeChoices($pdo); $k = strtoupper(trim($code));
            if (!isset($all[$k])) throw new InvalidArgumentException('Choose one of your own discount codes that\'s switched on and not ended (Discount codes).');
            $c = [$k, $all[$k]['words'], $all[$k]['ends']];
        } elseif ($withOffer && $code === null) $c = [(string) $p['offer_code'], (string) $p['offer_code_words'], $p['offer_code_ends'] ?: null];   // left as it was
        $moves = ['accept' => [['invited', 'declined'], 'accepted', 'You\'re taking part. Your business shows on the event\'s page' . ($offer !== '' || $c[0] !== '' ? ', with your offer.' : '.')], 'decline' => [['invited'], 'declined', 'Declined. The host can ask again.'],
                  'leave' => [['accepted'], 'left', 'You\'ve left the event. Undo puts you back.'], 'rejoin' => [['left'], 'accepted', 'Back in: you\'re taking part again.'], 'offer' => [['accepted'], 'accepted', 'Your offer is saved.']];
        if (!isset($moves[$what])) throw new InvalidArgumentException('Unknown action.');
        [$from, $to, $words] = $moves[$what];
        if (!in_array($p['status'], $from, true)) return 'Already ' . strtolower(self::PART[$p['status']] ?? $p['status']) . ': nothing to change.';
        $pdo->prepare('UPDATE event_participants SET status = ?, prev_status = ?, answered_by = ?, changed_at = UTC_TIMESTAMP(), changed_tz = ?' . ($withOffer ? ', offer_text = ?, offer_code = ?, offer_code_words = ?, offer_code_ends = ?' : '') . ' WHERE id = ?')
            ->execute(array_merge([$to, $p['status'], $uid, self::tzOk($tz)], $withOffer ? [$offer, $c[0], $c[1], $c[2]] : [], [$pid]));
        self::log($pdo, (int) $p['event_id'], $uid, ['accept' => 'accepted', 'decline' => 'declined', 'leave' => 'left', 'rejoin' => 'rejoined', 'offer' => 'offer'][$what], (string) $p['name'], $tz, $what === 'offer' ? self::offerWords((string) $p['offer_text'], (string) $p['offer_code']) : null);
        return $words;
    }

    /**
     * The event page's Taking part (P2, 0134's note #1594): the businesses taking part, by name (never ranked, 0007), each
     * with what its card shows: its town, its actions (ListingActions: Call, Directions, Website, Contact, as everywhere),
     * its offer (its own line and/or code, shown as written; none for a listing nobody has claimed; a code past its last
     * day not shown) and its GO Code (the short address of its listing page). [] for a cancelled event or one that's over.
     */
    public static function publicParticipants(PDO $pdo, array $ev, ?array $viewer = null): array
    {
        if (!self::partsReady($pdo) || $ev['status'] !== 'published' || self::over($ev)) return [];
        $st = $pdo->prepare("SELECT p.entity_id, p.offer_text, p.offer_code, p.offer_code_words, p.offer_code_ends, e.name, e.status, e.category_id, e.latitude, e.longitude, (SELECT CONCAT(TRIM(z.city), '|', TRIM(z.state)) FROM zip_coordinates z WHERE TRIM(z.zip) = TRIM(e.primary_zip) LIMIT 1) AS town
                              FROM event_participants p JOIN entities e ON e.id = p.entity_id
                              WHERE p.event_id = ? AND p.status = 'accepted' AND e.is_suppressed = 0 AND e.status IN ('unclaimed', 'claimed-unverified', 'pending-verification', 'verified') ORDER BY e.name");
        $st->execute([(int) $ev['id']]);
        try { $today = (new DateTime('now', new DateTimeZone((string) ($ev['zone'] ?: 'America/Phoenix'))))->format('Y-m-d'); } catch (Throwable) { $today = gmdate('Y-m-d'); }
        require_once __DIR__ . '/ListingActions.php'; require_once __DIR__ . '/ShortCodes.php';
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['entity_id'];
            $acts = ListingActions::actions($pdo, $id, $viewer);
            $mine = (string) $r['status'] !== 'unclaimed';   // a listing nobody runs now makes no offer (the CMO's ruling)
            if (!$mine) { $r['offer_text'] = ''; $r['offer_code'] = ''; }
            $code = (string) $r['offer_code'] !== '' ? self::liveCode($id, (string) $r['offer_code']) : null;   // read as it stands now
            $go = ''; try { $go = ShortCodes::forPage($pdo, '/listing/view.php?id=' . $id); } catch (Throwable $x) { error_log('Events go code: ' . $x->getMessage()); }
            $out[] = ['id' => $id, 'name' => (string) $r['name'], 'town' => (string) $r['town'] !== '' ? (function ($t) { [$c, $st] = array_pad(explode('|', $t, 2), 2, ''); return ucwords(strtolower($c)) . ($st !== '' ? ', ' . $st : ''); })((string) $r['town']) : '', 'acts' => $acts,
                      'offer' => (string) $r['offer_text'], 'code' => $code, 'go' => $go,
                      // a stop for Send to Maps only where its card has Directions (a private location or a mailbox has none)
                      'stop' => isset($acts['directions']) && $r['latitude'] !== null && $r['longitude'] !== null ? ['lat' => $r['latitude'], 'lon' => $r['longitude'], 'name' => (string) $r['name']] : null];
        }
        return $out;
    }

    /** The events a listing takes part in, still to come: "Part of <event>" on its page (0134's note). */
    public static function partOf(PDO $pdo, int $entity, int $n = 5): array
    {
        if (!self::partsReady($pdo)) return [];
        $st = $pdo->prepare("SELECT ev.*, h.name AS host_name, 1 AS part_of FROM event_participants p JOIN events ev ON ev.id = p.event_id JOIN entities h ON h.id = ev.entity_id
                              WHERE p.entity_id = ? AND p.status = 'accepted' AND ev.status = 'published' AND h.is_suppressed = 0 AND COALESCE(ev.ends_at, ev.starts_at) >= UTC_TIMESTAMP() - INTERVAL 2 DAY ORDER BY ev.starts_at LIMIT " . max(1, min(40, $n * 2)));
        $st->execute([$entity]);
        return array_slice(array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn ($r) => !self::over($r))), 0, $n);
    }

    /**
     * A business's discount code as it stands now, read when a page is drawn (the session manager, 2026-11-27: words follow
     * the state): from its walled data through the business gate, as MarketCheckout::writeOrder writes there (0110:
     * the listings login on a connection of its own, set to that business by @tv_tenant, read only through its b_ view).
     * The code only while it's switched on, in its dates and not used up, with its last day; null otherwise, and null
     * when the data can't be reached (nothing shown rather than something untrue).
     */
    public static function liveCode(int $entity, string $code, ?PDO $db = null): ?array
    {
        static $own = null, $seen = [];
        $code = strtoupper(trim($code));
        if ($code === '' || $entity <= 0) return null;
        $k = $entity . ':' . $code; $live = $db === null;   // only the page's own reads are remembered (a passed-in connection never fills the cache)
        if ($live && array_key_exists($k, $seen)) return $seen[$k];
        if ($live && $own === false) return null;   // the listings data couldn't be reached once this request: not tried again for each business
        $c = null;
        try {
            if ($live) { if ($own === null) { require_once __DIR__ . '/BusinessData.php'; try { $own = Database::realmOwn('listings'); } catch (Throwable $x) { $own = false; throw $x; } } $db = $own; }
            $db->prepare('SET @tv_tenant = ?')->execute([$entity]);
            try {   // one row: the code this business attached, and only the columns that decide whether it holds
                $st = $db->prepare('SELECT code, active, starts, ends, max_uses, uses FROM `b_biz_codes` WHERE code = ? LIMIT 1'); $st->execute([$code]);
                $c = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } finally { $db->exec('SET @tv_tenant = NULL'); }   // never left set for the next query on this connection
        } catch (Throwable $x) { error_log('Events::liveCode ' . $entity . ': ' . $x->getMessage()); if ($live) $seen[$k] = null; return null; }
        $today = date('Y-m-d');
        $ok = $c && (int) $c['active'] && !($c['starts'] && (string) $c['starts'] > $today) && !($c['ends'] && (string) $c['ends'] < $today) && !((int) $c['max_uses'] && (int) $c['uses'] >= (int) $c['max_uses']);
        $r = $ok ? ['code' => (string) $c['code'], 'ends' => $c['ends'] ?: null] : null;   // only these two reach a page: no counts, no other codes
        if ($live) $seen[$k] = $r;
        return $r;
    }

    /** "code SHOW10 until Dec 31": after the business's own words, the code and its last day only (the Design reviewer). */
    public static function codeWords(array $c): string
    {
        return 'code ' . $c['code'] . ($c['ends'] ? ' until ' . date('M j', strtotime((string) $c['ends'])) : '');
    }

    /** An offer in words, for History: 'the line' · code CODE. */
    public static function offerWords(string $text, string $code): string
    {
        return implode(' · ', array_filter([$text !== '' ? '"' . $text . '"' : '', $code !== '' ? 'code ' . $code : '']));
    }

    /**
     * The business's own discount codes it can offer: switched on and not ended (BizCodes, decisions/0121). Only inside the
     * business portal, where Scope reads that business's codes; [] elsewhere. [CODE => [words, ends]].
     */
    public static function codeChoices(PDO $pdo): array
    {
        if (!is_file(__DIR__ . '/BizCodes.php')) return [];
        require_once __DIR__ . '/BizCodes.php';
        if (!class_exists('Scope') || !Scope::isBusiness()) return [];
        $out = []; $today = date('Y-m-d');
        try { $rows = BizCodes::all($pdo); } catch (Throwable $x) { error_log('Events::codeChoices: ' . $x->getMessage()); return []; }
        foreach ($rows as $r) {
            if (!(int) $r['active'] || ($r['ends'] && (string) $r['ends'] < $today) || ((int) $r['max_uses'] && (int) ($r['uses'] ?? 0) >= (int) $r['max_uses'])) continue;
            $w = BizCodes::offer((string) $r['kind'], (int) $r['value']) . ((int) $r['min_order_cents'] ? ' orders of $' . number_format((int) $r['min_order_cents'] / 100, 2) . ' or more' : '')
                . ($r['starts'] && (string) $r['starts'] > $today ? ', from ' . date('M j', strtotime((string) $r['starts'])) : '') . ($r['ends'] ? ', until ' . date('M j', strtotime((string) $r['ends'])) : '');
            $out[(string) $r['code']] = ['words' => $w, 'ends' => $r['ends'] ?: null];
        }
        return $out;
    }

    /**
     * Each time the business's team opens Events: its offered codes checked against its Discount codes. A code switched
     * off, ended or used up comes off the event (the line stays); a changed one takes its new words. Returns what came off.
     */
    public static function recheckCodes(PDO $pdo, int $entity): array
    {
        if (!self::partsReady($pdo) || !class_exists('Scope') || !Scope::isBusiness()) return [];
        $st = $pdo->prepare("SELECT p.id, p.offer_code, p.offer_code_words, ev.title FROM event_participants p JOIN events ev ON ev.id = p.event_id WHERE p.entity_id = ? AND p.offer_code <> ''");
        $st->execute([$entity]); $rows = $st->fetchAll(PDO::FETCH_ASSOC); if (!$rows) return [];
        $all = self::codeChoices($pdo); $gone = [];
        foreach ($rows as $r) {
            $k = (string) $r['offer_code'];
            if (isset($all[$k])) { if ($all[$k]['words'] !== (string) $r['offer_code_words']) $pdo->prepare('UPDATE event_participants SET offer_code_words = ?, offer_code_ends = ? WHERE id = ?')->execute([$all[$k]['words'], $all[$k]['ends'], (int) $r['id']]); continue; }
            $pdo->prepare("UPDATE event_participants SET offer_code = '', offer_code_words = '', offer_code_ends = NULL WHERE id = ?")->execute([(int) $r['id']]);
            $gone[] = $k . ' (' . $r['title'] . ')';
        }
        return $gone;
    }

    /** The host takes a business off the event, or undoes it. */
    public static function hostMove(PDO $pdo, int $uid, int $host, int $pid, string $what, string $tz = ''): string
    {
        [$ok, $why] = self::canPost($pdo, $uid, $host); if (!$ok) throw new InvalidArgumentException($why);
        $p = self::part($pdo, $pid); $ev = $p ? self::get($pdo, (int) $p['event_id']) : null;
        if (!$ev || (int) $ev['entity_id'] !== $host) throw new InvalidArgumentException('That isn\'t one of your event\'s businesses.');
        $moves = ['remove' => [['accepted', 'invited', 'waiting'], 'removed', 'Removed from the event. Undo puts it back.'], 'unremove' => [['removed'], '', 'Put back as it was.']];
        if (!isset($moves[$what])) throw new InvalidArgumentException('Unknown action.');
        [$from, $to, $words] = $moves[$what];
        if (!in_array($p['status'], $from, true)) return 'Already ' . strtolower(self::PART[$p['status']] ?? $p['status']) . ': nothing to change.';
        if ($to === '') $to = in_array($p['prev_status'], ['accepted', 'invited', 'waiting'], true) ? $p['prev_status'] : 'invited';
        $pdo->prepare('UPDATE event_participants SET status = ?, prev_status = ?, changed_at = UTC_TIMESTAMP(), changed_tz = ? WHERE id = ?')->execute([$to, $p['status'], self::tzOk($tz), $pid]);
        self::log($pdo, (int) $p['event_id'], $uid, $what === 'remove' ? 'removed' : 'put back', (string) $p['name'], $tz);
        return $words;
    }

    /** Listings to invite, by name: [id, name, town], public ones only, never the host itself. */
    public static function findListings(PDO $pdo, string $q, int $host): array
    {
        $q = trim($q); if (mb_strlen($q) < 2) return [];
        $st = $pdo->prepare("SELECT e.id, e.name, TRIM(z.city) AS city, TRIM(z.state) AS state FROM entities e LEFT JOIN zip_coordinates z ON TRIM(z.zip) = TRIM(e.primary_zip)
                              WHERE e.name LIKE ? AND e.is_suppressed = 0 AND e.id <> ? ORDER BY e.name LIKE ? DESC, e.name LIMIT 8");
        $st->execute(['%' . $q . '%', $host, $q . '%']);
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'town' => $r['city'] ? ucwords(strtolower((string) $r['city'])) . ', ' . $r['state'] : ''], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function part(PDO $pdo, int $pid): ?array
    {
        $st = $pdo->prepare('SELECT p.*, e.name FROM event_participants p JOIN entities e ON e.id = p.entity_id WHERE p.id = ?'); $st->execute([$pid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A participants' move in the event's History: field 'part:<move>', the business named in the old value. */
    private static function log(PDO $pdo, int $event, int $uid, string $move, string $name, string $tz, ?string $offerWas = null): void
    {
        $pdo->prepare('INSERT INTO event_history (event_id, at, tz, user_id, field, old_value) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?)')->execute([$event, self::tzOk($tz), $uid, 'part:' . $move, $name . ($offerWas !== null ? '|' . $offerWas : '')]);
    }

    /** The invited business's team is told (its roles with Catalog & content), through the one dispatcher. */
    private static function tell(PDO $pdo, array $ev, int $entity, int $uid): void
    {
        try {
            require_once __DIR__ . '/Notify.php';
            $host = (string) ($pdo->query('SELECT name FROM entities WHERE id = ' . (int) $ev['entity_id'])->fetchColumn() ?: 'A business');
            Notify::send($pdo, 'biz.event_invite', ['entity' => $entity, 'except' => [$uid]],
                ['thing' => 'event-invite:' . (int) $ev['id'] . ':' . $entity, 'url' => '/business/' . $entity . '/events', 'from' => ['entity', (int) $ev['entity_id'], $host]],
                ['title' => $host . ' invites you to take part in ' . $ev['title'], 'body' => self::when($ev) . '. Accept with your offer, or decline, in your business portal → Events.']);
        } catch (Throwable $x) { error_log('event invite tell: ' . $x->getMessage()); }
    }

    // ---- the form's values ---------------------------------------------------------------------------------------------

    private static function clean(PDO $pdo, int $entity, array $in): array
    {
        $title = trim(preg_replace('/\s+/', ' ', (string) ($in['title'] ?? '')));
        if ($title === '') throw new InvalidArgumentException('Give the event a name.');
        if (mb_strlen($title) > 120) throw new InvalidArgumentException('Keep the name to 120 characters.');
        $about = trim((string) ($in['about'] ?? '')); if (mb_strlen($about) > 2000) throw new InvalidArgumentException('Keep the description to 2,000 characters.');
        $show = trim((string) ($in['showtimes'] ?? '')); if (mb_strlen($show) > 600) throw new InvalidArgumentException('Keep the showtimes to 600 characters.');
        $kind = (string) ($in['kind'] ?? ''); if (!isset(self::KINDS[$kind])) throw new InvalidArgumentException('Choose what kind of event it is.');
        $age = (string) ($in['age'] ?? 'all'); if (!isset(self::AGES[$age])) $age = 'all';
        $allDay = !empty($in['all_day']) ? 1 : 0;
        $fmt = $allDay ? 'Y-m-d' : 'Y-m-d\TH:i';
        $start = DateTime::createFromFormat('!' . $fmt, substr(trim((string) ($in['starts_at'] ?? '')), 0, $allDay ? 10 : 16));
        if (!$start) throw new InvalidArgumentException($allDay ? 'Choose the day it starts.' : 'Choose the day and time it starts.');
        $endRaw = trim((string) ($in['ends_at'] ?? '')); $end = null;
        if ($endRaw !== '') {
            $end = DateTime::createFromFormat('!' . $fmt, substr($endRaw, 0, $allDay ? 10 : 16));
            if (!$end) throw new InvalidArgumentException('That end isn\'t a date' . ($allDay ? '' : ' and time') . '.');
            if ($end < $start) throw new InvalidArgumentException('It ends before it starts. Check the end.');
        }
        foreach (['about' => $about, 'showtimes' => $show, 'title' => $title] as $f => $t) {
            if (preg_match('~(https?://|www\.)\S+~i', $t)) throw new InvalidArgumentException('No links in the ' . ($f === 'about' ? 'description' : $f) . ': an event\'s page shows only what\'s here, and tickets sold here (decisions/0134). Take the link out.');
            if (preg_match('/\b(?:pay|paid|buy|tickets?|cash|admission)\b[^.\n]{0,30}\b(?:at the door|box office)\b|\b(?:at the door|box office)\b[^.\n]{0,30}\b(?:pay|tickets?|cash|admission)\b/i', $t))
                throw new InvalidArgumentException('No "pay at the door" or box office notes, please. If you sell tickets for this event, link your ticket item; showtimes are shown as information only (decisions/0134).');
        }
        $ticket = (int) ($in['ticket_item_id'] ?? 0);
        if ($ticket && !isset(self::ticketItems($pdo, $entity)[$ticket])) throw new InvalidArgumentException('Choose one of your own published ticket items, or none.');
        $photo = (int) ($in['gallery_item_id'] ?? 0);
        if ($photo) {   // the business's own photo only
            require_once __DIR__ . '/Gallery.php'; $gi = Gallery::item($pdo, $photo);
            if (!$gi || $gi['owner_kind'] !== 'businesses' || (string) $gi['owner_ref'] !== (string) $entity) throw new InvalidArgumentException('Choose a cover photo from this business\'s own photos.');
        }
        return ['title' => $title, 'about' => $about, 'kind' => $kind, 'starts_at' => $start->format('Y-m-d H:i:00'), 'ends_at' => $end ? $end->format('Y-m-d H:i:00') : null,
            'all_day' => $allDay, 'age' => $age, 'showtimes' => $show, 'ticket_item_id' => $ticket ?: null, 'gallery_item_id' => $photo ?: null];
    }

    /** The Marketplace's rules read on an event's words (0134 §2's automated checks): '' when fine, else why it can't publish yet. */
    private static function checkWords(PDO $pdo, string $title, string $about): string
    {
        try {
            require_once __DIR__ . '/MarketRules.php';
            $r = MarketRules::check($pdo, $title, $about);
            if (in_array($r['tier'] ?? 'ok', ['prohibited', 'review'], true))
                return 'A word in it ("' . (string) ($r['word'] ?? '') . '") is one our team checks first, so it can\'t publish yet. Change it, or ask our team to look.';
        } catch (Throwable) {}
        return '';
    }

    private static function tzOk(string $tz): string { return preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+){0,2}$~', $tz) ? $tz : ''; }
}
