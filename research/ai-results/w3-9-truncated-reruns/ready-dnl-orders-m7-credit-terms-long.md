=== FILE: files/app/lib/CreditTerms.php ===
<?php
// app/lib/CreditTerms.php — credit terms on a client's account (decisions/0178 §4; Orders M7; design §3.2 "On credit
// terms", §3.5's chain "credit terms ⟶ due by the terms' date").
//
//   Net 15 and Net 30, granted by Jason only, to a client's account, after the credit process. Terms replace "invoice
//   later" (model 1.8). A deposit can still be asked to offset risk. An order on terms is due by its terms' date, and the
//   vendor and delivery don't wait for payment ("balance due per terms"); a deposit asked on it still comes before the
//   vendor.
//
//   client_credit_terms   one row per ask: asked (waiting for Jason) → granted | declined | withdrawn; a granted row
//                         is ended later (by Jason, or replaced by a new grant) and kept. Nothing is deleted.
//
// An order counts as on terms when its due_terms is net15/net30 and the client's account had a grant covering it when
// the order was sent (a draft: now). Ending a client's terms stops new orders; orders already sent keep theirs.
// Nothing here moves money or reaches QuickBooks.
declare(strict_types=1);

final class CreditTerms
{
    /** The terms Jason grants (0178 §4), as ClientOrders::DUE_TERMS names them: key => [words, days]. */
    public const TERMS = ['net15' => ['Net 15', 15], 'net30' => ['Net 30', 30]];
    public const STATUS = ['asked' => 'Waiting for Jason\'s OK', 'granted' => 'Granted', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn', 'ended' => 'Ended'];
    public const MIGRATION = 'app/migrations/2027-02-01_client_credit_terms.sql';

    /** Has the migration run (per connection, so a test can open a fresh one)? */
    public static function ready(PDO $pdo): bool
    {
        static $r = [];
        $k = spl_object_id($pdo);
        if (!isset($r[$k])) { try { $pdo->query('SELECT 1 FROM client_credit_terms LIMIT 0'); $r[$k] = true; } catch (Throwable) { $r[$k] = false; } }
        return $r[$k];
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    public static function days(string $t): int { return (int) (self::TERMS[$t][1] ?? 0); }

    public static function words(string $t): string { return (string) (self::TERMS[$t][0] ?? 'Due on receipt'); }

    /** Does a grant of $have cover an order on $want (Net 30 covers Net 15)? */
    public static function covers(string $have, string $want): bool
    {
        return self::days($want) > 0 && self::days($have) >= self::days($want);
    }

    /** The client's live grant at $at (default now), the longest if more than one; null when none. Never throws. */
    public static function live(PDO $pdo, ?int $uid, string $email, ?string $at = null): ?array
    {
        if (!self::ready($pdo)) return null;
        $email = strtolower(trim($email)); $uid = (int) ($uid ?? 0);
        if ($uid <= 0 && $email === '') return null;
        $at ??= self::now();
        try {
            $st = $pdo->prepare("SELECT * FROM client_credit_terms WHERE status IN ('granted', 'ended') AND decided_at IS NOT NULL AND decided_at <= ?
                AND (ended_at IS NULL OR ended_at > ?) AND ((? > 0 AND user_id = ?) OR (? <> '' AND LOWER(email) = ?)) ORDER BY id DESC");
            $st->execute([$at, $at, $uid, $uid, $email, $email]);
            $best = null;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) if (!$best || self::days((string) $g['terms']) > self::days((string) $best['terms'])) $best = $g;
            return $best;
        } catch (Throwable $x) { error_log('credit terms live: ' . $x->getMessage()); return null; }
    }

    /**
     * Is this order on credit terms (its vendor and delivery don't wait for payment)? Its due_terms is Net 15 / Net 30 and
     * the client's account had a grant covering it when the order was sent (a draft: now). Never throws: false on doubt,
     * so the payment holds stay.
     */
    public static function onTerms(PDO $pdo, array $o): bool
    {
        try {
            $t = (string) ($o['due_terms'] ?? 'receipt');
            if (!isset(self::TERMS[$t]) || (int) ($o['created_by'] ?? 0) <= 0) return false;
            $at = ($o['status'] ?? '') !== 'draft' && !empty($o['sent_at']) ? (string) $o['sent_at'] : null;
            $g = self::live($pdo, (int) ($o['user_id'] ?? 0) ?: null, (string) ($o['bill_email'] ?? ''), $at);
            return $g !== null && self::covers((string) $g['terms'], $t);
        } catch (Throwable $x) { error_log('credit terms onTerms: ' . $x->getMessage()); return false; }
    }

    /** Why an order for this client can't be given $terms now ('' when it can): the words staff see on Terms. */
    public static function whyNot(PDO $pdo, ?int $uid, string $email, string $terms): string
    {
        if (!isset(self::TERMS[$terms])) return '';
        $w = self::words($terms);
        if (!self::ready($pdo)) return $w . ' is credit terms, and credit terms wait for their database update (' . self::MIGRATION . '). Choose Due on receipt for now.';
        $g = self::live($pdo, $uid, $email);
        if ($g && self::covers((string) $g['terms'], $terms)) return '';
        return $w . ' is credit terms: only Jason grants them to a client\'s account, after the credit process. '
            . ($g ? 'This client\'s account has ' . self::words((string) $g['terms']) . ' only.' : 'This client\'s account has none.')
            . ' Ask Jason for them for this client, or choose Due on receipt.';
    }

    /** Only the Founder (Jason) grants, declines or ends terms, on his own Founder staff login (as Orders::canDecideRefund). Fails closed. */
    public static function canGrant(): array
    {
        try {
            require_once __DIR__ . '/../admin/Access.php';
            $s = Access::staff();
            if ($s === null) return [false, 'Only Jason grants credit terms, signed in on his own Founder staff login'];
            require_once __DIR__ . '/Workspaces.php';
            return Workspaces::levelOf($s)[0] === 'founder' ? [true, ''] : [false, 'Only Jason (the Founder) grants credit terms'];
        } catch (Throwable) { return [false, 'Only Jason grants credit terms, signed in on his own Founder staff login']; }
    }

    // ---- reading ---------------------------------------------------------------------------------------------------------

    public static function get(PDO $pdo, int $id): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM client_credit_terms WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A client's asks and grants, newest first (their history: nothing is deleted). */
    public static function history(PDO $pdo, ?int $uid, string $email): array
    {
        if (!self::ready($pdo)) return [];
        $email = strtolower(trim($email)); $uid = (int) ($uid ?? 0);
        $st = $pdo->prepare("SELECT * FROM client_credit_terms WHERE (? > 0 AND user_id = ?) OR (? <> '' AND LOWER(email) = ?) ORDER BY id DESC");
        $st->execute([$uid, $uid, $email, $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Every ask waiting for Jason (his cases), oldest first. */
    public static function waiting(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        return $pdo->query("SELECT * FROM client_credit_terms WHERE status = 'asked' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---- asking (staff), deciding (Jason) ------------------------------------------------------------------------------

    /**
     * Staff ask Jason for terms on a client's account, with what the credit process found. Nothing changes until he
     * grants. One ask waits per client. Returns its id.
     */
    public static function ask(PDO $pdo, ?int $uid, string $email, string $terms, string $creditNote, int $actor): int
    {
        if (!self::ready($pdo)) throw new RuntimeException('Credit terms wait for their database update (' . self::MIGRATION . ').');
        if (!isset(self::TERMS[$terms])) throw new RuntimeException('Choose Net 15 or Net 30.');
        $email = strtolower(trim($email)); $uid = (int) ($uid ?? 0);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The client\'s email is needed: their orders find their terms by it.');
        $note = trim(preg_replace('/\s+/u', ' ', $creditNote));
        if (mb_strlen($note) < 3) throw new RuntimeException('Say what the credit process found (references, the application, the limit asked): Jason reads it before he grants.');
        foreach (self::history($pdo, $uid ?: null, $email) as $r) if ($r['status'] === 'asked') throw new RuntimeException(self::words((string) $r['terms']) . ' is already waiting for Jason\'s OK for this client: withdraw it first to change it.');
        if (($g = self::live($pdo, $uid ?: null, $email)) && self::covers((string) $g['terms'], $terms)) throw new RuntimeException('This client\'s account has ' . self::words((string) $g['terms']) . ' already.');
        $pdo->prepare("INSERT INTO client_credit_terms (user_id, email, terms, status, credit_note, asked_by, asked_at) VALUES (?, ?, ?, 'asked', ?, ?, ?)")
            ->execute([$uid ?: null, mb_substr($email, 0, 190), $terms, mb_substr($note, 0, 400), $actor ?: null, self::now()]);
        return (int) $pdo->lastInsertId();
    }

    /** An ask taken back before Jason decides it: nothing had changed. */
    public static function withdraw(PDO $pdo, int $id, int $actor): void
    {
        $r = self::get($pdo, $id);
        if (!$r || $r['status'] !== 'asked') throw new RuntimeException('That ask isn\'t waiting any more.');
        $pdo->prepare("UPDATE client_credit_terms SET status = 'withdrawn', decided_by = ?, decided_at = ? WHERE id = ? AND status = 'asked'")->execute([$actor ?: null, self::now(), $id]);
    }

    /** Jason grants it: the client's account has these terms from now; an earlier grant of theirs ends (replaced). */
    public static function grant(PDO $pdo, int $id, string $note, int $actor): string
    {
        [$ok, $no] = self::canGrant(); if (!$ok) throw new RuntimeException($no . '.');
        $r = self::get($pdo, $id);
        if (!$r || $r['status'] !== 'asked') throw new RuntimeException('That ask isn\'t waiting any more.');
        $now = self::now();
        $st = $pdo->prepare("UPDATE client_credit_terms SET status = 'granted', decided_by = ?, decided_at = ?, decided_note = ? WHERE id = ? AND status = 'asked'");
        $st->execute([$actor, $now, mb_substr(trim($note), 0, 400), $id]);
        if (!$st->rowCount()) throw new RuntimeException('That ask isn\'t waiting any more.');
        $uid = (int) ($r['user_id'] ?? 0); $email = strtolower((string) $r['email']);
        $pdo->prepare("UPDATE client_credit_terms SET status = 'ended', ended_by = ?, ended_at = ?, ended_note = ? WHERE status = 'granted' AND id <> ? AND ((? > 0 AND user_id = ?) OR (? <> '' AND LOWER(email) = ?))")
            ->execute([$actor, $now, 'Replaced by ' . self::words((string) $r['terms']), $id, $uid, $uid, $email, $email]);
        return self::words((string) $r['terms']) . ' granted to ' . $r['email'] . '\'s account: orders sent from now can be on these terms.';
    }

    /** Jason declines it, with his reason for the one who asked: nothing changes. */
    public static function decline(PDO $pdo, int $id, string $why, int $actor): void
    {
        [$ok, $no] = self::canGrant(); if (!$ok) throw new RuntimeException($no . '.');
        if (trim($why) === '') throw new RuntimeException('Say why, for the one who asked.');
        $st = $pdo->prepare("UPDATE client_credit_terms SET status = 'declined', decided_by = ?, decided_at = ?, decided_note = ? WHERE id = ? AND status = 'asked'");
        $st->execute([$actor, self::now(), mb_substr(trim($why), 0, 400), $id]);
        if (!$st->rowCount()) throw new RuntimeException('That ask isn\'t waiting any more.');
    }

    /** Jason ends a client's terms: new orders can't take them; orders already sent on them keep them. */
    public static function end(PDO $pdo, int $id, string $why, int $actor): void
    {
        [$ok, $no] = self::canGrant(); if (!$ok) throw new RuntimeException($no . '.');
        if (trim($why) === '') throw new RuntimeException('Say why the terms end: it\'s kept with the account.');
        $st = $pdo->prepare("UPDATE client_credit_terms SET status = 'ended', ended_by = ?, ended_at = ?, ended_note = ? WHERE id = ? AND status = 'granted'");
        $st->execute([$actor, self::now(), mb_substr(trim($why), 0, 400), $id]);
        if (!$st->rowCount()) throw new RuntimeException('Those terms aren\'t live any more.');
    }
}
=== END FILE ===
=== FILE: files/app/migrations/2027-02-01_client_credit_terms.sql ===
-- Orders M7: credit terms on a client's account (decisions/0178 §4). Safe to run twice: it only creates a table that
-- isn't there yet. No UPDATE or DELETE. Rename the date to the next in the live migrations' sequence before shipping.
CREATE TABLE IF NOT EXISTS client_credit_terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  terms VARCHAR(10) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'asked',
  credit_note VARCHAR(400) NOT NULL DEFAULT '',
  asked_by INT UNSIGNED NULL,
  asked_at DATETIME NOT NULL,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decided_note VARCHAR(400) NOT NULL DEFAULT '',
  ended_by INT UNSIGNED NULL,
  ended_at DATETIME NULL,
  ended_note VARCHAR(400) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_cct_user (user_id),
  KEY idx_cct_email (email),
  KEY idx_cct_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
=== END FILE ===
=== FILE: files/app/lib/ClientOrders.php.region-1.php ===
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
=== END FILE ===
=== FILE: files/app/lib/ClientOrders.php.region-2.php ===
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
=== END FILE ===
=== FILE: files/app/lib/ClientOrders.php.region-3.php ===
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
=== END FILE ===
=== FILE: files/app/lib/ClientOrders.php.region-4.php ===
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
=== END FILE ===
=== FILE: tests/credit-terms-tests.php ===
<?php
// tools/orders-tests/credit-terms-tests.php — Orders M7, credit terms (decisions/0178 §4). Plain PHP, prints PASS/FAIL.
// Runs on an in-memory SQLite database (pdo_sqlite): it never touches the live database, and no code path here reaches
// QuickBooksPay (nothing is charged, refunded or posted; guardReal()/testHere() are not needed and not bypassed).
// Run AFTER the four ClientOrders.php regions are applied. APP_DIR overrides where app/ is.
declare(strict_types=1);

$app = rtrim((string) (getenv('APP_DIR') ?: __DIR__ . '/../../app'), '/');
$pass = 0; $fail = 0; $skip = 0;
function ok(string $name, bool $cond): void { global $pass, $fail; if ($cond) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name\n"; } }
function skip(string $name, string $why): void { global $skip; $skip++; echo "SKIP  $name ($why)\n"; }
function throws(callable $f, string $class = 'RuntimeException'): bool { try { $f(); return false; } catch (Throwable $x) { return $x instanceof $class; } }
register_shutdown_function(function () { global $pass, $fail, $skip; echo "\n$pass passed, $fail failed, $skip skipped\n"; });

if (!extension_loaded('pdo_sqlite')) { skip('all', 'pdo_sqlite not loaded'); exit(0); }
require_once $app . '/lib/CreditTerms.php';

// ---- before the migration: nothing is on terms, and staff are told why ----
$bare = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
ok('not ready before the migration', !CreditTerms::ready($bare));
ok('no order is on terms before the migration', !CreditTerms::onTerms($bare, ['created_by' => 5, 'bill_email' => 'a@x.test', 'due_terms' => 'net30', 'status' => 'draft']));
ok('Net 30 refused before the migration, naming it', str_contains(CreditTerms::whyNot($bare, null, 'a@x.test', 'net30'), 'client_credit_terms'));

// ---- the table, as the migration makes it (SQLite types) ----
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE client_credit_terms (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, email TEXT NOT NULL DEFAULT '', terms TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'asked', credit_note TEXT NOT NULL DEFAULT '', asked_by INTEGER, asked_at TEXT NOT NULL, decided_by INTEGER, decided_at TEXT,
    decided_note TEXT NOT NULL DEFAULT '', ended_by INTEGER, ended_at TEXT, ended_note TEXT NOT NULL DEFAULT '')");
$pdo->exec("CREATE TABLE order_ledger (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, kind TEXT, amount_cents INTEGER, request_key TEXT)");
$pdo->exec("CREATE TABLE order_events (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, line_id INTEGER, event TEXT, note TEXT, actor_user_id INTEGER, created_at TEXT)");
$seed = $pdo->prepare("INSERT INTO client_credit_terms (user_id, email, terms, status, asked_at, decided_at, ended_at) VALUES (?, ?, ?, ?, '2026-10-30 09:00:00', ?, ?)");
$seed->execute([null, 'a@x.test', 'net30', 'granted', '2026-11-01 10:00:00', null]);
$seed->execute([null, 'b@x.test', 'net15', 'granted', '2026-11-01 10:00:00', null]);
$seed->execute([null, 'c@x.test', 'net30', 'ended', '2026-11-01 10:00:00', '2026-11-10 10:00:00']);
$seed->execute([null, 'd@x.test', 'net30', 'granted', '2026-11-20 10:00:00', null]);
$seed->execute([null, 'e@x.test', 'net30', 'declined', '2026-11-01 10:00:00', null]);
$seed->execute([77, 'old@x.test', 'net30', 'granted', '2026-11-01 10:00:00', null]);
ok('ready after the migration', CreditTerms::ready($pdo));

ok('Net 30 covers Net 15', CreditTerms::covers('net30', 'net15'));
ok('Net 15 does not cover Net 30', !CreditTerms::covers('net15', 'net30'));
ok('nothing covers Due on receipt (it isn\'t terms)', !CreditTerms::covers('net30', 'receipt'));

$o = fn (array $x) => $x + ['created_by' => 5, 'user_id' => null, 'bill_email' => 'a@x.test', 'due_terms' => 'net30', 'status' => 'draft', 'sent_at' => null];
ok('granted Net 30: a draft on Net 30 is on terms', CreditTerms::onTerms($pdo, $o([])));
ok('email matched without case', CreditTerms::onTerms($pdo, $o(['bill_email' => 'A@X.test'])));
ok('Due on receipt is never on terms', !CreditTerms::onTerms($pdo, $o(['due_terms' => 'receipt'])));
ok('a shop order (no created_by) is never on terms', !CreditTerms::onTerms($pdo, $o(['created_by' => 0])));
ok('granted Net 15 only: Net 30 is not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'b@x.test'])));
ok('granted Net 15: Net 15 is on terms', CreditTerms::onTerms($pdo, $o(['bill_email' => 'b@x.test', 'due_terms' => 'net15'])));
ok('ended terms: an order sent while live keeps them', CreditTerms::onTerms($pdo, $o(['bill_email' => 'c@x.test', 'status' => 'pending', 'sent_at' => '2026-11-05 09:00:00'])));
ok('ended terms: an order sent after the end is not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'c@x.test', 'status' => 'pending', 'sent_at' => '2026-11-12 09:00:00'])));
ok('ended terms: a new draft is not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'c@x.test'])));
ok('granted after the order was sent: not on terms (send it again)', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'd@x.test', 'status' => 'sent', 'sent_at' => '2026-11-15 09:00:00'])));
ok('declined: not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'e@x.test'])));
ok('a member\'s grant found by account', CreditTerms::onTerms($pdo, $o(['user_id' => 77, 'bill_email' => 'new@x.test'])));

ok('whyNot: granted client may have Net 30', CreditTerms::whyNot($pdo, null, 'a@x.test', 'net30') === '');
ok('whyNot: Net 15 client told "Net 15 only"', str_contains(CreditTerms::whyNot($pdo, null, 'b@x.test', 'net30'), 'Net 15 only'));
ok('whyNot: no grant says only Jason grants', str_contains(CreditTerms::whyNot($pdo, null, 'z@x.test', 'net30'), 'only Jason grants'));
ok('whyNot: Due on receipt is always allowed', CreditTerms::whyNot($pdo, null, 'z@x.test', 'receipt') === '');

ok('ask without what the credit process found is refused', throws(fn () => CreditTerms::ask($pdo, null, 'z@x.test', 'net30', '  ', 9)));
ok('ask for a made-up term is refused', throws(fn () => CreditTerms::ask($pdo, null, 'z@x.test', 'net60', 'refs checked', 9)));
$rid = 0; try { $rid = CreditTerms::ask($pdo, null, 'z@x.test', 'net30', 'Two trade references, 3 years in business', 9); } catch (Throwable $x) { echo '      ' . $x->getMessage() . "\n"; }
ok('ask recorded, waiting for Jason', $rid > 0 && (CreditTerms::get($pdo, $rid)['status'] ?? '') === 'asked' && count(CreditTerms::waiting($pdo)) === 1);
ok('asking doesn\'t put anything on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'z@x.test'])));
ok('a second ask for the same client is refused', throws(fn () => CreditTerms::ask($pdo, null, 'z@x.test', 'net15', 'again', 9)));
ok('an ask for terms the client already has is refused', throws(fn () => CreditTerms::ask($pdo, null, 'a@x.test', 'net15', 'refs', 9)));
CreditTerms::withdraw($pdo, $rid, 9);
ok('withdrawn: nothing waits', CreditTerms::waiting($pdo) === [] && CreditTerms::get($pdo, $rid)['status'] === 'withdrawn');

// ---- the payment holds (ClientOrders, patched) ----
$co = false;
try { require_once $app . '/lib/ClientOrders.php'; $co = class_exists('ClientOrders') && method_exists('CreditTerms', 'onTerms'); } catch (Throwable $x) { skip('holds', 'ClientOrders.php didn\'t load here: ' . $x->getMessage()); }
if ($co) {
    $ord = fn (array $x) => $x + ['id' => 501, 'status' => 'pending', 'created_by' => 5, 'user_id' => null, 'bill_email' => 'a@x.test', 'due_terms' => 'net30',
        'sent_at' => '2026-11-05 09:00:00', 'total_cents' => 50000, 'deposit_cents' => 0, 'deposit_pct' => 0, 'pay_mode' => 'full', 'qb_env' => 'production', 'order_no' => 501, 'stage' => ''];
    ok('on terms: delivery isn\'t held for the balance', ClientOrders::shipHold($pdo, $ord([])) === '');
    ok('on terms: the vendor isn\'t held for payment', ClientOrders::vendorHold($pdo, $ord([])) === '');
    ok('no grant, Net 30 typed: delivery still held (0177 §4 unchanged)', ClientOrders::shipHold($pdo, $ord(['bill_email' => 'z@x.test'])) !== '');
    ok('no grant, Net 30 typed: the vendor still held', ClientOrders::vendorHold($pdo, $ord(['bill_email' => 'z@x.test'])) !== '');
    ok('Due on receipt: delivery held as before', ClientOrders::shipHold($pdo, $ord(['due_terms' => 'receipt'])) !== '');
    ok('on terms with a deposit asked: the vendor waits for the deposit', ClientOrders::vendorHold($pdo, $ord(['pay_mode' => 'deposit', 'deposit_cents' => 20000])) !== '');
    ok('on terms with a deposit asked: delivery doesn\'t wait for the rest', ClientOrders::shipHold($pdo, $ord(['pay_mode' => 'deposit', 'deposit_cents' => 20000])) === '');
}

// ---- last: only Jason grants (Access.php may need the site; a refusal is what's tested) ----
$rid2 = CreditTerms::ask($pdo, null, 'y@x.test', 'net15', 'Bank reference checked', 9);
ok('grant without a Founder staff login is refused', throws(fn () => CreditTerms::grant($pdo, $rid2, 'ok', 9)));
ok('decline without a Founder staff login is refused', throws(fn () => CreditTerms::decline($pdo, $rid2, 'no', 9)));
ok('still waiting after the refusals', CreditTerms::get($pdo, $rid2)['status'] === 'asked' && !CreditTerms::onTerms($pdo, $o(['bill_email' => 'y@x.test', 'due_terms' => 'net15'])));
exit($fail ? 1 : 0);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
### Can my business pay on Net 15 or Net 30 terms? (coming)

Yes, once your account has credit terms. Ask our team: we'll take you through a short credit check, and our founder
decides. Terms are Net 15 or Net 30.

With terms, you approve the order and we start the work and deliver it without waiting for payment. The invoice is due
by the date on it: 15 or 30 days. On some orders we may still ask for a deposit when you approve. The rest follows your
terms.

Questions: support@traversence.com.

### Can I approve an order now and pay the invoice later? (coming)

"Pay later" is now credit terms. An account with Net 15 or Net 30 terms pays each order's invoice by the date on it.
Without terms, an order is paid in full when you approve it, or a deposit is paid then and the rest before delivery.
To ask for terms, write to support@traversence.com.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "orders-m7-credit-terms",
  "gate": "5 (money policy, decisions/0178 §4: Jason's pop-up; the session manager to confirm the gate)",
  "what": "SKIPPED FIRST, as the attached documents say: (1) M2b, how an order goes in two legs (PR #1346), paused by Jason 2026-11-24 ('Pause for now. add to work queue.'): nothing here touches its fields or flow; (2) Order #12's books ($1,111.14 invoiced against $809.10 of lines), waiting for Jason after the holiday: nothing here reads or changes Order #12; (3) Work-queue parents are in the live database and not attached, so this release doesn't link to them. The attached draft (job 19, dnl-orders-m7-credit-terms.md) was EMPTY: there was nothing to review, so this is drafted from decisions/0178 §4, the design §3.2/§3.5/§6 and the live files. WHAT IT DOES: credit terms (Net 15 / Net 30) on a client's account, asked for by staff with what the credit process found and granted, declined or ended by Jason only (his own Founder staff login, as Orders::canDecideRefund). An order on terms (its due_terms Net 15/30, and a grant covering it when it was sent) no longer waits for payment before the vendor (vendorHold) or delivery (shipHold); a deposit asked on it still comes before the vendor. Staff can't give an order Net 15/30 unless the client has a grant (saveTerms; orders that already have them keep them, as invoice later does). The client's email says the terms in place of 'Pay in full on approval' (send). Orders on 'Due on receipt', and orders whose Net terms were typed without a grant, keep 0177 §4's holds exactly as today.",
  "files": [
    "files/app/lib/CreditTerms.php",
    "files/app/migrations/2027-02-01_client_credit_terms.sql",
    "files/app/lib/ClientOrders.php.region-1.php",
    "files/app/lib/ClientOrders.php.region-2.php",
    "files/app/lib/ClientOrders.php.region-3.php",
    "files/app/lib/ClientOrders.php.region-4.php",
    "tests/credit-terms-tests.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "decisions/0178 §4: Net 15 and Net 30, granted by Jason only, after the credit process; a deposit can still be asked; due by the terms' date; vendor and delivery don't wait (model 1.8)",
    "decisions/0178 'Not changed': 0177 §4's holds stay for every order not on terms; QuickBooksPay::guardReal() untouched (nothing here reaches QuickBooks)",
    "decisions/0101 note 2026-11-23: invoice later isn't offered; terms replace it (the same 'keeps what it has, can't newly choose it' rule is used for Net terms without a grant)",
    "research/order-management-design-2026-11-23.md §3.2 'On credit terms', §3.5 chain 'credit terms ⟶ due by the terms' date', §6 M7",
    "Drafting choices for review (not decided anywhere): a grant counts for orders sent while it was live (ending terms doesn't pull them from orders already sent); Net 30 covers Net 15; a new grant ends the client's earlier one; only Jason ends terms; a client is matched by account id or by email"
  ],
  "tests": [
    "tests/credit-terms-tests.php → copy to tools/orders-tests/credit-terms-tests.php; php tools/orders-tests/credit-terms-tests.php (needs pdo_sqlite; in-memory, never the live database; apply the four regions first)"
  ],
  "risks": [
    "NOTHING HERE WAS RUN OR VERIFIED: no test, lint, migration or zip check was run; the lane session must do all of it",
    "The job 19 draft was empty: this release had no prior draft to check against",
    "Queue order: M7 is item 6 of the Orders queue, after item 0 (M3's first fix: 'the order tests green again') and M3–M6; the existing order tests may not be green, so a failure elsewhere may not be this release's. Shipping M7 ahead of M3–M6 needs the session manager's OK",
    "No screens: the admin pages (admin/client-order.php Terms, the client's account) and Jason's Pulse case for an ask are not attached, so the Ask / Grant / Decline / End buttons and the Terms dropdown's hint are not built. Until they are, staff see the whyNot() words when they pick Net 15/30 for a client without a grant, and grants can only be made through CreditTerms::grant(). The FAQ entries are therefore 'coming'",
    "Behaviour change: staff today can pick Net 15/30 freely (it only set a due date). After this, a NEW choice of Net 15/30 needs Jason's grant; orders that already carry it keep it and keep their payment holds",
    "boardState() (the Money line's 'On credit terms' state, design §3.2) is not changed: the function is cut off in the attached ClientOrders.php. A terms order shows as 'owed' with 'past due' after its date, as today",
    "completeHold() / 0178 §6 not changed: a terms order delivered before its due date still becomes 'Delivered, balance due' for the admin. Whether a not-yet-due terms balance should wait quietly until its date is a question for Jason",
    "releaseAt(), mine(), dueDate(), hasItems() and proofGaps() are in the cut-off part of ClientOrders.php: the regions call them as the shown code does, unseen",
    "Time: grants are stamped with PHP's date(); orders.sent_at with MySQL NOW(). If PHP and MySQL time zones differ, a grant made minutes before a send could miss it (send again fixes it)",
    "Invoice-later orders aren't moved to terms: invoiceLaterChoose() still offers Send invoice / Release only",
    "Migration file name/date is a placeholder: rename it into the live sequence. No UPDATE/DELETE in it; CREATE TABLE IF NOT EXISTS is safe twice",
    "No money moves in any path here; tests use SQLite in memory and never load QuickBooksPay"
  ],
  "verify": [
    "ClientOrders.php is over 60 KB and was attached cut off: confirm each region's ANCHOR (saveTerms, shipHold, vendorHold, send) matches the live file byte for byte before replacing, then php -l",
    "file not shown: the rest of app/lib/ClientOrders.php (releaseAt, mine, dueDate, hasItems, boardState's end) — check the regions' calls against it",
    "file not shown: app/admin/Access.php and app/lib/Workspaces.php — CreditTerms::canGrant() mirrors Orders::canDecideRefund(); confirm Access::staff() and Workspaces::levelOf() as used there",
    "file not shown: admin/client-order.php and the Pulse case list — screens for Ask / Grant / Decline / End not built; plan them with the Design reviewer",
    "file not shown: the live migrations folder — pick the migration's date; confirm users.id is INT UNSIGNED (no foreign key is made)",
    "Run tools/orders-tests/credit-terms-tests.php after the regions: expect all PASS; the hold tests SKIP if ClientOrders.php can't load outside the site",
    "On staging: a client without a grant can't be given Net 30; with a grant, a full-pay order goes to the vendor and ships unpaid; a deposit order on terms waits for its deposit; the client's email says the terms",
    "Byte-check after sync before moving the line to 'Live' in handoffs/decided-not-live.md"
  ],
  "regions": true
}
=== END FILE ===