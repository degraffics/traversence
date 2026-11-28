<?php
// tools/auth-tests style: plain PHP, prints PASS/FAIL, exits 1 on any FAIL.
// Usage: php tests/email-revert-forced-reset.php [path/to/revert.php]
// Default target is this release's copy; pass the live path after deploy.

declare(strict_types=1);

$target = $argv[1] ?? __DIR__ . '/../files/sync-storage/api/auth/email/revert.php';
$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . " - $name\n";
    if (!$ok) {
        $fails++;
    }
}

check('target file exists', is_file($target));
$src = (string) @file_get_contents($target);

// 1. Lint.
$out = [];
$rc = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $out, $rc);
check('php -l passes', $rc === 0);

// 2. The users UPDATE sets password_hash together with email.
check(
    'users UPDATE sets email and password_hash in one statement',
    (bool) preg_match('/UPDATE users SET email = :email, password_hash = :password_hash/', $src)
);

// 3. That UPDATE is inside the Database::transaction closure, before bumpSessionVersion.
$txPos = strpos($src, 'Database::transaction(');
$updPos = strpos($src, 'password_hash = :password_hash');
$bumpPos = strpos($src, 'Auth::bumpSessionVersion(');
check('password lock is inside the transaction', $txPos !== false && $updPos !== false && $updPos > $txPos);
check('session bump still present after the lock', $bumpPos !== false && $bumpPos > $updPos);

// 4. The hash is computed outside (before) the transaction.
$hashPos = strpos($src, '$lockedPasswordHash = password_hash(');
check('locked hash computed before the transaction', $hashPos !== false && $hashPos < $txPos);

// 5. Behaviour of the exact locking expression used in the endpoint.
$h1 = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$h2 = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
check('locked hash is a recognised algorithm', password_get_info($h1)['algo'] !== null && password_get_info($h1)['algo'] !== 0);
check('locked hashes differ per call', $h1 !== $h2);
$guesses = ['', 'password', '0', 'null', $h1, str_repeat('a', 64)];
$anyMatch = false;
foreach ($guesses as $g) {
    if (password_verify($g, $h1)) {
        $anyMatch = true;
    }
}
check('no guessable input verifies against the locked hash', !$anyMatch);
check('random secret is 64 hex chars (no NUL, under bcrypt 72-byte limit)', strlen(bin2hex(random_bytes(32))) === 64);

// 6. The visitor is told how to recover.
check('response tells user to use Forgot password', str_contains($src, 'Forgot password'));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAIL(S)\n";
exit($fails === 0 ? 0 : 1);
