<?php
// app/lib/ControlEngine.php — the System Control Board's one engine (handoffs/control-board-plan.md step 3; the state model,
// handoffs/control-board-plan/step3/02-state-model.md §1 P1–P12, §5). Every caller reports what happened; only the engine
// decides a part's component, its alarm's level, its tries, what it holds and the words its state gives.
//   - The component (P2): a fault belongs to the part that failed, read from the evidence, never from the raiser's label.
//   - The diagnostic clock (P3, 02 §0a ruling 2): the system's tries are its own checks of the failed component at about
//     5, 15 and 35 minutes after the first fault, inside the hour. A scheduled run is never a try, and a burst inside one
//     run is one fault. A part with no check of its own (a task or job failing in its own code) has its next run as its
//     check: its next failure is a trip that holds nothing (ruling 3).
//   - The hold (P5, P6, §6): a trip places hold records (system_pauses) as narrow as its cause, never a switch: an
//     endpoint holds the tasks that call it; an outside source the tasks that use it, unless the need has a working
//     later source; the crawler down holds all gathering; a job, a task's own code or a workflow's roll-up hold nothing.
//   - No close without a real check (P4): Check and start again calls the component as the failure did.
//   - Words from state (P9, ruling 5): "Paused by the system (<what's held>): still trying, n of 3, next at <time>";
//     "Down: the system is still trying" when nothing could be held.
//   - Every trip has its Incident Report (P12; ControlIncidents).
// ControlAlarms is its store: raise(), clear(), resolve() and tick() call in here. Never throws to a caller.
declare(strict_types=1);


final class ControlEngine
{
    /** The system's tries: minutes after the first fault (02 §0a ruling 2, des: the back-off's spacing, inside the hour). */
    public const TRY_AT = [5, 15, 35];
    /** The hour every try sits inside ("Keep the hour", 0171). */
    public const HOUR = 60;
    /** A trip's checks after its tries, every so many minutes (§7's example: a check, the next 30 minutes on). */
    public const AFTER_MIN = 30;

    // ---- the component ---------------------------------------------------------------------------------------------

    /** The part that failed, from the alarm's key and process (P2): ep:, svc:, nd:, task:, job:, wk:, wf:, site:, or its proc. */
    public static function componentOf(PDO $pdo, string $key, string $proc, string $reading = ''): string
    {
        if (preg_match('#^ep:([a-z0-9_/]+):#', $key, $m)) return 'ep:' . $m[1];
        if (preg_match('/^task:failed:(.+)$/', $key, $m)) return self::classifyTask($pdo, $m[1], $reading, []);
        if (preg_match('/^svc:issue:(\d+)$/', $key, $m)) {
            try { $st = $pdo->prepare('SELECT service FROM service_issues WHERE id = ?'); $st->execute([(int) $m[1]]); $s = (string) $st->fetchColumn(); if ($s !== '') return 'svc:' . $s; } catch (Throwable $x) {}
            return $proc;
        }
        if (preg_match('/^pf:(svc|nd|wf|t|site|rw|wk):(.+)$/', $key, $m)) return $m[1] . ':' . $m[2];   // t: a Start task's line (its services)
        if (preg_match('/^(?:job|jobslow):([a-z0-9_]+)/', $key, $m)) return 'job:' . $m[1];
        if (in_array($key, ['run:failed', 'run:starved', 'silent:run'], true)) return 'wk:crawler';
        if ($key === 'silent:w0') return 'wk:w0-indexer';
        if (preg_match('/^ops:cap:([a-z0-9_]+)$/', $key, $m)) return 'svc:' . $m[1];
        return $proc;
    }

