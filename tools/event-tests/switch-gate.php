<?php
// The event_codes switch is the Founder's (decisions/0110's note, Tier 5; the CTO's final look, 2026-11-27): only the Founder
// sets it to anything that is, or could later become, on; anyone may kill it at its setting; Kill all and Lift all pressed
// by anyone else never stop half way and leave the Founder's switches as they are. On a real MySQL or MariaDB (lv_sw, user
// tv/tv on 127.0.0.1), with Access::isFounder stood in by a flag.
// Run: php switch-gate.php <root>/app
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/');
if (!is_file($app . '/lib/Switches.php')) { fwrite(STDERR, "usage: php switch-gate.php <root>/app\n"); exit(2); }
$tmp = sys_get_temp_dir() . '/swgate-' . getmypid(); @mkdir($tmp . '/lib', 0777, true); @mkdir($tmp . '/admin');
copy($app . '/lib/Switches.php', $tmp . '/lib/Switches.php');
file_put_contents($tmp . '/lib/Database.php', '<?php final class Database { public static ?PDO $p = null; public static function connection(): PDO { return self::$p; } }');
file_put_contents($tmp . '/lib/TestAccounts.php', '<?php final class TestAccounts { public static function isTestUser($p, int $u): bool { return false; } }');
file_put_contents($tmp . '/admin/Access.php', '<?php final class Access { public static bool $founder = false; public static function isFounder(): bool { return self::$founder; } }');
require $tmp . '/lib/Database.php'; require $tmp . '/admin/Access.php'; require $tmp . '/lib/Switches.php';
$root = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('DROP DATABASE IF EXISTS lv_sw'); $root->exec('CREATE DATABASE lv_sw'); $root->exec('USE lv_sw');
$root->exec("CREATE TABLE feature_switches (switch_key VARCHAR(40) PRIMARY KEY, state VARCHAR(8) NOT NULL DEFAULT 'off', share_pct INT NOT NULL DEFAULT 0, killed TINYINT NOT NULL DEFAULT 0, note VARCHAR(255) NOT NULL DEFAULT '', updated_by INT NULL, updated_at DATETIME NULL)");
$pdo = $root; Database::$p = $pdo;
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$tries = function (callable $f): string { try { $f(); return 'done'; } catch (RuntimeException $x) { return 'refused'; } };
$st = fn () => Switches::fresh($pdo, 'event_codes');
$t('it ships off', Switches::all($pdo)['event_codes']['state'] === 'off' && !Switches::on($pdo, 'event_codes', 0));
Access::$founder = false;
foreach (['on', 'test', 'share'] as $s) $t("someone else can't set it $s", $tries(fn () => Switches::set($pdo, 'event_codes', $s, 10, false, '', 2)) === 'refused' && $st() === null);
$t('nor save it on-but-killed (lifting kills later would turn it on)', $tries(fn () => Switches::set($pdo, 'event_codes', 'on', 0, true, '', 2)) === 'refused' && $st() === null);
$t('anyone may set it off', $tries(fn () => Switches::set($pdo, 'event_codes', 'off', 0, false, 'checked', 2)) === 'done' && $st()['state'] === 'off');
Access::$founder = true;
$t('the Founder switches it on', $tries(fn () => Switches::set($pdo, 'event_codes', 'on', 0, false, '0110 ruling', 1)) === 'done' && Switches::on($pdo, 'event_codes', 0));
Access::$founder = false;
$t('anyone may kill it at its setting', $tries(fn () => Switches::set($pdo, 'event_codes', 'on', 0, true, 'a problem', 2)) === 'done' && !Switches::on($pdo, 'event_codes', 0));
$t('but not lift the kill', $tries(fn () => Switches::set($pdo, 'event_codes', 'on', 0, false, '', 2)) === 'refused' && $st()['killed'] === true);
Switches::set($pdo, 'switch_test', 'on', 0, true, '', 2);
$n = Switches::killAll($pdo, false, 2);
$t('Lift all by someone else runs to the end: other switches lifted', Switches::fresh($pdo, 'switch_test')['killed'] === false, $n);
$t('and leaves the Founder\'s switch killed', $st()['killed'] === true && !Switches::on($pdo, 'event_codes', 0));
Access::$founder = true; Switches::set($pdo, 'event_codes', 'on', 0, false, '', 1); Access::$founder = false;
Switches::killAll($pdo, true, 2);
$t('Kill all by anyone kills it too', $st()['killed'] === true && !Switches::on($pdo, 'event_codes', 0) && Switches::fresh($pdo, 'switch_test')['killed'] === true);
$root->exec('DROP DATABASE lv_sw');
array_map('unlink', array_filter(glob($tmp . '/{,*/}*', GLOB_BRACE), 'is_file'));
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
