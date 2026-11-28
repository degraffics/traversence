Four of the eight findings (f01, f13, f14, f15) point at code past the truncation point of the attached `ClientOrders.php`, which stops at about line 1000. Parts of f02 and f17 are also past that point. Rule 6 says not to invent that code, so I handled them like this:

- **f02, f12, f16, f17:** real fixes as regions, because the code is visible.
- **f02:** only the guard and the env wrapper are included. Wiring them into `cantInvoice` and the charge path is listed in `verify`.
- **f01, f13, f14, f15:** each folder has a release.json marked blocked. It holds the exact plan and an acceptance test that fails until the lane writes the fix from the full file.

The live file is well over 60 KB, so every change is a region. The regions do not overlap, so the releases can be applied in any order.

=== FILE: f01/tests/f01_paid_in_quickbooks_acceptance.php ===
<?php
// tools/orders-tests/f01_paid_in_quickbooks_acceptance.php: ACCEPTANCE test for f01. It FAILS until the lane writes the fix.
// Static checks only: no database, no QuickBooks, no money (QuickBooksPay::guardReal() is never reached).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$src = function (string $m): string {
    $r = new ReflectionMethod('ClientOrders', $m);
    $lines = file($r->getFileName());
    return implode('', array_slice($lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
};

if (!method_exists('ClientOrders', 'paidInQuickBooks')) { echo "FAIL: ClientOrders::paidInQuickBooks not found (renamed?)\n"; exit(1); }
$s = $src('paidInQuickBooks');
$ok(str_contains($s, 'GET_LOCK') && str_contains($s, 'tv-pay-'), 'paidInQuickBooks takes the tv-pay- lock (as pay() and applyQuickBooks do)');
$ok(str_contains($s, 'RELEASE_LOCK'), 'paidInQuickBooks releases the lock');
$ok(!preg_match("/fromQuickBooks\\([^)]*\\)\\s*===\\s*''/", $s), "no fallback that records the whole balance when fromQuickBooks() returns ''");

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f01/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f01/release.json ===
{
  "name": "f01-paid-in-quickbooks-no-blind-fallback",
  "gate": "BLOCKED (code not shown) - money path: independent check before merge",
  "what": "paidInQuickBooks (ClientOrders.php:1753-1758) must no longer record the whole balance as paid when fromQuickBooks() returns ''. That '' means a lock timeout, nothing new, or a part already paid. Plan: (1) delete the fallback that records the balance; (2) if something must be recorded, take GET_LOCK('tv-pay-'.$oid, 20) and inside it re-read the order and Orders::money(); record only the amount QuickBooks reports for that payment, capped at what is left, with a qbpay-<oid>-<QB payment id> request_key so pay() and applyQuickBooks() see it; release in finally; (3) if QuickBooks reports nothing, say 'QuickBooks shows nothing new yet' and record nothing.",
  "files": [],
  "decisions": [
    "Safest default: record nothing on '' (the fallback deleted outright). Whether staff should get a 'QuickBooks shows nothing yet - record it by hand?' button instead is a person's decision; until then they use Record a payment."
  ],
  "tests": ["tests/f01_paid_in_quickbooks_acceptance.php (static acceptance test: FAILS until the fix is written)"],
  "risks": [
    "Nothing here was run or verified.",
    "No code is included: ClientOrders.php lines 1700-1800 were not in the attachment (it is truncated at about line 1000), so the fix could not be written without inventing code.",
    "The test is a static source check and assumes it sits in tools/orders-tests/ (TV_ROOT overrides the root)."
  ],
  "verify": [
    "file not shown: ClientOrders.php 1731-1758 (paidInQuickBooks, pay()) - write the fix from the full file as planned in 'what'.",
    "Confirm pay() and paidInQuickBooks() use the same lock name 'tv-pay-'.$oid and the same request_key scheme, so one cannot record what the other already did.",
    "Sandbox test: a QB payment for part of the balance is recorded as that part only; '' records nothing; two tabs pressing at once record once.",
    "Person's decision: whether a manual 'record anyway' path is wanted (see decisions)."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f02/files/app/lib/ClientOrders.php.region-1.php ===
<?php
// ANCHOR: replaces |    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }|
// through |    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }|
// (one line; the lane pastes everything below this comment in its place)
    public static function offeredMode(string $mode): string { return in_array($mode, self::OFFERED, true) ? $mode : 'full'; }

    /**
     * Why this order can't be invoiced or charged ('' when it can): a test copy (duplicate() with $test: its name starts
     * "TEST · ", or Orders::isTest) whose QuickBooks mode isn't Sandbox (or the local mock). Jason, 2026-11-10: a test copy
     * is always a sandbox order, no real money, ever. The name is checked as well as Orders::isTest, because a test copy
     * whose qb_env was overwritten with Production may no longer read as a test (f02).
     */
    public static function testEnvBlock(array $o): string
    {
        $test = str_starts_with((string) ($o['bill_name'] ?? ''), 'TEST · ');
        if (!$test) { try { $test = Orders::isTest($o); } catch (Throwable) { $test = false; } }
        if (!$test) return '';
        $env = (string) ($o['qb_env'] ?? '');
        if (in_array($env, ['sandbox', 'mock'], true)) return '';
        return 'This order is a test copy, but it\'s set to QuickBooks ' . ($env !== '' ? $env : '(no mode recorded)') . '. A test is invoiced and paid in Sandbox only, so nothing real is charged: make a new test copy.';
    }

    /**
     * $fn run in the QuickBooks mode the order was made in (its qb_env), never in whatever mode Payments is in now, and
     * refused for a test copy outside Sandbox (testEnvBlock). Wrap invoiceFor() and the card charge in this (f02).
     */
    public static function inOrderEnv(array $o, callable $fn): mixed
    {
        if (($why = self::testEnvBlock($o)) !== '') throw new RuntimeException($why);
        require_once __DIR__ . '/QuickBooksPay.php';
        return QuickBooksPay::withEnv((string) (($o['qb_env'] ?? '') ?: QuickBooksPay::env()), $fn);
    }
=== END FILE ===

=== FILE: f02/tests/f02_test_copy_env.php ===
<?php
// tools/orders-tests/f02_test_copy_env.php: f02's guard. No database, no QuickBooks call, no money.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };

$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'production']) !== '', 'a test copy set to production is refused');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => '']) !== '', 'a test copy with no mode recorded is refused');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'sandbox']) === '', 'a test copy in sandbox is allowed');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'mock']) === '', 'a test copy in mock is allowed');
$ok(ClientOrders::testEnvBlock(['bill_name' => 'Ann Lee', 'qb_env' => 'production']) === '', 'a real order in production is not touched');

