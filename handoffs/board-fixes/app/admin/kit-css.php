<?php
// app/admin/kit-css.php — the admin kit's look (decisions/0109 A7, decisions/0058 §11): the section header and its
// dropdown, lists (a table on a wide screen, cards on a phone), filters like Shortcuts, metrics, drawers, the quick
// task box, the stepper, landing pages, the builder and the permissions matrix.
?>
<style>
  /* OK text is Faded Forest Moss with Warm Near-Black (6.75:1 on the vellum, 5.15:1 on #D8C79D): no brand green passes as text */
  /* the admin on the design tokens (app/ui/theme-tokens.php, Release 1; design/css-theme-brand.md Release 2): no colour
     of its own. Cards are the parchment micro-gradient, the command header Deep Umber like the site's top bar. */
  body:has(.ak-body){color:var(--tv-ink)}
  .ak-svg{width:16px;height:16px;flex:0 0 16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .ak-head{margin:.1rem 0 .6rem}
  /* the command header (directive §1, replacing the sage bar of 2026-11-12): one calm row under the site's header.
     Back, the module, its sections ▾ and the page's own filter (#ak-cmdf) on the left; the board's light, the tools at
     hand and Shortcuts on the right (#ak-cmdr). The one search is the header's Ctrl K box above it. */
  .ak-cmd :focus-visible{outline-color:var(--tv-focus-dark)}
  .ak-cmd{position:sticky;top:var(--ak-top,calc(var(--ta-top,56px) + var(--as-h,0px)));z-index:36;display:flex;flex-wrap:wrap;align-items:center;gap:.5rem .75rem;min-height:3rem;margin:-1.1rem calc(-1 * clamp(1rem,3vw,2.5rem)) var(--tv-gap) !important;padding:.45rem clamp(1rem,3vw,2.5rem) .45rem .6rem;background:var(--tv-umber);color:var(--tv-on-umber);border-bottom:1px solid var(--tv-void);box-shadow:var(--tv-shadow)}
  .ak-cmd .ak-back{width:2.1rem;height:2.1rem;border:0;border-radius:var(--tv-r-sm);background:var(--tv-btn-inv);color:var(--tv-btn-inv-text);display:grid;place-items:center;flex:none}
  .ak-cmd .ak-back .ak-svg{width:18px;height:18px;flex-basis:18px;stroke-width:2.4}.ak-cmd .ak-back:hover{background:var(--tv-btn-inv-hover);color:var(--tv-btn-inv-text)}
  .ak-cmd .ak-mod{margin:0;font:700 1.02rem var(--tv-font-head);letter-spacing:0;text-transform:none;color:var(--tv-on-umber);white-space:nowrap}
  .ak-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .ak-cmd .ak-dd,.ak-cmd .ab{float:none;margin:0;color:var(--tv-on-umber)}
  .ak-cmd .ak-dd>summary,.ak-cmd .ab>summary,.ak-cmd .ak-fb{background:transparent;border:1px solid var(--tv-on-umber-meta);border-radius:var(--tv-r-sm);color:var(--tv-on-umber);box-shadow:none;min-height:2.1rem;box-sizing:border-box;transition:background-color var(--tv-ease),border-color var(--tv-ease)}
  .ak-cmd .ak-dd>summary{padding:.2rem .6rem;min-width:11rem;font:700 .95rem var(--tv-font-head)}
  .ak-cmd .ak-dd>summary::after,.ak-cmd .ab>summary::after{content:"▾";margin-left:auto;color:var(--tv-on-umber-meta);font-size:.85em}
  .ak-cmd .ab>summary{padding:.2rem .6rem;gap:.4rem;font:700 .88rem var(--tv-font-ui)}
  .ak-cmd .ak-dd[open]>summary,.ak-cmd .ab[open]>summary,.ak-cmd .ak-fb[aria-expanded=true]{background:var(--tv-umber-line);border-color:var(--tv-sage);color:var(--tv-on-umber);box-shadow:0 0 0 3px color-mix(in srgb,var(--tv-sage) 35%,transparent)}
  .ak-cmd .ak-item{font:700 .95rem var(--tv-font-head);color:var(--tv-on-umber);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
  .ak-cmdf{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;min-width:0}.ak-cmdf:empty{display:none}
  body:not(.tv-back) .ta-body:has(> .tv-page > header.ak-cmd){margin-top:0}   /* the page body's own gap too, so the bar sits right under the header */
  body.tv-embedded header.ak-cmd{display:none}   /* inside a panel or drawer (a Pulse case, At hand): the page only; the bar belongs to the page around it */
  .ak-cmdr{margin-left:auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.5rem .75rem;color:var(--tv-on-umber-meta)}
  .ak-cmd .ak-hand{margin-left:0;padding-left:.75rem;border-left:1px solid var(--tv-line)}.ak-cmd .ak-hand>span{color:var(--tv-on-umber-meta)}
  .ak-cmd .ak-hand .ak-ib{background:transparent;border-color:var(--tv-on-umber-meta);color:var(--tv-on-umber)}.ak-cmd .ak-hand .ak-ib:hover:not(:disabled){background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber)}
  .ak-cmd .ab{position:relative}
  .ak-mod{display:flex;align-items:center;gap:.35rem;margin:0;font:700 .78rem var(--tv-font-ui);letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta)}
  .ak-top{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem .8rem;margin-top:.15rem}
  .ak-back{display:inline-grid;place-items:center;width:2rem;height:2rem;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-link);font:700 1.35rem/1 var(--tv-font-ui);text-decoration:none;flex:none}.ak-back:hover{background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber)}
  .ak-h1{font:700 clamp(1.45rem,2.6vw,1.85rem)/1.15 var(--tv-font-head);margin:0;color:var(--tv-ink)}
  .ak-h{font:700 1.1rem var(--tv-font-head);color:var(--tv-ink);margin:var(--tv-gap) 0 .5rem}
  .ak-note{font-size:.9rem;color:var(--tv-meta);margin:.4rem 0}
  .ak-note a{color:var(--tv-link);text-decoration:underline;font-weight:600}
  .ak-card{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:var(--tv-r);padding:.75rem .9rem}
  .ak-off{color:var(--tv-meta)}
  .ak-flash{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));border-radius:.6rem;padding:.5rem .8rem;font-weight:600}.ak-flash.bad{background:var(--tv-fail-bg);color:var(--tv-fail)}
  .ak-load{color:var(--tv-meta);padding:.8rem 0}
  /* dropdown buttons: the section menu and the filters (like Shortcuts) */
  .ak-dd{position:relative}
  .ak-dd>summary,.ak-fb{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.35rem .75rem;font:700 .9rem var(--tv-font-ui);white-space:nowrap}
  .ak-dd>summary::-webkit-details-marker{display:none}.ak-dd>summary::after,.ak-fb::after{content:"▾";color:var(--tv-meta);font-size:.8em}
  .ak-dd[open]>summary,.ak-fb[aria-expanded=true]{background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber);box-shadow:0 0 0 3px color-mix(in srgb,var(--tv-sage) 35%,transparent)}
  .ak-fb b{background:var(--tv-card-b);color:var(--tv-meta);border-radius:999px;font-size:.75rem;padding:0 .4rem}
  .ak-ddl,.ak-fl{position:absolute;left:0;top:calc(100% + .35rem);z-index:38;width:min(19rem,calc(100vw - 6px));max-height:70vh;overflow-y:auto;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg);padding:.3rem}
  @media (min-width:768px){.ak-fw:last-child .ak-fl{left:auto;right:0}}   /* the right-most filter opens leftward, so it stays on screen */
  .ak-ddl a,.ak-fl button{display:flex;align-items:center;gap:.45rem;width:100%;padding:.42rem .55rem;border-radius:.5rem;color:var(--tv-ink);font:600 .95rem var(--tv-font-ui);text-decoration:none;background:none;border:0;text-align:left;cursor:pointer}
  .ak-ddl a:hover,.ak-fl button:hover{background:var(--tv-card-a)}
  .ak-ddl a.on,.ak-fl button.on{background:var(--tv-umber);color:var(--tv-card-a)}
  .ak-ddl a.off{color:var(--tv-taupe)}.ak-ddl a.off .ak-svg{opacity:.55}
  .ak-ddl i,.ak-fl i{margin-left:auto;font-style:normal;font-size:.72rem;font-weight:700;border-radius:999px;padding:.02rem .45rem;background:var(--tv-card-b);color:var(--tv-meta)}
  .ak-ddl a.on i,.ak-fl button.on i{background:var(--tv-meta);color:var(--tv-card-a)}
  .ak-fw{position:relative;display:inline-block}
  /* the tools at hand: 30px round icon buttons */
  .ak-hand{display:flex;align-items:center;gap:.3rem;margin-left:auto}
  .ak-hand>span{font-size:.78rem;color:var(--tv-meta);font-weight:700;margin-right:.1rem}
  .ak-ib{width:30px;height:30px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-icon,#B45309);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;flex:none}
  .ak-ib:hover:not(:disabled){background:var(--tv-sage);border-color:var(--tv-sage);color:var(--tv-umber);box-shadow:0 0 0 3px color-mix(in srgb,var(--tv-sage) 35%,transparent)}
  .ak-ib:disabled{opacity:.4;cursor:not-allowed}
  /* metrics */
  .ak-metrics{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(11rem,100%),1fr));gap:.5rem;margin:.2rem 0 var(--tv-gap)}
  .ak-metric{display:flex;flex-direction:column;align-items:flex-start;text-align:left;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:var(--tv-r);padding:.6rem .8rem;color:var(--tv-ink);cursor:pointer;font:inherit}
  .ak-metric:disabled{cursor:default}.ak-metric:not(:disabled):hover{border-color:var(--tv-sage)}
  .ak-metric b{font:700 1.5rem var(--tv-font-head);line-height:1.1}.ak-metric span{font-size:.9rem;color:var(--tv-meta)}
  .ak-metric.on{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));border-color:var(--tv-sage)}
  /* lists */
  .ak-list{margin:.2rem 0 var(--tv-gap)}
  .ak-lh{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin:1rem 0 .5rem}.ak-lh h2{margin:0}
  .ak-bar{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-bottom:.45rem}
  .ak-q{flex:1 1 14rem;min-width:0;background:var(--tv-field);border:1px solid var(--tv-line-strong);border-radius:999px;padding:.38rem .85rem;font-size:.95rem;color:var(--tv-ink)}
  .ak-b{display:inline-flex;align-items:center;justify-content:center;gap:.3rem;font:700 .9rem var(--tv-font-ui);border-radius:.6rem;padding:.4rem .8rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);text-decoration:none;cursor:pointer;white-space:nowrap}
  .ak-b.go{background:var(--tv-umber);border-color:var(--tv-umber);color:var(--tv-card-a)}
  .ak-b.no{color:var(--tv-fail);border-color:color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas))}
  .ak-b:disabled,.ak-b.dis{opacity:.45;cursor:not-allowed}
  .ak-t{width:100%;border-collapse:separate;border-spacing:0;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:var(--tv-r);font-size:.95rem}
  .ak-t>thead>tr:first-child>th:first-child{border-top-left-radius:var(--tv-r)}.ak-t>thead>tr:first-child>th:last-child{border-top-right-radius:var(--tv-r)}   /* the corners on the cells, so nothing in the table is clipped (its ⋯ menus) */
  .ak-t>tbody>tr:last-child>td:first-child{border-bottom-left-radius:var(--tv-r)}.ak-t>tbody>tr:last-child>td:last-child{border-bottom-right-radius:var(--tv-r)}
  .ak-t th{text-align:left;font:600 .8rem var(--tv-font-ui);letter-spacing:0;text-transform:none;color:var(--tv-meta);padding:.5rem .7rem;border-bottom:1px solid var(--tv-line);background:var(--tv-sec,var(--tv-card-a))}   /* sentence case (design/table-standard.md) */
  .ak-t td{padding:.5rem .7rem;border-top:1px solid var(--tv-line);vertical-align:top;color:var(--tv-ink)}
  .ak-t tr:first-child td{border-top:0}
  .ak-t tbody tr{cursor:pointer}.ak-t tbody tr:hover td{background:color-mix(in srgb,var(--tv-sec,#EDE6D5) 60%,var(--tv-card-a))}
  /* the table standard (design/table-standard.md, T1) */
  .ak-t td{line-height:1.35}.ak-t .akt-num{text-align:right;white-space:nowrap}.ak-t td.ak-tn .akt-sub{display:block;margin-top:.1rem;font-size:.82rem;color:var(--tv-meta);font-weight:400}
  .ak-t .akt-acts{width:1%;white-space:nowrap;text-align:right}.ak-t td.akt-acts{vertical-align:middle}.akt-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
  .akt-ract{padding:.25rem .75rem;font-size:.85rem}.akt-rm{position:relative;display:inline-block;margin-left:.3rem}
  .akt-rmb{width:32px;height:32px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-link);font:700 1rem/1 var(--tv-font-ui);cursor:pointer}.akt-rmb:hover,.akt-rmb[aria-expanded=true]{border-color:var(--tv-link)}
  .akt-rml.up{top:auto;bottom:calc(100% + .25rem)}   /* near the bottom of the window it opens upward */
  .akt-rml{position:absolute;right:0;top:calc(100% + .25rem);z-index:32;min-width:14rem;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.6rem;box-shadow:var(--tv-shadow-lg);padding:.3rem;display:flex;flex-direction:column;text-align:left}
  .akt-rml[hidden]{display:none}.akt-rml button{display:flex;gap:.55rem;align-items:flex-start;border:0;background:none;padding:.42rem .55rem;border-radius:.4rem;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;text-align:left}
  .akt-rml button:hover:not(:disabled){background:var(--tv-sec,#EDE6D5)}.akt-rml button:disabled{color:var(--tv-meta);cursor:not-allowed}.akt-rml small{display:block;font-size:.76rem;color:var(--tv-meta)}.akt-rmi{display:flex;color:var(--tv-icon,#B45309)}.akt-rmi svg{width:16px;height:16px}
  .akt-st{display:inline-flex;align-items:center;gap:.35rem;font:600 .82rem var(--tv-font-ui);color:var(--tv-ink);white-space:nowrap}.akt-st i{width:.6rem;height:.6rem;border-radius:999px;background:var(--tv-sage);flex:none}
  .akt-st.warn i{background:var(--tv-hold)}.akt-st.bad i{background:var(--tv-fail)}.akt-st.info i{background:var(--tv-taupe)}.akt-st.bad{color:var(--tv-fail)}
  .akt-grp td{background:var(--tv-sec,#EDE6D5)}.akt-gt{display:flex;align-items:center;gap:.55rem;width:100%;border:0;background:none;padding:0;font:inherit;text-align:left;cursor:pointer;color:var(--tv-ink)}
  .akt-gc{display:inline-block;transition:transform var(--tv-ease);color:var(--tv-meta)}.akt-grp[aria-expanded=true] .akt-gc{transform:rotate(90deg)}.akt-gn{font:700 .78rem var(--tv-font-ui);border-radius:999px;padding:.05rem .5rem;background:var(--tv-card-a);border:1px solid var(--tv-line)}.akt-gt small{color:var(--tv-meta);margin-left:auto}
  .ak-t tr[data-in] td:first-child{padding-left:2rem}.ak-t .akt-nod{color:var(--tv-meta);width:3rem}.akt-tnote{margin:.35rem .2rem 0;font-size:.82rem;color:var(--tv-meta)}.akt-tnote[hidden]{display:none}
  .akt-skel{display:flex;flex-direction:column;gap:.45rem;padding:.6rem 0}.akt-skel i{display:block;height:2.4rem;border-radius:.5rem;background:linear-gradient(90deg,var(--tv-sec,#EDE6D5),var(--tv-card-a),var(--tv-sec,#EDE6D5));background-size:200% 100%;animation:ak-sk 1.2s linear infinite}
  @keyframes ak-sk{to{background-position:-200% 0}}
  .akt-gsort{margin-left:.6rem;font:600 .78rem var(--tv-font-ui);color:var(--tv-link);white-space:nowrap}   /* a closed group's sort (T4) */
  <?php require_once __DIR__ . '/../ui/table-kit.php'; echo tv_table_css(); ?>
  .ak-nm{color:var(--tv-link);text-decoration:underline;font-weight:700;background:none;border:0;padding:0;font:inherit;font-weight:700;cursor:pointer;text-align:left}
  .ak-pill{display:inline-block;font-size:.78rem;border-radius:999px;padding:.08rem .5rem;background:var(--tv-card-b);color:var(--tv-meta);font-weight:700;white-space:nowrap}
  .ak-pill.end{background:var(--tv-card-b);color:var(--tv-meta)}.ak-pill.ok{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}.ak-pill.red{background:var(--tv-fail-bg);color:var(--tv-fail)}
  .ak-empty{padding:1rem;color:var(--tv-meta);text-align:center}
  /* the lists' standard (Jason, 2026-11-12, as the vault's): + to add beside the search, sortable column names, Columns, Show more and a page bar */
  .ak-plus{width:38px;height:38px;flex:none;border-radius:50%;border:0;background:var(--tv-btn);color:var(--tv-btn-text);display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
  .ak-plus .ak-svg{width:20px;height:20px;flex-basis:20px;stroke-width:2.5}.ak-plus:hover{background:var(--tv-btn-hover)}.ak-plus:disabled{opacity:.45;cursor:default}
  .ak-sort{border:0;background:none;padding:0;font:inherit;letter-spacing:inherit;text-transform:inherit;color:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:.25rem}
  .ak-sort i{font-style:normal;color:var(--tv-taupe)}.ak-sort[aria-sort] i,.ak-sort:hover{color:var(--tv-link)}
  .ak-thl{display:flex;align-items:center;gap:.5rem;justify-content:space-between}
  .ak-cols{position:relative;text-transform:none;letter-spacing:0}.ak-cols>summary{list-style:none;cursor:pointer;font:600 .8rem var(--tv-font-ui);color:var(--tv-meta);border:1px solid var(--tv-line-strong);border-radius:999px;padding:.15rem .55rem;background:#fff;white-space:nowrap}
  .ak-cols>summary::-webkit-details-marker{display:none}.ak-cols[open]>summary{border-color:var(--tv-link);color:var(--tv-link)}
  .ak-colsl{position:absolute;right:0;top:calc(100% + .3rem);z-index:31;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.6rem;box-shadow:var(--tv-shadow-lg);padding:.45rem .65rem;min-width:12rem}
  .ak-colsl label{display:flex;align-items:center;gap:.45rem;padding:.22rem 0;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;white-space:nowrap}.ak-colsl input{accent-color:var(--tv-link)}
  .ak-more{display:block;width:100%;margin:.5rem 0 0;padding:.6rem 1rem}.ak-more[hidden]{display:none}
  @media (max-width:767px){
    .ak-t,.ak-t tbody,.ak-t tr,.ak-t td{display:block;width:auto}
    .ak-t{background:transparent;border:0;border-radius:0}
    .ak-t thead{display:none}
    .ak-t tbody tr{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.5rem;margin:0 3px 3px;padding:.5rem .7rem}
    .ak-t td{border:0;padding:.08rem 0;display:flex;gap:.5rem}
    .ak-t td[data-l]::before{content:attr(data-l);color:var(--tv-meta);font-size:.82rem;min-width:6.5rem;flex:none}
    .ak-t td.ak-tn{font-size:1.02rem}
    .ak-t td.akt-acts{justify-content:flex-end;padding-top:.35rem;width:auto}.ak-t .akt-nod{display:none}.ak-t .akt-num{text-align:left}.ak-t tr[data-in] td:first-child{padding-left:0}.ak-t tr[data-in]{margin-left:1rem}
    .ak-list,.ak-metrics{margin-left:-3px;margin-right:-3px}
    .ak-bar,.ak-lh,.ak-pg{margin-left:3px;margin-right:3px}
    .ak-metrics{gap:3px;padding:0 3px}.ak-metric{border-radius:.5rem}
    .ak-hand{margin-left:0;width:100%}
    .ak-cmd{margin:-.8rem calc(-1 * var(--tv-pad,.85rem)) 1rem !important;padding:.4rem .5rem;gap:.4rem .5rem}
    /* phones (Jason, 2026-11-19: "Command needs help"): one row for where you are and the board's light and Shortcuts, a
       second only for the page's own filter and the tools at hand. The module's name gives way to the sections dropdown,
       which names the page (Back returns to the module) */
    .ak-cmd{flex-wrap:wrap}.ak-cmd .ak-mod{display:none}
    .ak-cmdr{display:contents}.ak-cmdr>*{order:4}
    .ak-cmd .ak-back{order:1}
    .ak-cmd .ak-dd{order:2;flex:1 1 0;min-width:0;max-width:none}.ak-cmd .ak-dd>summary{min-width:0;width:100%}.ak-cmd .ak-dd>summary>span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ak-cmd .ak-light{order:3}
    .ak-cmd .ab{order:5;margin-left:0}.ak-cmd .ab>summary span{display:none}
    .ak-cmd::after{content:"";order:6;flex-basis:100%;height:0;margin-top:-.4rem}   /* the break before the second row */
    .ak-cmdf{order:7;flex:1 1 auto}
    .ak-cmd .ak-hand{order:8;margin-left:auto;padding-left:.4rem;gap:.25rem}.ak-cmd .ak-hand>span{display:none}
    .ak-cmd .ak-ddl{position:fixed;left:3px;right:3px;width:auto;top:auto}   /* the sections list across the screen, so it never runs off the right edge */
  }
  /* drawers: two deep at most; the top one is what Back closes */
  .ak-dim{position:fixed;inset:0;background:rgba(35,29,23,.35);z-index:1700}
  .ak-dr{position:fixed;top:0;right:0;bottom:0;width:min(36rem,100%);z-index:1750;background:var(--tv-card-a);border-left:1px solid var(--tv-line-strong);box-shadow:-18px 0 40px -20px rgba(35,29,23,.45);display:flex;flex-direction:column}
  .ak-dr.wide{width:min(64rem,100%)}
  .ak-dr.l2{z-index:1760;width:min(34rem,94%)}.ak-dr.l2.wide{width:min(60rem,94%)}
  .ak-drb{display:flex;align-items:center;gap:.5rem;padding:.55rem .8rem;background:var(--tv-umber);color:var(--tv-card-a)}
  .ak-drb b{flex:1;font:700 1.05rem/1.25 var(--tv-font-head);min-width:0;overflow:hidden;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow-wrap:anywhere}
  .ak-drb a{color:var(--tv-card-a);font-size:.82rem;text-decoration:underline;white-space:nowrap}
  .ak-x{width:34px;height:34px;border-radius:999px;border:1px solid color-mix(in srgb,var(--tv-on-umber) 45%,transparent);background:transparent;color:var(--tv-card-a);font-size:1.3rem;line-height:1;cursor:pointer;flex:none}
  .ak-drc{flex:1;overflow-y:auto;padding:.8rem 1rem 2rem}
  .ak-drc.fr{padding:.6rem .7rem 0;overflow:hidden;background:var(--tv-canvas,var(--tv-card-a))}.ak-drc.fr iframe{width:100%;height:100%;border:0;display:block;border-radius:.4rem;background:var(--tv-card-a)}
  /* a drawer's Back (one panel at a time, 2026-11-23): its own small button in the header, never the page header's round .ak-back */
  .ak-drb .ak-drback{flex:none;display:inline-flex;align-items:center;gap:.2rem;padding:.2rem .6rem;border:1px solid color-mix(in srgb,var(--tv-on-umber,#fff) 45%,transparent);border-radius:999px;color:var(--tv-card-a);font:600 .82rem var(--tv-font-ui);text-decoration:none;white-space:nowrap}
  .ak-drb .ak-drback:hover,.ak-drb .ak-drback:focus-visible{background:color-mix(in srgb,var(--tv-on-umber,#fff) 18%,transparent)}.ak-drb .ak-drback[hidden]{display:none}
  @media (max-width:767px){.ak-drc.fr{padding:.4rem 3px 0}.ak-drb{gap:.35rem;padding:.5rem .6rem}}
  .ak-sub{color:var(--tv-meta);margin:0 0 .5rem}
  .ak-steps{display:flex;flex-wrap:wrap;gap:.25rem;margin:.3rem 0 .6rem;padding:0;list-style:none}
  .ak-steps li{font-size:.8rem;font-weight:700;border-radius:999px;padding:.12rem .55rem;background:var(--tv-card-a);color:var(--tv-meta)}
  .ak-steps li.done{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}.ak-steps li.now{background:var(--tv-umber);color:var(--tv-card-a)}.ak-steps li.endnow{background:var(--tv-card-b);color:var(--tv-meta)}
  .ak-clock{font-size:.88rem;color:var(--tv-meta);margin:.1rem 0 .5rem}.ak-clock.late{color:var(--tv-fail);font-weight:700}
  .ak-acts{display:flex;flex-wrap:wrap;gap:.35rem;margin:.3rem 0}
  .ak-why{display:block;font-size:.78rem;color:var(--tv-meta);font-weight:500}
  .ak-b.dis{white-space:normal;flex-direction:column;align-items:flex-start;text-align:left;max-width:100%}
  .ak-ask{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.6rem;padding:.6rem;margin:.4rem 0;display:grid;gap:.4rem}
  .ak-ask label{display:grid;gap:.15rem;font-size:.85rem;font-weight:700;color:var(--tv-meta)}
  .ak-in{width:100%;box-sizing:border-box;background:var(--tv-field);border:1px solid var(--tv-line-strong);border-radius:.5rem;padding:.42rem .6rem;font-size:.95rem;color:var(--tv-ink);font-weight:400}
  .ak-dl{display:grid;grid-template-columns:minmax(7rem,11rem) 1fr;gap:.3rem .8rem;font-size:.95rem;margin:.4rem 0}
  .ak-dl dt{color:var(--tv-meta)}.ak-dl dd{margin:0;white-space:pre-line;overflow-wrap:anywhere}
  .ak-sec{margin:var(--tv-gap) 0 .4rem;font:700 .75rem var(--tv-font-ui);letter-spacing:.07em;text-transform:uppercase;color:var(--tv-meta)}
  .ak-schf{display:flex;gap:.35rem;align-items:center;justify-content:flex-end;flex-wrap:wrap}.ak-schf .ak-in{max-width:12rem;padding:.3rem .5rem;font-size:.85rem}
  .ak-code{display:block;white-space:pre-wrap;word-break:break-all;font-size:.78rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.4rem;padding:.25rem .4rem;margin-top:.2rem;color:var(--tv-ink)}
  .ak-actrow{margin:.5rem 0 .2rem}
  .ak-checks{border:0;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.3rem .5rem}.ak-checks legend{font-size:.85rem;font-weight:700;color:var(--tv-meta);margin-bottom:.2rem}
  .ak-checks label{display:inline-flex;align-items:center;gap:.3rem;font-weight:600;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:999px;padding:.2rem .6rem;font-size:.85rem}
  .ak-tl p.ak-tl-note{background:var(--tv-card-a);border-left:3px solid var(--tv-icon,#B45309);padding-left:.5rem}
  .ak-tl p{margin:0;padding:.35rem 0;border-top:1px solid var(--tv-line);font-size:.88rem}.ak-tl small{color:var(--tv-meta)}
  .ak-links a{display:block;color:var(--tv-link);text-decoration:underline;font-weight:600;padding:.15rem 0}
  /* the quick task box */
  .ak-qt{position:fixed;inset:0;z-index:1800;display:flex;align-items:center;justify-content:center;background:rgba(35,29,23,.4);padding:3px}
  .ak-qtb{background:var(--tv-card-a);border-radius:var(--tv-r);width:min(28rem,100%);max-height:90vh;overflow:auto;box-shadow:0 20px 50px -20px rgba(35,29,23,.5)}
  .ak-qtb form{padding:.8rem 1rem;display:grid;gap:.55rem}
  .ak-qtb label{display:grid;gap:.2rem;font-size:.88rem;font-weight:700;color:var(--tv-meta)}
  .ak-pick{display:grid;gap:.2rem;font-size:.88rem;font-weight:700;color:var(--tv-meta)}.ak-pl{display:flex;align-items:center;gap:.4rem}
  .ak-info{width:1.3rem;height:1.3rem;border-radius:999px;border:1.5px solid var(--tv-icon,#B45309);background:var(--tv-card-a);color:var(--tv-icon,#B45309);font:italic 700 .78rem/1 var(--tv-font-head);cursor:pointer;padding:0;display:inline-grid;place-items:center;transition:background .15s,color .15s}
  .ak-info:hover,.ak-info:focus-visible{background:var(--tv-icon,#B45309);color:var(--tv-card-a);outline:none}.ak-info[aria-expanded="true"]{background:var(--tv-sage);border-color:var(--tv-sage);color:#fff;box-shadow:0 0 0 3px color-mix(in srgb,var(--tv-sage) 35%,transparent)}
  .ak-assist{display:flex;flex-wrap:wrap;align-items:center;gap:.3rem .5rem;margin:-.15rem 0 .1rem}.ak-assist small{flex:1 1 12rem;font-size:.76rem;font-weight:400;color:var(--tv-meta)}
  .ak-assist .ak-b[disabled]{opacity:.55;cursor:not-allowed}
  .ak-fitnote{justify-self:start;border:0;background:none;padding:.1rem 0;font:400 .8rem/1.35 inherit;color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));text-align:left;cursor:pointer}
  .ak-fitnote u{text-decoration:underline;text-underline-offset:2px;color:var(--tv-link)}.ak-fitnote:hover u,.ak-fitnote:focus-visible u{color:var(--tv-icon,#B45309)}.ak-fitnote:focus-visible{outline:2px solid var(--tv-icon,#B45309);outline-offset:2px;border-radius:.25rem}
  .ak-ph{display:flex;flex-wrap:wrap;gap:.4rem;margin:.2rem 0 .1rem}.ak-phi{position:relative;width:76px;height:76px;border-radius:.5rem;overflow:hidden;background:var(--tv-canvas);border:1px solid var(--tv-line-strong)}
  .ak-phi img{width:100%;height:100%;object-fit:cover;display:block}.ak-phi button{position:absolute;top:3px;right:3px;width:22px;height:22px;border-radius:999px;border:0;background:color-mix(in srgb,var(--tv-umber) 85%,transparent);color:var(--tv-card-a);cursor:pointer;line-height:1;font-size:.9rem}
  .ak-phi.first::after{content:"Cover";position:absolute;left:3px;bottom:3px;font:700 .6rem var(--tv-font-ui);background:color-mix(in srgb,var(--tv-umber) 85%,transparent);color:var(--tv-card-a);border-radius:.3rem;padding:.05rem .3rem}
  .ak-phadd{width:76px;height:76px;border-radius:.5rem;border:1.5px dashed var(--tv-taupe);display:flex;align-items:center;justify-content:center;font:700 .75rem var(--tv-font-ui);color:var(--tv-icon,#B45309);cursor:pointer;text-align:center;background:var(--tv-card-a)}
  .ak-phadd:hover{background:var(--tv-card-a)}.ak-phnote{margin:.2rem 0 0;font-size:.8rem}
  /* the choices' dialog: dimmed page; a bottom sheet on a phone, a centered card wider */
  html.ak-popping{overflow:hidden}
  .ak-popwrap{position:fixed;inset:0;z-index:1900;background:rgba(35,29,23,.45);display:flex;align-items:flex-end;justify-content:center;animation:ak-fade .15s ease-out}
  .ak-pop{background:var(--tv-card-a);width:100%;max-height:85vh;display:flex;flex-direction:column;border-radius:1rem 1rem 0 0;box-shadow:0 -12px 40px -12px rgba(35,29,23,.4);animation:ak-up .2s ease-out;overflow:hidden}
  .ak-pop::before{content:"";align-self:center;width:2.4rem;height:.25rem;border-radius:999px;background:var(--tv-line-strong);margin:.5rem 0 0}
  .ak-poph{display:flex;align-items:flex-start;justify-content:space-between;gap:.6rem;padding:.45rem .9rem .55rem;border-bottom:1px solid var(--tv-line)}
  .ak-poph b{display:block;font:700 .98rem var(--tv-font-head);color:var(--tv-ink)}.ak-poph small{display:block;font-size:.76rem;line-height:1.35;color:var(--tv-meta);margin-top:.1rem}
  .ak-popx{flex:none;width:1.8rem;height:1.8rem;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-meta);font-size:1.15rem;line-height:1;cursor:pointer}
  .ak-popx:hover,.ak-popx:focus-visible{background:var(--tv-card-a);outline:none}
  .ak-popl{overflow-y:auto;overscroll-behavior:contain;padding:.15rem 0 .35rem;scrollbar-width:thin;scrollbar-color:var(--tv-line-strong) transparent}
  .ak-opt{display:grid;grid-template-columns:1rem 1fr;gap:.6rem;align-items:start;width:100%;text-align:left;border:0;border-bottom:1px solid var(--tv-line);background:none;padding:.45rem .9rem;cursor:pointer;font:inherit;color:var(--tv-ink)}
  .ak-opt:last-child{border-bottom:0}
  .ak-opt:hover{background:var(--tv-card-a)}.ak-opt:focus-visible{outline:2px solid var(--tv-icon,#B45309);outline-offset:-2px}
  .ak-radio{width:1rem;height:1rem;margin-top:.1rem;border-radius:999px;border:2px solid var(--tv-line-strong);background:#fff;box-sizing:border-box}
  .ak-opt.on .ak-radio{border-color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));background:radial-gradient(circle,color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink)) 0 .24rem,#fff .26rem)}
  .ak-opt.on{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas))}.ak-opt.on b{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}
  .ak-optt b{display:block;font-weight:700;font-size:.88rem;line-height:1.3}.ak-optt small{display:block;font-weight:400;font-size:.76rem;color:var(--tv-meta);line-height:1.35;margin-top:.05rem}
  .ak-optwhy{display:block;font-style:normal;font-size:.8rem;color:var(--tv-meta);margin-top:.15rem}.ak-optwhy:empty{display:none}
  .ak-offh{margin:.3rem .9rem 0;padding-top:.4rem;border-top:1px solid var(--tv-line);font-size:.75rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--tv-meta)}
  .ak-opt.off{cursor:not-allowed;opacity:.5;padding-top:.2rem;padding-bottom:.2rem;border-bottom:0}.ak-opt.off:hover{background:none}.ak-opt.off small{display:none}.ak-opt.off .ak-radio{visibility:hidden}
  @media (min-width:768px){.ak-popwrap{align-items:center;padding:1.5rem}.ak-pop{width:min(27rem,100%);max-height:min(40rem,85vh);border-radius:1rem;box-shadow:0 24px 60px -20px rgba(35,29,23,.5);animation:ak-pop .16s ease-out}.ak-pop::before{display:none}.ak-poph{padding-top:.9rem}}
  @keyframes ak-fade{from{opacity:0}}@keyframes ak-up{from{transform:translateY(30%)}}@keyframes ak-pop{from{transform:scale(.97);opacity:0}}
  @media (prefers-reduced-motion:reduce){.ak-popwrap,.ak-pop{animation:none}}
  /* bridges and landing pages */
  .ak-frame{width:100%;border:0;display:block;min-height:15rem;background:transparent}
  .ak-landing{background:var(--tv-card-a);border:1px dashed var(--tv-line-strong);border-radius:var(--tv-r);padding:1rem 1.1rem;max-width:46rem}
  .ak-landing h2{font:700 1.3rem var(--tv-font-head);margin:.1rem 0 .3rem}
  .ak-tag{display:inline-block;margin:0;font:700 .72rem var(--tv-font-ui);letter-spacing:.06em;text-transform:uppercase;border-radius:999px;padding:.1rem .55rem;background:var(--tv-card-b);color:var(--tv-meta)}
  .ak-card .ak-bar{display:flex;align-items:center;gap:.5rem;font-size:.92rem;margin:.3rem 0;flex-wrap:nowrap}
  .ak-card .ak-bar span{flex:0 0 9.5rem}.ak-card .ak-bar i{display:block;height:.7rem;background:var(--tv-sage);border-radius:999px}
  /* the permissions matrix */
  .ak-perm h3{margin:.9rem 0 .2rem}.ak-perm h3:first-child{margin-top:0}
  .ak-prow{display:grid;grid-template-columns:minmax(10rem,1.3fr) 11rem 2fr;gap:.4rem .6rem;align-items:center;padding:.4rem 0;border-top:1px solid var(--tv-line);font-size:.92rem}
  .ak-prow select{background:var(--tv-field);border:1px solid var(--tv-line-strong);border-radius:.5rem;padding:.25rem .4rem}
  .ak-depts{display:flex;flex-wrap:wrap;gap:.15rem .7rem}.ak-depts label{display:inline-flex;gap:.25rem;align-items:center;font-size:.85rem}
  .ak-prow.saved{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas))}
  @media (max-width:767px){.ak-prow{grid-template-columns:1fr}.ak-dl{grid-template-columns:1fr;gap:.05rem}.ak-dl dd{margin-bottom:.35rem}}
  /* the workflow builder */
  .ak-wfs{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(17rem,100%),1fr));gap:.5rem}
  .ak-wf{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:var(--tv-r);padding:.7rem .85rem;display:grid;gap:.25rem;align-content:start;text-align:left;cursor:pointer;font:inherit;color:var(--tv-ink)}
  .ak-wf:hover{border-color:var(--tv-sage)}
  .ak-wf small{color:var(--tv-meta)}
  .ak-chips{display:flex;flex-wrap:wrap;gap:.2rem;align-items:flex-start;align-content:flex-start}.ak-chips span{font-size:.72rem;border-radius:999px;padding:.02rem .45rem;background:var(--tv-card-a);color:var(--tv-meta)}
  .ak-ed{display:grid;gap:.5rem}
  .ak-row{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.6rem;padding:.55rem .7rem;display:grid;gap:.4rem}
  .ak-row.lock{background:var(--tv-card-a)}
  .ak-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(10rem,100%),1fr));gap:.4rem}
  .ak-grid label,.ak-row>label{display:grid;gap:.15rem;font-size:.8rem;font-weight:700;color:var(--tv-meta)}
  .ak-checks{display:flex;flex-wrap:wrap;gap:.15rem .7rem;font-size:.85rem}.ak-checks label{display:inline-flex;gap:.25rem;align-items:center;font-weight:500}
  .ak-rh{display:flex;align-items:center;gap:.4rem}.ak-rh b{flex:1}.ak-rh small{color:var(--tv-meta)}
  .ak-mini{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:.4rem;padding:.1rem .45rem;font-size:.8rem;cursor:pointer;color:var(--tv-ink)}
  .ak-mini:disabled{opacity:.4;cursor:not-allowed}
  .ak-probs{background:var(--tv-hold-bg);border:1px solid color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-radius:.6rem;padding:.5rem .8rem;font-size:.9rem}.ak-probs.ok{background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));border-color:color-mix(in srgb,var(--tv-moss) 40%,var(--tv-canvas))}
  .ak-probs ul{margin:.2rem 0 0;padding-left:1.1rem}
  /* collapsible rows (Data separation): title and status left, actions flush right, body underneath */
  .ak-fold{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:var(--tv-r);margin:0 0 .4rem}
  .ak-foldh{display:flex;align-items:flex-start;gap:.5rem;padding:.55rem .6rem .55rem .4rem}
  .ak-foldt{flex:1;min-width:0;display:flex;gap:.45rem;align-items:flex-start;background:none;border:0;padding:.1rem;text-align:left;cursor:pointer;font:inherit;color:var(--tv-ink)}
  .ak-foldt b{display:block;font-size:1rem}.ak-foldt small{display:block;font-size:.85rem;color:var(--tv-meta);margin-top:.05rem}
  .ak-foldt small.ok{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}.ak-foldt small.bad{color:var(--tv-fail);font-weight:700}
  .ak-chev{flex:none;width:1rem;color:var(--tv-meta);transition:transform .15s;margin-top:.1rem}
  .ak-foldt[aria-expanded=true] .ak-chev{transform:rotate(90deg)}
  .ak-folda{flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;max-width:55%}
  .ak-folda form{margin:0}
  .ak-foldb{padding:0 .8rem .7rem 2rem}
  .ak-tbls{margin:0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:.25rem .9rem;font-size:.9rem}
  .ak-tbls small{color:var(--tv-meta)}
  .ak-res{display:flex;flex-wrap:wrap;gap:.5rem;padding:.35rem 0;border-top:1px solid var(--tv-line);font-size:.92rem}.ak-res>span{flex:1 1 12rem;min-width:0;overflow-wrap:anywhere}.ak-res:first-child{border-top:0}
  .ak-res b{flex:none;width:1.1rem}.ak-res.amt b{width:auto;min-width:4.2rem;text-align:right}.ak-res b.ok{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}.ak-res b.bad{color:var(--tv-fail)}
  .ak-res small{display:block;color:var(--tv-meta);font-size:.85rem}
  @media (max-width:767px){.ak-fold{border-radius:.5rem;margin-bottom:3px}.ak-foldb{padding-left:1.6rem}}
  .ak-arch{background:var(--tv-hold-bg);border:1px solid color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-radius:.6rem;padding:.5rem .8rem;font-size:.92rem;margin:0 0 .6rem}
  .ak-arch a{color:var(--tv-link);text-decoration:underline;font-weight:700}
  /* gentle geometry (directive §3): soft corners and a warm, low shadow on the containers; rows inside keep their
     compact padding (space goes between groups, never inside a list's rows) */
  .ak-card,.ak-t,.ak-fold,.ak-wf,.ak-metric,.ak-landing,.ak-row,.ak-probs,.ak-arch{box-shadow:var(--tv-shadow)}
  .ak-card,.ak-fold,.ak-wf,.ak-metric,.ak-landing,.ak-row{background:var(--tv-card)}   /* elevated surfaces: the micro-gradient (brand.md §4) */
  .ak-q,.ak-in,.ak-prow select,.ak-per select{background:var(--tv-field);border-color:var(--tv-line-strong)}
  .ak-b,.ak-ib,.ak-fb,.ak-dd>summary,.ak-mini,.ak-pgb,.ak-nm,.ak-metric,.ak-wf{transition:background-color var(--tv-ease),border-color var(--tv-ease),color var(--tv-ease)}
  .ak-t,.ak-card,.ak-fold{border-color:var(--tv-line)}
  .ak-body>.ak-card+.ak-card,.ak-body>.ak-fold+.ak-card{margin-top:var(--tv-gap)}
  @media (max-width:767px){.ak-t{box-shadow:none}.ak-t tbody tr{box-shadow:var(--tv-shadow)}}
</style>
