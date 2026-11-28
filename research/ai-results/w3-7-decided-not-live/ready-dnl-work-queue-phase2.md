**Skipped, and why (also recorded in `release.json`):**

- **The attached DRAFT (job 19) is empty.** There was nothing to review, so this release is drafted fresh from `handoffs/work-queue-phase2.md` and the live `GaugeTasks.php`.
- **Paused or waiting for Jason, and left untouched:**
  - M2b, which Jason paused on 2026-11-24.
  - Order #12's books ("I will address this after holiday").
  - Nothing in phase 2 itself is paused or waiting for Jason. The 2026-11-27 note makes the thread Tier 3, in the Project Manager's lane.
- **Not drafted, because of sequencing or dependencies:**
  - **Release 2, Ask Claude (`task_assist`).** It waits for the agents' own $40 counter in `AiClient`. 0180's "Separate each" correction says "nothing is spent from (3) until its counter is live", and that counter ships with the AI items release. `AiClient.php` isn't attached.
  - **Release 3, Start the build and Gemini's public-page read.** These come after release 1. Gap 1 (Gemini) was waiting for Jason; 0180's paid Google AI note has since answered it, but it is still release 3's work.
  - **The Executive channel ("+ Add → To: the CEO").** It is its own release after this one.
  - **Role queues.** The plan lists them with release 1, but they need `TeamRoster.php` and the Work queue page, and neither is attached.
  - **Notifications (`Notify::send`).** `Notify.php` isn't attached.

=== FILE: files/app/lib/TaskThread.php ===
<?php
// app/lib/TaskThread.php — a task's thread (Work queue phase 2, release 1; handoffs/work-queue-phase2.md "The design",
// and its note of 2026-11-27: Tier 3, the Project Manager's (8) lane). The last section of a task's page, one column,
// newest at the foot, as a chat:
//   - staff posts, typed in the box at the foot (spell check, 0144), kept in control_gauge_task_posts;
//   - the system's lines, read from the task's History in the logbook (GaugeTasks::history): no second record;
//   - the build session's own words, from releases/task-notes.json (the session manager writes it; read by the hourly path).
// Release 1 has no AI: Ask Claude (release 2) and Start the build and Gemini (release 3) come later.
// The session manager's rulings (releases/task-rulings.json) become tasks once each (applyRulings).
// Privacy (0180 §4 territory 5, staff messages): posts carry a staff member's name and stay on the admin page; they never
// travel in GaugeTasks::report() (0145 §1). Every press is in the board's logbook (ControlLog), as every Work queue press is.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';
require_once __DIR__ . '/GaugeTasks.php';

