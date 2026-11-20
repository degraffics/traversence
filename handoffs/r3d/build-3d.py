#!/usr/bin/env python3
"""Release 3d (design/css-theme-brand.md §4 Release 3): Community and the member dashboard. /community/ to Jason's mockup,
adapted to his newer directive (Jason, 2026-11-20, 3d's pop-up: "Mockup, adapted"; community-scope.md's note): Start a
group top right, one search with a topic dropdown (the topics, in place of chips) and a sort, popular tags as words,
cards on the card colour with the topic's icon, "+N this week" (a count of joins in 7 days, never who: 0058 §13), the
about, members and Join group (to the group's own "What you're agreeing to", decisions/0137 §3: joining is consent to the
charter). Every behaviour of the page before is kept. Reads the live mirror, so re-running it rebuilds on the newest sync."""
import os, re, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)
def between(s, a, b, new):
    i = s.index(a); j = s.index(b, i) + len(b); return s[:i] + new + s[j:]

# ---- the directory: each group's joins in the last 7 days, a count only (0058 §13) ----
f = 'app/lib/Community.php'; s = live(f)
s = rep(s, "        $out = [];\n        foreach ($rows as $g) {\n            $id = (int) $g['id']; $pk = (string) ($g['place_key'] ?? '');",
           "        $week = [];   // joins in the last 7 days, a count only, never who (decisions/0058 §13): \"+N this week\" on /community/'s cards\n"
           "        if ($ids) { try { $st = $pdo->prepare(\"SELECT group_id, COUNT(*) AS n FROM community_group_members WHERE status = 'active' AND joined_at >= ? AND group_id IN (\" . implode(',', $ids) . ') GROUP BY group_id');\n"
           "            $st->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]); foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $week[(int) $r['group_id']] = (int) $r['n']; } catch (Throwable $e) {} }\n"
           "        $out = [];\n        foreach ($rows as $g) {\n            $id = (int) $g['id']; $pk = (string) ($g['place_key'] ?? '');")
s = rep(s, "'join_mode' => (string) $g['join_mode'], 'members' => (int) $g['members'], 'local' => $pk !== '',",
           "'join_mode' => (string) $g['join_mode'], 'members' => (int) $g['members'], 'week' => $week[$id] ?? 0, 'local' => $pk !== '',")
put(f, s)

