#!/usr/bin/env python3
"""The vault's row actions on the table standard's §7 (Jason, 2026-11-20, T5's pop-up: "Follow the standard"): Copy
password stays the row's one visible button; Show the password for 10 seconds, Copy the user name, Change it and Delete it
go into ⋯ with words (the admin's ⋯ menu, .akt-rm, from T1). A greyed item says why (no user name saved). The menu closes
with its ×, Esc or a tap outside (CLAUDE.md); Delete keeps its confirm, the menu staying open until it's answered; near the
bottom of the window it opens upward. Reads the live mirror, so re-running it rebuilds on the newest sync."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)

f = 'website/js/tv-vault.js'; s = live(f)
s = rep(s, "        + '<div class=\"tvv-r\" role=\"cell\">' + ICON('copy', 'Copy the password', 'copy') + ICON('eye', 'Show the password for 10 seconds', 'show') + ICON('user', 'Copy the user name', 'user') + ICON('pen', 'Change it', 'edit') + ICON('trash', 'Delete it', 'del') + '</div>'",
           "        + '<div class=\"tvv-r\" role=\"cell\">' + ICON('copy', 'Copy the password', 'copy') + MORE(it) + '</div>'   // one button and ⋯ (table-standard §7; Jason, 2026-11-20: \"Follow the standard\")")
s = rep(s, "  function byId(id) {",
"""  // ⋯ for a login: the rest of its actions, in words (the admin's ⋯ menu from T1, .akt-rm); a greyed one says why
  function MITEM(n, t, act, off, danger) { return '<button type="button" role="menuitem" data-act="' + act + '"' + (off ? ' disabled' : '') + (danger ? ' class="tvv-mdel"' : '') + '><span class="akt-rmi" aria-hidden="true">' + (ICONS[n] || '') + '</span><span>' + esc(t) + (off ? '<small>' + esc(off) + '</small>' : '') + '</span></button>'; }
  function MORE(it) {
    return '<span class="akt-rm"><button type="button" class="akt-rmb" data-tvv-rm aria-haspopup="menu" aria-expanded="false" aria-label="More for ' + esc(it.name) + '" title="More">⋯</button>'
      + '<span class="akt-rml" role="menu" hidden><button type="button" class="tvv-mx" data-tvv-mx aria-label="Close" title="Close">×</button>'
      + MITEM('eye', 'Show the password for 10 seconds', 'show') + MITEM('user', 'Copy the user name', 'user', it.user ? '' : 'No user name saved.')
      + MITEM('pen', 'Change it', 'edit') + MITEM('trash', 'Delete it', 'del', '', true) + '</span></span>';
  }
  function menusShut() { root.querySelectorAll('#tvv-items .akt-rml:not([hidden])').forEach(function (l) { l.hidden = true; l.classList.remove('up'); l.previousSibling.setAttribute('aria-expanded', 'false'); }); }
  function menuToggle(b) {
    var l = b.nextSibling, open = l.hidden; menusShut(); if (!open) return;
    l.hidden = false; b.setAttribute('aria-expanded', 'true');
    if (l.getBoundingClientRect().bottom > innerHeight - 8) l.classList.add('up');   // near the bottom: it opens upward
    var first = l.querySelector('[role=menuitem]:not(:disabled)'); if (first) first.focus();
  }
  function byId(id) {""")
# the row's click: ⋯ opens and shuts; × shuts; an item shuts its menu (Delete only once its confirm is answered)
s = rep(s, "  async function onItem(e) {\n    var b = e.target.closest('button[data-act]'); if (!b) return;",
           "  async function onItem(e) {\n    var rm = e.target.closest('[data-tvv-rm]'); if (rm) return menuToggle(rm);\n    if (e.target.closest('[data-tvv-mx]')) return menusShut();\n    var b = e.target.closest('button[data-act]'); if (!b) return;\n    if (b.closest('.akt-rml') && b.getAttribute('data-act') !== 'del') menusShut();")
s = rep(s, "      if (!TvAsk.gate(b, 'Delete the login for ' + it.name + '?', { yes: 'Delete', danger: true })) return;\n",
           "      if (!TvAsk.gate(b, 'Delete the login for ' + it.name + '?', { yes: 'Delete', danger: true })) return;\n      menusShut();\n")
# Esc and a tap outside close it (CLAUDE.md: everything that pops open closes)
s = rep(s, "  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !warnBox && PANELS.some(",
           "  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.querySelector('#tvv-items .akt-rml:not([hidden])')) { e.stopImmediatePropagation(); menusShut(); } }, true);   // Esc shuts an open ⋯ first\n"
           "  document.addEventListener('click', function (e) { if (!e.target.closest('#tvv-items .akt-rm') && !e.target.closest('.tv-ask, [role=dialog], [role=alertdialog]')) menusShut(); });   // a tap outside shuts it (its own confirm isn't outside)\n"
           "  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !warnBox && PANELS.some(")
put(f, s)

f = 'app/ui/vault.php'; s = live(f)
s = rep(s, "#tvv .tvv-ib[aria-pressed=true]{",
           "#tvv .tvv-r .akt-rm{margin-left:0}#tvv .akt-rml{padding-top:1.9rem}#tvv .tvv-mx{position:absolute;top:.2rem;right:.3rem;width:28px;height:28px;border:0;background:none;color:var(--tv-meta,#5E4F3C);font:700 1.2rem/1 var(--tv-font-ui);cursor:pointer;border-radius:999px}#tvv .tvv-mx:hover{background:var(--tv-sec,#EDE6D5)}\n"
           "#tvv .akt-rml .tvv-mdel{color:var(--tv-crimson,#8C3A23)}#tvv .akt-rml .tvv-mdel .akt-rmi{color:var(--tv-crimson,#8C3A23)}   /* ⋯ for a login (table-standard §7): its ×; Delete in Crimson Thread, the remove colour (design 3a, 2026-11-18) */\n"
           "#tvv .tvv-ib[aria-pressed=true]{")
put(f, s)
f = 'app/ui/faq.md'; s = live(f)
k = "is always emailed at once."
i = s.index("Our own team has one now: the logins for the outside services we use are kept in a vault"); j = s.index(k, i) + len(k)
s = s[:j] + " In the list, each login has **Copy password** beside it; the rest (**Show the password for 10 seconds**, **Copy the user name**, **Change it** and **Delete it**) is under its **⋯** (2026-11-20)." + s[j:]
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
