<?php
// market/index.php — the Marketplace (decisions/0031, 0135): what people near you are offering, newest first.
// The landing (Jason's review, 2026-11-03, design/marketplace-landing-mockup.png): the title and a local line, four
// promises (0% from sales, paid to the seller directly, pickup or meet locally, town only), the search with Filters and
// Sort (newest, nearest town to town, price), big category tiles on the plain landing only, then the grid with its count.
// The Marketplace menu is the site's own rail (section-rails.php).
//
// Facebook Marketplace is the base (a grid of posts: photo, price, title, roughly where), without paid or engagement-
// ranked placement: visibility is never bought (0007). Find by words and a place; the Filters button holds category,
// kind and price, and the ones in use show beside it with ×. No payment passes through Traversence: you message the
// seller and work out price, payment and pickup together. Businesses' catalogs join in step 3.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/lib/Market.php';
require_once __DIR__ . '/../../app/lib/MarketBiz.php';
require_once __DIR__ . '/../../app/ui/market-ui.php';

$pdo = Database::connection();
$uid = Auth::currentUserId();
$ready = Market::ready($pdo);
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$place = (string) ($_GET['place'] ?? '');
$placeLabel = '';
if ($place !== '') { try { [$place, $placeLabel] = Market::place($pdo, $place); } catch (Throwable) { $place = ''; } }
$cats = $ready ? Market::categories($pdo) : [];
$cat = isset($cats[(string) ($_GET['category'] ?? '')]) ? (string) $_GET['category'] : '';
require_once __DIR__ . '/../../app/lib/ItemKinds.php';
$kind = (string) ($_GET['kind'] ?? '') === 'item' ? 'physical' : ((string) ($_GET['kind'] ?? '') === 'service' ? 'service' : (isset(ItemKinds::KINDS[(string) ($_GET['kind'] ?? '')]) ? (string) $_GET['kind'] : ''));   // the nine kinds (decisions/0141 §5)
$free = !empty($_GET['free']);
$max = $free ? 0 : max(0, (int) ($_GET['max'] ?? 0));
$from = in_array((string) ($_GET['from'] ?? ''), ['people', 'business'], true) ? (string) $_GET['from'] : '';
// the four sections (Jason, 2026-11-27, decisions/0135's note; the CEO's rulings of the same date): Shops (businesses' and
// makers' items), Experiences & Regional Goods (a window onto Shops: the kinds in MarketBiz::REGIONAL_KINDS from a business
// whose home is one of our areas), Trade (business to business, coming) and the Aftermarket (people's own posts). A section
// picks the grid's source, as From does; Browse all (no section) stays the landing, so nothing that opens /market/ moves.
$sections = [
    'shops' => ['Shops', 'store', 'Local businesses and makers: goods, services, tickets and stays'],
    'regional' => ['Experiences & Regional Goods', 'pin', 'Tickets, stays, harvest and made to order, from businesses at home here'],
    'trade' => ['Trade', 'users', 'Businesses buying from businesses: coming'],
    'aftermarket' => ['Aftermarket', 'home', 'Neighbors\' own things, paid between you'],
];
$section = isset($sections[(string) ($_GET['section'] ?? '')]) ? (string) $_GET['section'] : '';
if ($section === 'shops' || $section === 'regional') $from = 'business';
elseif ($section === 'aftermarket') $from = 'people';
$tabOn = $section !== '' ? $section : ($from === 'business' ? 'shops' : ($from === 'people' ? 'aftermarket' : ''));   // From in Filters lights its section
$sort = isset(Market::SORTS[(string) ($_GET['sort'] ?? '')]) ? (string) $_GET['sort'] : 'new';
// pages by number for every sort, 10 a page unless the visitor chose 25, 50 or 100 (the lists' standard, Jason, 2026-11-12;
// it replaced "More" by time, so the page bar can show where you are and how many in all)
$per = max(1, min(120, (int) ($_GET['per'] ?? 10)));   // the page sends the visitor's choice rounded up to whole rows of cards
$page = max(1, min(200, (int) ($_GET['page'] ?? 1)));
$beforeAt = '';
$before = $page > 1 ? 1 : 0;   // a later page
// Nearest: town to town, from the town chosen here; else the header's location (the page adds ?near=, rounded to about a
// mile, never kept); else a signed-in member's home town. Without any, it asks for a town and shows newest.
$origin = null; $originLabel = ''; $near = '';
if ($sort === 'near' && $ready) {
    if ($place !== '') { $origin = Market::placeCenter($pdo, $place); $originLabel = $placeLabel; }
    if (!$origin && preg_match('/^(-?\d{1,2}(?:\.\d{1,2})?),(-?\d{1,3}(?:\.\d{1,2})?)$/', (string) ($_GET['near'] ?? ''), $nm) && abs((float) $nm[1]) <= 90 && abs((float) $nm[2]) <= 180) {
        $origin = [(float) $nm[1], (float) $nm[2]]; $originLabel = 'your location'; $near = $nm[1] . ',' . $nm[2];
    }
    if (!$origin && $uid) {
        try { $hs = $pdo->prepare('SELECT home_cluster_id FROM users WHERE id = ?'); $hs->execute([$uid]); $home = (int) $hs->fetchColumn();
            if ($home && ($origin = Market::placeCenter($pdo, 'cluster:' . $home))) { try { $originLabel = Market::place($pdo, 'cluster:' . $home)[1]; } catch (Throwable) { $originLabel = 'your home town'; } } } catch (Throwable) {}
    }
}
$needTown = $sort === 'near' && !$origin;
// people's posts and businesses' published items (step 3) in one grid: each source gives its first part in the same
// order and the two are merged. Newest pages by time (the next starts before the last shown); the other sorts by number.
$f = ['q' => $q, 'place' => $place, 'category' => $cat, 'kind' => $kind, 'max' => $max, 'free' => $free ? 1 : 0, 'before_at' => $beforeAt, 'sort' => $needTown ? 'new' : $sort, 'origin' => $origin, 'regional' => $section === 'regional' ? 1 : 0];
$lim = min(5000, $page * $per + 1);   // each source's first part, merged, then this page of it
$trade = $section === 'trade';   // coming: no grid yet
$posts = $ready && !$trade && $from !== 'business' ? Market::browse($pdo, $f, $uid, $lim)['posts'] : [];
$items = $ready && !$trade && $from !== 'people' && MarketBiz::ready($pdo) ? MarketBiz::browse($pdo, $f, $lim) : [];
$all = array_merge($posts, $items);
if ($f['sort'] === 'new') {
    usort($all, fn ($a, $b) => strcmp($b['when'], $a['when']));
    $more = count($all) > $page * $per ? (string) ($page + 1) : '';
    $shown = array_slice($all, ($page - 1) * $per, $per);
} else {
    $dir = $f['sort'] === 'high' ? -1 : 1;
    usort($all, function ($a, $b) use ($dir) {
        $x = $a['sk'] ?? null; $y = $b['sk'] ?? null;
        if ($x === null || $y === null) return $x === $y ? strcmp($b['when'], $a['when']) : ($x === null ? 1 : -1);   // no price (Ask) or no town: last
        return $x == $y ? strcmp($b['when'], $a['when']) : ($x < $y ? -$dir : $dir);
    });
    $more = count($all) > $page * $per ? (string) ($page + 1) : '';
    $shown = array_slice($all, ($page - 1) * $per, $per);
}
$res = ['posts' => $shown, 'more' => $more];
$total = $ready && !$trade ? ($from !== 'business' ? Market::count($pdo, $f, $uid) : 0) + ($from !== 'people' ? MarketBiz::count($pdo, $f) : 0) : 0;
$saved = [];
$postIds = array_map(fn ($c) => (int) $c['id'], array_filter($res['posts'], fn ($c) => empty($c['biz'])));
if ($uid && $postIds) {
    $st = $pdo->prepare('SELECT post_id FROM market_saves WHERE user_id = ? AND post_id IN (' . implode(',', $postIds) . ')');
    $st->execute([$uid]);
    $saved = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
}
$keep = array_filter(['q' => $q, 'place' => $place, 'category' => $cat, 'kind' => $kind, 'section' => $section, 'from' => $section !== '' ? '' : $from, 'max' => $max ?: '', 'free' => $free ? '1' : '', 'sort' => $sort !== 'new' ? $sort : '', 'near' => $near, 'per' => $per !== 10 ? (string) $per : ''], fn ($v) => $v !== '');
$url = fn (array $without = [], array $plus = []) => '/market/' . (($p = http_build_query(array_diff_key($keep, array_flip($without)) + $plus)) !== '' ? '?' . $p : '');
$chips = [];
if ($cat !== '') $chips[] = [$cat, $url(['category'])];
if ($kind !== '') $chips[] = [ItemKinds::name($kind), $url(['kind'])];
if ($from !== '' && $section === '') $chips[] = [$from === 'people' ? 'From people' : 'From businesses', $url(['from'])];
if ($free) $chips[] = ['Free', $url(['free'])];
if ($max) $chips[] = ['Up to $' . number_format($max), $url(['max'])];
$nFilters = count($chips);
$landing = $ready && !$before && $q === '' && $cat === '' && $kind === '' && $from === '' && $section === '' && !$free && !$max && $cats;   // the tiles: the plain landing only
// the grid's heading: what order it's in, and where
$head = $needTown || $sort === 'new' ? 'Newest' . ($placeLabel !== '' ? ' in and around ' . $placeLabel : '') : ($sort === 'near' ? 'Nearest to ' . $originLabel : ($sort === 'low' ? 'Lowest price first' : 'Highest price first') . ($placeLabel !== '' ? ' in and around ' . $placeLabel : ''));
if ($tabOn !== '') $head = $sections[$tabOn][0] . ': ' . lcfirst($head);   // the grid's heading names its section
/** A category's tile icon (the brand's flat icons), from words in its name. */
$catIcon = function (string $c): string {
    $n = strtolower($c);
    foreach ([['/vehicle|motor|auto|car\b|atv|truck|trailer|boat/', 'truck'], ['/garden|yard/', 'tree'], ['/appliance|electronic|computer|phone/', 'power'], ['/d[eé]cor/', 'sparkle'],
              ['/baby|kid/', 'user'], ['/home|furniture|household/', 'home'], ['/tool|equipment|gear|machin/', 'settings'],
              ['/farm|ranch|livestock|hay|feed|timber|wood|lumber|build/', 'tree'], ['/harvest|produce|food|bak|kitchen/', 'utensils'], ['/craft|art|handmade|jewel/', 'pen'],
              ['/cloth|apparel|wear|shoe/', 'user'], ['/electronic|computer|phone/', 'power'], ['/book|music|media|game/', 'book'], ['/outdoor|sport|camp|hunt|fish|bike/', 'compass'],
              ['/service|lesson|repair|help/', 'users'], ['/event|ticket|class/', 'calendar'], ['/free|give/', 'sparkle'], ['/pet|animal/', 'shield']] as [$re, $ic]) if (preg_match($re, $n)) return $ic;
    return 'store';
};

