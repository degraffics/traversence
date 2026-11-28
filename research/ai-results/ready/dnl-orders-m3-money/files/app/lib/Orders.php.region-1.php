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
