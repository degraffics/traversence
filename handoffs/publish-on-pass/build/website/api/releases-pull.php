<?php
// /api/releases-pull.php — the ping from git (Jason, 2026-11-15: "need a way to push immediate fixes like this into
// production"): the docs repo's workflow (.github/workflows/release-ping.yml) calls this as soon as a build lands in its
// releases/inbox/ on main, so the site takes it at once instead of at the 10-minute job's next run. It does exactly what
// that job does (Releases::fromGit: only builds already merged into main, each onto staging; one not sent for review goes
// live by itself once its checks there pass), so it needs no secret: anyone calling it can only make the site look sooner.
// It carries the one permission taking a build needs (releases.publish, Releases::asPing) for that one call: since #314 a web
// call with no staff session is nobody, and every ping was refused (2026-11-27). Since 2026-11-28 (Jason: "Push to live
// should be done immediately, upon pass") it also moves on each release whose before-and-after look is back, as the job
// does (BeforeAfter::advance, under the same grant): beforeafter.yml pings when it commits a verdict, so a pass publishes
// then, not at the job's next run. Nothing from the request decides anything: the verdict is read from main, and a release
// with no pass there stays where it is. At most once a minute, one taker at a time (the locks in fromGit and advance).
// Never on staging.
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
$lines = []; $stopped = '';
try { $lines = Releases::asPing(fn () => Releases::fromGit($pdo, 0, 'ping')); }   // as the job takes builds: publish only, for this one call
catch (Throwable $x) { error_log('releases-pull: ' . $x->getMessage()); $stopped = mb_substr($x->getMessage(), 0, 200); }
// each release whose look is back moves on now, as releases_tick does next (a step that throws leaves it waiting for the job)
try {
    require_once __DIR__ . '/../../app/lib/BeforeAfter.php';
    foreach (Releases::asPing(fn () => BeforeAfter::advance($pdo)) as $l) $lines[] = $l;
} catch (Throwable $x) { error_log('releases-pull before-and-after: ' . $x->getMessage()); }
if ($stopped !== '') Response::error('The look in git stopped: ' . $stopped, 500, 'failed');
Response::success(['lines' => array_values($lines)]);
