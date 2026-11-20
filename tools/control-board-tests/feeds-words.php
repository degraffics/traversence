<?php
// The outside services' words follow their state (2026-11-20, Jason's screenshots of Common Crawl's card): a trip's hold has
// no "until" (it read "Backing off until Jan 1", the trip's far-off stamp); a wait that's over says so (issue #22 read
// "Backing off until Nov 18" two days later); S3's SlowDown (a 503 asking us to reduce our request rate) is a request to
// slow down, not a broken server. Run: php feeds-words.php <root>/app  (no database)
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require_once $app . '/lib/OutsideServices.php'; require_once $app . '/lib/ServiceHealth.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };

$slow = '<?xml version="1.0" encoding="UTF-8"?><Error><Code>SlowDown</Code><Message>Please reduce your request rate.</Message></Error>';
$t('a 503 asking us to slow down (S3\'s SlowDown) is a rate wait', ServiceHealth::classify(503, $slow) === 'rate');
$t('…any other 503 is still a server error', ServiceHealth::classify(503, '<html><title>503 Service Unavailable</title></html>') === 'server');
$t('…and a 429 is still a rate wait', ServiceHealth::classify(429, '') === 'rate');

$base = ['id' => 35, 'service' => 'commoncrawl', 'host' => 'index.commoncrawl.org', 'caller' => 'crawler', 'kind' => 'server', 'state' => 'open', 'hits' => 6,
         'first_at' => '2026-11-19 16:02:00', 'last_at' => '2026-11-19 17:10:00', 'evidence' => '{"latest":{"status":504,"body":"Gateway Time-out"}}', 'probe_url' => null, 'next_probe_at' => null, 'last_probe' => null];
$trip = $base + ['hold_until' => ServiceHealth::TRIP_HOLD];
[, $w] = ServiceHealth::describe($trip);
$t('a trip\'s hold says "Paused by the system (a trip)", never a date (it read "Backing off until Jan 1")', str_contains($w, 'Paused by the system (a trip): no calls until its check passes') && !str_contains($w, 'Jan 1') && !str_contains($w, 'Backing off'), $w);
$soon = $base + ['hold_until' => gmdate('Y-m-d H:i:s', time() + 600)];
[, $w] = ServiceHealth::describe($soon);
$t('a wait still running says until when', str_contains($w, 'Backing off until '), $w);
$over = $base + ['id' => 22, 'hold_until' => '2026-11-18 12:01:00'];
[, $w] = ServiceHealth::describe($over);
$t('a wait that\'s over says so, never "until" a past time (issue #22 read "Backing off until Nov 18")', str_contains($w, 'Its wait ended') && str_contains($w, 'the next call tries it again') && !str_contains($w, 'Backing off until'), $w);
$t('the trip\'s stamp is one constant, used where the hold is set', str_contains((string) file_get_contents($app . '/lib/ServiceHealth.php'), "SET hold_until = '\" . self::TRIP_HOLD . \"'"));
$ops = (string) file_get_contents($app . '/lib/Operations.php'); $con = (string) file_get_contents($app . '/admin/operations-console.php');
$t('the switch-on check\'s "Backing off" line and the card\'s Next try use the same words', str_contains($ops, 'lcfirst(ServiceHealth::waitWords($si))') && str_contains($con, 'ServiceHealth::waitWords($i)'));
$py = (string) @file_get_contents(dirname(__DIR__, 2) . '/workers/crawler/crawler.py');
$t('the crawler classifies SlowDown the same way', $py === '' || str_contains($py, 'if status == 503 and re.search(r"slowdown|reduce your request rate", b):'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
