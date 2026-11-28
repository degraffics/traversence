<?php
// app/lib/TeamVet.php — "Run a test ticket" on a Team role's page (Jason, 2026-11-28: each role "ready to be vetted": run a test
// ticket on staging, show the result and its cost; an agent counts as live only once its test ticket passes).
//   - Staging only, like the runner itself (TeamRunner::blocked): never on the live site.
//   - It makes one test ticket the role's own step applies to, runs that step once through TeamRunner::run (the same code a
//     real run takes: its permissions, its spending limit, its ceiling), and reads the run's own record: what it did, its state,
//     its cost.
//   - Nothing stays in the queue: the run is undone (or its proposal dismissed) and the test ticket withdrawn, by the person who
//     pressed it. The run and the ticket are kept in the logbook, marked as a test.
//   - Where a role can't run yet, off() says why and the way forward (CLAUDE.md "Never a dead end").
//   - No outside call, no paid AI, no money: a step costs what TeamRunner::STEP_COST_MICROS says, $0 today.
declare(strict_types=1);

require_once __DIR__ . '/TeamRunner.php';
require_once __DIR__ . '/TeamRoster.php';

final class TeamVet
{
    /** Test tickets live on their own area, so no report or goal counts them. */
    public const GAUGE = 'area:team-test';

    /** The role's step a test ticket runs: its first single-ticket step (the audit is a daily job, not one ticket's step). */
    public static function stepOf(array $r): string
    {
        foreach (TeamRoster::steps((string) $r['slug']) as $k) if ($k !== 'audit') return $k;
        return '';
    }

    /** Why this role can't run a test ticket here and now, with the way forward; '' when it can. */
    public static function off(PDO $pdo, array $r): string
    {
        $kind = (string) $r['kind']; $slug = (string) $r['slug'];
        if ($kind === 'founder') return 'The Founder acts only when signed in, never as an agent, so there is nothing to test.';
        if ($kind === 'advocate') return 'An advocate only looks: it never works a ticket. Its own audit of the live site comes with its release; its ticket is in the Work queue (Agent online, ' . $r['name'] . ').';
        $steps = TeamRoster::steps($slug);
        if (!$steps) return 'It has no step in the runner yet, so there is nothing to run. Its own release brings one; its ticket is in the Work queue (Agent online, ' . $r['name'] . ').';
        $step = self::stepOf($r);
        if ($step === '') return 'Its work is the daily audit, not one ticket\'s step: use Run the audit now.';
        $b = TeamRunner::blocked($pdo);
        if ($b === 'live') return 'A test ticket runs on the staging copy only, so it can never touch the live site: open this page there.';
        if ($b !== null) return $b;
        if ((string) $r['status'] !== 'active') return 'It\'s on standby, so the runner gives it no work: Make active first, then run its test ticket.';
        if (TeamRunner::mode($pdo, $step) === 'off') return 'Its step is off while agents work up to ' . TeamRunner::CEILINGS[TeamRunner::ceiling($pdo)][0] . '. The Founder raises that on the Team page (Agents work up to), then it can be tested.';
        return '';
    }

