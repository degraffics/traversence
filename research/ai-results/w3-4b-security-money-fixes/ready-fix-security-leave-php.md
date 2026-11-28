=== FILE: f20/files/listing/api/leave.php ===
<?php
// /listing/api/leave.php — Leave a message on an unclaimed listing (decisions/0172 §3.3, ListingMessages). Works signed
// out; POSTs need X-CSRF-Token. A message is accepted only with a way to answer its sender: a member account, a
// confirmed email (a link first; nothing goes until it's pressed), or a phone with the guest's say-so.
//   GET  ?challenge=ENTITY_ID       -> {route, before, button, nonce, question, signed_in, csrf, mine: [the member's own here]}
//   POST {do:'leave', entity_id, body, [guest: nonce, answer, website, name, answer_by: email|phone, email, phone, phone_ok]}
//                                   -> {state, said, code (a guest's, shown once)}
//   POST {do:'check', code}         -> {thread}              where it stands, its words (the code travels in the body)
//   POST {do:'reply', code, body} / {do:'reply', id, body} -> {thread} / {mine}   writing again, once it's been answered
//   POST {do:'withdraw', code} / {do:'withdraw', id}       -> {}                   any time before it's delivered
//   POST {do:'resend', code}        -> {}                    a new confirm link to the guest's email
//   GET  ?mine=1                    -> {items}               a member's left messages (their Pulse lists the same)
// Bearer-code actions (check / reply / withdraw / resend with a code) are throttled by LeaveThrottle (finding f20):
// failed codes per IP, 'check' reads per IP, and confirm-link resends per code and per IP. Over a limit -> 429 rate_limited.
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/ListingMessages.php';
require_once __DIR__ . '/../../../app/lib/LeaveThrottle.php';

header('Cache-Control: no-store');
$pdo = Database::connection();
if (!ListingMessages::ready($pdo)) Response::error('Leaving a message isn\'t switched on yet. Call them instead.', 503, 'not_configured');
$uid = Auth::currentUserId();

$code = '';
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$throttle = null;
$codeAction = false;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!empty($_GET['mine'])) {
            if ($uid === null) Response::error('Sign in to see your messages.', 401, 'unauthenticated');
            Response::json(['ok' => true, 'items' => ListingMessages::forMember($pdo, $uid)]);
        }
        $e = (int) ($_GET['challenge'] ?? 0);
        $route = ListingMessages::routeFor($pdo, $e);
        [$before, $button] = ListingMessages::words($route);
        Response::json(['ok' => true, 'route' => $route, 'before' => $before, 'button' => $button, 'signed_in' => $uid !== null,
                        'csrf' => $_SESSION['csrf_token'] ?? null, 'mine' => $uid !== null ? ListingMessages::forMember($pdo, $uid, $e) : []]
                       + ($uid === null ? GuestInquiries::challenge() : []));
    }
    Response::requireMethod('POST');
    Response::requireCsrf();
    $b = Response::jsonBody();
    $do = (string) ($b['do'] ?? 'leave');
    $code = trim((string) ($b['code'] ?? ''));
    $id = (int) ($b['id'] ?? 0);

    $codeAction = $code !== '' && in_array($do, ['check', 'reply', 'withdraw', 'resend'], true);
    if ($codeAction) {
        $throttle = new LeaveThrottle($pdo);
        $why = $throttle->admit($do, $code, $ip);
        if ($why !== null) {
            header('Retry-After: ' . LeaveThrottle::retryAfter($why));
            if ($why === 'resend') {
                Response::error('A confirm link was sent recently. Check your inbox (and spam folder), then wait a few minutes before asking again.', 429, 'rate_limited');
            }
            Response::error('Too many tries with a reply code. Please wait a few minutes and try again.', 429, 'rate_limited');
        }
    }

    switch ($do) {
        case 'leave':
            Response::json(['ok' => true] + ListingMessages::leave($pdo, (int) ($b['entity_id'] ?? 0), $uid, $b));
        case 'check':
            $thread = GuestInquiries::guestView($pdo, $code);
            if (empty($thread) && $throttle !== null) $throttle->failed($code, $ip);
            Response::json(['ok' => true, 'thread' => $thread]);
        case 'reply':
            if ($code !== '') { GuestInquiries::guestReply($pdo, $code, (string) ($b['body'] ?? '')); Response::json(['ok' => true, 'thread' => GuestInquiries::guestView($pdo, $code)]); }
            if ($uid === null) Response::error('Sign in, or use your reply code.', 401, 'unauthenticated');
            ListingMessages::memberFollowUp($pdo, $uid, $id, (string) ($b['body'] ?? ''));
            Response::json(['ok' => true, 'mine' => ListingMessages::forMember($pdo, $uid, (int) ($b['entity_id'] ?? 0))]);
        case 'withdraw':
            if ($code === '' && $uid === null) Response::error('Sign in, or use your reply code.', 401, 'unauthenticated');
            ListingMessages::withdraw($pdo, $uid, $id, $code);
            Response::json(['ok' => true]);
        case 'resend':
            ListingMessages::resendConfirm($pdo, $code);
            Response::json(['ok' => true]);
    }
    Response::error('Unknown request.', 400, 'bad_request');
} catch (InvalidArgumentException $x) {
    if ($codeAction && $throttle !== null) $throttle->failed($code, $ip);
    Response::error($x->getMessage(), 422, 'not_allowed');
}
=== END FILE ===
=== FILE: f20/files/app/lib/LeaveThrottle.php ===
<?php
// LeaveThrottle — rate limits for bearer-code actions on /listing/api/leave.php (finding f20).
// Stores only sha256 hashes of IPs and codes, never the raw values. Table: listing_leave_throttle (migration f20).
// If the table is missing or the DB errors: 'resend' is refused (fail closed; it sends mail), every other action
// is allowed (fail open) and a line is written to the error log once per request.
declare(strict_types=1);

