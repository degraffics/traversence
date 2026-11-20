<?php
// tools/design-tests/send-to-maps/rules.php: Send to Maps follows Directions' rules (decisions/0174 §2, §4).
// php rules.php <root> (a copy of the site with app/ and website/), on a real MySQL or MariaDB with entities,
// entity_metadata and zip_coordinates. Its rows use ids 990001+ and are removed after.
declare(strict_types=1);
$root = $argv[1] ?? '';
require_once $root . '/app/bootstrap.php';
require_once $root . '/app/ui/maps-send.php';
require_once $root . '/app/lib/JourneyPlan.php';
$pass = 0; $fail = 0;
$ok = function (bool $c, string $m) use (&$pass, &$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; $c ? $pass++ : $fail++; };

// the last guard, in maps-send.php
$pt = fn (string $n, array $x = []) => $x + ['lat' => 34.1, 'lon' => -110.1, 'name' => $n];
$got = array_column(tv_maps_stops([
    $pt('Open cafe', ['address' => '12 Main St, Show Low AZ']),
    $pt('Shelter', ['private' => true]), $pt('Kept', ['private_location' => 1]), $pt('Told only', ['story' => true]),
    $pt('ZIP middle', ['approx' => 1]), $pt('Box', ['address' => 'PO Box 77, Show Low AZ']), $pt('No point', ['lat' => null]),
    $pt('Trailhead', ['label' => 'Trailhead']),
]), 2);
$ok($got === ['Open cafe', 'Trailhead'], 'Send to Maps leaves out private, story-only, ZIP-middle, mailbox and pointless stops (' . implode(', ', $got) . ')');
$ok(tv_maps_attrs([$pt('A'), $pt('Shelter', ['private' => true])]) === '', 'one stop left after the rules: no Send to Maps');
$a = tv_maps_attrs([$pt('A'), $pt('B', ['lat' => 34.2])], 'T');
$ok(str_contains($a, 'data-maps=') && str_contains($a, 'data-maps-title="T"') && str_contains($a, 'https://www.google.com/maps/dir/'), 'two stops: the link and its data');

// a guide's journey drops what Directions would (JourneyPlan::dropListings)
$pdo = Database::connection();
$pdo->exec('DELETE FROM entities WHERE id BETWEEN 990001 AND 990009');
$z = $pdo->query('SELECT zip, latitude, longitude FROM zip_coordinates LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$ins = $pdo->prepare('INSERT INTO entities (id, name, category_id, latitude, longitude, primary_zip) VALUES (?, ?, NULL, ?, ?, ?)');
$ins->execute([990001, 'Pine Cafe', 34.25, -110.03, $z['zip']]);
$ins->execute([990002, "Mountain Women's Shelter", 34.26, -110.04, $z['zip']]);
$ins->execute([990003, 'Box Diner', 34.27, -110.05, $z['zip']]);
$ins->execute([990004, 'Middle Grill', (float) $z['latitude'], (float) $z['longitude'], $z['zip']]);
$out = [];
foreach ([[990001, '1 Main St, Show Low AZ'], [990002, '9 Elm St, Show Low AZ'], [990003, 'P.O. Box 4, Show Low AZ'], [990004, '2 Oak St, Show Low AZ']] as [$id, $ad])
    $out['entity:' . $id] = ['id' => $id, 'name' => 'x', 'address' => $ad];
$out['landmark:1'] = ['name' => 'Painted Desert Inn'];
$m = new ReflectionMethod('JourneyPlan', 'dropListings'); $m->setAccessible(true);
$kept = array_keys($m->invoke(null, $pdo, $out));
$pdo->exec('DELETE FROM entities WHERE id BETWEEN 990001 AND 990009');
$ok(in_array('entity:990001', $kept, true) && in_array('landmark:1', $kept, true), 'a plain listing and a landmark stay');
$ok(!in_array('entity:990002', $kept, true), 'a shelter (location kept private) is never a stop');
$ok(!in_array('entity:990003', $kept, true), 'a mailbox address is never a stop');
$ok(!in_array('entity:990004', $kept, true), "a point at its ZIP's middle is still left out");

// a landmark's place to eat: the index's private flag is read
$src = file_get_contents($root . '/app/lib/Landmarks.php');
$ok((bool) preg_match("/why LIKE '%eat%' AND private_location = 0/", $src), "a landmark's place to eat is never a private one");
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
