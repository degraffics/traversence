<?php
// app/lib/AdvocatesAudit.php — the advocates' own audit, on a schedule and independent of the builders (decisions/0180 §5:
// the test-account advocates do QA verification and experience tickets; its note "roles check one another": releases are
// checked by the advocates A1–A12). Between releases, once a day, the advocates look at staging as their test accounts
// (GitHub's run, as the before-and-after check's looks are), and a finding seen on two runs in a row opens one experience
// ticket in the Work queue, for the lane that owns the screen. Run by advocates_tick (hourly; it asks once a day).
//
// The rules it keeps:
//   - Independent of the builders: started only by its schedule, never by a release, a build session or a ticket; it reads
//     no build brief, and nothing a builder does can start, skip or quiet it.
//   - It never blocks, withdraws, holds or publishes a release, and never rolls one back (0180 §8: rollback is the release
//     checks' rules only). It waits while a release is being checked (that look comes first, and staging is changing).
//   - An advocate never builds or moves a ticket (0180 §5): each ticket opens unowned at Planning; its lane's manager or a
//     person takes it. Withdraw on the Work queue is its Undo.
//   - Read only, as the test accounts and staff test logins: no form sent, never the Founder, never live's people (0165 §3).
//     A test account's marker (its address) is never copied into a ticket.
//   - $0: GitHub's minutes only, counted in the before-and-after check's daily budget (decisions/0179 DB4); none asked once
//     the day's budget is spent. No AI.
//   - Off until its switch advocates_audit is on. On staging it does nothing (asked from live, as AuditClock asks).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class AdvocatesAudit
{
    public const SWITCH = 'advocates_audit';
    public const EVENT = 'advocates-audit';
    /** Asked once a day from this hour (UTC), clear of the site audit's 7:00, 9:00, 10:00 and 19:00. */
    public const HOUR_UTC = 4;
    public const RESULT = 'site-audit/results/advocates/latest.json';
    /** A finding opens its ticket on its second run in a row (the audit's two looks, piece2-team.md §6). */
    public const RUNS_TO_OPEN = 2;
    /** At most this many tickets opened a run. */
    public const MAX_TICKETS = 5;
    /** What a finding may be: 0179's four rules that a single look can show, and an experience finding (0180 §5). */
    public const RULES = [
        'access' => 'lost access or an error on a valid view',
        'layout' => 'a layout or phone problem',
        'data' => 'missing data or a broken core flow',
        'friction' => 'friction in the experience',
    ];

    /** Tests replace these: fn(string $event, array $payload): string ('' = asked), and fn(string $path): ?string. */
    public static $askFn = null;
    public static $readFn = null;

    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS advocate_audit_seen (akey CHAR(44) NOT NULL PRIMARY KEY, who VARCHAR(40) NOT NULL DEFAULT '', page VARCHAR(200) NOT NULL DEFAULT '',
            size VARCHAR(8) NOT NULL DEFAULT '', rule VARCHAR(20) NOT NULL DEFAULT '', what VARCHAR(300) NOT NULL DEFAULT '', runs INT NOT NULL DEFAULT 0,
            last_run VARCHAR(160) NOT NULL DEFAULT '', task_id INT UNSIGNED NULL, at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS advocate_audit_state (k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function state(PDO $pdo, string $k): string
    {
        try { $st = $pdo->prepare('SELECT v FROM advocate_audit_state WHERE k = ?'); $st->execute([$k]); $v = $st->fetchColumn(); return $v === false ? '' : (string) $v; } catch (Throwable $x) { return ''; }
    }

    private static function setState(PDO $pdo, string $k, string $v): void
    {
        $pdo->prepare('REPLACE INTO advocate_audit_state (k, v, at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$k, mb_substr($v, 0, 255)]);
    }

    /** Is today's look due? Once a day, from HOUR_UTC. */
    public static function due(int $hourUtc, string $lastDay, string $today): bool { return $lastDay !== $today && $hourUtc >= self::HOUR_UTC; }

    /** One finding's key: the same advocate, page, size and rule is the same finding. */
    public static function key(array $f): string { return 'adv:' . sha1($f['who'] . '|' . $f['page'] . '|' . $f['size'] . '|' . $f['rule']); }

    /**
     * The run's result file, checked field by field; null when there's none or it isn't one. {run, at, seconds, views,
     * findings: [{who, page, size, rule, what, lane}]}, at most 200 findings.
     */
    public static function parse(?string $raw): ?array
    {
        $v = $raw === null ? null : json_decode($raw, true);
        if (!is_array($v)) return null;
        $run = (string) ($v['run'] ?? '');
        if (!preg_match('#^https://github\.com/degraffics/traversence/actions/runs/\d+$#', $run)) return null;
        $out = ['run' => $run, 'at' => preg_match('/^\d{4}-\d{2}-\d{2}T[\d:.]+Z?$/', (string) ($v['at'] ?? '')) ? (string) $v['at'] : '',
            'seconds' => max(0.0, (float) ($v['seconds'] ?? 0)), 'views' => (int) ($v['views'] ?? 0), 'findings' => []];
        foreach ((array) ($v['findings'] ?? []) as $f) {
            if (!is_array($f)) continue;
            $who = is_scalar($f['who'] ?? null) ? (string) $f['who'] : ''; $page = is_scalar($f['page'] ?? null) ? (string) $f['page'] : '';
            $rule = is_scalar($f['rule'] ?? null) ? (string) $f['rule'] : '';
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $who) || !preg_match('#^/[A-Za-z0-9/._?=&%-]{0,200}$#', $page) || !isset(self::RULES[$rule])) continue;
            $what = is_scalar($f['what'] ?? null) ? (string) $f['what'] : '';
            $what = preg_replace('/[^\s@]+@[^\s@]+/u', '(an address)', $what);
            $what = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $what)), 0, 300);
            $lane = is_scalar($f['lane'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', (string) $f['lane']) ? (string) $f['lane'] : '';
            $out['findings'][] = ['who' => $who, 'page' => $page, 'size' => ($f['size'] ?? '') === 'phone' ? 'phone' : 'desk', 'rule' => $rule, 'what' => $what, 'lane' => $lane];
            if (count($out['findings']) === 200) break;
        }
        return $out;
    }

    /** One run: read the last look, then ask for today's when it's due. A line for the job's log ('' on staging). */
    public static function tick(PDO $pdo): string
    {
        require_once __DIR__ . '/Staging.php'; if (Staging::here()) return '';
        require_once __DIR__ . '/Switches.php';
        try { if (!Switches::on($pdo, self::SWITCH)) return 'The advocates\' audit is switched off (System Configuration → Switches, ' . self::SWITCH . ').'; } catch (Throwable $x) { return 'The switches couldn\'t be read: ' . $x->getMessage(); }
        self::ensure($pdo);
        if ((int) $pdo->query("SELECT GET_LOCK('tv_adv_audit', 0)")->fetchColumn() !== 1) return 'Another run is going: this one did nothing.';
        try {
            $out = array_values(array_filter([self::apply($pdo), self::ask($pdo)]));
            return $out ? implode(' ', $out) : 'Nothing to do: today\'s look is asked for or not due, and no new result.';
        } finally { try { $pdo->query("SELECT RELEASE_LOCK('tv_adv_audit')"); } catch (Throwable $x) {} }
    }

    /** Ask GitHub for today's look, once, when it's due and nothing comes first. */
    public static function ask(PDO $pdo): string
    {
        $today = gmdate('Y-m-d');
        if (!self::due((int) gmdate('G'), self::state($pdo, 'asked_day'), $today)) return '';
        require_once __DIR__ . '/BeforeAfter.php';
        if (BeforeAfter::waiting($pdo)) return 'Today\'s look waits: a release is being checked by the advocates (its look comes first).';
        if (BeforeAfter::spent($pdo)) return 'Today\'s look waits: the day\'s GitHub minutes are spent (decisions/0179, DB4).';
        require_once __DIR__ . '/StagingSite.php';
        if (StagingSite::copyHold($pdo) !== '') return 'Today\'s look waits: staging is closed until a fresh copy finishes.';
        $payload = ['day' => $today, 'advocates' => 'all'];
        if (self::$askFn) $why = (string) (self::$askFn)(self::EVENT, $payload);
        else { require_once __DIR__ . '/AuditClock.php'; $why = AuditClock::askGitHub(self::EVENT, $payload); }
        if ($why !== '') return 'GitHub wasn\'t asked for today\'s look (' . $why . '); asked again next hour.';
        self::setState($pdo, 'asked_day', $today);
        return 'Asked GitHub for today\'s look (the advocates, on staging).';
    }

    /** Read the last look once: count its minutes, keep each finding's runs, open a ticket on its second run in a row. */
    public static function apply(PDO $pdo): string
    {
        if (self::$readFn) $raw = (self::$readFn)(self::RESULT);
        else { require_once __DIR__ . '/DocsRepo.php'; $raw = DocsRepo::now(self::RESULT); }
        $r = self::parse($raw === null ? null : (string) $raw);
        if (!$r || $r['run'] === self::state($pdo, 'applied_run')) return '';
        require_once __DIR__ . '/BeforeAfter.php';
        BeforeAfter::countMinutes($pdo, $r['seconds']);
        $now = []; foreach ($r['findings'] as $f) $now[self::key($f)] = $f;
        // a finding not seen this run starts again; one whose ticket is open stays with its ticket
        foreach ($pdo->query('SELECT akey, task_id FROM advocate_audit_seen')->fetchAll(PDO::FETCH_ASSOC) as $row)
            if (!isset($now[$row['akey']]) && $row['task_id'] === null) $pdo->prepare('DELETE FROM advocate_audit_seen WHERE akey = ?')->execute([$row['akey']]);
        require_once __DIR__ . '/GaugeTasks.php';
        $new = 0; $opened = 0;
        foreach ($now as $k => $f) {
            $st = $pdo->prepare('SELECT * FROM advocate_audit_seen WHERE akey = ?'); $st->execute([$k]); $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $pdo->prepare('INSERT INTO advocate_audit_seen (akey, who, page, size, rule, what, runs, last_run, at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, UTC_TIMESTAMP())')
                    ->execute([$k, $f['who'], $f['page'], $f['size'], $f['rule'], $f['what'], $r['run']]);
                $new++; continue;
            }
            if ($row['last_run'] === $r['run']) continue;
            $runs = (int) $row['runs'] + 1; $task = $row['task_id'] === null ? null : (int) $row['task_id'];
            if ($task !== null) {
                $t = GaugeTasks::task($pdo, $task);
                if ($t && $t['undone_at'] !== null) { $pdo->prepare('UPDATE advocate_audit_seen SET last_run = ? WHERE akey = ?')->execute([$r['run'], $k]); continue; }   // withdrawn by a person: never opened again
                if (!$t || $t['cleared_at'] !== null) { $task = null; $runs = 1; }   // done, and seen again: it starts over
            }
            if ($task === null && $runs >= self::RUNS_TO_OPEN && $opened < self::MAX_TICKETS) { $task = self::open($pdo, $f, $r['run']); $opened++; }
            $pdo->prepare('UPDATE advocate_audit_seen SET runs = ?, last_run = ?, what = ?, task_id = ? WHERE akey = ?')->execute([$runs, $r['run'], $f['what'], $task, $k]);
        }
        self::setState($pdo, 'applied_run', $r['run']);
        return 'Read the advocates\' look' . ($r['at'] !== '' ? ' of ' . $r['at'] : '') . ': ' . count($now) . ' finding' . (count($now) === 1 ? '' : 's') . ' · ' . $new . ' new · ' . $opened . ' experience ticket' . ($opened === 1 ? '' : 's') . ' opened.';
    }

    /** One experience ticket (0180 §5): Work, at Planning, unowned, its lane when the finding names one of the seven. */
    private static function open(PDO $pdo, array $f, string $run): int
    {
        $what = 'Advocate ' . ucfirst(str_replace('-', ' ', $f['who'])) . ': ' . $f['page'] . ' at ' . ($f['size'] === 'phone' ? '390' : '1280') . ', ' . self::RULES[$f['rule']];
        $why = ($f['what'] !== '' ? rtrim($f['what'], '.') . '. ' : '') . 'Seen on two of the advocates\' daily looks in a row (' . $run . '). An experience ticket (decisions/0180 §5): its lane\'s manager takes it or proposes a Planning initiative from it; the advocate never builds or moves it.';
        $id = GaugeTasks::add($pdo, 'area:advocates', $what, mb_substr($why, 0, 400), 'the advocates\' daily audit', 'the advocates\' audit', 0, '', 'work');
        try {
            require_once __DIR__ . '/TeamRunner.php';
            [$tier, , $flag] = TeamRunner::tier($what . ' ' . $f['what'], ['path:' . $f['page']]);   // by what it touches; words only flag it
            $pdo->prepare('UPDATE control_gauge_tasks SET tier = ? WHERE id = ? AND tier IS NULL')->execute([$tier, $id]);
            if ($flag !== '') GaugeTasks::flag($pdo, $id, $flag);
        } catch (Throwable $x) { error_log('advocates audit tier: ' . $x->getMessage()); }
        if ($f['lane'] !== '') {
            try { require_once __DIR__ . '/TeamRoster.php'; if (in_array($f['lane'], TeamRoster::LANES, true)) $pdo->prepare("UPDATE control_gauge_tasks SET lane = ? WHERE id = ? AND (lane IS NULL OR lane = '')")->execute([$f['lane'], $id]); }
            catch (Throwable $x) { error_log('advocates audit lane: ' . $x->getMessage()); }
        }
        return $id;
    }
}
