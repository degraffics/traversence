The fix records a marker in the browser session when the code is verified, and the wire card is shown only when that marker is present. The step that verifies the emailed code is in a file that wasn't attached. That file has to call `WireCardGate::mark()`. Until it does, the new check makes the card 404 for everyone. That failure is safe, but it is a visible outage, so it is the ship gate below.

=== FILE: f10/files/app/lib/WireCardGate.php ===
<?php
// app/lib/WireCardGate.php — ties the wire-card picture to the browser that entered the emailed code (finding f10).
// The code-entry step calls WireCardGate::mark($token) right after the code checks out. website/api/wire-card.php
// calls WireCardGate::allowed($token). Only a hash of the order token is kept, and only in the server-side session.
// Each marker lasts ClientOrders::WIRE_MINUTES (15 if that constant is missing), and ClientOrders::wireLeft()
// still applies on top.
declare(strict_types=1);

final class WireCardGate
{
    public const KEY = 'wire_card_ok';
    public const MAX = 20;          // markers kept per session; the oldest are dropped first
    public const FALLBACK_MINUTES = 15;

    /** Call at code verification, before any output. Returns false if the session could not be used. */
    public static function mark(string $token): bool
    {
        if ($token === '' || !self::open(true)) {
            return false;
        }
        // A new session id at the moment access is granted blocks session fixation. The data is kept.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        self::markIn($_SESSION, $token, time(), self::minutes());
        return true;
    }

    /** For the wire-card endpoint. Never creates a session, and closes it (releasing the lock) once read. */
    public static function allowed(string $token): bool
    {
        if ($token === '' || !self::open(false)) {
            return false;
        }
        $ok = self::allowedIn($_SESSION, $token, time(), self::minutes());
        session_write_close();
        return $ok;
    }

    public static function minutes(): int
    {
        return defined('ClientOrders::WIRE_MINUTES')
            ? max(1, (int) constant('ClientOrders::WIRE_MINUTES'))
            : self::FALLBACK_MINUTES;
    }

    /** Pure: records the marker inside a session-like array. */
    public static function markIn(array &$s, string $token, int $now, int $minutes): void
    {
        $map = is_array($s[self::KEY] ?? null) ? $s[self::KEY] : [];
        $map = self::prune($map, $now, $minutes);
        unset($map[self::key($token)]);
        $map[self::key($token)] = $now;
        while (count($map) > self::MAX) {
            array_shift($map);
        }
        $s[self::KEY] = $map;
    }

    /** Pure: true only if this token was marked in this session within the window. */
    public static function allowedIn(array $s, string $token, int $now, int $minutes): bool
    {
        if ($token === '') {
            return false;
        }
        $map = is_array($s[self::KEY] ?? null) ? $s[self::KEY] : [];
        $at = $map[self::key($token)] ?? null;
        return is_int($at) && $at <= $now && $now < $at + $minutes * 60;
    }

    private static function prune(array $map, int $now, int $minutes): array
    {
        return array_filter($map, static fn($at) => is_int($at) && $at <= $now && $now < $at + $minutes * 60);
    }

    private static function key(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function open(bool $create): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (session_status() === PHP_SESSION_DISABLED || headers_sent()) {
            return false;
        }
        // Visitors without a session cookie (link-only, probers) never get a session created here.
        if (!$create && (string) ($_COOKIE[session_name()] ?? '') === '') {
            return false;
        }
        ini_set('session.use_strict_mode', '1');
        session_cache_limiter('');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return session_start();
    }
}
=== END FILE ===

=== FILE: f10/files/website/api/wire-card.php ===
<?php
// website/api/wire-card.php — our wire details as a picture, for the client's order page only (decisions/0101,
// addendum 2026-10-28): for the client with the order's link, after they've entered the code we emailed them, for
// ClientOrders::WIRE_MINUTES. Never cached, never a download; anything else is a 404.
// f10: "after they've entered the code" is now tied to this browser through a session marker (WireCardGate).
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/ClientOrders.php';
require_once __DIR__ . '/../../app/lib/WireCardGate.php';

