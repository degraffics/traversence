<?php
// tools/trip-tests/sharing.php: sharing, Personal Guides and counts (decisions/0174 release 4), on a real MySQL or MariaDB.
// php sharing.php <root>: as trips.php, with the migrations up to 2026-11-21_personal-guides.sql run, and
// platform_counts, listing_actions and content_reports. Rows: users 990021+, entities 990021+, removed after.
declare(strict_types=1);
$root = $argv[1] ?? '';
require_once $root . '/app/bootstrap.php';
require_once $root . '/app/lib/Trips.php';
require_once $root . '/app/lib/Engage.php';
require_once $root . '/app/lib/Links.php';
require_once $root . '/app/lib/Reports.php';
require_once $root . '/app/lib/ListingActions.php';
require_once $root . '/app/lib/PlatformCounts.php';
$pass = 0; $fail = 0;
$ok = function (bool $c, string $m) use (&$pass, &$fail) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; $c ? $pass++ : $fail++; };
$pdo = Database::connection();
$clean = function () use ($pdo) {
    $pdo->exec('DELETE FROM personal_guides WHERE user_id BETWEEN 990021 AND 990029');
    $pdo->exec('DELETE s FROM trip_stops s JOIN trips t ON t.id = s.trip_id WHERE t.user_id BETWEEN 990021 AND 990029');
    $pdo->exec('DELETE FROM trips WHERE user_id BETWEEN 990021 AND 990029');
    $pdo->exec('DELETE FROM entity_metadata WHERE entity_id BETWEEN 990021 AND 990029');
    $pdo->exec('DELETE FROM entities WHERE id BETWEEN 990021 AND 990029');
    $pdo->exec('DELETE FROM contributors WHERE user_id BETWEEN 990021 AND 990029');
    $pdo->exec('DELETE FROM listing_actions WHERE entity_id BETWEEN 990021 AND 990029');
    $pdo->exec("DELETE FROM platform_counts WHERE metric = 'trip'");
    $pdo->exec("DELETE FROM content_reports WHERE kind = 'personal_guide'");
};
$clean();
$ins = $pdo->prepare('INSERT INTO entities (id, name, category_id, latitude, longitude, is_suppressed) VALUES (?, ?, NULL, ?, ?, 0)');
$ins->execute([990021, 'Open Cafe', 34.25, -110.03]);
$ins->execute([990022, "Hope Women's Shelter", 34.26, -110.04]);
$ins->execute([990023, 'Gone Grill', 34.27, -110.05]);
$pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (?, ?, ?)')->execute([990023, 'closed', json_encode(['note' => 'gone'])]);
$u = 990021; $other = 990022;

// ---- sharing (0174 §1) ----
$t = Trips::create($pdo, $u, 'Shared one');
Trips::add($pdo, $u, $t, 'listing', '990021');
$url = Trips::share($pdo, $u, $t, true);
$tok = substr($url, strrpos($url, '=') + 1);
$ok(str_starts_with($url, 'https://traversence.com/trip/?t=') && strlen($tok) === 32, 'Share: a link with a long random token');
$ok((int) (Trips::byToken($pdo, $tok)['id'] ?? 0) === $t, 'the link opens the trip');
Trips::share($pdo, $u, $t, false);
$ok(Trips::byToken($pdo, $tok) === null, 'turned off: the link opens nothing');
$url2 = Trips::share($pdo, $u, $t, true); $tok2 = substr($url2, strrpos($url2, '=') + 1);
$ok($tok2 !== $tok && Trips::byToken($pdo, $tok) === null && Trips::byToken($pdo, $tok2) !== null, 'on again: a new link; the old one stays dead');
$threw = false; try { Trips::share($pdo, $other, $t, true); } catch (RuntimeException $e) { $threw = true; }
$ok($threw, 'nobody shares someone else\'s trip');
$ok(Trips::byToken($pdo, "' OR 1=1 --") === null && Trips::byToken($pdo, '') === null, 'a made-up token opens nothing');
Trips::delete($pdo, $u, $t);
$ok(Trips::byToken($pdo, $tok2) === null, 'a deleted trip\'s link opens nothing');
Trips::delete($pdo, $u, $t, true);
// what a shared view may show
$p = Trips::resolve($pdo, 'listing', '990022'); $c = Trips::resolve($pdo, 'listing', '990023');
$ok(!empty($p['private']) && empty($c['private']) && !$c['ok'], 'a private place is marked never to show; a closed one is public, shown greyed');

