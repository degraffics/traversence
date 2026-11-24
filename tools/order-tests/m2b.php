<?php
// tools/order-tests/m2b.php <root>: order management M2b, how it goes (decisions/0101's note, 2026-11-24; mockup
// handoffs/order-m2b/), on the design harness's database (MariaDB tvh). Runs m2b-seed.php first. Each check names its source:
//   leg 1    the Delivery line reads the work order's delivery until the work order is received: at the vendor (with
//            its words), ready at the vendor for us to collect (Order #12's case), on its way to us, our courier out from
//            the vendor, straight to the client
//   rule     where a shipping work order goes: no ship-to to us, the client's address to them, Blind Drop Ship always
//            the client, Will Call and Internal Transfer to us
//   leg 2    once received: Pick Up ready at our shop, Courier waiting for its number, out by courier from our shop
//   lines    the Order line and the next step say the same as the Delivery line (not out the door while it comes to us)
//   change   Change how it goes…: the work order's delivery (✎, never Undo, the old value in both histories, refused once
//            received); the vendor told only when asked; nothing changed is nothing saved
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
ob_start(); passthru('php ' . escapeshellarg(__DIR__ . '/m2b-seed.php') . ' ' . escapeshellarg($root)); ob_end_clean();
require_once $root . '/app/lib/Database.php'; require_once $root . '/app/lib/Orders.php'; require_once $root . '/app/lib/ClientOrders.php'; require_once $root . '/app/lib/WorkOrders.php';
$pdo = Database::connection();
$ok = 0; $bad = 0;
$t = function (string $what, bool $pass, $saw = '') use (&$ok, &$bad) { $pass ? $ok++ : $bad++; echo ($pass ? 'PASS ' : 'FAIL ') . $what . ($pass ? '' : "\n     saw: " . (is_string($saw) ? $saw : json_encode($saw))) . "\n"; };
$throws = function (callable $f) { try { $f(); return ''; } catch (Throwable $e) { return $e->getMessage(); } };
$ids = []; foreach ($pdo->query('SELECT id, client_company FROM orders') as $r) $ids[$r['client_company']] = (int) $r['id'];
$bs = fn (string $n) => ClientOrders::boardState($pdo, Orders::get($pdo, $ids[$n]));
$line = fn (array $b) => $b['delivery']['word'] . $b['delivery']['sentence'];
$wo = fn (string $n) => WorkOrders::forOrder($pdo, $ids[$n])[0];
$md = fn (int $days) => date('M j', time() + $days * 86400);

// ---- rule ----
$t('rule: Blind Drop Ship goes to the client, even with no ship-to', WorkOrders::goesTo(['delivery' => 'blind_drop', 'ship_to' => '']) === 'client');
$t('rule: Will Call and Internal Transfer come to us, even with an address', WorkOrders::goesTo(['delivery' => 'will_call', 'ship_to' => 'x']) === 'us' && WorkOrders::goesTo(['delivery' => 'internal', 'ship_to' => 'x']) === 'us');
$t('rule: Ship UPS with no ship-to comes to us; with the client\'s address it goes to them', WorkOrders::goesTo(['delivery' => 'ups', 'ship_to' => '']) === 'us' && WorkOrders::goesTo(['delivery' => 'ups', 'ship_to' => "Ana\n12 Main"]) === 'client');
$t('rule: Freight and Local Delivery read the same way', WorkOrders::goesTo(['delivery' => 'freight', 'ship_to' => 'x']) === 'client' && WorkOrders::goesTo(['delivery' => 'local', 'ship_to' => ' ']) === 'us');
$t('rule: every delivery has its one-line meaning', !array_diff_key(WorkOrders::DELIVERY, WorkOrders::DELIVERY_MEANS));

// ---- leg 1 ----
$b = $bs('Leg Make Co');
$t('leg 1: in production says where, with the leg\'s words', $line($b) === 'At the vendor: work order 20261027-CP' . str_pad((string) $ids['Leg Make Co'], 4, '0', STR_PAD_LEFT) . ' at Competition Printing, in production.' && $b['delivery']['leg'] === 'Will Call: we collect it.', [$line($b), $b['delivery']['leg']]);
$b = $bs('Leg Ready Co');
$t('leg 1: a Will Call ready at the vendor is ours to collect, never "Ready for pickup at our shop" (Order #12)', $b['delivery']['key'] === 'collect' && $line($b) === 'Ready at Competition Printing for us to collect, since ' . $md(-22) . ' (22 days).', $line($b));
$t('leg 1: its leg names the method and the work order', str_starts_with($b['delivery']['leg'], 'Will Call · work order 20261027-CP'), $b['delivery']['leg']);
$t('lines: the Order line isn\'t "Out the door" while it waits at the vendor', $b['order']['key'] === 'production' && $b['order']['word'] === 'Ready at the vendor', $b['order']);
$t('lines: its work order id rides with the line, for the Receive button', $b['delivery']['wo'] === (int) $wo('Leg Ready Co')['id']);
$b = $bs('Leg Courier Co');
$t('leg 1: a courier we book on the Will Call: out by courier from the vendor, its number and when it was booked', $b['delivery']['key'] === 'shipped' && $line($b) === 'Out by courier from Competition Printing: Rapid Couriers · 55821, ' . $md(-1) . '.', $line($b));
$t('lines: then it is out the door, not a pickup', $b['order']['key'] === 'door' && !str_contains($b['order']['sentence'], 'Ready for pickup'), $b['order']);
$b = $bs('Leg ToUs Co');
$t('leg 1: Ship UPS with no ship-to is on its way to us, with its tracking', $b['delivery']['key'] === 'to_us' && $line($b) === 'On its way to us from Competition Printing: UPS · 1Z999AA10123456784, ' . $md(-2) . '.', $line($b));
$t('lines: on its way to us is still in production', $b['order']['key'] === 'production', $b['order']);
$b = $bs('Leg Blind Co');
$t('leg 1: Blind Drop Ship shipping goes to the client: no leg 2', $line($b) === 'Shipping from the vendor to the client: FedEx · 7712 3456 7890, ' . $md(-1) . '.' && str_starts_with($b['delivery']['leg'], 'Blind Drop Ship · work order'), [$line($b), $b['delivery']['leg']]);
$b = $bs('Leg Direct Co');
$t('leg 1: Ship UPS to the client\'s ship-to goes straight to them', $b['delivery']['key'] === 'shipped' && str_starts_with($line($b), 'Shipping from the vendor to the client: UPS · 1Z55'), $line($b));

