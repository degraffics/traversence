<?php
// Release 3b-1: the Explore hero's order (decisions/0058 §30, 0138, 0143 §4): the page's own photos (fallback_slides,
// landmarks' Commons photos) come before any stock photo; stock only when there's no real photo. And the stock byline.
declare(strict_types=1);
$root = $argv[1];
require $root . '/app/lib/Database.php';
foreach (['Response', 'Auth', 'Store'] as $c) if (is_file("$root/app/lib/$c.php")) require_once "$root/app/lib/$c.php";
require_once $root . '/app/ui/app-shell.php';
require_once $root . '/app/ui/explore-hero.php';
$pdo = Database::connection();
$pdo->exec('DROP TABLE IF EXISTS stock_images, stock_uses');
$pdo->exec("CREATE TABLE stock_images (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(10) DEFAULT 'live', title VARCHAR(200), path VARCHAR(300) DEFAULT '', remote_url VARCHAR(300) DEFAULT '', card_path VARCHAR(300) DEFAULT '', licence VARCHAR(60) DEFAULT 'CC0', licence_url VARCHAR(300) DEFAULT '', author VARCHAR(120) DEFAULT '', source VARCHAR(30) DEFAULT 'pixabay', source_id VARCHAR(60) DEFAULT '', source_url VARCHAR(300) DEFAULT '', subjects VARCHAR(300) DEFAULT '', place_key VARCHAR(40) DEFAULT '', place_scope VARCHAR(20) DEFAULT 'subject')");
$pdo->exec("CREATE TABLE stock_uses (id INT AUTO_INCREMENT PRIMARY KEY, stock_id INT, slot VARCHAR(20), owner_ref VARCHAR(80))");
$pdo->exec("INSERT INTO stock_images (title, remote_url, author, subjects) VALUES ('Canyon', 'https://example.test/stock.jpg', 'jplenio', 'landscape national park')");
$ok = 0; $bad = 0;
function t(string $n, bool $c) { global $ok, $bad; echo ($c ? 'ok   ' : 'FAIL ') . $n . "\n"; $c ? $ok++ : $bad++; }
$real = [['image' => 'https://example.test/landmark.jpg', 'caption' => 'Window Rock · Photo: X, Wikimedia Commons']];
$stock = ['subject' => 'landscape', 'place' => '', 'owner' => 'explore:test', 'representative' => true, 'fallback' => 'landscape'];
$h = tv_explore_hero($pdo, ['name' => 'Regions', 'stock' => $stock, 'fallback_slides' => $real]);
t('the page\'s own photo is shown', strpos($h, 'landmark.jpg') !== false);
t('no stock photo when a real one exists', strpos($h, 'stock.jpg') === false && strpos($h, 'xh-stock') === false);
$h2 = tv_explore_hero($pdo, ['name' => 'Regions', 'stock' => $stock, 'fallback_slides' => []]);
t('stock only when there is no real photo', strpos($h2, 'stock.jpg') !== false);
t('its byline on the photo: "Photo: jplenio on Pixabay"', strpos($h2, 'Photo: jplenio on Pixabay') !== false);
t('an i opens its details (Stock photo, licence, source)', strpos($h2, 'tv-stk-i') !== false && strpos($h2, 'tv-stk-d') !== false);
t('"Stock photo" still said, in the tooltip', (bool) preg_match('/title="Stock photo \(representative\)/', $h2));
echo "$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
