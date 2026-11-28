<?php
// tools/market-tests/f06-no-cancel-open-card.php: checks the installed MarketCheckout.php. Plain PHP, prints PASS/FAIL.
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
            if ($s === '{' || (is_array($t[$k]) && in_array($t[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY
