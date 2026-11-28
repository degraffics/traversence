<?php
// tools/work-queue-tests/task-thread-tests.php — Work queue phase 2, release 1 (the thread). Plain PHP; prints PASS/FAIL.
// The first part needs no database. The second runs only against a test database named in TASK_THREAD_TEST_DSN (its
// dbname must contain "test"), with TASK_THREAD_TEST_USER / TASK_THREAD_TEST_PASS; never against the live site.
// No money moves anywhere in these tests.
declare(strict_types=1);

$lib = rtrim((string) (getenv('APP_LIB') ?: __DIR__ . '/../../app/lib'), '/');
require_once $lib . '/TaskThread.php';

$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fail++; }
function throwsMsg(callable $f): string { try { $f(); return ''; } catch (Throwable $x) { return $x->getMessage(); } }

// ---- no database -------------------------------------------------------------------------------------------------------
$history = [
    ['at' => '2026-12-01 10:00:00', 'what' => 'Task #12 added to the Work queue: Fix the thing', 'who' => 'Jason'],
    ['at' => '2026-12-01 10:05:00', 'what' => 'Task #12 post added: hello', 'who' => 'Jason'],
    ['at' => '2026-12-01 11:00:00', 'what' => 'Task #12 moved: Planning → Building', 'who' => 'Jason'],
];
$posts = [['id' => 7, 'at' => '2026-12-01 10:05:00', 'who' => 'Jason', 'uid' => 1, 'body' => 'hello', 'edited_at' => null]];
$notes = TaskThread::notesFor([
    ['task' => 12, 'line' => 'Built, in review', 'link' => 'https://github.com/example/pull/1', 'at' => '2026-12-01T12:00:00Z'],
    ['task' => 13, 'line' => 'Not this task', 'link' => '', 'at' => '2026-12-01T09:00:00Z'],
    ['task' => 12, 'line' => 'Bad link', 'link' => 'javascript:alert(1)', 'at' => '2026-12-01T12:30:00Z'],
    ['task' => 12, 'line' => '   ', 'link' => '', 'at' => '2026-12-01T12:40:00Z'],
    ['task' => 12, 'line' => 'No time', 'link' => ''],
], 12);
check('notes: only this task\'s, with words and a time', count($notes) === 2);
check('notes: an at in ISO form becomes UTC Y-m-d H:i:s', $notes[0]['at'] === '2026-12-01 12:00:00');
check('notes: a javascript: link is dropped', $notes[1]['link'] === '');

$t = TaskThread::merge(12, $posts, $history, $notes);
check('merge: system lines, the post and the notes (the post\'s own log line left out)', count($t) === 5);
check('merge: oldest first, newest at the foot', $t[0]['text'] === 'added to the Work queue: Fix the thing' && $t[4]['text'] === 'Bad link');
check('merge: the post sits between the system lines by time', $t[1]['kind'] === 'person' && $t[1]['post_id'] === 7);
check('merge: "Task #12 " is taken off a system line', $t[2]['text'] === 'moved: Planning → Building');
check('merge: no line reads "post added"', !array_filter($t, fn ($e) => str_contains($e['text'], 'post added')));

check('cleanBody: empty is refused', throwsMsg(fn () => TaskThread::cleanBody("  \r\n ")) === 'Write something first.');
check('cleanBody: past 4,000 characters is refused', throwsMsg(fn () => TaskThread::cleanBody(str_repeat('a', 4001))) !== '');
check('cleanBody: line breaks kept, \\r dropped', TaskThread::cleanBody("a\r\nb") === "a\nb");

$html = TaskThread::render(12, TaskThread::merge(12, [['id' => 9, 'at' => gmdate('Y-m-d H:i:s'), 'who' => 'Jason', 'uid' => 1, 'body' => '<script>x</script>', 'edited_at' => null]], [], []), true, '/admin/x.php', ['csrf' => 'tok'], 'Jason', 1);
check('render: words are escaped', !str_contains($html, '<script>x</script>') && str_contains($html, '&lt;script&gt;'));
check('render: the box at the foot, with spell check', str_contains($html, 'name="body"') && str_contains($html, 'spellcheck="true"'));
check('render: the page\'s own token goes with each form', substr_count($html, 'name="csrf" value="tok"') >= 2);
check('render: Edit and Undo on one\'s own post', str_contains($html, '<summary>Edit</summary>') && str_contains($html, 'value="undo"'));
$other = TaskThread::render(12, TaskThread::merge(12, [['id' => 9, 'at' => gmdate('Y-m-d H:i:s'), 'who' => 'Jason', 'uid' => 1, 'body' => 'hi', 'edited_at' => null]], [], []), true, '/admin/x.php', [], 'Someone', 2);
check('render: no Edit on someone else\'s post', !str_contains($other, '<summary>Edit</summary>'));
$closed = TaskThread::render(12, [], false, '/admin/x.php');
check('render: a closed task has no box', !str_contains($closed, 'name="body"') && str_contains($closed, 'kept as it stands'));
check('array_is_list_compat', array_is_list_compat([1, 2]) && !array_is_list_compat(['a' => 1]));

// ---- a test database only ----------------------------------------------------------------------------------------------
$dsn = (string) getenv('TASK_THREAD_TEST_DSN');
if ($dsn === '' || !preg_match('/dbname=[^;]*test/i', $dsn)) {
    echo "SKIP: database tests (set TASK_THREAD_TEST_DSN to a test database whose name contains \"test\")\n";
} else {
    $pdo = new PDO($dsn, (string) getenv('TASK_THREAD_TEST_USER'), (string) getenv('TASK_THREAD_TEST_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    TaskThread::ensure($pdo); TaskThread::ensure($pdo);
    check('ensure: safe twice', true);
    $tid = GaugeTasks::add($pdo, 'area:thread-test', 'Thread test ' . uniqid(), 'the thread\'s tests', 'tests', 'Test Staff', 990001);
    $pid = TaskThread::post($pdo, $tid, "First post\nsecond line", 'Test Staff', 990001);
    check('post: kept', count(TaskThread::posts($pdo, $tid)) === 1);
    check('post: a member (no staff name) can\'t post', throwsMsg(fn () => TaskThread::post($pdo, $tid, 'x', '', 0)) !== '');
    check('edit: not someone else\'s', throwsMsg(fn () => TaskThread::edit($pdo, $pid, 'changed', 'Other Staff', 990002)) === 'Only whoever wrote a post can edit it.');
    check('edit: one\'s own', TaskThread::edit($pdo, $pid, 'First post, edited', 'Test Staff', 990001) === 'Saved. The old words are in its History.');
    check('edit: the same words change nothing', TaskThread::edit($pdo, $pid, 'First post, edited', 'Test Staff', 990001) === 'Nothing changed.');
    check('edit: the old words are in the History', (bool) array_filter(GaugeTasks::history($pdo, $tid), fn ($h) => str_contains((string) $h['what'], 'post edited: "First post second line"')));
    $th = TaskThread::thread($pdo, $tid);
    check('thread: the post and the task\'s added line, no "post" log lines', (bool) array_filter($th, fn ($e) => $e['kind'] === 'person' && $e['edited'])
        && (bool) array_filter($th, fn ($e) => $e['kind'] === 'system' && str_starts_with($e['text'], 'added to the Work queue'))
        && !array_filter($th, fn ($e) => $e['kind'] === 'system' && str_starts_with($e['text'], 'post ')));
    check('handle: post', TaskThread::handle($pdo, ['thread_do' => 'post', 'task' => $tid, 'body' => 'Second'], 'Test Staff', 990001) === 'Posted.');
    check('undo: one\'s own, within a day', str_starts_with(TaskThread::undo($pdo, $pid, 'Test Staff', 990001), 'Undone'));
    check('undo: the row kept, off the thread', count(TaskThread::posts($pdo, $tid)) === 1);
    GaugeTasks::withdraw($pdo, $tid, 'the thread\'s tests are done', 'Test Staff');
    check('post: a withdrawn task\'s thread is kept as it stands', str_contains(throwsMsg(fn () => TaskThread::post($pdo, $tid, 'late', 'Test Staff', 990001)), 'kept as it stands'));
    $key = 'test-' . uniqid();
    $rul = [['key' => $key, 'what' => 'Ruling test ' . $key, 'why' => 'the thread\'s tests', 'gauge' => 'area:thread-test']];
    check('rulings: added once', TaskThread::applyRulings($pdo, $rul) === 1);
    check('rulings: not again', TaskThread::applyRulings($pdo, $rul) === 0);
    check('rulings: a bad key is left alone', TaskThread::applyRulings($pdo, [['key' => 'bad key!', 'what' => 'x', 'why' => 'y']]) === 0);
    $st = $pdo->prepare('SELECT id FROM control_gauge_tasks WHERE source = ?'); $st->execute(['task-rulings.json · ' . $key]);
    if ($rid = (int) $st->fetchColumn()) GaugeTasks::withdraw($pdo, $rid, 'the thread\'s tests are done', 'Test Staff');
}

echo $fail ? "FAIL: $fail check(s) failed\n" : "PASS: all checks\n";
exit($fail ? 1 : 0);
