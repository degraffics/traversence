**Skipped, said first:** nothing in M7 itself is paused or waiting for Jason. 0178 §4 answers the design's §5 Q4, which was M7's only precondition.

These are left alone because the attached documents mark them paused or waiting:
- **M2b** is paused by Jason.
- **Order #12's books** wait on "I will address this after holiday".

The M7 task's own status in the Work queue isn't attached, so check it before this goes in.

**Review of the draft:** the attached DRAFT (job 19, `dnl-orders-m7-credit-terms.md`) is **empty**, so there was nothing to review. These files are built from 0178 §4, the design, and the live files.

**Two limits on how complete this is:**
- **`ClientOrders.php` is truncated** in the attachment. `boardState()` is cut mid-function, and `releaseAt()`, `dueDate()`, `hasItems()` and `mine()` aren't shown. So this release covers the library, the migration and the holds. The board's Money line and Next step for a terms order are described in `verify`, not written.
- **No grant screen or Terms choice yet.** `admin/client-order.php` isn't attached, so there is no screen for Jason to grant terms and no Terms choice on the order page. The feature can't be used until those land; the FAQ says "coming".

=== FILE: files/app/migrations/2027-02-01_client_credit_terms.sql ===
-- Credit terms on a client's account (decisions/0178 §4, Orders M7): Net 15 or Net 30, granted by Jason only.
-- Safe to run twice: the table only if missing; pay_mode gains 'terms' only when it is an ENUM without it.
-- No UPDATE or DELETE: nothing existing changes.

CREATE TABLE IF NOT EXISTS client_credit_terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  terms VARCHAR(10) NOT NULL,
  note VARCHAR(400) NOT NULL DEFAULT '',
  granted_by INT UNSIGNED NOT NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  ended_by INT UNSIGNED NULL,
  end_note VARCHAR(400) NOT NULL DEFAULT '',
  KEY cct_user (user_id),
  KEY cct_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @tv_t := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'pay_mode');
SET @tv_n := (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'pay_mode');
SET @tv_d := (SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'pay_mode');
SET @tv_s := IF(@tv_t LIKE 'enum(%' AND @tv_t NOT LIKE '%''terms''%',
  CONCAT('ALTER TABLE orders MODIFY pay_mode ', LEFT(@tv_t, CHAR_LENGTH(@tv_t) - 1), ',''terms'')',
         IF(@tv_n = 'NO', ' NOT NULL', ' NULL'),
         IF(@tv_d IS NULL OR @tv_d = 'NULL', '', CONCAT(' DEFAULT ', IF(@tv_d LIKE '''%''', @tv_d, QUOTE(@tv_d))))),
  'SELECT 1');
