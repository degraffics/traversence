<?php
// Keep staging caught up switches on at the fresh copy's last step, and nothing is previewed or published from half a copy
// (Jason, 2026-11-26: "it is supposed to move/change as actions fire"). On the bench's MySQL or MariaDB; it writes only
// staging_state (the seed). Never live.
//   php tools/staging-tests/mirror-on.php <app folder>
$app = rtrim($argv[1] ?? '', '/'); $seed = __DIR__ . '/mirror-seed.php';
require_once $app . '/lib/Database.php'; require_once $app . '/admin/Access.php'; require_once $app . '/lib/StagingSite.php'; require_once $app . '/lib/StagingPublish.php'; require_once $app . '/lib/StagingApi.php';
foreach (['staff' => ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => 'Jason (test)', 'user_id' => 1], 'loaded' => true] as $k => $v) { $p = new ReflectionProperty(Access::class, $k); $p->setAccessible(true); $p->setValue(null, $v); }
$pdo = Database::connection(); $fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; echo ($c ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$c) $fails++; }
$seedTo = function (string $st, string $pv = 'fresh') use ($app, $seed) { exec('php ' . escapeshellarg($seed) . ' ' . escapeshellarg($app) . ' ' . $st . ' ' . $pv, $o, $rc); if ($rc) throw new RuntimeException('seed failed'); };
$step = new ReflectionMethod(StagingSite::class, 'runStep'); $step->setAccessible(true);
$refuses = function (callable $f, string $want) { try { $f(); return false; } catch (RuntimeException $x) { return str_contains($x->getMessage(), $want); } };

// each state as mirrorState and the Now line read it
foreach (['off' => 'off', 'turning_on' => 'turning_on', 'on' => 'on', 'stuck' => 'stuck'] as $st => $want) {
    $seedTo($st); $ms = StagingSite::mirrorState($pdo);
    ok($ms['state'] === $want, "$st: mirrorState is $want");
}
$seedTo('turning_on'); $ms = StagingSite::mirrorState($pdo);
ok($ms['pos'] === 475 && $ms['of'] === 1184 && $ms['pct'] === 40, 'turning on: step 475 of 1184, 40%');
$line = fn () => StagingApi::line(['changes' => 0, 'held' => 0, 'conflicts' => 0, 'mirror' => StagingSite::mirrorOn($pdo), 'mirror_state' => StagingSite::mirrorState($pdo)] + StagingApi::summary($pdo));
ok(str_contains($line(), 'keeping caught up: turning on') && !str_contains($line(), 'not kept caught up'), 'turning on: the Now line says turning on, never "not kept caught up"');
$seedTo('off'); ok(str_contains($line(), 'not kept caught up'), 'off: the Now line says not kept caught up');
$seedTo('on'); ok(!str_contains($line(), 'caught up'), 'on: the Now line has no "not kept caught up"');

// the copy's last step ('finish', StagingSite::runStep) switches it on
$seedTo('turning_on'); $r = (array) StagingSite::get($pdo, 'refresh'); $setup = (array) StagingSite::get($pdo, 'setup');
try { $step->invokeArgs(null, [$pdo, $setup, &$r, ['finish']]); } catch (Throwable $x) { /* finish()'s own tidy may want staging's databases; the switch comes first */ }
$m = (array) StagingSite::get($pdo, 'mirror');
ok(!empty($m['on']) && empty($m['starting']) && !empty($m['since']), "the refresh's 'finish' step turns keeping caught up on (on, since, no longer starting)");
ok(StagingSite::mirrorOn($pdo), 'mirrorOn is true after finish');
ok(in_array('Staging keeps caught up from here: live\'s changes come in within a minute, staging\'s own work is kept.', (array) $r['log'], true), 'and the refresh log says so');

// finish without the press (the weekly kind) leaves it off
$seedTo('off'); $r = (array) StagingSite::get($pdo, 'refresh'); $r['pos'] = 1183; unset($r['finished_at']);
try { $step->invokeArgs(null, [$pdo, $setup, &$r, ['finish']]); } catch (Throwable $x) {}
ok(!StagingSite::mirrorOn($pdo), 'a copy nobody asked to keep caught up finishes with it still off');

// "Don't turn it on" during the copy: the base steps after it put no trigger on live
$seedTo('turning_on'); $pdo->prepare("UPDATE staging_state SET v = ? WHERE k = 'mirror'")->execute([json_encode(['on' => false, 'off_at' => date('c'), 'by' => 1])]);
$r = (array) StagingSite::get($pdo, 'refresh'); $threw = false;
try { $step->invokeArgs(null, [$pdo, $setup, &$r, ['base', 'main', 'orders']]); } catch (Throwable $x) { $threw = $x->getMessage(); }
ok($threw === false && !empty($r['base_skipped']), "after Don't turn it on, a 'base' step returns at once (no outbox on live, no staging trigger)");
ok(StagingSite::mirrorState($pdo)['state'] === 'off', "and it reads off");

// nothing is previewed or published from half a copy: the server refuses, not only the greyed buttons
$seedTo('turning_on');
ok($refuses(fn () => StagingPublish::preview($pdo, 1), 'A fresh copy of live is going into staging'), 'turning on: Preview is refused on the server');
ok($refuses(fn () => StagingPublish::start($pdo, 1), 'A fresh copy of live is going into staging'), 'turning on: Publish is refused on the server');
$seedTo('stuck');
ok($refuses(fn () => StagingPublish::preview($pdo, 1), 'stopped part way'), 'a stopped copy: Preview is refused on the server');
ok($refuses(fn () => StagingPublish::start($pdo, 1), 'stopped part way'), 'a stopped copy: Publish is refused on the server');
$seedTo('on'); ok(StagingSite::copyHold($pdo) === '', 'a whole copy: no hold');

echo "\n" . ($n - $fails) . " of $n checks pass\n"; exit($fails ? 1 : 0);
