=== FILE: files/app/lib/TeamContent.php ===
<?php
// app/lib/TeamContent.php: the Front-End Content Writers' agent.
//
// What this role is and where its rules come from:
//   - decisions/0180, appendix row 26. Layer 4, reporting to the Editorial Director (12). It carries the duties of two
//     retired roles: the Regional Directory & Data Curator (14) and the AI & Dynamic Guide Quality Auditor (20).
//   - It is the Data & Content lane's role on the platform (research/team-vetting-2026-11-27.md). Under R2's routes,
//     crawler, W-workflow and jurisdiction work stays in the IT Manager's lane; Data & Content builds that as Build & QA (21),
//     never through this step.
//
// One step, TeamRunner::STEPS['content'], works it:
//   - On a Work ticket at Planning in the Editorial Director's lane, it proposes the goal, scope and Done when that are still
//     empty, from fixed templates by the ticket's kind (FAQ entry, words on a page, guide quality, listing accuracy).
//   - A person accepts the proposal, applied as their Edit with History, or dismisses it.
//   - It never writes over a person's words (0164: "never overwrite what a person entered"; 0180 §7).
//   - It never moves a stage, never takes a ticket, and never touches a high-risk territory (0180 §4: those get their Tier 4
//     review through the lane manager first).
//
// AI access order (0180 §6 and its notes of 2026-11-27; jason-spec §4), cheapest first:
//   1. Code: its scope comes from the templates here, at $0.
//   2. API: closed. The agents' own $40 counter in AiClient isn't live, and nothing is spent from it until it is
//      (0180's correction, 2026-11-27).
//   3. Session: the words themselves are written in a Claude session, through the System Architect's build brief.
//
// Ticket types: no new GaugeTasks type. Its tickets are Work tickets. KINDS below are kinds of Work ticket inside its lane.
declare(strict_types=1);

require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';
require_once __DIR__ . '/TeamRunner.php';

final class TeamContent
{
    /** Its row on the Team. The seed isn't in this release: confirm 0180 row 26's slug. Must equal TeamRunner::STEPS['content'][0]. */
    public const SLUG = 'content-writers';
    public const STEP = 'content';
    /** Its lane: the Editorial Director's (0180 appendix: row 26 reports there; TeamRoster::LANE_ROUTES). */
    public const LANE = 'editorial-director';

    /** Its four grounds (0180 §6), kept with the code so its Team page record and its step say the same. */
    public const CHARTER = [
        'follows' => [
            'decisions/0180 §1, §4, §6, §7 (tiers, the high-risk territories, lean reasoning, people\'s work stays theirs)',
            'decisions/0164 (W0–W15: curators, not controllers; never overwrite what a person entered; nothing private to a free external API)',
            'decisions/0066 (tribal nations\' land: held, never shown, never sent)',
            'decisions/0057 (the FAQ grows with every build)',
            'decisions/0145 §3 (test accounts: views only, never acted through)',
            'handoffs/piece2/jason-spec.md §4–§7 (internal first; endpoint and data governance; knowledge to action; inboxes, logs and reports)',
        ],
        'job' => 'Front-End Content Writers (0180 appendix, row 26), under the Editorial Director. It covers three things:'
            . ' the site\'s words (microcopy, error states, the FAQ);'
            . ' the quality of place and on-demand guides and how-tos (W4–W5, W10–W11);'
            . ' and directory accuracy (W1–W3, W14): no duplicates, closures only once verified, and a person\'s entry never written over.',
        'knows' => 'Its lane\'s Work tickets at Planning (their words, targets, decisions and steps), the kinds below and their templates.',
        'escalates' => 'Anything outside its kinds, or a disagreement with a person\'s entry, goes to its lane manager, the Editorial Director (12).'
            . ' A high-risk territory gets its Tier 4 review through that lane (0180 §4).'
            . ' Any held name goes to the Privacy & 0066 Guards (25).'
            . ' It never goes to the Founder directly: Tier 5 comes only through the CEO\'s brief (0180 §3).',
        'made_of' => 'ai: an AI agent (jason-spec §11 #19). Its Test Editor lens is a view only, never acted through (0180 §7).',
    ];

