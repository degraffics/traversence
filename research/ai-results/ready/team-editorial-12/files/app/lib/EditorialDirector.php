<?php
// app/lib/EditorialDirector.php — role 12, the Editorial Director (decisions/0180 §5: Layer 3, reports to the CMO; the
// appendix's row 12). It holds the Editorial lane's agent: its charter (0180 §6's four things), its lane
// (TeamRoster::LANE_ROUTES['editorial-director']), its ticket types, its checks, its one prompt, and its AI access order.
//
// The rules it keeps:
//   - Propose only, at every ceiling. A proposal changes nothing until a person accepts it. An Accept adds the proposed
//     steps to the ticket as that person's own adds, kept in its History. A person's words are never overwritten. No step
//     is ever deleted.
//   - Its ticket types: Work tickets in its own lane at Planning or Building. It never takes an Audit ticket (the IT
//     Manager's) or a Resolution ticket (the Founder's ruling; the Translator drafts those). It never takes a ticket a
//     person has (TeamRunner::mayWork).
//   - AI access order (jason-spec §4; 0180 §6 and its note "the routing rule, cheapest first"):
//       1. code ($0) wherever its checks settle the question;
//       2. the agents' API, for one focused call, only once that budget's own counter is live (0180's note "Separate
//          each": "nothing is spent from (3) until its counter is live"). It is closed in this release;
//       3. a session, for anything code can't settle: words to write. The session gets a lean ask carried as a step
//          (it travels in the System Architect's build brief, TeamRunner::buildBrief). It is never a paid call from here.
//   - $0. STEP_COST_MICROS['editorial'] is 0. Nothing here calls an outside service.
//   - Territory decides review, not the lane. A change that touches one of 0180 §4's territories gets a step that its
//     Tier 4 review is recorded before live. It never publishes as plain Tier 1 or Tier 2.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ControlLog.php';
require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';

final class EditorialDirector
{
    public const SLUG = 'editorial-director';
    public const STEP = 'editorial';
    /** The Access action its step needs, and that action's default rule (Access::canAs), as the other steps carry theirs. */
    public const ACTION = 'team.editorial';
    public const ACTION_DEFAULT = ['min' => 'manager', 'depts' => []];

    /** 0180 §6: each role is grounded in four things, kept on its record. */
    public const CHARTER = [
        'adrs' => [
            'decisions/0180 §1, §4, §5, §6, §7, §8 (five tiers, territories, Layer 3 lanes, lean reasoning, guardrails, the simplicity veto)',
            'decisions/0179 (publish authority; Undo on every agent action)',
            'decisions/0057 (the FAQ grows with every build; trust pages)',
            'decisions/0066 (tribal nations\' land: held names go nowhere)',
            'decisions/0164 (the Knowledge Engine: curators, not controllers; never overwrite what a person entered)',
            'decisions/0058 §11 (the 390px phone rules)',
            'handoffs/piece2/jason-spec.md §4–§7 (internal first, endpoint governance, knowledge to action, departmental logs)',
        ],
        'job' => 'Editorial Director (role 12, Layer 3, reports to the CMO). Owns the Editorial lane: the FAQ and copy, places, listings, guides, journeys, community pages and design. Triages its lane\'s Work tickets (Tier 3), checks each against the house standards before it is built, and holds a screen for clutter or a missing next action, with the Simplicity Guardian (15). Directs Front-End Content Writers (26), Public UI/UX Designers (27) and the Executive Translator (28). Carries the Historian\'s retired duties (0180 appendix).',
        'knowledge' => [
            'Standards, in order: the Founder\'s approved mockup or design decision where one exists; else brand.md, design/css-theme-brand.md, design/table-standard.md and 0058 §11\'s 390px rules (0180 §8). It names the one it used.',
            'Queue: Work tickets whose lane is editorial-director (TeamRoster::lane), at Planning or Building.',
            'Reports: its proposals and their accept or dismiss counts (team_runs, step editorial).',
        ],
        'escalation' => [
            'Another lane\'s work: hand off with a note (GaugeTasks::handOff; Tier 3).',
            'Touches a high-risk territory: its Tier 4 review is recorded before live (0180 §4). It is the CMO\'s for mission, and the CTO\'s for data and routes.',
            'Changes a rule, a tier, a fence or an ADR: a Founder ruling, Tier 5 (0180 §2, §3), drafted by the Translator (28).',
            'A deadlock with another executive line: the CEO first (0180\'s note, 2026-11-27).',
        ],
    ];

