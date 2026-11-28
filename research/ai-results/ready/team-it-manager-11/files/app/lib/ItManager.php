<?php
// app/lib/ItManager.php — the IT Manager (decisions/0180 §5, appendix row 11: Layer 3, reports to the CTO (5) and the CIO (6)).
// This is the role's agent. It does one $0 step in TeamRunner (STEPS 'triage') and only ever proposes, at every ceiling.
//
// How it works:
//   - Its lane is TeamRoster::LANE_ROUTES['it-manager'] (unchanged here): releases, staging, alarms, config, the crawler and
//     sources, the scheduled jobs, migrations, and the data, address, nations and privacy territories. Audit tickets are also
//     in its lane.
//   - On a Work ticket in that lane, at Planning or Building with no release named yet, it proposes a triage:
//       - the tier, by what the ticket touches (TeamRunner::tier);
//       - the checks its specialists record on the ticket as steps (22, 24, 25, and its own crawler-batch verification under
//         0180 §1 Tier 2);
//       - the decisions the ticket rests on;
//       - where any further reasoning goes.
//     A person's Accept adds those steps and attachments through GaugeTasks, each kept in History. Dismiss changes nothing.
//   - AI access order (0180 §6 and its note "the routing rule, cheapest first"):
//       1. code, at $0, wherever code settles it;
//       2. the agents' API, for a bounded call, but only once its own counter and allow() exist ("Separate each",
//          2026-11-27). Until then this rung is skipped;
//       3. a Claude session, for multi-step reasoning.
//     The step itself never calls any AI or outside service (TeamRunner's header; STEP_COST_MICROS 0).
//   - Never, whatever it proposes:
//       - a Founder ruling;
//       - a money move (0180 §4.1);
//       - a person's words written over;
//       - a test account acted through;
//       - a held 0066 name sent anywhere.
declare(strict_types=1);

require_once __DIR__ . '/TeamRunner.php';

final class ItManager
{
    public const SLUG = 'it-manager';

    /** The role's grounding, the four things 0180 §6 names, kept beside its Team record (its mandate there stays a person's words). */
    public const CHARTER = [
        'role' => 'IT Manager (decisions/0180, appendix row 11)',
        'layer' => 'Layer 3 middle manager; reports to the CTO (5) and the CIO (6); one exclusive swimlane (0180 §5)',
        'adrs' => ['0180 §1–§8 and its notes (tiers, territories, the hard money line, routing)', '0179 (publish authority, the decision tree)', '0165 (staging first)',
            '0066 (tribal nations\' land: held names go nowhere)', '0164 (nothing private to a free external API)', '0161 (one claim per file or function)', '0145 §3 (test accounts stay isolated)'],
        'job' => ['Owns the IT lane\'s tickets (Tier 3): triage, multi-file changes, crawler and ingestion problems, recurring audit anomalies',
            'Verifies crawler batch fixes before they go live (0180 §1, Tier 2)',
            'Sees that every change touching a territory has its Tier 4 review recorded before live (0180 §4)',
            'Directs System Architect (20), Build & QA Sessions (21), Staging & Release Workers (22), Operations & Reconciliation (23), Schema & Migration Guards (24), Privacy & 0066 Guards (25)'],
        'knowledge' => ['The Work queue (GaugeTasks), its lane (TeamRoster::LANE_ROUTES), the territories (TeamRunner::TERRITORIES)',
            'Scheduled tasks, alarms, Site health and the daily audit\'s tickets', 'The zip check (ReleaseKind), the before-and-after check, the fence (TeamRunner::frontOf)'],
        'escalation' => ['Cross-lane work: hands off one lane at a time with a note (GaugeTasks::handOff, 0180 §7)',
            'A territory: Tier 4 review by the CTO or CIO with the Prior Ruling Check (0180 §3); checked by the CMO (7) and the Privacy & 0066 Guard (25)',
            'Money and billing: the Accounting Manager\'s lane; the hard money line (0180 §4.1)',
            'A rule, ADR or fence change, or a deadlock the CEO can\'t settle: Tier 5, a Founder ruling'],
        'never' => ['Answers a Founder ruling', 'Moves real money or spends above $0', 'Writes over a person\'s words', 'Acts through a test account', 'Sends a held 0066 name anywhere'],
    ];

