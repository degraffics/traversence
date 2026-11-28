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
