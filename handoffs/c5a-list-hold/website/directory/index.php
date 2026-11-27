<?php
// directory/index.php — Get Local: businesses near you (decisions/0053, 0055 §1).
//
// Like a "businesses near me" search. The header's search is the search box: on this page, Enter shows its words here,
// and its Quick picks (Near me, Open now, Has a phone, Has a website) switch the filters (window.tvQuick). The page:
// its title with the List | Map toggle on the same line, the filters in use (each removable), then the results. The
// Map opens the full-screen map (js/map-view.js, decisions/0058 §5) with every result as a pin. The choice is remembered. Each result shows what you need at a glance: what it is, how far, the address,
// open or closed right now, and Call, Directions and Website. A confidential location shows its phone only
// (decisions/0044). The earlier directory is archived at /directory/classic.php (archive.md).
//
// Takes: ?q= &cat= &catname= &cluster= | &geo= | &hub= | &lat= &lon= &radius= &near= , &open=now, &has=phone,website,
// &sort=best|distance|name. With no place in the address, it uses the place chosen on the site.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/app-shell.php';
require_once __DIR__ . '/../../app/ui/section-rails.php';

$rail = array_values(array_filter(tv_section_rail('local')['items'], fn ($it) => $it['key'] !== 'listing'));
// The classic directory (/directory/classic.php) is archived (Jason, 2026-11-19): out of the rail, still reachable by its address; archive.md

