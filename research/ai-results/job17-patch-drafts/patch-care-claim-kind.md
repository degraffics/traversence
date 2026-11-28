# Release draft: `care-claim-kind`

This is a draft only. Nothing here has been run, applied or tested; the lane session verifies all of it.

## 1. Plan

1. **`Claims::KIND_GROUPS`** gets `'Health & Medical' => 'care'`. Source: 0070 note 2026-11-27/28, "Health & Medical listings take their own claim kind, `care`".
2. **`Claims::KIND_ROLES['care']`** is Administrator or practice manager, Staff, Communications, Other. Source: the same note.
3. **`Claims::KIND_WORDS['care']`** gets:
   - the tip "Work at %s? Claim it to keep its hours and services current.";
   - the title "Claim this health care listing".

   Both come from the note. The `button` and `yours` values are **my drafts and are not in the note**: "Work here? Claim it" and "your organization". They need Design review (see §5).
4. **`Claims::kind()` docblock** now lists `care`. The code path needs no change: the map lookup covers it, and only `public` goes on to the nation check.
5. **Proof needs no code change.** The note sets proof "by this decision's agency rule (the code to an email at its own domain first)". `Claims::domains()` already puts the listing's own website domain first for every kind. `care` doesn't add an agency domain, because the note gives it none. The *ordering* of routes on the page lives in `js/claim.js`, which is not shown (see §5).
6. **Free only.** `Claims::activate()` refuses a paid plan for `care`, server-side, per the note: "Free until a decision on its own plan". I add this because `js/claim.js` is not shown, so I can't confirm the page hides paid plans for an unknown kind.
7. **`claim.php` step 3** says "Plans for health care listings are coming". Without this, a clinic would read "Plans for agencies and nonprofits are coming". Source: the same note.
8. **`listing/api/claim.php`** needs no change. It already returns `kind` from `Claims::kind()`.
9. **`listing/view.php`** is not shown. The note requires two changes there, and I can't draft either without the file (see §5):
   - The aside takes its words from `KIND_WORDS` instead of the hard-coded "Are you the owner?" (view.php:564).
   - Staff see "Open in the editor", never Claim (CLAUDE.md "Never a dead end").

## 2. Diffs

The attached files carry no line numbers. The `@@` start lines are estimates; for Claims.php I anchored on the note's `Claims.php:371`. Context lines are copied exactly, so `git apply` / `patch` should locate them by context. The lane should check the offsets it reports.

```diff
--- a/app/lib/Claims.php
+++ b/app/lib/Claims.php
@@ -368,23 +368,25 @@
     // ------------------------------------------------------------------ who might claim it (Jason, 2026-11-19; decisions/0070 note)
 
     /** The category groups (decisions/0073) whose listings are claimed by an agency or an organization, not an owner. */
-    public const KIND_GROUPS = ['Government & Public Services' => 'public', 'Non-Profit & Community' => 'community', 'Religious & Faith-Based' => 'community'];
+    public const KIND_GROUPS = ['Government & Public Services' => 'public', 'Non-Profit & Community' => 'community', 'Religious & Faith-Based' => 'community', 'Health & Medical' => 'care'];
     /** The roles offered on the claim form, by kind. */
     public const KIND_ROLES = [
         'business'  => ['Owner', 'Co-owner or partner', 'Manager', 'Employee', 'Other'],
         'public'    => ['Site or park manager', 'Agency staff', 'Communications or public information', 'Other'],
         'community' => ['Director, pastor or leader', 'Staff', 'Board member', 'Volunteer', 'Other'],
         'nation'    => ['Nation staff', 'Tribal enterprise staff', 'Other'],
+        'care'      => ['Administrator or practice manager', 'Staff', 'Communications', 'Other'],   // decisions/0070 note, 2026-11-27/28
     ];
     /** The words, by kind: the short button, its tip (%s: the name), the claim page's title, and "yours" in its sentences. */
     public const KIND_WORDS = [
         'business'  => ['button' => 'Claim it', 'tip' => 'Own or manage %s? Claim it to add photos, hours and more.', 'title' => 'Claim your business', 'yours' => 'your business'],
         'public'    => ['button' => 'Staff? Claim it', 'tip' => 'Work for the agency that runs %s? Claim it to keep its hours, photos and notices current.', 'title' => 'Claim this place for your agency', 'yours' => 'your agency'],
         'community' => ['button' => 'Run it? Claim it', 'tip' => 'Run %s? Claim it to add photos, hours and more.', 'title' => 'Claim your organization', 'yours' => 'your organization'],
         'nation'    => ['button' => 'Nation staff? Claim it', 'tip' => 'Work for the nation that runs %s? Ask to claim it: our team checks with the nation first.', 'title' => 'A place on a nation\'s land', 'yours' => 'the nation'],
+        'care'      => ['button' => 'Work here? Claim it', 'tip' => 'Work at %s? Claim it to keep its hours and services current.', 'title' => 'Claim this health care listing', 'yours' => 'your organization'],
     ];
 
-    /** business | public | community | nation (a public place on a nation's land: the nation's own tools decide, 0054, 0066). */
+    /** business | public | community | care | nation (a public place on a nation's land: the nation's own tools decide, 0054, 0066; care: Health & Medical, decisions/0070 note 2026-11-27/28). */
     public static function kind(PDO $pdo, int $e): string
     {
         static $memo = [];
@@ -688,9 +690,11 @@
         // Terms agreed (Jason, 2026-10-24: "no terms or confirmation upon clicking activate")
         if ($plan !== 'free' && !$agreed) throw new InvalidArgumentException('Review the trial and agree to the Terms first.');
         $e = (int) $c['entity_id'];
+        // health care stays on Free until a decision on its own plan (decisions/0070 note, 2026-11-27/28)
+        if ($plan !== 'free' && self::kind($pdo, $e) === 'care') throw new InvalidArgumentException('Health care listings are on Free for now: plans for them are coming.');
         $cur = Billing::current($pdo, 'listing', $e);
         if ($cur && $cur['status'] !== 'trial') throw new InvalidArgumentException('This listing has a paid plan: change it on its Plans page.');
         Database::transaction(function (PDO $pdo) use ($c, $plan, $e, $cur) {
```