    /** The ticket types and what this role does with each. */
    public const TICKETS = [
        'work' => 'Proposes its triage at Planning or Building, before its release is named',
        'audit' => 'In its lane; the audit step itself is Operations & Reconciliation (23), who reports here',
        'resolution' => 'Never answers one: only the Founder does (Tier 5)',
    ];

    /** AI access order (0180 §6; the note "the routing rule, cheapest first"). */
    public const AI_ORDER = ['code', 'api', 'session'];

    /** The words a session (or, later, a bounded API call) is given: lean, the change and its areas only (0180 §6). */
    public const PROMPTS = [
        'triage' => 'Settle what code could not: does this ticket stay in the IT lane or split, and in what order do its checks run? Answer in at most five lines, citing the decision and section each answer rests on. Staging only; never move money; never write over a person\'s words.',
        'prior_ruling' => 'Prior Ruling Check (decisions/0180 §3), for the reviewing executive: does this change strictly implement, enforce or repair what a ratified ADR, 0066 or a governance rule already says? Answer yes or no, and cite the ADR and section. No citation means no: it goes to Tier 5.',
    ];

    /** The agents' API rung: off until the AI items release ships its own counter and allow() (0180, "Separate each"). AiClient: file not shown here. */
    public static function agentsApiLive(): bool { return false; }

    /** [rung, why] for a need: 'settled' (code has it), 'bounded' (a small known context), 'reasoning' (multi-step). */
    public static function aiRoute(string $need, string $territory = ''): array
    {
        if ($need === 'settled') return ['code', 'Settled in code at $0 (0180 §6: code first).'];
        if ($need === 'bounded' && $territory !== 'nations' && self::agentsApiLive()) return ['api', 'A bounded call on the agents\' own $40 cap, through its allow() (0180 note, "Separate each").'];
        if ($need === 'bounded') return ['session', $territory === 'nations'
            ? 'A Claude session, with the ticket\'s words left out: held 0066 names go nowhere.'
            : 'A Claude session: the agents\' API counter isn\'t live yet, so agents stay on sessions (0180 note, "Separate each").'];
        return ['session', 'A Claude session, for multi-step reasoning (0180 note to §6).'];
    }

    /** Is this step's work here? A Work ticket in the IT lane, at Planning or Building, no release named, never triaged before. */
    public static function applies(PDO $pdo, array $t): bool
    {
        if ((string) ($t['ticket'] ?? 'work') !== 'work') return false;
        if (!in_array((string) ($t['stage'] ?? ''), ['planning', 'building'], true) || (string) ($t['rel'] ?? '') !== '') return false;
        if (self::triaged($pdo, (int) $t['id'])) return false;   // once proposed (accepted or dismissed), it isn't proposed again
        return TeamRoster::lane($pdo, $t) === self::SLUG;
    }

    private static function triaged(PDO $pdo, int $task): bool
    {
        try { $st = $pdo->prepare("SELECT 1 FROM team_runs WHERE task_id = ? AND step = 'triage' AND state IN ('proposed', 'done') LIMIT 1"); $st->execute([$task]); return (bool) $st->fetchColumn(); } catch (Throwable $x) { return true; }   // unreadable: propose nothing
    }

    /**
     * The triage from what the ticket touches, its words and the fence (pure: no database). Returns tier, territory, flag,
     * checks ([see, do, confirm] each), notes, adrs (numbers), adr_sections and route [rung, why, need].
     */
    public static function plan(array $targets, string $text, array $front = []): array
    {
        [$tier, $terr, $flag] = TeamRunner::tier($text, $targets);
        $checks = []; $notes = []; $adrs = ['0165']; $sec = ['0165 (staging first)'];
        $checks[] = ['Staging & Release Workers (22)', 'Staging & Release Workers (22): stage it first; the before-and-after check passes for the advocates\' views', 'The release\'s "Before and after (advocates)" check passed'];
        $migration = $terr === 'data' || (bool) preg_match('/\b(migrations?|create table|alter table|\.sql)\b/i', $text)
            || (bool) array_filter($targets, fn ($x) => str_contains((string) $x, 'app/migrations/'));
        if ($migration) $checks[] = ['Schema & Migration Guards (24)', 'Schema & Migration Guards (24): the zip check\'s migration rules (ReleaseKind): additive only, or each UPDATE/DELETE carries its data_reason; safe to run twice; no DELIMITER', 'The zip check passed its migration rules'];
        if (in_array($terr, ['nations', 'privacy'], true) || in_array($flag, ['nations', 'privacy'], true))
            $checks[] = ['Privacy & 0066 Guards (25)', 'Privacy & 0066 Guards (25): no held name or protected location on any surface (Landmarks::visibleSql, HeldNames); no personal data leaves; test accounts stay isolated (0145 §3)', 'The guard\'s line on the ticket: clear'];
        $crawl = (bool) array_filter($targets, fn ($x) => str_starts_with((string) $x, 'job:') || str_starts_with((string) $x, 'path:/admin/crawler') || str_starts_with((string) $x, 'path:/api/crawl/'))
            || (bool) preg_match('/\b(crawl(er|ing)?|ingest(ion)?|batch)\b/i', $text);
        if ($crawl) $checks[] = ['IT Manager (11)', 'IT Manager (11): verify the batch fix on staging: the job runs on time, no new failures, inside the $0 cap (0180 §1, Tier 2)', 'Scheduled tasks shows its next runs ok'];
        if ($terr !== '') {
            $checks[] = ['CTO (5) / CIO (6)', 'Tier 4 review recorded before live, with the Prior Ruling Check citing its ADR and section (0180 §3, §4); checked by the CMO (7) and the Privacy & 0066 Guard (25)', 'The review and its citation are on the ticket'];
            $notes[] = 'It touches ' . lcfirst(GaugeTasks::BOUNDARIES[$terr] ?? $terr) . '. The Tier 4 review (R3) isn\'t built yet, so today it\'s a Founder ruling, as TeamRunner::tier sets.';
            $adrs[] = '0180'; $sec[] = '0180 §3 and §4';
        }
        if ($terr === 'data') $sec[] = '0180 §4 item 2';
        if ($terr === 'address') $sec[] = '0180 §4 item 3';
        if ($terr === 'nations') { $adrs[] = '0066'; $sec[] = '0066'; }
        if ($terr === 'privacy') { $adrs[] = '0145'; $sec[] = '0145 §3'; $sec[] = '0164'; }
        if ($terr === 'money') { $adrs[] = '0178'; $sec[] = '0180 §4.1'; $sec[] = '0178'; $notes[] = 'Money and billing is the Accounting Manager\'s lane: hand it off with a note. The hard money line holds (0180 §4.1).'; }
        if ($flag !== '') $notes[] = 'Its words suggest ' . lcfirst(GaugeTasks::BOUNDARIES[$flag] ?? $flag) . ': a person checks the flag.';
        if ($front) $notes[] = 'It reaches a public page (' . mb_substr(implode(', ', $front), 0, 160) . '): at Backend only, it waits for the Founder (the fence).';
        $need = $terr !== '' ? 'bounded' : (count($targets) > 3 || (!$targets && $tier !== 1) ? 'reasoning' : 'settled');
        [$rung, $why] = self::aiRoute($need, $terr);
        return ['tier' => $tier, 'territory' => $terr, 'flag' => $flag, 'checks' => $checks, 'notes' => $notes, 'adrs' => array_values(array_unique($adrs)),
            'adr_sections' => array_values(array_unique($sec)), 'route' => [$rung, $why, $need]];
    }

