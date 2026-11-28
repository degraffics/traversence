=== FILE: files/app/lib/TeamRunner.php.region-1.php ===
// ANCHOR: replaces `    public const STEPS = [` through `        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],`
// (The docblock above STEPS and the closing `    ];` after the audit line stay as they are.)
// DRAFT. This adds the Data & Content lane group's two steps. They sit before 'handoff' on purpose: pick() walks STEPS in order,
// so a Building ticket in the Editorial or Marketing lane gets its brief from its own lane. Any other ticket still goes to the
// System Architect's handoff.
    public const STEPS = [
        'gate' => ['project-manager', 'Moves a Work ticket from Planning to Building once its gate passes', 'team.gate', ['min' => 'manager', 'depts' => ['ops']]],
        'content_brief' => ['editorial-director', 'Writes a Building ticket\'s build brief in the Editorial lane (places, listings, guides, journeys, the FAQ and copy, design): its checks in code first, then the brief for a build session', 'team.content_brief', ['min' => 'manager', 'depts' => []]],
        'marketing_brief' => ['marketing-director', 'Writes a Building ticket\'s build brief in the Marketing lane (SEO, taxonomy, campaigns, the funnel): its checks in code first, then the brief for a build session', 'team.marketing_brief', ['min' => 'manager', 'depts' => []]],
        'handoff' => ['system-architect', 'Writes a Building ticket\'s build brief and hands it to the session manager', 'team.handoff', ['min' => 'staff', 'depts' => ['eng']]],
        'brief' => ['translator', 'Proposes a Resolution brief\'s plain words from its ticket and its decisions', 'team.brief', ['min' => 'staff', 'depts' => ['ops']]],
        'audit' => ['ops-reconciliation', 'Runs the daily health and drift audit: a finding is looked at twice before it opens a ticket', 'team.audit', ['min' => 'manager', 'depts' => ['ops']]],
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-2.php ===
// ANCHOR: replaces `    public const STEP_AT = [` through `        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],`
// (The docblock above STEP_AT and the closing `    ];` after the audit line stay as they are.)
// DRAFT. The content steps follow handoff's pattern exactly: off at Planning, and act from Building on. Where the brief goes is
// still decided by briefTo()/briefFor() (the ceiling and the reach fence).
    public const STEP_AT = [
        'gate' => ['planning' => 'propose', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'content_brief' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'marketing_brief' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'handoff' => ['planning' => 'off', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
        'brief' => ['planning' => 'propose', 'building' => 'propose', 'implementation' => 'propose', 'confirmation' => 'propose'],
        'audit' => ['planning' => 'act', 'building' => 'act', 'implementation' => 'act', 'confirmation' => 'act'],
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-3.php ===
// ANCHOR: replaces `    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];` through `    public const STEP_COST_MICROS = ['gate' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];`
// (A single line. The docblock above it stays.)
    public const STEP_COST_MICROS = ['gate' => 0, 'content_brief' => 0, 'marketing_brief' => 0, 'handoff' => 0, 'brief' => 0, 'audit' => 0];

    /**
     * The Data & Content lane group's steps (DRAFT). Each maps step => the lane it works. Under decisions/0180 §5 every ticket
     * routes to exactly one middle manager. The appendix rows 12 and 13 are the Editorial Director and the Marketing Director.
     * The team vetting of 2026-11-27 (option A) gives both lanes to the Data & Content session, which builds as Build & QA (21).
     * Data & Content's crawler, W-workflow and jurisdiction tickets route to the IT Manager (TeamRoster::LANE_ROUTES puts IT
     * first), so these steps never take them.
     */
    public const CONTENT_STEPS = ['content_brief' => 'editorial-director', 'marketing_brief' => 'marketing-director'];

    /**
     * The AI access order, cheapest first (decisions/0180 §6 and its note "we pipe agents into session work and system into
     * api work"; jason-spec §4: internal systems and deterministic logic first, no silent guesswork). Each tier is used only
     * when the tier before it can't settle the question.
     */
    public const AI_ORDER = [
        'code' => 'Code and fixed templates at $0: our own records, the decisions, the lanes and the checks below',
        'api' => 'A focused call with a small, known context (a draft, a lean Prior Ruling Check) on the agents\' own $40 counter, through AiClient::allow()',
        'session' => 'A Claude session (the Max plan) for multi-step reasoning and build work, through the build brief',
    ];

    /**
     * Is the agents' API tier live? It stays false until the agents' own $40 counter is built in AiClient, with its own allow()
     * refusal and its own line on the meter (0180's note "Separate each": "nothing is spent from (3) until its counter is
     * live, and until then agents stay on sessions"). It is part of ruleData, so turning it on changes the fingerprint and
     * needs the Founder's approval of the tree again.
     */
    public const AGENTS_API_LIVE = false;

    /**
     * The default charter for each content lane. It is used only when the role's own mandate on the Team page is empty: a
     * mandate is a person's words and always wins. The text comes from the 0180 appendix (rows 12, 13, 26, 27, 29, 30, and
     * the retired 14 and 20, whose duties went to 26) and from jason-spec §6.
     */
    public const CONTENT_CHARTER = [
        'editorial-director' => 'Editorial lane: places, listings, guides, journeys, the FAQ and copy, design. Carries the retired Regional Directory & Data Curator\'s and AI & Dynamic Guide Quality Auditor\'s duties (directory accuracy, verified closures, guide quality). Curators, not controllers: tell the story, never the way in; never overwrite what a person entered; held 0066 names go nowhere. Every visitor-facing change carries its FAQ entry (0057), passes 390px and keeps one clear next action.',
        'marketing-director' => 'Marketing lane: SEO, taxonomy, campaigns and the funnel. Search demand (W12) is kept by place, kind and topic, never as raw search strings; funnel gaps (W15) read hub to zone to town to anchor. Zero-commission and the mission are never traded for reach. No spend: agents are at $0.',
    ];
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-4.php ===
// ANCHOR: replaces `    public static function ruleData(): array` through `    }` (the closing brace of ruleData, the first `    }` after that line)
// (Its docblock above stays.) DRAFT. The content steps are already in 'steps', 'step_at' and 'step_cost'. The lane map and the
// AI order are added here because they are rules, not wording. CONTENT_CHARTER is wording and stays out of the fingerprint.
    public static function ruleData(): array
    {
        $steps = []; foreach (self::STEPS as $k => [$role, , $action, $def]) $steps[$k] = [$role, $action, $def];
        return ['steps' => $steps, 'step_at' => self::STEP_AT, 'ceilings' => array_keys(self::CEILINGS), 'territories' => self::TERRITORIES, 'tier5_words' => self::TIER5_WORDS, 'tier1_words' => self::TIER1, 'step_cost' => self::STEP_COST_MICROS,
            'content_steps' => self::CONTENT_STEPS, 'ai_order' => array_keys(self::AI_ORDER), 'agents_api' => self::AGENTS_API_LIVE];
    }
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-5.php ===
// ANCHOR: replaces `    public static function applies(PDO $pdo, string $step, array $t): bool` through `    }` (the closing brace of applies, the first `    }` after that line)
// (Its docblock "Does this step have work on this ticket?" stays.)
    public static function applies(PDO $pdo, string $step, array $t): bool
    {
        $tk = (string) ($t['ticket'] ?? 'work'); $sg = (string) ($t['stage'] ?? '');
        if ($step === 'gate') return $tk === 'work' && $sg === 'planning' && !self::openProposal($pdo, (int) $t['id'], 'gate')
            && (GaugeTasks::gate($pdo, $t) === [] || (self::mode($pdo, 'gate') === 'act' && self::takesItself($pdo, $t) && GaugeTasks::gate($pdo, $t) === ['someone to have it (Take it)']));
        if (isset(self::CONTENT_STEPS[$step])) {   // a Building Work ticket in this step's own lane, with no brief yet (as handoff)
            if ($tk !== 'work' || $sg !== 'building' || trim((string) $t['goal']) === '' || trim((string) ($t['build_brief'] ?? '')) !== '' || (string) $t['rel'] !== '') return false;
            if (TeamRoster::lane($pdo, $t) !== self::CONTENT_STEPS[$step]) return false;
            [$slug, , $act, $def] = self::STEPS[$step];
            $d = TeamRoster::doer($pdo, $slug, $act, $def);
            // a lane role without its permissions leaves the ticket to the System Architect's handoff, so a refusal never starves it
            return $d !== null && TeamRoster::can($d[0], $act, $def)[0];
        }
        if ($step === 'handoff') return $tk === 'work' && $sg === 'building' && trim((string) $t['goal']) !== '' && trim((string) ($t['build_brief'] ?? '')) === '' && (string) $t['rel'] === '';
        if ($step === 'audit') return false;   // the audit is its own daily job, not one ticket's step
        if ($step === 'brief') { if ($tk !== 'resolution' || $sg !== 'brief') return false; return self::briefDraft($pdo, $t) !== []; }
        return false;
    }
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-6.php ===
// ANCHOR: replaces `    public static function run(PDO $pdo, array $t, string $step, array $role): string` through `    }` (the closing brace of run, the first `    }` at four spaces after that line)
// (Its docblock "Do the step, log it, and return the line." stays.) DRAFT. The only change is the content branch before
// 'handoff'. gate, handoff and brief are copied unchanged.
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
            if (isset(self::CONTENT_STEPS[$step])) {   // the Data & Content lane group's brief (decisions/0180 §5 lanes 12 and 13; §6 cheapest first)
                $lane = self::CONTENT_STEPS[$step];
                [$brief, $route] = self::contentBrief($pdo, $t, $step, $role);
                $was = (string) $t['owner'];
                $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = ?, owner = ?, owner_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$brief, mb_substr($who, 0, 80), $id]);
                $to = self::briefFor($pdo, ['build_brief' => $brief] + $t);   // the same fence as handoff (P3-6): a public page waits for the Founder while reach is Backend only
                $said = $to === 'build' ? 'straight to a build session' : ($to === 'founder' ? 'front end: waiting for you (it reaches a public page: ' . mb_substr(implode(', ', self::frontOf($pdo, ['build_brief' => $brief] + $t)), 0, 160) . ')' : 'with the session manager');
                ControlLog::add($pdo, 'control', '', 'Task #' . $id . ' build brief written in ' . TeamRoster::laneName($pdo, $lane) . '\'s lane; ' . $said . ' (hourly report, build-requests.json)', $who);
                return self::log($pdo, $role, $id, $step, 'its scope, decisions and steps, and its lane\'s checks (code, $0)' . ($to !== 'session manager' ? ', and how far it reaches (the fence)' : ''), 'Wrote its build brief (' . $route . '); ' . $said . ' at the next hourly report', 'done', '', null,
                    'Takes the brief back and gives the ticket back to ' . ($was !== '' ? $was : 'nobody'), ['owner' => $was], 'task:' . $id);
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

=== FILE: files/app/lib/TeamRunner.php.region-7.php ===
// ANCHOR: replaces `    /** A Building ticket's build brief, in plain words from its own record: what, why, source, scope, decisions, steps. */` through `    }` (the closing brace of buildBrief, the first `    }` after that line)
// DRAFT. Three new methods (aiRoute, contentRules, contentBrief) go in before buildBrief. buildBrief itself is copied unchanged.

    /**
     * Which tier of the AI access order (AI_ORDER) a kind of work uses: [tier, why in words]. 'check' is settled in code at $0.
     * 'draft' (a bounded call) would use the agents' API, but only once AGENTS_API_LIVE is true, and until then it goes to a
     * session. 'build' (multi-step) always goes to a session. Anything unknown goes to a session, never a silent guess
     * (jason-spec §4.5). Nothing here calls AiClient: in this release the API tier has no caller.
     */
    public static function aiRoute(string $kind): array
    {
        if ($kind === 'check') return ['code', 'code settles it at $0 (decisions/0180 §6: internal first)'];
        if ($kind === 'draft' && self::AGENTS_API_LIVE) return ['api', 'a focused draft on the agents\' own $40 counter, through AiClient::allow() (0180 note "Separate each")'];
        if ($kind === 'draft') return ['session', 'a focused draft would use the agents\' API, but its own $40 counter isn\'t live yet, so a session does it (0180 note "Separate each")'];
        return ['session', 'code checks first ($0), then a build session for the work itself: build work is multi-step, and the API is only for focused calls (0180 §6 note "we pipe agents into session work")'];
    }

    /**
     * The content lanes' checks, in code (no DB, no AI). These are the lines a build must hold, from the ticket's words, whether
     * its change reaches a public page, and the territory its targets touch. They are rules from 0180 §4, §7 and §8, 0057,
     * 0066 and jason-spec §6. Each is a line for the brief: none blocks, because the fence and the Tier 4 review do the blocking.
     */
    public static function contentRules(string $text, bool $public, string $territory): array
    {
        $out = [
            'Never write over what a person entered: propose it for their Accept, as an Edit with History (W0–W15 rule; decisions/0180 §7).',
            'Held 0066 names and protected locations go nowhere: Landmarks::visibleSql and HeldNames stay the only read (0180 §4 item 4).',
        ];
        if ($public) $out[] = 'It reaches a page visitors see: add or update its FAQ entry (0057), check it at 390px (0058 §11) and keep one clear next action (0180 §8). While reach is Backend only, it waits at its brief for the Founder (P3-6).';
        if (preg_match(self::TIER5_WORDS['nations'], $text)) $out[] = 'Its words suggest 0066: the Privacy & 0066 Guards (25) check it before it goes further.';
        if (preg_match('/\b(search(es)?|demand|zero[- ]results?|W12)\b/i', $text)) $out[] = 'Search demand is kept by place, kind and topic, never as raw search strings (W12; jason-spec §6.1).';
        if (preg_match('/\b(funnel|hub|zone|anchor|W15)\b/i', $text)) $out[] = 'Funnel gaps read hub → zone → town → anchor (W15).';
        if (preg_match('/\b(guides?|research|place descriptions?|AI)\b/', $text)) $out[] = 'Guides and research spend the platform\'s own $40 (ai_month_usd; 0160 §5); this role spends $0.';
        if ($territory !== '') $out[] = 'It touches ' . (GaugeTasks::BOUNDARIES[$territory] ?? $territory) . ': a Tier 4 review is recorded before it goes live (0180 §4).';
        return $out;
    }

    /**
     * A content lane's build brief: [brief, the AI route in words]. The order is: the lane and its charter (the role's own
     * mandate when a person wrote one, else CONTENT_CHARTER), then the plain brief (buildBrief), then the code checks and the
     * route. It stays within 4000 characters, with the checks always kept in full. Lean by design (0180 §6): the ticket's own
     * record only, never the history.
     */
    public static function contentBrief(PDO $pdo, array $t, string $step, array $role): array
    {
        $lane = self::CONTENT_STEPS[$step] ?? 'editorial-director';
        $mandate = trim((string) ($role['mandate'] ?? ''));
        $charter = $mandate !== '' ? $mandate : (self::CONTENT_CHARTER[$lane] ?? '');
        $text = implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['in_scope'] ?? ''), (string) ($t['done_when'] ?? '')]);
        $public = self::frontOf($pdo, $t) !== [];
        $territory = self::touches(TeamRoster::ticketTargets($t));
        [$tier, $why] = self::aiRoute('build');
        $head = implode("\n", ['Lane: ' . TeamRoster::laneName($pdo, $lane) . ' (decisions/0180 §5); built by Build & QA Sessions (21)',
            'Charter: ' . mb_substr((string) preg_replace('/\s+/', ' ', $charter), 0, 600)]);
        $tail = ['Checks (in code, $0; each must hold in the build):']; foreach (self::contentRules($text, $public, $territory) as $c) $tail[] = '- ' . $c;
        $tail[] = 'AI route: ' . $why . '.';
        $tail = implode("\n", $tail);
        $room = max(200, 4000 - mb_strlen($head) - mb_strlen($tail) - 2);
        return [mb_substr($head . "\n" . mb_substr(self::buildBrief($pdo, $t), 0, $room) . "\n" . $tail, 0, 4000), 'code checks, then ' . ($tier === 'session' ? 'a build session' : 'the agents\' API')];
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
=== END FILE ===

=== FILE: files/app/lib/TeamRunner.php.region-8.php ===
// ANCHOR: replaces `    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string` through `    }` (the closing brace of undo, the first `    }` at four spaces after that line)
// (Its docblock stays.) DRAFT. The only change: a content step's run is undone the same way as handoff (the brief is taken back
// and the owner restored).
    public static function undo(PDO $pdo, int $rid, string $who, string $tz = ''): string
    {
        $r = self::get($pdo, $rid); if (!$r) throw new RuntimeException('That run isn\'t there.');
        if ($r['undone_at'] !== null) throw new RuntimeException('Already undone, by ' . $r['undone_by'] . '.');
        if ($r['state'] !== 'done') throw new RuntimeException($r['state'] === 'proposed' ? 'A proposal changes nothing: Accept or Dismiss it.' : 'It did nothing, so there\'s nothing to undo.');
        $u = json_decode((string) $r['undo_data'], true) ?: []; $tid = (int) $r['task_id'];
        if ($r['step'] === 'gate') { GaugeTasks::move($pdo, $tid, (string) $u['to'], $who, 'Undo of run #' . $rid, $tz); if (array_key_exists('owner', $u)) $pdo->prepare('UPDATE control_gauge_tasks SET owner = ?, owner_at = NULL WHERE id = ?')->execute([(string) $u['owner'], $tid]); }
        elseif ($r['step'] === 'handoff' || isset(self::CONTENT_STEPS[(string) $r['step']])) {
            $pdo->prepare('UPDATE control_gauge_tasks SET build_brief = NULL, owner = ?, owner_at = ? WHERE id = ?')->execute([(string) ($u['owner'] ?? ''), ($u['owner'] ?? '') !== '' ? gmdate('Y-m-d H:i:s') : null, $tid]);
            ControlLog::add($pdo, 'control', '', 'Task #' . $tid . ' build brief taken back (Undo of run #' . $rid . ')', $who, '', $tz);
        } elseif ($r['step'] === 'audit') GaugeTasks::withdraw($pdo, (int) $u['withdraw'], 'Undo of run #' . $rid, $who, $tz);
        else throw new RuntimeException('This kind of run has no Undo.');
        $pdo->prepare('UPDATE team_runs SET undone_at = UTC_TIMESTAMP(), undone_by = ? WHERE id = ? AND undone_at IS NULL')->execute([mb_substr($who, 0, 80), $rid]);
        return 'Undone: ' . lcfirst((string) $r['undo_how']) . '.';
    }
=== END FILE ===

=== FILE: tests/team-content-tests.php ===
<?php
// tests/team-content-tests.php (for tools/team-tests/): the Data & Content lane steps in TeamRunner (DRAFT).
// Plain PHP, no database, no network, no money: it reads constants and calls only the pure helpers aiRoute(), contentRules()
// and fingerprint() with no PDO. It prints PASS or FAIL per check and exits 1 on any FAIL.
// Run it from the repo, or set TRV_ROOT to the folder that holds app/lib/TeamRunner.php.
declare(strict_types=1);

$root = (string) (getenv('TRV_ROOT') ?: '');
if ($root === '') { $d = __DIR__; for ($i = 0; $i < 6; $i++) { if (is_file($d . '/app/lib/TeamRunner.php')) { $root = $d; break; } $d = dirname($d); } }
if ($root === '') { echo "FAIL: can't find app/lib/TeamRunner.php (set TRV_ROOT)\n"; exit(1); }
require_once $root . '/app/lib/TeamRunner.php';

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$ok) $fails++; }

