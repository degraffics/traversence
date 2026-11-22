#!/usr/bin/env python3
"""Release 3e (design/css-theme-brand.md §4 Release 3): the Marketplace, checkout, storefronts and domains onto the tokens,
layouts kept. The layouts are Jason's own choices (decisions/0135 "The landing page, reviewed", 2026-11-03, and its
"Exceptions to the build standards", 2026-11-13), so nothing is moved: only colours. Each hex goes onto the token for what
it colours (recolor.py, 3d's), keeping its hex as the fallback; what it can't place stays and is listed.
Storefronts keep the business's own colours (handoffs/admin-design.md: "business colours kept, owner warned below 4.5:1"):
the one new behaviour is that warning in the business portal's Storefront page. trvgo.us's own pages (a code not found, a
GO Code's page, a proof, a storefront not found) draw no tokens, so their fallbacks carry the three levels' values
(brand.md §4's note, 2026-11-19). Reads the live mirror, so re-running it rebuilds on the newest sync."""
import os, re, shutil, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import recolor as R
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def mpath(rel): return M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1).replace('trvgo/', 'sync-trvgo/', 1)
def live(rel): return open(mpath(rel), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)

# 3e's additions to 3d's map: light words on the umber and the badges (--tv-on-umber), the gallery's dark strip (--tv-void),
# an error edge (--tv-fail)
R.TEXT.update({'FFFBF2': 'on-umber'}); R.SURF.update({'1E140C': 'void'}); R.LINE.update({'B91C1C': 'fail'})

report = {}
for f in ['website/market/index.php', 'website/market/view.php', 'website/market/item.php', 'website/market/cart.php',
          'website/market/order.php', 'website/market/rules.php', 'website/market/ticket.php', 'website/market/download.php',
          'website/shop/index.php', 'app/ui/market-ui.php', 'app/ui/domains-view.php']:
    r = {'mapped': 0, 'left': {}}; s = R.recolor(live(f), r); report[f] = r
    if s != live(f): put(f, s)

# ---- trvgo.us's own pages: no tokens there, so the fallbacks are the three levels (page, card, edge, ink, meta) ----
LEVEL = {'#F7EFDC': 'var(--tv-canvas,#E4DCC5)', '#FFFBF2': 'var(--tv-card-a,#F6F1E4)', '#EADFC4': 'var(--tv-card-edge,#E3DBC6)',
         '#3E2B1F': 'var(--tv-ink,#2A1D14)', '#8A7355': 'var(--tv-meta,#5E4F3C)', '#92400E': 'var(--tv-link,#92400E)'}
def levels(s, rep_):
    def one(m):
        css = m.group(0)
        for h, v in LEVEL.items():
            n = len(re.findall(re.escape(h) + r'\b', css, re.I)); rep_['mapped'] += n
            css = re.sub(re.escape(h) + r'\b', v, css, flags=re.I)
        css = re.sub(r'(\.card\{[^}]*background:)#fff\b', r'\1var(--tv-card-a,#F6F1E4)', css)   # the proof's details card
        for h in re.findall(r'(?<!,)#[0-9A-Fa-f]{6}\b|(?<!,)#[0-9A-Fa-f]{3}\b', css): rep_['left'][h.upper()] = rep_['left'].get(h.upper(), 0) + 1
        return css
    return re.sub(r"<style>.*?</style>", one, s, flags=re.S)
for f in ['trvgo/index.php', 'app/ui/storefront-view.php']:
    s = live(f); r = {'mapped': 0, 'left': {}}
    if f == 'app/ui/storefront-view.php':   # only its "storefront not found" page; the storefront itself is the business's theme
        i = s.index("<style>body{margin:0;min-height:100vh"); j = s.index('</style>', i) + 8
        s = s[:i] + levels(s[i:j], r) + s[j:]
    else: s = levels(s, r)
    report[f] = r; put(f, s)

# ---- the owner's warning: an accent under 4.5:1 on its theme's cards (admin-design.md; WCAG's text ratio, §3) ----
f = 'app/lib/Storefront.php'; s = live(f)
s = rep(s, "    /** WCAG contrast ratio of two #RRGGBB colors. */",
"""    /**
     * The owner's warning (Release 3e; handoffs/admin-design.md: "business colours kept, owner warned below 4.5:1"): an
     * accent that reads under 4.5:1 on its theme's cards (links and small words in it are hard to read) is kept, and said.
     * Under 3:1 save() refuses it, as before. '' when it reads well, or when it's blank (the theme's own accent).
     */
    public static function accentNote(string $accent, string $theme): string
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent) || !isset(self::THEMES[$theme])) return '';
        $r = self::contrast(strtoupper($accent), self::THEMES[$theme][2]);
        if ($r >= 4.5 || $r < 3.0) return '';
        return 'Your accent reads at ' . number_format(floor($r * 10) / 10, 1) . ':1 on the ' . self::THEMES[$theme][0] . ' theme\\'s cards. '
            . 'Links and small words in it can be hard to read; 4.5:1 or more reads well. A darker one fixes it, or keep it as it is.';
    }

    /** WCAG contrast ratio of two #RRGGBB colors. */""")
