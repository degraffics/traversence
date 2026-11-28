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
