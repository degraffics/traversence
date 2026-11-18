<?php
// W2's second look (2026-11-18: GET /api/crawl/verify.php answered a hidden 500 on every run): a listing whose check
// throws is set aside with its error, the call still answers, and the ledger keeps the error for the board's W2 row.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php w2-verify.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require $app . '/lib/crawler/Verify.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$ri = Database::t('crawler_raw_index');
$pdo->exec("CREATE TABLE IF NOT EXISTS $ri (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20) NOT NULL, payload_type VARCHAR(20) NOT NULL, confidence_score INT NOT NULL DEFAULT 0, raw_payload MEDIUMTEXT NULL)");
$pdo->exec("DELETE FROM $ri");
$p = json_encode(['name' => 'Kayenta Trading Post', 'where' => ['address' => '100 US-163', 'city' => 'Kayenta', 'state' => 'AZ'], 'actions' => ['phone' => '9286970000'], 'geo' => ['latitude' => 36.7, 'longitude' => -110.25], 'deep' => ['at' => '2026-11-01']]);
$pdo->prepare("INSERT INTO $ri (status, payload_type, confidence_score, raw_payload) VALUES ('staged', 'listing', 50, ?), ('staged', 'listing', 40, ?)")->execute([$p, $p]);
// the first listing's save fails the way a live database refusal would (the second is fine); setting it aside still saves
$pdo->exec("DROP TRIGGER IF EXISTS w2_refuse");
$pdo->exec("CREATE TRIGGER w2_refuse BEFORE UPDATE ON $ri FOR EACH ROW BEGIN IF NEW.id = (SELECT MIN(id) FROM $ri) AND NEW.raw_payload NOT LIKE '%\"error\"%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data refused by the test'; END IF; END");
$threw = null; $out = null;
try { $out = Verify::lease($pdo, 4); } catch (Throwable $x) { $threw = $x->getMessage(); }
$t('a listing whose check throws no longer fails the whole call', $threw === null && is_array($out), $threw);
$t('…the one that threw is set aside, its error in words; the other goes to the worker', count(Verify::$problems) === 1 && str_contains((string) reset(Verify::$problems), 'Data refused by the test') && count($out) === 1, [Verify::$problems, $out]);
$c = json_decode((string) $pdo->query("SELECT raw_payload FROM $ri ORDER BY id LIMIT 1")->fetchColumn(), true);
$t('…kept on the listing, held 6 hours', !empty($c['verify']['error']) && strtotime((string) $c['verify']['leased_until']) > time() + 5 * 3600, $c['verify'] ?? null);
Verify::$problems = [];
$out = Verify::lease($pdo, 4);
$t('…the next call doesn\'t trip on it again (set aside)', Verify::$problems === [], Verify::$problems);
// the ledger keeps what was set aside; the board reads it as the last error
require $app . '/lib/ControlLedger.php'; require $app . '/lib/ControlBoard.php';
$pdo->exec("INSERT INTO control_ledger (at, endpoint, method, code, ms, reasons) VALUES (UTC_TIMESTAMP(), 'crawl/verify', 'GET', 500, 40, 'answered 500: Could not hand out listings to verify: PDOException: Table missing')");
$cl = ControlBoard::calls($pdo, ['crawl/verify']);
$t('the board\'s W2 row reads the last error in its own words', ($cl['last_error']['said'] ?? '') !== '' && str_contains($cl['last_error']['said'], 'Table missing'), $cl);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