    /**
     * Run one test ticket for this role. Returns the result in words; throws InvalidArgumentException with off()'s words when
     * it can't run. $who is the person who pressed it: the clean-up (Undo, Dismiss, Withdraw) is theirs.
     */
    public static function run(PDO $pdo, array $r, string $who, string $tz = ''): string
    {
        $no = self::off($pdo, $r);
        if ($no !== '') throw new InvalidArgumentException($no);
        $who = trim($who) !== '' ? $who : 'a staff member';
        TeamRunner::ensure($pdo); GaugeTasks::ensure($pdo);
        $step = self::stepOf($r); $slug = (string) $r['slug']; $actor = TeamRoster::actor($r);
        if ((int) $pdo->query("SELECT GET_LOCK('team_tick', 0)")->fetchColumn() !== 1) throw new InvalidArgumentException('The runner is in the middle of a run: try again in a minute.');
        $id = 0; $run = null; $left = [];
        try {
            $what = 'Test ticket for ' . $r['name'] . ' (' . gmdate('M j, H:i') . ' UTC)';
            $id = GaugeTasks::add($pdo, self::GAUGE, $what, 'Run a test ticket, pressed by ' . $who . ': it shows what this role does with a ticket, then it is withdrawn', 'System Configuration → Team → ' . $r['name'], $who, 0, $tz, $step === 'brief' ? 'resolution' : 'work');
            if ($step === 'brief') {   // the Translator: a Resolution brief with a decision attached and its words still empty
                $pdo->prepare('INSERT INTO control_gauge_task_adrs (task_id, adr, at, who) VALUES (?, ?, UTC_TIMESTAMP(), ?)')->execute([$id, '0180', mb_substr($who, 0, 80)]);
            } else {   // the gate and the hand-off: a Work ticket with its scope, no decision needed, held by the role itself
                GaugeTasks::edit($pdo, $id, ['goal' => 'A test: see this role take one step on a ticket', 'done_when' => 'The step ran and its result is on the role\'s page'], $who, $tz);
                GaugeTasks::noDecision($pdo, $id, $who, $tz);
                $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = UTC_TIMESTAMP()' . ($step === 'handoff' ? ", stage = 'building', stage_at = UTC_TIMESTAMP()" : '') . ' WHERE id = ?')->execute([mb_substr($actor, 0, 80), $id]);
            }
            $t = GaugeTasks::task($pdo, $id);
            if (!$t || !TeamRunner::applies($pdo, $step, $t)) throw new RuntimeException('its step doesn\'t apply to the test ticket as made (' . $step . ')');
            TeamRunner::run($pdo, $t, $step, $r);
            $runs = TeamRunner::runs($pdo, $id, 1); $run = $runs[0] ?? null;
            if (!$run) throw new RuntimeException('the step ran but recorded nothing');
            // nothing stays in the queue: undo what it did, or dismiss what it proposed
            try {
                if ($run['state'] === 'done' && (string) $run['undo_how'] !== '') TeamRunner::undo($pdo, (int) $run['id'], $who, $tz);
                elseif ($run['state'] === 'proposed') TeamRunner::dismiss($pdo, (int) $run['id'], $who);
            } catch (Throwable $x) { $left[] = 'its run #' . (int) $run['id'] . ' wasn\'t undone (' . $x->getMessage() . ')'; }
        } catch (RuntimeException $x) {
            $run = $run ?? ['id' => 0, 'state' => 'failed', 'did' => '', 'why' => $x->getMessage(), 'cost_micros' => 0];
        } finally {
            if ($id) { try { GaugeTasks::withdraw($pdo, $id, 'A test ticket: it ran and is finished', $who, $tz); } catch (Throwable $x) { $left[] = 'test ticket #' . $id . ' wasn\'t withdrawn (' . $x->getMessage() . ')'; } }
            $pdo->query("SELECT RELEASE_LOCK('team_tick')");
        }
        $pass = in_array((string) $run['state'], ['done', 'proposed'], true);
        $cost = (int) ($run['cost_micros'] ?? 0);
        $did = $pass ? (string) $run['did'] : ((string) $run['why'] !== '' ? (string) $run['why'] : 'It did nothing');
        $v = ['ok' => $pass ? 1 : 0, 'run' => (int) $run['id'], 'task' => $id, 'step' => $step, 'state' => (string) $run['state'], 'cost' => $cost, 'did' => mb_substr($did, 0, 110)];
        $pdo->prepare('REPLACE INTO team_settings (k, v, who, at, tz) VALUES (?, ?, ?, UTC_TIMESTAMP(), ?)')->execute([self::key($slug), json_encode($v, JSON_UNESCAPED_UNICODE), mb_substr($who, 0, 80), mb_substr($tz, 0, 64)]);
        $words = ($pass ? 'Passed' : 'Not passed') . ': ' . rtrim($did, '.') . '. Cost $' . number_format($cost / 1e6, 2) . '.';
        ControlLog::add($pdo, 'control', '', 'Team role ' . $slug . ': test ticket #' . $id . ' ' . ($pass ? 'passed' : 'didn\'t pass') . ' (' . TeamRunner::STEPS[$step][1] . '): ' . mb_substr($did, 0, 200) . '; cost $' . number_format($cost / 1e6, 2), $who, '', $tz);
        return $words . ($left ? ' To tidy by hand in the Work queue: ' . implode('; ', $left) . '.' : ' The test ticket (#' . $id . ') is withdrawn and its run ' . ($run['state'] === 'proposed' ? 'dismissed' : ($run['state'] === 'done' ? 'undone' : 'kept in the log')) . ', so nothing stays in the queue.');
    }

