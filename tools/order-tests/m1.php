<?php
// tools/order-tests/m1.php <root>: order management M1 (research/order-management-design-2026-11-23.md §3, §6; decisions/0178
// §6) on the design harness's database, after tools/order-tests/m1-seed.php has seeded it (it deletes and remakes the
// orders tables' rows only). <root> holds app/ (the live lib with the release's files over it). Checks, each against its source:
//   §3.1–3.4   every seeded order's three states, its one Next step and its tile, from ClientOrders::boardState() alone
//   §4.1       the board's counts: each tile, and Money owed (what's owed now, any stage) apart from them
//   0178 §6    "Yes, I have it" with money owed: kept, thanked, not completed; Delivered, balance due; the Pulse case's
//              three ways; paid in full then completes it; a paid order still completes on the client's word
//   M1 part 5  the drawer's Complete greyed with what's owed (the adapter's guard), allowed once paid
//   got-it     the reminder skips an order the client already said they have
declare(strict_types=1);
$root = $argv[1];
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/ClientOrders.php';
require_once $root . '/app/lib/Pulse.php';
require_once $root . '/app/admin/Workflow.php';
require_once $root . '/app/admin/Access.php';
$pdo = Database::connection();
$ok = 0; $bad = 0;
$t = function (string $what, bool $pass, $saw = '') use (&$ok, &$bad) { $pass ? $ok++ : $bad++; echo ($pass ? 'PASS ' : 'FAIL ') . $what . ($pass ? '' : "\n     saw: " . (is_string($saw) ? $saw : json_encode($saw))) . "\n"; };
$ids = []; foreach ($pdo->query("SELECT id, client_company FROM orders") as $r) $ids[$r['client_company']] = (int) $r['id'];
$b = fn (string $n) => ClientOrders::boardState($pdo, Orders::get($pdo, $ids[$n]));
// [order, money, delivery, next label, tile]
$want = [
    'Draft Co' => ['draft', 'not_due', 'not_started', 'Finish and send', 'needs'],
    'Sent Co' => ['client', 'not_due', 'not_started', 'Send a reminder', 'client'],
    'Expired Co' => ['expired', 'not_due', 'not_started', 'Send it again', 'needs'],
    'Changes Co' => ['changes', 'not_due', 'not_started', 'Upload the new proof', 'needs'],
    'Deposit Co' => ['approved', 'deposit_due', 'not_started', 'Take a payment', 'needs'],
    'Deposit Paid Co' => ['approved', 'deposit_paid', 'not_started', 'Send the work order', 'needs'],
    'Paid Co' => ['approved', 'paid', 'not_started', 'Send the work order', 'needs'],
    'Vendor Co' => ['production', 'paid', 'vendor', 'Follow the work order', 'production'],
    'Mesa Ridge Lodge' => ['door', 'released', 'ready', 'Collect the balance', 'needs'],
    'Released Co' => ['door', 'released', 'ready', 'Collect the balance', 'needs'],
    'Shipped Paid Co' => ['door', 'paid', 'shipped', 'Complete the order', 'door'],
    'Delivered Owed Co' => ['door', 'released', 'delivered', 'Collect the balance', 'needs'],
    'Complete Co' => ['complete', 'paid', 'delivered', null, 'done'],
    'Cancelled Co' => ['cancelled', 'none', 'none', null, 'done'],
    'Past Due Co' => ['approved', 'balance_due', 'not_started', 'Collect the balance', 'needs'],
];
foreach ($want as $n => $w) {
    $s = $b($n); $got = [$s['order']['key'], $s['money']['key'], $s['delivery']['key'], $s['next']['label'] ?? null, $s['tile']];
    $t("§3 $n: " . implode(' · ', array_map('strval', $w)), $got === $w, $got);
}
$s = $b('Mesa Ridge Lodge');
$t('§3.2 Order #12\'s case: "$809.10 owed", released before payment, never "paid outside"', $s['money']['word'] === '$809.10 owed' && $s['money']['sub'] === 'released before payment' && !preg_match('/paid outside/i', json_encode($s)), $s['money']);
$t('§3.4 waits on someone else: the step says who', $b('Sent Co')['next']['state'] === 'Waiting on the client' && $b('Vendor Co')['next']['state'] === 'With the vendor', [$b('Sent Co')['next'], $b('Vendor Co')['next']]);
$t('§3.4 Delivered, balance due says so', $b('Delivered Owed Co')['next']['state'] === 'Delivered, balance due', $b('Delivered Owed Co')['next']);
$t('§3.2 a deposit order\'s balance isn\'t owed now before it\'s out the door; the deposit is', $b('Deposit Paid Co')['money']['due'] === 0 && $b('Deposit Co')['money']['due'] === 41200, [$b('Deposit Paid Co')['money'], $b('Deposit Co')['money']['due']]);
$t('§3.2 Due on receipt is never "past due"; Net 15 run out is', !$b('Released Co')['money']['past_due'] && $b('Past Due Co')['money']['past_due']);
foreach (['order', 'money'] as $k) foreach ($want as $n => $w) { $x = $b($n)[$k]; if (stripos($x['sentence'], $x['word'] . '.') === 0) $t("words: $n's $k sentence repeats its word", false, $x); }
$ok++;
// the board's counts (tools.php's tv_order_board over the same function)
$tools = require $root . '/app/admin/tools.php';
$noTest = fn () => '';
[$rows, $n] = tv_order_board($pdo, [], 1, $noTest);
$t('§4.1 tiles: Needs us 10, Waiting on the client 1, In production 1, Out the door 1', [$n['needs'], $n['client'], $n['production'], $n['door']] === [10, 1, 1, 1], $n);
$t('§4.1 Money owed: 5 orders, $3,648.40 (the deposit due counts, a deposit order\'s later balance doesn\'t)', $n['owed'] === 5 && $n['owed_cents'] === 4 * 80910 + 41200, $n);
$t('§4.1 Open leaves out complete and cancelled; All shows them', count($rows) === 13 && count(tv_order_board($pdo, ['view' => 'all'], 1, $noTest)[0]) === 15);
$t('§4.1 Mine: only orders I started', count(tv_order_board($pdo, ['view' => 'mine'], 2, $noTest)[0]) === 0);
$list = $tools['backoffice/orders']['list'];
[$r2] = $list($pdo, ['stage' => 'owed'], 1); $t('§4.1 the Money owed tile filters to the 5 that owe now, in any stage', count($r2) === 5 && !array_filter($r2, fn ($r) => !$r['owes']), array_column($r2, 'title'));
[$r3] = $list($pdo, ['stage' => 'client'], 1); $t('§4.1 a tile filters the list; each row keeps the tile as its stage for api.php', count($r3) === 1 && $r3[0]['stage'] === 'client', $r3);
$m = $tools['backoffice/orders']['metrics']($pdo);
$t('§4.1 the metrics: four state tiles then Money owed with its line', array_column($m, 0) === ['Needs us', 'Waiting on the client', 'In production', 'Out the door', 'Money owed'] && $m[4][1] === '$3,648.40' && $m[4][3]['sub'] === '5 orders, in any stage', $m);
// M1 part 5: the drawer greys Complete while money is owed
$mv = fn (int $id) => array_values(array_filter(Workflow::moves($pdo, 'client_order', $id), fn ($x) => $x['key'] === 'to_complete'))[0] ?? null;
$c = $mv($ids['Released Co']); $t('part 5: the drawer\'s Complete greyed with what\'s owed', $c && !$c['allowed'] && str_starts_with($c['why'], '$809.10 still owed'), $c);
$c = $mv($ids['Shipped Paid Co']); $t('part 5: paid, the drawer\'s Complete is open', $c && $c['allowed'], $c);
// 0178 §6
$o = Orders::get($pdo, $ids['Released Co']);
ClientOrders::confirmReceived($pdo, $o, 5, 'Great work', true);
$o = Orders::get($pdo, $ids['Released Co']); $s = ClientOrders::boardState($pdo, $o);
$t('0178 §6: "Yes, I have it" with money owed records it and doesn\'t complete', !empty($o['received_at']) && $o['stage'] === 'ready' && $s['delivery']['key'] === 'delivered' && $s['next']['state'] === 'Delivered, balance due', [$o['stage'], $s]);
$ev = $pdo->query("SELECT event FROM order_events WHERE order_id = {$ids['Released Co']} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$t('0178 §6: the history keeps received and Delivered, balance due', in_array('received', $ev, true) && in_array('delivered_owed', $ev, true), $ev);
try { ClientOrders::confirmReceived($pdo, $o, 0, '', false); $t('0178 §6: a second "Yes" is said back, not recorded twice', false); } catch (RuntimeException $x) { $t('0178 §6: a second "Yes" is said back, not recorded twice', str_contains($x->getMessage(), 'Thank you'), $x->getMessage()); }
$items = []; try { $items = Pulse::items($pdo, null, 1); } catch (Throwable $x) { echo '     (Pulse::items: ' . $x->getMessage() . ")\n"; }
$byKey = fn (array $its, string $k) => array_values(array_filter($its, fn ($i) => ($i['key'] ?? '') === $k))[0] ?? null;
$case = $byKey($items, 'order:' . $ids['Released Co']);
$steps = $case ? array_column(Pulse::playbook($case), 't') : [];
$t('0178 §6: its Pulse case offers the three ways', $case && $case['order']['issue'] === 'delbal' && count(array_filter($steps, fn ($l) => preg_match('/record the payment|collect the balance|complete it with a reason/i', $l))) === 3, [$case['why'] ?? null, $steps]);
$t('got-it: the reminder skips an order the client said they have', (function () use ($pdo, $ids) { $pdo->exec("UPDATE order_events SET created_at = NOW() - INTERVAL 4 DAY WHERE order_id = {$ids['Released Co']} AND event = 'stage'"); ClientOrders::gotItTick($pdo); return !(int) $pdo->query("SELECT COUNT(*) FROM order_events WHERE order_id = {$ids['Released Co']} AND event = 'got_it_reminder'")->fetchColumn(); })());
ClientOrders::recordPayment($pdo, $ids['Released Co'], 80910, 'check', '2231', 1, false);
$o = Orders::get($pdo, $ids['Released Co']);
$t('0178 §6: recording the payment completes it ("then proceed")', $o['stage'] === 'complete' && $o['status'] === 'paid', [$o['stage'], $o['status']]);
$items = []; try { $items = Pulse::items($pdo, null, 1); } catch (Throwable) {}
$t('0178 §6: and its Pulse step is gone', ($byKey($items, 'order:' . $ids['Released Co'])['order']['issue'] ?? '') !== 'delbal', $byKey($items, 'order:' . $ids['Released Co']));
$o = Orders::get($pdo, $ids['Shipped Paid Co']); ClientOrders::confirmReceived($pdo, $o, 0, '', false);
$t('as before: a paid order completes on the client\'s word', Orders::get($pdo, $ids['Shipped Paid Co'])['stage'] === 'complete');
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
