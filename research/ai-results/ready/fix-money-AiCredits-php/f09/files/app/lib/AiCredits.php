<?php
// app/lib/AiCredits.php — who pays for what a member asks our AI to do (Jason, 2026-10-30): no one causes Traversence an
// AI cost that a revenue stream doesn't cover. AI Assist is for members with a paid plan: their own (Personal Plus, Pro)
// or one on a business they own. Each such member gets a few free asks a day, then each ask uses one AI Assist credit
// from a plan they pay for (the plan's credits and any +50 packs, Billing::PLANS / ADDONS), their own first, then their
// businesses'. A trial has none until its first payment; a plan whose payment failed has none until it's paid. A
// backstop holds a member's month at half of what their plans pay a month, whatever the credits say. Each ask is booked
// in ai_jobs.charged_to ('free', 'user:<id>', 'listing:<id>'), so a business's credits are shared by its owners and
// nothing else keeps a count. An ask counts from the moment it's booked (still waiting or working included) until it
// fails, so two asks at once can't both use the last free ask or credit. When a count can't be read, nothing is left
// (it fails closed) and the state says 'unread'. AI Assist's asks and Write it for me (catalog
// descriptions, ItemWriter) count alike. Read by /ai/ and by the Chameleon Filter (a member's words go to Claude only when funded).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Billing.php';

final class AiCredits
{
    /** Free asks a day for a member with a paid plan, before credits: the default (Thresholds & gates tunes it). */
    public const FREE_PER_DAY = 3;
    /** The most a member's asks may cost us in a month, as a share of what their plans pay a month: the default (Thresholds & gates). */
    public const COVER = 0.5;

    /**
     * The plans that pay for this member's asks: [key => ['key', 'label', 'credits', 'used', 'left', 'cents' (a month),
     * 'unread' (its count couldn't be read, so it shows none left)]], their own first. Only active, paid plans; a
     * business's only when they are its owner.
     */
    public static function pots(PDO $pdo, int $uid): array
    {
        $out = [];
        $try = function (string $type, int $id, string $label) use ($pdo, &$out) {
            $s = Billing::current($pdo, $type, $id);
            if (!$s || $s['status'] !== 'active' || Billing::planPrice((string) $s['plan'], 'month') <= 0) return;
            $credits = (int) (Billing::PLANS[$s['plan']][4] ?? 0);
            $cents = Billing::planPrice((string) $s['plan'], (string) $s['cycle']) / ((string) $s['cycle'] === 'year' ? 12 : 1);
            foreach (Billing::addons($pdo, (int) $s['id']) as $a) { $credits += (int) (Billing::ADDONS[$a['addon']][3] ?? 0) * (int) $a['qty']; $cents += (int) (Billing::ADDONS[$a['addon']][1] ?? 0) * (int) $a['qty']; }
            $key = $type . ':' . $id;
            $used = self::count($pdo, 'charged_to = ?', [$key], date('Y-m-01'));
            $unread = $used === null;   // couldn't count: treat every credit as used (fails closed, f09)
            if ($unread) $used = $credits;
            $out[$key] = ['key' => $key, 'label' => $label . ' (' . Billing::PLANS[$s['plan']][0] . ')', 'credits' => $credits, 'used' => $used, 'left' => max(0, $credits - $used), 'cents' => (int) round($cents), 'unread' => $unread];
        };
        try { $try('user', $uid, 'Your plan'); } catch (Throwable $e) {}
        try {
            require_once __DIR__ . '/Team.php';
            foreach (Team::listingsOf($pdo, $uid) as $id => $l) if ($l['role'] === 'owner') $try('listing', (int) $id, (string) $l['name']);
        } catch (Throwable $e) {}
        return $out;
    }

    /**
     * Where a member stands: ['pots', 'funded' (a paid plan pays for their asks), 'free_left' (today), 'spent' and 'cover'
     * (this month, dollars), 'next' (what the next ask uses: 'free', a pot key, or null), 'why' (when null: 'no_plan',
     * 'used' or 'cover'), 'trial' (a plan of theirs that's still in its trial, by name), 'unread' (a count couldn't be
     * read, so asks are held: the page can say "we couldn't check your credits, try again shortly")].
     */
    public static function state(PDO $pdo, int $uid, string $want = ''): array
    {
        $pots = self::pots($pdo, $uid);
        $cents = array_sum(array_column($pots, 'cents'));
        $spent = 0.0;
        try { $st = $pdo->prepare("SELECT COALESCE(SUM(cost_micros), 0) FROM " . Database::t('ai_jobs') . " WHERE kind IN ('topic_assist', 'item_write') AND created_by = ? AND created_at >= ?"); $st->execute([$uid, date('Y-m-01')]); $spent = (int) $st->fetchColumn() / 1e6; } catch (Throwable $e) {}
        require_once __DIR__ . '/Thresholds.php';   // tuned in System Configuration → Thresholds & gates
        $cover = $cents / 100 * (float) Thresholds::get($pdo, 'ai_cover');
        $freeUsed = $pots ? self::count($pdo, "created_by = ? AND charged_to = 'free'", [$uid], date('Y-m-d')) : 0;
        $free = ($pots && $freeUsed !== null) ? max(0, self::freePerDay($pdo) - $freeUsed) : 0;   // couldn't count: no free asks (f09)
        $unread = $freeUsed === null || in_array(true, array_column($pots, 'unread'), true);
        $next = null; $why = null;
        if (!$pots) $why = 'no_plan';
        elseif ($spent >= $cover) $why = 'cover';
        elseif ($free) $next = 'free';
        elseif ($want !== '' && ($pots[$want]['left'] ?? 0) > 0) $next = $want;
        else { foreach ($pots as $k => $p) if ($p['left'] > 0) { $next = $k; break; } if (!$next) $why = 'used'; }
        $trial = '';
        if (!$pots) { try { $s = Billing::current($pdo, 'user', $uid); if ($s && $s['status'] === 'trial') $trial = (string) Billing::PLANS[$s['plan']][0]; } catch (Throwable $e) {} }
        return ['pots' => $pots, 'funded' => (bool) $pots, 'free_left' => $free, 'spent' => $spent, 'cover' => $cover, 'next' => $next, 'why' => $why, 'trial' => $trial, 'unread' => $unread];
    }

    /** Free asks a day for a member with a paid plan (Thresholds & gates; FREE_PER_DAY when not set). */
    public static function freePerDay(PDO $pdo): int
    {
        require_once __DIR__ . '/Thresholds.php';
        return (int) Thresholds::get($pdo, 'ai_free_asks');
    }

    /** True when a paid plan pays for this member's AI use (the Chameleon Filter reads their words with Claude only then). */
    public static function funded(PDO $pdo, ?int $uid): bool
    {
        return $uid ? (bool) self::pots($pdo, $uid) : false;
    }

    /**
     * Asks booked since $from that haven't failed: waiting and working asks count too, so asks sent at once can't
     * each use the same last free ask or credit (f11). Only 'failed' is given back. Null when the count can't be read:
     * callers treat that as nothing left (f09), never as nothing used.
     */
    private static function count(PDO $pdo, string $where, array $args, string $from): ?int
    {
        try { $st = $pdo->prepare("SELECT COUNT(*) FROM " . Database::t('ai_jobs') . " WHERE kind IN ('topic_assist', 'item_write') AND COALESCE(status, '') <> 'failed' AND " . $where . " AND created_at >= ?"); $st->execute(array_merge($args, [$from])); return (int) $st->fetchColumn(); }
        catch (Throwable $e) { error_log('AiCredits::count could not read ai_jobs: ' . $e->getMessage()); return null; }
    }
}
