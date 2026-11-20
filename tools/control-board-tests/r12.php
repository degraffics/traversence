<?php
// control-board-12 (decisions/0171 §12, the session manager's call for Jason, 2026-11-19): each process's own page,
// /admin/config/control/<kind>-<name>, opened from its row as a drawer (its controls, alarms, readings, trend, logbook and
// report), and the board's four tables as sections under it in System Configuration: Alarms (Acknowledge and Check and
// start again through the board's own calls, greyed with the reason), Logbook, Ledger (with the records each call
// touched) and Upkeep. Executives, as the board; nothing new is kept.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r12.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo); $pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, means, todo, reading, n_times, first_at, last_at) VALUES ('x:w1', 'wf:W1', 'warning', 'check', 'W1: something to look at', 'm', 't', 'r', 2, UTC_TIMESTAMP() - INTERVAL 1 HOUR, UTC_TIMESTAMP())");
$aid = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at, cleared_at, cleared_how) VALUES ('x:old', 'wf:W2', 'notice', 'check', 'W2 old', 1, UTC_TIMESTAMP() - INTERVAL 2 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY, 'fine')");
ControlLog::add($pdo, 'note', 'wf:W1', 'Looked at W1', 'Jason (staff)', 'testing');
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received, n_stored, n_refused, reasons, records) VALUES (UTC_TIMESTAMP(), 'crawl/verify', 'POST', 200, 40, 10, 7, 3, 'duplicate: 3', 2)");
$lid = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO control_records (ledger_id, at, kind, ref_id, what) VALUES (?, UTC_TIMESTAMP(), 'listing', 41, 'stored'), (?, UTC_TIMESTAMP(), 'listing', 42, 'stored')")->execute([$lid, $lid]);
$tools = (function () use ($app) { return require $app . '/admin/tools.php'; })();
if (!is_array($tools)) { $tools = $GLOBALS['TV_TOOLS'] ?? (function_exists('tv_admin_tools') ? tv_admin_tools() : []); }
$t('the four tools exist, executives only', isset($tools['config/alarms'], $tools['config/log'], $tools['config/ledger'], $tools['config/upkeep']) && $tools['config/alarms']['view']['min'] === 'executive' && $tools['config/ledger']['view']['min'] === 'executive', array_keys(array_filter($tools, fn ($k) => str_starts_with((string) $k, 'config/'), ARRAY_FILTER_USE_KEY)));
// alarms
[$rows, $n, $counts] = ($tools['config/alarms']['list'])($pdo, []);
$t('Alarms lists the open ones by default, counting each choice', $n === 1 && $rows[0]['id'] === $aid && $counts['cleared'] === 1 && $counts['all'] === 2, [$n, $counts]);
[$rows2] = ($tools['config/alarms']['list'])($pdo, ['show' => 'cleared']);
$t('…and the cleared ones under Cleared', count($rows2) === 1 && $rows2[0]['title'] === 'W2 old');
$d = ($tools['config/alarms']['get'])($pdo, $aid);
$keys = array_column($d['acts'], 'key');
// a warning: no Acknowledge, the system is handling it (0175 R2a part 2, item 10; 0171 §7's 3c note), and no Check and start
// again (it isn't a trip); the drawer says so in Acknowledged
$t('its drawer: what it means, what to do, its process\'s page, and nothing to press on a warning (no Acknowledge, no Check and start again)', $keys === [] && in_array('/admin/config/control/wf-W1', array_column($d['links'], 1), true) && $d['fields'][0][1] === 'm', [$keys, $d['links']]);
$t('…its Acknowledged line says the system is handling it', str_contains((string) array_column($d['fields'], 1, 0)['Acknowledged'], 'the system is handling it'));
$pdo->exec("UPDATE control_alarms SET level = 'notice' WHERE id = " . (int) $aid); $d = ($tools['config/alarms']['get'])($pdo, $aid);
$t('a notice still offers Acknowledge; a disallowed action is greyed with the reason (no role in a test: ControlBoard::can() says why)', array_column($d['acts'], 'key') === ['ack'] && is_string($d['acts'][0]['off']) && ($d['acts'][0]['off'] === '' || strlen($d['acts'][0]['off']) > 5), $d['acts']);
$pdo->exec("UPDATE control_alarms SET level = 'warning' WHERE id = " . (int) $aid);
// log, ledger, upkeep
[$lr] = ($tools['config/log']['list'])($pdo, ['scope' => 'wf:W1']);
$t('Logbook: one process\'s entries, with who and why in the drawer', (bool) array_filter($lr, fn ($r) => $r['title'] === 'Looked at W1' && $r['who'] === 'Jason (staff)'));
[$gr] = ($tools['config/ledger']['list'])($pdo, ['show' => 'refused']);
$g = ($tools['config/ledger']['get'])($pdo, $lid);
$t('Ledger: the calls with refusals, and a call\'s drawer lists the records it touched', count($gr) === 1 && $gr[0]['refused'] === 3 && str_contains((string) $g['fields'][6][1], 'stored listing #41'), $g['fields'][6] ?? null);
[$ur] = ($tools['config/upkeep']['list'])($pdo, []);
$t('Upkeep: what runs next (the nightly pre-flight at least)', (bool) array_filter($ur, fn ($r) => str_contains($r['title'], 'pre-flight')), array_column($ur, 'title'));
// the manifests and the kit
$mf = file_get_contents($app . '/admin/manifests.php'); $kit = file_get_contents(dirname($app) . '/website/js/admin-kit.js');
$t('each is a section under the board in System Configuration', str_contains($mf, "'alarms' => \$W('Control Board: Alarms'") && str_contains($mf, "['table', 'config/log']") && str_contains($mf, "['table', 'config/ledger']") && str_contains($mf, "['table', 'config/upkeep']"));
$t('the kit greys an action with its reason', str_contains($kit, "(a.off ? ' disabled title=\"' + esc(a.off) + '\"' : '')"));
// the process page
$cc = file_get_contents($app . '/admin/control-console.php');
$t('a process\'s own page: its path read, an unknown one says so with a link back, each row links to it as a drawer', str_contains($cc, "#^/admin/config/control/([a-z]{1,5})[-:]") && str_contains($cc, "There\\'s no process called") && str_contains($cc, ">Its own page</a>") && str_contains($cc, 'data-peek-frame>Its own page'));
$t('…it shows its alarms, its row open, its logbook and its report', str_contains($cc, "tv_cb_alarms(\$mineAll") && str_contains($cc, "(\$focus !== '' ? ' open' : '')") && str_contains($cc, "tv_cb_log_rows(\$pdo, \$focus)") && str_contains($cc, "?report=' . \$e(rawurlencode(\$focus))"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
