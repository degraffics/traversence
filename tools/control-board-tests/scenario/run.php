<?php
// The scenario test (handoffs/control-board-build.md §5; step3/02-state-model.md §4.9): every cell of step 2's failure map
// (173, step2/README.md "The totals") mapped to 02 §4.9's 135 designed scenarios (cells.md), each stood up on the bench's
// lv_main, its failure caused through the engine's real callers (ControlAlarms, ServiceHealth, CrawlService::report,
// ControlLedger, the watch), then 02 §4.9's five things asserted: (1) state, (2) hold (exactly Held / On a backup /
// Unaffected, no switch value changed: CS-1), (3) guard (ControlEngine::$checker called with the row's component), (4) words
// at the engine, Thread and Notify level and on the views (views(): the board's row, Pulse, the module card), (5) messages
// (Notify in test mode: Mailer and WebPush stood in, nothing sent). Then recovery, and Recovered.
// Run: mariadb < ../../release-b-tests/start-schema.sql && php run.php <root>/app   (the ledger: control_ledger, made here if missing)
//   run.php <root>/app [suite ...]   suites: lift faults clock actions escalation correlation portal words named
// Output: one line per scenario ("  ok  S-B23 …" / "FAIL  S-B23 … — expected … got …"), a summary by suite, the cells covered.
// Outside calls: none. Every check goes through ControlEngine::$checker (02 §4.9: "a stand-in host records it"); the
// process's proxy is pointed at a closed port, so a call that escaped the stand-in fails instead of reaching a live host.
declare(strict_types=1);
ini_set('log_errors', '1'); ini_set('display_errors', 'stderr'); ini_set('error_log', sys_get_temp_dir() . '/scenario-errors-' . getmypid() . '.log');   // the engine's own error_log lines, kept off the output
foreach (['http_proxy', 'https_proxy', 'HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'all_proxy'] as $pv) putenv($pv . '=http://127.0.0.1:9');
putenv('no_proxy='); putenv('NO_PROXY=');
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php run.php <root>/app [suite ...]\n"); exit(2); }
$only = array_slice($argv, 2);
// a copy of app/ beside its root (its .env found the same four folders up), with email and push stood in (dispatcher-tests/r1)
$app = dirname($src, 2) . '/sctest-' . getmypid() . '/app';
@mkdir(dirname($app), 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($app));
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg(dirname($app))));
file_put_contents($app . '/lib/Mailer.php', "<?php\nfinal class Mailer { public static array \$sent = []; public static bool \$routed = false; public static ?array \$thread = null;\n public static function send(\$to, \$subj, \$text, \$name = null, \$as = ''): void { self::\$sent[] = [\$to, \$subj, \$text, self::\$thread]; } }\n");
file_put_contents($app . '/lib/WebPush.php', "<?php\nfinal class WebPush { public static array \$sent = [];\n public static function sendToUser(PDO \$p, int \$u, array \$m): array { self::\$sent[] = ['u' => \$u] + \$m; return ['sent' => 1, 'failed' => 0, 'held' => 0]; } }\n");
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
foreach (['Database', 'ControlAlarms', 'ControlEngine', 'ControlIncidents', 'Automation', 'ControlRegister', 'ControlLog', 'Thread', 'Notify', 'Mailer', 'WebPush',
          'ServiceHealth', 'Preflight', 'Pulse', 'SearchIndex', 'ControlLedger', 'crawler/CrawlService', 'Thresholds'] as $l) require_once $app . '/lib/' . $l . '.php';
$GLOBALS['APP'] = $app;
$pdo = Database::connection(); $GLOBALS['pdo'] = $pdo;

