<?php
// #304's proof, 2026-11-26: (1) the 10-minute release job put a release on staging while its before look was still running
// (staged at 11:00, the look ran 10:46 to 11:13), so half the "before" look was of the change; (2) when the look came in,
// the check called the staging step again on a release already staged, and wrote "its plan needs a look: Its plan was
// approved already" as a task for a person. Now the job leaves a release waiting for its before look alone, and a release
// already on staging goes straight to its after look.
// Run: php tickwait.php <root>/app   (it resets lv_main; its setup is pipeline.php's)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php pipeline.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$T = dirname(__DIR__) . '/team-tests'; $H = dirname(__DIR__, 2) . '/handoffs/';
$run($T . '/before-schema.sql');
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql', $H . 'piece2-build/team-tickets/build/app/migrations/2026-11-25_tickets.sql', $H . 'piece2-build/team-runner/build/app/migrations/2026-11-25_team_flag.sql'] as $f) $run($f);
$pdo->exec("CREATE TABLE feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, email VARCHAR(190) NOT NULL DEFAULT '', role VARCHAR(20) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'active')");
require_once $app . '/lib/Staging.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Releases.php'; require_once $app . '/lib/BeforeAfter.php'; require_once $app . '/lib/GaugeTasks.php';
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true);
$stage = function (bool $on) use ($here) { putenv('STAGING=' . ($on ? '1' : '0')); $here->setValue(null, null); };
$stage(false);
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$asks = []; $askSays = ''; $files = [];
BeforeAfter::$askFn = function ($e, $p) use (&$asks, &$askSays) { $asks[] = [$e, $p]; return $askSays; };
BeforeAfter::$readFn = function ($path) use (&$files) { return $files[$path] ?? null; };
$verdict = function (int $id, array $v) use (&$files) { $files['site-audit/results/beforeafter/' . $id . '.json'] = json_encode(['release' => (string) $id] + $v); };
$mk = function (string $name, array $plan = []) use ($pdo) {
    $z = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(), 'p32') . '.zip'; $z->open($tmp, ZipArchive::CREATE);
    $z->addFromString('release.json', json_encode($plan + ['title' => $name])); $z->addFromString('app/lib/P32Test' . md5($name) . '.php', "<?php\n// a test file\n"); $z->close();
    $id = (int) Releases::fromUpload($pdo, 0, $tmp, $name, '')['id']; @unlink($tmp);
    Releases::noteFromCheck($pdo, $id, 'Came from git: releases/inbox/' . $name . ' (git 0123456789).');
    return $id; };
$sha = str_repeat('a', 40);
$rel = fn (int $id) => Releases::get($pdo, $id);
$ba = fn (int $id) => BeforeAfter::state($pdo, $id);
require_once $app . '/lib/StagingSite.php'; StagingSite::ensure($pdo);
Switches::set($pdo, 'before_after', 'test', 0, false, 'tickwait test', 1);
$old = fn (int $id) => $pdo->prepare("UPDATE site_releases SET updated_at = NOW() - INTERVAL 20 MINUTE WHERE id = ?")->execute([$id]);
// 1. waiting for its before look, 20 minutes on: the job leaves it
$a = $mk('tw-a.zip'); BeforeAfter::begin($pdo, $a, 'tw-a.zip', $sha, false); $old($a);
$before = $rel($a)['state']; $logN = count($rel($a)['log']);
Releases::tick($pdo);
$t('waiting for its before look, 20 minutes on: the 10-minute job doesn\'t put it on staging', $rel($a)['state'] === $before && $ba($a)['phase'] === 'before', [$before, $rel($a)['state']]);
$t('… and writes nothing on it', count($rel($a)['log']) === $logN, array_slice($rel($a)['log'], $logN));
// 2. one not waiting (the check off when it came in): the job still moves it on, as before
Switches::set($pdo, 'before_after', 'off', 0, false, 'tickwait test', 1);
$b = $mk('tw-b.zip'); $old($b); $logN = count($rel($b)['log']);
Releases::tick($pdo);
$t('a build not waiting on the check: the job still tries to put it on staging (here staging isn\'t set up, and it says so)', count($rel($b)['log']) > $logN && (bool) preg_grep('/not on staging yet|Staging isn\'t set up|on staging/i', array_column(array_slice($rel($b)['log'], $logN), 'msg')), array_column(array_slice($rel($b)['log'], $logN), 'msg'));
Switches::set($pdo, 'before_after', 'test', 0, false, 'tickwait test', 1);
// 3. its before look in, the release already on staging (put there some other way): straight to the after look, no wrong note
$c = $mk('tw-c.zip'); BeforeAfter::begin($pdo, $c, 'tw-c.zip', $sha, false);
$pdo->prepare("UPDATE site_releases SET state = 'staged', checks_ok = 1, checks = ? WHERE id = ?")->execute([json_encode(['ok' => true, 'items' => [['PHP files parse', true, '1 checked']]]), $c]);
$verdict($c, ['phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 60, 'run' => 'https://github.com/degraffics/traversence/actions/runs/2']);
$n0 = count($asks); $logN = count($rel($c)['log']); $out = BeforeAfter::advance($pdo);
$msgs = array_column(array_slice($rel($c)['log'], $logN), 'msg');
$t('its before look in and already on staging: the after look is asked', $ba($c)['phase'] === 'after' && end($asks)[1]['phase'] === 'after' && count($asks) === $n0 + 1, $out);
$t('… with no "its plan needs a look" written on it', !preg_grep('/plan needs a look|approved already/', $msgs), $msgs);
$t('… and its card says the advocates look again', str_contains(Releases::standing($rel($c))[0], 'the advocates are looking again') || (bool) preg_grep('/advocates look again/', $msgs), $msgs);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
