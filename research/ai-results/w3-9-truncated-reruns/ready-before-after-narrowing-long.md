=== FILE: files/app/lib/BeforeAfter.php ===
<?php
// app/lib/BeforeAfter.php — Piece 3's before-and-after check, the site's side (decisions/0179 rule 2 and its notes "How the
// Team's capacity grows" §4 and "Piece 3's decisions"; handoffs/piece3-check.md §3, P3-2). A release from git, while the
// switch before_after is on, waits before it goes on staging: GitHub's run (.github/workflows/beforeafter.yml) looks at
// staging as it is (DB1: never live), then the release is staged as before, then the run looks again and writes its
// verdict to site-audit/results/beforeafter/<N>.json on main. Releases::checkedByAdvocates() adds it to the release's
// checks: a pass goes on to publish (or holds for the batch), a block withdraws it and puts the failure in the Work queue
// (DB2), an incomplete look holds it for a person. The minutes a day the runs may use (DB4) are counted here; once spent,
// a release holds for the batch. Run by releases_tick every 10 minutes (advance()). Never on staging.
//
// Narrowed to what 0179 says it checks (release before-after-narrowing): a block stands only on one of the note's four rules
// (an access or privacy leak, or lost access or errors on a valid view, 0066 included; a layout or phone regression; missing
// data or a broken core flow; out-of-scope drift without a reason). A finding outside them is said on the release's card,
// never a block; drift from the build brief's plan opens a ticket, never a block or a rollback (decisions/0180 §8). A block
// whose rule this list doesn't know still blocks, as before (narrow()).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class BeforeAfter
{
    public const SWITCH = 'before_after';
    public const LATE_MINUTES = 60;        // no answer in an hour: asked again once
    public const GIVE_UP_MINUTES = 120;    // still nothing: a ticket in the Work queue for a person
    public const NOTE_MINUTES = 30;        // no verdict file yet: said once on the release's card (the run takes 15 to 25 minutes)
    public const ITEM = 'Before and after (advocates)';

    /** 0179's four rules (its note "How the Team's capacity grows" §4): only these block a change. key => words. */
    public const RULES = [
        'access' => 'an access or privacy leak, or lost access or errors on a valid view (0066 included)',
        'layout' => 'a layout or phone regression',
        'data' => 'missing data or a broken core flow',
        'drift' => 'out-of-scope drift without a reason',
    ];
    /** A block's rule in the verdict file that isn't one of the four: said on the card, never a block. rule => why. */
    public const REPORT_ONLY = [
        'alt' => 'An image without alt text isn\'t one of 0179\'s four rules: reported for Site health, not blocked.',
        'unnamed' => 'A control without a name isn\'t one of 0179\'s four rules: reported for Site health, not blocked.',
        'dialog' => 'A browser dialog isn\'t one of 0179\'s four rules: reported, not blocked.',
        'area' => 'It reaches a high-risk territory: named for its Tier 4 review (decisions/0180 §4), never a block by itself.',
    ];
    /** Drift from the plan: a ticket, never a block or a rollback (decisions/0180 §8; piece3-check.md §2.4). rule => why. */
    public const TICKET_ONLY = [
        'plan' => 'A file its build brief didn\'t name (decisions/0180 §8: drift from the plan opens a ticket, never a block or a rollback).',
    ];

    /** Tests replace these: fn(string $event, array $payload): string ('' = asked), and fn(string $path): ?string. */
    public static $askFn = null;
    public static $readFn = null;
    /** Test seam: stands in for Releases::checkedByAdvocates (tools/piece3-tests/tickverdict.php); null in use. */
    public static $decideFn = null;

    public static function on(PDO $pdo): bool
    {
        require_once __DIR__ . '/Staging.php'; if (Staging::here()) return false;
        require_once __DIR__ . '/Switches.php';
        $s = Switches::all($pdo)[self::SWITCH] ?? null;
        // any state but off (Jason's note on 0179: "Turn on P3-2's before-and-after switch with it"): a release's switch steps
        // (staff and test accounts, then a share) are about who sees a feature, and this check runs as the system with no one
        // signed in, so read as "staff first" it was off until step 3 (2026-11-26, #297 to #302 went out unchecked)
        return $s && !$s['killed'] && $s['state'] !== 'off';
    }

    // ---- the record of each release's check (site_release_settings, the Releases page's own table) ----------------------

    public static function state(PDO $pdo, int $id): ?array
    {
        try { $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute(['ba:' . $id]); $v = $st->fetchColumn(); } catch (Throwable $x) { return null; }
        $d = $v === false ? null : json_decode((string) $v, true);
        return is_array($d) ? $d : null;
    }

    private static function put(PDO $pdo, int $id, ?array $d): void
    {
        if ($d === null) { $pdo->prepare('DELETE FROM site_release_settings WHERE k = ?')->execute(['ba:' . $id]); return; }
        $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba:' . $id, json_encode($d, JSON_UNESCAPED_SLASHES)]);
    }

    /** Every release waiting on the check: id => its record. */
    public static function waiting(PDO $pdo): array
    {
        $out = [];
        try { foreach ($pdo->query("SELECT k, v FROM site_release_settings WHERE k LIKE 'ba:%'")->fetchAll(PDO::FETCH_ASSOC) as $r) { $d = json_decode((string) $r['v'], true); if (is_array($d) && preg_match('/^ba:(\d+)$/', $r['k'], $m)) $out[(int) $m[1]] = $d; } } catch (Throwable $x) {}
        ksort($out);
        return $out;
    }

    // ---- the daily budget (DB4) ------------------------------------------------------------------------------------------

    /** [minutes used today (UTC), the day's budget or null when it isn't set yet]. */
    public static function budget(PDO $pdo): array
    {
        $get = function (string $k) use ($pdo) { try { $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute([$k]); return $st->fetchColumn(); } catch (Throwable $x) { return false; } };
        $used = json_decode((string) ($get('ba_used') ?: ''), true);
        $mins = is_array($used) && ($used['day'] ?? '') === gmdate('Y-m-d') ? (float) $used['minutes'] : 0.0;
        $b = $get('ba_budget');
        return [round($mins, 1), ($b === false || $b === '' || !is_numeric($b)) ? null : (int) $b];
    }

    public static function setBudget(PDO $pdo, ?int $minutes): void
    {
        $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba_budget', $minutes === null ? '' : (string) max(0, $minutes)]);
    }

    private static function spend(PDO $pdo, float $seconds): void
    {
        [$mins] = self::budget($pdo);
        $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba_used', json_encode(['day' => gmdate('Y-m-d'), 'minutes' => round($mins + $seconds / 60, 2)])]);
    }

    /** The advocates' own daily audit (AdvocatesAudit) counts its GitHub minutes in the same day's budget (DB4). */
    public static function countMinutes(PDO $pdo, float $seconds): void { if ($seconds > 0) self::spend($pdo, $seconds); }

    public static function spent(PDO $pdo): bool { [$used, $cap] = self::budget($pdo); return $cap !== null && $used >= $cap; }

    // ---- asking GitHub, reading the verdict ------------------------------------------------------------------------------

    private static function ask(int $id, string $phase, array $d): string
    {
        $payload = ['release' => (string) $id, 'phase' => $phase, 'zip' => (string) ($d['zip'] ?? ''), 'blob' => (string) ($d['blob'] ?? '')];
        if (self::$askFn) return (string) (self::$askFn)('before-after', $payload);
        require_once __DIR__ . '/AuditClock.php';
        return AuditClock::askGitHub('before-after', $payload);
    }

    /** The run's verdict file, checked field by field, narrowed to 0179's four rules (narrow()); null when there's none yet. */
    public static function verdict(int $id): ?array
    {
        $path = 'site-audit/results/beforeafter/' . $id . '.json';
        if (self::$readFn) $raw = (self::$readFn)($path);
        else { require_once __DIR__ . '/DocsRepo.php'; $raw = DocsRepo::now($path); }   // through GitHub's API, as main is now (2026-11-27: raw reads came back empty for an hour)
        $v = $raw === null ? null : json_decode((string) $raw, true);
        if (!is_array($v) || (string) ($v['release'] ?? '') !== (string) $id) return null;
        $s = fn ($k, $n = 400) => mb_substr(trim((string) ($v[$k] ?? '')), 0, $n);
        return self::narrow(['phase' => in_array($v['phase'] ?? '', ['before-done', 'after-done'], true) ? $v['phase'] : '', 'verdict' => in_array($v['verdict'] ?? '', ['waiting', 'pass', 'block', 'incomplete'], true) ? $v['verdict'] : '',
                'first' => $s('first'), 'run' => preg_match('#^https://github\.com/degraffics/traversence/actions/runs/\d+$#', (string) ($v['run'] ?? '')) ? (string) $v['run'] : '',
                'blocks' => (int) ($v['blocks_total'] ?? 0), 'views' => (int) ($v['views'] ?? 0), 'seconds' => (float) ($v['seconds'] ?? 0),
                // its slow pages reached only through a shared file, said on the release's card (the CEO's Tier 4 condition, 2026-11-27)
                'far' => self::farLines((array) ($v['far'] ?? []))], $v);
    }

    /**
     * A block stands only on 0179's four rules (its note §4). $out: the verdict as read; $raw: the file. Each listed block
     * (the file's "blocks": [{who, page, size, rule, what}]) whose rule is REPORT_ONLY becomes a line on the card, and one
     * TICKET_ONLY becomes a ticket; any other rule, one of the four or one this list doesn't know, still blocks. The verdict
     * turns to a pass only when every block the file counts is listed and none is left: a list short of its total keeps the
     * block, as before. Adds 'reports', 'tickets' and 'narrowed' (how many blocks were set aside).
     */
    public static function narrow(array $out, array $raw): array
    {
        $out += ['reports' => [], 'tickets' => [], 'narrowed' => 0];
        $list = $raw['blocks'] ?? null;
        if (($out['verdict'] ?? '') !== 'block' || !is_array($list)) return $out;
        $kept = []; $seen = 0;
        foreach ($list as $b) {
            if (!is_array($b)) continue;
            $seen++; $rule = strtolower(trim(is_scalar($b['rule'] ?? null) ? (string) $b['rule'] : ''));
            if (isset(self::REPORT_ONLY[$rule])) { if (count($out['reports']) < 5) $out['reports'][] = self::blockLine($b) . ' ' . self::REPORT_ONLY[$rule]; continue; }
            if (isset(self::TICKET_ONLY[$rule])) { if (count($out['tickets']) < 5) $out['tickets'][] = self::blockLine($b); continue; }
            $kept[] = $b;   // one of the four, or a rule not known here: it blocks, as before
        }
        $out['narrowed'] = $seen - count($kept);
        if ($out['narrowed'] === 0 || $seen < (int) $out['blocks']) return $out;   // what the list leaves out can't be shown to be outside the four
        if ($kept) { $out['blocks'] = count($kept); $out['first'] = mb_substr(self::blockLine($kept[0]), 0, 400); return $out; }
        $out['verdict'] = 'pass'; $out['blocks'] = 0; $out['first'] = '';
        $out['reports'][] = 'Before-and-after check: passed on 0179\'s four rules; ' . $out['narrowed'] . ' finding' . ($out['narrowed'] === 1 ? '' : 's') . ' outside them reported, not blocked.';
        return $out;
    }

    /** One listed block as the card's sentence: only its own fields, checked; an email address (a test account's marker) never copied. */
    private static function blockLine(array $b): string
    {
        $who = is_scalar($b['who'] ?? null) ? (string) $b['who'] : ''; $page = is_scalar($b['page'] ?? null) ? (string) $b['page'] : '';
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $who)) $who = '';
        if (!preg_match('#^/[A-Za-z0-9/._?=&%-]{0,200}$#', $page)) $page = '';
        $at = ($b['size'] ?? '') === 'phone' ? '390' : '1280';
        $w = $b['what'] ?? ($b['evidence'] ?? '');
        $what = is_scalar($w) ? (string) $w : '';
        $what = preg_replace('/[^\s@]+@[^\s@]+/u', '(an address)', $what);
        $what = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $what)), 0, 160);
        return 'Before-and-after check: ' . ($page !== '' ? $page : 'a page') . ($who !== '' ? ' (as the test ' . ucfirst(str_replace('-', ' ', $who)) . ', at ' . $at . ')' : '') . ($what !== '' ? ': ' . rtrim($what, '.') : '') . '.';
    }

    // ---- the steps ---------------------------------------------------------------------------------------------------------

    /**
     * A release just taken from git: while the check is on, it waits for its before look instead of going on staging. Returns
     * the line for the run, or '' when the check isn't on (the release then goes on as it always has). $hold: its release.json
     * sends it for review.
     */
    public static function begin(PDO $pdo, int $id, string $zip, string $blob, bool $hold): string
    {
        if (!self::on($pdo) || !preg_match('/^[0-9a-f]{40}$/', $blob)) return '';
        $d = ['phase' => 'before', 'asked_at' => time(), 'zip' => mb_substr($zip, 0, 120), 'blob' => $blob, 'hold' => $hold, 'tries' => 1];
        // staging closed (a fresh copy going in, or one stopped part way: every page answers 503): a look now would be of
        // nothing, so it isn't asked until staging opens; the 10-minute step asks then (2026-11-26)
        $why = ($closed = self::closed($pdo)) !== '' ? $closed : self::ask($id, 'before', $d);
        if ($why !== '') { $d['asked_at'] = 0; $d['ask_error'] = $why; }
        self::put($pdo, $id, $d);
        require_once __DIR__ . '/Releases.php';
        Releases::noteFromCheck($pdo, $id, $closed !== '' ? 'Waiting for its before look: ' . $closed . '.' : ($why === '' ? 'Waiting for its before look: the advocates look at staging as it is, then it goes on staging (the before-and-after check).' : 'Waiting for its before look, but GitHub wasn\'t asked yet (' . $why . '); the site asks again within 10 minutes.'));
        return 'Release #' . $id . ': waiting for its before look (the advocates).';
    }

    /** The 10-minute step: each waiting release moves on when its look is back. Lines for the run's note. */
    public static function advance(PDO $pdo): array
    {
        if (self::staging()) return [];
        require_once __DIR__ . '/Releases.php';
        // one at a time (releases_tick and the ping from beforeafter.yml, 2026-11-27), so a release is never put on staging twice
        $got = 1; try { $got = (int) $pdo->query("SELECT GET_LOCK('tv_rel_ba', 0)")->fetchColumn(); } catch (Throwable $x) { $got = 1; }
        if ($got !== 1) return [];
        $out = [];
        try {
            foreach (self::waiting($pdo) as $id => $d) {
                try { $l = self::step($pdo, $id, $d); if ($l !== '') $out[] = $l; }
                catch (Throwable $e) { $out[] = 'Release #' . $id . ' (before-and-after): ' . $e->getMessage(); }
            }
        } finally { try { $pdo->query("SELECT RELEASE_LOCK('tv_rel_ba')"); } catch (Throwable $x) {} }
        return $out;
    }

    /**
     * The verdict's far list as the card's sentences, at most three (the Design reviewer's words, 2026-11-27): a slow page the
     * release reaches only through a file nearly every page loads, tried again after the look. "blocks" isn't one: its block
     * says it. Only what the check gives in its own fields: an account's slug, the page's address, the width, the file.
     */
    public static function farLines(array $far): array
    {
        $out = [];
        foreach ($far as $x) {
            if (!is_array($x) || !in_array($x['outcome'] ?? '', ['opened', 'before_too'], true)) continue;
            $who = (string) ($x['who'] ?? ''); $page = (string) ($x['page'] ?? '');
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $who) || !preg_match('#^/[A-Za-z0-9/._?=&%-]{0,200}$#', $page)) continue;
            $via = array_values(array_filter(array_map('strval', (array) ($x['via'] ?? [])), fn ($f) => (bool) preg_match('#^[A-Za-z0-9/_.-]{1,120}$#', $f)));
            $at = ($x['size'] ?? '') === 'phone' ? '390' : '1280';
            $head = 'Before-and-after check: ' . $page . ' (as the test ' . ucfirst(str_replace('-', ' ', $who)) . ', at ' . $at . ') ';
            $tail = ' Staging is slow there; it\'s reached only through ' . ($via ? implode(', ', $via) : 'a file nearly every page loads') . '.';
            $out[] = $x['outcome'] === 'opened'
                ? $head . 'timed out, then opened when tried again.' . $tail . ' The IT Manager\'s lane has it.'
                : $head . 'was slow before this release too, and still didn\'t open after it.' . $tail . ' The slow-pages ticket has it.';
            if (count($out) === 3) break;
        }
        return $out;
    }

    /** Tests only: fn(): cron lines, and fn(job, preset) for Scheduler::change, in place of cPanel. */
    public static $linesFn = null;
    public static $changeFn = null;

    /**
     * releases_tick's own cron line to every 10 minutes, as Scheduler::JOBS sets it and this check counts on (piece3-check.md
     * §3: "releases_tick (every 10 minutes)"), once (2026-11-27: live's line read every 24 minutes, so each step of a release waited up to
     * 24 minutes; the session manager's ruling). Done once and remembered: a later Change on Scheduled tasks is a person's
     * and stays. Waits, saying nothing, while cPanel isn't connected. Returns the line for the run's note ('' for none).
     */
    public static function tickLine(PDO $pdo): string
    {
        if (self::staging()) return '';
        require_once __DIR__ . '/Releases.php'; Releases::ensure($pdo);
        $st = $pdo->prepare('SELECT v FROM site_release_settings WHERE k = ?'); $st->execute(['tick_line_10min']);
        if ($st->fetchColumn() !== false) return '';
        require_once __DIR__ . '/Scheduler.php';
        if (!self::$linesFn && !CPanel::ready()) return '';
        $line = Scheduler::lineFor(self::$linesFn ? (self::$linesFn)() : Scheduler::cronLines(), 'releases_tick');
        $was = $line['minute'] ?? null; $msg = '';
        if ($line && $was !== '*/10') { self::$changeFn ? (self::$changeFn)('releases_tick', '10min') : Scheduler::change('releases_tick', '10min'); $msg = 'releases_tick: its cron line moved from ' . $was . ' to */10.'; }
        $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['tick_line_10min', json_encode(['at' => gmdate('c'), 'was' => $was])]);
        return $msg;
    }

    private static function staging(): bool { require_once __DIR__ . '/Staging.php'; return Staging::here(); }

    /** Why staging can't be looked at now ('' when it can): a fresh copy going in, or one that stopped part way. */
    private static function closed(PDO $pdo): string
    {
        require_once __DIR__ . '/StagingSite.php';
        if (StagingSite::copyHold($pdo) === '') return '';
        return 'staging is closed until a fresh copy finishes (' . (StagingSite::running($pdo) ? 'one is going in now' : 'the last one stopped part way: Start staging over opens it') . '); it\'s asked for once staging opens';
    }

    /** After a verdict is decided: what it set aside, on the card, and drift from the plan as one Work ticket (0180 §8). Never throws. */
    private static function afterNotes(PDO $pdo, int $id, array $v): void
    {
        try {
            foreach ((array) ($v['reports'] ?? []) as $n) Releases::noteFromCheck($pdo, $id, (string) $n);
            if (!empty($v['tickets'])) {
                require_once __DIR__ . '/GaugeTasks.php';
                $t = GaugeTasks::add($pdo, 'area:before-after', 'Release #' . $id . ': files its build brief didn\'t name',
                    mb_substr(implode(' · ', (array) $v['tickets']), 0, 300) . ' ' . self::TICKET_ONLY['plan'], 'the before-and-after check, release #' . $id, 'the before-and-after check', 0);
                Releases::noteFromCheck($pdo, $id, 'Before-and-after check: files its build brief didn\'t name; task #' . $t . ' in the Work queue (not a block).');
            }
        } catch (Throwable $x) { error_log('before-after notes: ' . $x->getMessage()); }
    }

    private static function step(PDO $pdo, int $id, array $d): string
    {
        $r = Releases::get($pdo, $id);
        // a release withdrawn, replaced or published some other way meanwhile: nothing left to wait for
        if (!$r || in_array($r['state'], ['withdrawn', 'live', 'off', 'rolled_back'], true)) { self::put($pdo, $id, null); return ''; }
        if ((int) ($d['asked_at'] ?? 0) === 0) {   // GitHub wasn't reached, or staging was closed: ask now, once staging is open
            if (($closed = self::closed($pdo)) !== '') {
                if (($d['ask_error'] ?? '') !== $closed) { $d['ask_error'] = $closed; self::put($pdo, $id, $d); Releases::noteFromCheck($pdo, $id, 'Waiting for its ' . $d['phase'] . ' look: ' . $closed); }
                return '';
            }
            $why = self::ask($id, (string) $d['phase'], $d);
            if ($why === '' && $d['phase'] === 'before' && !empty($d['ask_error'])) Releases::noteFromCheck($pdo, $id, 'Waiting for its before look: asked for now (the advocates look at staging as it is, then it goes on staging).');
            if ($why === '') { $d['asked_at'] = time(); unset($d['ask_error']); } else $d['ask_error'] = $why;
            self::put($pdo, $id, $d);
            return $why === '' ? 'Release #' . $id . ': asked for its ' . $d['phase'] . ' look.' : 'Release #' . $id . ': GitHub still not asked (' . $why . ').';
        }
        $v = self::verdict($id);
        if ($d['phase'] === 'before' && $v && $v['phase'] === 'before-done') {
            if (empty($d['counted_before'])) { self::spend($pdo, $v['seconds']); $d['counted_before'] = 1; self::put($pdo, $id, $d); }   // once, though staging may not take it this run
            // already on staging (put there some other way): nothing to approve again, straight to its after look (#304's
            // "its plan needs a look: Its plan was approved already", 2026-11-26)
            $line = $r['state'] === 'staged' ? 'Release #' . $id . ': on staging already.' : Releases::straightOn($pdo, $id, 0);
            $r = (array) Releases::get($pdo, $id);
            if (($r['state'] ?? '') !== 'staged') return $line . ' (its before look is in; it goes on staging when it can)';
            if (!$r['checks_ok']) { $w = Releases::selfWithdraw($pdo, $r); self::put($pdo, $id, null); return $line . ($w !== '' ? ' ' . $w : ''); }   // its waiting cleared only once that's done
            $d['phase'] = 'after'; $d['asked_at'] = time(); $d['tries'] = 1; $d['before_seconds'] = $v['seconds']; unset($d['noted_missing']);   // the after verdict counts the before looks again
            $why = self::ask($id, 'after', $d); if ($why !== '') { $d['asked_at'] = 0; $d['ask_error'] = $why; }
            self::put($pdo, $id, $d);
            Releases::noteFromCheck($pdo, $id, 'On staging and its checks passed; the advocates look again now (the before-and-after check).');
            return 'Release #' . $id . ': on staging; its after look asked for.';
        }
        if ($d['phase'] === 'after' && $v && $v['phase'] === 'after-done' && $v['verdict'] !== 'waiting') {
            // decided first, its waiting cleared after: a step that throws (a permission, the database) leaves it waiting, so the
            // next run decides it again, never a release left on staging with nothing waiting for it (2026-11-27)
            if (empty($d['counted_after'])) { self::spend($pdo, max(0.0, $v['seconds'] - (float) ($d['before_seconds'] ?? 0))); $d['counted_after'] = 1; self::put($pdo, $id, $d); }   // once, as the before look's
            $line = self::$decideFn ? (string) (self::$decideFn)($id, $v) : Releases::checkedByAdvocates($pdo, $id, $v, (bool) ($d['hold'] ?? false) || self::spent($pdo));
            self::put($pdo, $id, null);
            foreach ($v['far'] as $n) Releases::noteFromCheck($pdo, $id, $n);   // kept on its card and in the daily summary's record
            self::afterNotes($pdo, $id, $v);   // what 0179's four rules set aside, and drift from the plan as a ticket
            return $line;
        }
        // late: asked again once, then a person is told in the Work queue
        $mins = (time() - (int) $d['asked_at']) / 60;
        // no verdict on main yet past NOTE_MINUTES: said once on the release's card and record, with where it's read (2026-11-27:
        // #318 waited an hour and its job, quiet by design, said nothing of why)
        if (!$v && $mins >= self::NOTE_MINUTES && empty($d['noted_missing'])) {
            $d['noted_missing'] = 1; self::put($pdo, $id, $d);
            Releases::noteFromCheck($pdo, $id, 'Waiting for its ' . $d['phase'] . ' look: no verdict file on main yet (site-audit/results/beforeafter/' . $id . '.json), asked ' . (int) $mins . ' minutes ago; the site reads it again every 10 minutes.');
        }
        if ($mins >= self::GIVE_UP_MINUTES && empty($d['ticket'])) {
            require_once __DIR__ . '/GaugeTasks.php';
            $d['ticket'] = GaugeTasks::auditOpen($pdo, 'ba-late:' . $id, 'The before-and-after check didn\'t answer for release #' . $id,
                'Its ' . $d['phase'] . ' look was asked for ' . (int) $mins . ' minutes ago and twice, with no verdict on main (site-audit/results/beforeafter/' . $id . '.json). The release waits on staging and never publishes without it. Look at the workflow\'s runs: https://github.com/degraffics/traversence/actions/workflows/beforeafter.yml', 2, 'system · before-and-after check');
            self::put($pdo, $id, $d);
            return 'Release #' . $id . ': no answer from the before-and-after check in ' . (int) $mins . ' minutes; task #' . $d['ticket'] . ' in the Work queue.';
        }
        if ($mins >= self::LATE_MINUTES && (int) ($d['tries'] ?? 1) < 2) {
            $why = self::ask($id, (string) $d['phase'], $d); $d['tries'] = 2;
            self::put($pdo, $id, $d);
            return 'Release #' . $id . ': no answer in ' . (int) $mins . ' minutes; asked for its ' . $d['phase'] . ' look again' . ($why !== '' ? ' (' . $why . ')' : '') . '.';
        }
        return '';
    }

    /** The line the Releases page shows for a release still waiting (or '' when it isn't). */
    public static function words(PDO $pdo, int $id): string
    {
        $d = self::state($pdo, $id); if (!$d) return '';
        $mins = $d['asked_at'] ? (int) ((time() - (int) $d['asked_at']) / 60) : 0;
        return ($d['phase'] === 'before' ? 'Waiting for its before look' : 'Being checked by the advocates') . ($d['asked_at'] ? ' (asked ' . $mins . ' min ago)' : (str_starts_with((string) ($d['ask_error'] ?? ''), 'staging is closed') ? ': ' . $d['ask_error'] : ' (GitHub not asked yet: ' . ($d['ask_error'] ?? '') . ')')) . '.';
    }
}
=== END FILE ===

