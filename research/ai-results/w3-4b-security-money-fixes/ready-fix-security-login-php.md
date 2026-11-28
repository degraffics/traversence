=== FILE: f03/files/app/lib/LoginThrottle.php ===
<?php

declare(strict_types=1);

/**
 * Brute-force limiter for the sign-in endpoint (finding f03).
 *
 * It keeps a sliding window of FAILED sign-ins, counted two ways:
 *   - per client IP (IPv6 grouped by /64)
 *   - per account (the typed email, trimmed and lower-cased)
 *
 * Each key is stored as a small JSON list of timestamps in its own file under
 * one directory, using flock. The account key is a hash of whatever email was
 * typed, so the code never looks up the account. A 429 therefore says nothing
 * about whether the account exists.
 *
 * If storage fails, the limiter fails OPEN (it logs and allows the request)
 * so a broken disk cannot lock everyone out. See release.json decisions.
 */
final class LoginThrottle
{
    public const IP_LIMIT = 30;          // failed sign-ins per IP per window
    public const ACCOUNT_LIMIT = 10;     // failed sign-ins per account per window
    public const WINDOW_SECONDS = 900;   // 15 minutes

    private string $dir;

    public function __construct(
        ?string $dir = null,
        private int $ipLimit = self::IP_LIMIT,
        private int $accountLimit = self::ACCOUNT_LIMIT,
        private int $window = self::WINDOW_SECONDS
    ) {
        $this->dir = rtrim($dir ?? dirname(__DIR__, 2) . '/storage/login-throttle', '/');
    }

    /** Client IP from REMOTE_ADDR only. Forwarded headers are not trusted. IPv6 is grouped to its /64. */
    public static function clientIp(array $server): string
    {
        $ip = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            return 'unknown';
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return 'unknown';
        }
        if (strlen($bin) === 16) {
            // An IPv4-mapped address (::ffff:a.b.c.d) is keyed on its IPv4 part.
            if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
                return (string) inet_ntop(substr($bin, 12, 4));
            }
            return bin2hex(substr($bin, 0, 8)) . '::/64';
        }
        return $ip;
    }

    public static function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function ipKey(string $ip): string
    {
        return 'i_' . hash('sha256', $ip);
    }

    public static function accountKey(string $email): string
    {
        return 'a_' . hash('sha256', self::normaliseEmail($email));
    }

    /** Returns the seconds the caller must wait, or 0 when a new attempt is allowed. */
    public function retryAfter(string $ip, string $email, ?int $now = null): int
    {
        $now ??= time();
        $wait = $this->waitFor(self::ipKey($ip), $this->ipLimit, $now);
        if (self::normaliseEmail($email) !== '') {
            $wait = max($wait, $this->waitFor(self::accountKey($email), $this->accountLimit, $now));
        }
        return $wait;
    }

    public function recordFailure(string $ip, string $email, ?int $now = null): void
    {
        $now ??= time();
        $this->append(self::ipKey($ip), $this->ipLimit, $now);
        if (self::normaliseEmail($email) !== '') {
            $this->append(self::accountKey($email), $this->accountLimit, $now);
        }
        if (random_int(1, 100) === 1) {
            $this->gc(time());
        }
    }

    /** After a successful sign-in, this clears the account counter. The IP counter is kept. */
    public function clearAccount(string $email): void
    {
        if (self::normaliseEmail($email) === '') {
            return;
        }
        $path = $this->path(self::accountKey($email));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Removes counter files that have not changed within one window. */
    public function gc(int $now): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $now - $this->window) {
                @unlink($file);
            }
        }
    }

    private function waitFor(string $key, int $limit, int $now): int
    {
        $times = $this->prune($this->read($key), $now);
        $count = count($times);
        if ($count < $limit) {
            return 0;
        }
        $oldestCounted = $times[$count - $limit];
        return max(1, $oldestCounted + $this->window - $now);
    }

    /** @return int[] sorted timestamps that are still inside the window */
    private function prune(array $times, int $now): array
    {
        $cut = $now - $this->window;
        $out = [];
        foreach ($times as $t) {
            if (is_int($t) && $t > $cut) {
                $out[] = $t;
            }
        }
        sort($out);
        return $out;
    }

    private function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function read(string $key): array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return [];
        }
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            error_log('LoginThrottle: cannot read ' . $path);
            return [];
        }
        try {
            if (!flock($fh, LOCK_SH)) {
                return [];
            }
            $raw = stream_get_contents($fh);
            flock($fh, LOCK_UN);
            return $this->decode($raw === false ? '' : $raw);
        } finally {
            fclose($fh);
        }
    }

    private function append(string $key, int $limit, int $now): void
    {
        if (!$this->ensureDir()) {
            return;
        }
        $path = $this->path($key);
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            error_log('LoginThrottle: cannot write ' . $path);
            return;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return;
            }
            $raw = stream_get_contents($fh);
            $times = $this->prune($this->decode($raw === false ? '' : $raw), $now);
            $times[] = $now;
            $times = array_slice($times, -max(1, $limit));
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode(array_values($times)));
            fflush($fh);
            flock($fh, LOCK_UN);
        } finally {
            fclose($fh);
        }
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dir)) {
            return true;
        }
        if (@mkdir($this->dir, 0700, true) || is_dir($this->dir)) {
            return true;
        }
        error_log('LoginThrottle: cannot create ' . $this->dir . ' (limiter is failing open)');
        return false;
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . $key . '.json';
    }
}
=== END FILE ===
=== FILE: f03/files/sync-storage/api/auth/login.php ===
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
if (!TestAccounts::checkRequest((string) ($body['email'] ?? ''))) { try { Captcha::verify((string) ($body['captcha'] ?? '')); } catch (RuntimeException $e) { Response::error($e->getMessage(), 400, 'captcha'); } }
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    Response::error('Email and password are required.', 422, 'validation_error');
}

