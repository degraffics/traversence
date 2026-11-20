/* js/tv-trips.js: trips you can save (decisions/0174 §1, §2, release 2).
 *   <button data-trip-add='{"kind":"lm","ref":"12","name":"Lyman Lake"}'>   Add to a trip: the picker
 *   <button data-trip-journey="7">                                         Make it my trip (a ready-made journey)
 *   <div data-trips-device></div>                                          a guest's trip on this device, drawn here
 *   TvTrips.refFor(listing)                                                a card's listing (map card, Peek) as {kind, ref, name}
 * Members: their trips, the newest first, and New trip (named for the place); it confirms in the picker with Open it and
 * Undo. Guests: one trip kept on this device (localStorage), with an account offered to keep it; signing in moves it
 * into the account (the next page that loads this). The server applies the rules: a place kept private, told only or
 * closed for good can't go in, with why. Every pop-up closes with ×, Esc and a click outside. */
(function () {
  'use strict';
  if (window.TvTrips) return;
  var API = '/api/trips.php', KEY = 'tv-trip-device', MAX = 60, me = null, box = null, opener = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function tz() { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return ''; } }
  function say(m, o) { if (window.TvAsk) TvAsk.say(m, o); }
  function busy(b, on) { if (b && window.TvAsk && TvAsk.busy) TvAsk.busy(b, on); }
  function device() { try { var t = JSON.parse(localStorage.getItem(KEY) || 'null'); return t && Array.isArray(t.stops) ? t : null; } catch (e) { return null; } }
  function keep(t) { try { if (t && t.stops.length) localStorage.setItem(KEY, JSON.stringify(t)); else localStorage.removeItem(KEY); return true; } catch (e) { return false; } }
  function who() {
    if (me) return me;
    me = fetch(API, {credentials: 'include'}).then(function (r) { return r.json(); }).then(function (d) { if (!d.ok) throw d; return d; });
    me.catch(function () { me = null; });
    return me;
  }
  function post(body) {
    return who().then(function (d) {
      body.tz = tz();
      return fetch(API, {method: 'POST', credentials: 'include', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': d.csrf || ''},
        body: JSON.stringify(window.tvSafe ? tvSafe(body) : body)});
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok || j.ok === false) throw j; return j; }); });
  }
  function err(e) { return (e && (e.message || e.error)) || 'That didn\'t work: try again.'; }

  // ---- the panel -------------------------------------------------------------------------------------------------------
  function css() {
    if (document.getElementById('tv-tp-css')) return;
    var s = document.createElement('style'); s.id = 'tv-tp-css';
    s.textContent = '.tvtp{position:fixed;z-index:9000;width:min(21rem,calc(100vw - 6px));max-height:calc(100vh - 1rem);overflow:auto;background:#FFFBF2;border:1px solid #CBB98D;border-radius:.6rem;box-shadow:0 10px 30px rgba(62,43,31,.22);padding:.75rem .8rem .8rem;color:#3E2B1F;font-size:.88rem;line-height:1.4}'
      + '.tvtp h2{font:700 1rem/1.25 var(--tv-font-head,Georgia,serif);margin:0 1.9rem .1rem 0}.tvtp-sub{margin:0 0 .5rem;color:#8A7355;font-size:.8rem}'
      + '.tvtp-x{position:absolute;top:.4rem;right:.4rem;width:30px;height:30px;border-radius:999px;border:1px solid #CBB98D;background:#FFFBF2;color:#92400E;font-size:1.1rem;line-height:1;cursor:pointer}'
      + '.tvtp ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.3rem}'
      + '.tvtp-t{display:flex;align-items:center;gap:.4rem;width:100%;text-align:left;min-height:40px;border:1px solid #CBB98D;background:#FFFBF2;border-radius:.5rem;padding:.4rem .6rem;font:inherit;color:#3E2B1F;cursor:pointer}.tvtp-t:hover{border-color:#B45309}'
      + '.tvtp-t b{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tvtp-t small{color:#8A7355;white-space:nowrap}.tvtp-t.new{border-style:dashed;color:#92400E;font-weight:700}'
      + '.tvtp-done{display:flex;flex-wrap:wrap;align-items:center;gap:.3rem .6rem;border:1px solid #8FBCA8;background:#EEF6F1;border-radius:.5rem;padding:.4rem .6rem}.tvtp-done span{flex:1 1 100%;font-weight:600}'
      + '.tvtp-done a,.tvtp-done button{font:600 .82rem/1.2 inherit;color:#92400E;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer}'
      + '.tvtp-note{margin:.55rem 0 0;font-size:.78rem;color:#8A7355}.tvtp-note button{font:inherit;color:#92400E;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer}'
      + '.tvtp-off{color:#8A7355;border:1px dashed #CBB98D;border-radius:.5rem;padding:.45rem .6rem}'
      + '@media (max-width:767px){.tvtp{left:3px!important;right:3px;bottom:3px;top:auto!important;width:auto;border-radius:.5rem}}';
    document.head.appendChild(s);
  }
  function close() {
    if (!box) return;
    box.remove(); box = null;
    document.removeEventListener('keydown', onKey, true); document.removeEventListener('pointerdown', onOut, true);
    if (opener) { opener.setAttribute('aria-expanded', 'false'); try { opener.focus({preventScroll: true}); } catch (e) {} }
    opener = null;
  }
  function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } }
  function onOut(e) { if (box && !box.contains(e.target) && !(opener && opener.contains(e.target))) close(); }
  function place(a) {
    if (window.innerWidth < 768 || !a || !a.isConnected) return;
    var r = a.getBoundingClientRect(), w = box.offsetWidth, h = box.offsetHeight;
    box.style.left = Math.max(3, Math.min(window.innerWidth - w - 3, r.right - w)) + 'px';
    var top = r.bottom + 6; if (top + h > window.innerHeight - 3) top = Math.max(3, r.top - h - 6);
    box.style.top = top + 'px';
  }
  function panel(a, html) {
    close(); css();
    box = document.createElement('div'); box.className = 'tvtp'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-labelledby', 'tvtp-h');
    box.innerHTML = '<button type="button" class="tvtp-x" aria-label="Close" title="Close">×</button>' + html;
    document.body.appendChild(box); opener = a; if (a) a.setAttribute('aria-expanded', 'true');
    box.querySelector('.tvtp-x').addEventListener('click', close);
    document.addEventListener('keydown', onKey, true); document.addEventListener('pointerdown', onOut, true);
    place(a);
    var f = box.querySelector('.tvtp-t, button:not(.tvtp-x), a'); if (f) try { f.focus({preventScroll: true}); } catch (e) {}
    return box;
  }
  function redraw(a, html) { if (!box) return panel(a, html); box.innerHTML = '<button type="button" class="tvtp-x" aria-label="Close" title="Close">×</button>' + html; box.querySelector('.tvtp-x').addEventListener('click', close); place(a); return box; }

  // ---- Add to a trip ---------------------------------------------------------------------------------------------------
  function add(a, it) {
    if (box && opener === a) { close(); return; }
    var head = '<h2 id="tvtp-h">Add to a trip</h2><p class="tvtp-sub">' + esc(it.name || 'This place') + '</p>';
    panel(a, head + '<p class="tvtp-sub" aria-busy="true">Looking at your trips…</p>');
    Promise.all([who(), post({action: 'addable', kind: it.kind, ref: it.ref})]).then(function (r) {
      var d = r[0], c = r[1];
      if (!box) return;
      if (!c.can) { redraw(a, head + '<p class="tvtp-off" data-tv-status="info" data-tv-label="Can\'t be added:">' + esc(c.why) + '</p>'); return; }
      if (d.signed_in) member(a, it, head, d.trips || [], c.trip_name); else guest(a, it, head, c.trip_name);
    }).catch(function (e) { if (box) redraw(a, head + '<p class="tvtp-off" data-tv-status="bad" data-tv-label="Error:">' + esc(err(e)) + ' <a href="mailto:support@traversence.com">Ask our team</a>.</p>'); });
  }
  function member(a, it, head, trips, newName) {
    var h = head + '<ul>';
    trips.forEach(function (t) { h += '<li><button type="button" class="tvtp-t" data-trip="' + t.id + '"><b>' + esc(t.name) + '</b><small>' + t.stops + ' stop' + (t.stops === 1 ? '' : 's') + '</small></button></li>'; });
    h += '<li><button type="button" class="tvtp-t new" data-trip="new">+ New trip: ' + esc(newName) + '</button></li></ul>'
      + '<p class="tvtp-note">Your trips are private: only you see them. <a href="/user/trips/" style="color:#92400E;text-decoration:underline">Your trips</a></p>';
    redraw(a, h);
    box.addEventListener('click', function (e) {
      var b = e.target.closest('.tvtp-t'); if (!b) return;
      busy(b, true);
      post({action: 'add', kind: it.kind, ref: it.ref, trip: b.getAttribute('data-trip')}).then(function (r) {
        busy(b, false); me = null;   // counts changed
        var li = b.parentNode;
        li.innerHTML = '<div class="tvtp-done" role="status"><span>✓ ' + (r.already ? 'Already in ' : 'Added to ') + esc(r.trip.name) + '</span><a href="' + esc(r.trip.url) + '">Open it</a>'
          + (r.already ? '' : '<button type="button" data-undo="' + r.stop + '">Undo</button>') + '</div>';
        var u = li.querySelector('[data-undo]');
        if (u) u.addEventListener('click', function () {
          busy(u, true);
          post({action: 'remove', stop: +u.getAttribute('data-undo')}).then(function () { me = null; busy(u, false); li.innerHTML = '<div class="tvtp-done"><span>Taken out of ' + esc(r.trip.name) + '</span></div>'; })
            .catch(function (e2) { busy(u, false); say(err(e2), {anchor: u, bad: true}); });
        });
      }).catch(function (e) { busy(b, false); say(err(e), {anchor: b, bad: true}); });
    });
  }
  function guest(a, it, head, newName) {
    var t = device(), n = t ? t.stops.length : 0, has = t && t.stops.some(function (s) { return s.kind === it.kind && String(s.ref) === String(it.ref); });
    var h = head + '<ul><li><button type="button" class="tvtp-t" data-trip="device"><b>' + esc(t && t.name ? t.name : newName) + '</b><small>' + (t ? n + ' stop' + (n === 1 ? '' : 's') : 'new') + '</small></button></li></ul>'
      + '<p class="tvtp-note">As a guest, one trip is kept on this device. <button type="button" data-join>Make an account</button> to keep it and see it on another device.</p>';
    redraw(a, h);
    box.querySelector('[data-join]').addEventListener('click', function () { close(); if (window.TvJoin) TvJoin.open({why: 'An account keeps your trips and shows them on any device. The trip on this device moves into it.', next: location.pathname + location.search}); else location.href = '/login.php'; });
    box.querySelector('[data-trip="device"]').addEventListener('click', function (e) {
      var b = e.currentTarget, li = b.parentNode, cur = device() || {name: newName, stops: [], at: Date.now()};
      if (has) { li.innerHTML = '<div class="tvtp-done" role="status"><span>✓ Already in ' + esc(cur.name) + '</span><a href="/user/trips/">Open it</a></div>'; return; }
      if (cur.stops.length >= MAX) { say('Your trip holds ' + MAX + ' stops: make an account to start another.', {anchor: b, bad: true}); return; }
      cur.stops.push({kind: it.kind, ref: String(it.ref), name: it.name || ''}); cur.at = Date.now();
      if (!keep(cur)) { say('This browser won\'t keep anything on the device (private browsing?): make an account to keep a trip.', {anchor: b, bad: true}); return; }
      li.innerHTML = '<div class="tvtp-done" role="status"><span>✓ Added to ' + esc(cur.name) + ' on this device</span><a href="/user/trips/">Open it</a><button type="button" data-undo>Undo</button></div>';
      li.querySelector('[data-undo]').addEventListener('click', function () {
        var t2 = device(); if (t2) { t2.stops = t2.stops.filter(function (s) { return !(s.kind === it.kind && String(s.ref) === String(it.ref)); }); keep(t2); }
        li.innerHTML = '<div class="tvtp-done"><span>Taken out of ' + esc(cur.name) + '</span></div>';
      });
    });
  }

  // ---- Make it my trip -------------------------------------------------------------------------------------------------
  function journey(a, id) {
    busy(a, true);
    who().then(function (d) {
      if (d.signed_in) return post({action: 'copy_journey', id: id}).then(function (r) {
        busy(a, false);
        panel(a, '<h2 id="tvtp-h">Your trip</h2><div class="tvtp-done" role="status"><span>✓ ' + esc(r.trip.name) + ': ' + r.added + ' stop' + (r.added === 1 ? '' : 's') + '</span><a href="' + esc(r.trip.url) + '">Open it</a></div>'
          + (r.left && r.left.length ? '<p class="tvtp-note">Left out, by the same rules as Directions: ' + esc(r.left.join('; ')) + '</p>' : '')
          + '<p class="tvtp-note">It\'s yours to change: take stops out, add others with ⋯ → Add to a trip. Days, reordering and times come with the trip page.</p>');
      });
      return post({action: 'journey', id: id}).then(function (j) {
        busy(a, false);
        var cur = device();
        var go = function () {
          if (!keep({name: j.name, stops: j.stops.slice(0, MAX), at: Date.now()})) { say('This browser won\'t keep anything on the device (private browsing?): make an account to keep a trip.', {anchor: a, bad: true}); return; }
          panel(a, '<h2 id="tvtp-h">Your trip on this device</h2><div class="tvtp-done" role="status"><span>✓ ' + esc(j.name) + ': ' + j.stops.length + ' stops</span><a href="/user/trips/">Open it</a></div>'
            + '<p class="tvtp-note">As a guest, one trip is kept on this device. <button type="button" data-join>Make an account</button> to keep more and see them anywhere.</p>');
          box.querySelector('[data-join]').addEventListener('click', function () { close(); if (window.TvJoin) TvJoin.open({why: 'An account keeps your trips and shows them on any device.', next: location.pathname + location.search}); });
        };
        if (cur && cur.stops.length && window.TvAsk) TvAsk.confirm('Replace the trip on this device (' + cur.name + ', ' + cur.stops.length + ' stops)? An account keeps more than one.', {anchor: a, yes: 'Replace it'}).then(function (y) { if (y) go(); });
        else go();
      });
    }).catch(function (e) { busy(a, false); say(err(e), {anchor: a, bad: true}); });
  }

  // ---- a guest's trip, drawn on Your trips --------------------------------------------------------------------------------
  function drawDevice(el) {
    var t = device();
    if (!t || !t.stops.length) { el.innerHTML = '<p class="tvtr-none tv-bleed">No trip on this device yet. Tap ⋯ → <b>Add to a trip</b> on a place or business, or <b>Make it my trip</b> on a ready-made journey.</p>'; return Promise.resolve(); }
    el.innerHTML = '<p aria-busy="true">Reading your trip…</p>';
    return post({action: 'resolve', stops: t.stops}).then(function (r) {
      var h = '<section class="tvtr tv-bleed" id="trip-device"><h2>' + esc(t.name) + '</h2><p class="tvtr-m">' + t.stops.length + ' stop' + (t.stops.length === 1 ? '' : 's') + ' · kept on this device only</p><ol class="tvtr-s">';
      r.stops.forEach(function (s, i) {
        h += '<li' + (s.ok ? '' : ' class="off"') + '>' + (s.ok ? '<a href="' + esc(s.url) + '"' + (s.kind === 'osm' ? ' target="_blank" rel="noopener"' : '') + '>' + esc(s.name) + '</a><small>' + esc(s.sub || '') + '</small>'
          : '<b>' + esc(s.name || t.stops[i].name || 'A stop') + '</b><small>' + esc(s.why) + '</small>') + '<button type="button" class="tvtr-x" data-device-out="' + i + '" aria-label="Take out ' + esc(s.name) + '" title="Take it out">×</button></li>';
      });
      var pts = r.stops.filter(function (s) { return s.ok; }).map(function (s) { return [+(+s.lat).toFixed(5), +(+s.lon).toFixed(5), s.name]; });
      h += '</ol><p class="tvtr-a">' + (pts.length >= 2 ? '<a class="tvtr-b" href="#" data-maps="' + esc(JSON.stringify(pts)) + '" data-maps-title="' + esc(t.name) + '" aria-haspopup="dialog">Send to Maps</a>' : '')
        + '<button type="button" class="tvtr-b" data-join>Make an account to keep it</button></p>'
        + (r.stops.some(function (s) { return s.kind === 'osm'; }) ? '<p class="tvtr-m">Places marked from <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors.</p>' : '') + '</section>';
      el.innerHTML = h;
      el.querySelector('[data-join]').addEventListener('click', function () { if (window.TvJoin) TvJoin.open({why: 'An account keeps your trips and shows them on any device. This trip moves into it.', next: '/user/trips/'}); else location.href = '/login.php'; });
      el.querySelectorAll('[data-device-out]').forEach(function (b) {
        b.addEventListener('click', function () {
          var t2 = device(), i = +b.getAttribute('data-device-out'), gone = t2.stops.splice(i, 1)[0]; keep(t2);
          drawDevice(el).then(function () {
          var row = document.createElement('p'); row.className = 'tvtr-m'; row.setAttribute('data-tv-status', 'info'); row.setAttribute('data-tv-label', 'Taken out:');
          row.innerHTML = esc(gone.name || 'A stop') + '. <button type="button" class="tvtr-u">Undo</button>';
          el.insertBefore(row, el.firstChild);
          row.querySelector('button').addEventListener('click', function () { var t3 = device() || {name: t2.name, stops: [], at: Date.now()}; t3.stops.splice(Math.min(i, t3.stops.length), 0, gone); keep(t3); drawDevice(el); });
          });
        });
      });
    }).catch(function (e) { el.innerHTML = '<p data-tv-status="bad" data-tv-label="Error:">' + esc(err(e)) + ' <a href="mailto:support@traversence.com">Ask our team</a>.</p>'; });
  }

  // ---- signing in moves the device's trip into the account ----------------------------------------------------------------
  function claim() {
    var t = device(); if (!t || !t.stops.length) return;
    who().then(function (d) {
      if (!d.signed_in) return;
      return post({action: 'claim', name: t.name, stops: t.stops}).then(function (r) {
        keep(null); me = null;
        say('Your trip from this device is now in your account: ' + r.trip.name + ' (' + r.added + ' stop' + (r.added === 1 ? '' : 's') + ').' + (r.left && r.left.length ? ' Left out: ' + r.left.join('; ') + '.' : ''), {bad: false});
        document.dispatchEvent(new CustomEvent('tv-trips-claimed', {detail: r}));
      });
    }).catch(function () {});
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-trip-add]');
    if (a) {
      e.preventDefault(); var it; try { it = JSON.parse(a.getAttribute('data-trip-add')); } catch (x) { return; }
      var menu = a.closest('.eg-ml, [role="menu"]'), at = a;
      if (menu) {   // from a ⋯ menu: the menu closes (its own ×) and the picker sits by its ⋯ button
        var home = menu._home, more = home && home.querySelector('.eg-more, [aria-haspopup="menu"]');
        if (!more && menu.previousElementSibling && menu.previousElementSibling.matches('[aria-haspopup]')) more = menu.previousElementSibling;
        var mx = menu.querySelector('.eg-mx, [data-close], [aria-label="Close"]'); if (mx) mx.click(); else menu.hidden = true;
        if (more && more.isConnected) at = more;
      }
      add(at, it); return;
    }
    var j = e.target.closest && e.target.closest('[data-trip-journey]');
    if (j) { e.preventDefault(); journey(j, +j.getAttribute('data-trip-journey')); }
  });
  function boot() { claim(); document.querySelectorAll('[data-trips-device]').forEach(drawDevice); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  /** A card's listing as a stop (the map's card, search's Peek): outdoors ids are rec:<ridb> or rec:g<gnis> (a landmark). */
  function refFor(l) {
    if (!l) return null;
    if (l.kind === 'outdoors') { var id = String(l.id).replace(/^rec:/, ''), g = /^g0*(\d+)$/.exec(id); return g ? {kind: 'lm', ref: g[1], name: l.name} : {kind: 'rec', ref: id, name: l.name}; }
    return {kind: 'listing', ref: String(l.id), name: l.name || l.business_name || ''};
  }
  window.TvTrips = {add: add, journey: journey, device: device, close: close, refFor: refFor};
})();