The `claim.php` path below is assumed from its `../app/` requires. The attachment only says `sync-storage/claim.php`, so the lane should confirm the real repo path, probably the website root.

```diff
--- a/claim.php
+++ b/claim.php
@@ -229,7 +229,7 @@
   <ol class="tv-bleed cl-steps cl-card" aria-label="How claiming works">
     <li><b>Confirm by email.</b> We send a code to <?= $e($me['email']) ?>. You can then edit your description, hours, services and photos.</li>
     <li><b>Get verified.</b> One tap sends us a verification request (or text your claim's code). Once we confirm, your name, phone, address and website open up, and the listing shows ✓ Verified.</li>
-    <li><b><?= $kind === 'business' ? 'Choose a plan.' : 'Your plan.' ?></b> <?= $kind === 'business' ? 'Free, or a paid plan (free trial for now).' : 'Free. Plans for agencies and nonprofits are coming; until then, everything a claimed listing gets on Free is yours.' ?></li>
+    <li><b><?= $kind === 'business' ? 'Choose a plan.' : 'Your plan.' ?></b> <?= $kind === 'business' ? 'Free, or a paid plan (free trial for now).' : ($kind === 'care' ? 'Free. Plans for health care listings are coming; until then, everything a claimed listing gets on Free is yours.' : 'Free. Plans for agencies and nonprofits are coming; until then, everything a claimed listing gets on Free is yours.') ?></li>
   </ol>
   <?php if ($kind === 'public'): // who runs it: its Nexus (decisions/0054), never typed in as its name ?>
   <div class="tv-bleed cl-card"><h2>Who runs it</h2>
```

`listing/view.php`: file not shown, so there is no diff. The lane needs to draft or verify both of these there:
- the aside at :564, using `Claims::KIND_WORDS[Claims::kind($pdo, $id)]` for its button and tip;
- staff seeing "Open in the editor" instead of Claim.

## 3. Tests

**New file: `tools/listing-tests/claim-kind-care.php`**

