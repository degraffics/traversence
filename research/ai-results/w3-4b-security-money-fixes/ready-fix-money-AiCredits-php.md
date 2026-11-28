Shipping order is f11 first, then f09, f10 and f25.

- **f11** goes first because it happens in normal use: two asks at once can both take the last free ask or credit. It needs no fault to trigger.
- **f09 and f10** fail open only when a database read fails.
- **f25** is low risk and needs Jason to confirm what was intended.

All four change the same file, `app/lib/AiCredits.php`. So each folder's file builds on the one before it (f11 → f09 → f10 → f25), and each `release.json` names the release it needs first. None of this has been run.

=== FILE: f11/files/app/lib/AiCredits.php ===
<?php
// app/lib/AiCredits.php — who pays for what a member asks our AI to do (Jason, 2026-10-30): no one causes Traversence an
// AI cost that a revenue stream doesn't cover. AI Assist is for members with a paid plan: their own (Personal Plus, Pro)
// or one on a business they own. Each such member gets a few free asks a day, then each ask uses one AI Assist credit
// from a plan they pay for (the plan's credits and any +50 packs, Billing::PLANS / ADDONS), their own first, then their
// businesses'. A trial has none until its first payment; a plan whose payment failed has none until it's paid. A
// backstop holds a member's month at half of what their plans pay a month, whatever the credits say. Each ask is booked
// in ai_jobs.charged_to ('free', 'user:<id>', 'listing:<id>'), so a business's credits are shared by its owners and
// nothing else keeps a count. An ask counts from the moment it's booked (still waiting or working included) until it
// fails, so two asks at once can't both use the last free ask or credit. AI Assist's asks and Write it for me (catalog
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
     * The plans that pay for this member's asks: [key => ['key', 'label', 'credits', 'used', 'left', 'cents' (a month)]],
     * their own first. Only active, paid plans; a business's only when they are its owner.
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
            $out[$key] = ['key' => $key, 'label' => $label . ' (' . Billing::PLANS[$s['plan']][0] . ')', 'credits' => $credits, 'used' => $used, 'left' => max(0, $credits - $used), 'cents' => (int) round($cents)];
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
     * 'used' or 'cover'), 'trial' (a plan of theirs that's still in its trial, by name)].
     */
    public static function state(PDO $pdo, int $uid, string $want = ''): array
    {
        $pots = self::pots($pdo, $uid);
        $cents = array_sum(array_column($pots, 'cents'));
        $spent = 0.0;
        try { $st = $pdo->prepare("SELECT COALESCE(SUM(cost_micros), 0) FROM " . Database::t('ai_jobs') . " WHERE kind IN ('topic_assist', 'item_write') AND created_by = ? AND created_at >= ?"); $st->execute([$uid, date('Y-m-01')]); $spent = (int) $st->fetchColumn() / 1e6; } catch (Throwable $e) {}
        require_once __DIR__ . '/Thresholds.php';   // tuned in System Configuration → Thresholds & gates
        $cover = $cents / 100 * (float) Thresholds::get($pdo, 'ai_cover');
        $free = $pots ? max(0, self::freePerDay($pdo) - self::count($pdo, "created_by = ? AND charged_to = 'free'", [$uid], date('Y-m-d'))) : 0;
        $next = null; $why = null;
        if (!$pots) $why = 'no_plan';
        elseif ($spent >= $cover) $why = 'cover';
        elseif ($free) $next = 'free';
        elseif ($want !== '' && ($pots[$want]['left'] ?? 0) > 0) $next = $want;
        else { foreach ($pots as $k => $p) if ($p['left'] > 0) { $next = $k; break; } if (!$next) $why = 'used'; }
        $trial = '';
        if (!$pots) { try { $s = Billing::current($pdo, 'user', $uid); if ($s && $s['status'] === 'trial') $trial = (string) Billing::PLANS[$s['plan']][0]; } catch (Throwable $e) {} }
        return ['pots' => $pots, 'funded' => (bool) $pots, 'free_left' => $free, 'spent' => $spent, 'cover' => $cover, 'next' => $next, 'why' => $why, 'trial' => $trial];
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
     * each use the same last free ask or credit (f11). Only 'failed' is given back.
     */
    private static function count(PDO $pdo, string $where, array $args, string $from): int
    {
        try { $st = $pdo->prepare("SELECT COUNT(*) FROM " . Database::t('ai_jobs') . " WHERE kind IN ('topic_assist', 'item_write') AND COALESCE(status, '') <> 'failed' AND " . $where . " AND created_at >= ?"); $st->execute(array_merge($args, [$from])); return (int) $st->fetchColumn(); }
        catch (Throwable $e) { return 0; }
    }
}
=== END FILE ===

=== FILE: f11/tests/ai-credits-f11-inflight.php ===
<?php
// tests/ai-credits-f11-inflight.php — plain PHP test, prints PASS/FAIL per check (tools/<area>-tests/ style). DRAFT: not run.
// Usage: php ai-credits-f11-inflight.php [path to AiCredits.php]  (default: this release's files/app/lib/AiCredits.php)
// Needs pdo_sqlite. Loads AiCredits.php beside stub Database/Billing/Team/Thresholds in a temp folder. No money moves.
declare(strict_types=1);

$src = $argv[1] ?? __DIR__ . '/../files/app/lib/AiCredits.php';
if (!is_file($src)) { echo "FAIL AiCredits.php not found at $src\n"; exit(1); }
$dir = sys_get_temp_dir() . '/aicredits-f11-' . getmypid() . '-' . mt_rand();
mkdir($dir);
ini_set('error_log', $dir . '/error.log');
file_put_contents("$dir/Database.php", <<<'PHP'
<?php
final class Database { public static function t(string $name): string { return $name; } }
PHP);
file_put_contents("$dir/Billing.php", <<<'PHP'
<?php
final class Billing {
    public const PLANS = ['plus' => ['Personal Plus', 0, 0, 0, 2], 'biz' => ['Business Pro', 0, 0, 0, 50]];
    public const ADDONS = [];
    public static array $subs = [];
    public static function current($pdo, string $type, int $id): ?array { return self::$subs[$type . ':' . $id] ?? null; }
    public static function planPrice(string $plan, string $cycle): int { return ['plus' => 1000, 'biz' => 4000][$plan] ?? 0; }
    public static function addons($pdo, int $id): array { return []; }
}
PHP);
file_put_contents("$dir/Team.php", <<<'PHP'
<?php
final class Team { public static array $map = []; public static function listingsOf($pdo, int $uid): array { return self::$map[$uid] ?? []; } }
PHP);
file_put_contents("$dir/Thresholds.php", <<<'PHP'
<?php
final class Thresholds { public static array $v = ['ai_cover' => 0.5, 'ai_free_asks' => 3]; public static function get($pdo, string $k) { return self::$v[$k] ?? null; } }
PHP);
copy($src, "$dir/AiCredits.php");

class FailPdo extends PDO
{
    public string $failOn = '';
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if ($this->failOn !== '' && strpos($query, $this->failOn) !== false) throw new PDOException('simulated failure');
        return parent::prepare($query, $options);
    }
}

