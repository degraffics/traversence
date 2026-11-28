=== FILE: files/app/lib/EditorialDirector.php ===
<?php
// app/lib/EditorialDirector.php — the Editorial Director's agent (decisions/0180 §5, Layer 3, role 12; reports to the CMO (7);
// Front-End Content Writers (26), Public UI/UX Designers (27) and the Executive Translator (28) report here; it carries the
// retired Historian's (9) duties, per 0180's appendix). DRAFT: written for the lane session to verify, test and zip-check.
//
// What it does, and only this (0180 §6 "keep the reasoning lean"; jason-spec §4 "internal systems first"):
//   - It works Work tickets in its own lane (TeamRoster::LANE_ROUTES['editorial-director'], R2's routes), at Planning or
//     Building. It reads the ticket's own record and proposes the checks its lane owes as steps (see, do, confirm). Those
//     checks are the FAQ, plain words, one clear next action, the 390px phone, the standard it's checked against, "then"
//     and "now", and a high-risk territory's Tier 4 review.
//   - It only ever proposes. A person's Accept adds the steps (GaugeTasks::stepAdd, additive: nobody's words are written
//     over). A Dismiss stands: it proposes once per ticket.
//   - It never rules on a high-risk territory (that's Tier 4, 0180 §4) and never drafts a Founder ruling (the Translator's
//     brief step does that).
//   - AI access order, cheapest first (0180's note to §6, "the routing rule"): code ($0), then the API for a bounded call,
//     then a session for multi-step reasoning.
//     The agents' API counter (0180's correction "Separate each", budget 3) isn't live, and "nothing is spent from (3)
//     until its counter is live". So the API tier is closed here and its needs fall to a session.
//     Nothing in this file calls an outside service or paid AI.
declare(strict_types=1);

require_once __DIR__ . '/GaugeTasks.php';
require_once __DIR__ . '/TeamRoster.php';

final class EditorialDirector
{
    public const SLUG = 'editorial-director';
    public const STEP = 'editorial';

    /** Its charter: the four things 0180 §6 grounds every role in. Words for the Team page; the runner's rules live in TeamRunner::STEPS. */
    public const CHARTER = [
        'follows' => 'decisions/0180 (§5 its lane; §8 the simplicity veto and the fidelity fallback; §7 people\'s words stay theirs); 0057 (the FAQ grows with every build); 0058 §11 (390px); 0066 (held names never shown); 0164 (nothing private to a free external API); brand.md, design/css-theme-brand.md, design/table-standard.md',
        'job' => 'Owns the Editorial lane: places, listings, guides, journeys, the FAQ and copy, and design. Makes sure every change in it carries its FAQ entry, plain words, one clear next action, a 390px check and a named standard. Holds a screen for clutter with the Simplicity Guardian (15). Carries the Historian\'s "then" and "now".',
        'knows' => 'The Work queue\'s tickets in its lane, their steps and decisions; TeamRoster::LANE_ROUTES; TeamRunner::TERRITORIES.',
        'escalates' => 'Hand-offs between lanes are Tier 3: its own call (GaugeTasks::handOff). A change that touches a high-risk territory gets the CMO\'s or the owning executive\'s Tier 4 review (0180 §4). A deadlock goes to the CEO (0180\'s note, 2026-11-27). Founder rulings (Tier 5) only on 0180 §3\'s triggers.',
    ];

    /** The ticket types it works: key => what it does with them ('' = never). */
    public const TICKETS = [
        'work' => 'At Planning or Building, in its own lane: proposes its editorial checks as steps',
        'audit' => '',        // the daily audit is the IT Manager's lane (TeamRoster::lane: audits are it-manager)
        'resolution' => '',   // a Founder ruling's brief is the Translator's (28) to draft, the Founder's to answer
    ];
    /** The stages it proposes at (its checks are confirmed at Confirmation, as steps). */
    public const STAGES = ['planning', 'building'];

