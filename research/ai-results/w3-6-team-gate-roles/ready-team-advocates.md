The reconcile file has no content: all 18 pass sections are empty. Nothing below comes from it. Everything is drawn from decisions/0180, jason-spec §4–§7, team-vetting-2026-11-27 and the three attached live files.

Two choices shape this draft:

- **No new runner step.** The advocates get no entry in `TeamRunner::STEPS`. Adding one would change `ruleData()` and the fingerprint. That would clear the Founder's one-time tree approval and drop agents to Planning. Their intake runs as code, the same way `TeamRoster::LANE_ROUTES` already stays out of the fingerprint.
- **No reasoning AI.** The advocates' access order is code only. API and session stay closed. Opening them would change a role's permissions, which is a Tier 5 Founder ruling (0180 §2).

=== FILE: files/app/lib/TeamAdvocates.php ===
<?php
// app/lib/TeamAdvocates.php — the test-account advocates (A1–A12) as decisions/0180 §5 sets them, and their experience tickets
// (0180's rollout, R4). DRAFT: not run, not tested.
//
// What an advocate is (0180 §5; jason-spec §11 Department 4; decisions/0145 §3):
//   - A test account's view, beside the layers. It does two jobs:
//       1. QA verification: it is one of the before-and-after check's views (0179 rule 2, Piece 3: beforeafter.mjs).
//       2. Experience tickets: a persona's friction or UX finding opens a Work ticket routed to the middle manager whose lane
//          owns that screen (TeamRoster::laneOf). That manager may propose a Planning initiative from it.
//   - It never builds, never moves a ticket, never acts through its test account, never sends anything, never pays (0180 §7;
//     0145 §3). TeamRoster::can() already refuses every advocate; nothing here changes that.
//   - So the ticket is opened by code (the before-and-after check's record, taken in here), named "as" the advocate.
//     It writes no team_runs row: Implementation's evidence ("runs by an advocate or the Founder: must be 0") stays 0.
//
// AI access, in order (0180's note to §6, "the routing rule, cheapest first"):
//   1. code ($0): yes. Every step here is fixed form.
//   2. API: closed. The agents' own $40 counter isn't live ("nothing is spent from (3) until its counter is live").
//      An advocate is "views only" in any case.
//   3. session: closed. An advocate doesn't reason; its manager does, in that lane's session.
//   Opening 2 or 3 for an advocate would change a role's permissions: a Founder ruling, Tier 5 (0180 §2).
//
// Not in TeamRunner::ruleData(): this is routing and intake, not a rule of the tree. The tree's fingerprint, and so the
// Founder's one-time approval, stand (0180's note "It is one time only").
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';
require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';
require_once __DIR__ . '/TeamRunner.php';

