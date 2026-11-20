#!/usr/bin/env python3
"""Pulse filters (design/table-standard.md, Jason's note of 2026-11-20 and its answers). Reads the live mirror, writes out/.
Replayable: run again on a newer sync to rebuild."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out'); SRC = os.path.join(S, 'src')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:100], s.count(a)); return s.replace(a, b)
src = lambda f: open(os.path.join(SRC, f), encoding='utf-8').read()

put('release.json', src('release.json'))
put('app/migrations/2026-11-20_pulse_views.sql', src('pulse-views.sql'))
put('app/lib/PulseViews.php', src('PulseViews.php'))
put('website/user/api/pulse-views.php', src('api-pulse-views.php'))

# the staging class (decisions/0165, R14): a person's own saved views are records
f = 'app/lib/staging-tables.php'; s = live(f)
s = rep(s, "'pulse_forwards' /* what a person passed on in Pulse (0172) */, ",
           "'pulse_forwards' /* what a person passed on in Pulse (0172) */, 'pulse_views' /* a person's saved views in Pulse (table-standard, 2026-11-20) */, ")
s = rep(s, "'pulse_forwards.user_id' => 'users', ", "'pulse_forwards.user_id' => 'users', 'pulse_views.user_id' => 'users', ")
put(f, s)

f = 'app/ui/pulse-ui.php'; s = live(f)
# --- the header's four icons: their words beside them on a wide screen (note 6)
s = rep(s, "aria-label=\"Critical, overdue, and escalated to you\"><?= tv_icon('bell', 'pu-svg') ?>",
           "aria-label=\"Critical, overdue, and escalated to you\"><?= tv_icon('bell', 'pu-svg') ?><span class=\"pb-icw\" aria-hidden=\"true\">The bell</span>")
s = rep(s, "<summary class=\"pb-ic\" title=\"Your day\" aria-label=\"Your day\"><?= tv_icon('calendar', 'pu-svg') ?></summary>",
           "<summary class=\"pb-ic\" title=\"Your day\" aria-label=\"Your day\"><?= tv_icon('calendar', 'pu-svg') ?><span class=\"pb-icw\" aria-hidden=\"true\">Your day</span></summary>")
s = rep(s, "<circle cx=\"19\" cy=\"12\" r=\"1.6\" fill=\"currentColor\"/></svg></summary>\n        <div class=\"pb-popc pb-qa\">",
           "<circle cx=\"19\" cy=\"12\" r=\"1.6\" fill=\"currentColor\"/></svg><span class=\"pb-icw\" aria-hidden=\"true\">Quick actions</span></summary>\n        <div class=\"pb-popc pb-qa\">")
s = rep(s, "<circle cx=\"18\" cy=\"18\" r=\"2\"/></svg></a>\n    </div>\n  </div>",
           "<circle cx=\"18\" cy=\"18\" r=\"2\"/></svg><span class=\"pb-icw\" aria-hidden=\"true\">Settings</span></a>\n    </div>\n  </div>")
# --- one filter button: lists, Show, Senders, Levels, Group by, saved views; Group ▾ folds into it (note 1)
s = rep(s, "      <?php if (array_filter($extra, fn ($x) => $x['kind'] === 'mail')): ?><button type=\"button\" class=\"pb-chip\" data-chip=\"mail\" aria-pressed=\"false\" title=\"The mail of the mailboxes you read (Dashboard → My inbox shows the same)\">Mail (<b><?= (int) $mailN ?></b>)</button><?php endif; ?>\n        </div></div></details>\n",
           "      <?php if (array_filter($extra, fn ($x) => $x['kind'] === 'mail')): ?><button type=\"button\" class=\"pb-chip\" data-chip=\"mail\" aria-pressed=\"false\" title=\"The mail of the mailboxes you read (Dashboard → My inbox shows the same)\">Mail (<b><?= (int) $mailN ?></b>)</button><?php endif; ?>\n        </div>\n"
           "        <p class=\"pb-fddh\">Senders</p><div class=\"pb-ticks\" id=\"pb-from\" role=\"group\" aria-label=\"Senders\"></div>\n"
           "        <p class=\"pb-fddh\">Levels</p><div class=\"pb-ticks\" id=\"pb-sev\" role=\"group\" aria-label=\"Levels\"></div>\n"
           "        <p class=\"pb-fddh\">Group by</p><div class=\"pb-gby\" role=\"radiogroup\" aria-label=\"Group by\"><button type=\"button\" class=\"pb-chip\" role=\"radio\" data-gb=\"\">None</button><button type=\"button\" class=\"pb-chip\" role=\"radio\" data-gb=\"from\">Sender</button><button type=\"button\" class=\"pb-chip\" role=\"radio\" data-gb=\"src\">Kind</button><button type=\"button\" class=\"pb-chip\" role=\"radio\" data-gb=\"sev\">Level</button></div>\n"
           "        <p class=\"pb-fddh\">Saved views</p><div class=\"pb-saved\" id=\"pb-saved\"></div>\n"
           "        <form class=\"pb-svf\" id=\"pb-svf\"><label class=\"sr-only\" for=\"pb-svn\">Name this view</label><input id=\"pb-svn\" maxlength=\"60\" placeholder=\"Name this view\" autocomplete=\"off\" data-spell><button type=\"submit\" class=\"pb-b2\">Save</button></form>\n"
           "      </div></details>\n    <span class=\"pb-tags\" id=\"pb-tags\" aria-live=\"polite\"></span>\n"
           "    <span class=\"ps-gbar\" id=\"ps-gbar\" hidden><button type=\"button\" class=\"pb-all\" data-gall=\"1\">Open all</button><button type=\"button\" class=\"pb-all\" data-gall=\"0\">Close all</button></span>\n")
a = s.index("    <details class=\"pb-fdd pb-gdd\" id=\"pb-gdd\">"); b = s.index("</div></details>\n", a) + len("</div></details>\n")
s = s[:a] + s[b:]
# --- no filter controls in the column headers (§4): the From and level funnels go, into the button
s = rep(s, "<button type=\"button\" class=\"ps-flt\" data-filter=\"sev\" title=\"Show only some levels\" aria-label=\"Filter by level\" aria-pressed=\"false\"><svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M3 5h18l-7 8v6l-4-2v-4z\"/></svg></button>", "")
s = rep(s, "<button type=\"button\" class=\"ps-flt\" data-filter=\"from\" title=\"Show only some senders\" aria-label=\"Filter by sender\" aria-pressed=\"false\"><svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M3 5h18l-7 8v6l-4-2v-4z\"/></svg></button>", "")
s = rep(s, "role=\"group\" aria-label=\"Sort and filter the stream\">", "role=\"group\" aria-label=\"Sort the stream\">")
# --- CSS
s = rep(s, "  .pb-gddc{display:flex;flex-direction:column;width:auto;min-width:11rem}.pb-gddc button{border:0;background:none;text-align:left;padding:.42rem .55rem;border-radius:.4rem;font:500 .92rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer}\n"
           "  .pb-gddc button:hover{background:var(--tv-sec,#EDE6D5)}.pb-gddc button[aria-checked=true]{font-weight:700;color:var(--tv-link)}.pb-gddc button[aria-checked=true]::before{content:'✓ '}\n",
           "  /* the one filter button's panel (table-standard note, 2026-11-20): ticks that apply at once, tags with ×, saved views */\n"
           "  .pb-row2 .pb-fddc{max-height:min(75vh,40rem);overflow:auto}\n"
           "  .pb-ticks,.pb-gby,.pb-saved{display:flex;flex-wrap:wrap;gap:.3rem;margin:0 0 .55rem}.pb-ticks{max-height:11rem;overflow:auto}\n"
           "  .pb-tick{display:inline-flex;align-items:center;gap:.35rem;border:1px solid #CBB98D;background:var(--cr);border-radius:999px;padding:.25rem .65rem .25rem .5rem;font:600 .86rem var(--tv-font-ui);color:var(--b);cursor:pointer}\n"
           "  .pb-tick input{margin:0;accent-color:#2F6B55}.pb-tick b{font-weight:700;color:var(--tv-meta)}.pb-tick.none{color:var(--tv-meta);border-style:dashed}\n"
           "  .pb-gby .pb-chip[aria-checked=true]{background:#F0B57A;border-color:#E39A52;color:#3E2B1F}\n"
           "  .pb-all{border:0;background:none;color:var(--tv-link);font:600 .84rem var(--tv-font-ui);text-decoration:underline;cursor:pointer;padding:.25rem .35rem}\n"
           "  .pb-tags{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;min-width:0}.pb-tags:empty{display:none}\n"
           "  .pb-tag{display:inline-flex;align-items:center;gap:.15rem;border:1px solid #8FBCA8;background:#EAF4EF;color:#1F3B30;border-radius:999px;padding:.12rem .2rem .12rem .65rem;font:600 .84rem var(--tv-font-ui);max-width:100%}\n"
           "  .pb-tag button{border:0;background:none;width:26px;height:26px;border-radius:999px;font-size:1.05rem;line-height:1;cursor:pointer;color:inherit;flex:none}.pb-tag button:hover{background:#CFE5DA}\n"
           "  .pb-sv{display:inline-flex;align-items:center}.pb-svx{border:0;background:none;width:28px;height:28px;border-radius:999px;cursor:pointer;color:var(--tv-meta);font-size:1.1rem;line-height:1}.pb-svx:hover{color:var(--tv-link)}\n"
           "  .pb-svn{margin:0;font-size:.85rem;color:var(--tv-meta)}.pb-svgone{gap:.3rem;font-size:.86rem;color:var(--tv-meta)}.pb-svgone button{border:0;background:none;color:var(--tv-link);text-decoration:underline;font:600 .86rem var(--tv-font-ui);cursor:pointer;padding:0}\n"
           "  .pb-svf{display:flex;gap:.35rem}.pb-svf[hidden]{display:none}.pb-svf input{flex:1;min-width:0;border:1px solid var(--tv-field-edge,#8D7E68);background:var(--tv-field,#FFFBF2);border-radius:999px;padding:.35rem .75rem;font:inherit;font-size:.92rem}\n"
           "  .ps-gbar{display:flex;gap:.15rem;margin-left:auto}.ps-gbar[hidden]{display:none}\n"
           "  .pb-icw{display:none}@media (min-width:768px){.pb-tools .pb-ic{width:auto;padding:0 .8rem 0 .65rem;gap:.35rem}.pb-tools .pb-icw{display:inline;font:600 .86rem var(--tv-font-ui);color:var(--tv-ink)}.pb-tools .pb-ic[aria-pressed=true] .pb-icw{color:#1F3B30}}   /* words beside the icons (note 6); icons only on a phone */\n")
s = rep(s, "  .ps-sort,.ps-flt{border:0;", "  .ps-sort{border:0;")
s = rep(s, "  .ps-flt svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2.2;vertical-align:-1px}\n", "")
s = rep(s, "  .ps-flt[aria-pressed=true]{background:#8FBCA8;color:#1F3B30;border-radius:999px}\n", "")
# --- JS: the filters as one predicate, so the panel can count within the other filters (note 2)
s = rep(s, "  function apply() {\n    var n = 0;\n    rows.forEach(function (r) {\n      var s = r.dataset, open = s.state !== 'done', show;\n",
           "  function shows(r, skip) {   // does a row pass the filters? skip names one ('from' or 'sev') left out, for the panel's counts\n"
           "      var s = r.dataset, open = s.state !== 'done', show;\n")
s = rep(s, "      if (typeof T !== 'undefined' && T) { if (T.from.length && T.from.indexOf(s.from) < 0) show = false; if (T.sev.length && T.sev.indexOf(s.sev) < 0) show = false; }   // the header's filters\n",
           "      if (typeof T !== 'undefined' && T) { if (skip !== 'from' && T.from.length && T.from.indexOf(s.from) < 0) show = false; if (skip !== 'sev' && T.sev.length && T.sev.indexOf(s.sev) < 0) show = false; }   // Senders and Levels\n")
s = rep(s, "      else if (MM.view.indexOf('folder:') === 0) show = mk.folder === MM.view.slice(7);\n      r.hidden = !show; if (show) n++;\n",
           "      else if (MM.view.indexOf('folder:') === 0) show = mk.folder === MM.view.slice(7);\n      return show;\n  }\n"
           "  function apply() {\n    var n = 0;\n    rows.forEach(function (r) {\n      var show = shows(r, '');\n      r.hidden = !show; if (show) n++;\n")
# a click inside the panel that redraws it (Undo, a tick) isn't a click outside it
s = rep(s, "document.querySelectorAll('.pb-pop[open], .pb-fdd[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); }); });",
           "document.querySelectorAll('.pb-pop[open], .pb-fdd[open]').forEach(function (d) { if (e.target.isConnected && !d.contains(e.target)) d.removeAttribute('open'); }); });")
# Group by: in the one panel now; a group of 3 rows or fewer opens by itself; what's opened or closed is remembered
s = rep(s, "    var lab = document.querySelector('[data-gb-lab]'); if (lab) lab.textContent = 'Group: ' + ({ '': 'None', from: 'Sender', src: 'Kind', sev: 'Level' })[GB];\n"
           "    document.querySelectorAll('[data-gb]').forEach(function (b) { b.setAttribute('aria-checked', b.dataset.gb === GB ? 'true' : 'false'); });\n"
           "    document.getElementById('pb-gdd').classList.toggle('on', !!GB);\n", "")
s = rep(s, "      var members = by[k], shown = members.filter(function (r) { return !r.hidden; }); if (!shown.length) return;\n"
           "      var h = document.createElement('div'); h.className = 'ps-row ps-ghead'; h.dataset.gkey = k; h.setAttribute('aria-expanded', GO[GB + ':' + k] ? 'true' : 'false');\n"
           "      h.innerHTML = '<button type=\"button\" class=\"ps-gb\" aria-expanded=\"' + (GO[GB + ':' + k] ? 'true' : 'false') + '\">",
           "      var members = by[k], shown = members.filter(function (r) { return !r.hidden; }); if (!shown.length) return;\n"
           "      var op = GO[GB + ':' + k]; if (op === undefined) op = shown.length <= 3;   // 3 rows or fewer opens by itself (Jason, 2026-11-20)\n"
           "      var h = document.createElement('div'); h.className = 'ps-row ps-ghead'; h.dataset.gkey = k; h.setAttribute('aria-expanded', op ? 'true' : 'false');\n"
           "      h.innerHTML = '<button type=\"button\" class=\"ps-gb\" aria-expanded=\"' + (op ? 'true' : 'false') + '\">")
s = rep(s, "        r.setAttribute('data-gm', k); if (!GO[GB + ':' + k]) {", "        r.setAttribute('data-gm', k); if (!op) {")
s = rep(s, "    var h = e.target.closest('.ps-ghead'); if (!h) return; var k = GB + ':' + h.dataset.gkey; GO[k] = !GO[k]; apply();",
           "    var h = e.target.closest('.ps-ghead'); if (!h) return; var k = GB + ':' + h.dataset.gkey; GO[k] = h.getAttribute('aria-expanded') !== 'true'; saveGroups(); apply();")
s = rep(s, "  document.getElementById('pb-gdd').addEventListener('click', function (e) {\n"
           "    var b = e.target.closest('[data-gb]'); if (!b) return; GB = b.dataset.gb; GO = {}; try { localStorage.setItem('pb_group', GB); } catch (x) {} this.removeAttribute('open'); apply();\n  });\n", "")
# a choice in the panel no longer closes it: the filters combine (note 1); ×, Esc or a click outside closes it (note 3)
s = rep(s, "  document.querySelectorAll('[data-chip]').forEach(function (c) { c.addEventListener('click', function () { var d = document.getElementById('pb-fdd'); if (d) d.removeAttribute('open'); }); });\n", "")
s = rep(s, "    M.view = b.dataset.view; try { localStorage.setItem('pb_view', M.view); } catch (x) {} paintMarks(); apply(); var fd = document.getElementById('pb-fdd'); if (fd) fd.removeAttribute('open');\n",
           "    M.view = b.dataset.view; try { localStorage.setItem('pb_view', M.view); } catch (x) {} paintMarks(); apply();\n")
s = rep(s, "    var on = !G[r.dataset.key]; G[r.dataset.key] = on; r.setAttribute('aria-expanded', String(on));\n    var oi = r.querySelector('[data-ia=\"open\"]'); if (oi) oi.setAttribute('aria-expanded', String(on));\n    apply();\n",
           "    var on = !G[r.dataset.key]; G[r.dataset.key] = on; r.setAttribute('aria-expanded', String(on));\n    var oi = r.querySelector('[data-ia=\"open\"]'); if (oi) oi.setAttribute('aria-expanded', String(on));\n    saveGroups(); apply();\n")
# the header's funnels and TvAsk.choose's "Show these …" box go (note 1); sorting stays on the headers
s = rep(s, "  // the stream as a table (Jason, 2026-11-18): tap a header to sort, its ▾ to filter, drag a header's edge to resize;\n",
           "  // the stream as a table (Jason, 2026-11-18): tap a header to sort, drag a header's edge to resize (its filters are in\n  // the one filter button since 2026-11-20);\n")
a = s.index("  function paintFilters() {"); b = s.index("\n", a) + 1; s = s[:a] + s[b:]   # replaced in place by filters.js's paintFilters
a = s.index("    var fb = e.target.closest('[data-filter]'); if (!fb) return;"); b = s.index("    });\n  });\n", a) + len("    });\n  });\n")
s = s[:a] + "  });\n" + s[b:]
s = rep(s, "  paintFilters(); sortRows(); apply();\n", src('filters.js') + "  sortRows(); apply();\n")
assert 'pb-gdd' not in s and 'ps-flt' not in s and s.count('function paintFilters') == 1 and 'Show these' not in s
put(f, s)

# the FAQ: Pulse's entry updated in place (its anchor kept)
f = 'app/ui/faq.md'; s = live(f)
s = rep(s, "Tap a column's name to sort by it (again to reverse, a third time for the stream's own order), its ▾ to show only some senders or levels (every one starts ticked; untick the ones you don't want), and drag",
           "Tap a column's name to sort by it (again to reverse, a third time for the stream's own order), and drag")
s = rep(s, "**⋯** for Quick actions, and the stream's settings (the sliders), which open in a panel over Pulse.",
           "**⋯** for Quick actions, and the stream's settings (the sliders), which open in a panel over Pulse; on a wide screen each says its name beside its icon.")
s = rep(s, "tap it for your lists (Inbox, Pinned, Follow up, Archived, your folders) and **Show**: **Mine** (given to you) and **Resolved** (closed); the funnels on **From** and the level, and **Group ▾**, sort out the rest;",
           "tap it for your lists (Inbox, Pinned, Follow up, Archived, your folders), **Show** (**Mine**, given to you; **Resolved**, closed; and the stream's other views), **Senders** and **Levels** (each with how many your other choices leave, a greyed 0 for none; untick the ones you don't want), **Group by**, and **Saved views**. Each choice applies at once and the panel stays open, so you can combine them; what's on shows as tags beside the button, each with × to take it off (**Clear all** for every one). Name a mix under **Saved views** (\"Needs me now\") and it comes back with one tap on any device you sign in on; × removes one, with Undo;")
s = rep(s, "Each column heading shows its sort arrow (faint until you sort by it; tap again to turn it round), and **From** and the level have a funnel to show only some.",
           "Each column heading shows its sort arrow (faint until you sort by it; tap again to turn it round).")
s = rep(s, "**Group ▾** gathers the rows under one heading each, by **Sender**, **Kind** (Support, Fulfillment, System…) or **Level**, with how many; tap a heading to open its rows there, and **None** for the plain list.",
           "**Group by** gathers the rows under one heading each, by **Sender**, **Kind** (Support, Fulfillment, System…) or **Level**, with how many; a group of 3 rows or fewer opens by itself, a tap on a heading opens or closes it (remembered on that device), **Open all** and **Close all** sit beside the filter button, and **None** is the plain list.")
put(f, s)
print('built', sorted(os.path.relpath(os.path.join(dp, x), O) for dp, _, fs in os.walk(O) for x in fs))
