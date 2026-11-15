<?php
// Start the system's tests (decisions/0163 §10 note, 2026-11-15): mariadb < start-schema.sql && php start.php <app>; .env four folders up from lib/. 33 tests.
declare(strict_types=1);
$app = $argv[1];
final class Access { public static $ok = true; public static function can($p, $o = []) { return [self::$ok, 'Executives only']; } public static function staff() { return ['name' => 'Jason']; } }
require $app . '/lib/Database.php'; require $app . '/lib/SystemStart.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$wf = fn ($w) => $pdo->query("SELECT paused, paused_why FROM crawl_workflows WHERE wf = '$w'")->fetch(PDO::FETCH_ASSOC);
$t('never pressed: idle, tick does nothing', SystemStart::tick($pdo)['state'] === 'idle');
$t('nothing in Pulse before the press', SystemStart::pulseItems($pdo) === []);
$st = SystemStart::start($pdo, 7, 'Jason');
Automation::forget();
echo "  state: {$st['state']}\n"; foreach ($st['items'] as $id => $it) echo "    $id: {$it['s']} — " . mb_substr($it['why'], 0, 150) . "\n";
$t('the run is under way', in_array($st['state'], ['running', 'stopped'], true), $st['state']);
$t('keys missing are held, not stopping the rest', $st['items']['keys']['s'] === 'held');
$t('watching held without its keys', $st['items']['watch']['s'] === 'held');
$t('Railway held without its keys, with a Pulse step naming the setting', $st['items']['railway']['s'] === 'held' && (bool) array_filter($st['asks'], fn ($a) => str_contains($a[1], '*/5 * * * *')));
$t('W3, paused before the press, now waits its turn (old reason in the log)', $wf('W3')['paused_why'] === SystemStart::WAIT, $wf('W3'));
$master = Automation::master($pdo);
if ($st['items']['master']['s'] === 'done') {
    $t('master on', $master === null);
    $on = array_keys(array_filter($st['items'], fn ($i) => $i['s'] === 'on'));
    $t('exactly one workflow running', count($on) === 1, $on);
    $t('the rest still paused, waiting their turn', (int) $wf('W12')['paused'] === 1 && $wf('W12')['paused_why'] === SystemStart::WAIT);
} else {
    $t('master held by a red line for everything, named', $master !== null && $st['items']['master']['why'] !== '', $st['items']['master']);
    $t('workflows all wait', (int) $wf('W1')['paused'] === 1);
}
$p = SystemStart::pulseItems($pdo);
$t('one Pulse case, "Starting the system"', count($p) === 1 && $p[0][0] === 'ops:start' && $p[0][2] === 'Starting the system', $p);
$steps = SystemStart::pulseSteps($pdo, fn ($a, $b, $c = null, $d = false, $e = '') => [$a, $b, $c, $e]);
$t('its steps end with Look again', end($steps)[2][1] === 'ops_start_tick');
$t('a key step names where it goes', (bool) array_filter($steps, fn ($s) => str_starts_with($s[0], 'Add ') && (str_contains($s[1], '.env') || str_contains($s[1], 'Railway'))));
// a second press is refused while running
$no = false; try { SystemStart::start($pdo, 7, 'Jason'); } catch (RuntimeException $e) { $no = true; }
$t('a second press refused while it runs', $no);
// the gate: wait, then pass after hours
$t('gate waits at first', SystemStart::gate($pdo, 'wf:W4', time())[0] === 'wait', SystemStart::gate($pdo, 'wf:W4', time()));
$t('gate passes after start_gate_hours clean', SystemStart::gate($pdo, 'wf:W4', time() - 7 * 3600)[0] === 'pass', SystemStart::gate($pdo, 'wf:W4', time() - 7 * 3600));
require_once $app . '/lib/Scheduler.php'; Scheduler::ensure($pdo);
$pdo->exec("INSERT INTO job_runs (job, started_at, ok) VALUES ('journeys_tick', NOW(), 1), ('journeys_tick', NOW(), 1)");
$t('gate passes on 2 good runs', SystemStart::gate($pdo, 'wf:W7', time() - 600)[0] === 'pass', SystemStart::gate($pdo, 'wf:W7', time() - 600));
$pdo->exec("INSERT INTO job_runs (job, started_at, ok) VALUES ('journeys_tick', NOW(), 0), ('journeys_tick', NOW(), 0)");
$t('gate fails on a rise in errors', SystemStart::gate($pdo, 'wf:W7', time() - 600)[0] === 'fail', SystemStart::gate($pdo, 'wf:W7', time() - 600));
// the walk: master on once nothing holds everything; one at a time; a red line for W2's service holds only W2
SystemStart::pauseAll($pdo, 7, 'Jason'); $pdo->exec("DELETE FROM job_runs");
SystemStart::$check = [['line' => 'Google Places (W2)', 'ok' => false, 'why' => 'No key: GOOGLE_PLACES_KEY (Railway).', 'fix' => null, 'scope' => 'W2', 'stage' => '']];
$st = SystemStart::start($pdo, 7, 'Jason'); Automation::forget();
$t('master on when nothing holds everything', $st['items']['master']['s'] === 'done' && Automation::master($pdo) === null, $st['items']['master']);
$t('W1 first, alone', $st['items']['wf:W1']['s'] === 'on' && (int) $wf('W1')['paused'] === 0 && (int) $wf('W4')['paused'] === 1, [$st['items']['wf:W1'], $wf('W4')]);
$st = SystemStart::tick($pdo); Automation::forget();
$t('the next waits while W1\'s gate is open', $st['items']['wf:W1']['s'] === 'on' && $st['items']['wf:W2']['s'] === 'pending', $st['items']['wf:W1']);
$pdo->exec("INSERT INTO job_runs (job, started_at, ok) VALUES ('listings_tick', NOW(), 1), ('listings_tick', NOW(), 1)");
$st = SystemStart::tick($pdo); Automation::forget();
$t('W1 passes on 2 good runs; W2 held by its own red line; W3 starts', $st['items']['wf:W1']['s'] === 'done' && $st['items']['wf:W2']['s'] === 'held' && $st['items']['wf:W3']['s'] === 'on', [$st['items']['wf:W2'], $st['items']['wf:W3']]);
$t('W2 stays paused', (int) $wf('W2')['paused'] === 1);
$t('a held item is a step in Pulse', (bool) array_filter($st['asks'], fn ($a) => str_starts_with($a[0], 'Google Places')));
// a gate that fails: W3 errors
$pdo->exec("INSERT INTO job_runs (job, started_at, ok) VALUES ('page_read_tick', NOW(), 0), ('vectors_tick', NOW(), 0)");
$st = SystemStart::tick($pdo); Automation::forget();
$t('a failed gate stops the sequence, pauses that one, holds the rest', $st['state'] === 'stopped' && $st['items']['wf:W3']['s'] === 'stopped' && (int) $wf('W3')['paused'] === 1 && $st['items']['wf:W4']['s'] === 'pending', [$st['state'], $st['items']['wf:W3']]);
$p = SystemStart::pulseItems($pdo);
$t('one case, critical, with Try again and Leave it', $p[0][1] === 'critical' && (bool) array_filter($st['asks'], fn ($a) => ($a[2][1] ?? '') === 'ops_start_skip'));
$pdo->exec("DELETE FROM job_runs");
SystemStart::pulseDo($pdo, 'ops_start_skip', 7, 'Jason'); Automation::forget(); $st = SystemStart::state($pdo);
$t('Leave it: W3 stays paused, W4 starts', $st['state'] === 'running' && (int) $wf('W3')['paused'] === 1 && $st['items']['wf:W4']['s'] === 'on', [$st['state'], $st['items']['wf:W4']]);
// a person pauses the next one meanwhile: their switch stands
Automation::setWorkflow($pdo, 'W5', true, 'Jason: not yet', 7, 'Jason'); Automation::forget();
$pdo->exec("INSERT INTO job_runs (job, started_at, ok) VALUES ('guide_finds_tick', NOW(), 1), ('guide_finds_tick', NOW(), 1)");
$st = SystemStart::tick($pdo); Automation::forget();
$t('a person\'s pause since the start holds it, the next goes on', $st['items']['wf:W5']['s'] === 'held' && (int) $wf('W5')['paused'] === 1 && $st['items']['wf:W6']['s'] === 'on', [$st['items']['wf:W4'], $st['items']['wf:W5'], $st['items']['wf:W6']]);
SystemStart::$check = null;
// Pause everything
Access::$ok = true;
$say = SystemStart::pauseAll($pdo, 7, 'Jason'); Automation::forget();
$t('Pause everything: master off', Automation::master($pdo) !== null, $say);
$t('Pause everything: the run paused, logged', SystemStart::state($pdo)['state'] === 'paused' && (int) $pdo->query("SELECT COUNT(*) FROM automation_log WHERE ref = 'pause_all'")->fetchColumn() >= 1);
$t('Railway untouched without its keys, said so', str_contains($say, 'keys aren'));
$t('no Pulse case once paused', SystemStart::pulseItems($pdo) === []);
// staging refuses
$rp = new ReflectionProperty('Staging', 'here'); $rp->setAccessible(true); $rp->setValue(null, true);
$no = false; try { SystemStart::start($pdo, 7, 'Jason'); } catch (RuntimeException $e) { $no = str_contains($e->getMessage(), 'live site only'); }
$t('staging refuses the press', $no);
$html = SystemStart::panel($pdo, 'x', true, '');
$t('on staging the buttons are greyed with the reason', substr_count($html, 'disabled title=') === 2 && str_contains($html, 'live site only'));
$rp->setValue(null, false);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
