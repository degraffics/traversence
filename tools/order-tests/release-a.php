<?php
// tools/order-tests/release-a.php <root>: the ordering release (Release A, 2026-11-23) proved on the design harness's
// database (QB_ENV=mock: no money moves). What ironclad.php's survey doesn't cover: the deposit from cost + 15%
// (decisions/0177 §3), the order taker's tax choice (§2), the Ship to on the invoice, the QuickBooks pull's tax
// exemption and Leave it (Jason's screenshot, "504 Interstate Commerce"), the invoice voided on a cancel (#18), the guest's
// Back to the cart (#11), "no answer" from the card processor (#9), approval's words (#20) and the Orders list's
// Balance due, Next step and Open orders. Each check prints PASS or FAIL with what it saw; exit 1 on any FAIL.
declare(strict_types=1);
$root = $argv[1];
putenv('QB_ENV=mock');
foreach (['Database', 'Orders', 'Products', 'ClientOrders', 'QrIssue', 'QbPull', 'Books', 'QuickBooksPay'] as $l) require_once $root . '/app/lib/' . $l . '.php';
$pdo = Database::connection();
$fails = 0; $n = 0;
function ok(string $what, bool $pass, string $saw) { global $fails, $n; $n++; if (!$pass) $fails++; echo ($pass ? 'PASS ' : 'FAIL ') . "$what\n     $saw\n"; }
function thrown(callable $f): string { try { $f(); return ''; } catch (Throwable $e) { return $e->getMessage(); } }
$q = function (string $sql, array $a = []) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchAll(PDO::FETCH_ASSOC); };
$o = fn (int $id) => Orders::get($pdo, $id);
$staff = 1;
$pid = (int) ($q("SELECT id FROM products WHERE sku = 'IRON-CARDS'")[0]['id'] ?? 0);
$pid = Products::save($pdo, $pid, ['name' => 'Ironclad business cards', 'sku' => 'IRON-CARDS', 'qty' => [500, 1000], 'price' => ['100.00', '160.00'], 'cost' => ['40.00', '60.00'], 'options' => "Paper: Matte, Gloss", 'taxable' => 1, 'status' => 'active']);
$client = ['name' => 'Release A Client', 'email' => 'release-a@example.com', 'company' => 'Release A Co', 'phone' => ''];
$bill = ['street' => '12 Main St', 'city' => 'Springerville', 'state' => 'AZ', 'zip' => '85938'];
$proof = function (int $oid, int $lid) use ($pdo) { $pdo->prepare("INSERT INTO order_proofs (order_id, line_id, file_name, mime, status, kind) VALUES (?, ?, 'p.pdf', 'application/pdf', 'approved', 'proof')")->execute([$oid, $lid]); };
$sent = function (array $over = [], bool $custom = false) use ($pdo, $staff, $client, $pid, $proof): int {
    $oid = ClientOrders::create($pdo, $staff, $client);
    $lid = ClientOrders::addProduct($pdo, $oid, $pid, 500, [0 => 'Matte'], '', '', $staff);
    $proof($oid, $lid);
    if ($custom) ClientOrders::addCustom($pdo, $oid, 'Design time', 1, '50.00', true, '', $staff);   // no cost recorded
    foreach ($over as $k => $v) $pdo->prepare("UPDATE orders SET $k = ? WHERE id = ?")->execute([$v, $oid]);
    ClientOrders::send($pdo, $oid, 14, $staff);
    return $oid;
};

