<?php
// "Run a test ticket" on a Team role's page (TeamVet; Jason, 2026-11-28: each role ready to be vetted, live only once its test
// ticket passes) on a real MySQL or MariaDB: never on live; refused in words with the way forward where a role can't run yet
// (the runner off, on standby, no step, an advocate, the Founder, the audit, a step off at this ceiling); one test ticket made
// for the role's own step and run once through the real TeamRunner::run; the result and its cost shown and kept; the run undone
// or dismissed and the ticket withdrawn, so nothing stays in the queue; a refusal by Permissions is "Not passed", in words;
// the role's page (tools.php) shows Test ticket, AI access and the button, greyed with why.
// Run: php test-ticket.php <root>/app   (it resets lv_main; <root>/app = the live app plus this build, with the migrations)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_file($app . '/lib/TeamVet.php')) { fwrite(STDERR, "usage: php test-ticket.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { if (trim($s) === '') continue; $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$run(__DIR__ . '/before-schema.sql');
$H = dirname(__DIR__, 2) . '/handoffs/';
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql'] as $f) $run($f);
foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql', '2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_runs.sql', '2026-11-25_team_flag.sql'] as $f) $run($app . '/migrations/' . $f);
$pdo->exec("CREATE TABLE feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (action VARCHAR(80) PRIMARY KEY, min_level VARCHAR(20), departments VARCHAR(200), updated_by INT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS site_releases (id INT AUTO_INCREMENT PRIMARY KEY, zip_name VARCHAR(190) NOT NULL DEFAULT '', state VARCHAR(20) NOT NULL DEFAULT '', live_at DATETIME NULL)");
require_once $app . '/lib/TeamVet.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Staging.php'; require_once $app . '/admin/Access.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true);
$stage = function (bool $on) use ($here) { putenv('STAGING=' . ($on ? '1' : '0')); $here->setValue(null, null); };
$sw = function (string $state) use ($pdo) { Switches::set($pdo, 'team_runner', $state, 0, false, 'test', 1); };
$J = 'Jason (test)'; $role = fn (string $s) => TeamRoster::bySlug($pdo, $s);
$n = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();
$base = $n('SELECT COUNT(*) FROM control_gauge_tasks');   // a migration seeds one task of its own
$refused = function (callable $f) { try { $f(); return ''; } catch (InvalidArgumentException $x) { return $x->getMessage(); } };
TeamRunner::ensure($pdo); GaugeTasks::ensure($pdo);

// ---- on the live site: never
$stage(false); $sw('on');
$t('live: greyed, staging only, with the way forward', str_contains(TeamVet::off($pdo, $role('project-manager')), 'staging copy only') && str_contains(TeamVet::off($pdo, $role('project-manager')), 'open this page there'));
$m = $refused(fn () => TeamVet::run($pdo, $role('project-manager'), $J));
$t('live: pressing it anyway is refused in the same words, and nothing is made', str_contains($m, 'staging copy only') && $n('SELECT COUNT(*) FROM control_gauge_tasks') === $base && $n('SELECT COUNT(*) FROM team_runs') === 0 && TeamVet::last($pdo, 'project-manager') === null);
$t('never run: the page says so, and what counts as live', str_contains(TeamVet::lastWords($pdo, $role('project-manager')), 'Not run yet') && str_contains(TeamVet::lastWords($pdo, $role('project-manager')), 'live once its test ticket passes'));

// ---- on staging, the runner switched off
$stage(true); $sw('off');
$t('staging, runner off: greyed with the runner\'s own words (where to switch it on)', str_contains(TeamVet::off($pdo, $role('project-manager')), 'switched off') && str_contains(TeamVet::off($pdo, $role('project-manager')), 'Switches'));
$sw('on');