    /** The lean packet for a session or bounded call: the change, its areas and the ADR sections, nothing more (0180 §6). */
    public static function packet(array $t, array $targets, array $plan): string
    {
        $L = ['IT Manager (11) · task #' . (int) ($t['id'] ?? 0), self::PROMPTS[$plan['territory'] !== '' ? 'prior_ruling' : 'triage']];
        if ($plan['territory'] === 'nations' || $plan['flag'] === 'nations') $L[] = 'Its words are left out (0066: held names go nowhere); read them on the ticket.';
        else $L[] = 'What: ' . mb_substr(trim((string) ($t['what'] ?? '')), 0, 300);
        $L[] = 'Touches: ' . ($targets ? implode(', ', array_slice($targets, 0, 12)) : 'nothing named');
        if ($plan['territory'] !== '') $L[] = 'Territory: ' . (GaugeTasks::BOUNDARIES[$plan['territory']] ?? $plan['territory']);
        $L[] = 'Decisions it may rely on: ' . implode('; ', $plan['adr_sections']);
        return mb_substr(implode("\n", $L), 0, 1500);
    }

    /** The proposal for a ticket (TeamRunner::run's triage step). */
    public static function propose(PDO $pdo, array $t): array
    {
        $targets = TeamRoster::ticketTargets($t);
        $text = implode(' ', [(string) ($t['what'] ?? ''), (string) ($t['why'] ?? ''), (string) ($t['goal'] ?? ''), (string) ($t['build_brief'] ?? '')]);
        $front = TeamRunner::frontOf($pdo, $t);
        $p = self::plan($targets, $text, $front);
        $p['lane'] = self::SLUG; $p['targets'] = $targets;
        if ($p['route'][0] !== 'code') $p['packet'] = self::packet($t, $targets, $p);
        return $p;
    }

    /** One line for the run log (at most 400 characters). */
    public static function said(array $p): string
    {
        $n = count($p['checks'] ?? []);
        return mb_substr('Proposed a triage: Tier ' . (int) $p['tier'] . ($p['territory'] !== '' ? ', touches ' . lcfirst(GaugeTasks::BOUNDARIES[$p['territory']] ?? $p['territory']) : '')
            . ' · ' . $n . ' check' . ($n === 1 ? '' : 's') . ' for its specialists · reasoning: ' . $p['route'][0], 0, 400);
    }

    /** A person accepts: the checks become steps and the decisions are attached, each through GaugeTasks with History. A step already there isn't added twice. */
    public static function accept(PDO $pdo, int $task, array $p, string $who, string $tz = ''): string
    {
        $by = $who . ' (accepting system · IT Manager)';
        $have = array_map(fn ($s) => (string) $s['do_what'], GaugeTasks::steps($pdo, $task)); $added = 0; $att = 0;
        foreach ((array) ($p['checks'] ?? []) as $c) {
            [$see, $do, $confirm] = array_pad(array_map('strval', (array) $c), 3, '');
            if ($do === '' || in_array(mb_substr($do, 0, 600), $have, true)) continue;
            GaugeTasks::stepAdd($pdo, $task, $see, $do, $confirm, $by, $tz); $added++;
        }
        $on = array_column(GaugeTasks::adrs($pdo, $task), 'adr');
        foreach ((array) ($p['adrs'] ?? []) as $a) {
            if (in_array((string) $a, $on, true)) continue;
            try { GaugeTasks::adrAttach($pdo, $task, (string) $a, $by, $tz); $att++; } catch (RuntimeException $x) {}   // not in the Decisions list: left for a person
        }
        return 'Accepted the IT Manager\'s triage: ' . $added . ' check' . ($added === 1 ? '' : 's') . ' added as steps, ' . $att . ' decision' . ($att === 1 ? '' : 's') . ' attached. It\'s in its History.';
    }
}
