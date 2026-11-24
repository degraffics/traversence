<?php
// board-phone's PHP checks (Run: release-b-tests/start-schema.sql, then php board-phone.php <root>/app): the board's pages carry the
// threads' look even when the request's first Thread::html was thrown away (ControlBoard::settle, run on every draw, can
// render a Pulse or email copy first: Jason's 7:46 PM screenshot of W1's side panel ran "…UTC.What it affects: Holds: W1
// ListingsWhat the system is doing…" together); the Alerts count is ControlAlarms::counts' (02-state-model P9).
$app = $argv[1]; require $app . '/lib/Database.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php'; require_once $app . '/lib/Thread.php';
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
ControlAlarms::raise($pdo, 'task:failed:overture_bulk', 'wf:W1', 'trip', 'crash', 'overture_bulk failed in its own code (run #1290)', 'Its work goes back.', 'Read its why.', 'run #1290', [], true);
$pdo->exec("UPDATE control_alarms SET level = 'trip', tripped_at = UTC_TIMESTAMP() WHERE akey = 'task:failed:overture_bulk'");   // tripped (raise tries first: a warning until then)
ControlAlarms::raise($pdo, 'job:x:late', 'job:x', 'warning', 'late', 'x (job) is late', 'It runs late.', 'Nothing to do.', 'late', [], true);
ControlAlarms::raise($pdo, 'job:y:late', 'job:y', 'warning', 'late', 'y (job) is late', 'It runs late.', 'Nothing to do.', 'late', [], true);
$pdo->exec("UPDATE control_alarms SET ack_at = UTC_TIMESTAMP(), ack_name = 'Jason' WHERE akey = 'job:y:late'");
ControlAlarms::raise($pdo, 'pf:nd:z', 'wf:W1', 'notice', 'check', 'Places (W1): running on a backup', 'It works on a backup.', 'Check.', 'source 2', [], true);
// a thread drawn first and thrown away, as settle's Pulse or email copy is
$thrown = Thread::html(['title' => 'thrown away', 'level' => 'trip', 'status' => 'x'], 'pulse');
$t('the thrown-away copy took the once-per-request look (the old way the board got it)', str_contains($thrown, '.tv-th-parts{display:grid'));
foreach (['the board' => '', 'W1\'s own page (its side panel)' => 'wf-W1'] as $nm => $proc) {
    $_GET = $proc !== '' ? ['proc' => $proc] : [];
    $h = tv_admin_mod_control($pdo);
    $t($nm . ': carries the threads\' look (.tv-th-parts as a grid) after a thrown-away first thread', str_contains($h, '.tv-th-parts{display:grid'));
    if ($proc !== '') $t($nm . ': its alarm parts are drawn as the look expects', str_contains($h, 'class="tv-th-parts"'));
}
$c = ControlAlarms::counts($pdo); $one = $c['trip'] + $c['warning'];
$_GET = []; $h = tv_admin_mod_control($pdo);
preg_match('~<details class="cb-pop cb-alerts[^"]*"><summary[^>]*><span class="cb-alw">Alerts</span><b>(\d+)</b>~', $h, $m);
preg_match('~data-cb-rail="deliver">.*?<span class="cb-rcnt"[^>]*>(\d+)<~', $h, $r);
$t('Alerts and the rail\'s Board say ControlAlarms::counts\' number (1 trip + 1 unseen warning; the seen warning and the notice not in it)', $one === 2 && ($m[1] ?? '') === '2' && ($r[1] ?? '') === '2', [$one, $m[1] ?? null, $r[1] ?? null]);
$t('no row of chips: one Alerts dropdown and the phone\'s ☰ in "What\'s wrong now"', substr_count((string) strstr((string) strstr($h, '<nav class="cb-qbar"'), '</nav>', true), '<details class="cb-pop') === 2);
// the summary names each group as the list does; a seen warning is listed under Seen, never counted; no "warning" pill
preg_match('~<small class="cb-als">([^<]*)</small>~', $h, $sm);
$t('Alerts\' summary names each group as its list does ("1 trip · 1 warning")', ($sm[1] ?? '') === '1 trip · 1 warning', $sm[1] ?? null);
$seen = (string) (preg_match('~<section class="cb-ag n" aria-label="Seen">.*?</section>~s', $h, $sx) ? $sx[0] : '');
$t('…the seen warning is under Seen, said as not counted', str_contains($seen, 'y (job) is late') && str_contains($seen, 'Not counted') && preg_match('~<b>1</b>~', $seen), mb_substr($seen, 0, 200));
$warn = (string) (preg_match('~<section class="cb-ag a" aria-label="Warnings">.*?</section>~s', $h, $wx) ? $wx[0] : '');
$t('…Warnings\' rows carry no "warning" pill (its heading says it)', $warn !== '' && !str_contains($warn, '<b>warning</b>'), mb_substr($warn, 0, 200));
// W1 waiting for its run (both trips started again): its row says so, never "Tripped: stopped"
$pdo->exec("UPDATE control_alarms SET resolve_note = 'W1 runs again (Jason, 04:13 UTC).' WHERE akey = 'task:failed:overture_bulk'");
[$light, $means] = ControlBoard::boardLight($pdo, 'wf:W1', ['light' => 'bad'], ['wf:W1' => true]);
$t('a part whose trips all started again: amber, Waiting for its run, never "Tripped: stopped"', $light === 'hold' && str_starts_with($means, 'Waiting for its run') && !str_contains($means, 'stopped'), [$light, $means]);
[$rows] = tv_cb_table_rows($pdo, ['q' => 'W1']); $w1 = null; foreach ($rows as $g) foreach ($g['_group']['rows'] ?? [] as $r) if ($r['id'] === 'wf:W1') $w1 = $r;
$t('…its row in the systems table: Waiting for its run, "Started again: …", Check and start again greyed with why', $w1 && $w1['light']['word'] === 'Waiting for its run' && $w1['stage'] === 'Waiting for its run' && str_starts_with($w1['where'], 'Started again:') && str_starts_with((string) ($w1['_off']['resolve'] ?? ''), 'Already started again'), $w1);
// the Scheduled tasks page's "Late": never sooner than the job's own schedule (seven daily jobs said Late at 30 to 600 minutes)
require_once $app . '/lib/Scheduler.php'; $every = ['minute' => 1, '5min' => 5, '10min' => 10, '15min' => 15, 'hourly' => 60, 'hourly2' => 60, '6hours' => 360, 'daily' => 1440, 'nightly' => 1440, 'weekly' => 10080];
$short = []; foreach (Scheduler::JOBS as $j => $x) if (isset($every[$x[2]]) && (int) $x[3] < $every[$x[2]]) $short[] = $j . ' ' . $x[2] . ' ' . $x[3];
$t('no job is "Late" before its own schedule comes round (every late limit at least its interval)', !$short, $short);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
