<?php
// api/crawl/loads.php — the worker's open-dataset loads (decisions/0164 §1.5; 0163 §8 stage 8; OpenLoads). Worker token.
//   GET ?dataset=overture&release=2026-10-21.0   -> {paused, why} or {towns: [{key, name, state, zip, box}]}
//   POST {dataset, release, key, batch, rows: […]} -> {seen, staged, listed, closed, skipped[, again]}   (≤2000 rows; batch: its
//                                                    number in the town, so a resend does nothing twice)
//   POST {dataset, release, key, done: true}      -> {ok}
//   POST {geofabrik_probe: [{state, ok, status, bytes, why}]}  -> {ok}
//   GET ?dataset=geofabrik                                   -> {paused, why} or {states: [{key, name, release}]} (one a week each, at most)
//   POST {dataset: geofabrik, release, key: state, osm: [{type, id, lat, lon, tags}]}  -> {seen, kept}  (≤2000; OpenStreetMap's own table)
//   POST {dataset: geofabrik, release, key: state, done: true} -> {ok}
// Shipped off: every answer is "paused" until the master, W1, the dataset's service and its task are on.
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../../app/lib/crawler/OpenLoads.php';

if (!OpenLoads::ready($pdo)) Response::error('The open loads\' database update hasn\'t run yet.', 503, 'not_configured');
try {
    OpenLoads::ensureTasks($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $ds = (string) ($_GET['dataset'] ?? '');
        if ($ds === 'geofabrik') {
            if (($w = OpenLoads::off($pdo, 'geofabrik')) !== null) Response::json(['ok' => true, 'paused' => true, 'why' => $w, 'states' => []]);
            Response::json(['ok' => true, 'paused' => false, 'states' => OpenLoads::geofabrikDue($pdo)]);
        }
        if (!isset(OpenLoads::DATASETS[$ds])) Response::error('Which dataset?', 422, 'bad_request');
        if (($w = OpenLoads::off($pdo, $ds)) !== null) Response::json(['ok' => true, 'paused' => true, 'why' => $w, 'towns' => []]);
        require_once __DIR__ . '/../../../app/lib/Thresholds.php';
        $n = max(0, (int) Thresholds::get($pdo, 'load_towns_per_run'));
        Response::json(['ok' => true, 'paused' => false, 'towns' => OpenLoads::due($pdo, $ds, mb_substr((string) ($_GET['release'] ?? ''), 0, 40), $n)]);
    }
    Response::requireMethod('POST');
    $b = Response::jsonBody();
    if (isset($b['geofabrik_probe'])) {
        OpenLoads::probed($pdo, (array) $b['geofabrik_probe']);
        Response::json(['ok' => true]);
    }
    if (($b['dataset'] ?? '') === 'geofabrik') {   // a state's OpenStreetMap extract: OpenStreetMap's own table, never our listings (decisions/0065)
        if (($w = OpenLoads::off($pdo, 'geofabrik')) !== null) Response::json(['ok' => true, 'paused' => true, 'why' => $w]);
        $rel = mb_substr((string) ($b['release'] ?? ''), 0, 40); $st = (string) ($b['key'] ?? '');
        if (!empty($b['done'])) { OpenLoads::finish($pdo, 'geofabrik', $rel, $st); Response::json(['ok' => true]); }
        Response::json(['ok' => true] + OpenLoads::geofabrikBatch($pdo, $rel, $st, (array) ($b['osm'] ?? [])));
    }
    $ds = (string) ($b['dataset'] ?? ''); $rel = mb_substr((string) ($b['release'] ?? ''), 0, 40); $key = mb_substr((string) ($b['key'] ?? ''), 0, 40);
    if (!isset(OpenLoads::DATASETS[$ds]) || $rel === '' || $key === '') Response::error('Which dataset, release and town?', 422, 'bad_request');
    if (($w = OpenLoads::off($pdo, $ds)) !== null) Response::json(['ok' => true, 'paused' => true, 'why' => $w]);
    if (!empty($b['done'])) { OpenLoads::finish($pdo, $ds, $rel, $key); Response::json(['ok' => true]); }
    $no = isset($b['batch']) && is_numeric($b['batch']) ? max(0, (int) $b['batch']) : null;
    $ids = [];
    $res = ['ok' => true] + OpenLoads::batch($pdo, $ds, $rel, $key, (array) ($b['rows'] ?? []), $no, $ids);
    // the reply first, then the recheck on what it touched (0163 §12's "at once", without the worker waiting on it: IR-0001,
    // the session manager's ruling 2026-11-24); a server that can't end the reply early runs it before replying, as before
    if ($ids && (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'))) {
        http_response_code(200); header('Content-Type: application/json'); echo json_encode($res, JSON_UNESCAPED_SLASHES);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else litespeed_finish_request();
        ignore_user_abort(true);
        OpenLoads::recheck($pdo, $ids);
        exit;
    }
    OpenLoads::recheck($pdo, $ids);
    Response::json($res);
} catch (Throwable $e) {
    error_log('api/crawl/loads.php: ' . $e->getMessage());
    Response::error('Could not take the load: ' . mb_substr($e->getMessage(), 0, 200), 500, 'server_error');
}
