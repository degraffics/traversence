<?php
// The Team (decisions/0179's Piece 2, release P2-2; handoffs/piece2-team.md §3.2, §4.3, §7): the roster on a real MySQL or
// MariaDB. The schema and its seed (separate files), each run twice: 33 roles, 9 working, 11 advocates, 13 on standby, the
// same the second time, and a role someone edited never written over. Access::canAs against the same rules can() reads
// (defaults and a saved rule), for every level and department, and never above executive. TeamRoster::can: an advocate
// and the Founder never act; a role on standby doesn't. Edit with History; the status and its way back; the Founder stays
// active. The tool config/team: groups by department, standby folded, the filters, a role's drawer and its actions.
// Run: mariadb -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main' && php roster.php <root>/app
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php roster.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/TeamRoster.php'; require_once $app . '/admin/Access.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$run = function (string $f) use ($pdo, $app) { foreach (array_filter(array_map('trim', explode(";\n", file_get_contents($app . '/migrations/' . $f)))) as $q) { $q = trim(preg_replace('/^--.*$/m', '', $q)); if ($q !== '') $pdo->exec($q); } };
ControlLog::ensure($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (action VARCHAR(80) PRIMARY KEY, min_level VARCHAR(20), departments VARCHAR(200), updated_by INT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_tasks (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10), owner VARCHAR(80) NOT NULL DEFAULT '', undone_at DATETIME NULL, cleared_at DATETIME NULL)");
$pdo->exec('DROP TABLE IF EXISTS team_roster');

// 1. the schema and the seed, each twice
foreach ([1, 2] as $i) { $run('2026-11-25_team_roster.sql'); $run('2026-11-25_team_roster_seed.sql'); }
$c = TeamRoster::counts(TeamRoster::all($pdo));
$t('the seed run twice: 33 roles', $c['all'] === 33, $c);
$t('9 working, 11 advocates, 13 on standby (piece2-team.md §7)', $c['working'] === 9 && $c['advocates'] === 11 && $c['standby'] === 13 && $c['inactive'] === 0, $c);
$t('every spec number 1–33 once', count(array_unique(array_column(TeamRoster::all($pdo), 'spec_no'))) === 33);
$t('the seed file makes no table (0162: a seed beside its CREATE is cleared unrun)', !preg_match('/CREATE\s+TABLE/i', file_get_contents($app . '/migrations/2026-11-25_team_roster_seed.sql')));
$t('the schema file writes no rows', !preg_match('/\b(INSERT\s+INTO|DELETE\s+FROM)\b|\bUPDATE\s+\w+\s+SET\b/i', preg_replace('/^--.*$/m', '', file_get_contents($app . '/migrations/2026-11-25_team_roster.sql'))));
$orders = TeamRoster::bySlug($pdo, 'orders'); $fin = TeamRoster::bySlug($pdo, 'finance');
$t('Orders and Finance on standby (money is Tier 3)', $orders['status'] === 'standby' && $fin['status'] === 'standby');
$ops = TeamRoster::bySlug($pdo, 'ops-reconciliation'); $t('Ops & Reconciliation active, the audit\'s owner', $ops['status'] === 'active' && $ops['kind'] === 'auditor');
$adv = array_filter(TeamRoster::all($pdo), fn ($r) => $r['kind'] === 'advocate');
$t('every advocate bound to a test account or signed_out, with no level to act as', count(array_filter($adv, fn ($r) => $r['test_account'] !== '' && $r['acts_level'] === '')) === 11);
require_once $app . '/lib/TestAccounts.php';
$t('each advocate\'s account is one TestAccounts defines', count(array_filter($adv, fn ($r) => $r['test_account'] === 'signed_out' || isset(TestAccounts::MEMBERS[$r['test_account']]) || isset(TestAccounts::STAFF[$r['test_account']]))) === 11);
$f = TeamRoster::bySlug($pdo, 'founder'); $t('the Founder: active, no level to act as (the real login)', $f['status'] === 'active' && $f['acts_level'] === '' && TeamRoster::actsAs($f) === 'the real login');
$t('no column marks a production founder (the ruling)', !in_array('is_production_founder', array_column($pdo->query('SHOW COLUMNS FROM team_roster')->fetchAll(PDO::FETCH_ASSOC), 'Field'), true));

// 2. a role someone edited isn't written over by the seed
$pm = TeamRoster::bySlug($pdo, 'project-manager');
$msg = TeamRoster::edit($pdo, (int) $pm['id'], ['mandate' => 'Moves tickets when their gates pass.'], 'Jason (test)', 'America/Phoenix');
$t('Edit saves and says where the old words are', str_contains($msg, 'History'), $msg);
$run('2026-11-25_team_roster_seed.sql');
$t('the seed again leaves the edit', TeamRoster::bySlug($pdo, 'project-manager')['mandate'] === 'Moves tickets when their gates pass.');
$h = TeamRoster::history($pdo, (int) $pm['id']);
$t('History holds the old words, who, and the zone', $h && str_contains($h[0]['what'], 'Moves each ticket') && $h[0]['who'] === 'Jason (test)' && $h[0]['tz'] === 'America/Phoenix', $h[0] ?? null);
$t('Edit with nothing new says so', TeamRoster::edit($pdo, (int) $pm['id'], ['mandate' => 'Moves tickets when their gates pass.'], 'Jason (test)') === 'Nothing changed.');
try { TeamRoster::edit($pdo, (int) $pm['id'], ['name' => ' '], 'Jason (test)'); $t('an empty name is refused', false); } catch (RuntimeException $x) { $t('an empty name is refused', str_contains($x->getMessage(), 'name')); }

// 3. status and its way back
$cl = TeamRoster::bySlug($pdo, 'cognitive-load');
$m1 = TeamRoster::setStatus($pdo, (int) $cl['id'], 'active', 'Jason (test)', 'trying it');
$t('Make active: says it has no steps yet', str_contains($m1, 'no steps yet'), $m1);
$m2 = TeamRoster::setStatus($pdo, (int) $cl['id'], 'standby', 'Jason (test)');
$t('Put on standby: its way back named', str_contains($m2, 'made active again') && TeamRoster::get($pdo, (int) $cl['id'])['status'] === 'standby', $m2);
$m3 = TeamRoster::setStatus($pdo, (int) $cl['id'], 'inactive', 'Jason (test)'); $t('Retire: kept, and can come back', str_contains($m3, 'kept') && TeamRoster::get($pdo, (int) $cl['id'])['status'] === 'inactive');
TeamRoster::setStatus($pdo, (int) $cl['id'], 'standby', 'Jason (test)');
$t('each move is in its History with the reason', count(TeamRoster::history($pdo, (int) $cl['id'])) === 4 && str_contains(json_encode(TeamRoster::history($pdo, (int) $cl['id'])), 'trying it'));
try { TeamRoster::setStatus($pdo, (int) $f['id'], 'standby', 'Jason (test)'); $t('the Founder can\'t be put on standby', false); } catch (RuntimeException $x) { $t('the Founder can\'t be put on standby', str_contains($x->getMessage(), 'always active')); }
$t('the same status again says so', str_starts_with(TeamRoster::setStatus($pdo, (int) $cl['id'], 'standby', 'Jason (test)'), 'It\'s already'));

// 4. Access::canAs: the same rules as can(), for a named level and department
$ref = function (array $r, string $lv, string $dep): bool { $rk = Access::RANK[$lv]; if ($rk >= Access::RANK['executive']) return true; return $rk >= (Access::RANK[$r['min']] ?? 2) && (!$r['depts'] || in_array($dep, $r['depts'], true)); };
$bad = [];
foreach ([['min' => 'staff', 'depts' => []], ['min' => 'manager', 'depts' => ['ops']], ['min' => 'director', 'depts' => ['fin', 'sal']], ['min' => 'staff', 'depts' => ['eng']]] as $rule)
    foreach (['assistant', 'staff', 'editor', 'manager', 'director', 'executive'] as $lv) foreach (array_merge([''], array_keys(Access::DEPT_NAMES)) as $dep)
        if (Access::canAs('test.' . md5(json_encode($rule)), $lv, $dep, $rule)[0] !== $ref($rule, $lv, $dep)) $bad[] = [$rule, $lv, $dep];
$t('canAs matches the rule for every level and department (4 rules × 6 levels × 7 departments)', !$bad, array_slice($bad, 0, 3));
$t('canAs refuses the Founder\'s level and unknown levels', !Access::canAs('x', 'founder', '')[0] && !Access::canAs('x', 'king', '')[0]);
$pdo->exec("INSERT INTO admin_permissions (action, min_level, departments, updated_by) VALUES ('team.saved', 'director', 'ops', 1)");
$fresh = new ReflectionProperty(Access::class, 'rules'); $fresh->setAccessible(true); $fresh->setValue(null, null);
$t('canAs reads a rule saved in Permissions over the default', !Access::canAs('team.saved', 'manager', 'ops')[0] && Access::canAs('team.saved', 'director', 'ops')[0]);
$t('canAs says what\'s needed', Access::canAs('team.saved', 'staff', 'fin')[1] === 'Needs a Director or above in Operations', Access::canAs('team.saved', 'staff', 'fin')[1]);

// 5. TeamRoster::can: only active working roles act, only through canAs
$t('an advocate never acts', !TeamRoster::can(reset($adv), 'anything')[0]);
$t('the Founder never acts as an agent', !TeamRoster::can($f, 'anything')[0]);
$t('a role on standby doesn\'t act', !TeamRoster::can(TeamRoster::bySlug($pdo, 'historian'), 'anything')[0]);
$t('the Project Manager acts as a Manager in Operations', TeamRoster::can(TeamRoster::bySlug($pdo, 'project-manager'), 'team.saved')[0] === false && TeamRoster::can(TeamRoster::bySlug($pdo, 'project-manager'), 'pm.ok', ['min' => 'manager', 'depts' => ['ops']])[0]);
$t('its actor name: "system · <role>"', TeamRoster::actor($pm) === 'system · Project Manager');

// 6. the tool, as the admin API calls it
$tools = require $app . '/admin/tools.php'; $T = $tools['config/team'];
$t('the section is in the manifests (config/team)', str_contains(file_get_contents($app . '/admin/manifests.php'), "['table', 'config/team']"));
$t('the Team\'s view needs an Executive; editing, the Founder', $T['view']['min'] === 'executive' && $T['edit']['min'] === 'founder');
[$rows, $total] = ($T['list'])($pdo, []);
$labels = array_map(fn ($r) => $r['_group']['label'] . ' ' . $r['_group']['n'], $rows);
$t('the default: departments as groups, standby folded into one', $labels === ['Leadership 2', 'Operations 3', 'Build 4', 'Advocates 11', 'On standby and retired 13'], $labels);
$t('a group counts as one row for paging (table-standard §9)', $total === 5, $total);
[$rows] = ($T['list'])($pdo, ['show' => 'active']); $t('Active only: 20, a plain list', count($rows) === 20 && !isset($rows[0]['_group']));
[$rows] = ($T['list'])($pdo, ['show' => 'standby', 'dept' => 'operations']); $t('a filter on department and status', count($rows) === 6, count($rows));
[$rows] = ($T['list'])($pdo, ['q' => 'audit']); $t('a search reads names, mandates and works', $rows && str_contains(json_encode($rows), 'Operations & Reconciliation'));
$fl = ($T['filters'])($pdo); $t('the filter says its counts', str_contains($fl[0][2][''], '(33)') && str_contains($fl[0][2]['standby'], '(13)'));
[$rows] = ($T['list'])($pdo, ['show' => 'active']); $cur = array_values(array_filter($rows, fn ($r) => $r['ref'] === 'content-editor'))[0];
$t('a working role with no step says "No steps yet"', $cur['works'] === 'No steps yet', $cur);
$pdo->exec("INSERT INTO control_gauge_tasks (kind, owner) VALUES ('fix', 'system · Project Manager'), ('fix', 'system · Project Manager')");
[$rows] = ($T['list'])($pdo, ['show' => 'active']); $pmr = array_values(array_filter($rows, fn ($r) => $r['ref'] === 'project-manager'))[0];
$t('Open tickets counts what a role has on the queue', $pmr['open'] === 2, $pmr);
$d = ($T['get'])($pdo, (int) $pm['id']);
$keys = array_column($d['acts'], 'key');
$t('a working role\'s drawer: Edit, Put on standby, Retire', $keys === ['edit', 'standby', 'inactive'], $keys);
$t('its History in the drawer, labelled with its zone', $d['timeline_title'] === 'History' && str_contains($d['timeline'][0]['at'], 'MST'), $d['timeline'][0] ?? null);
$t('it names where its permissions come from', str_contains(json_encode($d['fields']), 'what Permissions gives'));
$df = ($T['get'])($pdo, (int) $f['id']); $t('the Founder\'s drawer has no status moves', array_column($df['acts'], 'key') === ['edit']);
$da = ($T['get'])($pdo, (int) TeamRoster::bySlug($pdo, 'advocate-guest')['id']);
$t('an advocate\'s drawer names its test account and links Test accounts', str_contains(json_encode($da['fields']), 'Signed out') && $da['links'][0][1] === '/admin/config/testaccounts');
$ds = ($T['get'])($pdo, (int) TeamRoster::bySlug($pdo, 'historian')['id']); $t('a standby role\'s drawer offers Make active', in_array('active', array_column($ds['acts'], 'key'), true));
$m = ($T['act'])($pdo, (int) TeamRoster::bySlug($pdo, 'historian')['id'], 'active', ['why' => 'test'], 0);
$t('the tool\'s act makes it active and says it has no steps', str_contains($m, 'no steps yet') && TeamRoster::bySlug($pdo, 'historian')['status'] === 'active', $m);
try { ($T['act'])($pdo, 1, 'delete', [], 0); $t('an unknown action is refused', false); } catch (InvalidArgumentException $x) { $t('an unknown action is refused', true); }
$t('nothing deletes a role', !preg_match('/DELETE\s+FROM\s+team_roster/i', file_get_contents($app . '/lib/TeamRoster.php')));
$t('team_roster has its staging class (records, R14)', str_contains(file_get_contents($app . '/lib/staging-tables.php'), "'team_roster'"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