    /** The kinds of Work ticket it takes in its lane: key => [name, words pattern, page paths]. First match wins: paths, then words. */
    public const KINDS = [
        'faq' => ['FAQ entry', '/\b(faq|frequently asked)\b/i', ['/faq.php', 'app/ui/faq.md']],
        'guide' => ['Guide quality', '/\b(guides?|how-tos?|journeys?|stories|story)\b/i', ['/guide', '/journey', '/explore']],
        'listing' => ['Listing accuracy', '/\b(listings?|duplicates?|closures?|opening hours|directory)\b/i', ['/place', '/listing']],
        'copy' => ['Words on a page', '/\b(copy|wording|microcopy|labels?|typos?|error messages?|empty states?)\b/i', []],
    ];

    /** Its prompts in fixed form (code, $0): kind => field => template. {what} is the ticket's own words, {where} the page it names. */
    public const TEMPLATES = [
        'faq' => [
            'goal' => 'The FAQ answers it, in plain words: {what}',
            'in_scope' => 'One FAQ entry (new or changed) in app/ui/faq.md, shown on /faq.php.',
            'out_scope' => 'How any page works; prices, billing or refund words (the Accounting lane); anything about tribal nations\' land beyond "held and never shown" (the Privacy & 0066 Guards, 25).',
            'done_when' => 'The entry reads on /faq.php as a visitor sees it; anything not built yet says "coming"; it names no person, no held place and no price that isn\'t settled.',
        ],
        'guide' => [
            'goal' => 'A guide a visitor can trust: {what}',
            'in_scope' => 'The guide, journey or how-to on {where}: its words, its sources and its gaps.',
            'out_scope' => 'Writing over a guide or story a person wrote; the guide pipeline\'s code, the W-workflows and the crawler (the IT Manager\'s lane); spend beyond the platform\'s AI budget.',
            'done_when' => 'It shows on staging with its sources named and "then" kept apart from "now"; it names no held place (0066); its remaining gaps are listed on the ticket.',
        ],
        'listing' => [
            'goal' => 'The listing is right: {what}',
            'in_scope' => 'The detail named on {where}, from a source named on the ticket.',
            'out_scope' => 'Merging, deleting or bulk-changing records (a data change: the IT Manager\'s lane and its Tier 4 review); claims and storefronts (the Sales lane); anything the owner entered themselves.',
            'done_when' => 'Staging shows the corrected detail with its source; a closure shows only once verified; nothing a person entered is written over (a disagreement stays a ticket).',
        ],
        'copy' => [
            'goal' => 'Plain words on {where}: {what}',
            'in_scope' => 'The words named on the ticket, on {where}, and their FAQ entry if one changes.',
            'out_scope' => 'The page\'s layout and behaviour (the Public UI/UX Designers, 27, and Build & QA, 21); a member\'s or business\'s own words.',
            'done_when' => 'The new words show at phone (390px) and desktop sizes, the screen keeps one clear next action, and the old words are in its History.',
        ],
    ];

    /**
     * The lean ask for a session (0180 §6: only the change, what it touches and the decisions it may rely on, never the whole
     * history). It is carried on the proposal for the build brief. It is never sent from here.
     */
    public const SESSION_ASK = 'As the Front-End Content Writers (decisions/0180 row 26), for task #{id} only: {what}. It touches: {targets}. Rely only on: {adrs}. Answer with the exact words to change, where, and the decision you rely on; if no decision settles it, say so. Name no held place (0066); rewrite nothing a person wrote.';

    /** Word flags that stop it: a person checks these first (TeamRunner::tier's third value, or the ticket's own flag). */
    public const STOP_FLAGS = ['money', 'privacy', 'nations', 'mission', 'deadlock'];

    public const API_CLOSED = 'Closed: the agents\' own $40 API counter isn\'t live in AiClient, and nothing is spent from it until it is (decisions/0180, correction of 2026-11-27).';

    /** The API step of its order: closed in this release (see API_CLOSED). */
    public static function apiOpen(): bool { return false; }

    /** Its AI access order for a kind, cheapest first: [[route, why], ...]. Code always settles the scope it proposes. */
    public static function route(string $kind): array
    {
        return [
            ['code', 'Its scope comes from the fixed templates here, at $0 (decisions/0180 §6: internal first).'],
            ['api', self::apiOpen() ? 'Open for one focused call, on the agents\' budget.' : self::API_CLOSED],
            ['session', 'The words themselves (' . strtolower(self::KINDS[$kind][0] ?? 'the change') . ') are written in a Claude session, through the System Architect\'s build brief once this scope is accepted and the gate passes.'],
        ];
    }

