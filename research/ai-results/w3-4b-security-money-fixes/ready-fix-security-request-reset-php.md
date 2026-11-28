Highest risk first: there is only one finding, f14 (medium). Nothing below has been run, tested or zip-checked. The lane session must do all of that.

=== FILE: f14/files/app/lib/RateLimiter.php ===
<?php

declare(strict_types=1);

/**
 * Small file-backed sliding-window rate limiter (finding f14).
 *
 * - Keys are stored only as sha256 hashes, so no plaintext emails or IPs
 *   are written to disk.
 * - Blocked attempts are NOT recorded. A sender cannot extend their own
 *   lockout by retrying, and the window frees up on schedule.
 * - It fails OPEN: if storage is unavailable, the request is allowed and an
 *   error is logged. Broken storage then never stops legitimate password
 *   resets (see release.json decisions).
 *
 * Storage dir: RATE_LIMIT_DIR from the environment. Otherwise
 * <sys temp dir>/traversence-ratelimit.
 */
final class RateLimiter
{
    private static ?string $dirOverride = null;

    /** Test hook: force a storage directory (null = back to env/default). */
    public static function setDirectory(?string $dir): void
    {
        self::$dirOverride = $dir;
    }

    public static function directory(): string
    {
        if (self::$dirOverride !== null) {
            return rtrim(self::$dirOverride, '/');
        }
        $env = getenv('RATE_LIMIT_DIR');
        if (is_string($env) && $env !== '') {
            return rtrim($env, '/');
        }
        return rtrim(sys_get_temp_dir(), '/') . '/traversence-ratelimit';
    }

    /**
     * Try to record one attempt for ($bucket, $key).
     *
     * Returns true when the attempt is within $max per $windowSeconds and has
     * been recorded. Returns false when the limit is already reached; nothing
     * is recorded in that case.
     */
    public static function hit(string $bucket, string $key, int $max, int $windowSeconds, ?int $now = null): bool
    {
        if ($max < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException('RateLimiter: max and windowSeconds must be >= 1.');
        }
        $now = $now ?? time();

        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log('RateLimiter: cannot create storage dir ' . $dir . ' — failing open.');
            return true;
        }

        $file = self::fileFor($bucket, $key);
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            error_log('RateLimiter: cannot open ' . $file . ' — failing open.');
            return true;
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                error_log('RateLimiter: cannot lock ' . $file . ' — failing open.');
                return true;
            }

            $raw = stream_get_contents($fh);
            $stamps = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $t) {
                        if (is_int($t) && $t > $now - $windowSeconds && $t <= $now) {
                            $stamps[] = $t;
                        }
                    }
                }
            }

            $allowed = count($stamps) < $max;
            if ($allowed) {
                $stamps[] = $now;
            }

            // Write back the pruned list, plus the new stamp if allowed.
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode(array_values($stamps)));
            fflush($fh);
            flock($fh, LOCK_UN);

            return $allowed;
        } finally {
            fclose($fh);
        }

        // Opportunistic GC is done by the caller via maybeGc().
    }

    /** Remove limiter files not touched for $maxAgeSeconds. Returns count removed. */
    public static function gc(int $maxAgeSeconds = 86400, ?int $now = null): int
    {
        $now = $now ?? time();
        $dir = self::directory();
        if (!is_dir($dir)) {
            return 0;
        }
        $removed = 0;
        $files = glob($dir . '/*.json');
        if ($files === false) {
            return 0;
        }
        foreach ($files as $f) {
            $mtime = @filemtime($f);
            if ($mtime !== false && $mtime < $now - $maxAgeSeconds) {
                if (@unlink($f)) {
                    $removed++;
                }
            }
        }
        return $removed;
    }

    /** Run gc() on roughly 1 in $oneIn calls. */
    public static function maybeGc(int $oneIn = 100): void
    {
        if ($oneIn > 0 && random_int(1, $oneIn) === 1) {
            self::gc();
        }
    }

    /** Path of the storage file for ($bucket, $key). Public for tests. */
    public static function fileFor(string $bucket, string $key): string
    {
        $safeBucket = preg_replace('/[^A-Za-z0-9_-]/', '', $bucket);
        return self::directory() . '/' . $safeBucket . '-' . hash('sha256', $bucket . "\0" . $key) . '.json';
    }
}
=== END FILE ===

=== FILE: f14/files/sync-storage/api/auth/password/request-reset.php ===
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
=== END FILE ===

=== FILE: f14/tests/ratelimiter-test.php ===
<?php

declare(strict_types=1);

// Plain PHP test for RateLimiter (finding f14). Run: php ratelimiter-test.php
// Works from the release folder (tests/) or once deployed under tools/auth-tests/.

$candidates = [
    __DIR__ . '/../files/app/lib/RateLimiter.php',
    __DIR__ . '/../../app/lib/RateLimiter.php',
];
$loaded = false;
foreach ($candidates as $c) {
    if (is_file($c)) { require_once $c; $loaded = true; break; }
}
if (!$loaded) { echo "FAIL: RateLimiter.php not found\n"; exit(1); }

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    if (!$ok) { $fails++; }
}

