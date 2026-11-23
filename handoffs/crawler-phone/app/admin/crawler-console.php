<?php
// app/admin/crawler-console.php — Insights → Crawler: the crawler console (decisions/0160 §4; handoffs/crawler-revamp.md
// Phase 2; Jason, 2026-11-13: in Insights). Each workflow with its capture tasks: what each is waiting on, what the last
// runs did with it (done, nothing to do, not reached, failed and why), its share of each run, its spend this month; pause
// or resume a workflow, change its share, run a task first, switch a task off, put failed work back. Below, the last runs,
// task by task. Times show where they happened (each <time> is drawn in the viewer's zone by js/tv-time.js).
declare(strict_types=1);

function tv_admin_mod_crawler(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/OutsideServices.php';   // plainWhy: a server's error page in words, never its HTML
    require_once __DIR__ . '/../lib/crawler/CrawlService.php';
    $e = 'tv_w_e';
    if (!CrawlService::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Not ready:">The crawler service\'s database update hasn\'t run yet: it runs within the hour, or open System Configuration → Database updates. Until then, <a class="underline" href="/admin/crawler.php">the earlier overview</a> shows the crawler.</p>';
    // a run's own page, and a workflow's records for a day (decisions/0171 §4, the ledger; Jason, 2026-11-18: "queue the
    // crawler run records fix"): each run opens to what it touched
    if (isset($_GET['run']) && ctype_digit((string) $_GET['run'])) return tv_crawler_run_page($pdo, (int) $_GET['run']);
    if (isset($_GET['records'])) return tv_crawler_records_page($pdo, (string) $_GET['records'], (string) ($_GET['day'] ?? 'today'));
    [$can, $why] = Access::can('insights.crawler.edit', ['min' => 'staff', 'depts' => ['ops']]);
    $uid = (int) (Auth::currentUserId() ?? 0);
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cr_do']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            $wf = (string) ($_POST['wf'] ?? ''); $task = (string) ($_POST['task'] ?? '');
            switch ((string) $_POST['cr_do']) {
                case 'pause': CrawlService::pause($pdo, $wf, true, $uid, (string) ($_POST['why'] ?? '')); $say = $wf . ' is paused: the next runs leave its tasks alone.'; break;
                case 'resume': CrawlService::pause($pdo, $wf, false, $uid); $say = $wf . ' is running again from the next run.'; break;
                case 'share': CrawlService::setShare($pdo, $wf, (int) ($_POST['share'] ?? 0)); $say = $wf . '\'s share is saved: the next run plans with it.'; break;
                case 'first': CrawlService::runNext($pdo, $task); $say = 'It\'s due on the next run.'; break;
                case 'off': case 'on': throw new RuntimeException('A task\'s switch is on its workflow\'s page on the System Control Board now, recorded with who and why: https://traversence.com/admin/config/control/wf-' . (CrawlService::tasks($pdo)[$task]['wf'] ?? 'W1') . '#task-' . $task);
                case 'retry': $n = CrawlService::retry($pdo, $task); $say = $n ? $n . ' put back in the queue, their tries reset.' : 'Nothing had failed.'; break;
                default: throw new RuntimeException('Unknown action.');
            }
            $msg = '<p data-tv-flash="ok">' . $e($say) . '</p>';
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x->getMessage()) . '</p>'; }
    }
    $at = fn (?string $t) => $t ? '<time datetime="' . $e(date('c', (int) strtotime($t))) . '" data-tv-time>' . $e(date('M j, g:i a', (int) strtotime($t))) . '</time>' : 'never';
    $usd = fn (float $v) => '$' . number_format($v, $v > 0 && $v < 0.01 ? 4 : 2);   // always in dollars, never cents (Jason, 2026-11-23: "Cents vs dollars": 65.2¢ read as $62)
    $btn = function (string $do, string $label, array $hidden, string $confirm = '', bool $danger = false) use ($e, $can, $why, $csrf): string {
        $h = '<form method="post" style="display:inline">' . '<input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="cr_do" value="' . $e($do) . '">';
        foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . $e($k) . '" value="' . $e((string) $v) . '">';
        $h .= '<button class="ak-b" type="submit"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . ($confirm !== '' ? ' data-confirm="' . $e($confirm) . '" data-yes="' . $e($label) . '"' . ($danger ? ' data-danger' : '') : '') . '>' . $e($label) . '</button></form>';
        return $h;
    };

    $wfs = CrawlService::workflows($pdo); $tasks = CrawlService::tasks($pdo);
    $stats = CrawlService::taskStats($pdo, 24); $spend = CrawlService::spend($pdo);
    $runs = CrawlService::runs($pdo, 20);
    $h = '<p class="ak-note">The crawler is one service; each workflow asks it for what it needs through its capture tasks (decisions/0160). Every run, every 5 minutes on Railway, '
       . 'the site plans which tasks it works and gives each its share of the run: finding businesses first, then whoever waited longest. A task the run didn\'t reach is never a failure: it goes first next time. '
       . 'A run that couldn\'t work shows as failed on Railway, and in Pulse when runs keep failing.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>' . $msg;

    // where it stands
    $st = CrawlService::stalled($pdo);
    $last = $runs[0] ?? null;
    if ($st) $h .= '<p data-tv-status="bad" data-tv-label="' . $e($st[1]) . ':">' . $e($st[2]) . '</p>';
    elseif (!$last) $h .= '<p data-tv-status="info" data-tv-label="No runs yet:">The worker hasn\'t run through the service yet. It does from its next deploy; until then it runs its old fixed order (<a class="underline" href="/admin/crawler.php">the earlier overview</a>).</p>';
    else $h .= '<p data-tv-status="' . (in_array($last['status'], ['ok', 'idle', 'partial'], true) ? 'ok' : ($last['status'] === 'running' ? 'info' : 'bad')) . '" data-tv-label="Last run:">'
        . $at((string) $last['started_at']) . ': ' . $e((string) $last['status']) . ($last['why'] ? ' (' . $e((string) $last['why']) . ')' : '') . '.</p>';

    // the day in numbers
    $day = ['ok' => 0, 'partial' => 0, 'idle' => 0, 'starved' => 0, 'failed' => 0, 'abandoned' => 0];
    foreach ($pdo->query("SELECT status, COUNT(*) n FROM crawl_runs WHERE started_at >= NOW() - INTERVAL 24 HOUR GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) $day[$k] = (int) $v;
    $nr = 0; foreach ($stats as $s) $nr += (int) ($s['not_reached'] ?? 0);
    $month = 0.0; foreach ($spend as $by) foreach ($by as $svc => [$u, $c]) $month += $svc === 'railway' ? 0 : $c;
    $rail = 0; foreach ($spend as $by) $rail += (int) ($by['railway'][0] ?? 0);
    $h .= '<div class="ak-metrics">';
    foreach ([['Runs, last 24 h', array_sum($day)], ['Worked', $day['ok'] + $day['partial']], ['Nothing to do', $day['idle']], ['Starved or failed', $day['starved'] + $day['failed'] + $day['abandoned']],
              ['Tasks not reached', $nr], ['Paid calls this month', $usd($month)], ['Railway time this month', round($rail / 3600, 1) . ' h']] as [$l, $v])
        $h .= '<div class="ak-metric tv-bleed"><b>' . $e((string) $v) . '</b><span>' . $e($l) . '</span></div>';
    $h .= '</div>';

    // what each task's own queue holds today
    $q = [];
    $one = function (string $sql) use ($pdo): array { try { return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR)); } catch (Throwable $x) { return []; } };
    $q['coverage'] = $one("SELECT CONCAT('coverage ', status), COUNT(*) FROM " . Database::t('crawl_jobs') . " GROUP BY status") + $one("SELECT CONCAT('town ', status, IF(attempts >= 3, ' (3+ tries)', '')), COUNT(*) FROM town_crawl_jobs GROUP BY 1");
    $q['identity'] = $one("SELECT CONCAT(status, IF(attempts >= 3, ' (set aside)', '')), COUNT(*) FROM " . Database::t('identity_jobs') . " GROUP BY 1");
    $q['source'] = $one("SELECT status, COUNT(*) FROM " . Database::t('reference_sources') . " GROUP BY status");
    // W2's questions for a person (decisions/0160 W2): what its checks sent to Review and is still waiting
    $w2review = [];
    foreach ($one("SELECT CONCAT(device_hash, ' ', kind), COUNT(*) FROM suggestions WHERE status = 'new' AND device_hash IN ('crawler-refresh', 'crawler-dupes', 'system:google') GROUP BY 1") as $k => $n)
        $w2review[['crawler-refresh closed' => 'possibly closed', 'crawler-refresh fix' => 'changed details, moved or dead websites', 'crawler-dupes same' => 'live duplicates', 'system:google closed' => 'closed on Google'][$k] ?? $k] = $n;
    // W5's own queue (guides a visitor asked for, built on the site): where each stands, and the failed ones' tries
    $w5 = $one("SELECT CASE WHEN status = 'failed' AND tries > 3 THEN 'failed, waiting for our team' WHEN status = 'failed' THEN 'failed, tried again by itself' ELSE status END, COUNT(*) FROM guide_requests GROUP BY 1")
        ?: $one("SELECT status, COUNT(*) FROM guide_requests GROUP BY status");
    $req = []; foreach ($pdo->query("SELECT wf, status, COUNT(*) n FROM crawl_requests GROUP BY wf, status")->fetchAll(PDO::FETCH_ASSOC) as $r) $req[$r['wf']][$r['status']] = (int) $r['n'];
    $kv = fn (array $a) => $a ? implode(', ', array_map(fn ($k, $v) => $e((string) $k) . ' ' . (int) $v, array_keys($a), $a)) : '';
    $svcName = ['claude' => 'Claude', 'tavily' => 'Tavily search', 'brave' => 'Brave search', 'tavily_extract' => 'Tavily page reads', 'google_places' => 'Google Places', 'railway' => 'Railway'];

    // what a workflow brought in: one dropdown each for the workflow and the day ("W1 today")
    $h .= '<form method="get" class="cr-rec"><b>What a workflow touched:</b> <label class="ak-dd"><select name="records" aria-label="Workflow">'
        . implode('', array_map(fn ($k, $w) => '<option value="' . $e($k) . '"' . ($k === 'W1' ? ' selected' : '') . '>' . $e($k . ' ' . $w['name']) . '</option>', array_keys($wfs), $wfs))
        . '</select></label> <label class="ak-dd"><select name="day" aria-label="When"><option value="today" selected>Today</option><option value="yesterday">Yesterday</option><option value="7">The last 7 days</option></select></label> '
        . '<button class="ak-b" type="submit">Show its records</button></form>';

    foreach ($wfs as $wf => $w) {
        $mine = array_filter($tasks, fn ($t) => $t['wf'] === $wf);
        $sp = $spend[$wf] ?? [];
        if (!$mine && !$sp && !isset($req[$wf]) && !($wf === 'W5' && $w5)) continue;   // a workflow that doesn't use the crawler yet (W4, W5, W7, W13…) shows once it does
        $paused = (int) $w['paused'] === 1;
        $body = '';
        if ($paused) $body .= '<p data-tv-status="info" data-tv-label="Paused:">Since ' . $at((string) $w['paused_at']) . ($w['paused_why'] ? ': ' . $e((string) $w['paused_why']) : '') . '. Its work waits, untouched.</p>';
        $body .= '<p class="ak-note">' . ($paused ? $btn('resume', 'Resume', ['wf' => $wf]) : $btn('pause', 'Pause', ['wf' => $wf], 'Pause ' . $w['name'] . '? Its tasks are left alone until it\'s resumed.', true))
               . ' <form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="cr_do" value="share"><input type="hidden" name="wf" value="' . $e($wf) . '">'
               . '<label>Share of each run <input class="ak-in" type="number" name="share" min="0" max="100" value="' . (int) $w['share_pct'] . '" style="width:4.5rem"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>%</label> '
               . '<button class="ak-b" type="submit"' . ($can ? '' : ' disabled title="' . $e($why) . '"') . '>Save share</button></form></p>';
        if ($sp) $body .= '<p class="ak-note">This month: ' . implode(' · ', array_map(fn ($svc, $v) => $e($svcName[$svc] ?? $svc) . ' ' . number_format((int) $v[0]) . ' call' . ((int) $v[0] === 1 ? '' : 's') . ', ' . $usd((float) $v[1]), array_keys($sp), $sp)) . ' (failed calls included; prices beyond Claude\'s are estimates).</p>';
        if (isset($req[$wf])) $body .= '<p class="ak-note">Requests to the crawler: ' . $kv($req[$wf]) . '.</p>';
        if ($wf === 'W5' && $w5) $body .= '<p class="ak-note">Guides visitors asked for: ' . $kv($w5) . '. Built on the site, not by the worker; a failed build is tried again after 30 minutes, 2 hours and 8 hours, then waits in Pulse.</p>';
        if ($wf === 'W2' && $w2review) $body .= '<p class="ak-note">Waiting in Review from it: ' . $kv($w2review) . ' (<a class="underline" href="/admin/local/review">Review</a>; nothing here changes a listing by itself).</p>';
        if ($mine) {
            $body .= '<div class="ak-aa"><table><thead><tr><th>Capture task</th><th>Every</th><th>Last served</th><th>Last 24 h</th><th>Its queue</th><th></th></tr></thead><tbody>';
            foreach ($mine as $t => $r) {
                $s = $stats[$t] ?? [];
                $day24 = array_filter(['done' => $s['done'] ?? 0, 'nothing to do' => $s['idle'] ?? 0, 'not reached' => $s['not_reached'] ?? 0, 'failed' => $s['failed'] ?? 0, 'off' => $s['off'] ?? 0]);
                $every = (int) $r['every_minutes'] >= 1440 ? 'nightly, when due' : ((int) $r['every_minutes'] >= 60 ? round((int) $r['every_minutes'] / 60) . ' h' : (int) $r['every_minutes'] . ' min');
                // its switch has one home, its workflow's page on the board, recorded with who and why (decisions/0175 §6:
                // "Insights → Crawler's on and off become links to the same switch"), opened in the side panel
                $acts = $btn('first', 'Run first', ['task' => $t]) . ' <a class="ak-b" href="/admin/config/control/wf-' . $e((string) $r['wf']) . '#task-' . $e((string) $t) . '" data-peek-frame>' . ((int) $r['enabled'] ? 'Switch off…' : 'Switch on…') . '</a>'
                      . (in_array($t, ['coverage', 'identity'], true) ? ' ' . $btn('retry', 'Retry failed', ['task' => $t]) : '');
                $body .= '<tr><td data-th="Capture task"><b>' . $e((string) $r['name']) . '</b>' . ((int) $r['enabled'] ? '' : ' <em>(off)</em>') . '<br><small>' . $e(rtrim((string) $r['what'], '.')) . '.' . (trim((string) $r['from_where']) !== '' ? ' From ' . $e((string) $r['from_where']) . '.' : '') . '</small>'
                       . ($r['last_outcome'] ? '<br><small>Last: ' . $e(CrawlService::OUTCOMES[$r['last_outcome']] ?? (string) $r['last_outcome']) . ($r['last_why'] ? ' (' . $e(OutsideServices::plainWhy((string) $r['last_why'])) . ')' : '') . '</small>' : '') . '</td>'
                       . '<td data-th="Every">' . $e($every) . '</td><td data-th="Last served">' . $at($r['last_served_at']) . '</td><td data-th="Last 24 h">' . ($day24 ? $kv($day24) : 'no runs') . '</td><td data-th="Its queue">' . (($qq = $kv($q[$t] ?? [])) !== '' ? $qq : 'nothing waiting') . '</td><td class="cr-acts">' . $acts . '</td></tr>';
            }
            $body .= '</tbody></table></div>';
        }
        $tone = $paused ? 'info' : 'ok';
        $h .= tv_admin_fold($wf . '. ' . (string) $w['name'], ($paused ? 'Paused · ' : '') . (int) $w['share_pct'] . '% share', $tone, '', $body, false);
    }

    // the last runs, task by task
    if ($runs) {
        $body = '<div class="ak-aa"><table><thead><tr><th>Started</th><th>Status</th><th>Time</th><th>Fetches</th><th>Searches</th><th>What each task did</th></tr></thead><tbody>';
        require_once __DIR__ . '/../lib/ControlLedger.php'; $rc = ControlLedger::runCounts($pdo, array_column($runs, 'id'));
        foreach ($runs as $r) {
            $items = implode('; ', array_map(fn ($i) => $e((string) $i['task']) . ' ' . $e(strtolower(CrawlService::OUTCOMES[$i['outcome']] ?? (string) $i['outcome'])) . ((int) $i['seconds'] ? ' ' . (int) $i['seconds'] . 's' : '') . ($i['why'] ? ' (' . $e(mb_strimwidth(OutsideServices::plainWhy((string) $i['why']), 0, 90, '…')) . ')' : ''), $r['items']));
            $body .= '<tr><td data-th="Started">' . $at((string) $r['started_at']) . '</td><td data-th="Status">' . $e((string) $r['status']) . ($r['why'] ? '<br><small>' . $e(mb_strimwidth(OutsideServices::plainWhy((string) $r['why']), 0, 160, '…')) . '</small>' : '') . '</td><td data-th="Time">' . (int) $r['seconds'] . 's</td><td data-th="Fetches">' . (int) $r['fetches'] . '</td><td data-th="Searches">' . (int) $r['searches'] . '</td><td data-th="What each task did"><small>' . ($items ?: '–') . '</small>'
                . '<br><a class="underline" href="?run=' . (int) $r['id'] . '">' . (($n = (int) ($rc[(int) $r['id']] ?? 0)) ? 'Its ' . number_format($n) . ' record' . ($n === 1 ? '' : 's') : 'Open the run') . '</a></td></tr>';
        }
        $h .= tv_admin_fold('The last runs', count($runs) . ' shown', 'info', '', $body . '</tbody></table></div>', false);
    }
    $h .= '<p class="ak-note">Still on <a class="underline" href="/admin/crawler.php">the earlier overview</a> until their workflows move here (handoffs/crawler-revamp.md Phase 3): queueing coverage gaps by hand, the coverage map. Railway: project gracious-sparkle, service traversence, deployed from main.</p>';
    return '<div>' . $h . '</div><style>.ak-aa{overflow-x:auto;margin:.6rem 0}.ak-aa table{width:100%;border-collapse:collapse;font-size:.85rem}.ak-aa th,.ak-aa td{padding:.35rem .5rem;border-bottom:1px solid #E8DCC6;text-align:left;vertical-align:top}.ak-aa td{overflow-wrap:anywhere}.ak-aa .cr-acts{white-space:nowrap}'
        // on a phone each row is a card and each cell a labelled line: nothing wider than the window (decisions/0058 §11;
        // design/table-standard.md, phones; Jason's screenshot 2026-11-23: the task table clipped in the side panel)
        . '@media (max-width:767px){.ak-aa{overflow-x:visible}.ak-aa table,.ak-aa tbody,.ak-aa tr,.ak-aa td{display:block;width:auto}.ak-aa thead{display:none}'
        . '.ak-aa tr{border:1px solid #E8DCC6;border-radius:.5rem;margin:3px 0;padding:.3rem .2rem;background:#FFFDF8}.ak-aa td{border:0;padding:.15rem .4rem}'
        . '.ak-aa td[data-th]::before{content:attr(data-th) ": ";font-weight:600;color:var(--tv-meta,#6b5d49)}.ak-aa td[data-th="Capture task"]::before,.ak-aa td[data-th="Started"]::before{content:none}'
        . '.ak-aa .cr-acts{white-space:normal;display:flex;flex-wrap:wrap;gap:.3rem}}</style>';
}