=== FILE: files/app/lib/AdvocatesAudit.php ===
<?php
// app/lib/AdvocatesAudit.php — the advocates' own audit, on a schedule and independent of the builders (decisions/0180 §5:
// the test-account advocates do QA verification and experience tickets; its note "roles check one another": releases are
// checked by the advocates A1–A12). Between releases, once a day, the advocates look at staging as their test accounts
// (GitHub's run, as the before-and-after check's looks are), and a finding seen on two runs in a row opens one experience
// ticket in the Work queue, for the lane that owns the screen. Run by advocates_tick (hourly; it asks once a day).
//
// The rules it keeps:
//   - Independent of the builders: started only by its schedule, never by a release, a build session or a ticket; it reads
//     no build brief, and nothing a builder does can start, skip or quiet it.
//   - It never blocks, withdraws, holds or publishes a release, and never rolls one back (0180 §8: rollback is the release
//     checks' rules only). It waits while a release is being checked (that look comes first, and staging is changing).
//   - An advocate never builds or moves a ticket (0180 §5): each ticket opens unowned at Planning; its lane's manager or a
//     person takes it. Withdraw on the Work queue is its Undo.
//   - Read only, as the test accounts and staff test logins: no form sent, never the Founder, never live's people (0165 §3).
//     A test account's marker (its address) is never copied into a ticket.
//   - $0: GitHub's minutes only, counted in the before-and-after check's daily budget (decisions/0179 DB4); none asked once
//     the day's budget is spent. No AI.
//   - Off until its switch advocates_audit is on. On staging it does nothing (asked from live, as AuditClock asks).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class AdvocatesAudit
{
    public const SWITCH = 'advocates_audit';
    public const EVENT = 'advocates-audit';
    /** Asked once a day from this hour (UTC), clear of the site audit's 7:00, 9:00, 10:00 and 19:00. */
    public const HOUR_UTC = 4;
    public const RESULT = 'site-audit/results/advocates/latest.json';
    /** A finding opens its ticket on its second run in a row (the audit's two looks, piece2-team.md §6). */
    public const RUNS_TO_OPEN = 2;
    /** At most this many tickets opened a run. */
    public const MAX_TICKETS = 5;
    /** What a finding may be: 0179's four rules that a single look can show, and an experience finding (0180 §5). */
    public const RULES = [
        'access' => 'lost access or an error on a valid view',
        'layout' => 'a layout or phone problem',
        'data' => 'missing data or a broken core flow',
        'friction' => 'friction in the experience',
    ];

    /** Tests replace these: fn(string $event, array $payload): string ('' = asked), and fn(string $path): ?string. */
    public static $askFn = null;
    public static $readFn = null;

    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS advocate_audit_seen (akey CHAR(44) NOT NULL PRIMARY KEY, who VARCHAR(40) NOT NULL DEFAULT '', page VARCHAR(200) NOT NULL DEFAULT '',
            size VARCHAR(8) NOT NULL DEFAULT '', rule VARCHAR(20) NOT NULL DEFAULT '', what VARCHAR(300) NOT NULL DEFAULT '', runs INT NOT NULL DEFAULT 0,
            last_run VARCHAR(160) NOT NULL DEFAULT '', task_id INT UNSIGNED NULL, at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS advocate_audit_state (k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private static function state(PDO $pdo, string $k): string
    {
        try { $st = $pdo->prepare('SELECT v FROM advocate_audit_state WHERE k = ?'); $st->execute([$k]); $v = $st->fetchColumn(); return $v === false ? '' : (string) $v; } catch (Throwable $x) { return ''; }
    }

    private static function setState(PDO $pdo, string $k, string $v): void
    {
        $pdo->prepare('REPLACE INTO advocate_audit_state (k, v, at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$k, mb_substr($v, 0, 255)]);
    }

    /** Is today's look due? Once a day, from HOUR_UTC. */
    public static function due(int $hourUtc, string $lastDay, string $today): bool { return $lastDay !== $today && $hourUtc >= self::HOUR_UTC; }

    /** One finding's key: the same advocate, page, size and rule is the same finding. */
    public static function key(array $f): string { return 'adv:' . sha1($f['who'] . '|' . $f['page'] . '|' . $f['size'] . '|' . $f['rule']); }

    /**
     * The run's result file, checked field by field; null when there's none or it isn't one. {run, at, seconds, views,
     * findings: [{who, page, size, rule, what, lane}]}, at most 200 findings.
     */
    public static function parse(?string $raw): ?array
    {
        $v = $raw === null ? null : json_decode($raw, true);
        if (!is_array($v)) return null;
        $run = (string) ($v['run'] ?? '');
        if (!preg_match('#^https://github\.com/degraffics/traversence/actions/runs/\d+$#', $run)) return null;
        $out = ['run' => $run, 'at' => preg_match('/^\d{4}-\d{2}-\d{2}T[\d:.]+Z?$/', (string) ($v['at'] ?? '')) ? (string) $v['at'] : '',
            'seconds' => max(0.0, (float) ($v['seconds'] ?? 0)), 'views' => (int) ($v['views'] ?? 0), 'findings' => []];
        foreach ((array) ($v['findings'] ?? []) as $f) {
            if (!is_array($f)) continue;
            $who = is_scalar($f['who'] ?? null) ? (string) $f['who'] : ''; $page = is_scalar($f['page'] ?? null) ? (string) $f['page'] : '';
            $rule = is_scalar($f['rule'] ?? null) ? (string) $f['rule'] : '';
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $who) || !preg_match('#^/[A-Za-z0-9/._?=&%-]{0,200}$#', $page) || !isset(self::RULES[$rule])) continue;
            $what = is_scalar($f['what'] ?? null) ? (string) $f['what'] : '';
            $what = preg_replace('/[^\s@]+@[^\s@]+/u', '(an address)', $what);
            $what = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $what)), 0, 300);
            $lane = is_scalar($f['lane'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', (string) $f['lane']) ? (string) $f['lane'] : '';
            $out['findings'][] = ['who' => $who, 'page' => $page, 'size' => ($f['size'] ?? '') === 'phone' ? 'phone' : 'desk', 'rule' => $rule, 'what' => $what, 'lane' => $lane];
            if (count($out['findings']) === 200) break;
        }
        return $out;
    }

    /** One run: read the last look, then ask for today's when it's due. A line for the job's log ('' on staging). */
    public static function tick(PDO $pdo): string
    {
        require_once __DIR__ . '/Staging.php'; if (Staging::here()) return '';
        require_once __DIR__ . '/Switches.php';
        try { if (!Switches::on($pdo, self::SWITCH)) return 'The advocates\' audit is switched off (System Configuration → Switches, ' . self::SWITCH . ').'; } catch (Throwable $x) { return 'The switches couldn\'t be read: ' . $x->getMessage(); }
        self::ensure($pdo);
        if ((int) $pdo->query("SELECT GET_LOCK('tv_adv_audit', 0)")->fetchColumn() !== 1) return 'Another run is going: this one did nothing.';
        try {
            $out = array_values(array_filter([self::apply($pdo), self::ask($pdo)]));
            return $out ? implode(' ', $out) : 'Nothing to do: today\'s look is asked for or not due, and no new result.';
        } finally { try { $pdo->query("SELECT RELEASE_LOCK('tv_adv_audit')"); } catch (Throwable $x) {} }
    }

    /** Ask GitHub for today's look, once, when it's due and nothing comes first. */
    public static function ask(PDO $pdo): string
    {
        $today = gmdate('Y-m-d');
        if (!self::due((int) gmdate('G'), self::state($pdo, 'asked_day'), $today)) return '';
        require_once __DIR__ . '/BeforeAfter.php';
        if (BeforeAfter::waiting($pdo)) return 'Today\'s look waits: a release is being checked by the advocates (its look comes first).';
        if (BeforeAfter::spent($pdo)) return 'Today\'s look waits: the day\'s GitHub minutes are spent (decisions/0179, DB4).';
        require_once __DIR__ . '/StagingSite.php';
        if (StagingSite::copyHold($pdo) !== '') return 'Today\'s look waits: staging is closed until a fresh copy finishes.';
        $payload = ['day' => $today, 'advocates' => 'all'];
        if (self::$askFn) $why = (string) (self::$askFn)(self::EVENT, $payload);
        else { require_once __DIR__ . '/AuditClock.php'; $why = AuditClock::askGitHub(self::EVENT, $payload); }
        if ($why !== '') return 'GitHub wasn\'t asked for today\'s look (' . $why . '); asked again next hour.';
        self::setState($pdo, 'asked_day', $today);
        return 'Asked GitHub for today\'s look (the advocates, on staging).';
    }

    /** Read the last look once: count its minutes, keep each finding's runs, open a ticket on its second run in a row. */
    public static function apply(PDO $pdo): string
    {
        if (self::$readFn) $raw = (self::$readFn)(self::RESULT);
        else { require_once __DIR__ . '/DocsRepo.php'; $raw = DocsRepo::now(self::RESULT); }
        $r = self::parse($raw === null ? null : (string) $raw);
        if (!$r || $r['run'] === self::state($pdo, 'applied_run')) return '';
        require_once __DIR__ . '/BeforeAfter.php';
        BeforeAfter::countMinutes($pdo, $r['seconds']);
        $now = []; foreach ($r['findings'] as $f) $now[self::key($f)] = $f;
        // a finding not seen this run starts again; one whose ticket is open stays with its ticket
        foreach ($pdo->query('SELECT akey, task_id FROM advocate_audit_seen')->fetchAll(PDO::FETCH_ASSOC) as $row)
            if (!isset($now[$row['akey']]) && $row['task_id'] === null) $pdo->prepare('DELETE FROM advocate_audit_seen WHERE akey = ?')->execute([$row['akey']]);
        require_once __DIR__ . '/GaugeTasks.php';
        $new = 0; $opened = 0;
        foreach ($now as $k => $f) {
            $st = $pdo->prepare('SELECT * FROM advocate_audit_seen WHERE akey = ?'); $st->execute([$k]); $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $pdo->prepare('INSERT INTO advocate_audit_seen (akey, who, page, size, rule, what, runs, last_run, at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, UTC_TIMESTAMP())')
                    ->execute([$k, $f['who'], $f['page'], $f['size'], $f['rule'], $f['what'], $r['run']]);
                $new++; continue;
            }
            if ($row['last_run'] === $r['run']) continue;
            $runs = (int) $row['runs'] + 1; $task = $row['task_id'] === null ? null : (int) $row['task_id'];
            if ($task !== null) {
                $t = GaugeTasks::task($pdo, $task);
                if ($t && $t['undone_at'] !== null) { $pdo->prepare('UPDATE advocate_audit_seen SET last_run = ? WHERE akey = ?')->execute([$r['run'], $k]); continue; }   // withdrawn by a person: never opened again
                if (!$t || $t['cleared_at'] !== null) { $task = null; $runs = 1; }   // done, and seen again: it starts over
            }
            if ($task === null && $runs >= self::RUNS_TO_OPEN && $opened < self::MAX_TICKETS) { $task = self::open($pdo, $f, $r['run']); $opened++; }
            $pdo->prepare('UPDATE advocate_audit_seen SET runs = ?, last_run = ?, what = ?, task_id = ? WHERE akey = ?')->execute([$runs, $r['run'], $f['what'], $task, $k]);
        }
        self::setState($pdo, 'applied_run', $r['run']);
        return 'Read the advocates\' look' . ($r['at'] !== '' ? ' of ' . $r['at'] : '') . ': ' . count($now) . ' finding' . (count($now) === 1 ? '' : 's') . ' · ' . $new . ' new · ' . $opened . ' experience ticket' . ($opened === 1 ? '' : 's') . ' opened.';
    }

    /** One experience ticket (0180 §5): Work, at Planning, unowned, its lane when the finding names one of the seven. */
    private static function open(PDO $pdo, array $f, string $run): int
    {
        $what = 'Advocate ' . ucfirst(str_replace('-', ' ', $f['who'])) . ': ' . $f['page'] . ' at ' . ($f['size'] === 'phone' ? '390' : '1280') . ', ' . self::RULES[$f['rule']];
        $why = ($f['what'] !== '' ? rtrim($f['what'], '.') . '. ' : '') . 'Seen on two of the advocates\' daily looks in a row (' . $run . '). An experience ticket (decisions/0180 §5): its lane\'s manager takes it or proposes a Planning initiative from it; the advocate never builds or moves it.';
        $id = GaugeTasks::add($pdo, 'area:advocates', $what, mb_substr($why, 0, 400), 'the advocates\' daily audit', 'the advocates\' audit', 0, '', 'work');
        try {
            require_once __DIR__ . '/TeamRunner.php';
            [$tier, , $flag] = TeamRunner::tier($what . ' ' . $f['what'], ['path:' . $f['page']]);   // by what it touches; words only flag it
            $pdo->prepare('UPDATE control_gauge_tasks SET tier = ? WHERE id = ? AND tier IS NULL')->execute([$tier, $id]);
            if ($flag !== '') GaugeTasks::flag($pdo, $id, $flag);
        } catch (Throwable $x) { error_log('advocates audit tier: ' . $x->getMessage()); }
        if ($f['lane'] !== '') {
            try { require_once __DIR__ . '/TeamRoster.php'; if (in_array($f['lane'], TeamRoster::LANES, true)) $pdo->prepare("UPDATE control_gauge_tasks SET lane = ? WHERE id = ? AND (lane IS NULL OR lane = '')")->execute([$f['lane'], $id]); }
            catch (Throwable $x) { error_log('advocates audit lane: ' . $x->getMessage()); }
        }
        return $id;
    }
}
=== END FILE ===

