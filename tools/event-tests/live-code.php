<?php
// Events::liveCode (P1/P2; the session manager, 2026-11-27: the CTO's Tier 4 on reading a business's walled data from a
// public page, 0110). Four things, each tested on a real MySQL or MariaDB built like the listings realm (BusinessData:
// biz_codes with owner_id, the tv_tenant() function, the b_biz_codes view):
//   1. the read is limited to the one code that business attached (never another business's, never by owner alone);
//   2. it's read only (no INSERT, UPDATE, DELETE or REPLACE on the connection; only SET and one SELECT);
//   3. @tv_tenant is reset after the read, also when the read fails, and never leaks into the next query;
//   4. nothing else reaches the page: only [code, ends] comes back (no counts, no other codes, no notes).
// Run: php live-code.php <root>/app     (it resets lv_codes; user tv/tv on 127.0.0.1)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/');
if (!is_file($app . '/lib/Events.php')) { fwrite(STDERR, "usage: php live-code.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Events.php';
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
$src = (string) file_get_contents($app . '/lib/Events.php'); preg_match('/function liveCode.*?\n    }\n/s', $src, $m); $body = $m[0] ?? '';
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
$root->exec('RENAME TABLE biz_codes TO biz_codes_away');   // the read fails part way (its view can't be read)
$r = Events::liveCode(8, 'SHOW10', $db);
$t('3. a read that fails gives nothing (nothing shown rather than something untrue)', $r === null);
$t('3. and still resets @tv_tenant', $db->query('SELECT @tv_tenant IS NULL')->fetchColumn() == 1);
$root->exec('RENAME TABLE biz_codes_away TO biz_codes');

// 4. nothing else reaches the page
$r = Events::liveCode(8, 'SHOW10', $db);
$t('4. only code and ends come back', $r !== null && array_keys($r) === ['code', 'ends'], $r);
$t('4. no counts, notes, value or kind', !preg_match('/uses|note|value|kind|private|50/', json_encode($r)), $r);
$t('4. its words say only the code and its last day', Events::codeWords($r) === 'code SHOW10 until Dec 31', Events::codeWords($r));

$root->exec('DROP DATABASE lv_codes');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