// 1. The content steps exist and come before handoff, so pick() gives a lane ticket to its own lane first
$keys = array_keys(TeamRunner::STEPS);
foreach (TeamRunner::CONTENT_STEPS as $step => $lane) {
    check("$step is in STEPS", isset(TeamRunner::STEPS[$step]));
    check("$step comes before handoff", array_search($step, $keys, true) < array_search('handoff', $keys, true));
    check("$step's role is its own lane ($lane)", (TeamRunner::STEPS[$step][0] ?? '') === $lane);
    check("$lane is one of the seven lanes", in_array($lane, TeamRoster::LANES, true));
    check("$step's name fits team_runs.step (VARCHAR 20)", strlen($step) <= 20);
    // 2. Its modes: off at Planning, act from Building on (as handoff)
    foreach (array_keys(TeamRunner::CEILINGS) as $c) check("$step has a mode at $c", isset(TeamRunner::STEP_AT[$step][$c]));
    check("$step is off at Planning", (TeamRunner::STEP_AT[$step]['planning'] ?? '') === 'off');
    // 3. $0
    check("$step costs \$0", (TeamRunner::STEP_COST_MICROS[$step] ?? -1) === 0);
}
// the old steps are unchanged
check('gate is still the Project Manager\'s', TeamRunner::STEPS['gate'][0] === 'project-manager');
check('handoff is still the System Architect\'s', TeamRunner::STEPS['handoff'][0] === 'system-architect');
check('every step has a cost', array_diff(array_keys(TeamRunner::STEPS), array_keys(TeamRunner::STEP_COST_MICROS)) === []);
check('every step has its modes', array_diff(array_keys(TeamRunner::STEPS), array_keys(TeamRunner::STEP_AT)) === []);