$tok = (string) ($_GET['t'] ?? '');
$pdo = Database::connection();
$o = Products::ready($pdo) ? ClientOrders::byToken($pdo, $tok) : null;
$png = $o && $o['status'] === 'pending' && ClientOrders::wireLeft($o) > 0 && ($o['pay_method'] ?? '') === 'wire' && WireCardGate::allowed($tok) ? ClientOrders::wireCard($pdo, $o) : null;
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if ($png === null) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Not here.'; exit; }
header('Content-Type: image/png');
header('Content-Disposition: inline; filename="order"');
header('Content-Length: ' . strlen($png));
echo $png;
=== END FILE ===

=== FILE: f10/tests/wire-card-gate-test.php ===
<?php
// Place at tools/wire-card-tests/wire-card-gate-test.php. Run: php tools/wire-card-tests/wire-card-gate-test.php
// Override the repo root with WIRE_TEST_ROOT=/path/to/repo. Touches no DB and moves no money.
declare(strict_types=1);

$root = rtrim((string) (getenv('WIRE_TEST_ROOT') ?: __DIR__ . '/../..'), '/');
require_once $root . '/app/lib/WireCardGate.php';

$fail = 0;
function t(string $name, bool $ok): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$ok) { $fail++; }
}

$now = 1_800_000_000;
$min = 15;

$s = [];
t('unmarked session is refused', !WireCardGate::allowedIn($s, 'tokA', $now, $min));

WireCardGate::markIn($s, 'tokA', $now, $min);
t('marked token allowed in same session', WireCardGate::allowedIn($s, 'tokA', $now + 60, $min));
t('other token not allowed', !WireCardGate::allowedIn($s, 'tokB', $now + 60, $min));
t('empty token never allowed', !WireCardGate::allowedIn($s, '', $now, $min));
t('different (empty) session refused', !WireCardGate::allowedIn([], 'tokA', $now + 60, $min));
t('expires at window end', !WireCardGate::allowedIn($s, 'tokA', $now + $min * 60, $min));
t('still valid just before end', WireCardGate::allowedIn($s, 'tokA', $now + $min * 60 - 1, $min));
t('future-dated marker refused', !WireCardGate::allowedIn($s, 'tokA', $now - 1, $min));
t('raw token not stored', strpos(serialize($s), 'tokA') === false);

$s2 = [WireCardGate::KEY => 'garbage'];
t('garbage session value refused', !WireCardGate::allowedIn($s2, 'tokA', $now, $min));
WireCardGate::markIn($s2, 'tokA', $now, $min);
t('garbage replaced on mark', WireCardGate::allowedIn($s2, 'tokA', $now, $min));

$s3 = [];
for ($i = 0; $i < WireCardGate::MAX + 5; $i++) { WireCardGate::markIn($s3, 'tok' . $i, $now, $min); }
t('marker map capped', count($s3[WireCardGate::KEY]) === WireCardGate::MAX);
t('newest kept after cap', WireCardGate::allowedIn($s3, 'tok' . (WireCardGate::MAX + 4), $now, $min));
t('oldest dropped after cap', !WireCardGate::allowedIn($s3, 'tok0', $now, $min));

$s4 = [];
WireCardGate::markIn($s4, 'old', $now, $min);
WireCardGate::markIn($s4, 'new', $now + $min * 60 + 5, $min);
t('expired markers pruned on mark', count($s4[WireCardGate::KEY]) === 1);

// Static check that the endpoint consults the gate before building the picture.
$src = (string) @file_get_contents($root . '/website/api/wire-card.php');
t('endpoint requires WireCardGate', strpos($src, "require_once __DIR__ . '/../../app/lib/WireCardGate.php';") !== false);
$g = strpos($src, 'WireCardGate::allowed($tok)');
$w = strpos($src, 'ClientOrders::wireCard($pdo, $o)');
t('gate checked before wireCard()', $g !== false && $w !== false && $g < $w);

// Static check that the code-entry step sets the marker (file not shown; the lane sets WIRE_CODE_FILE).
$codeFile = (string) (getenv('WIRE_CODE_FILE') ?: '');
if ($codeFile === '') {
    echo 'FAIL code-entry file not given (set WIRE_CODE_FILE); WireCardGate::mark() wiring unchecked' . PHP_EOL;
    $fail++;
} else {
    t('code-entry step calls WireCardGate::mark', strpos((string) @file_get_contents($codeFile), 'WireCardGate::mark(') !== false);
}

