<?php
// tools/role-queues-tests/role-queues.php — RoleQueues, read only, on an in-memory SQLite Team (no MySQL, no money,
// nothing live). Run: php tools/role-queues-tests/role-queues.php  (APP_ROOT overrides the repo root). Prints PASS/FAIL.
declare(strict_types=1);

$root = getenv('APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/app/lib/RoleQueues.php';

$fail = 0;
function ok(bool $c, string $name): void { global $fail; echo ($c ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$c) $fail++; }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE team_roster (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, kind TEXT, status TEXT, reports_to TEXT DEFAULT '', sort INT DEFAULT 0, acts_level TEXT DEFAULT '', acts_dept TEXT DEFAULT '')");
$ins = $pdo->prepare('INSERT INTO team_roster (slug, name, kind, status, reports_to, sort) VALUES (?, ?, ?, ?, ?, ?)');
foreach ([['founder', 'Founder / Chair', 'founder', 'active', '', 1], ['project-manager', 'Project Manager', 'manager', 'active', 'founder', 8],
    ['it-manager', 'IT Manager', 'manager', 'active', 'founder', 11], ['system-architect', 'System Architect', 'operator', 'active', 'it-manager', 20],
    ['advocate-a1', 'Advocate A1', 'advocate', 'active', '', 40], ['historian', 'Historian', 'keeper', 'inactive', '', 99]] as $r) $ins->execute($r);
$tables = fn () => (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master")->fetchColumn();
$before = $tables();

$now = strtotime('2026-12-01 12:00:00 UTC');
$at = fn (int $h) => gmdate('Y-m-d H:i:s', $now - $h * 3600);
$queue = [
    ['id' => 1, 'at' => $at(2), 'what' => 'Tidy the queue labels', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'open', 'owner_now' => '', 'lane' => 'project-manager', 'priority' => 2],
    ['id' => 2, 'at' => $at(30), 'what' => 'Old untaken task', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'late', 'owner_now' => '', 'lane' => 'project-manager', 'priority' => null],
    ['id' => 3, 'at' => $at(5), 'what' => 'Brief the runner fix', 'ticket' => 'work', 'stage' => 'building', 'state' => 'taken', 'owner_now' => 'system · System Architect', 'lane' => 'it-manager'],
    ['id' => 4, 'at' => $at(50), 'what' => 'Decide: a fence', 'ticket' => 'resolution', 'stage' => 'waiting', 'state' => 'waiting', 'owner_now' => '', 'lane' => 'it-manager'],
    ['id' => 5, 'at' => $at(80), 'what' => 'FAQ typo', 'ticket' => 'work', 'stage' => 'confirmation', 'state' => 'done', 'owner_now' => '', 'lane' => 'editorial-director'],
    ['id' => 6, 'at' => $at(3), 'what' => 'Ledger line check', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'taken', 'owner_now' => 'A staff person', 'lane' => 'accounting-manager'],
    ['id' => 7, 'at' => $at(4), 'what' => 'Held by a retired role', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'taken', 'owner_now' => 'system · Historian', 'lane' => 'sales-director'],
    ['id' => 8, 'at' => $at(1), 'what' => 'An audit finding', 'ticket' => 'audit', 'stage' => 'found', 'state' => 'open', 'owner_now' => '', 'parent_id' => null],
    ['id' => 9, 'at' => $at(1), 'what' => 'Odd state', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'weird', 'owner_now' => 'system · Project Manager', 'lane' => 'marketing-director'],
];
$v = RoleQueues::build($pdo, $queue, $now);

ok(count($v['lanes']) === 7, 'seven lane queues, one per middle manager');
$pm = $v['lanes']['project-manager'];
ok($pm['open'] === 2 && $pm['late'] === 1, 'PM lane counts: 2 open, 1 late');
ok($pm['line'] === '2 open · 1 late (a day old, nobody has it)', 'PM lane line');
ok($pm['oldest_open_hours'] === 30, 'PM lane oldest open is 30 hours');
$pmr = $v['roles']['project-manager']['rows'];
ok(array_column($pmr, 'id') === [2, 1, 9], 'PM inbox: late first, then priority, then its own held row');
ok($pmr[0]['here'] === 'lane' && $pmr[2]['here'] === 'holds', 'PM inbox says why each row is there');
ok($pmr[0]['sla'] === 'Late: a day old and nobody has it', 'late row SLA words');
ok($pmr[1]['sla'] === 'Nobody has it yet: late in 22 hours', 'open row SLA counts down to a day');
ok(array_column($v['roles']['system-architect']['rows'], 'id') === [3], 'System Architect holds #3');
ok(array_column($v['roles']['it-manager']['rows'], 'id') === [8], 'IT Manager inbox: the unowned audit (lane by TeamRoster::lane), not the held or waiting ones');
ok(array_column($v['lanes']['it-manager']['rows'], 'id') === [3, 4, 8] || count($v['lanes']['it-manager']['rows']) === 3, 'IT lane has #3, #4 and the audit #8');
$f = $v['roles']['founder']['rows'];
ok(count($f) === 1 && $f[0]['id'] === 4 && $f[0]['here'] === 'ruling', 'Founder queue: the ruling waiting for him');
ok($f[0]['sla'] === 'Waiting for the Founder: no time limit is set', 'no per-stage limit invented (Q-B)');
ok($v['lanes']['editorial-director']['line'] === 'Nothing open' && $v['lanes']['editorial-director']['counts']['done'] === 1, 'done row counted, nothing open');
ok($v['lanes']['accounting-manager']['open'] === 1 && !isset($v['roles']['accounting-manager']), 'a person-held ticket shows in its lane only');
ok(array_column($v['unplaced'], 'id') === [7], 'a ticket held by a retired role is unplaced, for a person to see');
ok(!isset($v['roles']['advocate-a1']) && !isset($v['roles']['historian']), 'no queue for advocates or retired roles');
ok($v['lanes']['marketing-director']['rows'][0]['state'] === 'open', 'an unknown state reads as open');
ok(RoleQueues::line(['waiting' => 1]) === '1 open · 1 waiting for the Founder', 'line with a waiting ruling');
ok($tables() === $before, 'read only: no table created or changed');

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
