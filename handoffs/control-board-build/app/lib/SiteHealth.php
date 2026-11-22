<?php
// app/lib/SiteHealth.php — the Pulse "Site health" case (decisions/0145 Phase 4): one case, a step for each open finding
// of the site audit (the browser checks, the paths, the code checks), most paths blocked first. The findings are made by
// the audit's GitHub run in degraffics/traversence and published there as site-audit/results/findings.json; the site
// reads that public file (no key, no cost), keeps it an hour, and keeps the last good copy if GitHub can't be reached.
// Nothing here changes the site: a person can set a finding aside (with why) or mark it fixed, kept in
// site_health_marks (2027-03-03_site_health.sql); a set-aside finding comes back if its evidence changes.
// Behind the switch site_health (Switches): staff and test accounts first.
// When one of the audit's workflows hasn't run in 26 hours (AuditClock::stale, 2026-11-13), the case says so with a
// button that asks GitHub to run it, even when no finding is open.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PageCache.php';

final class SiteHealth
{
    public const SWITCH = 'site_health';
    /** where the audit publishes its findings (a public file in the docs repository) */
    public const SOURCE = 'https://raw.githubusercontent.com/degraffics/traversence/main/site-audit/results/findings.json';
    /** the audit's own pages, for the steps' links */
    public const REPO = 'https://github.com/degraffics/traversence';
    public const PATHS_PAGE = self::REPO . '/blob/main/site-audit/paths.md';
    public const PROPOSALS_PAGE = self::REPO . '/blob/main/site-audit/proposals.md';
    public const RUN_CHECKS = self::REPO . '/actions/workflows/site-checks.yml';
    /** how many findings get a step of their own (Pulse ticks steps 0–20); the rest are on the paths page */
    public const STEPS = 14;
    public const STATES = ['aside' => 'Set aside', 'fixed' => 'Said fixed'];

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM site_health_marks LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    public static function on(PDO $pdo): bool
    {
        require_once __DIR__ . '/Switches.php';
        return Switches::ready($pdo) && Switches::on($pdo, self::SWITCH);
    }

    private static function source(): string
    {
        Database::readEnv();
        $u = trim((string) (getenv('SITE_HEALTH_URL') ?: ''));   // staging, or a test, can read another copy
        return preg_match('#^https://#', $u) ? $u : self::SOURCE;
    }

    /**
     * The audit's findings: ['ok' => bool, 'at' => when the audit made them, 'run_url', 'findings' => [...], 'error' => ''].
     * Kept an hour; the last good copy is kept a month, so a GitHub hiccup shows the last findings with a note.
     */
    public static function load(PDO $pdo, bool $fresh = false): array
    {
        if ($fresh) PageCache::forget($pdo, 'sitehealth:now');
        $now = PageCache::get($pdo, 'sitehealth:now');
        if (is_array($now)) return $now;
        $got = self::fetch();
        if ($got['ok']) PageCache::put($pdo, 'sitehealth:last', 30 * 86400, $got);
        else {
            $last = PageCache::get($pdo, 'sitehealth:last');
            if (is_array($last) && $last['ok']) $got = ['error' => $got['error']] + $last;   // the last good copy, with why it's not newer
        }
        PageCache::put($pdo, 'sitehealth:now', $got['ok'] ? 3600 : 600, $got);
        return $got;
    }

