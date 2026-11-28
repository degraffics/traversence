<?php
// The "rules" release (handoffs/team-agents-rules.md; Jason's ruling B, decisions/0180 §2: one approval for the 42 agents' listed
// rule changes) on a real MySQL or MariaDB: every listed step is in TeamRunner's rules exactly as listed (its role, its
// Permissions action and default, what it does at each ceiling, $0), and nothing else is; the fingerprint changes once, and
// naming a step in BUILT (what an agent's own release does) doesn't change it; a step without its code does nothing, gets no
// work, isn't one of its role's steps, and the Team page says "Coming with its release"; the four live steps work as before.
// Run: php rules.php <root>/app <live app/lib/TeamRunner.php>   (it resets lv_main; <root>/app = the live app plus this build)
declare(strict_types=1);
[$app, $liveFile] = [rtrim($argv[1] ?? '', '/'), $argv[2] ?? ''];
if (!is_file($app . '/lib/TeamRunner.php') || !is_file($liveFile)) { fwrite(STDERR, "usage: php rules.php <root>/app <live TeamRunner.php>\n"); exit(2); }
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
exec('php ' . escapeshellarg(__DIR__ . '/run-migration.php') . ' ' . escapeshellarg($app) . ' ' . escapeshellarg($app . '/migrations/2026-11-26_0180_tiers_roster.sql'));   // decisions/0180's roster, as roster.cjs runs it
require_once $app . '/lib/TeamRunner.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Staging.php'; require_once $app . '/admin/Access.php'; require_once $app . '/admin/team-console.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$LIVE = ['gate', 'handoff', 'brief', 'audit'];
// the list, as the Founder reads it: one row a step
$list = [];
foreach (explode("\n", (string) file_get_contents($H . 'team-agents-rules.md')) as $line)
    if (preg_match('/^\| (\d\d) \| (\d) \| (.+?) \((\d+)\) \| `([a-z0-9-]+)` \| (.+?) \| (.+?) \| (.+?) \|$/', $line, $m)) $list[$m[5]] = ['no' => (int) $m[4], 'name' => $m[3], 'what' => $m[6], 'when' => $m[7]];