$threw = false;
try { ClientOrders::inOrderEnv(['bill_name' => 'TEST · Ann Lee', 'qb_env' => 'production'], fn () => 'charged'); } catch (RuntimeException $x) { $threw = true; }
$ok($threw, 'inOrderEnv refuses before running anything for a test copy in production');

$src = file_get_contents($root . '/app/lib/ClientOrders.php');
$ok(str_contains($src, 'testEnvBlock(') && substr_count($src, 'testEnvBlock(') >= 3, 'testEnvBlock is called from more than its own definition (lane: wire into cantInvoice)');
$ok(substr_count($src, 'inOrderEnv(') >= 2, 'inOrderEnv is used (lane: wrap invoiceFor and the charge)');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f02/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f02/release.json ===
{
  "name": "f02-test-copy-sandbox-only",
  "gate": "PARTIAL - money path: independent check before merge; the wiring below is still to be written",
  "what": "Adds ClientOrders::testEnvBlock() and ClientOrders::inOrderEnv(). A test copy (name starts 'TEST · ' or Orders::isTest) whose qb_env is not sandbox/mock is refused, and QuickBooks work runs in the order's own qb_env, never the mode Payments is in now. Still to be done by the lane (code not shown): in cantInvoice add `if (($w = self::testEnvBlock($o)) !== '') return $w;`; wrap invoiceFor (around :1470-1503) and the card charge (:1731 onward) in self::inOrderEnv($o, fn () => ...); delete the qb_env overwrite at :1731 (or make it only fill an empty qb_env on a non-test order).",
  "files": ["files/app/lib/ClientOrders.php.region-1.php"],
  "decisions": [
    "mock is allowed as well as sandbox: it moves no money and keeps local tests working. Strict 'sandbox only' is a one-word change if a person prefers it.",
    "A test copy with no qb_env recorded is refused (safest), not treated as sandbox.",
    "A real order with an empty qb_env still falls back to the current mode, as readQuickBooks already does."
  ],
  "tests": ["tests/f02_test_copy_env.php (guard cases PASS once the region is in; the last two FAIL until the lane does the wiring)"],
  "risks": [
    "Nothing here was run or verified.",
    "Only the guard is shipped: until cantInvoice, invoiceFor and the charge use it, f02 is not closed.",
    "Orders::isTest() is not shown; the guard also relies on the 'TEST · ' name prefix that duplicate() sets.",
    "inOrderEnv's return type is mixed (PHP 8.0 OK)."
  ],
  "verify": [
    "file not shown: ClientOrders.php :1470, :1481, :1485, :1503 (invoiceFor calls, cantInvoice) and :1731 (qb_env overwrite) - wire the helpers as in 'what'.",
    "file not shown: Orders.php (Orders::isTest) - confirm it does not need the database.",
    "Sandbox test with Payments switched to Production: a test copy approves and is invoiced in sandbox, and the charge goes to sandbox (guardReal()/testHere() hold).",
    "Confirm no existing test copy already has qb_env = production. If one does, it is now refused; that is the intent, but tell staff."
  ],
  "regions": true
}
=== END FILE ===

