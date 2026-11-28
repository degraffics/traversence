=== FILE: f15/files/app/lib/ResendLimiter.php ===
<?php
// Server-side limit for "send the confirmation link again" (finding f15). The session limit in
// resend-confirmation.php can be bypassed by dropping the cookie, so this limit is kept in the
// database instead. It is keyed per email address and per client IP.
//
// Every request is recorded first and then counted. Recording before counting means two
// concurrent requests cannot both slip under the limit. Rejected attempts are recorded too, so
// someone who keeps hammering stays blocked until the rolling window clears.
//
// Keys are stored only as sha256 hashes. Rows older than KEEP seconds are pruned now and then.
declare(strict_types=1);

final class ResendLimiter
{
    public const WINDOW    = 3600;  // rolling window, seconds
    public const PER_EMAIL = 3;     // sends allowed per address per window (matches the old per-session limit)
    public const PER_IP    = 10;    // sends allowed per client IP (IPv6: per /64) per window
    public const KEEP      = 86400; // rows older than this are pruned

    public function __construct(private PDO $db) {}

    /**
     * Records this attempt and checks it.
     * Returns null when the send may go ahead.
     * Returns 'email' or 'ip' when that limit is exceeded. 'email' wins when both are exceeded.
     */
    public function hit(string $email, string $ip, ?int $now = null): ?string
    {
        $now ??= time();
        $keys = ['email' => [self::PER_EMAIL, self::hashKey('email', strtolower(trim($email)))]];
        $ipKey = self::normalizeIp($ip);
        if ($ipKey !== '') $keys['ip'] = [self::PER_IP, self::hashKey('ip', $ipKey)];

        $ins = $this->db->prepare('INSERT INTO resend_confirmation_attempts (kind, key_hash, created_at) VALUES (?, ?, ?)');
        foreach ($keys as $kind => [, $hash]) $ins->execute([$kind, $hash, $now]);

        $cnt = $this->db->prepare('SELECT COUNT(*) FROM resend_confirmation_attempts WHERE kind = ? AND key_hash = ? AND created_at > ?');
        $over = null;
        foreach ($keys as $kind => [$limit, $hash]) {
            $cnt->execute([$kind, $hash, $now - self::WINDOW]);
            $n = (int) $cnt->fetchColumn();
            $cnt->closeCursor();
            if ($n > $limit && $over === null) $over = $kind;
        }

        if (random_int(1, 50) === 1) {
            try { $this->prune($now); } catch (Throwable $e) { error_log('resend limiter prune: ' . $e->getMessage()); }
        }
        return $over;
    }

    /**
     * Deletes attempt rows older than KEEP seconds and returns how many were removed.
     * data_reason: these rows exist only for rate limiting and are no use after the window.
     * Pruning keeps the table small and stops hashed emails/IPs from being kept longer than needed.
     */
    public function prune(?int $now = null): int
    {
        $now ??= time();
        $st = $this->db->prepare('DELETE FROM resend_confirmation_attempts WHERE created_at < ?');
        $st->execute([$now - self::KEEP]);
        return $st->rowCount();
    }

    public static function hashKey(string $kind, string $value): string
    {
        return hash('sha256', 'tv-resend|' . $kind . '|' . $value);
    }

    /** IPv4 is used as-is. IPv6 is cut to its /64 so one host can't rotate addresses. Invalid or empty input gives ''. */
    public static function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') return '';
        $bin = @inet_pton($ip);
        if ($bin === false) return '';
        if (strlen($bin) === 4) return inet_ntop($bin);
        return bin2hex(substr($bin, 0, 8)) . '::/64';
    }
}
=== END FILE ===
=== FILE: f15/files/database/migrations/20261106_resend_confirmation_attempts.sql ===
-- f15: server-side rate limit for resend-confirmation (per email + per IP).
-- Safe to run twice: CREATE TABLE IF NOT EXISTS only. No data is changed.
CREATE TABLE IF NOT EXISTS resend_confirmation_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kind       VARCHAR(8)      NOT NULL,
    key_hash   CHAR(64)        NOT NULL,
    created_at INT UNSIGNED    NOT NULL,
    KEY idx_rca_kind_key_time (kind, key_hash, created_at),
    KEY idx_rca_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
=== END FILE ===
=== FILE: f15/files/sync-storage/api/auth/resend-confirmation.php ===
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
=== END FILE ===
=== FILE: f15/tests/resend-limiter-tests.php ===
<?php
// tools/auth-tests/resend-limiter-tests.php — plain PHP, prints PASS/FAIL per check.
// Uses in-memory SQLite (needs pdo_sqlite). Sends no mail and moves no money.
// Run: php tools/auth-tests/resend-limiter-tests.php
declare(strict_types=1);