# ---- /community/ ----
f = 'website/community/index.php'; s = live(f)
s = between(s, "<style>", "</style>", """<style>
  /* /community/ to Jason's mockup, adapted (2026-11-20): on the tokens and the three levels (brand.md §4) */
  .cg-top{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:.75rem 1rem}
  .cg-top h1{font:700 clamp(1.6rem,3.2vw,2.2rem)/1.2 var(--tv-font-display,var(--tv-font-head));color:var(--tv-ink);margin:0}
  .cg-top p{color:var(--tv-meta);margin:.3rem 0 0;max-width:46rem}
  .cg-bar{background:var(--tv-sec);border:1px solid var(--tv-sec-edge,var(--tv-line));border-radius:var(--tv-r);padding:.75rem;display:grid;gap:.55rem}
  .cg-find{display:flex;flex-wrap:wrap;gap:.5rem}.cg-find .cg-q{flex:1 1 16rem;min-width:0;position:relative}
  .cg-find .cg-q svg{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--tv-meta)}.cg-find .cg-q input{padding-left:2.2rem}
  .cg-in{width:100%;border:1px solid var(--tv-field-edge,var(--tv-line-strong));border-radius:.6rem;padding:.5rem .7rem;background:var(--tv-field,var(--tv-card-a));color:var(--tv-ink);font-size:.92rem;box-sizing:border-box}
  .cg-find select.cg-in{width:auto;flex:0 1 12rem}
  .cg-b{display:inline-flex;align-items:center;gap:.35rem;font-size:.88rem;font-weight:600;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:.6rem;padding:.45rem .9rem;cursor:pointer;white-space:nowrap;text-decoration:none}
  .cg-b:hover{border-color:var(--tv-link);color:var(--tv-link)}
  .cg-b.go{background:var(--tv-btn);border-color:var(--tv-btn);color:var(--tv-btn-text)}.cg-b.go:hover{background:var(--tv-btn-hover);color:var(--tv-btn-text)}
  .cg-b svg{width:16px;height:16px}
  .cg-pop{font-size:.85rem;color:var(--tv-meta);display:flex;flex-wrap:wrap;gap:.2rem .7rem;align-items:baseline}.cg-pop:empty{display:none}
  .cg-tag{border:0;background:none;padding:0;font:inherit;color:var(--tv-link);text-decoration:underline;cursor:pointer}
  .cg-hd{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin:1.1rem 0 .5rem}
  .cg-h{font:700 1.2rem var(--tv-font-head);color:var(--tv-ink);margin:0;display:flex;align-items:center;gap:.45rem}.cg-h svg{width:20px;height:20px;color:var(--tv-icon,#B45309)}
  .cg-n{font-size:.8rem;font-weight:700;color:var(--tv-link);background:color-mix(in srgb,var(--tv-link) 10%,var(--tv-card-a));border-radius:999px;padding:.15rem .6rem;white-space:nowrap}
  .cg-s{font-size:.8rem;color:var(--tv-meta)}
  .cg-list{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fill,minmax(17rem,1fr))}
  .cg-g{display:flex;flex-direction:column;gap:.45rem;background:var(--tv-card-a);border:1px solid var(--tv-card-edge,var(--tv-line));border-radius:var(--tv-r);padding:1rem 1.05rem;color:var(--tv-ink);box-shadow:var(--tv-shadow)}
  .cg-g:hover{border-color:var(--tv-line-strong)}
  .cg-gt{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}
  .cg-ic{width:42px;height:42px;border-radius:.6rem;background:var(--tv-sec);border:1px solid var(--tv-line);display:grid;place-items:center;color:var(--tv-icon,#B45309)}.cg-ic svg{width:22px;height:22px}
  .cg-wk{font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:color-mix(in srgb,var(--tv-moss,#6B7F62) 60%,var(--tv-ink));background:color-mix(in srgb,var(--tv-sage,#92BEA5) 28%,var(--tv-card-a));border-radius:.35rem;padding:.15rem .45rem;white-space:nowrap}
  .cg-gn{font:700 1.08rem/1.3 var(--tv-font-head);color:var(--tv-link);text-decoration:underline}
  .cg-ab{font-size:.88rem;color:var(--tv-ink);margin:0}
  .cg-gf{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:auto;padding-top:.6rem;border-top:1px solid var(--tv-line)}
  .cg-gf .cg-s{display:inline-flex;align-items:center;gap:.3rem}.cg-gf .cg-s svg{width:16px;height:16px}
  .cg-card{background:var(--tv-sec);border:1px solid var(--tv-sec-edge,var(--tv-line));border-radius:var(--tv-r);padding:1rem 1.1rem}
  #cg-mine .cg-b{max-width:100%;overflow:hidden;text-overflow:ellipsis;vertical-align:top}
  @media (max-width:767px){.cg-find select.cg-in{flex:1 1 9rem}.cg-top .cg-b.go{width:100%;justify-content:center}}
</style>""")
s = rep(s, "tv_page_start('Community groups', ['no_crumbs' => true]);   // no breadcrumbs (decisions/0058 §11)\n?>",
           "tv_page_start('Community groups', ['no_crumbs' => true]);   // no breadcrumbs (decisions/0058 §11)\n"
           "// each topic's brand icon on its cards (the mockup's topic icon; tv_icon(), never emoji)\n"
           "$cgIcons = ['' => tv_icon('users')]; foreach (['outdoors' => 'tree', 'arts' => 'pen', 'faith' => 'landmark', 'family' => 'users', 'health' => 'heart', 'food' => 'utensils', 'history' => 'book', 'causes' => 'sparkle', 'business' => 'store', 'hobbies' => 'play', 'other' => 'users'] as $k => $n) $cgIcons[$k] = tv_icon($n);\n"
           "$cgUi = ['users' => tv_icon('users'), 'search' => tv_icon('search'), 'plus' => tv_icon('plus'), 'trend' => tv_icon('chart')];\n?>")
s = between(s, '<div class="max-w-4xl space-y-4">', '<div id="cg-all"><p class="text-sm">Loading…</p></div>',
"""<div class="max-w-5xl space-y-4">
  <div class="cg-top">
    <div>
      <h1>Community groups</h1>
      <p class="text-sm">Groups for any interest, pastime or cause, and local groups for a town or area. Anyone can find a group; only members see its posts and who belongs. Joining lets that group's me""" + re.search(r"Joining lets that group's me(.*?)</p>", live(f), re.S).group(1) + """</p>
    </div>
    <a class="cg-b go" href="#cg-new" data-cg-start><?= $cgUi['plus'] ?>Start a group</a>
  </div>
  <p id="cg-here" class="text-sm"></p>
  <section class="cg-bar tv-bleed" aria-label="Find a group">
    <form id="cg-find" class="cg-find" role="search">
      <label class="cg-q"><?= $cgUi['search'] ?><input class="cg-in" name="q" placeholder="Find a group by name, topic or place" aria-label="Find a group" data-spell></label>
      <select class="cg-in" id="cg-topic" aria-label="Topic"><option value="">All topics</option></select>
      <select class="cg-in" id="cg-sort" aria-label="Sort"></select>
      <button class="cg-b" type="submit">Find</button>
    </form>
    <div id="cg-pop" class="cg-pop"></div>
  </section>
  <div id="cg-mine"></div>
  <div id="cg-all"><p class="text-sm">Loading…</p></div>""")
