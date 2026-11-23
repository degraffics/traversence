// tools/order-tests/release-a-split.cjs <outdir> <order at 1280> <order at 390>: an order shipping to two addresses on the order page
// (design harness, staff harness000…0): the notice and Split into one order per address in the order's one notices box, the
// split named with Undo, Undo putting the items back, no sideways scroll; at 1280 and 390. Prints PASS/FAIL.
const { chromium } = require('playwright');
const base = 'http://127.0.0.1:8790', out = process.argv[2], ids = { 1280: process.argv[3], 390: process.argv[4] };
let fails = 0; const ok = (w, what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + w + ' ' + what + '\n     ' + saw); };
(async () => { const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const c = await b.newContext({ viewport: { width: w, height: w > 500 ? 900 : 844 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    const p = await c.newPage(); const url = base + '/admin/backoffice/orders/' + ids[w];
    await p.goto(url, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(900);
    const btn = p.locator('button:has-text("Split into one order per address")');
    ok(w, 'The notice and its button', await btn.count() === 1, (await p.locator('.cs-news').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 160));
    await p.screenshot({ path: `${out}/split-notice-${w}.png`, clip: { x: 0, y: 0, width: w, height: w > 500 ? 520 : 700 } });
    await btn.click(); await p.waitForTimeout(400);
    const yes = p.locator('button:has-text("Split it")'); if (await yes.count()) await Promise.all([p.waitForLoadState('domcontentloaded'), yes.first().click()]);
    await p.waitForTimeout(1200);
    const undo = p.locator('button:has-text("Undo the split")');
    const flash = await p.evaluate(() => (document.querySelector('[data-tv-flash]') || {}).textContent || '');
    ok(w, 'Split: the new order named, Undo offered', await undo.count() === 1 && /order #/.test(flash), flash.trim().slice(0, 170));
    await p.screenshot({ path: `${out}/split-pop-${w}.png`, clip: { x: 0, y: 0, width: w, height: w > 500 ? 520 : 700 } }); await p.waitForTimeout(5200);
    await p.screenshot({ path: `${out}/split-done-${w}.png`, clip: { x: 0, y: 0, width: w, height: w > 500 ? 520 : 700 } });
    ok(w, 'No sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), '');
    await undo.click(); await p.waitForTimeout(400);
    const y2 = p.locator('button:has-text("Undo the split")').last(); const yes2 = p.locator('.tv-ask button:has-text("Undo the split"), [role=dialog] button:has-text("Undo the split")');
    if (await yes2.count()) await Promise.all([p.waitForLoadState('domcontentloaded'), yes2.first().click()]);
    await p.waitForTimeout(1200);
    ok(w, 'Undo: the items back, the split offered again', await p.locator('button:has-text("Split into one order per address")').count() === 1, (await p.evaluate(() => (document.querySelector('[data-tv-flash]') || {}).textContent || '')).trim().slice(0, 120));
    await c.close(); }
  await b.close(); console.log(fails ? fails + ' failed' : 'all passed'); })();
