<?php
// app/lib/OrderMoney.php — Back-Office → Money (Orders M3; research/order-management-design-2026-11-23.md §4.4 and §6):
//
//   Outstanding        every approved client order with money still to pay, by client, aged from its due date
//                      (current, 1–30, 31–60, 61–90, 90+; a deposit order's balance that isn't due yet: due later).
//                      Money owed is read from ClientOrders::boardState() (§3: nothing else decides it).
//   Take a payment     one payment that came in (a check, a wire, cash…) recorded across one client's open orders:
//                      deposits first, then balances, oldest first (decisions/0177 §4). Each part is an ordinary payment
//                      on its order ('out-' key), received on its QuickBooks invoice when it has one (0178 note (b)), and
//                      corrected like any payment: Cancel the record, or Refu nd… with Jason's OK (0178's money note).
//   Credits on account each client's credit (90 days, 0098) and its use: staff apply it to an order that owes money
//                      (0178 §3: the system suggests it, staff press it, never by itself). credit_used is written here for
//                      the first time. A credit applied is money: no Undo; Cancel the record instead (0178's money note).
//
// It records only. It never charges a card (0178 §5: no keyed cards; the wallet's saved cards aren't used here), never
// makes a credit (a credit memo waits for Jason's OK, 0178's note of 2026-11-24) and never turns an overpayment into one.
// Orders whose books wait for Jason (HELD) are listed and never paid into or credited from here.
declare(strict_types=1);

require_once __DIR__ . '/Orders.php';

final class OrderMoney
{
    public const AGING = ['current' => 'Current', 'd1_30' => '1–30 days', 'd31_60' => '31–60 days', 'd61_90' => '61–90 days', 'd90' => '90+ days', 'later' => 'Due later'];

    /** Orders whose books wait for Jason (handoffs/decided-not-live.md, "Open with the Founder"): shown, never touched here. */
    public const HELD = ['12' => 'Order #12\'s books wait for Jason ($1,111.14 invoiced against $809.10 of lines; "I will address this after holiday", 2026-11-27)'];

    // ---- pure helpers (tested without a database) ------------------------------------------------------------------------

    /** The aging bucket for days past due (0 or less: current). */
    public static function bucket(int $daysPast): string
    {
        if ($daysPast <= 0) return 'current';
        if ($daysPast <= 30) return 'd1_30';
        if ($daysPast <= 60) return 'd31_60';
        if ($daysPast <= 90) return 'd61_90';
        return 'd90';
    }

    /** Whole days from a due date to today (negative: not due yet). Both 'Y-m-d'. */
    public static function daysPast(string $due, string $today): int
    {
        return (int) floor((strtotime($today . ' 00:00:00') - strtotime($due . ' 00:00:00')) / 86400);
    }

    /**
     * One payment split over open orders (0177 §4): each order's deposit still due first, then each balance, both oldest
     * first. $rows in oldest-first order: [['id', 'deposit' (cents of the deposit still due), 'left' (all still to pay)], …].
     * Returns ['parts' => [id => cents], 'over' => cents more than the orders owe].
     */
    public static function allocate(int $cents, array $rows): array
    {
        $parts = []; $rest = max(0, $cents);
        foreach ($rows as $r) {
            $take = min($rest, max(0, min((int) $r['deposit'], (int) $r['left'])));
            if ($take > 0) { $parts[(int) $r['id']] = $take; $rest -= $take; }
        }
        foreach ($rows as $r) {
            $room = max(0, (int) $r['left'] - ($parts[(int) $r['id']] ?? 0));
            $take = min($rest, $room);
            if ($take > 0) { $parts[(int) $r['id']] = ($parts[(int) $r['id']] ?? 0) + $take; $rest -= $take; }
        }
        return ['parts' => $parts, 'over' => $rest];
    }

    /** Why this order can't be paid into or credited from here ('' when it can). */
    public static function held(array $o): string
    {
        return Orders::isTest($o) ? '' : (self::HELD[Orders::no($o)] ?? '');
    }

    /** Whose order it is: a member by account, a guest by email (as Orders::creditBalance groups them). */
    public static function ownerKey(array $o): string
    {
        return (int) ($o['user_id'] ?? 0) > 0 ? 'u' . (int) $o['user_id'] : 'e' . strtolower(trim((string) ($o['bill_email'] ?? '')));
    }

    // ---- reading ---------------------------------------------------------------------------------------------------------

