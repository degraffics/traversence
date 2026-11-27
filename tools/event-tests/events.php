<?php
// Events, release E1 (decisions/0134's minimum before Jan 1, the note of 2026-11-27, option (a)) on a real MySQL or
// MariaDB: who may post (a claimed listing; a role with Catalog & content to edit; not an unclaimed one, not a viewer);
// the form's rules (a name, a kind from 0134 §3, a start; the end after it; no links, no "pay at the door" or box office
// notes; only the host's own published ticket item; only the host's own photo); times kept as the venue's, with its zone
// named; a draft → publish → unpublish / cancel → put back on; delete kept 30 days with Put back; nothing in the past
// published; held words; every change keeps its old value in the History.
// Run: php events.php <root>/app <migration .sql>   (it resets lv_events)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); $mig = $argv[2] ?? '';
if (!is_file($app . '/lib/Events.php') || !is_file($mig)) { fwrite(STDERR, "usage: php events.php <root>/app <2026-12-05_events.sql>\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_events'); $pdo->exec('CREATE DATABASE lv_events CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_events');
foreach (DbUpdates::statements((string) file_get_contents($mig)) as $s) if (trim($s) !== '') $pdo->exec($s);
foreach (DbUpdates::statements((string) file_get_contents($mig)) as $s) if (trim($s) !== '') $pdo->exec($s);   // safe to run twice
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(120), status VARCHAR(20), is_suppressed TINYINT DEFAULT 0, owner_user_id INT NULL, primary_zip VARCHAR(10), latitude DOUBLE NULL, longitude DOUBLE NULL, plan_tier VARCHAR(20) DEFAULT 'free')");
$pdo->exec("CREATE TABLE zip_coordinates (zip VARCHAR(10), city VARCHAR(80), state VARCHAR(4), latitude DOUBLE, longitude DOUBLE)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(120), display_name VARCHAR(120))");
$pdo->exec("CREATE TABLE listing_memberships (listing_id INT, user_id INT, role VARCHAR(20), status VARCHAR(10))");
$pdo->exec("CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20))");
$pdo->exec("CREATE TABLE role_perms (role VARCHAR(20), perm VARCHAR(20), level VARCHAR(10))");
$pdo->exec("CREATE TABLE market_items (id INT PRIMARY KEY, entity_id INT, title VARCHAR(160), kind VARCHAR(20), status VARCHAR(20), published_at DATETIME)");
$pdo->exec("INSERT INTO zip_coordinates VALUES ('85938','Springerville','AZ',34.13,-109.28),('87301','Gallup','NM',35.52,-108.74)");
$pdo->exec("INSERT INTO entities (id,name,status,owner_user_id,primary_zip,longitude) VALUES (10,'Hometown Theater','verified',1,'85938',-109.28),(11,'Unclaimed Cafe','unclaimed',NULL,'85938',-109.28),(12,'Gallup Hall','verified',5,'87301',-108.74)");
$pdo->exec("INSERT INTO users VALUES (1,'owner@example.test','Owner Ann'),(2,'creator@example.test','Creator Cy'),(3,'viewer@example.test','Viewer Vi'),(4,'stranger@example.test','Stranger'),(5,'nm@example.test','NM Owner')");
$pdo->exec("INSERT INTO listing_memberships VALUES (10,2,'creator','active'),(10,3,'viewer','active'),(11,1,'manager','active')");
$pdo->exec("INSERT INTO role_perms VALUES ('creator','catalog','edit'),('manager','catalog','edit'),('viewer','catalog','view')");
$pdo->exec("INSERT INTO market_items VALUES (501,10,'Community night: 120 seats','ticket','published',NOW()),(502,10,'Popcorn tin','physical','published',NOW()),(503,12,'Another hall\'s ticket','ticket','published',NOW()),(504,10,'Old class ticket','class','published',NOW()),(505,10,'Draft ticket','ticket','draft',NOW())");
require_once $app . '/lib/Events.php'; require_once $app . '/lib/Gallery.php';
Gallery::ensure($pdo);
$gal = function (string $kind, string $ref) use ($pdo) { $c = $pdo->query("SHOW COLUMNS FROM gallery_items")->fetchAll(PDO::FETCH_COLUMN); $row = ['owner_kind' => $kind, 'owner_ref' => $ref];
    foreach ($c as $col) if (!isset($row[$col]) && $col !== 'id') { $t = $pdo->query("SELECT DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_items' AND COLUMN_NAME = " . $pdo->quote($col))->fetch(PDO::FETCH_ASSOC);
        if ($t['IS_NULLABLE'] === 'NO' && $t['COLUMN_DEFAULT'] === null) $row[$col] = in_array($t['DATA_TYPE'], ['int', 'tinyint', 'bigint', 'smallint', 'decimal', 'double'], true) ? 0 : (in_array($t['DATA_TYPE'], ['datetime', 'timestamp', 'date'], true) ? gmdate('Y-m-d H:i:s') : ''); }
    $pdo->prepare('INSERT INTO gallery_items (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row)); return (int) $pdo->lastInsertId(); };
$own = $gal('businesses', '10'); $other = $gal('businesses', '12');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$err = function (callable $f) { try { $f(); return ''; } catch (InvalidArgumentException $x) { return $x->getMessage(); } };
$soon = (new DateTime('+20 days'))->format('Y-m-d'); $base = ['title' => 'Community night at the movies', 'kind' => 'arts', 'starts_at' => $soon . 'T19:00', 'ends_at' => $soon . 'T21:30', 'age' => 'all', 'about' => 'A night out for the town.', 'showtimes' => 'Nightly at 4:00 and 7:15.'];

// who may post
$t('ready once its migration ran (run twice, no error)', Events::ready($pdo));
$t('the owner of a claimed listing may post', Events::canPost($pdo, 1, 10)[0]);
$t('a Creator (Catalog & content: edit) may post', Events::canPost($pdo, 2, 10)[0]);
$t('a Viewer may not, with why', !Events::canPost($pdo, 3, 10)[0] && str_contains(Events::canPost($pdo, 3, 10)[1], 'role'));
$t('someone not on the team may not', !Events::canPost($pdo, 4, 10)[0]);
$t('an unclaimed listing can\'t post, and says Claim it', !Events::canPost($pdo, 1, 11)[0] && str_contains(Events::canPost($pdo, 1, 11)[1], 'Claim'));
// the form
$id = Events::save($pdo, 1, 10, $base + ['ticket_item_id' => 501, 'gallery_item_id' => $own], 0, 'America/Phoenix');
$e = Events::get($pdo, $id);
$t('saved as a draft with the venue\'s zone (Springerville: America/Phoenix)', $e['status'] === 'draft' && $e['zone'] === 'America/Phoenix' && $e['starts_at'] === $soon . ' 19:00:00');
$t('a New Mexico venue gets America/Denver', Events::get($pdo, Events::save($pdo, 5, 12, $base, 0))['zone'] === 'America/Denver');
$t('the time is shown with its zone named', (bool) preg_match('/7:00 PM to 9:30 PM MST$/', Events::when($e)), Events::when($e));
$t('the ticket link is the host\'s own item', (int) $e['ticket_item_id'] === 501 && (int) $e['gallery_item_id'] === $own);
$t('no name refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['title' => ''] + $base)), 'name'));
$t('a kind not in 0134 §3 refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['kind' => 'film'] + $base)), 'kind'));
$t('an end before the start refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['ends_at' => $soon . 'T18:00'] + $base)), 'ends before'));
$t('a link in the description refused (0134 §3)', str_contains($err(fn () => Events::save($pdo, 1, 10, ['about' => 'Tickets at https://seller.example/x'] + $base)), 'No links'));
$t('a "pay at the door" note refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['about' => 'Pay at the door, $5.'] + $base)), 'at the door'));
$t('a box office note in the showtimes refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['showtimes' => 'Tickets at the box office.'] + $base)), 'box office'));
$t('plain showtimes kept as information', Events::get($pdo, $id)['showtimes'] === 'Nightly at 4:00 and 7:15.');
$t('another business\'s ticket refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['ticket_item_id' => 503] + $base)), 'own published ticket'));
$t('a non-ticket item refused', $err(fn () => Events::save($pdo, 1, 10, ['ticket_item_id' => 502] + $base)) !== '');
$t('a draft ticket item refused', $err(fn () => Events::save($pdo, 1, 10, ['ticket_item_id' => 505] + $base)) !== '');
$t('a legacy "class" ticket item is offered', isset(Events::ticketItems($pdo, 10)[504]) && !isset(Events::ticketItems($pdo, 10)[502]));
$t('another business\'s photo refused', str_contains($err(fn () => Events::save($pdo, 1, 10, ['gallery_item_id' => $other] + $base)), 'own photos'));
$t('all day: dates only, shown as all day', str_contains(Events::when(Events::get($pdo, Events::save($pdo, 1, 10, ['all_day' => 1, 'starts_at' => $soon, 'ends_at' => ''] + $base))), 'all day'));
$t('a viewer\'s save refused', $err(fn () => Events::save($pdo, 3, 10, $base)) !== '');
$t('editing another business\'s event refused', str_contains($err(fn () => Events::save($pdo, 5, 12, $base, $id)), 'isn\'t this business'));
// edits keep the old value
Events::save($pdo, 2, 10, ['title' => 'Community night: the big show'] + $base + ['ticket_item_id' => 501, 'gallery_item_id' => $own], $id, 'America/Denver');
$h = Events::history($pdo, $id);
$t('an edit keeps the old name in the History, with who and their zone', $h[0]['field'] === 'title' && $h[0]['old_value'] === 'Community night at the movies' && $h[0]['who'] === 'Creator Cy' && $h[0]['tz'] === 'America/Denver', $h[0] ?? null);
$t('saving with nothing changed adds nothing', (function () use ($pdo, $id, $base) { $n = count(Events::history($pdo, $id)); Events::save($pdo, 1, 10, ['title' => 'Community night: the big show'] + $base + ['ticket_item_id' => 501, 'gallery_item_id' => $own = (int) Events::get($pdo, $id)['gallery_item_id']], $id); return count(Events::history($pdo, $id)) === $n; })());
// moves
$t('publish', str_contains(Events::act($pdo, 1, 10, $id, 'publish'), 'Published') && Events::get($pdo, $id)['status'] === 'published' && Events::get($pdo, $id)['published_at'] !== null);
$t('publish again: says so, nothing changes', str_contains(Events::act($pdo, 1, 10, $id, 'publish'), 'Already published'));
$t('a published event can\'t be deleted (cancel it first)', str_contains(Events::act($pdo, 1, 10, $id, 'delete'), 'Already'));
$t('cancel, then Put back on', Events::act($pdo, 1, 10, $id, 'cancel') !== '' && Events::get($pdo, $id)['status'] === 'cancelled' && Events::act($pdo, 1, 10, $id, 'restore') !== '' && Events::get($pdo, $id)['status'] === 'published');
$t('unpublish: back to a draft', Events::act($pdo, 1, 10, $id, 'unpublish') !== '' && Events::get($pdo, $id)['status'] === 'draft');
$t('delete a draft: kept, with Put back', Events::act($pdo, 1, 10, $id, 'delete') !== '' && Events::get($pdo, $id)['status'] === 'deleted' && count(array_filter(Events::forHost($pdo, 10), fn ($r) => (int) $r['id'] === $id)) === 1);
$t('a deleted event can\'t be edited until put back', str_contains($err(fn () => Events::save($pdo, 1, 10, $base, $id)), 'Put it back'));
$t('Put back: a draft again', Events::act($pdo, 1, 10, $id, 'undelete') !== '' && Events::get($pdo, $id)['status'] === 'draft' && Events::get($pdo, $id)['deleted_at'] === null);
$pdo->exec("UPDATE events SET status = 'deleted', deleted_at = UTC_TIMESTAMP() - INTERVAL 31 DAY WHERE id = $id");
$t('after 30 days a deleted event leaves the list', !array_filter(Events::forHost($pdo, 10), fn ($r) => (int) $r['id'] === $id));
$pdo->exec("UPDATE events SET status = 'draft', deleted_at = NULL WHERE id = $id");
$t('every move is in the History', count(array_filter(Events::history($pdo, $id), fn ($x) => $x['field'] === 'status')) >= 6);
$past = Events::save($pdo, 1, 10, ['starts_at' => (new DateTime('-10 days'))->format('Y-m-d') . 'T19:00', 'ends_at' => ''] + $base);
$t('an event in the past can\'t be published', str_contains($err(fn () => Events::act($pdo, 1, 10, $past, 'publish')), 'past'));
$t('a viewer can\'t move one', $err(fn () => Events::act($pdo, 3, 10, $id, 'publish')) !== '');
$t('another business can\'t move it', str_contains($err(fn () => Events::act($pdo, 5, 12, $id, 'publish')), 'isn\'t this business'));
$t('an unknown move refused', str_contains($err(fn () => Events::act($pdo, 1, 10, $id, 'boost')), 'Unknown'));
$t('the list: newest start first', (function () use ($pdo) { $l = Events::forHost($pdo, 10); $s = array_column(array_filter($l, fn ($r) => $r['status'] !== 'deleted'), 'starts_at'); $c = $s; rsort($c); return $s === $c; })());
$pdo->exec('DROP DATABASE lv_events');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