$t('the list names 27 steps', count($list) === 27, count($list));
$new = array_values(array_diff(array_keys(TeamRunner::STEPS), $LIVE));
$t('the rules hold exactly the 4 live steps and the 27 listed, in the list\'s order', array_slice(array_keys(TeamRunner::STEPS), 0, 4) === $LIVE && $new === array_keys($list), array_diff($new, array_keys($list)));
$t('each does what its row says, word for word', !array_filter($list, fn ($x, $k) => TeamRunner::STEPS[$k][1] !== $x['what'], ARRAY_FILTER_USE_BOTH));
$bad = []; foreach ($list as $k => $x) { $r = TeamRoster::bySlug($pdo, TeamRunner::STEPS[$k][0]); if (!$r || $r['name'] !== $x['name'] || (string) $r['spec_no'] !== (string) $x['no'] || $r['status'] === 'inactive') $bad[] = $k; }
$t('each belongs to the role its row names, by its number in decisions/0180, on the live roster', $bad === [], $bad);
$t('each asks Permissions for its own action, team.<step>', !array_filter($new, fn ($k) => TeamRunner::STEPS[$k][2] !== 'team.' . $k));
$lvl = fn (int $no) => $no <= 7 ? 'executive' : ($no <= 14 ? 'manager' : 'staff');
$draft = ['sequence' => ['manager', ['ops']], 'stall' => ['manager', ['ops']], 'triage' => ['manager', ['eng']], 'editorial' => ['manager', []], 'content' => ['staff', []]];
$t('its default is its draft\'s, or the role\'s own layer for a planned step', !array_filter($list, fn ($x, $k) => TeamRunner::STEPS[$k][3] !== ['min' => $draft[$k][0] ?? $lvl($x['no']), 'depts' => $draft[$k][1] ?? []], ARRAY_FILTER_USE_BOTH));
$all = ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'];
$t('every new step only proposes at every ceiling, as listed; stall acts from Building on', !array_filter($new, fn ($k) => TeamRunner::STEP_AT[$k] !== ($k === 'stall' ? ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'] : $all)) && !array_filter($list, fn ($x, $k) => ($k === 'stall') !== str_contains($x['when'], 'acts from Building'), ARRAY_FILTER_USE_BOTH));
$t('every step has a mode at every ceiling and a cost, and every cost is $0', !array_filter(array_keys(TeamRunner::STEPS), fn ($k) => array_keys(TeamRunner::STEP_AT[$k] ?? []) !== array_keys(TeamRunner::CEILINGS) || (TeamRunner::STEP_COST_MICROS[$k] ?? -1) !== 0) && count(TeamRunner::STEP_COST_MICROS) === 31);
$t('step keys are one a role\'s step: the managers\' triage steps each have their own', count(array_unique($new)) === 27 && !array_diff(['triage', 'triage-service', 'triage-accounting', 'triage-marketing', 'triage-sales'], $new));
$t('the three rules that come once: reasoning order, the paid API off, 3 days', TeamRunner::STEP_AI === ['gate' => ['code'], 'sequence' => ['code'], 'stall' => ['code', 'api', 'session']] && TeamRunner::AGENTS_API_LIVE === false && TeamRunner::STALL_DAYS === 3);
$rd = TeamRunner::ruleData();
$t('they are part of what the approval is tied to', $rd['step_ai'] === TeamRunner::STEP_AI && $rd['agents_api'] === false && $rd['stall_days'] === 3 && count($rd['steps']) === 31);
$t('no advocate, the Founder, the CEO or a session has a step', !array_filter(TeamRunner::STEPS, fn ($s) => in_array($s[0], ['founder', 'ceo', 'builder'], true) || str_starts_with($s[0], 'advocate-')));

// the fingerprint: once
$fp = function (string $file): string { $code = '<?php namespace FP' . substr(md5($file . microtime()), 0, 8) . '; use PDO; ' . preg_replace('/^<\?php/', '', preg_replace('/^(declare\(strict_types=1\);|require_once [^\n]*)$/m', '', (string) file_get_contents($file)), 1) . ' echo TeamRunner::fingerprint();'; $tmp = tempnam(sys_get_temp_dir(), 'fp'); file_put_contents($tmp, $code); $out = shell_exec('php ' . escapeshellarg($tmp)); unlink($tmp); return trim((string) $out); };
$now = TeamRunner::fingerprint(); $was = $fp($liveFile);
$t('the fingerprint changes from the live one: one approval is needed', strlen($was) === 64 && $was !== $now, [$was, $now]);
$built = tempnam(sys_get_temp_dir(), 'tr'); $src = (string) file_get_contents($app . '/lib/TeamRunner.php');
file_put_contents($built, str_replace("public const BUILT = ['gate', 'handoff', 'brief', 'audit'];", "public const BUILT = ['gate', 'handoff', 'brief', 'audit', 'sequence', 'stall', 'triage'];", $src, $cnt));
$t('naming a step in BUILT, as an agent\'s own release does, keeps the fingerprint', $cnt === 1 && $fp($built) === $now, $fp($built));
file_put_contents($built, str_replace("'triage' => ['planning' => 'propose', 'building' => 'propose'", "'triage' => ['planning' => 'propose', 'building' => 'act'", $src, $cnt));
$t('a release that makes a step act where its row says it proposes changes it: a new press', $cnt === 1 && $fp($built) !== $now);
unlink($built);
$t('BUILT is not a rule', !in_array(TeamRunner::BUILT, $rd, true) && TeamRunner::BUILT === $LIVE);

// a step without its code does nothing
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true); putenv('STAGING=1'); $here->setValue(null, null);
Switches::set($pdo, 'team_runner', 'on', 0, false, 'test', 1); TeamRunner::ensure($pdo); GaugeTasks::ensure($pdo);
TeamRunner::approveTree($pdo, 'Jason (test)'); TeamRunner::setCeiling($pdo, 'building', 'Jason (test)', 'test');
$id = GaugeTasks::add($pdo, 'area:test', 'A ticket idle a week in the IT lane', 'why', 'src', 'Jason (test)', 1);
$pdo->exec("UPDATE control_gauge_tasks SET priority = 1, at = UTC_TIMESTAMP() - INTERVAL 9 DAY, stage_at = UTC_TIMESTAMP() - INTERVAL 9 DAY WHERE id = $id");
$tk = GaugeTasks::task($pdo, $id);
$t('no unbuilt step applies to any ticket', !array_filter($new, fn ($k) => TeamRunner::applies($pdo, $k, $tk)));
$pick = TeamRunner::pick($pdo);
$t('the runner gives no work to an unbuilt step', $pick === null || in_array($pick[1], $LIVE, true));
$t('a run finds nothing an unbuilt step would do, and records no run for one', !str_contains(TeamRunner::tick($pdo), 'Unstick') && (int) $pdo->query("SELECT COUNT(*) FROM team_runs WHERE step NOT IN ('gate','handoff','brief','audit')")->fetchColumn() === 0);
$t('a role\'s steps are only the built ones; the rest are planned', TeamRoster::steps('project-manager') === ['gate'] && TeamRoster::planned('project-manager') === ['sequence', 'stall'] && TeamRoster::steps('it-manager') === [] && TeamRoster::planned('it-manager') === ['triage'] && TeamRoster::planned('system-architect') === [] && TeamRoster::steps('system-architect') === ['handoff']);
$t('so a role with only a planned step still reads as having no step yet', !TeamRoster::hasSteps(TeamRoster::bySlug($pdo, 'coo')) && TeamRoster::hasSteps(TeamRoster::bySlug($pdo, 'translator')));
$html = tv_admin_mod_team($pdo);
$t('the Team page lists all 31, the 27 as "Coming with its release", saying they do nothing until then', substr_count($html, 'Coming with its release') === 27 && substr_count($html, 'It does nothing until then') === 27 && substr_count($html, '<tr><td><b>') >= 31);
$t('the four live steps read as before: acts, proposes, off', str_contains($html, '>acts<') && str_contains($html, 'proposes, a person accepts'));
$t('the page shows the new fingerprint, and that it is approved here', str_contains($html, substr(TeamRunner::fingerprint($pdo), 0, 12)) && str_contains($html, 'Approved:'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