try {
    $user = Auth::attemptLogin($email, $password);
} catch (RuntimeException $e) {
    if ($e->getCode() === 4090) Response::error($e->getMessage(), 409, 'closing');   // decisions/0091: on to /user/restore-account.php
    if ($e->getCode() === 4030) Response::error($e->getMessage(), 403, 'unconfirmed');   // decisions/0014: confirm the email first; the page offers the link again
    // Generic 401 for both "no such user" and "wrong password" — avoids
    // leaking which emails have accounts. Each one counts toward the
    // per-IP and per-account limits above (LoginThrottle, finding f03).
    $throttle->recordFailure($clientIp, $email);
    Response::error($e->getMessage(), 401, 'invalid_credentials');
}

$throttle->clearAccount($email);

Response::success([
    'user' => $user,
    'csrf_token' => $_SESSION['csrf_token'], // frontend attaches this as X-CSRF-Token on future mutating requests
]);
=== END FILE ===
=== FILE: f03/tests/login-throttle-test.php ===
<?php

declare(strict_types=1);

// Plain PHP test for f03. Run: php login-throttle-test.php  (prints PASS/FAIL lines)

$lib = __DIR__ . '/../files/app/lib/LoginThrottle.php';
if (!is_file($lib)) { $lib = __DIR__ . '/../../app/lib/LoginThrottle.php'; }
$login = __DIR__ . '/../files/sync-storage/api/auth/login.php';
if (!is_file($login)) { $login = __DIR__ . '/../../sync-storage/api/auth/login.php'; }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . "\n";
    if (!$ok) { $fails++; }
}

$dir = sys_get_temp_dir() . '/login-throttle-test-' . bin2hex(random_bytes(4));
$t = new LoginThrottle($dir, 5, 3, 60);
$now = 1_000_000;

check('fresh IP and account allowed', $t->retryAfter('1.2.3.4', 'a@example.com', $now) === 0);

for ($i = 0; $i < 2; $i++) { $t->recordFailure('1.2.3.4', 'a@example.com', $now + $i); }
check('under account limit still allowed', $t->retryAfter('1.2.3.4', 'a@example.com', $now + 2) === 0);
$t->recordFailure('1.2.3.4', 'a@example.com', $now + 2);
$w = $t->retryAfter('1.2.3.4', 'a@example.com', $now + 3);
check('account blocked at limit', $w > 0 && $w <= 60);
check('account block applies from another IP', $t->retryAfter('9.9.9.9', 'a@example.com', $now + 3) > 0);
check('email normalised (case, spaces)', $t->retryAfter('9.9.9.9', '  A@Example.COM ', $now + 3) > 0);
check('other account from same IP not yet blocked', $t->retryAfter('1.2.3.4', 'b@example.com', $now + 3) === 0);
check('account allowed again after window', $t->retryAfter('9.9.9.9', 'a@example.com', $now + 61) === 0);

