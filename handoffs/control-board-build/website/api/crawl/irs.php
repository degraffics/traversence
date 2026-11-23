<?php
// api/crawl/irs.php — the worker's monthly IRS exempt-organization load (decisions/0048). Worker token required.
//   GET                                   -> {due, states, last_finished, records}
//   POST {start: "eo-2026-10"}            -> {load_id}
//   POST {load_id, file, rows: [{ein, name, street, city, state, zip, ntee}, …]}  (≤2000)  -> {saved}
//   POST {load_id, file, done: true, seen} -> {removed}

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../../app/lib/crawler/IrsBulk.php';

if (!IrsBulk::ready($pdo)) Response::error('Run app/migrations/2026-10-01_irs_bulk.sql first.', 503, 'not_configured');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') Response::json(['ok' => true] + IrsBulk::status($pdo));
    Response::requireMethod('POST');
    $b = Response::jsonBody();
    if (isset($b['start'])) Response::json(['ok' => true, 'load_id' => IrsBulk::start($pdo, (string) $b['start'])]);
    $file = (string) ($b['file'] ?? '');
    if ($file === '') Response::error('Which file?', 422, 'bad_request');
    if (!empty($b['done'])) Response::json(['ok' => true, 'removed' => IrsBulk::finish($pdo, (int) ($b['load_id'] ?? 0), $file, (int) ($b['seen'] ?? 0))]);
    Response::json(['ok' => true, 'saved' => IrsBulk::batch($pdo, $file, (array) ($b['rows'] ?? []))]);
} catch (DomainException $e) {   // the file changed shape (02 row 38b): nothing written, the load held for a person
    Response::error($e->getMessage(), 422, 'shape');
} catch (Throwable $e) {
    error_log('api/crawl/irs.php: ' . $e->getMessage());
    Response::error('Could not save the IRS batch: ' . mb_substr($e->getMessage(), 0, 200), 500, 'server_error');
}
