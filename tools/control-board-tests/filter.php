<?php
// control-board-filter (Jason, 2026-11-19: "I should be able to click on trip or held and have the section filter to
// those"): each count in a system's header is a button that opens the system and shows only the rows in that state;
// pressed again, or Show all, every row shows; kept through the board's redraws. Checked in a browser at 390 and 1280.
// Run: php filter.php <root>/app
declare(strict_types=1);
$app = $argv[1];
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n\n"; } };
$cc = file_get_contents($app . '/admin/control-console.php'); $js = file_get_contents(dirname($app) . '/website/js/admin-control.js');
$t('each count in a system\'s header is a button naming what it shows', str_contains($cc, '<button type="button" class="cb-lf" data-cb-lf="\' . $k . \'" title="Show only the \''));
$t('each process row carries the light its header counted', str_contains($cc, "data-light=\"' . \$e(\$lt0) . '\""));
$t('a Show all line under the header, hidden until a filter is on', str_contains($cc, '<p class="cb-lfon" hidden><span data-cb-lfw></span> <button type="button" class="ak-b cb-xs" data-cb-lf="">Show all</button></p>'));
$t('the press opens the system and filters its rows without toggling the header; again clears it', str_contains($js, "lf[sys.id] = k && lf[sys.id] !== k ? k : '';") && str_contains($js, 'sys.open = true; lfApply();') && str_contains($js, 'e.preventDefault(); e.stopPropagation();'));
$t('kept through each redraw', str_contains($js, 'loadOpen(); lfApply();'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
