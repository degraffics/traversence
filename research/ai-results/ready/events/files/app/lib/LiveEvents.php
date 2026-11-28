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
