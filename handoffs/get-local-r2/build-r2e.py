#!/usr/bin/env python3
"""Get Local R2, part 5: the header's search (Jason, 2026-11-19: "Why is the search bar still looking like this?";
approved (a) and (b)). (a) The split bar's look: a cream pill with its ⌕ inside, the place as a chip ("in Ancient America
▾", the pin opening the place chooser) and the round copper search button. (b) On a page with its own search bar (Get
Local; Home already has none in the header) it folds to just its ⌕, which opens the site search, as does Ctrl K (⌘K).
Replayable: build-r2e.py <root> with app/ui/header.php, website/js/search-panel.js."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
PIN = '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg></button></span>'
patch('app/ui/header.php', [
 (PIN, '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><span class="tvs-pl" data-tvs-pl>Anywhere</span><i class="tvs-car" aria-hidden="true">&#9662;</i></button></span>'),
 ("  elseif (!$tv_home) echo $tv_search_bar; endif; ?>",
  "  elseif ($tv_fold) echo str_replace('class=\"tvs-bar\" role=\"search\"', 'class=\"tvs-bar tvs-fold\" role=\"search\"', $tv_search_bar);   // the page has its own bar: the header's folds to ⌕ (2026-11-19)\n"
  "  elseif (!$tv_home) echo $tv_search_bar; endif; ?>"),
 ("$tv_search_bar = '';\n", "$tv_search_bar = '';\n$tv_fold = (bool) preg_match('#^/directory(/(index\\.php)?)?$#', $tv_path);   // Get Local: its own search bar, so one search per view (css-theme-brand.md)\n"),
 ("    .tvs-panel:not(.full){border:1px solid var(--tv-line-strong);",
  "    /* the split bar's look (Jason, 2026-11-19): a cream pill, ⌕ inside, the place as a chip, the round copper button */\n"
  "    .tvs-bar{border-radius:999px;padding:0 .3rem 0 .85rem;background:var(--tv-field,#FFFBF2);overflow:visible;gap:.35rem}.tvs-bar .tvs-icon{display:flex;color:var(--tv-icon,#B45309)}\n"
  "    .tvs-btns{background:none;padding:0;gap:.35rem}\n"
  "    .tvs-btns .tvs-pin{width:auto;height:32px;border:1px solid var(--tv-line-strong);border-radius:999px;padding:0 .7rem;gap:.3rem;background:var(--tv-card-a);color:var(--tv-ink);font:600 .84rem var(--tv-font-ui);white-space:nowrap;max-width:15rem}\n"
  "    .tvs-btns .tvs-pin svg{width:15px;height:15px;color:var(--tv-icon,#B45309);flex:none}.tvs-pl{overflow:hidden;text-overflow:ellipsis}.tvs-car{font-style:normal;font-size:.7rem;color:var(--tv-meta)}\n"
  "    .tvs-btns .tvs-pin.set{box-shadow:none;background:var(--tv-card-a)}.tvs-btns .tvs-pin:hover{background:var(--tv-sec,#EDE6D5);box-shadow:none}\n"
  "    .tvs-sbtn{order:2;width:36px;height:36px;border:0;background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#E4DCC5)}.tvs-sbtn:hover{background:var(--tv-btn-hover,#78350F);color:var(--tv-btn-text,#E4DCC5);box-shadow:none}\n"
  "    .tvs-fold{flex:0 0 auto!important;max-width:none!important;background:none!important;border:0!important;padding:0!important;margin-left:auto!important}\n"
  "    .tvs-fold>.tvs-icon,.tvs-fold>.tvs-in,.tvs-fold>.tvs-btns{display:none!important}\n"
  "    .tvs-fold>.tvs-open{display:flex!important;width:40px;height:40px;flex:none;justify-content:center;border:1px solid var(--tv-line-strong);border-radius:999px;background:var(--tv-field,#FFFBF2);color:var(--tv-icon,#B45309)}.tvs-fold>.tvs-open span{display:none}\n"
  "    .tvs-panel:not(.full){border:1px solid var(--tv-line-strong);"),
])
patch('website/js/search-panel.js', [
 ("    input.placeholder = p ? 'Search in ' + p + '…' : 'Search places, listings, groups, stories';\n    if (pin) pin.classList.toggle('set', !!p);",
  "    input.placeholder = p ? 'What are you looking for?' : 'Search places, listings, groups, stories';\n    if (pin) pin.classList.toggle('set', !!p);\n"
  "    var pl = pin && pin.querySelector('[data-tvs-pl]'); if (pl) { pl.textContent = p ? 'in ' + p : 'Anywhere'; pin.title = p ? 'Searching in ' + p + ': choose where' : 'Choose where to search'; }   // the place as a chip (2026-11-19)"),
 ("  var btn = root.querySelector('.tvs-open'); if (btn) btn.addEventListener('click', openFresh);",
  "  var btn = root.querySelector('.tvs-open'); if (btn) btn.addEventListener('click', openFresh);\n"
  "  document.addEventListener('keydown', function (e) {   // Ctrl K / ⌘K opens the site search, as the folded ⌕ does (2026-11-19); the admin's has its own\n"
  "    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K') && !document.getElementById('tv-asearch')) { e.preventDefault(); openFresh(); }\n"
  "  });"),
])
print('built')
