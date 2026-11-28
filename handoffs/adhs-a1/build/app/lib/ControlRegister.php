<?php
// app/lib/ControlRegister.php — the unit register (decisions/0171 §3; Jason, 2026-11-18: "Organized by how an operator
// can see and understand each system and everything within its process"). The System Control Board, Start the system and
// the pre-flight read it, so each one sees the same systems and processes, and a new workflow, task, worker, service or
// job appears by itself. (It is "the unit register", not the service register: that one is Operations::register.)
//
// What is already declared elsewhere is read, never copied here: the workflows (Automation::WORKFLOWS) and their site
// jobs (Automation::JOBS), Start's order and tasks (SystemStart::ORDER, TASKS), the source map (Preflight::NEEDS), the
// outside companies (OutsideServices::COMPANIES, CONTROLS), every scheduled job (Scheduler::JOBS) and the crawler's capture
// tasks with their workflow (CrawlService::tasks(), live from crawl_capture_tasks). Only what nothing declared before is
// here: the workers, and each endpoint the workers call, with the crawler tasks it serves and the tables its answers
// land in. An endpoint on the server that isn't listed here shows as unclaimed (the pre-flight's "Every endpoint
// claimed" line), and the zip check refuses a new one that doesn't name its process.
//
// A process's route: its sources (the source map) → the worker or site job that carries it → the endpoints → the tables
// → where it ends up. The pre-flight's "Routes" group tests each hop is there (ControlRegister::route); Release 2 adds
// what passes along each hop (the ledger).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Automation.php';

final class ControlRegister
{
    /** The four systems, in the order the board shows them. */
    public const SYSTEMS = [
        'engine' => 'The Knowledge Engine',
        'workers' => 'Workers',
        'services' => 'Outside services',
        'site' => 'The site itself',
    ];

    /** The workers on Railway: key => [name, where it runs, the endpoints it calls itself (not a task's), its check-in line]. */
    public const WORKERS = [
        'w0-indexer' => ['W0 Indexer', 'Railway: the w0-indexer service, always on', ['crawl/index'], 'rw:beat'],
        'crawler' => ['The crawler', 'Railway: the traversence service, every 5 minutes (its schedule set by Start the system)', ['crawl/service', 'crawl/usage'], 'rw:crawler'],
        'video-worker' => ['The video worker', 'Railway: the video-worker service (720p and 1080p copies of posted videos)', ['video/jobs', 'video/results'], ''],
    ];

