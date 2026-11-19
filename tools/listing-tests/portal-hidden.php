<?php
// Site-audit case #22: an owner or manager opens their own hidden listing in the portal (Claims::ownListing), the public
// lookup still hides it (Claims::listing), and the editor's notice says why only where the reason is theirs to know
// (Claims::hiddenReason). On MariaDB or MySQL: php portal-hidden.php <root> (a root holding app/, with a .env four
// folders up from app/lib/). It makes its own small tables in the test database, and drops them before and after.
declare(strict_types=1);
$root = $argv[1];
require $root . '/app/lib/Database.php';
foreach (['Response', 'Auth'] as $c) if (is_file("$root/app/lib/$c.php")) require_once "$root/app/lib/$c.php";
require $root . '/app/lib/Claims.php';
require $root . '/app/lib/Team.php';
$pdo = Database::connection();
$ok = 0; $bad = 0;
function t(string $name, bool $c) { global $ok, $bad; echo ($c ? 'ok   ' : 'FAIL ') . $name . "\n"; $c ? $ok++ : $bad++; }
$tables = 'entities, entity_metadata, listing_access, test_records, client_listings';
$pdo->exec("DROP TABLE IF EXISTS $tables");
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(160), entity_type VARCHAR(20) DEFAULT 'merchant', status VARCHAR(30) DEFAULT 'claimed-unverified',
            owner_user_id INT NULL, plan_tier VARCHAR(20) NULL, is_suppressed TINYINT DEFAULT 0, updated_at DATETIME NULL)");
$pdo->exec('CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(60), meta_value TEXT, PRIMARY KEY (entity_id, meta_key))');
$pdo->exec('CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20))');
$pdo->exec('CREATE TABLE test_records (kind VARCHAR(20), ref_id INT, label VARCHAR(120), created_at DATETIME, UNIQUE KEY (kind, label))');
$pdo->exec('CREATE TABLE client_listings (entity_id INT PRIMARY KEY, private TINYINT, tagged_by INT NULL)');
$owner = 7; $manager = 8; $stranger = 9;
$rows = [[1, 'Open Cafe', 0, 'verified'], [2, 'Test Listing', 1, 'claimed-unverified'], [3, 'Private Client', 1, 'claimed-unverified'],
         [4, 'Merged Away', 1, 'claimed-unverified'], [5, 'Kept Twin', 0, 'verified'], [6, 'Held By Staff', 1, 'verified'], [10, 'Pending One', 0, 'pending']];
foreach ($rows as [$id, $n, $h, $s]) $pdo->prepare('INSERT INTO entities (id, name, is_suppressed, status, owner_user_id) VALUES (?, ?, ?, ?, ?)')->execute([$id, $n, $h, $s, in_array($id, [5, 6], true) ? null : $owner]);
$pdo->exec("INSERT INTO listing_access VALUES (6, $manager, 'manager')");
$pdo->exec("INSERT INTO test_records VALUES ('listing', 2, 'Traversence Test Listing', NOW())");
$pdo->exec('INSERT INTO client_listings VALUES (3, 1, NULL)');
$pdo->exec("INSERT INTO entity_metadata VALUES (4, 'same_as', '{\"id\":5}'), (6, 'removed', '{\"target\":\"restricted\",\"why\":\"nation hold\"}')");

// the public lookup is unchanged: a hidden listing stays hidden
t('public: an open listing is found', Claims::listing($pdo, 1) !== null);
t('public: a hidden listing is not found', Claims::listing($pdo, 2) === null && Claims::listing($pdo, 6) === null);
// the team's own lookup
t('owner: opens their hidden test listing', ($r = Claims::ownListing($pdo, $owner, 2)) !== null && (int) $r['is_suppressed'] === 1);
t('owner: opens their open listing', Claims::ownListing($pdo, $owner, 1) !== null);
t('manager: opens the hidden listing they manage', Claims::ownListing($pdo, $manager, 6) !== null);
t('manager: not a listing they don\'t manage', Claims::ownListing($pdo, $manager, 2) === null);
t('a stranger: never a hidden listing', Claims::ownListing($pdo, $stranger, 2) === null && Claims::ownListing($pdo, $stranger, 6) === null);
t('a stranger: not even an open one (the portal is the team\'s)', Claims::ownListing($pdo, $stranger, 1) === null);
t('the list passed in decides, as Team::listingsOf would', Claims::ownListing($pdo, $stranger, 2, [2 => ['id' => 2]]) !== null && Claims::ownListing($pdo, $owner, 2, []) === null);
// the reasons
$why = fn (int $id) => Claims::hiddenReason($pdo, Claims::ownListing($pdo, $owner, $id) ?? Claims::ownListing($pdo, $manager, $id));
t('public listing: no notice', $why(1) === null);
t('test listing: said plainly, no ask needed', ($w = $why(2)) && str_contains($w['text'], 'test listing') && !$w['ask']);
t('private client: said plainly, with Ask our team', ($w = $why(3)) && str_contains($w['text'], 'private') && $w['ask']);
t('merged: names the kept listing with its link', ($w = $why(4)) && $w['link'][0] === '/listing/view.php?id=5' && str_contains($w['link'][1], 'Kept Twin') && $w['ask']);
t('merged: carries the kept listing, so the editor locks and points there', ($w = $why(4)) && ($w['merged'] ?? null) === [5, 'Kept Twin'] && str_contains($w['text'], 'make them on Kept Twin'));
t('other hidden kinds keep the editable form (no merged)', !isset($why(2)['merged']) && !isset($why(3)['merged']) && !isset($why(6)['merged']));
t('held by staff: only "under review", never the staff reason', ($w = $why(6)) && str_contains($w['text'], 'under review') && !str_contains(strtolower($w['text']), 'nation') && !str_contains(strtolower($w['text']), 'restricted') && $w['ask']);
t('not public yet (status): said, with Ask our team', ($w = $why(10)) && str_contains($w['text'], 'isn\'t public yet') && $w['ask']);
$pdo->exec('UPDATE entities SET is_suppressed = 1 WHERE id = 5');
t('merged into a listing that is hidden too: "under review", no link', ($w = $why(4)) && $w['link'] === null && str_contains($w['text'], 'under review'));
// asking our team: only the listing's own team, and an email to answer at
$_SESSION = [];
try { Claims::askAboutHidden($pdo, ['id' => $stranger, 'email' => 'x@example.com'], 6, 'why?'); t('ask: a stranger is refused', false); } catch (InvalidArgumentException $x) { t('ask: a stranger is refused', str_contains($x->getMessage(), 'manage')); }
try { Claims::askAboutHidden($pdo, ['id' => $owner, 'email' => ''], 2, 'why?'); t('ask: no email on the account says where to write', false); } catch (InvalidArgumentException $x) { t('ask: no email on the account says where to write', str_contains($x->getMessage(), '@')); }
$pdo->exec("DROP TABLE IF EXISTS $tables");
echo "$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
