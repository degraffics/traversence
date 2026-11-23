// The board's Workflows page rendered at 1280 and 390 (handoffs/control-board-plan/step3/workflows-page.md §9; 03 §27):
// no sideways scroll, with a panel open too; 14 Arc gauges and W13's list; two cards a row on a phone; the counts filter
// the cards and pressed again show every card (R11); a card opens its own page in the side panel, its paths beside it,
// Back through them, and every panel closes by ×, Esc and a click outside (CLAUDE.md "everything that pops open closes").
// Run after workflows-page.php (its seed): node workflows-page.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2];
const html = execFileSync('php', [path.join(__dirname, 'workflows-page-fixture.php'), root]);
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch();
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } }); const W = w + 'px: ';
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname === '/wf') return r.fulfill({ body: html, contentType: 'text/html' });
   return r.fulfill({ body: '<!doctype html><title>Peek</title><p>' + u.pathname + '</p>', contentType: 'text/html' }); });
  await p.goto('http://t.local/wf'); await p.waitForTimeout(500);
  const sw = () => p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  const open = () => p.evaluate(() => [...document.querySelectorAll('.wfp-panel')].filter(x => !x.hidden).map(x => x.id));
  t(W + 'no sideways scroll', await sw() <= 0, await sw());
  const g = await p.evaluate(() => ({ gauges: document.querySelectorAll('.wfp-card .sr svg.sr-arc').length, list: !!document.querySelector('#wfp-card-W13 .wfp-list'), cards: document.querySelectorAll('.wfp-card').length,
    cols: getComputedStyle(document.getElementById('wfp-grid')).gridTemplateColumns.split(' ').length }));
  t(W + '15 cards: 14 Arc gauges and W13\'s list', g.cards === 15 && g.gauges === 14 && g.list, g);
  if (w === 390) t(W + 'two cards a row on a phone', g.cols === 2, g.cols);
  // the counts filter the cards; pressed again, every card shows
  await p.click('[data-wfp-filter="att"]');
  const f1 = await p.evaluate(() => [...document.querySelectorAll('.wfp-card')].filter(c => !c.hidden).map(c => c.id.replace('wfp-card-', '')));
  const n1 = await p.evaluate(() => +document.querySelector('[data-wfp-filter="att"] b').textContent);
  t(W + 'Needs attention shows only its cards (its count)', f1.length === n1 && f1.includes('W1') && f1.includes('W3'), [f1, n1]);
  await p.click('[data-wfp-filter="att"]');
  t(W + '…pressed again, every card', await p.evaluate(() => [...document.querySelectorAll('.wfp-card')].every(c => !c.hidden)));
  // a card opens its page in the side panel; × closes it
  await p.click('#wfp-card-W1 .wfp-open'); await p.waitForTimeout(150);
  t(W + 'a card opens its own page in the side panel', JSON.stringify(await open()) === '["wfp-W1"]' && await p.isVisible('#wfp-W1 .wfp-x'));
  t(W + '…no sideways scroll with it open', await sw() <= 0 && await p.evaluate(() => { const a = document.getElementById('wfp-W1'); return a.scrollWidth <= a.clientWidth + 1; }), await sw());
  await p.click('#wfp-W1 .wfp-x'); await p.waitForTimeout(100);
  t(W + '× closes it', (await open()).length === 0 && await p.isHidden('[data-wfp-scrim]'));
  // Esc
  await p.click('#wfp-card-W2 .wfp-open'); await p.waitForTimeout(100); await p.keyboard.press('Escape'); await p.waitForTimeout(100);
  t(W + 'Esc closes it', (await open()).length === 0);
  // a click outside
  await p.click('#wfp-card-W3 .wfp-open'); await p.waitForTimeout(100);
  await p.mouse.click(5, 450); await p.waitForTimeout(100);
  t(W + 'a click outside closes it', (await open()).length === 0);
  // the three paths beside it, with Back
  await p.click('#wfp-card-W1 .wfp-open'); await p.waitForTimeout(100);
  await p.click('#wfp-W1 [data-wfp-open="wfp-W1-c"]'); await p.waitForTimeout(100);
  t(W + 'Core Services ▸ from its page opens beside it (one panel at a time)', JSON.stringify(await open()) === '["wfp-W1-c"]' && /loads\.php/.test(await p.textContent('#wfp-W1-c')));
  await p.click('#wfp-W1-c [data-wfp-back]'); await p.waitForTimeout(100);
  t(W + '…Back returns to its page', JSON.stringify(await open()) === '["wfp-W1"]');
  await p.click('#wfp-W1 [data-wfp-back]'); await p.waitForTimeout(100);
  t(W + '…Back again closes', (await open()).length === 0);
  for (const k of ['a', 'r']) {
    await p.click('#wfp-card-W4 [data-wfp-open="wfp-W4-' + k + '"]'); await p.waitForTimeout(100);
    const o = await open(); await p.keyboard.press('Escape'); await p.waitForTimeout(100);
    t(W + 'the card\'s ' + (k === 'a' ? 'Workflows ▸' : 'Registered Services ▸') + ' opens its panel, Esc closes it', JSON.stringify(o) === '["wfp-W4-' + k + '"]' && (await open()).length === 0, o);
  }
  // Paused now's item opens the workflow's page
  await p.click('#wfp-now [data-wfp-open="wfp-W3"]'); await p.waitForTimeout(100);
  t(W + 'Paused now\'s item opens its workflow\'s page', JSON.stringify(await open()) === '["wfp-W3"]' && /waiting on the re-read/i.test(await p.textContent('#wfp-W3')));
  await p.keyboard.press('Escape');
  // a link in the panel opens a peek over it; Esc closes the peek first, the panel stays
  await p.click('#wfp-card-W1 .wfp-open'); await p.waitForTimeout(100);
  await p.click('#wfp-W1 a[href="/admin/config/control/wf-W1"][data-peek-frame] >> nth=-1'); await p.waitForTimeout(400);
  const pk = await p.locator('.tv-pk').count();
  await p.keyboard.press('Escape'); await p.waitForTimeout(150);
  t(W + 'its own page on the board opens in a drawer over it; Esc closes that first, the panel stays', pk === 1 && await p.locator('.tv-pk').count() === 0 && JSON.stringify(await open()) === '["wfp-W1"]', pk);
  await p.keyboard.press('Escape'); await p.waitForTimeout(100);
  t(W + '…and Esc again closes the panel', (await open()).length === 0);
  if (w === 390) await p.screenshot({ path: path.join(require('os').tmpdir(), 'workflows-page-390.png'), fullPage: false });
  await p.close();
 }
 await b.close(); console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
