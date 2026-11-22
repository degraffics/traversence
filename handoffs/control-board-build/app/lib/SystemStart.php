<?php
// app/lib/SystemStart.php — "Start the system" and "Pause everything" (Jason, 2026-11-15: "It seems like you expect me to
// flip each switch. Thats more work for me again. We need a ondemand system start which will fire each of the steps you
// expect me to flip. And I will address issues that the system can't fix itself."; decisions/0163 §10, its note of that
// day). One press on System Configuration → Automation (or the Workflow board) runs the switch-on in steps, on the server
// (system_start_tick, every 10 minutes), so the page can close:
//   1. the keys: a missing one holds only what needs it, and is a step on the card naming where it goes;
//   2. watching Railway's deploys and the sync, once its keys are there;
//   3. the switch-on check, with what it can fix by itself fixed (database updates, a failed one tried once more, the
//      register's caps recorded, held services' probes brought forward, the junk count read fresh); a red line holds
//      only what it's for (the master waits only for a line that holds everything), and its fix is on the card;
//   4. confirm operational, within minutes (0163 §10.2's note of 2026-11-17): every workflow and task tested like the
//      pre-flight (its services, keys, cron lines, gate and a dry run where there is one). Before each, the lines it
//      depends on are tested fresh (decisions/0171 §15.1, from the unit register: its outside services and workers when
//      older than pf_fresh_mins, then its source-map needs, always); the four query timings run once a session (slow is
//      noted and holds nothing; a query that fails is a step on the card). A red one is held with its answer and fix and
//      tested again at each look; the rest go on;
//   5. Railway: the crawler's schedule set again through its API (restarts kept at Never), or a step with the setting;
//   6. the master switch on once every workflow and task is confirmed; then each confirmed one switches on in order
//      (W0, W1 … W12, then the new tasks once their workflow is on: the identity recheck, Overture, Foursquare,
//      OpenStreetMap from Geofabrik, the vector index, W14), without waiting on runs. Each comes back at half budget for
//      14 days (Clocks::ramp, 0163 §10.2).
// Each one on is watched by itself afterwards (gate(): start_gate_runs good runs or start_gate_hours, no service issue that
// holds it, nothing out of budget, failures no more than one in ten): one that misbehaves is stopped alone with its fixes
// on the card, and the rest carry on. When Jason fixes what a step names, the next look carries on by itself. Nothing here
// runs until the button is pressed; never on staging.
// Every outside call it makes (Railway's API) goes through ServiceHealth::allowed (holds, pauses, staging's answer).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Automation.php';
require_once __DIR__ . '/Operations.php';
require_once __DIR__ . '/Thresholds.php';

final class SystemStart
{
    public const KEY = 'ops:start';
    public const BY = 'Start the system';
    public const WAIT = 'Waiting its turn (Start the system)';
    /** The crawler's schedule on Railway before it was removed on 2026-11-14 (handoffs/crawler-revamp.md). */
    public const CRON = '*/5 * * * *';
    /** The workflows in order (0163 §10.2: one at a time; W0 the indexer first, Jason 2026-11-17, then W1). W13 is people's own: nothing to switch. */
    public const ORDER = ['W0', 'W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8', 'W9', 'W10', 'W11', 'W12'];
    /** The new tasks after them (§6): item => [name, its workflow (on first), services it calls, site jobs, crawler task(s)]. */
    public const TASKS = [
        'recheck' => ['The identity recheck', 'W2', [], ['identity_recheck_tick'], ['recheck']],
        'overture' => ['Overture\'s town loads', 'W1', ['overture'], [], ['overture_bulk']],
        'foursquare' => ['Foursquare\'s open places', 'W1', ['foursquare'], [], ['foursquare_bulk']],
        'osm' => ['OpenStreetMap from Geofabrik', 'W1', ['geofabrik'], [], ['geofabrik_bulk']],
        'vectors' => ['The vector index', 'W3', ['supabase', 'gemini'], ['vectors_tick'], []],
        'W14' => ['W14 Authority sources', '', [], [], []],
    ];
    /** The steps before the workflows. */
    /** How long one look may spend confirming workflows (seconds); a page open on the card looks every 15 seconds. */
    public static int $budget = 40;
    /** The answer to a press on the card, shown at its top: [ok, words] (set by the Automation page). */
    public static ?array $said = null;

    public const FIRST = ['keys' => 'Check the keys', 'watch' => 'Switch on watching', 'check' => 'Run the switch-on check', 'verify' => 'Confirm each workflow and task is operational', 'railway' => 'Railway: the crawler\'s schedule', 'master' => 'The master switch on'];
    public const LABELS = ['pending' => 'Waiting', 'on' => 'Running, watched', 'done' => 'Done', 'held' => 'Held', 'stopped' => 'Stopped'];

    /** The tests' own switch-on check lines (tools/release-b-tests/start.php); null on the site. */
    public static $check = null;

    // ---- its state (one row in search_index_state: no new table) ---------------------------------------------------

    public static function state(PDO $pdo): array
    {
        try { require_once __DIR__ . '/SearchIndex.php'; $j = json_decode(SearchIndex::state($pdo, self::KEY), true); } catch (Throwable) { $j = null; }
        return is_array($j) ? $j : ['state' => 'idle'];
    }

