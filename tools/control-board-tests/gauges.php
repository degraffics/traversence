<?php
// The gauges' data layer (handoffs/control-board-build-log.md §7, the views' one contract): app/lib/ControlGauges.php over
// site-audit/results/gauges.json (tools/page-audit/gauges.py). From step3/sections/gauge-lifecycle.md: the development rule
// in order (§3) and a regression moving back, logged; the runtime rule (§7); scores (§1, §4); the 16 previews (§6); the
// runtime counts today (§7); W13 a list and W14 not built (workflows-page.md §5); an unreadable source → "not checked yet"
// and the last good copy kept (03 §27; SiteHealth's pattern); live readings (Trips reach a person, Incident Reports close)
// and a trip on a gauge's parts; targets not approved are null; Fix it links from the association map; gauges.py --check.
// No network: the source is a local file (ControlGauges::$source).
// Run: php gauges.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/lib/PageCache.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlIncidents.php';
require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/Automation.php'; require_once $app . '/lib/ControlGauges.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 1500) : '') . "\n"; } };

$repo = dirname(__DIR__, 2);
$file = $repo . '/site-audit/results/gauges.json';
$tmp = sys_get_temp_dir() . '/gauges-test-' . getmypid();
@mkdir($tmp);
$pdo->exec('CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)');
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS control_incidents'); $pdo->exec('DROP TABLE IF EXISTS control_log');
$pdo->exec('DROP TABLE IF EXISTS system_pauses'); Automation::ensurePauses($pdo);
ControlAlarms::ensure($pdo); ControlIncidents::ensure($pdo); ControlLog::ensure($pdo);
$reset = function () use ($pdo) { $pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget(); Automation::forget(); };
$by = function (array $l, string $k) { $o = []; foreach ($l as $r) $o[(string) $r[$k]] = $r; return $o; };
$all = ControlGauges::CRITERIA;
$item = function (string $n, array $met) { return ['name' => $n, 'weight' => 1, 'met' => $met, 'no_check' => []]; };

// ---- 1. the development rule, in order (§3) ----------------------------------------------------------------------
$S = function (array $items, bool $signed = true) { return ControlGauges::devStage($items, $signed)[0]; };
$t('1a. not signed off: Planning, whatever is met', $S([$item('a', $all)], false) === 'Planning');
$t('1b. signed, an item not built: Phased Development', $S([$item('a', $all), $item('b', ['decided'])]) === 'Phased Development');
$t('1c. every item built, one not checked: Audit & Review', $S([$item('a', $all), $item('b', ['decided', 'built', 'reviewed', 'live', 'proved'])]) === 'Audit & Review');
$t('1d. checked but not reviewed: Audit & Review', $S([$item('a', ['decided', 'built', 'checked', 'live', 'proved'])]) === 'Audit & Review');
$t('1e. checked and reviewed, not live: Implementation', $S([$item('a', ['decided', 'built', 'checked', 'reviewed', 'proved'])]) === 'Implementation');
$t('1f. live but not proved: Implementation', $S([$item('a', ['decided', 'built', 'checked', 'reviewed', 'live'])]) === 'Implementation');
$t('1g. every criterion met: Monitoring', $S([$item('a', $all), $item('b', $all)]) === 'Monitoring');
$t('1h. the earliest stage any item is in wins (one not built holds a nearly done gauge)', $S([$item('a', ['decided', 'built', 'checked', 'reviewed', 'live']), $item('b', ['decided'])]) === 'Phased Development');
$t('1i. an item that drops a criterion isn\'t held by it (a page with nothing to prove drops "proved")', $S([['name' => 'p', 'criteria' => ['decided', 'built', 'checked', 'reviewed', 'live'], 'met' => ['decided', 'built', 'checked', 'reviewed', 'live']]]) === 'Monitoring');
$w = ControlGauges::devStage([['name' => 'Get Local', 'met' => ['decided', 'built', 'live'], 'no_check' => ['TOOLS', 'LOAD']]], true);
$t('1j. the reason names the item, the criterion and its missing checks', $w[0] === 'Audit & Review' && str_contains($w[1], 'Get Local: not checked (no check yet: TOOLS, LOAD)'), $w);

