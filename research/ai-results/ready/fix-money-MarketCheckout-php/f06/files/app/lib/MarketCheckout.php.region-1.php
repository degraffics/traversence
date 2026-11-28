<?php
// ANCHOR: replaces `    public static function cancel(PDO $pdo, int $uid, array $o): void` through `        if ($booking) { try { Bookings::changed($pdo, $o, 'customer', 'cancelled before paying; the dates are free again'); } catch (Throwable $x) { error_log('booking cancelled notify: ' . $x->getMessage()); } }` and the method's closing `    }` on the next line. The docblock above it stays.
// (The `<?php` line and this ANCHOR comment are not pasted.)
    public static function cancel(PDO $pdo, int $uid, array $o): void
    {
        if ((int) $o['buyer_id'] !== $uid) throw new InvalidArgumentException('That order isn\'t yours.');
        $o = self::get($pdo, (int) $o['id']) ?? $o;   // read fresh: what it is now decides (f06)
        // A card order still being paid has an open payment at the gateway. Cancelling here wouldn't close that payment, so a
        // later payment would land on a cancelled order. It's refused; the order expires by itself after its hold (f06).
        if ($o['status'] === 'pending' && $o['method'] === 'card') throw new InvalidArgumentException('Its card payment is still open, so it can\'t be cancelled here. If you don\'t pay, it\'s released on its own within ' . self::CARD_HOLD_MIN . ' minutes of placing it. If you were charged, write to support@traversence.com with order ' . (int) $o['id'] . '.');
        // data_reason: f06. The buyer cancels an unpaid order waiting for a direct payment. Card orders being paid are excluded.
        $st = $pdo->prepare("UPDATE market_orders SET status = 'cancelled', updated_at = ? WHERE id = ? AND (status = 'awaiting' OR (status = 'pending' AND method <> 'card'))");
        $st->execute([self::now(), (int) $o['id']]);
        if (!$st->rowCount()) throw new InvalidArgumentException('It can\'t be cancelled now.');
        require_once __DIR__ . '/Bookings.php';
        $booking = Bookings::of($o) !== null;   // a stay or a rental: the team is told as a booking (biz.booking, decisions/0172 §2.5), once
        if ($o['biz_quote_id']) self::notify($pdo, $o, 'Order #' . (int) $o['id'] . ' was cancelled by the buyer before paying. Void it in Quotes & invoices.', $booking ? '' : 'biz.order');
        if ($booking) { try { Bookings::changed($pdo, $o, 'customer', 'cancelled before paying; the dates are free again'); } catch (Throwable $x) { error_log('booking cancelled notify: ' . $x->getMessage()); } }
    }
