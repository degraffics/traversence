<?php
// loads-reply (IR-0001, 2026-11-24): a load's batch keys and the recheck after the reply, on a real MariaDB.
// php load-batches.php <app dir>   (after the start schema; it adds open_loads and open_load_batches itself)
// - a batch taken once: staged and counted; sent again with the same number: its first answer with "again", nothing staged
//   or counted twice; another number: taken
// - with a recheck list, batch() hands the ids back instead of rechecking inside the call (loads.php rechecks after its reply)
// - without a number (an older worker): as before
declare(strict_types=1);
$app = $argv[1] ?? '';
require $app . '/lib/Database.php';
require $app . '/lib/crawler/OpenLoads.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$pdo->exec('CREATE TABLE IF NOT EXISTS open_loads (dataset VARCHAR(20) NOT NULL, place_key VARCHAR(40) NOT NULL, release_id VARCHAR(40) NOT NULL, started_at DATETIME NULL, finished_at DATETIME NULL,
  seen INT DEFAULT 0, staged INT DEFAULT 0, listed INT DEFAULT 0, closed INT DEFAULT 0, PRIMARY KEY (dataset, place_key, release_id))');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', (string) file_get_contents($argv[2] ?? ''))))) as $q) $pdo->exec($q);
$pdo->exec("DELETE FROM open_loads WHERE place_key = 'cluster:99'"); $pdo->exec("DELETE FROM open_load_batches WHERE place_key = 'cluster:99'");
// rows from outside our states are counted and skipped, no identity check or staging (the batch key is checked before
// any of that, so it holds the same for rows that stage); a resend must leave every count as the first left it
$st = 'TX';
$row = function (string $id, string $name) use (&$st) { return ['id' => $id, 'name' => $name, 'street' => '1 Main St', 'city' => 'Kayenta', 'state' => $st, 'zip' => '86033', 'phone' => '', 'website' => '',
                                          'category' => 'restaurant', 'confidence' => 0.9, 'lat' => 36.72, 'lng' => -110.25, 'status' => '', 'sources' => [['Overture', 'CDLA-Permissive-2.0']]]; };
$rows = [$row('ov-1', 'Blue Coffee Pot'), $row('ov-2', 'Golden Sands Cafe')];
$counts = fn () => $pdo->query("SELECT seen, staged FROM open_loads WHERE place_key = 'cluster:99'")->fetch(PDO::FETCH_ASSOC);
$staged = fn () => (int) $pdo->query("SELECT COALESCE(SUM(staged), 0) + COALESCE(SUM(seen), 0) FROM open_loads")->fetchColumn();

$ids = ['x'];
$a = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:99', $rows, 0, $ids);
$c1 = $counts(); $s1 = $staged();
$t('batch 0 taken: seen 2, counted once', $a['seen'] === 2 && (int) $c1['seen'] === 2 && empty($a['again']), [$a, $c1]);
$t('…with a recheck list, the touched ids come back to the caller (here none), not rechecked inside', $ids === [], $ids);
$b = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:99', $rows, 0, $ids);
$t('batch 0 sent again: its first answer, with again', ($b['again'] ?? false) === true && $b['seen'] === $a['seen'] && $b['staged'] === $a['staged'], $b);
$t('…counted once (open_loads unchanged)', $counts() == $c1, $counts());
$t('…no work done again (every count as the first left it)', $staged() === $s1, [$s1, $staged()]);
$t('…and no recheck asked for (nothing new touched)', $ids === [], $ids);
$c = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:99', [$row('ov-3', 'Tse Bit Ai Diner')], 1);
$t('batch 1: taken, counted on top', empty($c['again']) && (int) $counts()['seen'] === 3, [$c, $counts()]);
$d = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:99', [$row('ov-4', 'Monument Valley Grill')]);
$t('no number (an older worker): taken as before, nothing kept', empty($d['again']) && (int) $counts()['seen'] === 4 && (int) $pdo->query("SELECT COUNT(*) FROM open_load_batches WHERE place_key = 'cluster:99'")->fetchColumn() === 2);
$e = OpenLoads::batch($pdo, 'overture', 'r2', 'cluster:99', $rows, 0);
$t('the same number in another release is its own batch', empty($e['again']));
$src = (string) file_get_contents(dirname($app) . '/website/api/crawl/loads.php');
$t('loads.php replies first, then rechecks (fastcgi_finish_request or litespeed_finish_request), else rechecks before replying',
   preg_match('/echo json_encode\(\$res.*finish_request\(\);.*OpenLoads::recheck\(\$pdo, \$ids\);\s*exit;\s*\}\s*OpenLoads::recheck\(\$pdo, \$ids\);\s*Response::json\(\$res\)/s', $src) === 1);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
