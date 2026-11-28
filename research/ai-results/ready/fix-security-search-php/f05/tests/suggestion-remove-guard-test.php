<?php
// tools/search-tests/suggestion-remove-guard-test.php — f05: unit checks for SuggestionRemoveGuard (no database).
// Run: php tools/search-tests/suggestion-remove-guard-test.php   (or set ROOT=/path/to/repo)

declare(strict_types=1);

$root = getenv('ROOT') ?: dirname(__DIR__, 2);
$lib = $root . '/app/lib/SuggestionRemoveGuard.php';
if (!is_file($lib)) { echo "FAIL cannot find $lib (set ROOT)\n"; exit(1); }
require_once $lib;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) $fails++;
}

$tok = 'abc123def456abc123def456';

// tokens
check('no expected token fails closed', !SuggestionRemoveGuard::tokenOk(null, $tok));
check('no supplied token fails', !SuggestionRemoveGuard::tokenOk($tok, null));
check('empty tokens fail', !SuggestionRemoveGuard::tokenOk('', ''));
check('wrong token fails', !SuggestionRemoveGuard::tokenOk($tok, $tok . 'x'));
check('matching token passes', SuggestionRemoveGuard::tokenOk($tok, $tok));

// where the token comes from
check('header token is read', SuggestionRemoveGuard::supplied(['HTTP_X_CSRF_TOKEN' => $tok], []) === $tok);
check('body token is read', SuggestionRemoveGuard::supplied([], ['csrf_token' => $tok]) === $tok);
check('array body token ignored', SuggestionRemoveGuard::supplied([], ['csrf_token' => [$tok]]) === null);

// whole request
check('POST with token passes', SuggestionRemoveGuard::requestOk([], ['csrf_token' => $tok], $tok));
check('POST without token fails', !SuggestionRemoveGuard::requestOk([], ['removed' => 'x'], $tok));
check('cross-site with right token fails', !SuggestionRemoveGuard::requestOk(['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_X_CSRF_TOKEN' => $tok], [], $tok));
check('same-origin with token passes', SuggestionRemoveGuard::requestOk(['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_X_CSRF_TOKEN' => $tok], [], $tok));
check('no session token fails closed', !SuggestionRemoveGuard::requestOk([], ['csrf_token' => $tok], null));

// who may block
check('GET never blocks, even for staff', !SuggestionRemoveGuard::mayBlock(false, true, true));
check('POST without token never blocks', !SuggestionRemoveGuard::mayBlock(true, false, true));
check('visitor POST never blocks', !SuggestionRemoveGuard::mayBlock(true, true, false));
check('staff POST with token blocks', SuggestionRemoveGuard::mayBlock(true, true, true));

// body parsing
check('form body used as is', SuggestionRemoveGuard::body(['removed' => 'a'], 'application/x-www-form-urlencoded', '') === ['removed' => 'a']);
check('JSON body parsed', SuggestionRemoveGuard::body([], 'application/json; charset=utf-8', '{"removed":"a","q":"b"}') === ['removed' => 'a', 'q' => 'b']);
check('bad JSON is empty', SuggestionRemoveGuard::body([], 'application/json', '{nope') === []);
check('non-JSON raw body ignored', SuggestionRemoveGuard::body([], 'text/plain', 'removed=a') === []);
check('oversize JSON ignored', SuggestionRemoveGuard::body([], 'application/json', '{"a":"' . str_repeat('x', 70000) . '"}') === []);
check('str() rejects arrays', SuggestionRemoveGuard::str(['removed' => ['x']], 'removed') === null);

// session token (only when sessions can start in CLI)
if (session_status() === PHP_SESSION_NONE && @session_start()) {
    unset($_SESSION['csrf_token']);
    check('session without token gives null', SuggestionRemoveGuard::sessionToken() === null);
    $_SESSION['csrf_token'] = $tok;
    check('session token read', SuggestionRemoveGuard::sessionToken() === $tok);
    session_destroy();
} else {
    echo "PASS session checks skipped (sessions unavailable in this CLI)\n";
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
