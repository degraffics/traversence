<?php
// The Control Board's release 3e (decisions/0171 §4, the ledger; Jason, 2026-11-18: "queue the crawler run records fix"):
// Insights → Crawler opens each run to the records it touched, grouped by what happened, and a workflow's records for a
// day ("W1 today"). Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration>
//      && php r3e.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlLedger.php';
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; require_once $app . '/admin/crawler-console.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_run_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id INT NOT NULL, wf VARCHAR(8) NOT NULL, task VARCHAR(40) NOT NULL, outcome VARCHAR(20) NOT NULL,
    allotted_s INT NOT NULL DEFAULT 0, seconds INT NOT NULL DEFAULT 0, fetches INT NOT NULL DEFAULT 0, searches INT NOT NULL DEFAULT 0, counts TEXT NULL, why VARCHAR(500) NULL, at DATETIME NOT NULL)");
$pdo->exec("INSERT IGNORE INTO crawl_capture_tasks (task, wf, enabled) VALUES ('coverage', 'W1', 1), ('second_look', 'W2', 1)");
$pdo->exec("INSERT INTO crawl_runs (id, run_key, started_at, status, seconds) VALUES (77, 'r77', NOW(), 'ok', 140), (78, 'r78', NOW() - INTERVAL 2 DAY, 'ok', 90)");
$pdo->exec("INSERT INTO crawl_run_items (run_id, wf, task, outcome, seconds, at) VALUES (77, 'W1', 'coverage', 'done', 120, NOW())");
$pdo->exec("INSERT INTO control_ledger (id, at, endpoint, method, code, run_id, n_received, n_stored) VALUES (900, UTC_TIMESTAMP(), 'crawl/results', 'POST', 200, 77, 4, 2), (901, UTC_TIMESTAMP() - INTERVAL 2 DAY, 'crawl/results', 'POST', 200, 78, 1, 1)");
$pdo->exec("INSERT INTO control_records (ledger_id, at, kind, ref_id, what) VALUES (900, UTC_TIMESTAMP(), 'listing', 501, 'stored'), (900, UTC_TIMESTAMP(), 'listing', 502, 'updated: phone, website'),
    (900, UTC_TIMESTAMP(), 'review', 31, 'review'), (900, UTC_TIMESTAMP(), 'listing', 503, 'refused: phone kept (a person entered it)'), (901, UTC_TIMESTAMP() - INTERVAL 2 DAY, 'listing', 600, 'stored')");
$t('what each line was: added, changed, Review, refused', ControlLedger::whatKind('stored') === 'stored' && ControlLedger::whatKind('updated: phone') === 'updated' && ControlLedger::whatKind('review') === 'review' && ControlLedger::whatKind('refused: x') === 'refused');
$t('each run\'s count of records', ControlLedger::runCounts($pdo, [77, 78]) === [77 => 4, 78 => 1], ControlLedger::runCounts($pdo, [77, 78]));
$p = tv_crawler_run_page($pdo, 77);
$t('a run\'s page: the command strip with its title and where it stands', str_contains($p, 'Crawler run #77') && str_contains($p, '4 records touched') && str_contains($p, 'id="cs"'), mb_substr(strip_tags($p), 0, 200));
$t('…its records under their workflow, grouped by what happened', str_contains($p, '<h2 class="hb-h">W1</h2>') && str_contains($p, '<b>Added</b> 1') && str_contains($p, '<b>Changed</b> 1') && str_contains($p, '<b>Sent to Review</b> 1') && str_contains($p, '<b>Refused (kept as they were)</b> 1'));
$t('…each opening its own page in a Peek, with what changed', str_contains($p, 'href="/admin/listing-edit.php?id=502" data-peek-frame') && str_contains($p, 'updated: phone, website') && str_contains($p, 'href="/admin/listing-intake.php?row=31"'));
$t('…a person\'s field kept is said so', str_contains($p, 'phone kept (a person entered it)'));
$t('…each count a button narrowing it', str_contains($p, 'href="?run=77&what=updated"'));
$_GET['what'] = 'updated'; $p2 = tv_crawler_run_page($pdo, 77); unset($_GET['what']);
$t('…narrowed to Changed: only those, with Show them all', str_contains($p2, '502') && !str_contains($p2, 'id=501') && str_contains($p2, 'Show them all'));
$t('a run no longer kept says so, with the way back', str_contains(tv_crawler_run_page($pdo, 99999), 'isn\'t kept any more') && str_contains(tv_crawler_run_page($pdo, 99999), 'Back to the crawler'));
$w = tv_crawler_records_page($pdo, 'W1', 'today');
$t('"W1 today": today\'s four, by kind and by run', str_contains($w, 'W1, today (UTC): 4 records') && str_contains($w, 'Run #77') && !str_contains($w, 'id=600'), mb_substr(strip_tags($w), 0, 200));
$t('…the last 7 days takes in the older run', str_contains(tv_crawler_records_page($pdo, 'W1', '7'), '5 records'));
$t('…another workflow with none says so, with the way back', str_contains(tv_crawler_records_page($pdo, 'W2', 'today'), 'touched no listing') && str_contains(tv_crawler_records_page($pdo, 'W2', 'today'), '/admin/insights/crawler'));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
