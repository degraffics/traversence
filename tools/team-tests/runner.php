<?php
// The Team's runner (Piece 2, P2-3; handoffs/piece2-team.md §5; Jason's Q1 A and Q4 B, 2026-11-25) on a real MySQL or MariaDB:
// it does nothing and logs nothing on live; off until its switch is On; one run at a time (the lock); one step a run, a ticket
// at most once an hour; never a ticket a person has; the Project Manager moves a Work ticket through Planning's gate, the System
// Architect writes a Building ticket's build brief, the Translator proposes a Resolution brief's words (never over a person's,
// never an answer); each with its Undo, a second Undo refused, a proposal accepted as a person's Edit or dismissed; a role on
// standby gets no work; refused by Permissions when its level can't; no outside call or paid AI anywhere in it; the queue's
// tables are records now and team_runs staging's own; the job is QUIET and staging only; report() carries the build brief.
// Run: php runner.php <root>/app   (it resets lv_main)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php runner.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { if (trim($s) === '') continue; $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$run(__DIR__ . '/before-schema.sql');
$H = dirname(__DIR__, 2) . '/handoffs/';
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql'] as $f) $run($f);
foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql', '2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_runs.sql', '2026-11-25_team_runs.sql'] as $f) $run($app . '/migrations/' . $f);
$pdo->exec("CREATE TABLE feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (action VARCHAR(80) PRIMARY KEY, min_level VARCHAR(20), departments VARCHAR(200), updated_by INT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS site_releases (id INT AUTO_INCREMENT PRIMARY KEY, zip_name VARCHAR(190) NOT NULL DEFAULT '', state VARCHAR(20) NOT NULL DEFAULT '', live_at DATETIME NULL)");
require_once $app . '/lib/TeamRunner.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Staging.php'; require_once $app . '/admin/Access.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true);
$stage = function (bool $on) use ($here) { putenv('STAGING=' . ($on ? '1' : '0')); $here->setValue(null, null); };
$sw = function (string $state) use ($pdo) { Switches::set($pdo, 'team_runner', $state, 0, false, 'test', 1); };
$J = 'Jason (test)'; $row = fn ($id) => GaugeTasks::task($pdo, $id);
$nRuns = fn () => (int) $pdo->query('SELECT COUNT(*) FROM team_runs')->fetchColumn();
$gap = fn () => $pdo->exec('UPDATE team_runs SET at = at - INTERVAL 2 HOUR');   // an hour on, so a ticket may be worked again

// a Work ticket ready to leave Planning (unowned is not allowed by its gate: a Team role has it), one a person has, a Resolution
$w = GaugeTasks::add($pdo, 'area:test', 'Ready to build', 'why', 'src', $J, 1);
GaugeTasks::edit($pdo, $w, ['goal' => 'Do it', 'done_when' => 'It works'], $J); GaugeTasks::noDecision($pdo, $w, $J); GaugeTasks::assign($pdo, $w, 'system · Project Manager', $J);
$mine = GaugeTasks::add($pdo, 'area:test', 'A person has this', 'why', 'src', $J, 1);
GaugeTasks::edit($pdo, $mine, ['goal' => 'g', 'done_when' => 'd', 'priority' => '1'], $J); GaugeTasks::noDecision($pdo, $mine, $J); GaugeTasks::assign($pdo, $mine, $J, $J);
$res = GaugeTasks::add($pdo, 'area:test', 'May staff text go to the paid key?', 'privacy', '0164', $J, 1, '', 'resolution');
GaugeTasks::briefEdit($pdo, $res, 'boundary', 'privacy', $J);

