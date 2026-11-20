<?php
// decisions/0175's note (Jason, 2026-11-20): "Paused now" gives a clear picture.
// 1. Words true: a switch the board's shut-down wrote for a trip is never "by a person"; it names its trip.
// 2. Three groups: Needs attention (each trip, with its fix); Waiting on those (folded under its trip); Off on purpose
//    (a release's or a person's switch-off, with what it waits for, folded).
// 3. One health line above it: the critical duties first (0171's), then each system's counts.
// Run: mariadb < ../release-b-tests/start-schema.sql && php paused-now.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 1500) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php';
require_once $app . '/lib/crawler/CrawlService.php'; require_once $app . '/lib/Automation.php'; require_once $app . '/lib/ControlBoard.php'; require_once $app . '/lib/ControlAlarms.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec('DROP TABLE IF EXISTS system_pauses'); Automation::ensurePauses($pdo);
$pdo->exec("UPDATE automation_switches SET paused = 0");
$pdo->exec("UPDATE crawl_workflows SET paused = 0, paused_why = NULL, paused_by = NULL");
$pdo->exec("DELETE FROM crawl_capture_tasks WHERE task IN ('overture_bulk', 'geofabrik_bulk')");
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, mode, enabled, label, name, what, updated_at) VALUES ('overture_bulk', 'W1', 'bulk', 1, 'Overture', 'Overture: businesses by town', 'Monthly loads.', NOW()), ('geofabrik_bulk', 'W1', 'bulk', 1, 'Geofabrik', 'Geofabrik: OpenStreetMap by state', 'The state files.', NOW())");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1, off_why = NULL");
Automation::forget();
$trip = 'Shut down from the Control Board by the Control Board (a trip)';
$al = $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, todo, first_at, last_at, tripped_at, stopped, cleared_at) VALUES (?, ?, 'trip', 'check', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?)");
$al->execute(['pf:overture', 'wf:W1', 'Overture bulk failed', 'Check Overture\'s download and run its check', '[["proc","wf:W1","W1 Indexing"]]', null]); $a1 = (int) $pdo->lastInsertId();
$al->execute(['svc:issue:4', 'svc:commoncrawl', 'Common Crawl is down', 'Check and start again once index.commoncrawl.org answers', null, null]); $a2 = (int) $pdo->lastInsertId();
$al->execute(['pf:w5', 'wf:W5', 'W5 failed its check', 'Run its check', '[["proc","wf:W5","W5"]]', gmdate('Y-m-d H:i:s', time() - 86400)]); $a3 = (int) $pdo->lastInsertId();
// the old shut-down's switches (before 0175 made trips pause), a person's, a release's, and the system's own pause
$pdo->prepare("UPDATE crawl_capture_tasks SET enabled = 0, off_why = ? WHERE task = 'overture_bulk'")->execute([$trip]);
$pdo->prepare("UPDATE crawl_workflows SET paused = 1, paused_why = ? WHERE wf IN ('W1', 'W5')")->execute([$trip]);
$pdo->exec("UPDATE crawl_workflows SET paused = 1, paused_why = 'Until the review' WHERE wf = 'W3'");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 0, off_why = 'Shipped off by Release A: on after the switch-on check' WHERE task = 'geofabrik_bulk'");
Automation::sysPause($pdo, 'service', 'commoncrawl', 'Common Crawl is down', $a2);
Automation::forget();

$all = ControlBoard::pausedNow($pdo);
$by = []; foreach ($all as $x) $by[$x[3]] = $x;
$t('1. nothing the trip\'s shut-down wrote reads "by a person"', !array_filter($all, fn ($x) => $x[1] === 'a person' && str_contains($x[2], 'the Control Board (a trip)')) && !array_filter($all, fn ($x) => $x[1] === 'a person' && in_array($x[3], ['/admin/config/control/wf-W1', '/admin/config/control/wf-W1#task-overture_bulk', '/admin/config/control/wf-W5'], true)), $all);
$o = $by['/admin/config/control/wf-W1#task-overture_bulk'] ?? null;
$t('…the task it switched off is the system\'s, under its trip, which it names', $o && $o['g'] === 'sys' && $o['alarm'] === $a1 && $o[1] === 'the system (a trip)' && str_contains($o[2], 'Overture bulk failed'), $o);
$w1 = $by['/admin/config/control/wf-W1'] ?? null;
$t('…and so is the workflow it paused', $w1 && $w1['g'] === 'sys' && $w1['alarm'] === $a1, $w1);
$w5 = $by['/admin/config/control/wf-W5'] ?? null;
$t('…one whose trip has closed names that trip, with no open trip to fold under', $w5 && $w5['g'] === 'sys' && $w5['alarm'] === 0 && str_contains($w5[2], 'W5 failed its check') && str_contains($w5[2], 'nobody had set this switch before'), $w5);
$t('a person\'s and a release\'s switch-offs are off on purpose, with what each waits for', ($by['/admin/config/control/wf-W3']['g'] ?? '') === 'purpose' && ($by['/admin/config/control/wf-W1#task-geofabrik_bulk']['g'] ?? '') === 'purpose' && str_contains($by['/admin/config/control/wf-W1#task-geofabrik_bulk'][2], 'switch-on check'));
$cc = $by['/admin/config/register#svc-commoncrawl'] ?? null;
$t('the system\'s own pause sits under its trip', $cc && $cc['g'] === 'sys' && $cc['alarm'] === $a2, $cc);