put(f, s)

f = 'app/business/storefront.php'; s = live(f)
# the line under the picker: said on load for the saved accent, and live while picking (the same rule in the browser)
s = rep(s, """ . '"' . ($canEdit ? '' : ' disabled') . '></span></label>'
        . '<label class="ck-l">Tagline""",
""" . '"' . ($canEdit ? '' : ' disabled') . '></span></label>'
        // the wrapper keeps its place when the kit draws the line as a notice (js/tv-ask.js); no ×: it goes when the accent reads well
        . '<div data-sf-acnote' . (($an = Storefront::accentNote((string) $sf['accent'], (string) $sf['theme'])) !== '' ? '' : ' hidden') . '><p data-tv-status="info" data-tv-keep data-tv-label="Hard to read:"><span data-sf-actext>' . $e($an) . '</span></p></div>'
        . '<label class="ck-l">Tagline""")
s = rep(s, """    if (pick) pick.addEventListener("input", function () { f.accent.value = pick.value.toUpperCase(); });""",
"""    if (pick) pick.addEventListener("input", function () { f.accent.value = pick.value.toUpperCase(); acNote(); });
    // the owner warning as they pick (the rule of Storefront::accentNote): under 4.5:1 on the theme cards is said, never refused
    var CARDS = ' . json_encode(array_map(fn ($t) => [$t[0], $t[2]], Storefront::THEMES)) . ';
    function lum(h) { return [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); })
      .reduce(function (a, v, i) { return a + v * [0.2126, 0.7152, 0.0722][i]; }, 0); }
    function acNote() {
      var p = f.querySelector("[data-sf-acnote]"), w = p && p.querySelector("[data-sf-actext]"); if (!w) return;
      var a = (f.accent.value || "").trim().toUpperCase(), th = f.querySelector("input[name=theme]:checked"), c = th && CARDS[th.value];
      if (!c || !/^#[0-9A-F]{6}$/.test(a)) { p.hidden = true; return; }
      var x = lum(a), y = lum(c[1]), r = (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
      if (r >= 4.5) { p.hidden = true; return; }
      w.textContent = r < 3 ? "Your accent reads at " + (Math.floor(r * 10) / 10).toFixed(1) + ":1 on the " + c[0] + " theme\\x27s cards, too close to read. Saving asks for a darker or brighter one."
        : "Your accent reads at " + (Math.floor(r * 10) / 10).toFixed(1) + ":1 on the " + c[0] + " theme\\x27s cards. Links and small words in it can be hard to read; 4.5:1 or more reads well. A darker one fixes it, or keep it as it is.";
      p.hidden = false;
    }
    f.accent.addEventListener("input", acNote);
    f.querySelectorAll("input[name=theme]").forEach(function (t) { t.addEventListener("change", acNote); });""")
s = rep(s, """            return 'Saved. Your storefront is trvgo.us/' . (!empty($sf['parent_slug']) ? $sf['parent_slug'] . '/' : '') . $sf['slug'] . '.';""",
"""            $an = Storefront::accentNote((string) $sf['accent'], (string) $sf['theme']);   // kept, and said (Release 3e)
            return 'Saved. Your storefront is trvgo.us/' . (!empty($sf['parent_slug']) ? $sf['parent_slug'] . '/' : '') . $sf['slug'] . '.' . ($an !== '' ? ' ' . $an : '');""")
put(f, s)

for f, r in report.items():
    print('%-34s mapped %3d  left %s' % (f, r['mapped'], dict(sorted(r['left'].items(), key=lambda x: -x[1]))))

# ---- the FAQ (decisions/0057): the one visitor-facing change, from the owner's view; add-only ----
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "### Can I put my storefront on my own domain?",
"""### Why does it say my accent color is hard to read?
Your accent color colors your storefront's buttons and links, which sit on your theme's cards. In **Storefront & pages**, under **Accent color**, a note says how well it reads there, as you pick it and whenever you open the page: under 4.5:1 (the level small words need to be easy to read), it says **Hard to read** with the number. It's a note, not a stop: you can keep the color and save, and the note stays until the color reads well. A darker accent (or a lighter one on the Night theme) fixes it. A color under 3:1 is too close to the cards to read at all, so saving asks for another.

### Can I put my storefront on my own domain?""")
put(f, s)
