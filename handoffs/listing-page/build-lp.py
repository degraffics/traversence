#!/usr/bin/env python3
"""The listing page (handoffs/screens-proposal-2026-11-22.md B5, Option 1; Jason's answer 2026-11-22). Reads the live
mirror, writes out/. Replayable."""
import os, re, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:100], s.count(a)); return s.replace(a, b)
put('release.json', open(os.path.join(S, 'src', 'release.json'), encoding='utf-8').read())

# how sure we are, in words: the same button and panel as the ring (decisions/0133), its label said (B5)
f = 'app/ui/confidence-ui.php'; s = live(f)
s = rep(s, "/**\n * How we know, under the card (opened by the ring).",
"""/**
 * How sure we are, in words (B5, Jason 2026-11-22: "the score ring said in words"): the band's colour as a dot, its label,
 * and the button that opens How we know, the ring's own job (decisions/0133). The number is in the panel.
 */
function tv_confidence_words(array $c): string
{
    $say = 'How sure we are: ' . $c['label'] . '. Show how we know.';
    return '<button type="button" class="lv-sure" data-know-open aria-controls="lv-know" aria-expanded="false" aria-label="' . tv_e($say) . '">'
        . '<span class="lv-sure-dot" style="background:' . tv_confidence_colour((int) $c['score']) . '" aria-hidden="true"></span>'
        . '<span>How sure we are: <b>' . tv_e((string) $c['label']) . '</b></span><span class="lv-sure-more" aria-hidden="true">How we know ▾</span></button>';
}

/**
 * How we know, under the card (opened by the ring or the words).""")
s = rep(s, "    return '<style>.lv-ring{",
           "    return '<style>.lv-sure{display:inline-flex;flex-wrap:wrap;align-items:center;gap:.25rem .45rem;margin-top:.45rem;padding:0;border:0;background:none;color:var(--tv-ink,#3E2B1F);font:inherit;font-size:.82rem;text-align:left;cursor:pointer}'\n"
           "        . '.lv-sure:hover .lv-sure-more{text-decoration-thickness:2px}.lv-sure-dot{width:.65rem;height:.65rem;border-radius:999px;flex:none}.lv-sure-more{color:var(--tv-link,#92400E);font-weight:600;text-decoration:underline;text-underline-offset:2px}'\n"
           "        . '.lv-sure[aria-expanded=true] .lv-sure-more{text-decoration:none}.lv-sure:focus-visible{outline:2px solid #F59E0B;outline-offset:2px}'\n"
           "        . '.lv-ring{")
put(f, s)

f = 'website/listing/view.php'; s = live(f)
# B5: one row of named actions under the title; the ring in words; the Link alone in the corner; Fix the pin only with a pin
a0 = s.index('    <div class="lv-acts" aria-label="Contact and directions">'); a1 = s.index('    <div class="lv-info">')
block = s[a0:a1]
block = block.replace("      <?= $conf ? tv_confidence_ring($conf) : '' ?>\n", "")
NAMES = {'Call': 'Call', 'Email them from your own mail app': 'Email', 'Get directions': 'Directions', 'See on map': 'Map',
         'Website': 'Website', 'Contact or book online': 'Contact', 'Save contact': 'Save contact', 'QR code': 'QR code'}
def lab(m):
    t = m.group(0); title = re.search(r'\stitle="([^"]+)"', t).group(1); name = NAMES[title]
    return re.sub(r'(</(a|button)>)$', '<span class="lv-al">' + name + r'</span>\1', t)
n0 = block.count('class="lv-a"')
block = re.sub(r'<(a|button) (?:type="button" )?class="lv-a".*?</\1>', lab, block, flags=re.S)
assert block.count('lv-al') == n0 == 8, (n0, block.count('lv-al'))
# the corner's Message moves into the row, named (one control in the corner: the Link)
msg = s[s.index("  <?php if (isset($acts['message'])): ?>\n  <a data-link-extra"):s.index("  <?php if ($isAdminViewer): ?><a class=\"lv-admin\"")]
s = s.replace(msg, '')
row_msg = (msg.replace('data-link-extra ', 'class="lv-a" ').replace(' class="tv-extra"', '').replace('width="15" height="15" ', '')
              .replace('</svg></a>\n  <?php elseif', '</svg><span class="lv-al">Message</span></a>\n  <?php elseif')
              .replace('</svg></a>\n  <?php endif; ?>', '</svg><span class="lv-al">Leave a message</span></a>\n  <?php endif; ?>'))
