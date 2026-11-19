<?php
// The Control Board's release 4b (decisions/0171 §6): the historian. Each process's state written when it changes and at
// least hourly; each finished hour's counts once; a finished day rolled up into control_daily and old rows trimmed;
// uptime (green or amber against the time it should have been on); trends over 24 hours, 7 days and 30 days, for a
// process and a system summed, drawn as an inline SVG. Also health.php's unused_schema block and Telemetry pointing to
// the board. Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration>
//      && php r4b.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlHistorian.php'; require_once $app . '/lib/ControlBoard.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_readings'); $pdo->exec('DROP TABLE IF EXISTS control_daily');
require_once $app . '/lib/SearchIndex.php'; require_once $app . '/lib/Automation.php';
$pdo->exec("DELETE FROM search_index_state WHERE k IN ('ch:hour', 'ch:day')");
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('second_look', 'W2', 1)");   // W2's route reads its task (crawl/verify)
$T0 = (int) (floor(time() / 86400) * 86400) - 86400 * 2 + 3 * 3600;   // two days ago, 03:00 UTC: a fixed clock
$L = fn (array $m) => array_map(fn ($l) => [$l, []], $m);

// 1. state: written when it changes, and at least once an hour; nothing else
$d = ControlHistorian::tick($pdo, $T0, $L(['wf:W1' => 'ok', 'wf:W2' => 'bad', 'svc:tavily' => 'off']));
$n = fn () => (int) $pdo->query("SELECT COUNT(*) FROM control_readings WHERE reading = 'state'")->fetchColumn();
$t('the first tick writes each process\'s state', $n() === 3, $d);
ControlHistorian::tick($pdo, $T0 + 300, $L(['wf:W1' => 'ok', 'wf:W2' => 'bad', 'svc:tavily' => 'off']));
$t('…5 minutes later, nothing changed: nothing written', $n() === 3);
ControlHistorian::tick($pdo, $T0 + 600, $L(['wf:W1' => 'ok', 'wf:W2' => 'hold', 'svc:tavily' => 'off']));
$t('…a change: only that one written', $n() === 4 && $pdo->query("SELECT state FROM control_readings WHERE reading = 'state' ORDER BY id DESC LIMIT 1")->fetchColumn() === 'hold');
ControlHistorian::tick($pdo, $T0 + 3700, $L(['wf:W1' => 'ok', 'wf:W2' => 'hold', 'svc:tavily' => 'off']));
$t('…an hour on, unchanged: written again (at least hourly; W2 changed 50 minutes ago, so not yet)', $n() === 6);
ControlHistorian::tick($pdo, $T0 + 4000, $L(['wf:W1' => 'maint', 'wf:W2' => 'ok', 'svc:tavily' => 'off']));

// 2. uptime: green or amber against the time it should have been on; off and maintenance don't count
$du = ControlHistorian::durations($pdo, ['wf:W1', 'wf:W2', 'svc:tavily'], $T0, $T0 + 7600);
$t('W2: 600 s red, then held (amber) and ok: up 7000 of 7600', $du['wf:W2']['bad'] === 600 && $du['wf:W2']['up'] === 7000 && $du['wf:W2']['due'] === 7600, $du['wf:W2']);
$t('…its uptime 92.1%', ControlHistorian::uptime($du['wf:W2']) === 92.1, ControlHistorian::uptime($du['wf:W2']));
$t('W1 in maintenance after 4000 s: that time isn\'t due, so uptime stays 100%', $du['wf:W1']['maint'] === 3600 && ControlHistorian::uptime($du['wf:W1']) === 100.0, $du['wf:W1']);
$t('a process switched off all along isn\'t due at all: no uptime, never 0%', ControlHistorian::uptime($du['svc:tavily']) === null);

