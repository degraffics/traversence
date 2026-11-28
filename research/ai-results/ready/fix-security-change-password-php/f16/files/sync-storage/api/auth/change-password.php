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
