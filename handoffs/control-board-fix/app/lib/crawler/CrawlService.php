<?php

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/CrawlQueue.php';

/**
 * app/lib/crawler/CrawlService.php — the crawler as one service (decisions/0160 §2; handoffs/crawler-revamp.md Phase 2).
 *
 * The crawler fetches and reads for whoever asks; each creation tool is a workflow (W1–W13) that configures it with
 * capture tasks (what, from where, how often, its share and limits). Each 5-minute run of the worker:
 *   1. starts with plan(): the capture tasks due, in order (finding businesses first, then whoever waited longest), each
 *      with its share of the run's time and fetches, and how many items fit in that time. Paused workflows and switched
 *      off tasks aren't in it. The monthly bulk loads are tasks of their own, run only at night and after the rest;
 *   2. works each task inside its share: a task takes work only when it has the time to do it;
 *   3. ends with report(): what each task did (done, idle, not reached, failed, off) and why, its fetches and paid calls.
 *      A task not reached is never a failure and never a result: it's simply first next time.
 * One queue of requests (crawl_requests) lets a workflow ask for one piece of work ("read this page"); the worker takes
 * requests only inside a task's share and gives back what it didn't do, unchanged.
 * The cost ledger (crawl_costs) counts every paid call the worker makes, failed ones included; Claude's are in ai_jobs.
 * stalled() replaces the old "crawler has stopped" alert, which watched only the coverage queue.
 * The contract with the worker is versioned (CONTRACT): an older worker never calls this and runs its fixed order as before.
 */
final class CrawlService
{
    public const CONTRACT = 2;
    /** Seconds one item of a task usually takes: how many items fit in a share. */
    private const PER_ITEM = ['coverage' => 30, 'second_look' => 10, 'targets' => 40, 'refresh' => 12, 'identity' => 12, 'geocode' => 60,
                              'places' => 6, 'source' => 60, 'commoncrawl' => 60, 'heritage' => 20, 'howto' => 20, 'learn' => 15];
    /** A task's weight inside its workflow's share (finding businesses first: the review's E19). Others weigh 1. */
    private const WEIGHT = ['coverage' => 3, 'second_look' => 2, 'refresh' => 2, 'identity' => 2];
    private const MIN_SHARE = 20;          // seconds: a task given less than this can't do anything useful
    public const OUTCOMES = ['done' => 'Did its work', 'idle' => 'Nothing to do', 'not_reached' => 'Not reached (no time left)',
                             'failed' => 'Failed', 'off' => 'Off (a key or a switch)', 'skipped' => 'Skipped',
                             'held' => 'Held (an outside service it needs has an issue)'];   // never a failure (ServiceHealth, Jason 2026-11-14)
    /** The night window for the bulk loads, Arizona time (they run after the run's other work, never in place of it). */
    private const NIGHT = [1, 5];
    /** Estimated prices for the worker's paid calls (estimates: the providers' bills are the record). Set in .env to change. */
    private const PRICES = ['tavily' => ['TAVILY_USD_PER_CREDIT', 0.008], 'brave' => ['BRAVE_USD_PER_SEARCH', 0.005],
                            'google_places' => ['GOOGLE_PLACES_USD_PER_CALL', 0.032], 'tavily_extract' => ['TAVILY_USD_PER_CREDIT', 0.008]];

    /**
     * One price per service (Release A's leftover, 0163 stage 2): the estimate the ledger and Services' page both use, set in
     * .env by its one name (the older names still read, so nothing set before is lost).
     */
    public static function price(string $service): float
    {
        $old = ['google_places' => 'PLACES_USD_PER_CALL', 'tavily' => 'WEBSEARCH_USD_PER_SEARCH'];
        [$env, $def] = self::PRICES[$service] ?? ['', 0.0];
        foreach ([$env, $old[$service] ?? ''] as $e) if ($e !== '' && is_numeric(getenv($e)) && getenv($e) !== '') return (float) getenv($e);
        return (float) $def;
    }

    /** The .env name a service's price is set by. */
    public static function priceEnv(string $service): string { return (string) (self::PRICES[$service][0] ?? ''); }

    public static function ready(PDO $pdo): bool
    {
        static $r = null;
        if ($r !== null) return $r;
        try { $pdo->query('SELECT 1 FROM crawl_capture_tasks LIMIT 1'); $pdo->query('SELECT 1 FROM crawl_runs LIMIT 1'); return $r = true; } catch (Throwable $e) { return $r = false; }
    }

    /** The workflows: wf => row (name, share_pct, paused…). */
    public static function workflows(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT * FROM crawl_workflows ORDER BY CAST(SUBSTRING(wf, 2) AS UNSIGNED)')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['wf']] = $r;
        return $out;
    }

    /** The capture tasks: task => row. */
    public static function tasks(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT * FROM crawl_capture_tasks ORDER BY wf, sort, task')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['task']] = $r;
        return $out;
    }

    private static function hour(): int
    {
        $d = new DateTime('now', new DateTimeZone('America/Phoenix'));
        return (int) $d->format('G');
    }