// ---- 2. scores (§1, §4) --------------------------------------------------------------------------------------------
[$m, $n] = ControlGauges::devScore([$item('a', ['decided', 'built', 'live']), $item('b', $all)]);
$t('2a. criteria met over configured (3 + 6 of 12)', $m === 9.0 && $n === 12.0, [$m, $n]);
[$m, $n] = ControlGauges::devScore([['name' => 'core', 'weight' => 2, 'met' => ['decided']], $item('b', $all)]);
$t('2b. a weighted item counts its weight (2 × 1 + 6 of 2 × 6 + 6)', $m === 8.0 && $n === 18.0, [$m, $n]);
$t('2c. runtime setup %: 3 of 4 is 75', ControlGauges::setupPct(['probe' => true, 'target' => true, 'check' => false, 'alarm' => true]) === 75.0);

// ---- 3. the runtime rule (§7) ---------------------------------------------------------------------------------------
$R = function (bool $p, bool $tg, bool $c, bool $a) { return ControlGauges::rtStage(['probe' => $p, 'target' => $tg, 'check' => $c, 'alarm' => $a])[0]; };
$t('3a. none of the four: Planning', $R(false, false, false, false) === 'Planning');
$t('3b. a target but no probe: Phased Development', $R(false, true, false, false) === 'Phased Development');
$t('3c. the probe, no target: Audit & Review', $R(true, false, false, true) === 'Audit & Review');
$t('3d. the probe and target, check not passing: Audit & Review', $R(true, true, false, true) === 'Audit & Review');
$t('3e. all but the alarm: Implementation', $R(true, true, true, false) === 'Implementation');
$t('3f. all four: Monitoring', $R(true, true, true, true) === 'Monitoring');

// ---- 4. nothing read: every gauge "not checked yet", the page never fails --------------------------------------------
$reset(); ControlGauges::$source = $tmp . '/nothing-here.json';
$src = ControlGauges::source($pdo); $dev = ControlGauges::development($pdo); $rt = ControlGauges::runtime($pdo); $wf = ControlGauges::workflows($pdo);
$t('4a. source: not ok, with why', !$src['ok'] && $src['error'] !== '' && !$src['kept'], $src);
$t('4b. the 16 development gauges still drawn, each "not checked yet" with no score', count($dev) === 16 && !array_filter($dev, function ($g) { return $g['score'] !== null || $g['text'] !== 'not checked yet' || $g['checked']; }), $dev[0] ?? null);
$t('4c. the 23 runtime gauges, each "not checked yet", reading null', count($rt) === 23 && !array_filter($rt, function ($g) { return $g['reading'] !== null || $g['reading_text'] !== 'not checked yet' || $g['checked']; }), $rt[0] ?? null);
$t('4d. W0–W14 from Automation::WORKFLOWS, not checked yet', count($wf) === 15 && !array_filter($wf, function ($g) { return $g['checked'] || $g['score'] !== null; }), $wf[0] ?? null);

// ---- 5. the real file: the 16 previews match gauge-lifecycle.md §6 ---------------------------------------------------
$reset(); ControlGauges::$source = $file;
$src = ControlGauges::source($pdo);
$t('5a. source read, with the file\'s time', $src['ok'] && $src['at'] !== '' && $src['from'] === $file, $src);
$dev = $by(ControlGauges::development($pdo), 'title');
$six = ['Discovery' => 45, 'Directory' => 52, 'Community' => 39, 'Marketplace' => 44, 'TRVGO – Storefront' => 41, 'Accounts' => 48, 'Dashboard & Pulse' => 49,
        'Get Local 360°' => 48, 'Let\'s Explore 360°' => 46, 'Market 360°' => 49, 'Social 360°' => 44, 'Sales & Growth' => 46, 'White Glove Concierge' => 23,
        'Back-Office' => 43, 'Insights & Research' => 46, 'System Configuration' => 48];   // 2026-11-23 evening: LOAD, PHOTOS-SHOWN and BRAND built, their results read; Dashboard & Pulse 50 -> 49, System Configuration 49 -> 48 (gauge-lifecycle.md §6)