$lights = ['site:search' => 'bad', 'wf:W1' => 'bad', 'svc:commoncrawl' => 'hold', 'site:db' => 'ok', 'svc:aws' => 'ok'];
$h = ControlBoard::pausedHtml($pdo, 'cb-paused', $lights);
$hl = preg_match('~<p class="cb-health" data-tv-status="(\w+)" data-tv-label="Health now:">(.*?)</p>~', $h, $m) ? html_entity_decode($m[2], ENT_QUOTES) : '';
$t('3. the health line comes first, above Paused now', $hl !== '' && strpos($h, 'cb-health') < strpos($h, 'Paused now:'), $h);
$t('…the critical duties first: those working together, a down one named with its part', str_starts_with($hl, 'Page load, sign-in, payments, database, email and site jobs: working. Search: down.') && ($m[1] ?? '') === 'bad', $hl);
$hn = ControlBoard::pausedHtml($pdo, 'cb-paused', ['wf:W1' => 'bad', 'site:search' => 'ok']);
$t('…red only for a critical duty down; a system\'s part down is the info colour', str_contains($hn, 'class="cb-health" data-tv-status="info"'), mb_substr($hn, 0, 300));
$t('…then each system\'s count: down, held, waiting, off on purpose', (bool) preg_match('/Data gathering: 1 down \(W1 [^)]*\), 2 parts waiting on them, 2 off on purpose\. Outside services: 1 held, waiting for what it needs\. Workers and the site\'s own parts: working\.$/', $hl), $hl);
$t('2. the head says it in groups', str_contains(html_entity_decode($h, ENT_QUOTES), '2 trips need attention, 1 held by the system with no open trip, 3 waiting on them, 2 off on purpose.'), $h);
$na = substr($h, (int) strpos($h, 'Needs attention'), (int) strpos($h, 'Off on purpose') - (int) strpos($h, 'Needs attention'));
$t('Needs attention: each trip, with its fix, opening its part\'s page in the side panel', str_contains($na, 'href="/admin/config/control/wf-W1" data-peek-frame>Overture bulk failed</a>: tripped. Its fix: Check Overture&#039;s download and run its check.') && str_contains($na, '>Common Crawl is down</a>: tripped. Its fix: Check and start again'), $na);
$t('…what each trip holds folded under it, in a few words', (bool) preg_match('~Overture bulk failed</a>.*?<details class="cb-plm"><summary>Waiting on it: 2</summary>.*?wf-W1#task-overture_bulk" data-peek-frame>Overture: businesses by town \(W1\)</a>: switched off for it by the board, before trips paused instead\. Nobody had set this switch before, so it stays off until someone switches it on there\..*?</details>~s', $na) && str_contains($na, 'svc-commoncrawl" data-peek-frame>Common Crawl</a>: paused until its check passes.') && (bool) preg_match('~Common Crawl is down</a>.*?<summary>Waiting on it: 1</summary>.*?svc-commoncrawl~s', $na), $na);
$t('…the one left by a closed trip is listed there on its own, saying what to do', (bool) preg_match('~wf-W5" data-peek-frame>W5[^<]*</a>: left off by a trip that has closed \(W5 failed its check\)\..*Switch it on there when it.s ready\.~', $na), $na);
$t('Off on purpose: folded, each with what it waits for', (bool) preg_match('~<details class="cb-plm"><summary>Off on purpose: 2</summary><ul class="cb-pl">.*Until the review.*switch-on check.*</ul></details>~s', $h), $h);
$t('the trips\' items aren\'t in Off on purpose', !str_contains(substr($h, (int) strpos($h, 'Off on purpose:')), 'Overture: businesses'));
$t('every item still opens in the side panel', substr_count($h, 'data-peek-frame') === 2 + 3 + 1 + 2, substr_count($h, 'data-peek-frame'));

// all well: green, and the words say so
$pdo->exec("UPDATE control_alarms SET cleared_at = UTC_TIMESTAMP()"); $pdo->exec("UPDATE system_pauses SET cleared_at = UTC_TIMESTAMP()");
$pdo->exec("UPDATE crawl_workflows SET paused = 0, paused_why = NULL"); $pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1, off_why = NULL");
Automation::forget();
$h2 = ControlBoard::pausedHtml($pdo, 'cb-paused', ['site:search' => 'ok']);
$t('nothing wrong: the health line is green and says what works, then "Paused now: Nothing."', str_contains($h2, 'data-tv-status="ok" data-tv-label="Health now:">Page load, search, sign-in, payments, database, email and site jobs: working. Data gathering, workers, outside services and the site&#039;s own parts: working.</p>') && str_contains($h2, 'data-tv-label="Paused now:">Nothing.'), $h2);
// the board passes its own lights
$board = tv_admin_mod_control($pdo);
$t('the board draws it in its top line, from its own read of each part', str_contains($board, 'id="cb-paused"') && str_contains($board, 'class="cb-health"'));
require_once $app . '/admin/automation-console.php';
$t('Automation\'s top has the same line', str_contains(tv_admin_mod_automation($pdo), 'id="au-paused" class="cb-paused"><p class="cb-health"'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
