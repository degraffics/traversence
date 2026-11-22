<?php
// The Control Board's release 4a (decisions/0171 §2.5): maintenance. A process taken out on purpose, with why and a Back by:
// shut down in order, its alarms notices (a trip stays a trip), Start skips it saying why; Put back runs its check first and
// a red check keeps it out with the reason; a window set ahead goes out and comes back by itself (the 5-minute watch); the
// upkeep calendar reads what the code already schedules. Run: mariadb < ../release-b-tests/start-schema.sql && php r4a.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlMaintenance.php'; require_once $app . '/lib/ControlAlarms.php';
require_once $app . '/lib/Automation.php'; require_once $app . '/lib/SystemStart.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS control_maintenance');
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1), ('second_look', 'W2', 1)");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason');
Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W2', false, 'test', 7, 'Jason'); Automation::forget();
$on = function (string $wf) use ($pdo) { Automation::forget(); return Automation::wfOff($pdo, $wf) === null; };
$fresh = function () { $r = new ReflectionProperty('ControlMaintenance', 'out'); $r->setAccessible(true); $r->setValue(null, null); };
$refused = function (callable $f) { try { $f(); return ''; } catch (RuntimeException $x) { return $x->getMessage(); } };

// 1. what can be taken out, and what's refused
// 02 §5.3 and row 103 extend Take out to every part (module, host, job, worker): a task without its own switch still can't
$t('a workflow, a task with its own switch, a service and a site job can be taken out (02 row 103)', ControlMaintenance::canTake('wf:W1') && ControlMaintenance::canTake('t:overture') && !ControlMaintenance::canTake('t:vectors') && ControlMaintenance::canTake('svc:tavily') && ControlMaintenance::canTake('job:preflight_tick'));
$t('no why: refused', str_contains($refused(fn () => ControlMaintenance::take($pdo, 'wf:W2', time(), time() + 3600, '  ', 7, 'Jason')), 'Say why'));
$t('a Back by before it goes out: refused', str_contains($refused(fn () => ControlMaintenance::take($pdo, 'wf:W2', time() + 3600, time() + 60, 'keys', 7, 'Jason')), 'Back by'));

