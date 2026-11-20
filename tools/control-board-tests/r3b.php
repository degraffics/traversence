<?php
// The Control Board's release 3b (decisions/0171 §8; Jason, 2026-11-18). Its Overpass trip is retired with Overpass
// (Jason, 2026-11-19, "Stop entirely"): the watch closes every OpenStreetMap issue and never raises "Every Overpass
// server is held"; a reading said again doesn't bump the count; the seed raises a server error only when the latest call failed; long
// text is one line with a toggle (▸), never "…" and never repeated; an alarm's buttons the same on the board and in Pulse. Run: mariadb < ../release-b-tests/start-schema.sql
//      && mariadb lv_main < <2a's ledger migration> && php r3b.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php'; require $app . '/lib/ServiceHealth.php'; require $app . '/lib/SearchIndex.php'; require $app . '/lib/Automation.php';
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
// Overpass retired (Jason, 2026-11-19, "Stop entirely"): the watch closes every OpenStreetMap issue and never raises
// "Every Overpass server is held"; 3a's per-server notice closes with them
$t('Overpass retired: the watch raises no "Every Overpass server is held"', ControlAlarms::openOf($pdo, 'svc:osm:held') === null);
$t('…and closes the OpenStreetMap servers\' issues and their notices as retired', ControlAlarms::openOf($pdo, 'svc:issue:1') === null
    && (int) $pdo->query("SELECT COUNT(*) FROM service_issues WHERE service = 'osm' AND state = 'open'")->fetchColumn() === 0
    && str_starts_with((string) $pdo->query("SELECT resolved_how FROM service_issues WHERE id = 1")->fetchColumn(), 'Retired: we no longer use Overpass'));
ControlAlarms::raise($pdo, 'svc:osm:held', 'svc:osm', 'trip', 'held', 'Every Overpass server is held (2 refusing us)', '', '', '', [], true, false);
ControlAlarms::tick($pdo);
$t('…an old "Every Overpass server is held" left open closes on the next watch, saying why', ControlAlarms::openOf($pdo, 'svc:osm:held') === null
    && str_starts_with((string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'svc:osm:held' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'Retired: we no longer use Overpass'));
$pdo->exec("INSERT INTO service_issues (service, host, caller, kind, first_at, last_at, evidence) VALUES ('osm', 'overpass.kumi.systems', 'crawler', 'ban', NOW(), NOW(), '{}')");
ServiceHealth::alarm($pdo, (int) $pdo->lastInsertId(), 'osm', 'overpass.kumi.systems', 'ban', ['status' => 406, 'error' => '', 'body' => 'Not Acceptable'], 'crawler');
$t('…a late refusal raises nothing and closes at once', ControlAlarms::openOf($pdo, 'svc:osm:held') === null && (int) $pdo->query("SELECT COUNT(*) FROM service_issues WHERE service = 'osm' AND state = 'open'")->fetchColumn() === 0);
$t('…no Overpass server is handed out; the full list marks each retired', ServiceHealth::servers($pdo, 'osm', 'overpass') === []
    && !array_filter(ServiceHealth::servers($pdo, 'osm', 'overpass', true), fn ($s) => empty($s['retired']) || !(int) $s['paused']));
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
$t('long text: one toggle (▸), the text once, no "…" and no More', substr_count($m, 'The indexer sent 125') === 6 && str_contains($m, 'aria-expanded="false"') && substr_count($m, 'data-cb-clamp') === 1 && !str_contains($m, '…') && !str_contains($m, '>More<'));
$t('short text: as it is, no toggle', tv_cb_more('Short.') === 'Short.');
$c = tv_cb_check(['s' => 'bad', 'at' => time(), 'extra' => ['parts' => [['Dry run: the test batch: smaller than a full batch.', 'bad'], ['Needs: Read pages ✓.', 'ok'], ['Now: switched on.', null]]]]);
$t('a check: Holds it once; the toggle lists only its other parts', substr_count($c, 'smaller than a full batch') === 1 && str_contains($c, 'Its other 2 parts'), $c);
$c2 = tv_cb_check(['s' => 'bad', 'at' => time(), 'extra' => ['parts' => [['Railway: ' . $long, 'bad'], ['Needs: Read pages ✓.', 'ok']]]]);
$t('…a long one: "Holds it:" sits inside its toggle\'s line', (bool) preg_match('/<span class="cb-tgt" data-cb-more><b class="cb-bad">Holds it:<\/b> Railway/u', $c2), $c2);
$o = tv_cb_check(['s' => 'bad', 'at' => time(), 'said' => 'What holds it: Its gate: the switch-on check has no red line for it. ' . $long]);
$t('a line from before parts: no "What holds it" lead-in, said once, and it says to press Check', substr_count($o, 'no red line') === 1 && !str_contains($o, 'What holds it') && str_contains($o, 'press Check'), $o);
// no contact while Overpass is held (Jason, 2026-11-18): no probe goes out for an OpenStreetMap refusal; other services' do
$pdo->exec('DELETE FROM service_issues');
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, first_at, last_at, evidence, probe_url, probe_every_min, probe_agent, next_probe_at) VALUES
    (10, 'osm', 'overpass-api.de', 'crawler', 'ban', NOW(), NOW(), '{}', 'https://overpass-api.de/api/status', 1440, 'as_refused', UTC_TIMESTAMP() - INTERVAL 1 HOUR),
    (11, 'tavily', 'api.tavily.com', 'crawler', 'quota', NOW(), NOW(), '{}', 'https://api.tavily.com/usage', 360, '', UTC_TIMESTAMP() - INTERVAL 1 HOUR),
    (12, 'osm', 'overpass.kumi.systems', 'crawler', 'timeout', NOW(), NOW(), '{}', 'https://overpass.kumi.systems/api/status', 360, '', UTC_TIMESTAMP() - INTERVAL 1 HOUR)");
