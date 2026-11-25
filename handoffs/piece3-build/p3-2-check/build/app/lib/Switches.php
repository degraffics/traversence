<?php
// app/lib/Switches.php — feature switches (decisions/0145 §6, Phase 2): a feature can be turned on or off without an
// upload. Each switch is off, on for staff and test accounts only ("test"), on for a share of members as well
// ("share"), or on for everyone ("on"); the kill switch turns it off for all at once and stays until lifted.
// New features from here on check theirs: if (Switches::on($pdo, 'my_feature')) { … }. System Configuration → Switches.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Switches
{
    /** key => [name, what it turns on, its state until someone sets it] */
    public const DEFS = [
        'switch_test' => ['Switch test', 'A line on the FAQ page saying the switch is on for you. Used to check that switches work (Phase 2).', 'off'],
        'releases' => ['Releases page', 'System Configuration → Releases (decisions/0145 Phase 5): releases uploaded, tried on staging, published in steps, turned off or rolled back. Released itself this way: staff first.', 'test'],
        'site_health' => ['Site health in Pulse', 'The Pulse case "Site health": a step for each open finding of the site audit (the browser checks, the paths, the code checks), with the page, its screenshot and its proposal (decisions/0145 Phase 4). Staff see it first.', 'test'],   // on for staff and test accounts from the start (Jason, 2026-11-12: "go with your defaults")
        'audit_clock' => ['Site audit clock', 'The site asks GitHub to run the site audit\'s checks when their hour comes, since GitHub\'s own schedule skips them when it\'s busy (AuditClock, the job Site audit clock). Off: only GitHub\'s schedule runs them. A background job, so it\'s on or off for everyone.', 'on'],
        'before_after' => ['The before-and-after check', 'Each release from git waits on staging for the advocates (decisions/0179 rule 2, Piece 3): before the change and after it, they look at the pages it touches as each test account and the staff test logins, at 1280 and 390, and a page made worse stops it (it withdraws itself, and the failure goes in the Work queue). Off: releases go on staging and publish as before. A background job, so it\'s on or off for everyone.', 'off'],
        // the staging copy only (decisions/0145 Phase 3): they do nothing on the live site, and each refresh turns them off again
        'team_runner' => ['Staging: the Team\'s runner', 'On the staging copy only: the Team\'s agents take one ticket at a time from the Work queue, do one step (move it through its gate, write a build brief, propose a brief\'s words), log it with Undo, and stop (decisions/0179\'s Piece 2). Nothing it does reaches live until staging is published in an approved batch. Off: nothing runs.', 'off'],
        'staging_ai' => ['Staging: paid AI', 'On the staging copy only: lets Claude, Document AI and Textract run there for a test (they cost money). Off: AI tools there say it isn\'t connected.', 'off'],
        'staging_crawler' => ['Staging: the crawler', 'On the staging copy only: lets its crawl queue hand out jobs for a test. Off: the queue there stays paused.', 'off'],
    ];
    public const STATES = ['off' => 'Off', 'test' => 'Staff and test accounts', 'share' => 'A share of members', 'on' => 'Everyone'];

    private static ?array $rows = null;

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM feature_switches LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /** Every switch the code knows, with its saved setting: key => [name, about, state, share_pct, killed, note, updated_at]. */
    public static function all(PDO $pdo): array
    {
        $saved = self::saved($pdo);
        $out = [];
        foreach (self::DEFS as $k => [$name, $about, $def]) {
            $r = $saved[$k] ?? null;
            $out[$k] = ['name' => $name, 'about' => $about, 'state' => $r['state'] ?? $def, 'share_pct' => (int) ($r['share_pct'] ?? 0), 'killed' => (bool) ($r['killed'] ?? false),
                        'note' => (string) ($r['note'] ?? ''), 'updated_at' => $r['updated_at'] ?? null];
        }
        return $out;
    }

    private static function saved(PDO $pdo): array
    {
        if (self::$rows !== null) return self::$rows;
        self::$rows = [];
        try { foreach ($pdo->query('SELECT * FROM feature_switches')->fetchAll(PDO::FETCH_ASSOC) as $r) self::$rows[$r['switch_key']] = $r; } catch (Throwable) {}
        return self::$rows;
    }

    /** Is the feature on for whoever is here (the signed-in member, or a visitor)? */
    public static function on(PDO $pdo, string $key, ?int $uid = null): bool
    {
        $s = self::all($pdo)[$key] ?? null;
        if (!$s || $s['killed'] || $s['state'] === 'off') return false;
        if ($s['state'] === 'on') return true;
        $uid = $uid ?? (int) ($_SESSION['user_id'] ?? 0);
        if ($uid && (self::isStaff() || self::isTest($pdo, $uid))) return true;   // staff and test accounts see it first
        if ($s['state'] === 'share' && $uid) return (crc32($key . '|' . $uid) % 100) < $s['share_pct'];   // the same members each time
        return false;
    }

    private static function isStaff(): bool
    {
        return !empty($_SESSION['tv_staff']) || !empty($_SESSION['tv_team']);
    }

    private static function isTest(PDO $pdo, int $uid): bool
    {
        require_once __DIR__ . '/TestAccounts.php';
        return TestAccounts::isTestUser($pdo, $uid);
    }

    public static function set(PDO $pdo, string $key, string $state, int $pct, bool $killed, string $note, int $by): void
    {
        if (!isset(self::DEFS[$key])) throw new RuntimeException('That switch isn\'t one the site knows.');
        if (!isset(self::STATES[$state])) throw new RuntimeException('Choose who it\'s on for.');
        $pct = max(0, min(100, $pct));
        $pdo->prepare('INSERT INTO feature_switches (switch_key, state, share_pct, killed, note, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())
                       ON DUPLICATE KEY UPDATE state = VALUES(state), share_pct = VALUES(share_pct), killed = VALUES(killed), note = VALUES(note), updated_by = VALUES(updated_by), updated_at = NOW()')
            ->execute([$key, $state, $pct, $killed ? 1 : 0, mb_substr(trim($note), 0, 255), $by ?: null]);
        self::$rows = null;
    }

    /** Off for everyone at once (or back to its setting). */
    public static function killAll(PDO $pdo, bool $kill, int $by): int
    {
        $n = 0;
        foreach (self::all($pdo) as $k => $s) { self::set($pdo, $k, $s['state'], $s['share_pct'], $kill, $s['note'], $by); $n++; }
        return $n;
    }
}
