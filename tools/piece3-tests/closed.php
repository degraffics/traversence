<?php
// The before look while staging is closed (2026-11-26, the session manager's case: Jason's Try again copy closes staging
// for about 30 minutes, and every page answers 503). The site asks for no look of a closed staging: the release waits and
// says why ("Waiting for its before look: staging is closed until a fresh copy finishes…"), the 10-minute step asks once
// it opens; a copy that stopped part way is closed too. Never withdrawn, never published. The verdict's own guard (an
// unusable before look is incomplete, never a block) is compare-closed.test.mjs.
// Run: php closed.php <root>/app   (it resets lv_main; its setup is pipeline.php's)
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
$put = function (string $k, ?array $v) use ($pdo) { if ($v === null) { $pdo->prepare('DELETE FROM staging_state WHERE k = ?')->execute([$k]); return; } $pdo->prepare('REPLACE INTO staging_state (k, v, updated_at) VALUES (?, ?, NOW())')->execute([$k, json_encode($v)]); };
Switches::set($pdo, 'before_after', 'test', 0, false, 'closed test', 1);   // step 1, as P3-6 left it: on since #303
// 1. a copy going in: begin asks nothing, the release says why
$put('refresh', ['id' => 'x', 'started_at' => date('c'), 'steps' => [['begin'], ['finish']], 'pos' => 1, 'log' => [], 'updated' => time()]);
$id = $mk('closed-a.zip'); $line = BeforeAfter::begin($pdo, $id, 'closed-a.zip', $sha, false);
$t('a copy going in: GitHub is not asked for a look at a closed staging', $asks === [] && (int) $ba($id)['asked_at'] === 0, $asks);
$st = Releases::standing($rel($id))[0];
$t('… the release says it waits for staging to open, not that GitHub failed', str_contains($st, 'Waiting for its before look: staging is closed until a fresh copy finishes') && !str_contains($st, "GitHub wasn't asked"), $st);
$t('… the Releases page line says the same', str_contains(BeforeAfter::words($pdo, $id), 'staging is closed until a fresh copy finishes'), BeforeAfter::words($pdo, $id));
$out = BeforeAfter::advance($pdo); $out = BeforeAfter::advance($pdo);
$t('… the 10-minute step keeps waiting while it\'s closed: nothing asked, no second note', $asks === [] && count(array_filter($rel($id)['log'], fn ($l) => str_contains($l['msg'], 'staging is closed'))) === 1, $out);
$t('… never withdrawn, never published', !in_array($rel($id)['state'], ['withdrawn', 'live', 'vetted'], true), $rel($id)['state']);
// 2. a copy that stopped part way: still closed
$put('refresh', ['id' => 'x', 'started_at' => date('c'), 'steps' => [['begin'], ['finish']], 'pos' => 1, 'log' => [], 'updated' => time(), 'finished_at' => date('c'), 'ok' => false, 'message' => 'stopped']);
$put('last', ['at' => date('c'), 'ok' => false, 'message' => 'stopped']);
BeforeAfter::advance($pdo);
$t('a copy that stopped part way: still nothing asked', $asks === []);
// 3. staging opens (a copy finished): the next step asks
$put('refresh', ['id' => 'y', 'started_at' => date('c'), 'steps' => [['begin'], ['finish']], 'pos' => 2, 'log' => [], 'updated' => time(), 'finished_at' => date('c'), 'ok' => true]);
$put('last', ['at' => date('c'), 'ok' => true]);
$out = BeforeAfter::advance($pdo);
$t('staging open: the 10-minute step asks for its before look, with its release and blob', count($asks) === 1 && $asks[0][1]['release'] === (string) $id && $asks[0][1]['phase'] === 'before' && $asks[0][1]['blob'] === $sha, $out);
$t('… and the release now says it\'s been asked', str_contains(BeforeAfter::words($pdo, $id), 'asked 0 min ago'), BeforeAfter::words($pdo, $id));
$t('… its card says asked for now, no longer closed', str_starts_with(Releases::standing($rel($id))[0], 'Waiting for its before look: asked for now'), Releases::standing($rel($id))[0]);
// 4. open from the start: asked at once, as before
$b = $mk('closed-b.zip'); BeforeAfter::begin($pdo, $b, 'closed-b.zip', $sha, false);
$t('staging open from the start: asked at once, as P3-2 built it', count($asks) === 2 && (int) $ba($b)['asked_at'] > 0);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
