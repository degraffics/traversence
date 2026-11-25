<?php
// No Undo on any alarm's acknowledgement (the Control Board session, 2026-11-25; CLAUDE.md #1314: a record someone entered is
// edited, never undone; Undo only for an action): after Acknowledge, a trip's row on the board and its module card offer no
// Undo; the row's state line says who saw it and when; Pulse's Reopen reopens the case and leaves the acknowledgement as it was.
// Run: php no-undo.php <root>/app (on the start schema; it runs board-panels-seed.php itself when no trip is there)
$app = $argv[1]; require $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Pulse.php';
$pass = 0; $fail = 0; $t = function ($n, $ok) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n\n"; } };
$q = "SELECT id FROM control_alarms WHERE level = 'trip' AND cleared_at IS NULL ORDER BY id LIMIT 1";
$id = 0; try { $id = (int) $pdo->query($q)->fetchColumn(); } catch (Throwable $x) {}
if (!$id) { shell_exec('php ' . escapeshellarg(__DIR__ . '/board-panels-seed.php') . ' ' . escapeshellarg($app) . ' 2>/dev/null'); $id = (int) $pdo->query($q)->fetchColumn(); }
$t('a trip to acknowledge', $id > 0);
ControlAlarms::ack($pdo, $id, 7, 'Jason');
$html = shell_exec('php ' . escapeshellarg(__DIR__ . '/board-page.php') . ' ' . escapeshellarg($app) . ' page 2>/dev/null');
$row = preg_match('#<li id="cb-alarm-' . $id . '".*?</li>#s', (string) $html, $m) ? $m[0] : '';
$t('the board drew the acknowledged trip', $row !== '');
$t('its row offers no Undo (no unack button)', $row !== '' && !str_contains($row, 'data-cb="unack"') && !preg_match('#>Undo<#', $row));
$t('…and no Acknowledge again', !str_contains($row, 'data-cb="ack"'));
$t('its state line: "Seen by Jason, <time>"', (bool) preg_match('#data-tv-label="Seen by">Jason, <time datetime="[^"]+" data-tv-time>#', $row));
$card = (string) file_get_contents($app . '/admin/module-cards.php');
$t('the module card: no "Undo Acknowledge" left', !str_contains($card, "'Undo Acknowledge'"));
// Pulse's own tables, as the site has them (a case's mark and its notes); Audit's log too
$pdo->exec("CREATE TABLE IF NOT EXISTS pulse_marks (item_key VARCHAR(80) PRIMARY KEY, case_no INT AUTO_INCREMENT UNIQUE, done_at DATETIME NULL, done_by INT NULL, assigned_to INT NULL, snooze_until DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS pulse_notes (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(80), user_id INT, kind VARCHAR(20), body TEXT, to_email VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
try { Pulse::mark($pdo, 'alarm:' . $id, 'done', 7);
Pulse::mark($pdo, 'alarm:' . $id, 'undo', 7); } catch (Throwable $x) { echo '  (Pulse: ' . $x->getMessage() . ")\n"; }
$a = $pdo->query('SELECT ack_at, ack_name FROM control_alarms WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
$t('Pulse\'s Reopen leaves the acknowledgement (who and when kept)', $a['ack_at'] !== null && $a['ack_name'] === 'Jason');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