=== FILE: f15/tests/f15_charge_then_ledger_acceptance.php ===
<?php
// tools/orders-tests/f15_charge_then_ledger_acceptance.php: ACCEPTANCE test for f15. FAILS until the fix is written. Static only, no money.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
if (!method_exists('ClientOrders', 'pay')) { echo "FAIL: ClientOrders::pay not found (renamed?)\n"; exit(1); }
$r = new ReflectionMethod('ClientOrders', 'pay');
$s = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
$ok(str_contains($s, 'needs_books'), 'pay() logs needs_books (with the charge id) when the ledger write after a charge fails');
$ok((bool) preg_match('/catch\s*\(\s*Throwable/', $s), 'pay() catches a failure after the charge instead of losing it');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f15/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f15/release.json ===
{
  "name": "f15-charge-always-recorded",
  "gate": "BLOCKED (code not shown) - money path: independent check before merge",
  "what": "In pay() (ClientOrders.php:1734-1744) the card is charged before the ledger write, so a failure in between leaves a charge with no record. Plan: (1) write the ledger row (self::received(...) with the same $key) immediately after a successful charge, before any invoice, QuickBooks posting, email or stage work; (2) wrap everything after the charge in try/catch(Throwable): on failure, Orders::event($pdo, $oid, 'needs_books', 'Card charged <amount>, charge <charge id>, request <key>, but it wasn\\'t recorded: <message>') and error_log it, then tell the client the payment went through; (3) never retry the charge in the catch.",
  "files": [],
  "decisions": [
    "On a failure after a successful charge the client is told it went through and staff get needs_books (safest: no second charge). Whether to also email staff at once is a person's decision."
  ],
  "tests": ["tests/f15_charge_then_ledger_acceptance.php (static acceptance test: FAILS until the fix is written)"],
  "risks": [
    "Nothing here was run or verified.",
    "No code is included: ClientOrders.php 1731-1758 was not in the attachment."
  ],
  "verify": [
    "file not shown: ClientOrders.php 1731-1758 (pay()) - write the fix as planned.",
    "Confirm $key at :1735/:1738 is the value sent to the gateway as Request-Id (QuickBooksPay charge; file not shown). If it is not, a retry can charge twice: make it so.",
    "Sandbox test: force the ledger write to throw after a sandbox charge, and check that a needs_books event carries the charge id and that no second charge happens."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f13/tests/f13_record_payment_lock_acceptance.php ===
<?php
// tools/orders-tests/f13_record_payment_lock_acceptance.php: ACCEPTANCE test for f13. FAILS until the fix is written. Static only.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
if (!method_exists('ClientOrders', 'recordPayment')) { echo "FAIL: ClientOrders::recordPayment not found (renamed?)\n"; exit(1); }
$r = new ReflectionMethod('ClientOrders', 'recordPayment');
$s = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
$ok(str_contains($s, 'GET_LOCK') && str_contains($s, 'tv-pay-'), 'recordPayment takes the tv-pay- lock');
$ok(str_contains($s, 'RELEASE_LOCK') && str_contains($s, 'finally'), 'recordPayment releases it in finally');
$gl = strpos($s, 'GET_LOCK'); $money = strrpos($s, 'Orders::money');
$ok($gl !== false && $money !== false && $money > $gl, 'the amount left is re-read inside the lock');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f13/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f13/release.json ===
{
  "name": "f13-record-payment-under-lock",
  "gate": "BLOCKED (code not shown) - money path: independent check before merge",
  "what": "recordPayment (ClientOrders.php:2188-2194) takes no lock, so a recorded payment and a card charge can together overrun the total. Plan: $lock = $pdo->quote('tv-pay-'.$oid); refuse with 'Another payment on this order is going through: try again in a moment.' if GET_LOCK($lock, 20) !== 1; inside try: re-read the order and Orders::money(), recompute what is left, refuse an amount above it, then record, post to QuickBooks (recordOtherPayment) and store qb_payment_id, all still inside the lock; finally RELEASE_LOCK.",
  "files": [],
  "decisions": [
    "An amount above what is left is refused, not capped (safest: staff see the conflict). Allowing overpayment as credit is a person's decision (0098 credits)."
  ],
  "tests": ["tests/f13_record_payment_lock_acceptance.php (static acceptance test: FAILS until the fix is written)"],
  "risks": [
    "Nothing here was run or verified.",
    "No code is included: ClientOrders.php 2150-2220 was not in the attachment.",
    "f12's skip on qb_payment_id is only race-free if the QuickBooks post and the qb_payment_id update happen inside this lock."
  ],
  "verify": [
    "file not shown: ClientOrders.php 2188-2194 (recordPayment) - write the fix as planned.",
    "Confirm the lock name matches pay() and applyQuickBooks() exactly ('tv-pay-'.$oid).",
    "Two-session test on a sandbox order: a card charge and a recorded payment at once never total more than the order."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f14/tests/f14_received_dedupe_acceptance.php ===
<?php
// tools/orders-tests/f14_received_dedupe_acceptance.php: ACCEPTANCE test for f14. FAILS until the fix is written. Static only.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$body = function (string $m): string { $r = new ReflectionMethod('ClientOrders', $m); return implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)); };

if (!method_exists('ClientOrders', 'received') || !method_exists('ClientOrders', 'recordPayment')) { echo "FAIL: received()/recordPayment() not found\n"; exit(1); }
$t = (new ReflectionMethod('ClientOrders', 'received'))->getReturnType();
$ok($t !== null && (string) $t === 'bool', 'received() returns bool (false = already recorded)');
$s = $body('recordPayment');
$ok(!preg_match("/date\\(\\s*'[^']*i[^']*'\\s*\\)/", $s), 'the duplicate key no longer uses the minute');
$ok(str_contains($s, 'nonce'), 'the duplicate key comes from the form nonce');
$ok((bool) preg_match('/if\s*\(\s*!\s*self::received\(/', $s), 'the QuickBooks post and the event are skipped when received() says it was a duplicate');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f14/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f14/release.json ===
{
  "name": "f14-record-payment-nonce-key",
  "gate": "BLOCKED (code not shown) - money path: independent check before merge",
  "what": "recordPayment's duplicate key (ClientOrders.php:2193, 2202, 2210) uses the minute. That double-posts across a minute boundary, merges two real same-amount payments in one minute, and still posts to QuickBooks for a deduped record. Plan: (1) received() (:2508-2510) returns bool: true when it inserted the ledger row, false when the request_key was already there; (2) recordPayment keys on a nonce from the form ('rec-'.$oid.'-'.$nonce, the nonce matching /^[a-f0-9]{16,40}$/, made with bin2hex(random_bytes(16)) when the form is rendered); (3) when received() returns false, return 'Already recorded' and skip the QuickBooks post and the event. Callers that ignore the return (applyQuickBooks etc.) are unaffected.",
  "files": [],
  "decisions": [
    "A form with no valid nonce is refused ('Refresh the page and record it again') rather than falling back to a time key (safest).",
    "The admin form that renders the nonce is not shown; it must be updated in the same release."
  ],
  "tests": ["tests/f14_received_dedupe_acceptance.php (static acceptance test: FAILS until the fix is written)"],
  "risks": [
    "Nothing here was run or verified.",
    "No code is included: ClientOrders.php 2188-2210 and 2508-2510 and the admin payment form were not in the attachment."
  ],
  "verify": [
    "file not shown: ClientOrders.php 2188-2210 (recordPayment), 2508-2510 (received()), and the admin form posting to recordPayment.",
    "Grep every caller of received() and confirm none relies on a void return.",
    "Sandbox test: double-submit the same form = one record and one QuickBooks post; two real payments of the same amount in one minute = two records."
  ],
  "regions": false
}
=== END FILE ===

=== FILE: f12/files/app/lib/ClientOrders.php.region-1.php ===
<?php
// ANCHOR: replaces |    private static function applyQuickBooks(PDO $pdo, int $oid, array $pays): string|
// through |    /** The newest payment applied from a linked QuickBooks invoice (readQuickBooks), or null: the Money line says it. */|
// (the lane pastes everything below this comment in its place; the last line is kept as it was)
    private static function applyQuickBooks(PDO $pdo, int $oid, array $pays): string
    {
        $lock = $pdo->quote('tv-pay-' . $oid);
        if ((int) $pdo->query("SELECT GET_LOCK($lock, 20)")->fetchColumn() !== 1) return '';
        try {
            // a payment recorded here and posted to the linked invoice (recordPayment → QuickBooksPay::recordOtherPayment) keeps
            // QuickBooks' id in order_ledger.qb_payment_id: read back from the invoice it's skipped even without our mark (f12)
            $posted = self::hasLedgerQbId($pdo) ? $pdo->prepare('SELECT 1 FROM order_ledger WHERE order_id = ? AND qb_payment_id = ? LIMIT 1') : null;
            $got = 0;
            foreach ($pays as $p) {
                if (!empty($p['ours']) || (int) ($p['cents'] ?? 0) <= 0 || (string) ($p['id'] ?? '') === '') continue;
                $key = 'qbpay-' . $oid . '-' . $p['id'];   // the ledger's keys are unique across orders
                $seen = $pdo->prepare('SELECT 1 FROM order_ledger WHERE order_id = ? AND request_key = ?'); $seen->execute([$oid, $key]);
                if ($seen->fetchColumn()) continue;   // applied once: twice is nothing
                if ($posted) { $posted->execute([$oid, (string) $p['id']]); $mine = $posted->fetchColumn(); $posted->closeCursor(); if ($mine) continue; }   // posted from here: already in the ledger
                $o = Orders::get($pdo, $oid);
                if (!$o || $o['status'] !== 'pending') break;
                $left = (int) $o['total_cents'] - (int) Orders::money($pdo, $oid)['paid'];
                if ($left <= 0) break;
                $c = min((int) $p['cents'], $left);
                $ref = trim(strtolower((string) ($p['method'] ?? '')) . ' ' . (string) ($p['ref'] ?? ''));
                self::received($pdo, $oid, $c, $c < $left ? 'deposit' : 'charge', 'QuickBooks', $ref, $key, false);
                if (($d = strtotime((string) ($p['date'] ?? ''))) && $d <= time()) $pdo->prepare('UPDATE order_ledger SET created_at = ? WHERE order_id = ? AND request_key = ?')->execute([date('Y-m-d 12:00:00', $d), $oid, $key]);   // when it was paid
                if ((int) $p['cents'] > $c) Orders::event($pdo, $oid, 'needs_books', 'QuickBooks shows ' . Orders::usd((int) $p['cents']) . ' paid on invoice ' . $o['invoice_no'] . '; only ' . Orders::usd($c) . ' was left on this order, so that much is recorded. Check the order\'s lines against the invoice.');
                $got += $c;
            }
            return $got ? Orders::usd($got) . ' paid in QuickBooks, recorded' : '';
        } finally { $pdo->query("SELECT RELEASE_LOCK($lock)"); }
    }

    /** Has order_ledger a qb_payment_id column (a payment this site posted to QuickBooks keeps QuickBooks' id there)? */
    private static function hasLedgerQbId(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $pdo->query('SELECT qb_payment_id FROM order_ledger LIMIT 0'); $ok = true; }
            catch (Throwable) { $ok = false; error_log('applyQuickBooks: order_ledger.qb_payment_id missing; payments posted from here are told apart by their mark only'); }
        }
        return $ok;
    }

    /** The newest payment applied from a linked QuickBooks invoice (readQuickBooks), or null: the Money line says it. */
=== END FILE ===

=== FILE: f12/tests/f12_skip_posted_payments.php ===
<?php
// tools/orders-tests/f12_skip_posted_payments.php: f12. Static checks (no DB, no QuickBooks, no money).
// A database test needs MySQL (GET_LOCK) and is listed in release.json 'verify' for the lane.
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$body = function (string $m): string { $r = new ReflectionMethod('ClientOrders', $m); return implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)); };

