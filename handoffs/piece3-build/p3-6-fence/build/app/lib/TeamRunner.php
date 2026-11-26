<?php
// app/lib/TeamRunner.php — the Team's agent runner (decisions/0179's Piece 2, release P2-3; handoffs/piece2-team.md §5, approved
// by Jason 2026-11-25). A scheduled job (team_tick) that, on staging only, takes one ticket, does one step for one active role,
// logs it, and stops. What it does reaches live only when staging is published, inside the approved batch (Jason's Q1 A: the
// Work queue's tables are records).
//
// The rules it keeps (each from the design and the session manager's rulings):
//   - Staging only: Staging::here() or it does nothing and logs nothing (on live the job is QUIET, so its silence isn't late).
//   - Off until switched on: the switch team_runner (System Configuration → Switches, on staging).
//   - One run at a time (GET_LOCK), one step a run, a ticket at most once an hour.
//   - Never a ticket a person has (owner a person's name): only unowned ones, or ones a Team role has ("system · <role>").
//   - Never Tier 3: on a Resolution ticket it may only propose the brief's words; only the Founder answers (0179 rule 3).
//   - Its permissions only through Access::canAs, for the role's level and department (TeamRoster::can); never test accounts.
//   - Internal first and free: every step here reads our own records and writes through the owning library. No step calls an
//     outside service or paid AI (Jason's Q4 B), so none asks StagingGate::hold() or AiClient::allow(); a paid step, if ever
//     added, holds alone at the cap and raises one brief per ticket (Jason, 2026-11-25; piece2-team.md §5.1).
//   - A person's words are never overwritten: where a step would write words a person may have written, it proposes them,
//     and a person's Accept applies them as an Edit with History.
//   - Every run has Undo (an action rolled back as if never done), or says why it can't; a second Undo is refused.
//   - Each run notes what it touched (touches), for piece 3's before-and-after check.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';
require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';

