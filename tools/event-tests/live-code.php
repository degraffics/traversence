<?php
// Events::liveCode (P1/P2; the session manager, 2026-11-27: the CTO's Tier 4 on reading a business's walled data from a
// public page, 0110). Four things, each tested on a real MySQL or MariaDB built like the listings realm (BusinessData:
// biz_codes with owner_id, the tv_tenant() function, the b_biz_codes view):
//   1. the read is limited to the one code that business attached (never another business's, never by owner alone);
//   2. it's read only (no INSERT, UPDATE, DELETE or REPLACE on the connection; only SET and one SELECT);
//   3. @tv_tenant is reset after the read, also when the read fails, and never leaks into the next query;
//   4. nothing else reaches the page: only [code, ends] comes back (no counts, no other codes, no notes).
//   5. the page's own path: the listings login's own connection, a passed-in one never filling its cache, a failed
//      connection tried once per request.
// Run: php live-code.php <root>/app     (it resets lv_codes; user tv/tv on 127.0.0.1, with CREATE USER and GRANT OPTION for the read-only login)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/');
if (!is_file($app . '/lib/Events.php')) { fwrite(STDERR, "usage: php live-code.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Events.php'; require_once $app . '/lib/BusinessData.php';
$root = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('DROP DATABASE IF EXISTS lv_codes'); $root->exec('CREATE DATABASE lv_codes'); $root->exec('USE lv_codes');
$root->exec("CREATE TABLE biz_codes (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL, code VARCHAR(40), kind VARCHAR(8), value INT, min_order_cents BIGINT DEFAULT 0,
  starts DATE NULL, ends DATE NULL, max_uses INT DEFAULT 0, once_each TINYINT DEFAULT 1, uses INT DEFAULT 0, active TINYINT DEFAULT 1, note VARCHAR(200) DEFAULT '', UNIQUE KEY (owner_id, code))");
$root->exec('CREATE FUNCTION tv_tenant() RETURNS INT UNSIGNED DETERMINISTIC NO SQL RETURN @tv_tenant');
$root->exec('CREATE VIEW b_biz_codes AS SELECT * FROM biz_codes WHERE owner_id = tv_tenant()');
$root->exec("INSERT INTO biz_codes (owner_id, code, kind, value, ends, uses, max_uses, active, note) VALUES
  (8, 'SHOW10', 'percent', 10, '2099-12-31', 3, 0, 1, 'private note'), (8, 'OFF5', 'amount', 500, NULL, 0, 0, 0, ''), (8, 'OLD', 'percent', 5, '2000-01-01', 0, 0, 1, ''),
  (8, 'FULL', 'percent', 5, NULL, 10, 10, 1, ''), (9, 'SHOW10', 'percent', 50, NULL, 0, 0, 1, 'the other business'), (9, 'ONLY9', 'percent', 20, NULL, 0, 0, 1, '')");
$db = new PDO('mysql:host=127.0.0.1;dbname=lv_codes;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$writes = function () use ($root): int { $n = 0; foreach ($root->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace','Com_insert_select','Com_update_multi','Com_delete_multi')") as $r) $n += (int) $r['Value']; return $n; };

// 1. limited to the one code that business attached
$r = Events::liveCode(8, 'SHOW10', $db);
$t('1. its own code, on and in its dates: found', $r !== null && $r['code'] === 'SHOW10', $r);
$a = Events::liveCode(8, 'SHOW10', $db); $b = Events::liveCode(9, 'SHOW10', $db);
$t('1. two businesses with a code of the same name each read only their own (8\'s ends 2099-12-31, 9\'s never)', ($a['ends'] ?? '') === '2099-12-31' && $b !== null && $b['ends'] === null, [$a, $b]);
$t('1. one business never reads another\'s code', Events::liveCode(8, 'ONLY9', $db) === null);
$t('1. a code belonging only to another business: nothing', Events::liveCode(7, 'SHOW10', $db) === null && Events::liveCode(7, 'ONLY9', $db) === null);
$t('1. no code asked, nothing read', Events::liveCode(8, '', $db) === null && Events::liveCode(0, 'SHOW10', $db) === null);
$t('1. switched off, ended, used up: nothing', Events::liveCode(8, 'OFF5', $db) === null && Events::liveCode(8, 'OLD', $db) === null && Events::liveCode(8, 'FULL', $db) === null);
$src = (string) file_get_contents($app . '/lib/BusinessData.php'); preg_match('/function publicCode.*?\n    }\n/s', $src, $m); $body = $m[0] ?? '';   // the read lives in the realm's gateway
$t('1. the one query names the code and LIMIT 1, through the tenant view only', substr_count($body, '->prepare(\'SELECT') === 1 && str_contains($body, 'FROM `b_biz_codes` WHERE code = ? LIMIT 1') && !preg_match('/FROM `?biz_codes/', $body));

// 2. read only
$w0 = $writes(); for ($i = 0; $i < 20; $i++) { Events::liveCode(8, 'SHOW10', $db); Events::liveCode(9, 'ONLY9', $db); }
$t('2. forty reads write nothing (the server\'s own write counters unchanged)', $writes() === $w0, [$w0, $writes()]);
$t('2. its code holds no INSERT, UPDATE, DELETE or REPLACE', !preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE)\b/i', $body));
$t('2. the rows are as they were', (int) $root->query("SELECT SUM(uses) FROM biz_codes")->fetchColumn() === 13);

// 3. @tv_tenant reset, never leaking into the next query
Events::liveCode(8, 'SHOW10', $db);
$t('3. after a read, @tv_tenant is NULL on that connection', $db->query('SELECT @tv_tenant IS NULL')->fetchColumn() == 1);
$t('3. so the next query on it sees no business\'s rows', (int) $db->query('SELECT COUNT(*) FROM b_biz_codes')->fetchColumn() === 0);
$db->exec('SET @tv_tenant = 9'); Events::liveCode(8, 'SHOW10', $db);
$t('3. a tenant the caller had set is put back as it was (QA\'s second look)', (int) $db->query('SELECT @tv_tenant')->fetchColumn() === 9); $db->exec('SET @tv_tenant = NULL');
$d0 = Events::liveCode(8, 'OLD', $db); $root->exec("UPDATE biz_codes SET ends = CURDATE() WHERE owner_id = 8 AND code = 'OLD'"); $d1 = Events::liveCode(8, 'OLD', $db);
$root->exec("UPDATE biz_codes SET ends = NULL, starts = CURDATE() + INTERVAL 1 DAY WHERE owner_id = 8 AND code = 'OLD'"); $d2 = Events::liveCode(8, 'OLD', $db);
$t('1. dates as checkout counts them: ends today still shows, starts tomorrow doesn\'t', $d0 === null && $d1 !== null && $d2 === null, [$d0, $d1, $d2]);
$root->exec('RENAME TABLE biz_codes TO biz_codes_away');   // the read fails part way (its view can't be read)
$r = Events::liveCode(8, 'SHOW10', $db);
$t('3. a read that fails gives nothing (nothing shown rather than something untrue)', $r === null);
$t('3. and still resets @tv_tenant', $db->query('SELECT @tv_tenant IS NULL')->fetchColumn() == 1);
$root->exec('RENAME TABLE biz_codes_away TO biz_codes');

// 4. nothing else reaches the page
$r = Events::liveCode(8, 'SHOW10', $db);
$t('4. only its public terms come back: code, ends, the smallest order', $r !== null && array_keys($r) === ['code', 'ends', 'min_cents'], $r);
$t('4. no counts, notes, value or kind', !preg_match('/uses|note|value|kind|private|"50/', json_encode($r)), $r);
$root->exec("UPDATE biz_codes SET min_order_cents = 2000 WHERE owner_id = 8 AND code = 'SHOW10'");
$t('4. a smallest order is said, so the offer stays true', Events::codeWords(Events::liveCode(8, 'SHOW10', $db)) === 'code SHOW10 on orders of $20.00 or more until Dec 31');
$root->exec("UPDATE biz_codes SET min_order_cents = 0 WHERE owner_id = 8 AND code = 'SHOW10'");
$t('4. its words say only the code and its last day', Events::codeWords($r) === 'code SHOW10 until Dec 31', Events::codeWords($r));

// 5. the page's own path (the CTO's review, finding F): the listings login's own connection, its cache, its failure
$root->exec('DROP DATABASE IF EXISTS lv_codes2'); $root->exec('CREATE DATABASE lv_codes2'); $root->exec('USE lv_codes2');
$root->exec("CREATE TABLE biz_codes LIKE lv_codes.biz_codes"); $root->exec('CREATE FUNCTION tv_tenant() RETURNS INT UNSIGNED DETERMINISTIC NO SQL RETURN @tv_tenant');
$root->exec('CREATE VIEW b_biz_codes AS SELECT * FROM biz_codes WHERE owner_id = tv_tenant()');   // the "live" realm: no codes at all
$root->exec('USE lv_codes');
$sub = function (string $env, string $code) use ($app): array {   // a fresh request: its own statics
    $log = tempnam(sys_get_temp_dir(), 'lc');
    $php = 'require "' . $app . '/lib/Database.php"; require "' . $app . '/lib/Events.php"; $db = new PDO("mysql:host=127.0.0.1;dbname=lv_codes;charset=utf8mb4", "tv", "tv", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); ' . $code;
    $out = []; exec($env . ' ' . escapeshellarg(PHP_BINARY) . ' -d log_errors=1 -d error_log=' . escapeshellarg($log) . ' -r ' . escapeshellarg($php) . ' 2>&1', $out);
    $l = (string) @file_get_contents($log); @unlink($log); return [implode("\n", $out), $l];
};
$ok = 'DB_HOST=127.0.0.1 DB_LISTINGS_DATABASE=lv_codes2 DB_LISTINGS_USERNAME=tv DB_LISTINGS_PASSWORD=tv';
[$o] = $sub($ok, 'Events::liveCode(8, "SHOW10", $db); echo json_encode(Events::liveCode(8, "SHOW10"));');
$t('5. a passed-in connection never fills the page\'s cache (the page then reads its own realm: nothing there)', trim($o) === 'null', $o);
[$o] = $sub(str_replace('lv_codes2', 'lv_codes', $ok), 'echo json_encode(Events::liveCode(8, "SHOW10"));');
$t('5. the page\'s path reads through the listings login\'s own connection (Database::realmOwn)', str_contains($o, 'SHOW10'), $o);
[$o, $l] = $sub('DB_HOST=127.0.0.1 DB_LISTINGS_DATABASE=lv_codes DB_LISTINGS_USERNAME=nobody DB_LISTINGS_PASSWORD=wrong', 'echo json_encode([Events::liveCode(8, "SHOW10"), Events::liveCode(9, "ONLY9"), Events::liveCode(8, "OLD")]);');
$t('5. a connection that fails gives nothing for every business on the page', trim($o) === '[null,null,null]', $o);
$t('5. and is tried once, not once per business', substr_count($l, 'BusinessData::publicCode') === 1, $l);
// the real listings login has no rights on the table itself, only its view (the CTO's review, finding C)
$root->exec("DROP USER IF EXISTS 'lc_ro'@'localhost'"); $root->exec("DROP USER IF EXISTS 'lc_ro'@'%'");
$root->exec("CREATE USER 'lc_ro'@'localhost' IDENTIFIED BY 'ro'"); $root->exec("CREATE USER 'lc_ro'@'%' IDENTIFIED BY 'ro'");
foreach (['localhost', '%'] as $h) $root->exec("GRANT SELECT ON lv_codes.b_biz_codes TO 'lc_ro'@'$h'");
$ro = 'DB_HOST=127.0.0.1 DB_LISTINGS_DATABASE=lv_codes DB_LISTINGS_USERNAME=lc_ro DB_LISTINGS_PASSWORD=ro';
[$o] = $sub($ro, 'echo json_encode(Events::liveCode(8, "SHOW10"));');
$t('5. a login that can read only the view reads its own code through it', str_contains($o, 'SHOW10'), $o);
$roPdo = new PDO('mysql:host=127.0.0.1;dbname=lv_codes', 'lc_ro', 'ro', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$t('5. and couldn\'t read the table itself (so the view is the only way in)', (function () use ($roPdo) { try { $roPdo->query('SELECT * FROM biz_codes'); return false; } catch (PDOException $x) { return true; } })());
$root->exec("DROP USER 'lc_ro'@'localhost'"); $root->exec("DROP USER 'lc_ro'@'%'");
// never the request's own business connection (finding B: its reset would leave the portal's later writes refused)
[$o] = $sub($ok, 'require_once "' . $app . '/lib/Scope.php"; Scope::enterBusiness($db, 8); try { Events::liveCode(8, "SHOW10", $db); echo "used"; } catch (LogicException $x) { echo "refused"; }');
$t('5. a caller passing the portal\'s own business connection is refused', trim($o) === 'refused', $o);
$root->exec('DROP DATABASE lv_codes2');

$root->exec('DROP DATABASE lv_codes');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