    /** The AI access order (0180's note to §6): each tier, cheapest first, with what it is for. */
    public const ACCESS_ORDER = [
        'code' => 'Fixed-form checks over our own records, at $0 (0180 §6; jason-spec §4.1)',
        'api' => 'A bounded call with a small, known context (a copy draft), on the agents\' own $40 counter through AiClient::allow(): closed until that counter is live (0180, "Separate each")',
        'session' => 'Multi-step reasoning in a Claude session (the Founder\'s Max plan): the simplicity veto\'s judgement on a rendered screen',
    ];
    /** What each need starts at; a closed tier passes it on to the next. */
    public const NEEDS = ['checklist' => 'code', 'draft' => 'api', 'veto' => 'session'];

    private const HISTORY = '/\b(history|historic|heritage|stor(y|ies)|journeys?|seasons?|seasonal|then and now|lineage)\b/i';
    private const SCREEN_WORDS = '/\b(page|screen|button|label|FAQ|empty state|error message|layout)\b/i';

    /**
     * The checks: key => [label, when it applies, a marker that says it's already there, see, do, confirm].
     * When: always (in its lane) | public (a page someone outside the admin sees) | history | territory | nations.
     */
    public const CHECKS = [
        'faq' => ['The FAQ', 'public', '/\bFAQ\b/i', 'What a visitor, member or business sees changes',
            'Add or change its FAQ entry as the visitor sees it, "coming" where it isn\'t built yet (decisions/0057: the FAQ grows with every build)', 'Its FAQ entry reads true on /faq.php'],
        'plain_words' => ['Plain words', 'always', '/plain words|jargon/i', 'Every new word: labels, errors, empty states, emails',
            'Read every new word as the person who sees it: plain, no jargon, no raw codes or stack traces (jason-spec §2, §6; 0180 role 26)', 'No jargon or raw codes left'],
        'next_action' => ['One clear next action', 'public', '/next action/i', 'The screen as it renders on staging',
            'Check the screen keeps one clear next action and adds no clutter; if not, the Editorial Director or the Simplicity Guardian holds it with the reason (decisions/0180 §8, the simplicity veto)', 'One clear next action on the staging screen, or a hold ticket with its reason'],
        'phone' => ['The 390px phone', 'public', '/\b390\b/', 'The screen at 390px wide',
            'Check it at 390px: nothing cut off, no sideways scroll, the primary action in reach (decisions/0058 §11)', 'It reads and works at 390px'],
        'standard' => ['Its standard', 'public', '/mockup|brand\.md|css-theme-brand|table-standard/i', 'What it\'s checked against',
            'Name the standard it\'s checked against: the Founder\'s approved mockup where one exists, else brand.md, design/css-theme-brand.md, design/table-standard.md (decisions/0180 §8)', 'The check names the standard it used'],
        'then_now' => ['"Then" and "now"', 'history', '/"then"|\bthen and now\b|then.{0,20}now/i', 'Words about a place\'s past, a story or a journey',
            'Keep "then" apart from "now": what was is never written as what is (the Historian\'s duty, carried here by decisions/0180\'s appendix)', 'Past and present are told apart'],
        'held' => ['Held names', 'nations', '/held names|\b0066\b/i', 'Any word or map that could name a protected place',
            'Check that no held name or protected location shows in any word, link or image (decisions/0066)', 'No held name or protected location shows'],
        'tier4' => ['Its Tier 4 review', 'territory', '/Tier 4/i', 'It touches a high-risk territory: {territory}',
            'Wait for its Tier 4 review and Prior Ruling Check before live ({territory}; decisions/0180 §4): the Editorial Director doesn\'t rule on it', 'The Tier 4 review is recorded on the ticket'],
    ];

    /** The words a ticket carries: its own fields and its steps (not "out of scope", which names what it won't do). */
    public static function words(array $t, array $steps = []): string
    {
        $w = [];
        foreach (['what', 'why', 'goal', 'in_scope', 'done_when', 'build_brief'] as $k) $w[] = (string) ($t[$k] ?? '');
        foreach ($steps as $s) $w[] = (string) ($s['see'] ?? '') . ' ' . (string) ($s['do_what'] ?? '') . ' ' . (string) ($s['confirm'] ?? '');
        return implode("\n", $w);
    }

