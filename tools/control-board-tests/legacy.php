<?php
// control-board-legacy (the session manager, 2026-11-19): a pre-flight trip from before 3c (no trip time) is settled by the
// watch to what a fresh raise would be today: closed if its check passes, its notice or warning if amber, still a trip
// timed from now if red, each logged by the system; an alarm's title is its red part, else its amber part, never a
// passing one (W0 read "the switch-on check has no red line for it"); a trip with its time keeps its level (words only);
// W0's test batch is asked again when its answer waits or it was short, and a short one names the indexer's version.
// Run: mariadb < ../release-b-tests/start-schema.sql && php legacy.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Preflight.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/Automation.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
Preflight::results($pdo); $lines = Preflight::lines($pdo);
$old = function (string $key, string $proc, string $title, bool $timed = false) use ($pdo) { $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, reading, n_times, first_at, last_at, tripped_at) VALUES (?, ?, 'trip', 'check', ?, 'old words', 1, UTC_TIMESTAMP() - INTERVAL 8 HOUR, UTC_TIMESTAMP() - INTERVAL 8 HOUR, " . ($timed ? 'UTC_TIMESTAMP() - INTERVAL 8 HOUR' : 'NULL') . ")")->execute([$key, $proc, $title]); };
$gate = ['Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).', 'ok'];
$o = fn (string $s, array $parts) => ['s' => $s, 'said' => implode(' ', array_column($parts, 0)), 'fix' => [], 'at' => time(), 'extra' => ['parts' => $parts]];

// 1. W0 today: its gate passes, its test batch is amber; the old trip becomes a notice titled by the batch
$old('pf:wf:W0', 'wf:W0', 'W0 Indexer: Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).');
Preflight::alarm($pdo, 'wf:W0', $lines['wf:W0'], $o('warn', [$gate, ['Dry run: the test batch: asked just now; the indexer sends it at its next check-in.', 'warn'], ['Now: switched on.', null]]), false, true);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W0');
$t('an old trip whose check is amber today becomes its notice, no longer red', $a && $a['level'] === 'notice' && empty($a['tripped_at']), [$a['level'] ?? null]);
$t('…titled by its amber part (the test batch), never its passing gate', str_contains((string) $a['title'], 'Dry run: the test batch') && !str_contains((string) $a['title'], 'no red line'), $a['title'] ?? null);
$t('…and the logbook says the system settled it', (bool) array_filter(ControlLog::feed($pdo, 'wf:W0'), fn ($e) => str_contains($e[4], 'An old trip settled: amber today') && $e[5] === 'the system'));

// 2. an old trip whose check is red today: still red, its red part the title, timed from now if it holds something switched on
$old('pf:wf:W3', 'wf:W3', 'W3: something from yesterday.');
$red = ['Its gate: 1 red line(s) in the switch-on check: "Common Crawl (W3)" (Held: Issue #25).', 'bad'];
Preflight::alarm($pdo, 'wf:W3', $lines['wf:W3'], $o('bad', [['Needs: Pages ✓.', 'ok'], $red]), false, true);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W3');
$req = (bool) ($lines['wf:W3'][2] ?? false); $on = Automation::master($pdo) === null && Automation::wfOff($pdo, 'W3') === null;
$want = $req && $on ? 'trip' : 'warning';
$t('an old trip whose check is red today: ' . $want . ' (as a fresh raise would be), titled by its red part', $a && $a['level'] === $want && str_contains((string) $a['title'], '"Common Crawl (W3)"'), [$a['level'] ?? null, $a['title'] ?? null]);
$t('…a trip now carries its time (from now), a warning none', $want === 'trip' ? !empty($a['tripped_at']) && strtotime($a['tripped_at'] . ' UTC') > time() - 120 : empty($a['tripped_at']), $a['tripped_at'] ?? null);

// …and with the system and W3 switched on, a required red line stays a trip, timed from now
if ($req) {
    Automation::set($pdo, 'master', 'all', false, 'test', 0, 'test'); Automation::setWorkflow($pdo, 'W3', false, 'test', 0, 'test'); Automation::forget();
    $pdo->exec("DELETE FROM control_alarms WHERE akey = 'pf:wf:W3'"); $old('pf:wf:W3', 'wf:W3', 'W3: something from yesterday.');
    Preflight::alarm($pdo, 'wf:W3', $lines['wf:W3'], $o('bad', [['Needs: Pages ✓.', 'ok'], $red]), false, true);
    $a = ControlAlarms::openOf($pdo, 'pf:wf:W3');
    $t('switched on: an old red trip stays a trip, timed from now, titled by its red part', $a && $a['level'] === 'trip' && !empty($a['tripped_at']) && strtotime($a['tripped_at'] . ' UTC') > time() - 120 && str_contains((string) $a['title'], '"Common Crawl (W3)"'), [$a['level'] ?? null, $a['tripped_at'] ?? null]);
    Automation::set($pdo, 'master', 'all', true, 'test', 0, 'test'); Automation::setWorkflow($pdo, 'W3', true, 'test', 0, 'test'); Automation::forget();
}

// 3. an old trip whose check passes today: closed
$old('pf:t:vectors', 't:vectors', 'The vector index: old.');
Preflight::alarm($pdo, 't:vectors', $lines['t:vectors'], $o('ok', [['Supabase: Ready.', 'ok']]), false, true);
$t('an old trip whose check passes today is closed, its words saying so', ControlAlarms::openOf($pdo, 'pf:t:vectors') === null && str_contains((string) $pdo->query("SELECT cleared_how FROM control_alarms WHERE akey = 'pf:t:vectors'")->fetchColumn(), 'Its check passed'));

// 4. a trip with its time (after 3c) keeps its level: only its words follow the check
$old('pf:wf:W5', 'wf:W5', 'W5: old.', true);
Preflight::alarm($pdo, 'wf:W5', $lines['wf:W5'], $o('warn', [['Dry run: amber.', 'warn']]), false, true);
$a = ControlAlarms::openOf($pdo, 'pf:wf:W5');
$t('a trip with its trip time is never settled by the watch: still a trip, only its words now', $a && $a['level'] === 'trip' && !empty($a['tripped_at']) && str_contains((string) $a['title'], 'Dry run: amber'), [$a['level'] ?? null, $a['title'] ?? null]);

// 5. the watch: old trips first, a waiting answer read back, W0's short batch asked again
$src = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch tests old trips first and settles them', str_contains($src, "ORDER BY (level = 'trip' AND tripped_at IS NULL) DESC") && str_contains($src, 'Preflight::alarm($pdo, $lid, $pl[$lid], $pr[$lid], false, $legacy)'));
$t('…reads back a line left waiting over 2 minutes, and asks W0\'s test batch again after half an hour red', str_contains($src, "(\$st === 'wait' && \$at < time() - 120)") && str_contains($src, "(\$lid === 'rw:batch' && \$st === 'bad' && \$at < time() - 1800)"));
$pf = file_get_contents($app . '/lib/Preflight.php');
$t('a short test batch names the indexer\'s version; under 1.1.1 it says to redeploy, with Railway\'s link', str_contains($pf, "Railway still runs indexer ' . \$gv") && str_contains($pf, "version_compare(\$gv, '1.1.1', '<') ? [self::fixUrl('Railway: the w0-indexer service → Deployments'") && !str_contains($pf, "the worker's settings didn't reach it"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
