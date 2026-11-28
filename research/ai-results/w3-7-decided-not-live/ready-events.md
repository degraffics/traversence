=== FILE: files/app/lib/LiveEvents.php ===
<?php
// app/lib/LiveEvents.php — Live events, the AI Content Crawler's second template (decisions/0133; Jason, 2026-10-30): what's
// coming up in each place with a live guide. Free sources first: the place's own official site, its events and calendar
// pages, read by our crawler. Claude reads those and lists the events with their dates, searching the web only for what
// they don't list, and names only events a page announces (each keeps the page that says so). Each is checked: a date
// ahead and inside the window, a page that's a web address, a venue matched to our listings where we have it; a name that
// may mark a protected place waits for a person. An event shows in the guide's Events section and on its venue's listing
// until the day after it ends. Our team can take one down, and it stays down. Run by the guide job, one place a run,
// each place every week, only while the template is set running.
// The Hometown Theater pilot's fixes (decisions/0134, the note of 2026-11-27; job 14's LiveEvents review):
//   - a tribal nation's ceremonies and sacred observances are never listed by the crawler (0134 §2: only the nation posts them);
//   - no "pay at the door", box office, outside-seller notes or links in a card's price or summary (0134 §3);
//   - a listing's Coming up shows each event once, as the guide does (0134 §1);
//   - a row newly held keeps its reason for the person who looks (0134 §7).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ContentTemplates.php';

final class LiveEvents
{
    public const STATUSES = ['live' => 'Showing', 'held' => 'Waiting for a person', 'expired' => 'Past', 'removed' => 'Taken down'];

