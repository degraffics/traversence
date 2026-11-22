// Checks the Workflows page (step3/board/workflows.html, and the board's Workflows view in index.html) at 1280 and 390:
// 15 workflow gauges, each one score gauge (Ring and Arc) with its stage pill and its band within 1% of its score, no needle;
// every workflow's own page and its three paths open and close by ×, Esc and a click outside, with Back; the filters; the case's
// six steps; kept/moved/dropped; no sideways scroll; no console errors; the validator on the standalone page.
const { chromium } = require('playwright');
const { ringProblems } = require('./ring-check.cjs');
const dir = '/home/user/traversence/handoffs/control-board-plan/step3/board/';
(async () => {
  const b = await chromium.launch();
  const out = []; let fails = 0;
  const ok = (c, m) => { out.push((c ? 'PASS ' : 'FAIL ') + m); if (!c) fails++; };
  for (const file of ['workflows.html', 'index.html']) {
    for (const w of [1280, 390]) {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } });
      const p = await ctx.newPage();
      const errs = [];
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      p.on('pageerror', e => errs.push(String(e)));
      await p.goto('file://' + dir + file);
      const nav = async () => { if (file === 'index.html') await p.click('.rail [data-nav="workflows"]'); };
      await nav();
      const tag = `${file} @${w}`;
      const sw = () => p.evaluate(() => document.documentElement.scrollWidth);
      const isOpen = id => p.evaluate(id => document.body.classList.contains('open') && !document.getElementById(id).hidden, id);
      const cards = await p.evaluate(() => [...document.querySelectorAll('#wp-grid .wfg')].map(c => [c.id, c.querySelectorAll('.sr').length, (c.querySelector('.band-word.stage') || {}).textContent, c.querySelectorAll('.btn.path').length, !!c.querySelector('.lt'), !!c.querySelector('.proposed')]));
      const STG = ['Planning', 'Phased Development', 'Audit & Review', 'Implementation', 'Monitoring'];
      ok(cards.length === 15 && cards.map(c => c[0]).join(',') === Array.from({ length: 15 }, (_, i) => 'wf-W' + i).join(',') && cards.every(c => c[1] === 1 && STG.includes(c[2]) && c[3] === 3 && c[4] && c[5]),
        `${tag}: W0–W14, each one score gauge with its stage pill, its state with a reason, a proposed measure and three paths (${cards.length})`);
      ok(await p.evaluate(() => { const g = id => document.querySelector('#wf-' + id + ' .band-word.stage').textContent; return g('W1') === 'Audit & Review' && g('W13') === 'Planning' && g('W14') === 'Planning' && /Paused by the system \(a trip\)/.test(document.querySelector('#wf-W1 .lt').textContent) && /Off by the system/.test(document.querySelector('#wf-W3 .lt').textContent); }),
        `${tag}: real states: W1 paused by the system (a trip), W3 off by the system; W13 and W14 in Planning`);
      for (const st of ['ring', 'arc']) {
        await p.click(`.wp-head [data-gstyle-set="${st}"]`);
        const rp = await p.evaluate(ringProblems);
        const s = await sw();
        ok(rp.style === st && rp.nbad === 0 && rp.needles === 0 && (st === 'ring' || rp.top50 >= 3) && s <= w,
          `${tag}: ${st}: every score gauge one ${st}, band within 1% of its score, number, label, stage pill; ${rp.needles} needles${st === 'arc' ? `; 50 at the top centre (${rp.top50})` : ''}; scrollWidth ${s}` + (rp.nbad ? ': ' + rp.bad.join(' | ') : ''));
        if (file === 'workflows.html') await p.screenshot({ path: `${dir}shots/workflows-${st}-${w}.png`, fullPage: true });
      }
      await p.click('.wp-head [data-gstyle-set="ring"]');
      // every workflow's own page and its three paths: open, and close by ×
      let opened = 0, closedX = 0, wide = 0;
      for (let i = 0; i < 15; i++) {
        for (const pre of ['p-wfp-', 'p-wfa-', 'p-wfc-', 'p-wfr-']) {
          const id = pre + 'W' + i;
          await p.evaluate(id => { const a = document.createElement('a'); a.setAttribute('data-open-panel', id); a.hidden = true; document.body.appendChild(a); a.click(); a.remove(); }, id);
          if (await isOpen(id)) opened++;
          const r = await p.evaluate(id => Math.round(document.getElementById(id).getBoundingClientRect().right), id);
          if (r > w + 1) wide++;
          await p.click(`#${id} .x`);
          if (!(await p.evaluate(() => document.body.classList.contains('open')))) closedX++;
        }
      }
      ok(opened === 60 && closedX === 60 && wide === 0, `${tag}: each workflow's own page and its three paths open (${opened} of 60) and close by × (${closedX}); none wider than the window`);
      // Esc and a click outside; the card opens its page; Back
      await p.click('#wf-W3 .wfg-open'); const o1 = await isOpen('p-wfp-W3');
      await p.keyboard.press('Escape'); const o2 = await isOpen('p-wfp-W3');
      await p.click('#wf-W3 .wfg-open'); await p.mouse.click(5, 450); const o3 = await isOpen('p-wfp-W3');
      ok(o1 && !o2 && !o3, `${tag}: a card opens its workflow's page, which closes by Esc and by a click outside`);
      await p.click('#wf-W1 .wfg-open'); await p.click('#p-wfp-W1 [data-open-panel="p-wfr-W1"]');
      const inR = await isOpen('p-wfr-W1');
      await p.click('#p-wfr-W1 [data-wf-back]'); const backOk = await isOpen('p-wfp-W1');
      await p.click('#p-wfp-W1 [data-wf-back]'); const closed = !(await p.evaluate(() => document.body.classList.contains('open')));
      ok(inR && backOk && closed, `${tag}: from W1's page to Registered Services and Back to W1's page; Back there closes it`);
      ok(await p.evaluate(() => { const a = document.getElementById('p-wfp-W1'); return /A gap against the model/.test(a.textContent) && /overture_bulk/.test(a.textContent) && a.querySelectorAll('.wp-t tbody tr').length >= 8; }),
        `${tag}: W1's page lists its tasks with their needs and switches, and names its gap against R87`);
      ok(await p.evaluate(() => /What a source down holds/.test(document.getElementById('p-wfp-W3').textContent) && /cc_home|per-site/.test(document.getElementById('p-wfp-W3').textContent)), `${tag}: W3's page says what a source down holds and links the case`);
      // filters
      const f = async g => { await p.click(`[data-wf-filter="${g}"]`); return p.evaluate(() => [...document.querySelectorAll('#wp-grid .wfg')].filter(c => !c.hidden).map(c => c.id.slice(3)).join(',')); };
      const att = await f('att'); const all1 = await f('att');
      const none = await f('none'); await f('none');
      ok(att === 'W1,W3' && all1.split(',').length === 15 && none.split(',').length === 9, `${tag}: Needs attention shows W1 and W3; pressed again every card; No open alarm shows 9 (${att}; ${none})`);
      // the case and kept/moved/dropped
      const cs = await p.evaluate(() => [...document.querySelectorAll('#wf-case .wp-cs')].map(li => li.querySelector('.live, .sctag').textContent));
      ok(cs.length === 6 && cs.filter(x => /^real/.test(x)).length >= 3 && cs.some(x => /illustration/.test(x)), `${tag}: the case in six steps, the real ones and the illustrations marked (${cs.join(' / ')})`);
      ok(await p.evaluate(() => { const d = document.querySelector('.wp-kmd'); return d && d.querySelectorAll('tbody tr').length === 13 && /8 kept, 2 moved, 3 dropped/.test(d.querySelector('summary').textContent); }), `${tag}: today's page: 8 kept, 2 moved, 3 dropped, each with its reason`);
      const s2 = await sw(); ok(s2 <= w, `${tag}: no sideways scroll (${s2})`);
      if (file === 'workflows.html') { await p.click('#wf-W3 .wfg-open'); await p.screenshot({ path: `${dir}shots/workflows-panel-${w}.png` }); await p.keyboard.press('Escape'); }
      ok(errs.length === 0, `${tag}: no console errors` + (errs.length ? ': ' + errs.slice(0, 3).join(' | ') : ''));
      await ctx.close();
    }
  }
  await b.close();
  const fs = require('fs');
  const size = fs.statSync(dir + 'workflows.html').size;
  ok(size < 300000, `workflows.html is ${size} bytes, under 300 KB`);
  const v = require('child_process').spawnSync('python3', [__dirname + '/board-validate.py', dir + 'workflows.html'], { encoding: 'utf8' });
  for (const line of v.stdout.trim().split('\n')) ok(line.startsWith('PASS'), 'validator (workflows.html): ' + line.replace(/^(PASS|FAIL) /, ''));
  console.log(out.join('\n')); console.log(fails ? `${fails} FAILED` : 'ALL PASS');
})();
