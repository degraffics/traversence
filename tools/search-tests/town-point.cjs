// C.4 (decisions/0053; P9): on the design harness at 390 and 1280: a town picked in Get Local's place pop-up opens within 10 mi and
// the distance changes; its area is listed once as "Town area" and has no distance; ?town= opens at 10 mi, ?radius= wins;
// a ZIP keeps any distance (0060). Run: TW_INJECT=<a stand-in for Tailwind's CDN> node tools/search-tests/town-point.cjs <shots dir>
const { chromium } = require('playwright'); const fs = require('fs');
const TW = (process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''); const out = process.argv[2] || '.';
const sent = async p => (await p.locator('#dr-sent').first().innerText().catch(() => '')).replace(/\s+/g, ' ').trim();
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [390, 1280]) {
  const mk = async () => { const c = await b.newContext({ viewport: { width: w, height: 844 }, isMobile: w < 800, hasTouch: w < 800 }); await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW })); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e))); return [c, p, errs]; };
  // 1. pick the town in the place pop-up
  let [c, p, errs] = await mk(); await p.goto('http://127.0.0.1:8790/directory/', { waitUntil: 'load' }); await p.waitForTimeout(1500);
  let box = p.locator('.tvpp .tv-pbox-in').first(); if (!(await box.isVisible().catch(() => false))) { await p.locator('#dr-where').first().click().catch(() => {}); await p.waitForTimeout(800); box = p.locator('.tvpp .tv-pbox-in').first(); }
  await box.type('Show Low', { delay: 40 }); await p.waitForTimeout(2500);
  const opts = await p.locator('[role=option]:visible').evaluateAll(xs => xs.map(x => x.innerText.replace(/\s+/g, ' ').trim()));
  t(w + ' pop-up: the town first, as a town', /^Show Low, AZ Town/i.test(opts[0] || ''), opts.join(' | '));
  t(w + ' pop-up: the area listed as the area, not as the town again', opts.slice(1).every(o => !/^show low\b/i.test(o)) && opts.filter(o => /Town area/.test(o)).length === 1, opts.join(' | '));
  await p.screenshot({ path: out + '/c4-popup-' + w + '.png' });
  await p.locator('[role=option]:visible').first().click(); await p.waitForTimeout(2500);
  t(w + ' town picked: within 10 mi of Show Low', /within 10 mi .*of Show Low/i.test(await sent(p)), await sent(p));
  await p.screenshot({ path: out + '/c4-town-' + w + '.png' });
  await p.locator('#dr-sent [data-tok="dist"]').first().click(); await p.waitForTimeout(500); await p.locator('#dr-pop [data-dist="25"]').click(); await p.waitForTimeout(2000);
  t(w + ' the distance changes: within 25 mi', /within 25 mi/i.test(await sent(p)), await sent(p));
  const sw = await p.evaluate(() => document.documentElement.scrollWidth); t(w + ' no sideways scroll', sw <= w, 'scrollWidth ' + sw);
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / ')); await c.close();
  // 2. the area chosen: no distance (the list covers the area)
  [c, p, errs] = await mk(); await p.goto('http://127.0.0.1:8790/directory/', { waitUntil: 'load' }); await p.waitForTimeout(1500);
  box = p.locator('.tvpp .tv-pbox-in').first(); if (!(await box.isVisible().catch(() => false))) { await p.locator('#dr-where').first().click().catch(() => {}); await p.waitForTimeout(800); box = p.locator('.tvpp .tv-pbox-in').first(); }
  await box.type('Show Low', { delay: 40 }); await p.waitForTimeout(2500);
  const area = p.locator('[role=option]:visible', { hasText: 'Town area' }).first();
  if (await area.isVisible().catch(() => false)) { await area.click(); await p.waitForTimeout(2500); t(w + ' area picked: "in" the area, no within', / in /.test(await sent(p)) && !/within/.test(await sent(p)), await sent(p)); }
  else t(w + ' area option present', false); await c.close();
  // 3. ?town= link (0053): within 10 mi
  [c, p, errs] = await mk(); await p.goto('http://127.0.0.1:8790/directory/?town=Show%20Low&state=AZ', { waitUntil: 'load' }); await p.waitForTimeout(2500);
  t(w + ' ?town=Show Low: within 10 mi', /within 10 mi/i.test(await sent(p)), await sent(p)); await c.close();
  // 4. ?town= with a radius in the address: the address wins
  [c, p, errs] = await mk(); await p.goto('http://127.0.0.1:8790/directory/?town=Show%20Low&state=AZ&radius=50', { waitUntil: 'load' }); await p.waitForTimeout(2500);
  t(w + ' ?radius=50 wins', /within 50 mi/i.test(await sent(p)), await sent(p)); await c.close();
  // 5. a ZIP keeps no cap (0060)
  [c, p, errs] = await mk(); await c.addInitScript(() => { try { sessionStorage.setItem('traversence_location', JSON.stringify({ state: 'AZ', city: null, zip: '85901', lat: null, lon: null, radius: 10, scope: null, source: 'picked' })); } catch (e) {} });
  await p.goto('http://127.0.0.1:8790/directory/', { waitUntil: 'load' }); await p.waitForTimeout(2500);
  t(w + ' a ZIP: any distance, as before', /within any distance/i.test(await sent(p)), await sent(p)); await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); })();
