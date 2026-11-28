<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';

Response::requireMethod('POST');
Response::requireCsrf();

$body = Response::jsonBody();
// brute-force limiter (finding f03): failed sign-ins are counted per IP and per typed email, and checked before the captcha and the password
require_once __DIR__ . '/../../../app/lib/LoginThrottle.php';
$throttle = new LoginThrottle();
$clientIp = LoginThrottle::clientIp($_SERVER);
$retryAfter = $throttle->retryAfter($clientIp, (string) ($body['email'] ?? ''));
if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);
    Response::error('Too many sign-in attempts. Please wait a few minutes and try again.', 429, 'rate_limited');
}
// a captcha (decisions/0097): off until its keys are in .env
require_once __DIR__ . '/../../../app/lib/Captcha.php';
require_once __DIR__ . '/../../../app/lib/TestAccounts.php';   // the site's own checks sign in a test account with their token, no captcha (decisions/0145 §3)
require_once __DIR__ . '/../../../app/lib/TestLoginSecret.php';   // finding f12: the no-captcha path also needs the server secret (TEST_LOGIN_SECRET) sent as X-Test-Login-Secret, compared with hash_equals; unset secret = no bypass
$testAccountBypass = TestLoginSecret::matches(TestLoginSecret::configured(), (string) ($_SERVER['HTTP_X_TEST_LOGIN_SECRET'] ?? ''))
    && TestAccounts::checkRequest((string) ($body['email'] ?? ''));
if (!$testAccountBypass) { try { Captcha::verify((string) ($body['captcha'] ?? '')); } catch (RuntimeException $e) { Response::error($e->getMessage(), 400, 'captcha'); } }
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    Response::error('Email and password are required.', 422, 'validation_error');
}

require_once __DIR__ . '/../../../app/lib/LoginErrorMap.php';

try {
    $user = Auth::attemptLogin($email, $password);
} catch (RuntimeException $e) {
    // finding f13: 409 'closing' (decisions/0091) and 403 'unconfirmed' (decisions/0014) are only
    // returned when LoginErrorMap::STATUS_AFTER_PASSWORD_CONFIRMED is true. Otherwise every failure is
    // one generic 401 with a fixed message, so the response does not reveal which emails have accounts.
    [$status, $errorCode, $message] = LoginErrorMap::forException((int) $e->getCode(), $e->getMessage());
    if ($status === 401) {
        $throttle->recordFailure($clientIp, $email);   // counts toward the per-IP and per-account limits (f03)
    }
    Response::error($message, $status, $errorCode);
}

$throttle->clearAccount($email);

Response::success([
    'user' => $user,
    'csrf_token' => $_SESSION['csrf_token'], // frontend attaches this as X-CSRF-Token on future mutating requests
]);
