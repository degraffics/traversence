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
foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql', '2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_runs.sql', '2026-11-25_team_runs.sql', '2026-11-25_team_flag.sql'] as $f) $run($app . '/migrations/' . $f);
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
// 3. at Planning (the ceiling, Jason 2026-11-25): the Project Manager only proposes; never the person's ticket
$t('the ceiling starts at Planning, the tree not approved', TeamRunner::ceiling($pdo) === 'planning' && TeamRunner::treeApproval($pdo) === null);
$out = TeamRunner::tick($pdo);
$r1 = $pdo->query('SELECT * FROM team_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$t('at Planning, the Project Manager proposes "ready for Building" and moves nothing', $r1['step'] === 'gate' && $r1['state'] === 'proposed' && $row($w)['stage'] === 'planning', $out);
$t('… one step only: one run logged', $nRuns() === 1);
$t('… never the ticket a person has, though it\'s priority 1 and ready', $row($mine)['stage'] === 'planning');
$t('… its proposal isn\'t made twice', !TeamRunner::applies($pdo, 'gate', $row($w)));
// 4. the next run: the Translator's proposal on the Resolution (the Work ticket was worked within the hour)
$out = TeamRunner::tick($pdo);
$r2 = $pdo->query('SELECT * FROM team_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$t('the next run: the Translator proposes why it needs the Founder, from its boundary', $r2['step'] === 'brief' && $r2['state'] === 'proposed' && str_contains((string) $r2['proposal'], 'Founder ruling'), $out);
$t('… a proposal changes nothing until a person accepts it', trim((string) GaugeTasks::brief($pdo, $res)['why_you']) === '');
$t('… and nothing else to do within the hour (the Architect is off at Planning)', TeamRunner::tick($pdo) === 'Nothing to do: no ticket a working role has a step for.');
try { TeamRunner::undo($pdo, (int) $r1['id'], $J); $t('a proposal has no Undo: Accept or Dismiss', false); } catch (RuntimeException $x) { $t('a proposal has no Undo: Accept or Dismiss', str_contains($x->getMessage(), 'Accept or Dismiss')); }
// 5. Accept: the move made as the person who accepts it; the brief's words as their Edit
$t('Accept the Project Manager\'s proposal: moved to Building as the person', str_starts_with(TeamRunner::accept($pdo, (int) $r1['id'], $J), 'Accepted: Moved to Building') && $row($w)['stage'] === 'building' && array_slice(GaugeTasks::moves($pdo, $w), -1)[0]['who'] === $J);
$t('Accept the brief: the part filled, kept in its History', str_starts_with(TeamRunner::accept($pdo, (int) $r2['id'], $J), 'Accepted: 1') && str_contains((string) GaugeTasks::brief($pdo, $res)['why_you'], 'Founder ruling') && str_contains(json_encode(GaugeTasks::history($pdo, $res), JSON_UNESCAPED_UNICODE), 'accepting system · Executive Translator'));
try { TeamRunner::accept($pdo, (int) $r2['id'], $J); $t('accepted once only', false); } catch (RuntimeException $x) { $t('accepted once only', true); }
$gap(); $t('the Translator proposes nothing over words a person (or an Accept) wrote', TeamRunner::briefDraft($pdo, $row($res)) === []);
GaugeTasks::move($pdo, $w, 'planning', $J, 'test: back for the next ceiling');
$d = TeamRunner::tick($pdo); $rd = $pdo->query('SELECT id FROM team_runs ORDER BY id DESC LIMIT 1')->fetchColumn();
$t('Dismiss: nothing changes, kept as dismissed', str_starts_with(TeamRunner::dismiss($pdo, (int) $rd, $J), 'Dismissed') && $row($w)['stage'] === 'planning');
// 6. the decision tree and the ceiling (the Founder's; fingerprinted rules; one stage at a time, never Confirmation yet)
try { TeamRunner::setCeiling($pdo, 'building', $J); $t('no raise before the decision tree is approved', false); } catch (RuntimeException $x) { $t('no raise before the decision tree is approved', str_contains($x->getMessage(), 'Approve the decision tree first')); }
$t('approve the tree: tied to its rules\' fingerprint', str_starts_with(TeamRunner::approveTree($pdo, $J), 'Approved') && TeamRunner::treeApproval($pdo)['v'] === (method_exists(TeamRunner::class, 'reach') ? TeamRunner::fingerprint($pdo) : TeamRunner::fingerprint()));
try { TeamRunner::setCeiling($pdo, 'implementation', $J); $t('one stage at a time', false); } catch (RuntimeException $x) { $t('one stage at a time', str_contains($x->getMessage(), 'One stage at a time')); }
$t('raise to Building', TeamRunner::setCeiling($pdo, 'building', $J, 'evidence read') === 'Agents work up to Building now.' && TeamRunner::mode($pdo, 'gate') === 'act' && TeamRunner::mode($pdo, 'handoff') === 'act');
$t('each raise has its evidence lines with readings and sources', count(TeamRunner::evidence($pdo, 'building')) === 3 && count(TeamRunner::evidence($pdo, 'implementation')) === 3 && str_contains(json_encode(TeamRunner::evidence($pdo, 'building')), 'accepted'));
TeamRunner::setCeiling($pdo, 'implementation', $J);
try { TeamRunner::setCeiling($pdo, 'confirmation', $J); $t('Confirmation waits for Piece 3', false); } catch (RuntimeException $x) { $t('Confirmation waits for Piece 3', str_contains($x->getMessage(), 'Piece 3')); }
TeamRunner::setCeiling($pdo, 'building', $J);
$t('the settings\' History is in the logbook', count(array_filter($pdo->query("SELECT what FROM control_log WHERE what LIKE 'Team setting:%'")->fetchAll(PDO::FETCH_COLUMN))) >= 4);
// 7. at Building: the Project Manager acts, with Undo; the System Architect's build brief, with Undo
$gap(); $out = TeamRunner::tick($pdo); $ra = $pdo->query('SELECT * FROM team_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$t('at Building: the Project Manager moves the ticket itself', $ra['step'] === 'gate' && $ra['state'] === 'done' && $row($w)['stage'] === 'building', $out);
$t('… its run says what it saw, did, its Undo and what it touched', $ra['seen'] !== '' && $ra['undo_how'] === 'Moves it back to Planning' && $ra['touches'] === 'task:' . $w && (int) $ra['cost_micros'] === 0);
$t('… the move\'s History names the role, not a person', array_slice(GaugeTasks::moves($pdo, $w), -1)[0]['who'] === 'system · Project Manager');
$t('Undo the move: back to Planning', str_starts_with(TeamRunner::undo($pdo, (int) $ra['id'], $J), 'Undone') && $row($w)['stage'] === 'planning');
try { TeamRunner::undo($pdo, (int) $ra['id'], $J); $t('a second Undo is refused', false); } catch (RuntimeException $x) { $t('a second Undo is refused', str_contains($x->getMessage(), 'Already undone')); }
GaugeTasks::move($pdo, $w, 'building', $J); $gap();
$pdo->exec("UPDATE control_gauge_tasks SET owner = '' WHERE id = $w");   // unowned in Building: the Architect may take it
$out = TeamRunner::tick($pdo);
$t('at Building: the System Architect writes the build brief and has the ticket', str_contains((string) $row($w)['build_brief'], 'Task #' . $w . ': Ready to build') && str_contains((string) $row($w)['build_brief'], 'Done when: It works') && $row($w)['owner'] === 'system · System Architect', $out);
$rep = GaugeTasks::report($pdo); $one = array_values(array_filter($rep['requests'], fn ($x) => $x['id'] === $w))[0];
$t('report() carries the brief to the session manager, and the role\'s name', str_contains($one['build_brief'], 'Ready to build') && $one['owner'] === 'system · System Architect');
$r3 = $pdo->query("SELECT id FROM team_runs WHERE step = 'handoff' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('Undo the handoff: the brief taken back, the ticket unowned again', str_starts_with(TeamRunner::undo($pdo, (int) $r3, $J), 'Undone') && $row($w)['build_brief'] === null && $row($w)['owner'] === '');
// a rule change clears the approval and lowers the ceiling (the fingerprint)
$pdo->exec("UPDATE team_settings SET v = 'an older fingerprint' WHERE k = 'tree'");
$t('the rules changed since approval: the approval is cleared and agents are back at Planning, said in the logbook', TeamRunner::ceiling($pdo) === 'planning' && TeamRunner::treeApproval($pdo) === null && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE '%rules changed since%'")->fetchColumn() === 1);
TeamRunner::approveTree($pdo, $J); TeamRunner::setCeiling($pdo, 'building', $J);
// 8. a role on standby gets no work; Permissions can refuse a role
$gap(); $pm = TeamRoster::bySlug($pdo, 'system-architect'); TeamRoster::setStatus($pdo, (int) $pm['id'], 'standby', $J);
$t('a role on standby whose line has no role Permissions allows the step: no work, it waits for the Founder (decisions/0180 §5)', TeamRunner::tick($pdo) === 'Nothing to do: no ticket a working role has a step for.');
TeamRoster::setStatus($pdo, (int) $pm['id'], 'active', $J);
$pdo->exec("INSERT INTO admin_permissions (action, min_level, departments, updated_by) VALUES ('team.handoff', 'director', '', 1)");
$fresh = new ReflectionProperty(Access::class, 'rules'); $fresh->setAccessible(true); $fresh->setValue(null, null);
$out = TeamRunner::tick($pdo);
$t('Permissions above its level: the run is refused, saying what\'s needed, and nothing changes', str_contains($out, 'refused') && str_contains($out, 'Needs a Director') && trim((string) $row($w)['build_brief']) === '', $out);
// 9. the code's own guarantees
$src = (string) file_get_contents($app . '/lib/TeamRunner.php');
$t('no outside call and no paid AI in the runner (Q4 B)', !preg_match('/curl_|file_get_contents\s*\(\s*[\'"]https?:|AiClient::claude|Gemini::/', $src));
$t('no direct SQL on landmarks or nations (0066; piece2-team.md §5.2)', !preg_match('/\b(FROM|JOIN|INTO|UPDATE)\s+`?(landmarks|nations)\b/i', $src));
$t('it never gives a Founder ruling (Tier 5)', !str_contains($src, 'GaugeTasks::answer'));
require_once $app . '/lib/staging-tables.php';
$t('the queue\'s five tables and team_settings are records now (Q1 A), team_runs staging\'s own', count(array_intersect(['control_gauge_tasks', 'control_gauge_task_steps', 'control_gauge_task_adrs', 'control_gauge_task_moves', 'control_gauge_task_briefs', 'team_settings'], StagingTables::RECORDS)) === 6 && in_array('team_runs', StagingTables::OWN, true) && !in_array('control_gauge_tasks', StagingTables::OWN, true));
$t('their links to their task are registered (0165 §4)', StagingTables::REFS['control_gauge_task_steps.task_id'] === 'control_gauge_tasks' && StagingTables::REFS['control_gauge_task_moves.run_id'] === '');
require_once $app . '/lib/Scheduler.php';
$t('team_tick: in the jobs, set up by itself, and QUIET (its silence on live is never late)', isset(Scheduler::JOBS['team_tick']) && in_array('team_tick', Scheduler::AUTO, true) && in_array('team_tick', Scheduler::QUIET, true));
$t('its script exits on live before anything is recorded', preg_match('/if \(!Staging::here\(\)\) exit\(0\);[\s\S]*Scheduler::run/', (string) file_get_contents($app . '/scripts/team_tick.php')) === 1);
$t('the switch ships off', Switches::DEFS['team_runner'][2] === 'off');
$t('the Team page lists each working role\'s real steps', in_array(TeamRoster::steps('project-manager'), [['gate'], ['gate', 'sequence', 'stall']], true) && TeamRoster::steps('qa-security') === [] && TeamRoster::steps('translator') === ['brief']);
// "It's Tier 2" / "It's Tier 3" are a person's buttons only (the session manager's check on P2-4, 2026-11-25): the runner's code
// never settles a flag, and with a flagged ticket in the queue at the highest ceiling, many ticks leave its flag and tier alone
$src = (string) file_get_contents($app . '/lib/TeamRunner.php');
$t('the runner\'s code never calls settleFlag, never clears a flag', !str_contains($src, 'settleFlag') && !preg_match("/flag\s*=\s*''/", $src) && !str_contains($src, "'qflag'"));
$fl = GaugeTasks::add($pdo, 'area:control-board', 'A refund note on a trail page', 'Text left from a template', 'test', $J, 1); GaugeTasks::flag($pdo, $fl, 'money');
GaugeTasks::edit($pdo, $fl, ['goal' => 'g', 'done_when' => 'd', 'priority' => '2'], $J); GaugeTasks::noDecision($pdo, $fl, $J); GaugeTasks::assign($pdo, $fl, 'system · Project Manager', $J);
TeamRunner::setCeiling($pdo, 'implementation', $J); for ($i = 0; $i < 6; $i++) { $gap(); TeamRunner::tick($pdo); }
$t('a flagged ticket through six ticks at Implementation: still flagged money, still Tier 2, no Resolution opened by an agent', $row($fl)['flag'] === 'money' && (int) ($row($fl)['tier'] ?? 2) !== 5
    && (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE parent_id = $fl AND ticket = 'resolution'")->fetchColumn() === 0);
$t('mission and deadlock: words only, a flag for a person (no area in the code to touch)', TeamRunner::tier('zero-commission for makers') === [2, '', 'mission'] && TeamRunner::tier('two decisions disagree on the cap')[2] === 'deadlock'
    && TeamRunner::tier('a deadlock between 0164 and 0163')[2] === 'deadlock' && !isset(TeamRunner::TERRITORIES['mission']) && !isset(TeamRunner::TERRITORIES['deadlock']));
// Piece 3, P3-4 (decisions/0179's "What comes next": Undo on every agent action, a spending limit per agent; Confirmation),
// where the root has it
if (method_exists('TeamRunner', 'confirmationWaits')) {
    $log = new ReflectionMethod(TeamRunner::class, 'log'); $log->setAccessible(true);
    $pm = TeamRoster::bySlug($pdo, 'project-manager');
    $log->invoke(null, $pdo, $pm, $w, 'gate', 'a test', 'Moved it somewhere', 'done', '', null, '', null, 'task:' . $w);
    $last = $pdo->query('SELECT * FROM team_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $t('P3-4: a step that did something with no Undo is recorded refused, saying so', $last['state'] === 'refused' && str_contains($last['why'], 'no Undo'), $last);
    $t('… every run recorded done has its Undo', (int) $pdo->query("SELECT COUNT(*) FROM team_runs WHERE state = 'done' AND (undo_how = '' OR undo_data IS NULL)")->fetchColumn() === 0);
    $t('P3-4: every step costs $0 (no paid AI, Q4 B), and each role\'s limit is $0 unless set', array_sum(TeamRunner::STEP_COST_MICROS) === 0 && TeamRunner::spendLimit($pdo, 'project-manager') === 0);
    $pdo->exec("INSERT INTO team_runs (at, role, step, state, cost_micros) VALUES (UTC_TIMESTAMP(), 'project-manager', 'gate', 'done', 1500000), (UTC_TIMESTAMP() - INTERVAL 40 DAY, 'project-manager', 'gate', 'done', 9000000)");
    $t('… what a role spent this month, from team_runs (last month\'s not counted)', TeamRunner::spentThisMonth($pdo, 'project-manager') === 1500000, TeamRunner::spentThisMonth($pdo, 'project-manager'));
    $t('… the step costs are in the decision tree\'s rules (a change clears its approval)', isset(TeamRunner::ruleData()['step_cost']));
    TeamRunner::setCeiling($pdo, 'implementation', $J);
    try { TeamRunner::setCeiling($pdo, 'confirmation', $J); $t('P3-4: Confirmation waits while the before-and-after check is off', false); } catch (RuntimeException $x) { $t('P3-4: Confirmation waits while the before-and-after check is off', str_contains($x->getMessage(), 'before-and-after check switched on')); }
    Switches::set($pdo, 'before_after', 'on', 0, false, 'test', 1);
    try { TeamRunner::setCeiling($pdo, 'confirmation', $J); $t('… and while rule 2 is off', false); } catch (RuntimeException $x) { $t('… and while rule 2 is off', str_contains($x->getMessage(), 'rule 2')); }
    $sp = new ReflectionProperty(Access::class, 'staff'); $sp->setAccessible(true); $lp = new ReflectionProperty(Access::class, 'loaded'); $lp->setAccessible(true);
    $sp->setValue(null, ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => $J, 'user_id' => 1]); $lp->setValue(null, true);
    Switches::set($pdo, 'rule2_autopublish', 'on', 0, false, 'test', 1); $sp->setValue(null, null);
    $t('… both on: the Founder raises it to Confirmation, one stage on from Implementation', TeamRunner::setCeiling($pdo, 'confirmation', $J) === 'Agents work up to Confirmation and safe publish now.');
    $ev = TeamRunner::evidence($pdo, 'confirmation');
    $t('… its evidence: six lines, each a reading and its source (checked, wrong blocks, escapes, Undo end to end, ready, and Brief A\'s staging Publish)', count($ev) === 6 && $ev[5][0] === 'A staging Publish' && str_contains($ev[5][1], 'Brief A') && $ev[4][1] === 'The before-and-after check and rule 2 are on' && str_contains($ev[0][2], 'Before and after (advocates)'), $ev);
    TeamRunner::setCeiling($pdo, 'planning', $J);
}
// Jason, 2026-11-25 (through the session manager): from Building the Project Manager takes the tickets the audit raised itself;
// one a person entered waits for Give it to the Team; from Implementation the build brief goes straight to a build session
if (method_exists(TeamRunner::class, 'takesItself')) {
    if (!TeamRunner::treeApproval($pdo)) TeamRunner::approveTree($pdo, $J);
    $au = GaugeTasks::auditOpen($pdo, 'test:found-it', 'A finding the audit raised', 'its words', 2, 'system · Operations & Reconciliation Manager', '', '');
    $aw = GaugeTasks::auditConfirm($pdo, $au, 'system · Operations & Reconciliation Manager');
    GaugeTasks::edit($pdo, $aw, ['goal' => 'Fix the finding', 'done_when' => 'Its check passes'], $J); GaugeTasks::noDecision($pdo, $aw, $J);
    $pw = GaugeTasks::add($pdo, 'area:test', 'A person entered this', 'why', 'src', $J, 1);
    GaugeTasks::edit($pdo, $pw, ['goal' => 'g', 'done_when' => 'd'], $J); GaugeTasks::noDecision($pdo, $pw, $J);
    $pm = TeamRoster::bySlug($pdo, 'project-manager');
    TeamRunner::setCeiling($pdo, 'planning', $J);
    $t('at Planning: an audit-raised ticket with nobody on it isn\'t taken (the gate only proposes, and needs someone)', !TeamRunner::applies($pdo, 'gate', $row($aw)) && $row($aw)['owner'] === '');
    TeamRunner::setCeiling($pdo, 'building', $J);
    $t('at Building: the Work ticket the audit confirmed is one the Project Manager takes itself', TeamRunner::takesItself($pdo, $row($aw)) && TeamRunner::applies($pdo, 'gate', $row($aw)));
    $t('… one a person entered, with nobody on it, isn\'t: it waits for Give it to the Team', !TeamRunner::takesItself($pdo, $row($pw)) && !TeamRunner::applies($pdo, 'gate', $row($pw)));
    $out = TeamRunner::run($pdo, $row($aw), 'gate', $pm);
    $rr = $pdo->query("SELECT * FROM team_runs WHERE task_id = $aw ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $t('… it takes it and moves it to Building, logged with its Undo', $row($aw)['stage'] === 'building' && str_starts_with((string) $row($aw)['owner'], 'system · ') && $rr['state'] === 'done' && str_starts_with($rr['did'], 'Took it (the audit raised it)'), [$out, $rr]);
    TeamRunner::undo($pdo, (int) $rr['id'], $J);
    $t('… Undo: back at Planning with nobody on it, as if never done', $row($aw)['stage'] === 'planning' && $row($aw)['owner'] === '');
    GaugeTasks::assign($pdo, $pw, 'system · Project Manager', $J);
    $t('… the person\'s ticket, once given to the Team: the Project Manager moves it', TeamRunner::applies($pdo, 'gate', $row($pw)));
    // the brief's way
    GaugeTasks::move($pdo, $pw, 'building', $J, 'test');
    $t('at Building: a brief goes to the session manager (report\'s brief_to)', TeamRunner::briefTo($pdo) === 'session manager');
    TeamRunner::setCeiling($pdo, 'implementation', $J);
    $ar = TeamRoster::bySlug($pdo, 'system-architect'); $pdo->exec("DELETE FROM admin_permissions WHERE action = 'team.handoff'"); $fresh->setValue(null, null);   // the default (staff, eng) again
    $pdo->exec("UPDATE control_gauge_tasks SET owner = '' WHERE id = $pw");
    if (method_exists(TeamRunner::class, 'reach')) $pdo->exec("UPDATE control_gauge_tasks SET what = 'A person entered this (website/admin/router.php)' WHERE id = $pw");   // behind the fence (P3-6), a backend ticket
    $out = TeamRunner::run($pdo, $row($pw), 'handoff', $ar);
    $rq = array_values(array_filter(GaugeTasks::report($pdo)['requests'], fn ($q) => $q['id'] === $pw));
    $t('at Implementation: the Architect\'s brief goes straight to a build session, said in its run and in the report (brief_to "build")', TeamRunner::briefTo($pdo) === 'build' && str_contains($out, 'straight to a build session') && ($rq[0]['brief_to'] ?? '') === 'build' && $rq[0]['build_brief'] !== '', [$out, $rq]);
    $t('the ceilings\' words say so: Building takes audit tickets, Implementation goes straight to a build session, Confirmation publishes Tier 1 and 2', str_contains(TeamRunner::CEILINGS['building'][1], 'takes the tickets the audit raised itself') && str_contains(TeamRunner::CEILINGS['implementation'][1], 'straight to a build session') && !str_contains(TeamRunner::CEILINGS['implementation'][1], 'nothing more acts') && str_contains(TeamRunner::CEILINGS['confirmation'][1], 'Tier 1 and Tier 2'));
    $t('the brief step only ever proposes, at every ceiling', array_unique(array_values(TeamRunner::STEP_AT['brief'])) === ['propose']);
    TeamRunner::setCeiling($pdo, 'planning', $J);
}
// P3-6, the fence (decisions/0179's note "agents work on the backend first", Jason, 2026-11-26): how far Implementation reaches
if (method_exists(TeamRunner::class, 'reach')) {
    require_once $app . '/lib/ControlGauges.php';
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)");
    $gf = sys_get_temp_dir() . '/fence-gauges-' . getmypid() . '.json';
    $base = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/site-audit/results/gauges.json'), true);
    $gauges = function (array $extra) use ($base, $gf, $pdo) { file_put_contents($gf, json_encode($extra + $base)); ControlGauges::$source = $gf; $pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true); };
    $gauges(['fence' => ['at' => '2026-11-26T00:16:00Z', 'backend' => ['app/lib/AdminReport.php', 'app/ui/admin-bits.php']], 'fence_refusals' => []]);
    $sp = new ReflectionProperty(Access::class, 'staff'); $sp->setAccessible(true); $lp = new ReflectionProperty(Access::class, 'loaded'); $lp->setAccessible(true);
    $founder = fn (bool $on) => [$sp->setValue(null, $on ? ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => $J, 'user_id' => 1] : ['id' => 2, 'status' => 'active', 'role' => 'staff', 'level' => 'director', 'name' => 'A director', 'user_id' => 2]), $lp->setValue(null, true)];
    TeamRunner::setCeiling($pdo, 'planning', $J);
    $pdo->exec("DELETE FROM team_settings WHERE k = 'reach'");
    $t('P3-6: it ships Backend only', TeamRunner::reach($pdo) === 'backend');
    $founder(false);
    try { TeamRunner::setReach($pdo, 'everywhere', 'A director'); $t('P3-6: a director can\'t change how far Implementation reaches', false); } catch (RuntimeException $x) { $t('P3-6: a director (not the Founder) can\'t change how far Implementation reaches, and nothing changes', str_contains($x->getMessage(), 'Only the Founder') && TeamRunner::reach($pdo) === 'backend'); }
    $sp->setValue(null, null);
    try { TeamRunner::setReach($pdo, 'everywhere', 'the job'); $t('… nor a job or an agent (no staff signed in)', false); } catch (RuntimeException $x) { $t('… nor a job or an agent (no staff signed in)', str_contains($x->getMessage(), 'Only the Founder')); }
    $founder(true);
    if (!TeamRunner::treeApproval($pdo)) TeamRunner::approveTree($pdo, $J);
    $fp0 = TeamRunner::fingerprint($pdo);
    $out = TeamRunner::setReach($pdo, 'everywhere', $J, 'America/Phoenix');
    $t('the Founder changes it: Everywhere, said in the History with what it was, and its way back', $out === 'Implementation reaches Everywhere now.' && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Team setting: Implementation reaches Backend only → Everywhere%Change back%'")->fetchColumn() === 1, $out);
    $t('… it\'s one of the decision tree\'s rules: the fingerprint changes and the approval is cleared, agents at Planning', TeamRunner::fingerprint($pdo) !== $fp0 && TeamRunner::treeApproval($pdo) === null && TeamRunner::ceiling($pdo) === 'planning');
    TeamRunner::setReach($pdo, 'backend', $J);
    $t('… Change back: Backend only again, and the same fingerprint as before', TeamRunner::reach($pdo) === 'backend' && TeamRunner::fingerprint($pdo) === $fp0);
    try { TeamRunner::setReach($pdo, 'somewhere', $J); $t('an unknown setting refused', false); } catch (RuntimeException $x) { $t('an unknown setting refused', str_contains($x->getMessage(), 'Choose')); }
    TeamRunner::approveTree($pdo, $J); TeamRunner::setCeiling($pdo, 'building', $J); TeamRunner::setCeiling($pdo, 'implementation', $J);
    $mk = function (string $what) use ($pdo, $J) { $id = GaugeTasks::add($pdo, 'area:test', $what, 'why', 'src', $J, 1); GaugeTasks::edit($pdo, $id, ['goal' => 'g', 'done_when' => 'd'], $J); GaugeTasks::noDecision($pdo, $id, $J); GaugeTasks::assign($pdo, $id, 'system · Project Manager', $J); GaugeTasks::move($pdo, $id, 'building', $J, 'test'); $pdo->exec("UPDATE control_gauge_tasks SET owner = '' WHERE id = " . (int) $id); return GaugeTasks::task($pdo, $id); };
    $adm = $mk('Tidy website/admin/router.php and app/admin/team-console.php and app/lib/AdminReport.php');
    $pubT = $mk('Change app/lib/Orders.php');
    $mixT = $mk('Change app/lib/AdminReport.php and app/ui/cart.php');
    $none = $mk('Make the reports better');
    $t('at Implementation, Backend only: a ticket naming only /admin/ pages, admin parts and backend files goes to a build session', TeamRunner::briefFor($pdo, $adm) === 'build' && TeamRunner::frontOf($pdo, $adm) === [], TeamRunner::frontOf($pdo, $adm));
    $t('… one reaching a public page stops at its brief, for the Founder', TeamRunner::briefFor($pdo, $pubT) === 'founder' && TeamRunner::frontOf($pdo, $pubT) === ['app/lib/Orders.php']);
    $t('… a mixed one too, naming only its public file', TeamRunner::briefFor($pdo, $mixT) === 'founder' && TeamRunner::frontOf($pdo, $mixT) === ['app/ui/cart.php']);
    $t('… one naming no file isn\'t proved backend: the Founder', TeamRunner::briefFor($pdo, $none) === 'founder');
    $gauges(['fence' => ['at' => '', 'backend' => []]]);
    $t('… no reach map read yet: nothing but the admin itself is proved backend', TeamRunner::briefFor($pdo, $mixT) === 'founder' && TeamRunner::frontOf($pdo, ['id' => $adm['id']] + $adm) !== [] && str_contains(implode(' ', TeamRunner::frontOf($pdo, $adm)), "isn't here yet"));
    $gauges(['fence' => ['at' => '2026-11-26T00:16:00Z', 'backend' => ['app/lib/AdminReport.php', 'app/ui/admin-bits.php']], 'fence_refusals' => []]);
    // the Architect's run on a public ticket says so, and the report routes it to the Founder
    $pdo->exec("DELETE FROM admin_permissions WHERE action = 'team.handoff'"); $fresh->setValue(null, null);
    $ar = TeamRoster::bySlug($pdo, 'system-architect');
    $out = TeamRunner::run($pdo, GaugeTasks::task($pdo, (int) $pubT['id']), 'handoff', $ar);
    $rq = array_values(array_filter(GaugeTasks::report($pdo)['requests'], fn ($q) => $q['id'] === (int) $pubT['id']));
    $t('the Architect\'s brief on a public ticket: "front end: waiting for you", and the report sends it to the Founder, never a build session', str_contains($out, 'front end: waiting for you') && ($rq[0]['brief_to'] ?? '') === 'founder' && ($rq[0]['fence'] ?? '') === 'backend', [$out, $rq]);
    $out = TeamRunner::run($pdo, GaugeTasks::task($pdo, (int) $adm['id']), 'handoff', $ar);
    $rq = array_values(array_filter(GaugeTasks::report($pdo)['requests'], fn ($q) => $q['id'] === (int) $adm['id']));
    $t('… on a backend ticket: straight to a build session (brief_to "build", as P3-4)', str_contains($out, 'straight to a build session') && ($rq[0]['brief_to'] ?? '') === 'build', [$out, $rq]);
    // a build refused at the fence (send.py) comes back: the ticket at its brief, for the Founder, once
    $pdo->prepare('UPDATE control_gauge_tasks SET rel = ? WHERE id = ?')->execute(['agent-admin.zip', (int) $adm['id']]);
    $gauges(['fence' => ['at' => '2026-11-26T00:16:00Z', 'backend' => ['app/lib/AdminReport.php']], 'fence_refusals' => [['ticket' => (int) $adm['id'], 'zip' => 'agent-admin.zip', 'reason' => 'app/ui/cart.php reaches /cart.php', 'at' => '2026-11-26T01:00:00Z']]]);
    $n1 = TeamRunner::applyRefusals($pdo); $n2 = TeamRunner::applyRefusals($pdo);
    $t('a build refused at the fence: the ticket back at its brief (its release cleared), for the Founder, said once in the logbook', $n1 === 1 && $n2 === 0 && TeamRunner::briefFor($pdo, GaugeTasks::task($pdo, (int) $adm['id'])) === 'founder' && GaugeTasks::task($pdo, (int) $adm['id'])['rel'] === '' && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Task #" . (int) $adm['id'] . " back at its brief, front end%'")->fetchColumn() === 1);
    // Everywhere: the fence doesn't stop it
    TeamRunner::setReach($pdo, 'everywhere', $J); TeamRunner::approveTree($pdo, $J); TeamRunner::setCeiling($pdo, 'building', $J); TeamRunner::setCeiling($pdo, 'implementation', $J);
    $t('with Everywhere: a public ticket goes to a build session', TeamRunner::briefFor($pdo, GaugeTasks::task($pdo, (int) $pubT['id'])) === 'build');
    TeamRunner::setReach($pdo, 'backend', $J); TeamRunner::approveTree($pdo, $J); TeamRunner::setCeiling($pdo, 'building', $J);
    $t('at Building: briefs go to the session manager, fence or not', TeamRunner::briefFor($pdo, GaugeTasks::task($pdo, (int) $pubT['id'])) === 'session manager');
    $t('the fence calls nothing outside (no curl, no paid AI)', !preg_match('/curl_|file_get_contents\s*\(\s*[\'"]https?:|AiClient::claude|Gemini::/', (string) file_get_contents($app . '/lib/TeamRunner.php')));
    TeamRunner::setCeiling($pdo, 'planning', $J); $sp->setValue(null, null); @unlink($gf);
}
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