$root = null;
foreach ([__DIR__ . '/../..', __DIR__ . '/..', __DIR__ . '/../files', getcwd()] as $c) {
    if (is_file($c . '/app/lib/ResendLimiter.php')) { $root = $c; break; }
}
if ($root === null) { echo "FAIL: app/lib/ResendLimiter.php not found\n"; exit(1); }
require_once $root . '/app/lib/ResendLimiter.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { echo "SKIP: pdo_sqlite not available\n"; exit(0); }

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n"; if (!$ok) $fails++; }
function freshDb(): PDO {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE resend_confirmation_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, kind VARCHAR(8) NOT NULL, key_hash CHAR(64) NOT NULL, created_at INTEGER NOT NULL)');
    return $db;
}

$now = 1_800_000_000;

// 1. Per email: 3 allowed, 4th refused, even from different IPs (i.e. without the session cookie)
$l = new ResendLimiter(freshDb());
$r = [];
for ($i = 1; $i <= 4; $i++) $r[] = $l->hit('victim@example.com', "203.0.113.$i", $now + $i);
check('email: first 3 allowed', $r[0] === null && $r[1] === null && $r[2] === null);
check('email: 4th refused across IPs', $r[3] === 'email');

// 2. Email key is case/whitespace-insensitive
$l = new ResendLimiter(freshDb());
foreach (['A@Example.com', ' a@example.com', 'a@EXAMPLE.COM'] as $i => $e) $l->hit($e, "198.51.100.$i", $now);
check('email: case-insensitive key', $l->hit('a@example.com', '198.51.100.9', $now) === 'email');

// 3. Per IP: 10 different emails allowed, 11th refused
$l = new ResendLimiter(freshDb());
$ok = true;
for ($i = 1; $i <= 10; $i++) $ok = $ok && $l->hit("u{$i}@example.com", '192.0.2.7', $now) === null;
check('ip: first 10 allowed', $ok);
check('ip: 11th refused', $l->hit('u11@example.com', '192.0.2.7', $now) === 'ip');
check('ip: other IP unaffected', $l->hit('u12@example.com', '192.0.2.8', $now) === null);

// 4. Window rolls off after an hour
$l = new ResendLimiter(freshDb());
for ($i = 0; $i < 3; $i++) $l->hit('w@example.com', '192.0.2.1', $now);
check('window: refused inside the hour', $l->hit('w@example.com', '192.0.2.2', $now + 10) === 'email');
check('window: allowed after the hour', $l->hit('w@example.com', '192.0.2.3', $now + ResendLimiter::WINDOW + 11) === null);

// 5. IPv6 addresses in the same /64 share one bucket
check('ipv6: same /64 normalises equal', ResendLimiter::normalizeIp('2001:db8:1:2::1') === ResendLimiter::normalizeIp('2001:db8:1:2:ffff::9'));
check('ipv6: different /64 differs', ResendLimiter::normalizeIp('2001:db8:1:2::1') !== ResendLimiter::normalizeIp('2001:db8:1:3::1'));
check('ip: garbage normalises to empty', ResendLimiter::normalizeIp('not-an-ip') === '' && ResendLimiter::normalizeIp('') === '');

// 6. Empty IP: only the email limit applies, and no ip rows are written
$db = freshDb(); $l = new ResendLimiter($db);
$l->hit('n@example.com', '', $now);
check('ip: empty IP writes no ip row', (int) $db->query("SELECT COUNT(*) FROM resend_confirmation_attempts WHERE kind = 'ip'")->fetchColumn() === 0);

// 7. Stored keys are hashed (no plain email or IP in the table)
$db = freshDb(); $l = new ResendLimiter($db);
$l->hit('plain@example.com', '192.0.2.50', $now);
$dump = json_encode($db->query('SELECT * FROM resend_confirmation_attempts')->fetchAll(PDO::FETCH_ASSOC));
check('privacy: no plain email/IP stored', !str_contains($dump, 'plain@example.com') && !str_contains($dump, '192.0.2.50'));

// 8. Prune removes only rows older than KEEP
$db = freshDb(); $l = new ResendLimiter($db);
$l->hit('old@example.com', '192.0.2.60', $now - ResendLimiter::KEEP - 5);
$l->hit('new@example.com', '192.0.2.61', $now);
$gone = $l->prune($now);
$left = (int) $db->query('SELECT COUNT(*) FROM resend_confirmation_attempts')->fetchColumn();
check('prune: removes old rows only', $gone === 2 && $left === 2);

// 9. The endpoint actually uses the limiter, after the session check and before the user lookup
$ep = $root . '/sync-storage/api/auth/resend-confirmation.php';
if (is_file($ep)) {
    $src = (string) file_get_contents($ep);
    $pSess = strpos($src, "\$_SESSION['tv_resend'] = \$t;");
    $pLim  = strpos($src, '->hit($email');
    $pSel  = strpos($src, 'SELECT id, email_verified_at FROM users');
    check('endpoint: requires ResendLimiter.php', str_contains($src, "app/lib/ResendLimiter.php"));
    check('endpoint: limiter after session check, before lookup', $pSess !== false && $pLim !== false && $pSel !== false && $pSess < $pLim && $pLim < $pSel);
    check('endpoint: 429 too_many on limit', str_contains($src, "Response::error(\$tooMany, 429, 'too_many');"));
} else {
    echo "SKIP: endpoint file not found at {$ep}\n";
}

