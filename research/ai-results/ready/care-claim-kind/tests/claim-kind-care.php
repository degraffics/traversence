<?php
// tools/listing-tests/claim-kind-care.php: health care's claim kind (decisions/0070 note, 2026-11-27/28).
//   php tools/listing-tests/claim-kind-care.php
// Prints PASS/FAIL (or SKIP) per check and exits 1 on any FAIL.
// The kind(), domains() and activate() checks run on an in-memory SQLite fixture (SKIP without pdo_sqlite), never
// the live database. The activate() checks stop at the guards, before Billing: no trial is written and no money
// moves (QuickBooksPay is never reached).
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/lib/Claims.php';   // match claim-kinds.php's bootstrap if it sets env first (file not shown)

$fail = 0; $n = 0;
$check = function (string $what, bool $ok) use (&$fail, &$n): void {
    $n++;
    echo ($ok ? 'PASS' : 'FAIL') . "  $what\n";
    if (!$ok) $fail++;
};

// The map, as the note sets it
$check('Health & Medical maps to care', (Claims::KIND_GROUPS['Health & Medical'] ?? null) === 'care');
$check('existing groups unchanged', (Claims::KIND_GROUPS['Government & Public Services'] ?? null) === 'public'
    && (Claims::KIND_GROUPS['Non-Profit & Community'] ?? null) === 'community'
    && (Claims::KIND_GROUPS['Religious & Faith-Based'] ?? null) === 'community');
$check('care roles as the note sets them', (Claims::KIND_ROLES['care'] ?? null) === ['Administrator or practice manager', 'Staff', 'Communications', 'Other']);
$check('care title', (Claims::KIND_WORDS['care']['title'] ?? null) === 'Claim this health care listing');
$check('care tip', (Claims::KIND_WORDS['care']['tip'] ?? null) === 'Work at %s? Claim it to keep its hours and services current.');

// Every kind the page can reach has roles and all four words, and its tip takes the name once
$kinds = array_unique(array_merge(['business', 'nation'], array_values(Claims::KIND_GROUPS)));
$whole = true;
foreach ($kinds as $k) {
    $w = Claims::KIND_WORDS[$k] ?? [];
    if (empty(Claims::KIND_ROLES[$k]) || !isset($w['button'], $w['tip'], $w['title'], $w['yours']) || substr_count($w['tip'], '%s') !== 1) {
        $whole = false; echo "      missing or bad: $k\n";
    }
}
$check('every kind has roles and words (no undefined index on claim.php)', $whole);

// kind(), domains() and the activate() guard against a fixture
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP  fixture checks: pdo_sqlite not available\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, parent_id INTEGER)');
    $pdo->exec('CREATE TABLE entities (id INTEGER PRIMARY KEY, category_id INTEGER, latitude REAL, longitude REAL)');
    $pdo->exec('CREATE TABLE listing_claims (id INTEGER PRIMARY KEY, entity_id INTEGER, user_id INTEGER, status TEXT)');
    $pdo->exec("INSERT INTO categories VALUES (1, 'Health & Medical', NULL), (2, 'Dentist', 1), (3, 'Food & Drink', NULL), (4, 'Bakery', 3)");
    $pdo->exec('INSERT INTO entities VALUES (9001, 2, NULL, NULL), (9002, 1, NULL, NULL), (9003, 4, NULL, NULL)');
    $pdo->exec("INSERT INTO listing_claims VALUES (501, 9001, 77, 'managed'), (503, 9003, 77, 'managed')");

    $check('a dentist (child of Health & Medical) is care', Claims::kind($pdo, 9001) === 'care');
    $check('a listing filed on the group itself is care', Claims::kind($pdo, 9002) === 'care');
    $check('a bakery stays business', Claims::kind($pdo, 9003) === 'business');
    $check('care proof: the listing\'s own domain only', Claims::domains($pdo, 9001, 'https://www.example-clinic.com/hours') === ['example-clinic.com']);
    $check('care with no website: no domain route', Claims::domains($pdo, 9001, '') === []);

    if (!is_file($root . '/app/lib/Billing.php')) {
        echo "SKIP  activate() guard: app/lib/Billing.php not found\n";
    } else {
        $msg = function (callable $f): string { try { $f(); return '(no error)'; } catch (Throwable $x) { return $x->getMessage(); } };
        $care = 'Health care listings are on Free for now: plans for them are coming.';
        $check('activate(): a paid plan on care is refused, Terms agreed', $msg(fn () => Claims::activate($pdo, 77, 501, 'core', true)) === $care);
        $check('activate(): a paid plan on care is refused with the care reason, not the Terms', $msg(fn () => Claims::activate($pdo, 77, 501, 'basic', false)) === $care);
        $check('activate(): business still asks for the Terms first', $msg(fn () => Claims::activate($pdo, 77, 503, 'core', false)) === 'Review the trial and agree to the Terms first.');
    }
}

// The page (source check; the path is the lane's to confirm)
$claimPage = '';
foreach ([$root . '/website/claim.php', $root . '/claim.php'] as $p) if (is_file($p)) { $claimPage = (string) file_get_contents($p); break; }
if ($claimPage === '') echo "SKIP  claim.php not found at website/claim.php or claim.php\n";
else $check('claim.php step 3 names health care\'s plans as coming', str_contains($claimPage, 'Plans for health care listings are coming'));

echo "\n" . ($n - $fail) . "/$n passed\n";
exit($fail ? 1 : 0);