$s = $body('applyQuickBooks');
$ok(str_contains($s, 'qb_payment_id'), 'applyQuickBooks checks order_ledger.qb_payment_id');
$ok(strpos($s, 'qb_payment_id = ?') !== false && strpos($s, 'order_id = ? AND qb_payment_id') !== false, 'the check is scoped to this order (sandbox and production ids can collide)');
$ok(strpos($s, '$posted') < strpos($s, 'self::received('), 'the skip comes before anything is recorded');
$ok(str_contains($s, "GET_LOCK") && str_contains($s, 'RELEASE_LOCK'), 'still under the tv-pay- lock');
$ok(method_exists('ClientOrders', 'hasLedgerQbId'), 'hasLedgerQbId exists');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f12/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f12/release.json ===
{
  "name": "f12-skip-qb-payments-posted-here",
  "gate": "money path: independent check before merge",
  "what": "applyQuickBooks now also skips a QuickBooks payment whose id is already in order_ledger.qb_payment_id for this order (a payment recorded here and posted to the linked invoice). Before, that depended only on the 'ours' mark.",
  "files": ["files/app/lib/ClientOrders.php.region-1.php"],
  "decisions": [
    "The check is per order, because QuickBooks ids are unique per company and sandbox and production can repeat them.",
    "A ledger row with that id whose record was later cancelled (order_payment_cancels) still blocks re-applying the QuickBooks payment. Safest: no double record. The correction is made in QuickBooks (0178 money note). A person may decide otherwise.",
    "If the column is missing, it logs and falls back to today's mark-only behaviour, rather than stopping all QuickBooks reads."
  ],
  "tests": ["tests/f12_skip_posted_payments.php"],
  "risks": [
    "Nothing here was run or verified.",
    "Race: if recordPayment posts to QuickBooks and writes qb_payment_id outside the tv-pay- lock, a read in between can still double-record. f13 closes that by holding the lock across post and update."
  ],
  "verify": [
    "file not shown: the migration that adds order_ledger.qb_payment_id, and the code that writes it (recordPayment, around :2188-2210). Confirm the column exists in production and is filled for posted payments.",
    "file not shown: QuickBooksPay::invoiceByNumber's 'ours' mark - confirm what it reads.",
    "DB test (MySQL, sandbox): record a payment on a linked order (posted to sandbox QuickBooks), run readQuickBooks($pdo, $oid, true), and check that the ledger has one row, not two."
  ],
  "regions": true
}
=== END FILE ===

