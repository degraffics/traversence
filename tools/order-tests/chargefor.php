<?php
// tools/order-tests/chargefor.php <lib dir>: the chargeFor fix (decisions/0101 note 2026-11-20; map §5): a test-marked order in production is looked up for its refund, not refused.
$dir = $argv[1];
require $dir . '/QuickBooksPay.php';
$pdo = new PDO('sqlite::memory:');
$o = ['id' => 9, 'bill_name' => 'TEST · Acme', 'qb_env' => 'production', 'invoice_id' => '', 'charge_id' => ''];
$ok = 0; $n = 0;
$check = function ($name, $cond) use (&$ok, &$n) { $n++; if ($cond) $ok++; echo ($cond ? 'ok  ' : 'FAIL') . " $name\n"; };
try { $r = QuickBooksPay::withEnv('production', fn () => QuickBooksPay::chargeFor($pdo, $o)); $check('test copy in production: looked up, no charge found -> ""', $r === ''); }
catch (Throwable $e) { $check('test copy in production: looked up (got: ' . $e->getMessage() . ')', false); }
$o2 = $o; $o2['charge_id'] = 'CH-1';
$check('charge taken on our page returned as before', QuickBooksPay::withEnv('production', fn () => QuickBooksPay::chargeFor($pdo, $o2)) === 'CH-1');
try { QuickBooksPay::withEnv('production', fn () => QuickBooksPay::chargeCard($pdo, $o, 'tok', 'k')); $check('charging a test copy still refused', false); }
catch (Throwable $e) { $check('charging a test copy still refused', str_contains($e->getMessage(), 'test order')); }
echo "$ok/$n\n"; exit($ok === $n ? 0 : 1);
