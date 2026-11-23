// js/admin-kit.js — the admin's widgets in the browser (decisions/0109 A3, A3a): lists (search, filters like Shortcuts,
// the stage filter, a table on a wide screen and cards on a phone, paging), metrics that filter the list, the detail
// drawer (where the item is in its workflow, its moves greyed out with the reason when not yours to make), tools at
// hand opened in a drawer over the work, quick tasks, and the bridges' frames. Drawers go two deep; Back closes the top
// one; what you were doing (search, filters, page, the open item) is kept.
(function () {
  'use strict';
  var csrf = null;
  // the command header's slots (Jason's design directive, 2026-11-18: one header, no stacked rows): a page's own filter or
  // utility row marked data-ak-cmd moves up into the header, left beside the sections (data-ak-cmd) or right beside At hand
  // (data-ak-cmd="right"). Opt-in; inside a drawer (no header) it stays where it is.
  (function () {
    var l = document.getElementById('ak-cmdf'), r = document.getElementById('ak-cmdr');
    if (!l || !r) return;
    document.querySelectorAll('.ak-body [data-ak-cmd]').forEach(function (x) { if (x.getAttribute('data-ak-cmd') === 'right') r.insertBefore(x, r.querySelector(':scope > .ab')); else l.appendChild(x); });
  })();
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  // a field of words (a name, a description, a note): the browser's spell check and the site's word suggestions (js/tv-ask.js
  // TvSpell, from our own word list). Codes, amounts, dates and the structured boxes (prices, options) are left alone.
  var NOT_WORDS = /sku|code|email|phone|url|link|zip|token|key|price|qty|cost|amount|number|date|starts|ends|time|runs|markup|options|weight|dims|capacity|downloads|days|pct|fee|deposit|waiver|units|guests|nights|tax|interval|tracking/i;
  function words(name) { return NOT_WORDS.test(name) ? ' spellcheck="false"' : ' spellcheck="true" autocapitalize="sentences" data-spell'; }
  // a list whose choices each mean something (an option's third part): a small i beside it opens what each one is, and
  // picking one there chooses it (Jason, 2026-11-04: "so users can learn and pick the correct kind"; "If the icon is clicked
  // popup the info"; "the popup looks bad, need better CSS UI UX practices"). A dialog over a dimmed page: a bottom sheet
  // on a phone, centered on a wider screen; each choice a row with a radio, its name and what it is; one marked, none
  // highlighted by hover alone. A list of item kinds (data-fit) follows the name above it: kinds the name can't be are
  // greyed with why ("Tamales are food"), decisions/0144.
  function pick(name, label, opts, cur) {
    var help = opts.some(function (o) { return o[2]; });
    var sel = '<select class="ak-in" name="' + esc(name) + '"' + (help ? ' aria-label="' + esc(label) + '"' : '') + (name === 'kind' && help ? ' data-fit' : '') + '>' + opts.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (cur != null && String(cur) === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select>';
    if (!help) return '<label>' + esc(label) + sel + '</label>';
    return '<div class="ak-pick"><span class="ak-pl"><span>' + esc(label) + '</span><button type="button" class="ak-info" data-ak-info aria-haspopup="dialog" aria-expanded="false" aria-label="What each ' + esc(label.toLowerCase()) + ' means" title="What each one means">i</button></span>' + sel
      + '<button type="button" class="ak-fitnote" data-ak-info hidden></button><template>' + opts.map(function (o) { return '<button type="button" class="ak-opt" role="radio" aria-checked="false" data-ak-pick="' + esc(o[0]) + '"><span class="ak-radio" aria-hidden="true"></span><span class="ak-optt"><b>' + esc(o[1]) + '</b>' + (o[2] ? '<small>' + esc(o[2]) + '</small>' : '') + '<em class="ak-optwhy"></em></span></button>'; }).join('') + '</template></div>';
  }
  var pop = null;
  function unpop() { if (!pop) return; pop.__btn.setAttribute('aria-expanded', 'false'); pop.remove(); pop = null; document.documentElement.classList.remove('ak-popping'); }
  function openPop(i) {
    var w = i.closest('.ak-pick'), sel = w.querySelector('select'), label = w.querySelector('.ak-pl > span').textContent.trim();
    i = w.querySelector('.ak-info');   // the i or the line under the list: either opens it, the i shows it's open
    pop = document.createElement('div'); pop.className = 'ak-popwrap';
    pop.innerHTML = '<div class="ak-pop" role="dialog" aria-modal="true" aria-labelledby="ak-poph"><div class="ak-poph"><div><b id="ak-poph">' + esc(label) + '</b><small>Tap one to choose it.</small></div><button type="button" class="ak-popx" data-ak-unpop aria-label="Close">×</button></div>'
      + '<div class="ak-popl" role="radiogroup" aria-label="' + esc(label) + '">' + w.querySelector('template').innerHTML + '</div></div>';
    pop.__btn = i; pop.__sel = sel; document.body.appendChild(pop); i.setAttribute('aria-expanded', 'true'); document.documentElement.classList.add('ak-popping');
    var fit = sel.__fit, list = pop.querySelector('.ak-popl'), off = [];
    if (fit) pop.querySelector('.ak-poph small').textContent = said(fit) + ', so it can be ' + names(sel, fit.kinds) + '.';
    pop.querySelectorAll('[data-ak-pick]').forEach(function (b) {
      var k = b.getAttribute('data-ak-pick'), on = k === sel.value;
      b.setAttribute('aria-checked', on ? 'true' : 'false'); b.classList.toggle('on', on);
      if (fit && fit.kinds.indexOf(k) < 0) { b.disabled = true; b.classList.add('off'); off.push(b); }
    });
    if (off.length) {   // the kinds its name can't be: after the ones it can, names only
      var h = document.createElement('p'); h.className = 'ak-offh'; h.textContent = 'Not for ' + fit.word; list.appendChild(h);
      off.forEach(function (b) { list.appendChild(b); });
    }
    var on = pop.querySelector('.on') || pop.querySelector('[data-ak-pick]:not([disabled])'); if (on) on.focus();
  }
  document.addEventListener('click', function (e) {
    var i = e.target.closest('[data-ak-info]');
    if (i) { var mine = pop && pop.__btn === i; unpop(); if (!mine) openPop(i); return; }
    if (!pop) return;
    var pk = e.target.closest('[data-ak-pick]');
    if (pk && pop.contains(pk) && !pk.disabled) { var s = pop.__sel; s.value = pk.getAttribute('data-ak-pick'); s.dispatchEvent(new Event('change', { bubbles: true })); unpop(); s.focus(); return; }
    if (e.target.closest('[data-ak-unpop]') || !e.target.closest('.ak-pop')) unpop();
  });
  document.addEventListener('keydown', function (e) {   // the pop-up's own keys: Esc closes it (not the box under it); arrows move between choices
    if (!pop) return;
    if (e.key === 'Escape') { e.stopPropagation(); var b = pop.__btn; unpop(); b.focus(); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      var all = Array.prototype.slice.call(pop.querySelectorAll('[data-ak-pick]:not([disabled])')), at = all.indexOf(document.activeElement);
      if (all.length) { e.preventDefault(); all[(at + (e.key === 'ArrowDown' ? 1 : all.length - 1)) % all.length].focus(); }
    }
  }, true);
  // the kinds that fit a name: asked once typing pauses (the same word check as spelling, /api/spell.php?k=1)
  var fitTimer;
  document.addEventListener('input', function (e) {
    var nm = e.target; if (nm.name !== 'name' || !nm.form) return;
    var sel = nm.form.querySelector('select[data-fit]'); if (!sel) return;
    clearTimeout(fitTimer);
    fitTimer = setTimeout(function () {
      var t = nm.value.trim(); if (t.length < 3) { applyFit(sel, null); return; }
      fetch('/api/spell.php?k=1&t=' + encodeURIComponent(t.slice(0, 160)), { credentials: 'include' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d && nm.value.trim() === t) applyFit(sel, d.fit || null); }).catch(function () {});
    }, 600);
  });
  function fitNow(root) { var sel = root.querySelector('select[data-fit]'), nm = sel && sel.form && sel.form.elements.name; if (nm && nm.value) nm.dispatchEvent(new Event('input', { bubbles: true })); }   // a name already there
  function applyFit(sel, fit) {
    sel.__fit = fit;
    Array.prototype.forEach.call(sel.options, function (o) { o.disabled = !!(fit && fit.kinds.indexOf(o.value) < 0); });
    var note = sel.parentNode.querySelector('.ak-fitnote');
    if (fit && sel.options[sel.selectedIndex] && sel.options[sel.selectedIndex].disabled) { sel.value = fit.kinds[0]; sel.dispatchEvent(new Event('change', { bubbles: true })); }
    if (note) { note.hidden = !fit; if (fit) note.innerHTML = esc(said(fit)) + ' · ' + fit.kinds.length + ' of ' + sel.options.length + ' kinds fit · <u>See which</u>'; }   // short; the pop-up says the rest
  }
  function said(fit) { return fit.word.charAt(0).toUpperCase() + fit.word.slice(1) + (/s$/.test(fit.word) && !/ss$/.test(fit.word) ? ' are ' : ' is ') + fit.what; }
  function names(sel, keys) { var n = keys.map(function (k) { var o = sel.querySelector('option[value="' + k + '"]'); return o ? o.textContent : k; }); return n.length > 1 ? n.slice(0, -1).join(', ') + ' or ' + n[n.length - 1] : n[0] || ''; }
  function tok() { return csrf ? Promise.resolve(csrf) : fetch('/api/auth/session.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) { return (csrf = d.csrf_token || ''); }); }
  function done(r) { return r.json().catch(function () { return { ok: false, error: { message: 'Something went wrong. Try again.' } }; }).then(function (j) { if (!j.ok) throw new Error((j.error && j.error.message) || 'Something went wrong.'); return j; }); }
  function get(tool, q) {
    var u = (window.AK_API || '/admin/api/') + tool + '?' + Object.keys(q).filter(function (k) { return q[k] !== '' && q[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(q[k]); }).join('&');
    return fetch(u, { credentials: 'include' }).then(done);
  }
  function post(tool, body) {
    return tok().then(function (c) { return fetch((window.AK_API || '/admin/api/') + tool, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': c }, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) }); }).then(done);
  }
  function say(m) { if (window.TvAsk) TvAsk.say(m); }
  function store(k, v) { try { if (v === undefined) return JSON.parse(sessionStorage.getItem(k) || 'null'); sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  function fmt(v, kind) {
    if (v == null || v === '') return '';
    if (kind === 'money') return '$' + (Number(v) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (kind === 'date') { var d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? String(v) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
    if (kind === 'count') return Number(v).toLocaleString();
    return String(v);
  }
  // any endpoint that answers the list's contract ({rows, total, counts?, sortable?}): tv_table(['src' => …])
  function getSrc(src, q) {
    var u = src + (src.indexOf('?') < 0 ? '?' : '&') + Object.keys(q).filter(function (k) { return q[k] !== '' && q[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(q[k]); }).join('&');
    return fetch(u, { credentials: 'include', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok === false) throw new Error((j.error && j.error.message) || j.error || 'Not available right now.'); return j; });
  }
  window.TvAdmin = { get: get, post: post, esc: esc };

  // ---- dropdown menus (the section menu, and every filter) --------------------------------------------------------
  document.addEventListener('click', function (e) {
    document.querySelectorAll('.ak-dd[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    document.querySelectorAll('.ak-fw .ak-fl').forEach(function (l) { if (!l.parentNode.contains(e.target)) { l.hidden = true; l.previousSibling.setAttribute('aria-expanded', 'false'); } });
  });

  // ---- lists --------------------------------------------------------------------------------------------------------
  var lists = [];
  function List(el) {
    var C = JSON.parse(el.getAttribute('data-ak-table')), self = this, skey = 'ak:' + location.pathname + ':' + C.tool;
    // filters are kept for a reload or Back; arriving by a link or the menu starts the list fresh, so an old filter never hides everything
    var nav = (function () { try { var n = performance.getEntriesByType('navigation')[0]; return n ? n.type : ''; } catch (e) { return ''; } })();
    var S = (nav === 'reload' || nav === 'back_forward' ? store(skey) : null) || { q: '', stage: '', f: {}, page: 0 };
    // the lists' standard (Jason, 2026-11-12, as the vault's): sortable columns, Columns to show or hide them, so many a page
    // (kept in this browser), Show more under the list, and a bar with the pages, the range and how many a page
    var lkey = 'ak-l:' + C.tool, L = (function () { try { return JSON.parse(localStorage.getItem(lkey) || 'null') || {}; } catch (e) { return {}; } })();
    S.per = [10, 25, 50, 100].indexOf(+L.per) >= 0 ? +L.per : 10;   // 10 a page unless you chose (Jason, 2026-11-12) S.sort = S.sort || L.sort || ''; S.dir = S.dir || L.dir || 'asc'; S.extra = 1;
    var hidden = L.hidden || [], sortable = [];
    function keepL() { try { localStorage.setItem(lkey, JSON.stringify({ per: S.per, sort: S.sort, dir: S.dir, hidden: hidden })); } catch (e) {} }
    (C.filters || []).forEach(function (f) { if (!(f[0] in S.f)) S.f[f[0]] = f[2][0][0]; });
    var labels = {}; (C.stages || []).forEach(function (s) { labels[s.key] = s; });
    this.C = C; this.el = el;
    el.innerHTML = '<div class="ak-lh"><h2 class="ak-h">' + esc(C.title) + '</h2></div>'
      + (C.create && !C.create.allowed[0] ? '<p class="ak-note" style="text-align:right;margin-top:-.2rem">' + esc(C.create.allowed[1]) + ' to ' + esc(C.create.label.toLowerCase()) + '.</p>' : '')
      + '<div class="ak-bar">' + (C.create ? '<button type="button" class="ak-plus" data-ak-new aria-label="' + esc(C.create.label) + '" title="' + esc(C.create.allowed[0] ? C.create.label : C.create.allowed[1]) + '"' + (C.create.allowed[0] ? '' : ' disabled') + '><svg class="ak-svg" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>' : '')
      + '<input class="ak-q" type="search" placeholder="Search ' + esc(C.title.toLowerCase()) + '" value="' + esc(S.q) + '" aria-label="Search"><span data-ak-filters></span></div>'
      + '<div data-ak-rows><div class="akt-skel" role="status" aria-label="Loading"><i></i><i></i><i></i></div></div><button type="button" class="ak-b ak-more" data-ak-more hidden>Show more</button><div class="ak-pg" data-ak-pg></div><p class="akt-tnote" data-ak-note hidden></p>';
    var rowsEl = el.querySelector('[data-ak-rows]'), pg = el.querySelector('[data-ak-pg]'), fw = el.querySelector('[data-ak-filters]'), counts = {}, total = 0, capped = false, rows = [];

    function menu(key, icon, label, opts) {   // a dropdown like Shortcuts: icon, the current choice, its count, ▾
      var cur = key === 'stage' ? S.stage : S.f[key], o = opts.filter(function (x) { return x[0] === cur; })[0] || opts[0];
      return '<span class="ak-fw"><button type="button" class="ak-fb" aria-expanded="false" data-fk="' + esc(key) + '">' + icon + esc(o[1]) + (o[2] != null ? ' <b>' + o[2] + '</b>' : '') + '</button><span class="ak-fl" hidden>'
        + opts.map(function (x) { return '<button type="button" data-fv="' + esc(x[0]) + '"' + (x[0] === cur ? ' class="on"' : '') + '>' + esc(x[1]) + (x[2] != null ? '<i>' + x[2] + '</i>' : '') + '</button>'; }).join('') + '</span></span>';
    }
    var ICON = '<svg class="ak-svg" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>';
    function drawFilters() {
      var h = '';
      if (C.pipeline) {
        var all = 0; Object.keys(counts).forEach(function (k) { all += counts[k]; });
        var multi = [];   // a metric that stands for several stages ("To make": paid, in production, ordered) is a choice of its own
        if (S.stage.indexOf(',') > 0) { var mb = document.querySelector('[data-ak-stage="' + C.tool + '|' + S.stage + '"] span'), n = 0; S.stage.split(',').forEach(function (k) { n += counts[k] || 0; }); multi = [[S.stage, mb ? mb.textContent : 'Several stages', n]]; }
        h += menu('stage', ICON, 'Stage', [['', 'Every stage', all || null]].concat(multi, C.stages.map(function (s) { return [s.key, s.label, counts[s.key] || 0]; })));
      }
      (C.filters || []).forEach(function (f) {   // a filter the tool counts (count_by) shows each option's count, like the Stage menu
        var n = C.countBy === f[0], sum = 0; if (n) Object.keys(counts).forEach(function (k) { if (k !== '') sum += counts[k]; }); if (n && counts[''] != null) sum = counts[''];   // the tool can say what "all" counts
        h += menu(f[0], ICON, f[1], f[2].map(function (o) { return [o[0], o[1], n ? (o[0] === '' ? sum : (counts[o[0]] || 0)) : null]; }));
      });
      fw.innerHTML = h;
    }
    fw.addEventListener('click', function (e) {
      var b = e.target.closest('.ak-fb');
      if (b) { var l = b.nextSibling, open = l.hidden; fw.querySelectorAll('.ak-fl').forEach(function (x) { x.hidden = true; x.previousSibling.setAttribute('aria-expanded', 'false'); }); l.hidden = !open; b.setAttribute('aria-expanded', open ? 'true' : 'false'); return; }
      var v = e.target.closest('[data-fv]'); if (!v) return;
      var k = v.closest('.ak-fw').querySelector('.ak-fb').getAttribute('data-fk'), val = v.getAttribute('data-fv');
      if (k === 'stage') S.stage = val; else S.f[k] = val;
      S.page = 0; S.extra = 1; self.load();
    });
    rowsEl.addEventListener('click', function (e) {   // Clear filters: back to everything
      if (!e.target.closest('[data-ak-clear]')) return;
      S.q = ''; S.stage = ''; S.page = 0; (C.filters || []).forEach(function (f) { S.f[f[0]] = f[2][0][0]; }); el.querySelector('.ak-q').value = ''; self.load();
    });
    var t = null;
    el.querySelector('.ak-q').addEventListener('input', function (e) { clearTimeout(t); t = setTimeout(function () { S.q = e.target.value.trim(); S.page = 0; S.extra = 1; self.load(); }, 300); });
    pg.addEventListener('click', function (e) { var b = e.target.closest('[data-pg]'); if (!b || b.disabled) return; S.page = +b.getAttribute('data-pg'); S.extra = 1; self.load(); if (el.getBoundingClientRect().top < 0) el.scrollIntoView({ block: 'start', behavior: 'smooth' }); });
    pg.addEventListener('change', function (e) { if (!e.target.matches('[data-ak-per]')) return; var first = S.page * S.per; S.per = +e.target.value; S.page = Math.floor(first / S.per); S.extra = 1; keepL(); self.load(); });
    el.querySelector('[data-ak-more]').addEventListener('click', function (e) { if (window.TvAsk && TvAsk.busy) TvAsk.busy(e.target, true); self.load(true).then(function () { if (window.TvAsk && TvAsk.busy) TvAsk.busy(e.target, false); }); });
    rowsEl.addEventListener('click', function (e) {
      var sb = e.target.closest('[data-ak-sort]'); if (sb) { var k = sb.getAttribute('data-ak-sort'); if (S.sort === k) S.dir = S.dir === 'asc' ? 'desc' : 'asc'; else { S.sort = k; S.dir = 'asc'; } S.page = 0; S.extra = 1; keepL(); self.load(); return; }
    });
    rowsEl.addEventListener('change', function (e) {   // Columns: which to show
      var c = e.target.closest('[data-ak-col]'); if (!c) return; var k = c.getAttribute('data-ak-col');
      hidden = hidden.filter(function (x) { return x !== k; }); if (!c.checked) hidden.push(k); keepL(); draw(true);
    });
    document.addEventListener('click', function (e) { var d = el.querySelector('.ak-cols[open]'); if (d && !d.contains(e.target)) d.open = false; });

    function cell(r, c) {
      var v = r[c[0]];
      if (c[2] === 'name') {
        if (C.open === 'lead') return '<button type="button" class="ak-nm" data-lead="' + r.id + '">' + esc(v) + '</button>';
        if (C.open !== 'detail') { var href = r.href || C.open.replace('{id}', r.id).replace('{ref}', encodeURIComponent(r.ref || ''));   // an item of this section (/admin/backoffice/orders/9) opens as its page here; anything else in a drawer
          return '<a class="ak-nm" href="' + esc(href) + '"' + (href.indexOf(location.pathname.replace(/\/$/, '') + '/') === 0 ? '' : ' data-ak-page') + '>' + esc(v) + '</a>'; }
        return '<button type="button" class="ak-nm" data-ak-item="' + r.id + '">' + esc(v) + '</button>';
      }
      if (c[2] === 'stage') { var s = labels[v]; return v ? '<span class="ak-pill' + (s && s.end ? ' end' : '') + '">' + esc(s ? s.label : v) + '</span>' : ''; }
      if (c[2] === 'status') return statusHtml(v);
      if (c[2] === 'when') return whenHtml(v, r[c[0] + '_tz']);
      if (c[2] === 'pill') return v ? '<span class="ak-pill' + (/paid|active|ok/.test(v) ? ' ok' : /disput|declin|past|fail/.test(v) ? ' red' : '') + '">' + esc(String(v).replace(/_/g, ' ')) + '</span>' : '';
      return esc(fmt(v, c[2]));
    }
    // ---- the table standard (design/table-standard.md, T1): status, times, a sub-line, one action and ⋯, group rows
    var TONE = { ok: 'ok', good: 'ok', working: 'ok', warn: 'warn', held: 'warn', amber: 'warn', bad: 'bad', fail: 'bad', red: 'bad', info: 'info' };
    function statusHtml(v) {   // {tone, word} or a word; colour with its word, never colour alone
      if (v == null || v === '') return '';
      var o = typeof v === 'object' ? v : { word: String(v), tone: String(v).toLowerCase() };
      return '<span class="akt-st ' + (TONE[o.tone] || 'info') + '"><i aria-hidden="true"></i>' + esc(o.word || '') + '</span>';
    }
    function whenHtml(v, tz) {   // where it happened, labeled (CLAUDE.md "Times where they happened"); js/tv-time.js formats it
      if (!v) return '';
      var iso = String(v).replace(' ', 'T'), d = new Date(iso), txt = isNaN(d) ? String(v) : (window.TvTime && TvTime.fmt ? TvTime.fmt(iso, tz) : d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' }));
      return '<time datetime="' + esc(iso) + '" data-tv-time' + (tz ? ' data-tz="' + esc(tz) + '"' : '') + '>' + esc(txt) + '</time>';
    }
    function menuHtml(r) {   // the row's one visible action, then ⋯ with words; a greyed item says why
      var h = '';
      if (C.act) { var off = r._off && r._off[C.act[0]]; h += '<button type="button" class="ak-b akt-ract" data-ak-act="' + esc(C.act[0]) + '"' + (off ? ' disabled title="' + esc(off) + '"' : '') + '>' + esc(C.act[1]) + '</button>'; }
      if (C.menu && C.menu.length) h += '<span class="akt-rm"><button type="button" class="akt-rmb" data-ak-rm aria-haspopup="menu" aria-expanded="false" aria-label="More for ' + esc(r[C.columns[0][0]]) + '" title="More">⋯</button><span class="akt-rml" role="menu" hidden>'
        + C.menu.map(function (m) { var off = r._off && r._off[m[0]]; return '<button type="button" role="menuitem" data-ak-act="' + esc(m[0]) + '"' + (off ? ' disabled' : '') + '>' + (m[2] ? '<span class="akt-rmi" aria-hidden="true">' + m[2] + '</span>' : '') + '<span>' + esc(m[1]) + (off ? '<small>' + esc(off) + '</small>' : '') + '</span></button>'; }).join('') + '</span></span>';
      return h;
    }
    function rowHtml(r) {
      var cols = C.columns.filter(function (c, i) { return !i || hidden.indexOf(c[0]) < 0; }), acts = C.act || (C.menu && C.menu.length);
      function gSorted(g) {   // sorted by a column inside the group: said on the closed row (Jason, 2026-11-19)
        var c = S.sort && cols.filter(function (x) { return x[0] === S.sort; })[0];
        if (!c || c[2] === 'name' || c[2] === 'status' || (g.rows || []).length < 2) return '';
        return '<small class="akt-gsort">sorted by ' + esc(c[1]) + (S.dir === 'desc' ? ' ↓' : ' ↑') + '</small>';
      }
      if (r._group) {   // a group (alarms, a system feed): one row that opens to its members (0172, 0171 §8's note)
        var g = r._group, kids = (g.rows || []).map(function (k) { k._in = r.id; return rowHtml(k); }).join('');
        return '<tr class="akt-grp" data-grp="' + esc(r.id) + '" aria-expanded="false"><td class="ak-tn" colspan="' + (cols.length + (acts ? 1 : 0)) + '"><button type="button" class="akt-gt" data-ak-grp aria-expanded="false">'
          + '<span class="akt-gc" aria-hidden="true">▸</span>' + statusHtml(g.status) + '<b>' + esc(g.label) + '</b><span class="akt-gn">' + (g.n != null ? g.n : (g.rows || []).length) + '</span>' + (g.sub ? '<small>' + esc(g.sub) + '</small>' : '') + gSorted(g) + '</button></td></tr>' + kids;
      }
      return '<tr data-id="' + esc(r.id) + '"' + (r._in ? ' data-in="' + esc(r._in) + '" hidden' : '') + '>' + cols.map(function (c, i) { var v = cell(r, c); if (!i && C.sub && r[C.sub]) v += '<small class="akt-sub">' + esc(r[C.sub]) + '</small>';
        return '<td data-col="' + esc(c[0]) + '"' + (i ? ' data-l="' + esc(c[1]) + '"' + (c[2] === 'count' || c[2] === 'money' || c[2] === 'when' || c[2] === 'date' ? ' class="akt-num"' : '') : ' class="ak-tn"') + '>' + v + '</td>'; }).join('')
        + (acts ? '<td class="akt-acts">' + menuHtml(r) + '</td>' : '') + '</tr>';
    }
    rowsEl.addEventListener('click', function (e) {
      var g = e.target.closest('[data-ak-grp]');
      if (g) { var tr = g.closest('tr'), open = tr.getAttribute('aria-expanded') !== 'true'; tr.setAttribute('aria-expanded', open ? 'true' : 'false'); g.setAttribute('aria-expanded', open ? 'true' : 'false');
        rowsEl.querySelectorAll('tr[data-in="' + tr.getAttribute('data-grp') + '"]').forEach(function (k) { k.hidden = !open; }); return; }
      var m = e.target.closest('[data-ak-rm]');
      if (m) { var l = m.nextSibling, was = !l.hidden; rowsEl.querySelectorAll('.akt-rml').forEach(function (x) { x.hidden = true; x.previousSibling.setAttribute('aria-expanded', 'false'); }); l.hidden = was; m.setAttribute('aria-expanded', was ? 'false' : 'true');
        if (!was) { l.classList.remove('up'); var lb = l.getBoundingClientRect(); if (lb.bottom > window.innerHeight - 8 && m.getBoundingClientRect().top - lb.height > 8) l.classList.add('up'); }   // room below, or open upward
        return; }
      var a = e.target.closest('[data-ak-act]');
      if (a && !a.disabled) {   // the page acts on it (tv-table:act), else the tool's own action
        var id = a.closest('tr').getAttribute('data-id'), key = a.getAttribute('data-ak-act'), row = null;
        (function find(list) { list.forEach(function (x) { if (String(x.id) === id) row = x; if (x._group) find(x._group.rows || []); }); })(rows);
        var ml = a.closest('.akt-rml'); if (ml) { ml.hidden = true; ml.previousSibling.setAttribute('aria-expanded', 'false'); }
        var ev = new CustomEvent('tv-table:act', { bubbles: true, cancelable: true, detail: { key: key, id: id, row: row, button: a, list: self } });
        if (el.dispatchEvent(ev) && C.tool) {
          if (window.TvAsk && TvAsk.busy) TvAsk.busy(a, true);
          post(C.tool, { action: key, id: id }).then(function (j) { if (window.TvAsk) { TvAsk.busy(a, false); TvAsk.say(j.message || 'Done.', { anchor: a }); } self.load(); })
            .catch(function (x) { if (window.TvAsk) { TvAsk.busy(a, false); TvAsk.say(x.message, { anchor: a, bad: true }); } });
        }
      }
    });
    document.addEventListener('click', function (e) { if (!el.contains(e.target) || !e.target.closest('.akt-rm')) el.querySelectorAll('.akt-rml').forEach(function (x) { if (!x.hidden) { x.hidden = true; x.previousSibling.setAttribute('aria-expanded', 'false'); } }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') el.querySelectorAll('.akt-rml').forEach(function (x) { x.hidden = true; }); });
    function draw() {
      if (!rows.length) { var filtered = S.q || S.stage || (C.filters || []).some(function (f) { return S.f[f[0]] !== f[2][0][0]; });
        rowsEl.innerHTML = '<div class="ak-empty tv-bleed">' + (filtered ? 'Nothing matches these filters. <button type="button" class="ak-b" data-ak-clear>Clear filters</button>' : 'Nothing here yet.') + '</div>'; }
      else {
        var cols = C.columns.filter(function (c, i) { return !i || hidden.indexOf(c[0]) < 0; }), open = !!rowsEl.querySelector('.ak-cols[open]');
        var th = function (c, i) {
          var on = S.sort === c[0], can = sortable.indexOf(c[0]) >= 0;
          var t = can ? '<button type="button" class="ak-sort" data-ak-sort="' + esc(c[0]) + '"' + (on ? ' aria-sort="' + (S.dir === 'asc' ? 'ascending' : 'descending') + '"' : '') + ' title="Sort by ' + esc(String(c[1]).toLowerCase()) + '">' + esc(c[1]) + ' <i aria-hidden="true">' + (on ? (S.dir === 'asc' ? '↑' : '↓') : '↕') + '</i></button>' : esc(c[1]);
          if (i === cols.length - 1 && C.columns.length > 2) t = '<span class="ak-thl">' + t + '<details class="ak-cols"' + (open ? ' open' : '') + '><summary title="Choose the columns">⚙ Columns</summary><div class="ak-colsl">'
            + C.columns.slice(1).map(function (x) { return '<label><input type="checkbox" data-ak-col="' + esc(x[0]) + '"' + (hidden.indexOf(x[0]) < 0 ? ' checked' : '') + '> ' + esc(x[1]) + '</label>'; }).join('') + '</div></details></span>';
          return '<th' + (/^(count|money|when|date)$/.test(c[2]) ? ' class="akt-num"' : '') + '>' + t + '</th>';
        };
        rowsEl.innerHTML = '<table class="ak-t"><thead><tr>' + cols.map(th).join('') + (C.act || (C.menu && C.menu.length) ? '<th class="akt-acts"><span class="akt-sr">Actions</span></th>' : '') + '</tr></thead><tbody>'
          + rows.map(rowHtml).join('') + '</tbody></table>';
        // a column with no data yet on any row says so once, under the table, never hidden (principle 4)
        var none = cols.filter(function (c, i) { return i && c[2] !== 'actions' && rows.every(function empty(r) { return r._group ? (r._group.rows || []).every(empty) : (r[c[0]] == null || r[c[0]] === ''); }); });   // a group's members count (a table of only groups had every column marked empty)
        none.forEach(function (c) { rowsEl.querySelectorAll('[data-col="' + c[0] + '"]').forEach(function (x) { x.classList.add('akt-nod'); }); });
        var note = el.querySelector('[data-ak-note]'); if (note) { note.hidden = !none.length; note.textContent = none.map(function (c) { return c[1]; }).join(', ') + ': ' + (C.noData || 'no history yet') + '.'; }
      }
      var per = S.per, from = S.page * per, to = Math.min(total, from + rows.length), pages = Math.max(1, Math.ceil(total / per)), a = S.page, z = S.page + S.extra - 1;
      var more = el.querySelector('[data-ak-more]'), left = total - to; more.hidden = left <= 0; more.textContent = 'Show ' + Math.min(per, left) + ' more';
      var pb = function (p, label, cur, aria, off) { return '<button type="button" class="ak-pgb" data-pg="' + p + '"' + (cur ? ' aria-current="page"' : '') + (off ? ' disabled' : '') + ' aria-label="' + esc(aria) + '">' + label + '</button>'; };
      var nav = '';
      if (pages > 1) {
        var want = {}; [0, pages - 1, a - 1, z + 1].forEach(function (x) { if (x >= 0 && x < pages) want[x] = 1; }); for (var x = a; x <= z; x++) want[x] = 1;
        var last = -1; nav = pb(a - 1, '‹', false, 'Previous page', a <= 0);
        Object.keys(want).map(Number).sort(function (m, n) { return m - n; }).forEach(function (x) { if (last >= 0 && x > last + 1) nav += '<span class="ak-gap">…</span>'; nav += pb(x, (x + 1).toLocaleString(), x >= a && x <= z, 'Page ' + (x + 1)); last = x; });
        nav += pb(z + 1, '›', false, 'Next page', z >= pages - 1);
      }
      pg.innerHTML = total > 10 ? '<nav class="ak-pages" aria-label="Pages">' + nav + '</nav><span class="ak-range">' + (from + 1).toLocaleString() + '–' + to.toLocaleString() + ' of ' + total.toLocaleString() + (capped ? '+</span><span class="ak-note">The first ' + total.toLocaleString() + ' are here: search or filter to reach the rest.</span>' : '</span>')
        + '<label class="ak-per">Show <select data-ak-per aria-label="How many a page">' + [10, 25, 50, 100].map(function (n) { return '<option' + (n === per ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select> per page</label>' : '';
      return;
      pg.innerHTML = total > per ? '<span>' + (from + 1) + '–' + Math.min(total, from + rows.length) + ' of ' + total.toLocaleString() + '</span><span>' + (S.page ? '<button type="button" class="ak-b" data-pg="-1">‹ Back</button> ' : '') + (from + rows.length < total ? '<button type="button" class="ak-b" data-pg="1">More ›</button>' : '') + '</span>' : (rows.length ? '<span>' + total.toLocaleString() + ' in all</span>' : '');
    }
    rowsEl.addEventListener('click', function (e) {
      if (e.target.closest('a,button')) return;
      var tr = e.target.closest('tr[data-id]'); if (!tr || e.target.closest('.akt-acts')) return;
      var n = tr.querySelector('.ak-nm'); if (n) n.click();
    });
    this.setStage = function (s) { S.stage = S.stage === s ? '' : s; S.page = 0; S.extra = 1; self.load(); };
    this.load = function (more) {   // more: the next page under the ones shown (Show more)
      if (!more) S.extra = 1;
      store(skey, S);
      var q = { action: 'list', q: S.q, stage: S.stage, page: more ? S.page + S.extra : S.page, per: S.per, sort: S.sort, dir: S.dir };
      Object.keys(S.f).forEach(function (k) { q[k] = S.f[k]; });
      return (C.src ? getSrc(C.src, q) : get(C.tool, q)).then(function (j) {
        if (more) { rows = rows.concat(j.rows); S.extra++; } else rows = j.rows;
        total = j.total; capped = !!j.capped; counts = j.counts || {}; sortable = j.sortable || []; if (j.per) S.per = j.per; drawFilters(); draw(); markMetrics(C.tool, S.stage); })
        .catch(function (x) { rowsEl.innerHTML = '<div class="ak-empty">' + esc(x.message) + '</div>'; });
    };
    el.addEventListener('click', function (e) {
      if (e.target.closest('[data-ak-new]')) newItem(self);
    });
    this.load();
  }
  function markMetrics(tool, stage) { document.querySelectorAll('[data-ak-stage]').forEach(function (b) { var p = b.getAttribute('data-ak-stage').split('|'); if (p[0] === tool) b.classList.toggle('on', p[1] === stage); }); }
  function listFor(tool) { return lists.filter(function (l) { return l.C.tool === tool && l.C.pipeline; })[0] || lists.filter(function (l) { return l.C.tool === tool; })[0]; }
  function reloadAll() { lists.forEach(function (l) { l.load(); }); }
  // the lead panel (app/ui/lead-panel.php) tells the lists when a lead changes
  window.slRefreshRow = function () { reloadAll(); };

  document.addEventListener('click', function (e) {
    var m = e.target.closest('[data-ak-stage]'); if (!m) return;
    var p = m.getAttribute('data-ak-stage').split('|'), l = listFor(p[0]); if (!l) return;
    l.setStage(p[1]); l.el.scrollIntoView({ block: 'start', behavior: 'smooth' });
  });

  // ---- drawers --------------------------------------------------------------------------------------------------------
  var stack = [], dim = null;
  // One panel at a time (decisions/0175 §6; Jason, 2026-11-23: an alarm's drawer opened its process's page as a second
  // drawer, and its register entry inside that as a third panel: two headers, two ×, two "Open the full page"): what opens
  // from an open drawer replaces what it shows, with Back to where it was, as js/tv-ask.js's panel does. Back (and the
  // browser's back) steps back; ×, Esc and a click outside close it, Back and all.
  function head(d, title, full) {
    d.__title = title; d.__full = full || ''; d.setAttribute('aria-label', title);
    d.querySelector('.ak-drb b').textContent = title;
    var f = d.querySelector('.ak-full'); f.hidden = !full; if (full) f.setAttribute('href', full);
    d.querySelector('.ak-back').hidden = !d.__back.length;
  }
  function layer(title, wide, full) {
    var top = stack[stack.length - 1];
    if (top) {
      top.__back.push({ title: top.__title, full: top.__full, wide: top.classList.contains('wide'), c: top.querySelector('.ak-drc'), hash: location.hash.slice(1) });
      var c2 = document.createElement('div'); c2.className = 'ak-drc';
      top.replaceChild(c2, top.querySelector('.ak-drc'));
      top.classList.toggle('wide', !!wide); head(top, title, full);
      history.pushState({ ak: 1, step: top.__back.length }, '');
      return c2;
    }
    if (!dim) { dim = document.createElement('div'); dim.className = 'ak-dim'; dim.addEventListener('click', shutAll); document.body.appendChild(dim); }
    var d = document.createElement('aside');
    d.className = 'ak-dr' + (wide ? ' wide' : ''); d.__back = [];
    d.setAttribute('role', 'dialog');
    d.innerHTML = '<div class="ak-drb"><a href="#" class="ak-back" hidden aria-label="Back to what this panel showed before">← Back</a><b></b><a class="ak-full" target="_top" hidden>Open the full page</a><button type="button" class="ak-x" aria-label="Close">×</button></div><div class="ak-drc"></div>';
    d.querySelector('.ak-x').addEventListener('click', shutAll);
    d.querySelector('.ak-back').addEventListener('click', function (e) { e.preventDefault(); history.back(); });
    document.body.appendChild(d);
    stack.push(d); head(d, title, full);
    history.pushState({ ak: 1 }, '');
    document.documentElement.style.overflow = 'hidden';
    return d.querySelector('.ak-drc');
  }
  function stepBack(d) {
    var s = d.__back.pop();
    d.replaceChild(s.c, d.querySelector('.ak-drc'));
    d.classList.toggle('wide', s.wide); head(d, s.title, s.full); setHash(s.hash);
  }
  function close() {
    var d = stack.pop(); if (d) d.remove();
    if (!stack.length) { if (dim) { dim.remove(); dim = null; } document.documentElement.style.overflow = ''; setHash(''); }
  }
  // ×, Esc and a click outside: the whole drawer, every step it took in the history with it
  function shutAll() { var d = stack[stack.length - 1]; if (!d) return; d.__all = true; history.go(-(d.__back.length + 1)); }
  window.addEventListener('popstate', function () { var d = stack[stack.length - 1]; if (!d) return; if (d.__all || !d.__back.length) close(); else stepBack(d); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && stack.length && !document.querySelector('.ak-qt')) shutAll(); });
  function setHash(h) { try { history.replaceState(history.state, '', location.pathname + location.search + (h ? '#' + h : '')); } catch (e) {} }
  // inside a drawer's own frame, or inside the site's side panel: the one panel already open shows it, never a panel in it
  var kitHost = (function () { try { var f = window.frameElement; return f && f.closest && f.closest('.ak-dr') && parent.TvAdmin && parent.TvAdmin.frame ? parent.TvAdmin : null; } catch (e) { return null; } })();
  var inPeek = window.parent !== window && /[?&]peek=1/.test(location.search);

  function frame(url, title) {
    if (kitHost) return kitHost.frame(url, title);
    if (inPeek && window.TvAsk && TvAsk.peek) return TvAsk.peek(url, title);
    var hi = url.indexOf('#'), hash = hi < 0 ? '' : url.slice(hi), base = hi < 0 ? url : url.slice(0, hi);   // the #card stays last, so the page lands on it (Jason, 2026-11-19: "Open card shows the specific place")
    var u = base + (base.indexOf('?') < 0 ? '?' : '&') + (/^\/admin\/[a-z]+\/[a-z0-9_-]+\/?$/.test(base.split('?')[0]) ? 'panel=1' : 'embed=1') + hash;
    var c = layer(title, true, url); c.classList.add('fr');
    c.innerHTML = '<iframe src="' + esc(u) + '" title="' + esc(title) + '"></iframe>';
  }
  window.TvAdmin.isOpen = function () { return stack.length > 0; };
  window.TvAdmin.frame = frame;

  function detail(tool, id) {
    var c = layer('Loading…', false);
    var d = stack[stack.length - 1];
    if (!d.__back.length) setHash('open=' + tool + ':' + id);
    function paint(it) {
      d.querySelector('.ak-drb b').textContent = it.title; d.__title = it.title; d.setAttribute('aria-label', it.title);
      var w = it.workflow, h = it.subtitle ? '<p class="ak-sub">' + esc(it.subtitle) + '</p>' : '';
      if (w) {
        h += '<ol class="ak-steps" aria-label="Where it is">' + w.stages.filter(function (s) { return !s.end || s.state === 'now'; }).map(function (s) { return '<li class="' + (s.state === 'now' ? (s.end ? 'endnow' : 'now') : s.state) + '"' + (s.state === 'now' ? ' aria-current="step"' : '') + '>' + esc(s.label) + '</li>'; }).join('') + '</ol>';
        var fin = w.stages.some(function (s) { return s.state === 'now' && s.end; });
        if (fin) h += '<p class="ak-clock">Finished: ' + esc(w.labels[w.stage] || w.stage) + '</p>';
        else h += '<p class="ak-clock' + (w.late ? ' late' : '') + '">' + esc(w.labels[w.stage] || w.stage) + ' for ' + (w.hours < 48 ? w.hours + ' hours' : Math.round(w.hours / 24) + ' days') + (w.sla ? (w.late ? ', past its ' + w.sla + '-hour goal' : ' (goal: within ' + w.sla + ' hours)') : '') + '</p>';
        var mv = w.moves.filter(function (m) { return !m.any; }), any = w.moves.filter(function (m) { return m.any; });
        if (mv.length) h += '<div class="ak-sec">Move it on</div><div class="ak-acts">' + mv.map(btn).join('') + '</div><div data-ak-askbox></div>';
        if (any.length) h += '<div class="ak-sec">Any time</div><div class="ak-acts">' + any.map(btn).join('') + '</div>';
      }
      if (it.acts && it.acts.length) h += '<div class="ak-acts ak-actrow">' + it.acts.map(function (a) { return '<button type="button" class="ak-b' + (a.primary ? ' go' : '') + '" data-act="' + esc(a.key) + '"' + (a.off ? ' disabled title="' + esc(a.off) + '"' : '') + '>' + esc(a.label) + '</button>'; }).join('') + '</div><div data-ak-actbox></div>';
      if (it.photos) {   // its photos (a gallery the item owns): the first is the cover; + adds through the one uploader, × takes one off
        var ph = it.photos;
        h += '<div class="ak-sec">Photos</div><div class="ak-ph" data-ak-photos>' + ph.items.map(function (x, i) { return '<span class="ak-phi' + (i ? '' : ' first') + '" data-id="' + x.id + '"><img src="' + esc(x.card) + '" alt="" loading="lazy"><button type="button" data-ak-phx aria-label="Take this photo off">×</button></span>'; }).join('')
          + (ph.items.length < ph.max ? '<label class="ak-phadd" title="Add photos (JPG, PNG or WebP)">+ Photos<input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-ak-phadd></label>' : '') + '</div>'
          + (ph.note ? '<p class="ak-note ak-phnote">' + esc(ph.note) + ' ' + ph.items.length + ' of ' + ph.max + '.</p>' : '');
      }
      if (it.fields.length) h += '<div class="ak-sec">Details</div><dl class="ak-dl">' + it.fields.map(function (f) { return '<dt>' + esc(f[0]) + '</dt><dd>' + esc(f[1]) + '</dd>'; }).join('') + '</dl>';
      if (it.links.length) h += '<div class="ak-sec">Open</div><div class="ak-links">' + it.links.map(function (l) { return l[2] === 'new' ? '<a href="' + esc(l[1]) + '" target="_blank" rel="noopener">' + esc(l[0]) + ' ↗</a>' : '<a href="' + esc(l[1]) + '" data-ak-page>' + esc(l[0]) + '</a>'; }).join('') + '</div>';
      if (it.timeline) h += '<div class="ak-sec">' + esc(it.timeline_title || 'Activity') + '</div><div class="ak-tl">' + (it.timeline.length ? it.timeline.map(function (r) { return '<p class="ak-tl-' + esc(r.kind) + '">' + esc(r.text).replace(/\n/g, '<br>') + '<br><small>' + esc(r.at) + (r.by ? ' · ' + esc(r.by) : '') + '</small></p>'; }).join('') : '<p><small>Nothing yet.</small></p>') + '</div>';
      if (w) h += '<div class="ak-sec">History</div><div class="ak-tl">' + (w.history.length ? w.history.map(function (r) { return '<p>' + esc(w.labels[r.to] || r.to || r.move) + (r.from && r.from !== r.to ? ' <small>from ' + esc(w.labels[r.from] || r.from) + '</small>' : '') + '<br><small>' + esc(r.at) + (r.by ? ' · ' + esc(r.by) : '') + '</small>' + (r.note ? '<br>' + esc(r.note) : '') + (r.effects ? '<br><small>' + esc(r.effects) + '</small>' : '') + '</p>'; }).join('') : '<p><small>No moves made here yet.</small></p>') + '</div>';
      c.innerHTML = h;
      c.__it = it;
    }
    function btn(m) {
      return '<button type="button" class="ak-b' + (m.any ? '' : ' go') + (m.allowed ? '' : ' dis') + '" data-mv="' + esc(m.key) + '"' + (m.allowed ? '' : ' disabled title="' + esc(m.why) + '"') + '>' + esc(m.label) + (m.allowed ? '' : '<span class="ak-why">' + esc(m.why) + '</span>') + '</button>';
    }
    var ASK = { note: ['Why', 'textarea'], tracking: ['Tracking number', 'input'] };
    c.addEventListener('click', function (e) {   // an action of the tool's own (a note, tags, an edit): its form opens under the buttons
      var ab = e.target.closest('[data-act]'); if (!ab) return;
      var a = c.__it.acts.filter(function (x) { return x.key === ab.getAttribute('data-act'); })[0], box = c.querySelector('[data-ak-actbox]');
      if (box.getAttribute('data-for') === a.key && box.innerHTML) { box.innerHTML = ''; box.removeAttribute('data-for'); return; }
      box.setAttribute('data-for', a.key);
      box.innerHTML = '<form class="ak-ask"><b>' + esc(a.label) + '</b>' + (a.note ? '<p class="ak-note" style="margin:0">' + esc(a.note) + '</p>' : '') + a.asks.map(function (q) {
        var req = q[5] === 'optional' ? '' : ' required';
        if (q[2] === 'checks') return '<fieldset class="ak-checks"><legend>' + esc(q[1]) + '</legend>' + q[3].map(function (o) { return '<label><input type="checkbox" name="' + esc(q[0]) + '" value="' + esc(o[0]) + '"' + ((q[4] || []).indexOf(o[0]) >= 0 ? ' checked' : '') + '> ' + esc(o[1]) + '</label>'; }).join('') + '</fieldset>';
        if (q[2] === 'select') return pick(q[0], q[1], q[3], q[4]);
        var help = a.assist && a.assist.field === q[0] ? '<span class="ak-assist"><button type="button" class="ak-b" data-ak-assist' + (a.assist.ok ? '' : ' disabled title="' + esc(a.assist.why) + '"') + '>Write it for me</button><small>' + esc(a.assist.ok ? 'AI Assist writes it from the name, kind and details; you check it before saving.' : a.assist.why) + '</small></span>' : '';
        return '<label>' + esc(q[1]) + (q[2] === 'textarea' ? '<textarea class="ak-in" rows="' + (+q[4] || 3) + '" name="' + esc(q[0]) + '"' + req + words(q[0]) + '>' + esc(q[3] || '') + '</textarea>' : '<input class="ak-in" name="' + esc(q[0]) + '" value="' + esc(q[3] || '') + '"' + req + words(q[0]) + '>') + '</label>' + help;
      }).join('') + '<div class="ak-acts"><button class="ak-b go' + (a.danger ? ' no' : '') + '">' + esc(a.go || 'Save') + '</button><button type="button" class="ak-b" data-ak-nevermind>Never mind</button></div></form>';
      box.querySelector('[data-ak-nevermind]').onclick = function () { box.innerHTML = ''; box.removeAttribute('data-for'); };
      var wb = box.querySelector('[data-ak-assist]');
      if (wb) wb.onclick = function () {   // Write it for me: the draft goes in the box; Undo puts theirs back; nothing saved till Save
        var f = box.querySelector('form'), ta = f.elements[a.assist.field], inp = {};
        a.asks.forEach(function (q) { if (f.elements[q[0]] && q[2] !== 'checks') inp[q[0]] = f.elements[q[0]].value; });
        if (window.TvAsk) TvAsk.busy(wb, true);
        post(tool, { action: 'act', id: id, act: a.assist.act, input: inp }).then(function (j) {
          if (window.TvAsk) TvAsk.busy(wb, false);
          var was = ta.value; ta.value = j.draft; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus();
          var u = wb.parentNode.querySelector('[data-ak-undo]') || document.createElement('button');
          u.type = 'button'; u.className = 'ak-b'; u.setAttribute('data-ak-undo', ''); u.textContent = 'Undo';
          u.onclick = function () { ta.value = was; u.remove(); };
          wb.insertAdjacentElement('afterend', u);
          if (window.TvAsk) TvAsk.say(j.message || 'Written.', { anchor: wb });
        }).catch(function (x) { if (window.TvAsk) { TvAsk.busy(wb, false); TvAsk.say(x.message, { anchor: wb, bad: true }); } });
      };
      box.querySelector('form').onsubmit = function (ev) {
        ev.preventDefault(); var f = ev.target, inp = {}, go = f.querySelector('.go');
        a.asks.forEach(function (q) { inp[q[0]] = q[2] === 'checks' ? Array.prototype.map.call(f.querySelectorAll('input[name="' + q[0] + '"]:checked'), function (x) { return x.value; }) : f.elements[q[0]].value.trim(); });
        go.disabled = true;
        (function send(inp) { post(tool, { action: 'act', id: id, act: a.key, input: inp }).then(function (j) {
          if (j.ask) {   // a question first: answered in place, then sent again with the answer (decisions/0142 §4)
            return (window.TvAsk ? TvAsk.confirm(j.ask.text, { anchor: go, yes: j.ask.yes, no: j.ask.no }) : Promise.resolve(false)).then(function (yes) {
              var more = yes ? j.ask.yes_input : j.ask.no_input;
              if (!more) { go.disabled = false; return; }
              send(Object.assign({}, inp, more));
            });
          }
          lists.filter(function (x) { return x.C.tool === tool; }).forEach(function (x) { x.load(); });
          if (j.gone) { history.back(); if (window.TvAsk) TvAsk.say(j.message || 'Deleted.'); return; }   // the record is gone: its drawer closes
          paint(j.item); say(j.message || 'Saved.'); })
          .catch(function (x) { go.disabled = false; if (window.TvAsk) TvAsk.say(x.message, { anchor: go, bad: true }); }); })(inp);
      };
      var first = box.querySelector('textarea, input.ak-in'); if (first) first.focus();
      fitNow(box);
    });
    c.addEventListener('change', function (e) {   // + Photos
      var inp = e.target.closest('[data-ak-phadd]'); if (!inp || !inp.files.length || !window.TvUpload) return;
      var ph = c.__it.photos, box = c.querySelector('[data-ak-photos]');
      TvUpload.send(inp.files, { target: ph.target, ctx: ph.ctx, el: box, shrink: 2400 }).then(function (r) {
        get(tool, { action: 'get', id: id }).then(function (j) { paint(j.item); say((r && r[0] && r[0].message) || 'Added.'); });
      }, function () { inp.value = ''; });
    });
    c.addEventListener('click', function (e) {   // × on a photo: the rest kept, in order
      var x = e.target.closest('[data-ak-phx]'); if (!x) return;
      var box = x.closest('[data-ak-photos]'); x.closest('.ak-phi').remove();
      var ids = Array.prototype.map.call(box.querySelectorAll('.ak-phi'), function (p) { return +p.getAttribute('data-id'); });
      post(tool, { action: 'act', id: id, act: 'photos', input: { ids: ids } }).then(function (j) { paint(j.item); say(j.message || 'Saved.'); })
        .catch(function (err) { if (window.TvAsk) TvAsk.say(err.message, { anchor: box, bad: true }); });
    });
    c.addEventListener('click', function (e) {
      var b = e.target.closest('[data-mv]'); if (!b || b.disabled) return;
      var it = c.__it, m = it.workflow.moves.filter(function (x) { return x.key === b.getAttribute('data-mv'); })[0];
      if (m.link) { frame(m.link, m.label); return; }
      var box = c.querySelector('[data-ak-askbox]');
      var bad = /cancel|end|lost|refund|stop|no$/.test(m.to || m.key);
      if (m.asks.length && box) {
        box.innerHTML = '<form class="ak-ask"><b>' + esc(m.label) + '</b>' + m.asks.map(function (a) { var q = ASK[a] || [a.replace(/_/g, ' '), 'input']; return '<label>' + esc(q[0]) + (q[1] === 'textarea' ? '<textarea class="ak-in" rows="2" name="' + esc(a) + '" required' + words(a) + '></textarea>' : '<input class="ak-in" name="' + esc(a) + '" required' + words(a) + '>') + '</label>'; }).join('')
          + '<div class="ak-acts"><button class="ak-b go' + (bad ? ' no' : '') + '">' + esc(m.label) + '</button><button type="button" class="ak-b" data-ak-nevermind>Never mind</button></div></form>';
        box.querySelector('[data-ak-nevermind]').onclick = function () { box.innerHTML = ''; };
        box.querySelector('form').onsubmit = function (ev) { ev.preventDefault(); var inp = {}; m.asks.forEach(function (a) { inp[a] = ev.target.elements[a].value.trim(); }); go(m, inp, ev.target.querySelector('.go')); };
        box.querySelector('.ak-in').focus();
        return;
      }
      if (bad && window.TvAsk && !TvAsk.gate(b, m.label + '?', { yes: m.label, danger: true })) return;
      go(m, {}, b);
    });
    function go(m, inp, b) {
      if (b) b.disabled = true;
      post(tool, { action: 'move', id: id, move: m.key, input: inp }).then(function (j) { paint(j.item); say(m.label + ': done.'); var l = lists.filter(function (x) { return x.C.tool === tool; }); l.forEach(function (x) { x.load(); }); })
        .catch(function (x) { if (b) b.disabled = false; if (window.TvAsk) TvAsk.say(x.message, b ? { anchor: b, bad: true } : { bad: true }); });
    }
    get(tool, { action: 'get', id: id }).then(function (j) { paint(j.item); }).catch(function (x) { c.innerHTML = '<p>' + esc(x.message) + '</p>'; });
  }
  window.TvAdmin.detail = detail;

  document.addEventListener('click', function (e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;   // a deliberate new tab still works
    var it = e.target.closest('[data-ak-item]');
    if (it) { e.preventDefault(); detail(it.closest('[data-ak-table]').__list.C.tool, +it.getAttribute('data-ak-item')); return; }
    var p = e.target.closest('a[data-ak-page]');
    if (p) { e.preventDefault(); frame(p.getAttribute('href'), p.textContent.replace('↗', '').trim()); return; }
    var hd = e.target.closest('[data-ak-hand]');
    if (hd) { e.preventDefault(); frame(hd.getAttribute('data-ak-hand'), hd.getAttribute('data-ak-title')); }
  });

  // ---- quick tasks (New …): a small box over the page; a page-sized one opens in a drawer ---------------------------
  function newItem(list) {
    var c = list.C.create;
    if (c.page) { if (c.page.indexOf(location.pathname.replace(/\/$/, '') + '/') === 0) { location.href = c.page; return; } frame(c.page, c.label); return; }   // a page of this section (a new order) opens as a page, not in a drawer
    var q = document.createElement('div'); q.className = 'ak-qt';
    q.innerHTML = '<div class="ak-qtb" role="dialog" aria-label="' + esc(c.label) + '"><div class="ak-drb"><b>' + esc(c.label) + '</b><button type="button" class="ak-x" aria-label="Close">×</button></div><form>'
      + c.fields.map(function (f) {
        if (f[2] === 'select') return pick(f[0], f[1], f[3], null);
        return '<label>' + esc(f[1]) + '<input class="ak-in" name="' + esc(f[0]) + '" type="' + (f[2] === 'number' ? 'number' : 'text') + '"' + (f[4] === 'optional' ? '' : ' required') + (f[2] === 'number' ? '' : words(f[0])) + '></label>';
      }).join('') + '<div class="ak-acts"><button class="ak-b go">' + esc(c.label) + '</button></div></form></div>';
    document.body.appendChild(q);
    function shut() { q.remove(); document.removeEventListener('keydown', key); }
    function key(e) { if (e.key === 'Escape') shut(); }
    document.addEventListener('keydown', key);
    q.querySelector('.ak-x').onclick = shut;
    q.addEventListener('click', function (e) { if (e.target === q) shut(); });
    var form = q.querySelector('form'); form.elements[0].focus(); fitNow(form);
    form.onsubmit = function (e) {
      e.preventDefault();
      var inp = {}; c.fields.forEach(function (f) { inp[f[0]] = form.elements[f[0]].value.trim(); });
      var b = form.querySelector('.go'); b.disabled = true;
      post(list.C.tool, { action: 'create', input: inp }).then(function (j) { shut(); say(j.message || 'Done.'); list.load(); if (j.id && list.C.open === 'detail') detail(list.C.tool, j.id); })
        .catch(function (x) { b.disabled = false; if (window.TvAsk) TvAsk.say(x.message, { anchor: b, bad: true }); });   // a quick task's problem: inside its box, beside its button
    };
  }

  // ---- System Configuration → Permissions: each change saves as it's made -----------------------------------------
  document.addEventListener('change', function (e) {
    var r = e.target.closest('[data-ak-perm="1"] .ak-prow'); if (!r) return;
    var depts = Array.prototype.map.call(r.querySelectorAll('input[name=d]:checked'), function (x) { return x.value; });
    post('config/permissions', { rule: r.getAttribute('data-action'), min: r.querySelector('select').value, depts: depts })
      .then(function () { r.classList.add('saved'); setTimeout(function () { r.classList.remove('saved'); }, 1200); say('Saved.'); }).catch(function (x) { say(x.message); });
  });

  // ---- start --------------------------------------------------------------------------------------------------------
  document.querySelectorAll('[data-ak-table]').forEach(function (el) { var l = new List(el); el.__list = l; lists.push(l); });
  // ?new=<tool> (the admin search's Actions): start that list's new item at once; ?q=… (its See all): search the list
  (function () { var p = new URLSearchParams(location.search), nw = p.get('new'), sq = p.get('q');
    if (nw) { var ln = lists.filter(function (x) { return x.C.tool === nw && x.C.create; })[0]; if (ln) setTimeout(function () { newItem(ln); }, 300); }
    if (sq) lists.forEach(function (x) { var i = x.el.querySelector('.ak-q'); if (i && !i.value) { i.value = sq; i.dispatchEvent(new Event('input')); } }); })();
  document.querySelectorAll('[data-ak-frame]').forEach(function (f) { if (window.TvAsk && TvAsk.fitFrame) TvAsk.fitFrame(f); });
  document.querySelectorAll('[data-ak-full]').forEach(function (a) { a.target = '_top'; });
  // an item opened from its address (/admin/backoffice/orders/922), or still open when the page was left
  var body = document.querySelector('[data-ak-open]'), m = /^#open=([a-z]+\/[a-z0-9_-]+):(\d+)$/.exec(location.hash);
  if (m) detail(m[1], +m[2]);
  else if (body) { var o = body.getAttribute('data-ak-open').split('|'), l = listFor(o[0]); if (l && l.C.open === 'lead') { var b = document.createElement('button'); b.setAttribute('data-lead', o[1]); b.hidden = true; document.body.appendChild(b); setTimeout(function () { b.click(); }, 300); } else if (l) detail(o[0], +o[1]); }
  // a link to one thing lands on it (Jason, 2026-11-19: "the card or section should always have a route that I can follow to
  // fix the specific issue"): #<id> opens the folded card or section it names and every fold around it, scrolls to it and
  // marks it for a moment, on the page and in a drawer; again when the address's # changes
  function land() {
    var id = decodeURIComponent((location.hash || '').slice(1)); if (!id || id.indexOf('=') >= 0) return;
    var el = document.getElementById(id); if (!el) return;
    for (var d = el; d; d = d.parentElement) if (d.tagName === 'DETAILS') d.open = true;
    var f = el.querySelector && el.querySelector(':scope > details'); if (f) f.open = true;
    // the kit's folds (tv_admin_fold: a button and its hidden body), the one it names and every one around it
    var folds = []; var own = el.classList && el.classList.contains('ak-fold') ? el : (el.querySelector && el.querySelector(':scope > .ak-fold')); if (own) folds.push(own);
    for (var a = el.parentElement; a; a = a.parentElement) if (a.classList && a.classList.contains('ak-fold')) folds.push(a);
    folds.forEach(function (fo) { var t = fo.querySelector(':scope > .ak-foldh .ak-foldt'), b = t && document.getElementById(t.getAttribute('aria-controls')); if (t && b) { t.setAttribute('aria-expanded', 'true'); b.hidden = false; } });
    setTimeout(function () { el.scrollIntoView({ block: 'start', behavior: 'smooth' }); el.classList.add('ak-landed'); setTimeout(function () { el.classList.remove('ak-landed'); }, 2600); }, 60);
  }
  if (!document.getElementById('ak-landed-css')) { var st = document.createElement('style'); st.id = 'ak-landed-css'; st.textContent = '.ak-landed{outline:3px solid #8FBCA8;outline-offset:3px;border-radius:.5rem;transition:outline-color 1s}'; document.head.appendChild(st); }
  land(); window.addEventListener('hashchange', land);

  // ---- the table standard for tables the server writes (design/table-standard.md, T3-T5): js/tv-table.js since T4, shared
  // with the admin's older pages; loaded here once, and these two stay the same calls
  function staticTable(t) { if (window.TvTable) TvTable.enhance(t); }
  function staticTables(root) { if (window.TvTable) TvTable.all(root); }
  window.TvAdmin.staticTable = staticTable; window.TvAdmin.staticTables = staticTables;
  if (!window.TvTable && !document.querySelector('script[src^="/js/tv-table.js"]')) { var tvt = document.createElement('script'); tvt.src = '/js/tv-table.js' + ((document.currentScript && document.currentScript.src.split('?')[1]) ? '?' + document.currentScript.src.split('?')[1] : ''); document.head.appendChild(tvt); }
})();
