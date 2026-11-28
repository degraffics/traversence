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