// 1. live: nothing, logged nothing; off: says so; on: runs
$stage(false); $sw('on');
$t('on live: does nothing and logs nothing', TeamRunner::tick($pdo) === '' && $nRuns() === 0);
$stage(true); $sw('off');
$t('on staging, switched off: says so, does nothing', str_contains(TeamRunner::tick($pdo), 'switched off') && $nRuns() === 0);
$sw('test'); $t('"Staff and tests" isn\'t on for a job with no one signed in', str_contains(TeamRunner::tick($pdo), 'switched off'));
$sw('on');
// 2. the lock: another run going means this one does nothing
$other = new PDO('mysql:host=127.0.0.1;dbname=lv_main', 'tv', 'tv'); $other->query("SELECT GET_LOCK('team_tick', 0)");
$t('one run at a time: with the lock held, it does nothing', str_contains(TeamRunner::tick($pdo), 'Another run') && $nRuns() === 0);
$other->query("SELECT RELEASE_LOCK('team_tick')");
// 3. one step a run: the Project Manager's gate first (priority, then age), never the person's ticket
$out = TeamRunner::tick($pdo);
$t('a run: the Project Manager moves the Work ticket to Building', str_contains($out, 'system · Project Manager') && $row($w)['stage'] === 'building', $out);
$t('… one step only: one run logged', $nRuns() === 1);
$t('… never the ticket a person has, though it\'s priority 1 and ready', $row($mine)['stage'] === 'planning');
$t('… the move\'s History names the role, not a person', str_contains(json_encode(GaugeTasks::moves($pdo, $w), JSON_UNESCAPED_UNICODE), '"who":"system · Project Manager"'));
$r1 = $pdo->query('SELECT * FROM team_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$t('the run says what it saw, did, its Undo and what it touched', $r1['state'] === 'done' && $r1['seen'] !== '' && $r1['undo_how'] === 'Moves it back to Planning' && $r1['touches'] === 'task:' . $w && (int) $r1['cost_micros'] === 0);
// 4. the next run: the Translator's proposal on the Resolution (the Work ticket was worked within the hour)
$out = TeamRunner::tick($pdo);
$r2 = $pdo->query('SELECT * FROM team_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$t('the next run: the Translator proposes why it needs the Founder, from its boundary', $r2['step'] === 'brief' && $r2['state'] === 'proposed' && str_contains((string) $r2['proposal'], 'Tier 3 line'), $out);
$t('… a proposal changes nothing until a person accepts it', trim((string) GaugeTasks::brief($pdo, $res)['why_you']) === '');
$t('… and the Resolution isn\'t worked again within the hour', TeamRunner::tick($pdo) === 'Nothing to do: no ticket a working role has a step for.');
// 5. Undo, and a second Undo refused
$t('Undo the Project Manager\'s move: back to Planning', str_starts_with(TeamRunner::undo($pdo, (int) $r1['id'], $J), 'Undone') && $row($w)['stage'] === 'planning');
try { TeamRunner::undo($pdo, (int) $r1['id'], $J); $t('a second Undo is refused', false); } catch (RuntimeException $x) { $t('a second Undo is refused', str_contains($x->getMessage(), 'Already undone')); }
try { TeamRunner::undo($pdo, (int) $r2['id'], $J); $t('a proposal has no Undo: Accept or Dismiss', false); } catch (RuntimeException $x) { $t('a proposal has no Undo: Accept or Dismiss', str_contains($x->getMessage(), 'Accept or Dismiss')); }
// 6. Accept a proposal: a person's Edit, never over a person's words
$t('Accept: the brief\'s part filled, kept in its History', str_starts_with(TeamRunner::accept($pdo, (int) $r2['id'], $J), 'Accepted: 1') && str_contains((string) GaugeTasks::brief($pdo, $res)['why_you'], 'Tier 3 line') && str_contains(json_encode(GaugeTasks::history($pdo, $res), JSON_UNESCAPED_UNICODE), 'accepting system · Executive Translator'));
try { TeamRunner::accept($pdo, (int) $r2['id'], $J); $t('accepted once only', false); } catch (RuntimeException $x) { $t('accepted once only', true); }
$gap(); $t('the Translator proposes nothing over words a person (or an Accept) wrote', TeamRunner::briefDraft($pdo, $row($res)) === []);
// 7. the System Architect's build brief, its Undo
GaugeTasks::move($pdo, $w, 'building', $J); $gap();
$pdo->exec("UPDATE control_gauge_tasks SET owner = '' WHERE id = $w");   // unowned in Building: the Architect may take it
$out = TeamRunner::tick($pdo);
$t('the System Architect writes the build brief and has the ticket', str_contains((string) $row($w)['build_brief'], 'Task #' . $w . ': Ready to build') && str_contains((string) $row($w)['build_brief'], 'Done when: It works') && $row($w)['owner'] === 'system · System Architect', $out);
$rep = GaugeTasks::report($pdo); $one = array_values(array_filter($rep['requests'], fn ($x) => $x['id'] === $w))[0];
$t('report() carries the brief to the session manager, and the role\'s name', str_contains($one['build_brief'], 'Ready to build') && $one['owner'] === 'system · System Architect');
$r3 = $pdo->query("SELECT id FROM team_runs WHERE step = 'handoff' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('Undo the handoff: the brief taken back, the ticket unowned again', str_starts_with(TeamRunner::undo($pdo, (int) $r3, $J), 'Undone') && $row($w)['build_brief'] === null && $row($w)['owner'] === '');
// 8. a role on standby gets no work; Permissions can refuse a role
$gap(); $pm = TeamRoster::bySlug($pdo, 'system-architect'); TeamRoster::setStatus($pdo, (int) $pm['id'], 'standby', $J);
$t('a role on standby gets no work', TeamRunner::tick($pdo) === 'Nothing to do: no ticket a working role has a step for.');
TeamRoster::setStatus($pdo, (int) $pm['id'], 'active', $J);
$pdo->exec("INSERT INTO admin_permissions (action, min_level, departments, updated_by) VALUES ('team.handoff', 'director', '', 1)");
$fresh = new ReflectionProperty(Access::class, 'rules'); $fresh->setAccessible(true); $fresh->setValue(null, null);
$out = TeamRunner::tick($pdo);
$t('Permissions above its level: the run is refused, saying what\'s needed, and nothing changes', str_contains($out, 'refused') && str_contains($out, 'Needs a Director') && trim((string) $row($w)['build_brief']) === '', $out);
// 9. the code's own guarantees
$src = (string) file_get_contents($app . '/lib/TeamRunner.php');
$t('no outside call and no paid AI in the runner (Q4 B)', !preg_match('/curl_|file_get_contents\s*\(\s*[\'"]https?:|AiClient::claude|Gemini::/', $src));
$t('no direct SQL on landmarks or nations (0066; piece2-team.md §5.2)', !preg_match('/\b(FROM|JOIN|INTO|UPDATE)\s+`?(landmarks|nations)\b/i', $src));
$t('it never answers a Tier 3', !str_contains($src, 'GaugeTasks::answer'));
require_once $app . '/lib/staging-tables.php';
$t('the queue\'s five tables are records now (Q1 A), team_runs staging\'s own', count(array_intersect(['control_gauge_tasks', 'control_gauge_task_steps', 'control_gauge_task_adrs', 'control_gauge_task_moves', 'control_gauge_task_briefs'], StagingTables::RECORDS)) === 5 && in_array('team_runs', StagingTables::OWN, true) && !in_array('control_gauge_tasks', StagingTables::OWN, true));
$t('their links to their task are registered (0165 §4)', StagingTables::REFS['control_gauge_task_steps.task_id'] === 'control_gauge_tasks' && StagingTables::REFS['control_gauge_task_moves.run_id'] === '');
require_once $app . '/lib/Scheduler.php';
$t('team_tick: in the jobs, set up by itself, and QUIET (its silence on live is never late)', isset(Scheduler::JOBS['team_tick']) && in_array('team_tick', Scheduler::AUTO, true) && in_array('team_tick', Scheduler::QUIET, true));
$t('its script exits on live before anything is recorded', preg_match('/if \(!Staging::here\(\)\) exit\(0\);[\s\S]*Scheduler::run/', (string) file_get_contents($app . '/scripts/team_tick.php')) === 1);
$t('the switch ships off', Switches::DEFS['team_runner'][2] === 'off');
$t('the Team page lists each working role\'s real steps', TeamRoster::steps('project-manager') === ['gate'] && TeamRoster::steps('qa-security') === [] && TeamRoster::steps('translator') === ['brief']);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
