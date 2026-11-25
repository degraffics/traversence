<?php
// Piece 3, P3-3: rule 1 and rule 2 kept by the site (Releases::rule2, the switch rule2_autopublish; decisions/0179) on a real
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
$c = $mk('r2-wording-bad.zip', ['app/ui/faq.md' => $faqNew], 'wording', true);
$t('a kind that isn\'t proved (wording, but a .md): it waits, saying why', str_contains($why($c), 'isn\'t in an approved batch') && str_contains($why($c), 'isn\'t PHP'), $why($c));
$d = $mk('r2-none.zip', ['app/lib/R2Test.php' => "<?php\n"], '', true);
$t('no kind: it waits for the batch', str_contains($why($d), 'names no kind'), $why($d));
GaugeTasks::approve($pdo, 'area:batch-' . gmdate('Y-m-d'), 'batch', 'other.zip, r2-none.zip', 'the daily batch', 'Jason (test)', 1);
$t('… once today\'s approved batch names its zip, it goes', $why($d) === '');
$e = $mk('r2-order.zip', ['website/order.php' => "<?php echo 'x';"], 'additive', true);
$t('a Tier 3 area is never low-risk, whatever its kind says', str_contains($why($e), 'Tier 3'), $why($e));
// through checkedByAdvocates → autoPublish: one that must wait goes for review, never live
$f = $mk('r2-wait.zip', ['app/lib/R2Wait.php' => "<?php\n"], '', false);
$out = Releases::checkedByAdvocates($pdo, $f, ['verdict' => 'pass', 'first' => '', 'views' => 5, 'run' => '', 'blocks' => 0, 'seconds' => 0], false);
$lg = array_column((array) Releases::get($pdo, $f)['log'], 'msg');
$t('passed by the advocates but not in the batch and no kind: it waits for review, its record saying why', (bool) preg_grep('/^Waits for the daily batch: /', $lg) && str_starts_with(Releases::lastWordFor(Releases::get($pdo, $f)), 'Sent for review') && Releases::get($pdo, $f)['state'] === 'staged', $out);
// the Work queue's strip
$pdo->prepare("UPDATE site_releases SET state = 'live', live_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$a]);
if (!function_exists('tv_icon')) { function tv_icon(string $n, $c = ''): string { return ''; } }
require_once $app . '/admin/widgets.php'; require_once $app . '/lib/ControlGauges.php'; require_once $app . '/admin/control-console.php';
$h = tv_cb_wq_batch($pdo, []);
$t('the strip: "Published alone today (low-risk)" with the release, its kind and Undo', str_contains($h, 'Published alone today') && str_contains($h, '#' . $a . ' r2-faq.zip') && str_contains($h, 'data-wq="relback" data-id="' . $a . '"') && str_contains($h, ' · faq · '), strip_tags($h));
$pdo->prepare("UPDATE site_releases SET state = 'rolled_back' WHERE id = ?")->execute([$a]);
$t('… rolled back: it says undone, no button', str_contains(tv_cb_wq_batch($pdo, []), 'undone (rolled back)') && !str_contains(tv_cb_wq_batch($pdo, []), 'data-wq="relback"'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
