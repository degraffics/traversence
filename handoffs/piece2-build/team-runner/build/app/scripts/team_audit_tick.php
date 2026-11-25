<?php
// app/scripts/team_audit_tick.php — the Team's daily audit (decisions/0179's Piece 2, P2-4; TeamRunner::audit): on the staging copy
// only, while the switch team_runner is on. On the live site it exits at once and records nothing (Scheduler::QUIET).
//   daily: php /path/to/app/scripts/team_audit_tick.php   (Scheduler::JOBS 'team_audit_tick', added by Scheduler::sync())
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Staging.php';
if (!Staging::here()) exit(0);   // never on live: nothing recorded
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('team_audit_tick');
require_once __DIR__ . '/../lib/TeamRunner.php';
echo TeamRunner::audit(Database::connection()) . "\n";
