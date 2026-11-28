<?php
// ANCHOR: replaces     public static function send(PDO $pdo, int $oid, int $days, int $staff, bool $mail = true): string through the first line "    }" after it (the line before it is:         Orders::event($pdo, $oid, $again ? 'resent' : 'sent', (self::$mailed ? 'To ' : 'The email didn\'t go to ') . $o['bill_email'] . ', good until ' . date('M j', strtotime($until)), $staff);)
// The two docblocks above send() stay where they are. This region is send() itself, then the new tell() after it.
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
        self::$mailed = self::tell($pdo, $o, 'order.client', $again ? 'resent' : 'sent', ($again ? 'Your updated order from Traversence' : 'Your order from Traversence') . ', for your review',
            'Hello ' . $o['bill_name'] . ",\n\n" . ($again ? 'We\'ve updated your order' . ($proofs ? ' with new proofs' : '') . '.' : 'Your order is ready for your review.')
            . ($o['note'] !== '' ? "\n\n" . $o['note'] : '')
            . "\n\nReview it" . ($proofs ? ', approve the proofs,' : '') . " and approve it here:\n" . self::url($o)
            . "\n\nThe total is " . Orders::usd((int) $o['subtotal_cents']) . ' before tax. ' . self::PAY_MODES[$o['pay_mode']] . ($depWords ?? '') . '.'
            . "\nThis link works until " . date('F j, Y', strtotime($until)) . ".");
        Orders::event($pdo, $oid, $again ? 'resent' : 'sent', (self::$mailed ? 'To ' : 'The email didn\'t go to ') . $o['bill_email'] . ', good until ' . date('M j', strtotime($until)), $staff);
        return self::url($o);
    }

    /**
     * One email to the client about their order, through the one dispatcher (decisions/0172 §2.4, §4). A client who is a
     * member, whose account email is the order's billing email, gets it through Notify: in their Pulse, and by email, which
     * they can't turn off (0172 §1.6: "email (always)"). Anyone else (a client who isn't a member, an account email that
     * differs from the order's), or whenever Notify can't take it (its update not run, the kind unknown, an error), gets it
     * by self::mail exactly as before. Never two emails: once Notify has taken it, self::mail isn't called.
     * No thread is passed: each update is its own email (Notify's "one email per unread conversation" would hold the
     * client's stage emails while an earlier one sits unread). True when the email went, or the dispatcher holds it to go
     * later (quiet hours, message control); false when it didn't.
     */
    private static function tell(PDO $pdo, array $o, string $kind, string $what, string $subject, string $body): bool
    {
        $uid = (int) ($o['user_id'] ?? 0);
        $n = 0; $uidTo = 0; $thing = '';
        if ($uid > 0 && is_file(__DIR__ . '/Notify.php')) {
            try {
                require_once __DIR__ . '/Notify.php';
                $st = $pdo->prepare('SELECT email FROM users WHERE id = ? AND is_suspended = 0'); $st->execute([$uid]);
                $acct = strtolower(trim((string) ($st->fetchColumn() ?: '')));
                if ($acct !== '' && $acct === strtolower(trim((string) ($o['bill_email'] ?? ''))) && Notify::ready($pdo)) {
                    $thing = 'order:' . (int) $o['id'] . ':' . $what . ':' . bin2hex(random_bytes(4));   // each call is a real send, as before
                    $uidTo = $uid;
                    $n = Notify::send($pdo, $kind, ['users' => [$uid]], ['thing' => $thing, 'url' => self::url($o), 'from' => ['system', null, 'Orders']], ['title' => $subject, 'body' => $body]);
                }
            } catch (Throwable $x) { error_log('client order ' . (int) ($o['id'] ?? 0) . ' through Notify: ' . $x->getMessage()); }
        }
        if ($n <= 0) return (bool) self::mail($o, $subject, $body);
        try {   // Notify took it: what its email route says
            $m = $pdo->prepare('SELECT id FROM comms_messages WHERE to_user = ? AND kind = ? AND thing = ? LIMIT 1'); $m->execute([$uidTo, $kind, $thing]);
            $stage = '';
            foreach (Notify::routeOf($pdo, (int) ($m->fetchColumn() ?: 0)) as $r) if ($r['via'] === 'email') $stage = $r['stage'];
            return in_array($stage, ['sent', 'held'], true);
        } catch (Throwable $x) { error_log('client order ' . (int) ($o['id'] ?? 0) . ' route: ' . $x->getMessage()); return true; }
    }
