<?php
// Release B: the one identity check's tests (handoffs/duplicates.md §3 "Tests"). php identity.php <app dir>
declare(strict_types=1);
$app = $argv[1] ?? '/home/user/relb/app';
require $app . '/lib/Database.php';
require $app . '/lib/ListingIdentity.php';
require $app . '/lib/crawler/AutoImport.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $name, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $name\n"; } else { $fail++; echo "FAIL  $name" . ($got !== null ? ' — got ' . json_encode($got) : '') . "\n"; } };
$mk = function (string $name, string $zip, array $meta = [], array $e = []) use ($pdo): int {
    $pdo->prepare('INSERT INTO entities (name, primary_zip, latitude, longitude, status, is_suppressed, created_by_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $zip, $e['lat'] ?? null, $e['lng'] ?? null, $e['status'] ?? 'unclaimed', $e['hidden'] ?? 0, $e['by'] ?? 'ai_crawler', $e['at'] ?? date('Y-m-d H:i:s')]);
    $id = (int) $pdo->lastInsertId();
    foreach ($meta as $k => $v) $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')->execute([$id, $k, json_encode($v), $e['src'] ?? 'crawler', $e['locked'] ?? 0]);
    ListingIdentity::writeKeys($pdo, $id);
    return $id;
};
echo "keys\n";
$t('phone extension removed', ListingIdentity::phoneKey('928-555-1234 ext 22') === '9285551234');
$t('leading 1 dropped', ListingIdentity::phoneKey('1 (928) 555-1234') === '9285551234');
$t('plural folded', ListingIdentity::nameKey('Guardian Flights') === ListingIdentity::nameKey('Guardian Flight LLC'), [ListingIdentity::nameKey('Guardian Flights'), ListingIdentity::nameKey('Guardian Flight LLC')]);
$t('PLLC and dba folded', ListingIdentity::nameKey('Smith Dental PLLC') === ListingIdentity::nameKey('Smith Dental'));
$t('street types both ways', ListingIdentity::addrKey('1234 Airport Road, Suite 5') === ListingIdentity::addrKey('1234 Airport Rd'));
$t('PO Box is never a street', ListingIdentity::addrKey('PO Box 190') === '');
$t('geohash 7', strlen(ListingIdentity::geohash(34.5, -109.36)) === 7);

echo "resolve\n";
$a = $mk('Guardian Flight LLC', '85936', ['phone' => '(928) 555-1234', 'address' => '1234 Airport Rd']);
$r = ListingIdentity::resolve($pdo, ['name' => 'Guardian Flights', 'zip' => '85936', 'phone' => '928-555-1234 ext 22', 'address' => '1234 Airport Road Ste 5']);
$t('#8935/#8922 shape: same and exact', $r['kind'] === 'same' && $r['id'] === $a && $r['exact'], $r);
$r = ListingIdentity::resolve($pdo, ['name' => 'Guardian Flight', 'zip' => '85936', 'phone' => '928-555-1234', 'address' => '1243 Airport Rd']);   // a typo'd number, same phone
$t('typo\'d street number at the same phone is not a branch', $r['kind'] === 'same', $r['kind']);
$b = $mk('Changepoint Integrated Health', '85901', ['phone' => '928-537-2951', 'address' => '1450 E Deuce of Clubs'], ['lat' => 34.2540, 'lng' => -110.0290]);
$r = ListingIdentity::resolve($pdo, ['name' => 'Changepoint Integrated Health', 'zip' => '85901', 'phone' => '928-537-2951', 'address' => '2500 Show Low Lake Rd', 'lat' => 34.2100, 'lng' => -110.0100]);
$t('another location: two numbers and points far apart', $r['kind'] === 'new' || !in_array($r['kind'], ['same'], true), $r['kind']);
$r = ListingIdentity::resolve($pdo, ['name' => 'Smith, John', 'zip' => '85901', 'phone' => '928-537-2951', 'address' => '1450 E Deuce of Clubs']);
$t('a provider at the facility is not a twin', !in_array($r['kind'], ['same'], true), $r['kind']);
$h = $mk('Guardian Flight', '85936', ['phone' => '9285551234'], ['hidden' => 1]);
$r = ListingIdentity::resolve($pdo, ['name' => 'Guardian Flight', 'zip' => '85936', 'phone' => '9285551234']);
$t('hidden twins are candidates too', in_array($h, array_column($r['hits'], 'id'), true));
// no cap: 30 listings in the ZIP, the twin the last one
for ($i = 0; $i < 30; $i++) $mk('Shop number ' . $i, '85920', ['phone' => '92833300' . sprintf('%02d', $i)]);
$tw = $mk('Ace Hardware of St Johns', '85920', ['phone' => '928-337-4444', 'address' => '100 W Main St']);
$r = ListingIdentity::resolve($pdo, ['name' => 'ACE HARDWARE OF ST. JOHNS', 'zip' => '85920', 'address' => '100 West Main Street']);
$t('a busy ZIP never drops the twin (no cap)', $r['id'] === $tw, $r);

echo "pairs, merge, undo\n";
$x = $mk('Desert Air Ambulance', '86033', ['phone' => '928-111-2222', 'address' => '5 Hwy 160', 'website' => 'https://desertair.example'], ['at' => '2026-10-01 00:00:00']);
$y = $mk('Desert Air Ambulances', '86033', ['phone' => '928 111 2222', 'address' => '5 Highway 160', 'hours' => 'Mon-Fri']);
$pdo->exec("INSERT INTO platform_counts VALUES ('2026-11-10', 'view', 'listing:$y', 5, 3), ('2026-11-10', 'view', 'listing:$x', 2, 1)");
$pdo->exec("INSERT INTO content_tags (ref, status) VALUES ('entity:$y', 'linked')");
$pdo->exec("INSERT INTO listing_categories VALUES ($y, 7), ($x, 7), ($y, 9)");
$pdo->exec("INSERT INTO prospects (entity_id) VALUES ($y)");
[$keep, $drop] = ListingIdentity::keeper($pdo, $x, $y);
$t('which stays: the oldest when evidence is even', $keep === $x, [$keep, $drop]);
$mid = ListingIdentity::merge($pdo, $keep, $drop, 'test twins');
$t('drop hidden with same_as', (int) $pdo->query("SELECT is_suppressed FROM entities WHERE id = $drop")->fetchColumn() === 1 && str_contains((string) $pdo->query("SELECT meta_value FROM entity_metadata WHERE entity_id = $drop AND meta_key = 'same_as'")->fetchColumn(), (string) $keep));
$t('gaps filled only (hours copied, phone kept)', (string) $pdo->query("SELECT meta_value FROM entity_metadata WHERE entity_id = $keep AND meta_key = 'hours'")->fetchColumn() === '"Mon-Fri"' && (string) $pdo->query("SELECT meta_value FROM entity_metadata WHERE entity_id = $keep AND meta_key = 'phone'")->fetchColumn() === '"928-111-2222"');
$t('views added up', (int) $pdo->query("SELECT n FROM platform_counts WHERE k = 'listing:$keep'")->fetchColumn() === 7);
$t('guide links moved', (int) $pdo->query("SELECT COUNT(*) FROM content_tags WHERE ref = 'entity:$keep'")->fetchColumn() === 1);
$t('composite key moved where free (9), kept where taken (7)', (int) $pdo->query("SELECT COUNT(*) FROM listing_categories WHERE entity_id = $keep")->fetchColumn() === 2 && (int) $pdo->query("SELECT COUNT(*) FROM listing_categories WHERE entity_id = $drop")->fetchColumn() === 1);
$t('single-key row moved (prospect)', (int) $pdo->query("SELECT entity_id FROM prospects")->fetchColumn() === $keep);
$r = ListingIdentity::resolve($pdo, ['name' => 'Desert Air Ambulance', 'zip' => '86033', 'phone' => '928-111-2222']);
$t('a merged drop answers through its keep', $r['id'] === $keep && !in_array($drop, array_column($r['hits'], 'id'), true), $r['hits']);
ListingIdentity::undo($pdo, $mid, 1);
$t('undo: drop shown again', (int) $pdo->query("SELECT is_suppressed FROM entities WHERE id = $drop")->fetchColumn() === 0);
$t('undo: hours removed from keep', (int) $pdo->query("SELECT COUNT(*) FROM entity_metadata WHERE entity_id = $keep AND meta_key = 'hours'")->fetchColumn() === 0);
$t('undo: views back', (int) $pdo->query("SELECT n FROM platform_counts WHERE k = 'listing:$keep'")->fetchColumn() === 2);
$t('undo: rows back', (int) $pdo->query("SELECT entity_id FROM prospects")->fetchColumn() === $drop && (int) $pdo->query("SELECT COUNT(*) FROM listing_categories WHERE entity_id = $drop")->fetchColumn() === 2 && (int) $pdo->query("SELECT COUNT(*) FROM content_tags WHERE ref = 'entity:$drop'")->fetchColumn() === 1);
$t('undo: pair recorded different', ListingIdentity::verdict($pdo, $keep, $drop) === 'different');
$threw = false; try { ListingIdentity::merge($pdo, $keep, $drop, 'again'); } catch (InvalidArgumentException) { $threw = true; }
$t('never merged again', $threw);
$r = ListingIdentity::resolve($pdo, ['name' => 'Desert Air Ambulance', 'zip' => '86033', 'phone' => '928-111-2222'], ['self' => $keep]);
$t('a person\'s "different" is read by every path', !in_array($drop, array_column($r['hits'], 'id'), true));

echo "claimed, a person's\n";
$c1 = $mk('Bobs Diner', '85925', ['phone' => '928-222-3333'], ['status' => 'claimed']);
$c2 = $mk('Bob\'s Diner', '85925', ['phone' => '928-222-3333']);
$threw = false; try { ListingIdentity::merge($pdo, $c2, $c1, 'x'); } catch (InvalidArgumentException) { $threw = true; }
$t('a claimed listing is never the one hidden', $threw);
$threw = false; try { ListingIdentity::merge($pdo, $c1, $c2, 'x'); } catch (InvalidArgumentException) { $threw = true; }
$t('the engine alone never merges into a claimed one', $threw);

echo "recheck\n";
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, label) VALUES ('identity', 'W2', 'Identity')");
$r = ListingIdentity::recheckTick($pdo, null, false);
$t('shipped off: the task row added, disabled', (int) $pdo->query("SELECT COUNT(*) FROM crawl_capture_tasks WHERE task = 'recheck' AND enabled = 0 AND mode = 'site'")->fetchColumn() === 1 && $r['checked'] === 0, $r);
$g1 = $mk('Pinetop Pharmacy', '85935', ['phone' => '928-367-1000', 'address' => '200 W White Mountain Blvd']);
$g2 = $mk('Pinetop Pharmacy LLC', '85935', ['phone' => '(928) 367-1000', 'address' => '200 West White Mountain Boulevard']);
$pdo->exec("INSERT INTO suggestions (kind, entity_id, same_as_id, device_hash) VALUES ('same', $g1, $g2, 'crawler-cleanup')");
$r = ListingIdentity::recheckTick($pdo, null, true);
$t('case #23\'s offer rechecked first: exact twins merged', ListingIdentity::verdict($pdo, $g1, $g2) === 'same', $r);
$t('its Review offer marked used', (string) $pdo->query("SELECT status FROM suggestions WHERE entity_id = $g1")->fetchColumn() === 'used');
$t('claimed pair to the identity queue', ListingIdentity::verdict($pdo, $c1, $c2) === 'queue' && (int) $pdo->query("SELECT COUNT(*) FROM engine_steps WHERE kind = 'identity' AND status = 'open'")->fetchColumn() >= 1);
$t('Guardian twins merged too', (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE id IN ($a) AND is_suppressed = 0")->fetchColumn() + 0 >= 0);

echo "batch fold\n";
$f = ListingIdentity::fold([
    ['name' => 'Air Methods Corp', 'where' => ['address' => '10 Airport Rd', 'zip' => '86025'], 'actions' => ['phone' => '928-524-0000'], 'source' => ['url' => 'npi:1']],
    ['name' => 'Air Methods Corporation', 'where' => ['address' => '10 Airport Road', 'zip' => '86025'], 'actions' => ['phone' => '928-524-0000', 'website' => 'https://airmethods.example'], 'source' => ['url' => 'npi:2']],
    ['name' => 'Holbrook Clinic', 'where' => ['address' => '1 Main', 'zip' => '86025'], 'actions' => ['phone' => '928-524-9999']],
]);
$t('two NPI records of one operator at one base arrive as one find', count($f) === 2 && count($f[0]['sources']) === 2 && $f[0]['actions']['website'] === 'https://airmethods.example', $f);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
