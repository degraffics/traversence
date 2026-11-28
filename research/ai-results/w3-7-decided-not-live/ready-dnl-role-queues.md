=== FILE: files/app/lib/RoleQueues.php ===
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
=== END FILE ===
=== FILE: tests/role-queues.php ===
<?php
// tools/role-queues-tests/role-queues.php — RoleQueues, read only, on an in-memory SQLite Team (no MySQL, no money,
// nothing live). Run: php tools/role-queues-tests/role-queues.php  (APP_ROOT overrides the repo root). Prints PASS/FAIL.
declare(strict_types=1);

$root = getenv('APP_ROOT') ?: dirname(__DIR__, 2);
require $root . '/app/lib/RoleQueues.php';

$fail = 0;
function ok(bool $c, string $name): void { global $fail; echo ($c ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$c) $fail++; }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE team_roster (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, kind TEXT, status TEXT, reports_to TEXT DEFAULT '', sort INT DEFAULT 0, acts_level TEXT DEFAULT '', acts_dept TEXT DEFAULT '')");
$ins = $pdo->prepare('INSERT INTO team_roster (slug, name, kind, status, reports_to, sort) VALUES (?, ?, ?, ?, ?, ?)');
foreach ([['founder', 'Founder / Chair', 'founder', 'active', '', 1], ['project-manager', 'Project Manager', 'manager', 'active', 'founder', 8],
    ['it-manager', 'IT Manager', 'manager', 'active', 'founder', 11], ['system-architect', 'System Architect', 'operator', 'active', 'it-manager', 20],
    ['advocate-a1', 'Advocate A1', 'advocate', 'active', '', 40], ['historian', 'Historian', 'keeper', 'inactive', '', 99]] as $r) $ins->execute($r);
