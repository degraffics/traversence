=== FILE: files/app/lib/TeamRunner.php.region-1.php ===
// ANCHOR: replaces         'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]], through         'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
        // the Project Manager's second step (decisions/0180 role 8; jason-spec §8 "Project Manager tracking sequence/dependencies"; §11 #6 "tracks dependencies and stalls"): propose-only at every ceiling
        'stall' => ['project-manager', 'Says what a stalled Work ticket in its lane waits on, from its own record, or asks a session when its record can\'t say', 'team.stall', ['min' => 'manager', 'depts' => ['ops']]],
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-2.php ===
// ANCHOR: replaces         'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'], through         'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        // a stall note never changes a ticket: a person accepts or dismisses it, at every ceiling
        'stall' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-3.php ===
// ANCHOR: replaces     public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0]; through     public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];
    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0, 'stall' => 0];

    /**
     * The AI access order for each step (decisions/0180 §6 and its note "the routing rule, cheapest first"; jason-spec §4.1
     * "Internal Systems & Deterministic Logic First" and §4.5 "No Silent Guesswork"). The order is: code and templates first, at $0.
     * Then the API, for a focused call with a small, known context. Then a session, for reasoning. Each step lists only the
     * routes it may use, in that order, and every step uses code first. This is part of the rules (ruleData), so changing it
     * clears the tree's approval.
     */
    public const AI_ACCESS = ['gate' => ['code'], 'handoff' => ['code'], 'brief' => ['code'], 'audit' => ['code'], 'stall' => ['code', 'api', 'session']];
    /** A Work ticket in the Project Manager's lane is stalled when its stage hasn't moved for this many days (research/team-vetting-2026-11-27: "A ticket idle for 3 days"). */
    public const STALL_DAYS = 3;
    /** The cap on a session question (0180 §6: "concise and targeted ... never the whole history"). */
    public const ASK_MAX = 1600;

    /**
     * Each role's grounding (decisions/0180 §6: "the ADRs and docs it must follow; its job description; its job knowledge; its
     * escalation support"), as fixed words in the code. A person's words on the Team page (team_roster.mandate, .rules) are
     * never written over by this. slug => charter. Other roles add their own entries here.
     */
    public const CHARTERS = [
        'project-manager' => [
            'role' => 'Project Manager (decisions/0180 role 8: Layer 3, reports to the COO)',
            'lane' => 'project-manager',
            'tickets' => ['work'],
            'follows' => ['decisions/0180 §1 (tiers), §4 (territories), §5 (lanes), §6 (how roles reason), §7 (guardrails)', 'decisions/0179 (the ceiling, the decision tree, rule 2)', 'decisions/0165 (staging first)', 'decisions/0066 (tribal nations\' land)', 'decisions/0164 (nothing private to a free outside API)', 'decisions/0178 (money: no Undo; refunds need a person)', 'handoffs/piece2/jason-spec.md §4 (routing) and §8 (the four-stage Work queue)'],
            'job' => 'Moves Work tickets through Planning, Building, Implementation and Confirmation. Moves a ticket out of Planning once its gate passes (the gate step). Tracks stalls and dependencies in its own lane (the stall step). Its lane is delivery: the lane a ticket falls to when no other lane route matches.',
            'knowledge' => ['The Work queue: GaugeTasks::queue, ::gate, ::steps, ::moves', 'its own runs (team_runs, steps gate and stall)', 'the lane routes (TeamRoster::LANE_ROUTES, ::lane)', 'the high-risk territories (TeamRunner::TERRITORIES)'],
            'escalates' => ['Work for another lane: a hand-off, one lane at a time, with a note (GaugeTasks::handOff; Tier 3)', 'A change that touches a high-risk territory: the Tier 4 review before live (0180 §4)', 'A rule, fence, permission or new policy: a Founder ruling (Tier 5), as a Resolution ticket', 'While it is on standby, its work goes up its line (TeamRoster::doer): to the COO, then the CEO, then the Founder'],
            'never' => ['takes a ticket a person has', 'writes over a person\'s words', 'acts through a test account', 'moves money or spends above $0', 'works one ticket more than once an hour', 'changes a rule, a fence or a ceiling'],
        ],
    ];

    /**
     * The fixed question a stall step sends to a session when the ticket's own record can't say why it stalled (0180 §6: lean,
     * answered with a citation). The placeholders are filled from the ticket alone: no history, no person's name.
     */
    public const ASK_PROMPT = "You are the Project Manager (decisions/0180 role 8). Answer in two lines and nothing else. "
        . "(1) What the ticket waits on, the next action, and who takes it. (2) The decision and section that rests on, or \"none applies\". "
        . "If another lane must do it, say \"Hand off to <lane>\" (Tier 3). If it touches a high-risk territory, say \"Tier 4 review\". "
        . "Never change a rule, a fence or a person's words. If only a rule change would settle it, say \"Founder ruling (Tier 5)\".\n"
        . "Work ticket #{id}, at {stage} for {days} days; its own record doesn't say why.\n"
        . "What: {what}\nWhy: {why}\nDone when: {done_when}\nDecisions attached: {adrs}\nTouches: {touches}";
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-4.php ===
// ANCHOR: replaces     public static function ruleData(): array through         return ['steps' => $steps, 'step_at' => self::STEP_AT, 'ceilings' => array_keys(self::CEILINGS), 'territories' => self::TERRITORIES, 'tier5_words' => self::TIER5_WORDS, 'tier1_words' => self::TIER1, 'step_cost' => self::STEP_COST_MICROS];
    public static function ruleData(): array
    {
        $steps = []; foreach (self::STEPS as $k => [$role, , $action, $def]) $steps[$k] = [$role, $action, $def];
        // the AI access order and the stall threshold are rules (who may reach which route, and when): they join the fingerprint
        return ['steps' => $steps, 'step_at' => self::STEP_AT, 'ceilings' => array_keys(self::CEILINGS), 'territories' => self::TERRITORIES, 'tier5_words' => self::TIER5_WORDS, 'tier1_words' => self::TIER1, 'step_cost' => self::STEP_COST_MICROS,
            'ai_access' => self::AI_ACCESS, 'stall_days' => self::STALL_DAYS];
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-5.php ===
// ANCHOR: replaces         if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step through         if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'stall') return self::stallDue($pdo, $t);   // the Project Manager's own lane only (0180 §5)
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-6.php ===
// ANCHOR: replaces             if ($step === 'brief') { through             if ($step === 'brief') {
            if ($step === 'stall') {   // the Project Manager (0180 role 8): code first, then the API, then a session (AI_ACCESS; 0180 §6)
                $skipped = [];
                foreach (self::AI_ACCESS['stall'] as $route) {
                    if ($route === 'code') {
                        $says = self::stallRead($pdo, $t);
                        if ($says !== '') return self::log($pdo, $role, $id, $step, 'its stage, gate, release, steps and the tickets it waits on (its own record: code, $0)', 'Proposed a stall note: ' . mb_substr($says, 0, 300), 'proposed', '', ['note' => $says, 'route' => 'code'],
                            'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
                        continue;
                    }
                    if ($route === 'api') {
                        $no = self::apiOpen();
                        $skipped[] = 'the API: ' . ($no !== '' ? $no : 'open, but no Project Manager call is written for it yet, so nothing was sent');
                        continue;
                    }
                    if ($route === 'session') {
                        $adr = array_map(fn ($a) => $a['adr'] === 'none' ? 'none applies' : (string) $a['adr'], GaugeTasks::adrs($pdo, $id));
                        $q = self::askText($t, $adr, self::touches(TeamRoster::ticketTargets($t)), self::idleDays($t));
                        return self::log($pdo, $role, $id, $step, 'its own record, which doesn\'t say why it stalled', 'Asked a session, as the Project Manager, what it waits on (' . mb_substr(implode('; ', $skipped), 0, 220) . ')', 'proposed', '',
                            ['ask' => $q, 'route' => 'session', 'skipped' => $skipped], 'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
                    }
                }
                return self::log($pdo, $role, $id, $step, $name, '', 'failed', 'No route could settle it: ' . implode('; ', $skipped));
            }
            if ($step === 'brief') {
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-7.php ===
// ANCHOR: replaces         } $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = []; through         } $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = [];
        }
        if ($r['step'] === 'stall') {   // the Project Manager's note or question: accepted into the ticket's History, nothing else changes
            $note = (string) ($p['note'] ?? '');
            $words = $note !== '' ? 'stall noted: ' . $note : 'the Project Manager\'s question to a session taken up';
            ControlLog::add($pdo, 'control', '', 'Task #' . (int) $r['task_id'] . ' ' . mb_substr($words, 0, 380) . ' (run #' . $rid . ')', $who, '', $tz);
            $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
            return 'Accepted: it\'s in the ticket\'s History. Nothing else changed.';
        }
        $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = [];
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-8.php ===
// ANCHOR: replaces     private static function roleName(PDO $pdo, string $slug): string { $r = TeamRoster::bySlug($pdo, $slug); return $r ? (string) $r['name'] : $slug; } through     private static function roleName(PDO $pdo, string $slug): string { $r = TeamRoster::bySlug($pdo, $slug); return $r ? (string) $r['name'] : $slug; }
    private static function roleName(PDO $pdo, string $slug): string { $r = TeamRoster::bySlug($pdo, $slug); return $r ? (string) $r['name'] : $slug; }

    // ---- the Project Manager (decisions/0180 role 8): its charter, its stall step, its routes --------------------------------

    /** A role's charter (CHARTERS), or [] when none is written yet. */
    public static function charter(string $slug): array { return self::CHARTERS[$slug] ?? []; }

    /**
     * Whether the agents' API route is open: '' when it is, else why not. It stays closed until the AI items release brings
     * the agents' own $40 counter in AiClient (decisions/0180, the correction "Separate each", 2026-11-27: "nothing is spent from
     * (3) until its counter is live, and until then agents stay on sessions"). That release replaces this with the counter's
     * own allow() check. Until then nothing here calls AiClient, an outside service or paid AI.
     */
    public static function apiOpen(): string
    {
        return 'not open: the agents\' API budget has no counter of its own yet, so nothing is spent from it (decisions/0180, "Separate each")';
    }

    /** Whole days since a ticket last moved stage or changed hands (stage_at or owner_at, whichever is later; at if neither). */
    public static function idleDays(array $t, ?int $now = null): int
    {
        $ts = 0;
        foreach (['stage_at', 'owner_at', 'at'] as $k) {
            $v = (string) ($t[$k] ?? ''); if ($v === '') continue;
            $x = strtotime($v . ' UTC'); if ($x !== false && $x > $ts) $ts = $x;
            if ($k !== 'at' && $ts > 0 && $k === 'owner_at') break;
        }
        return $ts > 0 ? max(0, (int) floor((($now ?? time()) - $ts) / 86400)) : 0;
    }

    /** Is a stall note due on this ticket? A Work ticket in the Project Manager's lane, idle STALL_DAYS, with no open or recent stall run. */
    public static function stallDue(PDO $pdo, array $t): bool
    {
        $c = self::CHARTERS['project-manager'];
        if (!in_array((string) ($t['ticket'] ?? 'work'), $c['tickets'], true)) return false;
        if (!in_array((string) ($t['stage'] ?? ''), ['planning', 'building', 'implementation', 'confirmation'], true)) return false;
        if (($t['cleared_at'] ?? null) !== null || ($t['undone_at'] ?? null) !== null) return false;
        if (self::idleDays($t) < self::STALL_DAYS) return false;
        if (TeamRoster::lane($pdo, $t) !== $c['lane']) return false;   // another lane's stalls are its own manager's (0180 §5)
        if (self::openProposal($pdo, (int) $t['id'], 'stall')) return false;
        try {
            $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = 'stall' AND at > UTC_TIMESTAMP() - INTERVAL " . (int) self::STALL_DAYS . " DAY LIMIT 1");
            $st->execute([(int) $t['id']]); if ($st->fetchColumn()) return false;
        } catch (Throwable $x) { return false; }
        return true;
    }

    /** Open tickets a ticket's own words name ("#123", "task #123"), itself left out: what it may be waiting on. */
    public static function openDeps(PDO $pdo, array $t): array
    {
        $text = implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['in_scope'] ?? '')]);
        if (!preg_match_all('/(?:\btask\s*)?#(\d{1,7})\b/i', $text, $m)) return [];
        $ids = array_values(array_diff(array_unique(array_map('intval', $m[1])), [(int) ($t['id'] ?? 0), 0]));
        $out = [];
        foreach (array_slice($ids, 0, 10) as $d) { $r = GaugeTasks::task($pdo, $d); if ($r && $r['cleared_at'] === null && $r['undone_at'] === null) $out[] = $d; }
        return $out;
    }

    /** What a stalled ticket waits on, read from our own records ('' when they can't say). */
    public static function stallRead(PDO $pdo, array $t): string
    {
        $id = (int) $t['id']; $sg = (string) ($t['stage'] ?? '');
        $rulings = [];
        try { $st = $pdo->prepare("SELECT id FROM control_gauge_tasks WHERE parent_id = ? AND ticket = 'resolution' AND cleared_at IS NULL AND undone_at IS NULL ORDER BY id"); $st->execute([$id]); $rulings = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)); } catch (Throwable $x) {}
        $missing = $sg === 'planning' ? GaugeTasks::gate($pdo, $t) : [];
        $openSteps = $sg === 'confirmation' ? count(array_filter(GaugeTasks::steps($pdo, $id), fn ($s) => $s['done_at'] === null)) : 0;
        $briefTo = trim((string) ($t['build_brief'] ?? '')) !== '' ? self::briefFor($pdo, $t) : '';
        return self::stallSays($t, $missing, $rulings, self::openDeps($pdo, $t), $openSteps, self::touches(TeamRoster::ticketTargets($t)), $briefTo, self::openProposal($pdo, $id, 'gate'));
    }

    /** The stall note's words (pure: from what stallRead gathered). '' when nothing in the record explains the stall. */
    public static function stallSays(array $t, array $missing, array $rulings, array $deps, int $openSteps, string $territory, string $briefTo, bool $gateOpen = false): string
    {
        $sg = (string) ($t['stage'] ?? ''); $rel = trim((string) ($t['rel'] ?? '')); $w = [];
        if ($rulings) $w[] = 'the Founder\'s ruling (Tier 5) on ' . implode(', ', array_map(fn ($i) => 'task #' . $i, $rulings));
        if ($deps) $w[] = implode(', ', array_map(fn ($i) => 'task #' . $i, $deps)) . ', which it names and which is still open';
        if ($sg === 'planning') {
            if ($missing) $w[] = 'its Planning gate: ' . implode(', ', $missing);
            elseif ($gateOpen) $w[] = 'a person to accept the Project Manager\'s proposal to move it to Building';
        } elseif ($sg === 'building' && $rel === '') {
            if ($briefTo === '') $w[] = 'its build brief (the System Architect\'s step at Building)';
            elseif ($briefTo === 'founder') $w[] = 'you: its change reaches a public page while Implementation reaches Backend only (the fence)';
            elseif ($briefTo === 'build') $w[] = 'a build session to take its brief and name its release';
            else $w[] = 'the session manager to queue its brief and name its release';
        } elseif ($sg === 'implementation' && $rel !== '') {
            $w[] = 'its release ' . $rel . ' to go live (it moves on by itself)';
        } elseif ($sg === 'confirmation' && $openSteps > 0) {
            $w[] = $openSteps . ' step' . ($openSteps === 1 ? '' : 's') . ' to be confirmed';
        }
        if (!$w) return '';
        $stage = GaugeTasks::stagesOf('work')[$sg][0] ?? $sg;
        return 'Task #' . (int) ($t['id'] ?? 0) . ' has been at ' . $stage . ' for ' . self::idleDays($t) . ' days. It waits on ' . implode('; ', $w) . '.'
            . ($territory !== '' ? ' It touches a high-risk territory (' . (GaugeTasks::BOUNDARIES[$territory] ?? $territory) . '), so its Tier 4 review comes before live (decisions/0180 §4).' : '');
    }

    /** The session question (pure): ASK_PROMPT filled from the ticket alone, capped at ASK_MAX. No history; no owner's name. */
    public static function askText(array $t, array $adrs, string $territory, int $days): string
    {
        $one = fn ($s, int $n): string => mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $s)), 0, $n);
        $dw = $one($t['done_when'] ?? '', 240);
        $q = strtr(self::ASK_PROMPT, [
            '{id}' => (string) (int) ($t['id'] ?? 0), '{days}' => (string) $days, '{stage}' => GaugeTasks::stagesOf('work')[(string) ($t['stage'] ?? '')][0] ?? (string) ($t['stage'] ?? ''),
            '{what}' => $one($t['what'] ?? '', 240), '{why}' => $one($t['why'] ?? '', 240), '{done_when}' => $dw !== '' ? $dw : 'not set',
            '{adrs}' => $adrs ? implode(', ', array_slice($adrs, 0, 8)) : 'none attached',
            '{touches}' => $territory !== '' ? (GaugeTasks::BOUNDARIES[$territory] ?? $territory) : 'no high-risk territory',
        ]);
        return mb_substr($q, 0, self::ASK_MAX);
    }

    /** The Project Manager's open questions to a session, by ticket: what the report carries (GaugeTasks::report, 'team_ask'). */
    public static function asks(PDO $pdo): array
    {
        $out = [];
        try {
            foreach ($pdo->query("SELECT id, task_id, role, at, proposal FROM team_runs WHERE step = 'stall' AND state = 'proposed' AND settled_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $p = json_decode((string) $r['proposal'], true) ?: []; if ((string) ($p['ask'] ?? '') === '') continue;
                $out[(int) $r['task_id']] = ['run' => (int) $r['id'], 'role' => (string) $r['role'], 'at_utc' => str_replace(' ', 'T', (string) $r['at']) . 'Z',
                    'to' => 'a session, as the Project Manager (decisions/0180 role 8)', 'ask' => (string) $p['ask']];
            }
        } catch (Throwable $x) {}
        return $out;
    }
=== END FILE ===

=== FILE: files/app/lib/GaugeTasks.php.region-1.php ===
// ANCHOR: replaces             } catch (Throwable $x) { foreach ($req as $i => $q) if ($q['build_brief'] !== '') $req[$i]['brief_to'] = 'session manager'; } through             } catch (Throwable $x) { foreach ($req as $i => $q) if ($q['build_brief'] !== '') $req[$i]['brief_to'] = 'session manager'; }
            } catch (Throwable $x) { foreach ($req as $i => $q) if ($q['build_brief'] !== '') $req[$i]['brief_to'] = 'session manager'; }
            // the Project Manager's open question to a session (decisions/0180 §6: code first, then the API, then a session), carried
            // with its ticket. Its words come from the ticket alone: no person's name (0145 §1)
            try {
                require_once __DIR__ . '/TeamRunner.php'; $asks = TeamRunner::asks($pdo);
                foreach ($req as $i => $q) if (isset($asks[$q['id']])) $req[$i]['team_ask'] = $asks[$q['id']];
            } catch (Throwable $x) {}