/** A record the crawler touched: its own page in a Peek (a listing, a Review item), what happened to it. */
function tv_crawler_rec(array $r): string
{
    $e = 'tv_w_e'; $id = (int) $r['ref_id']; $k = (string) $r['kind'];
    $u = $k === 'listing' ? '/admin/listing-edit.php?id=' . $id : ($k === 'review' ? '/admin/listing-intake.php?row=' . $id : '');
    $label = ($k === 'listing' ? 'Listing #' : ($k === 'review' ? 'Review item #' : ucfirst($k) . ' #')) . $id;
    return '<li>' . ($u !== '' ? '<a class="underline" href="' . $e($u) . '" data-peek-frame>' . $e($label) . '</a>' : $e($label)) . ' <small>' . $e((string) $r['what']) . '</small></li>';
}

/** Records grouped by what happened: stored, updated, sent to Review, refused (each a fold with its count). */
function tv_crawler_rec_groups(array $recs, string $only = ''): string
{
    require_once __DIR__ . '/../lib/ControlLedger.php';
    $names = ['stored' => 'Added', 'updated' => 'Changed', 'review' => 'Sent to Review', 'refused' => 'Refused (kept as they were)', 'other' => 'Other'];
    $by = []; foreach ($recs as $r) $by[ControlLedger::whatKind((string) $r['what'])][] = $r;
    $h = '';
    foreach ($names as $k => $label) {
        if (empty($by[$k]) || ($only !== '' && $only !== $k)) continue;
        $h .= '<details class="cr-g"' . ($only !== '' || count($by) === 1 ? ' open' : '') . '><summary><b>' . tv_w_e($label) . '</b> ' . number_format(count($by[$k])) . '</summary><ul class="cr-l">' . implode('', array_map('tv_crawler_rec', $by[$k])) . '</ul></details>';
    }
    return $h;
}

