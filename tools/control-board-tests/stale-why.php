<?php
// loads-reply: a workflow that hasn't run says what goes stale for it (the Design reviewer, 2026-11-24): the hand-off of gauge fix
// requests its own sentence, the audit's checks the caller's. No database. php stale-why.php <app dir>
declare(strict_types=1);
require ($argv[1] ?? '') . '/lib/AuditClock.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n — " . json_encode($got) . "\n"; } };
$g = ['gauge-requests' => ['name' => 'the hand-off of gauge fix requests']]; $a = ['site-checks' => ['name' => 'the browser checks and the paths']];
$w = AuditClock::staleWhy($g, 'What the gauges read may be out of date.');
$t('gauge requests alone: its own sentence, never the gauges\' one', $w === 'No run in 26 hours: the hand-off of gauge fix requests. New requests may not have reached a build, and who has each one may be out of date.', $w);
$w = AuditClock::staleWhy($a, 'What the gauges read may be out of date.');
$t('an audit check alone: the caller\'s sentence, as before', $w === 'No run in 26 hours: the browser checks and the paths. What the gauges read may be out of date.', $w);
$w = AuditClock::staleWhy($a + $g, 'What Site health shows may be out of date.');
$t('both: both names, each sentence once', $w === 'No run in 26 hours: the browser checks and the paths, the hand-off of gauge fix requests. What Site health shows may be out of date. New requests may not have reached a build, and who has each one may be out of date.', $w);
$t('gauge-requests asked for every hour', AuditClock::FLOWS['gauge-requests'][1] === 'gauge-requests.yml' && AuditClock::FLOWS['gauge-requests'][2] === range(0, 23));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
