#!/usr/bin/env python3
"""Get Local R2, part 1: the line over the list (Jason, 2026-11-19: "this still feels cluttered"; approved Change 1).
One line: "N [kind ▾] within/in [place ▾]" with any active filter as a chip (×) in it, then Filters ▾ (Open now, Has a
phone, Has a website, Favorites, ticked; its count; ×, Esc and a click outside close it) and Sort on the right. The
"· showing 1–10" goes (the page bar says it), and what's left of that line has no stray dot. Replaces decisions/0058's
2026-11-19 "Toggles … and Sort under it". Replayable: build-r2a.py <root> with website/directory/index.php."""
import sys, os
p = os.path.join(sys.argv[1], 'website/directory/index.php'); s = open(p, encoding='utf-8').read()
def rep(a, b, n=1):
    global s
    assert s.count(a) == n, (a[:90], s.count(a)); s = s.replace(a, b)
i = s.index('  <div class="dr-quick" role="group" aria-label="Refine"><span class="dr-tgs">')
j = s.index('</label></div>\n', i) + len('</label></div>\n')
quick = s[i:j]
tgs = quick[quick.index('<span class="dr-tgs">'):quick.index('</span>', quick.index('<span class="dr-tgs">')) + len('</span>')]
srt = quick[quick.index('<label class="dr-srt">'):quick.rindex('</label>') + len('</label>')]
FUN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>'
s = s[:i] + s[j:]
rep('  <p class="dr-sent" id="dr-sent" aria-live="polite"></p>\n',
    '  <div class="dr-row1"><p class="dr-sent" id="dr-sent" aria-live="polite"></p>\n'
    '  <div class="dr-ctl"><details class="dr-fdd" id="dr-fdd"><summary class="dr-fb" title="Show only some businesses">' + FUN + 'Filters <b id="dr-fn" hidden></b> <i aria-hidden="true">&#9662;</i></summary>\n'
    '    <div class="dr-fddl" role="group" aria-label="Filters"><button type="button" class="dr-fx" data-fdd-x aria-label="Close" title="Close">&times;</button><p class="dr-fh">Show only</p>' + tgs + '</div></details>\n'
    '  ' + srt + '</div></div>\n')
rep("  .dr-quick{display:flex;", "  .dr-row1{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .6rem;margin:0 0 .6rem}.dr-row1 .dr-sent{margin:0;flex:1 1 auto}\n"
    "  .dr-ctl{display:flex;align-items:center;gap:.5rem;margin-left:auto}   /* Filters and Sort, on the sentence's line (Jason, 2026-11-19) */\n"
    "  .dr-fdd{position:relative}.dr-fdd>summary{list-style:none}.dr-fdd>summary::-webkit-details-marker{display:none}\n"
    "  .dr-fb{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.36rem .8rem;font:600 .88rem var(--tv-font-ui);cursor:pointer}\n"
    "  .dr-fb svg{width:15px;height:15px;color:var(--tv-icon,#B45309)}.dr-fb i{font-style:normal;font-size:.75rem;color:var(--tv-meta)}.dr-fdd[open]>.dr-fb{border-color:var(--tv-link)}\n"
    "  .dr-fb b{background:var(--tv-sage,#8FBCA8);color:var(--tv-umber,#2A1B12);border-radius:999px;padding:0 .4rem;font-size:.78rem}\n"
    "  .dr-fddl{position:absolute;right:0;top:calc(100% + .35rem);z-index:40;min-width:14rem;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.45rem}\n"
    "  .dr-fh{margin:.1rem .4rem .3rem;font:600 .78rem var(--tv-font-ui);color:var(--tv-meta);padding-right:2rem}\n"
    "  .dr-fx{position:absolute;top:.3rem;right:.35rem;width:28px;height:28px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);font-size:1.05rem;line-height:1;cursor:pointer}\n"
    "  .dr-fddl .dr-tgs{display:flex;flex-direction:column;gap:.1rem}\n"
    "  .dr-fddl .dr-tg{display:flex;align-items:center;gap:.45rem;width:100%;text-align:left;border:0!important;background:none;border-radius:.45rem;box-shadow:none!important;padding:.45rem .5rem!important;font:500 .92rem var(--tv-font-ui)}\n"
    "  .dr-fddl .dr-tg::before{content:'';width:1rem;height:1rem;border:1px solid var(--tv-line-strong);border-radius:.25rem;flex:none;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem}\n"
    "  .dr-fddl .dr-tg[aria-pressed=\"true\"]{background:none;color:var(--tv-ink);font-weight:700}.dr-fddl .dr-tg[aria-pressed=\"true\"]::before{content:'\\2713';background:var(--tv-sage,#8FBCA8);border-color:var(--tv-sage,#8FBCA8)}\n"
    "  .dr-fddl .dr-tg:hover{background:var(--tv-sec,#EDE6D5)}\n"
    "  @media (max-width:767px){.dr-ctl{margin-left:0;width:100%;justify-content:space-between}.dr-fddl{left:0;right:auto}}\n"
    "  .dr-quick{display:flex;")