final class LeaveThrottle
{
    public const TABLE = 'listing_leave_throttle';

    // Failed codes (unknown / rejected) per IP.
    public const FAIL_MAX = 10;
    public const FAIL_WINDOW = 900;          // 15 minutes
    // 'check' reads per IP (a check returns contact details, even for a valid code).
    public const CHECK_MAX = 30;
    public const CHECK_WINDOW = 3600;        // 1 hour
    // Confirm-link resends.
    public const RESEND_GAP = 600;           // at most 1 per code per 10 minutes
    public const RESEND_DAY_MAX = 3;         // at most 3 per code per 24 hours
    public const RESEND_IP_MAX = 10;         // at most 10 per IP per hour
    public const RESEND_IP_WINDOW = 3600;
    public const KEEP_SECONDS = 172800;      // rows older than 2 days are pruned

    private PDO $pdo;
    private Closure $now;
    private ?bool $ready = null;
    private bool $logged = false;
    private int $pruneOdds;

    public function __construct(PDO $pdo, ?Closure $now = null, int $pruneOdds = 50)
    {
        $this->pdo = $pdo;
        $this->now = $now ?? static function (): int { return time(); };
        $this->pruneOdds = max(0, $pruneOdds);
    }

    public static function key(string $kind, string $value): string
    {
        return hash('sha256', $kind . '|' . $value);
    }

