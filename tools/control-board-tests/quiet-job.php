<?php
// A job that logs only when it has work (Scheduler::QUIET) is never "late" (2026-11-20, Jason's screenshot of Automation:
// "Runs: … last ran 2 days ago, Nov 18, 1:24 AM MST, and is late": system_start_tick, which "does nothing (and logs
// nothing) until the button is pressed"). Its line says it has had nothing to do since; a job that should have run still
// reads late. Run: mariadb < ../release-b-tests/start-schema.sql && php quiet-job.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(strip_tags((string) $got), 0, 400) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/lib/Scheduler.php'; require_once $app . '/admin/job-line.php';
Scheduler::ensure($pdo); $pdo->exec('DELETE FROM job_runs');
$pdo->prepare("INSERT INTO job_runs (job, started_at, finished_at, ok, note) VALUES ('system_start_tick', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 1, 'Start the system: done')")->execute();
$t('system_start_tick is a quiet job (it logs only while Start the system is going)', in_array('system_start_tick', Scheduler::QUIET, true));
$line = tv_admin_job_line($pdo, 'config', 'automation');
$t('Automation\'s line for it isn\'t "late" two days on, and isn\'t red', !str_contains($line, 'is late') && !str_contains($line, 'data-tv-status="bad"'), $line);
$t('…it says it has had nothing to do since', str_contains($line, 'has had nothing to do since'), $line);
$src = (string) file_get_contents($app . '/admin/sections.php');
$t('the Scheduled tasks page\'s Late leaves quiet jobs out too', str_contains($src, "!in_array(\$job, array_merge(Scheduler::QUIET, Scheduler::RETIRED), true);   // a quiet job's silence isn't lateness"));
$pdo->exec('DELETE FROM job_runs');
$pdo->prepare("INSERT INTO job_runs (job, started_at, finished_at, ok, note) VALUES ('control_tick', NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR, 1, '')")->execute();
$jobs = []; foreach (tv_job_pages() as $j => $p) if ($j === 'control_tick') $jobs[] = $p;
if ($jobs) { [$mk, $sk] = explode('/', $jobs[0], 2); $cl = tv_admin_job_line($pdo, $mk, $sk);
    $t('a job that should have run (the board\'s watch, 2 hours quiet) still reads late', str_contains($cl, 'is late'), $cl); }
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
