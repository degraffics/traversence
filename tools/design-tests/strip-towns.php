<?php
// tools/design-tests/strip-towns.php — the hub's weather strip (Guides::stripPick, 2026-11-19): one town per district
// first, then the best left; a town already shown (same name, or a point within about 2 km) is skipped.
// Run: php tools/design-tests/strip-towns.php <path to the release's or the mirror's app/lib/Guides.php>
declare(strict_types=1);
$f = $argv[1] ?? __DIR__ . '/../../../traverse-3way-sync/sync-app/lib/Guides.php';
if (!class_exists('PDO')) { echo "needs PDO\n"; exit(1); }
// Guides.php needs its neighbours only when called through the database; stripPick is pure
$src = file_get_contents($f);
$src = preg_replace(['/^\s*require_once[^;]*;\s*$/m', '/declare\(strict_types=1\);/'], '', $src);
eval('?>' . $src);
$pass = 0; $fail = 0;
function ok(bool $c, string $what): void { global $pass, $fail; if ($c) { $pass++; echo "ok   $what\n"; } else { $fail++; echo "FAIL $what\n"; } }
// cards: [key, name, url, line, listings, planned]
$P = [
  'c:abq1' => ['name' => 'Albuquerque', 'lat' => 35.0844, 'lon' => -106.6504],
  'c:abq2' => ['name' => 'Albuquerque', 'lat' => 35.1100, 'lon' => -106.6100],
  'c:abq3' => ['name' => 'Albuquerque Northeast Heights', 'lat' => 35.0850, 'lon' => -106.6510],   // within 2 km of abq1
  'c:rr'   => ['name' => 'Rio Rancho', 'lat' => 35.2328, 'lon' => -106.6630],
  'c:sl'   => ['name' => 'The Show Low District', 'lat' => 34.2542, 'lon' => -110.0298],
  'c:sl2'  => ['name' => 'Show Low District', 'lat' => 34.3000, 'lon' => -110.1000],               // same name without "The"
  'c:pt'   => ['name' => 'Pinetop-Lakeside', 'lat' => 34.1425, 'lon' => -109.9604],
  'c:far'  => ['name' => 'Farmington', 'lat' => 36.7281, 'lon' => -108.2187],
  'c:nopt' => ['name' => 'Nowhere', 'lat' => null, 'lon' => null],
];
$place = fn (string $k) => $P[$k] ?? null;
$card = fn (string $k, int $n) => [$k, $P[$k]['name'], '', '', $n, false];
$groups = [
  [$card('c:abq1', 900), $card('c:abq2', 800), $card('c:abq3', 700), $card('c:rr', 300)],   // a busy district
  [$card('c:nopt', 999), $card('c:sl', 120), $card('c:sl2', 110), $card('c:pt', 90)],
  [$card('c:far', 60)],
];
$names = array_map(fn ($c) => $c['name'], Guides::stripPick($groups, $place));
ok(count($names) <= 5, 'at most 5');
ok(array_slice($names, 0, 3) === ['Albuquerque', 'The Show Low District', 'Farmington'], 'one per district first: ' . implode(', ', array_slice($names, 0, 3)));
ok(count(array_keys($names, 'Albuquerque')) === 1, 'Albuquerque once');
ok(!in_array('Albuquerque Northeast Heights', $names, true), 'a place within 2 km of one shown is skipped');
ok(!in_array('Show Low District', $names, true), 'the same name without "The" is skipped');
ok(!in_array('Nowhere', $names, true), 'a place with no point is skipped');
ok(in_array('Rio Rancho', $names, true) && in_array('Pinetop-Lakeside', $names, true), 'the rest filled by listings: ' . implode(', ', $names));
ok(Guides::stripPick([], $place) === [], 'nothing in, nothing out');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