$got = []; foreach ($six as $g => $p) $got[$g] = isset($dev[$g]) ? (int) round($dev[$g]['preview']) : null;
$t('5b. each preview, recomputed on the site from its criteria, is §6\'s number', $got === $six, $got);
$t('5c. every gauge in Planning at 0 until Jason signs off (§6)', count($dev) === 16 && !array_filter($dev, function ($g) { return $g['signed'] || $g['stage'] !== 'Planning' || $g['score'] !== 0.0 || $g['k'] !== 'planning'; }));
$ps = array_count_values(array_column($dev, 'preview_stage'));
$t('5d. their real stages under the draft: 15 Phased Development, Dashboard & Pulse in Audit & Review (§6)', ($ps['Phased Development'] ?? 0) === 15 && $dev['Dashboard & Pulse']['preview_stage'] === 'Audit & Review', $ps);
$t('5e. the Planning line: "configuration drafted: N items, M criteria, awaiting sign-off"', $dev['Discovery']['text'] === 'configuration drafted: 30 items, 180 criteria, awaiting sign-off', $dev['Discovery']['text']);
$t('5f. "no check yet" per gauge, TOOLS first (LOAD built 2026-11-23: no longer missing on Dashboard & Pulse)', ($dev['Dashboard & Pulse']['no_check'] ?? null) === ['TOOLS'] && $dev['White Glove Concierge']['no_check'] === ['TOOLS'], $dev['Dashboard & Pulse']['no_check'] ?? null);
$t('5g. three drill paths, a workflow to its own page, a service to its card', ($dev['Directory']['paths']['workflows'][0][4] ?? '') === '/admin/config/control/wf-W1'
   && in_array('/admin/config/register#svc-google', array_column($dev['Directory']['paths']['registered'], 4), true) && count($dev['Community']['paths']['workflows']) === 0, $dev['Directory']['paths']);
$t('5h. the fallback titles are the file\'s 16', array_keys($dev) === ControlGauges::DEV_FALLBACK);

// ---- 6. the runtime gauges today (§7) ------------------------------------------------------------------------------
$rtl = ControlGauges::runtime($pdo); $rt = $by($rtl, 'id');
$c = array_count_values(array_column($rtl, 'stage'));
$t('6a. 1 Monitoring, 1 Implementation, 13 Audit & Review, 4 Phased Development, 4 Planning', ($c['Monitoring'] ?? 0) === 1 && ($c['Implementation'] ?? 0) === 1 && ($c['Audit & Review'] ?? 0) === 13
   && ($c['Phased Development'] ?? 0) === 4 && ($c['Planning'] ?? 0) === 4, $c);
