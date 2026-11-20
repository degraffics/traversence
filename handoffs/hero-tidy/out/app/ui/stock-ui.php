<?php
// app/ui/stock-ui.php — how a stock photo (decisions/0143) is shown: always labelled. Its credit sits on the photo
// ("Photo: <who> on <source>", with an i; Jason, 2026-11-17), and a tap opens its details: "Stock photo", who took it, the
// licence, the source, and "Representative photo" when it doesn't show the actual place. The owner sees "Add your own photo"; once they do, their
// photo shows instead (the caller only shows stock where there's no photo of its own).
//
//   tv_stock_tag($shown, ['nudge' => '<a …>Add your own photo</a>' | '', 'at' => 'bl'|'tl'|'tr'|'in'])   the label, for
//                                     inside a position:relative box that shows the photo ('in': in the flow, for a
//                                     caption area of the caller's own); 'site' => 'https://traversence.com' where the page is on
//                                     another domain (a storefront on trvgo.us)
//   tv_stock_css()                                                    its styles, once per page (tv_stock_tag adds them)
declare(strict_types=1);

function tv_stock_tag(array $s, array $o = []): string
{
    static $n = 0; $n++;
    $e = fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $what = !empty($s['representative']) ? 'It shows the kind of place or thing, not this one itself.' : 'Stock photo of this place.';   // the heading already says Representative
    $tip = 'Stock photo' . (!empty($s['representative']) ? ' (representative)' : '') . ' · ' . $s['credit'] . ' · tap for details';
    $h = ($n === 1 ? tv_stock_css() : '')
        . '<details class="tv-stk tv-stk-' . $e($o['at'] ?? 'bl') . '" data-stock="' . (int) $s['id'] . '"><summary title="' . $e($tip) . '" aria-label="' . $e($tip) . '">'
        // the photo's credit on the photo, with an i for its details (Jason, 2026-11-17: "the by line should be shown, with maybe an
        // i icon to show more"); "Stock photo" and "Representative" are in the details and the tooltip
        . '<span class="tv-stk-by">Photo: ' . $e(trim((string) ($s['author'] ?? '')) !== '' ? $s['author'] . ' on ' . $s['source_name'] : $s['source_name']) . '</span>'
        . '<span class="tv-stk-i" aria-hidden="true">i</span></summary>'
        . '<div class="tv-stk-d"><b>' . $e($s['label']) . '</b><span>' . $e($what) . ' Nobody on Traversence took it; it\'s here until a real one is added.</span>'
        . '<span>' . $e($s['credit']) . '</span>'
        . '<span>' . ($s['licence_url'] !== '' ? '<a href="' . $e($s['licence_url']) . '" target="_blank" rel="noopener nofollow">' . $e($s['licence'] ?: 'Licence') . '</a>' : $e($s['licence']))
        . ($s['source_url'] !== '' ? ' · <a href="' . $e($s['source_url']) . '" target="_blank" rel="noopener nofollow">' . $e($s['source_name']) . ' ↗</a>' : '') . '</span>'
        . '<a href="' . $e(($o['site'] ?? '') . '/faq.php#why-do-some-photos-say-stock-photo') . '">About stock photos</a></div></details>';
    if (!empty($o['nudge'])) $h .= '<span class="tv-stk-own' . (($o['at'] ?? '') === 'in' ? ' in' : '') . '">' . $o['nudge'] . '</span>';
    return $h;
}

