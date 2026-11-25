<?php
// app/lib/ControlBoard.php — the System Control Board's view and controls (decisions/0171 §1, §2, release 2b; Jason,
// 2026-11-18: "start to finish system management and control. Organized by how an operator can see and understand each
// system and everything within its process"). It reads what exists and adds nothing of its own to the database:
//   the systems and processes (ControlRegister), each process's pre-flight line and route as last tested (Preflight), its
//   switch (Automation), the last 24 hours of its worker calls (ControlLedger's control_ledger), its crawler task
//   outcomes (crawl_run_items), its site jobs' runs (job_runs), and Start the system's state (SystemStart);
// and acts through what exists: a process is started only after its pre-flight line is tested green (the interlock), shut
// down in order (its tasks, then the workflow), and the whole system shut down in reverse start-up order, ending
// "stopped, safe" once no crawler run is still going. Pause everything stays the emergency stop (SystemStart::pauseAll).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Automation.php';
require_once __DIR__ . '/ControlRegister.php';

final class ControlBoard
{
    public const STAGES = [
        'preflight' => 'Pre-flight', 'startup' => 'Start-up', 'observe' => 'Observation and reporting',
        'shutdown' => 'Shut-down', 'maintenance' => 'Maintenance', 'history' => 'History',
    ];
    /** Shut-down's record (search_index_state). */
    public const KEY = 'ops:shutdown';
    public const BY = 'the Control Board';

    public static function can(): array
    {
        if (!class_exists('Access', false)) require_once __DIR__ . '/../admin/Access.php';
        return Access::can('config.control', ['min' => 'executive', 'depts' => []]);
    }

    /** The stage the system is in now: [key, words]. */
    public static function stage(PDO $pdo): array
    {
        require_once __DIR__ . '/SystemStart.php';
        require_once __DIR__ . '/Preflight.php';
        $sd = self::shutdownState($pdo);
        if (($sd['state'] ?? '') === 'stopping') return ['shutdown', 'Shutting down: waiting for what was running to finish.'];
        $live = Preflight::live($pdo);
        if (($live['state'] ?? '') === 'running') return ['preflight', 'The pre-flight is running (' . (int) ($live['i'] ?? 0) . ' of ' . (int) ($live['total'] ?? 0) . ').'];
        $st = SystemStart::state($pdo);
        if (($st['state'] ?? '') === 'running') return ['startup', 'Starting: ' . self::startWhere($st)];
        if (Automation::master($pdo) !== null) return ['shutdown', ($sd['state'] ?? '') === 'safe' ? 'Stopped, safe: ' . (string) ($sd['left'] ?? '') : 'Stopped: the master switch is off.'];
        return ['observe', 'Running, watched.'];
    }

    private static function startWhere(array $st): string
    {
        $n = 0; $on = 0;
        foreach ((array) ($st['items'] ?? []) as $id => $it) if (str_starts_with((string) $id, 'wf:') || str_starts_with((string) $id, 't:')) { $n++; if (in_array($it['s'] ?? '', ['on', 'done'], true)) $on++; }
        return $on . ' of ' . $n . ' on.';
    }

    /** A time window as SQL: [from, to] seconds ago (null: the last 24 hours), against $now (UTC_TIMESTAMP() or NOW()). */
    private static function win(?array $w, string $col, string $now): string
    {
        if ($w === null) return "$col >= $now - INTERVAL 1 DAY";
        return "$col >= $now - INTERVAL " . (int) $w[0] . " SECOND AND $col < $now - INTERVAL " . (int) $w[1] . ' SECOND';
    }