    private static function save(PDO $pdo, array $st): void
    {
        $st['log'] = array_slice($st['log'] ?? [], -400);   // the session's whole report (its run log line opens to it)
        require_once __DIR__ . '/SearchIndex.php';
        SearchIndex::setState($pdo, self::KEY, json_encode($st, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function note(array &$st, string $line): void { $st['log'][] = [gmdate('Y-m-d H:i:s'), $line]; }

    public const SESSIONS = 'start:sessions';

    /** A finished or replaced Start session, kept whole for the run log: who, when, how it ended, each step, every event. */
    private static function archive(PDO $pdo, array $st): void
    {
        if (empty($st['run'])) return;
        try {
            require_once __DIR__ . '/SearchIndex.php';
            $all = json_decode(SearchIndex::state($pdo, self::SESSIONS), true); $all = is_array($all) ? $all : [];
            $all = array_values(array_filter($all, fn ($x) => (int) ($x['run'] ?? 0) !== (int) $st['run']));
            $all[] = self::session($st);
            SearchIndex::setState($pdo, self::SESSIONS, json_encode(array_slice($all, -20), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (Throwable $x) { error_log('start sessions: ' . $x->getMessage()); }
    }

    /** What a session is now, in words (2026-11-18: "Starting" for 6.5 hours read as stuck): Starting while it confirms or
     *  switches on; Operational once everything confirmed is on and only watching remains (with how many on, held, stopped). */
    public static function runLabel(array $x): string
    {
        $n = ['done' => 0, 'on' => 0, 'held' => 0, 'stopped' => 0, 'pending' => 0];
        foreach ((array) ($x['items'] ?? []) as $id => $it) if (!isset(self::FIRST[$id])) $n[$it['s'] ?? 'pending'] = ($n[$it['s'] ?? 'pending'] ?? 0) + 1;
        $st = (string) ($x['state'] ?? '');
        if ($st === 'done') return 'Everything on';
        if ($st === 'paused') return 'Paused everything';
        if (!in_array($st, ['running', 'stopped'], true)) return 'Ended';
        if ($n['pending'] || (($x['items']['master']['s'] ?? '') !== 'done')) return 'Starting';
        return 'Operational: ' . ($n['done'] + $n['on']) . ' on' . ($n['on'] ? ' (' . $n['on'] . ' still watched)' : '') . ($n['held'] ? ', ' . $n['held'] . ' held' : '') . ($n['stopped'] ? ', ' . $n['stopped'] . ' stopped' : '');
    }

    /** A session's report, as text: who, when, how it stands, every step, every event (the run log's Download report). */
    public static function report(PDO $pdo, int $run): ?array
    {
        $st = self::state($pdo); $x = null;
        if ((int) ($st['run'] ?? 0) === $run) $x = self::session($st);
        else { try { require_once __DIR__ . '/SearchIndex.php'; foreach ((array) json_decode(SearchIndex::state($pdo, self::SESSIONS), true) as $s) if ((int) ($s['run'] ?? 0) === $run) $x = $s; } catch (Throwable $e) {} }
        if (!$x) return null;
        $t = fn (int $ts) => $ts ? gmdate('M j, Y g:i A', $ts) . ' UTC' : '';
        $o = ['Start the system: report', 'Status: ' . self::runLabel($x), 'Started: ' . $t((int) $x['run']) . ($x['by'] !== '' ? ' by ' . $x['by'] : ''), 'Last event: ' . $t((int) $x['end']), '', '== Steps =='];
        foreach (self::items() as $id => $nm) { if (!isset($x['items'][$id])) continue; $it = $x['items'][$id]; $o[] = str_pad(self::LABELS[$it['s']] ?? $it['s'], 18) . $nm . ($it['why'] !== '' ? ': ' . $it['why'] : '') . ($it['at'] ? ' (' . $t((int) $it['at']) . ')' : ''); }
        $o[] = ''; $o[] = '== Every event, in order ==';
        foreach ((array) $x['log'] as [$at, $line]) $o[] = $at . ' UTC  ' . $line;
        return [implode("\n", $o) . "\n", (int) $x['run']];
    }

    /** One session as the run log keeps it. */
    public static function session(array $st): array   // public for the logbook (ControlLog, 0171 §10)
    {
        $run = (int) ($st['run'] ?? 0); $log = array_values(array_filter((array) ($st['log'] ?? []), fn ($l) => strtotime($l[0] . ' UTC') >= $run - 5));
        $items = []; foreach ((array) ($st['items'] ?? []) as $id => $it) $items[$id] = ['s' => (string) ($it['s'] ?? 'pending'), 'why' => (string) ($it['why'] ?? ''), 'at' => (int) ($it['at'] ?? 0), 'on_at' => (int) ($it['on_at'] ?? 0), 'done_at' => (int) ($it['done_at'] ?? 0)];
        $end = $log ? (int) strtotime(end($log)[0] . ' UTC') : $run;
        return ['run' => $run, 'by' => (string) ($st['by'] ?? ''), 'state' => (string) ($st['state'] ?? ''), 'end' => $end, 'items' => $items, 'log' => $log];
    }

    /** Every item in order: id => name. */
    public static function items(): array
    {
        $out = self::FIRST;
        foreach (self::ORDER as $wf) $out['wf:' . $wf] = $wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? '');
        foreach (self::TASKS as $t => $d) $out['t:' . $t] = $d[0];
        return $out;
    }

    public static function can(): array
    {
        if (!class_exists('Access', false)) require_once __DIR__ . '/../admin/Access.php';
        return Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
    }

    private static function staging(): bool
    {
        require_once __DIR__ . '/Staging.php';
        return Staging::here();
    }

    /** Why the button can't be pressed here, or null. */
    public static function refused(PDO $pdo): ?string
    {
        if (self::staging()) return 'Start the system runs on the live site only: staging is a safe copy, where nothing switches on.';
        if (!Automation::ready($pdo)) return 'The switches\' database update hasn\'t run yet.';
        return null;
    }

    // ---- the two buttons ----------------------------------------------------------------------------------------------

    /** Start the system: a new run, everything waiting its turn, and its first steps at once. */
    public static function start(PDO $pdo, int $uid, string $who): array
    {
        if (($no = self::refused($pdo)) !== null) throw new RuntimeException($no);
        $st = self::state($pdo);
        if (in_array($st['state'] ?? '', ['running', 'stopped'], true)) throw new RuntimeException('It\'s already starting: its progress is below.');
        self::archive($pdo, $st);   // the session before goes to the run log whole, with its report (Jason, 2026-11-17)
        $st = ['state' => 'running', 'run' => time(), 'by_uid' => $uid, 'by' => $who, 'items' => [], 'asks' => [], 'log' => []];
        foreach (array_keys(self::items()) as $id) $st['items'][$id] = ['s' => 'pending', 'why' => '', 'at' => 0];
        // every workflow waits its turn, so the master doesn't start them all at once (0163 §10.2); one paused before keeps
        // its reason in the log, and any other reason seen later is a person's pause since the start
        foreach (array_merge(self::ORDER, ['W14']) as $wf) {
            $r = Automation::workflows($pdo)[$wf] ?? null;
            if ($r && !((int) $r['paused'] && (string) $r['paused_why'] === self::WAIT)) try { Automation::setWorkflow($pdo, $wf, true, self::WAIT, $uid, self::BY); } catch (Throwable $x) { error_log('start wait ' . $wf . ': ' . $x->getMessage()); }
        }
        self::note($st, 'Started by ' . ($who !== '' ? $who : 'a director') . '.');
        self::log($pdo, 'start', false, 'Start the system pressed', $uid, $who);
        self::save($pdo, $st);
        self::$budget = 25;
        return self::tick($pdo);
    }

    /** Pause everything: the master off and Railway's schedule stopped (when its keys allow), in one press, logged. */
    public static function pauseAll(PDO $pdo, int $uid, string $who): string
    {
        if (self::staging()) throw new RuntimeException('Nothing runs on staging to pause.');
        Automation::set($pdo, 'master', 'all', true, 'Pause everything, pressed by ' . ($who !== '' ? $who : 'a director'), $uid, $who);
        $st = self::state($pdo);
        [$ok, $words] = self::railwayKeys() ? self::railwaySchedule($pdo, null) : [false, 'Railway\'s keys aren\'t in .env, so its schedule wasn\'t touched (each run still ends at once while the master is off).'];
        $st['state'] = 'paused'; $st['paused_at'] = time();
        self::note($st, 'Paused everything (' . ($who !== '' ? $who : 'a director') . '). Railway: ' . $words);
        self::log($pdo, 'pause_all', true, 'Pause everything; Railway: ' . $words, $uid, $who);
        self::save($pdo, $st);
        return 'Everything is paused: the master switch is off. Railway: ' . $words;
    }

    private static function log(PDO $pdo, string $ref, bool $pause, string $why, int $uid, string $who): void
    {
        try {
            $pdo->prepare('INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?)')
                ->execute(['master', $ref, $pause ? 1 : 0, mb_substr($why, 0, 255), $uid ?: null, mb_substr($who, 0, 80) ?: null]);
        } catch (Throwable $x) { error_log('start log: ' . $x->getMessage()); }
    }

    // ---- the tick: one step forward at a time ------------------------------------------------------------------------

    /** The every-10-minutes job (and each press): does what's due, then says where it is. */
    public static function tick(PDO $pdo): array
    {
        $st = self::state($pdo);
        if (!in_array($st['state'] ?? '', ['running', 'stopped'], true) || self::staging()) return $st;
        $lock = false;
        try { $lock = (bool) $pdo->query("SELECT GET_LOCK('tv_system_start', 0)")->fetchColumn(); } catch (Throwable) {}
        if (!$lock) return $st;
        try { $st = self::advance($pdo, $st); self::save($pdo, $st); }
        catch (Throwable $x) { error_log('system start: ' . $x->getMessage()); self::note($st, 'A step failed: ' . mb_substr($x->getMessage(), 0, 200) . ' (tried again next time).'); self::save($pdo, $st); }
        finally { try { $pdo->query("SELECT RELEASE_LOCK('tv_system_start')"); } catch (Throwable) {} }
        return $st;
    }

    private static function set(array &$st, string $id, string $s, string $why): void
    {
        $was = $st['items'][$id] ?? [];
        if (($was['s'] ?? '') !== $s || ($was['why'] ?? '') !== $why) {
            $st['items'][$id] = ['s' => $s, 'why' => $why, 'at' => time(), 'on_at' => $s === 'on' ? (int) ($was['on_at'] ?? 0 ?: time()) : (int) ($was['on_at'] ?? 0),
                                 'done_at' => $s === 'done' ? (int) ($was['done_at'] ?? 0 ?: time()) : 0];   // Complete's time (Jason, 2026-11-17: started, running, complete, active status)
            if (($was['s'] ?? '') !== $s) self::note($st, (self::items()[$id] ?? $id) . ': ' . strtolower(self::LABELS[$s] ?? $s) . ($why !== '' ? ' (' . $why . ')' : '') . '.');
        }
    }

    private static function advance(PDO $pdo, array $st): array
    {
        $uid = (int) ($st['by_uid'] ?? 0); $asks = [];
        // a person paused the master after this run turned it on: the run stops (their switch stands)
        $m = Automation::masterRow($pdo);
        if (!empty($st['master_on']) && $m && (int) $m['paused'] && (string) ($m['by_name'] ?? '') !== self::BY) {
            $st['state'] = 'paused'; self::note($st, 'The master switch was paused by a person: the start stops here (Start the system again to carry on).');
            return $st;
        }

        // 1. the keys
        $reg = Operations::register($pdo); $missing = [];
        foreach ($reg as $k => $r) {
            if (!$r['workflows'] && !in_array($k, ['railway', 'github', 'gemini', 'supabase', 'foursquare'], true)) continue;
            foreach ($r['keys'] as [$n, $where, $have]) if ($have !== true) $missing[$n] = [$where, $have, $r['name']];
        }
        foreach ($missing as $n => [$where, $have, $svc]) $asks[] = ['Add ' . $n, 'For ' . $svc . ': ' . ($have === null ? 'the crawler hasn\'t reported it yet (it does at each run). If it\'s missing, add it ' : 'add it ') . Operations::whereToAdd($where) . '. What needs it waits; everything else goes ahead.', null, str_starts_with($where, 'Railway') ? 'railway' : '', ['keys'], 'keys'];
        self::set($st, 'keys', $missing ? 'held' : 'done', $missing ? count($missing) . ' missing or not reported: ' . implode(', ', array_keys($missing)) : 'Every key in place');

        // 2. watching
        $w = Operations::watching($pdo);
        if (empty($w['on'])) {
            $need = []; foreach (Operations::WATCH as [$name, $key]) if (self::env($key) === '') $need[] = $key;
            if (!$need && self::env('RAILWAY_PROJECT_ID') !== '' && self::env('RAILWAY_SERVICE_ID') !== '') {
                Operations::setWatching($pdo, true, $uid); Operations::watchTick($pdo);
                self::set($st, 'watch', 'done', 'Switched on, first look taken');
            } else self::set($st, 'watch', 'held', 'Needs ' . implode(', ', $need ?: ['RAILWAY_PROJECT_ID', 'RAILWAY_SERVICE_ID']) . ' in .env');
        } else self::set($st, 'watch', 'done', 'On');

        // 3. the switch-on check, after what it can fix by itself
        $fixed = self::fixes($pdo, $st);
        Automation::forget();
        $chk = self::$check ?? Operations::switchOnCheck($pdo);
        if (self::$check === null) { try { require_once __DIR__ . '/Preflight.php'; Preflight::recordSwitchOn($pdo, $chk, self::BY); } catch (Throwable $x) { error_log('Start: recording the check: ' . $x->getMessage()); } }   // the same answers on the pre-flight and the board (2026-11-17)
        $byScope = [];
        foreach ($chk as $l) if (!$l['ok']) $byScope[$l['scope']][] = $l;
        $all = $byScope['all'] ?? [];
        foreach ($chk as $l) {
            if ($l['ok'] || in_array($l['scope'], ['keys', 'schedule'], true)) continue;   // keys: above; the schedule: step 4
            $act = $l['fix'] ? ['tool', $l['fix'][1]] : null;
            if (str_contains($l['why'], 'junk name') ) $act = ['do', 'ops_start_junk', 'Send them to Review'];
            $asks[] = [$l['line'], $l['why'] . ' ' . ($l['scope'] === 'all' ? 'It holds the master switch.' : 'It holds only what it\'s for (' . $l['scope'] . ').'), $act, '', $l['scope'] === 'all' ? ['check', 'master'] : ['check'], (string) $l['scope']];
        }
        $red = count(array_filter($chk, fn ($l) => !$l['ok'] && !in_array($l['scope'], ['keys', 'schedule'], true)));
        // held only when a line holds everything; a red line for one part (W14's hub terms) holds that part alone, so the check
        // itself is done and says so (2026-11-17: "Held" in red read as a stop while the master went on)
        $parts = array_values(array_unique(array_map(fn ($l) => (string) $l['scope'], array_filter($chk, fn ($l) => !$l['ok'] && $l['scope'] !== 'all' && !in_array($l['scope'], ['keys', 'schedule'], true)))));
        self::set($st, 'check', $all ? 'held' : 'done', ($red ? $red . ' red line' . ($red === 1 ? '' : 's') . ($all ? ', ' . count($all) . ' holding everything' : ', holding only ' . implode(', ', $parts) . ': the rest starts') : 'Every line green') . ($fixed ? '; fixed by itself: ' . implode(', ', $fixed) : ''));

        // 3b. confirm operational, within minutes (Jason, 2026-11-17: "the start needs to confirm it's operational within minutes
        // similar to the preflight"): every workflow and task tested like the pre-flight (its services, keys, cron lines, gate
        // and a dry run where one exists), as many as fit in this look; a red one is held with its answer and fix, and is
        // tested again at each look until it's green; the rest go on
        $ids0 = array_slice(array_keys(self::items()), count(self::FIRST));
        $ver = (array) ($st['verify'] ?? []); $t0 = time();
        try {
            require_once __DIR__ . '/Preflight.php';
            foreach ($ids0 as $id) {
                $s0 = (string) ($st['items'][$id]['s'] ?? 'pending');
                if (in_array($s0, ['on', 'done'], true) && isset($ver[$id])) continue;   // switched on: watched now, not tested again
                if (isset($ver[$id]) && $ver[$id]['s'] !== 'bad') continue;               // green or amber: kept
                if (time() - $t0 >= self::$budget) break;
                $pid = $id === 't:W14' ? 'wf:W14' : $id;
                // what it depends on, tested fresh first (decisions/0171 §15.1): carried on at the next look when time runs out
                if (!Preflight::refreshFor($pdo, $pid, $t0 + self::$budget, self::BY)) break;
                $r = Preflight::run($pdo, $pid, self::BY);
                $ver[$id] = ['s' => (string) $r['s'], 'said' => mb_substr((string) $r['said'], 0, 600), 'fix' => array_slice((array) ($r['fix'] ?? []), 0, 3), 'at' => time()];
            }
            // the four query timings, once a session (decisions/0171 §15.1): slow is noted and holds nothing; a failed query is a step
            if (!isset($st['timings']) && time() - $t0 < self::$budget) {
                $tm = [];
                foreach (array_keys(Preflight::TIMINGS) as $k) { $r = Preflight::run($pdo, 'ms:' . $k, self::BY); $tm[$k] = ['s' => (string) $r['s'], 'said' => mb_substr((string) $r['said'], 0, 300), 'failed' => str_starts_with((string) $r['said'], 'The query failed')]; }
                $st['timings'] = ['at' => time(), 'lines' => $tm];
            }
        } catch (Throwable $x) { error_log('start verify: ' . $x->getMessage()); }
        $st['verify'] = $ver;
        $tmNote = '';
        if (isset($st['timings']['lines'])) {
            $slow = []; foreach ($st['timings']['lines'] as $k => $t) {
                if (!empty($t['failed'])) $asks[] = [Preflight::TIMINGS[$k][0] . ': the query failed', $t['said'] . ' It holds nothing; the database needs a look.', ['tool', '/admin/config/preflight#pf-ms-' . $k], '', ['verify'], ''];
                elseif ($t['s'] === 'bad') $slow[] = Preflight::TIMINGS[$k][0];
            }
            $tmNote = '; query timings: ' . ($slow ? count($slow) . ' slow (' . implode('; ', $slow) . '), noted, holding nothing' : 'within their limits');
        }
        $nv = count(array_filter($ids0, fn ($id) => isset($ver[$id]))); $nbad = array_keys(array_filter($ver, fn ($v) => $v['s'] === 'bad'));
        foreach ($nbad as $id) { $f = $ver[$id]['fix'][0] ?? null; $asks[] = [(self::items()[$id] ?? $id) . ': not operational', $ver[$id]['said'], $f && $f[0] === 'tool' ? ['tool', $f[2]] : null, '', [$id, 'verify'], '']; }
        self::set($st, 'verify', $nv < count($ids0) ? 'on' : 'done', ($nv < count($ids0) ? 'Confirming: ' . $nv . ' of ' . count($ids0) . ' tested, each after what it depends on' : (count($ids0) - count($nbad)) . ' of ' . count($ids0) . ' operational' . ($nbad ? '; not yet: ' . implode(', ', array_map(fn ($i) => self::items()[$i] ?? $i, $nbad)) . ' (each held with its fix, the rest go on)' : '')) . $tmNote);

        // 4. Railway's schedule
        $rs = $st['items']['railway'] ?? [];
        if (($rs['s'] ?? '') !== 'done') {
            if (self::railwayKeys()) {
                [$ok, $words] = self::railwaySchedule($pdo, self::CRON);
                self::set($st, 'railway', $ok ? 'done' : 'held', $words);
                if (!$ok) $asks[] = ['Set the crawler\'s schedule on Railway', $words . ' By hand: Railway → the traversence service → Settings: Cron Schedule ' . self::CRON . ', Restart Policy Never.', null, 'railway', ['railway'], 'schedule'];
            } else {
                self::set($st, 'railway', 'held', 'No RAILWAY_API_TOKEN, RAILWAY_PROJECT_ID and RAILWAY_SERVICE_ID in .env');
                $asks[] = ['Set the crawler\'s schedule on Railway', 'Add RAILWAY_API_TOKEN, RAILWAY_PROJECT_ID and RAILWAY_SERVICE_ID in the site\'s .env (cPanel → File Manager) and this is done for you. Or by hand: Railway → the traversence service → Settings: Cron Schedule ' . self::CRON . ', Restart Policy Never.', null, 'railway', ['railway'], 'schedule'];
            }
        }

        // 5. the master: waits only for the lines that hold everything
        if ($all) {
            self::set($st, 'master', 'held', 'Waiting for: ' . implode('; ', array_map(fn ($l) => $l['line'], array_slice($all, 0, 3))));
            $st['asks'] = $asks; return $st;
        }
        if (($st['items']['verify']['s'] ?? '') !== 'done') { self::set($st, 'master', 'pending', 'Waits for every workflow and task to be confirmed'); $st['asks'] = $asks; return $st; }
        if (Automation::master($pdo)) { Automation::set($pdo, 'master', 'all', false, 'Start the system', $uid, self::BY); $st['master_on'] = time(); }
        self::set($st, 'master', 'done', 'On; each confirmed workflow and task switches on in order');

        // 5–6. the queue (Jason, 2026-11-17: "Once its operational the queue for firing each is clean and efficient"): in order,
        // each confirmed workflow switches on, then each new task once its workflow is on; nothing waits hours for the one
        // before. Watching goes on after: each one on is watched by itself (its gate: a service issue that holds it, out of
        // budget, too many failed runs) and stopped alone, with its fixes on the card; the rest carry on.
        $ids = $ids0;
        if ($st['state'] === 'stopped') $st['state'] = 'running';   // a stop holds only its own step now
        foreach ($ids as $sid) if (($st['items'][$sid]['s'] ?? '') === 'stopped') {   // stopped by an issue that no longer holds its service: carries on
            $it = $st['items'][$sid];
            $iss = (int) ($it['issue'] ?? 0) ?: (preg_match('/service issue \(#(\d+)\)/', (string) ($it['why'] ?? ''), $mm) ? (int) $mm[1] : 0);
            if (!$iss) continue;
            $still = false;
            try { require_once __DIR__ . '/ServiceHealth.php'; foreach (ServiceHealth::openAll($pdo) as $si) if ((int) $si['id'] === $iss && self::issueHolds($pdo, $si)) $still = true; } catch (Throwable $x) { $still = true; }
            if ($still) continue;
            self::turnOn($pdo, $sid, $uid); self::set($st, $sid, 'on', 'Carried on: issue #' . $iss . ' no longer holds its service'); $st['items'][$sid]['on_at'] = time(); unset($st['items'][$sid]['issue']);
        }
        // watching: each one on, by itself
        foreach ($ids as $id) {
            if (($st['items'][$id]['s'] ?? '') !== 'on') continue;
            if (($mt = self::inMaintenance($pdo, $id)) !== null) { self::set($st, $id, 'held', $mt); continue; }
            if (($p = self::personPaused($pdo, self::parts($pdo, $id)[0])) !== null && (str_starts_with($id, 'wf:') || $id === 't:W14')) { self::set($st, $id, 'held', $p); continue; }
            $g = self::gate($pdo, $id, (int) $st['items'][$id]['on_at']);
            if ($g[0] === 'fail') {
                self::pauseItem($pdo, $id, 'Stopped by Start the system\'s watch: ' . $g[1], $uid);
                self::set($st, $id, 'stopped', $g[1]);
                $st['items'][$id]['issue'] = (int) ($g[2] ?? 0);
                $asks[] = [self::items()[$id] . ': stopped', $g[1] . ' It\'s paused by itself; the rest carry on.', null, '', [$id], ''];
                continue;
            }
            self::set($st, $id, $g[0] === 'pass' ? 'done' : 'on', $g[1]);
        }
        // firing, in order: each confirmed one whose turn has come (a task once its workflow is on)
        foreach ($ids as $id) {
            $s = $st['items'][$id]['s'] ?? 'pending';
            if (in_array($s, ['done', 'stopped', 'on'], true)) continue;
            $v = $st['verify'][$id] ?? null;
            if (!$v) { self::set($st, $id, 'pending', 'Waits to be confirmed'); continue; }
            if ($v['s'] === 'bad') { self::set($st, $id, 'held', 'Not operational yet: ' . mb_substr($v['said'], 0, 300)); continue; }
            if (($mt = self::inMaintenance($pdo, $id)) !== null) { self::set($st, $id, 'held', $mt . ' Skipped: it comes back by itself, or with Put back on the Control Board.'); continue; }
            $why = self::heldBy($pdo, $id, $st, $byScope);
            if ($why !== null) { self::set($st, $id, 'held', $why); continue; }
            self::turnOn($pdo, $id, $uid);
            self::set($st, $id, 'on', 'Switched on; watched');
        }
        // the start goes on looking while anything is held, so a fix is picked up; it's done when everything is on
        $left = array_filter($ids, fn ($id) => ($st['items'][$id]['s'] ?? '') !== 'done');
        if (!$left) { $st['state'] = 'done'; self::note($st, 'Everything is on.'); }
        $st['asks'] = $asks;
        return $st;
    }

    /** What the check can fix by itself, once a run (each kept in the state so it isn't repeated): [what it did]. */
    private static function fixes(PDO $pdo, array &$st): array
    {
        $done = [];
        try {
            require_once __DIR__ . '/DbUpdates.php';
            $failed = array_keys(array_filter(DbUpdates::log($pdo), fn ($r) => !(int) $r['ok']));
            if ($failed && empty($st['fix_retried'])) { DbUpdates::retry($pdo, $failed); $st['fix_retried'] = 1; $done[] = 'the failed database update tried once more'; }
            if (DbUpdates::all($pdo)) { $r = DbUpdates::autoRun($pdo, false, 'start'); if ($r['ran']) $done[] = count($r['ran']) . ' database update(s) run'; }
        } catch (Throwable $x) { error_log('start fixes db: ' . $x->getMessage()); }
        try { if ($s = Operations::seed($pdo)) $done[] = 'the register\'s caps recorded (' . implode(', ', $s) . ')'; } catch (Throwable $x) {}
        if (empty($st['fix_probed'])) {
            try {
                require_once __DIR__ . '/ServiceHealth.php';
                $n = 0; foreach (ServiceHealth::openAll($pdo) as $si) if (ServiceHealth::holding($si) && ServiceHealth::probeSoon($pdo, (int) $si['id'])) $n++;
                if ($n) $done[] = $n . ' held service(s) probed again';
            } catch (Throwable $x) {}
            try { require_once __DIR__ . '/SearchIndex.php'; SearchIndex::setState($pdo, 'ops:junk_live', ''); } catch (Throwable $x) {}   // counted fresh
            $st['fix_probed'] = 1;
        }
        return $done;
    }

    /** Why an item must wait now (a red line for it or its services, a missing key, its workflow not on), or null. */
    /** Taken out for maintenance on the Control Board (0171 §2.5): Start skips it, saying why. */
    private static function inMaintenance(PDO $pdo, string $id): ?string
    {
        try { require_once __DIR__ . '/ControlMaintenance.php'; $m = ControlMaintenance::outFor($pdo, $id); return $m ? ControlMaintenance::words($m) . '.' : null; }
        catch (Throwable $x) { return null; }
    }

    private static function heldBy(PDO $pdo, string $id, array $st, array $byScope): ?string
    {
        [$wf, $svcs, , $tasks] = self::parts($pdo, $id);
        $why = [];
        if (str_starts_with($id, 'wf:')) {
            if ($wf === 'W0') {   // the indexer: its frontier, and its service on Railway checking in (Jason sets it up once)
                require_once __DIR__ . '/crawler/Frontier.php';
                if (!Frontier::ready($pdo)) return 'Its database update hasn\'t run';
                Frontier::ensureWorkflow($pdo); Automation::forget();
                $beat = Frontier::lastBeat($pdo);
                if (empty($beat['at']) || (int) $beat['at'] < time() - 900) return 'The indexer on Railway hasn\'t checked in' . (!empty($beat['at']) ? ' since ' . gmdate('M j, H:i', (int) $beat['at']) . ' UTC' : ' yet') . ': its service (W0 Indexer) must be deployed and running (it sleeps while W0 is off)';
            }
            $r = Automation::workflows($pdo)[$wf] ?? null;
            if (!$r) return 'Not set up yet (its database update adds it)';
            if (($p = self::personPaused($pdo, $wf)) !== null) return $p;
        } else {
            $t = substr($id, 2); $parent = self::TASKS[$t][1];
            if ($parent !== '' && !in_array($st['items']['wf:' . $parent]['s'] ?? '', ['on', 'done'], true)) return 'Waits for ' . $parent . ' to be switched on';
            if ($t === 'recheck') { require_once __DIR__ . '/ListingIdentity.php'; if (!ListingIdentity::ready($pdo)) $why[] = 'its database update hasn\'t run'; }
            if (in_array($t, ['overture', 'foursquare', 'osm'], true)) { require_once __DIR__ . '/crawler/OpenLoads.php'; if (!OpenLoads::ready($pdo)) $why[] = 'its database update hasn\'t run'; }
            if ($t === 'foursquare' && (Operations::workerKeys()['HF_TOKEN'] ?? null) !== true) $why[] = 'HF_TOKEN on Railway (a Hugging Face account that accepted Foursquare\'s terms), not reported by the crawler yet';
            if ($t === 'vectors') {
                require_once __DIR__ . '/Vectors.php';
                if (!Vectors::configured()) $why[] = 'SUPABASE_URL and SUPABASE_SERVICE_KEY in .env';
                if (self::env('GEMINI_API_KEY') === '') $why[] = 'GEMINI_API_KEY in .env';
            }
            if ($t === 'W14') { require_once __DIR__ . '/HubOpening.php'; if (!HubOpening::ready($pdo)) $why[] = 'its database update hasn\'t run'; }
        }
        foreach (array_merge([$wf], $svcs) as $sc) foreach ($byScope[$sc] ?? [] as $l) $why[] = $l['line'] . ': ' . rtrim($l['why'], '.');
        return $why ? ucfirst(implode('; ', array_slice(array_unique($why), 0, 3))) : null;
    }

    /** A workflow paused by a person since the start (any reason but the start's own), or null. */
    private static function personPaused(PDO $pdo, string $wf): ?string
    {
        $r = Automation::workflows($pdo)[$wf] ?? null;
        if (!$r || !(int) $r['paused']) return null;
        $why = (string) ($r['paused_why'] ?? '');
        return $why === self::WAIT ? null : 'Paused by a person since the start' . ($why !== '' ? ': ' . $why : '') . ' (their switch stands)';
    }

    /** An item's workflow, the services it calls, its site jobs and its crawler tasks. */
    private static function parts(PDO $pdo, string $id): array
    {
        if (str_starts_with($id, 'wf:')) {
            $wf = substr($id, 3); $svcs = [];
            foreach (array_keys(OutsideServices::COMPANIES) as $svc) if (in_array($wf, Operations::users($pdo, $svc)[0], true)) $svcs[] = $svc;
            $jobs = array_keys(array_filter(Automation::JOBS, fn ($w) => in_array($wf, $w, true)));
            return [$wf, $svcs, $jobs, []];
        }
        $t = substr($id, 2);
        if ($t === 'W14') return ['W14', [], [], []];
        [, $parent, $svcs, $jobs, $tasks] = self::TASKS[$t];
        return [$parent, $svcs, $jobs, $tasks];
    }

    private static function turnOn(PDO $pdo, string $id, int $uid): void
    {
        $why = 'Its turn in Start the system';
        if (str_starts_with($id, 'wf:') || $id === 't:W14') {
            $wf = $id === 't:W14' ? 'W14' : substr($id, 3);
            if ($wf === 'W14') { require_once __DIR__ . '/HubOpening.php'; HubOpening::ensureWorkflow($pdo); }
            if ($wf === 'W0') { require_once __DIR__ . '/crawler/Frontier.php'; Frontier::ensureWorkflow($pdo); }
            Automation::setWorkflow($pdo, $wf, false, $why, $uid, self::BY);
            return;
        }
        $t = substr($id, 2); [, , $svcs, $jobs, $tasks] = self::TASKS[$t];
        if (in_array($t, ['overture', 'foursquare', 'osm'], true)) { require_once __DIR__ . '/crawler/OpenLoads.php'; OpenLoads::ensureTasks($pdo); }
        if ($t === 'recheck') { require_once __DIR__ . '/ListingIdentity.php'; ListingIdentity::ensureTask($pdo); }   // registered at its first run otherwise, so switching it on refused "No such capture task" (the pre-flight, 2026-11-17)
        foreach ($svcs as $svc) if (Automation::service($pdo, $svc)) Automation::set($pdo, 'service', $svc, false, $why, $uid, self::BY);
        foreach ($tasks as $task) Automation::setTask($pdo, $task, true, $why, $uid, self::BY);
        if ($t === 'vectors') {
            require_once __DIR__ . '/AiClient.php';
            $f = AiClient::FEATURES['vector_index'];
            AiClient::setFeature($pdo, 'vector_index', ['enabled' => 1, 'model' => $f[2], 'per_day' => $f[3], 'month_usd' => $f[4]], $uid);
        }
        foreach ($jobs as $job) self::ensureJob($job);
    }

    /** A job not set up in cPanel by itself (Scheduler::AUTO leaves it out until it's wanted) added on its usual schedule. */
    private static function ensureJob(string $job): void
    {
        try {
            require_once __DIR__ . '/Scheduler.php';
            if (!Scheduler::lineFor(Scheduler::cronLines(), $job)) Scheduler::add($job, Scheduler::JOBS[$job][2]);
        } catch (Throwable $x) { error_log('start job ' . $job . ': ' . $x->getMessage()); }
    }

    private static function pauseItem(PDO $pdo, string $id, string $why, int $uid): void
    {
        // the watch never writes a person's switch (the one engine, CS-1 and P5; g3 C8, F7: it set crawl_workflows.paused,
        // credited to whoever pressed Start): it raises its fault through the engine, which holds only what its cause touches
        // (a Start task's crawler tasks; for a workflow, the cause's own alarms hold what they touch, P6)
        $why = mb_substr($why, 0, 250);
        require_once __DIR__ . '/ControlAlarms.php';
        $comp = str_starts_with($id, 't:') && $id !== 't:W14' ? $id : ($id === 't:W14' ? 'wf:W14' : $id);
        ControlAlarms::raise($pdo, 'start:held:' . $id, $comp, 'trip', 'check', (self::TASKS[substr($id, 2)][0] ?? $id) . ': held by Start the system\'s watch',
            'Start the system\'s watch found it not ready: ' . $why, 'Fix what its reason names; its own alarm (beside this) says how. Check and start again checks it first.', $why, [['Start the system (its step)', 'peek', '/admin/config/automation#start'], ['Check', 'check', $comp]], true, false, $comp);
    }

    /** The gates (0163 §12, "Start the system"): ['wait'|'pass'|'fail', why]. */
    public static function gate(PDO $pdo, string $id, int $since): array
    {
        [$wf, $svcs, $jobs] = self::parts($pdo, $id);
        $secs = max(0, time() - $since);
        $hours = (float) Thresholds::get($pdo, 'start_gate_hours'); $need = (int) Thresholds::get($pdo, 'start_gate_runs');
        // no new service issue
        try {
            require_once __DIR__ . '/ServiceHealth.php';
            // a new issue stops it only when it holds the service (a block, a refused key, a used-up quota, or every server
            // down with nothing else to answer); one server timing out while others or our copy answer doesn't (2026-11-17:
            // W1 stopped on one overpass.kumi.systems timeout)
            foreach (ServiceHealth::openAll($pdo) as $si) if (in_array((string) $si['service'], $svcs, true) && strtotime((string) $si['first_at'] . ' UTC') >= $since && self::issueHolds($pdo, $si)) { [$t] = ServiceHealth::describe($si); return ['fail', 'A new service issue (#' . (int) $si['id'] . '): ' . $t . '.', (int) $si['id']]; }
        } catch (Throwable $x) {}
        // nothing out of budget
        foreach ($svcs as $svc) { $s = Operations::status($pdo, $svc); if ($s[0] === 'out') return ['fail', (OutsideServices::COMPANIES[$svc][0] ?? $svc) . ' is out of budget: ' . $s[1]]; }
        // its runs since it came on: good ones, and failures no more than one in ten
        [$good, $bad] = self::runs($pdo, $id, $jobs, $secs);
        if ($bad > 1 && $bad * 10 > $good + $bad) return ['fail', $bad . ' of its ' . ($good + $bad) . ' runs failed since it came on (more than one in ten).'];
        $gone = $secs >= $hours * 3600;
        if ($good >= $need || $gone) return ['pass', ($good >= $need ? $good . ' good run' . ($good === 1 ? '' : 's') : 'Clean for ' . self::num($hours) . ' hours') . ', no new service issue, nothing out of budget'];
        return ['wait', $good . ' of ' . $need . ' good runs so far; clean so far; watched until ' . $need . ' good runs or ' . self::num($hours) . ' hours (' . (int) floor($secs / 60) . ' min in)'];
    }

    /** Does this open issue hold its service (so a workflow using it must wait)? Operations::status decides, server by server. */
    private static function issueHolds(PDO $pdo, array $si): bool
    {
        if (!ServiceHealth::holding($si)) return false;
        try { return Operations::status($pdo, (string) $si['service'])[0] === 'held'; } catch (Throwable $x) { return true; }
    }

    /** Its runs since it came on: [good, failed]. The site's jobs (job_runs) and, for the crawler's part, the crawler's runs. */
    private static function runs(PDO $pdo, string $id, array $jobs, int $secs): array
    {
        $good = 0; $bad = 0; $secs = max(1, $secs);
        if ($jobs) try {
            $in = implode(',', array_map(fn ($j) => $pdo->quote($j), $jobs));
            $r = $pdo->query('SELECT COALESCE(SUM(ok = 1), 0) g, COALESCE(SUM(ok = 0), 0) b FROM ' . Database::t('job_runs') . " WHERE job IN ($in) AND started_at >= NOW() - INTERVAL $secs SECOND")->fetch(PDO::FETCH_ASSOC);
            $good += (int) $r['g']; $bad += (int) $r['b'];
        } catch (Throwable $x) {}
        if ($id === 'wf:W0') try {   // the indexer's batches since it came on: each receipt that matched is a good run
            $r = $pdo->query('SELECT COALESCE(SUM(ok = 1), 0) g, COALESCE(SUM(ok = 0), 0) b FROM ' . Database::t('crawl_batches') . " WHERE kind <> 'test' AND at >= NOW() - INTERVAL $secs SECOND")->fetch(PDO::FETCH_ASSOC);
            return [$good + (int) $r['g'], $bad + (int) $r['b']];
        } catch (Throwable $x) { return [$good, $bad]; }
        $crawler = str_starts_with($id, 'wf:') ? str_contains((string) (Automation::WORKFLOWS[substr($id, 3)][2] ?? ''), 'crawler') : (bool) self::TASKS[substr($id, 2)][4];
        // a task's own crawler outcomes (2026-11-18, folded into decisions/0171's releases): "nothing to do" is a good run,
        // so a task like the identity recheck isn't watched until the 6-hour mark only because it had no work
        $own = str_starts_with($id, 't:') ? (array) (self::TASKS[substr($id, 2)][4] ?? []) : [];
        if ($own) try {
            $in = implode(',', array_map(fn ($t) => $pdo->quote($t), $own));
            $r = $pdo->query("SELECT COALESCE(SUM(outcome IN ('done', 'idle')), 0) g, COALESCE(SUM(outcome = 'failed'), 0) b FROM crawl_run_items WHERE task IN ($in) AND at >= NOW() - INTERVAL $secs SECOND")->fetch(PDO::FETCH_ASSOC);
            return [$good + (int) $r['g'], $bad + (int) $r['b']];
        } catch (Throwable $x) {}
        if ($crawler && $id !== 't:recheck') try {
            $r = $pdo->query("SELECT COALESCE(SUM(status IN ('ok', 'partial', 'done')), 0) g, COALESCE(SUM(status = 'failed'), 0) b FROM crawl_runs WHERE started_at >= NOW() - INTERVAL $secs SECOND")->fetch(PDO::FETCH_ASSOC);
            $good += (int) $r['g']; $bad += (int) $r['b'];
        } catch (Throwable $x) {}
        return [$good, $bad];
    }

    // ---- Pulse: one case, "Starting the system" ------------------------------------------------------------------------

    public static function pulseItems(PDO $pdo): array
    {
        $st = self::state($pdo);
        if (!in_array($st['state'] ?? '', ['running', 'stopped'], true) || empty($st['asks'])) return [];
        $n = count($st['asks']);
        return [['ops:start', $st['state'] === 'stopped' ? 'critical' : 'alert', 'Starting the system', ($st['state'] === 'stopped' ? 'Stopped at a gate. ' : '') . $n . ' thing' . ($n === 1 ? '' : 's') . ' only you can fix; the rest carries on by itself.', date('Y-m-d H:i:s', (int) $st['run']), '/admin/config/automation#start']];
    }

    public static function pulseSteps(PDO $pdo, callable $s): array
    {
        $st = self::state($pdo); $out = [];
        foreach ($st['asks'] ?? [] as [$t, $h, $act, $ext]) $out[] = $s($t, $h, $act, false, (string) $ext);
        $out[] = $s('Look again now', 'Once you\'ve fixed one, the sequence carries on by itself within 10 minutes; this looks at once.', ['do', 'ops_start_tick', 'Look again']);
        return $out;
    }

    public static function pulseDo(PDO $pdo, string $what, int $uid, string $who): string
    {
        [$ok, $why] = self::can();
        if (!$ok) throw new RuntimeException($why . '.');
        $st = self::state($pdo);
        if ($what === 'ops_start_junk') {
            require_once __DIR__ . '/DataCleanup.php';
            $r = DataCleanup::run($pdo, 'junk_name', $uid, $who);
            require_once __DIR__ . '/SearchIndex.php'; SearchIndex::setState($pdo, 'ops:junk_live', '');
            self::tick($pdo);
            return 'Data cleanup\'s junk-name pass ran' . (!empty($r['id']) ? ' (run #' . (int) $r['id'] . ', with Undo on Data cleanup)' : '') . '.';
        }
        if (in_array($what, ['ops_start_go', 'ops_start_skip'], true)) {   // a stopped step (each stops alone since 2026-11-17)
            foreach ($st['items'] as $id => $it) if ($it['s'] === 'stopped') {
                if ($what === 'ops_start_go') { self::turnOn($pdo, $id, $uid); self::set($st, $id, 'on', 'Tried again by ' . $who); $st['items'][$id]['on_at'] = time(); }
                else self::set($st, $id, 'done', 'Left paused by ' . $who . '; switch it on by hand when it\'s right');
            }
            if (in_array($st['state'] ?? '', ['stopped', 'running'], true)) $st['state'] = 'running'; unset($st['stop_asks']);
            self::save($pdo, $st);
            self::tick($pdo);
            return $what === 'ops_start_go' ? 'It\'s on again and watched; the sequence carries on.' : 'It stays paused; the sequence carries on with the next.';
        }
        if ($what === 'ops_start_tick') { self::tick($pdo); return 'Looked again now: each step shows where it stands.'; }
        throw new RuntimeException('Unknown step.');
    }

    // ---- Railway (its API, through the gates) --------------------------------------------------------------------------

    private static function env(string $k): string
    {
        $v = getenv($k);
        if (($v === false || $v === '') && isset($_ENV[$k])) $v = (string) $_ENV[$k];
        return $v === false ? '' : (string) $v;
    }

    public static function railwayKeys(): bool
    {
        return self::env('RAILWAY_API_TOKEN') !== '' && self::env('RAILWAY_PROJECT_ID') !== '' && self::env('RAILWAY_SERVICE_ID') !== '';
    }

    private static function railway(PDO $pdo, string $query, array $vars): array
    {
        require_once __DIR__ . '/ServiceHealth.php';
        if (($no = ServiceHealth::allowed($pdo, 'backboard.railway.com')) !== null) throw new RuntimeException($no);
        $ch = curl_init('https://backboard.railway.com/graphql/v2');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POST => true, CURLOPT_USERAGENT => 'Traversence system start',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . self::env('RAILWAY_API_TOKEN'), 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $vars])]);
        $body = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $j = json_decode($body, true) ?: [];
        if ($code < 200 || $code >= 300 || !empty($j['errors'])) throw new RuntimeException('Railway answered ' . $code . (!empty($j['errors'][0]['message']) ? ': ' . mb_substr((string) $j['errors'][0]['message'], 0, 160) : ''));
        return (array) ($j['data'] ?? []);
    }

    /** The crawler's schedule set ($cron) or stopped (null), restarts kept at Never, then read back: [ok, words]. */
    public static function railwaySchedule(PDO $pdo, ?string $cron): array
    {
        try {
            $p = self::railway($pdo, 'query($p: String!) { project(id: $p) { environments { edges { node { id name } } } } }', ['p' => self::env('RAILWAY_PROJECT_ID')]);
            $env = ''; foreach ($p['project']['environments']['edges'] ?? [] as $e) { if ($env === '' || strtolower((string) $e['node']['name']) === 'production') $env = (string) $e['node']['id']; }
            if ($env === '') return [false, 'Railway found no environment in project RAILWAY_PROJECT_ID.'];
            $svc = self::env('RAILWAY_SERVICE_ID');
            self::railway($pdo, 'mutation($s: String!, $e: String!, $i: ServiceInstanceUpdateInput!) { serviceInstanceUpdate(serviceId: $s, environmentId: $e, input: $i) }', ['s' => $svc, 'e' => $env, 'i' => ['cronSchedule' => $cron, 'restartPolicyType' => 'NEVER']]);
            $r = self::railway($pdo, 'query($s: String!, $e: String!) { serviceInstance(serviceId: $s, environmentId: $e) { cronSchedule restartPolicyType nextCronRunAt } }', ['s' => $svc, 'e' => $env])['serviceInstance'] ?? [];
            $got = (string) ($r['cronSchedule'] ?? ''); $rp = (string) ($r['restartPolicyType'] ?? '');
            $ok = ($cron === null ? $got === '' : $got === $cron) && $rp === 'NEVER';
            return [$ok, $ok ? ($cron === null ? 'its schedule is stopped, restarts Never.' : 'schedule ' . $cron . ', restarts Never' . (!empty($r['nextCronRunAt']) ? ', next run ' . gmdate('H:i', (int) strtotime((string) $r['nextCronRunAt'])) . ' UTC' : '') . '.')
                         : 'Railway reads back schedule "' . ($got !== '' ? $got : 'none') . '", restarts ' . ($rp !== '' ? $rp : 'unknown') . '.'];
        } catch (Throwable $x) { return [false, 'Couldn\'t set it through Railway\'s API: ' . $x->getMessage() . '.']; }
    }

    private static function num(float $v): string { return floor($v) === $v ? (string) (int) $v : number_format($v, 1); }

    // ---- the progress view -------------------------------------------------------------------------------------------

    /** The card: the two buttons and each step done, waiting or held, with its reason. $action: where the forms post. */
    public static function panel(PDO $pdo, string $csrf, bool $can, string $why, string $action = ''): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $st = self::state($pdo); $state = (string) ($st['state'] ?? 'idle');
        // while it's confirming, the open card looks again itself (every 15 seconds), so the confirmation takes minutes, not
        // the scheduled job's 10-minute steps
        if ($state === 'running' && ($st['items']['verify']['s'] ?? 'pending') !== 'done' && !self::staging()) { self::$budget = 20; $st = self::tick($pdo); $state = (string) ($st['state'] ?? 'idle'); }
        $no = self::refused($pdo); $dis = !$can ? ' disabled title="' . $e($why) . '"' : ($no !== null ? ' disabled title="' . $e($no) . '"' : '');
        $back = $e($action) . '#start';   // a press comes back to the card, its answer at the top of it (2026-11-18: it reloaded at the page's top)
        $form = fn (string $do, string $label, string $confirm, bool $danger) => '<form method="post" action="' . $back . '"' . ' style="display:inline"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="au_do" value="' . $e($do) . '"><input type="hidden" name="ref" value="all">'
            . '<button class="ak-b' . ($danger ? '' : ' go') . '" type="submit" data-confirm="' . $e($confirm) . '" data-yes="' . $e($label) . '"' . ($danger ? ' data-danger' : '') . $dis . '>' . $e($label) . '</button></form>';
        $busy = in_array($state, ['running', 'stopped'], true);
        $form2 = fn (string $do, string $label, string $ref) => '<form method="post" action="' . $back . '"' . ' style="display:inline"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="au_do" value="' . $e($do) . '"><input type="hidden" name="ref" value="' . $e($ref) . '"><button class="ak-b go" type="submit"' . $dis . '>' . $e($label) . '</button></form>';
        $h = '<div class="ak-card tv-bleed au-m" id="start"><h3 class="ak-h" style="margin-top:0">Start the system</h3>'
            // the board's start-up stage (decisions/0171 §14): this card and the board press the same Start; the board shows it
            // process by process, with its alarms, shut-down and maintenance
            . '<p data-tv-status="info" data-tv-label="On the board:">Start, watch and shut down the whole system, or one process at a time, on the <a class="underline" href="/admin/config/control">System Control Board</a>. This card presses the same Start.</p>'
            . '<p class="ak-note">One press runs the whole switch-on on the server (the page can close): the keys, watching, the switch-on check with what it can fix by itself, Railway\'s schedule, the master switch, then W1 to W12 one at a time and the new tasks, each starting only once the one before has run cleanly. Anything only you can fix is a step in Pulse ("Starting the system"); fix it and it carries on by itself.</p>'
            . '<p>' . (!$busy ? $form('start', 'Start the system', 'Start the system? The switch-on runs by itself, one workflow at a time, each at half budget for its first 14 days. Pause everything stops it at any time.', false) . ' ' : '')
            . $form('pause_all', 'Pause everything', 'Pause everything? The master switch goes off and Railway\'s schedule stops. Business operations carry on.', true) . '</p>';
        if ($no !== null) $h .= '<p data-tv-status="info" data-tv-label="Here:">' . $e($no) . '</p>';
        if (self::$said !== null) $h .= '<p data-tv-status="' . (self::$said[0] ? 'ok' : 'bad') . '" data-tv-label="' . (self::$said[0] ? 'Done:' : 'Didn\'t work:') . '">' . $e(self::$said[1]) . '</p>';   // the answer to the press, on the card
        // self-contained (Jason, 2026-11-17: "the start system needs to function as a selfcontained process, so if there is a
        // failure/fix it needs to be inline with the START up"): no pre-flight result here; each step shows what holds it and
        // its fixes under it, with Check again. The pre-flight stays its own diagnostic page, linked.
        $h .= '<p class="ak-note" style="margin:.2rem 0">For a deeper look at every service and setting: <a class="underline" href="/admin/config/preflight">the pre-flight</a> (diagnostics; Start doesn\'t wait on it).</p>';
        $tone = ['running' => 'info', 'stopped' => 'bad', 'done' => 'ok', 'paused' => 'info', 'idle' => 'info'][$state] ?? 'info';
        $words = ['idle' => 'Never pressed.', 'running' => self::runLabel(self::session($st)) === 'Starting' ? 'Starting, step by step.' : self::runLabel(self::session($st)) . '.', 'stopped' => 'Stopped at a gate: the stopped step below says why, with its fixes.', 'done' => 'Start ran to its end.', 'paused' => 'Paused everything.'][$state] ?? $state;   // "Everything is on" was untrue once a task was off (0175 R2a, item 4): what's paused now is the line at the top of the page
        $since = !empty($st['run']) ? ' Started ' . self::when((int) $st['run']) . (!empty($st['by']) ? ' by ' . $e($st['by']) : '') . '.' : '';
        if ($state === 'done') $since .= ' What\'s paused now is at the top of this page.';
        $h .= '<p data-tv-status="' . $tone . '" data-tv-label="Now:">' . $e($words) . $since . '</p>';
        // the steps as a queue, like the pre-flight (Jason, 2026-11-17): one line per step, its mark (✓ done, a spinner while
        // it runs, ✗ held or stopped, a dash waiting its turn), its name, where it stands and when; a line opens (▾) to what
        // it found and its fixes; the check step opens to Check before switching on, line by line
        $h .= '<style>#start{min-width:0;max-width:100%;overflow-wrap:anywhere}#start .st-q,#start .st-runs{list-style:none;margin:.4rem 0;padding:0;display:grid;grid-template-columns:minmax(0,1fr);gap:2px}#start .st-q>li,#start .st-runs>li{min-width:0}#start .st-b{min-width:0}#start table{table-layout:fixed;width:100%}#start td{white-space:normal!important;overflow-wrap:anywhere}#start form{display:inline}#start .st-q details>summary{display:flex;align-items:center;gap:.5rem;padding:.2rem .5rem;min-height:2rem;cursor:pointer;list-style:none;border-radius:.4rem;background:#fff;border:1px solid #eadfce}#start .st-q details>summary::-webkit-details-marker{display:none}#start .st-q details>summary::after{content:"\25BE";color:#92400E}#start .st-q details[open]>summary::after{content:"\25B4"}#start .st-n{flex:1 1 0;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}#start .st-s{font-size:.8rem;color:#8A7355}#start .st-m{display:inline-flex;width:1.3rem;height:1.3rem;border-radius:50%;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex:0 0 auto}#start .st-ok{background:#E3F1E8;color:#1F5C43}#start .st-bad{background:#FBE3E3;color:#991B1B}#start .st-on{border:2px solid #B45309;border-right-color:transparent;animation:st-spin 1s linear infinite}#start .st-wait{background:#F3EDE3;color:#8A7355}#start .st-b{padding:.35rem .6rem .5rem 2.3rem}@keyframes st-spin{to{transform:rotate(360deg)}}@media (max-width:767px){#start .st-w{display:none}#start .st-b{padding-left:.6rem}}</style>';
        if (!empty($st['items'])) {
            $asks = (array) ($st['asks'] ?? []);
            $lastRuns = [];   // each workflow's latest crawler run, for Active status
            try { require_once __DIR__ . '/crawler/CrawlService.php'; if (CrawlService::ready($pdo)) foreach (CrawlService::tasks($pdo) as $t) { $w = (string) $t['wf']; if ($t['last_served_at'] && (!isset($lastRuns[$w]) || $t['last_served_at'] > $lastRuns[$w]['last_served_at'])) $lastRuns[$w] = $t; } } catch (Throwable $x) {}
            $n = ['done' => 0, 'on' => 0, 'held' => 0, 'pending' => 0];
            foreach (array_keys(self::items()) as $id) { $x = (string) ($st['items'][$id]['s'] ?? 'pending'); $x = $x === 'stopped' ? 'held' : $x; $n[$x] = ($n[$x] ?? 0) + 1; }
            $h .= '<p class="st-c" style="margin:.4rem 0 0"><span class="st-m st-ok">&#10003;</span> ' . $n['done'] . ' done · ' . ($n['on'] ? '<span class="st-m st-on"></span> ' . $n['on'] . ' running · ' : '') . ($n['held'] ? '<span class="st-m st-bad">!</span> ' . $n['held'] . ' held · ' : '') . '<span class="st-m st-wait">&ndash;</span> ' . $n['pending'] . ' waiting their turn</p>';
            $mineAny = function (string $id) use ($asks, $pdo): bool {   // does this step have its own fixes listed (then its status line isn't repeated)
                $sc = []; if (str_starts_with($id, 'wf:') || str_starts_with($id, 't:')) { try { [$pw, $ps] = self::parts($pdo, $id); $sc = array_merge([$pw], $ps); } catch (Throwable $x) {} }
                foreach ($asks as $a) if (in_array($id, (array) ($a[4] ?? []), true) || ($sc && in_array((string) ($a[5] ?? ''), $sc, true))) return true;
                return false;
            };
            $h .= '<ol class="st-q">';
            foreach (self::items() as $id => $name) {
                $it = $st['items'][$id] ?? ['s' => 'pending', 'why' => ''];
                $k = ['done' => 'ok', 'on' => 'on', 'held' => 'bad', 'stopped' => 'bad', 'pending' => 'wait'][$it['s']] ?? 'wait';
                $pWf = ''; $pWhy = null;   // a workflow a person paused after its turn (2026-11-18: W1 "Left paused", with no way back on from the card)
                if (in_array($it['s'], ['done', 'on'], true) && (str_starts_with($id, 'wf:') || $id === 't:W14')) { $pWf = $id === 't:W14' ? 'W14' : substr($id, 3); try { $pWhy = self::personPaused($pdo, $pWf) ?? (str_starts_with((string) $it['why'], 'Left paused') && Automation::wfOff($pdo, $pWf) !== null ? (string) $it['why'] : null); } catch (Throwable $x) {} }
                if ($pWhy !== null) $k = 'bad';
                $mark = '<span class="st-m st-' . $k . '" aria-hidden="true">' . ['ok' => '&#10003;', 'bad' => '!', 'on' => '', 'wait' => '&ndash;'][$k] . '</span>';
                $body = '<p data-tv-status="' . (['ok' => 'ok', 'bad' => 'bad'][$k] ?? 'info') . '" data-tv-label="' . $e(self::LABELS[$it['s']] ?? $it['s']) . ':" style="margin:0">' . $e((string) $it['why']) . '</p>';
                if (isset($st['verify'][$id])) $it['ver'] = $st['verify'][$id];
                if (str_starts_with($id, 'wf:') || str_starts_with($id, 't:')) $body = self::lifeLines($pdo, $id, $it, $lastRuns, $e) . (in_array($it['s'], ['held', 'stopped'], true) && !$mineAny($id) ? $body : '');   // its why is in the four lines, except what holds it
                // what holds this step, each with its fix: a button that does it here, or the exact page where only a person can
                $mine = []; $scopes = [];
                if (str_starts_with($id, 'wf:') || str_starts_with($id, 't:')) { try { [$pw, $ps] = self::parts($pdo, $id); $scopes = array_merge([$pw], $ps); } catch (Throwable $x) {} }
                foreach ($asks as $a) if (!in_array((string) ($a[2][1] ?? ''), ['ops_start_go', 'ops_start_skip'], true) && in_array($id, (array) ($a[4] ?? []), true) || ($scopes && in_array((string) ($a[5] ?? ''), $scopes, true))) $mine[] = $a;
                // the fix done here, never by going to another page (Jason, 2026-11-18: "Fix it can't take me to another page. I
                // already explained this"): a hub's licence sources each with Read its terms and Terms read: allowed; any other
                // page's fix opens in a drawer over this one
                $hubDone = false;
                foreach ($mine as $a) {
                    $fx = '';
                    $isHub = str_starts_with((string) $a[0], 'Opening a geo-hub');
                    if ($isHub) { if ($hubDone) continue; $hubDone = true; $fx = self::hubTermsInline($pdo, $form2, $e); }
                    elseif (preg_match('/Issue #(\d+)/', (string) $a[1], $im)) { $isHub = true; $fx = self::issueInline($pdo, (int) $im[1], (string) ($a[5] ?? ''), $form2, $e); }   // a service issue: what happened, what to do, its buttons, here (2026-11-18: the drawer didn't say how to fix it)
                    elseif (!empty($a[2][0]) && $a[2][0] === 'do') $fx = $form2('start_do', (string) $a[2][2], (string) $a[2][1]);
                    elseif (!empty($a[2][0]) && $a[2][0] === 'tool') $fx = '<a class="ak-b" href="' . $e((string) $a[2][1]) . '" data-peek-frame="' . $e((string) $a[0]) . '">Fix it here</a>';
                    elseif (!empty($a[3]) && class_exists('Pulse', false) && isset(Pulse::SERVICES[(string) $a[3]][1])) $fx = '<a class="ak-b" href="' . $e((string) Pulse::SERVICES[(string) $a[3]][1]) . '" target="_blank" rel="noopener">Open ' . $e((string) Pulse::SERVICES[(string) $a[3]][0]) . '</a>';
                    $body .= '<p data-tv-status="bad" data-tv-label="' . $e((string) $a[0]) . ':" style="margin:.3rem 0 0">' . $e((string) $a[1]) . '</p>' . ($fx !== '' && $isHub ? $fx : '') . ($fx !== '' && !$isHub ? '<p style="margin:.2rem 0 0">' . $fx . '</p>' : '');
                }
                if ($it['s'] === 'stopped') {   // its fixes on the step itself (Jason, 2026-11-17): Pulse gets a notice, never the only way
                    $iss = (int) ($it['issue'] ?? 0) ?: (preg_match('/service issue \(#(\d+)\)/', (string) $it['why'], $mm) ? (int) $mm[1] : 0);
                    // the one right action, named (Jason, 2026-11-18: "how do I know what the right action is")
                    $holdsNow = false;
                    if ($iss) { try { require_once __DIR__ . '/ServiceHealth.php'; foreach (ServiceHealth::openAll($pdo) as $si) if ((int) $si['id'] === $iss && self::issueHolds($pdo, $si)) $holdsNow = true; } catch (Throwable $x) {} }
                    $todo = !$iss ? 'Press Try it again: it switches back on and is watched; if the same thing happens it stops again.'
                        : ($holdsNow ? 'Press ' . self::issueVerb($pdo, $iss) . ': the service is asked again, and once it answers this carries on by itself (a passing check is what closes it).'
                                     : 'Press Check again: issue #' . $iss . ' no longer holds its service, so this carries on at once. (Nothing to fix.)');
                    $body .= '<p data-tv-status="info" data-tv-label="What to do:" style="margin:.35rem 0 0">' . $e($todo) . '</p>';
                    $body .= '<p style="margin:.35rem 0 0">' . $form2('start_do', 'Try it again', 'ops_start_go') . ' ' . $form2('start_do', 'Leave it paused, carry on', 'ops_start_skip')
                        . ($iss ? ' ' . self::issueButtons($pdo, $iss, $form2) : '') . '</p>';
                }
                if (in_array($it['s'], ['held', 'stopped', 'on'], true)) $body .= '<p style="margin:.35rem 0 0">' . $form2('start_tick', 'Check again', $id) . ' <small class="ak-note">Looks again now, on the server, and carries on if it\'s clear.</small></p>';
                if ($id === 'check') $body .= '<p class="ak-note" style="margin:.35rem 0 0"><a class="ak-b" href="/admin/config/board#check">Every line of the check</a></p>';
                if ($pWhy !== null) { $mark = '<span class="st-m st-bad" aria-hidden="true">!</span>'; $body .= '<p data-tv-status="bad" data-tv-label="Paused:" style="margin:.3rem 0 0">' . $e($pWhy) . '</p><p data-tv-status="info" data-tv-label="What to do:" style="margin:.3rem 0 0">Press Switch ' . $e($pWf) . ' on when it\'s right: it runs again and is watched like the rest.</p><p style="margin:.3rem 0 0">' . $form2('wf_on', 'Switch ' . $pWf . ' on', $pWf) . '</p>'; }
                $h .= '<li><details' . ($k === 'bad' ? ' open' : '') . '><summary>' . $mark . '<span class="st-n">' . $e($name) . '</span><span class="st-s">' . $e($pWhy !== null ? 'Paused' : (self::LABELS[$it['s']] ?? $it['s'])) . '</span>' . (!empty($it['at']) ? '<span class="st-w"><small>' . self::when((int) $it['at']) . '</small></span>' : '') . '</summary><div class="st-b">' . $body . '</div></details></li>';
            }
            $h .= '</ol>';
        }
        // the run log (Jason, 2026-11-17): one line per Start session, newest first, its status, start and end, who and counts;
        // each opens to its report: every step with where it ended up, then every event of that session in order
        $sess = [];
        try { require_once __DIR__ . '/SearchIndex.php'; $sess = json_decode(SearchIndex::state($pdo, self::SESSIONS), true); $sess = is_array($sess) ? $sess : []; } catch (Throwable $x) {}
        if (!empty($st['run'])) { $sess = array_values(array_filter($sess, fn ($x) => (int) ($x['run'] ?? 0) !== (int) $st['run'])); $sess[] = self::session($st); }
        if ($sess) {
            $names = self::items(); $sw = ['running' => 'Starting', 'stopped' => 'Stopped at a gate', 'done' => 'Everything on', 'paused' => 'Paused everything', 'idle' => 'Ended'];
            $lb = '<ol class="st-runs" style="list-style:none;margin:.3rem 0 0;padding:0;display:grid;gap:2px">';
            foreach (array_reverse($sess) as $x) {
                $n = ['done' => 0, 'on' => 0, 'held' => 0, 'pending' => 0];
                foreach ($x['items'] as $it) { $k = $it['s'] === 'stopped' ? 'held' : $it['s']; $n[$k] = ($n[$k] ?? 0) + 1; }
                $live = in_array($x['state'], ['running', 'stopped'], true) && (int) $x['run'] === (int) ($st['run'] ?? 0);
                $label = self::runLabel($x);
                $rep = '<table style="width:100%;border-collapse:collapse;font-size:.85rem;margin:.3rem 0"><tbody>';
                foreach ($names as $id => $nm) { if (!isset($x['items'][$id])) continue; $it = $x['items'][$id]; $rep .= '<tr style="border-top:1px solid #eadfce"><td style="padding:.15rem .4rem">' . $e($nm) . '</td><td style="padding:.15rem .4rem;white-space:nowrap">' . $e(self::LABELS[$it['s']] ?? $it['s']) . '</td><td style="padding:.15rem .4rem">' . $e($it['why']) . '</td><td style="padding:.15rem .4rem;white-space:nowrap"><small>' . ($it['at'] ? self::when($it['at']) : '') . '</small></td></tr>'; }
                $rep .= '</tbody></table><p class="ak-note" style="margin:.3rem 0 .1rem"><b>Every event, in order</b></p><ul style="margin:0;padding-left:1rem;font-size:.85rem">';
                foreach ($x['log'] as [$at, $line]) $rep .= '<li>' . self::when((int) strtotime($at . ' UTC')) . ' ' . $e($line) . '</li>';
                $rep .= '</ul>';
                $lb .= '<li><details style="background:#fff;border:1px solid #eadfce;border-radius:.4rem"><summary style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;padding:.25rem .5rem;cursor:pointer"><b>' . $e($label) . '</b><span>Started ' . self::when((int) $x['run']) . ' · ' . ($live ? 'last update ' : 'ended ') . self::when((int) $x['end']) . '</span><span>' . $e($x['by']) . '</span><span class="st-s">&#10003; ' . $n['done'] . ($n['on'] ? ' · running ' . $n['on'] : '') . ($n['held'] ? ' · held ' . $n['held'] : '') . ' · waiting ' . $n['pending'] . '</span><span style="margin-left:auto;display:inline-flex;gap:.3rem"><a class="ak-b" href="/admin/api/config/automation?action=report&amp;run=' . (int) $x['run'] . '" download>Download report</a><a class="ak-b" href="/admin/api/config/automation?action=report&amp;view=1&amp;run=' . (int) $x['run'] . '" target="_blank" rel="noopener">Open</a></span></summary><div style="padding:.2rem .6rem .5rem">' . $rep . '</div></details></li>';
            }
            $h .= '<details class="st-log" open><summary>Run log: each Start, newest first</summary>' . $lb . '</ol></details>';
        }
        // while it runs, the card follows it: drawn again from the server every 15 seconds, the open lines kept open
        if ($state === 'running') $h .= '<script>(function(){var c=document.getElementById("start");if(!c||c.dataset.f)return;c.dataset.f=1;function t(){fetch(location.href,{credentials:"include"}).then(function(r){return r.text();}).then(function(x){var n=new DOMParser().parseFromString(x,"text/html").getElementById("start");if(!n)return;var o=[].map.call(c.querySelectorAll(".st-q details[open] .st-n,.st-log[open]>summary"),function(e){return e.textContent;});c.innerHTML=n.innerHTML;c.dataset.f=1;[].forEach.call(c.querySelectorAll(".st-q details .st-n,.st-log>summary"),function(e){if(o.indexOf(e.textContent)>=0)(e.closest("details")).open=true;});if(window.TvTime)TvTime.paint(c);if(n.querySelector(".st-m.st-on")||/Starting/.test(n.textContent))setTimeout(t,15000);}).catch(function(){setTimeout(t,30000);});}setTimeout(t,15000);})();</script>';
        return $h . '</div>';
    }