=== FILE: f17/files/app/lib/ClientOrders.php.region-1.php ===
<?php
// ANCHOR: replaces |     * Why an order can't be released for delivery (Shipped or Ready for pickup) yet, or '' when it can. One rule on every|
// through |     * Why an approved order can't go to a vendor yet (a work order sent or its link handed over, or marked sent to a|
// (the lane pastes everything below this comment in its place; the last two lines are kept as they were)
     * Why an order can't be released for delivery (Shipped or Ready for pickup) yet, or '' when it can. One rule on every
     * path (decisions/0101 note of 2026-11-20, B.4, as the order model changes it, note of 2026-11-23): paid in full
     * ("Balance must be paid before order is release for delivery", model 1.9), except an admin's release before payment
     * (releaseHold, with a reason). Invoice later is no longer an exception (model §0 item 3), and neither are outside
     * terms (f17): money taken outside QuickBooks is recorded on the order (Record a payment), and until it is, delivery
     * waits like any order's.
     */
    public static function shipHold(PDO $pdo, array $o): string
    {
        $oid = (int) $o['id'];
        $left = (int) $o['total_cents'] - Orders::money($pdo, $oid)['paid'];
        if ($left <= 0 || self::overridden($pdo, $oid)) return '';
        return 'Order #' . Orders::no($o) . ' isn\'t paid in full yet (' . Orders::usd($left) . ' still to pay), so it can\'t be released for delivery (shipped or ready for pickup). Record the payment when it comes in, or an admin can release it before payment, with a reason.';
    }

    /**
     * Why an approved order can't go to a vendor yet (a work order sent or its link handed over, or marked sent to a
=== END FILE ===

=== FILE: f17/tests/f17_outside_ship_hold.php ===
<?php
// tools/orders-tests/f17_outside_ship_hold.php: f17. Static checks (shipHold reads Orders::money from the DB; the DB test is in 'verify').
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$r = new ReflectionMethod('ClientOrders', 'shipHold');
$s = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
$ok(!str_contains($s, "'outside'"), "shipHold no longer lets 'outside' orders through");
$ok(str_contains($s, 'self::overridden('), "an admin's release with a reason still lets it through");
$ok(str_contains($s, "Orders::money("), 'it still checks what is paid');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f17/faq/faq-entries.md ===
no faq change
=== END FILE ===

=== FILE: f17/release.json ===
{
  "name": "f17-outside-orders-wait-for-payment",
  "gate": "order flow: review before merge",
  "what": "shipHold no longer returns '' for pay_mode 'outside'. An order paid outside QuickBooks ships or becomes ready only once the payment is recorded, or when an admin releases it with a reason, like every other order (model 1.9).",
  "files": ["files/app/lib/ClientOrders.php.region-1.php"],
  "decisions": [
    "If an 'outside' order is approved with an automatic 'released' event (see verify), overridden() would still let it ship with no admin reason. Whether outside orders should be auto-released at all is a person's decision. The safest default is no auto-release: staff record the payment or an admin releases it."
  ],
  "tests": ["tests/f17_outside_ship_hold.php"],
  "risks": [
    "Nothing here was run or verified.",
    "Behaviour change: open 'outside' orders with nothing recorded as paid can no longer be marked shipped or ready until staff record the payment. Tell staff before release.",
    "boardState (around :1050) still counts 'outside' as release-met for the 'staged for production' wording. That is display only and left alone; it can say staged while delivery waits."
  ],
  "verify": [
    "file not shown: ClientOrders.php :798-799 context, :1449 and :1453 (the approve/outside path). If it records an order_events 'released' row automatically for outside orders, this fix does nothing until that is removed or made to require an admin and a reason.",
    "file not shown: whichever item-status path checks OUT_DOOR statuses against shipHold - confirm it calls shipHold and has no 'outside' shortcut of its own.",
    "DB test (sandbox): an 'outside' order with nothing recorded is refused at setStage('shipped'); after Record a payment for the full amount it is allowed.",
    "Query open 'outside' orders with a balance before release, so staff can record their payments."
  ],
  "regions": true
}
=== END FILE ===

=== FILE: f16/files/app/lib/ClientOrders.php.region-1.php ===
<?php
// ANCHOR: replaces |    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array|
// through |    /** Who to tell about the client's answers: the staff member who built it (their work email), else the site's address. */|
// (the lane pastes everything below this comment in its place; the last line is kept as it was)
    public static function depositFor(PDO $pdo, array $o, int $total, ?array $lines = null): array
    {
        $pct = (int) ($o['deposit_pct'] ?? 0);
        if ($pct > 0) return [min($total, (int) round($total * $pct / 100)), $pct . '% of the total, as set', [], []];
        $lines ??= Orders::lines($pdo, (int) $o['id']);
        // 0177 §3: only a COGS that's recorded and checked (Costing, the Cost tab) is figured from cost; unchecked or zero is 65%
        return self::depositFromLines($lines, $total, fn (array $l): bool => self::costed($pdo, (int) ($l['id'] ?? 0)));
    }

    /**
     * depositFor's sum, given which lines' COGS is checked ($checked($line) => bool). A checked item: (qty × cost_cents +
     * ship_cost_cents) × (100 + DEPOSIT_MARKUP)%; shipping we pay goes on top of its COGS, never counts as a COGS alone
     * (f16). Any other item: DEPOSIT_DEFAULT % of its price (with the shipping charged). Never above $total.
     * Returns [cents, words for our team, the titles and ids of items without a checked cost].
     */
    public static function depositFromLines(array $lines, int $total, callable $checked): array
    {
        $cost = 0; $guess = 0; $open = []; $openIds = [];
        foreach ($lines as $l) {
            if (($l['status'] ?? '') === 'cancelled') continue;
            $qty = max(1, (int) ($l['qty'] ?? 1));
            $cogs = $qty * (int) ($l['cost_cents'] ?? 0);
            if ($cogs > 0 && $checked($l)) { $cost += $cogs + max(0, (int) ($l['ship_cost_cents'] ?? 0)); continue; }
            $price = $qty * (int) ($l['unit_cents'] ?? 0) + (int) ($l['ship_cents'] ?? 0);
            if ($price <= 0) continue;
            $guess += (int) round($price * self::DEPOSIT_DEFAULT / 100); $open[] = (string) ($l['title'] ?? ''); $openIds[] = (int) ($l['id'] ?? 0);
        }
        $dep = min($total, (int) round($cost * (100 + self::DEPOSIT_MARKUP) / 100) + $guess);
        $words = 'items\' cost + ' . self::DEPOSIT_MARKUP . '%' . ($open ? '; ' . count($open) . ' item' . (count($open) === 1 ? '' : 's') . ' at ' . self::DEPOSIT_DEFAULT . '% of price (cost not checked)' : '');
        return [$dep, $words, $open, $openIds];
    }

    /** Who to tell about the client's answers: the staff member who built it (their work email), else the site's address. */
