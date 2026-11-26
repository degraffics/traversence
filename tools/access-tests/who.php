<?php
// The CEO's urgent fix (2026-11-26; decisions/0109 admin access through Access::can, 0127 each target's check decides who):
// a signed-in member was read as the Founder outside the admin (Access::who's default with no staff session), so the order
// upload targets let a member add or replace a proof, or add a file, on any order. Proved on the live code first, then the
// fix: a member is refused on both targets; staff pass; a staff lookup that fails is refused; a scheduled job (the command
// line) still runs; the admin's switch-over (decisions/0100) still opens. On a real MySQL or MariaDB (it resets lv_main).
// Run: php who.php <the fix's root>/app <the live mirror's app>
declare(strict_types=1);
[$fix, $live] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/')];
if (!is_file($fix . '/admin/Access.php') || !is_file($live . '/admin/Access.php')) { fwrite(STDERR, "usage: php who.php <fix>/app <live>/app\n"); exit(2); }
require_once $fix . '/lib/Database.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$reset = function (bool $staff, bool $owner = true) use ($pdo) {
    $pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4'); $pdo->exec('USE lv_main');
    if (!$staff) return;
    $pdo->exec("CREATE TABLE staff_accounts (id INT PRIMARY KEY, user_id INT, status VARCHAR(12), role VARCHAR(12), level VARCHAR(12), department VARCHAR(8), assists INT NULL, name VARCHAR(60))");
    $pdo->exec("INSERT INTO staff_accounts VALUES (7, 501, 'active', 'admin', 'staff', 'svc', NULL, 'Test Service'), (1, 1, " . ($owner ? "'active'" : "'ended'") . ", 'owner', 'founder', '', NULL, 'Owner')");
};
$run = fn (string $app, string $case, string $mode = 'web') => json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/case.php') . ' ' . escapeshellarg($app) . ' ' . $case . ' ' . $mode), true);

// 1. proved on the live code
$reset(true); $m = $run($live, 'member');
$t('live: a signed-in member with no staff login reads as the Founder', ($m['who'][0] ?? '') === 'founder', $m['who'] ?? $m);
$t('live: … and order_proof lets it past the staff check (any order id)', str_starts_with((string) ($m['order_proof'] ?? ''), 'passed'), $m['order_proof'] ?? null);
$t('live: … and order_file too', str_starts_with((string) ($m['order_file'] ?? ''), 'passed'), $m['order_file'] ?? null);

// 2. the fix
$m = $run($fix, 'member');
$t('fixed: a member with no staff login is nobody', ($m['who'] ?? null) === ['', ''], $m['who'] ?? $m);
$t('fixed: can() refuses, saying to sign in with a staff login', ($m['can'][0] ?? true) === false && str_contains((string) $m['can'][1], 'staff login'), $m['can'] ?? null);
$t('fixed: order_proof refuses a member', str_starts_with((string) $m['order_proof'], 'refused: Sign in with your staff login'), $m['order_proof']);
$t('fixed: order_file refuses a member', str_starts_with((string) $m['order_file'], 'refused: Sign in with your staff login'), $m['order_file']);
$s = $run($fix, 'staff');
$t('fixed: a staff login in Service passes both targets\' staff check', ($s['who'][0] ?? '') === 'staff' && str_starts_with((string) $s['order_proof'], 'passed') && str_starts_with((string) $s['order_file'], 'passed'), $s);
$reset(false); $b = $run($fix, 'broken');
$t('fixed: a staff lookup that fails is refused, never the Founder', ($b['who'] ?? null) === ['', ''] && str_starts_with((string) $b['order_proof'], 'refused'), $b);
$c = $run($fix, 'member', 'cli');
$t('fixed: a scheduled job (the command line) still runs as before', ($c['who'][0] ?? '') === 'founder' && $c['can'][0] === true, $c);
$reset(true, false); $a = $run($fix, 'admin_switchover');
$t('fixed: decisions/0100\'s switch-over (no owner\'s staff login yet): an admin page still opens', ($a['who'][0] ?? '') === 'founder', $a);
$reset(true, false); $a2 = $run($fix, 'member');
$t('… but outside the admin, even then, a member is nobody', ($a2['who'] ?? null) === ['', ''], $a2);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