echo $fails === 0 ? "ALL PASS\n" : "{$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f15/faq/faq-entries.md ===
no faq change

The visitor-facing message has not changed ("We've sent it a few times already. Check your spam folder, or try again in an hour."). The limit is now also enforced per address and per connection rather than only per browser session. That does not change what a visitor is told.
=== END FILE ===
=== FILE: f15/release.json ===
{
  "name": "f15-resend-confirmation-server-side-limit",
  "gate": "med: apply the migration before deploying the code (the code fails open without the table, so the order is not fatal)",
  "what": "resend-confirmation.php was limited per session only, so clearing the cookie gave unlimited sends and allowed mail-bombing any address. This adds a database-backed rolling-hour limit: 3 sends per email address (counted whether or not the address has an account, so the 429 does not reveal registrations) and 10 per client IP (IPv6 grouped per /64). The existing per-session limit and all messages are unchanged.",
  "files": [
    "files/app/lib/ResendLimiter.php",
    "files/database/migrations/20261106_resend_confirmation_attempts.sql",
    "files/sync-storage/api/auth/resend-confirmation.php",
    "tests/resend-limiter-tests.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "NEEDS A PERSON: should the limiter fail OPEN or CLOSED if its table or the DB is unavailable? Implemented default: fail open with error_log, keeping the per-session limit, so legitimate users can still confirm. Failing closed is stronger against mail-bombing but blocks every resend whenever the limiter errors.",
    "NEEDS A PERSON: limit values. Defaults are PER_EMAIL=3/hour (same as the old session limit) and PER_IP=10/hour. Shared IPs (offices, campuses, carrier NAT) could hit the IP limit; raise PER_IP if support sees this.",
    "NEEDS A PERSON: captcha. The finding also mentions that there is no captcha. No captcha was added because no captcha provider or library is shown. The server-side limits are the safe default; adding a captcha is a separate product decision.",
    "Rejected attempts are recorded too (insert-then-count, which is race-safe), so continued hammering keeps a key blocked until the rolling hour clears.",
    "The email limit counts every requested address, including ones with no account, so the 429 response does not enable account enumeration.",
    "Emails and IPs are stored only as sha256 hashes and pruned after 24h (probabilistically, 1 in 50 requests)."
  ],
  "tests": [
    "tests/resend-limiter-tests.php (install as tools/auth-tests/resend-limiter-tests.php; needs pdo_sqlite; prints PASS/FAIL per check and exits non-zero on any FAIL)"
  ],
  "risks": [
    "NOTHING HERE HAS BEEN RUN OR VERIFIED: the migration, code and tests are drafts only.",
    "REMOTE_ADDR is used as the client IP. Behind a reverse proxy or CDN, every visitor may share one IP and the IP limit would block everyone after 10 sends an hour. Confirm how the app gets the real client IP. Do not trust X-Forwarded-For unless it is set by a known proxy.",
    "The email hashes are unsalted sha256, which can be reversed by guessing likely addresses. This is acceptable because rows live 24h at most, but note it for privacy review.",
    "Pruning runs a DELETE on about 2% of requests. With the idx_rca_time index this should be cheap; check it on production volume.",
    "Fail-open default: if the migration is not applied, only the old per-session limit protects this endpoint (the errors are logged)."
  ],
  "verify": [
    "Live path assumed to be sync-storage/api/auth/resend-confirmation.php, inferred from the attachment name and its require_once __DIR__ . '/../../../app/bootstrap.php'. Confirm before copying.",
    "app/bootstrap.php, Response, Database, Auth and app/lib/Mailer.php: file not shown. Assumed (as the existing code does) that Response::error() ends the request and that Database::connection() returns a PDO with ERRMODE_EXCEPTION.",
    "Migrations folder and naming convention: file not shown. Placed at database/migrations/20261106_resend_confirmation_attempts.sql; move or rename it to match the project's runner. The migration is CREATE TABLE IF NOT EXISTS only, so it is safe to run twice.",
    "Check whether an existing rate-limit helper or table exists in app/lib (file not shown). If one does, consider reusing it instead of ResendLimiter.",
    "Run the migration twice on staging and confirm no error and the table exists.",
    "Run php tools/auth-tests/resend-limiter-tests.php and expect ALL PASS.",
    "Manual test on staging: request a resend 4 times for one address while clearing cookies between requests; expect the 4th to return 429 too_many. Also send 11 requests for different addresses from one IP; expect the 11th to return 429.",
    "Confirm the client-IP source behind the production proxy (see risks).",
    "Decide fail-open vs fail-closed, the limit values, and the captcha question (see decisions).",
    "Zip-check that the release contains only the files listed in 'files'."
  ],
  "regions": false
}
=== END FILE ===