    /**
     * Each endpoint the workers call (under website/api/): [what it carries, the crawler tasks it serves ('@<worker>' for
     * the worker's own calls, 'W0' for the indexer), the tables its answers land in]. Checked against the worker's code
     * (workers/crawler/crawler.py HANDLERS, indexer.py) and each endpoint's library, 2026-11-18.
     */
    public const ENDPOINTS = [
        'crawl/index' => ['W0 Indexer asks for addresses to read and sends back what it found', ['W0'], ['crawl_frontier', 'crawl_batches', 'crawl_junk']],
        'crawl/service' => ['The crawler\'s plan for a run, and its report', ['@crawler'], ['crawl_runs', 'crawl_run_items', 'crawl_service_calls', 'crawl_costs', 'crawl_requests']],
        'crawl/usage' => ['The crawler\'s web search and Google allowance, asked and reported', ['@crawler'], ['search_index_state']],
        'crawl/jobs' => ['Towns and kinds to look for, handed to the crawler', ['coverage'], ['crawl_jobs']],
        'crawl/results' => ['What the crawler found for a job, staged for Review', ['coverage'], ['crawl_jobs', 'crawler_raw_index', 'crawler_sources']],
        'crawl/verify' => ['A second look at listings waiting for Review', ['second_look'], ['crawler_raw_index']],
        'crawl/targets' => ['What people searched for, as places to look', ['targets'], ['crawl_targets']],
        'crawl/refresh' => ['Live listings re-read from their own websites', ['refresh'], ['entity_metadata', 'crawler_raw_index']],
        'crawl/identity' => ['Who a listing is: its identity built from sources', ['identity'], ['identity_jobs', 'identity_facts', 'identity_fact_sources']],
        'crawl/geocode' => ['Map points for street addresses', ['geocode'], ['entities']],
        'crawl/places' => ['Google\'s check of a listing', ['places'], ['entity_metadata', 'suggestions']],
        'crawl/sources' => ['Sites we read regularly, and what they name', ['source'], ['reference_sources', 'source_facts', 'crawl_pages']],
        'crawl/commoncrawl' => ['Common Crawl\'s archive, read for us', ['commoncrawl'], ['reference_sources', 'source_facts', 'crawl_pages']],
        'crawl/heritage' => ['Library of Congress records for each guide\'s place', ['heritage'], ['heritage_facts', 'heritage_places', 'heritage_sources']],
        'crawl/guides' => ['Official pages read into how-to guides', ['howto'], ['how_guides']],
        'crawl/learn' => ['Search that learns: gaps and the sites that fill them', ['learn'], ['search_learned', 'situation_gaps', 'situation_sources']],
        'crawl/npi' => ['The monthly NPI Registry load', ['npi_bulk'], ['npi_loads', 'npi_orgs']],
        'crawl/irs' => ['The monthly IRS exempt-organization load', ['irs_bulk'], ['irs_loads', 'irs_orgs']],
        'crawl/rec' => ['The monthly Recreation.gov load', ['rec_bulk'], ['rec_loads', 'rec_places']],
        'crawl/landmarks' => ['The monthly natural landmarks load', ['landmarks_bulk'], ['landmark_loads', 'landmarks']],
        'crawl/w14' => ['W14: an authority source\'s robots.txt and terms read for a person to decide', ['w14_terms'], ['authority_sources']],
        'crawl/loads' => ['The open-dataset loads: Overture, Foursquare, OpenStreetMap from Geofabrik', ['overture_bulk', 'foursquare_bulk', 'geofabrik_bulk'], ['open_loads', 'entity_metadata']],
        'video/jobs' => ['Posted videos waiting for their copies', ['@video-worker'], ['video_jobs']],
        'video/results' => ['The finished 720p and 1080p copies', ['@video-worker'], ['video_jobs', 'member_post_media']],
    ];

    /**
     * The site's duties (0171 §5's note, Jason 2026-11-19: "Critical is that our site can perform its duties. Search, page
     * load, payment processing, database connections, system critical processing or procedures"). A part is critical when
     * its failure stops one of them: its first failure trips at once while the system keeps trying.
     */
    public const DUTIES = [
        'page' => ['Page load', 'The site\'s pages open: its host, its settings, its address.'],
        'search' => ['Search', 'Search answers: its places, indexes and services.'],
        'payments' => ['Payments', 'Payments go through: the gateway, its webhooks, checkout and plans.'],
        'database' => ['Database connections', 'The site reaches its databases, and their updates run.'],
        'procedures' => ['System-critical procedures', 'Sign-in, releases (inbox, staging, publish, migrations), the scheduler, the board\'s watch, system email.'],
    ];

    /**
     * The parts that carry a duty, by default (changed on Thresholds & gates; each one's reason, for the release summary).
     * Not critical by themselves (Jason): the crawler, W0, the video worker, enrichment, Railway as a host.
     */
    public const CRITICAL = [
        'svc:bluehost' => 'page',      // the web server, PHP, the router: every page
        'site:env' => 'page',          // the site's settings: without them nothing loads
        'svc:cloudflare' => 'page',    // unsure: in front of the site's address (DNS); flagged so a failure is seen at once
        'site:db' => 'database',
        'site:dbup' => 'database',     // database updates: a failed one holds the rest (and a release's migrations)
        'svc:quickbooks' => 'payments',
        'job:billing_tick' => 'payments',   // plans and renewals
        'svc:github' => 'procedures',  // releases come through git, and the sync
        'site:sched' => 'procedures',  // the scheduler: every site job
        'job:control_tick' => 'procedures',  // the board's own watch
        'job:releases_tick' => 'procedures',
        'job:staging_publish_tick' => 'procedures',
        'svc:aws' => 'procedures',     // system email (SES): sign-in links, codes and notices
        'site:search' => 'search',     // ControlSensor: a test search every 5 minutes, and visitors' server errors on search's paths
        'site:signin' => 'procedures', // ControlSensor: visitors' server errors on the sign-in page and api/auth
        'site:payments' => 'payments', // ControlSensor: checkout's and QuickBooks' paths, and QuickBooks' token
    ];

