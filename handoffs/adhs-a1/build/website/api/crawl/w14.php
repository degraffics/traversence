<?php
// api/crawl/w14.php — W14, an authority source's terms read by the worker (decisions/0164 §7.1 steps 2 and 3; Jason through
// the CEO, 2026-11-28, for Arizona's licensed health facilities; HubOpening). Worker token.
//   GET                          -> {paused, why} or {sources: [{id, url, terms_url}]}   (at most 3, one service at a time)
//   POST {read: [{id, robots, crawl_delay, page_status, page_why, terms_status, terms_why, terms_text, lists}]} -> {kept}
// The worker reads robots.txt, the terms page and the source's own page once, politely; nothing from the source's records
// is kept. A person reads the words on the source's Pulse step (Hubs) and decides. Shipped off: "paused" until the master,
// W14, the source's service and the task are on.
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../../app/lib/HubOpening.php';

if (!HubOpening::ready($pdo)) Response::error('The hubs\' database update hasn\'t run yet.', 503, 'not_configured');
try {
    HubOpening::ensureWorkflow($pdo);
    $why = null;
    foreach (HubOpening::READER_SERVICES as $svc) { $why = HubOpening::termsOff($pdo, $svc); if ($why === null) break; }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($why !== null) Response::json(['ok' => true, 'paused' => true, 'why' => $why, 'sources' => []]);
        Response::json(['ok' => true, 'paused' => false, 'sources' => HubOpening::termsDue($pdo)]);
    }
    Response::requireMethod('POST');
    if ($why !== null) Response::json(['ok' => true, 'paused' => true, 'why' => $why]);
    $b = Response::jsonBody();
    Response::json(['ok' => true, 'kept' => HubOpening::termsRead($pdo, (array) ($b['read'] ?? []))]);
} catch (Throwable $e) {
    error_log('api/crawl/w14.php: ' . $e->getMessage());
    Response::error('Could not take the terms read: ' . mb_substr($e->getMessage(), 0, 200), 500, 'server_error');
}
