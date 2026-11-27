<?php
// /api/releases-pull.php — the ping from git (Jason, 2026-11-15: "need a way to push immediate fixes like this into
// production"): the docs repo's workflow (.github/workflows/release-ping.yml) calls this as soon as a build lands in its
// releases/inbox/ on main, so the site takes it at once instead of at the 10-minute job's next run. It does exactly what
// that job does (Releases::fromGit: only builds already merged into main, each onto staging; one not sent for review goes
// live by itself once its checks there pass), so it needs no secret: anyone calling it can only make the site look sooner.
// It carries the one permission taking a build needs (releases.publish, Releases::asPing) for that one call: since #314 a web
// call with no staff session is nobody, and every ping was refused (2026-11-27). Verdicts are moved on by the 10-minute
// job, never here. At most once a minute, one taker at a time (the lock in fromGit). Never on staging.
//   POST (no body)  -> {ok, lines: [...]}   429 when it looked under a minute ago
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Staging.php';
header('Cache-Control: no-store');
if (Staging::here()) { http_response_code(404); exit; }
Response::requireMethod('POST');
require_once __DIR__ . '/../../app/lib/Releases.php';
$pdo = Database::connection();
Releases::ensure($pdo);
try {
    $st = $pdo->query("SELECT v FROM site_release_settings WHERE k = 'git_seen_ping'");
    $last = json_decode((string) $st->fetchColumn(), true);
    if (is_array($last) && time() - (int) ($last['at'] ?? 0) < 60) Response::error('Looked under a minute ago: the next look takes it.', 429, 'too_soon');
} catch (Throwable $x) { error_log('releases-pull: ' . $x->getMessage()); }
@set_time_limit(240);
try { $lines = Releases::asPing(fn () => Releases::fromGit($pdo, 0, 'ping')); }   // as the job takes builds: publish only, for this one call
catch (Throwable $x) { error_log('releases-pull: ' . $x->getMessage()); Response::error('The look in git stopped: ' . mb_substr($x->getMessage(), 0, 200), 500, 'failed'); }
Response::success(['lines' => array_values($lines)]);
