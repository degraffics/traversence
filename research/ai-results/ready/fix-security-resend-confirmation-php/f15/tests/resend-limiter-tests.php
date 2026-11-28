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
