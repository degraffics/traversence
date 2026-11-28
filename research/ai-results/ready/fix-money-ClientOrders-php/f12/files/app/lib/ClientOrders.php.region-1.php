<?php
// ANCHOR: replaces |    private static function applyQuickBooks(PDO $pdo, int $oid, array $pays): string|
// through |    /** The newest payment applied from a linked QuickBooks invoice (readQuickBooks), or null: the Money line says it. */|
// (the lane pastes everything below this comment in its place; the last line is kept as it was)
    private static function applyQuickBooks(PDO $pdo, int $oid, array $pays): string
    {
        $lock = $pdo->quote('tv-pay-' . $oid);
        if ((int) $pdo->query("SELECT GET_LOCK($lock, 20)")->fetchColumn() !== 1) return '';
        try {
            // a payment recorded here and posted to the linked invoice (recordPayment → QuickBooksPay::recordOtherPayment) keeps
            // QuickBooks' id in order_ledger.qb_payment_id: read back from the invoice it's skipped even without our mark (f12)
            $posted = self::hasLedgerQbId($pdo) ? $pdo->prepare('SELECT 1 FROM order_ledger WHERE order_id = ? AND qb_payment_id = ? LIMIT 1') : null;
            $got = 0;
            foreach ($pays as $p) {
                if (!empty($p['ours']) || (int) ($p['cents'] ?? 0) <= 0 || (string) ($p['id'] ?? '') === '') continue;
                $key = 'qbpay-' . $oid . '-' . $p['id'];   // the ledger's keys are unique across orders
                $seen = $pdo->prepare('SELECT 1 FROM order_ledger WHERE order_id = ? AND request_key = ?'); $seen->execute([$oid, $key]);
                if ($seen->fetchColumn()) continue;   // applied once: twice is nothing
                if ($posted) { $posted->execute([$oid, (string) $p['id']]); $mine = $posted->fetchColumn(); $posted->closeCursor(); if ($mine) continue; }   // posted from here: already in the ledger
                $o = Orders::get($pdo, $oid);
                if (!$o || $o['status'] !== 'pending') break;
                $left = (int) $o['total_cents'] - (int) Orders::money($pdo, $oid)['paid'];
                if ($left <= 0) break;
                $c = min((int) $p['cents'], $left);
                $ref = trim(strtolower((string) ($p['method'] ?? '')) . ' ' . (string) ($p['ref'] ?? ''));
                self::received($pdo, $oid, $c, $c < $left ? 'deposit' : 'charge', 'QuickBooks', $ref, $key, false);
                if (($d = strtotime((string) ($p['date'] ?? ''))) && $d <= time()) $pdo->prepare('UPDATE order_ledger SET created_at = ? WHERE order_id = ? AND request_key = ?')->execute([date('Y-m-d 12:00:00', $d), $oid, $key]);   // when it was paid
                if ((int) $p['cents'] > $c) Orders::event($pdo, $oid, 'needs_books', 'QuickBooks shows ' . Orders::usd((int) $p['cents']) . ' paid on invoice ' . $o['invoice_no'] . '; only ' . Orders::usd($c) . ' was left on this order, so that much is recorded. Check the order\'s lines against the invoice.');
                $got += $c;
            }
            return $got ? Orders::usd($got) . ' paid in QuickBooks, recorded' : '';
        } finally { $pdo->query("SELECT RELEASE_LOCK($lock)"); }
    }

    /** Has order_ledger a qb_payment_id column (a payment this site posted to QuickBooks keeps QuickBooks' id there)? */
    private static function hasLedgerQbId(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $pdo->query('SELECT qb_payment_id FROM order_ledger LIMIT 0'); $ok = true; }
            catch (Throwable) { $ok = false; error_log('applyQuickBooks: order_ledger.qb_payment_id missing; payments posted from here are told apart by their mark only'); }
        }
        return $ok;
    }

    /** The newest payment applied from a linked QuickBooks invoice (readQuickBooks), or null: the Money line says it. */
