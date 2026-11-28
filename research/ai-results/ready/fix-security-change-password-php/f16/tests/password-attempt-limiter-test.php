<?php

declare(strict_types=1);

// f16 tests. Run from the CLI: php tools/auth-tests/password-attempt-limiter-test.php
// Part 1 is pure logic and always runs.
// Part 2 needs the DB and the f16 migration; without them it prints SKIP.
// It uses synthetic user ids and the scope 'f16_selftest', and deletes its own rows.
// No money and no email are involved.

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/PasswordAttemptLimiter.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$ok) {
        $fails++;
    }
}

// ---- Part 1: pure decide() ----
$now = 1_700_000_000;
$max = PasswordAttemptLimiter::MAX_ATTEMPTS;
$win = PasswordAttemptLimiter::WINDOW_SECONDS;

$d = PasswordAttemptLimiter::decide(1, $now, $now);
check('first attempt allowed', $d['allowed'] === true && $d['retry_after'] === 0);

$d = PasswordAttemptLimiter::decide($max, $now, $now);
check('attempt number MAX still allowed', $d['allowed'] === true);

$d = PasswordAttemptLimiter::decide($max + 1, $now, $now + 60);
check('attempt MAX+1 blocked', $d['allowed'] === false);
check('retry_after counts to window end', $d['retry_after'] === $win - 60);

$d = PasswordAttemptLimiter::decide($max + 1, $now, $now + $win + 5);
check('retry_after never below 1', $d['retry_after'] === 1);

// ---- Part 2: DB-backed reserve / refund / reset ----
$pdo = null;
try {
    require_once $root . '/app/bootstrap.php';
    $pdo = Database::connection();
    $pdo->query('SELECT 1 FROM password_attempt_counters LIMIT 1');
} catch (Throwable $e) {
    echo 'SKIP: DB part (' . $e->getMessage() . ')' . PHP_EOL;
    $pdo = null;
}

if ($pdo instanceof PDO) {
    $scope = 'f16_selftest';
    $uidA = 9000000000000000001;
    $uidB = 9000000000000000002;
    $cleanup = function () use ($pdo, $scope): void {
        // data_reason: remove only rows this test created (synthetic ids, test scope).
        $pdo->prepare('DELETE FROM password_attempt_counters WHERE scope = :s')->execute([':s' => $scope]);
    };
    $cleanup();
    try {
        $t = 1_700_000_000;
        for ($i = 1; $i <= $max; $i++) {
            $r = PasswordAttemptLimiter::reserve($pdo, $uidA, $scope, $t + $i);
            check("reserve #$i allowed", $r['allowed'] === true && $r['failures'] === $i);
        }
        $r = PasswordAttemptLimiter::reserve($pdo, $uidA, $scope, $t + 100);
        check('reserve MAX+1 blocked', $r['allowed'] === false);
        check('blocked retry_after > 0', $r['retry_after'] > 0);

        $r = PasswordAttemptLimiter::reserve($pdo, $uidB, $scope, $t + 100);
        check('other user unaffected', $r['allowed'] === true && $r['failures'] === 1);

        $r = PasswordAttemptLimiter::reserve($pdo, $uidA, $scope, $t + 1 + $win);
        check('new window after expiry resets to 1', $r['allowed'] === true && $r['failures'] === 1);

        PasswordAttemptLimiter::refund($pdo, $uidA, $scope);
        PasswordAttemptLimiter::refund($pdo, $uidA, $scope); // must not underflow
        $r = PasswordAttemptLimiter::reserve($pdo, $uidA, $scope, $t + 2 + $win);
        check('refund floors at 0', $r['failures'] === 1);

        PasswordAttemptLimiter::reset($pdo, $uidA, $scope);
        $r = PasswordAttemptLimiter::reserve($pdo, $uidA, $scope, $t + 3 + $win);
        check('reset clears counter', $r['failures'] === 1);
    } catch (Throwable $e) {
        check('DB part threw: ' . $e->getMessage(), false);
    } finally {
        $cleanup();
    }
}

echo ($fails === 0 ? 'ALL PASS' : "FAILURES: $fails") . PHP_EOL;
exit($fails === 0 ? 0 : 1);
