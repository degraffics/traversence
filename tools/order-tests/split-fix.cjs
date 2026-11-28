// The order page's split fix (Gate 2 run, 2026-11-27; slot #8): an order whose lots dialog renders (ship_mode single or
// multi) had a script error, "Cannot read properties of null (reading 'hasAttribute')": the lots dialog's Split button
// carried the save group's data-split, so the save group's script died on it and Save ▾ lost its list and its confirm
// question (the QuickBooks invoice and COGS warnings). Rendered at 1280 and 390 on the order harness, staff session.
// Run: node split-fix.cjs [base] [order id with ship_mode single] [order id with none]
const { chromium } = require('playwright'); const fs = require('fs');
const base = process.argv[2] || 'http://127.0.0.1:8795', shipped = process.argv[3] || '1', plain = process.argv[4] || '2';
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : '';
let pass = 0, fail = 0; const ok = (c, m, x) => { if (c) pass++; else fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m + (x ? '  [' + x + ']' : '')); };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    console.log('— ' + w + 'px');
    const c = await b.newContext({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36' });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', q => { const u = q.request().url(); if (u.startsWith('https://cdn.tailwindcss.com')) return q.fulfill({ contentType: 'application/javascript', body: TW }); return u.startsWith(base) ? q.continue() : q.abort(); });
    for (const [oid, what] of [[shipped, 'with shipping (the lots dialog renders)'], [plain, 'with none']]) {
      const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(base + '/admin/backoffice/orders/' + oid, { waitUntil: 'load' }); await p.waitForTimeout(900);
      ok(!errs.length, `order ${oid} ${what}: no script error`, errs.slice(0, 1).join(''));
      const g = await p.evaluate(() => { const sp = document.querySelector('.co-split[data-split]'); if (!sp) return null; const m = sp.querySelector('[data-split-main]'), l = sp.querySelector('.co-split-l');
        return { confirm: m && m.getAttribute('data-confirm') || '', items: l ? l.querySelectorAll('button').length : 0 }; });
      if (g) {
        ok(g.confirm.length > 0, `order ${oid}: Save's confirm question is set (the save group's script ran)`, g.confirm.slice(0, 70));
        ok(g.items > 0, `order ${oid}: Save ▾ has its list`, String(g.items));
      }
      if (oid === shipped) {
        const lots = await p.evaluate(() => ({ btn: !!document.querySelector('#co-lots [data-lots-split]'), bare: document.querySelectorAll('[data-split]:not(.co-split)').length }));
        ok(lots.btn && lots.bare === 0, 'the lots dialog\'s Split is its own (data-lots-split); no bare data-split outside a save group', JSON.stringify(lots));
        // and it still splits: open an item's lots, ask for 3 equal lots
        const opener = p.locator('[data-ship-open]').first();
        if (await opener.count()) {
          await opener.evaluate(e => e.click()); await p.waitForTimeout(300);
          await p.fill('#co-lots [data-split-n]', '3'); await p.click('#co-lots [data-lots-split]'); await p.waitForTimeout(200);
          const n = await p.$$eval('#co-lots [data-lots-rows] > *', x => x.length);
          ok(n === 3, 'the lots dialog\'s Split still splits an item into 3 lots', String(n));
          await p.click('#co-lots [data-lots-close]');
        } else ok(false, 'an item to open its lots (seed: a line on the shipped order)');
      }
      await p.close();
    }
    await c.close();
  }
  console.log(pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0);
})();
