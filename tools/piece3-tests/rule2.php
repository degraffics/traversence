<?php
// Piece 3, P3-3 (with Jason's Tier 2 ruling and the add-only migration ruling, 2026-11-25): rule 1 and rule 2 kept by the site (Releases::rule2, the switch rule2_autopublish; decisions/0179) on a real
// MySQL or MariaDB. Off: nothing waits. On: a release in today's approved batch goes; a proved low-risk kind the advocates
// passed goes alone, noted "Published alone (low-risk: …)"; not proved, or not passed by the advocates, waits for the batch
// (review), saying why; only the Founder turns the switch on; the Work queue's strip lists what went alone with Undo.
// Run: php rule2.php <root>/app   (it resets lv_main)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php rule2.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$H = dirname(__DIR__, 2) . '/handoffs/';
$run(dirname(__DIR__) . '/team-tests/before-schema.sql');
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql', $H . 'piece2-build/team-tickets/build/app/migrations/2026-11-25_tickets.sql'] as $f) $run($f);
$pdo->exec("CREATE TABLE feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, email VARCHAR(190) NOT NULL DEFAULT '', role VARCHAR(20) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'active')");
require_once $app . '/lib/Staging.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Releases.php'; require_once $app . '/lib/BeforeAfter.php'; require_once $app . '/lib/GaugeTasks.php'; require_once $app . '/admin/Access.php';
putenv('STAGING=0');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$rule2 = new ReflectionMethod(Releases::class, 'rule2'); $rule2->setAccessible(true);
$faq = (string) file_get_contents($app . '/ui/faq.md');
$mk = function (string $name, array $files, string $kind, bool $advocates) use ($pdo) {
    $z = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(), 'r2') . '.zip'; $z->open($tmp, ZipArchive::CREATE);
    $z->addFromString('release.json', json_encode(['title' => $name, 'kind' => $kind])); foreach ($files as $p => $c) $z->addFromString($p, $c); $z->close();
    $id = (int) Releases::fromUpload($pdo, 0, $tmp, $name, '')['id']; @unlink($tmp);
    Releases::noteFromCheck($pdo, $id, 'Came from git: releases/inbox/' . $name . ' (git 0123456789).');
    if ($kind !== '') $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['kind:' . $id, $kind]);
    $items = [['PHP files parse', true, '0 checked']]; if ($advocates) $items[] = [BeforeAfter::ITEM, true, '12 views'];
    $pdo->prepare("UPDATE site_releases SET state = 'staged', checks_ok = 1, checks = ? WHERE id = ?")->execute([json_encode(['ok' => true, 'items' => $items, 'first_fail' => '']), $id]);
    return $id; };
