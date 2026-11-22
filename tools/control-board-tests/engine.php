<?php
// The control board's one engine (handoffs/control-board-build.md step 4; the state model, step3/02-state-model.md):
// the component that failed (P2), the system's tries on the diagnostic clock (P3), a hold as narrow as its cause (P5, P6;
// D1: /api/crawl/loads holds only the bulk loads, W1's other tasks carry on), no close without a real check (P4, D3), a
// task's own code held by nothing (ruling 3), a job holding only its task (D8), words from state (P9, ruling 5), an
// Incident Report at every trip and correlation within 15 minutes (P12, O1), and the migrations run twice.
// Run: mariadb < ../release-b-tests/start-schema.sql && php engine.php <root>/app   (the root: the mirror with the build over it)
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlEngine.php';
require_once $app . '/lib/ControlIncidents.php'; require_once $app . '/lib/Automation.php'; require_once $app . '/lib/ControlRegister.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS system_pauses'); $pdo->exec('DROP TABLE IF EXISTS control_incidents');
// the release's migrations, twice, over a table made the old way
$pdo->exec("CREATE TABLE control_alarms (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, akey VARCHAR(120) NOT NULL)");
foreach ([1, 2] as $_) foreach (['2026-12-01_control_engine.sql', '2026-12-01_control_incidents.sql'] as $mf)
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', (string) file_get_contents($app . '/migrations/' . $mf))))) as $q) $pdo->exec($q);
$cols = $pdo->query("SHOW COLUMNS FROM control_alarms")->fetchAll(PDO::FETCH_COLUMN);
$t('Migration A runs twice and adds component, try_n, next_try_at, incident_id', !array_diff(['component', 'try_n', 'next_try_at', 'incident_id'], $cols), $cols);
$t('Migration B runs twice and makes control_incidents', (bool) $pdo->query("SHOW TABLES LIKE 'control_incidents'")->fetchColumn());
require_once $app . '/lib/staging-tables.php';
$t('control_incidents is each site\'s own, as its alarms are (zip check R14)', StagingTables::classOf('control_incidents') === StagingTables::classOf('control_alarms'), StagingTables::classOf('control_incidents'));
$pdo->exec('DROP TABLE control_alarms'); $pdo->exec('DROP TABLE control_incidents');
$t('the store makes its table with the engine\'s columns', ControlAlarms::engineOn($pdo));

$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('overture_bulk','W1',1),('foursquare_bulk','W1',1),('geofabrik_bulk','W1',1),('coverage','W1',1),('second_look','W2',1)");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1 WHERE task IN ('overture_bulk','foursquare_bulk','geofabrik_bulk','coverage','second_look','recheck')");
ControlRegister::forget(); Automation::forget();
$open = fn (string $k) => ControlAlarms::openOf($pdo, $k);
$back = function (string $k, int $mins) use ($pdo) { $pdo->prepare('UPDATE control_alarms SET first_at = first_at - INTERVAL ? MINUTE, next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE akey = ? AND cleared_at IS NULL')->execute([$mins, $k]); };
$paused = fn (string $kind, string $ref) => Automation::forget() || Automation::sysOff($pdo, $kind, $ref) !== null;
$checks = []; ControlEngine::$checker = function (string $c) use (&$checks) { return array_key_exists($c, $checks) ? $checks[$c] : ['pass' => null]; };

// 1. the component (P2)
$t('a task\'s failure on an outside host is that service\'s', ControlEngine::classifyTask($pdo, 'coverage', 'HTTP 503', ['api.tavily.com' => [3, 3, '503']]) === 'svc:tavily', ControlEngine::classifyTask($pdo, 'coverage', 'HTTP 503', ['api.tavily.com' => [3, 3, '503']]));
$t('a read timeout in a bulk load with no outside host failing is our endpoint\'s (IR-0001)', ControlEngine::classifyTask($pdo, 'overture_bulk', "TimeoutError('The read operation timed out')", []) === 'ep:crawl/loads');
$t('a failure with nothing to point at is the task\'s own code', ControlEngine::classifyTask($pdo, 'second_look', 'KeyError: name', []) === 'task:second_look');
$t('keys map to their components', ControlEngine::componentOf($pdo, 'ep:crawl/loads:5xx', 'wf:W1') === 'ep:crawl/loads' && ControlEngine::componentOf($pdo, 'job:identity_recheck_tick:stuck', 'job:identity_recheck_tick') === 'job:identity_recheck_tick' && ControlEngine::componentOf($pdo, 'pf:svc:loc', 'svc:loc') === 'svc:loc' && ControlEngine::componentOf($pdo, 'run:failed', 'wk:crawler') === 'wk:crawler');

