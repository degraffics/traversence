=== FILE: files/app/lib/Situations.php.region-1.php ===
<?php
// ANCHOR: replaces         ['feel-sick', 'Feeling sick', 'normal', 0, 'resident', through              ['Pharmacies', 'resident', 'Pharmacies|Pharmacists', '', 'listing', 'phone']]],
// (search release 1, (a) and C7) "urgent care" is a situation of its own and is read as urgent: open now first, and known-closed
// left out. Its first need is urgent care by name, then walk-in clinics, then the ER. "urgent care" and "walk in clinic" leave
// Feeling sick. Feeling sick's clinics need names "urgent", so urgent care leads it (the need's own order, solutions()).
// Pharmacies are left out of the urgent-care answer while people are still filed under Pharmacies (the audit's C5).
        ['urgent-care', 'Urgent care', 'urgent', 0, 'resident',
            ['urgent care', 'urgentcare', 'urgent care clinic', 'walk in clinic', 'walkin clinic', 'walk in care', 'immediate care'],
            [['Urgent care', 'resident', '', 'urgent', 'listing', 'phone'],
             ['Walk-in clinics', 'resident', 'Clinics|Ambulatory Health Care Services|Medical Centers', '', 'listing', 'phone'],
             ['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%', '', 'listing', 'phone']]],
        ['feel-sick', 'Feeling sick', 'normal', 0, 'resident',
            ['feel sick', 'im sick', 'fever', 'flu', 'sore throat', 'need a doctor', 'see a doctor'],
            [['Clinics and urgent care', 'resident', 'Clinics|Ambulatory Health Care Services|Health Services|Medical Centers', 'urgent', 'listing', 'phone'],
             ['Doctors', 'resident', 'Physicians & Surgeons|Nurses-Practitioners|Physicians Assistants', '', 'listing', 'phone'],
             ['Pharmacies', 'resident', 'Pharmacies|Pharmacists', '', 'listing', 'phone']]],
=== END FILE ===

=== FILE: files/app/lib/Situations.php.region-2.php ===
<?php
// ANCHOR: replaces     private const SEED_VERSION = '13'; through     private const SEED_VERSION = '13';
// 14: the Urgent care situation; "urgent care" and "walk in clinic" moved off Feeling sick; Feeling sick's clinics need names "urgent".
    private const SEED_VERSION = '14';
=== END FILE ===

