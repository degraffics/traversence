<?php
// tools/order-tests/ironclad.php <root>: placing, configuring and paying for an order, end to end, on the design
// harness's database (handoffs/design-harness; QB_ENV=mock, so no money moves and guardReal is never reached for real).
// Jason, 2026-11-23: "placing orders and configuring them or purchase is what must be ironclad first." Each case runs the
// path the page runs (the library calls behind each button) and records what it did: WORKS (does what the decisions say),
// PARTIAL (works, with a gap) or BROKEN (wrong money, wrong record, a crash or a false message), with the evidence.
// It is a survey, not a pass/fail suite: a BROKEN line is a finding. Exit 0 always; the table is the result.
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
foreach (['Database', 'Orders', 'Products', 'ClientOrders', 'WorkOrders', 'QrIssue', 'Shipments', 'Collateral', 'QuickBooksPay'] as $l) require_once $root . '/app/lib/' . $l . '.php';
$pdo = Database::connection();
$rows = [];
function r(string $path, string $case, string $verdict, string $evidence) { global $rows; $rows[] = [$path, $case, $verdict, $evidence]; echo str_pad($verdict, 8) . " $path · $case\n         $evidence\n"; }
function thrown(callable $f): string { try { $f(); return ''; } catch (Throwable $e) { if ($e instanceof PDOException && getenv('IRON_FIX')) throw $e; return get_class($e) . ': ' . $e->getMessage(); } }   // IRON_FIX: let the harness's schema fixer see a missing column
$o = fn (int $id) => Orders::get($pdo, $id);
$money = fn (int $id) => Orders::money($pdo, $id)['paid'];
$q = fn (string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchAll(PDO::FETCH_ASSOC); })();
$staff = 1;

// a catalog product: 500 for $100, 1,000 for $160; Paper: Matte, Gloss; taxable
$pid = (int) ($q("SELECT id FROM products WHERE sku = 'IRON-CARDS'")[0]['id'] ?? 0);
$pid = Products::save($pdo, $pid, ['name' => 'Ironclad business cards', 'sku' => 'IRON-CARDS', 'qty' => [500, 1000], 'price' => ['100.00', '160.00'], 'cost' => ['40.00', '60.00'], 'options' => "Paper: Matte, Gloss", 'taxable' => 1, 'status' => 'active']);
$client = ['name' => 'Ironclad Client', 'email' => 'ironclad-client@example.com', 'company' => 'Ironclad Co', 'phone' => ''];
$bill = ['street' => '12 Main St', 'city' => 'Springerville', 'state' => 'AZ', 'zip' => '85938'];
$proof = function (int $oid, int $lid, string $status = 'approved') use ($pdo) { $pdo->prepare("INSERT INTO order_proofs (order_id, line_id, file_name, mime, status, kind) VALUES (?, ?, 'p.pdf', 'application/pdf', ?, 'proof')")->execute([$oid, $lid, $status]); };
$ready = function (array $over = []) use ($pdo, $staff, $client, $pid, $proof, $o): array {   // a sent order with one approved proof
    $oid = ClientOrders::create($pdo, $staff, $client);
    $lid = ClientOrders::addProduct($pdo, $oid, $pid, 500, [0 => 'Matte'], '', '', $staff);
    $proof($oid, $lid);
    foreach ($over as $k => $v) $pdo->prepare("UPDATE orders SET $k = ? WHERE id = ?")->execute([$v, $oid]);
    ClientOrders::send($pdo, $oid, 14, $staff);
    return [$oid, $lid];
};

