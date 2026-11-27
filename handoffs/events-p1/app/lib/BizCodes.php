<?php
// app/lib/BizCodes.php — a business's own discount codes (decisions/0121). Jason, 2026-10-24: "for listings we don't need
// to set rules for codes": no floor, no approval; the business sets the offer (a percent off, or an amount off) and,
// if it wants, dates, a usage limit, once per customer and a smallest order. A code goes on a quote (the team adds it,
// or the customer types it on their quote page before approving); it counts as used when the quote is approved.
// Lives in the business's walled data (b_biz_codes, b_biz_code_uses), read through the scope like its other tools.
// A code a business attaches to an event it takes part in is published there by it (Events, decisions/0134's note #1605);
// a change saved here, or its last use, updates or withdraws that copy (published()).
declare(strict_types=1);

require_once __DIR__ . '/Scope.php';

final class BizCodes
{
    public const KINDS = ['percent' => 'A percent off', 'amount' => 'An amount off'];

    private static function t(string $x): string { return Scope::t($x); }

    public static function clean(string $c): string { return strtoupper(substr(preg_replace('/[^A-Za-z0-9-]/', '', $c), 0, 40)); }

    /** "15% off" or "$20.00 off". */
    public static function offer(string $kind, int $value): string
    {
        return $kind === 'amount' ? '$' . number_format($value / 100, 2) . ' off' : $value . '% off';
    }

