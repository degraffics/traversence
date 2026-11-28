**Skipped first.** Nothing in decisions/0177 is paused or waiting for Jason. §1–§4 are accepted and confirmed, and M5 is queued rather than paused. Two things in the attached documents are paused or waiting for Jason, so I left both alone:

- **M2b ("how an order goes")** is paused by Jason ("Pause for now. add to work queue."). Nothing here builds on or changes that flow.
- **Order #12's books** wait for Jason ("I will address this after holiday"). Nothing here reads or changes that order.

**Review of the draft.** The attached job-19 draft is empty, so there was nothing to review. I wrote this from the live files and the decision.

Points from reviewing the live code against 0177:

- **Deposit when a cost isn't checked.** `ClientOrders::depositFor` treats only a cost of zero as "can't be figured." 0177 §3's body also counts an *unchecked* cost (`Costing`). Jason's confirmed wording says "isn't recorded." I flagged this and did not change it.
- **Deposit caps.** The per-invoice cap ("never above that invoice's total") and a staff % "applied to each invoice's total" don't exist yet. I added both as pure arithmetic in a new library, and `depositFor` now calls the same rule.
- **What isn't wired.** The parts that turn this into real QuickBooks invoices are in files that weren't attached or were truncated: `QuickBooksPay::invoiceFor`, approve, pay, recalc, `releaseAt`, delivery and split. I didn't invent them; they're listed under "verify."
- **Sequencing.** M5 follows M3 and M4 (0177's note, design §6), and queue item 0 ("the order tests green") comes first. That is the gate.

=== FILE: files/app/lib/OrderDestinations.php ===
<?php
// app/lib/OrderDestinations.php — one order, several destinations (decisions/0177 §1, §3, §4; Orders M5, release B).
//
// Pure arithmetic over an order's row and its lines, so it can be tested without a database: no PDO, no QuickBooks,
// no money moves. The callers (ClientOrders) read the rows and write the results.
//
//   destKey   which destination a line goes to: 'a<id>' (one of the order's addresses, order_lines.ship_to), 'order'
//             (the order's own Ship to), 'ours' (pickup, nothing to deliver, a GO Code: our address, QuickBooks' own
//             fallback, 0177 §1)
//   plan      the order's invoices, one per destination, oldest destination first: lines, subtotal, tax (given back by
//             QuickBooks per invoice, passed in), total, deposit
//   deposit   one invoice's deposit (0177 §3): staff's % of that invoice's total when set, else its items' COGS (product
//             and shipping cost) + markup %, an item with no cost at the default % of its price; never above the total.
//             ClientOrders::depositFor calls this, so there is one rule.
//   spread    an estimate's deposit spread over the invoices in proportion to their deposits (0177 §3, last point)
//   allocate  a payment against the invoices: deposits first, then each invoice's balance, oldest destination first (§4)
//   releaseNeed / releaseMet   the vendor release waits on the whole order (§4: "single invoices are tied to the whole
//             order status"): never one invoice on its own
declare(strict_types=1);

final class OrderDestinations
{
    /** A line's price as the deposit reads it (qty × unit + the shipping it charges). The lane checks it against recalc. */
    public static function lineCents(array $l): int
    {
        return max(1, (int) ($l['qty'] ?? 1)) * (int) ($l['unit_cents'] ?? 0) + (int) ($l['ship_cents'] ?? 0);
    }

    /**
     * Where a line goes. An address of its own wins; else a GO Code (digital) is ours; else the order's delivery decides:
     * pickup or nothing to deliver is ours; '' with no shipping line on the order is nothing to deliver (ClientOrders::DELIVERY).
     */
    public static function destKey(array $o, array $l, bool $orderShips): string
    {
        $st = (string) ($l['ship_to'] ?? '');
        if (preg_match('/^\d+$/', $st) && (int) $st > 0) return 'a' . (int) $st;
        if (($l['product'] ?? '') === 'go_code') return 'ours';
        $d = (string) ($o['delivery'] ?? '');
        if ($d === 'pickup' || $d === 'none') return 'ours';
        if ($d === '' && !$orderShips) return 'ours';
        return 'order';
    }

    /** Oldest destination first: the order's own Ship to, then its added addresses by id, our address last. */
    public static function rank(string $key): int
    {
        if ($key === 'order') return 0;
        if ($key === 'ours') return PHP_INT_MAX;
        return (int) substr($key, 1);
    }

    /** Words for our team. */
    public static function label(string $key): string
    {
        if ($key === 'order') return 'The order\'s Ship to';
        if ($key === 'ours') return 'Our address (pickup, delivered by us, or digital)';
        return 'Address ' . (int) substr($key, 1);
    }

    /**
     * One invoice's deposit on $total from its $lines. Returns [cents, words for our team, titles and ids of the items
     * without a recorded cost]. Exactly the rule ClientOrders::depositFor had (release A), now usable per invoice.
     */
    public static function deposit(array $o, int $total, array $lines, int $markup, int $default): array
    {
        $pct = (int) ($o['deposit_pct'] ?? 0);
        if ($pct > 0) return [min($total, (int) round($total * $pct / 100)), $pct . '% of the total, as set', [], []];
        $cost = 0; $guess = 0; $open = []; $openIds = [];
        foreach ($lines as $l) {
            if (($l['status'] ?? '') === 'cancelled') continue;
            $c = max(1, (int) $l['qty']) * (int) ($l['cost_cents'] ?? 0) + (int) ($l['ship_cost_cents'] ?? 0);
            if ($c > 0) { $cost += $c; continue; }
            $price = max(1, (int) $l['qty']) * (int) $l['unit_cents'] + (int) ($l['ship_cents'] ?? 0);
            if ($price <= 0) continue;
            $guess += (int) round($price * $default / 100); $open[] = (string) $l['title']; $openIds[] = (int) $l['id'];
        }
        $dep = min($total, (int) round($cost * (100 + $markup) / 100) + $guess);
        $words = 'items\' cost + ' . $markup . '%' . ($open ? '; ' . count($open) . ' item' . (count($open) === 1 ? '' : 's') . ' at ' . $default . '% of price' : '');
        return [$dep, $words, $open, $openIds];
    }

    /**
     * The order's invoices, one per destination, oldest first. $taxByDest: destKey => tax cents QuickBooks gave back for
     * that invoice (empty before it's asked: totals are then before tax). $markup, $default: ClientOrders::DEPOSIT_MARKUP
     * and DEPOSIT_DEFAULT, passed by the caller so the numbers live in one place.
     */
    public static function plan(array $o, array $lines, array $taxByDest, int $markup, int $default): array
    {
        $ships = false;
        foreach ($lines as $l) if (($l['product'] ?? '') === 'shipping' && ($l['status'] ?? '') !== 'cancelled') $ships = true;
        $groups = [];
        foreach ($lines as $l) {
            if (($l['status'] ?? '') === 'cancelled') continue;
            $groups[self::destKey($o, $l, $ships)][] = $l;
        }
        uksort($groups, fn ($a, $b) => self::rank((string) $a) <=> self::rank((string) $b));
        $out = [];
        foreach ($groups as $k => $ls) {
            $k = (string) $k;
            $sub = 0; foreach ($ls as $l) $sub += self::lineCents($l);
            $tax = max(0, (int) ($taxByDest[$k] ?? 0));
            $total = $sub + $tax;
            [$dep, $words, $open, $openIds] = self::deposit($o, $total, $ls, $markup, $default);
            $out[] = ['key' => $k, 'label' => self::label($k), 'lines' => array_map(fn ($l) => (int) $l['id'], $ls),
                      'subtotal' => $sub, 'tax' => $tax, 'total' => $total, 'deposit' => $dep, 'deposit_words' => $words,
                      'open' => $open, 'open_ids' => $openIds];
        }
        return $out;
    }

    /** The order's deposit: the sum of its invoices' deposits (0177 §3). */
    public static function orderDeposit(array $plan): int
    {
        $n = 0; foreach ($plan as $i) $n += (int) $i['deposit'];
        return $n;
    }

    /** The order's total across its invoices. */
    public static function orderTotal(array $plan): int
    {
        $n = 0; foreach ($plan as $i) $n += (int) $i['total'];
        return $n;
    }

    /**
     * $amount spread over $weights (key => weight) in proportion, whole cents, summing exactly to $amount (largest
     * remainder; ties go to the earlier key, the older destination). All weights 0: all of it on the first key.
     */
    public static function spread(int $amount, array $weights): array
    {
        $out = array_fill_keys(array_keys($weights), 0);
        if ($amount <= 0 || !$out) return $out;
        $sum = 0; foreach ($weights as $w) $sum += max(0, (int) $w);
        if ($sum <= 0) { $out[array_key_first($out)] = $amount; return $out; }
        $rem = []; $given = 0;
        foreach ($weights as $k => $w) {
            $exact = $amount * max(0, (int) $w) / $sum;
            $out[$k] = (int) floor($exact); $given += $out[$k]; $rem[$k] = $exact - $out[$k];
        }
        arsort($rem);   // stable in PHP 8: equal remainders keep their order
        foreach (array_keys($rem) as $k) { if ($given >= $amount) break; $out[$k]++; $given++; }
        return $out;
    }

    /**
     * A payment of $cents recorded against the order's invoices (0177 §4): each invoice's deposit first, then each one's
     * balance, in the order given (pass them oldest destination first, as plan() returns them). Each invoice: key, total,
     * deposit, paid (already recorded on it). $depositsFirst false (pay in full): balances only. Returns
     * ['applied' => key => cents, 'left' => cents over what the order owes].
     */
    public static function allocate(int $cents, array $invoices, bool $depositsFirst = true): array
    {
        $applied = []; foreach ($invoices as $i) $applied[(string) $i['key']] = 0;
        $left = max(0, $cents);
        if ($depositsFirst) foreach ($invoices as $i) {
            if ($left <= 0) break;
            $k = (string) $i['key'];
            $want = max(0, (int) $i['deposit'] - (int) ($i['paid'] ?? 0) - $applied[$k]);
            $t = min($left, $want); $applied[$k] += $t; $left -= $t;
        }
        foreach ($invoices as $i) {
            if ($left <= 0) break;
            $k = (string) $i['key'];
            $want = max(0, (int) $i['total'] - (int) ($i['paid'] ?? 0) - $applied[$k]);
            $t = min($left, $want); $applied[$k] += $t; $left -= $t;
        }
        return ['applied' => $applied, 'left' => $left];
    }

    /**
     * What must be paid across the whole order before it goes to the vendor (model 1.9 as 0177 §4 keeps it): a deposit
     * order, all its invoices' deposits; otherwise the whole total. An admin's release (or outside terms, which record
     * one) is the caller's overridden().
     */
    public static function releaseNeed(string $payMode, array $plan): int
    {
        $dep = self::orderDeposit($plan);
        if ($payMode === 'deposit' && $dep > 0) return $dep;
        return self::orderTotal($plan);
    }

    /** The whole order may go to the vendor. One invoice's deposit or balance alone never releases its destination. */
    public static function releaseMet(string $payMode, array $plan, int $paid, bool $overridden): bool
    {
        return $overridden || $paid >= self::releaseNeed($payMode, $plan);
    }
}
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-1.php ===
<?php
// ANCHOR: replaces "    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array" through "        return [$dep, $words, $open, $openIds];"
// The method's docblock above it and its closing "    }" below it stay as they are in the live file.
// Same behaviour as before (the rule moved to OrderDestinations::deposit so each destination's invoice uses it too):
// lines are read only when no staff % is set, as before.
    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array
    {
        require_once __DIR__ . '/OrderDestinations.php';   // one rule for the order and for each destination's invoice (decisions/0177 §3; M5)
        if ((int) ($o['deposit_pct'] ?? 0) <= 0) $lines ??= Orders::lines($pdo, (int) $o['id']);
        return OrderDestinations::deposit($o, $total, $lines ?? [], self::DEPOSIT_MARKUP, self::DEPOSIT_DEFAULT);
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-2.php ===
<?php
// ANCHOR: replaces "    public static function deleteTest(PDO $pdo, int $oid): void" through "        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$oid]);"
// The method's docblock above it and its closing "    }" below it stay as they are in the live file.
// One change: order_invoices (2027-02-10_order_invoices.sql, M5) is cleared with a sandbox order. Before that migration
// runs, $q swallows the missing table, as it already does for the others.
    public static function deleteTest(PDO $pdo, int $oid): void
    {
        $o = Orders::get($pdo, $oid);
        if (!$o) throw new RuntimeException('That order is already gone.');
        if (!Orders::isTest($o)) throw new RuntimeException('Only a sandbox (test) order can be deleted. A real one is cancelled, and kept.');
        require_once __DIR__ . '/Store.php';
        $q = function (string $sql) use ($pdo, $oid) { try { $pdo->prepare($sql)->execute([$oid]); } catch (Throwable $x) { } };   // a table from a migration not run yet: nothing to clear
        try { foreach ($pdo->query('SELECT * FROM order_proofs WHERE order_id = ' . $oid)->fetchAll(PDO::FETCH_ASSOC) as $p) if ($p['mime'] !== 'go-code') Store::remove(self::proofPath($p)); } catch (Throwable $x) { }
        try { foreach ($pdo->query('SELECT * FROM order_files WHERE order_id = ' . $oid)->fetchAll(PDO::FETCH_ASSOC) as $f) Store::remove(self::filePath($f)); } catch (Throwable $x) { }
        $q('DELETE FROM order_line_parts WHERE line_id IN (SELECT id FROM order_lines WHERE order_id = ?)');
        $q('DELETE FROM order_pack_items WHERE pack_id IN (SELECT id FROM order_packs WHERE order_id = ?)');
        foreach (['order_proofs', 'order_files', 'order_packs', 'order_lots', 'order_addresses', 'order_invoices', 'order_vendor_jobs', 'order_work_orders', 'vendor_jobs', 'order_projects', 'promotion_uses', 'order_ledger', 'order_events', 'order_lines'] as $t) $q("DELETE FROM $t WHERE order_id = ?");
        $q('DELETE FROM qr_issued WHERE order_id = ?');
        $q('UPDATE order_inbox SET order_id = NULL WHERE order_id = ?');
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$oid]);
=== END FILE ===

=== FILE: files/app/migrations/2027-02-10_order_invoices.sql ===
-- Orders M5 (decisions/0177 §1): one QuickBooks invoice per destination under one order.
-- One row per order and destination (dest_key: 'order', 'a<order_addresses.id>', 'ours'; OrderDestinations::destKey).
-- Safe to run twice (CREATE TABLE IF NOT EXISTS). No UPDATE or DELETE. Nothing writes it until the wiring release:
-- until then an order with more than one address still says to send each address as its own order (0177 status).
-- The lane renames the file to the day it ships, and checks the id and charset types against `orders`.
CREATE TABLE IF NOT EXISTS order_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  dest_key VARCHAR(24) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  subtotal_cents INT NOT NULL DEFAULT 0,
  tax_cents INT NOT NULL DEFAULT 0,
  total_cents INT NOT NULL DEFAULT 0,
  deposit_cents INT NOT NULL DEFAULT 0,
  invoice_id VARCHAR(40) NULL,
  invoice_no VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY order_dest (order_id, dest_key),
  KEY order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
=== END FILE ===

=== FILE: tests/m5-destinations-tests.php ===
<?php
// tools/orders-tests/m5-destinations-tests.php — Orders M5 (decisions/0177): the per-destination arithmetic.
// Plain PHP, no database, no QuickBooks, no money: OrderDestinations is pure (checked below).
// Run: php tools/orders-tests/m5-destinations-tests.php   (prints PASS/FAIL per check; exit 1 on any FAIL)
declare(strict_types=1);

$lib = null;
foreach ([__DIR__ . '/../../app/lib/OrderDestinations.php', __DIR__ . '/../files/app/lib/OrderDestinations.php'] as $p) if (is_file($p)) { $lib = $p; break; }
if ($lib === null) { echo "FAIL OrderDestinations.php not found\n"; exit(1); }
require_once $lib;

$fails = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $fails++;
};
$M = 15; $D = 65;   // ClientOrders::DEPOSIT_MARKUP, DEPOSIT_DEFAULT (checked against the class in m5-deposit-parity-tests.php)