    /**
     * A task's failure, read from what its run reported (P2; IR-0001): the host that failed in its calls. A failure on an
     * outside service's host is that service's; one on our own site's host, or a read timeout with no outside host
     * failing in a task that calls our endpoint, is our endpoint's; else the task's own code.
     * $calls: {host: [calls, failures, last error]} as the worker reports them. Returns the component.
     */
    public static function classifyTask(PDO $pdo, string $task, string $why, array $calls): string
    {
        $failing = [];
        foreach ($calls as $h => $c) if (is_array($c) && (int) ($c[1] ?? 0) > 0) $failing[strtolower((string) $h)] = (string) ($c[2] ?? '');
        $ours = strtolower((string) parse_url((string) (getenv('APP_URL') ?: 'https://traversence.com'), PHP_URL_HOST));
        try {
            require_once __DIR__ . '/OutsideServices.php';
            foreach (array_keys($failing) as $h) { if ($h === $ours || $h === 'www.' . $ours) continue; $s = OutsideServices::serviceOfHost($h); if ($s !== '') return 'svc:' . $s; }
        } catch (Throwable $x) {}
        $ep = self::endpointOfTask($task);
        if ($ep !== '' && (isset($failing[$ours]) || isset($failing['www.' . $ours]) || ($failing === [] && preg_match('/timed? ?out|timeout/i', $why)))) return 'ep:' . $ep;
        return 'task:' . $task;
    }

    /** The endpoint of ours a crawler task sends its work to ('' if none): ControlRegister::ENDPOINTS. */
    public static function endpointOfTask(string $task): string
    {
        try { require_once __DIR__ . '/ControlRegister.php'; foreach (ControlRegister::ENDPOINTS as $e => $d) if (in_array($task, (array) $d[1], true)) return (string) $e; } catch (Throwable $x) {}
        return '';
    }

    // ---- the check -------------------------------------------------------------------------------------------------

    /** Whether a component has a check of its own the system can run (§2.7); a task's or job's is its next run. */
    public static function checkable(PDO $pdo, string $comp): bool
    {
        if (str_starts_with($comp, 'ep:') || str_starts_with($comp, 'wk:')) return true;
        if (preg_match('/^(svc|nd|site|rw|t):(.+)$/', $comp, $m)) {
            try { require_once __DIR__ . '/Preflight.php'; return isset(Preflight::lines($pdo)[$comp]); } catch (Throwable $x) { return false; }
        }
        return false;
    }

    /** Tests' stand-in for the checks: fn(string $comp): array|null (null: run the real one). */
    public static $checker = null;