    /** Does it reach a screen someone outside the admin sees? By its paths first; else by its words. */
    public static function isPublic(array $targets, string $text): bool
    {
        foreach ($targets as $tg) {
            [$k, $v] = array_pad(explode(':', (string) $tg, 2), 2, ''); $v = trim($v);
            if ($k !== 'path' || $v === '') continue;
            $v = (string) preg_replace('#^(website|sync-storage)/#', '/', $v);
            foreach (['/admin', 'admin/', 'app/', 'workers/', '/api/'] as $back) if (str_starts_with($v, $back)) continue 2;
            return true;
        }
        return (bool) preg_match(self::SCREEN_WORDS, $text);
    }

    /**
     * The checks still missing, in code (no DB): each [key, label, see, do, confirm]. One whose marker is already in its words
     * isn't proposed again. $territory: TeamRunner::touches(); $territoryName: its words (GaugeTasks::BOUNDARIES).
     */
    public static function checklist(array $t, array $steps, array $targets, string $territory = '', string $territoryName = ''): array
    {
        $text = self::words($t, $steps); $public = self::isPublic($targets, $text); $out = [];
        $name = $territoryName !== '' ? $territoryName : $territory;
        foreach (self::CHECKS as $k => [$label, $when, $marker, $see, $do, $confirm]) {
            $on = match ($when) {
                'always' => true, 'public' => $public, 'history' => (bool) preg_match(self::HISTORY, $text),
                'territory' => $territory !== '', 'nations' => $territory === 'nations', default => false,
            };
            if (!$on || preg_match($marker, $text)) continue;
            $out[] = ['key' => $k, 'label' => $label, 'see' => str_replace('{territory}', $name, $see), 'do' => str_replace('{territory}', $name, $do), 'confirm' => $confirm];
        }
        return $out;
    }

    /** Is the agents' API counter live? Not yet: the AI items release (IT lane, the CFO's Tier 4) builds it. Until then, false. */
    public static function agentsApiLive(): bool { return false; }

    /** Where a need goes, cheapest first: [tier, why]. A closed tier passes it on; an unknown need goes to a session, never a guess. */
    public static function route(string $need, ?bool $apiLive = null): array
    {
        $first = self::NEEDS[$need] ?? '';
        if ($first === 'code') return ['code', 'Settled in code, at $0 (decisions/0180 §6; jason-spec §4.1: internal first).'];
        if ($first === 'api') {
            if ($apiLive ?? self::agentsApiLive()) return ['api', 'A bounded call on the agents\' $40 counter, through AiClient::allow() (decisions/0180, "Separate each").'];
            return ['session', 'The agents\' API counter isn\'t live, and nothing is spent from it until it is (decisions/0180, "Separate each"): a session takes it.'];
        }
        if ($first === 'session') return ['session', 'It needs judgement on a rendered screen: a Claude session, within the Founder\'s plan (decisions/0180 §6).'];
        return ['session', 'Code can\'t settle it and it isn\'t a known bounded call: a session takes it, no silent guesswork (jason-spec §4.5).'];
    }

    /**
     * The session's prompt: lean and targeted (0180 §6). It carries only the change, what it touches and the decisions it may
     * rely on. It asks for yes or no with a citation, never the whole history.
     */
    public static function sessionPrompt(array $t, array $targets, string $territory, string $need = 'veto'): string
    {
        $L = [];
        $L[] = 'You are the Editorial Director (decisions/0180 role 12, Layer 3, reporting to the CMO).';
        $L[] = 'Task #' . (int) ($t['id'] ?? 0) . ': ' . mb_substr(trim((string) ($t['what'] ?? '')), 0, 300);
        if (trim((string) ($t['goal'] ?? '')) !== '') $L[] = 'Goal: ' . mb_substr(trim((string) $t['goal']), 0, 300);
        $L[] = 'Touches: ' . ($targets ? mb_substr(implode(', ', $targets), 0, 300) : 'no file or page named');
        if ($territory !== '') $L[] = 'High-risk territory: ' . $territory . '. Don\'t rule on it: its Tier 4 review is an executive\'s (decisions/0180 §4).';
        if ($need === 'veto') {
            $L[] = 'Rely only on: decisions/0180 §8 (the simplicity veto; the fidelity fallback), 0058 §11 (390px), 0057 (the FAQ).';
            $L[] = 'Question: on the staging screen for this change, is there one clear next action, with no added clutter, against its named standard?';
            $L[] = 'Answer yes or no, citing the decision and section. If no, give the hold\'s reason in one sentence.';
        }
        $L[] = 'Never write over a person\'s words. Never name a place decisions/0066 holds.';
        return mb_substr(implode("\n", $L), 0, 1500);
    }

