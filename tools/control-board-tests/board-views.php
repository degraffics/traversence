<?php
// The board's views (the control board's build, step 4; plan §2.1 rows 32 and 38, §4 D5, D15, D16, D17, D22's page), on the
// bench: "Is it delivering?" (03-cards.md §4, §27; gauge-lifecycle.md §7), Development (gauge-lifecycle.md §1–§6) with Site
// health's list (check-register.md "one notification path"), Incidents and an IR's page (03 §7; 02 P12), the folded
// report (D16; live-reads C3), Copy marks on every section (D15), one count with an acknowledged trip in it (D5; g4 F1),
// and W13 a list and W14 Not built wherever the board names workflows' states (workflows-page.md §6). The rendered checks
// at 1280 and 390 are board-views.cjs.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php board-views.php <root>/app
declare(strict_types=1);
$app = rtrim($argv[1], '/');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
// the seed (a trip on W1 opened as IR-0001 and acknowledged, a warning, Site health with two findings), then the board
passthru('php ' . escapeshellarg(__DIR__ . '/board-views-seed.php') . ' ' . escapeshellarg($app) . ' > /dev/null');
require $app . '/lib/Database.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
ControlGauges::$source = dirname(__DIR__, 2) . '/site-audit/results/gauges.json';
$html = tv_admin_mod_control($pdo);
$txt = fn (string $h) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $h), ENT_QUOTES)));
$sec = function (string $id) use ($html): string { $i = strpos($html, 'id="' . $id . '"'); return $i === false ? '' : substr($html, $i, 60000); };

// ---- D5: one count on every surface, an acknowledged trip in it
$ac = ControlAlarms::counts($pdo);
$t('D5: the acknowledged trip is in the one count (ControlAlarms::counts)', $ac['trip'] === 1 && $ac['warning'] === 1, $ac);
$t('D5: the board\'s Alarms panel and stage line count the same (no ack_at filter of its own)', !str_contains(file_get_contents($app . '/admin/control-console.php'), "cleared_at IS NULL AND ack_at IS NULL AND level IN ('trip','warning')") && str_contains($txt(substr($sec('cb-alarms'), 0, 3000)), '1 trip, 1 warning open'), mb_substr($txt($sec('cb-alarms')), 0, 80));
$t('D5: the acknowledged trip says "Seen: by Jason" on its row and stays open (02 row 96: the light stays red)', (bool) preg_match('/id="cb-alarm-1"[^>]*data-astate="ack"[^>]*data-alevel="trip".*?Seen:.*?by Jason/s', $html));
$js = file_get_contents(dirname($app) . '/website/js/admin-control.js');
$t('D5: the Open filter keeps every open trip, seen, pressed or not, and the unseen warnings; notices under Notices (board-phone: one count, ControlAlarms::counts)', str_contains($js, "live = st !== 'cleared' && (lv === 'trip' || st === 'open')") && str_contains($js, "(k === 'open' && live && lv !== 'notice')"));

