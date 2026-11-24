<?php
// The audit clock's request to GitHub (Run: an empty database; php audit-clock-retry.php <root>/app): a request that gets no answer
// at all (the connection failed: 03:17 and 04:17 UTC on 2026-11-24 said only "GitHub said nothing") is tried once more after
// 5 seconds, and the note keeps the connection's own error. The connection is made to fail with an unreachable proxy; never
// reaches GitHub.
$app = $argv[1];
putenv('GITHUB_DOCS_TOKEN=test-not-a-key'); putenv('HTTPS_PROXY=http://127.0.0.1:9'); putenv('https_proxy=http://127.0.0.1:9');
require $app . '/lib/Database.php'; require $app . '/lib/AuditClock.php';
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$t0 = microtime(true); $why = AuditClock::dispatch('gauge-requests', 'test'); $s = microtime(true) - $t0;
$t('no answer: tried again after 5 seconds', $s >= 5 && $s < 60, round($s, 1));
$t('…and says so with the connection\'s own error, never "GitHub said nothing"', str_starts_with($why, 'GitHub didn\'t answer, twice 5 seconds apart (') && !str_contains($why, 'said nothing'), $why);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
