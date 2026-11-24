<?php
// app/lib/GaugeTasks.php — what a person does from a gauge's panel (the gauge panel, approved by Jason 2026-11-23;
// handoffs/gauge-panel.md; CLAUDE.md "Every action is a task"). One table, each site's own (staging class: own):
//   - fix: Request a fix on one item (an issue, a thing not built, a check that doesn't reach it). With no direct action, the
//     item is sent to a build: the request is kept here, named for its gauge ("the Discovery build"), shown on the row as
//     "Requested: the … build has it · who · when" with Undo, reported by health.php so the daily fetch writes build-requests.json and the
//     session manager queues it under the owning session (its ruling on Q1, 2026-11-23). It clears by itself once the
//     item's check passes: the item no longer stands where it was sent from (Jason: "once fixed it should clear the issue").
//   - scope / target: Jason's own decisions on a gauge (gauge-lifecycle §3, §8 decided 2's note): Approve the scope moves
//     a development gauge out of Planning; Approve the target sets a runtime gauge's target to the one proposed. Each read by
//     ControlGauges over gauges.json at once, and reported by health.php so gauges.py records it (the ruling on Q3).
//   - The Work queue (Jason, 2026-11-24: "Make a gauge for this and have its queue from the development board. We need to be
//     able to add tasks to the queue and manage them so we don't fall out of score or forget what we identified as needing
//     to be done"): Add a task writes the same fix record, on a gauge or an area (area:<slug>), with its why and source,
//     bucket 'task' (an item no gauge row moves). Each is taken by a person or moved to a session (owner), named for its
//     release (rel), edited with History (every change in the logbook), withdrawn with a reason, and done when its check
//     passes or its release is live (site_releases), never by a press. Late: a day old and nobody has it (kit-buttons' rule).
// Every press, and every Undo, is in the board's logbook too (ControlLog). Who is a staff member's name, never a member's.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';

