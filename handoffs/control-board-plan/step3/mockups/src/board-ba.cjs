// Before/after screenshots of the Board view and a fault module card (Railway: the crawler down) at 1280 and 390.
// Usage: node board-ba.cjs before|after
const { chromium } = require('playwright');
const dir = '/home/user/traversence/handoffs/control-board-plan/step3/board/';
const tag = process.argv[2] || 'after';
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const p = await (await b.newContext({ viewport: { width: w, height: 1400 }, deviceScaleFactor: 2 })).newPage();
    await p.goto('file://' + dir + 'index.html');
    await p.evaluate(() => { try { localStorage.clear(); } catch (e) {} });
    await p.reload();
    await p.screenshot({ path: `${dir}shots/${tag}-board-${w}.png` });
    await p.click('.rail [data-nav="modules"]'); await p.evaluate(() => { document.getElementById('mod-railway').open = true; });
    await p.evaluate(() => { const b = document.querySelector('.sc-b[data-mod="railway"][data-scn="2"]'); if (b) b.click(); });
    await p.evaluate(() => { document.querySelector('.top').style.position = 'static'; });
    await (await p.$('#mod-railway')).screenshot({ path: `${dir}shots/${tag}-fault-card-${w}.png` });
  }
  await b.close();
})();