function tv_stock_css(): string
{
    return '<style>
.tv-stk{position:absolute;z-index:4;max-width:calc(100% - 1rem);font:600 .72rem/1.3 system-ui,sans-serif;color:var(--tv-ink,#3E2B1F)}
.tv-stk-bl{left:.5rem;bottom:.5rem}.tv-stk-tr{right:.5rem;top:.5rem}.tv-stk-tl{left:.5rem;top:.5rem}
.tv-stk summary{list-style:none;cursor:pointer;display:inline-block;background:rgba(255,251,242,.92);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:999px;padding:.12rem .25rem .12rem .55rem;white-space:nowrap;display:inline-flex;align-items:center;gap:.35rem;max-width:100%}
.tv-stk summary::-webkit-details-marker{display:none}
.tv-stk summary:focus-visible{outline:2px solid var(--tv-focus,#92400E);outline-offset:2px}
.tv-stk-by{overflow:hidden;text-overflow:ellipsis;min-width:0}
.tv-stk-i{flex:none;width:1.05rem;height:1.05rem;border-radius:999px;border:1.5px solid var(--tv-icon,#B45309);color:var(--tv-icon,#B45309);font:italic 700 .7rem/1 var(--tv-font-head,ui-serif,Georgia,serif);display:inline-grid;place-items:center}
.tv-stk[open] .tv-stk-i{background:var(--tv-sage,#8FBCA8);border-color:var(--tv-sage,#8FBCA8);color:var(--tv-umber,#3E2B1F)}
.tv-stk-d{display:grid;gap:.3rem;margin-top:.3rem;background:color-mix(in srgb,var(--tv-card-a,#FFFBF2) 80%,transparent);-webkit-backdrop-filter:blur(10px) saturate(1.15);backdrop-filter:blur(10px) saturate(1.15);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.6rem;padding:.55rem .65rem;font-weight:400;width:min(18rem,calc(100vw - 2rem));box-shadow:0 8px 20px rgba(62,43,31,.25)}
.tv-stk-tr .tv-stk-d{position:absolute;right:0}.tv-stk-tl .tv-stk-d{position:absolute;left:0}
.tv-stk-bl .tv-stk-d,.tv-stk-in .tv-stk-d{position:absolute;left:0;bottom:calc(100% + .3rem);margin:0}
.tv-stk-in{position:relative;left:auto;bottom:auto;max-width:100%}
.tv-stk-own.in{position:static}
.tv-stk-d a{color:var(--tv-link,#92400E);text-decoration:underline}
.tv-stk-own{position:absolute;z-index:4;right:.5rem;bottom:.5rem}
.tv-stk-own a,.tv-stk-own button{display:inline-block;background:var(--tv-btn);color:var(--tv-btn-text);border:0;border-radius:999px;padding:.3rem .75rem;font:700 .76rem/1.3 system-ui,sans-serif;text-decoration:none;cursor:pointer}
.tv-stk-own a:hover,.tv-stk-own button:hover{background:var(--tv-btn-hover)}
</style>';
}

/**
 * The Gallery's Stock tab (decisions/0143): the stock library inside the one tool for art, kept apart from what's yours.
 * Stock photos aren't yours and never go in your photos or albums; here you find one and use it where a photo only
 * illustrates (for a business: its storefront's cover), and see where you're using stock now. $b: a business's gallery.
 */
function tv_stock_gallery_tab(PDO $pdo, int $uid, int $b, string $q): string
{
    require_once __DIR__ . '/../lib/StockLibrary.php';
    $e = fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    if (!StockLibrary::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Coming:">Stock photos appear here once the library\'s database update has run.</p>';
    // where this gallery's owner may use stock: illustrative spots only
    $spots = [];
    if ($b) {
        require_once __DIR__ . '/../lib/Storefront.php'; require_once __DIR__ . '/../lib/Team.php';
        if (Storefront::ready($pdo) && Storefront::get($pdo, $b)) {
            $can = Team::can($pdo, $uid, $b, 'storefront', 'edit');
            $spots[] = ['slot' => 'storefront_cover', 'owner' => (string) $b, 'label' => 'Storefront cover', 'post' => '/business/api/' . $b . '/storefront', 'action' => 'stock_cover',
                'own' => (int) (Storefront::get($pdo, $b)['cover_id'] ?? 0) > 0, 'can' => $can, 'why' => $can ? '' : 'Your role can\'t change the storefront.'];
        }
    }
    $h = '<div class="stg"><p class="stg-note"><b>Stock photos aren\'t yours.</b> Nobody on Traversence took them: they come from Wikimedia Commons, the Library of Congress and Pixabay, each with its credit, and they\'re always labelled "Stock photo" where they show. '
       . 'They never go in your photos or albums. Use one only where a photo illustrates, like a storefront\'s cover; your own photo always takes its place once you add it.</p>';
    if ($spots) {
        $h .= '<h2 class="stg-h">Where you can use stock</h2><div class="stg-spots">';
        foreach ($spots as $sp) {
            $ch = StockLibrary::chosen($pdo, $sp['slot'], $sp['owner']);
            $h .= '<div class="stg-spot tv-bleed"><b>' . $e($sp['label']) . '</b><span>' . ($sp['own'] ? 'Your own photo is there, so no stock shows.' : ($ch ? 'Using a stock photo you chose.' : 'We pick a stock photo until you choose one or add your own.')) . '</span>'
                . ($ch ? '<span class="stg-im" style="background-image:url(\'' . $e($ch['card']) . '\')">' . tv_stock_tag($ch) . '</span><button type="button" class="gl-b" data-stock-clear data-slot="' . $e($sp['slot']) . '" data-owner="' . $e($sp['owner']) . '" data-action="' . $e($sp['action']) . '" data-post="' . $e($sp['post']) . '"' . ($sp['can'] ? '' : ' disabled title="' . $e($sp['why']) . '"') . '>Let us pick</button>' : '')
                . '</div>';
        }
        $h .= '</div>';
    } else {
        $h .= '<p data-tv-status="info" data-tv-label="Browse only:">' . ($b ? 'Set up a storefront and you can use a stock photo as its cover.' : 'Nothing of yours takes a stock photo: your profile, journeys and albums are for your own photos. Businesses can use stock for a storefront\'s cover.') . '</p>';
    }
    $h .= '<form class="stg-f" method="get" action="/gallery/" role="search"><input type="hidden" name="tab" value="stock">' . ($b ? '<input type="hidden" name="b" value="' . $b . '">' : '')
        . '<input type="search" name="q" value="' . $e($q) . '" placeholder="What should it show? (desert, main street, market…)" aria-label="Search stock photos"><button class="gl-b go">Search</button></form>';
    $items = StockLibrary::find($pdo, $q, '', 48);
    // the sources too: what the library hasn't got yet, found at Wikimedia Commons, the Library of Congress and Pixabay
    [$found, $notes] = $q !== '' ? StockLibrary::discover($pdo, $q, 36) : [[], []];
    if (!$items && !$found) return $h . '<p class="stg-note">' . ($q !== '' ? 'Nothing fits those words. Try other words.' . ($notes ? ' (' . $e(implode(' ', $notes)) . ')' : '') : 'Type what the photo should show, and Search: the library and Wikimedia Commons, the Library of Congress and Pixabay are searched.') . '</p></div>' . tv_stock_gallery_css();
    $h .= $items ? '<h2 class="stg-h">In the library</h2><div class="stg-g">' : '<div class="stg-g">';
    foreach ($items as $it) {
        $h .= '<div class="stg-i tv-bleed"><span class="stg-im" style="background-image:url(\'' . $e($it['card']) . '\')" role="img" aria-label="' . $e($it['title']) . '">' . tv_stock_tag($it) . '</span>'
            . '<small>' . $e($it['credit']) . '</small>';
        foreach ($spots as $sp) $h .= '<button type="button" class="gl-b" data-stock-use="' . (int) $it['id'] . '" data-slot="' . $e($sp['slot']) . '" data-owner="' . $e($sp['owner']) . '" data-action="' . $e($sp['action']) . '" data-post="' . $e($sp['post']) . '"'
            . ($sp['can'] ? '' : ' disabled title="' . $e($sp['why']) . '"') . '>Use as ' . $e(mb_strtolower($sp['label'])) . '</button>';
        $h .= '</div>';
    }
    $h .= '</div>';
    if ($found) {
        $h .= '<h2 class="stg-h">Found at ' . $e(implode(', ', array_values(array_unique(array_column($found, 'source_name'))))) . '</h2>'
            . '<p class="stg-note">Free to use, each with its credit and licence. Choosing one adds it to the stock library (a copy, labelled "Stock photo" wherever it shows).' . ($notes ? ' ' . $e(implode(' ', $notes)) : '') . '</p><div class="stg-g">';
        foreach ($found as $it) {
            $h .= '<div class="stg-i tv-bleed"><span class="stg-im" style="background-image:url(\'' . $e($it['card']) . '\')" role="img" aria-label="' . $e($it['title']) . '"></span>'
                . '<small><b>' . $e($it['source_name']) . '</b> · ' . $e($it['credit']) . ($it['licence'] !== '' ? ' · ' . $e($it['licence']) : '') . ($it['source_url'] !== '' ? ' · <a href="' . $e($it['source_url']) . '" target="_blank" rel="noopener nofollow">Its page ↗</a>' : '') . '</small>';
            foreach ($spots as $sp) $h .= '<button type="button" class="gl-b" data-stock-use="0" data-stock-adopt="' . $e($it['source']) . '" data-stock-sid="' . $e($it['source_id']) . '" data-q="' . $e($q) . '" data-slot="' . $e($sp['slot']) . '" data-owner="' . $e($sp['owner']) . '" data-action="' . $e($sp['action']) . '" data-post="' . $e($sp['post']) . '"'
                . ($sp['can'] ? '' : ' disabled title="' . $e($sp['why']) . '"') . '>Use as ' . $e(mb_strtolower($sp['label'])) . '</button>';
            $h .= '</div>';
        }
        $h .= '</div>';
    } elseif ($notes) $h .= '<p class="stg-note">' . $e(implode(' ', $notes)) . '</p>';
    return $h . '</div>' . tv_stock_gallery_css();
}

function tv_stock_gallery_css(): string
{
    return '<style>.stg-note{font-size:.9rem;color:#5C4632;line-height:1.5;margin:0 0 1rem;max-width:52rem}.stg-h{font:700 1.15rem var(--tv-font-head,Georgia,serif);color:#3E2B1F;margin:.4rem 0 .5rem}'
        . '.stg-spots{display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:.6rem;margin-bottom:1.2rem}.stg-spot{display:grid;gap:.4rem;background:#FFFBF2;border:1px solid #E5D7B6;border-radius:.7rem;padding:.7rem;font-size:.88rem;color:#5C4632}.stg-spot b{color:#3E2B1F}'
        . '.stg-f{display:flex;gap:.4rem;margin:0 0 1rem}.stg-f input{flex:1;min-width:0;border:1.5px solid #CBB98D;border-radius:999px;padding:.5rem .9rem;font-size:1rem;background:#fff}'
        . '.stg-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:.6rem}.stg-i{display:grid;gap:.4rem;align-content:start;background:#FFFBF2;border:1px solid #E5D7B6;border-radius:.7rem;padding:.5rem}'
        . '.stg-im{position:relative;display:block;aspect-ratio:16/10;border-radius:.5rem;background:#E3D3AC center/cover no-repeat}.stg-im .tv-stk summary{white-space:normal}.stg-i small{font-size:.75rem;color:#5C4632;overflow-wrap:anywhere}.stg-i small a{color:#92400E;text-decoration:underline}'
        . '@media (max-width:767px){.stg-g,.stg-spots{grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}.stg-i,.stg-spot{border-radius:.5rem}.stg-i .gl-b{font-size:.78rem;padding:.35rem .6rem}}</style>';
}