// ---- purity: no money can move from here --------------------------------------------------------------------------
$src = (string) file_get_contents($lib);
$check('lib never calls QuickBooks', strpos($src, 'QuickBooksPay') === false);
$check('lib takes no database', strpos($src, 'PDO') === false);

// ---- deposit: release A's rule, unchanged ---------------------------------------------------------------------------
$lines = [
    ['id' => 1, 'title' => 'Banner', 'qty' => 1, 'unit_cents' => 30000, 'cost_cents' => 10000, 'ship_cost_cents' => 2000, 'status' => 'open'],
    ['id' => 2, 'title' => 'Stickers', 'qty' => 2, 'unit_cents' => 500, 'cost_cents' => 0, 'status' => 'open'],
    ['id' => 3, 'title' => 'Gone', 'qty' => 1, 'unit_cents' => 99999, 'cost_cents' => 99999, 'status' => 'cancelled'],
];
[$dep, $words, $open, $ids] = OrderDestinations::deposit(['deposit_pct' => 0], 50000, $lines, $M, $D);
$check('deposit = cost × 1.15 + 65% of an uncosted item', $dep === 14450, (string) $dep);   // 12000 × 1.15 = 13800, + 1000 × .65 = 650
$check('deposit words', $words === "items' cost + 15%; 1 item at 65% of price", $words);
$check('uncosted item named', $open === ['Stickers'] && $ids === [2]);
$check('deposit never above the total', OrderDestinations::deposit(['deposit_pct' => 0], 10000, $lines, $M, $D)[0] === 10000);
$check('staff % of the total', OrderDestinations::deposit(['deposit_pct' => 30], 33333, $lines, $M, $D)[0] === 10000);
$check('staff % per invoice: 50% of 101 is 51 (two invoices of 101 make 102, not 101)', OrderDestinations::deposit(['deposit_pct' => 50], 101, [], $M, $D)[0] === 51);

