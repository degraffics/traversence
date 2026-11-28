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
