<?php
// Cards and photos (handoffs/screens-proposal-2026-11-22.md A4-A6, B3; the session manager's review 2026-11-22): the release's dv_mi, dv_credit and dv_card_photos, taken from
// its discovery/index.php, run with stand-ins for the photo libraries (stubs/), and its card drawn by explore-hero.php.
// Run: php run.php <the release's out/ dir> (needs its website/discovery/index.php and app/ui/explore-hero.php)
$OUT = rtrim($argv[1] ?? '', '/'); if (!is_file($OUT . '/website/discovery/index.php')) { fwrite(STDERR, "usage: php run.php <out dir>\n"); exit(2); }
$T = sys_get_temp_dir() . '/cf-' . getmypid(); @mkdir($T . '/website/discovery', 0777, true); @mkdir($T . '/app/lib', 0777, true);
foreach (glob(__DIR__ . '/stubs/*.php') as $f) copy($f, $T . '/app/lib/' . basename($f));
function tv_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function tv_icon($n, $c = '') { return '<svg class="ta-svg"></svg>'; }
$src = file_get_contents($OUT . '/website/discovery/index.php');
$code = '<?php ';
foreach (['dv_mi', 'dv_credit', 'dv_card_photos'] as $fn) { preg_match('/\nfunction ' . $fn . '\(.*?\n}\n/s', $src, $m); $code .= substr($m[0], 1); }
file_put_contents($T . '/website/discovery/fns.php', $code); require $T . '/website/discovery/fns.php';   // __DIR__ there finds the stubs as ../../app/lib
require $OUT . '/app/ui/explore-hero.php';
$pdo = new PDO('sqlite::memory:'); $pdo->exec('CREATE TABLE landmarks (name TEXT, state TEXT, image_url TEXT, image_credit TEXT, image_license TEXT)');
$pass = 0; $fail = 0; $ok = function ($c, $m) use (&$pass, &$fail) { if ($c) { $pass++; echo "  ✓ $m\n"; } else { $fail++; echo "  ✗ $m\n"; } };
$ok(dv_mi(null) === '' && dv_mi(INF) === '' && dv_mi(0.0, true) === '' && dv_mi(12.0, true) === '', 'no distance when the point isn\'t known: none, infinite, or only its ZIP\'s middle (at 0 or 12 mi)');
$ok(dv_mi(0.0) === 'under <b>½</b> mi' && dv_mi(0.4) === 'under <b>½</b> mi', 'a real point under half a mile says "under ½ mi", never "0 mi"');
$cr = dv_credit('Bob Ray', 'cc-by-sa-4.0'); $ok($cr['by'] === '© Bob Ray' && $cr['lic'] === 'CC BY-SA 4.0', 'a CC BY-SA credit: "© Bob Ray" and "CC BY-SA 4.0", both shown');
$cr = dv_credit('NPS', 'Public domain'); $ok($cr['by'] === 'NPS' && $cr['lic'] === 'Public domain', 'a public-domain photo: no ©, "Public domain"');
$cr = dv_credit('', 'CC0'); $ok($cr['by'] === 'Wikimedia Commons' && $cr['lic'] === 'CC0', 'no author: "Wikimedia Commons · CC0"');
$ok(dv_mi(43.2) === '<b>43</b> mi', 'a real distance shows: 43 mi');
$cards = [['name' => 'Lyman Lake State Park', 'eid' => 301, 'url' => '/l/301', 'text' => 'State Parks'], ['name' => 'Fool Hollow Lake', 'eid' => 302, 'url' => '/l/302', 'text' => 'State Parks'],
          ['name' => 'Navajo Code Talkers Memorial', 'eid' => 304, 'url' => '/l/304', 'text' => 'Monuments'], ['name' => 'Show Low Creek', 'eid' => 303, 'url' => '/l/303', 'text' => 'State Parks']];
$out = dv_card_photos($pdo, $cards, 'az');
$ok(($out[0]['image'] ?? '') === '/img/road.jpg' && ($out[0]['credit']['by'] ?? '') === 'Stock photo · Ann Lee on Unsplash', 'the first card gets the stock photo, its credit kept for the photo');
$ok(($out[1]['image'] ?? '') === '', 'the next card would repeat the same stock photo: the pattern instead');
$ok(($out[2]['image'] ?? '') === '' && StockLibrary::$n === 2, 'a memorial gets no stock photo (not even asked for one)');
$ok(($out[3]['image'] ?? '') === '/img/c.jpg' && ($out[3]['credit']['by'] ?? '') === '© Bob Ray' && ($out[3]['credit']['lic'] ?? '') === 'CC BY-SA 4.0', 'a place\'s own Commons photo still comes first, credited with its licence');
$ok(!array_filter($out, fn ($c) => array_filter($c['stats'] ?? [], fn ($x) => str_contains($x, 'Photo'))), 'no credit line under any card');
$h = tv_explore_card($out[0]);
$ok(str_contains($h, 'class="xc-cr"') && str_contains($h, '>Stock photo · Ann Lee on Unsplash</span>') && str_contains($h, '<i aria-hidden="true">i</i>') && str_contains($h, 'Stock photo (representative)'), 'the card shows "Stock photo" in words on the photo, with an i');
$h3 = tv_explore_card($out[3]);
$ok(str_contains($h3, '>© Bob Ray</span><span class="xc-lic" aria-hidden="true">CC BY-SA 4.0</span>'), 'a Commons card shows who and the licence in words on the photo');
$rec = dv_card_photos($pdo, [['name' => 'Navajo National Monument', 'rid' => 'r1', 'url' => '/r1', 'text' => 'Ancestral Puebloan cliff dwellings'], ['name' => 'Petrified Forest National Park', 'rid' => 'r2', 'url' => '/r2', 'text' => 'A park with a monument to the old road']], 'az');
$ok(($rec[0]['image'] ?? '') === '' && ($rec[1]['image'] ?? '') !== '', 'a monument by name gets no stock photo; a park whose summary mentions a monument still does');
$h2 = tv_explore_card($out[1]);
$ok(!str_contains($h2, 'xc-cr'), 'a card with no photo shows no credit');
$ok(str_contains($h, 'background:rgba(42,27,18,.62);border:1px solid rgba(255,251,242,.55)') , 'the corner icon sits on a dark disc');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
