<?php

declare(strict_types=1);

// Plain PHP test for f13. Run: php login-error-map-test.php

$lib = __DIR__ . '/../files/app/lib/LoginErrorMap.php';
if (!is_file($lib)) { $lib = __DIR__ . '/../../app/lib/LoginErrorMap.php'; }
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

$g = LoginErrorMap::GENERIC_MESSAGE;

check('default flag is false (safest)', LoginErrorMap::STATUS_AFTER_PASSWORD_CONFIRMED === false);
check('default: 4090 -> generic 401', LoginErrorMap::forException(4090, 'Account is closing') === [401, 'invalid_credentials', $g]);
check('default: 4030 -> generic 401', LoginErrorMap::forException(4030, 'Please confirm your email') === [401, 'invalid_credentials', $g]);
check('unknown user -> generic 401, fixed text', LoginErrorMap::forException(0, 'No such user') === [401, 'invalid_credentials', $g]);
check('wrong password -> same response as unknown user', LoginErrorMap::forException(0, 'Wrong password') === LoginErrorMap::forException(0, 'No such user'));
check('flag on: 4090 -> 409 closing', LoginErrorMap::forException(4090, 'Closing', true) === [409, 'closing', 'Closing']);
check('flag on: 4030 -> 403 unconfirmed', LoginErrorMap::forException(4030, 'Unconfirmed', true) === [403, 'unconfirmed', 'Unconfirmed']);
check('flag on: other -> generic 401', LoginErrorMap::forException(1, 'x', true) === [401, 'invalid_credentials', $g]);

$src = (string) @file_get_contents($login);
check('login.php found', $src !== '');
check('old 409 passthrough removed', !str_contains($src, "Response::error(\$e->getMessage(), 409, 'closing')"));
check('old 403 passthrough removed', !str_contains($src, "Response::error(\$e->getMessage(), 403, 'unconfirmed')"));
check('old raw-message 401 removed', !str_contains($src, "Response::error(\$e->getMessage(), 401, 'invalid_credentials')"));
check('uses LoginErrorMap', str_contains($src, 'LoginErrorMap::forException('));
check('401 still counted by limiter', str_contains($src, '$throttle->recordFailure($clientIp, $email);'));
check('f12 secret check still present', str_contains($src, 'TestLoginSecret::matches('));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