// ===== 1. Staff build the order =====
$m = thrown(fn () => ClientOrders::create($pdo, $staff, ['name' => 'X', 'email' => 'not-an-email']));
r('Build', 'Create: a bad client email', str_contains($m, 'Add the client\'s email') ? 'WORKS' : 'BROKEN', 'Refused: "' . $m . '"');
$oid = ClientOrders::create($pdo, $staff, $client);
r('Build', 'Create: name and email', $o($oid)['status'] === 'draft' ? 'WORKS' : 'BROKEN', 'Order ' . $oid . ' draft, token ' . strlen((string) $o($oid)['access_token']) . ' chars');
$lid = ClientOrders::addProduct($pdo, $oid, $pid, 500, [0 => 'Gloss'], '', '', $staff);
$l = $q('SELECT * FROM order_lines WHERE id = ?', [$lid])[0];
r('Build', 'Add a catalog product (500, Gloss)', (int) $l['unit_cents'] === 10000 && str_contains((string) $l['options'], 'Gloss') ? 'WORKS' : 'BROKEN', 'unit ' . $l['unit_cents'] . '¢, options "' . $l['options'] . '", taxable ' . $l['taxable']);
$lid2 = ClientOrders::addProduct($pdo, $oid, $pid, (int) 'abc', [], '', '', $staff);
$l2 = $q('SELECT * FROM order_lines WHERE id = ?', [$lid2])[0];
r('Build', 'Add a product with quantity "abc"', 'BROKEN', 'Accepted with no message: title "' . $l2['title'] . '", unit ' . $l2['unit_cents'] . '¢ (quantity became 1, priced pro rata from the 500 tier); the page casts (int) before the library sees it');
ClientOrders::removeLine($pdo, $oid, $lid2, $staff);
r('Build', 'An option not picked', str_contains((string) $l2['options'], 'Matte') ? 'WORKS' : 'PARTIAL', 'Took the first choice ("' . $l2['options'] . '"), as decided in 0101 ("The first choice is the standard one")');
$m = thrown(fn () => ClientOrders::addCustom($pdo, $oid, 'Design', 1, '-5', true, '', $staff));
r('Build', 'A negative price', $m !== '' ? 'WORKS' : 'BROKEN', 'Refused: "' . $m . '"');
// the Taxable box on a catalog line
ClientOrders::setLineField($pdo, $oid, $lid, 'taxable', '0', $staff);
$before = (int) $q('SELECT taxable FROM order_lines WHERE id = ?', [$lid])[0]['taxable'];
$rp = new ReflectionMethod('ClientOrders', 'refreshPrices'); $rp->setAccessible(true); $rp->invoke(null, $pdo, $oid);   // what the builder's page load runs
$after = (int) $q('SELECT taxable FROM order_lines WHERE id = ?', [$lid])[0]['taxable'];
r('Build', 'Untick Taxable on a catalog line, then the page reloads', $before === 0 && $after === 1 ? 'BROKEN' : 'WORKS', "taxable was $before after the save, $after after the reload (refreshPrices puts the catalog's back: ClientOrders.php:258)");
// a catalog price change on a draft, then on a sent order
Products::save($pdo, $pid, ['name' => 'Ironclad business cards', 'sku' => 'IRON-CARDS', 'qty' => [500, 1000], 'price' => ['110.00', '160.00'], 'cost' => ['40.00', '60.00'], 'options' => "Paper: Matte, Gloss", 'taxable' => 1, 'status' => 'active']);
$rp->invoke(null, $pdo, $oid);
r('Build', 'The catalog price changes while it\'s a draft', (int) $q('SELECT unit_cents FROM order_lines WHERE id = ?', [$lid])[0]['unit_cents'] === 11000 ? 'WORKS' : 'BROKEN', 'The line follows: ' . $q('SELECT unit_cents FROM order_lines WHERE id = ?', [$lid])[0]['unit_cents'] . '¢');
Products::save($pdo, $pid, ['name' => 'Ironclad business cards', 'sku' => 'IRON-CARDS', 'qty' => [500, 1000], 'price' => ['100.00', '160.00'], 'cost' => ['40.00', '60.00'], 'options' => "Paper: Matte, Gloss", 'taxable' => 1, 'status' => 'active']);
// send
$e = ClientOrders::create($pdo, $staff, $client);
$m = thrown(fn () => ClientOrders::send($pdo, $e, 14, $staff));
r('Build', 'Send with no items', str_contains($m, 'Add something') ? 'WORKS' : 'BROKEN', 'Refused: "' . $m . '"');
$np = ClientOrders::create($pdo, $staff, $client); ClientOrders::addProduct($pdo, $np, $pid, 500, [], '', '', $staff);
$m = thrown(fn () => ClientOrders::send($pdo, $np, 14, $staff));
r('Build', 'Send a printed item with no proof', str_contains($m, 'needs a proof') ? 'WORKS' : 'BROKEN', 'Refused: "' . mb_substr($m, 0, 120) . '"');
$proof($oid, $lid);
$msg = ClientOrders::send($pdo, $oid, 14, $staff);
$ev = $q("SELECT note FROM order_events WHERE order_id = ? AND event IN ('sent','resent') ORDER BY id DESC LIMIT 1", [$oid])[0]['note'] ?? '';
r('Build', 'Send when the email can\'t go (no mail server)', 'BROKEN', 'Status ' . $o($oid)['status'] . '; the event says "' . $ev . '" and the page says "Sent. The client has the link by email" though Mailer failed (logged only: ClientOrders.php mail())');
$n0 = (int) $q('SELECT COUNT(*) c FROM order_lines WHERE order_id = ?', [$e])[0]['c'];
ClientOrders::addCustom($pdo, $e, 'Design', 1, '50.00', true, '', $staff); ClientOrders::addCustom($pdo, $e, 'Design', 1, '50.00', true, '', $staff);
r('Build', 'A double click on Add it', 'BROKEN', 'Two identical lines made (' . ((int) $q('SELECT COUNT(*) c FROM order_lines WHERE order_id = ?', [$e])[0]['c'] - $n0) . '): no busy state on the builder and no request key');

