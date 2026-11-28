<?php
// tools/pause-tests/helpfind.php — the pause's last path (the IT Manager's Tier 4 condition, 2026-11-28): HelpFind::promoteSome
// puts a "Help us find" card back on the site when it gains a phone or street address. While paused it takes only a card
// whose phone or address a person entered (locked, or admin, owner or user), never one the system filled; with the pause
// emptied, as before. Run on MariaDB: php tools/pause-tests/helpfind.php <build root> <mirror's sync-app/lib> [socket]
declare(strict_types=1);

[$root, $live, $sock] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/'), $argv[3] ?? '/run/mysqld/mysqld.sock'];
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }
$run = function (bool $paused) use ($root, $live, $sock): array {
    $lib = sys_get_temp_dir() . '/hf-' . getmypid() . ($paused ? 'p' : 'u'); @mkdir($lib, 0777, true);
    exec('cp -r ' . escapeshellarg($live) . '/. ' . escapeshellarg($lib));
    foreach (['HelpFind.php', 'crawler/AutoImport.php'] as $f) copy("$root/app/lib/$f", "$lib/$f");
    if (!$paused) { $a = file_get_contents("$lib/crawler/AutoImport.php"); file_put_contents("$lib/crawler/AutoImport.php", preg_replace("/public const PAUSED = '(?:[^'\\\\]|\\\\.)*';/", "public const PAUSED = '';", $a, 1)); }
    file_put_contents("$lib/run.php", <<<'PHP'
<?php
[$lib, $sock] = [$argv[1], $argv[2]];
require "$lib/Database.php"; require "$lib/HelpFind.php";
$pdo = new PDO("mysql:unix_socket=$sock", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'hf_' . getmypid(); $pdo->exec("DROP DATABASE IF EXISTS $db"); $pdo->exec("CREATE DATABASE $db"); $pdo->exec("USE $db");
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, entity_type VARCHAR(20) DEFAULT 'merchant', is_suppressed TINYINT DEFAULT 1, updated_at DATETIME NULL)");
$pdo->exec("CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, source_type VARCHAR(20), is_locked TINYINT DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, PRIMARY KEY (entity_id, meta_key))");
$card = '{"target":"help_find","reason":"x"}';
foreach ([[1, 'phone', '"928-555-0101"', 'ai_crawler', 0], [2, 'phone', '"928-555-0102"', 'owner', 1], [3, 'address', '"12 Main St"', 'ai_crawler', 0], [4, 'address', '"14 Main St"', 'admin', 1]] as [$id, $k, $v, $src, $lock]) {
    $pdo->exec("INSERT INTO entities (id) VALUES ($id)");
    $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked) VALUES (?, 'removed', ?, 'admin', 1), (?, ?, ?, ?, ?)")->execute([$id, $card, $id, $k, $v, $src, $lock]);
}
// promoteSome returns how many it took (promote()'s own later steps are logged and caught when their tables aren't here)
echo json_encode(['n' => @HelpFind::promoteSome($pdo, 50)]);
$pdo->exec("DROP DATABASE $db");
PHP);
    $out = (string) shell_exec('php ' . escapeshellarg("$lib/run.php") . ' ' . escapeshellarg($lib) . ' ' . escapeshellarg($sock) . ' 2>/dev/null');
    exec('rm -rf ' . escapeshellarg($lib));
    return json_decode($out, true) ?: ['raw' => $out];
};
$p = $run(true); $u = $run(false);
check('paused: only the two cards whose phone or address a person entered are taken (the owner\'s phone, our team\'s address)', ($p['n'] ?? -1) === 2);
check('pause emptied: all four, as before (the crawler\'s phone and address too)', ($u['n'] ?? -1) === 4);
echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
