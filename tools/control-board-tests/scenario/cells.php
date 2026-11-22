<?php
// Step 2's failure map, cell by cell (step2/README.md "The totals": 173): g1 35 rows, g2 31 rows, g3 38 rows (J1–J12,
// C1–C15, D1–D6, M1–M5), g4 69 cells (17 states × board, Pulse, email, push, plus the logbook). cells.md maps each.
$c = [];
for ($i = 1; $i <= 35; $i++) $c[] = 'g1:' . $i;
for ($i = 1; $i <= 31; $i++) $c[] = 'g2:' . $i;
foreach (['J' => 12, 'C' => 15, 'D' => 6, 'M' => 5] as $p => $n) for ($i = 1; $i <= $n; $i++) $c[] = 'g3:' . $p . $i;
foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9a', '9b', '9c', '10', '11', '12', '13', '14', '15'] as $r) foreach (['B', 'P', 'E', 'Pu'] as $ch) $c[] = 'g4:' . $r . $ch;
$c[] = 'g4:16';
return $c;
