<?php
// app/scripts/releases_tick.php — releases on their schedule (decisions/0145 Phase 5): publishes a release when its time
// comes, takes Claude's builds from git onto staging (decisions/0159 §5, Phase 6), and moves live releases on a step once their hours have passed clean, inside the release window. A failed check
// pauses the release and emails whoever approved it. Every 10 minutes; sets itself up in cPanel through the API
// (Scheduler::AUTO). Does nothing on staging, nor when no release is scheduled or live.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Staging.php';
if (Staging::here()) exit;
require_once __DIR__ . '/../lib/Releases.php';
$pdo = Database::connection();
// Claude's builds waiting in git (decisions/0159 §5, Phase 6): onto staging, every run (one GitHub request when none wait)
$git = [];
try { $git = Releases::fromGit($pdo, 0); } catch (Throwable $e) { error_log('releases_tick from git: ' . $e->getMessage()); }
foreach ($git as $l) echo $l, "\n";
// the before-and-after check (Piece 3, P3-2): each release waiting for the advocates moves on when its look is back
try { require_once __DIR__ . '/../lib/BeforeAfter.php'; foreach (BeforeAfter::advance($pdo) as $l) echo $l, "\n"; } catch (Throwable $e) { error_log('releases_tick before-and-after: ' . $e->getMessage()); }
try {
    Releases::ensure($pdo);
    $n = (int) $pdo->query("SELECT COUNT(*) FROM site_releases WHERE state = 'scheduled' OR (state = 'live' AND step < 3 AND paused = 0)")->fetchColumn();
    if (!$n) exit;   // nothing waiting: no run logged
} catch (Throwable $e) { exit; }
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('releases_tick');   // its runs show in System Configuration → Scheduled tasks
$lines = Releases::tick($pdo);
echo $lines ? implode("\n", $lines) . "\n" : "Nothing due.\n";
