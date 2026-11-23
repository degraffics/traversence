<?php
// For board-live.cjs: the board seeded with the live alarms snapshot (site-audit/results/alarms.json, written daily by the health job) and a founder. php board-live-seed.php <root>/app <alarms.json>
$app = $argv[1]; require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php'; $pdo = Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
$pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'f@example.test', 'Jason', 'active', 'founder', '', '')");
$d = json_decode(file_get_contents($argv[2]), true);
foreach ($d['open'] as $a) ControlAlarms::raise($pdo, $a['key'], $a['process'], $a['level'], $a['level'] === 'trip' ? 'crash' : 'retry', $a['title'], (string) ($a['means'] ?? ''), 'Read the evidence on its card, fix the cause (its key, its block, its server), then press Check and start again: it checks first, and closes it when the check passes.', (string) ($a['reading'] ?? ''), [], false, false);
$pdo->exec("UPDATE control_alarms SET tripped_at = UTC_TIMESTAMP() WHERE level = 'trip'");
echo "seeded " . count($d['open']) . "\n";