    public static function all(PDO $pdo, string $q = ''): array
    {
        $pdo = Scope::pdo($pdo);
        $st = $pdo->prepare('SELECT * FROM ' . self::t('biz_codes') . ($q !== '' ? ' WHERE code LIKE ? OR note LIKE ?' : '') . ' ORDER BY active DESC, created_at DESC');
        $st->execute($q !== '' ? ["%$q%", "%$q%"] : []);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        $pdo = Scope::pdo($pdo);
        $st = $pdo->prepare('SELECT * FROM ' . self::t('biz_codes') . ' WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Makes or changes a code. */
    public static function save(PDO $pdo, int $id, array $in, int $uid): int
    {
        $was = $id ? (string) (self::get($pdo, $id)['code'] ?? '') : '';
        $pdo = Scope::pdo($pdo);
        $code = self::clean((string) ($in['code'] ?? ''));
        if (strlen($code) < 3) throw new InvalidArgumentException('A code is 3 to 40 letters, numbers or dashes, like SPRING15.');
        $kind = ($in['kind'] ?? '') === 'amount' ? 'amount' : 'percent';
        $raw = str_replace([',', '$', '%', ' '], '', trim((string) ($in['value'] ?? '')));
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) throw new InvalidArgumentException($kind === 'percent' ? 'Write the percent off, like 15.' : 'Write the amount off, like 20.00.');
        $value = $kind === 'percent' ? (int) round((float) $raw) : (int) round((float) $raw * 100);
        if ($kind === 'percent' && $value > 100) throw new InvalidArgumentException('A percent off is 100 at most.');
        $min = trim((string) ($in['min_order'] ?? '')) === '' ? 0 : (int) round((float) str_replace([',', '$'], '', (string) $in['min_order']) * 100);
        $d = function (string $k) use ($in): ?string { $v = trim((string) ($in[$k] ?? '')); if ($v === '') return null; if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) throw new InvalidArgumentException('Write dates like 2026-12-31.'); return $v; };
        $starts = $d('starts'); $ends = $d('ends');
        if ($starts && $ends && $ends < $starts) throw new InvalidArgumentException('It ends before it starts.');
        $vals = [$code, $kind, $value, max(0, $min), $starts, $ends, max(0, (int) ($in['max_uses'] ?? 0)), ($in['once_each'] ?? '1') === '0' ? 0 : 1, ($in['active'] ?? '1') === '0' ? 0 : 1, mb_substr(trim((string) ($in['note'] ?? '')), 0, 200)];
        try {
            if ($id) { $pdo->prepare('UPDATE ' . self::t('biz_codes') . ' SET code = ?, kind = ?, value = ?, min_order_cents = ?, starts = ?, ends = ?, max_uses = ?, once_each = ?, active = ?, note = ? WHERE id = ?')->execute([...$vals, $id]); self::published($pdo, $id, $was); return $id; }
            $pdo->prepare('INSERT INTO ' . self::t('biz_codes') . ' (owner_id, code, kind, value, min_order_cents, starts, ends, max_uses, once_each, active, note, created_by, created_at) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$vals, $uid ?: null, date('Y-m-d H:i:s')]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $x) {
            if ((int) ($x->errorInfo[1] ?? 0) === 1062) throw new InvalidArgumentException('You already have a code ' . $code . '.');
            throw $x;
        }
    }

    public static function byCode(PDO $pdo, string $code): ?array
    {
        $pdo = Scope::pdo($pdo);
        $st = $pdo->prepare('SELECT * FROM ' . self::t('biz_codes') . ' WHERE code = ?'); $st->execute([self::clean($code)]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Can this code go on this quote? Returns the code, or says plainly why not. */
    public static function check(PDO $pdo, string $code, string $email, int $subtotal, int $quoteId = 0): array
    {
        $pdo = Scope::pdo($pdo);
        $c = self::byCode($pdo, $code);
        $today = date('Y-m-d');
        if (!$c || !(int) $c['active']) throw new InvalidArgumentException('That code isn\'t one we know, or it\'s switched off.');
        if ($c['starts'] && $today < $c['starts']) throw new InvalidArgumentException('That code starts on ' . date('F j', strtotime((string) $c['starts'])) . '.');
        if ($c['ends'] && $today > $c['ends']) throw new InvalidArgumentException('That code ended on ' . date('F j', strtotime((string) $c['ends'])) . '.');
        if ((int) $c['max_uses'] && (int) $c['uses'] >= (int) $c['max_uses']) throw new InvalidArgumentException('That code has been used up.');
        if ((int) $c['min_order_cents'] && $subtotal < (int) $c['min_order_cents']) throw new InvalidArgumentException('That code is for orders of $' . number_format((int) $c['min_order_cents'] / 100, 2) . ' or more.');
        if ((int) $c['once_each'] && $email !== '') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM ' . self::t('biz_code_uses') . ' WHERE code_id = ? AND email = ? AND quote_id <> ?'); $st->execute([(int) $c['id'], strtolower($email), $quoteId]);
            if ((int) $st->fetchColumn()) throw new InvalidArgumentException('That code has been used on your account already.');
        }
        return $c;
    }

    /** The amount off a subtotal (never more than it). */
    public static function off(string $kind, int $value, int $subtotal): int
    {
        if ($kind === '' || $value <= 0 || $subtotal <= 0) return 0;
        return min($subtotal, $kind === 'amount' ? $value : (int) round($subtotal * $value / 100));
    }

    /** Counted when the quote it's on is approved: one use, with what it took off. */
    public static function redeem(PDO $pdo, array $q, int $offCents): void
    {
        $pdo = Scope::pdo($pdo);
        if (!(int) ($q['code_id'] ?? 0)) return;
        $pdo->prepare('INSERT INTO ' . self::t('biz_code_uses') . ' (owner_id, code_id, quote_id, email, off_cents, created_at) VALUES (0, ?, ?, ?, ?, ?)')->execute([(int) $q['code_id'], (int) $q['id'], strtolower((string) $q['cust_email']), $offCents, date('Y-m-d H:i:s')]);
        $pdo->prepare('UPDATE ' . self::t('biz_codes') . ' SET uses = uses + 1 WHERE id = ?')->execute([(int) $q['code_id']]);
        $c = self::get($pdo, (int) $q['code_id']); if ($c) self::published($pdo, (int) $c['id'], (string) $c['code']);   // its last use takes it off its events
    }

    /**
     * The code as its business published it on events it takes part in (decisions/0134's note #1605: published by its owner,
     * like a post): updated or withdrawn whenever the code changes here, from the owner's own request. A failure is logged;
     * the owner's next visit to Events checks again (Events::recheckCodes).
     */
    private static function published(PDO $pdo, int $id, string $was): void
    {
        if (!Scope::isBusiness() || $was === '' || !is_file(__DIR__ . '/Events.php')) return;
        try { require_once __DIR__ . '/Events.php'; Events::codeChanged(Database::connection(), Scope::owner(), $was, self::get($pdo, $id)); }
        catch (Throwable $x) { error_log('BizCodes::published ' . Scope::owner() . ' ' . $was . ': ' . $x->getMessage()); }
    }

    /** Uses of a code, newest first. */
    public static function uses(PDO $pdo, int $id): array
    {
        $pdo = Scope::pdo($pdo);
        $st = $pdo->prepare('SELECT * FROM ' . self::t('biz_code_uses') . ' WHERE code_id = ? ORDER BY id DESC LIMIT 100'); $st->execute([$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
