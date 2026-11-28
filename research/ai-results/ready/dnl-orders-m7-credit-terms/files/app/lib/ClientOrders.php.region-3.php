<?php
// ANCHOR: replaces     public static function vendorHold(PDO $pdo, array $o): string through     }
// (the last line is vendorHold's own closing brace, the first "    }" after its line
//  "        return 'Order #' . Orders::no($o) . ' goes to the vendor once ' . ($dep ? 'its deposit is paid' : 'it\'s paid in full') . ' (' . Orders::usd($need - $paid) . ' still to pay). Save the work order now and send it then, or an admin can release the order before payment, with a reason.';")
    public static function vendorHold(PDO $pdo, array $o): string
    {
        if (!self::isClient($o) || !in_array((string) $o['status'], ['pending'], true)) return '';   // paid, or not approved (create() says so)
        $oid = (int) $o['id'];
        $need = self::releaseAt($o); $paid = Orders::money($pdo, $oid)['paid'];
        $dep = $o['pay_mode'] === 'deposit' && (int) ($o['deposit_cents'] ?? 0) > 0;
        // credit terms (decisions/0178 §4, M7): the vendor doesn't wait for payment; a deposit asked to offset the risk still comes first
        require_once __DIR__ . '/CreditTerms.php';
        if (!$dep && CreditTerms::onTerms($pdo, $o)) return '';
        if ($need <= 0 || $paid >= $need || self::overridden($pdo, $oid)) return '';
        return 'Order #' . Orders::no($o) . ' goes to the vendor once ' . ($dep ? 'its deposit is paid' : 'it\'s paid in full') . ' (' . Orders::usd($need - $paid) . ' still to pay). Save the work order now and send it then, or an admin can release the order before payment, with a reason.';
    }
