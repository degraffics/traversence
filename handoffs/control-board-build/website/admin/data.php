<?php
// admin/data.php: Admin Data Browser. Find, filter, sort and edit any table.
// All data comes from /admin/api/data.php, which enforces access server-side.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';          // first, so the check below runs before any output
require_once __DIR__ . '/../../app/ui/page-shell.php';
header('Content-Type: text/html; charset=utf-8');         // bootstrap defaults to JSON

$user = Auth::currentUser();
if ($user === null || empty($user['is_admin'])) {
    http_response_code($user === null ? 401 : 403);
    tv_page_start('Data browser');
    echo '<h1 class="font-serif font-bold text-2xl">Data browser</h1>'
       . '<p class="text-sm text-[#5C4632]">' . ($user === null ? 'Please sign in with an admin account.' : 'This account is not an admin.') . '</p>';
    tv_page_end();
    exit;
}

tv_page_start('Data browser');
?>
<div>
  <h1 class="font-serif font-bold text-3xl">Data browser</h1>
  <p class="text-sm text-[#5C4632] mt-1">Every table, one line per record. Click a row to edit it. Edits are logged.</p>
</div>

<section class="bg-[#F7EFDC] border border-[#CBB98D]/70 rounded-2xl shadow-md p-3 space-y-3">
  <div class="flex flex-wrap gap-2">
    <select id="db-table" class="border border-[#CBB98D] rounded-lg bg-white px-2 py-2 text-sm font-semibold"></select>
    <input id="db-q" type="search" placeholder="Find (name, text, or ID)" class="flex-1 min-w-[10rem] border border-[#CBB98D] rounded-lg bg-white px-3 py-2 text-sm">
  </div>
  <div id="db-filters" class="flex flex-wrap gap-2"></div>
  <div class="flex flex-wrap items-center gap-2 text-sm">
    <label class="text-xs text-[#8A7355]" for="db-sort">Sort</label>
    <select id="db-sort" class="border border-[#CBB98D] rounded-lg bg-white px-2 py-1.5 text-sm"></select>
    <button id="db-dir" type="button" class="border border-[#CBB98D] rounded-lg bg-white px-2 py-1.5 text-sm" aria-label="Toggle sort direction">&darr;</button>
    <button id="db-clear" type="button" class="text-xs underline text-[#5C4632] ml-auto">Clear</button>
  </div>
</section>

<p id="db-note" class="text-xs text-[#5C4632] hidden"></p>
<p id="db-count" class="text-xs text-[#8A7355]"></p>

<div class="bg-[#F7EFDC] border border-[#CBB98D]/70 rounded-2xl shadow-md overflow-hidden">
  <div id="db-head" class="grid grid-cols-[3.5rem_1fr_6rem_5.5rem] gap-2 px-3 py-2 text-[10px] uppercase tracking-wide text-[#8A7355] border-b border-[#CBB98D]/60 bg-[#E3D3AC]/50"></div>
  <ul id="db-rows" class="divide-y divide-[#CBB98D]/50"></ul>
</div>

<div class="flex items-center justify-between text-sm">
  <button id="db-prev" type="button" class="border border-[#CBB98D] rounded-lg bg-white px-3 py-1.5 disabled:opacity-40">&larr; Prev</button>
  <span id="db-page" class="text-xs text-[#5C4632]"></span>
  <button id="db-next" type="button" class="border border-[#CBB98D] rounded-lg bg-white px-3 py-1.5 disabled:opacity-40">Next &rarr;</button>
</div>

<!-- Editor -->
<div id="ed" class="fixed inset-0 z-50 hidden">
  <div id="ed-back" class="absolute inset-0 bg-black/50"></div>
  <div class="absolute inset-x-0 bottom-0 top-10 sm:inset-y-6 sm:left-auto sm:right-6 sm:w-[34rem] bg-[#EFE3C8] rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col">
    <div class="flex items-center justify-between px-4 py-3 border-b border-[#CBB98D]">
      <h2 id="ed-title" class="font-serif font-bold text-lg truncate"></h2>
      <button id="ed-close" type="button" class="text-2xl leading-none px-2" aria-label="Close">&times;</button>
    </div>
    <div id="ed-msg" class="hidden mx-4 mt-3 rounded-lg px-3 py-2 text-xs"></div>
    <div id="ed-body" class="flex-1 overflow-y-auto px-4 py-3 space-y-3"></div>
    <div class="flex items-center gap-2 px-4 py-3 border-t border-[#CBB98D]">
      <button id="ed-save" type="button" class="bg-[#3E2B1F] text-[#EFE3C8] rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-40">Save</button>
      <span id="ed-state" class="text-xs text-[#8A7355]"></span>
    </div>
  </div>
