<?php
// T4 (design/table-standard.md): sorting a list with group rows, run from api.php's own block.
// php tools/design-tests/t4-sort.php <path to website/admin/api.php>
$src = file_get_contents($argv[1]);
$a = strpos($src, "            if (\$sort !== '' && \$rows) {"); $b = strpos($src, "            \$total = count(\$rows);", $a);
$block = substr($src, $a, $b - $a);
$st = fn ($t, $w) => ['tone' => $t, 'word' => $w];
$base = [
  ['id' => 'sys:a', '_group' => ['label' => 'Workers', 'status' => $st('ok', 'Working'), 'rows' => [['name' => 'W2', 'stage' => 'Observe', 'light' => $st('ok', 'Working')], ['name' => 'W0', 'stage' => 'Start', 'light' => $st('info', 'Not tested')]]]],
  ['id' => 'sys:b', '_group' => ['label' => 'Outside services', 'status' => $st('bad', 'Tripped'), 'rows' => [['name' => 'Overpass', 'stage' => 'Start', 'light' => $st('bad', 'Tripped')], ['name' => 'Claude', 'stage' => 'Observe', 'light' => $st('ok', 'Working')]]]],
  ['id' => 'sys:c', '_group' => ['label' => 'The Knowledge Engine', 'status' => $st('warn', 'Held'), 'rows' => [['name' => 'W1', 'stage' => 'Pre-flight', 'light' => $st('warn', 'Held')]]]],
];
$tool = ['columns' => [['name', 'Process', 'name'], ['light', 'Status', 'status'], ['stage', 'Stage', 'pill']]];
$run = function ($sort, $dir) use ($block, $base, $tool) { $rows = $base; $in = ['dir' => $dir]; eval($block); return $rows; };
$show = fn ($rows, $col) => implode(' | ', array_map(fn ($g) => $g['_group']['label'] . ': ' . implode(',', array_map(fn ($k) => is_array($k[$col]) ? $k[$col]['word'] : $k[$col], $g['_group']['rows'])), $rows));
$ok = 0; $bad = 0; $t = function ($name, $got, $want) use (&$ok, &$bad) { if ($got === $want) { $ok++; } else { $bad++; echo "FAIL $name\n  got:  $got\n  want: $want\n"; } };
$t('stage asc: inside each group, groups keep order', $show($run('stage', ''), 'stage'), 'Workers: Observe,Start | Outside services: Observe,Start | The Knowledge Engine: Pre-flight');
$t('stage desc', $show($run('stage', 'desc'), 'stage'), 'Workers: Start,Observe | Outside services: Start,Observe | The Knowledge Engine: Pre-flight');
$t('status asc: worst group first, worst row first', $show($run('light', ''), 'light'), 'Outside services: Tripped,Working | The Knowledge Engine: Held | Workers: Not tested,Working');
$t('status desc', $show($run('light', 'desc'), 'light'), 'Workers: Working,Not tested | The Knowledge Engine: Held | Outside services: Working,Tripped');
$t('process: groups by name', $show($run('name', ''), 'name'), 'Outside services: Claude,Overpass | The Knowledge Engine: W1 | Workers: W0,W2');
// a plain list still sorts, and a status column no longer compares as "Array"
$block2 = $block; $rows = [['n' => 'b', 's' => $st('ok', 'Working')], ['n' => 'a', 's' => $st('bad', 'Tripped')]]; $in = []; $tool = []; $sort = 's'; eval($block2);
$t('plain list by status', $rows[0]['n'], 'a');
$rows = [['n' => 'b'], ['n' => 'a']]; $sort = 'n'; $in = ['dir' => 'desc']; eval($block2); $t('plain list by text desc', $rows[0]['n'], 'b');
echo "$ok passed, $bad failed\n";