    /** Is the Money section on the sandbox side now (test orders only), or the real one? */
    public static function testSide(): bool
    {
        require_once __DIR__ . '/QuickBooksPay.php';
        return QuickBooksPay::env() === 'sandbox';
    }

    private static function sideSql(bool $test): string
    {
        return $test ? " AND o.qb_env = 'sandbox'" : " AND COALESCE(o.qb_env, '') <> 'sandbox'";
    }

    /** When the order's money became due, 'Y-m-d': ClientOrders::dueDate() when it can be called, else its terms from approval. */
    public static function dueSince(array $o): string
    {
        require_once __DIR__ . '/ClientOrders.php';
        if (is_callable(['ClientOrders', 'dueDate'])) { $d = strtotime((string) ClientOrders::dueDate($o)); if ($d) return date('Y-m-d', $d); }
        if (!empty($o['due_date']) && ($d = strtotime((string) $o['due_date']))) return date('Y-m-d', $d);
        $from = (string) ($o['approved_at'] ?? '') ?: (string) ($o['sent_at'] ?? '') ?: (string) ($o['created_at'] ?? '') ?: 'now';
        $days = (int) (ClientOrders::DUE_TERMS[(string) ($o['due_terms'] ?? 'receipt')][1] ?? 0);
        return date('Y-m-d', strtotime($from . ' +' . $days . ' days'));
    }

    /** One order as Outstanding shows it, or null when nothing is left to pay. */
    public static function row(PDO $pdo, array $o, string $today): ?array
    {
        require_once __DIR__ . '/ClientOrders.php';
        $b = ClientOrders::boardState($pdo, $o);
        $left = (int) $b['money']['left'];
        if ($left <= 0) return null;
        $due = (int) $b['money']['due'];
        $since = self::dueSince($o);
        $days = self::daysPast($since, $today);
        return ['id' => (int) $o['id'], 'no' => Orders::no($o), 'order' => $o, 'left' => $left, 'due' => $due,
                'deposit' => $b['money']['key'] === 'deposit_due' ? $due : 0, 'word' => (string) $b['money']['word'], 'sentence' => (string) $b['money']['sentence'],
                'since' => $since, 'days' => $due > 0 ? max(0, $days) : 0, 'aging' => $due > 0 ? self::bucket($days) : 'later',
                'held' => self::held($o), 'next' => $b['next'] ?? null];
    }

