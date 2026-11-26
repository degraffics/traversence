<?php
// The stopped copy of 2026-11-26 03:2x UTC ("strip admin admin_access": MySQL server has gone away): a realm's connection,
// kept for the whole of staging_carry_tick's run, was closed by the server while it sat idle. This kills the kept connection
// as the server did, then asks StagingSite::conn() again, as the next step does. On the bench's MySQL or MariaDB. Never live.
//   php tools/staging-tests/conn-gone.php <app folder>
$app = rtrim($argv[1] ?? '', '/');
require_once $app . '/lib/Database.php'; require_once $app . '/lib/StagingSite.php';
$fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; echo ($c ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$c) $fails++; }
$conn = new ReflectionMethod(StagingSite::class, 'conn'); $conn->setAccessible(true);
$at = new ReflectionProperty(StagingSite::class, 'connAt'); $at->setAccessible(true);
$other = Database::connection();

$c1 = $conn->invoke(null, 'main'); $id1 = (int) $c1->query('SELECT CONNECTION_ID()')->fetchColumn();
ok((int) $c1->query('SELECT @@SESSION.wait_timeout')->fetchColumn() === 3600, 'a new connection asks the server to keep it an hour idle');
ok($conn->invoke(null, 'main') === $c1, 'asked again within 20 seconds: the same connection, nothing asked of the server');

// the server closes it, as it did while main's strip ran
$other->exec('KILL ' . $id1); usleep(200000);
$cause = '';
try { @$c1->query('SELECT 1'); } catch (Throwable $e) { $cause = $e->getMessage(); }
ok(str_contains($cause, 'gone away') || str_contains($cause, '2006') || str_contains($cause, 'Lost connection') || str_contains($cause, '2013'), 'the cause, reproduced: the kept connection, used after the server closed it, fails (' . substr($cause, 0, 70) . ')');

// the next step, a while later, asks conn() again
$v = $at->getValue(); $v['main'] = time() - 120; $at->setValue(null, $v);
$threw = '';
try { $c2 = $conn->invoke(null, 'main'); $id2 = (int) $c2->query('SELECT CONNECTION_ID()')->fetchColumn(); $one = (int) $c2->query('SELECT 1')->fetchColumn(); }
catch (Throwable $e) { $threw = $e->getMessage(); }
ok($threw === '', 'conn() after the server closed it: no error' . ($threw !== '' ? ' (' . $threw . ')' : ''));
ok(isset($c2) && $c2 !== $c1 && $id2 !== $id1 && $one === 1, 'it hands out a new, working connection');
ok((int) $c2->query('SELECT @@SESSION.wait_timeout')->fetchColumn() === 3600, 'with its hour idle asked again');
ok(stripos((string) $c2->query('SELECT @@SESSION.sql_mode')->fetchColumn(), 'NO_ZERO_DATE') === false, 'and the zero-dates mode set again');

// an idle but healthy connection is kept (asked with SELECT 1, not made again)
$v = $at->getValue(); $v['main'] = time() - 120; $at->setValue(null, $v);
ok($conn->invoke(null, 'main') === $c2, 'idle but still open: kept');

// the step itself: the strip on the new connection runs (one table on staging's copy)
$db = 'lv_mainstg'; $c2->exec("CREATE DATABASE IF NOT EXISTS $db"); $c2->exec("DROP TABLE IF EXISTS $db.admin_access"); $c2->exec("CREATE TABLE $db.admin_access (id INT PRIMARY KEY, user_id INT, tier VARCHAR(30), ip_address VARCHAR(45))");
$c2->exec("INSERT INTO $db.admin_access VALUES (1, 5, 'super_admin', '203.0.113.9')");
$v = $at->getValue(); $v['main'] = time() - 120; $at->setValue(null, $v); $other->exec('KILL ' . $id2); usleep(200000);
$threw = ''; try { StagingSite::strip($conn->invoke(null, 'main'), $db, [], [], 'salt', 'admin_access'); } catch (Throwable $e) { $threw = $e->getMessage(); }
ok($threw === '', 'the step that stopped ("strip … admin_access"), after its connection was closed: runs' . ($threw !== '' ? ' (' . $threw . ')' : ''));
ok($other->query("SELECT ip_address FROM $db.admin_access")->fetchColumn() === null, 'and does its work (the address cleared)');
$other->exec("DROP TABLE $db.admin_access");

echo "\n" . ($n - $fails) . " of $n checks pass\n"; exit($fails ? 1 : 0);
