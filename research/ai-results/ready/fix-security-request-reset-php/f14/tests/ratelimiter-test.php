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
