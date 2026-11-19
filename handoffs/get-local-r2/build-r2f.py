#!/usr/bin/env python3
"""Get Local R2, part 6: the approved concept's last pieces (Jason, 2026-11-19: "Build as shown"; layout: "Lets build it
each way for a A/B test"). Arrange (per page; Show: closed for good; the details on each card, in order; the computer
layout), the Table view (List | Table | Map, spreadsheet-style column menus), and the list beside the map (numbered pins,
the distance ring, Search this area) as one side of the A/B test, the List | Table | Map switch the other.
Replayable: build-r2f.py <root> with website/directory/index.php and website/api/search_listings.php."""
import sys, os
R = sys.argv[1]
def patch(rel, pairs):
    p = os.path.join(R, rel); s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert s.count(a) == 1, (rel, a[:90], s.count(a)); s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)

# ---- the search: hide businesses closed for good when asked (Arrange's Show), and say its centre (the ring) ----
OLD_MORE = '  .dr-more{display:block;width:100%;margin-top:.4rem;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;padding:.55rem;font-weight:600;color:var(--tv-ink);cursor:pointer}\n'
patch('website/api/search_listings.php', [
 ("    // Favorites: only these listings (the person's own saved and linked businesses; the page sends their ids)\n",
  "    // Arrange → Show (Jason, 2026-11-19): closed for good shows by default, marked Closed (never hidden by us); a person may hide it for themselves\n"
  "    if (($_GET['closed'] ?? '') === 'hide') $where .= \" AND NOT EXISTS (SELECT 1 FROM entity_metadata cz WHERE cz.entity_id = x.id AND cz.meta_key = 'closed')\";\n"
  "    // Favorites: only these listings (the person's own saved and linked businesses; the page sends their ids)\n"),
 ("        'listings'          => $listings,\n        'read'",
  "        'listings'          => $listings,\n        'center'            => $hasLocation ? ['lat' => $centerLat, 'lon' => $centerLon, 'radius' => $radius] : null,   // the list beside the map: its distance ring (2026-11-19)\n        'read'"),
])