// 3. each finished hour's counts, once, from the ledger, crawler runs and site jobs
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_run_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id INT NULL, task VARCHAR(40) NOT NULL, outcome VARCHAR(20) NOT NULL, at DATETIME NOT NULL)");
$pdo->exec('DELETE FROM control_ledger'); $pdo->exec('DELETE FROM crawl_run_items');
$pdo->exec("INSERT INTO control_ledger (at, endpoint, code, ms, n_received, n_stored, n_updated, n_review, n_refused) VALUES (UTC_TIMESTAMP() - INTERVAL 90 MINUTE, 'crawl/verify', 200, 120, 10, 6, 1, 1, 2), (UTC_TIMESTAMP() - INTERVAL 80 MINUTE, 'crawl/verify', 500, 300, 0, 0, 0, 0, 0), (UTC_TIMESTAMP() - INTERVAL 10 MINUTE, 'crawl/verify', 200, 100, 5, 5, 0, 0, 0)");
$pdo->exec("INSERT INTO crawl_run_items (task, outcome, at) VALUES ('second_look', 'done', NOW() - INTERVAL 85 MINUTE), ('second_look', 'failed', NOW() - INTERVAL 70 MINUTE)");
$now = time(); $hour = intdiv($now, 3600) * 3600 - 3600;
$c1 = ControlBoard::calls($pdo, ['crawl/verify'], [$now - $hour, $now - $hour - 3600]);
$inHour = fn (string $ago) => strtotime(gmdate('Y-m-d H:i:s', $now - (int) $ago * 60)) >= $hour && strtotime(gmdate('Y-m-d H:i:s', $now - (int) $ago * 60)) < $hour + 3600;
$expect = ($inHour('90') ? 1 : 0) + ($inHour('80') ? 1 : 0) + ($inHour('10') ? 1 : 0);
$t('ControlBoard::calls takes a window: the last full hour only', $c1['calls'] === $expect, [$c1, $expect]);
$pdo->exec("DELETE FROM search_index_state WHERE k = 'ch:hour'");
ControlHistorian::tick($pdo, $now, $L(['wf:W2' => 'ok']));
$h = $pdo->query("SELECT reading, value FROM control_readings WHERE proc = 'wf:W2' AND reading <> 'state' AND at = '" . gmdate('Y-m-d H:i:s', $hour) . "'")->fetchAll(PDO::FETCH_KEY_PAIR);
$t('the hour just ended: W2\'s counts written at the hour\'s start, only what isn\'t zero', $expect === 0 ? !isset($h['calls']) : ((int) $h['calls'] === $expect && !isset($h['spend'])), $h);
$before = (int) $pdo->query("SELECT COUNT(*) FROM control_readings WHERE reading <> 'state'")->fetchColumn();
ControlHistorian::tick($pdo, $now + 60, $L(['wf:W2' => 'ok']));
$t('…once: the next tick in the same hour adds no counts', (int) $pdo->query("SELECT COUNT(*) FROM control_readings WHERE reading <> 'state'")->fetchColumn() === $before);

// 4. a trend: buckets, totals, uptime; a system summed
$pdo->exec('DELETE FROM control_readings');
$base = intdiv(time(), 3600) * 3600;
$ins = $pdo->prepare('INSERT INTO control_readings (proc, reading, value, state, at) VALUES (?, ?, ?, ?, ?)');
$ins->execute(['wf:W1', 'state', 0, 'ok', gmdate('Y-m-d H:i:s', $base - 23 * 3600)]);
$ins->execute(['wf:W1', 'state', 3, 'bad', gmdate('Y-m-d H:i:s', $base - 2 * 3600)]);
$ins->execute(['wf:W1', 'state', 0, 'ok', gmdate('Y-m-d H:i:s', $base - 3600)]);
foreach ([5, 4, 3] as $ago) { $ins->execute(['wf:W1', 'in', 10 * $ago, null, gmdate('Y-m-d H:i:s', $base - $ago * 3600)]); $ins->execute(['wf:W1', 'stored', 8 * $ago, null, gmdate('Y-m-d H:i:s', $base - $ago * 3600)]); $ins->execute(['wf:W1', 'ms', 100 * $ago, null, gmdate('Y-m-d H:i:s', $base - $ago * 3600)]); }
$ins->execute(['wf:W2', 'state', 0, 'ok', gmdate('Y-m-d H:i:s', $base - 23 * 3600)]);
$ins->execute(['wf:W2', 'runs', 7, null, gmdate('Y-m-d H:i:s', $base - 3 * 3600)]);
$tr = ControlHistorian::trend($pdo, 'wf:W1', '24h');
$t('a 24-hour trend: 24 hourly buckets, records in summed, average ms', count($tr['buckets']) === 24 && (int) $tr['totals']['in'] === 120 && (int) $tr['totals']['stored'] === 96 && (int) $tr['totals']['ms'] === 400, $tr['totals']);
$red = array_values(array_filter($tr['buckets'], fn ($b) => $b['state'] === 'down'));
$t('…the hour it was red shows red; its uptime is under 100%', count($red) === 1 && $red[0]['at'] === $base - 2 * 3600 && $tr['uptime'] !== null && $tr['uptime'] < 100 && $tr['uptime'] > 90, [$tr['uptime'], count($red)]);
$t('a 7-day trend: 28 buckets of 6 hours; 30 days: 30 daily', count(ControlHistorian::trend($pdo, 'wf:W1', '7d')['buckets']) === 28 && count(ControlHistorian::trend($pdo, 'wf:W1', '30d')['buckets']) === 30);
$sys = ControlHistorian::trend($pdo, 'sys:engine', '24h');
$t('a system\'s trend sums its processes (W1\'s records, W2\'s runs)', (int) $sys['totals']['in'] === 120 && (int) $sys['totals']['runs'] === 7 && $sys['procs'] > 2, [$sys['totals'], $sys['procs']]);
$html = ControlHistorian::html($tr, 'wf:W1');
$t('…drawn as an inline SVG with words: range buttons, bars, the state strip, an aria label', str_contains($html, '<svg class="cb-trs"') && str_contains($html, 'data-cb-range="7d"') && str_contains($html, 'role="img" aria-label="The last 24 hours: Uptime') && str_contains($html, '120 records in (96 stored, 0 refused)') && str_contains($html, 'var(--cb-fail)'), mb_substr(strip_tags($html), 0, 200));
$t('…no library: nothing loaded', !str_contains($html, '<script') && !str_contains($html, 'src='));

