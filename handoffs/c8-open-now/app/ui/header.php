<?php
/**
 * includes/header.php — the right side of the top bar (decisions/0055 §2).
 *
 *   Cart (GO Codes, with its count) · Bell (notifications)  ›  [ AI Assist · Let's Explore · Get Local · Connect · Market · Menu ]
 *
 * Who you are lives in the account bar above the header (context-switcher.php, tv_acting_bar): your photo there switches
 * accounts, its icon signs out (Jason, 2026-11-05). So there's no person icon here, and the menu (☰) on wider screens is for
 * visitors: Create account and Sign in at its top (Jason, 2026-11-05: only in the menu, not beside it), and the essential pages.
 *
 * The small tab collapses the funnel box to just the menu; the choice is remembered. Phones always show the bell and
 * the menu, and the menu opens the full-screen menu (Let's Explore, Get Local, Connect, Market, Login, AI Assist,
 * Help Center). The current funnel glows. No animated transitions. Funnels without a page yet show as "coming soon".
 *
 * Menu items tagged data-auth="out" | "in" | "admin" are shown by js/header-nav.js from window.appAuthActive /
 * window.appIsAdmin (set by js/location-scope.js). Hiding a link is only presentation: pages check access themselves.
 * Element ids used by other scripts are kept: icon-menu-btn, site-menu-panel,
 * menu-location-dot, menu-location-label.
 */
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/csp.php';
$tv_admin = tv_route(['admin/index.php' => '/admin/']) ?? tv_route(['admin/categories.php' => '/admin/categories.php']);   // the admin dashboard (decisions/0078)
$tv_admin_label = tv_route(['admin/index.php' => 'x']) ? 'Admin dashboard' : 'Manage Categories';
$tv_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$tv_in = false; try { $tv_in = class_exists('Auth') && Auth::currentUser() !== null; } catch (Throwable) {}   // signed in (pages without the sign-in loaded follow js/header-nav.js)