assert row_msg.count('lv-al') == 2 and 'data-link-extra' not in row_msg
block = rep(block, "      <?php // Save contact (",
            '\n'.join('    ' + l if l.strip() else l for l in row_msg.rstrip('\n').split('\n')) + '\n' + "      <?php // Save contact (")
a0 = s.index('    <div class="lv-acts" aria-label="Contact and directions">'); a1 = s.index('    <div class="lv-info">')
s = s[:a0] + s[a1:]   # the actions leave the body: they're the ⋯ toolkit's list now (Jason, 2026-11-22)
tk = block.replace('    <div class="lv-acts" aria-label="Contact and directions">', '    <div class="lv-acts" id="lv-tk-l">', 1)
tk = ('  <button type="button" class="tv-extra lv-tk-b" data-link-extra aria-haspopup="true" aria-expanded="false" aria-controls="lv-tk" title="Call, directions, map and more" aria-label="Call, directions, map and more">'
      '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg></button>\n'
      '  <div class="lv-tk" id="lv-tk" role="dialog" aria-label="Call, directions, map and more" hidden>\n'
      '    <div class="lv-tk-h"><b><?= tv_e($biz[\'name\']) ?></b><button type="button" class="lv-tk-x" data-tk-close aria-label="Close">×</button></div>\n'
      + tk + '  </div>\n')
link = '  <span data-link="business:<?= $eid ?>" data-link-label="<?= tv_e($biz[\'name\']) ?>"></span>\n'
s = rep(s, link, link + tk)
s = rep(s, "      <?php if (empty($privateLocation)): ?><div class=\"m lv-fix\"><a href=\"/listing/location.php?id=<?= $eid ?>\"><?= $isAdminViewer ? 'Location and map pin' : 'Wrong spot on the map? Fix the pin' ?></a></div><?php endif; ?>",
           "      <?php if ($isAdminViewer || (empty($privateLocation) && $hasPt && !$mailOnly)): // only where there's a pin to fix: never on a PO box or a listing with no point (B5); staff always place it ?><div class=\"m lv-fix\"><a href=\"/listing/location.php?id=<?= $eid ?>\"><?= $isAdminViewer ? 'Location and map pin' : 'Wrong spot on the map? Fix the pin' ?></a></div><?php endif; ?>")
s = rep(s, "      <?php foreach ($badges as [$label, $cls]): ?><span class=\"lv-badge <?= $cls ?>\"><?= tv_e($label) ?></span><?php endforeach; ?>\n    </div>",
           "      <?php foreach ($badges as [$label, $cls]): ?><span class=\"lv-badge <?= $cls ?>\"><?= tv_e($label) ?></span><?php endforeach; ?>\n"
           "      <?php if ($conf): ?><div><?= tv_confidence_words($conf) ?></div><?php endif; // how sure we are, in words (B5; decisions/0133) ?>\n    </div>")
s = rep(s, "<?php // how sure we are (decisions/0133): the ring heads the actions column; How we know opens under the card",
           "<?php // how sure we are (decisions/0133), said in words under the details (B5); How we know opens under the card")
