<?php
// tools/orders-tests/place-steps.php — ClientOrders::placeSteps() (M4, Place an order, guided). No database, no money:
// placeSteps is pure (it reads arrays), so nothing here touches QuickBooks or the order tables.
// Run from the repo: php tools/orders-tests/place-steps.php   (or set TV_LIB to app/lib's path)
declare(strict_types=1);

$lib = getenv('TV_LIB') ?: __DIR__ . '/../../app/lib';
require_once $lib . '/ClientOrders.php';

$fail = 0;
$t = function (string $what, bool $ok) use (&$fail) { echo ($ok ? 'PASS' : 'FAIL') . ' ' . $what . "\n"; if (!$ok) $fail++; };
$by = fn (array $steps) => array_column($steps, null, 'key');

$base = ['status' => 'draft', 'bill_name' => 'Ana Ruiz', 'bill_email' => 'ana@example.com', 'client_company' => '', 'subtotal_cents' => 12000,
         'delivery' => '', 'pay_mode' => 'full', 'deposit_pct' => 0, 'ship_street' => '', 'ship_city' => '', 'ship_state' => '', 'ship_zip' => ''];
$item = ['product' => 'product', 'status' => 'open', 'title' => 'Banner · 1'];

// a new draft, client only: Items is current
$s = $by(ClientOrders::placeSteps($base, []));
$t('client done with a name and email', $s['client']['done']);
$t('no items: Items is the current step', !$s['items']['done'] && $s['items']['current']);
$t('only one current step', count(array_filter(array_column(ClientOrders::placeSteps($base, []), 'current'))) === 1);

// a cancelled line and a shipping line aren't items
$s = $by(ClientOrders::placeSteps($base, [['product' => 'product', 'status' => 'cancelled'], ['product' => 'shipping', 'status' => 'open']]));
$t('cancelled and shipping lines are not items', !$s['items']['done']);
$t('a shipping line with delivery blank routes to shipped', str_contains($s['where']['note'], 'address it ships to'));

// pickup: no address asked
$s = $by(ClientOrders::placeSteps(['delivery' => 'pickup'] + $base, [$item]));
$t('pickup is done without an address', $s['where']['done'] && str_contains($s['where']['summary'], 'our shop'));
$t('pickup says nothing about several addresses', $s['where']['note'] === '');

// shipped: address needed; several destinations not offered (0177)
$s = $by(ClientOrders::placeSteps(['delivery' => 'ship'] + $base, [$item]));
$t('shipped without an address is not done, and current', !$s['where']['done'] && $s['where']['current']);
$t('shipped says each address goes as its own order', str_contains($s['where']['note'], 'its own order'));
$full = ['delivery' => 'ship', 'ship_street' => '1 Main St', 'ship_city' => 'Show Low', 'ship_state' => 'AZ', 'ship_zip' => '85901'] + $base;
$s = $by(ClientOrders::placeSteps($full, [$item]));
$t('shipped with a full address is done', $s['where']['done'] && str_contains($s['where']['summary'], 'Show Low'));

// terms: invoice later isn't offered; credit terms are coming; deposit says how it's figured
$s = $by(ClientOrders::placeSteps(['pay_mode' => 'invoice', 'delivery' => 'none'] + $base, [$item]));
$t('invoice later is not done', !$s['terms']['done'] && str_contains($s['terms']['note'], 'isn\'t offered'));
$t('credit terms said as coming', str_contains($s['terms']['note'], 'coming'));
$s = $by(ClientOrders::placeSteps(['pay_mode' => 'deposit', 'delivery' => 'none'] + $base, [$item]));
$t('deposit with no % is cost + 15%', str_contains($s['terms']['summary'], 'cost + 15%'));
$s = $by(ClientOrders::placeSteps(['pay_mode' => 'deposit', 'deposit_pct' => 40, 'delivery' => 'none'] + $base, [$item]));
$t('deposit with a % says it', str_contains($s['terms']['summary'], '40%'));

// all filled on a draft: Review and send is current; once sent, nothing is current
$s = $by(ClientOrders::placeSteps(['delivery' => 'none'] + $base, [$item]));
$t('everything filled: Review and send is current', $s['review']['current'] && !$s['review']['done']);
$s = ClientOrders::placeSteps(['delivery' => 'none', 'status' => 'sent', 'sent_at' => '2026-12-01 10:00:00'] + $base, [$item]);
$t('sent: no current step', !in_array(true, array_column($s, 'current'), true));

echo $fail ? "FAIL: $fail\n" : "PASS: all\n";
exit($fail ? 1 : 0);
