<?php
// app/lib/Preflight.php — the pre-flight check, before Start the system (decisions/0163 §10's note of 2026-11-16; Jason,
// 2026-11-17: "yes start the preflight session now"). System Configuration → Pre-flight proves each part WORKS, not only
// that its key is set: the site itself, every outside company (each Outside services card's Test is its line here: one
// list, one status), W0 and the crawler on Railway, the heaviest queries timed, then 0164's phases, each workflow in Start
// the system's order and the new tasks, each ready only when everything under it is.
//   Every test is real, free and harmless: a read-only call, a sign-in without sending, a known test value. Never money,
//   never a message to a real person, never a write to anyone's data. Calls go through StagingGate::hold() (staging's
//   answer) and a host held by an open service issue isn't asked at all (its issue is the answer). A service only PAUSED
//   (Automation) is still tested: the pre-flight reads, and a paused service is what it's for.
//   Its own process: it runs any time (stopped, starting or running) and never starts, pauses or changes anything.
//   Start the system reads its latest result (SystemStart::panel); it doesn't run it. It runs again each night and after
//   a .env change (app/scripts/preflight_tick.php); red lines are one Pulse case, "Pre-flight", a step per line.
// Results: preflight_results, one row per line (its id), and '_run' for the last full runs, so a run of one line never
// overwrites another's. Its own table (2026-11-17): search_index_state's value column cut longer results short on live.
// Staging's own (staging-tables.php): each site keeps its own diagnosis.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SearchIndex.php';

final class Preflight
{
    /** The nightly run: the first tick at or after this hour, Arizona time (the Control Board's upkeep calendar reads it). */
    public const NIGHT_HOUR = 2;
    public const GROUPS = [
        'site' => 'The site itself',
        'services' => 'Outside services',
        'railway' => 'W0 and the crawler on Railway',
        'timing' => 'Query timings',
        'needs' => 'What the engine needs, ours first (decisions/0163, the source map)',
        'siteneeds' => 'What the site needs',
        'workflows' => 'Workflows, in Start the system\'s order',
        'tasks' => 'The new tasks',
        'routes' => 'Routes: each process end to end (the unit register, decisions/0171)',
        'phases' => 'The engine\'s phases (decisions/0164): ready when their workflows are',
        'switchon' => 'Check before switching on: Start the system\'s gate, line by line',
    ];
    /** A line's state in words: a service line that's amber is "Partly working" (one of its servers is down, only the step
     *  that uses it waits: reachAll), never "On a backup", which is a need's (the words follow the state). */
    public static function markOf(string $id, string $s): string
    {
        return str_starts_with($id, 'svc:') && $s === 'warn' ? 'Partly working' : (string) (self::MARKS[$s] ?? $s);
    }

    public const MARKS = ['ok' => 'Ready', 'warn' => 'On a backup', 'bad' => 'Not ready', 'na' => 'Not needed yet', 'wait' => 'Waiting', 'new' => 'Not run yet'];

    /**
     * The source map (decisions/0163's note of 2026-11-17): each need, its sources in order (ours first), and what happens
     * when none works. id => [group, name, workflows, sources, if none works, soft, planned backup].
     * A source: ['ours', table, words, days before it's stale (0: no limit)] | ['svc', company] | ['line', pre-flight line, words]
     * | ['copy', 'osm', words] (our OpenStreetMap copy by state, OpenPlaces::ourCopy)
     * | ['paid', need] (a paid backup, only by a director's override inside its cap: PaidOverride; works only when on, under
     * its cap and its company's own line works).
     * Ready when its first source works; "on a backup" (amber) when a later one does; not ready when none does, unless
     * soft (its fallback always works: then amber). A paid backup is used only by a director's override (not here yet).
     */
    public const NEEDS = [
        'pages' => ['needs', 'Read pages (W0)', ['W0'], [['line', 'rw:beat', 'W0 Indexer on Railway']], 'waits; nothing lost', false, 'the crawler image on the traversence Fly organization'],
        // ours first: the Geofabrik copy by state (OpenPlaces::ourCopy: loaded, fresh, kept by today's kinds), which W1's coverage
        // and targets read; Overpass retired 2026-11-19 (ServiceHealth::OSM_RETIRED), so a kind or town the copy lacks isn't fetched
        'places' => ['needs', 'Places by kind and town (W1)', ['W1'], [['copy', 'osm', 'our OpenStreetMap copy (Geofabrik)'], ['svc', 'overture']], 'waits', false, ''],
        'bulk' => ['needs', 'Businesses in bulk (W1)', ['W1'], [['ours', 'open_places', 'our open-data copies', 45], ['svc', 'overture'], ['svc', 'foursquare'], ['svc', 'geofabrik']], 'the last load is kept', false, ''],
        'health' => ['needs', 'Health providers (W1)', ['W1'], [['ours', 'npi_orgs', 'our NPI copy', 45], ['svc', 'cms']], 'the last load is kept', false, ''],
        'nonprofits' => ['needs', 'Nonprofits (W1)', ['W1'], [['ours', 'irs_orgs', 'our IRS copy', 45], ['svc', 'irs']], 'the last load is kept', false, ''],
        'recreation' => ['needs', 'Recreation areas (W1)', ['W1'], [['ours', 'rec_places', 'our Recreation.gov copy', 45], ['svc', 'ridb']], 'the last load is kept', false, ''],
        'official' => ['needs', 'Official places (W1, W9)', ['W1', 'W9'], [['ours', 'landmarks', 'our landmarks', 0], ['svc', 'wikimedia']], 'waits', false, ''],
        'confirm' => ['needs', 'Confirm a business (W1, W2)', ['W1', 'W2'], [['ours', 'open_places', 'our copies agreeing', 45], ['svc', 'cms'], ['svc', 'azcc'], ['svc', 'tavily'], ['svc', 'brave'], ['paid', 'confirm']], 'the person queue', false, ''],
        'prose' => ['needs', 'Page prose into facts (W1–W3, W10, W11)', ['W1', 'W2', 'W3', 'W10', 'W11'], [['svc', 'gemini'], ['paid', 'prose']], '"waiting to be read"; nothing lost', false, ''],
        'reread' => ['needs', 'Re-read websites (W2)', ['W2'], [['line', 'rw:crawler', 'the crawler reading directly'], ['svc', 'commoncrawl']], 'waits', false, ''],
        'contact' => ['needs', 'A missing phone or website (W2)', ['W2'], [['ours', 'open_places', 'our open-data copies', 45], ['svc', 'tavily'], ['svc', 'brave']], 'waits', false, ''],
        'geocode' => ['needs', 'Map points (W2)', ['W2'], [['ours', 'zip_coordinates', 'our ZIP and town points', 0], ['svc', 'census']], 'waits', false, ''],
        'stillopen' => ['needs', 'Still open? (W2)', ['W2'], [['ours', 'open_places', 'our copies\' open or closed status', 45], ['svc', 'google']], 'waits', false, ''],
        'sources' => ['needs', 'Sources read regularly (W3)', ['W3'], [['ours', 'source_facts', 'what our sources said', 0], ['line', 'rw:crawler', 'the crawler reading directly'], ['svc', 'commoncrawl']], 'waits', false, ''],
        'semantic' => ['needs', 'Semantic search (W3)', ['W3'], [['svc', 'supabase'], ['svc', 'gemini']], 'search uses keyword search (0164 §5.2)', true, ''],
        'writing' => ['needs', 'Writing and judging (W4–W6, W8, W10)', ['W4', 'W5', 'W6', 'W8', 'W10'], [['svc', 'anthropic']], 'queued; W5 gives a first look from what we hold', false, ''],
        'heritage' => ['needs', 'Archives and history (W6)', ['W6'], [['ours', 'heritage_facts', 'our heritage catalog', 0], ['svc', 'loc'], ['svc', 'wikimedia'], ['svc', 'nps']], 'waits', false, ''],
        'drive' => ['needs', 'Drive times (W7)', ['W7'], [['ours', 'travel_legs', 'drive times we kept', 0], ['svc', 'heigit']], 'estimated times', true, ''],
        'landmarks' => ['needs', 'Landmarks, names and photos (W9)', ['W9'], [['ours', 'landmarks', 'our landmarks', 0], ['svc', 'wikimedia'], ['svc', 'usgs'], ['svc', 'census']], 'waits', false, ''],
        'websearch' => ['needs', 'Web search (W8, W10–W12)', ['W8', 'W10', 'W11', 'W12'], [['svc', 'tavily'], ['svc', 'brave']], 'queued', false, ''],
        'authority' => ['needs', 'Authority sources (W14)', ['W14'], [['svc', 'azcc'], ['svc', 'nmsos']], 'waits (the licence lists are inactive until queue 3a)', false, ''],
        // the site (not the engine): visible, optional for the engine's launch
        'hosting' => ['siteneeds', 'Hosting (the site itself)', [], [['line', 'site:db', 'Bluehost: the site and its databases']], 'the site is down', false, 'a read-only copy of the public pages on Cloudflare or the Fly edge; nightly database backups'],
        'enginehost' => ['siteneeds', 'The engine\'s host', [], [['line', 'rw:api', 'Railway']], 'the engine waits', false, 'Bluehost\'s jobs for the light tasks; the crawler on the traversence Fly organization'],
        'sync' => ['siteneeds', 'Sync, checks and docs', [], [['svc', 'github']], 'the sync waits; the site runs', false, 'Railway runs sync.py; recorded in git once GitHub is back'],
        'siteemail' => ['siteneeds', 'The site\'s own email', [], [['svc', 'bluehost'], ['svc', 'aws']], 'waits', false, ''],
        'bulkemail' => ['siteneeds', 'Bulk email and digests', [], [['svc', 'aws']], 'waits', false, ''],
        'storage' => ['siteneeds', 'File storage', [], [['svc', 'cloudflare'], ['svc', 'aws']], 'uploads wait', false, ''],
        'payments' => ['siteneeds', 'Our orders\' payments', [], [['svc', 'quickbooks']], 'wire or check', true, 'Stripe as a second processor'],
        'checkout' => ['siteneeds', 'Marketplace checkout', [], [['svc', 'stripe'], ['svc', 'square']], 'pay at pickup; contact the seller', true, ''],
        'domains' => ['siteneeds', 'Selling domains', [], [['svc', 'namecom']], 'sales pause', true, ''],
        'owndomains' => ['siteneeds', 'Businesses\' own domains', [], [['svc', 'fly']], 'the storefront\'s trvgo.us address still works', true, ''],
        'signin' => ['siteneeds', 'Sign in with Google, Microsoft, Apple or Facebook', [], [['svc', 'google'], ['svc', 'microsoft'], ['svc', 'apple'], ['svc', 'meta']], 'email and password always work', true, ''],
        'maps' => ['siteneeds', 'Maps', [], [['svc', 'osm'], ['svc', 'usgs']], 'the list without the map', true, ''],
        'weather' => ['siteneeds', 'Weather and road work on guides', [], [['svc', 'nws'], ['svc', 'az511']], 'hidden, with a link to check', true, ''],
        'populations' => ['siteneeds', 'Populations and towns', [], [['ours', 'tv_zip_population', 'our Census loads', 0], ['svc', 'census']], 'the last load is kept', true, ''],
        'eucheck' => ['siteneeds', 'The EU sign-up check', [], [['ours', 'ip_eu_ranges', 'our DB-IP file', 45], ['svc', 'dbip']], 'the last file is kept', true, ''],
        'stock' => ['siteneeds', 'Stock photos', [], [['ours', 'stock_images', 'our stock library', 0], ['svc', 'pixabay'], ['svc', 'loc'], ['svc', 'wikimedia']], 'a placeholder', true, ''],
        'videos' => ['siteneeds', 'Videos in journeys', [], [['svc', 'vimeo'], ['svc', 'google']], 'a link out', true, ''],
        'styles' => ['siteneeds', 'Older pages\' styles', [], [['svc', 'tailwind']], 'unstyled', true, ''],
        'recalls' => ['siteneeds', 'Recalls on the Marketplace', [], [['svc', 'cpsc']], 'the check is skipped, logged', true, ''],
        'aiassist' => ['siteneeds', 'AI Assist and reading cards', [], [['svc', 'anthropic'], ['svc', 'aws']], '"try again later"', true, ''],
        'logins' => ['siteneeds', 'Staff logins', [], [['svc', 'onepassword']], 'the admin\'s Vault', true, ''],
    ];
    /** 0164 §7: the workflows each phase runs through. */
    public const PHASES = [
        1 => ['Wide discovery and source classification', ['W0', 'W1', 'W2', 'W3', 'W6', 'W9', 'W10', 'W11', 'W14']],
        2 => ['Contextual extraction', ['W1', 'W2', 'W3', 'W6', 'W9', 'W10', 'W11', 'W14']],
        3 => ['Entity resolution, vetting and privacy', ['W1', 'W2', 'W6', 'W8', 'W9', 'W10', 'W11']],
        4 => ['The master catalog, graph and index', ['W1', 'W4', 'W5', 'W6', 'W8', 'W9', 'W10', 'W11', 'W12']],
        5 => ['Publishing, serving and access', ['W1', 'W4', 'W5', 'W7', 'W10', 'W11']],
        6 => ['Lifecycle and self-healing', ['W2', 'W6', 'W14']],
    ];
    /** The query timings: id => [what, its Thresholds limit]. */
    public const TIMINGS = [
        'radius' => ['A radius search (listings within 50 miles of Kayenta)', 'pf_ms_radius'],
        'rollup' => ['A zone roll-up (live listings counted by geo-hub)', 'pf_ms_rollup'],
        'graph' => ['A graph walk (two steps out from the best-linked record)', 'pf_ms_graph'],
        'index' => ['An index lookup (one address in the W0 frontier)', 'pf_ms_index'],
    ];
    /** Companies whose red line holds everything (the engine can't launch without them). */
    private const CORE = ['bluehost', 'railway', 'github'];
    /** Outside links for fixes (a step Jason takes elsewhere). */
    private const ENV_URL = 'https://my.bluehost.com/hosting/app#/cpanel';
    /** A W0 test batch asked of the indexer is handed out once, then waited for this long. */
    private const ASK_SECS = 900;

    private static ?array $cache = null;   // one run's shared reads (cron lines, the register)

    // ---- the list -------------------------------------------------------------------------------------------------------