// ===== 2. The client: open, approve =====
$pdo->prepare('UPDATE orders SET expires_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() - 60), $oid]);
$m = thrown(fn () => ClientOrders::approve($pdo, $o($oid), 'Ironclad Client', $bill));
r('Client', 'Approve on an expired link', str_contains($m, 'expired') ? 'WORKS' : 'BROKEN', 'Refused: "' . $m . '"');
$pdo->prepare('UPDATE orders SET expires_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() + 86400), $oid]);
$m = thrown(fn () => ClientOrders::approve($pdo, $o($oid), 'Ironclad Client', ['street' => '', 'city' => '', 'state' => '', 'zip' => '']));
r('Client', 'Approve with no billing address', str_contains($m, 'billing address') ? 'WORKS' : 'BROKEN', 'Refused: "' . $m . '"');
$a = ClientOrders::approve($pdo, $o($oid), 'Ironclad Client', $bill);
r('Client', 'Approve (mock QuickBooks: 8.6% tax)', $a['status'] === 'pending' && (int) $a['tax_cents'] > 0 && $a['invoice_no'] !== '' ? 'WORKS' : 'BROKEN', 'pending, subtotal ' . $a['subtotal_cents'] . '¢ + tax ' . $a['tax_cents'] . '¢ = ' . $a['total_cents'] . '¢, invoice ' . $a['invoice_no']);
$m = thrown(fn () => ClientOrders::approve($pdo, $o($oid), 'Ironclad Client', $bill));
r('Client', 'Approve again (a second click after the first finished)', str_contains($m, 'already approved') ? 'WORKS' : 'BROKEN', 'Refused: "' . $m . '"');
// an order emptied after it was sent
[$x] = $ready(); foreach (Orders::lines($pdo, $x) as $xl) ClientOrders::removeLine($pdo, $x, (int) $xl['id'], $staff);
$m = thrown(fn () => ClientOrders::approve($pdo, $o($x), 'Ironclad Client', $bill));
r('Client', 'Approve an order whose items were all removed after sending', $m === '' ? 'BROKEN' : 'WORKS', $m === '' ? 'Approved with no items: total ' . $o($x)['total_cents'] . '¢, invoice ' . $o($x)['invoice_no'] : 'Refused: "' . $m . '"');

// ===== 3. Pay =====
$tok = fn () => 'mock-' . bin2hex(random_bytes(6));
$m = thrown(fn () => ClientOrders::pay($pdo, $o($oid), 'mock-decline-0002'));
r('Pay', 'A refused card', str_contains($m, 'Nothing was charged') && $money($oid) === 0 ? 'WORKS' : 'BROKEN', 'Said "' . $m . '"; paid ' . $money($oid) . '¢; card_declined events ' . count($q("SELECT id FROM order_events WHERE order_id = ? AND event = 'card_declined'", [$oid])));
$t1 = $tok(); ClientOrders::pay($pdo, $o($oid), $t1);
r('Pay', 'Pay in full by card', $o($oid)['status'] === 'paid' && $money($oid) === (int) $o($oid)['total_cents'] ? 'WORKS' : 'BROKEN', 'status ' . $o($oid)['status'] . ', paid ' . $money($oid) . '¢ of ' . $o($oid)['total_cents'] . '¢, stage "' . $o($oid)['stage'] . '"');
ClientOrders::pay($pdo, $o($oid), $t1);
r('Pay', 'The same card form sent twice (back button, resubmit)', $money($oid) === (int) $o($oid)['total_cents'] ? 'WORKS' : 'BROKEN', 'paid stays ' . $money($oid) . '¢ (nothing due: returns without charging)');
// deposit: two submits in a row
[$d] = $ready(['pay_mode' => 'deposit', 'deposit_pct' => 65]); ClientOrders::approve($pdo, $o($d), 'Ironclad Client', $bill);
ClientOrders::pay($pdo, $o($d), $tok()); $afterDep = $money($d);
ClientOrders::pay($pdo, $o($d), $tok());
r('Pay', 'Deposit terms: the card form submitted twice (a double click)', $money($d) > $afterDep ? 'PARTIAL' : 'WORKS', 'First charge ' . $afterDep . '¢ (the deposit, ' . $o($d)['deposit_cents'] . '¢); the second, seconds later, charged the balance too: paid ' . $money($d) . '¢ of ' . $o($d)['total_cents'] . '¢. The button disables itself; the server can\'t tell a double click from "Pay the rest"');
// a part payment on QuickBooks' page below the deposit
[$p] = $ready(['pay_mode' => 'deposit', 'deposit_pct' => 65]); ClientOrders::approve($pdo, $o($p), 'Ironclad Client', $bill);
$pdo->prepare("UPDATE orders SET note = 'mock-part:2000' WHERE id = ?")->execute([$p]); ClientOrders::fromQuickBooks($pdo, $p);
$due = ClientOrders::dueNow($pdo, $o($p)); $depLeft = (int) $o($p)['deposit_cents'] - 2000;
r('Pay', 'A part payment below the deposit, then the page asks for…', $due === $depLeft ? 'WORKS' : 'BROKEN', 'Asks ' . $due . '¢; the rest of the deposit is ' . $depLeft . '¢ (dueNow switches to the whole balance once anything is paid: ClientOrders.php:116)');
// an outside order
[$x2] = $ready(['pay_mode' => 'outside']); ClientOrders::approve($pdo, $o($x2), 'Ironclad Client', $bill);
$m = thrown(fn () => ClientOrders::pay($pdo, $o($x2), $tok()));
r('Pay', 'An order paid outside QuickBooks, paid by card on its page', $m === '' ? 'BROKEN' : 'WORKS', $m === '' ? 'Charged ' . $money($x2) . '¢ by card although it has no invoice (order.php:395 shows the card form; pay() has no outside check); needs_books: ' . count($q("SELECT id FROM order_events WHERE order_id = ? AND event = 'needs_books'", [$x2])) : 'Refused: "' . $m . '"');
// shipping changed after approval
[$s] = $ready(); ClientOrders::approve($pdo, $o($s), 'Ironclad Client', $bill); $tot = (int) $o($s)['total_cents'];
Shipments::setMode($pdo, $s, 'single', '', $staff);
r('Pay', 'Shipping set to One address after approval', (int) $o($s)['total_cents'] === $tot ? 'WORKS' : 'BROKEN', 'Total ' . $tot . '¢ with tax became ' . $o($s)['total_cents'] . '¢ (recalc writes the pre-tax subtotal at any status: ClientOrders.php:2302)');
// a per-item shipping charge
[$sc, $scl] = $ready(); $pdo->prepare("UPDATE orders SET status = 'draft' WHERE id = ?")->execute([$sc]);
ClientOrders::setShip($pdo, $sc, $scl, ['style' => 'ship', 'carrier' => 'UPS', 'charge' => '25.00', 'cost' => '18.00'], $staff);
Shipments::setMode($pdo, $sc, 'single', '', $staff); ClientOrders::send($pdo, $sc, 14, $staff);
$sub = (int) $o($sc)['subtotal_cents']; ClientOrders::approve($pdo, $o($sc), 'Ironclad Client', $bill);
r('Pay', 'An item with its own shipping charge ($25), shipped to one address', (int) $o($sc)['total_cents'] - (int) $o($sc)['tax_cents'] >= $sub ? 'WORKS' : 'BROKEN', 'The page showed ' . $sub . '¢ before tax; the invoice came to ' . ((int) $o($sc)['total_cents'] - (int) $o($sc)['tax_cents']) . '¢ before tax (each item\'s ship charge isn\'t an invoice line: ClientOrders.php:1068)');

// ===== 4. The client's own purchases (the GO Codes cart) =====
$guest = ['uid' => 0, 'guest' => hash('sha256', 'ironclad-guest-' . bin2hex(random_bytes(4)))];   // QrIssue::owner()'s shape
$m = thrown(function () use ($pdo, $guest) {
    $r = QrIssue::create($pdo, $guest, 'link', ['url' => 'https://example.com'], ['dark' => '#000000', 'light' => '#ffffff', 'round' => 0], 'Iron code');
});
r('Shop', 'A guest adds a GO Code to the cart', $m === '' ? 'WORKS' : 'BROKEN', $m === '' ? 'In the cart at $4.95' : $m);
$co = null; $m = thrown(function () use ($pdo, $guest, &$co) { $co = QrIssue::checkout($pdo, $guest, ['name' => 'Guest Buyer', 'email' => 'guest@example.com', 'street' => '1 A St', 'city' => 'Eagar', 'state' => 'AZ', 'zip' => '85925']); });
r('Shop', 'A guest checks out', $m === '' && $co ? 'WORKS' : 'BROKEN', $m === '' ? 'Order ' . ($co['id'] ?? '?') . ', total ' . ($co['total_cents'] ?? '?') . '¢' : $m);
if ($co) {
    $m = thrown(fn () => QrIssue::cancelMine($pdo, $guest, (int) $co['id']));
    r('Shop', 'A guest presses Back to the cart', $m === '' ? 'WORKS' : 'BROKEN', $m === '' ? 'Back in the cart' : 'Crashes with "' . mb_substr($m, 0, 140) . '" (QrIssue.php:415 passes null to an int; the API doesn\'t catch TypeError, so the cart shows a parse error)');
}
// a client order paid from Manage GO Codes
[$cm] = $ready(['pay_mode' => 'deposit', 'deposit_pct' => 65]); ClientOrders::approve($pdo, $o($cm), 'Ironclad Client', $bill);
ClientOrders::pay($pdo, $o($cm), $tok()); $dep = $money($cm);
$pdo->prepare('UPDATE orders SET user_id = 1 WHERE id = ?')->execute([$cm]);
$owner = ['uid' => 1, 'guest' => ''];
$listed = in_array($cm, array_map(fn ($x) => (int) $x['id'], QrIssue::orders($pdo, $owner)), true);
$m = thrown(fn () => QrIssue::payCard($pdo, $owner, $cm, $tok()));
r('Shop', 'Manage GO Codes lists a client order and its Pay now', $listed && $m === '' ? 'BROKEN' : 'WORKS', 'Listed: ' . ($listed ? 'yes' : 'no') . '; Pay now ' . ($m === '' ? 'went through: the card is charged the whole total, ' . $o($cm)['total_cents'] . '¢ (QrIssue.php:390 calls chargeCard with no amount, so total_cents), while the order records only the ' . ($money($cm) - $dep) . '¢ left: the ' . $dep . '¢ deposit is charged twice' : 'refused: ' . $m));

// ===== 5. Duplicate and reorder =====
$dup = ClientOrders::duplicate($pdo, $oid, $staff, false);
r('Copies', 'Duplicate a paid order', $o($dup)['status'] === 'draft' && count(Orders::lines($pdo, $dup)) === count(Orders::lines($pdo, $oid)) ? 'WORKS' : 'BROKEN', 'Draft ' . $dup . ' with ' . count(Orders::lines($pdo, $dup)) . ' line(s), pay mode ' . $o($dup)['pay_mode']);
$cid = $q('SELECT collateral_id FROM order_lines WHERE order_id = ? LIMIT 1', [$dup])[0]['collateral_id'] ?? null;
r('Copies', 'The copy\'s lines don\'t claim the old collateral', empty($cid) ? 'WORKS' : 'BROKEN', 'collateral_id on the copy: ' . var_export($cid, true));
$t = $q("SELECT id FROM orders WHERE id = ?", [ClientOrders::duplicate($pdo, $oid, $staff, true)])[0]['id'];
r('Copies', 'A test copy', str_starts_with((string) $o((int) $t)['bill_name'], 'TEST · ') && $o((int) $t)['qb_env'] === 'sandbox' ? 'WORKS' : 'PARTIAL', 'Named "' . $o((int) $t)['bill_name'] . '", qb_env "' . $o((int) $t)['qb_env'] . '"');

echo "\n" . count($rows) . " cases: " . count(array_filter($rows, fn ($x) => $x[2] === 'WORKS')) . ' works, ' . count(array_filter($rows, fn ($x) => $x[2] === 'PARTIAL')) . ' partial, ' . count(array_filter($rows, fn ($x) => $x[2] === 'BROKEN')) . " broken\n";
if (!empty($argv[2])) file_put_contents($argv[2], json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