final class TaskThread
{
    /** A post's longest length, in characters. */
    public const MAX = 4000;
    /** A post's Undo is offered for a day, as GaugeTasks::UNDO_HOURS; after that it's edited, never taken off. */
    public const UNDO_HOURS = 24;
    private static bool $ready = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        GaugeTasks::ensure($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_task_posts (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL,
            at DATETIME NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', kind VARCHAR(10) NOT NULL DEFAULT 'person', who VARCHAR(80) NOT NULL DEFAULT '', uid INT NOT NULL DEFAULT 0,
            body TEXT NOT NULL, edited_at DATETIME NULL, undone_at DATETIME NULL, undone_by VARCHAR(80) NOT NULL DEFAULT '',
            KEY task_at (task_id, at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");   // 2026-11-28_task_thread.sql makes the same table
        self::$ready = true;
    }

    /** A post's words: line breaks kept, trimmed, never empty, never past MAX. */
    public static function cleanBody(string $body): string
    {
        $body = trim(str_replace("\r", '', $body));
        if ($body === '') throw new RuntimeException('Write something first.');
        if (mb_strlen($body) > self::MAX) throw new RuntimeException('That\'s longer than 4,000 characters: split it into two posts.');
        return $body;
    }

    /** Post on a task's thread. Only staff post; a done or withdrawn task's thread is kept as it stands. Returns the post's id. */
    public static function post(PDO $pdo, int $taskId, string $body, string $who, int $uid, string $tz = ''): int
    {
        self::ensure($pdo);
        self::openTask($pdo, $taskId);
        if (trim($who) === '') throw new RuntimeException('Only a staff member can post on a task.');
        $body = self::cleanBody($body);
        $pdo->prepare("INSERT INTO control_gauge_task_posts (task_id, at, tz, kind, who, uid, body) VALUES (?, UTC_TIMESTAMP(), ?, 'person', ?, ?, ?)")
            ->execute([$taskId, self::tz($tz), mb_substr(trim($who), 0, 80), $uid, $body]);
        $id = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', 'Task #' . $taskId . ' post added: ' . self::line($body), $who, '', $tz);
        return $id;
    }

    /** Edit a post (CLAUDE.md "Edit or Undo"): only whoever wrote it; the old words kept in the logbook (its History). */
    public static function edit(PDO $pdo, int $postId, string $body, string $who, int $uid, string $tz = ''): string
    {
        self::ensure($pdo);
        $p = self::postRow($pdo, $postId);
        if (!$p || $p['undone_at'] !== null) throw new RuntimeException('That post isn\'t on the thread any more.');
        if ((string) $p['kind'] !== 'person' || !self::mine($p, $who, $uid)) throw new RuntimeException('Only whoever wrote a post can edit it.');
        self::openTask($pdo, (int) $p['task_id']);
        $body = self::cleanBody($body);
        if ($body === (string) $p['body']) return 'Nothing changed.';
        $pdo->prepare('UPDATE control_gauge_task_posts SET body = ?, edited_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$body, $postId]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $p['task_id'] . ' post edited: "' . mb_substr(self::line((string) $p['body']), 0, 120) . '" → "' . mb_substr(self::line($body), 0, 120) . '"', $who, '', $tz);
        return 'Saved. The old words are in its History.';
    }

    /** Undo a post within a day: only whoever wrote it; the row kept (undone_at) and its words in the logbook. */
    public static function undo(PDO $pdo, int $postId, string $who, int $uid, string $tz = ''): string
    {
        self::ensure($pdo);
        $p = self::postRow($pdo, $postId);
        if (!$p || $p['undone_at'] !== null) throw new RuntimeException('It was already undone, or it isn\'t here any more.');
        if ((string) $p['kind'] !== 'person' || !self::mine($p, $who, $uid)) throw new RuntimeException('Only whoever wrote a post can undo it.');
        self::openTask($pdo, (int) $p['task_id']);
        if ((int) strtotime($p['at'] . ' UTC') < time() - self::UNDO_HOURS * 3600) throw new RuntimeException('Undo is offered for a day; this post is older. Edit it instead.');
        $pdo->prepare('UPDATE control_gauge_task_posts SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ? AND undone_at IS NULL')->execute([mb_substr($who, 0, 80), $postId]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $p['task_id'] . ' post undone: "' . mb_substr(self::line((string) $p['body']), 0, 200) . '"', $who, '', $tz);
        return 'Undone: the post is off the thread. Its words are in the logbook.';
    }

    /** A task's posts on the thread (not undone), oldest first. */
    public static function posts(PDO $pdo, int $taskId): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_task_posts WHERE task_id = ? AND undone_at IS NULL ORDER BY at, id'); $st->execute([$taskId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The build session's own words for one task, from releases/task-notes.json (decoded): [{task, line, link, at}].
     * A line with no task, no words or no time is left out; a link that isn't http(s) is dropped.
     */
    public static function notesFor(array $all, int $taskId): array
    {
        $out = [];
        foreach ($all as $n) {
            if (!is_array($n) || (int) ($n['task'] ?? 0) !== $taskId) continue;
            $line = trim((string) preg_replace('/\s+/u', ' ', (string) ($n['line'] ?? ''))); if ($line === '') continue;
            $at = self::utc((string) ($n['at'] ?? '')); if ($at === '') continue;
            $link = trim((string) ($n['link'] ?? ''));
            if ($link !== '' && !preg_match('~^https?://[^\s"<>]+$~i', $link)) $link = '';
            $out[] = ['at' => $at, 'line' => mb_substr($line, 0, 400), 'link' => $link];
        }
        return $out;
    }

    /** A JSON file the hourly path brings (task-notes.json, task-rulings.json) as a list, or [] when it's missing or unreadable. */
    public static function loadJson(string $path): array
    {
        if ($path === '' || !is_file($path)) return [];
        $j = json_decode((string) @file_get_contents($path), true);
        return is_array($j) && array_is_list_compat($j) ? $j : [];
    }

    /**
     * The thread, oldest first, newest at the foot: posts, the system's lines from History (a post's own logbook line left
     * out: the post itself is on the thread) and the session's notes. Each entry: at (UTC), kind (person | system | session),
     * who, text, link, post_id, uid, edited.
     */
    public static function merge(int $taskId, array $posts, array $history, array $notes): array
    {
        $out = [];
        $postLine = 'Task #' . $taskId . ' post ';
        foreach ($history as $h) {
            $what = (string) ($h['what'] ?? '');
            if (str_starts_with($what, $postLine)) continue;
            $out[] = ['at' => (string) ($h['at'] ?? ''), 'kind' => 'system', 'who' => (string) ($h['who'] ?? ''),
                'text' => (string) preg_replace('/^Task #' . $taskId . ' /', '', $what), 'link' => '', 'post_id' => 0, 'uid' => 0, 'edited' => false];
        }
        foreach ($posts as $p)
            $out[] = ['at' => (string) $p['at'], 'kind' => 'person', 'who' => (string) $p['who'], 'text' => (string) $p['body'], 'link' => '',
                'post_id' => (int) $p['id'], 'uid' => (int) $p['uid'], 'edited' => ($p['edited_at'] ?? null) !== null];
        foreach ($notes as $n)
            $out[] = ['at' => (string) $n['at'], 'kind' => 'session', 'who' => 'the build session, by the session manager', 'text' => (string) $n['line'],
                'link' => (string) $n['link'], 'post_id' => 0, 'uid' => 0, 'edited' => false];
        usort($out, fn ($a, $b) => strcmp($a['at'], $b['at']));   // usort is stable from PHP 8.0: same-second lines keep their order
        return $out;
    }

    /** One task's thread, read now. $allNotes: task-notes.json decoded (the caller loads it; [] until it exists). */
    public static function thread(PDO $pdo, int $taskId, array $allNotes = []): array
    {
        return self::merge($taskId, self::posts($pdo, $taskId), GaugeTasks::history($pdo, $taskId), self::notesFor($allNotes, $taskId));
    }

    /**
     * The session manager's rulings (releases/task-rulings.json, decoded: [{key, what, why, gauge?}]), each made a Work queue
     * task once, by its key (kept as the task's source). A ruling whose same words already stand open on its place isn't added
     * again. Returns how many were added.
     */
    public static function applyRulings(PDO $pdo, array $rulings, string $tz = ''): int
    {
        self::ensure($pdo);
        $n = 0;
        foreach ($rulings as $r) {
            if (!is_array($r)) continue;
            $key = (string) ($r['key'] ?? ''); if (!preg_match('/^[A-Za-z0-9._:-]{1,80}$/', $key)) continue;
            $what = trim((string) ($r['what'] ?? '')); $why = trim((string) ($r['why'] ?? ''));
            if ($what === '' || $why === '') continue;
            $gauge = (string) ($r['gauge'] ?? 'area:rulings');
            $source = 'task-rulings.json · ' . $key;
            $st = $pdo->prepare('SELECT id FROM control_gauge_tasks WHERE source = ? LIMIT 1'); $st->execute([$source]);
            if ($st->fetchColumn()) continue;
            try {
                if (GaugeTasks::openFor($pdo, $gauge, trim((string) preg_replace('/\s+/', ' ', $what)))) continue;
                GaugeTasks::add($pdo, $gauge, $what, $why, $source, 'the session manager (task-rulings.json)', 0, $tz);
                $n++;
            } catch (Throwable $x) { error_log('task rulings: ' . $x->getMessage()); }
        }
        return $n;
    }

    /**
     * The page's one handler for the thread's three presses (after the page's own staff and form-token checks):
     * thread_do = post | edit | undo. Returns the line to show; throws with the words to show.
     */
    public static function handle(PDO $pdo, array $in, string $who, int $uid, string $tz = ''): string
    {
        $do = (string) ($in['thread_do'] ?? '');
        if ($do === 'post') { self::post($pdo, (int) ($in['task'] ?? 0), (string) ($in['body'] ?? ''), $who, $uid, $tz); return 'Posted.'; }
        if ($do === 'edit') return self::edit($pdo, (int) ($in['post'] ?? 0), (string) ($in['body'] ?? ''), $who, $uid, $tz);
        if ($do === 'undo') return self::undo($pdo, (int) ($in['post'] ?? 0), $who, $uid, $tz);
        throw new RuntimeException('That isn\'t something the thread does.');
    }

    /**
     * The thread's section for a task's page. $open: the task is neither done nor withdrawn. $action: the page's own URL.
     * $hidden: the page's own fields (its form token). $viewer / $viewerUid: who is looking, so their own posts offer Edit
     * and Undo. Styling is the page's (Design reviewer: brand.md, design/css-theme-brand.md).
     */
    public static function render(int $taskId, array $entries, bool $open, string $action, array $hidden = [], string $viewer = '', int $viewerUid = 0): string
    {
        $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $keep = '';
        foreach ($hidden as $k => $v) $keep .= '<input type="hidden" name="' . $h($k) . '" value="' . $h($v) . '">';
        $keep .= '<input type="hidden" name="task" value="' . $taskId . '">';
        $o = '<section class="task-thread" id="thread-' . $taskId . '" aria-label="Thread"><h3>Thread</h3>';
        if (!$entries) {
            $o .= '<p class="tt-empty">Nothing here yet. The task\'s History shows here as it happens, and anyone on staff can post.</p>';
        } else {
            $o .= '<ol class="tt-list">';
            foreach ($entries as $e) {
                $kind = in_array($e['kind'] ?? '', ['person', 'system', 'session'], true) ? $e['kind'] : 'system';
                $who = trim((string) ($e['who'] ?? '')) !== '' ? (string) $e['who'] : 'the system';
                $at = (string) ($e['at'] ?? '');
                $o .= '<li class="tt-' . $kind . '"><div class="tt-meta"><span class="tt-who">' . $h($who) . '</span> · <time datetime="' . $h(str_replace(' ', 'T', $at) . 'Z') . '">' . $h($at) . ' UTC</time>'
                    . (!empty($e['edited']) ? ' · edited' : '') . '</div>';
                $o .= '<p class="tt-text">' . nl2br($h($e['text'] ?? '')) . '</p>';
                if ((string) ($e['link'] ?? '') !== '') $o .= '<a class="tt-link" href="' . $h($e['link']) . '" target="_blank" rel="noopener noreferrer">Open ↗</a>';
                if ($open && $kind === 'person' && self::mine(['uid' => (int) ($e['uid'] ?? 0), 'who' => $who], $viewer, $viewerUid)) {
                    $pid = (int) ($e['post_id'] ?? 0);
                    $o .= '<details class="tt-edit"><summary>Edit</summary><form method="post" action="' . $h($action) . '">' . $keep
                        . '<input type="hidden" name="thread_do" value="edit"><input type="hidden" name="post" value="' . $pid . '">'
                        . '<label>Your post<textarea name="body" rows="3" spellcheck="true" maxlength="' . self::MAX . '">' . $h($e['text'] ?? '') . '</textarea></label>'
                        . '<button type="submit">Save</button></form></details>';
                    if ((int) strtotime($at . ' UTC') >= time() - self::UNDO_HOURS * 3600)
                        $o .= '<form class="tt-undo" method="post" action="' . $h($action) . '">' . $keep . '<input type="hidden" name="thread_do" value="undo"><input type="hidden" name="post" value="' . $pid . '"><button type="submit">Undo</button></form>';
                }
                $o .= '</li>';
            }
            $o .= '</ol>';
        }
        if ($open) {
            $o .= '<form class="tt-box" method="post" action="' . $h($action) . '">' . $keep . '<input type="hidden" name="thread_do" value="post">'
                . '<label for="tt-body-' . $taskId . '">Post on this task</label><textarea id="tt-body-' . $taskId . '" name="body" rows="3" spellcheck="true" maxlength="' . self::MAX . '" required></textarea>'
                . '<button type="submit">Post</button></form>';
        } else {
            $o .= '<p class="tt-closed">This task is done or withdrawn: its thread is kept as it stands.</p>';
        }
        return $o . '</section>';
    }

    // ---- inside ----------------------------------------------------------------------------------------------------------

    private static function openTask(PDO $pdo, int $taskId): array
    {
        $r = GaugeTasks::task($pdo, $taskId);
        if (!$r) throw new RuntimeException('That task isn\'t in the queue.');
        if ($r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is done or withdrawn: its thread is kept as it stands. It\'s in the logbook.');
        return $r;
    }

    private static function postRow(PDO $pdo, int $postId): ?array
    {
        $st = $pdo->prepare('SELECT * FROM control_gauge_task_posts WHERE id = ?'); $st->execute([$postId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Whoever wrote it: by the account when there is one, else by the staff name. */
    private static function mine(array $p, string $who, int $uid): bool
    {
        if ($uid > 0) return (int) ($p['uid'] ?? 0) === $uid;
        return $who !== '' && (string) ($p['who'] ?? '') === $who;
    }

    private static function line(string $s): string { return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $s)), 0, 200); }

    private static function utc(string $s): string
    {
        if ($s === '') return '';
        $t = strtotime($s);
        return $t === false ? '' : gmdate('Y-m-d H:i:s', $t);
    }

    private static function tz(string $tz): string { return preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+){0,2}$~', $tz) ? $tz : ''; }   // as GaugeTasks::tz
}

/** array_is_list() arrives in PHP 8.1; this site runs 8.0. */
function array_is_list_compat(array $a): bool
{
    $i = 0;
    foreach ($a as $k => $_) { if ($k !== $i++) return false; }
    return true;
}
=== END FILE ===

=== FILE: files/app/migrations/2026-11-28_task_thread.sql ===
-- app/migrations/2026-11-28_task_thread.sql — Work queue phase 2, release 1: a task's thread (handoffs/work-queue-phase2.md).
-- Additive only: one new table (0180 §4 item 2: additive tables stay Tier 2). Safe to run twice. No UPDATE, no DELETE.
-- TaskThread::ensure() makes the same table if this hasn't run.
CREATE TABLE IF NOT EXISTS control_gauge_task_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  at DATETIME NOT NULL,
  tz VARCHAR(64) NOT NULL DEFAULT '',
  kind VARCHAR(10) NOT NULL DEFAULT 'person',
  who VARCHAR(80) NOT NULL DEFAULT '',
  uid INT NOT NULL DEFAULT 0,
  body TEXT NOT NULL,
  edited_at DATETIME NULL,
  undone_at DATETIME NULL,
  undone_by VARCHAR(80) NOT NULL DEFAULT '',
  KEY task_at (task_id, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
=== END FILE ===

=== FILE: tests/task-thread-tests.php ===
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
=== END FILE ===

=== FILE: faq/faq-entries.md ===
## Can we talk about a task on its own page?

Yes. Every task in the Work queue has a **Thread** at the foot of its page. Anyone on staff can post there; the box has spell check. The task's History shows in the same thread as it happens: when it was added, taken, moved to a stage, given its release, and done. You can edit your own post, and the old words stay in the task's History. Undo takes a post off within a day.

When a task is done or withdrawn, its thread stays as it stands, and nothing more is posted on it.

## Who can see a task's thread?

Only staff, on the System Control Board. Posts carry the staff member's name, and they never leave the board in the reports the build sessions read.

## Does the build session say what it's doing on the task?

Yes. When the session manager passes on a build session's note ("built, in review", "needs your answer"), it shows in the thread, with a link where there is one. It usually arrives within the hour.

## Can I ask Claude about a task?

Coming. **Ask Claude** will draft a task's scope, split it into steps and write its build brief. It only runs when you press it, and the thread will show what each press cost. It waits for the team's own monthly AI budget to be set up.

## Can I send a task to be built from its page?

Coming. **Start the build** will send the task's brief to the session manager. It will never open a session by itself, and the button will show where the task is.

## Can I post to the CEO from the Work queue?

Coming. **+ Add** will offer **To: the CEO**. The Executive Suite will answer in that item's thread.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "work-queue-phase2-r1-thread",
  "gate": "Tier 3 (decisions/0180 §1 'non-protected workflow redesigns'; Jason 2026-11-27 'this doesn't seem like a t5 concern'), Project Manager (8) lane through jason-spec §8's four stages. Before live: the Project Manager decides whether 0180 §4 territory 5 (privacy: staff messages) applies; if it does, Tier 4 records its review and Prior Ruling Check (0180 §4: implements handoffs/work-queue-phase2.md release 1). Jason sees it as a line in the daily batch (0179). Sequencing: after task-seed-4, the pipeline release and R2; launch work wins against 0181's gates.",
  "what": "Work queue phase 2, release 1: a task's thread. Staff posts in a new additive table (control_gauge_task_posts), with Edit (old words in History) and Undo within a day. The system's lines are read from the task's existing History (GaugeTasks::history), with no second record. The build session's notes come from releases/task-notes.json. The session manager's rulings (releases/task-rulings.json) become Work queue tasks, once each. No AI, no notifications, no money. The attached DRAFT (job 19) was empty, so this is drafted fresh from the handoff and the live GaugeTasks.php.",
  "files": [
    "files/app/lib/TaskThread.php",
    "files/app/migrations/2026-11-28_task_thread.sql",
    "tests/task-thread-tests.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "Builds release 1 of handoffs/work-queue-phase2.md ('The thread: posts, system lines from History, the box. No AI.') and its 2026-11-27 note (also reads releases/task-notes.json and releases/task-rulings.json).",
    "Tier 3 per the handoff's note and decided-not-live.md; territory 5 is flagged for the Project Manager, not decided here.",
    "No live file is changed. GaugeTasks.php is used through its public methods (task, history, add, openFor, ensure, withdraw), so no region of it is replaced.",
    "Posts never go into GaugeTasks::report() / health.php, so no staff name leaves the board (0145 §1).",
    "A done or withdrawn task's thread is read-only, matching GaugeTasks::open()'s rule for steps and decisions.",
    "Each post's press is a logbook line ('Task #N post added/edited/undone: ...'). The thread leaves those lines out of its system lines, because the post itself is shown.",
    "Proposed file formats, for the session manager to confirm: task-notes.json = [{task, line, link, at}]; task-rulings.json = [{key, what, why, gauge?}], each ruling becoming a Work task with source 'task-rulings.json · <key>' and gauge 'area:rulings' by default.",
    "SKIPPED: the attached DRAFT (job 19) is empty, so there was nothing to review.",
    "SKIPPED (paused or waiting for Jason, untouched): M2b (paused by Jason 2026-11-24) and Order #12's books ('I will address this after holiday'). Nothing in phase 2 itself is paused or waiting for Jason.",
    "SKIPPED (dependency): release 2, Ask Claude / task_assist. It runs on the agents' own $40 counter in AiClient, and 0180's 'Separate each' correction says nothing is spent from it until that counter is live (the AI items release). AiClient.php is not attached. Gaps 3 and 4 (daily share, model) remain suggestions.",
    "SKIPPED (sequencing): release 3, Start the build and Gemini's public-page read. Gemini's gap 1 is answered by 0180's paid Google AI note; held 0066 names still go nowhere.",
    "SKIPPED (sequencing): the Executive channel ('+ Add' → To: the CEO). It is its own release after this one.",
    "SKIPPED (files not shown): role queues (decided-not-live lists them with release 1; they need TeamRoster.php and the Work queue page) and notifications through Notify::send (Notify.php)."
  ],
  "tests": [
    "tests/task-thread-tests.php → tools/work-queue-tests/task-thread-tests.php: the pure checks run anywhere. The database checks run only with TASK_THREAD_TEST_DSN pointing at a database whose name contains 'test'. No money moves, and nothing touches QuickBooksPay."
  ],
  "risks": [
    "Nothing here has been run, tested, linted or zip-checked. It is a DRAFT for the lane session.",
    "GaugeTasks::history reads control_log.at. If ControlLog stores local time rather than UTC, system lines will sort out of step with posts (stored as UTC_TIMESTAMP). ControlLog.php is not shown.",
    "Territory 5 (privacy: staff messages) may apply. If the Project Manager flags it, Tier 4's review must be recorded before live.",
    "TaskThread::render's markup is unstyled and has had no 1280px or 390px check. It needs the Design reviewer's fidelity check (brand.md, design/css-theme-brand.md) and the simplicity veto (Guardian 15, Editorial Director 12).",
    "applyRulings creates tasks automatically from a file the session manager writes. A wrong file would add wrong tasks; each can be withdrawn with a reason.",
    "Live GaugeTasks::handOff writes columns lane and handoff that GaugeTasks::ensure() doesn't create (presumably R2's migration). This release doesn't depend on them, but it was noticed in review."
  ],
  "verify": [
    "file not shown: the task page / side panel (#282) and its POST handler. Wire TaskThread::render() at the foot of the task page and TaskThread::handle($pdo, $_POST, $staffName, $staffUid, $tz) behind the page's own staff check and form token, passing the token in $hidden.",
    "file not shown: ControlLog.php. Confirm add()'s signature as used in GaugeTasks, and that control_log.at is UTC.",
    "file not shown: Database.php. Confirm that requiring it has no side effect in the test script.",
    "file not shown: health.php / the hourly path (gauge-requests.mjs, gauges.json) and where releases/task-notes.json and releases/task-rulings.json land on the server. Wire TaskThread::loadJson(<path>) into thread() and applyRulings().",
    "file not shown: Notify.php (thread notifications deferred), AiClient.php (release 2), TeamRoster.php (role queues).",
    "Run app/migrations/2026-11-28_task_thread.sql twice on staging. The second run must change nothing. Rename the file if the migrations folder's dating differs.",
    "Run tools/work-queue-tests/task-thread-tests.php on staging, with a test DSN: every line PASS.",
    "Before-and-after check on the task page at 1280 and 390, including the Staff Views Advocate (A12).",
    "Confirm GaugeTasks::report() output is unchanged (no posts and no staff names in build-requests.json)."
  ],
  "regions": false
}
=== END FILE ===