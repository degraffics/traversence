<?php
// website/api/wire-card.php — our wire details as a picture, for the client's order page only (decisions/0101,
// addendum 2026-10-28): for the client with the order's link, after they've entered the code we emailed them, for
// ClientOrders::WIRE_MINUTES. Never cached, never a download; anything else is a 404.
// f10: "after they've entered the code" is now tied to this browser through a session marker (WireCardGate).
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/ClientOrders.php';
require_once __DIR__ . '/../../app/lib/WireCardGate.php';

$tok = (string) ($_GET['t'] ?? '');
$pdo = Database::connection();
$o = Products::ready($pdo) ? ClientOrders::byToken($pdo, $tok) : null;
$png = $o && $o['status'] === 'pending' && ClientOrders::wireLeft($o) > 0 && ($o['pay_method'] ?? '') === 'wire' && WireCardGate::allowed($tok) ? ClientOrders::wireCard($pdo, $o) : null;
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if ($png === null) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Not here.'; exit; }
header('Content-Type: image/png');
header('Content-Disposition: inline; filename="order"');
header('Content-Length: ' . strlen($png));
echo $png;
