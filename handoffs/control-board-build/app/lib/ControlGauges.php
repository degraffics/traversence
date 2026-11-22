<?php
// app/lib/ControlGauges.php — the System Control Board's gauges (decisions/0171; handoffs/control-board-build-log.md §7, the
// views' one contract). The repo makes each gauge's configuration and evidence (tools/page-audit/gauges.py writes
// site-audit/results/gauges.json); the site reads that public file (no key, no cost), keeps it an hour, keeps the last good
// copy a month (as SiteHealth does), and works out every stage here, from the file's criteria: a stage is computed, never
// stored (step3/sections/gauge-lifecycle.md §3, §7). Nothing read: every gauge says "not checked yet" (03 §27: an empty grey
// dial, "not checked yet" once), and the page never fails.
//   - development(): the 16 development gauges. Planning at 0 until Jason signs off the configuration; the draft's % is
//     the preview (§6). The rule, in order (§3): not signed → Planning; any item not built → Phased Development; any item
//     without its check or its review → Audit & Review; any item not live or not proved → Implementation; else Monitoring.
//   - runtime(): the 23 runtime gauges. Setup (the probe built, its target set, its check passing, its alarm wired) is the
//     score below Monitoring; at Monitoring the live reading against its target (§7). A reading is the site's own, marked
//     live with its time: Trips reach a person (control_alarms' trips and their email), Incident Reports close
//     (control_incidents); an alarm-wired gauge's parts tripped make it red and its check not passing.
//   - workflows(): W0–W14 (step3/workflows-page.md §5): W13 a list, W14 not built; each one's holds from the engine.
// A stage that moves back is logged in the Control Board's logbook with its reason (§3: "the drop is logged").
// Ids, words and readings only: nothing about a person.
declare(strict_types=1);

require_once __DIR__ . '/PageCache.php';

final class ControlGauges
{
    /** where the repo publishes the gauges (a public file in the docs repository) */
    public const SOURCE = 'https://raw.githubusercontent.com/degraffics/traversence/main/site-audit/results/gauges.json';
    /** Tests (or staging) read another copy: a local file path or an https URL; null reads GAUGES_URL, else SOURCE. */
    public static ?string $source = null;
    /** Jason's five words (gauge-lifecycle.md §2) => the score gauge's stage key (app/ui/score-gauge.php). */
    public const STAGES = ['Planning' => 'planning', 'Phased Development' => 'phased', 'Audit & Review' => 'audit', 'Implementation' => 'impl', 'Monitoring' => 'monitor'];
    /** Each item's definition of done, the default list in the lifecycle's order (§4). */
    public const CRITERIA = ['decided', 'built', 'checked', 'reviewed', 'live', 'proved'];
    public const SETUP = ['probe' => 'The probe built', 'target' => 'Its target set', 'check' => 'Its check passing', 'alarm' => 'Its alarm wired to the board'];
    public const NOT_CHECKED = 'not checked yet';
    public const NO_TARGET = 'target: set by Jason';
    /** Gauges whose reading the site has itself (§7: "the live reading it has stays on the card, marked live"). */
    public const LIVE = ['o-told', 'o-ir'];
    /** With nothing read, the gauges are still drawn, each "not checked yet" (03 §27). The 16 (gauge-lifecycle.md §6). */
    public const DEV_FALLBACK = ['Discovery', 'Directory', 'Community', 'Marketplace', 'TRVGO – Storefront', 'Accounts', 'Dashboard & Pulse',
        'Get Local 360°', 'Let\'s Explore 360°', 'Market 360°', 'Social 360°', 'Sales & Growth', 'White Glove Concierge', 'Back-Office',
        'Insights & Research', 'System Configuration'];
    /** The 23 runtime gauges by id (step3's board, gauge-lifecycle.md §7); the test holds them to the file's. */
    public const RT_FALLBACK = ['o-pages' => 'Pages answer', 'o-speed' => 'Pages load fast', 'o-signin' => 'Sign-in works end to end',
        'o-phone' => 'The phone layout', 'o-search' => 'Search finds what they came for', 'o-near' => '3 within 5 mi', 'o-paths' => 'Paths complete',
        'o-fresh' => 'Fresh', 'o-complete' => 'Complete', 'o-leak' => 'Nothing private or sacred leaks', 'o-photos' => 'Photos load',
        'o-gather' => 'Gathering reconciles', 'o-grow' => 'Knowledge keeps growing', 'o-pay' => 'Payments go through', 'o-renew' => 'Renewals run',
        'o-email' => 'Email is delivered', 'o-claude' => 'Within our means: Claude (AI)', 'o-places' => 'Within our means: Google Places',
        'o-search-cap' => 'Within our means: web search', 'o-told' => 'Trips reach a person', 'o-uptime' => 'Things recover',
        'o-ir' => 'Incident Reports close', 'o-selfrec' => 'The system recovers by itself'];

