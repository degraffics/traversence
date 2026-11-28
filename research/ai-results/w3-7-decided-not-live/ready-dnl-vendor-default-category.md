=== FILE: release.json ===
{
  "name": "dnl-vendor-default-category",
  "gate": "Ships switched OFF. The item's own condition ('once confirmed in the sandbox', handoffs/decided-not-live.md, Later) has no recorded confirmation in the attached files. QuickBooksPay::VENDOR_CATEGORY_FIELD stays '' until the lane session confirms in the QuickBooks sandbox which Vendor field holds the default category. While it is '', every vendor bill posts to the setting 'Vendor bills post to' exactly as today. The Tier and gate number for this release are not stated in the attached files; the session manager assigns them.",
  "what": "Reads a vendor's default category (default expense account) from QuickBooks, read only, when a vendor bill on an order is created in QuickBooks (decisions/0178 note (c)). The bill posts to that category when the vendor has a usable one (active, and of type Cost of Goods Sold, Expense or Other Expense). Otherwise it posts to the setting 'Vendor bills post to' (VendorBills::account()). createBill() also returns 'from' => 'vendor'|'setting'. Nothing is stored, no migration is needed, nothing is written to QuickBooks beyond the Bill it already made, and no money moves.",
  "files": [
    "files/app/lib/QuickBooksPay.php.region-1.php",
    "tests/vendor-category.php",
    "faq/faq-entries.md",
    "release.json"
  ],
  "decisions": [
    "SAID FIRST: checked for anything paused or waiting for Jason. This item is neither, so it is drafted. The following nearby items are skipped and not touched: M2b, how an order goes (paused by Jason 2026-11-24, 'Pause for now. add to work queue.'); Order #12's books (open with the Founder, 'I will address this after holiday', 2026-11-27); paying vendors (decisions/0178 note (c): 'Paying vendors stays a separate decision'). M7 credit terms is also untouched (not this item).",
    "handoffs/decided-not-live.md, Later: 'A vendor's default category from QuickBooks, once confirmed in the sandbox' is the item. Its sandbox condition is honoured by shipping switched off (VENDOR_CATEGORY_FIELD = '').",
    "decisions/0178 note (c) (Jason, 2026-11-23, 'Yes, create it'): a vendor bill recorded on an order is also created in QuickBooks as a Bill against the vendor. This release only changes which account that Bill's single line posts to.",
    "createBill() docblock (live): the account comes from the setting 'Vendor bills post to', 'never .env'. Kept: the setting stays the fallback, and nothing new is read from .env.",
    "decisions/0178 'Not changed': QuickBooksPay::guardReal() still runs first in createBill(); the new code only reads.",
    "ASSUMED, NOT DECIDED: the vendor's category wins over the setting. No attached decision states the precedence. The reverse is a one-line swap in billAccount().",
    "Open Work-queue parent tasks are in the live database and not attached. Their notes may narrow or change this item."
  ],
  "tests": [
    "tests/vendor-category.php (to live at tools/quickbooks-tests/vendor-category.php): QB_ENV=mock, with no network, no database writes and no money. It checks billAccount()'s choice, categoryUsable()'s account-type and active filter, the mock vendorCategory(), the off gate (both constants ''), that the field is never set to APAccountRef, and createBill()'s wiring and 'from' key by reading the source. Prints PASS/FAIL/SKIP and exits 1 on any FAIL."
  ],
  "risks": [
    "NOTHING HERE HAS BEEN RUN, LINTED, TESTED OR ZIP-CHECKED. It is a draft for the lane session.",
    "Review of the job-19 draft, changes made: (1) The live QuickBooksPay.php is well over about 60 KB, so this release ships a region rather than a diff or a whole file. The new constants and methods sit inside the same region, right after createBill(), because PHP allows class constants anywhere in the class body. That keeps it to one anchor. (2) The draft accepted any active account as the vendor's category. Here only Cost of Goods Sold, Expense and Other Expense accounts are accepted (categoryUsable(), pure and tested). A Bank, Accounts Payable or Income account in an AccountBasedExpenseLineDetail would mispost or be refused, so in those cases the bill falls back to the setting. (3) The Vendor read uses call() with an optional VENDOR_CATEGORY_MINOR override, rather than fetch(), so the field can need a newer minorversion without changing the global MINOR = '75'. (4) The category's account id must be digits. (5) The mock return of createBill() also says 'from' => 'setting', so the return shape is the same in every mode. The mock account label is unchanged. (6) The test SKIPs the PDO-handle checks when pdo_sqlite is missing, instead of crashing.",
    "The QuickBooks Accounting API may not expose a vendor's default category on the Vendor entity at all. Vendor.APAccountRef is the vendor's payables account, NOT its expense category, and must never be used (the test guards this). If the sandbox shows no such field, the item can't be built this way: leave the constant '' and return the item to the session manager.",
    "The precedence (vendor's category before the setting) is an assumption. See decisions.",
    "Expense and Other Expense accounts are accepted alongside Cost of Goods Sold. Whether an order's vendor bill may post to a plain Expense account is not decided in the attached files. Narrow the list in categoryUsable() if not.",
    "When switched on, each bill makes two extra GETs (vendor, account). Both go through call() → http(), so StagingGate::hold and the QuickBooks log apply. Any failure falls back to the setting and never blocks the bill.",
    "Idempotency: the Bill keeps its requestid 'vbill-<id>'. If a vendor's category changes between a first try and a retry, QuickBooks returns the original Bill with its original account. The account label returned then reflects the new choice, not what QuickBooks actually used.",
    "The createBill docblock still mentions 'QB_COGS_ACCOUNT_ID in .env', which contradicts its own 'never .env' later in the same docblock. The docblock is left as live (outside the region) and only noted here.",
    "In mock mode, vendorCategory() answers (vendor 101) even though the gate is off. This is test-only, and createBill()'s mock branch returns before it is called, so no mock bill changes."
  ],
  "verify": [
    "Repo path: the live file was attached from sync-app/lib/QuickBooksPay.php, but its header says app/lib/QuickBooksPay.php. Confirm which path the region targets.",
    "Apply region-1 by hand. Replace from the exact line '    public static function createBill(PDO $pdo, array $b, array $o, array $acct): array' through the first '    }' after it (the line that closes createBill), with the region's content minus its ANCHOR comment lines. createBill's docblock above it stays as is. Then run php -l under PHP 8.0.",
    "Sandbox, before setting VENDOR_CATEGORY_FIELD: in the QuickBooks sandbox, give a test vendor a default category. GET vendor/<id> at minorversion 75 and higher, and find the field, its shape ({value,name} expected) and the minorversion needed. Only then set VENDOR_CATEGORY_FIELD (and VENDOR_CATEGORY_MINOR if needed), and update the gate check in the test. Record the confirmation where the item says.",
    "Run tools/quickbooks-tests/vendor-category.php (copy of tests/vendor-category.php). Expect all PASS, or SKIP only for the PDO-handle checks.",
    "file not shown: app/lib/Orders.php (required by QuickBooksPay.php; the test needs it to load under QB_ENV=mock without a database).",
    "file not shown: app/lib/VendorBills.php (createBill()'s caller). Confirm it ignores or tolerates the extra 'from' key. Saying 'the vendor's default category in QuickBooks' in History would be a separate patch once it is attached.",
    "file not shown: faq.md. Place faq/faq-entries.md's entries in its style, marked coming until this is switched on.",
    "file not shown: the existing order and vendor-bill tests (tools/order-tests or wherever they live). Run them to confirm mock bills still carry the setting's account label.",
    "file not shown: app/lib/StagingGate.php. Confirm hold() still covers the two extra GETs on staging.",
    "Check that the QuickBooks log (app/storage/logs/quickbooks.log) shows the vendor and account GETs with the company id masked.",
    "Zip-check the release folder."
  ],
  "regions": true
}
=== END FILE ===

