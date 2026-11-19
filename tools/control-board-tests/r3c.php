<?php
// The Control Board's release 3c (decisions/0171 §5, §7's note; Jason, 2026-11-18): a trip is what the system couldn't
// fix. It tries first (a warning, control_fix_tries or control_fix_minutes), then a trip goes to an admin: what it holds
// is stopped, as narrow as its cause, and only an executive's Resolve clears it (what it stopped starts again once its
// check passes, Undo for a day). A refusal or being out of budget trips at once. Slow calls and jobs, a back-off at its
// limit, the last Pulse cases as alarms. Run: mariadb < ../release-b-tests/start-schema.sql
//      && mariadb lv_main < <2a's ledger migration> && php r3c.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ServiceHealth.php';
require_once $app . '/lib/Automation.php'; require_once $app . '/lib/SearchIndex.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms');
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL, last_probe TEXT NULL,
    resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1), ('second_look', 'W2', 1)");
Automation::set($pdo, 'master', 'all', false, 'test', 7, 'Jason'); Automation::forget();
$on = fn (string $wf) => Automation::wfOff($pdo, $wf) === null;
Automation::setWorkflow($pdo, 'W1', false, 'test', 7, 'Jason'); Automation::setWorkflow($pdo, 'W2', false, 'test', 7, 'Jason'); Automation::forget();

// 1. the system tries first: a crash is a warning for its tries, then a trip
$crash = fn () => ControlAlarms::raise($pdo, 'task:failed:second_look', 'wf:W2', 'trip', 'crash', 'W2: its task second_look failed', 'Its work goes back.', 'Read the why.', 'boom');
$crash(); $a = ControlAlarms::openOf($pdo, 'task:failed:second_look');
$t('a crash: a warning while the system tries again (1 of 3)', $a['level'] === 'warning' && str_contains((string) $a['means'], '1 of 3 tries') && $a['tripped_at'] === null, $a);
$crash(); $t('…the second try still a warning', ControlAlarms::openOf($pdo, 'task:failed:second_look')['level'] === 'warning');
$crash(); Automation::forget(); $a = ControlAlarms::openOf($pdo, 'task:failed:second_look');
$t('…the third: a trip, marked tripped', $a['level'] === 'trip' && $a['tripped_at'] !== null, $a);
$t('…it stopped only its own workflow (W2), not W1', !$on('W2') && $on('W1') && Automation::master($pdo) === null, ControlAlarms::stoppedWords($a));
$t('…and it says what it stopped', ControlAlarms::stoppedWords($a) === 'W2 (its tasks first, then the workflow)', ControlAlarms::stoppedWords($a));
$t('…its reading good again doesn\'t clear it: noted, waiting for Resolve', !ControlAlarms::clear($pdo, 'task:failed:second_look', 'It did its work in run #9.') && (string) ControlAlarms::openOf($pdo, 'task:failed:second_look')['good_how'] === 'It did its work in run #9.');

// 2. the time limit: an hour from the first failure, even with fewer tries
ControlAlarms::raise($pdo, 'job:slowpoke', 'job:slowpoke', 'trip', 'crash', 'The job stopped');
$pdo->exec("UPDATE control_alarms SET first_at = UTC_TIMESTAMP() - INTERVAL 61 MINUTE WHERE akey = 'job:slowpoke'");
ControlAlarms::raise($pdo, 'job:slowpoke', 'job:slowpoke', 'trip', 'crash', 'The job stopped');
$j = ControlAlarms::openOf($pdo, 'job:slowpoke');
$t('an hour of trying is a trip even at 2 tries', $j['level'] === 'trip' && (int) $j['n_times'] === 2, $j);
$t('…a site job tied to no workflow can\'t be stopped from here: it only trips', ControlAlarms::stoppedWords($j) === '');

// 3. nothing to try: a refusal trips at once
ControlAlarms::raise($pdo, 'x:held', 'svc:tavily', 'trip', 'held', 'Tavily refused our key');
$t('a refusal (held) trips at once', ControlAlarms::openOf($pdo, 'x:held')['level'] === 'trip');

