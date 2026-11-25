<?php
// app/lib/TeamRoster.php — the Team: the roles that work the Work queue (decisions/0179's Piece 2, handoffs/piece2-team.md
// §3.2 and §7, approved by Jason 2026-11-25). One row per entity in Jason's spec (handoffs/piece2/jason-spec.md §11): the
// Founder (the real login, never an agent), the day-to-day operators, the background keepers and the auditors, and the
// advocates, each bound to a test account (decisions/0145 §3) and only ever a view, never acting (0165 §3: a test
// account's work is never carried). Named TeamRoster, not Team: app/lib/Team.php is a business's own team (0102).
//
// What a role may do comes only from Access (Access::canAs with its acts_level and acts_dept); nothing here grants a
// permission. Its words (mandate, rules, stages) are a person's entries: ✎ Edit with the old words in its History (the
// board's logbook), never Undo (CLAUDE.md "Edit or Undo"). Its status moves Active ⇄ Standby ⇄ Inactive, each with its
// way back on the same card; a role is never deleted. The runner (P2-3) gives work only to an active role with a step.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';

final class TeamRoster
{
    /** The spec's four departments (jason-spec.md §11): key => [name, what it does]. */
    public const DEPARTMENTS = [
        'leadership' => ['Leadership', 'Vision, plain-English briefs, and the guards on simplicity, legal risk and the mission'],
        'operations' => ['Operations', 'Delivery, operations, finance, the historian and member support'],
        'build' => ['Build', 'Design, print, content, AI guides, engineering and QA'],
        'advocates' => ['Advocates', 'The audience: each bound to a test account, a view only'],
    ];
    /** Kinds (Jason's requirement 3: "separates day-to-day operators from background keepers and auditors"). */
    public const KINDS = [
        'founder' => ['Founder', 'The real login: answers Tier 3. Never an agent'],
        'operator' => ['Operator', 'Day to day: moves work along the queue'],
        'keeper' => ['Keeper', 'In the background: guards a rule or a record'],
        'auditor' => ['Auditor', 'Checks: confirms work and runs the audit'],
        'advocate' => ['Advocate', 'A test account\'s view, for checks only. Never acts'],
    ];
    /** What each is made of (the spec's entity_type). */
    public const MADE_OF = ['human' => 'A person', 'ai' => 'An agent', 'hybrid' => 'An agent and a person', 'persona' => 'A test account\'s view'];
    /** Status, each with its meaning and its way back. */
    public const STATUSES = [
        'active' => ['Active', 'Working: the runner can give it a step it has'],
        'standby' => ['On standby', 'Kept, not working: Make active brings it back'],
        'inactive' => ['Retired', 'Kept for the record, not working: Make active brings it back'],
    ];

    private static ?bool $ready = null;

    public static function ready(PDO $pdo): bool
    {
        if (self::$ready === true) return true;
        try { $pdo->query('SELECT 1 FROM team_roster LIMIT 0'); return self::$ready = true; } catch (Throwable) { return false; }
    }

