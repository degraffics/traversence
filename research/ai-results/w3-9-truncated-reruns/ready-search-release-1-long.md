=== FILE: files/app/lib/Situations.php.region-1.php ===
<?php
// ANCHOR: replaces         ['someone-hurt', 'Someone is hurt or very sick', 'urgent', 1, 'resident', through              ['Pharmacies', 'resident', 'Pharmacies|Pharmacists', '', 'listing', 'phone']]],
// (inside const SEED: the health block from Someone is hurt through Feeling sick; the first matching last line after the first line)
// What changed:
// - "urgent care", "walk in clinic" leave Feeling sick (normal) for a new situation, Urgent care (urgent): read as urgent,
//   so known-closed answers are left out and open now leads (audit 2026-11-27 F10).
// - Each "urgent care" need names Urgent Care% first and carries the word "urgent", so solutions() puts urgent care ahead
//   of other clinics (audit C7).
        ['someone-hurt', 'Someone is hurt or very sick', 'urgent', 1, 'resident',
            ['someone is hurt', 'badly hurt', 'bleeding badly', 'wont stop bleeding', 'chest pain', 'heart attack', 'cant breathe', 'not breathing', 'overdose', 'poisoned', 'snake bite', 'stroke', 'unconscious', 'passed out', 'seizure', 'head injury'],
            [['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%|Medical Centers', 'emergency', 'listing', 'phone'],
             ['Ambulance', 'resident', 'Ambulance Service|Air Ambulance Service|Fire Departments', '', 'listing', 'phone'],
             ['Urgent care and clinics', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Health Services', 'urgent', 'listing', 'phone']]],
        ['injury', 'An injury', 'urgent', 0, 'resident',
            ['broke my', 'broken my', 'broke my arm', 'broke my leg', 'broke my ankle', 'broke my wrist', 'broke my foot', 'broke my hand', 'broke my finger', 'broke my toe',
             'broke my nose', 'broke my rib', 'broken bone', 'broken arm', 'broken leg', 'broken ankle', 'broken wrist', 'sprained', 'sprained my ankle', 'twisted my ankle', 'twisted my knee',
             'hurt my back', 'hurt my knee', 'hurt my', 'injured', 'injury', 'cut myself', 'need stitches', 'deep cut', 'dislocated', 'fell and hurt', 'bad burn'],
            [['Urgent care and clinics', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Health Services|Medical Centers', 'urgent', 'listing', 'phone'],
             ['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%', 'emergency', 'listing', 'phone'],
             ['Bone and joint doctors', 'resident', 'Orthopedic Surgeons|Physicians & Surgeons', '', 'listing', 'phone'],
             ['Braces, crutches and supplies', 'resident', 'Pharmacies|Home Health & Health Care Equipment', '', 'listing', 'where'],
             ['Getting better', 'resident', 'Physical Therapists|Chiropractors DC|Occupational Therapists', '', 'listing', 'phone']]],
        ['feel-sick', 'Feeling sick', 'normal', 0, 'resident',
            ['feel sick', 'im sick', 'fever', 'flu', 'sore throat', 'need a doctor', 'see a doctor'],
            [['Clinics and urgent care', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Health Services|Medical Centers', 'urgent', 'listing', 'phone'],
             ['Doctors', 'resident', 'Physicians & Surgeons|Nurses-Practitioners|Physicians Assistants', '', 'listing', 'phone'],
             ['Pharmacies', 'resident', 'Pharmacies|Pharmacists', '', 'listing', 'phone']]],
        // "urgent care" is a need that can't wait (decisions/0181; the listing audit 2026-11-27, F10 and C7): urgent, so open
        // now leads and what's known to be closed isn't offered; urgent care itself before other clinics. Not a danger
        // situation (no 911 line): that list is Admin's (principle 6).
        ['urgent-care', 'Urgent care', 'urgent', 0, 'resident',
            ['urgent care', 'urgent care clinic', 'urgent care center', 'urgentcare', 'walk in clinic', 'walk in care', 'emergency room', 'emergency department'],
            [['Urgent care and walk-in clinics', 'resident', 'Urgent Care%|Clinics|Ambulatory Health Care Services|Medical Centers', 'urgent walk', 'listing', 'phone'],
             ['Emergency room and hospitals', 'resident', 'Hospitals|Emergency Medical%|Medical Centers', 'emergency', 'listing', 'phone']]],
=== END FILE ===

=== FILE: files/app/lib/Situations.php.region-2.php ===
<?php
// ANCHOR: replaces     private const SEED_VERSION = '13'; through     private const SEED_VERSION = '13';
    private const SEED_VERSION = '14';
=== END FILE ===

=== FILE: files/app/lib/Situations.php.region-3.php ===
<?php
// ANCHOR: replaces     private static function solutions(PDO $pdo, array $n, ?array $place, array $o, bool $urgent): array through         return [array_slice($items, 0, $per), $incomplete, $missed];
// (the method's closing brace after that line stays as it is)
// What changed:
// - the need's own order ahead of weight (decisions/0061's health-order note; audit C7): the category pattern a listing
//   was found by, in the need's order, then a listing whose words carry the need's words ("urgent"), then weight, in SQL
//   (so LIMIT 400 never cuts the first kind) and, in the place, ahead of distance in the final sort.
// - an interim guard for audit C5: a listing named in NPI's "Last, First" form with no business word is a person, not
//   a place, and isn't offered for a need. The root fix is AutoImport/IntakeStager/DataCleanup (not in this release).
    private static function solutions(PDO $pdo, array $n, ?array $place, array $o, bool $urgent): array
    {
        $kinds = array_values(array_intersect(array_map('trim', explode(',', (string) $n['kinds'])), ['listing', 'outdoors', 'story', 'group', 'experience', 'topic']));
        if (!$kinds) $kinds = ['listing'];
        $p = [];
        $or = [];
        $cats = self::catIds($pdo, (string) $n['cats']);
        // the need's own order: the first category it names leads ("Urgent Care%" before "Clinics"), never the listing number
        $pos = [];
        foreach (explode('|', (string) $n['cats']) as $i => $pat) {
            $pat = trim($pat); if ($pat === '') continue;
            foreach (self::catIds($pdo, $pat) as $cid) if (!isset($pos[$cid])) $pos[$cid] = $i;
        }
        // a need that names categories takes businesses from them only ("key" isn't Key Cannabis Dispensary); its words
        // find outdoor places, stories, groups and experiences, and businesses only when it names no category
        if ($cats) $or[] = "(si.kind = 'listing' AND si.category_id IN (" . implode(',', array_map('intval', $cats)) . '))';
        $wor = []; $nw = [];
        foreach (array_slice(array_values(array_filter(explode(' ', (string) $n['words']))), 0, 8) as $i => $w) {
            $wn = trim(SearchIndex::norm($w));
            if ($wn === '') continue;
            $wor[] = "si.terms LIKE :nw$i";
            $p[":nw$i"] = '% ' . $wn . ' %';
            $nw[$i] = $wn;
        }
        if ($wor) $or[] = ($cats ? "si.kind <> 'listing' AND " : '') . '(' . implode(' OR ', $wor) . ')';
        if (!$or) return [[], 0];
        $kin = implode(',', array_map(fn ($k) => "'" . $k . "'", $kinds));
        $here = $place ? SearchIndex::whereSql($pdo, $place, null, $p) : '';
        $center = $o['center'] ?? null; $reach = (string) ($o['reach'] ?? 'all');
        $box = '';
        if ($center && $reach !== 'all' && $reach !== 'place') {
            $d = max(1, (int) $reach) / 69.0;
            $box = ' AND si.lat BETWEEN :b1 AND :b2 AND si.lon BETWEEN :b3 AND :b4';
            $p += [':b1' => $center[0] - $d, ':b2' => $center[0] + $d, ':b3' => $center[1] - $d * 1.3, ':b4' => $center[1] + $d * 1.3];
        }
        // the need's order and its words ahead of weight, so the 400 kept are the right ones
        $posSql = '';
        if (count(array_unique($pos)) > 1) {
            $by = [];
            foreach ($pos as $cid => $i) $by[$i][] = (int) $cid;
            ksort($by);
            $posSql = 'CASE';
            foreach ($by as $i => $ids) $posSql .= ' WHEN si.category_id IN (' . implode(',', $ids) . ') THEN ' . (int) $i;
            $posSql .= ' ELSE 99 END, ';
        }
        $hitSql = '';
        if ($nw) {
            $hs = [];
            foreach ($nw as $i => $wn) { $hs[] = "si.terms LIKE :nh$i"; $p[":nh$i"] = '% ' . $wn . ' %'; }   // own names: a name can't be bound twice
            $hitSql = '(' . implode(' OR ', $hs) . ') DESC, ';
        }
        $sql = "SELECT si.kind, si.item_id, si.label, si.sub, si.url, si.lat, si.lon, si.hours, si.private_location, si.city, si.state, si.category_id, si.terms"
             . ($here !== '' ? ", COALESCE($here, 0) AS here" : ', 0 AS here')
             . " FROM search_index si WHERE si.kind IN ($kin) AND (" . implode(' OR ', $or) . ")$box"
             . ' ORDER BY here DESC, ' . $posSql . $hitSql . 'si.weight DESC LIMIT 400';
        try { $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { error_log('situations need: ' . $e->getMessage()); return [[], 0]; }
        // a person isn't a place (audit C5, interim): NPI's "Last, First" form with no business word in it
        $person = static fn (string $s): bool => (bool) preg_match('/^\p{Lu}[\p{L}\'\-]+,\s*\p{Lu}[\p{L}.]*(\s+\p{Lu}[\p{L}.]*){0,3}$/u', trim($s))
            && !preg_match('/&|\b(inc|llc|co|company|clinic|center|centre|pharmacy|hospital|health|care|services?|group|associates|and|medical)\b/i', $s);
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
            if ($r['kind'] === 'listing' && $person((string) $r['label'])) continue;
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
            $terms = ' ' . trim((string) ($r['terms'] ?? '')) . ' ';
            $hit = false;
            foreach ($nw as $wn) if (str_contains($terms, ' ' . $wn . ' ')) { $hit = true; break; }
            $items[] = ['kind' => (string) $r['kind'], 'id' => (string) $r['item_id'], 'label' => (string) $r['label'], 'sub' => (string) $r['sub'], 'url' => (string) $r['url'],
                        'phone' => $phone, 'open' => $open, 'always' => $always, 'here' => (int) $r['here'] === 1, 'miles' => $miles !== null ? round($miles) : null,
                        'peek' => $r['kind'] === 'listing',
                        '_pos' => $r['category_id'] !== null && isset($pos[(int) $r['category_id']]) ? $pos[(int) $r['category_id']] : 99, '_hit' => $hit];
        }
        // in the place first; urgent: open now first; in the place, the need's own order (its first kind, then its words)
        // before distance; beyond the place, nearest first, then the need's order
        usort($items, fn ($a, $b) => [!$a['here'], $urgent ? $a['open'] !== true : 0, $a['here'] ? $a['_pos'] : 0, $a['here'] ? !$a['_hit'] : 0, $a['miles'] ?? 99999, $a['_pos'], !$a['_hit']]
                                 <=> [!$b['here'], $urgent ? $b['open'] !== true : 0, $b['here'] ? $b['_pos'] : 0, $b['here'] ? !$b['_hit'] : 0, $b['miles'] ?? 99999, $b['_pos'], !$b['_hit']]);
        $items = array_map(function ($x) { unset($x['_pos'], $x['_hit']); return $x; }, $items);
        $per = (int) ($o['per'] ?? 5);
        return [array_slice($items, 0, $per), $incomplete, $missed];
=== END FILE ===

=== FILE: files/app/lib/SearchIndex.php.region-1.php ===
<?php
// ANCHOR: replaces     public static function refresh(PDO $pdo, bool $force = false): void through             error_log('SearchIndex::refresh: ' . $e->getMessage());
// (the catch's and the method's closing braces after that line stay as they are)
// What changed: the catch-up is the scheduled job search_index_tick (Scheduler::JOBS), which calls refresh($pdo, true, true).
// While the job has run in the last 30 minutes, a visitor's search no longer does the work (decisions/0181: "search is
// running really slow"). If the job stops, searches take it back up after 30 minutes, as before.
    public static function refresh(PDO $pdo, bool $force = false, bool $byJob = false): void
    {
        try {
            if (!self::ready($pdo)) return;
            if (!$force && !$byJob) {
                $job = self::state($pdo, 'refresh_job_at');
                if ($job !== '' && strtotime($job) > time() - 1800) return;   // the scheduled job keeps it current
            }
            if ($byJob) self::setState($pdo, 'refresh_job_at', date('Y-m-d H:i:s'));
            $last = self::state($pdo, 'refreshed_at');
            if (!$force && $last !== '' && strtotime($last) > time() - 900) return;
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
=== END FILE ===

=== FILE: files/app/scripts/search_index_tick.php ===
<?php
// app/scripts/search_index_tick.php — the search index's catch-up (SearchIndex::refresh), every 15 minutes from cron
// (Scheduler::JOBS 'search_index_tick'). It used to run inside a visitor's search; a search now does it only when this
// job hasn't run in 30 minutes. Database only: no outside call.
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
require_once __DIR__ . '/../lib/SearchIndex.php';

Scheduler::run('search_index_tick');

$pdo = Database::connection();
if (!SearchIndex::ready($pdo)) { echo "No search index yet: nothing to catch up.\n"; exit(0); }
$t = microtime(true);
SearchIndex::refresh($pdo, true, true);
echo 'Search index caught up (mark ' . SearchIndex::state($pdo, 'listing_mark') . ') in ' . round(microtime(true) - $t, 1) . " s\n";
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
        'search_index_tick' => ['Search index catch-up', 'Every 15 minutes: brings search up to date with the listings changed since its last look (600 at a time, carrying on where it stopped), rebuilds stories, groups and topics, and indexes new landmarks (SearchIndex::refresh). It used to run inside a visitor\'s search, which slowed that search (decisions/0181); a search still does it when this job hasn\'t run in 30 minutes. Database only: no outside call.', '15min', 60],
        'preflight_tick' => ['Pre-flight', 'Runs every pre-flight test (System Configuration → Pre-flight) each night at 2 am Arizona time, and within 15 minutes of a .env change: real, free, read-only tests of every service, setting, workflow and task, red lines to Pulse ("Pre-flight"). It never starts, pauses or changes anything.', '15min', 60],
        'system_start_tick' => ['Start the system, carried on', 'Works through Start the system (System Configuration → Automation) once it\'s pressed: the keys, watching, the switch-on check, Railway\'s schedule, the master switch, then each workflow and new task one at a time behind its gate (decisions/0163 §10). Does nothing until it\'s pressed, and never on staging.', '10min', 120],
        'staging_publish_tick' => ['Staging: Publish, carried on', 'Works through a Publish of staging on the server once it\'s started (the site manager), a step at a time, so the page can close; starts a scheduled one at its time after a fresh preview (decisions/0165 §4). Does nothing when none is running.', 'minute', 15],
    ];

    /** Jobs that set themselves up in cPanel (sync()), on their usual schedule, with no one opening cPanel (Jason, 2026-10-28):
     *  every new job goes here. One removed on the Scheduled tasks page stays removed. */
    public const AUTO = ['control_tick', 'founder_mail_tick', 'team_tick', 'team_audit_tick', 'moments_tick', 'earned_storage_tick', 'guide_ondemand_tick', 'journeys_tick', 'landmark_photos_tick', 'like_watch_tick', 'guest_tick', 'region_tick', 'work_orders_tick', 'pay_wait_tick', 'got_it_tick', 'qb_orders_tick', 'guide_finds_tick', 'guide_live_tick', 'listings_tick', 'services_tick', 'heritage_tick', 'takedowns_tick', 'daily_report', 'market_tick', 'notice_digest', 'notify_release', 'listing_messages_tick', 'mail_sync', 'mail_keep', 'pulse_followup', 'booking_remind', 'ses_setup_tick', 'domains_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick', 'vault_digest_tick', 'audit_clock_tick', 'system_start_tick', 'frontier_tick', 'search_index_tick', 'preflight_tick', 'files_tick'];

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
     * A start the database refused (2026-11-23: some jobs didn't start on a minute when others did; "Too many connections"
     * is the usual cause on shared hosting): kept, the last 200, for /api/health.php's jobs section, so the daily report
     * shows it without anyone reading the server's error log. Never throws.
     */
    public static function missed(string $job, string $why): void
    {
        try {
            $f = self::missFile(); if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
            $h = @fopen($f, 'c+'); if (!$h) return;
            if (flock($h, LOCK_EX)) {
                $all = json_decode((string) stream_get_contents($h), true); if (!is_array($all)) $all = [];
                $all[] = ['job' => $job, 'at' => gmdate('Y-m-d\TH:i:s\Z'), 'why' => mb_substr(preg_replace('/\s+/', ' ', $why), 0, 160)];
                $all = array_slice($all, -200);
                ftruncate($h, 0); rewind($h); fwrite($h, json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); fflush($h); flock($h, LOCK_UN);
            }
            fclose($h);
        } catch (Throwable $x) {}
    }

    /** The starts the database refused: [[job, at (UTC), why]], oldest first. */
    public static function misses(): array
    {
        $all = @json_decode((string) @file_get_contents(self::missFile()), true);
        return is_array($all) ? $all : [];
    }

    /** The last run of each job: job => row. */
    public static function lastRuns(PDO $pdo): array
    {
        self::ensure($pdo);
        $out = [];
        foreach (array_keys(self::JOBS) as $j) {
            $st = $pdo->prepare('SELECT * FROM ' . Database::t('job_runs') . ' WHERE job = ? ORDER BY id DESC LIMIT 1'); $st->execute([$j]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $f = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('job_runs') . ' WHERE job = ? AND ok = 0 AND started_at > NOW() - INTERVAL 1 DAY'); $f->execute([$j]);
                $r['fails_today'] = (int) $f->fetchColumn();
                $out[$j] = $r;
            }
        }
        return $out;
    }

    // ---- the cron jobs in cPanel ---------------------------------------------------------------------------------------

    /** Every cron line in cPanel: [line, linekey, minute, hour, day, month, weekday, command]. */
    public static function cronLines(): array
    {
        $out = [];
        foreach (CPanel::api2('Cron', 'listcron') as $r) {
            if (!is_array($r) || !isset($r['command'])) continue;   // the list ends with a count
            $out[] = ['line' => (int) ($r['line'] ?? 0), 'linekey' => (string) ($r['linekey'] ?? ''), 'minute' => (string) $r['minute'], 'hour' => (string) $r['hour'], 'day' => (string) $r['day'],
                'month' => (string) $r['month'], 'weekday' => (string) $r['weekday'], 'command' => (string) $r['command']];
        }
        return $out;
    }

    /** Which cron line runs a job (null when none). */
    public static function lineFor(array $lines, string $job, bool $staging = false): ?array
    {
        foreach ($lines as $l) if (str_contains($l['command'], 'app/scripts/' . $job . '.php') && self::isStagingLine($l) === $staging) return $l;
        return null;
    }

    /** Is this one of staging's own copies (its scripts are in staging's folder)? Live's page and sync() never touch those. */
    public static function isStagingLine(array $l): bool { return str_contains((string) $l['command'], '/staging.trvgo.us/'); }

    // ---- staging's own copies of the jobs (decisions/0165 §1) -----------------------------------------------------------

    /** Jobs that only talk to the outside world, or run staging itself: never copied to staging. */
    /** Jobs that log a run only when they have work (a staging refresh going, live's changes to push, a release waiting):
     *  long quiet isn't a fault, so the site audit doesn't count their silence (Site health D03, 2026-11-18). */
    public const QUIET = ['founder_mail_tick' /* emails the Founder: logs only when it sends */, 'team_tick' /* the Team's runner: staging only, so on live it never logs (Piece 2) */, 'team_audit_tick' /* and its daily audit, the same */, 'staging_carry_tick', 'staging_push_tick', 'releases_tick', 'system_start_tick' /* logs only while Start the system is going (its script: "does nothing (and logs nothing) until the button is pressed"); 2026-11-20, Automation read "last ran 2 days ago … and is late" */];
    /** Jobs kept in the list but no longer run (their description says why): never late. */
    public const RETIRED = ['staging_tick'];
    public const NOT_ON_STAGING = ['founder_mail_tick', 'ses_setup_tick', 'domains_tick', 'audit_clock_tick', 'staging_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick'];

    /**
     * Staging's copy of each job, in cron through live's cPanel key (staging has none), pointing at staging's folder: they
     * run on staging's data under the gate. Only while "Staging runs its own jobs" is on (shipped off); off takes them out.
     * $scripts: staging's app/scripts folder. Returns [added, removed].
     */
    public static function syncStaging(bool $on, string $scripts): array
    {
        if (!CPanel::ready()) return [[], []];
        $lines = self::cronLines(); $added = []; $gone = [];
        foreach ($lines as $l) if (self::isStagingLine($l) && (!$on || !preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $m) || in_array($m[1], self::NOT_ON_STAGING, true))) {
            CPanel::api2('Cron', 'remove_line', ['line' => $l['line'], 'linekey' => $l['linekey']]);
            if (preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $m)) $gone[] = $m[1];
            $lines = self::cronLines();
        }
        if (!$on) return [[], $gone];
        $php = trim((string) getenv('CRON_PHP')) ?: '/usr/local/bin/php';
        foreach ($lines as $l) if (preg_match('#^(\S*php\S*)\s+\S*app/scripts/#', $l['command'], $m)) { $php = $m[1]; break; }
        foreach (self::AUTO as $job) {
            if (in_array($job, self::NOT_ON_STAGING, true) || !isset(self::JOBS[$job]) || !self::lineFor($lines, $job)) continue;   // only what live runs
            if (self::lineFor($lines, $job, true)) continue;
            $live = self::lineFor($lines, $job);
            CPanel::api2('Cron', 'add_line', ['command' => $php . ' ' . rtrim($scripts, '/') . '/' . $job . '.php >/dev/null 2>&1', 'minute' => ($at = self::stagingTime($live))['minute'], 'hour' => $at['hour'], 'day' => $at['day'], 'month' => $at['month'], 'weekday' => $at['weekday']]);
            $added[] = $job;
        }
        return [$added, $gone];
    }

    /** Minutes after live's start that staging's copy of a job starts (decisions/0165 §1: staging never costs live its limits;
     *  2026-11-26, the CEO's ruling: live's 6:13 daily jobs missed their start with staging's copies in the same minute against
     *  the database's 25 connections). */
    public const STAGING_OFFSET = 7;

    /** When staging's copy of a job runs: live's time, STAGING_OFFSET minutes later where live's minute is one number (a minute
     *  past :59 carries into the next hour, and past 23:59 into the next day when no day is set; where a day, month or weekday is
     *  set, or the hour is a list, a range or every N hours, it starts STAGING_OFFSET minutes earlier instead, so it never lands on another day or
     *  hour). A minute that isn't one number (every minute, every N minutes, a list) is kept as live's. */
    public static function stagingTime(array $live): array
    {
        $t = ['minute' => (string) $live['minute'], 'hour' => (string) $live['hour'], 'day' => (string) $live['day'], 'month' => (string) $live['month'], 'weekday' => (string) $live['weekday']];
        if (!preg_match('/^\d{1,2}$/', $t['minute'])) return $t;
        $m = (int) $t['minute'] + self::STAGING_OFFSET;
        if ($m < 60) { $t['minute'] = (string) $m; return $t; }
        $anyDay = $t['day'] === '*' && $t['month'] === '*' && $t['weekday'] === '*';
        if ($t['hour'] === '*') { $t['minute'] = (string) ($m - 60); return $t; }   // every hour: past :59 is the next hour's start
        if (preg_match('/^\d{1,2}$/', $t['hour']) && ((int) $t['hour'] < 23 || $anyDay)) { $t['minute'] = (string) ($m - 60); $t['hour'] = (string) (((int) $t['hour'] + 1) % 24); return $t; }
        $t['minute'] = (string) ((int) $t['minute'] - self::STAGING_OFFSET);   // a list, a range or a set day: earlier, never another day
        return $t;
    }

    /** Staging's copies moved to their time (stagingTime) where they still start with live's: the copies made before the offset,
     *  and a live line changed since. Run by sync(), hourly at most; staging's lines only, live's never touched. */
    public static function offsetStaging(): array
    {
        if (!CPanel::ready()) return [];
        $moved = [];
        foreach (self::cronLines() as $l) {
            if (!self::isStagingLine($l) || !preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $mm)) continue;
            $lines = self::cronLines(); $live = self::lineFor($lines, $mm[1]); $cur = self::lineFor($lines, $mm[1], true);
            if (!$live || !$cur) continue;
            $at = self::stagingTime($live);
            if ($cur['minute'] === $at['minute'] && $cur['hour'] === $at['hour'] && $cur['day'] === $at['day'] && $cur['month'] === $at['month'] && $cur['weekday'] === $at['weekday']) continue;
            CPanel::api2('Cron', 'edit_line', ['line' => $cur['line'], 'linekey' => $cur['linekey'], 'command' => $cur['command']] + $at);
            $moved[] = $mm[1];
        }
        return $moved;
    }

    /** The command for a job: PHP (the one our other jobs use, else CRON_PHP, else cPanel's usual), the script, output to nowhere (no email each run). */
    public static function command(string $job, array $lines = []): string
    {
        $php = trim((string) getenv('CRON_PHP')) ?: '/usr/local/bin/php';
        foreach ($lines as $l) if (preg_match('#^(\S*php\S*)\s+\S*app/scripts/#', $l['command'], $m)) { $php = $m[1]; break; }
        $script = (realpath(__DIR__ . '/../scripts') ?: __DIR__ . '/../scripts') . '/' . $job . '.php';
        return $php . ' ' . $script . ' >/dev/null 2>&1';
    }

    /**
     * Sets up the AUTO jobs cPanel doesn't have yet, on their usual schedule, through the cPanel API: at most once an hour
     * unless $now. A job removed on the page (or one we set up and someone took out in cPanel) isn't put back. Returns
     * the jobs it added.
     */
    public static function sync(PDO $pdo, bool $now = false): array
    {
        // once an hour at most, for all of it (the cron gap of 2026-11-23: every job's start, about 40 jobs, ai_worker every
        // minute, ran the five upkeep steps below before this guard, up to 300 listings re-scored each time; a pile-up holds
        // the server's processes and database connections, and the next jobs don't start). The upkeep keeps its batch sizes.
        self::ensure($pdo);
        $t = Database::t('job_setup');
        if (!$now) {
            $st = $pdo->query("SELECT at FROM $t WHERE job = '_sync'"); $last = $st ? $st->fetchColumn() : false;
            if ($last && strtotime((string) $last) > time() - 3600) return [];
        }
        $pdo->prepare("REPLACE INTO $t (job, state, at) VALUES ('_sync', 'ran', NOW())")->execute();
        try { require_once __DIR__ . '/../admin/DataMove.php'; DataMove::home($pdo); } catch (Throwable $x) { error_log('identity home: ' . $x->getMessage()); }   // the identity record lives in main (decisions/0133)
        try { require_once __DIR__ . '/Identity.php'; Identity::backfillIntake($pdo, 50); Identity::backfillListings($pdo, 50); require_once __DIR__ . '/crawler/IntakeStager.php'; IntakeStager::tidySources($pdo); require_once __DIR__ . '/ListingConfidence.php'; ListingConfidence::backfill($pdo, 300); ListingConfidence::stillOpenTick($pdo, 300); } catch (Throwable $x) { error_log('intake identities: ' . $x->getMessage()); }   // sightings from before 2026-11-01 get theirs
        try { require_once __DIR__ . '/DbUpdates.php'; DbUpdates::autoRun($pdo); } catch (Throwable $x) { error_log('db updates: ' . $x->getMessage()); }   // new migration files run themselves, at most once an hour
        try { require_once __DIR__ . '/SchemaUpkeep.php'; if (SchemaUpkeep::scanDue()) SchemaUpkeep::scanUnused($pdo); } catch (Throwable $x) { error_log('unused schema: ' . $x->getMessage()); }   // the monthly check for tables and columns no code uses (decisions/0162)
        if (!CPanel::ready()) return [];
        try { if ($mv = self::offsetStaging()) error_log('staging jobs moved to live + ' . self::STAGING_OFFSET . ' min: ' . implode(', ', $mv)); } catch (Throwable $x) { error_log('staging job offset: ' . $x->getMessage()); }
        $known = $pdo->query("SELECT job, state FROM $t")->fetchAll(PDO::FETCH_KEY_PAIR);
        $lines = self::cronLines(); $added = [];
        foreach (self::AUTO as $job) {
            if (!isset(self::JOBS[$job])) continue;
            if (self::lineFor($lines, $job)) { if (!isset($known[$job])) self::mark($pdo, $job, 'added'); continue; }
            if (isset($known[$job])) continue;   // removed, or set up before and taken out by hand: left that way
            $p = self::PRESETS[self::JOBS[$job][2]];
            CPanel::api2('Cron', 'add_line', ['command' => self::command($job, $lines), 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
            self::mark($pdo, $job, 'added'); $added[] = $job;
        }
        return $added;
    }

    private static function mark(PDO $pdo, string $job, string $state): void
    {
        try { self::ensure($pdo); $pdo->prepare('REPLACE INTO ' . Database::t('job_setup') . ' (job, state, at) VALUES (?, ?, NOW())')->execute([$job, $state]); } catch (Throwable $e) { error_log('scheduler mark: ' . $e->getMessage()); }
    }

    public static function add(string $job, string $preset): void
    {
        if (!isset(self::JOBS[$job])) throw new InvalidArgumentException('Not one of our jobs.');
        $p = self::PRESETS[$preset] ?? null; if (!$p) throw new InvalidArgumentException('Choose when it runs.');
        $lines = self::cronLines();
        if (self::lineFor($lines, $job)) throw new InvalidArgumentException('It\'s already set up in cPanel.');
        CPanel::api2('Cron', 'add_line', ['command' => self::command($job, $lines), 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
        self::mark(Database::connection(), $job, 'added');
    }

    public static function change(string $job, string $preset): void
    {
        $p = self::PRESETS[$preset] ?? null; if (!$p) throw new InvalidArgumentException('Choose when it runs.');
        $l = self::lineFor(self::cronLines(), $job); if (!$l) throw new InvalidArgumentException('It isn\'t set up in cPanel yet.');
        CPanel::api2('Cron', 'edit_line', ['line' => $l['line'], 'linekey' => $l['linekey'], 'command' => $l['command'], 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
    }

    public static function remove(string $job): void
    {
        $l = self::lineFor(self::cronLines(), $job); if (!$l) throw new InvalidArgumentException('It isn\'t set up in cPanel.');
        CPanel::api2('Cron', 'remove_line', ['line' => $l['line'], 'linekey' => $l['linekey']]);
        self::mark(Database::connection(), $job, 'removed');   // sync() leaves it out from now on
    }

    /** The preset a line matches ('' when it's set to something else). */
    public static function presetOf(array $l): string
    {
        foreach (self::PRESETS as $k => $p) if ([$l['minute'], $l['hour'], $l['day'], $l['month'], $l['weekday']] === array_slice($p, 1)) return $k;
        return '';
    }

    /** A cron schedule in words, as well as can be said. */
    public static function words(array $l): string
    {
        $k = self::presetOf($l);
        return $k !== '' ? self::PRESETS[$k][0] : trim($l['minute'] . ' ' . $l['hour'] . ' ' . $l['day'] . ' ' . $l['month'] . ' ' . $l['weekday']) . ' (cron)';
    }
}
=== END FILE ===

=== FILE: tests/search-release-1-situations.php ===
<?php
// tools/search-tests/search-release-1-situations.php — plain PHP, prints PASS/FAIL. No network, no money, no live DB
// (SQLite in memory). Run from the repo: php tools/search-tests/search-release-1-situations.php  (or set TV_ROOT).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/SearchIndex.php';
require_once $root . '/app/lib/Situations.php';

$fails = 0;
function check(string $what, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . '  ' . $what . "\n"; if (!$ok) $fails++; }

// (a) "urgent care" is read as an urgent situation, and only by one situation
$rc = new ReflectionClass('Situations');
$seed = $rc->getConstant('SEED');
$by = []; foreach ($seed as $row) $by[$row[0]] = $row;
check('urgent-care situation exists', isset($by['urgent-care']));
check('urgent-care is urgent', ($by['urgent-care'][2] ?? '') === 'urgent');
check('urgent-care is not a danger situation', (int) ($by['urgent-care'][3] ?? 1) === 0);
check('"urgent care" is one of its phrases', in_array('urgent care', $by['urgent-care'][5] ?? [], true));
$owners = [];
foreach ($seed as $row) foreach ($row[5] as $ph) if (Situations::norm($ph) === 'urgent care') $owners[] = $row[0];
check('"urgent care" belongs to urgent-care only', $owners === ['urgent-care']);
check('Feeling sick no longer holds "walk in clinic"', !in_array('walk in clinic', $by['feel-sick'][5] ?? [], true));
check('SEED_VERSION bumped to 14', $rc->getConstant('SEED_VERSION') === '14');
$first = $by['urgent-care'][6][0] ?? [];
check('its first need names Urgent Care% first', str_starts_with((string) ($first[2] ?? ''), 'Urgent Care%'));
check('its first need requires a phone', ($first[5] ?? '') === 'phone');

// (b) the need's order ahead of weight; (C5 interim) a person isn't offered
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, is_active INT DEFAULT 1)');
$pdo->exec('CREATE TABLE search_index (kind TEXT, item_id TEXT, label TEXT, sub TEXT, url TEXT, lat REAL, lon REAL, hours TEXT, private_location INT DEFAULT 0, city TEXT, state TEXT, category_id INT, terms TEXT, weight INT DEFAULT 0, why TEXT)');
$pdo->exec('CREATE TABLE entity_metadata (entity_id INT, meta_key TEXT, meta_value TEXT)');
$pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'Urgent Care Centers'), (2, 'Clinics')");
$rows = [
    [10, 'Clinic A', 2, ' clinic a clinics ', 10],
    [11, 'Banner Urgent Care', 2, ' banner urgent care clinics ', 0],
    [12, 'QuickMed', 1, ' quickmed urgent care centers ', 0],
    [13, 'Smith, Jane', 2, ' smith jane clinics ', 20],
];
$ins = $pdo->prepare("INSERT INTO search_index (kind, item_id, label, sub, url, city, state, category_id, terms, weight) VALUES ('listing', ?, ?, '', '', 'Show Low', 'AZ', ?, ?, ?)");
$meta = $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (?, 'phone', ?)");
foreach ($rows as [$id, $label, $cat, $terms, $w]) { $ins->execute([(string) $id, $label, $cat, $terms, $w]); $meta->execute([$id, json_encode('(928) 555-01' . $id)]); }
$m = new ReflectionMethod('Situations', 'solutions');
$m->setAccessible(true);
$need = ['cats' => 'Urgent Care%|Clinics', 'words' => 'urgent', 'kinds' => 'listing', 'requires' => 'phone'];
$res = $m->invoke(null, $pdo, $need, null, ['per' => 10], false);
$labels = array_column($res[0], 'label');
check('urgent care category first, then a clinic named urgent care, then by weight', $labels === ['QuickMed', 'Banner Urgent Care', 'Clinic A']);
check('a "Last, First" person is not offered', !in_array('Smith, Jane', $labels, true));
check('no internal sort keys leak', !array_key_exists('_pos', $res[0][0] ?? []) && !array_key_exists('_hit', $res[0][0] ?? []));

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: tests/search-release-1-index-job.php ===
<?php
// tools/search-tests/search-release-1-index-job.php — plain PHP, prints PASS/FAIL. SQLite in memory; no cPanel call, no
// network. Run from the repo: php tools/search-tests/search-release-1-index-job.php  (or set TV_ROOT).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/Scheduler.php';
require_once $root . '/app/lib/SearchIndex.php';

$fails = 0;
function check(string $what, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . '  ' . $what . "\n"; if (!$ok) $fails++; }

// (c) the job is in Scheduler::JOBS, sets itself up, and its script is there
check('search_index_tick in JOBS', isset(Scheduler::JOBS['search_index_tick']));
check('its preset exists', isset(Scheduler::PRESETS[Scheduler::JOBS['search_index_tick'][2] ?? '']));
check('late is at least its schedule', (int) (Scheduler::JOBS['search_index_tick'][3] ?? 0) >= 15);
check('in AUTO (sets itself up in cPanel)', in_array('search_index_tick', Scheduler::AUTO, true));
check('not kept off staging', !in_array('search_index_tick', Scheduler::NOT_ON_STAGING, true));
check('script exists', is_file($root . '/app/scripts/search_index_tick.php'));
check('job key fits job_runs.job (40)', strlen('search_index_tick') <= 40);

// a search skips the catch-up while the job has run in the last 30 minutes
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE search_index (kind TEXT, item_id TEXT)');
$pdo->exec('CREATE TABLE search_index_state (k TEXT PRIMARY KEY, v TEXT)');
$old = date('Y-m-d H:i:s', time() - 7200);
SearchIndex::setState($pdo, 'refreshed_at', $old);
SearchIndex::setState($pdo, 'refresh_job_at', date('Y-m-d H:i:s', time() - 60));
SearchIndex::refresh($pdo);   // as a visitor's search calls it
check('a search leaves it to the job', SearchIndex::state($pdo, 'refreshed_at') === $old);

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
## Why does a search for "urgent care" show what's open first?

Urgent care is something you need now, so we treat it that way. Places that are open now, or open 24 hours, come first,
and places we know are closed right now aren't offered. Urgent care centers come before other clinics, then emergency
rooms and hospitals. Where a place's hours aren't known, we say so; we never guess that it's open. If anyone is in
danger, call 911.

## What does "How sure we are" mean on a listing? (coming)

Coming. Today the label counts the sources that agree on a listing's address, phone and website. We're changing it to
say exactly what it vouches for ("Address and phone confirmed by 2 sources"), and a listing whose kind of business or
description doesn't match its sources will show as "Being checked" until it's put right.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "search-release-1",
  "gate": "1 (live harm; decisions/0181's note, #1620)",
  "what": "(a) 'urgent care', 'walk in clinic', 'emergency room' read as a new urgent situation, Urgent care (open now first, known-closed left out), and taken out of Feeling sick; SEED_VERSION 13 -> 14. (b) Situations::solutions orders by the need's own category order and its words ahead of weight (SQL, so LIMIT 400 keeps the right rows) and, in the place, ahead of distance; urgent care before clinics (audit C7); interim C5 guard: NPI 'Last, First' person names aren't offered as answers to a need. (c) SearchIndex::refresh moved off the search request into a scheduled job, search_index_tick (Scheduler::JOBS and AUTO, every 15 min); a search runs it again only when the job hasn't run in 30 minutes. (d) C3, C5, C6: their root-cause files are not attached (see verify); only the C5 interim guard above and the C6 FAQ wording ('coming') are in this release.",
  "files": [
    "files/app/lib/Situations.php.region-1.php",
    "files/app/lib/Situations.php.region-2.php",
    "files/app/lib/Situations.php.region-3.php",
    "files/app/lib/SearchIndex.php.region-1.php",
    "files/app/scripts/search_index_tick.php",
    "files/app/lib/Scheduler.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "decisions/0061 (situations; health-order note; principle 5 and 6: Urgent care is urgent, not a danger situation)",
    "decisions/0060 (2026-11-27 note: a need like urgent care ranks that exact kind first)",
    "decisions/0181 (live harm; 'search is running really slow')",
    "decisions/0164 (people aren't places: C5)",
    "research/listing-audit-2026-11-27.md (F10, C3, C5, C6, C7)"
  ],
  "tests": [
    "tests/search-release-1-situations.php -> tools/search-tests/ (seed reading of 'urgent care'; need order ahead of weight on SQLite; person guard)",
    "tests/search-release-1-index-job.php -> tools/search-tests/ (job in JOBS/AUTO, script present, a search defers to the job)"
  ],
  "risks": [
    "Nothing here was run, tested, linted or verified: every file is a DRAFT for the lane session.",
    "The attached DRAFT (job 17a, research/ai-results/job17-patch-drafts/patch-search-release-1.md) was EMPTY: no draft diffs could be reviewed against the live files. This release was written from the live files directly; compare with the real draft if it exists.",
    "Situations.php was attached truncated (ends inside logTerm), so it is shipped as regions only; the lane must apply them to the full live file.",
    "Region 3 changes ordering for every need, not only health: in the place, the need's first-named category now leads ahead of distance (e.g. Wrecker Service ahead of a nearer Towing%). Beyond the place, nearest still leads.",
    "The C5 person guard drops any listing named exactly 'Word, Word …' with no business word; a real business named that way would stop being offered for needs (still in the directory).",
    "If Feeling sick was edited in Admin (source 'admin'), the seed refresh leaves its 'urgent care' phrase and both situations will answer together.",
    "'emergency room' and 'emergency department' now read as Urgent care (urgent, no 911 line); someone-hurt keeps the 911 line for its own phrases.",
    "If cron for search_index_tick isn't set up or is paused by Automation::job, searches resume the catch-up after 30 minutes (no worse than today), but a 30-minute gap in freshness is possible.",
    "No real money moves anywhere in this release or its tests; no QuickBooksPay calls."
  ],
  "verify": [
    "Draft file research/ai-results/job17-patch-drafts/patch-search-release-1.md: attached empty; get the real draft and diff it against these files.",
    "decisions/0061's health-order note: the attachment was truncated before it; check region 3's ordering against its exact words.",
    "app/lib/UniversalSearch.php: file not shown. Audit F10: 'urgent' read as a time word at :355 may strip 'urgent' before Situations::read sees 'urgent care'; it must read the phrase first. Also find every SearchIndex::refresh caller (expected: the search path) and confirm none passes force=true per request.",
    "Category names on live: confirm the category Banner's urgent care (#8938) is filed under and whether any 'Urgent Care%' category exists (SELECT id, name FROM categories WHERE name LIKE '%urgent%').",
    "app/scripts/*_tick.php bootstrap: file not shown. Match search_index_tick.php's requires/env loading to an existing tick script (e.g. frontier_tick.php).",
    "app/lib/Automation.php: file not shown. Confirm Automation::job() doesn't pause 'search_index_tick' (it's database-only upkeep, like frontier_tick).",
    "C3 (NPI taxonomy code -> category on NPI evidence; one category writer): app/lib/Identity.php (fromReferences :971-980), app/lib/crawler/IntakeStager.php (:437, :467-492), app/lib/CategoryClassifier.php (setCategory :289-293), app/lib/crawler/NpiBulk.php, workers/crawler/crawler.py (:1039): file not shown; not built here.",
    "C5 root fix (looksPerson in evaluate's floor :177-178; NPI_CATEGORY only for NPI registry :471; DataCleanup person rule :499 without its no-category condition, with Undo): app/lib/crawler/AutoImport.php, app/lib/crawler/IntakeStager.php, app/lib/DataCleanup.php: file not shown; not built here. ListingIdentity.php was attached but the audit places the fix in evaluate, so it is unchanged.",
    "C6 (label says what it vouches for; kind mismatch or shared-site About holds it at 'Being checked'): app/lib/ListingConfidence.php (:24-31, :85-182, :151) and Identity.php: file not shown; FAQ entry marked coming.",
    "Run both tests at the lane; run a search for 'urgent care show low', 'urgent care', 'walk in clinic', 'feel sick', 'emergency room show low' at 1280 and 390 and check the Urgent care answer and that Feeling sick is unchanged otherwise.",
    "After deploy: Scheduled tasks shows search_index_tick added by sync() and running every 15 min; search_index_state has refresh_job_at advancing; search latency compared before/after."
  ],
  "regions": true
}
=== END FILE ===