// 5. a finished day rolled up; old rows trimmed
$day = gmdate('Y-m-d', $base - 86400);
$pdo->exec('DELETE FROM control_readings');
$d0 = (int) strtotime($day . ' UTC');
$ins->execute(['wf:W1', 'state', 0, 'ok', gmdate('Y-m-d H:i:s', $d0 - 600)]);
$ins->execute(['wf:W1', 'state', 3, 'bad', gmdate('Y-m-d H:i:s', $d0 + 6 * 3600)]);
$ins->execute(['wf:W1', 'state', 4, 'maint', gmdate('Y-m-d H:i:s', $d0 + 7 * 3600)]);
$ins->execute(['wf:W1', 'state', 1, 'warn', gmdate('Y-m-d H:i:s', $d0 + 8 * 3600)]);
$ins->execute(['wf:W1', 'in', 5, null, gmdate('Y-m-d H:i:s', $d0 + 3600)]); $ins->execute(['wf:W1', 'in', 9, null, gmdate('Y-m-d H:i:s', $d0 + 7200)]);
$ins->execute(['wf:W1', 'in', 1, null, gmdate('Y-m-d H:i:s', $d0 - 3 * 86400)]);   // old
ControlHistorian::rollup($pdo, $day);
$r = $pdo->query("SELECT * FROM control_daily WHERE day = '$day' AND proc = 'wf:W1' AND reading = 'in'")->fetch(PDO::FETCH_ASSOC);
$s = $pdo->query("SELECT * FROM control_daily WHERE day = '$day' AND proc = 'wf:W1' AND reading = 'state'")->fetch(PDO::FETCH_ASSOC);
$t('the day rolled up: a count\'s min, max, last and sum', $r && (float) $r['vmin'] === 5.0 && (float) $r['vmax'] === 9.0 && (float) $r['vlast'] === 9.0 && (float) $r['vsum'] === 14.0, $r);
$t('…its state: an hour red, an hour blue, the rest green or amber; due = 23 hours', $s && (int) $s['red_s'] === 3600 && (int) $s['blue_s'] === 3600 && (int) $s['up_s'] === 79200 && (int) $s['due_s'] === 82800, $s);
require_once $app . '/lib/Thresholds.php';
$t('raw rows are kept control_readings_days (Thresholds & gates, 30 until set)', isset(Thresholds::GATES['control_readings_days']) && ControlHistorian::keepDays($pdo) === 30);
$m = new ReflectionMethod('ControlHistorian', 'trim'); $m->setAccessible(true);
$m->invoke(null, $pdo, $d0 + 30 * 86400 - 86400 * 2);   // as if 28 days on: the row 3 days before the day goes
$t('…older rows trimmed, the day\'s kept', (int) $pdo->query("SELECT COUNT(*) FROM control_readings WHERE at < '" . gmdate('Y-m-d H:i:s', $d0 - 86400) . "'")->fetchColumn() === 0 && (int) $pdo->query("SELECT COUNT(*) FROM control_readings")->fetchColumn() === 6);

