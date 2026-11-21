const { chromium } = require('playwright');
const fs = require('fs');
const dir = '/home/user/traversence/handoffs/control-board-plan/step3/mockups/';
(async () => {
  const b = await chromium.launch();
  const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));
  const res = [];
  for (const f of files) {
    for (const w of [1280, 390]) {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2 });
      const p = await ctx.newPage();
      await p.goto('file://' + dir + f);
      const r = await p.evaluate(() => {
        const sw = document.documentElement.scrollWidth;
        const wide = [];
        document.querySelectorAll('body *').forEach(e => { const rc = e.getBoundingClientRect(); if (rc.right > innerWidth + 0.5 && rc.width > 0 && !e.closest('.panel')) wide.push(e.tagName + '.' + e.className + ' ' + Math.round(rc.right)); });
        return { sw, wide: wide.slice(0, 5) };
      });
      // open/close check on pages with a panel
      let panel = '';
      if (await p.$('[data-open-panel]')) {
        await p.click('[data-open-panel]');
        const o1 = await p.evaluate(() => document.body.classList.contains('open'));
        await p.keyboard.press('Escape');
        const o2 = await p.evaluate(() => document.body.classList.contains('open'));
        await p.click('[data-open-panel]'); await p.click('.panel:not([hidden]) .x');
        const o3 = await p.evaluate(() => document.body.classList.contains('open'));
        await p.click('[data-open-panel]'); await p.mouse.click(5, 450);
        const o4 = await p.evaluate(() => document.body.classList.contains('open'));
        const psw = await p.evaluate(() => document.documentElement.scrollWidth);
        panel = `panel open=${o1} esc=${!o2} x=${!o3} outside=${!o4}`;
      }
      if (f !== 'index.html') await p.screenshot({ path: dir + 'shots/' + f.replace('.html', '') + '-' + w + '.png', fullPage: true });
      res.push(`${f} @${w}: scrollWidth=${r.sw} ${w===390 ? (r.sw <= 390 ? 'PASS' : 'FAIL') : ''} ${r.wide.join(' | ')} ${panel}`);
      await ctx.close();
    }
  }
  console.log(res.join('\n'));
  await b.close();
})();