    public static function retryAfter(string $why): int
    {
        return $why === 'resend' ? self::RESEND_GAP : ($why === 'checks' ? self::CHECK_WINDOW : self::FAIL_WINDOW);
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
                $this->ready = true;
            } catch (Throwable) {
                $this->ready = false;
            }
        }
        return $this->ready;
    }

    /**
     * Decide whether a bearer-code action may go ahead, and record it if so.
     * Returns null when allowed, else the reason: 'tries' | 'checks' | 'resend'.
     */
    public function admit(string $do, string $code, string $ip): ?string
    {
        if ($code === '') return null;
        if (!$this->ready()) {
            $this->logOnce('LeaveThrottle: table ' . self::TABLE . ' missing; resend refused, other code actions not throttled');
            return $do === 'resend' ? 'resend' : null;
        }
        try {
            $ipKey = self::key('ip', $ip);
            if ($this->over('fail_ip', $ipKey, self::FAIL_MAX, self::FAIL_WINDOW)) return 'tries';
            if ($do === 'check') {
                if ($this->over('check_ip', $ipKey, self::CHECK_MAX, self::CHECK_WINDOW)) return 'checks';
                $this->hit('check_ip', $ipKey);
            }
            if ($do === 'resend') {
                $codeKey = self::key('code', $code);
                if ($this->over('resend_code', $codeKey, 1, self::RESEND_GAP)
                    || $this->over('resend_code', $codeKey, self::RESEND_DAY_MAX, 86400)
                    || $this->over('resend_ip', $ipKey, self::RESEND_IP_MAX, self::RESEND_IP_WINDOW)) {
                    return 'resend';
                }
                $this->hit('resend_code', $codeKey);
                $this->hit('resend_ip', $ipKey);
            }
            return null;
        } catch (Throwable $x) {
            $this->logOnce('LeaveThrottle: ' . $x->getMessage());
            return $do === 'resend' ? 'resend' : null;
        }
    }

    /** Record a failed (unknown or rejected) code from this IP. */
    public function failed(string $code, string $ip): void
    {
        if ($code === '' || !$this->ready()) return;
        try {
            $this->hit('fail_ip', self::key('ip', $ip));
        } catch (Throwable $x) {
            $this->logOnce('LeaveThrottle: ' . $x->getMessage());
        }
    }

    public function count(string $bucket, string $subject, int $window): int
    {
        $since = gmdate('Y-m-d H:i:s', ($this->now)() - $window);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE bucket = ? AND subject = ? AND created_at > ?');
        $st->execute([$bucket, $subject, $since]);
        return (int) $st->fetchColumn();
    }

    public function over(string $bucket, string $subject, int $max, int $window): bool
    {
        return $this->count($bucket, $subject, $window) >= $max;
    }

    public function hit(string $bucket, string $subject): void
    {
        $st = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (bucket, subject, created_at) VALUES (?, ?, ?)');
        $st->execute([$bucket, $subject, gmdate('Y-m-d H:i:s', ($this->now)())]);
        if ($this->pruneOdds > 0 && random_int(1, $this->pruneOdds) === 1) $this->prune();
    }

    /** data_reason: throttle rows are only counters (hashed IP/code); rows older than KEEP_SECONDS can no longer affect any limit. */
    public function prune(): int
    {
        $before = gmdate('Y-m-d H:i:s', ($this->now)() - self::KEEP_SECONDS);
        $st = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE created_at < ?');
        $st->execute([$before]);
        return $st->rowCount();
    }

    private function logOnce(string $msg): void
    {
        if ($this->logged) return;
        $this->logged = true;
        error_log($msg);
    }
}
=== END FILE ===
=== FILE: f20/files/migrations/f20_listing_leave_throttle.sql ===
-- f20: counters for bearer-code rate limits on /listing/api/leave.php. Safe to run twice. No data changes.
CREATE TABLE IF NOT EXISTS listing_leave_throttle (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket VARCHAR(16) NOT NULL,
  subject CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_bucket_subject_time (bucket, subject, created_at),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
=== END FILE ===
=== FILE: f20/tests/leave-throttle-tests/leave_throttle_test.php ===
<?php
// Plain PHP test for LeaveThrottle (finding f20). Run: php tests/leave-throttle-tests/leave_throttle_test.php
// Uses in-memory SQLite; no mail, no money, no live DB.
declare(strict_types=1);

$lib = __DIR__ . '/../../files/app/lib/LeaveThrottle.php';
if (!is_file($lib)) $lib = __DIR__ . '/../../../../app/lib/LeaveThrottle.php';
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$ok) $fails++;
}

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL - pdo_sqlite not available; cannot run LeaveThrottle tests\n";
    exit(1);
}

function freshPdo(bool $withTable): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($withTable) {
        $pdo->exec('CREATE TABLE listing_leave_throttle (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket VARCHAR(16) NOT NULL, subject CHAR(64) NOT NULL, created_at DATETIME NOT NULL)');
    }
    return $pdo;
}

$t = 1700000000;
$now = function () use (&$t): int { return $t; };

