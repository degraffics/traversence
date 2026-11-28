**Skipped, and why (also recorded in `release.json`):**
- **M2b, "how an order goes":** paused by Jason on 2026-11-24 ("Pause for now. add to work queue."). Nothing here touches stages, legs or the stage rules.
- **Order #12's books:** waiting for Jason ("I will address this after holiday"). Nothing here touches money, invoices or the ledger.
- **M7 credit terms (Net 15 / Net 30):** queued separately. Not drafted.
- **Texting (0172 step 8):** waiting on its own decision. No text channel here.
- **Telling staff about the client's answers:** `staffEmail()` is attached, but its callers are not. Not drafted.
- **Work queue status of this task:** the database isn't attached. The lane must confirm the task is open and not paused before building.

**Review of the job-19 draft (what I changed):**
1. **The draft can't patch `ClientOrders::mail()`.** That function isn't in the attached file, which is truncated. I leave it untouched and add `tell()`. It is called from the four senders that are fully attached: `send`, `cancel`, `setStage` and `invoiceLaterChoose`. Other `mail()` callers in the unseen part of the file still use the old path, and `release.json` says so.
2. **Non-members can't go through Notify.**
   - `Notify::recipients()` only takes user ids.
   - So a client with no account, or a member whose account email isn't the order's `bill_email`, still gets `mail()` as before.
   - "The order goes there" stays true.
3. **Email must not be switchable off.** The draft's Notice level with the default flags would let a member turn order emails off, which would lose the private link. 0172 §1.6 says "email (always)", so both kinds have `email can be off = false`.
4. **No `thread` key.** Notify's thread rule would silently drop the second email while the first is unread. Each send gets a unique `thing`.
5. **Three side effects in Notify, fixed for the new kinds only:**
   - The footer offered "Stop these", which does nothing for these kinds.
   - The Communications setting would explain them as "your account's safety".
   - " · Traversence" would be appended to subjects that already say Traversence.

Notify.php was estimated at about 70 KB, so both files go as regions.

=== FILE: files/app/lib/Notify.php.region-1.php ===
// ANCHOR: replaces `        'mail.failure'   => ['The site\'s email failed', 'A sender\'s email failed, or message control paused something: Check again is in the message.', 'alert', 'staff', 'Team', true, true, 'system', ''],` through `        'mail.failure'   => ['The site\'s email failed', 'A sender\'s email failed, or message control paused something: Check again is in the message.', 'alert', 'staff', 'Team', true, true, 'system', ''],`
        'mail.failure'   => ['The site\'s email failed', 'A sender\'s email failed, or message control paused something: Check again is in the message.', 'alert', 'staff', 'Team', true, true, 'system', ''],
        // client orders (ours, decisions/0101) through the one dispatcher (0172 §2.4, §1.6: "per order: email (always)"): a member
        // client has it in their Pulse and by email; email can't be turned off, since it carries the order's private link
        // (ClientOrders::tell; a client who isn't a member is emailed as before)
        'order.client'   => ['Your order from us', 'An order our team built for you: ready to review, updated, at a stage of production or delivery, or cancelled. Its link is in the message.', 'notice', 'member', 'Orders', true, false, 'system', ''],
        'order.invoice'  => ['An invoice due on your order', 'Your order\'s invoice is due now: how much, and the link to see it and pay.', 'alert', 'member', 'Orders', true, false, 'system', ''],
=== END FILE ===