=== FILE: files/app/scripts/advocates_tick.php ===
<?php
// app/scripts/advocates_tick.php — the advocates' own audit, on its schedule (Scheduler::JOBS 'advocates_tick'; AdvocatesAudit).
// Hourly: reads the last look and asks GitHub for today's once it's due. Does nothing on staging; off until its switch is on.
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('advocates_tick');
require_once __DIR__ . '/../lib/AdvocatesAudit.php';

try { echo AdvocatesAudit::tick(Database::connection()), "\n"; }
catch (Throwable $e) { echo 'Stopped: ', $e->getMessage(), "\n"; exit(1); }
=== END FILE ===

=== FILE: files/app/migrations/2026-11-28_advocates_audit.sql ===
-- The advocates' own audit (AdvocatesAudit): its findings' runs and its state. Add-only (decisions/0179's note: safe additive);
-- safe to run twice. AdvocatesAudit::ensure() makes the same tables.
CREATE TABLE IF NOT EXISTS advocate_audit_seen (akey CHAR(44) NOT NULL PRIMARY KEY, who VARCHAR(40) NOT NULL DEFAULT '', page VARCHAR(200) NOT NULL DEFAULT '',
  size VARCHAR(8) NOT NULL DEFAULT '', rule VARCHAR(20) NOT NULL DEFAULT '', what VARCHAR(300) NOT NULL DEFAULT '', runs INT NOT NULL DEFAULT 0,
  last_run VARCHAR(160) NOT NULL DEFAULT '', task_id INT UNSIGNED NULL, at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS advocate_audit_state (k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
=== END FILE ===

=== FILE: files/app/lib/Scheduler.php ===
<?php
// app/lib/Scheduler.php — our scheduled scripts (app/scripts/*_tick.php, ai_worker.php) and their cron jobs in cPanel,
// set from System Configuration → Scheduled tasks through the cPanel API (CPanel::api2, module Cron). Each script
// records its own runs (run() at its top), so the page shows when each last ran and whether it worked, whether or not
// cPanel can be reached from here. Cron runs on the server's clock.
declare(strict_types=1);

require_once __DIR__ . '/AdminTables.php';
require_once __DIR__ . '/CPanel.php';

final class Scheduler
{
    /** Our jobs: key (its script, app/scripts/<key>.php) => [name, what it does, the preset it starts on, how late is "late" in minutes]. A daily
     * job's "late" is a day plus two hours (1560), never less than its own schedule: seven daily jobs said Late hours before they were
     * due (2026-11-24, Scheduled tasks on live). */
    public const JOBS = [
        'control_tick' => ['The System Control Board\'s watch', 'Every 5 minutes: takes out and puts back the maintenance windows set ahead, and catches what goes quiet (W0 Indexer, a crawler run that never reported, a site job that stopped or never finished) as alarms on the board, and emails a warning left unacknowledged a day (decisions/0171 §5).', '5min', 30],
        'team_audit_tick' => ['The Team\'s daily audit', 'On the staging copy only, while the Team\'s runner is switched on: the Operations & Reconciliation Manager reads Site health\'s findings, late or failed jobs and open alarms, opens one Audit ticket per new finding, and a day later confirms it (opening the Work or Resolution ticket it calls for) or clears it as noise (decisions/0179\'s Piece 2). On the live site it does nothing and logs nothing.', 'daily', 1560],
        'founder_mail_tick' => ['Email to the Founder', 'Every 10 minutes on the live site: today\'s daily batch and the session manager\'s messages, each emailed once to the Founder through the site\'s own email (FounderMail, Notify). Logs only when it sends.', '10min', 60],
        'team_tick' => ['The Team\'s runner', 'On the staging copy only, while its switch is on: takes one ticket from the Work queue, does one step for one working role, logs it with Undo, and stops (decisions/0179\'s Piece 2). On the live site it does nothing and logs nothing.', '10min', 60],
        'advocates_tick' => ['The advocates\' own audit', 'Once a day (from 4:00 UTC), while its switch advocates_audit is on: asks GitHub for the test-account advocates\' look at staging, on a schedule and independent of any release or build (decisions/0180 §5), and reads the result: a finding seen on two looks in a row opens one experience ticket in the Work queue for the lane that owns the screen. It never blocks, withdraws or publishes a release, waits while one is being checked, and counts its GitHub minutes in the before-and-after check\'s daily budget. Does nothing on staging.', 'hourly', 180],
        'ai_worker' => ['AI worker', 'Reads queued AI jobs: scanned business cards now, menus and brochures with the Studio.', 'minute', 15],
        'standing_tick' => ['Notices of action', 'Moves notices whose 24 hours ran out (page loads do it too, every few minutes).', '10min', 60],
        'sales_tick' => ['Lead scores', 'Scores the listings worth a call and gives new listings in a territory to its rep.', 'hourly', 180],
        'files_tick' => ['Files to the file store', 'Moves files still on the server into their owners\' folders in the file store (Cloudflare R2), a batch at a time: files from before (decisions/0124), and any upload the store didn\'t take at the time, which waits on the server and is tried again here (decisions/0163\'s source map: file storage, else uploads wait).', '10min', 60],
        'work_orders_tick' => ['Work order reminders', 'Asks vendors for an update: a work order not accepted by 9:00 am the next business day, 24 hours before its must-have time, and at it.', 'hourly', 180],
        'guide_finds_tick' => ['Find it and guide drafts', 'Looks up the names a guide mentions that we don\'t have yet (our crawler first, then web search with Claude) and sends what it confirms through the listings pipeline, which publishes the ready ones; links names once they\'re listed. And drafts one guide a run with Claude (asked-for ones first, then unwritten city and community guides), published by its score. And, while the Live events template is set running, looks for one place\'s upcoming events.', '15min', 60],
        'listings_tick' => ['Listings pipeline', 'Matches the crawler\'s waiting listings again, merges the ones we already have and publishes the ready ones by rule, without anyone opening Review.', '15min', 60],
        'guide_live_tick' => ['Guide weather and roads', 'Keeps each guide\'s weather (National Weather Service) and Arizona\'s road work (AZ 511) fresh ahead of visitors, so a guide never waits on them.', '15min', 60],
        'got_it_tick' => ['Did it arrive?', 'Reminds a client to tell us they have their order (that completes it), 72 hours after the last of it shipped or was ready for pickup, once.', 'hourly', 180],
        'qb_orders_tick' => ['QuickBooks invoices on orders', 'Reads the QuickBooks invoice each open order names (one made here, or one made by hand and linked by its number) and records any payment QuickBooks shows that isn\'t on the order yet, so the order moves on by itself (M1b). Writes nothing to QuickBooks.', 'hourly', 180],
        'pay_wait_tick' => ['Payment reminders', 'Reminds a client who chose wire or check and hasn\'t given a confirmation code (and tells support): 2 hours after, 9:00 am the next business day, and 4 pm with the other ways to pay.', '15min', 60],
        'services_tick' => ['Services & usage', 'The service register\'s upkeep (today\'s caps recorded once; watching Railway and the sync, when it\'s switched on), and the month\'s report on the 1st by email (decisions/0133, 0163). The 50/75/95% warnings are Pulse cases.', 'hourly', 180],
        'takedowns_tick' => ['Takedowns and the guide audit', 'Hides what someone asked us to take down once 24 hours pass without a person deciding (a protected reason hides it at once), and draws the quarterly sample of published guides to audit (decisions/0133).', '10min', 60],
        'heritage_tick' => ['Heritage catalog', 'Reads each guide\'s place (the Library of Congress\'s records the crawler found, its dossier and research) into vetted facts for the heritage catalog and the guides\' "Walking in the footsteps" chapter, files them in the Topic index, keeps topic summaries current and researches the most asked-for thin topics.', 'hourly', 180],
        'daily_report' => ['Daily report', 'Emails the Founder one report a day at 5 am Arizona time (Stream settings): every open Pulse case with a button to its step, and the system status. It checks each hour and sends once the hour comes.', 'hourly2', 180],
        'market_tick' => ['Marketplace check', 'Asks sellers "Still available?" of every post that\'s due, and takes off the ones asked at the last check that nobody confirmed (decisions/0135 §8). Closes offers nobody answered in 7 days and reminds a trade\'s other side to confirm (§4).', 'daily', 1560],
        'mail_sync' => ['Mailboxes in Pulse', 'Reads every connected mailbox (ours by IMAP, outside ones by IMAP, POP or Microsoft\'s Graph) into its readers\' Pulse inbox, and keeps read state in step (decisions/0172 §4.7). A refused password becomes a Reconnect message in Pulse.', '5min', 60],
        'mail_keep' => ['Mailboxes: keep 90 days', 'Our working copy of connected mailboxes keeps the last 90 days for search and threading; the mailbox itself keeps everything (decisions/0172 §4.7).', 'daily', 1560],
        'pulse_followup' => ['Pulse: follow-ups', 'Brings back, unread and on top, each item someone flagged to follow up once its time comes, and tells them (decisions/0172, Flags and tags in Pulse).', '10min', 60],
        'booking_remind' => ['Booking reminders', 'Reminds customers a day before a stay or rental and 2 hours before check-in, once each (decisions/0172 §2.5).', '15min', 60],
        'listing_messages_tick' => ['Messages left on unclaimed listings', 'Paces visitors\' requests to unclaimed listings\' emails (the 10-day notice, paused ones resumed), deletes unconfirmed guest emails after 48 hours and expires messages never claimed after 90 days, telling their senders (decisions/0172 §3.3).', 'hourly', 120],
        'notify_release' => ['Quiet hours: held email', 'Sends the email that waited for someone\'s quiet hours once they end (decisions/0172, the one dispatcher). Messages already read in their Pulse aren\'t emailed.', '10min', 60],
        'notice_digest' => ['Notices by email: weekly digest', 'Sends each person who chose a weekly email of their notices one email of the week\'s notices, through Amazon SES (decisions/0137 §6, §8). Runs daily; each person gets one every 7 days.', 'daily', 1560],
        'ses_setup_tick' => ['Email sending setup', 'Sets up Amazon SES by itself (decisions/0137 §8): the sending domains, their DNS records through the cPanel API, bounce reports, the check with AWS, asking for production access, and one test once live. Does nothing once the setup is done.', '15min', 60],
        'domains_tick' => ['Businesses\' domains', 'Checks businesses\' own domains on their storefronts (decisions/0139 §3): DNS and the certificate for the ones waiting, live ones once a day, and renews domains bought through us two weeks before they run out.', '15min', 60],
        'billing_tick' => ['Plans and renewals', 'Renews what\'s due on the card on file, retries declined cards, ends trials and sends the reminders.', 'daily', 1560],
        'region_tick' => ['Sign-up region check', 'Keeps the EU/EEA/UK address ranges for the sign-up check fresh (decisions/0027): downloads DB-IP\'s free country file (CC BY 4.0) once a month; daily it only checks.', 'daily', 1560],
        'guide_ondemand_tick' => ['Guides on demand', 'Carries on guides a searcher asked for after they leave, writes the ones waiting for the allowance (most asked first), and writes each first look in full once its deeper research is in (decisions/0147).', '5min', 30],
        'journeys_tick' => ['Suggested journeys', 'Builds each live guide\'s suggested journeys from its own places by rules (no AI), three guides a run, again weekly or when the guide changes; asks openrouteservice once for a town\'s travel times and keeps them (decisions/0146).', 'hourly', 180],
        'supabase_keepalive' => ['Supabase keep-alive', 'One tiny read a day so the free Supabase project doesn\'t pause (decisions/0164 §5.2). Under Supabase\'s own switch, not the master: it gathers nothing and costs nothing.', 'daily', 1560],
        'page_read_tick' => ['Pages waiting to be read', 'Pages whose reading had to stop are read into facts by the AI that AI use routes "Read a public page into facts" to (decisions/0164 §2.3; gap review P1-1). Shipped off: it runs only while automation, W3 and that feature are on. Not set up in cPanel by itself.', 'hourly', 300],
        'vectors_tick' => ['Vector index', 'Public listings\' text turned into vectors on Supabase for semantic search (decisions/0164 §5.2). Shipped off: it runs only while automation, W3, Supabase and AI use\'s "vector index" are on. Not set up in cPanel by itself.', 'hourly', 300],
        'identity_recheck_tick' => ['Identity recheck', 'Live listings checked against each other (decisions/0164 §6.1): exact twins merged by themselves with Undo, variations vetted, the rest to "The engine needs a person". Shipped off: it runs only while automation, W2 and its task are on. Not set up in cPanel by itself: add it here, in a quiet hour.', 'daily', 1560],
        'data_cleanup_tick' => ['Data cleanup', 'A pass for every rule of Insights → Data cleanup, once a day, so what the crawler made stays in line with today\'s rules. Shipped off: it does nothing until its daily pass is switched on there, and only while automation is on. Not set up in cPanel by itself: add it here when it\'s wanted.', 'daily', 1560],
        'landmark_photos_tick' => ['Landmark and place photos', 'Looks on Wikimedia Commons for a free photo of each shown landmark that has none (taken nearby and named for it), 20 an hour, then of the parks, monuments, visitor centres and historic sites on our cards (15 an hour, never a place kept private), and keeps the best with its credit and licence. One with none found is looked at again after 30 days.', 'hourly', 180],
        'like_watch_tick' => ['Like checks', 'Looks at the last hour\'s likes: takes machine likes (perfectly timed, or more likes than views) off the count, reports groups of new accounts and guests to Safety in Pulse, and deletes like records older than 30 days.', 'hourly', 180],
        'earned_storage_tick' => ['Earned storage', 'Counts each member\'s qualifying followers and friends who joined, and adds the storage they\'ve earned: 1 GB per 500 followers and per friend, up to 25 GB, 5 GB a month at most (decisions/0154 §7).', 'daily', 1500],
        'moments_tick' => ['Moments', 'Ends posts whose 3 or 7 days are up (deletes them and their photos and videos, unless kept or waiting on a report), tells people a day before, lets go of uploads never posted, and releases reported posts\' held copies after their 90 days (decisions/0153).', 'hourly', 180],
        'guest_tick' => ['Guests\' 30 days', 'Deletes the hashed connections behind guests\' likes after 30 days (decisions/0009); the likes stay in the counts.', 'daily', 1560],
        'vault_digest_tick' => ['Vault email summaries', 'Sends the vault\'s hourly and daily email summaries to whoever chose them (the vault\'s Emails), only when something happened. Alerts about devices, recovery words and locks always go at once.', 'hourly', 180],
        'releases_tick' => ['Releases', 'Publishes a release when its scheduled time comes, and moves live releases on a step (members, then everyone) once their hours have passed clean, inside the release window (System Configuration → Releases). A failed check pauses it and emails whoever approved it.', '10min', 60],
        'staging_carry_tick' => ['Staging refresh, carried on', 'Works through a staging refresh on the server once one is started (System Configuration → Staging, or the weekly Staging refresh), so the page needn\'t stay open. Does nothing when no refresh is running.', 'minute', 15],
        'audit_clock_tick' => ['Site audit clock', 'Asks GitHub to run the site audit\'s checks when their hour comes (the code checks at 7:00 and 19:00 UTC, the browser checks and paths at 9:00, the search probe at 10:00), because GitHub\'s own schedule skips them when it\'s busy (decisions/0145). Site health in Pulse says when one hasn\'t run in 26 hours. Does nothing on staging.', 'hourly', 180],
        'staging_tick' => ['Staging refresh (stopped)', 'The weekly copy of live over staging stopped with decisions/0165: staging catches up from live instead, keeping its own work. Start staging over is a button on System Configuration → Staging. It does nothing now and can be removed.', 'weekly', 10140],
        'staging_push_tick' => ['Staging: live\'s changes pushed', 'Sends each change on the live site to staging within a minute (live\'s outbox), for the rows staging didn\'t touch; a row both changed goes on the conflicts list (decisions/0165 §3). Does nothing until Keep staging caught up is switched on (System Configuration → Staging).', 'minute', 15],
        'staging_night_tick' => ['Staging: the nightly full pass', 'Every night (2:13 am Arizona): every row\'s fingerprint against staging\'s base, which finds a missed push, a delete the database cascaded and a change made outside the code; copies again the reference tables live changed (decisions/0165 §3). Does nothing until Keep staging caught up is on.', 'nightly', 1560],
        'frontier_tick' => ['The index\'s upkeep (W0)', 'Keeps W0 Indexer\'s frontier in order (decisions/0164 §1.4): sites not decided yet classified by the machine (a tribal nation\'s set aside for a person), places\' own websites ranked first, junk seen often made a skip rule, URLs never read in six months kept as their hash, its size recorded for the pre-flight. Database only: no outside call, so it runs whatever the switches say.', 'hourly', 180],
        'preflight_tick' => ['Pre-flight', 'Runs every pre-flight test (System Configuration → Pre-flight) each night at 2 am Arizona time, and within 15 minutes of a .env change: real, free, read-only tests of every service, setting, workflow and task, red lines to Pulse ("Pre-flight"). It never starts, pauses or changes anything.', '15min', 60],
        'system_start_tick' => ['Start the system, carried on', 'Works through Start the system (System Configuration → Automation) once it\'s pressed: the keys, watching, the switch-on check, Railway\'s schedule, the master switch, then each workflow and new task one at a time behind its gate (decisions/0163 §10). Does nothing until it\'s pressed, and never on staging.', '10min', 120],
        'staging_publish_tick' => ['Staging: Publish, carried on', 'Works through a Publish of staging on the server once it\'s started (the site manager), a step at a time, so the page can close; starts a scheduled one at its time after a fresh preview (decisions/0165 §4). Does nothing when none is running.', 'minute', 15],
    ];

    /** Jobs that set themselves up in cPanel (sync()), on their usual schedule, with no one opening cPanel (Jason, 2026-10-28):
     *  every new job goes here. One removed on the Scheduled tasks page stays removed. */
    public const AUTO = ['control_tick', 'founder_mail_tick', 'team_tick', 'team_audit_tick', 'moments_tick', 'earned_storage_tick', 'guide_ondemand_tick', 'journeys_tick', 'landmark_photos_tick', 'like_watch_tick', 'guest_tick', 'region_tick', 'work_orders_tick', 'pay_wait_tick', 'got_it_tick', 'qb_orders_tick', 'guide_finds_tick', 'guide_live_tick', 'listings_tick', 'services_tick', 'heritage_tick', 'takedowns_tick', 'daily_report', 'market_tick', 'notice_digest', 'notify_release', 'listing_messages_tick', 'mail_sync', 'mail_keep', 'pulse_followup', 'booking_remind', 'ses_setup_tick', 'domains_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick', 'vault_digest_tick', 'audit_clock_tick', 'system_start_tick', 'frontier_tick', 'preflight_tick', 'files_tick', 'advocates_tick'];

    /** Schedules to choose from: key => [label, minute, hour, day, month, weekday]. */
    public const PRESETS = [
        'minute' => ['Every minute', '*', '*', '*', '*', '*'],
        '5min' => ['Every 5 minutes', '*/5', '*', '*', '*', '*'],
        '10min' => ['Every 10 minutes', '*/10', '*', '*', '*', '*'],
        '15min' => ['Every 15 minutes', '*/15', '*', '*', '*', '*'],
        'hourly' => ['Every hour (at :17)', '17', '*', '*', '*', '*'],
        'hourly2' => ['Every hour (at :02)', '2', '*', '*', '*', '*'],
        '6hours' => ['Every 6 hours', '17', '*/6', '*', '*', '*'],
        'daily' => ['Every day at 6:13 am', '13', '6', '*', '*', '*'],
        'nightly' => ['Every night at 2:13 am', '13', '2', '*', '*', '*'],
        'weekly' => ['Every Sunday at 3:13 am', '13', '3', '*', '*', '0'],
    ];

    public static function ensure(PDO $pdo): void
    {
        AdminTables::make($pdo, ['job_runs' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, job VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL,
            finished_at DATETIME DEFAULT NULL, ok TINYINT(1) DEFAULT NULL, note VARCHAR(255) NOT NULL DEFAULT \'\', KEY idx_job_runs (job, id))',
            'job_setup' => '(job VARCHAR(40) NOT NULL PRIMARY KEY, state VARCHAR(10) NOT NULL, at DATETIME NOT NULL)']);
    }

    // ---- a script's own run --------------------------------------------------------------------------------------------

    /** Called at the top of a scheduled script: records the run, and at the end whether it finished and what it said. */
    public static function run(string $job): void
    {
        try {
            $pdo = Database::connection();
            self::ensure($pdo);
            $pdo->prepare('INSERT INTO ' . Database::t('job_runs') . ' (job, started_at) VALUES (?, NOW())')->execute([$job]);
            $id = (int) $pdo->lastInsertId();
            if (random_int(1, 50) === 1) $pdo->prepare('DELETE FROM ' . Database::t('job_runs') . ' WHERE job = ? AND started_at < NOW() - INTERVAL 14 DAY')->execute([$job]);
        } catch (Throwable $e) { error_log('scheduler: ' . $e->getMessage()); self::missed($job, $e->getMessage()); return; }   // the job still runs, unrecorded: its miss is kept for the health report
        try { self::sync($pdo); } catch (Throwable $e) { error_log('scheduler sync: ' . $e->getMessage()); }   // any running job sets up new ones (hourly at most)
        ob_start();
        register_shutdown_function(function () use ($pdo, $id) {
            $out = (string) ob_get_clean();
            echo $out;
            $e = error_get_last();
            $bad = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
            $note = $bad ? 'Stopped: ' . $e['message'] : (string) end($lines);
            try { $pdo->prepare('UPDATE ' . Database::t('job_runs') . ' SET finished_at = NOW(), ok = ?, note = ? WHERE id = ?')->execute([$bad ? 0 : 1, mb_substr($note, 0, 255), $id]); } catch (Throwable) {}
        });
        // a job that gathers or creates content skips while automation is paused, or all its workflows are (System
        // Configuration → Automation; Jason, 2026-11-14): recorded as a run that worked and said why, never a failure
        try {
            require_once __DIR__ . '/Automation.php';
            if (($w = Automation::job($pdo, $job)) !== null) { echo 'Paused: ' . $w . "\n"; exit(0); }
        } catch (Throwable $e) { error_log('scheduler automation: ' . $e->getMessage()); }
    }

    /** Where a start that couldn't be recorded is kept (the server's own folder, never synced): the database refused it. */
    public static function missFile(): string { return __DIR__ . '/../storage/scheduler-missed.json'; }

    /**
     * A start the database refused (2026-11-23: some jobs didn't start on a minute when others did; "Too many connections"
     * is the usual cause on shared hosting): kept, the last 200, for /api/health.php's jobs section, so the daily report
     * shows it without anyone reading the server's error log. Never throws.
     */
    public static function missed(string $job, string $why): void
    {
        try {
            $f = self::missFile(); if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
            $h = @fopen($f, 'c+'); if (!$h) return;
            if (flock($h, LOCK_EX)) {
                $all = json_decode((string) stream_get_contents($h), true); if (!is_array($all)) $all = [];
                $all[] = ['job' => $job, 'at' => gmdate('Y-m-d\TH:i:s\Z'), 'why' => mb_substr(preg_replace('/\s+/', ' ', $why), 0, 160)];
                $all = array_slice($all, -200);
                ftruncate($h, 0); rewind($h); fwrite($h, json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); fflush($h); flock($h, LOCK_UN);
            }
            fclose($h);
        } catch (Throwable $x) {}
    }

    /** The starts the database refused: [[job, at (UTC), why]], oldest first. */
    public static function misses(): array
    {
        $all = @json_decode((string) @file_get_contents(self::missFile()), true);
        return is_array($all) ? $all : [];
    }

    /** The last run of each job: job => row. */
    public static function lastRuns(PDO $pdo): array
    {
        self::ensure($pdo);
        $out = [];
        foreach (array_keys(self::JOBS) as $j) {
            $st = $pdo->prepare('SELECT * FROM ' . Database::t('job_runs') . ' WHERE job = ? ORDER BY id DESC LIMIT 1'); $st->execute([$j]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $f = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('job_runs') . ' WHERE job = ? AND ok = 0 AND started_at > NOW() - INTERVAL 1 DAY'); $f->execute([$j]);
                $r['fails_today'] = (int) $f->fetchColumn();
                $out[$j] = $r;
            }
        }
        return $out;
    }

    // ---- the cron jobs in cPanel ---------------------------------------------------------------------------------------

    /** Every cron line in cPanel: [line, linekey, minute, hour, day, month, weekday, command]. */
    public static function cronLines(): array
    {
        $out = [];
        foreach (CPanel::api2('Cron', 'listcron') as $r) {
            if (!is_array($r) || !isset($r['command'])) continue;   // the list ends with a count
            $out[] = ['line' => (int) ($r['line'] ?? 0), 'linekey' => (string) ($r['linekey'] ?? ''), 'minute' => (string) $r['minute'], 'hour' => (string) $r['hour'], 'day' => (string) $r['day'],
                'month' => (string) $r['month'], 'weekday' => (string) $r['weekday'], 'command' => (string) $r['command']];
        }
        return $out;
    }

    /** Which cron line runs a job (null when none). */
    public static function lineFor(array $lines, string $job, bool $staging = false): ?array
    {
        foreach ($lines as $l) if (str_contains($l['command'], 'app/scripts/' . $job . '.php') && self::isStagingLine($l) === $staging) return $l;
        return null;
    }

    /** Is this one of staging's own copies (its scripts are in staging's folder)? Live's page and sync() never touch those. */
    public static function isStagingLine(array $l): bool { return str_contains((string) $l['command'], '/staging.trvgo.us/'); }

    // ---- staging's own copies of the jobs (decisions/0165 §1) -----------------------------------------------------------

    /** Jobs that only talk to the outside world, or run staging itself: never copied to staging. */
    /** Jobs that log a run only when they have work (a staging refresh going, live's changes to push, a release waiting):
     *  long quiet isn't a fault, so the site audit doesn't count their silence (Site health D03, 2026-11-18). */
    public const QUIET = ['founder_mail_tick' /* emails the Founder: logs only when it sends */, 'team_tick' /* the Team's runner: staging only, so on live it never logs (Piece 2) */, 'team_audit_tick' /* and its daily audit, the same */, 'staging_carry_tick', 'staging_push_tick', 'releases_tick', 'system_start_tick' /* logs only while Start the system is going (its script: "does nothing (and logs nothing) until the button is pressed"); 2026-11-20, Automation read "last ran 2 days ago … and is late" */];
    /** Jobs kept in the list but no longer run (their description says why): never late. */
    public const RETIRED = ['staging_tick'];
    public const NOT_ON_STAGING = ['founder_mail_tick', 'ses_setup_tick', 'domains_tick', 'audit_clock_tick', 'staging_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick', 'advocates_tick' /* asks GitHub from live, as audit_clock_tick does */];

    /**
     * Staging's copy of each job, in cron through live's cPanel key (staging has none), pointing at staging's folder: they
     * run on staging's data under the gate. Only while "Staging runs its own jobs" is on (shipped off); off takes them out.
     * $scripts: staging's app/scripts folder. Returns [added, removed].
     */
    public static function syncStaging(bool $on, string $scripts): array
    {
        if (!CPanel::ready()) return [[], []];
        $lines = self::cronLines(); $added = []; $gone = [];
        foreach ($lines as $l) if (self::isStagingLine($l) && (!$on || !preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $m) || in_array($m[1], self::NOT_ON_STAGING, true))) {
            CPanel::api2('Cron', 'remove_line', ['line' => $l['line'], 'linekey' => $l['linekey']]);
            if (preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $m)) $gone[] = $m[1];
            $lines = self::cronLines();
        }
        if (!$on) return [[], $gone];
        $php = trim((string) getenv('CRON_PHP')) ?: '/usr/local/bin/php';
        foreach ($lines as $l) if (preg_match('#^(\S*php\S*)\s+\S*app/scripts/#', $l['command'], $m)) { $php = $m[1]; break; }
        foreach (self::AUTO as $job) {
            if (in_array($job, self::NOT_ON_STAGING, true) || !isset(self::JOBS[$job]) || !self::lineFor($lines, $job)) continue;   // only what live runs
            if (self::lineFor($lines, $job, true)) continue;
            $live = self::lineFor($lines, $job);
            CPanel::api2('Cron', 'add_line', ['command' => $php . ' ' . rtrim($scripts, '/') . '/' . $job . '.php >/dev/null 2>&1', 'minute' => ($at = self::stagingTime($live))['minute'], 'hour' => $at['hour'], 'day' => $at['day'], 'month' => $at['month'], 'weekday' => $at['weekday']]);
            $added[] = $job;
        }
        return [$added, $gone];
    }

    /** Minutes after live's start that staging's copy of a job starts (decisions/0165 §1: staging never costs live its limits;
     *  2026-11-26, the CEO's ruling: live's 6:13 daily jobs missed their start with staging's copies in the same minute against
     *  the database's 25 connections). */
    public const STAGING_OFFSET = 7;

    /** When staging's copy of a job runs: live's time, STAGING_OFFSET minutes later where live's minute is one number (a minute
     *  past :59 carries into the next hour, and past 23:59 into the next day when no day is set; where a day, month or weekday is
     *  set, or the hour is a list, a range or every N hours, it starts STAGING_OFFSET minutes earlier instead, so it never lands on another day or
     *  hour). A minute that isn't one number (every minute, every N minutes, a list) is kept as live's. */
    public static function stagingTime(array $live): array
    {
        $t = ['minute' => (string) $live['minute'], 'hour' => (string) $live['hour'], 'day' => (string) $live['day'], 'month' => (string) $live['month'], 'weekday' => (string) $live['weekday']];
        if (!preg_match('/^\d{1,2}$/', $t['minute'])) return $t;
        $m = (int) $t['minute'] + self::STAGING_OFFSET;
        if ($m < 60) { $t['minute'] = (string) $m; return $t; }
        $anyDay = $t['day'] === '*' && $t['month'] === '*' && $t['weekday'] === '*';
        if ($t['hour'] === '*') { $t['minute'] = (string) ($m - 60); return $t; }   // every hour: past :59 is the next hour's start
        if (preg_match('/^\d{1,2}$/', $t['hour']) && ((int) $t['hour'] < 23 || $anyDay)) { $t['minute'] = (string) ($m - 60); $t['hour'] = (string) (((int) $t['hour'] + 1) % 24); return $t; }
        $t['minute'] = (string) ((int) $t['minute'] - self::STAGING_OFFSET);   // a list, a range or a set day: earlier, never another day
        return $t;
    }

    /** Staging's copies moved to their time (stagingTime) where they still start with live's: the copies made before the offset,
     *  and a live line changed since. Run by sync(), hourly at most; staging's lines only, live's never touched. */
    public static function offsetStaging(): array
    {
        if (!CPanel::ready()) return [];
        $moved = [];
        foreach (self::cronLines() as $l) {
            if (!self::isStagingLine($l) || !preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $mm)) continue;
            $lines = self::cronLines(); $live = self::lineFor($lines, $mm[1]); $cur = self::lineFor($lines, $mm[1], true);
            if (!$live || !$cur) continue;
            $at = self::stagingTime($live);
            if ($cur['minute'] === $at['minute'] && $cur['hour'] === $at['hour'] && $cur['day'] === $at['day'] && $cur['month'] === $at['month'] && $cur['weekday'] === $at['weekday']) continue;
            CPanel::api2('Cron', 'edit_line', ['line' => $cur['line'], 'linekey' => $cur['linekey'], 'command' => $cur['command']] + $at);
            $moved[] = $mm[1];
        }
        return $moved;
    }

    /** The command for a job: PHP (the one our other jobs use, else CRON_PHP, else cPanel's usual), the script, output to nowhere (no email each run). */
    public static function command(string $job, array $lines = []): string
    {
        $php = trim((string) getenv('CRON_PHP')) ?: '/usr/local/bin/php';
        foreach ($lines as $l) if (preg_match('#^(\S*php\S*)\s+\S*app/scripts/#', $l['command'], $m)) { $php = $m[1]; break; }
        $script = (realpath(__DIR__ . '/../scripts') ?: __DIR__ . '/../scripts') . '/' . $job . '.php';
        return $php . ' ' . $script . ' >/dev/null 2>&1';
    }

    /**
     * Sets up the AUTO jobs cPanel doesn't have yet, on their usual schedule, through the cPanel API: at most once an hour
     * unless $now. A job removed on the page (or one we set up and someone took out in cPanel) isn't put back. Returns
     * the jobs it added.
     */
    public static function sync(PDO $pdo, bool $now = false): array
    {
        // once an hour at most, for all of it (the cron gap of 2026-11-23: every job's start, about 40 jobs, ai_worker every
        // minute, ran the five upkeep steps below before this guard, up to 300 listings re-scored each time; a pile-up holds
        // the server's processes and database connections, and the next jobs don't start). The upkeep keeps its batch sizes.
        self::ensure($pdo);
        $t = Database::t('job_setup');
        if (!$now) {
            $st = $pdo->query("SELECT at FROM $t WHERE job = '_sync'"); $last = $st ? $st->fetchColumn() : false;
            if ($last && strtotime((string) $last) > time() - 3600) return [];
        }
        $pdo->prepare("REPLACE INTO $t (job, state, at) VALUES ('_sync', 'ran', NOW())")->execute();
        try { require_once __DIR__ . '/../admin/DataMove.php'; DataMove::home($pdo); } catch (Throwable $x) { error_log('identity home: ' . $x->getMessage()); }   // the identity record lives in main (decisions/0133)
        try { require_once __DIR__ . '/Identity.php'; Identity::backfillIntake($pdo, 50); Identity::backfillListings($pdo, 50); require_once __DIR__ . '/crawler/IntakeStager.php'; IntakeStager::tidySources($pdo); require_once __DIR__ . '/ListingConfidence.php'; ListingConfidence::backfill($pdo, 300); ListingConfidence::stillOpenTick($pdo, 300); } catch (Throwable $x) { error_log('intake identities: ' . $x->getMessage()); }   // sightings from before 2026-11-01 get theirs
        try { require_once __DIR__ . '/DbUpdates.php'; DbUpdates::autoRun($pdo); } catch (Throwable $x) { error_log('db updates: ' . $x->getMessage()); }   // new migration files run themselves, at most once an hour
        try { require_once __DIR__ . '/SchemaUpkeep.php'; if (SchemaUpkeep::scanDue()) SchemaUpkeep::scanUnused($pdo); } catch (Throwable $x) { error_log('unused schema: ' . $x->getMessage()); }   // the monthly check for tables and columns no code uses (decisions/0162)
        if (!CPanel::ready()) return [];
        try { if ($mv = self::offsetStaging()) error_log('staging jobs moved to live + ' . self::STAGING_OFFSET . ' min: ' . implode(', ', $mv)); } catch (Throwable $x) { error_log('staging job offset: ' . $x->getMessage()); }
        $known = $pdo->query("SELECT job, state FROM $t")->fetchAll(PDO::FETCH_KEY_PAIR);
        $lines = self::cronLines(); $added = [];
        foreach (self::AUTO as $job) {
            if (!isset(self::JOBS[$job])) continue;
            if (self::lineFor($lines, $job)) { if (!isset($known[$job])) self::mark($pdo, $job, 'added'); continue; }
            if (isset($known[$job])) continue;   // removed, or set up before and taken out by hand: left that way
            $p = self::PRESETS[self::JOBS[$job][2]];
            CPanel::api2('Cron', 'add_line', ['command' => self::command($job, $lines), 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
            self::mark($pdo, $job, 'added'); $added[] = $job;
        }
        return $added;
    }

    private static function mark(PDO $pdo, string $job, string $state): void
    {
        try { self::ensure($pdo); $pdo->prepare('REPLACE INTO ' . Database::t('job_setup') . ' (job, state, at) VALUES (?, ?, NOW())')->execute([$job, $state]); } catch (Throwable $e) { error_log('scheduler mark: ' . $e->getMessage()); }
    }

    public static function add(string $job, string $preset): void
    {
        if (!isset(self::JOBS[$job])) throw new InvalidArgumentException('Not one of our jobs.');
        $p = self::PRESETS[$preset] ?? null; if (!$p) throw new InvalidArgumentException('Choose when it runs.');
        $lines = self::cronLines();
        if (self::lineFor($lines, $job)) throw new InvalidArgumentException('It\'s already set up in cPanel.');
        CPanel::api2('Cron', 'add_line', ['command' => self::command($job, $lines), 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
        self::mark(Database::connection(), $job, 'added');
    }

    public static function change(string $job, string $preset): void
    {
        $p = self::PRESETS[$preset] ?? null; if (!$p) throw new InvalidArgumentException('Choose when it runs.');
        $l = self::lineFor(self::cronLines(), $job); if (!$l) throw new InvalidArgumentException('It isn\'t set up in cPanel yet.');
        CPanel::api2('Cron', 'edit_line', ['line' => $l['line'], 'linekey' => $l['linekey'], 'command' => $l['command'], 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
    }

    public static function remove(string $job): void
    {
        $l = self::lineFor(self::cronLines(), $job); if (!$l) throw new InvalidArgumentException('It isn\'t set up in cPanel.');
        CPanel::api2('Cron', 'remove_line', ['line' => $l['line'], 'linekey' => $l['linekey']]);
        self::mark(Database::connection(), $job, 'removed');   // sync() leaves it out from now on
    }

    /** The preset a line matches ('' when it's set to something else). */
    public static function presetOf(array $l): string
    {
        foreach (self::PRESETS as $k => $p) if ([$l['minute'], $l['hour'], $l['day'], $l['month'], $l['weekday']] === array_slice($p, 1)) return $k;
        return '';
    }

    /** A cron schedule in words, as well as can be said. */
    public static function words(array $l): string
    {
        $k = self::presetOf($l);
        return $k !== '' ? self::PRESETS[$k][0] : trim($l['minute'] . ' ' . $l['hour'] . ' ' . $l['day'] . ' ' . $l['month'] . ' ' . $l['weekday']) . ' (cron)';
    }
}
=== END FILE ===

=== FILE: tests/ba-narrowing.php ===
<?php
// tests/ba-narrowing.php (tools/piece3-tests/ style): BeforeAfter::narrow() keeps a block only on 0179's four rules (or a rule
// it doesn't know), turns report-only rules into card lines and plan drift into a ticket line, and never passes a block whose
// list is short of its total. Pure: no database, no GitHub. TV_APP: the app folder (default ../../app from tools/<area>-tests/).
declare(strict_types=1);

$app = getenv('TV_APP') ?: dirname(__DIR__, 2) . '/app';
require_once $app . '/lib/BeforeAfter.php';

$fails = 0;
function ok(string $name, bool $cond): void { global $fails; echo ($cond ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$cond) $fails++; }
function base(string $verdict, int $blocks, string $first = 'first'): array
{ return ['phase' => 'after-done', 'verdict' => $verdict, 'first' => $first, 'run' => '', 'blocks' => $blocks, 'views' => 4, 'seconds' => 60.0, 'far' => []]; }
$b = fn (string $rule, string $what = 'x') => ['who' => 'new-member', 'page' => '/x.php', 'size' => 'phone', 'rule' => $rule, 'what' => $what];

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('alt')]]);
ok('only an alt-text block: passes, reported', $v['verdict'] === 'pass' && $v['blocks'] === 0 && count($v['reports']) === 2 && $v['narrowed'] === 1);

$v = BeforeAfter::narrow(base('block', 2), ['blocks' => [$b('alt'), $b('layout', 'overlap')]]);
ok('alt plus a layout block: still blocks, one block', $v['verdict'] === 'block' && $v['blocks'] === 1 && str_contains($v['first'], 'overlap'));

$v = BeforeAfter::narrow(base('block', 3), ['blocks' => [$b('alt')]]);
ok('a list short of its total keeps the block as it was', $v['verdict'] === 'block' && $v['blocks'] === 3 && $v['first'] === 'first');

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('something-new')]]);
ok('a rule not known here still blocks', $v['verdict'] === 'block' && $v['narrowed'] === 0);