    /** A workflow's or task's life in the start (Jason, 2026-11-17: "started, running, complete, active status"). */
    private static function lifeLines(PDO $pdo, string $id, array $it, array $lastRuns, callable $e): string
    {
        $s = (string) ($it['s'] ?? 'pending'); $on = (int) ($it['on_at'] ?? 0); $done = (int) ($it['done_at'] ?? 0) ?: ($s === 'done' ? (int) ($it['at'] ?? 0) : 0);
        $row = fn (string $label, string $tone, string $html) => '<p data-tv-status="' . $tone . '" data-tv-label="' . $e($label) . ':" style="margin:.15rem 0">' . $html . '</p>';
        $v = $it['ver'] ?? null;
        $h = $row('Confirmed', $v ? ($v['s'] === 'bad' ? 'bad' : 'ok') : 'info', $v ? ($v['s'] === 'bad' ? 'Not operational yet' : 'Operational') . ' ' . self::when((int) $v['at']) . ($v['s'] === 'bad' ? ': ' . $e((string) $v['said']) : '') : 'Not tested yet');
        $h .= $row('Started', $on ? 'ok' : 'info', $on ? 'Switched on ' . self::when($on) : ($s === 'held' ? 'Not yet: held (below)' : 'Not yet: waiting its turn'));
        $run = '';
        if ($on) {
            try { [, , $jobs] = self::parts($pdo, $id); [$good, $bad] = self::runs($pdo, $id, $jobs, max(0, ($done ?: time()) - $on)); $run = $good . ' good run' . ($good === 1 ? '' : 's') . ($bad ? ', ' . $bad . ' failed' : '') . ' since it came on'; } catch (Throwable $x) {}
        }
        $h .= $row('Running', $s === 'on' ? 'info' : ($on ? 'ok' : 'info'), $e($s === 'on' ? ($run !== '' ? $run . '. ' : '') . (string) $it['why'] : ($on ? ($run !== '' ? $run : 'Watched') . ($done ? ', until it passed its gate' : '') : 'Not yet')));
        $h .= $row('Complete', $done ? 'ok' : 'info', $done ? 'Passed its gate ' . self::when($done) . ($s === 'done' && (string) $it['why'] !== '' ? ': ' . $e((string) $it['why']) : '') : 'Not yet');
        $now = '';
        try {
            $wf = str_starts_with($id, 'wf:') ? substr($id, 3) : (self::TASKS[substr($id, 2)][1] ?? '');
            if (!in_array($s, ['done', 'on'], true)) $now = $s === 'held' ? 'Off: held (below)' : 'Off until its turn';
            elseif (str_starts_with($id, 'wf:')) { $off = Automation::wfOff($pdo, $wf); $now = $off !== null ? 'Paused: ' . $off : (Automation::master($pdo) ? 'On, held by the master switch' : 'Active'); }
            else $now = 'Active';
            $l = $lastRuns[$wf] ?? null;
            if ($l) $now .= '. Last run: ' . $l['name'] . ', ' . $l['last_served_at'] . ' (server time): ' . strtolower((string) (class_exists('CrawlService', false) ? (CrawlService::OUTCOMES[(string) $l['last_outcome']] ?? $l['last_outcome']) : $l['last_outcome']));
        } catch (Throwable $x) {}
        $h .= $row('Active status', str_starts_with($now, 'Active') ? 'ok' : 'info', $e($now !== '' ? $now : 'Unknown'));
        return $h;
    }

