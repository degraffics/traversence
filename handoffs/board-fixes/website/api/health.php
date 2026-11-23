<?php
// /api/health.php — the health report for the site audit (decisions/0145 §1, Phase 2): totals only, never anything
// about a person. Locked: Authorization: Bearer <HEALTH_TOKEN> (in .env and the GitHub secret of the same name); it
// answers nothing without the token, and is off while HEALTH_TOKEN isn't set.
//   GET /api/health.php -> {ok, at, php, mysql, tables: [{name, rows}], jobs: [{job, last, last_utc, ok, fails_today, quiet, default, line, line_preset}],
//                           jobs_missed: {last_24h, recent: [{job, at, why}]}, db_connections: {max_connections, max_user_connections, Threads_connected, …}, ai: {...},
//                           cache: {...}, status: [{state, name}], switches: {...}, test_accounts: n,
//                           towns: {place_key: {town, jobs: [{kind, status, found, published, in_review, error}]}}, town_jobs,
//                           crawler: {finds, staged, place_store, websites_taken_off, map_points, …} (the revamp's snapshot),
//                           alarms: [{id, level, process, title, means, since, tripped, acknowledged, stopped, url}] (the Control Board's open trips and warnings),
//                           consistency: {alarms, open, checks, mismatches: [{id, key, level, surface, check, expected, got}], by_check}
//                             (the board's daily check, control-board-plan.md step 5: each alarm's words, names and Fix it on every surface),
//                           ledger: {hours, endpoints: {ep: {calls, ok, client_errors, server_errors, received, sent, stored, updated,
//                             review, refused, ms_avg}}, workflows: {...the same, by the workflow its endpoint serves}} (worker calls, 24 h),
//                           irs_loads: {loads: [{file, started, finished, rows_seen, removed}], removed_total} (the nonprofits' loads)}
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Cache-Control: no-store');

