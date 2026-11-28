<?php
// tools/search-tests/search_map_confidential_test.php  (release f01)
//
// Checks that the map view of api/search_listings.php never returns a pin for a
// confidential listing (ListingSafety::isConfidential, decisions/0044).
//
// Part A (always runs): static checks on the source file + php -l.
// Part B (optional, live): when BASE_URL and CONF_ID are set, it calls
//   BASE_URL/api/search_listings.php?view=map&ids=CONF_ID   (signed out, no cookies)
// and requires that no pin with that id comes back. CONF_ID must be a listing
// staff know is confidential (a shelter-type listing). PUB_ID, if set, must be
// a public listing with coordinates and MUST come back (guards against "fixed by
// returning nothing").
//
// Usage:
//   php search_map_confidential_test.php [path/to/api/search_listings.php]
//   BASE_URL=https://staging.example CONF_ID=123 PUB_ID=456 php search_map_confidential_test.php
//
// Read-only. No money moves, no writes.

$fails = 0;
function check(string $label, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) $fails++;
}

// ---- locate the file ----
$file = $argv[1] ?? (getenv('SEARCH_LISTINGS_FILE') ?: '');
if ($file === '') {
    $root = dirname(__DIR__, 2);
    foreach ([$root . '/api/search_listings.php', $root . '/public_html/api/search_listings.php', __DIR__ . '/../files/api/search_listings.php'] as $cand) {
        if (is_file($cand)) { $file = $cand; break; }
    }
}
if ($file === '' || !is_file($file)) {
    echo "FAIL: search_listings.php not found (pass its path as the first argument)" . PHP_EOL;
    exit(1);
}
$src = (string) file_get_contents($file);

// ---- Part A: static ----
$out = []; $rc = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
check('php -l is clean', $rc === 0);

$mapStart = strpos($src, "if (\$view === 'map') {");
$mapEnd   = $mapStart === false ? false : strpos($src, '// ---- The requested page ----', $mapStart);
check('map block found', $mapStart !== false && $mapEnd !== false);
$map = ($mapStart !== false && $mapEnd !== false) ? substr($src, $mapStart, $mapEnd - $mapStart) : '';

check('pin SELECT carries x.category_id', (bool) preg_match('/SELECT x\.id,[^"]*x\.category_id[^"]*FROM \(\$pinInner\)/s', $map));
$confPos  = strpos($map, 'ListingSafety::isConfidential');
$pushPos  = strpos($map, '$pins[] = [');
check('map block calls ListingSafety::isConfidential', $confPos !== false);
check('confidential test runs before pins are built', $confPos !== false && $pushPos !== false && $confPos < $pushPos);
check('managedSet is computed on the filtered rows (after the test)', $confPos !== false && ($m = strpos($map, 'managedSet')) !== false && $confPos < $m);
check('truncated uses the pre-filter count', strpos($map, "'truncated' => (\$total > \$pinFetched && \$pinFetched >= MAX_PINS)") !== false);
check('ListingSafety.php still required at top', strpos($src, "require_once __DIR__ . '/../../app/lib/ListingSafety.php';") !== false);

// ---- Part B: live, optional ----
$base   = rtrim((string) getenv('BASE_URL'), '/');
$confId = (int) getenv('CONF_ID');
$pubId  = (int) getenv('PUB_ID');
if ($base === '' || $confId <= 0) {
    echo 'SKIP: live check (set BASE_URL and CONF_ID to run it)' . PHP_EOL;
} else {
    $fetch = function (string $ids) use ($base): ?array {
        $url = $base . '/api/search_listings.php?view=map&ids=' . rawurlencode($ids);
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 20, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;
        $j = json_decode($body, true);
        return is_array($j) ? $j : null;
    };
    $j = $fetch((string) $confId);
    check('live: map response is JSON success', is_array($j) && ($j['success'] ?? false) === true);
    $pinIds = is_array($j) ? array_map(fn ($p) => (int) ($p['id'] ?? 0), $j['pins'] ?? []) : [];
    check("live: confidential listing $confId has no pin", is_array($j) && !in_array($confId, $pinIds, true));
    $txt = is_array($j) ? json_encode($j) : '';
    check('live: response carries no lat/lon at all for the confidential-only query', is_array($j) && count($j['pins'] ?? []) === 0 && strpos($txt, '"lat"') === false);
    if ($pubId > 0) {
        $j2 = $fetch($pubId . ',' . $confId);
        $ids2 = is_array($j2) ? array_map(fn ($p) => (int) ($p['id'] ?? 0), $j2['pins'] ?? []) : [];
        check("live: public listing $pubId still has its pin", in_array($pubId, $ids2, true));
        check("live: confidential listing $confId still absent beside a public one", !in_array($confId, $ids2, true));
    } else {
        echo 'SKIP: public-pin regression (set PUB_ID)' . PHP_EOL;
    }
}

echo ($fails === 0 ? 'ALL PASS' : "FAILURES: $fails") . PHP_EOL;
exit($fails === 0 ? 0 : 1);
