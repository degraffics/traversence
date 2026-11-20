/* js/tv-maps.js: Send to Maps (decisions/0174 §4). A link with data-maps='[[lat,lon,"name"],…]' opens a chooser:
 * Google Maps or Apple Maps (remembered on this device), from My location or the first stop, and the route in legs when
 * it has more stops than one link holds ("Leg 1 of 3"), each starting where the last ended, so nothing is cut silently.
 * No key, no call from our server: each leg is a plain link the visitor taps. Closes with ×, Esc and a click outside. */
(function () {
  'use strict';
  var MID = 8;   // stops between the start and the end of one link (the site's proven count since journeys began)
  function get(k, d) { try { return localStorage.getItem(k) || d; } catch (e) { return d; } }
  function put(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function pt(s) { return s[0] + ',' + s[1]; }

  /** Legs: [{from: stop|null, to: [stops…]}]; from null is My location. */
  function legs(stops, here) {
    var out = [], i = here ? 0 : 1, from = here ? null : stops[0];
    while (i < stops.length) {
      var end = Math.min(stops.length - 1, i + MID);
      out.push({from: from, to: stops.slice(i, end + 1)});
      from = stops[end]; i = end + 1;
    }
    return out;
  }
  function url(app, leg) {
    var to = leg.to, dest = to[to.length - 1], mid = to.slice(0, -1), q = [];
    if (app === 'apple') {
      if (leg.from) q.push('source=' + encodeURIComponent(pt(leg.from)));
      q.push('destination=' + encodeURIComponent(pt(dest)));
      mid.forEach(function (s) { q.push('waypoint=' + encodeURIComponent(pt(s))); });
      q.push('mode=driving');
      return 'https://maps.apple.com/directions?' + q.join('&');
    }
    q.push('api=1');
    if (leg.from) q.push('origin=' + encodeURIComponent(pt(leg.from)));
    q.push('destination=' + encodeURIComponent(pt(dest)));
    if (mid.length) q.push('waypoints=' + mid.map(function (s) { return encodeURIComponent(pt(s)); }).join('%7C'));
    q.push('travelmode=driving');
    return 'https://www.google.com/maps/dir/?' + q.join('&');
  }

  var box = null, opener = null;
  function css() {
    if (document.getElementById('tv-sm-css')) return;
    var st = document.createElement('style'); st.id = 'tv-sm-css';
    st.textContent = '.tvsm{position:fixed;z-index:9000;width:min(22rem,calc(100vw - 6px));max-height:calc(100vh - 1rem);overflow:auto;background:#FFFBF2;border:1px solid #CBB98D;border-radius:.6rem;box-shadow:0 10px 30px rgba(62,43,31,.22);padding:.75rem .8rem .8rem;color:#3E2B1F;font-size:.88rem;line-height:1.4}'
      + '.tvsm h2{font:700 1rem/1.25 var(--tv-font-head,Georgia,serif);margin:0 1.8rem .15rem 0;color:#3E2B1F}.tvsm-sub{margin:0 0 .55rem;color:#8A7355;font-size:.8rem}'
      + '.tvsm-x{position:absolute;top:.4rem;right:.4rem;width:30px;height:30px;border-radius:999px;border:1px solid #CBB98D;background:#FFFBF2;color:#92400E;font-size:1.1rem;line-height:1;cursor:pointer}'
      + '.tvsm-l{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#8A7355;margin:.5rem 0 .25rem}'
      + '.tvsm-seg{display:flex;gap:.35rem;flex-wrap:wrap}.tvsm-seg button{flex:1 1 auto;min-height:34px;border-radius:999px;border:1px solid #CBB98D;background:#FFFBF2;color:#3E2B1F;font:600 .84rem/1.2 inherit;padding:.3rem .7rem;cursor:pointer;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
      + '.tvsm-seg button[aria-pressed="true"]{background:#8FBCA8;border-color:#6E9C88;color:#1F2A24;box-shadow:0 0 0 3px rgba(143,188,168,.3)}'
      + '.tvsm-go{list-style:none;margin:.6rem 0 0;padding:0;display:flex;flex-direction:column;gap:.35rem}.tvsm-go a{display:block;border-radius:.5rem;background:#92400E;color:#fff;font-weight:700;text-decoration:none;padding:.5rem .7rem}.tvsm-go a small{display:block;font-weight:400;opacity:.9;font-size:.76rem;margin-top:.1rem}'
      + '.tvsm-note{margin:.55rem 0 0;font-size:.76rem;color:#8A7355}'
      + '@media (max-width:767px){.tvsm{left:3px!important;right:3px;bottom:3px;top:auto!important;width:auto;border-radius:.5rem}}';
    document.head.appendChild(st);
  }
  function close() {
    if (!box) return;
    box.remove(); box = null;
    document.removeEventListener('keydown', onKey, true);
    document.removeEventListener('pointerdown', onOut, true);
    if (opener) { opener.setAttribute('aria-expanded', 'false'); try { opener.focus({preventScroll: true}); } catch (e) {} }
    opener = null;
  }
  function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); close(); } }
  function onOut(e) { if (box && !box.contains(e.target) && !(opener && opener.contains(e.target))) close(); }

  function draw(stops, title) {
    var app = get('tv-maps-app', 'google'), here = get('tv-maps-from', 'here') === 'here';
    var ls = legs(stops, here), name = app === 'apple' ? 'Apple Maps' : 'Google Maps', n = stops.length;
    var h = '<button type="button" class="tvsm-x" aria-label="Close" title="Close">×</button>'
      + '<h2 id="tvsm-h">Send to Maps</h2><p class="tvsm-sub">' + esc(title || 'This route') + ' · ' + n + ' stops, in order</p>'
      + '<p class="tvsm-l">Open in</p><div class="tvsm-seg" data-k="app"><button type="button" data-v="google" aria-pressed="' + (app !== 'apple') + '">Google Maps</button><button type="button" data-v="apple" aria-pressed="' + (app === 'apple') + '">Apple Maps</button></div>'
      + '<p class="tvsm-l">Start from</p><div class="tvsm-seg" data-k="from"><button type="button" data-v="here" aria-pressed="' + here + '">My location</button><button type="button" data-v="first" aria-pressed="' + !here + '" title="' + esc(stops[0][2]) + '">' + esc(stops[0][2] || 'The first stop') + '</button></div>'
      + (ls.length > 1 ? '<p class="tvsm-note" style="margin-top:.6rem">One link holds ' + (MID + 2) + ' points, so this route goes in ' + ls.length + ' legs. Open them in order: each starts where the last ended.</p>' : '')
      + '<ul class="tvsm-go">';
    ls.forEach(function (lg, i) {
      var a = lg.from ? lg.from[2] : 'My location', b = lg.to[lg.to.length - 1][2];
      h += '<li><a href="' + esc(url(app, lg)) + '" target="_blank" rel="noopener" data-out="directions">'
        + (ls.length > 1 ? 'Leg ' + (i + 1) + ' of ' + ls.length + ' in ' + name : 'Open in ' + name) + ' ↗'
        + '<small>' + esc(a) + ' → ' + esc(b) + ' · ' + lg.to.length + ' stop' + (lg.to.length === 1 ? '' : 's') + '</small></a></li>';
    });
    h += '</ul><p class="tvsm-note">' + (app === 'apple' ? 'Several stops in Apple Maps need iOS 18.4 or macOS 15.4 or later. ' : '')
      + 'Your choice is kept on this device. Check hours before you go.</p>';
    box.innerHTML = h;
  }
  function place(a) {
    if (window.innerWidth < 768) return;
    var r = a.getBoundingClientRect(), w = box.offsetWidth, hh = box.offsetHeight;
    var left = Math.max(3, Math.min(window.innerWidth - w - 3, r.right - w));
    var top = r.bottom + 6; if (top + hh > window.innerHeight - 3) top = Math.max(3, r.top - hh - 6);
    box.style.left = left + 'px'; box.style.top = top + 'px';
  }
  function open(a) {
    var stops; try { stops = JSON.parse(a.getAttribute('data-maps')); } catch (e) { return false; }
    if (!stops || stops.length < 2) return false;
    close(); css();
    box = document.createElement('div'); box.className = 'tvsm'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-labelledby', 'tvsm-h');
    document.body.appendChild(box); opener = a; a.setAttribute('aria-expanded', 'true');
    var title = a.getAttribute('data-maps-title') || '';
    draw(stops, title); place(a);
    box.addEventListener('click', function (e) {
      if (e.target.closest('.tvsm-x')) { close(); return; }
      var b = e.target.closest('.tvsm-seg button'); if (!b) return;
      var k = b.parentNode.getAttribute('data-k');
      put(k === 'app' ? 'tv-maps-app' : 'tv-maps-from', b.getAttribute('data-v'));
      draw(stops, title); place(a);
      var again = box.querySelector('.tvsm-seg[data-k="' + k + '"] button[data-v="' + b.getAttribute('data-v') + '"]'); if (again) again.focus();
    });
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('pointerdown', onOut, true);
    var f = box.querySelector('.tvsm-go a'); if (f) f.focus({preventScroll: true});
    return true;
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-maps]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
    if (box && opener === a) { e.preventDefault(); close(); return; }
    if (open(a)) e.preventDefault();
  });
  window.TvMaps = {legs: legs, url: url, open: open, close: close};
})();