// ---- destinations ---------------------------------------------------------------------------------------------------
$check('own address wins', OrderDestinations::destKey(['delivery' => 'pickup'], ['ship_to' => '12'], false) === 'a12');
$check('ship_to 0 is no address', OrderDestinations::destKey(['delivery' => 'ship'], ['ship_to' => '0'], true) === 'order');
$check('pickup is ours', OrderDestinations::destKey(['delivery' => 'pickup'], ['ship_to' => ''], false) === 'ours');
$check('nothing to deliver is ours', OrderDestinations::destKey(['delivery' => 'none'], [], false) === 'ours');
$check("delivery '' without a shipping line is ours", OrderDestinations::destKey(['delivery' => ''], [], false) === 'ours');
$check("delivery '' with a shipping line is the order's Ship to", OrderDestinations::destKey(['delivery' => ''], [], true) === 'order');
$check('a GO Code is ours', OrderDestinations::destKey(['delivery' => 'ship'], ['product' => 'go_code'], true) === 'ours');

// ---- the plan: one invoice per destination, oldest first ----------------------------------------------------------
$o = ['id' => 9, 'delivery' => 'ship', 'deposit_pct' => 0];
$ls = [
    ['id' => 11, 'product' => 'go_code', 'title' => 'GO Code: Menu', 'qty' => 1, 'unit_cents' => 3000, 'cost_cents' => 0, 'ship_to' => ''],
    ['id' => 12, 'product' => 'custom', 'title' => 'Signs', 'qty' => 2, 'unit_cents' => 5000, 'cost_cents' => 2000, 'ship_cost_cents' => 1000, 'ship_to' => '7'],
    ['id' => 13, 'product' => 'product', 'title' => 'Banner', 'qty' => 1, 'unit_cents' => 20000, 'cost_cents' => 8000, 'ship_to' => ''],
    ['id' => 14, 'product' => 'shipping', 'title' => 'Shipping', 'qty' => 1, 'unit_cents' => 1500, 'cost_cents' => 1200, 'ship_to' => ''],
    ['id' => 15, 'product' => 'custom', 'title' => 'Cancelled', 'qty' => 1, 'unit_cents' => 7777, 'cost_cents' => 1, 'ship_to' => '', 'status' => 'cancelled'],
];
$plan = OrderDestinations::plan($o, $ls, ['order' => 1720, 'a7' => 800], $M, $D);
$check('three invoices, oldest first, ours last', array_column($plan, 'key') === ['order', 'a7', 'ours'], implode(',', array_column($plan, 'key')));
$check("order's Ship to holds the banner and its shipping", $plan[0]['lines'] === [13, 14]);
$check('cancelled line on no invoice', !in_array(15, array_merge(...array_column($plan, 'lines')), true));
$check('totals carry QuickBooks\' tax per invoice', array_column($plan, 'total') === [23220, 10800, 3000], implode(',', array_column($plan, 'total')));
$check('deposit per invoice', array_column($plan, 'deposit') === [10580, 5750, 1950], implode(',', array_column($plan, 'deposit')));
$check("order's deposit is the sum", OrderDestinations::orderDeposit($plan) === 18280);
$check('one destination, one invoice', count(OrderDestinations::plan($o, [$ls[2], $ls[3]], [], $M, $D)) === 1);

