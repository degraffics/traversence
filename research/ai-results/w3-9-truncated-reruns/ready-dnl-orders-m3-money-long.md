=== FILE: files/app/lib/OrderMoney.php ===
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
=== END FILE ===
=== FILE: files/app/lib/Orders.php.region-1.php ===
<?php
// ANCHOR: replaces     /** What the ledger adds up to for an order: paid in, refunded, credited, disputed, and the net kept. */ through         if ($status !== $o['status']) $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $oid]); (and the "    }" closing settle() right after it)
// Covers money(), cancelledCents(), cancels() (unchanged) and settle(); adds creditCancelledCents(). Orders M3.

    /** What the ledger adds up to for an order: paid in, refunded, credited, disputed, and the net kept. */
    public static function money(PDO $pdo, int $oid): array
    {
        $st = $pdo->prepare('SELECT kind, SUM(amount_cents) AS c FROM order_ledger WHERE order_id = ? GROUP BY kind'); $st->execute([$oid]);
        $k = array_fill_keys(array_keys(self::KINDS), 0);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $k[$r['kind']] = (int) $r['c'];
        // credit from the client's account (credit_used, applied by staff, M3, 0178 §3) pays toward what's owed, so 'paid'
        // counts it; it never came in by card or check, so 'cash' and 'net' (what a refund can send back) don't
        $used = max(0, $k['credit_used'] - self::creditCancelledCents($pdo, $oid));
        $cash = $k['charge'] + $k['deposit'] - self::cancelledCents($pdo, $oid);   // a cancelled record is as if never recorded (0178's note)
        $net = $cash - $k['refund'] - $k['chargeback'] + $k['chargeback_reversed'];
        return ['paid' => $cash + $used, 'cash' => $cash, 'refunded' => $k['refund'], 'credited' => $k['credit_issued'], 'credit_used' => $used,
                'disputed' => max(0, $k['chargeback'] - $k['chargeback_reversed']), 'net' => $net, 'by_kind' => $k];
    }

    /** What Cancel the record has taken off an order's payments (charge, deposit; order_payment_cancels, M2); 0 before its migration. */
    public static function cancelledCents(PDO $pdo, int $oid): int
    {
        try { $st = $pdo->prepare("SELECT COALESCE(SUM(c.amount_cents), 0) FROM order_payment_cancels c JOIN order_ledger l ON l.id = c.ledger_id WHERE c.order_id = ? AND l.kind IN ('charge', 'deposit')"); $st->execute([$oid]); return (int) $st->fetchColumn(); }
        catch (Throwable) { return 0; }
    }

    /** What Cancel the record has taken off credit applied to an order (credit_used, M3); 0 before its migration. */
    public static function creditCancelledCents(PDO $pdo, int $oid): int
    {
        try { $st = $pdo->prepare("SELECT COALESCE(SUM(c.amount_cents), 0) FROM order_payment_cancels c JOIN order_ledger l ON l.id = c.ledger_id WHERE c.order_id = ? AND l.kind = 'credit_used'"); $st->execute([$oid]); return (int) $st->fetchColumn(); }
        catch (Throwable) { return 0; }
    }

    /** An order's cancelled payment records, by the ledger row each takes off. */
    public static function cancels(PDO $pdo, int $oid): array
    {
        try { $st = $pdo->prepare('SELECT * FROM order_payment_cancels WHERE order_id = ?'); $st->execute([$oid]); $out = []; foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $out[(int) $c['ledger_id']] = $c; return $out; }
        catch (Throwable) { return []; }
    }

    /** The status that follows from the money, for a paid order (drafts, waiting and cancelled orders keep theirs). */
    private static function settle(PDO $pdo, int $oid): void
    {
        $o = self::get($pdo, $oid);
        if (!$o || in_array($o['status'], ['draft', 'sent', 'void'], true)) return;
        $m = self::money($pdo, $oid);
        if ($m['paid'] <= 0) return;
        if ($o['status'] === 'pending' && $m['paid'] < (int) $o['total_cents']) return;   // a deposit: waiting for the rest (decisions/0101)
        $open = self::disputeOpen($pdo, $oid);
        // refunded only when nothing is kept at all: credit applied from the account (M3) is kept, so an order paid by credit is paid
        $status = $open ? 'disputed' : ($m['net'] + $m['credit_used'] <= 0 ? 'refunded' : ($m['refunded'] > 0 ? 'part_refunded' : 'paid'));
        if ($status !== $o['status']) $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $oid]);
    }