foreach (['access', 'layout', 'data', 'drift'] as $r) {
    $v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b($r)]]);
    ok('0179 rule "' . $r . '" blocks', $v['verdict'] === 'block');
}

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('plan', 'app/lib/Extra.php')]]);
ok('plan drift alone: passes, one ticket line', $v['verdict'] === 'pass' && count($v['tickets']) === 1);

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('area')]]);
ok('a high-risk territory named: never a block by itself', $v['verdict'] === 'pass');

$v = BeforeAfter::narrow(base('pass', 0), ['blocks' => [$b('layout')]]);
ok('a pass is left as it is', $v['verdict'] === 'pass' && $v['reports'] === []);

$v = BeforeAfter::narrow(base('incomplete', 0), ['blocks' => [$b('alt')]]);
ok('an incomplete look is left as it is', $v['verdict'] === 'incomplete');

$v = BeforeAfter::narrow(base('block', 1), []);
ok('no list in the file: the block stands', $v['verdict'] === 'block' && $v['reports'] === [] && $v['tickets'] === []);

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('alt', 'shows check+member@traversence.com')]]);
ok('a test account\'s address is never copied to the card', !str_contains(implode(' ', $v['reports']), '@'));

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [['who' => '<b>', 'page' => 'javascript:x', 'rule' => 'alt', 'what' => ['no']]]]);
ok('bad fields aren\'t shown', !str_contains(implode(' ', $v['reports']), '<b>') && !str_contains(implode(' ', $v['reports']), 'javascript'));

