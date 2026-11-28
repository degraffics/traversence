<?php
// Piece 3, P3-2's tests on a real MySQL or MariaDB: the site's side of the before-and-after check (BeforeAfter and
// Releases::checkedByAdvocates; handoffs/piece3-check.md §3, §12; decisions/0179's note "Piece 3's decisions"). GitHub is
// stubbed (BeforeAfter::$askFn records each ask; $readFn hands back the verdict file). The switch off: nothing changes. On:
// a release waits for its before look; GitHub not reached is asked again; late is asked again once, then a Work queue
// ticket; before-done puts it on staging (here: staging isn't set up, so it says so and waits); on staging with its
// checks passed, the after look is asked; a block withdraws it and opens its Audit ticket (DB2); a pass with a hold, an
// incomplete look, and a spent budget (DB4) each wait for review; a withdrawn release's wait is dropped; never on staging.
// Run: php pipeline.php <root>/app   (it resets lv_main)
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
// 1. off: as today
$a = $mk('p32-off.zip');
$t('the switch ships off, and off nothing waits: begin() answers nothing', Switches::DEFS['before_after'][2] === 'off' && BeforeAfter::begin($pdo, $a, 'p32-off.zip', $sha, false) === '' && !$ba($a) && !$asks);
Switches::set($pdo, 'before_after', 'on', 0, false, 'p32 test', 1);
// 2. begin
$id = $mk('p32-a.zip');
$line = BeforeAfter::begin($pdo, $id, 'p32-a.zip', $sha, false);
$t('on: it waits for its before look, and GitHub is asked with its release, phase, zip and blob', str_contains($line, 'waiting for its before look') && $asks[0][0] === 'before-after' && $asks[0][1] === ['release' => (string) $id, 'phase' => 'before', 'zip' => 'p32-a.zip', 'blob' => $sha], $asks);
$t('… the Releases page says so (its standing)', str_starts_with(Releases::standing($rel($id))[0], 'Waiting for its before look'), Releases::standing($rel($id)));
$t('… a blob that isn\'t 40 hex characters is never asked for', BeforeAfter::begin($pdo, $mk('p32-bad.zip'), 'x.zip', 'nothex', false) === '');
// 3. GitHub not reached, then asked
$askSays = 'GitHub said 500.'; $b = $mk('p32-b.zip'); BeforeAfter::begin($pdo, $b, 'p32-b.zip', $sha, false);
$t('GitHub not reached: kept with why, and the release says so', (int) $ba($b)['asked_at'] === 0 && $ba($b)['ask_error'] === 'GitHub said 500.' && str_contains(Releases::standing($rel($b))[0], 'Waiting for its before look'));
$askSays = ''; $n0 = count($asks); $out = BeforeAfter::advance($pdo);
$t('… the next run asks again, once it answers', count($asks) === $n0 + 1 && (int) $ba($b)['asked_at'] > 0 && in_array('Release #' . $b . ': asked for its before look.', $out, true), $out);
// 4. late
$d = $ba($id); $d['asked_at'] = time() - 61 * 60; $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba:' . $id, json_encode($d)]);
$n0 = count($asks); $out = BeforeAfter::advance($pdo);
$t('no answer in an hour: asked again, once', count($asks) === $n0 + 1 && (int) $ba($id)['tries'] === 2 && (bool) preg_grep('/asked for its before look again/', $out), $out);
$d = $ba($id); $d['asked_at'] = time() - 121 * 60; $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba:' . $id, json_encode($d)]);
$out = BeforeAfter::advance($pdo); $tk = (int) ($ba($id)['ticket'] ?? 0);
$late = $tk ? GaugeTasks::task($pdo, $tk) : null;
$t('still nothing at two hours: an Audit ticket in the Work queue, named for the release, with the workflow\'s link', $late && $late['ticket'] === 'audit' && str_contains($late['what'], 'didn\'t answer for release #' . $id) && str_contains($late['why'], 'beforeafter.yml'), $late);
$out = BeforeAfter::advance($pdo);
$t('… never a second ticket for it', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE audit_key = 'ba-late:$id'")->fetchColumn() === 1);
// 5. before-done
$verdict($id, ['phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 120, 'run' => 'https://github.com/degraffics/traversence/actions/runs/1']);
$out = BeforeAfter::advance($pdo);
$t('before-done: it goes on staging (here staging isn\'t set up: it says so and waits)', (bool) preg_grep('/its before look is in/', $out) && $ba($id)['phase'] === 'before', $out);
$t('… the run\'s minutes are counted (DB4)', BeforeAfter::budget($pdo)[0] >= 2.0, BeforeAfter::budget($pdo));
$stagedNow = function (int $id) use ($pdo) { $pdo->prepare("UPDATE site_releases SET state = 'staged', checks_ok = 1, checks = ? WHERE id = ?")->execute([json_encode(['ok' => true, 'items' => [['PHP files parse', true, '1 checked']], 'first_fail' => '']), $id]); };
$stagedNow($id); $n0 = count($asks); $out = BeforeAfter::advance($pdo);
$t('on staging with its checks passed: the after look is asked', $ba($id)['phase'] === 'after' && end($asks)[1]['phase'] === 'after' && count($asks) === $n0 + 1, $out);
$t('… the Releases page says the advocates are looking again', str_contains(Releases::standing($rel($id))[0], 'the advocates are looking again'), Releases::standing($rel($id)));
// 6. a block
$verdict($id, ['phase' => 'after-done', 'verdict' => 'block', 'first' => 'Layout or phone: Sideways scroll at 390 (member, /user/trips.php, phone)', 'blocks_total' => 2, 'views' => 40, 'seconds' => 300, 'run' => 'https://github.com/degraffics/traversence/actions/runs/2']);
$out = BeforeAfter::advance($pdo); $r = $rel($id);
$item = array_values(array_filter($r['checks']['items'], fn ($i) => $i[0] === BeforeAfter::ITEM))[0] ?? null;
$t('a block: the checks say "Before and after (advocates)" failed, with the first problem and the report', $item && $item[1] === false && str_contains($item[2], 'Sideways scroll') && str_contains($item[2], 'actions/runs/2') && $r['checks_ok'] === false, $item);
$t('… it withdrew itself (DB2), and its wait is over', $r['state'] === 'withdrawn' && !$ba($id), $r['state']);
$blk = $pdo->query("SELECT * FROM control_gauge_tasks WHERE audit_key = 'ba:$id'")->fetch(PDO::FETCH_ASSOC);
$t('… the failure is in the Work queue: an Audit ticket with the release, the problem and the report', $blk && $blk['ticket'] === 'audit' && str_contains($blk['what'], 'Release #' . $id) && str_contains($blk['why'], 'actions/runs/2') && $blk['owner'] === 'system · before-and-after check', $blk);
$t('… the minutes counted once: the after look less the before looks', abs(BeforeAfter::budget($pdo)[0] - (2.0 + 3.0)) < 0.01, BeforeAfter::budget($pdo));
// 7. a pass held for review; an incomplete look; a spent budget
$go = function (string $name, bool $hold, string $want) use ($mk, $sha, $stagedNow, $verdict, $pdo) {
    $id = $mk($name); BeforeAfter::begin($pdo, $id, $name, $sha, $hold);
    $verdict($id, ['phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 0]); $stagedNow($id); BeforeAfter::advance($pdo);
    $verdict($id, ['phase' => 'after-done', 'verdict' => $want, 'first' => '', 'views' => 12, 'seconds' => 0]);
    return [$id, BeforeAfter::advance($pdo)]; };
[$p, $out] = $go('p32-hold.zip', true, 'pass');
$it = array_values(array_filter($rel($p)['checks']['items'], fn ($i) => $i[0] === BeforeAfter::ITEM))[0] ?? null;
$t('a pass, its release.json asking for review: passed, and it waits for review (never alone)', $it && $it[1] === true && str_contains($it[2], '12 views') && str_starts_with(Releases::lastWordFor($rel($p)), 'Sent for review'), [$it, $out]);
[$q, $out] = $go('p32-incomplete.zip', false, 'incomplete');
$it = array_values(array_filter($rel($q)['checks']['items'], fn ($i) => $i[0] === BeforeAfter::ITEM))[0] ?? null;
$t('incomplete (accounts couldn\'t sign in; ruling 1, 2026-11-28): not counted as a pass; it stays on staging, reaches the Founder as "couldn\'t be judged", with a ticket for the IT Manager (ruling1.php has the three looks)', $it && $it[1] === null && $rel($q)['state'] === 'staged' && Releases::review($pdo, $q) !== null && (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE audit_key = 'ba-incomplete:$q'")->fetchColumn() === 1, [$it, $out]);
BeforeAfter::setBudget($pdo, 1);
[$s, $out] = $go('p32-budget.zip', false, 'pass');
$t('the day\'s minutes spent (DB4; ruling 1, 2026-11-28): no look is asked, it waits unpublished until they reset, and nobody is asked to review it (ruling1.php has the rest)', BeforeAfter::spent($pdo) && (int) $ba($s)['asked_at'] === 0 && str_starts_with((string) $ba($s)['ask_error'], 'today\'s check minutes are spent') && Releases::review($pdo, $s) === null && $rel($s)['state'] !== 'live', $out);
BeforeAfter::setBudget($pdo, null);
// 8. withdrawn meanwhile; staging
$w = $mk('p32-w.zip'); BeforeAfter::begin($pdo, $w, 'p32-w.zip', $sha, false); $pdo->exec("UPDATE site_releases SET state = 'withdrawn' WHERE id = $w");
BeforeAfter::advance($pdo);
$t('a release withdrawn meanwhile: its wait is dropped', !$ba($w));
$x = $mk('p32-st.zip'); BeforeAfter::begin($pdo, $x, 'p32-st.zip', $sha, false);
$stage(true); $t('on staging: nothing moves, nothing is asked', BeforeAfter::advance($pdo) === [] && !BeforeAfter::on($pdo)); $stage(false);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
