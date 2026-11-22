<?php
// decisions/0175 release 1 (Jason, 2026-11-20): a fault never means Off. A trip is the system placing its pause ("A
// trip is when the PAUSE has been placed"), kept with its alarm and never written into a person's switch; every gate
// reads it beside the switch; it lifts when its check passes (Recovered), or for the system's try once each window; a
// person's Check and start again lifts it too. Today's switches a trip wrote into go back to what a person last set.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php paused.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ServiceHealth.php';
require_once $app . '/lib/Automation.php'; require_once $app . '/lib/SearchIndex.php'; require_once $app . '/lib/crawler/CrawlService.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS system_pauses');
if (!is_file($app . '/migrations/2026-11-20_system_pauses.sql')) { require_once $app . '/lib/Automation.php'; Automation::ensurePauses($pdo); }   // run on live and deleted: the table as the code makes it
else foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', (string) file_get_contents($app . '/migrations/2026-11-20_system_pauses.sql'))))) as $q) { $pdo->exec($q); $pdo->exec($q === '' ? 'DO 0' : $q); }
$t('its database update runs twice cleanly', (bool) $pdo->query("SHOW TABLES LIKE 'system_pauses'")->fetchColumn());
require_once $app . '/lib/staging-tables.php';
$t('system_pauses names its staging class (each site\'s own, as its alarms are)', StagingTables::classOf('system_pauses') === StagingTables::classOf('control_alarms'), StagingTables::classOf('system_pauses'));
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, name) VALUES ('coverage', 'W1', 1, 'Coverage'), ('second_look', 'W2', 1, 'Second look')");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1, off_why = NULL WHERE task IN ('coverage', 'second_look')");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason');
Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W2', false, 'test', 7, 'Jason'); Automation::forget();
$wfPaused = fn (string $wf) => (int) $pdo->query('SELECT paused FROM crawl_workflows WHERE wf = ' . $pdo->quote($wf))->fetchColumn();

// 1. a trip places the pause; the switch stays the person's. Since the control board's build (step 4) a trip holds only
// what its cause touches (P6): /api/crawl/verify's server error holds second_look, the task that calls it, never W2 whole
require_once $app . '/lib/ControlEngine.php';
$red = true; ControlEngine::$checker = function (string $c) use (&$red) { return $c === 'ep:crawl/verify' ? ['pass' => !$red, 'proof' => '/api/crawl/verify answered ' . ($red ? '500' : '200') . ' in 90 ms.', 'ms' => 90] : null; };
$crash = fn (bool $ev = true) => ControlAlarms::raise($pdo, 'ep:crawl/verify:5xx', 'wf:W2', 'trip', 'crash', '/api/crawl/verify answered a server error (500)', '', '', 'boom', [], $ev, false);
$held = fn () => Automation::forget() || Automation::taskOff($pdo, 'second_look') !== null;
$crash(); Automation::forget(); $a = ControlAlarms::openOf($pdo, 'ep:crawl/verify:5xx');
$t('a trip: the alarm is a trip', ($a['level'] ?? '') === 'trip' && $a['tripped_at'] !== null, $a);
$t('…W2\'s switch is untouched (still on, as Jason left it; Automation::wfOff reads only the switch)', $wfPaused('W2') === 0 && Automation::wfOff($pdo, 'W2') === null);
$t('…and second_look is paused by the system, with its reason', str_contains((string) Automation::taskOff($pdo, 'second_look'), 'Paused by the system (a trip): /api/crawl/verify answered a server error') && str_contains((string) Automation::taskOff($pdo, 'second_look'), 'It resumes when its check passes'), Automation::taskOff($pdo, 'second_look'));
$t('…its record names the alarm', (int) $pdo->query("SELECT alarm_id FROM system_pauses WHERE kind = 'task' AND ref = 'second_look' AND cleared_at IS NULL")->fetchColumn() === (int) $a['id']);
$t('…what it stopped reads as a pause', str_contains((string) $a['stopped'], '"pause","task:second_look"'), $a['stopped']);
$t('…W2 and W1 run on: only what the fault touches is paused', Automation::gathering($pdo, '', 'W1') === null && Automation::sysOff($pdo, 'wf', 'W2') === null);
$t('the log has no switch written by the board', (int) $pdo->query("SELECT COUNT(*) FROM automation_log WHERE by_name LIKE 'the Control Board%'")->fetchColumn() === 0);

