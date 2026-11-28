<?php
// tools/task-seed-tests/seed5.php: task-seed-5 on a real MariaDB (php seed5.php <live app/lib> <seed 4's migration> <seed 5's migration>).
// The Work queue's tables from the live GaugeTasks::ensure(), 61 tasks as live has, task-seed-4 run, R2's lane column, then
// task-seed-5 twice the way DbUpdates::autoRun does (split on ';'): tickets under their goals, phases, releases, lanes, the
// tickets of task-seed-4 moved in place, a person's move and words kept, no lane column, no goals.
declare(strict_types=1);
[$lib, $mig4, $mig5] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if (!is_file($lib . '/GaugeTasks.php') || !is_file($mig4) || !is_file($mig5)) { fwrite(STDERR, "usage: php seed5.php <live app/lib> <seed 4's migration> <seed 5's migration>\n"); exit(2); }
require_once $lib . '/ControlLog.php'; require_once $lib . '/GaugeTasks.php'; require_once $lib . '/TeamRoster.php';
$root = new PDO('mysql:host=localhost;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ok = 0; $bad = 0; $t = function (string $w, bool $p) use (&$ok, &$bad) { $p ? $ok++ : $bad++; echo ($p ? 'ok   ' : 'FAIL ') . "$w\n"; };
$D = json_decode((string) file_get_contents(__DIR__ . '/../../handoffs/task-seed-5/items.json'), true);
$run = function (PDO $pdo, string $file) { foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($file)))) as $s) { $s = trim(preg_replace('/^--.*$/m', '', $s)); if ($s !== '') $pdo->exec($s); } };
$fresh = function (string $db, bool $seed4, bool $lane) use ($root, $run, $mig4): PDO {
    $root->exec("DROP DATABASE IF EXISTS $db"); $root->exec("CREATE DATABASE $db CHARACTER SET utf8mb4");
    $pdo = new PDO("mysql:host=localhost;dbname=$db;charset=utf8mb4", 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['control_gauge_tasks', 'control_gauge_task_steps', 'control_gauge_task_adrs', 'control_gauge_task_moves', 'control_gauge_task_briefs'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
    $r = new ReflectionProperty(GaugeTasks::class, 'ready'); $r->setAccessible(true); $r->setValue(null, false); GaugeTasks::ensure($pdo);
    $ins = $pdo->prepare("INSERT INTO control_gauge_tasks (id, at, gauge, item, kind, what, bucket) VALUES (?, UTC_TIMESTAMP(), 'area:old', ?, 'fix', ?, 'task')");
    for ($i = 1; $i <= 61; $i++) $ins->execute([$i, 'old task ' . $i, 'old task ' . $i]);
    if ($seed4) $run($pdo, $mig4);
    if ($lane) $pdo->exec("ALTER TABLE control_gauge_tasks ADD COLUMN lane VARCHAR(40) NULL, ADD COLUMN handoff VARCHAR(400) NULL");   // R2's columns
    return $pdo;
};
$n = fn (PDO $pdo, string $sql) => (int) $pdo->query($sql)->fetchColumn();
$mine = "who = 'task-seed-5 (Build & QA, 21)'";

$pdo = $fresh('ts5', true, true);
$before = $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks');
$pdo->exec("UPDATE control_gauge_tasks SET source = 'A person wrote this' WHERE item LIKE 'The promise check:%'");   // a person's words, before the seed
$pdo->exec("UPDATE control_gauge_tasks SET stage = 'implementation', rel = 'theirs.zip' WHERE item LIKE 'The Marketplace''s four sections for Jan 1%'");   // a person's move
$run($pdo, $mig5); $n1 = $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks'); $m1 = $n($pdo, 'SELECT COUNT(*) FROM control_gauge_task_moves');
$run($pdo, $mig5);
$t('run twice: no ticket and no move made twice', $n1 === $n($pdo, 'SELECT COUNT(*) FROM control_gauge_tasks') && $m1 === $n($pdo, 'SELECT COUNT(*) FROM control_gauge_task_moves'));
$t(count($D['new']) . ' new tickets, none of task-seed-4\'s made again', $n1 - $before === count($D['new']) && $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE $mine") === count($D['new']));
$t('every new ticket is under a goal', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks c JOIN control_gauge_tasks p ON p.id = c.parent_id AND p.gauge = 'area:goals' WHERE c.$mine") === count($D['new']));
$t('each under the goal it names', !array_filter($D['new'], function ($x) use ($pdo) { $s = $pdo->prepare('SELECT p.item FROM control_gauge_tasks c JOIN control_gauge_tasks p ON p.id = c.parent_id WHERE c.gauge = ? AND c.item = ?'); $s->execute([$x['gauge'], $x['what']]); return $s->fetchColumn() !== $x['parent']; }));
$t('every new ticket cites its draft or release, and why', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE $mine AND (source = '' OR why = '')") === 0);
$ids = $pdo->query("SELECT id FROM control_gauge_tasks WHERE $mine ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$t('ids named: contiguous from MAX(id)+1', array_map('intval', $ids) === range($before + 1, $before + count($ids)));
$want = []; foreach ($D['new'] as $x) $want[$x['ticket'] . ':' . $x['stage']] = ($want[$x['ticket'] . ':' . $x['stage']] ?? 0) + 1; ksort($want);
$got = []; foreach ($pdo->query("SELECT CONCAT(ticket, ':', stage) k, COUNT(*) c FROM control_gauge_tasks WHERE $mine GROUP BY k") as $r) $got[$r['k']] = (int) $r['c']; ksort($got);
$t('each at its real phase (' . json_encode($got) . ')', $got === $want);
$t('every phase is one the Work queue knows for its ticket type', !array_filter($pdo->query("SELECT ticket, stage FROM control_gauge_tasks WHERE $mine")->fetchAll(PDO::FETCH_ASSOC), fn ($r) => !isset(GaugeTasks::STAGES[$r['ticket']][$r['stage']])));
$t('a ticket at Implementation names its release', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE $mine AND stage = 'implementation'") === 3 && $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE $mine AND stage = 'implementation' AND rel NOT IN ('pause-1a-4.zip', 'task-seed-4.zip', 'task-seed-5.zip')") === 0);
$t('release names fit the Work queue\'s rule', !array_filter($pdo->query("SELECT rel FROM control_gauge_tasks WHERE $mine AND rel <> ''")->fetchAll(PDO::FETCH_COLUMN), fn ($r) => !preg_match('/^[A-Za-z0-9._-]{1,120}$/', $r)));
$lanes = $pdo->query("SELECT lane FROM control_gauge_tasks WHERE $mine")->fetchAll(PDO::FETCH_COLUMN);
$t('every new ticket in one of the seven lanes', count($lanes) === count($D['new']) && !array_filter($lanes, fn ($l) => !in_array($l, TeamRoster::LANES, true)));
$t('the lane it names (titles with apostrophes too)', !array_filter($D['new'], function ($x) use ($pdo) { $s = $pdo->prepare('SELECT lane FROM control_gauge_tasks WHERE gauge = ? AND item = ?'); $s->execute([$x['gauge'], $x['what']]); return $s->fetchColumn() !== $x['lane']; }));
$one = $pdo->query("SELECT * FROM control_gauge_tasks WHERE item LIKE 'The pause:%'")->fetch(PDO::FETCH_ASSOC);
$t('TeamRoster::lane reads the stored lane', TeamRoster::lane($pdo, $one) === 'editorial-director');
$gc = $pdo->query("SELECT * FROM control_gauge_tasks WHERE item LIKE 'The daily goal check:%'")->fetch(PDO::FETCH_ASSOC);
$t('task-seed-4\'s goal check moved in place to Building, its release named, its build in Source', $gc['stage'] === 'building' && $gc['rel'] === 'goal-check.zip' && str_contains($gc['source'], 'handoffs/goal-check/') && $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE item LIKE 'The daily goal check:%'") === 1);
$t('its move is in its History, once', $n($pdo, "SELECT COUNT(*) FROM control_gauge_task_moves WHERE task_id = " . (int) $gc['id'] . " AND from_stage = 'planning' AND to_stage = 'building'") === 1);
$t('moves named from MAX(id)+1', array_map('intval', $pdo->query('SELECT id FROM control_gauge_task_moves ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) === range(1, $m1) && $m1 === 2);
$ms = $pdo->query("SELECT * FROM control_gauge_tasks WHERE item LIKE 'The Marketplace''s four sections for Jan 1%'")->fetch(PDO::FETCH_ASSOC);
$t('a person\'s move is never undone', $ms['stage'] === 'implementation' && $ms['rel'] === 'theirs.zip');
$t('a person\'s Source is never overwritten', $pdo->query("SELECT source FROM control_gauge_tasks WHERE item LIKE 'The promise check:%'")->fetchColumn() === 'A person wrote this');
$t('a draft linked on a Planning ticket leaves its phase', $pdo->query("SELECT CONCAT(stage, '|', source) FROM control_gauge_tasks WHERE item LIKE 'Role queues as the agents'' inbox%'")->fetchColumn() === 'planning|' . array_values(array_filter($D['moved'], fn ($m) => str_starts_with($m['item'], 'Role queues')))[0]['source']);
$pdo->exec("UPDATE control_gauge_tasks SET stage = 'confirmation' WHERE id = " . (int) $gc['id']); $run($pdo, $mig5);
$t('a later move by a person stays on a rerun', $pdo->query('SELECT stage FROM control_gauge_tasks WHERE id = ' . (int) $gc['id'])->fetchColumn() === 'confirmation' && $n($pdo, 'SELECT COUNT(*) FROM control_gauge_task_moves') === $m1);
$t('nothing deleted, no goal changed', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE gauge = 'area:goals'") === 10 && $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE id <= 61") === 61);
$t('the queue reads them', count(GaugeTasks::queue($pdo)) >= $n1 - 61);

$pdo = $fresh('ts5b', true, false); $run($pdo, $mig5); $run($pdo, $mig5);
$t('before R2\'s lane column: the same tickets, no error', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks WHERE $mine") === count($D['new']));
$pdo = $fresh('ts5c', false, true); $run($pdo, $mig5);
$t('before task-seed-4 (no goals): no ticket without its goal, no error', $n($pdo, "SELECT COUNT(*) FROM control_gauge_tasks") === 61);
foreach (['ts5', 'ts5b', 'ts5c'] as $db) $root->exec("DROP DATABASE IF EXISTS $db");
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
