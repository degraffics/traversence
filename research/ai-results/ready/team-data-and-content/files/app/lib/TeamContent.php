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