// 2. the crawler's plan leaves out what the system paused
$src = (string) file_get_contents($app . '/lib/crawler/CrawlService.php');
$t('the crawler\'s plan leaves out a task whose workflow or self the system paused', str_contains($src, 'if (Automation::sysAny($pdo, [\'wf:\' . (string) $r[\'wf\'], \'task:\' . (string) $t]) !== null) continue;') && Automation::sysOff($pdo, 'task', 'second_look') !== null && Automation::sysOff($pdo, 'wf', 'W1') === null);
$ol = (string) file_get_contents($app . '/lib/crawler/OpenLoads.php'); $li = (string) file_get_contents($app . '/lib/ListingIdentity.php');
$t('…and so do the open-data loads and the identity recheck', str_contains($ol, "'task:' . $task") && str_contains($li, "'task:' . self::TASK"));

// 3. it failed again: still paused (no second pause); its check passes: Recovered, the pause lifts
$crash(); $t('the same fault again: one pause, not two', (int) $pdo->query("SELECT COUNT(*) FROM system_pauses WHERE ref = 'second_look' AND cleared_at IS NULL")->fetchColumn() === 1);
ControlAlarms::clear($pdo, 'ep:crawl/verify:5xx', 'It answered 200.'); Automation::forget();
$c = ControlAlarms::byId($pdo, (int) $a['id']);
$t('its check passes: the trip closes itself as Recovered, escalated or not (Jason, 2026-11-20: "Close as Recovered")', ControlAlarms::ESCALATED_RECOVERS === true && $c['cleared_at'] !== null && str_starts_with((string) $c['cleared_how'], 'Recovered:'), $c['cleared_how']);
$t('…and its pause lifts: second_look runs again', !$held() && (string) $pdo->query("SELECT cleared_how FROM system_pauses WHERE ref = 'second_look'")->fetchColumn() !== '');

// 4. no lift without a check passing (Jason, 2026-11-20: "Why is this session inventing things instead of check what's
// already set by adr?"): the watch's checks after a trip (the state model §7) lift it only when the check passes
$crash(); Automation::forget(); $a2 = ControlAlarms::openOf($pdo, 'ep:crawl/verify:5xx');
$t('a new trip: paused again', $held(), $a2);
$pdo->exec("UPDATE system_pauses SET at = UTC_TIMESTAMP() - INTERVAL 5 HOUR WHERE cleared_at IS NULL");
$pdo->exec("UPDATE control_alarms SET next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id = " . (int) $a2['id']);
try { ControlAlarms::tick($pdo); } catch (Throwable $x) {} Automation::forget();
$t('hours later, its check still red: the watch hasn\'t lifted it', $held() && !method_exists('ControlAlarms', 'tryPaused'));

// 5. a person's Check and start again runs its check first: red keeps the pause, saying why; once the check passes, it lifts
[$ok, $msg] = ControlAlarms::resolve($pdo, (int) $a2['id'], 7, 'Jason', ''); Automation::forget();
$t('Check and start again with its check red: not started, the pause stays, saying why', !$ok && str_contains($msg, 'its check is still red') && $held() && $wfPaused('W2') === 0, $msg);
$red = false;
[$ok, $msg] = ControlAlarms::resolve($pdo, (int) $a2['id'], 7, 'Jason', ''); Automation::forget();
$t('…and its check passing lifts it; the switch was never touched', $ok && !$held() && $wfPaused('W2') === 0, $msg);
ControlEngine::$checker = null;

// 6. out of budget: the service paused by the system, its switch untouched
$pdo->exec("DELETE FROM automation_switches WHERE kind = 'service' AND ref = 'tavily'"); Automation::forget();
ControlAlarms::raise($pdo, 'ops:cap:tavily', 'svc:tavily', 'trip', 'limit', 'Tavily: out of this month\'s budget', '', '', '', [], true, false); Automation::forget();
$t('out of budget: Tavily paused by the system; no person\'s pause written', str_contains((string) Automation::gathering($pdo, 'tavily'), 'Paused by the system (a trip)') && Automation::service($pdo, 'tavily') === null && Automation::serviceOff($pdo, 'tavily') === null && str_contains((string) Automation::hostOff($pdo, 'api.tavily.com'), 'Paused by the system'), Automation::gathering($pdo, 'tavily'));