=== END FILE ===

=== FILE: f16/tests/f16_deposit_checked_cogs.php ===
<?php
// tools/orders-tests/f16_deposit_checked_cogs.php: f16, the deposit's pure sum (no DB, no money).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/ClientOrders.php';

$fail = 0;
$ok = function (bool $c, string $what) use (&$fail): void { echo ($c ? 'PASS' : 'FAIL') . ': ' . $what . "\n"; if (!$c) $fail++; };
$all = fn (array $l): bool => true;
$none = fn (array $l): bool => false;
$line = fn (array $x): array => $x + ['id' => 1, 'title' => 'Item', 'qty' => 1, 'unit_cents' => 0, 'ship_cents' => 0, 'cost_cents' => 0, 'ship_cost_cents' => 0, 'status' => 'open'];

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000, 'ship_cost_cents' => 200])], 100000, $all);
$ok($c === 1380, 'checked COGS + shipping we pay, +15%: (1000+200)×1.15 = 1380 (got ' . $c . ')');

[$c, , $open] = ClientOrders::depositFromLines([$line(['unit_cents' => 4000, 'ship_cost_cents' => 500])], 100000, $all);
$ok($c === 2600 && count($open) === 1, 'shipping cost only (no COGS) falls back to 65% of price = 2600 (got ' . $c . ')');

