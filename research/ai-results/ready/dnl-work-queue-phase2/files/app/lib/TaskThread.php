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