$due = array_column(ServiceHealth::dueProbes($pdo, 'crawler'), 'id');
$t('held Overpass: no probe goes out (not even its status page); another service\'s and a timeout\'s still do', !in_array(10, $due, true) && in_array(11, $due, true) && in_array(12, $due, true), $due);
$t('…and none is scheduled again for it', $pdo->query('SELECT next_probe_at FROM service_issues WHERE id = 10')->fetchColumn() === null);
$t('…Check it now sends nothing for it', ServiceHealth::probeSoon($pdo, 10) === false && $pdo->query('SELECT next_probe_at FROM service_issues WHERE id = 10')->fetchColumn() === null);
$t('…the rule: an OpenStreetMap refusal only', ServiceHealth::noProbe(['service' => 'osm', 'kind' => 'ban']) && ServiceHealth::noProbe(['service' => 'osm', 'kind' => 'quota']) && !ServiceHealth::noProbe(['service' => 'osm', 'kind' => 'timeout']) && !ServiceHealth::noProbe(['service' => 'tavily', 'kind' => 'ban']));
// an alarm's buttons: whole buttons dropped to fit, never a cut list; its Pulse item has the same buttons, acting in place
$pdo->exec('DELETE FROM control_alarms');
$many = []; for ($i = 0; $i < 4; $i++) $many[] = ['Step ' . $i . ' ' . str_repeat('x', 80), 'ops', 'ops_runnext:t' . $i, str_repeat('y', 40)];
ControlAlarms::raise($pdo, 'ops:empty:t0', 'wf:W1', 'warning', 'check', 'W1 t0 returns empty', '', '', '', $many, false);
$fx = json_decode((string) ControlAlarms::openOf($pdo, 'ops:empty:t0')['fix'], true);
$t('a long list of buttons: whole ones dropped, still a list', is_array($fx) && count($fx) >= 1 && count($fx) < 4, ControlAlarms::openOf($pdo, 'ops:empty:t0')['fix']);
$id = (int) ControlAlarms::raise($pdo, 'ops:cap:tavily', 'svc:tavily', 'warning', 'limit', 'Tavily at 95%', 'm', 'd', '95%', [['Raise the cap', 'opsask', 'ops_cap:tavily_cap', ['Tavily cap (searches)', '1000']], ['Pause it', 'ops', 'ops_pause:tavily', 'Pause Tavily?'], ['Its register entry', 'peek', '/admin/config/register#svc-tavily']], false);
$s = fn (string $t, string $h, ?array $act = null, bool $auto = false) => ['t' => $t, 'h' => $h, 'act' => $act, 'auto' => $auto];
$ps = ControlAlarms::pulseSteps($pdo, $id, $s);
// decisions/0176 R3: Fix it first (the card's Caps and budget, in the case's panel), then the item's own steps
$t('its Pulse item: Fix it on the card\'s caps first', ($ps[0]['t'] ?? '') === 'Fix it' && $ps[0]['act'] === ['tool', '/admin/config/register#svc-tavily-caps'], $ps[0] ?? null);
array_shift($ps);
$t('its Pulse item: Raise the cap as a form, Pause it as a button, its entry and the board beside it', count($ps) === 4 && $ps[0]['act'][0] === 'form' && $ps[0]['act'][1] === 'alarm_ops_cap:tavily_cap' && $ps[0]['act'][3][0][3] === '1000' && $ps[1]['act'] === ['do', 'alarm_ops_pause:tavily', 'Pause it'] && $ps[2]['act'][0] === 'tool' && str_contains((string) $ps[3]['act'][1], 'cb-alarm-' . $id), $ps);
$bad = ''; try { ControlAlarms::pulseDo($pdo, $id, 'alarm_ops_runnext:../x', [], 1, 'T'); } catch (Throwable $x) { $bad = $x->getMessage(); }
$t('…a step it doesn\'t carry is refused', $bad === 'That step isn\'t one the board does.', $bad);
$sv = (int) ControlAlarms::raise($pdo, 'svc:issue:3', 'svc:osm', 'warning', 'retry', 'kumi times out');
$t('…an outside service\'s alarm never offers Mark resolved in Pulse (Jason, 2026-11-19: "it should be the result of an issue being fixed")', !in_array(['do', 'alarm_svcresolve:3', 'Mark resolved'], array_column(ControlAlarms::pulseSteps($pdo, $sv, $s), 'act'), true));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