    /**
     * Runs a component's own check, past its own hold for that one request (P7), and records what was called (P4):
     * ['pass' => bool, 'proof' => words, 'ms' => int]. A component with no check of its own: ['pass' => null].
     */
    public static function check(PDO $pdo, string $comp, string $by = 'the system'): array
    {
        if (is_callable(self::$checker) && ($r = (self::$checker)($comp)) !== null) return $r + ['pass' => null, 'proof' => '', 'ms' => 0];
        $t0 = microtime(true);
        try {
            if (str_starts_with($comp, 'ep:')) return self::checkEndpoint($pdo, substr($comp, 3));
            if ($comp === 'wk:crawler') {
                $r = $pdo->query("SELECT id, status, started_at, ended_at FROM crawl_runs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $ok = $r && $r['ended_at'] !== null && $r['status'] !== 'failed' && strtotime((string) $r['started_at']) > time() - 40 * 60;
                return ['pass' => (bool) $ok, 'ms' => 0, 'proof' => $r ? 'The crawler\'s last run (#' . (int) $r['id'] . ') ' . ($r['ended_at'] === null ? 'hasn\'t reported yet' : 'ended ' . $r['status']) . '.' : 'The crawler has no run yet.'];
            }
            if ($comp === 'wk:w0-indexer') {
                require_once __DIR__ . '/crawler/Frontier.php'; $beat = (int) (Frontier::lastBeat($pdo)['at'] ?? 0);
                return ['pass' => $beat > time() - 15 * 60, 'ms' => 0, 'proof' => $beat ? 'W0 Indexer last checked in at ' . gmdate('H:i', $beat) . ' UTC.' : 'W0 Indexer hasn\'t checked in.'];
            }
            if ($comp === 'wk:video-worker') {
                $at = (string) $pdo->query("SELECT MAX(at) FROM control_ledger WHERE endpoint = 'video/jobs' AND code < 500")->fetchColumn();
                return ['pass' => $at !== '' && strtotime($at . ' UTC') > time() - 15 * 60, 'ms' => 0, 'proof' => $at !== '' ? 'The video worker last asked for work at ' . substr($at, 11, 5) . ' UTC.' : 'The video worker hasn\'t asked for work.'];
            }
            if (self::checkable($pdo, $comp)) {
                require_once __DIR__ . '/Preflight.php';
                // a person's check goes past its own hold (P7); the system's own try past a back-off's wait only (principle 1)
                [$was, $wasSys] = [Preflight::$now, Preflight::$systemTry]; Preflight::$now = $by !== 'the system'; Preflight::$systemTry = $by === 'the system';
                try { $r = Preflight::run($pdo, $comp, $by); } finally { Preflight::$now = $was; Preflight::$systemTry = $wasSys; }
                $s = (string) ($r['s'] ?? '');
                return ['pass' => $s !== 'bad' && $s !== '', 'ms' => (int) round((microtime(true) - $t0) * 1000), 'proof' => mb_substr(trim((string) ($r['said'] ?? '')), 0, 240)];
            }
        } catch (Throwable $x) { return ['pass' => false, 'ms' => (int) round((microtime(true) - $t0) * 1000), 'proof' => 'Its check failed to run: ' . mb_substr($x->getMessage(), 0, 200)]; }
        return ['pass' => null, 'ms' => 0, 'proof' => ''];
    }

    /** Our endpoint's check: an authenticated no-work request (?check=1) inside its speed gate (§2.7). */
    private static function checkEndpoint(PDO $pdo, string $ep): array
    {
        $base = rtrim((string) (getenv('APP_URL') ?: 'https://traversence.com'), '/');
        $tok = str_starts_with($ep, 'video/') ? (string) getenv('VIDEO_WORKER_TOKEN') : (string) getenv('CRAWLER_API_TOKEN');
        $gate = self::gateMs($pdo, $ep);
        $t0 = microtime(true);
        $ch = curl_init($base . '/api/' . $ep . '.php?check=1');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => (int) ceil($gate / 1000) + 5, CURLOPT_HTTPHEADER => ['X-Crawler-Token: ' . $tok, 'Authorization: Bearer ' . $tok, 'User-Agent: traversence-control-engine']]);
        $body = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch); curl_close($ch);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $pass = $code >= 200 && $code < 300 && $ms <= $gate;
        $proof = '/api/' . $ep . ' ' . ($code ? 'answered ' . $code : 'didn\'t answer' . ($err !== '' ? ' (' . mb_substr($err, 0, 80) . ')' : '')) . ' in ' . number_format($ms) . ' ms' . ($code >= 200 && $code < 300 && !$pass ? ', over its gate of ' . number_format($gate) . ' ms' : '') . '.';
        return ['pass' => $pass, 'ms' => $ms, 'proof' => $proof];
    }

    /** An endpoint's speed gate: its usual time (the middle of its last 50 good calls) × control_slow_factor, 1 s to 120 s (the worker waits 120 s). */
    public static function gateMs(PDO $pdo, string $ep): int
    {
        $usual = 0;
        try { $st = $pdo->prepare('SELECT ms FROM control_ledger WHERE endpoint = ? AND code < 500 ORDER BY id DESC LIMIT 50'); $st->execute([$ep]); $d = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)); if (count($d) >= 5) { sort($d); $usual = $d[intdiv(count($d), 2)]; } } catch (Throwable $x) {}
        $f = 3.0; try { require_once __DIR__ . '/Thresholds.php'; $f = (float) (Thresholds::get($pdo, 'control_slow_factor') ?? 3); } catch (Throwable $x) {}
        return (int) max(1000, min(120000, $usual * $f));
    }

    // ---- the level and the tries -----------------------------------------------------------------------------------

    /**
     * The level a raise gets now (P3, P8; ruling 3), for raise() to write: ['level', 'early' (a critical part's trip at once,
     * still trying), 'escalate', 'next' (its next try, UTC 'Y-m-d H:i:s' or null)]. $o: the open alarm, or null. Only a raise
     * that asks for a trip with the system trying first ($tryFirst) is read here; any other keeps its level.
     */
    public static function decide(PDO $pdo, ?array $o, string $comp, string $level, string $kind, bool $tryFirst, bool $event, string $duty): array
    {
        $out = ['level' => $level, 'early' => false, 'escalate' => false, 'next' => $o['next_try_at'] ?? null];
        if ($level !== 'trip' || !$tryFirst || in_array($kind, ['held', 'limit'], true)) return $out;
        $first = $o ? (int) strtotime($o['first_at'] . ' UTC') : time();
        $tripped = $o && $o['level'] === 'trip' && !empty($o['tripped_at']);
        if ($tripped && empty($o['retrying_at'])) return ['level' => 'trip'] + $out;   // already a trip: stays one
        if (self::checkable($pdo, $comp)) {
            // the engine's own checks are the tries: a repeat report inside them is the same fault, not a try (P3)
            if ($o && !empty($o['retrying_at'])) return ['level' => 'trip'] + $out;   // a critical part's trip, still trying: tick() escalates
            $next = $o['next_try_at'] ?? gmdate('Y-m-d H:i:s', $first + self::TRY_AT[0] * 60);
            if ($duty !== '') return ['level' => 'trip', 'early' => true, 'escalate' => false, 'next' => $next];
            return ['level' => 'warning', 'early' => false, 'escalate' => false, 'next' => $next];
        }
        // no check of its own: its next run is the check (ruling 3). The first failure is a warning; its next run failing
        // too is a trip that holds nothing, and its next scheduled run still goes ahead. A critical part trips at once.
        if ($duty !== '') return ['level' => 'trip', 'early' => !$tripped, 'escalate' => $o && !empty($o['retrying_at']) && $event, 'next' => null];
        if ($o && $event) return ['level' => 'trip', 'early' => false, 'escalate' => false, 'next' => null];
        return ['level' => 'warning', 'early' => false, 'escalate' => false, 'next' => null];
    }

    /** Its try count and next try for the words: [n done, of, next unix or 0]. */
    public static function tries(array $a): array
    {
        $n = (int) ($a['try_n'] ?? 0);
        return [$n, count(self::TRY_AT), !empty($a['next_try_at']) ? (int) strtotime($a['next_try_at'] . ' UTC') : 0];
    }

    /**
     * The watch's tries (tick, every 5 minutes): each open alarm with a try due is checked on its component. A pass closes
     * it (cleared, or Recovered for a trip); a fail counts a try; the third failed try, or the hour running out, is a trip
     * (or the Escalation Notice for a critical part's trip). A refusal or block is never tried by the system (principle 1).
     * Returns what it did.
     */
    public static function tick(PDO $pdo): array
    {
        $did = [];
        try {
            require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ensure($pdo);
            $due = $pdo->query("SELECT * FROM control_alarms WHERE cleared_at IS NULL AND next_try_at IS NOT NULL AND next_try_at <= UTC_TIMESTAMP() ORDER BY id LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($due as $a) {
                $comp = (string) ($a['component'] ?: self::componentOf($pdo, (string) $a['akey'], (string) $a['proc']));
                $r = self::check($pdo, $comp);
                if ($r['pass'] === null) { $pdo->prepare('UPDATE control_alarms SET next_try_at = NULL WHERE id = ?')->execute([(int) $a['id']]); continue; }
                $n = (int) $a['try_n'] + 1;
                $at = gmdate('H:i') . ' UTC';
                if ($r['pass']) {
                    $pdo->prepare('UPDATE control_alarms SET try_n = ?, next_try_at = NULL WHERE id = ?')->execute([$n, (int) $a['id']]);
                    ControlAlarms::clear($pdo, (string) $a['akey'], 'Its check passed at ' . $at . ' (try ' . $n . ' of ' . count(self::TRY_AT) . '): ' . $r['proof']);
                    $did[] = 'cleared #' . (int) $a['id'];
                    continue;
                }
                $first = (int) strtotime($a['first_at'] . ' UTC');
                $out = $n >= count(self::TRY_AT) || time() >= $first + self::HOUR * 60;
                $next = $out ? null : gmdate('Y-m-d H:i:s', $first + self::TRY_AT[$n] * 60);
                $pdo->prepare('UPDATE control_alarms SET try_n = ?, next_try_at = ?, reading = ? WHERE id = ?')->execute([$n, $next, mb_substr('Try ' . $n . ' at ' . $at . ': ' . $r['proof'], 0, 255), (int) $a['id']]);
                if ($out) {
                    if (!($a['level'] === 'trip' && empty($a['retrying_at']))) { ControlAlarms::triesOut($pdo, (int) $a['id']); $did[] = ($a['level'] === 'trip' ? 'escalated #' : 'tripped #') . (int) $a['id']; }
                    // a trip keeps its checks after its tries (§7: "Checked 09:42 MST: still refused. Next check 10:12 MST"):
                    // every AFTER_MIN, so it closes as Recovered when its cause is fixed, with no one pressing anything
                    $pdo->prepare('UPDATE control_alarms SET next_try_at = UTC_TIMESTAMP() + INTERVAL ' . self::AFTER_MIN . ' MINUTE WHERE id = ? AND cleared_at IS NULL')->execute([(int) $a['id']]);
                }
            }
        } catch (Throwable $x) { error_log('control engine tick: ' . $x->getMessage()); }
        return $did;
    }

    // ---- the hold: the Incident Radius -----------------------------------------------------------------------------

    /**
     * What a trip on this alarm holds (§6), each ['pause', kind:ref, words] for system_pauses or ['issue', id, words] for an
     * outside service's own hold. Nothing is written here: ControlAlarms::stop places it.
     */
    public static function radius(PDO $pdo, array $a): array
    {
        $key = (string) $a['akey']; $proc = (string) $a['proc'];
        $comp = (string) (($a['component'] ?? '') ?: self::componentOf($pdo, $key, $proc));
        require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/ControlRegister.php';
        $out = [];
        // the crawler itself down: all gathering (its host's work)
        if (in_array($key, ['run:failed', 'silent:run'], true) || $comp === 'wk:crawler') { if (Automation::master($pdo) === null) $out[] = ['pause', 'master:all', 'All gathering']; return $out; }
        // our endpoint: the tasks that call it (D1: /api/crawl/loads holds only the bulk loads)
        if (str_starts_with($comp, 'ep:')) {
            $ep = substr($comp, 3); $tw = ControlRegister::taskWorkflows($pdo);
            foreach ((array) (ControlRegister::ENDPOINTS[$ep][1] ?? []) as $for) {
                if (str_starts_with((string) $for, '@')) continue;   // the crawler's own plan and report: its run, not a hold
                if (isset($tw[$for])) { if (Automation::taskOff($pdo, (string) $for) === null) $out[] = ['pause', 'task:' . $for, $for . ' (' . $tw[$for] . ')']; }
                elseif (preg_match('/^W\d+$/', (string) $for) && Automation::wfOff($pdo, (string) $for) === null) $out[] = ['pause', 'wf:' . $for, $for];
            }
            return $out;
        }
        // a task's own code, a job, a workflow's roll-up: nothing beyond its own work (P7, §2.2; ruling 3)
        if (str_starts_with($comp, 'task:') || str_starts_with($comp, 'wf:')) return [];
        if (str_starts_with($comp, 'job:')) {
            // a job that is a workflow's task holds only that task (D8: identity_recheck_tick is W2's recheck)
            try { require_once __DIR__ . '/SystemStart.php'; foreach (SystemStart::TASKS as $d) if (in_array(substr($comp, 4), (array) $d[3], true)) foreach ((array) $d[4] as $tk) if (Automation::taskOff($pdo, (string) $tk) === null) $out[] = ['pause', 'task:' . $tk, (string) $d[0]]; } catch (Throwable $x) {}
            return $out;
        }
        // an outside service's issue: its calls wait on its own check (its host hold)
        if (preg_match('/^svc:issue:(\d+)$/', $key, $m)) {
            require_once __DIR__ . '/ServiceHealth.php';
            if (ServiceHealth::holdForTrip($pdo, (int) $m[1])) $out[] = ['issue', $m[1], 'Calls to it'];
            return array_merge($out, self::sourceTasks($pdo, substr($comp, 4)));
        }
        if ($key === 'svc:osm:held') return $out;
        // out of budget: its automatic calls
        if (str_starts_with($key, 'ops:cap:') && str_starts_with($proc, 'svc:')) {
            $svc = substr($proc, 4);
            if (Automation::service($pdo, $svc) === null && !isset(Automation::NOT_PAUSABLE[$svc])) $out[] = ['pause', 'service:' . $svc, 'Its automatic calls (out of budget)'];
            return $out;
        }
        // a need with no working source: the tasks that call its services, in every workflow it names
        if (str_starts_with($comp, 'nd:')) {
            require_once __DIR__ . '/Preflight.php'; require_once __DIR__ . '/OutsideServices.php';
            $nd = Preflight::NEEDS[substr($comp, 3)] ?? null; $tw = ControlRegister::taskWorkflows($pdo); $tasks = [];
            if ($nd) foreach ((array) $nd[3] as $sr) if (($sr[0] ?? '') === 'svc') foreach ((array) (OutsideServices::CONTROLS[(string) $sr[1]]['tasks'] ?? []) as $tk) if (in_array($tw[$tk] ?? '', (array) $nd[2], true)) $tasks[$tk] = true;
            foreach (array_keys($tasks) as $tk) if (Automation::taskOff($pdo, $tk) === null) $out[] = ['pause', 'task:' . $tk, $tk . ' (' . ($tw[$tk] ?? '') . ')'];
            return $out;
        }
        // an outside source (a pre-flight service line): the tasks that use it and have no working later source
        if (str_starts_with($comp, 'svc:')) return self::sourceTasks($pdo, substr($comp, 4));
        // W0 Indexer's own trips: W0
        if ($comp === 'wk:w0-indexer' || $proc === 'wk:w0-indexer') { if (Automation::wfOff($pdo, 'W0') === null) $out[] = ['pause', 'wf:W0', 'W0 (its tasks wait with it)']; return $out; }
        // a Start task's line (t:<x>): its crawler tasks
        if (str_starts_with($comp, 't:') || str_starts_with($proc, 't:')) {
            require_once __DIR__ . '/SystemStart.php'; $d = SystemStart::TASKS[substr(str_starts_with($comp, 't:') ? $comp : $proc, 2)] ?? null;
            if ($d && $d[4]) foreach ($d[4] as $tk) $out[] = ['pause', 'task:' . $tk, (string) $d[0]];
            return $out;
        }
        return $out;
    }

    /**
     * The crawler tasks that use an outside service (OutsideServices::CONTROLS) and wait on it: those whose need has no
     * other working source (P6: "each task reroutes to its next working source, or waits"). A need's other source works
     * when its pre-flight line isn't red.
     */
    public static function sourceTasks(PDO $pdo, string $svc): array
    {
        $out = [];
        try {
            require_once __DIR__ . '/OutsideServices.php'; require_once __DIR__ . '/Preflight.php'; require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/ControlRegister.php';
            $tasks = (array) (OutsideServices::CONTROLS[$svc]['tasks'] ?? []); if (!$tasks) return [];
            $tw = ControlRegister::taskWorkflows($pdo); $res = Preflight::results($pdo);
            foreach ($tasks as $tk) {
                if (!isset($tw[$tk]) || Automation::taskOff($pdo, (string) $tk) !== null) continue;
                if (self::backupFor($pdo, $svc, (string) $tw[$tk], $res) !== '') continue;   // on a backup: not held
                $out[] = ['pause', 'task:' . $tk, $tk . ' (' . $tw[$tk] . ')'];
            }
        } catch (Throwable $x) { error_log('control engine sourceTasks: ' . $x->getMessage()); }
        return $out;
    }

    /** The working later source a workflow's need has besides this service ('' if none): the first source after it whose line isn't red. */
    public static function backupFor(PDO $pdo, string $svc, string $wf, ?array $res = null): string
    {
        require_once __DIR__ . '/Preflight.php';
        $res = $res ?? Preflight::results($pdo);
        foreach (Preflight::NEEDS as $nd) {
            if (!in_array($wf, (array) $nd[2], true)) continue;
            $srcs = (array) $nd[3]; $seen = false;
            foreach ($srcs as $sr) {
                if (($sr[0] ?? '') === 'svc' && (string) ($sr[1] ?? '') === $svc) { $seen = true; continue; }
                if (!$seen) continue;
                if (($sr[0] ?? '') === 'paid') continue;   // a paid backup only under a director's override (R61)
                $line = ($sr[0] ?? '') === 'svc' ? 'svc:' . (string) $sr[1] : (($sr[0] ?? '') === 'line' ? (string) $sr[1] : '');
                if ($line === '' || (($res[$line]['s'] ?? '') !== 'bad')) return (string) (($sr[0] ?? '') === 'svc' ? $sr[1] : ($sr[2] ?? $sr[1] ?? 'its next source'));
            }
        }
        return '';
    }

    // ---- words from state ------------------------------------------------------------------------------------------

    /**
     * The status line (P9; ruling 5), read from the row as it stands now; '' for a closed alarm (its close words stand).
     */
    public static function status(array $a, string $heldWords): string
    {
        if ($a['cleared_at'] !== null) return '';
        [$n, $of, $next] = self::tries($a);
        $nx = $next ? self::at(gmdate('Y-m-d H:i:s', $next)) : '';
        $trip = $a['level'] === 'trip' && !empty($a['tripped_at']);
        if (!$trip) return $a['level'] === 'warning' && $next ? 'Warning: the system is trying again by itself (' . $n . ' of ' . $of . ', next at ' . $nx . '). Nothing is held.' : '';
        $since = self::at((string) $a['tripped_at']);
        $critical = !empty($a['retrying_at']);
        $comp = (string) ($a['component'] ?? '');
        if ($heldWords !== '') $lead = 'Paused by the system (' . $heldWords . ')';
        elseif ($critical) $lead = 'Down: the system is still trying';
        elseif (str_starts_with($comp, 'task:') || str_starts_with($comp, 'job:')) return 'Tripped ' . $since . ': nothing is held, so its next run still goes ahead.';
        else $lead = 'Down: nothing could be held';
        if ($next && $critical) return $lead . ', ' . $n . ' of ' . $of . ', next at ' . $nx . '.';
        if ($next && $n > 0) return $lead . ' since ' . $since . '. Its last check (' . $n . ') was still red; next check at ' . $nx . '.';
        if ($next) return $lead . ': still trying, ' . $n . ' of ' . $of . ', next at ' . $nx . '.';
        return $lead . ' since ' . $since . '.';
    }

    /** The notice's action word, from what the holds hold (§5.4; 0176's note "SYSTEM ALARM NOTICE: <item>: <action>"). */
    public static function action(array $a, bool $held, bool $escalated): string
    {
        if ($a['cleared_at'] !== null) return 'Recovered';
        if ($escalated) return 'Retries unsuccessful, now admin required';
        return $held ? 'Paused by the system' : 'Down: the system is still trying';
    }

    /** What the system is doing, from state (replaces a raiser's own words where they'd go stale: D4). */
    public static function doing(PDO $pdo, array $a, array $held): string
    {
        if ($a['cleared_at'] !== null) return '';
        $comp = (string) (($a['component'] ?? '') ?: self::componentOf($pdo, (string) $a['akey'], (string) $a['proc']));
        $trip = $a['level'] === 'trip' && !empty($a['tripped_at']);
        if (str_starts_with($comp, 'task:') || str_starts_with($comp, 'job:')) {
            if (!$held) return $trip ? 'It failed again on its next run. It can\'t be held without blocking its next run, so nothing is held and its next scheduled run still goes ahead; that run closes this when it does its work.'
                                     : 'No check can run before its next run: its work goes back and its next scheduled run tries it again. Nothing is held.';
        }
        if ($trip && $held) return 'Only what uses it waits: ' . implode(', ', $held) . '. Everything else carries on. It closes as Recovered when its check passes.';
        return '';
    }

    /** A time for the thread's words (Thread draws {t:…} labelled in the viewer's zone, UTC in plain text). */
    private static function at(string $utc): string { return '{t:' . str_replace(' ', 'T', $utc) . 'Z}'; }
}