$t->recordFailure('1.2.3.4', 'c@example.com', $now + 4);
$t->recordFailure('1.2.3.4', 'd@example.com', $now + 5);
check('IP blocked after 5 failures across emails', $t->retryAfter('1.2.3.4', 'new@example.com', $now + 6) > 0);
check('IP block with empty email', $t->retryAfter('1.2.3.4', '', $now + 6) > 0);

$t2 = new LoginThrottle($dir . '-b', 100, 2, 60);
$t2->recordFailure('5.5.5.5', 'x@example.com', $now);
$t2->recordFailure('5.5.5.5', 'x@example.com', $now);
check('blocked before clear', $t2->retryAfter('6.6.6.6', 'x@example.com', $now + 1) > 0);
$t2->clearAccount('x@example.com');
check('clearAccount lifts account block', $t2->retryAfter('6.6.6.6', 'x@example.com', $now + 1) === 0);

check('IPv6 grouped by /64', LoginThrottle::clientIp(['REMOTE_ADDR' => '2001:db8:1:2::1']) === LoginThrottle::clientIp(['REMOTE_ADDR' => '2001:db8:1:2:ffff::9']));
check('IPv6 different /64 differ', LoginThrottle::clientIp(['REMOTE_ADDR' => '2001:db8:1:2::1']) !== LoginThrottle::clientIp(['REMOTE_ADDR' => '2001:db8:1:3::1']));
check('IPv4-mapped keyed as IPv4', LoginThrottle::clientIp(['REMOTE_ADDR' => '::ffff:10.0.0.7']) === '10.0.0.7');
check('missing REMOTE_ADDR -> unknown', LoginThrottle::clientIp([]) === 'unknown');
check('X-Forwarded-For ignored', LoginThrottle::clientIp(['REMOTE_ADDR' => '1.1.1.1', 'HTTP_X_FORWARDED_FOR' => '2.2.2.2']) === '1.1.1.1');

$blocker = tempnam(sys_get_temp_dir(), 'ltf');
$bad = new LoginThrottle($blocker . '/sub', 1, 1, 60);
$prevLog = ini_set('error_log', '/dev/null');
$bad->recordFailure('7.7.7.7', 'z@example.com', $now);
check('unwritable storage fails open', $bad->retryAfter('7.7.7.7', 'z@example.com', $now) === 0);
if ($prevLog !== false) { ini_set('error_log', (string) $prevLog); }
@unlink($blocker);

$src = (string) @file_get_contents($login);
check('login.php found', $src !== '');
$pThrottle = strpos($src, '->retryAfter(');
$pCaptcha = strpos($src, 'Captcha::verify(');
check('limit checked before captcha', $pThrottle !== false && $pCaptcha !== false && $pThrottle < $pCaptcha);
check('login.php returns 429', str_contains($src, "429, 'rate_limited'"));
check('login.php sends Retry-After', str_contains($src, "header('Retry-After: '"));
$pRec = strpos($src, '->recordFailure(');
$p401 = strpos($src, "401, 'invalid_credentials'");
check('failure recorded before 401', $pRec !== false && $p401 !== false && $pRec < $p401);
check('success clears account counter', str_contains($src, '$throttle->clearAccount($email);'));

