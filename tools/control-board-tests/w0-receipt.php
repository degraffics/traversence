<?php
// The crawler release (2026-11-24): W0's "received 250, accounted for 0" (seen 7,646 times) was the ledger reading only an
// answer's top level, while /api/crawl/index nests its counts under receipt. No database. php w0-receipt.php <app dir>
declare(strict_types=1);
$app = $argv[1] ?? '';
require $app . '/lib/ControlLedger.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n — " . json_encode($got) . "\n"; } };
// a found batch as Frontier::found answers it: 250 received, 3 rejected, 40 junk and 12 skipped inside stored
$rej = [['k1', 'BAD_URL'], ['k2', 'BAD_URL'], ['k3', 'TOO_LONG']];
$c = ControlLedger::counts(['ok' => true, 'receipt' => ['key' => 'b1', 'declared' => 250, 'received' => 250, 'stored' => 247, 'rejected' => $rej, 'checksum' => 'x', 'ok' => true, 'new' => 30, 'new_sites' => 2, 'junk' => 40, 'skipped' => 12]]);
$t('a found receipt: stored 247, refused 3, nothing else (junk, skipped and new sit inside stored)', $c['stored'] === 247 && $c['refused'] === 3 && $c['review'] === 0 && $c['updated'] === 0, $c);
$t('…so stored + refused = received (the alarm\'s sum adds up)', $c['stored'] + $c['refused'] === 250);
$t('…its refusals by reason', $c['reasons'] === ['bad url' => 2, 'too long' => 1], $c['reasons']);
$c = ControlLedger::counts(['ok' => true, 'receipt' => ['received' => 250, 'stored' => 250, 'rejected' => [], 'new' => 0, 'junk' => 0, 'skipped' => 0]]);
$t('a clean batch: 250 stored, 0 refused', $c['stored'] === 250 && $c['refused'] === 0, $c);
$c = ControlLedger::counts(['ok' => true, 'stored' => 4, 'skipped' => 1]);
$t('an answer without a receipt counts as before (top level)', $c['stored'] === 4 && $c['refused'] === 1, $c);
$c = ControlLedger::counts(['ok' => true, 'receipt' => 'no']);
$t('a receipt that isn\'t a batch\'s is ignored, never an error', $c['stored'] === 0 && $c['refused'] === 0, $c);
$src = (string) file_get_contents(dirname($app) . '/website/api/crawl/index.php');
$t('index.php records one line per batch (its crawl_batches row, what it did), never a test batch\'s',
   str_contains($src, "ControlLedger::touched('index batch'") && str_contains($src, "\$kind !== 'test'"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