// ---- an estimate's deposit, in proportion ---------------------------------------------------------------------------
$s = OrderDestinations::spread(1000, ['order' => 4000, 'a7' => 3000]);
$check('spread in proportion, exact cents', $s === ['order' => 571, 'a7' => 429], json_encode($s));
$s = OrderDestinations::spread(1000, ['order' => 1, 'a7' => 1, 'ours' => 1]);
$check('spread ties go to the older destination', $s === ['order' => 334, 'a7' => 333, 'ours' => 333], json_encode($s));
$s = OrderDestinations::spread(500, ['order' => 0, 'a7' => 0]);
$check('no deposits to weigh: all on the oldest', $s === ['order' => 500, 'a7' => 0], json_encode($s));

// ---- a payment against the invoices -------------------------------------------------------------------------------
$inv = [['key' => 'order', 'total' => 10000, 'deposit' => 4000, 'paid' => 0], ['key' => 'a7', 'total' => 6000, 'deposit' => 3000, 'paid' => 0]];
$a = OrderDestinations::allocate(8000, $inv);
$check('deposits first, then the oldest balance', $a['applied'] === ['order' => 5000, 'a7' => 3000] && $a['left'] === 0, json_encode($a));
$a = OrderDestinations::allocate(20000, $inv);
$check('over the order: the rest is left over', $a['applied'] === ['order' => 10000, 'a7' => 6000] && $a['left'] === 4000, json_encode($a));
$a = OrderDestinations::allocate(5000, [['key' => 'order', 'total' => 10000, 'deposit' => 4000, 'paid' => 4000], ['key' => 'a7', 'total' => 6000, 'deposit' => 3000, 'paid' => 0]]);
$check('a deposit already in is skipped', $a['applied'] === ['order' => 2000, 'a7' => 3000], json_encode($a));
$a = OrderDestinations::allocate(8000, $inv, false);
$check('pay in full: balances only, oldest first', $a['applied'] === ['order' => 8000, 'a7' => 0], json_encode($a));