final class TeamAdvocates
{
    /**
     * The charter (0180 §6's four groundings). It is shown for reference only. A role's mandate and rules on the Team page are a
     * person's words, so this file never writes them (TeamRoster::edit, History kept).
     */
    public const CHARTER = [
        'job' => 'A test account\'s view, beside the layers: checks releases as that persona (the before-and-after check) and reports the friction it meets as experience tickets for the lane that owns the screen.',
        'follows' => ['decisions/0180 §5, §7', 'decisions/0179 rule 2', 'decisions/0145 §3', 'decisions/0165 §3', 'decisions/0066', 'decisions/0164'],
        'knows' => 'Its own persona\'s screens at phone (390px) and desktop sizes; the before-and-after check\'s rules; 0058 §11\'s 390px rules.',
        'hands_to' => 'The middle manager whose lane owns the screen (TeamRoster::laneOf). Never an executive or the Founder directly: territory brings the Tier 4 review (0180 §4), and the manager or a person decides on a Founder ruling.',
        'never' => ['builds or moves a ticket', 'acts through its test account', 'sends mail or messages', 'pays, even in the sandbox, on its own', 'calls paid AI or an outside service', 'puts a held 0066 name in a ticket'],
    ];
    /** The ticket types it may open: Work only, at Planning, unowned (the lane's manager takes it up). Never Audit or Resolution. */
    public const TICKETS = ['work'];
    /** AI access in order (0180's note to §6): [allowed, why]. The first allowed is used. */
    public const AI_ACCESS = [
        'code' => [true, 'Fixed-form intake and ticket words, at $0.'],
        'api' => [false, 'Closed: the agents\' own $40 API counter isn\'t live (0180, "Separate each"), and an advocate is a view only.'],
        'session' => [false, 'Closed: an advocate doesn\'t reason. The lane\'s manager does, in its session (0180 §6).'],
    ];
    /** What it found: key => [label, meaning]. */
    public const KINDS = ['friction' => ['friction', 'something stops or slows the persona'], 'ux' => ['a UX problem', 'something unclear, cluttered or with no one clear next action']];
    public const SIZES = ['390' => 'phone, 390px', 'desktop' => 'desktop'];
    /** Experience tickets sit on their own area of the Work queue. */
    public const GAUGE = 'area:experience';
    /** Two looks, a day apart, before a ticket (jason-spec §9.2: "distinguish real drift or faults from transient noise"). */
    public const SECOND_LOOK_HOURS = 20;
    /** A first sighting older than this starts again (it wasn't there on the runs between). */
    public const FORGET_HOURS = 72;
    /** At most this many new tickets a run (jason-spec §3: quiet by default; no flood for the managers). */
    public const MAX_NEW_PER_RUN = 5;
    /** A ticket withdrawn or done is not reopened for this many days (0180 §7: "No duplicates"). */
    public const QUIET_DAYS = 7;

    /** The AI access it uses now: the first allowed in AI_ACCESS's order ('code'). */
    public static function access(): string
    {
        foreach (self::AI_ACCESS as $k => [$ok]) if ($ok) return $k;
        return 'code';
    }

    /** Take out what must never reach a ticket: tags, email addresses, phone-like numbers (0145 §3; 0164). */
    public static function scrub(string $s): string
    {
        $s = strip_tags($s);
        $s = preg_replace('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', '[an email, removed]', $s) ?? '';
        $s = preg_replace('/\+?\d[\d\s().-]{6,}\d/', '[a number, removed]', $s) ?? '';
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        return mb_substr($s, 0, 240);
    }

    /**
     * One finding from the before-and-after check's record, checked: [row, ''] or [null, why not].
     * Expected shape (a contract this draft proposes; the producer isn't attached): {advocate: roster slug, page: '/path',
     * kind: friction|ux, size: 390|desktop, what: words}.
     */
    public static function normalize(array $f): array
    {
        $adv = strtolower(trim((string) ($f['advocate'] ?? '')));
        if (!preg_match('/^[a-z0-9-]{1,60}$/', $adv)) return [null, 'no advocate named'];
        $p = parse_url(trim((string) ($f['page'] ?? '')), PHP_URL_PATH); $page = is_string($p) ? $p : '';
        if ($page === '' || !preg_match('#^/[\w./-]{0,199}$#', $page) || str_contains($page, '..')) return [null, 'no page, or not a page path'];
        $kind = (string) ($f['kind'] ?? ''); if (!isset(self::KINDS[$kind])) return [null, 'its kind is friction or ux'];
        $size = (string) ($f['size'] ?? 'desktop'); if (!isset(self::SIZES[$size])) return [null, 'its size is 390 or desktop'];
        $what = self::scrub((string) ($f['what'] ?? '')); if ($what === '') return [null, 'it says nothing'];
        return [['advocate' => $adv, 'page' => $page, 'kind' => $kind, 'size' => $size, 'what' => $what], ''];
    }

    /** One finding's key: the same persona, page, kind, size and words are the same finding (fits team_settings.k, 40). */
    public static function key(array $row): string
    {
        return 'exp:' . substr(sha1(implode('|', [$row['advocate'], $row['page'], $row['kind'], $row['size'], mb_strtolower($row['what'])])), 0, 32);
    }

