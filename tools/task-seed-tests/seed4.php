<?php
// tools/task-seed-tests/seed4.php: task-seed-4 on a real MariaDB (php seed4.php <live app/lib> <migration.sql>). Builds the Work
// queue's tables with the live GaugeTasks::ensure(), seeds 61 tasks as live has (the linked ones by their real titles), runs
// the migration twice the way DbUpdates::autoRun does (split on ';'), and checks goals, children, links, ids, decisions, briefs.
declare(strict_types=1);
[$lib, $mig] = [$argv[1] ?? '', $argv[2] ?? ''];
require_once $lib . '/ControlLog.php'; require_once $lib . '/GaugeTasks.php';
$pdo = new PDO('mysql:host=localhost;dbname=ts4;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['control_gauge_tasks', 'control_gauge_task_steps', 'control_gauge_task_adrs', 'control_gauge_task_moves', 'control_gauge_task_briefs'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
GaugeTasks::ensure($pdo);
$ok = 0; $bad = 0; $t = function (string $w, bool $p) use (&$ok, &$bad) { $p ? $ok++ : $bad++; echo ($p ? 'ok   ' : 'FAIL ') . "$w\n"; };
$links = json_decode((string) file_get_contents(__DIR__ . '/../../handoffs/task-seed-4/items.json'), true);
$named = []; $byId = []; foreach ($links['goals'] as $g) { foreach ($g['link'] ?? [] as [$ga, $it]) $named[] = [$ga, $it]; foreach ($g['link_ids'] ?? [] as $i) $byId[] = $i; }
$ins = $pdo->prepare("INSERT INTO control_gauge_tasks (id, at, gauge, item, kind, what, bucket) VALUES (?, UTC_TIMESTAMP(), ?, ?, 'fix', ?, 'task')");
for ($i = 1; $i <= 61; $i++) { [$ga, $it] = ($i >= 59 ? $named[$i - 59] ?? null : null) ?? ['area:old', 'old task ' . $i]; $ins->execute([$i, $ga, $it, $it]); }
$pdo->exec("INSERT INTO control_gauge_task_adrs (id, task_id, adr, at) VALUES (40, 1, '0162', UTC_TIMESTAMP())");
$sql = (string) file_get_contents($mig);
$run = function () use ($pdo, $sql) { foreach (array_filter(array_map('trim', explode(';', $sql))) as $s) { $s = trim(preg_replace('/^--.*$/m', '', $s)); if ($s !== '') $pdo->exec($s); } };
$run(); $n1 = (int) $pdo->query('SELECT COUNT(*) FROM control_gauge_tasks')->fetchColumn(); $a1 = (int) $pdo->query('SELECT COUNT(*) FROM control_gauge_task_adrs')->fetchColumn();
$run();
$t('run twice: nothing made twice', $n1 === (int) $pdo->query('SELECT COUNT(*) FROM control_gauge_tasks')->fetchColumn() && $a1 === (int) $pdo->query('SELECT COUNT(*) FROM control_gauge_task_adrs')->fetchColumn());
$goals = $pdo->query("SELECT * FROM control_gauge_tasks WHERE gauge = 'area:goals' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$t('10 goals, each a parent with an owner, a measure and a due date', count($goals) === 10 && !array_filter($goals, fn ($g) => $g['owner'] === '' || !str_contains((string) $g['done_when'], 'Measure:') || !str_contains((string) $g['done_when'], 'Due:') || $g['parent_id'] !== null));
$t('every goal has its decision attached', (int) $pdo->query("SELECT COUNT(DISTINCT g.id) FROM control_gauge_tasks g JOIN control_gauge_task_adrs a ON a.task_id = g.id WHERE g.gauge = 'area:goals'")->fetchColumn() === 10);
$kids = $pdo->query("SELECT c.*, p.gauge pg FROM control_gauge_tasks c LEFT JOIN control_gauge_tasks p ON p.id = c.parent_id WHERE c.id > 61 AND c.gauge <> 'area:goals'")->fetchAll(PDO::FETCH_ASSOC);
$t('every new child links to a goal', count($kids) === 58 && !array_filter($kids, fn ($k) => $k['pg'] !== 'area:goals'));
$t('every new task cites its source', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE id > 61 AND source = ''")->fetchColumn() === 0);
$ids = $pdo->query('SELECT id FROM control_gauge_tasks WHERE id > 61 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$t('ids named: 62 onward, contiguous (MAX(id)+1)', $ids === range(62, 61 + count($ids)));
$aid = $pdo->query('SELECT id FROM control_gauge_task_adrs WHERE id > 40 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$t('decision rows named from MAX(id)+1 too', $aid === range(41, 40 + count($aid)));
$t('the existing tasks named gain their goal; others untouched', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE id <= 61 AND parent_id IS NOT NULL")->fetchColumn() === count($named) + count($byId));
$t('Founder rulings: 13 Resolution tickets at Tier 5, each with its brief', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks t JOIN control_gauge_task_briefs b ON b.task_id = t.id WHERE t.ticket = 'resolution' AND t.tier = 5")->fetchColumn() === 13);
$t('Order #12 linked under the launch, not made twice', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE item LIKE 'Order #12%'")->fetchColumn() === 1);
$t('work tickets start at Planning', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE id > 61 AND ticket = 'work' AND stage <> 'planning'")->fetchColumn() === 0);
$t('the goal fields: due_on and measure on every goal, none on children', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE gauge = 'area:goals' AND due_on IS NOT NULL AND measure <> ''")->fetchColumn() === 10 && (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE gauge <> 'area:goals' AND due_on IS NOT NULL")->fetchColumn() === 0);
$t('records goal due Nov 30', $pdo->query("SELECT due_on FROM control_gauge_tasks WHERE item LIKE 'Goals and records%'")->fetchColumn() === '2026-11-30');
$q = GaugeTasks::queue($pdo);
$t('the queue reads them', count($q) >= 129);
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