// ---- the vendor waits on the whole order -----------------------------------------------------------------------------
$check('deposit order needs every deposit', OrderDestinations::releaseNeed('deposit', $plan) === 18280);
$check('pay in full needs the whole total', OrderDestinations::releaseNeed('full', $plan) === 37020);
$check('one invoice\'s deposit alone releases nothing', !OrderDestinations::releaseMet('deposit', $plan, 10580, false));
$check('the whole deposit releases the order', OrderDestinations::releaseMet('deposit', $plan, 18280, false));
$check('an admin\'s release still works', OrderDestinations::releaseMet('full', $plan, 0, true));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: tests/m5-deposit-parity-tests.php ===
<?php
// tools/orders-tests/m5-deposit-parity-tests.php — ClientOrders::depositFor after M5's region-1 gives release A's numbers.
// The lines are passed in and no staff % path reads the database, so the PDO is an empty in-memory SQLite.
// No QuickBooks, no money. SKIP (not FAIL) when ClientOrders or pdo_sqlite isn't loadable here.
declare(strict_types=1);

$co = __DIR__ . '/../../app/lib/ClientOrders.php';
if (!is_file($co)) { echo "SKIP ClientOrders.php not found from here (run from tools/orders-tests/)\n"; exit(0); }
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { echo "SKIP pdo_sqlite not available\n"; exit(0); }
require_once $co;
$pdo = new PDO('sqlite::memory:');

