<?php
// tools/task-seed-tests/seed6.php: task-seed-6 on a real MariaDB (php seed6.php <live app/lib> <seed 4's> <seed 5's> <seed 6's migration>; since task-seed-6-2 the migration is 2026-11-28_task_seed_6b.sql and also seeds handoffs/task-seed-7's tickets).
// One ticket per agent in the CEO's wave order, task-seed-5's four team group tickets withdrawn (kept), run twice the way
// DbUpdates::autoRun does, a group ticket a person took left alone, before task-seed-5, before the goals.
declare(strict_types=1);
[$lib, $m4, $m5, $m6] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
if (!is_file($lib . '/GaugeTasks.php') || !is_file($m4) || !is_file($m5) || !is_file($m6)) { fwrite(STDERR, "usage: php seed6.php <live app/lib> <seed 4's> <seed 5's> <seed 6's migration>\n"); exit(2); }
require_once $lib . '/ControlLog.php'; require_once $lib . '/GaugeTasks.php'; require_once $lib . '/TeamRoster.php';
$root = new PDO('mysql:host=localhost;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ok = 0; $bad = 0; $t = function (string $w, bool $p) use (&$ok, &$bad) { $p ? $ok++ : $bad++; echo ($p ? 'ok   ' : 'FAIL ') . "$w\n"; };
$D = json_decode((string) file_get_contents(__DIR__ . '/../../handoffs/task-seed-6/items.json'), true);
$D7 = json_decode((string) file_get_contents(__DIR__ . '/../../handoffs/task-seed-7/items.json'), true)['new'];   // the session manager's tickets, seeded with task-seed-6-2
$run = function (PDO $pdo, string $file) { foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($file)))) as $s) { $s = trim(preg_replace('/^--.*$/m', '', $s)); if ($s !== '') $pdo->exec($s); } };
$fresh = function (string $db, array $seeds) use ($root, $run): PDO {
    $root->exec("DROP DATABASE IF EXISTS $db"); $root->exec("CREATE DATABASE $db CHARACTER SET utf8mb4");
    $pdo = new PDO("mysql:host=localhost;dbname=$db;charset=utf8mb4", 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $r = new ReflectionProperty(GaugeTasks::class, 'ready'); $r->setAccessible(true); $r->setValue(null, false); GaugeTasks::ensure($pdo);
    $ins = $pdo->prepare("INSERT INTO control_gauge_tasks (id, at, gauge, item, kind, what, bucket) VALUES (?, UTC_TIMESTAMP(), 'area:old', ?, 'fix', ?, 'task')");
    for ($i = 1; $i <= 61; $i++) $ins->execute([$i, 'old task ' . $i, 'old task ' . $i]);
    $pdo->exec("ALTER TABLE control_gauge_tasks ADD COLUMN lane VARCHAR(40) NULL, ADD COLUMN handoff VARCHAR(400) NULL");   // R2's columns
    foreach ($seeds as $s) $run($pdo, $s);
    return $pdo;
};
$n = fn (PDO $pdo, string $sql) => (int) $pdo->query($sql)->fetchColumn();
$mine = "who = 'task-seed-6 (Build & QA, 21)'";

$pdo = $fresh('ts6', [$m4, $m5]);
$before = $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks');
$pdo->exec("UPDATE control_gauge_tasks SET owner = 'Jason', owner_at = UTC_TIMESTAMP() WHERE item = 'The team release: the middle managers'");   // a person took one
$run($pdo, $m6); $n1 = $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks'); $run($pdo, $m6);
$t('run twice: nothing made twice, nothing deleted', $n1 === $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks') && $n1 - $before === 42 + count($D7));
$r7 = $pdo->query("SELECT c.*, p.item pi FROM control_gauge_tasks c LEFT JOIN control_gauge_tasks p ON p.id = c.parent_id WHERE c.who = 'task-seed-7 (Build & QA, 21)' ORDER BY c.id")->fetchAll(PDO::FETCH_ASSOC);
$t('task-seed-7: the session manager\'s ' . count($D7) . ' tickets, after the 42, each under its own goal', count($r7) === count($D7) && array_map(fn ($r) => (int) $r['id'], $r7) === range($before + 43, $before + 42 + count($D7)) && array_column($r7, 'pi') === array_column($D7, 'parent'));
$t('task-seed-7: each at its real phase, in its lane, its release and source named', array_column($r7, 'stage') === array_column($D7, 'stage') && array_column($r7, 'lane') === array_column($D7, 'lane') && array_column($r7, 'rel') === array_column($D7, 'rel') && !array_filter($r7, fn ($r) => $r['source'] === '' || $r['ticket'] !== 'work'));
$t('task-seed-7: the ticket for publishing on pass is there, at Implementation, with Jason\'s words', (bool) array_filter($r7, fn ($r) => $r['item'] === 'A release publishes the moment its check passes, not on the next 15-minute tick' && $r['stage'] === 'implementation' && str_contains($r['why'], 'Push to live should be done immediately, upon pass')));
$rows = $pdo->query("SELECT c.*, p.item pi FROM control_gauge_tasks c LEFT JOIN control_gauge_tasks p ON p.id = c.parent_id WHERE c.$mine ORDER BY c.id")->fetchAll(PDO::FETCH_ASSOC);
$t('42 agent tickets: 30 roles and the 12 advocates', count($rows) === 42 && count(array_filter($rows, fn ($r) => str_contains($r['item'], 'advocate A'))) === 12);
$t('no ticket for the Founder (1) or the CEO (2)', !array_filter($rows, fn ($r) => preg_match('/\((1|2)\)/', $r['item'])));
$t('each under the transition goal, in the Project Manager\'s lane, at Planning', !array_filter($rows, fn ($r) => !str_starts_with((string) $r['pi'], 'The transition cutover') || $r['lane'] !== 'project-manager' || $r['stage'] !== 'planning' || $r['ticket'] !== 'work'));
$t('ids follow the CEO\'s order (01 to 42), contiguous from MAX(id)+1', array_map(fn ($r) => (int) $r['id'], $rows) === range($before + 1, $before + 42) && !array_filter(array_keys($rows), fn ($i) => !str_starts_with($rows[$i]['item'], sprintf('Agent online %02d ', $i + 1))));
$num = fn (string $s) => preg_match('/\((\d+)\)(, upgraded in place)?$/', $s, $m) ? (int) $m[1] : (preg_match('/advocate (A\d+)/', $s, $m) ? $m[1] : null);
$order = array_map(fn ($r) => $num($r['item']), $rows);
$t('wave 1 is 8, 22, 16, 11, 3; wave 2 is 21, 24, 25, 20, 23, 28', array_slice($order, 0, 11) === [8, 22, 16, 11, 3, 21, 24, 25, 20, 23, 28]);
$t('wave 3 is 12, 26, the 12 advocates, 15, 27, 9, 18, 17', array_slice($order, 11, 19) === array_merge([12, 26], array_map(fn ($i) => 'A' . $i, range(1, 12)), [15, 27, 9, 18, 17]));
$t('wave 4 is 5, 6, 7, 4, 10, 19, 13, 14, 29, 30, 31, 32', array_slice($order, 30) === [5, 6, 7, 4, 10, 19, 13, 14, 29, 30, 31, 32]);
$t('every role from 3 to 32 once', (function () use ($order) { $r = array_values(array_filter($order, 'is_int')); sort($r); return $r === range(3, 32); })());
$t('the six active roles are upgraded in place, no other', (function () use ($rows, $num) { $u = array_map(fn ($r) => $num($r['item']), array_filter($rows, fn ($r) => str_ends_with($r['item'], ', upgraded in place'))); sort($u); return array_values($u) === [8, 20, 21, 23, 26, 28]; })());
$t('priority follows the wave (1, 2, then 3)', array_map(fn ($r) => (int) $r['priority'], $rows) === array_merge(array_fill(0, 5, 1), array_fill(0, 6, 2), array_fill(0, 31, 3)));
$t('each cites its draft and 0180\'s appendix, and says when it counts as live', !array_filter($rows, fn ($r) => !str_contains($r['source'], 'research/ai-results/job18-team/') || !str_contains($r['source'], 'decisions/0180') || !str_contains($r['why'], 'Run a test ticket passes on /admin/config/team')));
$g = $pdo->query("SELECT item, undone_at, done_how, owner FROM control_gauge_tasks WHERE item LIKE 'The team release:%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$t('task-seed-5\'s group tickets are kept: 3 withdrawn with why, the one a person took left alone', count($g) === 4 && count(array_filter($g, fn ($r) => $r['undone_at'] !== null && str_starts_with($r['done_how'], 'Withdrawn: replaced by one ticket per agent'))) === 3 && count(array_filter($g, fn ($r) => $r['owner'] === 'Jason' && $r['undone_at'] === null)) === 1);
$t('no other ticket is withdrawn', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE undone_at IS NOT NULL") === 3);
$q = []; foreach (GaugeTasks::queue($pdo) as $r) $q[$r['item']] = $r['state'];
$t('the queue shows the agents\' tickets open and the group tickets as withdrawn', ($q['Agent online 01 (wave 1): Project Manager (8), upgraded in place'] ?? '') !== 'withdrawn' && isset($q['Agent online 42 (wave 4): Partnerships & Retention Leads (32)']) && ($q['The team release: the executives'] ?? '') === 'withdrawn' && ($q['The team release: the middle managers'] ?? '') === 'taken');

$pdo = $fresh('ts6b', [$m4]); $run($pdo, $m6); $run($pdo, $m6);
$t('before task-seed-5: the 42 tickets, no error', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE $mine") === 42);
$pdo = $fresh('ts6c', []); $run($pdo, $m6);
$t('before the goals: no ticket without its goal, no error', $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks') === 61);
foreach (['ts6', 'ts6b', 'ts6c'] as $db) $root->exec("DROP DATABASE IF EXISTS $db");
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
