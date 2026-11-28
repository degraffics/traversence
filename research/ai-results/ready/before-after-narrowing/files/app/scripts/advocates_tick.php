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
