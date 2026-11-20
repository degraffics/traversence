// js/tv-trail.js — the way back up (Jason, 2026-11-12: "clicking into deeper pages does not have an easy way to go back up the
// route I took down"). Not a breadcrumb (content pages have none, decisions/0058 §11): the pages this visit actually took, kept
// in this tab (sessionStorage), shown as "‹ Back to <the page before>" with ▾ for the whole route. Opening a page already on the
// route goes back up it (the steps after it drop off). For everyone (Jason, 2026-11-12): in the account bar when signed in, else a slim line atop the page.
// Not on admin pages, sign-in pages or pop-up frames. Nothing leaves the browser.
(function () {
  'use strict';
  if (window.tvTrail || window.top !== window.self) return;
  var NAMES = { admin: 'the admin', user: 'your account', directory: 'Get Local', community: 'Groups', market: 'the Marketplace', shop: 'the Marketplace', discovery: 'Let\'s Explore', hub: 'the area guide', listing: 'the listing', journeys: 'Journeys', faq: 'the Help Center' };
  function backTo(u) {
    var seg = u.pathname.split('/')[1] || '', name = u.pathname === '/' ? 'Home' : (NAMES[seg.replace(/\.php$/, '')] || '');
    var w = document.createElement('span'); w.className = 'tv-tr';
    w.innerHTML = '<a href="' + String(u.pathname + u.search).replace(/"/g, '&quot;') + '" title="Back to where you came from"><b aria-hidden="true">&lsaquo;</b><span>' + (name ? 'Back to ' + name : 'Back') + '</span></a>';
    function put() { trailCss(); var as = document.querySelector('.tv-as'); if (as) { var sp = as.querySelector('.tv-as-sp'); if (sp) as.insertBefore(w, sp); else as.appendChild(w); return; }
      var m = document.getElementById('ta-work') || document.getElementById('tv-main') || document.querySelector('main'); if (!m) return;
      var line = document.createElement('div'); line.className = 'tv-trl'; line.appendChild(w); m.insertBefore(line, m.firstChild); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', put); else put();
  }
  var path = location.pathname;
  if (/^\/(admin|login|logout|register|verify|reset|api)\b/.test(path) || document.body.classList.contains('tv-embedded')) return;
  var KEY = 'tv-trail', MAX = 12;
  // a step's name: the hero's title, else the page's own title ("Get Local", a business's name), else its heading
  function title() {
    var h = document.querySelector('.xh-title span');
    var t = h ? h.textContent : (document.title || ((document.querySelector('h1') || {}).textContent || ''));
    t = String(t || '').replace(/\s*[|·–-]\s*Traversence.*$/i, '').replace(/^Explore:\s*/, '').replace(/\s+/g, ' ').trim();
    return t || 'Previous page';
  }
  // Which section a page is in: the route is Let's Explore's only (below); a page reached from it (a listing, a place, a guide,
  // a journey, a landmark) carries it on.
  var FAM = { discovery: 'explore', directory: 'local', community: 'community', market: 'market', shop: 'market', user: 'you', gallery: 'you', ai: 'you', business: 'business', '': 'home', 'index.php': 'home' };
  var seg = path.split('/')[1] || '', fam = Object.prototype.hasOwnProperty.call(FAM, seg) ? FAM[seg] : null;
  var here = { u: path + location.search, t: title(), f: fam };
  var trail = [];
  try { trail = JSON.parse(sessionStorage.getItem(KEY) || '[]') || []; } catch (e) { trail = []; }
  // Only the Let's Explore funnel (Jason, 2026-11-12: "the route should only be for the discovery funnel"): its pages, and what's
  // reached from them; any other section ends the route, and a page reached from elsewhere has none
  if (fam && fam !== 'explore') { try { sessionStorage.setItem(KEY, '[]'); } catch (e) {} return; }
  // A page that carries the route, opened with none in this tab (a new tab, a link from Pulse, the admin or a mail): one step back
  // to the page on our site it came from (Jason, 2026-11-20: "back to where they came from"), never a dead end. Not a route.
  var from = null;
  try { var rf = document.referrer ? new URL(document.referrer) : null; if (rf && rf.origin === location.origin && rf.pathname + rf.search !== path + location.search && !/^\/(login|logout|register|verify|reset|api)\b/.test(rf.pathname)) from = rf; } catch (e) {}
  if (!fam && !(trail.length && trail[0].f === 'explore')) { try { sessionStorage.setItem(KEY, '[]'); } catch (e) {} if (from) return backTo(from); return; }
  var at = -1;
  for (var i = 0; i < trail.length; i++) if (trail[i].u === here.u) { at = i; break; }
  if (at >= 0) { trail = trail.slice(0, at + 1); trail[at].t = here.t; }   // back up the route
  else if (trail.length && trail[trail.length - 1].t === here.t) trail[trail.length - 1] = here;   // the same page again (a state or place added): one step, not two (2026-11-12)
  else trail.push(here);
  if (trail.length > MAX) trail = trail.slice(trail.length - MAX);
  try { sessionStorage.setItem(KEY, JSON.stringify(trail)); } catch (e) {}
  window.tvTrail = trail;
  if (trail.length < 2) { if (from && fam === 'explore' && trail.length === 1 && trail[0].u === here.u) return backTo(from); return; }

  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var prev = trail[trail.length - 2];
  trailCss();
  function trailCss() { if (document.getElementById('tv-trail-css')) return; var css = document.createElement('style'); css.id = 'tv-trail-css';
  css.textContent = '.tv-tr{position:relative;display:inline-flex;align-items:center;min-width:0;max-width:min(32rem,55vw)}'
    + '.tv-tr a{display:inline-flex;align-items:center;gap:.3rem;min-width:0;color:inherit;text-decoration:none;font:600 .85rem system-ui,sans-serif;padding:.2rem .55rem;border-radius:999px;border:1px solid rgba(255,248,234,.45);background:rgba(255,248,234,.12)}'
    + '.tv-tr a:hover{background:rgba(255,248,234,.25)}.tv-tr a span{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
    + '.tv-tr button{margin-left:.2rem;border:1px solid rgba(255,248,234,.45);background:rgba(255,248,234,.12);color:inherit;border-radius:999px;width:26px;height:26px;cursor:pointer;font-size:.75rem;line-height:1}'
    + '.tv-tr ol{position:absolute;top:calc(100% + 6px);left:0;z-index:1500;margin:0;padding:.35rem;list-style:none;min-width:16rem;max-width:min(26rem,90vw);background:#FFFBF2;border:1px solid #CBB98D;border-radius:.7rem;box-shadow:0 16px 32px -14px rgba(0,0,0,.5)}'
    + '.tv-tr ol[hidden]{display:none}.tv-tr li a{display:flex;border:0;background:none;color:#3E2B1F;border-radius:.45rem;padding:.4rem .55rem;font-weight:500}.tv-tr li a:hover{background:#F3EAD3}'
    + '.tv-tr li a small{color:#8A7355;margin-right:.45rem;font-variant-numeric:tabular-nums}.tv-tr li.now a{font-weight:700;color:#92400E;pointer-events:none}'
    + '.tv-trl{display:flex;align-items:center;padding:.35rem .85rem;background:#F3EAD3;border-bottom:1px solid #E3D3AC;color:#3E2B1F;position:relative;z-index:5}'
    + '.tv-trl .tv-tr a,.tv-trl .tv-tr button{border-color:#CBB98D;background:#FFFBF2}'
    + '.ta-work>.tv-trl{margin:-1.1rem calc(-1 * var(--tv-pad,clamp(1rem,3vw,2.5rem))) 1.1rem}'   /* the page's own side padding (.85rem on phones): at 390 the desktop clamp made it 392px wide (2026-11-20) */   /* flush under the top bar; the hero below keeps its place */
    + '@media (max-width:640px){.tv-tr{max-width:46vw}.tv-tr a{font-size:.78rem}}';
  document.head.appendChild(css); }
  var w = document.createElement('span'); w.className = 'tv-tr';
  w.innerHTML = '<a href="' + esc(prev.u) + '" title="Back to ' + esc(prev.t) + '"><b aria-hidden="true">&lsaquo;</b><span>Back to ' + esc(prev.t) + '</span></a>'
    + (trail.length > 2 ? '<button type="button" aria-haspopup="true" aria-expanded="false" title="The route you took" aria-label="The route you took">&#9662;</button>'
      + '<ol hidden>' + trail.map(function (s, i) { return '<li' + (i === trail.length - 1 ? ' class="now"' : '') + '><a href="' + esc(s.u) + '"><small>' + (i + 1) + '</small>' + esc(s.t) + '</a></li>'; }).join('') + '</ol>' : '');
  var b = w.querySelector('button'), ol = w.querySelector('ol');
  if (b) {
    b.addEventListener('click', function (e) { e.stopPropagation(); ol.hidden = !ol.hidden; b.setAttribute('aria-expanded', String(!ol.hidden)); });
    document.addEventListener('click', function (e) { if (!w.contains(e.target)) { ol.hidden = true; b.setAttribute('aria-expanded', 'false'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { ol.hidden = true; b.setAttribute('aria-expanded', 'false'); } });
  }
  function place() {
    var as = document.querySelector('.tv-as');
    if (as) { var sp = as.querySelector('.tv-as-sp'); if (sp) as.insertBefore(w, sp); else as.appendChild(w); return; }
    var m = document.getElementById('ta-work') || document.getElementById('tv-main') || document.querySelector('main');
    if (!m) return;
    var line = document.createElement('div'); line.className = 'tv-trl'; line.appendChild(w);   // a guest: a slim line atop the page
    m.insertBefore(line, m.firstChild);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', place); else place();
})();
