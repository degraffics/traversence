// Checks step3/board/index.html at 1280 and 390: each view's width, each panel's width and its three ways to close,
// every scenario switch, both drill-down chains to the root and back, the coverage panel, and the console.
const { chromium } = require('playwright');
const dir = '/home/user/traversence/handoffs/control-board-plan/step3/board/';
const url = 'file://' + dir + 'index.html';
(async () => {
  const b = await chromium.launch();
  const out = []; let fails = 0;
  const ok = (c, m) => { out.push((c ? 'PASS ' : 'FAIL ') + m); if (!c) fails++; };
  for (const w of [1280, 390]) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2 });
    const p = await ctx.newPage();
    const errs = [];
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    p.on('pageerror', e => errs.push(String(e)));
    await p.goto(url);
    await p.evaluate(() => { try { localStorage.clear(); } catch (e) {} });
    await p.reload();
    const sw = () => p.evaluate(() => document.documentElement.scrollWidth);
    const isOpen = id => p.evaluate(id => document.body.classList.contains('open') && !document.getElementById(id).hidden, id);
    // views
    for (const v of ['board', 'modules', 'workflows', 'diagnostics']) {
      await p.click(`.rail [data-nav="${v}"]`);
      if (v !== 'board') await p.evaluate(v => document.querySelectorAll('#view-' + v + ' details.mod').forEach(d => d.open = true), v);
      const s = await sw(); ok(s <= w, `@${w} view ${v}: scrollWidth ${s}`);
      await p.screenshot({ path: `${dir}shots/${v === 'board' ? 'gauge-board' : v}-${w}.png`, fullPage: v === 'board' });
    }
    // every scenario switch renders
    await p.click('.rail [data-nav="modules"]');
    const btns = await p.$$eval('.sc-b', bs => bs.map(x => [x.dataset.mod, x.dataset.scn]));
    let bad = 0;
    for (const [m, i] of btns) {
      await p.click(`.sc-b[data-mod="${m}"][data-scn="${i}"]`);
      const vis = await p.evaluate(([m, i]) => { const e = document.querySelector(`#view-modules .scn[data-mod="${m}"][data-scn="${i}"]`); return !!e && !e.hidden && e.querySelector('article.card') !== null && e.getBoundingClientRect().height > 50; }, [m, i]);
      if (!vis) { bad++; out.push(`  scenario not shown: ${m} ${i}`); }
    }
    ok(bad === 0, `@${w} ${btns.length} scenario switches render`);
    const s2 = await sw(); ok(s2 <= w, `@${w} modules with switches flipped: scrollWidth ${s2}`);
    // diagnostics follows the selection
    const follows = await p.evaluate(() => { const ms = [...document.querySelectorAll('#view-diagnostics .scn[data-mod="tavily"]')]; const last = ms[ms.length - 1]; return ms.length > 1 && !last.hidden && ms.slice(0, -1).every(x => x.hidden); });
    ok(follows, `@${w} Diagnostics follows the last scenario picked (Tavily's last state)`);
    // panels: coverage, manage, setup, both chains
    await p.click('.rail [data-nav="board"]');
    const panelCheck = async (opener, id, label) => {
      for (const how of ['x', 'esc', 'outside']) {
        await opener();
        const o1 = await isOpen(id);
        const s = await sw();
        const wide = await p.evaluate(id => Math.ceil(document.getElementById(id).getBoundingClientRect().right), id);
        if (how === 'x') { ok(o1 && s <= w && wide <= w, `@${w} ${label} opens; scrollWidth ${s}, panel right ${wide}`); if (w === 390 || label === 'chain 1, level 1') await p.screenshot({ path: `${dir}shots/panel-${id}-${w}.png` }); }
        if (how === 'x') await p.click(`#${id} .x`);
        if (how === 'esc') await p.keyboard.press('Escape');
        if (how === 'outside') await p.mouse.click(5, 450);
        ok(!(await isOpen(id)), `@${w} ${label} closes by ${how}`);
      }
    };
    await panelCheck(() => p.click('[data-open-panel="p-coverage"]'), 'p-coverage', 'coverage');
    await p.click('.sgh[data-group="0"]');
    await panelCheck(() => p.click('#rt-0 [data-manage]'), 'p-manage', 'Manage (first page)');
    await p.click('.rail [data-nav="modules"]');
    await p.evaluate(() => document.getElementById('mod-railway').open = true);
    await panelCheck(() => p.click('#mod-railway .scn:not([hidden]) [data-open-panel="p-setup-railway"]'), 'p-setup-railway', 'setup capture (Railway)');
    await p.click('.rail [data-nav="board"]');
    // coverage: no silent gap
    const cov = await p.evaluate(() => { const objs = [...document.querySelectorAll('.obj')]; const n = objs.filter(o => o.classList.contains('s-n')).length; const listed = document.querySelectorAll('#p-coverage ul')[0].children.length; return [n, listed, window.__PAGES.length, window.__PAGES.filter(x => x.state === 'n' && !x.status.startsWith('Coming')).length, document.querySelectorAll('#p-coverage ul')[1].children.length]; });
    ok(cov[0] === cov[1] && cov[3] === cov[4], `@${w} coverage names every gap: ${cov[1]} of ${cov[0]} objectives, ${cov[4]} of ${cov[3]} unchecked pages (of ${cov[2]})`);
    // chains: to the root and back, no dead end
    for (const c of ['c1', 'c2']) {
      const n = await p.$$eval(`aside[id^="p-${c}-"]`, a => a.length);
      await p.evaluate(c => { const q = document.querySelector(`.qbar [data-open-panel="p-${c}-0"]`); q.closest('details').open = true; }, c);
      await p.click(`.qbar [data-open-panel="p-${c}-0"]`);
      let okc = true;
      for (let i = 0; i < n; i++) {
        okc = okc && await isOpen(`p-${c}-${i}`);
        const s = await sw(); okc = okc && s <= w;
        const why = await p.$eval(`#p-${c}-${i} .why`, e => e.textContent.length > 20);
        const forward = await p.$(`#p-${c}-${i} .navb [data-open-panel="p-${c}-${i + 1}"]`);
        const root = await p.$(`#p-${c}-${i} .rootbox`);
        okc = okc && why && (forward || root);
        if (w === 390 && i === n - 1) await p.screenshot({ path: `${dir}shots/chain-${c}-root-390.png` });
        if (forward) await forward.click();
      }
      for (let i = n - 1; i > 0; i--) { await p.click(`#p-${c}-${i} .navb .back`); okc = okc && await isOpen(`p-${c}-${i - 1}`); }
      ok(okc, `@${w} chain ${c}: ${n} levels to the root and back, each with its why, no dead end`);
      await p.keyboard.press('Escape');
    }
    // no paragraph over about 120 characters at an objective's or a card's top level (details excluded)
    const long = await p.evaluate(() => {
      const BLK = 'DIV,P,UL,OL,LI,DL,DD,DT,TABLE,TR,TD,DETAILS,SECTION,ARTICLE,ASIDE,H2,H3';
      const leaf = e => { for (let c = e.firstElementChild; c; c = c.nextElementSibling) if (BLK.includes(c.tagName)) return false; return true; };
      const out = [];
      document.querySelectorAll('.obj *, article.card *').forEach(e => {
        if (e.closest('details:not(.mod)') && !e.closest('summary')) return;
        if (!BLK.includes(e.tagName) && !(e.parentElement && e.parentElement.classList.contains('spend'))) return;
        if (leaf(e)) { const t = e.textContent.replace(/\s+/g, ' ').trim(); if (t.length > 125) out.push(t.slice(0, 80)); return; }
        let run = '';
        const flush = () => { const t = run.replace(/\s+/g, ' ').trim(); if (t.length > 125) out.push('(inline) ' + t.slice(0, 80)); run = ''; };
        e.childNodes.forEach(n => { if (n.nodeType === 1 && BLK.includes(n.tagName)) flush(); else run += n.textContent; }); flush();
      });
      return out;
    });
    ok(long.length === 0, `@${w} no top-level paragraph over ~120 characters in objectives and cards` + (long.length ? `: ${long.length}, e.g. ${long.slice(0, 2).join(' | ')}` : ''));
    // each objective says "not checked yet" at most once at its top level (details excluded)
    const rep = await p.evaluate(() => [...document.querySelectorAll('.obj')].map(o => { const c = o.cloneNode(true); c.querySelectorAll('details').forEach(d => { const s = d.querySelector(':scope > summary'); d.replaceWith(s ? s.cloneNode(true) : document.createTextNode('')); }); return [o.id, (c.textContent.match(/not checked yet/g) || []).length]; }).filter(x => x[1] > 1));
    ok(rep.length === 0, `@${w} no objective says "not checked yet" more than once at its top level` + (rep.length ? ': ' + rep.map(x => x.join(' ')).join(', ') : ''));
    // the header: at 390 the readings sit behind one pill, and the header stays short
    const hh = await p.evaluate(() => Math.round(document.querySelector('.bhead').getBoundingClientRect().height));
    ok(hh <= 80, `@${w} the header is ${hh} px tall (two lines at most)`);
    if (w === 390) { for (const how of ['x', 'esc', 'outside']) { await p.click('.bhead details.rd > summary'); const o1 = await p.evaluate(() => document.querySelector('.bhead details.rd').open);
      if (how === 'x') await p.click('.bhead details.rd .px'); if (how === 'esc') await p.keyboard.press('Escape'); if (how === 'outside') await p.mouse.click(5, 600);
      ok(o1 && !(await p.evaluate(() => document.querySelector('.bhead details.rd').open)), `@390 the Readings pill opens and closes by ${how}`); await p.keyboard.press('Escape'); } }
    // gauge cards: every snapshot a gauge card with both paths and its band word; every path ends at a module card, "none" or "not mapped"
    const gc = await p.evaluate(() => { const cs = [...document.querySelectorAll('#view-board .gcard')]; return [cs.length, cs.filter(c => c.querySelector('[data-open-panel^="p-wf-"]') && c.querySelector('[data-open-panel^="p-sv-"]')).length,
      cs.filter(c => (c.querySelector('.band-word') || {}).textContent).length,
      cs.filter(c => { const id = c.id; return ['p-wf-' + id, 'p-sv-' + id].every(pid => { const e = document.querySelector('#' + pid + ' .pend'); return e && (e.querySelector('[data-go-mod]') || /none|not mapped/.test(e.textContent)); }); }).length]; });
    ok(gc[0] === 23 && gc[1] === 23 && gc[2] === 23 && gc[3] === 23, `@${w} every snapshot is a gauge card (${gc[0]}), with both paths (${gc[1]}), its band word (${gc[2]}), each path ending at a module, "none" or "not mapped" (${gc[3]})`);
    await p.click('.rail [data-nav="board"]');
    for (const ex of ['o-speed', 'o-complete', 'o-gather']) {
      let okx = true;
      await p.evaluate(id => document.getElementById(id).scrollIntoView({ block: 'center' }), ex);
      await p.click(`#${ex} .gh`); okx = okx && await isOpen('p-snap-' + ex);
      await p.click(`#p-snap-${ex} [data-open-panel="p-wf-${ex}"]`); okx = okx && await isOpen('p-wf-' + ex) && (await sw()) <= w;
      if (w === 390) await p.screenshot({ path: `${dir}shots/example-${ex}-workflows-390.png` });
      await p.click(`#p-wf-${ex} .navb .back`); okx = okx && await isOpen('p-snap-' + ex);
      await p.click(`#p-snap-${ex} [data-open-panel="p-sv-${ex}"]`); okx = okx && await isOpen('p-sv-' + ex) && (await sw()) <= w;
      if (w === 390) await p.screenshot({ path: `${dir}shots/example-${ex}-services-390.png` });
      const mod = await p.$(`#p-sv-${ex} .pend [data-go-mod]`);
      await p.click(`#p-sv-${ex} .navb .back`); okx = okx && await isOpen('p-snap-' + ex);
      await p.keyboard.press('Escape'); okx = okx && !(await isOpen('p-snap-' + ex));
      ok(okx && !!mod, `@${w} worked example ${ex}: card → snapshot → Workflows → back → Services → back, ending at a module card`);
    }
    // Fix it only where something is wrong; no path row repeats its status; Monitoring gauges coloured by their reading, with the word
    const rows = await p.evaluate(() => { let fixOnOk = 0, rep = 0; document.querySelectorAll('aside[id^="p-sv-"] .lvl, aside[id^="p-wf-"] .lvl').forEach(l => {
        const lt = l.querySelector('.lvrow .lt'); const words = lt ? lt.textContent.trim() : '';
        const fx = [...l.querySelectorAll('button')].some(b => /^Fix it/.test(b.textContent));
        if (fx && !(lt && /lt (r|a|h)/.test(lt.className))) fixOnOk++;
        const why = (l.querySelector('.why1') || {}).textContent || '';
        if (words && words.length > 4 && why.includes(words)) rep++; });
      return [fixOnOk, rep]; });
    ok(rows[0] === 0, `@${w} no "Fix it" on a Working row (${rows[0]} found)`);
    ok(rows[1] === 0, `@${w} no path row repeats its status text (${rows[1]} found)`);
    const rts = await p.evaluate(() => [...document.querySelectorAll('#view-board .gcard')].map(c => [c.id, c.classList.contains('mon'), c.querySelectorAll('.sr').length, (c.querySelector('.band-word') || {}).textContent, /setup: \d of 4/.test(c.textContent)]));
    const STG = ['Planning', 'Phased Development', 'Audit & Review', 'Implementation'];
    ok(rts.length === 23 && rts.every(x => x[1] ? x[2] === 1 : (x[2] === 1 && STG.includes(x[3]) && x[4])), `@${w} every runtime gauge under the lifecycle: below Monitoring its one score gauge is setup % in its stage's colour with its stage and "setup: n of 4"; at Monitoring its live reading (${rts.filter(x => x[1]).map(x => x[0]).join(', ')} at Monitoring)`);
    const cs3 = await p.evaluate(() => [...document.querySelectorAll('#view-board .gcard')].filter(c => c.querySelector('[data-open-panel^="p-wf-"]') && c.querySelector('[data-open-panel^="p-cs-"]') && c.querySelector('[data-open-panel^="p-sv-"]') && /Core Services/.test(c.textContent) && /Registered Services/.test(c.textContent)).length);
    await p.click('#o-gather [data-open-panel="p-cs-o-gather"]');
    const csok = await p.evaluate(() => { const a = document.getElementById('p-cs-o-gather'); return !a.hidden && a.querySelectorAll('[data-go-mod]').length >= 3; });
    await p.keyboard.press('Escape');
    ok(cs3 === 23 && csok, `@${w} every runtime gauge has three drill paths: Workflows, Core Services and Registered Services; Core Services opens our own parts (${cs3})`);
    await p.click('#o-speed [data-cfg]');
    ok(await isOpen('p-snap-o-speed') && await p.evaluate(() => /Configure: its setup/.test(document.getElementById('p-snap-o-speed').textContent)), `@${w} Configure opens the gauge's setup`);
    await p.keyboard.press('Escape');
    const tint = await p.evaluate(() => [...document.querySelectorAll('#view-board .gcard.mon')].map(c => {
        const st = ['g', 'a', 'r', 'n'].find(k => c.classList.contains('s-' + k)); const r = c.querySelector('.sr');
        return r && r.classList.contains('k-' + st) && c.querySelector('.band-word.s-' + st) ? 0 : c.id; }).filter(Boolean));
    const stg = await p.evaluate(() => [...document.querySelectorAll('#view-board .gcard.setupc')].filter(c => { const w = c.querySelector('.band-word.stage'); const k = (w.className.match(/st-(\w+)/) || [])[1]; return !c.querySelector('.sr.k-' + k); }).map(c => c.id));
    ok(tint.length === 0 && stg.length === 0, `@${w} every Monitoring score gauge takes its reading's colour (sage met, amber below, red a trip) with its word; every setup gauge takes its stage's colour` + (tint.length + stg.length ? ': ' + tint.concat(stg).join(', ') : ''));
    // every control works: quick jumps, role filters
    await p.keyboard.press('Escape');
    const qj = await p.$$eval('.qbar .qj', bs => bs.length);
    const qg = await p.$$eval('.qbar details.qg', ds => ds.map(d => [d.querySelector('summary').textContent, d.querySelectorAll('.qj').length]));
    ok(qg.length >= 4 && qg.every(g => g[1] > 0), `@${w} the quick-jump strip is grouped rollups: ${qg.map(g => g[0].replace('▾', '').trim()).join(' · ')}`);
    let qok = 0;
    for (let i = 0; i < qj; i++) {
      await p.click('.rail [data-nav="board"]');
      await p.evaluate(() => window.scrollTo(0, 0));
      const before = await p.evaluate(() => [document.body.classList.contains('open'), [...document.querySelectorAll('.view')].find(v => !v.hidden).id, scrollY]);
      await p.evaluate(i => { const q = document.querySelectorAll('.qbar .qj')[i]; q.closest('details').open = true; }, i);
      await p.click(`.qbar .qj >> nth=${i}`);
      const after = await p.evaluate(() => [document.body.classList.contains('open'), [...document.querySelectorAll('.view')].find(v => !v.hidden).id, scrollY]);
      if (after[0] !== before[0] || after[1] !== before[1] || after[2] !== before[2]) qok++;
      await p.keyboard.press('Escape');
    }
    ok(qok === qj, `@${w} every quick jump does something: ${qok} of ${qj}`);
    await p.click('.rail [data-nav="modules"]');
    const rf = [];
    for (const role of ['Host', 'Service', 'Source', 'all']) {
      await p.click('#view-modules details.fdrop > summary');
      await p.click(`#view-modules [data-role-f="${role}"]`);
      rf.push(await p.evaluate(r => { const ds = [...document.querySelectorAll('#view-modules [data-role]')]; const d = document.querySelector('#view-modules details.fdrop');
        return !d.open && (r === 'all' ? ds.every(d => !d.hidden) : ds.every(d => d.hidden === (d.getAttribute('data-role') !== r))); }, role));
    }
    ok(rf.every(Boolean), `@${w} the role filter is one dropdown button that shows only its role and closes (Hosts, Services, Sources, All)`);
    const fpills = await p.evaluate(() => document.querySelectorAll('.seg [data-role-f]').length);
    ok(fpills === 0, `@${w} no list keeps a row of filter pills (${fpills} found)`);
    // the Diagnostics rollup: grouped banners, each item opens its module
    await p.click('.rail [data-nav="diagnostics"]');
    const dr = await p.evaluate(() => { const gs = [...document.querySelectorAll('#view-diagnostics .drollup details.qg')]; return [gs.length, gs.reduce((a, g) => a + g.querySelectorAll('.qj').length, 0), document.querySelectorAll('#view-diagnostics details.mod.diag').length]; });
    ok(dr[0] >= 2 && dr[1] === dr[2], `@${w} Diagnostics opens with grouped rollups: ${dr[0]} groups holding ${dr[1]} of ${dr[2]} modules`);
    await p.click('#view-diagnostics .drollup details.qg > summary');
    await p.click('#view-diagnostics .drollup details.qg .qj');
    ok(await p.evaluate(() => [...document.querySelectorAll('#view-diagnostics details.mod.diag')].some(d => d.open)), `@${w} a rollup item opens its module in Diagnostics`);
    // the one command search: Ctrl+K and the header icon; modules, workflows, pages and snapshots; closes three ways
    await p.click('.rail [data-nav="board"]');
    await p.keyboard.press('Control+k');
    ok(await isOpen('p-search') && await p.evaluate(() => document.activeElement && document.activeElement.id === 'cmdq'), `@${w} Ctrl+K opens the search with its box focused`);
    const kinds = {};
    for (const [q, kind] of [['bluehost', 'Module'], ['W3', 'Workflow'], ['crawl/refresh', 'Page'], ['Pages load fast', 'Snapshot']]) {
      await p.fill('#cmdq', q);
      kinds[kind] = await p.evaluate(k => [...document.querySelectorAll('#cmdr .cmdi .ck')].some(x => x.textContent === k), kind);
    }
    ok(Object.values(kinds).every(Boolean), `@${w} the search finds a module, a workflow, a page and a snapshot: ${JSON.stringify(kinds)}`);
    await p.fill('#cmdq', 'W3'); await p.keyboard.press('Enter');
    ok(await p.evaluate(() => !document.getElementById('view-workflows').hidden && !document.body.classList.contains('open')), `@${w} Enter takes the first result (W3's row in Workflows)`);
    await p.click('.rail [data-nav="board"]');
    for (const how of ['x', 'esc', 'outside']) {
      await p.click('.bhead [data-search]');
      const o1 = await isOpen('p-search');
      if (how === 'x') await p.click('#p-search .x');
      if (how === 'esc') await p.keyboard.press('Escape');
      if (how === 'outside') await p.mouse.click(3, 450);
      const sws = await sw();
      ok(o1 && !(await p.evaluate(() => document.body.classList.contains('open'))) && sws <= w, `@${w} the header's search icon opens it, and it closes by ${how}; scrollWidth ${sws}`);
    }
    // one primary scrollbar: in every view, nothing but the page scrolls (no inner scroll box, either way)
    for (const v of ['board', 'modules', 'workflows', 'diagnostics']) {
      await p.click(`.rail [data-nav="${v}"]`);
      const inner = await p.evaluate(() => [...document.querySelectorAll('body *')].filter(e => { if (e.closest('aside.panel') || !e.offsetParent) return false; const cs = getComputedStyle(e);
        return (/(auto|scroll)/.test(cs.overflowY) && e.scrollHeight > e.clientHeight + 1) || (/(auto|scroll)/.test(cs.overflowX) && e.scrollWidth > e.clientWidth + 1); }).map(e => e.tagName.toLowerCase() + '.' + [...e.classList].join('.')).slice(0, 5));
      ok(inner.length === 0, `@${w} ${v}: one primary scrollbar, no inner scroll box` + (inner.length ? ': ' + inner.join(', ') : ''));
    }
    if (w === 390) {
      await p.click('.rail [data-nav="modules"]');
      const edge = await p.evaluate(() => [...document.querySelectorAll('#view-modules details.mod, #view-board .gcard')].filter(e => e.offsetParent).slice(0, 40).map(e => { const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(innerWidth - r.right)]; }));
      await p.click('.rail [data-nav="board"]');
      ok(edge.length > 0 && edge.every(([l, r]) => l <= 4 && r <= 4), `@390 edge to edge below 768px: module cards within 4px of each side (${edge.length} measured)`);
    }
    await p.click('.rail [data-nav="board"]');
    // the card opens by keyboard
    await p.focus('#o-pages .gopen'); await p.keyboard.press('Enter'); ok(await isOpen('p-snap-o-pages'), `@${w} a gauge card opens by keyboard`); await p.keyboard.press('Escape');
    // every detail, accordion and popover opens and closes
    const dt = await p.evaluate(() => { let n = 0, bad = 0; document.querySelectorAll('details').forEach(d => { const s = d.querySelector(':scope > summary'); if (!s) { bad++; return; } const was = d.open; s.click(); const a = d.open !== was; s.click(); const b2 = d.open === was; n++; if (!(a && b2)) bad++; }); return [n, bad]; });
    ok(dt[1] === 0, `@${w} every detail and accordion opens and closes: ${dt[0]} checked, ${dt[1]} failed`);
    for (const how of ['x', 'esc', 'outside']) {
      await p.click('.rail [data-nav="board"]');
      const pop = await p.$('#view-board details.pop');
      await (await pop.$('summary')).click();
      const o1 = await pop.evaluate(d => d.open);
      if (how === 'x') await (await pop.$('.px')).click();
      if (how === 'esc') await p.keyboard.press('Escape');
      if (how === 'outside') await p.mouse.click(5, 450);
      const o2 = await pop.evaluate(d => d.open);
      ok(o1 && !o2, `@${w} the Improve popover opens and closes by ${how}`);
    }
    // card heights, collapsed: every card under ~1,400 px at 1280; every fault card under ~1,800 px at 390 with zone 4's actions visible
    await p.click('.rail [data-nav="modules"]');
    const hs = await p.evaluate(() => {
      const out = [];
      document.querySelectorAll('#view-modules .scn').forEach(sc => {
        const mod = sc.closest('details.mod'); const was = [mod.open, sc.hidden];
        mod.open = true; sc.hidden = false;
        sc.querySelectorAll('details').forEach(d => d.open = false);
        const c = sc.querySelector('article.card');
        if (c) { const act = c.querySelector('.thread .act') || c.querySelector('.act');
          out.push([sc.dataset.mod + ':' + sc.dataset.scn, Math.round(c.getBoundingClientRect().height), /fault-/.test(c.className), !!act && !act.closest('details:not([open])')]); }
        mod.open = was[0]; sc.hidden = was[1];
      });
      return out;
    });
    const tallest = hs.reduce((a, x) => x[1] > a[1] ? x : a, ['', 0]);
    const faults = hs.filter(x => x[2]); const tallF = faults.reduce((a, x) => x[1] > a[1] ? x : a, ['', 0]);
    if (w === 1280) ok(hs.every(x => x[1] <= 1400), `@1280 every card collapsed under ~1,400 px: tallest ${tallest[0]} ${tallest[1]} px` + (hs.filter(x => x[1] > 1400).length ? '; over: ' + hs.filter(x => x[1] > 1400).map(x => x[0] + ' ' + x[1]).join(', ') : ''));
    if (w === 390) ok(faults.every(x => x[1] <= 1800 && x[3]), `@390 every fault card collapsed under ~1,800 px with zone 4's actions visible: tallest ${tallF[0]} ${tallF[1]} px, railway:2 ${(hs.find(x => x[0] === 'railway:2') || [])[1]} px` + (faults.filter(x => x[1] > 1800 || !x[3]).length ? '; over: ' + faults.filter(x => x[1] > 1800 || !x[3]).map(x => x[0] + ' ' + x[1]).join(', ') : ''));
    await p.click('.rail [data-nav="board"]');
    // layout toggle remembered
    if (w === 1280) {
      await p.click('[data-layout="rows"]'); await p.reload();
      ok(await p.evaluate(() => document.getElementById('pgrid').classList.contains('rows')), '@1280 the stacked layout is remembered');
      await p.click('[data-layout="grid"]');
    }
    ok(errs.length === 0, `@${w} no console errors` + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
    await ctx.close();
  }
  await b.close();
  // the HTML and CSS validator (ids, nesting, aria-labels, CSS duplicates, fault classes)
  const v = require('child_process').spawnSync('python3', [__dirname + '/board-validate.py'], { encoding: 'utf8' });
  for (const line of v.stdout.trim().split('\n')) ok(line.startsWith('PASS'), 'validator: ' + line.replace(/^(PASS|FAIL) /, ''));
  console.log(out.join('\n')); console.log(fails ? `${fails} FAILED` : 'ALL PASS');
})();
