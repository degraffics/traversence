<?php
// business/router.php — the business portal (business-portal-architecture.md; decisions/0110 wall 1, 0115):
// /business/ (your businesses), /business/<listing>/<module>. Checks your role on that listing on every request (our
// login), then opens the business realm for it (BusinessData: the listings login, that one business only) for any
// module that reads its data. The address names the business; from there on its data comes only through the gateway.
// business/.htaccess sends the clean addresses here.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');   // the bootstrap starts JSON
register_shutdown_function(function () {   // if the page stops part way, say so where it stopped (and log why), not a page cut off in silence
    $x = error_get_last();
    if (!$x || !in_array($x['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    error_log('business portal stopped: ' . $x['message'] . ' in ' . $x['file'] . ':' . $x['line']);
    echo '<div style="max-width:40rem;margin:1rem auto;padding:.8rem 1rem;border:1px solid #E9B4B4;border-radius:.6rem;background:#FFF5F5;color:#7F1D1D;font:500 .9rem system-ui,sans-serif">'
        . 'This page stopped part way (' . htmlspecialchars(basename((string) $x['file'])) . ', line ' . (int) $x['line'] . '). Reload it; if it keeps happening, tell us.</div>';
});
require_once __DIR__ . '/../../app/business/portal.php';
require_once __DIR__ . '/../../app/lib/BusinessData.php';

$me = Auth::currentUser();
if ($me === null) { header('Location: /login.php?next=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/business/'))); exit; }
$uid = (int) $me['id'];
$main = Database::connection();
$parts = array_values(array_filter(explode('/', trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/')), 'strlen'));
$id = (int) ($parts[1] ?? 0);
$mk = (string) ($parts[2] ?? '') ?: 'dashboard';
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/admin/widgets.php';   // tv_w_e()
$e = 'tv_w_e';

// ---- /business/: the businesses you're on the team of ------------------------------------------------------------
if (!$id) {
    $mine = [];
    try {
        $mine = array_values(Team::listingsOf($main, $uid));
    } catch (Throwable) {}
    // straight to a business when it's clear which: the one you're acting as, or your only one
    require_once __DIR__ . '/../../app/lib/Roles.php';
    $cur = (string) (Roles::current($main, $uid)['key'] ?? '');
    $ids = array_map(fn ($b) => (int) $b['id'], $mine);
    $to = str_starts_with($cur, 'entity:') && in_array((int) substr($cur, 7), $ids, true) ? (int) substr($cur, 7) : (count($ids) === 1 ? $ids[0] : 0);
    if ($to && empty($_GET['all'])) { header('Location: ' . tv_biz_url($to), true, 302); exit; }
    tv_page_start('Your businesses', ['no_crumbs' => true, 'description' => 'The businesses you help run on Traversence.']);
    require __DIR__ . '/../../app/admin/kit-css.php';
    echo '<header class="ak-head"><p class="ak-mod">Business portal</p><h1 class="ak-h1">Your businesses</h1></header>';
    if (!$mine) echo '<div class="ak-card tv-bleed"><p>You\'re not on a business\'s team yet. <a class="underline" href="/claim.php">Claim or add your business</a>, or ask its owner to add you.</p></div>';
    foreach ($mine as $b) echo '<a class="ak-card tv-bleed bz-pick" href="' . $e(tv_biz_url((int) $b['id'])) . '"><b>' . $e($b['name']) . '</b><span>' . $e(Team::roleName($b['role'])) . ' · ' . $e(Plan::TIERS[Plan::tier((string) $b['plan_tier'])]) . '</span></a>';
    echo '<style>.bz-pick{display:flex;justify-content:space-between;gap:.5rem;align-items:center;text-decoration:none;color:var(--tv-ink,#3E2B1F)}.bz-pick b{color:var(--tv-link,#92400E);text-decoration:underline}.bz-pick span{color:var(--tv-meta,#8A7355);font-size:.9rem}</style>';
    tv_page_end(); exit;
}

// ---- one business: your role on it, every request ------------------------------------------------------------------
$ctx = tv_biz_context($main, $uid, $id);
if (!$ctx) {
    error_log('SECURITY business portal: user ' . $uid . ' opened business ' . $id . ' without a role on it');
    http_response_code(403);
    tv_page_start('Not one of your businesses', ['no_crumbs' => true]);
    require __DIR__ . '/../../app/admin/kit-css.php';
    echo '<div class="ak-card tv-bleed" style="max-width:34rem;margin:1rem auto"><h1 class="ak-h">This isn\'t one of your businesses</h1><p>Only its team can open it. If you help run it, ask its owner to add you. <a class="underline" href="/business/">Your businesses</a></p></div>';
    tv_page_end(); exit;
}
$mods = tv_biz_modules();
if (!isset($mods[$mk])) { header('Location: ' . tv_biz_url($id), true, 302); exit; }
$m = $mods[$mk];
[$ok, $why, $kind] = tv_biz_can($main, $ctx, $mk);
// a Marketplace order's dispute packet (decisions/0141 §6): one file, for the team who can see its orders
if ($mk === 'checkout' && isset($_GET['packet'])) {
    require_once __DIR__ . '/../../app/lib/MarketCheckout.php';
    $po = $ok && MarketCheckout::ready($main) ? MarketCheckout::get($main, (int) $_GET['packet']) : null;
    if (!$po || (int) $po['entity_id'] !== $id) { http_response_code($ok ? 404 : 403); header('Content-Type: text/plain; charset=utf-8'); echo $ok ? 'That order isn\'t one of this business\'s.' : $why; exit; }
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="order-' . (int) $po['id'] . '-dispute-packet.html"');
    echo MarketCheckout::packet($main, $po); exit;
}

// the menu: every module; what your plan or role doesn't open says so (greyed, with the reason)
$rail = tv_biz_rail($main, $id, $uid, false, $ctx['name']);   // the same menu as the listing's dashboard
// you're acting as this business while you're in it, so the account menu and the marker say so
require_once __DIR__ . '/../../app/lib/Roles.php';
try { if ((Roles::current($main, $uid)['key'] ?? '') !== 'entity:' . $id) Roles::setCurrent($main, $uid, 'entity:' . $id); } catch (Throwable) {}
$in360 = $mk === 'market' || ($m['in'] ?? '') === 'market';   // Market 360°: one button in the menu, lit for all its sections
tv_page_start($m['label'] . ' · ' . $ctx['name'], ['no_crumbs' => true, 'rail' => $rail, 'rail_label' => $ctx['name'], 'active' => $in360 ? 'market' : $mk, 'description' => 'Business portal: ' . $ctx['name'] . '.']);
require __DIR__ . '/../../app/admin/kit-css.php';
?>
<style>
  .ta-i.bz-off{opacity:.55}
  .bz-pill{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.12rem .6rem;font:600 .78rem system-ui,sans-serif;background:#F3E7CC;color:var(--tv-meta,#5C4632);border:1px solid var(--tv-line,#EADFC4)}
  .bz-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(15rem,100%),1fr));gap:.5rem;margin-top:.6rem}
  .bz-tile{display:block;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line,#EADFC4);border-radius:.8rem;padding:.7rem .85rem;text-decoration:none;color:var(--tv-ink,#3E2B1F)}
  .bz-tile b{display:block;color:var(--tv-link,#92400E);text-decoration:underline}.bz-tile small{display:block;color:var(--tv-meta,#8A7355);margin-top:.15rem;line-height:1.35}
  .bz-tile.off{opacity:.6}.bz-tile.off b{color:var(--tv-meta,#5C4632);text-decoration:none}
  .bz-tbl{width:100%;border-collapse:collapse;font-size:.9rem}.bz-tbl td,.bz-tbl th{padding:.4rem .3rem;border-top:1px solid var(--tv-line,#EADFC4);text-align:left}.bz-tbl th{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--tv-meta,#8A7355)}
  .bz-yes{color:#1F5C43;font-weight:700}.bz-no{color:var(--tv-meta,#8A7355)}
  @media (max-width:767px){.ak-top{position:relative}.ak-top .ak-dd{position:static}.ak-top .ak-ddl{left:0;right:0;width:auto}}   /* Market 360°'s dropdown: the header's full width on a phone, never off the edge */
</style>
<?php
echo '<header class="ak-head"><p class="ak-mod">' . ($in360 ? 'Market 360° · ' : '') . $e($ctx['name']) . ' · <span class="bz-pill">' . $e(Team::roleName($ctx['role'])) . '</span> <span class="bz-pill">' . $e(Plan::TIERS[$ctx['tier']]) . '</span></p>'
    . '<div class="ak-top"><h1 class="ak-h1">' . $e($mk === 'market' ? 'Get selling' : $m['label']) . '</h1>' . ($in360 ? (function () use ($main, $ctx, $id, $mk, $mods, $e) {
        // Market 360°'s sections, like the admin's section dropdown: Get selling, then the six modules (decisions/0141 §1);
        // a page not built yet is listed greyed, its tooltip saying what it will do
        $dd = '<details class="ak-dd"><summary>' . tv_icon('cart', 'ak-svg') . '<span>Market 360°</span></summary><nav class="ak-ddl" aria-label="Market 360° sections">';
        foreach (tv_biz_m360_sections() as $part => $keys) {
            if ($part !== '') $dd .= '<b style="display:block;font-size:.66rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-meta,#8A7355);padding:.55rem .7rem .2rem">' . $e($part) . '</b>';
            foreach ($keys as $k) {
                if (!isset($mods[$k])) continue;
                [$o, $w, $why] = tv_biz_can($main, $ctx, $k);
                $tip = $why === 'coming' ? 'Coming: ' . (string) ($mods[$k]['about'] ?? '') : $w;
                $dd .= '<a href="' . $e(tv_biz_url($id, $k)) . '"' . ($k === $mk ? ' class="on" aria-current="page"' : ($o ? '' : ' class="off" title="' . $e($tip) . '"')) . '>' . tv_icon($mods[$k]['icon'], 'ak-svg')
                    . '<span>' . $e($k === 'market' ? 'Get selling' : $mods[$k]['label']) . '</span>' . ($why === 'coming' ? '<small style="margin-left:auto;font-size:.68rem;color:var(--tv-meta,#8A7355)">coming</small>' : '') . '</a>';
            }
        }
        return $dd . '</nav></details>';
    })() : '') . '</div></header>';

if ($ctx['role'] === 'white_glove') echo '<div class="ak-card tv-bleed" style="background:#E8F1EC;border-color:var(--tv-sage,#8FBCA8)"><p><b>You\'re in as White Glove</b> (standing access through its plan). You can do only what the White Glove services cover (<a class="underline" href="/terms.php#white-glove" target="_blank">Terms</a>); every change is logged, and the owner sees that you have access.</p></div>';
$plansUrl = '/plans.php?listing=' . $id;
if (!$ok) {
    echo '<div class="ak-card ak-off tv-bleed" style="max-width:40rem"><p><b>' . $e($why) . '</b>' . ($kind === 'plan' ? ': <a class="underline" href="' . $e($plansUrl) . '">see plans</a>' : '') . '</p>'
        . (!empty($m['about']) ? '<p class="ak-note">' . $e($m['about']) . '</p>' : '')
        . ($kind === 'coming' ? '<p class="ak-note">It\'s coming to the business portal. Meanwhile, your listing, claims and plan are on <a class="underline" href="/listing/businessportal.php?id=' . $id . '">your business dashboard</a>.</p>' : '') . '</div>';
    tv_page_end(); exit;
}

// a module that works on the business's own data opens the business realm: from here its tools see that business only
if (!empty($m['tool']) || ($mk === 'dashboard' && tv_biz_can($main, $ctx, 'customers')[0])) {
    try { BusinessData::open($id); Scope::setPeople($ctx['people']); } catch (Throwable $x) {
        error_log('business portal: ' . $x->getMessage());
        echo '<div class="ak-card ak-off tv-bleed"><p>Your business\'s data can\'t be reached right now. Try again in a minute.</p></div>'; tv_page_end(); exit;
    }
}

if ($mk === 'dashboard') {
    echo tv_biz_glance($main, $id, $ctx['name']);   // the listing at a glance (0115 addendum)
    // the business's own front page (Jason, 2026-11-11: "The dashboard here needs the pulse and the whats on your mind, and any
    // other tools scattered around the business backend"): post as the business, its Pulse, and its messages and offers,
    // here rather than on the personal Dashboard (?in=pulse, ?in=business), then its numbers and every tool
    require_once __DIR__ . '/../../app/lib/MemberPosts.php';
    if (MemberPosts::ready($main) && MemberPosts::canPostAs($main, $uid, $id)) {   // "What's on your mind?" as the business (decisions/0153), its posts under it
        echo '<section id="posts" class="bz-sec"><div id="tvm-bcomp" style="margin:0 0 .6rem"></div><div id="tvm-bfeed"></div></section>'
            . '<script src="/js/tv-moments.js?v=' . (int) @filemtime(__DIR__ . '/../js/tv-moments.js') . '"></script><script>'
            . 'TvMoments.composer(document.getElementById("tvm-bcomp"), ' . json_encode(['name' => $ctx['name'], 'as' => $id], JSON_HEX_TAG | JSON_HEX_AMP) . ');'
            . 'TvMoments.feed(document.getElementById("tvm-bfeed"), ' . json_encode(['business' => $id, 'self' => true, 'hideEmpty' => true]) . ');</script>';
    }
    // its Pulse (decisions/0055 §5): what's coming in to the business, the same list as on the Dashboard (app/ui/pulse-panel.php)
    require_once __DIR__ . '/../../app/ui/pulse-panel.php';
    echo tv_pulse_css() . '<section id="pulse" class="bz-sec pu">' . tv_pulse_head(['label' => $ctx['name']])
        . tv_pulse_list(['me' => $ctx['name']]) . '</section>'
        . '<script src="/js/tv-pulse.js?v=' . (int) @filemtime(__DIR__ . '/../js/tv-pulse.js') . '"></script><script>window.TvPulse && TvPulse.load();</script>';
    // messages and offers (decisions/0047 §3): send an offer or news, ask people again, conversations, guest questions
    echo '<section id="messages" class="ak-card tv-bleed bz-sec bz-card bz" data-e="' . $id . '"><h2 class="ak-h" style="margin-top:0">Messages &amp; offers</h2>'
        . '<div class="bz-body text-sm mt-2">Loading…</div></section>'   // business-messages.php checks the team on each request
        . '<style>.bz-sec{margin:0 0 1rem;scroll-margin-top:5rem}' . <<<'CSS'
.bz-card .ab-in{width:100%;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.6rem;padding:.45rem .6rem;font-size:.9rem}.bz-card .ab-b{font-size:.8rem;font-weight:600;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#FFFBF2);color:var(--tv-ink,#3E2B1F);border-radius:.55rem;padding:.3rem .7rem;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block}.bz-card .ab-b.go{background:#15803D;border-color:#15803D;color:#fff}.bz-card .ab-b.no{color:var(--tv-crimson,#991B1B);border-color:#FCA5A5}.bz-card .ab-acts{display:inline-flex;gap:.3rem;flex-wrap:wrap}.bz-card .ab-chk{display:flex;align-items:center;gap:.4rem;font-size:.85rem;margin-top:.25rem}.bz-card .ab-lbl{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--tv-meta,#8A7355);margin-top:.6rem}.bz-card .ab-thread{display:block;width:100%;text-align:left;background:var(--tv-card-a,#FFFBF2);border:1px solid rgba(203,185,141,.6);border-radius:.7rem;padding:.55rem .7rem;margin-top:.45rem;cursor:pointer}.bz-card .ab-thread small{display:block;color:var(--tv-meta,#8A7355);font-size:.75rem;margin-top:.1rem}.bz-card .ab-convo-h{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.7rem}.bz-card .ab-convo{max-height:50vh;overflow-y:auto;margin-top:.6rem;display:flex;flex-direction:column;gap:.4rem}.bz-card .ab-msg{max-width:85%;background:var(--tv-card-a,#FFFBF2);border:1px solid rgba(203,185,141,.6);border-radius:.8rem;padding:.45rem .65rem;font-size:.9rem;align-self:flex-start}.bz-card .ab-msg.me{align-self:flex-end;background:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2);border-color:var(--tv-umber,#3E2B1F)}.bz-card .ab-msg small{display:block;font-size:.68rem;opacity:.7;margin-top:.15rem}.bz-card .bz-type{display:inline-block;font-size:.66rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase;padding:.05rem .4rem;border-radius:999px;border:1px solid currentColor;opacity:.8;margin-bottom:.2rem}.bz-card .bz-mutes{display:flex;flex-wrap:wrap;gap:.35rem .8rem;margin-top:.3rem}.bz-card .bz-rep{background:none;border:0;padding:0;font-size:.68rem;text-decoration:underline;opacity:.7;cursor:pointer;color:inherit;margin-left:.4rem}.bz-card .ab-send{display:flex;gap:.4rem;align-items:flex-end;margin-top:.6rem}.bz-card .ah-n{display:inline-block;min-width:1.4rem;text-align:center;background:#D97706;color:#fff;border-radius:999px;font-size:.72rem;font-weight:700;padding:0 .35rem;margin-right:.35rem}
CSS
        . '</style><script src="/js/business-inbox.js?v=' . (int) @filemtime(__DIR__ . '/../js/business-inbox.js') . '" defer></script>';
    require_once __DIR__ . '/../../app/lib/Customers.php';
    $n = Scope::isBusiness() ? count(Customers::all($main)) : null;
    echo '<div class="ak-metrics">'
        . ($n !== null ? '<a class="ak-metric tv-bleed" href="' . $e(tv_biz_url($id, 'customers')) . '"><b>' . $n . '</b><span>Customers</span></a>' : '')
        . '<div class="ak-metric tv-bleed"><b>' . count($ctx['team']) . '</b><span>On the team</span></div>'
        . '<a class="ak-metric tv-bleed" href="' . $e($plansUrl) . '"><b style="font-size:1.05rem">' . $e(Plan::TIERS[$ctx['tier']]) . '</b><span>Your plan</span></a>'
        . (function () use ($main, $id, $e, $plansUrl) {   // storage used of the plan's (decisions/0120)
            try { if (!Scope::isBusiness()) BusinessData::open($id); require_once __DIR__ . '/../../app/lib/BizFiles.php'; $u = BizFiles::used($main); $c = BizFiles::allowance($main, $id);
                return '<a class="ak-metric tv-bleed" href="' . $e($plansUrl) . '"><b style="font-size:1.05rem">' . $e(BizFiles::size($u)) . '</b><span>Storage used of ' . $e(BizFiles::size($c)) . '</span></a>'; }
            catch (Throwable $x) {   // said, never dropped: the tile stays with its label and why (words follow the state), the cause logged
                error_log('biz storage tile (listing ' . (int) $id . '): ' . $x->getMessage());
                return '<a class="ak-metric tv-bleed" href="' . $e($plansUrl) . '"><b style="font-size:1.05rem">—</b><span>Storage used: figures unavailable right now</span></a>'; }
        })() . (function () use ($main, $id, $e) {   // what visitors did from the listing, last 30 days (decisions/0058 §9): counts only, never who
            try { require_once __DIR__ . '/../../app/lib/ListingActions.php'; require_once __DIR__ . '/../../app/lib/PlatformCounts.php';
                if (!ListingActions::ready($main)) return '';
                $h = '<div class="ak-metric tv-bleed"><b>' . (int) PlatformCounts::total($main, 'view', 30, 'listing:' . $id)['visits'] . '</b><span>Visits to your listing (30 days)</span></div>';
                foreach (ListingActions::summary($main, $id, 30) as $k => $n) if (isset(ListingActions::ACTIONS[$k])) $h .= '<div class="ak-metric tv-bleed"><b>' . (int) $n . '</b><span>' . $e(ListingActions::ACTIONS[$k]) . ' (30 days)</span></div>';
                return $h; } catch (Throwable $x) { error_log('biz overview counts: ' . $x->getMessage()); return ''; }
        })() . '</div>';
    echo '<h2 class="ak-h">Your tools</h2><div class="bz-grid">';
    foreach ([['/listing/businessportal.php?id=' . $id, 'Edit listing', 'Your details, hours, photos and claims.'], ['/gallery/?b=' . $id, 'Gallery', 'Your photos, videos and albums, and kept posts.'],
              ['/user/dashboard.php?in=team#team', 'Team & roles', 'Who helps run it, and what each can do.']] as [$tu, $tl, $tw])
        echo '<a class="bz-tile tv-bleed" href="' . $e($tu) . '"><b>' . $e($tl) . '</b><small>' . $e($tw) . '</small></a>';
    foreach ($mods as $k => $x) {
        if ($k === 'dashboard' || ($x['in'] ?? '') === 'market') continue;   // Market 360°'s sections open from its own tile
        [$o, $w, $kd] = tv_biz_can($main, $ctx, $k);
        echo '<a class="bz-tile tv-bleed' . ($o ? '' : ' off') . '" href="' . $e(tv_biz_url($id, $k)) . '"><b>' . $e($x['label']) . '</b><small>' . $e($o ? ($x['about'] ?? 'Open') : $w) . '</small></a>';
    }
    echo '</div>';
} elseif ($mk === 'quotes' && isset($_GET['files'])) {
    // ---- a quote's proofs and files (BizFiles, decisions/0120): versions the customer approves, files shared or kept
    require_once __DIR__ . '/../../app/lib/BizFiles.php'; require_once __DIR__ . '/../../app/lib/BizQuotes.php';   // (BizFiles brings S3)
    $qid = (int) $_GET['files']; $q = BizQuotes::get($main, $qid);
    [$canEd] = tv_biz_can($main, $ctx, 'quotes', 'edit');
    $flash = $_SESSION['tv_biz_flash'] ?? null; unset($_SESSION['tv_biz_flash']);
    echo '<p><a class="underline" href="' . $e(tv_biz_url($id, 'quotes')) . '#quotes:' . $qid . '">&larr; Back to the quote</a></p>';
    if (!$q) { echo '<div class="ak-card tv-bleed"><p>That quote isn\'t here.</p></div>'; tv_page_end(); exit; }
    $more = '/plans.php?listing=' . $id . '#packs';   // more storage: a pack, or a bigger plan
    if ($flash) echo '<div class="ak-card tv-bleed" role="status" style="background:' . ($flash[0] === 'ok' ? '#DCEFE4' : '#F6DADA') . '"><p><b>' . $e($flash[1]) . '</b></p>' . (!empty($flash[2]) ? '<p><a class="ak-b go" href="' . $e($flash[2]) . '">Add storage</a></p>' : '') . '</div>';
    $used = BizFiles::used($main); $cap = BizFiles::allowance($main, $id); $pct = $cap ? min(100, round(100 * $used / $cap, 1)) : 100;
    echo '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">' . $e(BizQuotes::ref($q) . ' · ' . ($q['cust_name'] ?: $q['cust_email'])) . '</h2><p class="ak-note">Proofs are versions your customer approves, or asks you to change, on their quote page. Files are shared with them there, or kept for your team. '
        . 'Your storage: <b>' . $e(BizFiles::size($used)) . '</b> of ' . $e(BizFiles::size($cap)) . ' used.</p><div class="bz-bar' . ($pct >= 80 ? ' hi' : '') . '"><span style="width:' . $pct . '%"></span></div>'
        . '<p class="ak-note" style="margin-top:.4rem">' . ($pct >= 80 ? '<b>You\'re running low on storage.</b> ' : '') . '<a class="' . ($pct >= 80 ? 'ak-b go' : 'underline') . '" href="' . $e($more) . '">Add storage</a> with a storage pack or a bigger plan.</p></div>';
    $all = BizFiles::forQuote($main, $qid);
    $url = fn ($f) => '/business/files.php?b=' . $id . '&f=' . (int) $f['id'];
    $row = fn ($f) => '<div class="bz-tm"><div><a class="underline" href="' . $e($url($f)) . '" target="_blank" rel="noopener">' . $e($f['file_name']) . '</a><small>' . $e(BizFiles::size((int) $f['bytes']))
        . ($f['kind'] === 'file' ? ' · ' . ((int) $f['shared'] ? 'shared with the customer' : 'for your team') : '') . ($f['note'] !== '' ? ' · ' . $e($f['note']) : '') . '</small></div>'
        . ($canEd && !($f['kind'] === 'proof' && in_array($f['status'], ['approved', 'changes'], true)) ? '<div class="bz-tma"><button type="button" class="ak-b no" data-file-remove="' . (int) $f['id'] . '">Remove</button></div>' : '') . '</div>';
    $vers = [];
    foreach ($all as $f) if ($f['kind'] === 'proof') $vers[(int) $f['version']][] = $f;
    echo '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Proofs</h2>';
    if (!$vers) echo '<p class="ak-note">None yet.</p>';
    foreach ($vers as $v => $fs) { $st = (string) $fs[0]['status']; echo '<p style="margin:.6rem 0 .1rem"><b>Version ' . $v . '</b> <span class="bz-pill">' . $e(BizFiles::STATUS[$st] ?? $st) . '</span>' . ($fs[0]['decided_note'] !== '' ? '<br><small>“' . $e($fs[0]['decided_note']) . '”</small>' : '') . '</p>' . implode('', array_map($row, $fs)); }
    echo '</div><div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Files</h2>';
    $plain = array_values(array_filter($all, fn ($f) => $f['kind'] === 'file'));
    echo $plain ? implode('', array_map($row, $plain)) : '<p class="ak-note">None yet.</p>';
    echo '</div>';
    if ($canEd && !in_array($q['status'], ['void', 'declined'], true)) {
        $tok = (string) ($_SESSION['csrf_token'] ?? '');
        echo '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">Add</h2><form method="post" action="/business/files.php" enctype="multipart/form-data" class="bz-up" id="bz-upf"'
            . ' data-cap="' . $cap . '" data-used="' . $used . '" data-addstorage="' . $e($more) . '">'
            . '<input type="hidden" name="csrf" value="' . $e($tok) . '"><input type="hidden" name="b" value="' . $id . '"><input type="hidden" name="quote" value="' . $qid . '">'
            . '<label>What<select class="ak-in" name="kind"><option value="proof">A new proof version (the customer approves it)</option><option value="file">Files</option></select></label>'
            . '<label class="bz-sh"><input type="checkbox" name="shared" value="1" checked> Files: share them with the customer (proofs always are)</label>'
            . '<label>Files<input class="ak-in" type="file" name="files[]" multiple required></label>'
            . '<label>Note (optional)<input class="ak-in" name="note" maxlength="300"></label><div><button class="ak-b go">Upload</button></div>'
            . '</form>'
            . '<p class="ak-note">Proofs: PDF, images or video (MP4, MOV, WebM). Files: also Word, Excel, PowerPoint, text, CSV or ZIP. Up to 1 GB a file (raw video included): over 10 MB asks first and takes a while, so keep the page open. Everything counts toward your storage.</p></div>'
            . '<script>' . <<<'JS'
(function () {   // the site's one uploader (js/tv-upload.js, decisions/0127): straight to S3, up to 1 GB, size checked first, a progress bar
  var f = document.getElementById('bz-upf'); if (!f || !window.TvUpload) return;
  f.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var inp = f.querySelector('input[type=file]'), files = Array.prototype.slice.call(inp.files || []); if (!files.length) return;
    var btn = f.querySelector('button'); btn.disabled = true;
    TvUpload.send(files, { target: 'biz_file', el: f, batch: f.kind.value === 'proof',
      ctx: { b: f.b.value, quote: f.quote.value, kind: f.kind.value, shared: f.shared.checked ? '1' : '', note: f.note.value } })
      .then(function (res) { TvUpload.reloadHere(TvUpload.summary(res), f); }, function () { btn.disabled = false; inp.value = ''; });
  });
})();
JS . '</script>';
    }
    echo '<style>.bz-bar{height:8px;border-radius:999px;background:var(--tv-sec,#EADFC4);overflow:hidden;margin-top:.4rem}.bz-bar span{display:block;height:100%;background:#15803D}.bz-bar.hi span{background:#B45309}.bz-err{background:#F6DADA;color:#7F1D1D;border-radius:.6rem;padding:.5rem .7rem}.bz-err p{margin:0 0 .4rem}'
        . '.bz-tm{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.4rem 0;border-top:1px solid var(--tv-line,#EADFC4)}.bz-tm small{display:block;color:var(--tv-meta,#8A7355);font-size:.78rem}.bz-tm a{word-break:break-all}'
        . '.bz-up{display:grid;gap:.5rem}.bz-up label{display:flex;flex-direction:column;gap:.2rem;font-size:.85rem;color:var(--tv-meta,#5C4632)}.bz-up .bz-sh{flex-direction:row;align-items:center;gap:.4rem}</style>'
        . '<script>document.addEventListener("click",async function(e){var b=e.target.closest("[data-file-remove]");if(!b)return;if(!TvAsk.gate(b,"Remove this file? It frees its storage.",{yes:"Remove",danger:true}))return;'
        . 'var s=await (await fetch("/api/auth/session.php",{credentials:"include"})).json();var r=await (await fetch("/business/files.php",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":s.csrf_token},body:JSON.stringify({b:' . $id . ',remove:+b.getAttribute("data-file-remove")})})).json();'
        . 'if(r.ok)location.reload();else TvAsk.say((r.error&&r.error.message)||"That didn\'t work.",{anchor:b});});</script>';
} elseif (!empty($m['tool'])) {
    // one of our tools, scoped to this business (decisions/0115): the same list, drawer and actions as in our admin
    $tools = require __DIR__ . '/../../app/admin/tools.php';
    $t = $tools[$m['tool']];
    $bpdo = Scope::pdo($main);
    [$canEdit] = tv_biz_can($main, $ctx, $mk, 'edit');
    $hide = (array) ($t['business_hide'] ?? []);
    $filters = is_callable($t['filters'] ?? null) ? ($t['filters'])($bpdo) : ($t['filters'] ?? []);
    $fields = is_callable($t['create']['fields'] ?? null) ? ($t['create']['fields'])($bpdo) : ($t['create']['fields'] ?? []);
    $fields = tv_w_fields($fields);
    $cfg = ['tool' => $mk, 'title' => $m['label'], 'columns' => array_values(array_filter($t['columns'], fn ($c) => !in_array($c[0], $hide, true))),
        'filters' => array_map(fn ($f) => [$f[0], $f[1], array_map(fn ($k, $v) => [(string) $k, $v], array_keys($f[2]), $f[2])], $filters), 'stages' => [], 'pipeline' => false, 'open' => 'detail',
        'create' => !empty($t['create']['do']) ? ['label' => $t['create']['label'], 'page' => '', 'fields' => $fields, 'allowed' => [$canEdit, $canEdit ? '' : 'Your role can see ' . strtolower($m['label']) . ' but not change it']] : null];
    if (!empty($t['metrics'])) {
        echo '<div class="ak-metrics">';
        foreach (($t['metrics'])($bpdo) as [$label, $value]) echo '<div class="ak-metric tv-bleed"><b>' . $e((string) $value) . '</b><span>' . $e($label) . '</span></div>';
        echo '</div>';
    }
    echo '<section class="ak-list" data-ak-table="' . $e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"><div class="ak-load">Loading…</div></section>';
    echo '<p class="ak-note">' . $e(['customers' => 'Your customers are yours alone: no other business, and no one outside your team, can see them. Orders join them here when checkout opens.',
        'catalog' => 'Your cost, markup and supplier stay private to your team; customers see only the name, description, price and options.',
        'quotes' => 'Start one with New quote, add its lines and terms in its card, then Send: your customer gets a private link to approve it, and it becomes the invoice. Record payments as they arrive (cash, check, Zelle, your own pay link). Payments go to you, never through Traversence.',
        'discounts' => 'Make a code with New code. Your customer types it on their quote page before approving (or you add it to a quote); it counts as used when the quote is approved. Turn a code off any time.',
        'taxes' => 'Add a rate for each place you collect tax (a whole state, a city or ZIP codes). Choose one on a quote, or set your usual rate in Plan & settings.',
        'shipping' => 'The ways you deliver, with their costs. Add a line for shipping on a quote using these.',
        'orders' => 'Every approved quote is an order here. Move it on as you work; Ready and Shipped email your customer.'][$mk] ?? '') . '</p>';
    echo '<script>window.AK_API = ' . json_encode('/business/api/' . $id . '/') . ';</script>';
} elseif ($mk === 'marketplace') {
    // ---- the Marketplace (decisions/0135 step 3): publish catalog items, their photos, what buyers can do, offers
    require_once __DIR__ . '/../../app/business/marketplace.php';
    echo tv_biz_marketplace($main, $ctx, $uid);
} elseif ($mk === 'domains') {
    // ---- Domains (decisions/0169 §1a): the business's own domains and its wallet
    require_once __DIR__ . '/../../app/business/domains.php';
    echo tv_biz_domains($main, $ctx, $uid);
} elseif ($mk === 'storefront') {
    // ---- Storefront & pages (decisions/0139, the Marketplace's step 5a)
    require_once __DIR__ . '/../../app/business/storefront.php';
    echo tv_biz_storefront($main, $ctx, $uid);
} elseif ($mk === 'market') {
    // ---- Market 360° (decisions/0140): selling in one place, Get selling then Run your store
    require_once __DIR__ . '/../../app/business/market360.php';
    echo tv_biz_market360($main, $ctx, $uid);
} elseif ($mk === 'trust') {
    // ---- Trust score (decisions/0141 §3): the checks a business has done, each with the button that does it
    require_once __DIR__ . '/../../app/business/trust.php';
    echo tv_biz_trust($main, $ctx, $uid);
} elseif ($mk === 'linkers') {
    // ---- Linked people (decisions/0141 §2.6): names, contacts only as each shares them, live; no export
    require_once __DIR__ . '/../../app/business/trust.php';
    echo tv_biz_linkers($main, $ctx, $uid);
} elseif ($mk === 'policies') {
    // ---- Store policies (decisions/0142): returns, cancellations, deposits, house rules, and the box buyers see
    require_once __DIR__ . '/../../app/business/policies.php';
    echo tv_biz_policies($main, $ctx, $uid);
} elseif ($mk === 'checkout') {
    // ---- E-commerce setup (decisions/0135 step 4): getting paid, tax, shipping and pickup, going live, its orders
    require_once __DIR__ . '/../../app/business/checkout.php';
    echo tv_biz_checkout($main, $ctx, $uid);
} elseif ($mk === 'settings') {
    // ---- the plan first: what it is, storage, and where to change it or add storage (the Plans page, decisions/0108)
    $su = ''; try { if (!Scope::isBusiness()) BusinessData::open($id); require_once __DIR__ . '/../../app/lib/BizFiles.php'; $su = BizFiles::size(BizFiles::used($main)) . ' of ' . BizFiles::size(BizFiles::allowance($main, $id)) . ' storage used'; } catch (Throwable) {}
    echo '<div class="ak-card tv-bleed" id="plan"><h2 class="ak-h" style="margin-top:0">Your plan: ' . $e(Plan::TIERS[$ctx['tier']]) . '</h2>'
        . ($su !== '' ? '<p class="ak-note" style="margin:.1rem 0 .6rem">' . $e($su) . '.</p>' : '')
        . '<div style="display:flex;flex-wrap:wrap;gap:.45rem"><a class="ak-b go" href="' . $e($plansUrl) . '">' . ($ctx['tier'] === 'free' ? 'Choose a plan' : 'Change plan') . '</a><a class="ak-b" href="' . $e($plansUrl) . '#packs">Add storage</a><a class="ak-b" href="#features">What each plan includes</a></div></div>';
    // ---- the usual terms for quotes (decisions/0119): new quotes start from them
    [$qok] = tv_biz_can($main, $ctx, 'quotes', 'view'); [$qed] = tv_biz_can($main, $ctx, 'quotes', 'edit');
    if ($qok) {
        try {
            BusinessData::open($id); require_once __DIR__ . '/../../app/lib/BizQuotes.php'; require_once __DIR__ . '/../../app/lib/Commerce.php';
            $qd = BizQuotes::defaults($main); $rates = array_values(array_filter(Commerce::rates($main), fn ($x) => (int) $x['active']));
            $pct = $qd['tax_milli'] ? rtrim(rtrim(number_format($qd['tax_milli'] / 1000, 3, '.', ''), '0'), '.') : '';
            $dis = $qed ? '' : ' disabled';
            echo '<div class="ak-card tv-bleed" id="quote-terms"><h2 class="ak-h" style="margin-top:0">Quotes & invoices: your usual terms</h2><p class="ak-note">New quotes start with these; change any of them on a quote.' . ($qd['set'] ? '' : ' Until you save them, a new quote copies the last one\'s.') . '</p>'
                . '<form class="bz-qd" id="bz-qd"><label>When it\'s due<select class="ak-in" name="terms"' . $dis . '>' . implode('', array_map(fn ($k, $v) => '<option value="' . $k . '"' . ($k === $qd['terms'] ? ' selected' : '') . '>' . $e($v[0]) . '</option>', array_keys(BizQuotes::TERMS), BizQuotes::TERMS)) . '</select></label>'
                . '<label>Deposit on approval (%)<input class="ak-in" name="deposit_pct" inputmode="numeric" value="' . (int) $qd['deposit_pct'] . '"' . $dis . '></label>'
                . ($rates ? '<label>Your usual tax rate<select class="ak-in" name="tax_rate"' . $dis . '><option value="">The percent below</option>' . implode('', array_map(fn ($x) => '<option value="' . (int) $x['id'] . '"' . ((int) $x['rate_milli'] === $qd['tax_milli'] && $qd['tax_milli'] ? ' selected' : '') . '>' . $e($x['name'] . ' · ' . Commerce::pct((int) $x['rate_milli'])) . '</option>', $rates)) . '</select></label>' : '')
                . '<label>Sales tax (%)<input class="ak-in" name="tax" inputmode="decimal" value="' . $e($pct) . '"' . $dis . '></label>'
                . '<label class="w">Your pay link (Square, PayPal…), optional<input class="ak-in" name="pay_link" value="' . $e($qd['pay_link']) . '" placeholder="https://"' . $dis . '></label>'
                . '<label class="w">How else to pay (Zelle to…, checks to…)<textarea class="ak-in" name="pay_note" rows="2"' . $dis . '>' . $e($qd['pay_note']) . '</textarea></label>'
                . '<label class="w">Notes on every quote (optional)<textarea class="ak-in" name="notes" rows="2"' . $dis . '>' . $e($qd['notes']) . '</textarea></label>'
                . '<label>A quote is good for (days)<input class="ak-in" name="valid_days" inputmode="numeric" value="' . (int) $qd['valid_days'] . '"' . $dis . '></label>'
                . ($qed ? '<div class="w"><button class="ak-b go">Save</button></div>' : '<p class="ak-note w">Your role can see these but not change them.</p>') . '</form></div>'
                . '<style>.bz-qd{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(13rem,100%),1fr));gap:.5rem .7rem;margin-top:.5rem}.bz-qd label{display:flex;flex-direction:column;gap:.2rem;font-size:.82rem;color:var(--tv-meta,#5C4632)}.bz-qd .w{grid-column:1/-1}.bz-qd textarea{font:inherit}</style>'
                . '<script>(function(){var f=document.getElementById("bz-qd");if(!f||!f.querySelector("button"))return;f.addEventListener("submit",async function(e){e.preventDefault();var b=f.querySelector("button"),d={action:"quote_defaults"};Array.prototype.forEach.call(f.elements,function(x){if(x.name)d[x.name]=x.value;});'
                . 'var s=await (await fetch("/api/auth/session.php",{credentials:"include"})).json();var r=await (await fetch(' . json_encode('/business/api/' . $id . '/settings') . ',{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":s.csrf_token},body:JSON.stringify(window.tvSafe?tvSafe(d):d)})).json();'
                . 'TvAsk.say(r.ok?r.message:((r.error&&r.error.message)||"That didn\'t work."),{anchor:b});});})();</script>';
        } catch (Throwable $x) { error_log('business settings quote terms: ' . $x->getMessage()); }
    }
    // ---- your team: add by email (an invitation when they've no account yet), change roles, take people off (decisions/0118)
    $canTeam = Team::can($main, $uid, $id, 'team', 'edit'); $isOwner = $ctx['role'] === 'owner';
    $give = array_filter(Team::ROLES, fn ($k) => $k !== 'owner' && ($isOwner || $k !== 'manager'), ARRAY_FILTER_USE_KEY);
    $opts = fn (string $cur) => implode('', array_map(fn ($k, $v) => '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . $e($v) . '</option>', array_keys($give), $give));
    echo '<div class="ak-card tv-bleed" id="team"><h2 class="ak-h" style="margin-top:0">Your team</h2><div class="bz-team">';
    foreach ($ctx['team'] as $p) {
        $pu = (int) $p['user_id']; $pr = (string) $p['role']; $me = $pu === $uid;
        $editable = $canTeam && !$me && $pr !== 'owner' && ($isOwner || $pr !== 'manager');
        echo '<div class="bz-tm"><div><b>' . $e(trim((string) ($p['display_name'] ?: $p['email'])) ?: 'You') . '</b>' . ($me ? ' <small>(you)</small>' : '') . '<small>' . $e((string) $p['email']) . '</small></div><div class="bz-tma">'
            . ($editable ? '<select class="ak-in bz-sel" data-team-role="' . $pu . '" aria-label="Role">' . $opts($pr) . '</select><button type="button" class="ak-b no" data-team-remove="' . $pu . '" data-q="Take ' . $e(trim((string) ($p['display_name'] ?: $p['email']))) . ' off the team?">Remove</button>'
                : '<span class="bz-pill">' . $e(Team::roleName($pr)) . '</span>' . ($me && $pr !== 'owner' ? '<button type="button" class="ak-b no" data-team-remove="' . $pu . '" data-q="Leave ' . $e($ctx['name']) . '\'s team?">Leave</button>' : ''))
            . '</div></div>';
    }
    echo '</div>';
    $inv = $canTeam ? Team::invites($main, $id) : [];
    if ($inv) { echo '<p class="ak-note" style="margin:.6rem 0 .2rem"><b>Invited, not joined yet</b></p>'; foreach ($inv as $i) echo '<div class="bz-tm"><div><b>' . $e($i['email']) . '</b><small>' . $e(Team::roleName($i['role'])) . ' · invited ' . $e(date('M j', strtotime((string) $i['created_at']))) . '</small></div><div class="bz-tma"><button type="button" class="ak-b" data-invite-send="' . (int) $i['id'] . '">Send again</button><button type="button" class="ak-b" data-invite-cancel="' . (int) $i['id'] . '">Cancel</button></div></div>'; }
    if ($canTeam) echo '<form class="bz-add" id="bz-add"><input class="ak-in" type="text" name="name" maxlength="120" placeholder="Their name (optional)" aria-label="Their name" autocomplete="off"><input class="ak-in" type="email" name="email" required placeholder="Their email" aria-label="Their email" autocomplete="off"><select class="ak-in bz-sel" name="role" aria-label="Role">' . $opts('viewer') . '</select><button class="ak-b go">Add</button></form>'
        . '<p class="ak-note">Someone with a Traversence account joins at once. Anyone else gets an invitation: its link lets them claim their place and create their account in one step.' . ($isOwner ? '' : ' Only the Owner gives the Manager role.') . '</p>';
    else echo '<p class="ak-note">The Owner and Managers add people and change roles.</p>';
    if ($isOwner) {   // handing over ownership (decisions/0103 §2): the owner names a successor; nothing changes until they accept
        $pt = Team::pendingTransfer($main, $id);
        echo '<div class="bz-xfer"><p class="ak-note" style="margin:.7rem 0 .2rem"><b>Hand over the business</b></p>';
        if ($pt) echo '<div class="bz-tm"><div><b>Waiting for ' . $e(trim((string) $pt['to_name']) ?: $pt['to_email']) . ' to accept</b><small>Asked ' . $e(date('M j', strtotime((string) $pt['requested_at']))) . ' · lapses ' . $e(date('M j', strtotime((string) $pt['expires_at']))) . '. You\'re the Owner until they accept.</small></div><div class="bz-tma"><button type="button" class="ak-b" data-xfer-cancel>Cancel</button></div></div>';
        else {
            $others = array_values(array_filter($ctx['team'], fn ($p) => (int) $p['user_id'] !== $uid));
            echo '<form class="bz-add" id="bz-xfer"><select class="ak-in bz-sel" name="pick" aria-label="Who takes over">' . implode('', array_map(fn ($p) => '<option value="' . (int) $p['user_id'] . '">' . $e(trim((string) ($p['display_name'] ?: $p['email']))) . '</option>', $others)) . '<option value="">Someone else, by email</option></select>'
                . '<input class="ak-in" type="email" name="email" placeholder="Their account email" aria-label="Their account email"' . ($others ? ' hidden' : '') . '>'
                . '<button class="ak-b" data-confirm="Ask them to take over as Owner? Once they accept, they own it and you stay on as Manager (you can leave after). Its plan then needs their card." data-yes="Ask them">Ask them to take over</button></form>'
                . '<p class="ak-note">They become the Owner when they accept; you stay on the team as Manager. Its plan runs to the end of what\'s paid, then needs their card.</p>';
        }
        echo '</div>';
    }
    echo '</div><style>.bz-tm{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.45rem 0;border-top:1px solid var(--tv-line,#EADFC4)}.bz-tm small{display:block;color:var(--tv-meta,#8A7355);font-size:.78rem}'
        . '.bz-tma{display:flex;gap:.35rem;align-items:center;flex-wrap:wrap;justify-content:flex-end}.bz-sel{width:auto;padding:.3rem .45rem;font-size:.85rem}.bz-add{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.7rem}.bz-add input{flex:1;min-width:11rem}</style>'
        . '<script>(function(){var box=document.getElementById("team");if(!box)return;var api=' . json_encode('/business/api/' . $id . '/settings') . ';'
        . 'async function post(b,el){var s=await (await fetch("/api/auth/session.php",{credentials:"include"})).json();var r=await (await fetch(api,{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":s.csrf_token},body:JSON.stringify(window.tvSafe?tvSafe(b):b)})).json();'
        . 'if(r.ok){TvAsk.say(r.message||"Done.");setTimeout(function(){location.reload();},700);}else{TvAsk.say((r.error&&r.error.message)||"That didn\'t work.",{anchor:el});}}'
        . 'box.addEventListener("change",function(e){var s=e.target.closest("[data-team-role]");if(s)post({action:"team_role",user:+s.getAttribute("data-team-role"),role:s.value},s);});'
        . 'box.addEventListener("click",function(e){var r=e.target.closest("[data-team-remove]");if(r){if(!TvAsk.gate(r,r.getAttribute("data-q"),{yes:"Yes",danger:true}))return;post({action:"team_remove",user:+r.getAttribute("data-team-remove")},r);return;}'
        . 'var v=e.target.closest("[data-invite-send]");if(v){post({action:"invite_send",invite:+v.getAttribute("data-invite-send")},v);return;}var c=e.target.closest("[data-invite-cancel]");if(c){if(!TvAsk.gate(c,"Cancel this invitation?",{yes:"Cancel it",danger:true}))return;post({action:"invite_cancel",invite:+c.getAttribute("data-invite-cancel")},c);}});'
        . 'var f=document.getElementById("bz-add");if(f)f.addEventListener("submit",function(e){e.preventDefault();var b=f.querySelector("button");post({action:"team_add",name:f.elements.name.value,email:f.email.value,role:f.role.value},b);});'
        . 'var x=document.getElementById("bz-xfer");if(x){var pk=x.pick,em=x.email;pk.addEventListener("change",function(){em.hidden=pk.value!=="";if(!em.hidden)em.focus();});'
        . 'x.addEventListener("submit",function(e){e.preventDefault();var w=pk.value||em.value.trim();if(!w){TvAsk.say("Choose who takes over, or type their email.",{anchor:em});return;}post({action:"transfer_ask",who:w},x.querySelector("button"));});}'
        . 'var xc=document.querySelector("[data-xfer-cancel]");if(xc)xc.addEventListener("click",function(){if(!TvAsk.gate(xc,"Cancel the handover?",{yes:"Cancel it"}))return;post({action:"transfer_cancel"},xc);});})();</script>';
    require_once __DIR__ . '/../../app/lib/SupportAccess.php';
    try { $wg = SupportAccess::active($main, $id); $wgIn = SupportAccess::included($main, $id); $wgOff = SupportAccess::ownerOff($main, $id); }
    catch (Throwable $x) { error_log('business settings white glove: ' . $x->getMessage()); $wg = $wgIn = $wgOff = null; }
    if ($wgIn || $wg || $wgOff) {   // White Glove access (decisions/0117): who has it, what it covers, and the owner's switch
        echo '<div class="ak-card tv-bleed" id="white-glove"><h2 class="ak-h" style="margin-top:0">White Glove access</h2>';
        if ($wg) echo '<p><b>' . $e(trim((string) $wg['agent_name']) . ($wg['staff_id'] ? ' (' . $wg['staff_id'] . ')' : '')) . '</b>, your White Glove agent, has access since ' . $e(date('M j, Y', strtotime((string) $wg['granted_at']))) . '.</p>';
        elseif ($wgOff) echo '<p>You turned White Glove access off. Your agent can\'t open your business until you turn it on again.</p>';
        else echo '<p>Your plan includes White Glove. Your agent gets access as soon as we assign one, and we email you when we do.</p>';
        echo '<p class="ak-note">They can set up and update your profile, catalog, content, storefront and codes, and see your customers, orders and messages to help with them. They can\'t touch your billing or payouts, your team, your keys, exports or deleting the business. Every change they make is logged. <a class="underline" href="/terms.php#white-glove">The terms</a></p>';
        if ($ctx['role'] === 'owner') echo $wgOff
            ? '<button type="button" class="ak-b" data-wg="1">Turn White Glove access on</button>'
            : '<button type="button" class="ak-b" data-wg="0" data-confirm-wg="Turn White Glove access off? Your agent can\'t open your business, so the White Glove work that needs it waits until you turn it on again.">Turn White Glove access off</button>';
        echo '</div><script>document.querySelectorAll("[data-wg]").forEach(function (b) { b.addEventListener("click", async function () {'
            . ' if (b.dataset.confirmWg && !TvAsk.gate(b, b.dataset.confirmWg, { yes: "Turn it off", danger: true })) return;'
            . ' var s = await (await fetch("/api/auth/session.php", { credentials: "include" })).json();'
            . ' var r = await (await fetch(' . json_encode('/business/api/' . $id . '/settings') . ', { method: "POST", credentials: "include", headers: { "Content-Type": "application/json", "X-CSRF-Token": s.csrf_token }, body: JSON.stringify({ action: "white_glove", on: b.dataset.wg === "1" }) })).json();'
            . ' if (r.ok) location.reload(); else TvAsk.say((r.error && r.error.message) || "That didn\'t work.", { anchor: b }); }); });</script>';
    }
    echo '<div class="ak-card tv-bleed"><h2 class="ak-h" style="margin-top:0">What your role can do</h2><table class="bz-tbl"><tr><th>Area</th><th>' . $e(Team::roleName($ctx['role'])) . '</th></tr>';
    $words = ['none' => 'No', 'view' => 'See', 'draft' => 'Draft', 'edit' => 'Change', 'full' => 'Everything'];
    foreach (Team::PERMS as $pk => $pl) { $lv = Team::level($main, $ctx['role'], $pk); echo '<tr><td>' . $e($pl) . '</td><td class="' . ($lv === 'none' ? 'bz-no' : 'bz-yes') . '">' . $words[$lv] . '</td></tr>'; }
    echo '</table></div>';
    echo '<div class="ak-card tv-bleed" id="features"><h2 class="ak-h" style="margin-top:0">Your plan: ' . $e(Plan::TIERS[$ctx['tier']]) . '</h2><table class="bz-tbl"><tr><th>Feature</th><th>Included</th></tr>';
    foreach (Plan::FEATURES as $fk => [$fl]) { [$o, $start] = Plan::allows($main, $ctx['tier'], $fk); echo '<tr><td>' . $e($fl) . '</td><td class="' . ($o ? 'bz-yes' : 'bz-no') . '">' . ($o ? '✓' : $e(Plan::upgradeNote($start))) . '</td></tr>'; }
    echo '</table><p class="ak-note"><a class="underline" href="' . $e($plansUrl) . '">See plans</a></p></div>';
}
echo '<script src="/js/admin-kit.js?v=' . (int) @filemtime(__DIR__ . '/../js/admin-kit.js') . '"></script>';
tv_page_end();