// ---- "Is it delivering?" first
// since the opening view (2026-11-23, step3/board/index.html; Jason's ruling, 03 §27): the views are the rail, before the
// stages, Development then Board ("Is it delivering?", shown on load); Incidents is among Diagnostics' tables
$t('the views are the rail before the stages: Development, Board ("Is it delivering?", shown on load), Incidents among the tables (03 §4, §27)', (bool) preg_match('/cb-rail.*?data-cb-tab="development".*?data-cb-tab="deliver".*?aria-label="The board\'s stages.*?data-cb-tab="incidents"/s', $html) && str_contains($html, 'data-tab="deliver"') && strpos($html, 'cb-rail') < strpos($html, 'aria-label="The board\'s stages'));
$q3 = $txt(substr($sec('cb-q3'), 0, 4000));
$t('the three questions in one line: healthy, held, needs me (03 §4)', str_contains($q3, 'Is it healthy') && str_contains($q3, 'What\'s held and why') && str_contains($q3, 'What needs me'), mb_substr($q3, 0, 200));
$t('…the light stays honest after Acknowledge: "1 trip (seen by Jason)", red (g4 F1)', str_contains($q3, '1 trip (seen by Jason)') && (bool) preg_match('/id="cb-q3".*?data-tv-status="bad" data-tv-label="Not healthy:"/s', $html));
$t('…what\'s held names its trip and its Incident Report (IR-0001), which opens its page', str_contains($q3, 'W1 Listings: its runs failed IR-0001') && str_contains($sec('cb-q3'), 'href="/admin/config/control?ir=1" data-peek-frame'));
$rt = ControlGauges::runtime($pdo);
$cards = preg_match_all('/<article class="cb-gc" id="cb-g-/', $html);
$t('every runtime gauge is a card (ControlGauges::runtime: ' . count($rt) . ')', $cards === count($rt) && $cards >= 20, $cards);
$boxes = array_values(array_unique(array_map(fn ($g) => (string) $g['box'], $rt)));
$t('…grouped by its promise box, each a section with Copy (' . count($boxes) . ' boxes)', count($boxes) >= 3 && count(array_filter($boxes, fn ($b) => str_contains($html, 'class="ak-card tv-bleed cb-box" data-copy="' . htmlspecialchars($b, ENT_QUOTES) . '"'))) === count($boxes));
$one = $rt[0]; $c1 = $sec('cb-g-' . $one['id']); $c1 = substr($c1, 0, strpos($c1, '</article>'));
$t('a card: the whole card is a keyboard button opening its snapshot', str_contains($c1, '<button type="button" class="cb-gopen" data-cb-sp="cb-sp-' . $one['id'] . '-snap"'));
$t('…an Arc gauge, its stage pill under it, the trend ("No history yet"), the target ("target: set by Jason" where null)', str_contains($c1, 'class="sr-arc"') && str_contains($c1, 'class="sr-pill') && str_contains($c1, 'No history yet') && str_contains($c1, $one['target'] === null ? 'target: set by Jason' : 'target: '));
$t('…Workflows ▸, Core Services ▸, Registered Services ▸ (Jason, 2026-11-22)', substr_count($c1, 'cb-drill') === 3 && str_contains($c1, 'Workflows ▸') && str_contains($c1, 'Core Services ▸') && str_contains($c1, 'Registered Services ▸'));
$unc = array_values(array_filter($rt, fn ($g) => $g['reading'] === null && !$g['live']));
$ok = true; foreach ($unc as $g) { $c = $sec('cb-g-' . $g['id']); $c = substr($c, 0, strpos($c, '</article>')); if (substr_count($c, 'not checked yet') !== 1 || substr_count($c, 'Set up its check') !== 1) $ok = false; }
$t('a gauge not checked yet says so once, with one "Set up its check" pill (' . count($unc) . ' of them)', $ok && count($unc) > 0);
$lv = array_values(array_filter($rt, fn ($g) => $g['live'])); $ok = true;
foreach ($lv as $g) { $c = $sec('cb-g-' . $g['id']); $c = substr($c, 0, strpos($c, '</article>')); if (!preg_match('/class="cb-live">live · <time datetime="[^"]+" data-tv-time>[^<]+ UTC<\/time>/', $c)) $ok = false; }
$t('a live reading shows "live · <time>", labelled with its zone (' . count($lv) . ')', $ok && count($lv) > 0);
// the gauge panel as a drill-down (approved by Jason, 2026-11-23): What moves it, What you can do, its setup in words; measure, target and reading under Details
$t('its panel: the big reading, What moves it, its setup in words, Details (measure, target, reading) and the three paths', str_contains($html, 'id="cb-sp-' . $one['id'] . '-snap"') && str_contains($sec('cb-sp-' . $one['id'] . '-snap'), 'Its setup') && str_contains($sec('cb-sp-' . $one['id'] . '-snap'), 'Details: what it measures') && str_contains($html, 'id="cb-sp-' . $one['id'] . '-wf"') && str_contains($html, 'id="cb-sp-' . $one['id'] . '-cs"') && str_contains($html, 'id="cb-sp-' . $one['id'] . '-rs"'));
$t('…each path panel has × and Back to its snapshot', (bool) preg_match('/id="cb-sp-' . preg_quote($one['id'], '/') . '-wf".*?data-cb-spx aria-label="Close">×<.*?data-cb-sp="cb-sp-' . preg_quote($one['id'], '/') . '-snap">← Back</s', $html));
$t('the Arc only: no needle, no Ring, no style switch (03 §27)', !preg_match('/needle|sr-ring|data-gstyle/', $html));

