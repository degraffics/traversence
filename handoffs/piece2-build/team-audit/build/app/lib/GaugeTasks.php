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
            ticket VARCHAR(12) NOT NULL DEFAULT 'work', stage VARCHAR(16) NULL, stage_at DATETIME NULL, tier TINYINT NULL, audit_key VARCHAR(120) NOT NULL DEFAULT '', parent_id INT UNSIGNED NULL,
            KEY gauge_item (gauge, item), KEY kind_at (kind, at), KEY ticket_stage (ticket, stage)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");   // Piece 2's columns (ticket … parent_id): 2026-11-25_tickets.sql adds them to a table made before   // the Work queue's columns: 2026-11-24_work_queue.sql and 2026-11-24_task_scope.sql add them to a table made before
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_task_steps (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL,
            pos INT NOT NULL DEFAULT 0, see VARCHAR(600) NOT NULL DEFAULT '', do_what VARCHAR(600) NOT NULL DEFAULT '', confirm VARCHAR(600) NOT NULL DEFAULT '',
            at DATETIME NOT NULL, who VARCHAR(80) NOT NULL DEFAULT '', done_at DATETIME NULL, done_by VARCHAR(80) NOT NULL DEFAULT '', tz VARCHAR(64) NOT NULL DEFAULT '',
            KEY task_pos (task_id, pos)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_task_adrs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL,
            adr CHAR(4) NOT NULL, at DATETIME NOT NULL, who VARCHAR(80) NOT NULL DEFAULT '', removed_at DATETIME NULL, removed_by VARCHAR(80) NOT NULL DEFAULT '',
            KEY task_adr (task_id, adr)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_task_moves (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL, at DATETIME NOT NULL,
            tz VARCHAR(64) NOT NULL DEFAULT '', from_stage VARCHAR(16) NOT NULL DEFAULT '', to_stage VARCHAR(16) NOT NULL DEFAULT '', who VARCHAR(80) NOT NULL DEFAULT '', why VARCHAR(400) NOT NULL DEFAULT '',
            run_id INT UNSIGNED NULL, KEY task_at (task_id, at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_gauge_task_briefs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, task_id INT UNSIGNED NOT NULL,
            decision TEXT NULL, why_you TEXT NULL, boundary VARCHAR(20) NOT NULL DEFAULT '', a_text TEXT NULL, a_pros TEXT NULL, a_cons TEXT NULL, b_text TEXT NULL, b_pros TEXT NULL, b_cons TEXT NULL,
            recommend CHAR(1) NOT NULL DEFAULT '', delay_impact TEXT NULL, cost VARCHAR(255) NOT NULL DEFAULT '', answer CHAR(1) NOT NULL DEFAULT '', answer_words TEXT NULL,
            answered_by VARCHAR(80) NOT NULL DEFAULT '', answered_at DATETIME NULL, answer_tz VARCHAR(64) NOT NULL DEFAULT '', precedent VARCHAR(255) NOT NULL DEFAULT '',
            UNIQUE KEY task (task_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    private static function gaugeOk(string $g): bool { return (bool) preg_match('/^(dev|rt|area):[A-Za-z0-9_-]{1,60}$/', $g); }

    // ---- the Work queue ----------------------------------------------------------------------------------------------------
    /** A task a day old that nobody has (no owner, no session's pickup) is late (kit-buttons: "Not picked up yet after a day"). */
    public const LATE_HOURS = 24;

    /** Add a task to the queue: what, why and where it came from, on a gauge or an area. Returns its id (the same what on the same place: the one task stands). */
    public static function add(PDO $pdo, string $gauge, string $what, string $why, string $source, string $who, int $uid, string $tz = '', string $ticket = 'work', ?int $parent = null): int
    {
        if (!isset(self::TICKETS[$ticket])) throw new RuntimeException('Choose Work, Audit or Resolution.');
        self::ensure($pdo);
        if (!self::gaugeOk($gauge)) throw new RuntimeException('Which gauge or area is it for?');
        $what = trim(preg_replace('/\s+/', ' ', $what)); if ($what === '') throw new RuntimeException('Say what needs to be done.');
        if (trim($why) === '') throw new RuntimeException('Say why: what it fixes or who asked.');
        if ($who === '') throw new RuntimeException('Only a staff member can add a task.');
        $open = self::openFor($pdo, $gauge, $what); if ($open) return (int) $open['id'];
        $pdo->prepare("INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source) VALUES (UTC_TIMESTAMP(), ?, ?, ?, 'fix', ?, 'task', '', ?, ?, ?, ?)")
            ->execute([self::tz($tz), $gauge, mb_substr($what, 0, 255), mb_substr($what, 0, 400), mb_substr($who, 0, 80), $uid, mb_substr(trim($why), 0, 400), mb_substr(trim($source), 0, 255)]);
        $id = (int) $pdo->lastInsertId();
        // its type and its first stage (piece2-team.md §3.1): Work at Planning, Audit at Found, Resolution at Brief
        $pdo->prepare('UPDATE control_gauge_tasks SET ticket = ?, stage = ?, stage_at = UTC_TIMESTAMP(), parent_id = ? WHERE id = ?')->execute([$ticket, array_key_first(self::stagesOf($ticket)), $parent, $id]);
        if ($ticket === 'resolution') $pdo->prepare('INSERT INTO control_gauge_task_briefs (task_id, decision) VALUES (?, ?)')->execute([$id, mb_substr($what, 0, 4000)]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' added to the Work queue' . ($ticket !== 'work' ? ' as ' . strtolower(self::TICKETS[$ticket][0]) : '') . ': ' . $what, $who, '', $tz);
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
            if ($r['cleared_at'] === null && $r['undone_at'] === null) $r = self::advance($pdo, $r, $rel, isset($live[$rel]) && $rel !== '');   // the stages the system moves by its own checks
            $age = time() - (int) strtotime($r['at'] . ' UTC');
            $state = $r['undone_at'] !== null ? 'withdrawn' : ($r['cleared_at'] !== null ? 'done' : ($rel !== '' ? 'fixing' : ($owner !== '' ? 'taken' : ($age > self::LATE_HOURS * 3600 ? 'late' : 'open'))));
            if ($state !== 'done' && $state !== 'withdrawn' && ($r['ticket'] ?? '') === 'resolution' && ($r['stage'] ?? '') === 'waiting') $state = 'waiting';   // a Tier 3 brief: waiting for Jason, whoever has it
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

    /**
     * The decisions (ADRs) a task rests on (Jason, 2026-11-24: "need task to show what ADR is attached to it or have the ability to
     * look and assign adrs"): each from the admin's own list of decisions (DocsRepo::all(), System Configuration → Decisions),
     * never a second list. Attached and detached through Edit, each change in the task's History.
     */
    public static function adrs(PDO $pdo, int $id): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT adr, at, who FROM control_gauge_task_adrs WHERE task_id = ? AND removed_at IS NULL ORDER BY adr'); $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Every decision the admin knows: num => [num, title, file, status, replaced_by] (the Decisions page's own list). */
    public static function decisions(): array
    {
        $f = __DIR__ . '/DocsRepo.php';
        $clean = function (array $all): array { foreach ($all as $n => &$d) $d['title'] = preg_replace('/^' . preg_quote((string) $n, '/') . '\s*[:·.\-–]\s*/u', '', (string) $d['title']); unset($d); return $all; };   // the register's titles start with their number; the number is shown once
        if (is_file($f)) { try { require_once $f; return $clean(DocsRepo::all()); } catch (Throwable $x) {} }
        $out = []; $d = __DIR__ . '/../data/decisions-index.php';
        if (is_file($d)) foreach ((array) require $d as $r) if (($r[4] ?? '') !== 'note') $out[$r[0]] = ['num' => $r[0], 'title' => $r[1], 'file' => $r[3], 'status' => '', 'replaced_by' => ''];
        return $clean($out);
    }

    /** Attach a decision to a task (once: attached again, it stands). */
    public static function adrAttach(PDO $pdo, int $id, string $adr, string $who, string $tz = ''): string
    {
        self::open($pdo, $id);
        $adr = str_pad(preg_replace('/\D/', '', $adr), 4, '0', STR_PAD_LEFT);
        $all = self::decisions(); if (!isset($all[$adr])) throw new RuntimeException('There\'s no decision ' . $adr . ' in System Configuration → Decisions.');
        foreach (self::adrs($pdo, $id) as $a) if ($a['adr'] === $adr) return $adr . ' is already attached.';
        $pdo->prepare('INSERT INTO control_gauge_task_adrs (task_id, adr, at, who) VALUES (?, ?, UTC_TIMESTAMP(), ?)')->execute([$id, $adr, mb_substr($who, 0, 80)]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' decision ' . $adr . ' attached: ' . $all[$adr]['title'], $who, '', $tz);
        return 'Attached: ' . $adr . ', ' . $all[$adr]['title'] . '.';
    }

    /** Edit the attached decisions: keep only $keep (the others detached, each logged; kept in the table for the record). */
    public static function adrKeep(PDO $pdo, int $id, array $keep, string $who, string $tz = ''): string
    {
        self::open($pdo, $id);
        $keep = array_map(fn ($a) => (string) $a === 'none' ? 'none' : str_pad(preg_replace('/\D/', '', (string) $a), 4, '0', STR_PAD_LEFT), $keep); $gone = [];
        foreach (self::adrs($pdo, $id) as $a) {
            if (in_array($a['adr'], $keep, true)) continue;
            $pdo->prepare('UPDATE control_gauge_task_adrs SET removed_at = UTC_TIMESTAMP(), removed_by = ? WHERE task_id = ? AND adr = ? AND removed_at IS NULL')->execute([mb_substr($who, 0, 80), $id, $a['adr']]);
            ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' decision ' . $a['adr'] . ' detached', $who, '', $tz); $gone[] = $a['adr'];
        }
        return $gone ? 'Saved: ' . implode(', ', $gone) . ' detached. It\'s in the task\'s History.' : 'Nothing changed.';
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
        $c = ['open' => 0, 'taken' => 0, 'fixing' => 0, 'late' => 0, 'done' => 0, 'withdrawn' => 0, 'waiting' => 0];
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
            foreach ($pdo->query("SELECT * FROM control_gauge_tasks WHERE kind = 'fix' AND undone_at IS NULL AND cleared_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $req[] = ['id' => (int) $r['id'], 'at_utc' => str_replace(' ', 'T', (string) $r['at']) . 'Z', 'gauge' => $r['gauge'], 'build' => $r['value'], 'item' => $r['item'], 'what' => $r['what'], 'was' => $r['bucket'],
                    'why' => (string) $r['why'], 'source' => (string) $r['source'],
                    // a session's name as it is, and a Team role's ("system · Project Manager", a role, not a person); a person's never (0145 §1): "a staff member"
                    'owner' => $r['owner'] === '' ? '' : (stripos((string) $r['owner'], 'session') !== false || str_starts_with((string) $r['owner'], 'system · ') ? (string) $r['owner'] : 'a staff member'), 'release' => (string) $r['rel'],
                    'ticket' => (string) ($r['ticket'] ?? 'work'), 'stage' => (string) ($r['stage'] ?? ''), 'tier' => isset($r['tier']) ? (int) $r['tier'] : null,
                    'build_brief' => (string) ($r['build_brief'] ?? '')];   // the System Architect's brief for the session manager (Piece 2, P2-3)
            $ap = [];
            foreach (self::approvals($pdo) as $g => $ks) foreach ($ks as $k => $a) $ap[] = ['gauge' => $g, 'kind' => $k, 'value' => $a['value'], 'at_utc' => str_replace(' ', 'T', $a['at']) . 'Z'];
            return ['requests' => $req, 'approvals' => $ap];
        } catch (Throwable $x) { return ['requests' => [], 'approvals' => [], 'error' => $x->getMessage()]; }
    }

    // ---- tickets: types and stages (decisions/0179's Piece 2; handoffs/piece2-team.md §3.1, §8; approved by Jason 2026-11-25) ----
    /** The three ticket types (Jason's requirement 1): key => [name, what it is]. */
    public const TICKETS = [
        'work' => ['Work', 'Something to build or change: Planning, Building, Implementation, Confirmation'],
        'audit' => ['Audit', 'A finding from the daily health and drift audit: checked twice before it reaches anyone'],
        'resolution' => ['Resolution', 'A Tier 3 decision only the Founder makes, as a Decision Brief'],
    ];
    /** Each type's stages in order: key => [name, what moves it on]. */
    public const STAGES = [
        'work' => ['planning' => ['Planning', 'its goal and Done when, a decision attached (or none applies), and someone has it'],
                   'building' => ['Building', 'its release named'], 'implementation' => ['Implementation', 'its release live'],
                   'confirmation' => ['Confirmation', 'every step confirmed']],
        'audit' => ['found' => ['Found', 'its second look'], 'diagnosing' => ['Diagnosing', 'its check again'], 'confirmed' => ['Confirmed', 'the ticket it opens']],
        'resolution' => ['brief' => ['Brief', 'the decision, why it needs you, options A and B and the impact if delayed'], 'waiting' => ['Waiting for you', 'the Founder\'s answer'],
                         'decided' => ['Decided', 'its precedent recorded']],
    ];
    /** The Tier 3 boundaries (decisions/0179 rule 3): key => words. */
    public const BOUNDARIES = ['money' => 'Money, billing, refunds above the threshold, contracts', 'data' => 'Deleting or reshaping major data', 'address' => 'Changing public addresses',
        'nations' => 'Tribal nations\' land and sensitive sites (0066)', 'privacy' => 'Privacy', 'mission' => 'The mission\'s boundaries', 'deadlock' => 'A real deadlock between valid priorities'];
    /** A brief's fields: key => [label, required to send]. */
    public const BRIEF = ['decision' => ['The Decision', true], 'why_you' => ['Why it needs you', true], 'boundary' => ['Its Tier 3 boundary', true],
        'a_text' => ['Option A', true], 'a_pros' => ['A: pros', true], 'a_cons' => ['A: cons', true], 'b_text' => ['Option B', true], 'b_pros' => ['B: pros', true], 'b_cons' => ['B: cons', true],
        'recommend' => ['Recommended', true], 'delay_impact' => ['Impact if delayed', true], 'cost' => ['Cost', false]];

    public static function stagesOf(string $ticket): array { return self::STAGES[$ticket] ?? self::STAGES['work']; }

    /**
     * What a ticket needs before it may leave its stage: [] when nothing, else the missing things in words. Only the stages a
     * person moves (Planning, Brief) are pressed; the rest move by their own check (advance()).
     */
    public static function gate(PDO $pdo, array $r): array
    {
        $tk = (string) ($r['ticket'] ?? 'work'); $sg = (string) ($r['stage'] ?? ''); $miss = [];
        if ($tk === 'work' && $sg === 'planning') {
            if (trim((string) $r['goal']) === '') $miss[] = 'a goal';
            if (trim((string) $r['done_when']) === '') $miss[] = 'Done when';
            if (!self::adrs($pdo, (int) $r['id'])) $miss[] = 'a decision attached (or "No decision applies")';
            if (trim((string) $r['owner']) === '') $miss[] = 'someone to have it (Take it)';
        } elseif ($tk === 'work' && $sg === 'building') { if (trim((string) $r['rel']) === '') $miss[] = 'its release named';
        } elseif ($tk === 'work' && $sg === 'implementation') { $miss[] = 'its release is live (it moves by itself)';
        } elseif ($tk === 'work' && $sg === 'confirmation') { $miss[] = 'every step is confirmed (it moves by itself)';
        } elseif ($tk === 'resolution' && $sg === 'brief') {
            $b = self::brief($pdo, (int) $r['id']) ?? [];
            foreach (self::BRIEF as $k => [$lb, $req]) if ($req && trim((string) ($b[$k] ?? '')) === '') $miss[] = strtolower($lb);
        } elseif ($tk === 'resolution' && $sg === 'waiting') { $miss[] = 'the Founder answers';
        } elseif ($tk === 'resolution' && $sg === 'decided') { $miss[] = 'its precedent is recorded (it moves by itself)';
        } elseif ($tk === 'audit') { $miss[] = 'its second look is done (the audit, on staging)'; }
        return $miss;
    }

    /** The stage after this one, or '' at the last. */
    public static function nextStage(string $ticket, string $stage): string
    {
        $k = array_keys(self::stagesOf($ticket)); $i = array_search($stage, $k, true);
        return $i === false || $i + 1 >= count($k) ? '' : $k[$i + 1];
    }

    /** Record a stage move (the ticket's History and the moves table) and set it. */
    private static function setStage(PDO $pdo, array $r, string $to, string $who, string $why, string $tz = '', ?int $run = null): void
    {
        $pdo->prepare('UPDATE control_gauge_tasks SET stage = ?, stage_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$to, (int) $r['id']]);
        $pdo->prepare('INSERT INTO control_gauge_task_moves (task_id, at, tz, from_stage, to_stage, who, why, run_id) VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?)')
            ->execute([(int) $r['id'], self::tz($tz), (string) $r['stage'], $to, mb_substr($who, 0, 80), mb_substr($why, 0, 400), $run]);
        $nm = self::stagesOf((string) $r['ticket']);
        ControlLog::add($pdo, 'control', '', 'Task #' . $r['id'] . ' moved: ' . ($nm[$r['stage']][0] ?? ($r['stage'] ?: 'no stage')) . ' → ' . ($nm[$to][0] ?? $to) . ($why !== '' ? ' (' . $why . ')' : ''), $who, '', $tz);
    }

    /**
     * A person's move: forward one stage when its gate passes, or back to an earlier stage with a reason (CLAUDE.md "More than
     * one way forward": ✎ corrects what happened). Never past a stage the system moves by its own check.
     */
    public static function move(PDO $pdo, int $id, string $to, string $who, string $why = '', string $tz = '', ?int $run = null): string
    {
        $r = self::task($pdo, $id);
        if (!$r || $r['undone_at'] !== null || $r['cleared_at'] !== null) throw new RuntimeException('That task is done or withdrawn.');
        $tk = (string) ($r['ticket'] ?: 'work'); $st = self::stagesOf($tk); $keys = array_keys($st);
        if (!isset($st[$to])) throw new RuntimeException('That isn\'t one of a ' . strtolower(self::TICKETS[$tk][0]) . ' ticket\'s stages.');
        $from = array_search((string) $r['stage'], $keys, true); $dest = array_search($to, $keys, true);
        if ($from === $dest) return 'It\'s already at ' . $st[$to][0] . '.';
        if ($from !== false && $dest < $from) {
            if (trim($why) === '') throw new RuntimeException('Say why it goes back: it\'s kept in its History.');
            self::setStage($pdo, $r, $to, $who, trim($why), $tz, $run);
            return 'Back to ' . $st[$to][0] . '. Your reason is in its History.';
        }
        if ($from === false || $dest !== $from + 1) throw new RuntimeException('It moves one stage at a time.');
        if ($tk === 'resolution' && $to === 'decided') throw new RuntimeException('Only the Founder\'s answer decides it.');
        if (($tk === 'work' && in_array($to, ['confirmation'], true)) || $tk === 'audit') throw new RuntimeException('It moves there by itself when its check passes.');
        if ($m = self::gate($pdo, $r)) throw new RuntimeException('It needs ' . implode(', ', $m) . ' first.');
        self::setStage($pdo, $r, $to, $who, trim($why), $tz, $run);
        return 'Moved to ' . $st[$to][0] . '.';
    }

    /**
     * The moves the system makes by their own checks (piece2-team.md §8; Jason's Q2 A, 2026-11-25): Building → Implementation
     * once its release is named; Implementation → Confirmation once that release is live; Confirmation → Done once every step
     * is confirmed (a task with no steps is Done at once, as before). A Resolution closes once its precedent ticket is done.
     * A task from before the stages (stage empty) is done when its release is live, as #281 said.
     */
    private static function advance(PDO $pdo, array $r, string $rel, bool $live): array
    {
        $tk = (string) ($r['ticket'] ?? 'work'); $sg = (string) ($r['stage'] ?? '');
        $done = function (string $how) use ($pdo, &$r) {
            $pdo->prepare('UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP(), done_how = ? WHERE id = ? AND cleared_at IS NULL')->execute([mb_substr($how, 0, 255), (int) $r['id']]);
            $r['cleared_at'] = gmdate('Y-m-d H:i:s'); $r['done_how'] = $how;
            ControlLog::add($pdo, 'clear', '', 'Task #' . $r['id'] . ' done: ' . preg_replace('/^Done: /', '', $how) . ' (' . $r['what'] . ')', 'the system');
        };
        if ($tk === 'work') {
            if ($sg === '') { if ($live) $done('Done: ' . $rel . ' is live'); return $r; }
            if ($sg === 'building' && $rel !== '') { self::setStage($pdo, $r, 'implementation', 'the system', 'its release is named: ' . $rel); $r['stage'] = $sg = 'implementation'; }
            if ($sg === 'implementation' && $live) { self::setStage($pdo, $r, 'confirmation', 'the system', $rel . ' is live'); $r['stage'] = $sg = 'confirmation'; }
            if ($sg === 'confirmation') {
                $steps = self::steps($pdo, (int) $r['id']); $open = count(array_filter($steps, fn ($s) => $s['done_at'] === null));
                if ($open === 0) $done($steps ? 'Done: ' . ($rel !== '' ? $rel . ' is live and ' : '') . 'every step is confirmed' : 'Done: ' . ($rel !== '' ? $rel . ' is live' : 'confirmed'));
            }
        } elseif ($tk === 'audit' && $sg === 'confirmed') {
            $st = $pdo->prepare("SELECT id FROM control_gauge_tasks WHERE parent_id = ? AND cleared_at IS NOT NULL AND undone_at IS NULL LIMIT 1"); $st->execute([(int) $r['id']]);
            if ($c = $st->fetchColumn()) $done('Done: the ticket it opened is done (task #' . $c . ')');
        } elseif ($tk === 'resolution' && $sg === 'decided') {
            $st = $pdo->prepare("SELECT id FROM control_gauge_tasks WHERE parent_id = ? AND ticket = 'work' AND cleared_at IS NOT NULL AND undone_at IS NULL LIMIT 1"); $st->execute([(int) $r['id']]);
            if ($c = $st->fetchColumn()) $done('Done: its precedent is recorded (task #' . $c . ')');
        }
        return $r;
    }

    /** "No decision applies" (the Planning gate's other answer): kept as a decision row 'none', detached with ✎ like any other. */
    public static function noDecision(PDO $pdo, int $id, string $who, string $tz = ''): string
    {
        self::open($pdo, $id);
        foreach (self::adrs($pdo, $id) as $a) if ($a['adr'] === 'none') return 'It already says no decision applies.';
        $pdo->prepare('INSERT INTO control_gauge_task_adrs (task_id, adr, at, who) VALUES (?, ?, UTC_TIMESTAMP(), ?)')->execute([$id, 'none', mb_substr($who, 0, 80)]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' decision none attached: no decision applies', $who, '', $tz);
        return 'Saved: no decision applies. ✎ takes it off.';
    }

    /** A Resolution ticket's brief, or null. */
    public static function brief(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_task_briefs WHERE task_id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Edit a brief's field (CLAUDE.md "Edit or Undo"): the old words in its History. Only while it's a draft (Brief). */
    public static function briefEdit(PDO $pdo, int $id, string $field, string $value, string $who, string $tz = ''): string
    {
        $r = self::open($pdo, $id);
        if (($r['ticket'] ?? '') !== 'resolution') throw new RuntimeException('Only a Resolution ticket has a brief.');
        if (!isset(self::BRIEF[$field])) throw new RuntimeException('That isn\'t part of a brief.');
        if ((string) $r['stage'] !== 'brief') throw new RuntimeException('It\'s with the Founder: move it back to Brief (✎) to change the brief.');
        $v = trim(str_replace("\r", '', $value));
        if ($field === 'boundary' && $v !== '' && !isset(self::BOUNDARIES[$v])) throw new RuntimeException('Choose one of the Tier 3 boundaries (decisions/0179).');
        if ($field === 'recommend' && $v !== '' && !in_array($v, ['a', 'b'], true)) throw new RuntimeException('Recommend A or B.');
        $b = self::brief($pdo, $id);
        if (!$b) { $pdo->prepare('INSERT INTO control_gauge_task_briefs (task_id) VALUES (?)')->execute([$id]); $b = self::brief($pdo, $id); }
        if ($v === (string) ($b[$field] ?? '')) return 'Nothing changed.';
        $pdo->prepare('UPDATE control_gauge_task_briefs SET ' . $field . ' = ? WHERE task_id = ?')->execute([mb_substr($v, 0, $field === 'cost' ? 255 : 4000), $id]);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' brief edited: ' . strtolower(self::BRIEF[$field][0]) . ' "' . mb_substr((string) ($b[$field] ?? ''), 0, 120) . '" → "' . mb_substr($v, 0, 120) . '"', $who, '', $tz);
        return 'Saved. The old words are in its History.';
    }

    /** Send the brief to the Founder: Brief → Waiting for you, once its four parts and both options are filled. */
    public static function sendBrief(PDO $pdo, int $id, string $who, string $tz = ''): string
    {
        $r = self::open($pdo, $id);
        if (($r['ticket'] ?? '') !== 'resolution') throw new RuntimeException('Only a Resolution ticket has a brief.');
        $msg = self::move($pdo, $id, 'waiting', $who, 'the brief is ready', $tz);
        return str_starts_with($msg, 'Moved') ? 'Sent: it waits for the Founder\'s answer, in the daily summary and here.' : $msg;
    }

    /**
     * The Founder's answer (Jason's Q3 A, 2026-11-25): A, B or their own words, on the page, or relayed by the session manager
     * from the daily summary's email. Decided; a Work ticket opens to record it as precedent (a note in the decision it
     * touches and a CLAUDE.md line; 0179 rule 4), and the Resolution closes when that one is done. Corrected with ✎ (kept in
     * History) while the precedent isn't recorded yet.
     */
    public static function answer(PDO $pdo, int $id, string $ans, string $words, string $who, string $tz = ''): string
    {
        $r = self::open($pdo, $id);
        if (($r['ticket'] ?? '') !== 'resolution') throw new RuntimeException('Only a Resolution ticket takes an answer.');
        if (!in_array($ans, ['a', 'b', 'o'], true)) throw new RuntimeException('Choose A, B, or answer in your words.');
        $words = trim(str_replace("\r", '', $words)); if ($ans === 'o' && $words === '') throw new RuntimeException('Say your answer: it becomes the precedent.');
        $sg = (string) $r['stage'];
        if ($sg === 'brief') throw new RuntimeException('The brief isn\'t sent yet: send it first.');
        $b = self::brief($pdo, $id) ?? [];
        $was = (string) ($b['answer'] ?? '');
        $pdo->prepare('UPDATE control_gauge_task_briefs SET answer = ?, answer_words = ?, answered_by = ?, answered_at = UTC_TIMESTAMP(), answer_tz = ? WHERE task_id = ?')
            ->execute([$ans, mb_substr($words, 0, 4000), mb_substr($who, 0, 80), self::tz($tz), $id]);
        $say = ['a' => 'A' . (($b['a_text'] ?? '') !== '' ? ': ' . $b['a_text'] : ''), 'b' => 'B' . (($b['b_text'] ?? '') !== '' ? ': ' . $b['b_text'] : ''), 'o' => 'in their words: ' . $words][$ans];
        if ($sg === 'decided') {   // a correction, before its precedent is recorded
            ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' answer corrected: ' . ($was !== '' ? strtoupper($was) : 'none') . ' → ' . mb_substr($say, 0, 200), $who, '', $tz);
            return 'Saved. The answer before is in its History.';
        }
        self::setStage($pdo, $r, 'decided', $who, 'answered ' . mb_substr($say, 0, 200), $tz);
        $pid = self::add($pdo, (string) $r['gauge'], 'Record the precedent of task #' . $id . ': ' . mb_substr((string) $r['what'], 0, 300),
            'The Founder decided ' . mb_substr($say, 0, 300) . '. A resolved Tier 3 becomes standing precedent: a note in the decision it touches (or a new one) and a CLAUDE.md line where it changes how sessions work (decisions/0179 rule 4).',
            'Task #' . $id . ' (Resolution)', $who, 0, $tz, 'work', $id);
        $pdo->prepare('UPDATE control_gauge_tasks SET priority = 1 WHERE id = ?')->execute([$pid]);
        return 'Decided: ' . $say . '. Task #' . $pid . ' records it as precedent; this one closes when that\'s done.';
    }

    /**
     * The audit's own writes (Piece 2, P2-4; handoffs/piece2-team.md §6), on Audit tickets only: one finding, one ticket (by its
     * audit_key); its second look confirms it (and opens the Work or Resolution ticket it calls for) or clears it as noise.
     */
    public static function auditOpen(PDO $pdo, string $key, string $what, string $why, int $tier, string $owner, string $tz = ''): int
    {
        self::ensure($pdo);
        $st = $pdo->prepare("SELECT id FROM control_gauge_tasks WHERE ticket = 'audit' AND audit_key = ? AND undone_at IS NULL AND cleared_at IS NULL LIMIT 1"); $st->execute([$key]);
        if ($id = (int) $st->fetchColumn()) return $id;
        $id = self::add($pdo, 'area:audit', $what, $why, 'the daily audit (' . $key . ')', $owner, 0, $tz, 'audit');
        $pdo->prepare('UPDATE control_gauge_tasks SET audit_key = ?, tier = ?, owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([mb_substr($key, 0, 120), $tier, mb_substr($owner, 0, 80), $id]);
        return $id;
    }

    /** Its second look found it still there: Found → Confirmed, and the Work (Tier 1–2) or Resolution (Tier 3) ticket it calls for. */
    public static function auditConfirm(PDO $pdo, int $id, string $who, string $tz = ''): int
    {
        $r = self::open($pdo, $id); if (($r['ticket'] ?? '') !== 'audit') throw new RuntimeException('Only an Audit ticket is confirmed by the audit.');
        self::setStage($pdo, $r, 'confirmed', $who, 'still there on its second look', $tz);
        $tier = (int) ($r['tier'] ?? 2);
        $child = self::add($pdo, (string) $r['gauge'], ($tier === 3 ? 'Decide: ' : 'Fix: ') . (string) $r['what'], 'Confirmed by the daily audit (task #' . $id . ', Tier ' . $tier . '): ' . (string) $r['why'], 'task #' . $id . ' (Audit)', $who, 0, $tz, $tier === 3 ? 'resolution' : 'work', $id);
        $pdo->prepare('UPDATE control_gauge_tasks SET tier = ? WHERE id = ?')->execute([$tier, $child]);
        return $child;
    }

    /** Its second look found it gone: cleared by its own check (noise), never by a press (CLAUDE.md four steps). */
    public static function auditClear(PDO $pdo, int $id, string $who): void
    {
        $r = self::open($pdo, $id); if (($r['ticket'] ?? '') !== 'audit') return;
        $pdo->prepare('UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP(), done_how = ? WHERE id = ? AND cleared_at IS NULL')->execute(['Done: cleared by its own check (it no longer shows on its second look)', $id]);
        ControlLog::add($pdo, 'clear', '', 'Task #' . $id . ' done: cleared by its own check, it no longer shows (' . $r['what'] . ')', $who);
    }

    /** A ticket's stage moves, oldest first. */
    public static function moves(PDO $pdo, int $id): array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_gauge_task_moves WHERE task_id = ? ORDER BY id'); $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function tz(string $tz): string { return preg_match('~^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+){0,2}$~', $tz) ? $tz : ''; }
}