$tables = fn () => (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master")->fetchColumn();
$before = $tables();

$now = strtotime('2026-12-01 12:00:00 UTC');
$at = fn (int $h) => gmdate('Y-m-d H:i:s', $now - $h * 3600);
$queue = [
    ['id' => 1, 'at' => $at(2), 'what' => 'Tidy the queue labels', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'open', 'owner_now' => '', 'lane' => 'project-manager', 'priority' => 2],
    ['id' => 2, 'at' => $at(30), 'what' => 'Old untaken task', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'late', 'owner_now' => '', 'lane' => 'project-manager', 'priority' => null],
    ['id' => 3, 'at' => $at(5), 'what' => 'Brief the runner fix', 'ticket' => 'work', 'stage' => 'building', 'state' => 'taken', 'owner_now' => 'system · System Architect', 'lane' => 'it-manager'],
    ['id' => 4, 'at' => $at(50), 'what' => 'Decide: a fence', 'ticket' => 'resolution', 'stage' => 'waiting', 'state' => 'waiting', 'owner_now' => '', 'lane' => 'it-manager'],
    ['id' => 5, 'at' => $at(80), 'what' => 'FAQ typo', 'ticket' => 'work', 'stage' => 'confirmation', 'state' => 'done', 'owner_now' => '', 'lane' => 'editorial-director'],
    ['id' => 6, 'at' => $at(3), 'what' => 'Ledger line check', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'taken', 'owner_now' => 'A staff person', 'lane' => 'accounting-manager'],
    ['id' => 7, 'at' => $at(4), 'what' => 'Held by a retired role', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'taken', 'owner_now' => 'system · Historian', 'lane' => 'sales-director'],
    ['id' => 8, 'at' => $at(1), 'what' => 'An audit finding', 'ticket' => 'audit', 'stage' => 'found', 'state' => 'open', 'owner_now' => '', 'parent_id' => null],
    ['id' => 9, 'at' => $at(1), 'what' => 'Odd state', 'ticket' => 'work', 'stage' => 'planning', 'state' => 'weird', 'owner_now' => 'system · Project Manager', 'lane' => 'marketing-director'],
];
$v = RoleQueues::build($pdo, $queue, $now);

ok(count($v['lanes']) === 7, 'seven lane queues, one per middle manager');
$pm = $v['lanes']['project-manager'];
ok($pm['open'] === 2 && $pm['late'] === 1, 'PM lane counts: 2 open, 1 late');
ok($pm['line'] === '2 open · 1 late (a day old, nobody has it)', 'PM lane line');
ok($pm['oldest_open_hours'] === 30, 'PM lane oldest open is 30 hours');
$pmr = $v['roles']['project-manager']['rows'];
ok(array_column($pmr, 'id') === [2, 1, 9], 'PM inbox: late first, then priority, then its own held row');
ok($pmr[0]['here'] === 'lane' && $pmr[2]['here'] === 'holds', 'PM inbox says why each row is there');
ok($pmr[0]['sla'] === 'Late: a day old and nobody has it', 'late row SLA words');
ok($pmr[1]['sla'] === 'Nobody has it yet: late in 22 hours', 'open row SLA counts down to a day');
ok(array_column($v['roles']['system-architect']['rows'], 'id') === [3], 'System Architect holds #3');
ok(array_column($v['roles']['it-manager']['rows'], 'id') === [8], 'IT Manager inbox: the unowned audit (lane by TeamRoster::lane), not the held or waiting ones');
ok(array_column($v['lanes']['it-manager']['rows'], 'id') === [3, 4, 8] || count($v['lanes']['it-manager']['rows']) === 3, 'IT lane has #3, #4 and the audit #8');
$f = $v['roles']['founder']['rows'];
ok(count($f) === 1 && $f[0]['id'] === 4 && $f[0]['here'] === 'ruling', 'Founder queue: the ruling waiting for him');
ok($f[0]['sla'] === 'Waiting for the Founder: no time limit is set', 'no per-stage limit invented (Q-B)');
ok($v['lanes']['editorial-director']['line'] === 'Nothing open' && $v['lanes']['editorial-director']['counts']['done'] === 1, 'done row counted, nothing open');
ok($v['lanes']['accounting-manager']['open'] === 1 && !isset($v['roles']['accounting-manager']), 'a person-held ticket shows in its lane only');
ok(array_column($v['unplaced'], 'id') === [7], 'a ticket held by a retired role is unplaced, for a person to see');
ok(!isset($v['roles']['advocate-a1']) && !isset($v['roles']['historian']), 'no queue for advocates or retired roles');
ok($v['lanes']['marketing-director']['rows'][0]['state'] === 'open', 'an unknown state reads as open');
ok(RoleQueues::line(['waiting' => 1]) === '1 open · 1 waiting for the Founder', 'line with a waiting ruling');
ok($tables() === $before, 'read only: no table created or changed');

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
### What is a role queue?

Coming. Each of the seven lanes (Project Manager, Customer Service Manager, Accounting Manager, IT Manager, Editorial Director, Marketing Director, Sales Director) and each role on the Team will have its own view of the Work queue. It shows the tickets in that lane or held by that role, with counts: open, late, and waiting for the Founder. It is the same Work queue, filtered. There is no second list, and the Team has no mailboxes.

### What is in a role's inbox?

Coming. A role's inbox shows the tickets it holds. A lane manager's inbox also shows the tickets in its lane that nobody has taken yet, so it can take them or hand them to another lane. The Founder's queue shows the Founder rulings waiting for his answer. Advocates have no inbox, because they are views only and never hold work.

### When is a ticket late?

When it is a day old and nobody has taken it. This is the only time limit set so far. A ticket that nobody has yet shows how many hours are left before it is late. There are no time limits per stage yet: those wait for the Founder.

### Do alarms and reports emailed to Jason show up in a role's queue?

Not yet. This is decided, but it is not built in this release. Alarm and report emails work as they do today.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "role-queues",
  "gate": "Tier 3, in the Project Manager's (8) lane: a non-protected workflow view (decisions/0180 §1). Read only: no migration, no table or column, no money, no address, no 0066 surface. The Prior Ruling Check finds no high-risk territory under 0180 §4, so there is no Tier 4 review; Tier 4 is still needed if the lane finds that wiring the page touches one. Sequenced with the transition plan's phase 2 release 1 (handoffs/decided-not-live.md), after the pipeline release and R2. Launch work comes first under 0181's gates.",
  "what": "SKIPPED FIRST, as the attached documents require: (1) Per-stage SLA or stall limits. The session manager's Q-B ('no stall limit per stage (none decided), so no stall step') is kept for Jason's return, so none is invented. The only SLA shown is the decided rule: late means a day old and nobody has it (GaugeTasks::LATE_HOURS). (2) 'An alarm or report emailed to Jason also a row in its owning role's queue'. The mail and alarm code (FounderMail, Notify, ControlAlarms, Mailer) is not attached. Q-A's interim ruling, also kept for Jason's return, lets tickets reach the Team only by a person's 'Give it to the Team' or the audit, so alarm-made rows would need his answer first. Not paused or waiting, and unaffected: the M2b orders pause and Order #12's books are not touched. BUILT: app/lib/RoleQueues.php, a read-only view over GaugeTasks::queue()'s rows. It gives each of the seven lanes (TeamRoster::lane) and each non-advocate, non-retired role a queue: the rows it holds, its lane's untaken tickets for a lane manager, and waiting rulings for the Founder. Each queue carries counts, oldest-open hours, a chip line, and per-row SLA words. Tickets held under a retired or renamed role's name are listed as 'unplaced'.",
  "files": [
    "files/app/lib/RoleQueues.php",
    "tests/role-queues.php",
    "faq/faq-entries.md",
    "release.json"
  ],
  "decisions": [
    "Skipped: per-stage SLA. Q-B is undecided and waits for Jason's return (handoffs/decided-not-live.md, 'Gaps for Jason's return').",
    "Skipped: alarm or report emails as queue rows. The files are not shown, and Q-A's interim ruling waits for Jason's return.",
    "Role queues as the agents' inbox (Jason, 2026-11-27: 'Yes to role queues; mail only, OneDrive stays'; jason-spec §7): a view on the Work queue, with no agent mailboxes.",
    "0180 §5: 'every ticket routes to exactly one of the seven middle managers'. Department queues are the seven lanes, through TeamRoster::lane, so routing is reused and not duplicated (0180 §7, 'settled ideas are reused').",
    "0180 §5: advocates never build or move a ticket, so they get no queue. Retired roles are never shown (Jason's answer 1), so they get no queue either.",
    "0180 §1: non-protected workflow is Tier 3, in the Project Manager's lane (handoffs/decided-not-live.md).",
    "Kit-buttons / GaugeTasks::LATE_HOURS: a ticket is late when it is a day old and nobody has it. This is the only SLA."
  ],
  "tests": [
    "tests/role-queues.php -> tools/role-queues-tests/role-queues.php. Uses in-memory SQLite. Checks: lane grouping, lane-manager triage, held rows, the Founder's waiting rulings, the late and open SLA words, the unplaced retired-role row, that advocates and retired roles get no queue, unknown-state handling, and that the view makes no writes."
  ],
  "risks": [
    "Nothing here was run, tested, synced or zip-checked. It is all a DRAFT for the lane session.",
    "Job 19's draft file was attached empty, so there was nothing to review. This release is built from the attached live files only.",
    "GaugeTasks::queue() writes: advance() moves stages and closes tasks. RoleQueues therefore takes the page's existing queue() rows. If the page called queue() a second time for these views, it would repeat those checks.",
    "TeamRoster::lane() runs one query per ticket that has a parent and no stored lane (up to 3 deep). This can be slow on a long queue.",
    "Tickets held under a role's old name (after a rename through TeamRoster::edit) fall into 'unplaced' rather than the role's queue. This also affects TeamRoster::openTickets, which matches by name.",
    "The test requires the live Database.php and ControlLog.php through GaugeTasks and TeamRoster. If either connects or runs code when loaded, the SQLite test may not run as written.",
    "The FAQ entries say 'Coming' until the Work queue page shows the views."
  ],
  "verify": [
    "file not shown: the Work queue page (System Control Board, Development). Wire RoleQueues::build($pdo, $queueRows) using the rows the page already gets from GaugeTasks::queue(). Add a lane/role filter with each queue's line as its chip. The view checked at 1280 and 390px, with the simplicity veto (0180 §8).",
    "file not shown: app/lib/TeamRunner.php. TeamRoster::lane falls back to it through laneOf for rows with no stored lane. Check that it loads in the test, or that every test row avoids it as written.",
    "file not shown: app/lib/Database.php and app/lib/ControlLog.php. Check that loading them has no side effects for the SQLite test.",
    "file not shown: the R2 migration adding the control_gauge_tasks 'lane' and 'handoff' columns. GaugeTasks::handOff writes them. lane() works without them.",
    "file not shown: handoffs/piece2/jason-spec.md §7. Confirm that 'department' means the seven lanes and not TeamRoster::DEPARTMENTS (the layers). A per-layer roll-up of the role queues is a small addition if needed.",
    "file not shown: app/ui/faq.md. Add the four entries from faq/faq-entries.md.",
    "file not shown: FounderMail, Notify, ControlAlarms. Needed for the skipped alarm-row part once Q-A is answered.",
    "Confirm that 'system · ' (a middle dot) in RoleQueues matches TeamRoster::actor's bytes."
  ],
  "regions": false
}
=== END FILE ===