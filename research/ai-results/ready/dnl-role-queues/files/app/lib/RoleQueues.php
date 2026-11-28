<?php
// app/lib/RoleQueues.php — role queues, the agents' inbox (Jason, 2026-11-27: "Yes to role queues; mail only, OneDrive
// stays"; jason-spec §7; handoffs/decided-not-live.md). It is a view on the Work queue, never a second list. Each of the
// seven lanes (decisions/0180 §5: "every ticket routes to exactly one of the seven middle managers", TeamRoster::lane) and
// each role on the Team gets the Work queue's own rows, with their counts and the one service level decided so far: late
// means a day old and nobody has it (GaugeTasks::LATE_HOURS, kit-buttons' rule).
//
// Read only. It takes the rows GaugeTasks::queue() already built for the page and writes nothing. queue() moves stages by
// its own checks, so the page calls it once and hands its rows here. Per-stage time limits are not decided (the session
// manager's Q-B, kept for Jason's return: "no stall limit per stage (none decided)"), so there are none here. Alarms and
// reports emailed to Jason are not turned into rows here either: see release.json.
declare(strict_types=1);

require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';

final class RoleQueues
{
    /** The states a queue counts as open (GaugeTasks::queue()'s own states). */
    public const OPEN = ['open', 'taken', 'fixing', 'late', 'waiting'];
    /** A lane manager's inbox also holds its lane's tickets that nobody has yet, to take or hand on (0180 §1: Tier 3 triage). */
    public const TRIAGE = ['open', 'late'];
    /** Every state queue() gives a row; anything else is read as open. */
    private const STATES = ['open', 'taken', 'fixing', 'late', 'done', 'withdrawn', 'waiting'];
    /** How a Team role holds a ticket (TeamRoster::actor). */
    private const HELD = 'system · ';

    /**
     * The queues for the page: ['lanes' => slug => queue, 'roles' => slug => queue, 'unplaced' => rows]. Each queue is
     * [slug, name, rows, counts, open, late, oldest_open_hours, line]. A role queue's row says why it is there in 'here':
     * holds (the role has it), lane (its lane's manager, nobody has it yet) or ruling (a Founder ruling waiting for him).
     * Advocates have no queue: they are views and never hold work (0180 §5). Retired roles have none either. A ticket held
     * under a name no active or standby role has (a retired or renamed role) is listed in 'unplaced' so a person sees it.
     * $queue: GaugeTasks::queue()'s rows. $now: for tests.
     */
    public static function build(PDO $pdo, array $queue, ?int $now = null): array
    {
        $now = $now ?? time();
        $slugOf = [];
        foreach (TeamRoster::names($pdo) as $slug => $name) if (!isset($slugOf[$name])) $slugOf[$name] = (string) $slug;
        $lanes = [];
        foreach (TeamRoster::LANES as $l) $lanes[$l] = self::blank($l, TeamRoster::laneName($pdo, $l));
        $roles = []; $founder = '';
        foreach (TeamRoster::all($pdo) as $r) {
            if ((string) $r['kind'] === 'advocate') continue;
            if ((string) $r['kind'] === 'founder' && $founder === '') $founder = (string) $r['slug'];
            $roles[(string) $r['slug']] = self::blank((string) $r['slug'], (string) $r['name']) + ['status' => (string) $r['status'], 'kind' => (string) $r['kind']];
        }
        $unplaced = [];
        foreach ($queue as $t) {
            if (!is_array($t) || !isset($t['id'])) continue;
            $row = self::row($pdo, $t, $now);
            $lanes[$row['lane']]['rows'][] = $row;
            $owner = $row['owner'];
            if (str_starts_with($owner, self::HELD)) {
                $slug = $slugOf[substr($owner, strlen(self::HELD))] ?? '';
                if ($slug !== '' && isset($roles[$slug])) $roles[$slug]['rows'][] = $row + ['here' => 'holds'];
                elseif ($row['open']) $unplaced[] = $row;
            } elseif ($owner === '' && in_array($row['state'], self::TRIAGE, true) && isset($roles[$row['lane']])) {
                $roles[$row['lane']]['rows'][] = $row + ['here' => 'lane'];
            }
            if ($row['state'] === 'waiting' && $founder !== '') $roles[$founder]['rows'][] = $row + ['here' => 'ruling'];
        }
        foreach ($lanes as $k => $q) $lanes[$k] = self::finish($q);
        foreach ($roles as $k => $q) $roles[$k] = self::finish($q);
        return ['lanes' => $lanes, 'roles' => $roles, 'unplaced' => $unplaced];
    }

