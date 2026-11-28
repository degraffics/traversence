<?php
// tools/f9-tests/cleanup.php — F9's two Data cleanup rules (the CEO's ruling, 2026-11-28; research/data-capture-review-2026-12-02.md)
// on a real MariaDB: person_place finds people (NPI's "Last, First", a credential at the end), never a place with a
// business word or two plain words, and names its practice at the same street or phone; legacy_unsourced finds a
// September listing with no keepable source, acts only within the scope the CEO sets (off, health, all), and leaves a
// listing that came through the intake or has a kept source alone; the pilot's listings (#8807 and the ones Editorial adds)
// and anything a person touched are "Check these", never acted on; a pass acts through the ledger and Undo puts it back.
// Run: php tools/f9-tests/cleanup.php <build root> <mirror's sync-app/lib> [socket]
declare(strict_types=1);

[$root, $live, $sock] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/'), $argv[3] ?? '/run/mysqld/mysqld.sock'];
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }

$lib = sys_get_temp_dir() . '/f9-lib-' . getmypid(); @mkdir($lib, 0777, true);
exec('cp -r ' . escapeshellarg($live) . '/. ' . escapeshellarg($lib));
copy("$root/app/lib/DataCleanup.php", "$lib/DataCleanup.php");
require "$lib/Database.php"; require "$lib/DataCleanup.php";

// the names
$P = fn ($n) => DataCleanup::isPerson($n);
check('people: "Hiebert, Cassie", "Mcalister, Jamie L", "Iverson Alison NP", "Larson J W DC"', $P('Hiebert, Cassie') && $P('Mcalister, Jamie L') && $P('Iverson Alison NP') && $P('Larson J W DC'));
check('not people: a practice, a hospital, two plain words, a name with "of", a numbered name', !$P('Recoveryworks, LLC') && !$P('Summit Healthcare Regional Medical Center') && !$P('Taylor Pool') && !$P('Church of Christ') && !$P('Route 66 Diner') && !$P('Smith & Jones'));

