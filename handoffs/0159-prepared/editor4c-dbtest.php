<?php
require __DIR__ . '/app/lib/Blocks.php';
$pdo = Database::connection(); $pdo->exec('DROP TABLE IF EXISTS site_block_zones, site_block_versions, site_block_schedule');
$z = 'site.top'; $tree = ['sections' => [['cols' => [[['t' => 'heading', 'text' => 'Holiday hours', 'level' => 2]]]]]];
function ok($c, $m) { echo ($c ? 'ok   ' : 'FAIL ') . $m . "\n"; }
try { Blocks::schedule($pdo, $z, $tree, gmdate('c', time() - 60), 1, 'Jason', 'America/Denver'); ok(false, 'past refused'); } catch (RuntimeException $e) { ok(true, 'past refused: ' . $e->getMessage()); }
echo Blocks::schedule($pdo, $z, $tree, gmdate('c', time() + 3600), 1, 'Jason', 'America/Denver'), "\n";
$s = Blocks::state($pdo, $z); ok($s['scheduled'] !== null && $s['live_version'] === 0, 'scheduled, not live: ' . json_encode($s['scheduled']));
Blocks::saveDraft($pdo, $z, ['sections' => [['cols' => [[['t' => 'text', 'md' => 'newer draft']]]]]], 1, 'Jason', 'America/Denver');
$pdo->exec("UPDATE site_block_schedule SET at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE");
// two looks at once: a second connection
$p2 = new PDO('mysql:host=127.0.0.1;port=3399;dbname=t', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$s1 = Blocks::state($pdo, $z); $s2 = Blocks::state($p2, $z);
ok($s1['live_version'] === 1 && $s2['live_version'] === 1, 'went live once: version ' . $s1['live_version']);
ok((int) $pdo->query("SELECT COUNT(*) FROM site_block_versions")->fetchColumn() === 1, 'one version row');
ok($s1['draft'] !== null && $s1['draft']['sections'][0]['cols'][0][0]['md'] === 'newer draft', 'newer draft kept');
ok($s1['scheduled'] === null, 'schedule gone');
echo 'note: ', $pdo->query('SELECT note FROM site_block_versions')->fetchColumn(), "\n";
echo Blocks::schedule($pdo, $z, $tree, gmdate('c', time() + 7200), 1, 'Jason', '') , "\n";
echo Blocks::unschedule($pdo, $z), "\n"; ok(Blocks::state($pdo, $z)['scheduled'] === null, 'called off');
// site(): CLI may edit, so it sees the draft
Blocks::discard($pdo, $z);
$h = Blocks::site('site.top', true, false); ok(str_contains($h, 'Holiday hours') && str_contains($h, 'tvb-site'), 'site.top drawn where it has something');
ok(Blocks::site('site.bottom') === '', 'site.bottom empty: nothing, not even an outline, off the home page');
ok(Blocks::site('site.top', false) === '', 'not on a page that doesn\'t take it');
ok(Blocks::site('site.bottom', true, true) !== '', 'home page: empty site.bottom outlined for editing');
