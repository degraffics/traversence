<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../app/bootstrap.php';
require_once __DIR__ . '/../../../../app/lib/Mailer.php';
require_once __DIR__ . '/../../../../app/lib/RateLimiter.php';

// f14: throttle limits. These are the safest defaults; a person must confirm
// them (see release.json verify).
const RESET_IP_MAX        = 10;   // requests per IP ...
const RESET_IP_WINDOW     = 3600; // ... per hour
const RESET_TARGET_MAX    = 3;    // reset emails per address ...
const RESET_TARGET_WINDOW = 3600; // ... per hour

Response::requireMethod('POST');
Response::requireCsrf();

$body = Response::jsonBody();
// a captcha (decisions/0097): off until its keys are in .env
require_once __DIR__ . '/../../../../app/lib/Captcha.php';
try { Captcha::verify((string) ($body['captcha'] ?? '')); } catch (RuntimeException $e) { Response::error($e->getMessage(), 400, 'captcha'); }
$email = trim((string) ($body['email'] ?? ''));

if ($email === '') {
    Response::error('Email is required.', 422, 'validation_error');
}

// f14: throttling applies whether or not the captcha is on.
RateLimiter::maybeGc();

// Per-IP limit. A 429 here reveals nothing about whether an account exists.
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if ($clientIp === '') {
    $clientIp = 'unknown';
}
if (!RateLimiter::hit('reset-ip', $clientIp, RESET_IP_MAX, RESET_IP_WINDOW)) {
    if (!headers_sent()) {
        header('Retry-After: ' . RESET_IP_WINDOW);
    }
    Response::error('Too many password reset requests. Please try again later.', 429, 'rate_limited');
}

// Per-target limit. When it is exceeded we silently skip the token and the
// email, then return the same generic success below. The response therefore
// still never reveals whether the address has an account.
$targetAllowed = RateLimiter::hit('reset-target', strtolower($email), RESET_TARGET_MAX, RESET_TARGET_WINDOW);

$token = null;
if ($targetAllowed) {
    $token = Auth::requestPasswordReset($email);
} else {
    error_log('Password reset throttled for target hash ' . substr(hash('sha256', strtolower($email)), 0, 16));
}

if ($token !== null) {
    $resetUrl = rtrim(getenv('APP_URL') ?: '', '/') . '/reset-password.php?token=' . urlencode($token);
    try {
        Mailer::send(
            $email,
            'Reset your Traversence password',
            "A password reset was requested for your account.\n\nVisit this link to set a new password:\n{$resetUrl}\n\n"
            . "This link expires in 30 minutes. If you didn't request this, you can ignore this email — your password won't change."
        );
    } catch (Throwable $e) {
        error_log('Failed to send password reset email to ' . $email . ': ' . $e->getMessage());
    }
}
// Response is identical whether or not $token was null (no account found),
// the per-target throttle was hit, or the send failed. This endpoint never
// reveals whether the email address has an account, and a send failure
// shouldn't leak that either.

Response::success([
    'message' => 'If an account exists for that email, a password reset link has been sent.',
]);
