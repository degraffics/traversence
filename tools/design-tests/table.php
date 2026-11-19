<?php
// tv_table() and tv_w_table()'s wrapper (design/table-standard.md, T1). Run: php table.php <site checkout with app/>
$root = rtrim($argv[1] ?? '', '/');
require $root . '/app/admin/kit-table.php';
$f = 0; $ok = function ($c, $m) use (&$f) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; if (!$c) $f++; };
$h = tv_table(['src' => '/x', 'title' => 'Processes', 'columns' => [['name', 'Process', 'name']], 'menu' => [['pin', 'Pin in your own list']]]);
$ok(str_contains($h, 'class="ak-list"') && str_contains($h, 'data-ak-table='), 'draws the section the List fills');
preg_match('/data-ak-table="([^"]*)"/', $h, $m); $c = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
$ok(($c['src'] ?? '') === '/x' && ($c['menu'][0][1] ?? '') === 'Pin in your own list', 'carries src and the ⋯ menu');
$ok(array_key_exists('filters', $c) && array_key_exists('create', $c), 'fills the list contract\'s defaults');
$ok(str_contains($h, 'akt-skel') && str_contains($h, 'Loading processes'), 'a skeleton while loading, named');
$w = file_get_contents($root . '/app/admin/widgets.php');
$ok(str_contains($w, 'function tv_w_table(') && str_contains($w, 'return tv_table($cfg)'), 'tv_w_table stays and calls tv_table');
echo $f ? "$f failed\n" : "all passed\n"; exit($f ? 1 : 0);