    /** The role's last test ticket: ['ok', 'run', 'task', 'step', 'state', 'cost', 'did', 'who', 'at', 'tz'], or null when never run. */
    public static function last(PDO $pdo, string $slug): ?array
    {
        try {
            $st = $pdo->prepare('SELECT v, who, at, tz FROM team_settings WHERE k = ?'); $st->execute([self::key($slug)]);
            $row = $st->fetch(PDO::FETCH_ASSOC); if (!$row) return null;
            $v = json_decode((string) $row['v'], true); if (!is_array($v)) return null;
            return $v + ['who' => (string) $row['who'], 'at' => (string) $row['at'], 'tz' => (string) $row['tz']];
        } catch (Throwable $x) { return null; }
    }

    /** The last test ticket in words, for the role's page: its result, its cost, when and by whom; and why it can't run now, when it can't. */
    public static function lastWords(PDO $pdo, array $r): string
    {
        if ((string) $r['kind'] === 'founder') return '';
        $no = self::off($pdo, $r); $cant = $no !== '' ? ' It can\'t run now: ' . $no : '';   // the reason in words on the page too: a greyed button's title doesn't show on a phone
        if ((string) $r['kind'] === 'advocate') return $no;
        $l = self::last($pdo, (string) $r['slug']);
        if (!$l) return 'Not run yet. This role counts as live once its test ticket passes.' . $cant;
        $when = $l['at'] !== '' ? gmdate('M j, H:i', (int) strtotime($l['at'] . ' UTC')) . ' UTC' : '';
        return (!empty($l['ok']) ? 'Passed' : 'Not passed') . ': ' . rtrim((string) $l['did'], '.') . '. Cost $' . number_format(((int) $l['cost']) / 1e6, 2) . '. ' . $when . ', by ' . $l['who'] . ' (run #' . (int) $l['run'] . ', on the staging copy).' . $cant;
    }

    /** What this role's steps cost and call: code first, $0 (TeamRunner::STEP_COST_MICROS), with its monthly limit. */
    public static function aiWords(PDO $pdo, array $r): string
    {
        if (in_array((string) $r['kind'], ['founder', 'advocate'], true)) return '';
        $steps = TeamRoster::steps((string) $r['slug']);
        if (!$steps) return 'None yet: it has no step. When its release brings one, it runs in code first.';
        $cost = 0; foreach ($steps as $k) $cost += (int) (TeamRunner::STEP_COST_MICROS[$k] ?? 0);
        return 'Code only, at $' . number_format($cost / 1e6, 2) . ' a step: it calls no paid AI and no outside service. Its limit this month is $' . number_format(TeamRunner::spendLimit($pdo, (string) $r['slug']) / 1e6, 2)
            . '. A paid AI path for agents stays off until it has its own budget, which comes as its own release for the Founder\'s yes.';
    }

    private static function key(string $slug): string { return mb_substr('vet:' . $slug, 0, 40); }
}