</div>

<script>
(function () {
  var API = '/admin/api/data.php';
  var CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  var S = { tables: [], table: null, meta: null, q: '', filters: {}, sort: '', dir: 'desc', page: 1, total: 0, per: 25 };
  var $ = function (id) { return document.getElementById(id); };

  function h(tag, attrs, kids) {
    var el = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'class') el.className = attrs[k];
      else if (k === 'text') el.textContent = attrs[k];
      else if (k === 'on') Object.keys(attrs.on).forEach(function (e) { el.addEventListener(e, attrs.on[e]); });
      else el.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { if (c) el.appendChild(c); });
    return el;
  }

  function errText(d, r) {
    if (d && typeof d.error === 'string') return d.error;
    if (d && d.error && d.error.message) return d.error.message;
    if (d && d.message) return d.message;
    return 'Request failed (' + r.status + ')';
  }
  function call(opts) {
    var init = { credentials: 'include' };
    var url = API;
    if (opts.post) {
      init.method = 'POST';
      init.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF };
      init.body = JSON.stringify(opts.post);
    } else {
      var u = new URL(API, location.origin);
      Object.keys(opts.get).forEach(function (k) {
        var v = opts.get[k];
        if (v === '' || v === undefined || v === null) return;
        if (typeof v === 'object') Object.keys(v).forEach(function (a) { if (v[a] !== '') u.searchParams.set(k + '[' + a + ']', v[a]); });
        else u.searchParams.set(k, v);
      });
      url = u.toString();
    }
    return fetch(url, init).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || d.success === false) { var e = new Error(errText(d, r)); e.status = r.status; throw e; }
        return d.data || d;
      });
    });
  }

  // ---- list ---------------------------------------------------------------
  function loadTables() {
    return call({ get: { action: 'tables' } }).then(function (d) {
      S.tables = d.tables;
      var sel = $('db-table'); sel.textContent = '';
      d.tables.forEach(function (t) {
        sel.appendChild(h('option', { value: t.name, text: (t.label || t.name) + ' (~' + t.approx.toLocaleString() + ')' + (t.readonly ? ' · read-only' : '') }));
      });
      var first = d.tables.some(function (t) { return t.name === 'entities'; }) ? 'entities' : (d.tables[0] && d.tables[0].name);
      return selectTable(first);
    });
  }

  function selectTable(name) {
    S.table = name; $('db-table').value = name;
    S.q = ''; S.filters = {}; S.page = 1; $('db-q').value = '';
    return call({ get: { action: 'meta', table: name } }).then(function (m) {
      S.meta = m;
      S.sort = m.pk || (m.columns[0] && m.columns[0].name) || ''; S.dir = 'desc';
      var ss = $('db-sort'); ss.textContent = '';
      m.columns.forEach(function (c) { if (c.kind !== 'binary') ss.appendChild(h('option', { value: c.name, text: c.name })); });
      ss.value = S.sort; $('db-dir').innerHTML = '&darr;';
      var fx = $('db-filters'); fx.textContent = '';
      m.filters.forEach(function (f) {
        var el;
        if (f.options) {
          el = h('select', { class: 'border border-[#CBB98D] rounded-lg bg-white px-2 py-1.5 text-xs', 'data-col': f.col }, [h('option', { value: '', text: f.col + ': any' })]
            .concat([h('option', { value: '__null__', text: f.col + ': empty' })])
            .concat(f.options.map(function (o) { return h('option', { value: o, text: f.col + ': ' + o }); })));
          el.addEventListener('change', function () { S.filters[f.col] = el.value; S.page = 1; loadRows(); });
        } else {
          el = h('input', { type: 'text', placeholder: f.col + ' = ', class: 'border border-[#CBB98D] rounded-lg bg-white px-2 py-1.5 text-xs w-28', 'data-col': f.col });
          el.addEventListener('change', function () { S.filters[f.col] = el.value.trim(); S.page = 1; loadRows(); });
        }
        fx.appendChild(el);
      });
      var d = m.display;
      var head = $('db-head'); head.textContent = '';
      [['ID', m.pk], [d.label || 'Name', d.label], [d.status || 'Status', d.status], [d.updated || 'Updated', d.updated]].forEach(function (p) {
        head.appendChild(h('button', { type: 'button', class: 'text-left uppercase tracking-wide hover:underline truncate', text: p[0], on: { click: function () { if (p[1]) setSort(p[1]); } } }));
      });
      var note = $('db-note');
      if (m.readonly) { note.textContent = m.pk ? 'This table is read-only here.' : 'No single-column primary key, so this table is view-only.'; note.classList.remove('hidden'); }
      else note.classList.add('hidden');
      return loadRows();
    });
  }

  function setSort(col) {
    if (S.sort === col) S.dir = S.dir === 'asc' ? 'desc' : 'asc'; else { S.sort = col; S.dir = 'asc'; }
    $('db-sort').value = S.sort; $('db-dir').innerHTML = S.dir === 'asc' ? '&uarr;' : '&darr;';
    S.page = 1; loadRows();
  }

  function loadRows() {
    return call({ get: { action: 'rows', table: S.table, q: S.q, sort: S.sort, dir: S.dir, page: S.page, f: S.filters } }).then(function (d) {
      S.total = d.total; S.per = d.per_page;
      var ul = $('db-rows'); ul.textContent = '';
      if (!d.rows.length) ul.appendChild(h('li', { class: 'px-3 py-4 text-sm text-[#8A7355]', text: 'Nothing found.' }));
      d.rows.forEach(function (r) {
        var st = r._status === null || r._status === undefined ? '' : String(r._status);
        var btn = h('button', {
          type: 'button', class: 'w-full grid grid-cols-[3.5rem_1fr_6rem_5.5rem] gap-2 px-3 py-2.5 text-left text-sm hover:bg-[#E3D3AC]/60',
          on: { click: function () { if (r._pk !== null) openRow(r._pk); } }
        }, [
          h('span', { class: 'font-mono text-xs text-[#8A7355] self-center', text: r._pk === null ? '' : String(r._pk) }),
          h('span', { class: 'truncate font-semibold', text: r._label === null ? '(no name)' : String(r._label) }),
          h('span', { class: 'self-center' }, [st ? h('span', { class: 'inline-block max-w-full truncate text-[10px] uppercase tracking-wide rounded-full bg-amber-200/70 text-[#5C4632] px-2 py-0.5', text: st }) : null]),
          h('span', { class: 'text-xs text-[#8A7355] self-center', text: r._updated ? String(r._updated).slice(0, 10) : '' })
        ]);
        ul.appendChild(h('li', {}, [btn]));
      });
      var pages = Math.max(1, Math.ceil(d.total / d.per_page));
      $('db-count').textContent = d.total.toLocaleString() + ' record' + (d.total === 1 ? '' : 's');
      $('db-page').textContent = 'Page ' + S.page + ' of ' + pages;
      $('db-prev').disabled = S.page <= 1; $('db-next').disabled = S.page >= pages;
    }).catch(function (e) { $('db-count').textContent = e.message; });
  }

  // ---- editor -------------------------------------------------------------
  var E = null;   // { table, id, cols, orig, fields, readonly }

  function msg(text, bad) {
    var m = $('ed-msg');
    if (!text) { m.classList.add('hidden'); return; }
    m.textContent = text; m.className = 'mx-4 mt-3 rounded-lg px-3 py-2 text-xs ' + (bad ? 'bg-red-100 text-red-900' : 'bg-green-100 text-green-900');
  }

  function openRow(id) {
    return call({ get: { action: 'row', table: S.table, id: id } }).then(function (d) {
      var body = $('ed-body'); body.textContent = ''; msg('');
      E = { table: S.table, id: id, cols: d.columns, orig: d.row, fields: {}, readonly: d.readonly };
      $('ed-title').textContent = S.table + ' #' + id;
      d.columns.forEach(function (c) {
        if (c.kind === 'binary') return;
        var val = d.row[c.name];
        var ro = d.readonly || c.readonly;
        var input, getVal;
        var isNull = val === null;
        if (c.kind === 'bool') {
          input = h('input', { type: 'checkbox', class: 'h-5 w-5' }); input.checked = String(val) === '1'; input.disabled = ro;
          getVal = function () { return input.checked ? '1' : '0'; };
        } else if (c.kind === 'enum') {
          input = h('select', { class: 'w-full border border-[#CBB98D] rounded-lg bg-white px-2 py-2 text-sm' }, (c.options || []).map(function (o) { return h('option', { value: o, text: o }); }));
          input.value = val === null ? (c.options || [''])[0] : String(val); input.disabled = ro;
          getVal = function () { return input.value; };
        } else if (c.kind === 'text' || c.kind === 'json') {
          input = h('textarea', { rows: c.kind === 'json' ? 8 : 4, class: 'w-full border border-[#CBB98D] rounded-lg bg-white px-3 py-2 text-sm' + (c.kind === 'json' ? ' font-mono' : '') });
          input.value = val === null ? '' : String(val); input.disabled = ro;
          getVal = function () { return input.value; };
        } else {
          input = h('input', { type: 'text', class: 'w-full border border-[#CBB98D] rounded-lg bg-white px-3 py-2 text-sm', inputmode: (c.kind === 'int' || c.kind === 'decimal') ? 'decimal' : 'text' });
          input.value = val === null ? '' : String(val); input.disabled = ro;
          getVal = function () { return input.value; };
        }
        if (ro) input.classList.add('bg-[#E3D3AC]/40', 'text-[#5C4632]');

        var nullBox = null;
        if (!ro && c.nullable) {
          nullBox = h('input', { type: 'checkbox', class: 'mr-1' }); nullBox.checked = isNull; input.disabled = isNull;
          nullBox.addEventListener('change', function () { input.disabled = nullBox.checked; });
        }
        var label = h('div', { class: 'flex items-center justify-between gap-2 mb-1' }, [
          h('label', { class: 'text-xs font-semibold' }, [document.createTextNode(c.name), h('span', { class: 'ml-2 font-normal text-[#8A7355]', text: c.type + (ro ? ' · locked' : '') })]),
          c.fk && val !== null ? h('button', { type: 'button', class: 'text-xs underline text-[#5C4632]', text: 'open ' + c.fk + ' \u2197', on: { click: function () { jumpTo(c.fk, val); } } }) : null
        ]);
        var wrap = h('div', {}, [label, input, nullBox ? h('label', { class: 'block text-[11px] text-[#8A7355] mt-1' }, [nullBox, document.createTextNode('NULL')]) : null]);
        body.appendChild(wrap);
        E.fields[c.name] = { ro: ro, get: function () { return (nullBox && nullBox.checked) ? null : getVal(); } };
      });
      $('ed-save').classList.toggle('hidden', d.readonly);
      $('ed-state').textContent = d.readonly ? 'Read-only' : '';
      $('ed').classList.remove('hidden');
    }).catch(function (e) { TvAsk.say(e.message); });
  }

  function jumpTo(table, id) {
    $('ed').classList.add('hidden');
    selectTable(table).then(function () { openRow(id); });
  }

  function save() {
    if (!E) return;
    var changes = {}, before = {}, n = 0;
    Object.keys(E.fields).forEach(function (name) {
      var f = E.fields[name]; if (f.ro) return;
      var now = f.get(), was = E.orig[name];
      var a = now === null ? null : String(now), b = was === null ? null : String(was);
      if (a !== b) { changes[name] = now; before[name] = was; n++; }
    });
    if (!n) { msg('No changes to save.', false); return; }
    $('ed-save').disabled = true; $('ed-state').textContent = 'Saving…';
    call({ post: { action: 'update', table: E.table, id: E.id, changes: changes, before: before } }).then(function () {
      msg('Saved.', false); $('ed-state').textContent = '';
      var keep = E.id;
      return Promise.all([loadRows(), call({ get: { action: 'row', table: E.table, id: keep } }).then(function (d) { E.orig = d.row; })]);
    }).catch(function (e) { msg(e.message, true); $('ed-state').textContent = ''; })
      .then(function () { $('ed-save').disabled = false; });
  }

  // ---- wiring -------------------------------------------------------------
  var t; $('db-q').addEventListener('input', function (e) { clearTimeout(t); t = setTimeout(function () { S.q = e.target.value.trim(); S.page = 1; loadRows(); }, 300); });
  $('db-table').addEventListener('change', function (e) { selectTable(e.target.value); });
  $('db-sort').addEventListener('change', function (e) { S.sort = e.target.value; S.page = 1; loadRows(); });
  $('db-dir').addEventListener('click', function () { S.dir = S.dir === 'asc' ? 'desc' : 'asc'; $('db-dir').innerHTML = S.dir === 'asc' ? '&uarr;' : '&darr;'; S.page = 1; loadRows(); });
  $('db-clear').addEventListener('click', function () { selectTable(S.table); });
  $('db-prev').addEventListener('click', function () { if (S.page > 1) { S.page--; loadRows(); } });
  $('db-next').addEventListener('click', function () { S.page++; loadRows(); });
  $('ed-close').addEventListener('click', function () { $('ed').classList.add('hidden'); });
  $('ed-back').addEventListener('click', function () { $('ed').classList.add('hidden'); });
  $('ed-save').addEventListener('click', save);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') $('ed').classList.add('hidden'); });

  loadTables().catch(function (e) { $('db-count').textContent = e.message; });
})();
</script>
<?php tv_page_end();