// 4. the crawler itself down: the master switch; Resolve starts again only what it stopped, Undo puts it back
foreach ([1, 2, 3] as $i) ControlAlarms::raise($pdo, 'run:failed', 'wk:crawler', 'trip', 'crash', 'A crawler run failed (#' . $i . ')');
Automation::forget(); $r = ControlAlarms::openOf($pdo, 'run:failed');
$t('a run that failed outright, three times: a trip that pauses all gathering', $r['level'] === 'trip' && Automation::master($pdo) !== null, ControlAlarms::stoppedWords($r));
// (4e, Jason 2026-11-19: "no dead ends, system fixes first": Resolve no longer asks what fixed it; r4e tests that)
[$ok, $msg] = ControlAlarms::resolve($pdo, (int) $r['id'], 7, 'Jason', 'Redeployed the worker');
Automation::forget(); $r2 = ControlAlarms::byId($pdo, (int) $r['id']);
$t('…resolved: the master switch is back on, and it says how', $ok && Automation::master($pdo) === null && $r2['cleared_at'] !== null && str_contains((string) $r2['cleared_how'], 'Redeployed the worker') && str_contains((string) $r2['cleared_how'], 'All gathering'), $r2['cleared_how']);
[$ok, $msg] = ControlAlarms::unresolve($pdo, (int) $r['id'], 7, 'Jason');
Automation::forget(); $r3 = ControlAlarms::byId($pdo, (int) $r['id']);
$t('…Undo: open again, and gathering paused again', $ok && $r3['cleared_at'] === null && Automation::master($pdo) !== null, $msg);
ControlAlarms::resolve($pdo, (int) $r['id'], 7, 'Jason', 'Redeployed again'); Automation::forget();

// 5. a check still red: the trip stays, with the reason on its row
$w = ControlAlarms::openOf($pdo, 'task:failed:second_look');
[$ok, $msg] = ControlAlarms::resolve($pdo, (int) $w['id'], 7, 'Jason', 'Fixed its source');
$w2 = ControlAlarms::byId($pdo, (int) $w['id']);
$t('Resolve where W2\'s check is red: not resolved, the reason kept on the row, W2 still off', !$ok && $w2['cleared_at'] === null && str_starts_with((string) $w2['resolve_note'], 'Not resolved') && !$on('W2'), $msg);

