<?php
// app/lib/KindCheckout.php — buying by kind (decisions/0141 §5, ItemKinds): what each kind asks at checkout, what it
// adds to the order, what's paid now, and what the buyer gets once it's paid. MarketCheckout calls it; the kinds' rules
// live here so checkout itself stays one path.
//   asked      Made to order: the business's questions, per line · Harvest: a pickup window · Bookings: dates and guests,
//              and the house rules agreed (a record) · Rentals: dates, and the damage waiver if wanted
//   added      a service's travel fee · lodging tax on a stay · a rental's deposit (refundable) and waiver
//   paid now   Made to order: its deposit (the rest invoiced when ready) · Rentals on hold: authorized, captured on return
//              (held only when the return day is within HOLD_DAYS of now, so the authorization can't lapse first;
//              otherwise the deposit is charged and refunded on return)
//   after      Tickets: a code per seat, checked in once at the door · Digital: a download link (times and days) ·
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
    /** Days from now to the start of the return day within which a rental deposit may be held on the card (Stripe keeps an online card authorization about 7 days). */
    public const HOLD_DAYS = 6;

    public static function ready(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try { $pdo->query('SELECT 1 FROM market_tickets LIMIT 0'); $pdo->query('SELECT 1 FROM market_downloads LIMIT 0'); $pdo->query('SELECT pay_now_cents, sub_ref FROM market_orders LIMIT 0'); return $ok = true; }
        catch (PDOException) { return $ok = false; }
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

    /**
     * Can a rental's deposit be held on the card? Only if the return day ($to, as dates() gives it) starts within
     * HOLD_DAYS of now: the hold is captured on return, and a card authorization lapses after about a week, so what
     * counts is the time until it comes back, not how many days it's rented. A date that doesn't read: no hold.
     */
    public static function holdFits(string $to, ?int $now = null): bool
    {
        $t = strtotime($to);
        if (!$t) return false;
        return $t - ($now ?? time()) <= self::HOLD_DAYS * 86400;
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
                    $pct = (int) ($d['deposit_pct'] ?? 0);
                    if ($pct > 0 && $pct < 100) { $balance += (int) round((int) $l['qty'] * (int) $l['unit_cents'] * (100 - $pct) / 100); $l['kx']['deposit_pct'] = $pct; }
                    if (($d['proof'] ?? 'no') === 'yes') $l['kx']['proof'] = true;
                    break;
                case 'ticket':
                    $l['kx']['starts'] = (string) ($d['starts'] ?? '');
                    break;
                case 'harvest':
                    foreach (array_filter(preg_split('/\R/', (string) ($d['windows'] ?? '')) ?: []) as $w) $out['windows'][$w] = $w;
                    break;
                case 'booking':
                case 'rental':
                    if (count($lines) > 1) throw new InvalidArgumentException('Book ' . $l['title'] . ' on its own: it needs its dates.');
                    if (($kx['from'] ?? '') === '' || ($kx['to'] ?? '') === '') { $out['needs'][] = 'dates'; if ($strict) throw new InvalidArgumentException($k === 'booking' ? 'Choose your dates.' : 'Choose your rental dates.'); break; }
                    [$from, $to, $n] = self::dates($it, (string) $kx['from'], (string) $kx['to']);
                    if (self::freeOn($pdo, $it, $from, $to) < 1) throw new InvalidArgumentException('Those dates are taken. Try others.');
                    $l['qty'] = $n; $l['kx'] += ['from' => $from, 'to' => $to, 'units' => 1];
                    $l['title'] .= ' (' . date('M j', strtotime($from)) . ' to ' . date('M j', strtotime((string) $kx['to'])) . ', ' . $n . ($k === 'booking' ? ' night' : ' day') . ($n === 1 ? '' : 's') . ')';   // as the buyer chose them
                    if ($k === 'booking') {
                        $g = max(1, (int) ($kx['guests'] ?? 1));
                        if ($g > (int) ($d['max_guests'] ?? 2)) throw new InvalidArgumentException('Up to ' . (int) ($d['max_guests'] ?? 2) . ' guests.');
                        $l['kx']['guests'] = $g;
                        if (!empty($d['lodging_tax'])) $out['extra'][] = ['item_id' => 0, 'product_id' => 0, 'title' => 'Lodging tax (' . rtrim(rtrim(number_format((float) $d['lodging_tax'], 2), '0'), '.') . '%)', 'qty' => 1,
                            'unit_cents' => (int) round($n * (int) $l['unit_cents'] * (float) $d['lodging_tax'] / 100), 'taxable' => 0, 'extra' => 'lodging_tax'];
                        $out['house_rules'] = (string) ($d['house_rules'] ?? '');
                        if ($strict && $out['house_rules'] !== '' && empty($kx['rules'])) throw new InvalidArgumentException('Read and agree to the house rules.');
                    } else {
                        if (!empty($kx['waiver']) && !empty($d['waiver'])) $out['extra'][] = ['item_id' => 0, 'product_id' => 0, 'title' => 'Damage waiver', 'qty' => 1, 'unit_cents' => (int) $d['waiver'], 'taxable' => 1, 'extra' => 'waiver'];
                        if (!empty($d['deposit'])) {
                            $hold = ($d['deposit_mode'] ?? 'charge') === 'hold';
                            if ($hold && ($provider !== 'stripe' || $n > self::HOLD_DAYS || !self::holdFits($to))) $hold = false;   // a hold only on Stripe, and only when it comes back (and is captured) within the week a card holds it
                            $out['extra'][] = ['item_id' => 0, 'product_id' => 0, 'title' => 'Deposit (' . ($hold ? 'held on your card, released on return' : 'refunded on return') . ')', 'qty' => 1, 'unit_cents' => (int) $d['deposit'], 'taxable' => 0, 'extra' => 'deposit'];
                            $out['hold'] = $hold;
                        }
                    }
                    break;
                case 'subscription':
                    if (count($lines) > 1) throw new InvalidArgumentException('Subscribe to ' . $l['title'] . ' on its own.');
                    $l['qty'] = 1; $l['kx'] = ['interval' => ($d['interval'] ?? 'month') === 'year' ? 'year' : 'month', 'trial_days' => (int) ($d['trial_days'] ?? 0)];
                    $out['mode'] = 'subscription';
                    break;
            }
            $out['lines'][] = $l;
        }
        if ($out['windows']) {
            $out['needs'][] = 'window';
            $w = (string) ($kx['window'] ?? '');
            if ($strict && !isset($out['windows'][$w])) throw new InvalidArgumentException('Choose a pickup window.');
            if ($w !== '') foreach ($out['lines'] as &$x) if ($x['kind'] === 'harvest') $x['kx']['window'] = $w;
            unset($x);
        }
        if ($balance > 0) $out['balance'] = $balance;
        if (in_array('subscription', $kinds, true) && count($lines) > 1) throw new InvalidArgumentException('Subscribe on its own.');
        return $out;
    }

    /** How the buyer gets an order with nothing shipped, said plainly (its "getting it" line). */
    public static function getting(array $kp): string
    {
        $k = array_values(array_unique(array_column($kp['lines'], 'kind')));
        $win = (string) ($kp['lines'][0]['kx']['window'] ?? '');
        foreach ($kp['lines'] as $l) if (!empty($l['kx']['window'])) $win = (string) $l['kx']['window'];
        if ($k === ['ticket']) return 'Tickets: on your order page';
        if ($k === ['digital']) return 'Download after paying';
        if ($k === ['booking']) return 'Your stay';
        if ($k === ['rental']) return 'Picked up from the business, and returned';
        if ($k === ['subscription']) return 'Subscription';
        if ($k === ['service']) return 'Done where you agree with the business';
        if ($win !== '') return 'Pickup: ' . $win;
        return 'Nothing shipped';
    }

    // ---- after it's paid --------------------------------------------------------------------------------------------

    /** Once paid: a ticket code per seat, a download link per file. Returns what to tell the buyer (lines of text with links). */
    public static function fulfil(PDO $pdo, array $o): array
    {
        if (!self::ready($pdo)) return [];
        $tell = []; $now = date('Y-m-d H:i:s');
        foreach ((array) $o['lines'] as $l) {
            $k = (string) ($l['kind'] ?? ''); $iid = (int) ($l['item_id'] ?? 0);
            if (!$iid) continue;
            if ($k === 'ticket') {
                $have = $pdo->prepare('SELECT COUNT(*) FROM market_tickets WHERE order_id = ? AND item_id = ?'); $have->execute([(int) $o['id'], $iid]);
                for ($s = (int) $have->fetchColumn() + 1; $s <= (int) $l['qty']; $s++)
                    $pdo->prepare('INSERT INTO market_tickets (order_id, item_id, entity_id, seat, code, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([(int) $o['id'], $iid, (int) $o['entity_id'], $s, self::code(), $now]);
                $tell[] = $l['title'] . ': ' . (int) $l['qty'] . ' ticket' . ((int) $l['qty'] === 1 ? '' : 's') . ' on your order page, a code each for the door.';
            }
            if ($k === 'digital') {
                $it = MarketBiz::get($pdo, $iid); [, $d] = $it ? self::kindOf($it) : ['', []];
                $have = $pdo->prepare('SELECT token FROM market_downloads WHERE order_id = ? AND item_id = ?'); $have->execute([(int) $o['id'], $iid]);
                $tok = (string) $have->fetchColumn();
                if ($tok === '') {
                    $tok = bin2hex(random_bytes(16));
                    $pdo->prepare('INSERT INTO market_downloads (order_id, item_id, token, uses, max_uses, expires_at, created_at) VALUES (?, ?, ?, 0, ?, ?, ?)')
                        ->execute([(int) $o['id'], $iid, $tok, max(1, (int) ($d['downloads'] ?? 5)) * max(1, (int) $l['qty']), date('Y-m-d H:i:s', strtotime('+' . max(1, (int) ($d['days'] ?? 30)) . ' days')), $now]);
                }
                $tell[] = $l['title'] . ': download it at https://traversence.com/market/download.php?t=' . $tok . ' (' . max(1, (int) ($d['downloads'] ?? 5)) . ' times, ' . max(1, (int) ($d['days'] ?? 30)) . ' days).';
            }
        }
        return $tell;
    }

    private static function code(): string
    {
        $abc = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $c = ''; for ($i = 0; $i < 10; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)];
        return $c;
    }

    /** An order's tickets: [id, item_id, seat, code, checked_in_at]. */
    public static function tickets(PDO $pdo, int $orderId): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT * FROM market_tickets WHERE order_id = ? ORDER BY item_id, seat'); $st->execute([$orderId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function ticket(PDO $pdo, string $code): ?array
    {
        if (!self::ready($pdo) || !preg_match('/^[2-9A-HJ-NP-Z]{10}$/', $code)) return null;
        $st = $pdo->prepare('SELECT * FROM market_tickets WHERE code = ?'); $st->execute([$code]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** At the door: a ticket checked in once, by someone on the business's team. Returns [ok, message]. */
    public static function checkIn(PDO $pdo, string $code, int $uid): array
    {
        $t = self::ticket($pdo, $code);
        if (!$t) return [false, 'Not a ticket here.'];
        if (!MarketBiz::onTeam($pdo, $uid, (int) $t['entity_id'])) return [false, 'Only the business\'s team checks tickets in.'];
        $o = MarketCheckout::get($pdo, (int) $t['order_id']);
        if (!$o || $o['status'] !== 'paid') return [false, 'This ticket\'s order isn\'t paid (' . ($o ? strtolower(MarketCheckout::STATUSES[$o['status']] ?? $o['status']) : 'no order') . ').'];
        if ($t['checked_in_at']) return [false, 'Already checked in, ' . date('M j \a\t g:i a', strtotime((string) $t['checked_in_at'])) . '.'];
        $st = $pdo->prepare('UPDATE market_tickets SET checked_in_at = ?, checked_in_by = ? WHERE id = ? AND checked_in_at IS NULL'); $st->execute([date('Y-m-d H:i:s'), $uid, (int) $t['id']]);
        return $st->rowCount() ? [true, 'Checked in: seat ' . (int) $t['seat'] . '.'] : [false, 'Already checked in.'];
    }

    /** A download: [ok, the file's place (app/storage/…), its name, why not, its type]. Counts the use. */
    public static function download(PDO $pdo, string $token): array
    {
        if (!self::ready($pdo) || !preg_match('/^[a-f0-9]{32}$/', $token)) return [false, '', '', 'That link isn\'t right.'];
        $st = $pdo->prepare('SELECT * FROM market_downloads WHERE token = ?'); $st->execute([$token]);
        $dl = $st->fetch(PDO::FETCH_ASSOC);
        if (!$dl) return [false, '', '', 'That link isn\'t right.'];
        if (strtotime((string) $dl['expires_at']) < time()) return [false, '', '', 'This link has expired. Ask the seller for a new one.'];
        if ((int) $dl['uses'] >= (int) $dl['max_uses']) return [false, '', '', 'This link has been used ' . ((int) $dl['max_uses'] === 1 ? 'once, its only download' : 'its ' . (int) $dl['max_uses'] . ' times') . '. Ask the seller for a new one.'];
        $o = MarketCheckout::get($pdo, (int) $dl['order_id']);
        if (!$o || $o['status'] !== 'paid') return [false, '', '', 'This order isn\'t paid.'];
        $it = MarketBiz::get($pdo, (int) $dl['item_id']); [, $d] = $it ? self::kindOf($it) : ['', []];
        if (empty($d['file_key'])) return [false, '', '', 'The seller hasn\'t added the file. Ask them.'];
        $pdo->prepare('UPDATE market_downloads SET uses = uses + 1 WHERE id = ?')->execute([(int) $dl['id']]);
        return [true, (string) $d['file_key'], (string) ($d['file_name'] ?? 'download'), '', (string) ($d['file_mime'] ?? 'application/octet-stream')];
    }

    public static function downloads(PDO $pdo, int $orderId): array
    {
        if (!self::ready($pdo)) return [];
        $st = $pdo->prepare('SELECT * FROM market_downloads WHERE order_id = ?'); $st->execute([$orderId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