    /** Has it proposed on this ticket before? A Dismiss stands: it proposes once per ticket. */
    public static function proposedBefore(PDO $pdo, int $task): bool
    {
        try { $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = 'editorial' LIMIT 1"); $st->execute([$task]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return false; }
    }

    /** Its proposal for a ticket: the missing checks as steps, how they were settled, and the veto's session prompt for a public screen. */
    public static function proposal(PDO $pdo, array $t): array
    {
        if (!class_exists('TeamRunner')) require_once __DIR__ . '/TeamRunner.php';
        $steps = GaugeTasks::steps($pdo, (int) $t['id']);
        $targets = TeamRoster::ticketTargets($t);
        $b = TeamRunner::touches($targets);
        $out = ['steps' => self::checklist($t, $steps, $targets, $b, GaugeTasks::BOUNDARIES[$b] ?? $b), 'lane' => self::SLUG, 'route' => self::route('checklist')];
        if (self::isPublic($targets, self::words($t, $steps))) {
            [$tier, $why] = self::route('veto');
            $out['veto'] = ['tier' => $tier, 'why' => $why, 'prompt' => self::sessionPrompt($t, $targets, $b, 'veto')];
        }
        return $out;
    }

    /** Does its step have work on this ticket? A Work ticket at Planning or Building, in its lane, never proposed on, with a check missing. */
    public static function applies(PDO $pdo, array $t): bool
    {
        if ((string) ($t['ticket'] ?? 'work') !== 'work' || !in_array((string) ($t['stage'] ?? ''), self::STAGES, true)) return false;
        if (TeamRoster::lane($pdo, $t) !== self::SLUG) return false;
        if (self::proposedBefore($pdo, (int) $t['id'])) return false;
        return self::proposal($pdo, $t)['steps'] !== [];
    }
}
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-1.php ===
<?php
// ANCHOR: replaces `    public const STEPS = [` through `    ];` (the first `    ];` after it, directly below the 'audit' line)
    public const STEPS = [
        // the Editorial Director (decisions/0180 role 12; app/lib/EditorialDirector.php). It comes first, so its checks are
        // proposed on a Planning ticket in its lane before the gate moves the ticket on.
        'editorial' => ['editorial-director', 'Proposes its lane\'s editorial checks (the FAQ, plain words, one clear next action, 390px, its standard, Tier 4) as steps on a Work ticket', 'team.editorial', ['min' => 'manager', 'depts' => []]],
        'gate' => ['project-manager', 'Moves a Work ticket from Planning to Building once its gate passes', 'team.gate', ['min' => 'manager', 'depts' => ['ops']]],
        'handoff' => ['system-architect', 'Writes a Building ticket\'s build brief and hands it to the session manager', 'team.handoff', ['min' => 'staff', 'depts' => ['eng']]],
        'brief' => ['translator', 'Proposes a Resolution brief\'s plain words from its ticket and its decisions', 'team.brief', ['min' => 'staff', 'depts' => ['ops']]],
        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
    ];
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-2.php ===
<?php
// ANCHOR: replaces `    public const STEP_AT = [` through `    ];` (the first `    ];` after it, directly below the 'audit' line)
    public const STEP_AT = [
        'editorial' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],   // it only proposes: a person's Accept adds its steps
        'gate' => ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'handoff' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'brief' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],
        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
    ];
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-3.php ===
<?php
// ANCHOR: replaces `    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];` through `    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];`
    public const STEP_COST_MICROS = ['editorial' => 0, 'gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-4.php ===
<?php
// ANCHOR: replaces `    public static function applies(PDO $pdo, string $step, array $t): bool` through `    }` (the first `    }` after it, directly below `        return false;`)
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && !self::openProposal($pdo, (int) $t['id'], 'gate')
            && (GaugeTasks::gate($pdo, $t) === [] || (self::mode($pdo, 'gate') === 'act' && self::takesItself($pdo, $t) && GaugeTasks::gate($pdo, $t) === ['someone to have it (Take it)']));
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        if ($step === 'editorial') { require_once __DIR__ . '/EditorialDirector.php'; return EditorialDirector::applies($pdo, $t); }   // decisions/0180 role 12: its lane's Work tickets, once each
        return false;
    }
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-5.php ===
<?php
// ANCHOR: replaces `    public static function run(PDO $pdo, array $t, string $step, array $role): string` through `    }` (the first `    }` at four spaces after it, directly below `        return '';`)
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
            if ($step === 'editorial') {   // the Editorial Director (decisions/0180 role 12): only ever proposes; a person's Accept adds its checks as steps
                require_once __DIR__ . '/EditorialDirector.php';
                $p = EditorialDirector::proposal($pdo, $t);
                if (!$p['steps']) return $who . ' · task #' . $id . ' · nothing to propose: its editorial checks are already in its steps';
                return self::log($pdo, $role, $id, $step, 'its lane, its words, its steps and what it touches', 'Proposed ' . count($p['steps']) . ' editorial check' . (count($p['steps']) === 1 ? '' : 's') . ' as steps: ' . implode(', ', array_map(fn ($s) => strtolower((string) $s['label']), $p['steps'])), 'proposed', '', $p,
                    'A proposal changes nothing until a person accepts it', null, 'task:' . $id);
            }
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
        } catch (RuntimeException $x) { return self::log($pdo, $role, $id, $step, $name, '', 'failed', $x->getMessage()); }
        return '';
    }
