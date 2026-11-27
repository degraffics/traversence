<?php
// Events E2 (decisions/0134 §5's minimum) on a real MySQL or MariaDB: what the public sees. publicGet: published and
// cancelled only, never a draft or deleted one, nor one whose host is hidden; forEntity and forPlace: not yet over,
// soonest first, a town's (cluster), an area's (geo hub) and a region's (hub); ticketOf: only while the host's item is
// published; Engage takes 'event' (views, likes, comments) and Links resolves place:ev:<id> (the Link button).
// Run: php events-public.php <root>/app <2026-12-05_events.sql>   (it resets lv_events2)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); $mig = $argv[2] ?? '';
if (!is_file($app . '/lib/Events.php') || !is_file($mig)) { fwrite(STDERR, "usage: php events-public.php <root>/app <migration>\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_events2'); $pdo->exec('CREATE DATABASE lv_events2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_events2');
foreach (DbUpdates::statements((string) file_get_contents($mig)) as $s) if (trim($s) !== '') $pdo->exec($s);
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(120), status VARCHAR(20), is_suppressed TINYINT DEFAULT 0, micro_cluster_id INT NULL, primary_zip VARCHAR(10), longitude DOUBLE NULL)");
$pdo->exec("CREATE TABLE micro_clusters (id INT PRIMARY KEY, geo_hub_id INT)"); $pdo->exec("CREATE TABLE geo_hubs (id INT PRIMARY KEY, hub_id INT, name VARCHAR(60))");
$pdo->exec("CREATE TABLE market_items (id INT PRIMARY KEY, entity_id INT, title VARCHAR(160), kind VARCHAR(20), status VARCHAR(20))");
$pdo->exec("INSERT INTO micro_clusters VALUES (1, 10), (2, 10), (3, 20)"); $pdo->exec("INSERT INTO geo_hubs VALUES (10, 100, 'Round Valley'), (20, 200, 'Elsewhere')");
$pdo->exec("INSERT INTO entities VALUES (1,'Hometown Theater','verified',0,1,'85938',-109.28),(2,'Eagar Hall','verified',0,2,'85925',-109.29),(3,'Far Barn','verified',0,3,'86025',-110.1),(4,'Hidden Bar','verified',1,1,'85938',-109.28)");
$pdo->exec("INSERT INTO market_items VALUES (501,1,'Community night','ticket','published'),(502,1,'Old night','ticket','unpublished')");
$ins = $pdo->prepare("INSERT INTO events (entity_id,title,kind,starts_at,ends_at,all_day,zone,status,ticket_item_id,created_at,updated_at) VALUES (?,?,?,?,?,0,'America/Phoenix',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$d = fn (int $days, string $t = '19:00') => (new DateTime('now', new DateTimeZone('America/Phoenix')))->modify("$days days")->format('Y-m-d') . " $t:00";
$rows = [[1,'Film night','arts',$d(10),$d(10,'21:30'),'published',501],[1,'Draft night','arts',$d(12),null,'draft',null],[1,'Cancelled night','arts',$d(5),null,'cancelled',null],[1,'Gone night','arts',$d(6),null,'deleted',null],
         [1,'Last week','arts',$d(-7),$d(-7,'21:00'),'published',null],[2,'Eagar dance','music',$d(3),null,'published',502],[3,'Far rodeo','sports',$d(4),null,'published',null],[4,'Hidden show','music',$d(4),null,'published',null]];
$id = []; foreach ($rows as $r) { $ins->execute($r); $id[$r[1]] = (int) $pdo->lastInsertId(); }
require_once $app . '/lib/Events.php'; require_once $app . '/lib/Engage.php'; require_once $app . '/lib/Links.php';
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$titles = fn (array $rs) => array_column($rs, 'title');
$t('publicGet: published shows, with its host', (Events::publicGet($pdo, $id['Film night'])['host_name'] ?? '') === 'Hometown Theater');
$t('publicGet: cancelled shows (as cancelled)', (Events::publicGet($pdo, $id['Cancelled night'])['status'] ?? '') === 'cancelled');
$t('publicGet: a draft never', Events::publicGet($pdo, $id['Draft night']) === null);
$t('publicGet: a deleted one never', Events::publicGet($pdo, $id['Gone night']) === null);
$t('publicGet: none from a hidden listing', Events::publicGet($pdo, $id['Hidden show']) === null);
$t('the listing: soonest first, not over, no drafts', $titles(Events::forEntity($pdo, 1)) === ['Cancelled night', 'Film night'], $titles(Events::forEntity($pdo, 1)));
$t('Events::over: last week is over, next week isn\'t', Events::over(Events::get($pdo, $id['Last week'])) && !Events::over(Events::get($pdo, $id['Film night'])));
$t('a town\'s guide: its own listings\' events', $titles(Events::forPlace($pdo, ['key' => 'cluster:1'])) === ['Cancelled night', 'Film night'], $titles(Events::forPlace($pdo, ['key' => 'cluster:1'])));
$t('an area\'s guide: its towns\' events', $titles(Events::forPlace($pdo, ['key' => 'geo_hub:10'])) === ['Eagar dance', 'Cancelled night', 'Film night'], $titles(Events::forPlace($pdo, ['key' => 'geo_hub:10'])));
$t('a region\'s guide: its areas\' towns', count(Events::forPlace($pdo, ['key' => 'hub:100'])) === 3 && $titles(Events::forPlace($pdo, ['key' => 'hub:200'])) === ['Far rodeo']);
$t('a city or neighborhood guide: none yet', Events::forPlace($pdo, ['key' => 'town:5']) === []);
$t('Get tickets: the host\'s published item', (Events::ticketOf($pdo, Events::get($pdo, $id['Film night']))['url'] ?? '') === '/market/item.php?id=501');
$t('no Get tickets once the item is unpublished', Events::ticketOf($pdo, Events::get($pdo, $id['Eagar dance'])) === null);
$t('the cards open the event\'s page and mark a cancelled one', str_contains(Events::html(Events::forEntity($pdo, 1)), 'href="/event/?id=' . $id['Film night'] . '"') && str_contains(Events::html(Events::forEntity($pdo, 1)), 'Cancelled'));
$t('Engage: event is a kind, counted as event:<id>', in_array('event', Engage::KINDS, true) && Engage::COUNT_KEY['event'] === 'event:' && Engage::target('event', (string) $id['Film night']) === ['event', (string) $id['Film night']]);
$t('Engage: a non-number ref refused', (function () { try { Engage::target('event', 'x1'); return false; } catch (InvalidArgumentException) { return true; } })());
$t('Links: place:ev:<id> resolves to its page (the Link button)', Links::resolve($pdo, 'place', 'ev:' . $id['Film night']) === ['Film night', '/event/?id=' . $id['Film night']]);
$t('Links: a draft can\'t be linked', (function () use ($pdo, $id) { try { Links::resolve($pdo, 'place', 'ev:' . $id['Draft night']); return false; } catch (InvalidArgumentException) { return true; } })());
$pdo->exec('DROP DATABASE lv_events2');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