// ---- the deposit: cost + 15% (decisions/0177 §3)
$d = $sent(['pay_mode' => 'deposit', 'deposit_pct' => 0]);
$line = $q('SELECT cost_cents, unit_cents, qty FROM order_lines WHERE order_id = ? LIMIT 1', [$d])[0];
[$dep, $words] = ClientOrders::depositFor($pdo, $o($d), 100000);
ok('Deposit from the item\'s cost plus 15%', $dep === (int) round((int) $line['cost_cents'] * 1.15), "cost {$line['cost_cents']}¢ → deposit {$dep}¢ ($words)");
$d2 = $sent(['pay_mode' => 'deposit', 'deposit_pct' => 0], true);
[$dep2, $words2, $open2] = ClientOrders::depositFor($pdo, $o($d2), 100000);
ok('An item with no cost recorded counts at 65% of its price', $dep2 === (int) round((int) $line['cost_cents'] * 1.15) + 3250 && $open2 === ['Design time'], "deposit {$dep2}¢; no cost: " . implode(', ', $open2) . " ($words2)");
$d3 = $sent(['pay_mode' => 'deposit', 'deposit_pct' => 40]);
ok('Staff\'s % overrides', ClientOrders::depositFor($pdo, $o($d3), 20000)[0] === 8000, ClientOrders::depositFor($pdo, $o($d3), 20000)[1]);
ok('Never above the order', ClientOrders::depositFor($pdo, $o($d), 1000)[0] === 1000, 'capped at 1000¢');
$a = ClientOrders::approve($pdo, $o($d), 'Release A Client', $bill);
ok('Approval sets the deposit from cost', (int) $a['deposit_cents'] === (int) round((int) $line['cost_cents'] * 1.15), "deposit_cents {$a['deposit_cents']} of total {$a['total_cents']}");
ok('The client\'s first payment is the deposit', ClientOrders::dueNow($pdo, $a) === (int) $a['deposit_cents'], 'due now ' . ClientOrders::dueNow($pdo, $a) . '¢');
$m = thrown(fn () => ClientOrders::saveTerms($pdo, $sent(), ['name' => 'X', 'email' => 'x@example.com', 'pay_mode' => 'deposit', 'deposit_pct' => 0], $staff));
ok('Deposit with no % is allowed (figured from cost)', $m === '' || !str_contains($m, '%'), $m === '' ? 'saved' : $m);

// ---- the tax choice (decisions/0177 §2)
$t = $sent(['tax_choice' => 'reseller', 'tax_note' => 'Resale cert 12345']);
$at = ClientOrders::approve($pdo, $o($t), 'Release A Client', $bill);
ok('A reseller order is invoiced with no tax', (int) $at['tax_cents'] === 0 && (int) $at['total_cents'] === (int) $at['subtotal_cents'], "tax {$at['tax_cents']}¢, total {$at['total_cents']}¢");
$tx = $sent();
$ax = ClientOrders::approve($pdo, $o($tx), 'Release A Client', $bill);
ok('A taxable order is taxed', (int) $ax['tax_cents'] > 0, "tax {$ax['tax_cents']}¢ (mock 8.6%)");
$m = thrown(fn () => ClientOrders::setField($pdo, $tx, 'tax_choice', 'exempt', $staff));
ok('The tax can\'t change after approval', str_contains($m, 'Reopen'), $m);
$m = thrown(fn () => ClientOrders::setField($pdo, $sent(), 'tax_choice', 'whatever', $staff));
ok('Only a listed tax choice', str_contains($m, 'Choose how'), $m);

// ---- approval: no items, and the words when QuickBooks can't invoice (#12, #20, #7)
$e = $sent(); foreach (Orders::lines($pdo, $e) as $l) ClientOrders::removeLine($pdo, $e, (int) $l['id'], $staff);
$m = thrown(fn () => ClientOrders::approve($pdo, $o($e), 'Release A Client', $bill));
ok('An emptied order isn\'t approved', str_contains($m, 'no items'), $m);
ok('Mock QuickBooks can invoice', ClientOrders::cantInvoice($pdo, $o($tx)) === '', 'cantInvoice: ""');

// ---- several addresses: refused at approval until one invoice per address is built (decisions/0177)
$s1 = $sent(['ship_name' => 'Dana', 'ship_street' => '12 Main St', 'ship_city' => 'Springerville', 'ship_state' => 'AZ', 'ship_zip' => '85938', 'delivery' => 'ship']);
$ls = Orders::lines($pdo, $s1);
$pdo->prepare('UPDATE order_lines SET ship_address = ? WHERE id = ?')->execute(["Dana Ruiz\n12 Main Street\nSpringerville, AZ 85938", (int) $ls[0]['id']]);
ok('One place written two ways is one address', count(ClientOrders::shipTos($pdo, $o($s1), Orders::lines($pdo, $s1))) === 1, json_encode(ClientOrders::shipTos($pdo, $o($s1), Orders::lines($pdo, $s1))));
ClientOrders::addCustom($pdo, $s1, 'Banner', 1, '40.00', true, '', $staff);
$ls = Orders::lines($pdo, $s1);
$pdo->prepare('UPDATE order_lines SET ship_address = ? WHERE id = ?')->execute(["Mesa office\n55 Center St\nMesa, AZ 85201", (int) end($ls)['id']]);
$m = thrown(fn () => ClientOrders::approve($pdo, $o($s1), 'Release A Client', $bill + ['ship_same' => 1], $staff));
ok('Two addresses: approval refused with what to do', str_contains($m, 'ships to 2 addresses') && str_contains($m, 'Split into one order per address') && $o($s1)['status'] === 'sent', $m);

