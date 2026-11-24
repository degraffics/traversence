<?php
// api/crawl/index.php — W0 Indexer, the always-on worker on Railway (decisions/0164 §1.4's note of 2026-11-17).
//   GET  ?n=<URLs wanted>&secs=<seconds since its last check-in>&v=<worker version>&queue=<its local queue>
//        &mismatches=<receipts in a row that didn't match>&batch=<its batch size now>&gap=<its gap now>
//        -> {on, why, settings, work: [{u, h, host, etag, modified, again}], describe: [{host, home_url}], holds: {host: why}}
//        Off (staging, the master, W0's switch, its budget): {on: false, why, settings, sleep}; it checks in again later.
//   POST {key, kind: found|pages|about|test, n: <records sent>, rows: [...]}  -> {ok, receipt: {key, declared, received,
//        stored, rejected: [[key, why]], checksum, ok}}
//        found: [{u, f (the page it came from), t (link text)}]; pages: [{u, s, ty, b, e, m, fp, ti, k, n, fl, why}];
//        about: [{host, title, site_name, description, types}] (a new site's home page, for the machine's call);
//        test: the pre-flight's full-size batch: checked and answered, never stored. The check-in answers "test": true once
//        when System Configuration → Pre-flight asked for one (Preflight::takeTestAsk); W0 needn't be on.
//        Every record is keyed, so a batch sent again changes nothing. The checksum is sha1 over the sorted sha1 of each
//        record's address as sent, of those stored: the worker compares it to what it sent and resends what's missing.
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../../app/lib/crawler/Frontier.php';
require_once __DIR__ . '/../../../app/lib/Staging.php';

if (!Frontier::ready($pdo)) Response::error('The frontier\'s database update hasn\'t run yet (app/migrations/2027-03-12_w0_frontier.sql).', 503, 'not_configured');
Frontier::ensureWorkflow($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Frontier::beat($pdo, ['secs' => (int) ($_GET['secs'] ?? 0), 'v' => (string) ($_GET['v'] ?? ''), 'queue' => (int) ($_GET['queue'] ?? 0),
                          'mismatches' => (int) ($_GET['mismatches'] ?? 0), 'batch' => (int) ($_GET['batch'] ?? 0), 'gap' => (float) ($_GET['gap'] ?? 0), 'note' => (string) ($_GET['note'] ?? '')]);
    $set = Frontier::settings($pdo);
    // the pre-flight's full-size test batch (0164 §1.4): asked once from System Configuration → Pre-flight, sent even while W0 sleeps
    $test = false; try { require_once __DIR__ . '/../../../app/lib/Preflight.php'; $test = Preflight::takeTestAsk($pdo); } catch (Throwable $x) {}
    if (($why = Frontier::off($pdo)) !== null) Response::json(['ok' => true, 'on' => false, 'why' => $why, 'settings' => $set, 'sleep' => 60, 'test' => $test]);
    require_once __DIR__ . '/../../../app/lib/Automation.php';
    $n = max(0, min(100, (int) ($_GET['n'] ?? 25)));
    Response::json(['ok' => true, 'on' => true, 'why' => '', 'settings' => $set, 'work' => Frontier::work($pdo, $n),
                    'describe' => array_map(fn ($r) => ['host' => $r[0], 'home_url' => $r[1]], Sources::toDescribe($pdo, 5)),
                    'holds' => (object) Automation::pausedHosts($pdo), 'sleep' => 30, 'test' => $test]);
}
Response::requireMethod('POST');
if (Staging::here()) Response::error('Staging is a safe copy: the indexer writes to the live site only.', 423, 'staging');
$t0 = microtime(true);
$bytes = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$b = Response::jsonBody();
$kind = (string) ($b['kind'] ?? '');
$key = mb_substr((string) ($b['key'] ?? ''), 0, 80);
$rows = is_array($b['rows'] ?? null) ? $b['rows'] : [];
$declared = max(0, (int) ($b['n'] ?? 0));
if ($key === '' || !in_array($kind, ['found', 'pages', 'about', 'test'], true)) Response::error('A batch needs its key and kind (found, pages, about or test).', 400, 'bad_batch');
if (count($rows) > 1000) Response::error('A batch is 500 records at most.', 413, 'too_many');
try {
    switch ($kind) {
        case 'found': $r = Frontier::found($pdo, $rows); break;
        case 'pages': $r = Frontier::pages($pdo, $rows); break;
        case 'about':   // a new site's home page, read for the machine's call (no AI: the register decides by what it says)
            $keys = [];
            foreach ($rows as $d) {
                if (!is_array($d) || empty($d['host'])) continue;
                Sources::describe($pdo, (string) $d['host'], $d);
                $keys[] = sha1((string) $d['host']);
            }
            Sources::sweep($pdo, 50);
            sort($keys);
            $r = ['received' => count($rows), 'stored' => count($keys), 'rejected' => [], 'checksum' => sha1(implode(',', $keys))];
            break;
        default:   // test: the pre-flight's full-size batch, checked and answered, never stored
            $keys = [];
            foreach ($rows as $x) if (is_array($x) && isset($x['u']) && Frontier::normalize((string) $x['u']) !== null) $keys[] = sha1((string) $x['u']);
            sort($keys);
            $r = ['received' => count($rows), 'stored' => count($keys), 'rejected' => [], 'checksum' => sha1(implode(',', $keys))];
    }
} catch (Throwable $e) {
    error_log('crawl index ' . $kind . ': ' . $e->getMessage());
    Response::error('The batch couldn\'t be stored: ' . $e->getMessage(), 500, 'store_failed');
}
$receipt = Frontier::receipt($pdo, $key, $kind, $declared, $r, $bytes, (int) round((microtime(true) - $t0) * 1000));
// the board's record of the records (W0's "Its data"): one line per batch, its row in crawl_batches and what it did; never its addresses
if ($kind !== 'test' && class_exists('ControlLedger')) try {
    $st = $pdo->prepare('SELECT MAX(id) FROM ' . Database::t('crawl_batches') . ' WHERE batch_key = ?'); $st->execute([sha1($key)]);
    ControlLedger::touched('index batch', (int) $st->fetchColumn(), $kind . ': ' . (int) ($r['stored'] ?? 0) . ' kept, ' . (int) ($r['new'] ?? 0) . ' new' . (count((array) ($r['rejected'] ?? [])) ? ', ' . count((array) $r['rejected']) . ' refused' : ''));
} catch (Throwable $x) {}
Response::json(['ok' => true, 'receipt' => $receipt + ['new' => (int) ($r['new'] ?? 0), 'new_sites' => (int) ($r['new_sites'] ?? 0), 'junk' => (int) ($r['junk'] ?? 0), 'skipped' => (int) ($r['skipped'] ?? 0)]]);