    /**
     * A run starts: it's recorded, and given its plan. $ask: {run_key, worker_v, budget, fetches, have: [tasks the worker
     * can do]}. Returns {run_id, paused, why, plan: [{task, wf, mode, seconds, fetches, paid, per_run}]}.
     */
    public static function plan(PDO $pdo, array $ask): array
    {
        $key = preg_replace('/[^a-z0-9]/i', '', (string) ($ask['run_key'] ?? '')) ?: bin2hex(random_bytes(8));
        $key = substr($key, 0, 16);
        $budget = max(30, min(1800, (int) ($ask['budget'] ?? 240)));
        $fetchBudget = max(0, min(1000, (int) ($ask['fetches'] ?? 60)));
        $have = array_flip(array_map('strval', (array) ($ask['have'] ?? [])));
        $pdo->prepare('INSERT INTO crawl_runs (run_key, worker_v, started_at, status, budget_s) VALUES (?, ?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE started_at = started_at')
            ->execute([$key, mb_substr((string) ($ask['worker_v'] ?? ''), 0, 12), 'running', $budget]);
        $runId = (int) $pdo->query('SELECT id FROM crawl_runs WHERE run_key = ' . $pdo->quote($key))->fetchColumn();
        self::tidy($pdo);
        if (($pw = CrawlQueue::pausedWhy()) !== '') {   // the staging copy, CRAWL_QUEUE=off, or the automation master switch: the run ends at once
            $pdo->prepare("UPDATE crawl_runs SET status = 'paused', why = ? WHERE id = ?")->execute([mb_substr($pw, 0, 500), $runId]);
            return ['run_id' => $runId, 'run_key' => $key, 'paused' => true, 'why' => $pw, 'plan' => []];
        }
        require_once __DIR__ . '/../Automation.php';
        $wfs = self::workflows($pdo);
        $due = [];
        $night = self::hour() >= self::NIGHT[0] && self::hour() < self::NIGHT[1];
        foreach (self::tasks($pdo) as $t => $r) {
            $wf = $wfs[$r['wf']] ?? null;
            if (!$wf || (int) $wf['paused'] || !(int) $r['enabled']) continue;
            if (Automation::sysAny($pdo, ['wf:' . (string) $r['wf'], 'task:' . (string) $t]) !== null) continue;   // paused by the system: a trip (decisions/0175 §3)
            // its source's line is red and holds only the tasks that call it (Operations::gateFor; 0175 §3, a trip stops only
            // what it holds): this task waits, the rest of its workflow runs; it's served again once the line is green
            require_once __DIR__ . '/../Operations.php';
            if (Operations::taskHeld($pdo, (string) $t, (string) $r['wf'])) continue;
            if ((string) ($r['mode'] ?? '') === 'site') continue;   // a task the site runs itself (the identity recheck): never handed to the worker
            if ($have && !isset($have[$t])) continue;   // the worker can't do it (an older worker, or a stage it doesn't have)
            $last = $r['last_served_at'] !== null ? strtotime((string) $r['last_served_at']) : 0;
            // "every" counts from the last time it was served; a task not reached was never served, so it's due at once
            if ($last && $last > time() - ((int) $r['every_minutes'] * 60) + 30) continue;
            if ($r['mode'] === 'bulk' && !$night) continue;
            $due[$t] = $r;
        }
        $run = array_filter($due, fn ($r) => $r['mode'] !== 'bulk');
        $bulk = array_filter($due, fn ($r) => $r['mode'] === 'bulk');
        // shares: each workflow's share split between its tasks due now
        $byWf = [];
        foreach ($run as $t => $r) $byWf[$r['wf']][] = $t;
        $total = 0; foreach ($byWf as $wf => $ts) $total += max(1, (int) ($wfs[$wf]['share_pct'] ?? 10));
        $plan = [];
        foreach ($run as $t => $r) {
            $w = array_sum(array_map(fn ($x) => self::WEIGHT[$x] ?? 1, $byWf[$r['wf']]));
            $share = max(1, (int) ($wfs[$r['wf']]['share_pct'] ?? 10)) / max(1, $total) * (self::WEIGHT[$t] ?? 1) / max(1, $w);
            $sec = (int) max(self::MIN_SHARE, min((int) $r['max_seconds'], floor($budget * $share)));
            $fet = (int) min((int) $r['max_fetches'], max((int) $r['max_fetches'] > 0 ? 3 : 0, floor($fetchBudget * $share)));
            // the backlog drains at half budget for 14 days after its workflow comes back on (decisions/0163 §10.2, X1)
            static $ramp = []; if (!isset($ramp[$r['wf']])) { try { require_once __DIR__ . '/../Clocks.php'; $ramp[$r['wf']] = Clocks::ramp($pdo, (string) $r['wf']); } catch (Throwable) { $ramp[$r['wf']] = 1.0; } }
            $n = (int) max(1, min((int) max(1, floor((int) $r['per_run'] * $ramp[$r['wf']])), floor($sec / (self::PER_ITEM[$t] ?? 20))));
            // its routes (WorkflowSettings, recorded in Release A, read now): the services this task may call; with none of the
            // paid ones (Google, Tavily, Brave) among them, it makes no paid calls at all
            static $specs = [];
            if (!isset($specs[$r['wf']])) { try { require_once __DIR__ . '/../WorkflowSettings.php'; $specs[$r['wf']] = WorkflowSettings::current($pdo, (string) $r['wf']); } catch (Throwable) { $specs[$r['wf']] = []; } }
            $routes = $specs[$r['wf']]['tasks'][$t]['routes'] ?? null;
            $paid = (int) $r['max_paid'];
            if (is_array($routes) && !array_intersect($routes, ['google', 'tavily', 'brave'])) $paid = 0;
            $plan[] = ['task' => $t, 'wf' => $r['wf'], 'mode' => 'run', 'seconds' => $sec, 'fetches' => $fet, 'paid' => $paid, 'per_run' => $n, 'routes' => is_array($routes) ? array_values($routes) : null,
                       '_first' => $t === 'coverage' ? 0 : 1, '_last' => $r['last_served_at'] ?? ''];
        }
        // finding businesses first (the review's E19), then whoever was served longest ago; never reached at all first of those
        usort($plan, fn ($a, $b) => [$a['_first'], $a['_last'] !== '', $a['_last']] <=> [$b['_first'], $b['_last'] !== '', $b['_last']]);
        // one bulk load a night run, after the rest: it never takes the place of the run's own work
        if ($bulk) {
            uasort($bulk, fn ($a, $b) => strcmp((string) $a['last_served_at'], (string) $b['last_served_at']));
            $t = (string) array_key_first($bulk); $r = $bulk[$t];
            $plan[] = ['task' => $t, 'wf' => $r['wf'], 'mode' => 'bulk', 'seconds' => (int) $r['max_seconds'], 'fetches' => 0, 'paid' => 0, 'per_run' => 1, '_first' => 2, '_last' => ''];
        }
        $plan = array_map(function ($p) { unset($p['_first'], $p['_last']); return $p; }, $plan);
        // outside services with an open issue (ServiceHealth): the worker never calls a held server, sends the light probes
        // due, and doesn't ask OpenStreetMap again for a town and kind asked this month (worker 2.5; an older one ignores them)
        require_once __DIR__ . '/../ServiceHealth.php';
        // the run's share of the day's OpenStreetMap budget, spread over the day and paced (Thresholds & gates), and the servers
        // to ask in order (their settings on the service's card; a held one is skipped by the holds, never left off the list)
        $or = ServiceHealth::osmRun($pdo);
        $health = ['holds' => (object) ServiceHealth::holds($pdo, 'crawler'), 'probes' => ServiceHealth::dueProbes($pdo, 'crawler'), 'osm_recent' => ServiceHealth::osmRecent($pdo),
                   'osm_budget' => ['queries' => $or['queries'], 'bytes' => $or['bytes']], 'osm_gap' => $or['gap'],
                   'servers' => ['overpass' => array_column(ServiceHealth::servers($pdo, 'osm', 'overpass'), 'url')]];
        require_once __DIR__ . '/../Thresholds.php';
        $limits = ['searches' => (int) Thresholds::get($pdo, 'worker_searches_per_run'), 'places' => (int) Thresholds::get($pdo, 'worker_places_per_run'), 'osm' => (int) Thresholds::get($pdo, 'worker_osm_per_run')];
        // paid backups a director switched on (PaidOverride), each inside its own cap: only used when nothing free worked
        $paid = []; try { require_once __DIR__ . '/../PaidOverride.php'; $paid = PaidOverride::runAllowance($pdo); } catch (Throwable $x) { error_log('paid backups: ' . $x->getMessage()); }
        return $health + ['run_id' => $runId, 'run_key' => $key, 'paused' => false, 'why' => '', 'contract' => self::CONTRACT, 'limits' => $limits, 'paid_backups' => (object) $paid, 'plan' => $plan,
                'requests' => self::leaseRequests($pdo, $runId, max(0, $budget - array_sum(array_map(fn ($p) => $p['mode'] === 'run' ? $p['seconds'] : 0, $plan))))];
    }

    /**
     * A run ends: what each task did. $b: {run_key, status: ok|failed, why, seconds, fetches, searches, engine,
     * items: [{task, outcome, seconds, allotted, fetches, searches, places, counts: {}, why}] (pages Tavily read
     * are counted where they're recorded, SecondTier::recordExtracts),
     * requests: [{id, outcome: done|failed|back, result, why}]}. Returns the run's status.
     */
    public static function report(PDO $pdo, array $b): array
    {
        $key = substr((string) preg_replace('/[^a-z0-9]/i', '', (string) ($b['run_key'] ?? '')), 0, 16);
        $run = $pdo->prepare('SELECT * FROM crawl_runs WHERE run_key = ?'); $run->execute([$key]);
        $r = $run->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new InvalidArgumentException('No such run.');
        $runId = (int) $r['id'];
        $tasks = self::tasks($pdo);
        $engine = in_array((string) ($b['engine'] ?? ''), ['tavily', 'brave'], true) ? (string) $b['engine'] : '';
        $n = ['done' => 0, 'idle' => 0, 'not_reached' => 0, 'failed' => 0, 'off' => 0, 'skipped' => 0, 'held' => 0];
        $ins = $pdo->prepare('INSERT INTO crawl_run_items (run_id, wf, task, outcome, allotted_s, seconds, fetches, searches, counts, why, at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $served = $pdo->prepare('UPDATE crawl_capture_tasks SET last_served_at = NOW(), last_outcome = ?, last_why = ? WHERE task = ?');
        $missed = $pdo->prepare('UPDATE crawl_capture_tasks SET last_outcome = ?, last_why = ? WHERE task = ?');
        $taskOut = []; $itemCalls = [];
        foreach (array_slice((array) ($b['items'] ?? []), 0, 40) as $it) {
            if (!is_array($it)) continue;
            $t = (string) ($it['task'] ?? ''); if (!isset($tasks[$t])) continue;
            $o = isset(self::OUTCOMES[$it['outcome'] ?? '']) ? (string) $it['outcome'] : 'skipped';
            $n[$o]++;
            $why = mb_substr(trim((string) ($it['why'] ?? '')), 0, 500);
            $counts = array_slice(array_filter((array) ($it['counts'] ?? []), fn ($v) => is_int($v) || is_float($v) || is_string($v)), 0, 20, true);
            $ins->execute([$runId, $tasks[$t]['wf'], $t, $o, max(0, min(65535, (int) ($it['allotted'] ?? 0))), max(0, min(65535, (int) ($it['seconds'] ?? 0))),
                           max(0, min(65535, (int) ($it['fetches'] ?? 0))), max(0, min(65535, (int) ($it['searches'] ?? 0))), $counts ? json_encode($counts, JSON_UNESCAPED_SLASHES) : null, $why !== '' ? $why : null]);
            // a task not reached was never served: it stays due, and goes first next time
            ($o === 'not_reached' ? $missed : $served)->execute([$o, $why !== '' ? mb_substr($why, 0, 255) : null, $t]);
            // the ledger: every paid call, failed ones included
            if (($s = (int) ($it['searches'] ?? 0)) > 0 && $engine !== '') self::cost($pdo, $engine, $tasks[$t]['wf'], $t, $s, $runId, 'web search');
            if (($p = (int) ($it['places'] ?? 0)) > 0) self::cost($pdo, 'google_places', $tasks[$t]['wf'], $t, $p, $runId, 'Google Places checks');
            self::calls($pdo, $runId, $t, (array) ($it['calls'] ?? []));
            $taskOut[$t] = [$o, $why]; $itemCalls[$t] = (array) ($it['calls'] ?? []);
        }
        self::calls($pdo, $runId, '', (array) ($b['calls'] ?? []));   // the run's own calls, outside any task
        // the outside services' issues the worker met (each classified, with its evidence), its probes, and the servers that
        // answered (their back-off is over); the month's OpenStreetMap asks (worker 2.5)
        require_once __DIR__ . '/../ServiceHealth.php';
        ServiceHealth::fromReport($pdo, $b);
        ServiceHealth::osmAsked($pdo, array_filter((array) ($b['osm_asked'] ?? []), 'is_array'));
        ServiceHealth::osmUse($pdo, 'crawler', (int) ($b['osm_used']['queries'] ?? 0), (int) ($b['osm_used']['bytes'] ?? 0));
        // kinds or towns our OpenStreetMap copy doesn't hold (worker 2.13.0): Overpass is retired, so they're reported, never
        // fetched; one quiet notice on W1 naming where (Jason, 2026-11-19, "Stop entirely"; ServiceHealth::OSM_RETIRED)
        $nic = (array) ($b['osm_not_in_copy'] ?? []);
        if ((int) ($nic['kinds'] ?? 0) > 0) { try {
            require_once __DIR__ . '/../ControlAlarms.php';
            $where = array_values(array_unique(array_filter(array_map(fn ($j) => is_array($j) ? mb_substr((string) ($j['place'] ?? ''), 0, 60) : '', (array) ($nic['jobs'] ?? [])))));
            ControlAlarms::raise($pdo, 'osm:not_in_copy', 'wf:W1', 'notice', 'check', 'W1: some places aren\'t in our OpenStreetMap copy',
                'Overpass is retired, so a kind or town our Geofabrik copy (Arizona, New Mexico, Utah, Colorado and Nevada) doesn\'t hold is reported here, never fetched. Those places come only from our other sources.',
                'Nothing to fix unless you want more of the map: a state added to our copy (Geofabrik, free, weekly) brings its towns in.',
                (int) $nic['kinds'] . ' kind(s) in the last run' . ($where ? ': ' . implode(', ', array_slice($where, 0, 4)) . (count($where) > 4 ? ' and ' . (count($where) - 4) . ' more' : '') : ''),
                [['Geofabrik\'s card', 'peek', '/admin/config/register#svc-geofabrik']], true, false);
        } catch (Throwable $x) { error_log('not in our copy: ' . $x->getMessage()); } }
        if (is_array($b['paid_used'] ?? null)) { try { require_once __DIR__ . '/../PaidOverride.php'; PaidOverride::recordRun($pdo, $b['paid_used'], $runId); } catch (Throwable $x) { error_log('paid backups: ' . $x->getMessage()); } }
        foreach (array_slice((array) ($b['requests'] ?? []), 0, 100) as $q) if (is_array($q)) self::finishRequest($pdo, (int) ($q['id'] ?? 0), (string) ($q['outcome'] ?? 'back'), $q['result'] ?? null, (string) ($q['why'] ?? ''));
        // the run's honest status: failed (it couldn't work), starved (it had work and did none of it), partial, idle or ok
        $why = mb_substr(trim((string) ($b['why'] ?? '')), 0, 500);
        if (($b['status'] ?? '') === 'failed') $status = 'failed';
        elseif ($n['done'] === 0 && $n['not_reached'] > 0) $status = 'starved';
        // failed only when every task it ran failed: tasks idle beside one failure are that task's own row (02 row 49; g1 F4)
        elseif ($n['done'] === 0 && $n['failed'] > 0 && $n['idle'] === 0 && $n['held'] === 0) $status = 'failed';
        elseif ($n['not_reached'] > 0 || $n['failed'] > 0) $status = 'partial';
        elseif ($n['done'] === 0) $status = 'idle';
        else $status = 'ok';
        if ($why === '' && $status !== 'ok') $why = implode(', ', array_filter(array_map(fn ($k, $v) => $v ? $v . ' ' . strtolower(self::OUTCOMES[$k]) : '', array_keys($n), $n)));
        $pdo->prepare('UPDATE crawl_runs SET ended_at = NOW(), status = ?, seconds = ?, fetches = ?, searches = ?, why = ? WHERE id = ?')
            ->execute([$status, max(0, min(65535, (int) ($b['seconds'] ?? 0))), max(0, min(65535, (int) ($b['fetches'] ?? 0))), max(0, min(65535, (int) ($b['searches'] ?? 0))), $why !== '' ? $why : null, $runId]);
        // the alarms read at the report (decisions/0171 §5, release 3a): a failed run (the crawler itself down: every task
        // failed, or the worker said so) is tried again by the next runs, then a trip that pauses all gathering (3c); a
        // starved one a warning; a run that did its work clears them
        try {
            require_once __DIR__ . '/../ControlAlarms.php';
            $at = 'Run #' . $runId . ' ended ' . $status . ' at ' . gmdate('H:i') . ' UTC.';
            if ($status === 'failed') ControlAlarms::raise($pdo, 'run:failed', 'wk:crawler', 'trip', 'crash', 'A crawler run failed (#' . $runId . ')',
                'It couldn\'t do its work; what it was given goes back and the next scheduled run tries again.', 'Read run #' . $runId . '\'s why (beside this): it names what failed. The next scheduled run tries again by itself; Check tests the crawler now.', mb_substr($why, 0, 255), [['Run #' . $runId, 'peek', '/admin/insights/crawler?run=' . $runId], ['Check', 'check', 'wk:crawler']]);
            elseif ($status === 'starved') ControlAlarms::raise($pdo, 'run:starved', 'wk:crawler', 'warning', 'slow', 'A crawler run had work and did none of it (#' . $runId . ')',
                'Its time ran out before any task was reached: the run is too short for its plan, or a task is slow.', 'Open run #' . $runId . ' (beside this) to see which task used its time; shorten that task\'s share or lengthen the run.', mb_substr($why, 0, 255), [['Run #' . $runId, 'peek', '/admin/insights/crawler?run=' . $runId]]);
            else { ControlAlarms::clear($pdo, 'run:failed', $at); ControlAlarms::clear($pdo, 'run:starved', $at); }
            // a task that fails is its own alarm on its workflow (release 3c): the next runs try it again (a warning), then a
            // trip stops that workflow only; a run where it did its work clears it. A run that failed outright is the
            // crawler's own trip above, not one per task.
            if ($status !== 'failed') foreach ($taskOut as $t => [$o, $tw]) {
                // what failed, read from the task's own calls (the one engine, P2): an outside service's host, our endpoint
                // or the task's own code; the engine decides its tries, its hold and its words (P1, P9)
                if ($o === 'failed') {
                    require_once __DIR__ . '/../ControlEngine.php';
                    $comp = ControlEngine::classifyTask($pdo, $t, (string) $tw, (array) ($itemCalls[$t] ?? []));
                    // an outside service's host failing in the task's calls is that service's fault: the same fault as its issue,
                    // one alarm, never a second on the task (02 row 24; g1 row 4)
                    if (str_starts_with($comp, 'svc:') && self::svcFault($pdo, $t, (string) $tw, (array) ($itemCalls[$t] ?? []))) continue;
                    $ep = str_starts_with($comp, 'ep:') ? substr($comp, 3) : '';
                    // its title in plain words (02 §4.8 rows 11, 16): our endpoint's ("/api/crawl/loads is slow"), or the task's own code
                    $title = $ep !== '' ? '/api/' . $ep . (preg_match('/timed? ?out|timeout/i', (string) $tw) ? ' is slow' : ' keeps failing') : $t . ' failed in its own code (run #' . $runId . ')';
                    ControlAlarms::raise($pdo, 'task:failed:' . $t, 'wf:' . $tasks[$t]['wf'], 'trip', 'crash', $title,
                        $ep !== '' ? 'It didn\'t reply to the crawler in time; the work goes back and is sent again.' : 'Its work goes back and its next run tries it again.', 'Read its why in run #' . $runId . ' (beside this) and fix its source or its settings; then Try it first on the next run. Or Pause the task.', $tw !== '' ? $tw : 'failed', [['Run #' . $runId, 'peek', '/admin/insights/crawler?run=' . $runId], ['Try it first on the next run', 'ops', 'ops_runnext:' . $t], ['Pause the task', 'ops', 'ops_task_off:' . $t, 'Pause the task ' . $t . '? It\'s switched off on Automation with this reason; its work waits.']], true, true, $comp);
                }
                elseif ($o === 'done') ControlAlarms::clear($pdo, 'task:failed:' . $t, 'It did its work in run #' . $runId . ' (' . gmdate('H:i') . ' UTC).');
            }
            // our endpoint refused the worker before our code ran (Bluehost's bot check, 409 humans_; ModSecurity, 406): a
            // refusal on that endpoint, held at once for a person (02 row 23; g1 rows 25, 26: site_blocked was read by nothing)
            // the worker sends a count (crawler.py SITE_BLOCKED: calls the bot check still refused after its retry, this run), not
            // which endpoint: more than 0 is one refusal on the crawler's endpoints, /api/crawl/. A map of endpoints (a later
            // worker) is read by its named endpoints only; a number is never an endpoint (release #258 read the count 0 as /api/0)
            $sb = $b['site_blocked'] ?? 0;
            if (is_int($sb) || (is_string($sb) && ctype_digit($sb))) { if ((int) $sb > 0) self::blocked($pdo, 'crawl', 409, (int) $sb . ' of the crawler\'s calls refused by the bot check this run (run #' . $runId . ')'); }
            elseif (is_array($sb)) foreach (array_slice($sb, 0, 10, true) as $bep => $bi) {
                if (is_int($bep) || ctype_digit((string) $bep)) continue;
                $bep = (string) preg_replace('#[^a-z0-9_/]#', '', (string) $bep); if ($bep === '') continue; $bi = is_array($bi) ? $bi : [];
                self::blocked($pdo, $bep, (int) ($bi['status'] ?? 0), (string) ($bi['body'] ?? ''));
            }
            // the trip #258 raised in error on "/api/0", closed on its thread with why (once; nothing was refused or held)
            ControlAlarms::raisedInError($pdo, 'ep:0:blocked', 'the crawler\'s count of refused calls (0) was read as an endpoint by release #258. Nothing was refused and nothing was held.');
            // a task on its backup (02 rows 43-44; g2 row 27: search_switches were read by nothing): a Notice naming both,
            // and "Back on its first choice" when a run of it no longer switches
            $sw = [];
            foreach (array_slice((array) ($b['search_switches'] ?? []), 0, 20) as $x) if (is_array($x) && isset($tasks[(string) ($x['task'] ?? '')])) $sw[(string) $x['task']] = $x;
            require_once __DIR__ . '/../OutsideServices.php';
            foreach ($sw as $t => $x) {
                $from = (string) ($x['from'] ?? ''); $to = (string) ($x['to'] ?? '');
                $fn = (string) (OutsideServices::COMPANIES[$from][0] ?? $from); $tn = (string) (OutsideServices::COMPANIES[$to][0] ?? $to);
                ControlAlarms::raise($pdo, 'backup:' . $t, 'wf:' . $tasks[$t]['wf'], 'notice', 'check', $t . ' is on its backup (' . $tn . ') while ' . $fn . ' is paused',
                    'It carries on with ' . $tn . '; it goes back to ' . $fn . ' by itself once ' . $fn . '\'s check passes.', '', (int) ($x['n'] ?? 0) . ' search(es) by ' . $tn . ' in run #' . $runId, [], false, false);
            }
            foreach ($taskOut as $t => [$o]) if ($o === 'done' && !isset($sw[$t]) && ($bo = ControlAlarms::openOf($pdo, 'backup:' . $t)))
                ControlAlarms::clear($pdo, 'backup:' . $t, 'Back on its first choice in run #' . $runId . '.');
        } catch (Throwable $x) { error_log('crawl run alarms: ' . $x->getMessage()); }
        // Railway's minutes, for the ledger (no price: the plan's bill is the record)
        self::cost($pdo, 'railway', '', '', max(0, (int) ($b['seconds'] ?? 0)), $runId, 'seconds the run took', 0);
        return ['status' => $status, 'counts' => $n];
    }

    /**
     * A task's failure at an outside service's host, filed as that service's fault (ServiceHealth::failed, the crawler as
     * its caller): the same issue and alarm its own report opens. Returns whether one was filed.
     */
    private static function svcFault(PDO $pdo, string $task, string $why, array $calls): bool
    {
        require_once __DIR__ . '/../ServiceHealth.php';
        foreach ($calls as $h => $c) {
            if (!is_array($c) || (int) ($c[1] ?? 0) <= 0) continue;
            $err = (string) ($c[2] ?? ''); $st = preg_match('/\b([1-5]\d\d)\b/', $err, $m) ? (int) $m[1] : 0;
            $kind = $st > 0 ? ServiceHealth::classify($st, $err, '', (string) $h) : (preg_match('/timed? ?out|timeout|reset|refused/i', $err . ' ' . $why) ? 'timeout' : 'server');
            if ($kind === '' ) $kind = 'server';
            if (ServiceHealth::failed($pdo, (string) $h, $kind, 'crawler', ['status' => $st, 'error' => $err, 'task' => $task, 'what' => 'its task ' . $task], max(1, (int) ($c[1] ?? 1))) > 0) return true;
        }
        return false;
    }

    /** Our endpoint refused the worker (a bot check or ModSecurity): its refusal, a trip at once holding the tasks that call it. */
    public static function blocked(PDO $pdo, string $ep, int $status, string $body): void
    {
        try {
            require_once __DIR__ . '/../ControlAlarms.php';
            $what = $status === 409 || str_contains($body, 'humans_') ? 'Bluehost\'s bot check' : ($status === 406 ? 'ModSecurity' : 'The site');
            ControlAlarms::raise($pdo, 'ep:' . $ep . ':blocked', ControlAlarms::procOfEndpoint($pdo, $ep), 'trip', 'held', $what . ' refused the crawler at /api/' . $ep,
                'The worker\'s calls never reach our code: ' . $what . ' answers first. What calls /api/' . $ep . ' waits; nothing is lost.', 'Ask Bluehost to exempt /api/' . $ep . ' from its bot check (research/bluehost-bot-check-2026-11-15.md); then press Check and start again.',
                mb_substr(trim(($status ? $status . ' ' : '') . $body), 0, 255), [['Its calls', 'peek', '/admin/config/ledger?q=' . rawurlencode($ep)]], true, false, 'ep:' . $ep);
        } catch (Throwable $x) { error_log('crawl blocked: ' . $x->getMessage()); }
    }

    /**
     * The worker's calls by host (worker 2.4; decisions/0160 note 2026-11-13: "the crawler reports by service"): {host:
     * [calls, failures, last error]} for one task, each filed under its outside service (OutsideServices::serviceOfHost)
     * for that service's card. Hosts on no card, and "*" (the rest, summed), are "websites": businesses' and sources' own
     * pages. Kept 90 days. Before the table exists (its database update), nothing is kept and the run reports as before.
     */
    private static function calls(PDO $pdo, int $runId, string $task, array $calls): void
    {
        if (!$calls) return;
        require_once __DIR__ . '/../OutsideServices.php';
        try {
            $ins = $pdo->prepare('INSERT INTO crawl_service_calls (run_id, at, task, host, service, calls, fails, last_error) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)');
            foreach (array_slice($calls, 0, 60, true) as $host => $c) {
                if (!is_array($c)) continue;
                $host = substr(strtolower((string) preg_replace('/[^a-z0-9.*-]/i', '', (string) $host)), 0, 190); if ($host === '') continue;
                $n = max(0, min(1000000, (int) ($c[0] ?? 0))); $f = max(0, min($n, (int) ($c[1] ?? 0)));
                if (!$n) continue;
                $svc = $host === '*' ? '' : OutsideServices::serviceOfHost($host);
                $err = mb_substr(trim((string) ($c[2] ?? '')), 0, 255);
                $ins->execute([$runId, substr($task, 0, 40), $host, $svc !== '' ? $svc : 'websites', $n, $f, $f && $err !== '' ? $err : null]);
            }
            if (random_int(1, 200) === 1) $pdo->exec('DELETE FROM crawl_service_calls WHERE at < NOW() - INTERVAL 90 DAY');
        } catch (Throwable $e) { error_log('crawl calls: ' . $e->getMessage()); }
    }

    /** One line in the ledger. $usd null: from PRICES. */
    public static function cost(PDO $pdo, string $service, string $wf, string $task, int $units, ?int $runId, string $note = '', ?float $usd = null, bool $ok = true): void
    {
        if ($units <= 0) return;
        if ($usd === null) { $per = self::price($service); $usd = $per * ($service === 'tavily_extract' ? ceil($units / 5) : $units); }
        try {
            $pdo->prepare('INSERT INTO crawl_costs (at, service, wf, task, units, usd_micros, ok, run_id, note) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$service, $wf, $task, $units, (int) round($usd * 1e6), $ok ? 1 : 0, $runId, mb_substr($note, 0, 255)]);
        } catch (Throwable $e) { error_log('crawl cost: ' . $e->getMessage()); }
    }

    // ---- the one queue of requests ----------------------------------------------------------------------------------

    /** The kinds of request the worker does (CONTRACT 2). */
    public const REQUEST_KINDS = ['read_page' => 'Read one page for the places it names',
                                  'find_name' => 'Look up a business by its name in a town (one web search from the month\'s allowance, then its own website)'];

    /** A workflow asks the crawler for one piece of work. The same request already waiting isn't added twice. Its id. */
    public static function request(PDO $pdo, string $wf, string $kind, string $ref, array $payload, int $priority = 5, int $by = 0): int
    {
        if (!isset(self::REQUEST_KINDS[$kind])) throw new InvalidArgumentException('The crawler doesn\'t do that kind of request.');
        $st = $pdo->prepare("SELECT id FROM crawl_requests WHERE wf = ? AND kind = ? AND ref = ? AND status IN ('queued', 'leased') LIMIT 1");
        $st->execute([$wf, $kind, mb_substr($ref, 0, 190)]);
        if ($id = (int) $st->fetchColumn()) return $id;
        $pdo->prepare("INSERT INTO crawl_requests (wf, kind, ref, payload, status, priority, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, 'queued', ?, ?, NOW(), NOW())")
            ->execute([$wf, $kind, mb_substr($ref, 0, 190), json_encode($payload, JSON_UNESCAPED_SLASHES), max(0, min(9, $priority)), $by ?: null]);
        return (int) $pdo->lastInsertId();
    }

    /** Requests that fit in the time left, leased to this run; by workflow share, then priority. Never a try counted for taking one. */
    private static function leaseRequests(PDO $pdo, int $runId, int $seconds): array
    {
        if ($seconds < self::MIN_SHARE) return [];
        $rows = $pdo->query("SELECT q.* FROM crawl_requests q JOIN crawl_workflows w ON w.wf = q.wf AND w.paused = 0
                              WHERE q.status = 'queued' AND (q.next_at IS NULL OR q.next_at <= NOW()) ORDER BY q.priority DESC, q.id LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        $out = []; $left = $seconds;
        $up = $pdo->prepare("UPDATE crawl_requests SET status = 'leased', lease_until = NOW() + INTERVAL 30 MINUTE, run_id = ?, updated_at = NOW() WHERE id = ? AND status = 'queued'");
        foreach ($rows as $q) {
            if ((int) $q['est_seconds'] > $left) continue;
            $up->execute([$runId, (int) $q['id']]);
            if (!$up->rowCount()) continue;
            $left -= (int) $q['est_seconds'];
            $out[] = ['id' => (int) $q['id'], 'wf' => $q['wf'], 'kind' => $q['kind'], 'ref' => $q['ref'], 'payload' => json_decode((string) $q['payload'], true) ?: []];
        }
        return $out;
    }

    /** A request done, failed (a try counted, retried later, three tries) or given back unchanged (not done: no try). */
    private static function finishRequest(PDO $pdo, int $id, string $outcome, $result, string $why): void
    {
        if ($id <= 0) return;
        $why = mb_substr($why, 0, 500);
        if ($outcome === 'done') {
            $st = $pdo->prepare("UPDATE crawl_requests SET status = 'done', result = ?, why = ?, lease_until = NULL, updated_at = NOW() WHERE id = ? AND status = 'leased'");
            $st->execute([json_encode($result, JSON_UNESCAPED_SLASHES), $why ?: null, $id]);
            if ($st->rowCount()) { try { self::deliver($pdo, $id, is_array($result) ? $result : []); } catch (Throwable $e) { error_log('crawl request deliver ' . $id . ': ' . $e->getMessage()); } }
        }
        elseif ($outcome === 'failed') $pdo->prepare("UPDATE crawl_requests SET attempts = attempts + 1, status = IF(attempts + 1 >= 3, 'failed', 'queued'), next_at = NOW() + INTERVAL 6 HOUR, why = ?, lease_until = NULL, updated_at = NOW() WHERE id = ? AND status = 'leased'")
            ->execute([$why ?: 'failed', $id]);
        else $pdo->prepare("UPDATE crawl_requests SET status = 'queued', lease_until = NULL, run_id = NULL, updated_at = NOW() WHERE id = ? AND status = 'leased'")->execute([$id]);
    }

    /**
     * What a done request becomes, for the workflow that asked (decisions/0160: the crawler fetches and reads, the workflow
     * decides). find_name (W1): the business found by its name goes through the intake like any find (IntakeStager, 0076).
     */
    private static function deliver(PDO $pdo, int $id, array $result): void
    {
        $st = $pdo->prepare('SELECT * FROM crawl_requests WHERE id = ?'); $st->execute([$id]);
        $q = $st->fetch(PDO::FETCH_ASSOC);
        if (!$q || $q['kind'] !== 'find_name' || empty($result['candidate']) || !is_array($result['candidate'])) return;
        require_once __DIR__ . '/IntakeStager.php';
        $c = CrawlQueue::normalize($result['candidate']);
        if (!$c) return;
        $p = json_decode((string) $q['payload'], true) ?: [];
        $sid = IntakeStager::createSource($pdo, (string) ($c['source']['start_url'] ?? 'https://worker.traversence/request/' . $id), ['create_listings'], 'Send the crawler: "' . mb_substr((string) ($p['name'] ?? ''), 0, 80) . '" in ' . (string) ($p['city'] ?? '') . ', ' . (string) ($p['state'] ?? ''), (int) ($q['created_by'] ?? 0));
        $r = IntakeStager::stage($pdo, $sid, [$c]);
        $pdo->prepare('UPDATE crawl_requests SET result = ? WHERE id = ?')->execute([json_encode($result + ['staged' => count($r['staged']), 'published' => count($r['auto_imported']), 'known' => (int) $r['already_known']], JSON_UNESCAPED_SLASHES), $id]);
        if ($r['auto_imported'] && is_file(dirname(__DIR__) . '/SearchIndex.php')) { require_once dirname(__DIR__) . '/SearchIndex.php'; try { if (SearchIndex::ready($pdo)) SearchIndex::indexListings($pdo, array_map('intval', $r['auto_imported'])); } catch (Throwable $e) {} }
    }

    /** Leases that ran out with no report go back unchanged (not tried); runs that never reported are marked abandoned. */
    private static function tidy(PDO $pdo): void
    {
        try {
            $pdo->exec("UPDATE crawl_requests SET status = 'queued', lease_until = NULL, run_id = NULL, updated_at = NOW() WHERE status = 'leased' AND lease_until < NOW()");
            $pdo->exec("UPDATE crawl_runs SET status = 'abandoned', why = 'It started and never reported: stopped by Railway, or the site was unreachable at its end.' WHERE status = 'running' AND started_at < NOW() - INTERVAL 40 MINUTE");
        } catch (Throwable $e) { error_log('crawl tidy: ' . $e->getMessage()); }
    }

    // ---- the admin: pause, shares, retry -----------------------------------------------------------------------------

    public static function pause(PDO $pdo, string $wf, bool $pause, int $uid, string $why = ''): void
    {
        $st = $pdo->prepare('UPDATE crawl_workflows SET paused = ?, paused_by = ?, paused_at = IF(? = 1, NOW(), NULL), paused_why = ?, updated_at = NOW() WHERE wf = ?');
        $st->execute([$pause ? 1 : 0, $uid ?: null, $pause ? 1 : 0, $pause ? (mb_substr(trim($why), 0, 255) ?: null) : null, $wf]);
        if (!$st->rowCount()) throw new InvalidArgumentException('No such workflow.');
    }

    public static function setShare(PDO $pdo, string $wf, int $pct): void
    {
        $pdo->prepare('UPDATE crawl_workflows SET share_pct = ?, updated_at = NOW() WHERE wf = ?')->execute([max(0, min(100, $pct)), $wf]);
    }

    /** A task on or off; off keeps who and why (System Configuration → Automation), on clears them. */
    public static function setTask(PDO $pdo, string $task, bool $enabled, int $uid = 0, string $why = ''): void
    {
        $ex = $pdo->prepare('SELECT COUNT(*) FROM crawl_capture_tasks WHERE task = ?'); $ex->execute([$task]);
        if (!(int) $ex->fetchColumn()) throw new InvalidArgumentException('No such capture task.');
        try {
            $pdo->prepare('UPDATE crawl_capture_tasks SET enabled = ?, off_why = ?, off_by = ?, off_at = ' . ($enabled ? 'NULL' : 'UTC_TIMESTAMP()') . ', updated_at = NOW() WHERE task = ?')
                ->execute([$enabled ? 1 : 0, $enabled ? null : (mb_substr(trim($why), 0, 255) ?: null), $enabled ? null : ($uid ?: null), $task]);
        } catch (PDOException $e) {   // before the switches' database update
            $pdo->prepare('UPDATE crawl_capture_tasks SET enabled = ?, updated_at = NOW() WHERE task = ?')->execute([$enabled ? 1 : 0, $task]);
        }
    }

    /** "Run it first": the task is due on the next run (its last served time cleared). */
    public static function runNext(PDO $pdo, string $task): void
    {
        $pdo->prepare('UPDATE crawl_capture_tasks SET last_served_at = NULL, updated_at = NOW() WHERE task = ?')->execute([$task]);
    }

    /**
     * Failed work of a task back in its queue, its tries reset: coverage and town jobs, identity jobs set aside, failed
     * requests. Returns how many.
     */
    public static function retry(PDO $pdo, string $task): int
    {
        $n = 0;
        if ($task === 'coverage') {
            $n += $pdo->exec("UPDATE " . Database::t('crawl_jobs') . " SET status = 'queued', attempts = 0, updated_at = NOW() WHERE status = 'failed'") ?: 0;
            try { $n += $pdo->exec("UPDATE town_crawl_jobs SET status = 'queued', attempts = 0, updated_at = NOW() WHERE status = 'failed' OR (status = 'queued' AND attempts >= 3)") ?: 0; } catch (Throwable $e) {}
        } elseif ($task === 'identity') {
            $n += $pdo->exec("UPDATE " . Database::t('identity_jobs') . " SET attempts = 0, status = 'queued', next_at = NOW(), lease_until = NULL, updated_at = NOW() WHERE attempts >= 3 AND status IN ('queued', 'leased')") ?: 0;
        }
        $n += $pdo->exec("UPDATE crawl_requests SET status = 'queued', attempts = 0, next_at = NULL, updated_at = NOW() WHERE status = 'failed' AND kind IN (" . implode(',', array_map(fn ($k) => $pdo->quote($k), array_keys(self::REQUEST_KINDS))) . ')') ?: 0;
        return $n;
    }

    // ---- seeing it ----------------------------------------------------------------------------------------------------

    /** The latest runs: [{id, started_at, ended_at, status, seconds, fetches, searches, why, items: […]}]. */
    public static function runs(PDO $pdo, int $n = 20): array
    {
        $runs = $pdo->query('SELECT * FROM crawl_runs ORDER BY id DESC LIMIT ' . max(1, min(200, $n)))->fetchAll(PDO::FETCH_ASSOC);
        if (!$runs) return [];
        $ids = implode(',', array_map(fn ($r) => (int) $r['id'], $runs));
        $items = [];
        foreach ($pdo->query("SELECT * FROM crawl_run_items WHERE run_id IN ($ids) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $i) $items[(int) $i['run_id']][] = $i;
        foreach ($runs as &$r) $r['items'] = $items[(int) $r['id']] ?? [];
        return $runs;
    }

    /** Each task over the last $hours: [task => [done, idle, not_reached, failed, off, seconds, last_why]]. */
    public static function taskStats(PDO $pdo, int $hours = 24): array
    {
        $out = [];
        $st = $pdo->prepare('SELECT task, outcome, COUNT(*) n, SUM(seconds) s FROM crawl_run_items WHERE at >= NOW() - INTERVAL ? HOUR GROUP BY task, outcome');
        $st->execute([max(1, min(720, $hours))]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[$r['task']][$r['outcome']] = (int) $r['n']; $out[$r['task']]['seconds'] = ($out[$r['task']]['seconds'] ?? 0) + (int) $r['s']; }
        return $out;
    }

    /** This month's ledger: [wf => [service => [units, usd]]], Claude's spend by feature included from ai_jobs. */
    public static function spend(PDO $pdo): array
    {
        $out = [];
        $since = date('Y-m-01');
        foreach ($pdo->query("SELECT wf, service, SUM(units) u, SUM(usd_micros) c FROM crawl_costs WHERE at >= '$since' GROUP BY wf, service")->fetchAll(PDO::FETCH_ASSOC) as $r)
            $out[$r['wf'] !== '' ? $r['wf'] : 'run'][$r['service']] = [(int) $r['u'], (int) $r['c'] / 1e6];
        try {
            foreach ($pdo->query("SELECT kind, provider, COUNT(*) n, COALESCE(SUM(cost_micros), 0) c FROM " . Database::t('ai_jobs') . " WHERE created_at >= '$since' GROUP BY kind, provider")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $wf = self::FEATURE_WF[$r['kind']] ?? 'other';
                $svc = $r['provider'] === 'tavily' ? 'tavily_extract' : 'claude';   // pages Tavily read are recorded with the AI calls (SecondTier::recordExtracts)
                $o = $out[$wf][$svc] ?? [0, 0.0];
                $out[$wf][$svc] = [$o[0] + (int) $r['n'], $o[1] + (int) $r['c'] / 1e6];
            }
        } catch (Throwable $e) {}
        return $out;
    }

    /** Which workflow each AI feature belongs to (decisions/0160 §5), for the ledger. */
    public const FEATURE_WF = [
        'guide_research' => 'W4', 'guide_draft' => 'W4', 'guide_draft_auto' => 'W4', 'guide_names' => 'W4', 'guide_triage' => 'W4', 'guide_publish' => 'W4', 'fact_check' => 'W4',
        'guide_ondemand' => 'W5', 'content_names' => 'W8', 'guide_find' => 'W8', 'guide_find_add' => 'W8', 'page_read' => 'W3',
        'heritage_extract' => 'W6', 'topic_summary' => 'W6', 'topic_research' => 'W6', 'topic_assist' => 'W6', 'assist_followup' => 'W6',
        'live_events' => 'W10', 'find_settle' => 'W1', 'source_terms' => 'W3', 'page_extract' => 'W3', 'scan_read' => 'W3', 'place_about' => 'W9',
    ];

    /**
     * Whether the crawler is working, for Pulse (the old alert watched only the coverage queue, and cried wolf):
     * null when it is; else [key, title, why, since]. Before the first run through the service there's nothing to judge.
     */
    public static function stalled(PDO $pdo): ?array
    {
        if (!self::ready($pdo)) return null;
        $last = $pdo->query("SELECT started_at FROM crawl_runs ORDER BY id DESC LIMIT 1")->fetchColumn();
        if (!$last) return null;
        if (CrawlQueue::paused()) return null;
        if (strtotime((string) $last) < time() - 30 * 60)
            return ['stopped:' . substr((string) $last, 0, 13), 'The crawler has stopped', 'No run has started since ' . date('M j, g:i a', strtotime((string) $last)) . ' (it runs every 5 minutes on Railway).', (string) $last];
        $recent = $pdo->query("SELECT status, why, started_at FROM crawl_runs WHERE status NOT IN ('running') ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        if (count($recent) >= 6 && !array_filter($recent, fn ($r) => in_array($r['status'], ['ok', 'partial', 'idle', 'paused'], true)))
            return ['stalled:' . substr((string) $recent[5]['started_at'], 0, 13), 'The crawler\'s runs aren\'t working', 'The last 6 runs were ' . implode(', ', array_unique(array_column($recent, 'status'))) . ': ' . mb_strimwidth((string) ($recent[0]['why'] ?? ''), 0, 200, '…'), (string) $recent[5]['started_at']];
        return null;
    }
}