$pdo = new PDO("mysql:unix_socket=$sock", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS f9_test'); $pdo->exec('CREATE DATABASE f9_test'); $pdo->exec('USE f9_test');
foreach ([
  "CREATE TABLE entities (id INT PRIMARY KEY, entity_type VARCHAR(20) DEFAULT 'merchant', name VARCHAR(255), status VARCHAR(30) DEFAULT 'unclaimed', owner_user_id INT NULL, updated_by_type VARCHAR(20) DEFAULT 'ai_crawler', created_by_type VARCHAR(20) DEFAULT 'ai_crawler', is_suppressed TINYINT DEFAULT 0, primary_zip VARCHAR(10) DEFAULT '85901', category_id INT NULL, latitude DOUBLE NULL, longitude DOUBLE NULL, created_at DATETIME, updated_at DATETIME NULL)",
  "CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, source_type VARCHAR(20) DEFAULT 'ai_crawler', is_locked TINYINT DEFAULT 0, trust_score DOUBLE NULL, created_at DATETIME NULL, updated_at DATETIME NULL, PRIMARY KEY (entity_id, meta_key))",
  "CREATE TABLE crawler_raw_index (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, status VARCHAR(20), reviewed_by_user_id INT NULL, source_url VARCHAR(500) NULL, raw_payload TEXT, created_at DATETIME NULL)",
  "CREATE TABLE identity_facts (id INT AUTO_INCREMENT PRIMARY KEY, record_kind VARCHAR(20), record_id INT, fact VARCHAR(20), value TEXT, is_current TINYINT DEFAULT 1, status VARCHAR(20) DEFAULT 'current')",
  "CREATE TABLE identity_fact_sources (id INT AUTO_INCREMENT PRIMARY KEY, fact_id INT, kind VARCHAR(20), site VARCHAR(200) DEFAULT '')",
  "CREATE TABLE categories (id INT PRIMARY KEY, name VARCHAR(120), slug VARCHAR(120), parent_id INT NULL)",
  "CREATE TABLE cleanup_settings (k VARCHAR(40) PRIMARY KEY, v VARCHAR(500), by_id INT NULL, at DATETIME NULL)",
] as $q) $pdo->exec($q);
$pdo->exec("INSERT INTO categories VALUES (1, 'Health & Medical', 'health-and-medical', NULL), (2, 'Doctors', 'doctors', 1), (3, 'Food & Dining', 'food-and-dining', NULL), (4, 'Restaurants', 'restaurants', 3)");
$L = function (int $id, string $name, int $cat, string $created, array $meta, array $extra = []) use ($pdo): void {
    $e = $extra + ['status' => 'unclaimed', 'owner_user_id' => null, 'updated_by_type' => 'ai_crawler'];
    $pdo->prepare('INSERT INTO entities (id, name, category_id, created_at, status, owner_user_id, updated_by_type) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$id, $name, $cat, $created, $e['status'], $e['owner_user_id'], $e['updated_by_type']]);
    foreach ($meta as $k => $v) $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value, source_type, is_locked) VALUES (?, ?, ?, ?, ?)')->execute([$id, $k, json_encode($v), $extra['src'][$k] ?? 'ai_crawler', (int) ($extra['lock'][$k] ?? 0)]);
};
$sep = '2026-09-12 10:00:00'; $nov = '2026-11-20 10:00:00';
$L(100, 'Summit Family Medicine', 2, $sep, ['address' => '2200 E Show Low Lake Rd', 'phone' => '928-537-4375']);            // a practice
$L(101, 'Hiebert, Cassie', 2, $sep, ['address' => '2200 E Show Low Lake Rd Ste 4', 'phone' => '928-555-0101']);          // a person at it
$L(102, 'Iverson Alison NP', 2, $sep, ['address' => '9 Nowhere Rd', 'phone' => '928-555-0102']);                         // a person, no practice found
$L(103, 'Ironside, Alisa', 2, $sep, ['phone' => '928-555-0103', 'address' => '1 Elm St'], ['status' => 'claimed-unverified', 'owner_user_id' => 5]);   // claimed
$L(104, 'Pinetop Clinic', 2, $sep, ['address' => '10 Main St', 'phone' => '928-555-0104']);                               // legacy, health, no source
$L(105, 'Snowflake Cafe', 4, $sep, ['address' => '20 Main St', 'phone' => '928-555-0105']);                               // legacy, food, no source
$L(106, 'Lakeside Dental Arts', 2, $sep, ['address' => '30 Main St', 'phone' => '928-555-0106']);                         // legacy with a kept source (NPI)
$pdo->exec("INSERT INTO identity_facts (record_kind, record_id, fact, value) VALUES ('listing', 106, 'phone', '9285550106')"); $pdo->exec("INSERT INTO identity_fact_sources (fact_id, kind, site) VALUES (LAST_INSERT_ID(), 'registry', 'npiregistry.cms.hhs.gov')");
$L(107, 'Show Low Pharmacy', 2, $nov, ['address' => '40 Main St', 'phone' => '928-555-0107']);                            // November, through the intake
$pdo->exec("INSERT INTO crawler_raw_index (entity_id, status, raw_payload) VALUES (107, 'published', '{}')");
$L(8807, 'Hometown Theater', 4, $sep, ['address' => '7 W Commercial St', 'phone' => '928-337-2301']);                     // the pilot
$L(108, 'Springerville Grill', 4, $sep, ['address' => '50 Main St', 'phone' => '928-555-0108'], ['src' => ['phone' => 'admin'], 'lock' => ['phone' => 1]]);   // a person edited it

$items = fn (string $rule) => array_column(array_filter(DataCleanup::collect($pdo, $rule)['items'], fn ($i) => $i['rule'] === $rule), null, 'ref');
$pp = $items('person_place');
check('person_place: the two people, the practice found for the one at its street', isset($pp['entity:101'], $pp['entity:102']) && ($pp['entity:101']['data']['practice'] ?? 0) === 100 && str_contains($pp['entity:101']['why'], 'Summit Family Medicine (#100'));
check('…no practice: kept as a record on its own', str_contains($pp['entity:102']['why'], 'no practice found') && $pp['entity:102']['guard'] === 'act');
check('…the claimed person is "Check these", never acted on', ($pp['entity:103']['guard'] ?? '') === 'check');
check('…no place is taken for a person', !array_intersect_key($pp, array_flip(['entity:100', 'entity:104', 'entity:105', 'entity:8807'])));

$lu = $items('legacy_unsourced');
check('legacy_unsourced, scope off (the default): found, shown, none acted on, saying it waits on the CEO', isset($lu['entity:104'], $lu['entity:105']) && $lu['entity:104']['guard'] === 'left' && str_contains($lu['entity:104']['why'], 'Waiting on the CEO'));
check('…a September listing with a kept source (NPI) and one that came through the intake are left alone', !isset($lu['entity:106']) && !isset($lu['entity:107']));
DataCleanup::setSetting($pdo, 'legacy_scope', 'health', 1);
$lu = $items('legacy_unsourced');
check('scope health: the clinic acted on, the cafe left (outside the scope)', $lu['entity:104']['guard'] === 'act' && $lu['entity:105']['guard'] === 'left' && str_contains($lu['entity:105']['why'], 'Outside the scope set (health)'));
DataCleanup::setSetting($pdo, 'legacy_scope', 'all', 1);
$lu = $items('legacy_unsourced');
check('scope all: the cafe acted on; the pilot\'s theater and the listing a person edited are "Check these"', $lu['entity:105']['guard'] === 'act' && ($lu['entity:8807']['guard'] ?? '') === 'check' && ($lu['entity:108']['guard'] ?? '') === 'check');
DataCleanup::setSetting($pdo, 'pilot_ids', '105, 999', 1);
check('a participant Editorial adds to pilot_ids is "Check these" too', ($items('legacy_unsourced')['entity:105']['guard'] ?? '') === 'check' && DataCleanup::pilotIds($pdo) === [8807, 105, 999]);

$pdo->exec('DROP DATABASE f9_test');
exec('rm -rf ' . escapeshellarg($lib));
echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