final class TeamRunner
{
    public const SWITCH = 'team_runner';
    /** A ticket is worked at most once in this many minutes (piece2-team.md §5: "no run on it in the last hour"). */
    public const GAP_MINUTES = 60;
    /** Each step: [the role's slug, what it's called, the Access action it needs, that action's default rule]. */
    public const STEPS = [
        'gate' => ['project-manager', 'Moves a Work ticket from Planning to Building once its gate passes', 'team.gate', ['min' => 'manager', 'depts' => ['ops']]],
        'handoff' => ['system-architect', 'Writes a Building ticket\'s build brief and hands it to the session manager', 'team.handoff', ['min' => 'staff', 'depts' => ['eng']]],
        'brief' => ['translator', 'Proposes a Resolution brief\'s plain words from its ticket and its decisions', 'team.brief', ['min' => 'staff', 'depts' => ['ops']]],
        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
    ];
    /**
     * The ceiling (Jason, 2026-11-25: "limit agents and build to only the initial work que stage until I fully vet the builds";
     * decisions/0179's note on how the Team's capacity grows): the furthest stage agents work in. key => [name, what agents do].
     */
    public const CEILINGS = [
        'planning' => ['Planning', 'Agents only propose: the Project Manager says a ticket is ready for Building, the Translator suggests a brief\'s words, the audit opens tickets. A person accepts or dismisses each.'],
        // Jason, 2026-11-25 (through the session manager): the Project Manager acts at Building and takes audit-raised tickets
        // itself; at Implementation the brief goes straight to a build session; at Confirmation, Tier 1 and 2 publish alone
        'building' => ['Building', 'The Project Manager moves a ticket to Building once its gate passes, and takes the tickets the audit raised itself; a ticket a person entered still waits for Give it to the Team. The System Architect writes build briefs for the session manager.'],
        'implementation' => ['Implementation', 'As Building, and the System Architect\'s build brief goes straight to a build session, through the report (build-requests.json).'],
        'confirmation' => ['Confirmation and safe publish', 'Agents act as at Implementation, and a release that touches no Tier 3 area publishes by itself once staging\'s checks and the advocates pass it (Tier 1 and Tier 2, rule 2); Tier 3 comes to the Founder as a Decision Brief.'],
    ];
    /**
     * How far Implementation reaches (P3-6, the fence; decisions/0179's note "agents work on the backend first", Jason,
     * 2026-11-26: "get the agents active … without them pushing live file, except for the backend"; "Admin does count for
     * backend"). key => [name, what it means]. Ships "backend"; only the Founder changes it (setReach).
     */
    public const REACH = [
        'backend' => ['Backend only', 'A brief whose change stays in the backend (the admin, the site\'s internal code, the workers) goes to a build session; one that reaches a page a visitor, member or business sees stops at its brief and waits for you.'],
        'everywhere' => ['Everywhere', 'Briefs go to a build session wherever their change reaches.'],
    ];
    /** What each step does at each ceiling: act, propose, or off. */
    public const STEP_AT = [
        'gate' => ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'handoff' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'brief' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],
        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
    ];
    /**
     * Tier 3 by what a finding touches (decisions/0179's note, 2026-11-25: "detected by what a change or finding touches"), each
     * area from the code: its libraries, tables, page paths, jobs and services. Words are only a secondary flag (TIER3).
     */
    public const TIER3_AREAS = [
        'money' => ['libs' => ['QuickBooksPay', 'Billing', 'Orders', 'ClientOrders', 'Wallet', 'MarketPay', 'AccountDomains'],
            'tables' => ['orders', 'order_lines', 'order_ledger', 'order_events', 'order_refund_requests', 'order_payment_cancels', 'subscriptions', 'subscription_charges', 'wallet_cards', 'account_domains', 'domain_cart', 'market_orders', 'market_pay_events'],
            'paths' => ['/order.php', '/user/orders.php', '/market/cart.php', '/market/order.php', '/qr/cart.php', '/shop/domains.php', '/user/domains/', '/business/pay-connect.php', '/api/market-pay.php', '/api/market-webhook.php', '/api/qb-webhook.php', '/api/client-order.php', '/api/domains.php', '/api/admin/client-order.php', '/admin/order.php', '/admin/client-order.php', '/admin/quickbooks.php', '/admin/backoffice/orders', '/admin/backoffice/production'],
            'jobs' => ['billing_tick', 'pay_wait_tick', 'qb_orders_tick', 'got_it_tick', 'market_tick'], 'services' => ['quickbooks', 'stripe', 'square']],
        // a migration is Tier 3 (data) by what it does, not by being one (Jason, 2026-11-25, through the session manager: CREATE
        // TABLE IF NOT EXISTS or a guarded ADD COLUMN/INDEX alone is 0179's safe additive; UPDATE, DELETE, DROP, MODIFY or
        // RENAME stays Tier 3; TRUNCATE deletes and CHANGE renames or modifies a column). ReleaseKind::writes() reads them.
        'data' => ['libs' => ['DataMove', 'DbUpdates', 'StagingPublish'], 'tables' => ['db_updates_log'], 'paths' => ['/admin/config/dbupdates'], 'jobs' => [], 'services' => [],
            'sql' => ['UPDATE', 'DELETE', 'DROP', 'MODIFY', 'RENAME', 'TRUNCATE', 'CHANGE']],
        'address' => ['libs' => [], 'tables' => ['short_codes', 'business_domains'], 'paths' => ['/.htaccess', '/admin/router.php', 'trvgo/index.php', 'trvgo/.htaccess', 'site-audit/pages.md'], 'jobs' => ['domains_tick'], 'services' => ['cloudflare', 'namecom', 'fly']],
        // HeldNames and its read (P3-5, the session manager, 2026-11-26: 0066's own area, a data entry only)
        'nations' => ['libs' => ['Landmarks', 'Nations', 'HeldNames'], 'tables' => ['landmarks', 'nations', 'nation_decision_log'], 'paths' => ['/place/landmark.php', '/admin/landmarks.php', '/admin/nations.php', '/api/crawl/landmarks.php', '/api/held-hashes.php'], 'jobs' => ['landmark_photos_tick', 'heritage_tick'], 'services' => []],
        'privacy' => ['libs' => ['Auth', 'StaffLogin', 'TestAccounts', 'People', 'StepUpAuth', 'AccountDeletion'], 'tables' => ['users', 'staff_accounts', 'email_confirmations', 'people_connections', 'contact_cards', 'messages', 'user_otp_challenges', 'two_step_devices', 'test_records'],
            'paths' => ['/api/auth/', '/user/accounts.php', '/user/delete-account.php', '/user/api/delete-account.php', '/user/api/profile.php', '/user/api/people.php', '/admin/people.php', '/admin/api/people.php', '/admin/config/testaccounts', '/privacy.php', 'app/storage/'], 'jobs' => ['guest_tick'], 'services' => []],
    ];

    /**
     * What one step may cost (Piece 3, P3-4: "a spending limit per agent"). No step calls paid AI or an outside service
     * (Q4 B), so each is $0; a step that ever costs more than its role's limit this month is refused (spendLimit).
     */
    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];

    /** A role's monthly limit in micro-dollars (team_settings "spend:<slug>"; $0 unless the Founder sets one). */
    public static function spendLimit(PDO $pdo, string $slug): int { $v = self::setting($pdo, 'spend:' . $slug); return $v !== null && is_numeric($v['v']) ? max(0, (int) $v['v']) : 0; }

    /** What a role's runs cost this month (team_runs.cost_micros). */
    public static function spentThisMonth(PDO $pdo, string $slug): int
    {
        try { $st = $pdo->prepare("SELECT COALESCE(SUM(cost_micros), 0) FROM team_runs WHERE role = ? AND at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')"); $st->execute([$slug]); return (int) $st->fetchColumn(); } catch (Throwable $x) { return 0; }
    }

    /** The rule data the decision tree's approval is tied to (0179's note: "a fingerprint of its rules, not its wording"). */
    public static function ruleData(): array
    {
        $steps = []; foreach (self::STEPS as $k => [$role, , $action, $def]) $steps[$k] = [$role, $action, $def];
        return ['steps' => $steps, 'step_at' => self::STEP_AT, 'ceilings' => array_keys(self::CEILINGS), 'tier3_areas' => self::TIER3_AREAS, 'tier3_words' => self::TIER3, 'tier1_words' => self::TIER1, 'step_cost' => self::STEP_COST_MICROS];
    }
    /** With $pdo, the reach setting joins the rules (P3-6: changing it clears the tree's approval). */
    public static function fingerprint(?PDO $pdo = null): string { return hash('sha256', json_encode(self::ruleData() + ($pdo ? ['reach' => self::reach($pdo)] : []))); }

    /** How far Implementation reaches: 'backend' (as shipped, and whenever it can't be read) or 'everywhere'. */
    public static function reach(PDO $pdo): string { $r = self::setting($pdo, 'reach'); return $r && ($r['v'] ?? '') === 'everywhere' ? 'everywhere' : 'backend'; }

    /**
     * The Founder sets how far Implementation reaches (checked here too, never a job or an agent). Logged in the Team's History
     * with the value before; changing it changes the fingerprint, so the tree's approval is cleared (treeApproval).
     */
    public static function setReach(PDO $pdo, string $to, string $who, string $tz = ''): string
    {
        if (!isset(self::REACH[$to])) throw new RuntimeException('Choose Backend only or Everywhere.');
        require_once dirname(__DIR__) . '/admin/Access.php';
        if (!Access::isFounder()) throw new RuntimeException('Only the Founder changes how far Implementation reaches (decisions/0179\'s note, 2026-11-26).');
        $now = self::reach($pdo); if ($now === $to) return 'It\'s already ' . self::REACH[$to][0] . '.';
        self::put($pdo, 'reach', $to, $who, $tz);
        ControlLog::add($pdo, 'control', '', 'Team setting: Implementation reaches ' . self::REACH[$now][0] . ' → ' . self::REACH[$to][0] . ' (Change back on the Team page)', $who, '', $tz);
        self::treeApproval($pdo);   // a changed rule clears the approval now, said in the History
        return 'Implementation reaches ' . self::REACH[$to][0] . ' now.';
    }

    /**
     * The fence (P3-6): the files a ticket's change names that reach a page a visitor, member or business sees, or why it can't
     * be shown to stay in the backend. [] when every file named is backend: an /admin/ page or its parts (website/admin/,
     * app/admin/), a worker (workers/), or a file the live mirror's reach map (reach.py --fence-map, carried in gauges.json as
     * "fence") shows reaching no public page. A ticket naming no file, or a file the map doesn't hold, isn't proved backend.
     */
    public static function frontOf(PDO $pdo, array $t): array
    {
        $words = implode("\n", [(string) $t['what'], (string) $t['why'], (string) ($t['goal'] ?? ''), (string) ($t['in_scope'] ?? ''), (string) ($t['build_brief'] ?? '')]);
        foreach (GaugeTasks::steps($pdo, (int) $t['id']) as $st) $words .= "\n" . $st['see'] . ' ' . $st['do_what'];
        preg_match_all('#\b((?:app|website|workers|trvgo)/[\w./-]+\.(?:php|js|css|py|sql|json|md))\b#', $words, $m);
        $files = array_values(array_unique($m[1]));
        if (!$files) return ['(it names no file, so it can\'t be shown to stay in the backend)'];
        $back = self::fenceMap($pdo);
        $out = [];
        foreach ($files as $f) {
            if (str_starts_with($f, 'website/admin/') || str_starts_with($f, 'app/admin/') || str_starts_with($f, 'workers/')) continue;
            if ($back !== null && isset($back[$f])) continue;
            $out[] = $f . ($back === null ? ' (the reach map isn\'t here yet)' : '');
        }
        return $out;
    }

    /**
     * Is one file of a release backend (P3-6), as reach.py's fence judges it: the admin's pages and parts, a new file under app/
     * (its callers decide, and they're checked too), a migration that only adds, or a file the reach map holds.
     */
    public static function backendFile(PDO $pdo, string $p, bool $new, string $text = ''): bool
    {
        if (str_starts_with($p, 'website/admin/') || str_starts_with($p, 'app/admin/') || str_starts_with($p, 'workers/')) return true;
        if (str_starts_with($p, 'app/migrations/')) { require_once __DIR__ . '/ReleaseKind.php'; return !ReleaseKind::writes($text) && !ReleaseKind::insertsRows([$p => [$text, null]]); }
        if (str_starts_with($p, 'app/') && $new) return true;
        $map = self::fenceMap($pdo);
        return $map !== null && isset($map[$p]);
    }

    /** The live mirror's backend-only files (gauges.json's "fence"), or null when it isn't there. */
    private static function fenceMap(PDO $pdo): ?array
    {
        try { require_once __DIR__ . '/ControlGauges.php'; $f = (array) (ControlGauges::config($pdo)['fence'] ?? []); } catch (Throwable $x) { return null; }
        return ($f['at'] ?? '') !== '' && is_array($f['backend'] ?? null) ? array_fill_keys(array_map('strval', $f['backend']), true) : null;   // no map read yet: nothing is proved backend
    }

    /**
     * Where a ticket's brief goes now (report()'s "brief_to"): a build session from Implementation on, behind the fence ("founder"
     * when Backend only and its change reaches a public page, or a build refused it as front end), else the session manager.
     */
    public static function briefFor(PDO $pdo, array $t): string
    {
        if (self::briefTo($pdo) !== 'build') return 'session manager';
        if (self::reach($pdo) === 'everywhere') return 'build';
        if (self::setting($pdo, 'front:' . (int) $t['id'])) return 'founder';
        return self::frontOf($pdo, $t) ? 'founder' : 'build';
    }

    /**
     * A build session's zip refused at the fence (send.py --team-ticket; carried in gauges.json as "fence_refusals"): the ticket
     * goes back to its brief and waits for the Founder, said once on its run log and in the logbook.
     */
    public static function applyRefusals(PDO $pdo): int
    {
        try { require_once __DIR__ . '/ControlGauges.php'; $rs = (array) (ControlGauges::config($pdo)['fence_refusals'] ?? []); } catch (Throwable $x) { return 0; }
        $n = 0;
        foreach ($rs as $r) {
            $id = (int) ($r['ticket'] ?? 0); if ($id < 1 || self::setting($pdo, 'front:' . $id)) continue;
            $t = GaugeTasks::task($pdo, $id); if (!$t) continue;
            $why = mb_substr('A build reached a public page: ' . (string) ($r['reason'] ?? '') . ' (' . (string) ($r['zip'] ?? '') . ')', 0, 380);
            self::put($pdo, 'front:' . $id, $why, 'the fence');
            $pdo->prepare('UPDATE control_gauge_tasks SET rel = \'\' WHERE id = ?')->execute([$id]);
            ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' back at its brief, front end: waiting for the Founder (' . $why . ')', 'the fence');
            $n++;
        }
        return $n;
    }

    private static function setting(PDO $pdo, string $k): ?array
    {
        try { $st = $pdo->prepare('SELECT * FROM team_settings WHERE k = ?'); $st->execute([$k]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $x) { return null; }
    }
    private static function put(PDO $pdo, string $k, ?string $v, string $who, string $tz = ''): void
    {
        if ($v === null) { $pdo->prepare('DELETE FROM team_settings WHERE k = ?')->execute([$k]); return; }
        $pdo->prepare('INSERT INTO team_settings (k, v, who, at, tz) VALUES (?, ?, ?, UTC_TIMESTAMP(), ?) ON DUPLICATE KEY UPDATE v = VALUES(v), who = VALUES(who), at = VALUES(at), tz = VALUES(tz)')->execute([$k, $v, mb_substr($who, 0, 80), $tz]);
    }

    /** The decision tree's approval while its rules are unchanged, else null. A changed rule clears it and lowers the ceiling, logged. */
    public static function treeApproval(PDO $pdo): ?array
    {
        $a = self::setting($pdo, 'tree'); if (!$a) return null;
        if ((string) $a['v'] === self::fingerprint($pdo)) return $a;
        self::put($pdo, 'tree', null, 'the system');
        $c = self::setting($pdo, 'ceiling');
        if ($c && $c['v'] !== 'planning') self::put($pdo, 'ceiling', 'planning', 'the system');
        ControlLog::add($pdo, 'control', '', 'Team setting: the decision tree\'s rules changed since ' . $a['who'] . ' approved them, so the approval is cleared and agents work up to Planning again', 'the system');
        return null;
    }

    /** Approve the decision tree (the Founder's alone: the caller checks Access::isFounder). */
    public static function approveTree(PDO $pdo, string $who, string $tz = ''): string
    {
        if (self::treeApproval($pdo)) return 'It\'s already approved, as it stands.';
        self::put($pdo, 'tree', self::fingerprint($pdo), $who, $tz);
        ControlLog::add($pdo, 'control', '', 'Team setting: the decision tree approved (fingerprint ' . substr(self::fingerprint($pdo), 0, 12) . ')', $who, '', $tz);
        return 'Approved. Raising "Agents work up to" is open now, one stage at a time, each with its evidence.';
    }

    /** Where agents work up to: Planning until the Founder raises it, and always Planning while the decision tree isn't approved. */
    public static function ceiling(PDO $pdo): string
    {
        if (!self::treeApproval($pdo)) return 'planning';
        $c = self::setting($pdo, 'ceiling');
        return $c && isset(self::CEILINGS[$c['v']]) ? (string) $c['v'] : 'planning';
    }

    /** What a step does now: act, propose or off. */
    public static function mode(PDO $pdo, string $step): string { return self::STEP_AT[$step][self::ceiling($pdo)] ?? 'off'; }
    /** Where a build brief goes (report()'s "brief_to"): a build session from Implementation on, else the session manager. */
    public static function briefTo(PDO $pdo): string { return in_array(self::ceiling($pdo), ['implementation', 'confirmation'], true) ? 'build' : 'session manager'; }

    /** Change the ceiling (the Founder's alone): up one stage at a time once the tree is approved, never to Confirmation yet; down any time. */
    public static function setCeiling(PDO $pdo, string $to, string $who, string $why = '', string $tz = ''): string
    {
        if (!isset(self::CEILINGS[$to])) throw new RuntimeException('Choose Planning, Building, Implementation or Confirmation.');
        $keys = array_keys(self::CEILINGS); $now = self::ceiling($pdo);
        $from = array_search($now, $keys, true); $dest = array_search($to, $keys, true);
        if ($dest === $from) return 'It\'s already ' . self::CEILINGS[$to][0] . '.';
        if ($dest > $from) {
            if (!self::treeApproval($pdo)) throw new RuntimeException('Approve the decision tree first.');
            if ($dest !== $from + 1) throw new RuntimeException('One stage at a time, each on its evidence.');
            if ($to === 'confirmation' && ($w = self::confirmationWaits($pdo)) !== '') throw new RuntimeException($w);
        }
        self::put($pdo, 'ceiling', $to, $who, $tz);
        ControlLog::add($pdo, 'control', '', 'Team setting: agents work up to ' . self::CEILINGS[$now][0] . ' → ' . self::CEILINGS[$to][0] . (trim($why) !== '' ? ' (' . trim($why) . ')' : ''), $who, '', $tz);
        return 'Agents work up to ' . self::CEILINGS[$to][0] . ' now.';
    }

    /** The evidence for raising to a stage (0179's note, §3): each line with its reading here and its source; the Founder judges. */
    public static function evidence(PDO $pdo, string $to): array
    {
        $q = function (string $sql) use ($pdo) { try { return (int) $pdo->query($sql)->fetchColumn(); } catch (Throwable $x) { return null; } };
        require_once __DIR__ . '/Staging.php'; $here = Staging::here() ? 'staging' : 'the live site';
        $runs = $q('SELECT COUNT(*) FROM team_runs');
        if ($to === 'building') {
            $acc = $q("SELECT COUNT(*) FROM team_runs WHERE state = 'proposed' AND settled_at IS NOT NULL AND undone_at IS NULL"); $dis = $q("SELECT COUNT(*) FROM team_runs WHERE state = 'proposed' AND undone_at IS NOT NULL"); $open = $q("SELECT COUNT(*) FROM team_runs WHERE state = 'proposed' AND settled_at IS NULL");
            $noUndo = $q("SELECT COUNT(*) FROM team_runs WHERE state = 'done' AND undo_how = ''");
            return [['Planning\'s suggestions', $acc === null ? 'No runs table here' : $acc . ' accepted · ' . $dis . ' dismissed · ' . $open . ' open', 'team_runs on ' . $here],
                ['Staging isolation', Staging::here() ? 'This is staging: the runner works here only' : 'This is the live site: ' . (int) $runs . ' runs recorded here (the runner never runs on live; its work arrives only by Publish)', 'TeamRunner::blocked, team_tick.php'],
                ['Every run logged with its Undo', $runs === null ? 'No runs table here' : (int) $runs . ' runs · ' . (int) $noUndo . ' that did something without an Undo', 'team_runs.undo_how']];
        }
        if ($to === 'implementation') {
            $briefs = $q("SELECT COUNT(*) FROM team_runs WHERE step = 'handoff'"); $back = $q("SELECT COUNT(*) FROM team_runs WHERE step = 'handoff' AND undone_at IS NOT NULL");
            $refused = $q("SELECT COUNT(*) FROM team_runs WHERE state = 'refused'"); $adv = $q("SELECT COUNT(*) FROM team_runs r JOIN team_roster t ON t.slug = r.role WHERE t.kind IN ('advocate', 'founder')");
            require_once __DIR__ . '/StagingGate.php'; $held = null; try { $held = StagingGate::counts($pdo); } catch (Throwable $x) {}
            return [['Building\'s outputs', $briefs === null ? 'No runs table here' : (int) $briefs . ' build briefs written · ' . (int) $back . ' taken back', 'team_runs (step handoff)'],
                ['The agents\' permission check', 'Every step asks Access::canAs for its role\'s level · ' . (int) $refused . ' refused · ' . (int) $adv . ' runs by an advocate or the Founder (must be 0)', 'TeamRoster::can, team_runs'],
                ['Holds on outside calls, payments and email', $held === null ? 'Read on staging' : 'StagingGate: ' . json_encode($held), 'StagingGate::counts']];
        }
        // Confirmation and safe publish (0179's note §3; Piece 3, P3-4): what the before-and-after check and rule 2 have done
        $rel = []; try { $rel = $pdo->query("SELECT id, zip_name, state, checks, log, live_at, updated_at FROM site_releases WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 14 DAY")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) {}
        $ba = ['pass' => 0, 'block' => 0, 'none' => 0]; $byZip = []; $escapes = 0; $alone = 0; $undone = 0;
        foreach ($rel as $x) {
            $it = null; foreach ((array) (json_decode((string) $x['checks'], true)['items'] ?? []) as $i) if (($i[0] ?? '') === 'Before and after (advocates)') $it = $i[1];
            $ba[$it === true ? 'pass' : ($it === false ? 'block' : 'none')]++;
            $byZip[(string) $x['zip_name']][] = $it;
            if ($it === true && $x['state'] === 'rolled_back') $escapes++;
            if (str_contains((string) $x['log'], 'Published alone (')) { $alone++; if ($x['state'] === 'rolled_back') $undone++; }
        }
        $wrong = 0; foreach ($byZip as $its) if (in_array(false, $its, true) && end($its) === true) $wrong++;   // blocked, then the same zip passed unchanged in name
        return [['Releases checked by the advocates', '14 days: ' . $ba['pass'] . ' passed · ' . $ba['block'] . ' blocked · ' . $ba['none'] . ' not checked', 'site_releases: the check "Before and after (advocates)"'],
            ['Blocks that were wrong', $wrong . ' blocked, then sent again under the same name and passed', 'site_releases by zip name'],
            ['Escapes', $escapes . ' passed by the advocates, then rolled back', 'site_releases: rolled_back'],
            ['Undo end to end', $alone . ' published alone (rule 2) · ' . $undone . ' of them undone (rolled back)', 'site_releases: "Published alone"'],
            ['Ready', ($w = self::confirmationWaits($pdo)) === '' ? 'The before-and-after check and rule 2 are on' : $w, 'Switches: before_after, rule2_autopublish'],
            // Brief A (Jason, 2026-11-25: "keep staging record publishes in the daily batch for now"): revisit before this raise
            ['A staging Publish', 'Isn\'t checked by the advocates: it stays in the daily batch (Brief A). Decide whether it should be, before raising to Confirmation.', 'decisions/0179\'s note "Piece 3\'s decisions"; handoffs/piece3-check.md §15']];
    }

    /** Why Confirmation can't open yet ('' when it can): the before-and-after check and rule 2 both on (decisions/0179 rule 2). */
    public static function confirmationWaits(PDO $pdo): string
    {
        require_once __DIR__ . '/Switches.php'; $all = Switches::all($pdo);
        $on = fn ($k) => !empty($all[$k]) && !$all[$k]['killed'] && $all[$k]['state'] === 'on';
        if (!$on('before_after')) return 'Needs the before-and-after check switched on (Piece 3).';
        if (!$on('rule2_autopublish')) return 'Needs rule 2 switched on: low-risk releases publish alone (the Founder\'s switch).';
        return '';
    }

    /** A finding looks again after this many hours (piece2-team.md §6: "the next day's run checks it again"; the job runs daily). */
    public const SECOND_LOOK_HOURS = 20;
    /**
     * The tier rules (decisions/0179; piece2-team.md §6), on a finding's words: Tier 3 when it touches one of 0179 rule 3's
     * boundaries; Tier 1 when it's only the kinds rule 2 names as low-risk (wording, the FAQ, tests, the phone layout); Tier 2
     * otherwise (it still waits for the approved batch, 0179 rule 1). boundary => pattern.
     */
    public const TIER3 = [
        'money' => '/\b(orders?|payments?|billing|invoices?|refunds?|charges?|charged|prices?|pricing|quickbooks|stripe|square|payouts?|subscriptions?)\b/i',
        'data' => '/\b(delete|deleted|deletes|drop|dropped|migrations?|purge|purged|truncate|data loss|reshape)\b/i',
        'address' => '/\b(routes?|urls?|redirects?|public address|404)\b/i',
        'nations' => '/(\b0066\b|\bnations?\b|\btribal\b|\bsacred\b|\bburials?\b|archaeolog|landmark visibility|landmarks::)/i',
        'privacy' => '/\b(privacy|private|personal|email address(es)?|phone numbers?|consent|is_test|leak)\b/i',
        // the two boundaries with no area in the code (0179 rule 3): a finding about them is always a word flag for a person
        'mission' => '/\b(mission|commission|zero-commission)\b/i',
        'deadlock' => '/\b(deadlock(ed)?|conflicting decisions|decisions? (that )?(disagree|conflict|contradict)s?|contradicts? (decision|adr) ?\d{0,4})\b/i',
    ];
    public const TIER1 = '/\b(wording|typo|faq|label|tests?|layout|390|phone|mobile|contrast|spacing)\b/i';

    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_runs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, role VARCHAR(60) NOT NULL DEFAULT '',
            task_id INT UNSIGNED NULL, step VARCHAR(20) NOT NULL DEFAULT '', seen VARCHAR(400) NOT NULL DEFAULT '', did VARCHAR(400) NOT NULL DEFAULT '',
            state VARCHAR(10) NOT NULL DEFAULT 'done', why VARCHAR(400) NOT NULL DEFAULT '', proposal TEXT NULL, undo_how VARCHAR(400) NOT NULL DEFAULT '', undo_data TEXT NULL,
            undone_at DATETIME NULL, undone_by VARCHAR(80) NOT NULL DEFAULT '', settled_at DATETIME NULL, settled_by VARCHAR(80) NOT NULL DEFAULT '',
            cost_micros INT NOT NULL DEFAULT 0, touches VARCHAR(400) NOT NULL DEFAULT '', KEY task_at (task_id, at), KEY at (at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_settings (k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', who VARCHAR(80) NOT NULL DEFAULT '', at DATETIME NULL, tz VARCHAR(64) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** May it run here, now? null when yes, else why not (in words). */
    public static function blocked(PDO $pdo): ?string
    {
        require_once __DIR__ . '/Staging.php';
        if (!Staging::here()) return 'live';   // never on the live site
        require_once __DIR__ . '/Switches.php';
        try { if (!Switches::on($pdo, self::SWITCH)) return 'The Team\'s runner is switched off (System Configuration → Switches, on staging).'; } catch (Throwable $x) { return 'The switches couldn\'t be read: ' . $x->getMessage(); }
        if (!TeamRoster::ready($pdo)) return 'The Team isn\'t set up yet (its migration hasn\'t run).';
        return null;
    }

    /** One run: one ticket, one step. Returns a line for the job's log ('' on live: nothing logged). */
    public static function tick(PDO $pdo): string
    {
        $why = self::blocked($pdo);
        if ($why === 'live') return '';
        if ($why !== null) return $why;
        self::ensure($pdo);
        if ((int) $pdo->query("SELECT GET_LOCK('team_tick', 0)")->fetchColumn() !== 1) return 'Another run is going: this one did nothing.';
        try {
            $back = self::applyRefusals($pdo);
            $pick = self::pick($pdo);
            if (!$pick) return $back ? $back . ' ticket' . ($back === 1 ? '' : 's') . ' back at the brief (the fence); nothing else to do.' : 'Nothing to do: no ticket a working role has a step for.';
            [$t, $step, $role] = $pick;
            return self::run($pdo, $t, $step, $role);
        } finally { $pdo->query("SELECT RELEASE_LOCK('team_tick')"); }
    }

    /** The ticket and step for this run: [task row, step, role row], or null. Priority first, then age (the queue's own order). */
    public static function pick(PDO $pdo): ?array
    {
        $roles = [];
        foreach (self::STEPS as $step => [$slug]) { if ($step === 'audit' || self::mode($pdo, $step) === 'off') continue; $r = TeamRoster::bySlug($pdo, $slug); if ($r && $r['status'] === 'active') $roles[$step] = $r; }   // a step off at this ceiling gets no work
        if (!$roles) return null;
        $recent = [];
        try { $st = $pdo->prepare('SELECT DISTINCT task_id FROM team_runs WHERE at > UTC_TIMESTAMP() - INTERVAL ' . self::GAP_MINUTES . ' MINUTE'); $st->execute(); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $recent[(int) $x] = true; } catch (Throwable $x) {}
        $q = array_filter(GaugeTasks::queue($pdo), fn ($t) => !in_array($t['state'], ['done', 'withdrawn'], true) && !isset($recent[(int) $t['id']]) && self::mayWork($t));
        usort($q, fn ($a, $b) => [((int) ($a['priority'] ?? 0)) ?: 4, (int) $a['id']] <=> [((int) ($b['priority'] ?? 0)) ?: 4, (int) $b['id']]);
        foreach ($q as $t) foreach ($roles as $step => $role) if (self::applies($pdo, $step, $t)) return [$t, $step, $role];
        return null;
    }

    /**
     * A ticket the Project Manager takes itself from Building on (Jason, 2026-11-25): unowned, and raised by the audit (an Audit
     * ticket, or one whose parent is). One a person entered waits for Give it to the Team.
     */
    public static function takesItself(PDO $pdo, array $t): bool
    {
        if (trim((string) $t['owner']) !== '') return false;
        if ((string) ($t['ticket'] ?? '') === 'audit') return true;
        if (empty($t['parent_id'])) return false;
        try { $st = $pdo->prepare('SELECT ticket FROM control_gauge_tasks WHERE id = ?'); $st->execute([(int) $t['parent_id']]); return $st->fetchColumn() === 'audit'; } catch (Throwable $x) { return false; }
    }

    /** Never a ticket a person has: only unowned ones, or ones a Team role has. */
    public static function mayWork(array $t): bool { $o = (string) $t['owner']; return $o === '' || str_starts_with($o, 'system · '); }

    /** Does this step have work on this ticket? */
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && !self::openProposal($pdo, (int) $t['id'], 'gate')
            && (GaugeTasks::gate($pdo, $t) === [] || (self::mode($pdo, 'gate') === 'act' && self::takesItself($pdo, $t) && GaugeTasks::gate($pdo, $t) === ['someone to have it (Take it)']));
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        return false;
    }

    /**
     * [tier, boundary, flag] for a finding (0179's note, 2026-11-25: Tier 3 "detected by what a change or finding touches"). Its
     * targets come first: a page or file path (by its start), a job, a service, a library or a table named in TIER3_AREAS makes
     * it Tier 3 in that area. Words alone never do: a word in TIER3 leaves it Tier 2 with that area as its flag ("Words suggest a
     * Tier 3 boundary: a person checks"). Tier 1 only for the low-risk kinds (TIER1) with no flag; anything else Tier 2.
     * Targets: ['path:/order.php', 'job:billing_tick', 'svc:stripe', 'lib:Orders', 'table:orders'].
     */
    public static function tier(string $text, array $targets = []): array
    {
        if ($b = self::touches($targets)) return [3, $b, ''];
        foreach (self::TIER3 as $b => $re) if (preg_match($re, $text)) return [2, '', $b];
        return [preg_match(self::TIER1, $text) ? 1 : 2, '', ''];
    }

    /** The Tier 3 area a finding's targets touch, or ''. */
    public static function touches(array $targets): string
    {
        $kinds = ['path' => 'paths', 'job' => 'jobs', 'svc' => 'services', 'lib' => 'libs', 'table' => 'tables', 'sql' => 'sql'];
        foreach ($targets as $tg) {
            [$k, $v] = array_pad(explode(':', (string) $tg, 2), 2, ''); $v = trim($v); if ($v === '' || !isset($kinds[$k])) continue;
            if ($k === 'path') { $v = (string) (parse_url($v, PHP_URL_PATH) ?? $v); $v = preg_replace('#^(website|sync-storage)/#', '/', $v); }
            foreach (self::TIER3_AREAS as $b => $a) foreach ($a[$kinds[$k]] ?? [] as $x) {
                if ($k === 'path' ? ($v === $x || str_starts_with($v, $x) || str_starts_with(ltrim($v, '/'), ltrim($x, '/'))) : strcasecmp($v, $x) === 0) return $b;
            }
        }
        return '';
    }

    /** What a finding's own record names: its page and paths, the files in its detail (app/lib/X.php → lib:X), its job, its component. */
    public static function targetsOf(string $page, array $paths = [], string $detail = ''): array
    {
        $out = [];
        foreach (array_merge([$page], $paths) as $p) if (trim((string) $p) !== '') $out[] = 'path:' . trim((string) $p);
        if (preg_match_all('#\b(app/(?:lib|admin)/([A-Za-z]+)\.php|app/migrations/[\w.-]+|website/[\w./-]+\.php|trvgo/[\w./-]+)#', $detail, $m, PREG_SET_ORDER))
            foreach ($m as $x) $out[] = ($x[2] ?? '') !== '' ? 'lib:' . $x[2] : 'path:' . $x[1];
        return array_values(array_unique($out));
    }

    /**
     * What the audit finds, from our own records first (piece2-team.md §6): Site health's open findings (the file both sites
     * read, through StagingGate inside SiteHealth), jobs that are late here (Scheduler's own record, quiet and retired ones
     * left out), and this site's open alarms. key => [what, why].
     */
    public static function findings(PDO $pdo): array
    {
        $out = [];
        try { require_once __DIR__ . '/SiteHealth.php';
            foreach (SiteHealth::open($pdo) as $f) $out['finding:' . $f['id']] = ['Site health: ' . $f['title'], trim($f['severity'] . ' · ' . $f['detail'] . ($f['page'] !== '' ? ' · ' . $f['page'] : '') . ($f['blocks'] ? ' · blocks ' . $f['blocks'] . ' path' . ($f['blocks'] === 1 ? '' : 's') : '')),
                self::targetsOf((string) $f['page'], [], (string) $f['detail'])];   // its 'paths' are the visitor paths it blocks (names, not pages)
        } catch (Throwable $x) {}
        try { require_once __DIR__ . '/Scheduler.php'; require_once __DIR__ . '/Staging.php';
            $skip = array_merge(Scheduler::QUIET, Scheduler::RETIRED, Staging::here() ? Scheduler::NOT_ON_STAGING : []);
            foreach (Scheduler::lastRuns($pdo) as $job => $r) {
                if (in_array($job, $skip, true) || !isset(Scheduler::JOBS[$job])) continue;
                $late = (int) Scheduler::JOBS[$job][3]; $st = $pdo->prepare('SELECT TIMESTAMPDIFF(MINUTE, ?, NOW())'); $st->execute([(string) $r['started_at']]); $mins = (int) $st->fetchColumn();
                if ($late > 0 && $mins > $late) $out['job:' . $job] = ['A scheduled job is late: ' . Scheduler::JOBS[$job][0], 'Its last run started ' . round($mins / 60, 1) . ' hours ago; it\'s late after ' . round($late / 60, 1) . ' (Scheduled tasks).', ['job:' . $job]];
                elseif ((int) ($r['fails_today'] ?? 0) > 0 && (int) $r['ok'] === 0) $out['job:' . $job . ':fail'] = ['A scheduled job failed: ' . Scheduler::JOBS[$job][0], 'Its last run stopped: ' . (string) $r['note'], ['job:' . $job]];
            }
        } catch (Throwable $x) {}
        try { foreach ($pdo->query("SELECT * FROM control_alarms WHERE cleared_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $c = (string) ($a['component'] ?? ''); $tg = [];   // what it touches: its component (svc:, ep:, job:), else the job or service its key names
                foreach (array_filter([$c, (string) $a['akey']]) as $k) {
                    if (preg_match('#^svc:([a-z0-9_]+)#', $k, $m)) $tg[] = 'svc:' . $m[1];
                    elseif (preg_match('#^ep:([a-z0-9_/.-]+)#', $k, $m)) $tg[] = 'path:/' . ltrim($m[1], '/');
                    elseif (preg_match('#^(?:job|task:failed):([a-z0-9_]+)#', $k, $m)) $tg[] = 'job:' . $m[1];
                }
                $out['alarm:' . $a['id']] = ['An open alarm: ' . $a['title'], 'Level ' . $a['level'] . ' on the System Control Board.', array_values(array_unique($tg))];
            }
        } catch (Throwable $x) {}
        return $out;
    }

    /**
     * The daily audit (the Ops & Reconciliation Manager's step): each new finding opens one Audit ticket at Found; on its second
     * look a day later, one still there is Confirmed and opens the Work (Tier 1–2) or Resolution (Tier 3) ticket it calls for,
     * and one gone is cleared by its own check. Each ticket it opens is a run with Undo (withdraws it). Staging only, its switch on.
     */
    public static function audit(PDO $pdo, string $how = 'the daily run'): string
    {
        $why = self::blocked($pdo); if ($why === 'live') return ''; if ($why !== null) return $why;
        self::ensure($pdo);
        $role = TeamRoster::bySlug($pdo, 'ops-reconciliation');
        if (!$role || $role['status'] !== 'active') return 'The Operations & Reconciliation Manager isn\'t active: no audit.';
        [, , $action, $def] = self::STEPS['audit'];
        [$ok, $no] = TeamRoster::can($role, $action, $def);
        if (!$ok) return self::log($pdo, $role, 0, 'audit', 'its permissions', '', 'refused', $no . ' (Permissions: ' . $action . ')');
        if ((int) $pdo->query("SELECT GET_LOCK('team_audit', 0)")->fetchColumn() !== 1) return 'Another audit is going: this one did nothing.';
        try {
            $who = TeamRoster::actor($role); $now = self::findings($pdo); $n = ['opened' => 0, 'confirmed' => 0, 'cleared' => 0];
            $open = $pdo->query("SELECT * FROM control_gauge_tasks WHERE ticket = 'audit' AND audit_key <> '' AND undone_at IS NULL AND cleared_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            $have = []; foreach ($open as $t) $have[(string) $t['audit_key']] = $t;
            foreach ($open as $t) {   // the second look
                if ($t['stage'] !== 'found' || time() - (int) strtotime($t['at'] . ' UTC') < self::SECOND_LOOK_HOURS * 3600) continue;
                if (!isset($now[$t['audit_key']])) { GaugeTasks::auditClear($pdo, (int) $t['id'], $who); $n['cleared']++; continue; }
                $c = GaugeTasks::auditConfirm($pdo, (int) $t['id'], $who); $n['confirmed']++;
                self::log($pdo, $role, $c, 'audit', 'its second look at task #' . $t['id'], 'Confirmed task #' . $t['id'] . ' and opened this ' . ((int) $t['tier'] === 3 ? 'Resolution' : 'Work') . ' ticket (Tier ' . (int) $t['tier'] . ')', 'done', '', null, 'Withdraws this ticket', ['withdraw' => $c], 'task:' . $c);
            }
            foreach ($now as $k => $fd) {   // a new finding
                [$what, $words] = $fd; $tg = (array) ($fd[2] ?? []);
                if (isset($have[$k])) continue;
                [$tier, $b, $flag] = self::tier($what . ' ' . $words, $tg);
                $id = GaugeTasks::auditOpen($pdo, $k, $what, $words . ($b !== '' ? ' (Tier 3: ' . GaugeTasks::BOUNDARIES[$b] . ', by what it touches)' : ''), $tier, $who, '', $flag);
                self::log($pdo, $role, $id, 'audit', $k . ($tg ? ' · touches ' . implode(', ', $tg) : ''), 'Found: ' . $what . ' (Tier ' . $tier . ($flag !== '' ? ', words suggest ' . (GaugeTasks::BOUNDARIES[$flag] ?? $flag) . ': a person checks' : '') . '); it\'s looked at again tomorrow', 'done', '', null, 'Withdraws this ticket', ['withdraw' => $id], 'task:' . $id);
                $n['opened']++;
            }
            return $who . ' · audit (' . $how . '): ' . count($now) . ' finding' . (count($now) === 1 ? '' : 's') . ' · ' . $n['opened'] . ' new · ' . $n['confirmed'] . ' confirmed · ' . $n['cleared'] . ' cleared as noise';
        } finally { $pdo->query("SELECT RELEASE_LOCK('team_audit')"); }
    }

    /** Do the step, log it, and return the line. */
    public static function run(PDO $pdo, array $t, string $step, array $role): string
    {
        self::ensure($pdo);
        [, $name, $action, $def] = self::STEPS[$step];
        $who = TeamRoster::actor($role); $id = (int) $t['id'];
        [$ok, $no] = TeamRoster::can($role, $action, $def);
        if (!$ok) return self::log($pdo, $role, $id, $step, 'its permissions', '', 'refused', $no . ' (Permissions: ' . $action . ')');
        $cost = (int) (self::STEP_COST_MICROS[$step] ?? 0);   // the spending limit per agent (Piece 3, P3-4)
        if ($cost > 0 && self::spentThisMonth($pdo, (string) $role['slug']) + $cost > self::spendLimit($pdo, (string) $role['slug']))
            return self::log($pdo, $role, $id, $step, 'its spending limit', '', 'refused', 'It would spend $' . number_format($cost / 1e6, 2) . ', over this month\'s limit for ' . $role['name'] . ' ($' . number_format(self::spendLimit($pdo, (string) $role['slug']) / 1e6, 2) . ').');
        try {
            if ($step === 'gate' && self::mode($pdo, 'gate') === 'propose') {   // at Planning: a proposal a person accepts (Jason, 2026-11-25)
                return self::log($pdo, $role, $id, $step, 'its goal, Done when, decisions and owner', 'Proposed: ready for Building (its gate passes)', 'proposed', '', ['move' => 'building'], 'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
            if ($step === 'gate') {
                $took = trim((string) $t['owner']) === '' && self::takesItself($pdo, $t);   // an audit-raised ticket: it takes it first
                if ($took) $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ? AND owner = \'\'')->execute([mb_substr($who, 0, 80), $id]);
                try { $msg = GaugeTasks::move($pdo, $id, 'building', $who, 'its gate passed: a goal, Done when, its decisions and someone who has it'); }
                catch (RuntimeException $x) { if ($took) $pdo->prepare('UPDATE control_gauge_tasks SET owner = \'\', owner_at = NULL WHERE id = ?')->execute([$id]); throw $x; }
                return self::log($pdo, $role, $id, $step, 'its goal, Done when, decisions and owner', ($took ? 'Took it (the audit raised it); ' . lcfirst($msg) : $msg), 'done', '', null,
                    $took ? 'Moves it back to Planning and gives it back to nobody' : 'Moves it back to Planning', $took ? ['to' => 'planning', 'owner' => ''] : ['to' => 'planning'], 'task:' . $id);
            }
            if ($step === 'handoff') {
                $brief = self::buildBrief($pdo, $t);
                $was = (string) $t['owner'];
                $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = ?, owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$brief, mb_substr($who, 0, 80), $id]);
                $to = self::briefFor($pdo, ['build_brief' => $brief] + $t);   // Implementation on: straight to a build session (Jason, 2026-11-25), behind the fence (P3-6)
                $said = $to === 'build' ? 'straight to a build session' : ($to === 'founder' ? 'front end: waiting for you (it reaches a public page: ' . mb_substr(implode(', ', self::frontOf($pdo, ['build_brief' => $brief] + $t)), 0, 160) . ')' : 'with the session manager');
                ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' build brief written; ' . $said . ' (hourly report, build-requests.json)', $who);
                return self::log($pdo, $role, $id, $step, 'its scope, decisions and steps' . ($to !== 'session manager' ? ', and how far it reaches (the fence)' : ''), 'Wrote its build brief; ' . $said . ' at the next hourly report', 'done', '', null,
                    'Takes the brief back and gives the ticket back to ' . ($was !== '' ? $was : 'nobody'), ['owner' => $was], 'task:' . $id);
            }
            if ($step === 'brief') {
                $d = self::briefDraft($pdo, $t);
                return self::log($pdo, $role, $id, $step, 'its words, its decisions and the boundary', 'Proposed ' . count($d) . ' part' . (count($d) === 1 ? '' : 's') . ' of its brief: ' . implode(', ', array_map(fn ($k) => strtolower(GaugeTasks::BRIEF[$k][0]), array_keys($d))), 'proposed', '', $d,
                    'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
        } catch (RuntimeException $x) { return self::log($pdo, $role, $id, $step, $name, '', 'failed', $x->getMessage()); }
        return '';
    }

    /**
     * The Translator's words for a Resolution brief, from what we hold (no AI): only fields still empty, never a person's.
     * Why it needs you, from its boundary (0179 rule 3) and the decisions attached; the cost, when it has none named.
     */
    public static function briefDraft(PDO $pdo, array $t): array
    {
        $b = GaugeTasks::brief($pdo, (int) $t['id']) ?? []; $out = [];
        $bd = (string) ($b['boundary'] ?? '');
        $adr = array_values(array_filter(GaugeTasks::adrs($pdo, (int) $t['id']), fn ($a) => $a['adr'] !== 'none'));
        if (trim((string) ($b['why_you'] ?? '')) === '' && ($bd !== '' || $adr)) {
            $all = $adr ? GaugeTasks::decisions() : [];
            $out['why_you'] = trim(($bd !== '' ? 'It crosses a Tier 3 line only the Founder decides (decisions/0179 rule 3): ' . lcfirst(GaugeTasks::BOUNDARIES[$bd] ?? $bd) . '.' : '')
                . ($adr ? ' It rests on ' . implode('; ', array_map(fn ($a) => $a['adr'] . ' (' . ($all[$a['adr']]['title'] ?? 'not in the list') . ')', $adr)) . '.' : ''));
        }
        return $out;
    }

    /** A Building ticket's build brief, in plain words from its own record: what, why, source, scope, decisions, steps. */
    public static function buildBrief(PDO $pdo, array $t): string
    {
        $id = (int) $t['id']; $all = GaugeTasks::decisions(); $L = [];
        $L[] = 'Task #' . $id . ': ' . $t['what'];
        $L[] = 'Why: ' . $t['why'] . ((string) $t['source'] !== '' ? ' (source: ' . $t['source'] . ')' : '');
        foreach (['goal' => 'Goal', 'in_scope' => 'In scope', 'out_scope' => 'Out of scope', 'done_when' => 'Done when'] as $k => $lb) if (trim((string) ($t[$k] ?? '')) !== '') $L[] = $lb . ': ' . trim((string) $t[$k]);
        $ad = GaugeTasks::adrs($pdo, $id);
        if ($ad) $L[] = 'Decisions: ' . implode('; ', array_map(fn ($a) => $a['adr'] === 'none' ? 'none applies' : $a['adr'] . ' ' . ($all[$a['adr']]['title'] ?? ''), $ad));
        foreach (GaugeTasks::steps($pdo, $id) as $s) $L[] = 'Step ' . $s['pos'] . ': ' . $s['do_what'] . ($s['confirm'] !== '' ? ' (confirmed when: ' . $s['confirm'] . ')' : '');
        return mb_substr(implode("\n", $L), 0, 4000);
    }

    private static function log(PDO $pdo, array $role, int $task, string $step, string $seen, string $did, string $state, string $why = '', ?array $proposal = null, string $undoHow = '', ?array $undo = null, string $touches = ''): string
    {
        // Undo on every agent action (decisions/0179's "What comes next"; Piece 3, P3-4): a step that did something without its
        // Undo isn't recorded as done; it's refused, so it shows, and a step that can't be undone only ever proposes
        if ($state === 'done' && (trim($undoHow) === '' || $undo === null)) { $state = 'refused'; $why = 'It did "' . mb_substr($did, 0, 120) . '" with no Undo: a step that can\'t be undone only proposes (Piece 3).'; }
        $pdo->prepare('INSERT INTO team_runs (at, role, task_id, step, seen, did, state, why, proposal, undo_how, undo_data, touches) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([(string) $role['slug'], $task, $step, mb_substr($seen, 0, 400), mb_substr($did, 0, 400), $state, mb_substr($why, 0, 400), $proposal !== null ? json_encode($proposal, JSON_UNESCAPED_UNICODE) : null,
                mb_substr($undoHow, 0, 400), $undo !== null ? json_encode($undo) : null, mb_substr($touches, 0, 400)]);
        $rid = (int) $pdo->lastInsertId();
        $line = TeamRoster::actor($role) . ' · task #' . $task . ' · ' . ($state === 'done' || $state === 'proposed' ? $did : $state . ': ' . $why);
        ControlLog::add($pdo, 'control', '', 'Task #' . $task . ' run #' . $rid . ' (' . $step . '): ' . ($state === 'done' || $state === 'proposed' ? $did : $state . ', ' . $why), TeamRoster::actor($role));
        return $line;
    }

    /** A ticket's runs, newest first. */
    public static function runs(PDO $pdo, int $task, int $limit = 10): array
    {
        try { $st = $pdo->prepare('SELECT * FROM team_runs WHERE task_id = ? ORDER BY id DESC LIMIT ' . (int) $limit); $st->execute([$task]); return $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) { return []; }
    }

    public static function get(PDO $pdo, int $id): ?array
    {
        try { $st = $pdo->prepare('SELECT * FROM team_runs WHERE id = ?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $x) { return null; }
    }

    /** Undo a run: rolled back as if never done (CLAUDE.md "Edit or Undo"), logged; a second Undo is refused. */
    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r) throw new RuntimeException('That run isn\'t there.');
        if ($r['undone_at'] !== null) throw new RuntimeException('Already undone, by ' . $r['undone_by'] . '.');
        if ($r['state'] !== 'done') throw new RuntimeException($r['state'] === 'proposed' ? 'A proposal changes nothing: Accept or Dismiss it.' : 'It did nothing, so there\'s nothing to undo.');
        $u = json_decode((string) $r['undo_data'], true) ?: []; $tid = (int) $r['task_id'];
        if ($r['step'] === 'gate') { GaugeTasks::move($pdo, $tid, (string) $u['to'], $who, 'Undo of run #' . $rid, $tz); if (array_key_exists('owner', $u)) $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = NULL WHERE id = ?')->execute([(string) $u['owner'], $tid]); }
        elseif ($r['step'] === 'handoff') {
            $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = NULL, owner = ?, owner_at = ? WHERE id = ?')->execute([(string) ($u['owner'] ?? ''), ($u['owner'] ?? '') !== '' ? gmdate('Y-m-d H:i:s') : null, $tid]);
            ControlLog::add($pdo, 'control', '', 'Task #' . $tid . ' build brief taken back (Undo of run #' . $rid . ')', $who, '', $tz);
        } elseif ($r['step'] === 'audit') GaugeTasks::withdraw($pdo, (int) $u['withdraw'], 'Undo of run #' . $rid, $who, $tz);
        else throw new RuntimeException('This kind of run has no Undo.');
        $pdo->prepare('UPDATE team_runs SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ? AND undone_at IS NULL')->execute([mb_substr($who, 0, 80), $rid]);
        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';
    }

    /** An open (unsettled) proposal of this step on this ticket? */
    public static function openProposal(PDO $pdo, int $task, string $step): bool
    {
        try { $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = ? AND state = 'proposed' AND settled_at IS NULL LIMIT 1"); $st->execute([$task, $step]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return false; }
    }

    /** Open proposals on each ticket (for the queue's rows and filter): task id => count. */
    public static function openSuggestions(PDO $pdo): array
    {
        $out = []; try { foreach ($pdo->query("SELECT task_id, COUNT(*) n FROM team_runs WHERE state = 'proposed' AND settled_at IS NULL GROUP BY task_id")->fetchAll(PDO::FETCH_ASSOC) as $x) $out[(int) $x['task_id']] = (int) $x['n']; } catch (Throwable $x) {}
        return $out;
    }

    /** Accept a proposal: a move made as the person who accepts (through its gate), or a brief's parts applied as their Edit (History kept, only where still empty). */
    public static function accept(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r || $r['state'] !== 'proposed') throw new RuntimeException('That isn\'t a proposal.');
        if ($r['settled_at'] !== null) throw new RuntimeException('It\'s already ' . ($r['undone_at'] !== null ? 'dismissed' : 'accepted') . '.');
        $p = json_decode((string) $r['proposal'], true) ?: [];
        if ($r['step'] === 'gate') {
            $msg = GaugeTasks::move($pdo, (int) $r['task_id'], (string) ($p['move'] ?? 'building'), $who, 'accepting the Project Manager\'s proposal (run #' . $rid . ')', $tz);
            $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
            return 'Accepted: ' . $msg;
        } $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = [];
        foreach ($p as $k => $v) { if (trim((string) ($b[$k] ?? '')) !== '') { $kept[] = strtolower(GaugeTasks::BRIEF[$k][0]); continue; } GaugeTasks::briefEdit($pdo, (int) $r['task_id'], $k, (string) $v, $who . ' (accepting ' . TeamRoster::actor(['name' => self::roleName($pdo, (string) $r['role'])]) . ')', $tz); $n++; }
        $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
        return 'Accepted: ' . $n . ' part' . ($n === 1 ? '' : 's') . ' of the brief filled, kept in its History.' . ($kept ? ' Left as a person wrote it: ' . implode(', ', $kept) . '.' : '');
    }

    /** Dismiss a proposal: nothing changes; it's kept, marked dismissed. */
    public static function dismiss(PDO $pdo, int $rid, string $who): string
    {
        $r = self::get($pdo, $rid); if (!$r || $r['state'] !== 'proposed') throw new RuntimeException('That isn\'t a proposal.');
        if ($r['settled_at'] !== null) throw new RuntimeException('It\'s already settled.');
        $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ?, undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), mb_substr($who, 0, 80), $rid]);
        return 'Dismissed: nothing changed.';
    }

    private static function roleName(PDO $pdo, string $slug): string { $r = TeamRoster::bySlug($pdo, $slug); return $r ? (string) $r['name'] : $slug; }
}