$t('6b. Monitoring is Trips reach a person; Implementation is Sign-in works end to end', $rt['o-told']['stage'] === 'Monitoring' && $rt['o-signin']['stage'] === 'Implementation');
$t('6c. Planning: Pages load fast, Email is delivered, web search, recovers by itself', ['o-email', 'o-search-cap', 'o-selfrec', 'o-speed'] === (function ($l) { $x = array_keys(array_filter($l, function ($g) { return $g['stage'] === 'Planning'; })); sort($x); return $x; })($rt));
$t('6d. below Monitoring the score is setup %: Sign-in 75, Pages answer 25', $rt['o-signin']['score'] === 75.0 && $rt['o-pages']['score'] === 25.0, [$rt['o-signin']['score'], $rt['o-pages']['score']]);
$t('6e. a target not approved is null: "target: set by Jason"', $rt['o-pages']['target'] === null && $rt['o-pages']['target_text'] === 'target: set by Jason' && $rt['o-speed']['target'] === null && $rt['o-told']['target'] === 'every trip, at once');
$t('6f. no reading but the site\'s own: not checked yet, not live', $rt['o-pages']['reading'] === null && $rt['o-pages']['reading_text'] === 'not checked yet' && !$rt['o-pages']['live'] && $rt['o-pages']['band'] === 'none');
$t('6g. Fix it from the association map: Pages load fast → Bluehost\'s card', in_array(['Fix it: Bluehost', '/admin/config/register#svc-bluehost'], $rt['o-speed']['fix'], true), $rt['o-speed']['fix']);
$nm = array_values(array_filter($rt['o-told']['fix'], function ($f) { return str_starts_with($f[0], 'association not mapped'); }));
$t('6h. a link the map can\'t give says "association not mapped", with no link', count($nm) === 1 && $nm[0][1] === null, $rt['o-told']['fix']);
$t('6i. no check yet: none on Pages load fast once LOAD is built (2026-11-23)', $rt['o-speed']['no_check'] === [], $rt['o-speed']['no_check']);
$t('6j. the fallback ids and titles are the file\'s 23', array_combine(array_column($rtl, 'id'), array_column($rtl, 'title')) === ControlGauges::RT_FALLBACK);
$t('6k. Trips reach a person with no trip: live, "nothing to send", no reading; still Monitoring', $rt['o-told']['live'] && $rt['o-told']['reading'] === null && str_contains($rt['o-told']['reading_text'], 'No trip') && $rt['o-told']['k'] === 'none');

