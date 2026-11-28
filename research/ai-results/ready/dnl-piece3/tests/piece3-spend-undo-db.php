<?php
// tools/piece3-tests/piece3-spend-undo-db.php — against a throwaway MySQL schema only (its name must contain "test"):
//   1. log() writes a row with cost_micros (the column is there and filled).
//   2. undo() refuses a run already undone and a proposal.
//   3. undo() whose action fails gives its claim back (undone_at NULL after), or, if it succeeds, the run is marked undone once.
// Never the live or staging database. No outside call; nothing here moves money.
// Run: TV_TEST_DSN='mysql:host=127.0.0.1;dbname=tv_test' TV_TEST_USER=... TV_TEST_PASS=... php tools/piece3-tests/piece3-spend-undo-db.php
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
$fails = 0;
function check(string $name, bool $ok, string $why = ''): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n"; if (!$ok) $fails++; }

$dsn = (string) getenv('TV_TEST_DSN');
if ($dsn === '' || !preg_match('/dbname=[^;]*test/i', $dsn)) { echo "FAIL: set TV_TEST_DSN to a throwaway schema whose name contains \"test\"\n"; exit(1); }
$pdo = new PDO($dsn, (string) getenv('TV_TEST_USER'), (string) getenv('TV_TEST_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require_once $root . '/app/lib/TeamRunner.php';

TeamRunner::ensure($pdo);
$col = $pdo->query("SHOW COLUMNS FROM team_runs LIKE 'cost_micros'")->fetch(PDO::FETCH_ASSOC);
check('team_runs has cost_micros', (bool) $col);

$slug = 'piece3-test';
$pdo->prepare('DELETE FROM team_runs WHERE role = ?')->execute([$slug]);   // this throwaway schema's own test rows only
$role = ['slug' => $slug, 'name' => 'Piece 3 test role'];

// 1. log() books its cost
try {
    $m = new ReflectionMethod('TeamRunner', 'log'); $m->setAccessible(true);
    $m->invoke(null, $pdo, $role, 0, 'gate', 'a test', 'Proposed: a test', 'proposed', '', ['move' => 'building'], 'A proposal changes nothing until a person accepts it', null, 'task:0');
    $row = $pdo->query("SELECT state, cost_micros FROM team_runs WHERE role = 'piece3-test' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check('log() wrote the run', (bool) $row && $row['state'] === 'proposed');
    check('log() booked the step cost ($0 today)', (bool) $row && (int) $row['cost_micros'] === (int) TeamRunner::STEP_COST_MICROS['gate']);
    $m->invoke(null, $pdo, $role, 0, 'gate', 'a test', 'Moved: a test', 'done', '', null, '', null, 'task:0');   // done with no Undo
    $row = $pdo->query("SELECT state, cost_micros FROM team_runs WHERE role = 'piece3-test' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check('a done step with no Undo is refused and books nothing', (bool) $row && $row['state'] === 'refused' && (int) $row['cost_micros'] === 0);
    check('spentThisMonth reads $0', TeamRunner::spentThisMonth($pdo, $slug) === 0);
} catch (Throwable $x) { check('log() ran', false, $x->getMessage() . ' (ControlLog\'s table may be missing on the test schema: copy it from the live schema)'); }

$ins = $pdo->prepare("INSERT INTO team_runs (at, role, task_id, step, state, undo_how, undo_data, undone_at, undone_by) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?)");

// 2. a second Undo and a proposal are refused
$ins->execute([$slug, 2147480000, 'gate', 'done', 'Moves it back to Planning', json_encode(['to' => 'planning']), gmdate('Y-m-d H:i:s'), 'an earlier test']);
$id = (int) $pdo->lastInsertId();
try { TeamRunner::undo($pdo, $id, 'the test'); check('a second Undo is refused', false, 'it went through'); }
catch (RuntimeException $x) { check('a second Undo is refused', str_starts_with($x->getMessage(), 'Already undone'), $x->getMessage()); }
$ins->execute([$slug, 2147480000, 'gate', 'proposed', '', null, null, '']);
$id = (int) $pdo->lastInsertId();
try { TeamRunner::undo($pdo, $id, 'the test'); check('Undo of a proposal is refused', false, 'it went through'); }
catch (RuntimeException $x) { check('Undo of a proposal is refused', str_contains($x->getMessage(), 'Accept or Dismiss'), $x->getMessage()); }

// 3. a failing Undo gives its claim back (task 2147480000 doesn't exist here)
$ins->execute([$slug, 2147480000, 'gate', 'done', 'Moves it back to Planning', json_encode(['to' => 'planning']), null, '']);
$id = (int) $pdo->lastInsertId();
$threw = false; try { TeamRunner::undo($pdo, $id, 'the test'); } catch (Throwable $x) { $threw = true; }
$after = $pdo->query('SELECT undone_at, undone_by FROM team_runs WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
check('the claim matches the outcome', $threw ? ($after['undone_at'] === null && $after['undone_by'] === '') : ($after['undone_at'] !== null && $after['undone_by'] === 'the test'),
    'threw ' . ($threw ? 'yes' : 'no') . ', undone_at ' . var_export($after['undone_at'], true));

$pdo->prepare('DELETE FROM team_runs WHERE role = ?')->execute([$slug]);   // this throwaway schema's own test rows only
echo $fails === 0 ? "ALL PASS\n" : $fails . " FAIL\n";
exit($fails === 0 ? 0 : 1);
