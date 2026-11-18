<?php
// app/scripts/control_tick.php — the System Control Board's watch (decisions/0171 §5, release 3a): every 5 minutes it
// catches what doesn't call in (W0 Indexer quiet, a crawler run that never reported, a site job that stopped or never
// finished) and sends the warnings due their 24-hour email. Each problem is an alarm on the board; it clears by itself.
//   */5 * * * * php /path/to/app/scripts/control_tick.php   (Scheduler::JOBS 'control_tick', added by Scheduler::sync())
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('control_tick');   // its runs show in System Configuration → Scheduled tasks
require_once __DIR__ . '/../lib/ControlAlarms.php';
echo ControlAlarms::tick(Database::connection()) . "\n";