// 1. Table missing: check allowed (fail open), resend refused (fail closed).
$prev = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
$th = new LeaveThrottle(freshPdo(false), $now, 0);
check('missing table: check allowed', $th->admit('check', 'ABC', '1.2.3.4') === null);
check('missing table: resend refused', $th->admit('resend', 'ABC', '1.2.3.4') === 'resend');
if ($prev !== false) ini_set('error_log', $prev);

// 2. Empty code never throttled.
$th = new LeaveThrottle(freshPdo(true), $now, 0);
check('empty code allowed', $th->admit('resend', '', '1.2.3.4') === null);

// 3. Failed codes per IP.
$pdo = freshPdo(true);
$th = new LeaveThrottle($pdo, $now, 0);
for ($i = 0; $i < LeaveThrottle::FAIL_MAX; $i++) $th->failed('BAD' . $i, '9.9.9.9');
check('after FAIL_MAX failures, code action blocked', $th->admit('withdraw', 'X', '9.9.9.9') === 'tries');
check('other IP unaffected', $th->admit('withdraw', 'X', '8.8.8.8') === null);
$t += LeaveThrottle::FAIL_WINDOW + 1;
check('after FAIL_WINDOW, allowed again', $th->admit('withdraw', 'X', '9.9.9.9') === null);

// 4. Check reads per IP.
$pdo = freshPdo(true);
$th = new LeaveThrottle($pdo, $now, 0);
$okAll = true;
for ($i = 0; $i < LeaveThrottle::CHECK_MAX; $i++) $okAll = $okAll && $th->admit('check', 'GOOD', '5.5.5.5') === null;
check('CHECK_MAX checks allowed', $okAll);
check('next check blocked', $th->admit('check', 'GOOD', '5.5.5.5') === 'checks');

// 5. Resend per code.
$pdo = freshPdo(true);
$th = new LeaveThrottle($pdo, $now, 0);
check('first resend allowed', $th->admit('resend', 'R1', '7.7.7.7') === null);
check('second resend inside gap blocked', $th->admit('resend', 'R1', '7.7.7.7') === 'resend');
check('same code from other IP also blocked', $th->admit('resend', 'R1', '6.6.6.6') === 'resend');
$t += LeaveThrottle::RESEND_GAP + 1;
check('resend after gap allowed', $th->admit('resend', 'R1', '7.7.7.7') === null);
$t += LeaveThrottle::RESEND_GAP + 1;
check('third resend allowed', $th->admit('resend', 'R1', '7.7.7.7') === null);
$t += LeaveThrottle::RESEND_GAP + 1;
check('fourth resend in a day blocked', $th->admit('resend', 'R1', '7.7.7.7') === 'resend');

// 6. Resend per IP across many codes.
$pdo = freshPdo(true);
$th = new LeaveThrottle($pdo, $now, 0);
for ($i = 0; $i < LeaveThrottle::RESEND_IP_MAX; $i++) $th->admit('resend', 'C' . $i, '4.4.4.4');
check('resend per-IP cap', $th->admit('resend', 'Cnew', '4.4.4.4') === 'resend');

// 7. No raw code or IP stored.
$raw = $pdo->query('SELECT subject FROM listing_leave_throttle')->fetchAll(PDO::FETCH_COLUMN);
$leak = false;
foreach ($raw as $s) if (str_contains((string) $s, 'C1') || str_contains((string) $s, '4.4.4.4')) $leak = true;
check('only hashes stored', !$leak && count($raw) > 0);

// 8. Prune removes old rows only.
$before = (int) $pdo->query('SELECT COUNT(*) FROM listing_leave_throttle')->fetchColumn();
$t += LeaveThrottle::KEEP_SECONDS + 10;
$th->hit('check_ip', LeaveThrottle::key('ip', 'new'));
$removed = $th->prune();
$after = (int) $pdo->query('SELECT COUNT(*) FROM listing_leave_throttle')->fetchColumn();
check('prune removed old rows', $removed === $before && $after === 1);

// 9. leave.php wires the throttle in.
$leave = __DIR__ . '/../../files/listing/api/leave.php';
$src = is_file($leave) ? (string) file_get_contents($leave) : '';
check('leave.php requires LeaveThrottle', str_contains($src, "require_once __DIR__ . '/../../../app/lib/LeaveThrottle.php';"));
check('leave.php calls admit and answers 429', str_contains($src, '->admit($do, $code, $ip)') && str_contains($src, "429, 'rate_limited'"));
check('leave.php records failures', substr_count($src, '->failed($code, $ip)') >= 2);

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===
=== FILE: f20/faq/faq-entries.md ===
### Why does it say "Too many tries" when I use my reply code?

