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
// a decided one-time Data cleanup pass (F9, the health corrections; the IT Manager's design, 2026-11-28): taken before
// anything else, in its own try, so a failure never stops the board's watch; it does nothing when no pass is set
try { require_once __DIR__ . '/../lib/DataCleanup.php'; if (($w = DataCleanup::takeOnce(Database::connection())) !== null) echo $w . "\n"; }
catch (Throwable $x) { error_log('control_tick once-pass: ' . $x->getMessage()); }
require_once __DIR__ . '/../lib/ControlAlarms.php';
echo ControlAlarms::tick(Database::connection()) . "\n";
