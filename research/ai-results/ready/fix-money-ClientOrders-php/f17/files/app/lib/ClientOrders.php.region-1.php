<?php
// ANCHOR: replaces |     * Why an order can't be released for delivery (Shipped or Ready for pickup) yet, or '' when it can. One rule on every|
// through |     * Why an approved order can't go to a vendor yet (a work order sent or its link handed over, or marked sent to a|
// (the lane pastes everything below this comment in its place; the last two lines are kept as they were)
     * Why an order can't be released for delivery (Shipped or Ready for pickup) yet, or '' when it can. One rule on every
     * path (decisions/0101 note of 2026-11-20, B.4, as the order model changes it, note of 2026-11-23): paid in full
     * ("Balance must be paid before order is release for delivery", model 1.9), except an admin's release before payment
     * (releaseHold, with a reason). Invoice later is no longer an exception (model §0 item 3), and neither are outside
     * terms (f17): money taken outside QuickBooks is recorded on the order (Record a payment), and until it is, delivery
     * waits like any order's.
     */
    public static function shipHold(PDO $pdo, array $o): string
    {
        $oid = (int) $o['id'];
        $left = (int) $o['total_cents'] - Orders::money($pdo, $oid)['paid'];
        if ($left <= 0 || self::overridden($pdo, $oid)) return '';
        return 'Order #' . Orders::no($o) . ' isn\'t paid in full yet (' . Orders::usd($left) . ' still to pay), so it can\'t be released for delivery (shipped or ready for pickup). Record the payment when it comes in, or an admin can release it before payment, with a reason.';
    }

    /**
     * Why an approved order can't go to a vendor yet (a work order sent or its link handed over, or marked sent to a