// "Send the crawler" (decisions/0157 §3): Jason only (the Founder, signed in to the admin), under the results
$sendCrawler = false;
try {
    require_once __DIR__ . '/../../app/lib/crawler/IntakeStager.php';
    require_once __DIR__ . '/../../app/admin/Access.php';
    $me = Auth::currentUser();
    $sendCrawler = $me && IntakeStager::canUseIntake(Database::connection(), $me) && Access::who()[0] === 'founder';
} catch (Throwable $e) { $sendCrawler = false; }
if ($sendCrawler && empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

tv_app_start('Get Local', ['rail' => $rail, 'active' => 'directory', 'rail_label' => 'Get Local', 'bare' => true,
    'description' => 'Local businesses and services near you: open now, phone, directions and website at a glance.']);
?>
<style>
  /* top row: where (left), List | Map (right); then the title and count; filters live in the search's Quick picks */
  .dr-bar{display:flex;align-items:center;justify-content:space-between;gap:.6rem}
  .dr-wgrp{display:inline-flex;align-items:center;gap:.3rem;min-width:0;max-width:70%}
  .dr-wx{flex:0 0 auto;width:30px;height:30px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-link);font-size:1rem;line-height:1;cursor:pointer}.dr-wx[hidden]{display:none}.dr-wx:hover{background:var(--tv-canvas)}
  .dr-head{margin-top:.6rem;display:flex;flex-wrap:wrap;align-items:baseline;gap:.2rem .8rem}
  .dr-head .dr-sum{margin:0}.dr-head .dr-chips{margin:0}
  /* the finder (2026-11-12): one search across the top, Refine under it */
  .dr-rdh{font:700 1.02rem var(--tv-font-head,ui-serif,Georgia,serif);color:var(--tv-ink);margin:1rem 0 .4rem}.dr-rdh small{font:500 .78rem system-ui,sans-serif;color:var(--tv-meta);margin-left:.4rem}
  .dr-it.dr-rd{border-style:dashed}.dr-src{color:var(--tv-meta);font-size:.8rem}
  .dr-find{background:var(--tv-sec);border:1px solid var(--tv-sec-edge);border-radius:1rem;padding:.7rem .9rem .6rem;box-shadow:0 8px 22px -16px rgba(62,43,31,.55)}
  .dr-ftop{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.5rem}
  .dr-fh{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:clamp(1.2rem,1.8vw,1.45rem);color:var(--tv-ink);margin:0;line-height:1.2}
  .dr-ftop .dr-view button{padding:.25rem .75rem}
  .dr-form{display:flex;align-items:stretch;gap:.5rem}
  .dr-qbox{flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:.5rem;border:2px solid var(--tv-umber);border-radius:999px;background:var(--tv-field);padding:0 .4rem 0 .9rem;color:var(--tv-meta)}
  .dr-qbox:focus-within{border-color:var(--tv-icon,#B45309);box-shadow:0 0 0 3px rgba(180,83,9,.15)}
  .dr-qbox input{flex:1;min-width:0;border:0;outline:0;background:none;font:inherit;font-size:1rem;padding:.5rem 0;color:var(--tv-ink)}
  .dr-qbox input::placeholder{color:var(--tv-meta);opacity:1}   /* the palette, not the browser's blue-grey (Jason's screenshot, 2026-11-19) */
  .dr-qbox input::-webkit-search-cancel-button{display:none}
  .dr-qx{border:0;background:none;color:var(--tv-meta);font-size:1.4rem;line-height:1;cursor:pointer;padding:0 .35rem}.dr-qx[hidden]{display:none}
  .dr-flash{animation:drfl 1.6s ease}@keyframes drfl{0%,60%{box-shadow:0 0 0 4px rgba(180,83,9,.45)}100%{box-shadow:none}}
  .dr-wbtn{flex:1;min-width:0;display:flex;align-items:center;gap:.45rem;border:0;background:none;color:var(--tv-ink);font:600 1rem system-ui,sans-serif;padding:.5rem .4rem .5rem .8rem;cursor:pointer;text-align:left}
  .dr-wbtn>svg:first-child{color:var(--tv-icon,#B45309);flex:none}.dr-wbtn span{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dr-wch{flex:none;color:var(--tv-link)}
  .dr-wbw .dr-wx{margin-right:.3rem}
  .dr-wtgt{flex:none;width:32px;height:32px;margin-right:.25rem;display:flex;align-items:center;justify-content:center;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-icon,#B45309);cursor:pointer;padding:0}.dr-wtgt:hover{border-color:var(--tv-icon,#B45309)}
  .dr-form .dr-wgrp{flex:0 1 17rem;max-width:none;min-width:11rem;border-width:2px;border-color:var(--tv-line-strong);border-radius:999px;background:var(--tv-field)}
  .dr-go{flex:0 0 auto;border:0;border-radius:999px;background:var(--tv-btn);color:var(--tv-btn-text);font:700 1rem system-ui,sans-serif;padding:0 1.5rem;cursor:pointer}
  .dr-go:hover{background:var(--tv-btn-hover)}
  .dr-refine{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .6rem;margin-top:.5rem;padding-top:.5rem;border-top:1px solid var(--tv-line)}
  .dr-rf select{padding:.28rem .45rem!important}.dr-tg{padding:.25rem .7rem!important}
  .dr-rl{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-meta)}
  .dr-rf{display:inline-flex;align-items:center;gap:.35rem;font-size:.82rem;color:var(--tv-meta)}
  .dr-rf select{border:1px solid var(--tv-line-strong);border-radius:.6rem;background:var(--tv-field);padding:.38rem .5rem;font:inherit;font-size:.88rem;color:var(--tv-ink);max-width:15rem}
  .dr-rf[hidden]{display:none}
  .dr-tgs{display:inline-flex;flex-wrap:wrap;gap:.35rem}
  .dr-tg{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.33rem .75rem;font:600 .82rem system-ui,sans-serif;cursor:pointer}
  .dr-tg[aria-pressed="true"]{background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber);box-shadow:0 0 8px rgba(143,188,168,.7)}
  .dr-head .dr-h1{font-size:clamp(1.1rem,1.8vw,1.35rem)}
  /* the bar (Jason, 2026-11-19): the home page's split bar, on the card */
  .dr-find{padding:.55rem .6rem .6rem}
  .dr-ftop{margin:0 .2rem .45rem}
  .dr-fh{font:700 .74rem var(--tv-font-ui)!important;letter-spacing:.09em;text-transform:uppercase;color:var(--tv-meta)!important}
  .dr-form{gap:.35rem;background:var(--tv-card-b);border:1px solid var(--tv-line);border-radius:.9rem;padding:.35rem}
  .dr-form .dr-qbox,.dr-form .dr-wgrp{border:1px solid var(--tv-line)!important;border-radius:.65rem!important;background:var(--tv-field)!important;box-shadow:none}
  .dr-form .dr-qbox{flex:1.6 1 0;padding:0 .4rem 0 .75rem}.dr-form .dr-qbox>svg{color:var(--tv-icon,#B45309);flex:none}
  .dr-form .dr-qbox:focus-within,.dr-form .dr-wgrp:focus-within{border-color:var(--tv-icon,#B45309)!important}
  .dr-form .dr-qbox input{padding:.78rem 0;font-weight:600}
  .dr-form .dr-qbox input::placeholder{font-weight:500;font-size:1rem;color:var(--tv-meta)}   /* sentence case, as the home bar's (Jason, 2026-11-19) */
  .dr-form .dr-wgrp{flex:1 1 0;min-width:0}
  .dr-go{border-radius:.65rem!important;min-width:3.4rem;display:flex;align-items:center;justify-content:center;padding:0 1rem!important;color:#fff!important}
  .dr-go:hover{background:var(--tv-btn-hover)!important}
  /* what the words could mean: the kind first, then the businesses */
  .dr-find{position:relative}
  .dr-sug{position:absolute;left:.6rem;top:calc(100% - .2rem);z-index:30;width:min(34rem,calc(100% - 1.2rem));background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;box-shadow:var(--tv-shadow-lg);padding:.35rem}
  .dr-sug button,.dr-sug a{display:block;width:100%;text-align:left;border:0;background:none;padding:.45rem .6rem;border-radius:.5rem;cursor:pointer;color:var(--tv-ink);text-decoration:none;font:inherit}
  .dr-sug button:hover,.dr-sug a:hover,.dr-sug .on{background:var(--tv-field)}
  .dr-sug b{display:block;font-size:.95rem}.dr-sug small{color:var(--tv-meta);font-size:.8rem}
  .dr-sug hr{border:0;border-top:1px solid var(--tv-line);margin:.25rem .4rem}
  .dr-sug h4{margin:.35rem .6rem .15rem;font:700 .7rem var(--tv-font-ui);letter-spacing:.08em;text-transform:uppercase;color:var(--tv-meta)}
  /* the sentence (Refine): "4 [Barber shops ×] within [any distance ▾] of [Show Low ▾]" */
  .dr-sr{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .dr-head{display:block;position:relative;margin-top:.9rem}
  .dr-sent{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .45rem;margin:0 0 .5rem;font:600 1.05rem var(--tv-font-ui);color:var(--tv-ink)}
  .dr-tok{display:inline-flex;align-items:center;gap:.3rem;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-ink);border-radius:999px;padding:.2rem .7rem;font:600 .95rem var(--tv-font-ui);cursor:pointer}
  .dr-tok i{font-style:normal;color:var(--tv-meta);font-size:.8em}
  .dr-tok.k{background:color-mix(in srgb,var(--tv-umber) 40%,var(--tv-taupe));color:var(--tv-on-umber);border-color:transparent}.dr-tok.k i{color:var(--tv-on-umber-meta)}
  .dr-tok[aria-expanded="true"]{border-color:var(--tv-icon,#B45309);box-shadow:0 0 0 3px color-mix(in srgb,var(--tv-sage) 35%,transparent)}
  .dr-pop{position:absolute;z-index:25;left:0;top:2.6rem;width:min(22rem,calc(100vw - 6px));background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;box-shadow:var(--tv-shadow-lg);padding:.65rem .75rem}
  .dr-pop h4{margin:0 0 .4rem;font:700 .72rem var(--tv-font-ui);letter-spacing:.08em;text-transform:uppercase;color:var(--tv-meta)}
  .dr-pop .dr-pills{display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.55rem}
  .dr-pop .dr-pills button{border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-ink);border-radius:999px;padding:.2rem .65rem;font:600 .85rem var(--tv-font-ui);cursor:pointer}
  .dr-pop .dr-pills button[aria-pressed="true"]{background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber);box-shadow:0 0 8px rgba(143,188,168,.7)}
  .dr-pop .dr-pills small{color:var(--tv-meta);font-weight:500;margin-left:.2rem}
  .dr-ring{position:relative;height:84px;border-radius:.6rem;background:var(--tv-card-b);margin:0 0 .55rem;overflow:hidden}
  .dr-ring span{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);border:2px dashed var(--tv-link);border-radius:50%;transition:width var(--tv-ease),height var(--tv-ease)}
  .dr-ring b{position:absolute;left:50%;top:50%;width:8px;height:8px;margin:-4px 0 0 -4px;border-radius:50%;background:var(--tv-icon,#B45309)}
  .dr-pop .dr-lk{font-size:.88rem}
  .dr-row1{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .6rem;margin:0 0 .6rem}.dr-row1 .dr-sent{margin:0;flex:1 1 auto}
  .dr-ctl{display:flex;align-items:center;gap:.5rem;margin-left:auto}   /* Filters and Sort, on the sentence's line (Jason, 2026-11-19) */
  .dr-fdd{position:relative}.dr-fdd>summary{list-style:none}.dr-fdd>summary::-webkit-details-marker{display:none}
  .dr-fb{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.36rem .8rem;font:600 .88rem var(--tv-font-ui);cursor:pointer}
  .dr-fb svg{width:15px;height:15px;color:var(--tv-icon,#B45309)}.dr-fb i{font-style:normal;font-size:.75rem;color:var(--tv-meta)}.dr-fdd[open]>.dr-fb{border-color:var(--tv-link)}
  .dr-fb b{background:var(--tv-sage,#8FBCA8);color:var(--tv-umber,#2A1B12);border-radius:999px;padding:0 .4rem;font-size:.78rem}
  .dr-fddl{position:absolute;right:0;top:calc(100% + .35rem);z-index:40;min-width:14rem;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.45rem}
  .dr-fh{margin:.1rem .4rem .3rem;font:600 .78rem var(--tv-font-ui);color:var(--tv-meta);padding-right:2rem}
  .dr-fx{position:absolute;top:.3rem;right:.35rem;width:28px;height:28px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);font-size:1.05rem;line-height:1;cursor:pointer}
  .dr-fddl .dr-tgs{display:flex;flex-direction:column;gap:.1rem}
  .dr-fddl .dr-tg{display:flex;align-items:center;gap:.45rem;width:100%;text-align:left;border:0!important;background:none;border-radius:.45rem;box-shadow:none!important;padding:.45rem .5rem!important;font:500 .92rem var(--tv-font-ui)}
  .dr-fddl .dr-tg::before{content:'';width:1rem;height:1rem;border:1px solid var(--tv-line-strong);border-radius:.25rem;flex:none;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem}
  .dr-fddl .dr-tg[aria-pressed="true"]{background:none;color:var(--tv-ink);font-weight:700}.dr-fddl .dr-tg[aria-pressed="true"]::before{content:'\2713';background:var(--tv-sage,#8FBCA8);border-color:var(--tv-sage,#8FBCA8)}
  .dr-fddl .dr-tg:hover{background:var(--tv-sec,#EDE6D5)}
  @media (max-width:767px){.dr-ctl{margin-left:0;width:100%;justify-content:space-between}.dr-fddl{left:0;right:auto}}
  .dr-quick{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem .5rem;margin-bottom:.7rem}
  .dr-srt{margin-left:auto;display:inline-flex;align-items:center;gap:.4rem;font-size:.84rem;color:var(--tv-meta)}
  .dr-srt select{border:1px solid var(--tv-line-strong);border-radius:.6rem;background:var(--tv-field);padding:.3rem .45rem;font:600 .86rem var(--tv-font-ui);color:var(--tv-ink);max-width:14rem}
  .dr-head .dr-sum{font-size:.84rem;margin:0 0 .4rem}
  .dr-chips[hidden]{display:none!important}   /* the sentence says what narrows the list */
  @media (max-width:767px){.dr-form{flex-wrap:wrap}.dr-form .dr-qbox{flex:1 1 100%}.dr-form .dr-wgrp{flex:1 1 0}
    .dr-sent{font-size:.98rem}.dr-srt{margin-left:0}.dr-sug{left:0;width:100%}}

  @media (max-width:767px){.dr-find{padding:.75rem .7rem}.dr-form{flex-wrap:wrap}.dr-qbox{flex-basis:100%}.dr-form .dr-wgrp{flex:1 1 auto;min-width:0}.dr-go{padding:.6rem 1.1rem}
    .dr-refine{gap:.4rem}.dr-rl{flex-basis:100%}.dr-rf{flex:1 1 45%;min-width:0}.dr-rf select{flex:1;width:100%;max-width:none;min-width:0}}   /* a long kind name never pushes Kind past the card (2026-11-19) */
  .dr-top{display:flex;align-items:center;gap:.6rem 1rem}
  .dr-tl{min-width:0;flex:0 1 auto}
  .dr-top .dr-chips{flex:1 1 auto;margin:0;justify-content:flex-end}
  .dr-c.sm{padding:.22rem .65rem;font-size:.8rem}
  .dr-h1{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:clamp(1.35rem,2.4vw,1.85rem);color:var(--tv-ink);margin:0;line-height:1.2}
  .dr-chips:empty{display:none}
  /* the map fills the workspace; the title and toggle float over it (top and left are set from the workspace) */
  html.dr-mapmode{overflow:hidden}
  html.dr-mapmode .dr-mapbox{position:fixed;top:var(--dr-t,120px);left:var(--dr-l,64px);right:0;bottom:0;height:auto;min-height:0;border:0;border-radius:0;z-index:20}
  html.dr-mapmode .dr-top{position:fixed;top:calc(var(--dr-t,120px) + 12px);left:calc(var(--dr-l,64px) + 12px);right:12px;z-index:25;pointer-events:none}
  html.dr-mapmode .dr-tl,html.dr-mapmode .dr-view,html.dr-mapmode .dr-chips{pointer-events:auto}
  html.dr-mapmode .dr-top{align-items:flex-start}
  html.dr-mapmode .dr-top .dr-chips{flex:0 1 auto;margin-left:auto;background:rgba(255,251,242,.95);border:1px solid var(--tv-line-strong);border-radius:1rem;padding:.4rem .5rem;box-shadow:0 8px 20px -10px rgba(0,0,0,.45)}
  .dr-pin.ap span{opacity:.85}
  html.dr-mapmode .dr-tl{background:rgba(255,251,242,.95);border:1px solid var(--tv-line-strong);border-radius:1rem;padding:.55rem .85rem;box-shadow:0 8px 20px -10px rgba(0,0,0,.45);max-width:min(34rem,70%)}
  html.dr-mapmode .dr-h1{font-size:1.15rem}
  html.dr-mapmode .dr-sum{margin:.15rem 0 0}
  html.dr-mapmode .dr-view{box-shadow:0 8px 20px -10px rgba(0,0,0,.45)}
  html.dr-mapmode .dr-mapbar{top:auto;bottom:1.6rem;right:auto;left:.75rem}
  html.dr-mapmode .dr-mapnote{left:auto;right:3.2rem}
  .dr-view{flex:0 0 auto;display:inline-flex;border:1px solid var(--tv-line-strong);border-radius:999px;overflow:hidden;background:var(--tv-card-a)}
  .dr-view button{display:inline-flex;align-items:center;gap:.35rem;border:0;background:none;padding:.35rem .85rem;font-size:.84rem;font-weight:600;color:var(--tv-ink);cursor:pointer}
  .dr-view button svg{width:16px;height:16px}
  .dr-view button[aria-pressed="true"]{background:var(--tv-umber);color:var(--tv-on-umber)}
  .dr-lk{background:none;border:0;padding:0;color:var(--tv-link);text-decoration:underline;font-size:.86rem;font-weight:600;cursor:pointer}
  .dr-pill{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-link);border-radius:999px;padding:.25rem .75rem;font-size:.84rem;font-weight:600;cursor:pointer}
  .dr-chips{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.45rem;align-items:center}
  .dr-c{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.3rem .8rem;font-size:.84rem;cursor:pointer;white-space:nowrap}
  .dr-c.on{background:color-mix(in srgb,var(--tv-umber) 40%,var(--tv-taupe));color:var(--tv-on-umber);border-color:transparent}   /* a softer brown (Jason, 2026-11-19), cream on it 6:1 */
  .dr-c.x::after{content:" \00d7";opacity:.8}
  .dr-sel{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:999px;padding:.28rem .6rem;font-size:.84rem;color:var(--tv-ink)}
  .dr-lbl{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--tv-meta);margin:0 0 .45rem}
  .dr-grid{margin-top:.4rem}
  .dr-grid.map #dr-listbox{display:none}
  #dr-listbox{max-width:none}
  /* one column across the page's width (Jason, 2026-11-12: "a lot of wasted space", then "I dont like the two columns") */
  .dr-sum{font-size:.86rem;color:var(--tv-meta);margin:.25rem 0 0}
  /* cards: edge to edge, 3px apart; the name is the link; Call, Directions, Website, Peek sit left under it; Link in the corner */
  #dr-list{margin-top:.6rem;display:flex;flex-direction:column;gap:3px}
  .dr-it{position:relative;display:block;background:var(--tv-card-a);border:1px solid var(--tv-card-edge);border-radius:.6rem;padding:.55rem .75rem .6rem;margin:0}
  .dr-it.hi{border-color:var(--tv-line-strong);box-shadow:0 0 0 2px rgba(184,134,59,.25)}
  .dr-it{cursor:pointer}.dr-it:hover{border-color:var(--tv-line-strong)}   /* the whole card opens its pop-up card (2026-11-12) */
  .dr-main{min-width:0}
  .dr-n{display:inline-block;padding-right:2.6rem;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.08rem;color:var(--tv-link);text-decoration:underline;text-decoration-color:rgba(146,64,14,.35);text-underline-offset:3px}
  .dr-n:hover{color:var(--tv-icon,#B45309);text-decoration-color:currentColor}
  .dr-m{font-size:.82rem;color:var(--tv-meta);margin-top:.12rem;line-height:1.35}
  .dr-open{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));font-weight:600}.dr-closed{color:var(--tv-meta);font-weight:600}   /* closed right now: not a fault, so never red (design 3c) */
  .dr-unk{color:var(--tv-meta);font-style:italic}   /* hours not known (P10): said plainly, never a guess */
  .dr-badge.dr-shut{border-color:color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));background:var(--tv-hold-bg);color:color-mix(in srgb,var(--tv-hold) 75%,var(--tv-ink))}   /* marked closed (decisions/0072) */
  .dr-badge{font-size:.62rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;border:1px solid color-mix(in srgb,var(--tv-moss) 40%,var(--tv-canvas));background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));border-radius:.3rem;padding:0 .3rem;margin-left:.3rem;vertical-align:middle}
  /* the name across the top; the actions as a column of round icons down the right edge, under the Link button
     (one line of controls down the right); the details on the left */
  .dr-main{display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:.7rem;align-items:start}
  .dr-hd{grid-column:1/-1;margin-bottom:.35rem}
  /* the tools in a row, not stacked (Jason, 2026-11-12: "show the tool kit horizontally vs stacked because they are taking up
     valuable real estate"): on the name's line, left of the Link button; the details use the card's whole width */
  .dr-hd{grid-column:1;grid-row:1}
  .dr-acts{grid-column:2;grid-row:1;display:flex;flex-direction:row;flex-wrap:nowrap;gap:.3rem;margin-right:2.3rem}
  .dr-main>.dr-m{grid-column:1/-1}
  @media (min-width:521px){.dr-n{padding-right:0}}
  @media (max-width:520px){.dr-hd{grid-column:1/-1}.dr-acts{grid-column:1/-1;grid-row:2;margin:0 0 .3rem}}
  .dr-it{padding-right:.55rem}
  .dr-a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;text-decoration:none;color:var(--tv-icon,#B45309);background:var(--tv-field);border:1px solid var(--tv-line-strong);border-radius:999px;cursor:pointer;padding:0}
  .dr-a span{display:flex;align-items:center;justify-content:center}
  .dr-a svg{width:16px;height:16px}
  .dr-a:hover{border-color:var(--tv-icon,#B45309);background:var(--tv-hold-bg)}
  .dr-num{font:600 .8rem var(--tv-font-ui);color:var(--tv-meta);margin-right:.15rem}   /* the list's numbers: quiet until the map's pins use them */
  .dr-mail{display:inline-block;margin-left:.2rem;font-size:.74rem;border:1px solid var(--tv-line-strong);border-radius:999px;padding:0 .45rem;color:var(--tv-meta)}
  .dr-mw{position:relative;display:inline-flex}.dr-mb{font:700 1rem/1 system-ui,sans-serif;letter-spacing:.05em}
  .dr-ml{position:absolute;right:0;top:calc(100% + .3rem);z-index:30;min-width:13rem;display:flex;flex-direction:column;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.6rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.35rem .3rem .3rem}
  .dr-ml[hidden]{display:none}.dr-ml.up{top:auto;bottom:calc(100% + .3rem)}
  .dr-ml a,.dr-ml button[role=menuitem],.dr-mo{display:flex;align-items:center;gap:.5rem;padding:.45rem .55rem;border:0;background:none;border-radius:.4rem;font:500 .92rem var(--tv-font-ui);color:var(--tv-ink);text-decoration:none;cursor:pointer;text-align:left}
  .dr-ml a:hover,.dr-ml button[role=menuitem]:hover{background:var(--tv-sec,#EDE6D5)}.dr-ml svg{width:16px;height:16px;color:var(--tv-icon,#B45309);flex:none}
  .dr-mo{color:var(--tv-meta);cursor:default;flex-wrap:wrap}.dr-mo small{flex-basis:100%;margin-left:1.55rem;font-size:.76rem}
  .dr-mx{align-self:flex-end;width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);font-size:1rem;line-height:1;cursor:pointer;margin-bottom:.1rem}
  .dr-hd .tv-conf-chip{margin-left:.35rem;vertical-align:.1rem}
  .dr-al{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .dr-mapbox{position:relative;border:1px solid var(--tv-line-strong);border-radius:1rem;overflow:hidden;background:var(--tv-canvas);height:calc(100vh - var(--ta-top,64px) - 170px);min-height:380px}
  .dr-mapbox[hidden]{display:none}
  .dr-pop b{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:.95rem;color:var(--tv-ink)}.dr-pop p{margin:.15rem 0;font-size:.78rem;color:var(--tv-meta)}
  .dr-pop .dr-pa{display:flex;gap:.6rem;margin-top:.35rem}.dr-pop .dr-pa a,.dr-pop .dr-pa button{font-size:.78rem;font-weight:600;color:var(--tv-link);background:none;border:0;padding:0;cursor:pointer;text-decoration:underline}
  .dr-map{position:absolute;inset:0}
  .dr-mapbar{position:absolute;top:.5rem;right:.5rem;z-index:500;display:flex;gap:.35rem}
  .dr-mapbar button{background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.6rem;padding:.3rem .65rem;font-size:.8rem;font-weight:600;color:var(--tv-ink);cursor:pointer;box-shadow:0 4px 10px -6px rgba(0,0,0,.4)}
  .dr-mapnote{position:absolute;left:.5rem;bottom:.4rem;z-index:500;font-size:.7rem;color:var(--tv-meta);background:rgba(255,251,242,.9);border-radius:.35rem;padding:.05rem .35rem}
  .dr-pin{position:relative}
  .dr-pin span{display:block;width:26px;height:26px;border-radius:999px 999px 999px 2px;transform:rotate(-45deg);background:var(--tv-icon,#B45309);border:2px solid var(--tv-on-umber);box-shadow:0 2px 5px rgba(0,0,0,.45)}
  .dr-pin b{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font:700 11px/1 system-ui}
  .dr-pin.hi span{background:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}
  .dr-tell{font-size:.85rem;color:var(--tv-meta);margin:.7rem 0 0;text-align:center}
  .dr-ask{background:var(--tv-card-a);border:2px solid var(--tv-icon,#B45309);border-radius:1rem;padding:1.1rem 1.2rem;text-align:center;color:var(--tv-ink);box-shadow:0 10px 26px -18px rgba(62,43,31,.6)}
  .dr-ask h3{margin:0 0 .25rem;font:700 1.25rem var(--tv-font-head,ui-serif,Georgia,serif)}.dr-ask p{margin:0 0 .8rem;color:var(--tv-meta);font-size:.92rem}
  .dr-askb{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:.6rem}.dr-askb .dr-go{padding:.6rem 1.3rem}.dr-askb .dr-tg{padding:.55rem 1rem!important;font-size:.92rem}
  /* C.5a (0145 V06): the sentence's lines and the list's cards held while the first search runs, so nothing below them jumps */
  @media (max-width:767px){.dr-sent.dr-wait{min-height:3.8rem}}   /* on a phone it takes its own two lines; beside the controls it doesn't move them */
  .dr-skel>.dr-sum{padding:0 .3rem}.dr-skel i{display:block;height:7.5rem;margin:.6rem 0 0;border:1px solid var(--tv-line);border-radius:1rem;background:var(--tv-card-a);opacity:.55}
  @media (max-width:767px){.dr-skel i{margin-top:3px;border-radius:.5rem}}
  .dr-empty{background:var(--tv-canvas);border:1px dashed var(--tv-line-strong);border-radius:1rem;padding:1rem;color:var(--tv-ink);font-size:.92rem}
  @media (max-width: 900px){.dr-top{flex-wrap:wrap}.dr-top .dr-chips{order:3;flex-basis:100%;justify-content:flex-start}.dr-view{margin-left:auto}
    html.dr-mapmode .dr-top .dr-chips{flex-basis:auto;margin-left:0}}
  @media (max-width: 640px){html.dr-mapmode .dr-tl{max-width:none;flex:1}.dr-view button{padding:.35rem .6rem}}
  @media (max-width: 767px){.dr-it{border-radius:.5rem}}   /* edge to edge: the phone standard (#dr-list is tv-bleed) */

  /* Arrange (Jason, 2026-11-19): per page, what shows, the details on each card in order, the computer layout; remembered on this device */
  .dr-adl{position:absolute;right:0;top:calc(100% + .35rem);z-index:40;width:min(22rem,calc(100vw - 6px));max-height:min(34rem,calc(100vh - 8rem));overflow:auto;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.6rem .75rem .7rem}
  .dr-adl h4{margin:.55rem 0 .3rem;font:700 .74rem var(--tv-font-ui);letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta)}.dr-adl h4:first-of-type{margin-top:.1rem}
  .dr-adl .dr-pills{display:flex;flex-wrap:wrap;gap:.3rem}
  .dr-adl .dr-pills button{border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-ink);border-radius:999px;padding:.22rem .7rem;font:600 .85rem var(--tv-font-ui);cursor:pointer}
  .dr-adl .dr-pills button[aria-pressed="true"]{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber)}
  .dr-sw{display:flex;align-items:center;gap:.5rem;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;padding:.2rem 0}.dr-sw input{width:1rem;height:1rem;accent-color:var(--tv-umber)}
  .dr-sw small,.dr-adn{display:block;color:var(--tv-meta);font-size:.78rem;font-weight:400}
  .dr-dl{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.25rem}
  .dr-dl li{display:flex;align-items:center;gap:.35rem;border:1px solid var(--tv-line);background:var(--tv-field);border-radius:.5rem;padding:.25rem .3rem .25rem .5rem;font:500 .88rem var(--tv-font-ui);cursor:grab}
  .dr-dl li.drag{opacity:.45}.dr-dl li span{flex:1}.dr-dl li i{font-style:normal;color:var(--tv-meta);font-size:.75rem}
  .dr-dl li button{width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);cursor:pointer;line-height:1;padding:0}
  .dr-dl li button:disabled{opacity:.35;cursor:default}
  .dr-dadd{margin-top:.35rem;border:1px solid var(--tv-line-strong);border-radius:.55rem;background:var(--tv-field);padding:.3rem .45rem;font:inherit;font-size:.86rem;color:var(--tv-ink);max-width:100%}
  .dr-ard{margin-top:.6rem;border:0;background:none;color:var(--tv-link);text-decoration:underline;font:600 .85rem var(--tv-font-ui);cursor:pointer;padding:0}
  /* the Table view: each column sorts; its menu works like a spreadsheet's (A to Z, Z to A, search, ticks with counts) */
  .dr-tw{overflow:auto;border:1px solid var(--tv-line-strong);border-radius:.8rem;background:var(--tv-card-a)}
  .dr-tb{width:100%;border-collapse:collapse;font:400 .9rem var(--tv-font-ui);color:var(--tv-ink)}
  .dr-tb th{position:sticky;top:0;background:var(--tv-sec,#EDE6D5);text-align:left;padding:0;border-bottom:1px solid var(--tv-line-strong);white-space:nowrap;z-index:1}
  .dr-tb th button{display:flex;align-items:center;gap:.3rem;width:100%;border:0;background:none;padding:.5rem .6rem;font:700 .8rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;text-align:left}
  .dr-tb th button b{font-weight:400;color:var(--tv-meta)}.dr-tb th.f button{color:var(--tv-link)}
  .dr-tb td{padding:.45rem .6rem;border-bottom:1px solid var(--tv-line);vertical-align:top}.dr-tb tr:last-child td{border-bottom:0}
  .dr-tb td a{color:var(--tv-link,#92400E);font-weight:600}.dr-tb .dr-open{white-space:nowrap}
  .dr-tb tbody tr:hover{background:var(--tv-field)}
  .dr-cm{position:absolute;z-index:45;width:min(17rem,calc(100vw - 6px));background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.45rem}
  .dr-cm>button,.dr-cm label{display:flex;align-items:center;gap:.45rem;width:100%;border:0;background:none;padding:.35rem .4rem;border-radius:.4rem;font:500 .88rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;text-align:left}
  .dr-cm>button:hover,.dr-cm label:hover{background:var(--tv-field)}
  .dr-cm input[type=search]{width:100%;box-sizing:border-box;margin:.3rem 0;border:1px solid var(--tv-line-strong);border-radius:.45rem;background:var(--tv-field);padding:.3rem .45rem;font:inherit;font-size:.86rem}
  .dr-cm .dr-cml{max-height:12rem;overflow:auto}.dr-cm label small{flex:none}.dr-cm small{color:var(--tv-meta)}.dr-cm hr{border:0;border-top:1px solid var(--tv-line);margin:.3rem 0}
  .dr-cm .dr-cmx{position:absolute;top:.3rem;right:.3rem;width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);justify-content:center;padding:0}
  .dr-tnote{margin:.35rem .2rem 0;font-size:.8rem;color:var(--tv-meta)}
  /* the list beside the map (one side of the A/B test, computers only): numbered cards left, their pins right */
  @media (min-width:1024px){
    .dr-grid.side{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,44%);gap:.8rem;align-items:start}
    .dr-grid.side .dr-mapbox{position:sticky;top:calc(var(--ta-top,64px) + .5rem);height:calc(100vh - var(--ta-top,64px) - 1.5rem);min-height:380px}
    .dr-grid.side .dr-mapbar{display:flex;gap:.35rem}
  }
  .dr-sarea{background:var(--tv-btn,#92400E)!important;color:var(--tv-btn-text,#E4DCC5)!important;border-color:var(--tv-btn,#92400E)!important}
  @media (max-width:767px){.dr-view [data-view=table]{display:none}   /* a phone keeps List | Map */
    .dr-ctl{gap:.35rem}.dr-srt>span{display:none}.dr-srt select{max-width:9.5rem}.dr-fb{padding:.36rem .6rem}   /* Filters, Arrange and Sort fit one line at 390 */
    .dr-hd{padding-right:2.6rem}   /* the name, badges and score chip stay clear of the Link button */
    .dr-adl{position:fixed;left:3px;right:3px;width:auto;top:auto;bottom:3px;max-height:calc(100vh - 7rem)}}   /* Arrange rises from the foot on a phone */
</style>

<!-- The finder (Jason, 2026-11-12: "The listing/directory feels lackluster. Need to present a reliable, easy to use tool to find
     the business you want or need"; shown: a business directory's one search box with Refine under it, and a list with "Search
     by name or city" and "Filter by city"): one box for the name, the kind or the need, the place beside it, Search; then Refine,
     each list counting what's in the results. The header's search still searches everything. -->
<!-- The finder (Jason, 2026-11-19: "condense the find a business into the same style bar we have on the home page"): what |
     where | search, as the home page; under it, what the words could mean (the kind first, as the site's search does:
     "Barber shops · near Show Low / near me"), then the businesses. Refine is the sentence over the list (decisions/0058's
     Get Local note, 2026-11-19). The header's search still searches everything. -->
<section class="dr-find tv-bleed" aria-label="Find a business">
  <div class="dr-ftop"><h1 class="dr-fh">Get Local</h1>
  <div class="dr-view" role="group" aria-label="Show as">
    <button type="button" data-view="list" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>List</button>
<button type="button" data-view="table" aria-pressed="false" title="Show as a table: sort and filter each column"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16"/></svg>Table</button>
    <button type="button" data-view="map" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><path d="M8 2v16M16 6v16"/></svg>Map</button>
  </div></div>
  <form class="dr-form" id="dr-form" role="search" autocomplete="off">
    <label class="dr-qbox"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg>
      <input id="dr-q" type="search" placeholder="What are you looking for?" aria-label="Business name, kind of business, or what you need" aria-controls="dr-sug" aria-autocomplete="list" data-spell enterkeyhint="search">
      <button type="button" class="dr-qx" id="dr-qx" hidden aria-label="Clear the words" title="Clear the words">&times;</button></label>
    <span class="dr-wgrp dr-wbw"><button type="button" class="dr-wbtn" id="dr-where" title="Choose a place" aria-haspopup="dialog"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><span id="dr-where-l">Choose a place</span></button><button type="button" class="dr-wtgt tv-pbox-tgt" id="dr-where-tgt" title="Use my location" aria-label="Use my location"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg></button><button type="button" class="dr-wx" id="dr-where-x" hidden title="Clear the place: search everywhere" aria-label="Clear the place">&times;</button></span>
    <button type="submit" class="dr-go" aria-label="Search" title="Search"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.8-4.8"/></svg></button>
  </form>
  <div class="dr-sug" id="dr-sug" role="listbox" aria-label="Suggestions" hidden></div>
  <!-- the lists the sentence opens, kept for the page's own code (filled with their counts from the results) -->
  <div hidden><select id="dr-kind" aria-label="Kind of business"><option value="">All kinds</option></select><select id="dr-town" aria-label="Town"><option value="">All towns</option></select><span id="dr-distw"><select id="dr-dist" aria-label="How far"><option value="2">2 mi</option><option value="5">5 mi</option><option value="10">10 mi</option><option value="25">25 mi</option><option value="50">50 mi</option></select></span></div>
</section>
<div class="dr-head">
  <h2 class="dr-h1 dr-sr" id="dr-title">Businesses near you</h2>
  <div class="dr-row1"><p class="dr-sent dr-wait" id="dr-sent" aria-live="polite"></p>
  <div class="dr-ctl"><details class="dr-fdd" id="dr-fdd"><summary class="dr-fb" title="Show only some businesses"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>Filters <b id="dr-fn" hidden></b> <i aria-hidden="true">&#9662;</i></summary>
    <div class="dr-fddl" role="group" aria-label="Filters"><button type="button" class="dr-fx" data-fdd-x aria-label="Close" title="Close">&times;</button><p class="dr-fh">Show only</p><span class="dr-tgs"><button type="button" class="dr-tg" data-f="open" aria-pressed="false">Open now</button><button type="button" class="dr-tg" data-f="phone" aria-pressed="false">Has a phone</button><button type="button" class="dr-tg" data-f="website" aria-pressed="false">Has a website</button><button type="button" class="dr-tg" data-f="fav" aria-pressed="false">&#9733; Favorites</button></span></div></details>
  <details class="dr-fdd dr-add" id="dr-arr"><summary class="dr-fb" title="Arrange the results: how many, what shows, each card's details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>Arrange <i aria-hidden="true">&#9662;</i></summary>
    <div class="dr-adl" id="dr-adl" role="group" aria-label="Arrange"></div></details>
  <label class="dr-srt"><span>Sort</span><select id="dr-sort" aria-label="Sort by"><option value="best" title="The listings we’re most certain of first">Best match</option><option value="distance">Nearest first</option><option value="name">A to Z</option></select></label></div></div>
  <div class="dr-pop" id="dr-pop" role="dialog" hidden></div>
  <p class="dr-sum" id="dr-sum"></p>
  <div class="dr-chips" id="dr-chips" hidden></div>
</div>
<div class="dr-grid" id="dr-grid">
  <section aria-label="Results" id="dr-listbox"><div id="dr-list" class="tv-bleed"><div class="dr-skel" aria-busy="true"><p class="dr-sum">Loading…</p><i></i><i></i><i></i><i></i><i></i></div></div><script<?= tv_nonce() ?>>(function () {   // the cards' room is held only when a place is known (the link's, or this tab's), before the first paint; with none, the list asks for one (C.5a)
    var u = new URLSearchParams(location.search), k = ['cluster', 'geo', 'hub', 'town', 'lat', 'q', 'cat'].some(function (x) { return u.get(x); }), t = false;
    try { var l = JSON.parse(sessionStorage.getItem('traversence_location') || 'null'); t = !!(l && (l.scope || l.city || l.zip || l.lat != null)); } catch (e) {}
    if (!k && !t) { document.getElementById('dr-list').innerHTML = ''; document.getElementById('dr-sent').classList.remove('dr-wait'); }
  })();</script><div id="dr-pg"></div><div id="dr-tellbox"></div></section>
  <aside class="dr-mapbox" aria-label="Map" hidden><div class="dr-map" id="dr-map"></div>
    <div class="dr-mapbar"><button type="button" id="dr-sarea" class="dr-sarea" hidden>Search this area</button><button type="button" id="dr-mapmore" hidden>Show more</button><button type="button" id="dr-mapfull" hidden title="Open the full map">Full map</button></div><span class="dr-mapnote" id="dr-mapnote">Pins mark the ZIP area, not the street.</span></aside>
</div>

<script src="/js/tv-pager.js?v=<?= (int) @filemtime(__DIR__ . '/../js/tv-pager.js') ?>"></script>
<script>
  window.tvSendCrawler = <?= $sendCrawler ? json_encode(['csrf' => (string) $_SESSION['csrf_token']]) : 'null' ?>;
(function () {
  'use strict';
  var U = new URLSearchParams(location.search);
  var S = { q: U.get('q') || '', cat: U.get('cat') || '', catname: U.get('catname') || '', open: U.get('open') === 'now', has: (U.get('has') || '').split(',').filter(Boolean),
            sort: U.get('sort') || 'best', fav: U.get('fav') === '1', radius: +(U.get('radius') || 250), page: 1, total: 0, items: [], town: U.get('in_town') || '' };
  var urlPlace = null;
  ['cluster', 'geo', 'hub'].forEach(function (k) { if (!urlPlace && U.get(k)) urlPlace = { key: k, slug: U.get(k) }; });
  // a town (from a place page's town list): centred on the town, nearest first
  if (!urlPlace && U.get('town')) urlPlace = { town: U.get('town'), state: U.get('state') || '' };
  var localName = U.get('local') || '';   // a local name ("Wide Ruins"): its cluster's listings, said in the summary
  if (!urlPlace && U.get('lat') && U.get('lon')) urlPlace = { lat: +U.get('lat'), lon: +U.get('lon'), near: U.get('near') || 'the place you searched' };
  var LEVEL = { cluster: 'cluster', geo_hub: 'geo', hub: 'hub' };
  var $ = function (id) { return document.getElementById(id); };
  // A town as the place opens within 10 miles, the distance still changeable (decisions/0053, "Town names on a place page
  // open Get Local"; P9, Jason 2026-11-13): a town picked in the place pop-up or opened by ?town=. "Near me", a ZIP and a
  // map area keep the nearest first with no cap (decisions/0060, "The nearest, no cap"). A ?radius= in the address wins.
  var TOWN_MI = 10;
  function townPoint() {
    if (urlPlace) return !!urlPlace.town;
    var l = loc(); return !!(l && !(l.scope && l.scope.slug) && l.city && !l.zip && (l.lat == null || l.lat === ''));
  }
  if (!U.get('radius') && townPoint()) S.radius = TOWN_MI;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function loc() { return (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null; }
  function short(s) { return String(s || '').split('·')[0].trim(); }

  // ---- where ----
  function place() {
    if (urlPlace && urlPlace.key) return { qs: urlPlace.key + '=' + encodeURIComponent(urlPlace.slug), label: regionName(), region: true };
    if (urlPlace && urlPlace.town) return { qs: 'city=' + encodeURIComponent(urlPlace.town) + '&state=' + encodeURIComponent(urlPlace.state || 'all') + '&radius=' + S.radius, label: urlPlace.town, point: true };
    if (urlPlace) return { qs: 'lat=' + urlPlace.lat + '&lon=' + urlPlace.lon + '&radius=' + S.radius, label: urlPlace.near === 'your precise location' ? 'you' : urlPlace.near, point: true };
    var l = loc(); if (!l) return null;
    if (l.scope && l.scope.slug && LEVEL[l.scope.level]) return { qs: LEVEL[l.scope.level] + '=' + encodeURIComponent(l.scope.slug), label: l.scope.label || l.scope.slug, region: true };
    if (l.lat != null && l.lon != null) return { qs: 'lat=' + l.lat + '&lon=' + l.lon + '&radius=' + S.radius, label: l.townName || l.savedLabel || 'your location', point: true };
    if (l.zip) return { qs: 'zip=' + encodeURIComponent(l.zip) + '&radius=' + S.radius, label: 'ZIP ' + l.zip, point: true };
    if (l.city) return { qs: 'city=' + encodeURIComponent(l.city) + '&state=' + encodeURIComponent(l.state || 'all') + '&radius=' + S.radius, label: l.city + (l.state && l.state !== 'all' ? ', ' + l.state : ''), point: true };
    if (l.state && l.state !== 'all') return { qs: 'state=' + encodeURIComponent(l.state), label: l.state };
    return null;
  }
  function regionName() {
    var l = loc();
    if (l && l.scope && l.scope.slug === urlPlace.slug && l.scope.label && l.scope.label !== l.scope.slug) return l.scope.label;
    return urlPlace.slug.replace(/^cluster-\w+-/, '').replace(/-/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  // Near me: the device's own location, nearest first (the place itself is set in the header, like everywhere else)
  function nearMe(b) {
      if (!navigator.geolocation) { TvAsk.say('This browser can\'t share a location. Choose a place instead.', { anchor: b }); return; }
      b.textContent = 'Finding you…';
      navigator.geolocation.getCurrentPosition(function (pos) {
        urlPlace = { lat: +pos.coords.latitude.toFixed(4), lon: +pos.coords.longitude.toFixed(4), near: 'you' };   // never shown as a place: "near you"
        S.sort = 'distance'; search(true);
      }, function () { b.textContent = '◎ Near me'; TvAsk.say('Location access was blocked or unavailable. You can choose a place instead.', { anchor: b }); }, { timeout: 10000, maximumAge: 60000 });
  }
  window.onLocationChanged = function () { urlPlace = null; localName = ''; S.radius = townPoint() ? TOWN_MI : 250; search(true); };   // a place chosen in the menu: its own distance (a town 10 mi, decisions/0053; else no cap, 0060)
  // The header's search: on this page, Enter (or the words in the address) shows them here
  window.tvSearchSync = function (q) { if (q !== S.q) window.tvSearchHere(q); };   // the dropdown closed: the list follows the field
  window.tvSearchHere = function (q) { qPend = false; clearTimeout(qT); S.q = q; S.cat = ''; S.catname = ''; S.town = ''; search(true); return true; };

  // ---- filters ----
  function nearOn() { return !!(urlPlace && !urlPlace.key && (urlPlace.near === 'you' || urlPlace.near === 'your precise location')); }
  // Under the title: only what's narrowing the results, each removable (×). Turning filters on is the search's job
  // (its Quick picks: Near me, Open now, Has a phone, Has a website, Favorites).
  function paintChips() {
    var x = function (f, label) { return '<button type="button" class="dr-c sm on x" data-f="' + f + '" title="Remove">' + label + '</button>'; };
    var h = '';
    if (S.cat) h += x('uncat', esc(S.catname || S.cat.replace(/-/g, ' ')));
    if (S.town) h += x('untown', esc(S.town.toLowerCase().replace(/\b\w/g, function (c) { return c.toUpperCase(); })));
    if (S.q) h += x('unq', esc(S.q));
    if (nearOn()) h += x('near', 'Near me');   // Open now, phone, website and Favorites show lit in the finder's Refine instead
    $('dr-chips').innerHTML = h;
    paintFinder();
    var p = place(), w = $('dr-where-l'), lb = nearOn() ? 'Near you' : p ? short(p.label) : '';
    if (w) w.textContent = lb || 'Choose a place';   // the place button shows the place
    var wx = $('dr-where-x'); if (wx) wx.hidden = !p && !nearOn();   // a place set: × clears it (Jason, 2026-11-10)
  }
  // ★ Favorites: the businesses you linked or saved to your Address Book
  var favIds = null;
  function loadFavs() {
    if (favIds) return Promise.resolve(favIds);
    var j = function (u) { return fetch(u, { credentials: 'include' }).then(function (r) { return r.ok ? r.json() : {}; }).catch(function () { return {}; }); };
    return Promise.all([j('/user/api/links.php?list=1'), j('/user/api/people.php?view=contacts')]).then(function (a) {
      var ids = {};
      ((a[0].links || {}).business || []).forEach(function (l) { ids[+l.target_id] = 1; });
      (a[1].contacts || []).forEach(function (c) { if (c.kind === 'business' && c.ref) ids[+c.ref] = 1; });
      favIds = Object.keys(ids).filter(function (k) { return +k > 0; });
      favIds.signedIn = !!(a[0].ok || a[1].ok);
      return favIds;
    });
  }
  function toggle(f, b) {
    if (f === 'uncat') { S.cat = ''; S.catname = ''; }
    else if (f === 'untown') S.town = '';
    else if (f === 'unq') { qPend = false; clearTimeout(qT); $('dr-q').value = ''; S.q = ''; var hi0 = document.querySelector('.tvs-in'); if (hi0) hi0.value = ''; }
    else if (f === 'near') { if (nearOn()) { urlPlace = null; S.sort = 'best'; } else { nearMe(b || {}); return; } }
    else if (f === 'open') S.open = !S.open;
    else if (f === 'fav') { S.fav = !S.fav; if (S.fav) { loadFavs().then(function () { search(true); }); return; } }
    else { var i = S.has.indexOf(f); if (i >= 0) S.has.splice(i, 1); else S.has.push(f); }
    search(true);
  }
  $('dr-chips').addEventListener('click', function (e) { var b = e.target.closest('button[data-f]'); if (b) toggle(b.getAttribute('data-f'), b); });
  $('dr-chips').addEventListener('change', function (e) { var f = e.target.getAttribute('data-f'); if (f === 'radius') S.radius = +e.target.value; if (f === 'sort') S.sort = e.target.value; search(true); });
  // ---- the finder (2026-11-12): the words, the place, Search; Refine's lists count what's in the results ----
  var facets = null;
  function opts(sel, first, list, cur, curName) {
    var h = '<option value="">' + first + '</option>', have = false;
    (list || []).forEach(function (o) { if (o[0] === cur) have = true; h += '<option value="' + esc(o[0]) + '"' + (o[0] === cur ? ' selected' : '') + '>' + esc(o[1]) + (o[2] != null ? ' (' + Number(o[2]).toLocaleString() + ')' : '') + '</option>'; });
    if (cur && !have) h += '<option value="' + esc(cur) + '" selected>' + esc(curName || cur) + '</option>';
    sel.innerHTML = h;
  }
  function paintFinder() {
    var qi = $('dr-q'); if (!qi) return;
    if (document.activeElement !== qi && !qPend) qi.value = S.q; $('dr-qx').hidden = !qi.value;
    opts($('dr-kind'), 'All kinds', facets && facets.kinds ? facets.kinds.map(function (k) { return [k.slug, k.name, k.n]; }) : [], S.cat, S.catname || S.cat.replace(/-/g, ' '));
    opts($('dr-town'), 'All towns', facets && facets.towns ? facets.towns.map(function (t) { return [t.value, t.name, t.n]; }) : [], S.town, S.town);
    var p = place(), pt = !!(p && p.point);
    $('dr-distw').hidden = !pt; if (pt) { var ds = $('dr-dist'); if (![].some.call(ds.options, function (o) { return +o.value === S.radius; })) ds.insertAdjacentHTML('beforeend', '<option value="' + S.radius + '">' + S.radius + ' mi</option>'); ds.value = String(S.radius); }
    var so = $('dr-sort'); so.querySelector('[value=distance]').disabled = !pt; so.querySelector('[value=distance]').title = pt ? '' : 'Set a town, a ZIP or your location to sort by distance';
    so.value = S.sort === 'distance' && !pt ? 'best' : (nearOn() && S.sort === 'best' && !U.get('sort') ? 'distance' : (S.sort || 'best'));   // what the list really uses
    document.querySelectorAll('.dr-tg').forEach(function (b) { var f = b.getAttribute('data-f'); b.setAttribute('aria-pressed', String(f === 'open' ? S.open : f === 'fav' ? S.fav : S.has.indexOf(f) >= 0)); });
    var nf = (S.open ? 1 : 0) + (S.fav ? 1 : 0) + S.has.length, fn = $('dr-fn'); if (fn) { fn.textContent = nf; fn.hidden = !nf; }   // Filters' count
  }
  // The words follow what's typed (Jason, 2026-11-13; decisions/0058 note): from 3 letters the list updates on its own once
  // typing pauses, Search or Enter at once, and emptying the box shows everything again. Words typed and not yet searched
  // are never wiped by a redraw (a place chosen, Refine): the next search takes them.
  var qPend = false, qT = 0;
  function takeWords() {
    var v = $('dr-q').value.trim(); qPend = false; clearTimeout(qT);
    if (v !== S.q) { S.q = v; S.cat = ''; S.catname = ''; S.town = ''; }   // new words: Refine starts again from what they find
    var hin = document.querySelector('.tvs-in'); if (hin) hin.value = S.q;
  }
  $('dr-form').addEventListener('submit', function (e) { e.preventDefault(); takeWords(); hideSug(); search(true); });
  $('dr-q').addEventListener('input', function () {
    $('dr-qx').hidden = !this.value;
    var v = this.value.trim(); clearTimeout(qT); qPend = v !== S.q;
    if (qPend && (v.length >= 3 || v === '')) qT = setTimeout(function () { takeWords(); search(true); }, 350);
  });
  $('dr-qx').addEventListener('click', function () { qPend = false; clearTimeout(qT); $('dr-q').value = ''; this.hidden = true; if (S.q) { S.q = ''; search(true); } $('dr-q').focus(); });
  $('dr-kind').addEventListener('change', function () { var o = this.options[this.selectedIndex]; S.cat = this.value; S.catname = this.value ? o.textContent.replace(/ \([\d,]+\)$/, '') : ''; S.town = ''; search(true); });
  $('dr-town').addEventListener('change', function () { S.town = this.value; search(true); });
  $('dr-dist').addEventListener('change', function () { S.radius = +this.value; search(true); });
  $('dr-sort').addEventListener('change', function () { S.sort = this.value; search(true); });
  document.querySelector('.dr-tgs').addEventListener('click', function (e) { var b = e.target.closest('[data-f]'); if (b) toggle(b.getAttribute('data-f'), b); });
  (function () {   // Filters closes with its ×, Esc or a click outside (CLAUDE.md: everything that pops open closes)
    var d = $('dr-fdd'); if (!d) return;
    d.querySelector('[data-fdd-x]').addEventListener('click', function () { d.removeAttribute('open'); });
    document.addEventListener('click', function (e) { if (d.open && !d.contains(e.target)) d.removeAttribute('open'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && d.open) { d.removeAttribute('open'); d.querySelector('summary').focus(); } });
  })();


  // while a search runs, the list keeps the height of cards (C.5a, 0145 V06): the page under it doesn't jump when they come
  var SKEL = '<div class="dr-skel" aria-busy="true"><p class="dr-sum">Loading…</p><i></i><i></i><i></i><i></i><i></i></div>';
  // ---- the sentence (Refine, Jason 2026-11-19): what, how far, where; each part opens what changes it ----
  var DIST = [[2, '2 mi'], [5, '5 mi'], [10, '10 mi'], [25, '25 mi'], [50, '50 mi'], [250, 'Any distance']];
  function distName(r) { return r >= 250 ? 'any distance' : r + ' mi'; }
  function tok(act, label, on, x) { return '<button type="button" class="dr-tok' + (on ? ' k' : '') + '" data-tok="' + act + '" aria-haspopup="' + (x ? 'false' : 'dialog') + '" aria-expanded="false">' + label + ' <i aria-hidden="true">' + (x ? '×' : '▾') + '</i></button>'; }
  function paintSentence() {
    var el = $('dr-sent'); if (!el) return;
    el.classList.remove('dr-wait');   // its lines were held while the first search ran (C.5a: nothing below it jumps)
    var p = place(), h = '', n = S.total || 0;
    if (!p && !S.q && !S.cat) { el.innerHTML = ''; return; }
    h += '<b>' + (S.items.length ? n.toLocaleString() : 'No') + '</b> ';
    if (S.cat) h += tok('uncat', esc(S.catname || S.cat.replace(/-/g, ' ')), true, true);
    else h += tok('kind', n === 1 ? 'business' : 'businesses');
    if (S.q) h += ' matching ' + tok('unq', esc(S.q), true, true);
    if (p && p.point) h += ' within ' + tok('dist', distName(S.radius)) + ' of ';
    else h += p ? ' in ' : ' ';
    h += tok('place', p ? esc(nearOn() ? 'you' : short(p.label)) : 'choose a place');
    if (S.town) h += ' · ' + tok('untown', esc(S.town.toLowerCase().replace(/\b\w/g, function (c) { return c.toUpperCase(); })), true, true);
    [['open', S.open, 'Open now'], ['phone', S.has.indexOf('phone') >= 0, 'Has a phone'], ['website', S.has.indexOf('website') >= 0, 'Has a website'], ['fav', S.fav, '\u2605 Favorites']]
      .forEach(function (f) { if (f[1]) h += ' · ' + tok('unf-' + f[0], f[2], true, true); });   // a filter that's on, said in the sentence (2026-11-19)
    el.innerHTML = h;
  }
  function closePop() { var pp = $('dr-pop'); pp.hidden = true; pp.innerHTML = ''; document.querySelectorAll('.dr-tok[aria-expanded="true"]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); }
  function openPop(b, html) {
    var pp = $('dr-pop'), was = b.getAttribute('aria-expanded') === 'true';
    closePop(); if (was) return;
    pp.innerHTML = html; pp.hidden = false; b.setAttribute('aria-expanded', 'true');
    var hr = $('dr-sent').parentNode.getBoundingClientRect(), br = b.getBoundingClientRect();
    pp.style.top = (br.bottom - hr.top + 6) + 'px';
    pp.style.left = Math.max(0, Math.min(br.left - hr.left, hr.width - pp.offsetWidth)) + 'px';
  }
  $('dr-sent').addEventListener('click', function (e) {
    var b = e.target.closest('[data-tok]'); if (!b) return;
    var t = b.getAttribute('data-tok');
    if (t === 'uncat' || t === 'unq' || t === 'untown') { closePop(); toggle(t); return; }
    if (t.indexOf('unf-') === 0) { closePop(); toggle(t.slice(4)); return; }
    if (t === 'dist') {
      openPop(b, '<h4>How far</h4><div class="dr-ring" aria-hidden="true"><span style="width:' + (S.radius >= 250 ? 78 : 14 + Math.round(Math.sqrt(S.radius / 50) * 56)) + 'px;height:' + (S.radius >= 250 ? 78 : 14 + Math.round(Math.sqrt(S.radius / 50) * 56)) + 'px"></span><b></b></div>'
        + '<div class="dr-pills">' + DIST.map(function (d) { return '<button type="button" data-dist="' + d[0] + '" aria-pressed="' + (S.radius === d[0]) + '">' + d[1] + '</button>'; }).join('') + '</div>');
    } else if (t === 'kind') {
      var K = facets && facets.kinds ? facets.kinds : [];
      openPop(b, '<h4>What kind</h4>' + (K.length ? '<div class="dr-pills">' + K.slice(0, 18).map(function (k) { return '<button type="button" data-kind="' + esc(k.slug) + '" data-kname="' + esc(k.name) + '">' + esc(k.name) + (k.n != null ? '<small>' + Number(k.n).toLocaleString() + '</small>' : '') + '</button>'; }).join('') + '</div>' : '<p class="dr-sum">Type what you’re looking for in the bar above.</p>'));
    } else if (t === 'place') {
      var T = facets && facets.towns ? facets.towns : [];
      openPop(b, (T.length > 1 ? '<h4>Towns here</h4><div class="dr-pills">' + T.slice(0, 16).map(function (x) { return '<button type="button" data-town="' + esc(x.value) + '" aria-pressed="' + (S.town === x.value) + '">' + esc(x.name) + (x.n != null ? '<small>' + Number(x.n).toLocaleString() + '</small>' : '') + '</button>'; }).join('') + '</div>' : '')
        + '<h4>Somewhere else</h4><div class="dr-pills"><button type="button" data-ask="pop">Choose a place</button><button type="button" data-near>Near me</button></div>');
    }
  });
  $('dr-pop').addEventListener('click', function (e) {
    var d = e.target.closest('[data-dist]'), k = e.target.closest('[data-kind]'), tw = e.target.closest('[data-town]'), a = e.target.closest('[data-ask]'), nm = e.target.closest('[data-near]');
    if (d) { S.radius = +d.getAttribute('data-dist'); closePop(); search(true); }
    else if (k) { S.cat = k.getAttribute('data-kind'); S.catname = k.getAttribute('data-kname'); S.town = ''; closePop(); search(true); }
    else if (tw) { var v = tw.getAttribute('data-town'); S.town = S.town === v ? '' : v; closePop(); search(true); }
    else if (a) { closePop(); askPop(); }
    else if (nm) { closePop(); nearMe(nm); }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('#dr-pop,#dr-sent')) closePop(); if (!e.target.closest('#dr-sug,.dr-qbox')) hideSug(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closePop(); hideSug(); } });

  // ---- what the words could mean (the kind first, then the businesses): from the same search the list just ran ----
  function hideSug() { var g = $('dr-sug'); if (g) { g.hidden = true; g.innerHTML = ''; } }
  function paintSug() {
    var g = $('dr-sug'), qi = $('dr-q'); if (!g) return;
    if (document.activeElement !== qi || !S.q || S.cat) { hideSug(); return; }
    var p = place(), where = p ? (p.point ? 'near ' : 'in ') + short(p.label) : '', w = S.q.toLowerCase();
    var K = (facets && facets.kinds ? facets.kinds : []).filter(function (k) { return (S.meant || []).indexOf(k.name) >= 0 || String(k.name).toLowerCase().indexOf(w) >= 0; }).slice(0, 2);
    if (!K.length && facets && facets.kinds && facets.kinds[0]) K = [facets.kinds[0]];
    var h = '';
    K.forEach(function (k) {
      if (where) h += '<button type="button" role="option" data-sk="' + esc(k.slug) + '" data-sn="' + esc(k.name) + '"><b>' + esc(k.name) + '</b><small>' + esc(where) + '</small></button>';
      h += '<button type="button" role="option" data-sk="' + esc(k.slug) + '" data-sn="' + esc(k.name) + '" data-snear><b>' + esc(k.name) + '</b><small>near me</small></button>';
    });
    var B = S.items.slice(0, 3);
    if (B.length) h += (h ? '<hr>' : '') + B.map(function (l) { return '<a role="option" href="/listing/view.php?id=' + l.id + '"><b>' + esc(l.business_name) + '</b><small>' + esc([l.city, l.distance != null && p && p.point ? l.distance + ' mi' : ''].filter(Boolean).join(' · ')) + '</small></a>'; }).join('');
    g.innerHTML = h; g.hidden = !h;
  }
  $('dr-sug').addEventListener('click', function (e) {
    var b = e.target.closest('[data-sk]'); if (!b) return;
    qPend = false; clearTimeout(qT); S.cat = b.getAttribute('data-sk'); S.catname = b.getAttribute('data-sn'); S.q = ''; $('dr-q').value = ''; S.town = '';
    var hin = document.querySelector('.tvs-in'); if (hin) hin.value = '';
    hideSug();
    if (b.hasAttribute('data-snear')) nearMe(b); else search(true);
  });
  $('dr-q').addEventListener('keydown', function (e) {
    var g = $('dr-sug'); if (g.hidden || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
    var o = [].slice.call(g.querySelectorAll('[role=option]')); if (!o.length) return;
    e.preventDefault(); var i = o.indexOf(document.activeElement); i = e.key === 'ArrowDown' ? Math.min(o.length - 1, i + 1) : Math.max(0, i - 1); o[i].focus();
  });

  // the search's Quick picks, on this page
  window.tvQuick = {
    state: function () { return { near: nearOn(), open: S.open, phone: S.has.indexOf('phone') >= 0, website: S.has.indexOf('website') >= 0 }; },
    toggle: function (k) { toggle(k); }
  };
  (function () { var hin = document.querySelector('.tvs-in'); if (hin && S.q) hin.value = S.q; })();

  // ---- List or Map (remembered) ----
  var view = 'list';
  // the A/B test (Jason, 2026-11-19: "build it each way"): on a computer, half see the list beside the map, half the
  // List | Table | Map switch; each device keeps its side, Arrange can change it, and only the counts are kept ("gl-ab:…")
  var wide = window.matchMedia('(min-width: 1024px)');
  var AB = ''; try { AB = localStorage.getItem('tv_dir_ab') || ''; } catch (x) {}
  if (AB !== 'beside' && AB !== 'switch') { AB = Math.random() < .5 ? 'beside' : 'switch'; try { localStorage.setItem('tv_dir_ab', AB); } catch (x) {} }
  function abCount(what) { if (wide.matches && window.tvCount) tvCount({ m: 'act', k: 'gl-ab:' + AB + (what ? ':' + what : '') }); }
  function beside() { return AB === 'beside' && wide.matches && view === 'list'; }
  try { var v0 = localStorage.getItem('tv_dir_view'); view = v0 === 'map' || v0 === 'table' ? v0 : 'list'; } catch (x) {}
  // Map: the full-screen map (js/map-view.js), with every result as a pin, not just this page of the list. The header
  // search says what to look for while it's open; its panel has Open now, Phone, Website and ★ Favorites; Exit returns
  // to the list, which follows anything changed on the map.
  function openMap() {
    if (!window.TvMap) { setTimeout(openMap, 200); return; }
    try { localStorage.setItem('tv_dir_view', 'map'); } catch (x) {}
    var p = place(), changed = false;
    var T = [['open', 'Open now'], ['phone', 'Phone'], ['website', 'Website'], ['fav', '\u2605 Favorites']];
    var on = function (k) { return k === 'open' ? S.open : k === 'fav' ? S.fav : S.has.indexOf(k) >= 0; };
    var tools = function () { return T.map(function (t) { return '<button type="button" class="tvm-b' + (on(t[0]) ? ' go' : '') + '" data-act="' + t[0] + '" aria-pressed="' + on(t[0]) + '">' + t[1] + '</button>'; }).join(''); };
    TvMap.open({ panel: true,
      title: $('dr-title').textContent, radius: p && p.point ? S.radius : null, tools: tools(),
      titleFor: function (j) { var n = j.total != null ? j.total : (j.pins || []).length; return n.toLocaleString() + (n === 1 ? ' business' : ' businesses') + ' · ' + $('dr-title').textContent; },
      load: function (area, words) {
        if (words && words !== S.q) { qPend = false; clearTimeout(qT); S.q = words; S.cat = ''; S.catname = ''; changed = true; $('dr-title').textContent = '“' + words + '”' + (p ? (p.point ? ' near ' : ' in ') + short(p.label) : ''); }
        var go = function () {
          var u = new URLSearchParams(qs(1, true));
          // another area on the map: the list's Town choice no longer applies there (it hid every pin, 2026-11-12)
          if (area) { ['cluster', 'geo', 'hub', 'state', 'city', 'zip', 'town', 'in_town', 'near', 'lat', 'lon', 'radius'].forEach(function (k) { u.delete(k); }); u.set('lat', area.lat); u.set('lon', area.lon); u.set('radius', area.radius); }
          return fetch('/api/search_listings.php?' + u.toString() + '&view=map', { credentials: 'include' }).then(function (r) {
            return r.json().then(function (j) { if (!(r.ok && j.success)) throw new Error(j.error || ('HTTP ' + r.status)); return j; });
          });
        };
        return S.fav ? loadFavs().then(go) : go();
      },
      onTool: function (k) {   // the same filters as the list, kept in step with it
        if (k === 'open') S.open = !S.open; else if (k === 'fav') S.fav = !S.fav;
        else { var i = S.has.indexOf(k); if (i >= 0) S.has.splice(i, 1); else S.has.push(k); }
        changed = true;
        document.querySelectorAll('.tvm-side [data-act]').forEach(function (b) { var v = on(b.getAttribute('data-act')); b.classList.toggle('go', v); b.setAttribute('aria-pressed', String(v)); });
        TvMap.reload();
      },
      onCategory: function (slug, name) { qPend = false; clearTimeout(qT); S.cat = slug; S.catname = name; S.q = ''; changed = true; TvMap.reload(); },
      onList: function () { setView('list'); if (changed) search(true); }
    });
  }
  function setView(v) {
    if (v === 'table' && !wide.matches && window.matchMedia('(max-width: 767px)').matches) v = 'list';   // a phone keeps List | Map
    if (v === 'map') { if (window.tvCount) tvCount({ m: 'act', k: 'gl-view:map' }); abCount('map'); openMap(); return; }
    var was = view; view = v; closeCm();
    if (v !== was && was && window.tvCount) tvCount({ m: 'act', k: 'gl-view:' + v });
    try { localStorage.setItem('tv_dir_view', v); } catch (x) {}
    document.querySelectorAll('.dr-view [data-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === v)); });
    $('dr-grid').classList.toggle('map', v === 'map');
    var sd = beside(); $('dr-grid').classList.toggle('side', sd);
    document.querySelector('.dr-mapbox').hidden = v !== 'map' && !sd; $('dr-mapfull').hidden = !sd;
    if ((v !== was || sd) && S.items.length) redraw();
    if (v === 'map') window.scrollTo(0, 0);
    document.documentElement.classList.toggle('dr-mapmode', v === 'map');
    fitWork();
    if (v === 'map') pins(S.items);
  }
  wide.addEventListener && wide.addEventListener('change', function () { if (view !== 'map') setView(view); });   // the window crossed the computer width
  $('dr-mapfull').addEventListener('click', function () { openMap(); });
  // where the workspace starts (under the header, beside the toolbar; under the toolbar row on phones)
  function fitWork() {
    var w = document.getElementById('ta-work'), rail = document.getElementById('ta-rail'); if (!w) return;
    var r = w.getBoundingClientRect(), top = r.top;
    if (rail && window.matchMedia('(max-width: 767px)').matches) top = Math.max(top, rail.getBoundingClientRect().bottom);
    document.documentElement.style.setProperty('--dr-t', Math.max(0, Math.round(top)) + 'px');
    document.documentElement.style.setProperty('--dr-l', Math.round(r.left) + 'px');
    if (map) setTimeout(function () { map.invalidateSize(); }, 60);
  }
  window.addEventListener('resize', function () { if (view === 'map') fitWork(); });
  var tog = document.getElementById('ta-tog'); if (tog) tog.addEventListener('click', function () { setTimeout(fitWork, 30); });   // labels on or off: the toolbar's width
  document.querySelector('.dr-view').addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) setView(b.getAttribute('data-view')); });

  // ---- search ----
  function qs(page, forMap) {
    var p = place(), now = new Date(), a = [];
    if (S.q) a.push('q=' + encodeURIComponent(S.q));
    if (S.cat) a.push('category=' + encodeURIComponent(S.cat));
    if (p) a.push(p.qs);
    if (S.open) a.push('open=now');
    a.push('at=' + now.getDay() + ',' + (now.getHours() * 60 + now.getMinutes()));
    if (S.has.length) a.push('has=' + S.has.join(','));
    if (S.town) a.push('in_town=' + encodeURIComponent(S.town));
    if (!AR.closed) a.push('closed=hide');   // Arrange → Show (this person only)
    if (!forMap && page === (S.first || 1)) a.push('facets=1');   // the finder's Kind and Town lists, with their counts
    if (S.fav && favIds) a.push('ids=' + favIds.join(','));
    // a point (a town, near me, a ZIP): nearest first unless asked otherwise
    a.push('sort=' + (nearOn() && S.sort === 'best' && !U.get('sort') ? 'distance' : (S.sort === 'distance' && !(p && p.point) ? 'best' : S.sort)));   // near me: nearest first (CLAUDE.md); else Best match, the most certain first (Jason, 2026-11-19)
    if (!forMap) a.push('page=' + page, 'per_page=' + pg.per);
    return a.join('&');
  }
  function syncUrl() {
    var u = new URLSearchParams();
    if (S.q) u.set('q', S.q);
    if (S.cat) { u.set('cat', S.cat); if (S.catname) u.set('catname', S.catname); }
    if (urlPlace && urlPlace.key) { u.set(urlPlace.key, urlPlace.slug); if (localName) u.set('local', localName); }
    else if (urlPlace && urlPlace.town) { u.set('town', urlPlace.town); if (urlPlace.state) u.set('state', urlPlace.state); u.set('radius', S.radius); }
    else if (urlPlace && !urlPlace.town) { u.set('lat', urlPlace.lat); u.set('lon', urlPlace.lon); u.set('radius', S.radius); u.set('near', urlPlace.near); }
    if (S.open) u.set('open', 'now');
    if (S.has.length) u.set('has', S.has.join(','));
    if (S.town) u.set('in_town', S.town);
    if (S.fav) u.set('fav', '1');
    if (S.sort !== 'best') u.set('sort', S.sort);
    history.replaceState(null, '', '/directory/' + (u.toString() ? '?' + u.toString() : ''));
  }
  // closing the search dropdown steps back one history entry; keep the address matching what's shown
  window.addEventListener('popstate', function () { setTimeout(syncUrl, 0); });
  var seq = 0;
  function search(reset) {
    if (reset) { S.page = 1; S.first = 1; S.items = []; }
    if (qPend) { var tw = $('dr-q').value.trim(); if (tw.length >= 3 || tw === '') { S.q = tw; qPend = false; clearTimeout(qT); var hw = document.querySelector('.tvs-in'); if (hw) hw.value = S.q; } }   // typed, not yet searched: these words
    paintChips(); syncUrl();
    var p = place(), my = ++seq;
    var what = S.cat ? (S.catname || S.cat.replace(/-/g, ' ')) : S.q ? '“' + S.q + '”' : 'Businesses';
    $('dr-title').textContent = what + (p ? (S.cat || S.q ? (p.point ? ' near ' : ' in ') : ' near ') + short(p.label) : (S.cat || S.q ? '' : ' near you'));
    if (!p && !S.q && !S.cat) {
      $('dr-sum').textContent = '';
      // no place and nothing asked: an action, not a sentence (Jason, 2026-11-12: "something to lead action to setting location");
      // nothing left over from the search before (its pages, its "near …")
      // the pop-up asks; under it only a line that brings it back (Jason, 2026-11-12: "we dont need the underneath set location info box")
      $('dr-list').innerHTML = '<p class="dr-sum">No place set yet. <button type="button" class="dr-lk" data-ask="pop">Choose a place</button> and the businesses there appear here, nearest first.</p>';
      $('dr-sum').textContent = ''; pg.draw({ total: 0 }); $('dr-tellbox').innerHTML = '';
      askPop();   // and asked in a pop-up, as the map does (Jason, 2026-11-12: "maybe the directory should have the same popup")
      pins([]); return;
    }
    if (reset) $('dr-list').innerHTML = SKEL;
    fetch('/api/search_listings.php?' + qs(S.page), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
      if (my !== seq) return;
      if (!d.success) throw new Error(d.error || 'Search is unavailable right now.');
      S.total = d.total || 0; S.hoursKnown = d.hours_known || null;   // asked open: N of M have known hours (P10)
      S.items = S.items.concat(d.listings || []);
      if (d.facets) facets = d.facets;
      if (S.page === (S.first || 1)) { S.offers = d.offers || []; S.meant = d.meant || []; S.read = d.read || []; S.center = d.center || null; }   // the main search's reading: kinds meant, spelling offers
      pg.busy(false); if (view === 'table') paintTable(); else paintList(); paintChips(); paintSentence(); paintSug();   // sort shows once there's more than one
      if (view === 'map' || beside()) pins(S.items);
    }).catch(function (e) { $('dr-list').innerHTML = '<div class="dr-empty">' + esc(e.message) + '</div>'; });
  }
  document.addEventListener('click', function (e) {   // the cards' ⋯: one open at a time; ×, Esc or a click outside close it
    var b = e.target.closest('.dr-mb'), x = e.target.closest('.dr-mx');
    document.querySelectorAll('.dr-ml:not([hidden])').forEach(function (m) { if (!b || m.previousSibling !== b) { m.hidden = true; m.previousSibling.setAttribute('aria-expanded', 'false'); } });
    if (x) return;
    if (b) { var m = b.nextSibling; m.hidden = !m.hidden; b.setAttribute('aria-expanded', String(!m.hidden));
      if (!m.hidden) { m.classList.remove('up'); var r = m.getBoundingClientRect(); if (r.bottom > innerHeight - 8 && b.getBoundingClientRect().top - r.height > 8) m.classList.add('up'); } }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.querySelectorAll('.dr-ml:not([hidden])').forEach(function (m) { m.hidden = true; m.previousSibling.setAttribute('aria-expanded', 'false'); m.previousSibling.focus(); }); });
  // a street to drive to: not a PO Box (P.O. Box, PO BOX, POB, Box 12, PMB) and not a place kept private. One rule with
  // app/lib/Address.php's isStreet(), so the card, the map's card and the listing's page agree (2026-11-19)
  var MAILBOX = /^\s*(p\.?\s*o\.?\s*box|post\s+office\s+box|pob\b|box\s+\d|pmb\b|private\s+mail\s*box)/i;
  function hasStreet(l) { return !!(l && l.address && !l.private_location && !MAILBOX.test(String(l.address))); }
  var I = {
    call: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
    dir: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
    web: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/></svg>',
    add: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    peek: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
  };
  // the lists' standard (Jason, 2026-11-12; js/tv-pager.js): 10 a page or the visitor's choice, Show more, and the pages
  var pg = TvPager.mount($('dr-pg'), { key: 'directory', top: $('dr-listbox'),
    onMore: function () { pg.busy(true); S.page++; search(false); },
    onPage: function (x) { S.first = S.page = x + 1; S.items = []; $('dr-list').innerHTML = SKEL; search(false); },
    onPer: function () { search(true); } });
  function paintList() {
    var p = place();
    pg.draw({ total: S.items.length ? S.total : 0, page: (S.first || 1) - 1, shown: S.items.length });
    $('dr-tellbox').innerHTML = '';
    $('dr-sum').textContent = S.total ? [localName && urlPlace && urlPlace.key ? localName + ' is part of this area' : '',
      S.q && S.meant && S.meant.length ? 'Includes ' + S.meant.slice(0, 3).join(', ') : '',
      S.open && S.hoursKnown && S.hoursKnown.of ? S.hoursKnown.known + ' of ' + S.hoursKnown.of + ' have known hours' + (S.hoursKnown.known < S.hoursKnown.of ? '; the rest say Hours not known.' : '.') : ''].filter(Boolean).join(' · ') : '';   // P10, 2026-11-27: the same sentence as Quick Search's   // the page bar says which ones show (2026-11-19)
    // spelling: offered, never changed (decisions/0064, as the site's search)
    var offerHtml = S.q && S.offers && S.offers.length ? '<p class="dr-sum dr-dym">Did you mean ' + S.offers.map(function (o) { return '<button type="button" class="dr-tg" data-dym="' + esc(o) + '">' + esc(o) + '</button>'; }).join(' ') + '?</p>' : '';
    if (!S.items.length && S.fav) {
      $('dr-list').innerHTML = '<div class="dr-empty">' + (favIds && !favIds.signedIn ? '<a class="dr-lk" href="/login.php">Sign in</a> to see your favorites: the businesses you link or save to your Address Book.'
        : 'None of your favorites match here yet. Tap the link or ☆ Save on a business to add it.') + ' <button type="button" class="dr-lk" data-f-off>Show everything</button></div>';
      return;
    }
    if (!S.items.length) {
      $('dr-list').innerHTML = offerHtml + '<div class="dr-empty">Nothing found' + (p ? (p.point ? ' near ' : ' in ') + esc(short(p.label)) : '') + '. '
        + (S.open || S.has.length ? '<button type="button" class="dr-lk" data-clear>Clear the filters</button> · ' : '')
        + (p ? '<button type="button" class="dr-lk" data-everywhere>Search everywhere</button>' : 'Try other words or a category.')
        + '<br>Know a business that should be here? ' + tellBtn('Tell us') + sendBtn() + '</div>' + readHtml();
      return;
    }
    $('dr-list').innerHTML = offerHtml + S.items.map(function (l, i) {
      var tel = l.phone ? String(l.phone).replace(/[^\d+]/g, '') : '';
      var street = hasStreet(l);   // a mailbox or a private address has no way to drive to it
      var dir = street ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(l.address + ', ' + [l.city, l.state].filter(Boolean).join(', ')) : '';
      var web = l.website ? (/^https?:/i.test(l.website) ? l.website : 'https://' + l.website) : '';
      var ht = l.hours_text || '', cut = ht.indexOf(' · ');
      // data-out: the action is counted for the business (decisions/0058 §9): which listing, which action, which day; never who
      return '<article class="dr-it" data-i="' + i + '" id="dr-it-' + l.id + '" data-link-host><span data-link="business:' + l.id + '" data-link-label="' + esc(l.business_name) + '"></span><div class="dr-main">'
        + '<div class="dr-hd"><span class="dr-num">' + (((S.first || 1) - 1) * pg.per + i + 1) + '.</span> <a class="dr-n" href="/listing/view.php?id=' + l.id + '">' + esc(l.business_name) + '</a>' + (l.is_verified ? '<span class="dr-badge">Verified</span>' : '') + (l.closed ? '<span class="dr-badge dr-shut">Closed</span>' : '') + '</div>'
        + '<div class="dr-acts">'
        + (tel ? '<a class="dr-a" title="Call" href="tel:' + esc(tel) + '" data-out="call" data-e="' + l.id + '"><span>' + I.call + '</span><b class="dr-al">Call</b></a>' : '')
        + (dir ? '<a class="dr-a" title="Directions" href="' + esc(dir) + '" target="_blank" rel="noopener" data-out="directions" data-e="' + l.id + '"><span>' + I.dir + '</span><b class="dr-al">Directions</b></a>' : '')
        // the rest, in words, under ⋯ (Jason, 2026-11-19, Change 2): Email (its public email, decisions/0172 §3.3), Website, Peek
        + '<span class="dr-mw"><button type="button" class="dr-a dr-mb" aria-haspopup="menu" aria-expanded="false" title="More: email, website, peek" aria-label="More for ' + esc(l.business_name) + '">\u22ef</button>'
        + '<span class="dr-ml" role="menu" hidden><button type="button" class="dr-mx" aria-label="Close" title="Close">&times;</button>'
        + (l.email ? '<a role="menuitem" href="mailto:' + esc(l.email) + '" data-out="email" data-e="' + l.id + '">' + I.mail + 'Email</a>' : '<span class="dr-mo">' + I.mail + 'Email<small>No public email yet</small></span>')
        + (web ? '<a role="menuitem" href="' + esc(web) + '" target="_blank" rel="noopener nofollow" data-out="website" data-e="' + l.id + '">' + I.web + 'Website</a>' : '<span class="dr-mo">' + I.web + 'Website<small>No website yet</small></span>')
        + '<button type="button" role="menuitem" data-tv-peek="' + l.id + '">' + I.peek + 'Peek</button>'
        // Add to a trip (decisions/0174 §2): a place kept private is greyed with why; the server checks the rest
        + (l.private_location ? '<span class="dr-mo">' + I.add + 'Add to a trip<small>Its location is kept private: call them</small></span>'
          : '<button type="button" role="menuitem" data-trip-add="' + esc(JSON.stringify({kind: 'listing', ref: String(l.id), name: l.business_name})) + '" aria-haspopup="dialog">' + I.add + 'Add to a trip</button>')
        + '</span></span>'
        + '</div>'
        // the status shares the category line ("Restaurants · Open 24 hours"; "Closed · Opens 10 AM" fits the same way)
        + details(l, p, street)   // the details in the person's order (Arrange, 2026-11-19); by default: kind · distance · hours, then address · phone
        + '</div></article>';
    }).join('');
    $('dr-tellbox').innerHTML = readHtml() + '<p class="dr-tell">Missing a business' + (p ? (p.point ? ' near ' : ' in ') + esc(short(p.label)) : '') + '? ' + tellBtn('Tell us') + sendBtn() + '</p>';
  }
  // places named on pages we read that we don't list yet (decisions/0157 §3): after ours, when ours are few. The name opens
  // the page it's named on; Call and Directions as on any card. Each is checked against another source before it's listed.
  function readHtml() {
    var R = S.read || [];
    if (!R.length) return '';
    return '<h3 class="dr-rdh">Named on pages we read <small>not listed yet</small></h3>' + R.map(function (l) {
      var tel = l.tel ? String(l.tel).replace(/[^\d+]/g, '') : '';
      var dir = hasStreet(l) ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(l.address + (l.town && String(l.address).indexOf(l.town.split(',')[0]) < 0 ? ', ' + l.town : '')) : '';
      var web = l.website ? (/^https?:/i.test(l.website) ? l.website : 'https://' + l.website) : '';
      return '<article class="dr-it dr-rd"><div class="dr-main"><div class="dr-hd"><a class="dr-n" href="' + esc(l.source) + '" target="_blank" rel="noopener nofollow" title="The page it\u2019s named on: ' + esc(l.source_host) + '">' + esc(l.label) + '</a></div>'
        + '<div class="dr-acts">'
        + (tel ? '<a class="dr-a" title="Call" href="tel:' + esc(tel) + '"><span>' + I.call + '</span><b class="dr-al">Call</b></a>' : '')
        + (dir ? '<a class="dr-a" title="Directions" href="' + esc(dir) + '" target="_blank" rel="noopener"><span>' + I.dir + '</span><b class="dr-al">Directions</b></a>' : '')
        + (web ? '<a class="dr-a" title="Website" href="' + esc(web) + '" target="_blank" rel="noopener nofollow"><span>' + I.web + '</span><b class="dr-al">Website</b></a>' : '')
        + '</div>'
        + '<div class="dr-m">' + esc([l.kind, l.miles != null ? l.miles + ' mi' : ''].filter(Boolean).join(' · ')) + '</div>'
        + '<div class="dr-m">' + esc(l.address || l.town) + (l.phone ? ' · ' + esc(l.phone) : '') + '</div>'
        + '<div class="dr-m dr-src">' + esc(l.from) + '</div>'
        + '</div></article>';
    }).join('') + '<p class="dr-sum">Not Traversence listings yet: each is checked against another source before it\u2019s listed.</p>';
  }
  // Jason only (decisions/0157 §3): the crawler reads the town's sources again, asks OpenStreetMap again, and lists the
  // sources waiting for his call there. Free: no web search.
  function sendBtn() {
    return window.tvSendCrawler ? ' · <button type="button" class="dr-lk" data-send-crawler>Send the crawler</button>' : '';
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-send-crawler]'); if (!b || !window.tvSendCrawler) return;
    var p = place();
    if (!p || p.region) { TvAsk.say('Choose a town (or use your location) first: the crawler works a town at a time.', { anchor: b, bad: true }); return; }
    TvAsk.busy(b, true);
    var body = { qs: p.qs, q: S.q || S.catname || '' };
    fetch('/api/send-crawler.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.tvSendCrawler.csrf },
      body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok || !d.ok) throw new Error((d.error && d.error.message) || d.error || 'The crawler couldn\u2019t be sent.'); return d; }); })
      .then(function (d) { TvAsk.busy(b, false); TvAsk.say(d.message, { anchor: b }); })
      .catch(function (x) { TvAsk.busy(b, false); TvAsk.say(x.message, { anchor: b, bad: true }); });
  });
  // "Tell us" (js/suggest.js): a missing business, with what they searched for
  function tellBtn(label) {
    var p = place();
    return '<button type="button" class="dr-lk" data-tv-suggest="' + esc(JSON.stringify({ kind: 'missing', topic: 'business', q: S.q || '', place_name: p ? short(p.label) : '' })) + '">' + esc(label) + '</button>';
  }
  // the place: its name opens the pop-up, the target finds where you are at once (Jason, 2026-11-12: "the location should just
  // have the autodetect or activate when clicked on")
  $('dr-where').addEventListener('click', function () { askPop(); });
  $('dr-where-tgt').addEventListener('click', function (e) { if (typeof autoDetectLocation === 'function') autoDetectLocation(e); });
  $('dr-where-x').addEventListener('click', function () { urlPlace = null; localName = ''; if (typeof chooseAnywhere === 'function') chooseAnywhere(); else search(true); });   // clear the place: everywhere, like the search's ×
  // a click anywhere on a card that isn't one of its links or buttons opens its pop-up card, as Peek does (Jason, 2026-11-12:
  // "can clicking the box of the listing fire the popup card?"); selecting text doesn't
  // the pop-up: the page greyed behind it; gone when a place is chosen or it's closed
  // every time the list opens with no place (Jason, 2026-11-12: "once popup is closed it doesnt seem to be coming back"), not
  // once a visit; closed, the line under the title brings it back
  // the site's one place pop-up (js/location-scope.js tvPlacePop, Jason's "we need a location to search"); Not sure?: search everywhere
  // Not sure? goes to browsing by region (the pop-up's own default), never a notice (2026-11-12: it showed as an "Error")
  function askPop() { if (typeof window.tvPlacePop === 'function') window.tvPlacePop(); }
  function doAsk(k) {
    var box = $('dr-where');
    if (k === 'me' || k === 'type') askPop();
    else $('dr-q').focus();
  }
  $('dr-list').addEventListener('click', function (e) {
    var dy = e.target.closest('[data-dym]');
    if (dy) { qPend = false; clearTimeout(qT); S.q = dy.getAttribute('data-dym'); S.cat = ''; S.catname = ''; S.town = ''; var hi = document.querySelector('.tvs-in'); if (hi) hi.value = S.q; search(true); return; }
    var a = e.target.closest('[data-ask]'); if (!a) return;
    if (a.getAttribute('data-ask') === 'pop') askPop(); else doAsk(a.getAttribute('data-ask'));
  });
  $('dr-list').addEventListener('click', function (e) {
    var card = e.target.closest('.dr-it'); if (!card || e.target.closest('a,button,input,select,label,[data-link],.dr-ml')) return;
    var sel = window.getSelection && String(window.getSelection()); if (sel && sel.trim()) return;
    var pk = card.querySelector('[data-tv-peek]'); if (pk) pk.click();
  });
  $('dr-list').addEventListener('click', function (e) {
    if (e.target.closest('[data-clear]')) { S.open = false; S.has = []; search(true); return; }
    if (e.target.closest('[data-f-off]')) { S.fav = false; search(true); return; }
    if (e.target.closest('[data-everywhere]')) { urlPlace = null; if (typeof chooseAnywhere === 'function') chooseAnywhere(); else search(true); }
  });
  $('dr-list').addEventListener('mouseover', function (e) { var a = e.target.closest('.dr-it[data-i]'); if (a) hi(+a.getAttribute('data-i'), false); });


  // ---- Arrange (Jason, 2026-11-19, "Build as shown"): per page, Show, the details on each card in order, the computer's
  // layout. Kept on this device only (localStorage), never sent with who. ----
  var DET = { kind: 'Kind', dist: 'Distance', hours: 'Hours and status', addr: 'Address', phone: 'Phone number', web: 'Website', email: 'Email' };
  var DET_DEF = ['kind', 'dist', 'hours', 'addr', 'phone'];
  var AR = { det: DET_DEF.slice(), closed: true };
  try { var ar0 = JSON.parse(localStorage.getItem('tv_dir_arrange') || 'null'); if (ar0) { if (Array.isArray(ar0.det)) AR.det = ar0.det.filter(function (k) { return DET[k]; }); if (ar0.closed === false) AR.closed = false; } } catch (x) {}
  function saveAR() { try { localStorage.setItem('tv_dir_arrange', JSON.stringify(AR)); } catch (x) {} }
  function paintArrange() {
    var el = $('dr-adl'); if (!el) return;
    var off = Object.keys(DET).filter(function (k) { return AR.det.indexOf(k) < 0; });
    el.innerHTML = '<button type="button" class="dr-fx" data-arr-x aria-label="Close" title="Close">&times;</button>'
      + '<h4>Results per page</h4><div class="dr-pills">' + [10, 25, 50, 100].map(function (n) { return '<button type="button" data-per="' + n + '" aria-pressed="' + (pg.per === n) + '">' + n + '</button>'; }).join('') + '</div>'
      + '<h4>Show</h4><label class="dr-sw"><input type="checkbox" data-ar-closed' + (AR.closed ? ' checked' : '') + '><span>Businesses closed for good<small>Shown last and marked Closed. Untick to hide them for you.</small></span></label>'
      + '<h4>On each card</h4><p class="dr-adn">The first three share the top line. Drag to reorder, or use ↑ ↓.</p><ol class="dr-dl" id="dr-dl">'
      + AR.det.map(function (k, i) { return '<li draggable="true" data-det="' + k + '"><i aria-hidden="true">⋮⋮</i><span>' + DET[k] + '</span>'
          + '<button type="button" data-mv="-1" aria-label="Move ' + DET[k] + ' up"' + (i ? '' : ' disabled') + '>↑</button><button type="button" data-mv="1" aria-label="Move ' + DET[k] + ' down"' + (i < AR.det.length - 1 ? '' : ' disabled') + '>↓</button>'
          + '<button type="button" data-rm aria-label="Remove ' + DET[k] + ' from the cards" title="Remove">&times;</button></li>'; }).join('') + '</ol>'
      + (off.length ? '<select class="dr-dadd" data-ar-add aria-label="Add a detail"><option value="">+ Add a detail</option>' + off.map(function (k) { return '<option value="' + k + '">' + DET[k] + '</option>'; }).join('') + '</select>' : '')
      + '<h4>On a computer</h4><div class="dr-pills"><button type="button" data-ab="beside" aria-pressed="' + (AB === 'beside') + '">List beside the map</button><button type="button" data-ab="switch" aria-pressed="' + (AB === 'switch') + '">List, Table or Map</button></div>'
      + (wide.matches ? '' : '<p class="dr-adn">A phone always shows List or Map.</p>')
      + '<button type="button" class="dr-ard" data-ar-reset>Put it all back as it was</button>';
  }
  (function () {
    var d = $('dr-arr'); if (!d) return;
    d.addEventListener('toggle', function () { if (d.open) { closePop(); var f = $('dr-fdd'); if (f) f.removeAttribute('open'); paintArrange(); } });
    document.addEventListener('click', function (e) { if (d.open && e.target.isConnected && !d.contains(e.target)) d.removeAttribute('open'); });   // a button the panel just redrew isn't "outside"
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && d.open) { d.removeAttribute('open'); d.querySelector('summary').focus(); } });
    var el = $('dr-adl'), dragK = '';
    el.addEventListener('click', function (e) {
      var t = e.target;
      if (t.closest('[data-arr-x]')) { d.removeAttribute('open'); return; }
      var per = t.closest('[data-per]');
      if (per) { pg.per = +per.getAttribute('data-per'); try { localStorage.setItem('tvp:directory', String(pg.per)); } catch (x) {} paintArrange(); search(true); return; }
      var ab = t.closest('[data-ab]');
      if (ab) { AB = ab.getAttribute('data-ab'); try { localStorage.setItem('tv_dir_ab', AB); } catch (x) {} abCount('pick'); paintArrange(); setView(view === 'map' && AB === 'beside' ? 'list' : view); return; }
      var li = t.closest('li[data-det]'), k = li && li.getAttribute('data-det'), i = AR.det.indexOf(k);
      if (li && t.closest('[data-mv]')) { var j = i + +t.closest('[data-mv]').getAttribute('data-mv'); if (j >= 0 && j < AR.det.length) { AR.det.splice(i, 1); AR.det.splice(j, 0, k); saveAR(); paintArrange(); redraw(); var b = el.querySelector('li[data-det="' + k + '"] [data-mv="' + (j < i ? -1 : 1) + '"]'); if (b && !b.disabled) b.focus(); } return; }
      if (li && t.closest('[data-rm]')) { AR.det.splice(i, 1); saveAR(); paintArrange(); redraw(); return; }
      if (t.closest('[data-ar-reset]')) { AR = { det: DET_DEF.slice(), closed: true }; saveAR(); pg.per = 10; try { localStorage.removeItem('tvp:directory'); } catch (x) {} paintArrange(); search(true); }
    });
    el.addEventListener('change', function (e) {
      if (e.target.hasAttribute('data-ar-closed')) { AR.closed = e.target.checked; saveAR(); search(true); }
      if (e.target.hasAttribute('data-ar-add') && e.target.value) { AR.det.push(e.target.value); saveAR(); paintArrange(); redraw(); }
    });
    el.addEventListener('dragstart', function (e) { var li = e.target.closest('li[data-det]'); if (!li) return; dragK = li.getAttribute('data-det'); li.classList.add('drag'); try { e.dataTransfer.setData('text/plain', dragK); e.dataTransfer.effectAllowed = 'move'; } catch (x) {} });
    el.addEventListener('dragend', function () { dragK = ''; el.querySelectorAll('li.drag').forEach(function (x) { x.classList.remove('drag'); }); });
    el.addEventListener('dragover', function (e) { if (dragK && e.target.closest('li[data-det]')) e.preventDefault(); });
    el.addEventListener('drop', function (e) {
      var li = e.target.closest('li[data-det]'); if (!li || !dragK) return; e.preventDefault();
      var to = AR.det.indexOf(li.getAttribute('data-det')), from = AR.det.indexOf(dragK); if (to < 0 || from < 0 || to === from) return;
      AR.det.splice(from, 1); AR.det.splice(to, 0, dragK); dragK = ''; saveAR(); paintArrange(); redraw();
    });
  })();
  function redraw() { if (view === 'table') paintTable(); else paintList(); if (beside()) pins(S.items); }
  // a card's details, in the person's order: the first three on the top line, the rest under it
  function details(l, p, street) {
    var ht = l.hours_text || '', cut = ht.indexOf(' · '), web = l.website ? String(l.website).replace(/^https?:\/\//i, '').replace(/\/$/, '') : '';
    var f = {
      kind: l.category ? esc(l.category) : '',
      dist: l.distance != null && p && p.point ? esc(l.distance + ' mi') : '',
      hours: ht ? '<span class="' + (l.open_now ? 'dr-open' : 'dr-closed') + '">' + esc(cut > 0 ? ht.slice(0, cut) : ht) + '</span>' + (cut > 0 ? ' · ' + esc(ht.slice(cut + 3)) : '') : (l.closed ? '' : '<span class="dr-unk">Hours not known</span>'),   // only the owner's, our staff's or OpenStreetMap's hours say open or closed (P10)
      addr: (l.address ? esc(l.address) + (l.city && String(l.address).indexOf(l.city) < 0 ? ', ' + esc(l.city) : '')
          : (l.private_location ? 'Location kept private for the safety of the people it shelters. Call to get help.' : esc([l.city, l.state].filter(Boolean).join(', '))))
        + (l.address && !street && !l.private_location ? ' <span class="dr-mail">Mailing address only</span>' : ''),
      phone: l.phone ? esc(l.phone) : '',
      web: web ? esc(web) : '',
      email: l.email ? esc(l.email) : ''
    };
    var a = AR.det.map(function (k) { return f[k]; }).filter(Boolean);
    return (a.length ? '<div class="dr-m">' + a.slice(0, 3).join(' · ') + '</div>' : '') + (a.length > 3 ? '<div class="dr-m">' + a.slice(3).join(' · ') + '</div>' : '');
  }

  // ---- the Table view: the same results as rows; each column's menu sorts and filters the rows shown ----
  var TC = [['name', 'Name'], ['kind', 'Kind'], ['dist', 'Distance'], ['status', 'Status'], ['town', 'Town'], ['phone', 'Phone']];
  var TF = {}, TS = null;   // filters: column → { words, off: {value: 1} }; sort: [column, 1|-1]
  function cell(l, c) {
    if (c === 'name') return l.business_name || '';
    if (c === 'kind') return l.category || '';
    if (c === 'dist') return l.distance != null ? +l.distance : '';
    if (c === 'status') return l.closed ? 'Closed for good' : (l.hours_text ? (l.open_now ? 'Open' : 'Closed now') : 'Hours not known');
    if (c === 'town') return [l.city, l.state].filter(Boolean).join(', ');
    if (c === 'phone') return l.phone || '';
    return '';
  }
  function tRows() {
    var rows = S.items.map(function (l, i) { return { l: l, i: i }; }).filter(function (r) {
      return Object.keys(TF).every(function (c) { var f = TF[c], v = String(cell(r.l, c)); return !(f.off && f.off[v]) && !(f.words && v.toLowerCase().indexOf(f.words.toLowerCase()) < 0); });
    });
    if (TS) rows.sort(function (a, b) { var x = cell(a.l, TS[0]), y = cell(b.l, TS[0]); if (x === '' && y !== '') return 1; if (y === '' && x !== '') return -1; return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y))) * TS[1]; });
    return rows;
  }
  function paintTable() {
    var p = place(), cols = TC.filter(function (c) { return c[0] !== 'dist' || (p && p.point); });
    pg.draw({ total: S.items.length ? S.total : 0, page: (S.first || 1) - 1, shown: S.items.length });
    if (!S.items.length) { paintList(); return; }
    var rows = tRows(), nf = Object.keys(TF).length;
    $('dr-list').innerHTML = '<div class="dr-tw"><table class="dr-tb"><thead><tr><th scope="col">#</th>' + cols.map(function (c) {
        return '<th scope="col"' + (TF[c[0]] ? ' class="f"' : '') + ' aria-sort="' + (TS && TS[0] === c[0] ? (TS[1] > 0 ? 'ascending' : 'descending') : 'none') + '"><button type="button" data-col="' + c[0] + '" aria-haspopup="dialog">' + c[1]
          + (TS && TS[0] === c[0] ? (TS[1] > 0 ? ' ↑' : ' ↓') : '') + (TF[c[0]] ? ' <b title="Filtered">(filtered)</b>' : '') + ' <b aria-hidden="true">▾</b></button></th>'; }).join('') + '</tr></thead><tbody>'
      + rows.map(function (r) { var l = r.l, tel = l.phone ? String(l.phone).replace(/[^\d+]/g, '') : '';
          return '<tr data-i="' + r.i + '"><td>' + (((S.first || 1) - 1) * pg.per + r.i + 1) + '</td>' + cols.map(function (c) {
            var v = cell(l, c[0]);
            if (c[0] === 'name') return '<td><a href="/listing/view.php?id=' + l.id + '">' + esc(v) + '</a>' + (l.is_verified ? '<span class="dr-badge">Verified</span>' : '') + '</td>';
            if (c[0] === 'phone') return '<td>' + (tel ? '<a href="tel:' + esc(tel) + '" data-out="call" data-e="' + l.id + '">' + esc(v) + '</a>' : '') + '</td>';
            if (c[0] === 'status') return '<td class="' + (v === 'Open' ? 'dr-open' : v === 'Closed now' ? 'dr-closed' : '') + '">' + esc(v) + '</td>';
            if (c[0] === 'dist') return '<td>' + (v === '' ? '' : esc(v + ' mi')) + '</td>';
            return '<td>' + esc(v) + '</td>'; }).join('') + '</tr>'; }).join('')
      + (rows.length ? '' : '<tr><td colspan="' + (cols.length + 1) + '">None of these ' + S.items.length + ' match the column filters. <button type="button" class="dr-lk" data-tf-clear>Clear them</button></td></tr>')
      + '</tbody></table></div><p class="dr-tnote">The column menus sort and filter the ' + S.items.length.toLocaleString() + ' shown here' + (S.total > S.items.length ? ' (of ' + S.total.toLocaleString() + '; Show more adds the next)' : '') + '.'
      + (nf ? ' <button type="button" class="dr-lk" data-tf-clear>Clear the column filters</button>' : '') + '</p>';
  }
  function closeCm() { var m = document.querySelector('.dr-cm'); if (m) { var b = document.querySelector('[data-col][aria-expanded="true"]'); m.remove(); if (b) { b.setAttribute('aria-expanded', 'false'); return b; } } return null; }
  function openCm(b) {
    var c = b.getAttribute('data-col'), was = b.getAttribute('aria-expanded') === 'true'; closeCm(); if (was) return;
    var n = {}; S.items.forEach(function (l) { var v = String(cell(l, c)); n[v] = (n[v] || 0) + 1; });
    var vals = Object.keys(n).sort(function (a, z) { return a === '' ? 1 : z === '' ? -1 : (c === 'dist' ? +a - +z : a.localeCompare(z)); }), f = TF[c] || {};
    var m = document.createElement('div'); m.className = 'dr-cm'; m.setAttribute('role', 'dialog'); m.setAttribute('aria-label', 'Sort and filter ' + b.textContent.replace(/[▾↑↓]|\(filtered\)/g, '').trim());
    m.innerHTML = '<button type="button" class="dr-cmx" data-cm="x" aria-label="Close" title="Close">&times;</button>'
      + '<button type="button" data-cm="up">' + (c === 'dist' ? 'Nearest first' : 'Sort A to Z') + '</button><button type="button" data-cm="down">' + (c === 'dist' ? 'Farthest first' : 'Sort Z to A') + '</button>'
      + (TF[c] ? '<button type="button" data-cm="clear">Clear this filter</button>' : '') + '<hr>'
      + '<input type="search" data-cm="words" placeholder="Search this column" aria-label="Search this column" value="' + esc(f.words || '') + '">'
      + '<div class="dr-cml">' + vals.slice(0, 60).map(function (v) { return '<label><input type="checkbox" data-cv="' + esc(v) + '"' + (f.off && f.off[v] ? '' : ' checked') + '> Show ' + esc(v === '' ? '(blank)' : (c === 'dist' ? v + ' mi' : v)) + ' <small>(' + n[v] + ')</small></label>'; }).join('') + '</div>';
    document.body.appendChild(m); b.setAttribute('aria-expanded', 'true');
    var r = b.getBoundingClientRect();
    m.style.top = (r.bottom + window.scrollY + 4) + 'px'; m.style.left = Math.max(3 + window.scrollX, Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - m.offsetWidth - 3)) + 'px';
    m.addEventListener('click', function (e) {
      var a = e.target.closest('[data-cm]'), k = a && a.getAttribute('data-cm');
      if (k === 'x') { var bb = closeCm(); if (bb) bb.focus(); }
      else if (k === 'up' || k === 'down') { TS = [c, k === 'up' ? 1 : -1]; closeCm(); paintTable(); }
      else if (k === 'clear') { delete TF[c]; closeCm(); paintTable(); }
    });
    m.addEventListener('change', function (e) {
      var cv = e.target.getAttribute('data-cv'); if (cv == null) return;
      var g = TF[c] || (TF[c] = {}); g.off = g.off || {}; if (e.target.checked) delete g.off[cv]; else g.off[cv] = 1;
      if (!Object.keys(g.off).length && !g.words) delete TF[c]; keepCm(c);
    });
    var tw; m.addEventListener('input', function (e) { if (e.target.getAttribute('data-cm') !== 'words') return; var v = e.target.value.trim(); clearTimeout(tw); tw = setTimeout(function () { var g = TF[c] || (TF[c] = {}); g.words = v; if (!v && !(g.off && Object.keys(g.off).length)) delete TF[c]; keepCm(c); }, 250); });
    var ws = m.querySelector('[data-cm="words"]'); if (ws) ws.focus();
  }
  function keepCm(c) {   // repaint the rows, keeping the menu open where it was
    var m = document.querySelector('.dr-cm'), top = m && m.style.top, left = m && m.style.left, inp = m && m.querySelector('[data-cm="words"]'), focusW = inp && document.activeElement === inp;
    if (m) m.remove(); paintTable();
    var b = document.querySelector('[data-col="' + c + '"]'); if (!b) return; openCm(b);
    var m2 = document.querySelector('.dr-cm'); if (m2 && top) { m2.style.top = top; m2.style.left = left; } if (m2 && !focusW) { var w = m2.querySelector('[data-cm="words"]'); if (w) w.blur(); }
  }
  $('dr-list').addEventListener('click', function (e) {
    var b = e.target.closest('[data-col]'); if (b) { openCm(b); return; }
    if (e.target.closest('[data-tf-clear]')) { TF = {}; closeCm(); paintTable(); }
  });
  document.addEventListener('click', function (e) { if (e.target.isConnected && !e.target.closest('.dr-cm,[data-col]')) closeCm(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var b = closeCm(); if (b) b.focus(); } });

  // ---- the map beside the results ----
  var map = null, layer = null, marks = [], loading = null, ring = null, quiet = false;
  function leaflet() {
    if (window.L) return Promise.resolve();
    if (loading) return loading;
    var cdn = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/';
    loading = new Promise(function (res, rej) {
      var c = document.createElement('link'); c.rel = 'stylesheet'; c.href = cdn + 'leaflet.min.css'; document.head.appendChild(c);
      var s = document.createElement('script'); s.src = cdn + 'leaflet.min.js'; s.onload = res; s.onerror = function () { rej(new Error('The map could not load.')); }; document.head.appendChild(s);
    });
    return loading;
  }
  function pins(list) {
    leaflet().then(function () {
      if (!map) {
        map = L.map('dr-map', { zoomControl: false }).setView([34.2, -110.0], 8);
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors', referrerPolicy: 'strict-origin-when-cross-origin' }).addTo(map);
        layer = L.layerGroup().addTo(map);
      }
      layer.clearLayers(); marks = [];
      if (ring) { map.removeLayer(ring); ring = null; }
      var pc = place(), cen = S.center;   // the distance ring: how far the sentence says, round the place searched
      if (pc && pc.point && cen && cen.lat != null && S.radius < 250) ring = L.circle([+cen.lat, +cen.lon], { radius: S.radius * 1609.34, color: '#92400E', weight: 2, dashArray: '6 6', fill: true, fillOpacity: .04, interactive: false }).addTo(map);
      var pts = [], at = {};
      list.forEach(function (l, i) {
        if (l.lat == null || l.lon == null) { marks[i] = null; return; }
        // listings without their own point share their ZIP's middle: spread them in a small spiral so each shows
        var k = (+l.lat).toFixed(4) + ',' + (+l.lon).toFixed(4), n = at[k] = (at[k] || 0) + 1, la = +l.lat, lo = +l.lon;
        if (n > 1) { var r = 0.0011 * Math.sqrt(n - 1), g = (n - 1) * 2.39996; la += r * Math.sin(g); lo += r * Math.cos(g) / Math.cos(la * Math.PI / 180); }
        var m = L.marker([la, lo], { title: l.business_name, icon: L.divIcon({ className: 'dr-pin' + (l.approx || (l.address && !hasStreet(l) && !l.private_location) ? ' ap' : ''), html: '<span></span><b>' + (((S.first || 1) - 1) * pg.per + i + 1) + '</b>', iconSize: [26, 26], iconAnchor: [13, 26] }) });
        var tel = l.phone ? String(l.phone).replace(/[^\d+]/g, '') : '';
        m.bindPopup('<div class="dr-pop"><b>' + (((S.first || 1) - 1) * pg.per + i + 1) + '. ' + esc(l.business_name) + '</b><p>' + esc([l.category, l.hours_text ? String(l.hours_text).split(' · ')[0] : ''].filter(Boolean).join(' · ')) + '</p>'
          + '<p>' + esc(l.address || (l.private_location ? 'Location kept private. Call to get help.' : [l.city, l.state].filter(Boolean).join(', '))) + '</p>'
          + (l.address && !hasStreet(l) && !l.private_location ? '<p><i>Mailing address only: the pin shows the town.</i></p>' : l.approx ? '<p><i>Approximate: the middle of its ZIP area.</i> <button type="button" data-tv-suggest="' + esc(JSON.stringify({ kind: 'fix', entity_id: l.id, entity_name: l.business_name })) + '">Know where it is?</button></p>' : '')
          + '<div class="dr-pa">'
          + (tel ? '<a href="tel:' + esc(tel) + '">Call</a>' : '') + '<button type="button" data-tv-peek="' + l.id + '">Peek</button><a href="/listing/view.php?id=' + l.id + '">Open</a></div></div>');
        m.on('click', function () { hi(i, beside()); abCount('pin'); });
        m.addTo(layer); marks[i] = m; pts.push([la, lo]);
      });
      var stacked = Object.keys(at).some(function (k) { return at[k] > 1; });
      var nApprox = list.filter(function (l) { return l.approx; }).length;   // the note only when some pins are the ZIP's middle
      $('dr-mapnote').hidden = !nApprox; $('dr-mapnote').textContent = nApprox === list.length ? 'Pins mark the ZIP area, not the street.' : nApprox + ' pins mark the ZIP area, not the street.';   // shared ZIP points: zoom in so the spread pins separate
      quiet = true; $('dr-sarea').hidden = true;
      var top = beside() ? 40 : 120;
      if (ring && view !== 'map') map.fitBounds(ring.getBounds(), { padding: [30, 30] });
      else if (pts.length) map.fitBounds(pts, { paddingTopLeft: [60, top], paddingBottomRight: [60, 60], maxZoom: stacked ? 17 : 13 });
      setTimeout(function () { quiet = false; }, 700);
      if (!map._drArea) { map._drArea = 1; map.on('moveend', function () { if (!quiet && beside()) $('dr-sarea').hidden = false; }); }
      var mm = $('dr-mapmore'); mm.hidden = !((S.first - 1) * pg.per + S.items.length < S.total); mm.textContent = 'Show ' + Math.min(pg.per, S.total - (S.first - 1) * pg.per - S.items.length) + ' more';
      setTimeout(function () { map.invalidateSize(); }, 60);
    }).catch(function (e) { $('dr-mapnote').textContent = e.message; });
  }
  var hiNow = -1;
  function hi(i, scroll) {
    if (hiNow === i) return;
    document.querySelectorAll('.dr-it.hi').forEach(function (x) { x.classList.remove('hi'); });
    if (marks[hiNow] && marks[hiNow]._icon) marks[hiNow]._icon.classList.remove('hi');
    hiNow = i;
    var card = document.querySelector('.dr-it[data-i="' + i + '"]'); if (card) { card.classList.add('hi'); if (scroll) card.scrollIntoView({ block: 'center' }); }
    if (marks[i] && marks[i]._icon) marks[i]._icon.classList.add('hi');
  }
  $('dr-mapmore').addEventListener('click', function () { S.page++; search(false); });
  // Search this area (the list beside the map): the map's middle and the distance to its corner become the place
  $('dr-sarea').addEventListener('click', function () {
    if (!map) return; var c = map.getCenter(), ne = map.getBounds().getNorthEast();
    var mi = Math.max(1, Math.round(Math.min(c.distanceTo(L.latLng(ne.lat, c.lng)), c.distanceTo(L.latLng(c.lat, ne.lng))) / 1609.34));   // to the nearer edge: what shows on the map
    urlPlace = { lat: +c.lat.toFixed(4), lon: +c.lng.toFixed(4), near: 'this area' }; localName = ''; S.radius = Math.min(250, mi); S.town = '';
    this.hidden = true; abCount('area'); search(true);
  });
  // the test's counts: which side this device is on, and what was done there (never who)
  document.addEventListener('click', function (e) { var t = e.target.closest && e.target.closest('#dr-list a, #dr-list [data-tv-peek], #dr-list [data-out]'); if (t) abCount(t.hasAttribute('data-out') ? 'out' : 'open'); }, true);
  window.addEventListener('load', function () { abCount(''); });

  // start once the page's place is known (location-scope.js restores it on load)
  window.addEventListener('load', function () { setTimeout(function () {
    var v1 = view; view = ''; setView(v1 === 'table' ? 'table' : 'list'); if (v1 === 'map') { view = 'map'; openMap(); }   // the view last used here: open it again
    if (U.get('near') === 'me') nearMe({}); else if (S.fav) loadFavs().then(function () { search(true); }); else search(true);   // Near me from the search's Quick picks on another page
  }, 50); });
})();
</script>
<script src="/js/link-button.js?v=<?= (int) @filemtime(__DIR__ . '/../js/link-button.js') ?>" defer></script>
<script src="/js/tv-trips.js?v=<?= (int) @filemtime(__DIR__ . '/../js/tv-trips.js') ?>" defer></script>
<?php tv_app_end();