    /** One read per request. */
    private static ?array $loaded = null;
    /** Open alarms by process: proc => ['trip' => n, 'warning' => n, 'notice' => n, 'title' => the worst's title]. */
    private static ?array $alarms = null;
    private static bool $alarmsRead = false;

    public static function forget(): void { self::$loaded = null; self::$alarms = null; self::$alarmsRead = false; }

    // ---- the configuration: read, kept an hour, the last good copy a month (SiteHealth's pattern) ------------------

    /** Where the configuration was read and its time: ['ok', 'at' (when the repo made it), 'from', 'read_at', 'kept', 'error']. */
    public static function source(PDO $pdo): array
    {
        $c = self::config($pdo);
        return ['ok' => (bool) $c['ok'], 'at' => (string) ($c['at'] ?? ''), 'from' => (string) ($c['from'] ?? self::where()), 'read_at' => (int) ($c['read_at'] ?? 0),
                'kept' => !empty($c['kept']), 'error' => (string) ($c['error'] ?? '')];
    }

    private static function where(): string
    {
        if (self::$source !== null) return self::$source;
        try { if (class_exists('Database')) Database::readEnv(); } catch (Throwable $x) {}
        $u = trim((string) (getenv('GAUGES_URL') ?: ''));   // staging, or a test, can read another copy (as SITE_HEALTH_URL)
        return preg_match('#^https://#', $u) ? $u : self::SOURCE;
    }

    /** The parsed configuration, or ['ok' => false, 'error' => why]; with the last good copy kept when a read fails. */
    public static function config(PDO $pdo, bool $fresh = false): array
    {
        if (self::$loaded !== null && !$fresh) return self::$loaded;
        try {
            if ($fresh) PageCache::forget($pdo, 'gauges:now');
            $now = PageCache::get($pdo, 'gauges:now');
            if (is_array($now) && isset($now['ok'])) return self::$loaded = $now;
            $got = self::fetch();
            if ($got['ok']) PageCache::put($pdo, 'gauges:last', 30 * 86400, $got);
            else {
                $last = PageCache::get($pdo, 'gauges:last');
                if (is_array($last) && !empty($last['ok'])) $got = ['error' => $got['error'], 'kept' => true] + $last;   // the last good copy, with why it's not newer
            }
            PageCache::put($pdo, 'gauges:now', $got['ok'] ? 3600 : 600, $got);
            return self::$loaded = $got;
        } catch (Throwable $x) {
            return self::$loaded = ['ok' => false, 'error' => 'The gauges couldn\'t be read: ' . $x->getMessage(), 'from' => self::where()];
        }
    }

