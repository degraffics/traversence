<?php
// The follow-up (2026-11-19, Lyman Lake): a listing someone opens with no photo yet is looked up first on the next run.
// Same tree and database as test.php (README), plus page_cache. Run: php website/discovery/want.php
declare(strict_types=1);
$pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=cp_test;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require __DIR__ . '/../../app/lib/PlacePhotos.php'; require_once __DIR__ . '/../../app/lib/Automation.php'; require_once __DIR__ . '/../../app/lib/PageCache.php';
$ok = 0; $bad = 0; $t = function ($name, $got, $want) use (&$ok, &$bad) { if ($got === $want) $ok++; else { $bad++; echo "FAIL $name\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n"; } };
$pdo->exec('CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)');
$pdo->exec('DELETE FROM page_cache'); $pdo->exec('DELETE FROM place_photos');
$pdo->exec("DELETE FROM entities WHERE id = 90"); $pdo->exec("INSERT INTO entities (id,name,category_id,latitude,longitude) VALUES (90,'Painted Desert Visitor Center',1,35.0656,-109.7826)");
$t('opened with no photo: nothing to show yet', PlacePhotos::slides($pdo, 'listing', '90', 'West'), []);
$t('… and it is wanted', array_map('intval', (array) PageCache::get($pdo, 'pp:want')), [90]);
PlacePhotos::slides($pdo, 'listing', '90'); $t('wanted once, not twice', count((array) PageCache::get($pdo, 'pp:want')), 1);
PlacePhotos::slides($pdo, 'rec', 'abc'); $t('only listings are wanted', count((array) PageCache::get($pdo, 'pp:want')), 1);
[$looked] = PlacePhotos::find($pdo, 1);   // one place this run: the wanted one, ahead of lower ids and national parks
$t('the next run looks it up first', (int) $pdo->query("SELECT COUNT(*) FROM place_photos WHERE owner_kind = 'listing' AND owner_ref = '90'")->fetchColumn(), 1);
$t('… and only it (the pace holds)', $looked, 1);
$t('reached: it leaves the list', (array) PageCache::get($pdo, 'pp:want'), []);
$t('its photo shows now', count(PlacePhotos::slides($pdo, 'listing', '90', 'West')), 1);
$pdo->exec("DELETE FROM entities WHERE id = 90");
echo "$ok passed, $bad failed\n";