function tv_crawler_rec_css(): string { return '<style>.cr-rec{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin:.6rem 0}.cr-g{margin:.35rem 0}.cr-g>summary{cursor:pointer}.cr-l{margin:.3rem 0 .3rem 1rem;padding:0;list-style:disc;columns:2 18rem;font-size:.9rem}.cr-l li{break-inside:avoid;margin:.1rem 0}.cr-l a{color:#92400E}@media(max-width:600px){.cr-l{columns:1}}</style>'; }

/**
 * One crawler run's page (a record page: the command strip, decisions/0129): where it stands, then each workflow's
 * records, grouped by what happened, each opening in a Peek. "what" narrows it to one kind (Added, Changed…).
 */
function tv_crawler_run_page(PDO $pdo, int $runId): string
{
    require_once __DIR__ . '/../lib/ControlLedger.php'; require_once __DIR__ . '/../lib/ControlAlarms.php'; require_once __DIR__ . '/../ui/command-strip.php'; require_once __DIR__ . '/../lib/crawler/CrawlService.php';
    $e = 'tv_w_e';
    $st = $pdo->prepare('SELECT * FROM crawl_runs WHERE id = ?'); $st->execute([$runId]); $run = $st->fetch(PDO::FETCH_ASSOC);
    $back = '/admin/insights/crawler';
    if (!$run) return '<p data-tv-status="info" data-tv-label="Not found:">Run #' . $runId . ' isn\'t kept any more (runs are kept for a while, their records ' . 30 . ' days by default). <a class="underline" href="' . $back . '">Back to the crawler</a>.</p>';
    $items = $pdo->prepare('SELECT * FROM crawl_run_items WHERE run_id = ? ORDER BY id'); $items->execute([$runId]); $items = $items->fetchAll(PDO::FETCH_ASSOC);
    $f = ControlLedger::forRun($pdo, $runId);
    $ep = []; foreach ($f['calls'] as $c) $ep[(int) $c['id']] = (string) $c['endpoint'];
    $byWf = [];
    foreach ($f['records'] as $r) { $p = ControlAlarms::procOfEndpoint($pdo, $ep[(int) $r['ledger_id']] ?? ''); $byWf[str_starts_with($p, 'wf:') ? substr($p, 3) : 'Other'][] = $r; }
    $only = in_array($_GET['what'] ?? '', ['stored', 'updated', 'review', 'refused'], true) ? (string) $_GET['what'] : '';
    $when = date('M j, g:i a', (int) strtotime((string) $run['started_at']));
    $h = tv_command_strip(['noun' => 'crawler run', 'title' => 'Crawler run #' . $runId, 'back' => $back, 'close' => $back, 'root' => '.cr-run',
        'news' => ['Started ' . $when . ' · ' . (string) $run['status'] . ' · ' . (int) $run['seconds'] . ' s · ' . count($f['records']) . ' record' . (count($f['records']) === 1 ? '' : 's') . ' touched' . ($run['why'] ? ' · ' . (string) $run['why'] : '')],
        'news_tone' => in_array($run['status'], ['ok', 'idle', 'partial'], true) ? 'ok' : 'info', 'news_key' => 'crun-' . $runId]);
    $h .= '<div class="cr-run">';
    // what each task did, with its counts opening their records
    $h .= '<h2 class="hb-h">What each task did</h2><ul class="cr-l" style="columns:1;list-style:none;margin-left:0">';
    foreach ($items as $i) $h .= '<li><b>' . $e((string) $i['wf'] . ' · ' . (string) $i['task']) . '</b>: ' . $e(strtolower(CrawlService::OUTCOMES[$i['outcome']] ?? (string) $i['outcome'])) . ((int) $i['seconds'] ? ', ' . (int) $i['seconds'] . ' s' : '') . ($i['why'] ? ' <small>(' . $e((string) $i['why']) . ')</small>' : '') . '</li>';
    $h .= '</ul>';
    $tot = ['stored' => 0, 'updated' => 0, 'review' => 0, 'refused' => 0]; foreach ($f['records'] as $r) { $k = ControlLedger::whatKind((string) $r['what']); if (isset($tot[$k])) $tot[$k]++; }
    $h .= '<p class="cr-rec">' . implode(' ', array_map(fn ($k, $n) => $n ? '<a class="ak-b' . ($only === $k ? ' on' : '') . '" href="?run=' . $runId . '&what=' . $k . '">' . ['stored' => 'Added', 'updated' => 'Changed', 'review' => 'Sent to Review', 'refused' => 'Refused'][$k] . ' ' . $n . '</a>' : '', array_keys($tot), $tot))
        . ($only !== '' ? ' <a class="underline" href="?run=' . $runId . '">Show them all</a>' : '') . '</p>';
    if (!$f['records']) $h .= '<p data-tv-status="info" data-tv-label="No records:">This run touched no listing or Review item' . (count($f['calls']) ? ' (' . count($f['calls']) . ' call' . (count($f['calls']) === 1 ? '' : 's') . ' to the site, none that stored, changed or sent anything)' : '') . '. Records are kept ' . 30 . ' days by default (System Configuration → Thresholds & gates).</p>';
    ksort($byWf);
    foreach ($byWf as $wf => $recs) $h .= '<h2 class="hb-h">' . $e($wf) . '</h2>' . tv_crawler_rec_groups($recs, $only);
    $h .= '<p class="ak-note">Each line is what the site recorded at the call: ids and what changed (field names, never a person\'s details or a page\'s text); a field a person entered is never overwritten (decisions/0164), and it says so. <a class="underline" href="' . $back . '">Back to the crawler</a>.</p>';
    return $h . '</div>' . tv_crawler_rec_css();
}