// ---- 7. live readings and a regression moving back, logged ----------------------------------------------------------
$ins = $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at, tripped_at, mailed_at) VALUES (?, ?, 'trip', 'crash', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)");
$ins->execute(['t:a', 'svc:overture', 'Overture failed', gmdate('Y-m-d H:i:s')]);
ControlGauges::forget(); $rt = $by(ControlGauges::runtime($pdo), 'id');
$t('7a. one trip, sent: 100, good, live with its time, still Monitoring', $rt['o-told']['reading'] === 100.0 && $rt['o-told']['band'] === 'good' && $rt['o-told']['live'] && is_int($rt['o-told']['at']) && $rt['o-told']['stage'] === 'Monitoring' && $rt['o-told']['score'] === 100.0, $rt['o-told']);
$ins->execute(['t:b', 'wf:W1', 'overture_bulk failed', null]);
ControlGauges::forget(); $rt = $by(ControlGauges::runtime($pdo), 'id');
$t('7b. a trip not sent: 50, "1 of 2 trips sent", below', $rt['o-told']['reading'] === 50.0 && $rt['o-told']['reading_text'] === '1 of 2 trips sent' && $rt['o-told']['band'] === 'below', $rt['o-told']);
$t('7c. its check stops holding: it moves back to Audit & Review, with its reason', $rt['o-told']['stage'] === 'Audit & Review' && ($rt['o-told']['moved_back']['from'] ?? '') === 'Monitoring' && $rt['o-told']['score'] === 75.0, $rt['o-told']['moved_back']);
$logs = (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE proc = 'gauge:rt:o-told' AND what LIKE 'Gauge moved back from Monitoring to Audit & Review%'")->fetchColumn();
ControlGauges::forget(); $rt = $by(ControlGauges::runtime($pdo), 'id');
$logs2 = (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE proc = 'gauge:rt:o-told'")->fetchColumn();
$t('7d. the drop is logged once in the logbook; its reason stays on the gauge while it sits there', $logs === 1 && $logs2 === 1 && ($rt['o-told']['moved_back']['to'] ?? '') === 'Audit & Review', [$logs, $logs2]);
$t('7e. a trip on an alarm-wired gauge\'s parts: red, its check not passing (Gathering reconciles holds W1)', $rt['o-gather']['band'] === 'poor' && $rt['o-gather']['setup']['check'] === false && $rt['o-gather']['alarms']['trip'] === 2, $rt['o-gather']);   // W1's and Overture's
$t('7f. the workflow path carries its state', ($rt['o-gather']['paths']['workflows'][0][2] ?? '') === 'Trip: overture_bulk failed', $rt['o-gather']['paths']['workflows']);
$t('7g. a gauge not wired to the alarms isn\'t turned red by them (Pages load fast)', $rt['o-speed']['band'] === 'none');
$pdo->exec("UPDATE control_alarms SET mailed_at = UTC_TIMESTAMP()");
ControlGauges::forget(); $rt = $by(ControlGauges::runtime($pdo), 'id');
$t('7h. sent after all: back to Monitoring, no longer moved back', $rt['o-told']['stage'] === 'Monitoring' && $rt['o-told']['moved_back'] === null, $rt['o-told']);
$pdo->exec("INSERT INTO control_incidents (opened_at, closed_at, root_cause_state) VALUES (UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'confirmed'), (UTC_TIMESTAMP(), NULL, 'unconfirmed')");
ControlGauges::forget(); $rt = $by(ControlGauges::runtime($pdo), 'id');
$t('7i. Incident Reports close: 1 of 2 closed with a confirmed cause, live', $rt['o-ir']['reading'] === 50.0 && $rt['o-ir']['live'] && str_starts_with($rt['o-ir']['reading_text'], '1 of 2 closed with a confirmed cause'), $rt['o-ir']);

// ---- 8. W0–W14 (workflows-page.md §5) -------------------------------------------------------------------------------
$wf = $by(ControlGauges::workflows($pdo), 'wf');
$t('8a. W13 a list: no stage, no score', $wf['W13']['kind'] === 'list' && $wf['W13']['stage'] === null && $wf['W13']['score'] === null);
$t('8b. W14 not built: Phased Development, 1 of 4', $wf['W14']['kind'] === 'not_built' && $wf['W14']['stage'] === 'Phased Development' && $wf['W14']['score'] === 25.0, $wf['W14']);
$ar = array_filter($wf, function ($w) { return $w['kind'] === 'gauge' && $w['stage'] === 'Audit & Review' && $w['score'] === 75.0; });
$t('8c. W0–W12 in Audit & Review at 3 of 4, each with its approved target', count($ar) === 13 && $wf['W2']['target'] === '95% within their due day', count($ar));
$t('8d. W1 with its trip open: "Trip: …", red', $wf['W1']['band'] === 'poor' && str_starts_with($wf['W1']['reading_text'], 'Trip: '), $wf['W1']);
Automation::sysPause($pdo, 'wf', 'W3', 'Common Crawl is down', 1); Automation::sysPause($pdo, 'task', 'refresh', 'refresh counted more than it received', 1); Automation::forget();
ControlGauges::forget(); $wf = $by(ControlGauges::workflows($pdo), 'wf');
$t('8e. a workflow the engine holds: "Paused by the system (a trip)"', str_starts_with($wf['W3']['reading_text'], 'Paused by the system (a trip): Common Crawl is down') && $wf['W3']['band'] === 'poor', $wf['W3']);
$t('8f. a workflow with one task held: "Partly held: refresh"', str_starts_with($wf['W2']['reading_text'], 'Partly held: refresh') && $wf['W2']['live'], $wf['W2']);
$t('8g. a workflow with nothing held says so, its measure not read yet', $wf['W4']['reading_text'] === 'No open alarm; its measure isn\'t read yet' && $wf['W4']['band'] === 'none');

// ---- 9. the last good copy (SiteHealth's pattern) -------------------------------------------------------------------
$pdo->exec("DELETE FROM page_cache WHERE cache_key = 'gauges:now'"); ControlGauges::forget();
ControlGauges::$source = $tmp . '/gone.json';
$src = ControlGauges::source($pdo); $dev = ControlGauges::development($pdo);
$t('9a. a failed read keeps the last good copy, with why it isn\'t newer', $src['ok'] && $src['kept'] && $src['error'] !== '' && count($dev) === 16 && $dev[0]['checked'], $src);
file_put_contents($tmp . '/bad.json', '{"development": "not a list"');
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget(); ControlGauges::$source = $tmp . '/bad.json';
$src = ControlGauges::source($pdo); $rt = ControlGauges::runtime($pdo);
$t('9b. a broken file with no good copy: not checked yet, never an error page', !$src['ok'] && $rt[0]['reading_text'] === 'not checked yet', $src);
$t('9c. a failure is kept 10 minutes, not refetched on every page', is_array(PageCache::get($pdo, 'gauges:now')));

// ---- 10. a signed gauge, and a regression from the file ------------------------------------------------------------
$cfg = json_decode((string) file_get_contents($file), true);
$one = ['id' => 'x', 'title' => 'X', 'signed' => true, 'm' => [['111111', 1, 0], ['111111', 1, 0]], 'paths' => []];   // the compact form: six characters an item
$cfg['development'] = [$one]; file_put_contents($tmp . '/one.json', json_encode($cfg));
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget(); ControlGauges::$source = $tmp . '/one.json';
$d = ControlGauges::development($pdo)[0];
$t('10a. signed and every criterion met: Monitoring at 100', $d['stage'] === 'Monitoring' && $d['score'] === 100.0 && $d['k'] === 'monitor', $d);
$cfg['development'][0]['m'][1][0] = '111110';   // its path check stopped passing: "proved" taken away
file_put_contents($tmp . '/one.json', json_encode($cfg));
$pdo->exec("DELETE FROM page_cache WHERE cache_key = 'gauges:now'"); ControlGauges::forget();
$d = ControlGauges::development($pdo)[0];
$t('10b. a criterion stops holding: the score drops and the stage moves back to Implementation, with its reason', $d['stage'] === 'Implementation' && round($d['score'], 1) === 91.7
   && ($d['moved_back']['from'] ?? '') === 'Monitoring' && ($d['moved_back']['why'] ?? '') === '1 of 2 items not proved', $d);
$t('10c. logged in the logbook', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE proc = 'gauge:dev:x' AND why = '1 of 2 items not proved'")->fetchColumn() === 1);

// ---- 10b. what the site caches stays under 60 KB, and reads back whole or not at all -----------------------------------
// (the live page_cache.body column's size isn't known: only SiteHealth's ~15 KB is proven; the coordinator, 2026-11-22)
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget(); ControlGauges::$source = $file;
ControlGauges::development($pdo);
$len = (int) $pdo->query("SELECT MAX(LENGTH(body)) FROM page_cache WHERE cache_key LIKE 'gauges:%'")->fetchColumn();
$t('10d. the configuration the site caches is under 60 KB as stored', $len > 0 && $len <= ControlGauges::CACHE_MAX, $len);
$pdo->exec("UPDATE page_cache SET body = LEFT(body, 20000) WHERE cache_key = 'gauges:now'");   // a column that cut it short
ControlGauges::forget(); ControlGauges::$source = $tmp . '/gone.json';
$src = ControlGauges::source($pdo); $dev = ControlGauges::development($pdo);
$t('10e. a truncated cached value is unreadable: the last good copy is kept', $src['ok'] && $src['kept'] && $dev[0]['checked'] && count($dev) === 16, $src);
$pdo->exec("UPDATE page_cache SET body = LEFT(body, 20000) WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget();
$src = ControlGauges::source($pdo); $dev = ControlGauges::development($pdo);
$t('10f. the last good copy truncated too: not checked yet, never a half-read gauge', !$src['ok'] && !$dev[0]['checked'] && $dev[0]['text'] === 'not checked yet', $src);
// a file over the limit: the app's own file cache where there is one, else read again at most hourly, saying why
$big = json_decode($raw0 = (string) file_get_contents($file), true); $big['runtime'][0]['measure'] = str_repeat('x', 70000);   // a field the site keeps
file_put_contents($tmp . '/big.json', json_encode($big)); @mkdir($tmp . '/store');
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget(); ControlGauges::$source = $tmp . '/big.json'; ControlGauges::$fileCache = $tmp . '/store';
$src = ControlGauges::source($pdo);
$inDb = (int) $pdo->query("SELECT COUNT(*) FROM page_cache WHERE cache_key LIKE 'gauges:%'")->fetchColumn();
$t('10g. over 60 KB: never put in page_cache; kept in the file cache instead', $src['ok'] && $inDb === 0 && is_file($tmp . '/store/gauges-cache-gauges-now.json'), [$src, $inDb]);
ControlGauges::forget(); ControlGauges::$source = $tmp . '/gone.json';
$t('10h. read back from the file cache', ControlGauges::source($pdo)['ok']);
array_map('unlink', glob($tmp . '/store/*') ?: []); ControlGauges::$fileCache = $tmp . '/no-such-dir'; ControlGauges::forget(); ControlGauges::$source = $tmp . '/big.json';
$src = ControlGauges::source($pdo);
$marker = PageCache::get($pdo, 'gauges:now');
$t('10i. over 60 KB with no file cache: not checked yet, why said, read again at most hourly (a small marker kept)', !$src['ok'] && str_contains($src['error'], 'too big') && is_array($marker) && strlen((string) json_encode($marker)) < 1000, $src);
ControlGauges::$fileCache = null;
// a drawer's detail: gauges-items.json, read when it opens, kept per gauge under 60 KB
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"); ControlGauges::forget(); ControlGauges::$source = $file;
$dd = ControlGauges::detail($pdo, 'development', 'discovery');
$t('10j. a development gauge\'s detail: its 30 items by name, each with criteria met, checks and review', is_array($dd) && count($dd['list']) === 30 && isset($dd['list'][0]['name'], $dd['list'][0]['met'], $dd['list'][0]['review']), $dd ? array_keys($dd) : null);
$rd = ControlGauges::detail($pdo, 'runtime', 'o-told');
$t('10k. a runtime gauge\'s detail: setup\'s words, needs and sources', is_array($rd) && isset($rd['setup_why']['check'], $rd['needs']), $rd);
$len = (int) $pdo->query("SELECT MAX(LENGTH(body)) FROM page_cache WHERE cache_key LIKE 'gauges:item:%'")->fetchColumn();
$cnt = (int) $pdo->query("SELECT COUNT(*) FROM page_cache WHERE cache_key LIKE 'gauges:item:%'")->fetchColumn();
$t('10l. one read keeps every gauge\'s detail, each under 60 KB', $cnt === 16 + 23 + 15 && $len <= ControlGauges::CACHE_MAX, [$cnt, $len]);
$t('10m. a detail not there: null', ControlGauges::detail($pdo, 'runtime', 'o-nothing') === null && ControlGauges::detail($pdo, 'bad', 'x') === null);

// ---- 11. gauges.py --check ------------------------------------------------------------------------------------------
$py = $repo . '/tools/page-audit/gauges.py';
exec('python3 ' . escapeshellarg($py) . ' --check 2>&1', $o1, $rc1);
$t('11a. the committed gauges.json and gauges-items.json are current', $rc1 === 0, implode("\n", $o1));
$raw = (string) file_get_contents($file);   // edited as text: a PHP round trip would turn {} into []
copy(dirname($file) . '/gauges-items.json', $tmp . '/gauges-items.json');   // --check reads both, side by side
file_put_contents($tmp . '/same.json', preg_replace('/"generated_at": "[^"]*"/', '"generated_at": "2000-01-01T00:00:00Z"', $raw, 1));
exec('python3 ' . escapeshellarg($py) . ' --check --out ' . escapeshellarg($tmp . '/same.json') . ' 2>&1', $o2, $rc2);
$t('11b. only generated_at differing: still current', $rc2 === 0, implode("\n", $o2));
file_put_contents($tmp . '/stale.json', str_replace('"title": "Discovery"', '"title": "Discovery (edited)"', $raw));
exec('python3 ' . escapeshellarg($py) . ' --check --out ' . escapeshellarg($tmp . '/stale.json') . ' 2>&1', $o3, $rc3);
$t('11c. a stale file fails, saying how to remake it', $rc3 === 1 && str_contains(implode("\n", $o3), 'stale'), implode("\n", $o3));

@rmdir($tmp . '/store'); array_map('unlink', glob($tmp . '/*') ?: []); @rmdir($tmp);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
