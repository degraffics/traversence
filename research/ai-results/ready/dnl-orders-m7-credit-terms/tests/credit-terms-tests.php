<?php
// tools/orders-tests/credit-terms-tests.php — Orders M7, credit terms (decisions/0178 §4). Plain PHP, prints PASS/FAIL.
// Runs on an in-memory SQLite database (pdo_sqlite): it never touches the live database, and no code path here reaches
// QuickBooksPay (nothing is charged, refunded or posted; guardReal()/testHere() are not needed and not bypassed).
// Run AFTER the four ClientOrders.php regions are applied. APP_DIR overrides where app/ is.
declare(strict_types=1);

$app = rtrim((string) (getenv('APP_DIR') ?: __DIR__ . '/../../app'), '/');
$pass = 0; $fail = 0; $skip = 0;
function ok(string $name, bool $cond): void { global $pass, $fail; if ($cond) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name\n"; } }
function skip(string $name, string $why): void { global $skip; $skip++; echo "SKIP  $name ($why)\n"; }
function throws(callable $f, string $class = 'RuntimeException'): bool { try { $f(); return false; } catch (Throwable $x) { return $x instanceof $class; } }
register_shutdown_function(function () { global $pass, $fail, $skip; echo "\n$pass passed, $fail failed, $skip skipped\n"; });

if (!extension_loaded('pdo_sqlite')) { skip('all', 'pdo_sqlite not loaded'); exit(0); }
require_once $app . '/lib/CreditTerms.php';

// ---- before the migration: nothing is on terms, and staff are told why ----
$bare = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
ok('not ready before the migration', !CreditTerms::ready($bare));
ok('no order is on terms before the migration', !CreditTerms::onTerms($bare, ['created_by' => 5, 'bill_email' => 'a@x.test', 'due_terms' => 'net30', 'status' => 'draft']));
ok('Net 30 refused before the migration, naming it', str_contains(CreditTerms::whyNot($bare, null, 'a@x.test', 'net30'), 'client_credit_terms'));

// ---- the table, as the migration makes it (SQLite types) ----
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE client_credit_terms (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, email TEXT NOT NULL DEFAULT '', terms TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'asked', credit_note TEXT NOT NULL DEFAULT '', asked_by INTEGER, asked_at TEXT NOT NULL, decided_by INTEGER, decided_at TEXT,
    decided_note TEXT NOT NULL DEFAULT '', ended_by INTEGER, ended_at TEXT, ended_note TEXT NOT NULL DEFAULT '')");
$pdo->exec("CREATE TABLE order_ledger (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, kind TEXT, amount_cents INTEGER, request_key TEXT)");
$pdo->exec("CREATE TABLE order_events (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, line_id INTEGER, event TEXT, note TEXT, actor_user_id INTEGER, created_at TEXT)");
$seed = $pdo->prepare("INSERT INTO client_credit_terms (user_id, email, terms, status, asked_at, decided_at, ended_at) VALUES (?, ?, ?, ?, '2026-10-30 09:00:00', ?, ?)");
$seed->execute([null, 'a@x.test', 'net30', 'granted', '2026-11-01 10:00:00', null]);
$seed->execute([null, 'b@x.test', 'net15', 'granted', '2026-11-01 10:00:00', null]);
$seed->execute([null, 'c@x.test', 'net30', 'ended', '2026-11-01 10:00:00', '2026-11-10 10:00:00']);
$seed->execute([null, 'd@x.test', 'net30', 'granted', '2026-11-20 10:00:00', null]);
$seed->execute([null, 'e@x.test', 'net30', 'declined', '2026-11-01 10:00:00', null]);
$seed->execute([77, 'old@x.test', 'net30', 'granted', '2026-11-01 10:00:00', null]);
ok('ready after the migration', CreditTerms::ready($pdo));

ok('Net 30 covers Net 15', CreditTerms::covers('net30', 'net15'));
ok('Net 15 does not cover Net 30', !CreditTerms::covers('net15', 'net30'));
ok('nothing covers Due on receipt (it isn\'t terms)', !CreditTerms::covers('net30', 'receipt'));

$o = fn (array $x) => $x + ['created_by' => 5, 'user_id' => null, 'bill_email' => 'a@x.test', 'due_terms' => 'net30', 'status' => 'draft', 'sent_at' => null];
ok('granted Net 30: a draft on Net 30 is on terms', CreditTerms::onTerms($pdo, $o([])));
ok('email matched without case', CreditTerms::onTerms($pdo, $o(['bill_email' => 'A@X.test'])));
ok('Due on receipt is never on terms', !CreditTerms::onTerms($pdo, $o(['due_terms' => 'receipt'])));
ok('a shop order (no created_by) is never on terms', !CreditTerms::onTerms($pdo, $o(['created_by' => 0])));
ok('granted Net 15 only: Net 30 is not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'b@x.test'])));
ok('granted Net 15: Net 15 is on terms', CreditTerms::onTerms($pdo, $o(['bill_email' => 'b@x.test', 'due_terms' => 'net15'])));
ok('ended terms: an order sent while live keeps them', CreditTerms::onTerms($pdo, $o(['bill_email' => 'c@x.test', 'status' => 'pending', 'sent_at' => '2026-11-05 09:00:00'])));
ok('ended terms: an order sent after the end is not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'c@x.test', 'status' => 'pending', 'sent_at' => '2026-11-12 09:00:00'])));
ok('ended terms: a new draft is not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'c@x.test'])));
ok('granted after the order was sent: not on terms (send it again)', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'd@x.test', 'status' => 'sent', 'sent_at' => '2026-11-15 09:00:00'])));
ok('declined: not on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'e@x.test'])));
ok('a member\'s grant found by account', CreditTerms::onTerms($pdo, $o(['user_id' => 77, 'bill_email' => 'new@x.test'])));

ok('whyNot: granted client may have Net 30', CreditTerms::whyNot($pdo, null, 'a@x.test', 'net30') === '');
ok('whyNot: Net 15 client told "Net 15 only"', str_contains(CreditTerms::whyNot($pdo, null, 'b@x.test', 'net30'), 'Net 15 only'));
ok('whyNot: no grant says only Jason grants', str_contains(CreditTerms::whyNot($pdo, null, 'z@x.test', 'net30'), 'only Jason grants'));
ok('whyNot: Due on receipt is always allowed', CreditTerms::whyNot($pdo, null, 'z@x.test', 'receipt') === '');

ok('ask without what the credit process found is refused', throws(fn () => CreditTerms::ask($pdo, null, 'z@x.test', 'net30', '  ', 9)));
ok('ask for a made-up term is refused', throws(fn () => CreditTerms::ask($pdo, null, 'z@x.test', 'net60', 'refs checked', 9)));
$rid = 0; try { $rid = CreditTerms::ask($pdo, null, 'z@x.test', 'net30', 'Two trade references, 3 years in business', 9); } catch (Throwable $x) { echo '      ' . $x->getMessage() . "\n"; }
ok('ask recorded, waiting for Jason', $rid > 0 && (CreditTerms::get($pdo, $rid)['status'] ?? '') === 'asked' && count(CreditTerms::waiting($pdo)) === 1);
ok('asking doesn\'t put anything on terms', !CreditTerms::onTerms($pdo, $o(['bill_email' => 'z@x.test'])));
ok('a second ask for the same client is refused', throws(fn () => CreditTerms::ask($pdo, null, 'z@x.test', 'net15', 'again', 9)));
ok('an ask for terms the client already has is refused', throws(fn () => CreditTerms::ask($pdo, null, 'a@x.test', 'net15', 'refs', 9)));
CreditTerms::withdraw($pdo, $rid, 9);
ok('withdrawn: nothing waits', CreditTerms::waiting($pdo) === [] && CreditTerms::get($pdo, $rid)['status'] === 'withdrawn');

// ---- the payment holds (ClientOrders, patched) ----
$co = false;
try { require_once $app . '/lib/ClientOrders.php'; $co = class_exists('ClientOrders') && method_exists('CreditTerms', 'onTerms'); } catch (Throwable $x) { skip('holds', 'ClientOrders.php didn\'t load here: ' . $x->getMessage()); }
if ($co) {
    $ord = fn (array $x) => $x + ['id' => 501, 'status' => 'pending', 'created_by' => 5, 'user_id' => null, 'bill_email' => 'a@x.test', 'due_terms' => 'net30',
        'sent_at' => '2026-11-05 09:00:00', 'total_cents' => 50000, 'deposit_cents' => 0, 'deposit_pct' => 0, 'pay_mode' => 'full', 'qb_env' => 'production', 'order_no' => 501, 'stage' => ''];
    ok('on terms: delivery isn\'t held for the balance', ClientOrders::shipHold($pdo, $ord([])) === '');
    ok('on terms: the vendor isn\'t held for payment', ClientOrders::vendorHold($pdo, $ord([])) === '');
    ok('no grant, Net 30 typed: delivery still held (0177 §4 unchanged)', ClientOrders::shipHold($pdo, $ord(['bill_email' => 'z@x.test'])) !== '');
    ok('no grant, Net 30 typed: the vendor still held', ClientOrders::vendorHold($pdo, $ord(['bill_email' => 'z@x.test'])) !== '');
    ok('Due on receipt: delivery held as before', ClientOrders::shipHold($pdo, $ord(['due_terms' => 'receipt'])) !== '');
    ok('on terms with a deposit asked: the vendor waits for the deposit', ClientOrders::vendorHold($pdo, $ord(['pay_mode' => 'deposit', 'deposit_cents' => 20000])) !== '');
    ok('on terms with a deposit asked: delivery doesn\'t wait for the rest', ClientOrders::shipHold($pdo, $ord(['pay_mode' => 'deposit', 'deposit_cents' => 20000])) === '');
}

// ---- last: only Jason grants (Access.php may need the site; a refusal is what's tested) ----
$rid2 = CreditTerms::ask($pdo, null, 'y@x.test', 'net15', 'Bank reference checked', 9);
ok('grant without a Founder staff login is refused', throws(fn () => CreditTerms::grant($pdo, $rid2, 'ok', 9)));
ok('decline without a Founder staff login is refused', throws(fn () => CreditTerms::decline($pdo, $rid2, 'no', 9)));
ok('still waiting after the refusals', CreditTerms::get($pdo, $rid2)['status'] === 'asked' && !CreditTerms::onTerms($pdo, $o(['bill_email' => 'y@x.test', 'due_terms' => 'net15'])));
exit($fail ? 1 : 0);