    /**
     * A service issue's buttons on the card, the board's own (Jason, 2026-11-19: "If this was resolved by system or marked
     * as resolved by admin it should be handled"; no bare Mark resolved): Check and start again when its alarm tripped (it
     * checks, closes the trip and the issue, and starts again what it stopped), else Check issue now (a passing check
     * closes it), and It's fixed: try it again only where nothing can check it (its next call is the check).
     */
    private static function issueButtons(PDO $pdo, int $iss, callable $form2): string
    {
        [$verb, $act, $ref] = self::issueAct($pdo, $iss);
        return $form2($act, $verb, $ref);
    }

    /** The one button's words, for "What to do". */
    private static function issueVerb(PDO $pdo, int $iss): string { return self::issueAct($pdo, $iss)[0]; }

    /** [words, action, its ref] for a service issue: its tripped alarm's resolve, else its check, else try it again. */
    private static function issueAct(PDO $pdo, int $iss): array
    {
        try {
            require_once __DIR__ . '/ControlAlarms.php'; require_once __DIR__ . '/ServiceHealth.php';
            $al = ControlAlarms::openOf($pdo, 'svc:issue:' . $iss);
            if ($al && $al['level'] === 'trip' && !empty($al['tripped_at'])) return ['Check and start again', 'start_issue_restart', (string) (int) $al['id']];
            foreach (ServiceHealth::openAll($pdo) as $si) if ((int) $si['id'] === $iss && (empty($si['probe_url']) || ServiceHealth::noProbe($si))) return ['It\'s fixed: try it again', 'start_issue_resolve', (string) $iss];
        } catch (Throwable $x) {}
        return ['Check issue #' . $iss . ' now', 'start_issue_check', (string) $iss];
    }

