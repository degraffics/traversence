<?php
// tools/trips-tests style (f23): api/trips.php checks tz once, at the top; only real time zone names are kept.
// Runs the file's own $tz line (not a copy) against sample bodies.
// Run: php tests/trips-tz-checked.php [path/to/api/trips.php]
declare(strict_types=1);

$file = $argv[1] ?? __DIR__ . '/../files/api/trips.php';
$fail = 0;
$check = function (string $what, bool $ok) use (&$fail): void { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$ok) $fail++; };

$src = @file_get_contents($file);
$check('the file can be read: ' . $file, is_string($src) && $src !== '');
if (!is_string($src) || $src === '') exit(1);

$out = []; $code = 1;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
$check('php -l passes', $code === 0);

$found = preg_match('/^[ \t]*(\$tz = [^\n]*)$/m', $src, $lm) === 1;
$check('there is one $tz = ... line', $found && preg_match_all('/^[ \t]*\$tz = /m', $src) === 1);
if (!$found) { echo "$fail FAIL\n"; exit(1); }
$line = $lm[1];
$check('the $tz line uses the time zone list', strpos($line, 'DateTimeZone::listIdentifiers') !== false);
$check("\$b['tz'] is read only on the \$tz line", substr_count($src, "\$b['tz']") === substr_count($line, "\$b['tz']"));
$check('the old pattern check on $tz is gone', strpos($src, "preg_match('#^[A-Za-z_]+") === false);
$check('from_plan falls back to America/Phoenix', strpos($src, "\$tzName = \$tz !== '' ? \$tz : 'America/Phoenix';") !== false);

$run = function (array $b) use ($line) { $tz = null; eval($line); return $tz; };
$missing = new stdClass();
$cases = [
    ['America/Phoenix', 'America/Phoenix'],
    ['Europe/London', 'Europe/London'],
    ['UTC', 'UTC'],
    ['Asia/Calcutta', 'Asia/Calcutta'],                  // older name some browsers still send
    ['America/Argentina/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
    ['', ''],
    [$missing, ''],
    ['Foo/Bar', ''],                                     // passed the old pattern, then threw in new DateTimeZone
    ['america/phoenix', ''],
    ['<script>alert(1)</script>', ''],
    ["America/Phoenix\n", ''],
    [str_repeat('A/', 100), ''],
    ['+05:00', ''],
    [['America/Phoenix'], ''],
    [7, ''],
    [null, ''],
];
foreach ($cases as [$in, $want]) {
    $b = $in === $missing ? ['action' => 'claim'] : ['action' => 'claim', 'tz' => $in];
    $got = $run($b);
    $check('tz ' . ($in === $missing ? '(not sent)' : json_encode($in)) . ' -> ' . json_encode($want), $got === $want);
    if (is_string($got) && $got !== '') {
        $ok = true; try { new DateTimeZone($got); } catch (Throwable $e) { $ok = false; }
        $check('  ' . $got . ' opens as a DateTimeZone', $ok);
    }
}

// f07 carried in this file: the PDOException catch must still come before the RuntimeException catch
$p = strpos($src, 'catch (PDOException'); $r = strpos($src, 'catch (RuntimeException');
$check('f07 kept: catch (PDOException) before catch (RuntimeException)', $p !== false && $r !== false && $p < $r);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAIL\n";
exit($fail === 0 ? 0 : 1);
