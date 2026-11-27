<?php
// A participant's discount code is published by its owner, like a post (decisions/0134's note #1605): attaching it writes
// its public terms into the event's participant record from the owner's own request; the event page reads only that
// record; a change saved in Discount codes (BizCodes::save) or its last use (BizCodes::redeem) updates or withdraws the
// copy, with History; a copy before its first day or past its last doesn't show. On a real MySQL or MariaDB (lv_pub, user
// tv/tv on 127.0.0.1): main's tables and a realm built like BusinessData's (owner_id, tv_tenant(), the b_ view), the
// portal's Scope set to business 8 on a connection of its own. Database is stood in (main's connection; t()).
// Run: php publish-code.php <root>   (the build: <root>/app/lib/{Events,BizCodes}.php, <root>/website/event/index.php)
declare(strict_types=1);
$root = rtrim($argv[1] ?? '', '/'); $mirror = $argv[2] ?? '/home/user/traverse-3way-sync/sync-app/lib';
if (!is_file($root . '/app/lib/Events.php') || !is_file($root . '/app/lib/BizCodes.php') || !is_file($mirror . '/Scope.php')) { fwrite(STDERR, "usage: php publish-code.php <build root> [<mirror app/lib>]\n"); exit(2); }
$tmp = sys_get_temp_dir() . '/pubcode-' . getmypid(); @mkdir($tmp, 0777, true);
foreach (['Events.php', 'BizCodes.php'] as $f) copy($root . '/app/lib/' . $f, $tmp . '/' . $f);
copy($mirror . '/Scope.php', $tmp . '/Scope.php');
file_put_contents($tmp . '/Database.php', '<?php final class Database { public static ?PDO $p = null; public static function connection(): PDO { return self::$p; } public static function open(): ?PDO { return self::$p; } public static function t(string $x): string { return "`" . $x . "`"; } }');
require $tmp . '/Database.php'; require $tmp . '/Scope.php'; require $tmp . '/BizCodes.php'; require $tmp . '/Events.php';
$o = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$root0 = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'tv', 'tv', $o);
$root0->exec('DROP DATABASE IF EXISTS lv_pub'); $root0->exec('CREATE DATABASE lv_pub'); $root0->exec('USE lv_pub');
$sql = (string) file_get_contents($root . '/app/migrations/2026-12-09_event_participants.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $q) $root0->exec($q);
$root0->exec("CREATE TABLE events (id INT PRIMARY KEY, entity_id INT, title VARCHAR(120), status VARCHAR(12) DEFAULT 'published', starts_at DATETIME, ends_at DATETIME NULL, all_day TINYINT DEFAULT 0, zone VARCHAR(64) DEFAULT 'America/Phoenix')");
$root0->exec("CREATE TABLE event_history (id INT AUTO_INCREMENT PRIMARY KEY, event_id INT, at DATETIME, tz VARCHAR(64), user_id INT, field VARCHAR(40), old_value TEXT)");
$root0->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(120), status VARCHAR(20) DEFAULT 'verified', is_suppressed TINYINT DEFAULT 0, category_id INT NULL, latitude DOUBLE NULL, longitude DOUBLE NULL, primary_zip VARCHAR(10) DEFAULT '')");
$root0->exec("CREATE TABLE biz_codes (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, code VARCHAR(40) NOT NULL, kind VARCHAR(8) NOT NULL DEFAULT 'percent', value INT UNSIGNED NOT NULL DEFAULT 0,
  min_order_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, starts DATE DEFAULT NULL, ends DATE DEFAULT NULL, max_uses INT UNSIGNED NOT NULL DEFAULT 0, once_each TINYINT(1) NOT NULL DEFAULT 1, uses INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1, note VARCHAR(200) NOT NULL DEFAULT '', created_by INT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_bc (owner_id, code))");
