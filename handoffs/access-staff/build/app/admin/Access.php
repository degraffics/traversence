<?php
// app/admin/Access.php — who may open what and do what in the admin (decisions/0109 A6; Master AOS §9A). A module
// opens by department (Workspaces::opens); each action has its own rule (a lowest level, and departments), the same
// wherever its tool is opened. The rules start from the defaults the tools declare and can be changed in System
// Configuration → Permissions. An action someone may not take is shown greyed out with the reason (Jason, 2026-10-22).
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Workspaces.php';
require_once __DIR__ . '/../lib/StaffLogin.php';

final class Access
{
    // how much each level can do (an Assistant acts with the person they assist)
    public const RANK = ['vendor' => 0, 'assistant' => 1, 'creator' => 2, 'staff' => 2, 'editor' => 3, 'manager' => 3, 'director' => 4, 'executive' => 5, 'founder' => 6];
    public const LEVEL_NAMES = ['staff' => 'Staff', 'manager' => 'a Manager', 'director' => 'a Director', 'executive' => 'an Executive', 'founder' => 'the Founder'];
    public const DEPT_NAMES = ['ops' => 'Operations', 'fin' => 'Finance', 'sal' => 'Sales', 'svc' => 'Service', 'con' => 'Content', 'eng' => 'Engineering'];

    private static ?array $staff = null;
    private static bool $loaded = false;
    private static bool $failed = false;   // the staff lookup threw: nobody is assumed (fail closed)
    /** Test seam: true or false stands in for PHP_SAPI === 'cli' (tools/access-tests); null in use. */
    public static ?bool $cliTest = null;
    private static ?array $rules = null;

    /** The signed-in staff login, or null (an admin from before staff logins: everything, like the Founder). */
    public static function staff(): ?array
    {
        if (!self::$loaded) { self::$loaded = true; try { self::$staff = StaffLogin::inSession(Database::connection()) ?: null; } catch (Throwable) { self::$staff = null; self::$failed = true; } }
        return self::$staff;
    }

    /**
     * [level, department] of the person acting (an Assistant: of the person they assist). With no staff session (the CEO's
     * urgent fix, 2026-11-26: a signed-in member was read as the Founder outside the admin, so the order upload targets let a
     * member add or replace a proof on any order): nobody ('', ''), so can() refuses. Two cases keep the old reading, each
     * named by a decision: a scheduled job on the command line (its callers act on what a person approved; piece2-team.md
     * finding 2 sends agents through canAs), and the admin's own pages during decisions/0100's switch-over (until an owner's
     * staff login is active, admins use their member account, which app/bootstrap.php has already checked). A staff lookup
     * that fails is nobody, never the Founder.
     */
    public static function who(): array
    {
        $s = self::staff();
        if ($s === null) return !self::$failed && (self::cli() || self::switchOver()) ? ['founder', ''] : ['', ''];
        [$lv, $dep] = Workspaces::levelOf($s);
        if ($lv === 'assistant' && !empty($s['assists'])) {
            $boss = StaffLogin::get(Database::connection(), (int) $s['assists']);
            if ($boss && ($boss['status'] ?? '') !== 'ended') [$lv, $dep] = Workspaces::levelOf($boss);
        }
        return [$lv, $dep];
    }

    private static function cli(): bool { return self::$cliTest ?? (PHP_SAPI === 'cli'); }

    /** An admin page or API during decisions/0100's switch-over (no owner's staff login yet), where bootstrap checked the admin. */
    private static function switchOver(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (!preg_match('#^/(admin|api/admin)(/|$)#', $path)) return false;
        try { return !StaffLogin::enforced(Database::connection()); } catch (Throwable) { return false; }
    }

    public static function canOpen(string $module): bool
    {
        return in_array($module, Workspaces::opens(self::staff(), Database::connection()), true);
    }

