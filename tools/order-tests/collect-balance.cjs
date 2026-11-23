// tools/order-tests/collect-balance.cjs <base> <shipped id> <paid id>: Back-Office → Orders' Next step for an order out the
// door (released before payment by an admin) with a balance still due: "Collect the balance", opening the order at its
// payment step (#payhold, with Record the payment), its heading in view below the command strip, never under it; one paid in full says "Mark complete". At 1280 and 390, on the design
// harness (staff session harness000…0). Prints PASS/FAIL; exit 1 on a FAIL.
const { chromium } = require('playwright');
const [base, owed, paid] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3], process.argv[4]];
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + '\n     ' + saw); };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const c = await b.newContext({ viewport: { width: w, height: 900 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    const p = await c.newPage();
    await p.goto(base + '/admin/backoffice/orders', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1500);
    const step = (id) => p.evaluate((id) => {
      const a = [...document.querySelectorAll('a,button')].find((x) => (x.getAttribute('href') || '').startsWith('/admin/backoffice/orders/' + id) && x.closest('[class*=akt-act], td, .akt-card, [data-col=next]') && /Collect|Mark|Record|Start|Send|Finish|Open/.test(x.textContent));
      return a ? { text: a.textContent.trim(), href: a.getAttribute('href'), state: (a.parentElement.textContent || '').replace(a.textContent, '').trim().slice(0, 40), seen: !!a.offsetParent } : null;
    }, id);
    const s1 = await step(owed);
    ok(`${w}: shipped with a balance says Collect the balance`, s1 && s1.text === 'Collect the balance' && s1.href === `/admin/backoffice/orders/${owed}#payhold` && s1.seen, JSON.stringify(s1));
    const s2 = await step(paid);
    ok(`${w}: paid in full and ready says Mark complete`, s2 && s2.text === 'Mark complete', JSON.stringify(s2));
    if (s1) {
      await Promise.all([p.waitForURL(/#payhold/, { timeout: 8000 }).catch(() => {}), p.click(`a[href="${s1.href}"]`)]);
      await p.waitForTimeout(1200);
      const at = await p.evaluate(() => { const h = document.getElementById('payhold'); if (!h) return null; const r = h.getBoundingClientRect(); const card = h.nextElementSibling; const cs = document.querySelector('.cs'); return { top: Math.round(r.top), strip: cs ? Math.round(cs.getBoundingClientRect().bottom) : 0, heading: h.textContent.trim(), record: !!(card && [...card.querySelectorAll('button')].some((x) => /Record the payment/.test(x.textContent))), wide: document.documentElement.scrollWidth > innerWidth }; });
      ok(`${w}: the button opens the order at its payment step, its heading below the strip`, at && at.record && at.top >= at.strip && at.top < 450 && !at.wide, JSON.stringify(at) + ' ' + p.url().replace(base, ''));
      await p.screenshot({ path: `${process.env.SHOTS || '.'}/collect-${w}.png` });
    }
    await c.close();
  }
  await b.close();
  console.log(fails ? `${fails} failed` : 'all passed');
  process.exit(fails ? 1 : 0);
})();
