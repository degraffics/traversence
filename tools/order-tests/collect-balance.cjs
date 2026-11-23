// tools/order-tests/collect-balance.cjs <base> <out-the-door id> <paid id> <held id>, on the design harness (staff session
// harness000…0), at 1280 and 390. Prints PASS/FAIL; exit 1 on a FAIL.
//  - Back-Office → Orders: an order an admin released before payment that's shipped or ready with a balance due says
//    "Out the door" with Collect the balance, opening the order at its payment step (#payhold, Record the payment), its
//    heading in view below the command strip, never under it; one paid in full says Mark complete.
//  - One fact, shown once (Design reviewer, 2026-11-23): an order waiting for payment (the held id: approved, unpaid, not
//    released) says the hold and offers Release before payment only under Waiting for payment; Work orders carries one
//    amber line, Held until it's paid: <that section's heading> ↑, with no ×, and it goes back up to the payment step.
const { chromium } = require('playwright');
const [base, owed, paid, held] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3], process.argv[4], process.argv[5]];
const shots = process.env.SHOTS || '.';
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + '\n     ' + saw); };
const below = (p) => p.evaluate(() => { const h = document.getElementById('payhold'), cs = document.querySelector('.cs'); return h && cs ? Math.round(h.getBoundingClientRect().top) - Math.round(cs.getBoundingClientRect().bottom) : null; });
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const c = await b.newContext({ viewport: { width: w, height: 900 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    const p = await c.newPage();
    await p.goto(base + '/admin/backoffice/orders', { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1500);
    const step = (id) => p.evaluate((id) => {
      const a = [...document.querySelectorAll('a,button')].find((x) => (x.getAttribute('href') || '').startsWith('/admin/backoffice/orders/' + id) && /Collect|Mark|Record|Start|Send|Finish|Open/.test(x.textContent));
      return a ? { text: a.textContent.trim(), href: a.getAttribute('href'), state: (a.parentElement.textContent || '').replace(a.textContent, '').trim().slice(0, 40), seen: !!a.offsetParent } : null;
    }, id);
    // the list, both rows framed (the Design reviewer's proof)
    await p.evaluate((id) => { const a = document.querySelector(`a[href^="/admin/backoffice/orders/${id}"]`); const r = a && a.closest('tr, .akt-card, [data-id]'); if (r) { r.scrollIntoView({ block: 'start' }); const bar = document.querySelector('.cs, .ak-bar, [class*=cmd]'); window.scrollBy(0, -((bar ? bar.getBoundingClientRect().bottom : 120) + 12)); r.style.outline = '2px solid #B45309'; } }, owed);
    await p.waitForTimeout(300); await p.screenshot({ path: `${shots}/collect-list-${w}.png` });
    await p.evaluate(() => document.querySelectorAll('[style*="outline"]').forEach((r) => { r.style.outline = ''; }));
    const s1 = await step(owed);
    ok(`${w}: shipped with a balance says Out the door, Collect the balance`, s1 && s1.text === 'Collect the balance' && s1.state === 'Out the door' && s1.href === `/admin/backoffice/orders/${owed}#payhold` && s1.seen, JSON.stringify(s1));
    const s2 = await step(paid);
    ok(`${w}: paid in full and ready says Mark complete`, s2 && s2.text === 'Mark complete', JSON.stringify(s2));
    if (s1) {
      await Promise.all([p.waitForURL(/#payhold/, { timeout: 8000 }).catch(() => {}), p.click(`a[href="${s1.href}"]`)]);
      await p.waitForTimeout(1200);
      const at = await p.evaluate(() => { const h = document.getElementById('payhold'); const card = h && h.nextElementSibling; return h && { heading: h.textContent.trim(), record: !!(card && [...card.querySelectorAll('button')].some((x) => /Record the payment/.test(x.textContent))), released: /Released before payment/.test(card ? card.textContent : ''), wide: document.documentElement.scrollWidth > innerWidth }; });
      const gap = await below(p);
      ok(`${w}: Collect the balance opens the payment step, its heading below the strip`, at && at.record && gap !== null && gap >= 0 && gap < 80 && !at.wide, JSON.stringify({ ...at, gapBelowStrip: gap }));
      await p.screenshot({ path: `${shots}/collect-${w}.png` });
    }
    // an order waiting for payment: the hold said once
    await p.goto(`${base}/admin/backoffice/orders/${held}#payhold`, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1200);
    const once = await p.evaluate(() => {
      const work = document.getElementById('work'); const card = work && work.nextElementSibling;
      const up = card && card.querySelector('.tv-note a[href="#payhold"]');   // tv-ask draws a status line as its notice box
      const box = up && up.closest('.tv-note');
      return { hasWork: !!work,
               releaseButtons: [...document.querySelectorAll('button')].filter((x) => x.textContent.trim() === 'Release before payment').length,
               noticeTimes: [...document.querySelectorAll('.tv-note, .co-hold')].filter((x) => /^\s*(Waiting for payment|On a payment hold):/.test(x.textContent)).length,
               upLine: box ? box.textContent.replace('×', '').trim() : null, heading: (document.getElementById('payhold') || {}).textContent, amber: !!(box && box.classList.contains('info')), closable: !!(box && box.querySelector('.tv-note-x')),
               redInWork: !!(card && card.querySelector('.tv-note:not(.info)')), linkColor: up ? getComputedStyle(up).color : null };
    });
    ok(`${w}: waiting for payment, the hold is said once; Work orders points up to it in amber, no ×`, once.hasWork && once.releaseButtons === 1 && once.noticeTimes === 1 && once.heading && (once.upLine || '').replace(/\s+/g, ' ') === `Held until it's paid: ${once.heading.trim()} ↑`.replace("paid: ", 'paid:').replace(/\s+/g, ' ') && once.amber && !once.closable && !once.redInWork && once.linkColor === 'rgb(146, 64, 14)', JSON.stringify(once));
    if (once.upLine) {
      await p.evaluate(() => document.getElementById('work').scrollIntoView()); await p.waitForTimeout(300);
      await p.screenshot({ path: `${shots}/collect-held-work-${w}.png` });
      await p.click('#work + * .tv-note a[href="#payhold"]'); await p.waitForTimeout(500);
      const g = await below(p);
      ok(`${w}: Waiting for payment ↑ goes back up to the payment step`, g !== null && g >= 0 && g < 80, 'heading below the strip by ' + g + 'px');
      await p.screenshot({ path: `${shots}/collect-held-${w}.png` });
    }
    await c.close();
  }
  await b.close();
  console.log(fails ? `${fails} failed` : 'all passed');
  process.exit(fails ? 1 : 0);
})();