    private static function fetch(): array
    {
        $bad = fn (string $why) => ['ok' => false, 'at' => '', 'run_url' => '', 'findings' => [], 'error' => $why];
        if (!function_exists('curl_init')) return $bad('This server can\'t make web requests (curl).');
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold(self::source(), 'the site health report')) !== null) return $bad($w);
        $ch = curl_init(self::source());
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Traversence site health', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code !== 200) return $bad('GitHub didn\'t answer (' . ($err !== '' ? $err : 'HTTP ' . $code) . ').');
        return self::parse((string) $body);
    }

    /** The findings file, checked field by field: only what a step shows, links only to this site or https. */
    public static function parse(string $body): array
    {
        $bad = fn (string $why) => ['ok' => false, 'at' => '', 'run_url' => '', 'findings' => [], 'error' => $why];
        $d = json_decode($body, true);
        if (!is_array($d) || !isset($d['findings']) || !is_array($d['findings'])) return $bad('The audit\'s findings file couldn\'t be read.');
        $out = [];
        foreach ($d['findings'] as $f) {
            if (!is_array($f) || !preg_match('/^f-[a-f0-9]{6,40}$/', (string) ($f['id'] ?? ''))) continue;
            $s = fn (string $k, int $n = 300) => mb_substr(trim((string) ($f[$k] ?? '')), 0, $n);
            $url = fn (string $k) => preg_match('#^(https://|/(?!/))#', (string) ($f[$k] ?? '')) ? mb_substr((string) $f[$k], 0, 500) : '';
            $out[] = ['id' => (string) $f['id'], 'sig' => $s('sig', 64), 'kind' => $s('kind', 20), 'rule' => $s('rule', 10), 'title' => $s('title', 160), 'detail' => $s('detail', 600),
                'page' => $url('page'), 'who' => array_slice(array_map('strval', (array) ($f['who'] ?? [])), 0, 12), 'paths' => array_slice(array_map('strval', (array) ($f['paths'] ?? [])), 0, 20),
                'blocks' => (int) ($f['blocks'] ?? 0), 'severity' => in_array($f['severity'] ?? '', ['critical', 'alert', 'update'], true) ? (string) $f['severity'] : 'update',
                'shot_url' => $url('shot_url'), 'proposal' => $s('proposal', 12), 'proposal_url' => $url('proposal_url'), 'verdict' => $s('verdict', 20), 'decided' => !empty($f['decided']),
                'first_seen' => $s('first_seen', 30), 'last_seen' => $s('last_seen', 30)];
        }
        return ['ok' => true, 'at' => mb_substr((string) ($d['generated_at'] ?? ''), 0, 30), 'run_url' => preg_match('#^https://github\.com/#', (string) ($d['run_url'] ?? '')) ? (string) $d['run_url'] : self::RUN_CHECKS,
                'findings' => $out, 'error' => ''];
    }

    /** finding id => ['state', 'sig', 'note', 'by', 'at'] */
    public static function marks(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        $out = [];
        try { foreach ($pdo->query('SELECT m.*, u.display_name FROM site_health_marks m LEFT JOIN users u ON u.id = m.by_user')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['finding_id']] = $r; } catch (Throwable) {}
        return $out;
    }

    /**
     * The open findings: the audit's, less those set aside (unless their evidence changed since) and those said fixed
     * (until the next audit still finds them: a run after the mark brings them back). Most paths blocked first.
     */
    public static function open(PDO $pdo, ?array $loaded = null): array
    {
        $d = $loaded ?? self::load($pdo);
        $marks = self::marks($pdo);
        $open = [];
        foreach ($d['findings'] as $f) {
            $m = $marks[$f['id']] ?? null;
            if ($m && $m['state'] === 'aside' && (string) $m['sig'] === $f['sig']) continue;
            if ($m && $m['state'] === 'fixed' && ($d['at'] === '' || strtotime($d['at']) <= strtotime((string) $m['at']))) continue;   // not checked again yet
            $open[] = $f;
        }
        $rank = ['critical' => 0, 'alert' => 1, 'update' => 2];
        usort($open, fn ($a, $b) => [-$a['blocks'], $rank[$a['severity']], $a['first_seen']] <=> [-$b['blocks'], $rank[$b['severity']], $b['first_seen']]);
        return $open;
    }

    public static function mark(PDO $pdo, string $id, string $state, string $note, int $uid): string
    {
        if (!self::ready($pdo)) throw new RuntimeException('Run the site health update (2027-03-03_site_health.sql) first: System Configuration → Database updates.');
        if (!isset(self::STATES[$state])) throw new RuntimeException('Unknown choice.');
        $f = null;
        foreach (self::load($pdo)['findings'] as $x) if ($x['id'] === $id) { $f = $x; break; }
        if (!$f) throw new RuntimeException('That finding isn\'t in the audit\'s latest run any more: it may be fixed already. Check again for the newest.');
        $note = trim($note);
        if ($state === 'aside' && mb_strlen($note) < 4) throw new RuntimeException('Say why it\'s fine as it is: the next review reads it.');
        $pdo->prepare('REPLACE INTO site_health_marks (finding_id, state, sig, note, by_user, at) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$id, $state, $f['sig'], mb_substr($note, 0, 255), $uid ?: null]);
        return $state === 'aside' ? 'Set aside: "' . $f['title'] . '". It comes back if what the audit finds changes.'
                                  : 'Marked fixed: "' . $f['title'] . '". It stays off the list unless the next audit run still finds it.';
    }

    /** where the audit's list is seen (check-register.md: "Its list stays as the audit's view, reached from a gauge's drill-down") */
    public const LIST_URL = '/admin/config/control?tab=development#site-health';

    /**
     * Site health on one path (Jason, 2026-11-22, check-register.md): each open finding goes through the dispatcher once, a
     * Pulse item with no push (kind sitehealth.finding), to the people trips reach. A finding whose evidence changes is a new
     * one (its sig), so it's sent again; one set aside or marked fixed isn't sent. The audit never trips anything on its own
     * (the two clocks, decisions/0175). Run by the board's watch. Returns how many were sent.
     */
    public static function dispatch(PDO $pdo): int
    {
        try {
            if (!self::on($pdo)) return 0;
            require_once __DIR__ . '/Notify.php'; require_once __DIR__ . '/CommsSetup.php';
            $to = CommsSetup::founders($pdo);
            if (!$to) return 0;   // nobody to receive it: the list on the board still shows it
            // a check of the audit that stopped running is a runtime failure: a warning on the board through the one engine, with
            // its Run link (it was a line of the old case; never a trip: the audit is the scheduled diagnostic, decisions/0175)
            try {
                require_once __DIR__ . '/AuditClock.php'; require_once __DIR__ . '/ControlAlarms.php';
                $stale = AuditClock::stale($pdo);
                if ($stale) ControlAlarms::raise($pdo, 'audit:stale', 'site:audit', 'warning', 'silent',
                    'The site audit: ' . count($stale) . ' check' . (count($stale) === 1 ? '' : 's') . ' not run in ' . AuditClock::STALE_H . ' hours',
                    'No run in ' . AuditClock::STALE_H . ' hours: ' . implode(', ', array_column($stale, 'name')) . '. What the gauges read may be out of date.',
                    'Run it on GitHub (beside this).', '', array_map(fn ($c) => ['Run ' . $c['name'], 'link', (string) $c['url'], 'github'], array_slice($stale, 0, 3)), true, false, 'site:audit');
                else ControlAlarms::clear($pdo, 'audit:stale', 'Every check of the site audit has run in the last ' . AuditClock::STALE_H . ' hours.');
            } catch (Throwable $x) {}
            $n = 0;
            foreach (self::open($pdo) as $f) {
                $n += Notify::send($pdo, 'sitehealth.finding', ['staff' => $to],
                    ['thing' => mb_substr('sh:' . $f['id'] . ':' . $f['sig'], 0, 120), 'url' => self::LIST_URL . '-' . $f['id'], 'from' => ['system', null, 'Site audit']],
                    ['title' => mb_substr('Site health: ' . $f['title'], 0, 200),
                     'body' => ($f['blocks'] > 0 ? 'In someone\'s way: it blocks ' . $f['blocks'] . ' path' . ($f['blocks'] === 1 ? '' : 's') . '. ' : '') . 'Found by the site audit. The full list, with Set aside and Mark fixed, is on the System Control Board.']) > 0 ? 1 : 0;
            }
            return $n;
        } catch (Throwable $x) { return 0; }
    }

    /** The Pulse item, or null (switch off, or nothing open). Severity: alert when something blocks a path or a page won't open. */
    public static function item(PDO $pdo): ?array
    {
        if (!self::on($pdo)) return null;
        $d = self::load($pdo);
        if (!$d['ok'] && !$d['findings']) {   // never read: one quiet case saying so, so the gap isn't silent
            return ['key' => 'sitehealth:open', 'severity' => 'update', 'title' => 'Site health: the audit\'s findings couldn\'t be read',
                    'why' => $d['error'] . ' The case fills in once they can be read.', 'when' => date('Y-m-d 00:00:00'), 'url' => '', 'action' => 'Check again', 'health' => ['open' => [], 'data' => $d]];
        }
        $open = self::open($pdo, $d);
        $stale = [];
        try { if (is_file(__DIR__ . '/AuditClock.php')) { require_once __DIR__ . '/AuditClock.php'; $stale = AuditClock::stale($pdo); } } catch (Throwable) {}
        if (!$open && !$stale) return null;
        if (!$open) {   // nothing found, but a check has stopped running: the findings are old, so say so
            return ['key' => 'sitehealth:open', 'severity' => 'alert', 'title' => 'Site health: ' . count($stale) . ' of the audit\'s checks ha' . (count($stale) === 1 ? 's' : 've') . 'n\'t run in a day',
                    'why' => 'No run in ' . AuditClock::STALE_H . ' hours: ' . implode(', ', array_column($stale, 'name')) . '. What Site health shows may be out of date. Run it from here.',
                    'when' => date('Y-m-d H:i:s'), 'url' => '', 'action' => 'Run it', 'health' => ['open' => [], 'stale' => $stale, 'data' => ['at' => $d['at'], 'run_url' => $d['run_url'], 'error' => $d['error']]]];
        }
        $blocking = count(array_filter($open, fn ($f) => $f['blocks'] > 0));   // in a path's way: someone can't do what they came to do
        $urgent = $blocking || array_filter($open, fn ($f) => $f['severity'] !== 'update');
        $newest = max(array_map(fn ($f) => (string) ($f['first_seen'] ?: $d['at']), $open));   // a new finding reopens a resolved case
        $top = array_slice(array_column($open, 'title'), 0, 3);
        return ['key' => 'sitehealth:open', 'severity' => $urgent ? 'alert' : 'update',
                'title' => 'Site health: ' . count($open) . ' finding' . (count($open) === 1 ? '' : 's') . ($blocking ? ', ' . $blocking . ' in someone\'s way' : ''),
                'why' => ($stale ? 'Not run in a day: ' . implode(', ', array_column($stale, 'name')) . '. ' : '') . implode(' · ', $top) . (count($open) > 3 ? ' · …' : '') . '. From the site audit' . ($d['at'] !== '' ? ' of ' . gmdate('M j, g:i A', strtotime($d['at'])) . ' UTC' : '') . ($d['error'] !== '' ? ' (newer ones couldn\'t be read: ' . $d['error'] . ')' : '') . '.',
                'when' => $newest !== '' ? date('Y-m-d H:i:s', strtotime($newest)) : date('Y-m-d H:i:s'), 'url' => '', 'action' => 'Look',
                'health' => ['open' => $open, 'stale' => $stale, 'data' => ['at' => $d['at'], 'run_url' => $d['run_url'], 'error' => $d['error']]]];
    }

    /** The case's steps (Pulse::playbook): one per finding, the most paths blocked first, then the case's own. */
    public static function steps(array $it): array
    {
        $h = (array) ($it['health'] ?? []);
        $open = (array) ($h['open'] ?? []);
        $run = (string) ($h['data']['run_url'] ?? '') ?: self::RUN_CHECKS;
        $st = [];
        foreach ((array) ($h['stale'] ?? []) as $key => $w) {   // a check that stopped running comes first: everything below may be old
            $st[] = ['t' => 'Run ' . $w['name'] . ': ' . ($w['at'] !== '' ? 'last ran ' . gmdate('M j, g:i A', strtotime($w['at'])) . ' UTC' : 'no run found'),
                     'h' => 'GitHub skips scheduled runs when it\'s busy, and the site\'s clock asks for them each day' . (!empty($w['asked']) ? ' (last asked ' . gmdate('M j, g:i A', (int) $w['asked']) . ' UTC)' : '') . '. Run it now: GitHub starts it within a few minutes.',
                     'act' => ['do', 'sh_run:' . $key, 'Run it now'], 'auto' => false, 'ext' => ['the last run', $w['url']]];
        }
        foreach (array_slice($open, 0, self::STEPS) as $f) {
            $who = $f['who'] ? ' Seen as: ' . implode(', ', $f['who']) . '.' : '';
            $paths = $f['blocks'] ? ' In the way of ' . $f['blocks'] . ' path' . ($f['blocks'] === 1 ? '' : 's') . ($f['paths'] ? ' (' . implode(', ', array_slice($f['paths'], 0, 4)) . ')' : '') . '.' : '';
            $since = $f['first_seen'] !== '' ? ' Since ' . gmdate('M j', strtotime($f['first_seen'])) . '.' : '';
            $page = str_starts_with($f['page'], '/') ? $f['page'] : '';
            $exts = $page !== '' ? [['the page', $page]] : [];
            if ($f['shot_url'] !== '') $exts[] = ['the screenshot', $f['shot_url']];
            if ($f['proposal_url'] !== '') $exts[] = ['proposal ' . ($f['proposal'] ?: ''), $f['proposal_url']];
            $st[] = ['t' => ($f['rule'] !== '' ? $f['rule'] . ': ' : '') . $f['title'] . ($page !== '' ? ' (' . $page . ')' : ''),
                     'h' => trim($f['detail'] . $paths . $who . $since . ($f['decided'] && $f['proposal'] !== '' ? ' Decided in ' . $f['proposal'] . ': waiting for its build.' : ($f['proposal'] !== '' ? ' Proposal ' . $f['proposal'] . ' waits for your decision.' : ''))
                              . ' Fixed in a build: say so, and the next audit run confirms it. Fine as it is: set it aside with why.'),
                     'act' => ['form', 'sh_mark:' . $f['id'], 'Fixed or fine', [['state', 'What to record', 'select', 'fixed', [['fixed', 'Fixed: the next audit run checks it'], ['aside', 'Fine as it is: set it aside']]],
                                                                             ['note', 'Why (for a set-aside: the review reads it)', 'textarea', '']]],
                     'auto' => false]
                   + ($exts ? ['ext' => $exts[0], 'exts' => $exts] : []);
        }
        $more = count($open) - count($st);
        // what's checked out (decisions/0161 §10): the files Claude sessions are writing a release for, near expiry flagged
        try { if (is_file(__DIR__ . '/Checkouts.php')) { require_once __DIR__ . '/Checkouts.php'; if ($co = Checkouts::step()) $st[] = $co; } } catch (Throwable) {}
        $st[] = ['t' => $more > 0 ? 'And ' . $more . ' more' : 'Every path and its measures', 'h' => 'The paths page lists every path, its steps, dead ends and surprises, and how each has gone run by run.', 'act' => null, 'auto' => false, 'ext' => ['the paths page', self::PATHS_PAGE]];
        $st[] = ['t' => 'Read the findings again now', 'h' => 'The audit runs once a day; this reads its newest findings without waiting the hour.', 'act' => ['do', 'sh_refresh', 'Check again'], 'auto' => false];
        $st[] = ['t' => 'Run the checks now', 'h' => 'Asks GitHub to run the browser checks and the paths (about 15 minutes). Each run keeps its screenshots for 14 days.', 'act' => ['do', 'sh_run:site-checks', 'Run them now'], 'auto' => false,
                 'ext' => ['GitHub: the site checks', self::RUN_CHECKS], 'exts' => [['GitHub: the site checks', self::RUN_CHECKS], ['the last run and its screenshots', $run]]];
        $st[] = ['t' => 'Have the review turn them into proposals', 'h' => 'In a Claude Code session on degraffics/traversence, the page-review skill reads each finding, its screenshots, paths and decisions, and adds proposals for you to approve.', 'act' => null, 'auto' => false, 'ext' => ['the proposals', self::PROPOSALS_PAGE]];
        return $st;
    }
}