    private static function fetch(): array
    {
        $from = self::where();
        $bad = function (string $why) use ($from): array { return ['ok' => false, 'error' => $why, 'from' => $from, 'read_at' => time()]; };
        if (!preg_match('#^https://#', $from)) {   // a local copy (tests, a bench)
            $body = is_file($from) ? @file_get_contents($from) : false;
            return $body === false ? $bad('The gauges\' file isn\'t there (' . $from . ').') : self::parse((string) $body, $from);
        }
        if (!function_exists('curl_init')) return $bad('This server can\'t make web requests (curl).');
        try { require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold($from, 'the gauges\' configuration')) !== null) return $bad($w); } catch (Throwable $x) {}
        $ch = curl_init($from);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Traversence control board', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code !== 200) return $bad('GitHub didn\'t answer (' . ($err !== '' ? $err : 'HTTP ' . $code) . ').');
        return self::parse((string) $body, $from);
    }

    /** The file, checked: the three lists present, links only to this site or https. */
    public static function parse(string $body, string $from = ''): array
    {
        $d = json_decode($body, true);
        if (!is_array($d) || !is_array($d['development'] ?? null) || !is_array($d['runtime'] ?? null) || !is_array($d['workflows'] ?? null))
            return ['ok' => false, 'error' => 'The gauges\' file couldn\'t be read.', 'from' => $from, 'read_at' => time()];
        return ['ok' => true, 'at' => mb_substr((string) ($d['generated_at'] ?? ''), 0, 30), 'from' => $from, 'read_at' => time(), 'error' => '', 'kept' => false,
                'checks' => is_array($d['checks'] ?? null) ? $d['checks'] : [],
                'development' => array_values(array_filter($d['development'], 'is_array')), 'runtime' => array_values(array_filter($d['runtime'], 'is_array')),
                'workflows' => array_values(array_filter($d['workflows'], 'is_array'))];
    }

    // ---- the rules ----------------------------------------------------------------------------------------------

    /**
     * A development gauge's stage (gauge-lifecycle.md §3, "the rule, in order"): it sits at the earliest stage any item is
     * still in. A criterion with no check yet is unmet (§4). Returns [stage, why] (why: the item and criterion holding it).
     */
    public static function devStage(array $items, bool $signed): array
    {
        if (!$signed) return ['Planning', 'configuration awaiting Jason\'s sign-off'];
        if (!$items) return ['Planning', 'no configuration yet'];
        $steps = [['Phased Development', ['built']], ['Audit & Review', ['checked', 'reviewed']], ['Implementation', ['live', 'proved']]];
        foreach ($steps as [$stage, $need]) {
            foreach ($items as $it) {
                $crit = (array) ($it['criteria'] ?? self::CRITERIA); $met = (array) ($it['met'] ?? []);
                foreach ($need as $c) if (in_array($c, $crit, true) && !in_array($c, $met, true))
                    return [$stage, (string) ($it['name'] ?? 'an item') . ': not ' . $c . (($c === 'checked' && !empty($it['no_check'])) ? ' (no check yet: ' . implode(', ', (array) $it['no_check']) . ')' : '')];
            }
        }
        return ['Monitoring', 'every configured criterion met'];
    }

    /** Weighted criteria met over configured (§1, §4: weight 1 by default). [met, configured]. */
    public static function devScore(array $items): array
    {
        $m = 0.0; $n = 0.0;
        foreach ($items as $it) {
            $w = max(0.0, (float) ($it['weight'] ?? 1)); $crit = (array) ($it['criteria'] ?? self::CRITERIA);
            $n += $w * count($crit); $m += $w * count(array_intersect($crit, (array) ($it['met'] ?? [])));
        }
        return [$m, $n];
    }

    /**
     * A runtime gauge's stage (§7): Planning with none of the four; Phased Development while the probe isn't built; Audit &
     * Review while its target isn't set or its check isn't passing; Implementation while its alarm isn't wired; Monitoring
     * with all four. Returns [stage, why].
     */
    public static function rtStage(array $s): array
    {
        $p = !empty($s['probe']); $t = !empty($s['target']); $c = !empty($s['check']); $a = !empty($s['alarm']);
        if ($p && $t && $c && $a) return ['Monitoring', 'all four set up'];
        if (!$p && !$t && !$c && !$a) return ['Planning', 'none of the four set up'];
        if (!$p) return ['Phased Development', 'its probe isn\'t built'];
        if (!$t) return ['Audit & Review', 'its target isn\'t set'];
        if (!$c) return ['Audit & Review', 'its check isn\'t passing'];
        return ['Implementation', 'its alarm isn\'t wired to the board'];
    }

    public static function setupPct(array $s): float
    {
        $n = 0; foreach (array_keys(self::SETUP) as $k) if (!empty($s[$k])) $n++;
        return $n / count(self::SETUP) * 100;
    }

    // ---- the three lists ----------------------------------------------------------------------------------------

    /** The 16 development gauges (contract: id, title, stage, score, signed, items, criteria, met, preview, no_check, paths). */
    public static function development(PDO $pdo): array
    {
        $c = self::config($pdo);
        if (empty($c['ok'])) {
            $out = [];
            foreach (self::DEV_FALLBACK as $g) $out[] = ['id' => self::slug($g), 'title' => $g, 'stage' => 'Planning', 'k' => 'planning', 'score' => null, 'signed' => false,
                'items' => 0, 'criteria' => 0, 'met' => 0, 'preview' => null, 'preview_stage' => null, 'no_check' => [], 'paths' => ['workflows' => [], 'core' => [], 'registered' => []],
                'list' => [], 'checked' => false, 'text' => self::NOT_CHECKED, 'why' => self::NOT_CHECKED];
            return $out;
        }
        $out = [];
        foreach ($c['development'] as $g) {
            $list = array_values(array_filter((array) ($g['list'] ?? []), 'is_array'));
            $signed = !empty($g['signed']);
            [$m, $n] = self::devScore($list);
            [$stage, $why] = self::devStage($list, $signed);
            [$pst] = self::devStage($list, true);
            $pv = $n > 0 ? round($m / $n * 100, 1) : 0.0;
            $id = (string) ($g['id'] ?? self::slug((string) ($g['title'] ?? '')));
            $text = $signed ? ($stage === 'Monitoring' ? 'every configured criterion met' : (int) $m . ' of ' . (int) $n . ' criteria met')
                : ($list ? 'configuration drafted: ' . count($list) . ' items, ' . (int) $n . ' criteria, awaiting sign-off' : 'no configuration yet');
            $out[] = ['id' => $id, 'title' => (string) ($g['title'] ?? $id), 'side' => (string) ($g['side'] ?? ''), 'stage' => $stage, 'k' => self::STAGES[$stage],
                // Planning shows 0 until the sign-off (§3: "It starts at 0"); after it, criteria met over configured
                'score' => $signed ? $pv : 0.0, 'signed' => $signed, 'signed_at' => $g['signed_at'] ?? null,
                'items' => count($list), 'criteria' => (int) $n, 'met' => (int) $m, 'preview' => $pv, 'preview_stage' => $pst,
                'no_check' => array_values(array_map('strval', (array) ($g['no_check'] ?? []))), 'paths' => self::paths($pdo, (array) ($g['paths'] ?? [])),
                'list' => $list, 'out_of_scope' => (array) ($g['out_of_scope'] ?? []), 'checked' => true, 'text' => $text, 'why' => $why,
                'moved_back' => self::movedBack($pdo, 'dev:' . $id, $stage, $why)];
        }
        return $out;
    }

    /** The 23 runtime gauges (the contract's fields, plus setup_why, target_text, alarms and unmapped for the drawer). */
    public static function runtime(PDO $pdo): array
    {
        $c = self::config($pdo);
        $rows = !empty($c['ok']) ? $c['runtime'] : array_map(function ($id, $t) { return ['id' => $id, 'title' => $t, '_none' => true]; }, array_keys(self::RT_FALLBACK), self::RT_FALLBACK);
        $al = self::alarms($pdo);
        $out = [];
        foreach ($rows as $g) {
            $id = (string) ($g['id'] ?? '');
            $setup = ['probe' => false, 'target' => false, 'check' => false, 'alarm' => false];
            foreach ($setup as $k => $_) $setup[$k] = !empty($g['setup'][$k]);
            $why = (array) ($g['setup_why'] ?? []);
            $target = isset($g['target']) && $g['target'] !== '' ? (string) $g['target'] : null;   // not approved: null ("target: set by Jason")
            $r = ['reading' => null, 'reading_text' => self::NOT_CHECKED, 'at' => null, 'live' => false, 'band' => 'none'];
            if (empty($g['_none'])) {
                $live = self::reading($pdo, $id);
                if ($live !== null) $r = $live;
            }
            // a live reading is the check on its clock: met or not (§7; §3: a criterion that stops holding moves it back)
            if ($r['live'] && $r['reading'] !== null) { $setup['check'] = $r['band'] === 'good'; $why['check'] = $r['reading_text'] . ($r['band'] === 'good' ? '' : ': below its target'); }
            // an alarm-wired gauge's parts tripped: red, and its check isn't passing (03 §27: red on a trip)
            $trips = 0; $warn = 0; $first = '';
            foreach ((array) ($g['procs'] ?? []) as $p) { $trips += $al[$p]['trip'] ?? 0; $warn += $al[$p]['warning'] ?? 0; if ($first === '' && !empty($al[$p]['trip'])) $first = (string) $al[$p]['title']; }
            if ($setup['alarm'] && $trips > 0) { $r['band'] = 'poor'; $setup['check'] = false; $why['check'] = 'a trip on its parts: ' . $first; }
            [$stage, $swhy] = self::rtStage($setup);
            $mon = $stage === 'Monitoring';
            $out[] = ['id' => $id, 'box' => (string) ($g['box'] ?? ''), 'title' => (string) ($g['title'] ?? self::RT_FALLBACK[$id] ?? $id), 'measure' => (string) ($g['measure'] ?? ''),
                'target' => $target, 'target_text' => $target ?? self::NO_TARGET, 'target_proposed' => $g['target_proposed'] ?? null, 'unit' => (string) ($g['unit'] ?? '%'),
                'reading' => $r['reading'], 'reading_text' => $r['reading_text'], 'at' => $r['at'], 'live' => $r['live'], 'band' => $r['band'],
                'stage' => $stage, 'k' => $mon ? $r['band'] : self::STAGES[$stage], 'why' => $swhy, 'setup' => $setup, 'setup_why' => $why,
                // below Monitoring the score is the setup %; at it, the reading against its target (§7)
                'score' => $mon ? $r['reading'] : self::setupPct($setup),
                'paths' => self::paths($pdo, (array) ($g['paths'] ?? [])), 'fix' => self::fix((array) ($g['fix'] ?? [])),
                'no_check' => array_values(array_map('strval', (array) ($g['no_check'] ?? []))), 'needs_probe' => $g['needs_probe'] ?? null,
                'unmapped' => (array) ($g['unmapped'] ?? []), 'alarms' => ['trip' => $trips, 'warning' => $warn], 'checked' => empty($g['_none']),
                'moved_back' => empty($g['_none']) ? self::movedBack($pdo, 'rt:' . $id, $stage, $swhy) : null];
        }
        return $out;
    }

    /** W0–W14 (contract: wf, name, kind gauge|list|not_built, stage, score, setup, measure, target, reading_text, band). */
    public static function workflows(PDO $pdo): array
    {
        $c = self::config($pdo);
        $rows = [];
        if (!empty($c['ok'])) $rows = $c['workflows'];
        else { try { require_once __DIR__ . '/Automation.php'; foreach (Automation::WORKFLOWS as $w => $d) $rows[] = ['wf' => $w, 'name' => $d[0], '_none' => true]; } catch (Throwable $x) {} }
        $al = self::alarms($pdo);
        $pauses = [];
        try { require_once __DIR__ . '/Automation.php'; $pauses = Automation::pauses($pdo); } catch (Throwable $x) {}
        $out = [];
        foreach ($rows as $g) {
            $w = (string) ($g['wf'] ?? '');
            $kind = (string) ($g['kind'] ?? 'gauge');
            $row = ['wf' => $w, 'name' => (string) ($g['name'] ?? ''), 'kind' => $kind, 'href' => '/admin/config/control/wf-' . $w, 'measure' => (string) ($g['measure'] ?? ''),
                    'target' => isset($g['target']) && $g['target'] !== '' ? (string) $g['target'] : null, 'reading' => null, 'reading_text' => self::NOT_CHECKED, 'band' => 'none',
                    'at' => null, 'live' => false, 'checked' => empty($g['_none']), 'feeds' => (array) ($g['feeds'] ?? []),
                    'core' => self::paths($pdo, ['core' => (array) ($g['core'] ?? [])])['core'], 'registered' => self::paths($pdo, ['registered' => (array) ($g['registered'] ?? [])])['registered']];
            if (!empty($g['_none'])) { $out[] = $row + ['stage' => null, 'k' => 'none', 'score' => null, 'setup' => null, 'setup_why' => null]; continue; }
            if ($kind === 'list') {
                // W13: a list, not a gauge: people write stories, nothing runs to measure (workflows-page.md §5)
                $out[] = ['stage' => null, 'k' => 'none', 'score' => null, 'setup' => null, 'setup_why' => null] + array_merge($row, ['reading_text' => 'a list, not a gauge: people write']);
                continue;
            }
            $setup = []; foreach (array_keys(self::SETUP) as $k) $setup[$k] = !empty($g['setup'][$k]);
            $why = (array) ($g['setup_why'] ?? []);
            // its state from the engine's holds (decisions/0175: a trip places the system's pause): the workflow, or its tasks
            $hold = $pauses['wf:' . $w] ?? null;
            $heldTasks = array_values(array_filter((array) ($g['tasks'] ?? []), function ($t) use ($pauses) { return isset($pauses['task:' . $t]); }));
            $a = $al['wf:' . $w] ?? [];
            if ($hold) { $row['reading_text'] = 'Paused by the system (a trip): ' . rtrim((string) $hold['why'], '. '); $row['band'] = 'poor'; }
            elseif ($heldTasks) { $row['reading_text'] = 'Partly held: ' . implode(', ', $heldTasks) . ' paused by the system'; $row['band'] = 'poor'; }
            elseif (!empty($a['trip'])) { $row['reading_text'] = 'Trip: ' . $a['title']; $row['band'] = 'poor'; }
            elseif (!empty($a['warning'])) { $row['reading_text'] = 'Warning: ' . $a['title']; $row['band'] = 'below'; }
            elseif ($al !== null) $row['reading_text'] = 'No open alarm; its measure isn\'t read yet';
            if ($al !== null) { $row['live'] = true; $row['at'] = time(); }
            if ($row['band'] === 'poor') { $setup['check'] = false; $why['check'] = $row['reading_text']; }
            [$stage, $swhy] = self::rtStage($setup);
            $out[] = $row + ['stage' => $stage, 'k' => $stage === 'Monitoring' ? $row['band'] : self::STAGES[$stage], 'why' => $swhy,
                             // no workflow's measure is read on the site yet: at Monitoring its score would be that reading (§7)
                             'score' => $stage === 'Monitoring' ? null : self::setupPct($setup), 'setup' => $setup, 'setup_why' => $why,
                             'moved_back' => self::movedBack($pdo, 'wf:' . $w, $stage, $swhy)];
        }
        return $out;
    }

    // ---- readings, paths, fix, the drop log ---------------------------------------------------------------------

    /** A gauge's live reading on the site, or null where the site has none. */
    private static function reading(PDO $pdo, string $id): ?array
    {
        try {
            if ($id === 'o-told') {
                // "each trip's email and push", target "every trip, at once": the trips open now or tripped in 24 hours, and
                // how many reached a person (mailed_at: ControlAlarms::mail through the one dispatcher)
                require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ensure($pdo);
                $r = $pdo->query("SELECT COUNT(*) n, SUM(mailed_at IS NOT NULL) m FROM control_alarms WHERE level = 'trip'
                                  AND (cleared_at IS NULL OR COALESCE(tripped_at, first_at) >= UTC_TIMESTAMP() - INTERVAL 24 HOUR)")->fetch(PDO::FETCH_ASSOC);
                $n = (int) ($r['n'] ?? 0); $m = (int) ($r['m'] ?? 0);
                if ($n === 0) return ['reading' => null, 'reading_text' => 'No trip in the last 24 hours: nothing to send', 'at' => time(), 'live' => true, 'band' => 'none'];
                $v = round($m / $n * 100, 1);
                return ['reading' => $v, 'reading_text' => $m . ' of ' . $n . ' trips sent', 'at' => time(), 'live' => true, 'band' => $m === $n ? 'good' : 'below'];
            }
            if ($id === 'o-ir') {
                // crossover.md's one measure: "IRs closed with a confirmed cause out of IRs raised"
                require_once __DIR__ . '/ControlIncidents.php'; ControlIncidents::ensure($pdo);
                $r = $pdo->query("SELECT COUNT(*) n, SUM(closed_at IS NOT NULL AND root_cause_state = 'confirmed') m, SUM(closed_at IS NULL) o FROM control_incidents")->fetch(PDO::FETCH_ASSOC);
                $n = (int) ($r['n'] ?? 0); $m = (int) ($r['m'] ?? 0); $o = (int) ($r['o'] ?? 0);
                if ($n === 0) return ['reading' => null, 'reading_text' => 'No Incident Report yet', 'at' => time(), 'live' => true, 'band' => 'none'];
                return ['reading' => round($m / $n * 100, 1), 'reading_text' => $m . ' of ' . $n . ' closed with a confirmed cause' . ($o ? ' · ' . $o . ' open' : ''),
                        'at' => time(), 'live' => true, 'band' => $m === $n ? 'good' : 'below'];
            }
        } catch (Throwable $x) { error_log('control gauges reading ' . $id . ': ' . $x->getMessage()); }
        return null;
    }

    /** Open alarms by process (one query a request), or null when the alarm list can't be read. */
    private static function alarms(PDO $pdo): ?array
    {
        if (self::$alarmsRead) return self::$alarms;
        self::$alarmsRead = true;
        $out = [];
        try {
            require_once __DIR__ . '/ControlAlarms.php'; ControlAlarms::ensure($pdo);
            foreach ($pdo->query("SELECT proc, level, title FROM control_alarms WHERE cleared_at IS NULL ORDER BY FIELD(level, 'trip', 'warning', 'notice'), last_at DESC")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $p = (string) $a['proc']; $out[$p] = $out[$p] ?? ['trip' => 0, 'warning' => 0, 'notice' => 0, 'title' => (string) $a['title']];
                if (isset($out[$p][$a['level']])) $out[$p][$a['level']]++;
            }
        } catch (Throwable $x) { return self::$alarms = null; }
        self::$alarms = $out;
        return $out;
    }

    /** The three drill paths, each [id, name, state, share|null, href], the state from the open alarms where there is one. */
    private static function paths(PDO $pdo, array $p): array
    {
        $al = self::alarms($pdo) ?? [];
        $proc = ['workflows' => 'wf:', 'registered' => 'svc:'];
        $core = ['db' => ['site:db'], 'jobs' => ['site:sched'], 'workers' => ['wk:crawler', 'wk:w0-indexer'], 'video' => ['wk:video-worker'], 'dispatcher' => ['site:pulse']];
        $out = [];
        foreach (['workflows', 'core', 'registered'] as $k) {
            $out[$k] = [];
            foreach ((array) ($p[$k] ?? []) as $r) {
                if (!is_array($r) || !isset($r[0], $r[1])) continue;
                $ps = $k === 'core' ? ($core[(string) $r[0]] ?? []) : [$proc[$k] . $r[0]];
                $st = null;
                foreach ($ps as $x) if (!empty($al[$x]['trip'])) { $st = 'Trip: ' . $al[$x]['title']; break; }
                if ($st === null) foreach ($ps as $x) if (!empty($al[$x]['warning'])) { $st = 'Warning: ' . $al[$x]['title']; break; }
                $href = isset($r[4]) && is_string($r[4]) && preg_match('#^(/(?!/)|https://)#', $r[4]) ? $r[4] : '';
                $out[$k][] = [(string) $r[0], (string) $r[1], $st, isset($r[3]) && $r[3] !== null ? (string) $r[3] : null, $href];
            }
        }
        if (isset($p['needs'])) $out['needs'] = (array) $p['needs'];
        if (isset($p['tasks'])) $out['tasks'] = (array) $p['tasks'];
        return $out;
    }

    /** Fix it links: this site or https only; one the map can't give stays as its words with no link. */
    private static function fix(array $f): array
    {
        $out = [];
        foreach ($f as $x) {
            if (!is_array($x) || !isset($x[0])) continue;
            $h = isset($x[1]) && is_string($x[1]) && preg_match('#^(/(?!/)|https://)#', $x[1]) ? $x[1] : null;
            $out[] = [(string) $x[0], $h];
        }
        return $out;
    }

    /**
     * A stage that moved back since the last look (§3: "the stage moves back to where that criterion belongs, with its
     * reason, and the drop is logged"): logged once in the logbook; returns ['from', 'to', 'why'] or null. The last stage
     * seen is kept only to notice the drop; the stage shown is always computed.
     */
    private static function movedBack(PDO $pdo, string $key, string $stage, string $why): ?array
    {
        try {
            $order = array_keys(self::STAGES);
            $seen = PageCache::get($pdo, 'gauges:stage:' . $key);
            $prev = is_array($seen) ? (string) ($seen['stage'] ?? '') : '';
            if ($prev === $stage) return is_array($seen['back'] ?? null) ? $seen['back'] : null;   // still where it dropped to: its reason stays
            $back = null;
            if ($prev !== '' && in_array($prev, $order, true) && array_search($stage, $order, true) < array_search($prev, $order, true)) {
                $back = ['from' => $prev, 'to' => $stage, 'why' => $why, 'at' => time()];
                require_once __DIR__ . '/ControlLog.php';
                ControlLog::add($pdo, 'control', 'gauge:' . substr($key, 0, 50), 'Gauge moved back from ' . $prev . ' to ' . $stage, 'the system', $why);
            }
            PageCache::put($pdo, 'gauges:stage:' . $key, 365 * 86400, ['stage' => $stage, 'back' => $back, 'at' => time()]);
            return $back;
        } catch (Throwable $x) { return null; }
    }

    public static function slug(string $g): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($g)), '-');
    }
}
