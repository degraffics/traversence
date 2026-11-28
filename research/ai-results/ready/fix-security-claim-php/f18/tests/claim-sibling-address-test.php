<?php
// tools/claim-tests/claim-sibling-address-test.php (f18): 'locations' only returns street addresses for claims whose
// status is in $addressStatuses (empty by default = never). Plain PHP, prints PASS/FAIL/SKIP.
// Static checks always run. The HTTP check runs only with the env vars below, only on staging, and only calls the
// read-only 'locations' action (no writes, no 'activate', no money moves).
//   CLAIM_PHP     path to claim.php (optional; otherwise looked up next to this test)
//   CLAIM_URL     full URL of /listing/api/claim.php on staging
//   CLAIM_A_COOKIE / CLAIM_A_CSRF / CLAIM_A_ID   a member and one of their own claims (ideally not yet confirmed)
declare(strict_types=1);

final class T {
    public static int $fail = 0;
    public static function check(string $name, bool $ok, string $why = ''): void {
        echo ($ok ? 'PASS ' : 'FAIL ') . $name . (!$ok && $why !== '' ? ' (' . $why . ')' : '') . "\n";
        if (!$ok) self::$fail++;
    }
}

$path = (string) (getenv('CLAIM_PHP') ?: '');
foreach ([__DIR__ . '/../files/listing/api/claim.php', __DIR__ . '/../../listing/api/claim.php', __DIR__ . '/../../public_html/listing/api/claim.php'] as $p) {
    if ($path === '' && is_file($p)) $path = $p;
}
if ($path === '' || !is_file($path)) { echo "FAIL claim.php not found (set CLAIM_PHP)\n"; exit(1); }
$src = (string) file_get_contents($path);

if (function_exists('exec')) {
    $o = []; $rc = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
    T::check('php -l claim.php', $rc === 0, implode(' ', $o));
} else {
    echo "SKIP php -l (exec disabled)\n";
}

$switchAt = strpos($src, 'switch ($action) {');
$end = strpos($src, '} catch (DomainException');
$sw = $switchAt !== false && $end !== false ? substr($src, $switchAt, $end - $switchAt) : '';
$parts = preg_split("/\n\s*case '([a-z_]+)':/", $sw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
$cases = [];
for ($i = 1; $i + 1 < count($parts); $i += 2) $cases[$parts[$i]] = $parts[$i + 1];
$loc = $cases['locations'] ?? '';

T::check('$addressStatuses defined', preg_match('/\$addressStatuses = \[[^\]]*\];/', $src) === 1);
T::check("case 'locations' found", $loc !== '');
T::check('locations decides $showAddress from the claim status', str_contains($loc, "\$showAddress = in_array((string) \$c['status'], \$addressStatuses, true);"));
T::check('address is gated on $showAddress', str_contains($loc, "'address' => \$showAddress ? (\$r['address'] ?? '') : ''"));
T::check('no ungated address left', !str_contains($loc, "'address' => \$r['address'] ?? ''"));
T::check('locations keeps its own owner check', str_contains($loc, "(int) \$c['user_id'] !== \$uid"));
T::check('f04 ownership guard still present before the switch',
    ($g = strpos($src, 'if (in_array($action, $ownedActions, true)) {')) !== false && $switchAt !== false && $g < $switchAt);
T::check('f17 dispute response still has no claim_id', isset($cases['dispute']) && !str_contains($cases['dispute'], "'claim_id'"));

// the gating expression itself, run on sample rows (mirrors the line above)
$rows = [['id' => 1, 'name' => 'A', 'city' => 'X', 'address' => '1 Main St'], ['id' => 2, 'name' => 'B', 'city' => 'Y']];
foreach ([[[], 'pending', false], [['confirmed'], 'pending', false], [['confirmed'], 'confirmed', true]] as [$statuses, $status, $want]) {
    $showAddress = in_array($status, $statuses, true);
    $got = array_map(fn ($r) => $showAddress ? ($r['address'] ?? '') : '', $rows);
    $leak = $got[0] !== '';
    T::check('sample: status ' . $status . ' with allowlist [' . implode(',', $statuses) . '] ' . ($want ? 'shows' : 'hides') . ' address', $leak === $want);
}

$url = (string) (getenv('CLAIM_URL') ?: '');
$env = ['CLAIM_A_COOKIE', 'CLAIM_A_CSRF', 'CLAIM_A_ID'];
$have = $url !== '' && !in_array(false, array_map(fn ($k) => getenv($k) !== false && getenv($k) !== '', $env), true);
if (!$have || !function_exists('curl_init')) {
    echo "SKIP HTTP check (set CLAIM_URL and " . implode(', ', $env) . "; needs ext-curl)\n";
} else {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . getenv('CLAIM_A_CSRF'), 'Cookie: ' . getenv('CLAIM_A_COOKIE')],
        CURLOPT_POSTFIELDS => json_encode(['action' => 'locations', 'claim_id' => (int) getenv('CLAIM_A_ID')]),
    ]);
    $res = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    T::check('owner gets 200 on locations', $code === 200, "HTTP $code");
    $find = function ($v, string $key) use (&$find) {
        if (!is_array($v)) return null;
        if (array_key_exists($key, $v)) return $v[$key];
        foreach ($v as $x) { $f = $find($x, $key); if ($f !== null) return $f; }
        return null;
    };
    $json = json_decode($res, true);
    $shown = $find($json, 'addresses_shown');
    $locs = $find($json, 'locations');
    T::check('response says whether addresses are shown', is_bool($shown));
    if ($shown === false && is_array($locs)) {
        T::check('no address in any location while hidden', array_filter($locs, fn ($l) => ($l['address'] ?? '') !== '') === []);
    }
}

echo T::$fail === 0 ? "ALL PASS\n" : T::$fail . " FAILED\n";
exit(T::$fail === 0 ? 0 : 1);