ok('the four rules are 0179\'s', array_keys(BeforeAfter::RULES) === ['access', 'layout', 'data', 'drift']);
ok('no report-only rule is one of the four', array_intersect(array_keys(BeforeAfter::REPORT_ONLY + BeforeAfter::TICKET_ONLY), array_keys(BeforeAfter::RULES)) === []);

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: tests/advocates-audit.php ===
<?php
// tests/advocates-audit.php (tools/piece3-tests/ style): the advocates' own audit's parts that need no database: its result
// file read field by field, its once-a-day ask, its keys, and its Scheduler::JOBS entry. TV_APP: the app folder.
declare(strict_types=1);

$app = getenv('TV_APP') ?: dirname(__DIR__, 2) . '/app';
require_once $app . '/lib/Database.php';
require_once $app . '/lib/Scheduler.php';
require_once $app . '/lib/AdvocatesAudit.php';

$fails = 0;
function ok(string $name, bool $cond): void { global $fails; echo ($cond ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$cond) $fails++; }

$run = 'https://github.com/degraffics/traversence/actions/runs/123';
$file = json_encode(['run' => $run, 'at' => '2026-11-28T04:30:00Z', 'seconds' => 300, 'views' => 40, 'findings' => [
    ['who' => 'guest-visitor', 'page' => '/faq.php', 'size' => 'phone', 'rule' => 'layout', 'what' => 'Sideways scroll at 390', 'lane' => 'editorial'],
    ['who' => 'member', 'page' => '/user/x.php', 'size' => 'desk', 'rule' => 'friction', 'what' => 'check+member@traversence.com saw it'],
    ['who' => 'BAD', 'page' => '/x', 'rule' => 'layout'],
    ['who' => 'member', 'page' => 'http://evil', 'rule' => 'layout'],
    ['who' => 'member', 'page' => '/x', 'rule' => 'publish'],
]]);
$p = AdvocatesAudit::parse($file);
ok('a good file reads', is_array($p) && $p['run'] === $run && $p['seconds'] === 300.0);
ok('only valid findings kept', is_array($p) && count($p['findings']) === 2);
ok('an address is never kept', is_array($p) && !str_contains($p['findings'][1]['what'], '@'));
ok('a rule outside its list is dropped', is_array($p) && !in_array('publish', array_column($p['findings'], 'rule'), true));
ok('a run not from our repository is refused', AdvocatesAudit::parse(json_encode(['run' => 'https://github.com/other/repo/actions/runs/1'])) === null);
ok('nothing there: null', AdvocatesAudit::parse(null) === null && AdvocatesAudit::parse('not json') === null);

