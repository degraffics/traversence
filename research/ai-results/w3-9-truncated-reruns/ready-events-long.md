=== FILE: files/app/lib/Events.php ===
<?php
// app/lib/Events.php — events a business posts (decisions/0134, its minimum before Jan 1: the note of 2026-11-27, option
// (a), piloted by the Hometown theater). One store, `events`, for host-posted events; the crawler's Live events stay in
// their own store for Jan 1 (that note: "Live events stay separate from host events", the merge moves to Q1).
//   Who posts:  a business's team on a claimed listing, with Catalog & content to edit (Team::can, which holds an unclaimed
//               public listing at view; 0134 §2's Owner, Manager, Creator through their roles' permissions), and the person's
//               own Verification on (0062; 0134 §2 "Every host's poster is a verified member"; Jason's answer 1). The
//               Verification lookup is connected at start-up (Events::verifiedBy); until it is, posting is refused.
//   What:       title, a short description, a kind (0134 §3), when (start, end or all day, in the venue's own time zone:
//               CLAUDE.md "Times where they happened"), who for (all ages, 18+, 21+), a cover photo (the one uploader,
//               target event_photo), showtimes as information only, and a link to one of the host's own published
//               Event tickets items (ItemKinds 'ticket', bought on the Marketplace path). Free events otherwise: no price.
//   Never:      links to outside ticket sellers or "pay at the door" notes (0134 §3; the note: showtimes are information
//               only, with no ticket link and no door note). Words the Marketplace holds or blocks are held the same way,
//               on Publish, on Put back on, and on every edit of an event that's public (0134 §2, §7).
//   States:     draft → published ⇄ draft (Unpublish); a published event can be cancelled (it shows as cancelled, its way
//               back is Put back on); a draft can be deleted (kept 30 days with Put back). Each change of what a person
//               entered keeps the old value in its History (CLAUDE.md "Edit or Undo"). An event whose tickets are still on
//               sale can't be cancelled, and a published event whose tickets are sold can't change its date or time, until
//               buyers can be told (0134 §6, §6a).
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

    /** How a person's Verification (decisions/0062) is read: fn (PDO, int $uid): bool, set once at start-up. Null: not connected. */
    private static ?Closure $verified = null;

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM events LIMIT 0'); $pdo->query('SELECT 1 FROM event_history LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /** Connects the Verification lookup (0062): the existing one, wired where the app starts. Null disconnects it. */
    public static function verifiedBy(?callable $f): void { self::$verified = $f === null ? null : Closure::fromCallable($f); }

    /** Is this person's Verification on? Null when it can't be told (not connected, or the lookup failed): never read as yes. */
    public static function memberVerified(PDO $pdo, int $uid): ?bool
    {
        if (self::$verified === null) return null;
        if ($uid <= 0) return false;
        try { return (bool) (self::$verified)($pdo, $uid); } catch (Throwable $x) { error_log('events verification: ' . $x->getMessage()); return null; }
    }

    /** May this person post for this listing? [yes, why not]. The listing must be claimed; the person verified; the role needs Catalog & content to edit. */
    public static function canPost(PDO $pdo, int $uid, int $entity): array
    {
        $st = $pdo->prepare('SELECT status FROM entities WHERE id = ?'); $st->execute([$entity]); $status = (string) $st->fetchColumn();
        if ($status === 'unclaimed') return [false, 'Events are posted by a business that has claimed its listing. Claim it first.'];
        $v = self::memberVerified($pdo, $uid);
        if ($v === null) return [false, 'We can\'t check your Verification right now, so events can\'t be posted. Try again in a few minutes; if it stays, tell us at support@traversence.com.'];
        if (!$v) return [false, 'Turn on Verification for your own account to post events: everyone who posts an event is a verified member.'];
        require_once __DIR__ . '/Team.php';
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

    /** How many orders hold tickets of this ticket item (market_tickets: a code per seat, made when the order is paid). 0 when it can't be read. */
    public static function soldFor(PDO $pdo, int $item): int
    {
        if ($item <= 0) return 0;
        try { $st = $pdo->prepare('SELECT COUNT(DISTINCT order_id) FROM market_tickets WHERE item_id = ?'); $st->execute([$item]); return (int) $st->fetchColumn(); }
        catch (Throwable $x) { error_log('events sold count: ' . $x->getMessage()); return 0; }
    }

    /** Why a ticketed event can't be cancelled yet ('' when it can): its ticket item still on sale (0134 §6a: a cancelled event refunds everyone). */
    public static function ticketBlock(?array $ticket, int $sold): string
    {
        if ($ticket === null) return '';
        return 'Its tickets ("' . (string) $ticket['title'] . '") are still on sale on the Marketplace. Unpublish that item first (Market 360° → Marketplace)'
            . ($sold > 0 ? ', then refund or credit each of the ' . $sold . ' order' . ($sold === 1 ? '' : 's') . ' holding tickets from your Marketplace orders' : '') . '; then cancel the event.';
    }

    /** The reminder after cancelling an event people hold tickets for ('' when none). */
    public static function soldNote(int $sold): string
    {
        if ($sold <= 0) return '';
        return ' People hold tickets from ' . $sold . ' order' . ($sold === 1 ? '' : 's') . ': refund or credit each from your Marketplace orders, and tell them. We can\'t tell them for you yet.';
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
            if (in_array((string) $old['status'], ['published', 'cancelled'], true)) {   // what's public is checked as Publish checks it (0134 §2, §7)
                $keys = array_keys($changed);
                $when = (bool) array_intersect($keys, ['starts_at', 'ends_at', 'all_day']);
                $words = (bool) array_intersect($keys, ['title', 'about', 'showtimes']);
                $p = self::publicProblem($pdo, $v + $old, $when, $words, 'Its page is public, and that date has passed. Choose a date ahead.');
                if ($p !== '') throw new InvalidArgumentException($p);
                if ($when && (string) $old['status'] === 'published' && !empty($old['ticket_item_id']) && ($n = self::soldFor($pdo, (int) $old['ticket_item_id'])) > 0)
                    throw new InvalidArgumentException('People already hold tickets for this event (' . $n . ' order' . ($n === 1 ? '' : 's') . '), and we can\'t tell them of a new date or time from here yet. '
                        . 'Tell each buyer from your Marketplace orders, then ask us to change it with you: support@traversence.com.');
            }
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
        if ($what === 'publish' || $what === 'restore') {   // both make it public: the same checks (0134 §2, §7)
            $p = self::publicProblem($pdo, $e, true, true, 'This event is in the past. Change its date to ' . ($what === 'publish' ? 'publish it.' : 'put it back on.'));
            if ($p !== '') throw new InvalidArgumentException($p);
        }
        $after = '';
        if ($what === 'cancel' && !empty($e['ticket_item_id'])) {   // tickets on sale for a cancelled night: never (0134 §6a)
            $sold = self::soldFor($pdo, (int) $e['ticket_item_id']);
            $b = self::ticketBlock(self::ticketOf($pdo, $e), $sold);
            if ($b !== '') throw new InvalidArgumentException($b);
            $after = self::soldNote($sold);
        }
        $pdo->prepare('UPDATE events SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()' . ($to === 'deleted' ? ', deleted_at = UTC_TIMESTAMP()' : ($what === 'undelete' ? ', deleted_at = NULL' : '')) . ($to === 'published' && !$e['published_at'] ? ', published_at = UTC_TIMESTAMP()' : '') . ' WHERE id = ? AND status = ?')
            ->execute([$to, $uid, $id, $s]);
        $pdo->prepare('INSERT INTO event_history (event_id, at, tz, user_id, field, old_value) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?)')->execute([$id, self::tzOk($tz), $uid, $what, $s]);   // the move by name, with the state it left (the Design reviewer, 2026-11-27)
        return $words . $after;
    }

    /** A History line's words: the move named, with the state it left in brackets, as the state line names it ("cancelled it (was Published)"). */
    public const MOVE_WORDS = ['publish' => 'published it', 'unpublish' => 'unpublished it', 'cancel' => 'cancelled it', 'restore' => 'put it back on', 'delete' => 'deleted it', 'undelete' => 'put it back'];
    public static function historyWords(array $x): string
    {
        $f = (string) $x['field']; $was = $x['old_value'];
        if ($f === 'created') return 'added it';
        if (isset(self::MOVE_WORDS[$f])) return self::MOVE_WORDS[$f] . ' (was ' . (self::STATUSES[(string) $was] ?? (string) $was) . ')';
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
                . '<a class="lv-ev-n" href="/event/?id=' . (int) $r['id'] . '">' . $e($r['title']) . '</a>'
                . '<p class="lv-ev-m">' . ($off ? '<b class="ev-x">Cancelled</b> · ' : '') . $e(self::when($r)) . ($venue ? ' · <a href="/listing/view.php?id=' . (int) $r['entity_id'] . '">' . $e($r['host_name'] ?? '') . '</a>' : '')
                . ($off ? '' : ' · ' . ($r['ticket_item_id'] ? 'Tickets' : 'Free')) . '</p>'   // a cancelled night never reads as on sale
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

    // ---- the rules on what an event says (0134 §3), shared with the crawler's Live events ---------------------------------

    /**
     * What breaks 0134 §3 in a piece of text: 'link' (a web address, with or without http or www), 'seller' (an outside
     * ticket seller named), 'door' (pay at the door, a box office, cash or a payment app), or '' when it's fine.
     */
    public static function noteProblem(string $t): string
    {
        if (trim($t) === '') return '';
        if (preg_match('~(https?://|www\.)\S+~i', $t) || preg_match('~\b[a-z0-9][a-z0-9-]*\.(?:com|org|net|us|co|io|ly|biz|info|tickets|events|live|tv|me|app|gg|link)\b~i', $t)) return 'link';
        if (preg_match('/\b(?:eventbrite|ticketmaster|fandango|ticket\s?tailor|ticketleap|etix|showclix|brown paper tickets|atom tickets|stubhub|seatgeek|vivid\s?seats|tixr|ticketweb|axs)\b/i', $t)) return 'seller';
        foreach (['/\b(?:pay|paid|payment|buy|tickets?|tix|cash|admission|entry|cover)\b[^.\n]{0,30}\b(?:at the (?:door|gate)|box office)\b/i',
                  '/\b(?:at the (?:door|gate)|box office)\b[^.\n]{0,30}\b(?:pay|paid|buy|tickets?|tix|cash|admission|entry|cover)\b/i',
                  '/\$\s?\d[\d.,]*[^.\n]{0,25}\bat the (?:door|gate)\b/i',
                  '/\bat the (?:door|gate)\b[^.\n]{0,25}\$\s?\d/i',
                  '/\b(?:door|gate)\s+(?:sales|price|prices|tickets?|admission|charge)\b/i',
                  '/\bcash only\b/i',
                  '/\bpay(?:able)?\s+(?:on|upon)\s+(?:arrival|entry)\b/i',
                  '/\b(?:venmo|cash\s?app|zelle|paypal)\b/i'] as $p) if (preg_match($p, $t)) return 'door';
        return '';
    }

    /**
     * Why an event can't be public as it stands ('' when it can): its date passed ($date: checked), or a word our team
     * checks first ($words: checked). The same for Publish, Put back on and an edit of a public event (0134 §2, §7).
     */
    public static function publicProblem(PDO $pdo, array $e, bool $date, bool $words, string $pastWords = 'This event is in the past. Change its date to publish it.'): string
    {
        if ($date && self::over($e)) return $pastWords;
        if ($words) return self::checkWords($pdo, (string) ($e['title'] ?? ''), (string) ($e['about'] ?? '') . "\n" . (string) ($e['showtimes'] ?? ''));
        return '';
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
            $where = $f === 'about' ? 'description' : $f;
            $why = self::noteProblem($t);
            if ($why === 'link') throw new InvalidArgumentException('No links in the ' . $where . ': an event\'s page shows only what\'s here, and tickets sold here (decisions/0134). Take the link out.');
            if ($why === 'seller') throw new InvalidArgumentException('No outside ticket sellers in the ' . $where . ': tickets for an event here are sold here, through your Event tickets item (decisions/0134).');
            if ($why === 'door') throw new InvalidArgumentException('No "pay at the door" or box office notes, please. If you sell tickets for this event, link your ticket item; showtimes are shown as information only (decisions/0134).');
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
=== END FILE ===
=== FILE: files/app/lib/LiveEvents.php ===
<?php
// app/lib/LiveEvents.php — Live events, the AI Content Crawler's second template (decisions/0133; Jason, 2026-10-30): what's
// coming up in each place with a live guide. Free sources first: the place's own official site, its events and calendar
// pages, read by our crawler. Claude reads those and lists the events with their dates, searching the web only for what
// they don't list, and names only events a page announces (each keeps the page that says so). Each is checked: a date
// ahead and inside the window, a page that's a web address, a venue matched to our listings where we have it; a name that
// may mark a protected place, or a ceremony or sacred observance, waits for a person (a nation lists its own: 0134 §2). An
// event shows in the guide's Events section and on its venue's listing until the day after it ends. Its price and summary
// never carry a door note, a box office, an outside seller or a link (0134 §3); the page that announces it is its citation.
// Where the venue posted the same event itself, the host's words win and this one isn't shown (0134 §1). Our team can take
// one down, and it stays down. Run by the guide job, one place a run, each place every week, only while the template is set
// running.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ContentTemplates.php';

final class LiveEvents
{
    public const STATUSES = ['live' => 'Showing', 'held' => 'Waiting for a person', 'expired' => 'Past', 'removed' => 'Taken down'];
    /** Words that may mark a ceremony or sacred observance: held for a person (0134 §2's tribal nation row; 0066). */
    public const SACRED = '/\b(?:ceremon(?:y|ies|ial)|sacred|feast day|blessing)\b/i';

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT 1 FROM live_events LIMIT 0'); $pdo->query('SELECT 1 FROM live_event_places LIMIT 0'); $ok = true; } catch (Throwable $e) { $ok = false; } }
        return $ok;
    }

    /** The template in use: the saved one over the code's, part by part ($spec: one being shaped, for a preview or a try). */
    public static function template(PDO $pdo, ?array $spec = null): array
    {
        $code = ContentTemplates::liveEvents(); $t = null;
        if ($spec === null) { try { $t = ContentTemplates::get($pdo, 'live_events'); } catch (Throwable $e) {} $spec = $t ? (array) $t['spec'] : []; }
        foreach ($spec as $part => $v) if (is_array($v) && isset($code[$part]) && is_array($code[$part])) $code[$part] = array_replace($code[$part], $v); elseif ($v !== null) $code[$part] = $v;
        $code['status'] = $t ? (string) $t['status'] : 'draft';
        return $code;
    }

    /** Past events become past; returns how many. */
    public static function expire(PDO $pdo): int
    {
        if (!self::ready($pdo)) return 0;
        return (int) $pdo->exec("UPDATE live_events SET status = 'expired', updated_at = NOW() WHERE status IN ('live', 'held') AND COALESCE(ends_on, starts_on) < CURDATE()");
    }

    /** The guide job's share: one place a run (the template's), each place with a live guide every refresh_days, oldest first. */
    public static function tick(PDO $pdo, int $n = 1): int
    {
        if (!self::ready($pdo)) return 0;
        self::expire($pdo);
        $tpl = self::template($pdo);
        if ($tpl['status'] !== 'active') return 0;   // being shaped or off: it runs only when someone tries it
        require_once __DIR__ . '/AiClient.php';
        if (!AiClient::allow($pdo, 'live_events')[0]) return 0;
        require_once __DIR__ . '/Guides.php';
        $tiers = array_map('intval', (array) ($tpl['applies']['auto_tiers'] ?? [4, 5])); $days = max(1, (int) ($tpl['runs']['refresh_days'] ?? 7));
        $due = $pdo->query("SELECT g.place_key FROM place_guides g LEFT JOIN live_event_places e ON e.place_key = g.place_key
                             WHERE g.status = 'live' AND (e.checked_at IS NULL OR e.checked_at < " . $pdo->quote((require_once __DIR__ . '/Clocks.php') ? Clocks::sqlCutoff($pdo, 'W10', $days) : '') . ") ORDER BY e.checked_at IS NOT NULL, e.checked_at LIMIT 40")->fetchAll(PDO::FETCH_COLUMN);
        $done = 0;
        foreach ($due as $key) {
            if ($done >= max(1, min(5, (int) ($tpl['runs']['per_run'] ?? $n)))) break;
            $tier = Guides::TIERS[explode(':', (string) $key)[0]][0] ?? 0;
            if (!in_array($tier, $tiers, true)) continue;
            // a failed check (the service erred, not "found nothing") is tried again the next day, not after the full refresh_days
            // (decisions/0163's source map, 2026-11-17): its checked time is set back so it falls due a day from now; a check
            // that found nothing waits the full refresh_days, as before
            try { self::compose($pdo, (string) $key, null, 'write'); $done++; }
            catch (Throwable $e) {
                $back = max(0, $days - 1);
                error_log('live events ' . $key . ': ' . $e->getMessage() . ' (tried again in a day)');
                $pdo->prepare('INSERT INTO live_event_places (place_key, checked_at) VALUES (?, NOW() - INTERVAL ' . $back . ' DAY) ON DUPLICATE KEY UPDATE checked_at = NOW() - INTERVAL ' . $back . ' DAY')->execute([$key]);
            }
        }
        return $done;
    }

    /** What Claude would be sent for this place and what it would cost, spending nothing. */
    public static function preview(PDO $pdo, string $key, ?array $spec = null): array { return self::compose($pdo, $key, $spec, 'preview'); }

    /** Finds this place's events under $spec without saving them (Content templates → Try it). */
    public static function tryTemplate(PDO $pdo, string $key, ?array $spec): array { return self::compose($pdo, $key, $spec, 'try'); }

    /** 'write' saves what it finds, 'preview' builds the prompt only, 'try' finds without saving. */
    private static function compose(PDO $pdo, string $key, ?array $spec, string $mode): array
    {
        require_once __DIR__ . '/Guides.php';
        require_once __DIR__ . '/Dossier.php';
        require_once __DIR__ . '/AiClient.php';
        $p = Guides::place($pdo, $key) ?: throw new RuntimeException('That place isn\'t here.');
        $tpl = self::template($pdo, $spec); $w = (array) ($tpl['write'] ?? []);
        [$ok, $why] = AiClient::allow($pdo, 'live_events');
        if (!$ok && $mode !== 'preview') throw new RuntimeException($why);
        $fc = AiClient::feature($pdo, 'live_events');
        $days = max(7, (int) ($tpl['runs']['horizon_days'] ?? 120));
        // free sources first: the official site's event pages (the place's own, else its towns')
        $pages = [];
        foreach (self::sites($pdo, $p) as $site) { foreach (Dossier::eventPages($pdo, $site, (string) ($p['state'] ?? ''), 3) as $pg) $pages[] = $pg; if (count($pages) >= 4) break; }
        $venues = self::venues($pdo, $p);
        $fields = array_values(array_filter((array) ($w['fields'] ?? []), fn ($f) => !array_key_exists('on', $f) || $f['on'] || !empty($f['required'])));
        $prompt = "You are finding events for the Traversence guide to " . $p['name'] . ($p['state'] ? ', ' . $p['state'] : '') . ". Today is " . date('Y-m-d') . ".\n\n"
            . str_replace(['{place}', '{days}'], [(string) $p['name'], (string) $days], (string) ($w['purpose'] ?? '')) . "\n\n"
            . (string) ($w['voice'] ?? '') . "\n\n"
            . "FOR EACH EVENT:\n" . implode("\n", array_map(fn ($f) => '- ' . $f['key'] . ': ' . $f['instruction'], $fields)) . "\n\n"
            . "RULES:\n- Only events dated from today to " . date('Y-m-d', strtotime('+' . $days . ' days')) . ". Never guess a date: an event without one stays out.\n"
            . "- Only events a page below or a page you cite announces; give that page's address.\n"
            . "- Never list a tribal nation's ceremonies, feasts or sacred observances, or anything at a sacred or burial place: a nation lists its own. Leave out anything private.\n"
            . "- price: what it costs only (Free, \$10). Never where or how to pay: no box office, no door, no outside ticket seller, no link. summary: what it is, never how to buy.\n"
            . "- Spell a venue as in OUR LISTINGS when it's one of them.\n"
            . ($pages ? "- Read the pages below first. Search the web only for what they don't list.\n" : "- There are no official event pages to read: search the web (the town's, county's, chamber's, fairgrounds' and nations' own pages first).\n")
            . "\n" . ($venues ? "OUR LISTINGS HERE (venues):\n" . implode('; ', array_slice($venues, 0, 60)) . "\n\n" : '')
            . implode("\n\n", array_map(fn ($pg) => "PAGE " . $pg[0] . ":\n" . $pg[1], $pages));
        $prompt = mb_substr($prompt, 0, 50000);
        $searches = max(0, min((int) $fc['searches'], (int) ($tpl['gather']['searches'] ?? 3)));
        $maxTok = max(1000, min(16000, (int) ($w['max_tokens'] ?? 4000)));
        if ($mode === 'preview') {
            $in = (int) ceil(mb_strlen($prompt) / 3.6);
            return ['passes' => [['sections' => ['events'], 'prompt' => $prompt, 'searches' => $searches, 'max_tokens' => $maxTok, 'in' => $in, 'out' => min($maxTok, 1500),
                                  'usd' => AiClient::estimate((string) $fc['model'], $in, min($maxTok, 1500), 0), 'usd_max' => AiClient::estimate((string) $fc['model'], $in + 2500 * $searches, $maxTok, $searches)]],
                    'model' => (string) $fc['model'], 'research_held' => true, 'brief_usd' => 0.0, 'pages' => count($pages), 'blocked' => $ok ? '' : $why, 'say' => '',
                    'estimate' => [AiClient::estimate((string) $fc['model'], $in, min($maxTok, 1500), 0), AiClient::estimate((string) $fc['model'], $in + 2500 * $searches, $maxTok, $searches)]];
        }
        $props = []; foreach (['name', 'date_start', 'date_end', 'time', 'venue', 'price', 'summary', 'source'] as $k) $props[$k] = ['type' => 'string'];
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['events'], 'properties' => ['events' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($props), 'properties' => $props]]]];
        $r = AiClient::claudeAsk($prompt, $schema, (string) $fc['model'], $searches, $maxTok, 'low');
        AiClient::record($pdo, 'live_events', 0, 'done', $r);
        $evidence = implode("\n", array_column($pages, 1));
        $rows = []; $dropped = [];
        foreach (array_slice((array) ($r['data']['events'] ?? []), 0, 40) as $ev) {
            [$row, $no] = self::check($pdo, $p, (array) $ev, $days, $evidence);
            if ($row) $rows[] = $row; else $dropped[] = trim((string) ($ev['name'] ?? '?')) . ': ' . $no;
        }
        if ($mode === 'try') return ['events' => $rows, 'dropped' => $dropped, 'micros' => (int) ($r['micros'] ?? 0), 'pages' => count($pages), 'say' => count($rows) . ' event' . (count($rows) === 1 ? '' : 's') . ' found' . ($dropped ? ', ' . count($dropped) . ' left out by the checks' : '') . '.'];
        $kept = 0;
        foreach ($rows as $row) $kept += self::keep($pdo, $key, $row);
        $pdo->prepare('INSERT INTO live_event_places (place_key, checked_at, found, cost_micros) VALUES (?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE checked_at = NOW(), found = VALUES(found), cost_micros = cost_micros + VALUES(cost_micros)')->execute([$key, count($rows), (int) ($r['micros'] ?? 0)]);
        Guides::forget($pdo, $key);
        return ['say' => $kept . ' event' . ($kept === 1 ? '' : 's') . ' kept.', 'events' => $rows, 'dropped' => $dropped, 'micros' => (int) ($r['micros'] ?? 0)];
    }

    /**
     * An event as Claude gave it, checked: [row, ''] or [null, why it's left out]. A date ahead inside the window, a page that's
     * a web address, a name; a venue matched to our listings where we have it. A name that may mark a protected place, or a
     * ceremony or sacred observance, is held for a person (status held). Its price and summary are scrubbed of door notes,
     * outside sellers and links (scrub).
     */
    public static function check(PDO $pdo, array $p, array $ev, int $days, string $evidence = ''): array
    {
        $clip = fn ($v, int $n) => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $v))), 0, $n);
        $name = $clip($ev['name'] ?? '', 160); $src = trim((string) ($ev['source'] ?? ''));
        if ($name === '') return [null, 'no name'];
        $d = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $v)) && strtotime(trim((string) $v)) ? date('Y-m-d', strtotime(trim((string) $v))) : null;
        $start = $d($ev['date_start'] ?? ''); $end = $d($ev['date_end'] ?? '');
        if (!$start) return [null, 'no date'];
        if ($end !== null && $end < $start) $end = null;
        if (($end ?? $start) < date('Y-m-d')) return [null, 'already past'];
        if ($start > date('Y-m-d', strtotime('+' . $days . ' days'))) return [null, 'beyond the window'];
        if (!preg_match('#^https?://[^\s/]+\.[^\s/]+#i', $src)) return [null, 'no page that announces it'];
        $venue = $clip($ev['venue'] ?? '', 160); $vid = null;
        if ($venue !== '' && $p['lat'] !== null) {
            try { $st = $pdo->prepare('SELECT e.id FROM entities e WHERE e.is_suppressed = 0 AND e.name = ? AND ' . $p['sql'] . ' LIMIT 1'); $st->execute([$venue]); $vid = ($x = $st->fetchColumn()) ? (int) $x : null; } catch (Throwable $e) {}
        }
        require_once __DIR__ . '/Landmarks.php'; Landmarks::loadPeoples($pdo);
        $summary = $clip($ev['summary'] ?? '', 600);
        $sacred = (bool) preg_match(self::SACRED, $name . ' ' . $summary);
        $held = $sacred || Landmarks::isProtected($name . ' ' . $venue . ' ' . $summary);
        return [self::scrub(['title' => $name, 'starts_on' => $start, 'ends_on' => $end, 'time_text' => $clip($ev['time'] ?? '', 60), 'venue_name' => $venue, 'venue_entity_id' => $vid,
                 'price_text' => $clip($ev['price'] ?? '', 80), 'summary' => $summary, 'source_url' => mb_substr($src, 0, 500),
                 'status' => $held ? 'held' : 'live', 'why' => $sacred ? 'It may be a ceremony or sacred observance: a nation lists its own (decisions/0134 §2), so a person looks first.'
                     : ($held ? 'Its name or venue may mark a protected place: a person looks first.' : '')]), ''];
    }

    /**
     * 0134 §3 on what the crawler copied: a price that names the door, a box office, an outside seller or a link is cleared,
     * and the summary's sentences that do are left out. Used when an event is found and again when it's shown (rows kept
     * before this rule).
     */
    public static function scrub(array $r): array
    {
        require_once __DIR__ . '/Events.php';
        if ((string) ($r['price_text'] ?? '') !== '' && Events::noteProblem((string) $r['price_text']) !== '') $r['price_text'] = '';
        if ((string) ($r['summary'] ?? '') !== '')
            $r['summary'] = implode(' ', array_filter(preg_split('/(?<=[.!?])\s+/u', (string) $r['summary']) ?: [], fn ($s) => trim($s) !== '' && Events::noteProblem($s) === ''));
        return $r;
    }

    /** Saves an event (one row per place, name and first day). One our team took down stays down. Returns 1 if it's new. */
    private static function keep(PDO $pdo, string $key, array $r): int
    {
        $hash = md5($key . '|' . mb_strtolower($r['title']) . '|' . $r['starts_on']);
        $st = $pdo->prepare('SELECT status FROM live_events WHERE hash = ?'); $st->execute([$hash]); $was = $st->fetchColumn();
        if ($was === 'removed') return 0;
        // why is set before status: MySQL reads the assignments left to right, so why must see the status the row had (0134 §7: a held one keeps its reason)
        $pdo->prepare('INSERT INTO live_events (place_key, title, starts_on, ends_on, time_text, venue_name, venue_entity_id, price_text, summary, source_url, status, why, hash, found_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE ends_on = VALUES(ends_on), time_text = VALUES(time_text), venue_name = VALUES(venue_name), venue_entity_id = VALUES(venue_entity_id), price_text = VALUES(price_text),
                         summary = VALUES(summary), source_url = VALUES(source_url), why = IF(status = \'held\', why, VALUES(why)), status = IF(status = \'held\', \'held\', VALUES(status)), updated_at = NOW()')   // one waiting for a person keeps waiting
            ->execute([$key, $r['title'], $r['starts_on'], $r['ends_on'], $r['time_text'], $r['venue_name'], $r['venue_entity_id'], $r['price_text'], $r['summary'], $r['source_url'], $r['status'], $r['why'], $hash]);
        return $was === false ? 1 : 0;
    }

    /** The official sites to read: the place's own (Wikidata's official website, in its dossier), else its towns'. */
    private static function sites(PDO $pdo, array $p): array
    {
        require_once __DIR__ . '/Dossier.php';
        $out = [];
        $f = Dossier::facts($pdo, (string) $p['key']); if (!empty($f['website'])) $out[] = (string) $f['website'];
        if (!$out) foreach (array_slice(Guides::children($pdo, $p), 0, 3) as $k) { $t = Dossier::facts($pdo, (string) $k[0]); if (!empty($t['website'])) $out[] = (string) $t['website']; }
        return array_values(array_unique($out));
    }

    /** Our listings that could host an event here (things to do, places to eat, stay and shop), by name. */
    private static function venues(PDO $pdo, array $p): array
    {
        $out = [];
        foreach (['do', 'eat', 'stay', 'shop'] as $sec) foreach (Guides::picks($pdo, $p, $sec, 15, 'v') as $r) $out[] = (string) $r['name'];
        return array_values(array_unique($out));
    }

    /** The place's keys that its events count for: itself, its towns and neighborhoods (a community's guide shows its towns'). */
    private static function keysFor(PDO $pdo, array $p): array
    {
        $keys = [(string) $p['key']];
        foreach (Guides::children($pdo, $p) as $k) if (preg_match('/^(town|sub):/', (string) $k[0])) $keys[] = (string) $k[0];
        return $keys;
    }

    /** What's coming up for a place's guide, soonest first. */
    public static function forPlace(PDO $pdo, array $p, int $n = 8): array
    {
        if (!self::ready($pdo)) return [];
        require_once __DIR__ . '/Guides.php';
        $keys = self::keysFor($pdo, $p);
        $st = $pdo->prepare("SELECT * FROM live_events WHERE status = 'live' AND COALESCE(ends_on, starts_on) >= CURDATE() AND place_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ') ORDER BY starts_on, title, place_key = ? DESC LIMIT ' . max(1, min(90, $n * 3)));
        $st->execute(array_merge($keys, [(string) $p['key']]));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);   // a community and its town can both find the same event: shown once; a venue's own post wins
        return self::dedupe($rows, self::hostFor($pdo, array_column($rows, 'venue_entity_id')), $n);
    }

    /** What's coming up at a listing (its venue): the same event once, and none its host posted itself (0134 §1). */
    public static function forEntity(PDO $pdo, int $id, int $n = 5): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare("SELECT * FROM live_events WHERE status = 'live' AND venue_entity_id = ? AND COALESCE(ends_on, starts_on) >= CURDATE() ORDER BY starts_on, title LIMIT " . max(1, min(60, $n * 3)));
        $st->execute([$id]);
        return self::dedupe($st->fetchAll(PDO::FETCH_ASSOC), self::hostFor($pdo, [$id]), $n);
    }

    /**
     * Rows in order, the same event once (its title and first day), and none a venue posted itself the same day under a
     * near-identical title ($host: venue id => [[Y-m-d, title key], …], from hostFor). At most $n.
     */
    public static function dedupe(array $rows, array $host, int $n): array
    {
        $out = []; $seen = [];
        foreach ($rows as $r) {
            $t = self::norm((string) $r['title']); $k = $t . '|' . $r['starts_on'];
            if (isset($seen[$k])) continue;
            $v = (int) ($r['venue_entity_id'] ?? 0);
            if ($v && self::hostHas($host[$v] ?? [], $t, (string) $r['starts_on'])) continue;
            $seen[$k] = true; $out[] = $r;
            if (count($out) >= $n) break;
        }
        return $out;
    }

    /** The venues' own posted events (Events, published or cancelled, not past): venue id => [[Y-m-d, title key], …]. Empty when events aren't there. */
    public static function hostFor(PDO $pdo, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        try {
            require_once __DIR__ . '/Events.php';
            if (!Events::ready($pdo)) return [];
            $st = $pdo->prepare("SELECT entity_id, title, starts_at FROM events WHERE status IN ('published', 'cancelled') AND entity_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')
                                  AND COALESCE(ends_at, starts_at) >= CURDATE() - INTERVAL 1 DAY');
            $st->execute($ids);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['entity_id']][] = [substr((string) $r['starts_at'], 0, 10), self::norm((string) $r['title'])];
            return $out;
        } catch (Throwable $x) { error_log('live events host check: ' . $x->getMessage()); return []; }
    }

    private static function norm(string $t): string { return mb_strtolower((string) preg_replace('/\W+/u', '', $t)); }

    /** Same day, and the titles alike: equal, or one holding the other (both 6 letters or more). */
    private static function hostHas(array $list, string $t, string $day): bool
    {
        foreach ($list as [$d, $h]) {
            if ($d !== $day) continue;
            if ($h === $t) return true;
            if (min(mb_strlen($h), mb_strlen($t)) >= 6 && (str_contains($h, $t) || str_contains($t, $h))) return true;
        }
        return false;
    }

    /** When, said plainly: "Sat, Jun 6" or "Jun 6 – 8" (and the time). */
    public static function when(array $r): string
    {
        $s = strtotime((string) $r['starts_on']); $e = $r['ends_on'] ? strtotime((string) $r['ends_on']) : null;
        $t = $e && $e !== $s ? (date('M', $s) === date('M', $e) ? date('M j', $s) . '–' . date('j', $e) : date('M j', $s) . ' – ' . date('M j', $e)) : date('D, M j', $s);
        return $t . ((string) $r['time_text'] !== '' ? ' · ' . $r['time_text'] : '');
    }

    /** The events as cards: name (the page that announces it), when, where (the listing, when we have it), price, what it is. */
    public static function html(array $rows, bool $venue = true): string
    {
        if (!$rows) return '';
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $h = '<div class="lv-ev">';
        foreach ($rows as $r) {
            $r = self::scrub($r);   // rows kept before the door-note rule are shown by it too
            $where = $venue && (string) $r['venue_name'] !== '' ? ($r['venue_entity_id'] ? '<a href="/listing/view.php?id=' . (int) $r['venue_entity_id'] . '">' . $e($r['venue_name']) . '</a>' : $e($r['venue_name'])) : '';
            $h .= '<article class="lv-ev-c tv-bleed"><time datetime="' . $e($r['starts_on']) . '"><b>' . $e(date('j', strtotime((string) $r['starts_on']))) . '</b>' . $e(date('M', strtotime((string) $r['starts_on']))) . '</time><div>'
                . '<a class="lv-ev-n" href="' . $e($r['source_url']) . '" target="_blank" rel="noopener nofollow">' . $e($r['title']) . '</a>'
                . '<p class="lv-ev-m">' . $e(self::when($r)) . ($where !== '' ? ' · ' . $where : '') . ((string) $r['price_text'] !== '' ? ' · ' . $e($r['price_text']) : '') . '</p>'
                . ((string) $r['summary'] !== '' ? '<p class="lv-ev-s">' . $e($r['summary']) . '</p>' : '') . '</div></article>';
        }
        return $h . '</div><style>.lv-ev{display:grid;gap:.5rem;margin:.4rem 0 .8rem}.lv-ev-c{display:flex;gap:.75rem;background:#FFFBF2;border:1px solid #EADFC4;border-radius:.7rem;padding:.6rem .7rem}
.lv-ev-c time{flex:0 0 3.1rem;text-align:center;background:#F7EFDC;border-radius:.5rem;padding:.3rem 0;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#8A7355;line-height:1.1}
.lv-ev-c time b{display:block;font:700 1.35rem var(--tv-font-head,Georgia,serif);color:#92400E;letter-spacing:0}.lv-ev-n{font:700 1.02rem var(--tv-font-head,Georgia,serif);color:#92400E;text-decoration:underline}
.lv-ev-m{margin:.15rem 0 0;font-size:.85rem;color:#5C4632}.lv-ev-m a{color:#92400E;text-decoration:underline}.lv-ev-s{margin:.25rem 0 0;font-size:.9rem}
@media (max-width:767px){.lv-ev{gap:3px}.lv-ev-c{border-radius:.5rem}}</style>';
    }

    /** Our team: take one down (it stays down) or show one a person was asked to look at. */
    public static function setStatus(PDO $pdo, int $id, string $to, int $uid): void
    {
        if (!in_array($to, ['live', 'removed'], true)) throw new InvalidArgumentException('Show it or take it down.');
        $st = $pdo->prepare('SELECT place_key FROM live_events WHERE id = ?'); $st->execute([$id]);
        $key = $st->fetchColumn(); if ($key === false) throw new InvalidArgumentException('That event isn\'t here.');
        $pdo->prepare("UPDATE live_events SET status = ?, removed_by = ?, why = ?, updated_at = NOW() WHERE id = ?")->execute([$to, $to === 'removed' ? $uid : null, $to === 'removed' ? 'Taken down by our team.' : '', $id]);
        require_once __DIR__ . '/Guides.php'; Guides::forget($pdo, (string) $key);
    }

    /** For the admin list: upcoming and waiting first. */
    public static function list(PDO $pdo, string $status = '', int $n = 200): array
    {
        if (!self::ready($pdo)) return [];
        $w = $status !== '' && isset(self::STATUSES[$status]) ? 'status = ' . $pdo->quote($status) : "status IN ('live', 'held')";
        return $pdo->query("SELECT * FROM live_events WHERE $w ORDER BY status = 'held' DESC, starts_on LIMIT " . max(1, min(500, $n)))->fetchAll(PDO::FETCH_ASSOC);
    }
}
=== END FILE ===
=== FILE: files/app/lib/Engage.php ===
<?php
// api/lib/Engage.php — the standard engagement on every content page (decisions/0058 §20): views (the platform's
// counts, never who), likes (how many; never who), public comments, Share and Report. For listings, outdoor places,
// experiences and place pages; journeys keep their own (Journeys::comments). A comment needs a signed-in member who
// is 18 or older; it can be removed by its writer or by staff, and reported (Admin → Reports). One level of replies
// (decisions/0172 §2.3): a reply tells the comment's writer; a listing's Owner and Managers can hide comments on it.
declare(strict_types=1);

require_once __DIR__ . '/ListingSafety.php';

final class Engage
{
    public const KINDS = ['listing', 'rec', 'exp', 'place', 'lm', 'guide', 'how', 'sj', 'page', 'pg', 'event'];   // event: a business's event (decisions/0134 §5)   // pg: a Personal Guide (decisions/0174 §1)   // page: one of our own pages (SitePages), through its blocks (decisions/0159 step 4d)   // sj: a suggested journey (decisions/0146); 'mkt' (a Marketplace post) has views, Share and Report only; 'group' (a community group, decisions/0137 §4) views, Share and QR
    /** the platform's view-count key for each (decisions/0058 §13) */
    public const COUNT_KEY = ['listing' => 'listing:', 'rec' => 'rec:', 'exp' => 'experience:', 'place' => 'place:', 'lm' => 'landmark:', 'guide' => 'guide:', 'how' => 'how:', 'sj' => 'suggested:', 'pg' => 'personal_guide:', 'mkt' => 'market:', 'group' => 'group:', 'page' => 'page:', 'event' => 'event:'];

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM content_comments LIMIT 1'); $pdo->query('SELECT 1 FROM content_likes LIMIT 1'); return $ok = true; } catch (PDOException) { return $ok = false; }
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    /** A valid target: [kind, ref], or an exception. */
    public static function target(string $kind, string $ref): array
    {
        $ok = match ($kind) {
            'listing', 'exp', 'lm', 'guide', 'how', 'sj', 'page', 'pg', 'photo', 'event' => ctype_digit($ref),   // photo: a gallery item, only through Gallery (decisions/0126), which checks who may see it
            'mp' => ctype_digit($ref),   // a member's post, only through MemberPosts (decisions/0153), which checks who may see it
            'rec' => (bool) preg_match('/^[A-Za-z0-9_-]{1,40}$/', $ref),
            'place' => (bool) preg_match('/^(hub|geo_hub|cluster|town|sub):\d{1,9}$/', $ref),   // town, sub: city and neighborhood guides (decisions/0131)
            default => false,
        };
        if (!$ok) throw new InvalidArgumentException('That can\'t be liked or commented on.');
        return [$kind, $ref];
    }

    /** How many visits saw it (all time), from the platform's counts. */
    public static function views(PDO $pdo, string $kind, string $ref): int
    {
        try {
            require_once __DIR__ . '/PlatformCounts.php';
            return (int) PlatformCounts::total($pdo, 'view', 3650, self::COUNT_KEY[$kind] . $ref)['visits'];
        } catch (Throwable) { return 0; }
    }

    public static function likes(PDO $pdo, string $kind, string $ref): int
    {
        if (!self::ready($pdo)) return 0;
        require_once __DIR__ . '/TestAccounts.php';   // a test account's like isn't counted (decisions/0145 §3)
        $st = $pdo->prepare('SELECT COUNT(*) FROM content_likes l' . (TestAccounts::ready($pdo) ? ' WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.id = l.user_id AND u.is_test = 1) AND' : ' WHERE') . ' l.kind = ? AND l.ref = ?');
        $st->execute([$kind, $ref]);
        return (int) $st->fetchColumn() + self::guestLikes($pdo, $kind, $ref);
    }

    public static function liked(PDO $pdo, string $kind, string $ref, ?int $uid): bool
    {
        if (!$uid || !self::ready($pdo)) return false;
        $st = $pdo->prepare('SELECT 1 FROM content_likes WHERE kind = ? AND ref = ? AND user_id = ?');
        $st->execute([$kind, $ref, $uid]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Like or unlike, signed in. Returns [liked, likes]. A like passes the checks first (LikeGuard: speed, timing, 100 a
     * day), which can throw RuntimeException 428 (the human check) or 429 (today's likes used). Gallery photos are liked
     * inside a gallery its owner shares, so they skip them.
     */
    /**
     * Is there something to like (site audit P11, 2026-11-13: likes were taken for ids nothing has)? Each kind through its
     * own library, so a landmark that isn't shown can't be liked either. True when it can't be told (a library or table
     * missing): a like is never refused for our own gap. Kinds checked elsewhere (photo, mp) or by shape only (rec,
     * place) pass.
     */
    public static function exists(PDO $pdo, string $kind, string $ref): bool
    {
        try {
            switch ($kind) {
                case 'lm': require_once __DIR__ . '/Landmarks.php'; return !Landmarks::ready($pdo) || Landmarks::get($pdo, (int) $ref) !== null;
                case 'pg': require_once __DIR__ . '/Trips.php'; return !Trips::guidesReady($pdo) || Trips::guide($pdo, (int) $ref) !== null;   // published, its trip not deleted
                case 'sj': require_once __DIR__ . '/JourneyPlan.php'; return !JourneyPlan::ready($pdo) || JourneyPlan::get($pdo, (int) $ref) !== null;
                case 'guide': require_once __DIR__ . '/ResourceGuides.php'; return !ResourceGuides::ready($pdo) || ResourceGuides::guide($pdo, (int) $ref) !== null;
                case 'exp': $sql = "SELECT 1 FROM experiences WHERE id = ? AND status = 'published'"; break;
                case 'how': $sql = 'SELECT 1 FROM how_guides WHERE id = ?'; break;
                case 'listing': $sql = 'SELECT 1 FROM entities WHERE id = ? AND is_suppressed = 0'; break;
                case 'event': require_once __DIR__ . '/Events.php'; return Events::publicGet($pdo, (int) $ref) !== null;   // published or cancelled, its host up
                case 'page': $sql = "SELECT 1 FROM site_pages WHERE id = ? AND owner_id = 0 AND status = 'live'"; break;   // ours, and live
                default: return true;
            }
            $st = $pdo->prepare($sql); $st->execute([(int) $ref]);
            return (bool) $st->fetchColumn();
        } catch (Throwable) { return true; }
    }

    public static function toggleLike(PDO $pdo, int $uid, string $kind, string $ref): array
    {
        self::target($kind, $ref);
        $guard = $kind !== 'photo';
        if ($guard) require_once __DIR__ . '/LikeGuard.php';
        if (self::liked($pdo, $kind, $ref, $uid)) {
            $pdo->prepare('DELETE FROM content_likes WHERE kind = ? AND ref = ? AND user_id = ?')->execute([$kind, $ref, $uid]);
            if ($guard) LikeGuard::unrecord($pdo, $uid, $kind, $ref);
        } else {
            if (!self::exists($pdo, $kind, $ref)) throw new InvalidArgumentException('That isn\'t here any more, so it can\'t be liked.');
            if ($guard) LikeGuard::check($pdo, $uid);
            $pdo->prepare('INSERT INTO content_likes (kind, ref, user_id, created_at) VALUES (?, ?, ?, ?)')->execute([$kind, $ref, $uid, self::now()]);
            if ($guard) LikeGuard::record($pdo, $uid, $kind, $ref);
        }
        return [self::liked($pdo, $kind, $ref, $uid), self::likes($pdo, $kind, $ref)];
    }

    /** Guests' likes (decisions/0009; 0058 §20 as changed 2026-11-05): added straight to the item's count. */
    public static function guestLikes(PDO $pdo, string $kind, string $ref): int
    {
        try { $st = $pdo->prepare('SELECT guest_likes FROM content_like_tally WHERE kind = ? AND ref = ?'); $st->execute([$kind, $ref]); return (int) $st->fetchColumn(); }
        catch (PDOException) { return 0; }   // before the migration runs
    }

    /**
     * A guest likes or unlikes (Jason, 2026-11-05). Once per item per visit; up to Guest::PER_ITEM_CONNECTION guest likes
     * on one item from one connection a day (a family or an office on one connection can each like it). After
     * Guest::LIKES_PER_VISIT likes the account panel asks them to join, with Keep going as a guest (a RuntimeException 401;
     * its message ends "|more" when they may keep going), up to Guest::MAX_PER_VISIT. The checks (LikeGuard) watch every
     * guest closely: they can throw 428, the human check. Returns [liked, likes].
     */
    public static function toggleGuestLike(PDO $pdo, string $kind, string $ref): array
    {
        require_once __DIR__ . '/Guest.php';
        require_once __DIR__ . '/LikeGuard.php';
        self::target($kind, $ref);
        if (Guest::isBot()) throw new InvalidArgumentException('That can\'t be liked from here.');
        $k = $kind . ':' . $ref;
        if (!empty(Guest::liked()[$k])) {   // unliking this visit's own like
            $pdo->prepare('UPDATE content_like_tally SET guest_likes = GREATEST(guest_likes, 1) - 1 WHERE kind = ? AND ref = ?')->execute([$kind, $ref]);
            LikeGuard::unrecord($pdo, null, $kind, $ref);
            Guest::setLiked($k, false);
            return [false, self::likes($pdo, $kind, $ref)];
        }
        $n = count(Guest::liked());
        if ($n >= Guest::MAX_PER_VISIT) throw new RuntimeException('That\'s ' . Guest::MAX_PER_VISIT . ' likes this visit. Create a free account to keep liking: it takes a minute.', 401);
        if ($n >= Guest::LIKES_PER_VISIT && !Guest::more()) throw new RuntimeException('You\'re liking a lot of places. Create a free account to keep liking (it takes a minute): likes from an account count wherever you are.|more', 401);
        if (LikeGuard::ready($pdo)) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM like_events WHERE kind = ? AND ref = ? AND ip_key = ? AND src LIKE 'g:%' AND voided_at IS NULL AND created_at >= ?");
            $st->execute([$kind, $ref, LikeGuard::ipKey(), date('Y-m-d 00:00:00')]);
            if ((int) $st->fetchColumn() >= Guest::PER_ITEM_CONNECTION) throw new InvalidArgumentException('Guests on this connection have liked it today already. Create a free account to add yours.');
        }
        if (!self::exists($pdo, $kind, $ref)) throw new InvalidArgumentException('That isn\'t here any more, so it can\'t be liked.');
        LikeGuard::check($pdo, null);
        $pdo->prepare('INSERT INTO content_like_tally (kind, ref, guest_likes) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE guest_likes = guest_likes + 1')->execute([$kind, $ref]);
        LikeGuard::record($pdo, null, $kind, $ref);
        Guest::setLiked($k, true);
        return [true, self::likes($pdo, $kind, $ref)];
    }

    /** The connection check's records go after 30 days (decisions/0009); the likes stay in the counts. */
    public static function purgeGuestConnections(PDO $pdo): int
    {
        $d = $pdo->prepare('DELETE FROM guest_ip_likes WHERE created_at < ?'); $d->execute([date('Y-m-d H:i:s', time() - 30 * 86400)]);
        return $d->rowCount();
    }

    /** Whether replies are switched on (content_comments.parent_id, decisions/0172 §2.3). */
    public static function repliesReady(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT parent_id FROM content_comments LIMIT 0'); $ok = true; } catch (Throwable) { $ok = false; } }
        return $ok;
    }

    /** Whether who hid a comment, and when, is kept (content_comments.hidden_by, hidden_at). */
    public static function hideReady(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) { try { $pdo->query('SELECT hidden_by, hidden_at FROM content_comments LIMIT 0'); $ok = true; } catch (Throwable) { $ok = false; } }
        return $ok;
    }

    /**
     * The visible comments, oldest first: [id, user_id, body, created_at, parent_id, name, team]. One level of replies
     * (decisions/0172 §2.3): a reply carries its comment's id in parent_id, and a reply whose comment is hidden or gone
     * isn't listed. 'team': on a listing, the business's name when the writer is on its team (they answer as themselves,
     * named with the business).
     */
    public static function comments(PDO $pdo, string $kind, string $ref, int $limit = 200): array
    {
        if (!self::ready($pdo)) return [];
        require_once __DIR__ . '/People.php';
        $replies = self::repliesReady($pdo);
        $st = $pdo->prepare('SELECT c.id, c.user_id, c.body, c.created_at' . ($replies ? ', c.parent_id' : '') . " FROM content_comments c WHERE c.kind = ? AND c.ref = ? AND c.status = 'visible'"
            . ($replies ? " AND (c.parent_id IS NULL OR EXISTS (SELECT 1 FROM content_comments p WHERE p.id = c.parent_id AND p.status = 'visible'))" : '') . ' ORDER BY c.created_at, c.id LIMIT ' . max(1, $limit));
        $st->execute([$kind, $ref]);
        $biz = ''; $team = [];
        if ($kind === 'listing') { $s = $pdo->prepare('SELECT name FROM entities WHERE id = ?'); $s->execute([(int) $ref]); $biz = (string) $s->fetchColumn(); $team = self::teamIds($pdo, (int) $ref); }
        return array_map(function ($c) use ($pdo, $biz, $team) {
            $u = People::user($pdo, (int) $c['user_id']);
            $w = (int) $c['user_id'];
            return ['id' => (int) $c['id'], 'user_id' => $w, 'body' => (string) $c['body'], 'created_at' => (string) $c['created_at'], 'parent_id' => isset($c['parent_id']) ? (int) $c['parent_id'] : 0,
                    'name' => $u ? People::name($u) : 'Someone who left', 'team' => $biz !== '' && isset($team[$w]) ? $biz : ''];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function commentCount(PDO $pdo, string $kind, string $ref): int
    {
        if (!self::ready($pdo)) return 0;
        $st = $pdo->prepare("SELECT COUNT(*) FROM content_comments c WHERE c.kind = ? AND c.ref = ? AND c.status = 'visible'"
            . (self::repliesReady($pdo) ? " AND (c.parent_id IS NULL OR EXISTS (SELECT 1 FROM content_comments p WHERE p.id = c.parent_id AND p.status = 'visible'))" : ''));
        $st->execute([$kind, $ref]);
        return (int) $st->fetchColumn();
    }

    /** One comment, any status: [id, kind, ref, user_id, body, status, created_at, parent_id, hidden_by, hidden_at] or null. */
    public static function getComment(PDO $pdo, int $id): ?array
    {
        if (!self::ready($pdo) || $id <= 0) return null;
        $st = $pdo->prepare('SELECT id, kind, ref, user_id, body, status, created_at' . (self::repliesReady($pdo) ? ', parent_id' : '') . (self::hideReady($pdo) ? ', hidden_by, hidden_at' : '') . ' FROM content_comments WHERE id = ?');
        $st->execute([$id]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) return null;
        return ['id' => (int) $c['id'], 'kind' => (string) $c['kind'], 'ref' => (string) $c['ref'], 'user_id' => (int) $c['user_id'], 'body' => (string) $c['body'], 'status' => (string) $c['status'],
                'created_at' => (string) $c['created_at'], 'parent_id' => (int) ($c['parent_id'] ?? 0), 'hidden_by' => (int) ($c['hidden_by'] ?? 0), 'hidden_at' => (string) ($c['hidden_at'] ?? '')];
    }

    /** The visible replies to a comment, oldest first. */
    public static function replies(PDO $pdo, int $parentId, int $limit = 20): array
    {
        if (!self::repliesReady($pdo)) return [];
        require_once __DIR__ . '/People.php';
        $st = $pdo->prepare("SELECT id, user_id, body, created_at FROM content_comments WHERE parent_id = ? AND status = 'visible' ORDER BY created_at, id LIMIT " . max(1, $limit));
        $st->execute([$parentId]);
        return array_map(function ($c) use ($pdo) { $u = People::user($pdo, (int) $c['user_id']); return $c + ['name' => $u ? People::name($u) : 'Someone who left']; }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Where a comment's page is (a path on the site), as the Reports page links it. */
    public static function pageUrl(PDO $pdo, string $kind, string $ref): string
    {
        try {
            if ($kind === 'place') { require_once __DIR__ . '/Links.php'; return (string) Links::resolve($pdo, 'place', $ref)[1]; }
            if ($kind === 'page') { require_once __DIR__ . '/SitePages.php'; $p = SitePages::get($pdo, (int) $ref); return $p ? SitePages::url($p) : '/'; }
        } catch (Throwable) { return '/'; }
        return (['listing' => '/listing/view.php?id=', 'rec' => '/place/recreation.php?id=', 'exp' => '/experiences/view.php?id=', 'lm' => '/place/landmark.php?id=',
                 'guide' => '/guide/resources.php?id=', 'how' => '/guide/how.php?id=', 'sj' => '/journeys/suggested.php?id=', 'pg' => '/guide/personal.php?id=', 'mp' => '/user/post.php?id=',
                 'event' => '/event/?id='][$kind] ?? '/?') . rawurlencode($ref);   // event: a reply's notice and Reports land on the event's own page (the promise check, 0181)
    }

    /**
     * Posts a comment, or ($parentId) a reply to one: one level, so a reply to a reply joins its comment. Who hears
     * (decisions/0172 §2.3): a reply → the comment's writer (comment.reply), never themselves; a comment on a listing → the
     * business's team (biz.comment), replies too; a comment on an experience → its writer (content.comment).
     */
    public static function comment(PDO $pdo, int $uid, string $kind, string $ref, string $body, int $parentId = 0): array
    {
        require_once __DIR__ . '/Standing.php'; Standing::guard($pdo, $uid);   // limited accounts can't post or send (decisions/0079)
        self::target($kind, $ref);
        require_once __DIR__ . '/AgeGate.php';
        AgeGate::require($pdo, $uid);
        $body = mb_substr(trim(strip_tags($body)), 0, 2000);
        if ($body === '') throw new InvalidArgumentException($parentId > 0 ? 'Write a reply first.' : 'Write a comment first.');
        if ($p = ListingSafety::contentProblem($body)) throw new InvalidArgumentException('That comment can\'t be posted: ' . $p . '.');
        $parent = null;
        if ($parentId > 0) {
            if (!self::repliesReady($pdo)) throw new InvalidArgumentException('Replies switch on once the site\'s update has run. Add a comment for now.');
            $parent = self::getComment($pdo, $parentId);
            if ($parent && $parent['parent_id'] > 0) $parent = self::getComment($pdo, $parent['parent_id']);   // one level: a reply to a reply joins its comment
            if (!$parent || $parent['status'] !== 'visible' || $parent['kind'] !== $kind || $parent['ref'] !== $ref) throw new InvalidArgumentException('That comment isn\'t here any more, so it can\'t be answered. Add a comment instead.');
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM content_comments WHERE user_id = ? AND created_at >= ?');
        $st->execute([$uid, date('Y-m-d H:i:s', time() - 3600)]);
        if ((int) $st->fetchColumn() >= 30) throw new InvalidArgumentException('That\'s a lot of comments for one hour. Try again later.');
        if ($parent) $pdo->prepare('INSERT INTO content_comments (kind, ref, user_id, body, created_at, parent_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([$kind, $ref, $uid, $body, self::now(), $parent['id']]);
        else $pdo->prepare('INSERT INTO content_comments (kind, ref, user_id, body, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$kind, $ref, $uid, $body, self::now()]);
        $id = (int) $pdo->lastInsertId();   // read before any other query: MySQL's insert id is reset by the next statement
        require_once __DIR__ . '/People.php';
        $u = People::user($pdo, $uid);
        $name = $u ? People::name($u) : 'Someone';
        $url = self::pageUrl($pdo, $kind, $ref) . '#comments';
        $told = [];
        if ($parent && $parent['user_id'] !== $uid && !People::blocked($pdo, $uid, $parent['user_id'])) {   // a reply to my comment (comment.reply)
            try {
                require_once __DIR__ . '/Notify.php';
                Notify::send($pdo, 'comment.reply', ['users' => [$parent['user_id']]],
                    ['thing' => 'reply:' . $id, 'thread' => 'replies:' . $parent['id'], 'url' => $url, 'from' => ['user', $uid, $name]],
                    ['title' => $name . ' replied to your comment', 'body' => '"' . mb_strimwidth($body, 0, 240, '…') . '"']);
                $told[] = $parent['user_id'];
            } catch (Throwable $x) { error_log('comment reply notify: ' . $x->getMessage()); }
        }
        if ($kind === 'listing') {   // a comment on a listing is a notice to its business, in its Pulse (decisions/0172 §2.3, gap 3); email if they chose it. Replies too: the business still hears.
            try {
                require_once __DIR__ . '/Notify.php';
                $st = $pdo->prepare('SELECT name FROM entities WHERE id = ?'); $st->execute([(int) $ref]);
                $biz = (string) $st->fetchColumn();
                Notify::send($pdo, 'biz.comment', ['entity' => (int) $ref, 'except' => [$uid]],
                    ['thing' => 'comment:' . $id, 'thread' => 'comments:' . $ref, 'url' => '/listing/view.php?id=' . (int) $ref, 'from' => ['user', $uid, $name]],
                    ['title' => $name . ($parent ? ' replied in the comments on ' : ' commented on ') . ($biz !== '' ? $biz : 'your listing'), 'body' => '"' . mb_strimwidth($body, 0, 240, '…') . '"']);
            } catch (Throwable $x) { error_log('listing comment notify: ' . $x->getMessage()); }
        }
        if ($kind === 'exp') {   // a comment on something a person made: its maker (decisions/0172 §2.3); guides, guides' places and our pages have no maker to tell
            try {
                $st = $pdo->prepare('SELECT author_id, title FROM experiences WHERE id = ?'); $st->execute([(int) $ref]);
                $ex = $st->fetch(PDO::FETCH_ASSOC);
                $maker = $ex ? (int) $ex['author_id'] : 0;
                if ($maker > 0 && $maker !== $uid && !in_array($maker, $told, true) && !People::blocked($pdo, $uid, $maker)) {
                    require_once __DIR__ . '/Notify.php';
                    Notify::send($pdo, 'content.comment', ['users' => [$maker]],
                        ['thing' => 'comment:' . $id, 'thread' => 'comments:exp:' . $ref, 'url' => $url, 'from' => ['user', $uid, $name]],
                        ['title' => $name . ' commented on ' . ((string) $ex['title'] !== '' ? $ex['title'] : 'your experience'), 'body' => '"' . mb_strimwidth($body, 0, 240, '…') . '"']);
                }
            } catch (Throwable $x) { error_log('experience comment notify: ' . $x->getMessage()); }
        }
        return ['id' => $id, 'name' => $u ? People::name($u) : 'You', 'body' => $body, 'parent_id' => $parent ? $parent['id'] : 0];
    }

    /** The writer, or staff who look after content, can remove a comment. */
    public static function removeComment(PDO $pdo, int $uid, int $id): void
    {
        $st = $pdo->prepare('SELECT user_id FROM content_comments WHERE id = ?');
        $st->execute([$id]);
        $who = $st->fetchColumn();
        require_once __DIR__ . '/Journeys.php';
        if ($who === false || ((int) $who !== $uid && !Journeys::staffCan($pdo, $uid, 'journeys'))) throw new InvalidArgumentException('That comment isn\'t yours to remove.');
        $pdo->prepare('DELETE FROM content_comments WHERE id = ?')->execute([$id]);
    }

    /** Hides or shows a comment. $by: who did it (kept, with when, once the update has run: "Hidden by … ."). */
    public static function setCommentHidden(PDO $pdo, int $id, bool $hide, ?int $by = null): void
    {
        if (self::hideReady($pdo)) $pdo->prepare('UPDATE content_comments SET status = ?, hidden_by = ?, hidden_at = ? WHERE id = ?')->execute([$hide ? 'hidden' : 'visible', $hide ? $by : null, $hide ? self::now() : null, $id]);
        else $pdo->prepare('UPDATE content_comments SET status = ? WHERE id = ?')->execute([$hide ? 'hidden' : 'visible', $id]);
    }

    /**
     * The person's role on a business's team (Team's roles, or a role granted on it: Roles), or null. Used for who answers
     * and hides comments on its listing.
     */
    public static function teamRole(PDO $pdo, int $uid, int $entityId): ?string
    {
        if ($uid <= 0 || $entityId <= 0) return null;
        try {
            require_once __DIR__ . '/Team.php';
            $r = Team::roleOf($pdo, $uid, $entityId);
            if ($r !== null && $r !== 'white_glove') return $r;
        } catch (Throwable) {}
        try {
            require_once __DIR__ . '/Roles.php';
            foreach (Roles::team($pdo, 'org', 'entity', (string) $entityId) as $m) if ((int) $m['user_id'] === $uid) return (string) $m['role'];
        } catch (Throwable) {}
        return null;
    }

    /** Everyone on a business's team, as a set [user id => true]: Team's members, the claim's owner and managers, roles granted on it. */
    private static function teamIds(PDO $pdo, int $entityId): array
    {
        $ids = [];
        try { require_once __DIR__ . '/Team.php'; foreach (Team::members($pdo, $entityId) as $m) $ids[(int) $m['user_id']] = true; } catch (Throwable) {}
        try { $st = $pdo->prepare('SELECT user_id FROM listing_access WHERE entity_id = ?'); $st->execute([$entityId]); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) $ids[(int) $u] = true; } catch (Throwable) {}
        try { $st = $pdo->prepare('SELECT owner_user_id FROM entities WHERE id = ?'); $st->execute([$entityId]); if ($o = (int) $st->fetchColumn()) $ids[$o] = true; } catch (Throwable) {}
        try { require_once __DIR__ . '/Roles.php'; foreach (Roles::team($pdo, 'org', 'entity', (string) $entityId) as $m) $ids[(int) $m['user_id']] = true; } catch (Throwable) {}
        return $ids;
    }

    /**
     * Whether this person may hide (or show again) this comment, and if not, why: [yes, why]. Staff who look after
     * content may; on a listing, the business's Owner and Managers may (decisions/0172 §2.3). A comment our team hid
     * after reports stays with our team.
     */
    public static function canHide(PDO $pdo, int $uid, array $c): array
    {
        if (self::canTidy($pdo, $uid)) return [true, ''];
        if ($c['kind'] !== 'listing') return [false, 'Only our team hides comments here. Report it, and we\'ll look.'];
        if (!in_array(self::teamRole($pdo, $uid, (int) $c['ref']), ['owner', 'manager'], true)) return [false, 'The business\'s Owner and Managers hide comments on its listing.'];
        if ($c['status'] === 'hidden' && $c['hidden_by'] !== $uid && ($c['hidden_by'] <= 0 || self::teamRole($pdo, $c['hidden_by'], (int) $c['ref']) === null))
            return [false, 'Our team hid it after it was reported, so it stays hidden. Ask us about it: support@traversence.com.'];
        return [true, ''];
    }

    /** Whether this person may remove comments here (staff who look after content). */
    public static function canTidy(PDO $pdo, ?int $uid): bool
    {
        if (!$uid) return false;
        require_once __DIR__ . '/Journeys.php';
        return Journeys::staffCan($pdo, $uid, 'journeys');
    }
}
=== END FILE ===
=== FILE: tests/events-pilot-rules.php ===
<?php
// tools/events-tests/events-pilot-rules.php — the pilot's gate 1 rules on host events (decisions/0134 §2, §3, §6a, §7):
// Verification before posting, the door/seller/link words, the past-date check for anything public, the cancel block while
// tickets are on sale, and a cancelled card never reading "Tickets". Plain PHP: prints PASS/FAIL per check, exits 1 on any
// FAIL. No money moves: no order, payment, gateway or QuickBooks call is made; ticket counts are passed in, never read.
// The checks that need a database use an in-memory SQLite one (pdo_sqlite); without it they print SKIP.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/Events.php';

$fails = 0;
$t = function (string $name, bool $ok) use (&$fails): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; };

// ---- 0134 §3: no links, outside sellers or door notes
$cases = [
    ['Doors open 6:30. Popcorn on us.', ''],
    ['A community night with dinner nearby first.', ''],
    ['Fri 7:00 PM, Sat 2:00 and 7:00 PM', ''],
    ['St. Johns, AZ: bring a blanket.', ''],
    ['See hometowntheater.com for more', 'link'],
    ['www.example.org', 'link'],
    ['https://x.y/z', 'link'],
    ['Also on Fandango', 'seller'],
    ['Get them through Eventbrite', 'seller'],
    ['Pay at the door', 'door'],
    ['$8 at the door', 'door'],
    ['At the door: $8', 'door'],
    ['Box office opens at 6:30, tickets $8', 'door'],
    ['Door sales only', 'door'],
    ['Cash only, please', 'door'],
    ['Venmo the organizer', 'door'],
    ['Tickets available at our box office', 'door'],
];
foreach ($cases as [$text, $want]) $t('noteProblem("' . $text . '") is "' . $want . '"', Events::noteProblem($text) === $want);

// ---- the past-date check, as Publish, Put back on and an edit of a public event read it
$past = ['zone' => 'America/Phoenix', 'starts_at' => '2020-01-01 19:00:00', 'ends_at' => null, 'all_day' => 0, 'title' => 'x', 'about' => '', 'showtimes' => ''];
$ahead = ['starts_at' => date('Y-m-d H:i:00', time() + 30 * 86400)] + $past;
$sqlite = in_array('sqlite', PDO::getAvailableDrivers(), true) ? new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) : null;
if ($sqlite) {
    $t('a past event is refused with the words given', Events::publicProblem($sqlite, $past, true, false, 'PAST') === 'PAST');
    $t('an event ahead passes the date check', Events::publicProblem($sqlite, $ahead, true, false) === '');
    $t('an edit that leaves the date alone skips the date check', Events::publicProblem($sqlite, $past, false, false) === '');
} else echo "SKIP: publicProblem (no pdo_sqlite)\n";

// ---- 0134 §2: Verification, read through the connected lookup; never yes when it can't be told
if ($sqlite) {
    $sqlite->exec('CREATE TABLE entities (id INTEGER PRIMARY KEY, status TEXT)');
    $sqlite->exec("INSERT INTO entities (id, status) VALUES (1, 'unclaimed'), (2, 'verified')");
    Events::verifiedBy(null);
    [$ok, $why] = Events::canPost($sqlite, 7, 2);
    $t('not connected: refused, never let through', !$ok && str_contains($why, 'Verification'));
    $t('not connected: memberVerified is null', Events::memberVerified($sqlite, 7) === null);
    Events::verifiedBy(fn (PDO $p, int $u): bool => false);
    [$ok, $why] = Events::canPost($sqlite, 7, 2);
    $t('Verification off: refused with how to fix it', !$ok && str_contains($why, 'Turn on Verification'));
    Events::verifiedBy(function (PDO $p, int $u): bool { throw new RuntimeException('lookup down'); });
    $t('the lookup failing reads as unknown, not yes', Events::memberVerified($sqlite, 7) === null);
    [$ok, $why] = Events::canPost($sqlite, 7, 1);
    $t('an unclaimed listing is refused first', !$ok && str_contains($why, 'Claim it first'));
    Events::verifiedBy(fn (PDO $p, int $u): bool => true);
    $t('a guest (uid 0) is never verified', Events::memberVerified($sqlite, 0) === false);
    Events::verifiedBy(null);
} else echo "SKIP: canPost (no pdo_sqlite)\n";

// ---- 0134 §6a: a cancelled night can't leave its tickets on sale
$tk = ['id' => 9, 'title' => 'Community night seats', 'url' => '/market/item.php?id=9'];
$t('cancel blocked while the ticket item is published', str_contains(Events::ticketBlock($tk, 3), 'Unpublish that item first') && str_contains(Events::ticketBlock($tk, 3), '3 orders'));
$t('cancel allowed once the item is off sale', Events::ticketBlock(null, 3) === '');
$t('the reminder names the orders to refund', str_contains(Events::soldNote(1), '1 order:') && Events::soldNote(0) === '');

// ---- a cancelled card never reads as on sale
$row = ['id' => 5, 'entity_id' => 8807, 'host_name' => 'Hometown Theater', 'title' => 'Community night', 'zone' => 'America/Phoenix', 'starts_at' => '2026-12-18 19:00:00',
        'ends_at' => null, 'all_day' => 0, 'ticket_item_id' => 9, 'about' => '', 'status' => 'published'];
$t('a published ticketed card says Tickets', str_contains(Events::html([$row]), '· Tickets'));
$t('a cancelled card doesn\'t', !str_contains(Events::html([['status' => 'cancelled'] + $row]), '· Tickets'));

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===
=== FILE: tests/live-events-pilot.php ===
<?php
// tools/events-tests/live-events-pilot.php — the crawler's Live events on the pilot's path (LiveEvents.php; decisions/0134
// §1, §2, §3, §7): no door notes, sellers or links in price or summary; the same event once on a listing; the host's own post
// wins; a held row keeps its reason; the prompt never asks for a nation's ceremonies. Plain PHP, PASS/FAIL, exit 1 on any
// FAIL. No database, no AI call, no money.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/LiveEvents.php';

$fails = 0;
$t = function (string $name, bool $ok) use (&$fails): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; };

// ---- 0134 §3 on what the crawler copied
$r = LiveEvents::scrub(['price_text' => '$8, tickets at the box office', 'summary' => 'The new Avengers film on the big screen. Buy on Fandango. Family friendly.']);
$t('a door price is cleared', $r['price_text'] === '');
$t('the seller sentence is left out, the rest kept', $r['summary'] === 'The new Avengers film on the big screen. Family friendly.');
$r = LiveEvents::scrub(['price_text' => '$8', 'summary' => 'See hometowntheater.com for times.']);
$t('a plain price stays', $r['price_text'] === '$8');
$t('a summary that is only a link is emptied', $r['summary'] === '');

// ---- the same event once, and the host's words win (0134 §1; touch point D2, #8807)
$rows = [
    ['title' => 'Avengers: Doomsday', 'starts_on' => '2026-12-18', 'venue_entity_id' => 8807],
    ['title' => 'Avengers Doomsday', 'starts_on' => '2026-12-18', 'venue_entity_id' => 8807],
    ['title' => 'Avengers: Doomsday', 'starts_on' => '2026-12-19', 'venue_entity_id' => 8807],
    ['title' => 'Holiday market', 'starts_on' => '2026-12-18', 'venue_entity_id' => 9000],
];
$out = LiveEvents::dedupe($rows, [], 10);
$t('the same title and day shows once', count($out) === 3);
$host = [8807 => [['2026-12-18', 'communitynightavengersdoomsday']]];
$out = LiveEvents::dedupe($rows, $host, 10);
$t('the host\'s own post that day hides the crawler\'s', count($out) === 2 && $out[0]['starts_on'] === '2026-12-19');
$t('another venue is untouched', in_array('Holiday market', array_column($out, 'title'), true));
$t('a short title doesn\'t match by containment', count(LiveEvents::dedupe([['title' => 'Film', 'starts_on' => '2026-12-18', 'venue_entity_id' => 8807]], [8807 => [['2026-12-18', 'filmfestivalnight']]], 5)) === 1);
$t('at most n', count(LiveEvents::dedupe($rows, [], 1)) === 1);

// ---- the source itself: why before status in keep(), and the prompt's nation rule
$src = (string) file_get_contents($root . '/app/lib/LiveEvents.php');
$w = strpos($src, 'why = IF(status'); $s = strpos($src, ', status = IF(status');
$t('why is assigned before status (a newly held row keeps its reason)', $w !== false && $s !== false && $w < $s);
$t('the prompt no longer lists a ceremony a parish announces', !str_contains($src, 'parish announces'));
$t('the prompt says never to list a nation\'s ceremonies', str_contains($src, 'Never list a tribal nation\'s ceremonies'));
$t('ceremony words are held for a person', (bool) preg_match(LiveEvents::SACRED, 'Feast Day at the Pueblo') && !preg_match(LiveEvents::SACRED, 'Holiday market'));

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===
=== FILE: tests/engage-event-url.php ===
<?php
// tools/events-tests/engage-event-url.php — a comment's page on an event is the event's own page (Engage::pageUrl), so a
// reply's notice and the Reports page land on it (the promise check, decisions/0181's gate 1 note). Plain PHP, PASS/FAIL.
// No money, no notices sent. Needs pdo_sqlite for the PDO the method takes (it reads nothing for an event); SKIP without.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/Engage.php';

$fails = 0;
$t = function (string $name, bool $ok) use (&$fails): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; };

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { echo "SKIP: no pdo_sqlite\n"; exit(0); }
$pdo = new PDO('sqlite::memory:');
$t('an event comment lands on /event/?id=', Engage::pageUrl($pdo, 'event', '5') === '/event/?id=5');
$t('a listing is unchanged', Engage::pageUrl($pdo, 'listing', '8807') === '/listing/view.php?id=8807');
$t('an unknown kind still falls back', Engage::pageUrl($pdo, 'zzz', '1') === '/?1');

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
# Events: FAQ entries (new or changed)

### Who can post an event?
A business that has claimed its listing on Traversence. The person posting needs a role on the business's team that can edit its Catalog & content (an Owner or Manager can change roles), and Verification turned on for their own account. Posting events is free on every plan.

### Can my event say where else to buy tickets, or "pay at the door"?
No. An event's page shows only what's on Traversence: no links, no outside ticket sellers, no "pay at the door" or box office notes, and no payment apps. If you sell tickets for the event, publish an Event tickets item on the Marketplace and link it to the event; people buy there and you're paid your usual way. A theater's regular showtimes can be listed as information only, with no ticket link.

### Can I change an event after it's published?
Yes. A published event is checked again when you change it, as it was when you published it: its date must be ahead, and its words pass the same checks. If people already hold tickets, its date and time can't be changed from the form yet, because we can't tell them for you; tell each buyer from your Marketplace orders, then ask us to change it with you at support@traversence.com.

### How do I cancel an event that sells tickets?
First unpublish its Event tickets item (Market 360° → Marketplace) so no more are sold, refund or credit each buyer from your Marketplace orders, then cancel the event. It stays listed as cancelled until its date, and Put back on undoes it. **Coming:** buyers told automatically when an event is cancelled or changed.

### Why does a listing show an event only once?
When we find an event on a place's own