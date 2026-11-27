<?php
// app/lib/GoalCheck.php — the live side's daily goal check (CLAUDE.md "Goals and records live with the roles", Jason,
// 2026-11-27: "up and running by Monday morning 6 am"; built for the Project Manager (8) as Build & QA (21), IT lane).
// Every goal is a parent task in the Work queue (GaugeTasks, parent_id). Escalation follows the tiers (decisions/0180):
//   a stalled ticket   one of a goal's open tickets not moved in 3 days → one Work ticket for its lane's manager (Tier 3);
//   a date at risk     the goal's due date can't be met by its open tickets (AT_RISK) → one for that manager's executive
//                      (Tier 4); a goal whose tickets sit in more than one lane goes to the CEO.
// One open ticket per goal and kind (keyed in audit_key), so a later day adds nothing; it closes itself, "cleared by its
// own check", once the goal passes. Work tickets, not Audit ones: the Team's audit clears any Audit ticket it didn't find
// itself (TeamRunner::audit). $0: no outside call, no AI. The live site only (Scheduler::NOT_ON_STAGING).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';
require_once __DIR__ . '/ControlLog.php';

final class GoalCheck
{
    /** A ticket that hasn't moved in this many days is stalled (CLAUDE.md "Goals and records": "sit 3 days"). */
    public const STALL_DAYS = 3;

    /**
     * When a goal's date is at risk. PM (8), 2026-11-27, adjustable by the PM (a Tier 3 operational rule within 0180 §2):
     * its due date has passed with any of its tickets open; or it's within 'soon_days' days with any ticket still at one of
     * 'early' (not yet built).
     */
    public const AT_RISK = ['soon_days' => 7, 'early' => ['planning', 'building']];

    /** A goal that crosses lanes is the CEO's (CLAUDE.md "Goals and records"); the role R1 seeded (2026-11-26_0180_tiers_roster.sql). */
    public const CROSS_LANE = 'ceo';

    /** Our own tickets' keys: never counted as a goal's work. */
    private const KEYS = ['stall' => 'goal-stall:', 'risk' => 'goal-risk:'];