To protect the people who leave messages, we limit how often a reply code can be tried from one connection. After several codes that don't match, or a lot of checks in a short time, you'll be asked to wait a few minutes. Copy your code exactly as it was shown (or from your email) and try again after the wait.

### I asked for a new confirm link but it says to wait. Why?

We send at most one new confirm link for a message every 10 minutes, and no more than 3 in a day. Look in your inbox and spam folder for the last link we sent. If it still hasn't arrived after the wait, you can ask again.
=== END FILE ===
=== FILE: f20/release.json ===
{
  "name": "f20-leave-code-rate-limits",
  "gate": "med",
  "what": "Rate-limits bearer-code actions on /listing/api/leave.php. Each connection (IP) may have at most 10 failed codes per 15 minutes and 30 'check' reads per hour. Confirm-link resends are limited to 1 per code per 10 minutes, 3 per code per day, and 10 per IP per hour. Requests over a limit get 429 rate_limited with Retry-After. Counters are stored only as sha256 hashes in a new table, listing_leave_throttle.",
  "files": [
    "files/listing/api/leave.php",
    "files/app/lib/LeaveThrottle.php",
    "files/migrations/f20_listing_leave_throttle.sql",
    "tests/leave-throttle-tests/leave_throttle_test.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "Limits are the safest default and need a person's sign-off: FAIL 10/15min per IP, CHECK 30/h per IP, RESEND 1 per 10min and 3 per day per code, 10 per hour per IP. They are constants in LeaveThrottle.",
    "If the table is missing or the DB errors, the throttle fails closed for 'resend' (it sends mail) and fails open for check/reply/withdraw, so the feature keeps working. Each case writes an error_log line.",
    "A failure is recorded when a code action throws InvalidArgumentException, or when 'check' returns an empty thread.",
    "Raw codes and IPs are never stored; only sha256('ip|..') and sha256('code|..') are kept.",
    "data_reason: LeaveThrottle::prune() deletes throttle rows older than 2 days. They are counters only, and rows that old can no longer affect any limit. It runs on about 1 in 50 inserts.",
    "The member-only actions (reply or withdraw by id with no code) and 'leave' are not changed by this release."
  ],
  "tests": [
    "php tests/leave-throttle-tests/leave_throttle_test.php (needs pdo_sqlite)"
  ],
  "risks": [
    "DRAFT: nothing here has been run, linted, tested or zip-checked. The lane session must do all of that.",
    "Users behind a shared NAT or proxy share one IP bucket, so a busy office could hit the check or failure caps.",
    "Checking and then inserting is not atomic. Concurrent requests can go slightly over a limit (for example, two resends at once).",
    "If REMOTE_ADDR is a load balancer or proxy address, every visitor shares one bucket. That would block legitimate guests site-wide.",
    "Deploying leave.php before the migration runs means resend is refused for everyone until the table exists."
  ],
  "verify": [
    "Run the migration before deploying the PHP. The migrations folder and naming convention are file not shown; move f20_listing_leave_throttle.sql to match.",
    "app/lib/ListingMessages.php is file not shown. Check whether resendConfirm() already throttles mail, and whether it throws InvalidArgumentException for an unknown code.",
    "GuestInquiries (guestView/guestReply) is file not shown. Confirm that an unknown code either throws InvalidArgumentException or makes guestView return empty, so failures get counted.",
    "Response (error/json/requireCsrf) is file not shown. Confirm Response::error() and Response::json() end the request (the existing switch already relies on this), and that a 429 status is accepted.",
    "app/bootstrap.php is file not shown. Confirm the real client IP is in REMOTE_ADDR, or whether a trusted-proxy header must be used.",
    "Person's decision: approve or adjust the limit values, and the choice to fail closed on resend when the table is missing.",
    "Check the front-end handles 429 rate_limited and shows the message (front-end file not shown)."
  ],
  "regions": false
}
=== END FILE ===