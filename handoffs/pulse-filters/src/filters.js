  // One filter button (design/table-standard.md, Jason's note of 2026-11-20): the lists, Show, Senders, Levels, Group by
  // and saved views in one panel under its button; each tick applies at once; the active ones as tags with × beside it.
  // Counts are within what the other filters show; a sender or level with none is greyed at 0.
  var FDD = document.getElementById('pb-fdd'), TAGS = document.getElementById('pb-tags');
  var SEVK = Object.keys(SEVN), GBN = { '': 'None', from: 'Sender', src: 'Kind', sev: 'Level' };
  function chipName(c) { if (c === 'bell') return 'The bell'; var b = document.querySelector('[data-chip="' + c + '"]'); return b ? b.textContent.replace(/\s*\(\d+\)\s*$/, '') : c; }
  function listName(v) { if (v === '') return 'Inbox'; var o = { pinned: 'Pinned', flagged: 'Follow up', archived: 'Archived' }; return o[v] || (v.indexOf('folder:') === 0 ? v.slice(7) : v); }
  function ticks(f) {   // every sender or level in the stream, with how many the other filters leave
    var box = document.getElementById(f === 'from' ? 'pb-from' : 'pb-sev'); if (!box) return;
    var seen = {}, keys = [];
    rows.forEach(function (r) { if (r.dataset.parent) return; var v = r.dataset[f]; if (!(v in seen)) { seen[v] = 0; keys.push(v); } if (shows(r, f)) seen[v]++; });
    keys.sort(function (a, b) { return f === 'sev' ? SEVK.indexOf(a) - SEVK.indexOf(b) : a.localeCompare(b); });
    var all = !T[f].length;
    box.innerHTML = keys.map(function (v) {
      var on = all || T[f].indexOf(v) >= 0, n = seen[v];
      return '<label class="pb-tick' + (n ? '' : ' none') + '"><input type="checkbox" value="' + esc(v) + '"' + (on ? ' checked' : '') + '><span>' + esc(f === 'sev' ? (SEVN[v] || v) : v) + '</span><b>' + n + '</b></label>';
    }).join('') + (all ? '' : '<button type="button" class="pb-all" data-tick-all="' + f + '">Show all</button>');
    box.dataset.keys = JSON.stringify(keys);
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  ['pb-from', 'pb-sev'].forEach(function (id) { var box = document.getElementById(id); if (!box) return; var f = id === 'pb-from' ? 'from' : 'sev';
    box.addEventListener('change', function () {
      var keys = JSON.parse(box.dataset.keys || '[]'), on = [].slice.call(box.querySelectorAll('input:checked')).map(function (i) { return i.value; });
      T[f] = on.length >= keys.length ? [] : (on.length ? on : ['(none ticked)']);   // all ticked is everything; none ticked shows nothing, said so
      saveT(); apply();
    });
    box.addEventListener('click', function (e) { if (e.target.closest('[data-tick-all]')) { T[f] = []; saveT(); apply(); } });
  });
  function current() { return { list: M.view || '', show: chip || '', from: T.from.slice(), sev: T.sev.slice(), group: GB || '' }; }
  function same(a, b) { return JSON.stringify([a.list, a.show, a.from.slice().sort(), a.sev.slice().sort(), a.group]) === JSON.stringify([b.list || '', b.show || '', (b.from || []).slice().sort(), (b.sev || []).slice().sort(), b.group || '']); }
  function setChip(c) {
    chip = c || ''; document.querySelectorAll('[data-chip]').forEach(function (o) { o.setAttribute('aria-pressed', o.dataset.chip === chip ? 'true' : 'false'); });
    var bl = document.querySelector('[data-chip-bell]'); if (bl) bl.setAttribute('aria-pressed', chip === 'bell' ? 'true' : 'false');
  }
  function useView(f) {   // a saved view: its list, Show, senders, levels and Group by, all at once
    M.view = f.list || ''; try { localStorage.setItem('pb_view', M.view); } catch (x) {}
    setChip(f.show); T.from = (f.from || []).slice(); T.sev = (f.sev || []).slice(); saveT();
    GB = GBN.hasOwnProperty(f.group || '') ? (f.group || '') : ''; try { localStorage.setItem('pb_group', GB); } catch (x) {}
    paintMarks(); apply();
  }
  function paintFilters() { ticks('from'); ticks('sev'); paintTags(); }   // the filters' state: the panel's ticks and the tags (it painted the header funnels until 2026-11-20)
  function paintTags() {   // only the active filters, each with × to take it off
    if (!TAGS) return; var t = [];
    if (M.view) t.push(['list', 'List: ' + listName(M.view)]);
    if (chip) t.push(['show', chipName(chip)]);
    if (T.from.length) t.push(['from', T.from[0] === '(none ticked)' ? 'Senders: none' : 'Senders: ' + (T.from.length > 2 ? T.from.length : T.from.join(', '))]);
    if (T.sev.length) t.push(['sev', T.sev[0] === '(none ticked)' ? 'Levels: none' : 'Levels: ' + T.sev.map(function (v) { return SEVN[v] || v; }).join(', ')]);
    if (GB) t.push(['group', 'Group: ' + GBN[GB]]);
    TAGS.innerHTML = t.map(function (x) { return '<span class="pb-tag">' + esc(x[1]) + '<button type="button" data-untag="' + x[0] + '" aria-label="Take off ' + esc(x[1]) + '" title="Take it off">&times;</button></span>'; }).join('')
      + (t.length > 1 ? '<button type="button" class="pb-all" data-untag="all">Clear all</button>' : '');
  }
  if (TAGS) TAGS.addEventListener('click', function (e) {
    var b = e.target.closest('[data-untag]'); if (!b) return; var k = b.dataset.untag, f = current();
    if (k === 'all') f = { list: '', show: '', from: [], sev: [], group: '' }; else if (k === 'from' || k === 'sev') f[k] = []; else f[k] = '';
    useView(f);
  });
  FDD.addEventListener('click', function (e) {   // Group by, in the same panel; the panel stays open so filters can be combined
    var b = e.target.closest('[data-gb]'); if (!b) return; GB = b.dataset.gb; try { localStorage.setItem('pb_group', GB); } catch (x) {} apply();
  });
  // saved views, kept with the account (Jason, 2026-11-20: "With my account"; /user/api/pulse-views.php)
  var SV = { views: [], ready: true, csrf: '' };
  function vpost(body) {
    return fetch('/user/api/pulse-views.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SV.csrf || M.csrf || CSRF }, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })
      .then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) throw new Error((j.error && j.error.message) || 'That didn\'t save.'); if (j.views) SV.views = j.views; return j; });
  }
  function paintSaved() {
    var box = document.getElementById('pb-saved'), form = document.getElementById('pb-svf'); if (!box) return;
    if (!SV.ready) { box.innerHTML = '<p class="pb-svn">Saved views come with the next site update.</p>'; if (form) form.hidden = true; return; }
    var now = current();
    box.innerHTML = SV.views.length ? SV.views.map(function (v) { return '<span class="pb-sv"><button type="button" class="pb-chip" data-sv="' + v.id + '" aria-pressed="' + (same(now, v.filters) ? 'true' : 'false') + '">' + esc(v.name) + '</button><button type="button" class="pb-svx" data-sv-del="' + v.id + '" aria-label="Remove ' + esc(v.name) + '" title="Remove this view">&times;</button></span>'; }).join('')
      : '<p class="pb-svn">None yet. Choose filters, name them below, and they come back with one click on any device.</p>';
    if (form) form.hidden = false;
  }
  fetch('/user/api/pulse-views.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) return; SV.ready = j.ready !== false; SV.views = j.views || []; SV.csrf = j.csrf || ''; paintSaved(); }).catch(function () {});
  document.getElementById('pb-saved').addEventListener('click', function (e) {
    var u = e.target.closest('[data-sv-undo]'); if (u) { var g = JSON.parse(u.dataset.svUndo); if (window.TvAsk) TvAsk.busy(u, true); vpost({ save: g }).then(function (j) { paintSaved(); say('Put back “' + g.name + '”.', document.getElementById('pb-saved')); }).catch(function (x) { if (window.TvAsk) TvAsk.busy(u, false); say(x.message, u, true); }); return; }
    var d = e.target.closest('[data-sv-del]'); if (d) { if (window.TvAsk) TvAsk.busy(d, true);
      vpost({ delete: +d.dataset.svDel }).then(function (j) {   // gone, with Undo in its place
        paintSaved(); var box = document.getElementById('pb-saved'), s = document.createElement('span'); s.className = 'pb-sv pb-svgone';
        s.innerHTML = 'Removed “' + esc(j.view.name) + '”. <button type="button" data-sv-undo="' + esc(JSON.stringify({ name: j.view.name, filters: j.view.filters })) + '">Undo</button>'; box.insertBefore(s, box.firstChild);
      }).catch(function (x) { if (window.TvAsk) TvAsk.busy(d, false); say(x.message, d, true); }); return; }
    var b = e.target.closest('[data-sv]'); if (!b) return;
    var v = SV.views.filter(function (x) { return String(x.id) === b.dataset.sv; })[0]; if (v) useView(v.filters);
  });
  document.getElementById('pb-svf').addEventListener('submit', function (e) {
    e.preventDefault(); var inp = this.querySelector('input'), btn = this.querySelector('button'), name = inp.value.trim();
    if (!name) { say('Give the view a name, like “Needs me now”.', btn, true); return; }
    if (window.TvAsk) TvAsk.busy(btn, true);
    vpost({ save: { name: name, filters: current() } }).then(function (j) { if (window.TvAsk) TvAsk.busy(btn, false); inp.value = ''; paintSaved(); say(j.message, btn); })
      .catch(function (x) { if (window.TvAsk) TvAsk.busy(btn, false); say(x.message, btn, true); });
  });
  // groups (note 4): one of 3 rows or fewer opens by itself (Jason's answer); what you open or close is remembered here
  try { var go0 = JSON.parse(localStorage.getItem('pb_gopen') || '{}'); if (go0 && typeof go0 === 'object') GO = go0; } catch (x) {}
  try { var ao0 = JSON.parse(localStorage.getItem('pb_aopen') || '{}'); if (ao0 && typeof ao0 === 'object') Object.keys(ao0).forEach(function (k) { var r = row(k); if (r && r.dataset.group) { G[k] = !!ao0[k]; r.setAttribute('aria-expanded', String(G[k])); } }); } catch (x) {}
  function saveGroups() { try { localStorage.setItem('pb_gopen', JSON.stringify(GO)); var a = {}; Object.keys(G).forEach(function (k) { if (row(k)) a[k] = G[k]; }); localStorage.setItem('pb_aopen', JSON.stringify(a)); } catch (x) {} }
  var GBAR = document.getElementById('ps-gbar');
  if (GBAR) GBAR.addEventListener('click', function (e) {
    var b = e.target.closest('[data-gall]'); if (!b) return; var on = b.dataset.gall === '1';
    document.querySelectorAll('#ps-list .ps-ghead').forEach(function (h) { GO[GB + ':' + h.dataset.gkey] = on; });
    rows.forEach(function (r) { if (r.dataset.group && !r.hidden) { G[r.dataset.key] = on; r.setAttribute('aria-expanded', String(on)); var oi = r.querySelector('[data-ia="open"]'); if (oi) oi.setAttribute('aria-expanded', String(on)); } });
    saveGroups(); apply();
  });
  function paintGbar() { if (!GBAR) return; GBAR.hidden = !document.querySelector('#ps-list .ps-ghead:not([hidden]), #ps-list .ps-group:not([hidden])'); }
  var paintFddBase = paintFdd; paintFdd = function () {
    paintFddBase(); paintFilters(); paintSaved(); paintGbar();
    document.querySelectorAll('[data-gb]').forEach(function (b) { b.setAttribute('aria-checked', b.dataset.gb === GB ? 'true' : 'false'); });
    FDD.classList.toggle('on', !!(M.view || chip || T.from.length || T.sev.length || GB));
  };