# the layout: the actions a row over the details, names on wide screens, icons on phones (B5 Option 1, card anatomy)
s = rep(s, "  /* actions: a column of round icons down the right edge, under the Link button; the details on the left */\n"
           "  .lv-card{padding-right:.55rem}\n"
           "  .lv-body{display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:.75rem;margin-top:.5rem;align-items:start}\n"
           "  .lv-acts{grid-column:2;grid-row:1;display:flex;flex-direction:column;gap:.3rem}\n"
           "  .lv-info{grid-column:1;grid-row:1}\n"
           "  .lv-a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;",
           "  /* actions: one row under the title, each named on a wide screen, round icons on a phone (B5 Option 1, Jason 2026-11-22);\n"
           "     the details under them, full width, so the card is only as tall as what it holds */\n"
           "  .lv-body{display:flex;flex-direction:column;gap:.55rem;margin-top:.5rem}\n"
           "  /* the actions in the ⋯ toolkit beside the Link, top right (Jason, 2026-11-22: \"Use link icon in right top corner and ... tool kit just to the left of it\") */\n"
           "  .lv-tk-b{cursor:pointer}\n"
           "  .lv-tk{position:absolute;right:.55rem;top:2.7rem;z-index:40;width:min(17rem,calc(100% - 1.1rem));background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;box-shadow:0 12px 28px rgba(42,27,18,.22);padding:.35rem}\n"
           "  .lv-tk[hidden]{display:none}.lv-tk-h{display:flex;align-items:center;gap:.5rem;padding:.2rem .3rem .35rem .45rem;border-bottom:1px solid var(--tv-line);margin-bottom:.25rem}\n"
           "  .lv-tk-h b{flex:1;min-width:0;font-size:.85rem;color:var(--tv-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}\n"
           "  .lv-tk-x{width:30px;height:30px;flex:none;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-ink);font-size:1.1rem;line-height:1;cursor:pointer}\n"
           "  .lv-tk .lv-acts{display:flex;flex-direction:column;gap:.1rem}\n"
           "  .lv-tk .lv-a{width:100%!important;height:auto!important;justify-content:flex-start;gap:.6rem;padding:.4rem .5rem!important;border:0;background:none;border-radius:.5rem;color:var(--tv-ink);font:inherit;text-align:left}\n"
           "  .lv-tk .lv-a:hover,.lv-tk .lv-a:focus-visible{background:var(--tv-hold-bg)}.lv-tk .lv-a svg{flex:none;color:var(--tv-icon,#B45309)}\n"
           "  .lv-al{font-size:.9rem;font-weight:600;white-space:nowrap;color:var(--tv-link)}\n"
           "  /* the photo capped lower on a listing (B5): the details are what's looked for */\n"
           "  #about.la{align-self:start}   /* About is as tall as its words, not stretched beside the side card (B5: the empty card) */\n"
           "  .xh.xh,.ta-work .xh.xh{aspect-ratio:auto;height:clamp(200px,32vh,340px);min-height:0}\n"
           "  .lv-a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;")
# real categories before "Other …" (B5): the group, then the named ones, then any "Other"
s = rep(s, "$alsoCats = ListingCategories::get($pdo, (int) $biz['id']);",
           "$alsoCats = ListingCategories::get($pdo, (int) $biz['id']);\n"
           "$isOther = fn ($n) => (bool) preg_match('/^other\\b/i', trim((string) $n));   // \"Other …\" says least: it goes last (B5, Jason 2026-11-22)\n"
           "usort($alsoCats, fn ($a, $b) => $isOther($a['name']) <=> $isOther($b['name']));\n"
           "$otherFirst = $isOther($biz['category']) && array_filter($alsoCats, fn ($c) => !$isOther($c['name']));")
s = rep(s, "    <button type=\"button\" class=\"lv-q\" data-q=\"<?= tv_e($biz['category'] . $in) ?>\" title=\"More <?= tv_e(mb_strtolower((string) $biz['category'])) ?><?= tv_e($in) ?>\"><?= tv_e($biz['category']) ?></button>",
           "    <?php $mainCat = '<button type=\"button\" class=\"lv-q\" data-q=\"' . tv_e($biz['category'] . $in) . '\" title=\"More ' . tv_e(mb_strtolower((string) $biz['category'])) . tv_e($in) . '\">' . tv_e($biz['category']) . '</button>';\n"
           "    if (!$otherFirst) echo $mainCat; ?>")
k = s.index("foreach ($alsoCats as $ac): ?>"); e = s.index("<?php endforeach; ?>", k) + len("<?php endforeach; ?>")
s = s[:e] + "<?php if ($otherFirst) echo '<span aria-hidden=\"true\">·</span>' . $mainCat; ?>" + s[e:]
# an About with nothing in it isn't drawn for visitors (B5: the empty card); its owner still sees it, with how to fill it
s = rep(s, "  <section class=\"sm:col-span-2 la tv-bleed\" id=\"about\">",
           "  <?php if ($desc !== '' || $addons || $isOwner): // nothing to say: no card (B5) ?>\n  <section class=\"sm:col-span-2 la tv-bleed\" id=\"about\">")