    /** The kind of a ticket from its targets (paths first) and words; '' when it isn't one of its kinds. */
    public static function kindOf(string $text, array $targets): string
    {
        foreach ($targets as $tg) {
            [$k, $v] = array_pad(explode(':', (string) $tg, 2), 2, ''); $v = trim($v);
            if ($k !== 'path' || $v === '') continue;
            $p = parse_url($v, PHP_URL_PATH); $v = is_string($p) ? $p : $v; $v = (string) preg_replace('#^(website|sync-storage)/#', '/', $v);
            foreach (self::KINDS as $kind => [, , $paths]) foreach ($paths as $x)
                if ($v === $x || str_starts_with($v, $x) || str_starts_with(ltrim($v, '/'), ltrim($x, '/'))) return $kind;
        }
        foreach (self::KINDS as $kind => [, $re]) if (preg_match($re, $text)) return $kind;
        return '';
    }

    /** The page a ticket names, for the templates. */
    public static function where(array $targets): string
    {
        foreach ($targets as $tg) if (str_starts_with((string) $tg, 'path:') && trim(substr((string) $tg, 5)) !== '') return trim(substr((string) $tg, 5));
        return 'the page it names';
    }

    /**
     * Its checks on one ticket, with no database: $lane from TeamRoster::lane, $targets from TeamRoster::ticketTargets.
     * Returns:
     *   - kind: one of KINDS' keys, or '';
     *   - checks: [[name, passed, why not], ...];
     *   - fields: the fields it would propose;
     *   - ok: true only when every check passes and there is something to propose.
     */
    public static function assess(array $t, string $lane, array $targets): array
    {
        $text = trim(implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? '')]));
        $c = [];
        $isWork = (string) ($t['ticket'] ?? 'work') === 'work' && (string) ($t['stage'] ?? '') === 'planning';
        $c[] = ['A Work ticket at Planning', $isWork, $isWork ? '' : 'It only proposes scope before Building.'];
        $inLane = $lane === self::LANE;
        $c[] = ['In the Editorial Director\'s lane', $inLane, $inLane ? '' : 'Its lane is ' . (TeamRoster::LANE_NAMES[$lane] ?? $lane) . '\'s, not this role\'s.'];
        $terr = TeamRunner::touches($targets);
        $c[] = ['Touches no high-risk territory', $terr === '', $terr === '' ? '' : 'It touches ' . lcfirst(GaugeTasks::BOUNDARIES[$terr] ?? $terr) . ': its Tier 4 review comes first (decisions/0180 §4), and its scope is the lane manager\'s.'];
        $flag = trim((string) ($t['flag'] ?? '')); $words = TeamRunner::tier($text, [])[2];
        $stop = $flag !== '' ? $flag : (in_array($words, self::STOP_FLAGS, true) ? $words : '');
        $c[] = ['No flag for a person to check', $stop === '', $stop === '' ? '' : 'Its words suggest ' . lcfirst(GaugeTasks::BOUNDARIES[$stop] ?? $stop) . ': a person checks it first.'];
        $kind = self::kindOf($text, $targets);
        $c[] = ['One of its kinds', $kind !== '', $kind !== '' ? '' : 'Not an FAQ, words, guide or listing ticket: another role\'s (design is the Public UI/UX Designers\', 27).'];
        $fields = [];
        $pass = !in_array(false, array_column($c, 1), true);
        if ($pass) {
            $vars = ['{what}' => mb_substr(trim((string) ($t['what'] ?? '')), 0, 300), '{where}' => self::where($targets)];
            foreach (self::TEMPLATES[$kind] as $k => $tpl) if (trim((string) ($t[$k] ?? '')) === '') $fields[$k] = mb_substr(strtr($tpl, $vars), 0, in_array($k, ['in_scope', 'out_scope'], true) ? 4000 : 2000);
            $c[] = ['Only what nobody has written', $fields !== [], $fields !== [] ? '' : 'Its goal, scope and Done when are written already: a person\'s words stay theirs.'];
        }
        return ['kind' => $kind, 'checks' => $c, 'fields' => $fields, 'ok' => $pass && $fields !== []];
    }

    /** Has it proposed on this ticket before? Once a ticket: a dismissal is a person's answer, not a reason to ask again hourly. */
    public static function proposedBefore(PDO $pdo, int $id): bool
    {
        try { $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = ? AND state = 'proposed' LIMIT 1"); $st->execute([$id, self::STEP]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return false; }
    }

    /** Does it have work on this ticket (TeamRunner::applies)? */
    public static function wants(PDO $pdo, array $t): bool
    {
        if (self::proposedBefore($pdo, (int) $t['id'])) return false;
        try { return self::assess($t, TeamRoster::lane($pdo, $t), TeamRoster::ticketTargets($t))['ok']; } catch (Throwable $x) { return false; }
    }

    /** The lean session ask for this ticket (SESSION_ASK), with the decisions attached to it. */
    public static function sessionAsk(PDO $pdo, array $t, array $targets): string
    {
        $adr = [];
        try { foreach (GaugeTasks::adrs($pdo, (int) $t['id']) as $a) if ((string) $a['adr'] !== 'none') $adr[] = 'decisions/' . $a['adr']; } catch (Throwable $x) {}
        return strtr(self::SESSION_ASK, ['{id}' => (string) (int) $t['id'], '{what}' => mb_substr(trim((string) ($t['what'] ?? '')), 0, 300),
            '{targets}' => $targets ? implode(', ', $targets) : 'nothing named', '{adrs}' => $adr ? implode(', ', $adr) : 'decisions/0180 §6 and 0164 only']);
    }

    /**
     * The step's draft (TeamRunner::run).
     * Returns:
     *   - fields: [] when nothing applies;
     *   - seen, did, why: the run's log words;
     *   - proposal: the fields, plus its kind, checks, route, session ask and fence note, for the person deciding.
     */
    public static function draft(PDO $pdo, array $t): array
    {
        $seen = 'its kind, lane, territory, flags and which fields are still empty';
        $targets = TeamRoster::ticketTargets($t);
        $a = self::assess($t, TeamRoster::lane($pdo, $t), $targets);
        if (!$a['ok']) {
            $no = ['Only what nobody has written', false, 'Nothing to propose.'];
            foreach ($a['checks'] as $x) if (!$x[1]) { $no = $x; break; }
            return ['fields' => [], 'seen' => $seen, 'did' => '', 'why' => $no[0] . ': ' . $no[2], 'proposal' => []];
        }
        $front = []; try { $front = TeamRunner::frontOf($pdo, $t); } catch (Throwable $x) {}
        $proposal = ['fields' => $a['fields'], 'kind' => $a['kind'],
            'checks' => array_map(fn ($x) => $x[0] . ': ' . ($x[1] ? 'clear' : $x[2]), $a['checks']),
            'route' => array_map(fn ($r) => $r[0] . ': ' . $r[1], self::route($a['kind'])),
            'session_ask' => self::sessionAsk($pdo, $t, $targets),
            'fence' => $front ? 'It reaches a public page: while Implementation reaches Backend only, its build waits for the Founder (the fence).' : 'It stays in the backend.'];
        $names = implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($a['fields'])));
        return ['fields' => $a['fields'], 'seen' => $seen, 'did' => 'Proposed ' . count($a['fields']) . ' part' . (count($a['fields']) === 1 ? '' : 's') . ' of its scope (' . self::KINDS[$a['kind']][0] . '): ' . $names, 'why' => '', 'proposal' => $proposal];
    }
}
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-1.php ===
// ANCHOR: replaces     public const STEPS = [ through     ];
    public const STEPS = [
        'gate' => ['project-manager', 'Moves a Work ticket from Planning to Building once its gate passes', 'team.gate', ['min' => 'manager', 'depts' => ['ops']]],
        'handoff' => ['system-architect', 'Writes a Building ticket\'s build brief and hands it to the session manager', 'team.handoff', ['min' => 'staff', 'depts' => ['eng']]],
        'brief' => ['translator', 'Proposes a Resolution brief\'s plain words from its ticket and its decisions', 'team.brief', ['min' => 'staff', 'depts' => ['ops']]],
        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
        // the Front-End Content Writers (decisions/0180 appendix row 26; app/lib/TeamContent.php): a proposal only, never a person's words
        'content' => ['content-writers', 'Proposes an editorial Work ticket\'s goal, scope and Done when from its kind (FAQ, words, guide, listing); a person accepts', 'team.content', ['min' => 'staff', 'depts' => []]],
    ];
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-2.php ===
// ANCHOR: replaces     public const STEP_AT = [ through     ];
    public const STEP_AT = [
        'gate' => ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'handoff' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'brief' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],
        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'content' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],   // words a person may write: always a proposal (0164; 0180 §7)
    ];
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-3.php ===
// ANCHOR: replaces     public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0]; through     public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];
    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0, 'content' => 0];
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-4.php ===
// ANCHOR: replaces     public static function applies(PDO $pdo, string $step, array $t): bool through     }
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && !self::openProposal($pdo, (int) $t['id'], 'gate')
            && (GaugeTasks::gate($pdo, $t) === [] || (self::mode($pdo, 'gate') === 'act' && self::takesItself($pdo, $t) && GaugeTasks::gate($pdo, $t) === ['someone to have it (Take it)']));
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        if ($step === 'content') { if ($tk !== 'work' || $sg !== 'planning') return false; require_once __DIR__ . '/TeamContent.php'; return TeamContent::wants($pdo, $t); }   // the Front-End Content Writers: once a ticket
        return false;
    }
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-5.php ===
// ANCHOR: replaces     public static function run(PDO $pdo, array $t, string $step, array $role): string through     }
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
            if ($step === 'content') {   // the Front-End Content Writers (TeamContent): scope from templates, $0; a proposal only, never a person's words
                require_once __DIR__ . '/TeamContent.php';
                $c = TeamContent::draft($pdo, $t);
                if ($c['fields'] === []) return self::log($pdo, $role, $id, $step, $c['seen'], '', 'failed', $c['why']);
                return self::log($pdo, $role, $id, $step, $c['seen'], $c['did'], 'proposed', '', $c['proposal'], 'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
        } catch (RuntimeException $x) { return self::log($pdo, $role, $id, $step, $name, '', 'failed', $x->getMessage()); }
        return '';
    }
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-6.php ===
// ANCHOR: replaces     public static function accept(PDO $pdo, int $rid, string $who, string $tz = ''): string through     }
    public static function accept(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r || $r['state'] !== 'proposed') throw new RuntimeException('That isn\'t a proposal.');
        if ($r['settled_at'] !== null) throw new RuntimeException('It\'s already ' . ($r['undone_at'] !== null ? 'dismissed' : 'accepted') . '.');
        $p = json_decode((string) $r['proposal'], true) ?: [];
        if ($r['step'] === 'gate') {
            $msg = GaugeTasks::move($pdo, (int) $r['task_id'], (string) ($p['move'] ?? 'building'), $who, 'accepting the Project Manager\'s proposal (run #' . $rid . ')', $tz);
            $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
            return 'Accepted: ' . $msg;
        }
        if ($r['step'] === 'content') {   // the Front-End Content Writers' scope: filled only where still empty, as the accepting person's Edit (History kept)
            $t = GaugeTasks::task($pdo, (int) $r['task_id']); if (!$t) throw new RuntimeException('That task isn\'t in the queue.');
            $in = []; $kept = [];
            foreach ((array) ($p['fields'] ?? []) as $k => $v) { if (!in_array($k, GaugeTasks::SCOPE, true)) continue; if (trim((string) ($t[$k] ?? '')) !== '') { $kept[] = str_replace('_', ' ', (string) $k); continue; } $in[$k] = (string) $v; }
            if ($in) GaugeTasks::edit($pdo, (int) $r['task_id'], $in, $who . ' (accepting ' . TeamRoster::actor(['name' => self::roleName($pdo, (string) $r['role'])]) . ')', $tz);
            $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
            return 'Accepted: ' . count($in) . ' part' . (count($in) === 1 ? '' : 's') . ' of its scope filled, kept in its History.' . ($kept ? ' Left as a person wrote it: ' . implode(', ', $kept) . '.' : '');
        }
        $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = [];
        foreach ($p as $k => $v) { if (trim((string) ($b[$k] ?? '')) !== '') { $kept[] = strtolower(GaugeTasks::BRIEF[$k][0]); continue; } GaugeTasks::briefEdit($pdo, (int) $r['task_id'], $k, (string) $v, $who . ' (accepting ' . TeamRoster::actor(['name' => self::roleName($pdo, (string) $r['role'])]) . ')', $tz); $n++; }
        $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
        return 'Accepted: ' . $n . ' part' . ($n === 1 ? '' : 's') . ' of the brief filled, kept in its History.' . ($kept ? ' Left as a person wrote it: ' . implode(', ', $kept) . '.' : '');
    }