=== FILE: files/app/lib/Notify.php.region-2.php ===
// ANCHOR: replaces `    public static function settings(PDO $pdo, int $u, array $forWho): array` through `    }`
    public static function settings(PDO $pdo, int $u, array $forWho): array
    {
        require_once __DIR__ . '/Notices.php';
        $offPulse = [];
        if (Notices::ready($pdo)) { $st = $pdo->prepare('SELECT kind FROM notice_prefs WHERE user_id = ?'); $st->execute([$u]); $offPulse = array_flip($st->fetchAll(PDO::FETCH_COLUMN)); }
        $out = [];
        foreach (self::kinds() as $k => $d) {
            if ($d[3] !== 'any' && !in_array($d[3], $forWho, true)) continue;
            $notice = str_starts_with($k, 'notice.');
            $engineOn = $d[7] !== 'ses' || Notices::emailOn($pdo);
            $out[] = ['kind' => $k, 'name' => $d[0], 'what' => $d[1], 'level' => self::LEVELS[$d[2]], 'group' => $d[4],
                'pulse' => $notice ? !isset($offPulse[substr($k, 7)]) : true,
                'pulse_fixed' => $notice ? '' : 'Messages and work always reach your Pulse, so nothing that needs an answer is lost.',
                'email' => $engineOn && self::emailOn($pdo, $u, $k),
                // an order's emails always come because they carry its private link (0172 §1.6), not for the account's safety
                'email_fixed' => !$engineOn ? 'Notices by email aren\'t switched on yet.' : (!$d[6] || in_array($d[2], self::ALWAYS, true) ? ($d[4] === 'Orders' ? 'This one always comes by email: it carries your order\'s private link.' : 'This one always comes by email: it\'s about your account\'s safety.') : ''),
                'push' => !in_array($k, self::NO_PUSH, true) && self::pushChoice($pdo, $u, $k),
                'push_fixed' => in_array($k, self::NO_PUSH, true) ? 'This one never comes by push: it waits in your Pulse (and your email, if you choose).' : (in_array($d[2], self::ALWAYS, true) ? 'Critical messages always come by push on your devices.' : '')];
        }
        $q = self::quiet($pdo, $u);
        $digest = Notices::deliveryReady($pdo) && Notices::delivery($pdo, $u) === 'digest';
        return ['kinds' => $out, 'quiet' => $q, 'digest' => $digest, 'digest_on' => Notices::emailOn($pdo), 'ready' => self::ready($pdo), 'devices' => self::hasDevice($pdo, $u)];
    }
=== END FILE ===

