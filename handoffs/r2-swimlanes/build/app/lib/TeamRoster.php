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
    /**
     * The layers (decisions/0180 §5: 4 layers and 32 roles, plus the advocates beside them), kept in the department column:
     * key => [name, what it does]. Piece 2's departments (leadership, operations, build) stay only on retired rows.
     */
    public const DEPARTMENTS = [
        'founder' => ['Founder', 'Layer 1: strategic direction and the Founder rulings (Tier 5)'],
        'executive' => ['Executive suite', 'Layer 2: guides Tier 3, reviews every change that touches a high-risk territory (Tier 4)'],
        'managers' => ['Middle managers', 'Layer 3: one exclusive swimlane each; triage between lanes (Tier 3)'],
        'specialists' => ['Specialists', 'Layer 4: operational and guard specialists, within their lane (Tiers 1 and 2)'],
        'advocates' => ['Advocates', 'Beside the layers: each bound to a test account, a view only'],
    ];
    /** Piece 2's departments, for a retired row's own card only (never a list or a filter). */
    public const OLD_DEPARTMENTS = ['leadership' => 'Leadership', 'operations' => 'Operations', 'build' => 'Build'];
    /** Kinds (Jason's requirement 3: "separates day-to-day operators from background keepers and auditors"). */
    public const KINDS = [
        'founder' => ['Founder', 'The real login: gives the Founder rulings (Tier 5). Never an agent'],
        'executive' => ['Executive', 'Reviews Tier 4 and guides its lanes\' managers'],
        'manager' => ['Manager', 'Owns one swimlane: every ticket routes to exactly one'],
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
        'inactive' => ['Retired', 'A role decisions/0180 replaced: kept only so past tickets and runs still name it; never shown or given work'],
    ];

    private static ?bool $ready = null;

    public static function ready(PDO $pdo): bool
    {
        if (self::$ready === true) return true;
        try { $pdo->query('SELECT 1 FROM team_roster LIMIT 0'); return self::$ready = true; } catch (Throwable) { return false; }
    }

    /**
     * The Team as shown: the 32 roles and the 12 advocates, in the roster's order. A retired role (one decisions/0180 replaced)
     * is never in a list, a filter or a count (Jason, 2026-11-26, #1441); get() and bySlug() still find it, so a past ticket or
     * run still shows its name.
     */
    public static function all(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        return $pdo->query("SELECT * FROM team_roster WHERE status <> 'inactive' ORDER BY sort, id")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** slug => name for every role, retired ones too (a past ticket or run still names who did it). */
    public static function names(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        $o = []; foreach ($pdo->query('SELECT slug, name FROM team_roster')->fetchAll(PDO::FETCH_ASSOC) as $r) $o[(string) $r['slug']] = (string) $r['name'];
        return $o;
    }

    /**
     * Who does a step whose role is $slug (decisions/0180 §5: "a role on standby is skipped upward: its work goes to the next
     * active role above it, and if none is active, to the Founder"): the role itself when active; else the first active role up
     * its reports-to line, passing over retired ones and, given the step's Access action, any role Permissions doesn't allow it
     * (so a stand-in never runs a step it would only be refused). [the role row, the role it stands in for or '']; null when
     * the line reaches the Founder, who is never an agent: the work waits for a person, nothing is dropped.
     */
    public static function doer(PDO $pdo, string $slug, string $action = '', array $default = ['min' => 'staff', 'depts' => []]): ?array
    {
        $seen = []; $r = self::bySlug($pdo, $slug); $for = $r ? (string) $r['name'] : $slug;
        while ($r && !isset($seen[$r['slug']])) {
            $seen[$r['slug']] = true;
            if ($r['kind'] === 'founder') return null;
            if ($r['status'] === 'active' && ($r['slug'] === $slug || $action === '' || self::can($r, $action, $default)[0])) return [$r, $r['slug'] === $slug ? '' : $for];
            $r = (string) $r['reports_to'] !== '' ? self::bySlug($pdo, (string) $r['reports_to']) : null;
        }
        return null;
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

    /** "Staff · Operations", or "views only" for an advocate, "the real login" for the Founder, "No permissions yet" for a role with none. */
    public static function actsAs(array $r): string
    {
        if ($r['kind'] === 'founder') return 'the real login';
        if ($r['acts_level'] === '') return $r['kind'] === 'advocate' ? 'views only' : 'No permissions yet';
        require_once __DIR__ . '/../admin/Access.php';
        $lv = ['staff' => 'Staff', 'manager' => 'Manager', 'director' => 'Director', 'executive' => 'Executive'][$r['acts_level']] ?? ucfirst((string) $r['acts_level']);
        return $lv . ($r['acts_dept'] !== '' ? ' · ' . (Access::DEPT_NAMES[$r['acts_dept']] ?? $r['acts_dept']) : '');
    }

    /** May this role take an action? Only through Access's own rules, for its level and department (never a signed-in person's). */
    public static function can(array $r, string $action, array $default = ['min' => 'staff', 'depts' => []]): array
    {
        if ($r['status'] !== 'active') return [false, 'It\'s ' . strtolower(self::STATUSES[$r['status']][0] ?? $r['status']) . '.'];
        if ($r['kind'] === 'founder' || $r['kind'] === 'advocate' || $r['acts_level'] === '') return [false, $r['kind'] === 'founder' ? 'The Founder acts only when signed in, never as an agent.' : ($r['kind'] === 'advocate' ? 'An advocate is a view only: it never acts.' : 'It has no permissions yet: only the Founder gives a role permissions (decisions/0180 §2).')];
        require_once __DIR__ . '/../admin/Access.php';
        return Access::canAs($action, (string) $r['acts_level'], (string) $r['acts_dept'], $default);
    }

    /** The name an agent acts under, in the logbook and on a task's owner ("system · Project Manager"). */
    public static function actor(array $r): string { return 'system · ' . $r['name']; }

    // ---- decisions/0180 R2: swimlanes (§5 "Every ticket routes to exactly one of the seven middle managers by its domain") --

    /** The seven middle managers' lanes (0180 §5, Layer 3), by slug. */
    public const LANES = ['project-manager', 'customer-service-manager', 'accounting-manager', 'it-manager', 'editorial-director', 'marketing-director', 'sales-director'];

    /**
     * Which lane a ticket's targets fall in: a gap 0180 §5 leaves, filled by who does the work (the appendix's reporting lines;
     * the session manager's review of R2, 2026-11-26). First match wins. Orders and fulfilment are Customer Service's (17
     * reports there), billing the Accounting Manager's (19); a ticket that's both is Customer Service's, Accounting gets it by
     * hand-off. Data, addresses, nations and privacy are the IT Manager's (22, 24, 25 report there), as are the system's own
     * pages and audits (23). Territory still decides the Tier 4 review whatever the lane (0180 §4). Routing only: never in
     * TeamRunner::ruleData, so the decision tree's approval stands.
     */
    public const LANE_ROUTES = [
        'customer-service-manager' => ['libs' => ['Orders', 'ClientOrders', 'WorkOrders', 'OrderFiles', 'Proofs'], 'tables' => ['orders', 'order_lines', 'order_events', 'order_files', 'work_orders'],
            'paths' => ['/order.php', '/user/orders.php', '/market/cart.php', '/market/order.php', '/qr/cart.php', '/admin/backoffice', '/admin/orders', '/admin/clients', '/user/support', '/contact.php'], 'jobs' => ['qb_orders_tick', 'got_it_tick', 'pay_wait_tick']],
        'accounting-manager' => ['libs' => ['Billing', 'QuickBooksPay', 'QuickBooks', 'Wallet', 'MarketPay', 'AccountDomains'], 'tables' => ['subscriptions', 'subscription_charges', 'order_ledger', 'order_refund_requests', 'order_payment_cancels', 'wallet_cards'],
            'paths' => ['/admin/ecommerce/plans', '/admin/backoffice/plans', '/user/billing', '/business/pay-connect.php', '/api/market-pay.php'], 'jobs' => ['billing_tick'], 'services' => ['quickbooks', 'stripe', 'square']],
        'it-manager' => ['territories' => ['data', 'address', 'nations', 'privacy'],
            'libs' => ['Releases', 'BeforeAfter', 'Staging', 'StagingSite', 'StagingSync', 'StagingPublish', 'StagingGate', 'Scheduler', 'TeamRunner', 'TeamRoster', 'GaugeTasks', 'ControlAlarms', 'ControlGauges', 'DbUpdates', 'Switches', 'CPanel', 'Notify', 'Mailer'],
            'paths' => ['/admin/config', '/admin/control', '/admin/crawler', '/admin/sources', '/admin/auto-imports', '/admin/cluster-tools', '/admin/insights/crawler', '/api/crawl/', '/admin/staging', '/admin/releases'], 'jobs' => ['*'],
            'words' => '/\b(zip check|zipcheck|migration|release pipeline|before-and-after check|tools\/page-audit)\b/i'],   // the release pipeline's own tools
        'sales-director' => ['libs' => ['MarketBiz', 'Storefront', 'Claims', 'Partners'], 'paths' => ['/market', '/shop', '/business', '/claim', '/admin/marketplace', '/admin/partners', '/admin/claims', '/storefront']],
        'marketing-director' => ['words' => '/\b(SEO|taxonomy|campaign|funnel|brand drift)\b/i', 'paths' => ['/admin/marketing', '/admin/seo']],
        'editorial-director' => ['libs' => [], 'paths' => ['/faq.php', 'app/ui/faq.md', '/place', '/listing', '/guide', '/journey', '/explore', '/community', '/admin/content', '/admin/guides', 'design/'], 'words' => '/\b(FAQ|copy|wording|microcopy|guide|design)\b/i'],
    ];

    /**
     * A ticket's lane: the one it was handed off to, else the parent's (a Resolution keeps its work's lane), else audits the
     * IT Manager's, else the first route its targets match (TeamRunner::targetsOf over its words, its gauge's job), else the
     * Project Manager's ("Swimlane: delivery", the fallback). $t: a control_gauge_tasks row.
     */
    public static function lane(PDO $pdo, array $t, int $depth = 0): string
    {
        $stored = (string) ($t['lane'] ?? ''); if (in_array($stored, self::LANES, true)) return $stored;
        if (!empty($t['parent_id']) && $depth < 3) {
            try { $st = $pdo->prepare('SELECT * FROM control_gauge_tasks WHERE id = ?'); $st->execute([(int) $t['parent_id']]); if ($p = $st->fetch(PDO::FETCH_ASSOC)) return self::lane($pdo, $p, $depth + 1); } catch (Throwable $x) {}
        }
        if ((string) ($t['ticket'] ?? '') === 'audit') return 'it-manager';
        return self::laneOf(self::ticketTargets($t), implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['build_brief'] ?? '')]));
    }

    /** What a ticket names: its words' files and pages (TeamRunner::targetsOf), its gauge's job or component. */
    public static function ticketTargets(array $t): array
    {
        if (!class_exists('TeamRunner')) require_once __DIR__ . '/TeamRunner.php';
        $text = implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['source'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['build_brief'] ?? '')]);
        $paths = []; if (preg_match_all('#(?<![\w.])(/(?:admin|user|market|shop|business|api|place|listing|guide|journey|explore|community|qr|claim|storefront)[\w./-]*|/[\w-]+\.php)#', $text, $m)) $paths = $m[1];
        $out = TeamRunner::targetsOf('', $paths, $text);
        $g = (string) ($t['gauge'] ?? ''); if (preg_match('/^(?:job|wf|task):([a-z0-9_]+)/', $g, $m)) $out[] = 'job:' . $m[1];
        return $out;
    }

    /** The lane for a set of targets (and the words, for the routes that read words), by LANE_ROUTES' order. */
    public static function laneOf(array $targets, string $text = ''): string
    {
        if (!class_exists('TeamRunner')) require_once __DIR__ . '/TeamRunner.php';
        $kinds = ['path' => 'paths', 'job' => 'jobs', 'svc' => 'services', 'lib' => 'libs', 'table' => 'tables'];
        foreach (self::LANE_ROUTES as $lane => $r) {
            foreach ($targets as $tg) {
                [$k, $v] = array_pad(explode(':', (string) $tg, 2), 2, ''); $v = trim($v); if ($v === '' || !isset($kinds[$k])) continue;
                if ($k === 'path') { $v = (string) (parse_url($v, PHP_URL_PATH) ?? $v); $v = preg_replace('#^(website|sync-storage)/#', '/', $v); }
                foreach ($r[$kinds[$k]] ?? [] as $x) if ($x === '*' || ($k === 'path' ? ($v === $x || str_starts_with($v, $x) || str_starts_with(ltrim($v, '/'), ltrim($x, '/'))) : strcasecmp($v, $x) === 0)) return $lane;
            }
            if (!empty($r['territories']) && in_array(TeamRunner::touches($targets), $r['territories'], true)) return $lane;
            if (!empty($r['words']) && $text !== '' && preg_match($r['words'], $text)) return $lane;
        }
        return 'project-manager';
    }

    /** A lane's name, for its chip: the manager's name on the Team (its own words), else the slug in words. */
    /** The seven lanes' names as decisions/0180 writes them (layer 3, the appendix's rows 8-14), for when the Team has no row yet. */
    public const LANE_NAMES = ['project-manager' => 'Project Manager', 'customer-service-manager' => 'Customer Service Manager', 'accounting-manager' => 'Accounting Manager',
        'it-manager' => 'IT Manager', 'editorial-director' => 'Editorial Director', 'marketing-director' => 'Marketing Director', 'sales-director' => 'Sales Director'];
    public static function laneName(PDO $pdo, string $lane): string { $n = self::names($pdo)[$lane] ?? ''; return $n !== '' ? $n : (self::LANE_NAMES[$lane] ?? $lane); }

    /**
     * May this actor hand a ticket off from its lane (0180 §1: hand-offs between lanes are Tier 3, so the holding lane's
     * manager does it; an executive above that lane too, since Tier 4 guides Tier 3; the Founder always; nobody else; the
     * session manager's review of R2). $actor: a role's slug, or 'founder' for the Founder's login. [ok, why not].
     */
    public static function mayHandOff(PDO $pdo, string $lane, string $actor): array
    {
        if ($actor === 'founder') return [true, ''];
        if ($actor === $lane) return [true, ''];
        $seen = []; $r = self::bySlug($pdo, $lane);
        while ($r && !isset($seen[$r['slug']]) && (string) $r['reports_to'] !== '') {
            $seen[$r['slug']] = true; $r = self::bySlug($pdo, (string) $r['reports_to']);
            if ($r && $r['slug'] === $actor && $r['kind'] === 'executive') return [true, ''];
        }
        return [false, 'Only ' . self::laneName($pdo, $lane) . ', an executive above that lane, or the Founder hands it off (decisions/0180 §1: Tier 3).'];
    }

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
        if (!in_array($status, ['active', 'standby'], true)) throw new RuntimeException('Choose Active or On standby (Retired is only for the roles decisions/0180 replaced).');
        if ($r['status'] === 'inactive') throw new RuntimeException('It\'s a role decisions/0180 replaced: kept only so past tickets name it.');
        if ($r['kind'] === 'founder' && $status !== 'active') throw new RuntimeException('The Founder is always active: it\'s the real login.');
        if ($status === $r['status']) return 'It\'s already ' . strtolower(self::STATUSES[$status][0]) . '.';
        $pdo->prepare('UPDATE team_roster SET status = ?, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$status, mb_substr($who, 0, 80), $id]);
        ControlLog::add($pdo, 'control', '', self::tag($r) . ': ' . strtolower(self::STATUSES[$r['status']][0]) . ' → ' . strtolower(self::STATUSES[$status][0]), $who, trim($why), $tz);
        return ['active' => 'Active. ', 'standby' => 'On standby: its work goes to the next active role above it until it\'s made active again. '][$status]
            . ($status === 'active' && !in_array($r['kind'], ['advocate', 'founder'], true) && !self::hasSteps($r) ? 'It has no steps yet, so the runner gives it no work until one is written.' : '');
    }

    /** The steps the runner has for a role (TeamRunner::STEPS, the one list), by slug: none until the runner exists (P2-3). */
    public static function steps(string $slug): array
    {
        if (!class_exists('TeamRunner') && is_file(__DIR__ . '/TeamRunner.php')) require_once __DIR__ . '/TeamRunner.php';
        if (!class_exists('TeamRunner')) return [];
        return array_keys(array_filter(TeamRunner::STEPS, fn ($s) => $s[0] === $slug));
    }
    public static function hasSteps(array $r): bool { return self::steps((string) $r['slug']) !== []; }

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

    /** Counts for the page's line and the filter: working (active, not an advocate), advocates, on standby (retired roles are never counted). */
    public static function counts(array $all): array
    {
        $c = ['working' => 0, 'advocates' => 0, 'standby' => 0, 'inactive' => 0, 'active' => 0, 'all' => count($all)];
        foreach ($all as $r) {
            if ($r['status'] === 'inactive') { $c['all']--; continue; }
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
