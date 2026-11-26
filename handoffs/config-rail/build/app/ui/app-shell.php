<?php
/**
 * includes/app-shell.php — the one page layout (decisions/0055 §1).
 *
 *   Left:  the toolbar, full height along the left edge under the header. Quick access to everywhere the person
 *          needs to be in this part of the site (or in their current role). Icons; the tab at its foot expands it
 *          to show labels, and the choice is remembered. Opens and closes instantly (no transitions).
 *   Right: the workspace: the content or the task at hand.
 *   Phone: the toolbar becomes one row of icons across the top of the workspace, swipeable.
 *
 *   require_once __DIR__ . '/../../app/ui/app-shell.php';   // from a page one folder down (website/<folder>/)
 *   tv_app_start('Title', [
 *       'rail'   => [ ['key' => 'overview', 'label' => 'Overview', 'url' => '/discovery/', 'icon' => 'compass', 'badge' => 0, 'soon' => false,
 *                      'section' => 'Explore'], ... ],   // 'section' starts a labelled group
 *       'active' => 'overview',
 *       'rail_label' => "Let's Explore",
 *       'crumbs' => [['Home', '/'], ['Title']],          // optional
 *       'intro'  => 'One line under the title',           // optional (HTML allowed)
 *       'description' => 'meta description',
 *   ]);
 *   ... workspace content ...
 *   tv_app_end();
 */
require_once __DIR__ . '/page-shell.php';

/** Line icons (24x24, stroke). */
function tv_icon(string $name, string $cls = 'ta-svg'): string
{
    static $p = [
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        // in place of emoji (site audit P4, 2026-11-13): a map pin, a clock, a phone, a comment, play, a car, a heart
        'pin'      => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'comment'  => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/>',
        'play'     => '<polygon points="6 4 20 12 6 20 6 4"/>',
        'car'      => '<path d="M5 17h14v-5l-2-5H7l-2 5z"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/><path d="M5 12h14"/>',
        'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'bell'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'pen'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'eye'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'sparkle'  => '<path d="M12 3l1.9 5.6L19.5 10.5l-5.6 1.9L12 18l-1.9-5.6L4.5 10.5l5.6-1.9z"/><path d="M19 3v4M17 5h4"/>',
        'help'     => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'compass'  => '<circle cx="12" cy="12" r="10"/><polygon points="16.2 7.8 14.1 14.1 7.8 16.2 9.9 9.9 16.2 7.8"/>',
        'globe'    => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'map'      => '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>',
        'book'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'tree'     => '<path d="M12 2 5 12h4l-3 5h12l-3-5h4z"/><line x1="12" y1="17" x2="12" y2="22"/>',
        'landmark' => '<line x1="3" y1="22" x2="21" y2="22"/><line x1="6" y1="18" x2="6" y2="11"/><line x1="10" y1="18" x2="10" y2="11"/><line x1="14" y1="18" x2="14" y2="11"/><line x1="18" y1="18" x2="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
        'utensils' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'route'    => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
        'bookmark' => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
        'pulse'    => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'inbox'    => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'percent'  => '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'orders'   => '<path d="M8 4H6.5A1.5 1.5 0 0 0 5 5.5v15A1.5 1.5 0 0 0 6.5 22H12"/><path d="M16 4h1.5A1.5 1.5 0 0 1 19 5.5V11"/><rect x="9" y="2" width="6" height="4" rx="1"/><polyline points="8 10.5 9.5 12 12 9"/><line x1="14" y1="10.5" x2="16" y2="10.5"/><line x1="8.5" y1="16" x2="11" y2="16"/><circle cx="17.5" cy="17.5" r="2.6"/><path d="M17.5 13.4v1.2M17.5 20.4v1.2M13.4 17.5h1.2M20.4 17.5h1.2"/>',   // order management: a checked clipboard with a gear (Jason, 2026-10-30)
        'truck'    => '<path d="M1 4h14v12H1z"/><path d="M15 9h4l3 3v4h-7"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/>',
        'cart'     => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'contact'  => '<path d="M16 2v2"/><path d="M8 2v2"/><rect x="3" y="4" width="18" height="18" rx="2"/><circle cx="12" cy="11" r="3"/><path d="M7 20a5 5 0 0 1 10 0"/>',
        'store'    => '<path d="M3 9 4.5 4h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M10 20v-5h4v5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'home'     => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'camera'   => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'qr'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'filter'   => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'power'    => '<path d="M18.4 6.6a9 9 0 1 1-12.8 0"/><line x1="12" y1="2" x2="12" y2="12"/>',
        'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        // the pre-flight's marks (2026-11-17): failed and not needed yet, beside 'check'
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'dash'     => '<path d="M5 12h14"/>',
    ];
    // tv-ei: an icon inside a line of text, the size of its letters (in place of an emoji, site audit P4)
    $size = str_contains(' ' . $cls . ' ', ' tv-ei ') ? ' width="1em" height="1em" style="vertical-align:-.125em;flex:0 0 auto"' : '';
    return '<svg class="' . $cls . '"' . $size . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['compass']) . '</svg>';
}

function tv_app_start(string $title, array $opt = []): void
{
    tv_page_start($title, ['app' => true, 'description' => $opt['description'] ?? $title]);
    tv_app_open($title, $opt);
}