// 4. The AI access order: code, then API, then session
check('AI order is code, api, session', array_keys(TeamRunner::AI_ORDER) === ['code', 'api', 'session']);
check('the agents\' API is not live in this release', TeamRunner::AGENTS_API_LIVE === false);
check('a check is settled in code', TeamRunner::aiRoute('check')[0] === 'code');
check('a draft goes to a session while the API counter is not live', TeamRunner::aiRoute('draft')[0] === 'session');
check('a build goes to a session', TeamRunner::aiRoute('build')[0] === 'session');
check('an unknown kind goes to a session, never a guess', TeamRunner::aiRoute('whatever')[0] === 'session');

// 5. The rules carry the lanes and the AI order, so changing them clears the tree's approval
$rd = TeamRunner::ruleData();
check('ruleData has the content steps', ($rd['content_steps'] ?? null) === TeamRunner::CONTENT_STEPS);
check('ruleData has the AI order', ($rd['ai_order'] ?? null) === ['code', 'api', 'session']);
check('ruleData has the API switch', array_key_exists('agents_api', $rd) && $rd['agents_api'] === false);
check('ruleData keeps the charter wording out', !isset($rd['content_charter']));
check('fingerprint is a sha256', (bool) preg_match('/^[0-9a-f]{64}$/', TeamRunner::fingerprint()));