    /** Every role, in the roster's order. */
    public static function all(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        return $pdo->query('SELECT * FROM team_roster ORDER BY sort, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM team_roster WHERE id = ?'); $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function bySlug(PDO $pdo, string $slug): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare('SELECT * FROM team_roster WHERE slug = ?'); $st->execute([$slug]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** "Staff · Operations", or "views only" for an advocate, "the real login" for the Founder. */
    public static function actsAs(array $r): string
    {
        if ($r['kind'] === 'founder') return 'the real login';
        if ($r['acts_level'] === '') return 'views only';
        require_once __DIR__ . '/../admin/Access.php';
        $lv = ['staff' => 'Staff', 'manager' => 'Manager', 'director' => 'Director', 'executive' => 'Executive'][$r['acts_level']] ?? ucfirst((string) $r['acts_level']);
        return $lv . ($r['acts_dept'] !== '' ? ' · ' . (Access::DEPT_NAMES[$r['acts_dept']] ?? $r['acts_dept']) : '');
    }

    /** May this role take an action? Only through Access's own rules, for its level and department (never a signed-in person's). */
    public static function can(array $r, string $action, array $default = ['min' => 'staff', 'depts' => []]): array
    {
        if ($r['status'] !== 'active') return [false, 'It\'s ' . strtolower(self::STATUSES[$r['status']][0] ?? $r['status']) . '.'];
        if ($r['kind'] === 'founder' || $r['kind'] === 'advocate' || $r['acts_level'] === '') return [false, $r['kind'] === 'founder' ? 'The Founder acts only when signed in, never as an agent.' : 'An advocate is a view only: it never acts.'];
        require_once __DIR__ . '/../admin/Access.php';
        return Access::canAs($action, (string) $r['acts_level'], (string) $r['acts_dept'], $default);
    }

    /** The name an agent acts under, in the logbook and on a task's owner ("system · Project Manager"). */
    public static function actor(array $r): string { return 'system · ' . $r['name']; }

    /** Edit a role's words (CLAUDE.md "Edit or Undo"): name, mandate, rules, stages; the old words go to its History. */
    public static function edit(PDO $pdo, int $id, array $in, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $id); if (!$r) throw new RuntimeException('That role isn\'t on the Team.');
        if (trim($who) === '') throw new RuntimeException('Only a staff member can edit the Team.');
        $set = []; $vals = []; $log = [];
        foreach (['name' => [160, 'name', false], 'mandate' => [2000, 'mandate', true], 'rules' => [2000, 'rules', true], 'stages' => [120, 'works', false]] as $k => [$max, $word, $multi]) {
            if (!array_key_exists($k, $in)) continue;
            $v = $multi ? trim(str_replace("\r", '', (string) $in[$k])) : trim(preg_replace('/\s+/', ' ', (string) $in[$k]));
            if ($k === 'name' && $v === '') throw new RuntimeException('Its name can\'t be empty.');
            if ($v === (string) $r[$k]) continue;
            $set[] = $k . ' = ?'; $vals[] = mb_substr($v, 0, $max);
            $log[] = $word . ' "' . mb_substr((string) $r[$k], 0, 120) . '" → "' . mb_substr($v, 0, 120) . '"';
        }
        if (!$set) return 'Nothing changed.';
        $set[] = 'updated_by = ?'; $vals[] = mb_substr($who, 0, 80); $set[] = 'updated_at = UTC_TIMESTAMP()'; $vals[] = $id;
        $pdo->prepare('UPDATE team_roster SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
        ControlLog::add($pdo, 'control', '', self::tag($r) . ' edited: ' . implode('; ', $log), $who, '', $tz);
        return 'Saved. The old words are in its History.';
    }

    /** Make active, Put on standby, Retire: each with its way back (the opposite button on the same card). The Founder stays active. */
    public static function setStatus(PDO $pdo, int $id, string $status, string $who, string $why = '', string $tz = ''): string
    {
        $r = self::get($pdo, $id); if (!$r) throw new RuntimeException('That role isn\'t on the Team.');
        if (!isset(self::STATUSES[$status])) throw new RuntimeException('Choose Active, On standby or Retired.');
        if ($r['kind'] === 'founder' && $status !== 'active') throw new RuntimeException('The Founder is always active: it\'s the real login.');
        if ($status === $r['status']) return 'It\'s already ' . strtolower(self::STATUSES[$status][0]) . '.';
        $pdo->prepare('UPDATE team_roster SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$status, mb_substr($who, 0, 80), $id]);
        ControlLog::add($pdo, 'control', '', self::tag($r) . ': ' . strtolower(self::STATUSES[$r['status']][0]) . ' → ' . strtolower(self::STATUSES[$status][0]), $who, trim($why), $tz);
        return ['active' => 'Active. ', 'standby' => 'On standby: it does no work until it\'s made active again. ', 'inactive' => 'Retired: kept for the record, and it can be made active again. '][$status]
            . ($status === 'active' && !in_array($r['kind'], ['advocate', 'founder'], true) && !self::hasSteps($r) ? 'It has no steps yet, so the runner gives it no work until one is written.' : '');
    }

    /** The steps the runner (P2-3) has for a role, by slug. Empty until a step is written for it (piece2-team.md §5). */
    public const STEPS = [
        'translator' => ['brief'], 'project-manager' => ['gate', 'stall'], 'ops-reconciliation' => ['audit'],
        'system-architect' => ['handoff'], 'qa-security' => ['confirm'],
    ];
    public static function hasSteps(array $r): bool { return !empty(self::STEPS[(string) $r['slug']]); }

    /** A role's History: its lines in the board's logbook, newest first. */
    public static function history(PDO $pdo, int $id): array
    {
        $r = self::get($pdo, $id); if (!$r) return [];
        try {
            $st = $pdo->prepare('SELECT at, tz, what, who, why FROM control_log WHERE what LIKE ? ORDER BY id DESC LIMIT 50');
            $st->execute([self::tag($r) . '%']);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return []; }
    }

    /** Counts for the page's line and the filter: working (active, not an advocate), advocates, on standby, retired. */
    public static function counts(array $all): array
    {
        $c = ['working' => 0, 'advocates' => 0, 'standby' => 0, 'inactive' => 0, 'active' => 0, 'all' => count($all)];
        foreach ($all as $r) {
            if ($r['status'] === 'active') { $c['active']++; if ($r['kind'] === 'advocate') $c['advocates']++; else $c['working']++; }
            elseif ($r['status'] === 'standby') $c['standby']++; else $c['inactive']++;
        }
        return $c;
    }

    /** Open tickets a role has on the Work queue (owner "system · <name>"). */
    public static function openTickets(PDO $pdo): array
    {
        $out = [];
        try { foreach ($pdo->query("SELECT owner, COUNT(*) n FROM control_gauge_tasks WHERE kind = 'fix' AND undone_at IS NULL AND cleared_at IS NULL AND owner LIKE 'system · %' GROUP BY owner")->fetchAll(PDO::FETCH_ASSOC) as $x) $out[substr((string) $x['owner'], strlen('system · '))] = (int) $x['n']; } catch (Throwable) {}
        return $out;
    }

    /** A staff member's name for the logbook. */
    public static function whoName(PDO $pdo, int $uid): string
    {
        try { require_once __DIR__ . '/../admin/Access.php'; $s = Access::staff(); if ($s && !empty($s['name'])) return (string) $s['name']; } catch (Throwable) {}
        try { $st = $pdo->prepare("SELECT COALESCE(NULLIF(display_name, ''), email) FROM users WHERE id = ?"); $st->execute([$uid]); $n = (string) $st->fetchColumn(); if ($n !== '') return $n; } catch (Throwable) {}
        return $uid > 0 ? 'Staff #' . $uid : '';
    }

    private static function tag(array $r): string { return 'Team role ' . $r['slug']; }
}
