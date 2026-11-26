<?php
// For mirror-words.cjs and mirror-on.php: puts the bench's staging state in one of four positions of Keep staging caught up
// (decisions/0165 §3; Jason, 2026-11-26: its words follow where it stands): off, turning_on (a fresh copy at step 475 of
// 1184, as live read 03:09 UTC), on, stuck (the copy stopped part way). Also a preview, fresh or old. Never live.
//   php mirror-seed.php <app folder> <off|turning_on|on|stuck> [fresh|old|none]
$app = rtrim($argv[1], '/'); $state = $argv[2]; $pv = $argv[3] ?? 'none';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/StagingSite.php'; require_once $app . '/lib/StagingPublish.php';
$pdo = Database::connection(); StagingSite::ensure($pdo);
$base = sys_get_temp_dir() . '/stg-bench-base'; @mkdir($base . '/public_html/traversence', 0777, true); file_put_contents($base . '/.env', "APP_ENV=staging\n");
$put = function (string $k, ?array $v) use ($pdo) { if ($v === null) { $pdo->prepare('DELETE FROM staging_state WHERE k = ?')->execute([$k]); return; }
    $pdo->prepare('INSERT INTO staging_state (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()')->execute([$k, json_encode($v)]); };
$put('setup', ['base' => $base, 'at' => date('c', time() - 86400), 'docroot' => $base . '/public_html/traversence', 'dbs' => ['main' => 'lv_mainstg']]);
$steps = [['begin'], ['files']]; for ($i = 0; $i < 1180; $i++) $steps[] = ['copy', 'main', 't' . $i]; $steps[] = ['tidy', 'main']; $steps[] = ['finish'];
$last = ['at' => date('c', time() - 3600), 'started_at' => date('c', time() - 4000), 'tz' => 'America/Phoenix', 'ok' => true, 'tables' => 1180, 'rows' => 99000, 'files' => ['files' => 12000], 'stripped' => [], 'by' => 1];
$running = ['id' => 'abc', 'started_at' => date('c', time() - 600), 'tz' => 'America/Phoenix', 'by' => 1, 'steps' => $steps, 'pos' => 475, 'log' => [], 'rows' => 0, 'tables' => 0, 'files' => [], 'stripped' => [], 'updated' => time()];
if ($state === 'off') { $put('mirror', null); $put('refresh', $running + ['finished_at' => $last['at'], 'ok' => true]); $put('last', $last); }
if ($state === 'on') { $put('mirror', ['on' => true, 'since' => date('c', time() - 1800)]); $put('refresh', $running + ['pos' => count($steps), 'finished_at' => $last['at'], 'ok' => true]); $put('last', $last); }
if ($state === 'turning_on') { $put('mirror', ['starting' => true, 'asked_at' => date('c', time() - 600), 'by' => 1]); $put('refresh', $running); $put('last', $last); }
if ($state === 'stuck') { $msg = 'The refresh stopped at "copy main t475": the database refused (test). Staging stays closed until a refresh finishes: press Start staging over to try again, or ask us at support@traversence.com.';
    $put('mirror', ['starting' => true, 'asked_at' => date('c', time() - 600), 'by' => 1]); $put('refresh', $running + ['finished_at' => date('c'), 'ok' => false, 'message' => $msg]);
    $put('last', ['at' => date('c'), 'ok' => false, 'message' => $msg, 'by' => 1, 'started_at' => $running['started_at'], 'tz' => 'America/Phoenix']); }
$k = 'preview';
if ($pv === 'none') $put($k, null);
else { $pdo->exec('CREATE TABLE IF NOT EXISTS staging_state (k VARCHAR(32) NOT NULL PRIMARY KEY, v MEDIUMTEXT NOT NULL, updated_at DATETIME NOT NULL)');
    $put($k, ['id' => 'pv1', 'at' => gmdate('c', $pv === 'fresh' ? time() - 60 : time() - 3600), 'by' => 1, 'counts' => [], 'conflicts' => 0, 'late' => 0, 'not_carried' => [], 'unknown' => [], 'rows' => 0, 'clash' => [], 'releases' => [], 'blocking' => [], 'files' => 0, 'held' => 0, 'renumber' => 0, 'tables' => [], 'minutes' => 1]); }
echo "seeded $state ($pv)\n";