=== END FILE ===
=== FILE: tests/team-content-tests.php ===
<?php
// tools/team-tests/team-content-tests.php: the Front-End Content Writers' step (TeamContent and TeamRunner's 'content').
// Plain PHP with no database: every check here is a pure function. Prints PASS or FAIL per check, and exits 1 on any FAIL.
// Nothing is written, nothing is sent, and no money moves.
declare(strict_types=1);

$root = '';
foreach ([dirname(__DIR__), dirname(__DIR__, 2), getcwd()] as $r) if (is_file($r . '/app/lib/TeamContent.php')) { $root = $r; break; }
if ($root === '') { echo "FAIL setup: app/lib/TeamContent.php not found (run from the site root or tools/team-tests/)\n"; exit(1); }
require_once $root . '/app/lib/TeamContent.php';

$bad = 0;
function check(string $name, bool $pass, string $detail = ''): void
{
    global $bad; if (!$pass) $bad++;
    echo ($pass ? 'PASS ' : 'FAIL ') . $name . (!$pass && $detail !== '' ? ' (' . $detail . ')' : '') . "\n";
}
function tk(array $over = []): array
{
    return $over + ['id' => 1, 'ticket' => 'work', 'stage' => 'planning', 'what' => 'Add an FAQ entry on saved trips', 'why' => 'Members ask how saving works',
        'goal' => '', 'in_scope' => '', 'out_scope' => '', 'done_when' => '', 'flag' => '', 'build_brief' => '', 'owner' => '', 'source' => ''];
}