Database::readEnv();   // nothing has loaded .env yet on this request (the same as TestAccounts::checkRequest)
$want = (string) (getenv('HEALTH_TOKEN') ?: '');
if ($want === '') Response::error('The health report is off (no HEALTH_TOKEN).', 503, 'off');
$got = '';
if (preg_match('/^Bearer\s+(\S+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m)) $got = $m[1];
if ($got === '') $got = (string) ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '');
if (!hash_equals($want, $got)) Response::error('Not allowed.', 401, 'not_allowed');

$pdo = Database::connection();
$out = ['ok' => true, 'at' => date('c'), 'php' => PHP_VERSION, 'php_major_minor' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION];
try { $out['mysql'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable) { $out['mysql'] = ''; }

// table sizes (the database's own estimates: no rows read)
try {
    $out['tables'] = array_map(fn ($r) => ['name' => (string) $r['TABLE_NAME'], 'rows' => (int) $r['TABLE_ROWS'], 'mb' => round(((int) $r['DATA_LENGTH'] + (int) $r['INDEX_LENGTH']) / 1048576, 2)],
        $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable) { $out['tables'] = []; }

// scheduled jobs: when each last ran (site time, as before, and UTC), how many failed today, and its cron line's schedule as
// cPanel holds it (2026-11-23: Lead scores silent 35 hours, its line unknown without someone looking by hand); 'line' is
// null when cPanel's API isn't set up, 'missing' when the job has no line. Plus the starts the database refused (each
// job's start that couldn't be recorded) and the database's connection counts, the likely cause of a gap on a busy minute.
try {
    require_once __DIR__ . '/../../app/lib/Scheduler.php';
    $runs = Scheduler::lastRuns($pdo);
    $lines = null; try { if (CPanel::ready()) $lines = Scheduler::cronLines(); } catch (Throwable) { $lines = null; }
    $out['jobs'] = [];
    // 'quiet': it logs only when it has work (Scheduler::QUIET) or is retired (Scheduler::RETIRED), so its silence isn't a fault
    foreach (Scheduler::JOBS as $k => $j) { $r = $runs[$k] ?? []; $last = $r['started_at'] ?? ($r['at'] ?? null);
        $l = $lines !== null ? Scheduler::lineFor($lines, $k) : null;
        $out['jobs'][] = ['job' => $k, 'last' => $last, 'last_utc' => $last ? gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $last)) : null, 'ok' => $r['ok'] ?? ($r['status'] ?? null), 'fails_today' => (int) ($r['fails_today'] ?? 0),
        'quiet' => in_array($k, array_merge(Scheduler::QUIET, Scheduler::RETIRED), true), 'default' => (string) $j[2],
        'line' => $lines === null ? null : ($l ? trim($l['minute'] . ' ' . $l['hour'] . ' ' . $l['day'] . ' ' . $l['month'] . ' ' . $l['weekday']) : 'missing'),
        'line_preset' => $l ? Scheduler::presetOf($l) : null]; }
    $miss = Scheduler::misses(); $day = gmdate('Y-m-d\TH:i:s\Z', time() - 86400);
    $out['jobs_missed'] = ['last_24h' => count(array_filter($miss, fn ($m) => (string) ($m['at'] ?? '') >= $day)), 'recent' => array_slice($miss, -30)];
    $v = []; foreach (['max_connections', 'max_user_connections'] as $n) { try { $v[$n] = (int) $pdo->query("SELECT @@" . $n)->fetchColumn(); } catch (Throwable) { $v[$n] = null; } }
    foreach (['Threads_connected', 'Max_used_connections', 'Aborted_connects', 'Connection_errors_max_connections'] as $n) { try { $r = $pdo->query("SHOW GLOBAL STATUS LIKE " . $pdo->quote($n))->fetch(PDO::FETCH_NUM); $v[$n] = $r ? (int) $r[1] : null; } catch (Throwable) { $v[$n] = null; } }
    $out['db_connections'] = $v;
} catch (Throwable) { $out['jobs'] = []; }

// AI spend this month (money only)
try {
    require_once __DIR__ . '/../../app/lib/AiClient.php';
    $out['ai'] = ['month_usd' => round(AiClient::monthUsd($pdo), 2), 'cap_usd' => AiClient::capUsd()];
    $st = $pdo->query("SELECT kind, COUNT(*) n, COALESCE(SUM(cost_micros), 0) c FROM " . Database::t('ai_jobs') . " WHERE created_at >= '" . date('Y-m-01') . "' GROUP BY kind");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out['ai']['by_feature'][$r['kind']] = ['jobs' => (int) $r['n'], 'usd' => round((int) $r['c'] / 1e6, 2)];
} catch (Throwable) { $out['ai'] = null; }

// the page cache
try { $c = $pdo->query('SELECT COUNT(*) n, SUM(expires_at < NOW()) old FROM page_cache')->fetch(PDO::FETCH_ASSOC); $out['cache'] = ['entries' => (int) $c['n'], 'expired' => (int) $c['old']]; } catch (Throwable) { $out['cache'] = null; }

// the admin's own status checks (crawler, search index, loads, database updates…): their state and name only
try {
    require_once __DIR__ . '/../../app/ui/admin-dashboard.php';
    $out['status'] = function_exists('tv_admin_status') ? array_map(fn ($r) => ['state' => (string) ($r[0] ?? ''), 'name' => (string) ($r[1] ?? '')], tv_admin_status($pdo)) : [];
} catch (Throwable) { $out['status'] = []; }

// switches and test accounts
try { require_once __DIR__ . '/../../app/lib/Switches.php'; $out['switches'] = Switches::ready($pdo) ? array_map(fn ($s) => ['state' => $s['state'], 'killed' => $s['killed']], Switches::all($pdo)) : null; } catch (Throwable) { $out['switches'] = null; }
try { require_once __DIR__ . '/../../app/lib/TestAccounts.php'; $out['test_accounts'] = TestAccounts::ready($pdo) ? (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_test = 1')->fetchColumn() : null; } catch (Throwable) { $out['test_accounts'] = null; }

// towns built on demand (decisions/0147, 0149): each one's crawl jobs, what they published and what waits in Review
try {
    $out['towns'] = [];
    $rows = $pdo->query("SELECT j.place_key, a.name, a.state, j.group_label, j.status, j.attempts, j.result, j.updated_at FROM town_crawl_jobs j LEFT JOIN anchors a ON CONCAT('town:', a.id) = j.place_key
                         ORDER BY j.updated_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $k = (string) $r['place_key']; $res = json_decode((string) $r['result'], true) ?: [];
        $out['towns'][$k] ??= ['town' => trim((string) $r['name'] . ', ' . (string) $r['state'], ', '), 'jobs' => []];
        $out['towns'][$k]['jobs'][] = ['kind' => (string) $r['group_label'], 'status' => (string) $r['status'], 'attempts' => (int) $r['attempts'], 'found' => (int) ($res['candidates'] ?? 0),
            'published' => (int) ($res['auto_imported'] ?? 0), 'in_review' => (int) ($res['staged'] ?? 0), 'known' => (int) ($res['already_known'] ?? 0), 'error' => mb_substr((string) ($res['error'] ?? ''), 0, 200), 'at' => (string) $r['updated_at']];
    }
    $out['towns'] = array_slice($out['towns'], 0, 20, true);
    // where each town's places are: listings by state, how many search can find, landmarks by status (2026-11-10: Dana
    // Point's 11 published restaurants didn't show in search)
    foreach ($out['towns'] as $k => &$t) {
        $a = $pdo->prepare('SELECT name, state, zips FROM anchors WHERE CONCAT(\'town:\', id) = ?'); $a->execute([$k]); $an = $a->fetch(PDO::FETCH_ASSOC);
        if (!$an) continue;
        $zips = array_values(array_filter(array_map('trim', explode(',', (string) $an['zips']))));
        $in = $zips ? implode(',', array_map(fn ($z) => $pdo->quote($z), $zips)) : "''";
        try { $t['listings'] = array_column($pdo->query("SELECT CONCAT(COALESCE(status, ''), IF(is_suppressed = 1, ' hidden', '')) s, COUNT(*) n FROM entities WHERE entity_type = 'merchant' AND TRIM(primary_zip) IN ($in) GROUP BY s")->fetchAll(PDO::FETCH_ASSOC), 'n', 's'); } catch (Throwable $e) { $t['listings'] = ['error' => mb_substr($e->getMessage(), 0, 120)]; }
        try { $t['searchable'] = array_column($pdo->query("SELECT kind, COUNT(*) n FROM search_index WHERE zip IN ($in) OR (city = " . $pdo->quote((string) $an['name']) . ' AND state = ' . $pdo->quote((string) $an['state']) . ') GROUP BY kind')->fetchAll(PDO::FETCH_ASSOC), 'n', 'kind'); } catch (Throwable $e) { $t['searchable'] = ['error' => mb_substr($e->getMessage(), 0, 120)]; }
        try { $t['landmarks'] = array_column($pdo->query('SELECT status, COUNT(*) n FROM landmarks WHERE (near_town = ' . $pdo->quote((string) $an['name']) . ' AND state = ' . $pdo->quote((string) $an['state']) . ") OR zip5 IN ($in) GROUP BY status")->fetchAll(PDO::FETCH_ASSOC), 'n', 'status'); } catch (Throwable $e) { $t['landmarks'] = ['error' => mb_substr($e->getMessage(), 0, 120)]; }
    }
    unset($t);
    try { $out['index_mark'] = (string) $pdo->query("SELECT v FROM search_index_state WHERE k = 'listing_mark'")->fetchColumn(); } catch (Throwable) {}   // how far search has caught up with listing changes
    $out['town_jobs'] = array_column($pdo->query('SELECT status, COUNT(*) n FROM town_crawl_jobs GROUP BY status')->fetchAll(PDO::FETCH_ASSOC), 'n', 'status');
    // landmarks' kinds with no category of ours yet (2026-11-10): give the category that word (a term) and they're filed
    try { require_once __DIR__ . '/../../app/lib/Landmarks.php'; $out['landmark_kinds_unfiled'] = Landmarks::categoriesMissing($pdo); } catch (Throwable) {}
    // OpenStreetMap's asks (2026-11-10): how many this hour, and the last ones with what each found (0: none, or no answer)
    try {
        $out['osm_asks_hour'] = (int) $pdo->query("SELECT COUNT(*) FROM open_place_asks WHERE asked_at >= '" . date('Y-m-d H:i:s', time() - 3600) . "'")->fetchColumn();
        $out['osm_asks_last'] = $pdo->query('SELECT qkey, found, asked_at FROM open_place_asks ORDER BY asked_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
} catch (Throwable) { $out['towns'] = null; }

// the crawler revamp's snapshot (handoffs/crawler-revamp.md Phase 0, 2026-11-13): counts for each store the old crawler
// made, so the plan has numbers. Read-only: nothing here changes a row. Each count stands alone; one that fails says why.
$out['crawler'] = ['at' => date('c')];
$cr = &$out['crawler'];
$pairs = function (string $sql) use ($pdo): array { return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR)); };
$one = function (string $sql) use ($pdo): int { return (int) $pdo->query($sql)->fetchColumn(); };
$try = function (string $k, callable $f) use (&$cr): void { try { $cr[$k] = $f(); } catch (Throwable $e) { $cr[$k] = ['error' => mb_substr($e->getMessage(), 0, 160)]; } };
// staged finds by state; of the staged, how many are ready (and so stuck: ready finds publish at once), in Review, or still being looked at
$try('finds', fn () => $pairs("SELECT status, COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE payload_type = 'listing' GROUP BY status"));
$try('staged', function () use ($pdo): array {
    require_once __DIR__ . '/../../app/lib/crawler/AutoImport.php';
    $o = ['checked' => 0, 'ready_stuck' => 0, 'review' => 0, 'looking' => 0, 'no_kind' => 0, 'one_source' => 0, 'by_reason' => [], 'auto_import_on' => AutoImport::enabled()];
    $st = $pdo->query("SELECT raw_payload, created_at FROM " . Database::t('crawler_raw_index') . " WHERE status = 'staged' AND payload_type = 'listing' ORDER BY id DESC LIMIT 3000");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $c = json_decode((string) $r['raw_payload'], true); if (!is_array($c)) continue;
        $o['checked']++;
        $e = AutoImport::evaluate($c);
        $fail = array_column(array_filter($e['checks'], fn ($k) => !$k['pass']), 'code');
        if (in_array('appropriate', $fail, true) && $e['category_id'] === null) $o['no_kind']++;
        if (in_array('real', $fail, true) && count(AutoImport::sourceHosts($c)) < 2) $o['one_source']++;
        if ($e['eligible'] && empty($c['hold'])) { $o['ready_stuck']++; continue; }
        if (AutoImport::exhausted($c, (string) $r['created_at'])) { $o['review']++; $k = AutoImport::reason($c)['key']; $o['by_reason'][$k] = ($o['by_reason'][$k] ?? 0) + 1; }
        else $o['looking']++;
    }
    $o['of'] = (int) $pdo->query("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'staged' AND payload_type = 'listing'")->fetchColumn();   // checked: the newest 3,000
    return $o;
});
// the place store, and sources read but holding no places (most likely wiped by a starved read, review B6)
$try('place_store', function () use ($pdo, $one): array {
    $f = Database::t('source_facts'); $s = Database::t('reference_sources');
    return ['places' => $one("SELECT COUNT(*) FROM $f"), 'hosts' => $one("SELECT COUNT(DISTINCT host) FROM $f"),
            'sources_read' => $one("SELECT COUNT(*) FROM $s WHERE status = 'read'"),
            'sources_read_empty' => $one("SELECT COUNT(*) FROM $s r WHERE r.status = 'read' AND r.last_read_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM $f x WHERE x.host = TRIM(LEADING 'www.' FROM LOWER(r.host)))")];
});
// websites taken off listings as dead (kept as website_dead), by month; refresh leases abandoned (review B5)
$try('websites_taken_off', fn () => $pairs("SELECT DATE_FORMAT(updated_at, '%Y-%m') m, COUNT(*) FROM entity_metadata WHERE meta_key = 'website_dead' GROUP BY m ORDER BY m"));
$try('refresh', fn () => ['abandoned_listings' => $one("SELECT COUNT(*) FROM entity_metadata WHERE meta_key = 'crawler_refresh_abandoned'"),
                          'failed_once' => $one("SELECT COUNT(*) FROM entity_metadata WHERE meta_key = 'site_check'")]);
// public listings without a map point, or only their ZIP's middle (invisible or misplaced in every "near" search)
$try('map_points', function () use ($one): array {
    require_once __DIR__ . '/../../app/lib/PlaceScope.php'; require_once __DIR__ . '/../../app/lib/Geocoder.php';
    $pub = implode(',', array_map(fn ($x) => "'" . str_replace("'", '', $x) . "'", PlaceScope::PUBLIC_STATUSES));
    $w = "e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.status IN ($pub)";
    return ['public' => $one("SELECT COUNT(*) FROM entities e WHERE $w"), 'no_point' => $one("SELECT COUNT(*) FROM entities e WHERE $w AND (e.latitude IS NULL OR e.longitude IS NULL)"),
            'approximate' => $one("SELECT COUNT(*) FROM entities e WHERE $w AND " . Geocoder::APPROX)];
});
// town jobs and coverage jobs by status, with their tries spent
$try('town_jobs', fn () => $pairs("SELECT CONCAT(status, IF(attempts >= 3, ' (3+ tries)', '')) s, COUNT(*) FROM town_crawl_jobs GROUP BY s"));
$try('coverage_jobs', fn () => $pairs("SELECT CONCAT(status, IF(attempts >= 3, ' (3+ tries)', '')) s, COUNT(*) FROM " . Database::t('crawl_jobs') . " GROUP BY s"));
$try('identity_jobs', fn () => $pairs("SELECT CONCAT(status, IF(attempts >= 3, ' (set aside)', '')) s, COUNT(*) FROM " . Database::t('identity_jobs') . " GROUP BY s"));
// the source register: by status, and sites waiting for good after a describe that found nothing (review §2)
$try('register', function () use ($pairs, $one): array {
    $s = Database::t('reference_sources');
    return ['by_status' => $pairs("SELECT status, COUNT(*) FROM $s GROUP BY status"),
            'waiting_after_describe' => $one("SELECT COUNT(*) FROM $s WHERE status = 'suggested' AND judged_by IS NULL AND about_at IS NOT NULL")];
});
// OpenStreetMap values in listings, as each listing's identity records them: by fact, and where it's the only source (review C11)
$try('osm_in_listings', function () use ($pairs): array {
    $F = Database::t('identity_facts'); $S = Database::t('identity_fact_sources');
    return ['by_fact' => $pairs("SELECT f.fact, COUNT(DISTINCT f.record_id) FROM $F f JOIN $S s ON s.fact_id = f.id WHERE f.record_kind = 'listing' AND s.site LIKE '%openstreetmap.org' GROUP BY f.fact"),
            'only_source' => $pairs("SELECT f.fact, COUNT(DISTINCT f.record_id) FROM $F f JOIN $S s ON s.fact_id = f.id WHERE f.record_kind = 'listing' AND s.site LIKE '%openstreetmap.org'
                                      AND NOT EXISTS (SELECT 1 FROM $S o WHERE o.fact_id = f.id AND o.site NOT LIKE '%openstreetmap.org') GROUP BY f.fact")];
});
// heritage facts by tier and status; landmarks by status; guides whose last draft failed
$try('heritage', fn () => $pairs("SELECT CONCAT(tier, ' / ', status) k, COUNT(*) FROM heritage_facts GROUP BY k"));
$try('landmarks', fn () => $pairs("SELECT status, COUNT(*) FROM landmarks GROUP BY status"));
$try('guides', fn () => $pairs("SELECT CONCAT(status, ' / ', IF(draft_status = '', 'none', draft_status)) k, COUNT(*) FROM place_guides GROUP BY k"));
unset($cr);

// ?find=name: the landmarks, outdoor places and listings named like it, with their status (what search can or can't see)
$find = trim((string) ($_GET['find'] ?? ''));
if ($find !== '' && mb_strlen($find) <= 80) {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $find) . '%';
    try { $st = $pdo->prepare('SELECT gnis_id, name, status, why, nation, near_town, state, file FROM landmarks WHERE name LIKE ? ORDER BY name LIMIT 20'); $st->execute([$like]); $out['find']['landmarks'] = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) {}
    try { $st = $pdo->prepare('SELECT kind, item_id, label, city, state FROM search_index WHERE label LIKE ? ORDER BY label LIMIT 20'); $st->execute([$like]); $out['find']['searchable'] = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) {}
    try { $st = $pdo->prepare("SELECT id, name, primary_zip AS city, '' AS state, is_suppressed FROM entities WHERE entity_type = 'merchant' AND name LIKE ? ORDER BY name LIMIT 20"); $st->execute([$like]); $out['find']['listings'] = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $out['find']['listings_error'] = mb_substr($e->getMessage(), 0, 120); }
}

// the Control Board's open trips and warnings (decisions/0171 §7, release 3c): read by the session manager's hourly check,
// which pushes each new trip to Jason's phone. Words, levels and times only: nothing about a person.
try {
    require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
    $out['alarms'] = array_values(array_map(fn ($a) => ['id' => (int) $a['id'], 'key' => (string) $a['akey'], 'level' => (string) $a['level'], 'process' => (string) $a['proc'], 'title' => (string) $a['title'], 'reading' => (string) $a['reading'],
            'means' => (string) $a['means'], 'since' => gmdate('c', (int) strtotime($a['first_at'] . ' UTC')), 'tripped' => !empty($a['tripped_at']) ? gmdate('c', (int) strtotime($a['tripped_at'] . ' UTC')) : null,
            'acknowledged' => $a['ack_at'] !== null, 'stopped' => ControlAlarms::stoppedWords($a), 'url' => 'https://traversence.com/admin/config/control#cb-alarm-' . (int) $a['id']],
        array_filter(ControlAlarms::list($pdo, 200), fn ($a) => $a['cleared_at'] === null && in_array($a['level'], ['trip', 'warning'], true))));
} catch (Throwable) { $out['alarms'] = null; }
// the tables and columns no live code names (SchemaUpkeep's monthly check; release 4b, the session manager's way to read
// Pulse #31's list): names, databases and sizes only, never a row of data; removing one is a release Jason approves
try {
    require_once __DIR__ . '/../../app/lib/SchemaUpkeep.php';
    $u = SchemaUpkeep::unused(); $kept = SchemaUpkeep::keptUnused();
    $out['unused_schema'] = ['checked' => (string) $u['at'], 'files_read' => (int) $u['files'],
        'tables' => array_values(array_map(fn ($x) => ['db' => (string) $x['db'], 'table' => (string) $x['table'], 'rows' => (int) $x['rows'], 'rows_exact' => SchemaUpkeep::filled($pdo, (string) $x['db'], (string) $x['table']), 'mb' => round(((int) $x['bytes']) / 1048576, 2), 'kept' => isset($kept[$x['key']])], $u['tables'])),
        // how many rows hold a value in each listed column: a count only, never a value (4d)
        'columns' => array_values(array_map(fn ($x) => ['db' => (string) $x['db'], 'table' => (string) $x['table'], 'column' => (string) $x['column'], 'filled' => SchemaUpkeep::filled($pdo, (string) $x['db'], (string) $x['table'], (string) $x['column']), 'kept' => isset($kept[$x['key']])], $u['columns'])),
        'url' => 'https://traversence.com/admin/config/dbupdates#unused'];
} catch (Throwable) { $out['unused_schema'] = null; }
// the System Control Board's report in short (decisions/0171 §11, release 5b): the whole system over the last 24 hours
try { require_once __DIR__ . '/../../app/lib/ControlReport.php'; $out['alarms_summary'] = ControlReport::summary($pdo, 'day'); }
catch (Throwable) { $out['alarms_summary'] = null; }
// when the site last looked in git for builds, by whom (the 10-minute job, the ping, Check for builds), so a quiet job shows
try { require_once __DIR__ . '/../../app/lib/Releases.php'; $out['releases_looked'] = array_map(fn ($l) => ['who' => $l[0], 'at' => $l[1] ? gmdate('c', $l[1]) : null, 'found' => $l[2]], Releases::lastLooked($pdo)); }
catch (Throwable) { $out['releases_looked'] = null; }
// what's out for maintenance on purpose (0171 §2.5, release 4a): blue on the board, never a failure, so the hourly check
// doesn't report it as one
try {
    require_once __DIR__ . '/../../app/lib/ControlMaintenance.php';
    $out['maintenance'] = array_values(array_map(fn ($m) => ['process' => (string) $m['proc'], 'why' => (string) $m['why'], 'back_by' => gmdate('c', (int) strtotime($m['until_at'] . ' UTC')),
        'url' => 'https://traversence.com/admin/config/control#maintenance'], ControlMaintenance::out($pdo)));
} catch (Throwable) { $out['maintenance'] = null; }

// the board's daily consistency check (control-board-plan.md step 5): every alarm drawn on the board's row, its Pulse item
// and its module card, each saying what the one engine says (app/lib/ControlConsistency.php, the same code as the bench's
// scenario test). Alarm ids, keys and words only. A mismatch is a case for the building session and the session manager,
// never Pulse or email: GitHub's daily run writes it to site-audit/results/consistency.json.
try { require_once __DIR__ . '/../../app/lib/ControlConsistency.php'; $out['consistency'] = ControlConsistency::check($pdo); }
catch (Throwable $e) { $out['consistency'] = ['error' => mb_substr($e->getMessage(), 0, 160)]; }
// the ledger over 24 hours (D6's read, live-reads C3 2: "the ledger records 0 calls"): each worker endpoint's calls and what
// they handed in, stored and refused, and the same by the workflow its endpoint serves. Counts only.
try {
    require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
    $rows = $pdo->query("SELECT endpoint, COUNT(*) calls, SUM(code < 400) ok, SUM(code >= 400 AND code < 500) c4, SUM(code >= 500) c5, SUM(n_received) rcv, SUM(n_sent) snt,
                                SUM(n_stored) sto, SUM(n_updated) upd, SUM(n_review) rev, SUM(n_refused) ref, ROUND(AVG(ms)) ms
                         FROM control_ledger WHERE at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR GROUP BY endpoint ORDER BY endpoint")->fetchAll(PDO::FETCH_ASSOC);
    $sum = fn (array $r) => ['calls' => (int) $r['calls'], 'ok' => (int) $r['ok'], 'client_errors' => (int) $r['c4'], 'server_errors' => (int) $r['c5'], 'received' => (int) $r['rcv'], 'sent' => (int) $r['snt'],
        'stored' => (int) $r['sto'], 'updated' => (int) $r['upd'], 'review' => (int) $r['rev'], 'refused' => (int) $r['ref'], 'ms_avg' => (int) $r['ms']];
    $led = ['hours' => 24, 'endpoints' => [], 'workflows' => []];
    foreach ($rows as $r) {
        $e = $sum($r); $led['endpoints'][(string) $r['endpoint']] = $e;
        $wf = ''; try { $wf = (string) ControlAlarms::procOfEndpoint($pdo, (string) $r['endpoint']); } catch (Throwable) {}
        $wf = $wf !== '' ? $wf : 'none';
        foreach ($e as $k => $v) if ($k !== 'ms_avg') $led['workflows'][$wf][$k] = ($led['workflows'][$wf][$k] ?? 0) + $v;
    }
    $out['ledger'] = $led;
} catch (Throwable $e) { $out['ledger'] = ['error' => mb_substr($e->getMessage(), 0, 160)]; }
// the nonprofits' loads (live-reads C15; D11's floor): each load's file, its times, the rows it read and how many it took off
try {
    $t = Database::t('irs_loads');
    $ls = $pdo->query("SELECT file, started_at, finished_at, rows_seen, removed FROM $t ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $out['irs_loads'] = ['loads' => array_map(fn ($l) => ['file' => (string) $l['file'], 'started' => (string) $l['started_at'], 'finished' => $l['finished_at'] !== null ? (string) $l['finished_at'] : null,
        'rows_seen' => $l['rows_seen'] !== null ? (int) $l['rows_seen'] : null, 'removed' => $l['removed'] !== null ? (int) $l['removed'] : null], $ls),
        'removed_total' => (int) $pdo->query("SELECT COALESCE(SUM(removed), 0) FROM $t")->fetchColumn()];
} catch (Throwable $e) { $out['irs_loads'] = ['error' => mb_substr($e->getMessage(), 0, 160)]; }

Response::json($out);