$fails = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $fails++;
};

$check('markup is 15', ClientOrders::DEPOSIT_MARKUP === 15);
$check('default is 65', ClientOrders::DEPOSIT_DEFAULT === 65);
$lines = [
    ['id' => 1, 'title' => 'Banner', 'qty' => 1, 'unit_cents' => 30000, 'cost_cents' => 10000, 'ship_cost_cents' => 2000, 'status' => 'open'],
    ['id' => 2, 'title' => 'Stickers', 'qty' => 2, 'unit_cents' => 500, 'cost_cents' => 0, 'status' => 'open'],
];
$r = ClientOrders::depositFor($pdo, ['id' => 0, 'deposit_pct' => 0], 50000, $lines);
$check('cost rule unchanged', $r === [14450, "items' cost + 15%; 1 item at 65% of price", ['Stickers'], [2]], json_encode($r));
$r = ClientOrders::depositFor($pdo, ['id' => 0, 'deposit_pct' => 30], 33333);   // no lines: the % path never reads them
$check('staff % unchanged, no read', $r === [10000, '30% of the total, as set', [], []], json_encode($r));

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
### Can one order ship to more than one address?

Coming. For now, an order goes to one address: if things need to go to several places, we'll set up each address as its own order. Soon one order will be able to go to several addresses, with one invoice for each address, all on one order page with one balance.

