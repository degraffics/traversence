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
      await p.screenshot({ path: `${dir}shots/${v}-${w}.png`, fullPage: v === 'board' });
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
      await p.click(`[data-open-panel="p-${c}-0"]`);
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
  console.log(out.join('\n')); console.log(fails ? `${fails} FAILED` : 'ALL PASS');
})();
