<?php
// decisions/0175 release 2a, part 2: the board's lines and words.
// - What's paused now, by the system or a person, and why, each opening its fix: the board's top line and Automation's
//   (0175's note "pre-flight before a module is active, diagnostics once it is"; §6); the pre-flight's result in Start-up.
// - "Diagnostics" once active; "Check before switching on" only before Start or while the master is off (the same note).
// - A need on its backup is a working state, never Holding (0175 §3; Jason, 2026-11-20).
// - A row with an open warning shows the warning's mark, never a green ✓; no Acknowledge on a warning (0171 §7's 3c note).
// - A system's hour is red only when a critical part was down; another part held or down makes it amber, named (0175's note,
//   Jason 2026-11-20: "Critical part down"); each strip says what it counts.
// - A task's switch on its workflow's page, with the services each step uses and their backups (0175 §6).
// Run: mariadb < ../release-b-tests/start-schema.sql && php lines.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php';
require_once $app . '/lib/crawler/CrawlService.php'; require_once $app . '/lib/Automation.php'; require_once $app . '/lib/ControlBoard.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlHistorian.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_run_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id INT NOT NULL DEFAULT 0, wf VARCHAR(8) NOT NULL DEFAULT '', task VARCHAR(40) NOT NULL, outcome VARCHAR(20) NOT NULL, why VARCHAR(255) NULL, at DATETIME NOT NULL)");
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec("UPDATE automation_switches SET paused = 0 WHERE kind = 'master'");
$pdo->exec("UPDATE crawl_workflows SET paused = 0");
$pdo->exec("DELETE FROM crawl_capture_tasks WHERE task IN ('overture_bulk', 'geofabrik_bulk')");
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, mode, enabled, label, name, what, updated_at) VALUES ('overture_bulk', 'W1', 'bulk', 1, 'Overture', 'Overture: businesses by town', 'Monthly loads.', NOW())");
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1");
Automation::forget();