// ---- leg 2 ----
$b = $bs('Leg Pick Co');
$t('leg 2: received, the item is Pick Up: ready for pickup at our shop, received from the vendor', $b['delivery']['key'] === 'ready' && $line($b) === 'Ready for pickup at our shop. Received from Competition Printing ' . $md(-2) . '.', $line($b));
$b = $bs('Leg Send Co');
$t('leg 2: received, the item goes by Courier with no number: at our shop, waiting for its number', $b['delivery']['key'] === 'received' && $line($b) === 'Received from Competition Printing ' . $md(-1) . '; it goes by courier from our shop.' && $b['delivery']['leg'] === 'Courier: its number once it\'s booked.', [$line($b), $b['delivery']['leg']]);
$t('lines: at our shop is not out the door; the step is Add the courier\'s number, ours', $b['order']['word'] === 'At our shop' && $b['delivery']['line'] > 0 && in_array($b['next']['label'] ?? '', ['Add the courier\'s number', 'Record the vendor bill'], true), [$b['order'], $b['next']]);
$b = $bs('Leg Out Co');
$t('leg 2: the item\'s courier booked and shipped: out by courier from our shop', $b['delivery']['key'] === 'shipped' && str_starts_with($line($b), 'Out by courier from our shop: Rapid Couriers 55822'), $line($b));
$t('leg 2: no work order: the item statuses read as before', ClientOrders::legs($pdo, Orders::get($pdo, $ids['Leg Out Co']), [], [], []) === null);

// ---- change ----
$w = $wo('Leg Make Co'); $logN = fn ($w) => count(WorkOrders::log($pdo, (int) $w['id']));
$n0 = $logN($w);
$w2 = WorkOrders::setDelivery($pdo, $w, 'will_call', true, 1);
$t('change: the same method is nothing saved (no History line, no email)', $logN($w) === $n0 && empty($w2['_told']));
$w2 = WorkOrders::setDelivery($pdo, $w, 'blind_drop', false, 1);
$t('change: the work order now goes Blind Drop Ship', $w2['delivery'] === 'blind_drop');
$lg = WorkOrders::log($pdo, (int) $w['id']); $last = end($lg)['note'] ?? '';
$t('change: its History keeps the old value', $last === 'Delivery changed from Will Call to Blind Drop Ship', $last);
$ev = $pdo->query('SELECT note FROM order_events WHERE order_id = ' . $ids['Leg Make Co'] . " AND event = 'work_order_delivery' ORDER BY id DESC LIMIT 1")->fetchColumn();
$t('change: the order\'s History too', $ev === 'Work order ' . $w['wo_no'] . ': delivery changed from Will Call to Blind Drop Ship', $ev);
$t('change: not asked to tell the vendor: not told', empty($w2['_told']));
$b = $bs('Leg Make Co');
$t('change: the Delivery line follows at once (straight to the client)', $b['delivery']['leg'] === 'Blind Drop Ship: straight to the client.', $b['delivery']['leg']);
$t('change: a method not on the list is refused', str_contains($throws(fn () => WorkOrders::setDelivery($pdo, $w2, 'teleport', false, 1)), 'Pick how it goes'));
$t('change: a received work order\'s delivery is done', str_contains($throws(fn () => WorkOrders::setDelivery($pdo, $wo('Leg Pick Co'), 'ups', false, 1)), 'how it went is done'));
$w3 = WorkOrders::setDelivery($pdo, $w2, 'will_call', true, 1);   // the harness has no mail server: asked, but not sent, so not said
$t('change: asked to tell the vendor with no way to send: not claimed as told', empty($w3['_told']));
$t('change: back to Will Call is another ✎, not an Undo (History keeps both)', count(array_filter(WorkOrders::log($pdo, (int) $w['id']), fn ($g) => str_starts_with((string) $g['note'], 'Delivery changed'))) === 2);

echo "\n$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
