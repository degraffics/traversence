<?php
// The Control Board's 4c fixes (the session manager, 2026-11-19, from Jason's phone screenshot): an open pre-flight alarm's
// words follow its line as it stands now (a trip raised before checks were read part by part kept "the switch-on check
// has no red line for it" as its reason); the watch refreshes them from stored results, words only, never a level; and a
// trend with nothing recorded yet says "No history yet: readings start now", never "not due to be on".
// Run: mariadb < ../release-b-tests/start-schema.sql && php r4c.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Preflight.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlHistorian.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
Preflight::results($pdo);
$lines = Preflight::lines($pdo);
$put = function (string $id, array $v) use ($pdo) { $pdo->prepare('REPLACE INTO preflight_results (id, v, at) VALUES (?, ?, NOW())')->execute([$id, json_encode($v)]); };
$trip = function (string $key, string $proc, string $title) use ($pdo) { $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, reading, n_times, first_at, last_at, tripped_at) VALUES (?, ?, 'trip', 'check', ?, 'old words', 1, UTC_TIMESTAMP() - INTERVAL 11 HOUR, UTC_TIMESTAMP() - INTERVAL 11 HOUR, UTC_TIMESTAMP() - INTERVAL 11 HOUR)")->execute([$key, $proc, $title]); };
$old = 'W0 Indexer: Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).';

// 1. today's case: W0's trip kept its old words; its only red part now is its test batch (its own alarm)
$trip('pf:wf:W0', 'wf:W0', $old);
$put('wf:W0', ['s' => 'bad', 'said' => 'What holds it: Dry run: the test batch: 3 of 5 pages failed. Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).', 'fix' => [], 'at' => time(),
    'extra' => ['parts' => [['Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).', 'ok'], ['Dry run: the test batch: 3 of 5 pages failed.', 'bad'], ['Now: switched on.', null]]]]);
Preflight::alarm($pdo, 'wf:W0', $lines['wf:W0'], Preflight::results($pdo)['wf:W0'], false);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W0');
$t('W0\'s trip now names what holds it (its test batch), not the passing gate', $a && str_contains((string) $a['title'], 'held by its test batch') && !str_contains((string) $a['title'], 'no red line') && str_contains((string) $a['reading'], 'Dry run: the test batch'), $a['title'] ?? null);
$t('…still a trip, still open (its line is still red), its count unchanged', $a['level'] === 'trip' && (int) $a['n_times'] === 1 && $a['cleared_at'] === null);

// 2. a trip whose line has a red part of its own: named by that part
$trip('pf:wf:W3', 'wf:W3', 'W3: Its gate: the switch-on check has no red line for it.');
$put('wf:W3', ['s' => 'bad', 'said' => 'What holds it: Its gate: 1 red line(s) in the switch-on check: "Tavily key" (missing). Needs: Pages ✓.', 'fix' => [], 'at' => time(),
    'extra' => ['parts' => [['Needs: Pages ✓.', 'ok'], ['Its gate: 1 red line(s) in the switch-on check: "Tavily key" (missing).', 'bad']]]]);
Preflight::alarm($pdo, 'wf:W3', $lines['wf:W3'], Preflight::results($pdo)['wf:W3'], false);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W3');
$t('a trip with its own red part: titled by that part ("Tavily key")', $a && str_contains((string) $a['title'], '"Tavily key" (missing)') && $a['level'] === 'trip' && (int) $a['n_times'] === 1, $a['title'] ?? null);

// 3. a trip whose line passes now: says so, waits for Resolve
$trip('pf:wf:W5', 'wf:W5', 'W5: something old.');
$put('wf:W5', ['s' => 'ok', 'said' => 'Every part passes.', 'fix' => [], 'at' => time(), 'extra' => ['parts' => [['Needs: ✓.', 'ok']]]]);
Preflight::alarm($pdo, 'wf:W5', $lines['wf:W5'], Preflight::results($pdo)['wf:W5'], false);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W5');
$t('a trip whose check passes now closes itself as Recovered (decisions/0175 §3)', $a === null && str_starts_with((string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'pf:wf:W5' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'Recovered:'));

// 4. the refresh never changes a level: a warning stays a warning however long it's been
$pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at) VALUES ('pf:wf:W6', 'wf:W6', 'warning', 'check', 'W6: old', 1, UTC_TIMESTAMP() - INTERVAL 5 HOUR, UTC_TIMESTAMP() - INTERVAL 5 HOUR)")->execute();
$put('wf:W6', ['s' => 'bad', 'said' => 'What holds it: Its gate: 1 red line(s): "Gemini key" (missing).', 'fix' => [], 'at' => time(), 'extra' => ['parts' => [['Its gate: 1 red line(s): "Gemini key" (missing).', 'bad']]]]);
Preflight::alarm($pdo, 'wf:W6', $lines['wf:W6'], Preflight::results($pdo)['wf:W6'], false);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W6');
$t('the refresh rewrites a warning\'s words but never its level (no new trip, nothing stopped)', $a && $a['level'] === 'warning' && empty($a['tripped_at']) && str_contains((string) $a['title'], 'Gemini key'), [$a['level'] ?? null, $a['title'] ?? null]);
// and a warning whose line passes now is cleared, as a good reading always did
$put('wf:W6', ['s' => 'ok', 'said' => 'Passes.', 'fix' => [], 'at' => time(), 'extra' => ['parts' => []]]);
Preflight::alarm($pdo, 'wf:W6', $lines['wf:W6'], Preflight::results($pdo)['wf:W6'], false);
$t('…and a warning whose check passes now is cleared', ControlAlarms::openOf($pdo, 'pf:wf:W6') === null);

// 5. the watch does it, every 5 minutes, for every open pre-flight alarm
$src = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch refreshes every open pre-flight alarm\'s words from stored results (event false)', str_contains($src, "akey LIKE 'pf:%'") && str_contains($src, 'Preflight::alarm($pdo, $lid, $pl[$lid], $pr[$lid], false'));

// 6. a trend with nothing recorded: "No history yet", never "not due to be on"
$pdo->exec('DROP TABLE IF EXISTS control_readings'); $pdo->exec('DROP TABLE IF EXISTS control_daily');
$tr = ControlHistorian::trend($pdo, 'sys:engine', '24h'); $h = ControlHistorian::html($tr, 'sys:engine');
$t('a system with no readings yet: "No history yet: readings start now", in the words and over the empty bars', $tr['history'] === false && str_contains($h, 'No history yet: readings start now') && str_contains($h, '<p class="cb-tre">No history yet') && !str_contains($h, 'not due'), mb_substr(strip_tags($h), 0, 160));
$pdo->prepare("INSERT INTO control_readings (proc, reading, value, state, at) VALUES ('svc:tavily', 'state', 5, 'off', ?)")->execute([gmdate('Y-m-d H:i:s', time() - 30 * 3600)]);
$h2 = ControlHistorian::html(ControlHistorian::trend($pdo, 'svc:tavily', '24h'), 'svc:tavily');
$t('…one switched off all along: says so, with no uptime to count (never 0%)', str_contains($h2, 'switched off or in maintenance all this time') && !str_contains($h2, 'No history yet'), mb_substr(strip_tags($h2), 0, 160));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