[$c, , $open, $ids] = ClientOrders::depositFromLines([$line(['id' => 7, 'unit_cents' => 5000, 'cost_cents' => 1000])], 100000, $none);
$ok($c === 3250 && $ids === [7], 'an unchecked COGS falls back to 65% of price = 3250 and is listed (got ' . $c . ')');

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000])], 100, $all);
$ok($c === 100, 'never above the total');

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000, 'status' => 'cancelled'])], 100000, $all);
$ok($c === 0, 'a cancelled item counts for nothing');

[$c] = ClientOrders::depositFromLines([$line(['unit_cents' => 5000, 'cost_cents' => 1000]), $line(['id' => 2, 'unit_cents' => 2000])], 100000, fn (array $l): bool => (int) $l['id'] === 1);
$ok($c === 1150 + 1300, 'mixed: 1000×1.15 + 2000×65% = 2450 (got ' . $c . ')');

echo $fail ? "FAILED ($fail)\n" : "ALL PASS\n";
exit($fail ? 1 : 0);
=== END FILE ===

=== FILE: f16/faq/faq-entries.md ===
### How is my deposit worked out?

On an order paid with a deposit, the deposit covers what your items cost us to make, plus the shipping we pay, plus 15%. Where an item's cost hasn't been confirmed yet, that item's deposit is 65% of its price instead. The deposit is never more than the order's total. If we've agreed a set percentage with you, that percentage is used instead. Your order page and the email we send show the deposit before you approve.
=== END FILE ===