# the start form: its headings and words on the tokens (Tailwind's literal colours out)
s = s.replace('text-[#3E2B1F]', 'text-[color:var(--tv-ink)]').replace('text-[#5C4632]', 'text-[color:var(--tv-meta)]')
s = rep(s, '<a class="text-amber-800 font-semibold" href="/login.php">Sign in</a>', '<a class="font-semibold underline text-[color:var(--tv-link)]" href="/login.php">Sign in</a>')
s = rep(s, "'Groups in <a class=\"font-semibold text-amber-800 hover:underline\" href=\"'", "'Groups in <a class=\"font-semibold underline text-[color:var(--tv-link)]\" href=\"'")
# ---- the script ----
s = rep(s, "  var qs = new URLSearchParams(location.search), API = '/user/api/community.php', csrf = null, place = qs.get('place') || '', topic = qs.get('topic') || '', lastQ = '', started = false;",
           "  var qs = new URLSearchParams(location.search), API = '/user/api/community.php', csrf = null, place = qs.get('place') || '', topic = qs.get('topic') || '', lastQ = '', started = false;\n"
           "  var ICONS = <?= json_encode($cgIcons) ?>, UI = <?= json_encode($cgUi) ?>, last = null, mineIds = {};\n"
           "  // the sort (the mockup's \"Most active\"): joins in the last 7 days, then members. The order the list had before stays a choice:\n"
           "  // nearest first with a place, most members without one\n"
           "  var sort = qs.get('sort') || (place ? 'near' : 'active');")
s = between(s, "  function card(g) {", "  }\n  function load(q) {",
"""  function card(g) {
    var url = '/community/group.php?id=' + g.id + '&src=' + (place ? 'place' : 'search'), mine = mineIds[g.id];
    return '<article class="cg-g tv-bleed"><div class="cg-gt"><span class="cg-ic" aria-hidden="true">' + (ICONS[g.topic] || ICONS['']) + '</span>'
      + (g.week > 0 ? '<span class="cg-wk" title="Joined in the last 7 days">+' + g.week + ' this week</span>' : '') + '</div>'
      + '<a class="cg-gn" href="' + url + '">' + esc(g.name) + '</a>'
      + '<span class="cg-s">' + [g.where ? '<b>' + esc(WHERE[g.where] || '') + '</b>' : '', g.topic_label ? esc(g.topic_label) : '', g.join_mode === 'approval' ? 'ask to join' : ''].filter(Boolean).join(' · ') + '</span>'
      + (g.parent ? '<span class="cg-s">Part of ' + esc(g.parent.name) + '</span>' : '')
      + (g.about ? '<p class="cg-ab">' + esc(g.about) + '</p>' : '')
      + (g.tags && g.tags.length ? '<span class="cg-s">' + g.tags.map(function (t) { return '#' + esc(t); }).join(' ') + '</span>' : '')
      // Join group opens the group at "What you're agreeing to": joining is consent to its charter (decisions/0137 §3)
      + '<div class="cg-gf"><span class="cg-s">' + UI.users + g.members + ' member' + (g.members === 1 ? '' : 's') + '</span>'
      + '<a class="cg-b" href="' + url + (mine ? '' : '#charter') + '">' + (mine ? 'Open group' : g.join_mode === 'approval' ? 'Ask to join' : 'Join group') + '</a></div></article>';
  }
  function sorted(list) {
    if (sort !== 'active') return list;   // 'near' and 'members': the server's own order
    return list.slice().sort(function (a, b) { return (b.week || 0) - (a.week || 0) || b.members - a.members || a.name.localeCompare(b.name); });
  }
  function head(title, n, icon) { return '<div class="cg-hd"><h2 class="cg-h">' + (icon || '') + esc(title) + '</h2><span class="cg-n">' + n + ' group' + (n === 1 ? '' : 's') + '</span></div>'; }
  function draw(d, q) {
    var all = document.getElementById('cg-all');
    var none = '<p class="text-sm">' + (q || d.topic ? 'No groups match. Start one below.' : 'No groups yet. Start the first one below.') + '</p>';
    if (d.place && d.place.key) { var gs = sorted(d.groups); all.innerHTML = gs.length ? head('Groups in ' + d.place.label, gs.length) + '<p class="cg-s mb-1">' + (sort === 'near' ? 'Nearest first: groups here and nearby, then groups about places nearby' + (q || d.topic ? ', then groups anywhere that match' : '') + '.' : 'Most active first: joins this week, then members.') + '</p><div class="cg-list">' + gs.map(card).join('') + '</div>' : none; }
    else {
      var interest = sorted(d.groups.filter(function (g) { return !g.local; })), local = sorted(d.groups.filter(function (g) { return g.local; }));
      all.innerHTML = !d.groups.length ? none
        : (interest.length ? head(sort === 'active' ? 'Trending & growing groups' : 'Interest groups', interest.length, sort === 'active' ? UI.trend : '') + '<div class="cg-list">' + interest.map(card).join('') + '</div>' : '')
        + (local.length ? head('Local groups', local.length) + '<p class="cg-s mb-1">Groups that belong to a town or area. Each also shows on that place\\'s page.</p><div class="cg-list">' + local.map(card).join('') + '</div>' : '');
    }
    // the lists' standard (2026-11-12, js/tv-pager.js): 10 a page or the visitor's choice, Show more, and the pages
    if (window.TvPager) all.querySelectorAll('.cg-list').forEach(function (l, i) { TvPager.local(l, { key: 'groups' + i }); });
  }
  function sorts() {
    var o = place ? [['near', 'Nearest first'], ['active', 'Most active']] : [['active', 'Most active'], ['members', 'Most members']], el = document.getElementById('cg-sort');
    if (!o.some(function (x) { return x[0] === sort; })) sort = o[0][0];
    el.innerHTML = o.map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === sort ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('');
  }
  function url() {
    var p = [place ? 'place=' + encodeURIComponent(place) : '', topic ? 'topic=' + encodeURIComponent(topic) : '', sort !== (place ? 'near' : 'active') ? 'sort=' + sort : ''].filter(Boolean);
    history.replaceState(null, '', '/community/' + (p.length ? '?' + p.join('&') : ''));
  }
  sorts();
  function load(q) {""")