// ---- the drill rows: scenario where not real, "association not mapped" where no link
$rows = tv_cb_path_rows([['W2', 'W2 Listings checked', null, 'scenario: about 60%', '/admin/config/control/wf-W2'], ['x', 'A part', 'Trip: down', ['obs', '12 of 20'], '']], 'none');
$t('a share not read from live is marked scenario; a real one live; a row with no link "association not mapped"', str_contains($rows, '<span class="cb-scn">scenario</span> about 60%') && str_contains($rows, '<span class="cb-live">live</span> 12 of 20') && str_contains($rows, 'A part <span class="cb-unm">association not mapped</span>'));

// ---- Development
$dv = ControlGauges::development($pdo);
$dcards = preg_match_all('/<article class="cb-gc cb-dc"/', $html);
$t('Development: the 16 development gauges', count($dv) === 16 && $dcards === 16, [count($dv), $dcards]);
$ok = true; foreach ($dv as $g) if (empty($g['signed'])) { $c = $sec('cb-d-' . $g['id']); $c = substr($c, 0, strpos($c, '</article>')); if (!str_contains($c, 'data-score="0.0"') || !str_contains($c, '>Planning<') || !str_contains($c, 'awaiting sign-off') && !str_contains($c, 'no configuration yet')) $ok = false; }
$t('…each unsigned one at 0 in Planning, "configuration drafted: N items, M criteria, awaiting sign-off" (gauge-lifecycle.md §3, §6)', $ok);
$pv = array_values(array_filter($dv, fn ($g) => empty($g['signed']) && $g['preview'] !== null));
$c = $sec('cb-d-' . $pv[0]['id']); $c = substr($c, 0, strpos($c, '</article>'));
$t('…"Preview with the draft configuration" shows the draft\'s % (' . $pv[0]['title'] . ': ' . $pv[0]['preview'] . '%)', str_contains($c, 'Preview with the draft configuration') && str_contains($c, number_format((float) $pv[0]['preview'], 0) . '% if the draft were signed off'));
$nc = array_values(array_filter($dv, fn ($g) => $g['no_check']));
$c = $nc ? $sec('cb-d-' . $nc[0]['id']) : ''; $c = substr($c, 0, (int) strpos($c, '</article>'));
$t('…the "no check yet: …" line, and the three drill buttons on each', $nc && str_contains($c, 'no check yet: ' . htmlspecialchars(implode(', ', $nc[0]['no_check']), ENT_QUOTES)) && substr_count($c, 'cb-drill') === 3);

