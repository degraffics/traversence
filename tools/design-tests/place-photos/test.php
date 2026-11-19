<?php
// T: card photos and the Parks strip, on a seeded MySQL (cp_test); Commons stood in by app/lib/stock/StockWikimedia.php
declare(strict_types=1);
$pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=cp_test;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function tv_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
require __DIR__ . '/../../app/lib/PlacePhotos.php'; require_once __DIR__ . '/../../app/lib/Automation.php';
$ok = 0; $bad = 0; $t = function ($name, $got, $want) use (&$ok, &$bad) { if ($got === $want) $ok++; else { $bad++; echo "FAIL $name\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n"; } };
Automation::$off = 'W9 is paused'; $t('paused: nothing looked for', PlacePhotos::find($pdo, 10), [0, 0]); Automation::$off = null;
[$looked, $found] = PlacePhotos::find($pdo, 10);
$rows = []; foreach ($pdo->query('SELECT * FROM place_photos') as $r) $rows[$r['owner_kind'] . ':' . $r['owner_ref']] = $r;
$t('visitor centre: its own photo (name read as "Visitor Center")', basename((string) ($rows['listing:10']['image_url'] ?? '')), 'Painted_Desert_Visitor_Center.jpg');
$t('credit cleaned', $rows['listing:10']['image_credit'] ?? '', 'Tester A');
$t('licence kept', $rows['listing:10']['image_license'] ?? '', 'CC BY-SA 4.0');
$t('KU (Kansas): another place\'s photo never taken', $rows['listing:11']['image_url'] ?? 'x', '');
$t('own hero photo: not looked up', isset($rows['listing:12']), false);
$t('not a public place: not looked up', isset($rows['listing:13']), false);
$t('kept private: never looked up', [$rows['listing:14']['image_url'] ?? 'x', $rows['listing:14']['source'] ?? ''], ['', 'private']);
$t('park: too small and non-free skipped, the named one found', basename((string) ($rows['rec:2803']['image_url'] ?? '')), 'Petrified_Forest_National_Park_Blue_Mesa.jpg');
$t('nothing fits: tried, empty, retried after 30 days', [$rows['rec:1']['image_url'] ?? 'x', $rows['rec:1']['tried_at'] !== null], ['', true]);
$t('counts: four looked at (the private one never), two found', [$looked, $found], [4, 2]);
$t('second run looks again at nothing new', PlacePhotos::find($pdo, 10), [0, 0]);
// the discovery strip's own functions, as written
$src = file_get_contents(__DIR__ . '/index.php');
foreach (['dv_min_miles', 'dv_parks', 'dv_card_photos', 'dv_park_cards'] as $fn) { $a = strpos($src, "function $fn("); $i = strpos($src, '{', $a); $d = 0; for ($j = $i; $j < strlen($src); $j++) { if ($src[$j] === '{') $d++; elseif ($src[$j] === '}' && --$d === 0) break; } eval(substr($src, $a, $j - $a + 1)); }
$stJohns = [[34.5056, -109.3609], [34.25, -109.95]];   // the place's towns
$cards = dv_park_cards($pdo, dv_parks($pdo, null, '', $stJohns), '', 'hub:1', $stJohns);
$names = array_map(fn ($c) => $c['name'], $cards);
$t('near the place only (no Kansas, no Wyoming)', $names, ['Petrified Forest National Park', 'Painted Desert Visitor Center', 'Painted Desert Inn']);
$img = array_map(fn ($c) => basename((string) $c['image']), $cards);
$t('each its own photo, found before stock', $img, ['Petrified_Forest_National_Park_Blue_Mesa.jpg', 'Painted_Desert_Visitor_Center.jpg', 'inn-own.jpg']);
$t('distinct photos', count(array_unique($img)), 3);
$t('credited on the card', in_array('Photo: Tester A, Wikimedia Commons', $cards[1]['stats'], true), true);
$t('miles on the card', (bool) preg_grep('/ mi$/', $cards[1]['stats']), true);
$far = dv_park_cards($pdo, dv_parks($pdo, null, '', [[44.0, -100.0]]), '', '', [[44.0, -100.0]]);   // nowhere near any park: the nearest few, each with its miles
$t('none close: the nearest park or two, never empty', count($far) > 0 && $far[0]['name'] === 'Yellowstone National Park', true);
$none = dv_park_cards($pdo, dv_parks($pdo, null, ''), '', '', []);   // no place: as before (every unit, by state and name)
$t('no place: unchanged, stock where nothing is found', in_array('/stock/same-pixabay.jpg', array_map(fn ($c) => $c['image'], $none), true), true);
echo "$ok passed, $bad failed\n";
