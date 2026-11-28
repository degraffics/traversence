=== FILE: files/app/lib/TeamRunner.php ===
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
//   - Never a Founder ruling (Tier 5, decisions/0180 §1): on a Resolution ticket it may only propose the brief's words; only
//     the Founder answers.
//   - Its permissions only through Access::canAs, for the role's level and department (TeamRoster::can); never test accounts.
//   - Internal first and free: every step here reads our own records and writes through the owning library. No step calls an
//     outside service or paid AI (Jason's Q4 B), so none asks StagingGate::hold() or AiClient::allow(); a paid step, if ever
//     added, holds alone at the cap and raises one brief per ticket (Jason, 2026-11-25; piece2-team.md §5.1).
//   - A person's words are never overwritten: where a step would write words a person may have written, it proposes them,
//     and a person's Accept applies them as an Edit with History.
//   - Every run has Undo (an action rolled back as if never done), or says why it can't; a second Undo is refused.
//   - Each run notes what it touched (touches), for piece 3's before-and-after check.
//   - The IT Manager's triage (decisions/0180 §5, appendix row 11; DRAFT): code first, then the agents' API, then a session
//     (AI_ORDER). This runner itself only ever takes the code route: it records which route the ticket's reasoning needs,
//     and the lean question for it, and never makes the call.
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
        // the IT Manager (decisions/0180 §5, appendix row 11; Layer 3 → CTO and CIO): triages a Work ticket its lane owns (TeamRoster::LANE_ROUTES)
        'triage' => ['it-manager', 'Triages a Work ticket in the IT Manager\'s lane: records its lane, the territory it touches, how far it reaches and the route its reasoning takes', 'team.triage', ['min' => 'manager', 'depts' => ['eng']]],
    ];
    /**
     * The ceiling (Jason, 2026-11-25: "limit agents and build to only the initial work que stage until I fully vet the builds";
     * decisions/0179's note on how the Team's capacity grows): the furthest stage agents work in. key => [name, what agents do].
     */
    public const CEILINGS = [
        'planning' => ['Planning', 'Agents only propose: the Project Manager says a ticket is ready for Building, the Translator suggests a brief\'s words, the audit opens tickets. A person accepts or dismisses each.'],
        // Jason, 2026-11-25 (through the session manager): the Project Manager acts at Building and takes audit-raised tickets
        // itself; at Implementation the brief goes straight to a build session; at Confirmation, Tier 1 and 2 publish alone (decisions/0180 §1)
        'building' => ['Building', 'The Project Manager moves a ticket to Building once its gate passes, and takes the tickets the audit raised itself; a ticket a person entered still waits for Give it to the Team. The System Architect writes build briefs for the session manager.'],
        'implementation' => ['Implementation', 'As Building, and the System Architect\'s build brief goes straight to a build session, through the report (build-requests.json).'],
        'confirmation' => ['Confirmation and safe publish', 'Agents act as at Implementation, and a release that touches no high-risk territory publishes by itself once staging\'s checks and the advocates pass it (Tier 1 and Tier 2, rule 2); one that touches a territory comes to the Founder as a Decision Brief.'],
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
        // the IT Manager's triage: at Planning a proposal a person accepts; from Building it records the lane itself, with Undo
        'triage' => ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
    ];
    /**
     * The high-risk territories (decisions/0180 §4, which kept 0179's note: "detected by what a change or finding touches"), each
     * from the code: its libraries, tables, page paths, jobs and services. Words are only a secondary flag (TIER5_WORDS). Until
     * R3 brings the Tier 4 review (0180's rollout), a change that touches one waits for a Founder ruling (Tier 5), as before.
     */
    public const TERRITORIES = [
        'money' => ['libs' => ['QuickBooksPay', 'Billing', 'Orders', 'ClientOrders', 'Wallet', 'MarketPay', 'AccountDomains'],
            'tables' => ['orders', 'order_lines', 'order_ledger', 'order_events', 'order_refund_requests', 'order_payment_cancels', 'subscriptions', 'subscription_charges', 'wallet_cards', 'account_domains', 'domain_cart', 'market_orders', 'market_pay_events'],
            'paths' => ['/order.php', '/user/orders.php', '/market/cart.php', '/market/order.php', '/qr/cart.php', '/shop/domains.php', '/user/domains/', '/business/pay-connect.php', '/api/market-pay.php', '/api/market-webhook.php', '/api/qb-webhook.php', '/api/client-order.php', '/api/domains.php', '/api/admin/client-order.php', '/admin/order.php', '/admin/client-order.php', '/admin/quickbooks.php', '/admin/backoffice/orders', '/admin/backoffice/production'],
            'jobs' => ['billing_tick', 'pay_wait_tick', 'qb_orders_tick', 'got_it_tick', 'market_tick'], 'services' => ['quickbooks', 'stripe', 'square']],
        // a migration is high-risk (data) by what it does, not by being one (Jason, 2026-11-25, through the session manager: CREATE
        // TABLE IF NOT EXISTS or a guarded ADD COLUMN/INDEX alone is 0179's safe additive; UPDATE, DELETE, DROP, MODIFY or
        // RENAME stays high-risk (0180 §4, item 2); TRUNCATE deletes and CHANGE renames or modifies a column). ReleaseKind::writes() reads them.
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
    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0, 'triage' => 0];

    /**
     * The order a role's reasoning takes (decisions/0180 §6 and its note "the routing rule, cheapest first"; jason-spec §4.1
     * "Internal Systems & Deterministic Logic First", §4.5 "No Silent Guesswork"): code first, then the API for a focused call
     * with a small, known context, then a Claude session for multi-step reasoning. key => [name, when].
     */
    public const AI_ORDER = [
        'code' => ['Code', '$0: our own records and rules settle it (0180 §6, internal first)'],
        'api' => ['The API', 'A focused call with a small, known context, on the agents\' own $40 a month (0180\'s correction "Separate each"), through AiClient::allow(); only once that counter is live'],
        'session' => ['A Claude session', 'Multi-step reasoning such as a Prior Ruling Check (0180 §3, §6), in a session on the Founder\'s Claude Max plan, through git, in minutes'],
    ];
    /**
     * Is the agents' own API counter live (0180's correction "Separate each": "nothing is spent from (3) until its counter is
     * live, and until then agents stay on sessions")? false until the AI items release builds it; flipping it is a rule change
     * (it's in ruleData), so the decision tree is approved again.
     */
    public const AGENT_API_LIVE = false;
    /** The Work stages the IT Manager triages at (a ticket past Building already has its brief and release). */
    public const TRIAGE_STAGES = ['planning', 'building'];

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
        return ['steps' => $steps, 'step_at' => self::STEP_AT, 'ceilings' => array_keys(self::CEILINGS), 'territories' => self::TERRITORIES, 'tier5_words' => self::TIER5_WORDS, 'tier1_words' => self::TIER1, 'step_cost' => self::STEP_COST_MICROS,
            'ai_order' => array_keys(self::AI_ORDER), 'agent_api' => self::AGENT_API_LIVE];
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
     * The tier rules (decisions/0180 §1, the five tiers; piece2-team.md §6), on a finding's words: a word about a high-risk
     * territory only flags it for a person (Tier 5 comes by what it touches); Tier 1 when it's only the kinds 0179 rule 2 names
     * as low-risk (wording, the FAQ, tests, the phone layout); Tier 2 otherwise. territory => pattern.
     */
    /** The names before decisions/0180 R1, kept as aliases so nothing that still reads them breaks (the zip check keeps earlier
     *  work; removing them is for a later release, with Jason's OK). */
    public const TIER3_AREAS = self::TERRITORIES;
    public const TIER3 = self::TIER5_WORDS;
    public const TIER5_WORDS = [
        'money' => '/\b(orders?|payments?|billing|invoices?|refunds?|charges?|charged|prices?|pricing|quickbooks|stripe|square|payouts?|subscriptions?)\b/i',
        'data' => '/\b(delete|deleted|deletes|drop|dropped|migrations?|purge|purged|truncate|data loss|reshape)\b/i',
        'address' => '/\b(routes?|urls?|redirects?|public address|404)\b/i',
        'nations' => '/(\b0066\b|\bnations?\b|\btribal\b|\bsacred\b|\bburials?\b|archaeolog|landmark visibility|landmarks::)/i',
        'privacy' => '/\b(privacy|private|personal|email address(es)?|phone numbers?|consent|is_test|leak)\b/i',
        // the two territories with no area in the code (0180 §4, items 6 and 7): a finding about them is always a word flag for a person
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
        foreach (self::STEPS as $step => [$slug, , $act, $def]) { if ($step === 'audit' || self::mode($pdo, $step) === 'off') continue; $d = TeamRoster::doer($pdo, $slug, $act, $def); if ($d) $roles[$step] = $d[0]; }   // a step off at this ceiling gets no work; a role on standby's step goes up its line (0180 §5)
        if (!$roles) return null;
        $recent = [];
        try { $st = $pdo->prepare('SELECT DISTINCT task_id FROM team_runs WHERE at > UTC_TIMESTAMP() - INTERVAL ' . self::GAP_MINUTES . ' MINUTE'); $st->execute(); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $recent[(int) $x] = true; } catch (Throwable $x) {}
        $q = array_filter(GaugeTasks::queue($pdo), fn ($t) => !in_array($t['state'], ['done', 'withdrawn'], true) && !isset($recent[(int) $t['id']]) && self::mayWork($t));
        usort($q, fn ($a, $b) => [((int) ($a['priority'] ?? 0)) ?: 4, (int) $a['id']] <=> [((int) ($b['priority'] ?? 0)) ?: 4, (int) $b['id']]);
        foreach ($q as $t) foreach ($roles as $step => $role) if (self::mayWork($t, $role) && self::applies($pdo, $step, $t)) return [$t, $step, $role];
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

    /**
     * Never a ticket a person has: only unowned ones, or ones a Team role has. With a role (decisions/0180 R2, §7 "One owner at
     * a time"): the ticket's single owner is its lock. A role may work it when nobody holds it or it holds it itself; another
     * role's hold lapses after GAP_MINUTES (§7's "at most once an hour"), so a stalled role never keeps a ticket from its lane.
     */
    public static function mayWork(array $t, ?array $role = null): bool
    {
        $o = (string) $t['owner']; if ($o !== '' && !str_starts_with($o, 'system · ')) return false;
        if ($role === null || $o === '' || $o === TeamRoster::actor($role)) return true;
        $at = (string) ($t['owner_at'] ?? ''); return $at === '' || strtotime($at . ' UTC') < time() - self::GAP_MINUTES * 60;
    }

    /** Does this step have work on this ticket? */
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && !self::openProposal($pdo, (int) $t['id'], 'gate')
            && (GaugeTasks::gate($pdo, $t) === [] || (self::mode($pdo, 'gate') === 'act' && self::takesItself($pdo, $t) && GaugeTasks::gate($pdo, $t) === ['someone to have it (Take it)']));
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        if ($step === 'triage') {
            // the IT Manager's: a Work ticket at Planning or Building, with no lane stored yet (no hand-off made), never triaged
            // before (a dismissed or undone triage stands: it isn't proposed again), whose targets route it to the IT lane.
            // No lane column (R2's migration not run): no triage at all.
            if ($tk !== 'work' || !in_array($sg, self::TRIAGE_STAGES, true) || !array_key_exists('lane', $t) || (string) $t['lane'] !== '') return false;
            if (self::triaged($pdo, (int) $t['id'])) return false;
            return TeamRoster::lane($pdo, $t) === 'it-manager';
        }
        return false;
    }

    /** Has the IT Manager already triaged this ticket (a proposal or an act, settled, dismissed or undone)? */
    public static function triaged(PDO $pdo, int $task): bool
    {
        try { $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = 'triage' AND state IN ('done', 'proposed') LIMIT 1"); $st->execute([$task]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return true; }   // can't read: don't triage
    }

    /**
     * [tier, territory, flag] for a finding (0180 §4: detected "by what a change or finding touches"). Its targets come first:
     * a page or file path (by its start), a job, a service, a library or a table named in TERRITORIES makes it Tier 5 (a Founder
     * ruling, until R3's Tier 4 review) in that territory. Words alone never do: a word in TIER5_WORDS leaves it Tier 2 with that
     * territory as its flag ("Words suggest a high-risk territory: a person checks"). Tier 1 only for the low-risk kinds (TIER1)
     * with no flag; anything else Tier 2.
     * Targets: ['path:/order.php', 'job:billing_tick', 'svc:stripe', 'lib:Orders', 'table:orders'].
     */
    public static function tier(string $text, array $targets = []): array
    {
        if ($b = self::touches($targets)) return [5, $b, ''];
        foreach (self::TIER5_WORDS as $b => $re) if (preg_match($re, $text)) return [2, '', $b];
        return [preg_match(self::TIER1, $text) ? 1 : 2, '', ''];
    }

    /** The high-risk territory a finding's targets touch, or ''. */
    public static function touches(array $targets): string
    {
        $kinds = ['path' => 'paths', 'job' => 'jobs', 'svc' => 'services', 'lib' => 'libs', 'table' => 'tables', 'sql' => 'sql'];
        foreach ($targets as $tg) {
            [$k, $v] = array_pad(explode(':', (string) $tg, 2), 2, ''); $v = trim($v); if ($v === '' || !isset($kinds[$k])) continue;
            if ($k === 'path') { $v = (string) (parse_url($v, PHP_URL_PATH) ?? $v); $v = preg_replace('#^(website|sync-storage)/#', '/', $v); }
            foreach (self::TERRITORIES as $b => $a) foreach ($a[$kinds[$k]] ?? [] as $x) {
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

    // ---- the IT Manager (decisions/0180 §5, appendix row 11): its checks, its route and its lean question, all in code ($0) ----

    /** The IT Manager's checks on one ticket, from its own record: its lane, targets, territory, SQL words and reach. */
    public static function itChecks(PDO $pdo, array $t): array
    {
        $text = implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['in_scope'] ?? ''), (string) ($t['build_brief'] ?? '')]);
        return self::itChecksFrom(TeamRoster::ticketTargets($t), $text, self::frontOf($pdo, $t), TeamRoster::lane($pdo, $t));
    }

    /**
     * The same checks from what's already read (no database): the territory by what it touches (0180 §4); SQL that changes
     * data named in its words, upper case only, as a flag a person checks (words never decide, 0180 §4); its reach (frontOf).
     */
    public static function itChecksFrom(array $targets, string $text, array $front, string $lane): array
    {
        $sql = []; foreach (self::TERRITORIES['data']['sql'] as $w) if (preg_match('/\b' . $w . '\b/', $text)) $sql[] = $w;
        return ['lane' => $lane, 'targets' => array_values($targets), 'territory' => self::touches($targets), 'sql' => $sql, 'front' => array_values($front)];
    }

    /**
     * Which route the ticket's reasoning takes, cheapest first (AI_ORDER): code when no territory and no SQL flag (routing is
     * all it needs); else the Prior Ruling Check, which is reasoning (0180 §3): the agents' API once its counter is live, else a
     * Claude session. Never a guess: an unavailable route falls to the next (jason-spec §4.5). [route, why].
     */
    public static function aiRoute(array $c): array
    {
        if (($c['territory'] ?? '') === '' && empty($c['sql'])) return ['code', 'code settles it: it touches no high-risk territory'];
        if (self::AGENT_API_LIVE) return ['api', 'a lean Prior Ruling Check, a focused call on the agents\' own $40'];
        return ['session', 'a Prior Ruling Check: the agents\' API counter isn\'t live yet, so a Claude session (decisions/0180, "Separate each")'];
    }

    /** The triage note in plain words (kept on the run and in the ticket's History), at most 380 characters. */
    public static function triageNote(array $c, string $route, string $why): string
    {
        $L = ['Lane: IT Manager'];
        $b = (string) ($c['territory'] ?? '');
        $L[] = $b !== '' ? 'touches ' . lcfirst(GaugeTasks::BOUNDARIES[$b] ?? $b) . ': its Tier 4 review (CTO and CIO) is recorded before live, and until R3 records Tier 4 it waits for a Founder ruling' : 'touches no high-risk territory by its targets';
        if ($b === 'money') $L[] = 'the hard money line: built and staged only, a real charge, payout or refund takes a person (0180 §4.1)';
        if (!empty($c['sql'])) $L[] = 'its words name ' . implode(', ', (array) $c['sql']) . ': a person checks whether it changes data (words only flag)';
        $L[] = empty($c['front']) ? 'stays in the backend' : 'not shown to stay in the backend (' . (string) $c['front'][0] . '): its brief waits for the Founder while reach is Backend only';
        $L[] = 'reasoning: ' . (self::AI_ORDER[$route][0] ?? $route) . ', ' . $why;
        return mb_substr(implode('; ', $L), 0, 380);
    }

    /**
     * The lean question for the API or a session (0180 §6: "only the change, the areas it touches and the ADR sections it may
     * rely on, never the whole history, and it answers with a citation and yes or no"). No owner and no person's name.
     * $adrs: GaugeTasks::adrs rows.
     */
    public static function itPromptText(array $t, array $c, array $adrs): string
    {
        $L = ['Prior Ruling Check (decisions/0180 §3), lean. Answer yes or no, with the decision and section it rests on.'];
        $L[] = 'Change: task #' . (int) ($t['id'] ?? 0) . ': ' . mb_substr((string) ($t['what'] ?? ''), 0, 300);
        if (trim((string) ($t['goal'] ?? '')) !== '') $L[] = 'Goal: ' . mb_substr(trim((string) $t['goal']), 0, 300);
        $b = (string) ($c['territory'] ?? '');
        $L[] = 'Touches: ' . ($b !== '' ? (GaugeTasks::BOUNDARIES[$b] ?? $b) : 'no territory by its targets') . ($c['targets'] ?? [] ? ' (' . mb_substr(implode(', ', (array