ok('not due before its hour', !AdvocatesAudit::due(AdvocatesAudit::HOUR_UTC - 1, '2026-11-27', '2026-11-28'));
ok('due at its hour', AdvocatesAudit::due(AdvocatesAudit::HOUR_UTC, '2026-11-27', '2026-11-28'));
ok('once a day', !AdvocatesAudit::due(23, '2026-11-28', '2026-11-28'));

$f = $p['findings'][0];
ok('a key is stable', AdvocatesAudit::key($f) === AdvocatesAudit::key($f));
ok('a key fits its column', strlen(AdvocatesAudit::key($f)) === 44);
ok('another size is another finding', AdvocatesAudit::key($f) !== AdvocatesAudit::key(['size' => 'desk'] + $f));

ok('JOBS has advocates_tick', isset(Scheduler::JOBS['advocates_tick']));
ok('its preset exists', isset(Scheduler::PRESETS[Scheduler::JOBS['advocates_tick'][2]]));
ok('its late is at least its schedule', (int) Scheduler::JOBS['advocates_tick'][3] >= 60);
ok('it sets itself up (AUTO)', in_array('advocates_tick', Scheduler::AUTO, true));
ok('never copied to staging', in_array('advocates_tick', Scheduler::NOT_ON_STAGING, true));
ok('its script is in the release', is_file($app . '/scripts/advocates_tick.php'));

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAILED\n";
exit($fails === 0 ? 0 : 1);
=== END FILE ===