    /** A service issue on the card: what happened, what to do in plain words, and its one button (issueButtons), and the
     *  service's own page when a person must act there. */
    private static function issueInline(PDO $pdo, int $iss, string $scope, callable $form2, callable $e): string
    {
        try { require_once __DIR__ . '/ServiceHealth.php'; $r = null; foreach (ServiceHealth::openAll($pdo) as $si) if ((int) $si['id'] === $iss) $r = $si; } catch (Throwable $x) { $r = null; }
        if (!$r) return '<p data-tv-status="ok" data-tv-label="What to do:" style="margin:.3rem 0 0">Issue #' . $iss . ' is resolved already. Press Check again.</p>';
        // what happened: the issue's thread, its title and status (decisions/0176 §3: one thread, the same words everywhere)
        require_once __DIR__ . '/Thread.php'; $sf = ServiceHealth::facts($pdo, $r); $why = rtrim($sf['title'], '.') . '. ' . Thread::plain((string) $sf['status']);
        $k = ServiceHealth::KINDS[$r['kind']] ?? [$r['kind'], 'back', ''];
        $co = OutsideServices::COMPANIES[$r['service']][0] ?? $r['service'];
        $holds = $scope !== '' && $scope !== 'all' ? ' It holds only what uses ' . $co . ' (' . (isset(Automation::WORKFLOWS[$scope]) ? $scope : $co) . '); everything else runs.' : '';
        $todo = $k[1] === 'stop'
            ? 'A person decides. ' . $co . ' ' . strtolower($k[0]) . ': ' . $k[2] . ' When it\'s sorted, press ' . self::issueVerb($pdo, $iss) . ': a passing check is what closes it.'
            : 'Nothing for you to fix: it\'s ' . $co . '\'s own server (' . strtolower($k[0]) . '). The site waits and asks again by itself, and carries on when it answers. To try now, press ' . self::issueVerb($pdo, $iss) . '.';
        $h = '<p class="ak-note" style="margin:.3rem 0 0"><b>What happened:</b> ' . $e($why) . ' <a class="underline" href="/admin/config/register#issue-' . $iss . '" data-peek-frame>Its issue on the card</a></p>'
           . '<p data-tv-status="info" data-tv-keep data-tv-label="What to do:" style="margin:.3rem 0 0">' . $e($todo . $holds) . '</p>'
           . '<p style="margin:.3rem 0 0">' . self::issueButtons($pdo, $iss, $form2);
        if ($k[1] === 'stop' && class_exists('Pulse', false) && isset(Pulse::SERVICES[(string) $r['service']][1])) $h .= ' <a class="ak-b" href="' . $e((string) Pulse::SERVICES[(string) $r['service']][1]) . '" target="_blank" rel="noopener">' . $e($co) . '\'s own page</a>';
        return $h . '</p>';
    }