$root0->exec("CREATE TABLE biz_code_uses (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL DEFAULT 0, code_id INT, quote_id INT, email VARCHAR(190), off_cents INT, created_at DATETIME)");
$root0->exec('CREATE FUNCTION tv_tenant() RETURNS INT UNSIGNED DETERMINISTIC NO SQL RETURN @tv_tenant');
foreach (['biz_codes', 'biz_code_uses'] as $t) $root0->exec("CREATE VIEW b_$t AS SELECT * FROM $t WHERE owner_id = tv_tenant()");
// a new row through the view takes the business's owner_id (BusinessData's triggers do this on live)
$root0->exec("CREATE TRIGGER bc_owner BEFORE INSERT ON biz_codes FOR EACH ROW SET NEW.owner_id = IF(NEW.owner_id = 0, IFNULL(@tv_tenant, 0), NEW.owner_id)");
$root0->exec("CREATE TRIGGER bcu_owner BEFORE INSERT ON biz_code_uses FOR EACH ROW SET NEW.owner_id = IF(NEW.owner_id = 0, IFNULL(@tv_tenant, 0), NEW.owner_id)");
$root0->exec("INSERT INTO entities (id, name) VALUES (7, 'Hometown Theater'), (8, 'Diner on Main'), (9, 'Other Grill')");
$root0->exec("INSERT INTO events (id, entity_id, title, starts_at) VALUES (5, 7, 'Community night', '2099-12-18 19:00:00'), (6, 7, 'Matinee', '2099-12-19 14:00:00')");
$root0->exec("INSERT INTO biz_codes (owner_id, code, kind, value, min_order_cents, ends, max_uses, active, created_at) VALUES
  (8, 'SHOW10', 'percent', 10, 2000, '2099-12-31', 0, 1, NOW()), (8, 'SOON', 'percent', 5, 0, NULL, 0, 1, NOW()), (8, 'OFF', 'percent', 5, 0, NULL, 0, 0, NOW()),
  (8, 'LAST1', 'amount', 500, 0, NULL, 1, 1, NOW()), (9, 'SHOW10', 'percent', 50, 0, NULL, 0, 1, NOW())");
$root0->exec("UPDATE biz_codes SET starts = '2099-01-01' WHERE code = 'SOON'");
$main = new PDO('mysql:host=127.0.0.1;dbname=lv_pub;charset=utf8mb4', 'tv', 'tv', $o); Database::$p = $main;
$realm = new PDO('mysql:host=127.0.0.1;dbname=lv_pub;charset=utf8mb4', 'tv', 'tv', $o); $realm->exec('SET @tv_tenant = 8');
Scope::enterBusiness($realm, 8);   // business 8's portal request: its codes on its own connection
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$row = fn (int $ev, int $e) => $main->query("SELECT * FROM event_participants WHERE event_id = $ev AND entity_id = $e")->fetch() ?: [];
$hist = fn () => array_column($main->query('SELECT field FROM event_history ORDER BY id')->fetchAll(), 'field');

// 1. attaching publishes the terms, read from the owner's own connection
$ch = Events::codeChoices($main);
$t('its choices are its own codes that are on and not ended (not 9\'s, not OFF)', isset($ch['SHOW10'], $ch['SOON'], $ch['LAST1']) && !isset($ch['OFF']) && $ch['SHOW10']['min'] === 2000, array_keys($ch));
$pub = function (int $ev, int $e, string $code) use ($main, $ch) {   // what answer() writes when the owner takes part with a code
    $c = $ch[$code];
    $main->prepare("INSERT INTO event_participants (event_id, entity_id, status, offer_text, offer_code, offer_code_words, offer_code_starts, offer_code_ends, offer_code_min, invited_at, changed_at) VALUES (?, ?, 'accepted', 'Dinner first', ?, ?, ?, ?, ?, NOW(), NOW())")
        ->execute([$ev, $e, $code, $c['words'], $c['starts'], $c['ends'], $c['min']]);
};
$pub(5, 8, 'SHOW10'); $pub(6, 8, 'SHOW10');
$main->exec("INSERT INTO event_participants (event_id, entity_id, status, offer_code, offer_code_ends, invited_at, changed_at) VALUES (6, 9, 'accepted', 'SHOW10', NULL, NOW(), NOW())");   // business 9's own SHOW10
$r = $row(5, 8);
$t('the published copy holds the code, its days and smallest order', $r['offer_code'] === 'SHOW10' && $r['offer_code_ends'] === '2099-12-31' && (int) $r['offer_code_min'] === 2000, $r);
$pc = Events::publishedCode($r);
$t('the page shows its public terms only', $pc === ['code' => 'SHOW10', 'ends' => '2099-12-31', 'min_cents' => 2000], $pc);
$t('in words: the code, the smallest order, the last day', Events::codeWords($pc) === 'code SHOW10 on orders of $20.00 or more until Dec 31');

// 2. its days
$t('a copy before its first day doesn\'t show', Events::publishedCode(['offer_code' => 'SOON', 'offer_code_starts' => '2099-01-01', 'offer_code_ends' => null]) === null);
$t('a copy past its last day doesn\'t show', Events::publishedCode(['offer_code' => 'X', 'offer_code_starts' => null, 'offer_code_ends' => '2000-01-01']) === null);
$t('ends today still shows (as checkout counts it)', Events::publishedCode(['offer_code' => 'X', 'offer_code_starts' => date('Y-m-d'), 'offer_code_ends' => date('Y-m-d')]) !== null);
$t('no code: nothing', Events::publishedCode(['offer_code' => '']) === null);

// 3. a change saved in Discount codes updates the copies, with History
$id = (int) $realm->query("SELECT id FROM b_biz_codes WHERE code = 'SHOW10'")->fetchColumn();
$base = ['code' => 'SHOW10', 'kind' => 'percent', 'value' => '10', 'min_order' => '25.00', 'starts' => '', 'ends' => '2099-11-30', 'max_uses' => '0', 'once_each' => '1', 'active' => '1', 'note' => ''];
BizCodes::save($main, $id, $base, 2);
$t('its smallest order and last day changed: both copies follow', $row(5, 8)['offer_code_ends'] === '2099-11-30' && (int) $row(6, 8)['offer_code_min'] === 2500, [$row(5, 8), $row(6, 8)]);
$t('History says it changed, and what it was', in_array('part:code_changed', $hist(), true));
$t('business 9\'s own SHOW10 on another event is untouched', $row(6, 9)['offer_code'] === 'SHOW10' && $row(6, 9)['offer_code_ends'] === null);
BizCodes::save($main, $id, ['code' => 'SHOW15'] + $base, 2);
$t('renamed: its copies take the new name', $row(5, 8)['offer_code'] === 'SHOW15' && $row(6, 8)['offer_code'] === 'SHOW15');
BizCodes::save($main, $id, ['code' => 'SHOW15', 'active' => '0'] + $base, 2);
$t('switched off: withdrawn from both events, the owner\'s line kept', $row(5, 8)['offer_code'] === '' && $row(6, 8)['offer_code'] === '' && $row(5, 8)['offer_text'] === 'Dinner first');
$t('History says it came off', in_array('part:code_off', $hist(), true));
$t('in History\'s words', str_contains(Events::historyWords(['field' => 'part:code_off', 'old_value' => 'Diner on Main|SHOW15']), 'came off'));
$t('business 9\'s untouched still', $row(6, 9)['offer_code'] === 'SHOW10');

// 4. its last use takes it off
$main->exec('DELETE FROM event_participants WHERE entity_id = 8'); $pub(5, 8, 'LAST1');
$lid = (int) $realm->query("SELECT id FROM b_biz_codes WHERE code = 'LAST1'")->fetchColumn();
BizCodes::redeem($main, ['id' => 1, 'code_id' => $lid, 'cust_email' => 'a@example.test'], 500);
$t('its last use (1 of 1) withdraws it', $row(5, 8)['offer_code'] === '', $row(5, 8));

// 5. the owner's next visit checks again (a change made around the library)
$main->exec('DELETE FROM event_participants WHERE entity_id = 8'); $pub(5, 8, 'SOON');
$realm->exec("UPDATE b_biz_codes SET active = 0 WHERE code = 'SOON'");
$gone = Events::recheckCodes($main, 8);
$t('Events checks again on the owner\'s visit, and says what came off', $row(5, 8)['offer_code'] === '' && count($gone) === 1 && str_contains($gone[0], 'SOON'), $gone);

// 6. a failure in the copy never stops the owner's save
$main->exec('RENAME TABLE event_participants TO ep_away');
$ok = true; try { BizCodes::save($main, $id, ['code' => 'SHOW15', 'active' => '1'] + $base, 2); } catch (Throwable $x) { $ok = false; }
$t('the code is saved even when its copies can\'t be reached (logged; the next visit checks again)', $ok && (int) $realm->query("SELECT active FROM b_biz_codes WHERE id = $id")->fetchColumn() === 1);
$main->exec('RENAME TABLE ep_away TO event_participants');

// 7. nothing reads the business's data when the page is drawn
$ev = (string) file_get_contents($root . '/app/lib/Events.php');
preg_match('/function publicParticipants.*?\n    }\n/s', $ev, $m1); preg_match('/function publishedCode.*?\n    }\n/s', $ev, $m2);
$page = is_file($root . '/website/event/index.php') ? (string) file_get_contents($root . '/website/event/index.php') : '';
$t('the event page\'s reads name no realm, view or code library', ($m1[0] ?? '') !== '' && !preg_match('/BizCodes|BusinessData|b_biz_codes|realm|tv_tenant|Scope::/', preg_replace('~//[^\n]*|/\*.*?\*/~s', '', ($m1[0] ?? '') . ($m2[0] ?? '') . $page)));   // comments aside
$t('and no publicCode or tenant switch is left anywhere in the build', !preg_match('/publicCode|tv_publiccode/', $ev . (string) file_get_contents($root . '/app/lib/BizCodes.php')));

$root0->exec('DROP DATABASE lv_pub');
array_map('unlink', glob($tmp . '/*'));
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