final class GaugeTasks
{
    public const KINDS = ['fix', 'scope', 'target'];
    /** An Undo is offered for a day; after that the row's line stands (Undo stays in the logbook's history only). */
    public const UNDO_HOURS = 24;
    private static bool $ready = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_tasks (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL,
            tz VARCHAR(64) NOT NULL DEFAULT '', gauge VARCHAR(80) NOT NULL, item VARCHAR(255) NOT NULL DEFAULT '', kind VARCHAR(10) NOT NULL,
            what VARCHAR(400) NOT NULL DEFAULT '', bucket VARCHAR(10) NOT NULL DEFAULT '', value VARCHAR(255) NOT NULL DEFAULT '',
            who VARCHAR(80) NOT NULL DEFAULT '', uid INT NOT NULL DEFAULT 0, undone_at DATETIME NULL, cleared_at DATETIME NULL,
            why VARCHAR(400) NOT NULL DEFAULT '', source VARCHAR(255) NOT NULL DEFAULT '', owner VARCHAR(80) NOT NULL DEFAULT '', owner_at DATETIME NULL,
            rel VARCHAR(120) NOT NULL DEFAULT '', done_how VARCHAR(255) NOT NULL DEFAULT '',
            goal TEXT NULL, in_scope TEXT NULL, out_scope TEXT NULL, done_when TEXT NULL, priority TINYINT NULL,
            KEY gauge_item (gauge, item), KEY kind_at (kind, at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");   // the Work queue's columns: 2026-11-24_work_queue.sql and 2026-11-24_task_scope.sql add them to a table made before
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_task_steps (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL,
            pos INT NOT NULL DEFAULT 0, see VARCHAR(600) NOT NULL DEFAULT '', do_what VARCHAR(600) NOT NULL DEFAULT '', confirm VARCHAR(600) NOT NULL DEFAULT '',
            at DATETIME NOT NULL, who VARCHAR(80) NOT NULL DEFAULT '', done_at DATETIME NULL, done_by VARCHAR(80) NOT NULL DEFAULT '', tz VARCHAR(64) NOT NULL DEFAULT '',
            KEY task_pos (task_id, pos)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    private static function gaugeOk(string $g): bool { return (bool) preg_match('/^(dev|rt|area):[A-Za-z0-9_-]{1,60}$/', $g); }

    // ---- the Work queue ----------------------------------------------------------------------------------------------------
    /** A task a day old that nobody has (no owner, no session's pickup) is late (kit-buttons: "Not picked up yet after a day"). */
    public const LATE_HOURS = 24;

    /** Add a task to the queue: what, why and where it came from, on a gauge or an area. Returns its id (the same what on the same place: the one task stands). */
    public static function add(PDO $pdo, string $gauge, string $what, string $why, string $source, string $who, int $uid, string $tz = ''): int
    {
        self::ensure($pdo);
        if (!self::gaugeOk($gauge)) throw new RuntimeException('Which gauge or area is it for?');
        $what = trim(preg_replace('/\s+/', ' ', $what)); if ($what === '') throw new RuntimeException('Say what needs to be done.');
        if (trim($why) === '') throw new RuntimeException('Say why: what it fixes or who asked.');
        if ($who === '') throw new RuntimeException('Only a staff member can add a task.');
        $open = self::openFor($pdo, $gauge, $what); if ($open) return (int) $open['id'];
        $pdo->prepare("INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source) VALUES (UTC_TIMESTAMP(), ?, ?, ?, 'fix', ?, 'task', '', ?, ?, ?, ?)")
            ->execute([self::tz($tz), $gauge, mb_substr($what, 0, 255), mb_substr($what, 0, 400), mb_substr($who, 0, 80), $uid, mb_substr(trim($why), 0, 400), mb_substr(trim($source), 0, 255)]);
        $id = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' added to the Work queue: ' . $what, $who, '', $tz);
        return $id;
    }

    /** One task row, or null. */
    public static function task(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare("SELECT * FROM control_gauge_tasks WHERE id = ? AND kind = 'fix'"); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Edit what a person entered (CLAUDE.md "Edit or Undo"): what, why, source, gauge; each old value kept in the logbook (its History). */
    public static function edit(PDO $pdo, int $id, array $in, string $who, string $tz = ''): string
    {
        $r = self::task($pdo, $id);
        if (!$r || $r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is done or withdrawn: it can\'t be edited. It\'s in the logbook.');
        $set = []; $vals = []; $log = [];
        foreach (['what' => [400, 'what'], 'why' => [400, 'why'], 'source' => [255, 'source'], 'gauge' => [80, 'place'],
            'goal' => [2000, 'goal'], 'in_scope' => [4000, 'in scope'], 'out_scope' => [4000, 'out of scope'], 'done_when' => [2000, 'done when'], 'priority' => [1, 'priority']] as $k => [$max, $word]) {
            if (!array_key_exists($k, $in)) continue;
            $multi = in_array($k, self::SCOPE, true);   // the scope keeps its lines; the one-line fields fold their spaces
            $v = $multi ? trim(str_replace("\r", '', (string) $in[$k])) : trim(preg_replace('/\s+/', ' ', (string) $in[$k]));
            if ($k === 'priority') { if ($v !== '' && !in_array($v, ['1', '2', '3'], true)) throw new RuntimeException('Priority is 1, 2 or 3.');
                if ($v === (string) ($r['priority'] ?? '')) continue;
                $set[] = 'priority = ?'; $vals[] = $v === '' ? null : (int) $v; $log[] = 'priority ' . ((string) ($r['priority'] ?? '') ?: 'not set') . ' → ' . ($v ?: 'not set'); continue; }
            if ($k === 'gauge' && !self::gaugeOk($v)) throw new RuntimeException('Which gauge or area is it for?');
            if (in_array($k, ['what', 'why'], true) && $v === '') throw new RuntimeException('Its ' . $word . ' can\'t be empty.');
            if ($v === (string) ($r[$k] ?? '')) continue;
            $set[] = $k . ' = ?'; $vals[] = mb_substr($v, 0, $max);
            if ($k === 'what') { $set[] = 'item = ?'; $vals[] = mb_substr($v, 0, 255); }
            $log[] = $word . ' "' . mb_substr((string) ($r[$k] ?? ''), 0, 120) . '" → "' . mb_substr($v, 0, 120) . '"';
        }
        if (!$set) return 'Nothing changed.';
        $vals[] = $id; $pdo->prepare('UPDATE control_gauge_tasks SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' edited: ' . implode('; ', $log), $who, '', $tz);
        return 'Saved. The old words are in its History.';
    }

    /** Take a task (owner: the person) or move it to a session (owner: the session's name); logged. */
    public static function assign(PDO $pdo, int $id, string $owner, string $who, string $tz = ''): string
    {
        $r = self::task($pdo, $id);
        if (!$r || $r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is done or withdrawn.');
        $owner = trim($owner); if ($owner === '') throw new RuntimeException('Who has it?');
        if ($owner === (string) $r['owner']) return 'It\'s already with ' . $owner . '.';
        $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([mb_substr($owner, 0, 80), $id]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ($r['owner'] !== '' ? ' moved from ' . $r['owner'] . ' to ' : ' taken by ') . $owner . ': ' . $r['what'], $who, '', $tz);
        return $owner === $who ? 'Taken: it\'s yours.' : 'Moved to ' . $owner . '.';
    }

    /** Name the release that will put it live (the queue reads "Being fixed in <release>"; it's done once that release is live). */
    public static function release(PDO $pdo, int $id, string $rel, string $who, string $tz = ''): string
    {
        $r = self::task($pdo, $id);
        if (!$r || $r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is done or withdrawn.');
        $rel = trim($rel); if (!preg_match('/^[A-Za-z0-9._-]{1,120}$/', $rel)) throw new RuntimeException('Name its release as its zip is named (e.g. work-queue.zip).');
        $pdo->prepare('UPDATE control_gauge_tasks SET rel = ? WHERE id = ?')->execute([$rel, $id]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' being fixed in ' . $rel . ': ' . $r['what'], $who, '', $tz);
        return 'Being fixed in ' . $rel . '.';
    }

    /** Withdraw a task, with its reason (a decision changed, a duplicate); kept for the record and in the logbook. */
    public static function withdraw(PDO $pdo, int $id, string $reason, string $who, string $tz = ''): string
    {
        $r = self::task($pdo, $id);
        if (!$r || $r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is already done or withdrawn.');
        $reason = trim($reason); if ($reason === '') throw new RuntimeException('Say why it\'s withdrawn: it\'s kept with the task.');
        $pdo->prepare('UPDATE control_gauge_tasks SET undone_at = UTC_TIMESTAMP(), done_how = ? WHERE id = ? AND undone_at IS NULL')->execute([mb_substr('Withdrawn: ' . $reason, 0, 255), $id]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' withdrawn: ' . $reason . ' (' . $r['what'] . ')', $who, '', $tz);
        return 'Withdrawn. It\'s kept in the logbook with your reason.';
    }

    /**
     * The queue: every open task and fix request, and those done or withdrawn in the last 7 days, each with its state
     * (open | taken | fixing | late | done | withdrawn) and the line that says it. $pickups: gauges.json's pickups (id =>
     * [session, release]), the sessions' own record; a task done when its release is live is closed here, at once, and logged.
     */
    public static function queue(PDO $pdo, array $pickups = []): array
    {
        self::ensure($pdo);
        $live = [];
        try { foreach ($pdo->query("SELECT zip_name, live_at FROM site_releases WHERE state = 'live' AND zip_name <> ''")->fetchAll(PDO::FETCH_ASSOC) as $x) $live[(string) $x['zip_name']] = (string) $x['live_at']; } catch (Throwable $x) {}
        $out = [];
        foreach ($pdo->query("SELECT * FROM control_gauge_tasks WHERE kind = 'fix' AND ((undone_at IS NULL AND cleared_at IS NULL) OR COALESCE(cleared_at, undone_at) > UTC_TIMESTAMP() - INTERVAL 7 DAY) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pk = $pickups[(int) $r['id']] ?? null;
            $owner = (string) $r['owner'] !== '' ? (string) $r['owner'] : (string) ($pk['session'] ?? '');
            $rel = (string) $r['rel'] !== '' ? (string) $r['rel'] : (string) ($pk['release'] ?? '');
            if ($r['cleared_at'] === null && $r['undone_at'] === null && $rel !== '' && isset($live[$rel])) {   // its release is live: done, by itself
                $pdo->prepare('UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP(), done_how = ? WHERE id = ? AND cleared_at IS NULL')->execute([mb_substr('Done: ' . $rel . ' is live', 0, 255), (int) $r['id']]);
                $r['cleared_at'] = gmdate('Y-m-d H:i:s'); $r['done_how'] = 'Done: ' . $rel . ' is live';
                ControlLog::add($pdo, 'clear', '', 'Task #' . $r['id'] . ' done: ' . $rel . ' is live (' . $r['what'] . ')', 'the system');
            }
            $age = time() - (int) strtotime($r['at'] . ' UTC');
            $state = $r['undone_at'] !== null ? 'withdrawn' : ($r['cleared_at'] !== null ? 'done' : ($rel !== '' ? 'fixing' : ($owner !== '' ? 'taken' : ($age > self::LATE_HOURS * 3600 ? 'late' : 'open'))));
            $out[] = $r + ['state' => $state, 'owner_now' => $owner, 'rel_now' => $rel, 'task' => $r['bucket'] === 'task'];
        }
        return $out;
    }

    /** The scope's fields (Jason, 2026-11-24: "Each task must open and allow me to scope and set priorities and work that needs to be done"). */
    public const SCOPE = ['goal', 'in_scope', 'out_scope', 'done_when'];
    /** Priority, each with its meaning (CLAUDE.md "Words and lists": a choice that needs explaining carries it). */
    public const PRIORITY = [1 => ['Priority 1', 'first: the next one taken'], 2 => ['Priority 2', 'next, once the 1s are taken'], 3 => ['Priority 3', 'when there\'s room']];

    /** A task's steps, in order: each a see, do, confirm (CLAUDE.md "Every action is a task"). */
    public static function steps(PDO $pdo, int $id): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_task_steps WHERE task_id = ? ORDER BY pos, id'); $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function open(PDO $pdo, int $id): array
    {
        $r = self::task($pdo, $id);
        if (!$r || $r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is done or withdrawn: its steps can\'t change. It\'s in the logbook.');
        return $r;
    }

    /** Add a step: what to see, what to do, how it's confirmed. */
    public static function stepAdd(PDO $pdo, int $id, string $see, string $do, string $confirm, string $who, string $tz = ''): int
    {
        self::open($pdo, $id);
        $do = trim(preg_replace('/\s+/', ' ', $do)); if ($do === '') throw new RuntimeException('Say what to do in this step.');
        $pos = (int) $pdo->query('SELECT COALESCE(MAX(pos), 0) + 1 FROM control_gauge_task_steps WHERE task_id = ' . (int) $id)->fetchColumn();
        $pdo->prepare('INSERT INTO control_gauge_task_steps (task_id, pos, see, do_what, confirm, at, who, tz) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?)')
            ->execute([$id, $pos, mb_substr(trim($see), 0, 600), mb_substr($do, 0, 600), mb_substr(trim($confirm), 0, 600), mb_substr($who, 0, 80), self::tz($tz)]);
        $sid = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' step ' . $pos . ' added: ' . $do, $who, '', $tz);
        return $sid;
    }

    /** Edit a step (its words, or whether it's confirmed: a record corrected, never undone); the old words kept in the task's History. */
    public static function stepEdit(PDO $pdo, int $sid, array $in, string $who, string $tz = ''): string
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_task_steps WHERE id = ?'); $st->execute([$sid]); $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) throw new RuntimeException('That step isn\'t there any more.');
        self::open($pdo, (int) $s['task_id']);
        $set = []; $vals = []; $log = [];
        foreach (['see' => 'see', 'do_what' => 'do', 'confirm' => 'confirm'] as $k => $word) {
            if (!array_key_exists($k, $in)) continue;
            $v = trim(preg_replace('/\s+/', ' ', (string) $in[$k]));
            if ($k === 'do_what' && $v === '') throw new RuntimeException('Say what to do in this step.');
            if ($v === (string) $s[$k]) continue;
            $set[] = $k . ' = ?'; $vals[] = mb_substr($v, 0, 600); $log[] = $word . ' "' . mb_substr((string) $s[$k], 0, 100) . '" → "' . mb_substr($v, 0, 100) . '"';
        }
        if (array_key_exists('done', $in)) {
            $done = (bool) $in['done'];
            if ($done && $s['done_at'] === null) { $set[] = 'done_at = UTC_TIMESTAMP()'; $set[] = 'done_by = ?'; $vals[] = mb_substr($who, 0, 80); $set[] = 'tz = ?'; $vals[] = self::tz($tz); $log[] = 'confirmed done'; }
            if (!$done && $s['done_at'] !== null) { $set[] = 'done_at = NULL'; $set[] = "done_by = ''"; $log[] = 'corrected: not done after all (it was confirmed by ' . $s['done_by'] . ')'; }
        }
        if (!$set) return 'Nothing changed.';
        $vals[] = $sid; $pdo->prepare('UPDATE control_gauge_task_steps SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
        ControlLog::add($pdo, 'control', '', 'Task #' . $s['task_id'] . ' step ' . $s['pos'] . ' edited: ' . implode('; ', $log), $who, '', $tz);
        return in_array('confirmed done', $log, true) && count($log) === 1 ? 'Confirmed: step ' . $s['pos'] . ' is done.' : 'Saved. The old words are in its History.';
    }

    /** A task's History: every line the logbook keeps for it (added, edited with the old words, taken, moved, its release, done, withdrawn), oldest first. */
    public static function history(PDO $pdo, int $id): array
    {
        try { ControlLog::ensure($pdo);
            $st = $pdo->prepare("SELECT at, tz, what, who FROM control_log WHERE what LIKE ? ORDER BY id"); $st->execute(['Task #' . $id . ' %']);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $x) { return []; }
    }

    /** The queue's counts for its gauge: open, taken, fixing, late, done (7 days), withdrawn (7 days). */
    public static function counts(array $queue): array
    {
        $c = ['open' => 0, 'taken' => 0, 'fixing' => 0, 'late' => 0, 'done' => 0, 'withdrawn' => 0];
        foreach ($queue as $t) $c[$t['state']]++;
        return $c;
    }

    /**
     * Request a fix on one item: the request kept, and logged. $bucket: where the item stood (fail|build|check), so it clears when
     * it no longer stands there. $build: the gauge's name for the line ("Discovery"). Returns the new row's id.
     */
    public static function fix(PDO $pdo, string $gauge, string $item, string $bucket, string $what, string $build, string $who, int $uid, string $tz = ''): int
    {
        self::ensure($pdo);
        if (!self::gaugeOk($gauge)) throw new RuntimeException('Which gauge?');
        $item = trim($item); if ($item === '') throw new RuntimeException('Which item?');
        if (!in_array($bucket, ['fail', 'build', 'check'], true)) throw new RuntimeException('That item has nothing to fix.');
        if ($who === '') throw new RuntimeException('Only a staff member can send a fix.');
        $open = self::openFor($pdo, $gauge, $item);
        if ($open) return (int) $open['id'];   // pressed twice: the one request stands
        $pdo->prepare('INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid) VALUES (UTC_TIMESTAMP(), ?, ?, ?, \'fix\', ?, ?, ?, ?, ?)')
            ->execute([self::tz($tz), $gauge, mb_substr($item, 0, 255), mb_substr(trim($what), 0, 400), $bucket, mb_substr($build, 0, 255), mb_substr($who, 0, 80), $uid]);
        $id = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', 'Fix requested: ' . $item . ': ' . trim($what) . ', for the ' . $build . ' build', $who, '', $tz);
        return $id;
    }

    /** Approve the scope (dev) or the target (rt, with its value): Jason's decision, kept and logged. Returns the row's id. */
    public static function approve(PDO $pdo, string $gauge, string $kind, string $value, string $title, string $who, int $uid, string $tz = ''): int
    {
        self::ensure($pdo);
        if (!self::gaugeOk($gauge) || !in_array($kind, ['scope', 'target'], true)) throw new RuntimeException('Which gauge?');
        if ($kind === 'scope' && !str_starts_with($gauge, 'dev:')) throw new RuntimeException('A scope is approved on a development gauge.');
        if ($kind === 'target' && (!str_starts_with($gauge, 'rt:') || trim($value) === '')) throw new RuntimeException('Which target?');
        if ($who === '') throw new RuntimeException('Only a staff member can approve.');
        $have = self::approvals($pdo)[$gauge][$kind] ?? null;
        if ($have) return (int) $have['id'];
        $pdo->prepare('INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, value, who, uid) VALUES (UTC_TIMESTAMP(), ?, ?, \'\', ?, ?, ?, ?, ?)')
            ->execute([self::tz($tz), $gauge, $kind, mb_substr($title, 0, 400), mb_substr(trim($value), 0, 255), mb_substr($who, 0, 80), $uid]);
        $id = (int) $pdo->lastInsertId();
        ControlLog::add($pdo, 'control', '', ($kind === 'scope' ? 'Approved the scope of ' : 'Approved the target of ') . $title . ($kind === 'target' ? ': ' . trim($value) : ''), $who, '', $tz);
        return $id;
    }

    /** Undo (an open request at any age, an approval within a day): the row is marked undone (kept for the record), and logged. */
    public static function undo(PDO $pdo, int $id, string $who, string $tz = ''): string
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_tasks WHERE id = ?'); $st->execute([$id]); $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['undone_at'] !== null) throw new RuntimeException('It was already undone, or it isn\'t here any more.');
        if ($r['cleared_at'] !== null) throw new RuntimeException('It has already cleared: its check passed, so there\'s nothing to undo.');
        // an open request can be withdrawn at any age (nothing was done yet; past a day it's waiting, never stuck); an approval for a day
        if ($r['kind'] !== 'fix' && strtotime($r['at'] . ' UTC') < time() - self::UNDO_HOURS * 3600) throw new RuntimeException('Undo is offered for a day; this one is older. It\'s in the logbook.');
        $pdo->prepare('UPDATE control_gauge_tasks SET undone_at = UTC_TIMESTAMP() WHERE id = ? AND undone_at IS NULL')->execute([$id]);
        $what = $r['kind'] === 'fix' ? 'the fix for ' . $r['item'] : ($r['kind'] === 'scope' ? 'the scope approval of ' . $r['what'] : 'the target approval of ' . $r['what']);
        ControlLog::add($pdo, 'control', '', 'Undid ' . $what, $who, '', $tz);
        return $r['kind'] === 'fix' ? 'Undone: the fix request is withdrawn.' : 'Undone: the approval is taken back.';
    }

    /** The open fix request for one item, or null. */
    public static function openFor(PDO $pdo, string $gauge, string $item): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare("SELECT * FROM control_gauge_tasks WHERE gauge = ? AND item = ? AND kind = 'fix' AND undone_at IS NULL AND cleared_at IS NULL ORDER BY id DESC LIMIT 1");
        $st->execute([$gauge, mb_substr($item, 0, 255)]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * One gauge's fix rows, by item: open ones, and those cleared in the last day (the row says "Cleared: its check passed").
     * $now: item name => where it stands now (fail|build|check|ok). An open request whose item no longer stands where it was
     * sent from is cleared here, at once (Jason: "once fixed it should clear the issue"), and logged.
     */
    public static function forGauge(PDO $pdo, string $gauge, array $now = []): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare("SELECT * FROM control_gauge_tasks WHERE gauge = ? AND kind = 'fix' AND bucket <> 'task' AND undone_at IS NULL AND (cleared_at IS NULL OR cleared_at > UTC_TIMESTAMP() - INTERVAL 1 DAY) ORDER BY id");
        $st->execute([$gauge]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['cleared_at'] === null && isset($now[$r['item']]) && $now[$r['item']] !== $r['bucket']) {
                $pdo->prepare('UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP() WHERE id = ? AND cleared_at IS NULL')->execute([(int) $r['id']]);
                $r['cleared_at'] = gmdate('Y-m-d H:i:s');
                ControlLog::add($pdo, 'clear', '', 'Cleared: ' . $r['item'] . ' (its check passed)', 'the system');
            }
            $out[$r['item']] = $r;
        }
        return $out;
    }

    /** Approvals in force: gauge => kind => [id, at, who, value, tz]. Never throws (a board that can't read them shows the file's). */
    public static function approvals(PDO $pdo): array
    {
        try {
            self::ensure($pdo);
            $out = [];
            foreach ($pdo->query("SELECT id, at, tz, gauge, kind, value, who FROM control_gauge_tasks WHERE kind IN ('scope','target') AND undone_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $out[$r['gauge']][$r['kind']] = ['id' => (int) $r['id'], 'at' => (string) $r['at'], 'tz' => (string) $r['tz'], 'who' => (string) $r['who'], 'value' => (string) $r['value']];
            return $out;
        } catch (Throwable $x) { error_log('gauge tasks: ' . $x->getMessage()); return []; }
    }

    /** For health.php: open fix requests (the build queue) and approvals in force. Items, words and times: no person's name (0145 §1). */
    public static function report(PDO $pdo): array
    {
        try {
            self::ensure($pdo);
            $req = [];
            // the Work queue's tasks travel the same way (why, source, owner, release: words and session names, no member's)
            foreach ($pdo->query("SELECT id, at, gauge, item, what, bucket, value, why, source, owner, rel FROM control_gauge_tasks WHERE kind = 'fix' AND undone_at IS NULL AND cleared_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $req[] = ['id' => (int) $r['id'], 'at_utc' => str_replace(' ', 'T', (string) $r['at']) . 'Z', 'gauge' => $r['gauge'], 'build' => $r['value'], 'item' => $r['item'], 'what' => $r['what'], 'was' => $r['bucket'],
                    'why' => (string) $r['why'], 'source' => (string) $r['source'],
                    // a session's name as it is; a person's never (0145 §1): "a staff member"
                    'owner' => $r['owner'] === '' ? '' : (stripos((string) $r['owner'], 'session') !== false ? (string) $r['owner'] : 'a staff member'), 'release' => (string) $r['rel']];
            $ap = [];
            foreach (self::approvals($pdo) as $g => $ks) foreach ($ks as $k => $a) $ap[] = ['gauge' => $g, 'kind' => $k, 'value' => $a['value'], 'at_utc' => str_replace(' ', 'T', $a['at']) . 'Z'];
            return ['requests' => $req, 'approvals' => $ap];
        } catch (Throwable $x) { return ['requests' => [], 'approvals' => [], 'error' => $x->getMessage()]; }
    }

    private static function tz(string $tz): string { return preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+){0,2}$~', $tz) ? $tz : ''; }
}