    /** Every line in run order: id => [group, name, required, company key ('' none), workflow ('' none)]. */
    public static function lines(PDO $pdo): array
    {
        require_once __DIR__ . '/OutsideServices.php';
        require_once __DIR__ . '/Automation.php';
        require_once __DIR__ . '/SystemStart.php';
        $L = [
            'site:db' => ['site', 'The four databases answer (main, admin, listings, users)', true, '', ''],
            'site:env' => ['site', 'The site can read and write .env', true, 'bluehost', ''],
            'site:envdup' => ['site', '.env names each set once (a second line is ignored)', true, 'bluehost', ''],
            'site:dbup' => ['site', 'Database updates: none waiting or failed', true, '', ''],
            'site:sched' => ['site', 'The scheduler: every automatic job is set up in cPanel', true, 'bluehost', ''],
            'site:jobs' => ['site', 'Scheduled jobs: none stopped in the last day', false, '', ''],
            'site:token' => ['site', 'The workers\' shared key (CRAWLER_API_TOKEN) is set and in use', true, 'railway', ''],
            'site:health' => ['site', 'HEALTH_TOKEN for the daily health report', false, 'github', ''],
            'site:staging' => ['site', 'Staging\'s safe mode: no outside call skips the gate', false, '', ''],
            'site:state' => ['site', 'Long settings are kept whole (Start the system\'s state, search_index_state)', true, '', ''],
            'site:php' => ['site', 'The server\'s PHP', false, 'bluehost', ''],
        ];
        $req = self::requiredServices($pdo);
        foreach (OutsideServices::COMPANIES as $k => $c) $L['svc:' . $k] = ['services', $c[0], isset($req[$k]), $k, ''];
        $L['rw:api'] = ['railway', 'Railway\'s API answers with our token, and finds both services', true, 'railway', ''];
        $L['rw:paused'] = ['railway', 'The crawler and W0 Indexer stay paused until Start the system', true, 'railway', ''];
        $L['rw:beat'] = ['railway', 'W0 Indexer is running on Railway and checking in (asleep while W0 is off)', true, 'railway', 'W0'];
        $L['rw:batch'] = ['railway', 'W0\'s full-size test batch through the intake (marked test, never stored)', true, 'railway', 'W0'];
        $L['rw:crawler'] = ['railway', 'The crawler worker\'s last run reached the site', false, 'railway', ''];
        foreach (self::TIMINGS as $k => [$what]) $L['ms:' . $k] = ['timing', $what, false, '', ''];
        foreach (self::NEEDS as $k => $n) $L['nd:' . $k] = [$n[0], $n[1], $n[0] === 'needs', '', (string) ($n[2][0] ?? '')];   // the engine's needs are needed for launch; the site's are shown
        // the engine itself is needed for launch (Jason, 2026-11-17: "they are critical to our system"): every workflow, new
        // task and phase. A red one still holds only itself and what's under it at Start the system (0163 §10).
        foreach (array_merge(SystemStart::ORDER, ['W14']) as $wf) $L['wf:' . $wf] = ['workflows', $wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? ''), true, '', $wf];
        foreach (SystemStart::TASKS as $t => $d) if ($t !== 'W14') $L['t:' . $t] = ['tasks', $d[0], true, '', (string) $d[1]];   // W14 is its workflow's line
        // each process's route, hop by hop (decisions/0171 §9): shown, not needed for launch yet (it holds nothing until
        // Release 2 measures each hop); then the workers' routes, and every endpoint claimed by a process
        require_once __DIR__ . '/ControlRegister.php';
        foreach (array_merge(SystemStart::ORDER, ['W14']) as $wf) $L['rt:wf:' . $wf] = ['routes', 'The route of ' . $wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? ''), false, '', $wf];
        foreach (ControlRegister::WORKERS as $k => $w) $L['rt:wk:' . $k] = ['routes', 'The route of ' . $w[0], false, 'railway', ''];
        $L['rt:claimed'] = ['routes', 'Every endpoint the workers call is named by a process', false, '', ''];
        foreach (self::PHASES as $n => [$name]) $L['ph:' . $n] = ['phases', 'Phase ' . $n . ': ' . $name, true, '', ''];   // they roll up the workflows
        // Check before switching on (Jason, 2026-11-17: "act like the preflight queue"): every line of the switch-on check,
        // the one Start the system reads at its check step, as a line here. Needed for launch: the lines that hold everything;
        // a line for one part (a workflow, a service, W14's hubs) holds only that part. Keys and Railway's schedule are lines
        // too, though Start sees to them in their own steps.
        foreach (self::soList($pdo) as $id => [$what, $scope]) $L[$id] = ['switchon', $what, $scope === 'all', '', ''];
        return $L;
    }

    /** Services any workflow uses, and what everything stands on: needed for launch (Jason, 2026-11-17). Business operations
     *  the engine doesn't run on (payments, domains, sign-in, email) stay optional for the engine's launch. */
    private static function requiredServices(PDO $pdo): array
    {
        $out = array_fill_keys(self::CORE, true);
        try {
            require_once __DIR__ . '/Operations.php'; require_once __DIR__ . '/SystemStart.php';
            // since the source map (2026-11-17), the needs carry "needed for launch": a service with a working backup no longer
            // holds the launch by itself; only what everything stands on stays needed here (CORE)
        } catch (Throwable $x) {}
        return $out;
    }

    // ---- results ----------------------------------------------------------------------------------------------------------

    /** Every stored result: id => [s, said, tested, fix, at, ms, by, hist]. */
    /** Its table, made on first use (no hand step); the cut-off rows it replaces in search_index_state are cleared once. */
    private static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        if ($pdo->query("SHOW TABLES LIKE 'preflight_results'")->fetchColumn() !== false) return;
        $pdo->exec('CREATE TABLE IF NOT EXISTS preflight_results (id VARCHAR(40) NOT NULL PRIMARY KEY, v MEDIUMTEXT NOT NULL, at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        try { $pdo->exec("DELETE FROM search_index_state WHERE k LIKE 'pf:r:%' OR k = 'pf:run'"); } catch (Throwable $x) {}   // once, as the table is made
    }

    private static function load(PDO $pdo, string $id): ?array
    {
        self::ensure($pdo);
        $st = $pdo->prepare('SELECT v FROM preflight_results WHERE id = ?'); $st->execute([$id]);
        $j = json_decode((string) $st->fetchColumn(), true);
        return is_array($j) ? $j : null;
    }

    private static function store(PDO $pdo, string $id, array $v): void
    {
        self::ensure($pdo);
        $pdo->prepare('INSERT INTO preflight_results (id, v, at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE v = VALUES(v), at = VALUES(at)')
            ->execute([$id, json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    }

    public static function results(PDO $pdo): array
    {
        $out = [];
        try {
            self::ensure($pdo);
            foreach ($pdo->query("SELECT id, v FROM preflight_results WHERE id NOT IN ('_run', '_live')")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $j = json_decode((string) $v, true);
                if (is_array($j)) $out[(string) $k] = $j;
            }
        } catch (Throwable $x) { error_log('Preflight::results: ' . $x->getMessage()); }
        return $out;
    }

    public static function result(PDO $pdo, string $id): ?array
    {
        try { return self::load($pdo, $id); } catch (Throwable $x) { return null; }
    }

    private static function save(PDO $pdo, string $id, array $r): void { self::store($pdo, $id, $r); }

    /** Counts and the red required lines: ['ok', 'bad', 'na', 'wait', 'new', 'req_bad' => [id => name], 'at' => last full run]. */
    public static function summary(PDO $pdo, string $group = ''): array
    {
        $res = self::results($pdo); $out = ['ok' => 0, 'bad' => 0, 'na' => 0, 'wait' => 0, 'new' => 0, 'req_bad' => [], 'bad_ids' => [], 'at' => 0];
        foreach (self::lines($pdo) as $id => $l) {
            if ($group !== '' && $l[0] !== $group) continue;   // one group's counts (a run of the switch-on check alone)
            $s = $res[$id]['s'] ?? 'new';
            $out[$s] = ($out[$s] ?? 0) + 1;
            if ($s === 'bad') { $out['bad_ids'][$id] = $l[1]; if ($l[2]) $out['req_bad'][$id] = $l[1]; }
        }
        $run = self::result($pdo, '_run');
        $out['at'] = (int) ($run['at'] ?? 0); $out['by'] = (string) ($run['by'] ?? '');
        $out['start'] = (int) ($run['start'] ?? 0); $out['secs'] = isset($run['secs']) ? (int) $run['secs'] : null; $out['runs'] = (array) ($run['runs'] ?? []);
        $lim = self::slowMs($pdo); $out['slow'] = [];
        foreach (self::results($pdo) as $id => $r) if ((int) ($r['ms'] ?? 0) > $lim && ($group === '' || str_starts_with($id, 'so:') === ($group === 'switchon'))) $out['slow'][$id] = (int) $r['ms'];
        arsort($out['slow']);
        return $out;
    }

    public static function can(): array
    {
        if (!class_exists('Access', false)) require_once __DIR__ . '/../admin/Access.php';
        return Access::can('config.preflight', ['min' => 'executive', 'depts' => []]);
    }

    // ---- running ------------------------------------------------------------------------------------------------------

    /** Runs one line and keeps its result. */
    /** $wordsOnly: the Control Board's watch re-testing a stale line (4e): its result is saved, its alarm's words follow it, never its level. */
    public static function run(PDO $pdo, string $id, string $by = '', bool $wordsOnly = false): array
    {
        $lines = self::lines($pdo);
        if (!isset($lines[$id])) throw new InvalidArgumentException('Not a pre-flight line.');
        @set_time_limit(90);
        $t0 = microtime(true);
        try { $r = self::test($pdo, $id, $lines[$id]); }
        catch (Throwable $x) { error_log('preflight ' . $id . ': ' . $x->getMessage()); $r = ['bad', 'The test itself stopped: ' . mb_substr($x->getMessage(), 0, 300), 'The test', []]; }
        [$s, $said, $tested, $fix] = $r + [3 => []];
        $was = self::result($pdo, $id);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $hist = array_slice(array_merge((array) ($was['hist'] ?? []), [[time(), $s, $ms]]), -10);   // [when, state, how long it took]
        $out = ['s' => $s, 'said' => self::clean((string) $said), 'tested' => (string) $tested, 'fix' => array_values((array) $fix), 'at' => time(), 'ms' => $ms, 'by' => mb_substr($by, 0, 80), 'hist' => $hist];
        if (isset($r[4])) $out['extra'] = $r[4];
        self::save($pdo, $id, $out);
        self::alarm($pdo, $id, $lines[$id], $out, !$wordsOnly);
        return $out;
    }

    /**
     * A line's alarm (decisions/0171 §8: the pre-flight's case becomes alarms, one per line; release 3a): a red required
     * line is a trip while what it holds is switched on (release 3c: tried again first, then an admin's), else a warning; a red optional one a warning, a line on a backup a notice; a line that passes clears it. Its words
     * say what it holds back, in plain words, and its fix is the line's own check.
     */
    public static function alarm(PDO $pdo, string $id, array $line, array $out, bool $event = true, bool $settle = false): void
    {
        try {
            require_once __DIR__ . '/ControlAlarms.php';
            $key = 'pf:' . $id; $s = (string) $out['s'];
            // a phase only adds up its workflows, each with its own alarm: it raises none of its own, and one open from before
            // closes (release 5a, 2026-11-19: six phase trips repeated the eight workflow ones)
            if (str_starts_with($id, 'ph:')) { if (ControlAlarms::openOf($pdo, $key)) ControlAlarms::closeKey($pdo, $key, 'A phase only adds up its workflows; their own alarms say what holds them.'); return; }
            $nm = (string) ($line[1] ?? $id);
            $o0 = ControlAlarms::openOf($pdo, $key); $held = $o0 && $o0['level'] === 'trip';   // a trip stays open until Resolve (one raised before 3c too)
            // its check passes: a warning clears; a trip's pause lifts (decisions/0175 §3) and, as an escalated trip, it says it
            // passes and waits for a person (unless ControlAlarms::ESCALATED_RECOVERS); its words follow the line as it stands
            if (in_array($s, ['ok', 'na'], true)) { if ($held && !empty($o0['tripped_at']) && !ControlAlarms::ESCALATED_RECOVERS) ControlAlarms::retitle($pdo, $key, $nm . ': its check passes now; it waits for an admin\'s Check and start again'); ControlAlarms::clear($pdo, $key, 'Its check passed at ' . gmdate('H:i') . ' UTC.'); return; }
            if (!in_array($s, ['bad', 'warn'], true)) return;
            [, $name, $req] = $line + [null, '', false];
            // one alarm per issue: a workflow whose only red parts are its needs (each its own line, its own alarm) or W0's
            // test batch (the rw:batch line) raises none of its own; its row points to theirs
            if (str_starts_with($id, 'wf:') && $s === 'bad') {
                $bp = array_values(array_filter((array) ($out['extra']['parts'] ?? []), fn ($x) => ($x[1] ?? null) === 'bad'));
                if ($bp && !array_filter($bp, fn ($x) => !(str_starts_with((string) $x[0], 'Needs:') || ($id === 'wf:W0' && str_starts_with((string) $x[0], 'Dry run: the test batch'))))) {
                    $own = implode(' ', array_map(fn ($x) => (string) $x[0], $bp));
                    if ($held) ControlAlarms::retitle($pdo, $key, $nm . ': held by ' . (str_starts_with($own, 'Needs:') ? 'its needs' : 'its test batch') . ', each its own alarm', mb_substr($own, 0, 255));
                    if (!$held || empty($o0['tripped_at'])) ControlAlarms::clear($pdo, $key, 'Its red parts are their own alarms.'); return; }   // a trip stays: its line is still red (one from before 3c closes, as it did)
            }
            $proc = self::procOf($id);
            $said = (string) $out['said'];
            // the part that holds it: a red one, else an amber one, never a passing one (2026-11-19: W0, amber, kept "the
            // switch-on check has no red line for it", its passing gate, as its title)
            $pts = (array) ($out['extra']['parts'] ?? []);
            foreach (['bad', 'warn'] as $want) { $hit = null; foreach ($pts as $pp) if (($pp[1] ?? null) === $want) { $hit = (string) $pp[0]; break; } if ($hit !== null) { $said = $hit; break; } }
            $said = (string) preg_replace('/^What holds it:\s*/u', '', $said);
            $first = preg_match('/^(.{20,240}?[.!?])(\s|$)/u', $said, $m) ? $m[1] : (mb_strlen($said) <= 240 ? $said : (string) preg_replace('/\s+\S*$/u', '', mb_substr($said, 0, 240)) . '.');
            $means = self::meaning($id, $s);
            $fx = array_values(array_filter((array) $out['fix'], fn ($f) => is_array($f) && isset($f[1])));
            // the line's own fix travels with its alarm (routes, 2026-11-19: "the card should always have a route to the specific
            // issue"): a page of ours opens beside it, an outside one in a new tab; then its line on the board, and Check
            $afx = [];
            foreach (array_slice($fx, 0, 2) as $f) $afx[] = ($f[0] ?? '') === 'url' ? [(string) $f[1], 'link', (string) ($f[2] ?? '')] : [(string) $f[1], 'peek', (string) ($f[2] ?? '')];
            $afx[] = ['Its line on the pre-flight', 'peek', '/admin/config/preflight#pf-' . str_replace(':', '-', $id)]; $afx[] = ['Check', 'check', $id];
            $todo = $fx ? 'Its fix: ' . implode('; ', array_map(fn ($f) => (string) $f[1], array_slice($fx, 0, 2))) . '. Then press Check.' : 'Read the whole line on the pre-flight, fix the cause, then press Check.';
            // a trip is a process that "can't run what's switched on" (0171 §7): a required line is one only while the system
            // is on (and its workflow, for a workflow's line); with nothing switched on that needs it, it's a warning Start holds
            require_once __DIR__ . '/Automation.php';
            $on = Automation::master($pdo) === null && (!str_starts_with($proc, 'wf:') || Automation::wfOff($pdo, substr($proc, 3)) === null);
            if ($req && !$on && $s === 'bad') $means = trim($means . ' Nothing switched on needs it yet: Start holds it until it passes.');
            // a trip from before 3c (no trip time): the watch settles it to what a fresh raise would be today (control-board-
            // legacy, the session manager, 2026-11-19): amber becomes its notice or warning, red keeps the trip with its time
            if ($settle && $o0 && $o0['level'] === 'trip' && empty($o0['tripped_at'])) {
                $lv = $s === 'warn' ? 'notice' : ($req && $on ? 'trip' : 'warning');
                $pdo->prepare('UPDATE control_alarms SET level = ?, tripped_at = ' . ($lv === 'trip' ? 'UTC_TIMESTAMP()' : 'NULL') . ', means = ?, todo = ? WHERE id = ?')->execute([$lv, mb_substr($means, 0, 400), mb_substr($todo, 0, 400), (int) $o0['id']]);
                ControlAlarms::retitle($pdo, $key, (string) $name . ': ' . $first, mb_substr($said, 0, 255));
                try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, 'control', $proc, 'An old trip settled: ' . ($lv === 'trip' ? 'still red today, timed from now' : 'amber today, now a ' . $lv), 'the system', (string) $name . ': ' . $first); } catch (Throwable $x) {}
                return;
            }
            if (!$event) { ControlAlarms::retitle($pdo, $key, (string) $name . ': ' . $first, mb_substr($said, 0, 255));
                try { $pdo->prepare('UPDATE control_alarms SET fix = ? WHERE akey = ? AND cleared_at IS NULL')->execute([json_encode($afx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $key]); } catch (Throwable $x) {}
                return; }   // the watch's refresh: words only, never a level
            ControlAlarms::raise($pdo, $key, $proc, $s === 'warn' ? 'notice' : ($req && $on ? 'trip' : 'warning'), 'check', (string) $name . ': ' . $first, $means, $todo, mb_substr($said, 0, 255), $afx);
        } catch (Throwable $x) { error_log('preflight alarm ' . $id . ': ' . $x->getMessage()); }
    }

    /** The board's process for a pre-flight line. */
    public static function procOf(string $id): string
    {
        if (preg_match('/^(wf|svc|site|t|job):/', $id)) return $id;
        if (str_starts_with($id, 'rt:')) return substr($id, 3);
        if ($id === 'rw:beat' || $id === 'rw:batch') return 'wk:w0-indexer';
        if (str_starts_with($id, 'rw:')) return 'wk:crawler';
        if (str_starts_with($id, 'nd:')) { $n = self::NEEDS[substr($id, 3)] ?? null; return $n && $n[2] ? 'wf:' . $n[2][0] : 'site:needs'; }
        if (str_starts_with($id, 'ms:')) return 'site:db';
        return $id;
    }

    /** What a red (or backup) line means for the system, in a few plain words. */
    private static function meaning(string $id, string $s): string
    {
        if ($s === 'warn') return 'It works on a backup: nothing is held, but its first source needs a look.';
        if (str_starts_with($id, 'wf:')) return substr($id, 3) . ' won\'t start until this is fixed; the rest of the system runs.';
        if (str_starts_with($id, 'svc:')) return 'What needs this company waits; everything else runs.';
        if (str_starts_with($id, 'nd:')) { $n = self::NEEDS[substr($id, 3)] ?? null; return $n ? 'Nothing gives "' . preg_replace('/ \(.*\)$/', '', $n[1]) . '", so ' . $n[4] . '.' : 'A need has nothing that answers it.'; }
        if (str_starts_with($id, 'ms:')) return 'A heavy query is slower than its limit: pages that use it are slow; nothing stops.';
        if (str_starts_with($id, 'rw:')) return 'A worker on Railway isn\'t ready: its work waits.';
        if (str_starts_with($id, 'site:')) return 'Part of the site itself isn\'t ready; what depends on it waits.';
        return 'Its check didn\'t pass; what depends on it waits.';
    }

    /** Lines already tested fresh in this request by refreshFor (many workflows share a service). */
    private static array $fresh = [];

    /**
     * Before Start the system confirms a workflow or task (decisions/0171 §15.1; the gap: workflow() read the needs'
     * stored results, so a stale or never-run need didn't hold it): its outside services' and workers' lines are tested
     * again when older than pf_fresh_mins or never run, then the source map's needs that read them, always. Stops at
     * $until (a timestamp) and says so (false), so the look carries on at the next one; true when every one is fresh.
     */
    public static function refreshFor(PDO $pdo, string $id, int $until, string $by = ''): bool
    {
        require_once __DIR__ . '/ControlRegister.php';
        require_once __DIR__ . '/Thresholds.php';
        $max = 60 * max(1, (int) Thresholds::get($pdo, 'pf_fresh_mins'));
        $lines = self::lines($pdo);
        foreach (ControlRegister::dependsOn($pdo, $id) as $dep) {
            if (!isset($lines[$dep]) || isset(self::$fresh[$dep])) continue;
            if (!str_starts_with($dep, 'nd:')) { $was = self::result($pdo, $dep); if ($was && time() - (int) ($was['at'] ?? 0) < $max) { self::$fresh[$dep] = true; continue; } }
            if (time() >= $until) return false;
            self::run($pdo, $dep, $by);
            self::$fresh[$dep] = true;
        }
        return true;
    }

    /** Runs every line in order (the nightly run, a .env change): services first, so what's under them reads fresh results.
     *  The same server-side run as the page's Run all, so the page shows it while it goes. */
    public static function runAll(PDO $pdo, string $by): array
    {
        $l = self::live($pdo);
        if (($l['state'] ?? '') === 'running') return ['already' => 1];   // a run is going: it isn't started twice
        if (($l['state'] ?? '') === 'interrupted') return ['interrupted' => 1];   // it waits for Continue or Cancel (the page, Pulse)
        self::startRun($pdo, $by);
        self::work($pdo, 0);
        return self::summary($pdo);
    }

    // ---- a full run on the server (Jason, 2026-11-17: "Preflight only works if I stay on that page") -------------------
    // Run all starts it and the page only watches; it keeps going when the page closes (the request carries on after it has
    // answered), one at a time (a lock), its progress kept in '_live'. A run that stops moving (the web server ended the
    // request) is marked Interrupted and waits for a person (Jason: "this should be a prompt to answer continue, cancel"):
    // the page asks Continue or Cancel when it opens, Run all asks first (or Start a new run), and Pulse has both as buttons.
    // Every run is one line in the run log from its start, its status kept up to date (Jason: "status of partial runs go
    // into log right?"): Running, Interrupted at N of M, Continued from N, Cancelled at N of M, Finished.
    public const LIVE = '_live';
    private const STALE = 180;   // seconds with no progress: the run was cut off

    /** The run in progress or the last one, a run that stopped moving marked Interrupted first. [] when there's none. */
    public static function live(PDO $pdo): array
    {
        $l = self::result($pdo, self::LIVE) ?: [];
        if (self::interrupted($l)) $l = self::markInterrupted($pdo, $l);
        return $l;
    }

    /** A run marked running that has stopped moving. */
    public static function interrupted(array $l): bool { return ($l['state'] ?? '') === 'running' && time() - (int) ($l['beat'] ?? 0) > self::STALE; }

    private static function markInterrupted(PDO $pdo, array $l): array
    {
        $l['state'] = 'interrupted'; $l['current'] = '';
        $l['note'] = 'Interrupted at ' . (int) $l['i'] . ' of ' . (int) $l['total'] . ' (started by ' . $l['by'] . ' at ' . gmdate('M j, g:i A', (int) $l['start']) . ' UTC): waiting for Continue or Cancel.';
        self::store($pdo, self::LIVE, $l);
        self::logSet($pdo, $l, 'Interrupted at ' . (int) $l['i'] . ' of ' . (int) $l['total'] . ', waiting for Continue or Cancel');
        return $l;
    }

    /**
     * Starts a full run. A run going now is left as it is. One that was interrupted is answered by $mode: 'continue' (from
     * where it stopped), 'new' (it's cancelled and a new one starts), else it's returned as it is, for the person to choose.
     */
    public static function startRun(PDO $pdo, string $by, string $mode = '', string $group = ''): array
    {
        $l = self::live($pdo);
        if (($l['state'] ?? '') === 'running') return $l;
        if (($l['state'] ?? '') === 'interrupted') {
            if ($mode === 'continue') {
                $l['state'] = 'running'; $l['beat'] = time(); $l['continued'] = (int) ($l['continued'] ?? 0) + 1;
                $l['note'] = 'Continued from ' . (int) $l['i'] . ' of ' . (int) $l['total'] . ' by ' . mb_substr($by, 0, 80) . ' at ' . gmdate('g:i A', time()) . ' UTC.';
                self::store($pdo, self::LIVE, $l);
                self::logSet($pdo, $l, 'Running, continued from ' . (int) $l['i'] . ' of ' . (int) $l['total']);
                return $l;
            }
            if ($mode !== 'new') return $l;
            self::cancelRun($pdo, $by);
        }
        self::$cache = null;
        self::soCheck($pdo, true);   // the switch-on check's lines as they are now, so the run has each one
        $group = isset(self::GROUPS[$group]) ? $group : '';
        $ids = array_keys(array_filter(self::lines($pdo), fn ($x) => $group === '' || $x[0] === $group));   // a group alone: Check before switching on's Run the check
        $l = ['state' => 'running', 'by' => mb_substr($by, 0, 80), 'start' => time(), 'beat' => time(), 'i' => 0, 'total' => count($ids), 'ids' => $ids, 'group' => $group,
              'current' => '', 'waiting' => [], 'round' => 0, 'continued' => 0, 'note' => '', 'log' => 0];
        $l['log'] = self::logStart($pdo, $l);
        self::store($pdo, self::LIVE, $l);
        return $l;
    }

    /** Cancels an interrupted run (or a running one): logged as cancelled where it reached; the page opens fresh again. */
    public static function cancelRun(PDO $pdo, string $by): array
    {
        $l = self::live($pdo);
        if (!in_array($l['state'] ?? '', ['interrupted', 'running'], true)) return $l;
        $l['state'] = 'cancelled'; $l['current'] = ''; $l['end'] = time();
        $l['note'] = 'Cancelled at ' . (int) $l['i'] . ' of ' . (int) $l['total'] . ' by ' . mb_substr($by, 0, 80) . '.';
        self::store($pdo, self::LIVE, $l);
        self::logSet($pdo, $l, 'Cancelled at ' . (int) $l['i'] . ' of ' . (int) $l['total'] . ' by ' . mb_substr($by, 0, 80));
        return $l;
    }

    /** The run's line in the run log, from its start (its status, its report as far as it got). Its id, or 0. */
    private static function logStart(PDO $pdo, array $l): int
    {
        try {
            self::ensureRuns($pdo);
            $pdo->prepare('INSERT INTO preflight_runs (started, finished, secs, by_name, counts, report) VALUES (FROM_UNIXTIME(?), FROM_UNIXTIME(?), NULL, ?, ?, ?)')
                ->execute([(int) $l['start'], (int) $l['start'], (string) $l['by'], json_encode(['status' => 'Running']), 'Running: the report is written as it goes.']);
            $id = (int) $pdo->lastInsertId();
            $keep = (int) $pdo->query('SELECT id FROM preflight_runs ORDER BY id DESC LIMIT 1 OFFSET 49')->fetchColumn();
            if ($keep) $pdo->prepare('DELETE FROM preflight_runs WHERE id < ?')->execute([$keep]);
            return $id;
        } catch (Throwable $x) { error_log('Preflight run log: ' . $x->getMessage()); return 0; }
    }

    /** Brings the run's log line up to date: its status, when it last changed, its counts and its report so far. */
    private static function logSet(PDO $pdo, array $l, string $status): void
    {
        if (empty($l['log'])) return;
        try {
            self::ensureRuns($pdo);
            $g = (string) ($l['group'] ?? ''); $sum = self::summary($pdo, $g); $now = time();
            $counts = ['ok' => $sum['ok'], 'bad' => $sum['bad'], 'na' => $sum['na'], 'wait' => $sum['wait'], 'req_bad' => count($sum['req_bad']), 'slow' => count($sum['slow']),
                       'status' => mb_substr($status, 0, 160), 'reached' => (int) $l['i'], 'total' => (int) $l['total'], 'group' => $g];
            $head = 'Status: ' . $status . '.' . ((int) $l['i'] < (int) $l['total'] ? ' Reached ' . (int) $l['i'] . ' of ' . (int) $l['total'] . ': the lines after that show their earlier results.' : '') . "\n\n";
            $pdo->prepare('UPDATE preflight_runs SET finished = FROM_UNIXTIME(?), secs = ?, counts = ?, report = ? WHERE id = ?')
                ->execute([$now, $now - (int) $l['start'], json_encode($counts), $head . self::report($pdo), (int) $l['log']]);
        } catch (Throwable $x) { error_log('Preflight run log: ' . $x->getMessage()); }
    }

    /**
     * Works the run in progress: each line in order, its progress kept as it goes, then the lines still waiting (W0's test
     * batch) asked again every 20 seconds, five times at most, then the run logged as finished. One worker at a time (a
     * lock); $seconds (0: no limit) is how long this call may go before it hands back. Its state.
     */
    public static function work(PDO $pdo, int $seconds = 0): array
    {
        $lock = false;
        try { $lock = (int) $pdo->query("SELECT GET_LOCK('tv_preflight_run', 0)")->fetchColumn() === 1; } catch (Throwable $x) {}
        if (!$lock) return self::live($pdo);   // another worker has it
        try {
            @set_time_limit($seconds > 0 ? $seconds + 120 : 0);
            $t0 = time(); $l = self::live($pdo);
            if (($l['state'] ?? '') !== 'running') return $l;
            $lines = self::lines($pdo);
            $beat = function (array &$l) use ($pdo) {
                $now = self::result($pdo, self::LIVE) ?: [];
                if (($now['state'] ?? '') === 'cancelled') throw new RuntimeException('cancelled');   // a person cancelled it while it ran
                $l['beat'] = time(); self::store($pdo, self::LIVE, $l);
            };
            try {
                while ((int) $l['i'] < (int) $l['total']) {
                    if ($seconds > 0 && time() - $t0 > $seconds) return $l;
                    $id = (string) $l['ids'][(int) $l['i']];
                    $l['current'] = $id; $beat($l);
                    if (isset($lines[$id])) { $r = self::run($pdo, $id, (string) $l['by']); if ($r['s'] === 'wait') $l['waiting'][] = $id; }
                    $l['i'] = (int) $l['i'] + 1; $beat($l);
                }
                while ($l['waiting'] && (int) $l['round'] < 5) {
                    if ($seconds > 0 && time() - $t0 > $seconds) return $l;
                    $l['round'] = (int) $l['round'] + 1; $l['current'] = ''; $beat($l);
                    sleep(20);
                    $again = $l['waiting']; $l['waiting'] = [];
                    foreach ($again as $id) { $r = self::run($pdo, (string) $id, (string) $l['by']); if ($r['s'] === 'wait') $l['waiting'][] = $id; $beat($l); }
                }
            } catch (RuntimeException $x) { if ($x->getMessage() === 'cancelled') return self::live($pdo); throw $x; }
            $l['state'] = 'done'; $l['end'] = time(); $l['current'] = ''; self::store($pdo, self::LIVE, $l);
            if ((string) ($l['group'] ?? '') === '') self::lastFull($pdo, (string) $l['by'], (int) $l['start']);   // a group's run isn't a full run
            self::logSet($pdo, $l, 'Finished' . ((int) ($l['continued'] ?? 0) ? ' (continued ' . (int) $l['continued'] . ' time' . ((int) $l['continued'] === 1 ? '' : 's') . ' after it stopped)' : ''));
            return $l;
        } finally {
            try { $pdo->query("SELECT RELEASE_LOCK('tv_preflight_run')"); } catch (Throwable $x) {}
        }
    }

    /** What the page shows of the run: its state, how far, the line being tested, who started it and when. */
    public static function liveLines(PDO $pdo): array
    {
        $l = self::live($pdo);
        if (!$l) return ['state' => 'none'];
        $lines = self::lines($pdo); $cur = (string) ($l['current'] ?? '');
        return ['state' => self::interrupted($l) ? 'stalled' : (string) $l['state'], 'i' => (int) ($l['i'] ?? 0), 'total' => (int) ($l['total'] ?? 0), 'by' => (string) ($l['by'] ?? ''),
                'start' => (int) ($l['start'] ?? 0), 'current' => $cur !== '' ? (string) ($lines[$cur][1] ?? $cur) : '', 'waiting' => count((array) ($l['waiting'] ?? [])),
                'round' => (int) ($l['round'] ?? 0), 'note' => (string) ($l['note'] ?? ''), 'ids' => array_values((array) ($l['ids'] ?? []))];
    }

    /** A full run ended (the page's Run all, or the job): its start, end and length kept, with the last 10 runs. */
    public static function finished(PDO $pdo, string $by, int $start = 0, string $note = ''): void
    {
        self::lastFull($pdo, $by, $start);
        // the run log (Jason, 2026-11-17: "log its runs and have the report downloadable from that log line"): a run the page
        // drove itself (before the server run), logged at its end
        try {
            self::ensureRuns($pdo);
            $sum = self::summary($pdo); $now = time(); $start = $start > 0 && $start <= $now && $now - $start < 6 * 3600 ? $start : 0;
            $counts = ['ok' => $sum['ok'], 'bad' => $sum['bad'], 'na' => $sum['na'], 'wait' => $sum['wait'], 'req_bad' => count($sum['req_bad']), 'slow' => count($sum['slow']), 'status' => $note !== '' ? mb_substr($note, 0, 160) : 'Finished'];
            $pdo->prepare('INSERT INTO preflight_runs (started, finished, secs, by_name, counts, report) VALUES (FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?, ?, ?, ?)')
                ->execute([$start ?: $now, $now, $start ? $now - $start : null, mb_substr($by, 0, 80), json_encode($counts), self::report($pdo)]);
        } catch (Throwable $x) { error_log('Preflight run log: ' . $x->getMessage()); }
    }

    /** The last full run's start, end and length (the counts' "Last full run"), with the last 10 runs. */
    private static function lastFull(PDO $pdo, string $by, int $start): void
    {
        $was = self::result($pdo, '_run') ?: [];
        $now = time(); $start = $start > 0 && $start <= $now && $now - $start < 6 * 3600 ? $start : 0;
        $runs = array_slice(array_merge((array) ($was['runs'] ?? []), [[$start ?: $now, $start ? $now - $start : null, mb_substr($by, 0, 80)]]), -10);
        self::store($pdo, '_run', ['at' => $now, 'start' => $start, 'secs' => $start ? $now - $start : null, 'by' => mb_substr($by, 0, 80), 'runs' => $runs]);
    }

    /** The run log's table, made on first use; staging's own (staging-tables.php). */
    private static function ensureRuns(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $pdo->exec('CREATE TABLE IF NOT EXISTS preflight_runs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, started DATETIME NOT NULL, finished DATETIME NOT NULL, secs INT NULL,
                    by_name VARCHAR(80) NOT NULL DEFAULT \'\', counts VARCHAR(400) NOT NULL DEFAULT \'\', report MEDIUMTEXT NOT NULL, KEY finished (finished)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** The run log, newest first: [[id, started, finished, secs, by, counts]] (times as Unix seconds). */
    public static function runs(PDO $pdo, int $n = 50): array
    {
        try {
            self::ensureRuns($pdo);
            $rows = $pdo->query('SELECT id, UNIX_TIMESTAMP(started) s, UNIX_TIMESTAMP(finished) f, secs, by_name, counts FROM preflight_runs ORDER BY id DESC LIMIT ' . max(1, min(50, $n)))->fetchAll(PDO::FETCH_ASSOC);
            return array_map(fn ($r) => ['id' => (int) $r['id'], 'start' => (int) $r['s'], 'end' => (int) $r['f'], 'secs' => $r['secs'] !== null ? (int) $r['secs'] : null, 'by' => (string) $r['by_name'], 'counts' => json_decode((string) $r['counts'], true) ?: []], $rows);
        } catch (Throwable $x) { return []; }
    }

    /** One logged run's report, as it stood when the run ended: [text, finished] or null. */
    public static function runReport(PDO $pdo, int $id): ?array
    {
        try {
            self::ensureRuns($pdo);
            $st = $pdo->prepare('SELECT report, UNIX_TIMESTAMP(finished) f FROM preflight_runs WHERE id = ?'); $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return $r ? [(string) $r['report'], (int) $r['f']] : null;
        } catch (Throwable $x) { return null; }
    }

    /** A line slower than this (ms) is marked slow, to come back to (Thresholds pf_slow_ms). */
    public static function slowMs(PDO $pdo): int
    {
        require_once __DIR__ . '/Thresholds.php';
        return (int) (Thresholds::get($pdo, 'pf_slow_ms') ?? 5000);
    }

    /** "3 min 12 s", "48 s", "1.2 s". */
    public static function took(?int $secs, ?int $ms = null): string
    {
        if ($ms !== null) return $ms < 1000 ? $ms . ' ms' : number_format($ms / 1000, 1) . ' s';
        if ($secs === null) return '';
        return $secs >= 60 ? intdiv($secs, 60) . ' min ' . ($secs % 60) . ' s' : $secs . ' s';
    }

    /** No secret ever leaves in an answer: long tokens and key=value pairs are cut out. */
    private static function clean(string $s): string
    {
        $s = (string) preg_replace('/((?:key|token|secret|password|apikey|api_key|access_token)=)[^\s"\'&,}]+/i', '$1…', $s);   // key=value (a URL's query)
        $s = (string) preg_replace('/("(?:key|token|secret|password|apikey|api_key|access_token|client_secret)"\s*:\s*")[^"]+/i', '$1…', $s);   // "token": "…" (JSON)
        $s = (string) preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '…', $s);
        return mb_substr(trim($s), 0, 6000);   // whole: an answer cut short hid W1's reason (Jason's report, 2026-11-17)
    }

    private static function env(string $k): string
    {
        Database::readEnv();
        $v = getenv($k);
        if (($v === false || $v === '') && isset($_ENV[$k])) $v = (string) $_ENV[$k];
        return $v === false ? '' : trim((string) $v);
    }

    /** A person asked for this check now ("Check it now", 4f): a back-off's wait doesn't hold it; a refusal or block still does. */
    public static bool $now = false;
    /** The one engine's own try (its diagnostic clock): past a back-off's wait, never past a refusal, a block or a 429. */
    public static bool $systemTry = false;

    /** One outside call, through the gates. Returns [code, body, error, ms, headers]; code -1 when held (the reason in error). */
    private static function http(PDO $pdo, string $method, string $url, array $headers = [], $body = null, string $what = '', int $timeout = 15): array
    {
        require_once __DIR__ . '/StagingGate.php';
        if (($w = StagingGate::hold($url, 'Pre-flight: ' . $what)) !== null) return [-1, '', $w, 0, []];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (($w = self::held($pdo, $host)) !== null) return [-1, '', $w, 0, []];
        $hdr = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Traversence pre-flight (+https://traversence.com/)', CURLOPT_HTTPHEADER => $headers, CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_HEADERFUNCTION => function ($c, $l) use (&$hdr) { $p = strpos($l, ':'); if ($p) $hdr[strtolower(trim(substr($l, 0, $p)))] = trim(substr($l, $p + 1)); return strlen($l); }]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        $t = microtime(true);
        $b = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        // it answered: an open back-off issue at this host (a timeout, a server error) is closed, as any of the site's own
        // calls would close it (2026-11-19: Claude's #19 stayed open while nothing else called it). A refusal or block isn't
        // a back-off, so it waits for a person (ServiceHealth::answered closes only back-offs).
        if ($code >= 200 && $code < 300) { try { require_once __DIR__ . '/ServiceHealth.php'; ServiceHealth::answered($pdo, $host, 'site'); } catch (Throwable $x) {} }
        return [$code, is_string($b) ? $b : '', $err, (int) round((microtime(true) - $t) * 1000), $hdr];
    }

    /** A host held by an open service issue (a ban, a refused key): not asked again here; the issue is the answer. */
    private static function held(PDO $pdo, string $host): ?string
    {
        try {
            require_once __DIR__ . '/ServiceHealth.php';
            $r = ServiceHealth::open($pdo, $host, 'site');
            // a person's check goes past a back-off's wait, and past a refused key or a used-up quota (what our own change
            // fixes: a new key, a top-up), for that one request (the one engine's P7: a hold never blocks its own way out; g2 F2).
            // Never past a 429's wait (it asked us to slow down), nor past a block, which waits for Reactivate (principle 1).
            if ($r && self::$now && $r['kind'] !== 'rate' && ((ServiceHealth::KINDS[$r['kind']][1] ?? '') === 'back' || in_array($r['kind'], ['auth', 'quota'], true))) return null;
            if ($r && self::$systemTry && $r['kind'] !== 'rate' && (ServiceHealth::KINDS[$r['kind']][1] ?? '') === 'back') return null;
            if ($r && ServiceHealth::holding($r)) { [$t, $w] = ServiceHealth::describe($r); return 'Held by issue #' . (int) $r['id'] . ': ' . $t . '. ' . $w; }
        } catch (Throwable $x) {}
        return null;
    }

    /** A call's answer in words: [ok, words]. */
    private static function said(array $r, array $okCodes = [200]): array
    {
        [$code, $b, $err, $ms] = $r;
        if ($code === -1) return [null, $err];
        if ($code === 0) return [false, 'No answer' . ($err !== '' ? ': ' . $err : '') . '.'];
        $j = json_decode($b, true); $msg = '';
        if (is_array($j)) foreach (['message', 'error_description', 'error', 'detail', 'title'] as $k) if (isset($j[$k]) && is_scalar($j[$k])) { $msg = (string) $j[$k]; break; }
        if ($msg === '' && is_array($j['error'] ?? null)) $msg = (string) ($j['error']['message'] ?? '');
        if ($msg === '' && is_array($j['errors'][0] ?? null)) $msg = (string) ($j['errors'][0]['message'] ?? '');
        if ($msg === '' && $b !== '' && !in_array($code, $okCodes, true)) $msg = preg_match('#<title[^>]*>(.*?)</title>#is', $b, $m) ? 'a page titled ' . trim(html_entity_decode($m[1])) : mb_substr(trim((string) preg_replace('/\s+/', ' ', strip_tags($b))), 0, 160);
        if ($code === 403 && preg_match('/Just a moment|cf-chl|challenge-platform/i', $b)) $msg = 'Cloudflare\'s bot check stopped us (a challenge page)';
        return [in_array($code, $okCodes, true), 'Answered ' . $code . ' in ' . $ms . ' ms' . ($msg !== '' ? ': "' . $msg . '"' : '') . '.'];
    }

    /** Hours as words: "40 min", "3 h", "2 days". */
    private static function eta(float $h): string { return $h < 1 ? max(1, (int) round($h * 60)) . ' min' : ($h < 48 ? (int) round($h) . ' h' : (int) round($h / 24) . ' days'); }
    private static function fixUrl(string $label, string $url): array { return ['url', $label, $url]; }
    private static function fixTool(string $label, string $url): array { return ['tool', $label, $url]; }
    private static function fixDo(string $act, string $label): array { return ['do', $act, $label]; }
    private static function envFix(string $names): array { return self::fixUrl('Add ' . $names . ' in .env (cPanel → File Manager)', self::ENV_URL); }

    // ---- the tests -----------------------------------------------------------------------------------------------------

    /** [state ok|bad|na|wait, the answer in words, what was tested, fixes, extra?]. */
    private static function test(PDO $pdo, string $id, array $line): array
    {
        [$grp] = $line;
        if ($grp === 'services') return self::service($pdo, substr($id, 4));
        if ($grp === 'timing') return self::timing($pdo, substr($id, 3));
        if ($grp === 'workflows') return self::workflow($pdo, substr($id, 3));
        if ($grp === 'phases') return self::phase($pdo, (int) substr($id, 3));
        if ($grp === 'tasks') return self::task($pdo, substr($id, 2));
        if ($grp === 'needs' || $grp === 'siteneeds') return self::need($pdo, substr($id, 3));
        if ($grp === 'switchon') return self::soTest($pdo, $id);
        if ($grp === 'routes') { require_once __DIR__ . '/ControlRegister.php'; return $id === 'rt:claimed' ? ControlRegister::claimedLine() : ControlRegister::route($pdo, $id); }
        switch ($id) {
            case 'site:db':
                $v = (string) $pdo->query('SELECT VERSION()')->fetchColumn(); $out = ['main (' . Database::mainName() . ')']; $bad = []; $na = [];
                foreach (array_keys(Database::REALMS) as $r) {
                    [$db] = Database::realmConfig($r);
                    if (!$db) { $na[] = $r; continue; }
                    try { Database::realm($r)->query('SELECT 1'); $out[] = $r; } catch (Throwable $x) { $bad[] = $r . ' (' . mb_substr($x->getMessage(), 0, 80) . ')'; }
                }
                return [$bad ? 'bad' : 'ok', 'Answered: ' . implode(', ', $out) . '. Server ' . $v . '.' . ($na ? ' Not set up in .env: ' . implode(', ', $na) . '.' : '') . ($bad ? ' Refused: ' . implode('; ', $bad) . '.' : ''),
                    'A SELECT 1 on each database, with its own login', $bad ? [self::envFix('the DB_… settings')] : []];
            case 'site:env':
                $p = Database::envPath(); $ok = is_file($p) && is_readable($p) && is_writable($p) && is_writable(dirname($p));
                return [$ok ? 'ok' : 'bad', $ok ? 'Readable and writable, with a copy kept before each change (Settings changes it from the admin).' : (!is_file($p) ? 'There\'s no .env above the web root.' : 'The site can read it but not write it, so Settings can\'t change it from the admin.'),
                    'The file\'s permissions (nothing written)', $ok ? [] : [self::fixUrl('Set .env to 0640 in cPanel → File Manager', self::ENV_URL)]];
            case 'site:envdup':   // the site keeps the FIRST line for a name (Database::loadEnv): a newer value below it is silently ignored (Jason's Fly token, 2026-11-17)
                $p = Database::envPath(); $seen = []; $twice = [];
                foreach (is_readable($p) ? (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $n => $l) {
                    $l = trim($l); if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
                    $k = trim(explode('=', $l, 2)[0]);   // the name only: never the value
                    if (isset($seen[$k])) $twice[$k] = ($twice[$k] ?? [$seen[$k]]); else $seen[$k] = $n + 1;
                    if (isset($twice[$k])) $twice[$k][] = $n + 1;
                }
                $list = array_map(fn ($k, $ls) => $k . ' (lines ' . implode(', ', array_unique($ls)) . ')', array_keys($twice), $twice);
                return [$twice ? 'bad' : 'ok', $twice ? 'Set more than once: ' . implode('; ', $list) . '. The site uses the first line and ignores the rest, so a newer value further down has no effect: keep one line each, with the value you want.' : 'Each of its ' . count($seen) . ' names is set once.',
                    'The names in .env (never their values), counted', $twice ? [self::fixUrl('Edit .env in cPanel → File Manager (the home folder, above public_html)', self::ENV_URL)] : []];
            case 'site:dbup':
                require_once __DIR__ . '/DbUpdates.php';
                $w = DbUpdates::all($pdo); $f = array_keys(array_filter(DbUpdates::log($pdo), fn ($r) => !(int) $r['ok']));
                return [!$w && !$f ? 'ok' : 'bad', !$w && !$f ? 'None waiting, none failed.' : trim(($w ? count($w) . ' waiting (' . implode(', ', array_slice(array_column($w, 'file'), 0, 3)) . '). ' : '') . ($f ? count($f) . ' failed: ' . implode(', ', array_slice($f, 0, 3)) . '.' : '')),
                    'The migrations folder and the database updates log', $w || $f ? [self::fixDo('dbup', 'Run database updates now'), self::fixTool('Database updates', '/admin/config/dbupdates')] : []];
            case 'site:sched':
                require_once __DIR__ . '/Scheduler.php'; require_once __DIR__ . '/CPanel.php';
                if (!CPanel::ready()) return ['bad', 'cPanel\'s API isn\'t connected (CPANEL_HOST, CPANEL_USER, CPANEL_TOKEN), so the jobs can\'t be read or set up from here.', 'cPanel\'s cron list', [self::envFix('CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN')]];
                $lines = self::cron();
                if ($lines === null) return ['bad', 'cPanel didn\'t answer the cron list just now.', 'cPanel\'s cron list (read only)', [self::fixTool('Scheduled tasks', '/admin/config/scheduler')]];
                $miss = [];
                foreach (Scheduler::AUTO as $j) if (!Scheduler::lineFor($lines, $j)) $miss[] = $j;
                return [$miss ? 'bad' : 'ok', $miss ? count($miss) . ' of ' . count(Scheduler::AUTO) . ' automatic jobs have no cron line: ' . implode(', ', $miss) . '. They\'re added within the hour by themselves, or now with the button.' : 'All ' . count(Scheduler::AUTO) . ' automatic jobs have their cron line (' . count($lines) . ' lines in cPanel).',
                    'cPanel\'s cron list (read only), against Scheduler::AUTO', $miss ? [self::fixDo('sched', 'Set them up now'), self::fixTool('Scheduled tasks', '/admin/config/scheduler')] : []];
            case 'site:jobs':
                require_once __DIR__ . '/Scheduler.php';
                $bad = []; foreach (Scheduler::lastRuns($pdo) as $j => $r) if ((int) ($r['fails_today'] ?? 0)) $bad[] = (Scheduler::JOBS[$j][0] ?? $j) . ' (' . (int) $r['fails_today'] . ')';
                return [$bad ? 'bad' : 'ok', $bad ? 'Stopped in the last day: ' . implode(', ', $bad) . '.' : 'None stopped in the last day.', 'Each job\'s runs, as it records them', $bad ? [self::fixTool('Scheduled tasks', '/admin/config/scheduler')] : []];
            case 'site:token':
                if (self::env('CRAWLER_API_TOKEN') === '') return ['bad', 'CRAWLER_API_TOKEN isn\'t in .env, so neither worker can reach the site.', 'The setting', [self::envFix('CRAWLER_API_TOKEN (the same value as on Railway)')]];
                $seen = [];
                try { $at = $pdo->query('SELECT MAX(started_at) FROM crawl_runs')->fetchColumn(); if ($at) $seen[] = 'the crawler, ' . self::ago((int) strtotime((string) $at)); } catch (Throwable $x) {}
                try { require_once __DIR__ . '/crawler/Frontier.php'; $b = Frontier::lastBeat($pdo); if (!empty($b['at'])) $seen[] = 'W0 Indexer, ' . self::ago((int) $b['at']); } catch (Throwable $x) {}
                return [$seen ? 'ok' : 'wait', $seen ? 'Set, and accepted from: ' . implode('; ', $seen) . ' (the site refuses a wrong key, so a call that got in proves it matches).' : 'Set; no worker has used it yet.', 'The setting, and the workers\' last calls that got in', $seen ? [] : [self::fixUrl('Railway: both services → Variables', 'https://railway.com/dashboard')]];
            case 'site:health':
                $on = self::env('HEALTH_TOKEN') !== '';
                return [$on ? 'ok' : 'bad', $on ? 'Set. It must match the GitHub secret HEALTH_TOKEN, which the site can\'t read; the daily report failing would be the sign.' : 'Not set: the daily health report can\'t read the server.', 'The setting', [self::fixUrl('GitHub: the HEALTH_TOKEN secret', 'https://github.com/degraffics/traversence/settings/secrets/actions')] + ($on ? [] : [1 => self::envFix('HEALTH_TOKEN')])];
            case 'site:staging':
                require_once __DIR__ . '/Staging.php'; require_once __DIR__ . '/StagingGate.php';
                $root = dirname(__DIR__, 2);
                $hits = is_dir($root . '/app/lib') ? StagingGate::scan($root) : [];
                return [$hits ? 'bad' : 'ok', (Staging::here() ? 'This is staging: outside calls are held, sandboxed or paced. ' : 'This is the live site. ') . ($hits ? count($hits) . ' outside call(s) skip the gate, first ' . $hits[0][0] . ':' . $hits[0][1] . ' (' . $hits[0][2] . ').' : 'No outside call skips the gate.'),
                    'The code scanned for ungated calls (StagingGate::scan)', $hits ? [self::fixTool('Staging', '/admin/config/staging')] : []];
            case 'site:state':   // a long value written and read back (2026-11-17: search_index_state.v was VARCHAR(200), and Start the system's state was cut)
                require_once __DIR__ . '/SearchIndex.php';
                $type = '';
                try { $type = (string) $pdo->query("SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME, ' ', COLUMN_TYPE) SEPARATOR ', ') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'search_index_state' AND COLUMN_NAME IN ('v', 'v_long')")->fetchColumn(); } catch (Throwable $x) {}
                $long = json_encode(['test' => str_repeat('Long settings are kept whole. ', 160), 'at' => time()]);
                try { SearchIndex::setState($pdo, 'pf:roundtrip', (string) $long); $back = SearchIndex::state($pdo, 'pf:roundtrip'); $pdo->prepare('DELETE FROM search_index_state WHERE k = ?')->execute(['pf:roundtrip']); }
                catch (Throwable $x) { return ['bad', 'Couldn\'t write or read the test value: ' . $x->getMessage(), 'A ' . strlen((string) $long) . '-character value written and read back', []]; }
                $ok = $back === $long;
                return [$ok ? 'ok' : 'bad', $ok ? 'A ' . strlen((string) $long) . '-character value came back whole (columns: ' . $type . ').'
                    : 'A ' . strlen((string) $long) . '-character value came back as ' . strlen((string) $back) . ' characters: the table (' . $type . ') cuts long settings, so Start the system would lose its place. The database update 2026-11-17_search_index_state_wide.sql adds v_long, which holds them whole.',
                    'A ' . strlen((string) $long) . '-character value written to search_index_state and read back, then removed', $ok ? [] : [self::fixDo('dbup', 'Run the database updates now')]];
            case 'site:php':
                return ['ok', 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . '), curl ' . (function_exists('curl_version') ? (curl_version()['version'] ?? '?') : 'missing') . '.', 'The running version', []];
            case 'rw:api': return self::railwayApi($pdo);
            case 'rw:paused': return self::railwayPaused($pdo);
            case 'rw:beat': return self::w0Beat($pdo);
            case 'rw:batch': return self::w0Batch($pdo);
            case 'rw:crawler':
                try {
                    $r = $pdo->query('SELECT started_at, status, why, worker_v FROM crawl_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                    if (!$r) return ['wait', 'No crawler run has reached the site yet.', 'The crawler\'s runs, as the site records them', [self::fixUrl('Railway: the crawler service', 'https://railway.com/dashboard')]];
                    return ['ok', 'Its last run (worker ' . ($r['worker_v'] ?: '?') . ') reached the site ' . self::ago((int) strtotime((string) $r['started_at'])) . ': ' . $r['status'] . ($r['why'] ? ' ("' . mb_substr((string) $r['why'], 0, 120) . '")' : '') . '. While it\'s paused, each run ends at once; that\'s expected.', 'The crawler\'s runs, as the site records them', []];
                } catch (Throwable $x) { return ['bad', 'Couldn\'t read its runs: ' . $x->getMessage(), 'The crawler\'s runs', []]; }
        }
        return ['na', 'No test for this line yet.', '', []];
    }

    private static function ago(int $ts): string
    {
        $d = max(0, time() - $ts);
        return $d < 120 ? $d . ' seconds ago' : ($d < 7200 ? round($d / 60) . ' minutes ago' : ($d < 172800 ? round($d / 3600) . ' hours ago' : round($d / 86400) . ' days ago'));
    }

    private static function cron(): ?array
    {
        if (isset(self::$cache['cron'])) return self::$cache['cron'] ?: null;
        try { require_once __DIR__ . '/Scheduler.php'; $l = Scheduler::cronLines(); } catch (Throwable $x) { $l = []; }
        self::$cache['cron'] = $l;
        return $l ?: null;
    }

    // ---- outside companies (each card's Test) ------------------------------------------------------------------------

    /** A key that lives on Railway: the crawler reports whether it has it at each run (never the value). */
    /** The names of the crawler service's variables on Railway (its API, read only), or null. The values come back in the
     *  same answer and are dropped at once: only the names are kept, never logged or stored. */
    private static function railwayVarNames(PDO $pdo): ?array
    {
        if (array_key_exists('rwvars', (array) self::$cache)) return self::$cache['rwvars'];
        $names = null;
        if (self::env('RAILWAY_API_TOKEN') !== '' && self::env('RAILWAY_PROJECT_ID') !== '' && self::env('RAILWAY_SERVICE_ID') !== '') {
            $p = self::railwayGql($pdo, 'query($p: String!) { project(id: $p) { environments { edges { node { id name } } } } }', ['p' => self::env('RAILWAY_PROJECT_ID')]);
            $env = ''; foreach ($p[2]['project']['environments']['edges'] ?? [] as $e) { if ($env === '' || strtolower((string) $e['node']['name']) === 'production') $env = (string) $e['node']['id']; }
            if ($p[0] && $env !== '') {
                $v = self::railwayGql($pdo, 'query($p: String!, $e: String!, $s: String) { variables(projectId: $p, environmentId: $e, serviceId: $s) }', ['p' => self::env('RAILWAY_PROJECT_ID'), 'e' => $env, 's' => self::env('RAILWAY_SERVICE_ID')]);
                if ($v[0] && is_array($v[2]['variables'] ?? null)) $names = array_keys(array_filter($v[2]['variables'], fn ($x) => trim((string) $x) !== ''));   // names of the set ones; the values go no further
                unset($v);
            }
        }
        self::$cache['rwvars'] = $names;
        return $names;
    }

    private static function railwayKey(PDO $pdo, string $svc, string $name, string $what): array
    {
        require_once __DIR__ . '/Operations.php';
        $calls = self::crawlerCalls($pdo, $svc);
        // first Railway's own list (Jason's second report: the crawler has no schedule while paused, so its own report goes stale)
        $vars = self::railwayVarNames($pdo);
        if ($vars !== null) {
            $have = in_array($name, $vars, true);
            return [$have ? 'ok' : 'bad', ($have ? $name . ' is set on the crawler service (read from Railway, names only). ' . $what : $name . ' isn\'t set on the crawler service (read from Railway, names only).') . $calls,
                'The crawler service\'s variable names, from Railway\'s API (values never kept)', $have ? [] : [self::fixUrl('Add ' . $name . ' on Railway: gracious-sparkle → traversence → Variables', 'https://railway.com/dashboard')]];
        }
        $have = Operations::workerKeys()[$name] ?? null;
        if ($have === null) return ['wait', 'The key lives on Railway, where the site can\'t read it; the crawler hasn\'t reported its keys yet (it does at the start of each run).' . $calls, 'The crawler\'s report of its keys (names only)', [self::fixUrl('Railway: the crawler service → Variables', 'https://railway.com/dashboard')]];
        return [$have ? 'ok' : 'bad', ($have ? $name . ' is on Railway (the crawler\'s report). ' . $what : $name . ' isn\'t on Railway (the crawler\'s report).') . $calls, 'The crawler\'s report of its keys (names only) and its calls this month', $have ? [] : [self::fixUrl('Add ' . $name . ' on Railway: the crawler service → Variables', 'https://railway.com/dashboard')]];
    }

    private static function crawlerCalls(PDO $pdo, string $svc): string
    {
        try {
            $st = $pdo->prepare('SELECT SUM(calls) n, SUM(fails) f, MAX(at) last FROM crawl_service_calls WHERE service = ? AND at >= NOW() - INTERVAL 30 DAY'); $st->execute([$svc]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ((int) ($r['n'] ?? 0)) return ' The crawler\'s calls in 30 days: ' . number_format((int) $r['n']) . ', ' . number_format((int) $r['f']) . ' failed; the last ' . self::ago((int) strtotime((string) $r['last'])) . '.';
        } catch (Throwable $x) {}
        return '';
    }

    /** A free public endpoint answers (the service's own reachability, through the gates). */
    private static function reach(PDO $pdo, string $url, string $what, array $ok = [200], string $method = 'GET', array $h = []): array
    {
        $r = self::http($pdo, $method, $url, $h, null, $what);
        [$good, $words] = self::said($r, $ok);
        return [$good === null ? 'wait' : ($good ? 'ok' : 'bad'), $words, $what . ' (' . $method . ' ' . preg_replace('/([?&](key|api_key|apikey)=)[^&]+/', '$1…', $url) . ')', []];
    }

    /**
     * A service whose steps call more than one of its servers: each one tested (playbook "the pre-flight tests the host each
     * step actually uses", the workflow audit of 2026-11-21). [url, what, ok codes, the step that waits when it's down,
     * method]. The first is the server the main step uses: down, the line is red. A later one down: amber, naming only
     * the step that waits (decisions/0175 §3: a trip stops only what it holds).
     */
    private static function reachAll(PDO $pdo, array $parts): array
    {
        $st = 'ok'; $words = []; $whats = []; $fix = [];
        foreach ($parts as $i => $p) {
            [$url, $what, $ok, $step, $method] = array_pad($p, 5, null);
            $r = self::reach($pdo, (string) $url, (string) $what, $ok ?: [200], $method ?: 'GET');
            $whats[] = $r[2];
            if ($r[0] === 'ok') { $words[] = $r[1]; continue; }
            if ($i === 0) $st = $r[0] === 'wait' ? 'wait' : 'bad';
            elseif ($st === 'ok') $st = 'warn';
            $words[] = $what . ': ' . rtrim((string) $r[1], '.') . ($i > 0 && $step ? '. Only ' . $step . ' waits; the rest works.' : '.');
        }
        return [$st, implode(' ', array_filter($words, 'strlen')), implode('; ', $whats), $fix];
    }

    private static function service(PDO $pdo, string $k): array
    {
        $e = fn (string $n) => self::env($n);
        $r = self::serviceTest($pdo, $k, $e);   // its card's link is drawn on every company's line (preflight-console.php)
        try { require_once __DIR__ . '/Automation.php'; if (($p = Automation::serviceOff($pdo, $k)) !== null && $r[0] !== 'na') $r[1] .= ' (Paused on Automation until Start the system: this read-only test runs anyway.)'; } catch (Throwable $x) {}
        return $r;
    }

    private static function serviceTest(PDO $pdo, string $k, callable $e): array
    {
        require_once __DIR__ . '/Operations.php';
        $na = fn (string $why) => ['na', $why, 'Whether it\'s set up', []];
        switch ($k) {
            case 'cloudflare':
                $out = []; $bad = false; $fix = [];
                if (str_contains($e('S3_ENDPOINT'), 'r2.cloudflarestorage.com')) {
                    require_once __DIR__ . '/Aws.php'; require_once __DIR__ . '/S3.php';
                    [$ok, $say] = Aws::checkBucket(); $cors = $ok ? S3::corsOk() : null;
                    $out[] = 'R2: ' . $say . ($cors === false ? '; uploads need the CORS rule' : ($cors ? '; uploads ready' : ''));
                    if (!$ok || $cors === false) { $bad = true; $fix[] = self::fixUrl('R2 buckets', 'https://dash.cloudflare.com/?to=/:account/r2/overview'); }
                } else $out[] = 'R2 isn\'t in use (S3_ENDPOINT).';
                if ($e('TURNSTILE_SECRET_KEY') !== '') {   // a made-up token: a good secret answers "invalid-input-response", a bad one "invalid-input-secret"
                    $r = self::http($pdo, 'POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['secret' => $e('TURNSTILE_SECRET_KEY'), 'response' => 'preflight-not-a-token']), 'Turnstile: the secret checked with a made-up token');
                    $codes = (array) (json_decode($r[1], true)['error-codes'] ?? []);
                    if ($r[0] === -1) $out[] = 'Turnstile: ' . $r[2];
                    elseif (in_array('invalid-input-secret', $codes, true)) { $bad = true; $out[] = 'Turnstile refused our secret (invalid-input-secret).'; $fix[] = self::fixUrl('Turnstile', 'https://dash.cloudflare.com/?to=/:account/turnstile'); }
                    elseif ($r[0] === 200) $out[] = 'Turnstile accepted our secret (it refused only the made-up token, as it should).';
                    else { $bad = true; $out[] = 'Turnstile: ' . self::said($r)[1]; }
                } else $out[] = 'Turnstile isn\'t set up.';
                return [$bad ? 'bad' : 'ok', implode(' ', $out), 'The bucket answers a HEAD and its CORS rule; Turnstile\'s siteverify with a made-up token', $fix];
            case 'aws':
                require_once __DIR__ . '/SesMail.php';
                if (!SesMail::hasKeys('personal')) return $na('SES isn\'t set up (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY).');
                $t = microtime(true);
                try { [$c, $b] = SesMail::api('personal', 'GET', '/v2/email/account'); } catch (Throwable $x) { $c = 0; $b = $x->getMessage(); }
                $j = json_decode((string) $b, true) ?: [];
                if ($c !== 200) return ['bad', 'SES answered ' . $c . ': ' . mb_substr((string) ($j['message'] ?? $b), 0, 200), 'SES GetAccount (read only)', [self::fixUrl('SES', 'https://console.aws.amazon.com/ses/home#/get-set-up'), self::fixUrl('The site\'s key (IAM)', 'https://us-east-1.console.aws.amazon.com/iam/home#/users')]];
                $prod = !empty($j['ProductionAccessEnabled']);
                return [SesMail::mode() === 'live' && !$prod ? 'bad' : 'ok', 'SES accepted our key in ' . SesMail::region() . ' (' . (int) round((microtime(true) - $t) * 1000) . ' ms). Production access: ' . ($prod ? 'yes' : 'not yet (sandbox: only verified addresses)') . '. Mode here: ' . SesMail::mode() . ' (mock records, sends nothing).',
                    'SES GetAccount (read only; nothing is sent)', $prod ? [] : [self::fixUrl('Ask for production access', 'https://console.aws.amazon.com/ses/home#/account')]];
            case 'anthropic':
                if ($e('ANTHROPIC_API_KEY') === '') return $na('Not set up (ANTHROPIC_API_KEY).');
                $r = self::http($pdo, 'GET', 'https://api.anthropic.com/v1/models?limit=1', ['x-api-key: ' . $e('ANTHROPIC_API_KEY'), 'anthropic-version: 2023-06-01'], null, 'Anthropic: list models');
                [$ok, $w] = self::said($r);
                return [$ok === null ? 'wait' : ($ok ? 'ok' : 'bad'), $ok ? 'Our key is accepted (' . $w . ') Listing models costs nothing.' : $w, 'The models list with our key (free; no answer is written)', $ok ? [] : [self::fixUrl('Keys', 'https://console.anthropic.com/settings/keys')]];
            case 'google':
                $out = []; $bad = false;
                $kr = self::railwayKey($pdo, 'google', 'GOOGLE_PLACES_KEY', 'Each check costs about 3 cents, so it isn\'t called here.');
                $out[] = 'Places: ' . $kr[1]; if ($kr[0] === 'bad') $bad = true;
                if ($e('GOOGLE_CLIENT_ID') !== '') {
                    $r = self::http($pdo, 'GET', 'https://accounts.google.com/.well-known/openid-configuration', [], null, 'Google sign-in: its discovery document');
                    $out[] = 'Continue with Google: ' . ($r[0] === 200 ? 'Google\'s sign-in answers; our client ID is set.' : self::said($r)[1]);
                    if ($r[0] !== 200 && $r[0] !== -1) $bad = true;
                }
                return [$bad ? 'bad' : $kr[0], implode(' ', $out), 'The crawler\'s key report (Places costs money, so it\'s never called here); Google\'s sign-in discovery', $kr[3]];
            case 'tavily': return self::railwayKey($pdo, 'tavily', 'TAVILY_API_KEY', 'Each search counts against the month, so none is made here.');
            case 'brave': return self::railwayKey($pdo, 'brave', 'BRAVE_API_KEY', 'Each search counts against its plan, so none is made here.');   // Brave and Tavily both live and ready; which a workflow uses is the workflow's (Jason, 2026-11-17)
            case 'ridb': return self::railwayKey($pdo, 'ridb', 'RIDB_API_KEY', 'Its monthly load runs on the crawler.');
            case 'foursquare':
                $kr = self::railwayKey($pdo, 'foursquare', 'HF_TOKEN', '');
                $r = self::http($pdo, 'GET', 'https://huggingface.co/api/datasets/foursquare/fsq-os-places', [], null, 'Hugging Face: the dataset\'s card');
                $kr[1] .= ' The dataset: ' . ($r[0] === 200 ? 'Hugging Face answers (gated: the token\'s account must have accepted Foursquare\'s terms).' : self::said($r)[1]);
                if ($r[0] > 0 && $r[0] !== 200) $kr[0] = 'bad';
                return $kr;
            case 'railway':
                if ($e('RAILWAY_API_TOKEN') === '') return ['bad', 'RAILWAY_API_TOKEN isn\'t in .env: the site can\'t read or set the crawler\'s schedule.', 'The setting', [self::envFix('RAILWAY_API_TOKEN, RAILWAY_PROJECT_ID and RAILWAY_SERVICE_ID')]];
                $g = self::railwayGql($pdo, 'query($p: String!) { project(id: $p) { name } }', ['p' => self::env('RAILWAY_PROJECT_ID')]);   // a team or project token can't ask "me" (400): the project, as Start the system asks
                return [$g[0] ? 'ok' : 'bad', $g[0] ? 'Railway accepted our token and reads project ' . ($g[2]['project']['name'] ?? '?') . '. (Its services are checked under "W0 and the crawler on Railway".)' : $g[1], 'Railway\'s API: our project (read only)', $g[0] ? [] : [self::fixUrl('Railway: account → Tokens', 'https://railway.com/account/tokens')]];
            case 'bluehost':
                $out = []; $bad = false; $fix = [];
                if (self::env('SMTP_HOST') !== '') {
                    [$ok, $w] = self::smtp($pdo);
                    $out[] = 'The site\'s email: ' . $w; if (!$ok) { $bad = true; $fix[] = self::fixUrl('Email accounts (cPanel)', self::ENV_URL); }
                } else $out[] = 'The site\'s email uses the server\'s own mail (no SMTP_HOST).';
                require_once __DIR__ . '/CPanel.php';
                if (CPanel::ready()) { $l = self::cron(); $out[] = 'cPanel\'s API: ' . ($l !== null ? 'answered (' . count($l) . ' cron lines).' : 'didn\'t answer.'); if ($l === null) $bad = true; }
                else { $out[] = 'cPanel\'s API isn\'t connected.'; $bad = true; $fix[] = self::envFix('CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN'); }
                return [$bad ? 'bad' : 'ok', implode(' ', $out), 'A sign-in to the mail server without sending (EHLO, AUTH, QUIT); cPanel\'s cron list', $fix];
            case 'quickbooks':
                require_once __DIR__ . '/QuickBooksPay.php';
                if (!QuickBooksPay::configured()) return $na('Not set up (QB_CLIENT_ID, QB_CLIENT_SECRET; QB_SANDBOX_… for the sandbox).');
                $c = QuickBooksPay::connection($pdo);
                if (!$c) return ['bad', 'The app is set up (' . QuickBooksPay::env() . ') but no company is connected.', 'The connection on file (no call: a call could renew its tokens)', [self::fixTool('Connect it on E-Commerce → Payments', '/admin/ecommerce.php')]];
                $until = strtotime((string) $c['refresh_until']);
                return [$until > time() + 7 * 86400 ? 'ok' : 'bad', 'Connected (' . QuickBooksPay::env() . '); its sign-in is good until ' . gmdate('M j, Y', $until) . ' UTC' . ($until <= time() ? ': it has run out.' : ($until <= time() + 7 * 86400 ? ': under a week left, reconnect soon.' : '.')), 'The connection on file (no call: a call could renew its tokens)', $until > time() + 7 * 86400 ? [] : [self::fixTool('Reconnect on E-Commerce → Payments', '/admin/ecommerce.php')]];
            case 'stripe':
                $key = $e('STRIPE_TEST_SECRET_KEY') ?: $e('STRIPE_SECRET_KEY');
                if ($key === '') return $na('Not set up (STRIPE_TEST_SECRET_KEY).');
                if (!str_starts_with($key, 'sk_test_') && !str_starts_with($key, 'rk_test_')) return ['bad', 'The key set as the test key isn\'t a test key, so it isn\'t used here (no test touches real money).', 'The key\'s kind', [self::fixUrl('Stripe: test keys', 'https://dashboard.stripe.com/test/apikeys')]];
                $r = self::http($pdo, 'GET', 'https://api.stripe.com/v1/balance', ['Authorization: Bearer ' . $key], null, 'Stripe sandbox: the balance');
                [$ok, $w] = self::said($r);
                return [$ok === null ? 'wait' : ($ok ? 'ok' : 'bad'), ($ok ? 'The sandbox accepted our test key. ' : '') . $w, 'The sandbox\'s balance with the test key (read only; never a live key)', $ok ? [] : [self::fixUrl('Stripe: test keys', 'https://dashboard.stripe.com/test/apikeys')]];
            case 'square':
                $tok = $e('SQUARE_SANDBOX_ACCESS_TOKEN') ?: $e('SQUARE_ACCESS_TOKEN');
                if ($tok === '') return $na('Not set up (SQUARE_SANDBOX_ACCESS_TOKEN).');
                $r = self::http($pdo, 'GET', 'https://connect.squareupsandbox.com/v2/locations', ['Authorization: Bearer ' . $tok, 'Square-Version: 2024-10-17'], null, 'Square sandbox: locations');
                [$ok, $w] = self::said($r);
                return [$ok === null ? 'wait' : ($ok ? 'ok' : 'bad'), ($ok ? 'The sandbox accepted our token. ' : '') . $w, 'The sandbox\'s locations (read only; the sandbox host only)', $ok ? [] : [self::fixUrl('Square: developer apps', 'https://developer.squareup.com/apps')]];
            case 'namecom':
                $u = $e('NAMECOM_TEST_USER') ?: $e('NAMECOM_USER'); $t = $e('NAMECOM_TEST_TOKEN') ?: $e('NAMECOM_TOKEN');
                if ($u === '' || $t === '') return $na('Not set up (NAMECOM_TEST_USER, NAMECOM_TEST_TOKEN).');
                $r = self::http($pdo, 'POST', 'https://api.dev.name.com/core/v1/domains:checkAvailability', ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($u . ':' . $t)], ['domainNames' => ['traversence-preflight-' . gmdate('ymd') . '.com'], 'purchaseType' => 'registration'], 'Name.com sandbox: a search with a price');
                $j = json_decode($r[1], true) ?: []; $x = $j['results'][0] ?? null;
                if ($r[0] === 200 && $x) { $price = (float) ($x['purchasePrice'] ?? 0);
                    return [$price > 0 ? 'ok' : 'bad', 'The sandbox answered ' . $x['domainName'] . ': ' . (!empty($x['purchasable']) ? 'available' : 'not available') . ($price > 0 ? ', $' . number_format($price, 2) . ' a year.' : ', with no price.'), 'A search in Name.com\'s sandbox (api.dev.name.com; nothing bought)', $price > 0 ? [] : [self::fixUrl('Name.com: API settings', 'https://www.name.com/account/settings/api')]]; }
                return [$r[0] === -1 ? 'wait' : 'bad', self::said($r)[1], 'A search in Name.com\'s sandbox (api.dev.name.com; nothing bought)', [self::fixUrl('Name.com: API settings (the sandbox\'s own …-test user and token)', 'https://www.name.com/account/settings/api')]];
            case 'fly':
                if ($e('FLY_API_TOKEN') === '' || $e('FLY_APP') === '') return $na('Not set up (FLY_API_TOKEN, FLY_APP): businesses\' own domains open later.');
                $tok = $e('FLY_API_TOKEN'); $auth = str_starts_with($tok, 'FlyV1 ') ? $tok : (str_starts_with($tok, 'fm1') || str_starts_with($tok, 'fm2') ? 'FlyV1 ' . $tok : 'Bearer ' . $tok);   // a macaroon ("fly tokens create") goes as FlyV1, a personal token as Bearer
                $r = self::http($pdo, 'POST', 'https://api.fly.io/graphql', ['Authorization: ' . $auth, 'Content-Type: application/json'], ['query' => 'query($a: String!) { app(name: $a) { name certificates(first: 50) { totalCount nodes { hostname clientStatus } } } }', 'variables' => ['a' => $e('FLY_APP')]], 'Fly: the edge\'s certificates');
                $j = json_decode($r[1], true) ?: []; $app = $j['data']['app'] ?? null;
                if ($r[0] === 200 && $app) { $n = (int) ($app['certificates']['totalCount'] ?? 0); $pend = count(array_filter((array) ($app['certificates']['nodes'] ?? []), fn ($c) => strtolower((string) $c['clientStatus']) !== 'ready'));
                    return ['ok', 'Our token reads ' . $app['name'] . '\'s certificates: ' . $n . ' in all' . ($pend ? ', ' . $pend . ' not ready yet' : '') . '.', 'The edge app\'s certificate list (read only)', []]; }
                return [$r[0] === -1 ? 'wait' : 'bad', ($r[0] === 200 ? 'Fly answered but couldn\'t read ' . $e('FLY_APP') . ': ' . mb_substr((string) ($j['errors'][0]['message'] ?? 'no app'), 0, 200) : self::said($r)[1]) . ' (Sent as ' . strtok($auth, ' ') . '. FLY_API_TOKEN should be the whole line "fly tokens create deploy -a ' . $e('FLY_APP') . '" prints, starting "FlyV1 ", set once in .env; the .env line above checks for a second one.)', 'The edge app\'s certificate list (read only)', [self::fixUrl('Fly: the traversence organization\'s tokens', 'https://fly.io/dashboard/traversence/tokens')]];
            case 'github':
                $out = []; $bad = false;
                foreach (['GITHUB_DOCS_TOKEN' => 'degraffics/traversence', 'GITHUB_SYNC_TOKEN' => 'degraffics/traverse-3way-sync'] as $n => $repo) {
                    if ($e($n) === '') { $out[] = $n . ': not set.'; $bad = true; continue; }
                    $r = self::http($pdo, 'GET', 'https://api.github.com/repos/' . $repo, ['Authorization: Bearer ' . $e($n), 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'], null, 'GitHub: ' . $repo);
                    $exp = (string) ($r[4]['github-authentication-token-expiration'] ?? '');
                    $soon = $exp !== '' && strtotime($exp) < time() + 14 * 86400;
                    if ($r[0] !== 200 || $soon) $bad = true;
                    $out[] = $n . ': ' . ($r[0] === 200 ? 'reads ' . $repo : self::said($r)[1]) . ($exp !== '' ? '; expires ' . gmdate('M j, Y', (int) strtotime($exp)) . ($soon ? ' (soon: make a new one)' : '') : '; no expiry date') . '.';
                }
                return [$bad ? 'bad' : 'ok', implode(' ', $out), 'Each token reads its repository (and GitHub says when it expires)', $bad ? [self::fixUrl('GitHub: fine-grained tokens', 'https://github.com/settings/personal-access-tokens')] : []];
            case 'microsoft':
                if ($e('MICROSOFT_CLIENT_ID') === '') return $na('Sign in with Microsoft isn\'t set up (MICROSOFT_CLIENT_ID).');
                $t = preg_replace('/[^A-Za-z0-9.-]/', '', $e('MICROSOFT_TENANT') ?: 'common');
                return self::reach($pdo, 'https://login.microsoftonline.com/' . $t . '/v2.0/.well-known/openid-configuration', 'Microsoft sign-in: its discovery document for our tenant') ;
            case 'apple':
                if ($e('APPLE_CLIENT_ID') === '') return $na('Sign in with Apple isn\'t set up (APPLE_CLIENT_ID).');
                return self::reach($pdo, 'https://appleid.apple.com/auth/keys', 'Apple sign-in: the keys it signs with');
            case 'meta':
                if ($e('FACEBOOK_APP_ID') === '' || $e('FACEBOOK_APP_SECRET') === '') return $na('Sign in with Facebook isn\'t set up (FACEBOOK_APP_ID, FACEBOOK_APP_SECRET).');
                $r = self::http($pdo, 'GET', 'https://graph.facebook.com/oauth/access_token?' . http_build_query(['client_id' => $e('FACEBOOK_APP_ID'), 'client_secret' => $e('FACEBOOK_APP_SECRET'), 'grant_type' => 'client_credentials']), [], null, 'Meta: the app\'s own token');
                [$ok, $w] = self::said($r);
                return [$ok === null ? 'wait' : ($ok ? 'ok' : 'bad'), $ok ? 'Meta accepted our app ID and secret (an app token, kept nowhere).' : $w, 'An app token from our ID and secret (nothing posted; kept nowhere)', $ok ? [] : [self::fixUrl('Meta: the app\'s basic settings', 'https://developers.facebook.com/apps/')]];
            case 'census':
                $key = $e('CENSUS_API_KEY');
                // what the steps call: the geocoder (W2's map points, no key), TIGER's files (W9's land boundaries), and the data API
                $r = self::reachAll($pdo, [['https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?address=' . rawurlencode('1700 W Washington St, Phoenix, AZ 85007') . '&benchmark=Public_AR_Current&format=json', 'The Census geocoder: one address'],
                    ['https://www2.census.gov/geo/tiger/TIGER2024/AIANNH/tl_2024_us_aiannh.zip', 'Census TIGER\'s boundary file', [200], 'the land boundaries\' load', 'HEAD'],
                    ['https://api.census.gov/data/2020/dec/pl?get=NAME&for=state:04' . ($key !== '' ? '&key=' . rawurlencode($key) : ''), 'Census: Arizona\'s name from the 2020 count', [200], 'the counts from the data API']]);
                if ($key === '') $r[1] .= ' (No CENSUS_API_KEY: it works without, at a lower daily limit.)';
                return $r;
            case 'osm':   // Overpass retired (Jason, 2026-11-19, "Stop entirely"): no request; what we read instead is our own copy
                require_once __DIR__ . '/ServiceHealth.php'; require_once __DIR__ . '/OpenPlaces.php';
                $cw = ''; try { [, $cw] = OpenPlaces::ourCopy($pdo); } catch (Throwable $x) {}
                return ['na', ServiceHealth::OSM_RETIRED . ($cw !== '' ? ' What we read: ' . $cw . '.' : ''), 'No request: Overpass is retired (' . ServiceHealth::OSM_RETIRED_URL . ')', []];
            case 'wikimedia': return self::reachAll($pdo, [['https://query.wikidata.org/sparql?format=json&query=' . rawurlencode('SELECT ?x WHERE { BIND(wd:Q816 AS ?x) }'), 'Wikidata\'s query service: one answer (what the loads ask)'],
                ['https://www.wikidata.org/w/api.php?action=wbgetentities&ids=Q816&props=labels&languages=en&format=json', 'Wikidata: one record (Arizona)', [200], 'reading one record']]);
            case 'gemini':
                if ($e('GEMINI_API_KEY') === '') return ['bad', 'GEMINI_API_KEY isn\'t in .env.', 'The setting', [self::envFix('GEMINI_API_KEY'), self::fixUrl('Google AI Studio: API keys', 'https://aistudio.google.com/app/apikey')]];
                $r = self::http($pdo, 'GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1', ['x-goog-api-key: ' . $e('GEMINI_API_KEY')], null, 'Gemini: list models');
                [$ok, $w] = self::said($r);
                return [$ok === null ? 'wait' : ($ok ? 'ok' : 'bad'), $ok ? 'Our key is accepted (' . $w . ') Listing models uses none of the day\'s allowance.' : $w, 'The models list with our key (free; nothing generated)', $ok ? [] : [self::fixUrl('Google AI Studio: API keys', 'https://aistudio.google.com/app/apikey')]];
            case 'supabase': return self::supabase($pdo);
            case 'overture': return self::reach($pdo, 'https://overturemaps-us-west-2.s3.us-west-2.amazonaws.com/?list-type=2&prefix=release/&delimiter=/&max-keys=5', 'Overture: its public bucket lists the releases');   // the address the crawler reads
            case 'geofabrik':   // a redirect on Geofabrik's own host is followed (Jason, 2026-11-19: "Not ready: Answered 308"), at most 3
                $gu = 'https://download.geofabrik.de/north-america/us/arizona-updates/state.txt'; $gw = 'Geofabrik: Arizona\'s extract state (a few bytes, not the file)';
                $gr = self::http($pdo, 'GET', $gu, [], null, $gw);
                for ($gi = 0; $gi < 3 && in_array((int) $gr[0], [301, 302, 303, 307, 308], true) && !empty($gr[4]['location']); $gi++) {
                    $loc = (string) $gr[4]['location'];
                    if (!preg_match('#^https?://#i', $loc)) $loc = 'https://download.geofabrik.de' . (str_starts_with($loc, '/') ? '' : '/') . $loc;
                    if (strtolower((string) parse_url($loc, PHP_URL_HOST)) !== 'download.geofabrik.de') break;   // only its own host
                    $gu = $loc; $gr = self::http($pdo, 'GET', $gu, [], null, $gw);
                }
                [$gg, $gwords] = self::said($gr, [200]);
                return [$gg === null ? 'wait' : ($gg ? 'ok' : 'bad'), $gwords, $gw . ' (GET ' . $gu . ')', []];
            case 'azcc':   // the Commission moved from ecorp.azcc.gov (which no longer resolves) to its business center (2026-11-17)
                $r = self::reach($pdo, 'https://arizonabusinesscenter.azcc.gov/', 'The Arizona Corporation Commission\'s business center', [200, 301, 302]);
                if ($r[0] === 'bad' && str_contains($r[1], 'Answered 403')) $r[1] = 'Blocked: the Commission\'s business center answers our server 403 "Access Blocked", pending our public records request. ' . $r[1] . ' Nothing calls it while its service stays paused.';
                return $r;
            case 'nmsos': return self::reach($pdo, 'https://enterprise.sos.nm.gov/search', 'New Mexico\'s business search page', [200, 301, 302]);
            case 'azlicences': return ['na', 'Inactive: not released yet (licence lists, queue 3a). Their sites (azliquor.gov, roc.az.gov, the counties\') sit behind a Cloudflare challenge, so they aren\'t called; their lists come as monthly exports through the uploader when 3a ships.', 'Nothing: not called until released (Jason, 2026-11-17)', []];
            case 'heigit':
                if ($e('ORS_API_KEY') === '') return $na('Not set up (ORS_API_KEY).');
                $base = rtrim($e('ORS_API_URL') ?: 'https://api.heigit.org/openrouteservice', '/');   // its address since 2026 (api.openrouteservice.org is retired), as TravelLegs asks
                $r = self::http($pdo, 'POST', $base . '/v2/matrix/driving-car', ['Authorization: ' . $e('ORS_API_KEY'), 'Content-Type: application/json', 'Accept: application/json'], ['locations' => [[-111.6513, 35.1983], [-111.6097, 35.2135]], 'metrics' => ['duration']], 'openrouteservice: a two-point matrix');   // downtown Flagstaff to the university
                [$ok, $w] = self::said($r);
                if ($r[0] >= 500) $w .= ' That\'s their server\'s error, not our key (a refused key answers 401 or 403): try again later.';
                return [$ok === null ? 'wait' : ($ok ? 'ok' : 'bad'), ($ok ? 'Our key is accepted: one free leg near Kayenta. ' : '') . $w, 'A two-point travel-time matrix in Flagstaff with our key, the call the journeys make (1 of its 500 a day)', $ok ? [] : [self::fixUrl('openrouteservice: your keys', 'https://openrouteservice.org/dev/#/home')]];
            case 'nws': return self::reach($pdo, 'https://api.weather.gov/points/36.7278,-110.2546', 'The National Weather Service: Kayenta\'s forecast point', [200], 'GET', ['Accept: application/geo+json']);
            case 'az511':
                if ($e('AZ511_API_KEY') === '') return $na('Not set up (AZ511_API_KEY).');
                return self::reach($pdo, 'https://az511.com/api/wzdx?key=' . rawurlencode($e('AZ511_API_KEY')), 'AZ 511: the work zones feed with our key');
            case 'nps':   // NPGallery (the National Register's records) needs no key; the photos' API does (decisions/0173 §3: Check proves it)
                if ($e('NPS_API_KEY') !== '') {
                    $r = self::reach($pdo, 'https://developer.nps.gov/api/v1/parks?limit=1&stateCode=AZ', 'The Park Service\'s API: one park in Arizona with our key (free)', [200], 'GET', ['X-Api-Key: ' . $e('NPS_API_KEY')]);
                    if ($r[0] === 'bad') $r[3][] = self::fixUrl('api.data.gov: get or check the key', 'https://api.data.gov/signup/');
                    return $r;
                }
                return self::reach($pdo, 'https://npgallery.nps.gov/', 'NPGallery', [200, 301, 302]);
            case 'usgs': return self::reachAll($pdo, [['https://prd-tnm.s3.amazonaws.com/StagedProducts/GeographicNames/DomesticNames/DomesticNames_AZ_Text.zip', 'USGS GNIS: Arizona\'s names file (what W9 loads)', [200], '', 'HEAD'],
                ['https://basemap.nationalmap.gov/arcgis/rest/services/USGSTopo/MapServer?f=json', 'The National Map: the topo layer\'s description', [200], 'the topo map layer']]);
            case 'cms': return self::reachAll($pdo, [['https://npiregistry.cms.hhs.gov/api/?version=2.1&state=AZ&city=Kayenta&limit=1', 'The NPI Registry: one provider in Kayenta'],
                ['https://download.cms.gov/nppes/NPI_Files.html', 'The NPI files\' page (the monthly load)', [200], 'the monthly NPI load']]);
            case 'irs': return self::reach($pdo, 'https://www.irs.gov/charities-non-profits/exempt-organizations-business-master-file-extract-eo-bmf', 'The IRS\'s EO BMF page', [200, 301, 302]);
            case 'cpsc': return self::reach($pdo, 'https://www.saferproducts.gov/RestWebServices/Recall?format=json&RecallDateStart=' . gmdate('Y-m-d', time() - 3 * 86400), 'SaferProducts.gov: the last three days\' recalls');
            case 'loc': return self::reach($pdo, 'https://www.loc.gov/search/?q=kayenta&fo=json&c=1', 'The Library of Congress: one search result', [200, 301, 302]);
            // the bulk reads (W3's backup, W2's) use data.commoncrawl.org's list of crawls, never the index server; the per-site
            // copy asks the index server too (crawler 2.16.1)
            case 'commoncrawl': return self::reachAll($pdo, [['https://data.commoncrawl.org/crawl-data/index.html', 'Common Crawl: its list of crawls (the bulk reads)'],
                ['https://index.commoncrawl.org/collinfo.json', 'Common Crawl\'s index server', [200], 'the copy of one site\'s page']]);
            case 'dbip': return self::reach($pdo, 'https://db-ip.com/db/download/ip-to-country-lite', 'DB-IP: the free country file\'s page', [200, 301, 302]);
            case 'pixabay':
                if ($e('PIXABAY_KEY') === '') return $na('Not set up (PIXABAY_KEY).');
                $r = self::reach($pdo, 'https://pixabay.com/api/?key=' . rawurlencode($e('PIXABAY_KEY')) . '&q=canyon&per_page=3', 'Pixabay: one search with our key (free)');
                if ($r[0] === 'bad') $r[3][] = self::fixUrl('Pixabay: your API key', 'https://pixabay.com/api/docs/');
                return $r;
            case 'tailwind': return self::reach($pdo, 'https://cdn.tailwindcss.com/', 'Tailwind\'s CDN (what visitors\' browsers load)', [200, 301, 302], 'HEAD');
            case 'vimeo': return self::reach($pdo, 'https://player.vimeo.com/api/player.js', 'Vimeo\'s player (what visitors\' browsers load)', [200, 301, 302], 'HEAD');
            case 'onepassword': return $na('The site never calls 1Password: each card links to the login there.');
        }
        return $na('Nothing to test from the site.');
    }

    /** A sign-in to the mail server without sending: EHLO, AUTH LOGIN, QUIT. [ok, words]. */
    private static function smtp(PDO $pdo): array
    {
        require_once __DIR__ . '/StagingGate.php';
        $host = self::env('SMTP_HOST'); $port = (int) (self::env('SMTP_PORT') ?: 465);
        if (($w = StagingGate::hold('https://' . $host . '/', 'Pre-flight: the mail server\'s sign-in')) !== null) return [true, $w];
        $t = microtime(true);
        $s = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $no, $err, 10);
        if (!$s) return [false, 'Couldn\'t connect to ' . $host . ':' . $port . ' (' . $err . ').'];
        stream_set_timeout($s, 10);
        $read = function () use ($s): string { $o = ''; while (($l = fgets($s, 1024)) !== false) { $o .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $o; };
        $cmd = function (string $c) use ($s, $read): string { fwrite($s, $c . "\r\n"); return $read(); };
        $read(); $cmd('EHLO traversence.com');
        if ($port === 587) { $cmd('STARTTLS'); @stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $cmd('EHLO traversence.com'); }
        $cmd('AUTH LOGIN'); $cmd(base64_encode(self::env('SMTP_USERNAME') ?: self::env('SMTP_FROM_EMAIL'))); $a = $cmd(base64_encode(self::env('SMTP_PASSWORD')));
        $cmd('QUIT'); fclose($s);
        $ok = str_starts_with($a, '235');
        return [$ok, ($ok ? 'Signed in to ' . $host . ':' . $port . ' and left without sending' : 'The server refused the sign-in (' . trim(substr($a, 0, 80)) . ')') . ' (' . (int) round((microtime(true) - $t) * 1000) . ' ms).'];
    }

    private static function supabase(PDO $pdo): array
    {
        $url = rtrim(self::env('SUPABASE_URL'), '/'); $k = self::env('SUPABASE_SERVICE_KEY');
        if ($url === '' || $k === '') return ['bad', 'Supabase isn\'t connected: SUPABASE_URL and SUPABASE_SERVICE_KEY in .env.', 'The settings', [self::envFix('SUPABASE_URL and SUPABASE_SERVICE_KEY')]];
        $r = self::http($pdo, 'HEAD', $url . '/rest/v1/tv_vectors?select=ref', ['apikey: ' . $k, 'Authorization: Bearer ' . $k, 'Prefer: count=exact', 'Range: 0-0'], null, 'Supabase: the index\'s size');
        if ($r[0] === -1) return ['wait', $r[2], 'Supabase', []];
        if ($r[0] === 0 || $r[0] >= 500 || $r[0] === 540) return ['bad', 'Supabase didn\'t answer (' . ($r[0] ?: $r[2]) . '): the free project may be paused. Restore it; search keeps to the normal index meanwhile (0164 §5.2\'s note).', 'The index\'s row count (read only)', [self::fixUrl('Supabase: the project (Restore)', 'https://supabase.com/dashboard/projects')]];
        if ($r[0] === 404) return ['bad', 'Supabase is awake but the table tv_vectors isn\'t there: run the setup SQL from its card.', 'The index\'s row count (read only)', [self::fixTool('The setup SQL on Supabase\'s card', '/admin/config/register#svc-supabase'), self::fixUrl('Supabase: SQL editor', 'https://supabase.com/dashboard/projects')]];
        if ($r[0] >= 400) return ['bad', self::said($r, [200, 206])[1], 'The index\'s row count (read only)', [self::fixUrl('Supabase: API keys', 'https://supabase.com/dashboard/projects')]];
        $n = (int) preg_replace('#^.*/#', '', (string) ($r[4]['content-range'] ?? '0'));
        // locked down: without our key, the table refuses
        $anon = self::http($pdo, 'GET', $url . '/rest/v1/tv_vectors?select=ref&limit=1', [], null, 'Supabase: the index without our key');
        $locked = in_array($anon[0], [401, 403, 404], true) || $anon[0] === -1;
        $want = 0;
        try { $want = (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0")->fetchColumn(); } catch (Throwable $x) {}
        $since = SearchIndex::state($pdo, 'vectors:since');
        $behind = 0;
        try { $st = $pdo->prepare("SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0 AND updated_at > ?"); $st->execute([$since ?: '2000-01-01']); $behind = (int) $st->fetchColumn(); } catch (Throwable $x) {}
        $complete = $behind === 0;
        $taskOn = false;   // the vector index fills only once its task is on (Start the system): an empty index before then is expected
        try { require_once __DIR__ . '/AiClient.php'; require_once __DIR__ . '/Automation.php'; $taskOn = !empty(AiClient::feature($pdo, 'vector_index')['enabled']) && Automation::serviceOff($pdo, 'supabase') === null && Automation::masterOff($pdo) === null; } catch (Throwable $x) {}
        // catching up is measured, not guessed (routes, 2026-11-19; the session manager's D): each check keeps the backlog
        // it saw, so the line says how fast it shrinks and when it's done. Shrinking is ready; held only when its task is off
        // after Start, or nothing came off it for a day
        $now = time(); $tr = json_decode(SearchIndex::state($pdo, 'vectors:pf_track') ?: '[]', true) ?: [];
        if ($complete || empty($tr['b0']) || $behind > (int) ($tr['b'] ?? 0)) $tr = ['b0' => $behind, 'at0' => $now, 'b' => $behind, 'shrunk' => $now];
        elseif ($behind < (int) $tr['b']) { $tr['b'] = $behind; $tr['shrunk'] = $now; }
        try { SearchIndex::setState($pdo, 'vectors:pf_track', json_encode($tr)); } catch (Throwable $x) {}
        $hrs = max(1 / 60, ($now - (int) $tr['at0']) / 3600); $done = (int) $tr['b0'] - $behind; $rate = $done / $hrs;
        $stall = !$complete && $taskOn && $now - (int) $tr['shrunk'] > 86400;
        $masterOn = false; try { $masterOn = Automation::masterOff($pdo) === null; } catch (Throwable $x) {}
        $offAfterStart = !$complete && !$taskOn && $masterOn;
        $catch = $complete ? ', none waiting.' : '; ' . number_format($behind) . ' changed since its last upkeep and wait for it' . ($taskOn
            ? ($done > 0 ? ': catching up, ' . number_format($done) . ' done in ' . ($hrs < 1 ? (int) round($hrs * 60) . ' min' : number_format($hrs, 1) . ' h') . ' (about ' . number_format($rate) . ' an hour, done in about ' . self::eta($behind / max(1, $rate)) . ').'
                : ($stall ? ': nothing has come off it for ' . (int) floor(($now - (int) $tr['shrunk']) / 3600) . ' h, so its task isn\'t working through it.' : ': its task is on; the next check measures how fast it catches up.'))
            : ($offAfterStart ? ': its task is off, so nothing fills it. Switch its task on.' : ': expected, as the vector index task fills it once Start the system switches it on (confidential ones are never sent).'));
        $ok = $locked && !$stall && !$offAfterStart;
        $fix = !$locked ? [self::fixUrl('Supabase: table editor → tv_vectors → RLS', 'https://supabase.com/dashboard/projects')]
            : ($offAfterStart ? [self::fixTool('Switch its task on: the vector index', '/admin/config/ai#ai-vector_index')] : ($stall ? [self::fixTool('Its task: the vector index', '/admin/config/ai#ai-vector_index'), self::fixTool('The crawler\'s runs', '/admin/insights/crawler')] : []));
        return [$ok ? 'ok' : 'bad', 'Awake, ' . $r[3] . ' ms. The index holds ' . number_format($n) . ' vector(s) for ' . number_format($want) . ' public listings' . $catch . ($locked ? ' Without our key it refuses (locked down).' : ' It answers WITHOUT our key: turn on row level security for tv_vectors.'),
            'The index\'s row count, the listings changed since its last upkeep, and a call without our key (all read only)', $fix];
    }

    // ---- Railway and W0 ------------------------------------------------------------------------------------------------

    private static function railwayGql(PDO $pdo, string $q, array $vars): array
    {
        $r = self::http($pdo, 'POST', 'https://backboard.railway.com/graphql/v2', ['Authorization: Bearer ' . self::env('RAILWAY_API_TOKEN'), 'Content-Type: application/json'], ['query' => $q, 'variables' => $vars], 'Railway: a read');
        if ($r[0] === -1) return [false, $r[2], []];
        $j = json_decode($r[1], true) ?: [];
        if ($r[0] !== 200 || !empty($j['errors'])) return [false, 'Railway answered ' . $r[0] . (!empty($j['errors'][0]['message']) ? ': "' . mb_substr((string) $j['errors'][0]['message'], 0, 200) . '"' : '') . '.', []];
        return [true, '', (array) ($j['data'] ?? [])];
    }

    /** The project's services: name => [id, cron, restart]. */
    private static function railwayServices(PDO $pdo): array
    {
        if (isset(self::$cache['rw'])) return self::$cache['rw'];
        $g = self::railwayGql($pdo, 'query($p: String!) { project(id: $p) { name services { edges { node { id name serviceInstances { edges { node { cronSchedule restartPolicyType environmentId } } } } } } } }', ['p' => self::env('RAILWAY_PROJECT_ID')]);
        $out = ['ok' => $g[0], 'why' => $g[1], 'project' => (string) ($g[2]['project']['name'] ?? ''), 'svc' => []];
        foreach ($g[2]['project']['services']['edges'] ?? [] as $e) {
            $n = $e['node']; $i = $n['serviceInstances']['edges'][0]['node'] ?? [];
            $out['svc'][(string) $n['name']] = [(string) $n['id'], $i['cronSchedule'] ?? null, (string) ($i['restartPolicyType'] ?? '')];
        }
        return self::$cache['rw'] = $out;
    }

    private static function railwayApi(PDO $pdo): array
    {
        foreach (['RAILWAY_API_TOKEN', 'RAILWAY_PROJECT_ID', 'RAILWAY_SERVICE_ID'] as $n) if (self::env($n) === '') return ['bad', $n . ' isn\'t in .env.', 'The settings', [self::envFix('RAILWAY_API_TOKEN, RAILWAY_PROJECT_ID and RAILWAY_SERVICE_ID')]];
        $rw = self::railwayServices($pdo);
        if (!$rw['ok']) return ['bad', $rw['why'], 'The project\'s services (read only)', [self::fixUrl('Railway: account → Tokens', 'https://railway.com/account/tokens')]];
        $names = array_keys($rw['svc']);
        $crawler = array_search(self::env('RAILWAY_SERVICE_ID'), array_map(fn ($s) => $s[0], $rw['svc']), true);
        $w0 = isset($rw['svc']['w0-indexer']);
        $ok = $crawler !== false && $w0;
        return [$ok ? 'ok' : 'bad', 'Project ' . $rw['project'] . ': ' . implode(', ', $names) . '. ' . ($crawler !== false ? 'RAILWAY_SERVICE_ID is "' . $crawler . '" (the crawler). ' : 'No service has the id in RAILWAY_SERVICE_ID. ') . ($w0 ? 'w0-indexer is there.' : 'There\'s no service called w0-indexer.'),
            'The project\'s services (read only)', $ok ? [] : [self::fixUrl('Railway: the project', 'https://railway.com/dashboard')]];
    }

    private static function railwayPaused(PDO $pdo): array
    {
        require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/SystemStart.php';
        $st = (string) (SystemStart::state($pdo)['state'] ?? 'idle');
        if (in_array($st, ['running', 'stopped', 'done'], true)) return ['na', 'Start the system has been pressed (' . (SystemStart::LABELS[$st] ?? $st) . '), so the engine is meant to be on.', 'Start the system\'s state', []];
        $why = [];
        $master = Automation::master($pdo);
        if (!$master) $why[] = 'the master switch is ON';
        if (Automation::wfOff($pdo, 'W0') === null && $master === null) $why[] = 'W0 is on';
        $cron = '';
        if (self::env('RAILWAY_API_TOKEN') !== '') {
            $rw = self::railwayServices($pdo);
            foreach ($rw['svc'] as $name => [$id, $c]) if ($id === self::env('RAILWAY_SERVICE_ID')) { $cron = (string) $c; if ($cron !== '') $why[] = 'the crawler (' . $name . ') has a schedule on Railway (' . $cron . '), so it would run'; }
            if (!$rw['ok']) $why[] = 'Railway couldn\'t be read (' . $rw['why'] . ')';
        }
        require_once __DIR__ . '/crawler/Frontier.php';
        $words = 'The master switch is off' . ($master ? ' (' . Automation::line($master, 'paused') . ')' : '') . '; the crawler has no schedule on Railway; W0 Indexer ' . (Frontier::off($pdo) !== null ? 'is told to sleep at each check-in' : 'would read') . '.';
        return [$why ? 'bad' : 'ok', $why ? 'Before Start the system, ' . implode('; ', $why) . '.' : $words, 'The master switch, W0\'s answer at check-in, and the crawler\'s schedule on Railway (read only; nothing is changed here)',
            $why ? [self::fixTool('Pause everything (Automation)', '/admin/config/automation#start')] : []];
    }

    private static function w0Beat(PDO $pdo): array
    {
        require_once __DIR__ . '/crawler/Frontier.php';
        if (!Frontier::ready($pdo)) return ['bad', 'The frontier\'s database update hasn\'t run yet.', 'The frontier', [self::fixTool('Database updates', '/admin/config/dbupdates')]];
        $b = Frontier::lastBeat($pdo);
        if (empty($b['at'])) return ['bad', 'W0 Indexer has never checked in.', 'Its last check-in', [self::fixUrl('Railway: the w0-indexer service', 'https://railway.com/dashboard')]];
        $age = time() - (int) $b['at']; $s = Frontier::size($pdo);
        return [$age < 300 ? 'ok' : 'bad', 'Last check-in ' . self::ago((int) $b['at']) . ' (version ' . ($b['v'] ?? '?') . ', batch ' . (int) ($b['batch'] ?? 0) . ', gap ' . ($b['gap'] ?? '?') . ' s' . (!empty($b['mismatches']) ? ', ' . (int) $b['mismatches'] . ' mismatched receipts in a row' : '') . '). '
            . ($age < 300 ? 'It answers: ' . (Frontier::off($pdo) ?? 'on, reading') . ' ' : 'Over 5 minutes: is the service running? ')
            . 'The frontier: ' . number_format($s['rows']) . ' addresses, ' . number_format($s['sites']) . ' sites, ' . $s['mb'] . ' MB; ' . number_format($s['day']) . ' new today.',
            'Its check-ins (every minute while it sleeps) and the frontier\'s size', $age < 300 ? [] : [self::fixUrl('Railway: the w0-indexer service', 'https://railway.com/dashboard')]];
    }

    /** Asks the sleeping indexer for one full-size test batch at its next check-in (handed out once; api/crawl/index.php). */
    private static function w0Batch(PDO $pdo): array
    {
        require_once __DIR__ . '/crawler/Frontier.php'; require_once __DIR__ . '/Thresholds.php';
        $ask = json_decode(SearchIndex::state($pdo, 'w0:test_ask'), true) ?: [];
        $since = (int) ($ask['at'] ?? 0);
        $got = null;
        // the receipt's time is the database's own clock (NOW()), so the ask's time is compared in it too (FROM_UNIXTIME)
        if ($since) { try { $st = $pdo->prepare("SELECT declared, received, stored_n, ok, bytes, ms, at FROM " . Database::t('crawl_batches') . " WHERE kind = 'test' AND at >= FROM_UNIXTIME(?) ORDER BY id DESC LIMIT 1"); $st->execute([$since]); $got = $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $x) {} }
        if ($got) {
            $full = (int) Thresholds::get($pdo, 'indexer_found_batch');
            $gv = (string) (Frontier::lastBeat($pdo)['v'] ?? '');   // the version the indexer reported at its last check-in
            $ok = (int) $got['ok'] && (int) $got['received'] === (int) $got['declared'] && (int) $got['stored_n'] === (int) $got['received'] && (int) $got['declared'] >= $full;
            SearchIndex::setState($pdo, 'w0:test_ask', '');
            return [$ok ? 'ok' : 'bad', 'The indexer sent ' . (int) $got['declared'] . ' addresses in one batch (' . round((int) $got['bytes'] / 1024, 1) . ' KB); the site received ' . (int) $got['received'] . ' and checked ' . (int) $got['stored_n'] . ' in ' . (int) $got['ms'] . ' ms, storing none (a test). '
                . ($ok ? 'The receipt matches: the host, its firewall and the intake take a full batch.' : ((int) $got['declared'] < $full ? 'It was smaller than a full batch (' . $full . ')' . ($gv !== '' && version_compare($gv, '1.1.1', '<') ? ': Railway still runs indexer ' . $gv . ', which tested at the size it had dropped to after a slow answer; 1.1.1 always tests full size. Redeploy the w0-indexer service from main.' : ': the indexer (' . ($gv !== '' ? 'version ' . $gv : 'its version not reported') . ') sent the size it had dropped to after a slow answer. The system asks for a new one within half an hour.') : 'The receipt doesn\'t match: something on the way cut it (Bluehost\'s ModSecurity or bot check).')),
                'One full-size batch from the w0-indexer service on Railway to the intake (kind test: checked and answered, never stored); W0 stays paused', $ok ? [] : ($gv !== '' && version_compare($gv, '1.1.1', '<') ? [self::fixUrl('Railway: the w0-indexer service → Deployments', 'https://railway.com/dashboard')] : [self::fixTool('Thresholds: W0\'s batch size', '/admin/config/thresholds')])];
        }
        if ($since && time() - $since < self::ASK_SECS) return ['wait', 'Asked ' . self::ago($since) . '; the indexer sends it at its next check-in (about a minute). Test again shortly.', 'One full-size batch from the w0-indexer service (marked test, never stored)', []];
        $v = (string) (Frontier::lastBeat($pdo)['v'] ?? '');
        if ($v !== '' && version_compare($v, '1.1.0', '<')) return ['bad', 'The indexer on Railway is version ' . $v . ': it sends the test batch from 1.1.0, which Railway deploys from main.', 'Its version at check-in', [self::fixUrl('Railway: the w0-indexer service → Deployments', 'https://railway.com/dashboard')]];
        SearchIndex::setState($pdo, 'w0:test_ask', json_encode(['at' => time(), 'handed' => 0]));
        return ['wait', 'Asked just now: the sleeping indexer sends one full-size test batch at its next check-in (about a minute), without W0 starting. Test again shortly.', 'One full-size batch from the w0-indexer service (marked test, never stored)', []];
    }

    /** For api/crawl/index.php's check-in: true once when the pre-flight asked for a test batch (then marked handed out). */
    public static function takeTestAsk(PDO $pdo): bool
    {
        try {
            $ask = json_decode(SearchIndex::state($pdo, 'w0:test_ask'), true);
            if (!is_array($ask) || !empty($ask['handed']) || time() - (int) ($ask['at'] ?? 0) > self::ASK_SECS) return false;
            $ask['handed'] = time();
            SearchIndex::setState($pdo, 'w0:test_ask', json_encode($ask));
            return true;
        } catch (Throwable $x) { return false; }
    }

    // ---- query timings -----------------------------------------------------------------------------------------------

    private static function timing(PDO $pdo, string $k): array
    {
        require_once __DIR__ . '/Thresholds.php';
        [$what, $gate] = self::TIMINGS[$k];
        $limit = (int) Thresholds::get($pdo, $gate);
        $sql = ''; $args = [];
        switch ($k) {
            case 'radius':
                [$lat, $lon, $mi] = [36.7278, -110.2546, 50];
                $sql = "SELECT COUNT(*) FROM entities e WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.latitude BETWEEN ? AND ? AND e.longitude BETWEEN ? AND ?
                          AND (3958.8 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(e.latitude - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(e.latitude)) * POWER(SIN(RADIANS(e.longitude - ?) / 2), 2)))) <= ?";
                $d = $mi / 69.0; $args = [$lat - $d, $lat + $d, $lon - $d * 1.3, $lon + $d * 1.3, $lat, $lat, $lon, $mi];
                break;
            case 'rollup':
                $sql = "SELECT mc.geo_hub_id, COUNT(*) FROM entities e JOIN micro_clusters mc ON mc.id = e.micro_cluster_id WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 GROUP BY mc.geo_hub_id";
                break;
            case 'graph':
                try { $pdo->query('SELECT 1 FROM entity_edges LIMIT 0'); $edges = true; } catch (Throwable $x) { $edges = false; }
                if ($edges) { $start = (int) $pdo->query('SELECT from_id FROM entity_edges GROUP BY from_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn();
                    $sql = 'SELECT COUNT(DISTINCT e2.to_id) FROM entity_edges e1 JOIN entity_edges e2 ON e2.from_id = e1.to_id WHERE e1.from_id = ?'; $args = [$start]; $what .= ' (entity_edges)'; }
                else { try { $start = (int) $pdo->query('SELECT a_id FROM topic_relations GROUP BY a_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn(); } catch (Throwable $x) { return ['na', 'No graph to walk yet (neither entity_edges nor topic_relations).', $what, []]; }
                    $sql = 'SELECT COUNT(DISTINCT r2.b_id) FROM topic_relations r1 JOIN topic_relations r2 ON r2.a_id = r1.b_id WHERE r1.a_id = ?'; $args = [$start]; $what .= ' (the Topic index, until entity_edges exists)'; }
                break;
            case 'index':
                require_once __DIR__ . '/crawler/Frontier.php';
                if (Frontier::ready($pdo)) { $sql = 'SELECT COUNT(*) FROM ' . Database::t('crawl_frontier') . ' WHERE url_hash = ?'; $args = [sha1('https://traversence.com/preflight/' . mt_rand())]; }
                else { $sql = "SELECT COUNT(*) FROM entities WHERE entity_type = 'merchant' AND name = ?"; $args = ['Pre-flight ' . mt_rand()]; }
                break;
        }
        try { $pdo->exec('SET SESSION max_execution_time = 20000'); } catch (Throwable $x) {}
        $best = PHP_INT_MAX; $n = '';
        try {
            for ($i = 0; $i < 3; $i++) { $t = microtime(true); $st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll(PDO::FETCH_NUM); $best = min($best, (int) round((microtime(true) - $t) * 1000)); }
            $n = count($rows) === 1 ? number_format((int) $rows[0][count($rows[0]) - 1]) . ' found' : count($rows) . ' rows';
        } catch (Throwable $x) { return ['bad', 'The query failed: ' . mb_substr($x->getMessage(), 0, 200), $what, []]; }
        finally { try { $pdo->exec('SET SESSION max_execution_time = 0'); } catch (Throwable $x) {} }
        $was = self::result($pdo, 'ms:' . $k)['extra']['ms'] ?? [];
        $hist = array_slice(array_merge((array) $was, [$best]), -10);
        $trend = count($hist) > 2 ? ' Its last runs: ' . implode(', ', array_slice($hist, -5)) . ' ms.' : '';
        $r = [$best <= $limit ? 'ok' : 'bad', 'The best of three: ' . $best . ' ms (' . $n . '), against a limit of ' . $limit . ' ms.' . $trend . ($best > $limit ? ' It has slowed as the data grew: an index, or the query, needs a look.' : ''), $what . ': read only, timed on the live database', $best > $limit ? [self::fixTool('Thresholds: its limit', '/admin/config/thresholds')] : []];
        $r[4] = ['ms' => $hist];
        return $r;
    }

    // ---- the workflows, phases and new tasks -----------------------------------------------------------------------------

    /** The worst of some results: bad over wait over ok; na when all are na or there are none. */
    private static function worst(array $states): string
    {
        foreach (['bad', 'new', 'wait', 'warn', 'ok'] as $s) if (in_array($s, $states, true)) return $s === 'new' ? 'wait' : $s;
        return 'na';
    }

    // ---- needs (the source map, decisions/0163's note of 2026-11-17) ----------------------------------------------------------

    /** One of our own copies: [works, words]. It exists, has rows, and (with a limit) isn't stale. Read only. */
    private static function ours(PDO $pdo, string $table, int $staleDays): array
    {
        if (isset(self::$cache['ours:' . $table . ':' . $staleDays])) return self::$cache['ours:' . $table . ':' . $staleDays];
        $T = Database::t($table);
        try { $any = $pdo->query('SELECT 1 FROM ' . $T . ' LIMIT 1')->fetchColumn(); }
        catch (Throwable $x) { return self::$cache['ours:' . $table . ':' . $staleDays] = [false, $table . ' isn\'t there yet']; }
        if ($any === false) return self::$cache['ours:' . $table . ':' . $staleDays] = [false, $table . ' is empty (nothing loaded yet)'];
        $rows = 0; $col = '';
        try {
            $rows = (int) $pdo->query('SELECT MAX(TABLE_ROWS) FROM information_schema.TABLES WHERE TABLE_NAME = ' . $pdo->quote($table))->fetchColumn();
            $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = " . $pdo->quote($table) . " AND DATA_TYPE IN ('datetime', 'timestamp', 'date')")->fetchAll(PDO::FETCH_COLUMN);
            foreach (['loaded_at', 'updated_at', 'found_at', 'fetched_at', 'seen_at', 'at', 'created_at', 'first_at'] as $c) if (in_array($c, $cols, true)) { $col = $c; break; }
        } catch (Throwable $x) {}
        $newest = 0;
        if ($col !== '') { try { $newest = (int) strtotime((string) $pdo->query('SELECT MAX(`' . $col . '`) FROM ' . $T)->fetchColumn()); } catch (Throwable $x) {} }
        $age = $newest ? (int) floor((time() - $newest) / 86400) : null;
        $stale = $staleDays > 0 && $age !== null && $age > $staleDays;
        $rows = max(1, $rows); $words = ($rows > 1 ? 'about ' . number_format($rows) . ' rows' : 'it has rows') . ($age !== null ? ', newest ' . ($age === 0 ? 'today' : $age . ' day' . ($age === 1 ? '' : 's') . ' old') : '') . ($stale ? ' (stale: over ' . $staleDays . ' days)' : '');
        return self::$cache['ours:' . $table . ':' . $staleDays] = [!$stale, $words];
    }

    /** A need: its sources in order; the first that works decides how it stands. */
    private static function need(PDO $pdo, string $k): array
    {
        require_once __DIR__ . '/OutsideServices.php';
        [, , , $sources, $fallback, $soft, $planned] = self::NEEDS[$k];
        $res = self::results($pdo); $first = null; $said = []; $fix = [];
        foreach ($sources as $i => $src) {
            if ($src[0] === 'ours') { [$ok, $w] = self::ours($pdo, $src[1], (int) $src[3]); $name = 'Ours (' . $src[2] . ')'; }
            elseif ($src[0] === 'paid') { require_once __DIR__ . '/PaidOverride.php'; $po = PaidOverride::NEEDS[$src[1]];
                $ps = PaidOverride::state($pdo, $src[1]); $sv = (string) ($res['svc:' . $po[1]]['s'] ?? 'new');
                $ok = $ps['on'] && $ps['left'] > 0 && $sv === 'ok'; $name = '$ ' . $po[2] . ' (paid backup)';
                $w = $ps['words'] . ($ps['on'] && $sv !== 'ok' ? '; its own line isn\'t ready' : ''); }
            elseif ($src[0] === 'copy') { require_once __DIR__ . '/OpenPlaces.php'; [$ok, $w] = OpenPlaces::ourCopy($pdo); $name = 'Ours (' . $src[2] . ')'; $w = preg_replace('/^our OpenStreetMap copy from Geofabrik, /', '', $w); }
            elseif ($src[0] === 'svc') { $s = (string) ($res['svc:' . $src[1]]['s'] ?? 'new'); $ok = $s === 'ok'; $name = OutsideServices::COMPANIES[$src[1]][0] ?? $src[1];
                $w = $ok ? 'works' : ($s === 'na' ? 'not set up' : ($s === 'new' ? 'not tested yet' : ($s === 'wait' ? 'waiting' : 'not ready')));
                if (!$ok && $s !== 'na') $fix[] = self::fixTool('The ' . $name . ' line', '#pf-svc-' . $src[1]); }
            else { $s = (string) ($res[$src[1]]['s'] ?? 'new'); $ok = $s === 'ok'; $name = (string) $src[2]; $w = $ok ? 'works' : ($s === 'new' ? 'not tested yet' : 'not ready'); if (!$ok) $fix[] = self::fixTool('Its line', '#pf-' . str_replace(':', '-', $src[1])); }
            $said[] = ($i + 1) . '. ' . $name . ': ' . $w;
            if ($ok && $first === null) $first = $i;
        }
        $state = $first === 0 ? 'ok' : ($first !== null ? 'warn' : ($soft ? 'warn' : 'bad'));
        $head = $first === 0 ? 'Answered by its first source.' : ($first !== null ? 'Running on a backup (source ' . ($first + 1) . ').' : 'No source works, so ' . $fallback . '.');
        if (count($sources) === 1) $head .= ' No backup yet' . ($planned !== '' ? ' (planned: ' . $planned . ')' : '') . ($first !== null ? '; if it fails, ' . $fallback : '') . '.';
        return [$state, $head . ' ' . implode('; ', $said) . '.', 'Its sources in order, ours first (decisions/0163, the source map): our copies checked for rows and freshness, each outside source by its own line', $state === 'ok' ? [] : array_slice($fix, 0, 4)];
    }

    private static function workflow(PDO $pdo, string $wf): array
    {
        require_once __DIR__ . '/Operations.php'; require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/OutsideServices.php'; require_once __DIR__ . '/Scheduler.php';
        $res = self::results($pdo); $parts = []; $states = []; $fix = []; $pst = [];
        // each part's own state beside its words (2026-11-18: "what holds it" was found by words, and "has no red line" read as one)
        $mark = function () use (&$parts, &$states, &$pst) { $pst[count($parts) - 1] = end($states); };
        // its needs (the source map): each answered by its first source, a backup (amber), or nothing (red)
        $nd = [];
        foreach (self::NEEDS as $k => $n) if (in_array($wf, $n[2], true)) $nd[$k] = (string) ($res['nd:' . $k]['s'] ?? 'new');
        if ($nd) { $states[] = self::worst(array_values($nd));
            $parts[] = 'Needs: ' . implode(', ', array_map(fn ($k, $s) => preg_replace('/ \(W[^)]*\)$/', '', self::NEEDS[$k][1]) . ' ' . (['ok' => '✓', 'warn' => '(on a backup)', 'bad' => '✗', 'na' => '—', 'wait' => '…', 'new' => '(not run)'][$s] ?? $s), array_keys($nd), $nd)) . '.';
            $mark(); foreach ($nd as $k => $s) if ($s === 'bad') $fix[] = self::fixTool('The "' . self::NEEDS[$k][1] . '" line', '#pf-nd-' . $k); }
        // its jobs on the scheduler
        $jobs = array_keys(array_filter(Automation::JOBS, fn ($w) => in_array($wf, $w, true)));
        if ($jobs) { $lines = self::cron(); $miss = $lines === null ? [] : array_values(array_filter($jobs, fn ($j) => !Scheduler::lineFor($lines, $j) && in_array($j, Scheduler::AUTO, true)));   // a job shipped off gets its line from Start the system (SystemStart::ensureJob)
            $later = $lines === null ? [] : array_values(array_filter($jobs, fn ($j) => !Scheduler::lineFor($lines, $j) && !in_array($j, Scheduler::AUTO, true)));
            $states[] = $lines === null ? 'wait' : ($miss ? 'bad' : 'ok');
            $parts[] = 'Scheduled jobs: ' . implode(', ', array_map(fn ($j) => Scheduler::JOBS[$j][0] ?? $j, $jobs)) . ($lines === null ? ' (cPanel unread).' : ($miss ? '; no cron line: ' . implode(', ', $miss) . '.' : ($later ? '; ' . implode(', ', $later) . ' get their cron line from Start the system (shipped off).' : ', each with its cron line.')));
            $mark(); if ($miss) $fix[] = self::fixDo('sched', 'Set up the jobs now'); }
        // its crawler tasks registered
        try { require_once __DIR__ . '/crawler/CrawlService.php';
            if (CrawlService::ready($pdo)) { $t = array_filter(CrawlService::tasks($pdo), fn ($r) => (string) $r['wf'] === $wf);
                if ($t) $parts[] = 'Crawler tasks registered: ' . implode(', ', array_map(fn ($r) => (string) $r['name'] . ((int) $r['enabled'] ? '' : ' (off)'), $t)) . '.'; } } catch (Throwable $x) {}
        // its gate (the switch-on check's lines for it and its services: keys, holds, budgets, caps recorded)
        try { $red = Operations::blocking($pdo, $wf);
            // W14 held only by its own hub lines (a hub's licence sources' terms not read yet) waits, amber: it doesn't count
            // against launch (Jason, 2026-11-17: unread terms hold only W14), and a phase it's in isn't red for it
            $terms = $red && !array_filter($red, fn ($l) => $l['scope'] !== $wf || !str_starts_with((string) $l['line'], 'Opening a geo-hub'));
            $states[] = $red ? ($terms ? 'warn' : 'bad') : 'ok';
            if ($terms) $parts[] = $wf . ' waits for its terms: ' . implode('; ', array_map(fn ($l) => $l['line'] . ' (' . rtrim((string) $l['why'], '.') . ')', $red)) . '. Read them in Pulse → Hubs; nothing else waits on it.';
            else $parts[] = $red ? 'Its gate: ' . count($red) . ' red line(s) in the switch-on check: ' . implode('; ', array_map(fn ($l) => '"' . $l['line'] . '" (' . rtrim((string) $l['why'], '.') . ')', $red)) . '.' : 'Its gate: the switch-on check has no red line for it (keys, holds, budget and caps set).';
            $mark(); if ($red && !empty($red[0]['fix'])) $fix[] = self::fixTool((string) $red[0]['fix'][0], (string) $red[0]['fix'][1]); } catch (Throwable $x) { $parts[] = 'Its gate: couldn\'t read the switch-on check (' . $x->getMessage() . ').'; $states[] = 'bad'; $mark(); }
        // what holds only part of it (Operations::gateFor; decisions/0175 §3, a trip stops only what it holds; 0163's source
        // map): a source down that a backup covers holds nothing; one that only some tasks call holds just those tasks.
        // Amber, never red: the rest of the workflow runs.
        try { $g = Operations::gateFor($pdo, $wf);
            if ($g['tasks']) { $names = []; foreach ($g['tasks'] as $tk => $ls) $names[] = $tk . ' (' . implode('; ', array_unique(array_map(fn ($l) => (string) $l['line'], $ls))) . ')';
                $states[] = 'warn'; $parts[] = 'Waiting while their source is down: ' . implode(', ', $names) . '. The rest of ' . $wf . ' runs; they start again when the line is green.'; $mark(); }
            // never dropped from sight (the session manager's review, 2026-11-21): a red line it doesn't wait on still shows
            if ($g['apart']) { $states[] = 'warn'; $parts[] = 'Down, and ' . $wf . ' doesn\'t wait on it: ' . implode('; ', array_map(fn ($l) => '"' . $l['line'] . '" (' . rtrim((string) $l['why'], '.') . ')', $g['apart'])) . '. None of ' . $wf . '\'s needs names it and none of its tasks calls it, so it holds nothing here; its own line is on the Service register.'; $mark(); }
            if ($g['covered']) { $states[] = 'warn'; $parts[] = 'A source is down and covered: ' . implode('; ', array_map(fn ($l) => '"' . $l['line'] . '" (' . rtrim((string) $l['why'], '.') . ')', $g['covered'])) . '. Its needs still have a source that works, so nothing waits.'; $mark(); }
        } catch (Throwable $x) {}
        // a dry run where there's a preview
        $dry = self::dryRun($pdo, $wf, $res);
        if ($dry) { $states[] = $dry[0]; $parts[] = 'Dry run: ' . $dry[1]; $mark(); }
        else $parts[] = 'Dry run: none (it has no preview mode; its tasks are tested by their parts above).';
        // its switch, as information (paused until Start the system is expected)
        $off = Automation::wfOff($pdo, $wf);
        $parts[] = $off !== null ? 'Now: ' . $off : 'Now: switched on.';
        $s = self::worst($states);
        // what holds it first, so the reason is the first thing read (Jason's report, 2026-11-17: W1's was past the cut)
        // the parts, each with its state, for the board (decisions/0171: a check shown part by part, the red one plain)
        $rows = []; foreach ($parts as $i => $p) $rows[] = [$p, $pst[$i] ?? null];
        $holds = array_values(array_filter($rows, fn ($r) => $r[1] === 'bad'));
        if ($holds && $s === 'bad') { $hs = array_column($holds, 0); $parts = array_merge(['What holds it: ' . implode(' ', $hs)], array_values(array_diff($parts, $hs))); }
        return [$s, implode(' ', $parts), 'Everything under it: its services\' pre-flight lines, its jobs in cPanel, its crawler tasks, its gate in the switch-on check, a dry run where it has one', $fix, ['parts' => $rows]];
    }

    /** One small step without writing: [state, words] or null. */
    private static function dryRun(PDO $pdo, string $wf, array $res): ?array
    {
        if ($wf === 'W0') { $b = $res['rw:batch'] ?? null; return [$b['s'] ?? 'wait', $b ? 'the test batch: ' . ($b['said'] ?? '') : 'the test batch hasn\'t run yet (its line under Railway).']; }
        if ($wf === 'W1') {
            try { require_once __DIR__ . '/DataCleanup.php';
                if (!DataCleanup::ready($pdo)) return ['wait', 'Data cleanup\'s database update hasn\'t run.'];
                $p = DataCleanup::preview($pdo, 'junk_name'); $r = $p['rules']['junk_name'] ?? ['act' => 0, 'check' => 0];
                $err = array_filter(array_column($p['stores'], 'error'));
                return [$err ? 'bad' : 'ok', 'Data cleanup\'s junk-name rule previewed (nothing changed): ' . (int) $r['act'] . ' it would act on, ' . (int) $r['check'] . ' for a person.' . ($err ? ' ' . implode('; ', $err) : '')];
            } catch (Throwable $x) { return ['bad', 'Data cleanup\'s preview failed: ' . $x->getMessage()]; }
        }
        return null;
    }

    private static function phase(PDO $pdo, int $n): array
    {
        $res = self::results($pdo); $w = self::PHASES[$n][1]; $st = [];
        foreach ($w as $wf) $st[$wf] = $res['wf:' . $wf]['s'] ?? 'new';
        $bad = array_keys(array_filter($st, fn ($s) => $s === 'bad'));
        return [self::worst(array_values($st)), 'Its workflows: ' . implode(', ', array_map(fn ($k, $s) => $k . ' ' . (['ok' => '✓', 'warn' => '(waits for its terms)', 'bad' => '✗', 'na' => '—', 'wait' => '…', 'new' => '(not run)'][$s] ?? $s), array_keys($st), $st)) . '.' . ($bad ? ' A red workflow holds only itself and what\'s under it (0163 §10).' : ''),
            'Ready when each of its workflows is (0164 §7)', array_map(fn ($k) => self::fixTool('The ' . $k . ' line', '#pf-wf-' . $k), $bad)];
    }

    private static function task(PDO $pdo, string $t): array
    {
        require_once __DIR__ . '/SystemStart.php'; require_once __DIR__ . '/Scheduler.php'; require_once __DIR__ . '/OutsideServices.php';
        [$name, $wf, $svcs, $jobs, $ctasks] = SystemStart::TASKS[$t];
        if ($t === 'W14') return self::workflow($pdo, 'W14');
        $res = self::results($pdo); $states = []; $parts = []; $fix = [];
        foreach ($svcs as $k) { $s = $res['svc:' . $k]['s'] ?? 'new'; $states[] = $s; $parts[] = (OutsideServices::COMPANIES[$k][0] ?? $k) . ': ' . (self::MARKS[$s] ?? $s) . '.'; if ($s === 'bad') $fix[] = self::fixTool('The pre-flight\'s ' . (OutsideServices::COMPANIES[$k][0] ?? $k) . ' line', '#pf-svc-' . $k); }
        if ($jobs) { $lines = self::cron(); foreach ($jobs as $j) { $has = $lines !== null && Scheduler::lineFor($lines, $j); $auto = in_array($j, Scheduler::AUTO, true); $states[] = $lines === null ? 'wait' : ($has || !$auto ? 'ok' : 'bad'); $parts[] = (Scheduler::JOBS[$j][0] ?? $j) . ': ' . ($lines === null ? 'cPanel unread' : ($has ? 'its cron line is there' : ($auto ? 'no cron line' : 'its cron line comes from Start the system (shipped off)'))) . '.'; } }
        if ($ctasks) { try { require_once __DIR__ . '/crawler/CrawlService.php'; $have = CrawlService::ready($pdo) ? CrawlService::tasks($pdo) : [];
            foreach ($ctasks as $c) { $byStart = !isset($have[$c]) && $t === 'recheck';   // registered by Start the system at its turn (ListingIdentity::ensureTask)
                $states[] = isset($have[$c]) || $byStart ? 'ok' : 'bad'; $parts[] = 'Crawler task ' . $c . ': ' . (isset($have[$c]) ? 'registered' . ((int) $have[$c]['enabled'] ? ', on' : ', off until Start the system') : ($byStart ? 'registered by Start the system at its turn' : 'not registered')) . '.'; } } catch (Throwable $x) {} }
        if ($t === 'recheck') { try { require_once __DIR__ . '/Operations.php'; $red = Operations::blocking($pdo, 'W2'); if ($red) { $states[] = 'bad'; $parts[] = 'W2\'s gate: "' . $red[0]['line'] . '".'; } } catch (Throwable $x) {} }
        $wfRes = $wf !== '' ? ($res['wf:' . $wf]['s'] ?? 'new') : 'ok';
        $parts[] = 'Its workflow ' . $wf . ': ' . (self::MARKS[$wfRes] ?? $wfRes) . ' (it comes on after its workflow).';
        return [self::worst($states ?: ['ok']), implode(' ', $parts), 'Its services\' lines, its jobs in cPanel and its crawler task registered', $fix];
    }

    // ---- fixes a person presses here -----------------------------------------------------------------------------------

    public static function fix(PDO $pdo, string $act, int $uid): string
    {
        [$ok, $why] = self::can();
        if (!$ok) throw new RuntimeException($why . '.');
        if ($act === 'sched') { require_once __DIR__ . '/Scheduler.php'; $r = Scheduler::sync($pdo, true); return 'Scheduled tasks set up through cPanel' . ($r ? ': ' . implode(', ', array_map(fn ($x) => is_string($x) ? $x : json_encode($x), array_slice((array) $r, 0, 6))) : '') . '.'; }
        if ($act === 'dbup') { require_once __DIR__ . '/DbUpdates.php'; $r = DbUpdates::autoRun($pdo, false, 'preflight'); return count($r['ran'] ?? []) ? count($r['ran']) . ' database update(s) run.' : 'Nothing was waiting to run' . (!empty($r['failed']) ? '; one failed: see Database updates.' : '.'); }
        throw new InvalidArgumentException('Not a fix the pre-flight makes.');
    }

    // ---- the nightly run and .env changes (app/scripts/preflight_tick.php) ---------------------------------------------------

    /** Runs everything when the night comes (2 am Arizona) or .env changed since the last look. Returns why it ran, or ''. */
    public static function tick(PDO $pdo): string
    {
        // a full run cut off (the web server ended its request) is marked Interrupted (live() does it) and waits for a person's
        // Continue or Cancel (the page, Pulse); while one is going or waiting, nothing else starts
        $l = self::live($pdo);
        if (in_array($l['state'] ?? '', ['running', 'interrupted'], true)) return '';
        $p = Database::envPath(); clearstatcache(true, $p);
        $sig = is_file($p) ? filemtime($p) . ':' . filesize($p) : '';
        $was = json_decode(SearchIndex::state($pdo, 'pf:tick'), true) ?: [];
        $night = (new DateTime('now', new DateTimeZone('America/Phoenix')))->format('Y-m-d');
        $hour = (int) (new DateTime('now', new DateTimeZone('America/Phoenix')))->format('G');
        $why = '';
        if (($was['env'] ?? null) !== null && $sig !== $was['env']) $why = 'a .env change';
        elseif ($hour >= self::NIGHT_HOUR && ($was['night'] ?? '') !== $night) $why = 'the nightly run';
        SearchIndex::setState($pdo, 'pf:tick', json_encode(['env' => $sig, 'night' => $why === 'the nightly run' ? $night : (string) ($was['night'] ?? '')]));
        if ($why === '') return '';
        if ($why === 'a .env change') sleep(5);   // a person may be mid-edit: let the file settle
        self::runAll($pdo, 'Pre-flight (' . $why . ')');
        return $why;
    }

    // ---- Pulse: one case, "Pre-flight", a step per red line ---------------------------------------------------------------

    public static function pulseItems(PDO $pdo): array
    {
        $sum = self::summary($pdo);
        $l = self::live($pdo);
        if (($l['state'] ?? '') === 'interrupted')   // a run that stopped waits for a person (Jason, 2026-11-17): its case says so first
            return [['ops:preflight', 'alert', 'Pre-flight: a run stopped at ' . (int) $l['i'] . ' of ' . (int) $l['total'], (string) $l['note'] . ' Continue it from there, or cancel it.' . ($sum['bad_ids'] ? ' ' . count($sum['bad_ids']) . ' line(s) not ready so far.' : ''), date('Y-m-d H:i:s', (int) ($l['beat'] ?? time())), '/admin/config/preflight']];
        if (!$sum['bad_ids']) return [];
        $n = count($sum['bad_ids']); $req = count($sum['req_bad']);
        $first = array_slice(array_values($sum['req_bad'] + $sum['bad_ids']), 0, 3);   // what launch needs first
        return [['ops:preflight', $req ? 'alert' : 'update', 'Pre-flight: ' . $n . ' not ready', ($req ? $req . ' needed for launch. ' : 'None needed for launch. ') . implode('; ', $first) . ($n > 3 ? '; and more' : '') . '.', date('Y-m-d H:i:s', $sum['at'] ?: time()), '/admin/config/preflight']];
    }

    public static function pulseSteps(PDO $pdo, callable $s): array
    {
        $res = self::results($pdo); $lines = self::lines($pdo); $out = [];
        $l = self::live($pdo);
        if (($l['state'] ?? '') === 'interrupted') {   // the run that stopped: Continue and Cancel as buttons (Pulse rule)
            $out[] = $s('Continue the run from ' . (int) $l['i'] . ' of ' . (int) $l['total'], (string) $l['note'] . ' It runs the rest on the server; the pre-flight page shows it (https://traversence.com/admin/config/preflight).', ['do', 'ops_pf_continue', 'Continue']);
            $out[] = $s('Or cancel it', 'It\'s kept in the run log as cancelled where it reached, with its report so far.', ['do', 'ops_pf_cancel', 'Cancel the run']);
        }
        foreach ($lines as $id => $l) {
            $r = $res[$id] ?? null;
            if (($r['s'] ?? '') !== 'bad') continue;
            $help = (string) $r['said'];
            $act = ['do', 'ops_pf_run:' . $id, 'Test again'];
            foreach ((array) $r['fix'] as $f) {
                if ($f[0] === 'do') { $act = ['do', 'ops_pf_fix:' . $f[1], (string) $f[2]]; }
                elseif ($f[0] === 'url') $help .= ' ' . $f[1] . ': ' . $f[2];
            }
            $ext = in_array($l[3], ['railway', 'quickbooks', 'aws', 'cloudflare', 'anthropic', 'tavily', 'google', 'bluehost', 'github', 'stripe', 'namecom', 'square'], true) ? $l[3] : '';
            $out[] = $s(($l[2] ? 'Needed for launch: ' : '') . $l[1], $help . ' (https://traversence.com/admin/config/preflight#pf-' . str_replace(':', '-', $id) . ')', $act, false, $ext);
        }
        $out[] = $s('Run the whole pre-flight again', 'Every line, in order; a minute or two.', ['do', 'ops_pf_all', 'Run it all']);
        return $out;
    }

    public static function pulseDo(PDO $pdo, string $what, int $uid, string $who): string
    {
        [$ok, $why] = self::can();
        if (!$ok) throw new RuntimeException($why . '.');
        if ($what === 'ops_pf_continue') { $l = self::startRun($pdo, $who, 'continue'); if (($l['state'] ?? '') !== 'running') return 'There\'s no stopped run to continue.'; $l = self::work($pdo, 0); return ($l['state'] ?? '') === 'done' ? 'The run finished: see the pre-flight page and its run log.' : 'The run continues: the pre-flight page shows how far it is.'; }
        if ($what === 'ops_pf_cancel') { $l = self::cancelRun($pdo, $who); return ($l['state'] ?? '') === 'cancelled' ? (string) $l['note'] . ' It\'s in the run log with its report so far.' : 'There\'s no stopped run to cancel.'; }
        if ($what === 'ops_pf_all') { $n = self::runAll($pdo, $who); if (!empty($n['interrupted'])) return 'A run stopped part way and waits for Continue or Cancel (the steps above, or the pre-flight page).'; if (!empty($n['already'])) return 'A run is going: the pre-flight page shows how far it is.'; return 'The pre-flight ran: ' . $n['ok'] . ' ready, ' . $n['bad'] . ' not ready, ' . $n['wait'] . ' waiting.'; }
        if (str_starts_with($what, 'ops_pf_run:')) { $r = self::run($pdo, substr($what, 11), $who); return (self::MARKS[$r['s']] ?? $r['s']) . ': ' . $r['said']; }
        if (str_starts_with($what, 'ops_pf_fix:')) return self::fix($pdo, substr($what, 11), $uid);
        throw new RuntimeException('Unknown step.');
    }

    // ---- the plain-text report (to read, or send to Claude for fixes) -----------------------------------------------------

    /** Every line's latest result as plain text: the counts and the run's times, what's not ready (launch first), the slow
     *  ones, then every line. Answers were already cleaned of secrets when stored. Times in UTC, labelled. */
    // ---- Check before switching on, as pre-flight lines (Jason, 2026-11-17) ----------------------------------------------

    /** A switch-on line's id: its scope and words, so the same line keeps its id (and its history) from run to run. At most
     *  40 characters (preflight_results.id): a readable start and a short hash of the whole. */
    private static function soId(array $l): string
    {
        $k = (string) $l['scope'] . ' ' . (string) $l['line'];
        return 'so:' . rtrim(substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($k)), '-'), 0, 24), '-') . '-' . substr(sha1($k), 0, 8);
    }

    /** The switch-on check, keyed by line id: read fresh at most every 20 seconds within one request (a run reads it once
     *  for its lines), its list of lines kept so the page knows them without running it. */
    public static function soCheck(PDO $pdo, bool $fresh = false): array
    {
        static $at = 0, $by = [];
        if (!$fresh && $by && time() - $at < 20) return $by;
        require_once __DIR__ . '/Operations.php';
        return self::soKeep($pdo, Operations::switchOnCheck($pdo), $at, $by);
    }

    private static function soKeep(PDO $pdo, array $chk, int &$at, array &$by): array
    {
        $by = [];
        foreach ($chk as $l) { $id = self::soId($l); $k = $id; $n = 2; while (isset($by[$k])) $k = substr($id, 0, 37) . '-' . $n++; $by[$k] = $l; }
        $at = time();
        $list = array_map(fn ($l) => [(string) $l['line'], (string) $l['scope']], $by);
        try { if (SearchIndex::state($pdo, 'pf:so') !== ($j = json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) SearchIndex::setState($pdo, 'pf:so', $j); } catch (Throwable $x) { error_log('pf:so: ' . $x->getMessage()); }
        return $by;
    }

    /** The lines as last seen: [id => [words, scope]] (read once, the first time, when nothing is kept yet). */
    private static function soList(PDO $pdo): array
    {
        $j = json_decode((string) (function () use ($pdo) { try { return SearchIndex::state($pdo, 'pf:so'); } catch (Throwable $x) { return ''; } })(), true);
        if (is_array($j) && $j) return $j;
        try { return array_map(fn ($l) => [(string) $l['line'], (string) $l['scope']], self::soCheck($pdo)); } catch (Throwable $x) { error_log('pf:so: ' . $x->getMessage()); return []; }
    }

    /** What a switch-on line holds, in words. */
    private static function soHolds(string $scope): string
    {
        if ($scope === 'all') return 'It holds everything: the master switch waits for it.';
        if ($scope === 'keys') return 'A key: Start the system checks the keys in its first step.';
        if ($scope === 'schedule') return 'Railway\'s schedule: Start the system sets it in its own step.';
        return 'It holds only ' . (isset(Automation::WORKFLOWS[$scope]) ? $scope . ' (' . Automation::WORKFLOWS[$scope][0] . ')' : 'what it\'s for (' . $scope . ')') . '; the rest can start.';
    }

    /** One switch-on line, tested: the check as Start the system reads it. */
    private static function soTest(PDO $pdo, string $id): array
    {
        $by = self::soCheck($pdo);
        if (!isset($by[$id])) $by = self::soCheck($pdo, true);
        $l = $by[$id] ?? null;
        if (!$l) return ['na', 'This line isn\'t in the switch-on check any more (it changed or was taken out); the next run lists the lines as they are.', 'Operations::switchOnCheck', []];
        $fix = !empty($l['fix']) ? [self::fixTool((string) $l['fix'][0], (string) $l['fix'][1])] : [];
        return [$l['ok'] ? 'ok' : 'bad', rtrim((string) $l['why']) . ' ' . self::soHolds((string) $l['scope']) . ($l['stage'] !== '' ? ' (' . $l['stage'] . ')' : ''),
                'Operations::switchOnCheck: this line, as Start the system reads it at its check step (nothing written)', $l['ok'] ? [] : $fix];
    }

    /** The check as Start the system or the Workflow board just read it, kept as each line's latest result, so the pre-flight,
     *  the board and Start show the same answers. */
    public static function recordSwitchOn(PDO $pdo, array $chk, string $by): void
    {
        try {
            $at = 0; $map = [];
            $by2 = self::soKeep($pdo, $chk, $at, $map);
            $res = self::results($pdo);
            foreach ($by2 as $id => $l) {
                $s = $l['ok'] ? 'ok' : 'bad';
                $was = $res[$id] ?? [];
                if (($was['s'] ?? '') === $s && (int) ($was['at'] ?? 0) > time() - 300) continue;   // unchanged and recent: kept as it is
                $fix = !empty($l['fix']) && !$l['ok'] ? [self::fixTool((string) $l['fix'][0], (string) $l['fix'][1])] : [];
                $hist = array_slice(array_merge((array) ($was['hist'] ?? []), [[time(), $s, 0]]), -10);
                self::save($pdo, $id, ['s' => $s, 'said' => self::clean(rtrim((string) $l['why']) . ' ' . self::soHolds((string) $l['scope'])), 'tested' => 'The switch-on check, read by ' . $by,
                                       'fix' => $fix, 'at' => time(), 'ms' => 0, 'by' => mb_substr($by, 0, 80), 'hist' => $hist]);
            }
        } catch (Throwable $x) { error_log('Preflight::recordSwitchOn: ' . $x->getMessage()); }
    }

    public static function report(PDO $pdo): string
    {
        $t = fn (int $ts) => $ts ? gmdate('M j, Y g:i A', $ts) . ' UTC' : 'never';
        $mark = ['ok' => 'OK  ', 'warn' => 'BKUP', 'bad' => 'FAIL', 'na' => '--  ', 'wait' => 'WAIT', 'new' => 'NEW '];   // BKUP: running on a backup
        $lines = self::lines($pdo); $res = self::results($pdo); $sum = self::summary($pdo); $lim = self::slowMs($pdo);
        $o = ['TRAVERSENCE PRE-FLIGHT REPORT', 'Made ' . $t(time()) . ' from https://traversence.com/admin/config/preflight', ''];
        $o[] = 'Ready ' . $sum['ok'] . (!empty($sum['warn']) ? ' · On a backup ' . $sum['warn'] : '') . ' · Not ready ' . $sum['bad'] . ' · Waiting ' . $sum['wait'] . ' · Not needed yet ' . $sum['na'] . ($sum['new'] ? ' · Not run ' . $sum['new'] : '') . ' · Needed for launch and not ready ' . count($sum['req_bad']);
        $o[] = 'Last full run: ' . ($sum['start'] ? $t($sum['start']) . ' to ' . gmdate('g:i A', $sum['at']) . ' UTC (' . self::took($sum['secs']) . ')' : $t($sum['at'])) . ($sum['by'] !== '' ? ', by ' . $sum['by'] : '');
        $times = array_filter(array_map(fn ($r) => isset($r[1]) && $r[1] !== null ? gmdate('M j g:i A', (int) $r[0]) . ' ' . self::took((int) $r[1]) : '', $sum['runs']));
        if ($times) $o[] = 'Recent full runs: ' . implode('; ', $times);
        $one = function (string $id) use ($lines, $res, $mark, $t, $lim): array {
            $l = $lines[$id]; $r = $res[$id] ?? null; $s = (string) ($r['s'] ?? 'new');
            $x = [$mark[$s] . ' ' . $l[1] . ' [' . $id . ($l[2] ? ', needed for launch' : ', optional') . ']'];
            if (!$r) return array_merge($x, ['      Not run yet.']);
            $x[] = '      Took ' . self::took(null, (int) $r['ms']) . ((int) $r['ms'] > $lim ? ' (SLOW: over ' . self::took(null, $lim) . ')' : '') . ', ' . $t((int) $r['at']) . (($r['by'] ?? '') !== '' ? ', by ' . $r['by'] : '');
            $x[] = '      Answer: ' . (string) $r['said'];
            if (($r['tested'] ?? '') !== '') $x[] = '      Tested: ' . (string) $r['tested'];
            foreach ((array) ($r['fix'] ?? []) as $f) $x[] = '      Fix: ' . ($f[0] === 'do' ? $f[2] . ' (a button on the line)' : $f[1] . ': ' . ($f[0] === 'tool' && str_starts_with((string) $f[2], '/') ? 'https://traversence.com' . $f[2] : ($f[0] === 'tool' ? 'https://traversence.com/admin/config/preflight' . $f[2] : $f[2])));
            $h = array_slice((array) ($r['hist'] ?? []), -10);
            if (count($h) > 1) $x[] = '      Last runs: ' . implode(' ', array_map(fn ($e) => trim($mark[(string) $e[1]] ?? '?') . (isset($e[2]) ? ' ' . self::took(null, (int) $e[2]) : ''), $h));
            return $x;
        };
        $bad = array_keys($sum['req_bad'] + $sum['bad_ids']);
        $o[] = ''; $o[] = '== NOT READY (' . count($bad) . ', needed for launch first) ==';
        foreach ($bad as $id) $o = array_merge($o, $one($id));
        if (!$bad) $o[] = 'Nothing.';
        $o[] = ''; $o[] = '== SLOW (over ' . self::took(null, $lim) . ', slowest first) ==';
        foreach ($sum['slow'] as $id => $ms) $o[] = self::took(null, $ms) . '  ' . $lines[$id][1] . ' [' . $id . ']';
        if (!$sum['slow']) $o[] = 'Nothing.';
        foreach (self::GROUPS as $g => $label) {
            $o[] = ''; $o[] = '== ' . mb_strtoupper($label) . ' ==';
            foreach ($lines as $id => $l) if ($l[0] === $g) $o = array_merge($o, $one($id));
        }
        return implode("\n", $o) . "\n";
    }

    // ---- what Start the system shows first ---------------------------------------------------------------------------------

    /** The pre-flight's latest result, for Start the system's card. */
    public static function startNote(PDO $pdo): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $sum = self::summary($pdo);
        $link = '<a href="/admin/config/preflight">Pre-flight</a>';
        if (!$sum['at']) return '<p data-tv-status="info" data-tv-label="Pre-flight first:">It hasn\'t had a full run yet. Run it on ' . $link . ' before pressing Start: it proves each service, setting and workflow works, and changes nothing.</p>';
        $when = '<time datetime="' . gmdate('c', $sum['at']) . '" data-tv-time>' . gmdate('M j, g:i A', $sum['at']) . ' UTC</time>';
        $counts = $sum['ok'] . ' ✓ · ' . $sum['bad'] . ' ✗' . ($sum['wait'] ? ' · ' . $sum['wait'] . ' waiting' : '') . ' · ' . $sum['na'] . ' not needed yet';
        if ($sum['req_bad']) {
            $holds = [];
            foreach (array_slice($sum['req_bad'], 0, 5, true) as $id => $name) $holds[] = '<a href="/admin/config/preflight#pf-' . $e(str_replace(':', '-', $id)) . '">' . $e($name) . '</a>';
            return '<p data-tv-status="bad" data-tv-label="Pre-flight:">' . $counts . ' (' . $when . '). Needed for launch and not ready: ' . implode('; ', $holds) . (count($sum['req_bad']) > 5 ? '; and more' : '') . '. Each holds what it\'s for; the rest can start, or wait until they\'re fixed. See ' . $link . '.</p>';
        }
        return '<p data-tv-status="' . ($sum['bad'] ? 'info' : 'ok') . '" data-tv-label="Pre-flight:">' . $counts . ' (' . $when . ').' . ($sum['bad'] ? ' Nothing needed for launch is red; the red lines hold only what they\'re for.' : ' Everything is ready.') . ' See ' . $link . '.</p>';
    }
}
