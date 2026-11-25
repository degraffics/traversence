<?php
// For tickets.cjs: Piece 2's two migrations run as the site runs them, twice, over the Work queue work-queue-seed.php made; then
// a Tier 3 ticket with its brief whole and sent (Waiting for you), and a Work ticket scoped and taken, still at Planning (no
// decision yet). Prints the Resolution's id and the Work ticket's id. php tickets-seed.php <root>/app
$app = $argv[1]; require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php'; $pdo = Database::connection();
$H = dirname(__DIR__, 2) . '/handoffs/';   // what's live before Piece 2: task-scope's and task-adrs' columns and table (#282, #283)
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql'] as $f) foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); }
foreach ([1, 2] as $i) foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql'] as $f) foreach (DbUpdates::statements((string) file_get_contents($app . '/migrations/' . $f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); }
require_once $app . '/lib/GaugeTasks.php'; $J = 'Jason (test)';
$r = GaugeTasks::add($pdo, 'area:backlog', 'May staff\'s own text go to Gemini\'s paid key?', 'Gemini on the paid tier', '0164 §0.8', $J, 1, 'America/Phoenix', 'resolution');
foreach (['why_you' => 'Privacy (0179 rule 3): 0164 §0.8 keeps anything private off Gemini; the paid tier changes the terms, not the rule.', 'boundary' => 'privacy',
    'a_text' => 'The paid key only, checked on every call', 'a_pros' => 'A second model for wording', 'a_cons' => 'A wrong key setting is a privacy risk',
    'b_text' => 'Gemini stays public-only', 'b_pros' => 'Nothing changes; no new risk', 'b_cons' => 'Agents\' wording uses Claude alone', 'recommend' => 'b',
    'delay_impact' => 'Safe on hold: nothing in Piece 2 uses Gemini.', 'cost' => 'None now'] as $k => $v) GaugeTasks::briefEdit($pdo, $r, $k, $v, $J);
GaugeTasks::sendBrief($pdo, $r, $J);
$w = GaugeTasks::add($pdo, 'area:control-board', 'A data migration is never cleared as already there unrun', 'the #284 case', '0162', $J, 1, 'America/Phoenix');
GaugeTasks::edit($pdo, $w, ['goal' => 'A file counts as run only by its own log entry', 'done_when' => '#284\'s case runs on the bench'], $J); GaugeTasks::assign($pdo, $w, $J, $J);
echo $r, ' ', $w, "\n";
