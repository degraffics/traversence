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