    /** A queue's line for its chip: "3 open · 1 late (a day old, nobody has it) · 1 waiting for the Founder", or "Nothing open". */
    public static function line(array $c): string
    {
        $open = 0; foreach (self::OPEN as $s) $open += (int) ($c[$s] ?? 0);
        if ($open === 0) return 'Nothing open';
        $p = [$open . ' open'];
        if ((int) ($c['late'] ?? 0) > 0) $p[] = (int) $c['late'] . ' late (a day old, nobody has it)';
        if ((int) ($c['waiting'] ?? 0) > 0) $p[] = (int) $c['waiting'] . ' waiting for the Founder';
        return implode(' · ', $p);
    }

    /** The service-level words for one row: only the decided rule (a day, nobody has it); no per-stage limit (Q-B). */
    public static function sla(string $state, int $ageHours): string
    {
        if ($state === 'late') return 'Late: a day old and nobody has it';
        if ($state === 'open') { $left = max(1, GaugeTasks::LATE_HOURS - $ageHours); return 'Nobody has it yet: late in ' . $left . ($left === 1 ? ' hour' : ' hours'); }
        if ($state === 'waiting') return 'Waiting for the Founder: no time limit is set';
        return '';
    }

    private static function blank(string $slug, string $name): array
    {
        return ['slug' => $slug, 'name' => $name, 'rows' => [], 'counts' => [], 'open' => 0, 'late' => 0, 'oldest_open_hours' => null, 'line' => ''];
    }

    private static function row(PDO $pdo, array $t, int $now): array
    {
        $state = (string) ($t['state'] ?? 'open'); if (!in_array($state, self::STATES, true)) $state = 'open';
        $at = trim((string) ($t['at'] ?? ''));
        $ts = $at !== '' ? strtotime($at . ' UTC') : false;
        $age = $ts !== false ? max(0, intdiv($now - $ts, 3600)) : 0;
        $lane = TeamRoster::lane($pdo, $t); if (!in_array($lane, TeamRoster::LANES, true)) $lane = 'project-manager';
        $pr = $t['priority'] ?? null;
        return ['id' => (int) $t['id'], 'what' => (string) ($t['what'] ?? ''), 'ticket' => (string) (($t['ticket'] ?? '') ?: 'work'), 'stage' => (string) ($t['stage'] ?? ''),
            'state' => $state, 'owner' => (string) ($t['owner_now'] ?? ($t['owner'] ?? '')), 'lane' => $lane,
            'priority' => ($pr === null || $pr === '') ? null : (int) $pr, 'tier' => (isset($t['tier']) && $t['tier'] !== '') ? (int) $t['tier'] : null,
            'age_hours' => $age, 'open' => in_array($state, self::OPEN, true), 'sla' => self::sla($state, $age)];
    }

    /** Open first; within open, late first, then priority 1, 2, 3, then none; then oldest id first. Counts and the line. */
    private static function finish(array $q): array
    {
        usort($q['rows'], fn ($a, $b) => [$a['open'] ? 0 : 1, $a['state'] === 'late' ? 0 : 1, $a['priority'] ?? 9, $a['id']]
            <=> [$b['open'] ? 0 : 1, $b['state'] === 'late' ? 0 : 1, $b['priority'] ?? 9, $b['id']]);
        $q['counts'] = GaugeTasks::counts($q['rows']);
        $open = 0; foreach (self::OPEN as $s) $open += (int) $q['counts'][$s];
        $q['open'] = $open; $q['late'] = (int) $q['counts']['late'];
        foreach ($q['rows'] as $r) if ($r['open'] && ($q['oldest_open_hours'] === null || $r['age_hours'] > $q['oldest_open_hours'])) $q['oldest_open_hours'] = $r['age_hours'];
        $q['line'] = self::line($q['counts']);
        return $q;
    }
}
