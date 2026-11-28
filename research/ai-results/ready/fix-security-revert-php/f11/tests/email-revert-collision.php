<?php
// tools/auth-tests style: plain PHP, prints PASS/FAIL, exits 1 on any FAIL.
// Usage: php tests/email-revert-collision.php [path/to/revert.php]
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

// 2. Structure: check exists, inside the transaction, before any write.
$sql = 'SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1 FOR UPDATE';
$txPos = strpos($src, 'Database::transaction(');
$chkPos = strpos($src, $sql);
$tokPos = strpos($src, 'UPDATE email_confirmations SET confirmed_at');
$usrPos = strpos($src, 'UPDATE users SET email');
$bumpPos = strpos($src, 'Auth::bumpSessionVersion(');
check('collision SELECT (id != self) present', $chkPos !== false);
check('collision check is inside the transaction', $chkPos !== false && $txPos !== false && $chkPos > $txPos);
check('collision check runs before token is consumed', $chkPos !== false && $tokPos !== false && $chkPos < $tokPos);
check('collision check runs before users UPDATE', $chkPos !== false && $usrPos !== false && $chkPos < $usrPos);
check('collision check runs before session bump', $chkPos !== false && $bumpPos !== false && $chkPos < $bumpPos);
check('returns 409 email_in_use', (bool) preg_match("/409,\s*'email_in_use'/", $src));
check('unique-index race (23000) mapped to conflict', str_contains($src, "getCode() !== '23000'"));
check('other PDO errors are rethrown', str_contains($src, 'throw $e;'));
check('f02 password lock still present', str_contains($src, 'password_hash = :password_hash'));

// 3. Behaviour of the collision query (FOR UPDATE stripped; SQLite has no row locks).
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP - pdo_sqlite not available, query behaviour not exercised\n";
} else {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
    $db->exec("INSERT INTO users (id, email) VALUES (1, 'owner@example.test'), (2, 'other@example.test'), (3, 'attacker@example.test')");
    $q = $db->prepare(str_replace(' FOR UPDATE', '', $sql));

    // Self already holds the address (e.g. repeated revert) -> not a conflict.
    $q->execute(['email' => 'owner@example.test', 'id' => 1]);
    check('own address is not a conflict', $q->fetch() === false);

    // Free address -> not a conflict.
    $q->execute(['email' => 'free@example.test', 'id' => 3]);
    check('free address is not a conflict', $q->fetch() === false);

    // Address now owned by another user -> conflict.
    $q->execute(['email' => 'other@example.test', 'id' => 3]);
    check('address held by another user IS a conflict', $q->fetch() !== false);
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAIL(S)\n";
exit($fails === 0 ? 0 : 1);