// ---- Site health on the board (moved from Pulse's case)
$sh = $sec('site-health');
$t('Site health\'s list is on the Development tab (SiteHealth::LIST_URL: ?tab=development#site-health)', str_contains(SiteHealth::LIST_URL, 'tab=development#site-health') && strpos($html, 'id="site-health"') > strpos($html, 'id="cb-dev"') && strpos($html, 'id="site-health"') < strpos($html, 'id="cb-incidents"'));
$t('…each finding a row, id site-health-<id>, most paths blocked first', (bool) preg_match('/id="site-health-f-bbbbbb02".*id="site-health-f-aaaaaa01"/s', $sh));
$t('…its title, detail, blocks, severity and first seen', str_contains($txt($sh), 'Alert R07: Get Local doesn\'t open for a guest The page answered 500') && str_contains($txt($sh), 'In the way of 2 paths') && str_contains($txt($sh), 'first seen Nov 21, 10:00 UTC'));
$t('…the old case\'s actions kept: Mark fixed, Set aside (asks why), Check again, Run the checks now', str_contains($sh, 'data-cb-sh="sh_mark:f-bbbbbb02" data-state="fixed"') && str_contains($sh, 'data-cb-sh="sh_mark:f-bbbbbb02" data-state="aside" data-ask=') && str_contains($sh, 'data-cb-sh="sh_refresh"') && str_contains($sh, 'data-cb-sh="sh_run:site-checks"'));
$t('…posting to Pulse\'s own manage actions for sitehealth:open', str_contains($js, "post('/admin/api/pulse', { do: 'manage', key: 'sitehealth:open', what: what, args: args })"));
$pdo->exec("UPDATE page_cache SET body = '{\"v\":{\"ok\":false,\"at\":\"\",\"run_url\":\"\",\"findings\":[],\"error\":\"GitHub didn\\\\u0027t answer (HTTP 503).\"}}' WHERE cache_key = 'sitehealth:now'");
$bad = tv_cb_site_health($pdo);
$t('…findings that couldn\'t be read say so (SiteHealth::load\'s error), with Check again', str_contains(html_entity_decode($bad, ENT_QUOTES), 'data-tv-label="Couldn\'t read the findings:">GitHub didn\'t answer (HTTP 503).') && str_contains($bad, 'data-cb-sh="sh_refresh"'), $txt($bad));
$pdo->exec("UPDATE feature_switches SET state = 'off' WHERE switch_key = 'site_health'");   // Switches keeps what it read: a fresh process
$off = shell_exec('php -r ' . escapeshellarg('$a=$argv[1]; require $a."/lib/Database.php"; require $a."/admin/widgets.php"; require $a."/admin/control-console.php"; echo tv_cb_site_health(Database::connection());') . ' ' . escapeshellarg($app) . ' 2>&1');
$t('…switched off: said, never a silent gap', str_contains((string) $off, 'Site health is off'), mb_substr((string) $off, 0, 200));

// ---- a gauge's detail, loaded as its panel opens (ControlGauges::detail; the session, 2026-11-22: not on the first draw)
$dd = ControlGauges::detail($pdo, 'development', 'discovery'); $first = (string) ($dd['list'][0]['name'] ?? '');
$t('a gauge\'s detail isn\'t in the board\'s first draw: its panel carries a slot that loads it', $first !== '' && !str_contains($html, htmlspecialchars($first, ENT_QUOTES)) && str_contains($html, 'data-cb-gd="development:discovery"') && str_contains($html, 'data-cb-gd="runtime:o-pages"'));
$gd = tv_cb_gauge_detail($pdo, 'development:discovery');
$t('…?gauge=development:<id>: its drill-down, its items by where they stand, each with its checks and where it comes from', str_contains($gd, 'id="cb-gd"') && str_contains($gd, htmlspecialchars($first, ENT_QUOTES)) && str_contains($gd, 'What needs you first') && str_contains($gd, 'Where this comes from'));
$gr = tv_cb_gauge_detail($pdo, 'runtime:o-pages');
$t('…?gauge=runtime:<id>: what it counts, its needs folded to one line with their sources in order (its setup words are on the panel)', str_contains($gr, 'What it counts:') && str_contains($gr, 'What it needs:') && (str_contains($gr, 'first: ') || str_contains($gr, 'no outside source')));
$t('…a gauge with no detail says so, never a blank', str_contains(tv_cb_gauge_detail($pdo, 'runtime:nope'), 'data-tv-label="No detail:"'));
$t('…the board\'s script loads it on open, with Try again on a failure', str_contains($js, "'?gauge=' + encodeURIComponent(slot.getAttribute('data-cb-gd'))") && str_contains($js, 'data-cb-gdre'));