=== FILE: files/app/lib/Situations.php.region-3.php ===
<?php
// ANCHOR: replaces     /** [items, how many matched but couldn't complete the need] for one need. */ through     }
// (the region ends at the first line after the anchor's first line that is exactly four spaces and "}": solutions()' own end.
//  It adds catRank() after solutions().)
    /** [items, how many matched but couldn't complete the need] for one need. */
    private static function solutions(PDO $pdo, array $n, ?array $place, array $o, bool $urgent): array
    {
        $kinds = array_values(array_intersect(array_map('trim', explode(',', (string) $n['kinds'])), ['listing', 'outdoors', 'story', 'group', 'experience', 'topic']));
        if (!$kinds) $kinds = ['listing'];
        $p = [];
        $or = [];
        // each category with the place of its first pattern in the need (0 first): the need's own order (search release 1)
        $rank = self::catRank($pdo, (string) $n['cats']);
        $cats = array_keys($rank);
        // a need that names categories takes businesses from them only ("key" isn't Key Cannabis Dispensary); its words
        // find outdoor places, stories, groups and experiences, and businesses only when it names no category
        if ($cats) $or[] = "(si.kind = 'listing' AND si.category_id IN (" . implode(',', array_map('intval', $cats)) . '))';
        $wor = []; $wl = [];
        foreach (array_slice(array_values(array_filter(explode(' ', (string) $n['words']))), 0, 8) as $i => $w) {
            $wor[] = "si.terms LIKE :nw$i";
            $p[":nw$i"] = '% ' . trim(SearchIndex::norm($w)) . ' %';
            if (trim(SearchIndex::norm($w)) !== '') $wl[] = trim(SearchIndex::norm($w));
        }
        if ($wor) $or[] = ($cats ? "si.kind <> 'listing' AND " : '') . '(' . implode(' OR ', $wor) . ')';
        if (!$or) return [[], 0];
        // the need's order comes before weight (decisions/0061; the listing audit's C7): within its categories, a business
        // the need's words name first ("urgent" in Clinics and urgent care), then each category in the order the need
        // lists them, then the rest. Weight only orders within one step.
        $case = [];
        if ($cats && $wl) {
            $wr = [];
            foreach ($wl as $i => $w) { $wr[] = "si.terms LIKE :nr$i"; $p[":nr$i"] = '% ' . $w . ' %'; }   // own names: a server-side prepare can't use one name twice
            $case[] = "WHEN si.kind = 'listing' AND (" . implode(' OR ', $wr) . ') THEN 0';
        }
        $byRank = [];
        foreach ($rank as $id => $r) $byRank[$r][] = (int) $id;
        ksort($byRank);
        foreach ($byRank as $r => $ids) $case[] = 'WHEN si.category_id IN (' . implode(',', $ids) . ') THEN ' . ((int) $r + 1);
        $rankSql = $case ? 'CASE ' . implode(' ', $case) . ' ELSE 99 END' : '99';
        $kin = implode(',', array_map(fn ($k) => "'" . $k . "'", $kinds));
        $here = $place ? SearchIndex::whereSql($pdo, $place, null, $p) : '';
        $center = $o['center'] ?? null; $reach = (string) ($o['reach'] ?? 'all');
        $box = '';
        if ($center && $reach !== 'all' && $reach !== 'place') {
            $d = max(1, (int) $reach) / 69.0;
            $box = ' AND si.lat BETWEEN :b1 AND :b2 AND si.lon BETWEEN :b3 AND :b4';
            $p += [':b1' => $center[0] - $d, ':b2' => $center[0] + $d, ':b3' => $center[1] - $d * 1.3, ':b4' => $center[1] + $d * 1.3];
        }
        $sql = "SELECT si.kind, si.item_id, si.label, si.sub, si.url, si.lat, si.lon, si.hours, si.private_location, si.city, si.state"
             . ($here !== '' ? ", COALESCE($here, 0) AS here" : ', 0 AS here')
             . ", $rankSql AS need_rank"
             . " FROM search_index si WHERE si.kind IN ($kin) AND (" . implode(' OR ', $or) . ")$box"
             . ' ORDER BY here DESC, need_rank, si.weight DESC LIMIT 400';
        try { $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { error_log('situations need: ' . $e->getMessage()); return [[], 0]; }
        // phones for the listings (from their details)
        $phones = [];
        $lids = array_map(fn ($r) => (int) $r['item_id'], array_filter($rows, fn ($r) => $r['kind'] === 'listing'));
        if ($lids) {
            foreach (array_chunk($lids, 500) as $chunk) {
                foreach ($pdo->query("SELECT entity_id, meta_value FROM entity_metadata WHERE meta_key = 'phone' AND entity_id IN (" . implode(',', $chunk) . ')')->fetchAll(PDO::FETCH_ASSOC) as $m) {
                    $v = json_decode((string) $m['meta_value'], true); $v = is_string($v) ? $v : (string) $m['meta_value'];
                    if (trim($v) !== '') $phones[(int) $m['entity_id']] = trim($v);
                }
            }
        }
        [$dow, $min] = $o['at'] ?? [(int) date('w'), (int) date('G') * 60 + (int) date('i')];
        $req = array_filter(explode(',', (string) $n['requires']));
        $items = []; $incomplete = 0; $seenL = []; $missed = [];
        foreach ($rows as $r) {
            if ($reach === 'place' && $place && (int) $r['here'] !== 1) continue;   // the Distance slider at "My place only"
            // a place outside our areas ("Seattle, WA"): only what's in it or within about 150 miles, never Arizona's movers
            if (!empty($place['outside']) && (int) $r['here'] !== 1 && (!$center || $r['lat'] === null || (int) $r['private_location']
                || SearchIndex::miles($center[0], $center[1], (float) $r['lat'], (float) $r['lon']) > 150)) continue;
            $phone = $r['kind'] === 'listing' ? ($phones[(int) $r['item_id']] ?? '') : '';
            $hoursTxt = (string) ($r['hours'] ?? '');
            $always = (bool) preg_match('/24\s*(h|hours|\/7)|open 24/i', $hoursTxt);
            $open = null;
            if ($always) $open = true;
            elseif ($hoursTxt !== '' && class_exists('Hours')) { try { $open = Hours::openAt(Hours::parse($hoursTxt), (int) $dow, (int) $min); } catch (Throwable) {} }
            $hasWhere = !(int) $r['private_location'] && ($r['lat'] !== null || trim((string) $r['city']) !== '');
            // what can't complete the task isn't a solution (decisions/0061, principle 5)
            $missing = (in_array('phone', $req, true) && $phone === '') || (in_array('hours', $req, true) && $hoursTxt === '') || (in_array('where', $req, true) && !$hasWhere);
            if ($missing) {
                $incomplete++;
                if (!empty($o['collect']) && $r['kind'] === 'listing') $missed[] = ['id' => (int) $r['item_id'], 'label' => (string) $r['label'], 'sub' => (string) $r['sub'],
                    'missing' => array_values(array_filter([in_array('phone', $req, true) && $phone === '' ? 'phone' : '', in_array('hours', $req, true) && $hoursTxt === '' ? 'hours' : '', in_array('where', $req, true) && !$hasWhere ? 'place' : '']))];
                continue;
            }
            if ($urgent && $open === false) continue;   // known to be closed now: not a solution to an urgent need
            $miles = ($center && $r['lat'] !== null && !(int) $r['private_location']) ? SearchIndex::miles($center[0], $center[1], (float) $r['lat'], (float) $r['lon']) : null;
            $dk = mb_strtolower((string) $r['label']) . '|' . mb_strtolower(trim(explode('·', (string) $r['sub'])[0])) . '|' . mb_strtolower(trim((string) $r['city']));
            if (isset($seenL[$dk])) continue;   // the same business listed twice: once
            $seenL[$dk] = 1;
            $items[] = ['kind' => (string) $r['kind'], 'id' => (string) $r['item_id'], 'label' => (string) $r['label'], 'sub' => (string) $r['sub'], 'url' => (string) $r['url'],
                        'phone' => $phone, 'open' => $open, 'always' => $always, 'here' => (int) $r['here'] === 1, 'miles' => $miles !== null ? round($miles) : null,
                        'peek' => $r['kind'] === 'listing', '_rank' => (int) $r['need_rank']];
        }
        // in the place first; then the need's own order; urgent: open now first; then nearest
        usort($items, fn ($a, $b) => [!$a['here'], $a['_rank'], $urgent ? $a['open'] !== true : 0, $a['miles'] ?? 99999] <=> [!$b['here'], $b['_rank'], $urgent ? $b['open'] !== true : 0, $b['miles'] ?? 99999]);
        $items = array_map(function ($x) { unset($x['_rank']); return $x; }, $items);
        $per = (int) ($o['per'] ?? 5);
        return [array_slice($items, 0, $per), $incomplete, $missed];
    }

    /** A need's categories, each with the place of its first pattern ("Clinics|Medical Centers": Clinics 0), cached per request. */
    private static function catRank(PDO $pdo, string $patterns): array
    {
        static $cache = [];
        if ($patterns === '') return [];
        if (isset($cache[$patterns])) return $cache[$patterns];
        $rank = []; $i = 0;
        $st = $pdo->prepare('SELECT id FROM categories WHERE name LIKE ?');
        foreach (explode('|', $patterns) as $pat) {
            $pat = trim($pat); if ($pat === '') continue;
            $st->execute([$pat]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) if (!isset($rank[(int) $id])) $rank[(int) $id] = $i;
            $i++;
        }
        return $cache[$patterns] = $rank;
    }
=== END FILE ===

=== FILE: files/app/lib/SearchIndex.php.region-1.php ===
<?php
// ANCHOR: replaces     public static function refresh(PDO $pdo, bool $force = false): void through     }
// (the region ends at the first line after the anchor's first line that is exactly four spaces and "}": refresh()'s own end.
//  The doc comment above refresh() stays as it is.)
    public static function refresh(PDO $pdo, bool $force = false, bool $byJob = false): bool
    {
        try {
            if (!self::ready($pdo)) return false;
            // the catch-up is a scheduled job now (Scheduler::JOBS 'search_index_tick', search release 1). While the job has
            // run in the last 30 minutes, a visitor's search never waits on it. If the job hasn't run that recently (cron
            // not set up yet, or stopped), a search still does it, as before.
            if ($byJob) self::setState($pdo, 'refresh_job_at', date('Y-m-d H:i:s'));
            elseif (!$force) { $job = self::state($pdo, 'refresh_job_at'); if ($job !== '' && strtotime($job) > time() - 1800) return true; }
            $last = self::state($pdo, 'refreshed_at');
            if (!$force && $last !== '' && strtotime($last) > time() - 900) return true;
            self::setState($pdo, 'refreshed_at', date('Y-m-d H:i:s'));
            $mark = self::state($pdo, 'listing_mark');
            if ($mark !== '') {
                // listings changed since the mark, oldest first, 600 at a time. The mark moves to the last one taken, so each
                // run carries on where the last stopped (2026-11-10: it moved only when fewer than 300 had changed, so with
                // the crawler busy it re-read the same 300 every time and new listings, Dana Point's, never reached search)
                $now = date('Y-m-d H:i:s'); $n = 600;
                $st = $pdo->prepare('SELECT id, updated_at FROM entities WHERE updated_at > ? ORDER BY updated_at, id LIMIT ' . $n);
                $st->execute([$mark]); $er = $st->fetchAll(PDO::FETCH_ASSOC);
                $st = $pdo->prepare('SELECT entity_id, MAX(updated_at) u FROM entity_metadata WHERE updated_at > ? GROUP BY entity_id ORDER BY u LIMIT ' . $n);
                $st->execute([$mark]); $mr = $st->fetchAll(PDO::FETCH_ASSOC);
                $ids = array_values(array_unique(array_merge(array_map(fn ($r) => (int) $r['id'], $er), array_map(fn ($r) => (int) $r['entity_id'], $mr))));
                if ($ids) foreach (array_chunk($ids, 300) as $chunk) self::indexListings($pdo, $chunk);
                // as far as both lists reached: a full list stops at its last row; one with room left reached now
                $back = fn (string $t) => date('Y-m-d H:i:s', strtotime($t) - 1);   // a second back: others changed that same second are read again
                $reach = [count($er) >= $n ? $back((string) end($er)['updated_at']) : $now, count($mr) >= $n ? $back((string) end($mr)['u']) : $now];
                $next = min($reach);
                if ($next <= $mark) $next = date('Y-m-d H:i:s', strtotime($mark) + 1);   // a second full of changes: step past it
                self::setState($pdo, 'listing_mark', $next);
            }
            foreach (['story', 'group', 'topic'] as $k) self::rebuildSmall($pdo, $k);
            // landmarks added after the federal data (by hand, or found for a guide on demand: ids from 900000000), new since
            // last time; they were only indexed on a full rebuild (2026-11-10: Four Corners Monument, Dana Point's places)
            if (class_exists('Landmarks') || is_file(__DIR__ . '/Landmarks.php')) {
                require_once __DIR__ . '/Landmarks.php';
                if (Landmarks::ready($pdo)) {
                    $lm = self::state($pdo, 'landmark_added_mark');
                    if ($lm === '') $lm = 'g' . str_pad((string) (Landmarks::MANUAL_FROM - 1), 10, '0', STR_PAD_LEFT);
                    [$n, $next] = self::landmarksAfter($pdo, $lm, 300);
                    if ($n > 0) self::setState($pdo, 'landmark_added_mark', $next);
                    // each landmark filed under one of our categories by its source's class (2026-11-10: "from categories not
                    // guesses"); a few filed now are re-indexed at once, many go through the walk below
                    $filed = Landmarks::categorize($pdo);
                    if (count($filed) > 300) { self::setState($pdo, 'landmark_kind_walk3', 'g'); }
                    else foreach ($filed as $fid) self::refreshLandmark($pdo, (int) $fid);
                    // every landmark again under its kind, 1000 a run until done (once, and after a big filing)
                    $wk = self::state($pdo, 'landmark_kind_walk3');   // 3: with its activities (Landmarks::activities, 2026-11-10)
                    if ($wk === '') $wk = 'g';
                    if ($wk !== 'done') { [$wn, $wnext] = self::landmarksAfter($pdo, $wk, 1000); self::setState($pdo, 'landmark_kind_walk3', $wn < 1000 ? 'done' : $wnext); }
                }
            }
        } catch (Throwable $e) {
            error_log('SearchIndex::refresh: ' . $e->getMessage());
            return false;
        }
        return true;
    }
=== END FILE ===

=== FILE: files/app/scripts/search_index_tick.php ===
<?php
// app/scripts/search_index_tick.php — the search index's catch-up, as a scheduled job (Scheduler::JOBS 'search_index_tick',
// search release 1). It does what a visitor's search used to do at most every 15 minutes (SearchIndex::refresh): listings
// changed since the last look re-read, stories, groups and topics rebuilt, new landmarks added. While this has run in the
// last 30 minutes, searches skip it. Database only: no outside call.
// DRAFT: the requires below follow app/lib's own (ListingIdentity.php requires Database.php beside it). Match them to an
// existing app/scripts/*_tick.php header before shipping (file not shown).
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
require_once __DIR__ . '/../lib/SearchIndex.php';

Scheduler::run('search_index_tick');

$pdo = Database::connection();
if (!SearchIndex::ready($pdo)) { echo "The search index's tables aren't there yet: nothing to do.\n"; exit(0); }
$t = microtime(true);
$ok = SearchIndex::refresh($pdo, true, true);
echo ($ok ? 'Caught up' : 'Stopped with an error (see the error log)') . ' in ' . round(microtime(true) - $t, 1) . " s; listing mark " . SearchIndex::state($pdo, 'listing_mark') . "\n";
=== END FILE ===

=== FILE: files/app/lib/Scheduler.php ===
<?php
// app/lib/Scheduler.php — our scheduled scripts (app/scripts/*_tick.php, ai_worker.php) and their cron jobs in cPanel,
// set from System Configuration → Scheduled tasks through the cPanel API (CPanel::api2, module Cron). Each script
// records its own runs (run() at its top), so the page shows when each last ran and whether it worked, whether or not
// cPanel can be reached from here. Cron runs on the server's clock.
declare(strict_types=1);

require_once __DIR__ . '/AdminTables.php';
require_once __DIR__ . '/CPanel.php';

final class Scheduler
{
    /** Our jobs: key (its script, app/scripts/<key>.php) => [name, what it does, the preset it starts on, how late is "late" in minutes]. A daily
     * job's "late" is a day plus two hours (1560), never less than its own schedule: seven daily jobs said Late hours before they were
     * due (2026-11-24, Scheduled tasks on live). */
    public const JOBS = [
        'control_tick' => ['The System Control Board\'s watch', 'Every 5 minutes: takes out and puts back the maintenance windows set ahead, and catches what goes quiet (W0 Indexer, a crawler run that never reported, a site job that stopped or never finished) as alarms on the board, and emails a warning left unacknowledged a day (decisions/0171 §5).', '5min', 30],
        'team_audit_tick' => ['The Team\'s daily audit', 'On the staging copy only, while the Team\'s runner is switched on: the Operations & Reconciliation Manager reads Site health\'s findings, late or failed jobs and open alarms, opens one Audit ticket per new finding, and a day later confirms it (opening the Work or Resolution ticket it calls for) or clears it as noise (decisions/0179\'s Piece 2). On the live site it does nothing and logs nothing.', 'daily', 1560],
        'founder_mail_tick' => ['Email to the Founder', 'Every 10 minutes on the live site: today\'s daily batch and the session manager\'s messages, each emailed once to the Founder through the site\'s own email (FounderMail, Notify). Logs only when it sends.', '10min', 60],
        'team_tick' => ['The Team\'s runner', 'On the staging copy only, while its switch is on: takes one ticket from the Work queue, does one step for one working role, logs it with Undo, and stops (decisions/0179\'s Piece 2). On the live site it does nothing and logs nothing.', '10min', 60],
        'ai_worker' => ['AI worker', 'Reads queued AI jobs: scanned business cards now, menus and brochures with the Studio.', 'minute', 15],
        'standing_tick' => ['Notices of action', 'Moves notices whose 24 hours ran out (page loads do it too, every few minutes).', '10min', 60],
        'sales_tick' => ['Lead scores', 'Scores the listings worth a call and gives new listings in a territory to its rep.', 'hourly', 180],
        'files_tick' => ['Files to the file store', 'Moves files still on the server into their owners\' folders in the file store (Cloudflare R2), a batch at a time: files from before (decisions/0124), and any upload the store didn\'t take at the time, which waits on the server and is tried again here (decisions/0163\'s source map: file storage, else uploads wait).', '10min', 60],
        'work_orders_tick' => ['Work order reminders', 'Asks vendors for an update: a work order not accepted by 9:00 am the next business day, 24 hours before its must-have time, and at it.', 'hourly', 180],
        'guide_finds_tick' => ['Find it and guide drafts', 'Looks up the names a guide mentions that we don\'t have yet (our crawler first, then web search with Claude) and sends what it confirms through the listings pipeline, which publishes the ready ones; links names once they\'re listed. And drafts one guide a run with Claude (asked-for ones first, then unwritten city and community guides), published by its score. And, while the Live events template is set running, looks for one place\'s upcoming events.', '15min', 60],
        'listings_tick' => ['Listings pipeline', 'Matches the crawler\'s waiting listings again, merges the ones we already have and publishes the ready ones by rule, without anyone opening Review.', '15min', 60],
        'guide_live_tick' => ['Guide weather and roads', 'Keeps each guide\'s weather (National Weather Service) and Arizona\'s road work (AZ 511) fresh ahead of visitors, so a guide never waits on them.', '15min', 60],
        'got_it_tick' => ['Did it arrive?', 'Reminds a client to tell us they have their order (that completes it), 72 hours after the last of it shipped or was ready for pickup, once.', 'hourly', 180],
        'qb_orders_tick' => ['QuickBooks invoices on orders', 'Reads the QuickBooks invoice each open order names (one made here, or one made by hand and linked by its number) and records any payment QuickBooks shows that isn\'t on the order yet, so the order moves on by itself (M1b). Writes nothing to QuickBooks.', 'hourly', 180],
        'pay_wait_tick' => ['Payment reminders', 'Reminds a client who chose wire or check and hasn\'t given a confirmation code (and tells support): 2 hours after, 9:00 am the next business day, and 4 pm with the other ways to pay.', '15min', 60],
        'services_tick' => ['Services & usage', 'The service register\'s upkeep (today\'s caps recorded once; watching Railway and the sync, when it\'s switched on), and the month\'s report on the 1st by email (decisions/0133, 0163). The 50/75/95% warnings are Pulse cases.', 'hourly', 180],
        'takedowns_tick' => ['Takedowns and the guide audit', 'Hides what someone asked us to take down once 24 hours pass without a person deciding (a protected reason hides it at once), and draws the quarterly sample of published guides to audit (decisions/0133).', '10min', 60],
        'heritage_tick' => ['Heritage catalog', 'Reads each guide\'s place (the Library of Congress\'s records the crawler found, its dossier and research) into vetted facts for the heritage catalog and the guides\' "Walking in the footsteps" chapter, files them in the Topic index, keeps topic summaries current and researches the most asked-for thin topics.', 'hourly', 180],
        'daily_report' => ['Daily report', 'Emails the Founder one report a day at 5 am Arizona time (Stream settings): every open Pulse case with a button to its step, and the system status. It checks each hour and sends once the hour comes.', 'hourly2', 180],
        'market_tick' => ['Marketplace check', 'Asks sellers "Still available?" of every post that\'s due, and takes off the ones asked at the last check that nobody confirmed (decisions/0135 §8). Closes offers nobody answered in 7 days and reminds a trade\'s other side to confirm (§4).', 'daily', 1560],
        'mail_sync' => ['Mailboxes in Pulse', 'Reads every connected mailbox (ours by IMAP, outside ones by IMAP, POP or Microsoft\'s Graph) into its readers\' Pulse inbox, and keeps read state in step (decisions/0172 §4.7). A refused password becomes a Reconnect message in Pulse.', '5min', 60],
        'mail_keep' => ['Mailboxes: keep 90 days', 'Our working copy of connected mailboxes keeps the last 90 days for search and threading; the mailbox itself keeps everything (decisions/0172 §4.7).', 'daily', 1560],
        'pulse_followup' => ['Pulse: follow-ups', 'Brings back, unread and on top, each item someone flagged to follow up once its time comes, and tells them (decisions/0172, Flags and tags in Pulse).', '10min', 60],
        'booking_remind' => ['Booking reminders', 'Reminds customers a day before a stay or rental and 2 hours before check-in, once each (decisions/0172 §2.5).', '15min', 60],
        'listing_messages_tick' => ['Messages left on unclaimed listings', 'Paces visitors\' requests to unclaimed listings\' emails (the 10-day notice, paused ones resumed), deletes unconfirmed guest emails after 48 hours and expires messages never claimed after 90 days, telling their senders (decisions/0172 §3.3).', 'hourly', 120],
        'notify_release' => ['Quiet hours: held email', 'Sends the email that waited for someone\'s quiet hours once they end (decisions/0172, the one dispatcher). Messages already read in their Pulse aren\'t emailed.', '10min', 60],
        'notice_digest' => ['Notices by email: weekly digest', 'Sends each person who chose a weekly email of their notices one email of the week\'s notices, through Amazon SES (decisions/0137 §6, §8). Runs daily; each person gets one every 7 days.', 'daily', 1560],
        'ses_setup_tick' => ['Email sending setup', 'Sets up Amazon SES by itself (decisions/0137 §8): the sending domains, their DNS records through the cPanel API, bounce reports, the check with AWS, asking for production access, and one test once live. Does nothing once the setup is done.', '15min', 60],
        'domains_tick' => ['Businesses\' domains', 'Checks businesses\' own domains on their storefronts (decisions/0139 §3): DNS and the certificate for the ones waiting, live ones once a day, and renews domains bought through us two weeks before they run out.', '15min', 60],
        'billing_tick' => ['Plans and renewals', 'Renews what\'s due on the card on file, retries declined cards, ends trials and sends the reminders.', 'daily', 1560],
        'region_tick' => ['Sign-up region check', 'Keeps the EU/EEA/UK address ranges for the sign-up check fresh (decisions/0027): downloads DB-IP\'s free country file (CC BY 4.0) once a month; daily it only checks.', 'daily', 1560],
        'guide_ondemand_tick' => ['Guides on demand', 'Carries on guides a searcher asked for after they leave, writes the ones waiting for the allowance (most asked first), and writes each first look in full once its deeper research is in (decisions/0147).', '5min', 30],
        'journeys_tick' => ['Suggested journeys', 'Builds each live guide\'s suggested journeys from its own places by rules (no AI), three guides a run, again weekly or when the guide changes; asks openrouteservice once for a town\'s travel times and keeps them (decisions/0146).', 'hourly', 180],
        'supabase_keepalive' => ['Supabase keep-alive', 'One tiny read a day so the free Supabase project doesn\'t pause (decisions/0164 §5.2). Under Supabase\'s own switch, not the master: it gathers nothing and costs nothing.', 'daily', 1560],
        'page_read_tick' => ['Pages waiting to be read', 'Pages whose reading had to stop are read into facts by the AI that AI use routes "Read a public page into facts" to (decisions/0164 §2.3; gap review P1-1). Shipped off: it runs only while automation, W3 and that feature are on. Not set up in cPanel by itself.', 'hourly', 300],
        'vectors_tick' => ['Vector index', 'Public listings\' text turned into vectors on Supabase for semantic search (decisions/0164 §5.2). Shipped off: it runs only while automation, W3, Supabase and AI use\'s "vector index" are on. Not set up in cPanel by itself.', 'hourly', 300],
        'identity_recheck_tick' => ['Identity recheck', 'Live listings checked against each other (decisions/0164 §6.1): exact twins merged by themselves with Undo, variations vetted, the rest to "The engine needs a person". Shipped off: it runs only while automation, W2 and its task are on. Not set up in cPanel by itself: add it here, in a quiet hour.', 'daily', 1560],
        'data_cleanup_tick' => ['Data cleanup', 'A pass for every rule of Insights → Data cleanup, once a day, so what the crawler made stays in line with today\'s rules. Shipped off: it does nothing until its daily pass is switched on there, and only while automation is on. Not set up in cPanel by itself: add it here when it\'s wanted.', 'daily', 1560],
        'landmark_photos_tick' => ['Landmark and place photos', 'Looks on Wikimedia Commons for a free photo of each shown landmark that has none (taken nearby and named for it), 20 an hour, then of the parks, monuments, visitor centres and historic sites on our cards (15 an hour, never a place kept private), and keeps the best with its credit and licence. One with none found is looked at again after 30 days.', 'hourly', 180],
        'like_watch_tick' => ['Like checks', 'Looks at the last hour\'s likes: takes machine likes (perfectly timed, or more likes than views) off the count, reports groups of new accounts and guests to Safety in Pulse, and deletes like records older than 30 days.', 'hourly', 180],
        'earned_storage_tick' => ['Earned storage', 'Counts each member\'s qualifying followers and friends who joined, and adds the storage they\'ve earned: 1 GB per 500 followers and per friend, up to 25 GB, 5 GB a month at most (decisions/0154 §7).', 'daily', 1500],
        'moments_tick' => ['Moments', 'Ends posts whose 3 or 7 days are up (deletes them and their photos and videos, unless kept or waiting on a report), tells people a day before, lets go of uploads never posted, and releases reported posts\' held copies after their 90 days (decisions/0153).', 'hourly', 180],
        'guest_tick' => ['Guests\' 30 days', 'Deletes the hashed connections behind guests\' likes after 30 days (decisions/0009); the likes stay in the counts.', 'daily', 1560],
        'vault_digest_tick' => ['Vault email summaries', 'Sends the vault\'s hourly and daily email summaries to whoever chose them (the vault\'s Emails), only when something happened. Alerts about devices, recovery words and locks always go at once.', 'hourly', 180],
        'releases_tick' => ['Releases', 'Publishes a release when its scheduled time comes, and moves live releases on a step (members, then everyone) once their hours have passed clean, inside the release window (System Configuration → Releases). A failed check pauses it and emails whoever approved it.', '10min', 60],
        'staging_carry_tick' => ['Staging refresh, carried on', 'Works through a staging refresh on the server once one is started (System Configuration → Staging, or the weekly Staging refresh), so the page needn\'t stay open. Does nothing when no refresh is running.', 'minute', 15],
        'audit_clock_tick' => ['Site audit clock', 'Asks GitHub to run the site audit\'s checks when their hour comes (the code checks at 7:00 and 19:00 UTC, the browser checks and paths at 9:00, the search probe at 10:00), because GitHub\'s own schedule skips them when it\'s busy (decisions/0145). Site health in Pulse says when one hasn\'t run in 26 hours. Does nothing on staging.', 'hourly', 180],
        'staging_tick' => ['Staging refresh (stopped)', 'The weekly copy of live over staging stopped with decisions/0165: staging catches up from live instead, keeping its own work. Start staging over is a button on System Configuration → Staging. It does nothing now and can be removed.', 'weekly', 10140],
        'staging_push_tick' => ['Staging: live\'s changes pushed', 'Sends each change on the live site to staging within a minute (live\'s outbox), for the rows staging didn\'t touch; a row both changed goes on the conflicts list (decisions/0165 §3). Does nothing until Keep staging caught up is switched on (System Configuration → Staging).', 'minute', 15],
        'staging_night_tick' => ['Staging: the nightly full pass', 'Every night (2:13 am Arizona): every row\'s fingerprint against staging\'s base, which finds a missed push, a delete the database cascaded and a change made outside the code; copies again the reference tables live changed (decisions/0165 §3). Does nothing until Keep staging caught up is on.', 'nightly', 1560],
        'frontier_tick' => ['The index\'s upkeep (W0)', 'Keeps W0 Indexer\'s frontier in order (decisions/0164 §1.4): sites not decided yet classified by the machine (a tribal nation\'s set aside for a person), places\' own websites ranked first, junk seen often made a skip rule, URLs never read in six months kept as their hash, its size recorded for the pre-flight. Database only: no outside call, so it runs whatever the switches say.', 'hourly', 180],
        'search_index_tick' => ['Search index catch-up', 'Every 15 minutes: listings changed since the last look (their row or their details) re-read into search, 600 at a time from where the last run stopped; stories, groups and topics rebuilt; landmarks added since, filed and re-indexed (SearchIndex::refresh). Searches used to do this themselves, so a visitor sometimes waited on it; a search still does it when this job hasn\'t run in 30 minutes (search release 1). Database only: no outside call.', '15min', 60],
        'preflight_tick' => ['Pre-flight', 'Runs every pre-flight test (System Configuration → Pre-flight) each night at 2 am Arizona time, and within 15 minutes of a .env change: real, free, read-only tests of every service, setting, workflow and task, red lines to Pulse ("Pre-flight"). It never starts, pauses or changes anything.', '15min', 60],
        'system_start_tick' => ['Start the system, carried on', 'Works through Start the system (System Configuration → Automation) once it\'s pressed: the keys, watching, the switch-on check, Railway\'s schedule, the master switch, then each workflow and new task one at a time behind its gate (decisions/0163 §10). Does nothing until it\'s pressed, and never on staging.', '10min', 120],
        'staging_publish_tick' => ['Staging: Publish, carried on', 'Works through a Publish of staging on the server once it\'s started (the site manager), a step at a time, so the page can close; starts a scheduled one at its time after a fresh preview (decisions/0165 §4). Does nothing when none is running.', 'minute', 15],
    ];

    /** Jobs that set themselves up in cPanel (sync()), on their usual schedule, with no one opening cPanel (Jason, 2026-10-28):
     *  every new job goes here. One removed on the Scheduled tasks page stays removed. */
    public const AUTO = ['control_tick', 'founder_mail_tick', 'team_tick', 'team_audit_tick', 'moments_tick', 'earned_storage_tick', 'guide_ondemand_tick', 'journeys_tick', 'landmark_photos_tick', 'like_watch_tick', 'guest_tick', 'region_tick', 'work_orders_tick', 'pay_wait_tick', 'got_it_tick', 'qb_orders_tick', 'guide_finds_tick', 'guide_live_tick', 'listings_tick', 'services_tick', 'heritage_tick', 'takedowns_tick', 'daily_report', 'market_tick', 'notice_digest', 'notify_release', 'listing_messages_tick', 'mail_sync', 'mail_keep', 'pulse_followup', 'booking_remind', 'ses_setup_tick', 'domains_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick', 'vault_digest_tick', 'audit_clock_tick', 'system_start_tick', 'frontier_tick', 'preflight_tick', 'files_tick', 'search_index_tick'];

    /** Schedules to choose from: key => [label, minute, hour, day, month, weekday]. */
    public const PRESETS = [
        'minute' => ['Every minute', '*', '*', '*', '*', '*'],
        '5min' => ['Every 5 minutes', '*/5', '*', '*', '*', '*'],
        '10min' => ['Every 10 minutes', '*/10', '*', '*', '*', '*'],
        '15min' => ['Every 15 minutes', '*/15', '*', '*', '*', '*'],
        'hourly' => ['Every hour (at :17)', '17', '*', '*', '*', '*'],
        'hourly2' => ['Every hour (at :02)', '2', '*', '*', '*', '*'],
        '6hours' => ['Every 6 hours', '17', '*/6', '*', '*', '*'],
        'daily' => ['Every day at 6:13 am', '13', '6', '*', '*', '*'],
        'nightly' => ['Every night at 2:13 am', '13', '2', '*', '*', '*'],
        'weekly' => ['Every Sunday at 3:13 am', '13', '3', '*', '*', '0'],
    ];

    public static function ensure(PDO $pdo): void
    {
        AdminTables::make($pdo, ['job_runs' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, job VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL,
            finished_at DATETIME DEFAULT NULL, ok TINYINT(1) DEFAULT NULL, note VARCHAR(255) NOT NULL DEFAULT \'\', KEY idx_job_runs (job, id))',
            'job_setup' => '(job VARCHAR(40) NOT NULL PRIMARY KEY, state VARCHAR(10) NOT NULL, at DATETIME NOT NULL)']);
    }

    // ---- a script's own run --------------------------------------------------------------------------------------------

    /** Called at the top of a scheduled script: records the run, and at the end whether it finished and what it said. */
    public static function run(string $job): void
    {
        try {
            $pdo = Database::connection();
            self::ensure($pdo);
            $pdo->prepare('INSERT INTO ' . Database::t('job_runs') . ' (job, started_at) VALUES (?, NOW())')->execute([$job]);
            $id = (int) $pdo->lastInsertId();
            if (random_int(1, 50) === 1) $pdo->prepare('DELETE FROM ' . Database::t('job_runs') . ' WHERE job = ? AND started_at < NOW() - INTERVAL 14 DAY')->execute([$job]);
        } catch (Throwable $e) { error_log('scheduler: ' . $e->getMessage()); self::missed($job, $e->getMessage()); return; }   // the job still runs, unrecorded: its miss is kept for the health report
        try { self::sync($pdo); } catch (Throwable $e) { error_log('scheduler sync: ' . $e->getMessage()); }   // any running job sets up new ones (hourly at most)
        ob_start();
        register_shutdown_function(function () use ($pdo, $id) {
            $out = (string) ob_get_clean();
            echo $out;
            $e = error_get_last();
            $bad = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
            $note = $bad ? 'Stopped: ' . $e['message'] : (string) end($lines);
            try { $pdo->prepare('UPDATE ' . Database::t('job_runs') . ' SET finished_at = NOW(), ok = ?, note = ? WHERE id = ?')->execute([$bad ? 0 : 1, mb_substr($note, 0, 255), $id]); } catch (Throwable) {}
        });
        // a job that gathers or creates content skips while automation is paused, or all its workflows are (System
        // Configuration → Automation; Jason, 2026-11-14): recorded as a run that worked and said why, never a failure
        try {
            require_once __DIR__ . '/Automation.php';
            if (($w = Automation::job($pdo, $job)) !== null) { echo 'Paused: ' . $w . "\n"; exit(0); }
        } catch (Throwable $e) { error_log('scheduler automation: ' . $e->getMessage()); }
    }

    /** Where a start that couldn't be recorded is kept (the server's own folder, never synced): the database refused it. */
    public static function missFile(): string { return __DIR__ . '/../storage/scheduler-missed.json'; }

    /**
     * A start the database refused (2