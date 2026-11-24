<?php
// app/lib/ControlAlarms.php — the System Control Board's annunciator (decisions/0171 §5, §7; release 3a). One alarm list
// for the whole system, by level: a trip (stopped, crashed, can't run what's switched on), a warning (slow, retrying,
// near a limit, a wrong count) or a notice. An alarm is raised the moment its reading goes wrong (the ledger, a crawler
// run's report, a service failing; silence by the 5-minute watch), and the same problem again updates the open alarm,
// never a second. A warning or notice clears by itself when its reading is good again, saying how. A trip doesn't (Jason,
// 2026-11-18, release 3c): a crash is first a warning while the system tries its own fix (control_fix_tries, or
// control_fix_minutes, whichever comes first); then it's a trip: the Founder is emailed, its Pulse item goes to the
// Director's level, what it holds is stopped (as narrow as its cause: one workflow, task or service; the master switch only
// when the crawler itself is down) as Paused by the system (decisions/0175 §3). It closes itself as Recovered when its check
// passes, escalated or not (ESCALATED_RECOVERS), or by an executive's Check and start again, which checks first, with Undo. A refusal or running out of budget trips at once. Acknowledging stops the repeat email. A trip is emailed to the Founder at once through the one dispatcher
// (Notify::send, kind alarm.trip, decisions/0172). Only a trip reaches a person (Pulse, email, push): a warning clears
// itself or becomes a trip, so it stays on the board (Jason, 2026-11-19: "everything else should be cleared by the system
// so why send and pulse?"; 0171 §7's note).
// Ids, words and readings only: nothing about a person.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ControlAlarms
{
    public const LEVELS = ['trip' => 'Trip', 'warning' => 'Warning', 'notice' => 'Notice'];
    /** What kind of problem (0171 §5's table). */
    public const KINDS = ['slow' => 'Slow', 'retry' => 'Retrying', 'crash' => 'Crashed', 'held' => 'Held or out', 'limit' => 'Near a limit',
                          'count' => 'Wrong count', 'silent' => 'Gone quiet', 'check' => 'Its check'];
    private const RANK = ['notice' => 0, 'warning' => 1, 'trip' => 2];
    /** Kinds with nothing the system can try: a refusal, a key refused, a quota or budget used up. They trip at once. */
    public const AT_ONCE = ['held', 'limit'];
    private static bool $ready = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_alarms (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            akey VARCHAR(120) NOT NULL, proc VARCHAR(60) NOT NULL DEFAULT '', level VARCHAR(10) NOT NULL, kind VARCHAR(12) NOT NULL,
            title VARCHAR(200) NOT NULL, means VARCHAR(400) NOT NULL DEFAULT '', todo VARCHAR(400) NOT NULL DEFAULT '', reading VARCHAR(255) NOT NULL DEFAULT '',
            fix VARCHAR(400) NOT NULL DEFAULT '', n_times INT UNSIGNED NOT NULL DEFAULT 1, first_at DATETIME NOT NULL, last_at DATETIME NOT NULL,
            ack_uid INT UNSIGNED NULL, ack_name VARCHAR(80) NULL, ack_at DATETIME NULL, cleared_at DATETIME NULL, cleared_how VARCHAR(255) NULL,
            mailed_at DATETIME NULL, tripped_at DATETIME NULL, good_at DATETIME NULL, good_how VARCHAR(255) NULL, stopped VARCHAR(400) NULL,
            resolved_uid INT UNSIGNED NULL, resolved_name VARCHAR(80) NULL, resolved_at DATETIME NULL, resolve_note VARCHAR(400) NULL, retrying_at DATETIME NULL, component VARCHAR(80) NULL, try_n TINYINT UNSIGNED NOT NULL DEFAULT 0, next_try_at DATETIME NULL, incident_id INT UNSIGNED NULL, KEY open_key (akey, cleared_at), KEY proc (proc), KEY cleared (cleared_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // the one engine's columns (Migration A, 2026-12-01_control_engine.sql): until they're there, the old rules stand
        try { self::$engine = (bool) $pdo->query("SHOW COLUMNS FROM control_alarms LIKE 'next_try_at'")->fetch(); } catch (Throwable $x) { self::$engine = false; }
        self::$ready = true;
    }

    /** Whether the one engine's columns are in (ControlEngine decides the level, the tries and the hold). */
    private static bool $engine = false;
    public static function engineOn(PDO $pdo): bool { self::ensure($pdo); return self::$engine; }

    /** The board's process an endpoint serves: its workflow (a task named there counts as its task's workflow) or its worker. */
    public static function procOfEndpoint(PDO $pdo, string $ep): string
    {
        require_once __DIR__ . '/ControlRegister.php';
        $f = (string) (((array) (ControlRegister::ENDPOINTS[$ep][1] ?? []))[0] ?? '');
        if ($f === '') return 'ep:' . $ep;
        if ($f[0] === '@') return 'wk:' . substr($f, 1);
        if (preg_match('/^W\d+$/', $f)) return 'wf:' . $f;
        try { $wf = (string) (ControlRegister::taskWorkflows($pdo)[$f] ?? ''); if ($wf !== '') return 'wf:' . $wf; } catch (Throwable $x) {}
        return 't:' . $f;
    }

    /** The open alarm with this key, or null. */
    /** An open alarm's words brought up to date (its title, and its reading when given), nothing else. Never throws. */
    public static function retitle(PDO $pdo, string $key, string $title, ?string $reading = null): void
    {
        try {
            self::ensure($pdo);
            $o = self::openOf($pdo, $key);
            if (!$o || ((string) $o['title'] === $title && ($reading === null || (string) $o['reading'] === $reading))) return;
            $pdo->prepare('UPDATE control_alarms SET title = ?' . ($reading !== null ? ', reading = ?' : '') . ' WHERE id = ?')
                ->execute(array_merge([mb_substr($title, 0, 200)], $reading !== null ? [mb_substr($reading, 0, 255)] : [], [(int) $o['id']]));
        } catch (Throwable $x) { error_log('control alarm retitle ' . $key . ': ' . $x->getMessage()); }
    }

    public static function openOf(PDO $pdo, string $key): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_alarms WHERE akey = ? AND cleared_at IS NULL ORDER BY id DESC LIMIT 1'); $st->execute([$key]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Raises an alarm, or updates the open one with the same key (its count, last seen, words, and a higher level if it
     * got worse). $fix: [label, action, id] a button the board and Pulse press in place ('' none), or a list of them.
     * $event false: a reading still bad, said again (a cap, a red line, a quiet worker): last seen moves, the count doesn't.
     * Returns its id. Never throws: a failing alarm must not break what raised it.
     */
    public static function raise(PDO $pdo, string $key, string $proc, string $level, string $kind, string $title, string $means = '', string $todo = '', string $reading = '', array $fix = [], bool $event = true, bool $tryFirst = true, string $component = ''): int
    {
        try {
            self::ensure($pdo);
            $level = isset(self::LEVELS[$level]) ? $level : 'warning'; $kind = isset(self::KINDS[$kind]) ? $kind : 'check';
            // slow is never a trip (Jason, 2026-11-19: "no dead ends, system fixes first"): a slow call or job does less, it
            // isn't broken; stopping it made it do nothing. A warning that says how slow and clears itself when speed returns.
            if ($kind === 'slow' && $level === 'trip') $level = 'warning';
            // in maintenance (0171 §2.5, release 4a): what it raises is a notice, saying so; a trip already open stays a trip
            if ($level !== 'notice' && !str_starts_with($key, 'maint:')) {
                require_once __DIR__ . '/ControlMaintenance.php';
                $mt = ControlMaintenance::outFor($pdo, $proc);
                if ($mt) { $level = 'notice'; $means = trim(ControlMaintenance::words($mt) . '. ' . $means); }
            }
            // a critical part (0171 §5's note, Jason 2026-11-19: "if a system critical part goes into retry it misses the fact that
            // it was system critical, this is one that should immediately trip"): one carrying a duty (ControlRegister::duty) trips
            // on its first failure, while the system keeps trying; its retry warnings count as failures too
            $duty = ''; try { require_once __DIR__ . '/ControlRegister.php'; $duty = ControlRegister::duty($pdo, $proc); } catch (Throwable $x) {}
            // P8 (02 §1): "Critical is decided by the part's duty, never by the alarm's kind or the master switch": any fault on
            // a critical part trips at once (rows 68, 72), but slow is never a trip and a cap's band is only a warning (BL)
            // A module's red pre-flight line ('check' on svc:…) may be a part of it that carries no duty (row 56: cPanel's API on
            // Bluehost is "board only", never Page load), so a check trips at once only on the site's own parts and jobs
            if ($duty !== '' && $level === 'warning' && !in_array($kind, ['slow', 'limit'], true) && ($kind !== 'check' || preg_match('/^(site|job):/', $proc))) { $level = 'trip'; $tryFirst = true; }
            $early = false; $escalate = false; $comp = ''; $next = null;
            // the one engine (02 §5, P2, P3, P8): the component that failed, and its tries on the diagnostic clock
            require_once __DIR__ . '/ControlEngine.php';
            $comp = $component !== '' ? $component : ControlEngine::componentOf($pdo, $key, $proc, $reading);
            $d = ControlEngine::decide($pdo, self::openOf($pdo, $key), $comp, $level, $kind, $tryFirst, $event, $duty);
            [$level, $early, $escalate, $next] = [$d['level'], $d['early'], $d['escalate'], $d['next']];
            $cut = fn (string $s, int $n) => mb_substr(trim($s), 0, $n);
            $fx = $fix ? json_encode(isset($fix[0]) && is_array($fix[0]) ? array_slice($fix, 0, 4) : array_slice($fix, 0, 4), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';   // one button [label, action, id(, confirm|ask)], or a list of them
            // the column holds 400: whole buttons are dropped from the end, never a cut that breaks the list
            while (mb_strlen($fx) > 400 && isset($fix[0]) && is_array($fix[0]) && count($fix) > 1) { array_pop($fix); $fx = json_encode(array_slice($fix, 0, 4), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
            if (mb_strlen($fx) > 400) $fx = '';
            $o = self::openOf($pdo, $key);
            if ($o) {
                // worse goes up; a trip raised before 3c's rule (no tripped_at) takes the level it's read at now
                $lv = self::RANK[$level] > self::RANK[(string) $o['level']] || ($o['level'] === 'trip' && empty($o['tripped_at'])) ? $level : (string) $o['level'];
                $pdo->prepare('UPDATE control_alarms SET n_times = n_times + ' . ($event ? 1 : 0) . ', last_at = UTC_TIMESTAMP(), level = ?, title = ?, means = ?, todo = ?, reading = ?, fix = ? WHERE id = ?')
                    ->execute([$lv, $cut($title, 200), $cut($means, 400), $cut($todo, 400), $cut($reading, 255), mb_substr($fx, 0, 400), (int) $o['id']]);
                if (self::$engine) $pdo->prepare('UPDATE control_alarms SET component = ?, next_try_at = ? WHERE id = ?')->execute([$cut($comp, 80) ?: null, $lv === 'trip' && empty($early) && $o['level'] !== 'trip' ? null : $next, (int) $o['id']]);
                if ($lv === 'trip' && $o['level'] !== 'trip') {   // it got worse: a trip now, and the logbook keeps that moment (g4 row 16)
                    self::log($pdo, 'alarm', $proc, 'Trip: ' . $cut($title, 200), '', 'Its next run failed too at ' . gmdate('H:i') . ' UTC: ' . $cut($reading, 200));
                    if ($early) self::trippedEarly($pdo, (int) $o['id'], $duty); else self::tripped($pdo, (int) $o['id']);
                }
                elseif ($escalate) self::escalated($pdo, (int) $o['id'], $duty);
                return (int) $o['id'];
            }
            $pdo->prepare('INSERT INTO control_alarms (akey, proc, level, kind, title, means, todo, reading, fix, first_at, last_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
                ->execute([$cut($key, 120), $cut($proc, 60), $level, $kind, $cut($title, 200), $cut($means, 400), $cut($todo, 400), $cut($reading, 255), mb_substr($fx, 0, 400)]);
            $id = (int) $pdo->lastInsertId();
            if (self::$engine) $pdo->prepare('UPDATE control_alarms SET component = ?, next_try_at = ? WHERE id = ?')->execute([$cut($comp, 80) ?: null, $next, $id]);
            if (!str_starts_with($key, 'maint:')) self::log($pdo, 'alarm', $proc, self::LEVELS[$level] . ': ' . $cut($title, 200), '', $cut($means, 300));   // the logbook (0171 §10, 5a); maintenance logs itself
            if ($level === 'trip') { if ($early) self::trippedEarly($pdo, $id, $duty); else self::tripped($pdo, $id); }
            return $id;
        } catch (Throwable $x) { error_log('control alarm ' . $key . ': ' . $x->getMessage()); return 0; }
    }

    /**
     * Its reading is good again: a warning or notice clears, saying how. A trip closes itself as Recovered when its check
     * passes, escalated or not, and its pause lifts (decisions/0175 §3; Jason, 2026-11-20: "Close as Recovered";
     * ESCALATED_RECOVERS). A trip raised before 0171 3c's rule (no tripped_at) still clears as it did. Returns whether one
     * cleared. Never throws.
     */
    public static function clear(PDO $pdo, string $key, string $how): bool
    {
        try {
            self::ensure($pdo);
            $o = self::openOf($pdo, $key);
            if (!$o) return false;
            if ($o['level'] === 'trip' && !empty($o['retrying_at'])) return self::recovered($pdo, $o, $how);   // a critical part the system fixed
            if ($o['level'] === 'trip' && !empty($o['tripped_at'])) {
                // its check passes: the system's pause lifts and its work runs again (decisions/0175 §3), and the trip closes
                // as Recovered, escalated or not (Jason, 2026-11-20: "Close as Recovered"; ESCALATED_RECOVERS)
                require_once __DIR__ . '/Automation.php';
                Automation::sysResume($pdo, (int) $o['id'], 'Its check passed: ' . $how);
                if (self::ESCALATED_RECOVERS) {
                    // closed as Recovered first (02 §3c: "Recovered: <part> was down <n> min, the system fixed it"), then its
                    // issue's hold lifts: resolving the issue first closed the alarm as "Resolved by the system" (S-A10, S-C90)
                    $ok = self::recovered($pdo, $o, $how);
                    foreach ((array) json_decode((string) ($o['stopped'] ?? ''), true) as $st) if (($st[0] ?? '') === 'issue') { try { require_once __DIR__ . '/ServiceHealth.php'; ServiceHealth::resolve($pdo, (int) $st[1], 0, 'Its check passed: the system fixed it'); } catch (Throwable $x) {} }
                    if (preg_match('/^svc:issue:(\d+)$/', (string) $o['akey'], $m)) { try { require_once __DIR__ . '/ServiceHealth.php'; ServiceHealth::resolve($pdo, (int) $m[1], 0, 'Its check passed: the system fixed it'); } catch (Throwable $x) {} }
                    return $ok;
                }
                if (empty($o['good_at'])) $pdo->prepare('UPDATE control_alarms SET good_at = UTC_TIMESTAMP(), good_how = ? WHERE id = ?')->execute([mb_substr($how, 0, 255), (int) $o['id']]);
                return false;
            }
            return self::close($pdo, (int) $o['id'], $how);
        } catch (Throwable $x) { error_log('control alarm clear ' . $key . ': ' . $x->getMessage()); return false; }
    }

    /** Closed by a person's own act on its cause (Mark resolved, Reactivate): any level, trips included. */
    public static function closeKey(PDO $pdo, string $key, string $how): bool
    {
        $o = self::openOf($pdo, $key);
        return $o ? self::close($pdo, (int) $o['id'], $how) : false;
    }

    private static function close(PDO $pdo, int $id, string $how): bool
    {
        $st = $pdo->prepare('UPDATE control_alarms SET cleared_at = UTC_TIMESTAMP(), cleared_how = ? WHERE id = ? AND cleared_at IS NULL');
        $st->execute([mb_substr($how, 0, 255), $id]);
        $ok = $st->rowCount() > 0;
        if ($ok && self::$engine) { $pdo->prepare('UPDATE control_alarms SET next_try_at = NULL WHERE id = ?')->execute([$id]); try { require_once __DIR__ . '/ControlIncidents.php'; ControlIncidents::memberClosed($pdo, $id, $how); } catch (Throwable $x) {} }
        // the logbook keeps the finished story (decisions/0176 §2, §4 step 5): how it closed, what it had paused, since when
        if ($ok && ($a = self::byId($pdo, $id)) && !str_starts_with((string) $a['akey'], 'maint:')) {
            $story = $how; try { require_once __DIR__ . '/Thread.php'; $story = Thread::text(self::facts($pdo, $a), 'logbook'); } catch (Throwable $x) {}
            self::log($pdo, 'clear', (string) $a['proc'], 'Cleared: ' . (string) $a['title'], '', $story);
        }
        return $ok;
    }

    /** One line in the board's logbook (ControlLog, 0171 §10, release 5a). Never throws. */
    private static function log(PDO $pdo, string $kind, string $proc, string $what, string $who = '', string $why = ''): void
    {
        try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, $kind, $proc, $what, $who, $why); } catch (Throwable $x) {}
    }

    /** [tries, minutes] before a problem the system is fixing becomes a trip (slow has its own count). */
    public static function limits(PDO $pdo, string $kind): array
    {
        $g = function (string $k, int $d) use ($pdo): int { try { require_once __DIR__ . '/Thresholds.php'; $v = Thresholds::get($pdo, $k); return $v === null ? $d : max(1, (int) $v); } catch (Throwable $x) { return $d; } };
        return [$kind === 'slow' ? $g('control_slow_trips', 3) : $g('control_fix_tries', 3), $g('control_fix_minutes', 60)];
    }

    // ---- a trip: to an admin, and what it holds stopped -----------------------------------------------------------

    private const BY = 'the Control Board (a trip)';

    /** An escalated trip whose check passes later closes itself as Recovered, with a notice to whoever it was escalated to
     *  (Jason, 2026-11-20, in the decision pop-up: "Close as Recovered"); false would wait for Check and start again. */
    public const ESCALATED_RECOVERS = true;

    /**
     * It's a trip now: the Founder is emailed at once (§7), its Pulse item goes to the Director's level, and what it holds
     * is stopped, as narrow as its cause. What was stopped is kept, so Resolve starts only that again.
     */
    private static function tripped(PDO $pdo, int $id): void
    {
        try {
            $pdo->prepare('UPDATE control_alarms SET tripped_at = UTC_TIMESTAMP() WHERE id = ? AND tripped_at IS NULL')->execute([$id]);
            $a = self::byId($pdo, $id);
            if (!$a) return;
            $stopped = self::stop($pdo, $a);
            $pdo->prepare('UPDATE control_alarms SET stopped = ? WHERE id = ?')->execute([$stopped ? mb_substr(json_encode($stopped, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) : null, $id]);
            self::incident($pdo, $id, $stopped);
            // the Founder at once by the thread; Pulse's clock raises it to the Manager at 2 h and the Director at 4 h (R76; 02
            // §3c, §5.3 removes "escalateTo(…, 2) at a trip": g3 C15)
        } catch (Throwable $x) { error_log('control trip ' . $id . ': ' . $x->getMessage()); }
        self::mail($pdo, $id);
    }

    /** Its Incident Report (P12): opened or joined at the trip, with what it holds. */
    private static function incident(PDO $pdo, int $id, array $stopped): void
    {
        if (!self::$engine) return;
        try { require_once __DIR__ . '/ControlIncidents.php'; $ir = ControlIncidents::forTrip($pdo, $id); ControlIncidents::radius($pdo, $ir, array_map(fn ($s) => (string) $s[2], $stopped)); } catch (Throwable $x) {}
    }

    /**
     * The system's tries ran out (ControlEngine::tick: three failed checks, or the hour): a warning is a trip now, and holds
     * what its cause touches; a critical part's trip, already told, gets the Escalation Notice.
     */
    public static function triesOut(PDO $pdo, int $id): void
    {
        $a = self::byId($pdo, $id); if (!$a || $a['cleared_at'] !== null) return;
        if ($a['level'] === 'trip') { require_once __DIR__ . '/ControlRegister.php'; self::escalated($pdo, $id, ControlRegister::duty($pdo, (string) $a['proc'])); return; }
        $pdo->prepare("UPDATE control_alarms SET level = 'trip' WHERE id = ? AND level = 'warning'")->execute([$id]);
        self::tripped($pdo, $id);
        self::log($pdo, 'alarm', (string) $a['proc'], 'Trip: ' . (string) $a['title'], '', 'The system\'s tries ran out at ' . gmdate('H:i') . ' UTC with no fix: ' . (string) $a['reading']);
    }

    public static function byId(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_alarms WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * What a trip stops (Jason, 2026-11-18; playbook §2, a hold as narrow as its cause), each [what, ref, words]:
     * a workflow (its tasks first, the board's Shut down), a task, an outside service out of budget, a service issue held
     * until resolved, W0 Indexer; the master switch only when the crawler itself is down (a run that failed outright or
     * never reported). A site job not tied to a workflow can't be stopped from here: it only trips. Only what was on.
     */
    public static function stop(PDO $pdo, array $a): array
    {
        // the one engine's Incident Radius (02 §6; P5, P6): what the cause touches, never a person's switch
        $id = (int) $a['id']; $out = [];
        require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/ControlEngine.php';
        $why = mb_substr((string) $a['title'], 0, 200);
        try {
            foreach (ControlEngine::radius($pdo, $a) as [$what, $ref, $words]) {
                if ($what === 'pause') { [$k, $r] = explode(':', (string) $ref, 2); Automation::sysPause($pdo, $k, $r, $why, $id); }
                $out[] = [$what, (string) $ref, (string) $words];
            }
        } catch (Throwable $x) { error_log('control trip stop ' . (string) $a['akey'] . ': ' . $x->getMessage()); }
        return $out;
    }

    /**
     * An admin resolves a trip (executive and above; starting again needs Automation's permission, checked by the caller):
     * what fixed it is kept; what the trip stopped starts again only once its check passes. A check still red: the trip
     * stays open with that reason on its row. Returns [ok, words].
     */
    /**
     * A trip on a task's own code, pressed: lifts the pauses this trip set (the next run is its check), and says in words
     * what happened: started again, or what still holds it and which alarm's button lifts that. Never closes the trip.
     */
    private static function startForNextRun(PDO $pdo, array $a, int $id, string $who, string $note): string
    {
        require_once __DIR__ . '/Automation.php';
        $refs = [];
        foreach ((array) json_decode((string) ($a['stopped'] ?? ''), true) as $s) if (is_array($s) && ($s[0] ?? '') === 'pause') $refs[] = (string) ($s[1] ?? '');
        $lifted = Automation::sysResume($pdo, $id, 'Started again by ' . $who . ' for its next run');
        $at = gmdate('H:i') . ' UTC';
        // what still holds what this trip paused: another alarm's pause on the same workflow or task
        $still = [];
        foreach (Automation::pauses($pdo) as $key => $p) {
            if (!in_array($key, $refs, true) || (int) ($p['alarm_id'] ?? 0) === $id) continue;
            $o = ($p['alarm_id'] ?? null) !== null ? self::byId($pdo, (int) $p['alarm_id']) : null;
            $still[] = ($o ? '"' . (string) $o['title'] . '" (its own Check and start again lifts it)' : 'a pause of the system (' . $key . ')');
        }
        $what = $refs ? implode(', ', array_map(fn ($r) => str_starts_with($r, 'wf:') ? substr($r, 3) : $r, array_unique($refs))) : '';
        if ($still) return 'Started again at ' . $at . ' by ' . $who . ' for this trip, but ' . $what . ' is still paused by ' . implode(' and ', array_unique($still))
            . '. Press that one too; then ' . $what . ' runs, and its next run is this task\'s check: this closes as Fixed when that run does its work.';
        return ($lifted > 0 ? 'Started again at ' . $at . ' by ' . $who . ': ' . $what . ' runs again, and its next run is this task\'s check'
                            : 'Noted at ' . $at . ' by ' . $who . ': ' . $note . '. Its next run is its check')   // nothing of it was paused: as before
            . ': this closes as Fixed when that run does its work, or trips again if it fails.';
    }

    public static function resolve(PDO $pdo, int $id, int $uid, string $who, string $note): array
    {
        $a = self::byId($pdo, $id);
        if (!$a || $a['cleared_at'] !== null) return [false, 'It isn\'t open any more.'];
        $note = trim($note);
        if ($note === '') $note = 'Checked and started again';   // never a question to get past (Jason, 2026-11-19): the system checks, then starts
        $started = []; $fail = ''; $checked = false;
        // the one engine (P4, D3): no close without a real check of what failed, called as the failure did
        if (self::$engine) {
            require_once __DIR__ . '/ControlEngine.php';
            $comp = (string) (($a['component'] ?? '') ?: ControlEngine::componentOf($pdo, (string) $a['akey'], (string) $a['proc']));
            if (!str_starts_with($comp, 'wf:')) {
                $r = ControlEngine::check($pdo, $comp, $who);
                if ($r['pass'] === false) {
                    $why = 'Not resolved at ' . gmdate('H:i') . ' UTC by ' . $who . ': its check is still red: ' . mb_substr((string) $r['proof'], 0, 220) . ' Fix that first; then press Check and start again.';
                    $pdo->prepare('UPDATE control_alarms SET resolve_note = ? WHERE id = ?')->execute([mb_substr($why, 0, 400), $id]);
                    return [false, $why];
                }
                if ($r['pass'] === null && (str_starts_with($comp, 'task:') || str_starts_with($comp, 'job:'))) {
                    // its own code: no check can run before its next run (§2.7; R122 "It's fixed: try it again"); it closes when
                    // that run does its work, never on the press alone (NG). The pauses this trip set are lifted first, or that
                    // run never comes (W1, 2026-11-24: the trip paused wf:W1, the planner skips a paused workflow, and the press
                    // only noted it); one still held by another alarm is named, with its own button (never "acknowledged" alone)
                    $words = self::startForNextRun($pdo, $a, $id, $who, $note);
                    $pdo->prepare('UPDATE control_alarms SET resolve_note = ?, resolved_uid = ?, resolved_name = ?, ack_uid = COALESCE(ack_uid, ?), ack_name = COALESCE(ack_name, ?), ack_at = COALESCE(ack_at, UTC_TIMESTAMP()) WHERE id = ?')
                        ->execute([mb_substr($words, 0, 400), $uid ?: null, mb_substr($who, 0, 80) ?: null, $uid ?: null, mb_substr($who, 0, 80) ?: null, $id]);
                    self::log($pdo, 'resolve', (string) $a['proc'], 'Waiting for its next run: ' . (string) $a['title'], $who, $note);
                    return [true, $words];
                }
                if ($r['pass'] === true) { $note .= ' Its check passed: ' . mb_substr((string) $r['proof'], 0, 160); $checked = true; }
            }
        }
        // "Check and start again" checks first (Jason, 2026-11-19: the vector index read "Resolved" while Supabase still
        // wasn't ready, and its trip came straight back): a pre-flight trip whose line is still red stays open, saying why
        if (str_starts_with((string) $a['akey'], 'pf:')) {
            try { require_once __DIR__ . '/Preflight.php'; $lid = substr((string) $a['akey'], 3);
                if (isset(Preflight::lines($pdo)[$lid])) { $r = Preflight::run($pdo, $lid, $who, true);
                    if (($r['s'] ?? '') === 'bad') { $part = ''; foreach ((array) ($r['extra']['parts'] ?? []) as [$pt, $ps]) if ($ps === 'bad') { $part = (string) $pt; break; }
                        $fail = 'its check is still red: ' . mb_substr($part !== '' ? $part : (string) $r['said'], 0, 240) . ' Fix that first (Open its line shows how); then press Check and start again.'; } } } catch (Throwable $x) {}
        }
        foreach ((array) json_decode((string) ($a['stopped'] ?? ''), true) as [$what, $ref, $words]) {
            try {
                if ($what === 'proc') { require_once __DIR__ . '/ControlBoard.php'; ControlBoard::startOne($pdo, (string) $ref, $uid, $who); }
                elseif ($what === 'master') { require_once __DIR__ . '/Automation.php'; Automation::set($pdo, 'master', 'all', false, 'Trip resolved: ' . $note, $uid, $who); }
                elseif ($what === 'service') { require_once __DIR__ . '/Automation.php'; Automation::set($pdo, 'service', (string) $ref, false, 'Trip resolved: ' . $note, $uid, $who); }
                elseif ($what === 'pause') {   // its check first, as starting it does (ControlBoard::startOne); red: the pause stays
                    require_once __DIR__ . '/Automation.php';
                    [$pk, $pr] = explode(':', (string) $ref, 2) + ['', ''];
                    if (((Automation::pauses($pdo)[$pk . ':' . $pr] ?? [])['alarm_id'] ?? null) === null) continue;   // its pause already lifted (its check passed): nothing to start
                    $pid = $pk === 'wf' ? 'wf:' . $pr : '';
                    if ($pk === 'task') { try { require_once __DIR__ . '/ControlRegister.php'; $tw = (string) (ControlRegister::taskWorkflows($pdo)[$pr] ?? ''); if ($tw !== '') $pid = 'wf:' . $tw; } catch (Throwable $x) {} }
                    if ($pid !== '' && !$checked) {   // a workflow's line is a roll-up (02 §2.2): once what failed has passed its own check, it isn't asked again
                        require_once __DIR__ . '/Preflight.php';
                        if (isset(Preflight::lines($pdo)[$pid])) {
                            Preflight::refreshFor($pdo, $pid, time() + 40, 'the Control Board');
                            $r = Preflight::run($pdo, $pid, 'the Control Board');
                            if (($r['s'] ?? '') === 'bad') throw new RuntimeException('Not started: its check is red. ' . mb_substr((string) ($r['said'] ?? ''), 0, 300));
                        }
                    }
                    require_once __DIR__ . '/Automation.php'; Automation::sysResume($pdo, $id, 'Checked and started again by ' . $who);
                }
                else continue;   // a held issue's calls start again with its resolve, below
                $started[] = (string) $words;
            } catch (Throwable $x) { $fail = (string) $words . ': ' . $x->getMessage(); break; }
        }
        if ($fail !== '') {
            $why = 'Not resolved at ' . gmdate('H:i') . ' UTC by ' . $who . ': ' . $fail;
            $pdo->prepare('UPDATE control_alarms SET resolve_note = ? WHERE id = ?')->execute([mb_substr($why, 0, 400), $id]);
            return [false, $why . ($started ? ' (' . implode(', ', $started) . ' started again first.)' : '')];
        }
        // its cause, closed by the person too: an outside service's issue, or every Overpass server held
        try {
            require_once __DIR__ . '/ServiceHealth.php';
            if (preg_match('/^svc:issue:(\d+)$/', (string) $a['akey'], $m)) ServiceHealth::resolve($pdo, (int) $m[1], $uid, 'Resolved on the Control Board by ' . $who . ': ' . $note);
            if ($a['akey'] === 'svc:osm:held') foreach (ServiceHealth::openAll($pdo) as $i) if ($i['service'] === 'osm') ServiceHealth::resolve($pdo, (int) $i['id'], $uid, 'Resolved on the Control Board by ' . $who . ': ' . $note);
        } catch (Throwable $x) {}
        $how = 'Resolved by ' . $who . ': ' . $note . ($started ? ' Started again: ' . implode(', ', $started) . '.' : '');
        // a person's Resolve is also their acknowledgement: it leaves the queue for the logbook, with nothing more to press
        // (Jason, 2026-11-19: "I shouldn't have check and then acknowledge")
        $pdo->prepare('UPDATE control_alarms SET cleared_at = UTC_TIMESTAMP(), cleared_how = ?, resolved_uid = ?, resolved_name = ?, resolved_at = UTC_TIMESTAMP(), resolve_note = ?,
                ack_uid = COALESCE(ack_uid, ?), ack_name = COALESCE(ack_name, ?), ack_at = COALESCE(ack_at, UTC_TIMESTAMP()) WHERE id = ? AND cleared_at IS NULL')
            ->execute([mb_substr($how, 0, 255), $uid ?: null, mb_substr($who, 0, 80) ?: null, mb_substr($note, 0, 400), $uid ?: null, mb_substr($who, 0, 80) ?: null, $id]);
        self::log($pdo, 'resolve', (string) $a['proc'], 'Resolved: ' . (string) $a['title'], $who, $note . ($started ? ' Started again: ' . implode(', ', $started) . '.' : ''));
        if (self::$engine) { $pdo->prepare('UPDATE control_alarms SET next_try_at = NULL WHERE id = ?')->execute([$id]); try { require_once __DIR__ . '/ControlIncidents.php'; ControlIncidents::memberClosed($pdo, $id, $how); } catch (Throwable $x) {} }
        // a person's close updates the same thread on every channel (the one engine, P10; g4 F3: it never reached Pulse or
        // the email): "…: Fixed by <name>", quietly, as Recovered does
        if (!empty($a['tripped_at']) && ($c = self::byId($pdo, $id))) self::notifyOne($pdo, 'alarm.recovered', $c, self::noticeTitle($pdo, $c, 'recovered'), self::noticeBody($pdo, $c, 'recovered', 'Fixed by ' . $who . ': ' . $how), null, false);
        return [true, $how];
    }

    // ---- grouped alarms (decisions/0171 §8's note, Jason 2026-11-18: "Board and Pulse") ------------------------------

    /** The system an alarm's process belongs to (0171's four): wf/t and the services a workflow reads → engine. */
    public static function systemOf(string $proc): string
    {
        if (str_starts_with($proc, 'wf:') || str_starts_with($proc, 't:')) return 'engine';
        if (str_starts_with($proc, 'wk:')) return 'workers';
        if (str_starts_with($proc, 'svc:')) {   // a source a workflow's needs read (not the site's own needs)
            try { require_once __DIR__ . '/Preflight.php'; foreach (Preflight::NEEDS as $n) if ($n[0] !== 'siteneeds') foreach ($n[3] as $sr) if ($sr[0] === 'svc' && $sr[1] === substr($proc, 4)) return 'engine'; } catch (Throwable $x) {}
            return 'services';
        }
        return 'site';
    }

    /** Its kind's family: held (held, out, quiet), failing (crashed, retrying), slow, or check (a red check, a wrong count). */
    public static function familyOf(string $kind): string
    {
        return in_array($kind, ['held', 'limit', 'silent'], true) ? 'held' : (in_array($kind, ['crash', 'retry'], true) ? 'failing' : ($kind === 'slow' ? 'slow' : 'check'));
    }

    public const GROUP_WORDS = ['engine' => ['engine feed', 'engine feeds'], 'workers' => ['worker', 'workers'], 'services' => ['outside service', 'outside services'], 'site' => ['site part', 'site parts']];
    public const FAMILY_WORDS = ['held' => 'held', 'failing' => 'failing', 'slow' => 'slow', 'check' => 'with a red check'];

    /**
     * An open alarm's group key (02 row 127, §5.3: "groups and groupKey group by cause or incident"; "the board-system
     * grouping 'engine feeds'" is removed): its Incident Report (ir:<id>) when it has one, else the part that failed
     * (part:<component>). Two alarms share a group only when they share that cause.
     */
    public static function groupKey(array $a): string
    {
        if ((int) ($a['incident_id'] ?? 0) > 0) return 'ir:' . (int) $a['incident_id'];
        return 'part:' . (string) (($a['component'] ?? '') ?: $a['proc']);
    }

    /** A group's words, naming the cause (row 127: "<the cause in words>: 3 parts"; row 124: "IR-0001 · Correlated · 2 parts"). */
    public static function groupTitle(string $key, int $n, array $first = []): string
    {
        if (preg_match('/^ir:(\d+)$/', $key, $m)) { require_once __DIR__ . '/ControlIncidents.php'; return ControlIncidents::number((int) $m[1]) . ' · Correlated · ' . $n . ' parts'; }
        $name = ''; try { $name = $first ? self::noticeName(Database::connection(), $first) : ''; } catch (Throwable $x) {}
        return ($name !== '' ? $name : (string) preg_replace('/^part:/', '', $key)) . ': ' . $n . ' parts';
    }

    /**
     * The open alarms that share a group with at least one other: key => [title, level, ids (worst first, as listed)].
     * A group of one isn't a group: that alarm stays its own row.
     */
    public static function groups(array $alarms): array
    {
        $g = []; $first = []; $lv = [];
        foreach ($alarms as $a) if ($a['cleared_at'] === null) { $k = self::groupKey($a); $g[$k][] = (int) $a['id']; $first[$k] = $first[$k] ?? $a; $lv[$k] = max($lv[$k] ?? 0, self::RANK[(string) $a['level']] ?? 0); }
        $out = [];
        foreach ($g as $k => $ids) {
            if (count($ids) < 2) continue;
            $out[$k] = ['title' => self::groupTitle($k, count($ids), $first[$k]), 'level' => (string) array_search($lv[$k], self::RANK, true), 'ids' => $ids];
        }
        return $out;
    }

    /** The ids in a group ('alarms:<key>' or '<key>'), open now. */
    public static function groupMembers(PDO $pdo, string $key): array
    {
        $key = (string) preg_replace('/^alarms:/', '', $key);
        return self::groups(self::list($pdo, 300))[$key]['ids'] ?? [];
    }

    /**
     * Pulse's alarm items grouped (0171 §8's note): where two or more open alarm items share a group, one item
     * 'alarms:<key>' stands in their place, its 'children' the alarm items as they were (each keeps its own key, steps and
     * marks). Severity critical if any child is; the clock and escalation from its children.
     */
    public static function pulseGroups(PDO $pdo, array $items): array
    {
        $groups = self::groups(self::list($pdo, 300));
        if (!$groups) return $items;
        $of = []; foreach ($groups as $k => $g) foreach ($g['ids'] as $id) $of[$id] = $k;
        $kids = []; $rest = [];
        foreach ($items as $it) {
            $id = preg_match('/^alarm:(\d+)$/', (string) ($it['key'] ?? ''), $m) ? (int) $m[1] : 0;
            if ($id && isset($of[$id]) && ($it['state'] ?? '') !== 'done') $kids[$of[$id]][] = $it; else $rest[] = $it;
        }
        foreach ($kids as $k => $ch) {
            if (count($ch) < 2) { foreach ($ch as $c) $rest[] = $c; continue; }
            $first = $ch[0]; $whens = array_filter(array_map(fn ($c) => (string) $c['when'], $ch)); $dues = array_filter(array_map(fn ($c) => (string) ($c['sla_due'] ?? ''), $ch));
            $rest[] = ['key' => 'alarms:' . $k, 'source' => 'system', 'severity' => in_array('critical', array_column($ch, 'severity'), true) ? 'critical' : 'alert',
                'title' => (string) $groups[$k]['title'],
                'why' => implode('; ', array_map(fn ($c) => (string) $c['title'], $ch)) . '.', 'when' => $whens ? min($whens) : '', 'url' => '/admin/config/control#cb-alarms', 'action' => 'Open it',
                'about' => '', 'reply_to' => null, 'dept' => $first['dept'] ?? '', 'sla_due' => $dues ? min($dues) : null, 'state' => '', 'over' => (bool) array_filter(array_column($ch, 'over')),
                'mine' => false, 'who' => '', 'case_no' => 0, 'esc' => max(array_map(fn ($c) => (int) ($c['esc'] ?? 0), $ch)), 'esc_to_me' => (bool) array_filter(array_column($ch, 'esc_to_me')), 'children' => $ch];
        }
        return $rest;
    }

    /** Acknowledge every open alarm in a group (the board's group row). Returns how many. */
    public static function ackGroup(PDO $pdo, string $key, int $uid, string $who): int
    {
        $n = 0; foreach (self::groupMembers($pdo, $key) as $id) if (self::ack($pdo, $id, $uid, $who)) $n++;
        return $n;
    }

    /**
     * Once (decisions/0175 §7): switches a trip wrote into before 0175 go back to what a person last set, and each open
     * trip that switched something off places its pause instead. Safe to run every watch: once done, nothing matches.
     */
    public static function settle0175(PDO $pdo): array
    {
        $did = [];
        try {
            require_once __DIR__ . '/Automation.php';
            Automation::ensurePauses($pdo);
            foreach (Automation::putBackTripSwitches($pdo) as $p) $did[] = 'put back: ' . $p;
            foreach ($pdo->query("SELECT * FROM control_alarms WHERE cleared_at IS NULL AND level = 'trip' AND tripped_at IS NOT NULL AND stopped IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $st = (array) json_decode((string) $a['stopped'], true);
                if (!array_filter($st, fn ($x) => in_array($x[0] ?? '', ['proc', 'master', 'service'], true))) continue;
                $now = self::stop($pdo, $a);
                $pdo->prepare('UPDATE control_alarms SET stopped = ? WHERE id = ?')->execute([$now ? mb_substr(json_encode($now, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) : null, (int) $a['id']]);
                $did[] = 'trip #' . (int) $a['id'] . ' now pauses';
            }
        } catch (Throwable $x) { error_log('settle 0175: ' . $x->getMessage()); }
        return $did;
    }

    /** What a trip stopped, in words ('' none). */
    public static function stoppedWords(array $a): string
    {
        $st = json_decode((string) ($a['stopped'] ?? ''), true);
        return is_array($st) && $st ? implode(', ', array_map(fn ($x) => (string) ($x[2] ?? ''), $st)) : '';
    }

    /**
     * A module deleted (Retire, 02 row 116): each open alarm on it closes as "Closed: <part> was deleted by <name>; its history
     * is kept.", its holds lifted (its issues resolved), on every channel. NG allows this close without a check: nothing
     * becomes active. Returns how many.
     */
    public static function retired(PDO $pdo, string $svc, string $who): int
    {
        self::ensure($pdo); $n = 0;
        $st = $pdo->prepare("SELECT * FROM control_alarms WHERE cleared_at IS NULL AND (proc = ? OR component = ? OR component LIKE ?)"); $st->execute(['svc:' . $svc, 'svc:' . $svc, 'svc:' . $svc . ':%']);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $how = 'Closed: ' . self::noticeName($pdo, $a) . ' was deleted by ' . $who . '; its history is kept.';
            require_once __DIR__ . '/Automation.php'; Automation::sysResume($pdo, (int) $a['id'], $how);
            if (!self::close($pdo, (int) $a['id'], $how)) continue;
            $n++;
            if (preg_match('/^svc:issue:(\d+)$/', (string) $a['akey'], $m)) { try { require_once __DIR__ . '/ServiceHealth.php'; ServiceHealth::resolve($pdo, (int) $m[1], 0, $how); } catch (Throwable $x) {} }
            if (!empty($a['tripped_at']) && ($c = self::byId($pdo, (int) $a['id']))) self::notifyOne($pdo, 'alarm.recovered', $c, 'SYSTEM ALARM NOTICE: ' . self::noticeName($pdo, $c) . ': Closed', self::noticeBody($pdo, $c, 'recovered', $how), null, false);
        }
        return $n;
    }

    /**
     * An alarm raised in error (a defect, not a fault): closed as "Closed: raised in error: <why>", whatever it paused let go,
     * its thread told on every channel it reached, as Retire's close is (02 row 116), never as Recovered (nothing was down).
     * Returns whether one was open.
     */
    public static function raisedInError(PDO $pdo, string $key, string $why): bool
    {
        try {
            self::ensure($pdo);
            $a = self::openOf($pdo, $key); if (!$a) return false;
            $how = 'Closed: raised in error: ' . $why;
            require_once __DIR__ . '/Automation.php'; Automation::sysResume($pdo, (int) $a['id'], $how);
            if (!self::close($pdo, (int) $a['id'], $how)) return false;
            if (!empty($a['tripped_at']) && ($c = self::byId($pdo, (int) $a['id']))) self::notifyOne($pdo, 'alarm.recovered', $c, 'SYSTEM ALARM NOTICE: ' . self::noticeName($pdo, $c) . ': Closed', self::noticeBody($pdo, $c, 'recovered', $how), null, false);
            return true;
        } catch (Throwable $x) { error_log('control alarm raised in error ' . $key . ': ' . $x->getMessage()); return false; }
    }

    /**
     * Each open alarm's title as its raiser writes it today, from its state (principle 4, words follow the state; 02 §5.4,
     * the part's plain name): a held trip's raiser doesn't fire again, so an alarm raised before #258 kept its old words
     * ("index.commoncrawl.org: Its server failed (the crawler, on Railway)"). A service's issue from its issue row
     * (ServiceHealth::alarmTitle); a job's from Scheduler's name ("Lead scores (job) failed", "… is slow", "…: its run never
     * finished"); a task's from its component ("/api/crawl/loads keeps failing", "<task> failed in its own code (run #N)").
     * Its Pulse thread takes the same title in place: nothing is sent, nothing made unread. Returns how many changed.
     */
    /** A job's plain name, no "(job)": its row says it's a site job (the design review, 2026-11-23). */
    public static function jobPlain(string $job): string
    {
        try { require_once __DIR__ . '/Scheduler.php'; return (string) (Scheduler::JOBS[$job][0] ?? $job); } catch (Throwable $x) { return $job; }
    }

    /** One labelled time format for an alarm's words, as its Status draws it in UTC: "Nov 23, 3:08 PM UTC". */
    public static function atUtc(int $t): string { return gmdate('M j, g:i A', $t) . ' UTC'; }

    public static function redrawTitles(PDO $pdo): int
    {
        self::ensure($pdo); $n = 0;
        foreach ($pdo->query("SELECT * FROM control_alarms WHERE cleared_at IS NULL")->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $k = (string) $a['akey']; $old = (string) $a['title']; $new = '';
            if (preg_match('/^svc:issue:(\d+)$/', $k, $m)) {
                try { $st = $pdo->prepare('SELECT service, host, kind FROM service_issues WHERE id = ?'); $st->execute([(int) $m[1]]);
                      if ($r = $st->fetch(PDO::FETCH_ASSOC)) { require_once __DIR__ . '/ServiceHealth.php'; $new = ServiceHealth::alarmTitle((string) $r['service'], (string) $r['host'], (string) $r['kind']); } } catch (Throwable $x) {}
            } elseif (preg_match('/^pf:(svc:[a-z0-9_-]{1,40})$/', $k, $m) && preg_match('/^(.+?): (.+)$/s', $old, $tm)) {   // a check's raw transport error as a title (Library of Congress, 2026-11-23)
                require_once __DIR__ . '/ServiceHealth.php'; $new = ServiceHealth::lineTitle($m[1], $tm[1], $tm[2]);
            } elseif (preg_match('/^jobslow:(.+)$/', $k, $m)) $new = self::jobPlain($m[1]) . ' is slow';
            elseif (preg_match('/^job:(.+):stuck$/', $k, $m)) $new = self::jobPlain($m[1]) . ': its run never finished';
            elseif (preg_match('/^joblate:(.+)$/', $k, $m)) $new = self::jobPlain($m[1]) . ' is late';
            elseif (preg_match('/^job:([^:]+)$/', $k, $m)) { $nm = self::jobName($m[1]); if (!str_starts_with($old, $nm)) $new = $nm . ' failed'; }
            elseif (preg_match('/^task:failed:(.+)$/', $k, $m) && preg_match('/^W\d+: its task /', $old)) {
                $c = (string) ($a['component'] ?? '');
                $new = str_starts_with($c, 'ep:') ? '/api/' . substr($c, 3) . (preg_match('/timed? ?out|timeout/i', (string) $a['reading']) ? ' is slow' : ' keeps failing')
                     : $m[1] . ' failed in its own code' . (preg_match('/\(run #\d+\)/', $old, $rm) ? ' ' . $rm[0] : '');
            }
            if ($new === '' || $new === $old) continue;
            $pdo->prepare('UPDATE control_alarms SET title = ? WHERE id = ?')->execute([mb_substr($new, 0, 255), (int) $a['id']]);
            $a['title'] = $new; $n++;
            try { $pdo->prepare("UPDATE comms_messages SET title = ? WHERE thing = ? AND kind LIKE 'alarm.%'")->execute([mb_substr(self::noticeTitle($pdo, $a), 0, 200), 'alarm:' . (int) $a['id']]); } catch (Throwable $x) {}
        }
        return $n;
    }

    /** Undo a Resolve (within a day): the trip is open again and what it had stopped is stopped again. */
    public static function unresolve(PDO $pdo, int $id, int $uid, string $who): array
    {
        $a = self::byId($pdo, $id);
        if (!$a || $a['resolved_at'] === null || $a['cleared_at'] === null) return [false, 'It wasn\'t resolved, so there\'s nothing to undo.'];
        if (strtotime($a['resolved_at'] . ' UTC') < time() - 86400) return [false, 'A Resolve can be undone for a day; this one is older. If the problem is back, its alarm opens again by itself.'];
        if (self::openOf($pdo, (string) $a['akey'])) return [false, 'The same problem is already open again as a new alarm.'];
        $pdo->prepare('UPDATE control_alarms SET cleared_at = NULL, cleared_how = NULL, resolved_uid = NULL, resolved_name = NULL, resolved_at = NULL, resolve_note = ? WHERE id = ?')
            ->execute([mb_substr('Resolve undone by ' . $who . ' at ' . gmdate('H:i') . ' UTC.', 0, 400), $id]);
        $stopped = self::stop($pdo, self::byId($pdo, $id));
        $pdo->prepare('UPDATE control_alarms SET stopped = ? WHERE id = ?')->execute([$stopped ? mb_substr(json_encode($stopped, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) : null, $id]);
        self::log($pdo, 'resolve', (string) $a['proc'], 'Resolve undone: ' . (string) $a['title'], $who, $stopped ? implode(', ', array_column($stopped, 2)) . ' stopped again' : '');
        // its thread raised again on every channel (02 row 98, P10): "Reopened by <name>: the trip is open again; <scope> waits
        // again." Pulse unread, an email reply, one push
        if ($c = self::byId($pdo, $id)) self::notifyOne($pdo, 'alarm.trip', $c, self::noticeTitle($pdo, $c, 'trip'), self::noticeBody($pdo, $c, 'trip', 'Reopened by ' . $who . ': the trip is open again' . ($stopped ? '; ' . implode(', ', array_column($stopped, 2)) . ' waits again' : '') . '.'), 'alarm:' . $id);
        return [true, 'Undone: the trip is open again' . ($stopped ? ', and ' . implode(', ', array_column($stopped, 2)) . ' stopped again.' : '.')];
    }

    /** Clears every open alarm whose key starts with $prefix except those in $keep (the readings still bad). */
    public static function clearOthers(PDO $pdo, string $prefix, array $keep, string $how): int
    {
        $n = 0;
        try {
            self::ensure($pdo);
            $st = $pdo->prepare("SELECT akey FROM control_alarms WHERE cleared_at IS NULL AND akey LIKE ?"); $st->execute([addcslashes($prefix, '%_') . '%']);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $k) if (!in_array($k, $keep, true) && self::clear($pdo, (string) $k, $how)) $n++;
        } catch (Throwable $x) {}
        return $n;
    }

    /** Acknowledged by a person: stops the repeat email; it stays open until its reading is good. */
    public static function ack(PDO $pdo, int $id, int $uid, string $who): bool
    {
        self::ensure($pdo);
        $st = $pdo->prepare('UPDATE control_alarms SET ack_uid = ?, ack_name = ?, ack_at = UTC_TIMESTAMP() WHERE id = ? AND ack_at IS NULL');
        $st->execute([$uid ?: null, mb_substr($who, 0, 80) ?: null, $id]);
        $ok = $st->rowCount() > 0;
        if ($ok && ($a = self::byId($pdo, $id))) self::log($pdo, 'ack', (string) $a['proc'], 'Acknowledged: ' . (string) $a['title'], $who);
        return $ok;
    }

    /** Undo an acknowledgement (it emails again only if it's a new problem). */
    public static function unack(PDO $pdo, int $id): bool
    {
        self::ensure($pdo);
        $st = $pdo->prepare('UPDATE control_alarms SET ack_uid = NULL, ack_name = NULL, ack_at = NULL WHERE id = ? AND ack_at IS NOT NULL'); $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /**
     * The list: open alarms, worst first, then the trips that cleared before anyone saw them (kept 24 hours, 0171 §7).
     * Each row as stored, with 'fix' decoded.
     */
    public static function list(PDO $pdo, int $n = 100): array
    {
        try {
            self::ensure($pdo);
            $rows = $pdo->query("SELECT * FROM control_alarms WHERE cleared_at IS NULL OR cleared_at >= UTC_TIMESTAMP() - INTERVAL 48 HOUR
                                 ORDER BY cleared_at IS NOT NULL, FIELD(level, 'trip', 'warning', 'notice'), last_at DESC LIMIT " . max(1, min(500, $n)))->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) $r['fix'] = $r['fix'] !== '' ? (json_decode((string) $r['fix'], true) ?: []) : [];
            return $rows;
        } catch (Throwable $x) { return []; }
    }

    /** For the light: open trips and warnings, and the trips that cleared unseen. */
    public static function counts(PDO $pdo): array
    {
        $z = ['trip' => 0, 'warning' => 0, 'notice' => 0, 'unseen' => 0];
        try {
            self::ensure($pdo);
            // what's open and not acknowledged: the same alarms the board's Open filter shows, so the top bar, the panel and the
            // filter give one number (Jason, 2026-11-19: "alarms everywhere": 15, 23 and 5 for the same list)
            // an open trip counts whether or not someone has seen it (the one engine; g4 F1: acknowledging turned the light
            // green while the trip still held its parts); warnings and notices count until seen
            foreach ($pdo->query("SELECT level, COUNT(*) FROM control_alarms WHERE cleared_at IS NULL AND (ack_at IS NULL OR level = 'trip') GROUP BY level")->fetchAll(PDO::FETCH_KEY_PAIR) as $l => $c) if (isset($z[$l])) $z[$l] = (int) $c;
            $z['unseen'] = (int) $pdo->query("SELECT COUNT(*) FROM control_alarms WHERE level = 'trip' AND ack_at IS NULL AND cleared_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR")->fetchColumn();
        } catch (Throwable $x) {}
        return $z;
    }

    /** A process's open alarms (for its row on the board). */
    public static function ofProc(PDO $pdo, string $proc): array
    {
        try { self::ensure($pdo); $st = $pdo->prepare('SELECT * FROM control_alarms WHERE proc = ? AND cleared_at IS NULL ORDER BY FIELD(level, \'trip\', \'warning\', \'notice\'), last_at DESC'); $st->execute([$proc]); return $st->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $x) { return []; }
    }

    /**
     * A critical part's first failure (0171 §5's note): a trip at once, to Pulse, the Founder's email and the push, while
     * the system keeps trying; nothing is stopped yet. Recovered closes it; retries running out escalate it (escalated()).
     */
    private static function trippedEarly(PDO $pdo, int $id, string $duty): void
    {
        try {
            $pdo->prepare('UPDATE control_alarms SET tripped_at = UTC_TIMESTAMP(), retrying_at = UTC_TIMESTAMP() WHERE id = ? AND tripped_at IS NULL')->execute([$id]);
            self::incident($pdo, $id, []);
        } catch (Throwable $x) { error_log('control early trip ' . $id . ': ' . $x->getMessage()); }
        self::mail($pdo, $id);
    }

    /**
     * Its tries ran out: the Escalation Notice ("Retries unsuccessful, now admin required"), to Pulse, email and push; it's
     * an ordinary trip now and what it holds stays paused; it still closes as Recovered when its check passes (decisions/0175's
     * note, "an escalated trip closes as Recovered"; ESCALATED_RECOVERS), or by Check and start again.
     */
    private static function escalated(PDO $pdo, int $id, string $duty): void
    {
        try {
            $pdo->prepare('UPDATE control_alarms SET retrying_at = NULL WHERE id = ?')->execute([$id]);
            $a = self::byId($pdo, $id); if (!$a) return;
            $stopped = self::stop($pdo, $a);
            $pdo->prepare('UPDATE control_alarms SET stopped = ? WHERE id = ?')->execute([$stopped ? mb_substr(json_encode($stopped, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) : null, $id]);
            $part = self::partName($pdo, (string) $a['proc']); $dn = $duty !== '' ? ControlRegister::DUTIES[$duty][0] : 'critical';
            $title = 'Escalation Notice: retries unsuccessful, now admin required: ' . $part . ', ' . $dn;
            self::log($pdo, 'alarm', (string) $a['proc'], $title, '', (string) $a['title']);
            // the same thread as the trip, updated in place, its level's push once (decisions/0176 §1, §4 step 4b); the
            // trip's Pulse item shows it, so no second row
            self::notifyOne($pdo, 'alarm.escalated', $a, self::noticeTitle($pdo, $a, 'escalated'), self::noticeBody($pdo, $a, 'escalated', $title . '. It closes as Recovered when its check passes'), 'alarm:' . $id, false);
        } catch (Throwable $x) { error_log('control escalated ' . $id . ': ' . $x->getMessage()); }
    }

    /** A critical part the system fixed: the trip closes itself, with a notice that needs nothing pressed (0171 §5's note). */
    private static function recovered(PDO $pdo, array $o, string $how): bool
    {
        $mins = max(1, (int) round((time() - (int) strtotime((string) $o['first_at'] . ' UTC')) / 60));
        $part = self::partName($pdo, (string) $o['proc']);
        $words = !empty($o['resolved_name']) ? 'Fixed by ' . (string) $o['resolved_name'] . ': ' . $part . ' was down ' . $mins . ' min; its check passed.'
                                             : 'Recovered: ' . $part . ' was down ' . $mins . ' min, the system fixed it.';
        $ok = self::close($pdo, (int) $o['id'], mb_substr($words . ' ' . $how, 0, 255));
        if ($ok) {
            try { $pdo->prepare('UPDATE control_alarms SET retrying_at = NULL, ack_at = COALESCE(ack_at, UTC_TIMESTAMP()), ack_name = COALESCE(ack_name, \'the system (recovered)\') WHERE id = ?')->execute([(int) $o['id']]); } catch (Throwable $x) {}
            // the thread closes quietly (0176 §4 step 4a): the same message, now the finished story, a Notice with nothing to press
            self::notifyOne($pdo, 'alarm.recovered', $o, self::noticeTitle($pdo, $o, 'recovered'), self::noticeBody($pdo, $o, 'recovered', $words . ' ' . $how), null, false);
        }
        return $ok;
    }

    /** A part's name as the board shows it. */
    public static function partName(PDO $pdo, string $proc): string
    {
        try { require_once __DIR__ . '/ControlRegister.php'; $p = ControlRegister::processes($pdo)[$proc] ?? null; if ($p) return (string) $p[1]; } catch (Throwable $x) {}
        // a part the board has no row for still reads by its plain name (02 §5.4: "never job:, wf: or svc:")
        if (str_starts_with($proc, 'job:')) return self::jobName(substr($proc, 4));
        if (str_starts_with($proc, 'ep:')) return '/api/' . substr($proc, 3) . (str_contains(substr($proc, 3), '/') ? '' : '/');   // our endpoint by its path
        if (str_starts_with($proc, 'wf:')) { try { require_once __DIR__ . '/Automation.php'; $n = (string) (Automation::WORKFLOWS[substr($proc, 3)][0] ?? ''); if ($n !== '') return substr($proc, 3) . ' ' . $n; } catch (Throwable $x) {} }
        if (str_starts_with($proc, 'svc:')) { try { require_once __DIR__ . '/OutsideServices.php'; $co = (string) (OutsideServices::COMPANIES[substr($proc, 4)][0] ?? ''); if ($co !== '') return $co; } catch (Throwable $x) {} }
        return $proc;
    }

    /** One message to the Founders through the dispatcher (Pulse, email, push as their channels say). Never throws. */
    private static function notifyOne(PDO $pdo, string $kind, array $a, string $title, string $body, ?string $pulseRef, bool $status = true): void
    {
        try {
            require_once __DIR__ . '/Notify.php'; require_once __DIR__ . '/CommsSetup.php';
            if (!isset(Notify::KINDS[$kind])) return;
            $to = CommsSetup::founders($pdo);
            if (!$to) {
                // a trip always reaches someone (the one engine, P11; g4 F4): with nobody to receive it, the trip says so and the
                // dispatcher's own fault is raised, on the board, where it's seen
                if (in_array($kind, ['alarm.trip', 'alarm.escalated'], true)) {
                    $pdo->prepare('UPDATE control_alarms SET reading = ? WHERE id = ?')->execute([mb_substr('Reached no one: no founder account to receive it. ' . (string) $a['reading'], 0, 255), (int) $a['id']]);
                    if ((string) $a['akey'] !== 'dispatch:nobody') self::raise($pdo, 'dispatch:nobody', 'site:pulse', 'trip', 'held', 'The dispatcher reached no one with a trip',
                        'A trip (' . mb_substr((string) $a['title'], 0, 120) . ') had nobody to receive it: no founder account. It stands on the board.',
                        'Give a staff login the Founder level in Staff levels, so trips reach someone by Pulse, email and push.', 'no founder account',
                        // where a founder is named (manifests.php: backoffice → levels, /admin/staff-levels.php; CommsSetup::founders reads it)
                        [['Staff levels (who receives trips)', 'peek', '/admin/backoffice/levels']], true, false, 'site:pulse');
                }
                return;
            }
            // one thread per alarm (decisions/0176 §1): the trip, its Escalation Notice and Recovered are one message, updated in place
            $about = ['thing' => 'alarm:' . (int) $a['id'], 'url' => $kind === 'alarm.recovered' ? '/admin/config/control#cb-alarm-' . (int) $a['id'] : self::pulseUrl($pdo, $a), 'from' => ['system', null, 'Control Board']];
            if ($pulseRef !== null) $about['pulse_ref'] = $pulseRef;   // the trip's own Pulse item shows it: no second row
            Notify::send($pdo, $kind, ['staff' => $to], $about, ['title' => mb_substr($title, 0, 200), 'body' => ($status ? self::statusLine($pdo, (int) $a['id']) : '') . trim($body)]);
        } catch (Throwable $x) { error_log('control notify ' . $kind . ': ' . $x->getMessage()); }
    }

    /**
     * The email (0171 §7, through 0172's one dispatcher): a trip at once; a warning unacknowledged 24 hours. Sent only
     * when the dispatcher knows the kind (Notify::KINDS has alarm.trip / alarm.warning); until then nothing is sent and
     * the alarm still stands on the board. Marks it mailed so it goes once.
     */
    private static function mail(PDO $pdo, int $id): void
    {
        try {
            $st = $pdo->prepare('SELECT * FROM control_alarms WHERE id = ?'); $st->execute([$id]); $a = $st->fetch(PDO::FETCH_ASSOC);
            if (!$a || $a['mailed_at'] !== null) return;
            if ($a['level'] !== 'trip') return;   // only a trip reaches a person (0171 §7's note, 2026-11-19)
            $kind = 'alarm.trip';
            require_once __DIR__ . '/Notify.php'; require_once __DIR__ . '/CommsSetup.php';
            if (!isset(Notify::KINDS[$kind])) return;
            $to = CommsSetup::founders($pdo);
            if (!$to) { self::notifyOne($pdo, $kind, $a, '', '', null); return; }   // P11: nobody to receive it is the dispatcher's own fault
            // one thread per incident (02 row 124, S-F124; the session manager's ruling, 2026-11-22): a trip that joined another's
            // Incident Report updates the incident's Pulse item and replies in its email thread, under the first trip's subject;
            // no new subject and no push (decisions/0176 §1: one thread, updated in place)
            if (self::$engine && (int) ($a['incident_id'] ?? 0) > 0) {
                $mt = $pdo->prepare('SELECT id FROM control_alarms WHERE incident_id = ? AND id <> ? AND mailed_at IS NOT NULL ORDER BY id LIMIT 1'); $mt->execute([(int) $a['incident_id'], $id]);
                if ($first = (int) $mt->fetchColumn()) {
                    require_once __DIR__ . '/ControlIncidents.php';
                    $ir = (int) $a['incident_id'];
                    try { ControlIncidents::note($pdo, $ir, 'Correlated: ' . self::noticeName($pdo, $a) . ' joined (' . ControlIncidents::tripId($id) . '), on the thread of ' . ControlIncidents::tripId($first) . '.'); } catch (Throwable $x) {}
                    $pdo->prepare('UPDATE control_alarms SET mailed_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
                    $fa = self::byId($pdo, $first);
                    if ($fa) {
                        $ms = $pdo->prepare('SELECT * FROM control_alarms WHERE incident_id = ? ORDER BY id'); $ms->execute([$ir]);
                        $parts = []; foreach ($ms->fetchAll(PDO::FETCH_ASSOC) as $m) $parts[] = self::noticeName($pdo, $m);
                        $parts = array_values(array_unique($parts));
                        $line = ControlIncidents::number($ir) . ' · Correlated · ' . count($parts) . ' parts: ' . implode(', ', $parts) . ' · ' . self::actionOf($a, 'trip');
                        $body = $line . "\n" . self::noticeName($pdo, $a) . ' joined this incident (same host and kind within ' . ControlIncidents::WINDOW_MIN . ' minutes).' . "\n\n"
                              . self::noticeBody($pdo, $a, 'trip') . ((string) $a['reading'] !== '' ? 'Reading: ' . $a['reading'] : '');
                        Notify::send($pdo, $kind, ['staff' => $to], ['thing' => 'alarm:' . $first, 'url' => self::pulseUrl($pdo, $fa), 'from' => ['system', null, 'Control Board'],
                            'pulse_ref' => 'alarm:' . $first, 'no_push' => true],
                            ['title' => self::noticeTitle($pdo, $fa, 'trip'), 'body' => trim($body)]);
                    }
                    return;
                }
            }
            // the alarm notice (decisions/0176's note, 2026-11-20): subject "SYSTEM ALARM NOTICE: <item>: Paused by the system"
            $body = self::noticeBody($pdo, $a, 'trip') . ($a['reading'] !== '' ? 'Reading: ' . $a['reading'] : '');
            Notify::send($pdo, $kind, ['staff' => $to], ['thing' => 'alarm:' . $id, 'url' => self::pulseUrl($pdo, $a), 'from' => ['system', null, 'Control Board'],
                'pulse_ref' => 'alarm:' . $id],   // Pulse already shows the alarm (its item, or its group's): the email goes, no second row (0171 §8 "one thing")
                ['title' => self::noticeTitle($pdo, $a, 'trip'), 'body' => trim($body)]);
            $pdo->prepare('UPDATE control_alarms SET mailed_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
        } catch (Throwable $x) { error_log('control alarm mail ' . $id . ': ' . $x->getMessage()); }
    }

    // ---- the thread (decisions/0176 §1–2): the facts and buttons; Thread draws them on every surface ----------------

    /** The alarm notice's action, from the thread's state (decisions/0176's note, Jason 2026-11-20: "SYSTEM ALARM NOTICE:
     *  (Item or Group): (Action)"): Paused by the system, then Retries unsuccessful, now admin required, then Recovered. */
    public const ACTIONS = ['trip' => 'Paused by the system', 'escalated' => 'Retries unsuccessful, now admin required', 'recovered' => 'Recovered'];

    /** "SYSTEM ALARM NOTICE: <item>: <action>", the email subject and Pulse row (0176's note, 2026-11-20). */
    public static function noticeTitle(PDO $pdo, array $a, string $state = ''): string
    {
        if ($state === '') $state = $a['cleared_at'] !== null ? 'recovered' : (self::escalatedAt($pdo, $a) !== '' ? 'escalated' : 'trip');
        return 'SYSTEM ALARM NOTICE: ' . self::noticeName($pdo, $a) . ': ' . self::actionOf($a, $state);
    }

    /**
     * The thread's plain name (02 §4.8 rows 2, 21, 47, 62, 69; §5.4: "the part's plain name … never job:, wf: or svc:"): the
     * component's own name from its manifest (Common Crawl's index), our endpoint's path, "The crawler is down" (row 47),
     * a job's name from Scheduler::JOBS with "(job)", else the part's name on the board.
     */
    public static function noticeName(PDO $pdo, array $a): string
    {
        require_once __DIR__ . '/ControlEngine.php';
        $comp = (string) (($a['component'] ?? '') ?: ControlEngine::componentOf($pdo, (string) $a['akey'], (string) $a['proc']));
        if (($n = ControlEngine::partName($comp)) !== '') return $n;
        if (str_starts_with($comp, 'ep:')) return '/api/' . substr($comp, 3);
        if ($comp === 'wk:crawler') return 'The crawler is down';
        if (str_starts_with($comp, 'job:')) return self::jobName(substr($comp, 4));
        if (str_starts_with($comp, 'svc:')) { try { require_once __DIR__ . '/OutsideServices.php'; $co = (string) (OutsideServices::COMPANIES[(string) preg_replace('/:.*$/', '', substr($comp, 4))][0] ?? ''); if ($co !== '') return $co; } catch (Throwable $x) {} }
        return self::partName($pdo, (string) $a['proc']);
    }

    /** A job's plain name (02 §5.4: a job's from Scheduler::JOBS): "Lead scores (job)"; its id only when it has no entry. */
    public static function jobName(string $job): string
    {
        try { require_once __DIR__ . '/Scheduler.php'; $l = (string) (Scheduler::JOBS[$job][0] ?? ''); if ($l !== '') return $l . ' (job)'; } catch (Throwable $x) {}
        return $job . ' (job)';
    }

    /** The action as the state is now: "Paused by the system" only when it holds something (the one engine, §5.4). */
    public static function actionOf(array $a, string $state): string
    {
        if ($state === 'recovered' && !empty($a['resolved_name']) && $a['cleared_at'] !== null) return 'Fixed by ' . (string) $a['resolved_name'];
        if ($state === 'trip' && self::stoppedWords($a) === '') {
            $c = (string) ($a['component'] ?? '');
            return empty($a['retrying_at']) && (str_starts_with($c, 'task:') || str_starts_with($c, 'job:')) ? 'Tripped: nothing is held' : 'Down: the system is still trying';
        }
        return (string) (self::ACTIONS[$state] ?? self::ACTIONS['trip']);
    }

    /** The notice's body on every channel: the action and its status first (the push shows that line), then what's going on,
     *  what it means and what to do; the link is added by the dispatcher (0176's note, 2026-11-20). */
    private static function noticeBody(PDO $pdo, array $a, string $state, string $extra = ''): string
    {
        $st = '';
        if ($state === 'recovered') { $st = $extra; $extra = ''; }   // its words are how it closed ("<part> was down 12 min, the system fixed it")
        else { try { require_once __DIR__ . '/Thread.php'; $st = Thread::push(self::facts($pdo, $a))['body']; } catch (Throwable $x) {} }
        // the action once: a status that already starts with it ("Paused by the system (a trip) since …") isn't prefixed again
        $st = (string) preg_replace(['/^Recovered: /', '/^Escalation Notice [^:]*(?::\d\d[^:]*)*: retries unsuccessful, an admin is needed\.\s*/u'], '', $st);
        $act = $state === '' ? '' : self::actionOf($a, $state);
        if ($state === 'recovered' && str_starts_with($st, 'Closed:')) $act = '';   // a close that isn't a recovery (Retire, raised in error) says only how it closed
        if ($state === 'trip' && $st !== '' && preg_match('/^(Paused by the system|Down: |Tripped)/', $st)) $act = '';   // its status already says it
        $first = $st === '' ? $act . '.' : ($act === '' || stripos($st, $act) === 0 ? $st : $act . ': ' . $st);
        $portal = $state === 'recovered' ? '' : self::portalLine($pdo, $a);
        return $first . "\n"
            . 'What\'s going on: ' . (string) $a['title'] . ($extra !== '' ? '. ' . $extra : '') . "\n"
            . ($portal !== '' ? $portal . "\n" : '')
            . ((string) $a['means'] !== '' ? 'What it means: ' . (string) $a['means'] . "\n" : '')
            . ((string) $a['todo'] !== '' && $state !== 'recovered' ? 'What to do: ' . (string) $a['todo'] . "\n" : ($state === 'recovered' ? "What to do: nothing; this is a notice.\n" : ''));
    }

    /**
     * The provider's page that matches the fix (02 §0 PL, group G rows 128-135): a quota's billing, a key's API keys page, a
     * ban's console; from the manifest's portal_links, else its company links mapped by label (02 §8), never borrowed from
     * another type. "<Type> at <Provider>: <url>", or "No <type> page registered for <Provider> yet". '' for no outside cause.
     */
    public static function portalLine(PDO $pdo, array $a): string
    {
        $fix = self::fixOf($pdo, $a); $c = (string) ($fix['cause'] ?? '');
        $type = ['quota' => 'billing', 'key' => 'keys', 'blocked' => 'console'][$c] ?? '';
        if ($type === '' || !preg_match('/^svc:([a-z0-9_]+)/', (string) ($a['component'] ?? $a['proc']), $m)) {
            if ($type === '' || !preg_match('/^svc:([a-z0-9_]+)/', (string) $a['proc'], $m)) return '';
        }
        require_once __DIR__ . '/ControlEngine.php';
        $d = (array) (ControlEngine::defs()[$m[1]] ?? []); $co = (string) ($d['company'][0] ?? $m[1]); $prov = trim((string) preg_replace('/\s*\(.*\)$/', '', $co));
        $words = ['billing' => 'Billing', 'keys' => 'API keys', 'console' => 'Console'][$type];
        $url = (string) ($d['portal_links'][$type][1] ?? '');
        if ($url === '') {
            $want = ['billing' => ['billing', 'limits', 'usage'], 'keys' => ['keys', 'key', 'credentials', 'api settings'], 'console' => ['dashboard', 'console']][$type];
            foreach ($want as $w) { foreach ((array) ($d['company'][3] ?? []) as $ln) if (str_contains(mb_strtolower((string) ($ln[0] ?? '')), $w) && str_starts_with((string) ($ln[1] ?? ''), 'http')) { $url = (string) $ln[1]; break 2; } }
        }
        return $url !== '' ? $words . ' at ' . $prov . ': ' . $url : 'No ' . ['billing' => 'billing', 'keys' => 'keys', 'console' => 'console'][$type] . ' page registered for ' . $prov . ' yet.';
    }

    /** The thread's status as its words' first line, read now (the push shows it: decisions/0176 §2). */
    private static function statusLine(PDO $pdo, int $id): string
    {
        try { require_once __DIR__ . '/Thread.php'; $a = self::byId($pdo, $id); if (!$a) return '';
              $p = Thread::push(self::facts($pdo, $a)); return $p['body'] !== '' ? 'Status: ' . $p['body'] . "\n" : ''; }
        catch (Throwable $x) { return ''; }
    }

    /** A time for the thread's words: Thread draws it labelled, in the viewer's zone on a page, in UTC in plain text. */
    private static function at(?string $utc): string { return $utc ? '{t:' . str_replace(' ', 'T', (string) $utc) . 'Z}' : ''; }

    /**
     * An alarm's facts in the one shape (0176 §2), read from its row as it stands now (0176 §7): Title; Status (0175 §2–3:
     * Paused by the system (a trip), or trying again, with since when; acknowledged; good again; closed and how); What it
     * affects (what its trip paused, and for an outside service, each need that uses it with its other sources or "none",
     * from Preflight::NEEDS); What the system is doing (its words, which say how it's trying); What to do; Details (its
     * reading, kind and counts). No buttons here: each surface adds the ones it can press.
     */
    public static function facts(PDO $pdo, array $a): array
    {
        $cl = $a['cleared_at'] !== null; $lv = (string) $a['level']; $isTrip = $lv === 'trip' && !empty($a['tripped_at']);
        $sw = self::stoppedWords($a);
        if ($cl) $status = 'Closed ' . self::at((string) $a['cleared_at']) . ': ' . (string) $a['cleared_how'];
        elseif ($isTrip && !empty($a['retrying_at'])) $status = 'Tripped at once ' . self::at((string) $a['tripped_at']) . ': a critical part. The system is still trying; if that fixes it, this closes as Recovered.';
        elseif ($isTrip) {
            $esc = self::escalatedAt($pdo, $a);
            $status = ($esc !== '' ? 'Escalation Notice ' . self::at($esc) . ': retries unsuccessful, an admin is needed. ' : '')
                    . ($sw !== '' ? 'Paused by the system (a trip) since ' . self::at((string) $a['tripped_at']) . '. ' . (self::ESCALATED_RECOVERS || $esc === '' ? 'It closes as Recovered when its check passes.' : '')
                                  : 'Tripped since ' . self::at((string) $a['tripped_at']) . '.');
        } else $status = self::LEVELS[$lv] . ' since ' . self::at((string) $a['first_at']) . ', seen ' . (int) $a['n_times'] . ' time' . ((int) $a['n_times'] === 1 ? '' : 's') . '.';
        // words from state (the one engine, P9; 02 §0a ruling 5): what's held, the tries and the next one
        $doing = (string) $a['means'];
        if (!$cl && self::$engine) {
            require_once __DIR__ . '/ControlEngine.php';
            $es = ControlEngine::status($a, $sw);
            if ($es !== '') { $esc0 = $isTrip ? self::escalatedAt($pdo, $a) : ''; $status = ($esc0 !== '' ? 'Escalation Notice ' . self::at($esc0) . ': retries unsuccessful, an admin is needed. ' : '') . $es; }
            $held = []; foreach ((array) json_decode((string) ($a['stopped'] ?? ''), true) as $st) if (is_array($st) && isset($st[2])) $held[] = (string) $st[2];
            $ed = ControlEngine::doing($pdo, $a, $held);
            if ($ed !== '') $doing = $ed;
        }
        if (!$cl && $a['ack_at'] !== null) $status .= ' Acknowledged by ' . (string) ($a['ack_name'] ?? 'staff') . '.';
        if (!$cl && $isTrip && !empty($a['good_at'])) $status .= ' Its reading is good again since ' . self::at((string) $a['good_at']) . ': ' . (string) $a['good_how'];
        if (!$cl && str_starts_with((string) ($a['resolve_note'] ?? ''), 'Not resolved')) $status .= ' ' . (string) $a['resolve_note'];
        $det = [];
        if ((string) $a['reading'] !== '') $det[] = ['Reading', (string) $a['reading']];
        $det[] = ['Kind', self::KINDS[(string) $a['kind']] ?? (string) $a['kind']];
        $det[] = ['Seen', (int) $a['n_times'] . ' time' . ((int) $a['n_times'] === 1 ? '' : 's') . ', first ' . self::at((string) $a['first_at']) . ', last ' . self::at((string) $a['last_at'])];
        $fix = $cl ? null : self::fixOf($pdo, $a); $theirs = !$cl && ($fix['cause'] ?? '') === 'theirs';
        require_once __DIR__ . '/Thread.php';
        return ['title' => (string) $a['title'], 'level' => $cl ? 'closed' : $lv, 'pill' => $cl ? 'Cleared' : (self::LEVELS[$lv] ?? $lv),
        // 'holds': only a trip holds its part; a warning holds nothing (words true to state; the design review, 2026-11-23)
                'status' => trim($status), 'affects' => self::affects($pdo, $a), 'holds' => $a['level'] === 'trip' && !empty($a['tripped_at']) ? self::partName($pdo, (string) $a['proc']) : '', 'doing' => ($theirs && $doing !== '' ? Thread::HANDLING . ': ' : '') . $doing, 'todo' => (string) $a['todo'], 'details' => $det,
                'fix' => $theirs ? null : $fix, 'theirs' => $theirs];
    }

    /**
     * Its cause and its one spot (decisions/0176 §5, A5), from what the alarm already knows: an outside service's issue, from
     * the issue's kind (ServiceHealth::facts: a refused key, a used-up quota, a refusal; else 'theirs'); a cap out of budget
     * or near it, the card's Caps and budget (the community budget, Costs); a site that blocked W0, its line among the sites
     * that blocked us, with Reactivate. Anything else has no mapped cause: its row keeps its component button (0175 R2a).
     * ['cause', 'url'] or null.
     */
    public static function fixOf(PDO $pdo, array $a): ?array
    {
        $k = (string) $a['akey'];
        if (preg_match('/^svc:issue:(\d+)$/', $k, $m)) {
            try { $st = $pdo->prepare('SELECT id, kind, service FROM service_issues WHERE id = ?'); $st->execute([(int) $m[1]]); $r = $st->fetch(PDO::FETCH_ASSOC); } catch (Throwable $x) { $r = null; }
            if (!$r) return null;
            require_once __DIR__ . '/ServiceHealth.php'; return ServiceHealth::fixOf($r);
        }
        if (preg_match('/^ops:cap:([a-z0-9_]+)$/', $k, $m)) return ['cause' => 'quota', 'url' => '/admin/config/register#' . ($m[1] === 'community' ? 'costs' : 'svc-' . $m[1] . '-caps')];
        // QuickBooks refusing our token (row 70; g4 (b)): a key a person fixes, Connect QuickBooks again on its card
        if ($k === 'crit:site:payments:token') return ['cause' => 'key', 'url' => '/admin/config/register#svc-quickbooks-key'];
        if ($k === 'ops:w0:held') return ['cause' => 'blocked', 'url' => '/admin/config/control/wf-W0#cb-held'];
        if (str_starts_with($k, 'ops:w0:held:')) return ['cause' => 'blocked', 'url' => '/admin/config/control/wf-W0#' . self::heldAnchor(substr($k, 12))];
        return null;
    }

    /** A blocked site's line among the sites that blocked us, on W0's page on the board: held-<host>. */
    public static function heldAnchor(string $host): string { return 'held-' . preg_replace('/[^a-z0-9.-]/i', '-', mb_strtolower($host)); }

    /** Where a push or an email opens it (0176 §5: "From a push, it opens the app at the thread's Pulse row, with the panel
     *  on the fix"): its Pulse row, and its Fix it in the panel when it has one. */
    public static function pulseUrl(PDO $pdo, array $a): string
    {
        $fx = null; try { require_once __DIR__ . '/Thread.php'; $fx = Thread::fixOf(['fix' => self::fixOf($pdo, $a)]); } catch (Throwable $x) {}
        return '/admin/dashboard/stream#case=alarm:' . (int) $a['id'] . ($fx ? '&fix=1' : '');
    }

    /** When its Escalation Notice went (0171 §5's note), from the logbook; '' when it hasn't. */
    private static function escalatedAt(PDO $pdo, array $a): string
    {
        try { $st = $pdo->prepare("SELECT MAX(at) FROM control_log WHERE proc = ? AND kind = 'alarm' AND what LIKE 'Escalation Notice%' AND at >= ?");
              $st->execute([(string) $a['proc'], (string) $a['first_at']]); return (string) ($st->fetchColumn() ?: ''); }
        catch (Throwable $x) { return ''; }
    }

    /** What it affects (0176 §2): what its trip paused; an outside service's needs with their other sources, or "none". */
    public static function affects(PDO $pdo, array $a): array
    {
        $out = []; $sw = self::stoppedWords($a);
        if ($sw !== '' && $a['cleared_at'] === null) $out[] = 'Paused by the system: ' . $sw;
        elseif ($a['cleared_at'] === null && $a['level'] === 'trip' && !empty($a['tripped_at']) && empty($a['retrying_at'])) $out[] = 'Nothing could be paused from here (it only trips)';
        $proc = (string) $a['proc'];
        if (str_starts_with($proc, 'svc:')) {
            $co = substr($proc, 4);
            try {
                require_once __DIR__ . '/Preflight.php';
                foreach (Preflight::NEEDS as $nd) {
                    $srcs = (array) $nd[3]; $mine = false; $other = [];
                    foreach ($srcs as $sr) {
                        if (($sr[0] ?? '') === 'svc' && (string) ($sr[1] ?? '') === $co) { $mine = true; continue; }
                        $other[] = ($sr[0] ?? '') === 'svc' ? self::partName($pdo, 'svc:' . (string) $sr[1]) : (($sr[0] ?? '') === 'paid' ? 'a paid backup (a director\'s override)' : (string) ($sr[2] ?? ''));
                    }
                    if (!$mine) continue;
                    $wf = $nd[2] ? ' (' . implode(', ', (array) $nd[2]) . ')' : '';
                    $out[] = preg_replace('/ \(W[^)]*\)$/', '', (string) $nd[1]) . $wf . ': ' . ($other ? 'also from ' . implode(', ', array_filter($other, 'strlen')) : 'no backup (' . (string) $nd[4] . ')');
                    if (count($out) >= 5) break;
                }
            } catch (Throwable $x) {}
        }
        return $out;
    }

    /** The alarm inside its message in Pulse (Notify draws it: decisions/0176 §1): everything but the details, Acknowledge. */
    public static function pulseBody(PDO $pdo, array $m, int $viewer): array
    {
        require_once __DIR__ . '/Thread.php';
        $id = (int) preg_replace('/\D/', '', (string) ($m['thing'] ?? $m['about'] ?? ''));
        $st = $pdo->prepare('SELECT * FROM control_alarms WHERE id = ?'); $st->execute([$id]); $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return ['html' => '<p>This alarm is no longer kept.</p>', 'state' => 'Gone'];
        $state = $a['cleared_at'] !== null ? 'Cleared: ' . (string) $a['cleared_how'] : ($a['ack_at'] !== null ? 'Acknowledged by ' . ($a['ack_name'] ?? 'staff') . '; open until its reading is good' : 'Open');
        $f = self::facts($pdo, $a);
        // Acknowledge only on a trip (R80; 02 §3c: a warning has "no Acknowledge"; row 97)
        $f['buttons'] = array_merge($a['cleared_at'] === null && $a['ack_at'] === null && $a['level'] === 'trip' ? [['Acknowledge', 'ack', (string) $id]] : [], [['Open it on the board', 'href', '/admin/config/control#cb-alarm-' . $id]]);
        return ['html' => Thread::html($f, 'pulse', null, '/admin/api/config/control'), 'state' => $state];
    }

    /**
     * Its Pulse item's steps (0171 §8): the same buttons as its row on the board, acting in place through Pulse::manage
     * (a cap's Raise the cap as a one-field form, Pause it, Check it now, Mark resolved), then the board itself.
     */
    public static function pulseSteps(PDO $pdo, int $id, callable $s): array
    {
        $st = $pdo->prepare('SELECT * FROM control_alarms WHERE id = ?'); $st->execute([$id]); $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return [$s('Nothing to do', 'This alarm is no longer kept.', null, true)];
        $fx = json_decode((string) $a['fix'], true); $fx = is_array($fx) ? (isset($fx[0]) && is_array($fx[0]) ? $fx : [$fx]) : [];
        // no Mark resolved (Jason, 2026-11-19: "this should not be an option, it should be the result of an issue being
        // fixed"): a passing check closes it, Check it now or, on a trip, Check and start again
        // a trip: the same buttons as its row on the board, where an admin's Resolve (Check and start again) stands for these
        // (a trip closes as Recovered when its check passes, 0175 §3, or by Check and start again; Jason, 2026-11-19: Pulse's
        // buttons differing from the board's "don't work")
        // Mark resolved is removed everywhere (02 §5.3; row 95): an old button stored on a row is never drawn
        $fx = array_values(array_filter($fx, fn ($f) => ($f[1] ?? '') !== 'svc' . 'resolve'));
        if ($a['level'] === 'trip' && !empty($a['tripped_at'])) $fx = array_values(array_filter($fx, fn ($f) => !in_array($f[1] ?? '', ['svccheck', 'check'], true)));
        $out = [];
        // Fix it first (0176 §5, A5): its one spot in the case's panel; a spot still to come, greyed with its reason
        require_once __DIR__ . '/Thread.php';
        $fix = $a['cleared_at'] === null ? Thread::fixOf(['fix' => self::fixOf($pdo, $a)]) : null;
        if ($fix) $out[] = Thread::FIX[$fix['cause']][1] !== '' ? $s('Fix it', Thread::FIX[$fix['cause']][1] . '.', null) : $s('Fix it', 'Opens beside this case: ' . Thread::FIX[$fix['cause']][0] . '. ' . Thread::FIX[$fix['cause']][2] . '.', ['tool', $fix['url']]);
        if ($a['cleared_at'] === null) foreach ($fx as $f) {
            $k = (string) ($f[1] ?? ''); $l = (string) ($f[0] ?? '');
            if ($k === 'peek') $out[] = $s($l, 'Opens beside this item.', ['tool', (string) $f[2]]);
            elseif ($k === 'opsask') $out[] = $s($l, 'Now ' . (string) ($f[3][1] ?? '') . '. Kept with who and when.', ['form', 'alarm_' . (string) $f[2], 'Set it', [['value', (string) ($f[3][0] ?? 'Value'), 'text', (string) ($f[3][1] ?? '')]]]);
            elseif ($k === 'ops') $out[] = $s($l, is_string($f[3] ?? null) ? (string) $f[3] : 'Done in place.', ['do', 'alarm_' . (string) $f[2], $l]);
            elseif ($k === 'link') $out[] = $s($l, 'Opens on their site, in a new tab; nothing is changed from here.', null, false, (string) ($f[3] ?? ''));
        }
        if ($a['cleared_at'] === null && $a['level'] === 'trip' && !empty($a['tripped_at']))
            $out[] = $s('Check and start again', 'The system checks it first' . (self::stoppedWords($a) !== '' ? ', then starts again ' . self::stoppedWords($a) : '') . '; if the check is still red, it says which part holds it. Undo is kept for a day.', ['do', 'alarm_resolve', 'Check and start again']);
        elseif (!empty($a['resolved_at']) && strtotime($a['resolved_at'] . ' UTC') > time() - 86400)
            $out[] = $s('Undo the Resolve', 'Opens the trip again and stops again what it had stopped.', ['do', 'alarm_unresolve', 'Undo']);
        $out[] = $s('Open it on the board', 'Where it sits in the system, with its evidence.', ['tool', '/admin/config/control#cb-alarm-' . $id]);
        return $out;
    }

    /**
     * An operations step pressed on an alarm, on the board or in Pulse (each family's own permission, in Operations::pulseDo
     * and what it calls): its case's key and the steps that family carries. Unknown pairs are refused.
     */
    public static function opsDo(PDO $pdo, string $akey, string $what, array $in, int $uid, string $who): string
    {
        $fam = [
            '/^ops:(cap|empty):[a-z0-9_]+$/' => ['', '/^ops_(cap|pause|runnext|task_off):[a-z0-9_]+$/'],
            '/^ops:start:[a-f0-9]{10}$/' => ['ops:start', '/^ops_start_(go|skip|junk|tick)$/'],
            '/^ops:w0:[a-z0-9_.:-]+$/i' => ['ops:w0', '/^ops_w0_(reactivate|flag_ok):[a-z0-9_.:\/-]+$/i'],
            '/^ops:pf:stopped$/' => ['ops:preflight', '/^ops_pf_(continue|cancel)$/'],
            '/^task:failed:[a-z0-9_]+$/' => ['', '/^ops_(runnext|task_off):[a-z0-9_]+$/'],   // a failed task's own steps (routes, 2026-11-19)
        ];
        foreach ($fam as $kre => [$case, $wre]) if (preg_match($kre, $akey) && preg_match($wre, $what)) {
            require_once __DIR__ . '/Operations.php';
            return Operations::pulseDo($pdo, $case !== '' ? $case : $akey, $what, $in, $uid, $who);
        }
        throw new RuntimeException('That step isn\'t one the board does.');
    }

    /** A step pressed on its Pulse item: the board's own action, with the board's permissions. */
    public static function pulseDo(PDO $pdo, int $id, string $what, array $in, int $uid, string $who): string
    {
        $st = $pdo->prepare('SELECT akey FROM control_alarms WHERE id = ?'); $st->execute([$id]); $akey = (string) ($st->fetchColumn() ?: '');
        if ($akey === '') throw new RuntimeException('This alarm is no longer kept.');
        if (str_starts_with($what, 'alarm_ops_')) return self::opsDo($pdo, $akey, substr($what, 6), $in, $uid, $who);
        if (in_array($what, ['alarm_resolve', 'alarm_unresolve'], true)) {   // an admin's Resolve, and its Undo (executive; starting again, Automation's)
            require_once __DIR__ . '/ControlBoard.php'; [$ok, $why] = ControlBoard::can(); if (!$ok) throw new RuntimeException($why . '.');
            $a = self::byId($pdo, $id);
            if ($a && json_decode((string) ($a['stopped'] ?? ''), true)) { require_once __DIR__ . '/SystemStart.php'; [$ok, $why] = SystemStart::can(); if (!$ok) throw new RuntimeException($why . ': it would start again what the trip stopped.'); }
            [$done, $msg] = $what === 'alarm_resolve' ? self::resolve($pdo, $id, $uid, $who, (string) ($in['note'] ?? '')) : self::unresolve($pdo, $id, $uid, $who);
            if (!$done) throw new RuntimeException($msg);
            return $msg;
        }
        throw new RuntimeException('Unknown step.');
    }

    /**
     * The watch (0171 §5, every 5 minutes: control_tick): what doesn't call in. W0 Indexer silent while it's switched on, a
     * crawler run past its time plus 5 minutes, a site job whose last run stopped, a job run that never finished. Each raised, or cleared when its reading is good. Returns what it did, in words.
     */
    /**
     * The system's tries (the one engine's diagnostic clock, P3): each open alarm whose try is due is checked on its
     * component (ControlEngine::tick). An alarm raised under the old rule (tries counted by its own repeats, "The system is
     * trying again by itself (…)", or a critical part's trip still trying) gets its next try now, once, so it's checked
     * rather than tripped by a count. Returns what it did.
     */
    public static function expireTries(PDO $pdo): array
    {
        self::ensure($pdo);
        if (!self::$engine) return [];
        try { $pdo->exec("UPDATE control_alarms SET next_try_at = UTC_TIMESTAMP() WHERE cleared_at IS NULL AND next_try_at IS NULL AND try_n = 0 AND ((level = 'warning' AND means LIKE '%The system is trying again by itself (%') OR (level = 'trip' AND retrying_at IS NOT NULL))"); } catch (Throwable $x) {}
        require_once __DIR__ . '/ControlEngine.php';
        return ControlEngine::tick($pdo);
    }

    public static function tick(PDO $pdo): string
    {
        self::ensure($pdo);
        $did = [];
        if ($sd = self::seed($pdo)) $did[] = $sd . ' known problem(s) raised';
        foreach (self::settle0175($pdo) as $p) $did[] = $p;
        foreach (self::expireTries($pdo) as $p) $did[] = $p;
        // maintenance windows (0171 §2.5, release 4a): one whose time has come goes out; one past its Back by comes back
        try { require_once __DIR__ . '/ControlMaintenance.php'; foreach (ControlMaintenance::tick($pdo) as $m) $did[] = 'maintenance: ' . $m; } catch (Throwable $x) {}
        // the outside heartbeat's records, raised while the site was down, numbered now it's back (02 §0a ruling 6)
        try { require_once __DIR__ . '/ControlIncidents.php'; if ($hb = ControlIncidents::importHeartbeat($pdo)) $did[] = $hb . ' heartbeat record(s) imported'; } catch (Throwable $x) {}
        // Site health on one path: each new finding of the site audit as a Pulse item, no push (check-register.md, Jason, 2026-11-22)
        try { require_once __DIR__ . '/SiteHealth.php'; if ($sh = SiteHealth::dispatch($pdo)) $did[] = $sh . ' site audit finding(s) to Pulse'; } catch (Throwable $x) {}
        require_once __DIR__ . '/Automation.php';
        $masterOn = Automation::master($pdo) === null;
        // W0 Indexer: its check-in (every minute or so while it runs)
        try {
            require_once __DIR__ . '/crawler/Frontier.php';
            $beat = (int) (Frontier::lastBeat($pdo)['at'] ?? 0); $on = $masterOn && Automation::wfOff($pdo, 'W0') === null;
            if ($on && $beat && time() - $beat > 15 * 60) { self::raise($pdo, 'silent:w0', 'wk:w0-indexer', 'trip', 'silent', 'W0 Indexer has gone quiet', 'It hasn\'t checked in for ' . (int) floor((time() - $beat) / 60) . ' minutes while it\'s switched on: no addresses are being logged.', 'Open Railway (beside this) and its W0 Indexer service: if it crashed or its plan ran out, redeploy it there; then press Check here.', 'last check-in ' . gmdate('M j H:i', $beat) . ' UTC', [['Railway: W0 Indexer', 'link', 'https://railway.com/dashboard', 'railway'], ['Check', 'check', 'wk:w0-indexer']]); $did[] = 'W0 quiet'; }
            elseif ($beat && time() - $beat <= 15 * 60 && self::clear($pdo, 'silent:w0', 'It checked in at ' . gmdate('H:i', $beat) . ' UTC.')) $did[] = 'W0 back';
            elseif (!$on && ($ow = self::openOf($pdo, 'silent:w0')) && $ow['level'] !== 'trip') self::clear($pdo, 'silent:w0', 'It was switched off.');   // a trip stays as 0171 set it: switching W0 off isn't its check passing
        } catch (Throwable $x) {}
        // a crawler run that never reported: its time (RUN_LIMIT, 30 minutes) plus 5
        try {
            $late = $pdo->query("SELECT id, started_at FROM crawl_runs WHERE status = 'running' AND started_at < NOW() - INTERVAL 35 MINUTE ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($late) { self::raise($pdo, 'silent:run', 'wk:crawler', 'trip', 'silent', 'A crawler run never reported (#' . (int) $late['id'] . ')', 'It started over 35 minutes ago and hasn\'t said how it ended: it was stopped, or it crashed on Railway.', 'Open the run (beside this) for what it did before it went quiet, and the crawler on Railway for its last log lines; the site marks it abandoned.', 'started ' . (string) $late['started_at'], [['Run #' . (int) $late['id'], 'peek', '/admin/insights/crawler?run=' . (int) $late['id']], ['Railway: crawler', 'link', 'https://railway.com/dashboard', 'railway'], ['Check', 'check', 'wk:crawler']]); $did[] = 'run quiet'; }
            // an abandoned run is a failure, never "no run is overdue" (02 row 47; g1 row 30: four killed runs never tripped): it
            // clears only once a later run reports back
            elseif ((string) $pdo->query("SELECT status FROM crawl_runs ORDER BY id DESC LIMIT 1")->fetchColumn() !== 'abandoned' && self::clear($pdo, 'silent:run', 'No run is overdue (' . gmdate('H:i') . ' UTC).')) $did[] = 'run cleared';
        } catch (Throwable $x) {}
        // site jobs: the last run of each, stopped or never finished
        try {
            $t = Database::t('job_runs'); $bad = []; $stuck = [];
            // a skipped run ("Paused: …", written as ok) is never a good run (the one engine; g3 F1, J2): each job is read by
            // its last real run, and only a real run that ended well clears its alarm; a job with no real run in the window
            // keeps what's open (no evidence is never a clear)
            $good = [];
            foreach ($pdo->query("SELECT r.job, r.ok, r.note, r.started_at, r.finished_at FROM $t r JOIN (SELECT job, MAX(id) id FROM $t WHERE started_at > NOW() - INTERVAL 2 DAY AND note NOT LIKE 'Paused:%' GROUP BY job) m ON m.id = r.id")->fetchAll(PDO::FETCH_ASSOC) as $j) {
                $job = (string) $j['job'];
                if ($j['ok'] !== null && (int) $j['ok'] === 1) $good[] = $job;
                if ($j['ok'] !== null && (int) $j['ok'] === 0) {   // its own next runs are the system's tries (3c): a trip once they fail too
                    $bad[] = 'job:' . $job;
                    $rs = $pdo->prepare("SELECT ok FROM $t WHERE job = ? AND finished_at IS NOT NULL AND note NOT LIKE 'Paused:%' ORDER BY id DESC LIMIT 20"); $rs->execute([$job]);
                    $fails = 0; foreach ($rs->fetchAll(PDO::FETCH_COLUMN) as $ok) { if ((int) $ok === 1) break; $fails++; }
                    [$max] = self::limits($pdo, 'crash');
                    self::raise($pdo, 'job:' . $job, 'job:' . $job, 'trip', 'crash', self::jobName($job) . ' failed', 'Its last run ended with a problem (' . $fails . ' in a row); what it does waits until it runs well again.', 'Open its job (beside this): its note says why it stopped. Fix the cause; its next good run clears this.', mb_substr((string) $j['note'], 0, 255), [['Open its job: ' . self::jobPlain($job), 'peek', '/admin/config/scheduler#job-' . $job, self::jobPlain($job)]], false, $fails < $max);
                }
                // stuck over 2 h is a fault, not slow (02 row 66; g3 J5: "slow" could never trip): a warning, its next runs its
                // tries; a critical job (billing) trips at once (P8, row 68)
                elseif ($j['finished_at'] === null && strtotime((string) $j['started_at']) < time() - 7200) { $stuck[] = 'job:' . $job . ':stuck'; self::raise($pdo, 'job:' . $job . ':stuck', 'job:' . $job, 'trip', 'crash', self::jobPlain($job) . ': its run never finished', 'Its run started over 2 hours ago and never said it ended: it was killed, or it hangs.', 'Open its job (beside this) for its runs; the server\'s error log says why it hung. The next run starts as usual.', 'started ' . self::atUtc((int) strtotime((string) $j['started_at'])), [['Open its job: ' . self::jobPlain($job), 'peek', '/admin/config/scheduler#job-' . $job, self::jobPlain($job)]], false); }
            }
            $n = 0; foreach ($good as $gj) foreach (['job:' . $gj, 'job:' . $gj . ':stuck'] as $gk) if (!in_array($gk, array_merge($bad, $stuck), true) && self::clear($pdo, $gk, 'Its last run ended well (' . gmdate('H:i') . ' UTC).')) $n++;
            // slow (0171 §5, 3c): a job's last runs against its usual time (the middle of its last 20, 5 at least) × control_slow_factor,
            // and over a minute; a warning, a trip after control_slow_trips slow runs in a row
            require_once __DIR__ . '/Thresholds.php'; $f = (float) (Thresholds::get($pdo, 'control_slow_factor') ?? 3); [$sMax] = self::limits($pdo, 'slow'); $slow = [];
            foreach ($pdo->query("SELECT DISTINCT job FROM $t WHERE finished_at IS NOT NULL AND started_at > NOW() - INTERVAL 2 DAY")->fetchAll(PDO::FETCH_COLUMN) as $job) {
                $rs = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, started_at, finished_at) FROM $t WHERE job = ? AND finished_at IS NOT NULL ORDER BY id DESC LIMIT 21"); $rs->execute([$job]);
                $d = array_map('intval', $rs->fetchAll(PDO::FETCH_COLUMN)); if (count($d) < 6) continue;
                $past = array_slice($d, 1); sort($past); $usual = $past[intdiv(count($past), 2)]; $lim = max(60, $usual * $f);
                $run = 0; foreach ($d as $x) { if ($x <= $lim) break; $run++; }
                if ($run > 0) { $slow[] = 'jobslow:' . $job; self::raise($pdo, 'jobslow:' . $job, 'job:' . $job, 'warning', 'slow', self::jobPlain($job) . ' is slow',
                    'Its last run took ' . $d[0] . ' s against its usual ' . $usual . ' s (' . $run . ' slow in a row): what follows it waits.', 'Open its job (beside this) for its runs and times; a large backlog or a slow query is the usual cause.', $d[0] . ' s (usual ' . $usual . ')', [['Open its job: ' . self::jobPlain($job), 'peek', '/admin/config/scheduler#job-' . $job, self::jobPlain($job)]], false, false); }
            }
            self::clearOthers($pdo, 'jobslow:', $slow, 'Its last run took its usual time (' . gmdate('H:i') . ' UTC).');
            // late (02 row 65; g3 J7: red text on Scheduled tasks only): a job that hasn't started by its time plus 5 minutes.
            // Its time comes from its own cron line when it has one (a schedule changed on Scheduled tasks is read as changed),
            // else its default (Scheduler::JOBS); 14 days of runs, as long as they're kept, so a job silent for days stays
            // late instead of dropping out; never a job that logs only when it has work (QUIET), a retired one, or one taken
            // out on purpose. A skipped run ("Paused: …") still started on time. Its times in UTC, labelled (2026-11-23).
            try { require_once __DIR__ . '/Scheduler.php'; $every = ['minute' => 1, '5min' => 5, '10min' => 10, '15min' => 15, '30min' => 30, 'hourly' => 60, 'hourly2' => 60, '6hours' => 360, 'daily' => 1440, 'nightly' => 1440, 'weekly' => 10080];
                $late = []; $cand = [];
                $off = []; try { $off = $pdo->query("SELECT job FROM " . Database::t('job_setup') . " WHERE state = 'removed'")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $x) {}
                foreach ($pdo->query("SELECT job, MAX(started_at) s FROM $t WHERE started_at > NOW() - INTERVAL 14 DAY GROUP BY job")->fetchAll(PDO::FETCH_KEY_PAIR) as $job => $st0) {
                    if (!isset(Scheduler::JOBS[$job]) || in_array($job, Scheduler::QUIET, true) || in_array($job, Scheduler::RETIRED, true) || in_array($job, $off, true)) continue;
                    $mins = $every[(string) (Scheduler::JOBS[$job][2] ?? '')] ?? 0; if (!$mins) continue;
                    $s0 = (int) strtotime((string) $st0); if (time() > $s0 + $mins * 60 + 300) $cand[$job] = $s0;
                }
                // only when one looks late: its cron line, read once (cPanel's API), so the alarm says which it is
                $lines = null; if ($cand) { try { require_once __DIR__ . '/CPanel.php'; if (CPanel::ready()) $lines = Scheduler::cronLines(); } catch (Throwable $x) { $lines = null; } }
                foreach ($cand as $job => $s0) {
                    $l = $lines !== null ? Scheduler::lineFor($lines, $job) : null;
                    if ($lines !== null && $l) { $pk = Scheduler::presetOf($l); if ($pk === '' || !isset($every[$pk])) continue;   // a schedule we can't read in minutes: not judged
                        if (time() <= $s0 + $every[$pk] * 60 + 300) continue; $mins = $every[$pk]; }
                    else $mins = $every[(string) Scheduler::JOBS[$job][2]];
                    if (in_array('job:' . $job . ':stuck', $stuck, true)) continue;   // one cause, one alarm: its stuck run says it (rollups, css-theme-brand §2)
                    $due = $s0 + $mins * 60;
                    $missing = $lines !== null && !$l;
                    $late[] = 'joblate:' . $job;
                    self::raise($pdo, 'joblate:' . $job, 'job:' . $job, 'warning', 'silent', self::jobPlain($job) . ' is late',
                        $missing ? 'Its cron line isn\'t in cPanel any more, so nothing starts it; what it does waits.' : ($lines !== null ? 'Its cron line is there, but the server didn\'t start it on time (the server\'s cron, or too many jobs at once); what it does waits.' : 'Its cron line may be missing, or the server\'s cron stopped; what it does waits.'),
                        $missing ? 'Open its job (beside this): Set them up now adds its line back.' : 'Open its job (beside this): its line and its last runs. It clears when it starts.',
                        'due ' . self::atUtc($due) . ' · last started ' . self::atUtc($s0), [['Open its job: ' . self::jobPlain($job), 'peek', '/admin/config/scheduler#job-' . $job, self::jobPlain($job)]], false, false); }
                self::clearOthers($pdo, 'joblate:', $late, 'It started on time (' . gmdate('H:i') . ' UTC).');
            } catch (Throwable $x) { error_log('job lateness: ' . $x->getMessage()); }
            if ($bad) $did[] = count($bad) . ' job(s) stopped'; if ($n) $did[] = $n . ' job alarm(s) cleared';
        } catch (Throwable $x) {}
        // Overpass retired (Jason, 2026-11-19, "Stop entirely"; ServiceHealth::OSM_RETIRED): what was open about it, the
        // servers' issues and "Every Overpass server is held", closes as retired, once; nothing raises it again
        try { require_once __DIR__ . '/ServiceHealth.php'; if (($nr = ServiceHealth::retireOsm($pdo)) > 0) $did[] = $nr . ' Overpass alarm(s) closed: retired'; } catch (Throwable $x) {}
        // the last Pulse cases as alarms (0171 §8, 3c): Starting the system, W0 Indexer's needs, a pre-flight run that stopped;
        // and the cap and "returns empty" readings (3b)
        try { require_once __DIR__ . '/Operations.php'; Operations::alarms($pdo); } catch (Throwable $x) { error_log('ops alarms: ' . $x->getMessage()); }
        // the site's own critical duties: search, sign-in and payments (ControlSensor, 0171 §5's note)
        try { require_once __DIR__ . '/ControlSensor.php'; $sw = ControlSensor::watch($pdo); if ($sw !== '') $did[] = $sw; } catch (Throwable $x) { error_log('sensor: ' . $x->getMessage()); }
        // the 10-minute release job looking in git (2026-11-19: control-board-legacy sat in the inbox 25 minutes; the ping got
        // "too soon" and the job never took it): a warning when it hasn't looked in 30 minutes, cleared when it looks again
        try { require_once __DIR__ . '/Staging.php';
            if (!Staging::here()) { require_once __DIR__ . '/Releases.php'; $jl = 0; foreach (Releases::lastLooked($pdo) as $lk) if ($lk[0] === 'the 10-minute job') $jl = (int) $lk[1];
                if ($jl < time() - 1800) self::raise($pdo, 'rel:job:quiet', 'job:releases_tick', 'warning', 'check', 'The 10-minute release job hasn\'t looked in git ' . ($jl ? 'since ' . gmdate('M j, g:i A', $jl) . ' UTC' : 'yet'),
                    'Builds merged into the docs repo\'s inbox wait until the ping from git or Check for builds takes them; nothing is lost.',
                    'Check for builds on Releases takes them now. Then see its cron line in Scheduled tasks (Set them up now adds a missing one).',
                    $jl ? 'Last looked ' . gmdate('Y-m-d H:i', $jl) . ' UTC' : 'Never looked', [['Releases (Check for builds)', 'peek', '/admin/config/releases'], ['Its job: releases_tick', 'peek', '/admin/config/scheduler#job-releases_tick']], false);
                else self::clear($pdo, 'rel:job:quiet', 'It looked in git at ' . gmdate('H:i', $jl) . ' UTC.'); } } catch (Throwable $x) { error_log('release job watch: ' . $x->getMessage()); }
        // each open pre-flight alarm's words from its line as it stands now (stored results; nothing is run again): a trip
        // raised before the board read a check part by part kept its old words (W0, 2026-11-18); now it names its red part,
        // or, with its check passing, closes as Recovered (ESCALATED_RECOVERS). Not a new event: its count doesn't move.
        try { require_once __DIR__ . '/Preflight.php'; $pl = Preflight::lines($pdo); $pr = Preflight::results($pdo); $nr = 0;
            $rerun = 0;
            // a trip from before 3c (no trip time) first: each is tested again and settled (control-board-legacy, 2026-11-19)
            foreach ($pdo->query("SELECT akey, level, tripped_at FROM control_alarms WHERE cleared_at IS NULL AND akey LIKE 'pf:%' ORDER BY (level = 'trip' AND tripped_at IS NULL) DESC, id")->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $lid = substr((string) $ar['akey'], 3); if (!isset($pl[$lid])) continue;
                $legacy = $ar['level'] === 'trip' && empty($ar['tripped_at']);
                $at = (int) ($pr[$lid]['at'] ?? 0); $st = (string) ($pr[$lid]['s'] ?? '');
                // tested again first (free and read only; three a watch): a line not tested in 6 hours, so its words are today's
                // (2026-11-19: W0's result was from before checks were stored part by part); one left waiting on an answer for
                // over 2 minutes (W0's test batch: asked, answered, never read back); W0's test batch red for half an hour (asked
                // again: a short batch from a back-off proves nothing); and an old trip, to settle it
                if ($rerun < 3 && ($at < time() - 6 * 3600 || ($st === 'wait' && $at < time() - 120) || ($lid === 'rw:batch' && $st === 'bad' && $at < time() - 1800) || $legacy)) {
                    try { $pr[$lid] = Preflight::run($pdo, $lid, 'the Control Board\'s watch', true); $rerun++; } catch (Throwable $x) { continue; }
                }
                if (isset($pr[$lid])) { Preflight::alarm($pdo, $lid, $pl[$lid], $pr[$lid], false, $legacy); $nr++; }
            }
        } catch (Throwable $x) { error_log('pf words: ' . $x->getMessage()); }
        // titles raised before #258 redrawn from their state (words follow the state, principle 4; the session manager, 2026-11-23)
        try { if (($rt = self::redrawTitles($pdo)) > 0) $did[] = $rt . ' alarm title(s) redrawn from their state'; } catch (Throwable $x) { error_log('retitle: ' . $x->getMessage()); }
        // a slow trip from before 4e (or a slow alarm still holding what it stopped): the system lifts it itself, checking
        // first, and tries again every watch while a check is red (Jason, 2026-11-19: "no dead ends, system fixes first")
        try { foreach ($pdo->query("SELECT id, level FROM control_alarms WHERE cleared_at IS NULL AND kind = 'slow' AND (level = 'trip' OR (stopped IS NOT NULL AND stopped <> '' AND stopped <> '[]'))")->fetchAll(PDO::FETCH_ASSOC) as $sa) {
                [$okL, $msgL] = self::resolve($pdo, (int) $sa['id'], 0, 'the system', 'A slow call is a warning, never a stop (Jason, 2026-11-19): checked and started again by the system');
                if ($okL) $did[] = 'lifted slow trip #' . (int) $sa['id'];
                elseif ($sa['level'] === 'trip') $pdo->prepare("UPDATE control_alarms SET level = 'warning', means = ? WHERE id = ?")->execute([mb_substr('Slow, not broken. What it stopped starts again once its check passes; the system tries every 5 minutes. ' . $msgL, 0, 400), (int) $sa['id']]);
            } } catch (Throwable $x) { error_log('slow lift: ' . $x->getMessage()); }
        // sources read regularly outside our region (2026-11-19: it was raised once by the seed, judged by any two-letter
        // ending, and sent Jason to a list that didn't hold them): recounted hourly, its button opens them beside the board,
        // cleared when none are left; the system first follows shortened links to the site behind them
        try { require_once __DIR__ . '/crawler/Sources.php'; require_once __DIR__ . '/SearchIndex.php';
            if (Sources::tableExists($pdo) && (int) SearchIndex::state($pdo, 'control:offregion_at') < time() - 3600) {
                SearchIndex::setState($pdo, 'control:offregion_at', (string) time());
                if ($rs = Sources::resolveShorteners($pdo)) $did[] = $rs . ' shortened link(s) followed';
                [$off] = Sources::offRegion($pdo);
                if ($off) { $hs = array_map(fn ($x) => (string) $x[0]['host'], $off);
                    self::raise($pdo, 'w3:offregion', 'wf:W3', 'warning', 'check', count($off) . ' source' . (count($off) === 1 ? ' is' : 's are') . ' read regularly outside our region (' . implode(', ', array_slice($hs, 0, 3)) . (count($hs) > 3 ? ' and more' : '') . ')',
                        'W3 reads them every few days; their facts are likely about other places, and reading them uses the crawler\'s time. Nothing is paused unless you or a rule says so.',
                        'Open the ' . count($off) . ' sources (beside this): pause or keep each, pause all shown, or say which outside sources are welcome.',
                        mb_substr(implode(', ', array_slice($hs, 0, 12)), 0, 255), ['Open the ' . count($off) . ' sources', 'peek', '/admin/sources.php?show=offregion'], false);
                } else self::clear($pdo, 'w3:offregion', 'No source read regularly is outside our region now (or a rule welcomes it).');
            } } catch (Throwable $x) { error_log('offregion: ' . $x->getMessage()); }
        // a rate limit tripped before 5a: a warning again, back on its back-off (429 is "go slower", never a stop)
        try { require_once __DIR__ . '/ServiceHealth.php'; if ($rl = ServiceHealth::liftRateTrips($pdo)) $did[] = $rl . ' rate-limit trip(s) made warnings'; } catch (Throwable $x) {}
        // a site issue that backs off (a timeout, a server error) with its wait over and nothing calling it: the watch runs its
        // service's free check, at most once an hour each, two a watch; an answer closes it (4f, 2026-11-19: Claude's #19 sat
        // open from 20:05 with nothing calling). Never a refusal or a block: those wait for a person (principle 1).
        try { require_once __DIR__ . '/ServiceHealth.php'; require_once __DIR__ . '/Preflight.php'; require_once __DIR__ . '/SearchIndex.php'; $pl2 = Preflight::lines($pdo); $nc = 0;
            foreach (ServiceHealth::openAll($pdo) as $si) {
                if ($nc >= 2 || $si['caller'] !== 'site' || (ServiceHealth::KINDS[$si['kind']][1] ?? '') !== 'back' || ServiceHealth::holding($si) || !isset($pl2['svc:' . $si['service']])) continue;
                $k = 'sh:recheck:' . (int) $si['id'];
                if ((int) SearchIndex::state($pdo, $k) > time() - 3600) continue;
                SearchIndex::setState($pdo, $k, (string) time());
                $rr = Preflight::run($pdo, 'svc:' . $si['service'], 'the Control Board\'s watch', true); $nc++;
                if (($rr['s'] ?? '') === 'na') ServiceHealth::resolve($pdo, (int) $si['id'], 0, 'Not set up, so nothing calls it: ' . mb_substr((string) $rr['said'], 0, 160) . ' A new failure opens a new issue.');   // nothing waits on it
                $did[] = 'rechecked issue #' . (int) $si['id'];
            }
        } catch (Throwable $x) { error_log('issue recheck: ' . $x->getMessage()); }
        // the historian (0171 §6, release 4b): each process's state and the hour's counts kept over time; last, so the rest of
        // the watch never waits on it
        // its own time kept and watched like any slow job (the session manager, 2026-11-18): a heavy watch shows on the board
        try { require_once __DIR__ . '/ControlHistorian.php'; $h0 = microtime(true); foreach (ControlHistorian::tick($pdo) as $h) $did[] = 'history: ' . $h;
            ControlHistorian::timed($pdo, (int) round((microtime(true) - $h0) * 1000)); } catch (Throwable $x) {}
        return $did ? implode('; ', $did) . '.' : 'Nothing new.';
    }

    /**
     * What's already known, raised once (0171, release 3a: the queue isn't empty on day one): the pre-flight's red lines,
     * the service issues open now, the worker calls that answered a server error in the last day, and sources outside
     * our region being read. After this, each is raised the moment it happens.
     */
    public static function seed(PDO $pdo): int
    {
        self::ensure($pdo);
        require_once __DIR__ . '/SearchIndex.php';
        if (SearchIndex::state($pdo, 'control:alarms_seeded') !== '') return 0;
        $n = 0;
        try { require_once __DIR__ . '/Preflight.php'; $lines = Preflight::lines($pdo);
            foreach (Preflight::results($pdo) as $id => $r) if (isset($lines[$id]) && in_array($r['s'] ?? '', ['bad', 'warn'], true)) { Preflight::alarm($pdo, (string) $id, $lines[$id], $r); $n++; } } catch (Throwable $x) {}
        try { require_once __DIR__ . '/ServiceHealth.php';
            foreach (ServiceHealth::openAll($pdo) as $i) { $ev = json_decode((string) $i['evidence'], true) ?: []; $l = (array) ($ev['latest'] ?? []);
                ServiceHealth::alarm($pdo, (int) $i['id'], (string) $i['service'], (string) $i['host'], (string) $i['kind'], ['status' => (int) ($l['status'] ?? 0), 'error' => (string) ($l['error'] ?? ''), 'body' => (string) ($l['body'] ?? '')], (string) $i['caller']); $n++; } } catch (Throwable $x) {}
        try { require_once __DIR__ . '/ControlRegister.php';
            foreach ($pdo->query("SELECT l.id, l.endpoint, l.code, l.reasons, l.at, c.n FROM control_ledger l JOIN (SELECT endpoint, MAX(id) id, COUNT(*) n FROM control_ledger WHERE code >= 500 AND at >= UTC_TIMESTAMP() - INTERVAL 1 DAY GROUP BY endpoint) c ON c.id = l.id WHERE l.id = (SELECT MAX(id) FROM control_ledger z WHERE z.endpoint = l.endpoint)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $proc = self::procOfEndpoint($pdo, (string) $r['endpoint']);
                self::raise($pdo, 'ep:' . $r['endpoint'] . ':5xx', $proc, 'trip', 'crash', '/api/' . $r['endpoint'] . ' answers a server error (' . (int) $r['n'] . ' in the last day)',
                    'The worker\'s calls fail on our side; what they need is not done until it\'s fixed.', 'Open the failing call (beside this): its error and the records it carried. Fix its cause on the site; it clears by itself at the next good answer.', mb_substr((string) preg_replace('/^answered \d+: /', '', (string) $r['reasons']), 0, 255), [['The call (#' . (int) $r['id'] . ')', 'peek', '/admin/config/ledger#open=config/ledger:' . (int) $r['id']], ['Check', 'check', $proc]]); $n++;
            } } catch (Throwable $x) {}
        SearchIndex::setState($pdo, 'control:alarms_seeded', gmdate('c'));
        return $n;
    }

}
