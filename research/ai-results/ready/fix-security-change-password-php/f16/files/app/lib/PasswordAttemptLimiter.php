<?php

declare(strict_types=1);

/**
 * Per-user limiter for "prove you know your current password" checks
 * (finding f16).
 *
 * Design:
 *  - Reserve-then-verify. Every attempt is counted atomically BEFORE the
 *    password is checked. Parallel requests therefore cannot slip past
 *    the limit between the check and the increment.
 *  - A correct password resets the counter.
 *  - A request rejected for a reason that is not a wrong password (bad new
 *    password, DB error) gets its reservation refunded.
 *  - Fixed window. Up to MAX_ATTEMPTS attempts are allowed per
 *    WINDOW_SECONDS, counted from the first attempt in the window. When the
 *    window has passed, the next attempt starts a new window.
 *  - One shared scope (SCOPE_CURRENT_PASSWORD) is meant for every endpoint
 *    that checks the current password: change-password.php and, once wired,
 *    request-change.php. An attacker then gets one budget in total, not one
 *    budget per endpoint.
 *
 * Storage: table password_attempt_counters (see the migration in this
 * release). Times are unix seconds passed in from PHP, so tests can inject
 * a clock.
 */
final class PasswordAttemptLimiter
{
    public const SCOPE_CURRENT_PASSWORD = 'current_password';
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 900; // 15 minutes

    /**
     * Pure decision helper. It reads no state, so it can be tested without a DB.
     *
     * @return array{allowed: bool, failures: int, retry_after: int}
     */
    public static function decide(int $failures, int $windowStart, int $now): array
    {
        $allowed = $failures <= self::MAX_ATTEMPTS;
        $retryAfter = 0;
        if (!$allowed) {
            $retryAfter = max(1, ($windowStart + self::WINDOW_SECONDS) - $now);
        }
        return ['allowed' => $allowed, 'failures' => $failures, 'retry_after' => $retryAfter];
    }

    /**
     * Atomically counts one attempt and reports whether it may proceed.
     * Throws PDOException on storage failure. The caller decides whether to
     * fail open or closed (change-password.php fails closed).
     *
     * @return array{allowed: bool, failures: int, retry_after: int}
     */
    public static function reserve(PDO $pdo, int $userId, string $scope, ?int $now = null): array
    {
        $now = $now ?? time();
        $cutoff = $now - self::WINDOW_SECONDS;

        // MySQL applies ON DUPLICATE KEY UPDATE assignments left to right.
        // The second IF therefore still sees the OLD window_start, because
        // only `failures` has been reassigned at that point.
        $stmt = $pdo->prepare(
            'INSERT INTO password_attempt_counters (user_id, scope, failures, window_start)
             VALUES (:uid, :scope, 1, :now1)
             ON DUPLICATE KEY UPDATE
               failures = IF(window_start <= :cutoff1, 1, failures + 1),
               window_start = IF(window_start <= :cutoff2, :now2, window_start)'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':scope' => $scope,
            ':now1' => $now,
            ':cutoff1' => $cutoff,
            ':cutoff2' => $cutoff,
            ':now2' => $now,
        ]);

        $sel = $pdo->prepare(
            'SELECT failures, window_start FROM password_attempt_counters
             WHERE user_id = :uid AND scope = :scope'
        );
        $sel->execute([':uid' => $userId, ':scope' => $scope]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            // The row was written a moment ago, so it should exist. If it
            // does not, treat that as a storage failure and let the caller
            // fail closed.
            throw new PDOException('password_attempt_counters row missing after upsert');
        }

        return self::decide((int) $row['failures'], (int) $row['window_start'], $now);
    }

    /**
     * Gives back one reserved attempt. Used when the request failed for a
     * reason other than a wrong current password.
     * data_reason: decrements this user's own counter, one row, floor 0.
     */
    public static function refund(PDO $pdo, int $userId, string $scope): void
    {
        $stmt = $pdo->prepare(
            'UPDATE password_attempt_counters SET failures = failures - 1
             WHERE user_id = :uid AND scope = :scope AND failures > 0'
        );
        $stmt->execute([':uid' => $userId, ':scope' => $scope]);
    }

    /**
     * Clears the counter after a correct current password.
     * data_reason: removes this user's own counter row after a successful
     * verification, so earlier typos stop counting against them.
     */
    public static function reset(PDO $pdo, int $userId, string $scope): void
    {
        $stmt = $pdo->prepare(
            'DELETE FROM password_attempt_counters WHERE user_id = :uid AND scope = :scope'
        );
        $stmt->execute([':uid' => $userId, ':scope' => $scope]);
    }
}
