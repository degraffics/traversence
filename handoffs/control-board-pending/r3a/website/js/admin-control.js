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

  // the board drawn again in place
  function redraw() {
    if (going) return; going = true;
    var open = {}; box.querySelectorAll('details[id]').forEach(function (d) { open[d.id] = d.open; });
    fetch(location.pathname + location.search, { credentials: 'include' }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (html) {
      if (!html) return;
      var doc = new DOMParser().parseFromString(html, 'text/html'), nb = doc.getElementById('cb');
      if (!nb) return;
      if (box.querySelector('[aria-busy="true"]')) return;   // a press is still working: next time
      ['.cb-strip', '.cb-stages', '.cb-stage', '#cb-alarms', '.cb-panels', '#cb-p-site-needs'].forEach(function (sel) { var a = box.querySelector(sel), b = nb.querySelector(sel); if (!a || !b) return; var nu = document.importNode(b, true);
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
      filter();
      if (window.TvTime) TvTime.paint(box);
    }).catch(function () {}).then(function () { going = false; });
  }
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
  box.addEventListener('click', function (e) {
    var all = e.target.closest('[data-cb-all]');
    if (all) { var on = all.getAttribute('data-cb-all') === '1'; box.querySelectorAll('details.cb-sys, details.cb-p').forEach(function (d) { if (!d.hidden) d.open = on; }); return; }
    var go = e.target.closest('[data-cb-go]');
    if (go) { var t = document.getElementById(go.getAttribute('data-cb-go')); if (t) { e.preventDefault(); var s = t.closest('details.cb-sys'); if (s) s.open = true; t.open = true; t.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
  });
  // opened from a link to a part of the board (the top bar's light: #cb-alarms): that part opens, everything else stays closed
  function openHash() { var t = location.hash && document.getElementById(location.hash.slice(1)); if (!t || !box.contains(t)) return;
    for (var d = t; d && d !== box; d = d.parentElement) if (d.tagName === 'DETAILS') d.open = true;
    setTimeout(function () { t.scrollIntoView({ block: 'start' }); }, 50); }
  openHash(); window.addEventListener('hashchange', openHash);
  setInterval(function () { if (!document.hidden) redraw(); }, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) redraw(); });

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
      : post('/admin/api/config/control', { action: act, id: id });
    req.then(function (j) { TvAsk.busy(b, false); say(j.message || 'Done.', at, !!j.bad); setTimeout(redraw, 1200); },
      function (err) { TvAsk.busy(b, false); say(err.message, at, true); });
  });
})();
