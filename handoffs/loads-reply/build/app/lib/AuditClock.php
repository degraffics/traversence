<?php
// app/lib/AuditClock.php — the site keeps time for the site audit's GitHub runs (decisions/0145, Jason, 2026-11-13).
// GitHub skips or delays scheduled runs when it's busy (the 09:43 browser checks ran at 17:44, then not at all), so the
// audit's data, Site health and the paths went stale. The site's own cron (audit_clock_tick, hourly at :17) asks GitHub
// to run each workflow when its hour comes, through repository_dispatch with GITHUB_DOCS_TOKEN (Contents: write on
// degraffics/traversence is all it needs). The workflows still keep their own schedule as a fallback; a scheduled run
// that finds a run already made in its window does nothing.
// It also reads each workflow's last finished run (GitHub's API, public repo, no cost) so Site health can say when one
// hasn't run in 26 hours, with a button that asks for it at once. Never from staging (Staging::here()).
// Behind the switch audit_clock (on: it's a background job, off stops it asking).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PageCache.php';

final class AuditClock
{
    public const SWITCH = 'audit_clock';
    public const REPO = 'degraffics/traversence';
    /** key (the repository_dispatch type) => [name, workflow file, UTC hours it's asked for, what goes stale when it doesn't run (else the caller's)] */
    public const FLOWS = [
        'site-audit' => ['the site audit (code checks, page tree, deliveries)', 'site-audit.yml', [7, 19]],
        'site-checks' => ['the browser checks and the paths', 'site-checks.yml', [9]],
        'search-probe' => ['the search probe and the phone layout', 'search-probe.yml', [10]],
        // the gauge panel's requests, hourly (GitHub skipped the workflow's own schedule, 2026-11-23): build-requests.json for
        // the session manager's routing (tools/page-audit/gauge-requests.mjs)
        'gauge-requests' => ['the hand-off of gauge fix requests', 'gauge-requests.yml', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23],
            'New requests may not have reached a build, and who has each one may be out of date.'],   // its own consequence (the Design reviewer, 2026-11-24)
    ];
    /** a workflow with no finished run for this long is stale (hours) */
    public const STALE_H = 26;
    /** asked for at most this often by hand (minutes), so a button can't stack runs */
    private const HAND_GAP_MIN = 20;

    public static function on(PDO $pdo): bool
    {
        if (self::staging()) return false;
        require_once __DIR__ . '/Switches.php';
        if (!Switches::ready($pdo)) return true;
        // a background job has no one signed in, so a release's first step ("staff and test accounts") would read as off
        // (2026-11-13: release #9 set it to that step and the clock stayed silent): it runs unless turned off or killed
        $s = Switches::all($pdo)[self::SWITCH] ?? null;
        return !$s || (!$s['killed'] && $s['state'] !== 'off');
    }

    private static function staging(): bool
    {
        if (is_file(__DIR__ . '/Staging.php')) { require_once __DIR__ . '/Staging.php'; if (class_exists('Staging') && method_exists('Staging', 'here')) return Staging::here(); }
        Database::readEnv();
        return (string) getenv('STAGING') === '1';
    }

    private static function token(): string { Database::readEnv(); return trim((string) (getenv('GITHUB_DOCS_TOKEN') ?: '')); }

    private static function stateFile(): string
    {
        $d = __DIR__ . '/../storage/audit-clock';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d . '/asked.json';
    }

    /** key => when it was last asked for (unix time) */
    private static function asked(): array
    {
        $f = self::stateFile();
        $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
        return is_array($j) ? $j : [];
    }

    private static function remember(string $key): void
    {
        $a = self::asked(); $a[$key] = time();
        @file_put_contents(self::stateFile(), json_encode($a), LOCK_EX);
    }

    /** The hour a workflow was last due (unix time), on or before $now. */
    public static function lastDue(string $key, int $now): int
    {
        $best = 0;
        foreach (self::FLOWS[$key][2] as $h) {
            $t = gmmktime($h, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
            if ($t > $now) $t -= 86400;
            $best = max($best, $t);
        }
        return $best;
    }

    /** One request to GitHub: [status, body]. */
    private static function http(string $method, string $url, ?array $json = null): array
    {
        if (!function_exists('curl_init')) return [0, 'no curl'];
        $h = ['User-Agent: traversence-audit-clock', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if (self::token() !== '') $h[] = 'Authorization: Bearer ' . self::token();
        $body = $json !== null ? json_encode($json) : '';
        if ($body !== '') $h[] = 'Content-Type: application/json';
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold($url, 'the audit clock: GitHub ' . $method)) !== null) return [0, $w];   // staging never writes git
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_HTTPHEADER => $h]
            + ($body !== '' ? [CURLOPT_POSTFIELDS => $body] : []));
        $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        return [$code, is_string($res) ? $res : $err];
    }

    /** Asks GitHub to run one workflow now. '' when it was asked, else why not. */
    public static function dispatch(string $key, string $by = 'clock'): string
    {
        if (!isset(self::FLOWS[$key])) return 'Not one of the audit\'s workflows.';
        if (self::staging()) return 'Staging doesn\'t start the audit: the live site does.';
        if (self::token() === '') return 'No GITHUB_DOCS_TOKEN in .env, so the site can\'t ask GitHub.';
        [$code, $body] = self::http('POST', 'https://api.github.com/repos/' . self::REPO . '/dispatches', ['event_type' => $key, 'client_payload' => ['by' => $by]]);
        if ($code === 204) { self::remember($key); return ''; }
        $msg = (string) (json_decode($body, true)['message'] ?? '');
        return 'GitHub said ' . ($code ?: 'nothing') . ($msg !== '' ? ': ' . $msg : '') . ($code === 403 || $code === 404 ? ' (the key needs Contents: read and write on ' . self::REPO . ')' : '') . '.';
    }

    /** The hourly job: asks for each workflow whose hour has come since it was last asked for. Lines for the run's note. */
    public static function tick(PDO $pdo, ?int $now = null): array
    {
        if (!self::on($pdo)) return ['Off (the switch audit_clock, or this is staging).'];
        $now = $now ?? time();
        $asked = self::asked(); $out = [];
        foreach (self::FLOWS as $key => $f) {
            $due = self::lastDue($key, $now);
            if ((int) ($asked[$key] ?? 0) >= $due) continue;
            if ($now - $due > 6 * 3600 && isset($asked[$key])) { self::remember($key); continue; }   // the server was down past the window: wait for the next one
            $why = self::dispatch($key);
            $out[] = $why === '' ? 'Asked GitHub for ' . $key . '.' : 'Couldn\'t ask for ' . $key . ': ' . $why;
        }
        PageCache::forget($pdo, 'auditclock:');
        return $out ?: ['Nothing due.'];
    }

    /**
     * Each workflow's last finished run: key => ['name', 'at' (ISO, '' if none read), 'ok' (its conclusion was success),
     * 'url', 'stale' (no finished run in STALE_H hours), 'asked' (unix time the site last asked)]. Kept 30 minutes;
     * null when GitHub couldn't be read.
     */
    public static function runs(PDO $pdo): ?array
    {
        $got = PageCache::get($pdo, 'auditclock:runs');
        if (is_array($got)) return $got;
        $out = []; $asked = self::asked();
        foreach (self::FLOWS as $key => [$name, $file]) {
            [$code, $body] = self::http('GET', 'https://api.github.com/repos/' . self::REPO . '/actions/workflows/' . $file . '/runs?status=completed&per_page=1&exclude_pull_requests=true');
            if ($code !== 200) return null;
            $r = (array) (json_decode($body, true)['workflow_runs'][0] ?? []);
            $at = (string) ($r['run_started_at'] ?? $r['created_at'] ?? '');
            $out[$key] = ['name' => $name, 'at' => $at, 'ok' => ($r['conclusion'] ?? '') === 'success', 'url' => (string) ($r['html_url'] ?? 'https://github.com/' . self::REPO . '/actions/workflows/' . $file),
                          'stale' => $at === '' || strtotime($at) < time() - self::STALE_H * 3600, 'asked' => (int) ($asked[$key] ?? 0)];
        }
        PageCache::put($pdo, 'auditclock:runs', 1800, $out);
        return $out;
    }

    /** The workflows that haven't run in STALE_H hours (from runs()). */
    /**
     * "No run in 26 hours: <names>. <what goes stale>": a workflow with its own consequence says it; the rest share the caller's
     * ($default: what's true of the audit's readings). $stale: stale()'s rows, keyed by workflow.
     */
    public static function staleWhy(array $stale, string $default): string
    {
        $own = []; $shared = false;
        foreach (array_keys($stale) as $k) { $w = (string) (self::FLOWS[$k][3] ?? ''); if ($w !== '') $own[$w] = true; else $shared = true; }
        return 'No run in ' . self::STALE_H . ' hours: ' . implode(', ', array_column($stale, 'name')) . '.' . ($shared ? ' ' . $default : '') . ($own ? ' ' . implode(' ', array_keys($own)) : '');
    }

    public static function stale(PDO $pdo): array
    {
        if (self::staging()) return [];
        $r = self::runs($pdo);
        return $r ? array_filter($r, fn ($x) => $x['stale']) : [];
    }

    /** A person asks for a run from Site health. Returns the notice; throws when it can't. */
    public static function runNow(PDO $pdo, string $key): string
    {
        if (!isset(self::FLOWS[$key])) throw new RuntimeException('Not one of the audit\'s workflows.');
        $last = (int) (self::asked()[$key] ?? 0);
        if ($last > time() - self::HAND_GAP_MIN * 60) return 'Already asked ' . max(1, (int) round((time() - $last) / 60)) . ' min ago: GitHub starts it within a few minutes, and Site health updates when it finishes.';
        $why = self::dispatch($key, 'pulse');
        if ($why !== '') throw new RuntimeException($why);
        PageCache::forget($pdo, 'auditclock:');
        return 'Asked GitHub to run ' . self::FLOWS[$key][0] . '. It starts within a few minutes; Site health updates when it finishes.';
    }
}
