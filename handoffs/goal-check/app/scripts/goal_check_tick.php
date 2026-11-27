<?php
// app/scripts/goal_check_tick.php — the daily goal check (GoalCheck; CLAUDE.md "Goals and records live with the roles"):
// a goal's ticket not moved in 3 days → its lane's manager; a goal's date at risk → its executive. Daily on the live site
// only (Scheduler::NOT_ON_STAGING); sets itself up in cPanel through the API (Scheduler::AUTO). Logs every run.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('goal_check_tick');   // its runs show in System Configuration → Scheduled tasks
require_once __DIR__ . '/../lib/GoalCheck.php';
try { $n = GoalCheck::tick(Database::connection()); echo 'Goals ' . $n['goals'] . ', stalled ' . $n['stalled'] . ', at risk ' . $n['at_risk'] . ', opened ' . $n['opened'] . ', cleared ' . $n['cleared'] . ', no date ' . $n['no_date'] . "\n"; }
catch (Throwable $e) { echo 'Not ready yet: ' . $e->getMessage() . "\n"; }
