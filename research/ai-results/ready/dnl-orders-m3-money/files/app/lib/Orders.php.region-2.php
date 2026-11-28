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