// ---- Incidents and IR-0001's page
$t('Incidents: the IR-#### list, each opening its page', str_contains($sec('cb-irs'), 'href="/admin/config/control?ir=1" data-peek-frame>IR-0001</a>') && str_contains($txt($sec('cb-irs')), 'Unconfirmed'));
$ir = tv_cb_ir_page($pdo, 1, 'x'); $it = $txt($ir);
$t('IR-0001\'s page: its title "IR-0001 · 1 part: W1 Listings · Paused by the system" (03 §7)', str_contains($it, 'IR-0001 · 1 part: W1 Listings · Paused by the system'), mb_substr($it, 0, 160));
$t('…its root cause "Unconfirmed" in its own row until a person writes it', (bool) preg_match('/<h3 class="cb-title">Root cause<\/h3><div class="cb-rc"><p data-tv-status="info" data-tv-label="Unconfirmed:" data-tv-keep>/', $ir));
$t('…its members with warning and trip ids (W-0001, T-0001) and real times', str_contains($it, 'W-0001') && str_contains($it, 'T-0001') && substr_count($ir, 'data-tv-time') >= 3);
$t('…its radius as recorded, its timeline and its evidence', str_contains($it, 'Held all of W1') && str_contains($it, 'Opened at T-0001 (W-0001)') && str_contains($it, 'TimeoutError after 600 s'));
$t('…an IR that isn\'t kept says so, with the way to every report', str_contains($txt(tv_cb_ir_page($pdo, 99, 'x')), 'There\'s no IR-0099'));
ControlIncidents::setCause($pdo, 1, 'Our load endpoint timed out under the batch.', 'Jason');
$t('…once written, Confirmed with who wrote it', str_contains(tv_cb_ir_page($pdo, 1, 'x'), 'data-tv-label="Confirmed:" data-tv-keep>Our load endpoint timed out under the batch. <small>(Jason)'));

// ---- the folded report (D16)
$r = ['sections' => [
    'uptime' => ['Uptime', ['Process', 'System', 'Uptime', 'Up', 'Down', 'Maintenance', 'Off'], [['Common Crawl', 'Outside services', '37.1%', '', '', '', ''], ['W1 Listings', 'The Knowledge Engine', '48.1%', '', '', '', ''], ['W2', 'E', '100%', '', '', '', ''], ['W4', 'E', '100%', '', '', '', ''], ['Search', 'Site', 'not due to be on', '', '', '', '']], ''],
    'ran' => ['What ran, and records in, stored and refused', ['Workflow', 'Calls'], [['W2', 3]], ''],
    'alarms' => ['Alarms raised, acknowledged and cleared', ['Raised', 'Level', 'Process', 'Alarm', 'Times', 'Acknowledged by', 'Cleared', 'Open for', 'How it cleared'],
        array_merge(array_fill(0, 30, [['t' => 1763452800], 'Warning', 'W2', '/api/crawl/verify is slow', 1, '', ['t' => 1763452900], '2 min', 'its reading was good again']), [[['t' => 1763452800], 'Trip', 'W1', 'W1 Listings: its runs failed', 1, 'Jason', 'open', '1 h', '']]), ''],
    'faults' => ['Slow calls, retries and crashes', ['What', 'Raised in this window'], [['Slow', 30]], ''],
    'changes' => ['Shut-downs, maintenance and switches', ['When', 'Kind', 'What', 'Who', 'Why'], [[['t' => 1763452800], 'Switch', 'Task foursquare_bulk switched on', 'the system', 'Put back']], ''],
    'spend' => ['Spend against caps (as of now)', ['Service', 'What', 'Used', 'Cap', 'Of its cap'], [['Claude', 'AI', '29.22 USD', '40 USD', '73%']], ''],
    'trend' => ['The trend', ['Window'], [['The last 7 days']], '']]];
