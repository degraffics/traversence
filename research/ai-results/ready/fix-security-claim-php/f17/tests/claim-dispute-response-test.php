<?php
// tools/claim-tests/claim-dispute-response-test.php (f17): the 'dispute' action returns only dispute_id, never the
// disputed (other party's) claim_id. Plain PHP, prints PASS/FAIL/SKIP. Static only: filing a real dispute writes data and
// emails the owner, so the live check is a manual step in release.json 'verify'. No money moves.
//   CLAIM_PHP  path to claim.php (optional; otherwise looked up next to this test)
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
$d = $cases['dispute'] ?? '';

T::check("case 'dispute' found", $d !== '');
T::check('dispute response returns dispute_id', str_contains($d, "'dispute_id' => \$id"));
T::check('dispute response has no claim_id key', !str_contains($d, "'claim_id'"));
T::check('dispute case no longer reads disputes.claim_id', !str_contains($d, '->query(') && !str_contains($d, 'SELECT claim_id'));
T::check('no raw SQL query left in the endpoint', !str_contains($src, '$pdo->query('));
// f04 is carried in this file: make sure it wasn't lost
T::check('f04 ownership guard still present before the switch',
    ($g = strpos($src, 'if (in_array($action, $ownedActions, true)) {')) !== false && $switchAt !== false && $g < $switchAt);

echo T::$fail === 0 ? "ALL PASS\n" : T::$fail . " FAILED\n";
exit(T::$fail === 0 ? 0 : 1);