// ---- the way forward: Split into one order per address, and Undo (the session manager's condition, 2026-11-23)
$before = array_map(fn ($l) => (int) $l['id'], Orders::lines($pdo, $s1));
$kids = ClientOrders::split($pdo, $s1, $staff);
$kid = $kids[0] ?? 0;
$left = array_map(fn ($l) => (int) $l['id'], Orders::lines($pdo, $s1)); $moved = array_map(fn ($l) => (int) $l['id'], Orders::lines($pdo, $kid));
ok('Split makes one order per address, the items moved', count($kids) === 1 && count($left) + count($moved) === count($before) && count(ClientOrders::shipTos($pdo, $o($s1), Orders::lines($pdo, $s1))) === 1 && count(ClientOrders::shipTos($pdo, $o($kid), Orders::lines($pdo, $kid))) === 1, 'kept ' . count($left) . ', moved ' . count($moved) . ' to order ' . $kid);
ok('The new order keeps the client and terms, as a draft', $o($kid)['bill_email'] === $o($s1)['bill_email'] && $o($kid)['tax_choice'] === $o($s1)['tax_choice'] && $o($kid)['status'] === 'draft' && (int) $o($kid)['subtotal_cents'] === 4000, 'status ' . $o($kid)['status'] . ', subtotal ' . $o($kid)['subtotal_cents'] . '¢');
$as1 = ClientOrders::approve($pdo, $o($s1), 'Release A Client', $bill + ['ship_same' => 1], $staff);
ok('After the split the first order is approved', $as1['status'] === 'pending', 'status ' . $as1['status']);
$s2 = $sent(['ship_name' => 'Dana', 'ship_street' => '12 Main St', 'ship_city' => 'Springerville', 'ship_state' => 'AZ', 'ship_zip' => '85938', 'delivery' => 'ship']);
ClientOrders::addCustom($pdo, $s2, 'Banner', 1, '40.00', true, '', $staff);
$ls = Orders::lines($pdo, $s2); $pdo->prepare('UPDATE order_lines SET ship_address = ? WHERE id = ?')->execute(["Mesa office\n55 Center St\nMesa, AZ 85201", (int) end($ls)['id']]);
$n0 = count(Orders::lines($pdo, $s2)); $k2 = ClientOrders::split($pdo, $s2, $staff);
ok('Undo is offered while the new order is untouched', ClientOrders::splitUndoable($pdo, $s2) === $k2, json_encode(ClientOrders::splitUndoable($pdo, $s2)));
ClientOrders::unsplit($pdo, $s2, $staff);
ok('Undo puts the items back and cancels the new order', count(Orders::lines($pdo, $s2)) === $n0 && $o($k2[0])['status'] === 'void' && !ClientOrders::splitUndoable($pdo, $s2), count(Orders::lines($pdo, $s2)) . ' items back; order ' . $k2[0] . ' ' . $o($k2[0])['status']);
$s3 = $sent(['ship_name' => 'Dana', 'ship_street' => '12 Main St', 'ship_city' => 'Springerville', 'ship_state' => 'AZ', 'ship_zip' => '85938', 'delivery' => 'ship']);
ClientOrders::addCustom($pdo, $s3, 'Banner', 1, '40.00', true, '', $staff);
$ls = Orders::lines($pdo, $s3); $pdo->prepare('UPDATE order_lines SET ship_address = ? WHERE id = ?')->execute(["Mesa office\n55 Center St\nMesa, AZ 85201", (int) end($ls)['id']]);
$k3 = ClientOrders::split($pdo, $s3, $staff);
foreach (Orders::lines($pdo, $k3[0]) as $l) ClientOrders::removeLine($pdo, $k3[0], (int) $l['id'], $staff);
$m = thrown(fn () => ClientOrders::unsplit($pdo, $s3, $staff));
ok('Undo with nothing to bring back is refused, nothing changed', str_contains($m, 'Nothing was changed') && $o($k3[0])['status'] !== 'void', $m . ' · the other order ' . $o($k3[0])['status']);
// an item that can't move back (the database refuses that one row): Undo rolls everything back and cancels nothing
$s4 = $sent(['ship_name' => 'Dana', 'ship_street' => '12 Main St', 'ship_city' => 'Springerville', 'ship_state' => 'AZ', 'ship_zip' => '85938', 'delivery' => 'ship']);
ClientOrders::addCustom($pdo, $s4, 'Banner', 1, '40.00', true, '', $staff); ClientOrders::addCustom($pdo, $s4, 'Flag', 1, '30.00', true, '', $staff);
foreach (array_slice(Orders::lines($pdo, $s4), -2) as $l) $pdo->prepare('UPDATE order_lines SET ship_address = ? WHERE id = ?')->execute(["Mesa office\n55 Center St\nMesa, AZ 85201", (int) $l['id']]);
$k4 = ClientOrders::split($pdo, $s4, $staff); $kidLines = array_map(fn ($l) => (int) $l['id'], Orders::lines($pdo, $k4[0])); $parentN = count(Orders::lines($pdo, $s4));
$stuck = end($kidLines);
$pdo->exec('DROP TRIGGER IF EXISTS release_a_stuck');
$pdo->exec("CREATE TRIGGER release_a_stuck BEFORE UPDATE ON order_lines FOR EACH ROW BEGIN IF OLD.id = $stuck AND NEW.order_id <> OLD.order_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'held'; END IF; END");
$m = thrown(fn () => ClientOrders::unsplit($pdo, $s4, $staff));
$pdo->exec('DROP TRIGGER IF EXISTS release_a_stuck');
ok('An item that can\'t move back: Undo changes nothing and says so', str_contains($m, 'Nothing was changed') && $o($k4[0])['status'] === 'draft' && count(Orders::lines($pdo, $k4[0])) === count($kidLines) && count(Orders::lines($pdo, $s4)) === $parentN && ClientOrders::splitUndoable($pdo, $s4) === $k4, $m . ' · the other order ' . $o($k4[0])['status'] . ' with ' . count(Orders::lines($pdo, $k4[0])) . ' of ' . count($kidLines) . ' items; this one ' . count(Orders::lines($pdo, $s4)));
ClientOrders::unsplit($pdo, $s4, $staff);
ok('...and once it can, Undo brings every item back', count(Orders::lines($pdo, $s4)) === $parentN + count($kidLines) && $o($k4[0])['status'] === 'void', count(Orders::lines($pdo, $s4)) . ' items here; the other order ' . $o($k4[0])['status']);
$m = thrown(fn () => ClientOrders::split($pdo, $sent(), $staff));
ok('One address: nothing to split', str_contains($m, 'one address'), $m);