D = 'website/directory/index.php'
CSS = r"""
  /* Arrange (Jason, 2026-11-19): per page, what shows, the details on each card in order, the computer layout; remembered on this device */
  .dr-adl{position:absolute;right:0;top:calc(100% + .35rem);z-index:40;width:min(22rem,calc(100vw - 6px));max-height:min(34rem,calc(100vh - 8rem));overflow:auto;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.6rem .75rem .7rem}
  .dr-adl h4{margin:.55rem 0 .3rem;font:700 .74rem var(--tv-font-ui);letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta)}.dr-adl h4:first-of-type{margin-top:.1rem}
  .dr-adl .dr-pills{display:flex;flex-wrap:wrap;gap:.3rem}
  .dr-adl .dr-pills button{border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-ink);border-radius:999px;padding:.22rem .7rem;font:600 .85rem var(--tv-font-ui);cursor:pointer}
  .dr-adl .dr-pills button[aria-pressed="true"]{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber)}
  .dr-sw{display:flex;align-items:center;gap:.5rem;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;padding:.2rem 0}.dr-sw input{width:1rem;height:1rem;accent-color:var(--tv-umber)}
  .dr-sw small,.dr-adn{display:block;color:var(--tv-meta);font-size:.78rem;font-weight:400}
  .dr-dl{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.25rem}
  .dr-dl li{display:flex;align-items:center;gap:.35rem;border:1px solid var(--tv-line);background:var(--tv-field);border-radius:.5rem;padding:.25rem .3rem .25rem .5rem;font:500 .88rem var(--tv-font-ui);cursor:grab}
  .dr-dl li.drag{opacity:.45}.dr-dl li span{flex:1}.dr-dl li i{font-style:normal;color:var(--tv-meta);font-size:.75rem}
  .dr-dl li button{width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);cursor:pointer;line-height:1;padding:0}
  .dr-dl li button:disabled{opacity:.35;cursor:default}
  .dr-dadd{margin-top:.35rem;border:1px solid var(--tv-line-strong);border-radius:.55rem;background:var(--tv-field);padding:.3rem .45rem;font:inherit;font-size:.86rem;color:var(--tv-ink);max-width:100%}
  .dr-ard{margin-top:.6rem;border:0;background:none;color:var(--tv-link);text-decoration:underline;font:600 .85rem var(--tv-font-ui);cursor:pointer;padding:0}
  /* the Table view: each column sorts; its menu works like a spreadsheet's (A to Z, Z to A, search, ticks with counts) */
  .dr-tw{overflow:auto;border:1px solid var(--tv-line-strong);border-radius:.8rem;background:var(--tv-card-a)}
  .dr-tb{width:100%;border-collapse:collapse;font:400 .9rem var(--tv-font-ui);color:var(--tv-ink)}
  .dr-tb th{position:sticky;top:0;background:var(--tv-sec,#EDE6D5);text-align:left;padding:0;border-bottom:1px solid var(--tv-line-strong);white-space:nowrap;z-index:1}
  .dr-tb th button{display:flex;align-items:center;gap:.3rem;width:100%;border:0;background:none;padding:.5rem .6rem;font:700 .8rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;text-align:left}
  .dr-tb th button b{font-weight:400;color:var(--tv-meta)}.dr-tb th.f button{color:var(--tv-link)}
  .dr-tb td{padding:.45rem .6rem;border-bottom:1px solid var(--tv-line);vertical-align:top}.dr-tb tr:last-child td{border-bottom:0}
  .dr-tb td a{color:var(--tv-link,#92400E);font-weight:600}.dr-tb .dr-open{white-space:nowrap}
  .dr-tb tbody tr:hover{background:var(--tv-field)}
  .dr-cm{position:absolute;z-index:45;width:min(17rem,calc(100vw - 6px));background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.7rem;box-shadow:var(--tv-shadow-lg,0 12px 30px -12px rgba(0,0,0,.35));padding:.45rem}
  .dr-cm>button,.dr-cm label{display:flex;align-items:center;gap:.45rem;width:100%;border:0;background:none;padding:.35rem .4rem;border-radius:.4rem;font:500 .88rem var(--tv-font-ui);color:var(--tv-ink);cursor:pointer;text-align:left}
  .dr-cm>button:hover,.dr-cm label:hover{background:var(--tv-field)}
  .dr-cm input[type=search]{width:100%;box-sizing:border-box;margin:.3rem 0;border:1px solid var(--tv-line-strong);border-radius:.45rem;background:var(--tv-field);padding:.3rem .45rem;font:inherit;font-size:.86rem}
  .dr-cm .dr-cml{max-height:12rem;overflow:auto}.dr-cm label small{flex:none}.dr-cm small{color:var(--tv-meta)}.dr-cm hr{border:0;border-top:1px solid var(--tv-line);margin:.3rem 0}
  .dr-cm .dr-cmx{position:absolute;top:.3rem;right:.3rem;width:26px;height:26px;border-radius:999px;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);justify-content:center;padding:0}
  .dr-tnote{margin:.35rem .2rem 0;font-size:.8rem;color:var(--tv-meta)}
  /* the list beside the map (one side of the A/B test, computers only): numbered cards left, their pins right */
  @media (min-width:1024px){
    .dr-grid.side{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,44%);gap:.8rem;align-items:start}
    .dr-grid.side .dr-mapbox{position:sticky;top:calc(var(--ta-top,64px) + .5rem);height:calc(100vh - var(--ta-top,64px) - 1.5rem);min-height:380px}
    .dr-grid.side .dr-mapbar{display:flex;gap:.35rem}
  }
  .dr-sarea{background:var(--tv-btn,#92400E)!important;color:var(--tv-btn-text,#E4DCC5)!important;border-color:var(--tv-btn,#92400E)!important}
  @media (max-width:767px){.dr-view [data-view=table]{display:none}   /* a phone keeps List | Map */
    .dr-ctl{gap:.35rem}.dr-srt>span{display:none}.dr-srt select{max-width:9.5rem}.dr-fb{padding:.36rem .6rem}   /* Filters, Arrange and Sort fit one line at 390 */
    .dr-hd{padding-right:2.6rem}   /* the name, badges and score chip stay clear of the Link button */
    .dr-adl{position:fixed;left:3px;right:3px;width:auto;top:auto;bottom:3px;max-height:calc(100vh - 7rem)}}   /* Arrange rises from the foot on a phone */
"""
TABLE_BTN = '<button type="button" data-view="table" aria-pressed="false" title="Show as a table: sort and filter each column"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16"/></svg>Table</button>\n    '
ARR = ('  <details class="dr-fdd dr-add" id="dr-arr"><summary class="dr-fb" title="Arrange the results: how many, what shows, each card\'s details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>Arrange <i aria-hidden="true">&#9662;</i></summary>\n'
       '    <div class="dr-adl" id="dr-adl" role="group" aria-label="Arrange"></div></details>\n')
