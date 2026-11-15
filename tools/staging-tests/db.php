<?php
// tools/staging-tests/db.php — decisions/0165 against a real MySQL or MariaDB: two realms, each a live database and its
// staging copy, reached by one login (as each live login reaches its own staging copy on the server). It runs catch-up
// (staging's triggers, live's outbox, the push), renumbering at a clash, the preview, Publish (the write-ahead journal,
// batches, the still-matches check, new rows taking live's numbers, references rewritten), a failure part way with the
// backward undo, and Roll back.
//   php tools/staging-tests/db.php <app folder> [--dsn mysql:host=localhost] [--user tv] [--pass tvpass]
// The app folder's .env (four folders up from app/lib) must point DB_DATABASE at lv_main with the same login.
// It drops and makes lv_main, sg_main, lv_users and sg_users.
declare(strict_types=1);

$app = rtrim($argv[1] ?? '', '/');
$opt = getopt('', ['dsn::', 'user::', 'pass::']);
$dsn = $opt['dsn'] ?? 'mysql:host=localhost'; $user = $opt['user'] ?? 'tv'; $pass = $opt['pass'] ?? 'tvpass';
putenv('TV_TEST=1');
require_once $app . '/lib/Database.php';
require_once $app . '/lib/StagingSync.php';
require_once $app . '/lib/StagingPublish.php';
require_once $app . '/lib/StagingSite.php';

$fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; echo ($c ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$c) $fails++; }
$c = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
foreach (['lv_main', 'sg_main', 'lv_users', 'sg_users'] as $db) { $c->exec("DROP DATABASE IF EXISTS $db"); $c->exec("CREATE DATABASE $db CHARACTER SET utf8mb4"); }
$c2 = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$q = fn (string $sql) => $c->query($sql)->fetchAll();
$one = fn (string $sql) => $c->query($sql)->fetchColumn();