=== FILE: faq/faq-entries.md ===
### What does the before-and-after check stop?

Before a change goes live, the test-account advocates look at every page it touches on staging, before the change and after it. The change is stopped only when a page is made worse in one of four ways (decisions/0179):

- someone can see what they shouldn't, loses access, or gets an error (tribal nations' protected places included);
- the layout breaks, on a computer or a phone;
- data goes missing, or a core step stops working;
- a page changes that the change didn't say it would.

Anything else the advocates notice, such as an image without a description, is written on the release's card for the team to fix, and doesn't stop it. A file the change's plan didn't name opens a ticket in the Work queue; it doesn't stop the release or roll it back (decisions/0180 §8).

### Do the advocates check the site between releases?

Coming. Once it's switched on, the advocates look at staging once a day as their test accounts, on their own schedule and independent of anyone building. A problem they see on two days in a row opens one ticket in the Work queue for the team that owns that screen. They never build, move a ticket, or stop a release, and their look waits while a release is being checked.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "before-after-narrowing",
  "gate": "Tier 2, backend only (app/lib, app/scripts, an add-only migration; no public page). Prior Ruling Check: implements decisions/0179's note 'How the Team's capacity grows' §4 (the four block rules) and 0180 §5 (advocates) and §8 (plan drift opens a ticket, never a rollback). It changes what the publish check blocks, so a Tier 4 review is recommended before live (verify). Touches no 0180 §4 territory by its files.",
  "what": "(a) The before-and-after check's site side keeps a block only on 0179's four rules: a listed block whose rule is report-only (alt text, unnamed control, dialog, a territory named) goes on the release's card; plan drift (files the build brief didn't name) opens one Work ticket; any other or unknown rule still blocks, and a block list short of its total keeps the block. (b) The advocates' own audit: Scheduler::JOBS 'advocates_tick' (hourly, asks GitHub once a day from 4:00 UTC, switch advocates_audit, live only), its runner app/scripts/advocates_tick.php and AdvocatesAudit.php: a finding seen on two looks in a row opens one unowned Work ticket (an experience ticket) for its lane; it never blocks, withdraws or publishes a release, waits while one is checked, and counts its minutes in DB4's budget.",
  "files": [
    "files/app/lib/BeforeAfter.php",
    "files/app/lib/AdvocatesAudit.php",
    "files/app/scripts/advocates_tick.php",
    "files/app/migrations/2026-11-28_advocates_audit.sql",
    "files/app/lib/Scheduler.php",
    "tests/ba-narrowing.php",
    "tests/advocates-audit.php",
    "faq/faq-entries.md"
  ],
  "decisions": ["0179", "0180", "0165", "0066", "0145"],
  "tests": ["tests/ba-narrowing.php", "tests/advocates-audit.php"],
  "risks": [
    "DRAFT: nothing here was run, linted, tested or zip-checked; the lane session must run php -l on every file, both tests, and the zip check.",
    "The narrowing only acts on a per-block list in the verdict file ('blocks': [{who, page, size, rule, what}]); beforeafter.mjs isn't shown, so if it writes no such list or other rule names, nothing is narrowed and the check behaves as before (fail-safe, but the narrowing does nothing until it matches).",
    "Report-only rules (alt, unnamed, dialog, area) stop blocking: an unnamed control could be read as 'broken controls' under 0179 §4; confirm the list.",
    "The advocates' audit spends GitHub Actions minutes from the same daily budget as release checks (DB4); it asks only when no release is waiting and the budget isn't spent, but a late-day release could find less budget left.",
    "AdvocatesAudit opens tickets in the live Work queue (control_gauge_tasks) from a file on main; it dedups by what, at most 5 a run, but a noisy run could still open several tickets in a day.",
    "AdvocatesAudit::open sets tier from TeamRunner::tier, which still marks a touched territory Tier 5 until R3; the ticket stays a Work ticket, not a Resolution.",
    "Scheduler.php is output whole from the attached copy; any change on live since that copy would be overwritten."
  ],
  "verify": [
    "0180's attached text has no note saying 'advocates audit on a schedule, independent of the builders': §5 gives their two jobs, and its note 'roles check one another' gives releases to A1–A12. The schedule (once a day, 4:00 UTC), the two-looks rule, the 5-ticket cap and staging (not live) as the site looked at are this draft's choices: confirm or point to the note.",
    "0179's note of 2026-11-28 says 'The before-and-after check is unchanged'; confirm this narrowing is within rule 2 / note §4 (implementing it) and doesn't need a ruling.",
    "File not shown: tools/page-audit/beforeafter.mjs and .github/workflows/beforeafter.yml: the verdict file's per-block field name ('blocks') and its rule keys must match BeforeAfter::REPORT_ONLY / TICKET_ONLY / RULES; the narrowing of the four rules in the browser-side compare is not drafted here.",
    "File not shown: the GitHub workflow for the 'advocates-audit' repository_dispatch event and its writer of site-audit/results/advocates/latest.json ({run, at, seconds, views, findings:[{who, page, size, rule, what, lane}]}); it must be built (presumably on beforeafter.mjs's capture) before the switch is turned on.",
    "File not shown: app/lib/Releases.php (checkedByAdvocates, noteFromCheck): confirm it accepts the verdict array with the extra keys 'reports', 'tickets', 'narrowed', and that a narrowed 'pass' publishes as any pass.",
    "File not shown: app/lib/Switches.php: how a new switch (advocates_audit, shipped off) is registered; Switches::on for an unknown key is assumed to return false.",
    "File not shown: app/lib/TeamRoster.php: TeamRoster::LANES is assumed to be a list of lane keys (as GaugeTasks::handOff uses it), and control_gauge_tasks.lane to exist (R2). Which lane owns a screen is not set by the attached decisions; the result file's 'lane' is trusted only if it's one of LANES.",
    "File not shown: app/lib/AuditClock.php, DocsRepo.php, StagingSite.php, Staging.php: askGitHub, now, copyHold, here used as BeforeAfter uses them.",
    "File not shown: an existing app/scripts/*_tick.php to copy the runner's top lines from (require order, Database::connection()).",
    "Test paths: TV_APP default assumes tests live in tools/<area>-tests/ with app/ at the repo root; adjust to the mirror's layout (sync-app/).",
    "Whether a change to what the before-and-after check blocks counts as a 'release fence' under 0180 §2 (Tier 5) is not said; recorded here as Tier 2 with a Tier 4 review."
  ],
  "regions": false
}
=== END FILE ===