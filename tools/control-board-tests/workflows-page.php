<?php
// The board's Workflows page (handoffs/control-board-plan/step3/workflows-page.md, approved by Jason 2026-11-22), drawn on
// the bench from what it reads, item by item:
// - §7: 8 kept, 2 moved (gone from the page, each with its link to the board's Start-up and Pre-flight), 3 dropped (gone).
// - §5: a gauge card per workflow (Arc, score-gauge.php), W13 a list, W14 "Not built" (live-reads C6); Paused now in three
//   groups with counts that filter (R11, R21, R22); words follow the state (0175, R86, R105): a trip's "Paused by the system
//   (a trip): …", never "paused by a person".
// - D8: identity_recheck_tick's hang shows on W2's page, holding only recheck. D9: failed crawl jobs as a count on their
//   task's workflow (coverage, W1), with Try again there: Insights → Crawler's own Retry (cr_do=retry), no new action.
// - The two notices that closed when a workflow's line became a roll-up keep their facts on the workflow's page (W1's
//   "Needs: …", W14's "Nothing carries it yet"); Automation reads W13 a list and W14 Not built; the pre-flight reads a
//   workflow's line as a roll-up and a red line's alarm through the engine.
// Run: mariadb < ../release-b-tests/start-schema.sql && php workflows-page.php <root>
declare(strict_types=1);
$root = $argv[1]; $app = $root . '/app';
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n"; } };
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/preflight-console.php';
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Automation.php'; require_once $app . '/lib/WorkflowSettings.php';
// the gauges' file, read locally (no network): the repo's own, else a small stand-in in the contract's shape (build log §7)
if (is_file($app . '/lib/ControlGauges.php')) { require_once $app . '/lib/ControlGauges.php'; $gf = dirname(__DIR__, 2) . '/site-audit/results/gauges.json'; if (is_file($gf)) ControlGauges::$source = $gf; }
$text = fn (string $h) => html_entity_decode(strip_tags(preg_replace('~<(style|script)\b.*?</\1>~s', '', $h)), ENT_QUOTES, 'UTF-8');

// ---- the seed: master on; W1 a task trip and failed crawl jobs; W2 its recheck job hung; W3 two switches a closed trip
//      left off; W12 paused by a person; spend and a last run
foreach (["crawl_costs (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, wf VARCHAR(8) NOT NULL DEFAULT '', service VARCHAR(40) NOT NULL, units INT NOT NULL DEFAULT 0, usd_micros BIGINT NOT NULL DEFAULT 0)",
          "crawl_run_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id INT NOT NULL DEFAULT 0, wf VARCHAR(8) NOT NULL DEFAULT '', task VARCHAR(40) NOT NULL, outcome VARCHAR(20) NOT NULL, why VARCHAR(255) NULL, at DATETIME NOT NULL, allotted_s INT NOT NULL DEFAULT 0, seconds INT NOT NULL DEFAULT 0, fetches INT NOT NULL DEFAULT 0, searches INT NOT NULL DEFAULT 0, counts TEXT NULL)",
          "crawl_runs (id INT AUTO_INCREMENT PRIMARY KEY, started_at DATETIME NULL)",
          "crawl_jobs (id INT AUTO_INCREMENT PRIMARY KEY, cluster_id INT NULL, group_label VARCHAR(120) NOT NULL DEFAULT '', priority INT NOT NULL DEFAULT 0, status VARCHAR(12) NOT NULL DEFAULT 'queued', attempts INT NOT NULL DEFAULT 0, lease_until DATETIME NULL, result TEXT NULL, queued_by_user_id INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)"] as $sql) $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $sql);