=== END FILE ===
=== FILE: files/app/lib/Orders.php.region-2.php ===
<?php
// ANCHOR: replaces     public static function creditBalance(PDO $pdo, ?int $uid, string $email): int through         return (int) array_sum(array_column($lots, 0)); (and the "    }" closing creditBalance() right after it)
// The docblock above creditBalance() stays as it is. Adds creditRows() and creditLots() (pure, tested). Orders M3.

    public static function creditBalance(PDO $pdo, ?int $uid, string $email, ?bool $test = null): int
    {
        // $test (M3): only sandbox orders' credit (true), only real orders' (false), or both (null, as before). A credit applied
        // and then cancelled (Cancel the record) is as if never used.
        return (int) array_sum(array_column(self::creditLots(self::creditRows($pdo, $uid, $email, $test), time()), 0));
    }

    /** The credit given and used for a customer, oldest first, leaving out applied credit whose record was cancelled. */
    public static function creditRows(PDO $pdo, ?int $uid, string $email, ?bool $test = null): array
    {
        $env = $test === null ? '' : ($test ? " AND o.qb_env = 'sandbox'" : " AND COALESCE(o.qb_env, '') <> 'sandbox'");
        $base = "SELECT l.id, l.kind, l.amount_cents, l.created_at FROM order_ledger l JOIN orders o ON o.id = l.order_id
                WHERE l.kind IN ('credit_issued', 'credit_used') AND " . ($uid ? 'o.user_id = ?' : 'o.user_id IS NULL AND LOWER(o.bill_email) = LOWER(?)') . $env;
        $order = ' ORDER BY l.created_at, l.id';
        try { $st = $pdo->prepare($base . ' AND NOT EXISTS (SELECT 1 FROM order_payment_cancels c WHERE c.ledger_id = l.id)' . $order); $st->execute([$uid ?: $email]); }
        catch (Throwable) { $st = $pdo->prepare($base . $order); $st->execute([$uid ?: $email]); }   // before 2026-11-24_order_refunds.sql
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * What's left of each credit at $now: [[cents left, good until (timestamp)], …]. Each credit lasts CREDIT_DAYS; what's
     * used comes off the oldest first, and what's left of a credit past its date is gone. Rows: kind, amount_cents, created_at.
     */
    public static function creditLots(array $rows, int $now): array
    {
        $lots = [];   // [cents left, good until]
        $gone = function (int $at) use (&$lots) { foreach ($lots as $i => $c) if ($c[1] < $at) unset($lots[$i]); };
        foreach ($rows as $r) {
            $at = strtotime((string) $r['created_at']) ?: $now;
            $gone($at);
            if ($r['kind'] === 'credit_issued') { $lots[] = [(int) $r['amount_cents'], strtotime('+' . self::CREDIT_DAYS . ' days', $at)]; continue; }
            $use = (int) $r['amount_cents'];
            foreach ($lots as $i => $c) { if ($use <= 0) break; $take = min($use, $c[0]); $lots[$i][0] -= $take; $use -= $take; }
        }
        $gone($now);
        return array_values(array_filter($lots, fn ($c) => $c[0] > 0));
    }
=== END FILE ===
=== FILE: tests/orders-m3-money.php ===
<?php
// tools/orders-tests/m3-money.php — Orders M3 (the Money section): plain checks, PASS/FAIL per line, exit 1 on a FAIL.
// The first part needs no database. The second runs only with TV_ORDERS_DB=1 and QB_ENV=mock (QuickBooksPay::env() must
// read 'mock': no network, no real money; it seeds sandbox orders with no invoice, so nothing reaches QuickBooks).
// DRAFT: never run.
declare(strict_types=1);

$root = rtrim((string) (getenv('TV_ROOT') ?: dirname(__DIR__, 2)), '/');
require_once $root . '/app/lib/Orders.php';
require_once $root . '/app/lib/OrderMoney.php';

$fails = 0;
$ok = function (string $what, bool $pass) use (&$fails): void { echo ($pass ? 'PASS' : 'FAIL') . '  ' . $what . "\n"; if (!$pass) $fails++; };

// ---- aging --------------------------------------------------------------------------------------------------------------
$ok('bucket: not past due is current', OrderMoney::bucket(0) === 'current' && OrderMoney::bucket(-5) === 'current');
$ok('bucket: 1 and 30 are 1–30', OrderMoney::bucket(1) === 'd1_30' && OrderMoney::bucket(30) === 'd1_30');
$ok('bucket: 31 and 60 are 31–60', OrderMoney::bucket(31) === 'd31_60' && OrderMoney::bucket(60) === 'd31_60');
$ok('bucket: 61 and 90 are 61–90', OrderMoney::bucket(61) === 'd61_90' && OrderMoney::bucket(90) === 'd61_90');
$ok('bucket: 91 is 90+', OrderMoney::bucket(91) === 'd90');
$ok('daysPast counts whole days', OrderMoney::daysPast('2026-11-01', '2026-11-27') === 26);
$ok('daysPast is negative before the due date', OrderMoney::daysPast('2026-12-10', '2026-11-27') < 0);

// ---- allocation (0177 §4: deposits first, then balances, oldest first) ---------------------------------------------------
$rows = [['id' => 1, 'deposit' => 0, 'left' => 500], ['id' => 2, 'deposit' => 300, 'left' => 1000]];
$a = OrderMoney::allocate(600, $rows);
$ok('allocate: the younger order\'s deposit before the older balance', ($a['parts'][2] ?? 0) === 300 && ($a['parts'][1] ?? 0) === 300 && $a['over'] === 0);
$a = OrderMoney::allocate(1500, $rows);
$ok('allocate: exactly what\'s owed, nothing over', ($a['parts'][1] ?? 0) === 500 && ($a['parts'][2] ?? 0) === 1000 && $a['over'] === 0);
$a = OrderMoney::allocate(2000, $rows);
$ok('allocate: more than owed says how much over', $a['over'] === 500);
$a = OrderMoney::allocate(200, [['id' => 7, 'deposit' => 900, 'left' => 150]]);
$ok('allocate: a deposit never above what\'s left', ($a['parts'][7] ?? 0) === 150 && $a['over'] === 50);
$a = OrderMoney::allocate(100, [['id' => 3, 'deposit' => 0, 'left' => 400], ['id' => 4, 'deposit' => 0, 'left' => 400]]);
$ok('allocate: balances oldest first', ($a['parts'][3] ?? 0) === 100 && !isset($a['parts'][4]));

// ---- credit lots (90 days, oldest used first) -----------------------------------------------------------------------------
$d = fn (int $day) => date('Y-m-d H:i:s', strtotime('2026-01-01 12:00:00 +' . $day . ' days'));
$t = fn (int $day) => strtotime('2026-01-01 12:00:00 +' . $day . ' days');
$credit = [['kind' => 'credit_issued', 'amount_cents' => 10000, 'created_at' => $d(0)], ['kind' => 'credit_used', 'amount_cents' => 3000, 'created_at' => $d(10)],
           ['kind' => 'credit_issued', 'amount_cents' => 5000, 'created_at' => $d(20)], ['kind' => 'credit_used', 'amount_cents' => 8000, 'created_at' => $d(30)]];
$sum = fn (array $lots) => (int) array_sum(array_column($lots, 0));
$ok('credit: used from the oldest credit first', $sum(Orders::creditLots($credit, $t(31))) === 4000);
$ok('credit: the first lot running out leaves the second', $sum(Orders::creditLots($credit, $t(95))) === 4000);
$ok('credit: past 90 days it\'s gone', $sum(Orders::creditLots($credit, $t(115))) === 0);
$late = [['kind' => 'credit_issued', 'amount_cents' => 1000, 'created_at' => $d(0)], ['kind' => 'credit_used', 'amount_cents' => 500, 'created_at' => $d(100)]];
$ok('credit: never below zero after it ran out', $sum(Orders::creditLots($late, $t(101))) === 0);

// ---- Order #12 is held (its books wait for Jason) ---------------------------------------------------------------------------
$ok('held: real order #12 is held', OrderMoney::held(['id' => 12, 'order_no' => 12, 'qb_env' => 'production']) !== '');
$ok('held: sandbox S-12 isn\'t', OrderMoney::held(['id' => 12, 'test_no' => 12, 'qb_env' => 'sandbox']) === '');
$ok('held: another real order isn\'t', OrderMoney::held(['id' => 40, 'order_no' => 13, 'qb_env' => 'production']) === '');

// ---- with the database (opt-in) ---------------------------------------------------------------------------------------------
if (getenv('TV_ORDERS_DB') !== '1') { echo "SKIP  database checks (set TV_ORDERS_DB=1 and QB_ENV=mock on the test harness)\n"; exit($fails ? 1 : 0); }
putenv('QB_ENV=mock');
require_once $root . '/app/lib/Database.php';
require_once $root . '/app/lib/QuickBooksPay.php';
if (QuickBooksPay::env() !== 'mock') { echo "SKIP  database checks: QuickBooks isn't in mock mode here, so nothing runs (no real money, ever)\n"; exit($fails ? 1 : 0); }
$pdo = Database::connection();
$email = 'm3-test-' . bin2hex(random_bytes(4)) . '@example.invalid';
$ids = [];
$seed = function (string $status, int $total) use ($pdo, $email, &$ids): int {
    $pdo->prepare("INSERT INTO orders (status, bill_name, bill_email, created_by, pay_mode, total_cents, subtotal_cents, qb_env, access_token) VALUES (?, 'TEST · M3', ?, 1, 'full', ?, ?, 'sandbox', ?)")
        ->execute([$status, $email, $total, $total, bin2hex(random_bytes(20))]);
    return $ids[] = (int) $pdo->lastInsertId();
};
try {
    $p = $seed('pending', 10000);
    Orders::record($pdo, $p, 'charge', 10000, 'm3t-pay-' . $p, 'Check');
    Orders::record($pdo, $p, 'credit_issued', 3000, 'm3t-cr-' . $p, 'account credit');
    $a = $seed('pending', 5000); $b = $seed('pending', 4000);
    $ok('db: the credit shows on the client\'s sandbox side', Orders::creditBalance($pdo, null, $email, true) === 3000);
    $ok('db: and not on the real side', Orders::creditBalance($pdo, null, $email, false) === 0);
    OrderMoney::applyCredit($pdo, $a, 2000, 0);
    $ok('db: applied credit counts as paid', Orders::money($pdo, $a)['paid'] === 2000);
    $ok('db: applied credit can\'t be refunded to a card', Orders::refundable($pdo, $a) === 0);
    $ok('db: the credit left is 1000', Orders::creditBalance($pdo, null, $email, true) === 1000);
    $threw = false; try { OrderMoney::applyCredit($pdo, $b, 2000, 0); } catch (RuntimeException) { $threw = true; }
    $ok('db: more credit than there is is refused', $threw && Orders::money($pdo, $b)['paid'] === 0);
    $lid = (int) $pdo->query("SELECT id FROM order_ledger WHERE order_id = $a AND kind = 'credit_used' ORDER BY id DESC LIMIT 1")->fetchColumn();
    OrderMoney::cancelCredit($pdo, $a, $lid, 'Applied to the wrong order', 0);
    $ok('db: cancelling the record gives the credit back', Orders::creditBalance($pdo, null, $email, true) === 3000 && Orders::money($pdo, $a)['paid'] === 0);
    $threw = false; try { OrderMoney::takePayment($pdo, [$a, $b], 9500, 'check', '1001', 0); } catch (RuntimeException) { $threw = true; }
    $ok('db: an overpayment is refused, nothing recorded', $threw && Orders::money($pdo, $a)['paid'] === 0 && Orders::money($pdo, $b)['paid'] === 0);
    $done = OrderMoney::takePayment($pdo, [$b, $a], 9000, 'check', '1001', 0);
    $ok('db: one payment pays both orders', array_sum(array_column($done, 1)) === 9000 && Orders::get($pdo, $a)['status'] === 'paid' && Orders::get($pdo, $b)['status'] === 'paid');
    $again = OrderMoney::takePayment(...[$pdo, [$a, $b], 1, 'check', '1002', 0]) ?? null;
} catch (Throwable $e) {
    $ok('db: no error (' . get_class($e) . ': ' . $e->getMessage() . ')', $e instanceof RuntimeException && str_contains($e->getMessage(), 'isn\'t waiting for payment'));
} finally {
    // data_reason: removes only the rows this test seeded (its own sandbox orders, by id)
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        foreach (['order_payment_cancels', 'order_ledger', 'order_events', 'order_lines'] as $tb) { try { $pdo->exec("DELETE FROM $tb WHERE order_id IN ($in)"); } catch (Throwable) {} }
        $pdo->exec("DELETE FROM orders WHERE id IN ($in)");
    }
}
exit($fails ? 1 : 0);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
### Can the credit on my account pay toward an order? (coming)

