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
    private static ?array $rules = null;

    /** The signed-in staff login, or null (an admin from before staff logins: everything, like the Founder). */
    public static function staff(): ?array
    {
        if (!self::$loaded) { self::$loaded = true; try { self::$staff = StaffLogin::inSession(Database::connection()) ?: null; } catch (Throwable) { self::$staff = null; } }
        return self::$staff;
    }

    /** [level, department] of the person acting (an Assistant: of the person they assist). */
    public static function who(): array
    {
        $s = self::staff();
        if ($s === null) return ['founder', ''];
        [$lv, $dep] = Workspaces::levelOf($s);
        if ($lv === 'assistant' && !empty($s['assists'])) {
            $boss = StaffLogin::get(Database::connection(), (int) $s['assists']);
            if ($boss && ($boss['status'] ?? '') !== 'ended') [$lv, $dep] = Workspaces::levelOf($boss);
        }
        return [$lv, $dep];
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
     * manager's check on P2-1, 2026-11-25). Tier 3 answers and the daily batch's approval are the Founder's (decisions/0179).
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