# active filters as chips in the sentence; their × turns them off
rep("    if (S.town) h += ' · ' + tok('untown', esc(S.town.toLowerCase().replace(/\\b\\w/g, function (c) { return c.toUpperCase(); })), true, true);\n",
    "    if (S.town) h += ' · ' + tok('untown', esc(S.town.toLowerCase().replace(/\\b\\w/g, function (c) { return c.toUpperCase(); })), true, true);\n"
    "    [['open', S.open, 'Open now'], ['phone', S.has.indexOf('phone') >= 0, 'Has a phone'], ['website', S.has.indexOf('website') >= 0, 'Has a website'], ['fav', S.fav, '\\u2605 Favorites']]\n"
    "      .forEach(function (f) { if (f[1]) h += ' · ' + tok('unf-' + f[0], f[2], true, true); });   // a filter that's on, said in the sentence (2026-11-19)\n")
rep("    if (t === 'uncat' || t === 'unq' || t === 'untown') { closePop(); toggle(t); return; }",
    "    if (t === 'uncat' || t === 'unq' || t === 'untown') { closePop(); toggle(t); return; }\n"
    "    if (t.indexOf('unf-') === 0) { closePop(); toggle(t.slice(4)); return; }")
rep("    document.querySelectorAll('.dr-tg').forEach(function (b) { var f = b.getAttribute('data-f'); b.setAttribute('aria-pressed', String(f === 'open' ? S.open : f === 'fav' ? S.fav : S.has.indexOf(f) >= 0)); });",
    "    document.querySelectorAll('.dr-tg').forEach(function (b) { var f = b.getAttribute('data-f'); b.setAttribute('aria-pressed', String(f === 'open' ? S.open : f === 'fav' ? S.fav : S.has.indexOf(f) >= 0)); });\n"
    "    var nf = (S.open ? 1 : 0) + (S.fav ? 1 : 0) + S.has.length, fn = $('dr-fn'); if (fn) { fn.textContent = nf; fn.hidden = !nf; }   // Filters' count")
rep("  document.querySelector('.dr-tgs').addEventListener('click', function (e) { var b = e.target.closest('[data-f]'); if (b) toggle(b.getAttribute('data-f'), b); });",
    "  document.querySelector('.dr-tgs').addEventListener('click', function (e) { var b = e.target.closest('[data-f]'); if (b) toggle(b.getAttribute('data-f'), b); });\n"
    "  (function () {   // Filters closes with its ×, Esc or a click outside (CLAUDE.md: everything that pops open closes)\n"
    "    var d = $('dr-fdd'); if (!d) return;\n"
    "    d.querySelector('[data-fdd-x]').addEventListener('click', function () { d.removeAttribute('open'); });\n"
    "    document.addEventListener('click', function (e) { if (d.open && !d.contains(e.target)) d.removeAttribute('open'); });\n"
    "    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && d.open) { d.removeAttribute('open'); d.querySelector('summary').focus(); } });\n"
    "  })();")
# the line under the list's head: no "Open now" (the sentence says it), no "· showing" (the page bar does), no stray dot
rep("    $('dr-sum').textContent = S.total ? (S.open ? 'Open now' : '') + ('')\n"
    "      + (S.items.length < S.total ? ' · showing ' + ((S.first - 1) * pg.per + 1).toLocaleString() + '–' + ((S.first - 1) * pg.per + S.items.length).toLocaleString() : '')\n"
    "      + (localName && urlPlace && urlPlace.key ? ' · ' + localName + ' is part of this area' : '')\n"
    "      + (S.q && S.meant && S.meant.length ? ' · includes ' + S.meant.slice(0, 3).join(', ') : '') : '';",
    "    $('dr-sum').textContent = S.total ? [localName && urlPlace && urlPlace.key ? localName + ' is part of this area' : '',\n"
    "      S.q && S.meant && S.meant.length ? 'Includes ' + S.meant.slice(0, 3).join(', ') : ''].filter(Boolean).join(' · ') : '';   // the page bar says which ones show (2026-11-19)")
open(p, 'w', encoding='utf-8').write(s); print('built')
