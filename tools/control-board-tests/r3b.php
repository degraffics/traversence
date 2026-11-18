<?php
// The Control Board's release 3b (decisions/0171 §8; Jason, 2026-11-18): every Overpass server held is ONE trip a person
// resolves, with its steps; 3a's per-server notices fold into it; Mark resolved clears it only when no server refuses
// us; a reading said again doesn't bump the count; the seed raises a server error only when the latest call failed; long
// text is one line with More, never repeated. Run: mariadb < ../release-b-tests/start-schema.sql
//      && mariadb lv_main < <2a's ledger migration> && php r3b.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php'; require $app . '/lib/ServiceHealth.php'; require $app . '/lib/SearchIndex.php'; require $app . '/lib/Automation.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms');
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL,
    resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$ev = json_encode(['latest' => ['status' => 406, 'error' => '', 'body' => 'Not Acceptable']]);
$pdo->prepare("INSERT INTO service_issues (service, host, caller, kind, first_at, last_at, evidence) VALUES ('osm', 'overpass-api.de', 'crawler', 'ban', NOW(), NOW(), ?), ('osm', 'overpass.openstreetmap.fr', 'crawler', 'ban', NOW(), NOW(), ?), ('osm', 'overpass.kumi.systems', 'crawler', 'timeout', NOW(), NOW(), ?)")->execute([$ev, $ev, $ev]);
// 3a's notice for one server, before 3b
ControlAlarms::raise($pdo, 'svc:issue:1', 'svc:osm', 'notice', 'held', 'overpass-api.de is held: Refused us');
SearchIndex::setState($pdo, 'control:alarms_seeded', '1');
ControlAlarms::tick($pdo);
$a = ControlAlarms::openOf($pdo, 'svc:osm:held');
$t('every Overpass server held: ONE trip, its steps as buttons in place', $a && $a['level'] === 'trip' && str_contains((string) $a['title'], '2 refusing us') && count((array) json_decode((string) $a['fix'], true)) === 3 && str_contains((string) $a['fix'], 'osm-statement'), $a);
$t('…3a\'s per-server notice folded into it', ControlAlarms::openOf($pdo, 'svc:issue:1') === null);
ControlAlarms::tick($pdo);
$t('…the watch doesn\'t raise it twice or bump its count', (int) ControlAlarms::openOf($pdo, 'svc:osm:held')['n_times'] === 1);
ControlAlarms::ack($pdo, (int) $a['id'], 7, 'Jason');
$t('…acknowledging doesn\'t close it', ControlAlarms::openOf($pdo, 'svc:osm:held') !== null);
ServiceHealth::resolve($pdo, 1, 7, 'Lifted by FOSSGIS.');
$t('…one server resolved, another still refusing: it stays open', ControlAlarms::openOf($pdo, 'svc:osm:held') !== null);
ServiceHealth::resolve($pdo, 2, 7, 'Lifted.');
$t('…the last refusing server resolved: it clears, saying how', ControlAlarms::openOf($pdo, 'svc:osm:held') === null);
// a reading said again keeps its count
ControlAlarms::raise($pdo, 'ops:cap:x', 'svc:x', 'warning', 'limit', 'X at 75%', '', '', '75%', [], false); ControlAlarms::raise($pdo, 'ops:cap:x', 'svc:x', 'warning', 'limit', 'X at 75%', '', '', '76%', [], false);
$t('a reading said again: last seen moves, the count doesn\'t', (int) ControlAlarms::openOf($pdo, 'ops:cap:x')['n_times'] === 1 && ControlAlarms::openOf($pdo, 'ops:cap:x')['reading'] === '76%');
// the seed: a server error only when the endpoint's latest call failed
$pdo->exec('DELETE FROM control_alarms');
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, reasons) VALUES (UTC_TIMESTAMP(), 'crawl/verify', 'GET', 500, 30, 'answered 500: boom'), (UTC_TIMESTAMP(), 'crawl/verify', 'GET', 200, 30, NULL), (UTC_TIMESTAMP(), 'crawl/results', 'POST', 500, 30, 'answered 500: still')");
SearchIndex::setState($pdo, 'control:alarms_seeded', '');
ControlAlarms::seed($pdo);
$t('the seed: an endpoint whose latest call works isn\'t raised; one still failing is', ControlAlarms::openOf($pdo, 'ep:crawl/verify:5xx') === null && ControlAlarms::openOf($pdo, 'ep:crawl/results:5xx') !== null);
// long text: one line, More opens it in place; a check's other parts on a toggle, the red one not repeated
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; require $app . '/admin/control-console.php';
$long = str_repeat('The indexer sent 125 addresses in one batch; ', 6);
$m = tv_cb_more($long);
$t('long text: one element with More, the text once', substr_count($m, 'The indexer sent 125') === 6 && str_contains($m, 'data-cb-more') && substr_count($m, 'data-cb-clamp') === 1);
$c = tv_cb_check(['s' => 'bad', 'at' => time(), 'extra' => ['parts' => [['Dry run: the test batch: smaller than a full batch.', 'bad'], ['Needs: Read pages ✓.', 'ok'], ['Now: switched on.', null]]]]);
$t('a check: Holds it once; the toggle lists only its other parts', substr_count($c, 'smaller than a full batch') === 1 && str_contains($c, 'Its other 2 parts'), $c);
$o = tv_cb_check(['s' => 'bad', 'at' => time(), 'said' => 'What holds it: Its gate: the switch-on check has no red line for it. ' . $long]);
$t('a line from before parts: no "What holds it" lead-in, said once, and it says to press Check', substr_count($o, 'no red line') === 1 && !str_contains($o, 'What holds it') && str_contains($o, 'press Check'), $o);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
