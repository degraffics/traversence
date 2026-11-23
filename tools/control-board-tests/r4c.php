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

// 1–3. since the control board's build (step 4) a workflow's own line is a roll-up and raises nothing (the one engine,
// 02 §2.2): W0's, W3's and W5's old trips close as roll-ups when their lines are read, whatever their words
foreach (['wf:W0' => 'bad', 'wf:W3' => 'bad', 'wf:W5' => 'ok'] as $lid => $st) {
    $trip('pf:' . $lid, $lid, $old);
    $put($lid, ['s' => $st, 'said' => 'What holds it: Its gate: 1 red line(s) in the switch-on check: "Tavily key" (missing).', 'fix' => [], 'at' => time(), 'extra' => ['parts' => [['Its gate: 1 red line(s): "Tavily key" (missing).', $st]]]]);
    Preflight::alarm($pdo, $lid, $lines[$lid], Preflight::results($pdo)[$lid], false);
    $t('an old trip on ' . $lid . '\'s line closes as a roll-up', ControlAlarms::openOf($pdo, 'pf:' . $lid) === null && str_contains((string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'pf:$lid' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'only adds up its parts'));
}

// 4. the refresh never changes a level: a part's warning (a need's line) stays a warning however long it's been
$nid = ''; foreach (array_keys($lines) as $k) if (str_starts_with((string) $k, 'nd:')) { $nid = (string) $k; break; }
$pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at) VALUES (?, ?, 'warning', 'check', 'old', 1, UTC_TIMESTAMP() - INTERVAL 5 HOUR, UTC_TIMESTAMP() - INTERVAL 5 HOUR)")->execute(['pf:' . $nid, Preflight::procOf($nid)]);
$put($nid, ['s' => 'bad', 'said' => 'What holds it: "Gemini key" (missing).', 'fix' => [], 'at' => time(), 'extra' => ['parts' => [['"Gemini key" (missing).', 'bad']]]]);
Preflight::alarm($pdo, $nid, $lines[$nid], Preflight::results($pdo)[$nid], false);
$a = ControlAlarms::openOf($pdo, 'pf:' . $nid);
$t('the refresh rewrites a warning\'s words but never its level (no new trip, nothing stopped)', $a && $a['level'] === 'warning' && empty($a['tripped_at']) && str_contains((string) $a['title'], 'Gemini key'), [$a['level'] ?? null, $a['title'] ?? null]);
$put($nid, ['s' => 'ok', 'said' => 'Passes.', 'fix' => [], 'at' => time(), 'extra' => ['parts' => []]]);
Preflight::alarm($pdo, $nid, $lines[$nid], Preflight::results($pdo)[$nid], false);
$t('…and a warning whose check passes now is cleared', ControlAlarms::openOf($pdo, 'pf:' . $nid) === null);

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
