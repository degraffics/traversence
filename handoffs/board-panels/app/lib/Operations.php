<?php
// app/lib/Operations.php — Operations (decisions/0163 §1–§6, §10; Release A): one view of the outside services, their
// caps and paces, what they cost against revenue and the community budget, the warnings at 50/75/95% and "returns
// empty", watching Railway's deploys and the sync, and the switch-on check. Built from what exists (OutsideServices'
// cards, ServiceHealth, Automation's switches, CrawlService's ledger, ai_jobs, email_sends, Thresholds & gates): no new
// ledger. It shows and records: it calls no outside service while watching is off (shipped off), and switches nothing on.
//
//   register($pdo)          one row per service: what uses it, where its key lives (named, never shown), its caps and
//                           paces (Thresholds: the one setting the code reads), this month's use and cost, its status
//   costs($pdo)             this month by service and by workflow, against revenue and the community budget; the AI
//                           cap's shares per workflow (WorkflowSettings: ai_share_pct)
//   warnings($pdo)          every cap or budget at 50/75/95%: one Pulse case per service (ops:cap:<service>)
//   empties($pdo)           tasks that had work and got answers but returned nothing (one Pulse case, ops:empty)
//   watch / watchTick       Railway's latest deploy and the sync's runs (ops:watch:<what>), only while switched on
//   switchOnCheck($pdo)     "Check before switching on" (§10): each line green, or what's missing with its fix
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Thresholds.php';
require_once __DIR__ . '/OutsideServices.php';
require_once __DIR__ . '/Automation.php';

final class Operations
{
    /** The register's caps and paces by service (OutsideServices::COMPANIES key => Thresholds gates). */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const CAPS = [
        'aws' => ['ses_month_usd'],
        'anthropic' => ['ai_month_usd', 'guide_ondemand_share', 'ai_free_asks', 'ai_cover', 'po_claude_prose'],
        'google' => ['worker_places_per_run', 'places_monthly', 'places_stop_pct', 'paid_spread', 'po_places_confirm'],
        'tavily' => ['worker_searches_per_run', 'websearch_monthly', 'websearch_stop_pct', 'paid_spread'],
        'railway' => ['indexer_month_usd', 'indexer_gap_s', 'indexer_found_batch'],
        'overpass' => ['worker_osm_per_run', 'osm_site_per_hour', 'osm_verify_per_hour', 'osm_daily_crawler', 'osm_daily_site', 'osm_daily_mb', 'osm_per_minute_crawler', 'osm_per_minute_site', 'osm_cache_days', 'osm_ramp_days'],
        'gemini' => ['gemini_daily', 'gemini_per_minute', 'gemini_reserve_pct'],
        'supabase' => ['supabase_rows', 'supabase_daily'],
        'overture' => ['load_towns_per_run'],
        'foursquare' => ['load_towns_per_run'],
        'geofabrik' => ['geofabrik_days'],
        'azcc' => ['licence_lookups_daily'],
        'nmsos' => ['licence_lookups_daily'],
        'heigit' => ['ors_matrix_daily'],
    ];

    /** The paces among the caps, in words (%s is the value): what makes a service "paced". */
    public const PACES = [
        'places_stop_pct' => 'stops at %s%% of the month\'s checks, no further into it than the month is gone', 'websearch_stop_pct' => 'stops at %s%% of the month\'s searches, no further into it than the month is gone',
        'indexer_gap_s' => 'the indexer writes to the site at most one batch every %s seconds', 'paid_spread' => 'spread evenly through the day', 'gemini_per_minute' => 'at most %s a minute, paced through the day', 'gemini_reserve_pct' => '%s%% of each day held for visitors waiting; background work stops before it',
        'geofabrik_days' => 'one download every %s days a state, at most', 'licence_lookups_daily' => '%s lookups a day, a few seconds apart', 'osm_per_minute_crawler' => 'the crawler at most %s query a minute', 'osm_per_minute_site' => 'the site at most %s query a minute',
    ];

    /** Where each service's key lives, named, never shown: key => [[setting, where]]. 'Railway' ones the site can't read. */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const KEYS = [
        'cloudflare' => [['TURNSTILE_SECRET_KEY', '.env']],
        'aws' => [['AWS_ACCESS_KEY_ID', '.env'], ['AWS_SECRET_ACCESS_KEY', '.env']],
        'anthropic' => [['ANTHROPIC_API_KEY', '.env']],
        'google' => [['GOOGLE_PLACES_KEY', 'Railway']],
        'tavily' => [['TAVILY_API_KEY', 'Railway']],
        'railway' => [['CRAWLER_API_TOKEN', '.env and Railway'], ['RAILWAY_API_TOKEN', '.env (watching deploys)']],
        'namecom' => [['NAMECOM_TOKEN', '.env']],
        'github' => [['GITHUB_DOCS_TOKEN', '.env'], ['GITHUB_SYNC_TOKEN', '.env (watching the sync)']],
        'census' => [['CENSUS_API_KEY', '.env (optional: the data API\'s higher daily limit; the geocoder needs none)']],
        'gemini' => [['GEMINI_API_KEY', '.env']],
        'supabase' => [['SUPABASE_URL', '.env'], ['SUPABASE_SERVICE_KEY', '.env']],
        'foursquare' => [['HF_TOKEN', 'Railway (a Hugging Face token whose account accepted Foursquare\'s terms)']],
        'heigit' => [['ORS_API_KEY', '.env']],
        'az511' => [['AZ511_API_KEY', '.env']],
        'ridb' => [['RIDB_API_KEY', 'Railway']],
        'nps' => [['NPS_API_KEY', '.env (optional: the Park Service\'s photos; NPGallery needs none)']],
        'brave' => [['BRAVE_API_KEY', 'Railway']],
        'pixabay' => [['PIXABAY_KEY', '.env']],
    ];

    /** Services added switched off (Jason, 2026-11-15): paused once, on the first look, until a person switches them on. */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const ADDED_OFF = ['gemini', 'supabase', 'overture', 'foursquare', 'geofabrik', 'azcc', 'nmsos', 'azlicences'];
    /** Why each was added off, for its switch (2026-11-18: "until Release B" was out of date once B shipped; the live rows
     *  were reworded by app/migrations/2026-11-18_added_off_words.sql). Off until a person switches it on, plus its own reason. */
    public const ADDED_OFF_WHY = [
        'azcc' => 'Off until a person switches it on: the Commission\'s new site (arizonabusinesscenter.azcc.gov) blocks us until our records request is answered',
        'nmsos' => 'Off until a person switches it on: waits on its address fix and our records request',
        'azlicences' => 'Off until a person switches it on: the licence sites need Cloudflare set up by hand first (queue 3a)',
    ];
    public const ADDED_OFF_DEFAULT = 'Off until a person switches it on: added switched off (2026-11-15)';

    /** Services::all() keys onto the register's services. */
    public const USAGE_OF = ['websearch' => 'tavily', 'places' => 'google', 'claude' => 'anthropic', 'ses' => 'aws'];

    /** The warning levels (Jason, 2026-11-14), in percent, and each one's Pulse severity. */
    public const LEVELS = [95 => 'critical', 75 => 'alert', 50 => 'update'];

    /** Statuses (§1), always with the reason. */
    public const STATUSES = ['active' => 'Active', 'paced' => 'Paced', 'held' => 'Held', 'off' => 'Off', 'out' => 'Out of budget'];

    /** "Returns empty" (§4): a frequent task flagged after this many runs in 24 hours that returned nothing. */
    public const EMPTY_RUNS = 12;

    public static function can(): array
    {
        if (!class_exists('Access', false)) require_once __DIR__ . '/../admin/Access.php';
        return Access::can('config.operations', ['min' => 'executive', 'depts' => []]);
    }

    private static function env(string $k): string
    {
        $v = getenv($k);
        if (($v === false || $v === '') && isset($_ENV[$k])) $v = (string) $_ENV[$k];
        return $v === false ? '' : (string) $v;
    }

    private static function state(PDO $pdo, string $k): string
    {
        try { require_once __DIR__ . '/SearchIndex.php'; return SearchIndex::state($pdo, $k); } catch (Throwable $e) { return ''; }
    }

    private static function setState(PDO $pdo, string $k, string $v): void
    {
        require_once __DIR__ . '/SearchIndex.php';
        SearchIndex::setState($pdo, $k, $v);
    }

    // ---- the register (§1) --------------------------------------------------------------------------------------------

    /**
     * Today's values recorded once (§1: "the register starts from today's values, found automatically; nothing is
     * retyped"). Only a cap whose old place holds a value, or whose default is what the code used before, is recorded:
     * a worker's allowance it hasn't sent yet waits for its first report (api/crawl/usage.php). Returns the gates recorded.
     */
    public static function seed(PDO $pdo): array
    {
        $done = [];
        foreach (self::ADDED_OFF as $svc) {   // switched off, once (a person's later switch stands)
            if (!Automation::ready($pdo) || self::state($pdo, 'ops:added_off:' . $svc) !== '') continue;
            try { Automation::set($pdo, 'service', $svc, true, self::ADDED_OFF_WHY[$svc] ?? self::ADDED_OFF_DEFAULT, 0, 'Release A'); self::setState($pdo, 'ops:added_off:' . $svc, gmdate('Y-m-d H:i')); $done[] = $svc . ' off'; } catch (Throwable $x) { error_log('ops seed ' . $svc . ': ' . $x->getMessage()); }
        }
        foreach (Thresholds::FOUND as $gate => $from) {
            if (Thresholds::stored($pdo, $gate) !== null) continue;
            $f = Thresholds::found($pdo, $gate);
            if ($f === null && $from[0][0] === 'state') continue;   // the worker's number: recorded when it reports
            if (Thresholds::recordFound($pdo, $gate)) $done[] = $gate;
        }
        return $done;
    }

    /** Which workflows and tasks use a service: [workflows[], tasks[], jobs[]]. */
    public static function users(PDO $pdo, string $svc): array
    {
        $ctl = OutsideServices::CONTROLS[$svc] ?? [];
        $tasks = (array) ($ctl['tasks'] ?? []); $jobs = (array) ($ctl['jobs'] ?? []); $wfs = [];
        $crawl = self::crawlReady($pdo) ? CrawlService::tasks($pdo) : [];
        foreach ($tasks as $t) if (isset($crawl[$t])) $wfs[(string) $crawl[$t]['wf']] = true;
        foreach ($jobs as $j) foreach (Automation::JOBS[$j] ?? [] as $w) $wfs[$w] = true;
        if ($svc === 'anthropic' && self::crawlReady($pdo)) foreach (CrawlService::FEATURE_WF as $w) $wfs[$w] = true;
        $w = array_keys($wfs);
        usort($w, fn ($a, $b) => (int) substr($a, 1) <=> (int) substr($b, 1));
        return [$w, $tasks, $jobs];
    }

