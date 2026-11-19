<?php
// The Control Board's 5b, the reports (decisions/0171 §11): for the whole system, a system or one process, over a day or a
// week, built on demand from what's kept (no new table, no AI, no outside call): uptime, what ran, records, slow calls,
// retries and crashes, spend, alarms with how long each was open, shut-downs and switches, the trend; a summary of three
// to five lines; Download as CSV (the page's columns) and the page as a file (self-contained); nothing private.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r5b.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlReport.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/ControlHistorian.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
foreach (['control_alarms', 'control_log', 'control_readings', 'control_daily'] as $tb) $pdo->exec("DROP TABLE IF EXISTS $tb");
ControlAlarms::ensure($pdo); ControlLog::ensure($pdo); ControlHistorian::ensure($pdo);
$now = time(); $at = fn (int $ago) => gmdate('Y-m-d H:i:s', $now - $ago);
// W1 up 20 hours, down 4 (a state row each change)
$pdo->prepare("INSERT INTO control_readings (proc, reading, value, state, at) VALUES ('wf:W1', 'state', 0, 'ok', ?), ('wf:W1', 'state', 0, 'bad', ?)")->execute([$at(30 * 3600), $at(4 * 3600)]);
// alarms: one in the window with a member's email and phone in its title; one cleared before the window; one on W2
$pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, reading, n_times, first_at, last_at, ack_name, ack_at, cleared_at, cleared_how) VALUES
  ('a1', 'wf:W1', 'trip', 'crash', 'W1 crashed on jane.member@example.com (480) 555-1234 from 203.0.113.9', 'secret reading jane.member@example.com', 2, ?, ?, 'Jason (staff)', ?, ?, 'Checked and started again'),
  ('a2', 'wf:W1', 'warning', 'slow', 'W1 slow long ago', '', 1, ?, ?, NULL, NULL, ?, 'speed back'),
  ('a3', 'wf:W2', 'warning', 'retry', 'W2 backing off', '', 1, ?, ?, NULL, NULL, NULL, NULL)")->execute([$at(5 * 3600), $at(5 * 3600), $at(4 * 3600), $at(3 * 3600), $at(3 * 86400), $at(3 * 86400), $at(2 * 86400), $at(3600), $at(3600)]);
ControlLog::add($pdo, 'stop', 'wf:W1', 'W1 shut down', 'Jason (staff)', 'testing');
$r = ControlReport::build($pdo, '', 'day', $now);
$t('a report has every section of 0171 §11', array_keys($r['sections']) === ['uptime', 'ran', 'alarms', 'faults', 'changes', 'spend', 'trend'], array_keys($r['sections']));
$up = array_values(array_filter($r['sections']['uptime'][2], fn ($x) => str_starts_with((string) $x[0], 'W1')))[0] ?? null;
$t('uptime from the historian: W1 up 20 of 24 hours, 83.3%', $up && $up[2] === '83.3%', $up);
$al = $r['sections']['alarms'][2];
$t('alarms in the window only (the one cleared 2 days ago is left out)', count($al) === 2 && !array_filter($al, fn ($x) => str_contains((string) $x[3], 'long ago')), array_column($al, 3));
$a1 = array_values(array_filter($al, fn ($x) => str_starts_with((string) $x[3], 'W1 crashed')))[0] ?? null;
$t('…each with who acknowledged it, when it cleared, how long it was open and how', $a1 && $a1[5] === 'Jason (staff)' && is_array($a1[6]) && $a1[7] === '2 h' && $a1[8] === 'Checked and started again', $a1);
$t('…and nothing private: the email, phone and IP in its title are taken out; readings never included', $a1 && !str_contains(json_encode($r), 'jane.member') && !str_contains(json_encode($r), '555-1234') && !str_contains(json_encode($r), '203.0.113.9') && !str_contains(json_encode($r), 'secret reading'), $a1[3] ?? null);
$f = $r['sections']['faults'][2];
$t('slow calls, retries and crashes counted from what was raised in the window', $f[0][1] === 0 && $f[1][1] === 1 && $f[2][1] === 1, $f);
$t('shut-downs and switches from the logbook, with who and why', (bool) array_filter($r['sections']['changes'][2], fn ($x) => $x[2] === 'W1 shut down' && $x[3] === 'Jason (staff)' && $x[4] === 'testing'));
$t('a summary of three to five lines', count($r['summary']) >= 3 && count($r['summary']) <= 5 && str_contains($r['summary'][1], '1 trip raised'), $r['summary']);
// a scope: one process
$r2 = ControlReport::build($pdo, 'wf:W2', 'day', $now);
$t('one process\'s report holds only its own alarms', count($r2['sections']['alarms'][2]) === 1 && $r2['sections']['alarms'][2][0][3] === 'W2 backing off' && $r2['name'] !== 'The whole system', $r2['sections']['alarms'][2]);
$r3 = ControlReport::build($pdo, 'nonsense; DROP', 'month', $now);
$t('an unknown scope or range falls back to the whole system over a day', $r3['scope'] === '' && $r3['range'] === 'day');
$rw = ControlReport::build($pdo, '', 'week', $now);
$t('a week takes in the alarm from 2 days ago', count($rw['sections']['alarms'][2]) === 3);
// downloads
$csv = ControlReport::csv($r); $rows = array_map('str_getcsv', array_filter(explode("\n", $csv), 'strlen'));
$hdrOk = true; foreach ($r['sections'] as [$title, $cols]) { $i = array_search([$title], $rows, false); if ($i === false || ($rows[$i + 1] ?? []) !== $cols) $hdrOk = false; }
$t('the CSV: each section its heading, then the page\'s columns', $hdrOk);
$t('…moments in ISO UTC, and nothing private', (bool) preg_match('/\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ/', $csv) && !str_contains($csv, 'jane.member') && !str_contains($csv, '555-1234'));
$file = ControlReport::file($r);
$t('the page as a file is self-contained: no script, no outside file, no link back in', str_starts_with($file, '<!doctype html>') && !preg_match('/<script|<link|src=|href=/i', $file) && str_contains($file, 'Times are UTC'));
// on the board, as a section of config/control (never a page outside the manifests)
$cc = file_get_contents($app . '/admin/control-console.php');
$t('the report is a query on the board (?report=), and the board has its Reports panel', str_contains($cc, "if (isset(\$_GET['report'])) return tv_cb_report_page(") && str_contains($cc, '$h .= tv_cb_reports_panel($pdo);') && str_contains($cc, 'id="reports"'));
$api = file_get_contents(dirname($app) . '/website/admin/api.php'); $hp = file_get_contents(dirname($app) . '/website/api/health.php'); $dr = file_get_contents($app . '/lib/DailyReport.php');
$t('downloads through the board\'s endpoint; the health report and the 5 am daily report carry the summary (Monday the week\'s)', str_contains($api, "if (\$act === 'report')") && str_contains($hp, "\$out['alarms_summary']") && str_contains($dr, "ControlReport::summary(\$pdo, 'week')") && str_contains($dr, "format('N') === '1'"));
$src = file_get_contents($app . '/lib/ControlReport.php');
$t('nothing new is kept and nothing goes outside: no table, no AI, no outside call', !preg_match('/CREATE TABLE|INSERT INTO|curl_|file_get_contents\(.http|Gemini|Anthropic/i', $src));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