    /** Run it once: returns the counts, for the job's log line. $now (UTC seconds) only for tests. */
    public static function tick(PDO $pdo, int $now = 0): array
    {
        GaugeTasks::ensure($pdo);
        $now = $now ?: time(); $today = gmdate('Y-m-d', $now);
        $hasDue = self::hasColumn($pdo, 'due_on');
        $n = ['goals' => 0, 'stalled' => 0, 'at_risk' => 0, 'opened' => 0, 'cleared' => 0, 'no_date' => 0];
        $goals = $pdo->query("SELECT g.* FROM control_gauge_tasks g WHERE g.kind = 'fix' AND g.ticket = 'work' AND g.parent_id IS NULL AND g.cleared_at IS NULL AND g.undone_at IS NULL
                                AND EXISTS (SELECT 1 FROM control_gauge_tasks c WHERE c.parent_id = g.id AND c.kind = 'fix' AND c.audit_key NOT LIKE 'goal-%') ORDER BY g.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($goals as $g) {
            $n['goals']++;
            $kids = self::openChildren($pdo, (int) $g['id']);
            // stalled: the oldest of its open tickets' last moves
            $stale = array_values(array_filter($kids, fn ($k) => self::lastMove($pdo, $k) < $now - self::STALL_DAYS * 86400));
            $lanes = array_values(array_unique(array_map(fn ($k) => self::laneOf($pdo, $k), $kids)));
            if ($stale) {
                $n['stalled']++;
                $lane = count($lanes) > 1 ? self::laneOf($pdo, $stale[0]) : ($lanes[0] ?? self::laneOf($pdo, $g));
                $list = implode(', ', array_map(fn ($k) => '#' . $k['id'], array_slice($stale, 0, 8))) . (count($stale) > 8 ? ' and ' . (count($stale) - 8) . ' more' : '');
                $n['opened'] += self::raise($pdo, $g, 'stall', $lane, 3,
                    'Goal #' . $g['id'] . ': ' . count($stale) . ' ticket' . (count($stale) === 1 ? '' : 's') . ' not moved in ' . self::STALL_DAYS . ' days (' . $list . ')',
                    'The daily goal check: ' . $list . ' under goal #' . $g['id'] . ' (' . mb_substr((string) $g['what'], 0, 120) . ') ha' . (count($stale) === 1 ? 's' : 've') . ' not moved in ' . self::STALL_DAYS . ' days. Its lane\'s manager (Tier 3) moves, reassigns or hands it off.');
            } else $n['cleared'] += self::clear($pdo, (int) $g['id'], 'stall');
            // at risk: only a goal with a date
            $due = $hasDue ? (string) ($g['due_on'] ?? '') : '';
            if ($due === '') { $n['no_date']++; $n['cleared'] += self::clear($pdo, (int) $g['id'], 'risk'); continue; }
            $why = self::atRisk($due, $kids, $today);
            if ($why !== '') {
                $n['at_risk']++;
                $owner = count($lanes) > 1 ? self::CROSS_LANE : self::executiveOf($pdo, $lanes[0] ?? self::laneOf($pdo, $g));
                $n['opened'] += self::raise($pdo, $g, 'risk', $owner, 4, 'Goal #' . $g['id'] . ': its date ' . $due . ' is at risk (' . $why . ')',
                    'The daily goal check: goal #' . $g['id'] . ' (' . mb_substr((string) $g['what'], 0, 120) . ') is due ' . $due . ', and ' . $why . '. '
                    . (count($lanes) > 1 ? 'Its tickets sit in more than one lane, so it\'s the CEO\'s (Tier 4).' : 'Its lane\'s executive (Tier 4) decides: more hands, a smaller scope, or a new date (a goal that must change is a Founder ruling).'));
            } else $n['cleared'] += self::clear($pdo, (int) $g['id'], 'risk');
        }
        ControlLog::add($pdo, 'control', '', 'The daily goal check: ' . $n['goals'] . ' goal' . ($n['goals'] === 1 ? '' : 's') . ' checked, ' . $n['stalled'] . ' with a stalled ticket, ' . $n['at_risk'] . ' with its date at risk; '
            . $n['opened'] . ' ticket' . ($n['opened'] === 1 ? '' : 's') . ' opened, ' . $n['cleared'] . ' cleared' . ($n['no_date'] ? '; ' . $n['no_date'] . ' without a due date (date not checked)' : ''), 'the system');
        return $n;
    }

    /** Why its date is at risk, in words, or '' (AT_RISK). */
    public static function atRisk(string $due, array $kids, string $today): string
    {
        if (!$kids) return '';
        $days = (int) floor((strtotime($due . ' 00:00:00 UTC') - strtotime($today . ' 00:00:00 UTC')) / 86400);
        if ($days < 0) return count($kids) . ' ticket' . (count($kids) === 1 ? ' is' : 's are') . ' still open past it';
        if ($days <= self::AT_RISK['soon_days']) {
            $early = array_filter($kids, fn ($k) => in_array((string) ($k['stage'] ?? ''), self::AT_RISK['early'], true));
            if ($early) return count($early) . ' ticket' . (count($early) === 1 ? ' is' : 's are') . ' still at Planning or Building with ' . $days . ' day' . ($days === 1 ? '' : 's') . ' to go';
        }
        return '';
    }

    /** A goal's open tickets (not ours). */
    private static function openChildren(PDO $pdo, int $goal): array
    {
        $st = $pdo->prepare("SELECT * FROM control_gauge_tasks WHERE parent_id = ? AND kind = 'fix' AND cleared_at IS NULL AND undone_at IS NULL AND audit_key NOT LIKE 'goal-%' ORDER BY id");
        $st->execute([$goal]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** When a ticket last moved: the newest of when it was added, its stage, its owner and its logged moves (UTC seconds). */
    private static function lastMove(PDO $pdo, array $t): int
    {
        $at = 0;
        foreach (['at', 'stage_at', 'owner_at'] as $c) if (!empty($t[$c])) $at = max($at, (int) strtotime($t[$c] . ' UTC'));
        $st = $pdo->prepare('SELECT MAX(at) FROM control_gauge_task_moves WHERE task_id = ?'); $st->execute([(int) $t['id']]);
        if ($m = $st->fetchColumn()) $at = max($at, (int) strtotime($m . ' UTC'));
        return $at;
    }

    /** A ticket's lane (decisions/0180 R2's TeamRoster::lane); the Project Manager's before R2 is live (its fallback). */
    private static function laneOf(PDO $pdo, array $t): string
    {
        return method_exists('TeamRoster', 'lane') ? TeamRoster::lane($pdo, $t) : 'project-manager';
    }

    /** The executive a lane's manager reports to (the Team's reports_to); the CEO when none is set. */
    private static function executiveOf(PDO $pdo, string $lane): string
    {
        $r = TeamRoster::bySlug($pdo, $lane);
        $up = $r ? (string) ($r['reports_to'] ?? '') : '';
        return $up !== '' ? $up : self::CROSS_LANE;
    }

    /** Open its one ticket for this goal and kind, unless one is open already. Returns 1 when it opened one. */
    private static function raise(PDO $pdo, array $g, string $kind, string $owner, int $tier, string $what, string $why): int
    {
        $key = self::KEYS[$kind] . $g['id'];
        if (self::openKey($pdo, $key)) return 0;
        $id = GaugeTasks::add($pdo, 'area:goals', $what, $why, 'the daily goal check (goal #' . $g['id'] . ')', 'the system', 0, '', 'work', (int) $g['id']);
        $pdo->prepare('UPDATE control_gauge_tasks SET audit_key = ?, tier = ?, owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$key, $tier, mb_substr($owner, 0, 80), $id]);
        return 1;
    }

    /** Close its open ticket when the goal passes: done by its own check, never by a press (CLAUDE.md's four steps). */
    private static function clear(PDO $pdo, int $goal, string $kind): int
    {
        $id = self::openKey($pdo, self::KEYS[$kind] . $goal); if (!$id) return 0;
        $pdo->prepare('UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP(), done_how = ? WHERE id = ? AND cleared_at IS NULL')
            ->execute(['Done: cleared by its own check (' . ($kind === 'stall' ? 'every ticket under goal #' . $goal . ' has moved' : 'goal #' . $goal . '\'s date is no longer at risk') . ')', $id]);
        ControlLog::add($pdo, 'clear', '', 'Task #' . $id . ' done: cleared by its own check (goal #' . $goal . ')', 'the system');
        return 1;
    }

    private static function openKey(PDO $pdo, string $key): int
    {
        $st = $pdo->prepare("SELECT id FROM control_gauge_tasks WHERE audit_key = ? AND ticket = 'work' AND cleared_at IS NULL AND undone_at IS NULL LIMIT 1"); $st->execute([$key]);
        return (int) $st->fetchColumn();
    }

    /** task-seed-4 adds due_on and measure; before it's live, dates aren't checked. */
    private static function hasColumn(PDO $pdo, string $c): bool
    {
        try { $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'); $st->execute(['control_gauge_tasks', $c]); return (int) $st->fetchColumn() > 0; }
        catch (Throwable) { return false; }
    }
}