// ---- nothing paused
$h0 = ControlBoard::pausedHtml($pdo);
$t('nothing paused: the line says so, green', str_contains($h0, 'data-tv-status="ok" data-tv-label="Paused now:">Nothing.'), $h0);
// ---- a person's pauses and the system's
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 0, off_why = 'Waiting for its new key' WHERE task = 'overture_bulk'");
$tk1 = (string) $pdo->query("SELECT task FROM crawl_capture_tasks WHERE wf = 'W1' AND enabled = 0 LIMIT 1")->fetchColumn();
$pdo->exec("UPDATE crawl_workflows SET paused = 1, paused_why = 'Until the review' WHERE wf = 'W3'");
Automation::ensurePauses($pdo); Automation::sysPause($pdo, 'service', 'commoncrawl', 'index.commoncrawl.org failed 5 times', 7);
Automation::forget();
$all = ControlBoard::pausedNow($pdo);
$by = []; foreach ($all as $x) $by[$x[3]] = $x;
$t('a task a person switched off: named, by a person, why, and its fix on its workflow\'s page', isset($by['/admin/config/control/wf-W1#task-' . $tk1]) && $by['/admin/config/control/wf-W1#task-' . $tk1][1] === 'a person' && str_contains($by['/admin/config/control/wf-W1#task-' . $tk1][2], 'Waiting for its new key'), $all);
$t('a paused workflow: its own page', isset($by['/admin/config/control/wf-W3']) && str_contains($by['/admin/config/control/wf-W3'][2], 'Until the review'));
$t('the system\'s pause: by the system (a trip), with its reason, and the service\'s card', isset($by['/admin/config/register#svc-commoncrawl']) && $by['/admin/config/register#svc-commoncrawl'][1] === 'the system (a trip)' && str_contains($by['/admin/config/register#svc-commoncrawl'][2], 'It resumes when its check passes'), $all);
$h1 = ControlBoard::pausedHtml($pdo);
// grouped since 0175's note (2026-11-20, "Paused now gives a clear picture"): a pause whose trip isn't open needs a look itself
$t('the line counts them in groups, each a link that opens in the side panel', str_contains($h1, '1 held by the system with no open trip, 2 off on purpose.') && substr_count($h1, 'data-peek-frame') === 3, $h1);
// ---- the board's top line, and the pre-flight in Start-up
$board = tv_admin_mod_control($pdo);
$top = substr($board, (int) strpos($board, 'cb-top cb-strip'), 4000);
$t('the board\'s top line is what\'s paused now', str_contains($top, 'id="cb-paused"') && !str_contains(substr($top, 0, (int) strpos($top, 'cb-core')), 'Pre-flight,'), mb_substr($top, 0, 600));
$t('…and the pre-flight\'s result, with Run it again, is its own block for the Start-up stage', (bool) preg_match('~<div class="cb-top cb-pfres" id="cb-pfres"><span class="cb-result[^>]*>.*Pre-flight, .*Run it again~s', $board));
$js = (string) file_get_contents(dirname($app) . '/website/js/admin-control.js');
$t('…shown on the Pre-flight and Start-up tabs', str_contains($js, "['#cb-pfres', 'preflight startup']"));
// ---- Automation: the same line; Start's words; Check before switching on vs Diagnostics
require_once $app . '/admin/automation-console.php';
$au = tv_admin_mod_automation($pdo);
$t('Automation\'s top has the same line', str_contains($au, 'id="au-paused"') && str_contains($au, 'Waiting for its new key'));
$t('…and never says "Everything is on"', !str_contains($au, 'Everything is on'));
$t('before Start: "Check before switching on"', str_contains($au, 'data-tv-label="Check before switching on:"'));
require_once $app . '/lib/SearchIndex.php'; SearchIndex::setState($pdo, SystemStart::KEY, json_encode(['state' => 'done', 'run' => time() - 600, 'by' => 'Jason', 'items' => []]));
$au2 = tv_admin_mod_automation($pdo);
$t('Start done, the master on: the same lines are "Diagnostics"', str_contains($au2, 'data-tv-label="Diagnostics:"') && !str_contains($au2, 'data-tv-label="Check before switching on:"'));
$t('…and Start\'s card says it ran to its end, pointing to what\'s paused now', str_contains($au2, 'Start ran to its end.') && str_contains($au2, "What's paused now is at the top of this page."));
$t('Automation\'s Tasks: one line per workflow, opening its page in the side panel', str_contains($au2, 'href="/admin/config/control/wf-W1#cb-steps" data-peek-frame>Open W1</a>') && !str_contains($au2, 'name="au_do" value="task_off"'));
// ---- a workflow on a backup (0175 §3)
$pdo->exec("DELETE FROM crawl_capture_tasks WHERE task = 'geofabrik_bulk'");
$pdo->exec("INSERT INTO crawl_capture_tasks (task, wf, mode, enabled, label, name, what, updated_at) VALUES ('geofabrik_bulk', 'W1', 'bulk', 1, 'Geofabrik', 'Geofabrik: OpenStreetMap by state', 'The state files.', NOW())");
$pdo->exec("INSERT INTO crawl_run_items (task, wf, outcome, why, at) VALUES ('geofabrik_bulk', 'W1', 'failed', 'download.geofabrik.de answered 503', NOW())");
Preflight::results($pdo);
$pdo->prepare('REPLACE INTO preflight_results (id, v, at) VALUES (?, ?, NOW())')->execute(['nd:bulk', json_encode(['s' => 'ok', 'said' => 'Answered by its first source. 1. Ours (our open-data copies): about 9,000 rows, newest today; 2. Overture Maps (Places): works; 3. Foursquare open places: works; 4. Geofabrik (OpenStreetMap extracts): not ready.', 'at' => time()])]);
ControlRegister::forget();   // its task list is read once a request
$cov = ControlBoard::onBackup(Preflight::results($pdo), ['bulk'], ['geofabrik_bulk']);
$t('a failing task a backup covers: named with the need and the source answering it', $cov !== null && $cov['geofabrik_bulk'][0] === 'Businesses in bulk' && str_starts_with($cov['geofabrik_bulk'][1], 'Ours'), $cov);
$t('…a task with no outside service isn\'t "covered"', ControlBoard::onBackup(Preflight::results($pdo), ['bulk'], ['nope_task']) === null);
$p = ControlBoard::process($pdo, 'wf:W1', ['engine', 'W1 Listings'], Preflight::results($pdo));
$t('W1 with only that failure: amber, "On a backup", never held', $p['light'] === 'warn' && str_contains($p['words'], 'is answered by Ours') && str_starts_with($p['means'], 'On a backup'), [$p['light'], $p['words'], $p['means']]);
[$bl] = ControlBoard::boardLight($pdo, 'wf:W1', $p, []);
$t('…and the board\'s light keeps it amber (not Holding)', $bl === 'warn', $bl);
// ---- a warning's mark, and no Acknowledge on it
ControlAlarms::raise($pdo, 'x:w2warn', 'wf:W2', 'warning', 'retry', 'W2: its last call failed', 'The system tries again.', '', '', [], false, false);
[$rows] = tv_cb_table_rows($pdo, []);
$w2 = null; foreach ($rows as $g) foreach ($g['_group']['rows'] ?? [] as $r) if ($r['id'] === 'wf:W2') $w2 = $r;
$t('a part with an open warning shows Warning (amber), never a green Working', $w2 && $w2['light']['word'] === 'Warning' && $w2['light']['tone'] === 'warn', $w2['light'] ?? null);
$t('…and its mark is "!", not a ✓', str_contains(tv_cb_mark('alarm'), 'Warning: the system is handling it') && !str_contains(tv_cb_mark('alarm'), 'check'));
$al = array_values(array_filter(ControlAlarms::list($pdo), fn ($x) => $x['cleared_at'] === null));
$btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb="' . $act . '">' . $label . '</button>';
$ha = tv_cb_alarms($al, ControlAlarms::counts($pdo), ['wf:W2' => 'W2'], fn ($id) => 'cb-p-' . str_replace(':', '-', $id), true, '', $btn);
$t('no Acknowledge on a warning the system is handling', !str_contains($ha, 'data-cb="ack"'), mb_substr($ha, 0, 400));
ControlAlarms::raise($pdo, 'x:note', 'wf:W2', 'notice', 'check', 'W2: a note', 'm', '', '', [], false, false);
$al = array_values(array_filter(ControlAlarms::list($pdo), fn ($x) => $x['cleared_at'] === null && $x['level'] === 'notice'));
$t('…a notice still has it', str_contains(tv_cb_alarms($al, ControlAlarms::counts($pdo), ['wf:W2' => 'W2'], fn ($id) => 'x', true, '', $btn), 'data-cb="ack"'));
// ---- the red hour (a system) and what each strip says
ControlHistorian::ensure($pdo); $pdo->exec('DELETE FROM control_readings'); $pdo->exec('DELETE FROM control_daily');
$now = intdiv(time(), 3600) * 3600 + 1800; $h2 = $now - 3 * 3600; $h1x = $now - 2 * 3600;
$ins = $pdo->prepare("INSERT INTO control_readings (proc, reading, value, state, at) VALUES (?, 'state', 0, ?, ?)");
$sysProcs = array_keys(array_filter(ControlRegister::processes($pdo), fn ($p) => $p[0] === 'services'));
$crit = 'svc:bluehost'; $other = 'svc:commoncrawl';
foreach ($sysProcs as $pk) $ins->execute([$pk, 'ok', gmdate('Y-m-d H:i:s', $now - 86000)]);
$ins->execute([$other, 'bad', gmdate('Y-m-d H:i:s', $h2)]); $ins->execute([$other, 'ok', gmdate('Y-m-d H:i:s', $h2 + 1200)]);
$ins->execute([$crit, 'bad', gmdate('Y-m-d H:i:s', $h1x)]); $ins->execute([$crit, 'ok', gmdate('Y-m-d H:i:s', $h1x + 600)]);
$tr = ControlHistorian::trend($pdo, 'sys:services', '24h', $now);
$st = []; foreach ($tr['buckets'] as $b) $st[$b['at']] = $b;
$hb = intdiv($h2, 3600) * 3600; $cb = intdiv($h1x, 3600) * 3600;
$t('a system\'s hour with only a non-critical part down is amber, the part named', ($st[$hb]['state'] ?? '') === 'held' && in_array('Common Crawl', array_map(fn ($n) => preg_replace('/ .*/', '', $n) === 'Common' ? 'Common Crawl' : $n, $st[$hb]['held']), true) || (($st[$hb]['state'] ?? '') === 'held' && $st[$hb]['held']), $st[$hb] ?? null);
$t('…an hour with a critical part down is red', ($st[$cb]['state'] ?? '') === 'down', $st[$cb] ?? null);
$html = ControlHistorian::html($tr, 'sys:services');
$t('…the strip says what it counts (red only for a critical part), and names what was held, in words', str_contains($html, 'red only when a critical part') && str_contains($html, 'Held: ') && str_contains($html, 'the time it was due on, spent green or amber'), mb_substr(strip_tags($html), 0, 600));
$tp = ControlHistorian::trend($pdo, $other, '24h', $now); $sp = []; foreach ($tp['buckets'] as $b) $sp[$b['at']] = $b['state'];
$t('a process\'s own hour: red when it was down', ($sp[$hb] ?? '') === 'down', $sp[$hb] ?? null);
// ---- a workflow's steps and their switches
$_SERVER['REQUEST_URI'] = '/admin/config/control/wf-W1';
$wp = tv_admin_mod_control($pdo);
$t('W1\'s own page lists its steps, each anchored', str_contains($wp, 'id="cb-steps"') && str_contains($wp, 'id="task-geofabrik_bulk"') && str_contains($wp, 'id="task-' . $tk1 . '"'));
$gs = substr($wp, (int) strpos($wp, 'id="task-geofabrik_bulk"'), 2500);
$t('…a step names the service it uses and its backups, each card in the side panel', str_contains($gs, 'href="/admin/config/register#svc-geofabrik" data-peek-frame>Geofabrik') && str_contains($gs, 'its backup: Ours'), strip_tags($gs));
$t('…an on step offers Switch off, asking why', str_contains($gs, 'data-cb="taskoff"') && str_contains($gs, 'data-cb-ask="Why is'), $gs);
$os = substr($wp, (int) strpos($wp, 'id="task-' . $tk1 . '"'), 2000);
$t('…an off step says why and offers Switch on', str_contains($os, 'Waiting for its new key') && str_contains($os, 'data-cb="taskon"'), strip_tags($os));
$cc = (string) file_get_contents($app . '/admin/crawler-console.php');
$t('Insights → Crawler\'s on and off are links to that switch', str_contains($cc, "/admin/config/control/wf-' . \$e((string) \$r['wf']) . '#task-'") && !str_contains($cc, "CrawlService::setTask(\$pdo, \$task, false)"));
// ---- the card: Diagnostics once active
$api = (string) file_get_contents(dirname($app) . '/website/admin/api.php');
$t('the board\'s switch is Automation\'s own (setTask, its permission, a reason asked when off)', str_contains($api, "if (\$act === 'taskon' || \$act === 'taskoff')") && str_contains($api, "Access::can('config.automation'") && str_contains($api, 'Say why it'));
$os2 = (string) file_get_contents($app . '/ui/outside-services.php');
$t('an active service\'s card says Diagnostics, never Pre-flight', str_contains($os2, "(\$active ? 'Diagnostics' : 'Pre-flight')"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
