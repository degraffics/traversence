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
    ];

    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS team_runs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, role VARCHAR(60) NOT NULL DEFAULT '',
            task_id INT UNSIGNED NULL, step VARCHAR(20) NOT NULL DEFAULT '', seen VARCHAR(400) NOT NULL DEFAULT '', did VARCHAR(400) NOT NULL DEFAULT '',
            state VARCHAR(10) NOT NULL DEFAULT 'done', why VARCHAR(400) NOT NULL DEFAULT '', proposal TEXT NULL, undo_how VARCHAR(400) NOT NULL DEFAULT '', undo_data TEXT NULL,
            undone_at DATETIME NULL, undone_by VARCHAR(80) NOT NULL DEFAULT '', settled_at DATETIME NULL, settled_by VARCHAR(80) NOT NULL DEFAULT '',
            cost_micros INT NOT NULL DEFAULT 0, touches VARCHAR(400) NOT NULL DEFAULT '', KEY task_at (task_id, at), KEY at (at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
            $pick = self::pick($pdo);
            if (!$pick) return 'Nothing to do: no ticket a working role has a step for.';
            [$t, $step, $role] = $pick;
            return self::run($pdo, $t, $step, $role);
        } finally { $pdo->query("SELECT RELEASE_LOCK('team_tick')"); }
    }

    /** The ticket and step for this run: [task row, step, role row], or null. Priority first, then age (the queue's own order). */
    public static function pick(PDO $pdo): ?array
    {
        $roles = [];
        foreach (self::STEPS as $step => [$slug]) { $r = TeamRoster::bySlug($pdo, $slug); if ($r && $r['status'] === 'active') $roles[$step] = $r; }
        if (!$roles) return null;
        $recent = [];
        try { $st = $pdo->prepare('SELECT DISTINCT task_id FROM team_runs WHERE at > UTC_TIMESTAMP() - INTERVAL ' . self::GAP_MINUTES . ' MINUTE'); $st->execute(); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $recent[(int) $x] = true; } catch (Throwable $x) {}
        $q = array_filter(GaugeTasks::queue($pdo), fn ($t) => !in_array($t['state'], ['done', 'withdrawn'], true) && !isset($recent[(int) $t['id']]) && self::mayWork($t));
        usort($q, fn ($a, $b) => [((int) ($a['priority'] ?? 0)) ?: 4, (int) $a['id']] <=> [((int) ($b['priority'] ?? 0)) ?: 4, (int) $b['id']]);
        foreach ($q as $t) foreach ($roles as $step => $role) if (self::applies($pdo, $step, $t)) return [$t, $step, $role];
        return null;
    }

    /** Never a ticket a person has: only unowned ones, or ones a Team role has. */
    public static function mayWork(array $t): bool { $o = (string) $t['owner']; return $o === '' || str_starts_with($o, 'system · '); }

    /** Does this step have work on this ticket? */
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && GaugeTasks::gate($pdo, $t) === [];
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        return false;
    }

    /** Do the step, log it, and return the line. */
    public static function run(PDO $pdo, array $t, string $step, array $role): string
    {
        self::ensure($pdo);
        [, $name, $action, $def] = self::STEPS[$step];
        $who = TeamRoster::actor($role); $id = (int) $t['id'];
        [$ok, $no] = TeamRoster::can($role, $action, $def);
        if (!$ok) return self::log($pdo, $role, $id, $step, 'its permissions', '', 'refused', $no . ' (Permissions: ' . $action . ')');
        try {
            if ($step === 'gate') {
                $msg = GaugeTasks::move($pdo, $id, 'building', $who, 'its gate passed: a goal, Done when, its decisions and someone who has it');
                return self::log($pdo, $role, $id, $step, 'its goal, Done when, decisions and owner', $msg, 'done', '', null, 'Moves it back to Planning', ['to' => 'planning'], 'task:' . $id);
            }
            if ($step === 'handoff') {
                $brief = self::buildBrief($pdo, $t);
                $was = (string) $t['owner'];
                $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = ?, owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$brief, mb_substr($who, 0, 80), $id]);
                ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' build brief written and handed to the session manager (hourly report)', $who);
                return self::log($pdo, $role, $id, $step, 'its scope, decisions and steps', 'Wrote its build brief; with the session manager at the next hourly report', 'done', '', null,
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
        if ($r['step'] === 'gate') GaugeTasks::move($pdo, $tid, (string) $u['to'], $who, 'Undo of run #' . $rid, $tz);
        elseif ($r['step'] === 'handoff') {
            $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = NULL, owner = ?, owner_at = ? WHERE id = ?')->execute([(string) ($u['owner'] ?? ''), ($u['owner'] ?? '') !== '' ? gmdate('Y-m-d H:i:s') : null, $tid]);
            ControlLog::add($pdo, 'control', '', 'Task #' . $tid . ' build brief taken back (Undo of run #' . $rid . ')', $who, '', $tz);
        } else throw new RuntimeException('This kind of run has no Undo.');
        $pdo->prepare('UPDATE team_runs SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ? AND undone_at IS NULL')->execute([mb_substr($who, 0, 80), $rid]);
        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';
    }

    /** Accept a proposal: each part applied as a person's Edit (History kept), only where the field is still empty. */
    public static function accept(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r || $r['state'] !== 'proposed') throw new RuntimeException('That isn\'t a proposal.');
        if ($r['settled_at'] !== null) throw new RuntimeException('It\'s already ' . ($r['undone_at'] !== null ? 'dismissed' : 'accepted') . '.');
        $p = json_decode((string) $r['proposal'], true) ?: []; $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = [];
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
