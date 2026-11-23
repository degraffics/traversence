<?php
// The health report's three sections for the board's step 5 (control-board-plan.md; the session manager, 2026-11-23):
// consistency (app/lib/ControlConsistency.php, the same code as the scenario test's views()), the ledger over 24 hours,
// and the nonprofits' loads. Calls the real /api/health.php through PHP's server on the bench, with HEALTH_TOKEN set.
// Proves: locked without the token; a clean alarm passes every surface; a broken one (a raw id in its title) is reported
// with its id, surface and check; the ledger's counts by endpoint and by workflow; irs_loads' removed counts; nothing is
// sent (no Pulse row, email or push) and nothing about a person is in it.
// Run: mariadb < ../release-b-tests/start-schema.sql && php health-report.php <root>
declare(strict_types=1);
$root = rtrim($argv[1], '/'); $app = $root . '/app';
$env = dirname($root, 2) . '/.env';
$envWas = (string) file_get_contents($env);
file_put_contents($env, preg_replace('/^HEALTH_TOKEN=.*\n?/m', '', $envWas) . "HEALTH_TOKEN=health-test-token\n");
putenv('HEALTH_TOKEN=health-test-token');
require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n — " . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) . "\n"; } };

// the state: a founder; a clean trip through the real raiser; a broken one written as an old raiser did (raw ids)
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
$pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'founder@example.test', 'Jason', 'active', 'founder', '', '')");
ControlAlarms::raise($pdo, 'wf:W1:test', 'wf:W1', 'trip', 'crash', 'W1 Listings: its runs failed', 'Its work waits.', 'Check and start again.', 'TimeoutError after 600 s', [], false, false);
$clean = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'wf:W1:test'")->fetchColumn();
ControlAlarms::raise($pdo, 'job:digest_tick:old', 'job:digest_tick', 'warning', 'crash', 'job:digest_tick: failed', 'It waits.', 'Nothing.', '', [], false, false);
$broken = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'job:digest_tick:old'")->fetchColumn();
// the ledger: two calls to loads (one a 500), one to refresh; one older than a day (left out)
$pdo->exec("CREATE TABLE IF NOT EXISTS control_ledger (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, endpoint VARCHAR(80) NOT NULL, method VARCHAR(6), code INT, ms INT, run_id INT NULL,
  n_sent INT DEFAULT 0, n_received INT DEFAULT 0, n_stored INT DEFAULT 0, n_updated INT DEFAULT 0, n_review INT DEFAULT 0, n_refused INT DEFAULT 0, reasons VARCHAR(500) NULL, records INT DEFAULT 0)");
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, n_received, n_stored, n_updated, n_review, n_refused) VALUES
  (UTC_TIMESTAMP(), 'crawl/loads', 'POST', 200, 800, 500, 480, 10, 5, 5), (UTC_TIMESTAMP(), 'crawl/loads', 'POST', 500, 60000, 500, 0, 0, 0, 0),
  (UTC_TIMESTAMP(), 'crawl/refresh', 'POST', 200, 300, 20, 0, 18, 0, 2), (UTC_TIMESTAMP() - INTERVAL 2 DAY, 'crawl/loads', 'POST', 200, 100, 9, 9, 0, 0, 0)");
// the nonprofits' loads: one full, one that took 300 off
$irs = Database::t('irs_loads');
$pdo->exec("CREATE TABLE IF NOT EXISTS $irs (id INT AUTO_INCREMENT PRIMARY KEY, file VARCHAR(40), started_at DATETIME, finished_at DATETIME NULL, rows_seen INT NULL, removed INT NULL)");
$pdo->exec("INSERT INTO $irs (file, started_at, finished_at, rows_seen, removed) VALUES ('eo_az.csv', '2026-11-01 02:00:00', '2026-11-01 02:20:00', 20000, 0), ('eo_az.csv', '2026-11-15 02:00:00', '2026-11-15 02:21:00', 19700, 300)");
$msgs0 = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comms_messages'")->fetchColumn() ? (int) $pdo->query('SELECT COUNT(*) FROM comms_messages')->fetchColumn() : 0;

$port = 8790 + random_int(0, 50);
$srv = proc_open(['php', '-S', '127.0.0.1:' . $port, '-t', $root . '/website'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pp);
usleep(700000);
$get = function (string $tok) use ($port): array {
    $ch = curl_init('http://127.0.0.1:' . $port . '/api/health.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => $tok !== '' ? ['Authorization: Bearer ' . $tok] : []]);
    $b = (string) curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return [$c, json_decode($b, true), $b];
};
try {
    [$c0] = $get('');
    $t('locked: no token, no report (401)', $c0 === 401, $c0);
    [$c, $d, $raw] = $get('health-test-token');
    $t('the report answers with the token', $c === 200 && is_array($d) && ($d['ok'] ?? false), [$c, mb_substr($raw, 0, 300)]);
    $k = $d['consistency'] ?? [];
    $t('consistency: every alarm checked on its surfaces', ($k['alarms'] ?? 0) >= 2 && ($k['checks'] ?? 0) >= 8, $k);
    $mine = fn (int $id) => array_values(array_filter((array) ($k['mismatches'] ?? []), fn ($m) => (int) $m['id'] === $id));
    $t('…the clean trip passes on the board and in Pulse', $mine($clean) === [], $mine($clean));
    $b = $mine($broken);
    $t('…the broken one is reported: its id, key, the surface and "names" (a raw id)', $b && in_array('names', array_column($b, 'check'), true) && $b[0]['key'] === 'job:digest_tick:old' && in_array('board', array_column($b, 'surface'), true), $b);
    $t('…counted by check', ($k['by_check']['names'] ?? 0) >= 1, $k['by_check'] ?? null);
    $l = $d['ledger'] ?? [];
    $ld = $l['endpoints']['crawl/loads'] ?? [];
    $t('ledger: 24 hours by endpoint (two calls to loads, one a server error; the 2-day-old left out)', ($ld['calls'] ?? 0) === 2 && ($ld['server_errors'] ?? 0) === 1 && ($ld['received'] ?? 0) === 1000 && ($ld['stored'] ?? 0) === 480 && ($ld['refused'] ?? 0) === 5, $ld);
    $t('…and by the workflow its endpoint serves', isset($l['workflows']) && array_sum(array_column($l['workflows'], 'calls')) === 3, $l['workflows'] ?? null);
    $i = $d['irs_loads'] ?? [];
    $t('irs_loads: each load with rows seen and removed; the total removed', count($i['loads'] ?? []) === 2 && ($i['removed_total'] ?? -1) === 300 && ($i['loads'][0]['removed'] ?? null) === 300, $i);
    $msgs1 = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comms_messages'")->fetchColumn() ? (int) $pdo->query('SELECT COUNT(*) FROM comms_messages')->fetchColumn() : 0;
    $t('nothing is sent by reading it (no new Pulse row, email or push)', $msgs1 === $msgs0, [$msgs0, $msgs1]);
    $s3 = json_encode([$d['consistency'] ?? null, $d['ledger'] ?? null, $d['irs_loads'] ?? null]);
    $t('nothing about a person in the three sections (no email address, no founder\'s name)', !str_contains($s3, '@') && !str_contains($s3, 'Jason'), mb_substr($s3, 0, 200));
} finally {
    proc_terminate($srv); file_put_contents($env, $envWas);
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