    /**
     * Where it goes and how high: [lane, tier, territory, flag]. Territory comes from what it touches (TeamRunner::tier, 0180 §4).
     * The lane comes from TeamRoster::laneOf (R2's routes).
     */
    public static function route(array $row): array
    {
        $targets = TeamRunner::targetsOf($row['page']);
        [$tier, $territory, $flag] = TeamRunner::tier($row['what'], $targets);
        return [TeamRoster::laneOf($targets, $row['what']), $tier, $territory, $flag];
    }

    /**
     * The ticket's words, fixed form (the "prompt" this role has: no model reads it). [what, why]. On a 0066 page the finding's
     * own words stay out of the ticket.
     */
    public static function words(array $row, string $name, string $territory, string $lane): array
    {
        $detail = $territory === 'nations' ? 'details kept out of the ticket (decisions/0066): see the before-and-after check\'s own record' : $row['what'];
        $what = 'Experience: ' . $name . ' found ' . self::KINDS[$row['kind']][0] . ' on ' . $row['page'] . ' (' . self::SIZES[$row['size']] . '): ' . $detail;
        $why = 'Seen on two looks a day apart by the before-and-after check as ' . $name . ', a test account\'s view (decisions/0180 §5). For '
            . (TeamRoster::LANE_NAMES[$lane] ?? $lane) . ', whose lane owns this screen: it may plan a fix from it. The advocate never builds or moves it.'
            . ($territory !== '' ? ' It touches ' . lcfirst(GaugeTasks::BOUNDARIES[$territory] ?? $territory) . ': Tier 4 review before live (0180 §4).' : '');
        return [mb_substr($what, 0, 400), mb_substr($why, 0, 400)];
    }

    /** The name the check writes under: the check, as the advocate (never the advocate acting). */
    public static function actor(array $adv): string { return 'the before-and-after check · as ' . $adv['name']; }

    /**
     * Take in the advocates' findings (gauges.json "experience", read through ControlGauges::config, as the fence's refusals are).
     * Staging only; called from TeamRunner::tick inside its lock. Returns a line for the job's log, or '' when nothing changed.
     */
    public static function intake(PDO $pdo): string
    {
        require_once __DIR__ . '/Staging.php';
        if (!Staging::here()) return '';
        try { require_once __DIR__ . '/ControlGauges.php'; $rs = (array) (ControlGauges::config($pdo)['experience'] ?? []); } catch (Throwable $x) { return ''; }
        // data_reason: first sightings no later run saw again are forgotten (their own key prefix; the fence's 'front:' keys untouched)
        try { $pdo->prepare("DELETE FROM team_settings WHERE k LIKE 'exp:%' AND at < UTC_TIMESTAMP() - INTERVAL " . (int) self::FORGET_HOURS . ' HOUR')->execute(); } catch (Throwable $x) {}
        if (!$rs) return '';
        $n = ['opened' => 0, 'waiting' => 0, 'kept' => 0, 'refused' => 0];
        foreach ($rs as $f) {
            if ($n['opened'] >= self::MAX_NEW_PER_RUN) break;
            [$row] = self::normalize(is_array($f) ? $f : []);
            if (!$row) { $n['refused']++; continue; }
            $adv = TeamRoster::bySlug($pdo, $row['advocate']);
            if (!$adv || $adv['kind'] !== 'advocate' || $adv['status'] !== 'active') { $n['refused']++; continue; }   // only an active advocate's view
            $k = self::key($row);
            if (self::known($pdo, $k)) { $n['kept']++; continue; }
            $seen = self::firstSeen($pdo, $k);
            if ($seen === null) { self::markSeen($pdo, $k); $n['waiting']++; continue; }
            if (time() - $seen < self::SECOND_LOOK_HOURS * 3600) { $n['waiting']++; continue; }
            self::open($pdo, $row, $adv, $k);
            try { $pdo->prepare('DELETE FROM team_settings WHERE k = ?')->execute([$k]); } catch (Throwable $x) {}   // data_reason: its ticket now holds the key
            $n['opened']++;
        }
        if (array_sum($n) === 0) return '';
        return 'Advocates: ' . $n['opened'] . ' experience ticket' . ($n['opened'] === 1 ? '' : 's') . ' opened · ' . $n['waiting'] . ' waiting for a second look · '
            . $n['kept'] . ' already open or closed this week · ' . $n['refused'] . ' refused';
    }