$why = fn (int $id) => $rule2->invoke(null, $pdo, Releases::get($pdo, $id));
$faqNew = $faq . "\n### I'm testing rule 2. Is this a new question?\nYes.\n";
$a = $mk('r2-faq.zip', ['app/ui/faq.md' => $faqNew], 'faq', true);
$t('off (as shipped): nothing waits', Switches::DEFS['rule2_autopublish'][2] === 'off' && $why($a) === '');
// only the Founder turns it on
try { Switches::set($pdo, 'rule2_autopublish', 'on', 0, false, 't', 1); $t('a staff member can\'t turn rule 2 on', false); } catch (RuntimeException $x) { $t('a staff member (or no staff session) can\'t turn rule 2 on', str_contains($x->getMessage(), 'Only the Founder')); }
$sp = new ReflectionProperty(Access::class, 'staff'); $sp->setAccessible(true); $lp = new ReflectionProperty(Access::class, 'loaded'); $lp->setAccessible(true);
$sp->setValue(null, ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => 'Jason (test)', 'user_id' => 1]); $lp->setValue(null, true);
Switches::set($pdo, 'rule2_autopublish', 'on', 0, false, 'rule2 test', 1);
$t('the Founder turns it on', Switches::all($pdo)['rule2_autopublish']['state'] === 'on');
$sp->setValue(null, null);
$t('on: a proved FAQ release the advocates passed goes alone, its record says "Published alone (low-risk: faq)"', $why($a) === '' && str_starts_with(Releases::lastWordFor(Releases::get($pdo, $a)), 'Published alone (low-risk: faq)'), Releases::lastWordFor(Releases::get($pdo, $a)));
$b = $mk('r2-faq-nocheck.zip', ['app/ui/faq.md' => $faqNew], 'faq', false);
$t('… the same, the advocates not passed yet: it waits, saying so', str_contains($why($b), 'the advocates haven\'t passed it'), $why($b));
// Jason, 2026-11-25 (0179's note "Tier 2 publishes on its own once Piece 3 is live"): only Tier 3 stops
$last = fn (int $id) => Releases::lastWordFor(Releases::get($pdo, $id));
$c = $mk('r2-wording-bad.zip', ['app/ui/faq.md' => $faqNew], 'wording', true);
$t('a kind that isn\'t proved (wording, but a .md), the advocates passed: Tier 2, it goes alone, "Published alone (Tier 2)"', $why($c) === '' && str_starts_with($last($c), 'Published alone (Tier 2)'), [$why($c), $last($c)]);
$d = $mk('r2-none.zip', ['app/lib/R2Test.php' => "<?php\n"], '', true);
$t('no kind, nothing in a Tier 3 area, the advocates passed: Tier 2, it goes alone', $why($d) === '' && str_starts_with($last($d), 'Published alone (Tier 2)'));
$d2 = $mk('r2-none-nocheck.zip', ['app/lib/R2Test2.php' => "<?php\n"], '', false);
$t('… Tier 2 the advocates haven\'t passed: it waits, saying so', str_contains($why($d2), 'Tier 2, but the advocates haven\'t passed it'), $why($d2));
GaugeTasks::approve($pdo, 'area:batch-' . gmdate('Y-m-d'), 'batch', 'other.zip, r2-none-nocheck.zip', 'the daily batch', 'Jason (test)', 1);
$t('… once today\'s approved batch names its zip, it goes', $why($d2) === '');
$m = $mk('r2-create.zip', ['app/migrations/2026-12-01_r2x.sql' => "CREATE TABLE IF NOT EXISTS r2x (id INT, at DATETIME ON UPDATE CURRENT_TIMESTAMP);", 'app/lib/R2X.php' => "<?php\n"], 'additive', true);
$t('a CREATE TABLE IF NOT EXISTS migration with a new library, kind additive: proved Tier 1, it goes alone (the add-only ruling)', $why($m) === '' && str_starts_with($last($m), 'Published alone (low-risk: additive)'), [$why($m), $last($m)]);
$g = $mk('r2-drop.zip', ['app/migrations/2026-12-01_r2d.sql' => "CREATE TABLE IF NOT EXISTS r2y (id INT);\nDROP TABLE r2_old;"], 'additive', true);
$t('a migration that DROPs: Tier 3 (data), it waits for the Founder as a Decision Brief', str_contains($why($g), 'Tier 3 area (data)') && str_contains($why($g), 'Decision Brief'), $why($g));
$h0 = $mk('r2-insert.zip', ['app/migrations/2026-12-01_r2i.sql' => "INSERT INTO team_roster (slug) SELECT 'x' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM team_roster WHERE slug = 'x');"], '', true);
$t('a migration that INSERTs rows: a data fix, it stays in the daily batch (DB3)', str_contains($why($h0), 'DB3'), $why($h0));
$e = $mk('r2-order.zip', ['website/order.php' => "<?php echo 'x';"], 'additive', true);
$n0 = (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE ticket = 'resolution'")->fetchColumn();
$t('a Tier 3 area (money) is never published alone, whatever its kind says', str_contains($why($e), 'Tier 3 area (money)'), $why($e));
$why($e); $rs = $pdo->query("SELECT what FROM control_gauge_tasks WHERE ticket = 'resolution' ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
$t('… its Decision Brief: one Resolution ticket in the Work queue, once however often it\'s asked, and its record says so', count($rs) === $n0 + 1 && str_contains($rs[0], 'Release #' . $e) && str_contains($rs[0], 'money') && str_contains($last($e), 'Decision Brief #'), [$rs, $last($e)]);
// through checkedByAdvocates → autoPublish: one that must wait goes for review, never live
$f = $mk('r2-wait.zip', ['website/order.php' => "<?php echo 'y';"], '', false);
$out = Releases::checkedByAdvocates($pdo, $f, ['verdict' => 'pass', 'first' => '', 'views' => 5, 'run' => '', 'blocks' => 0, 'seconds' => 0], false);
$lg = array_column((array) Releases::get($pdo, $f)['log'], 'msg');
$t('passed by the advocates but Tier 3 and not in the batch: it waits for review, its record saying why', (bool) preg_grep('/^Waits for the daily batch: .*Tier 3/', $lg) && Releases::get($pdo, $f)['state'] === 'staged', [$out, $lg]);
// the Work queue's strip
$pdo->prepare("UPDATE site_releases SET state = 'live', live_at = UTC_TIMESTAMP() WHERE id IN (?, ?)")->execute([$a, $d]);
if (!function_exists('tv_icon')) { function tv_icon(string $n, $c = ''): string { return ''; } }
require_once $app . '/admin/widgets.php'; require_once $app . '/lib/ControlGauges.php'; require_once $app . '/admin/control-console.php';
$h = tv_cb_wq_batch($pdo, []);
$t('the strip: "Published alone today (Tier 1 and Tier 2)" with each release, its kind or Tier 2, and Undo', str_contains($h, 'Published alone today (Tier 1 and Tier 2)') && str_contains($h, '#' . $a . ' r2-faq.zip') && str_contains($h, 'data-wq="relback" data-id="' . $a . '"') && str_contains($h, ' · faq · ') && str_contains($h, '#' . $d . ' r2-none.zip</b> · Tier 2 · '), strip_tags($h));
$pdo->prepare("UPDATE site_releases SET state = 'rolled_back' WHERE id IN (?, ?)")->execute([$a, $d]);
$t('… rolled back: it says undone, no button', str_contains(tv_cb_wq_batch($pdo, []), 'undone (rolled back)') && !str_contains(tv_cb_wq_batch($pdo, []), 'data-wq="relback"'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