foreach ([$dir, $dir . '-b'] as $d) {
    foreach (glob($d . '/*.json') ?: [] as $f) { @unlink($f); }
    @rmdir($d);
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f03/faq/faq-entries.md ===
### Why does sign-in say "Too many sign-in attempts"?

To protect your account, sign-in pauses after several wrong passwords. This happens when the wrong passwords are typed for the same email address, or come from the same internet connection. The pause lasts up to 15 minutes. Wait a few minutes, then try again with the correct password. The message looks the same whether or not an account exists for that email.
=== END FILE ===
=== FILE: f03/release.json ===
{
  "name": "f03-login-brute-force-limiter",
  "gate": "high - ship first. f12 and f13 are stacked on top of this login.php.",
  "what": "Adds a sliding-window limiter for failed sign-ins to the login endpoint, counted per client IP and per typed email. When either limit is reached, the endpoint returns HTTP 429 'rate_limited' with Retry-After. This check runs before the captcha and before Auth::attemptLogin. Each 401 records a failure. A successful sign-in clears that account's counter.",
  "files": [
    "files/app/lib/LoginThrottle.php",
    "files/sync-storage/api/auth/login.php"
  ],
  "decisions": [
    "Limits: 30 failures per IP and 10 per account in 15 minutes (constants in LoginThrottle). A person should confirm these numbers.",
    "The client IP comes from REMOTE_ADDR only, and X-Forwarded-For is ignored. Behind a proxy or CDN, every visitor would share one IP bucket. If the site runs behind one, a person must decide which trusted header to use.",
    "Only 401 (invalid_credentials) counts as a failure. 409/403/422/400 do not count (f13 later folds 409/403 into 401 by default).",
    "The per-account limit lets an attacker lock a real user out for up to 15 minutes. This is accepted as the tradeoff, and the lockout is temporary.",
    "Test accounts (TestAccounts bypass) are NOT exempt from the limiter.",
    "Storage is flock'd JSON files under <root>/storage/login-throttle (no DB, because the DB layer is not shown). The limiter fails OPEN with error_log if the directory is not writable."
  ],
  "tests": ["tests/login-throttle-test.php"],
  "risks": [
    "Nothing here has been run, tested or zip-checked. This is a draft.",
    "Assumes Response::error() ends the request, as the existing code already relies on.",
    "File storage does not work across multiple web servers. A shared store (DB or Redis) is needed if there is more than one server.",
    "Counter files build up. A 1% random gc removes stale ones.",
    "header('Retry-After') could be overridden or dropped if Response::error clears headers (Response not shown)."
  ],
  "verify": [
    "Live path assumed to be sync-storage/api/auth/login.php, inferred from the attachment name and require paths. Confirm before copying.",
    "app/bootstrap.php, Response, Auth, Captcha and TestAccounts: file not shown.",
    "Confirm <root>/storage/ is writable by PHP and NOT web-served (the files hold only hashes and timestamps).",
    "PERSON'S DECISION: the limit numbers, and proxy/trusted-IP handling.",
    "Run php tests/login-throttle-test.php, then manually make 11 wrong-password attempts and expect a 429 with Retry-After."
  ],
  "regions": false
}
=== END FILE ===
=== FILE: f12/files/app/lib/TestLoginSecret.php ===
<?php

declare(strict_types=1);

/**
 * Finding f12: the captcha bypass for test accounts must also present a server
 * secret. The bypass was keyed on the email alone.
 *
 * Secret: env TEST_LOGIN_SECRET (at least 32 chars). Request header: X-Test-Login-Secret.
 * If the secret is unset or too short, the bypass is OFF and the captcha always applies.
 */
final class TestLoginSecret
{
    public const MIN_LENGTH = 32;
    public const ENV_NAME = 'TEST_LOGIN_SECRET';

    public static function configured(): string
    {
        $v = getenv(self::ENV_NAME);
        if ($v === false || $v === '') {
            $v = $_ENV[self::ENV_NAME] ?? $_SERVER[self::ENV_NAME] ?? '';
        }
        return is_string($v) ? trim($v) : '';
    }

    public static function matches(string $configured, string $given): bool
    {
        if (strlen($configured) < self::MIN_LENGTH || $given === '') {
            return false;
        }
        return hash_equals($configured, $given);
    }
}
=== END FILE ===
=== FILE: f12/files/sync-storage/api/auth/login.php ===
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

try {
    $user = Auth::attemptLogin($email, $password);
} catch (RuntimeException $e) {
    if ($e->getCode() === 4090) Response::error($e->getMessage(), 409, 'closing');   // decisions/0091: on to /user/restore-account.php
    if ($e->getCode() === 4030) Response::error($e->getMessage(), 403, 'unconfirmed');   // decisions/0014: confirm the email first; the page offers the link again
    // Generic 401 for both "no such user" and "wrong password" — avoids
    // leaking which emails have accounts. Each one counts toward the
    // per-IP and per-account limits above (LoginThrottle, finding f03).
    $throttle->recordFailure($clientIp, $email);
    Response::error($e->getMessage(), 401, 'invalid_credentials');
}

$throttle->clearAccount($email);

Response::success([
    'user' => $user,
    'csrf_token' => $_SESSION['csrf_token'], // frontend attaches this as X-CSRF-Token on future mutating requests
]);
=== END FILE ===
=== FILE: f12/tests/test-login-secret-test.php ===
<?php

declare(strict_types=1);

// Plain PHP test for f12. Run: php test-login-secret-test.php

$lib = __DIR__ . '/../files/app/lib/TestLoginSecret.php';
if (!is_file($lib)) { $lib = __DIR__ . '/../../app/lib/TestLoginSecret.php'; }
$login = __DIR__ . '/../files/sync-storage/api/auth/login.php';
if (!is_file($login)) { $login = __DIR__ . '/../../sync-storage/api/auth/login.php'; }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . "\n";
    if (!$ok) { $fails++; }
}

$good = str_repeat('k', 40);
check('matching secret accepted', TestLoginSecret::matches($good, $good));
check('wrong secret rejected', !TestLoginSecret::matches($good, str_repeat('j', 40)));
check('empty given rejected', !TestLoginSecret::matches($good, ''));
check('unset configured rejected', !TestLoginSecret::matches('', ''));
check('short configured secret disables bypass', !TestLoginSecret::matches('short', 'short'));
check('prefix of secret rejected', !TestLoginSecret::matches($good, substr($good, 0, 39)));

putenv('TEST_LOGIN_SECRET');
unset($_ENV['TEST_LOGIN_SECRET'], $_SERVER['TEST_LOGIN_SECRET']);
check('configured() empty when unset', TestLoginSecret::configured() === '');
putenv('TEST_LOGIN_SECRET=' . $good);
check('configured() reads env', TestLoginSecret::configured() === $good);
putenv('TEST_LOGIN_SECRET');

$libSrc = (string) file_get_contents($lib);
check('library uses hash_equals', str_contains($libSrc, 'hash_equals('));

$src = (string) @file_get_contents($login);
check('login.php found', $src !== '');
$pSecret = strpos($src, 'TestLoginSecret::matches(');
$pCheck = strpos($src, 'TestAccounts::checkRequest(');
check('secret checked before (and ANDed with) TestAccounts', $pSecret !== false && $pCheck !== false && $pSecret < $pCheck && str_contains($src, "\n    && TestAccounts::checkRequest("));
check('old email-only bypass removed', !str_contains($src, "if (!TestAccounts::checkRequest("));
check('f03 limiter still present', str_contains($src, "429, 'rate_limited'"));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f12/faq/faq-entries.md ===
no faq change
=== END FILE ===
=== FILE: f12/release.json ===
{
  "name": "f12-test-account-captcha-bypass-secret",
  "gate": "med - stacked on f03 (this login.php includes the f03 limiter). Ship after f03.",
  "what": "The test-account captcha bypass now also requires the X-Test-Login-Secret request header. The header is compared with hash_equals against the TEST_LOGIN_SECRET server env value (at least 32 chars). The secret is checked first, so TestAccounts::checkRequest is only reached with a valid secret. If the env value is unset or short, there is no bypass and the captcha always applies.",
  "files": [
    "files/app/lib/TestLoginSecret.php",
    "files/sync-storage/api/auth/login.php"
  ],
  "decisions": [
    "Safest default: fail closed. If TEST_LOGIN_SECRET is missing, the bypass is disabled.",
    "PERSON'S DECISION: the header name X-Test-Login-Secret and env name TEST_LOGIN_SECRET. The site's own checks (decisions/0145 §3) must be updated to send the header, or their sign-ins will need a captcha once the captcha keys are set.",
    "The existing TestAccounts::checkRequest is kept and ANDed with the secret, not replaced."
  ],
  "tests": ["tests/test-login-secret-test.php"],
  "risks": [
    "Nothing here has been run, tested or zip-checked. This is a draft.",
    "If TestAccounts::checkRequest already checks a token internally, this adds a second factor. That is harmless, but the token flow is unchanged.",
    "How .env is loaded is unknown. configured() reads getenv, then $_ENV, then $_SERVER. If bootstrap loads .env some other way, the secret will look unset and the bypass will stay off.",
    "While the captcha keys are unset, Captcha::verify presumably passes anyway, so there is no visible change until the keys are set."
  ],
  "verify": [
    "app/lib/TestAccounts.php: file not shown. The finding says '(verify)': check whether checkRequest really keys on the email alone.",
    "app/bootstrap.php / .env loading: file not shown. Confirm getenv('TEST_LOGIN_SECRET') sees the .env value.",
    "Live path assumed to be sync-storage/api/auth/login.php.",
    "Add TEST_LOGIN_SECRET (32+ random chars, e.g. bin2hex(random_bytes(32))) to .env and to the site checks' config, never to the repo.",
    "Run php tests/test-login-secret-test.php."
  ],
  "regions": false
}
=== END FILE ===
=== FILE: f13/files/app/lib/LoginErrorMap.php ===
<?php

