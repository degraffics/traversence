// Place box lists on phones (place-list.zip): at 390 and 1280, Get Local's "We need a location to search" keeps its bar and GO,
// its list floats under the box inside the window, choosing still works, and the location window's own list stays in the
// page's flow on phones (0058 §25). Run: TW_INJECT=<a stand-in for Tailwind's CDN> node tools/search-tests/place-list.cjs <shots dir> [tag]
const { chromium } = require('playwright'); const fs = require('fs');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.', tag = process.argv[3] || 'after';
const box = async (p, s) => p.evaluate(s => { const e = document.querySelector(s); if (!e || !e.offsetParent && getComputedStyle(e).position !== 'fixed') return null; const r = e.getBoundingClientRect(); return { l: Math.round(r.left), r: Math.round(r.right), t: Math.round(r.top), b: Math.round(r.bottom), pos: getComputedStyle(e).position }; }, s);
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [390, 1280]) {
  const c = await b.newContext({ viewport: { width: w, height: 844 }, isMobile: w < 800, hasTouch: w < 800 }); await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  // 1. "We need a location to search" on Get Local
  await p.goto('http://127.0.0.1:8790/directory/', { waitUntil: 'load' }); await p.waitForTimeout(1500);
  await p.locator('.tvpp .tv-pbox-in').type('Show Low', { delay: 30 }); await p.waitForTimeout(2500);
  const bar = await box(p, '.tvpp-bar'), go = await box(p, '.tvpp-go'), list = await box(p, '.tvpp .tv-pbox-list');
  t(w + ' pop-up: the bar keeps its height', bar && bar.b - bar.t < 60, JSON.stringify(bar));
  t(w + ' pop-up: GO inside the window, beside the box', go && go.r <= w && go.t === bar.t + 1, JSON.stringify(go));
  t(w + ' pop-up: the list floats under the bar, inside the window', list && list.pos === 'absolute' && list.t >= bar.b - 2 && list.r <= w, JSON.stringify(list));
  await p.screenshot({ path: out + '/' + tag + '-popup-' + w + '.png' });
  await p.locator('.tvpp [role=option]').first().click(); await p.waitForTimeout(2000);
  t(w + ' pop-up: choosing the town still works', /Show Low/.test(await p.locator('#dr-sent').innerText().catch(() => '')));
  // 2. the location modal's own list (phones: in the page's flow, decisions/0058 §25)
  await p.evaluate(() => { if (typeof openLocationModal === 'function') openLocationModal(); }); await p.waitForTimeout(800);
  const dest = p.locator('#loc-dest'); if (await dest.isVisible().catch(() => false)) { await dest.type('Show Low', { delay: 30 }); await p.waitForTimeout(2500);
   const dl = await box(p, '#loc-dest-list'); t(w + ' location modal: its list ' + (w < 641 ? 'in the flow (static)' : 'floating'), dl && dl.pos === (w < 641 ? 'static' : 'absolute'), JSON.stringify(dl)); await p.screenshot({ path: out + '/' + tag + '-modal-' + w + '.png' }); }
  else t(w + ' location modal opened', false);
  t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / ')); await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); })();
