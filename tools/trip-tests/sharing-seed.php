<?php
// tools/trip-tests/sharing-seed.php <root>: for sharing.cjs (harness only): user 1 a contributor and staff, a shelter and a closed
// business, and one trip ("Shared seed") holding an open listing, the shelter (added directly: it couldn't be added any
// more) and the closed one. Prints trip=<id>.
declare(strict_types=1);
require_once ($argv[1] ?? '') . '/app/bootstrap.php';
$pdo = Database::connection();
$pdo->exec('DELETE FROM personal_guides WHERE user_id = 1');
$pdo->exec('DELETE FROM admin_access WHERE user_id = 1'); $pdo->exec("INSERT INTO admin_access (user_id, hub_id, tier) VALUES (1, NULL, 'super_admin')");   // an Editor, for Admin → Journeys
$pdo->exec("DELETE FROM contributors WHERE user_id = '1'"); $pdo->exec("INSERT INTO contributors (user_id, status) VALUES ('1', 'active')");
$pdo->exec('DELETE FROM entity_metadata WHERE entity_id IN (990031, 990032)'); $pdo->exec('DELETE FROM entities WHERE id IN (990031, 990032)');
$pdo->exec("INSERT INTO entities (id, name, category_id, latitude, longitude, is_suppressed) VALUES (990031, 'Hope Women''s Shelter', NULL, 34.26, -110.04, 0), (990032, 'Gone Grill', NULL, 34.27, -110.05, 0)");
$pdo->exec("INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (990032, 'closed', '{\"note\":\"gone\"}')");
$pdo->exec("INSERT INTO trips (user_id, name, tz, days) VALUES (1, 'Shared seed', 'America/Phoenix', 1)");
$t = (int) $pdo->lastInsertId();
$ins = $pdo->prepare('INSERT INTO trip_stops (trip_id, day, position, kind, ref) VALUES (?, 1, ?, ?, ?)');
foreach ([['listing', '4'], ['listing', '990031'], ['listing', '990032'], ['listing', '5']] as $i => [$k, $r]) $ins->execute([$t, $i, $k, $r]);
echo "trip=$t\n";