// ---- the test schema: every board table (02 §4.9's last line; step 1 §7) ---------------------------------------------
$q = fn (string $s) => $pdo->exec($s);
foreach (['control_alarms', 'control_log', 'system_pauses', 'control_incidents', 'comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet', 'comms_controls', 'push_subs', 'staff_accounts', 'pulse_marks', 'pulse_notes', 'service_issues'] as $x) $q("DROP TABLE IF EXISTS $x");
ControlAlarms::ensure($pdo); ControlLog::ensure($pdo); ControlIncidents::ensure($pdo); Automation::ensurePauses($pdo);
$q("CREATE TABLE comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL, UNIQUE KEY comms_once (to_user, kind, thing), KEY comms_thread (to_user, kind, thread))");
$q("CREATE TABLE comms_route (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, via VARCHAR(12) NOT NULL, stage VARCHAR(12) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', provider_id VARCHAR(120) NOT NULL DEFAULT '', happened_at DATETIME NOT NULL, sender_key VARCHAR(30) NULL, addr_hash CHAR(64) NULL, subject VARCHAR(200) NULL, mail_kind VARCHAR(30) NULL)");
$q("CREATE TABLE comms_prefs (user_id INT UNSIGNED NOT NULL, kind VARCHAR(48) NOT NULL, via VARCHAR(12) NOT NULL, is_on TINYINT(1) NOT NULL DEFAULT 1, changed_at DATETIME NOT NULL, PRIMARY KEY (user_id, kind, via))");
$q("CREATE TABLE comms_quiet (user_id INT UNSIGNED NOT NULL PRIMARY KEY, quiet_from SMALLINT UNSIGNED NOT NULL, quiet_to SMALLINT UNSIGNED NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', changed_at DATETIME NOT NULL)");
$q("CREATE TABLE push_subs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, endpoint VARCHAR(500) NOT NULL DEFAULT '', failed_at DATETIME NULL)");
$q("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
$q("CREATE TABLE pulse_marks (item_key VARCHAR(90) PRIMARY KEY, case_no INT AUTO_INCREMENT UNIQUE, assigned_to INT NULL, done_at DATETIME NULL, done_by INT NULL, esc_level TINYINT DEFAULT 0, esc_at DATETIME NULL, data TEXT NULL)");
$q("CREATE TABLE pulse_notes (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(90), user_id INT, kind VARCHAR(20), body TEXT, to_email VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
// the ledger (its migration ran on live and was deleted: made from ControlLedger::finish's columns, as run-all.sh does)
$q("CREATE TABLE IF NOT EXISTS control_ledger (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, endpoint VARCHAR(80) NOT NULL, method VARCHAR(6), code INT, ms INT, run_id INT NULL, n_sent INT DEFAULT 0, n_received INT DEFAULT 0, n_stored INT DEFAULT 0, n_updated INT DEFAULT 0, n_review INT DEFAULT 0, n_refused INT DEFAULT 0, reasons VARCHAR(500) NULL, records INT DEFAULT 0, KEY (endpoint, id), KEY (run_id), KEY (at))");
$q("CREATE TABLE IF NOT EXISTS crawl_run_items (id INT AUTO_INCREMENT PRIMARY KEY, run_id INT, wf VARCHAR(4), task VARCHAR(40), outcome VARCHAR(20), allotted_s INT, seconds INT, fetches INT, searches INT, counts TEXT, why TEXT, at DATETIME)");
$q("CREATE TABLE IF NOT EXISTS crawl_service_calls (id INT AUTO_INCREMENT PRIMARY KEY, run_id INT, at DATETIME, task VARCHAR(40), host VARCHAR(190), service VARCHAR(40), calls INT, fails INT, last_error VARCHAR(255))");
$q("CREATE TABLE IF NOT EXISTS job_runs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, job VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, ok TINYINT(1) DEFAULT NULL, note VARCHAR(255) NOT NULL DEFAULT '', KEY idx_job_runs (job, id))");

// service_issues: from ServiceHealth's own columns (its migration was deleted after it ran)
$q("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(10), kind VARCHAR(12), state VARCHAR(10), hits INT DEFAULT 1, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(20) NULL, next_probe_at DATETIME NULL, last_probe TEXT NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL, KEY (host, caller, state))");
foreach (['last_served_at DATETIME NULL', 'last_outcome VARCHAR(20) NULL', 'last_why VARCHAR(255) NULL'] as $col) { try { $pdo->exec('ALTER TABLE crawl_capture_tasks ADD COLUMN ' . $col); } catch (Throwable $x) {} }
$pdo->exec("DELETE FROM users WHERE id = 7"); try { $pdo->exec("INSERT INTO users (id, display_name) VALUES (7, 'Jason')"); } catch (Throwable $x) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL, ADD COLUMN is_suspended TINYINT DEFAULT 0"); } catch (Throwable $x) {}
$pdo->exec("UPDATE users SET email = 'founder@example.test' WHERE id = 7");

// the crawler's tasks by workflow (ControlRegister::ENDPOINTS, OutsideServices::CONTROLS): every one a scenario names
const TASKS = ['overture_bulk' => 'W1', 'foursquare_bulk' => 'W1', 'geofabrik_bulk' => 'W1', 'coverage' => 'W1', 'targets' => 'W1', 'npi_bulk' => 'W1', 'irs_bulk' => 'W1',
    'rec_bulk' => 'W1', 'landmarks_bulk' => 'W9', 'second_look' => 'W2', 'refresh' => 'W2', 'identity' => 'W2', 'geocode' => 'W2', 'places' => 'W2', 'recheck' => 'W2',
    'source' => 'W3', 'commoncrawl' => 'W3', 'heritage' => 'W6', 'howto' => 'W11', 'learn' => 'W12', 'licence_lookup' => 'W14'];

// ---- the harness ------------------------------------------------------------------------------------------------------
$GLOBALS['checks'] = []; $GLOBALS['called'] = [];
ControlEngine::$checker = function (string $c) {
    $GLOBALS['called'][] = $c;
    if (array_key_exists($c, $GLOBALS['checks'])) return $GLOBALS['checks'][$c];
    // a task's, job's or workflow's own code has no check of its own (its next run is: ControlEngine::checkable); any other
    // part with no answer set fails, never a real outside call
    // (a stuck job's run, job:<job>:run, has a check of its own: 02 row 66)
    if (preg_match('/^(task|job|wf):[^:]+$/', $c)) return ['pass' => null, 'proof' => '', 'ms' => 0];
    return ['pass' => false, 'proof' => 'stand-in: ' . $c . ' gave no answer', 'ms' => 0];
};
function pass(string $proof = 'answered 200 in 180 ms.'): array { return ['pass' => true, 'proof' => $proof, 'ms' => 180]; }
function red(string $proof = 'answered 504 Gateway Time-out.'): array { return ['pass' => false, 'proof' => $proof, 'ms' => 30000]; }
function checks(array $c): void { foreach ($c as $k => $v) $GLOBALS['checks'][$k] = $v; }
function called(): array { return $GLOBALS['called']; }

function fresh(): void
{
    $pdo = $GLOBALS['pdo'];
    foreach (['control_alarms', 'control_log', 'system_pauses', 'control_incidents', 'comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet', 'push_subs', 'staff_accounts',
              'pulse_marks', 'pulse_notes', 'service_issues', 'control_ledger', 'control_maintenance', 'crawl_runs', 'crawl_run_items', 'crawl_service_calls', 'job_runs', 'automation_log', 'automation_switches', 'crawl_capture_tasks'] as $t) {
        try { $pdo->exec("DELETE FROM $t"); } catch (Throwable $x) {}
    }
    try { $pdo->exec("DELETE FROM search_index_state WHERE k LIKE 'control:%' OR k LIKE 'sh:%'"); } catch (Throwable $x) {}
    try { $pdo->exec("DELETE FROM engine_cases"); } catch (Throwable $x) {}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS comms_controls (ckey VARCHAR(80) PRIMARY KEY, paused TINYINT DEFAULT 0, why VARCHAR(255), by_uid INT, at DATETIME)"); } catch (Throwable $x) {}
    try { $pdo->exec("DELETE FROM comms_controls"); } catch (Throwable $x) {}
    // the master on (a person's switch: Jason's) and every workflow on, so a hold, not an Off, is what's read
    $pdo->exec("INSERT INTO automation_switches VALUES ('master','all',0,'On for the scenario',7,'Jason',UTC_TIMESTAMP())");
    $pdo->exec("UPDATE crawl_workflows SET paused = 0, paused_why = NULL");
    $ins = $pdo->prepare('INSERT INTO crawl_capture_tasks (task, wf, enabled) VALUES (?, ?, 1)');
    foreach (TASKS as $t => $w) $ins->execute([$t, $w]);
    $pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'founder@example.test', 'Jason', 'active', 'founder', '', '')");
    $pdo->exec("INSERT INTO push_subs (user_id, endpoint) VALUES (7, 'https://push.example.test/x')");
    try { SearchIndex::setState($pdo, 'control:duties', '[]'); } catch (Throwable $x) {}
    try { $pdo->exec("DELETE FROM preflight_results"); } catch (Throwable $x) {}
    // per-request caches the libraries keep (each test request is one scenario here)
    foreach ([['ControlMaintenance', 'out', null], ['Preflight', 'cache', null], ['Preflight', 'fresh', []]] as [$cl, $pr, $v]) {
        if (!class_exists($cl, false)) continue; try { $rp = new ReflectionProperty($cl, $pr); $rp->setAccessible(true); $rp->setValue(null, $v); } catch (Throwable $x) {}
    }
    Automation::forget(); ControlRegister::forget();
    Mailer::$sent = []; WebPush::$sent = []; $GLOBALS['checks'] = []; $GLOBALS['called'] = [];
}

/** The switches as a person set them (CS-1: a fault never writes one): every switch row and task/workflow flag. */
function snap(): string
{
    $pdo = $GLOBALS['pdo'];
    return md5(json_encode([$pdo->query("SELECT kind, ref, paused, by_name FROM automation_switches ORDER BY kind, ref")->fetchAll(PDO::FETCH_NUM),
        $pdo->query("SELECT task, enabled FROM crawl_capture_tasks ORDER BY task")->fetchAll(PDO::FETCH_NUM),
        $pdo->query("SELECT wf, paused FROM crawl_workflows ORDER BY wf")->fetchAll(PDO::FETCH_NUM)]));
}
/** Open system holds, as kind:ref. */
function holds(): array { Automation::forget(); $o = array_keys(Automation::pauses($GLOBALS['pdo'])); sort($o); return $o; }
/** Open service-issue holds (a host's calls held by a trip), as host. */
function hostHolds(): array { return $GLOBALS['pdo']->query("SELECT host FROM service_issues WHERE state = 'open' AND hold_until >= '2099-01-01'")->fetchAll(PDO::FETCH_COLUMN); }
function alarm(string $k): ?array { return ControlAlarms::openOf($GLOBALS['pdo'], $k); }
function last(string $k): ?array { $st = $GLOBALS['pdo']->prepare('SELECT * FROM control_alarms WHERE akey = ? ORDER BY id DESC LIMIT 1'); $st->execute([$k]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function byId(int $id): ?array { return ControlAlarms::byId($GLOBALS['pdo'], $id); }
function facts(?array $a): array { return $a ? ControlAlarms::facts($GLOBALS['pdo'], $a) : ['status' => '', 'doing' => '', 'title' => '', 'affects' => []]; }
function push(?array $a): array { return $a ? Thread::push(facts($a)) : ['title' => '', 'body' => '']; }
function title(?array $a, string $state = ''): string { return $a ? ControlAlarms::noticeTitle($GLOBALS['pdo'], $a, $state) : ''; }
function msgs(): array { return $GLOBALS['pdo']->query("SELECT * FROM comms_messages ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); }
function mails(): array { return Mailer::$sent; }
function pushes(): array { return WebPush::$sent; }
function quiet(): bool { return !msgs() && !Mailer::$sent && !WebPush::$sent; }
/** Moves an open alarm's clock back $mins and makes its next try due now (the diagnostic clock, controllable: 02 §4.9 clock). */
function due(string $k, int $mins = 0): void { $GLOBALS['pdo']->prepare('UPDATE control_alarms SET first_at = first_at - INTERVAL ? MINUTE, next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE akey = ? AND cleared_at IS NULL')->execute([$mins, $k]); }
function tick(): array { Automation::forget(); $r = ControlAlarms::expireTries($GLOBALS['pdo']); Automation::forget(); return $r; }
/** Three failed tries (5, 15, 35 min): the trip by the system's own checks. */
function triesOut(string $k): void { foreach ([5, 10, 20] as $m) { due($k, $m); tick(); } }
function resolveA(?array $a, string $who = 'Jason'): array { return $a ? ControlAlarms::resolve($GLOBALS['pdo'], (int) $a['id'], 7, $who, '') : [false, 'no alarm']; }
function pulseLevel(?array $a): int { if (!$a) return -1; $st = $GLOBALS['pdo']->prepare('SELECT esc_level FROM pulse_marks WHERE item_key = ?'); $st->execute(['alarm:' . (int) $a['id']]); return (int) $st->fetchColumn(); }

// the callers, as they raise in the live code (02 §5.2: every caller raises only through the engine)
/** ControlLedger's own raise for one endpoint call (its private alarms(), the real caller). */
function ledger(string $ep, int $code, int $ms = 200, int $received = 0, array $c = []): void
{
    $pdo = $GLOBALS['pdo'];
    $pdo->prepare('INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received, n_stored, n_updated, n_review, n_refused) VALUES (UTC_TIMESTAMP(), ?, "POST", ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$ep, $code, $ms, $received, (int) ($c['stored'] ?? 0), (int) ($c['updated'] ?? 0), (int) ($c['review'] ?? 0), (int) ($c['refused'] ?? 0)]);
    $m = new ReflectionMethod('ControlLedger', 'alarms'); $m->setAccessible(true);
    $m->invoke(null, $pdo, $ep, $code, $received, ['stored' => (int) ($c['stored'] ?? 0), 'updated' => (int) ($c['updated'] ?? 0), 'review' => (int) ($c['review'] ?? 0), 'refused' => (int) ($c['refused'] ?? 0)], [], $ms);
    Automation::forget();
}
/** A crawler run and its report (CrawlService::report, the real caller): items [task => [outcome, why, calls]], extra fields. */
function report(array $items, array $extra = []): array
{
    $pdo = $GLOBALS['pdo'];
    $key = 'sc' . substr(md5(uniqid('', true)), 0, 12);
    $pdo->prepare("INSERT INTO crawl_runs (run_key, started_at, status) VALUES (?, NOW(), 'running')")->execute([$key]);
    $its = []; foreach ($items as $t => $o) $its[] = ['task' => $t, 'outcome' => $o[0], 'why' => $o[1] ?? '', 'calls' => $o[2] ?? []];
    try { $r = CrawlService::report($pdo, ['run_key' => $key, 'items' => $its] + $extra); } catch (Throwable $x) { $r = ['error' => $x->getMessage()]; }
    Automation::forget(); ControlRegister::forget();
    return $r;
}
/** An outside service's failure (ServiceHealth::failed, the real caller). */
function svcFail(string $host, string $kind, string $caller = 'site', array $ev = [], int $n = 1): int { $id = ServiceHealth::failed($GLOBALS['pdo'], $host, $kind, $caller, $ev + ['status' => 0, 'body' => '', 'error' => ''], $n); Automation::forget(); return $id; }
function issueAlarm(int $id): ?array { return alarm('svc:issue:' . $id); }
function issue(int $id): ?array { $st = $GLOBALS['pdo']->prepare('SELECT * FROM service_issues WHERE id = ?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
/** A site job's run (Scheduler's job_runs), then the watch's job section. */
function jobRun(string $job, ?int $ok, string $note = '', int $agoMin = 1, ?int $tookS = 5): void
{
    $GLOBALS['pdo']->prepare('INSERT INTO job_runs (job, started_at, finished_at, ok, note) VALUES (?, NOW() - INTERVAL ' . (int) $agoMin . ' MINUTE, '
        . ($tookS === null ? 'NULL' : 'NOW() - INTERVAL ' . (int) $agoMin . ' MINUTE + INTERVAL ' . (int) $tookS . ' SECOND') . ', ?, ?)')->execute([$job, $ok, $note]);
}
function watch(): string { Automation::forget(); $r = ControlAlarms::tick($GLOBALS['pdo']); Automation::forget(); return $r; }
/** A raise as a named caller does it (for callers whose raise is inside a larger routine: the sensor, the start watch). */
function raiseAs(string $key, string $proc, string $level, string $kind, string $title, string $reading = '', bool $event = true, bool $tryFirst = true): int
{ $id = ControlAlarms::raise($GLOBALS['pdo'], $key, $proc, $level, $kind, $title, '', '', $reading, [], $event, $tryFirst); Automation::forget(); return $id; }

// ---- assertions, scenarios, output -------------------------------------------------------------------------------------
$GLOBALS['cur'] = null; $GLOBALS['results'] = []; $GLOBALS['cellsOf'] = [];
/** One of the scenario's assertions; the scenario fails on its first false one, naming what was expected and what came. */
function chk(string $expected, bool $ok, $got = null): bool
{
    if (!$ok && $GLOBALS['cur'] !== null) { if ($GLOBALS['cur']['fail'] === null) $GLOBALS['cur']['fail'] = [$expected, $got]; else $GLOBALS['cur']['more'][] = [$expected, $got]; }
    if ($GLOBALS['cur'] !== null) $GLOBALS['cur']['n']++;
    return $ok;
}
function j($v): string { return mb_substr(is_string($v) ? $v : (string) json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 260); }
/** A scenario: its id (02 §4.9), what it proves, step 2's cells it covers, the body. */
function sc(string $suite, string $id, string $what, array $cells, callable $fn): void
{
    $only = $GLOBALS['only'];
    if ($only && !in_array($suite, $only, true)) return;
    fresh();
    $GLOBALS['cur'] = ['fail' => null, 'more' => [], 'n' => 0, 'skip' => null];
    try { $fn(); } catch (Throwable $x) { if ($GLOBALS['cur']['fail'] === null) $GLOBALS['cur']['fail'] = ['no exception', get_class($x) . ': ' . $x->getMessage() . ' @' . basename($x->getFile()) . ':' . $x->getLine()]; }
    if ($GLOBALS['cur']['skip'] === null) { try { $vw = views(); if ($vw) foreach ($cells as $cell) $GLOBALS['viewsOf'][$cell][] = $id; } catch (Throwable $x) { chk('the views draw (board, Pulse, card)', false, get_class($x) . ': ' . $x->getMessage() . ' @' . basename($x->getFile()) . ':' . $x->getLine()); } }
    $c = $GLOBALS['cur']; $GLOBALS['cur'] = null;
    if ($c['skip'] !== null) { echo '  --  ' . $id . ' ' . $what . ' — ' . $c['skip'] . "\n"; $st = 'skip'; }
    elseif ($c['fail'] === null) { echo '  ok  ' . $id . ' ' . $what . ' (' . $c['n'] . ")\n"; $st = 'ok'; }
    else {
        echo 'FAIL  ' . $id . ' ' . $what . ' — expected ' . $c['fail'][0] . '; got ' . j($c['fail'][1]) . ($c['more'] ? ' (and ' . count($c['more']) . ' more below)' : '') . "\n";
        foreach ($c['more'] as [$e, $g]) echo '        · also expected ' . $e . '; got ' . mb_substr(j($g), 0, 160) . "\n";   // not a result line: the runner counts only "  ok  " and "FAIL  "
        $st = 'fail';
    }
    $GLOBALS['results'][] = [$suite, $id, $st];
    foreach ($cells as $cell) $GLOBALS['cellsOf'][$cell][] = [$id, $st];
}
/**
 * The views (cells.md's "views: pending", now the views are in): every alarm the scenario left is drawn where a person
 * reads it, and each surface says what the engine says (decisions/0176 §2: one shape everywhere; 02 §5.4, playbook
 * principle 4): the board's row (tv_cb_alarms), its Pulse item (ControlAlarms::pulseBody) and, for an outside service or a
 * worker, its module card (tv_module_card). Checked on each: the thread's title and its status words; the part's plain
 * name, never a raw id (job:, wf:, svc:, task:, ep:, wk:); its Fix it where its cause names one spot; a closed alarm off
 * the board, its Pulse item reading Recovered or Fixed by. Returns how many alarms it drew.
 */
function views(): int
{
    $pdo = $GLOBALS['pdo']; $app = $GLOBALS['APP'];
    static $loaded = false;
    if (!$loaded) { require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
        require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/module-cards.php'; $loaded = true; }
    $rows = $pdo->query('SELECT * FROM control_alarms ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return 0;
    $names = []; try { foreach (ControlRegister::processes($pdo) as $k => $p) $names[$k] = (string) $p[1]; } catch (Throwable $x) {}
    $btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb="' . $act . '"' . ($ok ? '' : ' disabled') . '>' . htmlspecialchars($label) . '</button>';
    $pid = fn (string $x) => 'cb-p-' . str_replace(':', '-', $x);
    $txt = fn (string $h) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace(['~<(style|script)\b.*?</\1>~s', '/<(details|summary|dt|dd|p|li|div)\b[^>]*>/'], ['', ' '], $h)), ENT_QUOTES | ENT_HTML5)));
    // a raw id in what a person reads (02 §5.4: "the part's plain name … never job:, wf: or svc:")
    // the bench's own checker words ("stand-in: wk:crawler gave no answer"), never shown live
    $raw = fn (string $t) => preg_match_all('/(?<![\w\/.@-])(?:job|wf|svc|task|ep|wk|run|pf):[a-z0-9_][\w:.-]*/i', (string) preg_replace('/stand-in: \S+/', '', $t), $m, PREG_OFFSET_CAPTURE) ? array_map(fn ($x) => $x[0] . ' in "…' . mb_substr($t, max(0, (int) $x[1] - 70), 110) . '…"', array_slice($m[0], 0, 2)) : [];
    $open = array_values(array_filter(ControlAlarms::list($pdo), fn ($a) => $a['cleared_at'] === null));   // as the console reads them (fix decoded)
    $board = $open ? $txt(tv_cb_alarms($open, ControlAlarms::counts($pdo), $names, $pid, true, '', $btn)) : '';
    foreach ($rows as $a) {
        $id = (int) $a['id']; $k = (string) $a['akey'];
        $f = ControlAlarms::facts($pdo, $a); $push = Thread::push($f);
        $title = (string) ($f['title'] ?? ''); $status = (string) ($push['body'] ?? '');
        // its words up to its first moment (a time is drawn per surface: in the viewer's zone on a page, UTC in text)
        $statusHead = trim((string) preg_replace('/\s*(\{t:|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d).*$/su', '', mb_substr($status, 0, 70)), " .:,");
        $pulse = $txt((string) (ControlAlarms::pulseBody($pdo, ['thing' => 'alarm:' . $id], 7)['html'] ?? ''));
        $fix = $a['cleared_at'] === null ? Thread::fixOf(['fix' => ControlAlarms::fixOf($pdo, $a)]) : null;
        if ($a['cleared_at'] === null) {
            $li = ''; if (preg_match('~<li id="cb-alarm-' . $id . '".*?</li>~s', tv_cb_alarms($open, ControlAlarms::counts($pdo), $names, $pid, true, '', $btn), $mm)) $li = $txt($mm[0]);
            chk("views $k: the board's row has its title and status", $li !== '' && str_contains($li, $title) && ($statusHead === '' || str_contains($li, $statusHead)), [$title, $statusHead, mb_substr($li, 0, 240)]);
            chk("views $k: the board's row names parts, never a raw id", !$raw($li), $raw($li));
            if ($fix) chk("views $k: Fix it on the board's row", str_contains($li, (string) ($fix['label'] ?? 'Fix it')), [$fix, mb_substr($li, 0, 200)]);
        } else chk("views $k: a closed alarm is off the board", !str_contains($board, 'cb-alarm-' . $id) && !preg_match('~id="cb-alarm-' . $id . '"~', tv_cb_alarms($open ?: [], ControlAlarms::counts($pdo), $names, $pid, true, '', $btn)), $id);
        chk("views $k: its Pulse item has the same title and status", str_contains($pulse, $title) && ($statusHead === '' || str_contains($pulse, $statusHead)), [$title, $statusHead, mb_substr($pulse, 0, 240)]);
        chk("views $k: its Pulse item names parts, never a raw id", !$raw($pulse), $raw($pulse));
        chk("views $k: no raw time token on the board or in Pulse", !str_contains($pulse . $board, '{t:'), mb_substr($pulse . $board, max(0, (int) mb_strpos($pulse . $board, '{t:') - 60), 120));
        $proc = (string) $a['proc']; $key = str_starts_with($proc, 'svc:') ? substr($proc, 4) : (str_starts_with($proc, 'wk:') ? $proc : '');
        if ($key !== '' && tv_mc_manifest($key) && $a['cleared_at'] === null) {
            $card = $txt(tv_module_card($pdo, $key, ['can' => true]));
            $ti = mb_strpos($card, $title);
            chk("views $k: its module card shows the thread (title and status)", $ti !== false && ($statusHead === '' || str_contains($card, $statusHead)), [$title, $ti !== false, $statusHead, $statusHead === '' || str_contains($card, $statusHead), mb_substr($card, max(0, ($ti !== false ? $ti : (int) mb_strpos($card, "Status:")) - 200), 500)]);
            chk("views $k: its module card names parts, never a raw id", !$raw($card), $raw($card));
            chk("views $k: its module card shows no raw time token", !str_contains($card, '{t:'), mb_substr($card, max(0, (int) mb_strpos($card, '{t:') - 60), 120));
        }
    }
    return count($rows);
}

/** A scenario that can't be caused on the bench (cells.md says why): neither a pass nor a fail. */
function notHere(string $why): void { $GLOBALS['cur']['skip'] = $why; }

$GLOBALS['only'] = $only;
foreach (['lift', 'faults', 'clock', 'actions', 'escalation', 'correlation', 'portal', 'named', 'words'] as $s) require __DIR__ . '/suites/' . $s . '.php';

// ---- the summary ----------------------------------------------------------------------------------------------------
echo "\nBy suite:\n";
$by = [];
foreach ($GLOBALS['results'] as [$s, $id, $st]) { $by[$s] ??= ['ok' => 0, 'fail' => 0, 'skip' => 0]; $by[$s][$st]++; }
foreach ($by as $s => $n) printf("    %-12s %3d ok %3d fail %3d not on the bench\n", $s, $n['ok'], $n['fail'], $n['skip']);
$designed = array_filter($GLOBALS['results'], fn ($r) => preg_match('/^S-[A-G]\d+$/', $r[1]));
$ids = array_unique(array_map(fn ($r) => $r[1], $designed));
// the cells: every one of step 2's 173 (cells.md's table), each covered by at least one scenario
$all = require __DIR__ . '/cells.php';
$covered = array_filter($all, fn ($c) => isset($GLOBALS['cellsOf'][$c]));
$missing = array_diff($all, array_keys($GLOBALS['cellsOf']));
$cellOk = array_filter($covered, fn ($c) => !array_filter($GLOBALS['cellsOf'][$c], fn ($r) => $r[1] === 'fail'));
printf("\nScenarios: %d of 135 designed run (%d named and words checks besides)\n", count($ids), count($GLOBALS['results']) - count($designed));
printf("Cells: %d of %d covered by a scenario (%d with every scenario passing)\n", count($covered), count($all), count($cellOk));
if ($missing && !$only) echo 'Cells with no scenario: ' . implode(', ', $missing) . "\n";
$vc = array_filter($all, fn ($c) => isset($GLOBALS['viewsOf'][$c]));
printf("Views: %d of %d cells drawn on the board, in Pulse and on the card (cells.md's \"views\" column)\n", count($vc), count($all));
if (getenv('VIEWS_OUT')) file_put_contents((string) getenv('VIEWS_OUT'), json_encode(['views' => $GLOBALS['viewsOf'] ?? [], 'cells' => $GLOBALS['cellsOf'], 'results' => $GLOBALS['results']], JSON_PRETTY_PRINT));
$f = count(array_filter($GLOBALS['results'], fn ($r) => $r[2] === 'fail'));
echo "\n" . (count($GLOBALS['results']) - $f) . " passed or not on the bench, $f failed\n";
exit($f ? 1 : 0);
