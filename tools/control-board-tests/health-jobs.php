<?php
// The health report's jobs (board-phone-header; the session manager, 2026-11-24): its jobs section failed on live for days and
// returned only "jobs: []", taking the refused starts (jobs_missed) and the connection counts with it, so Lead scores'
// missing starts couldn't be seen. Now the refused starts and the connection counts stand apart, and a failed jobs section
// says why (jobs_error). Calls the real /api/health.php through PHP's server on the bench, with HEALTH_TOKEN set; the jobs
// section is made to fail by a job_runs table with no id column. Nothing about a person is in it.
// Run: mariadb < ../release-b-tests/start-schema.sql && php health-jobs.php <root>
declare(strict_types=1);
$root = rtrim($argv[1], '/'); $app = $root . '/app';
$env = dirname($root, 2) . '/.env';
$envWas = (string) file_get_contents($env);
file_put_contents($env, preg_replace('/^HEALTH_TOKEN=.*\n?/m', '', $envWas) . "HEALTH_TOKEN=health-test-token\n");
require $app . '/lib/Database.php'; require $app . '/lib/Scheduler.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n — " . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES), 0, 400) . "\n"; } };
$port = 8840 + random_int(0, 50);
$srv = proc_open(['php', '-S', '127.0.0.1:' . $port, '-t', $root . '/website'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pp);
usleep(700000);
$get = function () use ($port): array {
    $ch = curl_init('http://127.0.0.1:' . $port . '/api/health.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['Authorization: Bearer health-test-token']]);
    $b = (string) curl_exec($ch); curl_close($ch); return [json_decode($b, true), $b];
};
$missFile = Scheduler::missFile(); $missWas = is_file($missFile) ? (string) file_get_contents($missFile) : null;
try {
    Scheduler::missed('sales_tick', 'SQLSTATE[08004] [1040] Too many connections');   // a start the database refused
    [$d] = $get();
    $t('working: the jobs listed, no jobs_error', is_array($d) && !empty($d['jobs']) && !isset($d['jobs_error']), array_keys((array) $d));
    $t('…the refused start kept (jobs_missed, by job, time and why)', ($d['jobs_missed']['last_24h'] ?? 0) >= 1 && in_array('sales_tick', array_column((array) ($d['jobs_missed']['recent'] ?? []), 'job'), true), $d['jobs_missed'] ?? null);
    $pdo->exec('DROP TABLE IF EXISTS job_runs'); $pdo->exec('CREATE TABLE job_runs (job VARCHAR(40), started_at DATETIME)');   // the jobs section now fails (no id)
    [$d, $raw] = $get();
    $t('the jobs section failing: jobs empty, and jobs_error says why', is_array($d) && ($d['jobs'] ?? null) === [] && str_contains((string) ($d['jobs_error'] ?? ''), 'id'), [$d['jobs_error'] ?? null, mb_substr($raw, 0, 200)]);
    $t('…the refused starts still there', ($d['jobs_missed']['last_24h'] ?? 0) >= 1, $d['jobs_missed'] ?? null);
    $t('…and the connection counts', is_array($d['db_connections'] ?? null) && array_key_exists('Threads_connected', $d['db_connections']), $d['db_connections'] ?? null);
    $t('nothing about a person in it (no email address)', !preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $raw));
} finally {
    proc_terminate($srv); file_put_contents($env, $envWas);
    if ($missWas === null) @unlink($missFile); else file_put_contents($missFile, $missWas);
    $pdo->exec('DROP TABLE IF EXISTS job_runs');
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
