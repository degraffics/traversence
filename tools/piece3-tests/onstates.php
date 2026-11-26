<?php
// The before-and-after check is on in every state of its switch but off (the session manager's condition for A, 2026-11-26):
// off, test (staff and test accounts), share, on gives off, on, on, on; killed is off in any state; staging never runs it.
// P3-6 shipped the switch at step 1 ('test') and BeforeAfter::on() read only 'on', so releases #297 to #302 went out unchecked.
// On the bench's MySQL or MariaDB; it sets the before_after switch and puts it back.
//   php tools/piece3-tests/onstates.php <app folder>
$app = rtrim($argv[1] ?? '', '/');
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/BeforeAfter.php';
$pdo = Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS feature_switches (switch_key VARCHAR(64) NOT NULL PRIMARY KEY, state VARCHAR(10) NOT NULL DEFAULT 'off', share_pct TINYINT NOT NULL DEFAULT 0, killed TINYINT NOT NULL DEFAULT 0, note VARCHAR(255) NOT NULL DEFAULT '', updated_by INT NULL, updated_at DATETIME NULL)");   // the bench's copy, as live has it
$fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; echo ($c ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$c) $fails++; }
$was = Switches::all($pdo)['before_after'] ?? null;
foreach (['off' => false, 'test' => true, 'share' => true, 'on' => true] as $state => $want) {
    Switches::set($pdo, 'before_after', $state, $state === 'share' ? 25 : 0, false, 'test', 0);
    ok(BeforeAfter::on($pdo) === $want, "switch '$state': the check is " . ($want ? 'on' : 'off'));
}
foreach (['test', 'share', 'on'] as $state) {
    Switches::set($pdo, 'before_after', $state, 25, true, 'test', 0);
    ok(BeforeAfter::on($pdo) === false, "switch '$state' but killed: the check is off");
}
if ($was) Switches::set($pdo, 'before_after', (string) $was['state'], (int) $was['share_pct'], (bool) $was['killed'], 'put back by the test', 0);
echo "\n" . ($n - $fails) . " of $n checks pass\n"; exit($fails ? 1 : 0);
