<?php
// For board-panels.cjs: the live alarms snapshot (site-audit/results/alarms.json) and a founder, with Google Cloud's cap
// alarm as Operations::alarms raises it today (its reading in its title; its register entry titled by the part's name).
// php board-panels-seed.php <root>/app [id]: with "id", prints Google Cloud's alarm id only.
$app = $argv[1]; require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; $pdo = Database::connection();
if (($argv[2] ?? '') === 'id') { echo (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'ops:cap:google' AND cleared_at IS NULL")->fetchColumn(); exit; }
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
$pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'f@example.test', 'Jason', 'active', 'founder', '', '')");
$d = json_decode(file_get_contents(dirname(__DIR__, 2) . '/site-audit/results/alarms.json'), true);
foreach ($d['open'] as $a) ControlAlarms::raise($pdo, $a['key'], $a['process'], $a['level'], $a['level'] === 'trip' ? 'crash' : 'retry', $a['title'], (string) ($a['means'] ?? ''), 'Read the evidence on its card.', (string) ($a['reading'] ?? ''), [], false, false);
$pdo->exec("UPDATE control_alarms SET tripped_at = UTC_TIMESTAMP() WHERE level = 'trip'");
// Jason's screenshot: Places at 608 of 1,000 (61%)
ControlAlarms::raise($pdo, 'ops:cap:google', 'svc:google', 'warning', 'limit', 'Google Cloud at 61%: 608 of 1,000 checks', 'Google Places check: 608 of 1,000 checks (61%).',
    'Nothing to do yet: raise the cap if the work needs more, or pause it; it climbs to the next level or clears by itself.', '61%',
    [['Raise the cap', 'opsask', 'ops_cap:google_places_cap', ['Google Places cap (checks)', '1000']], ['Google Cloud in the register', 'peek', '/admin/config/register#svc-google', 'Google Cloud']], false);
echo 'seeded ' . count($d['open']) . "\n";
