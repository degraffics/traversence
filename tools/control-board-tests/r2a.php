<?php
// The System Control Board's release 2a tests (decisions/0171 §4): the ledger written after each worker call through the
// endpoints' own _auth.php, the records it touched, the answer left as it was, and Start's watch counting a task's own
// "nothing to do" runs as good.
// Run: mariadb < r2a-schema.sql && mariadb lv_main < <app>/migrations/2026-11-18_control_ledger.sql
//      && (cd <root>/website && php -S 127.0.0.1:8765 &) && php r2a.php <root>/app
// The root holds the mirror with the release laid over it, and website/api/crawl/zz_test.php (in this folder as
// zz_test.php): a worker call that stores, refreshes and refuses. CRAWLER_API_TOKEN=testtoken, VIDEO_WORKER_TOKEN=vtoken.
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require $app . '/lib/ControlLedger.php'; require $app . '/lib/SystemStart.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$call = function (string $path, ?array $body = null, string $token = 'testtoken') {
    $c = curl_init('http://127.0.0.1:8765/api/' . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
    if ($body !== null) { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body)); }
    $out = curl_exec($c); $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    return [$code, json_decode((string) $out, true), (string) $out];
};
$last = fn () => $pdo->query('SELECT * FROM control_ledger ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$recs = fn (int $id) => $pdo->query('SELECT kind, ref_id, what FROM control_records WHERE ledger_id = ' . $id . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// one call, through the real _auth.php
$pdo->exec("INSERT INTO crawl_runs (run_key, started_at, status) VALUES ('r1', NOW(), 'running')");
$run = (int) $pdo->lastInsertId();
[$code, $ans, $raw] = $call('crawl/zz_test.php', ['candidates' => [['a' => 1], ['a' => 2], ['a' => 3]], 'log' => ['x', 'y']]);
$t('the worker gets its answer as before', $code === 200 && $raw === '{"ok":true,"already_known":2}', $raw);
$l = $last();
$t('one row: the endpoint, POST, 200, how long', $l && $l['endpoint'] === 'crawl/zz_test' && $l['method'] === 'POST' && (int) $l['code'] === 200 && (int) $l['ms'] >= 0, $l);
$t('…linked to the crawler run going on', (int) $l['run_id'] === $run, $l['run_id']);
$t('received: the candidates, not the log', (int) $l['n_received'] === 3, $l['n_received']);
$t('stored, updated, Review from what the libraries marked', (int) $l['n_stored'] === 1 && (int) $l['n_updated'] === 1 && (int) $l['n_review'] === 1, $l);
$t('refused by reason: already known (the answer) and a person\'s entry kept', (int) $l['n_refused'] === 3 && str_contains((string) $l['reasons'], 'already known: 2') && str_contains((string) $l['reasons'], 'kept what a person entered: 1'), $l['reasons']);
$r = $recs((int) $l['id']);
$t('one line per record touched', count($r) === 4, $r);
$t('a listing written field by field is one line', $r[0]['what'] === 'updated: zz_phone, zz_site', $r[0]);
$t('a person\'s field kept is named', $r[1]['what'] === 'refused: zz_phone kept (a person entered it)', $r[1]);
$t('stored outranks updated on the same listing', $r[3]['kind'] === 'listing' && (int) $r[3]['ref_id'] === 503 && $r[3]['what'] === 'stored', $r[3]);
$t('the person\'s field itself is untouched', $pdo->query("SELECT meta_value FROM entity_metadata WHERE entity_id = 502 AND meta_key = 'zz_phone'")->fetchColumn() === '"0"');

// an error answer
[$code] = $call('crawl/zz_test.php', ['fail' => 1]);
$l = $last();
$t('an error answer is recorded with its code and why', $code === 409 && (int) $l['code'] === 409 && str_contains((string) $l['reasons'], 'answered 409'), $l);
// not signed in: nothing recorded
$n = (int) $pdo->query('SELECT COUNT(*) FROM control_ledger')->fetchColumn();
[$code] = $call('crawl/zz_test.php', [], 'wrong');
$t('a call that isn\'t signed in isn\'t recorded', $code === 401 && (int) $pdo->query('SELECT COUNT(*) FROM control_ledger')->fetchColumn() === $n);
// a GET that hands work out
[$code, $ans] = $call('crawl/jobs.php?limit=2');
$l = $last();
$t('a real endpoint is measured by itself (jobs.php), whatever its answer', $l['endpoint'] === 'crawl/jobs' && $l['method'] === 'GET' && (int) $l['code'] === $code, [$code, $l]);
// the video worker's side
[$code] = $call('video/jobs.php', null, 'vtoken');
$l = $last();
$t('the video worker\'s calls too', $l['endpoint'] === 'video/jobs', $l);
// before the database update: the call still answers, nothing logged as an error
$pdo->exec('RENAME TABLE control_ledger TO control_ledger_x');
[$code, , $raw] = $call('crawl/zz_test.php', ['candidates' => [['a' => 1]]]);
$pdo->exec('RENAME TABLE control_ledger_x TO control_ledger');
$t('without its table the call answers the same', $code === 200 && $raw === '{"ok":true,"already_known":2}', $raw);
// the counting rules on their own
$c = ControlLedger::counts(['staged' => [1, 2], 'auto_imported' => [9], 'already_known' => 4, 'skipped' => 0]);
$t('answer counts: lists and numbers, by kind, refusals by reason', $c['review'] === 2 && $c['stored'] === 1 && $c['refused'] === 4 && $c['reasons'] === ['already known' => 4], $c);
// trimming
$pdo->exec("UPDATE control_ledger SET at = UTC_TIMESTAMP() - INTERVAL 400 DAY WHERE id = 1");
$pdo->exec("UPDATE control_records SET at = UTC_TIMESTAMP() - INTERVAL 400 DAY WHERE ledger_id = 1");
ControlLedger::trim($pdo);
$t('rows past their days are trimmed', (int) $pdo->query('SELECT COUNT(*) FROM control_ledger WHERE id = 1')->fetchColumn() === 0 && (int) $pdo->query('SELECT COUNT(*) FROM control_records WHERE ledger_id = 1')->fetchColumn() === 0);
$fr = ControlLedger::forRun($pdo, $run);
$t('a run opens to its calls and records', count($fr['calls']) >= 1 && is_array($fr['records']), count($fr['calls']));

// Start's watch: a task's own crawler outcomes, "nothing to do" good
$pdo->exec("INSERT INTO crawl_run_items (run_id, wf, task, outcome, at) VALUES ($run, 'W2', 'recheck', 'idle', NOW()), ($run, 'W2', 'recheck', 'idle', NOW()), ($run, 'W2', 'recheck', 'done', NOW()), ($run, 'W2', 'recheck', 'failed', NOW()), ($run, 'W1', 'coverage', 'failed', NOW())");
$m = new ReflectionMethod('SystemStart', 'runs'); $m->setAccessible(true);
[$g, $b] = $m->invoke(null, $pdo, 't:recheck', [], 3600);
$t('the identity recheck: nothing to do and done are good runs, its own failure counted', $g === 3 && $b === 1, [$g, $b]);
[$g, $b] = $m->invoke(null, $pdo, 't:overture', [], 3600);
$t('another task isn\'t charged with them', $g === 0 && $b === 0, [$g, $b]);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