    /** A part's duty ('' not critical): its saved choice, else its default. */
    public static function duty(PDO $pdo, string $proc): string
    {
        $m = self::dutyMap($pdo);
        return array_key_exists($proc, $m) ? (string) $m[$proc] : '';
    }

    /** Every part's duty: the defaults with what's saved over them ('' saved means "not critical"). */
    public static function dutyMap(PDO $pdo): array
    {
        $m = self::CRITICAL;
        try { require_once __DIR__ . '/SearchIndex.php'; $s = json_decode(SearchIndex::state($pdo, 'control:duties') ?: '[]', true); if (is_array($s)) foreach ($s as $k => $v) $m[(string) $k] = isset(self::DUTIES[$v]) ? (string) $v : ''; } catch (Throwable $x) {}
        return array_filter($m, fn ($v) => $v !== '');
    }

    /** Sets a part's duty ('' not critical), kept with who and when in the logbook. */
    public static function setDuty(PDO $pdo, string $proc, string $duty, string $who): void
    {
        if ($duty !== '' && !isset(self::DUTIES[$duty])) throw new InvalidArgumentException('That isn\'t one of the site\'s duties.');
        if (!isset(self::processes($pdo)[$proc])) throw new InvalidArgumentException('That part isn\'t on the board.');
        require_once __DIR__ . '/SearchIndex.php';
        $s = json_decode(SearchIndex::state($pdo, 'control:duties') ?: '[]', true); $s = is_array($s) ? $s : [];
        $was = self::duty($pdo, $proc);
        if ((self::CRITICAL[$proc] ?? '') === $duty) unset($s[$proc]); else $s[$proc] = $duty;
        SearchIndex::setState($pdo, 'control:duties', json_encode($s));
        try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, 'control', $proc, $duty === '' ? 'Not critical now' . ($was !== '' ? ' (was ' . self::DUTIES[$was][0] . ')' : '') : 'Critical: ' . self::DUTIES[$duty][0], $who); } catch (Throwable $x) {}
    }

    private static ?array $tasks = null;

    /** The crawler's capture tasks, live: task => workflow ('' when the crawler's tables aren't there yet). */
    public static function taskWorkflows(PDO $pdo): array
    {
        if (self::$tasks !== null) return self::$tasks;
        $out = [];
        try {
            require_once __DIR__ . '/crawler/CrawlService.php';
            if (CrawlService::ready($pdo)) foreach (CrawlService::tasks($pdo) as $k => $r) $out[(string) $k] = (string) ($r['wf'] ?? '');
        } catch (Throwable $x) { error_log('ControlRegister::taskWorkflows: ' . $x->getMessage()); }
        return self::$tasks = $out;
    }

    public static function forget(): void { self::$tasks = null; }

    /** Every process, by system: id => [system, name]. Ids are the pre-flight's where they exist (wf:, t:, svc:, site:). */
    public static function processes(PDO $pdo): array
    {
        require_once __DIR__ . '/SystemStart.php';
        require_once __DIR__ . '/OutsideServices.php';
        require_once __DIR__ . '/Scheduler.php';
        $out = [];
        foreach (Automation::WORKFLOWS as $wf => $w) $out['wf:' . $wf] = ['engine', $wf . ' ' . $w[0]];
        foreach (SystemStart::TASKS as $t => $d) if ($t !== 'W14') $out['t:' . $t] = ['engine', $d[0]];
        foreach (self::WORKERS as $k => $w) $out['wk:' . $k] = ['workers', $w[0]];
        foreach (OutsideServices::COMPANIES as $k => $c) $out['svc:' . $k] = ['services', (string) $c[0]];
        foreach (['site:db' => 'The databases', 'site:env' => 'The site\'s settings (.env)', 'site:dbup' => 'Database updates', 'site:sched' => 'The scheduler', 'site:staging' => 'Staging',
            'site:search' => 'Search', 'site:signin' => 'Sign-in', 'site:payments' => 'Checkout and payments', 'site:pulse' => 'Pulse'] as $k => $n) $out[$k] = ['site', $n];   // site:pulse: its gathering time (2026-11-19)   // the last three: ControlSensor's (0171 §5's note)
        foreach (Scheduler::JOBS as $j => $d) if (!isset(Automation::JOBS[$j])) $out['job:' . $j] = ['site', (string) $d[0]];   // a workflow's jobs are under its workflow
        return $out;
    }

    /**
     * One workflow's parts: its site jobs, crawler tasks, needs (the source map), services, workers, endpoints, the tables
     * its endpoints write ('writes') and the copies of ours its needs read ('reads').
     */
    public static function of(PDO $pdo, string $wf): array
    {
        require_once __DIR__ . '/Preflight.php';
        require_once __DIR__ . '/SystemStart.php';
        require_once __DIR__ . '/OutsideServices.php';
        $jobs = array_keys(array_filter(Automation::JOBS, fn ($w) => in_array($wf, $w, true)));
        $tasks = array_keys(array_filter(self::taskWorkflows($pdo), fn ($w) => $w === $wf));
        $needs = array_keys(array_filter(Preflight::NEEDS, fn ($n) => in_array($wf, $n[2], true)));
        $svc = []; $reads = []; $lines = [];
        foreach ($needs as $k) foreach (Preflight::NEEDS[$k][3] as $src) {
            if ($src[0] === 'svc') $svc[$src[1]] = true;
            elseif ($src[0] === 'ours') $reads[$src[1]] = true;
            elseif ($src[0] === 'line') $lines[$src[1]] = true;
        }
        foreach (SystemStart::TASKS as $d) if ($d[1] === $wf) foreach ($d[2] as $k) $svc[$k] = true;
        foreach (OutsideServices::CONTROLS as $k => $c) if (array_intersect($tasks, (array) ($c['tasks'] ?? [])) || array_intersect($jobs, (array) ($c['jobs'] ?? []))) $svc[$k] = true;
        $eps = []; $writes = []; $workers = [];
        foreach (self::ENDPOINTS as $e => [, $for, $tables]) {
            if (!in_array($wf, $for, true) && !array_intersect($tasks, $for)) continue;
            $eps[] = $e;
            foreach ($tables as $t) $writes[$t] = true;
        }
        if ($wf === 'W0') $workers[] = 'w0-indexer';
        if ($tasks) { $workers[] = 'crawler'; foreach (self::WORKERS['crawler'][2] as $e) if (!in_array($e, $eps, true)) $eps[] = $e; }
        return ['jobs' => $jobs, 'tasks' => $tasks, 'needs' => $needs, 'services' => array_keys($svc), 'workers' => $workers,
                'endpoints' => $eps, 'writes' => array_keys($writes), 'reads' => array_keys($reads), 'lines' => array_keys($lines)];
    }

    /**
     * The pre-flight lines a line depends on, to be tested fresh before it (Start's confirm step, decisions/0171 §15.1):
     * outside services and worker lines first, then the source map's needs that read them.
     */
    public static function dependsOn(PDO $pdo, string $id): array
    {
        require_once __DIR__ . '/Preflight.php';
        require_once __DIR__ . '/SystemStart.php';
        if ($id === 't:W14') $id = 'wf:W14';
        $first = []; $then = [];
        if (str_starts_with($id, 'wf:')) {
            $p = self::of($pdo, substr($id, 3));
            foreach ($p['needs'] as $k) foreach (Preflight::NEEDS[$k][3] as $src) {
                if ($src[0] === 'svc') $first['svc:' . $src[1]] = true;
                elseif ($src[0] === 'line') $first[$src[1]] = true;
            }
            foreach ($p['needs'] as $k) $then['nd:' . $k] = true;
        } elseif (str_starts_with($id, 't:') && isset(SystemStart::TASKS[substr($id, 2)])) {
            foreach (SystemStart::TASKS[substr($id, 2)][2] as $k) $first['svc:' . $k] = true;
        }
        return array_merge(array_keys($first), array_keys($then));
    }

    /** The endpoints on the server that no process claims (website/api/crawl and website/api/video). */
    public static function unclaimed(): array
    {
        $root = dirname(__DIR__, 2) . '/website/api/';
        $out = [];
        foreach (['crawl', 'video'] as $dir) {
            foreach ((array) glob($root . $dir . '/*.php') as $f) {
                $e = $dir . '/' . basename((string) $f, '.php');
                if (basename((string) $f) === '_auth.php' || isset(self::ENDPOINTS[$e])) continue;
                $out[] = $e;
            }
        }
        return $out;
    }

    /** Does a table answer (in whichever database it lives: Database::t)? */
    private static function tableThere(PDO $pdo, string $t): bool
    {
        try { $pdo->query('SELECT 1 FROM ' . Database::t($t) . ' LIMIT 0'); return true; } catch (Throwable $x) { return false; }
    }

    /**
     * A process's route, hop by hop, each checked to be there: [state, words, what was tested, fixes] like a pre-flight
     * line. A missing endpoint or table is red; a worker that hasn't checked in yet waits. It holds nothing by itself:
     * Release 2 measures each hop, Release 3 raises its alarm (decisions/0171 §9, §15).
     */
    public static function route(PDO $pdo, string $id): array
    {
        require_once __DIR__ . '/Preflight.php';
        require_once __DIR__ . '/Scheduler.php';
        $root = dirname(__DIR__, 2);
        $res = Preflight::results($pdo);
        $bad = []; $wait = []; $hops = [];
        $mark = fn (bool $ok) => $ok ? '✓' : '✗';
        if (str_starts_with($id, 'rt:wk:')) {
            $k = substr($id, 6); $w = self::WORKERS[$k] ?? null;
            if (!$w) return ['na', 'Not a worker in the register.', '', []];
            $eps = $w[2];
            foreach (self::ENDPOINTS as $e => [, $for]) if (in_array('@' . $k, $for, true) && !in_array($e, $eps, true)) $eps[] = $e;
            if ($k === 'crawler') foreach (self::ENDPOINTS as $e => [, $for]) if (!array_filter($for, fn ($f) => $f[0] === '@' || $f === 'W0') && !in_array($e, $eps, true)) $eps[] = $e;
            $tables = [];
            foreach ($eps as $e) foreach (self::ENDPOINTS[$e][2] as $t) $tables[$t] = true;
            $parts = ['jobs' => [], 'tasks' => [], 'endpoints' => $eps, 'writes' => array_keys($tables), 'reads' => [], 'workers' => [$k]];
            $label = $w[0] . ' (' . $w[1] . ')';
        } else {
            $wf = substr($id, 6);
            $parts = self::of($pdo, $wf);
            $label = $wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? '');
            if (!$parts['jobs'] && !$parts['tasks'] && !$parts['workers']) {
                $why = $wf === 'W13' ? 'People write stories themselves: no worker or job carries it, so there is no route to test.' : 'Nothing carries it yet: no site job, crawler task or worker names ' . $wf . '.';
                return [$wf === 'W13' ? 'na' : 'warn', $why, 'Its jobs, crawler tasks and workers in the unit register', []];
            }
        }
        // the worker or site job that carries it
        foreach ($parts['workers'] as $k) {
            $line = self::WORKERS[$k][3];
            $s = $line !== '' ? (string) ($res[$line]['s'] ?? 'new') : 'na';
            if ($s === 'bad') $bad[] = self::WORKERS[$k][0] . ' (its check-in line is red)';
            elseif ($s === 'new' || $s === 'wait') $wait[] = lcfirst(self::WORKERS[$k][0]) . ' (no check-in read yet)';
            $hops[] = 'Worker: ' . self::WORKERS[$k][0] . ($line !== '' ? ' ' . ($s === 'ok' || $s === 'warn' ? '✓' : ($s === 'bad' ? '✗' : '(not checked in yet)')) : '');
        }
        if ($parts['jobs']) {
            $miss = array_values(array_filter($parts['jobs'], fn ($j) => !is_file($root . '/app/scripts/' . $j . '.php')));
            foreach ($miss as $j) $bad[] = 'the job ' . $j . '\'s script (app/scripts/' . $j . '.php)';
            $hops[] = 'Site jobs: ' . implode(', ', array_map(fn ($j) => (Scheduler::JOBS[$j][0] ?? $j) . ' ' . $mark(!in_array($j, $miss, true)), $parts['jobs']));
        }
        if ($parts['tasks']) $hops[] = 'Crawler tasks: ' . implode(', ', $parts['tasks']);
        // the endpoints it reaches
        if ($parts['endpoints']) {
            $miss = array_values(array_filter($parts['endpoints'], fn ($e) => !is_file($root . '/website/api/' . $e . '.php')));
            foreach ($miss as $e) $bad[] = 'the endpoint /api/' . $e . '.php';
            $hops[] = 'Endpoints: ' . implode(', ', array_map(fn ($e) => '/api/' . $e . '.php ' . $mark(!in_array($e, $miss, true)), $parts['endpoints']));
        }
        // the tables it writes, and the copies of ours it reads
        foreach (['writes' => 'Tables it writes', 'reads' => 'Our copies it reads'] as $key => $words) {
            if (!$parts[$key]) continue;
            $miss = array_values(array_filter($parts[$key], fn ($t) => !self::tableThere($pdo, $t)));
            foreach ($miss as $t) $bad[] = 'the table ' . $t;
            $hops[] = $words . ': ' . implode(', ', array_map(fn ($t) => $t . ' ' . $mark(!in_array($t, $miss, true)), $parts[$key]));
        }
        $s = $bad ? 'bad' : ($wait ? 'wait' : 'ok');
        $head = $bad ? 'Not complete: ' . implode('; ', $bad) . ' isn\'t there.' : ($wait ? 'Every part is there; waiting for ' . implode(', ', $wait) . '.' : 'Complete: every hop is there.');
        $fix = [];
        if ($bad && str_contains(implode(' ', $bad), 'table')) $fix[] = ['tool', 'Database updates', '/admin/config/dbupdates'];
        return [$s, $head . ' ' . implode('. ', $hops) . '. What passes along each hop is recorded in the board\'s Ledger (each worker call and the records it touched).', $label . ': each hop of its route in the unit register (app/lib/ControlRegister.php) checked to be there: the worker\'s check-in, the jobs\' scripts, the endpoints\' files, the tables', $fix];
    }

    /** The "Every endpoint claimed" line: an endpoint no process names is a hop nobody watches. */
    public static function claimedLine(): array
    {
        $u = self::unclaimed();
        return [$u ? 'bad' : 'ok', $u ? count($u) . ' endpoint' . (count($u) === 1 ? '' : 's') . ' on the server that no process names: ' . implode(', ', array_map(fn ($e) => '/api/' . $e . '.php', $u)) . '. Each needs its line in the unit register (app/lib/ControlRegister.php, ENDPOINTS), so its route is watched.'
            : 'Every endpoint the workers call (' . count(self::ENDPOINTS) . ', under /api/crawl/ and /api/video/) is named by a process in the unit register.',
            'The files under website/api/crawl/ and website/api/video/ against the unit register', []];
    }
}
