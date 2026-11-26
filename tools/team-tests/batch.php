<?php
// Piece 2, P2-5 (decisions/0179 rule 1; Jason's Q3 A, 2026-11-25): the daily batch and the Founder's emailed answers, on a real
// MySQL or MariaDB. gauges.json carries {batch, answers} (gauges.py, batch_py.py); the site reads them: the batch strip at the
// top of the Work queue (today's, or the latest until it's approved; "No batch from the session manager today" otherwise; the Tier 3 decisions waiting
// listed first; Approve the batch greyed but for the Founder's own login); Approve records kind 'batch' for that date once,
// and report() carries it to the session manager; each emailed answer applied once, only to a Resolution waiting for the
// Founder, as "Jason (by email, recorded by the session manager)", with History and its precedent ticket.
// Run: php batch.php <root>/app   (it resets lv_main from before-schema.sql)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php batch.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$run(__DIR__ . '/before-schema.sql'); $H = dirname(__DIR__, 2) . '/handoffs/';
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql'] as $f) $run($f);
foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql'] as $f) $run($app . '/migrations/' . $f);
$pdo->exec("CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS site_releases (id INT AUTO_INCREMENT PRIMARY KEY, zip_name VARCHAR(190) NOT NULL DEFAULT '', state VARCHAR(20) NOT NULL DEFAULT '', live_at DATETIME NULL)");
require_once $app . '/lib/GaugeTasks.php'; require_once $app . '/lib/ControlGauges.php'; require_once $app . '/admin/Access.php';
if (!function_exists('tv_icon')) { function tv_icon(string $n, int $s = 16): string { return '<i data-i="' . $n . '"></i>'; } }
require_once $app . '/admin/widgets.php'; require_once $app . '/admin/control-console.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$J = 'Jason (test)';
$res = function (string $what) use ($pdo, $J) { $r = GaugeTasks::add($pdo, 'area:backlog', $what, 'why', '0179', $J, 1, 'America/Phoenix', 'resolution');
    foreach (['why_you' => 'Privacy (0179 rule 3)', 'boundary' => 'privacy', 'a_text' => 'Option one', 'a_pros' => 'p', 'a_cons' => 'c', 'b_text' => 'Option two', 'b_pros' => 'p', 'b_cons' => 'c', 'recommend' => 'b', 'delay_impact' => 'Safe on hold', 'cost' => 'None'] as $k => $v) GaugeTasks::briefEdit($pdo, $r, $k, $v, $J);
    return $r; };
