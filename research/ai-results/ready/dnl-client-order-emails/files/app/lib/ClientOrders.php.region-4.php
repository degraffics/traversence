<?php
// ANCHOR: replaces     public static function invoiceLaterChoose(PDO $pdo, int $oid, string $choice, string $why, int $staff): string through the first line "    }" after it (the line before it is:         return 'Order #' . Orders::no($o) . ' is Pay in full now; ' . $o['bill_name'] . ' was emailed that ' . Orders::usd($left) . ' is due, with the link to pay.';)
    public static function invoiceLaterChoose(PDO $pdo, int $oid, string $choice, string $why, int $staff): string
    {
        $o = Orders::get($pdo, $oid);
        if (!$o || !self::isClient($o) || ($o['pay_mode'] ?? '') !== 'invoice') throw new RuntimeException('That order isn\'t on invoice-later terms any more: it\'s been chosen for.');
        if ($choice === 'release') {
            if ($o['status'] !== 'pending') throw new RuntimeException('Order #' . Orders::no($o) . ' isn\'t approved yet: send it as an invoice now, and release it once it\'s approved if it should go ahead before payment.');
            self::releaseHold($pdo, $oid, $staff, $why);
            return 'Order #' . Orders::no($o) . ' is released before payment; it keeps its invoice-later terms.';
        }
        if ($choice !== 'invoice') throw new RuntimeException('Choose what happens to it.');
        $pdo->prepare("UPDATE orders SET pay_mode = 'full' WHERE id = ?")->execute([$oid]);
        Orders::event($pdo, $oid, 'terms', 'Invoice later ended: paid in full now (decisions/0101 note 2026-11-23)', $staff);
        if ($o['status'] !== 'pending') return 'Order #' . Orders::no($o) . ' is Pay in full now: it goes to the client that way when it\'s sent.';
        $left = max(0, (int) $o['total_cents'] - Orders::money($pdo, $oid)['paid']);
        self::tell($pdo, $o, 'order.invoice', 'invoice-due', 'Order #' . Orders::no($o) . ': your invoice is due now', 'Hello ' . $o['bill_name'] . ",\n\nOrder #" . Orders::no($o) . "'s invoice" . ($o['invoice_no'] ? ' (' . $o['invoice_no'] . ')' : '') . ' is due now: '
            . Orders::usd($left) . ". We start printing once it's paid.\n\nSee it and pay here:\n" . self::url($o) . "\n\nQuestions? Just reply.");
        return 'Order #' . Orders::no($o) . ' is Pay in full now; ' . $o['bill_name'] . ' was emailed that ' . Orders::usd($left) . ' is due, with the link to pay.';
    }
