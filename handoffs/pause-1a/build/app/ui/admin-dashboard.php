<?php
/**
 * The admin dashboard (Jason, 2026-10-03): one place for what needs doing today, notifications, the inbox, how the
 * system is running (each with a link to its tool) and the key numbers. Rendered on the admin dashboard, Today (admin/index.php, decisions/0078).
 * Every count is guarded: a table that isn't there yet is skipped, never an error.
 */
declare(strict_types=1);

/**
 * What's waiting, counted once for both the dashboard's Today list and the menu's badges, so the number on ☰ is the
 * number of things to do. Missing tables count as 0.
 */
function tv_admin_counts(PDO $pdo): array
{
    static $c = null;
    if ($c !== null) return $c;
    $one = function (string $sql, array $a = []) use ($pdo): int { try { $st = $pdo->prepare($sql); $st->execute($a); return (int) $st->fetchColumn(); } catch (Throwable) { return 0; } };
    if (!function_exists('tv_crawler_waiting')) require_once __DIR__ . '/admin-crawler.php';
    $w = function_exists('tv_crawler_waiting') ? tv_crawler_waiting($pdo) : [];
    $d7 = date('Y-m-d', strtotime('-7 days'));
    $reports = 0;
    if (is_file(__DIR__ . '/../lib/Reports.php')) { require_once __DIR__ . '/../lib/Reports.php'; try { $reports = Reports::openCount($pdo); } catch (Throwable) {} }
    $cl = ['claims' => 0, 'overdue' => 0, 'held' => 0, 'changes' => 0, 'disputes' => 0];
    if (is_file(__DIR__ . '/../lib/Claims.php')) { require_once __DIR__ . '/../lib/Claims.php'; try { $cl = Claims::counts($pdo); } catch (Throwable) {} }
    return $c = [
        'reports' => $reports,
        'standing' => (function () use ($pdo): int { try { require_once __DIR__ . '/../lib/Standing.php'; return Standing::reviewsWaiting($pdo); } catch (Throwable) { return 0; } })(),
        'claims' => $cl['claims'], 'claims_overdue' => $cl['overdue'], 'claims_held' => $cl['held'], 'claims_changes' => $cl['changes'], 'claims_disputes' => $cl['disputes'],
        'suggestions' => (int) ($w['suggestions'] ?? 0),
        // of those, what people sent from the site (the rest come from the crawler's guide finds, Google's closures, map pins and calls)
        'sugg_people' => $one("SELECT COUNT(*) FROM suggestions WHERE status = 'new' AND (user_id IS NOT NULL OR (device_hash NOT LIKE 'system:%' AND device_hash NOT LIKE 'crawler-%' AND device_hash NOT LIKE 'guide-find-%' AND device_hash NOT LIKE 'call-%'))"), 'listings' => (int) ($w['listings'] ?? 0), 'sources' => (int) ($w['sources'] ?? 0),
        'names' => (int) ($w['names'] ?? 0), 'clusters' => (int) ($w['clusters'] ?? 0),
        'experiences' => $one("SELECT COUNT(*) FROM experiences WHERE status = 'submitted'"),
        'missed' => $one("SELECT COUNT(*) FROM (SELECT q FROM search_outcomes WHERE day >= ? AND outcome IN ('zero', 'unread') GROUP BY q HAVING SUM(n) >= 3) x", [$d7]),
        'left' => $one("SELECT COUNT(*) FROM (SELECT q FROM search_outcomes WHERE day >= ? AND outcome = 'left_none' GROUP BY q HAVING SUM(n) >= 3) x", [$d7]),
        'due' => $one("SELECT COUNT(*) FROM resource_entries WHERE status = 'live' AND (verified = 0 OR last_checked IS NULL OR last_checked < ?)", [date('Y-m-d H:i:s', strtotime('-31 days'))]),
        'gaps' => $one("SELECT COUNT(*) FROM situation_gaps WHERE status IN ('tier2', 'none')"),
        'failed' => $one("SELECT COUNT(*) FROM " . Database::t('crawl_jobs') . " WHERE status = 'failed'"),
        'conflicts' => $one("SELECT COUNT(DISTINCT record_id) FROM " . Database::t('identity_facts') . " WHERE record_kind = 'listing' AND status = 'conflict'"),
        'guides' => (function () use ($pdo): int { try { require_once __DIR__ . '/../lib/HowGuides.php'; return HowGuides::counts($pdo)['draft'] ?? 0; } catch (Throwable) { return 0; } })(),
        'drafts' => (function () use ($pdo): int { try { require_once __DIR__ . '/../lib/SearchDrafts.php'; return SearchDrafts::count($pdo); } catch (Throwable) { return 0; } })(),
        'found' => (function () use ($pdo): int { try { require_once __DIR__ . '/../lib/IdentityJobs.php'; return count(IdentityJobs::waiting($pdo, 500)); } catch (Throwable) { return 0; } })(),
    ];
}

/**
 * System status: each part, how it's running, and its tool: [state ok|warn|bad|off, name, line, url]. The dashboard
 * shows it, and the daily report (DailyReport) sends the parts that need a look.
 */
