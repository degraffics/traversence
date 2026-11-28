<?php
// tools/search-tests/search-removed-get-test.php — f05: source checks on api/search.php (no database, no requests).
// A GET ?removed= must pass no user id; the block path must be a checked POST.
// Run: php tools/search-tests/search-removed-get-test.php   (or set SEARCH_PHP=/path/to/api/search.php, ROOT=/path/to/repo)

declare(strict_types=1);

$root = getenv('ROOT') ?: dirname(__DIR__, 2);
$cands = array_filter(array_merge([getenv('SEARCH_PHP') ?: '', $root . '/api/search.php'], glob($root . '/*/api/search.php') ?: []));
$file = '';
foreach ($cands as $c) if (is_file($c)) { $file = $c; break; }
if ($file === '') { echo "FAIL cannot find api/search.php (set SEARCH_PHP)\n"; exit(1); }
$src = (string) file_get_contents($file);

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$ok) $fails++;
}

check('old GET block call is gone',
    !str_contains($src, "(string) (\$_GET['rk'] ?? 'pred'), \$canTeach ? Auth::currentUserId() : null)"));

$get = strpos($src, "\$rmWhat = (string) \$_GET['removed'];");
$getNull = $get !== false ? strpos($src, '$rmBy = null;', $get) : false;
$getEnd = $get !== false ? strpos($src, '}', $get) : false;
check('GET branch sets no user id', $get !== false && $getNull !== false && $getEnd !== false && $getNull < $getEnd);

$guard = strpos($src, 'SuggestionRemoveGuard::requestOk(');
$block = strpos($src, 'SuggestionRemoveGuard::mayBlock(');
$remove = strpos($src, 'SuggestionFlags::remove($pdo, $rmQ, $rmWhat, $rmKind, $rmBy)');
check('CSRF check exists', $guard !== false);
check('CSRF check comes before the block decision', $guard !== false && $block !== false && $guard < $block);
check('the block decision comes before SuggestionFlags::remove', $block !== false && $remove !== false && $block < $remove);
check('failed check answers 403', str_contains($src, "Response::error('Please refresh the page and try again.', 403, 'csrf_failed')"));
check('POST path only on POST', str_contains($src, "strtoupper((string) (\$_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'"));

// syntax check when a php binary is at hand
$php = PHP_BINARY;
if ($php !== '' && function_exists('shell_exec')) {
    $out = (string) shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1');
    check('php -l search.php', str_contains($out, 'No syntax errors'));
}

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
