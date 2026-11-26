<?php
// app/scripts/founder_mail_tick.php — the site emails the Founder itself (FounderMail; Jason, 2026-11-25): today's batch and
// the session manager's messages, each once. Live only (it exits on staging, and is in Scheduler::NOT_ON_STAGING); it logs
// a run only when it sends (Scheduler::QUIET), so its quiet hours are never "late".
//   */10 * * * * php /path/to/app/scripts/founder_mail_tick.php   (Scheduler::JOBS 'founder_mail_tick', added by Scheduler::sync())
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Staging.php';
if (Staging::here()) exit(0);
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/FounderMail.php';
$lines = FounderMail::tick(Database::connection());
if (!$lines) exit(0);   // nothing to send: no run recorded
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('founder_mail_tick');
echo implode("\n", $lines), "\n";
