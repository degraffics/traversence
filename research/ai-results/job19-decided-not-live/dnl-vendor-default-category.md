**Not paused, nothing waiting for Jason:** In the attached `handoffs/decided-not-live.md` and `decisions/0178`, this item ("Later": *A vendor's default category from QuickBooks, once confirmed in the sandbox*) is not marked paused or waiting for Jason. These nearby items are, and this draft does not touch them:
- M2b (paused, 2026-11-24).
- Order #12's books (open with the Founder until after the holiday).
- Paying vendors (0178 note (c): "stays a separate decision").

**The item's own gate is not met:** "Once confirmed in the sandbox" has no recorded confirmation in the attached files. So the draft ships **switched off**. `VENDOR_CATEGORY_FIELD` is `''` until the lane session confirms in the sandbox which Vendor field holds the default category. Nothing here asserts that field's name.

The Work-queue task for this item is in the live database and isn't attached. Its notes may narrow or change this.

## 1. Plan

1. **Read the vendor's default category, read only.** Add `QuickBooksPay::vendorCategory()`, which returns the account as `[id, label]` or `[]`. It never blocks a bill and falls back on any failure. It rests on 0178 note (c) ("a vendor bill … created in QuickBooks as a Bill against the vendor").
2. **Decide where the bill posts.** Add `QuickBooksPay::billAccount()`, a pure choice: the vendor's category when it has one, otherwise the setting "Vendor bills post to" (`VendorBills::account()`, which stays the default and stays out of .env, per the `createBill` docblock).
3. **Use it in `createBill()`.** It now posts to the vendor's category when present. It also returns `'from' => 'vendor'|'setting'` so the caller can say why in History. **Assumption to confirm:** the vendor's category wins over the setting. No attached decision states that precedence.
4. **Gate it.** Add `VENDOR_CATEGORY_FIELD = ''`, which keeps the feature off until the sandbox confirms the field. This follows the handoff line "once confirmed in the sandbox".
5. **Add a test** under `tools/quickbooks-tests/` covering the choice, the mock read, the gate, and the wiring in `createBill`.
6. **Add FAQ entries**, marked "coming". `faq.md` is not attached ("file not shown"), so these are the entry texts only, not a diff.

Not changed:
- `VendorBills.php` (file not shown), so its caller is untouched. The extra `'from'` key is ignored until a lane session shows it.
- The mock branch of `createBill` (it returns before the vendor lookup), so existing mock bill tests keep their account label.

## 2. Diffs

Hunk line numbers are approximate because the attached file is unnumbered. The context lines are copied exactly. Apply with `git apply --recount` or patch's fuzz, then re-check by eye.

```diff
--- a/app/lib/QuickBooksPay.php
+++ b/app/lib/QuickBooksPay.php
@@ -24,6 +24,10 @@
                                'sandbox' => 'https://developer.api.intuit.com/.well-known/openid_sandbox_configuration'];
     private const MINOR = '75';
     public const ITEM_NAME = 'GO Code';
+    // The Vendor field that holds its default category (default expense account) in QuickBooks' API. '' = not confirmed
+    // in the sandbox yet, so bills post to the setting "Vendor bills post to" as before (handoffs/decided-not-live.md,
+    // "Later": "once confirmed in the sandbox"). Set it only after the sandbox shows the field, its shape and its minorversion.
+    public const VENDOR_CATEGORY_FIELD = '';
 
     private static ?string $forced = null;
     private static ?string $modeCache = null;
@@ -520,8 +524,10 @@
         $vid = (string) ($f['Vendor'][0]['Id'] ?? '');
         if ($vid === '') $vid = (string) (self::call($pdo, 'POST', 'vendor', ['DisplayName' => $name], [], 'vendor-' . substr(hash('sha256', $name), 0, 20))['Vendor']['Id'] ?? '');
         if ($vid === '') throw new RuntimeException('QuickBooks didn\'t add the vendor ' . $name . '.');
+        $acct = self::billAccount(self::vendorCategory($pdo, $vid), $acct);   // the vendor's default category in QuickBooks, else the setting
+        $from = (string) ($acct[2] ?? 'setting');
         $an = (string) ($acct[1] ?? ''); $acct = (string) ($acct[0] ?? '');
         if ($acct === '') throw new RuntimeException('Choose where vendor bills post (System Configuration → Settings, Vendor bills post to): QuickBooks has no Cost of Goods Sold account yet.');
         $amt = ((int) $b['total_cents']) / 100;
@@ -537,7 +543,7 @@
         $j = self::call($pdo, 'POST', 'bill', $body, [], $key);
         $id = (string) ($j['Bill']['Id'] ?? '');
         if ($id === '') throw new RuntimeException('QuickBooks didn\'t say which bill it made.');
-        return ['id' => $id, 'account' => $an !== '' ? $an : 'account ' . $acct];
+        return ['id' => $id, 'account' => $an !== '' ? $an : 'account ' . $acct, 'from' => $from];   // from: 'vendor' (its default category) or 'setting'
     }
 
     /**
@@ -551,6 +557,42 @@
         usort($out, fn ($p, $q) => [$p[1] === '' ? 'zzz' : $p[1], $p[2]] <=> [$q[1] === '' ? 'zzz' : $q[1], $q[2]]);
         return $out;
     }
 
+    /**
+     * A vendor's default category in QuickBooks (its default expense account), read only: [id, label], or [] when it has
+     * none, the account isn't active, the field isn't confirmed yet (VENDOR_CATEGORY_FIELD ''), or QuickBooks can't be
+     * read. Never blocks a bill: createBill() then posts to the setting "Vendor bills post to". Writes nothing in
+     * QuickBooks. The local test (mock): vendor 101 has one (5010 Printing and production), any other none.
+     */
+    public static function vendorCategory(PDO $pdo, string $vid): array
+    {
+        if ($vid === '') return [];
+        if (self::env() === 'mock') return $vid === '101' ? ['81', '5010 Printing and production'] : [];
+        if (self::VENDOR_CATEGORY_FIELD === '') return [];   // not confirmed in the sandbox: off
+        try {
+            $v = self::fetch($pdo, 'Vendor', $vid);
+            $ref = $v[self::VENDOR_CATEGORY_FIELD] ?? null;
+            $id = is_array($ref) ? trim((string) ($ref['value'] ?? '')) : '';
+            if ($id === '') return [];
+            $a = self::fetch($pdo, 'Account', $id);
+            if (!$a || ($a['Active'] ?? true) === false) return [];
+            $label = trim((string) ($a['AcctNum'] ?? '') . ' ' . (string) ($a['Name'] ?? ($ref['name'] ?? '')));
+            return [$id, $label];
+        } catch (Throwable $e) { error_log('qb vendor category: ' . $e->getMessage()); return []; }
+    }
+
+    /**
+     * Where a vendor bill posts: the vendor's default category when it has one, else the setting (VendorBills::account()).
+     * $vendor: vendorCategory()'s [id, label] or []; $setting: [id, label]. Returns [id, label, 'vendor'|'setting'].
+     */
+    public static function billAccount(array $vendor, array $setting): array
+    {
+        $vid = trim((string) ($vendor[0] ?? ''));
+        if ($vid !== '') return [$vid, (string) ($vendor[1] ?? ''), 'vendor'];
+        return [(string) ($setting[0] ?? ''), (string) ($setting[1] ?? ''), 'setting'];
+    }
+
     /** QuickBooks' item list, read only (decisions/0128): Id, Name, Description, UnitPrice, Taxable, Active, Type. */
     public static function items(PDO $pdo): array
     {
```

**Path note:** the file's own header says `app/lib/QuickBooksPay.php`, but it was attached from a path ending `sync-app/lib/QuickBooksPay.php`. The lane session confirms which repo path the diff targets.

No migration: nothing is stored. The category is read from QuickBooks each time a bill is created.

## 3. Tests

`tools/quickbooks-tests/vendor-category.php`

```php
<?php
// tools/quickbooks-tests/vendor-category.php — a vendor's default category from QuickBooks (handoffs/decided-not-live.md,
// "Later"). Local test only (QB_ENV=mock): no network, no database, no money. Prints PASS/FAIL per check; exits 1 on a FAIL.
declare(strict_types=1);

putenv('QB_ENV=mock');
require_once __DIR__ . '/../../app/lib/QuickBooksPay.php';

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
    QuickBooksPay::billAccount(['', 'Something'], $setting) === ['80', '5000 Cost of Goods Sold', 'setting']);
check('a category with no label keeps its id, label blank (createBill says "account <id>")',
    QuickBooksPay::billAccount(['81'], $setting) === ['81', '', 'vendor']);
check('no category and no setting: an empty id (createBill then asks for the setting)',
    QuickBooksPay::billAccount([], [])[0] === '');

// vendorCategory() in mock: reads only, no PDO calls are made in mock
$pdo = new PDO('sqlite::memory:');
check('mock: vendor 101 has a default category',
    QuickBooksPay::vendorCategory($pdo, '101') === ['81', '5010 Printing and production']);
check('mock: vendor 999 has none', QuickBooksPay::vendorCategory($pdo, '999') === []);
check('no vendor id: none', QuickBooksPay::vendorCategory($pdo, '') === []);

// The mock category is one of the mock Cost of Goods Sold accounts (so the two lists agree)
$ids = array_column(QuickBooksPay::cogsAccounts($pdo), 0);
check('mock category 81 is in cogsAccounts()', in_array('81', $ids, true));

// The gate: off until the sandbox confirms the field. When the lane session sets VENDOR_CATEGORY_FIELD after the
// sandbox check, change this check to name the confirmed field.
check('off until confirmed in the sandbox (VENDOR_CATEGORY_FIELD is empty)', QuickBooksPay::VENDOR_CATEGORY_FIELD === '');

// The wiring: createBill() asks for the vendor's category and says where the account came from; nothing from .env
$src = (string) file_get_contents(__DIR__ . '/../../app/lib/QuickBooksPay.php');
preg_match('/function createBill\(.*?\n    \}\n/s', $src, $m);
$body = $m[0] ?? '';
check('createBill() found', $body !== '');
check('createBill() uses billAccount(vendorCategory(...))', str_contains($body, 'self::billAccount(self::vendorCategory($pdo, $vid), $acct)'));
check('createBill() returns where the account came from', str_contains($body, "'from' => \$from"));
check('the category is never read from .env', !preg_match('/getenv\([\'"]QB_[A-Z_]*CATEG/', $src));

echo $fails === 0 ? "All passed.\n" : $fails . " failed.\n";
exit($fails === 0 ? 0 : 1);
```

The `PDO('sqlite::memory:')` is only a handle. Mock mode returns before any query. If the harness has no pdo_sqlite, swap it for the repo's usual test handle.

## 4. faq.md entries

`faq.md` is not shown, so these are new entries. Placement and heading style should match the file.

**When we record a vendor's bill on your order, which account does it go to in our books?** *(coming)*
Each vendor bill we record on an order is also created in QuickBooks as a bill against that vendor. Soon, if the vendor has a default category set in QuickBooks, the bill will go to that category automatically. If they don't, it goes to the account our team chose under "Vendor bills post to". This only changes how our own books are kept. It never changes what you pay, and it doesn't pay the vendor.

**Does this change anything on my invoice?** *(coming)*
No. Vendor bills are our costs, kept separately from your invoice. Your order's total, tax and payments are unaffected.

## 5. Risks, and what the lane session must verify

1. **The sandbox confirmation is the item's precondition.** In the QuickBooks sandbox, check whether the Vendor entity exposes a default expense category through the Accounting API at all, under which field name, and in what shape. The draft assumes a `{value, name}` reference. Also check whether it needs a `minorversion` above `MINOR = '75'`.
   - If the API doesn't expose the field, the item can't be built this way. Return it to the session manager and don't set the constant.
   - If it does, set `VENDOR_CATEGORY_FIELD` and update the gate check in the test.
2. **The precedence is assumed.** "The vendor's category wins over the setting" is my reading; no attached decision says so. Confirm it with the session manager, or the Work-queue task notes (not attached), before sending. The alternative is that the setting wins and the vendor's category is only used when the setting is unset, which is a one-line swap in `billAccount()`.
3. **Account type.** A vendor's default category may be an Expense account rather than Cost of Goods Sold. Confirm that's acceptable for order bills; otherwise filter on `AccountType` in `vendorCategory()`.
4. **Extra calls.** Each bill now makes two extra GETs (Vendor, Account) when the feature is on. Both go through `call()` → `http()`, so `StagingGate::hold` and the log apply. Confirm the log shows them, with no company id in the path.
5. **The caller (`VendorBills.php`, not shown).**
   - Confirm it tolerates the extra `'from'` key.
   - Decide whether History should say "posted to <label> (the vendor's default category in QuickBooks)". That would be a separate patch once the file is attached.
6. **Mock bills are unchanged.** `createBill()`'s mock branch returns before the vendor lookup, so a mock bill still shows the setting's account. Run the existing order and vendor-bill tests (tools/order-tests or wherever they live, not shown) to confirm nothing moved.
7. **Money rules hold.**
   - `guardReal()` still runs first in `createBill()`.
   - The new code only reads from QuickBooks.
   - Nothing pays a vendor (still a separate decision under 0178 (c)).
8. **Mechanics.**
   - Confirm the repo path (`app/lib` vs `sync-app/lib`).
   - Apply the diff with recount, run `php -l` under PHP 8.0, then run `tools/quickbooks-tests/vendor-category.php`.
   - Zip-check the release.

None of this has been run. It is a draft.