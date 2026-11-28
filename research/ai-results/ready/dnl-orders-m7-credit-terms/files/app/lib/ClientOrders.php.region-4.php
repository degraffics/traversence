<?php
// ANCHOR: replaces     public static function send(PDO $pdo, int $oid, int $days, int $staff, bool $mail = true): string through     }
// (the last line is send's own closing brace, the first "    }" after its line "        return self::url($o);" that
//  follows the Orders::event(... 'resent' : 'sent' ...) line; the two docblocks above send() stay as they are)
    public static function send(PDO $pdo, int $oid, int $days, int $staff, bool $mail = true): string
    {
        $o = self::mine($pdo, $oid, true);
        $lines = Orders::lines($pdo, $oid);
        if (!$lines) throw new RuntimeException('Add something to the order first.');
        if ($gaps = self::proofGaps($pdo, $lines)) throw new RuntimeException(self::proofGapText($gaps));
        self::refreshPrices($pdo, $oid);   // the catalog's latest, as it goes out
        foreach ($lines as $l) if ($l['product'] === 'go_code' && !$pdo->query('SELECT 1 FROM order_proofs WHERE line_id = ' . (int) $l['id'])->fetchColumn()) self::goProof($pdo, $oid, (int) $l['id'], $staff);   // codes added before proofs
        $again = $o['status'] === 'sent';
        $days = max(1, min(90, $days ?: self::DAYS));
        $until = date('Y-m-d 23:59:59', strtotime('+' . $days . ' days'));
        $pdo->prepare("UPDATE orders SET status = 'sent', sent_at = NOW(), expires_at = ?, review = '' WHERE id = ?")->execute([$until, $oid]);
        $o = Orders::get($pdo, $oid);
        $proofs = (int) $pdo->query("SELECT COUNT(*) FROM order_proofs WHERE order_id = $oid AND status = 'waiting'")->fetchColumn();
        self::$mailed = null;
        if (!$mail) { Orders::event($pdo, $oid, $again ? 'resent' : 'sent', 'Opened as the client by our team (sandbox test, no email), good until ' . date('M j', strtotime($until)), $staff); return self::url($o); }
        if ($o['pay_mode'] === 'deposit') $depWords = (int) $o['deposit_pct'] > 0 ? ' (' . (int) $o['deposit_pct'] . '% when you approve)' : ' (' . Orders::usd(self::depositFor($pdo, $o, (int) $o['subtotal_cents'], $lines)[0]) . ' when you approve)';
        $payWords = self::PAY_MODES[$o['pay_mode']] . ($depWords ?? '');
        // credit terms (decisions/0178 §4, M7): the client's granted Net 15 / Net 30, said in place of "pay in full on approval"
        require_once __DIR__ . '/CreditTerms.php';
        if (CreditTerms::onTerms($pdo, $o)) $payWords = ($o['pay_mode'] === 'deposit' ? 'A deposit' . ($depWords ?? '') . '; the rest is' : 'Nothing is due when you approve: it\'s')
            . ' on your account\'s ' . self::DUE_TERMS[(string) $o['due_terms']][0] . ' terms, due by the date on its invoice';
        self::$mailed = self::mail($o, ($again ? 'Your updated order from Traversence' : 'Your order from Traversence') . ', for your review',
            'Hello ' . $o['bill_name'] . ",\n\n" . ($again ? 'We\'ve updated your order' . ($proofs ? ' with new proofs' : '') . '.' : 'Your order is ready for your review.')
            . ($o['note'] !== '' ? "\n\n" . $o['note'] : '')
            . "\n\nReview it" . ($proofs ? ', approve the proofs,' : '') . " and approve it here:\n" . self::url($o)
            . "\n\nThe total is " . Orders::usd((int) $o['subtotal_cents']) . ' before tax. ' . $payWords . '.'
            . "\nThis link works until " . date('F j, Y', strtotime($until)) . ".");
        Orders::event($pdo, $oid, $again ? 'resent' : 'sent', (self::$mailed ? 'To ' : 'The email didn\'t go to ') . $o['bill_email'] . ', good until ' . date('M j', strtotime($until)), $staff);
        return self::url($o);
    }