# topics fill the dropdown (in place of the chips); popular tags as words; the list drawn by draw() with the sort
s = between(s, "      document.getElementById('cg-topics').innerHTML = [['', 'All']]", "      }).join('');\n",
"""      var tsel = document.getElementById('cg-topic');
      tsel.innerHTML = '<option value="">All topics</option>' + Object.keys(d.topics).map(function (k) { return '<option value="' + k + '"' + (k === d.topic ? ' selected' : '') + '>' + esc(d.topics[k]) + '</option>'; }).join('');
""")
s = rep(s, "      pop.innerHTML = d.popular && d.popular.length ? '<span class=\"cg-s\">Popular' + (d.topic ? ' in ' + esc(d.topics[d.topic]) : '') + ':</span> ' + d.popular.map(function (t) { return '<button type=\"button\" class=\"cg-chip\" data-tag=\"' + esc(t) + '\">#' + esc(t) + '</button>'; }).join(' ') : '';",
           "      pop.innerHTML = d.popular && d.popular.length ? '<span>Popular' + (d.topic ? ' in ' + esc(d.topics[d.topic]) : '') + ':</span> ' + d.popular.map(function (t) { return '<button type=\"button\" class=\"cg-tag\" data-tag=\"' + esc(t) + '\">#' + esc(t) + '</button>'; }).join(' ') : '';")
s = between(s, "      var none = '<p class=\"text-sm\">' + (q || d.topic ?", "TvPager.local(l, { key: 'groups' + i }); });\n",
"""      mineIds = {}; (d.mine || []).forEach(function (g) { if (g.status !== 'pending') mineIds[g.id] = 1; });
      last = d; draw(d, q);
""")
s = rep(s, "<p class=\"text-sm font-semibold text-[color:var(--tv-ink)] mb-1\">Your groups</p>", "<h2 class=\"cg-h\" style=\"font-size:1rem;margin-bottom:.4rem\">Your groups</h2>")
s = between(s, "  document.getElementById('cg-topics').addEventListener('click', function (e) {", "    load(lastQ);\n  });\n",
"""  document.getElementById('cg-topic').addEventListener('change', function (e) { topic = e.target.value; url(); load(lastQ); });
  document.getElementById('cg-sort').addEventListener('change', function (e) { sort = e.target.value; url(); if (last) draw(last, lastQ); });
""")
s = rep(s, "  load('');\n})();", "  // Start a group (top right) opens the form below and goes to it\n"
           "  document.querySelector('[data-cg-start]').addEventListener('click', function () { var n = document.getElementById('cg-new'); if (n.hidden) n.hidden = false; setTimeout(function () { var i = n.querySelector('input,textarea'); if (i) i.focus(); }, 50); });\n"
           "  load('');\n})();")