    /**
     * Outstanding: every approved client order on this side with money still to pay, by client, the longest overdue first.
     * Returns ['clients' => [[key, name, company, email, left, due, days, rows]], 'totals' => [aging => cents], 'left', 'orders'].
     */
    public static function outstanding(PDO $pdo, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $st = $pdo->query("SELECT o.* FROM orders o WHERE o.created_by > 0 AND o.status = 'pending'" . self::sideSql(self::testSide()) . ' ORDER BY o.id LIMIT 500');
        $clients = []; $totals = array_fill_keys(array_keys(self::AGING), 0); $all = 0; $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $r = self::row($pdo, $o, $today);
            if (!$r) continue;
            $k = self::ownerKey($o);
            if (!isset($clients[$k])) $clients[$k] = ['key' => $k, 'name' => (string) $o['bill_name'], 'company' => (string) ($o['client_company'] ?? ''), 'email' => (string) $o['bill_email'], 'left' => 0, 'due' => 0, 'days' => 0, 'rows' => []];
            $clients[$k]['rows'][] = $r;
            $clients[$k]['left'] += $r['left']; $clients[$k]['due'] += $r['due'];
            $clients[$k]['days'] = max($clients[$k]['days'], $r['days']);
            if ($r['due'] > 0) $totals[$r['aging']] += $r['due'];
            $totals['later'] += $r['left'] - $r['due'];
            $all += $r['left']; $n++;
        }
        $clients = array_values($clients);
        usort($clients, fn ($a, $b) => [$b['days'], $b['left']] <=> [$a['days'], $a['left']]);
        return ['clients' => $clients, 'totals' => $totals, 'left' => $all, 'orders' => $n];
    }

    /** The client's open orders (the same owner and side as $o), oldest first: what Take a payment ticks. */
    public static function openFor(PDO $pdo, array $o, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $uid = (int) ($o['user_id'] ?? 0);
        $st = $pdo->prepare("SELECT o.* FROM orders o WHERE o.created_by > 0 AND o.status = 'pending' AND " . ($uid > 0 ? 'o.user_id = ?' : 'o.user_id IS NULL AND LOWER(o.bill_email) = LOWER(?)') . self::sideSql(Orders::isTest($o)) . ' ORDER BY o.id LIMIT 100');
        $st->execute([$uid > 0 ? $uid : (string) $o['bill_email']]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) if ($r = self::row($pdo, $x, $today)) $out[] = $r;
        usort($out, fn ($a, $b) => [$a['since'], $a['id']] <=> [$b['since'], $b['id']]);
        return $out;
    }

    // ---- Take a payment across orders -------------------------------------------------------------------------------------

    /**
     * One payment that came in, recorded across the ticked orders of one client (deposits first, then balances, oldest
     * first). $method: a ClientOrders::PAY_METHODS key. Refused when it's more than they owe (an overpayment isn't made a
     * credit here). A double press within the minute records nothing more (the ledger's keys). Returns, per order:
     * [order id, cents recorded, words].
     */
    public static function takePayment(PDO $pdo, array $oids, int $cents, string $method, string $ref, int $actor): array
    {
        require_once __DIR__ . '/ClientOrders.php';
        if ($cents <= 0) throw new RuntimeException('Enter the amount that came in.');
        if (!isset(ClientOrders::PAY_METHODS[$method])) throw new RuntimeException('Choose how it came in.');
        $ref = mb_substr(trim($ref), 0, 60);
        $oids = array_values(array_unique(array_filter(array_map('intval', $oids))));
        if (!$oids) throw new RuntimeException('Tick the orders it pays. Nothing was recorded.');
        sort($oids);
        $rows = []; $owner = null; $side = null; $today = date('Y-m-d');
        foreach ($oids as $oid) {
            $o = Orders::get($pdo, $oid);
            if (!$o || !ClientOrders::isClient($o)) throw new RuntimeException('That order isn\'t here. Nothing was recorded.');
            if ($o['status'] !== 'pending') throw new RuntimeException('Order #' . Orders::no($o) . ' isn\'t waiting for payment any more. Untick it; nothing was recorded.');
            if (($h = self::held($o)) !== '') throw new RuntimeException($h . ': untick it; nothing is recorded on it from here.');
            if ($owner !== null && self::ownerKey($o) !== $owner) throw new RuntimeException('One payment is one client\'s: order #' . Orders::no($o) . ' is someone else\'s. Nothing was recorded.');
            if ($side !== null && Orders::isTest($o) !== $side) throw new RuntimeException('Test and real orders never share a payment. Nothing was recorded.');
            $owner = self::ownerKey($o); $side = Orders::isTest($o);
            $r = self::row($pdo, $o, $today);
            if (!$r) throw new RuntimeException('Nothing is left to pay on order #' . Orders::no($o) . '. Untick it; nothing was recorded.');
            $rows[] = $r;
        }
        usort($rows, fn ($a, $b) => [$a['since'], $a['id']] <=> [$b['since'], $b['id']]);
        $al = self::allocate($cents, $rows);
        if ($al['over'] > 0) throw new RuntimeException('That\'s ' . Orders::usd($al['over']) . ' more than the ticked orders owe (' . Orders::usd($cents - $al['over']) . '). Tick another order or record the right amount: an overpayment isn\'t turned into a credit here. Nothing was recorded.');
        $grp = substr(hash('sha256', implode(',', $oids) . '|' . $cents . '|' . $method . '|' . $ref . '|' . $actor . '|' . date('YmdHi')), 0, 12);
        $n = count($al['parts']); $done = [];
        foreach ($rows as $r) {
            $c = (int) ($al['parts'][$r['id']] ?? 0);
            if ($c <= 0) continue;
            $done[] = self::recordOne($pdo, $r['id'], $c, $method, $ref, 'out-take-' . $grp . '-' . $r['id'], $cents, $n, $actor);
        }
        return $done;
    }

    /** One order's part of a payment, under the card payment's lock (as ClientOrders::applyQuickBooks). */
    private static function recordOne(PDO $pdo, int $oid, int $c, string $method, string $ref, string $key, int $whole, int $n, int $actor): array
    {
        $label = ClientOrders::PAY_METHODS[$method];
        $lock = $pdo->quote('tv-pay-' . $oid);
        if ((int) $pdo->query("SELECT GET_LOCK($lock, 20)")->fetchColumn() !== 1) return [$oid, 0, 'Order #' . $oid . ' was being paid at the same moment: nothing was recorded on it. Try it again.'];
        try {
            $o = Orders::get($pdo, $oid);
            if (!$o || $o['status'] !== 'pending') return [$oid, 0, 'Order #' . Orders::no($o ?: ['id' => $oid]) . ' was paid meanwhile: nothing was recorded on it.'];
            $left = (int) $o['total_cents'] - (int) Orders::money($pdo, $oid)['paid'];
            $c = min($c, $left);
            if ($c <= 0) return [$oid, 0, 'Nothing was left to pay on order #' . Orders::no($o) . '.'];
            $kind = $c < $left ? 'deposit' : 'charge';
            $part = $n > 1 ? 'part of one payment of ' . Orders::usd($whole) . ' across ' . $n . ' orders' : 'Take a payment';
            if (!Orders::record($pdo, $oid, $kind, $c, $key, $label, $ref, $part, $actor ?: null)) return [$oid, 0, 'Already recorded on order #' . Orders::no($o) . '.'];
            $pid = '';
            $inv = (string) ($o['invoice_id'] ?? '') ?: (string) ($o['qb_link_id'] ?? '');
            if ($inv !== '') {   // received on its QuickBooks invoice too, so it's never entered twice (0178 note (b)); guardReal holds inside
                try {
                    Orders::sameEnv($o);
                    require_once __DIR__ . '/QuickBooksPay.php';
                    $pid = QuickBooksPay::recordOtherPayment($pdo, ['invoice_id' => $inv, 'qb_customer_id' => (string) ($o['qb_customer_id'] ?? '') ?: (string) ($o['qb_link_customer'] ?? '')] + $o, $c, $label, $ref, $key);
                } catch (Throwable $e) {
                    Orders::event($pdo, $oid, 'needs_books', 'Receive ' . Orders::usd($c) . ' on QuickBooks invoice ' . ($o['invoice_no'] ?: $inv) . ' by hand: ' . mb_substr($e->getMessage(), 0, 200), $actor ?: null);
                }
                if ($pid !== '') { try { $pdo->prepare('UPDATE order_ledger SET qb_payment_id = ? WHERE order_id = ? AND request_key = ?')->execute([$pid, $oid, $key]); } catch (Throwable) {} }
            }
            // starts with the amount and a space, and names "(payment N)": Orders::payments() reads it that way
            Orders::event($pdo, $oid, 'payment_recorded', Orders::usd($c) . ' by ' . strtolower($label) . ($ref !== '' ? ' ' . $ref : '') . ', ' . $part . ($pid !== '' ? ' (payment ' . $pid . ')' : ''), $actor ?: null);
            return [$oid, $c, Orders::usd($c) . ' recorded on order #' . Orders::no($o) . ($pid !== '' ? ', received on its QuickBooks invoice' : '') . '.'];
        } finally { $pdo->query("SELECT RELEASE_LOCK($lock)"); }
    }

    // ---- Credits on account -----------------------------------------------------------------------------------------------

    /** The client's credit on the order's side (test or real), as Orders::creditBalance figures it. */
    public static function creditFor(PDO $pdo, array $o): int
    {
        $uid = (int) ($o['user_id'] ?? 0);
        return Orders::creditBalance($pdo, $uid > 0 ? $uid : null, (string) $o['bill_email'], Orders::isTest($o));
    }

    /** "Credit available: $X · Apply it" for an order (0178 §3), or null: ['available', 'apply']. Never applied by itself. */
    public static function suggestion(PDO $pdo, array $o): ?array
    {
        if (($o['status'] ?? '') !== 'pending' || (int) ($o['created_by'] ?? 0) <= 0 || self::held($o) !== '') return null;
        $left = (int) $o['total_cents'] - (int) Orders::money($pdo, (int) $o['id'])['paid'];
        if ($left <= 0) return null;
        $have = self::creditFor($pdo, $o);
        return $have > 0 ? ['available' => $have, 'apply' => min($have, $left)] : null;
    }

    /** Staff apply the client's credit to an order that owes money (0178 §3). Returns what happened, in words. */
    public static function applyCredit(PDO $pdo, int $oid, int $cents, int $actor): string
    {
        require_once __DIR__ . '/ClientOrders.php';
        $o = Orders::get($pdo, $oid);
        if (!$o || !ClientOrders::isClient($o)) throw new RuntimeException('That order isn\'t here.');
        if ($o['status'] !== 'pending') throw new RuntimeException('Credit is applied to an approved order that still owes money.');
        if (($h = self::held($o)) !== '') throw new RuntimeException($h . ': no credit is applied to it from here.');
        if ($cents <= 0) throw new RuntimeException('Enter how much of the credit to apply.');
        $cl = $pdo->quote('tv-credit-' . substr(hash('sha256', self::ownerKey($o)), 0, 32));
        $pl = $pdo->quote('tv-pay-' . $oid);
        if ((int) $pdo->query("SELECT GET_LOCK($cl, 20)")->fetchColumn() !== 1) throw new RuntimeException('This client\'s credit is being used at the same moment. Try again; nothing was applied.');
        try {
            if ((int) $pdo->query("SELECT GET_LOCK($pl, 20)")->fetchColumn() !== 1) throw new RuntimeException('The order is being paid at the same moment. Try again; nothing was applied.');
            try {
                $o = Orders::get($pdo, $oid);
                $left = (int) $o['total_cents'] - (int) Orders::money($pdo, $oid)['paid'];
                if ($o['status'] !== 'pending' || $left <= 0) throw new RuntimeException('Nothing is owed on order #' . Orders::no($o) . ' now.');
                $have = self::creditFor($pdo, $o);
                if ($cents > $have) throw new RuntimeException('The client has ' . Orders::usd($have) . ' of credit. Nothing was applied.');
                if ($cents > $left) throw new RuntimeException('Only ' . Orders::usd($left) . ' is owed on order #' . Orders::no($o) . '. Nothing was applied.');
                $key = 'credit-use-' . $oid . '-' . substr(hash('sha256', $cents . '|' . $actor . '|' . date('YmdHi')), 0, 12);
                if (!Orders::record($pdo, $oid, 'credit_used', $cents, $key, 'account credit', '', 'Applied by our team from the client\'s credit', $actor ?: null)) throw new RuntimeException('That credit is applied already.');
                Orders::event($pdo, $oid, 'credit_applied', Orders::usd($cents) . ' of the client\'s credit applied; ' . Orders::usd($have - $cents) . ' of credit left', $actor ?: null);
                $inv = (string) ($o['invoice_no'] ?? '');
                if ($inv !== '' || !empty($o['invoice_id']) || !empty($o['qb_link_id']))   // nothing is written to QuickBooks from here: its invoice still shows the old balance
                    Orders::event($pdo, $oid, 'needs_books', 'Apply ' . Orders::usd($cents) . ' of the client\'s credit memo to QuickBooks invoice ' . ($inv !== '' ? $inv : (string) ($o['invoice_id'] ?: $o['qb_link_id'])) . ' there (Receive payment, with the credit), so its balance matches the order', $actor ?: null);
                return Orders::usd($cents) . ' of credit applied to order #' . Orders::no($o) . '. ' . Orders::usd($have - $cents) . ' of credit left.';
            } finally { $pdo->query("SELECT RELEASE_LOCK($pl)"); }
        } finally { $pdo->query("SELECT RELEASE_LOCK($cl)"); }
    }

    /** Why an applied credit's record can't be cancelled now ('' when it can); as Orders::cancelRecordBlock for a payment. */
    public static function cancelCreditBlock(PDO $pdo, array $o, array $l): string
    {
        $oid = (int) $o['id'];
        if (in_array($o['status'], ['void', 'refunded'], true)) return 'The order is ' . ($o['status'] === 'void' ? 'cancelled' : 'refunded');
        if (Orders::disputeOpen($pdo, $oid)) return 'A card dispute is open on this order';
        if (!empty($o['received_at']) || ($o['stage'] ?? '') === 'complete') return 'The order was delivered after it';
        try {
            $st = $pdo->prepare("SELECT wo_no FROM order_work_orders WHERE order_id = ? AND sent_at IS NOT NULL AND sent_at >= ? AND status NOT IN ('cancelled', 'declined', 'rejected') LIMIT 1");
            $st->execute([$oid, (string) $l['created_at']]);
            if ($wo = $st->fetchColumn()) return 'Work order ' . $wo . ' went to the vendor after it';
        } catch (Throwable) {}
        return '';
    }

    /**
     * Cancel the record of a credit applied (applied by mistake): as if never applied. The ledger is only added to: a row in
     * order_payment_cancels takes it out; the credit is the client's again (Orders::creditRows leaves it out).
     */
    public static function cancelCredit(PDO $pdo, int $oid, int $ledgerId, string $why, int $actor): void
    {
        $o = Orders::get($pdo, $oid);
        if (!$o) throw new RuntimeException('That order isn\'t here.');
        $st = $pdo->prepare("SELECT * FROM order_ledger WHERE id = ? AND order_id = ? AND kind = 'credit_used'"); $st->execute([$ledgerId, $oid]);
        $l = $st->fetch(PDO::FETCH_ASSOC);
        if (!$l) throw new RuntimeException('That credit isn\'t applied on this order.');
        if (isset(Orders::cancels($pdo, $oid)[$ledgerId])) throw new RuntimeException('That credit\'s record is cancelled already.');
        if (($no = self::cancelCreditBlock($pdo, $o, $l)) !== '') throw new RuntimeException($no . ': the credit stays on the order.');
        if (trim($why) === '') throw new RuntimeException('Say why it\'s taken off (applied to the wrong order…).');
        try { $ins = $pdo->prepare('INSERT IGNORE INTO order_payment_cancels (order_id, ledger_id, amount_cents, why, actor_user_id) VALUES (?, ?, ?, ?, ?)'); $ins->execute([$oid, $ledgerId, (int) $l['amount_cents'], mb_substr(trim($why), 0, 400), $actor ?: null]); }
        catch (Throwable) { throw new RuntimeException('Cancel the record waits for the database update (2026-11-24_order_refunds.sql).'); }
        if (!$ins->rowCount()) throw new RuntimeException('That credit\'s record is cancelled already.');
        // back to where it stood: owing again (data_reason: the order's status follows its money, as Orders::cancelRecord does)
        $m = Orders::money($pdo, $oid); $o = Orders::get($pdo, $oid);
        if ($m['paid'] < (int) $o['total_cents'] && in_array($o['status'], ['paid', 'part_refunded'], true)) $pdo->prepare("UPDATE orders SET status = 'pending', paid_at = NULL WHERE id = ?")->execute([$oid]);
        $o = Orders::get($pdo, $oid);
        if (($o['stage'] ?? '') === 'production' && (int) ($o['created_by'] ?? 0) > 0) {
            require_once __DIR__ . '/ClientOrders.php';
            if ($m['paid'] < ClientOrders::releaseAt($o) && !ClientOrders::overridden($pdo, $oid)) $pdo->prepare("UPDATE orders SET stage = '' WHERE id = ?")->execute([$oid]);
        }
        Orders::event($pdo, $oid, 'credit_cancelled', 'The ' . Orders::usd((int) $l['amount_cents']) . ' of credit applied was cancelled: ' . trim($why) . '. The credit is the client\'s again', $actor ?: null);
        if (!empty($o['invoice_no']) || !empty($o['invoice_id']) || !empty($o['qb_link_id'])) Orders::event($pdo, $oid, 'needs_books', 'If the credit was applied to its QuickBooks invoice by hand, take it off there too', $actor ?: null);
    }

    /**
     * Credits on account: each client with credit on this side, its balance, when the next part runs out, and their open
     * orders it could go toward (Apply to an order). [[key, name, company, email, balance, expires, orders]].
     */
    public static function credits(PDO $pdo, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $test = self::testSide();
        $st = $pdo->query("SELECT CASE WHEN o.user_id IS NOT NULL THEN CONCAT('u', o.user_id) ELSE CONCAT('e', LOWER(o.bill_email)) END AS k, MAX(o.user_id) AS uid, MAX(LOWER(o.bill_email)) AS email,
            MAX(o.bill_name) AS name, MAX(o.client_company) AS company FROM order_ledger l JOIN orders o ON o.id = l.order_id WHERE l.kind = 'credit_issued'" . self::sideSql($test) . ' GROUP BY k LIMIT 500');
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $uid = (int) ($c['uid'] ?? 0);
            $lots = Orders::creditLots(Orders::creditRows($pdo, $uid > 0 ? $uid : null, (string) $c['email'], $test), time());
            $bal = (int) array_sum(array_column($lots, 0));
            if ($bal <= 0) continue;
            $open = [];
            foreach (self::openFor($pdo, ['user_id' => $uid ?: null, 'bill_email' => (string) $c['email'], 'qb_env' => $test ? 'sandbox' : ''], $today) as $r)
                if ($r['held'] === '') $open[] = $r + ['apply' => min($bal, $r['left'])];
            $out[] = ['key' => (string) $c['k'], 'name' => (string) $c['name'], 'company' => (string) ($c['company'] ?? ''), 'email' => (string) $c['email'],
                      'balance' => $bal, 'expires' => date('Y-m-d', (int) min(array_column($lots, 1))), 'orders' => $open];
        }
        usort($out, fn ($a, $b) => [$a['expires'], -$a['balance']] <=> [$b['expires'], -$b['balance']]);
        return $out;
    }
}