$r1 = $res('May staff text go to Gemini\'s paid key?'); GaugeTasks::sendBrief($pdo, $r1, $J);
$r2 = $res('A second decision, still being briefed');
$r3 = $res('A third, answered in his words'); GaugeTasks::sendBrief($pdo, $r3, $J);
// the gauges' file, as gauges.py writes it, with today's batch and two emailed answers (and two it must ignore)
$base = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/site-audit/results/gauges.json'), true);
$file = sys_get_temp_dir() . '/p25-gauges.json';
$write = function (array $batch, array $answers) use ($base, $file) { $c = $base; $c['batch'] = $batch; $c['answers'] = $answers; file_put_contents($file, json_encode($c)); };
$today = gmdate('Y-m-d');
$line = ['release' => 'team-runner.zip', 'what' => 'The Team\'s runner on staging', 'why' => 'Piece 2', 'decision' => '0179', 'checks' => 'runner.php 45 on staging', 'way_back' => 'Roll back on the Releases page', 'tickets' => [$r1]];
$write(['date' => $today, 'lines' => [$line, ['release' => 'team-audit.zip', 'what' => '<b>tags</b> are words', 'tickets' => []]]], []);
ControlGauges::$source = $file; $pdo->exec('DELETE FROM page_cache');
$cfg = ControlGauges::config($pdo, true);
$t('the site reads today\'s batch from gauges.json: its date and two lines', ($cfg['batch']['date'] ?? '') === $today && count($cfg['batch']['lines'] ?? []) === 2, $cfg['batch'] ?? null);
$active = array_values(array_filter(GaugeTasks::queue($pdo), fn ($x) => in_array($x['state'], ['open', 'taken', 'fixing', 'late', 'waiting'], true)));
$h = tv_cb_wq_batch($pdo, $active);
$t('the strip: "2 releases waiting for your OK · 2 decisions waiting for you"', str_contains($h, '2 releases waiting for your OK · 2 decisions waiting for you'), strip_tags($h));
$t('… the Tier 3 decisions waiting first, before the releases, each a link to its ticket', strpos($h, 'wq-bw') < strpos($h, 'team-runner.zip') && str_contains($h, 'href="#wq-' . $r1 . '"') && str_contains($h, 'href="#wq-' . $r3 . '"') && !str_contains($h, 'href="#wq-' . $r2 . '" class="wq-btk">#' . $r2 . '</a> A second'));
$t('… each line: what, why, decision, checks on staging, way back, its tickets', str_contains($h, 'The Team&#039;s runner on staging') && str_contains($h, 'Checks on staging:') && str_contains($h, 'Way back:') && str_contains($h, 'Decision:'));
$t('… words from the file are escaped, never markup', str_contains($h, '&lt;b&gt;tags&lt;/b&gt;'));
$t('… Approve the batch greyed with why for anyone but the Founder (no staff session here)', (bool) preg_match('/data-wq="batchok"[^>]*disabled title="[^"]*Founder/', $h), $h);
// as the Founder
$sp = new ReflectionProperty(Access::class, 'staff'); $sp->setAccessible(true); $lp = new ReflectionProperty(Access::class, 'loaded'); $lp->setAccessible(true);
$sp->setValue(null, ['id' => 1, 'status' => 'active', 'role' => 'owner', 'level' => 'founder', 'name' => $J, 'user_id' => 1]); $lp->setValue(null, true);
$h = tv_cb_wq_batch($pdo, $active);
$t('the Founder: Approve the batch open, naming the releases', (bool) preg_match('/data-wq="batchok" data-date="' . $today . '" data-value="team-runner.zip, team-audit.zip"(?![^>]*disabled)/', $h), $h);
$id = GaugeTasks::approve($pdo, 'area:batch-' . $today, 'batch', 'team-runner.zip, team-audit.zip', 'the daily batch', $J, 1, 'America/Phoenix');
$t('Approve records kind batch for that date; again returns the same', $id > 0 && GaugeTasks::approve($pdo, 'area:batch-' . $today, 'batch', 'x', 'the daily batch', $J, 1) === $id);
$rep = GaugeTasks::report($pdo); $ap = array_values(array_filter($rep['approvals'], fn ($a) => $a['kind'] === 'batch'));
$t('report() carries it to the session manager: the day, the releases, the time', count($ap) === 1 && $ap[0]['gauge'] === 'area:batch-' . $today && $ap[0]['value'] === 'team-runner.zip, team-audit.zip', $ap);
$h = tv_cb_wq_batch($pdo, $active);
$t('the strip then says Approved by whom, with no button', str_contains($h, 'Approved</b> by Jason (test)') && !str_contains($h, 'data-wq="batchok"'));
$t('a malformed day refused', (function () use ($pdo, $J) { try { GaugeTasks::approve($pdo, 'area:batch-tomorrow', 'batch', 'x', 'x', $J, 1); return false; } catch (Throwable $x) { return true; } })());
// not today's
$write(['date' => gmdate('Y-m-d', time() - 86400), 'lines' => [$line]], []); $pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true);
$h = tv_cb_wq_batch($pdo, $active);
$t('yesterday\'s batch, not yet approved: still shown as "The latest batch", with Approve (the session manager, 2026-11-26: a batch merged late in the UTC day never showed)', str_contains($h, 'The latest batch (') && str_contains($h, 'team-runner.zip') && str_contains($h, 'data-wq="batchok"'), strip_tags($h));
GaugeTasks::approve($pdo, 'area:batch-' . gmdate('Y-m-d', time() - 86400), 'batch', 'team-runner.zip', 'the daily batch', $J, 1);
$h = tv_cb_wq_batch($pdo, $active);
$t('… once approved: "No batch from the session manager today", the decisions still first, nothing to approve', str_contains($h, 'No batch from the session manager today · 2 decisions waiting for you') && !str_contains($h, 'team-runner.zip') && !str_contains($h, 'batchok'), strip_tags($h));
$write([], []); $pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true);
$t('no batch at all: the same line', str_contains(tv_cb_wq_batch($pdo, $active), 'No batch from the session manager today'));
// the emailed answers
$ans = [['id' => $r1, 'ans' => 'b', 'words' => '', 'at' => gmdate('c')], ['id' => $r2, 'ans' => 'a', 'words' => ''], ['id' => $r3, 'ans' => 'o', 'words' => 'Keep it public-only until I rule on 0164 §0.8'], ['id' => 999, 'ans' => 'a'], ['id' => $r1, 'ans' => 'x']];
$write([], $ans); $pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true);
$n = GaugeTasks::applyAnswers($pdo, (array) ControlGauges::config($pdo)['answers']);
$b1 = GaugeTasks::brief($pdo, $r1); $b3 = GaugeTasks::brief($pdo, $r3);
$t('two applied: B on the first, his words on the third', $n === 2 && $b1['answer'] === 'b' && $b3['answer'] === 'o' && $b3['answer_words'] === 'Keep it public-only until I rule on 0164 §0.8', [$n, $b1['answer'] ?? null, $b3['answer'] ?? null]);
$t('… recorded as "Jason (by email, recorded by the session manager)"', $b1['answered_by'] === 'Jason (by email, recorded by the session manager)');
$t('… each opens its precedent Work ticket', (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE ticket = 'work' AND parent_id IN ($r1, $r3)")->fetchColumn() === 2);
$t('… a History line on each', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE who = 'Jason (by email, recorded by the session manager)'")->fetchColumn() >= 2);
$t('the one still being briefed is left alone', (GaugeTasks::brief($pdo, $r2)['answer'] ?? '') === '' && GaugeTasks::task($pdo, $r2)['stage'] === 'brief');
$t('applied again (the next read of the file): nothing doubled', GaugeTasks::applyAnswers($pdo, $ans) === 0 && (int) $pdo->query("SELECT COUNT(*) FROM control_gauge_tasks WHERE ticket = 'work' AND parent_id IN ($r1, $r3)")->fetchColumn() === 2);
$pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true); ControlGauges::queueGauge($pdo);
$t('the Work queue\'s gauge applies them as it reads the file (queueGauge), without error', true);
@unlink($file);
// a batch approved in chat (Jason, 2026-11-26: "No need to wait for approval"): once its releases are live, it says so and asks nothing
if (method_exists(ControlGauges::class, 'batchLive')) {
    $y = gmdate('Y-m-d', time() - 2 * 86400);   // a day no earlier test approved
    $l2 = ['release' => 'chat-a.zip', 'what' => 'A', 'why' => '', 'decision' => '', 'checks' => '', 'way_back' => '', 'tickets' => []];
    $l3 = ['release' => 'chat-b.zip', 'what' => 'B', 'why' => '', 'decision' => '', 'checks' => '', 'way_back' => '', 'tickets' => []];
    $write(['date' => $y, 'lines' => [$l2, $l3]], []); $pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true);
    $pdo->exec("INSERT INTO site_releases (zip_name, state, live_at) VALUES ('chat-a.zip', 'live', UTC_TIMESTAMP())");
    $h = tv_cb_wq_batch($pdo, []);
    $t('an older batch, one of its two releases live: "1 release waiting for your OK", that one marked "Live as #N", Approve still offered', str_contains($h, '1 release waiting for your OK') && preg_match('/chat-a\.zip<\/b> <span class="wq-blive">Live as #\d+<\/span>/', $h) && str_contains($h, 'data-wq="batchok"'), strip_tags($h));
    $pdo->exec("INSERT INTO site_releases (zip_name, state, live_at) VALUES ('chat-b.zip', 'live', UTC_TIMESTAMP())");
    $t('… both live (sent on approval in chat): the older batch is no longer shown or emailed', ControlGauges::latestBatch($pdo) === [] && str_contains(tv_cb_wq_batch($pdo, []), 'No batch from the session manager today'));
    $write(['date' => $today, 'lines' => [$l2, $l3]], []); $pdo->exec('DELETE FROM page_cache'); ControlGauges::config($pdo, true);
    $pdo->exec("DELETE FROM control_gauge_tasks WHERE kind = 'batch' AND gauge = 'area:batch-" . $today . "'");
    $h = tv_cb_wq_batch($pdo, []);
    $t('today\'s batch with every release live: "Every release in it is live", each marked, no Approve the batch', str_contains($h, 'Every release in it is live') && substr_count($h, 'wq-blive') === 2 && !str_contains($h, 'data-wq="batchok"') && str_contains($h, 'data-tv-status="ok"'), strip_tags($h));
    $pdo->exec("UPDATE site_releases SET state = 'rolled_back' WHERE zip_name = 'chat-b.zip'");
    $t('… one rolled back: it waits again, with Approve', str_contains(tv_cb_wq_batch($pdo, []), '1 release waiting for your OK') && str_contains(tv_cb_wq_batch($pdo, []), 'data-wq="batchok"'));
}
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