// 6. the board's own lights, through the board's rules
$lights = ControlHistorian::lights($pdo);
$t('every process\'s light, after the board\'s rules (shared ControlBoard::boardLight)', count($lights) > 20 && isset($lights['wf:W1']) && in_array($lights['wf:W1'][0], ['ok', 'warn', 'hold', 'bad', 'maint', 'off', 'wait'], true), count($lights));
$cs = file_get_contents($app . '/admin/control-console.php');
$t('the board uses the same rules, and each row and system has a trend that loads when opened', str_contains($cs, 'ControlBoard::boardLight($pdo, $id, $row, $tripped)') && str_contains($cs, 'data-cb-trend="sys:') && str_contains($cs, "tv_cb_kv('Trend'"));
$api = file_get_contents(dirname($app) . '/website/admin/api.php');
$t('the trend endpoint is read only and checks the id', str_contains($api, "if (\$act === 'trend')") && str_contains($api, "preg_match('/^(sys|wf|t|wk|svc|job|site):"));

// 6b. the historian's own time, judged like a slow job (the session manager: a heavy watch shows on the board)
require_once $app . '/lib/ControlAlarms.php';
$pdo->exec("DELETE FROM control_readings WHERE reading = 'hist_ms'");
foreach ([900, 1100, 1000, 950, 1050, 980] as $ms) ControlHistorian::timed($pdo, $ms);
$t('each watch\'s history time is kept (hist_ms on job:control_tick); usual times raise nothing', (int) $pdo->query("SELECT COUNT(*) FROM control_readings WHERE reading = 'hist_ms'")->fetchColumn() === 6 && ControlAlarms::openOf($pdo, 'watch:history:slow') === null);
$t('…over its usual × the slow factor and 20 s: a warning on the board', ControlHistorian::timed($pdo, 45000) && ControlAlarms::openOf($pdo, 'watch:history:slow')['level'] === 'warning');
ControlHistorian::timed($pdo, 1000);
$t('…its usual time again: the warning clears', ControlAlarms::openOf($pdo, 'watch:history:slow') === null);
$t('…and the watch times its history step', str_contains(file_get_contents($app . '/lib/ControlAlarms.php'), 'ControlHistorian::timed($pdo, (int) round((microtime(true) - $h0) * 1000))'));

// 7. the rest of 4b
// health.php's unused_schema only behind its token (the session manager: names map our database): served for real by
// PHP's own server with a HEALTH_TOKEN set for it; untokened and wrong-token requests get no block, the token gets it
$port = 8766; $web = dirname($app) . '/website';
$srv = proc_open(['php', '-d', 'variables_order=EGPCS', '-S', '127.0.0.1:' . $port, '-t', $web], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pp, null, array_merge(getenv(), ['HEALTH_TOKEN' => 'r4b-test-token']));
usleep(700000);
$get = function (array $h) use ($port) { $c = stream_context_create(['http' => ['header' => implode("\r\n", $h), 'ignore_errors' => true, 'timeout' => 20]]); return (string) @file_get_contents('http://127.0.0.1:' . $port . '/api/health.php', false, $c); };
$none = $get([]); $wrong = $get(['Authorization: Bearer nope']); $ok = $get(['Authorization: Bearer r4b-test-token']);
proc_terminate($srv);
$t('health.php without its token: refused, and no unused_schema in the answer', $none !== '' && !str_contains($none, 'unused_schema') && str_contains($none, '"ok":false'), mb_substr($none, 0, 160));
$t('…with a wrong token: the same', !str_contains($wrong, 'unused_schema') && str_contains($wrong, 'Not allowed'), mb_substr($wrong, 0, 160));
$t('…with its token: the block is there', str_contains($ok, '"unused_schema"'), mb_substr($ok, 0, 160));
$hp = file_get_contents(dirname($app) . '/website/api/health.php');
$t('health.php lists the unused names (names, databases and sizes only)', str_contains($hp, "\$out['unused_schema']") && !str_contains(substr($hp, (int) strpos($hp, 'unused_schema')), 'SELECT'));
$mf = file_get_contents($app . '/admin/manifests.php');
$t('Telemetry points to the board', str_contains($mf, "['System Control Board: trends and uptime', '/admin/config/control']"));
require_once $app . '/lib/staging-tables.php';
$t('control_readings and control_daily are each site\'s own on staging', in_array('control_readings', StagingTables::OWN, true) && in_array('control_daily', StagingTables::OWN, true));
$al = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch runs the historian, last', str_contains($al, 'ControlHistorian::tick($pdo)'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