tv_page_start('Marketplace', ['no_crumbs' => true, 'description' => 'Buy, sell and trade with local businesses, makers and neighbors on Traversence.']);
echo tv_market_css();
?>
<style>
  .mk{max-width:72rem}
  .mk-top{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:.6rem 1rem}
  .mk-h1{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:clamp(1.6rem,2.8vw,2.2rem);color:var(--tv-ink,#3E2B1F);margin:0}
  .mk-sub{font-size:.92rem;color:var(--tv-meta,#5C4632);margin:.25rem 0 0;max-width:40rem}
  .mk-go{display:inline-flex;align-items:center;gap:.4rem;background:var(--tv-btn);color:var(--tv-btn-text);border-radius:.7rem;padding:.65rem 1.15rem;font-weight:700;font-size:.92rem;text-decoration:none;box-shadow:0 8px 18px -10px rgba(146,64,14,.7)}
  .mk-go:hover{background:var(--tv-btn-hover)}
  .mk-go .ta-svg{width:16px;height:16px}
  .mk-find{display:flex;flex-wrap:wrap;gap:.45rem;align-items:center;margin:1rem 0 .4rem}
  .mk-in{border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink,#3E2B1F);border-radius:.6rem;padding:.45rem .65rem;font-size:.9rem;min-width:0}
  .mk-q{flex:2 1 14rem}.mk-pl{flex:1 1 11rem;position:relative}.mk-pl .mk-in{width:100%}
  .mk-list{position:absolute;left:0;right:0;top:100%;z-index:30;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.5rem;box-shadow:0 10px 20px -12px rgba(0,0,0,.4);max-height:16rem;overflow:auto}
  .mk-list button{display:flex;justify-content:space-between;gap:.5rem;width:100%;text-align:left;border:0;border-bottom:1px solid var(--tv-line,#EADFC4);background:none;padding:.5rem .6rem;font:inherit;font-size:.88rem;cursor:pointer;color:var(--tv-ink,#3E2B1F)}
  .mk-list small{color:var(--tv-meta,#8A7355)}
  .mk-b2{border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink,#3E2B1F);border-radius:.6rem;padding:.45rem .8rem;font-size:.88rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem}
  .mk-b2.go{background:var(--tv-umber,#3E2B1F);border-color:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2)}
  .mk-b2 .ta-svg{width:15px;height:15px;color:var(--tv-icon,#B45309)}
  .mk-dd{position:relative}.mk-dd>summary{list-style:none}.mk-dd>summary::-webkit-details-marker{display:none}
  .mk-dd[open]>summary{border-color:#B45309}
  .mk-ddp{position:absolute;right:0;top:calc(100% + .3rem);z-index:30;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.7rem;box-shadow:0 14px 30px -16px rgba(0,0,0,.45);padding:.7rem;width:min(19rem,calc(100vw - 2rem));display:grid;gap:.5rem}
  .mk-ddp label{display:grid;gap:.2rem;font-size:.78rem;font-weight:700;color:var(--tv-meta,#5C4632)}.mk-ddp label.ck{display:flex;align-items:center;gap:.4rem;font-weight:400;font-size:.88rem}
  .mk-n{background:#B45309;color:var(--tv-on-umber,#FFFBF2);border-radius:999px;font-size:.7rem;padding:0 .4rem}
  .mk-chips{display:flex;flex-wrap:wrap;gap:.35rem;margin:.2rem 0 .8rem}
  .mk-chip{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;background:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2);font-size:.8rem;padding:.2rem .6rem;text-decoration:none}
  .mk-chip b{color:#F3DDB0;font-size:.95rem}
  .mk-more{display:flex;justify-content:center;margin:1rem 0}
  .mk-empty{background:var(--tv-sec,#F7EFDC);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.9rem;padding:1rem;font-size:.92rem;color:var(--tv-meta,#5C4632)}
  .mk-h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.3rem;color:var(--tv-ink,#3E2B1F);margin:0}
  /* the four promises: each opens its answer in the FAQ */
  .mk-pr{display:grid;gap:.6rem;grid-template-columns:repeat(4,minmax(0,1fr));margin:1.1rem 0 .2rem}
  .mk-pi{display:flex;gap:.6rem;align-items:center;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line,#EADFC4);border-radius:.8rem;padding:.65rem .75rem;text-decoration:none;color:var(--tv-ink,#3E2B1F);min-width:0}
  .mk-pi:hover{border-color:var(--tv-line-strong,#CBB98D)}.mk-pi .ta-svg{width:20px;height:20px;color:var(--tv-icon,#B45309);flex:none;padding:.45rem;box-sizing:content-box;background:var(--tv-sec,#F7EFDC);border-radius:.55rem}
  .mk-pi b{display:block;font:700 .74rem system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase}.mk-pi small{display:block;color:var(--tv-meta,#8A7355);font-size:.8rem;line-height:1.3}
  /* the category tiles (the plain landing only): a way in, not a filter row */
  .mk-tiles{display:flex;gap:.55rem;overflow-x:auto;scroll-snap-type:x proximity;margin:1rem 0 .3rem;padding-bottom:.35rem;scrollbar-width:thin}   /* one row that scrolls sideways */
  .mk-tile{flex:0 0 7.4rem;scroll-snap-align:start;display:flex;flex-direction:column;align-items:center;gap:.45rem;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line,#EADFC4);border-radius:.8rem;padding:.85rem .5rem .75rem;text-decoration:none;color:var(--tv-ink,#3E2B1F);font-size:.84rem;font-weight:600;text-align:center;line-height:1.2}
  .mk-tile .ta-svg{width:22px;height:22px;color:var(--tv-icon,#B45309);padding:.6rem;box-sizing:content-box;background:var(--tv-sec,#F7EFDC);border:1px solid var(--tv-line,#EADFC4);border-radius:999px}
  .mk-tile:hover{border-color:var(--tv-line-strong,#CBB98D)}.mk-tile.on{border-color:#B45309;box-shadow:0 0 0 2px rgba(180,83,9,.15)}.mk-tile.on .ta-svg{background:#B45309;border-color:#B45309;color:var(--tv-on-umber,#FFFBF2)}
  .mk-gh{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem 1rem;margin:1.4rem 0 .7rem}
  .mk-gh>div{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}
  .mk-cnt{border-radius:999px;background:var(--tv-sec,#F7EFDC);border:1px solid var(--tv-line,#EADFC4);color:var(--tv-link,#92400E);font-size:.8rem;font-weight:600;padding:.12rem .6rem}
  .mk-sort{display:flex;align-items:center;gap:.4rem;font-size:.84rem;color:var(--tv-meta,#5C4632)}
  /* the four sections: a row of tabs under the promises (0135's note, 2026-11-27) */
  .mk-sec{display:flex;gap:.45rem;overflow-x:auto;margin:1rem 0 .2rem;padding-bottom:.2rem;scrollbar-width:thin}
  .mk-st{flex:0 0 auto;display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink,#3E2B1F);border-radius:999px;padding:.4rem .85rem;font-size:.88rem;font-weight:600;text-decoration:none;white-space:nowrap}
  .mk-st .ta-svg{width:15px;height:15px;color:var(--tv-icon,#B45309)}
  .mk-st:hover{border-color:#B45309}.mk-st[aria-current="page"]{background:var(--tv-umber,#3E2B1F);border-color:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2)}.mk-st[aria-current="page"] .ta-svg{color:#F3DDB0}
  .mk-st small{font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--tv-meta,#8A7355)}.mk-st[aria-current="page"] small{color:#F3DDB0}
  .mk-sd{font-size:.86rem;color:var(--tv-meta,#5C4632);margin:.35rem 0 0}
  @media (max-width:767px){
    .mk-sec{gap:3px;margin-left:calc(3px - var(--tv-pad,.85rem));margin-right:calc(3px - var(--tv-pad,.85rem));padding-left:calc(var(--tv-pad,.85rem) - 3px)}
    .mk-pr{grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}.mk-pi{border-radius:.5rem;padding:.55rem .6rem;gap:.45rem}.mk-pi .ta-svg{padding:.35rem}
    .mk-tiles{gap:3px;margin-left:calc(3px - var(--tv-pad,.85rem));margin-right:calc(3px - var(--tv-pad,.85rem))}.mk-tile{flex-basis:6.4rem;border-radius:.5rem}
    .mk-q{flex-basis:100%}.mk-pl{flex:1 1 0;min-width:0}
    .mk-go{padding:.55rem .9rem}
  }
</style>
<div class="mk">
  <div class="mk-top">
    <div><h1 class="mk-h1">Marketplace</h1><p class="mk-sub">Buy, sell and trade with local businesses, makers and neighbors<?= $placeLabel !== '' ? ' in and around ' . tv_e($placeLabel) : '' ?>.</p></div>
    <?php $tvFull = ''; if ($ready && $uid) { try { $tvCap = (int) Market::setting($pdo, 'person_cap'); if (Market::outCount($pdo, (int) $uid) >= $tvCap) $tvFull = "You have $tvCap posts out, the most at once. Mark one sold or take one off first."; } catch (Throwable) {} } ?>
    <?php if ($tvFull !== ''): /* 0135 §7: greyed out with the reason; a tap says why */ ?><button type="button" class="mk-go" aria-disabled="true" style="opacity:.55;cursor:default" title="<?= tv_e($tvFull) ?>" data-full="<?= tv_e($tvFull) ?>"><?= tv_icon('plus') ?>Post something</button>
    <script>document.addEventListener('click', function (e) { var b = e.target.closest('[data-full]'); if (b && window.TvAsk) TvAsk.say(b.getAttribute('data-full'), { anchor: b, bad: true }); });</script>
    <?php else: ?><a class="mk-go" href="/user/market/?new=1"><?= tv_icon('plus') ?>Post something</a><?php endif; ?>
  </div>

  <div class="mk-pr">
    <a class="mk-pi" href="/faq.php#what-is-the-marketplace"><?= tv_icon('percent') ?><span><b>0% from any sale</b><small>Traversence takes nothing</small></span></a>
    <a class="mk-pi" href="/faq.php#how-do-i-pay-for-something-on-the-marketplace"><?= tv_icon('cart') ?><span><b>Paid to the seller</b><small>Directly, or through their own Stripe or Square</small></span></a>
    <a class="mk-pi" href="/faq.php#how-do-i-buy-from-a-business-on-the-marketplace"><?= tv_icon('truck') ?><span><b>Pickup or meet locally</b><small>Or delivery, where a business offers it</small></span></a>
    <a class="mk-pi" href="/faq.php#can-people-see-my-address-on-the-marketplace"><?= tv_icon('shield') ?><span><b>Town only</b><small>Never your address</small></span></a>
  </div>

  <nav class="mk-sec" aria-label="Marketplace sections">
    <a class="mk-st" href="<?= tv_e($url(['section', 'from', 'page'])) ?>"<?= $tabOn === '' ? ' aria-current="page"' : '' ?>><?= tv_icon('store') ?>Browse all</a>
    <?php foreach ($sections as $sk => [$sl, $si]): ?><a class="mk-st" href="<?= tv_e($url(['section', 'from', 'page'], ['section' => $sk])) ?>"<?= $tabOn === $sk ? ' aria-current="page"' : '' ?>><?= tv_icon($si) ?><?= tv_e($sl) ?><?= $sk === 'trade' ? ' <small>Coming</small>' : '' ?></a><?php endforeach; ?>
  </nav>
  <script>(function () { var n = document.querySelector('.mk-sec'), c = n && n.querySelector('[aria-current="page"]'); if (c && c.offsetLeft + c.offsetWidth > n.clientWidth) n.scrollLeft = c.offsetLeft - 8; })();   // on a phone the chosen section is in view</script>
  <?php if ($tabOn !== ''): ?><p class="mk-sd"><?= tv_e($sections[$tabOn][2]) ?>. <a class="underline text-[color:var(--tv-link)]" href="/faq.php#what-are-the-marketplace-s-four-sections">How the sections work</a></p><?php endif; ?>

  <?php if ($trade): /* Trade (B2B): coming, built in Q1 2027 (0135's note, point 3) */ ?>
  <section class="mk-empty tv-bleed" style="margin-top:1rem">
    <h2 class="mk-h2">Trade is coming</h2>
    <p style="margin:.5rem 0;color:var(--tv-ink,#3E2B1F)">A place for businesses to buy from businesses: trade accounts for verified businesses that have claimed their listing, wholesale price lists with their minimums, a directory of suppliers by region, and shared Co-Op packages. As everywhere in the Marketplace, Traversence takes 0%, and you're paid through your own payment account. It's being built for early 2027.</p>
    <p style="margin:0"><a class="underline font-semibold" href="/faq.php#what-is-trade-on-the-marketplace">What Trade will be</a> · <a class="underline font-semibold" href="<?= tv_e($url(['section', 'from', 'page'], ['section' => 'shops'])) ?>">Browse the Shops meanwhile</a></p>
  </section>
  <?php elseif (!$ready): ?><p class="mk-empty" data-tv-status="info" data-tv-label="Coming:">The Marketplace opens once its database update has run (app/migrations/2027-01-12_marketplace.sql). It runs by itself within the hour.</p><?php else: ?>
  <form class="mk-find" method="get" action="/market/" id="mk-find">
    <input class="mk-in mk-q" type="search" name="q" value="<?= tv_e($q) ?>" placeholder="Search the Marketplace" aria-label="Search the Marketplace">
    <span class="mk-pl"><input class="mk-in" type="text" id="mk-place" value="<?= tv_e($placeLabel) ?>" placeholder="Anywhere: type a town" autocomplete="off" aria-label="Near a town or area">
      <input type="hidden" name="place" id="mk-place-key" value="<?= tv_e($place) ?>"><span class="mk-list" id="mk-place-list" hidden></span></span>
    <input type="hidden" name="sort" id="mk-sort-v" value="<?= $sort !== 'new' ? tv_e($sort) : '' ?>"><input type="hidden" name="near" id="mk-near" value="<?= tv_e($near) ?>">
    <details class="mk-dd"><summary class="mk-b2"><?= tv_icon('filter') ?>Filters<?= $nFilters ? ' <span class="mk-n">' . $nFilters . '</span>' : '' ?> &#9662;</summary>
      <div class="mk-ddp">
        <label>Category<select class="mk-in" name="category"><option value="">Any</option><?php foreach (array_keys($cats) as $c): ?><option<?= $c === $cat ? ' selected' : '' ?>><?= tv_e($c) ?></option><?php endforeach; ?></select></label>
        <label>Kind<select class="mk-in" name="kind"><option value="">Every kind</option><?php foreach (ItemKinds::KINDS as $k => $l): ?><option value="<?= $k ?>"<?= $k === $kind ? ' selected' : '' ?>><?= tv_e($l[0]) ?></option><?php endforeach; ?></select></label>
        <label>From<select class="mk-in" name="from"><option value="">People and businesses</option><option value="people"<?= $from === 'people' ? ' selected' : '' ?>>People</option><option value="business"<?= $from === 'business' ? ' selected' : '' ?>>Businesses</option></select></label>
        <label>Up to ($)<input class="mk-in" type="number" name="max" min="1" step="1" value="<?= $max ?: '' ?>" placeholder="Any price"></label>
        <label class="ck"><input type="checkbox" name="free" value="1"<?= $free ? ' checked' : '' ?>> Free only</label>
        <button class="mk-b2 go" type="submit">Show</button>
      </div></details>
    <button class="mk-b2 go" type="submit">Search</button>
  </form>
  <?php if ($chips): ?><div class="mk-chips"><?php foreach ($chips as [$label, $off]): ?><a class="mk-chip" href="<?= tv_e($off) ?>" aria-label="Remove <?= tv_e($label) ?>"><?= tv_e($label) ?> <b>&times;</b></a><?php endforeach; ?></div><?php endif; ?>

  <?php if ($landing): ?>
  <nav class="mk-tiles" aria-label="Browse by category">
    <a class="mk-tile on" href="<?= tv_e($url(['category'])) ?>" aria-current="page"><?= tv_icon('store') ?>Everything</a>
    <?php foreach (array_keys($cats) as $c): ?><a class="mk-tile" href="<?= tv_e($url(['category'], ['category' => $c])) ?>"><?= tv_icon($catIcon($c)) ?><?= tv_e($c) ?></a><?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <div class="mk-gh">
    <div><h2 class="mk-h2"><?= tv_e($q !== '' ? '“' . $q . '”: ' . lcfirst($head) : $head) ?></h2><span class="mk-cnt"><?= number_format($total) ?> listing<?= $total === 1 ? '' : 's' ?></span><?php if ($keep): ?><a class="underline text-sm text-[color:var(--tv-link)]" href="/market/">Start over</a><?php endif; ?></div>
    <label class="mk-sort">Sort by <select class="mk-in" id="mk-sort" aria-label="Sort by"><?php foreach (Market::SORTS as $k => $l): ?><option value="<?= $k ?>"<?= $k === $sort ? ' selected' : '' ?>><?= tv_e($l) ?></option><?php endforeach; ?></select></label>
  </div>
  <?php if ($needTown): ?><p data-tv-status="info" data-tv-label="Nearest:">Choose a town above (or set your location at the top) to see what's nearest first. Meanwhile, here's the newest.</p><?php endif; ?>

  <?php if ($res['posts']): ?>
  <div class="mk-grid" id="mk-grid" data-total="<?= (int) $total ?>" data-page="<?= (int) $page ?>" data-per="<?= (int) $per ?>"><?php foreach ($res['posts'] as $c) echo tv_market_card($c, empty($c['biz']) && isset($saved[$c['id']]), empty($c['biz'])); ?></div>
  <?php // the pages (js/tv-pager.js); without scripts, the next page's link ?>
  <div id="mk-pg"><?php if ($res['more']): ?><div class="mk-more"><a class="mk-b2" href="<?= tv_e($url(['page'], ['page' => $res['more']])) ?>">Next page</a></div><?php endif; ?></div>
  <script src="/js/tv-pager.js?v=<?= (int) @filemtime(__DIR__ . '/../js/tv-pager.js') ?>"></script>
  <script>
    (function () {   // the lists' standard (2026-11-12): Show more adds the next page here; the pages and how many a page open those
      var g = document.getElementById('mk-grid'), host = document.getElementById('mk-pg'); if (!g || !window.TvPager) return;
      var total = +g.getAttribute('data-total'), first = +g.getAttribute('data-page') - 1, per = +g.getAttribute('data-per'), loaded = 1;
      function at(p, n) { var u = new URL(location.href); u.searchParams.set('page', p + 1); if (n === 10) u.searchParams.delete('per'); else u.searchParams.set('per', n); if (!p) u.searchParams.delete('page'); return u.toString(); }
      var pg = TvPager.mount(host, { key: 'market', top: g, grid: g,
        onMore: function () {
          pg.busy(true);
          fetch(at(first + loaded, per), { credentials: 'include' }).then(function (r) { return r.text(); }).then(function (h) {
            var ng = new DOMParser().parseFromString(h, 'text/html').getElementById('mk-grid');
            if (ng) Array.prototype.forEach.call(ng.children, function (c) { g.appendChild(document.importNode(c, true)); });
            loaded++; pg.busy(false); pg.draw({ total: total, page: first, shown: g.children.length });
          }).catch(function () { pg.busy(false); if (window.TvAsk) TvAsk.say('Couldn\u2019t load more just now. Try again.', { anchor: host, bad: true }); });
        },
        onPage: function (x) { location.href = at(x, per); },
        onPer: function () { location.href = at(0, pg.size()); } });
      // the visitor's own choice, rounded up to whole rows (Jason, 2026-11-12: "count per row and show the correct amount of cards")
      if (pg.size() !== per && !/[?&]per=/.test(location.search)) { location.replace(at(0, pg.size())); return; }
      pg.draw({ total: total, page: first, shown: g.children.length });
    })();
  </script>
  <?php else: ?>
  <?php if ($from === 'business' && !$before): /* a section of businesses' items: nothing yet is said for businesses */ ?>
  <p class="mk-empty"><?= $section === 'regional' ? 'Nothing here yet. Event tickets, stays, harvest and made-to-order items from businesses at home in our areas show here as they\'re published.' : ($keep !== ['section' => 'shops'] && $keep ? 'Nothing matches yet. Try fewer words, another town, or fewer filters.' : 'No business items published yet.') ?> <a class="underline font-semibold" href="/faq.php#can-my-business-sell-on-the-marketplace">Can my business sell here?</a></p>
  <?php else: ?>
  <p class="mk-empty"><?= $before ? 'That\'s everything.' : ($keep ? 'Nothing matches yet. Try fewer words, another town, or fewer filters.' : 'Nothing posted yet. Have something to sell, give away or offer? Be the first.') ?> <a class="underline font-semibold" href="/user/market/?new=1">Post something</a></p>
  <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?= tv_market_js() ?>
<script>
(function () {
  var inp = document.getElementById('mk-place'); if (!inp) return;
  var key = document.getElementById('mk-place-key'), list = document.getElementById('mk-place-list'), t = null, seq = 0, opts = [];
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  inp.addEventListener('input', function () {
    key.value = ''; clearTimeout(t); var q = inp.value.trim(); if (q.length < 2) { list.hidden = true; return; }
    t = setTimeout(function () { var my = ++seq;
      fetch('/api/journeys.php?places=' + encodeURIComponent(q), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
        if (my !== seq) return; opts = d.places || [];
        list.innerHTML = opts.length ? opts.map(function (o, i) { return '<button type="button" data-i="' + i + '"><span>' + esc(o.label) + '</span><small>' + esc(o.sub || '') + '</small></button>'; }).join('') : '<button type="button" disabled>No town by that name yet</button>';
        list.hidden = false; }).catch(function () {});
    }, 220);
  });
  list.addEventListener('click', function (e) { var b = e.target.closest('[data-i]'); if (!b) return; var o = opts[+b.getAttribute('data-i')]; inp.value = o.label; key.value = o.key; list.hidden = true; var fm = document.getElementById('mk-find'); fm.requestSubmit ? fm.requestSubmit() : fm.submit(); });
  document.addEventListener('click', function (e) { if (!e.target.closest('.mk-pl')) list.hidden = true; });
  document.getElementById('mk-find').addEventListener('submit', function () { if (!key.value) inp.value = '';
    Array.prototype.forEach.call(this.elements, function (el) { if (el.name && !el.value && !(el.type === 'checkbox' && el.checked)) el.disabled = true; });   // a short address: only what's set
  });
  // Sort: Nearest measures from the town above; without one, from the location set at the top (rounded to about a mile)
  var sortSel = document.getElementById('mk-sort'), sortV = document.getElementById('mk-sort-v'), nearV = document.getElementById('mk-near');
  var here = function () { try { var l = JSON.parse(sessionStorage.getItem('traversence_location') || 'null'); if (l && l.lat != null && l.lon != null) return (+l.lat).toFixed(2) + ',' + (+l.lon).toFixed(2); } catch (e) {} return ''; };
  if (sortSel) sortSel.addEventListener('change', function () {
    sortV.value = sortSel.value === 'new' ? '' : sortSel.value;
    nearV.value = sortSel.value === 'near' && !key.value ? here() : '';
    TvAsk.busy(sortSel, true); var fm = document.getElementById('mk-find'); fm.requestSubmit ? fm.requestSubmit() : fm.submit();
  });
  // arriving on Nearest (the menu's Near me) with no town and no location yet in the address: use the one set at the top
  if (sortV.value === 'near' && !key.value && !nearV.value && here() && !/[?&]near=/.test(location.search)) { nearV.value = here(); var fm = document.getElementById('mk-find'); fm.requestSubmit ? fm.requestSubmit() : fm.submit(); }
})();
</script>
<?php tv_page_end();