// 7. a task's trip: the task paused by the system; its switch stays on; the card and Automation say both
require_once $app . '/lib/SystemStart.php';
$tk = ''; $tid = ''; foreach (SystemStart::TASKS as $id => $d) if (!empty($d[4])) { $tid = $id; $tk = (string) $d[4][0]; break; }
if ($tk !== '') {
    $pdo->prepare("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, name) VALUES (?, 'W1', 1, ?)")->execute([$tk, $tk]);
    $pdo->prepare('UPDATE crawl_capture_tasks SET enabled = 1 WHERE task = ?')->execute([$tk]);
    // a Start task's line red (its services: the one engine's t: component) holds its crawler tasks; a task failing in its
    // own code holds nothing (ruling 3), so the line is what's raised here
    ControlAlarms::raise($pdo, 'pf:t:' . $tid, 't:' . $tid, 'trip', 'crash', 'Its task ' . $tk . ': its sources don\'t answer', '', '', '', [], true, false); Automation::forget();
    $t('a Start task\'s trip: the task is paused by the system, its switch still on', Automation::taskOff($pdo, $tk) !== null && (int) $pdo->query('SELECT enabled FROM crawl_capture_tasks WHERE task = ' . $pdo->quote($tk))->fetchColumn() === 1);
}

// 8. once: today's switches a trip wrote into go back to what a person set; an open legacy trip now pauses
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, name) VALUES ('geofabrik_bulk', 'W1', 1, 'Geofabrik: OpenStreetMap by state')");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 0, off_why = 'Shut down from the Control Board by the Control Board (a trip)' WHERE task = 'geofabrik_bulk'");
$pdo->exec("INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP() - INTERVAL 1 DAY, 'task', 'geofabrik_bulk', 0, 'Switch on', 7, 'Jason'), (UTC_TIMESTAMP(), 'task', 'geofabrik_bulk', 1, 'Shut down from the Control Board by the Control Board (a trip)', NULL, 'the Control Board (a trip)')");
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled, name) VALUES ('never_set', 'W1', 0, 'Never set')");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 0, off_why = 'Shut down from the Control Board by the Control Board (a trip)' WHERE task = 'never_set'");
$pdo->exec("UPDATE crawl_workflows SET paused = 1, paused_why = 'Shut down from the Control Board by the Control Board (a trip)' WHERE wf = 'W3'");
$pdo->exec("INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP() - INTERVAL 2 DAY, 'workflow', 'W3', 0, 'Started', NULL, 'Start the system')");
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at, tripped_at, stopped) VALUES ('job:failed:page_read_tick', 'wf:W3', 'trip', 'crash', 'W3: page reads failed', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), '[[\"proc\",\"wf:W3\",\"W3 (its tasks first, then the workflow)\"]]')");
$legacy = (int) $pdo->lastInsertId();
$did = ControlAlarms::settle0175($pdo); Automation::forget();
$t('Geofabrik\'s task goes back on: what Jason last set', (int) $pdo->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'geofabrik_bulk'")->fetchColumn() === 1, $did);
$t('…logged: "Put back to what Jason set on …"', (bool) $pdo->query("SELECT 1 FROM automation_log WHERE ref = 'geofabrik_bulk' AND why LIKE 'Put back to what Jason set on %'")->fetchColumn());
$t('a task nobody ever set stays as it is', (int) $pdo->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'never_set'")->fetchColumn() === 0);
$t('W3 goes back to what Start the system set (on)', $wfPaused('W3') === 0);
$t('…and its open trip now holds only what its cause touches: a job holds nothing beyond its own work (the one engine, P7)', Automation::sysOff($pdo, 'wf', 'W3') === null && !str_contains((string) ControlAlarms::byId($pdo, $legacy)['stopped'], '"wf:W3"'), ControlAlarms::byId($pdo, $legacy)['stopped']);
$again = ControlAlarms::settle0175($pdo);
$t('run again: nothing more to do', $again === [], $again);

// 9. the words: Automation's task row and a service's card say On · Paused by the system
if ($tk !== '') $t('the reason reads as a trip that placed a pause', str_starts_with((string) Automation::taskOff($pdo, $tk), 'Paused by the system (a trip): Its task ' . $tk), Automation::taskOff($pdo, $tk));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