$f = tv_cb_report_folded($r); $ft = $txt($f);
$t('the report folded (D16): spend first, then uptime, alarms, switches, the rest folded', strpos($f, 'id="r-spend"') < strpos($f, 'id="r-uptime"') && strpos($f, 'id="r-uptime"') < strpos($f, 'id="r-alarms"') && strpos($f, 'id="r-alarms"') < strpos($f, 'id="r-changes"') && (bool) preg_match('/<details class="ak-card tv-bleed cb-rsec" id="r-ran"/', $f) && (bool) preg_match('/<details class="ak-card tv-bleed cb-rsec" id="r-trend"/', $f));
$t('…uptime only for what was below 100%, the rest one line ("2 parts at 100%; 1 not due to be on")', str_contains($ft, 'Common Crawl') && str_contains($ft, 'W1 Listings') && !str_contains($f, '<td data-th="Process">W2</td><td data-th="System">E</td><td data-th="Uptime">100%') && str_contains($ft, 'And 2 parts at 100%; 1 not due to be on.'), mb_substr($ft, 0, 300));
$t('…alarms grouped by part and alarm with a count ("/api/crawl/verify is slow ×30"), opened to the rows on tap', str_contains($ft, '/api/crawl/verify is slow W2 · Warning ×30') && substr_count($f, '<details class="cb-rg">') === 2 && str_contains($ft, '31 alarms in 2 groups'));
$t('…every section keeps Copy (data-copy), a section added later is never dropped', substr_count($f, 'data-copy=') === 7 && str_contains(tv_cb_report_folded(['sections' => ['new' => ['Something new', ['A'], [['x']], '']]]), 'Something new'));
require_once $app . '/lib/ControlReport.php';
$full = ControlReport::build($pdo, '', 'week');
$csv = ControlReport::csv($full);
$t('…the CSV unchanged: every section, its columns and rows', str_contains($csv, 'Uptime') && str_contains($csv, 'Alarms raised, acknowledged and cleared') && str_contains($csv, 'Spend against caps'));
$page = tv_cb_report_page($pdo, '', 'week', 'x');
$t('…the report page carries the summary first, then the folded sections, Download CSV and the page', strpos($page, 'id="r-summary"') < strpos($page, 'id="r-spend"') && str_contains($page, 'data-cb-dl="csv"') && str_contains($page, 'data-cb-dl="file"'));

// ---- Copy (D15)
$cc = file_get_contents($app . '/admin/control-console.php');
foreach (['cb-alarms', 'maintenance', 'log', 'reports', 'cb-times', 'cb-watch', 'cb-trends', 'preflight', 'cb-q3', 'site-health', 'cb-irs'] as $id)
    if (!preg_match('/id="' . $id . '"[^>]* data-copy="/', $html)) { $t('Copy: #' . $id . ' is marked data-copy', false); }
$t('Copy: every panel of the board, the views\' sections and cards marked data-copy, and tv-copy.js loaded', substr_count($html, 'data-copy=') > 60 && str_contains($html, '/js/tv-copy.js'), substr_count($html, 'data-copy='));
$tc = file_get_contents(dirname($app) . '/website/js/tv-copy.js');
$t('…tv-copy.js copies the visible text (innerText: never a closed fold or a hidden row), with the site\'s notice', str_contains($tc, 'el.innerText') && str_contains($tc, "TvAsk.say(msg, { anchor: b, bad: !!bad })") && !preg_match('/\balert\(|\bconfirm\(|\bprompt\(/', $tc));

// ---- W13 a list, W14 Not built
[$trows] = tv_cb_table_rows($pdo, ['q' => '', 'show' => '']);
$wf = []; foreach ($trows as $g) foreach ($g['_group']['rows'] as $row) $wf[$row['id']] = $row;
$t('the parts table: W13 "A list", nothing to switch (workflows-page.md §6)', ($wf['wf:W13']['stage'] ?? '') === 'A list' && str_contains((string) $wf['wf:W13']['where'], 'W13 is a list, not a gauge'), $wf['wf:W13'] ?? null);
$t('…W14 "Not built": licence_lookup isn\'t built (its old "Nothing carries it yet" notice\'s fact kept)', ($wf['wf:W14']['stage'] ?? '') === 'Not built' && str_contains((string) $wf['wf:W14']['where'], 'licence_lookup'), $wf['wf:W14'] ?? null);
$_SERVER['REQUEST_URI'] = '/admin/config/control/wf-W14';
$w14 = tv_admin_mod_control($pdo);
$t('…and on W14\'s own page', str_contains($w14, '>Not built<') && str_contains($txt($w14), 'Not built: licence_lookup'));
$_SERVER['REQUEST_URI'] = '/admin/config/control/wf-W1';
$w1 = tv_admin_mod_control($pdo);
$t('…W1\'s own page keeps its needs, each with its sources (its old "W1 Listings: Needs" notice\'s facts)', str_contains($w1, 'class="cb-chips"'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