echo ($fail === 0 ? 'ALL PASS' : "$fail FAILED") . PHP_EOL;
exit($fail === 0 ? 0 : 1);
=== END FILE ===

=== FILE: f10/faq/faq-entries.md ===
### Why can't I see your bank details for my wire payment?

For your safety, our wire details only show in the same browser where you entered the code we emailed you. They also only show for a limited time after you enter it. If you open your order link on another phone or computer, or the time has passed, enter the code again on that device. If you still can't see them, contact us. We will never send our bank details any other way without you asking.
=== END FILE ===

=== FILE: f10/release.json ===
{
  "name": "f10-wire-card-session-bound",
  "gate": "DO NOT SHIP files/website/api/wire-card.php until the code-entry step (file not shown) calls WireCardGate::mark($token) right after a successful code check. Without that call, the wire card returns 404 for every client. Ship app/lib/WireCardGate.php and the code-entry change first or in the same release. The test must print ALL PASS with WIRE_CODE_FILE set.",
  "what": "Finding f10 (med): the wire-card picture was served to anyone holding the order link once the code had been entered anywhere. Now the code-entry step marks the browser's session with a hash of the order token, and wire-card.php serves the picture only when that marker is present and younger than ClientOrders::WIRE_MINUTES. All existing checks (pending, wireLeft, pay_method wire) are unchanged. Every failure is the same 404.",
  "files": [
    "files/app/lib/WireCardGate.php",
    "files/website/api/wire-card.php"
  ],
  "decisions": [
    "NEEDS A PERSON'S DECISION: is tying the wire details to one browser acceptable? A client who switches devices must re-enter the code. Default implemented: yes, bound to the session (safest).",
    "Marker lifetime = ClientOrders::WIRE_MINUTES, falling back to 15 minutes if the constant is absent. ClientOrders::wireLeft() still applies as well.",
    "wire-card.php never creates a session for requests without a session cookie. The session is closed right after reading so the image request does not hold the session lock.",
    "mark() regenerates the session id when access is granted (anti-fixation).",
    "Only sha256(token) is stored in the session. The map is capped at 20 entries and expired entries are pruned."
  ],
  "tests": [
    "tests/wire-card-gate-test.php (place under tools/wire-card-tests/; set WIRE_CODE_FILE to the code-entry file)"
  ],
  "risks": [
    "Nothing here has been run, tested or verified. It is a draft for the lane session.",
    "If the code-entry step is not wired to WireCardGate::mark(), every client loses the wire details (fail-closed outage).",
    "If app/bootstrap.php already starts or configures sessions (name, cookie path, save handler), WireCardGate uses the active session as is. If bootstrap starts sessions on a narrower cookie path than /api/, the marker will not reach wire-card.php.",
    "session_regenerate_id in mark() could disturb other session-dependent code on that request (e.g. a CSRF token tied to the session id).",
    "Clients who verified the code before deploy will lose the card until they re-enter the code."
  ],
  "verify": [
    "file not shown: the code-entry / code-verification step (the order page or the endpoint that checks the emailed code). Add require_once of app/lib/WireCardGate.php and call WireCardGate::mark((string) $token) only after a successful code check, before any output.",
    "file not shown: app/bootstrap.php. Check whether it starts sessions and with which cookie params, and confirm the session cookie is sent to /api/wire-card.php.",
    "file not shown: app/lib/ClientOrders.php. Confirm WIRE_MINUTES exists and that byToken() takes the raw ?t= token, the same value passed to mark().",
    "Manual check: enter the code in browser A and confirm the picture loads. Open the same link in private browser B without the code and confirm 404. Wait past WIRE_MINUTES and confirm 404.",
    "Confirm the response headers still include Cache-Control: no-store and no session cache-limiter headers override it.",
    "Person's decision above (device binding) is confirmed before release."
  ],
  "regions": false
}
=== END FILE ===