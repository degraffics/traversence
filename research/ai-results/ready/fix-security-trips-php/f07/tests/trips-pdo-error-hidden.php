<?php
// tools/trips-tests style (f07): a database fault on /api/trips.php never shows its text; generic 500 instead.
// Run: php tests/trips-pdo-error-hidden.php [path/to/api/trips.php]
declare(strict_types=1);

$file = $argv[1] ?? __DIR__ . '/../files/api/trips.php';
$fail = 0;
$check = function (string $what, bool $ok) use (&$fail): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$ok) $fail++; };

$src = @file_get_contents($file);
$check('the file can be read: ' . $file, is_string($src) && $src !== '');
if (!is_string($src) || $src === '') exit(1);

$check('why the order matters: PDOException is a RuntimeException', is_subclass_of(PDOException::class, RuntimeException::class));

$out = []; $code = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
$check('php -l passes', $code === 0);

preg_match_all('/\}\s*catch\s*\(\s*\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)\s+\$/', $src, $m, PREG_OFFSET_CAPTURE);
$names = array_map(fn ($x) => ltrim((string) $x[0], '\\'), $m[1]);
$iP = array_search('PDOException', $names, true);
$iR = array_search('RuntimeException', $names, true);
$check('there is a catch (PDOException ...)', $iP !== false);
$check('there is still a catch (RuntimeException ...)', $iR !== false);
$check('catch (PDOException) comes before catch (RuntimeException)', $iP !== false && $iR !== false && $iP < $iR);

if ($iP !== false) {
    $start = $m[0][$iP][1];
    $end = isset($m[0][$iP + 1]) ? $m[0][$iP + 1][1] : strlen($src);
    $block = substr($src, $start, $end - $start);
    $has = preg_match('/Response::error\((.*?)\);/s', $block, $em) === 1;
    $check('the PDOException block answers with Response::error', $has);
    $check('the PDOException answer does not use the exception text', $has && strpos($em[1], 'getMessage') === false && strpos($em[1], '$e') === false);
    $check('the PDOException answer is a 500', $has && preg_match('/,\s*500\s*,/', $em[1]) === 1);
    $check('the PDOException detail is logged for staff', strpos($block, 'error_log(') !== false);
    $check('the PDOException block rolls back an open transaction', strpos($block, 'rollBack()') !== false);
}

// the only place an exception's own text may reach the visitor is the RuntimeException (Trips' refusals) block
$check('exactly one Response::error($e->getMessage() remains', substr_count($src, 'Response::error($e->getMessage()') === 1);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAIL\n";
exit($fail === 0 ? 0 : 1);