$dir = sys_get_temp_dir() . '/rl-test-' . bin2hex(random_bytes(6));
RateLimiter::setDirectory($dir);
$t0 = 1700000000;

// 1. Allows up to max, then blocks.
$results = [];
for ($i = 0; $i < 4; $i++) {
    $results[] = RateLimiter::hit('reset-target', 'a@example.com', 3, 3600, $t0 + $i);
}
check('first 3 allowed', $results[0] && $results[1] && $results[2]);
check('4th blocked', $results[3] === false);

// 2. A blocked attempt is not recorded, so the window frees on schedule.
check('still blocked inside window', RateLimiter::hit('reset-target', 'a@example.com', 3, 3600, $t0 + 3599) === false);
check('allowed after first stamp expires', RateLimiter::hit('reset-target', 'a@example.com', 3, 3600, $t0 + 3601) === true);

// 3. Keys and buckets are independent.
check('other key independent', RateLimiter::hit('reset-target', 'b@example.com', 3, 3600, $t0) === true);
check('other bucket independent', RateLimiter::hit('reset-ip', 'a@example.com', 1, 3600, $t0) === true);
check('ip bucket max 1 blocks 2nd', RateLimiter::hit('reset-ip', 'a@example.com', 1, 3600, $t0 + 1) === false);

// 4. No plaintext key on disk.
$file = RateLimiter::fileFor('reset-target', 'a@example.com');
check('file exists', is_file($file));
check('filename has no plaintext email', strpos($file, 'example.com') === false);
check('content has no plaintext email', strpos((string) file_get_contents($file), 'example') === false);

// 5. Corrupt file is treated as empty.
file_put_contents(RateLimiter::fileFor('reset-target', 'c@example.com'), 'not json');
check('corrupt file tolerated', RateLimiter::hit('reset-target', 'c@example.com', 1, 3600, $t0) === true);

// 6. Invalid args are rejected.
$threw = false;
try { RateLimiter::hit('x', 'y', 0, 10, $t0); } catch (InvalidArgumentException $e) { $threw = true; }
check('max=0 rejected', $threw);

// 7. gc removes old files.
touch($file, $t0 - 200000);
$removed = RateLimiter::gc(86400, $t0);
check('gc removed stale file', $removed >= 1 && !is_file($file));

// 8. Fails open when storage cannot be created (path under a regular file).
$blocker = $dir . '/blocker-file';
file_put_contents($blocker, 'x');
RateLimiter::setDirectory($blocker . '/sub');
check('fails open on unusable dir', @RateLimiter::hit('reset-ip', '1.2.3.4', 1, 60, $t0) === true);
RateLimiter::setDirectory($dir);

// Cleanup.
foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($dir);
RateLimiter::setDirectory(null);

echo $fails === 0 ? "ALL PASS\n" : "{$fails} FAIL(S)\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f14/tests/request-reset-static-test.php ===
<?php

declare(strict_types=1);

// Static check of the patched endpoint (finding f14). It does not boot the app.
// Run: php request-reset-static-test.php

$candidates = [
    __DIR__ . '/../files/sync-storage/api/auth/password/request-reset.php',
    __DIR__ . '/../../sync-storage/api/auth/password/request-reset.php',
];
$src = null;
foreach ($candidates as $c) {
    if (is_file($c)) { $src = (string) file_get_contents($c); break; }
}
if ($src === null) { echo "FAIL: request-reset.php not found\n"; exit(1); }

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    if (!$ok) { $fails++; }
}

$ipPos     = strpos($src, "RateLimiter::hit('reset-ip'");
$targetPos = strpos($src, "RateLimiter::hit('reset-target'");
$authPos   = strpos($src, 'Auth::requestPasswordReset(');
$mailPos   = strpos($src, 'Mailer::send(');

check('requires RateLimiter', strpos($src, "app/lib/RateLimiter.php") !== false);
check('per-IP limit present', $ipPos !== false);
check('per-target limit present', $targetPos !== false);
check('IP check before target check', $ipPos !== false && $targetPos !== false && $ipPos < $targetPos);
check('target check before token creation', $targetPos !== false && $authPos !== false && $targetPos < $authPos);
check('token creation guarded by $targetAllowed', (bool) preg_match('/if\s*\(\s*\$targetAllowed\s*\)\s*\{\s*\$token\s*=\s*Auth::requestPasswordReset/', $src));
check('mail still after token', $authPos !== false && $mailPos !== false && $authPos < $mailPos);
check('429 on IP limit', strpos($src, "429, 'rate_limited'") !== false);
check('generic success message unchanged', strpos($src, "'If an account exists for that email, a password reset link has been sent.'") !== false);
check('lint ok', (function () use ($candidates): bool {
    foreach ($candidates as $c) {
        if (is_file($c)) {
            $out = [];
            $rc = 1;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($c) . ' 2>&1', $out, $rc);
            return $rc === 0;
        }
    }
    return false;
})());

