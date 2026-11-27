<?php
// C.8 (P10): OpenNow on the design harness's MariaDB: whose hours are known (owner, staff, OpenStreetMap; never the crawler), each listing's zone (Navajo daylight time, Hopi inside it Arizona time), now there, the index keeping known hours only, find's open-first order and count. Run: php open-now.php <root> (seed: listings 101-106 and two nation shapes, see handoffs/c8-open-now/README.md).
// C.8 (P10): OpenNow's rules on the harness's MariaDB, and search's index and find over them.
$root = $argv[1]; require $root . '/app/bootstrap.php';
require_once $root . '/app/lib/OpenNow.php'; require_once $root . '/app/lib/SearchIndex.php'; require_once $root . '/app/lib/Hours.php';
$pdo = Database::connection(); $ok = 0; $bad = 0;
$t = function ($n, $c, $x = '') use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? 'PASS ' : 'FAIL ') . $n . ($x !== '' ? "  [$x]" : '') . "\n"; };
$k = OpenNow::known($pdo, [101, 102, 103, 104, 105, 106]);
$t('owner hours known', isset($k[101])); $t('staff hours known (gap a)', isset($k[104])); $t('OpenStreetMap hours known', isset($k[103]));
$t('crawler hours not known', !isset($k[102])); $t('no hours, not known', !isset($k[106]));
$t('AZ off nation land: Phoenix', OpenNow::zone($pdo, 'AZ', 34.25, -110.03) === 'America/Phoenix');
$t('Navajo land in AZ: Denver (gap c)', OpenNow::zone($pdo, 'AZ', 36.7, -110.25) === 'America/Denver');
$t('Hopi land inside Navajo: Phoenix (gap c)', OpenNow::zone($pdo, 'AZ', 35.9, -110.5) === 'America/Phoenix');
$t('NM: Denver', OpenNow::zone($pdo, 'NM', 35.5, -108.7) === 'America/Denver');
$t('Nations::at still the first', Nations::at($pdo, 35.9, -110.5) !== null && count(Nations::allAt($pdo, 35.9, -110.5)) === 2);
// July 1 2026 18:00 UTC: Phoenix 11:00, Denver 12:00 (daylight)
$at = gmmktime(18, 0, 0, 7, 1, 2026);
$t('now in Phoenix', OpenNow::now('America/Phoenix', $at) === [3, 660], json_encode(OpenNow::now('America/Phoenix', $at)));
$t('now in Denver', OpenNow::now('America/Denver', $at) === [3, 720]);
// the index keeps only known hours
SearchIndex::indexListings($pdo, [101, 102, 103, 104, 105, 106]);
$h = $pdo->query("SELECT item_id, hours FROM search_index WHERE kind = 'listing' AND item_id IN ('101','102','103','104','105','106')")->fetchAll(PDO::FETCH_KEY_PAIR);
$t('index: owner hours kept', !empty($h['101'])); $t('index: crawler hours dropped', empty($h['102'])); $t('index: OSM hours kept', !empty($h['103'])); $t('index: staff hours kept', !empty($h['104']));
// find with open now
$r = SearchIndex::find($pdo, ['text' => '', 'cat' => ['id' => 1, 'label' => 'Pizza'], 'place' => null, 'area' => ['lat' => 34.25, 'lon' => -110.03, 'radius' => 10], 'when' => ['now' => [0, 0]], 'limit' => 20]);
$ids = array_map(fn ($x) => $x['id'], array_filter($r['items'], fn ($x) => $x['kind'] === 'listing'));
$open = array_map(fn ($x) => $x['id'], array_filter($r['items'], fn ($x) => $x['kind'] === 'listing' && $x['open'] === true));
$t('find: known closed left out', !in_array('105', $ids, true), implode(',', $ids));
$t('find: unknown kept (gap b)', in_array('102', $ids, true) && in_array('106', $ids, true));
$t('find: the open ones first', array_slice($ids, 0, count($open)) === array_values($open) && count($open) === 3, 'open ' . implode(',', $open));
$t('find: crawler-hours place not open', !in_array('102', $open, true));
$t('find: count of known hours', ($r['hours_known'] ?? null) === ['known' => 3, 'of' => count($ids)], json_encode($r['hours_known'] ?? null));
$u = array_values(array_filter($r['items'], fn ($x) => $x['id'] === '102'))[0] ?? [];
$t('find: unknown marked hours_known false', ($u['hours_known'] ?? null) === false);
$r2 = SearchIndex::find($pdo, ['text' => '', 'cat' => ['id' => 1, 'label' => 'Pizza'], 'place' => null, 'area' => ['lat' => 34.25, 'lon' => -110.03, 'radius' => 10], 'when' => [], 'limit' => 20]);
$t('find without when: no count', !isset($r2['hours_known']));
echo "$ok passed, $bad failed\n";