// ---- counts (0174 §1, 0058 §13, 0094) ----
$_SESSION = [];
$lm = (string) $pdo->query('SELECT gnis_id FROM landmarks WHERE lat IS NOT NULL LIMIT 1')->fetchColumn();
$t2 = Trips::create($pdo, $u, 'Counted');
Trips::add($pdo, $u, $t2, 'listing', '990021');
if ($lm !== '') Trips::add($pdo, $u, $t2, 'lm', $lm);
$n = (int) $pdo->query("SELECT SUM(n) FROM listing_actions WHERE entity_id = 990021 AND action = 'trip'")->fetchColumn();
$ok($n >= 1 && isset(ListingActions::ACTIONS['trip']), 'a business added to a trip: counted in its actions ("Added to trips")');
if ($lm !== '') $ok((int) $pdo->query("SELECT SUM(n) FROM platform_counts WHERE metric = 'trip' AND k = 'lm:" . (int) $lm . "'")->fetchColumn() >= 1, 'a place added to a trip: counted (never who)');
if ($lm !== '') $ok(Trips::addedThisMonth($pdo, 'lm', $lm) >= 1, 'a place\'s page reads "Added to N trips this month" (Jason, 2026-11-20)');
$ok(Trips::addedThisMonth($pdo, 'listing', '990021') === 0, 'a business\'s count isn\'t shown publicly: it\'s in its own insights');
$cols = $pdo->query("SHOW COLUMNS FROM listing_actions")->fetchAll(PDO::FETCH_COLUMN);
$ok(!array_intersect($cols, ['user_id', 'session', 'ip']), 'nothing about who is stored with a count');

// ---- Personal Guides (0174 §1; checked like a journey) ----
$t3 = Trips::create($pdo, $u, 'Coffee stops');
Trips::add($pdo, $u, $t3, 'listing', '990021');
$intro = 'The three places I go for coffee when I am in Show Low, in the order I drive past them.';
$threw = ''; try { Trips::publishGuide($pdo, $u, $t3, 'Coffee stops', $intro); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok(str_contains($threw, 'contributor'), 'not a contributor yet: refused, with how to become one');
$pdo->prepare("INSERT INTO contributors (user_id, status) VALUES (?, 'active')")->execute([$u]);
$threw = ''; try { Trips::publishGuide($pdo, $u, $t3, 'Coffee stops', 'Too short.'); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok(str_contains($threw, 'few sentences'), 'a few sentences at least, as a journey before it publishes');
$g = Trips::publishGuide($pdo, $u, $t3, 'Coffee stops', $intro);
$ok($g['status'] === 'published' && Trips::guide($pdo, (int) $g['id']) !== null, 'published: anyone may read it');
$gid = (int) $g['id'];
$ok(Engage::target('pg', (string) $gid) !== [] && Engage::exists($pdo, 'pg', (string) $gid), 'a content kind: likes and comments work on it');
$ok(Links::resolve($pdo, 'place', 'pg:' . $gid)[1] === '/guide/personal.php?id=' . $gid, 'its Link button resolves');
$h = Trips::publishGuide($pdo, $u, $t3, 'Coffee stops', $intro . ' Ask for the casino bonus.');
$ok($h['status'] === 'held' && Trips::guide($pdo, $gid) === null && str_contains($h['hidden_reason'], 'casino bonus'), 'changed words are checked again: held, not shown, with why');
Trips::publishGuide($pdo, $u, $t3, 'Coffee stops', $intro);
Trips::withdrawGuide($pdo, $u, $t3);
$ok(Trips::guide($pdo, $gid) === null && Trips::guideOf($pdo, $t3)['status'] === 'withdrawn', 'Withdraw: it stops showing');
Trips::withdrawGuide($pdo, $u, $t3, true);
$ok(Trips::guide($pdo, $gid) !== null, 'Publish again: back');
Reports::file($pdo, $other, 'personal_guide', $gid, 'spam', '');
$ok((int) $pdo->query("SELECT COUNT(*) FROM content_reports WHERE kind = 'personal_guide' AND target_id = $gid")->fetchColumn() === 1, 'reportable, like a journey');
$threw = ''; try { Reports::file($pdo, $u, 'personal_guide', $gid, 'spam', ''); } catch (InvalidArgumentException $e) { $threw = $e->getMessage(); }
$ok(str_contains($threw, 'your own'), 'not by its own author');
Reports::decide($pdo, 1, 'personal_guide', $gid, 'remove');
$ok(Trips::guide($pdo, $gid) === null && Trips::guideOf($pdo, $t3)['status'] === 'hidden', 'our team hides it after reports');
$threw = ''; try { Trips::publishGuide($pdo, $u, $t3, 'Coffee stops', $intro); } catch (RuntimeException $e) { $threw = $e->getMessage(); }
$ok(str_contains($threw, 'Our team hid'), 'its author can\'t publish over a hide: they\'re told to ask our team');
Reports::decide($pdo, 1, 'personal_guide', $gid, 'restore');
$ok(Trips::guide($pdo, $gid) !== null, 'restored');
Trips::delete($pdo, $u, $t3);
$ok(Trips::guide($pdo, $gid) === null, 'its trip deleted: the guide stops showing (Put back brings both)');
$src = file_get_contents($root . '/app/lib/AccountDeletion.php');
$ok(str_contains($src, "UPDATE personal_guides SET status = 'hidden', hidden_reason = 'The author deleted their account', title = '', intro = ''"), 'account deletion hides and empties their guides, as journeys (0174 §6)');
$clean();
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