=== END FILE ===

=== FILE: tests/team-pm-tests.php ===
<?php
// tools/team-tests/team-pm-tests.php: the Project Manager's agent (decisions/0180 role 8). This is a DRAFT and has not been run.
// The tests are pure: they need no database and no network. No money moves: nothing here loads QuickBooksPay, a connector or AiClient.
// Run: php tools/team-tests/team-pm-tests.php   (or set SITE_ROOT to the site's root)
declare(strict_types=1);

$root = getenv('SITE_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/TeamRunner.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok): void { global $pass, $fail; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if ($ok) $pass++; else $fail++; }

// the role, its steps and its charter
check('gate belongs to the Project Manager', TeamRunner::STEPS['gate'][0] === 'project-manager');
check('stall belongs to the Project Manager', (TeamRunner::STEPS['stall'][0] ?? '') === 'project-manager');
check('stall asks Access for team.stall', (TeamRunner::STEPS['stall'][2] ?? '') === 'team.stall');
$allPropose = true; foreach (array_keys(TeamRunner::CEILINGS) as $c) if ((TeamRunner::STEP_AT['stall'][$c] ?? '') !== 'propose') $allPropose = false;
check('stall only proposes, at every ceiling', $allPropose);
check('stall costs $0', (TeamRunner::STEP_COST_MICROS['stall'] ?? -1) === 0);
$ch = TeamRunner::charter('project-manager');
check('charter has its four grounding parts (0180 §6)', !empty($ch['follows']) && !empty($ch['job']) && !empty($ch['knowledge']) && !empty($ch['escalates']));
check('charter: lane is project-manager, tickets are Work only', ($ch['lane'] ?? '') === 'project-manager' && ($ch['tickets'] ?? []) === ['work']);
check('the lane exists in TeamRoster::LANES', in_array('project-manager', TeamRoster