declare(strict_types=1);

/**
 * Finding f13: map Auth::attemptLogin failures to HTTP responses without
 * revealing whether an account exists.
 *
 * 409 'closing' and 403 'unconfirmed' can only be shown safely if
 * Auth::attemptLogin verifies the password BEFORE it throws 4090/4030.
 * Auth is not shown, so the default treats them as a generic 401.
 */
final class LoginErrorMap
{
    // PERSON'S DECISION (f13): set to true only after confirming that Auth::attemptLogin
    // runs password_verify before throwing code 4090 or 4030.
    public const STATUS_AFTER_PASSWORD_CONFIRMED = false;

    public const GENERIC_MESSAGE = 'Incorrect email or password.';

    /** @return array{0:int,1:string,2:string} [http status, error code, message] */
    public static function forException(int $code, string $message, bool $statusAfterPassword = self::STATUS_AFTER_PASSWORD_CONFIRMED): array
    {
        if ($statusAfterPassword && $code === 4090) {
            return [409, 'closing', $message];      // decisions/0091: on to /user/restore-account.php
        }
        if ($statusAfterPassword && $code === 4030) {
            return [403, 'unconfirmed', $message];  // decisions/0014: confirm the email first
        }
        // One fixed message for every 401, so the text cannot differ between "no such user" and "wrong password".
        return [401, 'invalid_credentials', self::GENERIC_MESSAGE];
    }
}
=== END FILE ===
=== FILE: f13/files/sync-storage/api/auth/login.php ===
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
=== END FILE ===
=== FILE: f13/tests/login-error-map-test.php ===
<?php

declare(strict_types=1);

// Plain PHP test for f13. Run: php login-error-map-test.php

$lib = __DIR__ . '/../files/app/lib/LoginErrorMap.php';
if (!is_file($lib)) { $lib = __DIR__ . '/../../app/lib/LoginErrorMap.php'; }
$login = __DIR__ . '/../files/sync-storage/api/auth/login.php';
if (!is_file($login)) { $login = __DIR__ . '/../../sync-storage/api/auth/login.php'; }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . "\n";
    if (!$ok) { $fails++; }
}

$g = LoginErrorMap::GENERIC_MESSAGE;

check('default flag is false (safest)', LoginErrorMap::STATUS_AFTER_PASSWORD_CONFIRMED === false);
check('default: 4090 -> generic 401', LoginErrorMap::forException(4090, 'Account is closing') === [401, 'invalid_credentials', $g]);
check('default: 4030 -> generic 401', LoginErrorMap::forException(4030, 'Please confirm your email') === [401, 'invalid_credentials', $g]);
check('unknown user -> generic 401, fixed text', LoginErrorMap::forException(0, 'No such user') === [401, 'invalid_credentials', $g]);
check('wrong password -> same response as unknown user', LoginErrorMap::forException(0, 'Wrong password') === LoginErrorMap::forException(0, 'No such user'));
check('flag on: 4090 -> 409 closing', LoginErrorMap::forException(4090, 'Closing', true) === [409, 'closing', 'Closing']);
check('flag on: 4030 -> 403 unconfirmed', LoginErrorMap::forException(4030, 'Unconfirmed', true) === [403, 'unconfirmed', 'Unconfirmed']);
check('flag on: other -> generic 401', LoginErrorMap::forException(1, 'x', true) === [401, 'invalid_credentials', $g]);