// ---- the QuickBooks pull: an exemption item (Jason's "504 Interstate Commerce"), and Leave it
putenv('QB_ENV=mock');
foreach ($q("SELECT id FROM orders WHERE invoice_no = '112907'") as $r) { $pdo->prepare("UPDATE orders SET invoice_no = NULL, invoice_id = NULL, status = 'void' WHERE id = ?")->execute([(int) $r['id']]); }
require_once $root . '/app/lib/SystemTags.php';
foreach (SystemTags::things($pdo, 'qbo.invoice', Books::env(), '9113') as $t) { $g = SystemTags::get($pdo, 'qbo.invoice', (int) $t, Books::env()); if ($g) SystemTags::remove($pdo, (int) $g['id'], $staff); }   // pulled before: free it to pull again
QbPull::preview($pdo, '112907');   // stages its items
Books::decide($pdo, '4', 'tax', 0, $staff);    // "Shrink Wrapping" stands in for the exemption item here
Books::decide($pdo, '3', 'leave', 0, $staff);  // "Shipping": kept as written
Books::decide($pdo, '2', 'charge', 0, $staff);
$pv = QbPull::preview($pdo, '112907');
$kinds = array_count_values(array_map(fn ($l) => $l['kind'], $pv['lines']));
ok('The pull reads tax and leave items', ($kinds['tax'] ?? 0) === 1 && ($kinds['leave'] ?? 0) === 1, json_encode($kinds));
$po = QbPull::pull($pdo, '112907', ['client' => 'person'], $staff);
$pl = $q("SELECT title, tags, unit_cents FROM order_lines WHERE order_id = ? AND status <> 'cancelled'", [$po]);
ok('An exemption item makes the pulled order exempt, named by it', $o($po)['tax_choice'] === 'exempt' && str_contains((string) $o($po)['tax_note'], 'Shrink Wrapping'), $o($po)['tax_choice'] . ' · ' . $o($po)['tax_note']);
ok('A left item comes in as written, not a charge', (bool) array_filter($pl, fn ($l) => str_contains($l['title'], 'Product will be shipped') || ($l['tags'] === '' && (int) $l['unit_cents'] === 1800)), count($pl) . ' lines');
ok('The pulled total still matches QuickBooks', (int) $o($po)['total_cents'] === 111114, 'total ' . $o($po)['total_cents'] . '¢');
$h = QbPull::itemChoices([1 => 'Business Cards'], '504 Interstate Commerce', 'item[9]');
ok('The pull\'s list: names only, each meaning behind its i, the exemption picked for an "interstate" item', str_contains($h, 'value="tax" selected') && str_contains($h, '>Leave it</option>') && str_contains($h, 'data-ak-info') && str_contains($h, '<small>Like Arizona'), 'tax selected; meanings in the i');
foreach (['4', '3', '2'] as $ref) Books::decide($pdo, $ref, 'clear', 0, $staff);