=== FILE: files/app/lib/Notify.php.region-3.php ===
// ANCHOR: replaces `    private static function deliverEmail(PDO $pdo, int $mid): bool` through `    }`
    private static function deliverEmail(PDO $pdo, int $mid): bool
    {
        $st = $pdo->prepare('SELECT * FROM comms_messages WHERE id = ?'); $st->execute([$mid]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) return false;
        $def = self::kinds()[(string) $m['kind']] ?? null;
        if (!$def) return false;
        $u = (int) $m['to_user'];
        if ($def[7] === 'ses') {   // notices: Amazon SES, as decisions/0137 §8 set
            require_once __DIR__ . '/Notices.php';
            $ok = Notices::emailOne($pdo, $u, (string) $m['title'], (string) $m['body'], (string) $m['url']);
            self::route($pdo, $mid, 'email', $ok ? 'sent' : 'failed', $ok ? 'By Amazon SES (notices)' : 'Amazon SES didn\'t take it (see the error log)');
            return $ok;
        }
        [$addr, $name] = self::addressOf($pdo, $u, $def[3] === 'staff');
        if ($addr === '') { self::route($pdo, $mid, 'email', 'failed', 'No email address on the account'); return false; }
        $link = str_starts_with((string) $m['url'], 'http') ? (string) $m['url'] : self::SITE . ((string) $m['url'] !== '' ? $m['url'] : '/user/dashboard.php#pulse');
        $text = trim((string) $m['title']) . "\n\n" . ((string) $m['body'] !== '' ? $m['body'] . "\n\n" : '')
              // a thread updated in place is one story, never a count of copies (02 §5.3; g4 F8)
              . ((int) $m['times'] > 1 && !in_array((string) $m['kind'], self::IN_PLACE, true) ? '(' . (int) $m['times'] . " of these are waiting.)\n\n" : '')
              . 'Open it: ' . $link . "\n\n"
              // a kind that can't be stopped by email (an order's, 0172 §1.6) offers no "Stop these" link that would do nothing
              . '-- ' . "\n" . ($def[3] === 'staff'
                  ? 'You get this email because of your choices for "' . $def[0] . '". Change them: https://traversence.com/admin/dashboard/comms'
                  : ($def[6] ? 'You get this email because of your choices for "' . $def[0] . '".' . "\nStop these: " . self::stopUrl($mid, $u) : 'You get this email for "' . $def[0] . '": it always comes by email, and it\'s in your Pulse too.') . "\nChange what reaches you, and how: " . self::SETTINGS) . "\nTraversence";
        try {
            require_once __DIR__ . '/Mailer.php';
            $flag = property_exists('Mailer', 'routed');   // Mailer logs its own sends; this message's route is written here instead
            if ($flag) Mailer::$routed = true;
            $as = '';
            if (is_file(__DIR__ . '/CommsSetup.php')) { require_once __DIR__ . '/CommsSetup.php'; $as = CommsSetup::prefixFor('notices', $pdo); }   // the sender routing gives "Account notices" (0167 §1)
            // an alarm's notice is one email thread (decisions/0176's notes, 2026-11-20): its subject is the notice itself
            // ("SYSTEM ALARM NOTICE: <item>: <action>"), the first email starts the thread and each later state (the Escalation
            // Notice, Recovered) replies in it (In-Reply-To and References to the first)
            $alarm = str_starts_with((string) $m['kind'], 'alarm.') && in_array((string) $m['kind'], self::IN_PLACE, true);
            if ($alarm && property_exists('Mailer', 'thread')) {
                $sent = 0; try { $c = $pdo->prepare("SELECT COUNT(*) FROM comms_route WHERE message_id = ? AND via = 'email' AND stage = 'sent'"); $c->execute([$mid]); $sent = (int) $c->fetchColumn(); } catch (Throwable) {}
                $root = '<tv-alarm-' . $mid . '@traversence.com>';
                Mailer::$thread = $sent ? ['id' => '<tv-alarm-' . $mid . '-' . ($sent + 1) . '@traversence.com>', 'parent' => $root] : ['id' => $root];
            }
            // an order's email keeps the subject the client always had ("Your order from Traversence, for your review")
            $whole = $alarm || str_starts_with((string) $m['kind'], 'order.');
            try { Mailer::send($addr, $whole ? (string) $m['title'] : (string) $m['title'] . ' · Traversence', $text, $name !== '' ? $name : null, $as); }
            finally { if ($flag) Mailer::$routed = false; if (property_exists('Mailer', 'thread')) Mailer::$thread = null; }
            self::route($pdo, $mid, 'email', 'sent', 'System email (Bluehost) to ' . self::maskAddr($addr));
            return true;
        } catch (Throwable $e) {
            self::route($pdo, $mid, 'email', 'failed', mb_substr($e->getMessage(), 0, 200));
            error_log('Notify email ' . $mid . ': ' . $e->getMessage());
            return false;
        }
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-1.php ===
// ANCHOR: replaces `    public static function send(PDO $pdo, int $oid, int $days, int $staff, bool $mail = true): string` through `    }`
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
        // through the one dispatcher for a member client (decisions/0172 §2.4); a client who isn't a member by email as before
        $how = self::tell($pdo, $o, 'order.client', $again ? 'resent' : 'sent', ($again ? 'Your updated order from Traversence' : 'Your order from Traversence') . ', for your review',
            'Hello ' . $o['bill_name'] . ",\n\n" . ($again ? 'We\'ve updated your order' . ($proofs ? ' with new proofs' : '') . '.' : 'Your order is ready for your review.')
            . ($o['note'] !== '' ? "\n\n" . $o['note'] : '')
            . "\n\nReview it" . ($proofs ? ', approve the proofs,' : '') . " and approve it here:\n" . self::url($o)
            . "\n\nThe total is " . Orders::usd((int) $o['subtotal_cents']) . ' before tax. ' . self::PAY_MODES[$o['pay_mode']] . ($depWords ?? '') . '.'
            . "\nThis link works until " . date('F j, Y', strtotime($until)) . ".");
        self::$mailed = $how === 'sent';
        Orders::event($pdo, $oid, $again ? 'resent' : 'sent', self::howWords($how) . $o['bill_email'] . ', good until ' . date('M j', strtotime($until)), $staff);
        return self::url($o);
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-2.php ===
// ANCHOR: replaces `    public static function cancel(PDO $pdo, int $oid, string $why, int $staff): void` through `    }`
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
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-3.php ===
// ANCHOR: replaces `    public static function setStage(PDO $pdo, int $oid, string $stage, string $tracking, ?int $staff, string $detail = '', bool $mail = true, bool $checkPaid = true): void   // $detail: for our history only (the vendor's name), never the client's` through `    }`
    public static function setStage(PDO $pdo, int $oid, string $stage, string $tracking, ?int $staff, string $detail = '', bool $mail = true, bool $checkPaid = true): void   // $detail: for our history only (the vendor's name), never the client's
    {
        $o = Orders::get($pdo, $oid);
        if (!$o || !self::isClient($o)) throw new RuntimeException('That order isn\'t here.');
        if (!isset(self::STAGES[$stage])) throw new RuntimeException('Choose a stage.');
        if (in_array($o['status'], ['draft', 'sent', 'void'], true)) throw new RuntimeException('The client hasn\'t approved this order yet.');
        if ($checkPaid && in_array($stage, ['shipped', 'ready'], true) && ($why = self::shipHold($pdo, $o)) !== '') throw new RuntimeException($why);
        if ($stage === 'ordered' && ($why = self::vendorHold($pdo, $o)) !== '') throw new RuntimeException($why);   // sent to a printer is released to the vendor (model 1.9)
        $tracking = mb_substr(trim($tracking), 0, 160);
        $pdo->prepare('UPDATE orders SET stage = ?, tracking = ? WHERE id = ?')->execute([$stage, $tracking, $oid]);
        if (in_array($stage, ['shipped', 'ready', 'complete'], true)) $pdo->prepare("UPDATE order_lines SET status = 'done' WHERE order_id = ? AND status = 'open'")->execute([$oid]);
        Orders::event($pdo, $oid, 'stage', self::STAGES[$stage] . ($detail !== '' ? ' ' . $detail : '') . ($tracking !== '' ? ' · ' . $tracking : ''), $staff);
        $say = ['production' => 'is in production', 'ordered' => 'has been sent to print', 'shipped' => 'has shipped', 'ready' => 'is ready for pickup', 'complete' => 'is complete'][$stage];
        if ($mail && self::wantsProgress($o)) self::tell($pdo, $o, 'order.client', 'stage-' . $stage, 'Your Traversence order ' . $say, 'Hello ' . $o['bill_name'] . ",\n\nYour order #" . Orders::no($o) . ' ' . $say . '.' . ($tracking !== '' ? "\n" . ($stage === 'shipped' ? 'Tracking: ' : '') . $tracking : '')
            . (in_array($stage, ['shipped', 'ready'], true) && self::hasReceived($pdo) ? "\n\n" . self::GOT_IT_ASK . "\n" . self::url($o) . '#got-it' : "\n\nSee your order any time:\n" . self::url($o)));
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-4.php ===
// ANCHOR: replaces `    public static function invoiceLaterChoose(PDO $pdo, int $oid, string $choice, string $why, int $staff): string` through `    }`
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
        $how = self::tell($pdo, $o, 'order.invoice', 'invoice-due', 'Order #' . Orders::no($o) . ': your invoice is due now', 'Hello ' . $o['bill_name'] . ",\n\nOrder #" . Orders::no($o) . "'s invoice" . ($o['invoice_no'] ? ' (' . $o['invoice_no'] . ')' : '') . ' is due now: '
            . Orders::usd($left) . ". We start printing once it's paid.\n\nSee it and pay here:\n" . self::url($o) . "\n\nQuestions? Just reply.");
        if ($how === 'failed') return 'Order #' . Orders::no($o) . ' is Pay in full now; the email to ' . $o['bill_name'] . ' didn\'t go: tell them ' . Orders::usd($left) . ' is due, with the link to pay.';
        return 'Order #' . Orders::no($o) . ' is Pay in full now; ' . $o['bill_name'] . ($how === 'sent' ? ' was emailed' : ' has it in their Pulse' . ($how === 'held' ? ' (the email waits for their quiet hours or message control)' : '')) . ' that ' . Orders::usd($left) . ' is due, with the link to pay.';
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-5.php ===
// ANCHOR: replaces `    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }` through `    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }`
    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }

    // ---- telling the client: through the one dispatcher (decisions/0172 §2.4, §1.6) ------------------------------------------

    /**
     * A client order's email.
     * - A member client whose account email is the order's: through Notify, kind order.client or order.invoice. It lands in
     *   their Pulse, and is emailed (always on, since it carries the order's private link).
     * - Anyone else, or the dispatcher not ready or not taking it: by mail(), as before. That covers a client who isn't a
     *   member, and a member whose account email isn't the order's ("the order goes there").
     * Each send is its own message: a unique thing and no thread, so a second email is never folded into an unread first.
     * Returns how it went:
     * - 'sent': emailed;
     * - 'held': in Pulse, the email waiting for quiet hours or message control;
     * - 'failed': the email didn't go;
     * - 'pulse': in Pulse, with no email stage on its route.
     */
    private static function tell(PDO $pdo, array $o, string $kind, string $what, string $subject, string $text): string
    {
        $uid = self::notifyTo($pdo, $o);
        if ($uid > 0) {
            try {
                require_once __DIR__ . '/Notify.php';
                $thing = 'order:' . (int) $o['id'] . ':' . $what . ':' . bin2hex(random_bytes(4));
                $n = Notify::send($pdo, $kind, ['users' => [$uid]], ['thing' => $thing, 'url' => self::url($o), 'from' => ['system', null, 'Orders']], ['title' => $subject, 'body' => $text]);
                if ($n > 0) return self::emailState($pdo, $uid, $kind, $thing);
            } catch (Throwable $x) { error_log('client order notify, order ' . (int) $o['id'] . ': ' . $x->getMessage()); }
        }
        return self::mail($o, $subject, $text) ? 'sent' : 'failed';
    }

    /** The member to reach through the dispatcher, or 0: the order's member, not suspended, whose account email is the order's. */
    private static function notifyTo(PDO $pdo, array $o): int
    {
        if ((int) ($o['user_id'] ?? 0) <= 0) return 0;
        try {
            $st = $pdo->prepare('SELECT email FROM users WHERE id = ? AND is_suspended = 0'); $st->execute([(int) $o['user_id']]);
            $e = (string) ($st->fetchColumn() ?: '');
        } catch (Throwable) { return 0; }
        return self::viaNotify($o, $e) ? (int) $o['user_id'] : 0;
    }

    /** Whether an order's email goes through the dispatcher: a member client whose account email is the order's. Pure (tested). */
    public static function viaNotify(array $o, string $accountEmail): bool
    {
        $e = strtolower(trim($accountEmail));
        return (int) ($o['user_id'] ?? 0) > 0 && $e !== '' && $e === strtolower(trim((string) ($o['bill_email'] ?? '')));
    }

    /** How the email of the message just sent went, read from its route (Notify::routeOf). */
    private static function emailState(PDO $pdo, int $uid, string $kind, string $thing): string
    {
        try {
            $st = $pdo->prepare('SELECT id FROM comms_messages WHERE to_user = ? AND kind = ? AND thing = ? ORDER BY id DESC LIMIT 1'); $st->execute([$uid, $kind, $thing]);
            $mid = (int) ($st->fetchColumn() ?: 0);
            return $mid ? self::routeWord(Notify::routeOf($pdo, $mid)) : 'pulse';
        } catch (Throwable) { return 'pulse'; }
    }

    /** The email's last stage on a route: 'sent', 'held', 'failed', or 'pulse' when it had none. Pure (tested). */
    public static function routeWord(array $route): string
    {
        $last = '';
        foreach ($route as $r) if ((string) ($r['via'] ?? '') === 'email') $last = (string) ($r['stage'] ?? '');
        return in_array($last, ['sent', 'held', 'failed'], true) ? $last : 'pulse';
    }

    /** The order's history line for a send, before the client's address. Pure (tested). */
    public static function howWords(string $how): string
    {
        return ['sent' => 'To ', 'held' => 'In their Pulse; the email waits (their quiet hours, or message control), to ', 'pulse' => 'In their Pulse, not emailed, for '][$how] ?? 'The email didn\'t go to ';
    }
=== END FILE ===

=== FILE: tests/client-order-emails.php ===
<?php
// tools/client-order-tests/client-order-emails.php: client order emails through the one dispatcher (decisions/0172 §2.4).
// Plain PHP; prints PASS/FAIL per check, exits 1 on any FAIL.
// It touches no database, sends no email and moves no money: it reads the code and calls pure functions only, so no
// QuickBooksPay guard is needed. Run from the site root:
//   php tools/client-order-tests/client-order-emails.php      (or SITE_ROOT=/path php …)
declare(strict_types=1);

$root = getenv('SITE_ROOT') ?: dirname(__DIR__, 2);
$lib = rtrim($root, '/') . '/app/lib';
$fails = 0;
$check = function (string $name, bool $ok, string $why = '') use (&$fails): void {
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n";
    if (!$ok) $fails++;
};

// ---- 1. Notify's register ----
require_once $lib . '/Notify.php';
foreach (['order.client' => 'notice', 'order.invoice' => 'alert'] as $k => $lvl) {
    $d = Notify::KINDS[$k] ?? null;
    $check("$k is a kind with 9 fields", is_array($d) && count($d) === 9);
    if (!is_array($d)) continue;
    $check("$k is at $lvl (0172 §2.4)", $d[2] === $lvl, (string) $d[2]);
    $check("$k is for members", $d[3] === 'member', (string) $d[3]);
    $check("$k: email on by default", $d[5] === true);
    $check("$k: email can't be turned off (0172 §1.6)", $d[6] === false);
    $check("$k: system email (Bluehost), not SES", $d[7] === 'system', (string) $d[7]);
    $check("$k: not a one-thread kind (each send is its own)", !in_array($k, Notify::IN_PLACE, true));
    $check("$k: push not forbidden", !in_array($k, Notify::NO_PUSH, true));
    $check("$k is in kinds()", isset(Notify::kinds()[$k]));
}

$nsrc = (string) file_get_contents($lib . '/Notify.php');
$check('Notify: no Stop these link on an email that can\'t be stopped', str_contains($nsrc, "it always comes by email, and it\\'s in your Pulse too."));
$check('Notify: an order\'s subject is kept whole', str_contains($nsrc, "str_starts_with((string) \$m['kind'], 'order.')"));
$check('Notify: the setting explains why order emails always come', str_contains($nsrc, "it carries your order\\'s private link."));

// ---- 2. ClientOrders ----
$loaded = false;
try { require_once $lib . '/ClientOrders.php'; $loaded = class_exists('ClientOrders', false); }
catch (Throwable $e) { $check('ClientOrders loads', false, $e->getMessage()); }
if ($loaded) {
    $check('ClientOrders loads', true);
    $o = ['id' => 1, 'user_id' => 7, 'bill_email' => 'Client@Example.com'];
    $check('viaNotify: a member with the order\'s email (any case)', ClientOrders::viaNotify($o, ' client@example.com '));
    $check('viaNotify: not a member', !ClientOrders::viaNotify(['user_id' => null] + $o, 'client@example.com'));
    $check('viaNotify: the account\'s email differs', !ClientOrders::viaNotify($o, 'other@example.com'));
    $check('viaNotify: no account email', !ClientOrders::viaNotify($o, ''));

    $r = fn (array ...$s) => array_map(fn ($x) => ['via' => $x[0], 'stage' => $x[1], 'detail' => '', 'at' => ''], $s);
    $check('routeWord: sent', ClientOrders::routeWord($r(['pulse', 'created'], ['pulse', 'delivered'], ['email', 'sent'])) === 'sent');
    $check('routeWord: held for quiet hours', ClientOrders::routeWord($r(['pulse', 'delivered'], ['email', 'held'])) === 'held');
    $check('routeWord: failed', ClientOrders::routeWord($r(['email', 'failed'], ['push', 'sent'])) === 'failed');
    $check('routeWord: Pulse only', ClientOrders::routeWord($r(['pulse', 'delivered'])) === 'pulse');
    $check('howWords: sent reads as before', ClientOrders::howWords('sent') === 'To ');
    $check('howWords: failed reads as before', ClientOrders::howWords('failed') === 'The email didn\'t go to ');

    $src = file((new ReflectionClass('ClientOrders'))->getFileName());
    $body = function (string $m) use ($src): string { $f = new ReflectionMethod('ClientOrders', $m); return implode('', array_slice($src, $f->getStartLine() - 1, $f->getEndLine() - $f->getStartLine() + 1)); };
    foreach (['send', 'cancel', 'setStage', 'invoiceLaterChoose'] as $m) {
        $b = $body($m);
        $check("$m tells the client through tell()", str_contains($b, 'self::tell(') && !str_contains($b, 'self::mail('));
    }
    $t = $body('tell');
    $check('tell: through Notify::send', str_contains($t, 'Notify::send('));
    $check('tell: falls back to mail() as before', str_contains($t, 'self::mail('));
    $check('tell: a unique thing per send', str_contains($t, 'random_bytes'));
    $check('tell: