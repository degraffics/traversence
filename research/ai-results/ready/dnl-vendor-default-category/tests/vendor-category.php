<?php
// tools/quickbooks-tests/vendor-category.php — a vendor's default category from QuickBooks (handoffs/decided-not-live.md,
// Later). Local test only (QB_ENV=mock): no network, no database writes, no money. Prints PASS/FAIL/SKIP per check;
// exits 1 on any FAIL.
declare(strict_types=1);

putenv('QB_ENV=mock');
$lib = __DIR__ . '/../../app/lib/QuickBooksPay.php';
require_once $lib;

$fails = 0;
function check(string $what, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $what . "\n";
    if (!$ok) $fails++;
}

$setting = ['80', '5000 Cost of Goods Sold'];

// billAccount(): the choice, pure
check('the vendor\'s category wins when it has one',
    QuickBooksPay::billAccount(['81', '5010 Printing and production'], $setting) === ['81', '5010 Printing and production', 'vendor']);
check('no category: the setting',
    QuickBooksPay::billAccount([], $setting) === ['80', '5000 Cost of Goods Sold', 'setting']);
check('a blank category id counts as none',
    QuickBooksPay::billAccount(['  ', 'Something'], $setting) === ['80', '5000 Cost of Goods Sold', 'setting']);
check('a category with no label keeps its id, label blank (createBill then says "account <id>")',
    QuickBooksPay::billAccount(['81'], $setting) === ['81', '', 'vendor']);
check('no category and no setting: an empty id (createBill then asks for the setting)',
    QuickBooksPay::billAccount([], [])[0] === '');

// categoryUsable(): only active cost or expense accounts
check('a Cost of Goods Sold account is usable', QuickBooksPay::categoryUsable(['Id' => '81', 'AccountType' => 'Cost of Goods Sold', 'Active' => true]));
check('an Expense account is usable', QuickBooksPay::categoryUsable(['Id' => '90', 'AccountType' => 'Expense', 'Active' => true]));
check('an Other Expense account is usable', QuickBooksPay::categoryUsable(['Id' => '91', 'AccountType' => 'Other Expense']));
check('an inactive account is not', !QuickBooksPay::categoryUsable(['Id' => '81', 'AccountType' => 'Cost of Goods Sold', 'Active' => false]));
check('an Accounts Payable account is not', !QuickBooksPay::categoryUsable(['Id' => '33', 'AccountType' => 'Accounts Payable', 'Active' => true]));
check('a Bank account is not', !QuickBooksPay::categoryUsable(['Id' => '35', 'AccountType' => 'Bank', 'Active' => true]));
check('an Income account is not', !QuickBooksPay::categoryUsable(['Id' => '1', 'AccountType' => 'Income', 'Active' => true]));
check('no account type: not', !QuickBooksPay::categoryUsable(['Id' => '81', 'Active' => true]));
check('no account: not', !QuickBooksPay::categoryUsable([]));

// vendorCategory() in mock: reads only; the PDO is only a handle, no query is made in mock
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: vendorCategory()/cogsAccounts() mock checks (no pdo_sqlite for a handle; use the repo's usual test handle)\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    check('mock: vendor 101 has a default category',
        QuickBooksPay::vendorCategory($pdo, '101') === ['81', '5010 Printing and production']);
    check('mock: vendor 999 has none', QuickBooksPay::vendorCategory($pdo, '999') === []);
    check('no vendor id: none', QuickBooksPay::vendorCategory($pdo, '') === []);
    check('blank vendor id: none', QuickBooksPay::vendorCategory($pdo, '   ') === []);
    $ids = array_column(QuickBooksPay::cogsAccounts($pdo), 0);
    check('mock category 81 is one of the mock Cost of Goods Sold accounts', in_array('81', $ids, true));
}

// The gate: off until the sandbox confirms the field. When the lane session sets VENDOR_CATEGORY_FIELD after the sandbox
// check, change these two checks to name the confirmed field and minorversion.
check('off until confirmed in the sandbox (VENDOR_CATEGORY_FIELD is empty)', QuickBooksPay::VENDOR_CATEGORY_FIELD === '');
check('no minorversion override yet (VENDOR_CATEGORY_MINOR is empty)', QuickBooksPay::VENDOR_CATEGORY_MINOR === '');
check('never the payables account (APAccountRef is not a category)', QuickBooksPay::VENDOR_CATEGORY_FIELD !== 'APAccountRef');

// The wiring, read from the source
$src = (string) file_get_contents($lib);
preg_match('/function createBill\(.*?\n    \}\n/s', $src, $m);
$body = $m[0] ?? '';
check('createBill() found', $body !== '');
check('createBill() still calls guardReal() first', (bool) preg_match('/\{\s*self::guardReal\(\$pdo, \$o\);/', $body));
check('createBill() uses billAccount(vendorCategory(...))', str_contains($body, 'self::billAccount(self::vendorCategory($pdo, $vid), $acct)'));
check('createBill() returns where the account came from', str_contains($body, "'from' => \$from"));
check('createBill()\'s mock return says the setting', str_contains($body, "'from' => 'setting'"));
preg_match('/function vendorCategory\(.*?\n    \}\n/s', $src, $v);
$vbody = $v[0] ?? '';
check('vendorCategory() found', $vbody !== '');
check('vendorCategory() is off before any call to QuickBooks',
    ($g = strpos($vbody, "if (self::VENDOR_CATEGORY_FIELD === '') return [];")) !== false && ($c = strpos($vbody, 'self::call(')) !== false && $g < $c);
check('vendorCategory() makes no POST', !str_contains($vbody, "'POST'"));
check('the category is never read from .env', !preg_match('/getenv\([\'"]QB_[A-Z_]*CATEG/', $src));

echo $fails === 0 ? "All passed.\n" : $fails . " failed.\n";
exit($fails === 0 ? 0 : 1);
