// Checks the 16 development gauges (step3/board/dev-gauges.html, and the Board view's Development rows in index.html) at 1280 and 390:
// 16 gauges, each with one needle; every gauge's panel and the Questions panel open and close by ×, Esc and a click outside; the
// other-face links open the other gauge's panel at that item; no sideways scroll; two gauges per row at 390; no console errors;
// the validator passes on the standalone page.
const { chromium } = require('playwright');
const dir = '/home/user/traversence/handoffs/control-board-plan/step3/board/';
(async () => {
  const b = await chromium.launch();
  const out = []; let fails = 0;
  const ok = (c, m) => { out.push((c ? 'PASS ' : 'FAIL ') + m); if (!c) fails++; };
  for (const file of ['dev-gauges.html', 'index.html']) {
    for (const w of [1280, 390]) {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } });
      const p = await ctx.newPage();
      const errs = [];
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      p.on('pageerror', e => errs.push(String(e)));
      await p.goto('file://' + dir + file);
      if (file === 'index.html') { await p.evaluate(() => { try { localStorage.clear(); } catch (e) {} }); await p.reload(); await p.click('.rail [data-nav="board"]'); }
      const tag = `${file} @${w}`;
      const g = await p.evaluate(() => [...document.querySelectorAll('.dg-g')].map(x => [x.dataset.gauge, x.querySelectorAll('.dg-needle').length, (x.querySelector('.dg-word') || {}).textContent, x.querySelector('.dg-pct').textContent, /configuration drafted: \d+ items, \d+ criteria, awaiting sign-off/.test(x.textContent)]));
      ok(g.length === 16 && g.every(x => x[1] === 1 && x[2] === 'Planning' && x[3] === '0%' && x[4]), `${tag}: 16 gauges, each with one needle, at 0 in Planning, "configuration drafted: N items, M criteria, awaiting sign-off" (${g.length} found)`);
      const STAGES = ['Planning', 'Phased Development', 'Audit & Review', 'Implementation', 'Monitoring'];
      await p.click('[data-dev-preview]');
      const pv = await p.evaluate(() => [...document.querySelectorAll('.dg-g')].map(x => [x.querySelector('.dg-word').textContent, x.querySelector('.dg-pct').textContent, x.querySelectorAll('.dg-needle').length, x.querySelector('.dg-needle').getAttribute('x2')]));
      ok(pv.every(x => STAGES.includes(x[0]) && /^\d+%$/.test(x[1]) && x[2] === 1) && pv.some(x => x[1] !== '0%'), `${tag}: the preview shows each gauge's draft % and stage, still one needle (${[...new Set(pv.map(x => x[0]))].join(', ')})`);
      const bd = [];
      const allWords = pv.map(x => x[0]).concat(bd.map(x => x[0]), g.map(x => x[2]));
      ok(!allWords.some(t => /Working|Less than optimal|Trip|Paused|Failing|Warning|Held|not checked|Behind|Under way|Nearly there/i.test(t)), `${tag}: build gauges use only Jason's five words, never the runtime words`);
      await p.click('[data-dev-preview]');
      ok(await p.evaluate(() => [...document.querySelectorAll('.dg-g')].every(x => x.querySelector('.dg-word').textContent === 'Planning' && x.querySelector('.dg-pct').textContent === '0%')), `${tag}: turning the preview off returns every gauge to Planning, 0%`);
      ok(await p.evaluate(() => document.querySelectorAll('.dg-grid')[0].querySelectorAll('.dg-g').length === 6 && document.querySelectorAll('.dg-grid')[1].querySelectorAll('.dg-g').length === 10), `${tag}: Front end 6, Back end 10`);
      ok(await p.evaluate(() => /One item not yet built keeps the whole gauge in Phased Development; the needle still shows progress/.test(document.querySelector('.dg').textContent)), `${tag}: the Phased Development line is on the page`);
      const rts = await p.evaluate(() => [...document.querySelectorAll('.dg-rtg')].map(g => [g.querySelectorAll('.dg-needle').length, g.querySelector('.dg-word').textContent, g.querySelector('.dg-rtk').textContent, g.querySelector('.dg-of').textContent]));
      ok(rts.length === 5 && rts.every(x => x[0] === 1) && rts.map(x => x[1]).join('|') === 'Planning|Phased Development|Audit & Review|Implementation|Monitoring' && rts[0][2] === 'today' && rts.slice(1).every(x => /illustration/.test(x[2])) && /target/.test(rts[4][3]),
        `${tag}: Pages load fast across the five stages, one needle each, Planning today and the rest illustrations, Monitoring on its reading against its target`);
      const ex = await p.evaluate(() => { const d = document.querySelector('.dg-ex'); return [...d.querySelectorAll('tbody tr')].map(r => r.cells[0].textContent.split(' ')[0] + ':' + r.cells[1].textContent); });
      ok(['R01–R15:passes', 'RENDER:passes', 'PATHS:passes', 'PATHS:fails', 'LOAD:no check yet', 'PHOTOS-SHOWN:no check yet', 'BRAND:partial', 'TOOLS:no check yet'].every(x => ex.includes(x)), `${tag}: Get Local's check list in full, with its "no check yet" rows (${ex.length} checks)`);
      ok(await p.evaluate(() => document.querySelectorAll('.dg-reg li').length === 25 && document.querySelectorAll('.dg-it .dg-ck').length > 200 && [...document.querySelectorAll('aside[id^="p-dev-"]')].filter(a => a.id !== 'p-dev-questions').every(a => [...a.querySelectorAll('button[disabled]')].some(b => /Run checks now/.test(b.textContent)))),
        `${tag}: the register (25 checks), each item's checks in its gauge's drawer, and Run checks now (drawn, inert) in every drawer`);
      ok(await p.evaluate(() => document.querySelectorAll('.dg-rev').length > 0 && /audited, awaiting sign-off/.test(document.body.textContent) && !/accepted at sign-off/.test(document.body.textContent)), `${tag}: "signed off" and "audited, awaiting sign-off" marks show; no "accepted at sign-off"`);
      ok(await p.evaluate(() => /Complete needs a path or function check; a page that only renders isn.t complete/.test(document.querySelector('.dg').textContent)), `${tag}: the check-level rule is on the page`);
      ok(await p.evaluate(() => /awaiting Jason.s sign-off/.test(document.querySelector('.dg').textContent) && /the stage of the work/.test(document.querySelector('.dg').textContent) && !document.querySelector('[data-dev-reading]')), `${tag}: the sign-off note and "the stage of the work" show; no reading switch`);
      if (w === 390) {
        const cols = await p.evaluate(() => { const gs = [...document.querySelectorAll('.dg-grid')[0].querySelectorAll('.dg-g')]; return new Set(gs.map(x => Math.round(x.getBoundingClientRect().top))).size; });
        ok(cols === 3, `${tag}: two gauges per row (${cols} rows for 6)`);
      }
      const sw0 = await p.evaluate(() => document.documentElement.scrollWidth);
      ok(sw0 <= w, `${tag}: no sideways scroll (${sw0})`);
      const isOpen = id => p.evaluate(id => { const e = document.getElementById(id); return !!e && !e.hidden && document.body.classList.contains('open'); }, id);
      const ids = await p.evaluate(() => [...document.querySelectorAll('.dg-g')].map(x => x.dataset.openPanel).concat(['p-dev-questions']));
      let good = 0, bars = 0;
      const ways = ['x', 'esc', 'outside'];
      for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        const sel = id === 'p-dev-questions' ? '.dg-q' : `.dg-g[data-open-panel="${id}"]`;
        await p.click(sel);
        const o1 = await isOpen(id);
        if (await p.evaluate(id => id === 'p-dev-questions' || ['conf', 'prog', 'mon'].every(t => document.querySelector('#' + id + ' [data-dev-tab="' + t + '"]')) && !!document.querySelector('#' + id + ' .nohist') && !!document.querySelector('#' + id + ' button[disabled]'), id)) bars++;
        const sw = await p.evaluate(() => document.documentElement.scrollWidth);
        const how = ways[i % 3];
        if (how === 'x') await p.click(`#${id} .x`);
        if (how === 'esc') await p.keyboard.press('Escape');
        if (how === 'outside') await p.mouse.click(2, 400);
        const o2 = await p.evaluate(id => !document.body.classList.contains('open'), id);
        if (o1 && o2 && sw <= w) good++; else out.push(`  ${tag} ${id} by ${how}: open ${o1}, closed ${o2}, width ${sw}`);
      }
      ok(good === ids.length, `${tag}: all ${ids.length} panels (16 gauges and Questions) open, fit and close by ×, Esc and a click outside: ${good} of ${ids.length}`);
      for (const how of ['x', 'esc', 'outside']) {
        await p.click('.dg-g >> nth=0'); const o1 = await isOpen('p-dev-discovery');
        if (how === 'x') await p.click('#p-dev-discovery .x'); if (how === 'esc') await p.keyboard.press('Escape'); if (how === 'outside') await p.mouse.click(2, 400);
        ok(o1 && !(await p.evaluate(() => document.body.classList.contains('open'))), `${tag}: Discovery's panel closes by ${how}`);
      }
      ok(bars === ids.length, `${tag}: each gauge's panel has Configuration, Progress and Monitoring tabs, an inert Sign off (Jason) and "No history yet"`);
      await p.click('.dg-g >> nth=1'); await p.click('#p-dev-directory [data-dev-tab="prog"]');
      ok(await p.evaluate(() => !document.getElementById('tp-directory-prog').hidden && document.getElementById('tp-directory-conf').hidden && !!document.querySelector('#tp-directory-prog .dg-bar')), `${tag}: the Progress tab opens with criteria met by phase`);
      await p.keyboard.press('Escape');
      await p.click('.dg-cfg >> nth=2'); await p.waitForTimeout(30);
      ok(await isOpen('p-dev-community') && await p.evaluate(() => !document.getElementById('tp-community-conf').hidden), `${tag}: Configure opens the gauge's drawer on its Configuration tab`);
      await p.keyboard.press('Escape');
      // other-face links: every one opens the other gauge's panel at that item
      const faces = await p.evaluate(() => [...document.querySelectorAll('.dg-face')].map(a => [a.closest('aside').id, a.dataset.openPanel, a.dataset.devItem]));
      let fok = 0;
      const sample = file === 'index.html' ? faces.filter((_, i) => i % 7 === 0) : faces;
      for (const [from, to, item] of sample) {
        await p.evaluate(id => { document.querySelectorAll('aside.panel').forEach(x => x.hidden = x.id !== id); document.body.classList.add('open'); const t = document.querySelector('#' + id + ' [data-dev-tab="conf"]'); if (t) t.click(); }, from);
        await p.click(`#${from} .dg-face[data-dev-item="${item}"]`);
        await p.waitForTimeout(60);
        const r = await p.evaluate(([to, item]) => { const e = document.getElementById(item); return !document.getElementById(to).hidden && !!e && e.closest('aside').id === to && e.classList.contains('hit'); }, [to, item]);
        if (r) fok++; else out.push(`  ${tag} face ${from} → ${to} ${item} failed`);
        await p.keyboard.press('Escape');
      }
      ok(sample.length > 0 && fok === sample.length, `${tag}: other-face links open the other gauge's panel at that item: ${fok} of ${sample.length}${file === 'index.html' ? ' (every 7th)' : ''}`);
      // Back returns to the panel the link came from
      const f0 = faces.find(x => x[0] !== x[1]);
      await p.evaluate(id => { document.querySelectorAll('aside.panel').forEach(x => x.hidden = x.id !== id); document.body.classList.add('open'); const t = document.querySelector('#' + id + ' [data-dev-tab="conf"]'); if (t) t.click(); }, f0[0]);
      await p.click(`#${f0[0]} .dg-face[data-dev-item="${f0[2]}"]`); await p.waitForTimeout(60);
      await p.click(`#${f0[1]} [data-dev-back]`); await p.waitForTimeout(60);
      ok(await isOpen(f0[0]), `${tag}: Back returns to the gauge the link came from`);
      await p.keyboard.press('Escape');
      ok(errs.length === 0, `${tag}: no console errors` + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
      if (file === 'dev-gauges.html') await p.screenshot({ path: `${dir}shots/dev-gauges-${w}.png`, fullPage: true });
      if (file === 'dev-gauges.html') { await p.click('.dg-g >> nth=15'); await p.screenshot({ path: `${dir}shots/dev-gauges-panel-${w}.png` }); await p.keyboard.press('Escape'); }
      await ctx.close();
    }
  }
  await b.close();
  const v = require('child_process').spawnSync('python3', [__dirname + '/board-validate.py', dir + 'dev-gauges.html'], { encoding: 'utf8' });
  for (const line of v.stdout.trim().split('\n')) ok(line.startsWith('PASS'), 'validator (dev-gauges.html): ' + line.replace(/^(PASS|FAIL) /, ''));
  console.log(out.join('\n')); console.log(fails ? `${fails} FAILED` : 'ALL PASS');
})();
