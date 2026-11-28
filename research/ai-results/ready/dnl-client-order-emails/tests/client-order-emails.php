<?php
// tests/client-order-emails.php — client order emails through the one dispatcher (0172). Static checks only: no database,
// no mail, no money (nothing here calls QuickBooksPay, Mailer or Notify::send).
// Run: php tests/client-order-emails.php [release root, default: this release folder]
// The database-backed checks (a member client gets a Pulse row and one email; a guest client gets ClientOrders::mail; no
// double email) need the order tests' harness, which wasn't attached: the lane adds them there.
declare(strict_types=1);

$root = rtrim($argv[1] ?? dirname(__DIR__), '/');
$lib = $root . '/files/app/lib';
$fail = 0;
$ok = function (string $name, bool $pass, string $why = '') use (&$fail): void {
    echo ($pass ? 'PASS ' : 'FAIL ') . $name . ($pass || $why === '' ? '' : ' — ' . $why) . "\n";
    if (!$pass) $fail++;
};
$lint = function (string $file): bool {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    return $code === 0;
};

// 1. Notify.php: syntax, and the two kinds
$nf = $lib . '/Notify.php';
$ok('Notify.php exists', is_file($nf));
$ok('Notify.php lints', is_file($nf) && $lint($nf));
if (is_file($nf)) {
    require_once $nf;
    foreach (['order.client' => 'notice', 'order.invoice' => 'alert'] as $k => $lvl) {
        $d = Notify::KINDS[$k] ?? null;
        $ok("$k registered", $d !== null);
        if (!$d) continue;
        $ok("$k level is $lvl (0172 §2.4)", $d[2] === $lvl, 'got ' . $d[2]);
        $ok("$k is for members", $d[3] === 'member');
        $ok("$k email on by default", $d[5] === true);
        $ok("$k email can't be turned off (0172 §1.6)", $d[6] === false);
        $ok("$k by system email (free)", $d[7] === 'system');
        $ok("$k not updated in place", !in_array($k, Notify::IN_PLACE, true));
        $ok("$k in kinds()", isset(Notify::kinds()[$k]));
    }
    $src = (string) file_get_contents($nf);
    $ok('no Stop these on a kind whose email can\'t be off', str_contains($src, '(!$def[6] ? \'You get this email because it\\\'s about "\''));
    $ok('settings says an order always comes by email', str_contains($src, 'it\\\'s about an order of yours.'));
}

// 2. ClientOrders regions: syntax (wrapped in a class), and every client email goes through tell()
$regions = glob($lib . '/ClientOrders.php.region-*.php') ?: [];
$ok('four ClientOrders regions', count($regions) === 4, 'found ' . count($regions));
$all = '';
foreach ($regions as $r) {
    $code = (string) file_get_contents($r);
    $ok(basename($r) . ' has an ANCHOR', str_contains($code, '// ANCHOR: replaces '));
    $body = preg_replace('/^<\?php\s*/', '', $code);
    $tmp = tempnam(sys_get_temp_dir(), 'coe') . '.php';
    file_put_contents($tmp, "<?php\nfinal class ClientOrdersRegionLint {\n" . $body . "\n}\n");
    $ok(basename($r) . ' lints inside a class', $lint($tmp));
    @unlink($tmp);
    $all .= $body;
}
$withoutTell = preg_replace('/private static function tell\(.*?\n    \}\n/s', '', $all);
$ok('no direct self::mail( outside tell()', !str_contains((string) $withoutTell, 'self::mail('));
$ok('tell() falls back to self::mail', str_contains($all, 'return (bool) self::mail($o, $subject, $body);'));
$ok('tell() passes no thread (each update is its own email)', !preg_match("/'thread'\s*=>/", $all));
$ok('send() records the result', str_contains($all, "self::\$mailed = self::tell(\$pdo, \$o, 'order.client'"));
$ok('cancel through tell', str_contains($all, "'cancelled', 'Your Traversence order was cancelled'"));
$ok('setStage through tell', str_contains($all, "'stage-' . \$stage"));
$ok('invoice due through tell as order.invoice', str_contains($all, "self::tell(\$pdo, \$o, 'order.invoice', 'invoice-due'"));
$ok('no money calls in the regions beyond what was there', substr_count($all, 'QuickBooksPay') === 0);

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
