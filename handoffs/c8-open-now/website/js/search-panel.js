// js/search-panel.js — universal search, in place (decisions/0053).
//
// The header's search field (a search icon on phones) opens a panel over the page; the page stays where it was.
// Typing shows results grouped by kind, and the query read as chips (place, category, kind, condition, facts) that can
// be removed. A result opens its page; a listing's Peek button opens its peek card instead. Nothing leaves the page until a result or "See all" is chosen.
// ✕, Esc, a click outside or the phone's Back button close it. Recent searches live in this browser only.
(function () {
  'use strict';
  var API = '/api/search.php';
  var root = document.getElementById('tv-search'); if (!root) return;
  var input = root.querySelector('.tvs-in'), panel = null, last = null, drop = [], timer = null, seq = 0, pushed = false, radius = 0, funnel = '', lastQS = '';
  var scopeK = '', placeOver = null, savedCsrf = null, savedIds = {}, keepNext = '';
  var applied = false;   // the words were put on the page's own list (Enter, "See them all in the directory list")
  // How far to look (decisions/0060): your place only, a distance around it, or everywhere (local first). Kept on this
  // device; the place itself never changes.
  var REACH = [['place', 'My place only'], [2, '2 mi'], [5, '5 mi'], [10, '10 mi'], [25, '25 mi'], [50, '50 mi'], [100, '100 mi'], [250, '250 mi'], ['all', '\u221E']];   // the last step: no limit
  // the Distance slider: 10 mi around a set place, unless the person widens it; back to 10 mi whenever the place changes
  // (Jason, 2026-10-04: a set place doesn't see outside it unless reconfigured). 'tv_reach2': the old ∞ default is gone.
  var REACH_DEF = 3;   // 10 mi ('tv_reach3': 2 mi joined the steps on 2026-11-10)
  var reachI = (function () { try { var v = localStorage.getItem('tv_reach3'); return v === null ? REACH_DEF : Math.max(0, Math.min(REACH.length - 1, +v)); } catch (x) { return REACH_DEF; } })();
  function setReach(i) { reachI = i; try { localStorage.setItem('tv_reach3', String(i)); } catch (x) {} }
  // "near me", "nearby", "closest" (decisions/0067, 2026-10-08): where, not words. With a place set, a search that says it
  // isn't left at no limit: 25 mi. With none, the panel asks (Use my location, or choose a place) instead of quietly
  // searching everywhere. The same phrases as the server's UniversalSearch::NEAR_ME.
  var NEARRX = /(^|\s)(close to me|close by me|near to me|closest to me|nearest to me|near me|nearme|around me|by me|nearby|near by|close by|closest|nearest|near here|around here)(\s|$)/i, REACH_NEAR = REACH.length - 1, nearPending = false, nearOn = false, nearWide = '';
  function saysNear() { return !!(input && NEARRX.test(input.value)); }
  function hasPlace() { return !!(placeOver || scope() || placeLabel()); }
  // "near me" is 2 mi (Jason, 2026-11-10), or 5 once the person widens that search; never further
  function nearKey() { return input ? input.value.trim().toLowerCase().replace(/\s+/g, ' ') : ''; }
  // "near me" is the nearest, with no distance cap (Jason, 2026-11-10): the Distance line reads no limit while it says so
  function nearReach() {
    if (!saysNear() || !hasPlace() || REACH[reachI][0] === 'all') return false;
    setReach(REACH.length - 1); return true;
  }
  // Please set your location (Jason, 2026-10-08: "near me or any other type of specific destination should fire a please set
  // your location"): whenever the words are for somewhere to go and no place is set. Everywhere chosen on purpose (the
  // picker's Anywhere, or "Search everywhere" here) stands for kinds of place; "near me" still asks, since it can't mean
  // everywhere. On Enter the place picker opens, once a visit.
  function everywhereOk() {
    var loc = (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null;
    var ok = false; try { ok = sessionStorage.getItem('tv_everywhere_ok') === '1'; } catch (x) {}
    return ok || !!(loc && loc.source === 'anywhere');
  }
  // the "Where should we look?" box: only when the words need a place ("near me", "nearby"). Otherwise the Location bar
  // above already says where and changes it (Jason, 2026-11-11: "Location and where should we look is redundant")
  function wantsPlace(d) { return !hasPlace() && (saysNear() || !!(d && d.near_me)); }
  // Where to look, asked inside the search (Jason, 2026-10-10: the pop-up "feels like a gate"): a town or ZIP right here,
  // Use my location, or keep everywhere. The results below stay; nothing opens over them. "More choices" opens the picker.
  // asked under the Location line, never again in the results (Jason, 2026-11-11: "Location, results, location, results")
  function locAsk(d) { var box = panel && panel.querySelector('.tvs-locask'); if (box) box.innerHTML = d && wantsPlace(d) && !(d.situation && d.situation.need_place) ? nearAsk() : ''; }
  function nearAsk() {
    var near = saysNear();
    return '<div class="tvs-intent tvs-nearask" role="status"><b>' + (near ? 'Location needed' : 'Where should we look?') + '</b> '
      + (near ? '<span>for \u201Cnear me\u201D: use your location, or a town or ZIP.</span>' : '<span>Showing everywhere for now; a place puts what\u2019s near it first.</span>')
      + '<span class="tvs-nearrow"><input type="search" class="tvs-nearin" placeholder="Town or ZIP" aria-label="Town or ZIP" autocomplete="off">'
      + '<button type="button" class="tvs-chipb" data-near-detect title="Use my location" aria-label="Use my location">' + TGT + 'Use my location</button></span><span class="tvs-nearlist" role="listbox" hidden></span>'
      + '<small>' + (near ? '' : '<button type="button" class="tvs-link" data-everywhere>Keep everywhere</button> \u00b7 ') + '<button type="button" class="tvs-link" data-where="change">More choices</button></small></div>';
  }
  // "near me" with no place (Jason, 2026-11-10): the site's own location pop-up (the place picker, with Auto-detect),
  // never results from everywhere. It opens as soon as the words say "near me" (once for those words), and again on
  // Enter; closed without a place, the results keep only a way to set it.
  function nearGate() { return saysNear() && !hasPlace(); }
  // "near me" and nothing else (Jason, 2026-11-10): everything near you, nearest first (up to 25, from the server), then
  // "Is there something specific…?" with the everyday kinds, each one tap
  function nearBare() { return saysNear() && !!input && !input.value.replace(new RegExp(NEARRX.source, 'gi'), ' ').trim(); }
  var NEAR_KINDS = [['coffee', 'Coffee'], ['places to eat', 'Places to eat'], ['groceries', 'Groceries'], ['gas', 'Gas'], ['pharmacy', 'Pharmacy'], ['things to do', 'Things to do'], ['where to stay', 'Where to stay'], ['parks', 'Parks'], ['health care', 'Health care']];
  function nearChooser() {
    return '<div class="tvs-intent tvs-nearwhat"><b>Is there something specific you\u2019d like to search for?</b> <span>Pick one, or type it before \u201Cnear me\u201D.</span><span class="tvs-nearrow" style="flex-wrap:wrap">'
      + NEAR_KINDS.map(function (k) { return '<button type="button" class="tvs-chipb" data-recent="' + esc(k[0] + ' near me') + '">' + esc(k[1]) + '</button>'; }).join('') + '</span></div>';
  }
  // the target icon: one tap uses your location (Auto-detect)
  var TGT = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="vertical-align:-3px;margin-right:.3rem"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>';
  var nearAsked = '';
  function nearWait() {
    return '<div class="tvs-intent tvs-nearwait" role="status"><b>Where are you?</b> <span>“Near me” needs your location: we search 2 miles around it.</span>'
      + '<span class="tvs-nearrow"><button type="button" class="tvs-btn" data-where="change">Set my location</button>'
      + '<button type="button" class="tvs-chipb" data-near-detect title="Use my location" aria-label="Use my location">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="vertical-align:-3px;margin-right:.3rem"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>Use my location</button></span></div>';
  }
  function nearModal(force) {   // the place picker, docked in the panel as the Location line's pencil opens it
    if (!panel || panel.classList.contains('tvs-placing') || typeof openLocationModal !== 'function') return;
    if (!force && nearAsked === nearKey()) return;   // once for these words; Enter or the button asks again
    nearAsked = nearKey();
    nearPending = true;   // the place it sets is for "near me": 2 mi
    openLocationModal({ near: true });
  }
  // the town box: our places first, then towns, as the place picker finds them; a ZIP as it is
  var nearT = null, nearSeq = 0, nearOpts = [];
  function nearType(inp) {
    clearTimeout(nearT);
    var q = inp.value.trim(), box = inp.closest('.tvs-nearask, .tvs-askplace').querySelector('.tvs-nearlist');
    if (q.length < 2) { box.hidden = true; return; }
    nearT = setTimeout(function () {
      var my = ++nearSeq;
      Promise.all([
        fetch('/api/place.php?action=find&q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).catch(function () { return {}; }),
        fetch('/api/suggest.php?q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).catch(function () { return {}; })
      ]).then(function (res) {
        if (my !== nearSeq) return;
        var out = [], seen = {};
        function add(o) { var k = String(o.label).toLowerCase().replace(/,\s*[a-z]{2}$/, ''); if (!seen[k]) { seen[k] = 1; out.push(o); } }   // "Show Low, AZ" once
        if (/^\d{5}$/.test(q)) add({ label: 'ZIP ' + q, sub: '', zip: q });
        (res[0].places || []).forEach(function (p) { add(p.level ? { label: p.label, sub: p.sub, level: p.level, slug: p.slug, area: p.area } : { label: p.label, sub: p.sub, city: p.city, state: p.state }); });
        (res[1].suggestions || []).forEach(function (x) { if (x.type === 'city') add({ label: x.label, sub: x.sub, city: x.label, state: x.sub }); });
        nearOpts = out.slice(0, 6);
        box.innerHTML = nearOpts.length ? nearOpts.map(function (o, i) { return '<button type="button" class="tvs-row" role="option" data-near-pick="' + i + '"><span><b>' + esc(o.label) + '</b><small>' + esc(o.sub || '') + '</small></span></button>'; }).join('')
          : '<p class="tvs-note">No town by that name yet. Try a nearby town or a ZIP.</p>';
        box.hidden = false;
      });
    }, 250);
  }
  function nearPick(o) {
    if (!o || typeof applyLocation !== 'function') return;
    nearPending = saysNear();
    if (o.level) applyLocation({ scope: { level: o.level, slug: o.slug, label: o.area || o.label } }, 'picked');
    else if (o.zip) applyLocation({ zip: o.zip }, 'picked');
    else applyLocation({ state: o.state || 'all', city: o.city }, 'picked');
  }
  window.addEventListener('tv:location', function () { setReach(nearPending ? REACH_NEAR : REACH_DEF); nearPending = false; if (panel) { head(); if (input.value.trim().length >= 2) run(); } });   // "Search in" scope; a saved search's own place
  // "Search in": the brand sections (the header's menu) and topics
  var QUICK = [['near', 'Near me'], ['open', 'Open now'], ['phone', 'Has a phone'], ['website', 'Has a website'], ['fav', 'Favorites']];
  // "Search Directory" (decisions/0058 §35): All, then the four sections by what they hold
  var SCOPES = [['', 'All'], ['f:explore', 'Discovery'], ['f:local', 'Directory'], ['f:social', 'Community'], ['f:market', 'Marketplace'], ['topics', 'Topics']];
  var SV = function (d, w) { return '<svg viewBox="0 0 24 24" width="' + (w || 18) + '" height="' + (w || 18) + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>'; };
  var IC = { search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>', pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>', pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    plus: '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>', clear: '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>',
    book: '<rect x="5" y="2" width="15" height="20" rx="2"/><path d="M3 6h3M3 12h3M3 18h3"/><circle cx="12.5" cy="10" r="2.5"/><path d="M8.5 17c.6-2 2.2-3 4-3s3.4 1 4 3"/>' };

  // a category's name without its trade codes ("…Supplies-Retail-New", "(Whls)"); "-Used" stays (SearchText::catLabel)
  function catName(n) {
    var x = String(n || '').trim().replace(/\s*\((whls|mfrs|wholesale|retail)\)\s*$/i, ''), was;
    do { was = x; x = x.replace(/-(retail|wholesale|new)$/i, ''); } while (x !== was);
    return x.trim() || String(n || '').trim();
  }
  // a category in Suggestions only when a word typed is one of its words ("down" isn't Downspouts); a long word may be
  // its root ("plumber" is Plumbing)
  function catFits(label, q) {
    var lw = catName(label).toLowerCase().split(/[^a-z0-9]+/).filter(Boolean);
    return String(q || '').toLowerCase().split(/[^a-z0-9]+/).filter(function (w) { return w.length >= 3; }).some(function (w) {
      return lw.some(function (l) { return l === w || l === w + 's' || w === l + 's' || l === w + 'es' || (w.length >= 5 && l.length >= 5 && l.slice(0, 5) === w.slice(0, 5)); });
    });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function isPhone() { return window.matchMedia && matchMedia('(max-width: 767px)').matches; }
  function scope() {
    // location-scope.js keeps the visitor's place in a top-level `let currentLocation` (not on window)
    var loc = (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null;
    return loc && loc.scope && loc.scope.slug ? { level: loc.scope.level, slug: loc.scope.slug, town: /^.{2,60},\s*[A-Z]{2}$/.test(loc.scope.label || '') ? loc.scope.label : '' } : null;
  }
  // A place outside our areas (set by state, city or ZIP, or Auto-detect: "Seattle, WA") has no area slug; it still goes
  // with the search, so search answers there instead of asking where again.
  function loose(p) {
    var l = (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null;
    if (!l || (l.scope && l.scope.slug)) return false;
    var any = false;
    if (l.city) { p.set('city', l.city); any = true; }
    if (l.state && l.state !== 'all') p.set('state', l.state);
    if (l.zip) { p.set('zip', l.zip); any = true; }
    if (l.lat != null && l.lon != null && l.lat !== '' ) { p.set('plat', String(l.lat)); p.set('plon', String(l.lon)); any = true; }
    return any;
  }
  function recents() { try { return JSON.parse(localStorage.getItem('tv_recent_search') || '[]'); } catch (x) { return []; } }
  function setRecents(r) { try { localStorage.setItem('tv_recent_search', JSON.stringify(r)); } catch (x) {} }
  // The scope token sits in the search field itself ("In: Stories ×"); Backspace at the start removes it.
  function paintToken() {
    [root, panel && panel.querySelector('.tvs-top')].forEach(function (box) {
      if (!box) return;
      var t = box.querySelector('.tvs-token'), inp = box.querySelector('.tvs-in, .tvs-in2');
      var lab = (SCOPES.filter(function (x) { return x[0] === scopeK; })[0] || [])[1];
      if (!scopeK) { if (t) t.remove(); return; }
      if (!t) { t = document.createElement('span'); t.className = 'tvs-token'; inp.parentNode.insertBefore(t, inp); }
      t.innerHTML = 'In: ' + esc(lab) + ' <button type="button" aria-label="Search everything again">&times;</button>';
      t.querySelector('button').onclick = function (e) { e.stopPropagation(); setScope(''); };
    });
  }
  function setScope(k) { scopeK = k; paintToken(); if (!panel) return; if (input.value.trim().length >= 2 || scopeK) run(); else home(); }
  function resultCount() {   // what the last full search found (for the counts: was anything found, never who)
    var d = last || {}, s = d.situation, n = d.results ? (d.results.items || []).length + ((d.results.beyond && d.results.beyond.items) || []).length : 0;
    if (s) s.needs.forEach(function (x) { n += x.items.length; });
    return n + (d.top ? 1 : 0);
  }
  function remember(q) {
    q = String(q || '').trim(); if (q.length < 2) return;
    try { if (lastQS && navigator.sendBeacon) navigator.sendBeacon(API + '?count=1&r=' + resultCount() + '&' + lastQS); } catch (x) {}   // presets learn what's asked here (never who)
    // the platform's counts (decisions/0058 §13): the words, cleaned, where, and whether anything was found; never who
    if (window.tvCount) {
      var d = last || {}, n = d.results ? d.results.total : (d.groups || []).reduce(function (a, g) { return a + (g.total || g.items.length || 0); }, 0);
      tvCount({ m: 'search', q: q, p: placeLabel(), r: n, z: n ? '' : 1 });
    }
    try { var r = recents().filter(function (x) { return x.toLowerCase() !== q.toLowerCase(); }); r.unshift(q); localStorage.setItem('tv_recent_search', JSON.stringify(r.slice(0, 8))); } catch (x) {}
    recentList();
  }

  var wantPlace = false;   // opened from a Where box or pin: the place picker shows first, inside the window
  function open() {
    if (panel) return;
    panel = document.createElement('div'); panel.className = 'tvs-panel'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'Search');
    var dim = document.createElement('div'); dim.className = 'tvs-dim'; dim.addEventListener('click', function () { close(); });
    // its own window (Jason, 2026-11-05): a pop-up over the dimmed page on a computer, the whole screen on a phone; it has
    // its own field and the place, so nothing on the page behind is needed. A click on the dimmed page, ×, or Esc closes it.
    var pop = !isPhone();
    panel.classList.add('full'); if (pop) { panel.classList.add('pop'); dim.classList.add('pop'); }
    document.body.appendChild(dim); document.body.appendChild(panel);
    panel.innerHTML = '<div class="tvs-top"><input class="tvs-in2" type="search" placeholder="' + esc(input.placeholder) + '" autocomplete="off" aria-label="Search">'
      // on a computer, the place is one Town or ZIP box with the target beside it (Jason, 2026-11-11: "choose a place should
      // have been replaced with the simple town/autodetect"; js/location-scope.js wirePlaceBox); × in it clears the place
      // (2026-11-11: "Cant reset the location from desktop"). On a phone, the pin opens the picker.
      + (pop && typeof window.wirePlaceBox === 'function' ? '<span class="tv-pbox" data-place-box data-own-clear></span>'
        : '<button type="button" class="tvs-pin2" data-where="change" aria-label="Choose where to search" title="Choose where to search"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><span class="tvs-pl2"></span></button>')
      + '<button type="button" class="tvs-x" aria-label="Close search">&times;</button></div><div class="tvs-body"></div>';
    var pbx = panel.querySelector('[data-place-box]');
    if (pbx) { window.wirePlaceBox(pbx); var pbX = pbx.querySelector('.tv-pbox-x'); if (pbX) pbX.setAttribute('data-where', 'all'); }   // × clears it as the search's own does
    var i2 = panel.querySelector('.tvs-in2'); i2.value = input.value; i2.addEventListener('input', function () { input.value = i2.value; drop = []; radius = 0; queue(); });
    // back in the words box with the place picker showing: back to the results (the place stays as chosen)
    i2.addEventListener('focus', function () { if (panel && panel.classList.contains('tvs-placing') && typeof closeLocationModal === 'function') closeLocationModal(); });
    i2.addEventListener('keydown', keys);
    setTimeout(function () {
      if (wantPlace) { wantPlace = false; if (typeof openLocationModal === 'function') openLocationModal(); return; }   // opened for the place: its picker, inside
      i2.focus(); if (i2.value) i2.select();   // words already there: selected, so typing replaces them
    }, 0);
    document.documentElement.style.overflow = 'hidden';
    placeTag();
    frame();
    panel.addEventListener('click', clicks);
    paintToken();
    panel.addEventListener('change', changes);
    panel.addEventListener('input', slide);
    panel.addEventListener('input', function (e) { if (e.target.classList.contains('tvs-nearin')) nearType(e.target); });
    panel.addEventListener('keydown', function (e) { if (e.target.classList.contains('tvs-nearin') && e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); if (nearOpts[0]) nearPick(nearOpts[0]); } });
    try { history.pushState({ tvsearch: 1 }, ''); pushed = true; } catch (x) {}
    if (input.value.trim().length >= 2) run(); else home();   // before typing: the place's presets
  }
  // Missed searches (decisions/0061): how each search turned out, sent as counts of the words, area and day. Never who.
  // A search is "left" when another one starts or the panel closes; if nothing was opened from it, that's a miss.
  var track = null;
  function outcome(list, qs) { try { if (qs && navigator.sendBeacon) navigator.sendBeacon(API + '?outcome=' + list.join(',') + '&' + qs); } catch (x) {} }
  function settle(how) {
    if (!track || track.done) return; track.done = true;
    if (track.opened || track.zero || track.unread || track.noplace) return;   // found something, or already counted as a miss
    var o = [how === 'again' && Date.now() - track.t < 60000 ? 'again' : 'no_open'];
    if (track.offered) o.push('ignored');
    outcome(o, track.qs);
  }
  // Predictions (decisions/0061, Jason 2026-10-02): the words as they stood when the panel last predicted. A tap on one
  // is "picked"; leaving without a tap, Enter or a search is "left_pred" (predictions showed) or "left_none" (only
  // "Search for…" did), so Admin can see predictions that miss or don't connect. Words, area and day only.
  var ptrack = null;
  function predQs(q) { var s = placeOver || scope(), p = new URLSearchParams({ q: q }); if (s) { p.set('level', s.level); p.set('slug', s.slug); if (s.town) p.set('town', s.town); } else loose(p); return p.toString(); }
  function predLeft() { if (ptrack && !ptrack.done) { ptrack.done = true; outcome([ptrack.shown ? 'left_pred' : 'left_none'], ptrack.qs); } ptrack = null; }
  window.addEventListener('pagehide', function () { predLeft(); settle('close'); });   // left the page some other way
  function landed(d) {
    if (!d._kept) return;   // only searches someone chose (Enter, a tap): not the panel reopening on the same words
    settle('again');
    var n = d.results ? (d.results.items || []).filter(function (x) { return x.kind !== 'topic'; }).length + ((d.results.beyond && d.results.beyond.items) || []).length : 0;
    if (d.top) n++;
    if (d.situation && d.situation.needs) d.situation.needs.forEach(function (x) { n += x.items.length; });
    track = { qs: d._qs, t: Date.now(), opened: false, done: false, unread: !!d.unread, noplace: !!(d.situation && d.situation.need_place),
      zero: n === 0 && !d.unread && !(d.situation && d.situation.need_place) && !d.elsewhere, offered: !!((d.situation_near || []).length || (d.predict || []).length) };
    var o = ['search']; if (track.unread) o.push('unread'); if (track.zero) o.push('zero'); if (track.noplace) o.push('noplace');
    outcome(o, d._qs);
  }
  function close(fromBack) {
    predLeft();
    settle('close');
    if (!panel) return;
    var qc = input.value.trim(); if (qc.length >= 3 && last && last._q === qc) remember(qc);   // they searched and saw results: it's a recent search
    if (panel.querySelector('.lm-box') && typeof closeLocationModal === 'function') closeLocationModal();   // the place picker goes back where it lives first
    panel.remove(); panel = null;
    var d = document.querySelector('.tvs-dim'); if (d) d.remove();
    var pk = document.querySelector('.tvs-peek'); if (pk) pk.remove();
    document.documentElement.style.overflow = '';
    if (pushed && fromBack !== true) { pushed = false; try { history.back(); } catch (x) {} }
    pushed = false;
    input.blur();
    // a page that shows the results itself (the directory): words put on its list with Enter or "See them all" stay there;
    // closing any other way (×, Escape, a tap outside) clears its search, back to the whole list (Jason, 2026-11-11: "clear the
    // list's filter")
    if (typeof window.tvSearchSync === 'function' && !applied) { input.value = ''; window.tvSearchSync(''); }
    applied = false;
  }
  window.addEventListener('popstate', function () { if (panel) { pushed = false; close(true); } });

  // The panel (decisions/0058 §35): a head row (Location, Search Directory); Search Results and Suggestions on the left;
  // Quick Search, Recent Searches and the Address Book on the right. Phones stack them.
  function frame() {
    var b = panel.querySelector('.tvs-body'); if (!b) return;
    var hb = function (attr, icon, label) { return '<button type="button" class="tvs-ib" ' + attr + ' title="' + label + '" aria-label="' + label + '">' + SV(IC[icon], 17) + '</button>'; };
    // (Jason, 2026-11-11) the order is: does it need a place (the Location line, asking there when it does), what the
    // words mean, then where to go; no "Search Results:" heading over it
    b.innerHTML = '<div class="tvs-hd"></div><div class="tvs-locask" style="margin:0 0 .35rem"></div><div class="tvs-cols"><div class="tvs-col tvs-main"><div class="tvs-res"><div class="tvs-t1"></div><div class="tvs-full"></div></div>'
      + '<div class="tvs-sugw"><h3 class="tvs-h">Suggestions:</h3><div class="tvs-sugs"></div></div></div>'
      + '<div class="tvs-col tvs-side"><h3 class="tvs-h">Quick Search:<span class="tvs-hbs">' + hb('data-qadd', 'plus', 'Add a quick search') + hb('data-pedit', 'pen', 'Change your quick searches') + '</span></h3>'
      + '<div class="tvs-saved"></div><div class="tvs-presets"></div><div class="tvs-qf"></div>'
      + '<h3 class="tvs-h">Recent Searches:<span class="tvs-hbs">' + hb('data-clear-recent', 'clear', 'Clear recent searches') + '</span></h3><div class="tvs-recent"></div>'
      + '<a class="tvs-ab" href="/user/dashboard.php#addressbook"><span>Looking for a specific person<b>Search Your Address Book</b></span><span class="tvs-abi">' + SV(IC.book, 22) + '</span></a></div></div>';
    head(); recentList(); presets(); loadSaved(); filters();
  }
  // The standard filters, at the foot of Quick Search: on the directory they switch its filters in place; elsewhere
  // they open it (decisions/0058 §39)
  function filters() {
    var box = panel && panel.querySelector('.tvs-qf'); if (!box) return;
    var qs0 = window.tvQuick ? window.tvQuick.state() : {};
    box.innerHTML = QUICK.map(function (x) { var on = !!qs0[x[0]];
      return '<button type="button" class="tvs-qr tvs-qfl' + (on ? ' on' : '') + '" data-quick="' + x[0] + '" aria-pressed="' + on + '"><span>' + (on ? '&#10003; ' : '') + esc(x[1]) + '</span><i class="tvs-ftag">Filter</i></button>'; }).join('');
  }
  function head() {
    var hd = panel && panel.querySelector('.tvs-hd'); if (!hd) return;
    var loc = (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null, pl = placeOver ? placeOver.label : placeLabel();
    // the words say "near me" and the place is where the device is (Auto-detect): say so, with the town
    if (pl && !placeOver && loc && loc.source === 'device' && saysNear()) pl = 'Near you (' + String(pl).split('·')[0].trim() + ')';
    // how it was chosen first (Jason, 2026-11-11: "visit hides how the place was chosen: I clicked autodetect"); the same words
    // as the map's (js/location-scope.js tvPlaceWhy)
    var why = placeOver ? 'from your saved search' : !loc ? '' : (typeof tvPlaceWhy === 'function' && tvPlaceWhy()) ? tvPlaceWhy().why : '';
    // on a phone the tools fold into one line that says what they're set to; a tap opens them (kept in this browser)
    var openT = false; try { openT = localStorage.getItem('tv_search_tools') === '1'; } catch (x) {}
    hd.classList.toggle('open', openT);
    var sk = SCOPES.filter(function (x) { return x[0] === scopeK; })[0];
    // the Location line is the toggle: where, then what Search Directory and Distance are set to; ✎ changes the place
    var tg = '<div class="tvs-tooltg"><button type="button" class="tvs-tbtn" data-tools aria-expanded="' + openT + '" aria-label="Search tools: ' + (openT ? 'hide' : 'show') + '"><span class="tvs-pinc">' + SV(IC.pin, 15) + '</span>'
      + '<span class="tvs-tsum">Location: <b>' + esc(pl ? String(pl).split('·')[0].trim() : 'Everywhere') + '</b>' + (function () {   // said once: no "· All · ∞" after Everywhere
          var bits = []; if (sk && sk[1] !== 'All') bits.push(sk[1]); if ((pl || placeOver || scope()) && !(scope() && /^(hub|geo_hub)$/.test(String(scope().level || '')))) bits.push(REACH[reachI][1]);
          return bits.length ? ' <small>· ' + esc(bits.join(' · ')) + '</small>' : ''; })() + '</span>'
      + '<span class="tvs-tcar" aria-hidden="true">' + (openT ? '\u25B4' : '\u25BE') + '</span></button>'
      + (pl ? '<button type="button" class="tvs-ib" data-where="all" title="Search everywhere (no place)" aria-label="Clear the place: search everywhere">\u00D7</button>' : '')
      + '<button type="button" class="tvs-ib" data-where="change" title="Change where to search" aria-label="Change where to search">' + SV(IC.pen, 15) + '</button></div>';
    hd.innerHTML = tg + '<span class="tvs-loc"' + (why ? ' title="' + esc(why) + '"' : '') + '><span class="tvs-pinc">' + SV(IC.pin, 17) + '</span>Location: <b>' + esc(pl ? String(pl).split('·')[0].trim() : 'Everywhere') + '</b>'
      + (pl ? '<button type="button" class="tvs-ib" data-where="all" title="Search everywhere (no place)" aria-label="Clear the place: search everywhere">\u00D7</button>' : '')
      + '<button type="button" class="tvs-ib" data-where="change" title="Change where to search" aria-label="Change where to search">' + SV(IC.pen, 16) + '</button></span>'
      + '<label class="tvs-dir">Search Directory: <select class="tvs-dsel" aria-label="Search directory">' + SCOPES.slice(0, 5).map(function (x) {
          return '<option value="' + x[0] + '"' + (x[0] === scopeK ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select></label>'
      + ((pl || placeOver || scope()) && !(scope() && /^(hub|geo_hub)$/.test(String(scope().level || ''))) ? '<label class="tvs-reach">Distance: <input type="range" class="tvs-rng" min="0" max="' + (REACH.length - 1) + '" step="1" value="' + reachI + '" aria-label="How far to search"'
      + ' aria-valuetext="' + esc(REACH[reachI][1]) + '"><output class="tvs-rngv">' + esc(REACH[reachI][1]) + '</output></label>'
      : '<label class="tvs-reach off" title="Choose a place to limit distance">Distance: <input type="range" class="tvs-rng" min="0" max="' + (REACH.length - 1) + '" value="' + (REACH.length - 1) + '" disabled aria-label="How far to search: choose a place first"><output class="tvs-rngv">\u221E</output></label>');   // no place: nothing to measure from
  }
  function body() { return panel && panel.querySelector('.tvs-full'); }
  function t1Box() { return panel && panel.querySelector('.tvs-t1'); }
  function recentList() {
    var box = panel && panel.querySelector('.tvs-recent'); if (!box) return;
    var r = recents().slice(0, 6);
    box.innerHTML = r.length ? r.map(function (q) { return '<div class="tvs-li"><button type="button" class="tvs-lb" data-recent="' + esc(q) + '">' + esc(q) + '</button><button type="button" class="tvs-lx" data-unrecent="' + esc(q) + '" aria-label="Remove ' + esc(q) + '">&times;</button></div>'; }).join('')
      : '<p class="tvs-empty">Your recent searches show here. They stay in this browser only.</p>';
  }
  // Suggestions: a likely word, the standard filters (Quick picks: on the directory they switch its filters in
  // place; elsewhere they open it), and the place's own searches not already in Quick Search
  function sugs(d) {
    var box = panel && panel.querySelector('.tvs-sugs'); if (!box) return;
    var qs0 = window.tvQuick ? window.tvQuick.state() : {}, q = input.value.trim().toLowerCase(), h = '';
    var line = function (attrs, label, tag, on) { return '<button type="button" class="tvs-sg' + (on ? ' on' : '') + '" ' + attrs + (attrs.indexOf('title=') < 0 ? ' title="' + esc(label) + '"' : '') + '><span>' + (on ? '&#10003; ' : '') + esc(label) + '</span><i>' + esc(tag) + '</i></button>'; };
    if (d && d.did_you_mean && !(d.did_you_mean_all && d.did_you_mean_all.length)) h += line('data-recent="' + esc(d.did_you_mean) + '"', 'Did you mean “' + esc(d.did_you_mean) + '”?', 'Spelling');
    // the categories that match: all of that kind, in the place
    var pl = String((placeOver ? placeOver.label : placeLabel()) || '').split('·')[0].trim(), cats = [];
    if (d) {
      (d._cats || []).concat((d.results && d.results.items) || []).concat(((d.results && d.results.beyond && d.results.beyond.items) || [])).forEach(function (x) { if (x.kind === 'topic') cats.push(x); });
      (d.groups || []).forEach(function (g) { if (g.kind === 'topics') g.items.forEach(function (x) { cats.push(x); }); });
    }
    var seen = {};
    if (d && d.situation) cats = [];   // a situation is the answer: its needs already say what kind of place helps
    cats.forEach(function (c) { var cn = catName(c.label); if (seen[cn] || !catFits(cn, q)) return; seen[cn] = 1; h += line('data-recent="' + esc(cn) + '" title="All ' + esc(cn) + (pl ? ' in ' + esc(pl) : '') + '"', 'All ' + cn + (pl ? ' in ' + pl : ''), 'Category'); });   // searched here: in your place, then beyond
    // nothing has all the words: each one on its own, with how many it finds
    ((d && d.split) || []).forEach(function (x) { h += line('data-recent="' + esc(x.q) + '"', 'Search “' + x.q + '”', x.count + (x.count >= 40 ? '+' : '') + ' found'); });
    var mine = myList().map(function (m) { return m.toLowerCase(); });
    h += (lastPresets.list || []).filter(function (x) { var k = String(x.q).toLowerCase(); return k !== q && mine.indexOf(k) < 0; }).slice(6, 10)   // Quick Search shows the first six
      .map(function (x) { return line('data-recent="' + esc(x.q) + '"', x.label, 'Search'); }).join('');
    box.innerHTML = h;
    var wrap = box.closest('.tvs-sugw'); if (wrap) wrap.hidden = !h;   // no heading over nothing
  }
  function home() {
    var b = body(); if (!b) return;
    var t1 = t1Box(); if (t1) t1.innerHTML = '';
    locAsk(null);   // nothing typed: nothing to ask
    head(); recentList(); sugs(null);
    var pl = placeOver ? placeOver.label : placeLabel(), where = pl ? String(pl).split('·')[0].trim() : '';
    var tryList = (where ? ['Open now in ' + where, 'Groups in ' + where, 'Stories about ' + where, where + ' population']   // the place's own searches are in Quick Search
      : ['Greer Arizona', 'physical therapy near Show Low', 'groups in St. Johns', 'Show Low population']);
    b.innerHTML = '<p class="tvs-empty">Type to search places, listings, stories, groups and topics' + (where ? ' in ' + esc(where) : '') + '. Or try:</p>'
      + tryList.map(function (q) { return '<button type="button" class="tvs-row tvs-try" data-recent="' + esc(q) + '"><span><b>' + esc(q) + '</b></span><span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>'; }).join('');
  }
  // One-tap searches: the person's own phrases (🔖, kept on this device) and the place's suggestions. One ✎ Edit
  // turns the list into a small editor: change or remove a phrase, add a new one, or add a suggestion to yours.
  var lastPresets = { title: 'Try', list: [], mine: {} };   // mine: your own quick searches' counts here, by their words
  function myList() {
    try { var a = JSON.parse(localStorage.getItem('tv_my_searches') || '[]'); return Array.isArray(a) ? a.filter(function (x) { return typeof x === 'string' && x.trim(); }).slice(0, 12) : []; } catch (x) { return []; }
  }
  function setMyList(a) {
    var seen = {}, out = [];
    a.forEach(function (q) { q = String(q).trim().slice(0, 80); var k = q.toLowerCase(); if (q && !seen[k]) { seen[k] = 1; out.push(q); } });
    try { localStorage.setItem('tv_my_searches', JSON.stringify(out.slice(0, 12))); } catch (x) {}
  }
  function presetRow(label, q, count) {
    return '<button type="button" class="tvs-qr" data-recent="' + esc(q) + '"><span>' + esc(label) + '</span>' + (count ? '<i class="tvs-n">' + count + '</i>' : '') + '</button>';
  }
  // Quick Search: your own searches (kept on this device) first, then the place's, each with how many it finds (yours
  // counted for where you are too: Jason, 2026-11-22, "Count them"; none for what changes by the minute)
  function presetList(title, list) {
    lastPresets = { title: title, list: list, mine: lastPresets.mine || {} };
    var mine = myList(), low = mine.map(function (m) { return m.toLowerCase(); });
    return mine.map(function (q) { return presetRow(q, q, lastPresets.mine[q.toLowerCase()] || 0); }).join('')
      + list.filter(function (x) { return low.indexOf(String(x.q).toLowerCase()) < 0; }).slice(0, 6).map(function (x) { return presetRow(x.label, x.q, x.count); }).join('');
  }
  function editPhrases() {
    var box = panel && panel.querySelector('.tvs-presets'); if (!box) return;
    var mine = myList(), sug = lastPresets.list.filter(function (x) { return mine.map(function (m) { return m.toLowerCase(); }).indexOf(String(x.q).toLowerCase()) < 0; });
    box.innerHTML = '<div class="tvs-sh"><span>Your quick searches</span><button type="button" class="tvs-link" data-pdone>Done</button></div>'
      + '<div class="tvs-pe">' + mine.map(function (q) { return peRow(q); }).join('') + '</div>'
      + '<div class="tvs-li tvs-padd"><input class="tvs-phr" type="text" maxlength="80" placeholder="Add a search, e.g. vegan food in Show Low" aria-label="Add a search phrase"><button type="button" class="tvs-lx" data-pnew aria-label="Add" title="Add">+</button></div>'
      + (sug.length ? '<div class="tvs-sh tvs-sh2"><span>Add from ' + esc(lastPresets.title) + '</span></div>' + sug.map(function (x) {
          return '<div class="tvs-li"><span class="tvs-lb tvs-plain">' + esc(x.label) + '</span><button type="button" class="tvs-lx" data-padd="' + esc(x.q) + '" aria-label="Add ' + esc(x.label) + ' to your searches" title="Add to yours">+</button></div>'; }).join('') : '')
      + '<p class="tvs-empty">Tap a phrase to change it. Kept on this device.</p>';
    var add = box.querySelector('.tvs-phr');
    add.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); addPhrase(add.value); } });
    add.focus();
  }
  function peRow(q) {
    return '<div class="tvs-li"><span aria-hidden="true">' + SV('<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>', 15) + '</span><input class="tvs-phr" type="text" maxlength="80" value="' + esc(q) + '" aria-label="Search phrase"><button type="button" class="tvs-lx" data-pdel aria-label="Remove" title="Remove">&times;</button></div>';
  }
  function editedList() { var box = panel && panel.querySelector('.tvs-pe'); return box ? [].map.call(box.querySelectorAll('.tvs-phr'), function (i) { return i.value; }) : myList(); }
  function addPhrase(q) {
    q = String(q || '').trim(); if (!q) return;
    var box = panel.querySelector('.tvs-pe'); if (!box) return;
    box.insertAdjacentHTML('beforeend', peRow(q)); setMyList(editedList());
    var add = panel.querySelector('.tvs-padd .tvs-phr'); if (add) { add.value = ''; add.focus(); }
  }
  function loadSaved() {
    var box = panel && panel.querySelector('.tvs-saved'); if (!box) return;
    fetch('/user/api/saved-searches.php', { credentials: 'include' }).then(function (r) { return r.json().then(function (d) { return { st: r.status, d: d }; }); }).then(function (x) {
      box = panel && panel.querySelector('.tvs-saved'); if (!box) return;
      if (x.st === 401 || !x.d.ok) { box.innerHTML = ''; return; }   // signed out, or not switched on: Quick Search alone
      savedCsrf = x.d.csrf; savedIds = {};
      box.innerHTML = x.d.saved.length ? x.d.saved.map(function (s) {
        savedIds[s.q + '|' + (s.place ? s.place.slug : '') + '|' + s.scope] = s.id;
        return '<div class="tvs-li tvs-sv"><button type="button" class="tvs-lb" data-saved="' + s.id + '"><span aria-hidden="true">★</span> ' + esc(s.label)
          + (s.place ? ' <span class="tvs-muted">· ' + esc(s.place.label) + '</span>' : '') + '</button><button type="button" class="tvs-lx" data-unsave="' + s.id + '" aria-label="Unsave ' + esc(s.label) + '">&times;</button></div>';
      }).join('') : '';
      box._list = x.d.saved;
    }).catch(function () { if (box) box.innerHTML = ''; });
  }
  function savePost(body) {
    return fetch('/user/api/saved-searches.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': savedCsrf || '' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json(); });
  }
  // One-tap questions for the place, from the system (decisions/0053 §5): what's there, the time, what's asked here
  function presets() {
    var s = scope(), p = new URLSearchParams({ presets: '1' }), now = new Date(), near = s ? '' : String(placeLabel()).split('·')[0].trim();
    if (/^(set location|current location)$/i.test(near)) near = '';
    if (s) { p.set('level', s.level); p.set('slug', s.slug); if (s.town) p.set('town', s.town); } else if (!loose(p) && near) p.set('near', near);
    p.set('at', now.getDay() + ',' + (now.getHours() * 60 + now.getMinutes()));
    var mine = myList();
    if (mine.length) {   // your own, counted as their search would find them here: the same reach and section as a search
      p.set('mine', JSON.stringify(window.tvSafe ? tvSafe(mine) : mine));
      if (!radius) p.set('reach', String(REACH[reachI][0]));
      var sec0 = document.querySelector('meta[name="tv-sec"]'); if (sec0 && sec0.content) p.set('track', sec0.content);
    }
    fetch(API + '?' + p.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
      var mc = {}; (d.mine || []).forEach(function (x) { if (x && x.q) mc[String(x.q).toLowerCase()] = x.count || 0; }); lastPresets.mine = mc;
      var box = panel && panel.querySelector('.tvs-presets'); if (!box || ((!d.presets || !d.presets.length) && !mine.length)) return;
      d.presets = d.presets || [];
      if (box.querySelector('.tvs-pe')) { lastPresets = { title: d.place ? 'Around ' + d.place : 'Try', list: d.presets }; return; }   // editing: don't replace it
      box.innerHTML = presetList(d.place ? 'Around ' + d.place : 'Try', d.presets);
      sugs(input.value.trim().length >= 2 ? last : null);
    }).catch(function () {});
  }

  // Two tiers (decisions/0060): suggestions while typing (about 120ms after a key), the full search on Enter, a tap,
  // or a longer pause (about 700ms)
  var sTimer = null, sSeq = 0;
  // decisions/0061: while typing, suggestions only; results on Enter or a tap (half-typed words never make results)
  // suggestions wait for three letters (Jason, 2026-11-11: "Wait for 3 letters"); before that, recent and quick searches
  var MIN_TYPED = 3;
  function queue() {
    clearTimeout(timer); clearTimeout(sTimer);
    var q = input.value.trim(), b = body(), box = t1Box();
    // the words changed: the last results aren't an answer to them any more, so they go at once
    if (b && q.length >= MIN_TYPED && (!last || last._q !== q)) b.innerHTML = '';   // the starter list and old results go
    else if (b && q.length < MIN_TYPED && last) home();
    if (q.length < MIN_TYPED) predLeft();   // the words were cleared: whatever was predicted for them was left
    // while typing, only the predictions show (Jason, 2026-10-02): the results, Quick Search and Suggestions wait for a
    // tap on a prediction or Enter. Less to load, nothing half-read on screen.
    if (panel) panel.classList.toggle('tvs-typing', q.length >= MIN_TYPED);
    // and there's always a way to search them: Enter, or this line (straight away, before any suggestion arrives)
    if (box) { if (q.length >= MIN_TYPED) { var er = box.querySelector('.tvs-enter'); if (er) er.outerHTML = enterRow(q); else box.innerHTML = box.innerHTML + enterRow(q); } else box.innerHTML = ''; }
    sTimer = setTimeout(suggest, 120);
  }
  function enterRow(q) {
    // (Jason, 2026-11-11: "Remove search for, except on phone, use a minimal line to see all results"): on a computer Enter
    // searches; on a phone one plain line at the foot of the suggestions does
    if (!isPhone()) return '';
    return '<button type="button" class="tvs-enter" data-tier2 style="display:block;width:100%;text-align:left;background:none;border:0;border-top:1px solid #EADFC4;padding:.6rem .25rem;margin-top:.35rem;font:inherit;font-size:.92rem;color:#92400E;cursor:pointer">See all results for \u201C<b>' + esc(q) + '</b>\u201D \u203A</button>';
  }
  // "Did you mean…?": each a whole search, the changed words in bold, with what it would answer
  function dymHtml(list) {
    return '<div class="tvs-dym"><p class="tvs-lbl">Did you mean</p>' + list.map(function (x) {
      var fixed = {}; (x.fixed || []).forEach(function (f) { fixed[f[1]] = 1; });
      var lab = String(x.q).split(' ').map(function (w) { return fixed[w] ? '<b>' + esc(w) + '</b>' : esc(w); }).join(' ');
      return rmWrap('<button type="button" class="tvs-row tvs-s1 tvs-dymr" data-recent="' + esc(x.q) + '"><span><span class="tvs-dymq">' + lab + '</span><small>' + (x.situation ? esc(x.situation) : 'Search these words') + '</small></span><span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>', x.q, 'dym');
    }).join('') + '</div>';
  }
  // a suggestion that doesn't fit can be removed (decisions/0063 §3): staff remove it for these words; a visitor's ×
  // is counted (never who) and enough of them drop it. Removing teaches; nothing is edited.
  function rmWrap(row, shown, kind) {
    return '<div class="tvs-rmw">' + row + '<button type="button" class="tvs-rm" data-rm="' + esc(shown) + '" data-rk="' + kind + '" title="Not what I meant: remove" aria-label="Remove this suggestion">&times;</button></div>';
  }
  // recognized or not (decisions/0063 §2): words search doesn't know are said plainly, with Suggest it (visitors) or
  // Add it (staff, which opens the Workbench)
  function lexHtml(d, q, after) {
    var lx = d.lexicon || {}, unk = lx.unknown || [];
    if (!unk.length && !(after && lx.unread)) return '';
    var what = unk.length ? (unk.length === 1 ? 'the word \u201C' + esc(unk[0]) + '\u201D' : 'the words \u201C' + unk.map(esc).join('\u201D, \u201C') + '\u201D') : 'the phrase \u201C' + esc(q) + '\u201D';
    var word = unk.length ? unk[0] : q;
    var act = d.can_teach
      ? '<a class="tvs-lexb" href="/admin/search-misses.php?q=' + encodeURIComponent(q) + '&word=' + encodeURIComponent(word) + '">Add it</a>'
      : '<button type="button" class="tvs-lexb" data-sugword="' + esc(word) + '" data-sugq="' + esc(q) + '">Suggest it</button>';
    return '<div class="tvs-lex" role="status"><p>We don\u2019t recognize ' + what + '. ' + (d.can_teach ? 'Would you like to add it?' : 'Would you like to suggest it?') + '</p>' + act + '</div>';
  }
  function askHtml(a) {
    if (!a) return '';
    // a place on its own (decisions/0066): what about it, as choices; "Make it my place" sets it; other towns of that name
    if (a.kind === 'place') return '<div class="tvs-ask tvs-askpl"><p>' + esc(a.q) + '</p><div class="tvs-askb">' + a.options.map(function (o) {
        var inner = '<b>' + esc(o.label) + '</b><small>' + esc(o.sub || '') + '</small>';
        if (o.set) return '<button type="button" class="tvs-askc tvs-setpl" data-setplace=\'' + esc(JSON.stringify(o.set)) + '\'' + dem(a.place, 'make it my place', 'place') + '>' + inner + '</button>';
        return o.url ? '<a class="tvs-askc" href="' + esc(o.url) + '"' + dem(a.place, o.label, 'tool') + '>' + inner + '</a>' : '<button type="button" class="tvs-askc" data-recent="' + esc(o.q) + '"' + dem(a.place, o.label, 'place') + '>' + inner + '</button>';
      }).join('') + '</div>'
      + (a.alts && a.alts.length ? '<p class="tvs-muted tvs-alts">Not this one? ' + a.alts.map(function (x) { return '<button type="button" class="tvs-link" data-recent="' + esc(x.q) + '">' + esc(x.label) + '</button>'; }).join(' · ') + '</p>' : '')
      + '</div>';
    return '<div class="tvs-ask"><p>' + esc(a.q) + '</p><div class="tvs-askb">' + a.options.map(function (o) {
      return o.url ? '<a class="tvs-askc" href="' + esc(o.url) + '"><b>' + esc(o.label) + '</b><small>' + esc(o.sub || '') + '</small></a>'
        : '<button type="button" class="tvs-askc" data-recent="' + esc(o.q) + '"><b>' + esc(o.label) + '</b><small>' + esc(o.sub || '') + '</small></button>';
    }).join('<span class="tvs-or">or</span>') + '</div></div>';
  }
  var sugSit = '';
  // 2026-10-14 (speed): the request for the words before is dropped when a newer one goes, and answers already had are
  // kept for a minute, so backspacing or retyping shows them at once
  var sAbort = null, sCache = {}, sCacheN = 0;
  function sugFetch(url) {
    var hit = sCache[url];
    if (hit && Date.now() - hit.t < 60000) return Promise.resolve(hit.d);
    if (sAbort) try { sAbort.abort(); } catch (e) {}
    sAbort = window.AbortController ? new AbortController() : null;
    return fetch(url, { credentials: 'include', signal: sAbort ? sAbort.signal : undefined }).then(function (r) { return r.json(); }).then(function (d) {
      if (++sCacheN > 60) { sCache = {}; sCacheN = 0; }
      sCache[url] = { t: Date.now(), d: d };
      return d;
    });
  }
  function suggest() {
    var q = input.value.trim(), box = t1Box(); if (!box) return;
    if (q.length < MIN_TYPED) { box.innerHTML = ''; return; }
    var nr = nearReach(); if (nr || nearOn !== saysNear()) { nearOn = saysNear(); head(); }
    var s = placeOver || scope(), p = new URLSearchParams({ suggest: 1, q: q, reach: String(REACH[reachI][0]) }), my = ++sSeq;
    if (/\s$/.test(input.value)) p.set('end', '1');   // a space after the last word: it's finished, not still being typed
    if (s) { p.set('level', s.level); p.set('slug', s.slug); if (s.town) p.set('town', s.town); } else loose(p);
    var sec0 = document.querySelector('meta[name="tv-sec"]'); if (sec0 && sec0.content) p.set('track', sec0.content);   // the page decides what leads while typing too (Jason, 2026-11-11)
    sugFetch(API + '?' + p.toString()).then(function (d) {
      if (my !== sSeq || !panel || input.value.trim() !== q || !panel.classList.contains('tvs-typing')) return;   // a search was picked meanwhile
      var b2 = t1Box(); if (!b2) return;
      var h = askHtml(d.ask), rows = '';
      // what the words may mean (the kinds read from them, "Maybe? … for “jalapeno”"): one line of choices, not a card each
      var meant = (d.items || []).filter(function (it) { return it.kind === 'category' && /^Maybe\?|\u00b7 for \u201C/.test(String(it.crumb || '')); });
      var forW = meant.length ? ((/for \u201C([^\u201D]+)\u201D/.exec(String(meant[0].crumb)) || [])[1] || q) : '';
      // each a chip that reads as a link to more (Jason, 2026-11-11: "the formatting seems run together … they need to feel like
      // links to additional content"): the chip look is .tvs-meant's
      var means = meant.length ? '<div class="tvs-meant"><p class="tvs-note" style="margin:.2rem 0 .4rem"><b>\u201C' + esc(forW) + '\u201D may mean:</b></p><div class="tvs-chipr" style="margin:0 0 .5rem">' + meant.map(function (it) {
        return '<button type="button" class="tvs-chipb" data-recent="' + esc(it.q) + '" data-pick="' + esc(it.label) + '" title="See ' + esc(it.label) + '">' + esc(it.label) + ' <span aria-hidden="true">&rsaquo;</span></button>'; }).join('') + '</div></div>' : '';
      rows = (d.items || []).filter(function (it) { return meant.indexOf(it) < 0; }).map(function (it) {
        var inner = '<span><b>' + esc(it.label) + '</b><small>' + esc(it.crumb || '') + '</small></span>';
        if (it.widen) return '<button type="button" class="tvs-row tvs-s1 far" data-widen="' + esc(it.widen) + '">' + inner + '<span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>';   // past the Distance setting: widen to it
        if (it.q) return '<button type="button" class="tvs-row tvs-s1' + (it.kind === 'situation' ? ' tvs-sits' + (it.urgent ? ' urgent' : '') : '') + '" data-recent="' + esc(it.q) + '"' + (it.kind === 'category' ? ' data-pick="' + esc(it.label) + '"' : '') + '>' + inner + '<span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>';
        return '<a class="tvs-row tvs-s1' + (it.here ? '' : ' far') + '" href="' + esc(it.url) + '">' + inner + '<span class="tvs-go">' + (it.peek ? peekBtn({ id: it.id, label: it.label }) : '<span aria-hidden="true">&rsaquo;</span>') + '</span></a>';
      }).join('');
      // the full search first (Enter), then what the words might be: situations, places, kinds, towns
      // the words name a situation: its answer, previewed while typing (the nearest one or two for each need)
      var pv = '', shown = {};
      sugSit = d.preview ? q.toLowerCase() : '';   // these words name a situation (the directory page answers it here, not in its list)
      if (d.preview) {   // only the needs that have an answer; the full results say what's still missing
        if (!d.preview.need_place && !d.preview.none_here) d.preview.needs = d.preview.needs.filter(function (n) { return n.items.length; });
        pv = situationHtml(d.preview);
        d.preview.labels.forEach(function (l) { shown[l] = 1; });
        rows = rows.replace(/<button type="button" class="tvs-row tvs-s1 tvs-sits[^"]*" data-recent="[^"]*"><span><b>([^<]*)<\/b>[\s\S]*?<\/button>/g, function (m, lab) { return shown[lab.replace(/&#39;/g, "'").replace(/&amp;/g, '&')] ? '' : m; });   // not twice
      }
      // completions, Google-style (decisions/0061): what was typed, then what it may carry on to in bold; a tap searches it
      var cmp = (d.complete || []).map(function (c) {
        return rmWrap('<button type="button" class="tvs-cmp" data-recent="' + esc(c.q) + '"><span class="tvs-cmpi" aria-hidden="true">' + SV(IC.search, 14) + '</span><span>' + esc(c.typed) + '<b>' + esc(c.rest) + '</b></span></button>', c.q, 'pred');
      }).join('');
      // "Maybe try" (decisions/0093): a pattern the search noticed, offered as its own suggestion, under its own label
      var mt = (d.maybe_try || []).length ? '<p class="tvs-lbl">Maybe try</p>' + d.maybe_try.map(function (c) {
        return rmWrap('<button type="button" class="tvs-cmp" data-recent="' + esc(c.q) + '"><span class="tvs-cmpi" aria-hidden="true">' + SV(IC.search, 14) + '</span><span>' + esc(c.typed) + '<b>' + esc(c.rest) + '</b></span></button>', c.q, 'pred');
      }).join('') : '';
      // a word partway typed ("jalapen"): the word it finishes comes before "Search for" what's typed so far (Jason, 2026-11-11)
      // the last word typed: a kind read from another word ("foundation" while "found" is typed) waits below
      var lastW = (q.toLowerCase().match(/(\S+)$/) || [])[1] || '';
      if (nearGate()) { b2.innerHTML = enterRow(q) + nearWait(); nearModal(false); return; }   // "near me" typed: where?
      locAsk(d);
      // the word being typed, as the full words it's becoming, each with the intent it serves ("Mexico · a country",
      // "Mexican food · restaurants and Mexican cuisine"): before the rest (Jason, 2026-11-11)
      var its = (d.intents || []).map(function (it) { return '<button type="button" class="tvs-row tvs-s1" data-recent="' + esc(it.q) + '"><span><b>' + esc(it.label) + '</b><small>' + esc(it.means) + '</small></span><span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>'; }).join('');
      // with full words to offer, the word isn't unknown ("We don't recognize 'mexi'") and the loose kinds read from it wait
      if (its) means = '';
      // what the words mean comes first: a situation ("Found someone's belongings") with the full words; a kind read from
      // another word ("foundation" while "found" is typed) waits below the places
      var sitRows = '', lateMeans = '';
      rows = rows.replace(/<button type="button" class="tvs-row tvs-s1 tvs-sits[\s\S]*?<\/button>/g, function (m) { sitRows += m; return ''; });
      if (means && forW && forW.toLowerCase() !== lastW) { lateMeans = means; means = ''; }
      // in order (Jason, 2026-11-11): what the words mean (a spelling offer, the place asked about, the full words, the
      // situation and its answer, the kinds), then Search for what's typed (a word not finished yet: the word it finishes
      // first), the ways to keep typing, then the places and listings
      b2.innerHTML = (d.did_you_mean && d.did_you_mean.length ? dymHtml(d.did_you_mean) : (its ? '' : lexHtml(d, q, false))) + h + its + sitRows + pv + means
        + cmp + mt + (rows ? rows : '') + lateMeans + enterRow(q);   // "see all results" last, on a phone only
      // nothing to suggest for these letters: say so, never a blank panel (Jason, 2026-11-11: "found a blank results screen")
      if (!b2.querySelector('button, a, .tvs-sit, .tvs-intent')) b2.innerHTML = '<p class="tvs-note" style="margin:.4rem 0">Nothing to suggest for \u201C' + esc(q) + '\u201D yet. Keep typing, or press Enter to search it.</p>' + enterRow(q);
      fillOsm(b2);   // businesses from OpenStreetMap, where we have no listings yet (decisions/0065)
      if (q.length >= 3) ptrack = { qs: predQs(q), shown: !!(cmp || mt || h || pv || rows), done: false };   // earlier, half-typed words aren't counted
    }).catch(function () {});
  }
  function run() {
    var q = input.value.trim(), s = scope();
    if (!panel) return;
    // a prediction was picked or Enter pressed: the predictions go, the results come
    if (ptrack) ptrack.done = true;   // searched: not "left"
    panel.classList.remove('tvs-typing');
    var t1x = t1Box(); if (t1x) t1x.innerHTML = '';
    if (q.length < 2 && !scopeK) { home(); return; }
    if (nearGate()) { var bn = body(); if (bn) bn.innerHTML = nearWait(); nearModal(true); return; }   // Enter on "near me" with no place: where first, no search from everywhere
    var params = new URLSearchParams({ q: q });
    if (placeOver) { params.set('level', placeOver.level); params.set('slug', placeOver.slug); }
    else if (s) { params.set('level', s.level); params.set('slug', s.slug); if (s.town) params.set('town', s.town); }
    else loose(params);
    if (scopeK) params.set('scope', scopeK);
    if (drop.length) params.set('drop', drop.join(','));
    if (nearReach()) head();
    if (radius) params.set('radius', radius);
    else params.set('reach', String(REACH[reachI][0]));
    var now = new Date(); params.set('at', now.getDay() + ',' + (now.getHours() * 60 + now.getMinutes()));   // for "open now", in their own time
    var sec = document.querySelector('meta[name="tv-sec"]'); if (sec && sec.content) params.set('track', sec.content);   // the Chameleon Filter: the section decides what leads
    var my = ++seq;
    if (q !== (last && last._q)) funnel = '';   // new words: back to all sections
    lastQS = params.toString(); var qsNow = lastQS;
    fetch(API + '?' + params.toString(), { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (my === seq && panel) { d._q = q; d._qs = qsNow; d._kept = !!keepNext && keepNext.toLowerCase() === q.toLowerCase(); last = d; landed(d); paint(d, q); if (keepNext && keepNext.toLowerCase() === q.toLowerCase()) { keepNext = ''; remember(q); } } })
      .catch(function () { var b = body(); if (b) b.innerHTML = '<p class="tvs-note">Search is unavailable right now.</p>'; });
  }

  function tripRef(l) { if (l.kind === 'outdoors') { var i = String(l.id).replace(/^rec:/, ''), g = /^g0*(\d+)$/.exec(i); return g ? {kind: 'lm', ref: g[1], name: l.name} : {kind: 'rec', ref: i, name: l.name}; } return {kind: 'listing', ref: String(l.id), name: l.name || ''}; }   // a Peek's listing as a trip stop (js/tv-trips.js)
  function row(it, kind) {
    var sec = sectionOf({ kind: ({ listings: 'listing', stories: 'story', groups: 'group', places: 'places', topics: 'topic' })[kind] || kind, funnel: it.funnel });
    return '<a class="tvs-row" href="' + esc(it.url || '#') + '" data-kind="' + kind + '"><span><b>' + esc(it.label) + '</b>'
      + (it.sub ? '<small>' + esc(it.sub) + '</small>' : '') + '</span><span class="tvs-go">' + (sec ? '<i class="tvs-kind" aria-hidden="true">' + esc(sec) + '</i> ' : '') + (it.peek || (it.url && it.kind !== 'topic') ? peekBtn(it) : '<span aria-hidden="true">&rsaquo;</span>') + '</span></a>';
  }
  function paint(d, q) {
    var b = body(); if (!b) return;
    head(); sugs(d);
    // the full search has landed: its results replace the quick ones, unless it found nothing ("lym" on its way to Lyman)
    var R0 = d.results, notTopic = function (x) { return x.kind !== 'topic'; };
    var real = !!d.top || !!d.elsewhere || !!(R0 && (R0.items.filter(notTopic).length || (R0.beyond && R0.beyond.items.length))) || (!R0 && (d.groups || []).some(function (g) { return g.kind !== 'topics' && g.items.length; }));   // with results, the groups aren't shown
    // the full results have landed: they replace the quick list, unless it holds what the words might mean (a situation:
    // "best place to star" keeps Stargazing and Starting a business on top while its results arrive below)
    // the completions stay until one is picked or the words change, like Google's (decisions/0061)
    var t1 = t1Box(), keepCmp = function () { return t1 ? [].map.call(t1.querySelectorAll('.tvs-cmp'), function (x) { return x.outerHTML; }).join('') : ''; };
    if (t1 && real && !t1.querySelector('.tvs-sits, .tvs-sit')) t1.innerHTML = keepCmp();
    var sitH = situationHtml(d.situation);
    if (sitH && (d.situation.need_place || d.situation.none_here || d.situation.needs.some(function (n) { return n.items.length; }))) { d.elsewhere = null; d.split = []; d.did_you_mean = null; if (t1) t1.innerHTML = keepCmp(); }   // the situation answers
    var h = askHtml(d.ask);
    // on the directory, the list below follows the words from one line at the foot ("See them all in the directory list"), not
    // a second one here at the top (Jason, 2026-11-11: "Top search for and then again see them all in directory")
    // One row (decisions/0058 §19): where it's read (the place, then any category or time) on the left, Save on the right
    var chips = (d.chips || []).filter(function (c) { return !((c.type === 'kind' || c.type === 'funnel') && c.scope); })
      .sort(function (x, y) { return (x.type === 'place' ? 0 : 1) - (y.type === 'place' ? 0 : 1); });
    h += '<div class="tvs-read"><div class="tvs-chips">' + chips.map(function (c) {
        var icon = { place: SV(IC.pin, 14) + ' ', when: SV('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', 14) + ' ', why: '', who: '' }[c.type] || '';
        if (c.type === 'do') return '<span class="tvs-chip on">Going ' + esc(c.label.toLowerCase()) + (c.about ? ' <small>for ' + esc(c.about) + '</small>' : '') + '<button type="button" data-drop="do" aria-label="Not an activity: search the words">&times;</button></span>';
        var t = c.type === 'place' && c.scope ? 'scope' : c.type;
        return '<span class="tvs-chip on">' + icon + esc(c.label) + (c.type === 'place' && c.radius ? ' <small>+ ' + c.radius + ' mi</small>' : '') + '<button type="button" data-drop="' + t + '" aria-label="Remove ' + esc(c.label) + '">&times;</button></span>';
      }).join('') + '</div>' + (q.length >= 2 ? saveBtn() : '') + '</div>';
    // the same on a pause as on Enter (no gate): where the words are heading first, then what would help us read them
    var near = (d.predict || []).concat(d.situation_near || []).slice(0, 4);
    // the same as while typing ("found": Found someone's belongings, Lost or found pet), with no label over them (Jason,
    // 2026-11-11: "it is different than other found search"); the kinds read from the words then never say "not sure"
    if (d.results) d.results._sit = near.length;
    if (near.length) h += near.map(function (n) { return '<button type="button" class="tvs-row tvs-s1 tvs-sits" data-recent="' + esc(n.q) + '"><span><b>' + esc(n.label) + '</b>' + (n.crumb ? '<small>' + esc(n.crumb) + '</small>' : '') + '</span><span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>'; }).join('');
    // learned from what people open after these words (decisions/0061, batch 3): published ones lead, "may also help" follows the results
    var lrow = function (x) { return rmWrap('<button type="button" class="tvs-row tvs-s1 tvs-learn" data-recent="' + esc(x.q) + '"><span><b>' + esc(x.label) + '</b><small>' + (x.status === 'live' ? 'What most people searching this open' : 'Often opened after these words') + '</small></span><span class="tvs-go" aria-hidden="true">&rsaquo;</span></button>', x.label, 'learned'); };
    var lLive = (d.learned || []).filter(function (x) { return x.status === 'live'; }), lMay = (d.learned || []).filter(function (x) { return x.status !== 'live'; });
    if (lLive.length) h += '<p class="tvs-lbl">Most opened</p>' + lLive.map(lrow).join('');
    var mayH = lMay.length ? '<p class="tvs-lbl">May also help</p>' + lMay.map(lrow).join('') : '';
    if (!d.did_you_mean) h = lexHtml(d, q, true) + h;   // a likely slip gets Did you mean (below), not "Suggest it"
    if (d.unread) h += '<div class="tvs-unread"><p class="tvs-muted">' + (near.length ? 'Or name' : 'Not sure what you\u2019re after yet. Name') + ' the kind of place or service (plumber, pizza, campground), or say what\u2019s going on (stuck in the mud, the power went out).</p></div>';
    // a likely misspelling with nothing found ("pozza"): offer the word first, not "none here" (decisions/0058 §12)
    var typo = !!d.did_you_mean && !d.top && !(d.results && d.results.items.length) && !(d.groups || []).some(function (g) { return g.items.length; });
    // a word nothing knows is never changed: ask, likeliest first (Jason, 2026-10-03)
    if (d.did_you_mean_all && d.did_you_mean_all.length) h += dymHtml(d.did_you_mean_all) + (typo ? '<p class="tvs-muted">Nothing matches \u201C' + esc(q) + '\u201D as typed.</p>' : '');
    else if (typo) h += '<div class="tvs-none"><p class="tvs-none-h">Did you mean <button type="button" class="tvs-link" data-recent="' + esc(d.did_you_mean) + '">' + esc(d.did_you_mean) + '</button>?</p>'
      + '<p class="tvs-muted">Nothing matches “' + esc(q) + '”.</p></div>';
    else if (d.elsewhere) { h += noneHere(d.elsewhere); if (window.tvCount) tvCount({ m: 'search_none_here', q: d.elsewhere.what, p: d.elsewhere.place }); }
    if (d.did_you_mean && !typo && !(d.did_you_mean_all && d.did_you_mean_all.length)) h += '<p class="tvs-note">Did you mean <button type="button" class="tvs-link" data-recent="' + esc(d.did_you_mean) + '">' + esc(d.did_you_mean) + '</button>?</p>';
    // how the search was read, the intent first (decisions/0157 §4.3), and how else it could be: one tap each
    if (d.reading && d.reading.text) h += '<p class="tvs-intent" data-reading="' + esc(d.reading.as) + '">' + esc(d.reading.text) + '.'
      + ((d.reading.otherwise || []).length ? ' <span class="tvs-muted">Or:</span> ' + d.reading.otherwise.map(function (o) {
          return o.drop ? '<button type="button" class="tvs-link" data-drop="' + esc(o.drop) + '">' + esc(o.label) + '</button>' : '<button type="button" class="tvs-link" data-recent="' + esc(o.q) + '">' + esc(o.label) + '</button>';
        }).join(' · ') : '') + '</p>';
    // a question about one thing we hold, answered (decisions/0157 §4.3): "blue hills hours"
    if (d.fact) {
      var F = d.fact;
      h += '<div class="tvs-top-card"><p class="tvs-lbl">' + esc(F.label) + ' · ' + esc(F.name) + '</p><p><b>' + esc(F.value) + '</b></p>'
        + (F.others && F.others.length ? '<ul>' + F.others.map(function (o) { return '<li>' + esc(o[0]) + ': ' + esc(o[1]) + '</li>'; }).join('') + '</ul>' : '')
        + '<p class="tvs-muted">' + esc(F.from) + '</p>'
        + '<a class="tvs-btn" href="' + esc(F.url) + '"' + (F.source ? ' target="_blank" rel="noopener nofollow"' : '') + '>' + (F.source ? 'The page it\u2019s on' : 'Open ' + esc(F.name)) + ' &rsaquo;</a></div>';
    }
    var t = d.top;
    if (t && t.kind === 'knowledge') {   // from our own guides (decisions/0133): the topic's summary and a few vetted facts
      h += '<div class="tvs-top-card"><p class="tvs-lbl">' + esc(t.label) + '</p>' + (t.summary ? '<p>' + esc(t.summary) + '</p>' : '')
        + (t.lines.length ? '<ul>' + t.lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>' : '')
        + '<p class="tvs-muted">' + esc(t.note) + '</p>'
        + (t.more && t.more.length ? '<p class="tvs-muted">Related: ' + t.more.map(function (m) { return '<button type="button" class="tvs-link" data-recent="' + esc(m) + '">' + esc(m) + '</button>'; }).join(', ') + '</p>' : '')
        + (t.url ? '<a class="tvs-btn" href="' + esc(t.url) + '">Walking in the footsteps &rsaquo;</a>' : '') + '</div>';
    } else if (t && t.kind === 'facts') {
      h += '<div class="tvs-top-card"><p class="tvs-lbl">' + esc(t.label) + '</p><ul>' + t.lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>'
        + '<p class="tvs-muted">' + esc(t.note) + '</p>' + (t.url ? '<a class="tvs-btn" href="' + esc(t.url) + '">More about ' + esc(t.label) + ' &rsaquo;</a>' : '') + '</div>';
    } else if (t) {
      h += '<p class="tvs-lbl">Top result</p>' + row(t, t.kind);
      if (t.offers) h += '<p class="tvs-offers">What they offer: <b>' + esc(t.offers) + '</b> &middot; <button type="button" class="tvs-link" data-recent="' + esc(String(t.offers).toLowerCase()) + '">More like it nearby</button></p>';   // Who → What (decisions/0061)
    }
    // From our guides (decisions/0133): the catalog's vetted facts the words name, right under the answer
    (d.groups || []).forEach(function (g) {
      if (g.kind !== 'heritage' || !g.items.length) return;
      h += '<p class="tvs-lbl">' + esc(g.title) + '</p>' + g.items.map(function (it) { return row(it, 'heritage'); }).join('');
    });
    // nothing read as a What (decisions/0061): say so plainly, and offer what its Why words point to
    var people = d.people;
    if (people && people.likely) h += peopleRow(people);
    var R = d.results;
    // a situation (decisions/0061): its needs lead; everything else the words found is one tap away below
    var cut = -1;
    var guideH = d.guide ? guideOffer(d.guide) : '';   // a place with no guide yet: we can build one now (decisions/0147); under the results (Jason, 2026-11-22)
    if (sitH) { h += sitH; cut = h.length; }
    // how an action was read (decisions/0060): what they'll do, where, and what puts the best first
    // "near me" with no place set: we don't know where that is (we never track anyone); offer to set it
    if (nearGate()) { b.innerHTML = nearWait(); nearModal(true); return; }   // nothing from everywhere for "near me"
    locAsk(d);   // in the Location line's place, not in the results
    if (d.intent && !sitH && !d.reading) h += '<p class="tvs-intent">Looking for places to go <b>' + esc(d.intent.do) + '</b>' + (d.intent.place ? ' near ' + esc(String(d.intent.place).split('·')[0].trim()) : '')
      + (d.intent.about ? ', with “' + esc(d.intent.about) + '” first' : '') + '.</p>';
    if (R) { h += resultsHtml(d, R) + guideH + (nearBare() ? nearChooser() : '') + mayH; b.innerHTML = fold(h, cut, q) + footHtml(d, q, people, !!sitH || !!t || R.items.length > 0 || !!(R.beyond && R.beyond.items.length), typo || !!d.elsewhere || !!R.widen || !!d.unread || !!R.google); fillOsm(b); return; }   // the widen line already says nothing's here
    (d.groups || []).forEach(function (g) {
      if (g.kind === 'topics' || g.kind === 'heritage') return;   // in Suggestions; From our guides is drawn above
      if (!g.items.length && !g.more) return;
      h += '<p class="tvs-lbl">' + esc(g.title) + (g.total ? ' <span class="tvs-muted">(' + g.total + ')</span>' : '') + '</p>' + g.items.map(function (it) { return row(it, g.kind); }).join('')
        + (g.more ? '<a class="tvs-more" href="' + esc(g.more.url) + '">' + esc(g.more.label) + ' &rarr;</a>' : '');
    });
    if (!t && !(d.groups || []).length && !typo && !d.elsewhere && !(d.results && d.results.google)) h += '<p class="tvs-note">Nothing found for “' + esc(q) + '”. Try fewer words, or remove a chip above.</p>';
    var found = !!t || (d.groups || []).some(function (g) { return g.items.length || g.more; });
    if (people && !people.likely && !found && !nearBare()) h += peopleRow(people);   // otherwise the Address Book is at the panel's corner
    b.innerHTML = fold(h + guideH, cut, q) + tellHtml(q, found || !!sitH);
    fillOsm(b);
  }
  // With a situation, the rest of what the words found folds away under one line
  function fold(h, cut, q) {
    if (cut < 0 || cut >= h.length) return h;
    return h.slice(0, cut) + '<details class="tvs-other"><summary>Everything else for “' + esc(q) + '”</summary>' + h.slice(cut) + '</details>';
  }
  // A situation's needs (decisions/0061): the danger line first; each need's solutions, local first then nearest; when
  // it's urgent, Call and open-now lead. The Chameleon Filter marks each need with its track.
  var TRACK = { resident: ['Get Local', 'res'], traveler: ['Let\u2019s Explore', 'trv'], community: ['Connect', 'com'], mixed: ['', 'res'] };
  var DANGER = '<p class="tvs-danger" role="alert"><b>If anyone is in danger, call <a href="tel:911">911</a>.</b></p>';
  // Where we have no listings yet (a place outside our areas): carry on on Google Maps, in a new tab. Only the words and
  // the town go to Google, from the visitor's own browser; the tap is counted by place, never who.
  // where a search led, for the search-demand counts (decisions/0066): the place, what for, where they went; never who
  function dem(place, anchor, dest) { return ' data-demand=\'' + esc(JSON.stringify({ pl: String(place || ''), an: String(anchor || ''), de: dest })) + '\''; }
  function gmaps(q, label, place) {
    return '<a class="tvs-gmap" href="https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(q) + '" target="_blank" rel="noopener"' + dem(place, label.replace(/^(More:?|Search)\s+/i, '').replace(/[\u201C\u201D]/g, '').replace(/ near .*$/, ''), 'gmaps')
      + ' data-count="act:gmaps:' + esc(String(place || '').slice(0, 30)) + '">' + esc(label) + ' <small>on Google Maps</small> <span aria-hidden="true">\u2197</span></a>';
  }
  // Businesses from OpenStreetMap where we have no listings yet (decisions/0065): a place to fill once the answer shows,
  // then filled by fillOsm(). Only the kind and the town's middle are asked; never who.
  function osmSlot(kind, lat, lon, place, mi) {
    return '<div class="tvs-osm" data-osm="' + esc(kind) + '" data-lat="' + esc(lat) + '" data-lon="' + esc(lon) + '" data-place="' + esc(place) + '"' + (mi ? ' data-mi="' + esc(mi) + '"' : '') + '><p class="tvs-empty">Looking on OpenStreetMap\u2026</p></div>';
  }
  // "near me" (Jason, 2026-11-10): enough within 2 mi, ours and OpenStreetMap's together? If not, offer 5 mi; if 5 isn't
  // enough either, say our results are lacking and tell the team (counts only: the words and the place, never who)
  // "near me" (Jason, 2026-11-10): the nearest are shown whatever their distance; enough near (ours and OpenStreetMap's within
  // N.within miles: 5 for errands, 30 for the outdoors) or our results are lacking: said, with how far the nearest is, and
  // reported once to our team and the crawler. "Near me" alone isn't judged.
  function nearFoot(N, d) {
    return '<div class="tvs-nearfoot" data-ours="' + (+N.ours || 0) + '" data-within="' + (+N.within || 5) + '" data-nearest="' + (N.nearest == null ? '' : +N.nearest) + '" data-enough="' + (+N.enough || 3)
      + '" data-place="' + esc(String(N.place || '').split('·')[0].trim()) + '" data-words="' + esc(N.words || '') + '" data-qs="' + esc(d && d._qs || '') + '"></div>';
  }
  var lackSent = {};
  function fillNear(box) {
    if (!box) return;
    box.querySelectorAll('.tvs-nearfoot:not([data-done])').forEach(function (el) {
      var osm = box.querySelector('.tvs-osm[data-osm]');
      if (osm && !osm.hasAttribute('data-n')) return;   // waits for OpenStreetMap's answer
      el.setAttribute('data-done', '1');
      var within = +el.getAttribute('data-within') || 5, enough = +el.getAttribute('data-enough') || 3;
      var osmNear = osm ? (osm.getAttribute('data-miles') || '').split(',').filter(function (m) { return m !== '' && +m <= within; }).length : 0;
      var n = (+el.getAttribute('data-ours') || 0) + osmNear, words = el.getAttribute('data-words');
      if (n >= enough || !words) return;   // enough near, or "near me" alone: nothing to report
      var where = el.getAttribute('data-place'), far = el.getAttribute('data-nearest');
      el.innerHTML = '<p class="tvs-note" data-tv-status="info" data-tv-label="We’re on it:">Our results for “' + esc(words) + '” near ' + esc(where) + ' are lacking'
        + (far !== '' ? ': the nearest of ours is ' + esc(far) + ' mi away' : '') + '. We’ll look into building better results for it in this area.</p>';
      var qs = el.getAttribute('data-qs');
      if (qs && !lackSent[qs]) { lackSent[qs] = 1; fetch(API + '?' + qs + '&lacking=1', { credentials: 'include' }).catch(function () {}); }   // once a search
    });
  }
  function fillOsm(box) {
    if (!box) return;
    box.querySelectorAll('.tvs-osm[data-osm]:not([data-done])').forEach(function (el) {
      el.setAttribute('data-done', '1');
      var p = new URLSearchParams({ kind: el.getAttribute('data-osm'), lat: el.getAttribute('data-lat'), lon: el.getAttribute('data-lon') });
      var rv = REACH[reachI][0]; p.set('mi', el.getAttribute('data-mi') || String(rv === 'place' ? 5 : (rv === 'all' ? 25 : rv)));   // within the Distance slider ("near me": its 2 or 5 mi)
      fetch('/api/open-places.php?' + p.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
        el.setAttribute('data-miles', (d.ok && d.items ? d.items : []).map(function (it) { return it.miles; }).join(',')); el.setAttribute('data-n', String(d.ok && d.items ? d.items.length : 0)); fillNear(box);
        if (!d.ok || !d.items || !d.items.length) { el.innerHTML = ''; return; }
        var place = el.getAttribute('data-place') || '';
        el.innerHTML = d.items.map(function (it) {
          var meta = [it.what, it.street, (it.miles < 0.1 ? '<0.1' : (it.miles < 10 ? (+it.miles).toFixed(1) : Math.round(it.miles))) + ' mi', it.note].filter(Boolean).join(' \u00B7 ');   // 0.4 mi, so three Chevrons read apart; note: a nation's land (review A3)
          var call = it.phone ? '<span class="tvs-call" role="button" tabindex="0" data-tel="' + esc(it.phone.replace(/[^\d+]/g, '')) + '" data-count="act:osm-call" title="Call ' + esc(it.label) + '" aria-label="Call ' + esc(it.label) + '">'
            + SV('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>', 16) + '</span>' : '';
          return '<a class="tvs-row tvs-sol" href="' + esc(it.directions || it.website || it.url) + '" target="_blank" rel="noopener" data-count="act:osm:' + esc(place.slice(0, 30)) + '"' + dem(place, d.label, 'osm') + ' title="' + (it.directions ? 'Directions to ' : '') + esc(it.label) + '"><span><b>' + esc(it.label) + '</b><small>' + esc(meta) + '</small></span>'
            + '<span class="tvs-go">' + call + ' <span aria-hidden="true">\u2197</span></span></a>';
        }).join('') + '<p class="tvs-osmc">From <a href="' + esc(d.credit_url) + '" target="_blank" rel="noopener">' + esc(d.credit) + '</a>, not Traversence listings. Tap one for directions.</p>';
      }).catch(function () { el.innerHTML = ''; el.setAttribute('data-n', '0'); fillNear(box); });
    });
    fillNear(box);   // nothing to wait for
  }
  function situationHtml(s) {
    if (!s || !s.needs) return '';
    var h = s.danger ? DANGER : '';
    // no place set (decisions/0061): ask where first; "nearest" from nowhere would be a guess hundreds of miles off
    if (s.need_place) return h + '<div class="tvs-sit tvs-askplace"><p class="tvs-sith">' + esc(s.labels.join(' + ')) + '</p>'
      + (s.state_hint ? '<p class="tvs-muted"><b>In ' + esc(s.state_hint.name) + '?</b> Where are you? Then we\u2019ll show the nearest ' + esc(s.needs.slice(0, 3).map(function (n) { return n.label.toLowerCase(); }).join(', ')) + '.</p>'
        // one box for the town or ZIP, beside the target icon (the same simple form as everywhere)
        + '<span class="tvs-nearrow"><input type="search" class="tvs-nearin" placeholder="Town or ZIP in ' + esc(s.state_hint.name) + '" aria-label="Town or ZIP" autocomplete="off"></span><span class="tvs-nearlist" role="listbox" hidden></span>' : '')
      + (s.state_hint ? '' : '<p class="tvs-muted">Where are you? Then we\u2019ll show the nearest ' + esc(s.needs.slice(0, 3).map(function (n) { return n.label.toLowerCase(); }).join(', ')) + '.</p>')
      // one tap finds you (Jason, 2026-11-11: "Auto detection should be the target icon so one click fires it"); a town instead is a link
      + '<span class="tvs-nearrow"><button type="button" class="tvs-btn" data-near-detect title="Use my location" aria-label="Use my location">' + TGT + 'Use my location</button>'
      + ' <button type="button" class="tvs-link" data-where="change">or choose a place</button></span></div>';
    // nothing listed there yet for any need (often a place outside our areas, "Seattle, WA"): say so, never ask where again
    if (!s.needs.some(function (n) { return n.items.length; })) {
      if (!s.none_here || !s.place) return h;
      var pn = String(s.place).split('\u00B7')[0].trim();
      return h + '<div class="tvs-sit tvs-askplace"><p class="tvs-sith">' + esc(s.labels.join(' + ')) + '</p>'
        + '<p class="tvs-muted">' + (s.outside ? 'We have no listings in ' + esc(pn) + ' yet.'
          : 'We have no listings near ' + esc(pn) + ' yet for ' + esc(s.needs.slice(0, 3).map(function (n) { return n.label.toLowerCase(); }).join(', ')) + '.') + '</p>'
        + (s.outside ? '<p class="tvs-muted">Carry on your search:</p>' + s.needs.map(function (n) {
            // a business OpenStreetMap knows ("Storage"): a few nearby first; Google Maps for the rest
            return n.osm && s.center ? '<div class="tvs-need res"><p class="tvs-needh"><span>' + esc(n.label) + '</span></p>' + osmSlot(n.osm, s.center[0], s.center[1], pn)
              + '<div class="tvs-gmaps">' + gmaps(n.gq + ' near ' + pn, 'More ' + n.label.toLowerCase(), pn) + '</div></div>'
              : '<div class="tvs-gmaps">' + gmaps(n.gq + ' near ' + pn, n.label, pn) + '</div>';
          }).join('') : '')
        + '<button type="button" class="tvs-btn" data-where="change">Choose another place</button></div>';
    }
    h += '<div class="tvs-sit' + (s.urgent ? ' urgent' : '') + '"><p class="tvs-sith">' + esc(s.labels.join(' + ')) + (s.may_help ? ' <small>may also help</small>' : '')
      + (s.place ? '<small> · ' + esc(String(s.place).split('·')[0].trim()) + ' first, then nearest</small>' : '') + '</p>';
    s.needs.forEach(function (n) {
      var tr = TRACK[n.track] || TRACK.resident;
      h += '<div class="tvs-need ' + tr[1] + '"><p class="tvs-needh"><span>' + esc(n.label) + '</span>' + (tr[0] ? '<i>' + esc(tr[0]) + '</i>' : '') + '</p>';
      if (!n.items.length) { h += '<p class="tvs-empty">Nothing listed for this yet.</p>' + (s.outside && s.place ? '<div class="tvs-gmaps">' + gmaps(n.gq + ' near ' + String(s.place).split('\u00B7')[0].trim(), n.label, s.place) + '</div>' : '') + '</div>'; return; }
      n.items.forEach(function (it) {
        var meta = [it.sub, it.miles != null ? (it.miles < 1 ? '<1' : it.miles) + ' mi' : (it.here ? 'here' : '')].filter(Boolean).join(' · ');
        var open = it.always ? '<em class="tvs-isopen">Open 24 hours</em>' : (it.open === true ? '<em class="tvs-isopen">Open now</em>' : '');   // its own class: .tvs-open is the header's search button, hidden on a computer and a pill on a phone (P10, 2026-11-27)
        var call = it.phone ? '<span class="tvs-call" role="button" tabindex="0" data-tel="' + esc(it.phone.replace(/[^\d+]/g, '')) + '"' + (/^\d+$/.test(String(it.id)) ? ' data-out="call" data-e="' + esc(it.id) + '"' : '') + ' title="Call ' + esc(it.label) + '" aria-label="Call ' + esc(it.label) + '">'
          + SV('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>', 16)
          + (s.urgent ? '<span>' + esc(it.phone) + '</span>' : '') + '</span>' : '';
        h += '<a class="tvs-row tvs-sol" href="' + esc(it.url || '#') + '"><span><b>' + esc(it.label) + '</b><small>' + esc(meta) + (open ? ' ' + open : '') + '</small></span>'
          + '<span class="tvs-go">' + call + (it.peek ? ' ' + peekBtn({ id: it.id, label: it.label }) : ' <span aria-hidden="true">&rsaquo;</span>') + '</span></a>';
      });
      h += '</div>';
    });
    return h + '</div>';
  }
  // The 5W results (decisions/0053 §8): everything that matches in one list, or grouped by kind if the person prefers.
  function grouped() { try { return localStorage.getItem('tv_search_group') === '1'; } catch (x) { return false; } }
  // what the words mean (decisions/0067): the closest kinds, each with how many are here; one tap searches that kind
  function meantHtml(R) {
    var M = R.meant; if (!M || !M.kinds || !M.kinds.length) return '';
    var chip = function (k) { return '<button type="button" class="tvs-chipb" data-recent="' + esc(k.q) + '">' + esc(k.label) + ' <span class="tvs-muted">(' + (k.here ? k.here + ' here' : k.all + ' in all') + ')</span></button>'; };
    var sure = M.kinds.filter(function (k) { return k.sure; }), maybe = M.kinds.filter(function (k) { return !k.sure; });
    // sure: the kinds it means. Not sure: say so, and offer the loose ones as maybes (Jason, 2026-10-07)
    return '<div class="tvs-meant">' + (sure.length ? '<p class="tvs-lbl">Closest kinds for \u201C' + esc(M.words) + '\u201D</p><div class="tvs-chipr">' + sure.map(chip).join('') + '</div>' : '')
      + (maybe.length ? (sure.length || R._sit ? '<p class="tvs-lbl">Maybe</p>' : '<p class="tvs-note">Not sure what \u201C' + esc(M.words) + '\u201D means here. Maybe one of these?</p>') + '<div class="tvs-chipr">' + maybe.map(chip).join('') + '</div>' : '') + '</div>';
  }
  // places named on pages we read that we don't list yet (decisions/0157 §3): each row opens the page it's named on; Call
  // where the page gave a phone. Being checked before they're listed (decisions/0044).
  function readHtml(R) {
    var X = R.read; if (!X || !X.items || !X.items.length) return '';
    return '<p class="tvs-lbl">Named on pages we read</p>' + X.items.map(function (it) {
      var meta = [it.kind, it.address || it.town, it.miles == null ? '' : (it.miles < 0.1 ? '<0.1' : (it.miles < 10 ? (+it.miles).toFixed(1) : Math.round(it.miles))) + ' mi', it.from, it.note].filter(Boolean).join(' \u00B7 ');   // note: a nation's land, or a private address (review A1)
      var call = it.tel ? '<span class="tvs-call" role="button" tabindex="0" data-tel="' + esc(String(it.tel).replace(/[^\d+]/g, '')) + '" data-count="act:read-call" title="Call ' + esc(it.label) + '" aria-label="Call ' + esc(it.label) + '">'
        + SV('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>', 16) + '</span>' : '';
      return '<a class="tvs-row tvs-sol" href="' + esc(it.source) + '" target="_blank" rel="noopener nofollow" data-count="act:read" title="Where ' + esc(it.label) + ' is named: ' + esc(it.source_host) + '"><span><b>' + esc(it.label) + '</b><small>' + esc(meta) + '</small></span>'
        + '<span class="tvs-go">' + call + ' <span aria-hidden="true">\u2197</span></span></a>';
    }).join('') + '<p class="tvs-osmc">Not Traversence listings yet: each is checked against another source before it\u2019s listed. Tap one to see the page it\u2019s named on.</p>';
  }
  // businesses we know only by name (decisions/0164 §3.5): a request, never a listing (no pin, no Call or Directions). Each
  // asks for its phone or address (js/suggest.js) and lets its owner claim it.
  function helpHtml(R) {
    var X = R.help; if (!X || !X.items || !X.items.length) return '';
    return '<p class="tvs-lbl">Help us find these businesses</p>' + X.items.map(function (it) {
      var meta = [it.what, it.town, it.miles == null ? '' : (it.miles < 10 ? (+it.miles).toFixed(1) : Math.round(it.miles)) + ' mi'].filter(Boolean).join(' \u00B7 ');
      var ask = JSON.stringify({ kind: 'fix', entity_id: it.id, entity_name: it.label, help_find: 1 });
      return '<div class="tvs-row tvs-sol"><span><b>' + esc(it.label) + '</b><small>' + esc(meta) + '</small></span><span class="tvs-go">'
        + '<button type="button" class="tvs-more tvs-sm" data-tv-suggest="' + esc(ask) + '" data-count="act:help-find">Know its phone or address?</button> '
        + '<a class="tvs-more tvs-sm" href="/claim.php?listing=' + (+it.id) + '">Is it yours?</a></span></div>';
    }).join('') + '<p class="tvs-osmc">We know these names but not yet how to reach them. Tell us what you know and we\u2019ll check it and list them.</p>';
  }
  // the pages we read that match the words (decisions/0157 §7.4): our index answers any search, each page one tap away
  function pagesHtml(R) {
    var X = R.pages; if (!X || !X.items || !X.items.length) return '';
    return '<p class="tvs-lbl">From pages we read</p>' + X.items.map(function (it) {
      return '<a class="tvs-row tvs-sol" href="' + esc(it.url) + '" target="_blank" rel="noopener nofollow" data-count="act:page"><span><b>' + esc(it.label) + '</b><small>' + esc(it.sub) + '</small></span><span class="tvs-go"><span aria-hidden="true">\u2197</span></span></a>';
    }).join('');
  }
  // nothing by name or kind: what mentions the words
  function mentionsHtml(R) {
    var M = R.mentions; if (!M || !M.items || !M.items.length) return '';
    return '<p class="tvs-lbl">Mentioning \u201C' + esc(M.words) + '\u201D</p>' + M.items.map(function (it) {
      return '<a class="tvs-row' + (it.here ? '' : ' far') + '" href="' + esc(it.url) + '"><span><b>' + esc(it.label) + '</b><small>' + esc(it.sub || '') + '</small></span><span class="tvs-go">' + (it.peek ? peekBtn({ id: it.id, label: it.label }) : '<span aria-hidden="true">&rsaquo;</span>') + '</span></a>';
    }).join('');
  }
  // a guide built while you wait (decisions/0147): the offer for a place outside our areas, or where its build stands
  function guideOffer(g) {
    var btn = g.state === 'first' ? 'Open the first look' : (g.state === 'building' ? 'See how it\'s going' : 'Build the guide to ' + g.label);
    return '<div class="tvs-top-card" style="border-color:#8FBCA8"><p class="tvs-lbl">A guide to ' + esc(g.label) + '</p><p class="tvs-muted">' + esc(g.text) + '</p>'
      + '<a class="tvs-btn" href="' + esc(g.url) + '" rel="nofollow">' + esc(btn) + ' &rsaquo;</a></div>';
  }
  function resultsHtml(d, R) {
    var tp = R.items.filter(function (x) { return x.kind === 'topic'; });
    if (tp.length) d._cats = tp;   // categories are a way in: they're in Suggestions (kept for its later redraws)
    R.items = R.items.filter(function (x) { return x.kind !== 'topic'; });
    var h = '', places = (d.groups || []).filter(function (g) { return g.kind === 'places' && g.items.length; })[0];
    // an outing (2026-11-10, "Kids? Ages? Type of activities?"): who's coming and inside or out, one tap each
    if (R.refine && R.refine.items && R.refine.items.length) h += '<p class="tvs-lbl">' + esc(R.refine.title || 'Narrow it') + '</p><div class="tvs-chipr">' + R.refine.items.map(function (x) {
      return '<button type="button" class="tvs-chipb' + (x.on ? ' on' : '') + '" data-recent="' + esc(x.q) + '"' + (x.on ? ' aria-pressed="true" style="background:#8FBCA8;border-color:#8FBCA8"' : '') + '>' + esc(x.label) + '</button>';
    }).join('') + '</div>';
    if (places) h += '<p class="tvs-lbl">Places</p>' + places.items.map(function (it) { return row(it, 'places'); }).join('');
    var when = (d.chips || []).some(function (c) { return c.type === 'when'; });
    if (!R.items.length) return h + (R.note && !R.near && !R.read ? '<p class="tvs-note">' + esc(R.note) + '</p>' : '') + meantHtml(R) + readHtml(R) + helpHtml(R) + pagesHtml(R) + mentionsHtml(R) + (R.osm ? osmSlot(R.osm.kind, R.osm.lat, R.osm.lon, R.osm.place, R.osm.mi) : '') + (R.google ? '<div class="tvs-gmaps">' + gmaps(R.google.q, (R.osm ? 'More: ' : 'Search ') + '\u201C' + R.google.q + '\u201D', R.google.place) + '</div>' : '') + (R.near ? nearFoot(R.near, d) : widenHtml(R)) + beyondHtml(R, item);
    var F = (R.funnels || []).filter(function (f) { return f.count > 0; });
    if (funnel && !F.some(function (f) { return f.key === funnel; })) funnel = '';
    // the count is what's listed below (the categories found are a way in, shown with Suggestions, not counted here)
    var nShown = (R.kinds || []).reduce(function (a, k) { return a + (k.kind === 'topic' ? 0 : (k.count || 0)); }, 0) || R.items.length;
    h += '<div class="tvs-rhead"><p class="tvs-lbl">Results <span class="tvs-muted">(' + nShown + ')</span></p>'
      + (F.length > 1 && !funnel ? '<button type="button" class="tvs-link" data-group-toggle>' + (grouped() ? 'One list' : 'Group by section') + '</button>' : '') + '</div>';
    // The site's sections (Let's Explore, Get Local, Connect, Market), with what each holds for this search
    if (F.length > 1 || (F.length === 1 && F[0].key !== 'local')) {
      h += '<div class="tvs-tabs" role="tablist"><button type="button" role="tab" class="tvs-tab' + (funnel ? '' : ' on') + '" data-funnel="">All</button>'
        + (R.funnels || []).map(function (f) {
            if (f.soon) return (d.chips || []).some(function (c) { return c.type === 'funnel' && c.value === 'market' || c.type === 'why' && c.value === 'buy'; })
              ? '<span class="tvs-tab off" title="Coming soon">' + esc(f.label) + ' <small>soon</small></span>' : '';
            return f.count ? '<button type="button" role="tab" class="tvs-tab' + (funnel === f.key ? ' on' : '') + '" data-funnel="' + f.key + '">' + esc(SECTION[f.key] || f.label) + ' <small>' + f.count + '</small></button>' : '';   // named as in Search Directory, like the rows' tags (0058 §38)
          }).join('') + '</div>';
    }
    if (R.note) h += '<p class="tvs-note">' + esc(R.note) + '</p>';
    // asked open now (or open on a day): how many of the businesses have known hours, the owner's, our staff's or
    // OpenStreetMap's (P10, 2026-11-27); the rest say Hours not known, never a guess
    if (when && R.hours_known && R.hours_known.of) h += '<p class="tvs-note" data-hours-known>' + R.hours_known.known + ' of ' + R.hours_known.of + ' have known hours.</p>';
    if (R.near) h += nearFoot(R.near, d);   // fewer than enough near: said above the rows (2026-11-22)
    h += meantHtml(R);
    function item(it, showKind) {   // a declaration: the "nothing here, something beyond" return above uses it too
      var tag = it.open === true ? '<em class="tvs-isopen">Open</em>' : (when && it.kind === 'listing' && it.open === null && it.hours_known === false ? '<em class="tvs-unk">Hours not known</em>' : '');
      return '<a class="tvs-row" href="' + esc(it.url || '#') + '" data-kind="' + esc(it.kind) + '"><span><b>' + esc(it.label) + '</b>'
        + '<small>' + esc(it.sub) + (tag ? ' ' + tag : '') + '</small></span><span class="tvs-go">' + (sectionOf(it) ? '<i class="tvs-kind" aria-hidden="true">' + esc(sectionOf(it)) + '</i> ' : '') + (it.peek || (it.url && it.kind !== 'topic') ? peekBtn(it) : '<span aria-hidden="true">&rsaquo;</span>') + '</span></a>';
    }
    var fOf = function (k) { return (R.funnels || []).filter(function (f) { return f.key === k; })[0]; };
    if (funnel) {
      var its = R.items.filter(function (it) { return it.funnel === funnel; }), fx = fOf(funnel);
      h += chunk(its, 5, 'f-' + funnel, (R.kinds || []).length > 1);
      if (fx && fx.url) h += '<a class="tvs-more" href="' + esc(fx.url) + '">Open ' + esc(fx.label) + ' &rarr;</a>';   // the section's own page keeps its own name
    } else if (grouped() && F.length > 1) {
      F.forEach(function (f) {
        var its = R.items.filter(function (it) { return it.funnel === f.key; });
        if (its.length) h += '<p class="tvs-lbl tvs-sub">' + esc(SECTION[f.key] || f.label) + ' <span class="tvs-muted">(' + f.count + ')</span></p>' + chunk(its, 3, 'g-' + f.key, true)
          + (f.url ? '<a class="tvs-more tvs-sm" href="' + esc(f.url) + '">Open ' + esc(f.label) + ' &rarr;</a>' : '');
      });
    } else {
      h += chunk(R.items.slice(0, 12), 5, 'all', (R.kinds || []).length > 1);   // the kind tag only when kinds are mixed
    }
    h += readHtml(R) + helpHtml(R) + pagesHtml(R);   // few of ours: the places named on pages we read, after them; then the pages themselves (decisions/0157)
    if (R.near && R.osm) h += osmSlot(R.osm.kind, R.osm.lat, R.osm.lon, R.osm.place, R.osm.mi);   // "near me" with only a few of ours: OpenStreetMap's within the same miles, then enough?
    return h + beyondHtml(R, item);
    // A few at a time (people take in 3 to 5 at once); the rest one tap away
    function chunk(list, n, key, showKind) {
      var more = list.length - n;
      return list.map(function (it, i) { var r = item(it, showKind); return i < n ? r : r.replace('<a class="tvs-row"', '<a class="tvs-row" hidden data-rest="' + key + '"'); }).join('')
        + (more > 0 ? '<button type="button" class="tvs-link tvs-showmore" data-show="' + key + '">Show ' + more + ' more</button>' : '');
    }
  }
  // "My place only" with nothing there: how near the nearest is, and a wider reach in one tap (the slider moves with it)
  function widenHtml(R) {
    var W = R.widen; if (!W || !W.steps || !W.steps.length) return '';
    return '<div class="tvs-widen"><p class="tvs-note">Nothing ' + (W.within ? 'within ' + W.within + ' mi of ' : 'in ') + esc(String(W.place).split('·')[0].trim()) + '. The nearest is ' + W.nearest + ' mi away.</p><div class="tvs-wbtn">'
      + W.steps.map(function (x) { return '<button type="button" class="tvs-chipb" data-widen="' + esc(x.reach) + '">' + esc(x.label) + ' <span class="tvs-muted">(' + x.count + (x.reach === 'all' && W.more ? '+' : '') + ')</span></button>'; }).join('') + '</div></div>';
  }
  // Local first, never only local (decisions/0060): what matches beyond the place, nearest first. The place someone set
  // stays as it is.
  function beyondHtml(R, item) {
    var B = R.beyond; if (!B || !B.items.length) return '';
    var list = B.items, n = 3;
    return '<p class="tvs-lbl tvs-beyond">Beyond ' + esc(String(B.place).split('·')[0].trim()) + ' <span class="tvs-muted">(' + B.total + ', nearest first)</span></p>'
      + list.map(function (it, i) { var r = item(it, true); return i < n ? r : r.replace('<a class="tvs-row"', '<a class="tvs-row" hidden data-rest="beyond"'); }).join('')
      + (list.length > n ? '<button type="button" class="tvs-link tvs-showmore" data-show="beyond">Show ' + (list.length - n) + ' more</button>' : '');
  }
  function saveKey() { var s = placeOver || scope(); return input.value.trim() + '|' + (s ? s.slug : '') + '|' + scopeK; }
  function saveBtn() { var on = !!savedIds[saveKey()]; return '<button type="button" class="tvs-chip tvs-savebtn' + (on ? ' on' : '') + '" data-save' + (on ? ' disabled' : '') + ' title="' + (on ? 'Saved to your searches' : 'Save this search') + '">' + (on ? '★ Saved' : '☆ Save') + '</button>'; }
  function hasListings(d) {
    if (d.results) return (d.results.kinds || []).some(function (k) { return k.kind === 'listing' && k.count > 0; });
    return (d.groups || []).some(function (g) { return g.kind === 'listings' && (g.items.length || g.more); });
  }
  // None of it in the place you're in (decisions/0058 §12): say so; recommend one or add a listing; and where it is
  // nearby, by town area, nearest first. Tapping one opens the map with its pin chosen.
  function noneHere(e) {
    var h = '<div class="tvs-none"><p class="tvs-none-h">No “' + esc(e.what) + '” in ' + esc(e.place) + ' yet.</p>'
      + '<div class="tvs-none-acts"><button type="button" class="tvs-btn" data-count="act:recommend" data-tv-suggest="' + esc(JSON.stringify({ kind: 'missing', topic: 'business', q: e.what, place_name: e.place })) + '">Recommend one</button>'
      + '<a class="tvs-btn" href="/claim.php?new=1" data-count="act:add_listing">Add a listing</a></div>';
    if (!e.towns.length) return h + '<p class="tvs-muted">' + (last && last.split && last.split.length ? 'Nothing has all of these words. Try them one at a time under Suggestions.' : 'Nothing like it within reach yet either.') + '</p></div>';
    h += '<p class="tvs-lbl">Nearby, with “' + esc(e.what) + '”</p>';
    e.towns.forEach(function (t) {
      h += '<p class="tvs-town"><b>' + esc(t.town) + '</b><small>' + (t.miles != null ? t.miles + ' mi · ' : '') + t.count + ' found</small></p>';
      t.items.forEach(function (i) {   // like a result row: open it, Peek, or see it on the map (decisions/0058 §38)
        h += '<a class="tvs-row" href="/listing/view.php?id=' + i.id + '" data-count="search_click:nearby">'
          + '<span><b>' + esc(i.label) + '</b><small>' + esc([i.sub, i.miles != null ? i.miles + ' mi' : ''].filter(Boolean).join(' · ')) + '</small></span>'
          + '<span class="tvs-go"><i class="tvs-kind" aria-hidden="true">Directory</i> <span class="tvs-mapb" role="button" tabindex="0" title="See it on the map" aria-label="See ' + esc(i.label) + ' on the map" data-else="' + i.id + '" data-lat="' + i.lat + '" data-lon="' + i.lon + '" data-what="' + esc(e.what) + '" data-label="' + esc(i.label) + '" data-sub="' + esc(i.sub || '') + '">'
          + SV(IC.pin, 15) + '</span> ' + peekBtn({ id: i.id, label: i.label }) + '</span></a>';
      });
    });
    return h + '</div>';
  }
  // the map, around one place found nearby, with it chosen and what else matches around it
  function mapAt(b) {
    var id = +b.getAttribute('data-else'), here = { lat: +b.getAttribute('data-lat'), lon: +b.getAttribute('data-lon'), radius: 15 }, what = b.getAttribute('data-what');
    var label = b.getAttribute('data-label'), sub = b.getAttribute('data-sub');
    var going = pushed; close();
    afterBack(going, function () {
      if (!window.TvMap) return;
      TvMap.open({ title: what + ' · around ' + label, radius: 15, select: id,
        titleFor: function (j) { return (j.total || 0) + ' · “' + what + '” around ' + label; },
        load: function (area, words) {
          var a = area || here, qs = new URLSearchParams({ pins: 1, kinds: 'listing', lat: a.lat, lon: a.lon, radius: a.radius, q: words || what });
          return fetch('/api/search.php?' + qs.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (j) {
            if (!(j.pins || []).some(function (p) { return p.id === id; })) (j.pins = j.pins || []).push({ id: id, lat: here.lat, lon: here.lon, name: label, category: sub });
            j.center = a; return j;
          });
        } });
    });
  }
  // Which part of the site it's in, named as in Search Directory (decisions/0058 §38)
  var SECTION = { local: 'Directory', explore: 'Discovery', social: 'Community', market: 'Marketplace' };
  function sectionOf(it) {
    if (it.kind === 'topic') return 'Category';
    if (it.funnel && SECTION[it.funnel]) return SECTION[it.funnel];
    return ({ listing: 'Directory', outdoors: 'Discovery', story: 'Discovery', experience: 'Discovery', journey: 'Discovery', group: 'Community', places: 'Place', heritage: 'Guides' })[it.kind] || '';
  }
  function kindLabel(R, k) { var m = R.kinds.filter(function (x) { return x.kind === k; })[0]; return m ? m.label : ({ listing: 'Listing', outdoors: 'Outdoors', story: 'Story', group: 'Group', experience: 'Experience' })[k] || k; }
  function footHtml(d, q, people, found, said) {   // said: the "did you mean" or "none here" box already says nothing was found
    var h = '', lg = (d.groups || []).filter(function (g) { return g.kind === 'listings' && g.more; })[0];
    // the directory counts its own way (a town's whole area, its own word match), so its number isn't repeated here,
    // where it could disagree with Results
    if (lg) h += typeof window.tvSearchHere === 'function' && !nearGate()   // already on the directory: its own list follows the words, here
      ? '<button type="button" class="tvs-more" data-here style="background:none;border:0;padding:0;font:inherit;cursor:pointer">See them all in the directory list &rarr;</button>'
      : '<a class="tvs-more" href="' + esc(lg.more.url) + '">' + esc(String(lg.more.label).replace(/^See all \d+ listings/, 'See them all')) + ' in the directory &rarr;</a>';
    ((d.results && d.results.more) || []).forEach(function (m) { h += '<a class="tvs-more" href="' + esc(m.url) + '">' + esc(m.label) + ' &rarr;</a>'; });
    var meant = d.results && ((d.results.meant && d.results.meant.kinds.length) || (d.results.mentions && d.results.mentions.items.length));
    var qw = q.replace(new RegExp(NEARRX.source, 'gi'), ' ').replace(/\s+/g, ' ').trim() || q;   // the words, without "near me"
    if (!found && !said && !nearBare()) h += '<p class="tvs-note">' + (meant ? 'Nothing is named “' + esc(qw) + '” here: try one of the kinds above, or change your words.' : 'Not sure what “' + esc(qw) + '” means. Try changing your search: fewer words, another word for it, or remove a chip above.') + '</p>';
    if (people && !people.likely && !found && !nearBare()) h += peopleRow(people);
    return h + tellHtml(q, found);
  }
  // "Tell us" (js/suggest.js): anything missing, or feedback on these results
  function tellHtml(q, found) {
    return '<p class="tvs-foot">' + (found ? 'Missing something?' : 'Can\u2019t find it?') + ' <button type="button" class="tvs-link" data-tv-suggest=\'' + esc(JSON.stringify({ kind: 'missing', q: q, place_name: placeLabel() })) + '\'>Tell us what\u2019s missing</button>'
      + ' &middot; <button type="button" class="tvs-link" data-tv-suggest=\'' + esc(JSON.stringify({ kind: 'feedback', q: q })) + '\'>Feedback on these results</button></p>';
  }
  function placeLabel() { var pl = ((last && last.chips) || []).filter(function (c) { return c.type === 'place'; })[0]; return pl ? pl.label : ''; }
  function peopleRow(p) {
    return '<a class="tvs-row tvs-people" href="' + esc(p.url) + '"><span><b>' + esc(p.label) + '</b><small>'
      + (p.signed_in ? 'People are found only in your Address Book, and only if they allow it.' : 'Sign in to search your Address Book. People aren\'t listed in public search.') + '</small></span><span class="tvs-go" aria-hidden="true">&rsaquo;</span></a>';
  }

  // A listing's row opens its page; only its Peek button opens the peek card (a span, since a button can't sit in a link).
  function peekBtn(it) {   // a listing by its number, an outdoor place as rec:<id>, any other row by its page (2026-11-22)
    var pid = it.kind === 'outdoors' ? 'rec:' + it.id : (it.peek ? String(it.id) : 'url:' + it.url);
    return '<span class="tvs-peekb" role="button" tabindex="0" data-peek="' + esc(pid) + '" title="Peek" aria-label="Peek at ' + esc(it.label) + '">Peek</span>';
  }
  function peekGo(el) {   // what a Peek names: a page opens in the page peek, a listing or outdoor place in the card
    var v = el.getAttribute('data-peek') || '';
    if (v.indexOf('url:') === 0) { if (window.TvAsk) TvAsk.peek(v.slice(4), el.getAttribute('aria-label').replace(/^Peek at /, '')); return; }
    peek(/^\d+$/.test(v) ? +v : v);
  }
  function peek(id) {
    var old = document.querySelector('.tvs-peek'); if (old) old.remove();
    var od = document.querySelector('.tvs-peek-dim'); if (od) od.remove();
    // the page dims behind it (Jason, 2026-11-19: "popups should dim bg"); a tap on the dim or Esc closes it
    var dim = document.createElement('div'); dim.className = 'tvs-peek-dim'; dim.setAttribute('aria-hidden', 'true'); document.body.appendChild(dim);
    var shut = function () { box.remove(); dim.remove(); document.removeEventListener('keydown', esc1); };
    var esc1 = function (e) { if (e.key === 'Escape') shut(); }; document.addEventListener('keydown', esc1); dim.addEventListener('click', function () { shut(); });
    var box = document.createElement('div'); box.className = 'tvs-peek'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Listing');
    box.innerHTML = '<p class="tvs-note">Loading…</p>'; document.body.appendChild(box);
    fetch(API + '?peek=' + encodeURIComponent(id), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) throw new Error((d.error && d.error.message) || 'Not available');
      var l = d.listing;
      var out = function (k) { return l.kind === 'outdoors' ? '' : ' data-out="' + k + '" data-e="' + (+l.id) + '"'; };
      box.innerHTML = '<button type="button" class="tvs-x" data-peek-close aria-label="Close">&times;</button>'
        + (l.category ? '<p class="tvs-muted"><button type="button" class="tvs-link" data-peekcat="' + esc(l.category) + '" title="Show every ' + esc(catName(l.category)) + ' listing here">' + esc(catName(l.category)) + ' &rsaquo;</button></p>' : '') + '<h3>' + esc(l.name) + '</h3>'
        + (l.address ? '<p>' + esc(l.address) + '</p>' : l.private_location ? '<p class="tvs-muted">Location kept private for the safety of the people it serves. Please call.</p>' : (l.town ? '<p>' + esc(l.town) + '</p>' : ''))
        + (l.about ? '<p class="tvs-about">' + esc(l.about) + '</p>' : '')
        + (l.hours.length ? '<p class="tvs-lbl">Hours</p><p>' + l.hours.map(esc).join('<br>') + '</p>' : '')
        + '<div class="tvs-acts">'
        // data-out: counted for the business (js/out-count.js): which listing and action, never who
        + (l.phone ? '<a class="tvs-btn" href="tel:' + esc(l.phone.replace(/[^\d+]/g, '')) + '"' + out('call') + '>Call ' + esc(l.phone) + '</a>' : '')
        + (l.directions ? '<a class="tvs-btn" href="' + esc(l.directions) + '" target="_blank" rel="noopener"' + out('directions') + '>Directions</a>' : '')
        + (l.website ? '<a class="tvs-btn" href="' + esc(l.website) + '" target="_blank" rel="noopener nofollow"' + out('website') + '>Website</a>' : '')
        + (l.contact_url ? '<a class="tvs-btn" href="' + esc(l.contact_url) + '" target="_blank" rel="noopener nofollow"' + out('contact') + '>Contact or book</a>' : '')
        + (l.private_location ? '' : '<button type="button" class="tvs-btn" style="cursor:pointer;font-family:inherit" data-trip-add="' + esc(JSON.stringify(tripRef(l))) + '" aria-haspopup="dialog">Add to a trip</button>')   // decisions/0174 §2
        + '<a class="tvs-btn go" href="' + esc(l.url) + '">' + (l.kind === 'outdoors' ? 'Open' : 'Open listing') + ' &rsaquo;</a></div>'
        + '<p class="tvs-muted"><button type="button" class="tvs-link" data-tv-suggest=\'' + esc(JSON.stringify(l.kind === 'outdoors' ? { target: String(id), target_name: l.name, topic: 'outdoors' } : { entity_id: +l.id, entity_name: l.name })) + '\'>Suggest an edit or tell us something</button></p>'
        // Link (open connection) and Save to Address Book (private), right here: the same buttons as the listing page
        + (l.kind === 'outdoors' ? '<p class="tvs-muted">From Recreation.gov. Check hours, fees and permits with the managing agency before you go.</p>'
          : '<div class="tvs-keep" data-link-host><span data-contact-save="business:' + l.id + '" data-link-label="' + esc(l.name) + '"></span>'
          + '<span data-link="business:' + l.id + '" data-link-label="' + esc(l.name) + '"></span></div>');
      if (l.kind !== 'outdoors' && !document.querySelector('script[src*="/js/link-button.js"]')) {
        var sc = document.createElement('script'); sc.src = '/js/link-button.js'; document.body.appendChild(sc);   // it finds the buttons itself
      }
      if (!window.TvTrips && !document.querySelector('script[src*="/js/tv-trips.js"]')) { var ts = document.createElement('script'); ts.src = '/js/tv-trips.js'; document.body.appendChild(ts); }   // Add to a trip's picker
    }).catch(function (x) { box.innerHTML = '<button type="button" class="tvs-x" data-peek-close aria-label="Close">&times;</button><p class="tvs-note">' + esc(x.message) + '</p>'; });
    box.addEventListener('click', function (e) {
      if (e.target.closest('[data-peek-close]')) { shut(); return; }
      var pc = e.target.closest('[data-peekcat]');
      if (pc && panel) {   // the category: search everything like it in the same place (and distance)
        var pl = ((last && last.chips) || []).filter(function (c) { return c.type === 'place' && !c.scope; })[0];
        input.value = pc.getAttribute('data-peekcat') + (pl ? ' in ' + pl.label : '');
        var i2 = panel.querySelector('.tvs-in2'); if (i2) i2.value = input.value;
        drop = []; shut(); run();
      }
    });
  }

  function showMap() {
    if (!last || !last.map) return;
    if (!window.TvMap) { var b = body(); if (b) b.insertAdjacentHTML('afterbegin', '<p class="tvs-note">The map is still loading; try again in a moment.</p>'); return; }
    var d = last, place = (d.chips || []).filter(function (c) { return c.type === 'place'; })[0];
    var what = (d.chips || []).filter(function (c) { return c.type === 'category' || c.type === 'kind'; }).map(function (c) { return c.label; })[0] || d.text || '';
    var where = place ? place.label + (d.area ? ' + ' + d.area.radius + ' mi' : '') : '';
    var title = [what, where].filter(Boolean).join(' · ') || 'Listings';   // a place alone ("Wide Ruins") reads "180 listings · Wide Ruins"
    var base = new URLSearchParams(d.map);
    remember(input.value);
    var going = pushed; close();
    // closing the panel steps history back; open the map once that has landed, so its own Back entry isn't eaten
    afterBack(going, function () { TvMap.open({
      title: title,
      radius: d.area ? d.area.radius : null,
      titleFor: function (j) { var n = j.total != null ? j.total : (j.pins || []).length; return n + (n === 1 ? ' listing' : ' listings') + ' · ' + title; },
      onCategory: function (slug, name) {   // the card's category: everything like it in the same place and distance
        base.set('category', slug); base.delete('q'); base.delete('has');
        title = name + (place ? ' · ' + place.label + (d.area ? ' + ' + d.area.radius + ' mi' : '') : '');
        TvMap.reload();
      },
      load: function (area, words) {
        var qs = new URLSearchParams(base.toString());
        if (words) { qs.set('q', words); qs.delete('category'); }   // typed in the header search while the map is open
        if (area) { ['cluster', 'geo', 'hub', 'state', 'city', 'zip'].forEach(function (k) { qs.delete(k); }); qs.set('lat', area.lat); qs.set('lon', area.lon); qs.set('radius', area.radius); }
        return fetch('/api/search_listings.php?' + qs.toString() + '&view=map', { credentials: 'include' }).then(function (r) {
          return r.json().then(function (j) { if (!(r.ok && j.success)) throw new Error(j.error || ('HTTP ' + r.status)); return j; });
        });
      },
      onList: function () { afterBack(true, open); },   // List, Back or Esc returns to these results
    }); });
  }
  function afterBack(waiting, fn) {
    if (!waiting) { fn(); return; }
    var done = false;
    function go() { if (done) return; done = true; window.removeEventListener('popstate', go); setTimeout(fn, 0); }
    window.addEventListener('popstate', go); setTimeout(go, 350);
  }

  function clicks(e) {
    if (e.target.closest('.tvs-x')) { close(); return; }
    var rm = e.target.closest('[data-rm]');
    if (rm) {
      e.preventDefault(); e.stopPropagation();
      try { if (navigator.sendBeacon) navigator.sendBeacon(API + '?removed=' + encodeURIComponent(rm.getAttribute('data-rm')) + '&rk=' + encodeURIComponent(rm.getAttribute('data-rk')) + '&q=' + encodeURIComponent(input.value.trim())); } catch (x) {}
      var w = rm.closest('.tvs-rmw'); if (w) w.remove();
      return;
    }
    var sw = e.target.closest('[data-sugword]');
    if (sw) {
      e.preventDefault();
      var box = sw.closest('.tvs-lex'); sw.disabled = true;
      fetch('/api/suggestion.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (g) {
        return fetch('/api/suggestion.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': g.csrf || '' },
          body: JSON.stringify({ kind: 'word', topic: 'other', target_name: sw.getAttribute('data-sugword'), context: sw.getAttribute('data-sugq'), note: '' }) }).then(function (r) { return r.json(); });
      }).then(function (j) { if (box) box.innerHTML = '<p>' + (j && j.ok ? 'Thanks. We\u2019ll look at \u201C' + esc(sw.getAttribute('data-sugword')) + '\u201D and teach search what it means.' : esc((j && j.error) || 'That didn\u2019t send. Try again later.')) + '</p>'; })
        .catch(function () { if (box) box.innerHTML = '<p>That didn\u2019t send. Try again later.</p>'; });
      return;
    }
    // a prediction tapped (not "Search for…"): counted against the words typed
    if (ptrack && !ptrack.done && e.target.closest('.tvs-t1 a[href], .tvs-t1 [data-peek], .tvs-t1 [data-recent], .tvs-t1 [data-tel], .tvs-t1 [data-where]')) { ptrack.done = true; outcome(['picked'], ptrack.qs); }
    // something was opened, called or peeked from the results (or a way forward taken): not a miss
    if (track && e.target.closest('.tvs-full a[href], .tvs-full [data-peek], .tvs-full [data-tel], .tvs-full [data-recent], .tvs-full [data-else], .tvs-full [data-widen], .tvs-full [data-where]')) track.opened = true;
    // what was opened, by its kind (decisions/0061, batch 3): once per thing per search, from the results or a prediction
    var oa = e.target.closest('.tvs-full a[href], .tvs-t1 a[href], [data-peek]'), ot = oa && oa.closest('.tvs-t1') ? ptrack : track;
    if (oa && ot && ot.qs) {
      var pv = oa.getAttribute('data-peek') || '', ou = oa.hasAttribute('data-peek') ? (/^\d+$/.test(pv) ? '/listing/view.php?id=' + pv : (pv.indexOf('url:') === 0 ? pv.slice(4) : (oa.closest('a[href]') || oa).getAttribute('href'))) : oa.getAttribute('href');
      ot.seen = ot.seen || {};
      if (ou && ou.charAt(0) === '/' && !ot.seen[ou]) { ot.seen[ou] = 1; try { if (navigator.sendBeacon) navigator.sendBeacon(API + '?opened=' + encodeURIComponent(ou) + '&' + ot.qs); } catch (x) {} }
    }
    // a kind of place tapped while typing (decisions/0067, 2026-10-07): counted against the words typed, so search learns
    // what they mean here. The kind and the words; never who
    var pk = e.target.closest('.tvs-t1 [data-pick]');
    if (pk && ptrack && ptrack.qs) { try { if (navigator.sendBeacon) navigator.sendBeacon(API + '?opened_kind=' + encodeURIComponent(pk.getAttribute('data-pick')) + '&' + ptrack.qs); } catch (x) {} }
    var fb = e.target.closest('[data-tv-suggest]');
    if (fb && track && /"kind":"feedback"/.test(fb.getAttribute('data-tv-suggest') || '')) outcome(['feedback'], track.qs);
    var t;
    var dmd = e.target.closest('[data-demand]');
    if (dmd && window.tvCount) { try { var dj = JSON.parse(dmd.getAttribute('data-demand')); if (dj.pl) window.tvCount({ m: 'demand', pl: dj.pl, an: dj.an, de: dj.de }); } catch (x) {} }
    // "Make it my place" on a place card: the set place changes only because they chose it (js/location-scope.js)
    if ((t = e.target.closest('[data-setplace]'))) {
      e.preventDefault();
      try { var sp = JSON.parse(t.getAttribute('data-setplace')); var lab = sp.label; delete sp.label; if (sp.scope) sp.scope.label = lab; if (typeof applyLocation === 'function') applyLocation(sp, 'picked'); t.querySelector('small').textContent = 'Done: ' + lab + ' is your place now'; } catch (x) {}
      return;
    }
    if ((t = e.target.closest('[data-tel]'))) { e.preventDefault(); location.href = 'tel:' + t.getAttribute('data-tel'); return; }   // counted by out-count.js (data-out)
    if ((t = e.target.closest('[data-else]'))) { e.preventDefault(); remember(input.value); mapAt(t); return; }
    if ((t = e.target.closest('[data-unrecent]'))) { var q0 = t.getAttribute('data-unrecent'); setRecents(recents().filter(function (x) { return x !== q0; })); recentList(); return; }
    if (e.target.closest('[data-clear-recent]')) { setRecents([]); recentList(); return; }
    if (e.target.closest('[data-qadd]')) {   // ⊕: the words in the field become a quick search; with none, the editor to type one
      var qa = input.value.trim();
      if (qa.length < 2) { editPhrases(); return; }
      setMyList([qa].concat(myList())); var pb0 = panel.querySelector('.tvs-presets'); if (pb0 && !pb0.querySelector('.tvs-pe')) pb0.innerHTML = presetList(lastPresets.title, lastPresets.list);
      presets();   // its count for here
      return;
    }
    if ((t = e.target.closest('[data-edit]'))) {   // put the suggestion in the field to change it first
      var fld = panel.querySelector('.tvs-in2') || input; input.value = t.getAttribute('data-edit'); if (fld !== input) fld.value = input.value;
      fld.focus(); try { fld.setSelectionRange(fld.value.length, fld.value.length); } catch (x) {}
      drop = []; run(); return;   // show its results now; keep typing to change them
    }
    if ((t = e.target.closest('[data-scope]'))) { setScope(t.getAttribute('data-scope')); return; }
    // your own search phrases
    if (e.target.closest('[data-tools]')) { var hd0 = panel.querySelector('.tvs-hd'), op = !hd0.classList.contains('open'); try { localStorage.setItem('tv_search_tools', op ? '1' : '0'); } catch (x) {} head(); return; }
    if (e.target.closest('[data-tier2]')) { keepNext = input.value.trim(); clearTimeout(timer); clearTimeout(sTimer); run(); return; }
    if (e.target.closest('[data-here]')) { if (nearGate()) { run(); return; } if (ptrack) ptrack.done = true; var qh2 = input.value.trim(); remember(qh2); window.tvSearchHere(qh2); applied = true; pushed = false; close(); return; }
    if (e.target.closest('[data-pedit]')) { editPhrases(); return; }
    if (e.target.closest('[data-pdone]')) { setMyList(editedList()); var pb = panel.querySelector('.tvs-presets'); if (pb) pb.innerHTML = presetList(lastPresets.title, lastPresets.list); presets(); return; }
    if ((t = e.target.closest('[data-pdel]'))) { t.closest('.tvs-li').remove(); setMyList(editedList()); return; }
    if (e.target.closest('[data-pnew]')) { var ai = panel.querySelector('.tvs-padd .tvs-phr'); addPhrase(ai && ai.value); return; }
    if ((t = e.target.closest('[data-padd]'))) { addPhrase(t.getAttribute('data-padd')); t.closest('.tvs-li').remove(); return; }
    if ((t = e.target.closest('[data-quick]'))) {
      var qk = t.getAttribute('data-quick'), qv = input.value.trim();
      if (window.tvQuick) { window.tvQuick.toggle(qk); filters(); return; }
      pushed = false; close();
      location.href = '/directory/?' + ({ near: 'near=me', open: 'open=now', phone: 'has=phone', website: 'has=website', fav: 'fav=1' })[qk] + (qv.length >= 2 ? '&q=' + encodeURIComponent(qv) : '');
      return;
    }
    if ((t = e.target.closest('[data-near-pick]'))) { nearPick(nearOpts[+t.getAttribute('data-near-pick')]); return; }
    if ((t = e.target.closest('[data-everywhere]'))) {   // everywhere, on purpose: not asked again this visit
      try { sessionStorage.setItem('tv_everywhere_ok', '1'); } catch (x) {}
      var bx = t.closest('.tvs-nearask'); if (bx) bx.remove();
      return;
    }
    if ((t = e.target.closest('[data-near-detect]'))) {
      // the device's place, asked once, only when tapped; the place applies as Auto-detect's does, and Distance goes to 25 mi
      var nb = t;
      if (!navigator.geolocation || typeof autoDetectLocation !== 'function') { nb.removeAttribute('data-near-detect'); nb.setAttribute('data-where', 'change'); nb.textContent = 'Choose a place'; return; }
      nearPending = true; nb.textContent = 'Finding you\u2026'; if (window.TvAsk && TvAsk.busy) TvAsk.busy(nb, true);
      navigator.geolocation.getCurrentPosition(function () { if (window.TvAsk && TvAsk.busy) TvAsk.busy(nb, false); autoDetectLocation({ currentTarget: nb }); }, function (err) {
        nearPending = false; if (window.TvAsk && TvAsk.busy) TvAsk.busy(nb, false);
        var box0 = nb.closest('.tvs-nearask, .tvs-nearwait, .tvs-askplace .tvs-nearrow');
        if (box0) box0.innerHTML = (err && err.code === 1 ? 'Location access is blocked in this browser.' : 'We couldn\u2019t find where you are.') + ' <button type="button" class="tvs-link" data-where="change">Choose a place</button> instead.';
      }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
      return;
    }
    if ((t = e.target.closest('[data-where]'))) {
      if (t.getAttribute('data-where') === 'all' && typeof chooseAnywhere === 'function') { placeOver = null; chooseAnywhere(); home(); }
      else if (panel.classList.contains('tvs-placing') && typeof closeLocationModal === 'function') closeLocationModal();   // the place button again: back to the results
      else if (typeof openLocationModal === 'function') openLocationModal();
      return;
    }
    if ((t = e.target.closest('[data-saved]'))) {
      var box = panel.querySelector('.tvs-saved'), sv = ((box && box._list) || []).filter(function (x) { return x.id === +t.getAttribute('data-saved'); })[0];
      if (sv) { placeOver = sv.place; scopeK = sv.scope || ''; paintToken(); input.value = sv.q; var i3 = panel.querySelector('.tvs-in2'); if (i3) i3.value = sv.q; drop = []; run(); }
      return;
    }
    if ((t = e.target.closest('[data-unsave]'))) { savePost({ action: 'remove', id: +t.getAttribute('data-unsave') }).then(loadSaved); t.closest('.tvs-li').remove(); return; }
    if ((t = e.target.closest('[data-save]'))) {
      var sp = placeOver || scope(), lab = placeOver ? placeOver.label : placeLabel();
      var go = function () {
        return savePost({ action: 'save', q: input.value.trim(), level: sp ? sp.level : '', slug: sp ? sp.slug : '', place: String(lab || '').split('·')[0].trim(), scope: scopeK,
          total: last && last.results ? last.results.total : 0 });
      };
      t.disabled = true; t.textContent = 'Saving…';
      (savedCsrf ? go() : fetch('/user/api/saved-searches.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) { savedCsrf = d.csrf; return go(); }))
        .then(function (d) {
          if (!d.ok) throw new Error((d.error && d.error.message) || 'Sign in to save searches.');
          savedIds[saveKey()] = d.id; t.textContent = '★ Saved'; t.classList.add('on');
        }).catch(function (x) { t.disabled = false; t.textContent = x.message; });
      return;
    }
    if (e.target.closest('[data-map]')) { if (window.tvCount) tvCount({ m: 'search_click', k: 'map' }); showMap(); return; }
    var sm = e.target.closest('[data-show]');
    if (sm) { panel.querySelectorAll('[data-rest="' + sm.getAttribute('data-show') + '"]').forEach(function (r) { r.hidden = false; }); sm.remove(); return; }
    var ft = e.target.closest('[data-funnel]');
    if (ft) { funnel = ft.getAttribute('data-funnel'); if (last) paint(last, input.value.trim()); return; }
    if (e.target.closest('[data-group-toggle]')) { try { localStorage.setItem('tv_search_group', grouped() ? '0' : '1'); } catch (x) {} if (last) paint(last, input.value.trim()); return; }
    var dr = e.target.closest('[data-drop]'); if (dr) { drop.push(dr.getAttribute('data-drop')); run(); return; }
    var wd = e.target.closest('[data-widen]');
    if (wd) { var wv = wd.getAttribute('data-widen'); if (saysNear()) nearWide = nearKey(); REACH.forEach(function (x, i) { if (String(x[0]) === wv) setReach(i); }); head(); run(); return; }
    var rc = e.target.closest('[data-recent]');
    // a tapped search is kept as a recent one once its results land, so its counts are its own
    if (rc) { keepNext = rc.getAttribute('data-recent'); input.value = rc.getAttribute('data-recent'); var i2 = panel.querySelector('.tvs-in2'); if (i2) i2.value = input.value; drop = []; run(); return; }
    var pk = e.target.closest('[data-peek]');
    if (pk) { e.preventDefault(); remember(input.value); peekGo(pk); return; }
    var a = e.target.closest('a[href]');
    if (a) { remember(input.value); pushed = false; }   // going somewhere they chose
  }
  function slide(e) {   // while dragging: the label; on release (change): search again
    if (!e.target.classList.contains('tvs-rng')) return;
    var i = +e.target.value, o = panel.querySelector('.tvs-rngv'); if (o) o.textContent = REACH[i][1]; e.target.setAttribute('aria-valuetext', REACH[i][1]);
  }
  function changes(e) {
    if (e.target.classList.contains('tvs-rng')) { setReach(+e.target.value); run(); return; }
    if (e.target.classList.contains('tvs-rad')) { radius = +e.target.value || 0; run(); }
    if (e.target.classList.contains('tvs-dsel')) setScope(e.target.value);   // Search Directory
  }
  function keys(e) {
    if (e.key === 'Escape') {   // first Escape clears the words, the next closes
      if (e.target.value) { e.target.value = ''; input.value = ''; placeOver = null; queue(); home(); return; }
      close(); return;
    }
    if (e.key === 'Backspace' && scopeK && e.target.selectionStart === 0 && e.target.selectionEnd === 0) { e.preventDefault(); setScope(''); return; }
    if (e.key === 'Enter') {
      e.preventDefault();
      var qh = e.target.value.trim();   // a page that shows search results itself (the directory) takes the words
      var named = sugSit === qh.toLowerCase() || !!(last && last._q === qh && last.situation);   // a situation: answered here, not as list words
      if (qh.length >= 2 && nearGate()) { run(); return; }   // "near me" with no place: where first, on every page (the directory list too)
      if (qh.length >= 2 && !named && typeof window.tvSearchHere === 'function' && window.tvSearchHere(qh)) { if (ptrack) ptrack.done = true; remember(qh); pushed = false; applied = true; close(); return; }
      if (!last || last._q !== qh || panel.classList.contains('tvs-typing')) { keepNext = qh; clearTimeout(timer); clearTimeout(sTimer); run(); return; }   // Enter: the full search, now (and kept)
      var first = panel && panel.querySelector('.tvs-full .tvs-top-card a, .tvs-full a.tvs-row, .tvs-full .tvs-more');
      if (first) { remember(input.value); pushed = false; location.href = first.href; }   // ...and again: the first result's page
    }
  }

  var selectNext = false;
  // on the Directory the words already searched are on the page (its "“Found” ×" filter), so the search opens empty for
  // new words (Jason, 2026-11-11: "when I type in search it carries in the previous found"); elsewhere they're kept, selected
  function openFresh() { if (!panel && typeof window.tvSearchHere === 'function' && input.value.trim()) { input.value = ''; drop = []; radius = 0; } open(); }
  input.addEventListener('focus', function () { openFresh(); });   // the pop-up has its own field, which takes the words
  input.addEventListener('mouseup', function (e) { if (selectNext) { e.preventDefault(); selectNext = false; input.select(); } });
  input.addEventListener('blur', function () { selectNext = false; });
  input.addEventListener('input', function () { drop = []; radius = 0; if (!input.value.trim()) placeOver = null; if (!panel) open(); else queue(); });
  input.addEventListener('keydown', keys);
  var btn = root.querySelector('.tvs-open'); if (btn) btn.addEventListener('click', openFresh);
  document.addEventListener('keydown', function (e) {   // Ctrl K / ⌘K opens the site search, as the folded ⌕ does (2026-11-19); the admin's has its own
    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K') && !document.getElementById('tv-asearch')) { e.preventDefault(); openFresh(); }
  });
  var sb = root.querySelector('.tvs-sbtn');   // the bar's search button: open the panel, or search the words again
  if (sb) sb.addEventListener('click', function (e) { e.stopPropagation(); if (!panel) open(); else run(); input.focus(); });
  // For pages: open a search with words already in (a preset on Discover), or a listing's peek card.
  window.TvSearch = {
    // deferred: called from a click, the same click would otherwise reach the "click outside closes it" handler
    open: function (q) { setTimeout(function () { input.value = q || ''; keepNext = input.value.trim(); drop = []; radius = 0; if (!panel) open(); var i2 = panel && panel.querySelector('.tvs-in2'); if (i2) i2.value = input.value; run(); }, 0); },
    peek: function (id) { peek(id); },
    // the place: the search's own window, with its place picker inside (Jason, 2026-11-05: never a pop-up on its own)
    where: function () { setTimeout(function () { if (panel) { if (typeof openLocationModal === 'function') openLocationModal(); return; } wantPlace = true; open(); }, 0); },
    // the Distance setting, for the place picker when it hangs under a split box's Where (the home page)
    reach: function () { return { i: reachI, steps: REACH, on: !!(placeOver || placeLabel() || scope()) }; },
    setReach: function (i) { setReach(Math.max(0, Math.min(REACH.length - 1, +i || 0))); placeTag(); if (panel) { head(); run(); } }
  };
  window.tvSearchWhere = function () { window.TvSearch.where(); };   // for data-tv-click (the home page's Where, the header's pin)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-tv-peek]'); if (!a) return;
    e.preventDefault(); peek(a.getAttribute('data-tv-peek'));
  });
  // Where to search: the pin opens the place chooser; the placeholder names the place (like the directory's bar).
  function placeLabel() { var s = scope(), l = document.getElementById('menu-location-label'); return s ? (currentLocation.scope.label || s.slug) : (l && l.textContent && !/not set/i.test(l.textContent) ? l.textContent : ''); }
  function syncPlace() {
    var p = placeLabel(), pin = root.querySelector('.tvs-pin');
    input.placeholder = p ? 'What are you looking for?' : 'Search places, listings, groups, stories';
    if (pin) pin.classList.toggle('set', !!p);
    var pl = pin && pin.querySelector('[data-tvs-pl]'); if (pl) { pl.textContent = p ? 'in ' + p : 'Anywhere'; pin.title = p ? 'Searching in ' + p + ': choose where' : 'Choose where to search'; }   // the place as a chip (2026-11-19)
    var i2 = panel && panel.querySelector('.tvs-in2'); if (i2) i2.placeholder = input.placeholder;
    placeTag();
    if (panel) { drop = drop.filter(function (d) { return d !== 'scope'; }); run(); }
  }
  // the pop-up's place button names the place and how far around it (the picker it opens holds both)
  function placeTag() {
    // the Town or ZIP box: the place this search uses (a saved search's own place too), × when there is one
    var pbx = panel && panel.querySelector('[data-place-box]');
    if (pbx) {
      var pl0 = String((placeOver ? placeOver.label : placeLabel()) || '').split('\u00b7')[0].trim();
      pbx.setAttribute('data-label', pl0); if (pbx.tvPaint) pbx.tvPaint();
      var px = pbx.querySelector('.tv-pbox-x'); if (px) px.hidden = !pl0;
      return;
    }
    var t = panel && panel.querySelector('.tvs-pl2'); if (!t) return;
    var p = String((placeOver ? placeOver.label : placeLabel()) || '').split('\u00b7')[0].trim();
    var rg = scope() && /^(hub|geo_hub)$/.test(String(scope().level || ''));   // a region: no distance after its name
    t.textContent = p ? p + (rg ? '' : ' \u00b7 ' + REACH[reachI][1]) : 'Choose a place';
  }
  var lbl = document.getElementById('menu-location-label');
  if (lbl && window.MutationObserver) new MutationObserver(syncPlace).observe(lbl, { childList: true, characterData: true, subtree: true });
  setTimeout(syncPlace, 600);
  // first, before any pop-up's own Esc: is one open over the panel? Then this Esc is that pop-up's alone
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.querySelector('.tvs-peek, .tv-ask-lb')) e.tvInner = true; }, true);
  document.addEventListener('keydown', function (e) {
    var mb = (e.key === 'Enter' || e.key === ' ') && e.target.closest && e.target.closest('.tvs-mapb');
    if (mb) { e.preventDefault(); mb.click(); return; }   // the map button by keyboard
    var pb = (e.key === 'Enter' || e.key === ' ') && e.target.closest && e.target.closest('.tvs-peekb');
    if (pb) { e.preventDefault(); e.stopPropagation(); peekGo(pb); return; }   // the Peek button by keyboard
    if (e.key === 'Escape' && panel && !e.tvInner && !(e.target.classList && (e.target.classList.contains('tvs-in') || e.target.classList.contains('tvs-in2')))) close();   // in the field, its own Escape decides
    if (e.key === '/' && !panel && !/input|textarea|select/i.test((document.activeElement || {}).tagName || '')) { e.preventDefault(); open(); }
  });
  document.addEventListener('click', function (e) {
    if (!panel || panel.classList.contains('full') || !e.target.isConnected) return;   // (a button the panel just re-drew isn't "outside")
    if (!panel.contains(e.target) && !root.contains(e.target) && !e.target.closest('.tvs-peek, .tvs-peek-dim, .tv-cs, .tv-chip, .tv-dim, .sg-bg')) close();   // the consent lightbox isn't "outside"
  });
})();
