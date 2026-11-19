#!/usr/bin/env python3
"""The place page (Jason, 2026-11-19, approved; reviewed by the session manager): (1) the photo viewer names what's in
the photo: its page, See on the map, Copy link; (2) Plan a trip here: the place's ready-made journeys (decisions/0146)
first, then the Trip Planner set to it; Add to a trip greyed, coming; (3) views shown at 0, and the viewer shows the
counts of the place in each photo; (4) ⋯ on the engage bar: See on the map, Directions (a street that isn't private),
Plan a trip here, Add to a trip (coming), Save contact, Suggest an edit, Report. Also: a business's own page gets no
Directions for a mailbox (R2's rule, which missed that page). Replayable: build-pp.py <root>."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:100], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)

# ---------------- the engage bar: views at 0, the ⋯ toolkit ----------------
patch('app/ui/engage-ui.php', [
 ("  .eg-rep{background:var(--tv-card-a);",
  "  /* ⋯ on the bar (Jason, 2026-11-19: \"Maybe a ... tool kit for things?\"): the page's tools in words; what can't be used here is greyed with why */\n"
  "  .eg-mw{position:relative;display:inline-flex}.eg-more{font:700 .95rem/1 system-ui,sans-serif;letter-spacing:.05em;padding:.28rem .6rem!important}\n"
  "  .eg-ml{position:absolute;right:0;top:calc(100% + .35rem);z-index:60;min-width:14.5rem;display:flex;flex-direction:column;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.35rem .3rem .3rem;text-align:left;text-shadow:none}\n"
  "  .eg-ml[hidden]{display:none}.eg-ml.up{top:auto;bottom:calc(100% + .35rem)}\n"
  "  .eg-ml a,.eg-ml button[role=menuitem],.eg-mo{display:flex;align-items:flex-start;gap:.5rem;width:100%;box-sizing:border-box;padding:.45rem .55rem;border:0;background:none;border-radius:.4rem;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);text-decoration:none;cursor:pointer;text-align:left;position:static}\n"
  "  .eg-ml a:hover,.eg-ml button[role=menuitem]:hover{background:var(--tv-field)}.eg-ml svg{width:16px;height:16px;flex:none;margin-top:.1rem;color:var(--tv-icon,#B45309)}\n"
  "  .eg-mo{color:var(--tv-meta);cursor:default}.eg-mo span{display:flex;flex-direction:column}.eg-mo small{font-size:.76rem;line-height:1.3}.eg-mo svg{color:var(--tv-meta)}\n"
  "  .eg-mx{align-self:flex-end;width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);font-size:1rem;line-height:1;cursor:pointer;padding:0;position:static}\n"
  "  .eg-trip{position:fixed;z-index:3100;left:50%;top:50%;transform:translate(-50%,-50%);width:min(26rem,calc(100vw - 6px));max-height:calc(100vh - 2rem);overflow:auto;box-sizing:border-box;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.9rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.45));padding:.8rem .9rem;color:var(--tv-ink);text-align:left}\n"
  "  .eg-trip h3{margin:0 2rem .5rem 0;font:700 1.02rem var(--tv-font-head,ui-serif,Georgia,serif)}.eg-trip p{margin:.3rem 0;font-size:.86rem;color:var(--tv-meta)}\n"
  "  .eg-trip ul{list-style:none;margin:.3rem 0 .6rem;padding:0;display:flex;flex-direction:column;gap:.3rem}.eg-trip li a{display:block;border:1px solid var(--tv-line);border-radius:.55rem;padding:.45rem .6rem;color:var(--tv-ink);text-decoration:none;background:var(--tv-field)}\n"
  "  .eg-trip li a b{display:block;color:var(--tv-link,#92400E)}.eg-trip li a small{color:var(--tv-meta)}.eg-trip .eg-plan{display:inline-flex;border-radius:999px;background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#E4DCC5);padding:.45rem .9rem;font-weight:700;text-decoration:none}\n"
  "  .eg-trip .eg-tx{position:absolute;top:.55rem;right:.55rem;width:30px;height:30px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);font-size:1.1rem;line-height:1;cursor:pointer}\n"
  "  .eg-rep{background:var(--tv-card-a);"),
 ("    if ($views > 0) $h .= '<span class=\"eg-n\"",
  "    // views always show, 0 included (Jason, 2026-11-19: \"the social numbers should always be present\"; 0058 §20 note)\n"
  "    $h .= '<span class=\"eg-n\""),
 ("    if (!empty($o['report'])) $h .= '<button type=\"button\" class=\"eg-b r\" data-eg-report=\"' . tv_e((string) $o['report']) . '\">Report</button>';\n    return $h . '</div>';",
  "    if (!empty($o['tools'])) $h .= tv_engage_tools((array) $o['tools'], (string) ($o['report'] ?? ''), $ic);   // ⋯ (Report moves into it)\n"
  "    elseif (!empty($o['report'])) $h .= '<button type=\"button\" class=\"eg-b r\" data-eg-report=\"' . tv_e((string) $o['report']) . '\">Report</button>';\n"
  "    return $h . '</div>';\n}\n\n"
  "/**\n * ⋯ on the bar (Jason, 2026-11-19; decisions/0058 §20 note): the page's own tools in words. Each one the page has runs the\n"
  " * page's own button (one way to do a thing); one it can't offer here is greyed with why, never hidden. $t: map (the page's\n"
  " * See on map button, '#id', or ''), dir (the page's directions URL, or ['', why]), trip (['key' => place key] or ['lat', 'lon']),\n"
  " * contact (a .vcf URL: businesses), suggest (data-tv-suggest's JSON fields).\n */\n"
  "function tv_engage_tools(array $t, string $report, callable $ic): string\n{\n"
  "    $off = fn (string $svg, string $label, string $why) => '<span class=\"eg-mo\" role=\"menuitem\" aria-disabled=\"true\">' . $ic($svg) . '<span>' . $label . '<small>' . tv_e($why) . '</small></span></span>';\n"
  "    $MAP = '<polygon points=\"1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6\"/><path d=\"M8 2v16M16 6v16\"/>'; $DIR = '<polygon points=\"3 11 22 2 13 21 11 13 3 11\"/>';\n"
  "    $TRIP = '<path d=\"M3 17l6-6 4 4 8-8\"/><path d=\"M14 7h7v7\"/>'; $ADD = '<path d=\"M12 5v14M5 12h14\"/>'; $VCF = '<path d=\"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2\"/><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M19 8v6M22 11h-6\"/>';\n"
  "    $EDIT = '<path d=\"M12 20h9\"/><path d=\"M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z\"/>'; $FLAG = '<path d=\"M4 22V4\"/><path d=\"M4 4h13l-2 4 2 4H4\"/>';\n"
  "    $m = '<button type=\"button\" class=\"eg-mx\" aria-label=\"Close\" title=\"Close\">&times;</button>';\n"
  "    $map = $t['map'] ?? '';\n"
  "    $m .= is_array($map) && isset($map['lat'], $map['lon']) ? '<button type=\"button\" role=\"menuitem\" data-eg-map=\"' . tv_e(json_encode($map)) . '\">' . $ic($MAP) . 'See on the map</button>'\n"
  "        : (is_string($map) && $map !== '' ? '<button type=\"button\" role=\"menuitem\" data-eg-click=\"' . tv_e($map) . '\">' . $ic($MAP) . 'See on the map</button>' : $off($MAP, 'See on the map', 'This page has no point on the map.'));\n"
  "    if (array_key_exists('dir', $t)) { $d = $t['dir'];\n"
  "        $m .= is_string($d) && $d !== '' ? '<a role=\"menuitem\" href=\"' . tv_e($d) . '\" target=\"_blank\" rel=\"noopener\"' . (!empty($t['out']) ? ' data-out=\"directions\" data-e=\"' . (int) $t['out'] . '\"' : '') . '>' . $ic($DIR) . 'Directions</a>'\n"
  "            : $off($DIR, 'Directions', is_array($d) ? (string) ($d[1] ?? 'No street address to drive to.') : 'No street address to drive to.'); }\n"
  "    $m .= !empty($t['trip']) ? '<button type=\"button\" role=\"menuitem\" data-eg-trip=\"' . tv_e(json_encode((array) $t['trip'])) . '\">' . $ic($TRIP) . 'Plan a trip here</button>' : $off($TRIP, 'Plan a trip here', 'This page isn\\'t tied to a place.');\n"
  "    $m .= $off($ADD, 'Add to a trip', 'Coming: trips you can save and add places to.');\n"
  "    if (!empty($t['contact'])) $m .= '<a role=\"menuitem\" href=\"' . tv_e((string) $t['contact']) . '\">' . $ic($VCF) . 'Save contact</a>';\n"
  "    if (!empty($t['suggest'])) $m .= '<button type=\"button\" role=\"menuitem\" data-tv-suggest=\"' . tv_e(json_encode((array) $t['suggest'])) . '\">' . $ic($EDIT) . 'Suggest an edit</button>';\n"
  "    if ($report !== '') $m .= '<button type=\"button\" role=\"menuitem\" data-eg-report=\"' . tv_e($report) . '\">' . $ic($FLAG) . 'Report</button>';\n"
  "    return '<span class=\"eg-mw\"><button type=\"button\" class=\"eg-b eg-more\" aria-haspopup=\"menu\" aria-expanded=\"false\" title=\"More: the map, directions, a trip\" aria-label=\"More tools\">&#8943;</button>'\n"
  "        . '<span class=\"eg-ml\" role=\"menu\" hidden>' . $m . '</span></span>';"),
])

# ---------------- /api/engage.php: counts (read only) and a place's journeys ----------------
patch('website/api/engage.php', [
 ("    if ($_SERVER['REQUEST_METHOD'] === 'GET') {\n",
  "    // GET counts=1&kind&ref: a page's views, likes and comments, for the photo viewer (Jason, 2026-11-19: \"the social numbers\n"
  "    // should always be present\"). Read only; never who.\n"
  "    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['counts'])) {\n"
  "        $k = (string) ($_GET['kind'] ?? ''); $r = substr(preg_replace('/[^\\w:.-]/', '', (string) ($_GET['ref'] ?? '')), 0, 60);\n"
  "        if (!in_array($k, Engage::KINDS, true) || $r === '' || !Engage::ready($pdo)) Response::error('Nothing to count.', 404, 'not_found');\n"
  "        if ($uid) $on = Engage::liked($pdo, $k, $r, $uid); else { require_once __DIR__ . '/../../app/lib/Guest.php'; $on = !empty(Guest::liked()[$k . ':' . $r]); }\n"
  "        Response::json(['ok' => true, 'views' => Engage::views($pdo, $k, $r), 'likes' => Engage::likes($pdo, $k, $r), 'comments' => Engage::commentCount($pdo, $k, $r), 'liked' => $on, 'like' => $k !== 'listing']);   // listings: no Like (0058 §20)\n"
  "    }\n"
  "    // GET trip=1&key or lat&lon: Plan a trip here (decisions/0146): the place's ready-made journeys, then the Trip Planner set to it\n"
  "    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['trip'])) {\n"
  "        require_once __DIR__ . '/../../app/lib/Guides.php'; require_once __DIR__ . '/../../app/lib/JourneyPlan.php';\n"
  "        $key = (string) ($_GET['key'] ?? '');\n"
  "        if (!preg_match('/^(hub|geo_hub|cluster|town|sub):\\d+$/', $key) && is_numeric($_GET['lat'] ?? null) && is_numeric($_GET['lon'] ?? null)) {   // a point: its nearest area, within 40 miles\n"
  "            $la = (float) $_GET['lat']; $lo = (float) $_GET['lon'];\n"
  "            $st = $pdo->prepare(\"SELECT id, (POW(center_lat - ?, 2) + POW((center_lon - ?) * COS(RADIANS(?)), 2)) d FROM micro_clusters WHERE status = 'active' AND center_lat IS NOT NULL ORDER BY d LIMIT 1\");\n"
  "            $st->execute([$la, $lo, $la]); $c = $st->fetch(PDO::FETCH_ASSOC);\n"
  "            $key = $c && sqrt((float) $c['d']) * 69 <= 40 ? 'cluster:' . (int) $c['id'] : '';\n"
  "        }\n"
  "        $p = $key !== '' ? Guides::place($pdo, $key) : null;\n"
  "        if (!$p) Response::json(['ok' => true, 'place' => null, 'journeys' => [], 'plan' => '/discovery/?view=plan']);\n"
  "        $js = []; try { foreach (array_slice(JourneyPlan::forPlace($pdo, $key), 0, 6) as $j) $js[] = ['title' => (string) $j['title'], 'why' => (string) ($j['why'] ?? ''), 'minutes' => (int) ($j['minutes'] ?? 0), 'url' => '/journeys/suggested.php?id=' . (int) $j['id']]; } catch (Throwable $e) {}\n"
  "        $q = ['view' => 'plan'];\n"
  "        if ($p['level'] === 'cluster') $q['cluster'] = (int) $p['id']; elseif ($p['level'] === 'geo_hub') $q['geo'] = (string) $p['slug']; elseif ($p['level'] === 'hub') $q['hub'] = (string) $p['slug'];\n"
  "        elseif ((int) $p['cluster'] > 0) { $q['cluster'] = (int) $p['cluster']; $q['town'] = (string) $p['name']; }\n"
  "        Response::json(['ok' => true, 'place' => ['name' => (string) $p['name'], 'url' => (string) ($p['url'] ?? '')], 'journeys' => $js, 'plan' => '/discovery/?' . http_build_query($q)]);\n"
  "    }\n"
  "    if ($_SERVER['REQUEST_METHOD'] === 'GET') {\n"),
])

# ---------------- js/engage.js: ⋯, Plan a trip here ----------------
patch('website/js/engage.js', [
 ("(function () {\n  var csrf = null,", "(function () {\n  if (window.tvEngage) return; window.tvEngage = true;   // once, also when the photo viewer loads it (2026-11-19)\n  var csrf = null,"),
 ("  document.addEventListener('click', function (e) {\n    var bar = e.target.closest('.eg-bar'), sec",
  "  // ⋯ on the bar (2026-11-19): one open at a time; ×, Esc or a click outside close it; it opens upward near the foot of the\n"
  "  // screen. A tool the page has runs the page's own button (data-eg-click); Plan a trip here opens the place's journeys.\n"
  "  // an open menu sits on the page itself (a hero's layer or a card can't cover or cut it), and goes home when it closes\n"
  "  function closeMenus(but) { document.querySelectorAll('.eg-ml:not([hidden])').forEach(function (m) { if (m === but) return; m.hidden = true; var h = m._home; if (h) { h.appendChild(m); var b = h.querySelector('.eg-more'); if (b) b.setAttribute('aria-expanded', 'false'); } }); }\n"
  "  document.addEventListener('click', function (e) {\n"
  "    var t = e.target; if (!t.closest || !t.isConnected) return;\n"
  "    var more = t.closest('.eg-more');\n"
  "    if (more) { var m = more._menu || (more._menu = more.nextElementSibling); m._home = more.parentNode; closeMenus(m);\n"
  "      if (!m.hidden) { closeMenus(); return; }\n"
  "      document.body.appendChild(m); m.hidden = false; more.setAttribute('aria-expanded', 'true');\n"
  "      if (!m.hidden) {   // placed on the screen, so a hero or a card never cuts it off; upward near the foot\n"
  "        var br = more.getBoundingClientRect(), w = m.offsetWidth, h = m.offsetHeight, top = br.bottom + 6;\n"
  "        if (top + h > innerHeight - 8 && br.top - h - 6 > 8) top = br.top - h - 6;\n"
  "        m.style.position = 'fixed'; m.style.zIndex = 3200; m.style.right = 'auto'; m.style.bottom = 'auto'; m.style.top = Math.max(3, top) + 'px'; m.style.left = Math.max(3, Math.min(br.right - w, document.documentElement.clientWidth - w - 3)) + 'px'; }\n"
  "      return; }\n"
  "    if (t.closest('.eg-mx')) { closeMenus(); return; }\n"
  "    var mp = t.closest('[data-eg-map]');\n"
  "    if (mp) { closeMenus(); if (window.tvMapAround) tvMapAround(JSON.parse(mp.getAttribute('data-eg-map') || '{}')); return; }\n"
  "    var go = t.closest('[data-eg-click]');\n"
  "    if (go) { closeMenus(); var el = document.querySelector(go.getAttribute('data-eg-click')); if (el) el.click(); return; }\n"
  "    var tr = t.closest('[data-eg-trip]');\n"
  "    if (tr) { closeMenus(); planTrip(tr, JSON.parse(tr.getAttribute('data-eg-trip') || '{}')); return; }\n"
  "    var rp = t.closest('.eg-ml [data-eg-report]');\n"
  "    if (rp && !rp._go) { closeMenus(); rp._go = true; setTimeout(function () { rp.click(); rp._go = false; }, 0); e.stopImmediatePropagation(); return; }   // back in its bar first: Report reads the bar\n"
  "    if (rp) return;\n"
  "    if (t.closest('.eg-ml a, .eg-ml [data-tv-suggest]')) { setTimeout(closeMenus, 0); return; }\n"
  "    if (!t.closest('.eg-ml')) closeMenus();\n"
  "  });\n"
  "  window.addEventListener('scroll', function () { if (document.querySelector('.eg-ml:not([hidden])')) closeMenus(); }, { passive: true });\n"
  "  document.addEventListener('keydown', function (e) {\n"
  "    if (e.key !== 'Escape') return;\n"
  "    var tp = document.querySelector('.eg-trip'); if (tp) { tp.remove(); return; }\n"
  "    var open = document.querySelector('.eg-ml:not([hidden])'); if (open) { closeMenus(); var b = open.previousElementSibling; if (b) b.focus(); }\n"
  "  });\n"
  "  // Plan a trip here (decisions/0146): the place's ready-made journeys first, then the Trip Planner set to it\n"
  "  function planTrip(btn, p) {\n"
  "    var old = document.querySelector('.eg-trip'); if (old) old.remove();\n"
  "    var q = new URLSearchParams({ trip: 1 }); ['key', 'lat', 'lon'].forEach(function (k) { if (p[k] != null && p[k] !== '') q.set(k, p[k]); });\n"
  "    if (window.TvAsk) TvAsk.busy(btn, true);\n"
  "    fetch('/api/engage.php?' + q.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {\n"
  "      if (window.TvAsk) TvAsk.busy(btn, false);\n"
  "      var box = document.createElement('div'); box.className = 'eg-trip'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Plan a trip here');\n"
  "      var name = d.place ? d.place.name : '';\n"
  "      box.innerHTML = '<button type=\"button\" class=\"eg-tx\" aria-label=\"Close\" title=\"Close\">&times;</button><h3>Plan a trip' + (name ? ' in ' + esc(name) : '') + '</h3>'\n"
  "        + ((d.journeys || []).length ? '<p>Ready-made journeys here, timed stop by stop:</p><ul>' + d.journeys.map(function (j) { return '<li><a href=\"' + esc(j.url) + '\"><b>' + esc(j.title) + '</b><small>' + esc([j.minutes ? Math.round(j.minutes / 60 * 10) / 10 + ' hours' : '', j.why].filter(Boolean).join(' · ')) + '</small></a></li>'; }).join('') + '</ul>'\n"
  "          : '<p>' + (name ? 'No ready-made journeys for ' + esc(name) + ' yet.' : 'This isn\\u2019t inside one of our areas yet, so there are no ready-made journeys.') + '</p>')\n"
  "        + '<a class=\"eg-plan\" href=\"' + esc(d.plan || '/discovery/?view=plan') + '\">Open the Trip Planner' + (name ? ' for ' + esc(name) : '') + '</a>'\n"
  "        + '<p>Saving a trip and adding places to it are coming.</p>';\n"
  "      document.body.appendChild(box); box.querySelector('.eg-tx').focus();\n"
  "      box.addEventListener('click', function (ev) { if (ev.target.closest('.eg-tx')) { box.remove(); btn.focus && btn.focus(); } });\n"
  "      setTimeout(function () { document.addEventListener('click', function off(ev) { if (!box.isConnected) { document.removeEventListener('click', off); return; } if (!box.contains(ev.target)) { box.remove(); document.removeEventListener('click', off); } }); }, 0);\n"
  "    }).catch(function () { if (window.TvAsk) { TvAsk.busy(btn, false); TvAsk.say('The journeys couldn\\u2019t load. Try again, or open the Trip Planner.', { anchor: btn, bad: true }); } });\n"
  "  }\n"
  "  document.addEventListener('click', function (e) {\n    var bar = e.target.closest('.eg-bar'), sec"),
])

# ---------------- the photo viewer: what's in the photo, its counts ----------------
EH = 'app/ui/explore-hero.php'
patch(EH, [
 # slides carry their subject when it isn't the page itself
 ("        else $ownHtml .= '<div class=\"xh-slide xh-zoom\" data-lb=\"' . tv_e($s['image']) . '\" data-lb-cap=\"' . tv_e($s['caption']) . '\"",
  "        else $ownHtml .= '<div class=\"xh-slide xh-zoom\" data-lb=\"' . tv_e($s['image']) . '\" data-lb-cap=\"' . tv_e($s['caption']) . '\"'\n"
  "            . (!empty($s['href']) ? ' data-lb-name=\"' . tv_e((string) ($s['subject'] ?? '')) . '\" data-lb-href=\"' . tv_e((string) $s['href']) . '\"'   // the photo is of another place: the viewer names it (2026-11-19)\n"
  "                . (!empty($s['kind']) ? ' data-lb-kind=\"' . tv_e((string) $s['kind']) . '\" data-lb-ref=\"' . tv_e((string) $s['ref']) . '\"' : '')\n"
  "                . (isset($s['lat'], $s['lon']) ? ' data-lb-lat=\"' . (float) $s['lat'] . '\" data-lb-lon=\"' . (float) $s['lon'] . '\"' : '') : '') . '"),
 ("  .xlb-acts{position:absolute;",
  "  /* what's in the photo (Jason, 2026-11-19: \"a way for the view to see the location/url of the subject\") */\n"
  "  .xlb-sub{position:absolute;left:.8rem;right:4rem;top:max(.8rem,env(safe-area-inset-top));display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;color:var(--tv-on-umber);font-size:.86rem}\n"
  "  .xlb-sub a.xlb-to{color:var(--tv-on-umber);font:700 1rem var(--tv-font-head,ui-serif,Georgia,serif);text-decoration:underline;text-underline-offset:3px;margin-right:.3rem}\n"
  "  .xlb .xlb-sub button{position:static;font-size:.8rem;font-weight:600;padding:.3rem .7rem;min-height:32px}\n"
  "  .xlb .eg-ml button,.xlb .eg-mx{position:static}.xlb .eg-ml{color:var(--tv-ink)}\n"
  "  @media (max-width:640px){.xlb-sub{right:3.6rem}.xlb-sub a.xlb-to{flex-basis:100%}}\n"
  "  .xlb-acts .eg-bar{display:flex;align-items:center}.xlb-acts .eg-b{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.3rem .75rem;font-weight:600;text-decoration:none;cursor:pointer}   /* also where the page has no bar of its own */\n"
  "  .xlb-acts{position:absolute;"),
 ("      box.querySelector('.xlb-n').textContent = list.length > 1 ? (at + 1) + ' of ' + list.length : '';\n    }",
  "      box.querySelector('.xlb-n').textContent = list.length > 1 ? (at + 1) + ' of ' + list.length : '';\n"
  "      subject(it);\n    }\n"
  "    // what's in the photo: its name (a link to its page), See on the map, Copy link; and its own counts when it isn't this page\n"
  "    function esc(s) { return String(s == null ? '' : s).replace(/[&<>\"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#39;' }[c]; }); }\n"
  "    var seq = 0;\n"
  "    function subject(it) {\n"
  "      var sub = box.querySelector('.xlb-sub'), acts = box.querySelector('.xlb-acts'), page = !it.href;\n"
  "      var name = page ? pageName : it.name, href = page ? location.pathname + location.search : it.href;\n"
  "      var mapBtn = page ? (src && src.querySelector('[data-eg-click]')) : null;\n"
  "      sub.innerHTML = (name ? '<a class=\"xlb-to\" href=\"' + esc(href) + '\">' + esc(name) + '</a>' : '')\n"
  "        + (page ? (mapBtn ? '<button type=\"button\" data-xlb=\"pmap\">See on the map</button>' : '') : (it.lat != null ? '<button type=\"button\" data-xlb=\"map\">See on the map</button>' : ''))\n"
  "        + (name ? '<button type=\"button\" data-xlb=\"copy\">Copy link</button>' : '');\n"
  "      sub.hidden = !sub.innerHTML;\n"
  "      if (page) { acts.innerHTML = ''; if (src) acts.appendChild(src.cloneNode(true)); acts.hidden = !src; return; }\n"
  "      acts.innerHTML = ''; acts.hidden = true; var my = ++seq;\n"
  "      if (!it.kind) return;\n"
  "      fetch('/api/engage.php?counts=1&kind=' + encodeURIComponent(it.kind) + '&ref=' + encodeURIComponent(it.ref), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {\n"
  "        if (my !== seq || !box || !d.ok) return;\n"
  "        var ic = function (p) { return '<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\">' + p + '</svg>'; };\n"
  "        acts.innerHTML = '<div class=\"eg-bar\" data-eg-kind=\"' + esc(it.kind) + '\" data-eg-ref=\"' + esc(it.ref) + '\" data-eg-title=\"' + esc(it.name) + '\">'\n"
  "          + '<span class=\"eg-n\" title=\"' + d.views + ' ' + (d.views === 1 ? 'visit' : 'visits') + ' saw its page\"' + ' aria-label=\"' + d.views + ' views\">' + ic('<path d=\"M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/>') + '<span>' + d.views + '</span></span>'\n"
  "          + (d.like ? '<button type=\"button\" class=\"eg-b\" data-eg-like aria-pressed=\"' + (d.liked ? 'true' : 'false') + '\" title=\"Like\">' + ic('<path d=\"M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z\"/>') + '<span>' + d.likes + '</span></button>' : '')\n"
  "          + '<a class=\"eg-b\" href=\"' + esc(it.href) + '#comments\" title=\"Comments on its page\">' + ic('<path d=\"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z\"/>') + '<span>' + d.comments + '</span></a></div>';\n"
  "        acts.hidden = false;\n"
  "        if (!window.tvEngage && !document.querySelector('script[src^=\"/js/engage.js\"]')) { var sc = document.createElement('script'); sc.src = '/js/engage.js'; document.head.appendChild(sc); }   // Like works here too\n"
  "      }).catch(function () {});\n"
  "    }\n"
  "    var pageName = '';\n"
  "    function mapAt(it) {   // the map around what's in the photo, with what's near it (as its own page's map does)\n"
  "      if (!window.TvMap) return;\n"
  "      var here = { lat: +it.lat, lon: +it.lon, radius: 10 }, id = it.kind === 'lm' ? 'rec:g' + ('0000000000' + it.ref).slice(-10) : '';\n"
  "      TvMap.open({ panel: true, title: it.name, radius: 10, select: id || undefined,\n"
  "        titleFor: function () { return 'Around ' + it.name; },\n"
  "        load: function (area, words) {\n"
  "          var a = area || here, qs = new URLSearchParams({ pins: 1, kinds: words ? 'listing,outdoors' : 'outdoors', lat: a.lat, lon: a.lon, radius: a.radius }); if (words) qs.set('q', words);\n"
  "          return fetch('/api/search.php?' + qs.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {\n"
  "            if (id && !(d.pins || []).some(function (p) { return p.id === id; })) (d.pins = d.pins || []).push({ id: id, lat: here.lat, lon: here.lon, name: it.name });\n"
  "            d.center = a; return d; });\n"
  "        } });\n"
  "    }\n"
  "    window.tvMapAround = function (p) { mapAt({ lat: p.lat, lon: p.lon, name: p.name || '', kind: '', ref: '' }); };"),
 ("      list = Array.prototype.map.call(track.querySelectorAll('[data-lb]'), function (s) { return { src: s.getAttribute('data-lb'), cap: s.getAttribute('data-lb-cap') || (s.querySelector('.xh-cap') ? s.querySelector('.xh-cap').textContent : '') }; });",
  "      list = Array.prototype.map.call(track.querySelectorAll('[data-lb]'), function (s) { return { src: s.getAttribute('data-lb'), cap: s.getAttribute('data-lb-cap') || (s.querySelector('.xh-cap') ? s.querySelector('.xh-cap').textContent : ''),\n"
  "        name: s.getAttribute('data-lb-name') || '', href: s.getAttribute('data-lb-href') || '', kind: s.getAttribute('data-lb-kind') || '', ref: s.getAttribute('data-lb-ref') || '',\n"
  "        lat: s.hasAttribute('data-lb-lat') ? +s.getAttribute('data-lb-lat') : null, lon: s.hasAttribute('data-lb-lon') ? +s.getAttribute('data-lb-lon') : null }; });\n"
  "      var hx = slide.closest('.xh'); pageName = hx ? (hx.getAttribute('data-xh-name') || '') : '';"),
 ("      if (copy && src) { var a = copy.querySelector('[data-eg-like]'), b = src.querySelector('[data-eg-like]'); if (a && b) b.outerHTML = a.outerHTML; }",
  "      if (copy && src && copy.getAttribute('data-eg-ref') === src.getAttribute('data-eg-ref') && copy.getAttribute('data-eg-kind') === src.getAttribute('data-eg-kind')) { var a = copy.querySelector('[data-eg-like]'), b = src.querySelector('[data-eg-like]'); if (a && b) b.outerHTML = a.outerHTML; }   // the page's own bar only"),
 ("      box.innerHTML = '<img alt=\"\"><p class=\"xlb-cap\"></p><p class=\"xlb-n\"></p><button type=\"button\" class=\"x\" aria-label=\"Close\">&times;</button>'",
  "      box.innerHTML = '<div class=\"xlb-sub\" hidden></div><img alt=\"\"><p class=\"xlb-cap\"></p><p class=\"xlb-n\"></p><button type=\"button\" class=\"x\" aria-label=\"Close\">&times;</button>'"),
 ("      var bar = slide.closest('.xh') && slide.closest('.xh').querySelector('.xh-acts .eg-bar');\n"
  "      if (bar) { var acts = document.createElement('div'); acts.className = 'xlb-acts'; acts.appendChild(bar.cloneNode(true)); box.appendChild(acts); src = bar; }",
  "      var bar = slide.closest('.xh') && slide.closest('.xh').querySelector('.xh-acts .eg-bar');\n"
  "      var acts = document.createElement('div'); acts.className = 'xlb-acts'; acts.hidden = true; box.appendChild(acts); src = bar || null;   // filled per photo (subject())"),
 ("        if (ev.target.closest('.xlb-acts')) {   // Comments: close and go to them; the rest work in place\n"
  "          if (ev.target.closest('a[href=\"#comments\"]')) close();\n          return;\n        }",
  "        if (ev.target.closest('.xlb-acts')) {   // Comments: close and go to them; a tool that leaves the viewer closes it; the rest work in place\n"
  "          if (ev.target.closest('a[href=\"#comments\"]')) close();\n          else if (ev.target.closest('[data-eg-click], [data-eg-map], [data-eg-trip], [data-tv-suggest], [data-eg-report]')) setTimeout(close, 0);   // after the tool has run\n          return;\n        }\n"
  "        var xb = ev.target.closest('[data-xlb]');\n"
  "        if (xb) { var it = list[at], k = xb.getAttribute('data-xlb');\n"
  "          if (k === 'copy') { var u = location.origin + (it.href || (location.pathname + location.search)); var ok = function () { if (window.TvAsk) TvAsk.say('Link copied', { anchor: xb }); };\n"
  "            if (navigator.clipboard) navigator.clipboard.writeText(u).then(ok, function () { if (window.TvAsk) TvAsk.ask('Copy this link', u); }); else if (window.TvAsk) TvAsk.ask('Copy this link', u); }\n"
  "          else if (k === 'map') { close(); mapAt(it); }\n"
  "          else if (k === 'pmap') { var mb = src && src.querySelector('[data-eg-click]'), el = mb && document.querySelector(mb.getAttribute('data-eg-click')); close(); if (el) el.click(); }\n"
  "          return; }\n"
  "        if (ev.target.closest('.xlb-sub')) return;"),
 # the hero says its own name (the viewer's subject when the photo is of the page itself)
 ("    $h .= '<section class=\"xh' . (empty($o['tabs']) ? ' xh-solo' : '') . ($none ? ' xh-nophoto' : '') . '\"'",
  "    $h .= '<section class=\"xh' . (empty($o['tabs']) ? ' xh-solo' : '') . ($none ? ' xh-nophoto' : '') . '\" data-xh-name=\"' . tv_e((string) ($o['heading'] ?? $name)) . '\"'"),
])

# ---------------- Let's Explore: each landmark photo says what and where it is ----------------
patch('website/discovery/index.php', [
 ("            $st = $pdo->prepare(\"SELECT name, state, near_town, image_url, image_credit FROM landmarks WHERE \"",
  "            $st = $pdo->prepare(\"SELECT gnis_id, name, state, near_town, lat, lon, image_url, image_credit FROM landmarks WHERE \""),
 ("    return array_map(fn ($r) => ['image' => (string) $r['image_url'], 'caption' =>",
  "    // each photo names its place for the viewer: its page, its point (a nation's land: its public place, Nations::pin) and its counts (2026-11-19)\n"
  "    if (!class_exists('Nations') && is_file(__DIR__ . '/../../app/lib/Nations.php')) require_once __DIR__ . '/../../app/lib/Nations.php';\n"
  "    $subj = function (array $r) use ($pdo) {\n"
  "        if (empty($r['gnis_id'])) return [];\n"
  "        $la = isset($r['lat']) ? (float) $r['lat'] : null; $lo = isset($r['lon']) ? (float) $r['lon'] : null;\n"
  "        if ($la !== null && class_exists('Nations')) { try { [$la, $lo] = Nations::pin($pdo, $la, $lo); } catch (Throwable $e) { $la = $lo = null; } }\n"
  "        return ['subject' => (string) $r['name'], 'href' => '/place/landmark.php?id=' . (int) $r['gnis_id'], 'kind' => 'lm', 'ref' => (string) (int) $r['gnis_id']] + ($la !== null && $lo !== null ? ['lat' => $la, 'lon' => $lo] : []);\n"
  "    };\n"
  "    return array_map(fn ($r) => $subj($r) + ['image' => (string) $r['image_url'], 'caption' =>"),
])

# ---------------- the pages pass their tools ----------------
patch('website/place/landmark.php', [
 ("    'actions' => tv_engage_bar($pdo, 'lm', (string) $id, ['title' => $name])]);",
  "    'actions' => tv_engage_bar($pdo, 'lm', (string) $id, ['title' => $name, 'tools' => ['map' => '#lm-map', 'dir' => $dir, 'trip' => ['lat' => $lat, 'lon' => $lon],\n"
  "        'suggest' => ['target' => 'landmark:' . $id, 'target_name' => $name, 'kind' => 'fix']]])]);   // ⋯ (2026-11-19)"),
])
patch('website/place/recreation.php', [
 ("    'actions' => tv_engage_bar($pdo, 'rec', (string) $r['ridb_id'], ['title' => $name])]); ?>",
  "    'actions' => tv_engage_bar($pdo, 'rec', (string) $r['ridb_id'], ['title' => $name, 'tools' => ['map' => $hasPt ? '#rp-map' : '', 'dir' => $dir,\n"
  "        'trip' => $hasPt ? ['lat' => (float) $r['lat'], 'lon' => (float) $r['lon']] : [], 'suggest' => ['target' => 'rec:' . $r['ridb_id'], 'target_name' => $name, 'kind' => 'fix']]])]); ?>   // ⋯ (2026-11-19)"),
])
patch('website/listing/view.php', [
 # a mailbox: no Directions on its own page either (R2's rule; Jason: "only a mailbox")
 ("} elseif ($biz['address']) {\n    $addrParts",
  "} elseif ($biz['address'] && !ListingActions::isMailbox((string) $biz['address'])) {   // a mailbox has nowhere to drive to (2026-11-19)\n    $addrParts"),
 ("$mapHref = null;\nif ($biz['latitude'] !== null && $biz['longitude'] !== null) {",
  "require_once __DIR__ . '/../../app/lib/ListingActions.php';\n$mapHref = null;\n$mailOnly = (string) $biz['address'] !== '' && ListingActions::isMailbox((string) $biz['address']);\n"
  "if ($biz['latitude'] !== null && $biz['longitude'] !== null && !$mailOnly) {"),
 ("    'actions' => tv_engage_bar($pdo, 'listing', (string) $eid, ['title' => $biz['name'], 'likes' => false])",
  "    'actions' => tv_engage_bar($pdo, 'listing', (string) $eid, ['title' => $biz['name'], 'likes' => false, 'tools' => ['map' => $hasPt ? '#lv-map' : '',\n"
  "        'dir' => $mapHref && empty($privateLocation) ? $mapHref : ['', !empty($privateLocation) ? 'Its location is kept private: call them.' : ($mailOnly ? 'Mailing address only: no street to drive to.' : 'No street address yet.')], 'out' => $eid,\n"
  "        'trip' => $hasPt ? ['lat' => (float) $biz['latitude'], 'lon' => (float) $biz['longitude']] : [], 'contact' => '/api/vcard.php?listing=' . $eid,\n"
  "        'suggest' => ['kind' => 'fix', 'entity_id' => $eid, 'entity_name' => $biz['name']]]])   // ⋯ (2026-11-19)"),
])
patch('website/hub/index.php', [
 ("    'actions' => tv_engage_bar($pdo, 'place', $key, ['title' => $p['name']])]) ?>",
  "    'actions' => tv_engage_bar($pdo, 'place', $key, ['title' => $p['name'], 'tools' => ['map' => $p['lat'] !== null ? ['lat' => (float) $p['lat'], 'lon' => (float) $p['lon'], 'name' => $p['name']] : '', 'trip' => ['key' => $key], 'suggest' => $sg + ['kind' => 'story']]])]) ?>   // ⋯ (2026-11-19)"),
])
print('built')