// ---- live, as it is ------------------------------------------------------------------------------------------------
$c->exec("CREATE TABLE lv_main.users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190), display_name VARCHAR(100), is_test TINYINT NOT NULL DEFAULT 0)");
$c->exec("CREATE TABLE lv_main.entities (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), slug VARCHAR(100) UNIQUE, phone VARCHAR(40), about TEXT, owner_user_id INT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$c->exec("CREATE TABLE lv_main.entity_metadata (entity_id INT UNSIGNED NOT NULL, mkey VARCHAR(40) NOT NULL, mval TEXT, PRIMARY KEY (entity_id, mkey))");
$c->exec("CREATE TABLE lv_main.site_pages (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(100), layout TEXT)");
$c->exec("CREATE TABLE lv_main.orders (id INT AUTO_INCREMENT PRIMARY KEY, total DECIMAL(10,2), bill_name VARCHAR(100))");
$c->exec("CREATE TABLE lv_main.journey_views (id INT AUTO_INCREMENT PRIMARY KEY, n INT)");
$c->exec("CREATE TABLE lv_users.user_places (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, entity_id INT UNSIGNED, note TEXT)");
$c->exec("INSERT INTO lv_main.users (email, display_name, is_test) VALUES ('ann@example.com','Ann',0), ('check+1@traversence.com','Test One',1), ('staff@example.com','Sam',0)");
$c->exec("INSERT INTO lv_main.entities (name, slug, phone, about, owner_user_id) VALUES ('Cafe One','cafe-one','928-555-1000','A cafe',1), ('Inn Two','inn-two','928-555-2000','An inn',1), ('Shop Three','shop-three',NULL,'A shop',NULL)");
$c->exec("INSERT INTO lv_main.entity_metadata VALUES (1,'hours','7-3'), (2,'hours','all day')");
$c->exec("INSERT INTO lv_main.site_pages (title, layout) VALUES ('Home', '{\"blocks\":[{\"listing_id\":1}]}')");
$c->exec("INSERT INTO lv_main.orders (total, bill_name) VALUES (12.50, 'Ann')");
$c->exec("INSERT INTO lv_users.user_places (user_id, entity_id, note) VALUES (1, 2, 'saved listing:2')");
// staging: a copy, as a Start over leaves it
foreach (['main' => ['users', 'entities', 'entity_metadata', 'site_pages', 'orders', 'journey_views'], 'users' => ['user_places']] as $realm => $ts) foreach ($ts as $t) {
    $l = $realm === 'main' ? 'lv_main' : 'lv_users'; $s = $realm === 'main' ? 'sg_main' : 'sg_users';
    $c->exec("CREATE TABLE $s.$t LIKE $l.$t"); $c->exec("INSERT INTO $s.$t SELECT * FROM $l.$t");
}
StagingSync::usePairs(['main' => [$c, 'lv_main', 'sg_main'], 'users' => [$c2, 'lv_users', 'sg_users']], [[2], [], 'salt']);
foreach (['main' => ['users', 'entities', 'entity_metadata', 'site_pages', 'orders'], 'users' => ['user_places']] as $realm => $ts) foreach ($ts as $t) {
    StagingSync::stagingTriggers($realm, $t); StagingSync::outboxTriggers($realm, $t); StagingSync::baseTable($realm, $t);
}
ok(StagingSync::canTrigger('main', 'live') && StagingSync::canTrigger('main', 'staging'), 'the TRIGGER right is there (probe)');
ok((int) $one("SELECT COUNT(*) FROM sg_main.stg_base WHERE tbl = 'entities'") === 3, 'the base: one row per row');
ok((int) $one("SELECT COUNT(*) FROM lv_main.stg_outbox") === 0, 'the outbox starts empty');

// ---- 1. a live change on a row staging didn't touch comes in, stripped ------------------------------------------------
$c->exec("UPDATE lv_main.users SET email = 'ann.new@example.com' WHERE id = 1");
$c->exec("UPDATE lv_main.entities SET about = 'A cafe, now with pie' WHERE id = 1");
ok((int) $one("SELECT COUNT(*) FROM lv_main.stg_outbox") === 2, 'live\'s writes are noted in its outbox (same database)');
$o = StagingSync::push();
ok(($o['applied'] ?? 0) === 2, 'the push applies them (' . json_encode($o) . ')');
ok($one("SELECT about FROM sg_main.entities WHERE id = 1") === 'A cafe, now with pie', '…the listing came in');
ok(str_ends_with((string) $one("SELECT email FROM sg_main.users WHERE id = 1"), '@staging.invalid'), '…the person\'s email came in stripped');
ok((int) $one("SELECT COUNT(*) FROM sg_main.stg_changes") === 0, '…and catch-up\'s own writes aren\'t staging\'s changes');
ok((int) $one("SELECT COUNT(*) FROM lv_main.stg_outbox") === 0, 'the outbox is cleared');

// ---- 2. staging works: changes, a new row, a test account's row ------------------------------------------------------
$c->exec("SET @stg_actor = 'u3s1', @stg_test = 0");
$c->exec("UPDATE sg_main.entities SET name = 'Cafe One (fixed)' WHERE id = 1");      // staging: name
$c->exec("UPDATE sg_main.entities SET phone = '928-555-2001' WHERE id = 2");          // staging: phone, live will change about
$c->exec("UPDATE sg_main.entities SET about = 'staging says' WHERE id = 3");          // both: about → a conflict
$c->exec("INSERT INTO sg_main.entities (id, name, slug, about, owner_user_id) VALUES (4, 'New Place', 'new-place', 'made on staging', 3)");
$c->exec("INSERT INTO sg_main.entity_metadata VALUES (4, 'hours', '9-5')");
$c->exec("INSERT INTO sg_main.site_pages (title, layout) VALUES ('Guide page', '{\"blocks\":[{\"listing_id\":4}]}')");
$c2->exec("SET @stg_actor = 'u3s1'");
$c2->exec("INSERT INTO sg_users.user_places (user_id, entity_id, note) VALUES (3, 4, 'see listing:4')");
$c->exec("SET @stg_actor = 'u2', @stg_test = 0");                                     // the test account
$c->exec("INSERT INTO sg_main.entities (name, slug, about) VALUES ('Test Thing', 'test-thing', 'by a test account')");
$c->exec("UPDATE sg_main.orders SET total = 1.00 WHERE id = 1");                          // money: never carried
$c->exec("SET @stg_actor = NULL, @stg_test = NULL");
ok((int) $one("SELECT COUNT(*) FROM sg_main.stg_changes") === 8, 'staging\'s triggers record its changes (' . $one("SELECT COUNT(*) FROM sg_main.stg_changes") . ')');
ok($one("SELECT actor FROM sg_main.stg_changes WHERE tbl = 'entities' AND k = '4'") === 'u3s1', '…with who made them');
ok(json_decode((string) $one("SELECT before_row FROM sg_main.stg_changes WHERE tbl = 'entities' AND k = '1'"), true)['name'] === 'Cafe One', '…and the row as it was');

// ---- 3. live keeps working: a clash of numbers, a different field, the same field ---------------------------------------
$c->exec("UPDATE lv_main.entities SET about = 'An inn by the river' WHERE id = 2");     // a different field than staging's
$c->exec("UPDATE lv_main.entities SET about = 'live says' WHERE id = 3");               // the same field: a conflict
$c->exec("INSERT INTO lv_main.entities (name, slug, about) VALUES ('Live Four', 'live-four', 'made on live')");   // takes id 4: staging's 4 must give way
$o = StagingSync::push();
ok(($o['renumbered'] ?? 0) === 1, 'staging gives way at a clash (' . json_encode($o) . ')');
$moved = (int) $one("SELECT id FROM sg_main.entities WHERE slug = 'new-place'");
ok($moved > 5, 'staging\'s new row moved to the next free number (' . $moved . ')');
ok($one("SELECT name FROM sg_main.entities WHERE id = 4") === 'Live Four', '…and took live\'s row');
ok((int) $one("SELECT COUNT(*) FROM sg_main.entity_metadata WHERE entity_id = $moved") === 1, '…its metadata moved with it (a column reference)');
ok(str_contains((string) $one("SELECT layout FROM sg_main.site_pages WHERE title = 'Guide page'"), '"listing_id":' . $moved), '…the page layout too (JSON)');
ok($one("SELECT note FROM sg_users.user_places WHERE user_id = 3") === 'see listing:' . $moved && (int) $one("SELECT entity_id FROM sg_users.user_places WHERE user_id = 3") === $moved, '…and in the other database (column and kind:id)');
ok((int) $one("SELECT COUNT(*) FROM sg_main.stg_renumber") === 1, 'the renumber is logged');
ok(count(StagingSync::conflicts()) === 1 && StagingSync::conflicts()[0]['k'] === '3', 'the same field two ways is on the conflicts list at once');
ok($one("SELECT about FROM sg_main.entities WHERE id = 2") === 'An inn', 'a row both changed is left untouched by catch-up');

// ---- 4. the preview ------------------------------------------------------------------------------------------------
$pdo = Database::connection();
$pv = StagingPublish::preview($pdo);
ok($pv['conflicts'] === 1 && $pv['blocking'] !== [], 'the preview counts the conflict and Publish waits for it');
StagingSync::settle('main', 'entities', '3', 'staging', 1);
$pv = StagingPublish::preview($pdo);
$plan = []; foreach (StagingPublish::planRows($pv['id'], 500) as $r) $plan[$r['tbl'] . ':' . $r['k']] = $r['do_what'];
ok(($plan['entities:1'] ?? '') === 'update' && ($plan['entities:2'] ?? '') === 'update' && ($plan['entities:3'] ?? '') === 'update', 'staging\'s changes: updates (the conflict settled as staging\'s)');
ok(($plan['entities:' . $moved] ?? '') === 'insert', 'staging\'s new listing: an insert');
$testK = (string) $one("SELECT id FROM sg_main.entities WHERE slug = 'test-thing'");
ok(($plan['entities:' . $testK] ?? '') === 'test', 'the test account\'s row is test data, never carried');
ok(!isset($plan['orders:1']) && ($pv['not_carried']['Money (everything on staging is sandbox)'] ?? 0) === 1, 'money is never carried');
ok($pv['blocking'] === [], 'nothing blocks (' . implode(' ', $pv['blocking']) . ')');

// ---- 5. a failure part way: undone, last write first --------------------------------------------------------------------
$c->exec("CREATE TRIGGER lv_main.boom BEFORE INSERT ON lv_main.entity_metadata FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'disk full (test)'");
$before = $q("SELECT id, name, phone, about FROM lv_main.entities ORDER BY id");
$run = StagingPublish::start($pdo, 1);
for ($i = 0; $i < 20 && in_array($run['state'], ['running', 'undoing'], true); $i++) $run = StagingPublish::carry($pdo, 5);
ok($run['state'] === 'undone', 'a failure while writing undoes the publish by itself (' . $run['state'] . ': ' . ($run['why'] ?? '') . ')');
ok($q("SELECT id, name, phone, about FROM lv_main.entities ORDER BY id") === $before, '…live\'s listings are as they were');
ok((int) $one("SELECT COUNT(*) FROM lv_main.entities WHERE slug = 'new-place'") === 0, '…the added row was removed');
ok((int) $one("SELECT COUNT(*) FROM sg_main.pub_journal WHERE pub = " . $c->quote($run['id']) . " AND done = 1 AND undone = 0") === 0, '…every written entry is marked undone');
$c->exec("DROP TRIGGER lv_main.boom");

// ---- 6. Publish, with a late change on live -------------------------------------------------------------------------------
$pv = StagingPublish::preview($pdo);
$c->exec("UPDATE lv_main.entities SET phone = '928-555-1111' WHERE id = 1");   // after the preview: a late conflict for row 1
$run = StagingPublish::start($pdo, 1);
for ($i = 0; $i < 30 && in_array($run['state'], ['running', 'undoing'], true); $i++) $run = StagingPublish::carry($pdo, 5);
ok($run['state'] === 'done', 'Publish finished (' . ($run['message'] ?? $run['why'] ?? '') . ')');
ok(count($run['late']) === 1, 'a row changed on live since the preview is a late conflict, left as live has it');
ok($one("SELECT name FROM lv_main.entities WHERE id = 1") === 'Cafe One', '…row 1 kept live\'s');
$r2 = $c->query("SELECT phone, about FROM lv_main.entities WHERE id = 2")->fetch();
ok($r2['phone'] === '928-555-2001' && $r2['about'] === 'An inn by the river', 'both sides\' fields kept on row 2');
ok($one("SELECT about FROM lv_main.entities WHERE id = 3") === 'staging says', 'the settled conflict: staging\'s');
$newId = (int) $one("SELECT id FROM lv_main.entities WHERE slug = 'new-place'");
ok($newId > 4 && $one("SELECT mval FROM lv_main.entity_metadata WHERE entity_id = $newId") === '9-5', 'the new listing took a live number, and its metadata points to it');
ok(str_contains((string) $one("SELECT layout FROM lv_main.site_pages WHERE title = 'Guide page'"), '"listing_id":' . $newId), '…the page\'s JSON points to it');
ok((int) $one("SELECT entity_id FROM lv_users.user_places WHERE user_id = 3") === $newId && $one("SELECT note FROM lv_users.user_places WHERE user_id = 3") === 'see listing:' . $newId, '…and the other database\'s row (column and text)');
ok((int) $one("SELECT COUNT(*) FROM lv_main.entities WHERE slug = 'test-thing'") === 0, 'test data stayed on staging');
ok((string) $one("SELECT total FROM lv_main.orders WHERE id = 1") === '12.50', 'money stayed as live had it');
ok($one("SELECT email FROM lv_main.users WHERE id = 1") === 'ann.new@example.com', 'people\'s real details untouched by staging\'s stand-ins');
ok((int) $one("SELECT COUNT(*) FROM lv_main.stg_outbox WHERE NOT (tbl = 'entities' AND k = '1')") === 0, 'Publish\'s own writes aren\'t pushed back to staging');
ok((int) $one("SELECT id FROM sg_main.entities WHERE slug = 'new-place'") === $newId, 'staging in step: its row took the live number');
ok((int) $one("SELECT COUNT(*) FROM sg_main.stg_changes WHERE tbl = 'entities' AND k IN ('2', '3', '$newId')") === 0, '…and the base was taken again for what went live');

// ---- 7. Roll back the whole publish -------------------------------------------------------------------------------------
$c->exec("UPDATE lv_main.entities SET about = 'changed after the publish' WHERE id = 3");   // live changed one since: left, listed
$run = StagingPublish::rollBack($pdo, 1);
for ($i = 0; $i < 20 && $run['state'] === 'undoing'; $i++) $run = StagingPublish::carry($pdo, 5);
ok($run['state'] === 'rolled_back', 'Roll back finished (' . ($run['message'] ?? '') . ')');
ok((int) $one("SELECT COUNT(*) FROM lv_main.entities WHERE slug = 'new-place'") === 0 && (int) $one("SELECT COUNT(*) FROM lv_main.entity_metadata WHERE mval = '9-5'") === 0, 'added rows removed');
ok($one("SELECT phone FROM lv_main.entities WHERE id = 2") === '928-555-2000', 'changed rows put back');
ok($one("SELECT about FROM lv_main.entities WHERE id = 3") === 'changed after the publish' && count($run['left'] ?? []) === 1, 'a row live changed since is left as live has it, and listed');

echo $fails ? "\n$fails of $n failed\n" : "\nAll $n passed\n";
exit($fails ? 1 : 0);