Yes. A credit we give you is good for 90 days. When you have credit and an order that still owes money, our team
applies it for you: ask us, or we'll offer it when we invoice you. It's never taken off by itself. The order then shows
the credit beside your payments, and what's left to pay.

### Can I pay for several orders at once? (coming)

Yes. Send one check, wire or transfer and tell us it covers your open orders. We record it across them: deposits first,
then what's left on each, oldest first. Each order shows its share. If it's more than you owe, we'll get in touch
rather than keep the difference.

### Why does my QuickBooks invoice still show a balance after my credit was applied? (coming)

Your order page is the one to go by. Our team also applies the credit to the QuickBooks invoice, usually the same day,
and then the two match. Questions: support@traversence.com.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "orders-m3-money",
  "gate": "Money (decisions/0180's hard money line): the session manager sets the tier; Design reviewer at 1280 and 390, then Jason's pop-up before it ships",
  "what": "Orders M3, the library half of Back-Office → Money: Outstanding with aging (current, 1–30, 31–60, 61–90, 90+, due later) by client; Take a payment across one client's orders (deposits first, then balances, oldest first, 0177 §4), each part received on its QuickBooks invoice when it has one (0178 note (b)); Credits on account and their use (credit_used written for the first time, 0178 §3), corrected by Cancel the record, never Undo. Orders::money now counts applied credit as paid, never as refundable. SKIPPED, said first: (1) M2b ('how an order goes', paused by Jason 2026-11-24): nothing here builds on it. (2) Order #12's books (waiting for Jason, 'after holiday', 2026-11-27): OrderMoney::HELD lists it in Outstanding but refuses any payment or credit on it from here. (3) The attached job 19 draft was empty: this was drafted from the decisions and live files alone. (4) Open Work-queue parents are in the live database, not attached: not checked. Not in this item and not drafted: Refunds & credit memos view (design §4.4 lists it under M3; the item doesn't, and refunds/credit memos wait for Jason), Remind on Outstanding, credit terms (M7). Not drafted: the page itself (see verify).",
  "files": [
    "files/app/lib/OrderMoney.php (new)",
    "files/app/lib/Orders.php.region-1.php (money, cancelledCents, new creditCancelledCents, cancels unchanged, settle)",
    "files/app/lib/Orders.php.region-2.php (creditBalance gains ?bool $test; new creditRows, creditLots)",
    "tests/orders-m3-money.php (to tools/orders-tests/m3-money.php)",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "research/order-management-design-2026-11-23.md §4.4, §6 M3 (approved as the plan, Jason, 2026-11-23)",
    "decisions/0177 §4: a payment goes to deposits first, then balances, oldest first",
    "decisions/0178 §3: credit on account: staff apply it, the system suggests it (OrderMoney::suggestion), never by itself",
    "decisions/0178 money note (2026-11-23): money never has Undo; a recorded, unprocessed entry has Cancel the record. 0178 §3 says 'with Undo'; the later standing rule wins, so an applied credit has Cancel the record. Session manager to confirm.",
    "decisions/0178 note (b): a payment recorded here is received on its linked QuickBooks invoice (QuickBooksPay::recordOtherPayment, guardReal inside)",
    "decisions/0178 §5: no keyed cards: Take a payment records money that came in; it never charges a card, and Wallet's saved cards aren't used",
    "decisions/0178 note of 2026-11-24: credit memos wait for Jason, so an overpayment is refused, never turned into credit",
    "decisions/0098: credits last 90 days, used oldest first; the ledger is only added to (a cancel is a row in order_payment_cancels)",
    "handoffs/decided-not-live.md: M2b paused; Order #12's books wait for Jason; M3 is queue item 2 after item 0 (the order tests green again)"
  ],
  "tests": [
    "tools/orders-tests/m3-money.php: aging buckets and days past due; allocation (deposits first, oldest first, overpayment measured); credit lots (oldest first, 90-day expiry, never negative); Order #12 held, S-12 not. Opt-in DB part (TV_ORDERS_DB=1, QB_ENV=mock, sandbox seeds with no invoice): credit sides kept apart, apply, refuse over-apply, applied credit not refundable, Cancel the record restores it, overpayment refused with nothing recorded, one payment across two orders"
  ],
  "risks": [
    "Nothing here was run, tested, linted or zip-checked: it is a DRAFT for the lane session.",
    "Orders::money()['paid'] now includes applied credit: every reader (shipHold, vendorHold, dueNow, boardState, completeHold, applyQuickBooks, cancelRecord) sees credit as paid. Intended, but each needs a look; 'cash' is added for anything that must mean money in hand.",
    "cancelledCents() now counts only charge/deposit cancels (it counted every cancel; before M3 only those existed). Any caller outside Orders.php not shown.",
    "settle(): 'refunded' now needs net + credit_used <= 0, so an order paid by credit settles as paid. A mixed order fully refunded in cash but with credit applied reads part_refunded.",
    "Nothing is written to QuickBooks when credit is applied: its invoice keeps the old balance until staff apply the credit memo there (a needs_books event says so). If QuickBooks' 'Automatically apply credits' is on, QuickBooks may apply the same credit memo itself: double use. Check that setting.",
    "The client's pay link is QuickBooks' invoice page on some orders: after credit is applied here and before it's applied in QuickBooks, a client paying there could overpay.",
    "Take a payment records with Orders::record directly. The per-order Record the payment in ClientOrders.php is past the attachment's truncation, so whatever else it does (paid_at, GO Codes going live, stage, receipt email) is not done here.",
    "Each order's part is recorded on its own, not in one transaction (QuickBooks calls can't sit in one). A failure part way leaves the earlier parts recorded, said per order in the result.",
    "dueSince() calls ClientOrders::dueDate() only if it is public (is_callable). Otherwise it falls back to approved_at/sent_at/created_at plus the terms' days, which may disagree with the board's past due.",
    "Outstanding calls ClientOrders::boardState() per order (up to 500): heavy on a long list.",
    "OrderMoney::HELD hard-codes Order #12 by its number: remove the entry when Jason answers.",
    "The DB test's seed INSERT guesses which orders columns are required; item 0's harness owns the real schema."
  ],
  "verify": [
    "The Back-Office → Money page, its route, the Take a payment drawer, the order page's 'Credit available: $X · Apply it' line, and the API actions: file not shown (admin/backoffice router, tools.php, tv_admin_start, client-order.php, /api/admin/client-order.php). Not drafted; build them on OrderMoney::outstanding/openFor/takePayment/credits/suggestion/applyCredit/cancelCredit.",
    "Who may take a payment and apply credit (Access::can key and rule): admin/Access.php not shown; the per-order record_pay check should be reused.",
    "ClientOrders.php's record-payment path, received(), dueDate() visibility, releaseAt(): file shown only in part (truncated); confirm the takePayment parts match what record_pay does.",
    "Orders.php is about 62 KB: regions:true. Check both ANCHOR first and last lines against live before replacing.",
    "orders.approved_at, orders.paid_at, order_ledger.qb_payment_id, orders.qb_link_customer exist on live (paid_at and qb_payment_id are used by live code; approved_at isn't seen on orders).",
    "order_payment_cancels has a unique key on ledger_id (live cancelRecord relies on INSERT IGNORE + rowCount).",
    "admin/order.php reads money()['credit_used'] and creditBalance(): file not shown; check they still read right.",
    "No migration in this release: it uses order_payment_cancels (2026-11-24_order_refunds.sql) and existing ledger kinds.",
    "Item 0 (the order tests green again) should land first so the DB part of the test has its harness.",
    "QuickBooks' 'Automatically apply credits' setting, in the sandbox first.",
    "Jason/session manager: Cancel the record (not Undo) for an applied credit, against 0178 §3's wording."
  ],
  "regions": true
}
=== END FILE ===