// the step's wiring in TeamRunner
check('step exists in STEPS', isset(TeamRunner::STEPS['content']));
check('step slug matches TeamContent::SLUG', (TeamRunner::STEPS['content'][0] ?? '') === TeamContent::SLUG);
$at = TeamRunner::STEP_AT['content'] ?? [];
check('STEP_AT covers every ceiling', array_keys($at) === array_keys(TeamRunner::CEILINGS));
check('never acts, only proposes', $at !== [] && array_unique(array_values($at)) === ['propose']);
check('costs $0', (TeamRunner::STEP_COST_MICROS['content'] ?? -1) === 0);
check('joins the decision tree rules (fingerprint changes: Founder re-approval)', isset(TeamRunner::ruleData()['steps']['content']));
echo 'INFO new rule fingerprint (no reach): ' . substr(TeamRunner::fingerprint(), 0, 12) . "\n";

// its lane routing (TeamRoster::LANE_ROUTES, unchanged)
check('the FAQ routes to the Editorial Director', TeamRoster::laneOf(['path:/faq.php'], 'FAQ') === 'editorial-director');

// its checks and proposals
$a = TeamContent::assess(tk(), 'editorial-director', ['path:/faq.php']);
check('FAQ ticket: kind faq', $a['kind'] === 'faq', $a['kind']);
check('FAQ ticket: proposes all four empty fields', $a['ok'] && array_keys($a['fields']) === ['goal', 'in_scope', 'out_scope', 'done_when'], json_encode(array_column($a['checks'], 2)));
check('FAQ Done when says coming', str_contains($a['fields']['done_when'] ?? '', '"coming"'));
$a = TeamContent::assess(tk(['goal' => 'A person wrote this goal']), 'editorial-director', ['path:/faq.php']);
check('a person\'s field is never proposed', $a['ok'] && !isset($a['fields']['goal']));
$a = TeamContent::assess(tk(['goal' => 'g', 'in_scope' => 'i', 'out_scope' => 'o', 'done_when' => 'd']), 'editorial-director', ['path:/faq.php']);
check('all written: nothing to propose', !$a['ok'] && $a['fields'] === []);
check('a territory target stops it', !TeamContent::assess(tk(), 'editorial-director', ['path:/order.php'])['ok']);
check('a stored flag stops it', !TeamContent::assess(tk(['flag' => 'address']), 'editorial-director', ['path:/faq.php'])['ok']);
check('nations words stop it', !TeamContent::assess(tk(['what' => 'Fix the wording on the sacred site notice']), 'editorial-director', [])['ok']);
check('another lane stops it', !TeamContent::assess(tk(), 'it-manager', ['path:/faq.php'])['ok']);
check('Building stage stops it', !TeamContent::assess(tk(['stage' => 'building']), 'editorial-director', ['path:/faq.php'])['ok']);
check('a design-only ticket is not its kind', TeamContent::kindOf('Redesign the hero layout', []) === '');
check('a listing path is listing', TeamContent::kindOf('fix it', ['path:/place/123']) === 'listing');
check('a guide word is guide', TeamContent::kindOf('The Show Low guide misses its sources', []) === 'guide');

