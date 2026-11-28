<?php
// tools/market-tests/f05-rental-return-once.php: checks the installed MarketCheckout.php. Plain PHP, prints PASS/FAIL.
// No database, no gateway, no money: it reads the source only.
declare(strict_types=1);

$file = getenv('MARKET_CHECKOUT') ?: dirname(__DIR__, 2) . '/app/lib/MarketCheckout.php';
$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; }
function method_src(string $file, string $name): ?string
{
    $t = token_get_all((string) file_get_contents($file)); $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1; while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n || !is_array($t[$j]) || $t[$j][0] !== T_STRING || $t[$j][1] !== $name) continue;
        $out = ''; $depth = 0; $started = false;
        for ($k = $i; $k < $n; $k++) {
            $s = is_array($t[$k]) ? $t[$k][1] : $t[$k]; $out .= $s;
            if ($s === '{' || (is_array($t[$k]) && in_array($t[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; $started = true; }
            elseif ($s === '}') { $depth--; if ($started && $depth === 0) return $out; }
        }
    }
    return null;
}

check('live file found: ' . $file, is_file($file));
if (!is_file($file)) exit(1);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lint, $rc);
check('php -l passes', $rc === 0);

$b = method_src($file, 'rentalReturned') ?? '';
check('rentalReturned found', $b !== '');
$claim = strpos($b, "SET pay_mode = 'returning'");
$cap = strpos($b, "payCall('capture'");
$ref = strpos($b, "payCall('refund'");
check('claims "returning" with a conditional UPDATE', $claim !== false && str_contains($b, 'AND pay_mode <=> ?') && str_contains($b, "status = 'paid'"));
check('claim checks rowCount before any money call', $claim !== false && ($rc2 = strpos($b, 'rowCount()', $claim)) !== false && $cap !== false && $rc2 < $cap && $ref !== false && $rc2 < $ref);
check('no direct MarketPay::capture/refund left in rentalReturned', !str_contains($b, 'MarketPay::capture(') && !str_contains($b, 'MarketPay::refund('));
check('idempotency key derived from the order id only', (bool) preg_match("/\\\$key = 'rental-return-' \\. \\(int\\) \\\$o\\['id'\\];/", $b));
check('record written only on the claimed row', str_contains($b, "SET pay_mode = 'returned', updated_at = ? WHERE id = ? AND pay_mode = 'returning'"));
$rec = strpos($b, "SET pay_mode = 'returned'");
check('record comes after the money call', $rec !== false && $cap !== false && $rec > $cap);
check('a gateway failure leaves it claimed and tickets our team', str_contains($b, 'GaugeTasks::add(') && str_contains($b, 'throw new RuntimeException('));
check('order is read fresh before the checks', strpos($b, 'self::get($pdo, (int) $o[\'id\'])') !== false && strpos($b, 'self::get($pdo, (int) $o[\'id\'])') < (int) strpos($b, "!== 'paid'"));
check('a zero refund is not sent', str_contains($b, '$card && $back > 0'));
$h = method_src($file, 'payCall') ?? '';
check('payCall passes the key only when MarketPay takes $idempotencyKey', str_contains($h, "'idempotencyKey'") && str_contains($h, 'idempotencyKey: $key'));

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
