<?php
// tools/pause-tests/identity.php — the pause's real path on MariaDB (the IT Manager's Tier 4 and its independent check,
// 2026-11-28): the evidence (Identity::applyEvidence, where a merge's adopt, the identity worker and the place store all
// land) writes no About text or hours while paused: they're held as one suggestion for Review, the About kept in full
// (Suggestions' machine-only field), a second find added to the open one; phone still applies; a field an owner or staff
// member set (locked) is never touched and never held; with the pause emptied, the evidence writes as before. Then the
// people's own saves (the owner's portal, Claims::writeField; the staff editor, IntakeStager::adminSave) write directly,
// locked, and never ask the pause, and the twin merge (ListingIdentity::merge) holds the two fields when not a person's.
// Run: php tools/pause-tests/identity.php <build root> <mirror's sync-app/lib> [socket]
declare(strict_types=1);

[$root, $live, $sock] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/'), $argv[3] ?? '/run/mysqld/mysqld.sock'];
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }

$run = function (bool $paused) use ($root, $live, $sock): array {
    $lib = sys_get_temp_dir() . '/pid-' . getmypid() . ($paused ? 'p' : 'u'); @mkdir($lib, 0777, true);
    exec('cp -r ' . escapeshellarg($live) . '/. ' . escapeshellarg($lib));
    foreach (['Identity.php', 'Suggestions.php', 'ListingIdentity.php', 'crawler/AutoImport.php'] as $f) copy("$root/app/lib/$f", "$lib/$f");
    if (!$paused) { $a = file_get_contents("$lib/crawler/AutoImport.php"); file_put_contents("$lib/crawler/AutoImport.php", preg_replace("/public const PAUSED = '(?:[^'\\\\]|\\\\.)*';/", "public const PAUSED = '';", $a, 1)); }
    $code = <<<'PHP'
<?php
declare(strict_types=1);
[$lib, $sock] = [$argv[1], $argv[2]];
require "$lib/Database.php"; require "$lib/Identity.php"; require "$lib/Suggestions.php";
$pdo = new PDO("mysql:unix_socket=$sock", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'pid_' . getmypid(); $pdo->exec("DROP DATABASE IF EXISTS $db"); $pdo->exec("CREATE DATABASE $db"); $pdo->exec("USE $db");
foreach ([
  "CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(255), status VARCHAR(30) DEFAULT 'unclaimed', owner_user_id INT NULL, updated_by_type VARCHAR(20) DEFAULT 'ai_crawler', updated_at DATETIME NULL, entity_type VARCHAR(20) DEFAULT 'merchant', is_suppressed TINYINT DEFAULT 0)",
  "CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, source_type VARCHAR(20), is_locked TINYINT DEFAULT 0, trust_score DOUBLE NULL, created_at DATETIME NULL, updated_at DATETIME NULL, PRIMARY KEY (entity_id, meta_key))",
  "CREATE TABLE identity_facts (id INT AUTO_INCREMENT PRIMARY KEY, record_kind VARCHAR(20), record_id INT, fact VARCHAR(20), value TEXT, is_current TINYINT DEFAULT 1, status VARCHAR(20))",
  "CREATE TABLE identity_fact_sources (id INT AUTO_INCREMENT PRIMARY KEY, fact_id INT, kind VARCHAR(20), site VARCHAR(200) DEFAULT '', url VARCHAR(500) DEFAULT '', label VARCHAR(200) DEFAULT '', private TINYINT DEFAULT 0)",
  "CREATE TABLE identity_applied (id INT AUTO_INCREMENT PRIMARY KEY, record_id INT, fact VARCHAR(20), fact_id INT, value TEXT, before_json TEXT, sources_n INT, applied_at DATETIME)",
  "CREATE TABLE suggestions (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(20), topic VARCHAR(20), entity_id INT NULL, same_as_id INT NULL, target VARCHAR(80) DEFAULT '', target_name VARCHAR(200) DEFAULT '', place_key VARCHAR(40) DEFAULT '', section VARCHAR(80) DEFAULT '', fields TEXT, note TEXT, context VARCHAR(400) DEFAULT '', site_key VARCHAR(80) DEFAULT '', phone10 VARCHAR(10) DEFAULT '', user_id INT NULL, device_hash VARCHAR(64), status VARCHAR(20) DEFAULT 'new', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
] as $q) $pdo->exec($q);
$fact = function (int $id, string $f, string $v, array $srcs) use ($pdo): void {
    $pdo->prepare("INSERT INTO identity_facts (record_kind, record_id, fact, value, status) VALUES ('listing', ?, ?, ?, 'single')")->execute([$id, $f, $v]); $fid = (int) $pdo->lastInsertId();
    foreach ($srcs as [$k, $site]) $pdo->prepare('INSERT INTO identity_fact_sources (fact_id, kind, site, url, label) VALUES (?, ?, ?, ?, ?)')->execute([$fid, $k, $site, "https://$site/", $site]);
};
// #1: unclaimed, empty; one website gives an About (the site-wide one, C1), hours, and a phone
$pdo->exec("INSERT INTO entities (id, name) VALUES (1, 'Summit Healthcare Regional Medical Center'), (2, 'Hometown Theater')");
$about = 'Comprehensive healthcare in Show Low, AZ. Board certified specialists offering quality preventive and diagnostic services in Lakeside, AZ. ' . str_repeat('More words. ', 30);
$fact(1, 'description', $about, [['website', 'summithealthcare.net']]);
$fact(1, 'hours', 'Monday - Friday 7 AM - 5 PM; Saturday 8 AM - 12 PM', [['website', 'summithealthcare.net']]);
$fact(1, 'phone', '9285373000', [['website', 'summithealthcare.net']]);
// #2: the owner wrote its About and hours (Claims::writeField: owner, locked); the evidence differs
$pdo->exec("INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked) VALUES (2, 'description', '\"Our own words\"', 'owner', 1), (2, 'hours', '[\"Fri 7 PM\"]', 'owner', 1)");
$fact(2, 'description', 'A site-wide line about something else', [['website', 'example.com'], ['website', 'other.com']]);
$fact(2, 'hours', 'Mon 9 AM - 5 PM', [['website', 'example.com'], ['website', 'other.com']]);
$a1 = Identity::applyEvidence($pdo, 1); $a2 = Identity::applyEvidence($pdo, 2);
// a second find: another About fact for #1
$fact(1, 'description', 'A second, different About from the same evidence.', [['website', 'summithealthcare.net']]);
$pdo->exec("UPDATE identity_facts SET is_current = 0 WHERE record_id = 1 AND fact = 'description' AND value LIKE 'Comprehensive%'");
$a3 = Identity::applyEvidence($pdo, 1);
$m = fn (int $id, string $k) => $pdo->query("SELECT meta_value, source_type, is_locked FROM entity_metadata WHERE entity_id = $id AND meta_key = '$k'")->fetch(PDO::FETCH_ASSOC) ?: null;
echo json_encode(['a1' => $a1, 'a2' => $a2, 'a3' => $a3, 'd1' => $m(1, 'description'), 'h1' => $m(1, 'hours'), 'p1' => $m(1, 'phone'), 'd2' => $m(2, 'description'), 'h2' => $m(2, 'hours'),
    'sugs' => $pdo->query('SELECT entity_id, kind, device_hash, fields, note FROM suggestions')->fetchAll(PDO::FETCH_ASSOC), 'applied' => $pdo->query('SELECT record_id, fact FROM identity_applied')->fetchAll(PDO::FETCH_ASSOC)]);
$pdo->exec("DROP DATABASE $db");
PHP;
    file_put_contents("$lib/run.php", $code);
    $out = (string) shell_exec('php ' . escapeshellarg("$lib/run.php") . ' ' . escapeshellarg($lib) . ' ' . escapeshellarg($sock) . ' 2>&1');
    exec('rm -rf ' . escapeshellarg($lib));
    return json_decode($out, true) ?: ['raw' => $out];
};

$p = $run(true);
if (isset($p['raw'])) { echo $p['raw'], "\n"; exit(1); }
$s1 = array_values(array_filter($p['sugs'], fn ($s) => (int) $s['entity_id'] === 1));
$f1 = json_decode((string) ($s1[0]['fields'] ?? ''), true) ?: [];
check('paused: the evidence writes no About and no hours on #1 (nothing in identity_applied for them)', $p['d1'] === null && $p['h1'] === null && !array_filter($p['applied'], fn ($a) => in_array($a['fact'], ['description', 'hours'], true)));
check('…the phone still applies, as the crawler\'s', ($p['p1']['source_type'] ?? '') === 'ai_crawler' && in_array('phone', $p['a1'], true));
check('…held as ONE suggestion for Review (kind fix, crawler-identity-paused), the About kept in full, the hours with it', count($s1) === 1 && $s1[0]['kind'] === 'fix' && $s1[0]['device_hash'] === 'crawler-identity-paused' && mb_strlen((string) ($f1['description'] ?? '')) > 160 && str_starts_with((string) $f1['description'], 'Comprehensive healthcare') && str_contains((string) ($f1['hours'] ?? ''), 'Monday - Friday'));
check('…a second find is added to the open suggestion, never dropped', str_contains((string) ($f1['description'] ?? ''), ' | A second, different About'));
check('…the note names the pause', str_contains((string) ($s1[0]['note'] ?? ''), 'Held for a person while the pause holds'));
check('the owner\'s own About and hours (locked): untouched, and nothing held for them', json_decode((string) $p['d2']['meta_value'], true) === 'Our own words' && $p['d2']['source_type'] === 'owner' && $p['h2']['source_type'] === 'owner' && !array_filter($p['sugs'], fn ($s) => (int) $s['entity_id'] === 2));
$u = $run(false);
check('pause emptied (the way back): the evidence writes the About and hours as before', ($u['d1']['source_type'] ?? '') === 'ai_crawler' && ($u['h1']['source_type'] ?? '') === 'ai_crawler' && !$u['sugs']);

// the people's own saves write directly, locked, and never ask the pause (they publish as today, paused or not)
$lv = fn (string $f) => (string) file_get_contents("$live/$f");
preg_match('/public static function writeField\(.*?\n    \}\n/s', $lv('Claims.php'), $wf);
check('the owner\'s portal save (Claims::writeField): owner, locked, written at once, no pause in it', isset($wf[0]) && str_contains($wf[0], "'owner', 1, 100.00") && !str_contains($wf[0], 'pausedWhy') && !str_contains($wf[0], 'holdForReview'));
preg_match('/public static function adminSave\(.*?\n    \}\n/s', file_get_contents("$live/crawler/IntakeStager.php"), $as);
check('the staff editor\'s save (IntakeStager::adminSave): admin, locked, written at once, no pause in it', isset($as[0]) && str_contains($as[0], "'admin', 1, NOW()") && !str_contains($as[0], 'pausedWhy') && !str_contains($as[0], 'holdForReview'));
check('…and neither file is in the release (unchanged)', !is_file("$root/app/lib/Claims.php") && !is_file("$root/app/lib/crawler/IntakeStager.php"));
// the twin merge: the two fields held unless it's a person's merge
$li = file_get_contents("$root/app/lib/ListingIdentity.php");
check('ListingIdentity::merge: description and hours held for Review while paused when no person merges, before the copy', preg_match("/in_array\(\\\$k, \['description', 'hours'\], true\) && !\\\$person\).*?AutoImport::pausedWhy\(\) !== null\).*?holdForReview\(\\\$pdo, \\\$keep.*?continue;.*?\\\$ins->execute\(\[\\\$keep, \\\$drop, \\\$k\]\)/s", $li) === 1);

echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