=== END FILE ===
=== FILE: files/app/lib/TeamRunner.php.region-6.php ===
<?php
// ANCHOR: replaces `    public static function accept(PDO $pdo, int $rid, string $who, string $tz = ''): string` through `    }` (the first `    }` at four spaces after it, directly below the `return 'Accepted: ' . $n . ' part' …` line)
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
        if ($r['step'] === 'editorial') {   // the Editorial Director's checks become the ticket's steps, added as the person who accepts (additive: nobody's words are written over)
            $fold = fn (string $s) => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $s)));
            $have = array_map(fn ($s) => $fold((string) $s['do_what']), GaugeTasks::steps($pdo, (int) $r['task_id'])); $n = 0;
            foreach ((array) ($p['steps'] ?? []) as $s) {
                $do = trim((string) ($s['do'] ?? '')); if ($do === '' || in_array($fold($do), $have, true)) continue;
                GaugeTasks::stepAdd($pdo, (int) $r['task_id'], (string) ($s['see'] ?? ''), $do, (string) ($s['confirm'] ?? ''), $who . ' (accepting ' . TeamRoster::actor(['name' => self::roleName($pdo, (string) $r['role'])]) . ')', $tz); $n++;
            }
            $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
            return 'Accepted: ' . $n . ' editorial check' . ($n === 1 ? '' : 's') . ' added as steps, each confirmed at Confirmation.';
        }
        $b = GaugeTasks::brief($pdo, (int) $r['task_id']) ?? []; $n = 0; $kept = [];
        foreach ($p as $k => $v) { if (trim((string) ($b[$k] ?? '')) !== '') { $kept[] = strtolower(GaugeTasks::BRIEF[$k][0]); continue; } GaugeTasks::briefEdit($pdo, (int) $r['task_id'], $k, (string) $v, $who . ' (accepting ' . TeamRoster::actor(['name' => self::roleName($pdo, (string) $r['role'])]) . ')', $tz); $n++; }
        $pdo->prepare('UPDATE team_runs SET settled_at = UTC_TIMESTAMP(), settled_by = ? WHERE id = ?')->execute([mb_substr($who, 0, 80), $rid]);
        return 'Accepted: ' . $n . ' part' . ($n === 1 ? '' : 's') . ' of the brief filled, kept in its History.' . ($kept ? ' Left as a person wrote it: ' . implode(', ', $kept) . '.' : '');
    }