=== FILE: files/app/lib/QuickBooksPay.php.region-1.php ===
    // ANCHOR: replaces     public static function createBill(PDO $pdo, array $b, array $o, array $acct): array through     }
    // (that last line is the first '    }' after the first line: the one that closes createBill. createBill's docblock above
    // it stays as live. Paste from the next line down; these two comment lines are not part of the code.)
    public static function createBill(PDO $pdo, array $b, array $o, array $acct): array
    {
        self::guardReal($pdo, $o);   // never real money for a test
        require_once __DIR__ . '/StagingGate.php';
        if (($why = StagingGate::hold(self::api() . '/v3/company/bill', 'a vendor bill for order #' . (int) $o['id'], 'order:' . (int) $o['id'])) !== null) throw new RuntimeException('Held on staging: ' . $why);
        if (self::env() === 'mock') return ['id' => 'MBL-' . (int) $b['id'], 'account' => (string) ($acct[1] ?? ''), 'from' => 'setting'];
        $name = mb_substr(trim(str_replace([':', '"'], ['-', ''], (string) $b['vendor'])), 0, 100);
        if ($name === '') throw new RuntimeException('The bill has no vendor named.');
        $f = self::query($pdo, "select Id from Vendor where DisplayName = '" . self::q($name) . "'");
        $vid = (string) ($f['Vendor'][0]['Id'] ?? '');
        if ($vid === '') $vid = (string) (self::call($pdo, 'POST', 'vendor', ['DisplayName' => $name], [], 'vendor-' . substr(hash('sha256', $name), 0, 20))['Vendor']['Id'] ?? '');
        if ($vid === '') throw new RuntimeException('QuickBooks didn\'t add the vendor ' . $name . '.');
        // Where it posts: the vendor's default category in QuickBooks when it has a usable one (read only; off until the
        // field is confirmed in the sandbox, VENDOR_CATEGORY_FIELD), else the setting "Vendor bills post to".
        $acct = self::billAccount(self::vendorCategory($pdo, $vid), $acct);
        $from = (string) ($acct[2] ?? 'setting');
        $an = (string) ($acct[1] ?? ''); $acct = (string) ($acct[0] ?? '');
        if ($acct === '') throw new RuntimeException('Choose where vendor bills post (System Configuration → Settings, Vendor bills post to): QuickBooks has no Cost of Goods Sold account yet.');
        $amt = ((int) $b['total_cents']) / 100;
        $key = 'vbill-' . (int) $b['id'];
        $body = ['VendorRef' => ['value' => $vid], 'TotalAmt' => $amt,
            'Line' => [['Amount' => $amt, 'DetailType' => 'AccountBasedExpenseLineDetail', 'Description' => mb_substr('Order #' . Orders::no($o) . ($b['po'] !== '' ? ', work order ' . $b['po'] : ''), 0, 4000),
                'AccountBasedExpenseLineDetail' => ['AccountRef' => ['value' => $acct]]]],
            'PrivateNote' => mb_substr('Traversence order #' . Orders::no($o) . ', the vendor\'s invoice ' . $b['bill_no'] . ' ' . self::mark($key), 0, 4000)];
        if ((string) $b['bill_no'] !== '') $body['DocNumber'] = mb_substr((string) $b['bill_no'], 0, 21);
        if (!empty($b['bill_date'])) $body['TxnDate'] = (string) $b['bill_date'];
        if (!empty($b['due_date'])) $body['DueDate'] = (string) $b['due_date'];
        $j = self::call($pdo, 'POST', 'bill', $body, [], $key);
        $id = (string) ($j['Bill']['Id'] ?? '');
        if ($id === '') throw new RuntimeException('QuickBooks didn\'t say which bill it made.');
        return ['id' => $id, 'account' => $an !== '' ? $an : 'account ' . $acct, 'from' => $from];   // from: 'vendor' (its default category) or 'setting'
    }

    // The Vendor field that holds its default category (default expense account) in QuickBooks' API. '' = not confirmed in
    // the sandbox yet, so bills post to the setting "Vendor bills post to" as before (handoffs/decided-not-live.md, Later:
    // "once confirmed in the sandbox"). Set it only after the sandbox shows the field and its shape. Never APAccountRef:
    // that is the vendor's payables account, not its category.
    public const VENDOR_CATEGORY_FIELD = '';
    // The minorversion that field needs, if newer than MINOR; '' = MINOR.
    public const VENDOR_CATEGORY_MINOR = '';

    /**
     * A vendor's default category in QuickBooks (its default expense account), read only: [id, label], or [] when it has
     * none, the account isn't usable (categoryUsable()), the field isn't confirmed yet (VENDOR_CATEGORY_FIELD ''), or
     * QuickBooks can't be read. Never blocks a bill: createBill() then posts to the setting "Vendor bills post to". Writes
     * nothing in QuickBooks. The local test (mock): vendor 101 has one (81, 5010 Printing and production), any other none.
     */
    public static function vendorCategory(PDO $pdo, string $vid): array
    {
        $vid = trim($vid);
        if ($vid === '') return [];
        if (self::env() === 'mock') return $vid === '101' ? ['81', '5010 Printing and production'] : [];
        if (self::VENDOR_CATEGORY_FIELD === '') return [];   // not confirmed in the sandbox: off
        try {
            $q = self::VENDOR_CATEGORY_MINOR !== '' ? ['minorversion' => self::VENDOR_CATEGORY_MINOR] : [];
            $v = (array) (self::call($pdo, 'GET', 'vendor/' . rawurlencode($vid), null, $q)['Vendor'] ?? []);
            $ref = $v[self::VENDOR_CATEGORY_FIELD] ?? null;
            $id = is_array($ref) ? trim((string) ($ref['value'] ?? '')) : '';
            if ($id === '' || !ctype_digit($id)) return [];
            $a = self::fetch($pdo, 'Account', $id);
            if (!self::categoryUsable($a)) return [];
            $label = trim(trim((string) ($a['AcctNum'] ?? '')) . ' ' . trim((string) ($a['Name'] ?? ($ref['name'] ?? ''))));
            return [$id, $label];
        } catch (Throwable $e) { error_log('qb vendor category: ' . $e->getMessage()); return []; }
    }

    /**
     * Can a vendor bill's one line post to this QuickBooks account: active, and a cost or expense account (Cost of Goods
     * Sold, Expense, Other Expense)? Anything else (a bank, payables or income account, or no type given): no, and the
     * bill posts to the setting.
     */
    public static function categoryUsable(array $a): bool
    {
        if (!$a) return false;
        if (($a['Active'] ?? true) === false) return false;
        return in_array((string) ($a['AccountType'] ?? ''), ['Cost of Goods Sold', 'Expense', 'Other Expense'], true);
    }

    /**
     * Where a vendor bill posts: the vendor's default category when it has one, else the setting (VendorBills::account()).
     * $vendor: vendorCategory()'s [id, label] or []; $setting: [id, label]. Returns [id, label, 'vendor'|'setting'].
     */
    public static function billAccount(array $vendor, array $setting): array
    {
        $vid = trim((string) ($vendor[0] ?? ''));
        if ($vid !== '') return [$vid, (string) ($vendor[1] ?? ''), 'vendor'];
        return [trim((string) ($setting[0] ?? '')), (string) ($setting[1] ?? ''), 'setting'];
    }
=== END FILE ===

=== FILE: tests/vendor-category.php ===
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
=== END FILE ===

=== FILE: faq/faq-entries.md ===
<!-- New entries for faq.md (file not shown: place and style them to match it). Marked coming until the release is switched on after the sandbox check. -->

**When you record a vendor's bill for my order, which account does it go to in your books?** *(coming)*
Each vendor bill we record on an order is also created in our QuickBooks as a bill against that vendor. If the vendor has a default category set in QuickBooks, the bill will go to that category. If they don't, it goes to the account our team chose for vendor bills. This only changes how our own books are kept. It never changes what you pay, and it doesn't pay the vendor.

**Does this change anything on my invoice?** *(coming)*
No. Vendor bills are our costs, kept separately from your invoice. Your order's total, tax and payments are not affected.
=== END FILE ===