// 6. The code checks
$base = TeamRunner::contentRules('Tidy a backend label', false, '');
check('two base rules always (people\'s words, 0066)', count($base) === 2);
$pub = implode("\n", TeamRunner::contentRules('Update a guide page', true, ''));
check('a public page asks for its FAQ entry', str_contains($pub, 'FAQ entry'));
check('a public page asks for 390px', str_contains($pub, '390px'));
check('a guide notes the platform\'s $40, agents at $0', str_contains(implode("\n", TeamRunner::contentRules('Rebuild the AI guides', false, '')), 'spends $0'));
check('search demand never keeps raw strings', str_contains(implode("\n", TeamRunner::contentRules('Show search demand by town', false, '')), 'never as raw search strings'));
check('a tribal word brings the 0066 guard', str_contains(implode("\n", TeamRunner::contentRules('Hide a sacred site', false, '')), 'Privacy & 0066 Guards'));
check('a territory brings the Tier 4 review', str_contains(implode("\n", TeamRunner::contentRules('x', false, 'money')), 'Tier 4 review'));

echo $fails === 0 ? "ALL PASS\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
### How do changes to place pages, guides and this FAQ get made? (coming: shown once this release is approved and live)

Our editorial team plans each change to a place page, a guide, a listing or this FAQ as its own piece of work. Before anything is built, it is checked against our standing rules. We never change what a person wrote about their own business or their own story without their say. Protected tribal sites are never shown. Search requests are counted by town and topic, never kept word for word.

Every change is built and tested on a private copy of the site first, including on a phone-sized screen