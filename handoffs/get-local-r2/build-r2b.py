#!/usr/bin/env python3
"""Get Local R2, part 2: the cards (Jason approved the cards-and-comments plan, "go ahead, build it", and on 2026-11-19
Change 2): Call and Directions as round buttons, then ⋯ with Email, Website and Peek in words, then Link; the score as a
chip beside the name keeping its ⓘ ("how sure we are, and why"; it still drives Best match); the status on the category
line; the list's numbers small and grey. A PO Box or a private address gets no Directions and says "Mailing address
only" (Jason: "why would m2 serv core have directions to it when it is only a mailbox?").
Replayable: build-r2b.py <root> with website/directory/index.php (after r2a) and website/js/tv-conf.js."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
D = 'website/directory/index.php'
patch(D, [
 ("      var tel = l.phone ? String(l.phone).replace(/[^\\d+]/g, '') : '';\n      var dir = l.address ? 'https://www.google.com/maps/dir/?api=1&destination='",
  "      var tel = l.phone ? String(l.phone).replace(/[^\\d+]/g, '') : '';\n      var street = hasStreet(l);   // a mailbox or a private address has no way to drive to it\n"
  "      var dir = street ? 'https://www.google.com/maps/dir/?api=1&destination='"),
 ("      var tel = l.tel ? String(l.tel).replace(/[^\\d+]/g, '') : '';\n      var dir = l.address ?",
  "      var tel = l.tel ? String(l.tel).replace(/[^\\d+]/g, '') : '';\n      var dir = hasStreet(l) ?"),
 ("        + '<div class=\"dr-hd\"><a class=\"dr-n\" href=\"/listing/view.php?id=' + l.id + '\">' + (((S.first || 1) - 1) * pg.per + i + 1) + '. ' + esc(l.business_name) + '</a>'",
  "        + '<div class=\"dr-hd\"><span class=\"dr-num\">' + (((S.first || 1) - 1) * pg.per + i + 1) + '.</span> <a class=\"dr-n\" href=\"/listing/view.php?id=' + l.id + '\">' + esc(l.business_name) + '</a>'"),
 ("        + (l.email ? '<a class=\"dr-a\" title=\"Email from your own mail app\" href=\"mailto:' + esc(l.email) + '\" data-out=\"email\" data-e=\"' + l.id + '\"><span>' + I.mail + '</span><b class=\"dr-al\">Email</b></a>' : '')   // its public email (decisions/0172 §3.3)\n",
  ""),
 ("        + (web ? '<a class=\"dr-a\" title=\"Website\" href=\"' + esc(web) + '\" target=\"_blank\" rel=\"noopener nofollow\" data-out=\"website\" data-e=\"' + l.id + '\"><span>' + I.web + '</span><b class=\"dr-al\">Website</b></a>' : '')\n"
  "        + '<button type=\"button\" class=\"dr-a\" title=\"Peek\" data-tv-peek=\"' + l.id + '\"><span>' + I.peek + '</span><b class=\"dr-al\">Peek</b></button>'\n",
  "        // the rest, in words, under ⋯ (Jason, 2026-11-19, Change 2): Email (its public email, decisions/0172 §3.3), Website, Peek\n"
  "        + '<span class=\"dr-mw\"><button type=\"button\" class=\"dr-a dr-mb\" aria-haspopup=\"menu\" aria-expanded=\"false\" title=\"More: email, website, peek\" aria-label=\"More for ' + esc(l.business_name) + '\">\\u22ef</button>'\n"
  "        + '<span class=\"dr-ml\" role=\"menu\" hidden><button type=\"button\" class=\"dr-mx\" aria-label=\"Close\" title=\"Close\">&times;</button>'\n"
  "        + (l.email ? '<a role=\"menuitem\" href=\"mailto:' + esc(l.email) + '\" data-out=\"email\" data-e=\"' + l.id + '\">' + I.mail + 'Email</a>' : '<span class=\"dr-mo\">' + I.mail + 'Email<small>No public email yet</small></span>')\n"
  "        + (web ? '<a role=\"menuitem\" href=\"' + esc(web) + '\" target=\"_blank\" rel=\"noopener nofollow\" data-out=\"website\" data-e=\"' + l.id + '\">' + I.web + 'Website</a>' : '<span class=\"dr-mo\">' + I.web + 'Website<small>No website yet</small></span>')\n"
  "        + '<button type=\"button\" role=\"menuitem\" data-tv-peek=\"' + l.id + '\">' + I.peek + 'Peek</button></span></span>'\n"),
 ("        + '<div class=\"dr-m\">' + esc([l.category, l.distance != null && p && p.point ? l.distance + ' mi' : ''].filter(Boolean).join(' · ')) + '</div>'\n",
  "        // the status shares the category line (\"Restaurants · Open 24 hours\"; \"Closed · Opens 10 AM\" fits the same way)\n"
  "        + '<div class=\"dr-m\">' + esc([l.category, l.distance != null && p && p.point ? l.distance + ' mi' : ''].filter(Boolean).join(' · '))\n"
  "          + (ht ? ' · <span class=\"' + (l.open_now ? 'dr-open' : 'dr-closed') + '\">' + esc(cut > 0 ? ht.slice(0, cut) : ht) + '</span>' + (cut > 0 ? ' · ' + esc(ht.slice(cut + 3)) : '') : '') + '</div>'\n"),
 ("          + (l.phone ? ' · ' + esc(l.phone) : '') + '</div>'\n"
  "        + (ht ? '<div class=\"dr-m\"><span class=\"' + (l.open_now ? 'dr-open' : 'dr-closed') + '\">' + esc(cut > 0 ? ht.slice(0, cut) : ht) + '</span>' + (cut > 0 ? ' · ' + esc(ht.slice(cut + 3)) : '') + '</div>' : '')\n",
  "          + (l.address && !street && !l.private_location ? ' <span class=\"dr-mail\">Mailing address only</span>' : '')\n"
  "          + (l.phone ? ' · ' + esc(l.phone) : '') + '</div>'\n"),
 ("  var I = {\n",
  "  // a street to drive to: not a PO Box (P.O. Box, PO BOX, POB, Box 12, PMB) and not a place kept private. One rule with\n"
  "  // app/lib/Address.php's isStreet(), so the card, the map's card and the listing's page agree (2026-11-19)\n"
  "  var MAILBOX = /^\\s*(p\\.?\\s*o\\.?\\s*box|post\\s+office\\s+box|pob\\b|box\\s+\\d|pmb\\b|private\\s+mail\\s*box)/i;\n"
  "  function hasStreet(l) { return !!(l && l.address && !l.private_location && !MAILBOX.test(String(l.address))); }\n"
  "  var I = {\n"),
 ("    var card = e.target.closest('.dr-it'); if (!card || e.target.closest('a,button,input,select,label,[data-link]')) return;",
  "    var card = e.target.closest('.dr-it'); if (!card || e.target.closest('a,button,input,select,label,[data-link],.dr-ml')) return;"),
 # CSS
 ("  .dr-al{position:absolute;", "  .dr-num{font:600 .8rem var(--tv-font-ui);color:var(--tv-meta);margin-right:.15rem}   /* the list's numbers: quiet until the map's pins use them */\n"
  "  .dr-mail{display:inline-block;margin-left:.2rem;font-size:.74rem;border:1px solid var(--tv-line-strong);border-radius:999px;padding:0 .45rem;color:var(--tv-meta)}\n"
  "  .dr-mw{position:relative;display:inline-flex}.dr-mb{font:700 1rem/1 system-ui,sans-serif;letter-spacing:.05em}\n"
  "  .dr-ml{position:absolute;right:0;top:calc(100% + .3rem);z-index:30;min-width:13rem;display:flex;flex-direction:column;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.6rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.35rem .3rem .3rem}\n"
  "  .dr-ml[hidden]{display:none}.dr-ml.up{top:auto;bottom:calc(100% + .3rem)}\n"
  "  .dr-ml a,.dr-ml button[role=menuitem],.dr-mo{display:flex;align-items:center;gap:.5rem;padding:.45rem .55rem;border:0;background:none;border-radius:.4rem;font:500 .92rem var(--tv-font-ui);color:var(--tv-ink);text-decoration:none;cursor:pointer;text-align:left}\n"
  "  .dr-ml a:hover,.dr-ml button[role=menuitem]:hover{background:var(--tv-sec,#EDE6D5)}.dr-ml svg{width:16px;height:16px;color:var(--tv-icon,#B45309);flex:none}\n"
  "  .dr-mo{color:var(--tv-meta);cursor:default;flex-wrap:wrap}.dr-mo small{flex-basis:100%;margin-left:1.55rem;font-size:.76rem}\n"
  "  .dr-mx{align-self:flex-end;width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);font-size:1rem;line-height:1;cursor:pointer;margin-bottom:.1rem}\n"
  "  .dr-hd .tv-conf-chip{margin-left:.35rem;vertical-align:.1rem}\n"
  "  .dr-al{position:absolute;"),
])
# ⋯ opens and closes (its ×, Esc, a click outside), opening upward near the window's bottom
p = os.path.join(R, D); s = open(p, encoding='utf-8').read()
a = "  // a street to drive to:"
s = s.replace(a, "  document.addEventListener('click', function (e) {   // the cards' ⋯: one open at a time; ×, Esc or a click outside close it\n"
  "    var b = e.target.closest('.dr-mb'), x = e.target.closest('.dr-mx');\n"
  "    document.querySelectorAll('.dr-ml:not([hidden])').forEach(function (m) { if (!b || m.previousSibling !== b) { m.hidden = true; m.previousSibling.setAttribute('aria-expanded', 'false'); } });\n"
  "    if (x) return;\n"
  "    if (b) { var m = b.nextSibling; m.hidden = !m.hidden; b.setAttribute('aria-expanded', String(!m.hidden));\n"
  "      if (!m.hidden) { m.classList.remove('up'); var r = m.getBoundingClientRect(); if (r.bottom > innerHeight - 8 && b.getBoundingClientRect().top - r.height > 8) m.classList.add('up'); } }\n"
  "  });\n"
  "  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.querySelectorAll('.dr-ml:not([hidden])').forEach(function (m) { m.hidden = true; m.previousSibling.setAttribute('aria-expanded', 'false'); m.previousSibling.focus(); }); });\n" + a, 1)
open(p, 'w', encoding='utf-8').write(s)
# the score: a chip beside the name on Get Local's cards, keeping its ⓘ; the ring elsewhere
patch('website/js/tv-conf.js', [
 ("    var a = ring(id, c), acts = host.querySelector('.dr-acts, .gd-acts, .tvm-acts');",
  "    var hd = host.querySelector('.dr-hd');\n"
  "    if (hd) { hd.appendChild(chip(id, c)); return; }   // Get Local's cards: a chip beside the name (Jason, 2026-11-19, Change 2)\n"
  "    var a = ring(id, c), acts = host.querySelector('.dr-acts, .gd-acts, .tvm-acts');"),
 ("  function place(host, id) {",
  "  function chip(id, c) {   // the score as a small chip: the number, its colour, and ⓘ to how we know\n"
  "    var a = document.createElement('a');\n"
  "    a.className = 'tv-conf tv-conf-chip'; a.href = '/listing/view.php?id=' + id + '#lv-know';\n"
  "    var say = 'How sure we are: ' + c.score + ' out of 100, ' + c.label + '. Show how we know.';\n"
  "    a.title = say; a.setAttribute('aria-label', say);\n"
  "    a.style.cssText = 'display:inline-flex;align-items:center;gap:.2rem;border:1px solid ' + c.colour + ';border-radius:999px;padding:0 .45rem;font:700 .76rem system-ui,sans-serif;color:#3E2B1F;text-decoration:none;background:#FFFBF2;line-height:1.5';\n"
  "    a.innerHTML = '<span aria-hidden=\"true\" style=\"width:.5rem;height:.5rem;border-radius:50%;background:' + c.colour + '\"></span>' + c.score + ' <span aria-hidden=\"true\" style=\"font-weight:400;opacity:.7\">\\u24d8</span>';\n"
  "    return a;\n"
  "  }\n"
  "  function place(host, id) {"),
])
print('built')
