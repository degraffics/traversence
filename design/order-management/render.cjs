// design/order-management/render.cjs: renders mockups.html's views (before and after each action) at 1280 and 390 into this folder (full page),
// and checks no view scrolls sideways at 390. Run: node design/order-management/render.cjs
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const b = await chromium.launch(); let bad = 0;
  for (const w of [1280, 390]) {
    const p = await (await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2 })).newPage();
    for (const v of ['board', 'board-after', 'board-empty', 'place', 'place-after', 'order', 'order-after', 'open', 'open-after', 'money', 'money-pay', 'money-after', 'catalog']) {
      await p.goto('file://' + path.join(__dirname, 'mockups.html') + '?v=' + v);
      const wide = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth);
      if (wide) { bad++; console.log(`FAIL ${v} at ${w}: scrolls sideways`); }
      await p.screenshot({ path: path.join(__dirname, `${v}-${w}.png`), fullPage: true });
    }
  }
  await b.close(); console.log(bad ? `${bad} wide` : 'rendered, nothing scrolls sideways'); process.exit(bad ? 1 : 0);
})();