function tv_admin_status(PDO $pdo): array   // a row may carry a 5th element: the outside service it needs (Pulse::SERVICES key)
{
    if (!class_exists('SearchIndex') && is_file(__DIR__ . '/../lib/SearchIndex.php')) require_once __DIR__ . '/../lib/SearchIndex.php';
    $one = function (string $sql, array $a = []) use ($pdo): int { try { $st = $pdo->prepare($sql); $st->execute($a); return (int) $st->fetchColumn(); } catch (Throwable) { return 0; } };
    $val = function (string $sql, array $a = []) use ($pdo) { try { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchColumn(); } catch (Throwable) { return false; } };
    $has = function (string $t) use ($pdo): bool { try { $pdo->query('SELECT 1 FROM ' . Database::t($t) . ' LIMIT 1'); return true; } catch (Throwable) { return false; } };
    $ago = function (?string $t): string {
        if (!$t) return '';
        $s = time() - strtotime($t);
        return $s < 3600 ? max(1, (int) round($s / 60)) . ' min ago' : ($s < 86400 ? (int) round($s / 3600) . ' h ago' : (int) round($s / 86400) . ' d ago');
    };
    $status = [];   // [state ok|warn|bad|off, name, line, url]
    $last = $val("SELECT MAX(updated_at) FROM " . Database::t('crawl_jobs') . " WHERE status <> 'queued' OR attempts > 0");
    // the worker checks in every run even with nothing queued (its web search and Google check reports): its latest
    // word, not only its last job, says it's running (2026-10-14: amber with 0 jobs queued while it reported each run)
    if (!class_exists('SearchIndex') && is_file(__DIR__ . '/../lib/SearchIndex.php')) require_once __DIR__ . '/../lib/SearchIndex.php';
    if (class_exists('SearchIndex') && SearchIndex::ready($pdo)) {
        $hb = max((int) SearchIndex::state($pdo, 'websearch_seen'), (int) SearchIndex::state($pdo, 'places_seen'));
        if ($hb > 0 && (!$last || $hb > strtotime((string) $last))) $last = date('Y-m-d H:i:s', $hb);
    }
    $paused = strtolower((string) (getenv('CRAWL_QUEUE') ?: 'on')) === 'off';
    $queued = $one("SELECT COUNT(*) FROM " . Database::t('crawl_jobs') . " WHERE status = 'queued'");
    $status[] = [$paused ? 'off' : (!$last ? 'warn' : (strtotime((string) $last) > time() - 3600 ? 'ok' : (strtotime((string) $last) > time() - 6 * 3600 ? 'warn' : 'bad'))), 'Crawler',
                 $paused ? 'Paused (CRAWL_QUEUE=off)' : ($last ? 'Last checked in ' . $ago((string) $last) : 'Hasn\'t checked in yet') . ' · ' . $queued . ' job' . ($queued === 1 ? '' : 's') . ' queued'
                 . (!$paused && (!$last || strtotime((string) $last) < time() - 6 * 3600) ? '. The worker on Railway isn\'t running: open this for how to fix it.' : ''),
                 !$paused && (!$last || strtotime((string) $last) < time() - 6 * 3600) ? '/admin/crawler.php#fix-worker' : '/admin/crawler.php', 'railway'];
    require_once __DIR__ . '/../lib/crawler/AutoImport.php';   // one answer for Auto-import: .env, or the pause (2026-11-28)
    $auto = AutoImport::enabled();
    $status[] = [$auto ? 'ok' : 'off', 'Auto-import', $auto ? 'On: listings that pass every check publish' : (AutoImport::pausedWhy() ?? 'Off: everything waits for review'), '/admin/auto-imports.php'];
    if (class_exists('SearchIndex') && SearchIndex::ready($pdo)) {
        $n = $one('SELECT COUNT(*) FROM search_index'); $ref = SearchIndex::state($pdo, 'refreshed_at');
        $status[] = [$n > 0 ? 'ok' : 'bad', 'Search index', number_format($n) . ' things searchable' . ($ref !== '' ? ' · refreshed ' . $ago(is_numeric($ref) ? date('Y-m-d H:i:s', (int) $ref) : $ref) : ''), '/admin/search-index.php'];
        // web search (decisions/0062): what the crawler reported after its last run, and the month's total
        $wsSeen = (int) SearchIndex::state($pdo, 'websearch_seen'); $wsEng = SearchIndex::state($pdo, 'websearch_engine');
        $wsUsed = SearchIndex::state($pdo, 'websearch_month') === date('Y-m') ? (int) SearchIndex::state($pdo, 'websearch_used') : 0;
        $wsCap = (int) SearchIndex::state($pdo, 'websearch_monthly');
        if (!$wsSeen) $status[] = ['warn', 'Web search', 'No report from the crawler yet (it reports after its next run)', '/admin/crawler.php'];
        elseif ($wsEng === '') $status[] = ['off', 'Web search', 'Off: no search key on the crawler (TAVILY_API_KEY in Railway)', '/admin/crawler.php', 'railway'];
        else $status[] = [$wsCap && $wsUsed >= $wsCap ? 'bad' : ($wsCap && $wsUsed >= 0.8 * $wsCap ? 'warn' : 'ok'), 'Web search',
                          ucfirst($wsEng) . ': ' . number_format($wsUsed) . ($wsCap ? ' of ' . number_format($wsCap) : '') . ' searches this month'
                          . ($wsCap && $wsUsed >= $wsCap ? ' (allowance used up)' : '') . ' · reported ' . $ago(date('Y-m-d H:i:s', $wsSeen)), '/admin/crawler.php'];
        $lr = (int) SearchIndex::state($pdo, 'learning_run');
        $status[] = [!$has('search_learned') ? 'off' : ($lr > time() - 12 * 3600 ? 'ok' : 'warn'), 'Search learning', !$has('search_learned') ? 'Not set up (run 2026-10-23_search_learning.sql)' : ($lr ? 'Last ran ' . $ago(date('Y-m-d H:i:s', $lr)) : 'Hasn\'t run yet'), '/admin/search-learning.php'];
    }
    if ($has('identity_jobs')) {   // the crawler's identity job (decisions/0062, step 4)
        require_once __DIR__ . '/../lib/IdentityJobs.php';
        $ij = IdentityJobs::stats($pdo);
        $status[] = [$ij['queued'] + $ij['leased'] + $ij['done'] + $ij['nothing'] ? 'ok' : 'warn', 'Identity job',
                     number_format($ij['queued']) . ' listings to look up · ' . number_format($ij['week']) . ' found something this week · ' . number_format($ij['nothing']) . ' nothing yet', '/admin/identities.php'];
    }
    if ($has('resource_entries')) {
        $due2 = $one("SELECT COUNT(*) FROM resource_entries WHERE status = 'live' AND (verified = 0 OR last_checked IS NULL OR last_checked < ?)", [date('Y-m-d H:i:s', strtotime('-31 days'))]);
        $status[] = [$due2 ? 'warn' : 'ok', 'Resource guides', $one("SELECT COUNT(*) FROM resource_entries WHERE status = 'live' AND verified = 1") . ' numbers confirmed' . ($due2 ? ' · ' . $due2 . ' due' : ''), '/admin/resource-guides.php'];
    }
    foreach ([['NPI Registry', 'npi_loads'], ['IRS nonprofits', 'irs_loads'], ['Recreation.gov', 'rec_loads']] as [$bn, $bt]) {
        if (!$has($bt)) continue;
        $f = $val('SELECT MAX(finished_at) FROM ' . Database::t($bt));
        $status[] = [$f && strtotime((string) $f) > time() - 40 * 86400 ? 'ok' : 'warn', $bn, $f ? 'Loaded ' . $ago((string) $f) : 'Not loaded yet', '/admin/crawler.php'];
    }
    $missing = array_values(array_filter(['search_outcomes' => '2026-10-19_search_outcomes.sql', 'resource_guides' => '2026-10-22_resource_guides.sql', 'search_learned' => '2026-10-23_search_learning.sql', 'search_senses' => '2026-10-24_word_senses.sql', 'member_actions' => '2026-11-17_member_standing.sql', 'prospects' => '2026-11-18_sales.sql', 'category_also' => '2026-11-19_category_also.sql'], fn ($f, $t) => !$has($t), ARRAY_FILTER_USE_BOTH));
    if ($missing) $status[] = ['bad', 'Database', 'Run in phpMyAdmin: ' . implode(', ', $missing), '/admin/config/dbupdates'];

    // scheduled tasks: one that hasn't run in its time, or failed today
    if (is_file(__DIR__ . '/../lib/Scheduler.php')) {
        try {
            require_once __DIR__ . '/../lib/Scheduler.php';
            $late = []; $failed = [];
            foreach (Scheduler::lastRuns($pdo) as $job => $r) {
                if (!isset(Scheduler::JOBS[$job])) continue;
                $name = Scheduler::JOBS[$job][0];
                // late only for a job that logs every run: a retired one (Scheduler::RETIRED) and one that logs only when it has
                // work (Scheduler::QUIET) are quiet by design (2026-11-18, Site health D03)
                $quiet = in_array($job, array_merge(Scheduler::QUIET, Scheduler::RETIRED), true);
                if (!$quiet && strtotime((string) $r['started_at']) < time() - 60 * (int) Scheduler::JOBS[$job][3]) $late[] = $name;
                elseif ((int) $r['fails_today'] > 0) $failed[] = $name;
            }
            $status[] = [$late || $failed ? ($late ? 'bad' : 'warn') : 'ok', 'Scheduled tasks',
                $late || $failed ? implode(' · ', array_filter([$late ? 'Late: ' . implode(', ', $late) : '', $failed ? 'Failed today: ' . implode(', ', $failed) : ''])) : 'All running on time',
                '/admin/config/scheduler'];
        } catch (Throwable) {}
    }
    // database updates: the site runs each new one itself; one that failed holds the ones after it
    if (is_file(__DIR__ . '/../lib/DbUpdates.php')) {
        try {
            require_once __DIR__ . '/../lib/DbUpdates.php';
            $wait = array_map('basename', glob(DbUpdates::dir() . '/*.sql') ?: []);
            $log = DbUpdates::log($pdo);
            $bad = array_values(array_filter($wait, fn ($f) => isset($log[$f]) && !(int) $log[$f]['ok']));
            $status[] = [$bad ? 'bad' : ($wait ? 'warn' : 'ok'), 'Database updates',
                $bad ? 'Failed: ' . implode(', ', $bad) . (count($wait) > count($bad) ? ' · ' . (count($wait) - count($bad)) . ' waiting behind it' : '') : ($wait ? count($wait) . ' waiting to run (within the hour)' : 'None waiting'),
                '/admin/config/dbupdates'];
        } catch (Throwable) {}
    }
    return $status;
}

