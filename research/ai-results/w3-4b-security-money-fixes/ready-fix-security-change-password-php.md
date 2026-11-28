=== FILE: f16/files/app/lib/PasswordAttemptLimiter.php ===
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
=== END FILE ===

=== FILE: f16/files/sync-storage/api/auth/change-password.php ===
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/middleware/require_auth.php';
require_once __DIR__ . '/../../../app/lib/Mailer.php';
require_once __DIR__ . '/../../../app/lib/PasswordAttemptLimiter.php';

Response::requireMethod('POST');
Response::requireCsrf();

$currentUser = $GLOBALS['__current_user'];
$body = Response::jsonBody();
$currentPassword = (string) ($body['current_password'] ?? '');
$newPassword = (string) ($body['new_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    Response::error('Current and new password are both required.', 422, 'validation_error');
}

$pdo = Database::connection();
$userId = (int) $currentUser['id'];
$attemptScope = PasswordAttemptLimiter::SCOPE_CURRENT_PASSWORD;

// f16: per-user limit on current-password guesses. The attempt is reserved
// (counted) before verification, so parallel requests cannot bypass the
// limit. If the limiter storage fails we fail CLOSED: password change is
// briefly unavailable rather than unlimited.
try {
    $attempt = PasswordAttemptLimiter::reserve($pdo, $userId, $attemptScope);
} catch (Throwable $e) {
    error_log('PasswordAttemptLimiter reserve failed for user ' . $userId . ': ' . $e->getMessage());
    Response::error('Password change is temporarily unavailable. Please try again shortly.', 503, 'service_unavailable');
}

if (!$attempt['allowed']) {
    $retryAfter = (int) $attempt['retry_after'];
    $minutes = (int) max(1, (int) ceil($retryAfter / 60));
    header('Retry-After: ' . $retryAfter);
    Response::error(
        'Too many incorrect password attempts. Please wait ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's')
        . ' and try again, or use "Forgot password" to reset it.',
        429,
        'too_many_attempts'
    );
}

try {
    Auth::changeCurrentPassword($pdo, $userId, $currentPassword, $newPassword);
} catch (InvalidArgumentException $e) {
    // Rejected for the new password, not for a wrong guess: give the attempt back.
    try {
        PasswordAttemptLimiter::refund($pdo, $userId, $attemptScope);
    } catch (Throwable $ignored) {
        error_log('PasswordAttemptLimiter refund failed for user ' . $userId . ': ' . $ignored->getMessage());
    }
    Response::error($e->getMessage(), 422, 'validation_error');
} catch (RuntimeException $e) {
    // PDOException extends RuntimeException. A database fault is not a wrong
    // guess, so it is not held against the user. The response itself is
    // unchanged from before this fix.
    if ($e instanceof PDOException) {
        try {
            PasswordAttemptLimiter::refund($pdo, $userId, $attemptScope);
        } catch (Throwable $ignored) {
            error_log('PasswordAttemptLimiter refund failed for user ' . $userId . ': ' . $ignored->getMessage());
        }
    }
    Response::error($e->getMessage(), 401, 'incorrect_password');
}

// The correct current password was proven, so clear the counter.
try {
    PasswordAttemptLimiter::reset($pdo, $userId, $attemptScope);
} catch (Throwable $e) {
    // Harmless if this fails: the counter simply expires with its window.
    error_log('PasswordAttemptLimiter reset failed for user ' . $userId . ': ' . $e->getMessage());
}

// Security notification — genuinely missing before this fix. Email-change
// already sends one; password-change (arguably the more security-sensitive
// of the two) silently didn't. No revert link here, unlike email-change:
// there's no prior password value to roll back to, and the correct
// recovery path if this wasn't the account holder is the existing
// forgot-password flow, which this notice points to directly.
try {
    Mailer::send(
        $currentUser['email'],
        'Your Traversence password was changed',
        "Your account password was just changed.\n\n"
        . "If this was you, no action is needed.\n\n"
        . "If you did NOT make this change, your account may be compromised — "
        . "reset your password immediately: " . rtrim(getenv('APP_URL') ?: '', '/') . "/forgot-password.php"
    );
} catch (Throwable $e) {
    // The password change itself already succeeded — a failed notification
    // shouldn't fail the whole request, but is logged since it's the
    // security-relevant notice for the account holder.
    error_log('Failed to send password-change notification to ' . $currentUser['email'] . ': ' . $e->getMessage());
}

Response::success(['message' => 'Password updated. You have been kept signed in on this device; other active sessions have been signed out.']);
=== END FILE ===

=== FILE: f16/files/migrations/2025_f16_password_attempt_counters.sql ===
-- f16: per-user counter for current-password checks.
-- Safe to run twice (IF NOT EXISTS). No DELIMITER. No data changes.
-- There is no foreign key to users because the users table was not shown.
-- user_id is BIGINT UNSIGNED so it covers either INT or BIGINT ids.
CREATE TABLE IF NOT EXISTS password_attempt_counters (
    user_id BIGINT UNSIGNED NOT NULL,
    scope VARCHAR(32) NOT NULL,
    failures INT UNSIGNED NOT NULL DEFAULT 0,
    window_start INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
=== END FILE ===

=== FILE: f16/tests/password-attempt-limiter-test.php ===
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
=== END FILE ===

=== FILE: f16/faq/faq-entries.md ===
### Why does it say "Too many incorrect password attempts" when I change my password?

To protect your account, you can enter your current password incorrectly only a few times in a short period. After that, password changes are paused for up to 15 minutes. The message tells you how long to wait. If you have forgotten your current password, use "Forgot password" on the sign-in page to reset it instead.

### Does the same limit apply when I change my email address?

Coming. The same protection will cover the email-change form, which also asks for your current password.
=== END FILE ===

=== FILE: f16/release.json ===
{
  "name": "f16-current-password-attempt-limit",
  "gate": "security",
  "what": "Adds a per-user limit on current-password guesses in sync-storage/api/auth/change-password.php: 5 attempts per 15-minute window, with HTTP 429, Retry-After and code too_many_attempts once exceeded. Attempts are reserved atomically before verification so parallel requests cannot bypass the limit. The counter resets on success and the attempt is refunded on validation or DB errors. Also adds a reusable PasswordAttemptLimiter and its table. request-change.php is NOT changed (file not shown).",
  "files": [
    "files/app/lib/PasswordAttemptLimiter.php",
    "files/sync-storage/api/auth/change-password.php",
    "files/migrations/2025_f16_password_attempt_counters.sql",
    "tests/password-attempt-limiter-test.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "Limit is 5 attempts per fixed 15-minute window, which allows at most about 480 guesses per day per account. A person should confirm whether this is acceptable or whether to add escalating or longer lockouts.",
    "One shared scope 'current_password' for every endpoint that checks the current password, so an attacker does not get a separate budget per endpoint.",
    "Fail CLOSED (503) if the limiter table or storage is unavailable. Password change stops working until the migration is applied. Needs a person's sign-off; the alternative is fail-open with logging.",
    "No email to the user when the lockout triggers (safest default: no new mail path). A person decides whether to add one.",
    "A PDOException from Auth::changeCurrentPassword is refunded and not counted as a guess. Its existing 401 response is kept unchanged.",
    "data_reason (runtime DELETE in reset): removes the acting user's own counter row after they prove the correct password.",
    "data_reason (runtime UPDATE in refund): decrements the acting user's own counter by 1, floored at 0, when the failure was not a wrong guess.",
    "The table has no foreign key to users because the users table was not shown. user_id is BIGINT UNSIGNED."
  ],
  "tests": [
    "tests/password-attempt-limiter-test.php (intended live path tools/auth-tests/). Pure decide() checks always run. The DB checks (reserve, block, per-user isolation, window expiry, refund floor, reset) need the migration and print SKIP otherwise."
  ],
  "risks": [
    "Nothing in this release has been run, tested or verified. It is a draft.",
    "The SQL assumes MySQL/MariaDB (ON DUPLICATE KEY UPDATE, IF(), left-to-right assignment). It will fail on SQLite or Postgres.",
    "Named placeholders are deliberately distinct (:now1/:now2, :cutoff1/:cutoff2) so they work with native prepares too.",
    "If Auth::changeCurrentPassword throws RuntimeException for reasons other than a wrong password (non-PDO), those cases are still counted as guesses.",
    "If Auth checks the new password before verifying the current one, the refund on 422 keeps that path free. If Auth checks it after, a 422 already implies the current password was correct, so the refund leaks nothing new.",
    "Fail-closed means a missed migration breaks password change for everyone (503).",
    "Response::error is assumed to terminate the request, as the original file already relied on."
  ],
  "verify": [
    "request-change.php: file not shown, so it is not changed. A person must wire the same pattern there with scope PasswordAttemptLimiter::SCOPE_CURRENT_PASSWORD: reserve before the current-password check, return 429 if not allowed, refund on non-guess errors, reset on success. Until then f16 is only half fixed.",
    "Auth::changeCurrentPassword: file not shown. Confirm that a wrong current password throws RuntimeException, a bad new password throws InvalidArgumentException, and the order in which it checks the two.",
    "Response class: file not shown. Confirm that Response::error(message, status, code) exits and that a header() call before it is kept.",
    "Database::connection(): file not shown. Confirm it returns a PDO on MySQL/MariaDB with ERRMODE_EXCEPTION.",
    "Migrations directory, naming and runner: not shown. Place 2025_f16_password_attempt_counters.sql where the project expects it, apply it BEFORE deploying the PHP (fail-closed), and run it twice to confirm it is idempotent.",
    "Confirm the live paths: sync-storage/api/auth/change-password.php and app/lib/PasswordAttemptLimiter.php (inferred from the require_once paths). The test assumes it lives at tools/auth-tests/.",
    "Person decision: the limit values (5 per 15 min), fail-closed versus fail-open, and lockout email.",
    "Front end: confirm the password-change form shows the 429 message for code 'too_many_attempts'.",
    "Zip-check that the release contains exactly the listed files."
  ],
  "regions": false
}
=== END FILE ===