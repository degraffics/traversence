<?php
// app/scripts/team_tick.php — the Team's runner (decisions/0179's Piece 2, P2-3; TeamRunner): on the staging copy only, while
// the switch team_runner is on, one ticket and one step a run. On the live site it exits at once and records nothing (the job
// is in Scheduler::QUIET, so its silence there is never late).
//   */10 * * * * php /path/to/app/scripts/team_tick.php   (Scheduler::JOBS 'team_tick', added by Scheduler::sync())
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Staging.php';
if (!Staging::here()) exit(0);   // never on live: nothing recorded
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('team_tick');   // its runs show in System Configuration → Scheduled tasks, on staging
require_once __DIR__ . '/../lib/TeamRunner.php';
echo TeamRunner::tick(Database::connection()) . "\n";
