/* js/admin-control.js — System Configuration → System Control Board (decisions/0171, release 2b). Each button asks first
 * where it changes something (data-confirm), shows its spinner (TvAsk.busy), answers beside itself (TvAsk.say), and the
 * board is drawn again in place: the stages, where the system stands and each system with its processes, keeping what's
 * open. The board also looks again every 30 seconds while it's showing, and at once when its tab is shown again. The
 * pre-flight fold below is left as it is (its own script runs it). The find box narrows the rows to those whose words,
 * needs or sources hold what's typed; Open all and Close all open or close every row; a held process's link in Holding now
 * opens it; Re-test the four runs the query timings one after another (2026-11-18, laid out after Jason's example board). */
(function () {
  var box = document.getElementById('cb');
  if (!box) return;
  var csrf = box.getAttribute('data-csrf') || '', going = false;

  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: { message: 'The server didn\'t answer clearly. Try again; still stuck? Write to support@traversence.com.' } }; }); })
      .then(function (j) { if (!j.ok) throw new Error((j.error && j.error.message) || 'Something went wrong. Try again.'); return j; });
  }
  function say(msg, el, bad) { if (window.TvAsk) TvAsk.say(msg, { anchor: el, bad: !!bad }); }

  // a system's counts filter its rows (Jason, 2026-11-19: "click on trip or held and have the section filter to those"):
  // a press opens the system and shows only that light; again, or Show all, shows every row. Kept through each redraw.
  var lf = {};
  function lfApply() {
    box.querySelectorAll('details.cb-sys').forEach(function (sys) {
      var k = lf[sys.id] || '', on = sys.querySelector('.cb-lfon'), names = { ok: 'working', bad: 'tripped', hold: 'held', warn: 'on a backup', maint: 'in maintenance', off: 'off', wait: 'not tested' };
      sys.querySelectorAll('details.cb-p[data-light]').forEach(function (p) { p.style.display = k && p.getAttribute('data-light') !== k ? 'none' : ''; });
      sys.querySelectorAll('.cb-sum [data-cb-lf]').forEach(function (b) { var is = !!k && b.getAttribute('data-cb-lf') === k; b.classList.toggle('on', is); b.setAttribute('aria-pressed', is ? 'true' : 'false'); });
      if (on) { on.hidden = !k; var w = on.querySelector('[data-cb-lfw]'); if (w) w.textContent = k ? 'Showing only the ' + (names[k] || k) + ' ones.' : ''; }
    });
  }
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cb-lf]'); if (!b) return;
    e.preventDefault(); e.stopPropagation();
    var sys = b.closest('details.cb-sys'); if (!sys) return;
    var k = b.getAttribute('data-cb-lf');
    lf[sys.id] = k && lf[sys.id] !== k ? k : '';
    sys.open = true; lfApply();
  }, true);

  // a report's downloads (release 5b): the server builds the CSV or the page as a file; saved here as a file
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cb-dl]'); if (!b) return;
    e.preventDefault(); if (window.TvAsk) TvAsk.busy(b, true);
    post('/admin/api/config/control', { action: 'report', format: b.getAttribute('data-cb-dl'), scope: b.getAttribute('data-cb-scope') || '', range: b.getAttribute('data-cb-range') || 'day' }).then(function (j) {
      if (window.TvAsk) TvAsk.busy(b, false);
      var url = URL.createObjectURL(new Blob([j.body], { type: j.type + ';charset=utf-8' })), a = document.createElement('a');
      a.href = url; a.download = j.name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
      say(j.message, b);
    }, function (err) { if (window.TvAsk) TvAsk.busy(b, false); say(err.message, b, true); });
  });

  // the board drawn again in place
  function redraw() {
    if (going) return; going = true;
    var open = {}; box.querySelectorAll('details[id]').forEach(function (d) { open[d.id] = d.open; });
    fetch(location.pathname + location.search, { credentials: 'include' }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (html) {
      if (!html) return;
      var doc = new DOMParser().parseFromString(html, 'text/html'), nb = doc.getElementById('cb');
      if (!nb) return;
      if (box.querySelector('[aria-busy="true"]')) return;   // a press is still working: next time
      ['.cb-strip', '#cb-q3', '.cb-stage', '#cb-alarms', '#cb-times', '#maintenance > summary', '#maintenance .cb-mlist', '#maintenance .cb-uplist', '#cb-p-site-needs'].forEach(function (sel) { var a = box.querySelector(sel), b = nb.querySelector(sel); if (!a || !b) return; var nu = document.importNode(b, true);
        if (nu.id && nu.id in open) nu.open = open[nu.id]; nu.querySelectorAll('details[id]').forEach(function (d) { if (d.id in open) d.open = open[d.id]; });   // what's open stays open
        a.parentNode.replaceChild(nu, a); });
      nb.querySelectorAll('details.cb-sys').forEach(function (n) {
        var cur = document.getElementById(n.id), nu = document.importNode(n, true);
        nu.querySelectorAll('details[id]').forEach(function (d) { if (d.id in open) d.open = open[d.id]; });
        if (n.id in open) nu.open = open[n.id];
        // a notice or an "are you sure" under a row stays where it was (the redraw would wipe it a second after a press)
        var keep = cur ? Array.prototype.map.call(cur.querySelectorAll('.tv-note, .tv-ask'), function (n) { var p = n.previousElementSibling; return [n, p && p.id]; }) : [];
        if (cur) cur.parentNode.replaceChild(nu, cur);
        keep.forEach(function (k) { var after = k[1] && nu.querySelector('#' + CSS.escape(k[1])); if (after) after.parentNode.insertBefore(k[0], after.nextSibling); });
      });
      filter(); afilter(); loadOpen(); lfApply(); tabsApply(); tableReload();
      if (window.TvTime) TvTime.paint(box);
    }).catch(function () {}).then(function () { going = false; });
  }
  // the logbook (0171 §10, release 5a): its one filter (a dropdown button), Show older, and Log an entry with the device's zone
  var logScope = '';
  function logLoad(before, btn) {
    var list = box.querySelector('[data-cb-loglist]'); if (!list) return;
    if (btn) TvAsk.busy(btn, true);
    post('/admin/api/config/control', { action: 'logfeed', scope: logScope, before: before || 0 }).then(function (j) {
      if (btn) { TvAsk.busy(btn, false); var li = btn.closest('li'); if (li) li.remove(); list.insertAdjacentHTML('beforeend', j.html || ''); } else list.innerHTML = j.html || '';
      if (window.TvTime) TvTime.paint(list);
    }, function (err) { if (btn) TvAsk.busy(btn, false); say(err.message, btn || list, true); });
  }
  box.addEventListener('click', function (e) {
    var lb = e.target.closest('[data-cb-logb]'), lw = e.target.closest('.cb-lgw');
    if (lb) { var l = lw.querySelector('.ak-fl'), open = l.hidden; l.hidden = !open; lb.setAttribute('aria-expanded', open ? 'true' : 'false'); return; }
    var lv = e.target.closest('[data-cb-logv]');
    if (lv && lw) { logScope = lv.getAttribute('data-cb-logv'); lw.querySelectorAll('[data-cb-logv]').forEach(function (x) { x.classList.toggle('on', x === lv); });
      lw.querySelector('[data-cb-logname]').textContent = lv.getAttribute('data-label') || lv.textContent; lw.querySelector('.ak-fl').hidden = true; lw.querySelector('[data-cb-logb]').setAttribute('aria-expanded', 'false'); logLoad(0); return; }
    var more = e.target.closest('[data-cb-logmore]');
    if (more) { logLoad(parseInt(more.getAttribute('data-cb-logmore'), 10) || 0, more); return; }
    var note = e.target.closest('[data-cb-lognote]');
    if (note && !note.disabled) { var f = document.getElementById('cb-log-form'); if (!f || !f.reportValidity()) return;
      var tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (x) {}
      TvAsk.busy(note, true);
      post('/admin/api/config/control', { action: 'lognote', text: f.elements.text.value, scope: f.elements.scope.value, tz: tz }).then(function (j) { TvAsk.busy(note, false); f.reset(); say(j.message || 'Logged.', note); logLoad(0); },
        function (err) { TvAsk.busy(note, false); say(err.message, note, true); }); }
  });
  box.addEventListener('submit', function (e) { if (e.target.id === 'cb-log-form') { e.preventDefault(); var b = e.target.querySelector('[data-cb-lognote]'); if (b) b.click(); } });

  // the historian's trends (0171 §6, release 4b): each loads when its row or system opens, its range buttons switch it in
  // place, and a redraw puts it back from a 5-minute cache (with the range chosen) instead of asking again
  var trCache = {}, trRange = {};
  function loadTrend(h, range, btn) {
    var id = h.getAttribute('data-cb-trend'); range = range || trRange[id] || '24h'; trRange[id] = range;
    var k = id + '|' + range, c = trCache[k];
    if (c && Date.now() - c[0] < 300000) { h.innerHTML = c[1]; h.setAttribute('data-loaded', range); return; }
    if (btn) TvAsk.busy(btn, true); else h.setAttribute('aria-busy', 'true');
    post('/admin/api/config/control', { action: 'trend', id: id, range: range }).then(function (j) {
      trCache[k] = [Date.now(), j.html || '']; h.removeAttribute('aria-busy'); if (btn) TvAsk.busy(btn, false);
      h.innerHTML = j.html || ''; h.setAttribute('data-loaded', range);
    }, function (err) { h.removeAttribute('aria-busy'); if (btn) TvAsk.busy(btn, false); say(err.message, btn || h, true); });
  }
  function loadOpen() {
    box.querySelectorAll('details.cb-p[open] .cb-trend, details.cb-sys[open] > .cb-sys-tr .cb-trend, details.cb-trends[open] .cb-trend').forEach(function (h) {
      var id = h.getAttribute('data-cb-trend'); if (h.getAttribute('data-loaded') !== (trRange[id] || '24h')) loadTrend(h); });
  }
  box.addEventListener('toggle', function (e) { if (e.target.open && e.target.matches && e.target.matches('details.cb-p, details.cb-sys, details.cb-trends')) loadOpen(); }, true);
  box.addEventListener('click', function (e) {
    var r = e.target.closest('[data-cb-range]'); if (!r) return;
    var h = r.closest('.cb-trend'); if (h) { e.preventDefault(); loadTrend(h, r.getAttribute('data-cb-range'), r); }
  });
  loadOpen();
  // find: each process row whose words, needs or sources hold every word typed; its system opens
  var q = box.querySelector('[data-cb-filter]');
  function filter() {
    var words = ((q && q.value) || '').toLowerCase().split(/\s+/).filter(Boolean), any = false;
    box.querySelectorAll('details.cb-sys').forEach(function (sys) {
      var shown = 0;
      sys.querySelectorAll('details.cb-p').forEach(function (p) {
        var hay = p.getAttribute('data-q') || '', hit = words.every(function (w) { return hay.indexOf(w) >= 0; });
        p.hidden = !hit; if (hit) shown++;
      });
      sys.hidden = words.length > 0 && shown === 0;
      if (words.length && shown) sys.open = true;
      if (shown) any = true;
    });
    var none = box.querySelector('.cb-none'); if (none) none.hidden = any || !words.length;
  }
  if (q) q.addEventListener('input', filter);
  // the queue's one filter: a dropdown button (icon, current choice, count, ▾) that admin-kit.js moves into the page's header
  // (Open, Acknowledged, Cleared, All; or a level), kept through each redraw, with each choice's count
  var aShow = 'open', fw = document.querySelector('.cb-afw');
  // an acknowledged trip stays in the Open list and its counts while it holds (D5: one count on every surface; 02 row 15,
  // g4 F1: "Acknowledge never turns it green"); an acknowledged warning or notice moves under Acknowledged
  function hit(li, k) { var st = li.getAttribute('data-astate'), lv = li.getAttribute('data-alevel'), open = st === 'open' || (st === 'ack' && lv === 'trip');
    return k === 'all' || k === st || (k === 'open' && open) || (['trip', 'warning', 'notice'].indexOf(k) >= 0 && lv === k && open); }
  function afilter() {
    var list = box.querySelectorAll('#cb-alarms ul.cb-al:not(.cb-al-in) > li[data-astate]'), n = 0;   // a group counts once; its alarms show inside it
    list.forEach(function (li) { var h = hit(li, aShow); li.hidden = !h; if (h) n++; });
    // inside a group, its members follow the same choice (2026-11-19: an acknowledged warning still showed in an open group)
    box.querySelectorAll('#cb-alarms ul.cb-al-in > li[data-astate]').forEach(function (li) { li.hidden = aShow !== 'all' && !hit(li, aShow); });
    var none = box.querySelector('#cb-alarms .cb-anone'); if (none) none.hidden = n > 0 || !list.length;
    if (!fw) return;
    // counts are alarms, not rows: a group counts each of its alarms, as the panel and the top bar do (Jason, 2026-11-19)
    var many = function (li, k) { var m = li.querySelectorAll('ul.cb-al-in > li[data-astate]'), c = 0; if (!m.length) return hit(li, k) ? 1 : 0; m.forEach(function (x) { if (hit(x, k)) c++; }); return c; };
    n = 0; list.forEach(function (li) { n += many(li, aShow); });
    fw.querySelectorAll('[data-cb-afv]').forEach(function (b) { var k = b.getAttribute('data-cb-afv'), c = 0; list.forEach(function (li) { c += many(li, k); });
      b.querySelector('i').textContent = c; b.classList.toggle('on', k === aShow); if (k === aShow) fw.querySelector('[data-cb-afname]').textContent = b.firstChild.textContent; });
    fw.querySelector('[data-cb-afcount]').textContent = n;
  }
  // the logbook's list: a box finds a part; a system's heading stays while any of its parts match
  box.addEventListener('input', function (e) {
    var q = e.target.closest('[data-cb-logq]'); if (!q) return;
    var w = q.value.trim().toLowerCase().split(/\s+/).filter(Boolean), fl = q.closest('.ak-fl'), any = false;
    fl.querySelectorAll('[data-cb-lgs]').forEach(function (g) { var hit = false;
      g.querySelectorAll('.cb-lgp').forEach(function (b) { var s = b.getAttribute('data-q') || '', ok = w.every(function (x) { return s.indexOf(x) >= 0; }); b.hidden = !ok; if (ok) hit = true; });
      var h = g.querySelector('button.cb-lgh'); if (h && !hit && w.length && w.every(function (x) { return (h.getAttribute('data-q') || '').indexOf(x) >= 0; })) hit = true;
      g.hidden = !hit; if (hit) any = true; });
    var all = fl.querySelector('.cb-lgh[data-cb-logv=""]'); if (all) all.hidden = w.length > 0;
    var none = fl.querySelector('.cb-lgnone'); if (none) none.hidden = any;
  });
  if (fw) fw.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cb-afb]'), l = fw.querySelector('.ak-fl');
    if (b) { var open = l.hidden; l.hidden = !open; b.setAttribute('aria-expanded', open ? 'true' : 'false'); return; }
    var v = e.target.closest('[data-cb-afv]');
    if (v) { aShow = v.getAttribute('data-cb-afv'); l.hidden = true; fw.querySelector('[data-cb-afb]').setAttribute('aria-expanded', 'false'); afilter(); if (box.hasAttribute('data-tab')) toTab('observe');
      // the list it filters comes into view, so a pick is seen to do something (Jason, 2026-11-19: "the drop down doesn't do anything")
      var al = document.getElementById('cb-alarms'); if (al) { al.open = true; al.scrollIntoView({ behavior: 'smooth', block: 'start' }); al.classList.add('ak-landed'); setTimeout(function () { al.classList.remove('ak-landed'); }, 2600); } }
  });
  afilter();
  // the six stages are tabs (Jason, 2026-11-19: "When clicked that information is what is on page, everything else goes
  // away"): each shows only its part; the one the system is in opens first; a link to a part opens its tab; kept through redraws
  var PARTS = [['#cb-deliver', 'deliver'], ['#cb-dev', 'development'], ['#cb-incidents', 'incidents'], ['#preflight', 'preflight'], ['#cb-pfres', 'preflight startup'], ['#cb-controls', 'startup observe shutdown maintenance'], ['#cb-alarms', 'observe'], ['#cb-times', 'observe'],
    ['#maintenance', 'maintenance'], ['#log', 'history'], ['#reports', 'history reports'],
    // the board's tables and pages as tabs (decisions/0175's note, release 2b part A): Alarms, Logbook, Ledger, Upkeep,
    // Reports, Scheduled tasks; Automation in Start-up; the system pre-flight's page in Pre-flight and Start-up
    ['#cb-tab-alarms', 'alarms'], ['#cb-tab-log', 'log'], ['#cb-tab-ledger', 'ledger'], ['#cb-tab-upkeep', 'upkeep'], ['#cb-tab-scheduler', 'scheduler'],
    ['#cb-tab-startup', 'startup'], ['#cb-tab-pfpage', 'preflight startup'], ['#cb-trends', 'history'], ['#cb-site-needs', 'preflight observe'], ['#cb-systems', 'startup observe shutdown maintenance'], ['details.cb-sys', 'startup observe shutdown maintenance']];
  var tab = box.getAttribute('data-tab') || 'deliver';   // "Is it delivering?" is the first view (03 §4, §27)
  function tabsApply() {
    if (!box.hasAttribute('data-tab')) return;   // a process's own page: everything shows
    PARTS.forEach(function (p) { box.querySelectorAll(p[0]).forEach(function (el) { el.classList.toggle('cb-offtab', (' ' + p[1] + ' ').indexOf(' ' + tab + ' ') < 0); }); });
    box.querySelectorAll('[data-cb-tab]').forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-cb-tab') === tab ? 'true' : 'false'); });
  }
  function tabOf(el) { for (var i = 0; i < PARTS.length; i++) { var c = el.closest(PARTS[i][0]); if (c) return PARTS[i][1].split(' ')[0]; } return ''; }
  function toTab(t) { if (!t) return; if (t !== tab) spClose(); tab = t; box.setAttribute('data-tab', t); tabsApply(); lazy(); }
  // a tab's page with its own forms, loaded in place the first time its tab opens (Scheduled tasks, Automation, the
  // pre-flight's page): a frame the height of its page, its links opening in the board's one side panel (peek=1); a link's
  // own spot (#job-<key>, #pf-<line>) goes with it when the board doesn't have it itself
  function lazy() {
    box.querySelectorAll('[data-cb-lazy]').forEach(function (p) {
      if (p.classList.contains('cb-offtab') || p.getAttribute('data-cb-loaded')) return;
      p.setAttribute('data-cb-loaded', '1');
      var h = decodeURIComponent((location.hash || '').slice(1)), at = h && h.indexOf('=') < 0 && !document.getElementById(h) ? '#' + encodeURIComponent(h) : '';
      var f = document.createElement('iframe'); f.className = 'cb-lazyf'; f.title = p.getAttribute('data-cb-lazyt') || 'Part of the board'; f.src = p.getAttribute('data-cb-lazy') + at;
      p.innerHTML = ''; p.appendChild(f);
      var fit = function () { try { var d = f.contentDocument; if (d && d.documentElement) f.style.height = Math.max(192, d.documentElement.scrollHeight) + 'px'; } catch (e) {} };
      f.addEventListener('load', function () { fit(); try { new ResizeObserver(fit).observe(f.contentDocument.documentElement); } catch (e) { setInterval(fit, 1000); } });
    });
  }
  function fromHash() { var id = decodeURIComponent((location.hash || '').slice(1)); if (!id || id.indexOf('=') >= 0) return; var el = document.getElementById(id); if (!el || !box.contains(el)) return; toTab(tabOf(el));
    // an alarm the filter hides (acknowledged): the filter shows its kind, so a link to it never lands on nothing (Jason, 2026-11-19: "alarm list link sends me to dead end")
    var li = el.closest('li[data-astate]'), st = li && li.getAttribute('data-astate');
    if (li && li.hidden && st) { aShow = st === 'open' ? 'open' : st; afilter(); var al = document.getElementById('cb-alarms'); if (al) al.open = true; var g = li.closest('details'); if (g) g.open = true;
      setTimeout(function () { li.scrollIntoView({ block: 'center', behavior: 'smooth' }); li.classList.add('ak-landed'); setTimeout(function () { li.classList.remove('ak-landed'); }, 2600); }, 80); } }
  var MAIN = { preflight: '#preflight', observe: '#cb-alarms', maintenance: '#maintenance', history: '#log' };   // a tab's own panel opens with it
  box.addEventListener('click', function (e) { var b = e.target.closest('[data-cb-tab]'); if (!b) return; toTab(b.getAttribute('data-cb-tab'));
    var m = MAIN[tab] && box.querySelector(MAIN[tab]); if (m && m.tagName === 'DETAILS') m.open = true; window.scrollTo({ top: Math.max(0, box.getBoundingClientRect().top + window.scrollY - 8), behavior: 'smooth' }); });
  window.addEventListener('hashchange', fromHash);
  // an old address (Scheduled tasks, Automation, Pre-flight, the board's tables) lands here with ?tab=<its tab>
  (function () { var m = /[?&]tab=([a-z]+)/.exec(location.search); if (m && box.querySelector('[data-cb-tab="' + m[1] + '"]')) tab = m[1], box.setAttribute('data-tab', tab); })();
  fromHash(); tabsApply(); lazy();
  box.addEventListener('click', function (e) {
    // More / Less: the same line opens in place, nothing repeated
    var mb = e.target.closest('[data-cb-more]');
    if (mb) { var c = mb.closest('[data-cb-clamp]'); if (c) { if (e.target.closest('a')) return; e.preventDefault(); var on = !c.classList.contains('open'), t = c.querySelector('button[data-cb-more]'); c.classList.toggle('open', on);
      if (t) { t.setAttribute('aria-expanded', on ? 'true' : 'false'); t.setAttribute('aria-label', on ? 'Fold it to one line' : 'Show all of it'); t.title = t.getAttribute('aria-label'); } } return; }
    var all = e.target.closest('[data-cb-all]');
    if (all) { var on = all.getAttribute('data-cb-all') === '1'; box.querySelectorAll('details.cb-sys, details.cb-p').forEach(function (d) { if (!d.hidden) d.open = on; });
      box.querySelectorAll('#cb-systems tr.akt-grp').forEach(function (g) { if ((g.getAttribute('aria-expanded') === 'true') !== on) { var gb = g.querySelector('[data-ak-grp]'); if (gb) gb.click(); } }); return; }
    var go = e.target.closest('[data-cb-go]');
    if (go && !document.getElementById(go.getAttribute('data-cb-go')) && /^cb-p-/.test(go.getAttribute('data-cb-go'))) {   // a part's row lives on its own page now (#192): open it there, never a dead link
      e.preventDefault(); var ref = go.getAttribute('data-cb-go').slice(5); if (window.TvAsk && TvAsk.peek) TvAsk.peek('/admin/config/control?proc=' + encodeURIComponent(ref), go.textContent || 'Its own page'); else location.href = '/admin/config/control?proc=' + encodeURIComponent(ref); return; }
    if (go) { var t = document.getElementById(go.getAttribute('data-cb-go')); if (t) { e.preventDefault(); var s = t.closest('details.cb-sys'); if (s) s.open = true; t.open = true; t.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
  });
  // opened from a link to a part of the board (the top bar's light: #cb-alarms): that part opens, everything else stays closed
  function openHash() { var t = location.hash && document.getElementById(location.hash.slice(1)); if (!t || !box.contains(t)) return;
    for (var d = t; d && d !== box; d = d.parentElement) if (d.tagName === 'DETAILS') d.open = true;
    setTimeout(function () { t.scrollIntoView({ block: 'start' }); }, 50); }
  openHash(); window.addEventListener('hashchange', openHash);
  setInterval(function () { if (!document.hidden) redraw(); }, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) redraw(); });

  // the parts' table (the admin's one table, T1): its rows reload with the board, and its buttons act as the board's own
  function tableReload() { var t = box.querySelector('#cb-systems [data-ak-table]'); if (t && t.__list && !t.querySelector('.akt-rml:not([hidden])')) t.__list.load(); }
  // a system's counts filter its parts (Jason, 2026-11-19, again after the table: "thought I could click listed items on the
  // right to filter the rows?"): each count on a group row ("2 tripped · 2 held") is a link that picks that light in the
  // table's own Show menu and opens the system; pressed again, every part shows. Built here, beside the table component.
  var gOpen = {};   // the systems opened by hand, kept through every reload of the table (the old board kept what was open)
  box.addEventListener('click', function (e) { var gb = e.target.closest('#cb-systems [data-ak-grp]'); if (!gb || e.target.closest('[data-cb-gf]')) return;
    setTimeout(function () { var g = gb.closest('tr.akt-grp'); if (g) { if (g.getAttribute('aria-expanded') === 'true') gOpen[g.getAttribute('data-grp')] = 1; else delete gOpen[g.getAttribute('data-grp')]; } }, 0); });
  var gfWant = null;   // { grp, v, old }: the system to open once the table has drawn the new rows
  function gfShow() {   // the Show menu's choices: { "tripped": "bad", "held": "hold", … } and the one chosen now
    var t = box.querySelector('#cb-systems [data-ak-table]'), m = {}, on = '';
    if (t) t.querySelectorAll('.ak-fb[data-fk="show"] + .ak-fl [data-fv]').forEach(function (b) {
      var w = (b.firstChild && b.firstChild.nodeType === 3 ? b.firstChild.nodeValue : b.textContent).trim().toLowerCase(), v = b.getAttribute('data-fv');
      if (v) m[w] = v; if (b.classList.contains('on')) on = v; });
    return { map: m, on: on, t: t };
  }
  function gfPaint() {
    var f = gfShow(); if (!f.t) return;
    f.t.querySelectorAll('tr.akt-grp .akt-gt > small').forEach(function (sm) {
      if (sm.getAttribute('data-cb-gf-done') === f.on) return;
      var txt = sm.getAttribute('data-cb-gf-text') || sm.textContent; sm.setAttribute('data-cb-gf-text', txt);
      sm.innerHTML = txt.split(' · ').map(function (part) {
        var mm = /^(\d+)\s+(.+)$/.exec(part.trim()), k = mm && f.map[mm[2].toLowerCase()];
        if (!k) return part.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
        return '<span role="button" tabindex="0" class="cb-lf' + (f.on === k ? ' on' : '') + '" data-cb-gf="' + k + '" title="' + (f.on === k ? 'Show every part' : 'Show only the ' + mm[2] + ' parts') + '">' + part + '</span>';
      }).join(' · ');
      sm.setAttribute('data-cb-gf-done', f.on);
    });
    f.t.querySelectorAll('tr.akt-grp').forEach(function (g) { if (g._cbk) return; g._cbk = 1;   // a row drawn anew: open again if it was open
      if (gOpen[g.getAttribute('data-grp')] && g.getAttribute('aria-expanded') !== 'true') { var gb = g.querySelector('[data-ak-grp]'); if (gb) gb.click(); } });
    if (gfWant && f.on === gfWant.v) { var g = f.t.querySelector('tr.akt-grp[data-grp="' + gfWant.grp + '"]');
      if (g && g !== gfWant.old) { gfWant = null; if (g.getAttribute('aria-expanded') !== 'true') { var gb = g.querySelector('[data-ak-grp]'); if (gb) gb.click(); } } }
  }
  function gfPick(el, e) {
    e.preventDefault(); e.stopPropagation();   // the count, not the group's own open and close
    var f = gfShow(), k = el.getAttribute('data-cb-gf'), v = f.on === k ? '' : k, tr = el.closest('tr.akt-grp');
    gfWant = v && tr ? { grp: tr.getAttribute('data-grp'), v: v, old: tr } : null;
    var opt = f.t && f.t.querySelector('.ak-fb[data-fk="show"] + .ak-fl [data-fv="' + v + '"]'); if (opt) opt.click();
  }
  box.addEventListener('click', function (e) { var el = e.target.closest('[data-cb-gf]'); if (el && box.contains(el)) gfPick(el, e); }, true);
  box.addEventListener('keydown', function (e) { var el = e.target.closest && e.target.closest('[data-cb-gf]'); if (el && (e.key === 'Enter' || e.key === ' ')) gfPick(el, e); }, true);
  (function () { var t = box.querySelector('#cb-systems'); if (t && window.MutationObserver) new MutationObserver(function () { gfPaint(); }).observe(t, { childList: true, subtree: true }); gfPaint(); })();

  box.addEventListener('tv-table:act', function (e) {
    var d = e.detail || {}, r = d.row || {}, k = d.key, btn = d.button, list = d.list;
    if (!d.list || !d.list.el || !d.list.el.closest('#cb-systems')) return;
    e.preventDefault();
    if (k === 'page') { if (window.TvAsk && TvAsk.peek) TvAsk.peek('/admin/config/control?proc=' + encodeURIComponent(r.ref || ''), r.name || 'Its own page'); return; }
    var map = { check: ['check', r.id], resolve: ['resolve', String(r.trip_id || '')], start: ['start1', r.id], stop: ['stop', r.id], maintout: ['maintout', r.id], maintback: ['maintback', String(r.maint_id || '')] }, m = map[k];
    if (!m) return;
    var go = function (extra) { if (window.TvAsk) TvAsk.busy(btn, true);
      return post('/admin/api/config/control', Object.assign({ action: m[0], id: m[1] }, extra || {})).then(function (j) { if (window.TvAsk) TvAsk.busy(btn, false); say(j.message || 'Done.', btn, !!j.bad); setTimeout(function () { if (list) list.load(); }, 900); },
        function (err) { if (window.TvAsk) TvAsk.busy(btn, false); say(err.message, btn, true); }); };
    if (k === 'maintout') { TvAsk.ask('Why is it going out?', '', { yes: 'Take it out' }).then(function (v) { if (v) go({ value: v }); }); return; }
    var svc = /^svc:/.test(r.id || '');   // an outside service: Pause, not Shut down (decisions/0173 §2)
    var ask = { stop: svc ? ['Pause ' + (r.name || 'it') + '? Nothing of ours calls it until it\'s activated again; its key stays.', 'Pause it', true] : ['Shut ' + (r.name || 'it') + ' down? Its tasks first, then the workflow. What\'s running finishes.', 'Shut it down', true], maintback: ['Put ' + (r.name || 'it') + ' back? Its check runs first; it starts again only if the check isn\'t red.', 'Put it back'] }[k];
    if (ask && !TvAsk.gate(btn, ask[0], { yes: ask[1], danger: !!ask[2] })) return;
    go();
  });

  // the views' side panels (03 §27: "each a side panel with Back, closing by ×, Esc and a click outside"; CLAUDE.md
  // "Everything that pops open closes"): one at a time; a card or a drill button opens one, Back opens its snapshot again
  var spBg = null, spFrom = null;
  function spClose() { var o = box.querySelector('.cb-sp:not([hidden])'); if (!o) return; o.hidden = true; if (spBg) spBg.hidden = true; document.documentElement.style.overflow = ''; if (spFrom && document.body.contains(spFrom)) { try { spFrom.focus(); } catch (x) {} } spFrom = null; }
  function spOpen(id, from, at) {
    var p = document.getElementById(id); if (!p || !box.contains(p)) return;
    box.querySelectorAll('.cb-sp:not([hidden])').forEach(function (x) { x.hidden = true; });
    if (!spBg) { spBg = document.createElement('div'); spBg.className = 'cb-spbg'; spBg.addEventListener('click', spClose); box.appendChild(spBg); }
    spBg.hidden = false; p.hidden = false; document.documentElement.style.overflow = 'hidden';   // one scrollbar: the panel's (03 §28.5)
    if (from && !from.closest('.cb-sp')) spFrom = from;
    var x = p.querySelector('[data-cb-spx]'); if (x) x.focus();
    var t = at && document.getElementById(at); if (t) setTimeout(function () { t.scrollIntoView({ block: 'start' }); }, 30); else p.scrollTop = 0;
    if (window.TvTime) TvTime.paint(p); if (window.TvCopy) TvCopy.scan(p);
    gdLoad(p);
  }
  // a gauge's detail (its items, criteria and setup words: ControlGauges::detail) loads when its panel first opens, so the
  // board's first draw never fetches it (the session, 2026-11-22); a failure says so with Try again
  function gdLoad(p) {
    p.querySelectorAll('[data-cb-gd]:not([data-loaded])').forEach(function (slot) {
      slot.setAttribute('data-loaded', '1'); slot.setAttribute('aria-busy', 'true');
      fetch(location.pathname + '?gauge=' + encodeURIComponent(slot.getAttribute('data-cb-gd')), { credentials: 'include' }).then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status)); }).then(function (html) {
        var d = new DOMParser().parseFromString(html, 'text/html').getElementById('cb-gd'); slot.removeAttribute('aria-busy');
        if (!d) throw new Error('no detail in the answer');
        slot.innerHTML = d.innerHTML; if (window.TvTime) TvTime.paint(slot);
      }).catch(function (err) { slot.removeAttribute('aria-busy'); slot.removeAttribute('data-loaded');
        slot.innerHTML = '<p data-tv-status="bad" data-tv-label="Couldn\'t load its detail:">' + String(err.message || err).replace(/[<&]/g, '') + ' <button type="button" class="ak-b cb-xs" data-cb-gdre>Try again</button></p>'; });
    });
  }
  box.addEventListener('click', function (e) { var b = e.target.closest('[data-cb-gdre]'); if (b) { e.preventDefault(); gdLoad(b.closest('.cb-sp')); } });
  box.addEventListener('click', function (e) {
    var x = e.target.closest('[data-cb-spx]'); if (x && box.contains(x)) { e.preventDefault(); spClose(); return; }
    var o = e.target.closest('[data-cb-sp]'); if (o && box.contains(o)) { e.preventDefault(); spOpen(o.getAttribute('data-cb-sp'), o, o.getAttribute('data-cb-spat')); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.querySelector('.cb-sp:not([hidden])')) { e.stopPropagation(); spClose(); } }, true);

  // Site health on the board (check-register.md, "Site health: one notification path", Jason 2026-11-22): its old Pulse
  // case's actions kept, through Pulse's own manage actions for sitehealth:open (Mark fixed, Set aside with why, Check
  // again, Run the checks now); the list is drawn again from the board after each
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cb-sh]'); if (!b || b.disabled || !box.contains(b)) return;
    e.preventDefault();
    var what = b.getAttribute('data-cb-sh'), args = b.hasAttribute('data-state') ? { state: b.getAttribute('data-state') } : {};
    var send = function () { TvAsk.busy(b, true);
      post('/admin/api/pulse', { do: 'manage', key: 'sitehealth:open', what: what, args: args }).then(function (j) { TvAsk.busy(b, false); say(j.message || 'Done.', b);
        setTimeout(function () { fetch(location.pathname + location.search, { credentials: 'include' }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (html) {
          var nu = html && new DOMParser().parseFromString(html, 'text/html').getElementById('site-health'), cur = document.getElementById('site-health');
          if (nu && cur) { cur.parentNode.replaceChild(document.importNode(nu, true), cur); if (window.TvTime) TvTime.paint(box); if (window.TvCopy) TvCopy.scan(box); } }).catch(function () {}); }, 900); },
        function (err) { TvAsk.busy(b, false); say(err.message, b, true); }); };
    if (b.hasAttribute('data-ask')) { TvAsk.ask(b.getAttribute('data-ask'), '', { yes: 'Set it aside' }).then(function (v) { if (v === null || v === undefined || v === false || !String(v).trim()) return; args.note = String(v); send(); }); return; }
    if (b.hasAttribute('data-confirm') && !TvAsk.gate(b, b.getAttribute('data-confirm'), { yes: b.getAttribute('data-yes') || 'Yes' })) return;
    send();
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cb]');
    if (!b || b.disabled || !box.contains(b)) return;
    if (b.closest('summary')) e.preventDefault();   // a button on a row's line works without opening or closing it
    // a button on a row's line answers under the whole row, full width: beside it, its narrow cell squeezed the words into a
    // column a letter wide (Jason, 2026-11-18, the Workers' Check)
    var at = b.closest('summary') ? b.closest('details') : b;
    if (b.hasAttribute('data-confirm') && !TvAsk.gate(b, b.getAttribute('data-confirm'), { yes: b.getAttribute('data-yes') || 'Yes', danger: b.hasAttribute('data-danger'), anchor: at })) return;
    var act = b.getAttribute('data-cb'), id = b.getAttribute('data-cb-id') || '';
    TvAsk.busy(b, true);
    var req = act === 'timings'
      ? ['ms:radius', 'ms:rollup', 'ms:graph', 'ms:index'].reduce(function (p, k) { return p.then(function (bad) { return post('/admin/api/config/control', { action: 'check', id: k }).then(function (j) { return bad || !!j.bad; }); }); }, Promise.resolve(false))
          .then(function (bad) { return { message: bad ? 'Re-tested: one or more is over its limit (noted; it holds nothing).' : 'Re-tested: all four are within their limits.', bad: bad }; })
      : act === 'preflight'
      ? post('/admin/api/config/preflight', { action: 'start', mode: 'new' }).then(function () { return { message: 'The pre-flight is running on the server; the board shows it as it goes.' }; })
      : b.hasAttribute('data-cb-form')   // a form's fields (a maintenance window: its times sent as moments, from this device's zone)
      ? (function () { var f = document.getElementById(b.getAttribute('data-cb-form')), body = { action: act, id: id };
          if (!f) return Promise.reject(new Error('The form isn\'t on the page; reload it.'));
          if (!f.reportValidity()) return Promise.resolve({ message: '', skip: true });
          Array.prototype.forEach.call(f.elements, function (el) { if (!el.name) return; body[el.name] = el.type === 'datetime-local' ? (el.value ? String(Math.floor(new Date(el.value).getTime() / 1000)) : '') : el.value; });
          return post('/admin/api/config/control', body).then(function (j) { f.reset(); return j; }); })()
      : b.hasAttribute('data-cb-ask')   // a step that needs one value first (Raise the cap): asked in the site's own box
      ? TvAsk.ask(b.getAttribute('data-cb-ask'), b.getAttribute('data-cb-val') || '', { yes: b.getAttribute('data-cb-yes') || 'Set it' }).then(function (v) { if (v === null || v === undefined || v === false) return { message: '', skip: true }; return post('/admin/api/config/control', { action: act, id: id, value: String(v) }); })
      : post('/admin/api/config/control', { action: act, id: id });
    req.then(function (j) { TvAsk.busy(b, false); if (j.skip) return;
      // a trip resolved leaves the queue at once, for the logbook (Jason, 2026-11-19: the red row stayed after "Resolved")
      var li = (act === 'resolve' || (act === 'svccheck' && /^(Closed|It answered)/.test(j.message || ''))) && !j.bad ? b.closest('li[id^="cb-alarm-"]') : null;   // and an issue its check closed
      if (li) { var host = li.parentNode && li.parentNode.closest ? (li.parentNode.closest('li[id^="cb-alarm-"]') || li.parentNode.closest('details') || li.parentNode) : null;
        li.remove(); say((j.message || 'Resolved.') + ' It\'s in the logbook.', host || box, false); redraw(); return; }
      // acknowledged: out of the open list at once, and where it went (Jason, 2026-11-19: "I acknowledged this but it is still listed")
      var ak = act === 'ack' && !j.bad && aShow === 'open' ? b.closest('li[id^="cb-alarm-"]') : null;
      // a trip acknowledged stays where it is, seen (D5; 02 row 96: "the light stays red"), and the board says so
      if (ak && ak.getAttribute('data-alevel') === 'trip') { ak.setAttribute('data-astate', 'ack'); say('Acknowledged: marked seen. It stays in the Open list while it holds; Check and start again is still on it.', ak, false); afilter(); setTimeout(redraw, 1200); return; }
      if (ak) { ak.setAttribute('data-astate', 'ack'); ak.hidden = true; say('Acknowledged: it\'s under Acknowledged (the filter), and comes back if it gets worse.', ak.parentNode.closest('details') || box, false); afilter(); setTimeout(redraw, 1200); return; }
      say(j.message || 'Done.', at, !!j.bad); setTimeout(redraw, 1200); },
      function (err) { TvAsk.busy(b, false); say(err.message, at, true); });
  });
})();
