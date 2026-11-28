<?php
// app/lib/KindCheckout.php — buying by kind (decisions/0141 §5, ItemKinds): what each kind asks at checkout, what it
// adds to the order, what's paid now, and what the buyer gets once it's paid. MarketCheckout calls it; the kinds' rules
// live here so checkout itself stays one path.
//   asked      Made to order: the business's questions, per line · Harvest: a pickup window · Bookings: dates and guests,
//              and the house rules agreed (a record) · Rentals: dates, and the damage waiver if wanted
//   added      a service's travel fee · lodging tax on a stay · a rental's deposit (refundable) and waiver
//   paid now   Made to order: its deposit (the rest invoiced when ready) · Rentals on hold: authorized, captured on return
//   after      Tickets: a code per seat, checked in once at the door (never a voided one: its order was refunded) ·
//              Digital: a download link (times and days) ·
//              Subscriptions: Stripe bills it each month or year until the buyer cancels
// Availability comes from orders themselves: a stay or a rental holds its dates (paid, or being paid within its hold),
// tickets their seats; nothing else to keep in step.
declare(strict_types=1);

require_once __DIR__ . '/ItemKinds.php';

final class KindCheckout
{
    /** Kinds bought one at a time with Buy now (they need dates, or recur): never in a cart with others. */
    public const ALONE = ['booking', 'rental', 'subscription'];
    /** Kinds that are shipped or handed over (the order asks how you get it). */
    public const SHIPS = ['physical', 'madetoorder'];

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM market_tickets LIMIT 0'); $pdo->query('SELECT 1 FROM market_downloads LIMIT 0'); $pdo->query('SELECT pay_now_cents, sub_ref FROM market_orders LIMIT 0'); return $ok = true; }
        catch (PDOException) { return $ok = false; }
    }

    /** Does this table have this column yet (a migration not run yet: no)? Table and column are constants here, never input. */
    private static function hasCol(PDO $pdo, string $table, string $col): bool
    {
        static $seen = [];
        $key = $table . '.' . $col;
        if (isset($seen[$key])) return $seen[$key];
        try { $pdo->query('SELECT ' . $col . ' FROM ' . $table . ' LIMIT 0'); return $seen[$key] = true; }
        catch (PDOException) { return $seen[$key] = false; }
    }

    public static function kindOf(array $it): array
    {
        $k = ItemKinds::key((string) ($it['kind'] ?? ''));
        return [$k, ItemKinds::data($k, $it['kind_data'] ?? null)];
    }

    /** Can it be bought now, by its kind? [yes, why not] (MarketCheckout::buyable adds its own checks first). */
    public static function buyable(PDO $pdo, array $it): array
    {
        [$k, $d] = self::kindOf($it);
        $mode = ItemKinds::buy($k, $d);
        if ($mode === 'quote') return [false, 'Priced by the hour: ask for a quote.'];
        [$ok, $miss] = ItemKinds::ready($k, $d);
        if (!$ok) return [false, 'Not ready to sell yet.'];
        if ($k === 'ticket' && !empty($d['starts']) && strtotime((string) $d['starts']) < time()) return [false, 'This event has started.'];
        if (in_array($k, ['booking', 'rental', 'subscription'], true) && !self::ready($pdo)) return [false, 'Opening soon.'];
        if ($k === 'subscription') {
            $g = MarketCheckout::gateway($pdo, (int) $it['entity_id']);
            if (!$g || $g['status'] !== 'connected') return [false, 'Subscriptions need the business\'s card payments.'];
            [$gok, $why] = ItemKinds::gatewayOk($k, (string) $g['provider']);
            if (!$gok) return [false, $why];
        }
        return [true, ''];
    }

    /** Seats left for a ticket: its capacity, less seats sold and seats being paid for. */
    public static function seatsLeft(PDO $pdo, array $it, int $exceptOrder = 0): ?int
    {
        [$k, $d] = self::kindOf($it);
        if ($k !== 'ticket' || empty($d['capacity'])) return null;
        return max(0, (int) $d['capacity'] - self::taken($pdo, (int) $it['id'], null, null, $exceptOrder));
    }

    /**
     * How many of an item are taken (paid, or being paid within their hold), optionally only on dates overlapping
     * [$from, $to) (stays and rentals). Read from the orders' lines.
     */
    public static function taken(PDO $pdo, int $itemId, ?string $from, ?string $to, int $exceptOrder = 0): int
    {
        $st = $pdo->prepare("SELECT order_lines FROM market_orders WHERE id <> ? AND order_lines LIKE ? AND (status = 'paid' OR (status IN ('pending','awaiting') AND hold_until > ?))");
        $st->execute([$exceptOrder, '%"item_id":' . $itemId . ',%', date('Y-m-d H:i:s')]);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $j) foreach ((array) json_decode((string) $j, true) as $l) {
            if ((int) ($l['item_id'] ?? 0) !== $itemId) continue;
            if ($from !== null) {
                $lf = (string) ($l['kx']['from'] ?? ''); $lt = (string) ($l['kx']['to'] ?? '');
                if ($lf === '' || !($lf < $to && $lt > $from)) continue;   // its dates don't overlap
                $n += (int) ($l['kx']['units'] ?? 1);
            } else $n += (int) $l['qty'];
        }
        return $n;
    }

    /** Units free on these dates (a stay: one place; a rental: how many it has). */
    public static function freeOn(PDO $pdo, array $it, string $from, string $to, int $exceptOrder = 0): int
    {
        [$k, $d] = self::kindOf($it);
        $have = $k === 'rental' ? max(1, (int) ($d['units'] ?? 1)) : 1;
        return max(0, $have - self::taken($pdo, (int) $it['id'], $from, $to, $exceptOrder));
    }

    /** Checks a date range for a stay or a rental. Returns [from, to, nights or days]. Throws with what's wrong. */
    public static function dates(array $it, string $from, string $to): array
    {
        [$k, $d] = self::kindOf($it);
        $f = strtotime($from); $t = strtotime($to);
        if (!$f || !$t) throw new InvalidArgumentException($k === 'booking' ? 'Choose your check-in and check-out dates.' : 'Choose the day you pick it up and the day you bring it back.');
        $from = date('Y-m-d', $f); $to = date('Y-m-d', $t);
        if ($from < date('Y-m-d')) throw new InvalidArgumentException('That date has passed.');
        $n = (int) round(($t - $f) / 86400);
        if ($k === 'rental') $n = max(1, $n);   // picked up and returned the same day: one day
        if ($n < 1) throw new InvalidArgumentException('Check-out is after check-in.');
        if ($n > 90) throw new InvalidArgumentException('Book up to 90 ' . ($k === 'booking' ? 'nights' : 'days') . ' at a time.');
        $min = (int) ($d[$k === 'booking' ? 'min_nights' : 'min_days'] ?? 1);
        if ($n < $min) throw new InvalidArgumentException('At least ' . $min . ($k === 'booking' ? ' nights.' : ' days.'));
        if ($k === 'rental' && !empty($d['max_days']) && $n > (int) $d['max_days']) throw new InvalidArgumentException('At most ' . (int) $d['max_days'] . ' days.');
        if ($k === 'rental' && $to === $from) $to = date('Y-m-d', $f + 86400);   // held through the day
        return [$from, $to, $n];
    }

    /**
     * The order's lines by kind, from the items and what the buyer gave ($kx: specs[item_id][i], window, from, to,
     * guests, waiver, rules). Returns ['lines' (each with kind and kx), 'extra' (fee, tax and deposit lines),
     * 'pay_now' (null: all), 'mode' (payment|subscription), 'hold' (a rental deposit held on the card), 'ships',
     * 'house_rules' (text agreed), 'needs' (what the page still has to ask)]. Throws with what's missing.
     */
    public static function prepare(PDO $pdo, array $lines, array $kx, bool $strict, string $provider = ''): array
    {
        $out = ['lines' => [], 'extra' => [], 'pay_now' => null, 'mode' => 'payment', 'hold' => false, 'ships' => false, 'house_rules' => '', 'windows' => [], 'needs' => []];
        $balance = 0; $kinds = [];
        foreach ($lines as $l) {
            $it = !empty($l['item_id']) ? MarketBiz::get($pdo, (int) $l['item_id']) : null;
            if (!$it) { $l['kind'] = 'physical'; $out['lines'][] = $l; continue; }   // the setup's test purchase
            [$k, $d] = self::kindOf($it); $kinds[] = $k;
            $l['kind'] = $k; $l['kx'] = [];
            if (in_array($k, self::SHIPS, true)) $out['ships'] = true;
            if ($k === 'physical' && ($d['pickup_only'] ?? 'no') === 'yes') $out['pickup_only'] = true;
            switch ($k) {
                case 'service':
                    if (!empty($d['travel_fee'])) $out['extra'][] = ['item_id' => 0, 'product_id' => 0, 'title' => 'Travel fee: ' . $l['title'], 'qty' => 1, 'unit_cents' => (int) $d['travel_fee'], 'taxable' => 0, 'extra' => 'travel'];
                    break;
                case 'madetoorder':
                    $qs = array_values(array_filter(preg_split('/\R/', (string) ($d['specs'] ?? '')) ?: []));
                    foreach ($qs as $i => $q) {
                        $a = mb_substr(trim(strip_tags((string) ($kx['specs'][$it['id']][$i] ?? ''))), 0, 400);
                        if ($a === '' && $strict) throw new InvalidArgumentException($l['title'] . ': ' . $q . '?');
                        $l['kx']['specs'][$q] = $a;
                    }
                    if ($qs) $out['needs'][] = 'specs';
                    $pct = (int) ($d['deposit_pct'] ??
