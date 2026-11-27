<?php
// W1 load trips (#11, launch plan B.7): a load batch's reply accounts for every record it received, so the ledger's
// "/api/crawl/loads: received 100, accounted for 80" can't come from a good batch (W1, 2026-11-24; ControlLedger::counts).
// php load-counts.php <app dir> <open_load_batches migration>   (a real MySQL or MariaDB; the database from <app>'s .env)
// The real OpenLoads::batch and ControlLedger::counts run; the identity check, the intake and the name check are stand-ins
// with known answers (their own suites test them), so each way a record can go is driven on purpose:
//   skipped (junk name, outside our states, closed but not ours), closed (a closure signal on a listing we have),
//   folded into a twin in the same batch, already known to the intake, staged.
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/');
$mig = $argv[2] ?? '';
if (!is_file($app . '/lib/crawler/OpenLoads.php') || !is_file($app . '/lib/ControlLedger.php') || !is_file($mig)) { fwrite(STDERR, "php load-counts.php <app dir> <open_load_batches migration>\n"); exit(2); }

// a scratch lib: the real files under test, the stand-ins beside them (OpenLoads requires them by path)
$lib = sys_get_temp_dir() . '/load-counts-' . getmypid() . '/lib';
@mkdir($lib . '/crawler', 0777, true);
foreach (['Database.php', 'ControlLedger.php', 'crawler/OpenLoads.php'] as $f) copy($app . '/lib/' . $f, $lib . '/' . $f);
$env = dirname($app, 3) . '/.env';   // Database.php reads the .env four folders above lib/
@mkdir(dirname($lib, 4), 0777, true);
if (is_file($env)) copy($env, dirname($lib, 4) . '/.env');
file_put_contents($lib . '/DataCleanup.php', '<?php class DataCleanup { public static function junkName(string $n): array { return [stripos($n, "junk") !== false, ""]; } }');
file_put_contents($lib . '/ListingConfidence.php', '<?php class ListingConfidence { public static function refresh($pdo, int $id): void {} }');
file_put_contents($lib . '/ListingIdentity.php', '<?php class ListingIdentity {
    public static function phoneKey(string $p): string { return preg_replace("/\\D/", "", $p); }
    // "Ours …" is a listing we have
    public static function resolve($pdo, array $c): array { return stripos((string) ($c["name"] ?? ""), "ours") === 0 ? ["kind" => "same", "id" => 7] : ["kind" => "new", "id" => 0]; }
    // twins: the same name folds into one
    public static function fold(array $c): array { $o = []; foreach ($c as $x) $o[strtolower($x["name"])] = $x; return array_values($o); }
}');
file_put_contents($lib . '/crawler/CrawlQueue.php', '<?php class CrawlQueue { public static function normalize(array $c): ?array { return stripos($c["name"], "unshaped") !== false ? null : $c; } }');
file_put_contents($lib . '/crawler/IntakeStager.php', '<?php class IntakeStager {
    public static $n = 100;
    public static function createSource($pdo, string $url, array $f, string $name, int $x): int { return 1; }
    // "Known …" is content the intake already has; "Auto …" imports itself; the rest wait in Review
    public static function stage($pdo, int $src, array $c): array { $s = []; $a = []; $k = 0;
        foreach ($c as $x) { if (stripos($x["name"], "known") === 0) { $k++; continue; } if (stripos($x["name"], "auto") === 0) $a[] = ["entity_id" => ++self::$n]; else $s[] = ++self::$n; }
        return ["staged" => $s, "already_known" => $k, "auto_imported" => $a]; }
}');
require $lib . '/Database.php';
require $lib . '/crawler/OpenLoads.php';
require $lib . '/ControlLedger.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };

$pdo->exec('CREATE TABLE IF NOT EXISTS open_loads (dataset VARCHAR(20) NOT NULL, place_key VARCHAR(40) NOT NULL, release_id VARCHAR(40) NOT NULL, started_at DATETIME NULL, finished_at DATETIME NULL,
  seen INT DEFAULT 0, staged INT DEFAULT 0, listed INT DEFAULT 0, closed INT DEFAULT 0, PRIMARY KEY (dataset, place_key, release_id))');
$pdo->exec('CREATE TABLE IF NOT EXISTS crawler_sources (id INT AUTO_INCREMENT PRIMARY KEY, url VARCHAR(500))');
$pdo->exec('CREATE TABLE IF NOT EXISTS entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, source_type VARCHAR(20), is_locked TINYINT, created_at DATETIME, updated_at DATETIME, PRIMARY KEY (entity_id, meta_key))');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', (string) file_get_contents($mig))))) as $q) $pdo->exec($q);
$pdo->exec("DELETE FROM open_loads WHERE place_key LIKE 'cluster:9%'"); $pdo->exec("DELETE FROM open_load_batches WHERE place_key LIKE 'cluster:9%'");

$row = fn (string $id, string $name, string $state = 'AZ', string $status = '') => ['id' => $id, 'name' => $name, 'street' => '1 Main St', 'city' => 'Kayenta', 'state' => $state, 'zip' => '86033',
    'phone' => '', 'website' => '', 'category' => 'restaurant', 'confidence' => 0.9, 'lat' => 36.72, 'lng' => -110.25, 'status' => $status, 'sources' => [['Overture', 'CDLA-Permissive-2.0']]];