/** The layout's styles and the remembered labels setting (once per page, before the rail). */
function tv_app_css(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    require_once __DIR__ . '/theme-tokens.php';
    echo tv_theme_tokens();   // already in the <head> from tv_page_start; here only for a page drawn without it
    ?>
<style>
  /* the shell on the design tokens (app/ui/theme-tokens.php, design/css-theme-brand.md Release 1): no colour of its own */
  body{margin:0}
  .ta{display:flex;align-items:stretch;min-height:calc(100vh - var(--ta-top,64px))}
  .ta *,.ta *::before,.ta *::after{box-sizing:border-box}
  /* the toolbar: flush with the left edge, full height under the header */
  /* fixed like the header: it stays put while the workspace (and the footer) scroll beside it */
  .ta{padding-left:64px}html.ta-wide .ta{padding-left:228px}
  body:has(#ta-rail) footer{margin-left:64px}html.ta-wide body:has(#ta-rail) footer{margin-left:228px}
  .ta-rail{position:fixed;left:0;top:var(--ta-rt,var(--ta-top,64px));bottom:0;width:64px;z-index:30;
    display:flex;flex-direction:column;background:var(--tv-umber);border-right:1px solid var(--tv-void);box-shadow:4px 0 14px -8px rgba(35,29,23,.45);overflow-y:auto;overflow-x:hidden;scrollbar-width:none}
  .ta-rail::-webkit-scrollbar{display:none}
  html.ta-wide .ta-rail{width:228px}
  /* the slim header (brand.php): no bar, so the toolbar runs to the top, under the compass */
  @media (min-width:768px){html.tv-brand-min.tv-nav-min .ta-rail{top:0;padding-top:var(--ta-top,48px);box-shadow:4px 0 14px -8px rgba(35,29,23,.45)}}
  .ta-name{font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-on-umber-meta);padding:.8rem .6rem .35rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center}
  html.ta-wide .ta-name{text-align:left;padding-left:1rem}
  html:not(.ta-wide) .ta-name{display:none}
  .ta-sec{height:1px;background:var(--tv-umber-line);margin:.35rem .7rem}
  .ta-back{margin:0 0 .35rem;font-size:.82rem}.ta-back a{color:var(--tv-link);text-decoration:none;font-weight:600}.ta-back a:hover{text-decoration:underline}
  html.ta-wide .ta-sec{height:auto;background:none;margin:.7rem 0 .15rem;padding:0 1rem;font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-on-umber-meta)}
  html:not(.ta-wide) .ta-sec span{display:none}
  /* a flat row: no box, nothing moves. The row you're on is tinted and its icon lights up, like the top bar */
  .ta-i{position:relative;display:flex;align-items:center;gap:.75rem;margin:0;height:44px;border-radius:0;padding:0 21px;color:var(--tv-on-umber-meta);text-decoration:none;white-space:nowrap}
  .ta-i:hover{background:rgba(255,251,242,.05);color:var(--tv-on-umber)}
  .ta-i:focus-visible{outline:2px solid var(--tv-focus-dark);outline-offset:-2px}
  .ta-i.on{background:rgba(255,251,242,.09);color:var(--tv-on-umber)}
  .ta-i.on .ta-svg{color:var(--tv-sage);filter:drop-shadow(0 0 6px color-mix(in srgb,var(--tv-sage) 85%,transparent))}
  .ta-i.soon{opacity:.45}
  .ta-svg{width:22px;height:22px;flex:0 0 22px}
  .ta-i,.ta-gt,.ta-tog,.cx{transition:background-color var(--tv-ease),color var(--tv-ease)}.ta-gt svg,.ta-mechev{transition:transform var(--tv-ease)}   /* brand.md §8 */
  /* groups: a parent with its tools underneath, opened and closed with the chevron */
  .ta-gh{position:relative}html.ta-wide .ta-gh>.ta-i{padding-right:36px}
  .ta-gt{position:absolute;right:.55rem;top:50%;transform:translateY(-50%);width:26px;height:26px;border:0;border-radius:.5rem;background:none;color:var(--tv-on-umber-meta);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
  .ta-gt:hover{background:var(--tv-umber-line);color:var(--tv-on-umber)}.ta-gt svg{width:15px;height:15px}
  .ta-gt[aria-expanded="true"] svg{transform:rotate(90deg)}
  html:not(.ta-wide) .ta-gt{right:0;top:auto;bottom:-2px;transform:none;width:16px;height:16px}html:not(.ta-wide) .ta-gt svg{width:11px;height:11px}
  .ta-sub{border-left:2px solid var(--tv-umber-line);margin:0 .5rem 0 1.35rem}
  html:not(.ta-wide) .ta-sub{margin:0 .35rem;border-left:0;border-top:1px dashed var(--tv-umber-line);border-bottom:1px dashed var(--tv-umber-line)}
  .ta-sub[hidden]{display:none}
  .ta-i.ta-ph{display:none}   /* a group's own page, inside it: phones only */
  .ta-i.ta-ch{height:36px;margin-left:.35rem}.ta-i.ta-ch .ta-svg{width:18px;height:18px;flex-basis:18px}.ta-i.ta-ch .ta-l{font-size:.82rem;font-weight:500}
  html:not(.ta-wide) .ta-i.ta-ch{margin-left:.5rem}
  .ta-l{font-size:.88rem;font-weight:600;overflow:hidden;text-overflow:ellipsis}
  html:not(.ta-wide) .ta-l,html:not(.ta-wide) .ta-soon{display:none}
  .ta-soon{margin-left:auto;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tv-on-umber-meta)}
  .ta-b{position:absolute;top:4px;left:28px;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:var(--tv-copper);color:#fff;font-size:10px;font-weight:700;line-height:17px;text-align:center}
  /* the wide menu (names showing): the count sits at the end of its row, not on the icon, and a long name wraps rather
     than being cut short ("Workbench & missed searches"); the narrow, icons-only menu keeps the count on the icon */
  html.ta-wide .ta-b{position:static;margin-left:auto;flex:none}
  html.ta-wide .ta-i{white-space:normal;height:auto;min-height:44px;padding-top:.3rem;padding-bottom:.3rem}
  html.ta-wide .ta-i.ta-ch{min-height:36px}
  html.ta-wide .ta-l{line-height:1.2;overflow:visible}
  .ta-foot{margin-top:auto;padding:.4rem .5rem .6rem}
  .ta-tog{display:flex;align-items:center;gap:.6rem;width:100%;height:36px;border:0;border-radius:.7rem;background:none;color:var(--tv-on-umber-meta);cursor:pointer;padding:0 13px;font-size:.78rem;font-weight:600}
  .ta-tog:hover{background:var(--tv-umber-line);color:var(--tv-on-umber-meta)}
  .ta-tog svg{width:18px;height:18px;flex:0 0 18px}
  html.ta-wide .ta-tog svg{transform:scaleX(-1)}
  html:not(.ta-wide) .ta-tog span{display:none}
  /* the workspace */
  .ta-work{flex:1;min-width:0;padding:1.1rem clamp(1rem,3vw,2.5rem) 2.5rem}
  .ta-top{padding:.55rem .5rem .25rem;border-bottom:1px solid var(--tv-umber-line);margin-bottom:.2rem}
  /* who you are, and the role you act in (includes/context-switcher.php) */
  .cx{display:flex;align-items:center;gap:.6rem;width:100%;background:none;border:0;border-radius:.8rem;padding:.3rem;cursor:pointer;color:var(--tv-on-umber);text-align:left}
  .cx:hover,.cx[aria-expanded="true"]{background:var(--tv-umber-line)}
  .cx:focus-visible{outline:2px solid var(--tv-focus-dark);outline-offset:1px}
  .cx-av{width:38px;height:38px;flex:0 0 38px;border-radius:999px;background:var(--tv-card-b);color:var(--tv-void);display:flex;align-items:center;justify-content:center;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.05rem}
  .cx-txt{display:flex;flex-direction:column;min-width:0;line-height:1.2}
  .cx-n{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:.98rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cx-r{font-size:.7rem;color:var(--tv-on-umber-meta);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  html:not(.ta-wide) .cx-txt{display:none}
  html:not(.ta-wide) .cx{justify-content:center;padding:.3rem 0}
  /* its menu's styles live with it (includes/context-switcher.php): it also opens from the top bar on wider screens */
  @media (min-width:768px){.ta-top{display:none}}   /* wider screens: you and your roles are at the top bar's person icon */
  .ta-crumbs{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem .5rem;font-size:.75rem;color:var(--tv-meta);margin-bottom:.6rem}
  .ta-crumbs a{color:inherit;text-decoration:none}.ta-crumbs a:hover{color:var(--tv-ink);text-decoration:underline}
  .ta-crumbs .cur{color:var(--tv-ink);font-weight:600}
  .ta-h1{font-family:var(--tv-font-head);font-weight:700;font-size:clamp(1.5rem,2.6vw,2.1rem);line-height:1.15;color:var(--tv-ink);margin:0}
  .ta-intro{font-size:.92rem;color:var(--tv-meta);margin:.3rem 0 0;max-width:46rem}
  .ta-body{margin-top:1.1rem}
  /* the tools list: part of the rail on wider screens; on phones, a drop-down from the tools button */
  .ta-list{display:contents}
  .ta-i.bz-off{opacity:.55}   /* a business tool your role or plan doesn't open (its reason is in the tip) */
  .ta-menu{display:none}
  .ta-swx{display:none}   /* phones only (below) */
  /* phone: a bar across the top of the workspace: the tools button (left) opens the list over the page; you (right) */
  @media (max-width: 767px) {
    .ta-swx{display:block}
    .ta,html.ta-wide .ta{flex-direction:column;min-height:0;padding-left:0}
    body:has(#ta-rail) footer,html.ta-wide body:has(#ta-rail) footer{margin-left:0}
    .ta-rail,html.ta-wide .ta-rail{position:sticky;left:auto;bottom:auto;top:var(--ta-top,56px);height:auto;width:100%;flex-direction:row;align-items:center;overflow:visible;
      border-right:0;border-bottom:1px solid var(--tv-void);box-shadow:0 4px 10px -6px rgba(35,29,23,.45);padding:.3rem .5rem;z-index:40}
    .ta-name,.ta-foot{display:none!important}
    /* phones (Jason, 2026-10-09). The compass opens the site menu; you (your photo, or ☰ signed out) open the page's tools list.
       Front end: the brown bar stays (compass, search, menu). Signed-in side (body.tv-back): no bar, corner blocks only, the
       compass top left and you (your photo) top right, which opens the menu; the page runs to the top. */
    body:has(#ta-rail) .ta-rail{position:fixed!important;top:0!important;left:0;width:0!important;height:0;min-height:0;padding:0;border:0;box-shadow:none;background:none;overflow:visible}
    body:has(#ta-rail) .ta-menu,body:has(#ta-rail) .ta-top{display:none!important}
    body:has(#ta-rail) .ta-list{position:fixed;right:0;left:auto;top:var(--ta-top,56px);width:min(calc(100vw - 84px),20rem);border-radius:0 0 0 .8rem;max-height:calc(100vh - var(--ta-top,56px) - 10px);max-height:calc(100dvh - var(--ta-top,56px) - 10px);padding-top:.2rem}
    /* your account: the list starts at the very top, joined to your photo's corner (no gap beside it, Jason 2026-10-23); its
       first row (you) sits beside the photo, clear of it; the bar holding the corners lets taps through to the list */
    body.tv-back:has(#ta-rail) .ta-list{top:0;max-height:100vh;max-height:100dvh;padding-top:0;border-top-left-radius:0}
    body.tv-back:has(#ta-rail) .ta-list>.ta-me:first-child .ta-mebtn{min-height:58px;padding-right:74px!important}
    body.tv-back:has(#ta-rail) .ta-list:not(:has(>.ta-me:first-child)){padding-top:58px}
    html:has(.ta-rail.open) body.tv-back #tv-brand{pointer-events:none}
    html:has(.ta-rail.open) body.tv-back #tv-brand :is(a,button,[role=button]){pointer-events:auto}
    body:has(#ta-rail) .ta-list .ta-i{padding:.2rem .9rem;gap:.65rem;height:auto;min-height:38px}
    body:has(#ta-rail) .ta-list .ta-sec{margin:.35rem 0 0!important}
    body:has(#ta-rail) .ta-list .ta-sec.fold{display:flex!important;align-items:center;cursor:pointer;min-height:30px}
    body:has(#ta-rail) .ta-list .ta-sec.fold::after{content:'▾';margin-left:auto;font-size:.8rem}
    body:has(#ta-rail) .ta-list .ta-sec.fold.shut::after{content:'▸'}
    body:has(#ta-rail) .ta-list .ta-folded{display:none!important}
    /* a page with no tools of its own (FAQ, the 404): on phones the list is only you, and signed out there's no button */
    body:has(#ta-rail) .ta-list.ta-site > :not(.ta-swx){display:none!important}
    body:has(.ta-list.ta-site):not(:has(.ta-site .ta-out)) #icon-menu-btn{display:none!important}
    .ta-site .ta-out{border-top:0;margin-top:0}
    .ta-me{border-bottom:1px solid var(--tv-umber-line);margin-bottom:.25rem;padding-bottom:.2rem}
    body:has(#ta-rail) .ta-rail.open{z-index:44}   /* the open list over the page's own buttons (Shortcuts), under your photo */
    .ta-mebtn{width:100%;border:0;background:none;color:var(--tv-on-umber);font:inherit;cursor:pointer;text-align:left}
    .ta-mebtn .ta-meav{display:none!important}   /* your photo is already in the corner button that opens this menu: shown once */
    .ta-mebtn .ta-l{min-width:0;flex:1}.ta-mebtn .ta-l b,.ta-mebtn .ta-l small{white-space:nowrap!important;overflow:hidden;text-overflow:ellipsis}
    .ta-meav{flex:0 0 30px;width:30px;height:30px;border-radius:999px;background:var(--tv-card-b);border:2px solid var(--tv-card-a);color:var(--tv-umber);display:flex;align-items:center;justify-content:center;font:700 .9rem var(--tv-font-head,Georgia,serif)}
    .ta-mebtn .ta-l b{display:block;font-weight:700}.ta-mebtn .ta-l small{display:block;font-size:.74rem;color:var(--tv-on-umber-meta);font-weight:400}
    .ta-mechev{margin-left:auto;flex:0 0 14px;color:var(--tv-on-umber-meta)}.ta-mebtn[aria-expanded="true"] .ta-mechev{transform:rotate(180deg)}
    .ta-mepanel .cx-menu.cx-inline .cx-who,.ta-mepanel .cx-menu.cx-inline .cx-o.on{display:none}   /* the row above already says who you are and as what: listed once */
    .ta-mepanel .cx-menu.cx-inline{position:static!important;width:auto!important;max-height:none;box-shadow:none;margin:.2rem .6rem .45rem;border-radius:.6rem}
    .ta-out{border-top:1px solid var(--tv-umber-line);margin-top:.35rem;padding-top:.2rem}.ta-out .ta-i{width:100%;border:0;background:none;color:var(--tv-on-umber);font:inherit;cursor:pointer;text-align:left}body:has(#ta-rail) .ta-list .ta-l{white-space:normal;line-height:1.2}.ta-dash .ta-i{font-weight:700}
    html:has(.ta-rail.open) .tvn-box{background:var(--tv-umber-line)!important}   /* your corner looks pressed while the tools are open */
    body:not(.tv-back):has(#ta-rail) :is(#top-bar,#tv-nav){background:var(--tv-umber)!important;pointer-events:auto;position:sticky}
    body:not(.tv-back):has(#ta-rail) :is(#top-bar,#tv-nav)>div{padding:.25rem .6rem!important;align-items:center}
    body:not(.tv-back):has(#ta-rail) :is(.tvb-box,.tvn-box){box-shadow:none!important;border-radius:.6rem!important}
    body:not(.tv-back):has(#ta-rail) .ta-body{margin-top:1.1rem}
    html.tv-brand-min.tv-nav-min body.tv-back:has(#ta-rail) :is(#top-bar,#tv-nav){position:fixed;top:0;left:0;right:0;z-index:45;background:var(--tv-umber)!important}   /* solid (2026-11-18): the page's notices showed through behind it on phones */
    html.tv-brand-min.tv-nav-min body.tv-back:has(#ta-rail) :is(.tvb-box,.tvn-box){width:56px;justify-content:center}
    html.tv-brand-min.tv-nav-min body.tv-back:has(#ta-rail) .ta-body{margin-top:calc(var(--ta-top,50px) + .6rem)}
    .tvn-me{display:flex!important;width:34px;height:34px;border-radius:50%;background:var(--tv-card-b) center/cover no-repeat;border:2px solid var(--tv-card-a);align-items:center;justify-content:center;font:700 .95rem var(--tv-font-head,Georgia,serif);color:var(--tv-umber)}
    .tvn-menu:has(.tvn-me)>svg{display:none}
    .ta-menu{display:flex;align-items:center;gap:.55rem;min-width:0;flex:1;height:44px;border:0;background:none;color:var(--tv-on-umber);font:inherit;font-weight:600;font-size:.95rem;cursor:pointer;padding:0 .3rem;text-align:left}
    .ta-menu .ta-svg{color:var(--tv-on-umber-meta)}.ta-menu .ta-cur{display:flex;align-items:center;gap:.4rem;min-width:0}.ta-menu .ta-cur .ta-svg{color:var(--tv-sage);width:20px;height:20px;flex-basis:20px}
    .ta-menu .ta-cur span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ta-menu .chev{margin-left:.1rem;color:var(--tv-on-umber-meta);transition:none}
    .ta-rail.open .ta-menu .chev{transform:rotate(180deg)}
    .ta-top{order:2;flex:0 0 auto;border:0;margin:0 0 0 auto;padding:0;position:static;background:none;box-shadow:none}
    .ta-top .cx{padding:.15rem}.ta-top .cx-txt{display:none}
    .ta-list{display:none;position:absolute;left:0;right:0;top:100%;flex-direction:column;background:var(--tv-umber);border-bottom:1px solid var(--tv-void);box-shadow:0 18px 30px -12px rgba(35,29,23,.6);
      max-height:calc(100vh - var(--ta-top,56px) - 60px);overflow-y:auto;padding:.3rem 0 .6rem}
    .ta-rail.open .ta-list{display:flex}
    .ta-sec{height:auto!important;background:none!important;margin:.6rem 0 .1rem!important;padding:0 1.2rem!important;font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-on-umber-meta)}
    .ta-sec span{display:inline!important}
    .ta-list .ta-i{height:46px;padding:0 1.2rem}
    .ta-list .ta-i .ta-l{display:inline!important}.ta-list .ta-soon{display:inline!important}
    .ta-list .ta-b{position:static;margin-left:auto}.ta-gh>.ta-i .ta-b{margin-right:.4rem}
    .ta-menu .ta-mb{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--tv-copper);color:#fff;font-size:10px;font-weight:700;line-height:18px;text-align:center;margin-left:-.35rem;align-self:flex-start;margin-top:.35rem}
    .ta-grp,.ta-gh{display:block}.ta-gh>.ta-i{padding-right:3rem}
    .ta-gt,html:not(.ta-wide) .ta-gt{right:1rem;top:23px;bottom:auto;transform:translateY(-50%);width:30px;height:30px}
    .ta-sub,html:not(.ta-wide) .ta-sub{margin:0 0 0 1.4rem;border-left:1px dashed var(--tv-umber-line);padding-left:.2rem}
    /* the tools list on phones (Jason, 2026-10-10): a group's whole row opens it, one group at a time; its count and arrow
       sit side by side at the right; its pages hang under it on a thin line, tight, nothing past the edge */
    body:has(#ta-rail) .ta-list[data-staff]{width:min(16rem,calc(100vw - 4rem))}   /* staff: room for the counts, so names stay on one line */
    body:has(#ta-rail) .ta-list .ta-gh>.ta-i{padding-right:2.4rem}
    body:has(#ta-rail) .ta-list .ta-gh>.ta-i .ta-b{margin-right:0}
    body:has(#ta-rail) .ta-list .ta-gt{right:.35rem;top:50%;transform:translateY(-50%);width:26px;height:26px;pointer-events:none}
    body:has(#ta-rail) .ta-grp:has(>.ta-sub:not([hidden]))>.ta-gh>.ta-i{color:var(--tv-on-umber);background:rgba(255,251,242,.04)}
    body:has(#ta-rail) .ta-list .ta-sub{margin:0 0 .3rem 1.55rem;border:0;border-left:1px solid var(--tv-umber-line);padding:0}
    body:has(#ta-rail) .ta-list .ta-i.ta-ch{margin:0;min-height:34px;padding:.15rem .7rem;gap:.55rem}
    body:has(#ta-rail) .ta-list .ta-i.ta-ph{display:flex}
    body:has(#ta-rail) .ta-list .ta-swx .ta-sec.fold{padding-right:.9rem!important}
    /* THE PHONE STANDARD (decisions/0058 §11), for every page: space is at a premium. Text keeps a small margin;
       cards, lists of cards and panels run edge to edge with 3px each side, 3px apart, with small corners.
       A page opts anything else in with class "tv-bleed"; one inside another keeps its card's padding. */
    .ta-work{--tv-pad:.85rem;padding:.8rem var(--tv-pad) 2rem}
    .ta-work .tv-bleed:not(.tv-bleed .tv-bleed),
    .ta-work :is(.dv-card,.mg-box,.jy-card,.jc-card,.ah-card,.ah-soon,.lv-card,.rp-card,.pf-card,.jm-list,.jr-empty,.dr-empty,.rv-card,.st-card,.le-card,.cg-card,.ct-card,.mk-card,.faq details,.rounded-2xl.border,.rounded-xl.border):not(:is(.dv-card,.mg-box,.jy-card,.jc-card,.ah-card,.lv-card,.rp-card,.pf-card,.jm-list,.rounded-2xl,.rounded-xl,.tv-bleed) *){
      margin-left:calc(3px - var(--tv-pad));margin-right:calc(3px - var(--tv-pad));border-radius:.5rem}
    .ta-work .jr-grid:not(.tv-bleed *){margin-left:calc(3px - var(--tv-pad));margin-right:calc(3px - var(--tv-pad));gap:3px}
    .ta-work .jr-grid .jr-card{border-radius:.5rem}
  }
</style>
<script<?= tv_nonce() ?>>try { if (localStorage.getItem('tv_rail') === 'wide' && !/^\/admin(\/|$)/.test(location.pathname)) document.documentElement.classList.add('ta-wide'); } catch (e) {}   /* the admin always opens with the rail collapsed (Jason, 2026-11-12); » opens it for the page */</script>
<?php
}

/**
 * The toolbar and the start of the workspace, for a page that already has its header (tv_app_start does both).
 * $opt['rail_top']: HTML above the items (the dashboard's Context Switcher). $opt['bare']: no crumbs or title.
 */
/** The item a page is about, for the platform's counts (js/out-count.js): "listing:12", "rec:abc", "journey:5". */
function tv_count_item(string $key): void
{
    echo '<meta name="tv-item" content="' . tv_e($key) . '">';
}

function tv_app_open(string $title, array $opt = []): void
{
    tv_app_css();
    // the section this page is in, for the platform's counts (decisions/0058 §13); staff pages aren't counted
    if (!function_exists('tv_section_for')) require_once __DIR__ . '/section-rails.php';
    $countSec = $opt['count_section'] ?? tv_section_for((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH))[0];
    if ($countSec !== 'admin') echo '<meta name="tv-sec" content="' . tv_e((string) $countSec) . '">';
    if (!isset($opt['rail_top'])) {   // the signed-in person and their role switcher, on every page with a toolbar
        require_once __DIR__ . '/context-switcher.php';
        $opt['rail_top'] = tv_context_switcher();
    }
    $rail = $opt['rail'] ?? [];
    $active = (string) ($opt['active'] ?? '');
    $crumbs = $opt['crumbs'] ?? [['Home', '/'], [$title]];
    ?>
<div class="ta" id="ta">
  <nav class="ta-rail" id="ta-rail" aria-label="<?= tv_e($opt['rail_label'] ?? $title) ?> tools">
    <?php if (!empty($opt['rail_top'])): ?><div class="ta-top"><?= $opt['rail_top'] ?></div><?php endif; ?>
    <?php if (!empty($opt['rail_label'])): ?><div class="ta-name" title="<?= tv_e($opt['rail_label']) ?>"><?= tv_e($opt['rail_label']) ?></div><?php endif; ?>
    <?php // phones: one tools button (where you are now) that opens the list below as a drop-down over the page
    $curIt = null;
    foreach ($rail as $it) { if (($it['key'] ?? '') === $active || !empty($it['on'])) $curIt = $it; foreach ($it['children'] ?? [] as $c) if (($c['key'] ?? '') === $active) $curIt = $c; } ?>
    <button type="button" class="ta-menu" id="ta-menu" aria-expanded="false" aria-controls="ta-list" aria-label="<?= tv_e(($opt['rail_label'] ?? 'Tools') . ' tools') ?>">
      <svg class="ta-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
      <span class="ta-cur"><?= $curIt ? (!empty($curIt['svg']) ? '' : tv_icon((string) ($curIt['icon'] ?? 'compass'))) : '' ?><span><?= tv_e($curIt['label'] ?? ($opt['rail_label'] ?? 'Tools')) ?></span></span>
      <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="ta-list<?= !empty($opt['rail_site']) ? ' ta-site' : '' ?>" id="ta-list"<?= array_filter($rail, fn ($x) => ($x['key'] ?? '') === 'admin') ? ' data-staff="1"' : '' ?>>
    <?php
    // One item; an item with 'children' is a group (Crawler ▸ its pages) that opens and closes in place, so the
    // whole menu stays put however deep you go. A group is open when you're on one of its pages, or as you left it.
    $one = function (array $it, bool $child = false) use ($active): string {
        $on = ($it['key'] ?? '') === $active || !empty($it['on']); $soon = !empty($it['soon']); $off = (string) ($it['off'] ?? '');   // off: greyed, with why (never hidden, decisions/0109)
        $tip = $it['label'] . ($soon ? ' (coming soon)' : ($off !== '' ? ': ' . $off : (!empty($it['what']) ? ': ' . $it['what'] : '')));
        if ($off !== '') $soon = true;
        $tag = $soon ? 'span' : 'a href="' . tv_e($it['url']) . '"';
        return '<' . $tag . ' class="ta-i' . ($child ? ' ta-ch' : '') . (!empty($it['cls']) ? ' ' . $it['cls'] : '') . ($on ? ' on' : '') . ($soon ? ' soon' : '') . '" title="' . tv_e($tip) . '" aria-label="' . tv_e($it['label']) . '"'
            . ($on ? ' aria-current="page"' : '') . ($soon ? ' aria-disabled="true"' : '') . (!empty($it['attrs']) ? ' ' . $it['attrs'] : '') . '>'
            . (!empty($it['svg']) ? '<svg class="ta-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $it['svg'] . '</svg>' : tv_icon((string) ($it['icon'] ?? 'compass')))
            . '<span class="ta-l">' . tv_e($it['label']) . '</span>' . ($soon && $off === '' ? '<span class="ta-soon">Soon</span>' : '')
            . (!empty($it['badge']) ? '<span class="ta-b">' . ((int) $it['badge'] > 99 ? '99+' : (int) $it['badge']) . '</span>' : '')
            . '</' . ($soon ? 'span' : 'a') . '>';
    };
    // phones, signed in: Dashboard first in every tools list, so it's one tap back from anywhere (unless this list is the Dashboard's own)
    $me = class_exists('Auth') ? Auth::currentUserId() : null;
    $hasDash = (bool) array_filter($rail, fn ($x) => in_array($x['key'] ?? '', ['pulse', 'admin'], true));   // staff: the admin Dashboard is the one
    // who you are and switching live in the account bar above the header now (Jason, 2026-11-05), not in this list
    if ($me && !$hasDash): ?><div class="ta-swx ta-dash"><?= $one(['key' => 'sw-dash', 'label' => 'Dashboard', 'icon' => 'home', 'url' => '/user/dashboard.php#pulse']) ?></div><?php endif;
    foreach ($rail as $i => $it):
        if (!empty($it['section']) && $i > 0): ?><div class="ta-sec" role="presentation"><span><?= tv_e($it['section']) ?></span></div><?php endif;
        if (!empty($it['children'])):
            $inside = in_array($active, array_column($it['children'], 'key'), true) || ($it['key'] ?? '') === $active;
            $gid = 'ta-g-' . preg_replace('/[^a-z0-9]/', '', strtolower((string) $it['key'])); ?>
      <div class="ta-grp<?= $inside ? ' in' : '' ?>" data-grp="<?= tv_e($gid) ?>">
        <div class="ta-gh"><?= $one($it) ?><button type="button" class="ta-gt" aria-expanded="<?= $inside ? 'true' : 'false' ?>" aria-controls="<?= $gid ?>" title="Show or hide <?= tv_e($it['label']) ?> tools" aria-label="Show or hide <?= tv_e($it['label']) ?> tools"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg></button></div>
        <?php // phones: tapping a group's row opens it, so its own page goes inside too when it isn't one of them already
        $urls = array_map(fn ($c) => (string) ($c['url'] ?? ''), $it['children']);
        $own = in_array($it['url'], $urls, true) || in_array($it['url'], array_map(fn ($u) => '/user/dashboard.php' . $u, $urls), true)
            ? [] : [['key' => $it['key'], 'label' => 'Overview', 'icon' => $it['icon'] ?? 'compass', 'url' => $it['url'], 'what' => $it['what'] ?? '', 'cls' => 'ta-ph']]; ?>
        <div class="ta-sub" id="<?= $gid ?>"<?= $inside ? '' : ' hidden' ?>><?= implode('', array_map(fn ($c) => $one($c, true), array_merge($own, $it['children']))) ?></div>
      </div>
    <?php else: echo $one($it); endif;
    endforeach;
    // phones: where to go is the compass's menu (header.php); this list is only what you can do here, and you (Jason, 2026-10-23)
    // signing out is the account bar's icon (Jason, 2026-11-05)
    ?>
    </div>
    <div class="ta-foot"><button type="button" class="ta-tog" id="ta-tog" aria-label="Show or hide labels"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg><span>Hide labels</span></button></div>
  </nav>
  <section class="ta-work" id="ta-work">
    <?php if (empty($opt['bare'])): ?>
    <?php if ($countSec === 'admin' && !str_starts_with((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/user/dashboard')): ?>
    <p class="ta-back"><a href="/admin/">&larr; Dashboard</a></p>
    <?php elseif ($crumbs): ?>
    <nav class="ta-crumbs" aria-label="Breadcrumb">
      <?php foreach ($crumbs as $i => $c): $last = $i === count($crumbs) - 1; ?>
        <?php if ($i > 0): ?><span aria-hidden="true">&rsaquo;</span><?php endif; ?>
        <?php if (!$last && !empty($c[1])): ?><a href="<?= tv_e($c[1]) ?>"><?= tv_e($c[0]) ?></a><?php else: ?><span class="cur" aria-current="page"><?= tv_e($c[0]) ?></span><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if (empty($opt['no_title'])): ?><h1 class="ta-h1"><?= tv_e($opt['heading'] ?? $title) ?></h1><?php endif; ?>
    <?php if (!empty($opt['intro'])): ?><p class="ta-intro"><?= $opt['intro'] ?></p><?php endif; ?>
    <?php endif; ?>
    <div class="ta-body">
<?php
}

function tv_app_end(): void
{
    tv_app_close();
    tv_page_end(['app' => true]);
}

function tv_app_close(): void
{
    ?>
    </div>
  </section>
</div>
<script<?= tv_nonce() ?>>
  // The toolbar sits just under the header (whatever its height, collapsed or not); labels on or off, remembered.
  (function () {
    var bar = document.getElementById('top-bar'), root = document.documentElement, tog = document.getElementById('ta-tog');
    function fit() {
      if (bar) root.style.setProperty('--ta-top', bar.offsetHeight + 'px');
      var as = document.querySelector('body > .tv-as'); if (as) root.style.setProperty('--as-h', as.offsetHeight + 'px');   // the account bar under the header (in flow or pinned)
      pin();
    }
    // the toolbar starts where the header ends, even if the header scrolls away
    function pin() {   // and below the account bar, which sits under the header now
      if (!bar) return; var b = bar.getBoundingClientRect().bottom, as = document.querySelector('body > .tv-as');
      if (as && getComputedStyle(as).position !== 'fixed') b = Math.max(b, as.getBoundingClientRect().bottom);
      root.style.setProperty('--ta-rt', Math.max(0, Math.round(b)) + 'px');
    }
    fit(); window.addEventListener('resize', fit); window.addEventListener('scroll', pin, { passive: true });
    if (bar && window.ResizeObserver) new ResizeObserver(fit).observe(bar);
    if (tog) tog.addEventListener('click', function () {
      var wide = root.classList.toggle('ta-wide');
      try { localStorage.setItem('tv_rail', wide ? 'wide' : 'narrow'); } catch (e) {}
    });
    // groups open and close in place; each remembers how you left it
    document.querySelectorAll('.ta-grp').forEach(function (g) {
      var b = g.querySelector('.ta-gt'), sub = g.querySelector('.ta-sub'), k = 'tv_grp_' + g.getAttribute('data-grp'), saved = null;
      try { saved = localStorage.getItem(k); } catch (x) {}
      function set(open) { sub.hidden = !open; b.setAttribute('aria-expanded', String(open)); }
      if (saved !== null && !g.classList.contains('in')) set(saved === '1');
      b.addEventListener('click', function (e) { e.preventDefault(); var open = sub.hidden; set(open); try { localStorage.setItem(k, open ? '1' : '0'); } catch (x) {} });
    });
    // phones: you, at the top of the list: the account switcher opens in place under it (context-switcher.php's menu, moved in)
    (function () {
      var b = document.querySelector('.ta-mebtn'), panel = document.getElementById('ta-mepanel'); if (!b || !panel) return;
      var role = b.querySelector('.ta-merole');
      var name = (b.querySelector('.ta-l b') || {}).textContent || '';
      var fill = function () {   // "Acting as Traversence staff · Owner": nothing your name already says (a staff ID in it)
        var m = document.getElementById('cx-menu'), w = m && m.querySelector('.cx-who small');
        if (w && role) role.textContent = w.textContent.split(' · ').filter(function (x) { x = x.trim(); return x && name.indexOf(x) < 0; }).join(' · ');
        return m;
      };
      setTimeout(fill, 0);
      b.addEventListener('click', function (e) {
        e.stopPropagation();
        var m = fill(); if (!m) { location.href = '/user/accounts.php'; return; }
        if (m.parentNode !== panel) { panel.appendChild(m); m.classList.add('cx-inline'); }
        var open = panel.hidden || m.hidden; panel.hidden = !open; m.hidden = !open; b.setAttribute('aria-expanded', String(open));
      });
      panel.addEventListener('click', function (e) { e.stopPropagation(); });   // choosing inside it doesn't close the list
    })();
    // phones: the tools button opens and closes the list
    var mb = document.getElementById('ta-menu'), railEl = document.getElementById('ta-rail');
    if (mb && railEl) {
      var setOpen = function (o) {
        railEl.classList.toggle('open', o); mb.setAttribute('aria-expanded', String(o));
        if (o) {   // one menu at a time: the page's own drop-downs (a section menu, a filter) close when the tools open
          Array.prototype.forEach.call(document.querySelectorAll('details.ak-dd[open]'), function (d) { d.open = false; });
          Array.prototype.forEach.call(document.querySelectorAll('.ak-fb[aria-expanded="true"]'), function (b) { b.click(); });
        }
      };
      window.tvToolsClose = function () { if (railEl.classList.contains('open')) setOpen(false); };
      // ...and the tools close when one of those opens
      document.addEventListener('toggle', function (e) { if (e.target.matches && e.target.matches('details.ak-dd') && e.target.open) window.tvToolsClose(); }, true);
      document.addEventListener('click', function (e) { var f = e.target.closest && e.target.closest('.ak-fb'); if (f && f.getAttribute('aria-expanded') !== 'true') window.tvToolsClose(); }, true);
      // phones: the corner with you (your photo, or ☰) opens this list (js/header-nav.js); the rail's own button is hidden
      window.tvTools = function () { setOpen(!railEl.classList.contains('open')); };
      // phones: each section folds under its heading; Things to do and Settings start folded (where you are stays open); remembered
      if (window.matchMedia('(max-width: 767px)').matches) {
        var shut0 = { 'things to do': 1, 'settings': 1 }, mem = {};
        if (railEl.querySelector('#ta-list[data-staff]')) shut0['switch to'] = 1;   // staff work in their tools; the rest of the site folds away
        try { mem = JSON.parse(localStorage.getItem('tv_tools_fold') || '{}'); } catch (x) {}
        Array.prototype.forEach.call(railEl.querySelectorAll('#ta-list > .ta-sec, #ta-list > .ta-swx > .ta-sec'), function (h) {
          var name = h.textContent.trim().toLowerCase(), items = [], n = h.nextElementSibling;
          while (n && !n.classList.contains('ta-sec') && !n.classList.contains('ta-swx')) { items.push(n); n = n.nextElementSibling; }
          if (!items.length) return;
          h.classList.add('fold'); h.setAttribute('role', 'button'); h.setAttribute('tabindex', '0');
          var set = function (shut) { h.classList.toggle('shut', shut); h.setAttribute('aria-expanded', String(!shut)); items.forEach(function (i) { i.classList.toggle('ta-folded', shut); }); };
          var here = items.some(function (i) { return i.matches('.on, .in') || i.querySelector('.on'); });
          set(!here && (name in mem ? mem[name] : !!shut0[name]));
          var flip = function (e) { e.stopPropagation(); var sh = !h.classList.contains('shut'); set(sh); mem[name] = sh; try { localStorage.setItem('tv_tools_fold', JSON.stringify(mem)); } catch (x) {} };
          h.addEventListener('click', flip); h.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flip(e); } });
        });
        var grps = railEl.querySelectorAll('#ta-list .ta-grp');
        Array.prototype.forEach.call(grps, function (g) {
          var head = g.querySelector('.ta-gh > .ta-i'), b = g.querySelector('.ta-gt');
          if (!head || !b) return;
          head.setAttribute('aria-expanded', b.getAttribute('aria-expanded'));
          head.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            var opening = g.querySelector('.ta-sub').hidden;
            if (opening) Array.prototype.forEach.call(grps, function (o) { var t = o.querySelector('.ta-gt'); if (o !== g && t && t.getAttribute('aria-expanded') === 'true') t.click(); });
            b.click(); head.setAttribute('aria-expanded', String(opening));
          });
        });
      }
      mb.addEventListener('click', function (e) { e.stopPropagation(); setOpen(!railEl.classList.contains('open')); });
      document.addEventListener('click', function (e) { if (railEl.classList.contains('open') && !e.target.closest('#ta-list, #ta-menu, #icon-menu-btn')) setOpen(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && railEl.classList.contains('open')) { setOpen(false); mb.focus(); } });
      // picking a tool closes the list; on the Dashboard a tool switches the section in place, so the button follows it
      var cur = mb.querySelector('.ta-cur');
      var follow = function (a) { if (!cur || !a) return; var ic = a.querySelector('svg'), l = a.querySelector('.ta-l'); cur.innerHTML = (ic ? ic.outerHTML : '') + '<span></span>'; cur.querySelector('span').textContent = l ? l.textContent : ''; };
      railEl.addEventListener('click', function (e) { var a = e.target.closest('#ta-list a.ta-i'); if (a) { follow(a); setOpen(false); } });
      window.addEventListener('hashchange', function () { var a = railEl.querySelector('#ta-list a.ta-i[href="' + location.hash + '"]'); if (a) follow(a); });
      // something waiting (Review's count): shown on the tools button too, so it isn't hidden in the closed list
      var waiting = [].reduce.call(railEl.querySelectorAll('#ta-list .ta-b'), function (n, b) { return b.closest('.ta-sub') ? n : n + (parseInt(b.textContent, 10) || 0); }, 0);   // each group's total once, not its pages again
      if (waiting) mb.querySelector('.ta-svg').insertAdjacentHTML('afterend', '<span class="ta-mb" title="Waiting for you">' + (waiting > 99 ? '99+' : waiting) + '</span>');
      var start = location.hash && railEl.querySelector('#ta-list a.ta-i[href="' + location.hash + '"]'); if (start) follow(start);
    }
    var rail = document.getElementById('ta-rail'), on = rail && rail.querySelector('.ta-i.on');
    if (on && rail.scrollWidth > rail.clientWidth + 4) rail.scrollLeft = on.offsetLeft - 12;   // phone row: show where you are
    // the menu stays where you left it from page to page (no jump back to the top)
    if (rail && rail.scrollHeight > rail.clientHeight + 4) {
      try { var y = +sessionStorage.getItem('tv_rail_y') || 0; if (y) rail.scrollTop = y; } catch (x) {}
      window.addEventListener('pagehide', function () { try { sessionStorage.setItem('tv_rail_y', String(rail.scrollTop)); } catch (x) {} });
    }
  })();
</script>
<?php
}