// ---- a cancel voids the invoice (#18); a guest's Back to the cart (#11)
$c = $sent(); $ac = ClientOrders::approve($pdo, $o($c), 'Release A Client', $bill);
Orders::cancelUnpaid($pdo, $c, '', $staff);
ok('Cancelling an unpaid order voids its invoice', (bool) $q("SELECT 1 FROM order_events WHERE order_id = ? AND event = 'invoice_voided'", [$c]), 'event invoice_voided');
$g = ['uid' => 0, 'guest' => 'guest-release-a'];
$pdo->prepare("INSERT INTO orders (status, guest_key, access_token, items, subtotal_cents, total_cents, bill_name, bill_email) VALUES ('pending', ?, ?, 0, 100, 100, 'Guest', 'g@example.com')")->execute(['guest-release-a', bin2hex(random_bytes(20))]);
$gid = (int) $pdo->lastInsertId();
$m = thrown(fn () => QrIssue::cancelMine($pdo, $g, $gid));
ok('A guest\'s Back to the cart cancels it, no crash', $m === '' && $o($gid)['status'] === 'void', $m === '' ? 'status ' . $o($gid)['status'] : $m);

// ---- "no answer" from the card processor (#9)
ok('No answer is told apart from a decline', Orders::unanswered(new RuntimeException('QuickBooks Payments didn\'t answer, so we can\'t tell yet whether the card was charged.')) && !Orders::unanswered(new RuntimeException('The card was declined. Nothing was charged.')), 'unanswered: timeout yes, decline no');

// ---- the Orders list: Balance due, Next step, open orders (the survey §5)
require_once $root . '/app/admin/Access.php'; require_once $root . '/app/admin/AdminModules.php';
$tools = require $root . '/app/admin/tools.php';
$tool = $tools['backoffice/orders'] ?? null;
if ($tool) {
    [$rows] = ($tool['list'])($pdo, [], $staff);
    $row = array_values(array_filter($rows, fn ($r) => $r['id'] === $d))[0] ?? null;
    ok('The list shows Balance due and a Next step (where it stands, one verb)', $row && $row['balance'] === (int) $o($d)['total_cents'] && $row['next']['label'] === 'Record a payment' && $row['next']['state'] === 'Waiting for payment', $row ? 'balance ' . json_encode($row['balance']) . ', ' . $row['next']['state'] . ' → "' . $row['next']['label'] . '"' : 'row missing');
    $sentRow = array_values(array_filter($rows, fn ($r) => $r['stage'] === 'sent'))[0] ?? null;
    ok('A sent order: "Not due yet" and Send again run in place', $sentRow && $sentRow['balance'] === ['word' => 'Not due yet'] && $sentRow['next']['act'] === 'resend' && $sentRow['next']['label'] === 'Send again', $sentRow ? json_encode($sentRow['balance']) . ', ' . json_encode($sentRow['next']) : 'none');
    $said = ($tool['act'])($pdo, (int) $sentRow['id'], 'resend', [], $staff);   // the harness has no mail server: it must say so, with the link
    ok('Send again: the email that didn\'t go is said as it is, with the link to copy', is_array($said) && str_starts_with($said['message'], 'The email didn\'t go to ') && str_contains((string) $said['copy'], '/order.php?t=') && !empty($said['bad']), json_encode($said));
    $cancelled = array_filter($rows, fn ($r) => in_array($r['stage'], ['cancelled', 'void', 'complete'], true));
    [$all] = ($tool['list'])($pdo, ['show' => 'all'], $staff);
    ok('Open orders by default; every order on asking', !$cancelled && count($all) > count($rows), count($rows) . ' open, ' . count($all) . ' in all');
    ok('Place an order is the page\'s first, labelled button', ($tool['create']['label'] ?? '') === 'Place an order' && !empty($tool['create']['primary']), json_encode($tool['create']));
} else ok('The Orders tool loads', false, 'tools.php has no backoffice/orders');

echo "\n$n checks: " . ($n - $fails) . " pass, $fails fail\n";
exit($fails ? 1 : 0);
