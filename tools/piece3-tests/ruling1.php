<?php
// Jason's ruling 1 (2026-11-28: "I shouldnt" be required to review these): releases wait for the Founder only for a high-risk
// territory or a migration that changes or removes rows (its data_reason), or when release.json asks for review. (1) An
// incomplete after look (the test accounts couldn't sign in) is asked again, 3 looks in all, each known by its run; still
// incomplete, the release stays on staging, unpublished, with an Audit ticket for the IT Manager, and only then reaches the
// Founder, in an email that says it couldn't be judged: never live unchecked. A later look that passes or blocks is acted on as ever. (2) Adding rows no longer holds a release; UPDATE
// or DELETE does. (3) The review email says "its checks passed" only when the before-and-after check passed. DB4: once the day's
// check minutes are spent no look is asked for and nobody is emailed. The release waits on staging until they reset.
// Run: php ruling1.php <root>/app   (it resets lv_main; its setup is pipeline.php's)
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
$stagedNow = function (int $id) use ($pdo) { $pdo->prepare("UPDATE site_releases SET state = 'staged', checks_ok = 1, checks = ? WHERE id = ?")->execute([json_encode(['ok' => true, 'items' => [['PHP files parse', true, '1 checked']], 'first_fail' => '']), $id]); };
Switches::set($pdo, 'before_after', 'on', 0, false, 'ruling1 test', 1);
try { $pdo->exec("ALTER TABLE control_gauge_tasks ADD COLUMN lane VARCHAR(40) NULL"); } catch (Throwable $e) {}   // R2's column
$run1 = 'https://github.com/degraffics/traversence/actions/runs/';
$item = fn (int $id) => array_values(array_filter((array) ($rel($id)['checks']['items'] ?? []), fn ($i) => $i[0] === BeforeAfter::ITEM))[0] ?? null;
$toAfter = function (string $name, bool $hold = false) use ($mk, $sha, $stagedNow, $verdict, $pdo) {
    $id = $mk($name); BeforeAfter::begin($pdo, $id, $name, $sha, $hold);
    $verdict($id, ['phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 0]); $stagedNow($id); BeforeAfter::advance($pdo);
    return $id; };
$look = function (int $id, string $what, string $run, array $more = []) use ($verdict, $pdo) { $verdict($id, ['phase' => 'after-done', 'verdict' => $what, 'first' => '', 'views' => 12, 'seconds' => 60, 'run' => $run] + $more); return BeforeAfter::advance($pdo); };
$tickets = fn (int $id) => $pdo->query("SELECT * FROM control_gauge_tasks WHERE audit_key = 'ba-incomplete:$id'")->fetchAll(PDO::FETCH_ASSOC);

// ---- (1) an incomplete look: asked again, three looks in all
$a = $toAfter('r1-a.zip'); $n0 = count($asks);
$out = $look($a, 'incomplete', $run1 . '11');
$t('look 1 incomplete: asked for its after look again, with its release, zip and blob', count($asks) === $n0 + 1 && end($asks)[1] === ['release' => (string) $a, 'phase' => 'after', 'zip' => 'r1-a.zip', 'blob' => $sha] && (bool) preg_grep('/incomplete look \(1 of 3\)/', $out), $out);
$t('… it still waits on the check, on staging, unpublished', $ba($a)['phase'] === 'after' && $ba($a)['seen_runs'] === [$run1 . '11'] && $rel($a)['state'] === 'staged');
$t('… nobody is asked to review it, and no ticket yet', Releases::review($pdo, $a) === null && $tickets($a) === [] && $item($a) === null);
$t('… its card says which look it was and that it was asked again', (bool) preg_grep('/look 1 of 3\. Asked again/', array_column((array) $rel($a)['log'], 'msg')));
$n0 = count($asks); $out = BeforeAfter::advance($pdo);
$t('the same verdict still on main isn\'t read as the new look: nothing asked, nothing decided', count($asks) === $n0 && $ba($a)['seen_runs'] === [$run1 . '11'] && $out === [], $out);
$out = $look($a, 'incomplete', $run1 . '12');
$t('look 2 incomplete: asked once more (2 of 3)', count($asks) === $n0 + 1 && count($ba($a)['seen_runs']) === 2 && Releases::review($pdo, $a) === null && $tickets($a) === []);
$n0 = count($asks); $out = $look($a, 'incomplete', $run1 . '13');
$tk = $tickets($a);
$t('look 3 incomplete: not asked a fourth time; one Audit ticket for the IT Manager', count($asks) === $n0 && count($tk) === 1 && $tk[0]['ticket'] === 'audit' && str_contains($tk[0]['what'], 'couldn\'t judge release #' . $a . ' in 3 looks') && str_contains($tk[0]['why'], 'For the IT Manager (11)') && str_contains($tk[0]['why'], 'beforeafter.yml') && str_contains($tk[0]['why'], 'runs/13'), $tk);
$t('… the ticket is in the IT Manager\'s lane', ($tk[0]['lane'] ?? '') === 'it-manager');
$t('… the release stays on staging, unpublished, and only now reaches the Founder for review', $rel($a)['state'] === 'staged' && Releases::review($pdo, $a) !== null && $rel($a)['live_at'] === null, $rel($a)['state']);
$t('… its checks say the advocates didn\'t judge it (not a pass), and its wait is over', $item($a) && $item($a)[1] === null && !$ba($a));
$t('… its card says so in words: stays on staging, waits for the Founder, a ticket for the IT Manager', (bool) preg_grep('/couldn\'t judge it in 3 looks.*stays on staging, unpublished.*waits for the Founder.*IT Manager/', array_column((array) $rel($a)['log'], 'msg')));
$t('… the line for the run names the task', (bool) preg_grep('/stays on staging, unpublished\. Task #' . (int) $tk[0]['id'] . '/', $out), $out);
BeforeAfter::advance($pdo);
$t('… and never a second ticket', count($tickets($a)) === 1);

// ---- a later look that judges it is acted on as ever
$b = $toAfter('r1-b.zip'); $look($b, 'incomplete', $run1 . '21'); $out = $look($b, 'pass', $run1 . '22');
$t('incomplete, then a pass on the second look: passed and published by itself, as a pass always is', $rel($b)['state'] === 'live' && $tickets($b) === [] && !$ba($b) && Releases::review($pdo, $b) === null && (bool) preg_grep('/before-and-after check: passed/', array_column((array) $rel($b)['log'], 'msg')), [$rel($b)['state'], $out]);
$c = $toAfter('r1-c.zip'); $look($c, 'incomplete', $run1 . '31'); $out = $look($c, 'block', $run1 . '32', ['first' => 'Layout or phone: Sideways scroll at 390', 'blocks_total' => 1]);
$t('incomplete, then a block: it withdraws itself with its ticket, as a block always does', $rel($c)['state'] === 'withdrawn' && $item($c)[1] === false && (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE audit_key = 'ba:$c'")->fetchColumn() === 1, $out);
$d = $toAfter('r1-d.zip'); $n0 = count($asks); $out = $look($d, 'incomplete', '');
$t('an incomplete look that names no run can\'t be told from the next: no loop, straight to the ticket, on staging', count($asks) === $n0 && count($tickets($d)) === 1 && $rel($d)['state'] === 'staged' && Releases::review($pdo, $d) !== null, $out);
$e = $toAfter('r1-e.zip', true); $out = $look($e, 'pass', $run1 . '41');
$t('a pass whose release.json asks for review still waits for the Founder\'s review', $item($e)[1] === true && Releases::review($pdo, $e) !== null && $rel($e)['state'] === 'staged', $out);
$f = $toAfter('r1-f.zip', true); $look($f, 'incomplete', $run1 . '51'); $look($f, 'incomplete', $run1 . '52'); $look($f, 'incomplete', $run1 . '53');
$t('before its last look nobody is asked: two incomplete looks send no review', (function () use ($toAfter, $look, $run1, $pdo) { $g = $toAfter('r1-g.zip'); $look($g, 'incomplete', $run1 . '61'); $look($g, 'incomplete', $run1 . '62'); return Releases::review($pdo, $g) === null; })() && Releases::review($pdo, $f) !== null && count($tickets($f)) === 1 && $rel($f)['state'] === 'staged');
$t('only the release the advocates passed is live: none went live unchecked', array_map('intval', $pdo->query("SELECT id FROM site_releases WHERE state = 'live'")->fetchAll(PDO::FETCH_COLUMN)) === [$b]);
$liveBefore = [$b];

// ---- DB4: the day's minutes spent. No new look, no email; it waits on staging until they reset
$pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba_used', json_encode(['day' => gmdate('Y-m-d'), 'minutes' => 0])]);
BeforeAfter::setBudget($pdo, 5);
$p1 = $toAfter('r1-min-a.zip'); $n0 = count($asks);
$pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba_used', json_encode(['day' => gmdate('Y-m-d'), 'minutes' => 6])]);
$out = $look($p1, 'pass', $run1 . '71');
$t('a look that passed while the minutes ran out: checked, so it publishes as any other, nobody asked to review it', BeforeAfter::spent($pdo) && $rel($p1)['state'] === 'live' && Releases::review($pdo, $p1) === null, [$rel($p1)['state'], $out]);
$p2 = $mk('r1-min-b.zip'); $n0 = count($asks); $line = BeforeAfter::begin($pdo, $p2, 'r1-min-b.zip', $sha, false);
$t('minutes spent: a new release\'s before look is not asked for', count($asks) === $n0 && (int) $ba($p2)['asked_at'] === 0 && preg_match('/^today\'s check minutes are spent \(\d+(\.\d)? of 5 used\); its look is asked for once they reset, at 00:00 UTC$/', (string) $ba($p2)['ask_error']) === 1, $ba($p2));
$t('… the Releases page says why it waits and when it resumes', str_contains(BeforeAfter::words($pdo, $p2), 'today\'s check minutes are spent') && str_contains(BeforeAfter::words($pdo, $p2), 'once they reset, at 00:00 UTC') && !str_contains(BeforeAfter::words($pdo, $p2), 'GitHub not asked'), BeforeAfter::words($pdo, $p2));
$out = BeforeAfter::advance($pdo);
$t('… the 10-minute step asks nothing while they are spent; nobody is emailed; it is not published', count($asks) === $n0 && Releases::review($pdo, $p2) === null && $rel($p2)['state'] !== 'live' && $rel($p2)['state'] !== 'staged');
$pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba_used', json_encode(['day' => gmdate('Y-m-d', time() - 86400), 'minutes' => 6])]);   // the next UTC day: the count starts again
$out = BeforeAfter::advance($pdo);
$t('after the reset: its before look is asked for, and it goes on as any release', !BeforeAfter::spent($pdo) && count($asks) === $n0 + 1 && end($asks)[1]['phase'] === 'before' && (int) $ba($p2)['asked_at'] > 0, $out);
$verdict($p2, ['phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 0]); $stagedNow($p2);
$pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba_used', json_encode(['day' => gmdate('Y-m-d'), 'minutes' => 9])]);
$n0 = count($asks); $out = BeforeAfter::advance($pdo);
$t('minutes spent between its two looks: on staging, its after look not asked, unpublished, said on its card', count($asks) === $n0 && $ba($p2)['phase'] === 'after' && (int) $ba($p2)['asked_at'] === 0 && $rel($p2)['state'] === 'staged' && Releases::review($pdo, $p2) === null && (bool) preg_grep('/Waiting for its after look: today\'s check minutes are spent.*stays unpublished until then/', array_column((array) $rel($p2)['log'], 'msg')), $out);
BeforeAfter::setBudget($pdo, null); $out = BeforeAfter::advance($pdo);
$t('… and once there are minutes again its after look is asked', count($asks) === $n0 + 1 && end($asks)[1]['phase'] === 'after' && (int) $ba($p2)['asked_at'] > 0, $out);
$out = $look($p2, 'pass', $run1 . '72');
$t('… then checked and published as normal', $rel($p2)['state'] === 'live' && Releases::review($pdo, $p2) === null, $out);
$t('no budget set: nothing is ever held for minutes', BeforeAfter::minutesHold($pdo) === '');

// ---- (2) only a migration that changes or removes rows waits as data
require_once $app . '/lib/ReleaseKind.php';
$m = fn (string $sql) => ['app/migrations/2026-11-28_x.sql' => [$sql, null]];
$t('adding rows is not a data hold', !ReleaseKind::changesRows($m("INSERT INTO t (a) SELECT 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM t)")) && !ReleaseKind::changesRows($m("CREATE TABLE IF NOT EXISTS t (a INT)")));
$t('an upsert\'s ON DUPLICATE KEY UPDATE and a column\'s ON UPDATE aren\'t a change of rows', !ReleaseKind::changesRows($m("INSERT INTO t (a) VALUES (1) ON DUPLICATE KEY UPDATE a = 1")) && !ReleaseKind::changesRows($m("CREATE TABLE t (at DATETIME ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (a) REFERENCES u (a) ON DELETE CASCADE)")));
$t('UPDATE or DELETE is', ReleaseKind::changesRows($m("UPDATE t SET a = 2 WHERE a = 1")) && ReleaseKind::changesRows($m("DELETE FROM t WHERE a = 1")) && ReleaseKind::changesRows($m("INSERT INTO t (a) VALUES (1);\nUPDATE t SET a = 2")));
$t('only a migration counts, never a page with the word in it', !ReleaseKind::changesRows(['website/x.php' => ['<?php $pdo->exec("UPDATE t SET a = 1");', null]]));
$t('the fence still knows an insert (TeamRunner::backendFile reads insertsRows)', ReleaseKind::insertsRows($m("INSERT INTO t (a) VALUES (1)")));
$src = (string) file_get_contents($app . '/lib/Releases.php');
$t('rule 2 holds on changesRows alone: no insertsRows, no data_fix kind', substr_count($src, 'ReleaseKind::changesRows($files)') === 1 && !str_contains($src, 'ReleaseKind::insertsRows') && !str_contains($src, "\$kind === 'data_fix' ||"));
$t('a high-risk territory still stops first, as a Decision Brief', strpos($src, "if (\$t3 !== '') { self::briefFor(") < strpos($src, 'ReleaseKind::changesRows($files)') && ReleaseKind::territory(['app/lib/Orders.php'], ['app/lib/Orders.php' => ["<?php\n", "<?php\n"]]) !== '');
$t('a migration that changes rows is still a high-risk territory (data), unchanged', ReleaseKind::territory(['app/migrations/2026-11-28_x.sql'], $m("UPDATE t SET a = 2")) !== '' && ReleaseKind::territory(['app/migrations/2026-11-28_x.sql'], $m("INSERT INTO t (a) VALUES (1)")) === '');

// ---- (3) the review email's words
$r = $rel($e);
$t('passed by the advocates: "its checks passed"', str_contains(Releases::reviewLead($r), 'is on staging, its checks passed, and it waits for your review.'));
$r2 = $rel($a);
$t('couldn\'t be judged: the email says so, with the 3 tries and why, never "its checks passed"', !str_contains(Releases::reviewLead($r2), 'its checks passed') && str_contains(Releases::reviewLead($r2), 'couldn\'t be judged') && str_contains(Releases::reviewLead($r2), 'tried 3 times') && str_contains(Releases::reviewLead($r2), 'couldn\'t sign in') && str_contains(Releases::reviewLead($r2), 'IT Manager'));
$r5 = $rel($e); $r5['checks']['items'][] = [BeforeAfter::ITEM, false, 'x']; $r5['checks']['items'] = array_values(array_filter($r5['checks']['items'], fn ($i) => $i[0] !== BeforeAfter::ITEM || $i[1] === false));
$t('blocked by the advocates: "has not passed it", never "its checks passed"', !str_contains(Releases::reviewLead($r5), 'its checks passed') && str_contains(Releases::reviewLead($r5), 'The before-and-after check has not passed it.'));
$r3 = $rel($e); $r3['checks']['items'] = [['PHP files parse', true, '1 checked']];
$t('the check didn\'t run on it: said so, never "its checks passed"', !str_contains(Releases::reviewLead($r3), 'its checks passed') && str_contains(Releases::reviewLead($r3), 'didn\'t run on it'));
$r4 = $rel($e); $r4['log'][] = ['at' => gmdate('c'), 'by' => 0, 'msg' => 'Waits for the daily batch: its migration changes or removes rows (UPDATE or DELETE, with its data_reason): it waits in the daily batch for the Founder.'];
$t('why it waits for the Founder is in the email, from its own record', str_contains(Releases::reviewLead($r4), 'Why it waits for you: its migration changes or removes rows'));
$t('the email\'s body and text both use that line', substr_count($src, 'self::reviewLead($r)') === 1 && substr_count($src, '$h($lead)') === 1 && substr_count($src, '$text = $lead') === 1 && substr_count($src, 'its checks passed, and it waits for your review') === 1);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
