<?php
// The Team's daily audit (Piece 2, P2-4; handoffs/piece2-team.md §6) on a real MySQL or MariaDB, as staging runs it: its
// sources (a Site health finding from the cached report, a late job, an open alarm); one Audit ticket per finding, Found,
// owned by the Operations & Reconciliation Manager, tiered by 0179's rules (a payment finding Tier 3, a phone-layout one
// Tier 1, the rest Tier 2); run twice, nothing doubled; on its second look a day later one still there is Confirmed and opens
// a Work ticket (Tier 1–2) or a Resolution (Tier 3), one gone is cleared by its own check; the Audit closes with the ticket it
// opened; each opened ticket has Undo (withdrawn); live and switched off do nothing; the job is QUIET and staging only.
// Run: php audit.php <root>/app   (it resets lv_main)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php audit.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { if (trim($s) === '') continue; $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$run(__DIR__ . '/before-schema.sql'); $H = dirname(__DIR__, 2) . '/handoffs/';
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql'] as $f) $run($f);
foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql', '2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_runs.sql'] as $f) $run($app . '/migrations/' . $f);
$pdo->exec("CREATE TABLE feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (action VARCHAR(80) PRIMARY KEY, min_level VARCHAR(20), departments VARCHAR(200), updated_by INT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS control_alarms (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), level VARCHAR(10), cleared_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS site_health_marks (finding_id VARCHAR(60) PRIMARY KEY, state VARCHAR(10), sig VARCHAR(64), note VARCHAR(255), by_user INT, at DATETIME)");
require_once $app . '/lib/TeamRunner.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Staging.php'; require_once $app . '/lib/PageCache.php'; require_once $app . '/lib/Scheduler.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true);
$stage = function (bool $on) use ($here) { putenv('STAGING=' . ($on ? '1' : '0')); $here->setValue(null, null); };
$sh = function (array $findings) use ($pdo) { PageCache::put($pdo, 'sitehealth:now', 3600, ['ok' => true, 'at' => gmdate('c'), 'run_url' => '', 'error' => '', 'findings' => $findings]); };
$f = fn ($id, $title, $detail) => ['id' => $id, 'sig' => 's', 'kind' => 'page', 'rule' => 'R1', 'title' => $title, 'detail' => $detail, 'page' => '/x', 'who' => [], 'paths' => [], 'blocks' => 1, 'severity' => 'alert', 'shot_url' => '', 'proposal' => '', 'proposal_url' => '', 'verdict' => '', 'decided' => false, 'first_seen' => '', 'last_seen' => ''];
$J = 'Jason (test)';
// the sources: two Site health findings, a late job, an open alarm
$sh([$f('f-aaaaaa', 'Checkout shows the wrong payment total', 'The order page sums tax twice'), $f('f-bbbbbb', 'The card is cut off at 390', 'Phone layout: the Link button overflows')]);
Scheduler::ensure($pdo); $pdo->exec("INSERT INTO job_runs (job, started_at, finished_at, ok, note) VALUES ('listings_tick', NOW() - INTERVAL 30 HOUR, NOW() - INTERVAL 30 HOUR, 1, 'ok')");
$pdo->exec("INSERT INTO control_alarms (title, level) VALUES ('W1 Listings is paused', 'warning')");
$tasks = fn () => $pdo->query("SELECT * FROM control_gauge_tasks WHERE ticket = 'audit' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// live and off: nothing
$stage(false); Switches::set($pdo, 'team_runner', 'on', 0, false, 't', 1);
$t('on live: the audit does nothing and logs nothing', TeamRunner::audit($pdo) === '' && !$tasks());
$stage(true); Switches::set($pdo, 'team_runner', 'off', 0, false, 't', 1);
$t('switched off: says so, opens nothing', str_contains(TeamRunner::audit($pdo), 'switched off') && !$tasks());
Switches::set($pdo, 'team_runner', 'on', 0, false, 't', 1);
// the first run: one Audit ticket per finding, at Found
$out = TeamRunner::audit($pdo);
$a = $tasks(); $by = []; foreach ($a as $x) $by[$x['audit_key']] = $x;
$t('four findings, four Audit tickets at Found', count($a) === 4 && count(array_filter($a, fn ($x) => $x['stage'] === 'found')) === 4, $out);
$t('… each keyed by its finding: Site health, the late job, the alarm', isset($by['finding:f-aaaaaa'], $by['finding:f-bbbbbb'], $by['job:listings_tick'], $by['alarm:1']), array_keys($by));
$t('… owned by the Operations & Reconciliation Manager', count(array_filter($a, fn ($x) => $x['owner'] === 'system · Operations & Reconciliation Manager')) === 4);
$t('tiers by 0179: a payment finding Tier 3 (money), a phone-layout one Tier 1, the others Tier 2',
    (int) $by['finding:f-aaaaaa']['tier'] === 3 && str_contains($by['finding:f-aaaaaa']['why'], 'Money') && (int) $by['finding:f-bbbbbb']['tier'] === 1 && (int) $by['job:listings_tick']['tier'] === 2 && (int) $by['alarm:1']['tier'] === 2,
    array_map(fn ($x) => (int) $x['tier'], $by));
$t('the late job says how late, in hours', str_contains($by['job:listings_tick']['why'], 'hours ago'));
$t('the run says what it found: "4 findings · 4 new"', str_contains($out, '4 findings · 4 new · 0 confirmed · 0 cleared'), $out);
$t('run again the same day: nothing doubled', str_contains(TeamRunner::audit($pdo), '0 new · 0 confirmed') && count($tasks()) === 4);
// a day on: the alarm cleared (gone), the rest still there
$pdo->exec("UPDATE control_gauge_tasks SET at = at - INTERVAL 21 HOUR WHERE ticket = 'audit'"); $pdo->exec("UPDATE control_alarms SET cleared_at = NOW()");
$out = TeamRunner::audit($pdo); $a = $tasks(); $by = []; foreach ($a as $x) $by[$x['audit_key']] = $x;
$t('the second look: the alarm that\'s gone is cleared by its own check', $by['alarm:1']['cleared_at'] !== null && str_contains((string) $by['alarm:1']['done_how'], 'cleared by its own check'), $out);
$t('… the three still there are Confirmed', count(array_filter($a, fn ($x) => $x['stage'] === 'confirmed')) === 3);
$kids = $pdo->query("SELECT * FROM control_gauge_tasks WHERE parent_id IS NOT NULL AND ticket <> 'audit' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$k = []; foreach ($kids as $x) $k[(int) $x['parent_id']] = $x;
$t('the Tier 3 finding opens a Resolution (to be briefed for the Founder)', $k[(int) $by['finding:f-aaaaaa']['id']]['ticket'] === 'resolution' && $k[(int) $by['finding:f-aaaaaa']['id']]['stage'] === 'brief');
$t('Tier 1 and 2 open Work tickets at Planning, their tier and parent kept', $k[(int) $by['finding:f-bbbbbb']['id']]['ticket'] === 'work' && (int) $k[(int) $by['finding:f-bbbbbb']['id']]['tier'] === 1 && $k[(int) $by['job:listings_tick']['id']]['stage'] === 'planning');
$t('the run counts it: "1 cleared as noise", "3 confirmed"', str_contains($out, '3 confirmed · 1 cleared as noise'), $out);
// the Audit closes with the ticket it opened
$child = (int) $k[(int) $by['job:listings_tick']['id']]['id']; $pdo->exec("UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP(), done_how = 'Done: fixed' WHERE id = $child");
GaugeTasks::queue($pdo);
$t('the Audit closes once the ticket it opened is done', GaugeTasks::task($pdo, (int) $by['job:listings_tick']['id'])['cleared_at'] !== null);
// Undo a ticket the audit opened
$rid = (int) $pdo->query("SELECT id FROM team_runs WHERE step = 'audit' AND task_id = " . (int) $k[(int) $by['finding:f-bbbbbb']['id']]['id'])->fetchColumn();
$t('Undo a ticket the audit opened: withdrawn', str_starts_with(TeamRunner::undo($pdo, $rid, $J), 'Undone') && GaugeTasks::task($pdo, (int) $k[(int) $by['finding:f-bbbbbb']['id']]['id'])['undone_at'] !== null);
// Permissions and the role
$pdo->exec("INSERT INTO admin_permissions (action, min_level, departments, updated_by) VALUES ('team.audit', 'executive', '', 1)");
$fr = new ReflectionProperty(Access::class, 'rules'); $fr->setAccessible(true); $fr->setValue(null, null);
$t('Permissions above its level: refused, saying what\'s needed', str_contains(TeamRunner::audit($pdo), 'Needs an Executive'));
$t('the tier rules: privacy and nations are Tier 3', TeamRunner::tier('a test account counted in likes')[0] === 3 && TeamRunner::tier('a sacred site name shown')[1] === 'nations' && TeamRunner::tier('the FAQ answer has a typo')[0] === 1);
$t('the audit job: daily, set up by itself, QUIET, staging only', Scheduler::JOBS['team_audit_tick'][2] === 'daily' && in_array('team_audit_tick', Scheduler::AUTO, true) && in_array('team_audit_tick', Scheduler::QUIET, true)
    && preg_match('/if \(!Staging::here\(\)\) exit\(0\);[\s\S]*Scheduler::run/', (string) file_get_contents($app . '/scripts/team_audit_tick.php')) === 1);
$tools = require $app . '/admin/tools.php'; $T = $tools['config/team'];
$d = ($T['get'])($pdo, (int) TeamRoster::bySlug($pdo, 'ops-reconciliation')['id']); $au = array_values(array_filter($d['acts'], fn ($x) => $x['key'] === 'audit'))[0] ?? null;
$t('its drawer offers Run the audit now (on staging, its switch on)', $au && $au['off'] === '', $au);
$stage(false); $d = ($T['get'])($pdo, (int) TeamRoster::bySlug($pdo, 'ops-reconciliation')['id']); $au = array_values(array_filter($d['acts'], fn ($x) => $x['key'] === 'audit'))[0] ?? null;
$t('… greyed on live with why: staging only', $au && str_contains($au['off'], 'staging copy only'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
