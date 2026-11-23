// tools/order-tests/ironclad.cjs <base> <tokens.json> <outdir>: the client's side of a staff-built order, in the browser at
// 1280 and 390, on the design harness (mock QuickBooks: no money moves; card 4111 1111 1111 1111 pays, one ending 0002 is
// refused). tokens.json comes from ironclad-seed.php. Like ironclad.php it is a survey: each line is WORKS, PARTIAL or
// BROKEN with what the page did. Also entry point 1, Back-Office → Orders: where "place an order" sits.
const { chromium } = require('playwright');
const fs = require('fs');
const [base, tokFile, out] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3], process.argv[4] || '.'];
const tok = JSON.parse(fs.readFileSync(tokFile, 'utf8'));
const rows = [];
const r = (w, path, kase, verdict, ev) => { rows.push([w, path, kase, verdict, ev]); console.log(`${verdict.padEnd(8)} ${w}: ${path} · ${kase}\n         ${ev}`); };
(async () => {
  const b = await chromium.launch();
  const ctx = async (w, staff) => { const c = await b.newContext({ viewport: { width: w, height: w > 500 ? 900 : 844 }, userAgent: 'Mozilla/5.0 Chrome/130' });
    if (staff) await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => /^https:\/\/(cdn\.tailwindcss\.com|fonts\.(googleapis|gstatic)\.com|cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com)\//.test(q.request().url()) || q.request().url().startsWith(base) ? q.continue() : q.abort());
    return c; };
  const wide = (p) => p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
  const text = (p) => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
  for (const w of [1280, 390]) {
    const T = tok[String(w)];
    // ---- entry point 1: Back-Office → Orders
    { const c = await ctx(w, true), p = await c.newPage();
      await p.goto(base + '/admin/backoffice/orders', { waitUntil: 'load' }); await p.waitForTimeout(600);
      const nb = await p.evaluate(() => { const e = [...document.querySelectorAll('a,button')].find((x) => /new client order|place an order/i.test((x.getAttribute('aria-label') || '') + ' ' + x.textContent)); if (!e) return null; const q = e.getBoundingClientRect(); return { words: e.textContent.trim(), w: Math.round(q.width), h: Math.round(q.height), top: Math.round(q.top + scrollY) }; });
      r(w, 'Entry', 'Back-Office → Orders: placing an order', nb && nb.words.length > 4 && nb.top < 250 ? 'WORKS' : 'BROKEN', nb ? `A ${nb.w}×${nb.h} icon ("${nb.words || '+'}", its name only in aria-label), ${nb.top}px down, under two "Runs" lines and four count tiles` : 'no new-order control found');
      await p.screenshot({ path: `${out}/entry-orders-${w}.png` }); await c.close(); }
    // ---- an expired link
    { const c = await ctx(w, false), p = await c.newPage();
      await p.goto(base + '/order.php?t=' + T.expired, { waitUntil: 'load' });
      const t = await text(p);
      r(w, 'Open', 'An expired link', /expired/.test(t) && !(await p.locator('button:has-text("Approve the order")').count()) ? 'WORKS' : 'BROKEN', (t.match(/This order's link expired[^.]*\.[^.]*\./) || ['?'])[0]);
      await p.screenshot({ path: `${out}/expired-${w}.png` }); await c.close(); }
    // ---- a wrong link
    { const c = await ctx(w, false), p = await c.newPage();
      await p.goto(base + '/order.php?t=' + 'f'.repeat(40), { waitUntil: 'load' });
      const t = await text(p); r(w, 'Open', 'A wrong link', /doesn't work/.test(t) ? 'WORKS' : 'BROKEN', (t.match(/This link doesn't work[^.]*\./) || ['?'])[0]); await c.close(); }
    // ---- approve, a refused card, pay, the back button
    { const c = await ctx(w, false), p = await c.newPage();
      await p.goto(base + '/order.php?t=' + T.full, { waitUntil: 'load' });
      r(w, 'Open', 'The order on its link', (await wide(p)) ? 'WORKS' : 'BROKEN', 'no sideways scroll: ' + (await wide(p)));
      await p.fill('#a-street', '12 Main St').catch(() => {}); await p.fill('#a-city', 'Springerville').catch(() => {}); await p.fill('#a-state', 'AZ').catch(() => {}); await p.fill('#a-zip', '85938').catch(() => {});
      await p.check('input[name=agree]');
      const btn = p.locator('button:has-text("Approve the order")');
      await Promise.all([p.waitForLoadState('load'), btn.dblclick()]).catch(() => {}); await p.waitForTimeout(1500);
      let t = await text(p);
      r(w, 'Approve', 'Approve the order (a double click)', /Approved\. Thank you|Pay/.test(t) && !/already approved/.test(t) ? 'WORKS' : (/already approved/.test(t) ? 'PARTIAL' : 'BROKEN'), (t.match(/(Approved\. Thank you!|This order is already approved\.|We couldn't finish[^.]*\.)/) || ['shown: ' + t.slice(0, 120)])[0]);
      await p.screenshot({ path: `${out}/approved-${w}.png` });
      const card = async (num) => { await p.fill('input[autocomplete=cc-name]', 'Dana Ruiz'); await p.fill('input[autocomplete=cc-number]', num); await p.fill('input[autocomplete=cc-exp]', '12/30'); await p.fill('input[autocomplete=cc-csc]', '123'); await p.fill('input[autocomplete=postal-code]', '85938'); await p.locator('button:has-text("Pay $")').first().click(); };
      if (await p.locator('input[autocomplete=cc-number]').count()) {
        await card('4000 0000 0000 0002'); await p.waitForTimeout(2000); t = await text(p);
        r(w, 'Pay', 'A refused card', /Nothing was charged/.test(t) ? 'WORKS' : 'BROKEN', (t.match(/[^.]*declined[^.]*\. Nothing was charged\./) || ['shown: ' + t.slice(0, 100)])[0]);
        await p.screenshot({ path: `${out}/declined-${w}.png` });
        await card('4111 1111 1111 1111'); await p.waitForTimeout(3000); t = await text(p);
        r(w, 'Pay', 'Pay in full by card', /Paid \$/.test(t) ? 'WORKS' : 'BROKEN', (t.match(/Paid \$[\d.,]+[^.]*\./) || ['shown: ' + t.slice(0, 140)])[0]);
        await p.screenshot({ path: `${out}/paid-${w}.png` });
        await p.goBack({ waitUntil: 'load' }).catch(() => {}); await p.waitForTimeout(800); t = await text(p);
        r(w, 'Pay', 'The back button after paying', /Paid \$/.test(t) && !(await p.locator('input[autocomplete=cc-number]').count()) ? 'WORKS' : 'BROKEN', /Paid \$/.test(t) ? 'Shows "Paid", no card form (no-store)' : 'shown: ' + t.slice(0, 120));
      } else r(w, 'Pay', 'The card form after approval', 'BROKEN', 'no card form on the page');
      await c.close(); }
    // ---- an order paid outside QuickBooks: is the card form offered?
    { const c = await ctx(w, false), p = await c.newPage();
      await p.goto(base + '/order.php?t=' + T.outside, { waitUntil: 'load' });
      await p.fill('#a-street', '12 Main St').catch(() => {}); await p.fill('#a-city', 'Springerville').catch(() => {}); await p.fill('#a-state', 'AZ').catch(() => {}); await p.fill('#a-zip', '85938').catch(() => {});
      await p.check('input[name=agree]').catch(() => {}); await p.locator('button:has-text("Approve the order")').click().catch(() => {}); await p.waitForTimeout(1500);
      const has = await p.locator('input[autocomplete=cc-number]').count();
      r(w, 'Pay', 'An order paid outside QuickBooks (cash, check)', has ? 'BROKEN' : 'WORKS', has ? 'The card form is offered on its page although it has no invoice; a payment there is a real card charge with nowhere to post in the books' : 'No card form');
      await p.screenshot({ path: `${out}/outside-${w}.png` }); await c.close(); }
  }
  await b.close();
  const n = (v) => rows.filter((x) => x[3] === v).length;
  console.log(`\n${rows.length} cases: ${n('WORKS')} works, ${n('PARTIAL')} partial, ${n('BROKEN')} broken`);
  fs.writeFileSync(`${out}/ironclad-rendered.json`, JSON.stringify(rows, null, 1));
})();