// ---- roles that can't run yet: why, and the way forward
$t('the Founder: never an agent', str_contains(TeamVet::off($pdo, $role('founder')), 'never as an agent'));
$t('an advocate: only looks, its release is named', str_contains(TeamVet::off($pdo, $role('advocate-guest')), 'only looks') && str_contains(TeamVet::off($pdo, $role('advocate-guest')), 'Work queue'));
$t('a role with no step yet: its release brings one, in the Work queue', str_contains(TeamVet::off($pdo, $role('qa-security')), 'no step in the runner yet') && str_contains(TeamVet::off($pdo, $role('qa-security')), 'Work queue'));
$t('the auditor: its work is the daily audit, Run the audit now', str_contains(TeamVet::off($pdo, $role('ops-reconciliation')), 'Run the audit now'));
$t('the System Architect at Planning: its step is off, the Founder raises it', str_contains(TeamVet::off($pdo, $role('system-architect')), 'off while agents work up to Planning') && str_contains(TeamVet::off($pdo, $role('system-architect')), 'Founder'));
TeamRoster::setStatus($pdo, (int) $role('project-manager')['id'], 'standby', $J, 'test');
$t('on standby: Make active first', str_contains(TeamVet::off($pdo, $role('project-manager')), 'Make active first'));
TeamRoster::setStatus($pdo, (int) $role('project-manager')['id'], 'active', $J, 'test');
$t('none of them is a dead end: every reason ends with what to do', !array_filter(['founder', 'advocate-guest', 'qa-security', 'ops-reconciliation', 'system-architect'], fn ($s) => strlen(TeamVet::off($pdo, $role($s))) < 40));

// ---- the Project Manager at Planning: its gate proposes
$t('the Project Manager can run', TeamVet::off($pdo, $role('project-manager')) === '');
$m = TeamVet::run($pdo, $role('project-manager'), $J, 'America/Phoenix');
$l = TeamVet::last($pdo, 'project-manager'); $rn = TeamRunner::get($pdo, (int) $l['run']); $tk = GaugeTasks::task($pdo, (int) $l['task']);
$t('its test ticket passes: the result and its cost, in words', str_starts_with($m, 'Passed: Proposed: ready for Building') && str_contains($m, 'Cost $0.00'), $m);
$t('it ran the real step, as the role, on a test ticket', $rn['role'] === 'project-manager' && $rn['step'] === 'gate' && $rn['state'] === 'proposed' && $tk['gauge'] === TeamVet::GAUGE && str_starts_with($tk['what'], 'Test ticket for Project Manager'));
$t('its proposal is dismissed and the ticket withdrawn: nothing stays in the queue', $rn['settled_at'] !== null && $tk['undone_at'] !== null && $tk['stage'] === 'planning' && !TeamRunner::openProposal($pdo, (int) $tk['id'], 'gate') && str_contains($m, 'nothing stays in the queue'));
$t('no open test ticket is left for anyone to work', !array_filter(GaugeTasks::queue($pdo), fn ($q) => $q['gauge'] === TeamVet::GAUGE && !in_array($q['state'], ['done', 'withdrawn'], true)));
$t('kept: passed, $0, who and when', (int) $l['ok'] === 1 && (int) $l['cost'] === 0 && $l['who'] === $J && $l['tz'] === 'America/Phoenix' && $l['step'] === 'gate');
$w = TeamVet::lastWords($pdo, $role('project-manager'));
$t('the role\'s page shows it: Passed, the cost, when, by whom, on staging', str_starts_with($w, 'Passed: ') && str_contains($w, 'Cost $0.00') && str_contains($w, 'by ' . $J) && str_contains($w, 'staging copy'), $w);
$t('it is in the role\'s History', (bool) array_filter(TeamRoster::history($pdo, (int) $role('project-manager')['id']), fn ($h) => str_contains($h['what'], 'test ticket #' . (int) $l['task'] . ' passed')));