=== END FILE ===
=== FILE: tests/editorial-director-tests.php ===
<?php
// tools/team-tests/editorial-director-tests.php — the Editorial Director's agent (decisions/0180 role 12). DRAFT: not run.
// Plain PHP, no database: the code-only parts (its checklist, access order, prompt) and the runner's constants.
// Run after the release's files are in place: php tools/team-tests/editorial-director-tests.php
declare(strict_types=1);

$root = null;
foreach ([__DIR__ . '/../..', __DIR__ . '/..', getcwd()] as $c) if (is_file($c . '/app/lib/EditorialDirector.php') && is_file($c . '/app/lib/TeamRunner.php')) { $root = realpath($c); break; }
$pass = 0; $fail = 0;
function check(string $name, bool $ok): void { global $pass, $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if ($ok) $pass++; else $fail++; }
if ($root === null) { echo "FAIL can't find app/lib/EditorialDirector.php and app/lib/TeamRunner.php from here\n"; exit(1); }
try { require_once $root . '/app/lib/TeamRunner.php'; require_once $root . '/app/lib/EditorialDirector.php'; }
catch (Throwable $x) { echo 'FAIL loading the libraries: ' . $x->getMessage() . "\n"; exit(1); }

// --- the runner's rules carry its step ---
check('STEPS has editorial for editorial-director', (TeamRunner::STEPS['editorial'][0] ?? '') === 'editorial-director');
check('editorial comes before gate (proposes before the gate moves a ticket)', array_search('editorial', array_keys(TeamRunner::STEPS), true) < array_search('gate', array_keys(TeamRunner::STEPS), true));
check('its Access action is team.editorial', (TeamRunner::STEPS['editorial'][2] ?? '') === 'team.editorial');
$sa = array_keys(TeamRunner::STEPS); $sb = array_keys(TeamRunner::STEP_AT); sort($sa); sort($sb);
check('STEP_AT and STEPS name the same steps', $sa === $sb);
$all = true; foreach (array_keys(TeamRunner::CEILINGS) as $c) if ((TeamRunner::STEP_AT['editorial'][$c] ?? '') !== 'propose') $all = false;
check('editorial only proposes, at every ceiling', $all);
check('editorial costs $0', (TeamRunner::STEP_COST_MICROS['editorial'] ?? null) === 0);
check('ruleData carries editorial (so the tree\'s fingerprint changes)', isset(TeamRunner::ruleData()['steps']['editorial']));

// --- its lane (TeamRoster::LANE_ROUTES, unchanged) ---
check('/faq.php routes to the Editorial lane', TeamRoster::laneOf(['path:/faq.php']) === 'editorial-director');
check('an /admin/config page goes to the IT lane even with FAQ in its words', TeamRoster::laneOf(['path:/admin/config/workflows'], 'FAQ') === 'it-manager');
check('a ticket naming FAQ by words only routes to Editorial', TeamRoster::laneOf([], 'Fix the FAQ wording') === 'editorial-director');