    /** A hub's licence sources whose terms aren't read, each with its own two buttons, on the card (the terms are the
     *  source's own page, opened beside this one to read; marking them read happens here). */
    private static function hubTermsInline(PDO $pdo, callable $form2, callable $e): string
    {
        try {
            require_once __DIR__ . '/HubOpening.php';
            $rows = array_filter(HubOpening::sources($pdo), fn ($r) => $r['terms_read_at'] === null && (string) $r['real_use'] !== 'blocked');
        } catch (Throwable $x) { return ''; }
        if (!$rows) return '<p data-tv-status="ok" data-tv-label="Done:" style="margin:.3rem 0 0">Every source\'s terms are read. Press Check again.</p>';
        $h = '<p class="ak-note" style="margin:.35rem 0 .2rem">For each one: read its terms (they open beside this page), then press <b>Terms read: allowed</b> only if they allow us to read it automatically. One that doesn\'t allow it: leave it, W14 waits for it alone.</p><ul style="list-style:none;margin:0;padding:0;display:grid;gap:.3rem">';
        foreach ($rows as $r) {
            $h .= '<li style="background:#fff;border:1px solid #eadfce;border-radius:.4rem;padding:.35rem .5rem"><b>' . $e($r['state'] . ', ' . $r['name']) . '</b> <small class="ak-note">' . $e(HubOpening::USES[$r['real_use']] ?? $r['real_use']) . '</small><br>'
                . '<a class="ak-b" href="' . $e((string) ($r['terms_url'] ?: $r['url'])) . '" target="_blank" rel="noopener">Read its terms</a> ' . $form2('start_hub_terms', 'Terms read: allowed', (string) (int) $r['id']) . '</li>';
        }
        return $h . '</ul>';
    }

    private static function when(int $ts): string
    {
        return '<time datetime="' . gmdate('c', $ts) . '" data-tv-time>' . gmdate('M j, g:i A', $ts) . ' UTC</time>';
    }
}
