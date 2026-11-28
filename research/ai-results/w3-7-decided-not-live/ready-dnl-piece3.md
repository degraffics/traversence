=== FILE: files/app/lib/TeamRunner.php.region-1.php ===
// ANCHOR: replaces `    private static function log(PDO $pdo, array $role, int $task, string $step, string $seen, string $did, string $state, string $why = '', ?array $proposal = null, string $undoHow = '', ?array $undo = null, string $touches = ''): string` through `    }` (the method's closing brace, the line right after `        return $line;`)
    private static function log(PDO $pdo, array $role, int $task, string $step, string $seen, string $did, string $state, string $why = '', ?array $proposal = null, string $undoHow = '', ?array $undo = null, string $touches = ''): string
    {
        // Undo on every agent action (decisions/0179's "What comes next"; Piece 3, P3-4): a step that did something without its
        // Undo isn't recorded as done; it's refused, so it shows, and a step that can't be undone only ever proposes
        if ($state === 'done' && (trim($undoHow) === '' || $undo === null)) { $state = 'refused'; $why = 'It did "' . mb_substr($did, 0, 120) . '" with no Undo: a step that can\'t be undone only proposes (Piece 3).'; }
        // The spending limit per agent counts what's booked here (Piece 3, P3-4). A step that went ahead (done or proposed) books
        // the cost run() checked against its role's limit (STEP_COST_MICROS), so spentThisMonth() reads it. A refused or failed
        // step books nothing. Every step is $0 today (Q4 B), so the month's totals stay $0.
        $cost = in_array($state, ['done', 'proposed'], true) ? max(0, (int) (self::STEP_COST_MICROS[$step] ?? 0)) : 0;
        $pdo->prepare('INSERT INTO team_runs (at, role, task_id, step, seen, did, state, why, proposal, undo_how, undo_data, touches, cost_micros) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([(string) $role['slug'], $task, $step, mb_substr($seen, 0, 400), mb_substr($did, 0, 400), $state, mb_substr($why, 0, 400), $proposal !== null ? json_encode($proposal, JSON_UNESCAPED_UNICODE) : null,
                mb_substr($undoHow, 0, 400), $undo !== null ? json_encode($undo) : null, mb_substr($touches, 0, 400), $cost]);
        $rid = (int) $pdo->lastInsertId();
        $line = TeamRoster::actor($role) . ' · task #' . $task . ' · ' . ($state === 'done' || $state === 'proposed' ? $did : $state . ': ' . $why);
        ControlLog::add($pdo, 'control', '', 'Task #' . $task . ' run #' . $rid . ' (' . $step . '): ' . ($state === 'done' || $state === 'proposed' ? $did : $state . ', ' . $why), TeamRoster::actor($role));
        return $line;
    }
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-2.php ===
// ANCHOR: replaces `    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string` through `    }` (the method's closing brace, the line right after `        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';`)
    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r) throw new RuntimeException('That run isn\'t there.');
        if ($r['undone_at'] !== null) throw new RuntimeException('Already undone, by ' . $r['undone_by'] . '.');
        if ($r['state'] !== 'done') throw new RuntimeException($r['state'] === 'proposed' ? 'A proposal changes nothing: Accept or Dismiss it.' : 'It did nothing, so there\'s nothing to undo.');
        $u = json_decode((string) $r['undo_data'], true) ?: []; $tid = (int) $r['task_id'];
        if (!in_array($r['step'], ['gate', 'handoff', 'audit'], true)) throw new RuntimeException('This kind of run has no Undo.');
        // Claimed first (Piece 3, P3-4: "a second Undo is refused"). Before this, the run was marked undone only after its Undo
        // acted, so two presses at once could both pass the check above and both act: a second move back, a second withdraw.
        // Now only the press that claims it acts. If its Undo fails, the claim is given back, so it can be pressed again.
        $by = mb_substr($who, 0, 80);
        $claim = $pdo->prepare('UPDATE team_runs SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ? AND undone_at IS NULL');
        $claim->execute([$by, $rid]);
        if ($claim->rowCount() !== 1) { $now = self::get($pdo, $rid); throw new RuntimeException('Already undone, by ' . (string) ($now['undone_by'] ?? 'someone else') . '.'); }
        try {
            if ($r['step'] === 'gate') { GaugeTasks::move($pdo, $tid, (string) $u['to'], $who, 'Undo of run #' . $rid, $tz); if (array_key_exists('owner', $u)) $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = NULL WHERE id = ?')->execute([(string) $u['owner'], $tid]); }
            elseif ($r['step'] === 'handoff') {
                $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = NULL, owner = ?, owner_at = ? WHERE id = ?')->execute([(string) ($u['owner'] ?? ''), ($u['owner'] ?? '') !== '' ? gmdate('Y-m-d H:i:s') : null, $tid]);
                ControlLog::add($pdo, 'control', '', 'Task #' . $tid . ' build brief taken back (Undo of run #' . $rid . ')', $who, '', $tz);
            } else GaugeTasks::withdraw($pdo, (int) $u['withdraw'], 'Undo of run #' . $rid, $who, $tz);
        } catch (Throwable $x) {
            $pdo->prepare('UPDATE team_runs SET undone_at = NULL, undone_by = \'\' WHERE id = ? AND undone_by = ?')->execute([$rid, $by]);
            throw $x;
        }
        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';
    }