k = s.index('id="about"'); e = s.index("  </section>\n", k) + len("  </section>\n")
s = s[:e] + "  <?php endif; ?>\n" + s[e:]
s = rep(s, "<?php if ($conf) { require_once __DIR__ . '/../../app/lib/SuggestionActions.php';",
           "<script>(function () {   // the ⋯ toolkit: opens beside the Link; ×, Esc, a click outside or an action close it (CLAUDE.md: everything that pops open closes)\n"
           "  var b = document.querySelector('.lv-tk-b'), m = document.getElementById('lv-tk'); if (!b || !m) return;\n"
           "  function show(on) { m.hidden = !on; b.setAttribute('aria-expanded', on ? 'true' : 'false'); if (on) { var f = m.querySelector('.lv-a'); if (f) f.focus(); } }\n"
           "  b.addEventListener('click', function (e) { e.stopPropagation(); show(m.hidden); });\n"
           "  m.addEventListener('click', function (e) { if (e.target.closest('[data-tk-close]') || e.target.closest('.lv-a')) { show(false); if (e.target.closest('[data-tk-close]')) b.focus(); } });\n"
           "  document.addEventListener('click', function (e) { if (!m.hidden && !m.contains(e.target) && e.target !== b) show(false); });\n"
           "  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.hidden) { show(false); b.focus(); } });\n"
           "})();</script>\n"
           "<?php if ($conf) { require_once __DIR__ . '/../../app/lib/SuggestionActions.php';")
s = rep(s, '    <div class="lv-info">\n',
           '    <div class="lv-info">\n      <h2 class="lv-name lv-loc"><?= tv_e($biz[\'name\']) ?></h2><?php // its name heads the location block: name, address, town, phone (Jason, 2026-11-22) ?>\n')
s = rep(s, "  .lv-fix{font-size:.78rem;margin-top:.3rem}", "  .lv-fix{font-size:.78rem;margin-top:.3rem}\n  .lv-name.lv-loc{margin:0 0 .2rem;padding-right:0;font-size:1.15rem;line-height:1.25}")
# the categories under the contact details, as quiet words that still search (Jason, 2026-11-22: "the detail pills are distracting from the contact")
k0 = s.index("  <?php if ($biz['category']): ?>\n  <p class=\"lv-kick\">"); k1 = s.index("  <?php endif; ?>\n", k0) + len("  <?php endif; ?>\n")
kick = s[k0:k1]; s = s[:k0] + s[k1:]
kick = '\n'.join('  ' + l if l.strip() else l for l in kick.rstrip('\n').split('\n')) + '\n'
s = rep(s, "      <?php foreach ($badges as [$label, $cls]): ?>", kick + "      <?php foreach ($badges as [$label, $cls]): ?>")
s = rep(s, "  .lv-fix{font-size:.78rem;margin-top:.3rem}",
           "  .lv-fix{font-size:.78rem;margin-top:.3rem}\n"
           "  .lv-info .lv-kick{padding-right:0;margin:.45rem 0 0;gap:.1rem .35rem}.lv-info .lv-kick .lv-q{border:0;background:none;padding:0;font-size:.8rem;font-weight:500;color:var(--tv-meta);text-decoration:underline;text-decoration-color:rgba(94,79,60,.35);text-underline-offset:3px}\n"
           "  .lv-info .lv-kick .lv-q:hover{background:none;color:var(--tv-link);text-decoration-color:currentColor}.lv-info .lv-kick>span[aria-hidden]{display:inline;color:var(--tv-meta)}.lv-info .lv-kick>span[aria-hidden]:first-child{display:none}")
put(f, s)
# FAQ: the listing page as it now is (anchors kept; one new entry)
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "Tap **Wrong spot? Fix the pin** on the map card or the listing page.",
           "Tap **Wrong spot? Fix the pin** on the map card or the listing page (it shows where there's a pin to fix, so not on a PO box).")
s = rep(s, "How sure we are of the listing, out of 100. It's on the listing's page and on its card in the directory, guides, the map and search. Tap it to see how we know:",
           "How sure we are of the listing, out of 100. It's on its card in the directory, guides, the map and search; on the listing's own page it's said in words (\"How sure we are: Qualified\", with **How we know**). Tap it to see how we know:")
k = s.index("### What is the number in the circle on a listing?"); e = s.index("\n### ", k + 5)
s = s[:e] + """

### What's on a listing's page?
Its photo, then a card with, in its top-right corner, the **Link** button with **⋯** beside it. **⋯** opens what you can do: **Call**, **Email**, **Directions**, **Map**, **Website**, **Contact**, **Message** (or **Leave a message** when the business isn't on Traversence yet), **Save contact** and **QR code**; tap one, or ×, or anywhere outside to close it. The card leads with its name, address, town and phone, then its categories in small words (each one searches for more like it near there, with the most specific first and any "Other" last), who runs it or whether it's claimed, and **How sure we are**, which opens **How we know**. **Link** links you with the business (sign in first). **About** shows when there's something to say about it.""" + s[e:]
put(f, s)
print('built', sorted(os.path.relpath(os.path.join(dp, x), O) for dp, _, fs in os.walk(O) for x in fs))