// ---- the Founder raises the ceiling to Building: the gate acts, the hand-off works
TeamRunner::approveTree($pdo, $J); TeamRunner::setCeiling($pdo, 'building', $J, 'test');
$m = TeamVet::run($pdo, $role('project-manager'), $J);
$l2 = TeamVet::last($pdo, 'project-manager'); $rn = TeamRunner::get($pdo, (int) $l2['run']); $tk = GaugeTasks::task($pdo, (int) $l2['task']);
$t('at Building the gate acts: passed, then undone and withdrawn', str_starts_with($m, 'Passed: ') && $rn['state'] === 'done' && $rn['undone_at'] !== null && $tk['undone_at'] !== null && $tk['stage'] === 'planning' && str_contains($m, 'its run undone'), $m);
$t('a second press replaces the last result: one record a role', $n("SELECT COUNT(*) FROM team_settings WHERE k = 'vet:project-manager'") === 1 && (int) $l2['run'] > (int) $l['run']);
$t('the System Architect can run now', TeamVet::off($pdo, $role('system-architect')) === '');
$m = TeamVet::run($pdo, $role('system-architect'), $J);
$l3 = TeamVet::last($pdo, 'system-architect'); $rn = TeamRunner::get($pdo, (int) $l3['run']); $tk = GaugeTasks::task($pdo, (int) $l3['task']);
$t('the System Architect: wrote the test ticket\'s build brief, then took it back', str_starts_with($m, 'Passed: Wrote its build brief') && $rn['step'] === 'handoff' && $rn['undone_at'] !== null && trim((string) ($tk['build_brief'] ?? '')) === '' && $tk['undone_at'] !== null, $m);
$m = TeamVet::run($pdo, $role('translator'), $J);
$l4 = TeamVet::last($pdo, 'translator'); $rn = TeamRunner::get($pdo, (int) $l4['run']); $tk = GaugeTasks::task($pdo, (int) $l4['task']);
$t('the Translator: proposed a brief\'s words on a test Resolution ticket, dismissed, withdrawn', str_starts_with($m, 'Passed: Proposed 1 part') && $rn['step'] === 'brief' && $rn['settled_at'] !== null && $tk['ticket'] === 'resolution' && $tk['undone_at'] !== null, $m);
$t('a test Resolution ticket never waits for the Founder', $n("SELECT COUNT(*) FROM control_gauge_tasks WHERE ticket = 'resolution' AND undone_at IS NULL") === 0);

// ---- Permissions refuse it: Not passed, in words; still nothing stays
$fresh = new ReflectionProperty(Access::class, 'rules'); $fresh->setAccessible(true);
$pdo->exec("REPLACE INTO admin_permissions (action, min_level, departments, updated_by) VALUES ('team.gate', 'executive', '', 1)"); $fresh->setValue(null, null);
$m = TeamVet::run($pdo, $role('project-manager'), $J);
$l5 = TeamVet::last($pdo, 'project-manager'); $tk = GaugeTasks::task($pdo, (int) $l5['task']);
$t('refused by Permissions: Not passed, with why and where', str_starts_with($m, 'Not passed: ') && str_contains($m, 'Permissions: team.gate') && (int) $l5['ok'] === 0 && $l5['state'] === 'refused', $m);
$t('its test ticket is withdrawn all the same', $tk['undone_at'] !== null);
$t('the page says Not passed', str_starts_with(TeamVet::lastWords($pdo, $role('project-manager')), 'Not passed: '));
$pdo->exec("DELETE FROM admin_permissions WHERE action = 'team.gate'"); $fresh->setValue(null, null);

// ---- the runner mid-run: asked to try again, nothing made
$other = new PDO('mysql:host=127.0.0.1;dbname=lv_main', 'tv', 'tv'); $other->query("SELECT GET_LOCK('team_tick', 0)");
$before = $n('SELECT COUNT(*) FROM control_gauge_tasks');
$m = $refused(fn () => TeamVet::run($pdo, $role('project-manager'), $J));
$t('while the runner is mid-run: try again in a minute, nothing made', str_contains($m, 'try again in a minute') && $n('SELECT COUNT(*) FROM control_gauge_tasks') === $before);
$other->query("SELECT RELEASE_LOCK('team_tick')"); $other = null;
$t('and the lock is given back after every test', (int) $pdo->query("SELECT IS_FREE_LOCK('team_tick')")->fetchColumn() === 1);

