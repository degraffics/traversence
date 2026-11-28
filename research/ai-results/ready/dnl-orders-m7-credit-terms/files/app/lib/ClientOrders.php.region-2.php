<?php
// ANCHOR: replaces     public static function shipHold(PDO $pdo, array $o): string through     }
// (the last line is shipHold's own closing brace, the first "    }" after its line
//  "        return 'Order #' . Orders::no($o) . ' isn\'t paid in full yet (' . Orders::usd($left) . ' still to pay), so it can\'t be released for delivery (shipped or ready for pickup). Record the payment when it comes in, or an admin can release it before payment, with a reason.';")
    public static function shipHold(PDO $pdo, array $o): string
    {
        $oid = (int) $o['id'];
        if (($o['pay_mode'] ?? '') === 'outside') return '';
        require_once __DIR__ . '/CreditTerms.php';
        if (CreditTerms::onTerms($pdo, $o)) return '';   // credit terms (decisions/0178 §4, M7): delivery doesn't wait; the balance is due by the terms' date
        $left = (int) $o['total_cents'] - Orders::money($pdo, $oid)['paid'];
        if ($left <= 0 || self::overridden($pdo, $oid)) return '';
        return 'Order #' . Orders::no($o) . ' isn\'t paid in full yet (' . Orders::usd($left) . ' still to pay), so it can\'t be released for delivery (shipped or ready for pickup). Record the payment when it comes in, or an admin can release it before payment, with a reason.';
    }