AB_BLK = r"""  // the A/B test (Jason, 2026-11-19: "build it each way"): on a computer, half see the list beside the map, half the
  // List | Table | Map switch; each device keeps its side, Arrange can change it, and only the counts are kept ("gl-ab:…")
  var wide = window.matchMedia('(min-width: 1024px)');
  var AB = ''; try { AB = localStorage.getItem('tv_dir_ab') || ''; } catch (x) {}
  if (AB !== 'beside' && AB !== 'switch') { AB = Math.random() < .5 ? 'beside' : 'switch'; try { localStorage.setItem('tv_dir_ab', AB); } catch (x) {} }
  function abCount(what) { if (wide.matches && window.tvCount) tvCount({ m: 'act', k: 'gl-ab:' + AB + (what ? ':' + what : '') }); }
  function beside() { return AB === 'beside' && wide.matches && view === 'list'; }
"""
JS_ARRANGE = r"""
  // ---- Arrange (Jason, 2026-11-19, "Build as shown"): per page, Show, the details on each card in order, the computer's
  // layout. Kept on this device only (localStorage), never sent with who. ----
  var DET = { kind: 'Kind', dist: 'Distance', hours: 'Hours and status', addr: 'Address', phone: 'Phone number', web: 'Website', email: 'Email' };
  var DET_DEF = ['kind', 'dist', 'hours', 'addr', 'phone'];
  var AR = { det: DET_DEF.slice(), closed: true };
  try { var ar0 = JSON.parse(localStorage.getItem('tv_dir_arrange') || 'null'); if (ar0) { if (Array.isArray(ar0.det)) AR.det = ar0.det.filter(function (k) { return DET[k]; }); if (ar0.closed === false) AR.closed = false; } } catch (x) {}
  function saveAR() { try { localStorage.setItem('tv_dir_arrange', JSON.stringify(AR)); } catch (x) {} }
  function paintArrange() {
    var el = $('dr-adl'); if (!el) return;
    var off = Object.keys(DET).filter(function (k) { return AR.det.indexOf(k) < 0; });
    el.innerHTML = '<button type="button" class="dr-fx" data-arr-x aria-label="Close" title="Close">&times;</button>'
      + '<h4>Results per page</h4><div class="dr-pills">' + [10, 25, 50, 100].map(function (n) { return '<button type="button" data-per="' + n + '" aria-pressed="' + (pg.per === n) + '">' + n + '</button>'; }).join('') + '</div>'
      + '<h4>Show</h4><label class="dr-sw"><input type="checkbox" data-ar-closed' + (AR.closed ? ' checked' : '') + '><span>Businesses closed for good<small>Shown last and marked Closed. Untick to hide them for you.</small></span></label>'
      + '<h4>On each card</h4><p class="dr-adn">The first three share the top line. Drag to reorder, or use ↑ ↓.</p><ol class="dr-dl" id="dr-dl">'
      + AR.det.map(function (k, i) { return '<li draggable="true" data-det="' + k + '"><i aria-hidden="true">⋮⋮</i><span>' + DET[k] + '</span>'
          + '<button type="button" data-mv="-1" aria-label="Move ' + DET[k] + ' up"' + (i ? '' : ' disabled') + '>↑</button><button type="button" data-mv="1" aria-label="Move ' + DET[k] + ' down"' + (i < AR.det.length - 1 ? '' : ' disabled') + '>↓</button>'
          + '<button type="button" data-rm aria-label="Remove ' + DET[k] + ' from the cards" title="Remove">&times;</button></li>'; }).join('') + '</ol>'
      + (off.length ? '<select class="dr-dadd" data-ar-add aria-label="Add a detail"><option value="">+ Add a detail</option>' + off.map(function (k) { return '<option value="' + k + '">' + DET[k] + '</option>'; }).join('') + '</select>' : '')
      + '<h4>On a computer</h4><div class="dr-pills"><button type="button" data-ab="beside" aria-pressed="' + (AB === 'beside') + '">List beside the map</button><button type="button" data-ab="switch" aria-pressed="' + (AB === 'switch') + '">List, Table or Map</button></div>'
      + (wide.matches ? '' : '<p class="dr-adn">A phone always shows List or Map.</p>')
      + '<button type="button" class="dr-ard" data-ar-reset>Put it all back as it was</button>';
  }
  (function () {
    var d = $('dr-arr'); if (!d) return;
    d.addEventListener('toggle', function () { if (d.open) { closePop(); var f = $('dr-fdd'); if (f) f.removeAttribute('open'); paintArrange(); } });
    document.addEventListener('click', function (e) { if (d.open && e.target.isConnected && !d.contains(e.target)) d.removeAttribute('open'); });   // a button the panel just redrew isn't "outside"
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && d.open) { d.removeAttribute('open'); d.querySelector('summary').focus(); } });
    var el = $('dr-adl'), dragK = '';
    el.addEventListener('click', function (e) {
      var t = e.target;
      if (t.closest('[data-arr-x]')) { d.removeAttribute('open'); return; }
      var per = t.closest('[data-per]');
      if (per) { pg.per = +per.getAttribute('data-per'); try { localStorage.setItem('tvp:directory', String(pg.per)); } catch (x) {} paintArrange(); search(true); return; }
      var ab = t.closest('[data-ab]');
      if (ab) { AB = ab.getAttribute('data-ab'); try { localStorage.setItem('tv_dir_ab', AB); } catch (x) {} abCount('pick'); paintArrange(); setView(view === 'map' && AB === 'beside' ? 'list' : view); return; }
      var li = t.closest('li[data-det]'), k = li && li.getAttribute('data-det'), i = AR.det.indexOf(k);
      if (li && t.closest('[data-mv]')) { var j = i + +t.closest('[data-mv]').getAttribute('data-mv'); if (j >= 0 && j < AR.det.length) { AR.det.splice(i, 1); AR.det.splice(j, 0, k); saveAR(); paintArrange(); redraw(); var b = el.querySelector('li[data-det="' + k + '"] [data-mv="' + (j < i ? -1 : 1) + '"]'); if (b && !b.disabled) b.focus(); } return; }
      if (li && t.closest('[data-rm]')) { AR.det.splice(i, 1); saveAR(); paintArrange(); redraw(); return; }
      if (t.closest('[data-ar-reset]')) { AR = { det: DET_DEF.slice(), closed: true }; saveAR(); pg.per = 10; try { localStorage.removeItem('tvp:directory'); } catch (x) {} paintArrange(); search(true); }
    });
    el.addEventListener('change', function (e) {
      if (e.target.hasAttribute('data-ar-closed')) { AR.closed = e.target.checked; saveAR(); search(true); }
      if (e.target.hasAttribute('data-ar-add') && e.target.value) { AR.det.push(e.target.value); saveAR(); paintArrange(); redraw(); }
    });
    el.addEventListener('dragstart', function (e) { var li = e.target.closest('li[data-det]'); if (!li) return; dragK = li.getAttribute('data-det'); li.classList.add('drag'); try { e.dataTransfer.setData('text/plain', dragK); e.dataTransfer.effectAllowed = 'move'; } catch (x) {} });
    el.addEventListener('dragend', function () { dragK = ''; el.querySelectorAll('li.drag').forEach(function (x) { x.classList.remove('drag'); }); });
    el.addEventListener('dragover', function (e) { if (dragK && e.target.closest('li[data-det]')) e.preventDefault(); });
    el.addEventListener('drop', function (e) {
      var li = e.target.closest('li[data-det]'); if (!li || !dragK) return; e.preventDefault();
      var to = AR.det.indexOf(li.getAttribute('data-det')), from = AR.det.indexOf(dragK); if (to < 0 || from < 0 || to === from) return;
      AR.det.splice(from, 1); AR.det.splice(to, 0, dragK); dragK = ''; saveAR(); paintArrange(); redraw();
    });
  })();
  function redraw() { if (view === 'table') paintTable(); else paintList(); if (beside()) pins(S.items); }
  // a card's details, in the person's order: the first three on the top line, the rest under it
  function details(l, p, street) {
    var ht = l.hours_text || '', cut = ht.indexOf(' · '), web = l.website ? String(l.website).replace(/^https?:\/\//i, '').replace(/\/$/, '') : '';
    var f = {
      kind: l.category ? esc(l.category) : '',
      dist: l.distance != null && p && p.point ? esc(l.distance + ' mi') : '',
      hours: ht ? '<span class="' + (l.open_now ? 'dr-open' : 'dr-closed') + '">' + esc(cut > 0 ? ht.slice(0, cut) : ht) + '</span>' + (cut > 0 ? ' · ' + esc(ht.slice(cut + 3)) : '') : '',
      addr: (l.address ? esc(l.address) + (l.city && String(l.address).indexOf(l.city) < 0 ? ', ' + esc(l.city) : '')
          : (l.private_location ? 'Location kept private for the safety of the people it shelters. Call to get help.' : esc([l.city, l.state].filter(Boolean).join(', '))))
        + (l.address && !street && !l.private_location ? ' <span class="dr-mail">Mailing address only</span>' : ''),
      phone: l.phone ? esc(l.phone) : '',
      web: web ? esc(web) : '',
      email: l.email ? esc(l.email) : ''
    };
    var a = AR.det.map(function (k) { return f[k]; }).filter(Boolean);
    return (a.length ? '<div class="dr-m">' + a.slice(0, 3).join(' · ') + '</div>' : '') + (a.length > 3 ? '<div class="dr-m">' + a.slice(3).join(' · ') + '</div>' : '');
  }

  // ---- the Table view: the same results as rows; each column's menu sorts and filters the rows shown ----
  var TC = [['name', 'Name'], ['kind', 'Kind'], ['dist', 'Distance'], ['status', 'Status'], ['town', 'Town'], ['phone', 'Phone']];
  var TF = {}, TS = null;   // filters: column → { words, off: {value: 1} }; sort: [column, 1|-1]
  function cell(l, c) {
    if (c === 'name') return l.business_name || '';
    if (c === 'kind') return l.category || '';
    if (c === 'dist') return l.distance != null ? +l.distance : '';
    if (c === 'status') return l.closed ? 'Closed for good' : (l.hours_text ? (l.open_now ? 'Open' : 'Closed now') : 'Hours not listed');
    if (c === 'town') return [l.city, l.state].filter(Boolean).join(', ');
    if (c === 'phone') return l.phone || '';
    return '';
  }
  function tRows() {
    var rows = S.items.map(function (l, i) { return { l: l, i: i }; }).filter(function (r) {
      return Object.keys(TF).every(function (c) { var f = TF[c], v = String(cell(r.l, c)); return !(f.off && f.off[v]) && !(f.words && v.toLowerCase().indexOf(f.words.toLowerCase()) < 0); });
    });
    if (TS) rows.sort(function (a, b) { var x = cell(a.l, TS[0]), y = cell(b.l, TS[0]); if (x === '' && y !== '') return 1; if (y === '' && x !== '') return -1; return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y))) * TS[1]; });
    return rows;
  }
  function paintTable() {
    var p = place(), cols = TC.filter(function (c) { return c[0] !== 'dist' || (p && p.point); });
    pg.draw({ total: S.items.length ? S.total : 0, page: (S.first || 1) - 1, shown: S.items.length });
    if (!S.items.length) { paintList(); return; }
    var rows = tRows(), nf = Object.keys(TF).length;
    $('dr-list').innerHTML = '<div class="dr-tw"><table class="dr-tb"><thead><tr><th scope="col">#</th>' + cols.map(function (c) {
        return '<th scope="col"' + (TF[c[0]] ? ' class="f"' : '') + ' aria-sort="' + (TS && TS[0] === c[0] ? (TS[1] > 0 ? 'ascending' : 'descending') : 'none') + '"><button type="button" data-col="' + c[0] + '" aria-haspopup="dialog">' + c[1]
          + (TS && TS[0] === c[0] ? (TS[1] > 0 ? ' ↑' : ' ↓') : '') + (TF[c[0]] ? ' <b title="Filtered">(filtered)</b>' : '') + ' <b aria-hidden="true">▾</b></button></th>'; }).join('') + '</tr></thead><tbody>'
      + rows.map(function (r) { var l = r.l, tel = l.phone ? String(l.phone).replace(/[^\d+]/g, '') : '';
          return '<tr data-i="' + r.i + '"><td>' + (((S.first || 1) - 1) * pg.per + r.i + 1) + '</td>' + cols.map(function (c) {
            var v = cell(l, c[0]);
            if (c[0] === 'name') return '<td><a href="/listing/view.php?id=' + l.id + '">' + esc(v) + '</a>' + (l.is_verified ? '<span class="dr-badge">Verified</span>' : '') + '</td>';
            if (c[0] === 'phone') return '<td>' + (tel ? '<a href="tel:' + esc(tel) + '" data-out="call" data-e="' + l.id + '">' + esc(v) + '</a>' : '') + '</td>';
            if (c[0] === 'status') return '<td class="' + (v === 'Open' ? 'dr-open' : v === 'Closed now' ? 'dr-closed' : '') + '">' + esc(v) + '</td>';
            if (c[0] === 'dist') return '<td>' + (v === '' ? '' : esc(v + ' mi')) + '</td>';
            return '<td>' + esc(v) + '</td>'; }).join('') + '</tr>'; }).join('')
      + (rows.length ? '' : '<tr><td colspan="' + (cols.length + 1) + '">None of these ' + S.items.length + ' match the column filters. <button type="button" class="dr-lk" data-tf-clear>Clear them</button></td></tr>')
      + '</tbody></table></div><p class="dr-tnote">The column menus sort and filter the ' + S.items.length.toLocaleString() + ' shown here' + (S.total > S.items.length ? ' (of ' + S.total.toLocaleString() + '; Show more adds the next)' : '') + '.'
      + (nf ? ' <button type="button" class="dr-lk" data-tf-clear>Clear the column filters</button>' : '') + '</p>';
  }
  function closeCm() { var m = document.querySelector('.dr-cm'); if (m) { var b = document.querySelector('[data-col][aria-expanded="true"]'); m.remove(); if (b) { b.setAttribute('aria-expanded', 'false'); return b; } } return null; }
  function openCm(b) {
    var c = b.getAttribute('data-col'), was = b.getAttribute('aria-expanded') === 'true'; closeCm(); if (was) return;
    var n = {}; S.items.forEach(function (l) { var v = String(cell(l, c)); n[v] = (n[v] || 0) + 1; });
    var vals = Object.keys(n).sort(function (a, z) { return a === '' ? 1 : z === '' ? -1 : (c === 'dist' ? +a - +z : a.localeCompare(z)); }), f = TF[c] || {};
    var m = document.createElement('div'); m.className = 'dr-cm'; m.setAttribute('role', 'dialog'); m.setAttribute('aria-label', 'Sort and filter ' + b.textContent.replace(/[▾↑↓]|\(filtered\)/g, '').trim());
    m.innerHTML = '<button type="button" class="dr-cmx" data-cm="x" aria-label="Close" title="Close">&times;</button>'
      + '<button type="button" data-cm="up">' + (c === 'dist' ? 'Nearest first' : 'Sort A to Z') + '</button><button type="button" data-cm="down">' + (c === 'dist' ? 'Farthest first' : 'Sort Z to A') + '</button>'
      + (TF[c] ? '<button type="button" data-cm="clear">Clear this filter</button>' : '') + '<hr>'
      + '<input type="search" data-cm="words" placeholder="Search this column" aria-label="Search this column" value="' + esc(f.words || '') + '">'
      + '<div class="dr-cml">' + vals.slice(0, 60).map(function (v) { return '<label><input type="checkbox" data-cv="' + esc(v) + '"' + (f.off && f.off[v] ? '' : ' checked') + '> Show ' + esc(v === '' ? '(blank)' : (c === 'dist' ? v + ' mi' : v)) + ' <small>(' + n[v] + ')</small></label>'; }).join('') + '</div>';
    document.body.appendChild(m); b.setAttribute('aria-expanded', 'true');
    var r = b.getBoundingClientRect();
    m.style.top = (r.bottom + window.scrollY + 4) + 'px'; m.style.left = Math.max(3 + window.scrollX, Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - m.offsetWidth - 3)) + 'px';
    m.addEventListener('click', function (e) {
      var a = e.target.closest('[data-cm]'), k = a && a.getAttribute('data-cm');
      if (k === 'x') { var bb = closeCm(); if (bb) bb.focus(); }
      else if (k === 'up' || k === 'down') { TS = [c, k === 'up' ? 1 : -1]; closeCm(); paintTable(); }
      else if (k === 'clear') { delete TF[c]; closeCm(); paintTable(); }
    });
    m.addEventListener('change', function (e) {
      var cv = e.target.getAttribute('data-cv'); if (cv == null) return;
      var g = TF[c] || (TF[c] = {}); g.off = g.off || {}; if (e.target.checked) delete g.off[cv]; else g.off[cv] = 1;
      if (!Object.keys(g.off).length && !g.words) delete TF[c]; keepCm(c);
    });
    var tw; m.addEventListener('input', function (e) { if (e.target.getAttribute('data-cm') !== 'words') return; var v = e.target.value.trim(); clearTimeout(tw); tw = setTimeout(function () { var g = TF[c] || (TF[c] = {}); g.words = v; if (!v && !(g.off && Object.keys(g.off).length)) delete TF[c]; keepCm(c); }, 250); });
    var ws = m.querySelector('[data-cm="words"]'); if (ws) ws.focus();
  }
  function keepCm(c) {   // repaint the rows, keeping the menu open where it was
    var m = document.querySelector('.dr-cm'), top = m && m.style.top, left = m && m.style.left, inp = m && m.querySelector('[data-cm="words"]'), focusW = inp && document.activeElement === inp;
    if (m) m.remove(); paintTable();
    var b = document.querySelector('[data-col="' + c + '"]'); if (!b) return; openCm(b);
    var m2 = document.querySelector('.dr-cm'); if (m2 && top) { m2.style.top = top; m2.style.left = left; } if (m2 && !focusW) { var w = m2.querySelector('[data-cm="words"]'); if (w) w.blur(); }
  }
  $('dr-list').addEventListener('click', function (e) {
    var b = e.target.closest('[data-col]'); if (b) { openCm(b); return; }
    if (e.target.closest('[data-tf-clear]')) { TF = {}; closeCm(); paintTable(); }
  });
  document.addEventListener('click', function (e) { if (e.target.isConnected && !e.target.closest('.dr-cm,[data-col]')) closeCm(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var b = closeCm(); if (b) b.focus(); } });
"""