function tv_admin_dashboard(PDO $pdo, array $ctx, int $uid): string
{
    foreach (['People', 'SearchIndex'] as $cl) if (!class_exists($cl) && is_file(__DIR__ . '/../lib/' . $cl . '.php')) require_once __DIR__ . '/../lib/' . $cl . '.php';
    $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $one = function (string $sql, array $a = []) use ($pdo): int { try { $st = $pdo->prepare($sql); $st->execute($a); return (int) $st->fetchColumn(); } catch (Throwable) { return 0; } };
    $val = function (string $sql, array $a = []) use ($pdo) { try { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchColumn(); } catch (Throwable) { return false; } };
    $rows = function (string $sql, array $a = []) use ($pdo): array { try { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) { return []; } };
    $has = function (string $t) use ($pdo): bool { try { $pdo->query('SELECT 1 FROM ' . Database::t($t) . ' LIMIT 1'); return true; } catch (Throwable) { return false; } };
    $can = fn (string $cap) => class_exists('Roles') ? Roles::can($ctx, $cap) : true;
    $d7 = date('Y-m-d', strtotime('-7 days')); $d14 = date('Y-m-d', strtotime('-14 days'));
    $ago = function (?string $t): string {
        if (!$t) return '';
        $s = time() - strtotime($t);
        return $s < 3600 ? max(1, (int) round($s / 60)) . ' min ago' : ($s < 86400 ? (int) round($s / 3600) . ' h ago' : (int) round($s / 86400) . ' d ago');
    };

    // ---- Today: what needs a person, most urgent first. [group, count, title, why, url, button] ----
    $today = [];
    $add = function (string $g, int $n, string $title, string $why, string $url, string $btn) use (&$today) { if ($n > 0) $today[] = [$g, $n, $title, $why, $url, $btn]; };
    $C = tv_admin_counts($pdo);
    if ($can('people') || $can('reports')) $add('Safety', $C['reports'], 'Reports to resolve', 'Something people flagged: decide to remove it or not.', '/admin/helper.php?q=reports', 'Work through');
    if ($can('people')) $add('Safety', $C['standing'], 'Reviews asked', 'A member asked a person to look again at a notice on their account; their clock is stopped until you decide.', '/admin/safety.php#standing', 'Decide');
    // claims (decisions/0070): overdue contacts first, then holds, disputes, claims and owner changes
    if ($can('content') || $can('listings')) {
        $add('People waiting', $C['claims_overdue'], 'Claims past 48 hours', 'Contact the claimant, record what you found, and decide.', '/admin/claims.php#claims', 'Contact them');
        $add('People waiting', $C['claims_held'], 'Notice: claim requests on hold', 'A second person asked to claim a listing that someone else holds.', '/admin/claims.php#held', 'Look');
        $add('People waiting', $C['claims_disputes'], 'Disputes about a listing\'s owner', 'Someone believes they own a listing another person holds.', '/admin/claims.php#disputes', 'Decide');
        $add('People waiting', max(0, $C['claims'] - $C['claims_overdue']), 'Claims to confirm', 'Match the text code, or contact the claimant within 48 hours.', '/admin/claims.php#claims', 'Confirm');
        $add('Listings', $C['claims_changes'], 'Owner changes waiting', 'Name, phone, address or website changes from listings not verified yet.', '/admin/claims.php#changes', 'Review');
    }
    if ($can('content') || $can('listings')) {
        $add('People waiting', $C['sugg_people'], 'Sent in by visitors', 'Missing places, fixes and feedback from people using the site.', '/admin/crawler-review.php?type=suggest', 'Read them');
        $add('Listings', max(0, $C['suggestions'] - $C['sugg_people']), 'Suggested by the crawler and our checks', 'Places a guide mentions that the crawler found, closures Google reports, pins and fixes from calls: check each, then add or fix.', '/admin/crawler-review.php?type=suggest', 'Review them');
        // straight to the work, one item at a time: possible duplicates first, then the rest (Jason: "multiple steps to get to a task")
        $lq = 'listings'; try { require_once __DIR__ . '/../lib/Helper.php'; if (Helper::staged($pdo)['dupes']) $lq = 'dupes'; } catch (Throwable) {}
        $add('Listings', $C['listings'], 'Listings the crawler couldn\'t finish', 'It tried everything it could on these (its own website, registries, maps): publish, merge or leave out.', '/admin/helper.php?q=' . $lq, 'Work through');
        $add('Listings', $C['experiences'], 'Experiences submitted', 'Members\' experiences waiting to be published.', '/admin/experiences.php', 'Review them');
        $add('Content', $C['sources'], 'Websites to decide on', 'Read them regularly, cite only, or ignore.', '/admin/helper.php?q=sources', 'Work through');
        $add('Content', $C['names'], 'Local names to confirm', 'Names people use for parts of an area.', '/admin/crawler-review.php?type=name', 'Confirm');
        $firstArea = $rows("SELECT id, geo_hub_id FROM micro_clusters WHERE name LIKE '%(auto-seeded%' ORDER BY id LIMIT 1")[0] ?? null;   // straight to it in the places tool
        $add('Content', $C['clusters'], 'Areas to name', 'Areas the system made on its own still need a proper name.', $firstArea ? '/admin/cluster-tools.php?view=places&hub=' . (int) $firstArea['geo_hub_id'] . '#c' . (int) $firstArea['id'] : '/admin/cluster-tools.php?view=places', 'Name them');
    }
    $add('Search', $C['missed'], 'Searches that found nothing this week', 'Words people searched 3+ times with no answer: teach search or add what\'s missing.', '/admin/search-misses.php', 'Open the workbench');
    $add('Search', $C['guides'], 'How-to guides to check', 'The crawler drafted the steps from official pages: put them in our words, check, publish.', '/admin/how-guides.php?s=draft', 'Review');
    $add('Search', $C['drafts'], 'Searches search couldn\'t answer on its own', 'Too few searches yet, or nothing to offer: look, or leave them to learn.', '/admin/search-misses.php#drafts', 'Decide');
    $add('Search', $C['left'], 'Words with nothing to predict', 'Typed 3+ times this week with only "Search for…" showing.', '/admin/search-misses.php#ms-pred', 'See them');
    $add('Search', $C['due'], 'Phone numbers to re-check', 'Resource-guide numbers not confirmed on their official page in the last month.', '/admin/search-oversight.php#due', 'Re-check');
    $add('Search', $C['gaps'], 'Situation needs with no answer nearby', 'The crawler couldn\'t fill these from open data; a web search or a person may.', '/admin/search-learning.php#gaps', 'See the gaps');
    $add('System', $C['failed'], 'Crawl jobs that failed', 'Usually OpenStreetMap was busy. One button puts them all back in the queue.', '/admin/crawler.php#fix-worker', 'Try again');
    // facts where sources disagree (decisions/0062): a person picks the right one
    if ($can('content') || $can('listings'))
        $add('Listings', $C['conflicts'], 'Facts where sources disagree', 'Two values for one phone, address or name: pick the right one.', '/admin/helper.php?q=conflicts', 'Work through');
    if ($can('content') || $can('listings'))
        $add('Listings', $C['found'], 'Found by the crawler', 'A missing phone, website or email that one website gives: yes or not right.', '/admin/helper.php?q=found', 'Work through');
    // QuickBooks (decisions/0096): GO Code payments stop when the connection lapses, so asking for a reconnect comes first
    if ($ctx['kind'] === 'admin' && $has('qb_connection')) {
        $qb = $rows('SELECT refresh_until FROM ' . Database::t('qb_connection') . ' WHERE id = 1')[0] ?? null;
        if ($qb) {
            $left = strtotime((string) $qb['refresh_until']) - time();
            if ($left <= 0) $add('Payments', 1, 'QuickBooks needs reconnecting', 'GO Code buying shows "opens soon" until you do. Orders and codes are safe.', '/admin/quickbooks.php', 'Reconnect');
            elseif ($left < 14 * 86400) $add('Payments', 1, 'Reconnect QuickBooks soon', 'Its connection runs out in ' . max(1, (int) ceil($left / 86400)) . ' days unless a sale or a reconnect renews it.', '/admin/quickbooks.php', 'Reconnect');
        }
    }
    // card disputes (decisions/0098): the bank wants an answer by a date, so they come first too
    if ($ctx['kind'] === 'admin' && $has('order_ledger')) {
        $n = (int) ($rows("SELECT COUNT(*) AS n FROM orders WHERE status = 'disputed'")[0]['n'] ?? 0);
        $add('Payments', $n, 'Card disputes open', 'A customer disputed a payment with their bank: answer it in QuickBooks, then record how it ended. Its GO Codes are paused meanwhile.', '/admin/ecommerce.php#orders', 'Open');
    }
    // wires and checks (decisions/0101): nothing else follows up once a client picks one, so an order can't sit on its hold unseen
    if ($ctx['kind'] === 'admin' && $has('order_ledger')) {
        $wait = $rows("SELECT o.id, o.pay_report, (SELECT MAX(e.created_at) FROM order_events e WHERE e.order_id = o.id AND e.event = 'pay_method') AS chosen
                       FROM orders o WHERE o.status = 'pending' AND o.pay_method IN ('wire', 'check') ORDER BY o.id");
        $quiet = array_values(array_filter($wait, fn ($w) => (string) $w['pay_report'] === '' && $w['chosen'] && strtotime((string) $w['chosen']) < time() - 2 * 3600));
        $sent = array_values(array_filter($wait, fn ($w) => (string) $w['pay_report'] !== ''));
        $add('Payments', count($quiet), 'Wire or check chosen, no confirmation code', 'Picked over 2 hours ago with no code yet: reminders go to them (2 hours, 9 am next business day, 4 pm with other ways to pay). A call may be quicker.', $quiet ? '/admin/backoffice/orders/' . (int) $quiet[0]['id'] . '#payhold' : '', 'Call them');
        $add('Payments', count($sent), 'Wires and checks on their way', 'The client says it\'s sent (their code is on the order): record it when it reaches the bank, and the order goes to production.', $sent ? '/admin/backoffice/orders/' . (int) $sent[0]['id'] . '#payhold' : '', 'Watch for it');
    }
    if ($ctx['kind'] === 'admin' && $has('order_inbox')) {
        $n = (function () use ($pdo): int { try { require_once __DIR__ . '/../lib/Orders.php'; return count(Orders::inbox($pdo)); } catch (Throwable) { return 0; } })();   // this mode's only
        $add('Payments', $n, 'Refunds made in QuickBooks to place', 'QuickBooks reported a refund or credit memo that could be more than one order: say which, so the right GO Codes stop.', '/admin/ecommerce.php#orders', 'Place them');
    }
    $order = ['Payments' => -1, 'Safety' => 0, 'People waiting' => 1, 'Listings' => 2, 'Search' => 3, 'Content' => 4, 'System' => 5];
    usort($today, fn ($a, $b) => [$order[$a[0]] ?? 9, -$a[1]] <=> [$order[$b[0]] ?? 9, -$b[1]]);

    // ---- Notifications: what happened lately, newest first ----
    $notes = [];
    foreach ($rows("SELECT reason, created_at FROM content_reports WHERE status = 'open' AND created_at >= ? ORDER BY created_at DESC LIMIT 5", [$d14]) as $r)
        $notes[] = [$r['created_at'], 'Report', 'New report: ' . str_replace('_', ' ', (string) $r['reason']), '/admin/reports.php'];
    foreach ($rows("SELECT c.status, c.created_at, e.name FROM listing_claims c JOIN entities e ON e.id = c.entity_id WHERE c.created_at >= ? AND c.status IN ('managed','on_hold') ORDER BY c.created_at DESC LIMIT 5", [$d14]) as $r)
        $notes[] = [$r['created_at'], $r['status'] === 'on_hold' ? 'Notice' : 'Claim', ($r['status'] === 'on_hold' ? 'Claim request on hold: ' : 'New claim: ') . $r['name'], '/admin/claims.php'];
    foreach ($rows("SELECT kind, target_name, context, created_at FROM suggestions WHERE status = 'new' AND created_at >= ? ORDER BY created_at DESC LIMIT 5", [$d14]) as $r) {
        $g = $r['context'] === 'google';   // the Places check (api/crawl/places.php), not a person
        $notes[] = [$r['created_at'], $g ? 'Google check' : 'Visitor', ($g && $r['kind'] === 'closed' ? 'Google says closed' : ucfirst((string) $r['kind'])) . ($r['target_name'] !== '' ? ': ' . $r['target_name'] : ''), '/admin/crawler-review.php?type=suggest'];
    }
    foreach ($rows("SELECT title, created_at FROM experiences WHERE status = 'submitted' AND created_at >= ? ORDER BY created_at DESC LIMIT 3", [$d14]) as $r)
        $notes[] = [$r['created_at'], 'Experience', 'Submitted: ' . $r['title'], '/admin/experiences.php'];
    foreach ($rows("SELECT title, published_at FROM journeys WHERE status = 'published' AND published_at >= ? ORDER BY published_at DESC LIMIT 3", [$d14]) as $r)
        $notes[] = [$r['published_at'], 'Journey', 'Published: ' . $r['title'], '/admin/journeys.php'];
    foreach ($rows("SELECT j.group_label, j.updated_at, m.name FROM " . Database::t('crawl_jobs') . " j LEFT JOIN micro_clusters m ON m.id = j.cluster_id WHERE j.status = 'failed' AND j.updated_at >= ? ORDER BY j.updated_at DESC LIMIT 3", [$d14]) as $r)
        $notes[] = [$r['updated_at'], 'Crawler', 'A crawl job failed' . ($r['name'] ? ' in ' . preg_replace('/\s*\(auto-seeded[^)]*\)/i', '', (string) $r['name']) : ''), '/admin/crawler.php'];
    foreach ($rows("SELECT phrase, status, changed_at FROM search_learned WHERE changed_at >= ? ORDER BY changed_at DESC LIMIT 4", [$d14]) as $r)
        $notes[] = [$r['changed_at'], 'Learning', '"' . $r['phrase'] . '": ' . (['live' => 'published', 'may_help' => 'now May also help', 'retired' => 'retired', 'learning' => 'back to learning'][$r['status']] ?? $r['status']), '/admin/search-learning.php'];
    foreach ($rows("SELECT name, last_checked FROM resource_entries WHERE verified = 0 AND last_checked IS NOT NULL AND last_checked >= ? ORDER BY last_checked DESC LIMIT 3", [$d14]) as $r)
        $notes[] = [$r['last_checked'], 'Resource', 'Number not found on its source: ' . $r['name'], '/admin/search-oversight.php#due'];
    usort($notes, fn ($a, $b) => strcmp((string) $b[0], (string) $a[0]));
    $notes = array_slice($notes, 0, 10);

    // ---- Inbox: your own messages and requests, and what visitors wrote ----
    $inbox = [];
    if (class_exists('People') && People::tableExists($pdo)) {
        try {
            $wt = People::waiting($pdo, $uid);
            if ($wt['unread']) $inbox[] = [$wt['unread'], $wt['unread'] === 1 ? 'unread message' : 'unread messages', '/user/dashboard.php#addressbook:messages'];
            if (!empty($wt['message_requests'])) $inbox[] = [$wt['message_requests'], 'message request' . ($wt['message_requests'] === 1 ? '' : 's'), '/user/dashboard.php#addressbook:messages'];
            if ($wt['requests']) $inbox[] = [$wt['requests'], $wt['requests'] === 1 ? 'person wants to connect' : 'people want to connect', '/user/dashboard.php#addressbook:requests'];
        } catch (Throwable) {}
    }
    $fb = $one("SELECT COUNT(*) FROM suggestions WHERE status = 'new' AND kind = 'feedback'");
    if ($fb) $inbox[] = [$fb, 'feedback note' . ($fb === 1 ? '' : 's') . ' from visitors', '/admin/crawler-review.php?type=suggest'];

    $status = tv_admin_status($pdo);

    // ---- Key numbers: the last 7 days against the 7 before ----
    $pair = function (string $sql) use ($one): array { return [$one($sql, [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('+1 day'))]), $one($sql, [date('Y-m-d', strtotime('-14 days')), date('Y-m-d', strtotime('-7 days'))])]; };
    $metrics = [];
    if ($has('search_outcomes')) {
        $s = $pair("SELECT COALESCE(SUM(n), 0) FROM search_outcomes WHERE outcome = 'search' AND day >= ? AND day < ?");
        $z = $pair("SELECT COALESCE(SUM(n), 0) FROM search_outcomes WHERE outcome IN ('zero', 'unread') AND day >= ? AND day < ?");
        $pk = $pair("SELECT COALESCE(SUM(n), 0) FROM search_outcomes WHERE outcome = 'picked' AND day >= ? AND day < ?");
        $lf = $pair("SELECT COALESCE(SUM(n), 0) FROM search_outcomes WHERE outcome IN ('left_pred', 'left_none') AND day >= ? AND day < ?");
        $metrics[] = ['Searches', $s[0], $s[1], '', '/admin/search-misses.php'];
        $rate = fn ($a, $b) => $a + $b > 0 ? (int) round(100 * $a / ($a + $b)) : null;
        $metrics[] = ['Searches that found something', $s[0] ? (int) round(100 * max(0, $s[0] - $z[0]) / $s[0]) : null, $s[1] ? (int) round(100 * max(0, $s[1] - $z[1]) / $s[1]) : null, '%', '/admin/search-misses.php'];
        $metrics[] = ['Predictions picked', $rate($pk[0], $lf[0] + $s[0]), $rate($pk[1], $lf[1] + $s[1]), '%', '/admin/search-misses.php#ms-pred'];
    }
    if ($has('platform_counts')) { $v = $pair("SELECT COALESCE(SUM(n), 0) FROM platform_counts WHERE metric = 'view' AND day >= ? AND day < ?"); $metrics[] = ['Page views', $v[0], $v[1], '', '/admin/insights.php']; }
    if ($has('listing_actions')) { $a = $pair("SELECT COALESCE(SUM(n), 0) FROM listing_actions WHERE day >= ? AND day < ?"); $metrics[] = ['Calls, directions, websites', $a[0], $a[1], '', '/admin/insights.php']; }
    require_once __DIR__ . '/../lib/TestAccounts.php';
    $u = $pair("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?" . TestAccounts::notTest($pdo));   // test accounts aren't members $metrics[] = ['New members', $u[0], $u[1], '', '/admin/people.php'];
    if ($has('journeys')) { $j = $pair("SELECT COUNT(*) FROM journeys WHERE status = 'published' AND published_at >= ? AND published_at < ?"); $metrics[] = ['Journeys published', $j[0], $j[1], '', '/admin/journeys.php']; }

    // ---- the day, as steps (Jason, 2026-10-14: "I need to be able to login, address issues, and do admin or sales tasks
    // that push the progress of the site and the business forward"): in order, each with one button to start ----
    $sales = null;
    if (is_file(__DIR__ . '/../lib/Sales.php')) { require_once __DIR__ . '/../lib/Sales.php'; try { if (Sales::ready($pdo)) $sales = Sales::funnel($pdo); } catch (Throwable) {} }
    $callsToday = $one("SELECT COUNT(*) FROM " . Database::t('prospect_log') . " WHERE staff_id = ? AND kind <> 'note' AND at >= ?", [$uid, date('Y-m-d 00:00:00')]);
    $goal = 10;   // contacts a day: a steady pace that turns unclaimed listings into active ones
    $rowsOf = fn (string ...$groups) => array_values(array_filter($today, fn ($r) => in_array($r[0], $groups, true)));
    $sysBad = array_values(array_filter($status, fn ($x) => in_array($x[0], ['warn', 'bad'], true)));
    $steps = [
        ['payments', 'Money waiting', 'Wires and checks to follow up, card disputes, QuickBooks.', $rowsOf('Payments'), '/admin/ecommerce.php#orders', 'Look'],
        ['safety', 'Safety first', 'Reports people sent, and members asking for a review.', $rowsOf('Safety'), '/admin/safety.php', 'Resolve them'],
        ['people', 'People waiting', 'Owners claiming their listing, their changes, disputes, and what visitors sent.', $rowsOf('People waiting'), '/admin/helper.php', 'Answer them'],
        ['sales', 'Sales', $sales ? (($sales['due'] ? $sales['due'] . ' follow-up' . ($sales['due'] === 1 ? '' : 's') . ' due. ' : '') . 'You\'ve logged ' . $callsToday . ' of ' . $goal . ' contacts today; ' . number_format($sales['new']) . ' unclaimed businesses not contacted yet.') : 'Set up Sales: run 2026-11-18_sales.sql.', [], '/admin/sales.php', 'Start calling'],
        ['listings', 'Listings', 'What the crawler couldn\'t finish on its own, facts to check, and members\' experiences.', $rowsOf('Listings'), '/admin/helper.php', 'Work through'],
        ['content', 'Search & content', 'What search couldn\'t answer, guides to check, places to name.', $rowsOf('Search', 'Content'), '/admin/helper.php', 'Work through'],
        ['system', 'System', $sysBad ? count($sysBad) . ' part' . (count($sysBad) === 1 ? '' : 's') . ' need a look.' : 'Everything is running.',
            array_merge(array_map(fn ($x) => ['System', 1, $x[1], $x[2], $x[3], 'Look'], $sysBad), $rowsOf('System')), '/admin/crawler.php', 'Look'],
    ];
    $stepN = function (array $st) use ($sales, $callsToday, $goal, $sysBad): int {
        if ($st[0] === 'sales') return $sales ? max((int) $sales['due'], $callsToday >= $goal ? 0 : $goal - $callsToday) : 0;
        if ($st[0] === 'system') return count($sysBad) + array_sum(array_column(array_filter($st[3], fn ($r) => $r[5] !== 'Look'), 1));
        return array_sum(array_column($st[3], 1));
    };
    $done = count(array_filter($steps, fn ($st) => $stepN($st) === 0));
    // the pop-up's breadcrumbs: each step, and its tasks (the kinds of work in it; a step without kinds is one task)
    $flow = array_map(fn ($st) => ['key' => $st[0], 'title' => $st[1], 'n' => $stepN($st),
        'tasks' => $st[3] ? array_map(fn ($r) => ['title' => $r[2], 'n' => (int) $r[1], 'url' => $r[4]], $st[3])
                          : [['title' => $st[1], 'n' => $stepN($st), 'url' => $st[4]]]], $steps);

    // ---- the inbox: notifications and what's addressed to staff, one stream, newest first ----
    $stream = [];
    foreach ($notes as [$when, $k, $text, $url]) $stream[] = [(string) $when, $k, $text, $url];
    foreach ($rows("SELECT a.review_asked_at, u.display_name FROM member_actions a JOIN users u ON u.id = a.user_id WHERE a.status = 'review' ORDER BY a.review_asked_at DESC LIMIT 5") as $r)
        $stream[] = [(string) $r['review_asked_at'], 'Review', ($r['display_name'] ?: 'A member') . ' asked for a review of a notice', '/admin/safety.php#standing'];
    foreach ($rows("SELECT created_at, name FROM entities WHERE entity_type = 'merchant' AND created_by_type IN ('owner','user') AND created_at >= ? ORDER BY created_at DESC LIMIT 5", [$d14]) as $r)
        $stream[] = [(string) $r['created_at'], 'Owner', 'An owner added ' . $r['name'], '/admin/sales.php#new'];
    usort($stream, fn ($a, $b) => strcmp($b[0], $a[0]));
    $stream = array_slice($stream, 0, 14);

    // ---- how the business is doing ----
    $biz = [];
    if ($sales) {
        $biz[] = ['Claimed listings', $sales['claimed'] + $sales['verified'], $sales['verified'] . ' verified · ' . $sales['paid'] . ' paying', '/admin/sales.php#funnel'];
        $biz[] = ['Claims started this week', $sales['week']['claims'], 'by owners', '/admin/claims.php'];
        $biz[] = ['Contacts this week', $sales['week']['contacts'], $sales['week']['invites'] . ' claim invites sent', '/admin/sales.php#funnel'];
        $biz[] = ['In the funnel', $sales['contacted'] + $sales['interested'] + $sales['invited'], $sales['interested'] . ' interested', '/admin/sales.php#all'];
        $biz[] = ['New listings this week', $sales['week']['added'], $sales['week']['field'] . ' from the field', '/admin/sales.php#new'];
    }

    // a staff login sees only what its role opens (decisions/0100): links into other areas are left out
    $role = null;
    try { require_once __DIR__ . '/../lib/StaffLogin.php'; if ($s = StaffLogin::inSession($pdo)) $role = (string) $s['role']; } catch (Throwable) {}
    if ($role !== null) {
        $ok = function (string $url) use ($role): bool { $p = (string) parse_url($url, PHP_URL_PATH); return !str_starts_with($p, '/admin') || StaffLogin::allowsArea($role, StaffLogin::areaFor($p)); };
        $inbox = array_values(array_filter($inbox, fn ($r) => $ok((string) $r[2])));
        $stream = array_values(array_filter($stream, fn ($r) => $ok((string) $r[3])));
        $biz = array_values(array_filter($biz, fn ($r) => $ok((string) $r[3])));
        $metrics = array_values(array_filter($metrics, fn ($r) => $ok((string) $r[4])));
        $status = array_values(array_filter($status, fn ($r) => $ok((string) $r[3])));
        foreach ($steps as $i => $st) {
            $steps[$i][3] = array_values(array_filter($st[3], fn ($t) => $ok((string) $t[4])));
            if (!$ok((string) $st[4]) && !$steps[$i][3]) unset($steps[$i]);
        }
        $steps = array_values($steps);
    }

    ob_start(); ?>
    <style>
      .ad2{display:grid;gap:.8rem;grid-template-columns:minmax(0,2fr) minmax(0,1fr);margin-top:.6rem;align-items:start}
      @media (max-width:1023px){.ad2{grid-template-columns:minmax(0,1fr)}}
      .ad-c{background:#F7EFDC;border:1px solid #CBB98D;border-radius:.6rem;padding:.75rem .85rem}
      .ad-c>h3{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.05rem;color:#3E2B1F;margin:0 0 .4rem;display:flex;align-items:baseline;gap:.5rem;flex-wrap:wrap}
      .ad-c>h3 small{font-family:system-ui,sans-serif;font-weight:400;font-size:.76rem;color:#7A6348}
      .ad-step{border-top:1px solid #EADFC4;padding:.6rem 0}
      .ad-step:first-of-type{border-top:0}
      .ad-sh{display:flex;gap:.65rem;align-items:center}
      .ad-num{flex:0 0 2rem;height:2rem;border-radius:999px;background:#3E2B1F;color:#FFF8EA;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem}
      .ad-step.done .ad-num{background:#8FBCA8;color:#1F3B30}
      .ad-sm{flex:1;min-width:0}.ad-sm b{display:block;font-size:.98rem;color:#3E2B1F}.ad-sm span{display:block;font-size:.8rem;color:#5C4632}
      .ad-cnt{font:700 1.2rem var(--tv-font-head,Georgia,serif);color:var(--tv-link,#92400E);flex:none;min-width:2.2rem;text-align:right}
      .ad-step.done .ad-cnt{color:#1F5137;font-size:.85rem;font-family:system-ui,sans-serif}
      .ad-go{flex:0 0 auto;font-size:.82rem;font-weight:700;border:1px solid #3E2B1F;background:#3E2B1F;color:#FFFBF2;border-radius:.55rem;padding:.4rem .75rem;text-decoration:none;white-space:nowrap}
      .ad-step.done .ad-go{background:#FFFBF2;color:#3E2B1F;border-color:#CBB98D}
      .ad-step details{margin:.35rem 0 0 2.65rem}.ad-step summary{cursor:pointer;font-size:.8rem;color:#92400E;font-weight:600}
      .ad-sub{display:flex;gap:.5rem;align-items:center;padding:.35rem 0;font-size:.84rem;color:#3E2B1F;text-decoration:none;border-top:1px dashed #EADFC4}
      .ad-sub:hover{color:#92400E}.ad-sub i{font-style:normal;font-weight:700;color:var(--tv-link,#92400E);min-width:2rem}
      .ad-r{display:flex;gap:.55rem;align-items:flex-start;padding:.45rem 0;border-top:1px solid #EADFC4;font-size:.85rem;color:#3E2B1F;text-decoration:none}
      .ad-r:hover{color:#92400E}.ad-r small{display:block;font-size:.72rem;color:#7A6348}
      .ad-r.new{background:linear-gradient(90deg,rgba(143,188,168,.18),transparent);border-radius:.3rem}
      .ad-k{flex:0 0 4.4rem;font-size:.6rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta,#5E4F3C);padding-top:.2rem}
      .ad-dot{flex:0 0 10px;height:10px;border-radius:999px;margin-top:.32rem;background:#8FBCA8}
      .ad-dot.warn{background:#D97706}.ad-dot.bad{background:#B91C1C}.ad-dot.off{background:#C9B89A}
      .ad-ms{display:grid;gap:.5rem;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
      .ad-m{display:block;background:#FFFBF2;border:1px solid #EADFC4;border-radius:.5rem;padding:.5rem .6rem;text-decoration:none;color:#3E2B1F}
      .ad-m b{display:block;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.35rem;line-height:1.1}.ad-m span{display:block;font-size:.74rem;color:#5C4632}.ad-m small{display:block;font-size:.7rem;color:var(--tv-meta,#5E4F3C)}
      .ad-m i{font-style:normal;font-size:.7rem;font-weight:700}.ad-m i.up{color:#1F5137}.ad-m i.dn{color:#B91C1C}.ad-m i.eq{color:var(--tv-meta,#5E4F3C)}
      .ad-empty{font-size:.84rem;color:#7A6348;margin:0}
      .wk-lb{position:fixed;inset:0;z-index:150;background:rgba(42,27,18,.55);overflow-y:auto;overscroll-behavior:contain;padding:12px}
      .wk{width:min(1180px,100%);min-height:calc(100% - 0px);margin:0 auto;background:#F7EFDC;border-radius:.9rem;box-shadow:0 20px 50px rgba(0,0,0,.35);display:flex;flex-direction:column}
      .wk-h{position:sticky;top:-12px;z-index:2;background:#3E2B1F;color:#FFF8EA;padding:.5rem .7rem;display:flex;flex-direction:column;gap:.35rem;border-radius:.9rem .9rem 0 0}
      .wk-top{display:flex;align-items:center;gap:.5rem}
      .wk-top b{flex:1;min-width:0;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1rem}
      .wk-top button{font:inherit;font-weight:700;font-size:.82rem;border-radius:.5rem;padding:.35rem .7rem;cursor:pointer;border:1px solid #CBB98D;background:#FFFBF2;color:#3E2B1F;white-space:nowrap}
      .wk-top button.next{background:#8FBCA8;border-color:#8FBCA8;color:#1F3B30}
      .wk-top button.x{width:2rem;height:2rem;padding:0;font-size:1.2rem;line-height:1}
      .wk-crumbs{display:flex;gap:.25rem;align-items:center}
      .wk-crumbs[hidden]{display:none}
      .wk-crumbs button{font:inherit;font-size:.8rem;border:0;border-radius:999px;padding:.25rem .6rem;cursor:pointer;background:transparent;color:#EADFC4;white-space:nowrap}
      .wk-crumbs button i{font-style:normal;font-weight:700;margin-left:.3rem;color:#F3B65A}
      .wk-crumbs button.done{opacity:.6}.wk-crumbs button.done i{color:#8FBCA8}
      .wk-crumbs button.on{background:#8FBCA8;color:#1F3B30;font-weight:700}.wk-crumbs button.on i{color:#1F3B30}
      .wk-crumbs > button + button::before{content:'›';margin-right:.45rem;color:#8A7355;font-weight:400}
      .wk-crumbs .tvm-menu button{color:#3E2B1F;background:#FFFBF2}
      .wk-b{flex:1;padding:.8rem 1.1rem 1.2rem}
      .wk iframe{display:block;width:100%;height:70vh;border:0;background:transparent}
      @media (max-width:767px){ .wk-lb{padding:0} .wk{min-height:100%;border-radius:0} .wk-h{top:0;border-radius:0} .wk-b{padding:.5rem 3px 1rem} .wk-top{flex-wrap:wrap} .wk-top b{flex-basis:calc(100% - 2.6rem);order:0;font-size:.95rem} .wk-top button.x{order:1}
        .wk-top button{order:2} .wk-top button.next{flex:1;overflow:hidden;text-overflow:ellipsis} }
      .ad-sys summary{cursor:pointer;list-style:none;display:flex;gap:.5rem;align-items:center;font-weight:700;color:#3E2B1F}
      .ad-sys summary::-webkit-details-marker{display:none}
    </style>
    <div class="ad2">
      <section class="ad-c tv-bleed"><h3>Your day <small><?= $done ?> of <?= count($steps) ?> steps clear · work down the list, top to bottom</small></h3>
        <?php foreach ($steps as $i => $st): $n = $stepN($st); $isDone = $n === 0; ?>
        <div class="ad-step<?= $isDone ? ' done' : '' ?>" id="step-<?= $e($st[0]) ?>" data-step="<?= $e($st[0]) ?>" data-title="<?= $e($st[1]) ?>" data-left="<?= (int) $n ?>">
          <div class="ad-sh"><span class="ad-num"><?= $isDone ? '✓' : $i + 1 ?></span>
            <span class="ad-sm"><b><?= $e($st[1]) ?></b><span><?= $e($st[2]) ?></span></span>
            <span class="ad-cnt"><?= $isDone ? 'Clear' : ($n > 999 ? '999+' : $n) ?></span>
            <a class="ad-go" data-work="<?= $e($st[0]) ?>" href="<?= $e($st[3] && !$isDone ? $st[3][0][4] : $st[4]) ?>"><?= $e($isDone ? 'Open' : $st[5]) ?></a></div>
          <?php if ($st[3]): ?><details<?= !$isDone && count($st[3]) > 1 ? '' : '' ?>><summary><?= count($st[3]) ?> kind<?= count($st[3]) === 1 ? '' : 's' ?> of work here</summary>
            <?php foreach ($st[3] as $ti => [$g, $cnt, $title, $why, $url, $btn]): ?><a class="ad-sub" data-work="<?= $e($st[0]) ?>" data-task="<?= (int) $ti ?>" href="<?= $e($url) ?>"><i><?= (int) $cnt ?></i><span><b style="font-weight:600"><?= $e($title) ?></b> · <?= $e($why) ?></span></a><?php endforeach; ?>
          </details><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </section>
      <section class="ad-c tv-bleed" id="ad-inbox"><h3>Inbox <small>what came in, newest first</small></h3>
        <?php foreach ($inbox as [$n, $text, $url]): ?><a class="ad-r" href="<?= $e($url) ?>"><span class="ad-k">For you</span><span><?= (int) $n ?> <?= $e($text) ?></span></a><?php endforeach; ?>
        <?php if (!$stream && !$inbox): ?><p class="ad-empty">Nothing new in the last two weeks.</p><?php endif; ?>
        <?php foreach ($stream as [$when, $k, $text, $url]): ?><a class="ad-r" data-t="<?= $e((string) strtotime($when)) ?>" href="<?= $e($url) ?>"><span class="ad-k"><?= $e($k) ?></span><span><?= $e($text) ?><small><?= $e($ago($when)) ?></small></span></a><?php endforeach; ?>
        <a class="ad-r" href="/user/dashboard.php#addressbook:messages"><span class="ad-k">Messages</span><span>Your own messages and Address Book</span></a>
      </section>
    </div>
    <section class="ad-c tv-bleed" style="margin-top:.8rem"><h3>How the business is doing</h3>
      <div class="ad-ms">
        <?php foreach ($biz as [$label, $n, $sub, $url]): ?><a class="ad-m" href="<?= $e($url) ?>"><b><?= number_format((int) $n) ?></b><span><?= $e($label) ?></span><small><?= $e($sub) ?></small></a><?php endforeach; ?>
        <?php foreach ($metrics as [$label, $now, $before, $unit, $url]):
          $tr = $now === null || $before === null ? '' : ($now > $before ? '<i class="up">▲ ' . ($unit ? ($now - $before) . ' pts' : ($before ? '+' . (int) round(100 * ($now - $before) / $before) . '%' : 'new')) . '</i>' : ($now < $before ? '<i class="dn">▼ ' . ($unit ? ($before - $now) . ' pts' : (int) round(100 * ($before - $now) / max(1, $before)) . '%') . '</i>' : '<i class="eq">no change</i>')); ?>
          <a class="ad-m" href="<?= $e($url) ?>"><b><?= $now === null ? '–' : $e(number_format((int) $now) . $unit) ?></b><span><?= $e($label) ?></span><?= $tr ?></a>
        <?php endforeach; ?>
      </div>
      <p class="ad-empty" style="margin-top:.4rem">The last 7 days, against the 7 before.</p>
    </section>
    <details class="ad-c ad-sys tv-bleed" style="margin-top:.8rem"><summary><span class="ad-dot <?= $sysBad ? 'warn' : '' ?>"></span>System: <?= $sysBad ? count($sysBad) . ' part' . (count($sysBad) === 1 ? '' : 's') . ' need a look' : 'everything is running' ?> <small style="font-weight:400;color:var(--tv-meta,#5E4F3C)">· show all</small></summary>
      <?php foreach ($status as [$sx, $name, $line, $url]): ?><a class="ad-r" href="<?= $e($url) ?>"><span class="ad-dot <?= $e($sx) ?>"></span><span><b style="font-weight:600"><?= $e($name) ?></b><small><?= $e($line) ?></small></span></a><?php endforeach; ?>
    </details>
    <script>
      // A step's work opens in a pop-up over the dashboard (Jason, 2026-10-14: "The standard should be popup lightbox so I can
      // work the task and once complete closes the popup so I can move to the next section of the process… Show steps as
      // breadcrumbs so if I need to go between steps I can easily"). The breadcrumbs are the steps of the work in front of
      // you: the step's kinds of work, and the tool's own sections (Sales: Calls today › All prospects › …), not the day's
      // six steps. Next walks them, then moves on to the next step with work; Done (or ×, or Esc) closes it with fresh
      // counts. The work grows to its full height, so the pop-up scrolls like a page, with one scroll bar.
      (function () {
        var FLOW = <?= json_encode($flow, JSON_UNESCAPED_UNICODE) ?>;
        var lb = null, cur = [0, 0], key = null, tabs = [], tab = null, wantTab = null;
        function esc(x) { return String(x == null ? '' : x).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
        function src(url) { var h = url.indexOf('#') >= 0 ? url.slice(url.indexOf('#')) : '', b = h ? url.slice(0, url.indexOf('#')) : url; return b + (b.indexOf('?') >= 0 ? '&' : '?') + 'embed=1' + h; }
        function nextOf(si, ti) {   // the next task with work: the rest of this step, then the next steps
          for (var i = si; i < FLOW.length; i++) for (var j = (i === si ? ti + 1 : 0); j < FLOW[i].tasks.length; j++) if (FLOW[i].tasks[j].n > 0) return [i, j];
          return null;
        }
        function prevOf(si, ti) { if (ti > 0) return [si, ti - 1]; if (si > 0) return [si - 1, FLOW[si - 1].tasks.length - 1]; return null; }
        function tabAt() { for (var i = 0; i < tabs.length; i++) if (tabs[i].k === tab) return i; return -1; }
        function frame() { return lb && lb.querySelector('iframe'); }
        // the tool's own sections (an admin area page's tabs) become the breadcrumbs; its own row and heading are hidden
        function readTabs() {
          tabs = []; tab = null;
          var f = frame(), d; try { d = f.contentDocument; } catch (x) { d = null; }
          if (!d || !d.body) return;
          if (!d.getElementById('wk-in')) { var st = d.createElement('style'); st.id = 'wk-in'; st.textContent = '.hb-tabs,.hb-h1{display:none!important}.hb-intro{margin-top:0}'; d.head.appendChild(st); }
          var seen = {};
          d.querySelectorAll('[data-hgo]').forEach(function (a) {
            var k = a.getAttribute('data-hgo'); if (seen[k]) return; seen[k] = 1;
            var b = a.querySelector('b'), label = ''; a.childNodes.forEach(function (n) { if (n !== b) label += n.textContent; });
            tabs.push({ k: k, title: label.trim(), n: b ? b.textContent : '' });
            if (a.classList.contains('on')) tab = k;
          });
          if (tabs.length < 2) { tabs = []; tab = null; return; }
          if (!tab) tab = tabs[0].k;
          if (!d.__wk) { d.__wk = true; d.addEventListener('tv-hub-show', function (e) { tab = e.detail; drawCrumbs(); }); }
          if (wantTab) { var w = wantTab === 'last' ? tabs[tabs.length - 1].k : wantTab; wantTab = null; if (w !== tab) showTab(w); }
        }
        function showTab(k) { var f = frame(); try { if (f.contentWindow.tvHubShow) f.contentWindow.tvHubShow(k); } catch (x) {} tab = k; drawCrumbs(); lb.scrollTop = 0; }
        function crumb(attr, title, n, on) { return '<button type="button" ' + attr + ' class="' + (on ? 'on' : '') + (n === 0 ? ' done' : '') + '">' + esc(title) + (n === '' || n == null ? '' : '<i>' + (n === 0 ? '✓' : esc(n)) + '</i>') + '</button>'; }
        function drawCrumbs() {
          var st = FLOW[cur[0]], items = [];
          st.tasks.forEach(function (x, j) {
            if (j === cur[1] && tabs.length) tabs.forEach(function (t) { items.push(crumb('data-wk-tab="' + esc(t.k) + '"', (st.tasks.length > 1 ? x.title + ': ' : '') + t.title, t.n, t.k === tab)); });
            else if (st.tasks.length > 1) items.push(crumb('data-wk-task="' + j + '"', x.title, x.n, j === cur[1]));
          });
          var nav = lb.querySelector('[data-wk-crumbs]');
          nav.hidden = items.length < 2; nav.innerHTML = items.join('');
          var ti = tabAt(), nx = nextOf(cur[0], cur[1]), nb = lb.querySelector('[data-wk-next]');
          nb.textContent = tabs.length && ti >= 0 && ti < tabs.length - 1 ? 'Next: ' + tabs[ti + 1].title + ' ▸'
            : nx ? (nx[0] === cur[0] ? 'Next: ' + FLOW[nx[0]].tasks[nx[1]].title : 'Next step: ' + FLOW[nx[0]].title) + ' ▸' : 'Done ✓';
          lb.querySelector('[data-wk-prev]').disabled = !(tabs.length && ti > 0) && !prevOf(cur[0], cur[1]);
        }
        function draw() {
          var st = FLOW[cur[0]], t = st.tasks[cur[1]];
          lb.querySelector('[data-wk-title]').textContent = st.title + (st.tasks.length > 1 ? ': ' + t.title : '');
          var f = frame(), want = src(t.url);
          if (f.getAttribute('src') !== want) { tabs = []; tab = null; f.setAttribute('src', want); }
          f.title = st.title;
          drawCrumbs(); lb.scrollTop = 0;
        }
        function go(to, last) { cur = to; key = FLOW[to[0]].key; wantTab = last ? 'last' : null; draw(); }
        function close() { if (!lb) return; lb.remove(); lb = null; document.documentElement.style.overflow = ''; document.removeEventListener('keydown', keys, true); var u = new URL(location.href); u.searchParams.delete('open'); u.hash = 'step-' + key; history.replaceState(null, '', u.toString()); location.reload(); }
        function keys(e) { if (e.key === 'Escape' && !document.querySelector('.tv-ask-lb, .tv-pk')) close(); }
        function open(si, ti) {
          if (window.top !== window) return;   // the dashboard shown inside a frame never opens a pop-up of its own
          cur = [si, ti]; key = FLOW[si].key;
          if (!lb) {
            lb = document.createElement('div'); lb.className = 'wk-lb'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true'); lb.setAttribute('aria-label', 'Your day');
            lb.innerHTML = '<div class="wk"><div class="wk-h"><div class="wk-top"><b data-wk-title></b><button type="button" data-wk-prev>◂ Back</button><button type="button" class="next" data-wk-next></button><button type="button" class="x" data-wk-close title="Close" aria-label="Close">×</button></div>'
              + '<nav class="wk-crumbs" data-more data-wk-crumbs aria-label="Steps of this work" hidden></nav></div>'
              + '<div class="wk-b"><iframe data-host data-no-back title="Work"></iframe></div></div>';
            document.body.appendChild(lb);
            document.documentElement.style.overflow = 'hidden';   // the pop-up scrolls, not the dashboard behind it
            var f = frame();
            if (window.TvAsk && TvAsk.fitFrame) TvAsk.fitFrame(f);   // full height: no scroll bar inside the pop-up
            f.addEventListener('load', function () { readTabs(); drawCrumbs(); });
            lb.addEventListener('click', function (e) {
              var b;
              if (e.target === lb || e.target.closest('[data-wk-close]')) { close(); return; }
              if ((b = e.target.closest('[data-wk-tab]'))) { showTab(b.getAttribute('data-wk-tab')); return; }
              if ((b = e.target.closest('[data-wk-task]'))) { go([cur[0], +b.getAttribute('data-wk-task')]); return; }
              if (e.target.closest('[data-wk-next]')) {
                var ti = tabAt(); if (tabs.length && ti >= 0 && ti < tabs.length - 1) { showTab(tabs[ti + 1].k); return; }
                var nx = nextOf(cur[0], cur[1]); if (!nx) { close(); return; } go(nx); return;
              }
              if (e.target.closest('[data-wk-prev]')) {
                var tj = tabAt(); if (tabs.length && tj > 0) { showTab(tabs[tj - 1].k); return; }
                var pv = prevOf(cur[0], cur[1]); if (pv) go(pv, true);
              }
            });
            document.addEventListener('keydown', keys, true);
          }
          draw();
        }
        document.addEventListener('click', function (e) {
          var a = e.target.closest('a[data-work]');
          if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0 || window.top !== window) return;
          e.preventDefault();
          var si = FLOW.findIndex(function (x) { return x.key === a.getAttribute('data-work'); }), ti = 0;
          if (a.hasAttribute('data-task')) ti = +a.getAttribute('data-task');
          else { ti = FLOW[si].tasks.findIndex(function (x) { return x.n > 0; }); if (ti < 0) ti = 0; }
          open(si, ti);
        });
        var want = new URL(location.href).searchParams.get('open');
        if (want) { var si0 = FLOW.findIndex(function (x) { return x.key === want; }); if (si0 >= 0) open(si0, Math.max(0, FLOW[si0].tasks.findIndex(function (x) { return x.n > 0; }))); }
      })();
      // what came in since your last visit is marked (kept in this browser)
      (function () {
        var k = 'tv_admin_seen', last = 0; try { last = +localStorage.getItem(k) || 0; } catch (x) {}
        document.querySelectorAll('#ad-inbox [data-t]').forEach(function (a) { if (last && +a.getAttribute('data-t') > last) a.classList.add('new'); });
        try { localStorage.setItem(k, String(Math.floor(Date.now() / 1000))); } catch (x) {}
      })();
    </script>
    <?php return (string) ob_get_clean();
}