// AI access order: code, then API, then session
$r = TeamContent::route('guide');
check('route order is code, api, session', array_column($r, 0) === ['code', 'api', 'session']);
check('API closed until the agents\' counter is live', TeamContent::apiOpen() === false && $r[1][1] === TeamContent::API_CLOSED);
check('session ask template stays lean (under 600 chars)', mb_strlen(TeamContent::SESSION_ASK) < 600);

echo ($bad === 0 ? 'ALL PASS' : $bad . ' FAILED') . "\n";
exit($bad === 0 ? 0 : 1);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
### Who writes the words on Traversence's pages, like this FAQ? *(coming)*

Our team writes them, and a person approves each change. Here is how it works:

- Our team includes AI agents. When a page's wording, an FAQ entry, a guide or a listing needs fixing, the content writing agent suggests what the fix should cover and how we'll know it's done.
- A person on our team accepts or dismisses each suggestion.
- The new words are checked on our test site, at phone and desktop sizes, before they reach you.
- Nothing a person wrote is ever written over. That covers a business owner's listing and a member's guide or story. Where we disagree with a person's entry, it becomes a note for a person to look at.

### Do you use AI to write guides and check listings? *(coming)*

Sometimes, and always in this order:

- **Fixed templates and our own records first.** These cost nothing.
- **Paid AI only within the small budgets we set.** It stops when a budget is reached.