/** A workflow's records for a day ("W1 today"), grouped by run, newest first. Days are UTC days. */
function tv_crawler_records_page(PDO $pdo, string $wf, string $day): string
{
    require_once __DIR__ . '/../lib/ControlLedger.php';
    $e = 'tv_w_e'; $wf = preg_match('/^W\d{1,2}$/', $wf) ? $wf : 'W1';
    [$from, $to, $label] = $day === 'yesterday' ? [gmdate('Y-m-d', strtotime('-1 day')), gmdate('Y-m-d'), 'yesterday']
        : ($day === '7' ? [gmdate('Y-m-d', strtotime('-6 days')), gmdate('Y-m-d', strtotime('+1 day')), 'the last 7 days'] : [gmdate('Y-m-d'), gmdate('Y-m-d', strtotime('+1 day')), 'today']);
    $recs = ControlLedger::records($pdo, $wf, $from . ' 00:00:00', $to . ' 00:00:00', 1000);
    $byRun = []; foreach ($recs as $r) $byRun[(int) $r['run_id']][] = $r;
    $h = '<p><a class="underline" href="/admin/insights/crawler">← The crawler</a></p><h2 class="hb-h">' . $e($wf) . ', ' . $e($label) . ' (UTC): ' . number_format(count($recs)) . ' record' . (count($recs) === 1 ? '' : 's') . (count($recs) >= 1000 ? ' (the newest 1,000)' : '') . '</h2>';
    if (!$recs) $h .= '<p data-tv-status="info" data-tv-label="Nothing yet:">' . $e($wf) . ' touched no listing or Review item ' . $e($label) . '. Its runs are on <a class="underline" href="/admin/insights/crawler">the crawler page</a>; records are kept 30 days by default.</p>';
    $h .= tv_crawler_rec_groups($recs);
    if (count($byRun) > 1 || ($byRun && array_key_first($byRun))) {
        $h .= '<h2 class="hb-h">By run</h2><ul class="cr-l" style="columns:1">';
        foreach ($byRun as $rid => $rs) $h .= '<li>' . ($rid ? '<a class="underline" href="?run=' . $rid . '">Run #' . $rid . '</a>' : 'Outside a run') . ': ' . count($rs) . '</li>';
        $h .= '</ul>';
    }
    return '<div>' . $h . '</div>' . tv_crawler_rec_css();
}