    /**
     * Words that sell outside Traversence or at the door (decisions/0134 §3: no links to outside ticket sellers and no
     * "pay at the door" notes; the source page, a citation, is the only link a card carries).
     */
    private const OUTSIDE_SELLING = '~(?:https?://|www\.)\S+|\b[a-z0-9-]+\.(?:com|org|net|events?|tickets?)\b|\bat the door\b|\bbox[ -]?office\b|\bdoor (?:price|sales?|tickets?)\b|\b(?:eventbrite|ticketmaster|ticket ?tailor|ticketleap|ticketweb|brown paper tickets|etix|axs|see ?tickets|stubhub|vivid ?seats|fandango|showclix)\b~i';

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
            // decisions/0134 §2 (the tribal nation row): a nation's ceremonies and sacred observances are listed only when the nation posts them itself, never by us
            . "- Never list a tribal nation's ceremonies or sacred observances, even when a page says they're open to the public: only the nation posts its own. A feast at another sacred place is listed only if its parish announces it as open to the public, and never with directions to the place; leave out anything private.\n"
            . "- In price and summary, never give \"pay at the door\" or box office notes, outside ticket sellers or web addresses.\n"
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
     * a web address, a name; a venue matched to our listings where we have it. A name that may mark a protected place is
     * held for a person (status held). A price that sells at the door or elsewhere is cleared, and such sentences leave the
     * summary (decisions/0134 §3).
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
        $rawSummary = $clip($ev['summary'] ?? '', 600);
        $summary = self::withoutOutsideSelling($rawSummary, false);
        $held = Landmarks::isProtected($name . ' ' . $venue . ' ' . $rawSummary);   // the whole summary, before any sentence leaves it
        return [['title' => $name, 'starts_on' => $start, 'ends_on' => $end, 'time_text' => $clip($ev['time'] ?? '', 60), 'venue_name' => $venue, 'venue_entity_id' => $vid,
                 'price_text' => self::withoutOutsideSelling($clip($ev['price'] ?? '', 80), true), 'summary' => $summary, 'source_url' => mb_substr($src, 0, 500),
                 'status' => $held ? 'held' : 'live', 'why' => $held ? 'Its name or venue may mark a protected place: a person looks first.' : ''], ''];
    }

    /**
     * Words a card may show, without door, box office or outside-seller notes and links (decisions/0134 §3). $whole: a price,
     * cleared whole when it says so; otherwise a summary, losing only the sentences that say so.
     */
    public static function withoutOutsideSelling(string $s, bool $whole): string
    {
        if ($s === '') return '';
        $bad = fn (string $t) => (bool) preg_match(self::OUTSIDE_SELLING, $t);
        if ($whole) return $bad($s) ? '' : $s;
        $keep = array_filter(preg_split('/(?<=[.!?])\s+/u', $s) ?: [], fn ($x) => trim($x) !== '' && !$bad($x));
        return trim(implode(' ', $keep));
    }

    /** Saves an event (one row per place, name and first day). One our team took down stays down. Returns 1 if it's new. */
    private static function keep(PDO $pdo, string $key, array $r): int
    {
        $hash = md5($key . '|' . mb_strtolower($r['title']) . '|' . $r['starts_on']);
        $st = $pdo->prepare('SELECT status FROM live_events WHERE hash = ?'); $st->execute([$hash]); $was = $st->fetchColumn();
        if ($was === 'removed') return 0;
        // why is set before status: MySQL reads these assignments left to right, so why must see the old status (a row
        // newly held gets its reason; one already waiting for a person keeps its own)
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

    /** One card per event, up to $n: a community and its town can each find the same one (same name, letters only, and first day). */
    public static function dedupe(array $rows, int $n): array
    {
        $out = []; $seen = [];
        foreach ($rows as $r) {
            $k = mb_strtolower(preg_replace('/\W+/u', '', (string) $r['title'])) . '|' . $r['starts_on'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true; $out[] = $r;
            if (count($out) >= $n) break;
        }
        return $out;
    }

    /** What's coming up for a place's guide, soonest first. */
    public static function forPlace(PDO $pdo, array $p, int $n = 8): array
    {
        if (!self::ready($pdo)) return [];
        require_once __DIR__ . '/Guides.php';
        $keys = self::keysFor($pdo, $p);
        $st = $pdo->prepare("SELECT * FROM live_events WHERE status = 'live' AND COALESCE(ends_on, starts_on) >= CURDATE() AND place_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ') ORDER BY starts_on, title, place_key = ? DESC LIMIT ' . max(1, min(90, $n * 3)));
        $st->execute(array_merge($keys, [(string) $p['key']]));
        return self::dedupe($st->fetchAll(PDO::FETCH_ASSOC), $n);   // a community and its town can both find the same event: shown once
    }

    /** What's coming up at a listing (its venue), each event once (decisions/0134 §1: same venue, same day, same name is one event). */
    public static function forEntity(PDO $pdo, int $id, int $n = 5): array
    {
        if (!self::ready($pdo)) return [];
        $n = max(1, min(20, $n));
        $st = $pdo->prepare("SELECT * FROM live_events WHERE status = 'live' AND venue_entity_id = ? AND COALESCE(ends_on, starts_on) >= CURDATE() ORDER BY starts_on, title, id LIMIT " . min(60, $n * 3));
        $st->execute([$id]);
        return self::dedupe($st->fetchAll(PDO::FETCH_ASSOC), $n);
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
=== FILE: files/app/lib/Events.php ===
<?php
// app/lib/Events.php — events a business posts (decisions/0134, its minimum before Jan 1: the note of 2026-11-27, option
// (a), piloted by the Hometown theater). One store, `events`, for host-posted events; the crawler's Live events stay in
// their own store for Jan 1 (that note: "Live events stay separate from host events", the merge moves to Q1).
//   Who posts:  a business's team on a claimed listing whose claim is confirmed, with Catalog & content to edit (Team::can,
//               which holds an unclaimed public listing at view; 0134 §2's Owner, Manager, Creator through their roles'
//               permissions). The poster's own Verification (0062; 0134 §2, Jason's answer 1) isn't checked here yet: its
//               lookup isn't wired in this release.
//   What:       title, a short description, a kind (0134 §3), when (start, end or all day, in the venue's own time zone:
//               CLAUDE.md "Times where they happened"), who for (all ages, 18+, 21+), a cover photo (the one uploader,
//               target event_photo), showtimes as information only, and a link to one of the host's own published
//               Event tickets items (ItemKinds 'ticket', bought on the Marketplace path). Free events otherwise: no price.
//   Never:      links to outside ticket sellers or "pay at the door" notes (0134 §3; the note: showtimes are information
//               only, with no ticket link and no door note). Words the Marketplace holds or blocks are held the same way.
//   Checks:     Publish, Put back on, and every edit of a published event pass the same checks (0134 §2's automated checks,
//               §7's date ahead): an edit that fails them isn't saved.
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
    /** A listing claimed but not yet confirmed (the listing's own states, as its Overview names them): no events until it is. */
    public const CLAIM_UNCONFIRMED = ['pending-verification', 'claimed-unverified'];

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM events LIMIT 0'); $pdo->query('SELECT 1 FROM event_history LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /**
     * May this person post for this listing? [yes, why not]. The listing must be claimed, its claim confirmed (0134 §2: "a
     * business also needs its claim"); the role needs Catalog & content to edit.
     */
    public static function canPost(PDO $pdo, int $uid, int $entity): array
    {
        require_once __DIR__ . '/Team.php';
        $st = $pdo->prepare('SELECT status FROM entities WHERE id = ?'); $st->execute([$entity]); $status = (string) $st->fetchColumn();
        if ($status === 'unclaimed') return [false, 'Events are posted by a business that has claimed its listing. Claim it first.'];
        if (in_array($status, self::CLAIM_UNCONFIRMED, true)) return [false, 'Your claim on this listing isn\'t confirmed yet. Finish confirming it in Edit listing, then post events.'];
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
     * day) and are stored as written, with the venue's zone beside them. An edit of a published event passes Publish's
     * checks first (its date, when it moved; its words, when they changed). Returns the event's id; throws with the reason.
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
            if ($old['status'] === 'published') {   // a live event's change shows at once, so it passes Publish's checks first (0134 §2, §7)
                $dated = array_key_exists('starts_at', $changed) || array_key_exists('ends_at', $changed) || array_key_exists('all_day', $changed);
                $worded = array_key_exists('title', $changed) || array_key_exists('about', $changed) || array_key_exists('showtimes', $changed);
                $hold = self::publishHold($pdo, array_merge($old, $v), 'edit', $dated, $worded);
                if ($hold !== '') throw new InvalidArgumentException($hold);
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
     * Why an event can't show yet ('' when it can): its date is ahead, and its words pass the Marketplace's rules (0134 §2's
     * automated checks, §7's date ahead). $what words the answer: 'publish', 'restore' (Put back on) or 'edit' (a change to a
     * published event). $date and $words choose which checks run (an edit runs only those its change touches).
     */
    public static function publishHold(PDO $pdo, array $e, string $what = 'publish', bool $date = true, bool $words = true): string
    {
        if ($date && strtotime((string) (($e['ends_at'] ?? null) ?: ($e['starts_at'] ?? ''))) < strtotime('-1 day'))
            return ['restore' => 'This event is in the past, so it can\'t be put back on. Change its date first.',
                    'edit' => 'Not saved: it\'s published, so its date must be ahead. Choose a date ahead.'][$what] ?? 'This event is in the past. Change its date to publish it.';
        if ($words) {
            $hold = self::checkWords($pdo, (string) ($e['title'] ?? ''), (string) ($e['about'] ?? '') . "\n" . (string) ($e['showtimes'] ?? ''));
            if ($hold !== '') return ($what === 'edit' ? 'Not saved, because it\'s published. ' : '') . $hold;
        }
        return '';
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
        if ($what === 'publish' || $what === 'restore') {   // Put back on publishes again, so it passes the same checks (0134 §2, §7)
            $hold = self::publishHold($pdo, $e, $what);
            if ($hold !== '') throw new InvalidArgumentException($hold);
        }
        $pdo->prepare('UPDATE events SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP()' . ($to === 'deleted' ? ', deleted_at = UTC_TIMESTAMP()' : ($what === 'undelete' ? ', deleted_at = NULL' : '')) . ($to === 'published' && !$e['published_at'] ? ', published_at = UTC_TIMESTAMP()' : '') . ' WHERE id = ? AND status = ?')
            ->execute([$to, $uid, $id, $s]);
        $pdo->prepare('INSERT INTO event_history (event_id, at, tz, user_id, field, old_value) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?)')->execute([$id, self::tzOk($tz), $uid, $what, $s]);   // the move by name, with the state it left (the Design reviewer, 2026-11-27)
        if ($what === 'cancel' && !empty($e['ticket_item_id']))   // 0134 §6a: a cancelled event refunds or credits everyone; its page stops offering tickets (ticketOf)
            $words .= ' Its Event tickets item is still on sale on the Marketplace: unpublish it in Market 360° → Marketplace, and refund or credit everyone who bought. We don\'t tell buyers for you yet, so tell them yourself.';
        return $words;
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
        $st = $pdo->prepare("SELECT ev.*, e.name AS host_name, e.micro_cluster_id FROM