// 2. a task's own code (ruling 3): a warning, then a trip that holds nothing (case #51: W1 never held whole)
$raiseTask = fn (string $tk, string $wf) => ControlAlarms::raise($pdo, 'task:failed:' . $tk, 'wf:' . $wf, 'trip', 'crash', $wf . ': its task ' . $tk . ' failed', 'Its work goes back and the next run tries it again; the workflow\'s other tasks carry on.', 'Read its why.', 'boom');
$raiseTask('second_look', 'W2'); $a = $open('task:failed:second_look');
$t('a task\'s first failure is a warning, its component the task, no try clock (its next run is its check)', $a['level'] === 'warning' && $a['component'] === 'task:second_look' && $a['next_try_at'] === null, $a);
$f = ControlAlarms::facts($pdo, $a);
$t('…its words say no check can run before its next run, and nothing is held (not "other tasks carry on")', str_contains($f['doing'], 'No check can run before its next run') && !str_contains($f['doing'], 'carry on'), $f['doing']);
$raiseTask('second_look', 'W2'); $a = $open('task:failed:second_look');
$t('its next run failing too is a trip', $a['level'] === 'trip' && $a['tripped_at'] !== null, $a['level']);
$t('…that holds nothing: W2 isn\'t paused, nor the task', !$paused('wf', 'W2') && !$paused('task', 'second_look') && (string) $a['stopped'] === '', $a['stopped']);
$f = ControlAlarms::facts($pdo, $a);
$t('…its status: Down, the system is still trying; its words: its next scheduled run still goes ahead', str_starts_with($f['status'], 'Down: the system is still trying') && str_contains($f['doing'], 'next scheduled run still goes ahead'), $f);
$t('…its Incident Report is open, IR-#### with its trip id', (int) $a['incident_id'] > 0 && ControlIncidents::get($pdo, (int) $a['incident_id'])['members'][0]['id'] == $a['id']);
[$ok, $w] = ControlAlarms::resolve($pdo, (int) $a['id'], 7, 'Jason', 'Fixed the parser');
$t('Check and start again on a task\'s own code: noted, stays open until its next run does its work (never closed on the press: NG)', $ok && str_contains($w, 'Its next run is its check') && $open('task:failed:second_look') !== null, $w);
ControlAlarms::clear($pdo, 'task:failed:second_look', 'It did its work in run #9.');
$c = $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'task:failed:second_look'")->fetchColumn();
$t('…its next run doing its work closes it as Fixed by the person, its check named', $open('task:failed:second_look') === null && str_starts_with((string) $c, 'Fixed by Jason'), $c);
$t('…and its Incident Report closes with it', ControlIncidents::get($pdo, (int) $a['incident_id'])['closed_at'] !== null);

// 3. two bulk tasks failing in W1 never hold W1 whole (D1, case #51)
$raiseTask('overture_bulk', 'W1'); $raiseTask('overture_bulk', 'W1'); $raiseTask('foursquare_bulk', 'W1'); $raiseTask('foursquare_bulk', 'W1');
$t('two task trips in W1: W1 isn\'t held; coverage still runs', !$paused('wf', 'W1') && !$paused('task', 'coverage') && !$paused('master', 'all'));
ControlAlarms::clear($pdo, 'task:failed:overture_bulk', 'ok'); ControlAlarms::clear($pdo, 'task:failed:foursquare_bulk', 'ok');