A person reviews the result before it's published.

Some things are never sent to any AI service:

- private details;
- the names of tribal nations' places we hold back from the map.

A listing shows a place as closed only once the closure is verified.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "team-content-writers",
  "gate": "decisions/0181 launch gates: guides are named as gate 3's critical path in 0180's budget notes. 0181 itself is not shown; the gate is unconfirmed.",
  "what": "The agent for the Front-End Content Writers role (0180 row 26), which is the Data & Content lane's role on the platform. It adds one $0 step, 'content', that only ever proposes. On an unowned or role-held Work ticket at Planning in the Editorial Director's lane, it proposes the goal, in scope, out of scope and Done when that are still empty. Its kinds are FAQ entry, words on a page, guide quality and listing accuracy, each with fixed templates. Its checks: lane, stage, no high-risk territory, no word flag, never a person's words, and a fence note. It proposes once per ticket. A person's Accept applies the proposal as their Edit with History. Its AI access order is recorded on every proposal: code, then API (closed until the agents' $40 counter is live), then a session through the System Architect's build brief. Ticket types are unchanged: its tickets are Work tickets, and KINDS classify them within its lane.",
  "files": [
    "files/app/lib/TeamContent.php",
    "files/app/lib/TeamRunner.php.region-1.php",
    "files/app/lib/TeamRunner.php.region-2.php",
    "files/app/lib/TeamRunner.php.region-3.php",
    "files/app/lib/TeamRunner.php.region-4.php",
    "files/app/lib/TeamRunner.php.region-5.php",
    "files/app/lib/TeamRunner.php.region-6.php",
    "tests/team-content-tests.php",
    "faq/faq-entries.md"
  ],
  "decisions": [
    "0180 §1, §2, §3, §4, §5, §6, §7, appendix row 26, and its notes of 2026-11-26/27 (routing cheapest first; separate budgets; agents' API counter not live)",
    "0179 (proposals, Undo, the tree fingerprint, the fence)",
    "0164 (W0–W15 rules: never overwrite what a person entered; nothing private to free APIs)",
    "0066 (held names never shown or sent)",
    "0057 (the FAQ grows with every build)",
    "0145 §3 (test accounts are views only)",
    "0165 (staging only)",
    "handoffs/piece2/jason-spec.md §4–§7"
  ],
  "tests": [
    "tests/team-content-tests.php: place it as tools/team-tests/team-content-tests.php and run `php tools/team-tests/team-content-tests.php`. It is pure, with no database, and prints PASS/FAIL per check."
  ],
  "risks": [
    "Nothing here was run, linted, tested or zip-checked. It is a draft for the lane session to verify.",
    "Adding the 'content' step changes TeamRunner::ruleData(), so the tree fingerprint changes. On deploy, treeApproval() clears the approval and drops every agent to Planning. Per 0180 §2 this is a decision-tree rule change, a Tier 5 Founder ruling. Per 0180's one-time-setup note, re-approval must be the Founder's own press or written words, never carried over. Ship only with that ruling in hand.",
    "If the role's seeded slug is not 'content-writers', TeamRoster::doer() finds no row and the step silently never runs. Nothing breaks, but the role does nothing.",
    "The role's acts_level/acts_dept are not set here. TeamRoster::can() refuses until the Founder grants permissions (0180 §2), and every attempt logs a 'refused' run.",
    "Almost all content work reaches public pages. With reach at Backend only, its eventual builds wait for the Founder at the fence. This step only proposes scope, so it is not blocked, but the gate's deliverable still needs the Founder's reach decision or per-ticket release.",
    "The word-flag stop is conservative. Common words such as 'personal', 'private', 'orders' or 'prices' make it skip a ticket and leave it for a person.",
    "The Work queue page that renders proposals is not shown. A 'content' proposal is shaped {fields, kind, checks, route, session_ask, fence}, not brief fields, so the page may mis-render it until it has a 'content' case.",
    "The reconcile file (rec-j18-team.md) is empty: all 18 pass sections have no content. No team-pass finding informed this release; it rests only on 0180, the spec and the live code."
  ],
  "verify": [
    "Several roles touch the same functions. TeamRunner STEPS, STEP_AT, STEP_COST_MICROS, applies(), run() and accept() are edited by every role release that adds a step. Merge these regions by hand with any other role's pending version, keeping each step's branch. ruleData()/fingerprint() are shared consequences.",
    "TeamRunner.php was judged over about 60 KB, so it is delivered as regions (regions: true). Each region file is a fragment with no <?php tag. The ANCHOR line is not part of the replacement. Where the last anchor line is '    }' or '    ];', it means the first such line after the anchor's first line.",
    "GaugeTasks.php and TeamRoster.php are unchanged. The role reuses Work tickets, GaugeTasks::edit/SCOPE/adrs/BOUNDARIES, TeamRoster::lane/ticketTargets/LANE_ROUTES/LANE_NAMES and TeamRunner::touches/tier/frontOf as they stand.",
    "The seed for 0180 row 26 is not shown (file not shown). Confirm its slug equals TeamContent::SLUG and STEPS['content'][0] ('content-writers'), its reports_to (editorial-director) and that it is active.",
    "app/admin/Access.php is not shown. Confirm 'team.content' is acceptable as an Access action with default ['min' => 'staff', 'depts' => []], and that an empty depts list means any department.",
    "The lane and handoff columns on control_gauge_tasks come from R2's migration, held on claude/r2-design (file not shown). Without them TeamRoster::lane() routes by words and paths, which still works.",
    "Lane scope: the vetting brief's option A gives the Data & Content session the Editorial (12) and Marketing (13) lanes. This step serves only the Editorial Director's lane, because the Marketing lane's specialists are roles 29–30. Confirm, or add marketing kinds in a later release.",
    "Not shown, and not called directly: AiClient (the agents' $40 counter; the API stays closed in code), HeldNames (held-name checks are left to the nations territory and word stops), 0181, Database.php, Staging.php, Switches.php and the Work queue page.",
    "Confirm app/ui/faq.md is the FAQ source, as named in TeamRoster::LANE_ROUTES, before the faq template's in_scope is relied on."
  ],
  "regions": true
}
=== END FILE ===