    /** Open the Work ticket at Planning, unowned, in its lane. Withdraw (a person's press) takes it back. Returns its id. */
    private static function open(PDO $pdo, array $row, array $adv, string $k): int
    {
        [$lane, $tier, $territory, $flag] = self::route($row);
        [$what, $why] = self::words($row, (string) $adv['name'], $territory, $lane);
        $who = self::actor($adv);
        $id = GaugeTasks::add($pdo, self::GAUGE, $what, $why, 'experience · ' . $row['advocate'] . ' · ' . $row['page'] . ' · ' . $row['size'], $who, 0, '', 'work');
        $pdo->prepare('UPDATE control_gauge_tasks SET audit_key = ?, tier = ? WHERE id = ?')->execute([$k, $tier, $id]);
        try { $pdo->prepare("UPDATE control_gauge_tasks SET lane = ? WHERE id = ? AND (lane IS NULL OR lane = '')")->execute([$lane, $id]); } catch (Throwable $x) {}   // R2's column; without it, TeamRoster::lane() finds the lane from its words
        if ($flag !== '') GaugeTasks::flag($pdo, $id, $flag);
        ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' experience ticket for ' . TeamRoster::laneName($pdo, $lane) . ' (Tier ' . $tier . ($territory !== '' ? ', ' . $territory : '') . '); Withdraw takes it back', $who);
        return $id;
    }

    /** Open, or closed in the last QUIET_DAYS (done or withdrawn): not opened again. */
    private static function known(PDO $pdo, string $k): bool
    {
        try {
            $st = $pdo->prepare("SELECT 1 FROM control_gauge_tasks WHERE ticket = 'work' AND audit_key = ? AND ((undone_at IS NULL AND cleared_at IS NULL) OR COALESCE(cleared_at, undone_at) > UTC_TIMESTAMP() - INTERVAL " . (int) self::QUIET_DAYS . ' DAY) LIMIT 1');
            $st->execute([$k]); return (bool) $st->fetchColumn();
        } catch (Throwable $x) { return true; }   // can't read: open nothing
    }

    private static function firstSeen(PDO $pdo, string $k): ?int
    {
        try { $st = $pdo->prepare('SELECT at FROM team_settings WHERE k = ?'); $st->execute([$k]); $at = $st->fetchColumn(); return $at ? (int) strtotime($at . ' UTC') : null; } catch (Throwable $x) { return null; }
    }

    private static function markSeen(PDO $pdo, string $k): void
    {
        try { $pdo->prepare("INSERT INTO team_settings (k, v, who, at, tz) VALUES (?, 'seen', 'the before-and-after check', UTC_TIMESTAMP(), '') ON DUPLICATE KEY UPDATE k = k")->execute([$k]); } catch (Throwable $x) {}
    }
}
=== END FILE ===

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
//   - The advocates' experience findings (decisions/0180 §5, R4) are taken in at the start of each run (TeamAdvocates::intake):
//     code only, never a step and never in ruleData, so the decision tree's approval stands.
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
        return ['steps' => $steps, 'step_at' => self::STEP_AT, 'ceilings' => array_keys(self::CEILINGS), 'territories' => self::TERRITORIES, 'tier5_words' => self::TIER5_WORDS, 'tier1_words' => self::TIER1, 'step_cost' => self::STEP_COST_MICROS];
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
     * The tier rules (decisions/0180 §1