=== END FILE ===

=== FILE: tests/piece3-spend-undo-static.php ===
<?php
// tools/piece3-tests/piece3-spend-undo-static.php — reads app/lib/TeamRunner.php as it is after this release and checks:
//   1. log() books cost_micros from STEP_COST_MICROS (the spending limit per agent counts something).
//   2. undo() claims the run before it acts and gives the claim back on failure (a second Undo is refused, even at once).
//   3. The decision tree's rule data is unchanged in shape, and every step still costs $0, so the fingerprint is unchanged.
// No database and no outside call. Nothing here moves money. Run: php tools/piece3-tests/piece3-spend-undo-static.php
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
$file = $root . '/app/lib/TeamRunner.php';
$fails = 0;
function check(string $name, bool $ok, string $why = ''): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n"; if (!$ok) $fails++; }
function body(string $src, string $start): string
{
    $i = strpos($src, $start); if ($i === false) return '';
    $j = strpos($src, "\n    }\n", $i); return $j === false ? '' : substr($src, $i, $j - $i);
}

$src = @file_get_contents($file);
check('TeamRunner.php is readable', is_string($src) && $src !== '', $file);
if (!is_string($src)) { echo "FAIL: stopped\n"; exit(1); }

// 1. log() books the cost
$log = body($src, 'private static function log(PDO $pdo');
check('log() found', $log !== '');
check('log() inserts cost_micros', str_contains($log, 'touches, cost_micros) VALUES'));
$vi = strpos($log, 'VALUES ('); $ve = $vi === false ? false : strpos($log, "')", $vi);
$qs = ($vi !== false && $ve !== false) ? substr_count(substr($log, $vi, $ve - $vi), '?') : -1;
$ci = strpos($log, 'INSERT INTO team_runs ('); $ce = $ci === false ? false : strpos($log, ') VALUES', $ci);
$cols = ($ci !== false && $ce !== false) ? count(explode(',', substr($log, $ci + strlen('INSERT INTO team_runs ('), $ce - $ci - strlen('INSERT INTO team_runs (')))) : -1;
check('log() has 13 columns and 12 placeholders (at is UTC_TIMESTAMP())', $cols === 13 && $qs === 12, 'columns ' . $cols . ', placeholders ' . $qs);
check('log() reads the cost from STEP_COST_MICROS', str_contains($log, 'self::STEP_COST_MICROS[$step]'));
check('log() books a cost only for done or proposed', str_contains($log, "in_array(\$state, ['done', 'proposed'], true)"));
check('log() still refuses a done step with no Undo', str_contains($log, "if (\$state === 'done' && (trim(\$undoHow) === '' || \$undo === null))"));
$refuse = strpos($log, "\$state = 'refused'"); $costAt = strpos($log, '$cost = ');
check('the cost is worked out after the no-Undo refusal', $refuse !== false && $costAt !== false && $refuse < $costAt);

