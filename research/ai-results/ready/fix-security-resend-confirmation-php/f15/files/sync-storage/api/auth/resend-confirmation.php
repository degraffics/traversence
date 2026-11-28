<?php
// POST {email} -> {ok}: sends the confirmation link again (decisions/0014; Jason, 2026-11-05). Says the same whether or
// not the address has an account, and sends nothing for one that's already confirmed. At most 3 a session per hour,
// and (f15) server-side at most ResendLimiter::PER_EMAIL per address and ResendLimiter::PER_IP per client IP per hour.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/Mailer.php';
require_once __DIR__ . '/../../../app/lib/ResendLimiter.php';

Response::requireMethod('POST');
Response::requireCsrf();

$email = strtolower(trim((string) (Response::jsonBody()['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Response::error('Enter your email address.', 422, 'validation_error');
$tooMany = 'We\'ve sent it a few times already. Check your spam folder, or try again in an hour.';
$t = array_values(array_filter((array) ($_SESSION['tv_resend'] ?? []), fn ($x) => (int) $x > time() - 3600));
if (count($t) >= 3) Response::error($tooMany, 429, 'too_many');
$t[] = time(); $_SESSION['tv_resend'] = $t;

// Server-side limit (f15). It counts every requested address, whether or not it has an account,
// so the 429 says nothing about which addresses are registered.
// Default when the limiter itself fails (e.g. migration not applied): log and let the request
// through. The per-session limit above still applies. Whether to fail closed is a decision
// listed in release.json.
$over = null;
try {
    $over = (new ResendLimiter(Database::connection()))->hit($email, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
} catch (Throwable $e) { error_log('resend confirmation limiter: ' . $e->getMessage()); }
if ($over !== null) {
    error_log('resend confirmation: ' . $over . ' limit reached');
    Response::error($tooMany, 429, 'too_many');
}

$said = 'If that address has an account waiting to be confirmed, we\'ve sent the link again. It works for 24 hours.';
try {
    $st = Database::connection()->prepare('SELECT id, email_verified_at FROM users WHERE email = ? LIMIT 1'); $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u && $u['email_verified_at'] === null) {
        $token = Auth::issueEmailConfirmation((int) $u['id'], 'registration', 1440);
        $url = rtrim(getenv('APP_URL') ?: '', '/') . '/verify-email.php?token=' . urlencode($token);
        Mailer::send($email, 'Confirm your Traversence account',
            "Here's your link to confirm your email address:\n{$url}\n\nIt works for 24 hours. After it, two quick questions and you can sign in.\n\n"
            . "If you didn't create this account, you can ignore this email.");
    }
} catch (Throwable $e) { error_log('resend confirmation: ' . $e->getMessage()); }
Response::success(['message' => $said]);
