<?php
// tools/claim-tests/claim-ownership-test.php (f04): every claim_id action in listing/api/claim.php is owner-checked.
// Plain PHP, prints PASS/FAIL/SKIP. Static checks always run. The HTTP check runs only when the CLAIM_* env vars are set,
// and only against staging: it calls the read-only 'locations' action and never 'activate' (no money moves) or any writing action.
//   CLAIM_PHP     path to claim.php (optional; otherwise looked up next to this test)
//   CLAIM_URL     full URL of /listing/api/claim.php on staging
//   CLAIM_A_COOKIE / CLAIM_A_CSRF   session cookie header value + CSRF token of member A (owns CLAIM_A_ID)
//   CLAIM_B_COOKIE / CLAIM_B_CSRF   the same for member B (a different, ordinary member)
//   CLAIM_A_ID    a claim id owned by member A
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

// 1. syntax
if (function_exists('exec')) {
    $o = []; $rc = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
    T::check('php -l claim.php', $rc === 0, implode(' ', $o));
} else {
    echo "SKIP php -l (exec disabled)\n";
}

// 2. the owned-actions list and the guard before the switch
$owned = [];
if (preg_match('/\$ownedActions = \[([^\]]*)\];/', $src, $m)) {
    preg_match_all("/'([a-z_]+)'/", $m[1], $mm);
    $owned = $mm[1];
}
T::check('$ownedActions list present', $owned !== []);
$switchAt = strpos($src, 'switch ($action) {');
$guardAt = strpos($src, 'if (in_array($action, $ownedActions, true)) {');
T::check('switch found', $switchAt !== false);
T::check('guard found before the switch', $guardAt !== false && $switchAt !== false && $guardAt < $switchAt);
T::check('guard compares the claim owner with the signed-in user', str_contains($src, "(int) \$owned['user_id'] !== \$uid"));
T::check('guard answers missing and not-yours alike with 404', str_contains($src, "Response::error('Claim not found.', 404, 'not_found');"));

// 3. every case that uses $cid is in the owned list; every owned action is a real case
$end = strpos($src, '} catch (DomainException');
$sw = $switchAt !== false && $end !== false ? substr($src, $switchAt, $end - $switchAt) : '';
$parts = preg_split("/\n\s*case '([a-z_]+)':/", $sw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
$cases = [];
for ($i = 1; $i + 1 < count($parts); $i += 2) $cases[$parts[$i]] = $parts[$i + 1];
T::check('cases parsed', count($cases) >= 15, 'got ' . count($cases));
foreach ($cases as $name => $code) {
    if (str_contains($code, '$cid')) T::check("case '$name' uses claim_id and is owner-guarded", in_array($name, $owned, true));
}
foreach ($owned as $name) T::check("owned action '$name' is a real case", isset($cases[$name]));

// 4. optional live check against staging (read-only action only)
$url = (string) (getenv('CLAIM_URL') ?: '');
$env = ['CLAIM_A_COOKIE', 'CLAIM_A_CSRF', 'CLAIM_B_COOKIE', 'CLAIM_B_CSRF', 'CLAIM_A_ID'];
$have = $url !== '' && !in_array(false, array_map(fn ($k) => getenv($k) !== false && getenv($k) !== '', $env), true);
if (!$have || !function_exists('curl_init')) {
    echo "SKIP HTTP check (set CLAIM_URL and " . implode(', ', $env) . "; needs ext-curl)\n";
} else {
    $post = function (string $cookie, string $csrf, array $b) use ($url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $csrf, 'Cookie: ' . $cookie],
            CURLOPT_POSTFIELDS => json_encode($b),
        ]);
        $res = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$code, $res];
    };
    $id = (int) getenv('CLAIM_A_ID');
    [$codeB, $resB] = $post((string) getenv('CLAIM_B_COOKIE'), (string) getenv('CLAIM_B_CSRF'), ['action' => 'locations', 'claim_id' => $id]);
    T::check('member B gets 404 on member A\'s claim', $codeB === 404, "HTTP $codeB");
    T::check('member B sees no locations or text code', !str_contains($resB, '"locations"') && !str_contains($resB, 'text_code'));
    [$codeA] = $post((string) getenv('CLAIM_A_COOKIE'), (string) getenv('CLAIM_A_CSRF'), ['action' => 'locations', 'claim_id' => $id]);
    T::check('member A still gets their own claim\'s locations', $codeA === 200, "HTTP $codeA");
    [$code0] = $post((string) getenv('CLAIM_B_COOKIE'), (string) getenv('CLAIM_B_CSRF'), ['action' => 'locations', 'claim_id' => 0]);
    T::check('claim_id 0 gets 404', $code0 === 404, "HTTP $code0");
}

echo T::$fail === 0 ? "ALL PASS\n" : T::$fail . " FAILED\n";
exit(T::$fail === 0 ? 0 : 1);