    /** Its ticket types: type => the stages it works in. */
    public const TICKETS = ['work' => ['planning', 'building']];

    /** The AI access order: route => [name, when]. */
    public const ACCESS = [
        'code' => ['Code', 'Always first: its checks are rules over the ticket\'s own record ($0).'],
        'api' => ['The agents\' API', 'One focused call, only once the agents\' own API budget is live (decisions/0180, "Separate each").'],
        'session' => ['A session', 'What code can\'t settle (words to write): a lean ask goes with the ticket\'s build brief to the session that builds it.'],
    ];

    public const STANDARDS = 'brand.md, design/css-theme-brand.md, design/table-standard.md and 0058 §11\'s 390px rules';
    /** Words that say a ticket writes words a visitor reads. */
    public const COPY = '/\b(FAQ|copy|wording|microcopy|labels?|error messages?|empty states?|onboarding|headings?)\b/i';
    /** Words that say a ticket changes a screen. */
    public const SCREEN = '/\b(screens?|pages?|layouts?|buttons?|labels?|forms?|empty states?|menus?|cards?)\b/i';
    /** Words that say a ticket handles what a person entered (W0–W15's rule: never overwrite what a person entered). */
    public const PERSON_WORDS = '/\b(guides?|listings?|places?|journeys?|stor(?:y|ies)|reviews?|descriptions?)\b/i';
    /** Words that reach 0066's ground. */
    public const NATIONS_WORDS = '/(\blandmarks?\b|\bheritage\b|\bnations?\b|\btribal\b|\bsacred\b)/i';
    /** The longest a session ask may be (0180 §6: "concise and targeted"). */
    public const ASK_MAX = 900;

    /** Is a path target a page a visitor, member or business sees? */
    public static function isPublicPath(string $v): bool
    {
        $v = (string) (parse_url($v, PHP_URL_PATH) ?? $v); $v = (string) preg_replace('#^(website|sync-storage)/#', '/', $v);
        if ($v === '' || str_starts_with($v, 'app/') || str_starts_with($v, 'workers/') || str_starts_with($v, 'design/')) return false;
        foreach (['/admin', '/api/', '/user/api/', '/admin/api/'] as $x) if (str_starts_with($v, $x)) return false;
        return str_starts_with($v, '/') || str_starts_with($v, 'trvgo/');
    }

