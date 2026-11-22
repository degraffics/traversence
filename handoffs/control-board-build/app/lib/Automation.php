<?php
// app/lib/Automation.php — the switches over everything the site and the crawler gather and create (Jason, 2026-11-14:
// "Please update the site settings to be off until switched back on, also give me a master switch to turn on and off.
// Also I see the services as routed services connected to a task so we should be able to turn those on and off as
// well."). System Configuration → Automation; decisions/0163 §9.
//
// Four layers, one rule: a call happens only when every switch above it is on.
//   1. The master switch (automation_switches, master/all): off stops every workflow that gathers or creates content:
//      the crawler's plan says paused (CrawlQueue::paused(), so a Railway run ends at once even if its schedule comes
//      back), the site's workflow jobs (JOBS) skip, the AI features that serve them (CrawlService::FEATURE_WF) skip, and
//      their outside calls (OpenStreetMap, Wikipedia, openrouteservice, the second tier's page reads…) aren't made.
//      It never stops business operations: orders, billing, payments, email, releases, staging, Moments, members' own
//      actions (a card scan, Write it for me), staff pressing a button in the admin, guides' weather and roads.
//   2. A workflow (crawl_workflows.paused, W1–W13): its capture tasks, its scheduled jobs, its AI features and its
//      on-demand paths (W5's guides for a visitor, W9's places) stop.
//   3. A capture task (crawl_capture_tasks.enabled): the crawler's plan leaves it out.
//   4. A service (automation_switches, service/<key>): a person pauses an outside service; every caller asks first: the
//      crawler (its hosts are sent as holds, so the worker never calls them), the site's own callers (ServiceHealth::
//      allowed(), the gathering calls below), and the AI providers (AiClient). A route is a task's use of a service, as
//      AiClient::ROUTES maps an AI feature to its providers: pausing the service closes every route to it.
// A paused thing is "paused", never a failure, and never raises a Pulse warning. Each switch keeps who set it, when and why.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Automation
{
    /** The workflows (decisions/0160 §3; W0, 0164 §1.4's note): wf => [name, what it makes, where it runs]. Seeded into crawl_workflows. */
    public const WORKFLOWS = [
        'W0' => ['Indexer', 'Every URL found logged in the frontier, new sites classified by the machine, pages read where the rules allow: Phase 1, always on in small batches (decisions/0164 §1.4).', 'indexer (Railway, always on)'],
        'W1' => ['Listings', 'Businesses and organizations found, checked and published as listings.', 'crawler and site'],
        'W2' => ['Keeping listings current', 'Live listings rechecked: websites, who they are, map points, Google.', 'crawler'],
        'W3' => ['Sources and the place store', 'The source register: sites we read regularly, and what they name.', 'crawler and site'],
        'W4' => ['Place guides', 'Research briefs, guide drafts, names in guides, guide fixes.', 'site'],
        'W5' => ['Guides on demand', 'A first look at a place a visitor searched, then its full guide.', 'site'],
        'W6' => ['Heritage and topics', 'The heritage catalog, topic summaries and topic research.', 'crawler and site'],
        'W7' => ['Suggested journeys', 'Each live guide\'s journeys, with travel times from openrouteservice.', 'site'],
        'W8' => ['Find it', 'Places a guide, journey or story names that we don\'t have yet.', 'site'],
        'W9' => ['Landmarks and places on demand', 'Landmarks, their photos, places opened or searched that we don\'t hold, their descriptions.', 'crawler and site'],
        'W10' => ['Live events', 'Upcoming events for places with a live guide.', 'site'],
        'W11' => ['How-to guides', 'Official pages read into how-to guides.', 'crawler'],
        'W12' => ['Search learning', 'What people search, learned; gaps queued for the crawler.', 'crawler'],
        'W13' => ['Stories', 'Person-written only: no crawler. Its named places go to Find it (W8).', 'people'],
        'W14' => ['Authority sources', 'Each state\'s licence and registry sources found, their terms read and readers tested, and staged switched off ahead of its hubs (decisions/0164 §7.1).', 'crawler and site'],
    ];

    /** The site's scheduled jobs that gather or create content: job => its workflows. A job skips when the master is
     *  off, or when every one of its workflows is paused (one that's on still runs its part; the rest stop at their AI
     *  features and calls). */
    public const JOBS = [
        'guide_finds_tick' => ['W4', 'W8', 'W10'],
        'guide_ondemand_tick' => ['W5'],
        'journeys_tick' => ['W7'],
        'heritage_tick' => ['W6'],
        'listings_tick' => ['W1'],
        'landmark_photos_tick' => ['W9'],
        'data_cleanup_tick' => ['W1'],
        'identity_recheck_tick' => ['W2'],
        'page_read_tick' => ['W3'],   // stage 9's reader of pages waiting to be read; shipped off
        'vectors_tick' => ['W3'],   // the vector index's upkeep (decisions/0164 §5.2), under Supabase's card; shipped off   // the identity recheck (decisions/0164 §6.1): W2's task `recheck`, shipped off   // Data cleanup's daily pass (decisions/0163 §8 stage 1b): under the master switch, and shipped off
    ];

    /** AI features a person presses for themselves (a staff member in the admin, a member, a business): the master and
     *  workflow switches don't stop them (decisions/0133: AI acts on a person's words only when called); the service
     *  switch and AI use's own switch do. Every other feature in CrawlService::FEATURE_WF is a workflow's. */
    public const PERSON_FEATURES = ['guide_draft', 'fact_check', 'topic_assist', 'item_write', 'card', 'brochure', 'photo_product', 'menu'];

    /** AI providers (AiClient::PROVIDERS) => their outside service (OutsideServices::COMPANIES). */
    public const PROVIDER_SERVICE = ['claude' => 'anthropic', 'google' => 'google', 'aws' => 'aws', 'gemini' => 'gemini'];

    /**
     * Services whose calls are business operations: not paused from here, so the master's promise holds (they're on the
     * page, greyed, with the reason). service => what depends on it.
     */
    public const NOT_PAUSABLE = [
        'bluehost' => 'the site itself, its jobs and its own email',
        'quickbooks' => 'invoices, card payments and refunds',
        'stripe' => 'Marketplace card checkout',
        'square' => 'Marketplace card checkout',
        'aws' => 'email (SES) and files (S3); Textract\'s scans stop with the second tier\'s switch in AI use',
        'cloudflare' => 'file storage and the sign-in captcha',
        'github' => 'releases and the 3-way sync',
        'microsoft' => 'sign-in and OneDrive',
        'apple' => 'sign-in',
        'meta' => 'sign-in',
        'namecom' => 'businesses\' domains',
        'fly' => 'businesses\' own domains on their storefronts',
        'railway' => 'it runs the crawler: its schedule is set on Railway, and while the master is off each run ends at once',
        'onepassword' => 'staff logins',
        'nws' => 'guides\' weather',
        'az511' => 'guides\' road work',
        'tailwind' => 'page styles',
        'vimeo' => 'members\' videos',
        'dbip' => 'the sign-up region check',
        'cpsc' => 'the Marketplace\'s recall check',
        'census' => 'map points for addresses people enter; the crawler\'s geocode task has its own switch',
    ];

    /** The release's seed (migration 2026-11-14_automation_switches): what the master says before anyone changes it. */
    public const SEED_WHY = 'Paused by Jason, 2026-11-14, until the crawler review';

    private static ?array $rows = null;
    private static ?array $wfs = null;

    /** Reads the switches once a request (and again after a change). Before the database update: the master is off. */
    private static function rows(?PDO $pdo): array
    {
        if (self::$rows !== null) return self::$rows;
        self::$rows = [];
        try {
            $pdo = $pdo ?? Database::connection();
            foreach ($pdo->query('SELECT s.*, u.display_name AS by_user FROM automation_switches s LEFT JOIN users u ON u.id = s.by_uid')->fetchAll(PDO::FETCH_ASSOC) as $r) self::$rows[$r['kind'] . ':' . $r['ref']] = $r;
        } catch (Throwable $e) {
            try { foreach (($pdo ?? Database::connection())->query('SELECT * FROM automation_switches')->fetchAll(PDO::FETCH_ASSOC) as $r) self::$rows[$r['kind'] . ':' . $r['ref']] = $r; }
            catch (Throwable $e2) { self::$rows = ['master:all' => ['kind' => 'master', 'ref' => 'all', 'paused' => 1, 'why' => self::SEED_WHY . ' (the switches\' database update hasn\'t run yet)', 'by_name' => 'Jason', 'at' => null, '_missing' => 1]]; }
        }
        return self::$rows;
    }

    public static function ready(?PDO $pdo = null): bool { return !isset(self::rows($pdo)['master:all']['_missing']); }

    public static function forget(): void { self::$rows = null; self::$wfs = null; self::$pauses = null; }

    // ---- paused by the system (decisions/0175 §2–3, Jason 2026-11-20: "the app service is not turned off when its
    // performance is not functional unless it requires it off for maintenance, hence PAUSED") ---------------------------
    // A fault never writes into a person's switch. What the board stops at a trip is a pause of its own here, with its
    // alarm and its reason; every gate reads it beside the switch (masterOff, wfOff, serviceOff, taskOff), and it lifts
    // when its alarm's check passes. A person's switch stays as they left it.

    private static ?array $pauses = null;

    /** The system's open pauses: "kind:ref" => row (kind master, wf, task, service). Never throws. */
    public static function pauses(?PDO $pdo = null): array
    {
        if (self::$pauses !== null) return self::$pauses;
        self::$pauses = [];
        try {
            foreach (($pdo ?? Database::connection())->query('SELECT * FROM system_pauses WHERE cleared_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $r) self::$pauses[$r['kind'] . ':' . $r['ref']] ??= $r;
        } catch (Throwable $e) {}   // before its database update: nothing is paused by the system
        return self::$pauses;
    }

    public static function ensurePauses(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_pauses (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL, ref VARCHAR(60) NOT NULL,
            alarm_id INT UNSIGNED NULL, why VARCHAR(255) NOT NULL DEFAULT '', at DATETIME NOT NULL, cleared_at DATETIME NULL, cleared_how VARCHAR(255) NULL,
            KEY open_ref (kind, ref, cleared_at), KEY alarm (alarm_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** Why the system has this paused ("Paused by the system (a trip): … It resumes when its check passes."), or null.
     *  A trip is the system placing this pause (Jason, 2026-11-20: "A trip is when the PAUSE has been placed"). */
    public static function sysOff(?PDO $pdo, string $kind, string $ref): ?string
    {
        $r = self::pauses($pdo)[$kind . ':' . $ref] ?? null;
        return $r ? 'Paused by the system (a trip): ' . rtrim((string) $r['why'], '. ') . ' (since ' . self::fmt((string) $r['at']) . '). It resumes when its check passes.' : null;
    }

    /** The system pauses one thing for an alarm. False when that alarm already holds it paused. */
    public static function sysPause(PDO $pdo, string $kind, string $ref, string $why, int $alarmId): bool
    {
        self::ensurePauses($pdo);
        $st = $pdo->prepare('SELECT 1 FROM system_pauses WHERE kind = ? AND ref = ? AND alarm_id = ? AND cleared_at IS NULL'); $st->execute([$kind, $ref, $alarmId]);
        if ($st->fetchColumn()) return false;
        $pdo->prepare('INSERT INTO system_pauses (kind, ref, alarm_id, why, at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())')->execute([$kind, mb_substr($ref, 0, 60), $alarmId ?: null, mb_substr($why, 0, 255)]);
        self::$pauses = null;
        return true;
    }

    /** Lifts what an alarm paused; returns how many. */
    public static function sysResume(PDO $pdo, int $alarmId, string $how): int
    {
        try {
            $st = $pdo->prepare('UPDATE system_pauses SET cleared_at = UTC_TIMESTAMP(), cleared_how = ? WHERE alarm_id = ? AND cleared_at IS NULL');
            $st->execute([mb_substr($how, 0, 255), $alarmId]);
            self::$pauses = null;
            return $st->rowCount();
        } catch (Throwable $e) { return 0; }
    }

    /** The first of these the system has paused ("master:all", "wf:W1", "service:tavily", "task:x"), in words, or null. */
    public static function sysAny(?PDO $pdo, array $keys): ?string
    {
        foreach ($keys as $k) { [$kind, $ref] = explode(':', $k, 2) + ['', '']; if ($ref !== '' && ($w = self::sysOff($pdo, $kind, $ref)) !== null) return ($kind === 'master' ? 'All gathering' : ($kind === 'wf' ? $ref : $ref)) . ': ' . $w; }
        return null;
    }

    /** Why the system has a crawler task paused, or null (its switch is the person's: crawl_capture_tasks.enabled). */
    public static function taskOff(?PDO $pdo, string $task): ?string { return self::sysOff($pdo, 'task', $task); }

    /**
     * Today's switches a trip wrote into (before decisions/0175), each put back to what a person last set: the last line
     * in the switch log by a person, never the board or Start the system (02 §0a ruling 4). Where nobody ever set it,
     * it stays as it is. Safe to run again: once put back, nothing carries the trip's name. Returns what it put back.
     */
    public static function putBackTripSwitches(PDO $pdo): array
    {
        $out = [];
        $trip = '%the Control Board (a trip)%';
        $last = function (string $kind, string $ref) use ($pdo): ?array {
            // a person's own act on the switch: Start the system's setting doesn't count as a person's (02 §0a ruling 4, O4)
            $st = $pdo->prepare("SELECT paused, by_name, at FROM automation_log WHERE kind = ? AND ref = ? AND COALESCE(by_name, '') NOT LIKE 'the Control Board%' AND COALESCE(by_name, '') NOT IN ('Start the system', 'the system') AND COALESCE(why, '') NOT LIKE '%the Control Board (a trip)%' AND COALESCE(why, '') NOT LIKE 'Start the system%' ORDER BY id DESC LIMIT 1");
            $st->execute([$kind, $ref]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        };
        $said = fn (array $l) => 'Put back to what ' . (trim((string) $l['by_name']) !== '' ? trim((string) $l['by_name']) : 'a person') . ' set on ' . self::fmt((string) $l['at']) . ' (decisions/0175: a fault pauses, never switches off)';
        try {
            foreach ($pdo->query("SELECT task FROM crawl_capture_tasks WHERE enabled = 0 AND (off_why LIKE " . $pdo->quote($trip) . ")")->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $l = $last('task', (string) $t);
                if (!$l || (int) $l['paused']) continue;
                require_once __DIR__ . '/crawler/CrawlService.php';
                CrawlService::setTask($pdo, (string) $t, true, 0, '');
                self::log($pdo, 'task', (string) $t, false, $said($l), 0, 'the system');
                $out[] = 'task ' . $t;
            }
        } catch (Throwable $e) { error_log('put back tasks: ' . $e->getMessage()); }
        try {
            foreach ($pdo->query("SELECT wf FROM crawl_workflows WHERE paused = 1 AND paused_why LIKE " . $pdo->quote($trip))->fetchAll(PDO::FETCH_COLUMN) as $wf) {
                $l = $last('workflow', (string) $wf);
                if (!$l || (int) $l['paused']) continue;
                $pdo->prepare('UPDATE crawl_workflows SET paused = 0, paused_why = NULL, paused_by = NULL, paused_at = NULL WHERE wf = ?')->execute([(string) $wf]);
                self::log($pdo, 'workflow', (string) $wf, false, $said($l), 0, 'the system');
                $out[] = 'workflow ' . $wf;
            }
        } catch (Throwable $e) { error_log('put back workflows: ' . $e->getMessage()); }
        try {
            foreach ($pdo->query("SELECT kind, ref FROM automation_switches WHERE paused = 1 AND (by_name LIKE 'the Control Board (a trip)%' OR why LIKE " . $pdo->quote($trip) . " OR why LIKE 'A trip on the Control Board:%')")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $l = $last((string) $r['kind'], (string) $r['ref']);
                if (!$l || (int) $l['paused']) continue;
                $pdo->prepare('UPDATE automation_switches SET paused = 0, why = ?, by_uid = NULL, by_name = ?, at = UTC_TIMESTAMP() WHERE kind = ? AND ref = ?')->execute([mb_substr($said($l), 0, 255), 'the system', $r['kind'], $r['ref']]);
                self::log($pdo, (string) $r['kind'], (string) $r['ref'], false, $said($l), 0, 'the system');
                $out[] = $r['kind'] . ' ' . $r['ref'];
            }
        } catch (Throwable $e) { error_log('put back switches: ' . $e->getMessage()); }
        if ($out) self::forget();
        return $out;
    }

    /** Who set a switch, for its line ("Jason"); '' when no one is named. */
    public static function by(array $r): string
    {
        return trim((string) (($r['by_user'] ?? '') !== '' ? $r['by_user'] : ($r['by_name'] ?? '')));
    }

    /** A stored UTC time, labeled ("Nov 14, 4:05 PM UTC"). */
    public static function fmt(?string $utc): string
    {
        if (!$utc) return '';
        $ts = strtotime($utc . ' UTC');
        return $ts ? gmdate('M j, g:i A', $ts) . ' UTC' : (string) $utc;
    }

    /** "why (set by Jason, Nov 14, 4:05 PM UTC)": a switch's reason and who set it, for every line that names it. */
    public static function line(array $r, string $fallback): string
    {
        $why = trim((string) ($r['why'] ?? '')) !== '' ? trim((string) $r['why']) : $fallback;
        $by = self::by($r); $at = self::fmt($r['at'] ?? null);
        return $why . ($by !== '' || $at !== '' ? ' (set' . ($by !== '' ? ' by ' . $by : '') . ($at !== '' ? ', ' . $at : '') . ')' : '');
    }

    // ---- the four layers -----------------------------------------------------------------------------------------------

    /** The master switch: null when it's on, else the row (why, who, when). */
    public static function master(?PDO $pdo = null): ?array
    {
        $r = self::rows($pdo)['master:all'] ?? null;
        return $r && (int) $r['paused'] ? $r : null;
    }

    /** The master switch's row, on or off (who last set it, when, why). */
    public static function masterRow(?PDO $pdo = null): ?array { return self::rows($pdo)['master:all'] ?? null; }

    /** Why automation is paused, or null when it's on. */
    public static function masterOff(?PDO $pdo = null): ?string
    {
        $m = self::master($pdo);
        return $m ? 'Automation is paused: ' . self::line($m, 'paused') . '.' : null;
    }

    /** The workflows' rows (crawl_workflows), read once a request. */
    public static function workflows(?PDO $pdo = null): array
    {
        if (self::$wfs !== null) return self::$wfs;
        self::$wfs = [];
        try {
            $pdo = $pdo ?? Database::connection();
            try { $rows = $pdo->query('SELECT w.*, u.display_name AS by_user FROM crawl_workflows w LEFT JOIN users u ON u.id = w.paused_by')->fetchAll(PDO::FETCH_ASSOC); }
            catch (Throwable $e) { $rows = $pdo->query('SELECT * FROM crawl_workflows')->fetchAll(PDO::FETCH_ASSOC); }
            foreach ($rows as $r) self::$wfs[(string) $r['wf']] = $r;
        } catch (Throwable $e) {}
        return self::$wfs;
    }

    /** Why a workflow is paused, or null when it runs. */
    public static function wfOff(?PDO $pdo, string $wf): ?string
    {
        $r = self::workflows($pdo)[$wf] ?? null;
        if (!$r || !(int) $r['paused']) return null;
        return $wf . ' ' . (string) ($r['name'] ?? self::WORKFLOWS[$wf][0] ?? '') . ' is paused: ' . self::line(['why' => $r['paused_why'] ?? '', 'by_user' => $r['by_user'] ?? '', 'at' => null], 'paused')
            . (!empty($r['paused_at']) ? ' since ' . date('M j', (int) strtotime((string) $r['paused_at'])) : '') . '.';
    }

    /** A service's pause: null when it may be called, else the row. */
    public static function service(?PDO $pdo, string $svc): ?array
    {
        $r = self::rows($pdo)['service:' . $svc] ?? null;
        return $r && (int) $r['paused'] ? $r : null;
    }

    /** Why a service is paused ("Paused by Jason …"), or null. A status like ServiceHealth's: never a failure. */
    public static function serviceOff(?PDO $pdo, string $svc): ?string
    {
        if ($svc === '' || isset(self::NOT_PAUSABLE[$svc])) return null;
        $r = self::service($pdo, $svc);
        if (!$r) return null;
        require_once __DIR__ . '/OutsideServices.php';
        return (OutsideServices::COMPANIES[$svc][0] ?? $svc) . ' is paused: ' . self::line($r, 'paused by a person') . '.';
    }

    /** The service a host belongs to (its card's hosts, or a server on one of its lists). */
    public static function serviceOfHost(?PDO $pdo, string $host): string
    {
        require_once __DIR__ . '/ServiceHealth.php';
        try { return ServiceHealth::serviceOf($pdo ?? Database::connection(), strtolower($host)); } catch (Throwable $e) { require_once __DIR__ . '/OutsideServices.php'; return OutsideServices::serviceOfHost($host); }
    }

    /** Why a host may not be called now because its service is paused, or null. */
    public static function hostOff(?PDO $pdo, string $host): ?string
    {
        $svc = self::serviceOfHost($pdo, $host);
        return self::serviceOff($pdo, $svc) ?? ($svc !== '' && !isset(self::NOT_PAUSABLE[$svc]) ? self::sysAny($pdo, ['service:' . $svc]) : null);
    }

    /** Every host of every paused service: host => why (the crawler's plan sends them as holds; the worker never calls them). */
    public static function pausedHosts(?PDO $pdo = null): array
    {
        $out = [];
        require_once __DIR__ . '/OutsideServices.php';
        $paused = [];
        foreach (self::rows($pdo) as $r) if (($r['kind'] ?? '') === 'service' && (int) $r['paused']) $paused[(string) $r['ref']] = 'Paused by a person (' . self::line($r, 'paused') . ')';
        foreach (self::pauses($pdo) as $r) if ($r['kind'] === 'service') $paused[(string) $r['ref']] ??= (string) self::sysOff($pdo, 'service', (string) $r['ref']);
        foreach ($paused as $svc => $why) {
            if (isset(self::NOT_PAUSABLE[$svc])) continue;
            foreach (OutsideServices::HOSTS[$svc] ?? [] as $h) $out[$h] = $why;
            try { foreach (($pdo ?? Database::connection())->query('SELECT url FROM service_servers WHERE service = ' . ($pdo ?? Database::connection())->quote($svc))->fetchAll(PDO::FETCH_COLUMN) as $u) if (($h = strtolower((string) parse_url((string) $u, PHP_URL_HOST))) !== '') $out[$h] = $why; } catch (Throwable $e) {}
        }
        return $out;
    }

    // ---- the questions callers ask -----------------------------------------------------------------------------------

    /**
     * A gathering call (a workflow's, made by itself): null when it may go ahead, else why not. $wf '' when the caller
     * serves several workflows (its own workflow is checked where it starts); $service '' when it calls no outside service.
     */
    public static function gathering(?PDO $pdo, string $service = '', string $wf = ''): ?string
    {
        if (($w = self::masterOff($pdo)) !== null) return $w;
        if ($wf !== '' && ($w = self::wfOff($pdo, $wf)) !== null) return $w;
        if ($service !== '' && ($w = self::serviceOff($pdo, $service)) !== null) return $w;
        // paused by the system: a trip (decisions/0175 §3), beside the person's switches, never written into them
        return self::sysAny($pdo, array_filter(['master:all', $wf !== '' ? 'wf:' . $wf : '', $service !== '' && !isset(self::NOT_PAUSABLE[$service]) ? 'service:' . $service : '']));
    }

    /** gathering() for a URL: its service read from its host. */
    public static function gatheringUrl(?PDO $pdo, string $url, string $wf = ''): ?string
    {
        if (($w = self::gathering($pdo, '', $wf)) !== null) return $w;
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $host !== '' ? self::hostOff($pdo, $host) : null;
    }

    /** A scheduled job (Scheduler::run): null when it runs, else why it skips. Jobs not in JOBS always run. */
    public static function job(?PDO $pdo, string $job): ?string
    {
        if (!isset(self::JOBS[$job])) return null;
        if (($w = self::masterOff($pdo)) !== null) return $w;
        $why = [];
        if (($w = self::sysAny($pdo, ['master:all'])) !== null) return $w;
        foreach (self::JOBS[$job] as $wf) { $w = self::wfOff($pdo, $wf) ?? self::sysAny($pdo, ['wf:' . $wf]); if ($w === null) return null; $why[] = $w; }
        return implode(' ', $why);
    }

    /** An AI feature (AiClient::allow): null when it may run, else why not. */
    public static function feature(?PDO $pdo, string $f): ?string
    {
        if (($w = self::serviceOff($pdo, 'anthropic') ?? self::sysAny($pdo, ['service:anthropic'])) !== null) return $w;
        if (in_array($f, self::PERSON_FEATURES, true)) return null;   // a person's own press
        require_once __DIR__ . '/crawler/CrawlService.php';
        $wf = CrawlService::FEATURE_WF[$f] ?? '';
        if ($wf === '') return null;   // not a workflow's
        return self::gathering($pdo, '', $wf);
    }

    /** An AI provider's service paused (AiClient::providerFor and its calls): why, or null. */
    public static function provider(string $provider): ?string
    {
        $svc = self::PROVIDER_SERVICE[$provider] ?? '';
        return self::serviceOff(null, $svc) ?? ($svc !== '' && !isset(self::NOT_PAUSABLE[$svc]) ? self::sysAny(null, ['service:' . $svc]) : null);
    }

    /** What a visitor is told where an on-demand feature is paused: never our internal reason, always a way on. */
    public const VISITOR_NOTE = 'New guides are paused for now. We\'ve kept your ask: it\'s built as soon as they\'re back on, most asked-for first. Tap Notify me to hear when it\'s ready, and see what we already list there.';

    // ---- setting them ------------------------------------------------------------------------------------------------

    /** The master switch, or a service, on or off, with why and who. Logged. */
    public static function set(PDO $pdo, string $kind, string $ref, bool $pause, string $why, int $uid, string $byName): void
    {
        if (!in_array($kind, ['master', 'service'], true)) throw new InvalidArgumentException('Unknown switch.');
        if ($kind === 'master') $ref = 'all';
        if ($kind === 'service') {
            require_once __DIR__ . '/OutsideServices.php';
            if (!isset(OutsideServices::COMPANIES[$ref])) throw new InvalidArgumentException('No such outside service.');
            if (isset(self::NOT_PAUSABLE[$ref])) throw new InvalidArgumentException('Its calls are business operations (' . self::NOT_PAUSABLE[$ref] . '): it isn\'t paused from here.');
            // a retired one stays off until it's brought back on its card (decisions/0173 §2), from wherever it's switched on
            require_once __DIR__ . '/ServiceStages.php';
            if (!$pause && ServiceStages::isRetired($pdo, $ref)) throw new InvalidArgumentException(OutsideServices::COMPANIES[$ref][0] . ' is retired: Bring it back on its card in the Service register first.');
        }
        $why = mb_substr(trim($why), 0, 255);
        if ($pause && $why === '') throw new InvalidArgumentException('Say why, so whoever sees it paused knows.');
        $pdo->prepare('INSERT INTO automation_switches (kind, ref, paused, why, by_uid, by_name, at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                       ON DUPLICATE KEY UPDATE paused = VALUES(paused), why = VALUES(why), by_uid = VALUES(by_uid), by_name = VALUES(by_name), at = VALUES(at)')
            ->execute([$kind, $ref, $pause ? 1 : 0, $why !== '' ? $why : null, $uid ?: null, mb_substr($byName, 0, 80) ?: null]);
        self::log($pdo, $kind, $ref, $pause, $why, $uid, $byName);
        self::forget();
        if ($kind === 'master' && !$pause) self::resumed($pdo, array_keys(self::WORKFLOWS), 'all');
        // Retire (ServiceStages::retire switches it off "Retired: …" first): its open trips close as Retired, their holds lift and
        // their history is kept (02 row 116, §3c; NG: a close without a check, the one allowed for a deleted part)
        if ($kind === 'service' && $pause && str_starts_with($why, 'Retired: ')) { try { require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::retired($pdo, $ref, $byName); } catch (Throwable $x) { error_log('retired alarms: ' . $x->getMessage()); } }
    }

    /** Switched back on: due dates move on by the pause (decisions/0164 §6.1, X1); the backlog then drains at half (Clocks::ramp). */
    private static function resumed(PDO $pdo, array $wfs, string $whose): void
    {
        try {
            require_once __DIR__ . '/Clocks.php';
            foreach ($wfs as $wf) { $secs = Clocks::lastPause($pdo, $whose === 'all' ? $wf : $whose); if ($secs > 0) Clocks::shiftOnResume($pdo, $wf, $secs); }
        } catch (Throwable $e) { error_log('automation resumed: ' . $e->getMessage()); }
    }

    /** A workflow paused or resumed (crawl_workflows, through CrawlService::pause). Logged. */
    public static function setWorkflow(PDO $pdo, string $wf, bool $pause, string $why, int $uid, string $byName): void
    {
        if (!isset(self::WORKFLOWS[$wf])) throw new InvalidArgumentException('No such workflow.');
        if ($pause && trim($why) === '') throw new InvalidArgumentException('Say why, so whoever sees it paused knows.');
        require_once __DIR__ . '/crawler/CrawlService.php';
        CrawlService::pause($pdo, $wf, $pause, $uid, $why);
        self::log($pdo, 'workflow', $wf, $pause, $why, $uid, $byName);
        self::forget();
        if (!$pause) self::resumed($pdo, [$wf], $wf);
    }

    /** A capture task on or off (crawl_capture_tasks, through CrawlService::setTask). Logged. */
    public static function setTask(PDO $pdo, string $task, bool $on, string $why, int $uid, string $byName): void
    {
        if (!$on && trim($why) === '') throw new InvalidArgumentException('Say why, so whoever sees it off knows.');
        require_once __DIR__ . '/crawler/CrawlService.php';
        CrawlService::setTask($pdo, $task, $on, $uid, $why);
        self::log($pdo, 'task', $task, !$on, $why, $uid, $byName);
    }

    private static function log(PDO $pdo, string $kind, string $ref, bool $pause, string $why, int $uid, string $byName): void
    {
        try {
            $pdo->prepare('INSERT INTO automation_log (at, kind, ref, paused, why, by_uid, by_name) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?)')
                ->execute([$kind, mb_substr($ref, 0, 40), $pause ? 1 : 0, mb_substr(trim($why), 0, 255) ?: null, $uid ?: null, mb_substr($byName, 0, 80) ?: null]);
        } catch (Throwable $e) { error_log('automation log: ' . $e->getMessage()); }
        // a service's switch is its stage too (decisions/0173 §2): Paused, or Active again, in its one history
        if ($kind === 'service') { require_once __DIR__ . '/ServiceStages.php'; ServiceStages::log($pdo, $ref, $pause ? 'paused' : 'active', $why, $uid, $byName); }
    }

    /** The last changes, newest first. */
    public static function history(PDO $pdo, int $n = 25): array
    {
        try { return $pdo->query('SELECT * FROM automation_log ORDER BY id DESC LIMIT ' . max(1, min(200, $n)))->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; }
    }

    /** Every service row (paused or resumed), for the page: service => row. */
    public static function services(?PDO $pdo = null): array
    {
        $out = [];
        foreach (self::rows($pdo) as $r) if (($r['kind'] ?? '') === 'service') $out[(string) $r['ref']] = $r;
        return $out;
    }
}