// 6. a back-off at its limit: a trip holding the server's calls; a person's resolve closes it
$pdo->exec("DELETE FROM service_issues");
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, hits, first_at, last_at, evidence) VALUES (40, 'tavily', 'api.tavily.com', 'crawler', 'timeout', 6, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{}')");
ServiceHealth::alarm($pdo, 40, 'tavily', 'api.tavily.com', 'timeout', ['status' => 0, 'error' => 'timed out', 'body' => ''], 'crawler');
$s = ControlAlarms::openOf($pdo, 'svc:issue:40');
$t('a back-off that ran out (6 in a row): a trip, its calls held until resolved', $s['level'] === 'trip' && $pdo->query('SELECT hold_until FROM service_issues WHERE id = 40')->fetchColumn() === '2099-01-01 00:00:00', $s);
ServiceHealth::resolve($pdo, 40, 0, 'It answered.');
// Jason, 2026-11-19 ("If this was resolved by system or marked as resolved by admin it should be handled"): the service
// answering (its check passing) closes its trip too, through the board's resolve, saying how
$t('…the server answering closes its trip too, saying "Cleared by its check"', ControlAlarms::openOf($pdo, 'svc:issue:40') === null
    && str_contains((string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'svc:issue:40' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'Cleared by its check'));
$pdo->exec("UPDATE service_issues SET state = 'open', resolved_at = NULL WHERE id = 40");
ServiceHealth::alarm($pdo, 40, 'tavily', 'api.tavily.com', 'timeout', ['status' => 0, 'error' => 'timed out', 'body' => ''], 'crawler');
ServiceHealth::resolve($pdo, 40, 7, 'Fixed by Jason, check passed.');
$t('…and a person\'s fix closes it, saying who', ControlAlarms::openOf($pdo, 'svc:issue:40') === null);
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, hits, first_at, last_at, evidence) VALUES (41, 'tavily', 'api.tavily.com', 'crawler', 'timeout', 2, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{}')");
ServiceHealth::alarm($pdo, 41, 'tavily', 'api.tavily.com', 'timeout', ['status' => 0, 'error' => 'timed out', 'body' => ''], 'crawler');
$t('…while it backs off it\'s a warning, counting its tries', ControlAlarms::openOf($pdo, 'svc:issue:41')['level'] === 'warning' && str_contains((string) ControlAlarms::openOf($pdo, 'svc:issue:41')['means'], '2 of 6'));

// 7. Overpass retired (Jason, 2026-11-19): nothing about it is held for a person
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, hits, first_at, last_at, evidence) VALUES (50, 'osm', 'overpass-api.de', 'crawler', 'ban', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{}')");
ServiceHealth::alarm($pdo, 50, 'osm', 'overpass-api.de', 'ban', ['status' => 406, 'error' => '', 'body' => 'Not Acceptable'], 'crawler');
ServiceHealth::resolve($pdo, 50, 0, 'Its probe answered 200.');
$t('Overpass retired (2026-11-19): a refusal raises no "Every Overpass server is held"; its issue closes as retired', ControlAlarms::openOf($pdo, 'svc:osm:held') === null
    && (string) $pdo->query("SELECT state FROM service_issues WHERE id = 50")->fetchColumn() === 'resolved');
$pdo->exec("UPDATE service_issues SET state = 'open' WHERE id = 50");
ServiceHealth::resolve($pdo, 50, 7, 'Reactivated by Jason after OSM released us.');
$t('…a person\'s Reactivate does', ControlAlarms::openOf($pdo, 'svc:osm:held') === null);

// 8. slow calls at the ledger: a warning (never a trip since 4e); the usual speed clears it
require_once $app . '/lib/ControlLedger.php';
for ($i = 0; $i < 25; $i++) $pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received) VALUES (UTC_TIMESTAMP(), 'crawl/results', 'POST', 200, 300, 1)");   // one record a call, as the test's own calls (5a judges speed per record)
$ref = new ReflectionMethod('ControlLedger', 'alarms'); $ref->setAccessible(true);
$call = fn (int $ms) => $ref->invoke(null, $pdo, 'crawl/results', 200, 1, ['stored' => 1, 'updated' => 0, 'review' => 0, 'refused' => 0], [], $ms);
$call(4000); $sl = ControlAlarms::openOf($pdo, 'ep:crawl/results:slow');
$t('a call 13 times its usual time: slow, a warning', $sl && $sl['level'] === 'warning' && $sl['kind'] === 'slow', $sl);
$call(300); $t('…one at its usual speed clears it', ControlAlarms::openOf($pdo, 'ep:crawl/results:slow') === null);
$call(4000); $call(4000); $call(4000);
$t('…slow three times in a row: still a warning, never a trip (4e: slow never stops anything)', (ControlAlarms::openOf($pdo, 'ep:crawl/results:slow')['level'] ?? '') === 'warning');
$call(900); $t('…under a second is never slow', true);

// 9. the steps the board and Pulse press: only the pairs each family carries
$bad = ''; try { ControlAlarms::opsDo($pdo, 'ops:start:0123456789', 'ops_pf_cancel', [], 7, 'Jason'); } catch (Throwable $x) { $bad = $x->getMessage(); }
$t('a step from another family is refused', $bad === 'That step isn\'t one the board does.', $bad);
$s2 = fn (string $t, string $h, ?array $act = null, bool $auto = false) => ['t' => $t, 'h' => $h, 'act' => $act, 'auto' => $auto];
$x = ControlAlarms::openOf($pdo, 'x:held');
$ps = ControlAlarms::pulseSteps($pdo, (int) $x['id'], $s2);
$t('a trip\'s Pulse item: Resolve as a form asking what fixed it', in_array('alarm_resolve', array_map(fn ($p) => $p['act'][1] ?? '', $ps), true), $ps);
$rr = ControlAlarms::byId($pdo, (int) $r['id']);
$t('…a resolved one offers Undo for a day', in_array(['do', 'alarm_unresolve', 'Undo'], array_column(ControlAlarms::pulseSteps($pdo, (int) $r['id'], $s2), 'act'), true));

// 10. a red required pre-flight line is a trip only while what it holds is on
require_once $app . '/lib/Preflight.php';
Automation::set($pdo, 'master', 'all', true, 'test: stopped', 7, 'Jason'); Automation::forget();
Preflight::alarm($pdo, 'svc:tavily', ['svc', 'Tavily', true], ['s' => 'bad', 'said' => 'Its key is refused.', 'fix' => [], 'extra' => []]);
$pf = ControlAlarms::openOf($pdo, 'pf:svc:tavily');
$t('a red required line with the system stopped: a warning Start holds', $pf && $pf['level'] === 'warning' && str_contains((string) $pf['means'], 'Start holds it'), $pf);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