put(f, s)
# ---- a group's page and the member dashboard: on the tokens, layouts kept (as 3a–3c; Plan A, cleared 2026-11-20) ----
import sys; sys.path.insert(0, S); from recolor import recolor
REPORT = {}
for f in ['website/community/group.php', 'website/user/dashboard.php', 'app/ui/pulse-panel.php', 'app/ui/profile-header.php']:   # the last two: the member dashboard's Pulse and profile, shared with public profiles and the business portal
    s = live(f); r = {'mapped': 0, 'left': {}}
    if f.endswith('group.php'):   # its primary buttons on the one button standard (#181): brand brown, page-colour text
        s = rep(s, ".cg-join.go{background:#3E2B1F;border-color:#3E2B1F;color:#EFE3C8}", ".cg-join.go{background:var(--tv-btn);border-color:var(--tv-btn);color:var(--tv-btn-text)}.cg-join.go:hover{background:var(--tv-btn-hover)}")
        s = rep(s, ".cg-b.go{background:#3E2B1F;border-color:#3E2B1F;color:#EFE3C8}", ".cg-b.go{background:var(--tv-btn);border-color:var(--tv-btn);color:var(--tv-btn-text)}.cg-b.go:hover{background:var(--tv-btn-hover)}")
    else:   # light words on the umber (the avatar and icons' gold, the active tab, your own messages): the tokens made for it
        s = s.replace("background:#3E2B1F;color:#B8863B;", "background:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber-meta,#B8863B);")
        s = s.replace("background:#3E2B1F;color:#FFFBF2;", "background:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#FFFBF2);")
    if f.endswith('pulse-panel.php'):   # the "needs you" counts: Grounded Amber, for waiting (css-theme-brand §2); white on it is 5.6:1 (was 3.19:1)
        s = rep(s, "text-align:center;background:#D97706;color:#fff;", "text-align:center;background:var(--tv-hold,#8f5600);color:#fff;")
        s = rep(s, "border-radius:999px;background:#D97706;color:#fff;font-size:.62rem;", "border-radius:999px;background:var(--tv-hold,#8f5600);color:#fff;font-size:.62rem;")
    s = recolor(s, r); REPORT[f] = r; put(f, s)
# the dashboard's counts and its trend's words, drawn by admin-dashboard.php: text, so at 4.5:1 (css-theme-brand §3)
f = 'app/ui/admin-dashboard.php'; s = live(f)
s = rep(s, ".ad-cnt{font:700 1.2rem var(--tv-font-head,Georgia,serif);color:#B45309;", ".ad-cnt{font:700 1.2rem var(--tv-font-head,Georgia,serif);color:var(--tv-link,#92400E);")   # was the icon colour, 4.38:1
s = rep(s, ".ad-sub i{font-style:normal;font-weight:700;color:#B45309;", ".ad-sub i{font-style:normal;font-weight:700;color:var(--tv-link,#92400E);")
s = rep(s, ".ad-m i.eq{color:#8A7355}", ".ad-m i.eq{color:var(--tv-meta,#5E4F3C)}")   # "no change" is words, 4.36:1
put(f, s)
for f, r in REPORT.items(): print('  %s: %d onto tokens; left as they were: %s' % (f, r['mapped'], ', '.join('%s ×%d' % kv for kv in sorted(r['left'].items()))))
f = 'app/ui/faq.md'; s = live(f)
k = "### How do I start a group?\n"
assert s.count(k) == 1
s = s.replace(k, """### How do I find a group?
On **Connect → Groups** (https://traversence.com/community/), search by a group's name, topic or place, or choose a topic from **All topics**. **Most active** puts first the groups more people joined in the last 7 days, then the biggest; **Most members** lists the biggest first, and with a place, **Nearest first** lists groups there and nearby first. A card's **+12 this week** is how many joined in the last 7 days: a count only, never who. **Join group** (or **Ask to join**) opens the group's page at **What you're agreeing to**, its charter, because joining is agreeing to it. **Start a group** is at the top right.

""" + k, 1)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