// 20 records, every way a record can go
$rows = [
    $row('a1', 'Junk Junk'), $row('a2', 'Texas BBQ', 'TX'), $row('a3', 'Gone Cafe', 'AZ', 'permanently_closed'),        // skipped ×3
    $row('a4', 'Ours Trading Post', 'AZ', 'permanently_closed'), $row('a5', 'Ours Motel', 'AZ', 'closed'),            // closed ×2
    $row('a6', 'Unshaped Thing'),                                                                                    // skipped (no shape)
    $row('a7', 'Blue Coffee Pot'), $row('a8', 'blue coffee pot'), $row('a9', 'Blue Coffee Pot'),                      // 2 folded, 1 on
    $row('b1', 'Known Diner'), $row('b2', 'Known Market'),                                                           // already known ×2
    $row('b3', 'Auto Gas'), $row('b4', 'Ours Grill'),                                                                // auto import; listed and staged
    $row('b5', 'Tse Bit Ai Diner'), $row('b6', 'Golden Sands Cafe'), $row('b7', 'Monument Valley Grill'), $row('b8', 'Navajo Arts'),
    $row('b9', 'Kayenta Clinic'), $row('c1', 'Laundry'), $row('c2', 'Hotel'),                                         // staged
];
$ids = [];
$a = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:91', $rows, 0, $ids);
$t('the batch saw all 20', $a['seen'] === 20, $a);
$t('…4 skipped (junk, outside our states, closed but not ours, no shape)', $a['skipped'] === 4, $a);
$t('…2 closed (closure signals on listings we have)', $a['closed'] === 2, $a);
$t('…2 folded into their twin in the batch, as duplicates', ($a['duplicates'] ?? null) === 2, $a);
$t('…2 already known to the intake', ($a['already_known'] ?? null) === 2, $a);
$t('…10 staged (Review and the one auto import)', $a['staged'] === 10, $a);
$t('…every record seen is one of those', $a['skipped'] + $a['closed'] + $a['duplicates'] + $a['already_known'] + $a['staged'] === $a['seen'], $a);
$t('…listed stays a part of staged (Ours Grill), never added on top', $a['listed'] === 1, $a);

// what the ledger makes of the reply (loads.php answers ['ok' => true] + batch())
$c = ControlLedger::counts(['ok' => true] + $a);
$sorted = $c['stored'] + $c['updated'] + $c['review'] + $c['refused'];
$t('the ledger accounts for all 20 it received (no "received 20, accounted for 16")', $sorted === 20, $c);
$t('…closed counted as updated (a closure signal on a listing)', $c['updated'] === 2, $c);
$t('…staged as Review', $c['review'] === 10, $c);
$t('…skipped, duplicates and already known as refused, by reason', $c['refused'] === 8 && ($c['reasons']['duplicates'] ?? 0) === 2 && ($c['reasons']['already known'] ?? 0) === 2 && ($c['reasons']['skipped'] ?? 0) === 4, $c);

// a batch sent again answers what it did then, counts included (open_load_batches.result, VARCHAR(500))
$b = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:91', $rows, 0, $ids);
$t('batch 0 sent again: its first answer, with again, the new counts kept', ($b['again'] ?? false) === true && ($b['duplicates'] ?? null) === 2 && ($b['already_known'] ?? null) === 2, $b);
$c2 = ControlLedger::counts(['ok' => true] + $b);
$t('…and the ledger still accounts for all 20', $c2['stored'] + $c2['updated'] + $c2['review'] + $c2['refused'] === 20, $c2);
$len = (int) $pdo->query("SELECT MAX(CHAR_LENGTH(result)) FROM open_load_batches WHERE place_key = 'cluster:91'")->fetchColumn();
$t('…its kept answer fits its column (' . $len . ' of 500)', $len > 0 && $len < 500, $len);

// a batch with nothing to stage (every row outside our states) still adds up, the new counts at 0
$d = OpenLoads::batch($pdo, 'overture', 'r1', 'cluster:92', [$row('d1', 'Texas BBQ', 'TX'), $row('d2', 'Okie Diner', 'OK')], 0);
$cd = ControlLedger::counts(['ok' => true] + $d);
$t('a batch with nothing to stage: duplicates and already known at 0, all accounted for', ($d['duplicates'] ?? null) === 0 && ($d['already_known'] ?? null) === 0 && $cd['refused'] === 2 && $cd['refused'] + $cd['review'] + $cd['updated'] + $cd['stored'] === 2, [$d, $cd]);

// the other endpoint that answers 'closed' (W2's /api/crawl/places: {ok, matched, closed}): closed now reads as updated
$p = ControlLedger::counts(['ok' => true, 'matched' => 1, 'closed' => 1]);
$t('places\' closed reads as updated too (it was counted as nothing)', $p['updated'] === 1, $p);
// the receipt shape (W0's /api/crawl/index) is read as before
$w = ControlLedger::counts(['ok' => true, 'receipt' => ['received' => 250, 'stored' => 247, 'rejected' => [['u', 'junk'], ['u', 'junk'], ['u', 'robots']]]]);
$t('W0\'s receipt is read as before (247 stored, 3 refused)', $w['stored'] === 247 && $w['refused'] === 3 && !empty($w['receipt']), $w);

$pdo->exec("DELETE FROM open_loads WHERE place_key LIKE 'cluster:9%'"); $pdo->exec("DELETE FROM open_load_batches WHERE place_key LIKE 'cluster:9%'");
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
