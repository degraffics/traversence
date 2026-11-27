<?php
// app/lib/BeforeAfter.php — Piece 3's before-and-after check, the site's side (decisions/0179 rule 2 and its notes "How the
// Team's capacity grows" §4 and "Piece 3's decisions"; handoffs/piece3-check.md §3, P3-2). A release from git, while the
// switch before_after is on, waits before it goes on staging: GitHub's run (.github/workflows/beforeafter.yml) looks at
// staging as it is (DB1: never live), then the release is staged as before, then the run looks again and writes its
// verdict to site-audit/results/beforeafter/<N>.json on main. Releases::checkedByAdvocates() adds it to the release's
// checks: a pass goes on to publish (or holds for the batch), a block withdraws it and puts the failure in the Work queue
// (DB2), an incomplete look holds it for a person. The minutes a day the runs may use (DB4) are counted here; once spent,
// a release holds for the batch. Run by releases_tick every 10 minutes (advance()). Never on staging.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class BeforeAfter
{
    public const SWITCH = 'before_after';
    public const LATE_MINUTES = 60;        // no answer in an hour: asked again once
    public const GIVE_UP_MINUTES = 120;    // still nothing: a ticket in the Work queue for a person
    public const NOTE_MINUTES = 30;        // no verdict file yet: said once on the release's card (the run takes 15 to 25 minutes)
    public const ITEM = 'Before and after (advocates)';

    /** Tests replace these: fn(string $event, array $payload): string ('' = asked), and fn(string $path): ?string. */
    public static $askFn = null;
    public static $readFn = null;

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

    public static function spent(PDO $pdo): bool { [$used, $cap] = self::budget($pdo); return $cap !== null && $used >= $cap; }

    // ---- asking GitHub, reading the verdict ------------------------------------------------------------------------------

    private static function ask(int $id, string $phase, array $d): string
    {
        $payload = ['release' => (string) $id, 'phase' => $phase, 'zip' => (string) ($d['zip'] ?? ''), 'blob' => (string) ($d['blob'] ?? '')];
        if (self::$askFn) return (string) (self::$askFn)('before-after', $payload);
        require_once __DIR__ . '/AuditClock.php';
        return AuditClock::askGitHub('before-after', $payload);
    }

    /** The run's verdict file, checked field by field; null when there's none yet. */
    public static function verdict(int $id): ?array
    {
        $path = 'site-audit/results/beforeafter/' . $id . '.json';
        if (self::$readFn) $raw = (self::$readFn)($path);
        else { require_once __DIR__ . '/DocsRepo.php'; $raw = DocsRepo::now($path); }   // through GitHub's API, as main is now (2026-11-27: raw reads came back empty for an hour)
        $v = $raw === null ? null : json_decode((string) $raw, true);
        if (!is_array($v) || (string) ($v['release'] ?? '') !== (string) $id) return null;
        $s = fn ($k, $n = 400) => mb_substr(trim((string) ($v[$k] ?? '')), 0, $n);
        return ['phase' => in_array($v['phase'] ?? '', ['before-done', 'after-done'], true) ? $v['phase'] : '', 'verdict' => in_array($v['verdict'] ?? '', ['waiting', 'pass', 'block', 'incomplete'], true) ? $v['verdict'] : '',
                'first' => $s('first'), 'run' => preg_match('#^https://github\.com/degraffics/traversence/actions/runs/\d+$#', (string) ($v['run'] ?? '')) ? (string) $v['run'] : '',
                'blocks' => (int) ($v['blocks_total'] ?? 0), 'views' => (int) ($v['views'] ?? 0), 'seconds' => (float) ($v['seconds'] ?? 0),
                // its far timeouts, said on the release's card (the CEO's Tier 4 condition, 2026-11-27): at most three, as the check wrote them
                'far' => array_slice(array_values(array_map(fn ($n) => mb_substr(trim((string) $n), 0, 300), array_filter((array) ($v['notes'] ?? []), fn ($n) => is_string($n) && preg_match('/when tried (again )?after the look/', $n)))), 0, 3)];
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
            if (!$r['checks_ok']) { self::put($pdo, $id, null); $w = Releases::selfWithdraw($pdo, $r); return $line . ($w !== '' ? ' ' . $w : ''); }
            $d['phase'] = 'after'; $d['asked_at'] = time(); $d['tries'] = 1; $d['before_seconds'] = $v['seconds']; unset($d['noted_missing']);   // the after verdict counts the before looks again
            $why = self::ask($id, 'after', $d); if ($why !== '') { $d['asked_at'] = 0; $d['ask_error'] = $why; }
            self::put($pdo, $id, $d);
            Releases::noteFromCheck($pdo, $id, 'On staging and its checks passed; the advocates look again now (the before-and-after check).');
            return 'Release #' . $id . ': on staging; its after look asked for.';
        }
        if ($d['phase'] === 'after' && $v && $v['phase'] === 'after-done' && $v['verdict'] !== 'waiting') {
            self::spend($pdo, max(0.0, $v['seconds'] - (float) ($d['before_seconds'] ?? 0)));
            self::put($pdo, $id, null);
            foreach ($v['far'] as $n) Releases::noteFromCheck($pdo, $id, 'The before-and-after check noted: ' . $n);   // kept on its card and in the daily summary's record
            return Releases::checkedByAdvocates($pdo, $id, $v, (bool) ($d['hold'] ?? false) || self::spent($pdo));
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
