<?php
// For old-suites.sh: Piece 2's two migrations over a bench the Work queue's own suites seeded (task-scope's and task-adrs'
// migrations first, as live has them), so those suites run on this release's code. Prints nothing. php tickets-migrate.php <root>/app
$app = $argv[1]; require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php'; $pdo = Database::connection();
$H = dirname(__DIR__, 2) . '/handoffs/';
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql', $app . '/migrations/2026-11-25_tickets.sql', $app . '/migrations/2026-11-25_tickets_stage.sql'] as $f)
    foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); }