echo $fails === 0 ? "ALL PASS\n" : "{$fails} FAIL(S)\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f14/faq/faq-entries.md ===
### I asked for several password reset emails but stopped receiving them. Why?

To protect inboxes from being flooded, we send at most 3 password reset emails to the same address in any one hour. If you've reached that limit, use the most recent email you received. If none arrived, wait an hour and try again.

Check your spam or junk folder too. Each reset link expires 30 minutes after it is sent.

### I see "Too many password reset requests. Please try again later."

Too many reset requests have come from your network in a short time. Wait up to an hour and try again.

If you're on a shared network, such as an office, school or public Wi-Fi, other people's requests may count toward the same limit. Trying from a different network usually helps.

### Is there a limit on creating accounts too?

coming
=== END FILE ===

=== FILE: f14/release.json ===
{
  "name": "f14-throttle-password-reset",
  "gate": "security-med",
  "what": "Adds per-IP and per-target throttling to the password-reset request endpoint. The limits apply whether or not the captcha is on, which stops mail bombing. The per-IP limit returns 429 with Retry-After. The per-target (email) limit silently skips token creation and email sending and returns the same generic success, so account existence is still not revealed. The new reusable app/lib/RateLimiter.php is file-backed and stores keys as hashes only. Register is NOT changed in this release because its file was not attached.",
  "files": [
    "files/app/lib/RateLimiter.php",
    "files/sync-storage/api/auth/password/request-reset.php",
    "tests/ratelimiter-test.php",
    "tests/request-reset-static-test.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "Default limits: 10 requests per IP per hour and 3 reset emails per address per hour. A person must confirm these.",
    "Throttling is always on, not only while the captcha is off. This is the safest default and the captcha stays as decisions/0097 describes.",
    "Per-target limit hit: return the identical generic success and do not reveal it. Per-IP limit hit: HTTP 429 'rate_limited', which reveals nothing about accounts.",
    "Blocked attempts are not recorded, so a sender cannot extend a lockout by retrying.",
    "Storage fails OPEN with error_log when the dir is unusable, so password resets are never broken. The alternative, fail-closed, needs a person's decision.",
    "The client IP is REMOTE_ADDR only. X-Forwarded-For is NOT trusted by default, because spoofing it would bypass the limit.",
    "File-backed storage, not DB: the DB layer/schema was not shown, so no migration was invented. Default dir is RATE_LIMIT_DIR, else the sys temp dir plus /traversence-ratelimit.",
    "The register endpoint is out of scope here because its file was not shown. RateLimiter is designed so register can call RateLimiter::hit('register-ip', ...) and RateLimiter::hit('register-target', ...) in a follow-up."
  ],
  "tests": [
    "php tests/ratelimiter-test.php",
    "php tests/request-reset-static-test.php"
  ],
  "risks": [
    "NOTHING here has been run, tested, linted or zip-checked. This is a draft for the lane session to verify.",
    "If the site is behind a proxy, load balancer or CDN, REMOTE_ADDR is the proxy's IP. All visitors would then share one per-IP bucket, and legitimate users could get 429 errors.",
    "If the sys temp dir is per-process (systemd PrivateTmp) or wiped, limits reset or are not shared across PHP workers. Set RATE_LIMIT_DIR to a persistent shared, writable, non-web-served dir.",
    "Across multiple web servers, file-backed limits are per-server, so the effective limit is multiplied by the server count.",
    "Fail-open means a broken storage dir silently disables throttling (logged only).",
    "A legitimate user who requests more than 3 resets in an hour gets no further emails, and only the FAQ explains this.",
    "Limiter files accumulate, one per IP/email. Opportunistic GC deletes files idle for more than 24h on about 1% of requests.",
    "The register endpoint remains unthrottled until a follow-up release, so the f14 finding is only partly closed."
  ],
  "verify": [
    "Confirm the live path. It was guessed as sync-storage/api/auth/password/request-reset.php from the attachment name and the ../../../../app requires. app/lib/RateLimiter.php is a NEW file next to the existing Mailer.php and Captcha.php.",
    "PERSON DECISION: confirm the limits (per IP 10/hour, per address 3/hour).",
    "PERSON DECISION: fail-open (default) or fail-closed when limiter storage is unusable.",
    "PERSON DECISION / INFRA: is there a reverse proxy? If yes, decide which trusted proxy header to use for the client IP.",
    "Set RATE_LIMIT_DIR in .env to a persistent, shared, writable directory outside the web root. Confirm the PHP user can write to it.",
    "register endpoint: file not shown. It was not changed. A follow-up must add per-IP and per-target RateLimiter::hit calls there.",
    "app/bootstrap.php, Response, Auth, Mailer, Captcha: file not shown. The patch assumes Response::error() ends the request (as the existing code already relies on), and that header() before it is safe.",
    "Manual check on staging: send 11 POSTs from one IP and confirm the 11th returns 429 with Retry-After. Send 4 POSTs for one existing address and confirm only 3 emails arrive and all 4 responses are identical.",
    "Run both test scripts and php -l on both changed/new PHP files."
  ],
  "regions": false
}
=== END FILE ===