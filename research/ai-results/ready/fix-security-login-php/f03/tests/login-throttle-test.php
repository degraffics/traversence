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
