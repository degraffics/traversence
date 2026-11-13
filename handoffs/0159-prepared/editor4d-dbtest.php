<?php
$T = $argv[1];
putenv('DB_HOST=127.0.0.1'); putenv('DB_PORT=3399'); putenv('DB_DATABASE=t'); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=');
require $T . '/app/lib/Blocks.php';
require_once $T . '/app/lib/Engage.php';
function ok($c, $m) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; }
$pdo = Database::connection();
$pdo->exec('DROP TABLE IF EXISTS site_block_zones, site_block_versions, site_block_schedule, site_pages, site_form_entries');
SitePages::ensure($pdo);
$pdo->exec("INSERT INTO site_pages (owner_id, slug, title, summary, body, form, status, created_at, updated_at) VALUES (0, 'about', 'About us', '', 'Hello', 'none', 'live', NOW(), NOW())");
$pid = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO site_pages (owner_id, slug, title, summary, body, form, status, created_at, updated_at) VALUES (0, 'draft', 'Draft', '', 'x', 'none', 'draft', NOW(), NOW())");
$did = (int) $pdo->lastInsertId();
// what the editor offers per zone
ok(isset(Blocks::typesFor("page.$pid")['form']) && isset(Blocks::typesFor("page.$pid")['engage']), 'page zone offers form and engage');
ok(!isset(Blocks::typesFor('site.top')['form']) && !isset(Blocks::liveFor('home.after-doors')['engage']) && isset(Blocks::typesFor('site.top')['search']), 'other zones don\'t; search still anywhere');
$tree = ['sections' => [['cols' => [[['t' => 'form', 'kind' => 'quote', 'heading' => 'Get a price'], ['t' => 'engage', 'comments' => '0']]]]]];
$c = Blocks::clean($tree); ok($c['sections'][0]['cols'][0][0] === ['t' => 'form', 'kind' => 'quote', 'heading' => 'Get a price'], 'form cleaned: ' . json_encode($c['sections'][0]['cols'][0]));
// before publishing: the form isn't taken
$in = ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Ten chairs please', 'kind' => 'quote'];
$pg = SitePages::get($pdo, $pid);
try { SitePages::submit($pdo, $pg, $in, '1.2.3.4'); ok(false, 'refused before publish'); } catch (InvalidArgumentException $e) { ok(true, 'refused before publish: ' . $e->getMessage()); }
Blocks::publish($pdo, "page.$pid", $tree, 1, 'Jason', 'America/Denver');
ok(Blocks::hasForm($pdo, $pid, 'quote') && !Blocks::hasForm($pdo, $pid, 'contact'), 'hasForm: quote yes, contact no');
$id = SitePages::submit($pdo, $pg, $in, '1.2.3.4'); ok($id > 0, 'quote taken after publish');
ok($pdo->query("SELECT kind FROM site_form_entries WHERE id = $id")->fetchColumn() === 'quote', 'entry kind quote');
try { SitePages::submit($pdo, $pg, ['kind' => 'contact'] + $in, '1.2.3.4'); ok(false, 'contact refused'); } catch (InvalidArgumentException $e) { ok(true, 'contact (not on the page) refused'); }
try { SitePages::submit($pdo, $pg, ['kind' => ''] + $in, '1.2.3.4'); ok(false, 'no kind refused'); } catch (InvalidArgumentException $e) { ok(true, 'no kind, page form none: refused as before'); }
// drawn in its zone, not elsewhere
Blocks::useDb($pdo, '/pages/view.php?s=about');
Blocks::inZone("page.$pid"); $h = Blocks::render(Blocks::clean($tree));
ok(str_contains($h, 'data-tvb-form') && str_contains($h, 'data-page="' . $pid . '"') && str_contains($h, 'Get a price') && substr_count($h, 'tvCaptcha') >= 1, 'form drawn in page zone');
ok(str_contains($h, 'drawn on the page itself'), 'engage placeholder without the page shell (API preview)');
Blocks::inZone('site.top'); $h2 = Blocks::render(Blocks::clean($tree)); ok($h2 === '', 'nothing drawn in site.top for visitors');
$h3 = Blocks::render(Blocks::clean($tree), true); ok(str_contains($h3, 'only on our own pages'), 'editor told why in site.top');
// engage kind page
ok(in_array('page', Engage::KINDS, true) && Engage::COUNT_KEY['page'] === 'page:', 'Engage page kind and count key');
ok(Engage::target('page', (string) $pid) === ['page', (string) $pid], 'target page ok');
try { Engage::target('page', 'x1'); ok(false, 'bad ref'); } catch (InvalidArgumentException $e) { ok(true, 'bad ref refused'); }
ok(Engage::exists($pdo, 'page', (string) $pid) && !Engage::exists($pdo, 'page', (string) $did) && !Engage::exists($pdo, 'page', '99999'), 'exists: live yes, draft no, missing no');