require "$dir/AiCredits.php";

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . " $name\n"; if (!$ok) $fails++; }
function job(PDO $pdo, int $uid, string $to, string $status, int $micros = 0, string $kind = 'topic_assist'): int
{
    $st = $pdo->prepare("INSERT INTO ai_jobs (kind, status, created_by, charged_to, cost_micros, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $st->execute([$kind, $status, $uid, $to, $micros, date('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

$pdo = new FailPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ai_jobs (id INTEGER PRIMARY KEY, kind TEXT, status TEXT, created_by INTEGER, charged_to TEXT, cost_micros INTEGER NOT NULL DEFAULT 0, created_at TEXT)");
Billing::$subs['user:1'] = ['id' => 1, 'status' => 'active', 'plan' => 'plus', 'cycle' => 'month'];

$s = AiCredits::state($pdo, 1);
check('fresh member: 3 free asks, next is free', $s['free_left'] === 3 && $s['next'] === 'free');

// Three free asks still working (not done yet): they must already count.
$free = [job($pdo, 1, 'free', 'queued'), job($pdo, 1, 'free', 'running'), job($pdo, 1, 'free', 'running', 0, 'item_write')];
$s = AiCredits::state($pdo, 1);
check('free asks still waiting/working count against today', $s['free_left'] === 0);
check('with free asks gone, next is the own plan', $s['next'] === 'user:1');

// Two credit asks still working use both credits.
$a = job($pdo, 1, 'user:1', 'queued');
job($pdo, 1, 'user:1', 'running');
$s = AiCredits::state($pdo, 1);
check('credit asks still working count as used', $s['pots']['user:1']['used'] === 2 && $s['pots']['user:1']['left'] === 0);
check('no credits left: next null, why used', $s['next'] === null && $s['why'] === 'used');

// A failed ask is given back.
$pdo->prepare("UPDATE ai_jobs SET status = 'failed' WHERE id = ?")->execute([$a]);
$s = AiCredits::state($pdo, 1);
check('a failed credit ask is given back', $s['pots']['user:1']['left'] === 1 && $s['next'] === 'user:1');
$pdo->prepare("UPDATE ai_jobs SET status = 'failed' WHERE id = ?")->execute([$free[0]]);
$s = AiCredits::state($pdo, 1);
check('a failed free ask is given back', $s['free_left'] === 1 && $s['next'] === 'free');

// Another member's asks don't count against this member's free asks.
job($pdo, 2, 'free', 'running');
$s = AiCredits::state($pdo, 1);
check("another member's free asks don't count", $s['free_left'] === 1);

array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "FAIL ($fails)\n" : "PASS all\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: f11/faq/faq-entries.md ===
**Does an AI Assist ask use a free ask or credit while it's still working?**
Yes. An ask uses a free ask or a credit as soon as it's sent, so sending several at once can't go past what you have. If an ask fails, you get its free ask or credit back.
=== END FILE ===

=== FILE: f11/release.json ===
{
  "name": "f11-ai-credits-count-inflight-asks",
  "gate": "First of four releases to app/lib/AiCredits.php (f11 → f09 → f10 → f25). Built on the attached live file.",
  "what": "AiCredits::count() counted only 'done' jobs, so asks sent in parallel each saw the same free ask or credit unused and could overrun the daily free asks and the plan's credits. It now counts every job that isn't 'failed', including waiting and working ones. count() feeds both pots() (credits used) and state() (free asks today), so this fixes both.",
  "files": ["files/app/lib/AiCredits.php", "tests/ai-credits-f11-inflight.php", "faq/faq-entries.md"],
  "decisions": [
    "Count every status except 'failed' (a NULL status counts, using COALESCE). This is the safe default: anything not clearly failed holds its credit.",
    "No atomic reservation at insert. The insert happens in /ai/ and ItemWriter, which weren't attached. A small race is left between state() and the INSERT. Needs a person's decision: add a per-member lock (for example MySQL GET_LOCK('ai_ask:<uid>')) around state() plus the INSERT in those callers, in a follow-up."
  ],
  "tests": ["php tests/ai-credits-f11-inflight.php [path/to/AiCredits.php]  (needs pdo_sqlite; stubs Database/Billing/Team/Thresholds)"],
  "risks": [
    "DRAFT: nothing here has been run, tested or zip-checked.",
    "If ai_jobs uses other end states that mean no AI call and no cost (for example 'cancelled', 'refused', 'error'), those asks now hold a credit. Members would see fewer asks left until the list is widened.",
    "A short window remains between the check (state()) and the INSERT in the callers, so two asks in the same instant can still both pass. That's much narrower than before, when the window lasted the whole AI call.",
    "Ships first. f09, f10 and f25 build on this file."
  ],
  "verify": [
    "Confirm the full list of ai_jobs.status values. Schema file not shown. Should any besides 'failed' give the ask back?",
    "Confirm the callers insert the ai_jobs row (charged_to set) before calling the AI, not after. /ai/ and ItemWriter not shown.",
    "Decide whether to add the atomic reservation (lock around state() plus INSERT) in the callers. Files not shown.",
    "Database.php, Billing.php, Team.php and Thresholds.php are not shown. The test stubs assume Billing::PLANS[plan][0]=label and [4]=credits, and ADDONS[addon][1]=cents and [3]=credits, as the attached code reads them.",
    "Check the FAQ entry against the existing AI Assist FAQ (file not shown) for wording and duplicates."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f09/files/app/lib/AiCredits.php ===
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
=== END FILE ===

=== FILE: f09/tests/ai-credits-f09-count-fails-closed.php ===
<?php
// tests/ai-credits-f09-count-fails-closed.php — plain PHP test, prints PASS/FAIL per check (tools/<area>-tests/ style). DRAFT: not run.
// Usage: php ai-credits-f09-count-fails-closed.php [path to AiCredits.php]  (default: this release's files/app/lib/AiCredits.php)
// Needs pdo_sqlite. Loads AiCredits.php beside stub Database/Billing/Team/Thresholds in a temp folder. No money moves.
declare(strict_types=1);

$src = $argv[1] ?? __DIR__ . '/../files/app/lib/AiCredits.php';
if (!is_file($src)) { echo "FAIL AiCredits.php not found at $src\n"; exit(1); }
$dir = sys_get_temp_dir() . '/aicredits-f09-' . getmypid() . '-' . mt_rand();
mkdir($dir);
ini_set('error_log', $dir . '/error.log');
file_put_contents("$dir/Database.php", <<<'PHP'
<?php
final class Database { public static function t(string $name): string { return $name; } }
PHP);
file_put_contents("$dir/Billing.php", <<<'PHP'
<?php
final class Billing {
    public const PLANS = ['plus' => ['Personal Plus', 0, 0, 0, 2], 'biz' => ['Business Pro', 0, 0, 0, 50]];
    public const ADDONS = [];
    public static array $subs = [];
    public static function current($pdo, string $type, int $id): ?array { return self::$subs[$type . ':' . $id] ?? null; }
    public static function planPrice(string $plan, string $cycle): int { return ['plus' => 1000, 'biz' => 4000][$plan] ?? 0; }
    public static function addons($pdo, int $id): array { return []; }
}
PHP);
file_put_contents("$dir/Team.php", <<<'PHP'
<?php
final class Team { public static array $map = []; public static function listingsOf($pdo, int $uid): array { return self::$map[$uid] ?? []; } }
PHP);
file_put_contents("$dir/Thresholds.php", <<<'PHP'
<?php
final class Thresholds { public static array $v = ['ai_cover' => 0.5, 'ai_free_asks' => 3]; public static function get($pdo, string $k) { return self::$v[$k] ?? null; } }
PHP);
copy($src, "$dir/AiCredits.php");

class FailPdo extends PDO
{
    public string $failOn = '';
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if ($this->failOn !== '' && strpos($query, $this->failOn) !== false) throw new PDOException('simulated failure');
        return parent::prepare($query, $options);
    }
}

require "$dir/AiCredits.php";

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . " $name\n"; if (!$ok) $fails++; }

$pdo = new FailPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ai_jobs (id INTEGER PRIMARY KEY, kind TEXT, status TEXT, created_by INTEGER, charged_to TEXT, cost_micros INTEGER NOT NULL DEFAULT 0, created_at TEXT)");
Billing::$subs['user:1'] = ['id' => 1, 'status' => 'active', 'plan' => 'plus', 'cycle' => 'month'];

$s = AiCredits::state($pdo, 1);
check('normal read: free asks available, unread false', $s['free_left'] === 3 && $s['next'] === 'free' && $s['unread'] === false);
check('normal read: pot not unread, 2 left', $s['pots']['user:1']['unread'] === false && $s['pots']['user:1']['left'] === 2);

// The count query fails (database error): nothing may look unused.
$pdo->failOn = 'COUNT(*)';
$s = AiCredits::state($pdo, 1);
check('count fails: no free asks', $s['free_left'] === 0);
check('count fails: pot shows no credits left', $s['pots']['user:1']['left'] === 0 && $s['pots']['user:1']['used'] === 2);
check('count fails: next is null, why used', $s['next'] === null && $s['why'] === 'used');
check('count fails: unread is true', $s['unread'] === true && $s['pots']['user:1']['unread'] === true);
check('count fails: still funded (the plan is real)', $s['funded'] === true && AiCredits::funded($pdo, 1) === true);
check('count fails: a wanted pot is not handed out', AiCredits::state($pdo, 1, 'user:1')['next'] === null);
check('count fails: the error is logged', is_file("$dir/error.log") && strpos((string) file_get_contents("$dir/error.log"), 'AiCredits::count') !== false);

$pdo->failOn = '';
$s = AiCredits::state($pdo, 1);
check('after recovery: back to normal', $s['free_left'] === 3 && $s['unread'] === false);

array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "FAIL ($fails)\n" : "PASS all\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: f09/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f09/release.json ===
{
  "name": "f09-ai-credits-count-fails-closed",
  "gate": "Needs f11 first (this file contains f11's change). Second of four releases to app/lib/AiCredits.php.",
  "what": "AiCredits::count() returned 0 when its query failed, so on a database error every free ask and credit looked unused (fails open). It now returns null and logs the error. pots() then treats the plan's credits as all used (left 0), state() gives no free asks, and both say 'unread' => true so /ai/ can explain why.",
  "files": ["files/app/lib/AiCredits.php", "tests/ai-credits-f09-count-fails-closed.php", "faq/faq-entries.md"],
  "decisions": [
    "Return null (as a private ?int) rather than rethrow. Rethrowing would make pots() silently drop the plan (its try/catch), so the member would look unfunded. It would also throw out of state(), whose free-ask count isn't wrapped, and could break /ai/.",
    "Not PHP_INT_MAX: 'used' is displayed, so it would show 9223372036854775807. On failure, used is set to the plan's credits instead.",
    "funded stays true on a count error. The plan really is paid; only the count is unknown. So the Chameleon Filter's funded() is unchanged.",
    "'why' keeps its existing values ('used' here). The new 'unread' key is added, and /ai/ can show a 'couldn't check, try again' message. Needs a person: whether /ai/ should show that message (page not shown)."
  ],
  "tests": ["php tests/ai-credits-f09-count-fails-closed.php [path/to/AiCredits.php]  (needs pdo_sqlite; stubs Database/Billing/Team/Thresholds)"],
  "risks": [
    "DRAFT: nothing here has been run, tested or zip-checked.",
    "During a database fault, members with a paid plan see 'used' and can't ask until it recovers. That's intended (fails closed), but the message may confuse them until /ai/ reads 'unread'.",
    "state() and pots() return a new key, 'unread'. Any code that compares the whole array or expects exact keys would notice it.",
    "error_log is written on every failing count. A long outage could make the log noisy."
  ],
  "verify": [
    "/ai/ and ItemWriter (not shown): confirm they gate on 'next' (null blocks), not on 'free_left' or 'left' alone.",
    "Decide whether /ai/ shows a specific message when state()['unread'] is true (page not shown).",
    "Run this release's test plus f11's test against the shipped file.",
    "Database.php, Billing.php, Team.php and Thresholds.php are not shown; the tests stub them."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f10/files/app/lib/AiCredits.php ===
<?php
// app/lib/AiCredits.php — who pays for what a member asks our AI to do (Jason, 2026-10-30): no one causes Traversence an
// AI cost that a revenue stream doesn't cover. AI Assist is for members with a paid plan: their own (Personal Plus, Pro)
// or one on a business they own. Each such member gets a few free asks a day, then each ask uses one AI Assist credit
// from a plan they pay for (the plan's credits and any +50 packs, Billing::PLANS / ADDONS), their own first, then their
// businesses'. A trial has none until its first payment; a plan whose payment failed has none until it's paid. A
// backstop holds a member's month at half of what their plans pay a month, whatever the credits say. Each ask is booked
// in ai_jobs.charged_to ('free', 'user:<id>', 'listing:<id>'), so a business's credits are shared by its owners and
// nothing else keeps a count. An ask counts from the moment it's booked (still waiting or working included) until it
// fails, so two asks at once can't both use the last free ask or credit. When a count or the month's spend can't be
// read, nothing is left (it fails closed) and the state says 'unread'. AI Assist's asks and Write it for me (catalog
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
     * 'used' or 'cover'), 'trial' (a plan of theirs that's still in its trial, by name), 'unread' (a count or the
     * month's spend couldn't be read, so asks are held: the page can say "we couldn't check your credits, try again
     * shortly")].
     */
    public static function state(PDO $pdo, int $uid, string $want = ''): array
    {
        $pots = self::pots($pdo, $uid);
        $cents = array_sum(array_column($pots, 'cents'));
        $spent = 0.0; $spentRead = false;
        try { $st = $pdo->prepare("SELECT COALESCE(SUM(cost_micros), 0) FROM " . Database::t('ai_jobs') . " WHERE kind IN ('topic_assist', 'item_write') AND created_by = ? AND created_at >= ?"); $st->execute([$uid, date('Y-m-01')]); $spent = (int) $st->fetchColumn() / 1e6; $spentRead = true; }
        catch (Throwable $e) { error_log('AiCredits::state could not read the month\'s spend: ' . $e->getMessage()); }
        require_once __DIR__ . '/Thresholds.php';   // tuned in System Configuration → Thresholds & gates
        $cover = $cents / 100 * (float) Thresholds::get($pdo, 'ai_cover');
        $freeUsed = $pots ? self::count($pdo, "created_by = ? AND charged_to = 'free'", [$uid], date('Y-m-d')) : 0;
        $free = ($pots && $freeUsed !== null) ? max(0, self::freePerDay($pdo) - $freeUsed) : 0;   // couldn't count: no free asks (f09)
        $unread = !$spentRead || $freeUsed === null || in_array(true, array_column($pots, 'unread'), true);
        $next = null; $why = null;
        if (!$pots) $why = 'no_plan';
        elseif (!$spentRead || $spent >= $cover) $why = 'cover';   // spend unknown: the backstop holds (fails closed, f10)
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
=== END FILE ===

=== FILE: f10/tests/ai-credits-f10-spend-fails-closed.php ===
<?php
// tests/ai-credits-f10-spend-fails-closed.php — plain PHP test, prints PASS/FAIL per check (tools/<area>-tests/ style). DRAFT: not run.
// Usage: php ai-credits-f10-spend-fails-closed.php [path to AiCredits.php]  (default: this release's files/app/lib/AiCredits.php)
// Needs pdo_sqlite. Loads AiCredits.php beside stub Database/Billing/Team/Thresholds in a temp folder. No money moves.
declare(strict_types=1);

$src = $argv[1] ?? __DIR__ . '/../files/app/lib/AiCredits.php';
if (!is_file($src)) { echo "FAIL AiCredits.php not found at $src\n"; exit(1); }
$dir = sys_get_temp_dir() . '/aicredits-f10-' . getmypid() . '-' . mt_rand();
mkdir($dir);
ini_set('error_log', $dir . '/error.log');
file_put_contents("$dir/Database.php", <<<'PHP'
<?php
final class Database { public static function t(string $name): string { return $name; } }
PHP);
file_put_contents("$dir/Billing.php", <<<'PHP'
<?php
final class Billing {
    public const PLANS = ['plus' => ['Personal Plus', 0, 0, 0, 2], 'biz' => ['Business Pro', 0, 0, 0, 50]];
    public const ADDONS = [];
    public static array $subs = [];
    public static function current($pdo, string $type, int $id): ?array { return self::$subs[$type . ':' . $id] ?? null; }
    public static function planPrice(string $plan, string $cycle): int { return ['plus' => 1000, 'biz' => 4000][$plan] ?? 0; }
    public static function addons($pdo, int $id): array { return []; }
}
PHP);
file_put_contents("$dir/Team.php", <<<'PHP'
<?php
final class Team { public static array $map = []; public static function listingsOf($pdo, int $uid): array { return self::$map[$uid] ?? []; } }
PHP);
file_put_contents("$dir/Thresholds.php", <<<'PHP'
<?php
final class Thresholds { public static array $v = ['ai_cover' => 0.5, 'ai_free_asks' => 3]; public static function get($pdo, string $k) { return self::$v[$k] ?? null; } }
PHP);
copy($src, "$dir/AiCredits.php");

class FailPdo extends PDO
{
    public string $failOn = '';
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if ($this->failOn !== '' && strpos($query, $this->failOn) !== false) throw new PDOException('simulated failure');
        return parent::prepare($query, $options);
    }
}

require "$dir/AiCredits.php";

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . " $name\n"; if (!$ok) $fails++; }
function job(PDO $pdo, int $uid, string $to, string $status, int $micros = 0, string $kind = 'topic_assist'): int
{
    $st = $pdo->prepare("INSERT INTO ai_jobs (kind, status, created_by, charged_to, cost_micros, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $st->execute([$kind, $status, $uid, $to, $micros, date('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

$pdo = new FailPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ai_jobs (id INTEGER PRIMARY KEY, kind TEXT, status TEXT, created_by INTEGER, charged_to TEXT, cost_micros INTEGER NOT NULL DEFAULT 0, created_at TEXT)");
Billing::$subs['user:1'] = ['id' => 1, 'status' => 'active', 'plan' => 'plus', 'cycle' => 'month'];

// Plus pays $10 a month: cover is $5.
$s = AiCredits::state($pdo, 1);
check('normal read: cover is $5, next free, unread false', abs($s['cover'] - 5.0) < 1e-9 && $s['next'] === 'free' && $s['why'] === null && $s['unread'] === false);

// The spend query fails: the backstop must hold.
$pdo->failOn = 'SUM(cost_micros)';
$s = AiCredits::state($pdo, 1);
check('spend fails: why cover', $s['why'] === 'cover');
check('spend fails: next null (no free ask, no credit)', $s['next'] === null);
check('spend fails: unread true', $s['unread'] === true);
check('spend fails: wanted pot not handed out', AiCredits::state($pdo, 1, 'user:1')['next'] === null);
check('spend fails: the result still encodes as JSON (no INF)', json_encode(AiCredits::state($pdo, 1)) !== false);
check('spend fails: the error is logged', is_file("$dir/error.log") && strpos((string) file_get_contents("$dir/error.log"), 'AiCredits::state') !== false);

// Recovery, and the normal backstop still trips on real spend.
$pdo->failOn = '';
check('after recovery: next free again', AiCredits::state($pdo, 1)['next'] === 'free');
job($pdo, 1, 'free', 'done', 5000000);
$s = AiCredits::state($pdo, 1);
check('real spend at cover: why cover', $s['why'] === 'cover' && $s['next'] === null && $s['unread'] === false);

// A member with no plan whose spend can't be read is still 'no_plan', not 'cover'.
$pdo->failOn = 'SUM(cost_micros)';
check('no plan + spend fails: why no_plan', AiCredits::state($pdo, 9)['why'] === 'no_plan');

array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "FAIL ($fails)\n" : "PASS all\n";
exit($fails ? 1 : 0);
=== END FILE ===

=== FILE: f10/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f10/release.json ===
{
  "name": "f10-ai-credits-spend-fails-closed",
  "gate": "Needs f11 and f09 first (this file contains both). Third of four releases to app/lib/AiCredits.php.",
  "what": "In AiCredits::state(), a failing spend query was swallowed and left $spent = 0, so the monthly backstop could never trip during a database fault. A failed read is now tracked ($spentRead) and logged. The member's state becomes why='cover' with next=null, and 'unread' => true.",
  "files": ["files/app/lib/AiCredits.php", "tests/ai-credits-f10-spend-fails-closed.php", "faq/faq-entries.md"],
  "decisions": [
    "Use a $spentRead flag rather than the suggested $spent = INF. json_encode() fails on INF ('Inf and NaN cannot be JSON encoded'), which could break /ai/ if it encodes the state. 'spent' stays a finite 0.0, and the block comes from why='cover'.",
    "Keep why='cover' as the reason, as suggested, so /ai/ shows its existing backstop message. 'unread' lets it say more if wanted.",
    "A member with no plan still gets why='no_plan' (that check comes first)."
  ],
  "tests": ["php tests/ai-credits-f10-spend-fails-closed