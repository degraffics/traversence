// The gauge panel as a drill-down (approved by Jason 2026-11-23; handoffs/gauge-panel.md; CLAUDE.md "Every action is a task"),
// rendered on the bench at 1280 and 390 with the gauges from the repo's own files:
//   - nothing in a gauge panel is inert: every row's name, every chip, every bar part and legend chip is a link or a button,
//     on a development panel and on a runtime one (Jason: "every single listed item is a dead end");
//   - one filled button per box; no Sign off on items; no red;
//   - the bar's parts and the legend filter the list, pressed again show all; the first 10 rows, then Show all;
//   - the dots' legend opens by ⓘ and closes by ×, Esc and a click outside;
//   - a row opens in place; a finding or a check opens over the panel with Back; its Fix it presses the row's;
//   - one cycle: Fix it → "Fixing: … · sent to the <gauge> build" with Undo, the button gone; Undo puts it back; no Next;
//     a Fix it pressed is kept (drawn again after a reload); Approve the scope → its line with Undo;
//   - a runtime panel: its setup in words, each step's link to its card above, its needs folded;
//   - nothing wider than the window.
// Run after the start schema and board-panels-seed.php: node gauge-panel.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const port = 8960 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')],
  { env: { ...process.env, ROOT: root, GAUGES_FILE: path.join(__dirname, '../../site-audit/results/gauges.json') }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
// an element that is text a person might press, but neither a link nor a button
const inert = (p, sel) => p.evaluate(s => [...document.querySelectorAll(s)].filter(e => e.getClientRects().length).flatMap(sp =>
  [...sp.querySelectorAll('.cbg-nm, .cbg-chip:not(.cbg-plain), .cbg-seg, .cbg-lg')].filter(e => !/^(A|BUTTON)$/.test(e.tagName)).map(e => e.outerHTML.slice(0, 90))), sel);
const red = (p, sel) => p.evaluate(s => [...document.querySelectorAll(s + ' *')].filter(e => e.getClientRects().length).map(e => getComputedStyle(e))
  .filter(c => [c.color, c.backgroundColor, c.borderLeftColor].some(x => x.replace(/\s/g, '') === 'rgb(166,43,43)')).length, sel);
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(1200);
      await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(500);
      await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector('#cb-sp-d-discovery-snap .cbg', { timeout: 15000 });
      const sp = '#cb-sp-d-discovery-snap';
      if (shots) await p.screenshot({ path: shots + '/gauge-dev-' + w + '.png' });
      t(L + 'Discovery: its bar reads Issues to fix · Not built yet · No check yet (Done hidden at 0)', (await p.$$eval(sp + ' .cbg-seg:not([hidden])', a => a.map(x => x.getAttribute('data-cbg-f')).join(' '))) === 'fail build check');
      t(L + 'nothing in the panel is inert text (names, chips, bar, legend)', !(await inert(p, sp)).length, await inert(p, sp));
      t(L + 'one filled button among the cards (Approve the scope), the rest outlined', (await p.$$eval(sp + ' .cbg-ac .cbg-b:not(.cbg-o)', a => a.map(x => x.textContent))).join('|') === 'Approve the scope');
      t(L + 'no Sign off anywhere on items, no red', (await p.$$eval(sp + ' button', a => a.filter(x => /sign off/i.test(x.textContent)).length)) === 0 && (await red(p, sp)) === 0);
      t(L + 'the first 10 rows, then Show all 30', (await p.$$eval(sp + ' .cbg-it', a => a.filter(x => x.getClientRects().length).length)) === 10 && /Show all 30/.test(await p.textContent(sp + ' [data-cbg-more]')));
      for (const f of ['fail', 'build', 'check']) {
        await p.click(sp + ' .cbg-seg[data-cbg-f="' + f + '"]');
        const vis = await p.$$eval(sp + ' .cbg-it', a => a.filter(x => x.getClientRects().length).map(x => x.getAttribute('data-cbg-b')));
        t(L + 'the bar\'s ' + f + ' part shows only its rows, all of them', vis.length > 0 && vis.every(x => x === f), vis);
        await p.click(sp + ' .cbg-seg[data-cbg-f="' + f + '"]');
      }
      await p.click(sp + ' .cbg-info'); t(L + 'the dots\' legend opens', await p.isVisible(sp + ' .cbg-dleg'));
      await p.keyboard.press('Escape'); t(L + '…Esc closes it, the panel stays', !(await p.isVisible(sp + ' .cbg-dleg')) && await p.isVisible(sp));
      await p.click(sp + ' .cbg-info'); await p.click(sp + ' .cbg-dlx'); t(L + '…× closes it', !(await p.isVisible(sp + ' .cbg-dleg')));
      await p.click(sp + ' .cbg-info'); await p.click(sp + ' .cbg-top'); t(L + '…a click outside closes it', !(await p.isVisible(sp + ' .cbg-dleg')));
      // an issue: open it, its evidence in place, every chip opens its view with Back
      await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]');
      const row = sp + ' .cbg-it[data-cbg-b="fail"] >> nth=0';
      await p.click(row + ' >> .cbg-ex');
      t(L + 'an issue opens in place: one sentence, its chips, one filled Fix it', /^The issue: /.test(await p.textContent(row + ' >> .cbg-st')) && (await p.locator(row).locator('.cbg-acts .cbg-b:not(.cbg-o)').count()) === 1);
      for (const c of await p.locator(row).locator('.cbg-chip[data-cbg-open]').all()) {
        await c.click(); const v = await p.locator(sp + ' .cbg-sub:not([hidden])').count();
        t(L + 'a chip opens its view over the panel', v === 1); await p.click(sp + ' .cbg-sub:not([hidden]) [data-cbg-back]');
      }
      if (shots) { await p.locator(row).locator('.cbg-chip[data-cbg-open]').first().click(); await p.screenshot({ path: shots + '/gauge-evidence-' + w + '.png' }); await p.click(sp + ' .cbg-sub:not([hidden]) [data-cbg-back]'); }
      // one cycle: Fix it → Fixing with Undo; Undo puts it back; no Next
      await p.locator(row).locator('[data-cbg="fix"]').click(); await p.waitForSelector(sp + ' .cbg-done', { timeout: 8000 });
      const line = await p.locator(row).locator('.cbg-done').textContent();
      t(L + 'Fix it → "Fixing: … · sent to the Discovery build", Undo beside it, the button gone', /^Fixing: .* · sent to the Discovery build/.test(line) && /Undo/.test(line) && !(await p.locator(row).locator('[data-cbg="fix"]').isVisible()), line);
      t(L + '…and no Next: the cycle ends there', !/Next/.test(line) && (await p.locator(row).locator('.cbg-done .cbg-b').count()) === 0);
      if (shots) await p.screenshot({ path: shots + '/gauge-fixing-' + w + '.png' });
      await p.locator(row).locator('.cbg-done [data-cbg="undo"]').click(); await p.waitForTimeout(600);
      t(L + 'Undo puts Fix it back', await p.locator(row).locator('[data-cbg="fix"]').isVisible() && (await p.locator(row).locator('.cbg-done').count()) === 0);
      // kept: pressed again, then the panel drawn again, the line is there
      await p.locator(row).locator('[data-cbg="fix"]').click(); await p.waitForSelector(sp + ' .cbg-done', { timeout: 8000 });
      await p.goto(home); await p.waitForTimeout(900); await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(400);
      await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector(sp + ' .cbg', { timeout: 15000 });
      await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]'); await p.click(row + ' >> .cbg-ex');
      t(L + 'a Fix it pressed is kept: drawn again with its Fixing line and Undo', /Fixing: /.test(await p.locator(row).locator('.cbg-done').textContent()));
      await p.locator(row).locator('.cbg-done [data-cbg="undo"]').click(); await p.waitForTimeout(500);
      // Approve the scope: its line in place, Undo
      await p.click(sp + ' [data-cbg="scope"]'); await p.waitForSelector(sp + ' .cbg-ac .cbg-done', { timeout: 8000 });
      t(L + 'Approve the scope → "Scope approved by …" with Undo, the button gone', /^Scope approved by /.test(await p.textContent(sp + ' .cbg-ac .cbg-done')) && !(await p.isVisible(sp + ' [data-cbg="scope"]')));
      await p.click(sp + ' .cbg-ac .cbg-done [data-cbg="undo"]'); await p.waitForTimeout(500);
      t(L + '…Undo puts Approve the scope back', await p.isVisible(sp + ' [data-cbg="scope"]'));
      t(L + 'the development panel: nothing wider than the window', await noSide(p));
      await p.click(sp + ' [data-cb-spx]');
      // a runtime gauge: Search finds what they came for
      await p.click('[data-cb-rail="deliver"]'); await p.waitForTimeout(400);
      await p.click('#cb-g-o-search .cb-gopen'); await p.waitForTimeout(400);
      const rs = '#cb-sp-o-search-snap';
      await p.waitForSelector(rs + ' [data-cb-gd][data-loaded]', { timeout: 15000 }).catch(() => {});
      if (shots) await p.screenshot({ path: shots + '/gauge-runtime-' + w + '.png' });
      t(L + 'runtime: What moves it, its setup in words (four rows)', /What moves it: \d of 4 setup steps done/.test(await p.textContent(rs + ' .cbg-top')) && (await p.$$eval(rs + ' .cbg-items .cbg-it', a => a.length)) === 4);
      t(L + 'runtime: one filled button among the cards, Approve the target with today\'s evidence', (await p.$$eval(rs + ' .cbg-ac .cbg-b:not(.cbg-o)', a => a.map(x => x.textContent))).join('|') === 'Approve the target' && /Today: /.test(await p.textContent(rs + ' .cbg-ac-sign')));
      await p.click(rs + ' [data-cbg-to] >> nth=0'); await p.waitForTimeout(400);
      t(L + 'runtime: a setup row\'s link brings its card into view, flashed', (await p.$$eval(rs + ' .cbg-ac.cbg-flash', a => a.length)) === 1);
      t(L + 'runtime: its needs folded to one line', /What it needs: \d+ needs?, \d+ sources?/.test(await p.textContent(rs + ' details.cbg-nd summary')) && !(await p.getAttribute(rs + ' details.cbg-nd', 'open')));
      t(L + 'runtime: nothing inert, no red, nothing wider than the window', !(await inert(p, rs)).length && (await red(p, rs)) === 0 && await noSide(p), await inert(p, rs));
      t(L + 'no page error', !errs.length, errs);
      await ctx.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