foreach (['last_outcome VARCHAR(20) NULL', 'last_why VARCHAR(255) NULL', 'max_paid INT NOT NULL DEFAULT 0'] as $c) { try { $pdo->exec('ALTER TABLE crawl_capture_tasks ADD COLUMN ' . $c); } catch (Throwable $x) {} }
$pdo->exec("INSERT INTO crawl_costs (at, wf, service, units, usd_micros) VALUES (NOW(), 'W1', 'tavily', 3, 1500000)");
$pdo->exec("UPDATE automation_switches SET paused = 0 WHERE kind = 'master'");
$pdo->exec("INSERT IGNORE INTO crawl_workflows (wf, name, paused) VALUES ('W0', 'Indexer', 0), ('W14', 'Authority sources', 0)");
$pdo->exec("UPDATE crawl_workflows SET paused = 0, paused_why = NULL");
$pdo->exec("UPDATE crawl_workflows SET paused = 1, paused_why = 'Until the search review', paused_at = NOW() WHERE wf = 'W12'");
$pdo->exec("DELETE FROM crawl_capture_tasks");
$ins = $pdo->prepare('INSERT INTO crawl_capture_tasks (task, wf, mode, enabled, label, name, what, off_why, last_served_at, last_outcome, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
foreach ([['coverage', 'W1', 'run', 1, 'Find places by kind and town', 'Businesses of one kind in one town cluster', null, '2026-11-22 13:55:00', 'done'],
          ['overture_bulk', 'W1', 'bulk', 1, 'Overture: load by town', 'Overture\'s town loads', null, null, null],
          ['refresh', 'W2', 'run', 1, 'Re-read listings\' own websites', 'Rechecks live listings', null, null, null],
          ['recheck', 'W2', 'run', 0, 'Identity recheck', 'The identity recheck', 'Shipped off (R155)', null, null],
          ['source', 'W3', 'run', 0, 'Read a source', 'Reads one source', 'A trip on the Control Board: Common Crawl failed', null, null],
          ['commoncrawl', 'W3', 'run', 0, 'Common Crawl', 'Our states\' local sites', 'A trip on the Control Board: Common Crawl failed', null, null],
          ['howto', 'W11', 'run', 1, 'How-to guides', 'Official pages', null, null, null],
          ['learn', 'W12', 'run', 1, 'Search learning', 'Gaps', null, null, null]] as [$tk, $wf, $mode, $on, $name, $what, $why, $last, $out])
    $ins->execute([$tk, $wf, $mode, $on, $name, $name, $what, $why, $last, $out]);
$pdo->exec("INSERT INTO crawl_run_items (wf, task, outcome, at, seconds) VALUES ('W1', 'coverage', 'done', NOW(), 20), ('W1', 'coverage', 'idle', NOW(), 1)");
$pdo->exec("DELETE FROM crawl_jobs"); for ($i = 0; $i < 8; $i++) $pdo->exec("INSERT INTO crawl_jobs (group_label, status, attempts, updated_at) VALUES ('Gallup × Health care', 'failed', 3, NOW())");
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
Automation::ensurePauses($pdo); $pdo->exec('DELETE FROM system_pauses');
$al = $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, todo, first_at, last_at, tripped_at, component, stopped) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?, ?)");
$al->execute(['task:failed:overture_bulk', 'wf:W1', 'trip', 'crash', 'overture_bulk failed (run #1290)', 'IR-0001: the endpoint\'s timings, then Check and start again', gmdate('Y-m-d H:i:s'), 'task:overture_bulk', json_encode([['pause', 'task:overture_bulk', 'overture_bulk (W1)']])]);
$tripId = (int) $pdo->lastInsertId();
Automation::sysPause($pdo, 'task', 'overture_bulk', 'overture_bulk failed (run #1290)', $tripId);
// D8: the job "identity_recheck_tick" never finished (live-reads C2 #5), raised as the board raises it (ControlAlarms.php: job:<job>:stuck)
$al->execute(['job:identity_recheck_tick:stuck', 'job:identity_recheck_tick', 'warning', 'slow', 'The job "identity_recheck_tick" never finished', 'Open its job (beside this) for its runs.', null, '', null]);
// a W14 route line and W1's line as their last runs left them (their notices closed: a workflow's line raises nothing)
$save = new ReflectionMethod('Preflight', 'save'); $save->setAccessible(true);
$save->invoke(null, $pdo, 'wf:W1', ['s' => 'bad', 'said' => 'Needs: Places by kind and town ✗, Businesses in bulk (on a backup).', 'tested' => '', 'fix' => [], 'at' => time(), 'ms' => 5, 'by' => 'test', 'hist' => [], 'extra' => ['parts' => [['Needs: Places by kind and town ✗, Businesses in bulk (on a backup).', 'bad']]]]);
$save->invoke(null, $pdo, 'rt:wf:W14', ['s' => 'bad', 'said' => 'Nothing carries it yet: no site job, crawler task or worker names W14.', 'tested' => '', 'fix' => [], 'at' => time(), 'ms' => 5, 'by' => 'test', 'hist' => []]);
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at) VALUES ('pf:svc:commoncrawl', 'svc:commoncrawl', 'warning', 'retry', 'Common Crawl isn\\'t answering', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
Automation::forget();
// two settings saves: versions with Go back to it (kept, R154)
WorkflowSettings::save($pdo, 'W1', WorkflowSettings::current($pdo, 'W1'), 0, 'first', 'UTC');
$sp = WorkflowSettings::current($pdo, 'W1'); $sp['starts']['triggers'] = 'Send the crawler'; WorkflowSettings::save($pdo, 'W1', $sp, 0, 'second', 'UTC');

$man = (function () use ($app) { return require $app . '/admin/manifests.php'; })();
$h = tv_admin_mod_workflows_crawler($pdo); $tx = $text($h);
file_put_contents(sys_get_temp_dir() . '/workflows-page.html', $h);
$wfs = array_keys(Automation::WORKFLOWS);
$card = function (string $wf) use ($h): string { $i = strpos($h, 'id="wfp-card-' . $wf . '"'); if ($i === false) return ''; $j = strpos($h, '</article>', $i); return substr($h, $i, $j - $i); };
$panel = function (string $id) use ($h): string { $i = strpos($h, '<aside class="wfp-panel" id="' . $id . '"'); if ($i === false) return ''; $j = strpos($h, '</aside>', $i); return substr($h, $i, $j - $i); };

echo "§7 kept (8)\n";
$t('kept 1: the stage workflows\' builder stays above the crawler\'s part, unchanged', ($man['config']['sections']['workflows']['widgets'] ?? []) === [['html', 'tv_admin_mod_workflows'], ['html', 'tv_admin_mod_workflows_crawler']] && function_exists('tv_admin_mod_workflows'));
$own = array_filter($wfs, fn ($w) => str_contains($h, 'href="/admin/config/control/wf-' . $w . '" data-peek-frame'));
$t('kept 2: each workflow\'s own page, W0–W14, opened in a drawer (its crumb and "Its own page on the board")', count($own) === 15, array_values(array_diff($wfs, $own)));
$t('…the card opens it in the side panel', count(array_filter($wfs, fn ($w) => str_contains($card($w), 'data-wfp-open="wfp-' . $w . '"') && $panel('wfp-' . $w) !== '')) === 15);
$t('kept 3: By workflow, this month: the fold, and the month\'s spend on W1\'s card', str_contains($tx, 'By workflow, this month') && str_contains($h, '$1.50') && str_contains($text($card('W1')), 'This month: $1.50'));
$t('kept 4: each workflow\'s switch line: a trip in 0175\'s words, never "paused by a person"', str_contains($text($card('W1')), 'Paused by the system (a trip)') && !preg_match('/paused by a person/i', $tx), $text($card('W1')));
$free = array_values(array_filter(['W4', 'W5', 'W6', 'W7', 'W8', 'W9', 'W10', 'W11'], function ($w) use ($pdo) { try { return !Operations::blocking($pdo, $w); } catch (Throwable $x) { return false; } }));
$t('…Running where nothing holds it (a workflow with no red line on this bench)', $free !== [] && str_contains($text($card($free[0])), 'Running') && str_contains($card($free[0]), 'wfp-lt wfp-g'), [$free, $text($card($free[0] ?? 'W11'))]);
$t('…a person\'s pause: "Paused: <why>", off on purpose, never a failure', str_contains($text($card('W12')), 'Paused: Until the search review') && str_contains($card('W12'), 'wfp-lt wfp-n') && str_contains($card('W12'), 'data-wfp-g="off'));
$t('kept 5: last run, labelled with its zone (<time datetime data-tv-time>)', (bool) preg_match('~Last run: <time datetime="2026-11-22T13:55:00[^"]*" data-tv-time>Nov 22, 1:55 PM [A-Z]{2,5}</time>, did its work~', $panel('wfp-W1')), mb_substr($text($panel('wfp-W1')), 0, 200));
// Returns empty (R62): a frequent task with work and answers but nothing back
$pdo->exec("INSERT INTO crawl_run_items (wf, task, outcome, at, fetches, counts) VALUES " . implode(',', array_fill(0, 12, "('W11', 'howto', 'done', NOW(), 3, '{}')")));
$h2 = tv_admin_mod_workflows_crawler($pdo);
$c11 = substr($h2, (int) strpos($h2, 'id="wfp-card-W11"'), 2500);
$t('kept 6: Returns empty, on the card and in Paused now\'s warnings', str_contains($text($c11), 'Returns empty: How-to guides') && str_contains($text($h2), 'returns empty: How-to guides had work and got answers'), $text($c11));
$pdo->exec("DELETE FROM crawl_run_items WHERE task = 'howto'");
$p1 = $panel('wfp-W1');
$t('kept 7: the settings form on its own page: what starts it, triggers, share, budgets, one save as a version, back to its panel', str_contains($p1, 'name="op_do" value="wf_save"') && str_contains($p1, 'action="#wfp-W1"') && str_contains($p1, 'name="triggers"') && str_contains($p1, 'name="ai_share_pct"') && str_contains($p1, 'name="month_usd"') && str_contains($p1, 'Save W1 settings'));
$t('…and its versions, each with Go back to it', str_contains($p1, 'Version 2') && str_contains($p1, 'name="op_do" value="wf_back"') && str_contains($p1, 'Go back to it'));
$t('…its intro\'s promise moved into the settings fold ("every setting is edited here … each save is a version")', str_contains($p1, 'Every setting is edited here, never only in code; each save is a version'));
$t('kept 8: the task table: each task\'s limits, the services it may use, its last 24 hours', str_contains($p1, 'name="t[coverage][') && str_contains($p1, 'Services it may use') && str_contains($text($p1), 'Last 24 h'));
$t('…as "Its steps and their switches": each step\'s switch on its own page (one home, R119, R144)', str_contains($p1, 'Its steps and their switches') && str_contains($p1, 'href="/admin/config/control/wf-W1#task-coverage" data-peek-frame>Its switch</a>') && !str_contains($p1, 'data-cb="taskon"') && !str_contains($p1, 'data-cb="taskoff"'));
// the form's save round trip, back to its panel with the answer
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['csrf' => 'x', 'op_do' => 'wf_save', 'ref' => 'W1', 'tz' => 'UTC', 'triggers' => 'Send the crawler; towns searched', 'note' => 'test'] + WorkflowSettings::current($pdo, 'W1')['budgets'];
$hp = tv_admin_mod_workflows_crawler($pdo); $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = [];
$t('…a save answers on the page (a version) — or greys with the reason for who can\'t', (bool) preg_match('/data-tv-flash="(ok|error)">(W1 saved as version 4|[^<]+)</', $hp, $fm) && ($fm[1] === 'ok' ? str_contains($fm[2], 'version 4') : $fm[2] !== ''), $fm ?? null);

echo "§7 moved (2) and dropped (3)\n";
$t('moved 1: Start the system isn\'t drawn here (its card\'s buttons are gone)', !str_contains($h, 'name="au_do" value="start"') && !str_contains($h, 'id="start"'));
$t('…its link to the board\'s Start-up', str_contains($h, 'href="/admin/config/control?tab=startup" data-peek-frame>Start-up</a>'));
$t('moved 2: "Check before switching on" isn\'t drawn here (no #check fold, no switch-on group)', !str_contains($h, 'id="check"') && !str_contains($h, 'data-pf-group="switchon"'));
$t('…its link to Start-up\'s pre-flight', str_contains($h, 'href="/admin/config/control?tab=preflight#pf-g-switchon" data-peek-frame'));
$red = []; foreach ($wfs as $w) { try { foreach (Operations::blocking($pdo, $w) as $l) $red[$w] = $l; } catch (Throwable $x) {} }
$rw = array_key_first($red);
$t('…each red line on the card of what it holds, with its fix on its page', $rw !== null && (str_contains($text($card($rw)), 'Held by its check') || str_contains($text($card($rw)), 'Not built') || str_contains($text($card($rw)), 'Paused')) && str_contains($text($panel('wfp-' . $rw)), (string) $red[$rw]['line']), [$rw, $text($card((string) $rw))]);
$t('dropped 1: the interlocks note', !str_contains($tx, 'Each line also holds its process on the'));
$t('dropped 2: the list of W1–W14 page buttons above the board', !preg_match('~<a class="ak-b" href="/admin/config/control/wf-W\d+" data-peek-frame>W\d+ ~', $h));
$t('dropped 3: the intro line "Each workflow, W1 to W13 (W14 from when it\'s built)…"', !str_contains($tx, 'Each workflow, W1 to W13'));
$t('/admin/config/board still opens it (the old address)', tv_admin_mod_board($pdo) !== '' && str_contains(tv_admin_mod_board($pdo), 'id="wfp-grid"'));

echo "§5 the gauges, W13 a list, W14 Not built\n";
$gauges = array_filter($wfs, fn ($w) => str_contains($card($w), 'class="sr '));
$t('a gauge card per workflow but W13: 14 Arc gauges, no needle', count($gauges) === 14 && !in_array('W13', $gauges, true) && !str_contains($h, 'needle'), array_values(array_diff($wfs, $gauges)));
$t('W13: a list, not a gauge, "Nothing to switch: people write"', str_contains($text($card('W13')), 'W13 is a list, not a gauge') && str_contains($text($card('W13')), 'Nothing to switch: people write'), $text($card('W13')));
$t('W14: Not built (licence_lookup), never Running', str_contains($text($card('W14')), 'Not built: licence_lookup') && !str_contains($text($card('W14')), 'Running'), $text($card('W14')));
if (class_exists('ControlGauges') && ControlGauges::source($pdo)['ok']) {
    $t('…each gauge with its stage pill and "setup: n of 4" from the gauges\' file', str_contains($card('W14'), 'sr-pill') && str_contains($text($card('W14')), 'setup: 1 of 4') && str_contains($text($card('W1')), 'of 4'), $text($card('W14')));
    $t('…the approved measure on the card', str_contains($text($card('W1')), 'approved'));
}
echo "Paused now, on the workflows (R11, R21, R22)\n";
$now = substr($h, (int) strpos($h, 'id="wfp-now"'), (int) strpos($h, 'wfp-clk') - (int) strpos($h, 'id="wfp-now"'));
$t('three groups: Needs attention, Warnings, Off on purpose; and Waiting on those under them', str_contains($now, '>Needs attention</h4>') && str_contains($now, '>Warnings</h4>') && str_contains($now, '>Off on purpose</h4>') && str_contains($now, 'Waiting on those: '), $text($now));
$t('…counts that filter the cards (four buttons, pressed state)', substr_count($now, 'data-wfp-filter=') === 4 && str_contains($now, 'aria-pressed="false"'));
$cnt = fn (string $g) => preg_match('~data-wfp-filter="' . $g . '"[^>]*>[^<]*<b>(\d+)</b>~', $now, $m) ? (int) $m[1] : -1;
$t('…each count is the cards carrying that group', $cnt('att') === count(array_filter($wfs, fn ($w) => preg_match('/data-wfp-g="[^"]*\batt\b/', $card($w)))) && $cnt('off') === count(array_filter($wfs, fn ($w) => preg_match('/data-wfp-g="[^"]*\boff\b/', $card($w)))), [$cnt('att'), $cnt('off')]);
$t('W3: its two switches left by a closed trip under Needs attention, with Jason\'s "waiting on the re-read"', str_contains($text($now), 'Off by the system, left by a closed trip') && str_contains($text($now), 'Waiting on the re-read (Jason, 2026-11-22)') && str_contains($text($card('W3')), 'Off by the system: 2 switches'), $text($card('W3')));
$t('each item opens its workflow\'s page in the side panel', str_contains($now, 'data-wfp-open="wfp-W1"') && str_contains($now, 'data-wfp-open="wfp-W3"'));

echo "D8, D9\n";
$p2 = $panel('wfp-W2');
$t('D8: identity_recheck_tick\'s hang shows on W2\'s page, holding only recheck', str_contains($text($p2), 'The job "identity_recheck_tick" never finished') && str_contains($text($p2), 'It is W2\'s recheck task: its hang holds only recheck; the rest of W2 runs'), mb_substr($text($p2), 0, 900));
$t('…on recheck\'s own step too, and as a warning on W2\'s card, not a loose job', str_contains($text($p2), 'The job "identity_recheck_tick" never finished. It holds only recheck.') && str_contains($text($card('W2')), 'Warning') === true || str_contains($card('W2'), 'warn'), $text($card('W2')));
$t('…its job\'s runs a tap away (the alarm\'s own link, in a drawer)', str_contains($p2, '/admin/config/control/job-identity_recheck_tick" data-peek-frame>Open it</a>'));
$t('D9: 8 failed crawl jobs, a count on W1 (the coverage task\'s workflow)', str_contains($text($card('W1')), '8 crawl jobs failed') && str_contains($p1, 'data-tv-label="8 crawl jobs failed:"'), $text($card('W1')));
$t('…with Try again there (coverage)', str_contains($p1, 'data-wfp-retry="coverage"') && str_contains($p1, '>Try again</button>'));
$t('…Try again is Insights → Crawler\'s own Retry (cr_do=retry, task), its answer read back, never claimed', str_contains($h, 'fetch("/admin/insights/crawler"') && str_contains($h, 'fd.set("cr_do","retry")') && str_contains($h, 'didn’t say it was done, so nothing is claimed'));
$cc = (string) file_get_contents($app . '/admin/crawler-console.php');
$t('…and that endpoint is live code: CrawlService::retry(coverage) puts failed crawl jobs back', str_contains($cc, "case 'retry': \$n = CrawlService::retry(\$pdo, \$task);") && str_contains((string) file_get_contents($app . '/lib/crawler/CrawlService.php'), "if (\$task === 'coverage') {"));
[$canR] = Access::can('insights.crawler.edit', ['min' => 'staff', 'depts' => ['ops']]);
$t('…greyed with the reason for who can\'t', $canR || (bool) preg_match('~data-wfp-retry="coverage" disabled title="[^"]+"~', $p1));

echo "The two closed notices' facts, kept on the workflow's page\n";
$t('W1: "Needs: …" from its line (a roll-up that raises nothing)', str_contains($text($p1), 'Needs: Places by kind and town ✗') && str_contains($text($p1), 'raises nothing itself'), mb_substr($text($p1), -1500));
$p14 = $panel('wfp-W14');
$t('W14: "Nothing carries it yet" from its route line, and Not built', str_contains($text($p14), 'Nothing carries it yet: no site job, crawler task or worker names W14') && str_contains($text($p14), 'Not built'), mb_substr($text($p14), 0, 800));

echo "The panels: three paths each, Back and ×\n";
$t('each workflow has its three paths (Workflows ▸, Core Services ▸, Registered Services ▸), each a side panel', count(array_filter($wfs, fn ($w) => $panel('wfp-' . $w . '-a') !== '' && $panel('wfp-' . $w . '-c') !== '' && $panel('wfp-' . $w . '-r') !== '')) === 15);
$t('every panel has × and Back', substr_count($h, '<aside class="wfp-panel"') === 60 && substr_count($h, 'data-wfp-close aria-label="Close">×</button>') === 60 && substr_count($h, 'data-wfp-back>← Back</button>') === 60);
$t('Core Services ▸ on W1 names /api/crawl/loads.php holding only the three bulk loads (§3)', str_contains($text($panel('wfp-W1-c')), '/api/crawl/loads.php: its own check, holding only overture_bulk, foursquare_bulk and geofabrik_bulk'));
$t('Registered Services ▸: needs with their sources in order, each source its card', str_contains($panel('wfp-W2-r'), 'href="/admin/config/register#svc-') && str_contains($text($panel('wfp-W2-r')), ' → '));
$t('Workflows ▸: W4 sends work to W6, W7, W10 and W8; W1 gets work from W5, W12 and W8', str_contains($panel('wfp-W4-a'), 'data-wfp-open="wfp-W6"') && str_contains($panel('wfp-W4-a'), 'data-wfp-open="wfp-W8"') && str_contains($panel('wfp-W1-a'), 'data-wfp-open="wfp-W12"'));
$t('panels close by × , Esc and a click outside (the script\'s three ways)', str_contains($h, 'data-wfp-scrim') && str_contains($h, 'e.key!=="Escape"') && str_contains($h, '[data-wfp-close]'));
$t('no browser dialogs', !preg_match('/\b(alert|confirm|prompt)\(/', preg_replace('/TvAsk\.\w+\(/', '', $h)));

echo "Automation and the pre-flight\n";
$au = tv_admin_mod_automation($pdo); $aut = $text($au);
$row = function (string $wf) use ($au, $text): string { $i = strpos($au, '<b>' . $wf . ' '); return $i === false ? '' : $text(substr($au, $i, (int) strpos($au, '</tr>', $i) - $i)); };
$t('Automation: W13 a list, not "Running"', str_contains($row('W13'), 'A list: people write stories') && !str_contains($row('W13'), 'Running'), $row('W13'));
$t('Automation: W14 Not built, not "Running"', str_contains($row('W14'), 'Not built: nothing carries it yet') && !str_contains($row('W14'), 'Running'), $row('W14'));
$t('Automation: "See each line and its fix" goes to Start-up\'s pre-flight, not the old board', !str_contains($au, '/admin/config/board#check'));
$l1 = tv_preflight_line($pdo, 'wf:W1');
$t('pre-flight: a workflow\'s line reads as a roll-up that raises nothing', str_contains($text($l1), 'adds up its parts and raises nothing itself'), $text($l1));
$save->invoke(null, $pdo, 'svc:commoncrawl', ['s' => 'bad', 'said' => 'index.commoncrawl.org answered 504.', 'tested' => 'GET', 'fix' => [], 'at' => time(), 'ms' => 5, 'by' => 'test', 'hist' => []]);
$l2 = tv_preflight_line($pdo, 'svc:commoncrawl');
$t('pre-flight: a red line shows its alarm\'s words through the engine, and opens it on the board', str_contains($l2, 'data-tv-label="Now:"') && str_contains($l2, 'Its alarm on the board'), $text($l2));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