=== FILE: f16/release.json ===
{
  "name": "f16-deposit-only-checked-cogs",
  "gate": "money amount shown to clients: review before merge",
  "what": "depositFor now uses the cost path only when the item's COGS is above zero and Costing says it is checked (self::costed). The shipping we pay is added on top of that COGS. An item with shipping cost only, unchecked COGS or zero COGS is figured at 65% of its price, as 0177 §3 says. The sum moved into a pure depositFromLines() so it can be tested.",
  "files": ["files/app/lib/ClientOrders.php.region-1.php"],
  "decisions": [
    "'Checked' is read as self::costed($pdo, $lineId), as refreshPrices already uses it. A cost typed on a custom line (addCustom) that Costing has not checked now counts at 65%. That follows 0177 §3, but a person should confirm it.",
    "The 65% fallback is of the price including shipping charged (as before); the shipping we pay is not added to it."
  ],
  "tests": ["tests/f16_deposit_checked_cogs.php"],
  "risks": [
    "Nothing here was run or verified.",
    "Deposits on orders still in draft or sent can go up when they are next figured. Already-approved orders keep their stored deposit_cents, but confirm nothing recomputes those.",
    "One costed() query per line: fine for normal order sizes."
  ],
  "verify": [
    "file not shown: ClientOrders::costed() (probably around :2851) and Costing.php - confirm costed() returns true only for a checked COGS and does not throw when the Costing table is missing. If the table is missing, every item would fall to 65%.",
    "Check where deposit_cents is set at approval (not shown) still calls depositFor.",
    "Sandbox: a deposit order with one checked and one unchecked item shows cost+15% plus 65%, and lists the unchecked item."
  ],
  "regions": true
}
=== END FILE ===