// ---- cost and AI access, in words
$t('AI access: code only, $0 a step, its limit, the paid path off', str_contains(TeamVet::aiWords($pdo, $role('project-manager')), 'Code only, at $0.00 a step') && str_contains(TeamVet::aiWords($pdo, $role('project-manager')), 'limit this month is $0.00') && str_contains(TeamVet::aiWords($pdo, $role('project-manager')), 'stays off until'));
$t('AI access for a role with no step, none for an advocate or the Founder', str_contains(TeamVet::aiWords($pdo, $role('qa-security')), 'None yet') && TeamVet::aiWords($pdo, $role('advocate-guest')) === '' && TeamVet::lastWords($pdo, $role('founder')) === '' && TeamVet::aiWords($pdo, $role('founder')) === '');
$t('no test moved money or called outside: every run cost $0', $n('SELECT COALESCE(SUM(cost_micros), 0) FROM team_runs') === 0);

// ---- the role's page (tools.php)
$tools = require $app . '/admin/tools.php'; $T = $tools['config/team'];
$d = $T['get']($pdo, (int) $role('project-manager')['id']); $f = []; foreach ($d['fields'] as [$k, $v]) $f[$k] = $v;
$act = fn (array $d, string $k) => array_values(array_filter($d['acts'], fn ($a) => $a['key'] === $k))[0] ?? null;
$t('the page shows Test ticket and AI access', str_starts_with($f['Test ticket'] ?? '', 'Not passed: ') && str_contains($f['AI access'] ?? '', 'Code only'));
$t('Run a test ticket is a button on the page, ready', ($act($d, 'test')['label'] ?? '') === 'Run a test ticket' && ($act($d, 'test')['off'] ?? 'x') === '');
$m = $T['act']($pdo, (int) $role('project-manager')['id'], 'test', ['tz' => 'America/Phoenix'], 0);
$t('pressing it runs the test and answers with the result and cost', str_starts_with($m, 'Passed: ') && str_contains($m, 'Cost $0.00'), $m);
$d = $T['get']($pdo, (int) $role('qa-security')['id']);
$t('a role that can\'t run yet: the button is greyed with why', str_contains($act($d, 'test')['off'] ?? '', 'no step in the runner yet'));
$f = []; foreach ($d['fields'] as [$k, $v]) $f[$k] = $v;
$t('and the page says why in words too (a title never shows on a phone)', str_contains($f['Test ticket'] ?? '', 'Not run yet') && str_contains($f['Test ticket'] ?? '', 'It can\'t run now: It has no step in the runner yet'));
$t('its Status is true to Its steps: active, waiting for its first step', ($f['Status'] ?? '') === 'Active · waiting for its first step (its own release brings one)' && str_starts_with($f['Its steps'] ?? '', 'None yet'), $f['Status'] ?? '');
$t('the greyed button\'s reason is the same as its Test ticket row', str_contains($f['Test ticket'], $act($d, 'test')['off']));
$dp = $T['get']($pdo, (int) $role('project-manager')['id']); $fp = []; foreach ($dp['fields'] as [$k, $v]) $fp[$k] = $v;
$t('a role with a step keeps its Status words', str_starts_with($fp['Status'] ?? '', 'Active · ') && !str_contains($fp['Status'], 'waiting for its first step'));
$d = $T['get']($pdo, (int) $role('advocate-guest')['id']); $f = []; foreach ($d['fields'] as [$k, $v]) $f[$k] = $v;
$t('an advocate\'s page says it only looks, and where its release is', str_contains($f['Test ticket'] ?? '', 'only looks') && str_contains($act($d, 'test')['off'] ?? '', 'only looks'));
$d = $T['get']($pdo, (int) $role('founder')['id']);
$t('no button on the Founder\'s page', $act($d, 'test') === null);
$stage(false);
$d = $T['get']($pdo, (int) $role('project-manager')['id']); $f = []; foreach ($d['fields'] as [$k, $v]) $f[$k] = $v;
$t('on live: the button is greyed (staging only), and the last result still shows', str_contains($act($d, 'test')['off'] ?? '', 'staging copy only') && str_starts_with($f['Test ticket'] ?? '', 'Passed: ') && str_contains($f['Test ticket'], 'It can\'t run now: A test ticket runs on the staging copy only'));
$m = $refused(fn () => $T['act']($pdo, (int) $role('project-manager')['id'], 'test', [], 0));
$t('on live: the action itself refuses', str_contains($m, 'staging copy only'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