```php
<?php
// tools/listing-tests/claim-kind-care.php: health care's claim kind (decisions/0070 note, 2026-11-27/28).
//   php tools/listing-tests/claim-kind-care.php
// Prints PASS/FAIL per check; exits 1 on any FAIL. The kind() and domains() checks use an in-memory SQLite
// fixture (SKIP if pdo_sqlite is missing), never the live database.
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/Claims.php';

$fail = 0; $n = 0;
$check = function (string $what, bool $ok) use (&$fail, &$n): void {
    $n++;
    echo ($ok ? 'PASS' : 'FAIL') . "  $what\n";
    if (!$ok) $fail++;
};

// 1–3. The map
$check('Health & Medical maps to care', (Claims::KIND_GROUPS['Health & Medical'] ?? null) === 'care');
$check('existing groups unchanged', (Claims::KIND_GROUPS['Government & Public Services'] ?? null) === 'public'
    && (Claims::KIND_GROUPS['Non-Profit & Community'] ?? null) === 'community'
    && (Claims::KIND_GROUPS['Religious & Faith-Based'] ?? null) === 'community');
$check('care roles as the note sets them', (Claims::KIND_ROLES['care'] ?? null) === ['Administrator or practice manager', 'Staff', 'Communications', 'Other']);

// 4–5. The words
$check('care title', (Claims::KIND_WORDS['care']['title'] ?? null) === 'Claim this health care listing');
$check('care tip', (Claims::KIND_WORDS['care']['tip'] ?? null) === 'Work at %s? Claim it to keep its hours and services current.');

// 6. Every kind the page can reach has roles and all four words, and its tip takes the name once
$kinds = array_unique(array_merge(['business', 'nation'], array_values(Claims::KIND_GROUPS)));
$whole = true;
foreach ($kinds as $k) {
    $w = Claims::KIND_WORDS[$k] ?? [];
    if (empty(Claims::KIND_ROLES[$k]) || !isset($w['button'], $w['tip'], $w['title'], $w['yours']) || substr_count($w['tip'], '%s') !== 1) {
        $whole = false; echo "      missing or bad: $k\n";
    }
}
$check('every kind has roles and words (no undefined index on claim.php)', $whole);

// 7–9. kind() and domains() against a fixture
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP  kind()/domains() fixture: pdo_sqlite not available\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, parent_id INTEGER)');
    $pdo->exec('CREATE TABLE entities (id INTEGER PRIMARY KEY, category_id INTEGER, latitude REAL, longitude REAL)');
    $pdo->exec("INSERT INTO categories VALUES (1, 'Health & Medical', NULL), (2, 'Dentist', 1), (3, 'Food & Drink', NULL), (4, 'Bakery', 3)");
    $pdo->exec('INSERT INTO entities VALUES (9001, 2, NULL, NULL), (9002, 1, NULL, NULL), (9003, 4, NULL, NULL)');
    $check('a dentist (child of Health & Medical) is care', Claims::kind($pdo, 9001) === 'care');
    $check('a listing filed on the group itself is care', Claims::kind($pdo, 9002) === 'care');
    $check('a bakery stays business', Claims::kind($pdo, 9003) === 'business');
    $check('care proof: the listing\'s own domain only, first', Claims::domains($pdo, 9001, 'https://www.example-clinic.com/hours') === ['example-clinic.com']);
    $check('care with no website: no domain route', Claims::domains($pdo, 9001, '') === []);
}

// 10–11. The page and the plan guard (source checks)
$claimPage = @file_get_contents($root . '/claim.php') ?: (@file_get_contents($root . '/website/claim.php') ?: '');
$check('claim.php step 3 names health care\'s plans as coming', str_contains($claimPage, 'Plans for health care listings are coming'));
$lib = (string) file_get_contents($root . '/app/lib/Claims.php');
$check('activate() keeps care on Free', str_contains($lib, "self::kind(\$pdo, \$e) === 'care'"));

echo "\n" . ($n - $fail) . "/$n passed\n";
exit($fail ? 1 : 0);
```

**Notes for the lane:**
- I placed this beside `tools/listing-tests/claim-kinds.php`, which is not shown. Match its bootstrap, meaning how it requires `Claims.php` and what env it sets. You could also fold these checks into it and update the "11 checks" count in 0070's note.
- The `claim.php` path probe tries two locations because the attachment's path is ambiguous. Fix it to the real one.
- The browser tests at 1280 and 390 required by the note belong in `claim-kinds.cjs`, which is not shown, so I haven't drafted them. They should cover:
  - a Health & Medical listing's aside (button and tip);
  - the claim page title and role list;
  - step 3's Free line;
  - no paid plan offered;
  - staff seeing "Open in the editor".

## 4. faq.md entries

faq.md is not shown, so match its heading style.

**Can I claim a clinic, pharmacy or other health care listing?**
Yes, if you work there. Open the listing and tap **Work here? Claim it**. The claim page is titled "Claim this health care listing". It asks your role:
- Administrator or practice manager;
- Staff;
- Communications;
- Other.

Once you've confirmed the email code we send to your account, you can keep the listing's hours, services, description and photos current.

**How do I confirm a health care claim?**
1. Confirm the code we email to your account.
2. For the ✓ Verified mark, the quickest way is a code sent to an email address at the practice's own website domain. This is offered when the listing has a website on file. You can also:
   - text your claim's code from the phone number on the listing;
   - send a document;
   - ask for a call or visit.

**Can I list myself as an individual doctor or provider?**
No. Traversence lists places, not individual people. Claim the clinic or practice where you work instead.

**Are there paid plans for health care listings?**
Not yet. Coming: plans of their own for health care listings. Until then, a claimed health care listing is on Free and gets everything a claimed listing gets on Free.

## 5. Risks and what the lane session must verify

1. **Group name.** `'Health & Medical'` must match the top-level `categories.name` exactly, including ampersand and