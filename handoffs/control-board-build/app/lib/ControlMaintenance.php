<?php
// app/lib/ControlMaintenance.php — the System Control Board's maintenance stage (decisions/0171 §2.5, release 4a). A
// process is taken out of service on purpose, with a reason and a planned end ("Back by"): it's stopped in order (its
// tasks, then the workflow: ControlBoard::stopOne), shows blue, its alarms become notices and Start skips it, saying
// why; it's never shown as a failure. Put back runs its pre-flight first, then starts again what it stopped; a red check
// keeps it out with the reason. A window set ahead takes it out and puts it back by itself (the 5-minute watch), both
// logged. Kept in control_maintenance (staging class: own). Workflows and the tasks with a switch of their own can be
// taken out; anything else isn't stopped from here.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ControlMaintenance
{
    private static bool $ready = false;
    private static ?array $out = null;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ready) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS control_maintenance (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            proc VARCHAR(60) NOT NULL, state VARCHAR(10) NOT NULL DEFAULT 'planned', from_at DATETIME NOT NULL, until_at DATETIME NOT NULL,
            why VARCHAR(255) NOT NULL, by_uid INT UNSIGNED NULL, by_name VARCHAR(80) NULL, out_at DATETIME NULL, stopped VARCHAR(255) NULL,
            back_at DATETIME NULL, back_by VARCHAR(80) NULL, note VARCHAR(400) NULL, KEY proc_state (proc, state), KEY state (state)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$ready = true;
    }

    /**
     * Whether a part can be taken out from here (02 §5.3: "extended to every part"; row 103: "for any module, host, job,
     * worker or part"): a workflow, a task with a switch of its own, a module (svc:), a job, a worker or a site part. A
     * workflow or task is stopped in order; any other part is marked out: its faults are Notices and Start skips it, and
     * nothing of it is switched (P5: maintenance never writes another switch).
     */
    public static function canTake(string $proc): bool
    {
        if (str_starts_with($proc, 'wf:')) return true;
        if (str_starts_with($proc, 't:')) { require_once __DIR__ . '/SystemStart.php'; $d = SystemStart::TASKS[substr($proc, 2)] ?? null; return (bool) ($d && $d[4]); }
        return (bool) preg_match('/^(svc|job|wk|site|ep|nd|rw):[A-Za-z0-9_.\/-]{1,50}$/', $proc);
    }

    /** Take out's default length, in hours: Thresholds & gates' control_maint_hours (2 until set). */
    public static function defaultHours(PDO $pdo): int
    {
        try { require_once __DIR__ . '/Thresholds.php'; $v = Thresholds::get($pdo, 'control_maint_hours'); return $v === null ? 2 : max(1, min(72, (int) $v)); } catch (Throwable $x) { return 2; }
    }

    /** Processes out now: proc => row. Cached per request; never throws. */
    public static function out(PDO $pdo): array
    {
        if (self::$out !== null) return self::$out;
        try { self::ensure($pdo); $rows = $pdo->query("SELECT * FROM control_maintenance WHERE state = 'out' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $x) { return self::$out = []; }
        $o = []; foreach ($rows as $r) $o[(string) $r['proc']] = $r;
        return self::$out = $o;
    }

    /** The maintenance a process is under: its own, or its workflow's for a task (t:…) that belongs to a workflow out. */
    public static function outFor(PDO $pdo, string $proc): ?array
    {
        $o = self::out($pdo);
        if (!$o) return null;
        if (isset($o[$proc])) return $o[$proc];
        if (str_starts_with($proc, 't:')) { require_once __DIR__ . '/SystemStart.php'; $wf = SystemStart::TASKS[substr($proc, 2)][1] ?? ''; if ($wf !== '' && isset($o['wf:' . $wf])) return $o['wf:' . $wf]; }
        return null;
    }

    /** In words, for a light's title, Start's held line and an alarm turned notice: "In maintenance: why, back by …". */
    public static function words(array $r): string
    {
        return 'In maintenance: ' . $r['why'] . ', back by ' . gmdate('M j, H:i', (int) strtotime($r['until_at'] . ' UTC')) . ' UTC';
    }

    /** Windows planned ahead and those out now, soonest first. */
    public static function listing(PDO $pdo): array
    {
        try { self::ensure($pdo); return $pdo->query("SELECT * FROM control_maintenance WHERE state IN ('planned', 'out') OR back_at >= UTC_TIMESTAMP() - INTERVAL 2 DAY ORDER BY state = 'back', from_at")->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $x) { return []; }
    }

    /**
     * Take a process out now, or plan a window ($from in the future). $until: when it's due back. Returns words.
     * Refused: one that can't be stopped from here, an end before its start, or one already out or planned over the time.
     */
    public static function take(PDO $pdo, string $proc, int $from, int $until, string $why, int $uid, string $who): string
    {
        self::ensure($pdo);
        $why = trim($why);
        if (!self::canTake($proc)) throw new RuntimeException('It can\'t be taken out from here: that isn\'t a part on the board.');
        if ($why === '') throw new RuntimeException('Say why, so whoever sees it out knows.');
        $now = time(); $from = max($now, $from);
        if ($until <= $from + 60) throw new RuntimeException('Its Back by has to be after it goes out.');
        $st = $pdo->prepare("SELECT COUNT(*) FROM control_maintenance WHERE proc = ? AND state IN ('planned', 'out') AND from_at < ? AND until_at > ?");
        $st->execute([$proc, gmdate('Y-m-d H:i:s', $until), gmdate('Y-m-d H:i:s', $from)]);
        if ((int) $st->fetchColumn()) throw new RuntimeException('It\'s already out, or planned out, over that time.');
        $pdo->prepare("INSERT INTO control_maintenance (proc, state, from_at, until_at, why, by_uid, by_name) VALUES (?, 'planned', ?, ?, ?, ?, ?)")
            ->execute([$proc, gmdate('Y-m-d H:i:s', $from), gmdate('Y-m-d H:i:s', $until), mb_substr($why, 0, 255), $uid ?: null, mb_substr($who, 0, 80) ?: null]);
        $id = (int) $pdo->lastInsertId();
        if ($from > $now + 60) return 'Planned: it goes out ' . gmdate('M j, H:i', $from) . ' UTC and comes back by itself by ' . gmdate('M j, H:i', $until) . ' UTC.';
        return self::goOut($pdo, $id);
    }

    /** A window's start: stop the process in order, and mark it out. */
    private static function goOut(PDO $pdo, int $id): string
    {
        $r = self::row($pdo, $id);
        if (!$r || $r['state'] !== 'planned') return 'It isn\'t waiting to go out.';
        $proc = (string) $r['proc']; $stopped = '';
        // the watch late, or the site down at its start (the session manager's note, 2026-11-18): it still goes out at the
        // next watch, and says so; a window whose Back by passed before any watch ran is missed, logged, never skipped silently
        $from = (int) strtotime($r['from_at'] . ' UTC'); $until = (int) strtotime($r['until_at'] . ' UTC'); $late = time() - $from;
        if ($until <= time()) {
            $pdo->prepare("UPDATE control_maintenance SET state = 'cancelled', note = ? WHERE id = ?")->execute(['Missed: no watch ran between its start and its Back by (the site or its jobs were down), so it never went out.', $id]);
            self::log($pdo, $proc, 'A maintenance window was missed: ' . $r['why'] . ', ' . gmdate('M j, H:i', $from) . ' to ' . gmdate('H:i', $until) . ' UTC. No watch ran in it, so it never went out.');
            return 'Missed: its window had ended before a watch ran.';
        }
        $lateWords = $late > 600 ? ' It went out ' . (int) round($late / 60) . ' minutes late: the watch didn\'t run at its start (the site or its jobs were down).' : '';
        require_once __DIR__ . '/Automation.php';
        // only a workflow or a task is stopped in order; any other part is only marked out (canTake)
        $on = str_starts_with($proc, 'wf:') ? Automation::wfOff($pdo, substr($proc, 3)) === null : str_starts_with($proc, 't:');
        if ($on) { try { require_once __DIR__ . '/ControlBoard.php'; ControlBoard::stopOne($pdo, $proc, (int) ($r['by_uid'] ?? 0), 'maintenance: ' . (string) ($r['by_name'] ?? '')); $stopped = $proc; } catch (Throwable $x) { $stopped = ''; } }
        $pdo->prepare("UPDATE control_maintenance SET state = 'out', out_at = UTC_TIMESTAMP(), stopped = ? WHERE id = ?")->execute([$stopped !== '' ? $stopped : null, $id]);
        self::$out = null;
        self::quiet($pdo, $proc);
        self::log($pdo, $proc, 'Taken out for maintenance: ' . $r['why'] . ($r['by_name'] ? ' (' . $r['by_name'] . ')' : '') . ', back by ' . gmdate('M j, H:i', $until) . ' UTC.' . $lateWords);
        return 'Out for maintenance' . ($stopped !== '' ? ' (stopped: its tasks, then the workflow)' : ' (it was already off)') . '; back by ' . gmdate('M j, H:i', $until) . ' UTC. Its alarms are notices until then, and Start skips it.' . $lateWords;
    }

    /**
     * Put it back: its pre-flight first, then what maintenance stopped starts again. A red check keeps it out, with the
     * reason on its row. $auto: the window's end, by the watch. Returns [ok, words].
     */
    public static function putBack(PDO $pdo, int $id, int $uid, string $who, bool $auto = false): array
    {
        $r = self::row($pdo, $id);
        if (!$r || $r['state'] !== 'out') return [false, 'It isn\'t out for maintenance.'];
        $proc = (string) $r['proc'];
        if ((string) $r['stopped'] !== '') {
            try { require_once __DIR__ . '/ControlBoard.php'; ControlBoard::startOne($pdo, $proc, $uid, $who !== '' ? $who : 'the Control Board'); }
            catch (Throwable $x) {
                $why = 'Not back at ' . gmdate('H:i') . ' UTC: ' . $x->getMessage();
                $pdo->prepare('UPDATE control_maintenance SET note = ? WHERE id = ?')->execute([mb_substr($why, 0, 400), $id]);
                if ($auto) { try { require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::raise($pdo, 'maint:back:' . $proc, $proc, 'warning', 'check', $proc . ' didn\'t come back from maintenance', 'Its window ended, but its check is red, so it stays out.', 'Press Check (beside this) to see what its check says; fix that, then press Put back on its window (beside this).', mb_substr($x->getMessage(), 0, 255), [['Check', 'check', $proc], ['Its window', 'peek', '/admin/config/control#maintenance']], false); } catch (Throwable $y) {} }
                return [false, $why];
            }
        }
        $pdo->prepare("UPDATE control_maintenance SET state = 'back', back_at = UTC_TIMESTAMP(), back_by = ?, note = NULL WHERE id = ?")->execute([mb_substr($auto ? 'its window\'s end' : $who, 0, 80), $id]);
        self::$out = null;
        try { require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::clear($pdo, 'maint:back:' . $proc, 'It came back.'); } catch (Throwable $x) {}
        self::log($pdo, $proc, 'Put back from maintenance' . ($auto ? ' at its window\'s end' : ' by ' . $who) . ((string) $r['stopped'] !== '' ? ': its check passed and it\'s on again.' : '.'));
        return [true, 'Back from maintenance' . ((string) $r['stopped'] !== '' ? ': its check passed and it\'s on again.' : '.')];
    }

    /** A window not yet started: cancelled. */
    public static function cancel(PDO $pdo, int $id, string $who): string
    {
        $r = self::row($pdo, $id);
        if (!$r || $r['state'] !== 'planned') throw new RuntimeException('Only a window that hasn\'t started can be cancelled; one that\'s out is put back.');
        $pdo->prepare("UPDATE control_maintenance SET state = 'cancelled', note = ? WHERE id = ?")->execute([mb_substr('Cancelled by ' . $who, 0, 400), $id]);
        try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, 'maint', (string) $r['proc'], 'A maintenance window cancelled: ' . (string) $r['why'], $who); } catch (Throwable $x) {}
        return 'Cancelled: it won\'t go out.';
    }

    /** The watch's part (every 5 minutes): windows whose time has come go out; those past their Back by come back. */
    public static function tick(PDO $pdo): array
    {
        $did = [];
        try {
            self::ensure($pdo);
            foreach ($pdo->query("SELECT id, proc FROM control_maintenance WHERE state = 'planned' AND from_at <= UTC_TIMESTAMP() ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) { $w = self::goOut($pdo, (int) $r['id']); $did[] = $r['proc'] . (str_starts_with($w, 'Missed') ? ' missed' : ' out'); }
            foreach ($pdo->query("SELECT id, proc FROM control_maintenance WHERE state = 'out' AND until_at <= UTC_TIMESTAMP() ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                [$ok] = self::putBack($pdo, (int) $r['id'], 0, '', true); $did[] = $r['proc'] . ($ok ? ' back' : ' kept out (red check)');
            }
        } catch (Throwable $x) { error_log('maintenance tick: ' . $x->getMessage()); }
        return $did;
    }

    public static function row(PDO $pdo, int $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT * FROM control_maintenance WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Its open warnings go quiet: notices while it's out (0171 §2.5). A trip stays a trip: it closes as Recovered when its check passes (0175 §3), or by Check and start again. */
    private static function quiet(PDO $pdo, string $proc): void
    {
        try { require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ensure($pdo); $pdo->prepare("UPDATE control_alarms SET level = 'notice' WHERE proc = ? AND cleared_at IS NULL AND level = 'warning'")->execute([$proc]); } catch (Throwable $x) {}
    }

    /** One line in the Pulse-free record (the alarms' notices list it until the logbook, release 5). */
    private static function log(PDO $pdo, string $proc, string $what): void
    {
        try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, 'maint', $proc, $what); } catch (Throwable $x) {}   // the logbook (0171 §10, 5a)
        try { require_once __DIR__ . '/ControlAlarms.php'; $id = ControlAlarms::raise($pdo, 'maint:' . $proc . ':' . gmdate('YmdHi'), $proc, 'notice', 'check', $what, '', '', '', [], true, false); if ($id) ControlAlarms::clear($pdo, 'maint:' . $proc . ':' . gmdate('YmdHi'), 'Logged.'); } catch (Throwable $x) {}
    }
    // ---- the upkeep calendar (0171 §2.5): what's due, read from what the code already schedules -------------------------

    /** The next time the clock reads $min:$hour (on $wday, '*' any day) in Arizona (no daylight saving), at or after $from. */
    public static function nextAz(int $hour, int $min, int $from, string $wday = '*'): int
    {
        $d = new DateTime('@' . $from); $d->setTimezone(new DateTimeZone('America/Phoenix'));
        $d->setTime($hour, $min, 0);
        for ($i = 0; $i < 8; $i++) {
            if ($d->getTimestamp() >= $from && ($wday === '*' || (int) $d->format('w') === (int) $wday)) return $d->getTimestamp();
            $d->modify('+1 day');
        }
        return $d->getTimestamp();
    }

    /** A scheduled job's next run, from its preset in Scheduler (minute, hour, weekday): null for one that runs every few minutes. */
    private static function nextJob(string $job, int $from): ?array
    {
        require_once __DIR__ . '/Scheduler.php';
        $pre = Scheduler::PRESETS[Scheduler::JOBS[$job][2] ?? ''] ?? null;
        if (!$pre || !ctype_digit($pre[1]) || !ctype_digit($pre[2])) return null;
        return [self::nextAz((int) $pre[2], (int) $pre[1], $from, $pre[5]), $pre[0] . ' (Arizona)'];
    }

    /** The crawler's night window for bulk loads (CrawlService::NIGHT, Arizona hours), read from the code. */
    public static function night(): array
    {
        try { require_once __DIR__ . '/crawler/CrawlService.php'; return (array) (new ReflectionClassConstant('CrawlService', 'NIGHT'))->getValue(); }
        catch (Throwable $x) { return [1, 5]; }
    }

    /**
     * What's due, soonest first: [what, at (unix, or 0 when its switch is off), how often, note, process]. Read only from
     * what the code and settings schedule, never a hand list (the session manager's note, 2026-11-18): the bulk and monthly
     * loads from crawl_capture_tasks in CrawlService's night window, the scheduled jobs from their Scheduler presets (Data
     * cleanup's daily pass, staging's nightly pass), the nightly pre-flight (Preflight::NIGHT_HOUR), and the checks due on
     * held services (service_issues). Each names its process, so the board opens it in place. Never throws.
     */
    public static function upkeep(PDO $pdo): array
    {
        $now = time(); $out = [];
        [$n0, $n1] = self::night();
        $fmt = fn (int $h) => ($h % 12 ?: 12) . ($h < 12 ? ' am' : ' pm');
        try {
            require_once __DIR__ . '/SystemStart.php'; require_once __DIR__ . '/ControlRegister.php';
            foreach ($pdo->query("SELECT task, wf, enabled, every_minutes, last_served_at FROM crawl_capture_tasks WHERE mode = 'bulk' ORDER BY wf, sort, task")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $task = (string) $r['task']; $every = (int) $r['every_minutes'];
                $last = $r['last_served_at'] !== null ? (int) strtotime((string) $r['last_served_at']) : 0;   // as CrawlService reads it
                $at = max($now, $last ? $last + $every * 60 : $now);
                $d = new DateTime('@' . $at); $d->setTimezone(new DateTimeZone('America/Phoenix')); $h = (int) $d->format('G');
                if ($h < $n0 || $h >= $n1) $at = self::nextAz($n0, 0, $at);
                $name = ''; $proc = 'wf:' . $r['wf'];
                foreach (SystemStart::TASKS as $k => $tk) if (in_array($task, (array) $tk[4], true)) { $name = $tk[0]; $proc = 't:' . $k; break; }
                if ($name === '') foreach (ControlRegister::ENDPOINTS as $ep) if (in_array($task, (array) $ep[1], true)) { $name = $ep[0]; break; }
                $often = 'every ' . ($every % 1440 === 0 ? ($every / 1440) . ' day' . ($every === 1440 ? '' : 's') : $every . ' minutes');
                $out[] = [$name !== '' ? $name : $task, (int) $r['enabled'] ? $at : 0, $often . ', in the night window (' . $fmt($n0) . '–' . $fmt($n1) . ' Arizona)',
                    ((int) $r['enabled'] ? ($last ? 'Last ' . gmdate('M j', $last) : 'Not run yet') : 'Off: its switch is off'), $proc];
            }
        } catch (Throwable $x) {}
        require_once __DIR__ . '/Scheduler.php';
        // a job outside Scheduler::AUTO runs only once someone adds it on Scheduled tasks: it's due only if it has run lately
        foreach (['data_cleanup_tick', 'staging_night_tick'] as $job) {
            $nx = self::nextJob($job, $now);
            if (!$nx) continue;
            $ran = '';
            try { $st = $pdo->prepare('SELECT MAX(started_at) FROM ' . Database::t('job_runs') . ' WHERE job = ?'); $st->execute([$job]); $ran = (string) $st->fetchColumn(); } catch (Throwable $x) {}
            $set = in_array($job, Scheduler::AUTO, true) || ($ran !== '' && strtotime($ran) > $now - 3 * 86400);
            $out[] = [(string) Scheduler::JOBS[$job][0], $set ? $nx[0] : 0, $nx[1], $set ? ($ran !== '' ? 'Last ' . gmdate('M j, H:i', (int) strtotime($ran)) . ' UTC' : 'Not run yet') : 'Not set up: it runs once it\'s added on Scheduled tasks', 'job:' . $job];
        }
        try { require_once __DIR__ . '/Preflight.php'; $out[] = ['The nightly pre-flight', self::nextAz(Preflight::NIGHT_HOUR, 0, $now), 'its first run after ' . $fmt(Preflight::NIGHT_HOUR) . ' Arizona, each night', 'Every pre-flight test; read-only', 'job:preflight_tick']; } catch (Throwable $x) {}
        try {
            foreach ($pdo->query("SELECT service, host, next_probe_at FROM service_issues WHERE state = 'open' AND next_probe_at IS NOT NULL ORDER BY next_probe_at LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $out[] = ['A check on ' . $r['service'] . ($r['host'] ? ' (' . $r['host'] . ')' : ''), max($now, (int) strtotime($r['next_probe_at'] . ' UTC')), 'until it answers', 'A held service\'s light check: it clears the hold if it answers', 'svc:' . $r['service']];
        } catch (Throwable $x) {}
        usort($out, fn ($a, $b) => ($a[1] ?: PHP_INT_MAX) <=> ($b[1] ?: PHP_INT_MAX));
        return $out;
    }
}