### How is sales tax worked out when an order goes to several places?

Coming, with orders to several addresses. Sales tax follows where each part goes, so each address gets its own invoice with its own tax. Anything picked up at our shop, delivered by us, or delivered digitally (like a GO Code) is taxed at our address. You'll see the tax for each invoice before you approve.

### How does a deposit work on an order going to several places?

Coming, with orders to several addresses. Each address's invoice has its own deposit, and your order's deposit is them added together. You'll see the amount before you approve, and a deposit paid on an estimate counts toward the order.

### Do I pay each invoice separately?

Coming, with orders to several addresses. No: you pay the order. A payment covers the deposits first, then the rest of each invoice, oldest first. Your order page shows one balance and lists each invoice.

### When does an order with several addresses go into production?

Coming, with orders to several addresses. The whole order goes to production together, once it's paid in full or its whole deposit is in. No single address goes ahead on its own.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "orders-m5-destinations-part1",
  "gate": "Not before M3 and M4 are live and the Orders queue's item 0 (the order tests green again) is done: decisions/0177 note of 2026-11-25 ('after M3 and M4'), order-management design §6, handoffs/decided-not-live.md Orders 0-4. Even then this zip turns nothing on for clients: an order with more than one address still says to send each address as its own order (0177 status) until the wiring release (QuickBooks invoice per destination, approval, payment) is drafted against files not attached here. Session manager and Design reviewer per §6; Jason's pop-up for anything client-facing.",
  "what": "Skipped first: M2b ('how an order goes', paused by Jason 2026-11-24: 'Pause for now. add to work queue.') and Order #12's books (waiting for Jason after the holiday) are untouched. Nothing in decisions/0177 is paused. The attached job-19 draft was empty, so this is built from the live files. Part 1 of M5: (1) app/lib/OrderDestinations.php, pure arithmetic for 0177: each line's destination (its own address, the order's Ship to, or our address for pickup, nothing-to-deliver and GO Codes), one invoice per destination oldest first, each invoice's deposit (COGS x 1.15 or staff % of that invoice's total, capped at it; 65% of price where no cost), the order's deposit as their sum, an estimate's deposit spread in proportion, a payment applied deposits first then balances oldest first, and the vendor release on the whole order only; (2) ClientOrders::depositFor delegates to it (same numbers as release A); (3) the order_invoices table (safe twice; nothing writes it yet) and deleteTest clearing it for sandbox orders; (4) tests and FAQ entries marked 'coming'.",
  "files": [
    "files/app/lib/OrderDestinations.php",
    "files/app/lib/ClientOrders.php.region-1.php",
    "files/app/lib/ClientOrders.php.region-2.php",
    "files/app/migrations/2027-02-10_order_invoices.sql",
    "tests/m5-destinations-tests.php",
    "tests/m5-deposit-parity-tests.php",
    "faq/faq-entries.md",
    "release.json"
  ],
  "decisions": [
    "decisions/0177 §1 (three levels; one invoice per destination; pickup, delivery by us and digital at our address)",
    "decisions/0177 §3 (deposit COGS + 15% per invoice, capped at it; staff % per invoice total; 65% where no cost; estimate deposit in proportion)",
    "decisions/0177 §4 (payments deposits first then balances, oldest destination first; vendor release on the whole order, Jason 2026-11-23)",
    "research/order-management-design-2026-11-23.md §6 (M5 after M3 and M4)",
    "handoffs/decided-not-live.md (M2b paused by Jason; Order #12 waiting for Jason)"
  ],
  "tests": [
    "tests/m5-destinations-tests.php -> tools/orders-tests/m5-destinations-tests.php (pure; no DB, no QuickBooks; also checks the lib never names QuickBooksPay or PDO)",
    "tests/m5-deposit-parity-tests.php -> tools/orders-tests/m5-deposit-parity-tests.php (ClientOrders::depositFor gives release A's numbers after region-1; in-memory SQLite, SKIP if not loadable)"
  ],
  "risks": [
    "Nothing here was run, tested, linted or zip-checked: every file is a draft for the lane session to verify.",
    "No real money moves: the library is pure arithmetic and the tests never call QuickBooksPay; the wiring release must keep QuickBooksPay::guardReal()/testHere() in its tests.",
    "region-1 changes live money code (release A's deposit): it is meant to be behaviour-identical; m5-deposit-parity-tests.php checks two cases only. Check the email in ClientOrders::send and the board's deposit sub against an existing deposit order before and after.",
    "0177 §3 says an item whose COGS 'isn't checked (Costing: unchecked, or zero)' counts at 65%; the live rule (kept as is) only treats a zero cost that way, matching Jason's confirmed words 'isn't recorded'. Left unchanged; the lane asks the session manager which reading holds.",
    "Read literally, 0177 §1 puts a GO Code (digital) at our address, so a shipped order with a GO Code gets two invoices. Implemented literally; confirm before wiring. Services (service_type) are not treated as digital here.",
    "'Oldest destination first' is defined as the order's own Ship to, then added addresses by id, our address last: an interpretation, not in the decision text.",
    "A staff % applied per invoice can differ by a cent per invoice from the same % of the whole order (tested: 2 x 50% of $1.01 = $1.02).",
    "Invoice subtotals use qty x unit + ship_cents (as depositFor reads a price); promo_off_cents and whatever recalc does are not included (recalc not shown).",
    "order_invoices is created but unused until the wiring release; its column types are a guess against orders.id (not shown).",
    "The FAQ wording avoids saying 'cost + 15%' to clients; a content look should confirm that's wanted."
  ],
  "verify": [
    "file not shown: app/lib/QuickBooksPay.php (invoiceFor's ShipAddr, invoice per destination, the guardReal/testHere helpers): the wiring release needs it.",
    "file not shown in full: app/lib/ClientOrders.php is truncated after boardState; approve, pay, recalc, releaseAt, delivery(), split/unsplit (release A) were not seen. Check releaseAt matches OrderDestinations::releaseNeed for deposit orders, and that split/unsplit is what release B replaces.",
    "Check the region anchors against the live ClientOrders.php byte for byte; each region ends before the method's closing brace, which stays.",
    "file not shown: the order_addresses table and order_lines.ship_to semantics beyond 'digits = an address id' (from duplicate()).",
    "file not shown: app/lib/Costing.php (what 'checked' means for 0177 §3).",
    "file not shown: faq.md (merge faq/faq-entries.md into it); tools/orders-tests/ harness (queue item 0 says it's red: run these after it's green).",
    "Rename the migration to the day it ships; run it twice in phpMyAdmin to confirm it is safe twice; confirm deleteTest on a sandbox order clears order_invoices once the table exists.",
    "Run both tests on PHP 8.0."
  ],
  "regions": true
}
=== END FILE ===