    /** The rule for an action: its saved one (Permissions), else its default. ['min' => level, 'depts' => [..]]. */
    public static function rule(string $action, array $default = ['min' => 'staff', 'depts' => []]): array
    {
        if (self::$rules === null) {
            self::$rules = [];
            try { foreach (Database::connection()->query('SELECT action, min_level, departments FROM ' . Database::t('admin_permissions') . '') as $r) self::$rules[$r['action']] = ['min' => $r['min_level'], 'depts' => array_values(array_filter(explode(',', (string) $r['departments'])))]; } catch (Throwable) {}
        }
        return self::$rules[$action] ?? $default;
    }

    /** May the person take this action? [true, ''] or [false, 'Needs a Manager in Sales or Operations']. */
    public static function can(string $action, array $default = ['min' => 'staff', 'depts' => []]): array
    {
        $r = self::rule($action, $default);
        [$lv, $dep] = self::who();
        if ($lv === '') return [false, 'Sign in with your staff login first'];
        $rank = self::RANK[$lv] ?? 0;
        if ($rank >= self::RANK['executive']) return [true, ''];   // Founder and Executives: everything
        $okLevel = $rank >= (self::RANK[$r['min']] ?? 2);
        $okDept = !$r['depts'] || in_array($dep, $r['depts'], true);
        if ($okLevel && $okDept) return [true, ''];
        return [false, 'Needs ' . (self::LEVEL_NAMES[$r['min']] ?? 'Staff') . ($r['min'] === 'staff' ? '' : ' or above') . ($r['depts'] ? ' in ' . implode(' or ', array_map(fn ($d) => self::DEPT_NAMES[$d] ?? $d, $r['depts'])) : '')];
    }

    public static function allowed(string $action, array $default = ['min' => 'staff', 'depts' => []]): bool { return self::can($action, $default)[0]; }

    /**
     * Is the person acting the Founder, signed in with the Founder's own staff login (role owner, level founder)? Never true
     * with no staff session: who() calls that the Founder for the admin's older logins, so can() alone can't tell (the session
     * manager's check on P2-1, 2026-11-25). Founder rulings (Tier 5) and the daily batch's approval are the Founder's (decisions/0180).
     */
    public static function isFounder(): bool
    {
        $s = self::staff();
        return $s !== null && ($s['status'] ?? '') === 'active' && Workspaces::levelOf($s)[0] === 'founder';
    }

    /**
     * May someone at this level and department take this action? The same rules as can() (saved in Permissions, else the
     * default), for a level and department that are named, never the signed-in person's (handoffs/piece2-team.md finding 2:
     * with no staff session, who() is the Founder, so can() in a scheduled job allows everything). The Team's agents act only
     * through this (TeamRoster::can). The Founder is never named here: a level above executive is refused.
     */
    public static function canAs(string $action, string $level, string $dept, array $default = ['min' => 'staff', 'depts' => []]): array
    {
        if (!isset(self::RANK[$level]) || self::RANK[$level] > self::RANK['executive']) return [false, 'No such level to act as'];
        $r = self::rule($action, $default);
        $rank = self::RANK[$level];
        if ($rank >= self::RANK['executive']) return [true, ''];
        $okLevel = $rank >= (self::RANK[$r['min']] ?? 2);
        $okDept = !$r['depts'] || in_array($dept, $r['depts'], true);
        if ($okLevel && $okDept) return [true, ''];
        return [false, 'Needs ' . (self::LEVEL_NAMES[$r['min']] ?? 'Staff') . ($r['min'] === 'staff' ? '' : ' or above') . ($r['depts'] ? ' in ' . implode(' or ', array_map(fn ($d) => self::DEPT_NAMES[$d] ?? $d, $r['depts'])) : '')];
    }

    public static function save(PDO $pdo, string $action, string $min, array $depts, int $by): void
    {
        if (!isset(self::LEVEL_NAMES[$min])) throw new InvalidArgumentException('Choose a level.');
        $depts = array_values(array_intersect(array_keys(self::DEPT_NAMES), $depts));
        $pdo->prepare('INSERT INTO ' . Database::t('admin_permissions') . ' (action, min_level, departments, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE min_level = VALUES(min_level), departments = VALUES(departments), updated_by = VALUES(updated_by)')
            ->execute([mb_substr($action, 0, 80), $min, implode(',', $depts), $by]);
        self::$rules = null;
    }
}