// --- its checklist, in code ---
$keys = fn (array $l) => array_column($l, 'key');
$t = ['id' => 7, 'what' => 'New empty state on /guide pages', 'why' => 'Visitors see a blank box', 'goal' => '', 'in_scope' => '', 'done_when' => '', 'build_brief' => ''];
$l = EditorialDirector::checklist($t, [], ['path:/guide/x.php']);
check('a public page gets faq, plain words, next action, 390px, standard', $keys($l) === ['faq', 'plain_words', 'next_action', 'phone', 'standard']);
$steps = [['see' => '', 'do_what' => 'Add its FAQ entry and check at 390px against brand.md', 'confirm' => 'one clear next action']];
check('a check already in its steps isn\'t proposed again', $keys(EditorialDirector::checklist($t, $steps, ['path:/guide/x.php'])) === ['plain_words']);
$adm = ['id' => 8, 'what' => 'Rename a column header', 'why' => 'Staff asked', 'done_when' => ''];
check('an admin-only change gets plain words only', $keys(EditorialDirector::checklist($adm, [], ['path:/admin/content/list.php'])) === ['plain_words']);
$h = ['id' => 9, 'what' => 'Heritage story for the old mill', 'why' => 'Requested'];
check('a heritage story gets "then" and "now"', in_array('then_now', $keys(EditorialDirector::checklist($h, [], ['path:/admin/content/x.php'])), true));
$l = EditorialDirector::checklist($t, [], ['path:/place/landmark.php'], 'nations', 'Tribal nations\' land and sensitive sites (0066)');
check('a nations change gets held names and its Tier 4 review', in_array('held', $keys($l), true) && in_array('tier4', $keys($l), true));
$tier4 = array_values(array_filter($l, fn ($x) => $x['key'] === 'tier4'))[0] ?? [];
check('the Tier 4 step names its territory and doesn\'t rule on it', str_contains((string) ($tier4['do'] ?? ''), '0066') && str_contains((string) ($tier4['do'] ?? ''), 'doesn\'t rule'));
check('no territory, no Tier 4 step', !in_array('tier4', $keys(EditorialDirector::checklist($t, [], ['path:/guide/x.php'])), true));

// --- its AI access order: code, then the API, then a session ---
check('the order is code, api, session', array_keys(EditorialDirector::ACCESS_ORDER) === ['code', 'api', 'session']);
check('the checklist is settled in code', EditorialDirector::route('checklist')[0] === 'code');
check('a draft goes to a session while the agents\' API counter isn\'t live', EditorialDirector::route('draft')[0] === 'session' && !EditorialDirector::agentsApiLive());
check('a draft goes to the API once its counter is live', EditorialDirector::route('draft', true)[0] === 'api');
check('the veto is a session\'s', EditorialDirector::route('veto')[0] === 'session');
check('an unknown need goes to a session, never a guess', EditorialDirector::route('something else')[0] === 'session');

// --- its session prompt: lean, cited, yes or no ---
$p = EditorialDirector::sessionPrompt($t + ['goal' => 'A clear empty state'], ['path:/guide/x.php'], '', 'veto');
check('the prompt names the task and its role', str_contains($p, 'Task #7') && str_contains($p, 'Editorial Director'));
check('the prompt asks yes or no with a citation', str_contains($p, 'yes or no') && str_contains($p, '0180 §8'));
check('the prompt stays lean (1500 characters at most)', mb_strlen($p) <= 1500);
check('a territory in the prompt is left to Tier 4', str_contains(EditorialDirector::sessionPrompt($t, [], 'money'), 'Tier 4'));

// --- its ticket types ---
check('it works Work tickets only', EditorialDirector::TICKETS['work'] !== '' && EditorialDirector::TICKETS['audit'] === '' && EditorialDirector::TICKETS['resolution'] === '');

echo "\n" . $pass . ' passed, ' . $fail . " failed\n";
exit($fail === 0 ? 0 : 1);
=== END FILE ===
=== FILE: faq/faq-entries.md ===
## Who looks after the words and screens on Traversence?

The Editorial Director is one of the Team's roles. It looks after places, listings, guides, journeys, this FAQ, and the site's words and design. When a change to something you see is planned, it adds the checks that change still needs:

- this FAQ kept true, saying "coming" where something isn't built yet
- plain words, with no jargon or error codes
- one clear next thing to do on each screen
- a check on a phone screen 390 pixels wide
- the design standard the screen is checked against

When a story or guide tells a place's past, the past is kept apart from today. Places that tribal nations hold are never named or shown.

The Editorial Director suggests these checks, and a person accepts them before they're added. It never changes anything someone has written. Changes that touch money, privacy, public addresses or tribal nations' land get a separate review by the executive team before they go live.

## Can a screen be held back because it's cluttered?

Coming. Before a screen goes live, the Editorial Director and the Cognitive Load & Simplicity Guardian will be able to hold it when it's cluttered or has no clear next step. Each hold will open a ticket that gives the reason.
=== END FILE ===
=== FILE: release.json ===
{
  "name": "team-role-12-editorial-director",
  "gate": "Tier 5 · Founder ruling. This adds a role's step to the decision tree's rules (TeamRunner::STEPS, ST