<?php
// decisions/0175 release 2b part A: every old address of the board's tables, Scheduled tasks, Pre-flight and Automation
// leads to its tab on the board (?tab=), its query kept and no fragment of its own (the browser keeps the link's own
// #job-<key> or #pf-<line>); opened in a tab (?panel=1) or the side panel (?embed=1), the page itself; not in the sections
// menu. Read from website/admin/router.php (TV_ADMIN_FOLDED, beside TV_ADMIN_MOVED). Run: php moved.php <root>/website
declare(strict_types=1);
$src = (string) file_get_contents($argv[1] . '/admin/router.php');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
preg_match('/const TV_ADMIN_FOLDED = (\[.*?\]);/s', $src, $m); $folded = eval('return ' . $m[1] . ';');
preg_match('/const TV_ADMIN_MOVED = (\[.*?\]);/s', $src, $m2); $moved = eval('return ' . $m2[1] . ';');
foreach ($folded as $k => $tb) $moved[$k] = 'config/control?tab=' . $tb;
$want = ['config/alarms' => 'alarms', 'config/log' => 'log', 'config/ledger' => 'ledger', 'config/upkeep' => 'upkeep', 'config/scheduler' => 'scheduler', 'config/preflight' => 'preflight', 'config/automation' => 'startup'];
foreach ($want as $old => $tab) $t("/admin/$old leads to the board's $tab tab", ($moved[$old] ?? '') === 'config/control?tab=' . $tab, $moved[$old] ?? null);
$t('the earlier move is kept (Outside services → the Service register)', ($moved['config/services'] ?? '') === 'config/register');
// the Location as the router writes it
$loc = function (string $to, string $q, string $id = ''): string { return '/admin/' . (str_contains($to, '?') ? $to . ($q !== '' ? '&' . $q : '') : $to . ($id !== '' ? '/' . rawurlencode($id) : '') . ($q !== '' ? '?' . $q : '')); };
$t('…its query kept, and no fragment, so the link\'s own spot comes along', $loc($moved['config/scheduler'], 'x=1') === '/admin/config/control?tab=scheduler&x=1' && !str_contains($moved['config/scheduler'], '#'));
$t('the router writes that Location', str_contains($src, "header('Location: /admin/config/control?tab=' . TV_ADMIN_FOLDED[\$mk . '/' . \$sk] . (\$q !== '' ? '&' . \$q : ''), true, 302)"));
$t('opened in a tab or the side panel, the page itself (no redirect)', str_contains($src, "if (isset(TV_ADMIN_FOLDED[\$mk . '/' . \$sk]) && !\$panel && empty(\$_GET['embed']))"));
$t('a moved section isn\'t in the sections menu', str_contains($src, "if (isset(TV_ADMIN_FOLDED[\$mk . '/' . \$k])) continue;"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
