<?php
// website/api/stg-csp.php — staging's Content Security Policy reports (decisions/0165 §1): what a page on staging tried to
// load and the policy blocked, kept in the held ledger (kind csp) so a third-party script that would track or report out
// shows in Held. Staging only; on live it's not there (404). Nothing is sent anywhere.
// f25: only CSP-report content types, only reports about this host's own pages, a global per-minute cap, and every
// stored value reduced to inert URL characters (no < > " ' ` or whitespace) before it reaches the ledger.
declare(strict_types=1);

require_once __DIR__ . '/../../app/lib/Staging.php';
if (!Staging::here() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(404); exit; }
require_once __DIR__ . '/../../app/lib/CspReport.php';
if (!CspReport::typeOk((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''))) { http_response_code(415); exit; }
$j = json_decode((string) file_get_contents('php://input', false, null, 0, 20000), true);
$r = is_array($j) ? (array) ($j['csp-report'] ?? $j) : [];
$doc = (string) ($r['document-uri'] ?? $r['documentURL'] ?? '');
if (!CspReport::hostOk($doc, (string) ($_SERVER['HTTP_HOST'] ?? ''), (string) ($_SERVER['HTTP_ORIGIN'] ?? ''))) { http_response_code(204); exit; }
$blocked = CspReport::cleanUri((string) ($r['blocked-uri'] ?? $r['blockedURL'] ?? ''), 190);
$page = CspReport::cleanPath((string) parse_url($doc, PHP_URL_PATH), 200);
$directive = CspReport::cleanDirective((string) ($r['violated-directive'] ?? $r['effectiveDirective'] ?? ''), 40);
if ($blocked !== '' && CspReport::allow(CspReport::rateFile(), time())) {
    require_once __DIR__ . '/../../app/lib/StagingGate.php';
    StagingGate::record('csp', StagingGate::serviceOf($blocked), $blocked, 'Blocked on ' . $page . ' (' . $directive . ')', '', 'not allowed to load on staging (its Content Security Policy)');
}
http_response_code(204);
