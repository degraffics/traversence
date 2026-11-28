<?php
// ANCHOR: replaces     public static function saveTerms(PDO $pdo, int $oid, array $in, int $staff): void through     }
// (the last line is saveTerms' own closing brace, the first "    }" after its line
//  "        $pdo->prepare('UPDATE qr_issued SET user_id = ?, guest_key = ? WHERE order_id = ? AND status = \'pending\'')->execute([$uid ?: null, $uid ? null : hash('sha256', 'client:' . $o['access_token']), $oid]);")
    public static function saveTerms(PDO $pdo, int $oid, array $in, int $staff): void
    {
        $o = self::mine($pdo, $oid, true);
        [$name, $email, $company, $phone] = self::client($in);
        $mode = isset(self::PAY_MODES[$in['pay_mode'] ?? '']) ? (string) $in['pay_mode'] : 'full';
        if (!in_array($mode, self::OFFERED, true) && $mode !== (string) $o['pay_mode']) throw new RuntimeException('Invoice later isn\'t offered any more: choose Pay in full or a Deposit. To go ahead before payment, an admin releases the order once it\'s approved, with a reason.');
        $pct = $mode === 'deposit' ? (int) ($in['deposit_pct'] ?? 0) : 0;
        if ($mode === 'deposit' && ($pct < 0 || $pct > 99)) throw new RuntimeException('Enter the deposit as a % between 1 and 99, or leave it empty for the items\' cost plus ' . self::DEPOSIT_MARKUP . '%.');
        if ($mode === 'outside' && !self::hasDelivery($pdo)) throw new RuntimeException('Import app/migrations/2026-12-08_delivery_outside.sql in phpMyAdmin first.');
        $due = isset(self::DUE_TERMS[$in['due_terms'] ?? '']) ? (string) $in['due_terms'] : 'receipt';
        // Net 15 / Net 30 are credit terms (decisions/0178 §4, M7): only for a client Jason has granted them to. An order
        // that already has them keeps them (as invoice later above); a change to them is checked against the grant.
        if (self::hasItems($pdo) && $due !== 'receipt' && $due !== (string) ($o['due_terms'] ?? 'receipt')) {
            require_once __DIR__ . '/CreditTerms.php';
            if (($why = CreditTerms::whyNot($pdo, self::memberFor($pdo, $email) ?: null, $email, $due)) !== '') throw new RuntimeException($why);
        }
        if (self::hasItems($pdo)) $pdo->prepare('UPDATE orders SET po_number = ?, due_terms = ? WHERE id = ?')->execute([mb_substr(trim((string) ($in['po'] ?? '')), 0, 60), $due, $oid]);
        if (self::hasDelivery($pdo)) $pdo->prepare('UPDATE orders SET delivery = ? WHERE id = ?')->execute([isset(self::DELIVERY[$in['delivery'] ?? '']) ? (string) $in['delivery'] : '', $oid]);
        $uid = self::memberFor($pdo, $email);
        $pdo->prepare('UPDATE orders SET bill_name = ?, bill_email = ?, client_company = ?, client_phone = ?, pay_mode = ?, deposit_pct = ?, note = ?, user_id = ?, guest_key = ? WHERE id = ?')
            ->execute([$name, $email, $company, $phone, $mode, $pct, mb_substr(trim((string) ($in['note'] ?? '')), 0, 300), $uid ?: null, $uid ? null : hash('sha256', 'client:' . $o['access_token']), $oid]);
        $pdo->prepare('UPDATE qr_issued SET user_id = ?, guest_key = ? WHERE order_id = ? AND status = \'pending\'')->execute([$uid ?: null, $uid ? null : hash('sha256', 'client:' . $o['access_token']), $oid]);
    }