// 2. undo() claims first
$undo = body($src, 'public static function undo(PDO $pdo, int $rid');
check('undo() found', $undo !== '');
$claimAt = strpos($undo, 'WHERE id = ? AND undone_at IS NULL');
$actAt = strpos($undo, 'GaugeTasks::move(');
check('undo() claims the run before it acts', $claimAt !== false && $actAt !== false && $claimAt < $actAt);
check('undo() refuses when its claim changed no row', str_contains($undo, 'rowCount() !== 1'));
check('undo() gives the claim back on failure', str_contains($undo, "SET undone_at = NULL, undone_by = '' WHERE id = ? AND undone_by = ?") || str_contains($undo, "SET undone_at = NULL, undone_by = \\'\\' WHERE id = ? AND undone_by = ?"));
check('undo() still refuses a proposal', str_contains($undo, 'A proposal changes nothing: Accept or Dismiss it.'));
check('undo() refuses an unknown step before claiming', ($k = strpos($undo, 'This kind of run has no Undo.')) !== false && $claimAt !== false && $k < $claimAt);

// 3. the rules are unchanged (the tree's approval stays)
try {
    require_once $file;
    $keys = array_keys(TeamRunner::ruleData());
    check('ruleData() keys unchanged', $keys === ['steps', 'step_at', 'ceilings', 'territories', 'tier5_words', 'tier1_words', 'step_cost'], implode(',', $keys));
    check('every step still costs $0', array_sum(TeamRunner::STEP_COST_MICROS) === 0 && array_keys(TeamRunner::STEP_COST_MICROS) === ['gate', 'handoff', 'audit', 'brief'] || array_sum(TeamRunner::STEP_COST_MICROS) === 0, json_encode(TeamRunner::STEP_COST_MICROS));
    echo 'INFO: fingerprint without the database ' . substr(TeamRunner::fingerprint(), 0, 12) . " (compare with the same line before this release on staging)\n";
} catch (Throwable $x) { check('TeamRunner loads', false, $x->getMessage()); }

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAIL\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: tests/piece3-spend-undo-db.php ===
<?php
// tools/piece3-tests/piece3-spend-undo-db.php — against a throwaway MySQL schema only (its name must contain "test"):
//   1. log() writes a row with cost_micros (the column is there and filled).
//   2. undo() refuses a run already undone and a proposal.
//   3. undo() whose action fails gives its claim back (undone_at NULL after), or, if it succeeds, the run is marked undone once.
// Never the live or staging database. No outside call; nothing here moves money.
// Run: TV_TEST_DSN='mysql:host=127.0.0.1;dbname=tv_test' TV_TEST_USER=... TV_TEST_PASS=... php tools/piece3-tests/piece3-spend-undo-db.php
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
$fails = 0;
function check(string $name, bool $ok, string $why = ''): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n"; if (!$ok) $fails++; }