patch(D, [
 (OLD_MORE, ''),   # dead: the old Show more button (js/tv-pager.js draws it now)
 ("  @media (max-width: 767px){.dr-it{border-radius:.5rem}}   /* edge to edge: the phone standard (#dr-list is tv-bleed) */\n",
  "  @media (max-width: 767px){.dr-it{border-radius:.5rem}}   /* edge to edge: the phone standard (#dr-list is tv-bleed) */\n" + CSS),
 ('    <button type="button" data-view="map" aria-pressed="false">', TABLE_BTN + '<button type="button" data-view="map" aria-pressed="false">'),
 ('  <label class="dr-srt"><span>Sort</span>', ARR + '  <label class="dr-srt"><span>Sort</span>'),
 ('<div class="dr-mapbar"><button type="button" id="dr-mapmore" hidden>Show more</button></div>',
  '<div class="dr-mapbar"><button type="button" id="dr-sarea" class="dr-sarea" hidden>Search this area</button><button type="button" id="dr-mapmore" hidden>Show more</button><button type="button" id="dr-mapfull" hidden title="Open the full map">Full map</button></div>'),
 # the view: list, table or map; the list beside the map on a computer when this device is on that side of the test
 ("  try { view = localStorage.getItem('tv_dir_view') === 'map' ? 'map' : 'list'; } catch (x) {}",
  AB_BLK + "  try { var v0 = localStorage.getItem('tv_dir_view'); view = v0 === 'map' || v0 === 'table' ? v0 : 'list'; } catch (x) {}"),
 ("  function setView(v) {\n    if (v === 'map') { openMap(); return; }\n    view = v;\n",
  "  function setView(v) {\n    if (v === 'table' && !wide.matches && window.matchMedia('(max-width: 767px)').matches) v = 'list';   // a phone keeps List | Map\n"
  "    if (v === 'map') { if (window.tvCount) tvCount({ m: 'act', k: 'gl-view:map' }); abCount('map'); openMap(); return; }\n"
  "    var was = view; view = v; closeCm();\n    if (v !== was && was && window.tvCount) tvCount({ m: 'act', k: 'gl-view:' + v });\n"),
 ("    document.querySelectorAll('.dr-view [data-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === v)); });\n"
  "    $('dr-grid').classList.toggle('map', v === 'map');\n    document.querySelector('.dr-mapbox').hidden = v !== 'map';\n",
  "    document.querySelectorAll('.dr-view [data-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === v)); });\n"
  "    $('dr-grid').classList.toggle('map', v === 'map');\n    var sd = beside(); $('dr-grid').classList.toggle('side', sd);\n"
  "    document.querySelector('.dr-mapbox').hidden = v !== 'map' && !sd; $('dr-mapfull').hidden = !sd;\n"
  "    if ((v !== was || sd) && S.items.length) redraw();\n"),
 ("    if (v === 'map') pins(S.items);\n  }\n",
  "    if (v === 'map') pins(S.items);\n  }\n  wide.addEventListener && wide.addEventListener('change', function () { if (view !== 'map') setView(view); });   // the window crossed the computer width\n"
  "  $('dr-mapfull').addEventListener('click', function () { openMap(); });\n"),
 # the search asks for closed-for-good hidden when the person said so; keeps the centre for the ring
 ("    if (S.town) a.push('in_town=' + encodeURIComponent(S.town));\n    if (!forMap",
  "    if (S.town) a.push('in_town=' + encodeURIComponent(S.town));\n    if (!AR.closed) a.push('closed=hide');   // Arrange → Show (this person only)\n    if (!forMap"),
 ("      if (S.page === (S.first || 1)) { S.offers = d.offers || []; S.meant = d.meant || []; S.read = d.read || []; }",
  "      if (S.page === (S.first || 1)) { S.offers = d.offers || []; S.meant = d.meant || []; S.read = d.read || []; S.center = d.center || null; }"),
 ("      pg.busy(false); paintList(); paintChips(); paintSentence(); paintSug();   // sort shows once there's more than one\n      if (view === 'map') pins(S.items);",
  "      pg.busy(false); if (view === 'table') paintTable(); else paintList(); paintChips(); paintSentence(); paintSug();   // sort shows once there's more than one\n      if (view === 'map' || beside()) pins(S.items);"),
 # the card's details follow Arrange
 ("        + '<div class=\"dr-m\">' + esc([l.category, l.distance != null && p && p.point ? l.distance + ' mi' : ''].filter(Boolean).join(' · '))\n"
  "          + (ht ? ' · <span class=\"' + (l.open_now ? 'dr-open' : 'dr-closed') + '\">' + esc(cut > 0 ? ht.slice(0, cut) : ht) + '</span>' + (cut > 0 ? ' · ' + esc(ht.slice(cut + 3)) : '') : '') + '</div>'\n"
  "        + '<div class=\"dr-m\">' + (l.address ? esc(l.address) + (l.city && String(l.address).indexOf(l.city) < 0 ? ', ' + esc(l.city) : '')\n"
  "            : (l.private_location ? 'Location kept private for the safety of the people it shelters. Call to get help.' : esc([l.city, l.state].filter(Boolean).join(', '))))\n"
  "          + (l.address && !street && !l.private_location ? ' <span class=\"dr-mail\">Mailing address only</span>' : '')\n"
  "          + (l.phone ? ' · ' + esc(l.phone) : '') + '</div>'\n",
  "        + details(l, p, street)   // the details in the person's order (Arrange, 2026-11-19); by default: kind · distance · hours, then address · phone\n"),
 ("  // ---- the map beside the results ----\n  var map = null, layer = null, marks = [], loading = null;",
  JS_ARRANGE + "\n  // ---- the map beside the results ----\n  var map = null, layer = null, marks = [], loading = null, ring = null, quiet = false;"),
 # the ring and Search this area
 ("      layer.clearLayers(); marks = [];\n",
  "      layer.clearLayers(); marks = [];\n"
  "      if (ring) { map.removeLayer(ring); ring = null; }\n"
  "      var pc = place(), cen = S.center;   // the distance ring: how far the sentence says, round the place searched\n"
  "      if (pc && pc.point && cen && cen.lat != null && S.radius < 250) ring = L.circle([+cen.lat, +cen.lon], { radius: S.radius * 1609.34, color: '#92400E', weight: 2, dashArray: '6 6', fill: true, fillOpacity: .04, interactive: false }).addTo(map);\n"),
 ("      if (pts.length) map.fitBounds(pts, { paddingTopLeft: [60, 120], paddingBottomRight: [60, 60], maxZoom: stacked ? 17 : 13 });",
  "      quiet = true; $('dr-sarea').hidden = true;\n"
  "      var top = beside() ? 40 : 120;\n"
  "      if (ring && view !== 'map') map.fitBounds(ring.getBounds(), { padding: [30, 30] });\n"
  "      else if (pts.length) map.fitBounds(pts, { paddingTopLeft: [60, top], paddingBottomRight: [60, 60], maxZoom: stacked ? 17 : 13 });\n"
  "      setTimeout(function () { quiet = false; }, 700);\n"
  "      if (!map._drArea) { map._drArea = 1; map.on('moveend', function () { if (!quiet && beside()) $('dr-sarea').hidden = false; }); }"),
 ("html: '<span></span><b>' + (i + 1) + '</b>'", "html: '<span></span><b>' + (((S.first || 1) - 1) * pg.per + i + 1) + '</b>'"),
 ("'<div class=\"dr-pop\"><b>' + (i + 1) + '. '", "'<div class=\"dr-pop\"><b>' + (((S.first || 1) - 1) * pg.per + i + 1) + '. '"),
 ("        m.on('click', function () { hi(i, false); });",
  "        m.on('click', function () { hi(i, beside()); abCount('pin'); });"),
 ("  $('dr-mapmore').addEventListener('click', function () { S.page++; search(false); });",
  "  $('dr-mapmore').addEventListener('click', function () { S.page++; search(false); });\n"
  "  // Search this area (the list beside the map): the map's middle and the distance to its corner become the place\n"
  "  $('dr-sarea').addEventListener('click', function () {\n"
  "    if (!map) return; var c = map.getCenter(), ne = map.getBounds().getNorthEast();\n"
  "    var mi = Math.max(1, Math.round(Math.min(c.distanceTo(L.latLng(ne.lat, c.lng)), c.distanceTo(L.latLng(c.lat, ne.lng))) / 1609.34));   // to the nearer edge: what shows on the map\n"
  "    urlPlace = { lat: +c.lat.toFixed(4), lon: +c.lng.toFixed(4), near: 'this area' }; localName = ''; S.radius = Math.min(250, mi); S.town = '';\n"
  "    this.hidden = true; abCount('area'); search(true);\n"
  "  });\n"
  "  // the test's counts: which side this device is on, and what was done there (never who)\n"
  "  document.addEventListener('click', function (e) { var t = e.target.closest && e.target.closest('#dr-list a, #dr-list [data-tv-peek], #dr-list [data-out]'); if (t) abCount(t.hasAttribute('data-out') ? 'out' : 'open'); }, true);\n"
  "  window.addEventListener('load', function () { abCount(''); });"),
 # start: a table remembered opens as a table
 ("    setView('list'); if (view === 'map') openMap();   // the map was last used here: open it again",
  "    var v1 = view; view = ''; setView(v1 === 'table' ? 'table' : 'list'); if (v1 === 'map') { view = 'map'; openMap(); }   // the view last used here: open it again"),
])
print('built')