$tv_i = [
    'ai' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.3 1.1 2.2v.5h5v-.5c0-.9.4-1.6 1.1-2.2A6 6 0 0 0 12 3z"/><path d="M12 6.5v6M10 8.5c0-1 .9-1.6 2-1.6M14 8.5c0-1-.9-1.6-2-1.6M9.8 11c.5.6 1.3.9 2.2.9s1.7-.3 2.2-.9"/></svg>',
    'explore' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M16.5 7.5 13.6 13.6 7.5 16.5l2.9-6.1z" fill="currentColor"/></svg>',
    'local' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5a7 7 0 0 0-7 7c0 5.2 7 12 7 12s7-6.8 7-12a7 7 0 0 0-7-7z" fill="currentColor"/><circle cx="12" cy="9.5" r="2.6" fill="var(--tv-on-umber)"/></svg>',
    'social' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3.2"/><path d="M12 8.8V4.5M12 15.2v4.3M8.8 12H4.5M15.2 12h4.3M9.7 9.7 6.7 6.7M14.3 14.3l3 3M14.3 9.7l3-3M9.7 14.3l-3 3"/><circle cx="12" cy="3.6" r="1.1" fill="currentColor"/><circle cx="12" cy="20.4" r="1.1" fill="currentColor"/><circle cx="3.6" cy="12" r="1.1" fill="currentColor"/><circle cx="20.4" cy="12" r="1.1" fill="currentColor"/><circle cx="6" cy="6" r="1.1" fill="currentColor"/><circle cx="18" cy="18" r="1.1" fill="currentColor"/><circle cx="18" cy="6" r="1.1" fill="currentColor"/><circle cx="6" cy="18" r="1.1" fill="currentColor"/></svg>',
    'link' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>',
    'market' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M4 10v10h16V10"/><path d="M3 5.5 4.5 3h15L21 5.5v2a2.5 2.5 0 0 1-4.5 1.5A2.5 2.5 0 0 1 12 9a2.5 2.5 0 0 1-4.5 0A2.5 2.5 0 0 1 3 7.5z"/><path d="M9.5 20v-5h5v5"/></svg>',
    'person' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>',
    'cart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2 3h3l2.4 11.2a1.8 1.8 0 0 0 1.8 1.4h8.6a1.8 1.8 0 0 0 1.8-1.4L21 7H6.2"/></svg>',
    'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></svg>',
    'route' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg>',
    'bars' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="2.2" rx="1" fill="currentColor"/><rect x="3" y="10.9" width="18" height="2.2" rx="1" fill="currentColor"/><rect x="3" y="16.8" width="18" height="2.2" rx="1" fill="currentColor"/></svg>',
];
// The primary funnels (decisions/0055 §2): [key, label, url or null (coming soon), icon, active?]
$tv_funnels = [
    ['ai', 'AI Assist', tv_route(['ai/index.php' => '/ai/']), 'ai', str_starts_with($tv_path, '/ai')],
    ['explore', "Let's Explore", tv_route(['discovery/index.php' => '/discovery/']), 'explore', str_starts_with($tv_path, '/discovery') || str_starts_with($tv_path, '/hub')],
    ['local', 'Get Local', tv_route(['directory/index.php' => '/directory/']), 'local', str_starts_with($tv_path, '/directory') || str_starts_with($tv_path, '/listing/view')],
    ['social', 'Connect', tv_route(['community/index.php' => '/community/']), 'social', str_starts_with($tv_path, '/community')],
    ['market', 'Marketplace', tv_route(['market/index.php' => '/market/']), 'market', str_starts_with($tv_path, '/market')],
];
$tv_e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// Universal search (decisions/0053): on every page except dashboards, admin, sign-in pages and the directory (whose
// own bar is the full search).
$tv_search = !preg_match('#^/(user/dashboard|listing/businessportal|login|register|forgot-password|reset-password|verify-email|join|directory/classic)#', $tv_path);
// on admin pages the header's box searches the admin (Jason, 2026-11-12): pages, tools and the records themselves, Ctrl+K
// from anywhere (js/admin-search.js); its Site switch opens the public search, which stays on the page, out of sight
// The sign-in pages (screens proposal A10, Jason 2026-11-22): nothing to show yet, so no cart, no bell and no admin search
// there (they looked switched off with no reason); the menu stays. One flag, read below.
$tv_signin = (bool) preg_match('#^/(login|register|forgot-password|reset-password|admin/login|admin/setup)(\.php)?$#', $tv_path);
$tv_admin_search = !$tv_signin && (bool) preg_match('#^/admin(/|$)#', $tv_path);
$tv_search = $tv_search && !$tv_signin;
// The home page holds the search in its hero (decisions/0138 §2): the same bar, so the header leaves it out there and the
// page prints $tv_search_bar where it wants it. Every other page keeps it in the header.
$tv_home = in_array($tv_path, ['/', '/index.php'], true);
$tv_search_bar = '';
$tv_fold = (bool) preg_match('#^/directory(/(index\.php)?)?$#', $tv_path);   // Get Local: its own search bar, so one search per view (css-theme-brand.md)
$tv_i['search'] = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg>';
$tv_item = 'block w-full text-left px-4 py-2 text-[color:var(--tv-ink)] hover:bg-[var(--tv-card-b)]';
require_once __DIR__ . '/theme-tokens.php';
echo tv_theme_tokens();   // the design tokens, for a page that builds its own head (the home page, the dashboard): drawn once a page
?>
<style>
  .tvn{display:flex;align-items:center;gap:.5rem;flex:1 1 auto;justify-content:flex-end;min-width:0}
  /* Universal search (decisions/0053) */
  .tvs-bar{flex:1 1 auto;max-width:34rem;margin:0 auto 0 1rem;display:flex;align-items:center;background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:.7rem;padding:0 .6rem;min-width:0}
  .tvs-bar .tvs-icon{color:var(--tv-meta);display:flex}.tvs-bar .tvs-icon svg{width:18px;height:18px}
  .tvs-in{flex:1;min-width:0;border:0;background:transparent;padding:.55rem .5rem;font-size:.95rem;color:var(--tv-ink);outline:none}
  .tvs-pin{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border:0;border-left:1px solid var(--tv-line-strong);background:none;color:var(--tv-meta);cursor:pointer;padding-left:.35rem}
  .tvs-pin svg{width:19px;height:19px}.tvs-pin:hover{color:var(--tv-icon,#B45309)}.tvs-pin.set{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}
  .tvs-open{display:none;background:none;border:0;color:var(--tv-meta);cursor:pointer;width:40px;height:40px;align-items:center;justify-content:center}.tvs-open svg{width:26px;height:26px}
  .tvs-open span{display:none}
  /* phones: a search box, not a lone icon (decisions/0058 §31); a tap opens the full search panel */
  @media (max-width: 767px){ .tvs-bar{flex:1 1 auto;margin:0 .35rem 0 .6rem;background:none;border:0;padding:0;max-width:none} .tvs-bar .tvs-icon,.tvs-in,.tvs-pin{display:none}
    .tvs-open{display:flex;flex:1;min-width:0;width:auto;height:38px;justify-content:flex-start;gap:.45rem;padding:0 .7rem;background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:999px;color:var(--tv-meta);font:inherit;font-size:.86rem;text-align:left}
    .tvs-open svg{width:18px;height:18px;flex:0 0 auto}.tvs-open span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--tv-meta);font-weight:500} }
  .tvs-dim{position:fixed;inset:0;background:rgba(62,43,31,.28);z-index:1370}
  .tvs-panel{position:fixed;z-index:1371;max-height:86vh;overflow:auto;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:1rem;box-shadow:0 18px 44px -12px rgba(0,0,0,.45);color:var(--tv-ink)}
  .tvs-panel.full{inset:0;max-height:none;border-radius:0;border:0;display:flex;flex-direction:column}
  .tvs-top{display:flex;gap:.4rem;align-items:center;padding:.7rem .8rem;border-bottom:1px solid var(--tv-line);background:var(--tv-umber)}
  .tvs-in2{flex:1;min-width:0;border:1px solid var(--tv-line-strong);border-radius:.6rem;padding:.6rem .7rem;font-size:1rem;background:var(--tv-canvas);color:var(--tv-ink)}
  .tvs-pin2{flex:0 0 40px;background:none;border:0;color:var(--tv-on-umber-meta);width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer}
  .tvs-x{background:none;border:0;font-size:1.8rem;line-height:1;color:var(--tv-meta);cursor:pointer;width:40px;height:40px}
  .tvs-panel.full .tvs-x{color:var(--tv-on-umber-meta)}
  /* on a computer the search is its own pop-up over the dimmed page (Jason, 2026-11-05): its field and the place on top,
     the results below; a click on the dimmed page, × or Esc closes it. Above the header, below Peek and the place picker. */
  .tvs-dim.pop{z-index:1450;background:rgba(30,20,14,.55)}
  .tvs-panel.full.pop{z-index:1451;inset:auto;top:4vh;left:50%;transform:translateX(-50%);width:min(1100px,calc(100vw - 32px));min-height:min(60vh,36rem);max-height:92vh;border:1px solid var(--tv-line-strong);border-radius:1rem;box-shadow:0 30px 60px -18px rgba(0,0,0,.6)}
  .tvs-panel.full .tvs-top{position:sticky;top:0;z-index:2}
  .tvs-panel.pop .tvs-top{padding:.8rem 1rem;gap:.6rem}
  .tvs-panel.pop .tvs-in2{font-size:1.05rem;padding:.7rem .85rem}
  .tvs-pl2{display:none}
  .tvs-panel.pop .tvs-pin2{flex:0 1 auto;width:auto;max-width:42%;height:44px;gap:.45rem;padding:0 .8rem;border:1px solid var(--tv-taupe);border-radius:.6rem}
  .tvs-panel.pop .tvs-pin2:hover{background:var(--tv-void)}
  .tvs-panel.pop .tvs-pl2{display:inline;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--tv-on-umber);font-size:.95rem;font-weight:600}
  @media (min-width:768px){.tvs-panel.pop .tvs-hd>.tvs-loc,.tvs-panel.pop .tvs-hd>.tvs-reach{display:none}}   /* the place button and its picker hold them */
  .tvs-body{padding:.5rem .85rem .6rem;overflow:auto}
  .tvs-lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--tv-meta);margin:.8rem 0 .25rem;font-weight:700}
  .tvs-row{display:flex;justify-content:space-between;align-items:center;gap:.6rem;padding:.45rem .55rem;border-radius:.6rem;color:var(--tv-ink);text-decoration:none}
  .tvs-row:hover,.tvs-row:focus{background:var(--tv-canvas)}.tvs-row b{font-weight:600}.tvs-row small{display:block;font-size:.75rem;color:var(--tv-meta)}
  .tvs-go{font-size:.72rem;color:var(--tv-icon,#B45309);font-weight:700;white-space:nowrap}
  .tvs-peekb{display:inline-block;border:1px solid var(--tv-line-strong);border-radius:999px;padding:.15rem .6rem;background:var(--tv-card-a);color:var(--tv-icon,#B45309);cursor:pointer}.tvs-peekb:hover,.tvs-peekb:focus-visible{background:var(--tv-sage);color:var(--tv-umber);border-color:var(--tv-sage);box-shadow:0 0 0 3px rgba(143,188,168,.35);outline:0}
  .tvs-more{display:inline-block;font-size:.8rem;color:var(--tv-icon,#B45309);font-weight:600;margin:.15rem 0 0 .55rem;text-decoration:none}
  .tvs-chips{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
  .tvs-chip{display:inline-flex;align-items:center;gap:.3rem;font-size:.82rem;border:1px solid var(--tv-line-strong);background:var(--tv-canvas);border-radius:999px;padding:.2rem .65rem;cursor:pointer;color:var(--tv-ink)}
  .tvs-chip.on{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber);cursor:default}.tvs-chip.on small{opacity:.7}
  .tvs-chip.on button{background:none;border:0;color:var(--tv-meta);font-size:1rem;cursor:pointer;padding:0 0 0 .1rem}
  .tvs-chip.ghost{background:none}
  .tvs-read{display:flex;align-items:flex-start;gap:.5rem;margin:.35rem 0 .3rem}.tvs-read .tvs-chips{flex:1;min-width:0}.tvs-read .tvs-savebtn{flex:0 0 auto;margin-left:auto;white-space:nowrap}
  .tvs-where{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.3rem .8rem;background:var(--tv-canvas);border:1px solid var(--tv-line);border-radius:.7rem;padding:.45rem .7rem;font-size:.86rem;color:var(--tv-ink);margin-bottom:.5rem}
  .tvs-why{color:var(--tv-meta);font-size:.78rem}.tvs-wacts{white-space:nowrap}
  .tvs-cols{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:.3rem 1.2rem}
  .tvs-col2{border-left:1px solid var(--tv-line);padding-left:1rem}
  .tvs-sec{margin-bottom:.45rem}
  .tvs-sh{display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--tv-meta);margin:.1rem 0 .2rem}
  .tvs-sh .tvs-link{font-size:.75rem;text-transform:none;letter-spacing:0;font-weight:600}
  .tvs-li{display:flex;align-items:center;gap:.2rem;border-radius:.55rem}.tvs-li:hover{background:var(--tv-canvas)}
  .tvs-lb{flex:1;min-width:0;text-align:left;background:none;border:0;padding:.22rem .4rem;font-size:.9rem;color:var(--tv-ink);cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .tvs-lx{flex:0 0 auto;width:28px;height:28px;border:0;background:none;color:var(--tv-meta);border-radius:999px;cursor:pointer;font-size:1rem}
  .tvs-lx:hover{background:var(--tv-card-b);color:var(--tv-ink)}.tvs-ed{font-size:.85rem}
  .tvs-sh2{margin-top:.6rem}
  .tvs-here{width:100%;text-align:left;background:var(--tv-canvas);border:1px solid var(--tv-line-strong);cursor:pointer;margin-bottom:.4rem}.tvs-here small{display:block;color:var(--tv-meta);font-size:.76rem}
  .tvs-phr{flex:1;min-width:0;box-sizing:border-box;height:2rem;line-height:1.2;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:.45rem;padding:.3rem .5rem;font-size:.88rem;color:var(--tv-ink);margin:.1rem 0}
  .tvs-pe .tvs-li{gap:.35rem;padding-left:.3rem}.tvs-plain{cursor:default}
  .tvs-n{display:inline-block;margin-left:.3rem;min-width:1.6rem;text-align:center;font-size:.72rem;font-weight:600;color:var(--tv-meta);background:var(--tv-card-b);border-radius:999px;padding:0 .4rem}
  .tvs-empty{font-size:.82rem;color:var(--tv-meta);margin:.1rem .4rem .3rem}.tvs-empty a{color:var(--tv-link);text-decoration:underline}
  /* carry on on Google Maps where we have no listings yet: one tappable row each, brand brown, opens a new tab */
  .tvs-gmaps{display:flex;flex-direction:column;gap:3px;margin:.25rem 0 .5rem}
  .tvs-gmap{display:flex;align-items:center;gap:.35rem;padding:.55rem .7rem;border:1px solid var(--tv-line);border-radius:.5rem;background:var(--tv-card-a);color:var(--tv-link);font-size:.88rem;font-weight:600;text-decoration:underline;text-underline-offset:2px}
  .tvs-gmap small{color:var(--tv-meta);font-weight:400;text-decoration:none}.tvs-gmap span{margin-left:auto;color:var(--tv-icon,#B45309);text-decoration:none}
  .tvs-gmap:hover,.tvs-gmap:focus-visible{background:var(--tv-card-a)}
  .tvs-askpl .tvs-askb{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:3px}.tvs-askpl .tvs-setpl{border-style:dashed}.tvs-alts{margin:.35rem 0 0}.tvs-alts .tvs-link{color:var(--tv-link);text-decoration:underline}
  .tvs-osm{margin:.15rem 0}.tvs-osmc{font-size:.72rem;color:var(--tv-meta);margin:.2rem .4rem .35rem}.tvs-osmc a{color:var(--tv-link);text-decoration:underline}
  .tvs-scopes{display:flex;flex-wrap:wrap;gap:.3rem}
  .tvs-sc{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.2rem .65rem;font-size:.8rem;cursor:pointer}
  .tvs-sc.on{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber)}
  .tvs-token{display:inline-flex;align-items:center;gap:.25rem;flex:0 0 auto;background:var(--tv-umber);color:var(--tv-on-umber);border-radius:999px;padding:.1rem .55rem;font-size:.78rem;font-weight:600;margin-right:.35rem;white-space:nowrap}
  .tvs-token button{background:none;border:0;color:var(--tv-meta);cursor:pointer;font-size:.95rem;line-height:1;padding:0}
  .tvs-savebtn.on{background:var(--tv-hold-bg);border-color:var(--tv-copper)}
  .tvs-foot{color:var(--tv-ink)!important}.tvs-foot a{color:var(--tv-link);text-decoration:underline;font-weight:600}
  @media (max-width:767px){.tvs-cols{grid-template-columns:minmax(0,1fr)}.tvs-col2{border-left:0;padding-left:0;border-top:1px solid var(--tv-line);padding-top:.6rem}}
  .tvs-rhead{display:flex;justify-content:space-between;align-items:baseline;gap:.5rem}.tvs-kind{font-style:normal;font-size:.7rem;color:var(--tv-meta);border:1px solid var(--tv-line);border-radius:999px;padding:0 .4rem;margin-right:.3rem}.tvs-open{font-style:normal;color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));font-weight:600}.tvs-isopen{font-style:normal;color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));font-weight:600}.tvs-unk{font-style:italic;color:var(--tv-meta)}.tvs-lbl.tvs-sub{margin-top:.5rem}.tvs-tabs{display:flex;flex-wrap:wrap;gap:.3rem;margin:.1rem 0 .4rem}.tvs-tab{font-size:.78rem;font-weight:600;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.15rem .6rem;cursor:pointer}.tvs-tab.on{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber)}.tvs-tab small{opacity:.7;font-weight:400}.tvs-tab.off{opacity:.55;cursor:default}.tvs-more.tvs-sm{font-size:.78rem;margin:.1rem 0 .3rem .6rem}
  .tvs-showmore{display:block;margin:.2rem 0 .3rem .6rem;font-size:.8rem}.tvs-row[hidden]{display:none}
  .tvs-tools{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin:.45rem 0 .2rem}.tvs-rad{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:.55rem;padding:.2rem .35rem;font-size:.82rem;color:var(--tv-ink);margin-left:.25rem}.tvs-mapbtn{font-weight:600}
  .tvs-muted{color:var(--tv-meta);font-size:.78rem}.tvs-note{font-size:.88rem;margin:.6rem 0}.tvs-foot{font-size:.82rem;color:var(--tv-ink);margin-top:.4rem}.tvs-foot a{color:var(--tv-link);text-decoration:underline;font-weight:600}
  .tvs-link{background:none;border:0;color:var(--tv-icon,#B45309);text-decoration:underline;cursor:pointer;font-size:inherit}
  .tvs-none{background:var(--tv-card-a);border:1px solid color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-radius:.8rem;padding:.7rem .8rem;margin:.6rem 0}
  .tvs-none-h{font-family:var(--tv-font-head);font-weight:700;font-size:1.05rem;color:var(--tv-ink);margin:0}
  .tvs-none-acts{display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0 .2rem}
  .tvs-none button.tvs-row{display:flex;width:100%;text-align:left;background:none;border:0;border-top:1px solid var(--tv-line);border-radius:0;padding:.45rem .2rem;cursor:pointer;font:inherit;color:inherit}
  .tvs-none button.tvs-row b{display:block;font-weight:600;color:var(--tv-ink)}.tvs-none button.tvs-row small{display:block;color:var(--tv-meta);font-size:.78rem}
  .tvs-none button.tvs-row .tvs-go{margin-left:auto;align-self:center;color:var(--tv-link);font-weight:600;font-size:.8rem}
  .tvs-town{display:flex;justify-content:space-between;gap:.5rem;align-items:baseline;margin:.6rem 0 .1rem;font-size:.85rem;color:var(--tv-ink)}.tvs-town small{color:var(--tv-meta)}
  .tvs-top-card{background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:.8rem;padding:.7rem .9rem;margin-top:.6rem}.tvs-top-card ul{margin:.2rem 0 .3rem 1rem;list-style:disc;font-size:1rem}
  .tvs-people{border-top:1px dashed var(--tv-line);margin-top:.6rem}
  .tvs-btn{display:inline-block;font-size:.82rem;font-weight:600;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:.6rem;padding:.35rem .75rem;text-decoration:none;margin-top:.3rem}
  .tvs-btn.go{background:var(--tv-btn);color:var(--tv-btn-text);border-color:var(--tv-btn);border-radius:999px}.tvs-btn.go:hover{background:var(--tv-btn-hover);border-color:var(--tv-btn-hover)}
  .tvs-peek-dim{position:fixed;inset:0;z-index:1599;background:rgba(42,27,18,.55)}   /* the page dims behind a Peek (Jason, 2026-11-19) */.tvs-acts{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.5rem}
  .tvs-peek{position:fixed;z-index:1600;left:50%;top:50%;transform:translate(-50%,-50%);width:min(26rem,calc(100vw - 2rem));max-height:85vh;overflow:auto;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:1rem;box-shadow:0 20px 50px -12px rgba(0,0,0,.55);padding:1rem 1.1rem;color:var(--tv-ink)}
  .tvs-peek h3{font-family:var(--tv-font-head);font-weight:700;font-size:1.25rem;margin:.1rem 0 .3rem}.tvs-peek .tvs-x{position:absolute;top:.3rem;right:.4rem}.tvs-about{font-size:.88rem;color:var(--tv-meta);margin:.4rem 0}.tvs-keep{position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-top:.7rem;padding:.55rem 3rem .1rem 0;border-top:1px solid var(--tv-line)}
  /* the bar and its panel (decisions/0058 §35): a cream field, then a gold block with Search and the place pin; the
     panel hangs from the bar in two columns */
  @media (min-width:768px){
    .tvs-bar{max-width:52rem;background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:.45rem;padding:0 0 0 .3rem;overflow:hidden;align-self:stretch;margin-top:.45rem;margin-bottom:.45rem}
    .tvs-bar .tvs-icon{display:none}
    .tvs-btns{display:flex;align-items:center;gap:.45rem;align-self:stretch;background:var(--tv-card-b);padding:0 .55rem}
    .tvs-sbtn,.tvs-btns .tvs-pin{width:38px;height:38px;border-radius:999px;border:3px solid var(--tv-umber);background:var(--tv-card-a);color:var(--tv-ink);display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0}
    .tvs-sbtn svg,.tvs-btns .tvs-pin svg{width:20px;height:20px}
    .tvs-sbtn:hover,.tvs-btns .tvs-pin:hover,.tvs-btns .tvs-pin.set{background:var(--tv-sage);color:var(--tv-umber);box-shadow:0 0 0 3px rgba(143,188,168,.35)}
    /* the split bar's look (Jason, 2026-11-19): a cream pill, ⌕ inside, the place as a chip, the round copper button */
    .tvs-bar{border-radius:999px;padding:0 .3rem 0 .85rem;background:var(--tv-field,#FFFBF2);overflow:visible;gap:.35rem}.tvs-bar .tvs-icon{display:flex;color:var(--tv-icon,#B45309)}
    .tvs-btns{background:none;padding:0;gap:.35rem}
    .tvs-btns .tvs-pin{width:auto;height:32px;border:1px solid var(--tv-line-strong);border-radius:999px;padding:0 .7rem;gap:.3rem;background:var(--tv-card-a);color:var(--tv-ink);font:600 .84rem var(--tv-font-ui);white-space:nowrap;max-width:15rem}
    .tvs-btns .tvs-pin svg{width:15px;height:15px;color:var(--tv-icon,#B45309);flex:none}.tvs-pl{overflow:hidden;text-overflow:ellipsis}.tvs-car{font-style:normal;font-size:.7rem;color:var(--tv-meta)}
    .tvs-btns .tvs-pin.set{box-shadow:none;background:var(--tv-card-a)}.tvs-btns .tvs-pin:hover{background:var(--tv-sec,#EDE6D5);box-shadow:none}
    .tvs-sbtn{order:2;width:36px;height:36px;border:0;background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#E4DCC5)}.tvs-sbtn:hover{background:var(--tv-btn-hover,#78350F);color:var(--tv-btn-text,#E4DCC5);box-shadow:none}
    .tvs-fold{flex:0 0 auto!important;max-width:none!important;background:none!important;border:0!important;padding:0!important;margin-left:auto!important}
    .tvs-fold>.tvs-icon,.tvs-fold>.tvs-in,.tvs-fold>.tvs-btns{display:none!important}
    .tvs-fold>.tvs-open{display:flex!important;width:40px;height:40px;flex:none;justify-content:center;border:1px solid var(--tv-line-strong);border-radius:999px;background:var(--tv-field,#FFFBF2);color:var(--tv-icon,#B45309)}.tvs-fold>.tvs-open span{display:none}
    .tvs-panel:not(.full){border:1px solid var(--tv-line-strong);border-top:0;border-radius:0 0 1rem 1rem;background:var(--tv-card-a)}
  }
  @media (max-width:767px){.tvs-btns{display:none}}
  .tvs-hd{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 2rem;padding:.2rem .1rem .55rem}
  @media (min-width:900px){.tvs-hd{flex-wrap:nowrap;gap:1.2rem}.tvs-hd .tvs-reach{margin-left:auto}.tvs-hd .tvs-loc b{max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}}   /* one row: Location, Search Directory, Distance top right */
  .tvs-intent{margin:.1rem 0 .45rem;padding:.4rem .6rem;border-left:3px solid var(--tv-sage);background:var(--tv-canvas);border-radius:.25rem;font-family:var(--tv-font-head);font-size:.98rem;color:var(--tv-ink)}
  .tvs-nearask>span:not(.tvs-nearrow):not(.tvs-nearlist){color:var(--tv-meta)}
  .tvs-nearrow{display:flex;gap:.4rem;align-items:center;margin:.4rem 0 .25rem;flex-wrap:wrap}
  .tvs-nearin{flex:1 1 10rem;min-width:0;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.5rem;padding:.35rem .55rem;font:inherit;font-size:.9rem;color:var(--tv-ink)}
  .tvs-nearlist{display:block;margin:.1rem 0 .3rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.5rem;overflow:hidden}
  .tvs-nearlist[hidden]{display:none}
  .tvs-nearlist .tvs-row{width:100%;text-align:left;border:0;border-top:1px solid var(--tv-line);background:transparent;cursor:pointer}
  .tvs-nearlist .tvs-row:first-child{border-top:0}
  .tvs-nearask small{display:block;font-family:var(--tv-font-ui)}
  .tvs-loc,.tvs-dir{display:inline-flex;align-items:center;gap:.4rem;font-family:var(--tv-font-head);font-size:1.15rem;color:var(--tv-ink);border-bottom:1px solid var(--tv-line-strong);padding-bottom:.15rem}
  .tvs-loc b{font-weight:400}
  .tvs-pinc{display:inline-flex;width:28px;height:28px;border-radius:999px;border:2px solid var(--tv-umber);align-items:center;justify-content:center;color:var(--tv-ink)}
  .tvs-ib{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1.5px solid var(--tv-umber);border-radius:999px;background:var(--tv-card-a);color:var(--tv-ink);cursor:pointer;padding:0}
  .tvs-ib:hover,.tvs-ib:focus-visible{background:var(--tv-sage);color:var(--tv-umber);outline:0;box-shadow:0 0 0 3px rgba(143,188,168,.35)}
  .tvs-ib svg{width:15px;height:15px}
  .tvs-dsel{font:inherit;font-family:ui-sans-serif,var(--tv-font-ui);font-size:.9rem;min-width:10rem;border:1px solid var(--tv-line-strong);border-radius:.3rem;background:var(--tv-card-a);color:var(--tv-ink);padding:.15rem .4rem}
  .tvs-h{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-family:var(--tv-font-head);font-weight:400;font-size:1.15rem;color:var(--tv-ink);border-bottom:1px solid var(--tv-line-strong);margin:.3rem 0 .4rem;padding:0 0 .15rem}
  .tvs-hbs{display:inline-flex;gap:.25rem}
  .tvs-cols{display:grid;grid-template-columns:minmax(0,2.4fr) minmax(0,1fr);gap:.3rem 1.4rem}
  .tvs-res .tvs-row{background:var(--tv-canvas);border-radius:.2rem;margin-bottom:2px}.tvs-res .tvs-row:hover,.tvs-res .tvs-row:focus{background:var(--tv-card-b)}
  .tvs-try{width:100%;border:0;text-align:left;cursor:pointer;font:inherit}.tvs-try b{font-family:var(--tv-font-head);font-weight:400;font-size:1rem}
  .tvs-t1 .tvs-row{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.3rem;margin-bottom:2px}.tvs-t1 .tvs-row:hover{background:var(--tv-canvas)}
  .tvs-t1 .tvs-row small{color:var(--tv-meta)}.tvs-t1 .tvs-row.far small{color:var(--tv-meta)}.tvs-s1{width:100%;box-sizing:border-box;text-align:left;font:inherit;cursor:pointer}
  .tvs-t1 .tvs-wide{background:var(--tv-canvas);border-style:dashed}.tvs-t1:not(:empty){margin-bottom:.5rem}
  .tvs-ask{background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:.5rem;padding:.55rem .65rem;margin:.1rem 0 .5rem}
  .tvs-ask p{margin:0 0 .4rem;font-family:var(--tv-font-head);font-size:1.02rem;color:var(--tv-ink)}
  .tvs-askb{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}.tvs-or{font-size:.8rem;color:var(--tv-meta)}
  .tvs-askc{flex:1 1 12rem;display:block;text-align:left;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:.4rem;padding:.4rem .6rem;color:var(--tv-ink);text-decoration:none;cursor:pointer;font:inherit}
  .tvs-askc b{display:block;font-weight:600}.tvs-askc small{display:block;font-size:.76rem;color:var(--tv-meta)}.tvs-askc:hover{background:var(--tv-sage);border-color:var(--tv-sage)}
  /* Suggestions: a plain list, one line each; each line says what it is (decisions/0058 §38, §39) */
  /* situations (decisions/0061): the danger line, then each need, marked by its track (the Chameleon Filter) */
  .tvs-danger{margin:.2rem 0 .5rem;padding:.55rem .7rem;border-radius:.4rem;background:var(--tv-fail-bg);border:1px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));color:var(--tv-fail);font-size:1rem}
  .tvs-danger a{color:var(--tv-fail);text-decoration:underline}
  .tvs-sit{margin:.1rem 0 .6rem}.tvs-sith{margin:.1rem 0 .35rem;font-family:var(--tv-font-head);font-size:1.15rem;color:var(--tv-ink)}.tvs-sith small{font-family:ui-sans-serif,var(--tv-font-ui);font-size:.78rem;color:var(--tv-meta)}
  .tvs-need{border-left:3px solid var(--tv-sage);padding-left:.55rem;margin:.45rem 0 .6rem}.tvs-need.trv{border-color:var(--tv-line-strong)}.tvs-need.com{border-color:var(--tv-amethyst)}
  .tvs-needh{display:flex;justify-content:space-between;align-items:baseline;margin:0 0 .25rem;font-weight:700;font-size:.85rem;letter-spacing:.03em;text-transform:uppercase;color:var(--tv-meta)}
  .tvs-needh i{font-style:normal;font-weight:600;font-size:.66rem;color:var(--tv-meta);letter-spacing:.06em}
  .tvs-sol .tvs-go{display:inline-flex;align-items:center;gap:.3rem;flex:0 0 auto}
  .tvs-call{cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;min-width:30px;height:30px;padding:0 .35rem;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-icon,#B45309);text-decoration:none;font-weight:700;font-size:.82rem}
  .tvs-call:hover{background:var(--tv-sage);color:var(--tv-umber);border-color:var(--tv-sage)}
  .tvs-sit.urgent .tvs-call{background:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));color:#fff;border-color:color-mix(in srgb,var(--tv-moss) 40%,var(--tv-canvas));padding:0 .7rem}
  .tvs-other{margin-top:.6rem}.tvs-other>summary{cursor:pointer;color:var(--tv-link);font-weight:600;font-size:.9rem;margin:.2rem 0 .4rem}
  .tvs-t1 .tvs-enter{background:var(--tv-canvas);border-color:var(--tv-line-strong)}.tvs-t1 .tvs-enter small{color:var(--tv-meta)}.tvs-t1 .tvs-enter .tvs-go{font-size:1.1rem;color:var(--tv-link)}
  .tvs-t1 .tvs-sits{border-left:3px solid var(--tv-sage)}.tvs-t1 .tvs-sits.urgent{border-left-color:var(--tv-icon,#B45309)}
  .tvs-learn{border-left:3px solid var(--tv-sage)}
  .tvs-rmw{position:relative}.tvs-rmw>.tvs-row,.tvs-rmw>.tvs-cmp{padding-right:2.4rem}
  .tvs-rm{position:absolute;top:50%;right:.35rem;transform:translateY(-50%);width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-meta);font-size:1rem;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0}
  .tvs-rm:hover{background:var(--tv-sage);color:var(--tv-umber);border-color:var(--tv-sage)}
  .tvs-lex{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .6rem;margin:.2rem 0 .45rem;padding:.5rem .65rem;border-left:3px solid var(--tv-icon,#B45309);background:var(--tv-card-a);border-radius:.25rem;font-size:.84rem;color:var(--tv-ink)}
  .tvs-lex p{margin:0;flex:1 1 12rem}.tvs-lexb{border:1px solid var(--tv-umber);background:var(--tv-umber);color:var(--tv-on-umber);border-radius:.45rem;padding:.25rem .65rem;font:inherit;font-size:.78rem;font-weight:600;cursor:pointer;text-decoration:none}
  .tvs-lexb:hover{background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber)}
  .tvs-dymr{border-left:3px solid var(--tv-icon,#B45309)}.tvs-dymq{display:block;font-size:.9rem;color:var(--tv-ink)}.tvs-dymq b{color:var(--tv-link)}
  .tvs-sugw{margin-top:.7rem}.tvs-sugs{display:flex;flex-direction:column}
  .tvs-sg{display:flex;align-items:baseline;justify-content:space-between;gap:.5rem;width:100%;border:0;border-bottom:1px solid var(--tv-line);background:none;color:var(--tv-ink);
    padding:.3rem .45rem;font:inherit;font-family:var(--tv-font-head);font-size:.95rem;text-align:left;text-decoration:none;cursor:pointer;border-radius:.25rem}
  .tvs-sg span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}   /* one line; the whole of it is its title */
  .tvs-sg i{flex:0 0 auto;font-style:normal;font-family:ui-sans-serif,var(--tv-font-ui);font-size:.68rem;letter-spacing:.04em;text-transform:uppercase;color:var(--tv-meta)}
  .tvs-sg:hover,.tvs-sg:focus-visible{background:var(--tv-card-b);outline:0}.tvs-sg.on{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));font-weight:600}.tvs-sg.on i{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}
  .tvs-mapb{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid var(--tv-line-strong);border-radius:999px;background:var(--tv-card-a);color:var(--tv-icon,#B45309);cursor:pointer;vertical-align:middle}
  .tvs-mapb:hover,.tvs-mapb:focus-visible{background:var(--tv-sage);color:var(--tv-umber);border-color:var(--tv-sage);outline:0}
  .tvs-none a.tvs-row{border-top:1px solid var(--tv-line);border-radius:0;padding:.45rem .2rem}
  /* the Distance slider, beside Search Directory */
  .tvs-reach{display:inline-flex;align-items:center;gap:.45rem;font-family:var(--tv-font-head);font-size:1.15rem;color:var(--tv-ink);border-bottom:1px solid var(--tv-line-strong);padding-bottom:.15rem}
  .tvs-rng{width:8rem;accent-color:var(--tv-copper);cursor:pointer}
  .tvs-rngv{font-family:ui-sans-serif,var(--tv-font-ui);font-size:.85rem;color:var(--tv-link);min-width:4.6rem}
  /* the filters at the foot of Quick Search */
  .tvs-qf{border-top:1px dashed var(--tv-line);margin-top:.3rem;padding-top:.25rem}
  .tvs-qfl .tvs-ftag{font-style:normal;font-family:ui-sans-serif,var(--tv-font-ui);font-size:.66rem;letter-spacing:.05em;text-transform:uppercase;color:var(--tv-meta)}
  .tvs-qfl.on{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));font-weight:600}.tvs-qfl.on .tvs-ftag{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}
  @media (max-width:767px){.tvs-reach{font-size:1rem}.tvs-rng{width:7rem}}
  .tvs-reach.off{opacity:.5;cursor:help}.tvs-reach.off .tvs-rng{cursor:help}   /* no place set: nothing to measure from */
  .tvs-widen{margin:.4rem 0 .6rem}.tvs-wbtn{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.35rem}
  .tvs-meant{margin:.4rem 0 .6rem}.tvs-chipr{display:flex;flex-wrap:wrap;gap:.35rem}.tvs-meant .tvs-chipb,.tvs-widen .tvs-chipb{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-link);border-radius:999px;padding:.3rem .8rem;font-size:.85rem;cursor:pointer}
  .tvs-meant .tvs-chipb:hover,.tvs-widen .tvs-chipb:hover{background:var(--tv-sage);color:var(--tv-ink);box-shadow:0 0 0 3px rgba(143,188,168,.35)}
  .tvs-qr{display:flex;width:100%;align-items:center;justify-content:space-between;gap:.5rem;border:0;background:none;text-align:left;font:inherit;font-family:var(--tv-font-head);font-size:.98rem;color:var(--tv-ink);padding:.12rem .4rem;border-radius:.3rem;cursor:pointer}
  .tvs-qr:hover{background:var(--tv-canvas)}.tvs-qr span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .tvs-qr .tvs-n{font-style:normal;background:var(--tv-card-b);color:var(--tv-meta);font-weight:700}
  .tvs-recent .tvs-lb{font-family:var(--tv-font-head);font-size:.98rem}
  .tvs-ab{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-top:.8rem;padding-top:.6rem;border-top:1px solid var(--tv-line-strong);text-decoration:none;font-family:var(--tv-font-head);font-size:.95rem;color:var(--tv-ink)}
  .tvs-ab b{display:block;font-weight:400;color:var(--tv-meta);font-size:1.05rem}.tvs-ab:hover b{text-decoration:underline}
  .tvs-abi{flex:0 0 40px;width:40px;height:40px;border-radius:999px;border:2px solid var(--tv-umber);display:flex;align-items:center;justify-content:center;color:var(--tv-ink);background:var(--tv-card-a)}
  .tvs-ab:hover .tvs-abi{background:var(--tv-sage);color:var(--tv-umber)}
  @media (max-width:767px){.tvs-hd{gap:.4rem 1rem}.tvs-loc,.tvs-dir{font-size:1rem}.tvs-cols{grid-template-columns:minmax(0,1fr)}.tvs-side{border-top:0;padding-top:0}}
  /* Small text throughout the search panel (Jason, 2026-10-02): headings .85rem, rows .82rem, second lines .72rem.
     Labels never wrap ("Search Directory:" on one line). */
  .tvs-panel .tvs-loc,.tvs-panel .tvs-dir,.tvs-panel .tvs-reach,.tvs-panel .tvs-h,.tvs-panel .tvs-sith{font-size:.85rem}
  .tvs-panel .tvs-loc,.tvs-panel .tvs-dir,.tvs-panel .tvs-reach{white-space:nowrap;flex:0 0 auto}
  .tvs-panel .tvs-dsel{font-size:.78rem;min-width:7.5rem}.tvs-panel .tvs-rngv{font-size:.75rem;min-width:3.6rem}
  .tvs-panel .tvs-hd{gap:.4rem 1rem}
  .tvs-panel .tvs-row,.tvs-panel .tvs-sg,.tvs-panel .tvs-qr,.tvs-panel .tvs-lb,.tvs-panel .tvs-recent .tvs-lb,.tvs-panel .tvs-try b,.tvs-panel .tvs-ab,
  .tvs-panel .tvs-note,.tvs-panel .tvs-intent,.tvs-panel .tvs-danger,.tvs-panel .tvs-ask p,.tvs-panel .tvs-none-h,.tvs-panel .tvs-where,.tvs-panel .tvs-other>summary{font-size:.82rem}
  .tvs-panel .tvs-row small,.tvs-panel .tvs-muted,.tvs-panel .tvs-foot,.tvs-panel .tvs-empty,.tvs-panel .tvs-chip,.tvs-panel .tvs-sc,.tvs-panel .tvs-btn,
  .tvs-panel .tvs-more,.tvs-panel .tvs-showmore,.tvs-panel .tvs-widen .tvs-chipb,.tvs-panel .tvs-needh,.tvs-panel .tvs-call,.tvs-panel .tvs-ab b{font-size:.72rem}
  .tvs-panel .tvs-sg i,.tvs-panel .tvs-qfl .tvs-ftag,.tvs-panel .tvs-lbl,.tvs-panel .tvs-n,.tvs-panel .tvs-go,.tvs-panel .tvs-kind{font-size:.62rem}
  .tvs-panel .tvs-row{padding:.32rem .5rem}.tvs-panel .tvs-qr{padding:.08rem .4rem}.tvs-panel .tvs-sg{padding:.22rem .45rem}
  .tvs-offers{margin:.15rem 0 .5rem .5rem;font-size:.75rem;color:var(--tv-meta)}.tvs-offers b{font-weight:600;color:var(--tv-ink)}
  .tvs-unread{margin:.3rem 0 .5rem;padding:.5rem .65rem;border-left:3px solid var(--tv-line-strong);background:var(--tv-canvas);border-radius:.25rem}.tvs-unread p{margin:0 0 .2rem}
  .tvs-panel .tvs-askc{padding:.3rem .6rem}.tvs-panel .tvs-askc b{font-size:.82rem}.tvs-panel .tvs-askc small,.tvs-panel .tvs-or{font-size:.72rem}
  .tvs-cmp{display:flex;align-items:center;gap:.55rem;width:100%;border:0;border-bottom:1px solid var(--tv-line);background:none;text-align:left;cursor:pointer;padding:.3rem .5rem;font:inherit;font-size:.82rem;color:var(--tv-meta)}
  .tvs-cmp b{font-weight:700;color:var(--tv-ink)}.tvs-cmp:hover,.tvs-cmp:focus-visible{background:var(--tv-canvas);outline:0}.tvs-cmpi{display:inline-flex;color:var(--tv-taupe);flex:0 0 auto}
  /* while typing, only the predictions (decisions/0061): results, Suggestions and the side column wait for a tap or Enter */
  .tvs-panel.tvs-typing .tvs-full,.tvs-panel.tvs-typing .tvs-sugw,.tvs-panel.tvs-typing .tvs-side{display:none}
  .tvs-panel.tvs-typing .tvs-cols{grid-template-columns:minmax(0,1fr)}
  /* the search tools: one line on a phone, opened with a tap */
  .tvs-tooltg{display:none}
  /* no built-in clear × inside the search boxes (Jason): the close ✕ sits past the pin; Esc clears */
  .tvs-in::-webkit-search-cancel-button,.tvs-in2::-webkit-search-cancel-button,.lm-in::-webkit-search-cancel-button{-webkit-appearance:none;appearance:none;display:none}.tvs-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
  @media (max-width:767px){
    .tvs-tooltg{display:flex;align-items:center;gap:.3rem;width:100%;border-bottom:1px solid var(--tv-line-strong);padding:.1rem .1rem .25rem}
    .tvs-tbtn{flex:1;min-width:0;display:flex;align-items:center;gap:.4rem;border:0;background:none;padding:0;font:inherit;font-family:var(--tv-font-head);font-size:.85rem;color:var(--tv-ink);cursor:pointer;text-align:left}
    .tvs-tbtn .tvs-tsum{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tvs-tbtn b{font-weight:400}
    .tvs-tbtn small{font-family:ui-sans-serif,var(--tv-font-ui);font-size:.72rem;color:var(--tv-meta)}.tvs-tcar{color:var(--tv-link);font-size:.8rem}
    .tvs-hd>.tvs-loc{display:none}   /* the toggle line is the Location line */
    .tvs-hd:not(.open)>.tvs-dir,.tvs-hd:not(.open)>.tvs-reach{display:none}
    .tvs-hd{padding-bottom:.3rem}
  }
  .tvs-panel .tvs-in2{font-size:16px}   /* the phone's box stays 16px so iOS doesn't zoom in */
  .tvn-bell{position:relative;display:flex;align-items:center;justify-content:center;width:40px;height:40px;color:var(--tv-meta)}
  .tvn-bell svg{width:30px;height:30px}.tvn-bell:hover{color:var(--tv-on-umber-meta)}
  .tvn-cart svg{width:28px;height:28px}
  @media (max-width:767px){ .tvn-bell{width:34px} }   /* cart and bell side by side on a phone */
  .tvn-badge[hidden]{display:none}
  .tvn-badge{position:absolute;top:2px;right:0;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:var(--tv-copper);color:#fff;font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center}
  .tvn-tab{display:flex;align-items:center;justify-content:center;width:14px;height:26px;background:var(--tv-taupe);color:var(--tv-ink);border:0;border-radius:.35rem 0 0 .35rem;cursor:pointer;padding:0;margin-right:-.5rem}
  .tvn-tab svg{width:10px;height:10px}
  .tvn-box{display:flex;align-items:center;gap:.45rem;border:1.5px solid var(--tv-taupe);border-radius:.8rem;padding:.3rem .45rem}
  .tvn-funnels{display:flex;align-items:center;gap:.45rem}
  .tvn-f{position:relative;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:999px;background:var(--tv-card-a);color:var(--tv-ink);border:3px solid var(--tv-taupe);text-decoration:none}
  .tvn-f svg{width:22px;height:22px}
  .tvn-f:hover{border-color:var(--tv-line-strong)}
  .tvn-f.on{background:var(--tv-sage);box-shadow:0 0 0 2px rgba(143,188,168,.35),0 0 12px rgba(143,188,168,.85)}
  .tvn-f.soon{opacity:.55;cursor:default}
  .tvn-f.me.on,.tvn-f.me.in{background:var(--tv-sage)}
  .tvn-menu{display:flex;align-items:center;justify-content:center;width:44px;height:40px;color:var(--tv-meta);background:none;border:0;cursor:pointer}
  .tvn-menu svg{width:32px;height:32px}.tvn-menu:hover{color:var(--tv-on-umber-meta)}
  #menu-location-dot{display:none!important}   /* the ☰ no longer glows when a location is set: location has its own pin in search (Jason, 2026-11-05) */
  .tvn-up{background:var(--tv-btn-inv);color:var(--tv-btn-inv-text);border:1px solid var(--tv-copper);border-radius:999px;padding:.4rem .95rem;font:700 .85rem var(--tv-font-ui);text-decoration:none;white-space:nowrap}
  .tvn-up:hover{background:var(--tv-btn-inv-hover);border-color:var(--tv-icon,#B45309)}
  .tvn-in{color:var(--tv-on-umber);border:1px solid var(--tv-taupe);border-radius:999px;padding:.38rem .85rem;font:600 .85rem var(--tv-font-ui);text-decoration:none;white-space:nowrap}
  .tvn-in:hover{border-color:var(--tv-line);color:#fff}
  .tvn-acct{display:flex;flex-direction:column;gap:.4rem;padding:.35rem .9rem .6rem}.tvn-acct.hidden{display:none}
  .tvn-acct .tvn-up,.tvn-acct .tvn-in{text-align:center;display:block}.tvn-acct .tvn-in{color:var(--tv-meta);border-color:var(--tv-link)}
  /* the open menu over everything on the page: the home page's search box sits at 1372 (index.php), above the header's 40 */
  :is(#top-bar,#tv-nav):has(#site-menu-panel:not(.hidden)){z-index:1400!important}
  body:has(.tv-as) :is(.tvn-acct,.tvpm-acct){display:none!important}   /* signed in (the account bar is there): never the visitor buttons, even before the page's script knows */
  @media (min-width:768px){.tvn-mw.tvn-signed,body:has(.tv-as) .tvn-mw{display:none}}   /* signed in, wider screens: the account bar has you; no menu */
  .tvn-me{display:none}   /* you, in the menu corner: phones on the signed-in side only (app-shell.php) */
  .tv-nav-min .tvn-funnels{display:none}
  .tv-nav-min .tvn-tab svg{transform:scaleX(-1)}
  .tv-nav-min .tvn-box{border-color:transparent;padding:0}   /* the slim bar: just the menu */
  .tv-nav-min .tvn-bell,.tv-nav-min .tvn-menu{height:36px}
  .tv-nav-min .tvs-in{padding-top:.4rem;padding-bottom:.4rem}
  @media (max-width: 767px){ .tvn-funnels{display:none!important} .tvn-tab{display:none} .tvn-box{border:0;padding:0} }
  /* The phone menu (decisions/0055, Jason's mockup): full screen over a topographic background. */
  .tvpm{position:fixed;inset:0;z-index:1500;overflow:auto;background:var(--tv-canvas);color:var(--tv-ink);
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='420' height='420' viewBox='0 0 420 420'%3E%3Cg fill='none' stroke='%23D9C7A3' stroke-width='1.6'%3E%3Cpath d='M60 90c40-50 120-40 150 0s10 110-50 120-150-20-100-120z'/%3E%3Cpath d='M85 100c30-30 85-25 105 5s0 75-45 80-100-35-60-85z'/%3E%3Cpath d='M112 112c18-15 50-12 60 5s-4 42-30 44-50-24-30-49z' stroke-dasharray='5 5'/%3E%3Cpath d='M250 250c50-40 140-20 150 40s-60 120-130 100-70-100-20-140z'/%3E%3Cpath d='M275 265c35-25 95-10 100 30s-45 80-90 65-45-70-10-95z' stroke-dasharray='5 5'/%3E%3Cpath d='M300 285c18-12 48-4 50 15s-22 40-45 32-22-36-5-47z'/%3E%3Cpath d='M-20 300c40 10 70 60 60 110M20 260c50 20 90 80 80 160'/%3E%3Cpath d='M300 -10c-10 40 20 80 80 90M340 -10c0 30 30 50 90 55' stroke-dasharray='5 5'/%3E%3C/g%3E%3C/svg%3E")}
  .tvpm[hidden]{display:none}
  .tvpm .hidden{display:none !important}   /* signed in: Login stays hidden (the menu's own display would win) */
  .tvpm-x{position:absolute;top:.9rem;right:1rem;width:40px;height:40px;border:0;background:none;color:var(--tv-ink);font-size:2rem;line-height:1;cursor:pointer}
  .tvpm-in{max-width:22rem;margin:0 auto;padding:1.5rem 1.5rem 2.5rem;display:flex;flex-direction:column;align-items:center}
  /* the quieter menu (Jason, 2026-11-22, option B): 1.35rem/700 words, a 96px logo, Help Center in sentence case, so it fits one screen */
  .tvpm-logo{width:96px;height:96px;margin:.2rem 0 .9rem}
  .tvpm-list{display:flex;flex-direction:column;gap:.65rem;align-self:stretch;padding-left:1.2rem}
  .tvpm-a{display:flex;align-items:center;gap:.7rem;font-weight:700;font-size:1.35rem;color:var(--tv-ink);text-decoration:none;font-family:ui-sans-serif,var(--tv-font-ui);letter-spacing:-.01em}
  .tvpm-a .ic{flex:none;display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;border:2.5px solid var(--tv-umber);background:var(--tv-card-a)}
  .tvpm-a .ic svg{width:19px;height:19px}
  .tvpm-a.soon{opacity:.5}.tvpm-a small{font-size:.8rem;font-weight:600;color:var(--tv-meta)}
  .tvpm-help{margin-top:1.4rem;background:var(--tv-btn);color:var(--tv-btn-text);border-radius:999px;padding:.45rem 2.3rem;font-weight:700;text-decoration:none;font-size:1rem}
  .tvpm-acct{align-self:stretch;display:flex;flex-direction:column;gap:.55rem;margin:0 0 1.3rem}
  .tvpm-up{display:block;text-align:center;background:var(--tv-btn);color:var(--tv-btn-text);border-radius:.7rem;padding:.75rem;font:800 1.1rem var(--tv-font-ui);text-decoration:none}
  .tvpm-sin{display:block;text-align:center;border:2px solid var(--tv-umber);color:var(--tv-ink);border-radius:.7rem;padding:.6rem;font:700 1rem var(--tv-font-ui);text-decoration:none}   /* Sign in: its own class (it shared .tvpm-in with the menu's column, which took its outline and width, 2026-11-20) */
  .tvpm-sub{margin-top:1.1rem;display:flex;flex-wrap:wrap;gap:.4rem 1.1rem;justify-content:center;font-size:.9rem}
  .tvpm-sub a,.tvpm-sub button{color:var(--tv-meta);text-decoration:underline;background:none;border:0;cursor:pointer;font-size:.9rem}
</style>
<nav id="tv-main-nav" class="tvn" aria-label="Main">
  <?php if ($tv_search): ob_start(); ?>
  <div id="tv-search" class="tvs-bar" role="search">
    <span class="tvs-icon"><?= $tv_i['search'] ?></span>
    <input class="tvs-in" type="search" placeholder="Search places, listings, groups, stories" autocomplete="off" aria-label="Search Traversence">
    <span class="tvs-btns"><button type="button" class="tvs-sbtn" title="Search" aria-label="Search"><?= $tv_i['search'] ?></button>
  <button type="button" class="tvs-pin" data-tv-click="tvSearchWhere" title="Choose where to search" aria-label="Choose where to search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><span class="tvs-pl" data-tvs-pl>Anywhere</span><i class="tvs-car" aria-hidden="true">&#9662;</i></button></span>
    <button type="button" class="tvs-open" aria-label="Search Traversence"><?= $tv_i['search'] ?><span>Search places, listings…</span></button>
  </div>
  <?php $tv_search_bar = (string) ob_get_clean();
  if ($tv_admin_search) echo '<div id="tv-asearch" class="tvs-bar" role="search"><span class="tvs-icon">' . $tv_i['search'] . '</span><input class="tvs-in" type="search" placeholder="Search the admin…  Ctrl K" autocomplete="off" aria-label="Search the admin">'
      . '<button type="button" class="tvs-open" data-tva aria-label="Search the admin">' . $tv_i['search'] . '<span>Search the admin…</span></button></div><div hidden>' . $tv_search_bar . '</div>';
  elseif ($tv_fold) echo str_replace('class="tvs-bar" role="search"', 'class="tvs-bar tvs-fold" role="search"', $tv_search_bar);   // the page has its own bar: the header's folds to ⌕ (2026-11-19)
  elseif (!$tv_home) echo $tv_search_bar; endif; ?>
  <?php if (!$tv_signin): require_once __DIR__ . '/section-rails.php'; $tv_cart = tv_go_cart_count(); ?>
  <a id="tv-cart" class="tvn-bell tvn-cart" href="/qr/cart.php" title="Cart<?= $tv_cart ? ' (' . $tv_cart . ')' : '' ?>" aria-label="Cart<?= $tv_cart ? ', ' . $tv_cart . ' item' . ($tv_cart === 1 ? '' : 's') : '' ?>">
    <?= $tv_i['cart'] ?><span class="tvn-badge" data-go-count<?= $tv_cart ? '' : ' hidden' ?>><?= $tv_cart ?: '' ?></span>
  </a>
  <a id="tv-bell" class="tvn-bell" href="/user/dashboard.php#notifications" title="Notifications" aria-label="Notifications">
    <?= $tv_i['bell'] ?><span class="tvn-badge" id="tv-bell-n" hidden></span>
  </a>
  <?php endif; ?>
  <button type="button" class="tvn-tab" data-tv-toggle="nav" aria-label="Collapse or expand the menu icons" title="Collapse or expand">
    <svg viewBox="0 0 10 10" aria-hidden="true"><path d="M3 1l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
  </button>
  <div class="tvn-box">
    <span class="tvn-funnels">
      <?php foreach ($tv_funnels as [$tv_k, $tv_label, $tv_url, $tv_icon, $tv_on]): ?>
        <?php if ($tv_url): ?>
          <a class="tvn-f<?= $tv_on ? ' on' : '' ?>" href="<?= $tv_e($tv_url) ?>" title="<?= $tv_e($tv_label) ?>" aria-label="<?= $tv_e($tv_label) ?>"<?= $tv_on ? ' aria-current="page"' : '' ?>><?= $tv_i[$tv_icon] ?></a>
        <?php else: ?>
          <span class="tvn-f soon" title="<?= $tv_e($tv_label) ?>: coming soon" aria-label="<?= $tv_e($tv_label) ?>, coming soon" role="img"><?= $tv_i[$tv_icon] ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </span>
    <div class="relative tvn-mw<?= $tv_in ? ' tvn-signed' : '' ?>">
      <button type="button" id="icon-menu-btn" class="tvn-menu" title="Menu" aria-label="Open menu"
              aria-haspopup="true" aria-expanded="false" aria-controls="site-menu-panel"><?= $tv_i['bars'] ?>
        <span id="menu-location-dot" class="hidden absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-[var(--tv-sage)]" aria-hidden="true"></span>
      </button>
      <div id="site-menu-panel" role="menu" aria-labelledby="icon-menu-btn" class="absolute right-0 top-full pt-2 w-60 hidden z-50">
        <div class="bg-[var(--tv-card-b)] border border-[color:var(--tv-line-strong)] rounded-xl shadow-2xl py-2 text-xs">
          <div class="tvn-acct<?= $tv_in ? ' hidden' : '' ?>" data-auth="out"><a href="/login.php?tab=create" role="menuitem" class="tvn-up">Create account</a><a href="/login.php" role="menuitem" class="tvn-in">Sign in</a></div>
          <button type="button" role="menuitem" data-tv-click="toggleSiteMenu:true openLocationModal" class="<?= $tv_item ?>">
            <span class="block font-semibold">Set Location</span>
            <span id="menu-location-label" class="block text-[11px] text-[color:var(--tv-meta)] truncate">Not set</span>
          </button>
          <?php // visitors' essentials (Jason, 2026-11-05): signed in, who you are and signing out are in the account bar ?>
          <div class="my-1 border-t border-[color:var(--tv-line-strong)]"></div>
          <a href="/our-approach.php" role="menuitem" class="<?= $tv_item ?>">About Traversence</a>
          <a href="/plans.php" role="menuitem" class="<?= $tv_item ?>">Plans</a>
          <a href="/faq.php" role="menuitem" class="<?= $tv_item ?>">Help Center</a>
          <a href="mailto:support@traversence.com" role="menuitem" class="<?= $tv_item ?>">Contact us</a>
          <button type="button" role="menuitem" class="<?= $tv_item ?>" data-tv-suggest="{}">Make a suggestion</button>
        </div>
      </div>
    </div>
  </div>
</nav>
<?php   // wider screens: you and your roles open from the person icon above (decisions/0058 §33)
try { require_once __DIR__ . '/context-switcher.php'; echo tv_context_switcher(false); } catch (Throwable $e) { error_log('header switcher: ' . $e->getMessage()); } ?>
<?php if (!$tv_in): try { require_once __DIR__ . '/social-buttons.php'; ?>
<template id="tv-join-social"><?= tv_social_buttons('Continue with', '/__NEXT__') ?></template>
<script src="/js/tv-join.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-join.js') ?>" defer></script>
<?php } catch (Throwable $e) { error_log('join panel: ' . $e->getMessage()); } endif;   // a guest's account panel (js/tv-join.js; decisions/0009) ?>
<?php if ($tv_search): ?><script src="/js/map-view.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/map-view.js') ?>" defer></script><?php endif; ?>
<?= tv_on_js() ?><script src="/js/tv-ask.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-ask.js') ?>"></script><script src="/js/tv-pager.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-pager.js') ?>" defer></script><script src="/js/tv-trail.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-trail.js') ?>" defer></script><script src="/js/tv-password.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-password.js') ?>"></script><script src="/js/tv-upload.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-upload.js') ?>"></script><script src="/js/tv-conf.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/tv-conf.js') ?>" defer></script>
<?php // privacy choices (decisions/0077): the analytics service, if one is set up (.env ANALYTICS_SRC, ANALYTICS_NAME), and
// a signed-in person's account choices; consent.js loads analytics and videos only as allowed
require_once __DIR__ . '/../lib/Consent.php';
$tvUid = (class_exists('Auth') && ($tvMe = Auth::currentUser())) ? (int) $tvMe['id'] : null;
if ($tvUid) { try { require_once __DIR__ . '/../lib/EarnedStorage.php'; EarnedStorage::touch(Database::connection(), $tvUid); } catch (Throwable) {} }   // a day they used the site: earned storage counts settled members (decisions/0154 §7)
$tvCh = []; foreach (['analytics', 'video'] as $tvT) { $tvCh[$tvT] = null; try { $tvPdo = $tvUid ? Database::connection() : null; if ($tvUid && $tvPdo && Consent::ready($tvPdo) && (Consent::active($tvPdo, $tvUid, $tvT) || Consent::paused($tvPdo, $tvUid, $tvT))) $tvCh[$tvT] = Consent::allows($tvPdo, $tvUid, $tvT); } catch (Throwable) {} }
$tvA = tv_is_card_page() ? '' : trim((string) getenv('ANALYTICS_SRC'));   // no analytics on a page that takes a card (0098) ?>
<script<?= tv_nonce() ?>>window.TV_CHOICES = <?= json_encode($tvCh) ?>;<?php if ($tvA !== '' && preg_match('#^https://#', $tvA)): ?> window.TV_ANALYTICS = <?= json_encode(['src' => $tvA, 'name' => trim((string) getenv('ANALYTICS_NAME'))], JSON_UNESCAPED_SLASHES) ?>;<?php endif; ?></script>
<script src="/js/consent.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/consent.js') ?>" defer></script>
<script src="/js/suggest.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/suggest.js') ?>" defer></script>
<script src="/js/out-count.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/out-count.js') ?>" defer></script>
<script src="/js/short-link.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/short-link.js') ?>" defer></script>
<?php if ($tv_search): ?><script src="/js/search-panel.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/search-panel.js') ?>" defer></script><?php endif; ?>
<?php if ($tv_admin_search): ?><script src="/js/admin-search.js?v=<?= (int) @filemtime(__DIR__ . '/../../website/js/admin-search.js') ?>" defer></script><?php endif; ?>
<div class="tvpm" id="tv-phone-menu" role="dialog" aria-modal="true" aria-label="Menu" hidden>
  <button type="button" class="tvpm-x" data-tvpm-close aria-label="Close menu">&times;</button>
  <div class="tvpm-in">
    <a href="/" aria-label="Traversence home"><img class="tvpm-logo" src="/image/North-Star-logo.svg" alt="Traversence"></a>
    <div class="tvpm-acct<?= $tv_in ? ' hidden' : '' ?>" data-auth="out"><a class="tvpm-up" href="/login.php?tab=create">Create account</a><a class="tvpm-sin" href="/login.php">Sign in</a></div>
    <div class="tvpm-list">
      <?php // the compass: where you can go, the same on every page (the ☰ / your photo holds what you can do here; Jason, 2026-10-23)
      foreach ([['explore', "Let's Explore", 'explore'], ['local', 'Get Local', 'local'], ['social', 'Connect', 'social'], ['market', 'Marketplace', 'market'], ['plan', 'Trip planner', 'route']] as [$tv_k, $tv_label, $tv_icon]):
          $tv_url = $tv_k === 'plan' ? tv_route(['discovery/index.php' => '/discovery/?view=plan']) : null; foreach ($tv_funnels as $tv_f) if ($tv_f[0] === $tv_k) $tv_url = $tv_f[2]; ?>
        <?php if ($tv_url): ?><a class="tvpm-a" href="<?= $tv_e($tv_url) ?>"><span class="ic"><?= $tv_i[$tv_icon] ?></span><?= $tv_e($tv_label) ?></a>
        <?php else: ?><span class="tvpm-a soon"><span class="ic"><?= $tv_i[$tv_icon] ?></span><?= $tv_e($tv_label) ?> <small>soon</small></span><?php endif; ?>
      <?php endforeach; ?>
      <a class="tvpm-a hidden" href="/user/dashboard.php" data-auth="in"><span class="ic"><?= $tv_i['person'] ?></span>Dashboard</a>
      <?php $tv_ai = $tv_funnels[0][2]; ?>
      <?php if ($tv_ai): ?><a class="tvpm-a" href="<?= $tv_e($tv_ai) ?>"><span class="ic"><?= $tv_i['ai'] ?></span>AI Assist</a>
      <?php else: ?><span class="tvpm-a soon"><span class="ic"><?= $tv_i['ai'] ?></span>AI Assist <small>soon</small></span><?php endif; ?>
    </div>
    <a class="tvpm-help" href="/faq.php">Help Center</a>
    <div class="tvpm-sub">
      <button type="button" data-tv-click="toggleSiteMenu:true openLocationModal">Set location: <span id="tvpm-loc">not set</span></button>
      <a href="/our-approach.php">About</a>
      <a href="/plans.php">Plans</a>
      <a href="mailto:support@traversence.com">Contact</a>
      <a href="/privacy.php">Privacy</a>
      <a href="/terms.php">Terms</a>
      <?php if ($tv_admin): ?><a href="<?= $tv_e($tv_admin) ?>" data-auth="admin" class="hidden"><?= $tv_e($tv_admin_label) ?></a><?php endif; ?>
    </div>
  </div>
</div>
<?php
// A notice of action on this account (decisions/0079): a bar under the top bar on every page until it's dealt with.
// Page loads also run the notices' clock (Standing::tickSoon, at most every few minutes).
if (class_exists('Database') && class_exists('Auth') && is_file(__DIR__ . '/../lib/Standing.php')) {
    require_once __DIR__ . '/../lib/Standing.php';
    try {
        $tv_pdo = Database::connection();
        Standing::tickSoon($tv_pdo);
        // closed accounts whose 30 days are up are deleted (decisions/0091), at most every 30 minutes
        if (is_file(__DIR__ . '/../lib/AccountDeletion.php')) { require_once __DIR__ . '/../lib/AccountDeletion.php'; AccountDeletion::tickSoon($tv_pdo); }
        $tv_nu = Auth::currentUser();
        $tv_nt = $tv_nu ? Standing::current($tv_pdo, (int) $tv_nu['id']) : null;
    } catch (Throwable) { $tv_nt = null; }
    if ($tv_nt && !str_starts_with($tv_path, '/user/notice')):
        $tv_txt = $tv_nt['status'] === 'review' ? 'You asked for a review of a notice on your account. A person will reply by email.'
            : ((int) $tv_nt['step'] >= 2 ? 'Final warning: posting and messaging are stopped on your account.' : 'There\'s a notice on your account that needs you within 24 hours.'); ?>
<div id="tv-notice" role="status" style="background:<?= (int) $tv_nt['step'] >= 2 && $tv_nt['status'] === 'open' ? 'var(--tv-fail)' : 'var(--tv-link)' ?>;color:var(--tv-on-umber);font-size:.88rem;padding:.45rem .9rem;display:flex;gap:.6rem;align-items:center;justify-content:center;flex-wrap:wrap;text-align:center">
  <span><?= $tv_e($tv_txt) ?></span><a href="/user/notice.php" style="color:var(--tv-on-umber);font-weight:700;text-decoration:underline;white-space:nowrap">See the notice &rarr;</a>
</div>
<script<?= tv_nonce() ?>>(function () { var n = document.getElementById('tv-notice'), t = document.getElementById('top-bar'); if (n && t && t.parentNode) t.parentNode.insertBefore(n, t.nextSibling); })();</script>
<?php endif;
}
// Staff signed in with no authenticator app (3f B8, decisions/0100's note of 2026-11-22): one notice on the admin side to add
// it, so a lost work email still leaves a way in; "Not now" (or its ×) is remembered for that staff login (staff.php
// app_nudge_off). A small bar fixed at the foot of the window, clear of the admin's fixed headers.
if (!$tv_signin && preg_match('#^/admin(/|$)#', $tv_path) && class_exists('Database') && is_file(__DIR__ . '/../lib/StaffLogin.php')) {
    try {
        require_once __DIR__ . '/../lib/StaffLogin.php';
        $tv_pdo = $tv_pdo ?? Database::connection();
        $tv_sl = StaffLogin::ready($tv_pdo) ? StaffLogin::inSession($tv_pdo) : null;
        if ($tv_sl && !StaffLogin::hasApp($tv_sl)) {
            $tv_q = $tv_pdo->prepare('SELECT 1 FROM ' . Database::t('staff_settings') . ' WHERE name = ?'); $tv_q->execute(['appnudge:' . (int) $tv_sl['id']]);
            if (!$tv_q->fetchColumn()): ?>
<div id="tv-appnudge" role="status" style="position:fixed;left:50%;transform:translateX(-50%);bottom:.75rem;z-index:1300;width:min(42rem,calc(100% - 6px));box-sizing:border-box;background:var(--tv-card-b,#F7EFDC);color:var(--tv-ink,#3E2B1F);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.6rem;box-shadow:0 6px 20px rgba(62,43,31,.25);font-size:.88rem;line-height:1.4;padding:.55rem 2.2rem .55rem .9rem;display:flex;gap:.3rem .8rem;align-items:center;flex-wrap:wrap">
  <span style="flex:1 1 16rem">Add an authenticator app to your staff login: if your work email is slow or out of reach, its code still signs you in.</span>
  <a href="/admin/staff.php#my-app" style="color:var(--tv-link);font-weight:700;text-decoration:underline;white-space:nowrap">Add it</a>
  <form method="post" action="/admin/staff.php" style="display:contents"><input type="hidden" name="csrf" value="<?= $tv_e((string) ($_SESSION['csrf_token'] ?? '')) ?>"><input type="hidden" name="do" value="app_nudge_off"><input type="hidden" name="back" value="<?= $tv_e((string) ($_SERVER['REQUEST_URI'] ?? '/admin/')) ?>"><button style="background:none;border:0;color:var(--tv-meta,#6B5A45);text-decoration:underline;cursor:pointer;font-size:.85rem;padding:0;white-space:nowrap">Not now</button><button aria-label="Not now" title="Not now" style="position:absolute;top:.2rem;right:.35rem;background:none;border:0;font-size:1.3rem;line-height:1;color:var(--tv-meta,#6B5A45);cursor:pointer;padding:.2rem .3rem">&times;</button></form>
</div>
<script<?= tv_nonce() ?>>document.addEventListener('DOMContentLoaded', function () { var n = document.getElementById('tv-appnudge'); if (!n) return; document.body.appendChild(n);
  // the page keeps room for it at its foot, so it never covers a page's last button (a record page's Save, the way-back bar)
  var pad = function () { document.body.style.paddingBottom = (n.offsetHeight + 24) + 'px'; }; pad(); addEventListener('resize', pad); });   // out of the header's stacking, over the page</script>
<?php       endif;
        }
    } catch (Throwable $tv_x) { error_log('app nudge: ' . $tv_x->getMessage()); }
}
// The zone on every page under the header (decisions/0159 step 4c, Blocks::site): what staff publish there shows on the
// browsing pages, never on the admin, sign-in or payment pages. The footer's zone follows this page's answer.
// This file is drawn inside the top bar's own row (a flex row, sticky), so the zone is kept hidden there and moved at
// once to just under the bar, as the account notice above is (Jason, 2026-11-14: on the home page it sat squeezed at the
// bar's right edge and stretched the bar). Where there's no top bar to put it under (or the bar is fixed over the page),
// it stays hidden, never in the row.
if (is_file(__DIR__ . '/../lib/Blocks.php')) {
    try {
        require_once __DIR__ . '/../lib/Blocks.php';
        $tv_site_here = ($tv_search || $tv_home) && !tv_is_card_page() && !preg_match('#^/(admin|staging|user/|qr/(cart|order)|market/(cart|order))#', $tv_path);
        $tv_site_top = Blocks::site('site.top', $tv_site_here, $tv_home);
        if ($tv_site_top !== '') {
            echo '<div id="tv-site-top" style="display:none">' . $tv_site_top . '</div>'
                . '<script' . tv_nonce() . '>(function () { var z = document.getElementById(\'tv-site-top\'), t = document.getElementById(\'top-bar\'), n = document.getElementById(\'tv-notice\');'
                . ' if (!z || !t || !t.parentNode || getComputedStyle(t).position === \'fixed\') return; var a = n && n.parentNode === t.parentNode ? n : t;'
                . ' a.parentNode.insertBefore(z, a.nextSibling); z.style.display = \'\'; })();</script>';
        }
    } catch (Throwable $e) { error_log('site zone: ' . $e->getMessage()); }
}