    /** The ticket's words, for the checks. */
    public static function words(array $t): string
    {
        return implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['in_scope'] ?? ''), (string) ($t['build_brief'] ?? '')]);
    }

    /**
     * The code checks (route 1). Pure: from the ticket row, its targets and its steps. Each is key => [see, do, confirm, source].
     * A check already covered by a step or by Done when isn't proposed again.
     */
    public static function checks(array $t, array $targets, array $steps = []): array
    {
        if (!class_exists('TeamRunner')) require_once __DIR__ . '/TeamRunner.php';
        $words = self::words($t);
        $have = mb_strtolower(implode(' ', array_map(fn ($s) => (string) ($s['see'] ?? '') . ' ' . (string) ($s['do_what'] ?? '') . ' ' . (string) ($s['confirm'] ?? ''), $steps)) . ' ' . (string) ($t['done_when'] ?? ''));
        $public = false;
        foreach ($targets as $tg) { [$k, $v] = array_pad(explode(':', (string) $tg, 2), 2, ''); if ($k === 'path' && self::isPublicPath(trim($v))) { $public = true; break; } }
        $screen = $public || (bool) preg_match(self::SCREEN, $words);
        $territory = TeamRunner::touches($targets);
        $out = [];
        if ($public && !str_contains($have, 'faq'))
            $out['faq'] = ['The FAQ', 'Add or update the FAQ entry for this change, as the visitor sees it; say "coming" where it isn\'t built', 'The entry is in app/ui/faq.md and shows on /faq.php on staging', 'decisions/0057'];
        if ($screen && !str_contains($have, '390'))
            $out['phone'] = ['The phone layout', 'Check the changed screen at 390px wide: nothing cut off, the primary action in reach', 'Seen at 390px on staging, with the standard named', 'decisions/0058 §11'];
        if ($screen && !str_contains($have, 'next action'))
            $out['simplicity'] = ['One screen, one clear next action', 'Read the screen as a first-time visitor: one clear next action, nothing that crowds it', 'The Editorial Director (12) or the Simplicity Guardian (15) has read it on staging', 'decisions/0180 §8'];
        if (preg_match(self::COPY, $words) && !str_contains($have, 'brand.md'))
            $out['voice'] = ['The words', 'Read the new words against ' . self::STANDARDS . ': plain, no jargon, no code words a visitor sees', 'Each new line read aloud on staging; the standard used is named on the ticket', 'decisions/0180 §8'];
        if ($territory !== '' && !str_contains($have, 'tier 4'))
            $out['territory'] = ['A high-risk territory: ' . (GaugeTasks::BOUNDARIES[$territory] ?? $territory), 'Record its Tier 4 review and Prior Ruling Check before live: it never publishes as plain Tier 1 or 2', 'The review is on the ticket, citing the ADR and section it relies on', 'decisions/0180 §4'];
        if (($territory === 'nations' || preg_match(self::NATIONS_WORDS, $words)) && !str_contains($have, 'held'))
            $out['held'] = ['Tribal nations\' land', 'Check no held name or protected location reaches the page (HeldNames, Landmarks::visibleSql)', 'The held-names check passes on staging', 'decisions/0066'];
        if (preg_match(self::PERSON_WORDS, $words) && !str_contains($have, 'person entered'))
            $out['person'] = ['A person\'s words', 'Leave what a person entered as they wrote it: propose, never overwrite', 'No field a person entered changes without their own Edit', 'decisions/0164; decisions/0180 §7'];
        return $out;
    }

    /** Does the ticket need words written (something code can't settle)? */
    public static function needsWords(array $t, array $steps = []): bool
    {
        if (!preg_match(self::COPY, self::words($t))) return false;
        foreach ($steps as $s) if (stripos((string) ($s['do_what'] ?? ''), 'session ask') !== false) return false;
        return true;
    }

    /** The agents' API (route 2): [open, why]. Closed until its own counter is live (0180's note "Separate each"). */
    public static function apiReady(): array
    {
        return [false, 'the agents\' API budget has no live counter yet (decisions/0180, "Separate each": nothing is spent from it until its counter is live)'];
    }

    /** Which route settles this ticket: [route, why]. Code first, then the API, then a session. */
    public static function route(bool $needsWords): array
    {
        if (!$needsWords) return ['code', 'Code settled it: the checks are rules over the ticket\'s own record ($0).'];
        [$open, $why] = self::apiReady();
        if ($open) return ['api', 'One focused call on the agents\' API, within its own cap.'];
        return ['session', 'Code can\'t write the words, and ' . $why . ', so a lean ask goes to the session that builds it.'];
    }

    /**
     * The one prompt: the session ask (0180 §6: "only the change, the areas it touches and the ADR sections it may rely on",
     * answered with a citation). Plain, lean, capped.
     */
    public static function ask(array $t, array $targets, string $territory, array $adrs): string
    {
        $a = 'Session ask (Editorial Director, 12): write the visitor\'s words for task #' . (int) ($t['id'] ?? 0) . ': ' . mb_substr(trim((string) ($t['what'] ?? '')), 0, 200) . '.'
            . ($targets ? ' It touches ' . mb_substr(implode(', ', $targets), 0, 200) . '.' : '')
            . ($territory !== '' ? ' Territory: ' . (GaugeTasks::BOUNDARIES[$territory] ?? $territory) . ' (its Tier 4 review comes first).' : '')
            . ' Rely on ' . ($adrs ? implode(', ', $adrs) : 'decisions/0057 and 0180 §8') . '. Standard: ' . self::STANDARDS . '.'
            . ' Answer with the words, the standard used and the ADR section relied on; never overwrite a person\'s words; held 0066 names go nowhere.';
        return mb_substr($a, 0, self::ASK_MAX);
    }

    /** The whole proposal for a ticket, or [] when there's nothing to propose. */
    public static function proposal(PDO $pdo, array $t): array
    {
        if (!class_exists('TeamRunner')) require_once __DIR__ . '/TeamRunner.php';
        $id = (int) $t['id'];
        $steps = GaugeTasks::steps($pdo, $id);
        $targets = TeamRoster::ticketTargets($t);
        $checks = self::checks($t, $targets, $steps);
        $needs = self::needsWords($t, $steps);
        [$route, $why] = self::route($needs);
        if (!$checks && $route === 'code') return [];
        [$tier, $territory, $flag] = TeamRunner::tier(self::words($t), $targets);
        $out = [];
        foreach ($checks as $k => [$see, $do, $confirm, $src]) $out[] = ['key' => $k, 'see' => $see, 'do' => $do . ' (' . $src . ')', 'confirm' => $confirm];
        if ($route === 'session') {
            $adrs = array_values(array_map(fn ($a) => 'decisions/' . $a['adr'], array_filter(GaugeTasks::adrs($pdo, $id), fn ($a) => $a['adr'] !== 'none')));
            $out[] = ['key' => 'ask', 'see' => 'The words to write', 'do' => self::ask($t, $targets, $territory, $adrs), 'confirm' => 'The words are on staging, read against the standard named'];
        }
        $hash = substr(sha1((string) json_encode([array_column($out, 'key'), $route, $territory, $flag])), 0, 12);
        return ['steps' => $out, 'route' => $route, 'route_why' => $why, 'tier' => $tier, 'territory' => $territory, 'flag' => $flag, 'hash' => $hash];
    }

    /** What the run saw, with the proposal's mark so the same proposal is never made twice. */
    public static function seen(array $p): string { return 'its words, targets and steps against the house standards [ed:' . (string) ($p['hash'] ?? '') . ']'; }

    /** What the run says it did. */
    public static function said(array $p): string
    {
        $k = array_column((array) ($p['steps'] ?? []), 'key'); $n = count($k);
        return 'Proposed ' . $n . ' step' . ($n === 1 ? '' : 's') . ' (' . implode(', ', $k) . '); route: ' . self::ACCESS[$p['route'] ?? 'code'][0]
            . (($p['territory'] ?? '') !== '' ? '; touches ' . (GaugeTasks::BOUNDARIES[$p['territory']] ?? $p['territory']) . ', Tier 4 before live' : '')
            . (($p['flag'] ?? '') !== '' ? '; words suggest ' . (GaugeTasks::BOUNDARIES[$p['flag']] ?? $p['flag']) . ': a person checks' : '');
    }

    /** Does this role's step have work on this ticket? Its own lane, its ticket types, a new proposal. */
    public static function applies(PDO $pdo, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if (!isset(self::TICKETS[$tk]) || !in_array($sg, self::TICKETS[$tk], true)) return false;
        if (TeamRoster::lane($pdo, $t) !== self::SLUG) return false;
        if (!class_exists('TeamRunner')) require_once __DIR__ . '/TeamRunner.php';
        if (TeamRunner::openProposal($pdo, (int) $t['id'], self::STEP)) return false;
        $p = self::proposal($pdo, $t); if (!$p) return false;
        try {   // dismissed or accepted before, unchanged since: never proposed again
            $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = ? AND seen LIKE ? LIMIT 1");
            $st->execute([(int) $t['id'], self::STEP, '%[ed:' . $p['hash'] . ']%']);
            if ($st->fetchColumn()) return false;
        } catch (Throwable $x) {}
        return true;
    }

    /** A person accepts: each proposed step added as their own add (History kept); one already there is left alone. */
    public static function accept(PDO $pdo, int $task, array $p, string $who, string $tz = ''): string
    {
        $norm = fn (string $s) => mb_strtolower(mb_substr(trim((string) preg_replace('/\s+/', ' ', $s)), 0, 600));
        $have = array_map(fn ($s) => $norm((string) $s['do_what']), GaugeTasks::steps($pdo, $task));
        $n = 0; $kept = 0;
        foreach ((array) ($p['steps'] ?? []) as $s) {
            $do = (string) ($s['do'] ?? ''); if (trim($do) === '') continue;
            if (in_array($norm($do), $have, true)) { $kept++; continue; }
            GaugeTasks::stepAdd($pdo, $task, (string) ($s['see'] ?? ''), $do, (string) ($s['confirm'] ?? ''), $who, $tz);
            $have[] = $norm($do); $n++;
        }
        return $n . ' step' . ($n === 1 ? '' : 's') . ' added to task #' . $task . ', kept in its History.' . ($kept ? ' ' . $kept . ' already there, left as it was.' : '');
    }
}
