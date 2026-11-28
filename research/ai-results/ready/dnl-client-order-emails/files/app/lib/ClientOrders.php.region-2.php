<?php
// ANCHOR: replaces     public static function cancel(PDO $pdo, int $oid, string $why, int $staff): void through the first line "    }" after it (the line before it is:         if ($o['status'] !== 'draft') self::mail($o, 'Your Traversence order was cancelled', 'Hello ' . $o['bill_name'] . ",\n\nOrder #" . Orders::no($o) . ' was cancelled' . ($why !== '' ? ': ' . $why : '') . '. Nothing was charged.');)
    public static function cancel(PDO $pdo, int $oid, string $why, int $staff): void
    {
        $o = Orders::get($pdo, $oid);
        if (!$o || !self::isClient($o)) throw new RuntimeException('That order isn\'t here.');
        if (($no = self::cancelBlock($pdo, $o)) !== '') throw new RuntimeException($no . '.');
        if (Orders::money($pdo, $oid)['paid'] > 0) throw new RuntimeException('Something has been paid on this order: Cancel the order… prepares its refund for Jason\'s OK, and it\'s cancelled when he approves it.');
        $pdo->prepare("DELETE FROM qr_issued WHERE order_id = ? AND status = 'pending'")->execute([$oid]);
        self::stopUnaccepted($pdo, $oid, $why, $staff);
        Orders::cancelUnpaid($pdo, $oid, $why !== '' ? $why : 'Cancelled', $staff);
        if ($o['status'] !== 'draft') self::tell($pdo, $o, 'order.client', 'cancelled', 'Your Traversence order was cancelled', 'Hello ' . $o['bill_name'] . ",\n\nOrder #" . Orders::no($o) . ' was cancelled' . ($why !== '' ? ': ' . $why : '') . '. Nothing was charged.');
    }