    /**
     * The last 24 hours of a set of endpoints' calls: calls, received, stored, updated, review, refused, errors, avg ms.
     * $w: [from, to] seconds ago instead (the historian's hour, release 4b).
     */
    public static function calls(PDO $pdo, array $endpoints, ?array $w = null): array
    {
        $z = ['calls' => 0, 'received' => 0, 'stored' => 0, 'updated' => 0, 'review' => 0, 'refused' => 0, 'errors' => 0, 'ms' => 0];
        if (!$endpoints) return $z;
        try {
            $in = implode(',', array_map(fn ($e) => $pdo->quote($e), $endpoints));
            $r = $pdo->query("SELECT COUNT(*) calls, COALESCE(SUM(n_received), 0) received, COALESCE(SUM(n_stored), 0) stored, COALESCE(SUM(n_updated), 0) updated, COALESCE(SUM(n_review), 0) review, COALESCE(SUM(n_refused), 0) refused, COALESCE(SUM(code >= 500), 0) errors, COALESCE(ROUND(AVG(ms)), 0) ms FROM control_ledger WHERE endpoint IN ($in) AND " . self::win($w, 'at', 'UTC_TIMESTAMP()'))->fetch(PDO::FETCH_ASSOC);
            foreach ($z as $k => $_) $z[$k] = (int) ($r[$k] ?? 0);
            if ($w !== null) return $z;
            // the last thing that went wrong, in its own words (2026-11-18: W2's 500 was hidden): a server error, or a record set aside
            $e = $pdo->query("SELECT at, endpoint, code, reasons FROM control_ledger WHERE endpoint IN ($in) AND at >= UTC_TIMESTAMP() - INTERVAL 1 DAY AND (code >= 500 OR reasons LIKE 'set aside%') ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($e) $z['last_error'] = ['at' => (int) strtotime($e['at'] . ' UTC'), 'endpoint' => (string) $e['endpoint'], 'code' => (int) $e['code'], 'said' => (string) ($e['reasons'] ?? '')];
        } catch (Throwable $x) {}
        return $z;
    }

    /** Every endpoint's calls in the window (the ledger, whatever workflow claims it): endpoint => calls(…)'s counts. */
    public static function callsByEndpoint(PDO $pdo, ?array $w = null): array
    {
        try {
            $out = [];
            foreach ($pdo->query("SELECT endpoint, COUNT(*) calls, COALESCE(SUM(n_received), 0) received, COALESCE(SUM(n_stored), 0) stored, COALESCE(SUM(n_updated), 0) updated, COALESCE(SUM(n_review), 0) review, COALESCE(SUM(n_refused), 0) refused, COALESCE(SUM(code >= 500), 0) errors FROM control_ledger WHERE " . self::win($w, 'at', 'UTC_TIMESTAMP()') . " GROUP BY endpoint")->fetchAll(PDO::FETCH_ASSOC) as $r)
                $out[(string) $r['endpoint']] = array_map('intval', array_diff_key($r, ['endpoint' => 1]));
            return $out;
        } catch (Throwable $x) { return []; }
    }

    /** The last 24 hours of crawler task outcomes (or the window $w): outcome => count. */
    public static function outcomes(PDO $pdo, array $tasks, ?array $w = null): array
    {
        if (!$tasks) return [];
        try {
            $in = implode(',', array_map(fn ($t) => $pdo->quote($t), $tasks));
            return array_map('intval', $pdo->query("SELECT outcome, COUNT(*) FROM crawl_run_items WHERE task IN ($in) AND " . self::win($w, 'at', 'NOW()') . " GROUP BY outcome")->fetchAll(PDO::FETCH_KEY_PAIR));
        } catch (Throwable $x) { return []; }
    }

    /** The last 24 hours of site jobs' runs (or the window $w): [good, failed]. */
    public static function jobRuns(PDO $pdo, array $jobs, ?array $w = null): array
    {
        if (!$jobs) return [0, 0];
        try {
            $in = implode(',', array_map(fn ($j) => $pdo->quote($j), $jobs));
            $r = $pdo->query('SELECT COALESCE(SUM(ok = 1), 0) g, COALESCE(SUM(ok = 0), 0) b FROM ' . Database::t('job_runs') . " WHERE job IN ($in) AND " . self::win($w, 'started_at', 'NOW()'))->fetch(PDO::FETCH_ASSOC);
            return [(int) $r['g'], (int) $r['b']];
        } catch (Throwable $x) { return [0, 0]; }
    }

    /** A crawler task's switch: null when on, else why it's off. */
    private static function taskOff(PDO $pdo, string $task): ?string
    {
        try {
            require_once __DIR__ . '/crawler/CrawlService.php';
            if (!CrawlService::ready($pdo)) return null;
            $t = CrawlService::tasks($pdo)[$task] ?? null;
            return $t && !(int) $t['enabled'] ? 'Off' . (!empty($t['off_why']) ? ': ' . (string) $t['off_why'] : '') : null;
        } catch (Throwable $x) { return null; }
    }

    /**
     * One process as the board shows it: [system, name, light (ok, warn, bad, off, wait), words, line (its pre-flight line's
     * stored result), route (its route line's stored result), calls, outcomes, jobs, switch ('on', 'off', ''), parts].
     */
    public static function process(PDO $pdo, string $id, array $p, array $res): array
    {
        require_once __DIR__ . '/Preflight.php';
        [$sys, $name] = $p;
        $line = $res[$id] ?? null; $route = null; $parts = []; $calls = self::calls($pdo, []); $out = []; $jobs = [0, 0]; $switch = ''; $off = null;
        if (str_starts_with($id, 'wf:')) {
            $wf = substr($id, 3); $parts = ControlRegister::of($pdo, $wf);
            $route = $res['rt:' . $id] ?? null; $calls = self::calls($pdo, array_values(array_filter($parts['endpoints'], fn ($e) => !in_array($e, ['crawl/service', 'crawl/usage'], true))));
            $out = self::outcomes($pdo, $parts['tasks']); $jobs = self::jobRuns($pdo, $parts['jobs']);
            $off = Automation::wfOff($pdo, $wf); $switch = $wf === 'W13' ? '' : ($off === null ? 'on' : 'off');
        } elseif (str_starts_with($id, 't:')) {
            require_once __DIR__ . '/SystemStart.php';
            $d = SystemStart::TASKS[substr($id, 2)] ?? null;
            if ($d) { $out = self::outcomes($pdo, $d[4]); $jobs = self::jobRuns($pdo, $d[3]); foreach ($d[4] as $tk) if (($o = self::taskOff($pdo, $tk)) !== null) $off = $o; $switch = $d[4] ? ($off === null ? 'on' : 'off') : ''; }
        } elseif (str_starts_with($id, 'wk:')) {
            $k = substr($id, 3); $w = ControlRegister::WORKERS[$k];
            $line = $w[3] !== '' ? ($res[$w[3]] ?? null) : null; $route = $res['rt:' . $id] ?? null;
            $eps = $w[2]; foreach (ControlRegister::ENDPOINTS as $e => [, $for]) if (in_array('@' . $k, $for, true) || ($k === 'crawler' && !array_filter($for, fn ($f) => $f[0] === '@' || $f === 'W0'))) $eps[] = $e;
            $calls = self::calls($pdo, array_values(array_unique($eps)));
        } elseif (str_starts_with($id, 'svc:')) {
            $off = Automation::serviceOff($pdo, substr($id, 4));
            // its own switch on the board (decisions/0173 §2): Activate and Pause, Automation's; not for business operations
            $switch = isset(Automation::NOT_PAUSABLE[substr($id, 4)]) ? '' : ($off === null ? 'on' : 'off');
        } elseif (str_starts_with($id, 'job:')) {
            $jobs = self::jobRuns($pdo, [substr($id, 4)]);
        }
        $s = (string) ($line['s'] ?? 'new');
        // what's wrong now, from its latest runs (2026-11-18: one failure in 92 turned a row red all day, and a red X sat beside
        // only good news): red when the latest call, job run or task run failed, or its check is red; amber when some failed
        // today but the latest did well. A red row's words start with why.
        $eps = $str = [];
        if (str_starts_with($id, 'wf:')) { $eps = array_values(array_filter((array) ($parts['endpoints'] ?? []), fn ($x) => !in_array($x, ['crawl/service', 'crawl/usage'], true))); $str = (array) ($parts['tasks'] ?? []); $jbs = (array) ($parts['jobs'] ?? []); }
        elseif (str_starts_with($id, 't:')) { $d = SystemStart::TASKS[substr($id, 2)] ?? null; $str = $d ? (array) $d[4] : []; $jbs = $d ? (array) $d[3] : []; }
        elseif (str_starts_with($id, 'job:')) $jbs = [substr($id, 4)];
        else $jbs = [];
        $now = self::latest($pdo, $eps, $jbs ?? [], $str);
        $badLine = $s === 'bad';
        // a need on its backup is a working state, never "Holding" (0175 §3; Jason, 2026-11-20: "if a workflow has a component
        // that is failing it needs to either reroute to a back up service or trip"): failing tasks a backup covers make the
        // workflow amber, "On a backup", with the backup named
        $covered = null;
        if (str_starts_with($id, 'wf:') && !$badLine && $now['bad'] && count($now['tasks']) === count($now['bad']))
            $covered = self::onBackup($res, (array) ($parts['needs'] ?? []), array_values($now['tasks']));
        if ($covered) { foreach ($now['bad'] as $i => $b) $now['some'][] = $b . '; ' . $covered[$now['tasks'][$i]][0] . ' is answered by ' . $covered[$now['tasks'][$i]][1] . ' meanwhile'; $now['bad'] = []; }
        $light = $off !== null ? 'off' : ($badLine || $now['bad'] ? 'bad' : ($s === 'warn' || $now['some'] ? 'warn' : ($s === 'ok' || $calls['calls'] || $jobs[0] ? 'ok' : 'wait')));
        $w = [];
        if ($off !== null) $w[] = rtrim($off, '.');
        if ($light === 'bad') {
            foreach ($now['bad'] as $b) $w[] = $b;
            if ($badLine) { $hold = ''; foreach ((array) ($line['extra']['parts'] ?? []) as [$t, $st]) if ($st === 'bad') { $hold = (string) $t; break; }
                $w[] = 'its check is red: ' . self::sentence($hold !== '' ? $hold : (string) ($line['said'] ?? '')); }
        }
        foreach ($now['some'] as $b) $w[] = $b;
        if ($calls['calls']) $w[] = number_format($calls['calls']) . ' worker call' . ($calls['calls'] === 1 ? '' : 's') . ' in 24 hours' . ($calls['errors'] ? ', ' . $calls['errors'] . ' with a server error' : '');
        if ($jobs[0] + $jobs[1]) $w[] = 'site jobs: ' . $jobs[0] . ' ran' . ($jobs[1] ? ', ' . $jobs[1] . ' stopped' : '');
        if (!$w) $w[] = $line ? self::sentence((string) ($line['said'] ?? '')) : 'Not tested yet: run its check.';
        $means = $off !== null ? 'Switched off: it does nothing until it\'s started.' : ($light === 'bad' ? 'It isn\'t doing its work until this is fixed; the rest of the system runs.'
            : ($light === 'warn' ? ($covered ? 'On a backup: it does its work while the failing part is fixed.' : 'It works, with some failures or on a backup.') : ($light === 'ok' ? 'Working.' : 'Not tested yet.')));
        return ['sys' => $sys, 'name' => $name, 'light' => $light, 'words' => ucfirst(rtrim(implode('. ', array_map(fn ($x) => rtrim((string) $x, '.'), $w)), '.')) . '.', 'means' => $means, 'line' => $line, 'route' => $route, 'calls' => $calls, 'outcomes' => $out, 'jobs' => $jobs, 'switch' => $switch, 'parts' => $parts];
    }

    /**
     * The light the board shows, after its rules (Jason, 2026-11-18): an open trip is red; out for maintenance is blue; any
     * other red is amber ("held") except a core host down. Shared by the board and the historian (release 4b), so the
     * trend shows what the board showed. $tripped: proc => true for open trips. Returns [light, means].
     */
    public static function boardLight(PDO $pdo, string $id, array $row, array $tripped): array
    {
        if (isset($tripped[$id])) {
            // every open trip on it started again for its next run (Check and start again lifted its pauses, task-trip-3): it's
            // waiting for that run, nothing held, as its cards say; never "stopped" (words follow the state; W1, 2026-11-24)
            $trips = array_values(array_filter(ControlAlarms::ofProc($pdo, $id), fn ($a) => (string) $a['level'] === 'trip'));
            if ($trips && !array_filter($trips, fn ($a) => ControlAlarms::startedAgain($a) === ''))
                return ['hold', 'Waiting for its run: its trip' . (count($trips) === 1 ? ' was' : 's were') . ' started again, nothing is held, and ' . (count($trips) === 1 ? 'it closes' : 'each closes') . ' as Fixed when that run works.'];
            return ['bad', 'Tripped: stopped until an admin resolves it.'];
        }
        try { require_once __DIR__ . '/ControlMaintenance.php'; if (ControlMaintenance::outFor($pdo, $id)) return ['maint', 'Out on purpose, never a failure: its alarms are notices, Start skips it, and Put back runs its check first.']; } catch (Throwable $x) {}
        if ($row['light'] === 'bad' && !in_array($id, ['svc:bluehost', 'svc:railway', 'svc:github'], true))
            return ['hold', 'Held: it waits for what it needs; the rest of the system runs. It\'s a trip only if the system can\'t fix it itself.'];
        return [(string) $row['light'], (string) $row['means']];
    }

    /**
     * What's paused right now, by the system or a person, and why: the board's live diagnostics line (decisions/0175's note,
     * "pre-flight before a module is active, diagnostics once it is": "The board's top line becomes live diagnostics: what's
     * paused right now (by the system or a person) and why, each item opening its fix in a drawer"), also at the top of
     * Automation (0175 §6). Each: [what, by ('the system' or the person's name), why, where its fix is, verb]. Never throws.
     * Also 'g', its group (0175's note, Jason 2026-11-20, "Paused now gives a clear picture"): 'sys' a trip holds it,
     * 'purpose' a release or a person switched it off; 'alarm' the trip's id; 'sys_of' the board's system; 'proc' its part.
     */
    public static function pausedNow(PDO $pdo): array
    {
        require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/OutsideServices.php'; require_once __DIR__ . '/ControlMaintenance.php';
        $out = [];
        $sys = function (string $kind, string $ref) use ($pdo): ?array { $r = Automation::pauses($pdo)[$kind . ':' . $ref] ?? null; return $r ? ['the system (a trip)', rtrim((string) $r['why'], '. ') . ', since ' . Automation::fmt((string) $r['at']) . '. It resumes when its check passes', (int) ($r['alarm_id'] ?? 0)] : null; };
        // a switch the board's shut-down wrote for a trip, before 0175 made trips pause instead (0175 §7), is the trip's,
        // never a person's (0175's note, 1: "a pause the board's shutdown made after a trip is never 'paused by a person';
        // it names its trip"). Where nobody had set it before, 0175 §7 leaves it as it is, so it's still read here.
        $byTrip = fn (string ...$w) => (bool) array_filter($w, fn ($s) => (bool) preg_match('/the Control Board \(a trip\)|^A trip on the Control Board:/', $s));
        $item = function (string $what, string $by, string $why, string $href, string $g, int $alarm = 0, string $sysOf = 'engine', string $verb = '', string $proc = '') use (&$out) {
            $x = [$what, $by, $why, $href]; if ($verb !== '') $x[4] = $verb; $x['g'] = $g; $x['alarm'] = $alarm; $x['sys_of'] = $sysOf; $x['proc'] = $proc; $out[] = $x; };
        $left = function (string $what, string $why, string $href, array $refs, string $sysOf = 'engine', string $proc = '') use ($pdo, $item, &$out) {
            $a = self::tripOf($pdo, $refs);
            $item($what, 'the system (a trip)', 'Switched off by the board for a trip' . ($a ? ' (' . rtrim((string) $a['title'], '. ') . ')' : '') . ' before trips paused instead; nobody had set this switch before, so it stays off', $href, 'sys', $a && $a['cleared_at'] === null ? (int) $a['id'] : 0, $sysOf, '', $proc);
            $out[count($out) - 1]['left'] = $a ? rtrim((string) $a['title'], '. ') : 'its alarm is no longer kept';
        };
        try {
            if ($m = Automation::master($pdo)) {
                if ($byTrip((string) ($m['why'] ?? ''), (string) ($m['by_name'] ?? ''))) $left('All automation (the master switch)', '', '/admin/config/control?tab=startup', ['master:all', 'run:failed']);
                else $item('All automation (the master switch)', Automation::by($m) ?: 'a person', rtrim(trim((string) ($m['why'] ?? '')) ?: 'paused', '. ') . (!empty($m['at']) ? ', ' . Automation::fmt((string) $m['at']) : ''), '/admin/config/control?tab=startup', 'purpose');
            }
            if ($x = $sys('master', 'all')) $item('All gathering', $x[0], $x[1], '/admin/config/control#cb-alarms', 'sys', $x[2]);
            foreach (Automation::workflows($pdo) as $wf => $r) {
                $name = $wf . ' ' . (string) ($r['name'] ?? Automation::WORKFLOWS[$wf][0] ?? '');
                $at = '/admin/config/control/wf-' . $wf;
                if ((int) ($r['paused'] ?? 0)) {
                    if ($byTrip((string) ($r['paused_why'] ?? ''), (string) ($r['by_user'] ?? ''))) $left($name, '', $at, ['wf:' . $wf], 'engine', 'wf:' . $wf);
                    else $item($name, trim((string) ($r['by_user'] ?? '')) ?: 'a person', rtrim(trim((string) ($r['paused_why'] ?? '')) ?: 'paused', '. ') . (!empty($r['paused_at']) ? ', since ' . date('M j', (int) strtotime((string) $r['paused_at'])) : ''), $at, 'purpose', 0, 'engine', '', 'wf:' . $wf);
                }
                if ($x = $sys('wf', (string) $wf)) $item($name, $x[0], $x[1], $at, 'sys', $x[2], 'engine', '', 'wf:' . $wf);
            }
            try { require_once __DIR__ . '/crawler/CrawlService.php'; require_once __DIR__ . '/SystemStart.php';
                if (CrawlService::ready($pdo)) foreach (CrawlService::tasks($pdo) as $tk => $t) {
                    $at = '/admin/config/control/wf-' . (string) $t['wf'] . '#task-' . $tk;   // its switch, on its workflow's page (0175 §6)
                    $nm = (string) $t['name'] . ' (' . (string) $t['wf'] . ')';
                    if (!(int) $t['enabled']) {
                        if ($byTrip((string) ($t['off_why'] ?? ''), (string) ($t['off_by'] ?? ''))) {
                            $refs = ['wf:' . (string) $t['wf'], 'task:' . $tk, (string) $tk];
                            foreach (SystemStart::TASKS as $st => $d) if (in_array((string) $tk, (array) $d[4], true)) $refs[] = 't:' . $st;
                            $left($nm, '', $at, $refs);
                        } else $item($nm, 'a person', rtrim(trim((string) ($t['off_why'] ?? '')) ?: 'switched off', '. '), $at, 'purpose');
                    }
                    if ($x = $sys('task', (string) $tk)) $item($nm, $x[0], $x[1], $at, 'sys', $x[2]);
                } } catch (Throwable $x) {}
            foreach (Automation::services($pdo) as $k => $r) if ((int) $r['paused']) {
                $nm = (string) (OutsideServices::COMPANIES[$k][0] ?? $k);
                if ($byTrip((string) ($r['why'] ?? ''), (string) ($r['by_name'] ?? ''))) $left($nm, '', '/admin/config/register#svc-' . $k, ['svc:' . $k, 'service:' . $k], 'services', 'svc:' . $k);
                else $item($nm, Automation::by($r) ?: 'a person', rtrim(trim((string) ($r['why'] ?? '')) ?: 'paused', '. '), '/admin/config/register#svc-' . $k, 'purpose', 0, 'services', '', 'svc:' . $k);
            }
            foreach (Automation::pauses($pdo) as $key => $r) if (str_starts_with((string) $key, 'service:')) { $k = substr((string) $key, 8); $x = $sys('service', $k); if ($x) $item((string) (OutsideServices::COMPANIES[$k][0] ?? $k), $x[0], $x[1], '/admin/config/register#svc-' . $k, 'sys', $x[2], 'services', '', 'svc:' . $k); }
            $pn = []; $ps = []; foreach (ControlRegister::processes($pdo) as $pk => $pv) { $pn[$pk] = (string) ($pv[1] ?? $pk); $ps[$pk] = (string) ($pv[0] ?? 'engine'); }
            foreach (ControlMaintenance::out($pdo) as $proc => $r) $item($pn[$proc] ?? (string) $proc, (string) ($r['by_name'] ?? '') ?: 'a person', ControlMaintenance::words($r), '/admin/config/control/' . str_replace(':', '-', (string) $proc), 'purpose', 0, $ps[$proc] ?? 'engine', 'taken out', (string) $proc);
        } catch (Throwable $x) { error_log('paused now: ' . $x->getMessage()); }
        return $out;
    }

    /** The trip a leftover switch came from: an open one first, then the newest; matched by its part or what it stopped. */
    private static function tripOf(PDO $pdo, array $refs): ?array
    {
        try {
            require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ensure($pdo);
            foreach ($pdo->query("SELECT id, proc, akey, title, todo, fix, stopped, cleared_at, cleared_how FROM control_alarms WHERE level = 'trip' ORDER BY cleared_at IS NOT NULL, id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                foreach ($refs as $r) if ($r !== '' && ((string) $a['proc'] === $r || (string) $a['akey'] === $r || str_contains((string) $a['stopped'], '"' . $r . '"'))) return $a;
            }
        } catch (Throwable $x) {}
        return null;
    }

    /** The open trips, newest first: id => the alarm. */
    private static function openTrips(PDO $pdo): array
    {
        $o = [];
        try { require_once __DIR__ . '/ControlAlarms.php'; foreach (ControlAlarms::list($pdo, 300) as $a) if ($a['cleared_at'] === null && $a['level'] === 'trip') $o[(int) $a['id']] = $a; } catch (Throwable $x) {}
        return $o;
    }


    /**
     * The site's duties for the health line, in its order (0175's note, 3: "the critical duties first (0171's)"; "search,
     * sign-in, payments, page load, database, email"): label => the parts (0171 §5's duty register, ControlRegister::dutyMap).
     * Sign-in and email are named out of 0171's "System-critical procedures"; its other parts (releases, the scheduler, the
     * board's watch) are "Site jobs".
     */
    public static function duties(PDO $pdo): array
    {
        require_once __DIR__ . '/ControlRegister.php';
        $d = ['Page load' => [], 'Search' => [], 'Sign-in' => [], 'Payments' => [], 'Database' => [], 'Email' => [], 'Site jobs' => []];
        foreach (ControlRegister::dutyMap($pdo) as $proc => $duty) {
            $l = $proc === 'site:signin' ? 'Sign-in' : ($proc === 'svc:aws' ? 'Email' : ['page' => 'Page load', 'search' => 'Search', 'payments' => 'Payments', 'database' => 'Database', 'procedures' => 'Site jobs'][$duty] ?? '');
            if ($l !== '') $d[$l][] = $proc;
        }
        return array_filter($d);
    }

    /**
     * The one health line over "Paused now" (0175's note, 3), in plain words: the critical duties first, then each system's
     * count: its parts down (a trip, or a core host), what waits on them, and what's off on purpose. $lights: proc => the
     * board's light (from the board's own read; null reads it). Returns [state, words]: 'bad' (red) only for a critical duty
     * down (0175's note, Jason 2026-11-20: "Critical part down"), 'info' for a system's part down or waiting, else 'ok'.
     */
    public static function health(PDO $pdo, array $paused, ?array $lights = null): array
    {
        require_once __DIR__ . '/ControlRegister.php';
        if ($lights === null) { $lights = []; try { require_once __DIR__ . '/ControlHistorian.php'; foreach (ControlHistorian::lights($pdo) as $id => $v) $lights[$id] = (string) $v[0]; } catch (Throwable $x) {} }
        $names = []; $sysOf = []; foreach (ControlRegister::processes($pdo) as $id => $p) { $names[$id] = (string) $p[1]; $sysOf[$id] = (string) $p[0]; }
        $list = function (array $w): string { $w = array_values($w); return count($w) < 2 ? (string) ($w[0] ?? '') : implode(', ', array_slice($w, 0, -1)) . ' and ' . end($w); };
        $ok = []; $bad = []; $say = [];
        foreach (self::duties($pdo) as $label => $procs) {
            $down = []; $out = [];
            foreach ($procs as $p) { $l = $lights[$p] ?? ''; if ($l === 'bad' || $l === 'hold') $down[] = $names[$p] ?? $p; elseif ($l === 'maint') $out[] = $names[$p] ?? $p; }
            if ($down) $bad[] = $label . ': down' . ($down === [$label] ? '' : ' (' . $list($down) . ')');
            elseif ($out) $bad[] = $label . ': out for maintenance (' . $list($out) . ')';
            else $ok[] = $label;
        }
        if ($ok) $say[] = $list(array_map(fn ($x, $i) => $i ? lcfirst($x) : $x, $ok, array_keys($ok))) . ': working';
        foreach ($bad as $b) $say[] = $b;
        // each part said once: a duty's part is in the duties above; a part down or held isn't counted again as waiting
        $quiet = []; $trouble = false; $named = array_merge(...array_values(self::duties($pdo) ?: [[]]));
        // the board's systems in plain words (Jason's example: "Data gathering")
        foreach (['engine' => 'Data gathering', 'workers' => 'Workers', 'services' => 'Outside services', 'site' => 'The site\'s own parts'] as $s => $label) {
            $down = []; $held = 0; $seen = [];
            foreach ($lights as $p => $l) { if (($sysOf[$p] ?? '') !== $s || in_array($p, $named, true)) continue;
                if ($l === 'bad') { $down[] = $names[$p] ?? $p; $seen[$p] = 1; } elseif ($l === 'hold') { $held++; $seen[$p] = 1; } }
            $wait = count(array_filter($paused, fn ($x) => ($x['g'] ?? '') === 'sys' && ($x['sys_of'] ?? '') === $s && !isset($seen[(string) ($x['proc'] ?? '')])));
            $purp = count(array_filter($paused, fn ($x) => ($x['g'] ?? '') === 'purpose' && ($x['sys_of'] ?? '') === $s));
            if (!$down && !$held && !$wait && !$purp) { $quiet[] = $label; continue; }
            $w = []; if ($down || $held || $wait) $trouble = true;
            if ($down) $w[] = count($down) . ' down (' . $list(array_slice($down, 0, 3)) . (count($down) > 3 ? ' and ' . (count($down) - 3) . ' more' : '') . ')';
            if ($held) $w[] = $held . ' held, waiting for what ' . ($held === 1 ? 'it needs' : 'they need');
            if ($wait) $w[] = $wait . ' part' . ($wait === 1 ? '' : 's') . ' waiting on ' . ($down ? 'them' : 'a trip');
            if ($purp) $w[] = $purp . ' off on purpose';
            $say[] = $label . ': ' . implode(', ', $w);
        }
        if ($quiet) $say[] = $list(array_map(fn ($x, $i) => $i ? lcfirst($x) : $x, $quiet, array_keys($quiet))) . ': working';
        return [$bad ? 'bad' : ($trouble ? 'info' : 'ok'), implode('. ', $say) . '.'];
    }

    /**
     * The live "Paused now" line (0175 §6), grouped (0175's note, Jason 2026-11-20, "Paused now gives a clear picture"):
     * the health line first; Needs attention (each trip, with its fix); Waiting on those (what each trip paused, folded under
     * it); Off on purpose (a release's or a person's switch-off, with what it waits for, folded). Each item opens where it's
     * switched or fixed, in the side panel; $id names it for links; $lights: proc => the board's light.
     */
    public static function pausedHtml(PDO $pdo, string $id = 'cb-paused', ?array $lights = null): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $all = self::pausedNow($pdo);
        [$hs, $hw] = self::health($pdo, $all, $lights);
        $h = '<div id="' . $e($id) . '" class="cb-paused">'
            . '<p class="cb-health" data-tv-status="' . $e($hs) . '" data-tv-label="Health now:">' . $e($hw) . '</p>';
        $trips = self::openTrips($pdo);
        if (!$all && !$trips) return $h . '<p class="cb-pn" data-tv-status="ok" data-tv-label="Paused now:">Nothing. Every switch is on, and the system has nothing paused.</p></div>';
        $one = fn (array $x) => '<li><a class="underline" href="' . $e($x[3]) . '" data-peek-frame>' . $e($x[0]) . '</a>: ' . $e($x[4] ?? 'paused') . ' by ' . $e($x[1]) . ' (' . $e($x[2]) . ')</li>';
        // under its trip, an item needs only its name and how it waits; one left by a closed trip says what to do
        $link = fn (array $x) => '<a class="underline" href="' . $e($x[3]) . '" data-peek-frame>' . $e($x[0]) . '</a>';
        $sub = fn (array $x) => '<li>' . $link($x) . ': ' . (isset($x['left']) ? 'switched off for it by the board, before trips paused instead. Nobody had set this switch before, so it stays off until someone switches it on there.' : 'paused until its check passes.') . '</li>';
        $lone = fn (array $x) => isset($x['left']) ? '<li>' . $link($x) . ': left off by a trip that has closed (' . $e($x['left']) . '). The board switched it off before trips paused instead, and nobody had set this switch before, so it stays off. Switch it on there when it\'s ready.</li>' : $one($x);
        $sysItems = array_values(array_filter($all, fn ($x) => ($x['g'] ?? '') === 'sys'));
        $purpose = array_values(array_filter($all, fn ($x) => ($x['g'] ?? '') === 'purpose'));
        $under = []; $loose = [];
        foreach ($sysItems as $x) { if (isset($trips[(int) $x['alarm']])) $under[(int) $x['alarm']][] = $x; else $loose[] = $x; }
        $att = [];
        foreach ($trips as $aid => $a) {
            $fix = rtrim(trim((string) ($a['todo'] ?? '')) ?: trim((string) ($a['fix'] ?? '')) ?: 'Open it to see what fixes it', '. ');
            $fix = (string) preg_replace('/^Its fix:\s*/i', '', $fix);   // a pre-flight trip's words already start "Its fix:": said once
            $w = $under[$aid] ?? [];
            $att[] = '<li><a class="underline" href="/admin/config/control/' . $e(str_replace(':', '-', (string) $a['proc'])) . '" data-peek-frame>' . $e(rtrim((string) $a['title'], '. ')) . '</a>: ' . (empty($a['tripped_at']) ? 'the system is trying to fix it' : 'tripped') . '. Its fix: ' . $e($fix) . '.'
                . ($w ? '<details class="cb-plm"><summary>Waiting on it: ' . count($w) . '</summary><ul class="cb-pl">' . implode('', array_map($sub, $w)) . '</ul></details>' : '') . '</li>';
        }
        // held by the system with no open trip to fold under (its alarm closed or no longer kept): each needs a look itself
        foreach ($loose as $x) $att[] = $lone($x);
        $head = [];
        if ($trips) $head[] = count($trips) . ' trip' . (count($trips) === 1 ? '' : 's') . ' need' . (count($trips) === 1 ? 's' : '') . ' attention';
        if ($loose) $head[] = count($loose) . ' held by the system with no open trip';
        if ($sysItems && count($sysItems) > count($loose)) $head[] = (count($sysItems) - count($loose)) . ' waiting on ' . (count($trips) === 1 ? 'it' : 'them');
        if ($purpose) $head[] = count($purpose) . ' off on purpose';
        $h .= '<div class="cb-pn" data-tv-status="' . ($att ? 'bad' : 'info') . '" data-tv-label="Paused now:">' . $e(ucfirst(implode(', ', $head)) . '. Each opens where it\'s switched or fixed.');
        if ($att) $h .= '<p class="cb-pg"><b>Needs attention</b></p><ul class="cb-pl">' . implode('', $att) . '</ul>';
        if ($purpose) $h .= '<details class="cb-plm"><summary>Off on purpose: ' . count($purpose) . '</summary><ul class="cb-pl">' . implode('', array_map($one, $purpose)) . '</ul></details>';
        return $h . '</div></div>';
    }

    /**
     * Needs as the board's table shows them (the source map, Preflight::NEEDS, read from each need's stored line nd:<key>):
     * key => [name, state (ok, warn, bad, new…), sources [[name, words, works]] in order, ours first, what happens when none
     * works, soft (its fallback always works), when it was tested].
     */
    public static function needs(array $res, array $keys): array
    {
        require_once __DIR__ . '/Preflight.php'; require_once __DIR__ . '/OutsideServices.php';
        $out = [];
        foreach ($keys as $k) {
            if (!isset(Preflight::NEEDS[$k])) continue;
            $n = Preflight::NEEDS[$k]; $line = $res['nd:' . $k] ?? null; $src = [];
            if ($line && preg_match_all('/(?:^|[.;] )(\d+)\. (.+?): (.+?)(?=; \d+\. |\.$|\. No backup)/u', (string) ($line['said'] ?? ''), $m, PREG_SET_ORDER))
                foreach ($m as $x) { $w = trim($x[3]); $src[] = [$x[2], $w, $w === 'works' || (str_starts_with($x[2], 'Ours') && !preg_match('/stale|not |no rows|missing/i', $w))]; }
            // the need's own verdict decides: none works when it's red (or amber with nothing working); the sources before the
            // first that works don't; the one it names works
            if ($src && $line) {
                $said = (string) ($line['said'] ?? ''); $first = null;
                if (str_starts_with($said, 'Answered by its first source')) $first = 0;
                elseif (preg_match('/^Running on a backup \(source (\d+)\)/', $said, $fm)) $first = (int) $fm[1] - 1;
                foreach ($src as $i => $_) if ($first === null || $i < $first) $src[$i][2] = false; elseif ($i === $first) $src[$i][2] = true;
            }
            if (!$src) foreach ($n[3] as $sr) $src[] = [$sr[0] === 'svc' ? (string) (OutsideServices::COMPANIES[$sr[1]][0] ?? $sr[1]) : (string) ($sr[2] ?? $sr[1]), 'not tested yet', null];
            $out[$k] = [preg_replace('/ \((W[^)]*|the site itself)\)$/', '', (string) $n[1]), (string) ($line['s'] ?? 'new'), $src, (string) $n[4], (bool) $n[5], (int) ($line['at'] ?? 0)];
        }
        return $out;
    }

    /** The three companies whose red line holds everything: key => [name, state]. */
    public static function core(array $res): array
    {
        $out = [];
        foreach (['bluehost' => 'Bluehost', 'railway' => 'Railway', 'github' => 'GitHub'] as $k => $n) $out[$k] = [$n, (string) ($res['svc:' . $k]['s'] ?? 'new')];
        return $out;
    }

    /** The needs that use a company: their names. */
    public static function usedBy(string $svc): array
    {
        require_once __DIR__ . '/Preflight.php';
        $out = [];
        foreach (Preflight::NEEDS as $n) foreach ($n[3] as $sr) if ($sr[0] === 'svc' && $sr[1] === $svc) { $out[] = (string) $n[1]; break; }
        return $out;
    }

    /** A query timing as the board shows it: [what, last ms (null: not run), limit, state, at]. */
    public static function timings(PDO $pdo, array $res): array
    {
        require_once __DIR__ . '/Preflight.php'; require_once __DIR__ . '/Thresholds.php';
        $out = [];
        foreach (Preflight::TIMINGS as $k => [$what, $gate]) {
            $r = $res['ms:' . $k] ?? null; $h = (array) ($r['extra']['ms'] ?? []);
            $lim = 0; try { $lim = (int) Thresholds::get($pdo, $gate); } catch (Throwable $x) {}
            $out[$k] = [$what, $h ? (int) end($h) : null, $lim, (string) ($r['s'] ?? 'new'), (int) ($r['at'] ?? 0)];
        }
        // Pulse gathering its rows, as staff open it (ms:pulse, 2026-11-19): its usual time, the middle of its last 20 opens
        try { require_once __DIR__ . '/ControlSensor.php'; [$pm] = ControlSensor::pulseUsual($pdo); $pl = ControlSensor::pulseLimit($pdo);
            $pat = (int) strtotime((string) ($pdo->query("SELECT MAX(at) FROM control_readings WHERE proc = 'site:pulse' AND reading = 'gather_ms'")->fetchColumn() ?: '') . ' UTC');
            $out['pulse'] = ['Pulse gathering its rows (as staff open it; the middle of its last 20 opens)', $pm, $pl, $pm === null ? 'new' : ($pm > $pl ? 'bad' : 'ok'), $pm === null ? 0 : $pat]; } catch (Throwable $x) {}
        return $out;
    }

    /**
     * The latest runs, read for what's wrong now: 'bad' (the latest call answered a server error, the latest run of a job
     * stopped, the latest run of a task failed; held runs aren't failures) and 'some' (failures today, the latest fine).
     * Each in plain words with its error and since when.
     */
    public static function latest(PDO $pdo, array $endpoints, array $jobs, array $tasks): array
    {
        $bad = $some = []; $badTasks = [];   // $badTasks: index in $bad => the task whose last runs failed
        try { if ($endpoints) { $in = implode(',', array_map(fn ($x) => $pdo->quote($x), $endpoints));
            foreach ($endpoints as $ep) {
                $st = $pdo->prepare("SELECT code, at, reasons FROM control_ledger WHERE endpoint = ? AND at >= UTC_TIMESTAMP() - INTERVAL 1 DAY ORDER BY id DESC LIMIT 50"); $st->execute([$ep]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) continue;
                $run = 0; foreach ($rows as $r) { if ((int) $r['code'] >= 500) $run++; else break; }
                $errs = count(array_filter($rows, fn ($r) => (int) $r['code'] >= 500));
                $why = self::sentence((string) preg_replace('/^answered \d+: /', '', (string) ($rows[0]['reasons'] ?? '')));
                if ($run) $bad[] = 'the site\'s /api/' . $ep . ' answers a server error (' . ($run === count($rows) ? ($run === 1 ? 'its only call' : 'every call') : ($run === 1 ? 'the last call' : 'the last ' . $run . ' calls')) . ' since ' . gmdate('H:i', (int) strtotime($rows[$run - 1]['at'] . ' UTC')) . ' UTC)' . ($why !== '' ? ': ' . $why : '');
                elseif ($errs) $some[] = $errs . ' of ' . count($rows) . ' calls to /api/' . $ep . ' failed today, the latest fine';
            } } } catch (Throwable $x) {}
        try { if ($jobs) { $t = Database::t('job_runs');
            foreach ($jobs as $j) { $st = $pdo->prepare("SELECT ok, note FROM $t WHERE job = ? AND started_at > NOW() - INTERVAL 1 DAY ORDER BY id DESC LIMIT 50"); $st->execute([$j]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) continue; $failed = count(array_filter($rows, fn ($r) => $r['ok'] !== null && (int) $r['ok'] === 0));
                if ($rows[0]['ok'] !== null && (int) $rows[0]['ok'] === 0) $bad[] = 'the job ' . $j . ' stopped on its last run' . ((string) $rows[0]['note'] !== '' ? ': ' . self::sentence((string) $rows[0]['note']) : '');
                elseif ($failed) $some[] = $failed . ' of ' . count($rows) . ' runs of ' . $j . ' stopped today, the latest fine'; } } } catch (Throwable $x) {}
        try { if ($tasks) { foreach ($tasks as $tk) {
                $st = $pdo->prepare("SELECT outcome, why FROM crawl_run_items WHERE task = ? AND at >= NOW() - INTERVAL 1 DAY AND outcome <> 'held' ORDER BY id DESC LIMIT 50"); $st->execute([$tk]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) continue;
                $run = 0; foreach ($rows as $r) { if ($r['outcome'] === 'failed') $run++; else break; }
                $failed = count(array_filter($rows, fn ($r) => $r['outcome'] === 'failed'));
                if ($run) { $bad[] = $tk . ' failed ' . ($run === count($rows) ? 'on every run today' : 'on its last ' . ($run === 1 ? 'run' : $run . ' runs')) . ((string) $rows[0]['why'] !== '' ? ': ' . self::sentence((string) $rows[0]['why']) : ''); $badTasks[count($bad) - 1] = $tk; }
                elseif ($failed) $some[] = $failed . ' of ' . count($rows) . ' ' . $tk . ' runs failed today, the latest ok'; } } } catch (Throwable $x) {}
        return ['bad' => $bad, 'some' => $some, 'tasks' => $badTasks];
    }

    /**
     * A workflow's failing tasks that a backup covers (decisions/0175 §3: "The workflow that uses it reroutes to a backup
     * service where one is named… It's paused by the system only when it can't do its work without that component"): each
     * failing task whose services are all sources of this workflow's needs, every one of those needs still answered by
     * another source. Returns task => [need name, the source answering it], or null when any failing task isn't covered.
     */
    public static function onBackup(array $res, array $needKeys, array $tasks): ?array
    {
        require_once __DIR__ . '/OutsideServices.php';
        if (!$tasks) return null;
        $needs = self::needs($res, $needKeys); $out = [];
        foreach ($tasks as $tk) {
            $svcs = []; foreach (OutsideServices::CONTROLS as $svc => $c) if (in_array($tk, (array) ($c['tasks'] ?? []), true)) $svcs[] = $svc;
            if (!$svcs) return null;
            $cover = null;
            foreach ($svcs as $svc) {
                $found = null;
                foreach ($needKeys as $nk) {
                    if (!isset($needs[$nk], Preflight::NEEDS[$nk])) continue;
                    $uses = array_filter(Preflight::NEEDS[$nk][3], fn ($sr) => $sr[0] === 'svc' && $sr[1] === $svc);
                    if (!$uses || !in_array($needs[$nk][1], ['ok', 'warn'], true)) continue;
                    $own = (string) (OutsideServices::COMPANIES[$svc][0] ?? $svc);
                    foreach ($needs[$nk][2] as [$sn, , $works]) if ($works && $sn !== $own) { $found = [$needs[$nk][0], (string) $sn]; break 2; }
                }
                if ($found === null) return null;
                $cover = $found;
            }
            $out[$tk] = $cover;
        }
        return $out;
    }

    /** The first whole sentence (never cut off with "…"); a long one is kept whole up to 300 characters. */
    public static function sentence(string $t): string
    {
        $t = trim((string) preg_replace('/^What holds it:\s*/u', '', $t));
        if (preg_match('/^(.{12,300}?[.!?])(\s|$)/u', $t, $m)) return rtrim($m[1], '.');
        return rtrim(mb_strlen($t) <= 300 ? $t : (string) preg_replace('/[,;:]?\s+\S*$/u', '', mb_substr($t, 0, 300)), '.');
    }

    /** The records a set of endpoints touched lately: [kind, id, what, at], newest first. */
    public static function records(PDO $pdo, array $endpoints, int $n = 20): array
    {
        if (!$endpoints) return [];
        try {
            $in = implode(',', array_map(fn ($e) => $pdo->quote($e), $endpoints));
            return $pdo->query("SELECT r.kind, r.ref_id, r.what, r.at FROM control_records r JOIN control_ledger l ON l.id = r.ledger_id WHERE l.endpoint IN ($in) ORDER BY r.id DESC LIMIT " . max(1, min(100, $n)))->fetchAll(PDO::FETCH_NUM);
        } catch (Throwable $x) { return []; }
    }

    /** The crawler's last runs with what each brought in: [run row, calls, records]. */
    public static function runs(PDO $pdo, int $n = 5): array
    {
        $out = [];
        try {
            require_once __DIR__ . '/ControlLedger.php';
            foreach ($pdo->query('SELECT id, started_at, ended_at, status, why FROM crawl_runs ORDER BY id DESC LIMIT ' . max(1, min(20, $n)))->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $f = ControlLedger::forRun($pdo, (int) $r['id']);
                $out[] = [$r, $f['calls'], array_slice($f['records'], 0, 30)];
            }
        } catch (Throwable $x) {}
        return $out;
    }

    // ---- controls ----------------------------------------------------------------------------------------------------

    /** Start one process: its check first, then its switch, only when the check isn't red (the interlock). */
    public static function startOne(PDO $pdo, string $id, int $uid, string $who): string
    {
        require_once __DIR__ . '/Preflight.php';
        $pid = $id === 't:W14' ? 'wf:W14' : $id;
        Preflight::refreshFor($pdo, $pid, time() + 40, self::BY);
        $r = Preflight::run($pdo, $pid, self::BY);
        if ($r['s'] === 'bad') throw new RuntimeException('Not started: its check is red. ' . mb_substr((string) $r['said'], 0, 400));
        if (str_starts_with($id, 'wf:')) { Automation::setWorkflow($pdo, substr($id, 3), false, 'Started from ' . self::BY, $uid, $who); self::logged($pdo, 'start', $id, substr($id, 3) . ' started: its check passed', $who); return substr($id, 3) . ' is on: its check passed.'; }
        if (str_starts_with($id, 't:')) {
            require_once __DIR__ . '/SystemStart.php';
            $d = SystemStart::TASKS[substr($id, 2)] ?? null;
            if (!$d || !$d[4]) throw new RuntimeException('It has no switch of its own: it comes on with its workflow.');
            foreach ($d[4] as $tk) Automation::setTask($pdo, $tk, true, 'Started from ' . self::BY, $uid, $who);
            self::logged($pdo, 'start', $id, $d[0] . ' started: its check passed', $who);
            return $d[0] . ' is on: its check passed.';
        }
        if (str_starts_with($id, 'svc:')) {   // Activate (decisions/0173 §2): its check above, then 0163 §10's switch-on check, as Automation's Resume
            require_once __DIR__ . '/OutsideServices.php'; $svc = substr($id, 4); $name = OutsideServices::COMPANIES[$svc][0] ?? $svc;
            require_once __DIR__ . '/Operations.php';
            $red = Operations::blocking($pdo, $svc);
            if ($red) throw new RuntimeException($name . ' stays paused: the switch-on check has ' . count($red) . ' red line' . (count($red) === 1 ? '' : 's') . ' for it, first "' . $red[0]['line'] . '" (' . $red[0]['why'] . ').');
            Automation::set($pdo, 'service', $svc, false, 'Activated from ' . self::BY, $uid, $who);
            self::logged($pdo, 'start', $id, $name . ' activated: its check passed', $who);
            return $name . ' is active: its check passed, and its workflows can call it.';
        }
        throw new RuntimeException('That can\'t be started from here.');
    }

    /** Shut one process down in order: its crawler tasks, then the workflow. What's running finishes or goes back unchanged. */
    public static function stopOne(PDO $pdo, string $id, int $uid, string $who): string
    {
        $why = 'Shut down from ' . self::BY . ' by ' . ($who !== '' ? $who : 'a director');
        if (str_starts_with($id, 'wf:')) {
            $wf = substr($id, 3);
            foreach (ControlRegister::of($pdo, $wf)['tasks'] as $tk) { try { Automation::setTask($pdo, $tk, false, $why, $uid, $who); } catch (Throwable $x) {} }
            Automation::setWorkflow($pdo, $wf, true, $why, $uid, $who);
            self::logged($pdo, 'stop', $id, $wf . ' shut down: its tasks first, then the workflow', $who, $why);
            return $wf . ' is shut down: its tasks first, then the workflow. A crawler run going now finishes its task; nothing is lost.';
        }
        if (str_starts_with($id, 't:')) {
            require_once __DIR__ . '/SystemStart.php';
            $d = SystemStart::TASKS[substr($id, 2)] ?? null;
            if (!$d || !$d[4]) throw new RuntimeException('It has no switch of its own: shut down its workflow.');
            foreach ($d[4] as $tk) Automation::setTask($pdo, $tk, false, $why, $uid, $who);
            self::logged($pdo, 'stop', $id, $d[0] . ' shut down', $who, $why);
            return $d[0] . ' is shut down.';
        }
        if (str_starts_with($id, 'svc:')) {   // Pause (decisions/0173 §2): nothing of ours calls it until it's activated; its key stays
            require_once __DIR__ . '/OutsideServices.php'; $svc = substr($id, 4); $name = OutsideServices::COMPANIES[$svc][0] ?? $svc;
            $why = 'Paused from ' . self::BY . ' by ' . ($who !== '' ? $who : 'a director');
            Automation::set($pdo, 'service', $svc, true, $why, $uid, $who);
            self::logged($pdo, 'stop', $id, $name . ' paused', $who, $why);
            return $name . ' is paused: nothing of ours calls it until it\'s activated again. Its key stays.';
        }
        throw new RuntimeException('That can\'t be shut down from here.');
    }

    public static function shutdownState(PDO $pdo): array
    {
        try { require_once __DIR__ . '/SearchIndex.php'; $j = json_decode((string) SearchIndex::state($pdo, self::KEY), true); return is_array($j) ? $j : []; }
        catch (Throwable $x) { return []; }
    }

    private static function saveShutdown(PDO $pdo, array $v): void
    {
        require_once __DIR__ . '/SearchIndex.php';
        SearchIndex::setState($pdo, self::KEY, json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * The whole system shut down in order (decisions/0171 §2.4): the new tasks, then the workflows in reverse start-up order,
     * then the master switch, then the crawler's schedule on Railway. Stays "stopping" until no crawler run that started
     * before it is still going, then "stopped, safe", saying what it left. Pause everything stays the emergency stop.
     */
    /** One line in the logbook (ControlLog, 0171 §10, release 5a). "the Control Board" as who is the system. Never throws. */
    private static function logged(PDO $pdo, string $kind, string $proc, string $what, string $who, string $why = ''): void
    {
        try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, $kind, $proc, $what, in_array($who, ['', self::BY], true) ? 'the system' : $who, $why); } catch (Throwable $x) {}
    }

    public static function shutDown(PDO $pdo, int $uid, string $who): string
    {
        require_once __DIR__ . '/SystemStart.php';
        require_once __DIR__ . '/Staging.php';
        if (Staging::here()) throw new RuntimeException('Nothing runs on staging to shut down.');
        $why = 'Shut down in order from ' . self::BY . ' by ' . ($who !== '' ? $who : 'a director');
        $done = [];
        foreach (array_reverse(array_keys(SystemStart::TASKS)) as $t) {
            if ($t === 'W14') continue;
            foreach (SystemStart::TASKS[$t][4] as $tk) { try { Automation::setTask($pdo, $tk, false, $why, $uid, $who); $done[] = $tk; } catch (Throwable $x) {} }
        }
        foreach (array_reverse(array_merge(SystemStart::ORDER, ['W14'])) as $wf) {
            if (Automation::wfOff($pdo, $wf) !== null) continue;
            try { Automation::setWorkflow($pdo, $wf, true, $why, $uid, $who); $done[] = $wf; } catch (Throwable $x) {}
        }
        Automation::set($pdo, 'master', 'all', true, $why, $uid, $who);
        [, $rw] = SystemStart::railwayKeys() ? SystemStart::railwaySchedule($pdo, null) : [false, 'Railway\'s keys aren\'t in .env, so its schedule wasn\'t touched (each run ends at once while the master is off).'];
        self::saveShutdown($pdo, ['state' => 'stopping', 'at' => time(), 'by' => $who, 'stopped' => $done, 'railway' => $rw]);
        self::logged($pdo, 'stop', '', 'The whole system shut down in order: ' . count($done) . ' switched off, then the master', $who);
        return 'Shutting down in order: ' . count($done) . ' switched off, then the master. ' . $rw . ' It reads "stopped, safe" once no crawler run is still going.';
    }

    /** Moves "stopping" to "stopped, safe" once nothing that started before the shut-down is still running. */
    public static function settle(PDO $pdo): array
    {
        $sd = self::shutdownState($pdo);
        if (($sd['state'] ?? '') !== 'stopping') return $sd;
        $going = 0;
        try { $going = (int) $pdo->query("SELECT COUNT(*) FROM crawl_runs WHERE status = 'running' AND started_at >= NOW() - INTERVAL 40 MINUTE")->fetchColumn(); } catch (Throwable $x) {}
        if ($going > 0) return $sd;
        $left = [];
        try { $left[] = number_format((int) $pdo->query("SELECT COUNT(*) FROM crawl_requests WHERE status IN ('queued', 'leased')")->fetchColumn()) . ' crawler requests kept for the next start'; } catch (Throwable $x) {}
        try { $left[] = number_format((int) $pdo->query("SELECT COUNT(*) FROM " . Database::t('crawler_raw_index') . " WHERE status = 'staged'")->fetchColumn()) . ' finds waiting in Review'; } catch (Throwable $x) {}
        $sd['state'] = 'safe'; $sd['safe_at'] = time();
        $sd['left'] = 'nothing half-written (each write is saved as it\'s made); ' . ($left ? implode('; ', $left) . '; ' : '') . 'every switch off, with who and why';
        self::saveShutdown($pdo, $sd);
        return $sd;
    }

    /** Start the system after a shut-down clears it (Start's own steps take it from there). */
    public static function cleared(PDO $pdo): void
    {
        try { require_once __DIR__ . '/SearchIndex.php'; $pdo->prepare('DELETE FROM search_index_state WHERE k = ?')->execute([self::KEY]); } catch (Throwable $x) {}
    }
}