// 2. taken out now: shut down in order, its warnings quiet, a trip stays a trip
ControlAlarms::raise($pdo, 'x:w1slow', 'wf:W1', 'warning', 'slow', 'W1 is slow');
ControlAlarms::ensure($pdo);   // a trip already open on W1, written as one (raising it would stop W1 first)
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at, tripped_at) VALUES ('x:w1trip', 'wf:W1', 'trip', 'held', 'W1 source refused us', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$msg = ControlMaintenance::take($pdo, 'wf:W1', time(), time() + 7200, 'New keys for its source', 7, 'Jason'); $fresh();
$m = ControlMaintenance::outFor($pdo, 'wf:W1');
$t('taken out now: out, W1 shut down (its tasks, then the workflow), and it says back by', $m && $m['state'] === 'out' && !$on('W1') && str_contains($msg, 'back by') && $m['stopped'] === 'wf:W1', [$msg, $m]);
$t('…its open warning is a notice now', ControlAlarms::openOf($pdo, 'x:w1slow')['level'] === 'notice');
$t('…a trip stays a trip: only an admin\'s Resolve clears it', ControlAlarms::openOf($pdo, 'x:w1trip')['level'] === 'trip');
$t('…the same process can\'t be taken out twice over the same time', str_contains($refused(fn () => ControlMaintenance::take($pdo, 'wf:W1', time() + 600, time() + 3600, 'again', 7, 'Jason')), 'already out'));
$t('…a task of W1 is under W1\'s maintenance too', ControlMaintenance::outFor($pdo, 't:overture') !== null && ControlMaintenance::outFor($pdo, 't:recheck') === null);

// 3. while out: what it raises is a notice, saying so
$id = ControlAlarms::raise($pdo, 'x:w1new', 'wf:W1', 'trip', 'crash', 'W1 crashed');
$a = ControlAlarms::byId($pdo, $id);
$t('a new alarm while it\'s out: a notice, saying it\'s in maintenance (no trip, nothing stopped)', $a['level'] === 'notice' && str_starts_with((string) $a['means'], 'In maintenance: New keys') && empty($a['tripped_at']), $a);
$id2 = ControlAlarms::raise($pdo, 'x:w1task', 't:overture', 'warning', 'retry', 'Overture retrying');
$t('…its task\'s too', ControlAlarms::byId($pdo, $id2)['level'] === 'notice');
$id3 = ControlAlarms::raise($pdo, 'x:w2', 'wf:W2', 'warning', 'retry', 'W2 retrying');
$t('…another process\'s alarm is untouched', ControlAlarms::byId($pdo, $id3)['level'] === 'warning');

// 4. Start skips it, saying why
$src = file_get_contents($app . '/lib/SystemStart.php');
$t('Start skips a process in maintenance in its firing and its watch, saying why', substr_count($src, 'self::inMaintenance($pdo, $id)') === 2 && str_contains($src, 'Skipped: it comes back by itself'));
$rm = new ReflectionMethod('SystemStart', 'inMaintenance'); $rm->setAccessible(true);
$t('…its words: "In maintenance: why, back by …"', str_starts_with((string) $rm->invoke(null, $pdo, 'wf:W1'), 'In maintenance: New keys for its source, back by ') && $rm->invoke(null, $pdo, 'wf:W2') === null);

// 5. Put back: its check first; a red check keeps it out with the reason
[$ok, $msg] = ControlMaintenance::putBack($pdo, (int) $m['id'], 7, 'Jason');
$m2 = ControlMaintenance::row($pdo, (int) $m['id']);
if ($ok) $t('Put back: its check passed, it\'s on again, and the row says who', $m2['state'] === 'back' && $on('W1') && $m2['back_by'] === 'Jason', [$msg, $m2]);
else $t('Put back with its check red: still out, W1 off, the reason on its row', $m2['state'] === 'out' && !$on('W1') && str_starts_with((string) $m2['note'], 'Not back at'), [$msg, $m2]);
if (!$ok) { $pdo->prepare("UPDATE control_maintenance SET state = 'back', back_at = UTC_TIMESTAMP(), back_by = 'test' WHERE id = ?")->execute([(int) $m['id']]); $fresh(); }
$fresh();
$t('…once back, a new alarm isn\'t quietened', ControlAlarms::byId($pdo, ControlAlarms::raise($pdo, 'x:w1after', 'wf:W1', 'warning', 'retry', 'W1 retrying'))['level'] === 'warning');

// 6. a window set ahead: planned, then out and back by the watch
if (!$on('W2')) Automation::setWorkflow($pdo, 'W2', false, 'test', 7, 'Jason'); Automation::forget();
$msg = ControlMaintenance::take($pdo, 'wf:W2', time() + 3600, time() + 7200, 'Its source moves tonight', 7, 'Jason'); $fresh();
$w = $pdo->query("SELECT * FROM control_maintenance WHERE proc = 'wf:W2' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$t('a window an hour ahead: planned, nothing stopped yet', $w['state'] === 'planned' && $on('W2') && str_starts_with($msg, 'Planned'), $msg);
$t('…the watch leaves it until its time', ControlMaintenance::tick($pdo) === [] && ControlMaintenance::row($pdo, (int) $w['id'])['state'] === 'planned');
$pdo->prepare('UPDATE control_maintenance SET from_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?')->execute([(int) $w['id']]);
$did = ControlMaintenance::tick($pdo); $fresh();
$t('…its time comes: the watch takes it out and shuts W2 down', $did === ['wf:W2 out'] && ControlMaintenance::row($pdo, (int) $w['id'])['state'] === 'out' && !$on('W2'), $did);
$log = $pdo->query("SELECT title FROM control_alarms WHERE akey LIKE 'maint:wf:W2:%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$t('…logged as a notice, already cleared', $log && str_starts_with($log[0], 'Taken out for maintenance: Its source moves tonight') && ControlAlarms::openOf($pdo, 'maint:wf:W2:' . gmdate('YmdHi')) === null, $log);
$pdo->prepare('UPDATE control_maintenance SET until_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?')->execute([(int) $w['id']]);
$did = ControlMaintenance::tick($pdo); $fresh(); $w2 = ControlMaintenance::row($pdo, (int) $w['id']);
if ($w2['state'] === 'back') $t('…past its Back by: back by itself, after its check', $did === ['wf:W2 back'] && $on('W2') && $w2['back_by'] === 'its window\'s end', [$did, $w2]);
else $t('…past its Back by with its check red: kept out, the reason on its row, a warning raised', $did === ['wf:W2 kept out (red check)'] && !$on('W2') && ControlAlarms::openOf($pdo, 'maint:back:wf:W2')['level'] === 'warning', [$did, $w2]);

// 6b. the watch late, or down for the whole window: never skipped silently (the session manager's note)
$msg = ControlMaintenance::take($pdo, 't:foursquare', time() + 600, time() + 7200, 'Its key rotates', 7, 'Jason');
$l = (int) $pdo->query("SELECT id FROM control_maintenance WHERE proc = 't:foursquare' ORDER BY id DESC LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE control_maintenance SET from_at = UTC_TIMESTAMP() - INTERVAL 40 MINUTE WHERE id = ?')->execute([$l]);
$did = ControlMaintenance::tick($pdo); $fresh();
$lg = (string) $pdo->query("SELECT title FROM control_alarms WHERE akey LIKE 'maint:t:foursquare:%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('a window the watch reached 40 minutes late: out at the next watch, and the log says how late', in_array('t:foursquare out', $did, true) && str_contains($lg, '40 minutes late'), [$did, $lg]);
ControlMaintenance::take($pdo, 't:overture', time() + 600, time() + 1200, 'A short one', 7, 'Jason');
$mm = (int) $pdo->query("SELECT id FROM control_maintenance WHERE proc = 't:overture' ORDER BY id DESC LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE control_maintenance SET from_at = UTC_TIMESTAMP() - INTERVAL 2 HOUR, until_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR WHERE id = ?')->execute([$mm]);
$did = ControlMaintenance::tick($pdo); $mr = ControlMaintenance::row($pdo, $mm);
$lg = (string) $pdo->query("SELECT title FROM control_alarms WHERE akey LIKE 'maint:t:overture:%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('…a window that ended before any watch ran: missed, said on its row and logged, nothing stopped', in_array('t:overture missed', $did, true) && $mr['state'] === 'cancelled' && str_starts_with((string) $mr['note'], 'Missed') && str_contains($lg, 'was missed'), [$did, $mr['note'], $lg]);

// 7. Cancel: only a window not started
$msg = ControlMaintenance::take($pdo, 't:overture', time() + 86400, time() + 90000, 'Not tonight', 7, 'Jason');
$c = $pdo->query("SELECT id FROM control_maintenance WHERE proc = 't:overture' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('a planned window is cancelled; it won\'t go out', str_starts_with(ControlMaintenance::cancel($pdo, (int) $c, 'Jason'), 'Cancelled') && ControlMaintenance::row($pdo, (int) $c)['state'] === 'cancelled');
$t('…one that\'s out isn\'t cancelled: it\'s put back', str_contains($refused(fn () => ControlMaintenance::cancel($pdo, (int) $c, 'Jason')), 'put back'));

// 8. the upkeep calendar
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL, last_probe TEXT NULL,
    resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec("INSERT INTO service_issues (service, host, caller, kind, state, next_probe_at) VALUES ('tavily', 'api.tavily.com', 'worker', 'refused', 'open', UTC_TIMESTAMP() + INTERVAL 30 MINUTE)");
try { $pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, mode, every_minutes, last_served_at) VALUES ('npi_bulk', 'W1', 1, 'bulk', 43200, NOW() - INTERVAL 40 DAY)"); } catch (Throwable $x) {}
$up = ControlMaintenance::upkeep($pdo); $by = []; foreach ($up as $u) $by[$u[0]] = $u;
require_once $app . '/lib/Scheduler.php'; require_once $app . '/lib/Preflight.php';
$sn = Scheduler::JOBS['staging_night_tick'][0]; $dc = Scheduler::JOBS['data_cleanup_tick'][0];
$t('the upkeep calendar, read from the code: the nightly pre-flight at Preflight::NIGHT_HOUR, staging\'s pass and Data cleanup at their Scheduler presets (Arizona)', isset($by['The nightly pre-flight'], $by[$sn], $by[$dc]) && gmdate('H:i', $by['The nightly pre-flight'][1]) === sprintf('%02d:00', Preflight::NIGHT_HOUR + 7) && gmdate('H:i', $by[$sn][1]) === '09:13' && $by[$sn][4] === 'job:staging_night_tick', array_keys($by));
$t('…a job not in cPanel by itself (Data cleanup) shows "not set up" until it has run, then its next time', $by[$dc][1] === 0 && str_starts_with($by[$dc][3], 'Not set up'), $by[$dc]);
Scheduler::ensure($pdo); $pdo->exec("INSERT INTO " . Database::t('job_runs') . " (job, started_at, ok) VALUES ('data_cleanup_tick', NOW() - INTERVAL 1 HOUR, 1)");
$by2 = []; foreach (ControlMaintenance::upkeep($pdo) as $u) $by2[$u[0]] = $u;
$t('…once it runs: due at its preset, 6:13 am Arizona', gmdate('H:i', $by2[$dc][1]) === '13:13' && str_starts_with($by2[$dc][3], 'Last '), $by2[$dc]);
$t('…the night window is CrawlService\'s own', ControlMaintenance::night() === [1, 5]);
$t('…a held service\'s check, when it\'s due', isset($by['A check on tavily (api.tavily.com)']) && abs($by['A check on tavily (api.tavily.com)'][1] - (time() + 1800)) < 120);
$npi = $by['The monthly NPI Registry load'] ?? null;
$t('…a monthly load overdue, named from the unit register: due in the next night window, every 30 days', $npi && $npi[2] === 'every 30 days, in the night window (1 am–5 am Arizona)' && (int) gmdate('G', $npi[1]) >= 8 && (int) gmdate('G', $npi[1]) < 12, $npi);
$ov = null; foreach ($up as $u) if ($u[4] === 't:overture') $ov = $u;
$t('…a task with its own switch is its process on the board (opens in place); off shows as off', $ov === null || $ov[1] === 0 || $ov[4] === 't:overture', $ov);
$t('…soonest first', array_column($up, 1) === (function ($a) { usort($a, fn ($x, $y) => ($x ?: PHP_INT_MAX) <=> ($y ?: PHP_INT_MAX)); return $a; })(array_column($up, 1)));

// 8b. Take out's length is a setting (Thresholds & gates), 2 hours until set
require_once $app . '/lib/Thresholds.php';
$t('Take out\'s default length: Thresholds\' control_maint_hours, 2 until set', isset(Thresholds::GATES['control_maint_hours']) && ControlMaintenance::defaultHours($pdo) === 2);
$api = file_get_contents(dirname($app) . '/website/admin/api.php');
$t('…the row\'s Take out uses it', str_contains($api, 'time() + 3600 * ControlMaintenance::defaultHours($pdo)'));

// 9. the board and the staging class
$cs = file_get_contents($app . '/admin/control-console.php');
$t('the board: blue for maintenance, its stage "In maintenance", a #maintenance panel with the form and the calendar', str_contains($cs, "\$light === 'maint'") && str_contains($cs, "['maint', 'In maintenance']") && str_contains($cs, 'id="maintenance"') && str_contains($cs, 'Upkeep calendar') && str_contains($cs, 'var(--tv-maint,var(--tv-river,#3B5A6B))'));
$t('the alarm list\'s filter is one dropdown button for the header (data-ak-cmd), not a select', str_contains($cs, '<span class="ak-fw cb-afw" data-ak-cmd>') && !str_contains($cs, 'data-cb-afilter'));
$t('the pre-flight result line is red only for a line really tripped or a core host down', str_contains($cs, "\$x['level'] === 'trip' && !empty(\$x['tripped_at'])) || \$coreBad ? 'bad' : 'hold'"));
$ss = file_get_contents($app . '/admin/sections.php'); $blk = substr($ss, (int) strpos($ss, '$u = SchemaUpkeep::unused();'), 2600);
$t('Database updates lists what no code names (#unused), read only: no Keep or remove there', str_contains($blk, "'Unused tables and columns (for Claude to sort)'") && !str_contains($blk, 'name="left"') && str_contains($ss, '<div id="unused"></div>'));
require_once $app . '/lib/staging-tables.php';
$t('control_maintenance is each site\'s own on staging', in_array('control_maintenance', StagingTables::OWN, true));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
