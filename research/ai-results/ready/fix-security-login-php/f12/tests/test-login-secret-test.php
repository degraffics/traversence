<?php

declare(strict_types=1);

// Plain PHP test for f12. Run: php test-login-secret-test.php

$lib = __DIR__ . '/../files/app/lib/TestLoginSecret.php';
if (!is_file($lib)) { $lib = __DIR__ . '/../../app/lib/TestLoginSecret.php'; }
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

$good = str_repeat('k', 40);
check('matching secret accepted', TestLoginSecret::matches($good, $good));
check('wrong secret rejected', !TestLoginSecret::matches($good, str_repeat('j', 40)));
check('empty given rejected', !TestLoginSecret::matches($good, ''));
check('unset configured rejected', !TestLoginSecret::matches('', ''));
check('short configured secret disables bypass', !TestLoginSecret::matches('short', 'short'));
check('prefix of secret rejected', !TestLoginSecret::matches($good, substr($good, 0, 39)));

putenv('TEST_LOGIN_SECRET');
unset($_ENV['TEST_LOGIN_SECRET'], $_SERVER['TEST_LOGIN_SECRET']);
check('configured() empty when unset', TestLoginSecret::configured() === '');
putenv('TEST_LOGIN_SECRET=' . $good);
check('configured() reads env', TestLoginSecret::configured() === $good);
putenv('TEST_LOGIN_SECRET');

$libSrc = (string) file_get_contents($lib);
check('library uses hash_equals', str_contains($libSrc, 'hash_equals('));

$src = (string) @file_get_contents($login);
check('login.php found', $src !== '');
$pSecret = strpos($src, 'TestLoginSecret::matches(');
$pCheck = strpos($src, 'TestAccounts::checkRequest(');
check('secret checked before (and ANDed with) TestAccounts', $pSecret !== false && $pCheck !== false && $pSecret < $pCheck && str_contains($src, "\n    && TestAccounts::checkRequest("));
check('old email-only bypass removed', !str_contains($src, "if (!TestAccounts::checkRequest("));
check('f03 limiter still present', str_contains($src, "429, 'rate_limited'"));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