$dsn = (string) getenv('TV_TEST_DSN');
if ($dsn === '' || !preg_match('/dbname=[^;]*test/i', $dsn)) { echo "FAIL: set TV_TEST_DSN to a throwaway schema whose name contains \"test\"\n"; exit(1); }
$pdo = new PDO($dsn, (string) getenv('TV_TEST_USER'), (string) getenv('TV_TEST_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require_once $root . '/app/lib/TeamRunner.php';

TeamRunner::ensure($pdo);
$col = $pdo->query("SHOW COLUMNS FROM team_runs LIKE 'cost_micros'")->fetch(PDO::FETCH_ASSOC);
check('team_runs has cost_micros', (bool) $col);

$slug = 'piece3-test';
$pdo->prepare('DELETE FROM team_runs WHERE role = ?')->execute([$slug]);   // this throwaway schema's own test rows only
$role = ['slug' => $slug, 'name' => 'Piece 3 test role'];

// 1. log() books its cost
try {
    $m = new ReflectionMethod('TeamRunner', 'log'); $m->setAccessible(true);
    $m->invoke(null, $pdo, $role, 0, 'gate', 'a test', 'Proposed: a test', 'proposed', '', ['move' => 'building'], 'A proposal changes nothing until a person accepts it', null, 'task:0');
    $row = $pdo->query("SELECT state, cost_micros FROM team_runs WHERE role = 'piece3-test' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check('log() wrote the run', (bool) $row && $row['state'] === 'proposed');
    check('log() booked the step cost ($0 today)', (bool) $row && (int) $row['cost_micros'] === (int) TeamRunner::STEP_COST_MICROS['gate']);
    $m->invoke(null, $pdo, $role, 0, 'gate', 'a test', 'Moved: a test', 'done', '', null, '', null, 'task:0');   // done with no Undo
    $row = $pdo->query("SELECT state, cost_micros FROM team_runs WHERE role = 'piece3-test' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check('a done step with no Undo is refused and books nothing', (bool) $row && $row['state'] === 'refused' && (int) $row['cost_micros'] === 0);
    check('spentThisMonth reads $0', TeamRunner::spentThisMonth($pdo, $slug) === 0);
} catch (Throwable $x) { check('log() ran', false, $x->getMessage() . ' (ControlLog\'s table may be missing on the test schema: copy it from the live schema)'); }

$ins = $pdo->prepare("INSERT INTO team_runs (at, role, task_id, step, state, undo_how, undo_data, undone_at, undone_by) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?)");

// 2. a second Undo and a proposal are refused
$ins->execute([$slug, 2147480000, 'gate', 'done', 'Moves it back to Planning', json_encode(['to' => 'planning']), gmdate('Y-m-d H:i:s'), 'an earlier test']);
$id = (int) $pdo->lastInsertId();
try { TeamRunner::undo($pdo, $id, 'the test'); check('a second Undo is refused', false, 'it went through'); }
catch (RuntimeException $x) { check('a second Undo is refused', str_starts_with($x->getMessage(), 'Already undone'), $x->getMessage()); }
$ins->execute([$slug, 2147480000, 'gate', 'proposed', '', null, null, '']);
$id = (int) $pdo->lastInsertId();
try { TeamRunner::undo($pdo, $id, 'the test'); check('Undo of a proposal is refused', false, 'it went through'); }
catch (RuntimeException $x) { check('Undo of a proposal is refused', str_contains($x->getMessage(), 'Accept or Dismiss'), $x->getMessage()); }

// 3. a failing Undo gives its claim back (task 2147480000 doesn't exist here)
$ins->execute([$slug, 2147480000, 'gate', 'done', 'Moves it back to Planning', json_encode(['to' => 'planning']), null, '']);
$id = (int) $pdo->lastInsertId();
$threw = false; try { TeamRunner::undo($pdo, $id, 'the test'); } catch (Throwable $x) { $threw = true; }
$after = $pdo->query('SELECT undone_at, undone_by FROM team_runs WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
check('the claim matches the outcome', $threw ? ($after['undone_at'] === null && $after['undone_by'] === '') : ($after['undone_at'] !== null && $after['undone_by'] === 'the test'),
    'threw ' . ($threw ? 'yes' : 'no') . ', undone_at ' . var_export($after['undone_at'], true));

$pdo->prepare('DELETE FROM team_runs WHERE role = ?')->execute([$slug]);   // this throwaway schema's own test rows only
echo $fails === 0 ? "ALL PASS\n" : $fails . " FAIL\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
# FAQ entries: Piece 3 (the admin's Team page and Work queue)

## Can an agent spend money?

No. Every agent's spending limit is $0 a month. None of the Team's steps calls a paid service. A step that would cost more than its agent's limit is refused, and the refusal shows on the ticket's runs.

Each run now records what it cost, so the month's total per agent is a real count. Today every total is $0.

Limits above $0 for agents are **coming**, with the agents' own API budget. Only real people can pay for services, and a real-money payment always needs a person's press.

## What does Undo do on an agent's action?

Every action an agent takes has an Undo. Undo puts things back as if the action never happened:

- a ticket moves back to Planning;
- a build brief is taken back;
- a ticket the audit opened is withdrawn.

An action that can't be undone is never done. The agent can only propose it, and a person accepts or dismisses the proposal.

Each action can be undone once. If two people press Undo at the same moment, only one goes through, and the other is told it's already undone. If an Undo fails, nothing changes, and it can be pressed again.

## What is the before-and-after check?

Before a release goes live, the test accounts look at every page it touches on staging, once before the change and once after. A release that makes a page worse is withdrawn, and the problem goes into the Work queue.

It is built. It is paused for four admin-only releases that no agent built. It is switched back on before any release an agent builds.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "piece3-spend-undo",
  "gate": "Daily batch (decisions/0179 rule 1; its note 'Piece 3's decisions': Piece 3's releases are not pre-approved). Tier 2 backend bug fix: one internal file (app/lib/TeamRunner.php); it touches no high-risk territory (TeamRunner and team_runs are not in TERRITORIES) and changes no rule the decision tree's fingerprint covers.",
  "what": "Piece 3's spending limit per agent and Undo per agent action, made to work as P3-4 wrote them. (1) TeamRunner::log() now writes cost_micros. Before this, run() checked a step's cost against the role's limit, but no run ever recorded a cost, so spentThisMonth() always read $0. (2) TeamRunner::undo() claims the run before it acts and gives the claim back if the Undo fails. Before this, two presses at once could both act (a double move back, a double withdraw), even though a second Undo is meant to be refused. Every step still costs $0, so nothing a person sees changes except the refusal of a second, simultaneous Undo.",
  "files": [
    "files/app/lib/TeamRunner.php.region-1.php",
    "files/app/lib/TeamRunner.php.region-2.php",
    "tests/piece3-spend-undo-static.php",
    "tests/piece3-spend-undo-db.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "SKIPPED (paused): changes to the before-and-after check (app/lib/BeforeAfter.php). decisions/0179's note of 2026-11-26 18:30 UTC pauses it for four releases (ba-proof-fixes, switch-readback, the staff-mail fix, R1). Its noise fixes go in ba-proof-fixes, and it switches back on before any agent-built release and before R2. BeforeAfter.php is reviewed below but not changed.",
    "SKIPPED (waiting for its own release and the Founder's fence): per-agent spend limits above $0, and any setter for team_settings 'spend:<slug>'. 0180's note 'Separate each' makes the agents' API budget its own $40 counter, built with the AI items release (IT Manager's lane, the CFO's Tier 4): 'nothing is spent from (3) until its counter is live'. 0180 §2 makes the spend cap a fence, a Founder ruling. Limits stay $0 and the per-step costs stay $0.",
    "SKIPPED (Jason's own presses): switching on rule 2 or the before-and-after switch, and raising the ceiling to Confirmation (0179's note 'Piece 3's decisions': 'Jason presses staging's three switches himself'; TeamRunner::setCeiling, confirmationWaits).",
    "SKIPPED (paused / waiting for Jason, not part of this item): M2b ('Pause for now'), Order #12's books ('after holiday'), no-undo (needs Jason's pop-up). Not touched.",
    "NOT ATTACHED: open Work-queue parents live in the database. This release names none and opens none.",
    "DRAFT REVIEW: the attached job 19 draft (research/ai-results/job19-decided-not-live/dnl-piece3.md) is empty, so there was nothing to review or keep. This release is built from the live files alone.",
    "STALE HANDOFF: handoffs/decided-not-live.md says Piece 3 is 'not started'. The live code says otherwise: BeforeAfter.php (P3-2), TeamRunner's STEP_COST_MICROS, spendLimit, spentThisMonth and the no-Undo refusal in log() (P3-4), and the fence (P3-6). The 2026-11-26 batch line also lists P3-3 to P3-6 as sent. The session manager should update the line after checking the sync. This release does not edit the handoff.",
    "Built on: 0179 'What comes next' (Piece 3: Undo on every agent action, a spending limit per agent), 0179 rule 1 (daily batch), 0180 §4.1 and §7 ($0 agent spend, the hard money line), 0180 §2 (the fingerprint covers the rules: ruleData() is untouched, so the tree's one-time approval of 2026-11-26 stands).",
    "Chosen: log() books the cost from STEP_COST_MICROS[$step], the same figure run() checks, and only for done or proposed runs. Refused and failed runs book $0. This keeps run(), audit() and every caller unchanged.",
    "Chosen: undo() claims with UPDATE ... WHERE id = ? AND undone_at IS NULL and checks rowCount() before acting. A failure clears only its own claim (AND undone_by = ?) and rethrows. The step check ('This kind of run has no Undo') now comes before the claim, so an unknown step never claims.",
    "Not changed: app/lib/AiCredits.php covers members' AI Assist credits, not agents' spending. It is out of scope and unchanged."
  ],
  "tests": [
    "tests/piece3-spend-undo-static.php -> tools/piece3-tests/piece3-spend-undo-static.php: no database. Checks the log() INSERT (13 columns, 12 placeholders, cost from STEP_COST_MICROS, after the no-Undo refusal), that undo() claims before it acts and gives the claim back, and that ruleData()'s keys and the $0 step costs are unchanged.",
    "tests/piece3-spend-undo-db.php -> tools/piece3-tests/piece3-spend-undo-db.php: a throwaway MySQL schema only (its name must contain 'test'). Checks that log() writes cost_micros, that a no-Undo done run is refused with $0, that a second Undo and an Undo of a proposal are refused, and that a failing Undo leaves undone_at NULL. No outside call and no money: no QuickBooksPay path is reached."
  ],
  "risks": [
    "NOTHING HERE WAS RUN OR VERIFIED: not linted, not tested, not zip-checked. The lane session runs php -l, both tests, and the zip check.",
    "If live's or staging's team_runs was created before cost_micros existed, CREATE TABLE IF NOT EXISTS never added it, and the new INSERT would fail on every run, stopping the Team's runner and audit. Check the column before publishing (verify).",
    "The undo() claim depends on PDOStatement::rowCount() returning affected rows for UPDATE on MySQL/MariaDB (the PDO default). If PDO::MYSQL_ATTR_FOUND_ROWS were set, it still returns 1 only when the row matched undone_at IS NULL, so the guard holds.",
    "While an Undo is acting, the run already shows as undone. If the Undo then fails, it goes back to not undone. A page read in that moment may show 'undone' briefly.",
    "Region anchors: the closing brace '    }' recurs in the file. Each region's end is the brace right after the quoted return line, and the lane must replace exactly that span.",
    "Reviewed, not fixed (each for its own ticket): audit() and applyRefusals() are not subject to the spend check (both $0 today); applyRefusals() acts as 'the fence' with a logbook line but no team_runs record or Undo; handoff Undo restores owner_at as now, not the original time; the audit's second-look Undo withdraws the Work ticket but leaves the Audit ticket Confirmed; BeforeAfter::tickLine() calls CPanel::ready() with no require_once for CPanel in the file shown."
  ],
  "verify": [
    "On live and staging: SHOW COLUMNS FROM team_runs LIKE 'cost_micros' returns the column (INT NOT NULL DEFAULT 0). If it is missing, hold this release and add a guarded, add-only migration first.",
    "On staging, before and after the release: TeamRunner::fingerprint($pdo) is identical, and TeamRunner::treeApproval($pdo) is still set (no 'decision tree's rules changed' line in the History).",
    "Apply region-1 over log() and region-2 over undo() in app/lib/TeamRunner.php, then php -l (PHP 8.0) and a byte diff showing only those two methods changed.",
    "Run tools/piece3-tests/piece3-spend-undo-static.php (ALL PASS), then piece3-spend-undo-db.php on a throwaway test schema (ALL PASS).",
    "On staging with the runner on: one tick writes a team_runs row with cost_micros 0; an Undo pressed twice quickly on a done gate run gives one 'Undone' and one 'Already undone'.",
    "file not shown: app/lib/ControlLog.php, GaugeTasks.php (move, withdraw), TeamRoster.php (actor), Releases.php, Scheduler.php (whether it loads CPanel), Database.php. Confirm their signatures match the calls kept unchanged here.",
    "file not shown: tools/piece3-tests/ (the harness and its bootstrap). Fit the two tests to its style if it differs.",
    "Whether the before-and-after check is back on (its pause covers four named releases only). This release is none of them, so it gets the check if the switch is on. Read Switches on live.",
    "The job 19 draft file came through empty. Confirm with the research job that no draft content was lost."
  ],
  "regions": true
}
=== END FILE ===