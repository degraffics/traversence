<?php
// tools/pause-tests/window.php — #330's second block (Review at 390 for the executive didn't open in 45 s): while paused,
// nothing staged leaves on its own, so the staged list only grows. The fix (session manager's call, pause-1a-4):
// AutoImport::advance passes a row once while paused (paused_pass) and skips it after; Review evaluates at most
// AutoImport::REVIEW_WINDOW staged rows, newest first, with Show more, and keeps the true total. Unpaused: unchanged.
// The real advance() on MariaDB with 1,000 staged rows. Run: php tools/pause-tests/window.php <build root> <sync-app/lib> [socket]
declare(strict_types=1);

[$root, $live, $sock] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/'), $argv[3] ?? '/run/mysqld/mysqld.sock'];
if (!is_file("$root/app/lib/crawler/AutoImport.php") || !is_dir($live)) { fwrite(STDERR, "usage: php window.php <build root> <sync-app/lib> [socket]\n"); exit(2); }
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }

$run = function (bool $paused) use ($root, $live, $sock): array {
    $lib = sys_get_temp_dir() . '/pw-' . getmypid() . ($paused ? 'p' : 'u'); @mkdir($lib, 0777, true);
    exec('cp -r ' . escapeshellarg($live) . '/. ' . escapeshellarg($lib));
    copy("$root/app/lib/crawler/AutoImport.php", "$lib/crawler/AutoImport.php");
    // the matcher's lookups (categories, entities) stubbed: this test is about which rows a pass reads and writes
    file_put_contents("$lib/crawler/IntakeStager.php", '<?php class IntakeStager { public static function freshMatch($p, $c) { return ["exact" => null, "possible" => []]; } public static function suggestFor($p, $c) { return []; } public static function existingListing($p, $id) { return null; } public static function reject(...$a) {} }');
    if (!$paused) { $a = file_get_contents("$lib/crawler/AutoImport.php"); file_put_contents("$lib/crawler/AutoImport.php", preg_replace("/public const PAUSED = '(?:[^'\\\\]|\\\\.)*';/", "public const PAUSED = '';", $a, 1)); }
    $code = <<<'PHP'
<?php
declare(strict_types=1);
[$lib, $sock] = [$argv[1], $argv[2]];
putenv('AUTO_IMPORT=off');   // unpaused runs publish nothing either: the test is about which rows advance() touches
require "$lib/Database.php"; require "$lib/crawler/AutoImport.php";
$pdo = new PDO("mysql:unix_socket=$sock", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'pw_' . getmypid(); $pdo->exec("DROP DATABASE IF EXISTS $db"); $pdo->exec("CREATE DATABASE $db"); $pdo->exec("USE $db");
$pdo->exec("CREATE TABLE crawler_raw_index (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20), payload_type VARCHAR(20), raw_payload LONGTEXT, confidence_score DOUBLE DEFAULT 0.5, entity_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, source_url VARCHAR(500) DEFAULT '')");
$ins = $pdo->prepare("INSERT INTO crawler_raw_index (status, payload_type, raw_payload) VALUES ('staged', 'listing', ?)");
for ($i = 1; $i <= 1000; $i++) $ins->execute([json_encode(['name' => "Place $i", 'hold' => 1])]);   // held rows: no lookups, only the pass itself
$p = new ReflectionProperty(Database::class, 'instance'); $p->setAccessible(true); $p->setValue(null, $pdo);
$touched = fn () => (int) $pdo->query("SELECT COUNT(*) FROM crawler_raw_index WHERE JSON_EXTRACT(raw_payload, '$.advanced_at') IS NOT NULL")->fetchColumn();
$out = ['paused' => AutoImport::pausedWhy() !== null, 'window' => AutoImport::REVIEW_WINDOW];
$a = AutoImport::advance($pdo, 150); $out['first'] = $a['checked']; $out['touched1'] = $touched();
$ids1 = $pdo->query("SELECT id FROM crawler_raw_index WHERE JSON_EXTRACT(raw_payload, '$.advanced_at') IS NOT NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$stamp = $pdo->query("SELECT raw_payload FROM crawler_raw_index WHERE id = " . (int) $ids1[0])->fetchColumn();
$b = AutoImport::advance($pdo, 150); $out['second'] = $b['checked']; $out['touched2'] = $touched();
$again = $pdo->query("SELECT raw_payload FROM crawler_raw_index WHERE id = " . (int) $ids1[0])->fetchColumn();
$out['first_row_untouched'] = $again === $stamp;
$out['flagged'] = (int) $pdo->query("SELECT COUNT(*) FROM crawler_raw_index WHERE JSON_EXTRACT(raw_payload, '$.paused_pass') IS NOT NULL")->fetchColumn();
for ($k = 0; $k < 10; $k++) AutoImport::advance($pdo, 150);
$out['after_all'] = (int) AutoImport::advance($pdo, 150)['checked'];
$pdo->exec("DROP DATABASE $db");
echo json_encode($out);
PHP;
    file_put_contents("$lib/run.php", $code);
    $o = (string) shell_exec('php ' . escapeshellarg("$lib/run.php") . ' ' . escapeshellarg($lib) . ' ' . escapeshellarg($sock) . ' 2>&1');
    exec('rm -rf ' . escapeshellarg($lib));
    $j = json_decode(trim(substr($o, (int) strrpos($o, '{'))), true);
    if (!is_array($j) || getenv("PW_DEBUG")) { echo $o, "\n"; }
    if (!is_array($j)) return [];
    return $j;
};

$p = $run(true);
check('paused: the run is paused', ($p['paused'] ?? null) === true);
check('…the first pass reads 150 of the 1,000 staged rows', ($p['first'] ?? 0) === 150 && ($p['touched1'] ?? 0) === 150);
check('…the second pass reads 150 others: 300 touched, none twice', ($p['second'] ?? 0) === 150 && ($p['touched2'] ?? 0) === 300);
check('…a row passed once is not written again', ($p['first_row_untouched'] ?? false) === true);
check('…each passed row is marked paused_pass', ($p['flagged'] ?? 0) === 300);
check('…once all 1,000 are passed, a pass reads none', ($p['after_all'] ?? -1) === 0);
check('…Review\'s window is 200', ($p['window'] ?? 0) === 200);

$u = $run(false);
check('unpaused: the run is not paused', ($u['paused'] ?? null) === false);
check('…the first pass reads 150, as before', ($u['first'] ?? 0) === 150);
check('…the second reads 150 again (the oldest-advanced first, as before; nothing skipped)', ($u['second'] ?? 0) === 150);
check('…nothing is marked paused_pass', ($u['flagged'] ?? -1) === 0);
check('…a pass after all still reads 150 (nothing skipped unpaused)', ($u['after_all'] ?? 0) === 150);

// Review: the window, newest first, the true total kept, Show more
$rv = file_get_contents("$root/website/admin/crawler-review.php");
check('Review: the window applies only while paused', str_contains($rv, '$pausedWindow = AutoImport::pausedWhy() !== null ? AutoImport::REVIEW_WINDOW'));
check('…newest first, at most the window', str_contains($rv, '"ORDER BY r.id DESC LIMIT " . $pausedWindow'));
check('…unpaused, the same order as before (confidence, then id)', str_contains($rv, ': "ORDER BY r.confidence_score DESC, r.id"'));
check('…Show more asks for the next window, capped', str_contains($rv, "min(50, max(0, (int) (\$_GET['more'] ?? 0)))") && str_contains($rv, "\$qs(['more' => (int) (\$_GET['more'] ?? 0) + 1])"));
check('…the total stays the true count of everything staged', substr_count($rv, "SELECT COUNT(*) FROM \" . Database::t('crawler_raw_index') . \" WHERE status = 'staged' AND payload_type = 'listing'") === 2 && str_contains($rv, 'of <b><?= number_format($stagedTotal) ?></b> waiting'));

// the window query on 1,000 rows reads no more than 200
$sql = "SELECT id FROM t WHERE status = 'staged' ORDER BY id DESC LIMIT 200";
$pdo = new PDO("mysql:unix_socket=$sock", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS pw_q'); $pdo->exec('USE pw_q'); $pdo->exec('DROP TABLE IF EXISTS t'); $pdo->exec("CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20))");
$pdo->exec("INSERT INTO t (status) SELECT 'staged' FROM (SELECT 1 FROM information_schema.columns LIMIT 1000) x");
$got = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
check('…on 1,000 staged rows Review reads 200, the newest', count($got) === 200 && (int) $got[0] === 1000 && (int) end($got) === 801);
$pdo->exec('DROP DATABASE pw_q');

echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
