<?php
// tools/search-tests/search-release-1-situations.php — plain PHP, prints PASS/FAIL. No network, no money, no live DB
// (SQLite in memory). Run from the repo: php tools/search-tests/search-release-1-situations.php  (or set TV_ROOT).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/SearchIndex.php';
require_once $root . '/app/lib/Situations.php';

$fails = 0;
function check(string $what, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . '  ' . $what . "\n"; if (!$ok) $fails++; }

// (a) "urgent care" is read as an urgent situation, and only by one situation
$rc = new ReflectionClass('Situations');
$seed = $rc->getConstant('SEED');
$by = []; foreach ($seed as $row) $by[$row[0]] = $row;
check('urgent-care situation exists', isset($by['urgent-care']));
check('urgent-care is urgent', ($by['urgent-care'][2] ?? '') === 'urgent');
check('urgent-care is not a danger situation', (int) ($by['urgent-care'][3] ?? 1) === 0);
check('"urgent care" is one of its phrases', in_array('urgent care', $by['urgent-care'][5] ?? [], true));
$owners = [];
foreach ($seed as $row) foreach ($row[5] as $ph) if (Situations::norm($ph) === 'urgent care') $owners[] = $row[0];
check('"urgent care" belongs to urgent-care only', $owners === ['urgent-care']);
check('Feeling sick no longer holds "walk in clinic"', !in_array('walk in clinic', $by['feel-sick'][5] ?? [], true));
check('SEED_VERSION bumped to 14', $rc->getConstant('SEED_VERSION') === '14');
$first = $by['urgent-care'][6][0] ?? [];
check('its first need names Urgent Care% first', str_starts_with((string) ($first[2] ?? ''), 'Urgent Care%'));
check('its first need requires a phone', ($first[5] ?? '') === 'phone');

// (b) the need's order ahead of weight; (C5 interim) a person isn't offered
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, is_active INT DEFAULT 1)');
$pdo->exec('CREATE TABLE search_index (kind TEXT, item_id TEXT, label TEXT, sub TEXT, url TEXT, lat REAL, lon REAL, hours TEXT, private_location INT DEFAULT 0, city TEXT, state TEXT, category_id INT, terms TEXT, weight INT DEFAULT 0, why TEXT)');
$pdo->exec('CREATE TABLE entity_metadata (entity_id INT, meta_key TEXT, meta_value TEXT)');
$pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'Urgent Care Centers'), (2, 'Clinics')");
$rows = [
    [10, 'Clinic A', 2, ' clinic a clinics ', 10],
    [11, 'Banner Urgent Care', 2, ' banner urgent care clinics ', 0],
    [12, 'QuickMed', 1, ' quickmed urgent care centers ', 0],
    [13, 'Smith, Jane', 2, ' smith jane clinics ', 20],
];
$ins = $pdo->prepare("INSERT INTO search_index (kind, item_id, label, sub, url, city, state, category_id, terms, weight) VALUES ('listing', ?, ?, '', '', 'Show Low', 'AZ', ?, ?, ?)");
$meta = $pdo->prepare("INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (?, 'phone', ?)");
foreach ($rows as [$id, $label, $cat, $terms, $w]) { $ins->execute([(string) $id, $label, $cat, $terms, $w]); $meta->execute([$id, json_encode('(928) 555-01' . $id)]); }
$m = new ReflectionMethod('Situations', 'solutions');
$m->setAccessible(true);
$need = ['cats' => 'Urgent Care%|Clinics', 'words' => 'urgent', 'kinds' => 'listing', 'requires' => 'phone'];
$res = $m->invoke(null, $pdo, $need, null, ['per' => 10], false);
$labels = array_column($res[0], 'label');
check('urgent care category first, then a clinic named urgent care, then by weight', $labels === ['QuickMed', 'Banner Urgent Care', 'Clinic A']);
check('a "Last, First" person is not offered', !in_array('Smith, Jane', $labels, true));
check('no internal sort keys leak', !array_key_exists('_pos', $res[0][0] ?? []) && !array_key_exists('_hit', $res[0][0] ?? []));

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