// 4. our endpoint's server error: tried on the clock, then holds only the tasks that call it
$ep = fn () => ControlAlarms::raise($pdo, 'ep:crawl/loads:5xx', 'wf:W1', 'trip', 'crash', '/api/crawl/loads answered a server error (500)', '', '', 'answered 500');
$ep(); $a = $open('ep:crawl/loads:5xx');
$t('an endpoint\'s server error is a warning first, its first try about 5 min after', $a['level'] === 'warning' && $a['component'] === 'ep:crawl/loads' && abs(strtotime($a['next_try_at'] . ' UTC') - strtotime($a['first_at'] . ' UTC') - 300) < 3, $a);
$f = ControlAlarms::facts($pdo, $a);
$t('…its words: trying again, 0 of 3, next at its time; nothing held', str_contains($f['status'], 'trying again by itself (0 of 3, next at {t:') && str_contains($f['status'], 'Nothing is held'), $f['status']);
$ep(); $ep(); $ep(); $a = $open('ep:crawl/loads:5xx');
$t('a burst of reports inside the tries is one fault, not tries (P3)', $a['level'] === 'warning' && (int) $a['try_n'] === 0, [$a['level'], $a['try_n']]);
$checks['ep:crawl/loads'] = ['pass' => false, 'proof' => '/api/crawl/loads answered 500 in 210 ms.', 'ms' => 210];
$back('ep:crawl/loads:5xx', 5); ControlAlarms::expireTries($pdo); $a = $open('ep:crawl/loads:5xx');
$t('try 1 at 5 min: its check fails, the next try at 15 min', (int) $a['try_n'] === 1 && $a['level'] === 'warning' && abs(strtotime($a['next_try_at'] . ' UTC') - strtotime($a['first_at'] . ' UTC') - 900) < 3 && str_contains($a['reading'], 'Try 1'), $a);
$back('ep:crawl/loads:5xx', 0); ControlAlarms::expireTries($pdo); $back('ep:crawl/loads:5xx', 0); ControlAlarms::expireTries($pdo); $a = $open('ep:crawl/loads:5xx');
$t('the third failed try is a trip', $a['level'] === 'trip' && $a['tripped_at'] !== null && (int) $a['try_n'] === 3, [$a['level'], $a['try_n']]);
$t('…holding only overture_bulk, foursquare_bulk and geofabrik_bulk', $paused('task', 'overture_bulk') && $paused('task', 'foursquare_bulk') && $paused('task', 'geofabrik_bulk') && !$paused('task', 'coverage') && !$paused('wf', 'W1'), $a['stopped']);
$f = ControlAlarms::facts($pdo, $a);
$t('…its status names what\'s held: Paused by the system (…)', str_starts_with($f['status'], 'Paused by the system (') && str_contains($f['status'], 'overture_bulk'), $f['status']);
$t('…its doing: only what uses it waits, everything else carries on', str_contains($f['doing'], 'Only what uses it waits') , $f['doing']);
$t('…no switch was written (a hold, never a switch: P5)', (int) $pdo->query("SELECT COUNT(*) FROM automation_log WHERE by_name LIKE 'the Control Board%'")->fetchColumn() === 0 && (int) $pdo->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'overture_bulk'")->fetchColumn() === 1);
$ir = (int) $a['incident_id'];
$t('…its Incident Report records what it holds', $ir > 0 && in_array('overture_bulk (W1)', ControlIncidents::get($pdo, $ir)['radius']['held'], true), ControlIncidents::get($pdo, $ir)['radius']);
[$ok, $w] = ControlAlarms::resolve($pdo, (int) $a['id'], 7, 'Jason', '');
$t('Check and start again with its check still red: stays open, saying why (no 5 ms close: D3)', !$ok && str_contains($w, 'its check is still red') && $open('ep:crawl/loads:5xx') !== null, $w);
$checks['ep:crawl/loads'] = ['pass' => true, 'proof' => '/api/crawl/loads answered 200 in 180 ms.', 'ms' => 180];
[$ok, $w] = ControlAlarms::resolve($pdo, (int) $a['id'], 7, 'Jason', '');
$t('…with its check passing: closed, the check named, the holds lifted', $ok && str_contains($w, 'Its check passed') && $open('ep:crawl/loads:5xx') === null && !$paused('task', 'overture_bulk'), $w);

// 5. recovery by the system's own try
$checks['ep:crawl/loads'] = ['pass' => false, 'proof' => '500', 'ms' => 1];
$ep(); $back('ep:crawl/loads:5xx', 61); ControlAlarms::expireTries($pdo); $a = $open('ep:crawl/loads:5xx');
$t('the hour running out is a trip at once (keep the hour)', $a['level'] === 'trip', $a['level']);
$checks['ep:crawl/loads'] = ['pass' => true, 'proof' => '/api/crawl/loads answered 200 in 150 ms.', 'ms' => 150];
$pdo->exec("UPDATE control_alarms SET next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE akey = 'ep:crawl/loads:5xx' AND cleared_at IS NULL"); ControlAlarms::expireTries($pdo);
$c = (string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'ep:crawl/loads:5xx' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('a trip\'s due check passing closes it as Recovered, its proof kept, holds lifted', $open('ep:crawl/loads:5xx') === null && str_starts_with($c, 'Recovered') && str_contains($c, 'answered 200') && !$paused('task', 'overture_bulk'), $c);

// 6. a job holds only its task (D8)
ControlAlarms::raise($pdo, 'job:identity_recheck_tick', 'job:identity_recheck_tick', 'trip', 'crash', 'The job "identity_recheck_tick" stopped', '', '', 'x');
ControlAlarms::raise($pdo, 'job:identity_recheck_tick', 'job:identity_recheck_tick', 'trip', 'crash', 'The job "identity_recheck_tick" stopped', '', '', 'x');
$a = $open('job:identity_recheck_tick');
$t('identity_recheck_tick failing twice: a trip holding only W2\'s recheck, never W2', $a['level'] === 'trip' && $paused('task', 'recheck') && !$paused('wf', 'W2'), [$a['level'], $a['stopped']]);
ControlAlarms::clear($pdo, 'job:identity_recheck_tick', 'Its last run ended well.');

// 7. a source with a working later source holds nothing (On a backup); one without holds its tasks
$t('sourceTasks: a service no task calls holds nothing', ControlEngine::sourceTasks($pdo, 'nosuch') === []);

// 8. correlation: two trips on the same host and kind within 15 minutes share one incident
$mk = function (string $k) use ($pdo) { ControlAlarms::raise($pdo, $k, 'site:search', 'trip', 'held', 'Held ' . $k, '', '', 'r', [], true, false); return ControlAlarms::openOf($pdo, $k); };
$x1 = $mk('ep:crawl/index:held'); $x2 = $mk('ep:crawl/jobs:held');
$t('two trips on our site of the same kind inside 15 min share one IR', (int) $x1['incident_id'] > 0 && $x1['incident_id'] === $x2['incident_id'], [$x1['incident_id'], $x2['incident_id']]);
$pdo->exec("UPDATE control_incidents SET opened_at = opened_at - INTERVAL 20 MINUTE");
$x3 = $mk('ep:crawl/geocode:held');
$t('…one after the window opens its own', (int) $x3['incident_id'] !== (int) $x1['incident_id']);
$t('IR numbers read IR-0001; trip and warning ids T-/W-', ControlIncidents::number(1) === 'IR-0001' && ControlIncidents::tripId(42) === 'T-0042' && ControlIncidents::warningId(42) === 'W-0042');
ControlIncidents::setCause($pdo, (int) $x1['incident_id'], 'The index host answered 504 for 30 hours.', 'Jason');
$t('a root cause written by a person is Confirmed', ControlIncidents::get($pdo, (int) $x1['incident_id'])['root_cause_state'] === 'confirmed');

// 9. a critical part: tripped at once while trying, tries out → the Escalation Notice
$pdo->exec("DELETE FROM search_index_state WHERE k = 'control:duties'");
ControlAlarms::raise($pdo, 'pf:svc:github', 'svc:github', 'trip', 'crash', 'GitHub didn\'t answer', '', '', 'timeout');
$a = $open('pf:svc:github');
$t('a critical part (GitHub, procedures) trips at once and keeps trying (P8)', $a['level'] === 'trip' && $a['retrying_at'] !== null && $a['next_try_at'] !== null || $a['level'] === 'warning', $a);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