PREPARE tv_m7 FROM @tv_s;
EXECUTE tv_m7;
DEALLOCATE PREPARE tv_m7;
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-1.php ===
// ANCHOR: replaces     public const PAY_MODES = ['full' => 'Pay in full on approval', 'deposit' => 'A deposit on approval, the rest once production schedules it, before delivery', 'invoice' => 'Approve now, pay the invoice later', through                               'outside' => 'Paid outside QuickBooks (cash, check, Zelle…): we record it'];
    // 'terms': the client's account has credit terms Jason granted (decisions/0178 §4, M7); offered only to that account
    // (saveTerms checks), so it isn't in OFFERED. Terms replace invoice later (model 1.8).
    public const PAY_MODES = ['full' => 'Pay in full on approval', 'deposit' => 'A deposit on approval, the rest once production schedules it, before delivery', 'invoice' => 'Approve now, pay the invoice later',
                              'outside' => 'Paid outside QuickBooks (cash, check, Zelle…): we record it',
                              'terms' => 'On the account\'s credit terms: approve now, pay by the due date'];
    /** The credit terms Jason may grant an account (decisions/0178 §4): keys of DUE_TERMS. */
    public const CREDIT_TERMS = ['net15', 'net30'];
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-2.php ===
// ANCHOR: replaces     public static function duplicate(PDO $pdo, int $oid, int $staff, bool $test): int through     }
// (the last line is the method's closing brace: the first line equal to '    }' after the first line)
    public static function duplicate(PDO $pdo, int $oid, int $staff, bool $test): int
    {
        $o = Orders::get($pdo, $oid);
        if (!$o || !self::isClient($o)) throw new RuntimeException('That order isn\'t here.');
        $me = self::staffEmail($pdo, ['created_by' => $staff]);
        $mk = fn () => self::create($pdo, $staff, ['name' => $test ? 'TEST · ' . preg_replace('/^(TEST · )+/u', '', (string) $o['bill_name']) : $o['bill_name'], 'email' => $test ? $me : $o['bill_email'],
            'company' => (string) $o['client_company'], 'phone' => (string) $o['client_phone']]);
        // a test copy is always a sandbox order (Jason, 2026-11-10), whatever mode Payments is in: no real money, ever
        require_once __DIR__ . '/QuickBooksPay.php';
        $nid = $test ? QuickBooksPay::withEnv('sandbox', $mk) : $mk();
        $keep = ['bill_street', 'bill_city', 'bill_state', 'bill_zip', 'ship_name', 'ship_street', 'ship_city', 'ship_state', 'ship_zip', 'note', 'pay_mode', 'deposit_pct', 'due_terms', 'ship_mode', 'delivery', 'no_ship_reason'];
        $set = []; $vals = [];
        foreach ($keep as $c) if (array_key_exists($c, $o)) { $set[] = "$c = ?"; $vals[] = $c === 'pay_mode' ? self::copyMode($pdo, $o, $test) : $o[$c]; }
        if ($set) { $vals[] = $nid; $pdo->prepare('UPDATE orders SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals); }
        // a copy of an order on credit terms keeps them only while the account still has them (never a test copy): else Pay in full, due on receipt
        if (self::onTerms($o) && array_key_exists('due_terms', $o) && ($nw = Orders::get($pdo, $nid)) && !self::onTerms($nw)) $pdo->prepare("UPDATE orders SET due_terms = 'receipt' WHERE id = ?")->execute([$nid]);
        $pmap = [];
        foreach (self::projects($pdo, $oid) as $pj) { $pdo->prepare('INSERT INTO order_projects (order_id, title, position, created_at) VALUES (?, ?, ?, NOW())')->execute([$nid, $pj['title'], (int) $pj['position']]); $pmap[(int) $pj['id']] = (int) $pdo->lastInsertId(); }
        // collateral_id too: a copy is a new order, not a reorder of that collateral (Collateral's own reorder sets it itself);
        // Collateral::fromOrder gives the copy's items their own when it's approved (the survey, #16)
        $skip = ['id', 'order_id', 'status', 'approved_at', 'created_at', 'item_status', 'tracking', 'project_id', 'ref_id', 'promo_off_cents', 'collateral_id'];
        $st = $pdo->prepare("SELECT * FROM order_lines WHERE order_id = ? AND status <> 'cancelled' AND product <> 'go_code' ORDER BY position, id"); $st->execute([$oid]);
        $copied = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $cols = array_values(array_diff(array_keys($l), $skip));
            $pdo->prepare('INSERT INTO order_lines (order_id, ' . implode(', ', $cols) . ', project_id) VALUES (?' . str_repeat(', ?', count($cols)) . ', ?)')
                ->execute(array_merge([$nid], array_map(fn ($c) => $l[$c], $cols), [isset($l['project_id']) && isset($pmap[(int) $l['project_id']]) ? $pmap[(int) $l['project_id']] : null]));
            $lid = (int) $pdo->lastInsertId(); $copied++;
            if (preg_match('/^\d+$/', (string) ($l['ship_to'] ?? ''))) $pdo->prepare("UPDATE order_lines SET ship_to = '' WHERE id = ?")->execute([$lid]);   // an address of the old order: the new one's own instead
            require_once __DIR__ . '/Costing.php'; Costing::carry($pdo, (int) $l['id'], $nid, $lid, $staff);   // its costing, at today's costs (unchecked)
            foreach (self::partsOf($pdo, [(int) $l['id']])[(int) $l['id']] ?? [] as $pt)
                $pdo->prepare('INSERT INTO order_line_parts (line_id, title, qty, unit_cents, cost_cents, position, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')->execute([$lid, $pt['title'], $pt['qty'], $pt['unit_cents'], $pt['cost_cents'], $pt['position']]);
            $pr = $pdo->prepare('SELECT * FROM order_proofs WHERE line_id = ? ORDER BY version, id'); $pr->execute([(int) $l['id']]);
            $proofs = $pr->fetchAll(PDO::FETCH_ASSOC);
            $last = 0; foreach ($proofs as $p) if (($p['kind'] ?? 'proof') === 'proof') $last = max($last, (int) $p['version']);
            foreach ($proofs as $p) {
                $src = Store::local(self::proofPath($p));
                if ($src === '') continue;   // its file is missing: skip it
                $final = ($p['kind'] ?? 'proof') === 'final';
                $pdo->prepare('INSERT INTO order_proofs (order_id, line_id, version, file_name, mime, status, created_by' . (self::hasProjects($pdo) ? ', kind' : '') . ') VALUES (?, ?, ?, ?, ?, ?, ?' . (self::hasProjects($pdo) ? ', ?' : '') . ')')
                    ->execute(array_merge([$nid, $lid, (int) $p['version'], $p['file_name'], $p['mime'], $final ? 'approved' : ((int) $p['version'] === $last ? 'waiting' : 'replaced'), $staff], self::hasProjects($pdo) ? [$final ? 'final' : 'proof'] : []));
                $np = (int) $pdo->lastInsertId();
                $dest = self::proofPlace($pdo, ['id' => $np, 'order_id' => $nid, 'mime' => $p['mime']]);
                if (!is_dir(dirname($dest))) @mkdir(dirname($dest), Store::mode($dest), true);
                if (!@copy($src, $dest)) { $pdo->prepare('DELETE FROM order_proofs WHERE id = ?')->execute([$np]); continue; }
                Store::keep($dest);
            }
        }
        self::recalc($pdo, $nid);
        Orders::event($pdo, $nid, 'drafted', ($test ? 'Test copy' : 'Copy') . ' of order #' . Orders::no($o) . ' (' . $copied . ' items)', $staff);
        Orders::event($pdo, $oid, 'copied', ($test ? 'Test copy' : 'Copied') . ' as order #' . Orders::no(Orders::get($pdo, $nid) ?: ['id' => $nid]), $staff);
        return $nid;
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-3.php ===
// ANCHOR: replaces     public static function saveTerms(PDO $pdo, int $oid, array $in, int $staff): void through     }
// (the last line is the method's closing brace: the first line equal to '    }' after the first line)
    public static function saveTerms(PDO $pdo, int $oid, array $in, int $staff): void
    {
        $o = self::mine($pdo, $oid, true);
        [$name, $email, $company, $phone] = self::client($in);
        $mode = isset(self::PAY_MODES[$in['pay_mode'] ?? '']) ? (string) $in['pay_mode'] : 'full';
        if ($mode !== 'terms' && !in_array($mode, self::OFFERED, true) && $mode !== (string) $o['pay_mode']) throw new RuntimeException('Invoice later isn\'t offered any more: choose Pay in full or a Deposit. To go ahead before payment, an admin releases the order once it\'s approved, with a reason.');
        $uid = self::memberFor($pdo, $email);
        $terms = null;
        if ($mode === 'terms') {   // only an account Jason granted terms to (decisions/0178 §4)
            if (!self::termsReady($pdo) || !self::hasItems($pdo)) throw new RuntimeException('Credit terms wait for their database update (' . self::TERMS_MIGRATION . '): it runs within the hour.');
            $terms = self::accountTerms($pdo, $uid ?: null, $email);
            if (!$terms) throw new RuntimeException($name . '\'s account has no credit terms. Only Jason grants Net 15 or Net 30, after the credit process: choose Pay in full or a Deposit meanwhile.');
        }
        $pct = $mode === 'deposit' ? (int) ($in['deposit_pct'] ?? 0) : 0;
        if ($mode === 'deposit' && ($pct < 0 || $pct > 99)) throw new RuntimeException('Enter the deposit as a % between 1 and 99, or leave it empty for the items\' cost plus ' . self::DEPOSIT_MARKUP . '%.');
        if ($mode === 'outside' && !self::hasDelivery($pdo)) throw new RuntimeException('Import app/migrations/2026-12-08_delivery_outside.sql in phpMyAdmin first.');
        $due = $terms ? (string) $terms['terms'] : (isset(self::DUE_TERMS[$in['due_terms'] ?? '']) ? (string) $in['due_terms'] : 'receipt');   // on terms, the account's terms are the due date's
        if (self::hasItems($pdo)) $pdo->prepare('UPDATE orders SET po_number = ?, due_terms = ? WHERE id = ?')->execute([mb_substr(trim((string) ($in['po'] ?? '')), 0, 60), $due, $oid]);
        if (self::hasDelivery($pdo)) $pdo->prepare('UPDATE orders SET delivery = ? WHERE id = ?')->execute([isset(self::DELIVERY[$in['delivery'] ?? '']) ? (string) $in['delivery'] : '', $oid]);
        $pdo->prepare('UPDATE orders SET bill_name = ?, bill_email = ?, client_company = ?, client_phone = ?, pay_mode = ?, deposit_pct = ?, note = ?, user_id = ?, guest_key = ? WHERE id = ?')
            ->execute([$name, $email, $company, $phone, $mode, $pct, mb_substr(trim((string) ($in['note'] ?? '')), 0, 300), $uid ?: null, $uid ? null : hash('sha256', 'client:' . $o['access_token']), $oid]);
        $pdo->prepare('UPDATE qr_issued SET user_id = ?, guest_key = ? WHERE order_id = ? AND status = \'pending\'')->execute([$uid ?: null, $uid ? null : hash('sha256', 'client:' . $o['access_token']), $oid]);
        if ($mode !== (string) $o['pay_mode'] && ($mode === 'terms' || (string) $o['pay_mode'] === 'terms'))
            Orders::event($pdo, $oid, 'terms', $terms ? 'On the client\'s ' . self::DUE_TERMS[(string) $terms['terms']][0] . ' credit terms (granted by Jason ' . date('M j, Y', strtotime((string) $terms['granted_at'])) . ')' : 'Off credit terms: ' . self::PAY_MODES[$mode], $staff);
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-4.php ===
// ANCHOR: replaces     public static function send(PDO $pdo, int $oid, int $days, int $staff, bool $mail = true): string through     }
// (the last line is the method's closing brace: the first line equal to '    }' after the first line; the two doc comments above it stay)
    public static function send(PDO $pdo, int $oid, int $days, int $staff, bool $mail = true): string
    {
        $o = self::mine($pdo, $oid, true);
        $lines = Orders::lines($pdo, $oid);
        if (!$lines) throw new RuntimeException('Add something to the order first.');
        if ($gaps = self::proofGaps($pdo, $lines)) throw new RuntimeException(self::proofGapText($gaps));
        if (self::onTerms($o)) {   // on the client's credit terms (decisions/0178 §4): still granted, and the order carries the account's terms
            $t = self::termsFor($pdo, $o);
            if (!$t) throw new RuntimeException('The client\'s credit terms were ended, so this order can\'t go out on them: choose Pay in full or a Deposit in Terms, then send it.');
            if (self::hasItems($pdo) && (string) ($o['due_terms'] ?? '') !== (string) $t['terms']) $pdo->prepare('UPDATE orders SET due_terms = ? WHERE id = ?')->execute([(string) $t['terms'], $oid]);
        }
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
        if ($o['pay_mode'] === 'terms' && isset(self::DUE_TERMS[(string) ($o['due_terms'] ?? '')])) $depWords = ' (' . self::DUE_TERMS[$o['due_terms']][0] . ': due ' . (int) self::DUE_TERMS[$o['due_terms']][1] . ' days after you approve)';
        self::$mailed = self::mail($o, ($again ? 'Your updated order from Traversence' : 'Your order from Traversence') . ', for your review',
            'Hello ' . $o['bill_name'] . ",\n\n" . ($again ? 'We\'ve updated your order' . ($proofs ? ' with new proofs' : '') . '.' : 'Your order is ready for your review.')
            . ($o['note'] !== '' ? "\n\n" . $o['note'] : '')
            . "\n\nReview it" . ($proofs ? ', approve the proofs,' : '') . " and approve it here:\n" . self::url($o)
            . "\n\nThe total is " . Orders::usd((int) $o['subtotal_cents']) . ' before tax. ' . self::PAY_MODES[$o['pay_mode']] . ($depWords ?? '') . '.'
            . "\nThis link works until " . date('F j, Y', strtotime($until)) . ".");
        Orders::event($pdo, $oid, $again ? 'resent' : 'sent', (self::$mailed ? 'To ' : 'The email didn\'t go to ') . $o['bill_email'] . ', good until ' . date('M j', strtotime($until)), $staff);
        return self::url($o);
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-5.php ===
// ANCHOR: replaces     public static function shipHold(PDO $pdo, array $o): string through     }
// (the last line is the method's closing brace: the first line equal to '    }' after the first line)
    public static function shipHold(PDO $pdo, array $o): string
    {
        $oid = (int) $o['id'];
        // paid outside, or on the client's credit terms (decisions/0178 §4: "the vendor and delivery don't wait for payment"; model 1.8: "balance due per terms")
        if (in_array(($o['pay_mode'] ?? ''), ['outside', 'terms'], true)) return '';
        $left = (int) $o['total_cents'] - Orders::money($pdo, $oid)['paid'];
        if ($left <= 0 || self::overridden($pdo, $oid)) return '';
        return 'Order #' . Orders::no($o) . ' isn\'t paid in full yet (' . Orders::usd($left) . ' still to pay), so it can\'t be released for delivery (shipped or ready for pickup). Record the payment when it comes in, or an admin can release it before payment, with a reason.';
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-6.php ===
// ANCHOR: replaces     public static function vendorHold(PDO $pdo, array $o): string through     }
// (the last line is the method's closing brace: the first line equal to '    }' after the first line)
    public static function vendorHold(PDO $pdo, array $o): string
    {
        if (!self::isClient($o) || !in_array((string) $o['status'], ['pending'], true)) return '';   // paid, or not approved (create() says so)
        if (self::onTerms($o)) return '';   // the client's credit terms: the vendor doesn't wait for payment (decisions/0178 §4)
        $oid = (int) $o['id'];
        $need = self::releaseAt($o); $paid = Orders::money($pdo, $oid)['paid'];
        if ($need <= 0 || $paid >= $need || self::overridden($pdo, $oid)) return '';
        $dep = $o['pay_mode'] === 'deposit' && (int) ($o['deposit_cents'] ?? 0) > 0;
        return 'Order #' . Orders::no($o) . ' goes to the vendor once ' . ($dep ? 'its deposit is paid' : 'it\'s paid in full') . ' (' . Orders::usd($need - $paid) . ' still to pay). Save the work order now and send it then, or an admin can release the order before payment, with a reason.';
    }
=== END FILE ===

=== FILE: files/app/lib/ClientOrders.php.region-7.php ===
// ANCHOR: replaces     public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; } through     public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }
// (a one-line method: kept as it was, the credit terms methods added after it)
    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }

    // ---- credit terms on a client's account (decisions/0178 §4, Orders M7) --------------------------------------------------
    // Net 15 or Net 30, granted to an account by Jason only, after the credit process (what it found is kept as the note).
    // An order on terms (pay_mode 'terms') is due by its terms' date (due_terms); the vendor and delivery don't wait for
    // payment. Completing with money still owed is still 0178 §6's (Delivered, balance due). Grants are only added to: a
    // change ends the old one (ended_at) and adds the new one, so the history stays.

    public const TERMS_MIGRATION = '2027-02-01_client_credit_terms.sql';

    public static function termsReady(PDO $pdo): bool
    {
        static $r = null;
        if ($r === null) { try { $pdo->query('SELECT 1 FROM client_credit_terms LIMIT 0'); $r = true; } catch (Throwable) { $r = false; } }
        return $r;
    }

    public static function onTerms(array $o): bool { return ($o['pay_mode'] ?? '') === 'terms'; }

    /** The days a credit term gives (0 for anything that isn't one). */
    public static function termsDays(string $terms): int { return in_array($terms, self::CREDIT_TERMS, true) ? (int) self::DUE_TERMS[$terms][1] : 0; }

    /** The date an order on $terms is due, counted from $from (its approval). */
    public static function termsDueDate(string $from, string $terms): string
    {
        $at = strtotime($from) ?: time();
        return date('Y-m-d', strtotime('+' . self::termsDays($terms) . ' days', $at));
    }

    /** An account's terms in force (by member, or by email), or null. */
    public static function accountTerms(PDO $pdo, ?int $uid, string $email): ?array
    {
        if (!self::termsReady($pdo)) return null;
        $email = strtolower(trim($email));
        if (!$uid && $email === '') return null;
        $st = $pdo->prepare('SELECT * FROM client_credit_terms WHERE ended_at IS NULL AND (' . ($uid ? 'user_id = ? OR ' : '') . 'email = ?) ORDER BY id DESC LIMIT 1');
        $st->execute($uid ? [$uid, $email] : [$email]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The terms in force for an order's client, or null. */
    public static function termsFor(PDO $pdo, array $o): ?array
    {
        return self::accountTerms($pdo, (int) ($o['user_id'] ?? 0) ?: null, (string) ($o['bill_email'] ?? ''));
    }

    /** Every grant an account has had, newest first (the history). */
    public static function termsHistory(PDO $pdo, ?int $uid, string $email): array
    {
        if (!self::termsReady($pdo)) return [];
        $email = strtolower(trim($email));
        $st = $pdo->prepare('SELECT t.*, g.display_name AS granted_name, e.display_name AS ended_name FROM client_credit_terms t LEFT JOIN users g ON g.id = t.granted_by LEFT JOIN users e ON e.id = t.ended_by WHERE ' . ($uid ? 't.user_id = ? OR ' : '') . 't.email = ? ORDER BY t.id DESC');
        $st->execute($uid ? [$uid, $email] : [$email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** A copy's pay mode: terms only while the account still has them, and never on a test copy. */
    private static function copyMode(PDO $pdo, array $o, bool $test): string
    {
        if (!self::onTerms($o)) return self::offeredMode((string) ($o['pay_mode'] ?? 'full'));
        return !$test && self::termsFor($pdo, $o) ? 'terms' : 'full';
    }

    /**
     * Only Jason grants (or ends) credit terms (0178 §4: "only Jason grants them to a client's account"), on his own Founder