    private static function crawlReady(PDO $pdo): bool
    {
        if (!class_exists('CrawlService', false)) { if (!is_file(__DIR__ . '/crawler/CrawlService.php')) return false; require_once __DIR__ . '/crawler/CrawlService.php'; }
        return CrawlService::ready($pdo);
    }

    /** Its keys: [[setting, where, present: true|false|null (can't be read from the site)]]. Never the value. */
    public static function keys(string $svc): array
    {
        $out = [];
        $rw = self::workerKeys();
        foreach (self::KEYS[$svc] ?? [] as [$k, $where]) $out[] = [$k, $where, str_starts_with($where, 'Railway') ? ($rw[$k] ?? null) : self::env($k) !== ''];
        return $out;
    }

    /** The Railway keys the worker said it has, by name (never values), from its last run's start: [name => bool]. */
    public static function workerKeys(): array
    {
        static $k = null;
        if ($k !== null) return $k;
        $k = [];
        try {
            require_once __DIR__ . '/SearchIndex.php';
            $j = json_decode((string) SearchIndex::state(Database::connection(), 'worker:keys'), true);
            foreach ((array) ($j['keys'] ?? []) as $n => $v) if (is_string($n) && preg_match('/^[A-Z][A-Z0-9_]{1,40}$/', $n)) $k[$n] = (bool) $v;
        } catch (Throwable $x) {}
        return $k;
    }

    /** Keeps the worker's report of its Railway keys (names and whether each is set; never a value). */
    public static function recordWorkerKeys(PDO $pdo, array $keys): void
    {
        $want = [];
        foreach (self::KEYS as $list) foreach ($list as [$n, $where]) if (str_starts_with($where, 'Railway')) $want[$n] = true;
        $out = [];
        foreach ($keys as $n => $v) if (is_string($n) && isset($want[$n])) $out[$n] = (bool) $v;
        try { require_once __DIR__ . '/SearchIndex.php'; SearchIndex::setState($pdo, 'worker:keys', json_encode(['at' => gmdate('c'), 'keys' => $out])); } catch (Throwable $x) { error_log('worker keys: ' . $x->getMessage()); }
    }

    /** Where to add a key, in words a person can follow. */
    public static function whereToAdd(string $where): string
    {
        if (str_starts_with($where, 'Railway')) return 'on Railway: the crawler service → Variables' . (str_contains($where, '(') ? ' ' . substr($where, strpos($where, '(')) : '');
        if (str_starts_with($where, '.env and Railway')) return 'in the site\'s .env (cPanel → File Manager) and on Railway (the crawler service → Variables)';
        return 'on its card in the Service register (Add its key)' . (str_contains($where, '(') ? ' ' . substr($where, strpos($where, '(')) : '');   // decisions/0173 §3: no cPanel trip
    }

    /** Its caps and paces: [[gate, label, value, unit, how it was set, set by, at]]. */
    public static function caps(PDO $pdo, string $svc): array
    {
        $out = [];
        foreach (self::CAPS[$svc] ?? [] as $g) {
            $def = Thresholds::GATES[$g] ?? null;
            if (!$def) continue;
            $st = Thresholds::stored($pdo, $g);
            $how = $st === null ? (isset(Thresholds::FOUND[$g]) && Thresholds::found($pdo, $g) !== null ? 'Not recorded yet: read from ' . Thresholds::found($pdo, $g)[1] : 'Its default')
                 : (!empty($st['found']) ? 'Found: ' . $st['found'] : (!empty($st['reset']) ? 'Reset to its default' : 'Set'));
            $out[] = ['gate' => $g, 'label' => $def[0], 'value' => Thresholds::get($pdo, $g), 'unit' => $def[6], 'how' => $how, 'by' => (int) ($st['by'] ?? 0), 'at' => (string) ($st['at'] ?? ''), 'min' => $def[3], 'max' => $def[4], 'step' => $def[5], 'where' => $def[7]];
        }
        return $out;
    }

    /** This month's use by our count: service => [used, cap, pct, cost usd, unit, money, note]. */
    public static function usage(PDO $pdo): array
    {
        $out = [];
        try {
            require_once __DIR__ . '/Services.php';
            foreach (Services::all($pdo) as $k => $s) {
                $svc = self::USAGE_OF[$k] ?? $k;
                $out[$svc][] = ['what' => $s['name'], 'used' => (float) $s['used'], 'cap' => (float) $s['cap'], 'pct' => (int) $s['pct'], 'cost' => (float) $s['cost'], 'unit' => (string) $s['unit'], 'money' => (bool) $s['money'], 'stop' => $s['stop'] ?? null];
            }
        } catch (Throwable $x) { error_log('ops usage: ' . $x->getMessage()); }
        // W0 Indexer's budget line (its always-on time on Railway, by our count)
        try {
            if (is_file(__DIR__ . '/crawler/Frontier.php')) {
                require_once __DIR__ . '/crawler/Frontier.php';
                if (Frontier::ready($pdo)) { $b = Frontier::budget($pdo); if ($b['cap'] > 0) $out['railway'][] = ['what' => 'W0 Indexer on Railway this month (' . $b['hours'] . ' h)', 'used' => round($b['used'], 2), 'cap' => $b['cap'], 'pct' => (int) floor($b['used'] / $b['cap'] * 100), 'cost' => $b['used'], 'unit' => 'dollars', 'money' => true, 'stop' => null]; }
            }
        } catch (Throwable $x) { error_log('ops usage W0: ' . $x->getMessage()); }
        if (class_exists('ServiceHealth', false) || is_file(__DIR__ . '/ServiceHealth.php')) {
            require_once __DIR__ . '/ServiceHealth.php';
            foreach (['crawler', 'site'] as $c) {
                try {
                    $b = ServiceHealth::osmBudget($pdo, $c);
                    $used = max(0, (int) $b['limit'] - (int) $b['queries']);
                    $out['osm'][] = ['what' => 'OpenStreetMap queries today: ' . ($c === 'site' ? 'the site' : 'the crawler'), 'used' => $used, 'cap' => (float) $b['limit'], 'pct' => $b['limit'] > 0 ? (int) floor($used / $b['limit'] * 100) : 0, 'cost' => 0.0, 'unit' => 'queries', 'money' => false, 'stop' => null, 'day' => true];
                } catch (Throwable $x) {}
            }
        }
        return $out;
    }

    /**
     * Its status, with the reason (§1): ['active'|'paced'|'held'|'off'|'out', why]. Off: switched off, or no key. Held: an
     * open issue holding it (ServiceHealth). Out of budget: a cap reached (until the 1st, or tomorrow for a daily one).
     * Paced: a pace keeps it under its cap. Silence is a status: a service nothing reports on says so.
     */
    /**
     * Whether an open issue holds only its own server: the service has a list of servers (ServiceHealth::ROLES), the issue's
     * host is one of them, and another server not paused has no holding issue, or (OpenStreetMap) another source in the
     * source map answers: our Geofabrik copy, Geofabrik itself, or the need's backup (decisions/0163's note). The server
     * stays held either way: ServiceHealth::allowed() keeps refusing calls to it.
     */
    private static function serverScoped(PDO $pdo, string $svc, array $issue, array $open): bool
    {
        $roles = ServiceHealth::ROLES[$svc] ?? [];
        $host = strtolower((string) ($issue['host'] ?? ''));
        if (!$roles || $host === '') return false;
        $hosts = [];
        foreach (array_keys($roles) as $role) foreach (ServiceHealth::servers($pdo, $svc, $role, true) as $sv) if (!(int) $sv['paused']) $hosts[strtolower((string) $sv['host'])] = true;
        if (!isset($hosts[$host])) return false;
        $held = [];
        foreach ($open as $o) if ($o['service'] === $svc && ServiceHealth::holding($o)) $held[strtolower((string) ($o['host'] ?? ''))] = true;
        foreach (array_keys($hosts) as $h) if (!isset($held[$h])) return true;
        return $svc === 'osm' && self::osmOtherSource($pdo) !== '';
    }

    /** OpenStreetMap's other sources in the source map (before our copy's first load; Overpass is retired): what answers, in words, or ''. */
    private static function osmOtherSource(PDO $pdo): string
    {
        try {
            require_once __DIR__ . '/OpenPlaces.php'; require_once __DIR__ . '/crawler/OpenLoads.php'; require_once __DIR__ . '/Preflight.php';
            [$copy, $cw] = OpenPlaces::ourCopy($pdo);
            if ($copy) return 'W1 reads ' . $cw;
            foreach (OpenLoads::lastProbe($pdo) ?? [] as $p) if (is_array($p) && !empty($p['ok'])) return 'Geofabrik\'s test download passed: W1 starts on our copy';
            $ok = fn (string $id) => in_array((string) ((Preflight::result($pdo, $id) ?? [])['s'] ?? ''), ['ok', 'warn'], true);
            if ($ok('svc:geofabrik')) return 'Geofabrik answers: W1 starts on our copy (its first load runs once W1 is on)';
            if ($ok('nd:places')) return 'Places by kind and town answers from a backup';
        } catch (Throwable $x) {}
        return '';
    }

    public static function status(PDO $pdo, string $svc, ?array $use = null): array
    {
        $use = $use ?? (self::usage($pdo)[$svc] ?? []);
        if (($why = Automation::serviceOff($pdo, $svc)) !== null) return ['off', $why];
        foreach (self::keys($svc) as [$k, $where, $has]) if ($has === false && !str_contains($where, '(')) return ['off', 'No key: ' . $k . ' isn\'t in ' . $where . '.'];
        try {
            require_once __DIR__ . '/ServiceHealth.php';
            $open = ServiceHealth::openAll($pdo);
            $backing = '';
            foreach ($open as $si) if ($si['service'] === $svc && ServiceHealth::holding($si)) {
                // a hold on one of a service's servers stays on that server (it's never called), and holds the service only
                // when no other server, and for OpenStreetMap no other source in the map, answers (Jason's report, 2026-11-17)
                if (self::serverScoped($pdo, $svc, $si, $open)) continue;
                // a back-off in its wait isn't a hold (0171 §6, 3c: "held or out" is a refusal, a refused key, a used-up quota
                // or budget; "a back-off trips at its case limit"): it's paced, a warning on its own issue, and holds no gate
                // until its alarm trips (2026-11-19: Wikidata's 429 tripped W1 through its gate)
                if (!ServiceHealth::spentHold($pdo, $si)) { if ($backing === '') $backing = 'Backing off: issue #' . (int) $si['id'] . ' (' . $si['host'] . ', ' . strtolower((string) (ServiceHealth::KINDS[$si['kind']][0] ?? $si['kind'])) . '): ' . lcfirst(ServiceHealth::waitWords($si)) . ', by itself.'; continue; }   // the words follow the state (a wait that's over says so)
                [$t] = ServiceHealth::describe($si); return ['held', 'Issue #' . (int) $si['id'] . ': ' . $t];
            }
            if ($backing !== '') return ['paced', $backing];
        } catch (Throwable $x) {}
        foreach ($use as $u) if ($u['cap'] > 0 && $u['pct'] >= 100) return ['out', $u['what'] . ' is at its cap: ' . (!empty($u['day']) ? 'until tomorrow (UTC).' : 'until ' . date('F j', strtotime('first day of next month')) . '.')];
        $p = [];
        foreach (self::CAPS[$svc] ?? [] as $g) {
            if (!isset(self::PACES[$g])) continue;
            $v = Thresholds::get($pdo, $g);
            if ((float) $v > 0) $p[] = sprintf(self::PACES[$g], self::num($v));
        }
        if ($p) return ['paced', ucfirst(implode('; ', array_unique($p))) . '.'];
        $master = Automation::master($pdo);
        return ['active', $master ? 'On; its automatic calls are held by the master switch (people\'s own presses still reach it).' : 'On.'];
    }

    public static function num($v): string
    {
        return is_float($v) && floor($v) !== $v ? number_format($v, 2) : number_format((float) $v);
    }

    /** One row per service (§1). */
    public static function register(PDO $pdo): array
    {
        $use = self::usage($pdo); $ledger = self::ledgerBy($pdo); $out = [];
        require_once __DIR__ . '/Services.php';
        $plan = []; foreach (Services::plans()[0] as $p) $plan[(string) $p['service']] = ($plan[(string) $p['service']] ?? 0.0) + (float) $p['usd'];   // flat monthly plans (Railway's Hobby)
        foreach (OutsideServices::COMPANIES as $k => $c) {
            [$wfs, $tasks, $jobs] = self::users($pdo, $k);
            $u = $use[$k] ?? [];
            // the ledger's cost (failed calls included) where it has one, as Costs shows; else Services' estimate
            $led = 0.0; foreach (OutsideServices::LEDGER[$k] ?? [] as $ls) $led += $ledger[$ls]['usd'] ?? 0.0;
            $cost = $led > 0 ? $led : array_sum(array_column(array_filter($u, fn ($x) => empty($x['day'])), 'cost'));
            $cost += $plan[$k] ?? 0.0;
            require_once __DIR__ . '/StagingGate.php';
            $out[$k] = ['key' => $k, 'name' => $c[0], 'what' => $c[1], 'price' => $c[2], 'workflows' => $wfs, 'tasks' => $tasks, 'jobs' => $jobs,
                        'staging' => StagingGate::answer($k), 'staging_word' => StagingGate::WORDS[StagingGate::answer($k)],   // its answer on staging (decisions/0165 §1); a service with none is held
                        'keys' => self::keys($k), 'caps' => self::caps($pdo, $k), 'use' => $u, 'cost' => $cost,
                        'provider' => 'Our count only' . ($k === 'anthropic' ? ' (no Admin API key: the Console is an individual account, decisions/0163 §7)' : ''),
                        'produced' => self::produced($pdo, $k), 'status' => self::status($pdo, $k, $u)];
        }
        return $out;
    }

    /** What a service produced this month and the cost per result, where the ledger says (§1). '' when it isn't counted. */
    private static function produced(PDO $pdo, string $svc): string
    {
        if ($svc !== 'google' && $svc !== 'tavily') return '';
        try {
            require_once __DIR__ . '/Services.php';
            $i = Services::independence($pdo);
            return $i['listings'] ? number_format($i['listings']) . ' listings from the crawler this month' . ($i['per_listing'] !== null ? ', ' . $i['per_listing'] . ' paid lookups each (Places and web search together)' : '') : 'No listings from the crawler this month';
        } catch (Throwable $x) { return ''; }
    }

    /** The crawler's ledger this month by its own service names: service => [units, usd, failed]. */
    private static function ledgerBy(PDO $pdo, ?string $month = null): array
    {
        $out = [];
        $from = ($month ?? date('Y-m')) . '-01'; $to = date('Y-m-01', strtotime($from . ' +1 month'));
        try {
            $st = $pdo->prepare('SELECT service, SUM(units) u, SUM(usd_micros) c, SUM(ok = 0) bad FROM crawl_costs WHERE at >= ? AND at < ? GROUP BY service');
            $st->execute([$from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['service']] = ['units' => (int) $r['u'], 'usd' => (int) $r['c'] / 1e6, 'failed' => (int) $r['bad']];
        } catch (Throwable $x) {}
        return $out;
    }

    // ---- costs together (§2) ------------------------------------------------------------------------------------------

    /**
     * This month's spend in one place: by service, by workflow, the plans, against revenue and the community budget.
     * ['services' => [svc => usd], 'workflows' => [wf => [svc => usd]], 'plans' => usd, 'total', 'revenue', 'community'
     * (MemberPosts::budget), 'covered' => bool, 'ai_shares' => [wf => [share_pct, cap_usd, spent, pct]]].
     */
    public static function costs(PDO $pdo): array
    {
        $svc = []; $wf = [];
        $ledgerOf = [];
        foreach (OutsideServices::LEDGER as $k => $names) foreach ($names as $n) $ledgerOf[$n] = $k;
        if (self::crawlReady($pdo)) {
            foreach (CrawlService::spend($pdo) as $w => $bySvc) foreach ($bySvc as $s => [$units, $usd]) {
                $company = $s === 'claude' ? 'anthropic' : ($ledgerOf[$s] ?? $s);
                $svc[$company] = ($svc[$company] ?? 0.0) + $usd;
                $wf[$w][$company] = ($wf[$w][$company] ?? 0.0) + $usd;
            }
        } else {
            try { require_once __DIR__ . '/AiClient.php'; $a = AiClient::monthUsd($pdo); if ($a > 0) { $svc['anthropic'] = $a; $wf['other']['anthropic'] = $a; } } catch (Throwable $x) {}
        }
        try {
            if (is_file(__DIR__ . '/SesMail.php')) { require_once __DIR__ . '/SesMail.php'; [$n, $usd] = SesMail::month($pdo); if ($usd > 0) { $svc['aws'] = ($svc['aws'] ?? 0.0) + $usd; $wf['email']['aws'] = ($wf['email']['aws'] ?? 0.0) + $usd; } }
        } catch (Throwable $x) {}
        require_once __DIR__ . '/Services.php';
        [$plans, $fixed] = Services::plans();
        foreach ($plans as $p) $svc[(string) $p['service']] = ($svc[(string) $p['service']] ?? 0.0) + (float) $p['usd'];
        $total = array_sum($svc);
        $community = ['month' => date('Y-m'), 'spent' => 0.0, 'budget' => 0.0, 'pct' => 0, 'revenue' => 0.0, 'share' => 0, 'floor' => 0];
        try { require_once __DIR__ . '/MemberPosts.php'; $community = MemberPosts::budget($pdo) + $community; } catch (Throwable $x) {}
        arsort($svc);
        return ['services' => $svc, 'workflows' => $wf, 'plans' => $fixed, 'total' => $total, 'revenue' => (float) $community['revenue'],
                'community' => $community, 'covered' => $total <= (float) $community['revenue'], 'ai_shares' => self::aiShares($pdo, $wf)];
    }

    /**
     * The AI cap's shares per workflow (§7, X4): each workflow's ai_share_pct (WorkflowSettings) of the month's cap, what
     * it spent, and its percent. A share unused at the month's end passes on to the next month's pool (recorded, not yet
     * carried: Release B). W5's half of the daily allowance (guide_ondemand_share) stays its own rule.
     */
    public static function aiShares(PDO $pdo, ?array $wf = null): array
    {
        require_once __DIR__ . '/AiClient.php';
        require_once __DIR__ . '/WorkflowSettings.php';
        $wf = $wf ?? self::costs($pdo)['workflows'];
        $cap = AiClient::capUsd(); $out = [];
        foreach (WorkflowSettings::all($pdo) as $w => $spec) {
            $pct = (int) ($spec['budgets']['ai_share_pct'] ?? 0);
            if ($pct <= 0) continue;
            $shareUsd = $cap * $pct / 100; $spent = (float) ($wf[$w]['anthropic'] ?? 0.0);
            $out[$w] = ['share_pct' => $pct, 'cap_usd' => $shareUsd, 'spent' => $spent, 'pct' => $shareUsd > 0 ? (int) floor($spent / $shareUsd * 100) : 0];
        }
        return $out;
    }

    // ---- warnings (§4) ------------------------------------------------------------------------------------------------

    /**
     * Every cap or budget at 50% or more: service => ['level', 'sev', 'lines' => [[what, used, cap, pct, money, unit]]].
     * The services' monthly caps (AI, SES, Google checks, web search), OpenStreetMap's daily budgets, the AI cap's shares
     * per workflow (under anthropic) and the community budget (under 'community').
     */
    public static function warnings(PDO $pdo): array
    {
        $by = [];
        // Gemini's daily allowance is paced to be used: it warns on the unexpected only (gap review F4), never on use
        foreach (self::usage($pdo) as $svc => $lines) if ($svc !== 'gemini') foreach ($lines as $u) if ($u['cap'] > 0 && $u['pct'] >= 50) $by[$svc][] = $u;
        try { require_once __DIR__ . '/Gemini.php'; foreach (Gemini::unexpected($pdo) as $l) $by['gemini'][] = ['what' => $l, 'used' => 0, 'cap' => 0, 'pct' => 75, 'money' => false, 'unit' => '']; } catch (Throwable $x) {}
        foreach (self::aiShares($pdo) as $w => $a) if ($a['pct'] >= 50) $by['anthropic'][] = ['what' => $w . '\'s share of the AI cap (' . $a['share_pct'] . '%)', 'used' => $a['spent'], 'cap' => $a['cap_usd'], 'pct' => $a['pct'], 'money' => true, 'unit' => 'dollars'];
        try {
            require_once __DIR__ . '/MemberPosts.php';
            $b = MemberPosts::budget($pdo);
            if ((float) $b['budget'] > 0 && (int) $b['pct'] >= 50) $by['community'][] = ['what' => 'The community budget (members\' Moments, Snaps and Keeps)', 'used' => (float) $b['spent'], 'cap' => (float) $b['budget'], 'pct' => (int) $b['pct'], 'money' => true, 'unit' => 'dollars'];
        } catch (Throwable $x) {}
        $out = [];
        foreach ($by as $svc => $lines) {
            $top = max(array_column($lines, 'pct'));
            foreach (self::LEVELS as $lvl => $sev) if ($top >= $lvl) { $out[$svc] = ['level' => $lvl, 'sev' => $sev, 'lines' => $lines]; break; }
        }
        return $out;
    }

    private static function fmtUse(array $u): string
    {
        return $u['money'] ? '$' . number_format((float) $u['used'], 2) . ' of $' . number_format((float) $u['cap'], 2) : number_format((float) $u['used']) . ' of ' . number_format((float) $u['cap']) . ' ' . $u['unit'];
    }

    // ---- "returns empty" (§4) -----------------------------------------------------------------------------------------

    /**
     * Tasks that had work and got answers, but nothing came back (not an empty queue, not held): a frequent task that
     * did its work at least EMPTY_RUNS times in 24 hours and found nothing; a bulk load on its first empty run.
     * [task => [wf, name, runs, fetches, searches, last_why, last_at, bulk]].
     */
    public static function empties(PDO $pdo): array
    {
        if (!self::crawlReady($pdo)) return [];
        $tasks = CrawlService::tasks($pdo); $out = [];
        try {
            $rows = $pdo->query("SELECT task, COUNT(*) n, SUM(fetches) f, SUM(searches) s, MAX(at) last_at,
                                        SUM(CASE WHEN counts IS NULL OR counts = '' OR counts = '[]' OR counts = '{}' THEN 0 ELSE 1 END) counted
                                   FROM crawl_run_items WHERE outcome = 'done' AND at >= NOW() - INTERVAL 24 HOUR GROUP BY task")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $x) { return []; }
        foreach ($rows as $r) {
            $t = (string) $r['task']; $info = $tasks[$t] ?? null;
            if (!$info || (int) $r['f'] + (int) $r['s'] <= 0) continue;   // asked nothing outside: not "empty", just quiet
            $bulk = ($info['mode'] ?? '') === 'bulk';
            if (!$bulk && (int) $r['n'] < self::EMPTY_RUNS) continue;
            $found = 0;
            $st = $pdo->prepare("SELECT counts FROM crawl_run_items WHERE task = ? AND outcome = 'done' AND at >= NOW() - INTERVAL 24 HOUR ORDER BY id DESC LIMIT 200"); $st->execute([$t]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) foreach ((array) (json_decode((string) $c, true) ?: []) as $v) if (is_numeric($v)) $found += (int) $v;
            if ($found > 0) continue;
            $out[$t] = ['wf' => (string) $info['wf'], 'name' => (string) $info['name'], 'runs' => (int) $r['n'], 'fetches' => (int) $r['f'], 'searches' => (int) $r['s'],
                        'last_why' => (string) ($info['last_why'] ?? ''), 'last_at' => (string) $r['last_at'], 'bulk' => $bulk];
        }
        return $out;
    }

    // ---- watching Railway and the sync (§5) ---------------------------------------------------------------------------

    /** What's watched: key => [name, the key it needs, where it looks]. */
    public const WATCH = [
        'railway' => ['Railway: the crawler\'s latest deploy', 'RAILWAY_API_TOKEN', 'backboard.railway.com (GraphQL), project RAILWAY_PROJECT_ID, service RAILWAY_SERVICE_ID'],
        'sync' => ['The 3-way sync\'s runs', 'GITHUB_SYNC_TOKEN', 'api.github.com: degraffics/traverse-3way-sync, sync.yml'],
    ];

    /** The watch's switch (shipped off, Release A): ['on' => bool, 'by', 'at']. */
    public static function watching(PDO $pdo): array
    {
        $j = json_decode(self::state($pdo, 'ops:watch'), true);
        return is_array($j) ? $j + ['on' => false] : ['on' => false, 'by' => 0, 'at' => ''];
    }

    public static function setWatching(PDO $pdo, bool $on, int $uid): void
    {
        if ($on) foreach (self::WATCH as [$name, $key]) if (self::env($key) === '') throw new RuntimeException($name . ' needs ' . $key . ' in .env (File Manager) first.');
        self::setState($pdo, 'ops:watch', json_encode(['on' => $on, 'by' => $uid, 'at' => gmdate('Y-m-d H:i')]));
    }

    /** The last look at each: key => ['at' (UTC), 'ok' => bool|null, 'what', 'url']. */
    public static function watched(PDO $pdo): array
    {
        $out = [];
        foreach (array_keys(self::WATCH) as $k) { $j = json_decode(self::state($pdo, 'ops:watch:' . $k), true); if (is_array($j)) $out[$k] = $j; }
        return $out;
    }

    /** The hourly look (services_tick), only while watching is on: free status reads (§6: no new spend). */
    public static function watchTick(PDO $pdo): array
    {
        if (empty(self::watching($pdo)['on'])) return [];
        $done = [];
        foreach (array_keys(self::WATCH) as $k) {
            try { $r = $k === 'railway' ? self::lookRailway() : self::lookSync(); }
            catch (Throwable $x) { $r = ['ok' => null, 'what' => 'Couldn\'t look: ' . mb_substr($x->getMessage(), 0, 200), 'url' => '']; }
            $prev = self::watched($pdo)[$k] ?? [];
            $r['since'] = ($r['ok'] ?? null) === false ? (($prev['ok'] ?? null) === false && !empty($prev['since']) ? (string) $prev['since'] : gmdate('Y-m-d H:i')) : '';   // when it started failing
            self::setState($pdo, 'ops:watch:' . $k, json_encode($r + ['at' => gmdate('Y-m-d H:i')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $done[] = 'watched ' . $k;
        }
        return $done;
    }

    private static function get(string $url, array $headers, ?string $post = null): array
    {
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold($url, 'the operations watch')) !== null) throw new RuntimeException($w);   // Railway and GitHub are held on staging
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'Traversence operations watch']);
        if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
        $body = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
        curl_close($ch);
        if ($code < 200 || $code >= 300) throw new RuntimeException('HTTP ' . $code . ($err !== '' ? ' (' . $err . ')' : ''));
        return json_decode($body, true) ?: [];
    }

    /** Railway's latest deploy: failed or crashed is bad; building or deploying for over 30 minutes is stalled. */
    private static function lookRailway(): array
    {
        $q = 'query($p: String!, $s: String!) { deployments(first: 1, input: {projectId: $p, serviceId: $s}) { edges { node { id status createdAt } } } }';
        $j = self::get('https://backboard.railway.com/graphql/v2', ['Authorization: Bearer ' . self::env('RAILWAY_API_TOKEN'), 'Content-Type: application/json'],
            json_encode(['query' => $q, 'variables' => ['p' => self::env('RAILWAY_PROJECT_ID'), 's' => self::env('RAILWAY_SERVICE_ID')]]));
        $n = $j['data']['deployments']['edges'][0]['node'] ?? null;
        if (!$n) return ['ok' => null, 'what' => 'No deploy found (check RAILWAY_PROJECT_ID and RAILWAY_SERVICE_ID).', 'url' => 'https://railway.com/dashboard'];
        $st = strtoupper((string) $n['status']); $age = time() - strtotime((string) $n['createdAt']);
        $bad = in_array($st, ['FAILED', 'CRASHED'], true); $stalled = in_array($st, ['BUILDING', 'DEPLOYING', 'INITIALIZING', 'QUEUED'], true) && $age > 1800;
        return ['ok' => !$bad && !$stalled, 'what' => 'The latest deploy is ' . strtolower($st) . ($stalled ? ' after ' . (int) round($age / 60) . ' minutes (stalled)' : '') . ', from ' . gmdate('M j, H:i', strtotime((string) $n['createdAt'])) . ' UTC.', 'url' => 'https://railway.com/dashboard'];
    }

    /** The sync's latest run: failed is bad; running over 15 minutes is stuck (CLAUDE.md); none for 14 hours is stalled. */
    private static function lookSync(): array
    {
        $j = self::get('https://api.github.com/repos/degraffics/traverse-3way-sync/actions/workflows/sync.yml/runs?per_page=1', ['Authorization: Bearer ' . self::env('GITHUB_SYNC_TOKEN'), 'Accept: application/vnd.github+json']);
        $r = $j['workflow_runs'][0] ?? null;
        if (!$r) return ['ok' => null, 'what' => 'No runs found.', 'url' => 'https://github.com/degraffics/traverse-3way-sync/actions'];
        $age = time() - strtotime((string) $r['created_at']); $running = (string) $r['status'] !== 'completed';
        $ok = !$running ? (string) $r['conclusion'] === 'success' && $age < 14 * 3600 : $age < 900;
        $what = $running ? 'Run #' . (int) $r['run_number'] . ' is ' . str_replace('_', ' ', (string) $r['status']) . ' for ' . (int) round($age / 60) . ' minutes' . ($age >= 900 ? ' (stuck)' : '')
              : 'Run #' . (int) $r['run_number'] . ' ' . ((string) $r['conclusion'] === 'success' ? 'finished' : (string) $r['conclusion']) . ' ' . (int) round($age / 3600) . ' h ago' . ($age >= 14 * 3600 ? ' (none since: stalled)' : '');
        return ['ok' => $ok, 'what' => $what . '.', 'url' => (string) ($r['html_url'] ?? 'https://github.com/degraffics/traverse-3way-sync/actions')];
    }

    /** The hourly job's part (Services::tick): today's caps recorded once, and the watch when it's on. */
    public static function tick(PDO $pdo): array
    {
        $out = [];
        if ($s = self::seed($pdo)) $out[] = 'recorded ' . implode(', ', $s);
        return array_merge($out, self::watchTick($pdo));
    }

    // ---- Pulse ------------------------------------------------------------------------------------------------------

    /** The cases: [[key, sev, title, why, when, url]]. */
    /**
     * The operations readings as the Control Board's alarms (decisions/0171 §8; 3b: caps and "returns empty"; 3c: Starting
     * the system, W0 Indexer's needs and a pre-flight run that stopped, the last of the five Pulse cases): one alarm per
     * thing, each with its buttons, cleared when its reading is good. Run by the 5-minute watch (control_tick) and when
     * Pulse is read.
     */
    public static function alarms(PDO $pdo): void
    {
        require_once __DIR__ . '/ControlAlarms.php';
        $capKeys = [];
        foreach (self::warnings($pdo) as $svc => $w) {
            $name = $svc === 'community' ? 'The community budget' : (OutsideServices::COMPANIES[$svc][0] ?? $svc);
            $top = $w['lines'][0]; foreach ($w['lines'] as $l) if ($l['pct'] > $top['pct']) $top = $l;
            // its time: when this level was first reached this month, so a resolved case comes back only at the next level
            $lv = json_decode(self::state($pdo, 'ops:lvl:' . $svc), true);
            if (!is_array($lv) || ($lv['m'] ?? '') !== date('Y-m') || (int) ($lv['l'] ?? 0) < $w['level']) { $lv = ['m' => date('Y-m'), 'l' => $w['level'], 'at' => date('Y-m-d H:i:s')]; self::setState($pdo, 'ops:lvl:' . $svc, json_encode($lv)); }
            $capc = ['ops:cap:' . $svc, $w['sev'], $name . ' at ' . $w['level'] . '%', $top['what'] . ': ' . self::fmtUse($top) . ' (' . $top['pct'] . '%).' . (count($w['lines']) > 1 ? ' And ' . (count($w['lines']) - 1) . ' more line(s).' : ''), (string) $lv['at'], '/admin/config/register' . ($svc !== 'community' ? '#svc-' . $svc : '')];
                    // its alarm, not a case (decisions/0171 §8, release 3b): Pulse shows it once, as the alarm's item
            require_once __DIR__ . '/ControlAlarms.php'; $capKeys[] = 'ops:cap:' . $svc;
            // near a limit is a warning at 50, 75 and 95% (0171 §6); only out of budget (100% or more) is a trip ("Held or out").
            // Its steps are the old case's, done in place: Raise the cap (one field), Pause the service (asks first), its entry.
            $out = (int) $top['pct'] >= 100; $fxc = [];
            if ($g = (self::CAPS[$svc][0] ?? '')) { $def = Thresholds::GATES[$g]; $fxc[] = ['Raise the cap', 'opsask', 'ops_cap:' . $g, [$def[0] . ' (' . $def[6] . ')', (string) Thresholds::get($pdo, $g)]]; }
            if (isset(OutsideServices::COMPANIES[$svc]) && !isset(Automation::NOT_PAUSABLE[$svc])) $fxc[] = ['Pause it', 'ops', 'ops_pause:' . $svc, 'Pause ' . OutsideServices::COMPANIES[$svc][0] . '? Nothing automatic calls it until it\'s resumed on the Automation page; business operations carry on.'];
            $fxc[] = [$name . ' in the register', 'peek', '/admin/config/register#svc-' . $svc, $name];   // its panel titled by the part's name (Jason, 2026-11-23)
            ControlAlarms::raise($pdo, 'ops:cap:' . $svc, 'svc:' . $svc, $out ? 'trip' : 'warning', 'limit', $out ? $name . ' is out of budget' : (string) $capc[2], (string) $capc[3] . ($out ? ' Work that needs it waits until the cap is raised or the month turns.' : ''), $out ? 'Raise the cap, or pause it and let the work wait until the month turns.' : 'Nothing to do yet: raise the cap if the work needs more, or pause it; it climbs to the next level or clears by itself.', $top['pct'] . '%', $fxc, false);
        }
        try { require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::clearOthers($pdo, 'ops:cap:', $capKeys, 'Back under its warning level.'); } catch (Throwable $x) {}
        // "returns empty": an alarm per task, not one case gathering them (decisions/0171 §8, release 3b)
        try {
            require_once __DIR__ . '/ControlAlarms.php'; $eKeys = [];
            $lastRun = []; try { foreach ($pdo->query("SELECT task, MAX(run_id) r FROM crawl_run_items WHERE at >= NOW() - INTERVAL 2 DAY GROUP BY task")->fetchAll(PDO::FETCH_ASSOC) as $lr) $lastRun[(string) $lr['task']] = (int) $lr['r']; } catch (Throwable $x) {}   // its own run, never the list of every run
            foreach (self::empties($pdo) as $t => $r) { $eKeys[] = 'ops:empty:' . $t;
                ControlAlarms::raise($pdo, 'ops:empty:' . $t, 'wf:' . $r['wf'], 'warning', 'check', $r['wf'] . ' ' . $r['name'] . ' returns empty', 'It had work and got nothing back: a source changed, a key stopped, or what it asks for has gone.', 'Open its runs and read what it asked and what came back; fix its source or its settings, then Check it now. Or pause it while you look.', (string) $r['runs'] . ' runs, nothing back' . ($r['last_why'] !== '' ? ': ' . $r['last_why'] : ''),
                    [['Check it now', 'ops', 'ops_runnext:' . $t], ['Pause the task', 'ops', 'ops_task_off:' . $t, 'Pause ' . $r['name'] . '? It\'s switched off on its workflow\'s page with this reason; its work waits.'], ['Its last run', 'peek', '/admin/insights/crawler?run=' . (int) ($lastRun[$t] ?? 0)]], false); }
            ControlAlarms::clearOthers($pdo, 'ops:empty:', $eKeys, 'It returned something again.');
        } catch (Throwable $x) { error_log('ops empty alarms: ' . $x->getMessage()); }
        // Starting the system: each thing only a person can fix is its own alarm, with its button (the sequence waits on it)
        try {
            require_once __DIR__ . '/SystemStart.php'; $st = SystemStart::state($pdo); $sKeys = [];
            if (in_array($st['state'] ?? '', ['running', 'stopped'], true)) foreach ((array) ($st['asks'] ?? []) as [$t, $h, $act, $ext]) {
                $k = 'ops:start:' . substr(md5((string) $t), 0, 10); $sKeys[] = $k;
                $fx = [];
                if (is_array($act) && ($act[0] ?? '') === 'do' && str_starts_with((string) ($act[1] ?? ''), 'ops_start_')) $fx[] = [(string) ($act[2] ?? 'Do it'), 'ops', (string) $act[1]];
                elseif (is_array($act) && ($act[0] ?? '') === 'tool') $fx[] = ['Open it', 'peek', (string) $act[1]];
                require_once __DIR__ . '/Pulse.php';
                if ((string) $ext !== '' && isset(Pulse::SERVICES[$ext])) $fx[] = ['Its console', 'link', (string) (Pulse::SERVICES[$ext][1] ?? Pulse::SERVICES[$ext])];
                $fx[] = ['Look again', 'ops', 'ops_start_tick'];
                ControlAlarms::raise($pdo, $k, 'stage:start', ($st['state'] ?? '') === 'stopped' ? 'trip' : 'warning', ($st['state'] ?? '') === 'stopped' ? 'held' : 'check', 'Starting the system: ' . (string) $t,
                    ($st['state'] ?? '') === 'stopped' ? 'Start stopped at this gate: only a person can fix it; the rest waits.' : 'Start carries on with the rest; this one waits for a person.', (string) $h, '', $fx, false);
            }
            ControlAlarms::clearOthers($pdo, 'ops:start:', $sKeys, 'Start no longer waits on it.');
        } catch (Throwable $x) { error_log('ops start alarms: ' . $x->getMessage()); }
        // W0 Indexer's needs: one alarm each (receipts that don't match are a crash: tried first, then a trip)
        try {
            $wKeys = [];
            if (is_file(__DIR__ . '/crawler/Frontier.php')) {
                require_once __DIR__ . '/crawler/Frontier.php';
                foreach (Frontier::needs($pdo) as [$k, $title, $what]) {
                    $key = 'ops:w0:' . preg_replace('/[^a-z0-9_.:-]/i', '_', (string) $k); $wKeys[] = $key;
                    if ($k === 'held') {   // the sites that blocked us: a quiet notice, a warning past the share of the day's reads
                        [$nb, $nr] = Frontier::blockShare($pdo); require_once __DIR__ . '/Thresholds.php'; $pct = (float) (Thresholds::get($pdo, 'w0_block_share') ?? 5);
                        $over = $nr >= 20 && 100 * $nb / max(1, $nr) > $pct;
                        $hfx = []; foreach (array_slice(Frontier::heldSites($pdo, 3), 0, 2) as $hs) $hfx[] = ['Reactivate ' . $hs['host'], 'ops', 'ops_w0_reactivate:' . $hs['host']];
                        $hfx[] = ['Every blocked site', 'peek', '/admin/config/control/wf-W0#cb-held'];   // W0's page, where each has Reactivate (decisions/0176 §5)
                        ControlAlarms::raise($pdo, $key, 'wf:W0', $over ? 'warning' : 'notice', 'check', 'W0 Indexer: ' . (string) $title, (string) $what,
                            $over ? $nb . ' of the ' . $nr . ' sites read in the last day blocked us (over ' . $pct . '%): that many points at our side (our pace, our address or how we ask). Look at what they answered.' : 'Nothing to do unless you want one of them: Reactivate gives it one polite try.',
                            $nb . ' blocked today of ' . $nr . ' sites read', $hfx, false, false);
                        continue;
                    }
                    $fx = str_starts_with((string) $k, 'held:') ? [['Reactivate', 'ops', 'ops_w0_reactivate:' . substr((string) $k, 5)]]
                        : (str_starts_with((string) $k, 'flag:') ? [['It\'s fine', 'ops', 'ops_w0_flag_ok:' . substr((string) $k, 5)]] : []);
                    $fx[] = ['Its index', 'peek', '/admin/insights/index'];
                    ControlAlarms::raise($pdo, $key, 'wf:W0', $k === 'receipts' ? 'trip' : 'warning', $k === 'receipts' ? 'count' : 'check', 'W0 Indexer: ' . (string) $title, (string) $what, $k === 'receipts' ? 'It clears itself once batches match again.' : '', '', $fx, false);
                }
            }
            ControlAlarms::clearOthers($pdo, 'ops:w0:', $wKeys, 'W0 Indexer doesn\'t need it now.');
        } catch (Throwable $x) { error_log('ops W0 alarms: ' . $x->getMessage()); }
        // a pre-flight run that stopped part way: Continue or Cancel, in place
        try {
            $pk = [];
            if (is_file(__DIR__ . '/Preflight.php')) {
                require_once __DIR__ . '/Preflight.php';
                foreach (Preflight::pulseItems($pdo) as $c) if (str_contains((string) $c[2], 'a run stopped')) { $pk[] = 'ops:pf:stopped';
                    ControlAlarms::raise($pdo, 'ops:pf:stopped', 'stage:preflight', 'warning', 'check', (string) $c[2], (string) $c[3], 'Continue it from there, or cancel it.', '', [['Continue', 'ops', 'ops_pf_continue'], ['Cancel the run', 'ops', 'ops_pf_cancel', 'Cancel the stopped run? It\'s kept in the run log where it reached.']], false); }
            }
            ControlAlarms::clearOthers($pdo, 'ops:pf:', $pk, 'No pre-flight run is stopped now.');
        } catch (Throwable $x) { error_log('ops preflight alarms: ' . $x->getMessage()); }
    }

    /** The cases left: [[key, sev, title, why, when, url]] (the rest are alarms: alarms() above). */
    public static function pulseItems(PDO $pdo): array
    {
        $out = [];
        try { self::alarms($pdo); } catch (Throwable $x) { error_log('ops alarms: ' . $x->getMessage()); }
        $watch = self::watching($pdo);
        if (!empty($watch['on'])) foreach (self::watched($pdo) as $k => $r) {
            if (($r['ok'] ?? null) !== false) continue;
            $out[] = ['ops:watch:' . $k, 'alert', self::WATCH[$k][0] . ': needs a look', (string) $r['what'], date('Y-m-d H:i:s', strtotime((string) ($r['since'] ?? $r['at'] ?? 'now') . ' UTC')), '/admin/config/control#cb-watch'];
        }
        return $out;
    }

    /** A case's steps (Pulse::playbook). $s is Pulse's step factory. */
    public static function pulseSteps(PDO $pdo, string $key, callable $s): array
    {
        if ($key === 'ops:start') { require_once __DIR__ . '/SystemStart.php'; return SystemStart::pulseSteps($pdo, $s); }
        if ($key === 'ops:preflight') { require_once __DIR__ . '/Preflight.php'; return Preflight::pulseSteps($pdo, $s); }
        if ($key === 'ops:w0') {
            require_once __DIR__ . '/crawler/Frontier.php';
            $st = [];
            foreach (Frontier::needs($pdo) as [$k, $title, $what]) {
                if ($k === 'receipts') { $st[] = $s($title, $what . ' It clears itself once batches match again.', ['tool', '/admin/insights/index']); continue; }
                if ($k === 'held') { require_once __DIR__ . '/EngineCase.php'; foreach (Frontier::heldSites($pdo, 20) as $hs) $st[] = $s($hs['host'] . ', ' . EngineCase::blockReason((string) $hs['held_why']), 'Reactivate gives it one polite try; still blocked, it\'s held again.', ['do', 'ops_w0_reactivate:' . $hs['host'], 'Reactivate']); continue; }
                if (str_starts_with($k, 'flag:')) { $st[] = $s($title, $what, ['do', 'ops_w0_flag_ok:' . substr($k, 5), 'It\'s fine']); continue; }
                $st[] = $s($title, $what, ['tool', '/admin/config/thresholds']);
            }
            return $st ?: [$s('Nothing needs a look now', 'The case closes by itself.', ['resolve'])];
        }
        if (str_starts_with($key, 'ops:cap:')) {
            $svc = substr($key, 8); $w = self::warnings($pdo)[$svc] ?? null; $st = [];
            foreach ($w['lines'] ?? [] as $l) $st[] = $s($l['what'] . ': ' . $l['pct'] . '%', self::fmtUse($l) . '.', null);
            $caps = self::CAPS[$svc] ?? [];
            if ($caps) {
                $g = $caps[0]; $def = Thresholds::GATES[$g];
                $st[] = $s('Raise the cap', $def[0] . ', now ' . self::num(Thresholds::get($pdo, $g)) . ' ' . $def[6] . '. Kept with who and when, with a Reset.', ['form', 'ops_cap:' . $g, 'Set it', [['value', $def[0] . ' (' . $def[6] . ')', 'text', (string) Thresholds::get($pdo, $g)]]]);
            }
            $ext = ['anthropic' => 'anthropic', 'aws' => 'ses', 'google' => 'google', 'tavily' => 'tavily'][$svc] ?? '';
            if ($ext !== '') $st[] = $s('Top up, or check the bill', 'With the provider: its balance and limits.', null, false, $ext);
            if (isset(OutsideServices::COMPANIES[$svc]) && !isset(Automation::NOT_PAUSABLE[$svc])) $st[] = $s('Pause the work', 'Pauses ' . OutsideServices::COMPANIES[$svc][0] . ' on the Automation page: nothing automatic calls it until it\'s resumed. Business operations carry on.', ['do', 'ops_pause:' . $svc, 'Pause it']);
            if ($svc === 'community') $st[] = $s('See the budget', 'Members\' Moments, Snaps and Keeps are paid from 3% of revenue, $25 at least (decisions/0154).', ['tool', '/admin/config/costs']);
            $st[] = $s('Resolve the case', 'It comes back if it climbs to the next level.', ['resolve']);
            return $st;
        }
        if ($key === 'ops:empty') {
            $st = [];
            foreach (self::empties($pdo) as $t => $r) {
                $st[] = $s($r['wf'] . ' ' . $r['name'] . ': ' . $r['runs'] . ' runs, nothing back', 'In 24 hours: ' . number_format($r['fetches']) . ' pages read, ' . number_format($r['searches']) . ' searches, nothing found.' . ($r['last_why'] !== '' ? ' Its last answer: ' . $r['last_why'] . '.' : '') . ' The step clears itself when it finds something again.', ['tool', '/admin/insights/crawler']);
                $st[] = $s('Check ' . $r['name'] . ' now', 'It runs first on the crawler\'s next run (while automation is on).', ['do', 'ops_runnext:' . $t, 'Check it now']);
                $st[] = $s('Pause ' . $r['name'], 'Switched off on its workflow\'s page, with your reason; its work waits.', ['do', 'ops_task_off:' . $t, 'Pause the task']);
            }
            return $st ?: [$s('Nothing returns empty now', 'The case closes by itself.', ['resolve'])];
        }
        if (str_starts_with($key, 'ops:watch:')) {
            $k = substr($key, 10); $r = self::watched($pdo)[$k] ?? [];
            return [$s('Look at it', (string) ($r['what'] ?? ''), null, false, $k === 'railway' ? 'railway' : 'github'),
                    $s('Resolve the case', 'Once it\'s healthy again; the next hourly look raises it again if not.', ['resolve'])];
        }
        return [];
    }

    /** Pulse::manage for an ops: case. Returns what to say. */
    public static function pulseDo(PDO $pdo, string $key, string $what, array $a, int $uid, string $who): string
    {
        if (str_starts_with($what, 'ops_w0_')) {   // W0 Indexer's steps
            if (!class_exists('Access', false)) require_once __DIR__ . '/../admin/Access.php';
            [$ok, $why] = Access::can('insights.crawler.edit', ['min' => 'staff', 'depts' => ['ops']]);
            if (!$ok) throw new RuntimeException($why . '.');
            require_once __DIR__ . '/crawler/Frontier.php';
            if (str_starts_with($what, 'ops_w0_reactivate:')) { $host = substr($what, 18); Frontier::reactivate($pdo, $host, $uid, $who); return $host . ' is reactivated: one polite try when W0 next reads it; still blocked, it\'s held again.'; }
            if (str_starts_with($what, 'ops_w0_flag_ok:')) { Frontier::unflag($pdo, substr($what, 15)); return 'Noted: its links keep being logged over several passes.'; }
            throw new RuntimeException('Unknown step.');
        }
        if ($key === 'ops:start' && str_starts_with($what, 'ops_start_')) { require_once __DIR__ . '/SystemStart.php'; return SystemStart::pulseDo($pdo, $what, $uid, $who); }   // its own permission (config.automation)
        if ($key === 'ops:preflight' && str_starts_with($what, 'ops_pf_')) { require_once __DIR__ . '/Preflight.php'; return Preflight::pulseDo($pdo, $what, $uid, $who); }   // its own permission (config.preflight)
        [$ok, $why] = self::can();
        if (!$ok) throw new RuntimeException($why . '.');
        if (preg_match('/^ops_cap:([a-z_]+)$/', $what, $m) && in_array($m[1], array_merge(...array_values(self::CAPS)), true)) {
            $v = Thresholds::set($pdo, $m[1], (string) ($a['value'] ?? ''), $uid);
            return Thresholds::GATES[$m[1]][0] . ': ' . self::num($v) . ' ' . Thresholds::GATES[$m[1]][6] . '.';
        }
        if (preg_match('/^ops_pause:([a-z0-9]+)$/', $what, $m) && isset(OutsideServices::COMPANIES[$m[1]])) {
            Automation::set($pdo, 'service', $m[1], true, 'Near its cap (Pulse warning)', $uid, $who);
            return OutsideServices::COMPANIES[$m[1]][0] . ' is paused.';
        }
        if (preg_match('/^ops_(runnext|task_off):([a-z0-9_]+)$/', $what, $m) && self::crawlReady($pdo) && isset(CrawlService::tasks($pdo)[$m[2]])) {
            if ($m[1] === 'runnext') { CrawlService::runNext($pdo, $m[2]); return 'It runs first on the crawler\'s next run.'; }
            Automation::setTask($pdo, $m[2], false, 'Returns empty (Pulse)', $uid, $who);
            return 'The task is switched off.';
        }
        throw new RuntimeException('Unknown step.');
    }

    // ---- the switch-on check (§10) ------------------------------------------------------------------------------------

    /**
     * "Check before switching on" (§10.1): [['line', 'ok' => bool, 'why', 'fix' => [label, url]|null, 'scope' => 'all'|
     * W…|service key, 'stage' => where it's built]]. Anything red keeps that part paused with the reason. Jason,
     * 2026-11-15: the master switch stays off until Release B, whose lines are red until it's built.
     */
    public static function switchOnCheck(PDO $pdo): array
    {
        $L = [];
        $line = function (string $what, bool $ok, string $why, ?array $fix = null, string $scope = 'all', string $stage = '') use (&$L) { $L[] = ['line' => $what, 'ok' => $ok, 'why' => $why, 'fix' => $fix, 'scope' => $scope, 'stage' => $stage]; };
        // no database update waiting or failed
        try {
            require_once __DIR__ . '/DbUpdates.php';
            $waiting = count(DbUpdates::all($pdo)); $failed = count(array_filter(DbUpdates::log($pdo), fn ($r) => !(int) $r['ok']));
            $line('No database update waiting or failed', !$waiting && !$failed, $waiting || $failed ? ($waiting ? $waiting . ' waiting' : '') . ($waiting && $failed ? ', ' : '') . ($failed ? $failed . ' failed' : '') . '.' : 'None.', ['Database updates', '/admin/config/dbupdates']);
        } catch (Throwable $x) { $line('No database update waiting or failed', false, 'Couldn\'t read them: ' . $x->getMessage(), ['Database updates', '/admin/config/dbupdates']); }
        // every service a workflow uses: its key present, not held, its budget and pace set
        foreach (self::register($pdo) as $k => $r) {
            if (!$r['workflows']) continue;
            $miss = array_values(array_filter($r['keys'], fn ($x) => $x[2] === false && !str_contains($x[1], '(')));
            $unread = array_values(array_filter($r['keys'], fn ($x) => $x[2] === null));
            $unset = array_values(array_filter($r['caps'], fn ($c) => isset(Thresholds::FOUND[$c['gate']]) && Thresholds::stored($pdo, $c['gate']) === null));   // a moved cap not recorded yet (its default elsewhere is set)
            $why = [];
            if ($miss) $why[] = 'no key: ' . implode(', ', array_map(fn ($x) => $x[0] . ' (' . $x[1] . ')', $miss));
            if ($unread) $why[] = implode(', ', array_column($unread, 0)) . ' on Railway: not reported by the crawler yet (it reports its keys at each run)';
            if ($r['status'][0] === 'held') $why[] = 'held: ' . $r['status'][1];
            if ($r['status'][0] === 'out') $why[] = 'out of budget: ' . $r['status'][1];
            if ($unset) $why[] = 'not recorded in the register: ' . implode(', ', array_map(fn ($c) => mb_strtolower($c['label']), $unset));
            $line($r['name'] . ' (' . implode(', ', $r['workflows']) . ')', !$why, $why ? ucfirst(implode('; ', $why)) . '.' : 'Key present, not held, its budget and pace set.', ['Service register', '/admin/config/register#svc-' . $k], $k);
        }
        // every key the switch-on needs, one line each, red with where to add it (Jason, 2026-11-15: "the keys: I will work on
        // these later"): the keys of services a workflow uses, the watch's keys, and Gemini's and Supabase's
        $need = [];
        foreach (self::register($pdo) as $k => $r) if ($r['workflows'] || in_array($k, ['railway', 'github', 'gemini', 'supabase', 'foursquare'], true)) foreach ($r['keys'] as $x) if (!str_contains($x[1], 'optional')) $need[$x[0]] = [$x[1], $x[2], $r['name']];   // an optional key never holds a switch-on
        foreach ($need as $n => [$where, $have, $svc]) {
            $line('Key ' . $n . ' (' . $svc . ')', $have === true, $have === true ? 'In place.' : ($have === null ? 'The crawler hasn\'t reported its keys yet (it does on its next run). If it\'s missing, add ' . $n . ' ' . self::whereToAdd($where) . '.' : 'Add ' . $n . ' ' . self::whereToAdd($where) . '.'), ['Service register', '/admin/config/register'], 'keys');
        }
        // OpenStreetMap: our copy (decisions/0163's source map). Overpass is retired (Jason, 2026-11-19, "Stop entirely";
        // ServiceHealth::OSM_RETIRED), so W1 reads only our Geofabrik copy (open_places). Before its first load, Geofabrik's
        // test download passing is enough: the load is W1's own task (t:osm), so holding W1 for it would never let it run.
        try {
            require_once __DIR__ . '/ServiceHealth.php'; require_once __DIR__ . '/OpenPlaces.php'; require_once __DIR__ . '/crawler/OpenLoads.php';
            [$copy, $cw] = OpenPlaces::ourCopy($pdo);
            $other = $copy ? '' : self::osmOtherSource($pdo);
            if ($copy) $why = 'W1 reads ' . $cw . '. Overpass is retired, so a kind or town the copy lacks is reported as not in our copy, never fetched.';
            elseif ($other !== '') $why = $other . ' Now: ' . $cw . '.';
            else $why = $cw . ', and Geofabrik hasn\'t answered a test yet. (Overpass is retired: our copy is the only OpenStreetMap source.)';
            $line('OpenStreetMap: our copy loaded', $copy || $other !== '', $why, ['Geofabrik\'s card', '/admin/config/register#svc-geofabrik'], 'osm');
        } catch (Throwable $x) { $line('OpenStreetMap: our copy loaded', false, 'Couldn\'t read our copy: ' . $x->getMessage(), null, 'osm'); }
        // Railway: its schedule back on and its latest deploy healthy (the watch)
        $watch = self::watching($pdo); $seen = self::watched($pdo)['railway'] ?? null;
        $line('The Railway crawler\'s schedule back on, its latest deploy healthy', !empty($watch['on']) && ($seen['ok'] ?? null) === true,
            empty($watch['on']) ? 'Watching is off (Release A ships it off); it needs RAILWAY_API_TOKEN in .env, then Switch watching on. The crawler\'s cron was removed 2026-11-14: set it again on Railway.' : (string) ($seen['what'] ?? 'Not looked at yet.'), ['Watching', '/admin/config/control#cb-watch'], 'schedule');   // its own scope: Start the system sets it (SystemStart), and it holds no workflow's site part
        // the story-only level (P5-3): built in Release A
        require_once __DIR__ . '/Landmarks.php';
        $line('Landmarks\' story-only level (P5-3)', defined('Landmarks::STORY_ONLY'), 'Built: a protected place publicly known moves only to story only; guides\' names no further.', null, 'W9', 'Release A');
        // what Release B built (decisions/0163 §8 "Before any switch-on"; Jason, 2026-11-15: keep the master off until then):
        // each line a real test of what's live, never a fixed answer
        foreach (self::releaseB($pdo) as $l) $line($l[0], $l[1], $l[2], $l[3] ?? null, $l[4] ?? 'all', 'Release B');
        return $L;
    }

    /** A method's source, for the lines that test what's live (reflection: the file on the server, as it runs). */
    private static function src(string $class, string $method = ''): string
    {
        try {
            $r = $method !== '' ? new ReflectionMethod($class, $method) : new ReflectionClass($class);
            $f = file((string) $r->getFileName()) ?: [];
            return implode('', array_slice($f, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
        } catch (Throwable) { return ''; }
    }

    /** The map pages' OpenStreetMap tiles against its tile usage policy: credited, a real Referer, no server download. [ok, why]. */
    public static function tilePolicy(): array
    {
        // @gate: it reads our own files for the words of an outside call; it makes none
        $root = dirname(__DIR__, 2) . '/website';   // the live tree: app/lib → the web root beside app/
        if (!is_dir($root)) $root = dirname(__DIR__, 2) . '/public_html';
        $bad = []; $n = 0;
        $it = is_dir($root) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) : [];
        foreach ($it as $f) {
            if (!preg_match('/\.(php|js)$/', (string) $f) || str_contains((string) $f, '/uploads/')) continue;
            $t = (string) @file_get_contents((string) $f);
            if (!str_contains($t, 'tile.openstreetmap.org/{z}')) continue;
            $n++;
            if (!str_contains($t, 'OpenStreetMap</a> contributors') || !str_contains($t, 'referrerPolicy')) $bad[] = substr((string) $f, strlen($root));
        }
        foreach (['file_get_contents(\'https://tile.', 'curl_init(\'https://tile.'] as $srv) foreach (glob(dirname(__DIR__) . '/lib/*.php') ?: [] as $f) if (str_contains((string) @file_get_contents($f), $srv)) $bad[] = basename($f) . ' (a download from the server)';
        return [!$bad && $n > 0, $bad ? 'Not to the policy: ' . implode(', ', array_slice($bad, 0, 6)) . '.' : $n . ' map pages: credited "© OpenStreetMap contributors" with its link, a real Referer, no download from the server.'];
    }

    /** Live crawler listings whose names are page text (DataCleanup's junk_name), counted at most every 6 hours. */
    private static function junkLive(PDO $pdo): ?int
    {
        $c = json_decode(self::state($pdo, 'ops:junk_live'), true);
        if (is_array($c) && ($c['at'] ?? 0) > time() - 6 * 3600) return (int) $c['n'];
        try {
            require_once __DIR__ . '/DataCleanup.php';
            if (!DataCleanup::ready($pdo)) return null;
            $p = DataCleanup::preview($pdo, 'junk_name');
            $n = (int) ($p['rules']['junk_name']['act'] ?? 0) + (int) ($p['rules']['junk_name']['check'] ?? 0);
            self::setState($pdo, 'ops:junk_live', json_encode(['n' => $n, 'at' => time()]));
            return $n;
        } catch (Throwable $x) { error_log('ops junk: ' . $x->getMessage()); return null; }
    }

    /** Release B's lines (handoffs/release-b.md): [what, ok, why, fix, scope]. */
    public static function releaseB(PDO $pdo): array
    {
        $L = [];
        require_once __DIR__ . '/crawler/AutoImport.php';
        // the crawler's open fixes: the junk names (and the live ones sent to Review), the four approved releases, the map tiles
        $miss = [];
        if (!str_contains(self::src('AutoImport', 'evaluate'), 'DataCleanup::junkName')) $miss[] = 'AutoImport doesn\'t call DataCleanup::junkName';
        foreach ([['Heritage', 'paper', 'W6'], ['HowGuides', 'copied', 'W10/W11']] as [$c, $m, $w]) { if (!class_exists($c, false)) @include_once __DIR__ . '/' . $c . '.php'; if (!method_exists($c, $m)) $miss[] = $w . ' isn\'t live'; }
        if (!class_exists('PlaceFinder', false)) @include_once __DIR__ . '/PlaceFinder.php';
        if (!str_contains(self::src('PlaceFinder'), 'Landmarks::wikiAllowed')) $miss[] = 'W9 isn\'t live';
        if (!class_exists('CrawlQueue', false)) @include_once __DIR__ . '/crawler/CrawlQueue.php';
        if (!str_contains(self::src('CrawlQueue'), "LIKE 'sit:%' DESC")) $miss[] = 'W12 isn\'t live';
        [$tok, $twhy] = self::tilePolicy();
        if (!$tok) $miss[] = 'the map tiles: ' . $twhy;
        $junk = self::junkLive($pdo);
        if ($junk === null) $miss[] = 'the live junk names couldn\'t be counted (Data cleanup isn\'t set up)';
        elseif ($junk > 0) $miss[] = $junk . ' live listing' . ($junk === 1 ? '' : 's') . ' still named with page text: run Data cleanup\'s "junk name" pass to send them to Review';
        $L[] = ['The crawler\'s open fixes live: the junk listing names, the four approved releases W6, W9, W10/W11, W12, the map tiles\' policy', !$miss, $miss ? ucfirst(implode('; ', $miss)) . '.' : 'AutoImport calls DataCleanup::junkName and no live listing is named with page text; W6, W9, W10/W11 and W12 are live; ' . lcfirst($twhy), $junk ? ['Data cleanup', '/admin/insights/cleanup'] : null];
        // W1 and W8 on 0164's floor and readiness (X2)
        $x2 = defined('AutoImport::OPEN_DATASETS') && !str_contains(self::src('AutoImport', 'evaluate'), 'oneSourceLine()');
        $L[] = ['W1 and W8 gate on 0164\'s floor and readiness, not 0133\'s (gap review X2)', $x2, $x2 ? 'A find is public once it has a real name and a phone or street address we may keep, and is ready (complete, appropriate, unique), at its confidence; listing_one_source and guide_find_add\'s score no longer gate.' : 'The score line still gates (AutoImport::evaluate).', null, 'W1'];
        // the clocks (X1)
        $cl = is_file(__DIR__ . '/Clocks.php');
        $bl = [];
        if ($cl) { require_once __DIR__ . '/Clocks.php'; foreach (Clocks::backlog($pdo) as $wf => $b) if ($b['paused_days'] > 0) $bl[] = $wf . ' ' . $b['paused_days'] . ' d'; }
        $L[] = ['The clocks: each workflow\'s backlog from its paused time, drained at the ramp\'s half budget (X1)', $cl, $cl ? 'Clocks count only switched-on time' . ($bl ? ' (paused so far: ' . implode(', ', array_slice($bl, 0, 8)) . ')' : '') . '; due dates move on by the pause at switch-on; each workflow runs at half for 14 days after it comes back on.' : 'Not built.'];
        // the engine's case (X3), the identity check and recheck
        require_once __DIR__ . '/EngineCase.php'; require_once __DIR__ . '/ListingIdentity.php';
        $eng = EngineCase::ready($pdo);
        $L[] = ['The engine\'s Pulse case, "The engine needs a person" (X3)', $eng, $eng ? 'Built: ' . count(EngineCase::open($pdo, 2000)) . ' step(s) open; finds out of options, the identity queue, sites that block us and banned services each a step with its button.' : 'Its database update (2026-11-16_listing_identity) hasn\'t run yet.', ['Database updates', '/admin/config/dbupdates']];
        $idr = ListingIdentity::ready($pdo);
        $L[] = ['One identity check on every path that creates or un-hides a listing, and the identity recheck (0164 §3.1, §6.1)', $idr, $idr ? 'Built: every path reads candidates fresh inside the identity lock; the recheck is W2\'s task "recheck", shipped off' . (($lr = ListingIdentity::lastRun($pdo)) ? ' (last run: ' . (int) $lr['merged'] . ' merged, ' . (int) $lr['queued'] . ' to the queue)' : '') . '.' : 'Its database update (2026-11-16_listing_identity) hasn\'t run yet.', ['Database updates', '/admin/config/dbupdates'], 'W2'];
        // held-blocked sites and banned services (P1-2, 0164 §6.1)
        require_once __DIR__ . '/ServiceHealth.php';
        $p12 = $eng && defined('ServiceHealth::EVAL_AFTER') && !str_contains(self::src('ServiceHealth', 'probed'), "the block is lifted.')");
        $held = $eng ? EngineCase::heldSites($pdo) : [];
        $L[] = ['A site that blocks us held with a Reactivate step, never retried by itself; the same for a banned service (P1-2, 0164 §6.1)', $p12, $p12 ? 'Built: ' . count($held) . ' site(s) held' . ($held ? ' (' . implode(', ', array_slice(array_keys($held), 0, 5)) . ')' : '') . '; a banned service\'s passing check says "looks lifted" for a person, and after 3 days still refused a person evaluates it.' : 'Its database update hasn\'t run yet, or the automatic return is still there.', ['Pulse', '/admin/pulse']];
        // the Gemini privacy guards (stage 7, 0164 §0.8)
        require_once __DIR__ . '/AiClient.php';
        if (is_file(__DIR__ . '/Gemini.php')) require_once __DIR__ . '/Gemini.php';
        $g = defined('AiClient::INPUT') && class_exists('Gemini', false) && method_exists('Gemini', 'safe') && method_exists('Gemini', 'refused');
        $L[] = ['Gemini\'s privacy guards before any feature can use it (0164 §0.8; P2-1, P2-2, P2-3, T1)', $g, $g ? 'Built: each feature\'s input class declared; Gemini refused for anything but public; pages stripped of R6\'s private details and held names before sending.' : 'Not built.', null, 'gemini'];
        // opening a geo-hub (stage 10)
        $h = is_file(__DIR__ . '/HubOpening.php');
        if ($h) { require_once __DIR__ . '/HubOpening.php'; foreach (HubOpening::lines($pdo) as $hl) $L[] = $hl; }
        else $L[] = ['Opening a geo-hub: its authority sources\' terms and readers, and its opening budget set (H3, H5)', false, 'Not built yet (decisions/0163 §8 stage 10).'];
        return $L;
    }

    /**
     * The check's red lines that hold a switch being turned on. 'all' (the master): every red line. A workflow: the lines
     * for it and for the services it uses (the lines for everything hold it through the master). A service: its own.
     * $withAll adds the lines for everything to a workflow's or a service's (what keeps it paused in all).
     */
    public static function blocking(PDO $pdo, string $scope = 'all', bool $withAll = false): array
    {
        $mine = [$scope => true];
        if (isset(Automation::WORKFLOWS[$scope])) foreach (array_keys(OutsideServices::COMPANIES) as $svc) if (in_array($scope, self::users($pdo, $svc)[0], true)) $mine[$svc] = true;
        $red = array_values(array_filter(self::switchOnCheck($pdo), fn ($l) => !$l['ok'] && ($scope === 'all' || isset($mine[$l['scope']]) || ($withAll && $l['scope'] === 'all'))));
        // a workflow: a service's red line holds it whole only when every one of its tasks uses that service and no other
        // source covers it (gateFor; decisions/0175 §3 "a trip stops only what it holds", 0163's source map)
        if (isset(Automation::WORKFLOWS[$scope])) { $g = self::gateFor($pdo, $scope); $keep = array_flip(array_map(fn ($l) => (string) $l['line'], $g['whole']));
            $red = array_values(array_filter($red, fn ($l) => !isset(OutsideServices::COMPANIES[$l['scope']]) || isset($keep[(string) $l['line']]))); }
        return $red;
    }

    /**
     * What a workflow's red service lines hold (the workflow audit, 2026-11-21; decisions/0175 §3: "a trip stops only what it
     * holds"; 0163's source map: each need's sources in order, then what happens without them). Each red line of a service
     * the workflow uses goes to one of:
     *   'covered': every need of the workflow that names the service still has a source that works (its first, or a backup:
     *              Preflight's need line ok or On a backup), so it holds nothing;
     *   'tasks':   it holds only the workflow's tasks that call the service (OutsideServices::CONTROLS[svc]['tasks']); the
     *              rest of the workflow runs;
     *   'whole':   every task of the workflow calls it, or a need of it has no source left and no single task can be named,
     *              so the workflow waits, as before;
     *   'apart':   none of the workflow's needs names it and none of its tasks calls it (it's reached through a site job, or as
     *              a paid backup): it holds nothing of the workflow (its own job waits on its own line).
     * Returns ['whole' => [lines], 'tasks' => [task => [lines]], 'covered' => [lines], 'apart' => [lines]].
     */
    private static array $gateMemo = [];
    /** Forget what gateFor read this request (after a line changes). */
    public static function forgetGate(): void { self::$gateMemo = []; }

    public static function gateFor(PDO $pdo, string $wf): array
    {
        if (isset(self::$gateMemo[$wf])) return self::$gateMemo[$wf];
        $out = ['whole' => [], 'tasks' => [], 'covered' => [], 'apart' => []];
        require_once __DIR__ . '/Preflight.php'; require_once __DIR__ . '/ControlRegister.php';
        $wfTasks = array_keys(array_filter(ControlRegister::taskWorkflows($pdo), fn ($w) => $w === $wf));
        foreach (self::switchOnCheck($pdo) as $l) {
            $svc = (string) $l['scope'];
            if ($l['ok'] || !isset(OutsideServices::COMPANIES[$svc]) || !in_array($wf, self::users($pdo, $svc)[0], true)) continue;
            $needs = array_keys(array_filter(Preflight::NEEDS, fn ($n) => in_array($wf, (array) $n[2], true) && array_filter((array) $n[3], fn ($sr) => ($sr[0] ?? '') === 'svc' && (string) ($sr[1] ?? '') === $svc)));
            $covered = $needs && !array_filter($needs, fn ($k) => !in_array((string) (Preflight::result($pdo, 'nd:' . $k)['s'] ?? ''), ['ok', 'warn'], true));
            if ($covered) { $out['covered'][] = $l + ['needs' => $needs]; continue; }
            $uses = array_values(array_intersect((array) (OutsideServices::CONTROLS[$svc]['tasks'] ?? []), $wfTasks));
            if ($uses && count($uses) < count($wfTasks)) { foreach ($uses as $t) $out['tasks'][$t][] = $l; continue; }
            if (!$uses && !$needs) { $out['apart'][] = $l; continue; }
            $out['whole'][] = $l;
        }
        return self::$gateMemo[$wf] = $out;
    }

    /** The red lines holding one crawler task by itself (gateFor's 'tasks'), or [] (CrawlService::plan skips a held one). */
    public static function taskHeld(PDO $pdo, string $task, string $wf): array
    {
        try { return self::gateFor($pdo, $wf)['tasks'][$task] ?? []; } catch (Throwable $x) { error_log('Operations::taskHeld: ' . $x->getMessage()); return []; }
    }
}
