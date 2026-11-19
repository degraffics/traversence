<?php
// The Control Board's 4f (the session manager, 2026-11-19, on Claude's issue #19): a passing pre-flight call closes its
// host's open back-off issue (never a refusal or block); a person's "Check it now" goes past a back-off's wait and runs the
// service's check at once; the watch rechecks a site back-off issue nothing calls, hourly. Uses PHP's own server as the
// outside host. Run: mariadb < ../release-b-tests/start-schema.sql && php r4f.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/Preflight.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS service_issues');
$pdo->exec("CREATE TABLE service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL, last_probe TEXT NULL,
    resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$dir = sys_get_temp_dir() . '/r4f-' . getmypid(); @mkdir($dir); file_put_contents($dir . '/ok.php', '<?php echo "{\"ok\":true}";');
$srv = proc_open(['php', '-S', '127.0.0.1:8767', '-t', $dir], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pp); usleep(500000);
$http = new ReflectionMethod('Preflight', 'http'); $http->setAccessible(true);
$held = new ReflectionMethod('Preflight', 'held'); $held->setAccessible(true);
$add = function (string $kind, string $hold) use ($pdo) { $pdo->prepare("INSERT INTO service_issues (service, host, caller, kind, first_at, last_at, hold_until) VALUES ('anthropic', '127.0.0.1', 'site', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)")->execute([$kind, $hold]); return (int) $pdo->lastInsertId(); };
$state = fn (int $id) => (string) $pdo->query('SELECT state FROM service_issues WHERE id = ' . $id)->fetchColumn();

// 1. a timeout whose wait has run out: the next check's answer closes it
$id = $add('timeout', gmdate('Y-m-d H:i:s', time() - 60));
$r = $http->invoke(null, $pdo, 'GET', 'http://127.0.0.1:8767/ok.php', [], null, 'test');
$t('a passing check closes the host\'s open timeout issue', $r[0] === 200 && $state($id) === 'resolved', [$r[0], $state($id)]);

// 2. a timeout still waiting: held, unless a person pressed Check it now
$id = $add('timeout', gmdate('Y-m-d H:i:s', time() + 3600));
$t('a timeout inside its wait holds the check', str_contains((string) $held->invoke(null, $pdo, '127.0.0.1'), 'Held by issue #' . $id));
Preflight::$now = true; $h = $held->invoke(null, $pdo, '127.0.0.1'); $r = $http->invoke(null, $pdo, 'GET', 'http://127.0.0.1:8767/ok.php', [], null, 'test'); Preflight::$now = false;
$t('…a person\'s Check it now goes past the wait, and the answer closes it', $h === null && $r[0] === 200 && $state($id) === 'resolved', [$h, $state($id)]);

// 3. a refusal or block is never passed or closed by the system (principle 1's exception)
$pdo->exec("UPDATE service_issues SET state = 'resolved'");
$id = $add('refused', gmdate('Y-m-d H:i:s', time() + 3600));
Preflight::$now = true; $h = $held->invoke(null, $pdo, '127.0.0.1'); Preflight::$now = false;
$t('a refusal stays held even for Check it now', $h !== null && str_contains($h, 'Held by issue #' . $id), $h);
$pdo->prepare('UPDATE service_issues SET hold_until = NULL WHERE id = ?')->execute([$id]);
$http->invoke(null, $pdo, 'GET', 'http://127.0.0.1:8767/ok.php', [], null, 'test');
$t('…and an answer from that host doesn\'t close a refusal: a person does', $state($id) === 'open');
proc_terminate($srv); @unlink($dir . '/ok.php'); @rmdir($dir);

// 4. the board's Check it now and the watch
$api = file_get_contents(dirname($app) . '/website/admin/api.php');
$t('Check it now on a site back-off issue runs its service\'s check at once and says whether it closed', str_contains($api, "Preflight::\$now = true; \$r = Preflight::run(\$pdo, 'svc:' . \$si['service'], \$who);") && str_contains($api, 'is closed, and what waited on it carries on'));
$al = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch rechecks a site back-off issue nothing calls (its wait over), hourly, two a watch, never a refusal', str_contains($al, "'sh:recheck:' . (int) \$si['id']") && str_contains($al, "(ServiceHealth::KINDS[\$si['kind']][1] ?? '') !== 'back'") && str_contains($al, 'ServiceHealth::holding($si)'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