$src = (string) @file_get_contents($login);
check('login.php found', $src !== '');
check('old 409 passthrough removed', !str_contains($src, "Response::error(\$e->getMessage(), 409, 'closing')"));
check('old 403 passthrough removed', !str_contains($src, "Response::error(\$e->getMessage(), 403, 'unconfirmed')"));
check('old raw-message 401 removed', !str_contains($src, "Response::error(\$e->getMessage(), 401, 'invalid_credentials')"));
check('uses LoginErrorMap', str_contains($src, 'LoginErrorMap::forException('));
check('401 still counted by limiter', str_contains($src, '$throttle->recordFailure($clientIp, $email);'));
check('f12 secret check still present', str_contains($src, 'TestLoginSecret::matches('));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f13/faq/faq-entries.md ===
### Sign-in says "Incorrect email or password" but I'm sure my password is right

For your security, sign-in does not say whether an account exists for an email address. It shows the same message whenever sign-in can't go ahead. That includes accounts whose email address hasn't been confirmed yet, and accounts that are being closed.

- **Just signed up?** Look in your inbox (and spam folder) for the confirmation email, and open its link first. A way to request a new confirmation link from this page is *coming*.
- **Asked to close your account?** Your account may be in its closing period. A way to restore it from sign-in is *coming*.
- **Still stuck?** Check the email address for typos. After several wrong tries, sign-in pauses for a few minutes.
=== END FILE ===
=== FILE: f13/release.json ===
{
  "name": "f13-login-no-account-status-leak",
  "gate": "med - stacked on f03 and f12 (this login.php includes both). Ship after f12.",
  "what": "The 409 'closing' and 403 'unconfirmed' responses no longer reveal account status before the password is known to be right. By default, every Auth::attemptLogin failure returns one 401 'invalid_credentials' with the fixed message 'Incorrect email or password.', and each counts toward the f03 limiter. The 409 and 403 responses come back only if a person confirms that Auth verifies the password before throwing 4090/4030, and sets LoginErrorMap::STATUS_AFTER_PASSWORD_CONFIRMED = true.",
  "files": [
    "files/app/lib/LoginErrorMap.php",
    "files/sync-storage/api/auth/login.php"
  ],
  "decisions": [
    "PERSON'S DECISION: read Auth::attemptLogin (file not shown). If it throws 4090/4030 only after password_verify succeeds, set STATUS_AFTER_PASSWORD_CONFIRMED = true to restore the decisions/0091 and decisions/0014 flows. Otherwise, either leave the flag false (the shipped default) or change Auth to check the password first, then set it true.",
    "With the default (false), unconfirmed and closing users lose the in-page 'resend link' and 'restore account' prompts. The FAQ marks those as coming.",
    "Every 401 now uses one fixed message instead of $e->getMessage(), so differing Auth messages can't reveal account existence. Non-auth RuntimeExceptions (e.g. a DB error) also become a generic 401 and count as a failure."
  ],
  "tests": ["tests/login-error-map-test.php"],
  "risks": [
    "Nothing here has been run, tested or zip-checked. This is a draft.",
    "Timing can still reveal accounts if Auth skips password_verify for unknown emails (Auth not shown).",
    "The frontend may branch on 409/403 to redirect to /user/restore-account.php or offer the confirm link. With the default, those branches never fire (frontend not shown).",
    "Other endpoints (signup, password reset, restore-account) may still reveal account existence. They are out of scope and not shown."
  ],
  "verify": [
    "app/lib/Auth.php: file not shown. The finding says '(verify)': check the order of the password check against the 4090/4030 throws.",
    "Frontend login page handling of 409/403: file not shown.",
    "Live path assumed to be sync-storage/api/auth/login.php.",
    "Run php tests/login-error-map-test.php. Then manually sign in to an unconfirmed account with a wrong password and expect the generic 401."
  ],
  "regions": false
}
=== END FILE ===