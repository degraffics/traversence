// tools/order-tests/complete-owed.cjs <base> <owed id> <paid id>, on the design harness (staff session harness000…0, an
// admin), at 1280 and 390, then the writes once. Prints PASS/FAIL; exit 1 on a FAIL. It completes <owed id>: reset its
// stage before running again (UPDATE orders SET stage='shipped'; its lines' item_status back; its wf_items row removed). Jason, 2026-11-23: "paid in full or overrides" (decisions/0177 §4), the Design reviewer's
// and the session manager's note on #1307:
//  - an order with money owed: Complete the order greyed with its reason, the owed amount said once; the strip's finish
//    is Collect the balance, landing on the payment's Amount; an admin's "Override: mark complete anyway" asks a reason
//  - refused without the override, refused with no reason; with one, complete and the reason in the history
//  - the drawer's Complete move (the workflow) refused with the same words; a paid order's Complete stays pressable
const { chromium } = require('playwright');
const [base, owed, paid] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3], process.argv[4]];
const shots = process.env.SHOTS || '.';
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + '\n     ' + saw); };
(async () => {
  const b = await chromium.launch();
  const ctx = async (w) => { const c = await b.newContext({ viewport: { width: w, height: 900 } }); await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]); await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort()); return c; };
  const read = (p) => p.evaluate(() => {
    const f = document.getElementById('finish'); const card = f && f.nextElementSibling; const btn = document.getElementById('co-complete');
    const txt = card ? card.innerText : '';
    return { disabled: !!(btn && btn.disabled), reason: btn ? btn.getAttribute('title') : null, owedTimes: (txt.match(/\$[\d,]+\.\d\d still (owed|to be paid)/g) || []).length,
             override: !!(card && [...card.querySelectorAll('summary')].some((s) => /Override: mark complete anyway/.test(s.textContent))),
             strip: [...document.querySelectorAll('.cs-lines [data-cs-go]:not([disabled]):not(.cs-tk *)')].map((x) => x.textContent.trim() + '→' + x.getAttribute('data-cs-go')).filter((x) => /Complete|Collect/.test(x)) };   // since M1 the Order and Money lines carry these, not ▾
  });
  for (const w of [1280, 390]) {
    const c = await ctx(w); const p = await c.newPage();
    await p.goto(`${base}/admin/backoffice/orders/${owed}`, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1200);
    const r = await read(p);
    ok(`${w}: money owed, Complete the order is greyed with its reason, the amount said once, an admin's override offered`, r.disabled && /still owed: collect the balance first/.test(r.reason || '') && r.owedTimes === 1 && r.override, JSON.stringify(r));
    ok(`${w}: the Money line's button is Collect the balance`, r.strip.length === 1 && r.strip[0] === 'Collect the balance→#co-pay-amt', JSON.stringify(r.strip));
    // the line's button lands on the payment's Amount, in view below the strip
    await p.evaluate(() => { const x = document.querySelector('.cs-lines [data-cs-go="#co-pay-amt"]'); x && x.click(); });
    await p.waitForTimeout(900);
    const land = await p.evaluate(() => { const a = document.getElementById('co-pay-amt'), cs = document.querySelector('.cs'); if (!a || !cs) return { inView: false, missing: !a ? '#co-pay-amt' : '.cs' }; const r = a.getBoundingClientRect(); return { focused: document.activeElement === a, top: Math.round(r.top), stripBottom: Math.round(cs.getBoundingClientRect().bottom), inView: r.top >= cs.getBoundingClientRect().bottom && r.bottom <= innerHeight }; });
    ok(`${w}: Collect the balance on the Money line lands on the payment's Amount, in view below the strip`, land.inView, JSON.stringify(land));
    await p.screenshot({ path: `${shots}/complete-owed-land-${w}.png` });
    await p.evaluate(() => document.getElementById('finish').scrollIntoView()); await p.evaluate(() => { const d = [...document.querySelectorAll('#finish + * details')][0]; if (d) d.open = true; }); await p.waitForTimeout(300);
    await p.evaluate(() => window.scrollBy(0, -120));
    await p.screenshot({ path: `${shots}/complete-owed-${w}.png` });
    if (paid) {
      await p.goto(`${base}/admin/backoffice/orders/${paid}`, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1000);
      const q = await read(p);
      ok(`${w}: paid in full, Complete the order stays pressable, no override`, !q.disabled && !q.override && q.strip[0] === 'Complete→#co-complete', JSON.stringify(q));
    }
    await c.close();
  }
  // the writes, once: the page's own form posts (what a press sends), then the drawer's move
  const c = await ctx(1280); const p = await c.newPage();
  await p.goto(`${base}/admin/backoffice/orders/${owed}`, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(800);
  const post = (extra) => p.evaluate(async (extra) => {
    const f = [...document.querySelectorAll('form')].find((x) => x.querySelector('input[name=do][value=complete]'));
    const d = new FormData(f); d.delete('override'); d.delete('why'); for (const [k, v] of Object.entries(extra)) d.set(k, v);
    const html = await (await fetch(location.href, { method: 'POST', body: new URLSearchParams(d), credentials: 'same-origin' })).text();
    const doc = new DOMParser().parseFromString(html, 'text/html'); const fl = doc.querySelector('[data-tv-flash]');
    return { flash: fl ? fl.getAttribute('data-tv-flash') + ': ' + fl.textContent.trim().slice(0, 160) : '', complete: /This order is done/.test(html) };
  }, extra);
  // the drawer's move first, while it's still owed
  const mv = await p.evaluate(async (id) => {
    const t = await (await fetch('/api/auth/session.php', { credentials: 'include' })).json();
    const r = await fetch('/admin/api/backoffice/orders', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': t.csrf_token || '' }, body: JSON.stringify({ action: 'move', id: +id, move: 'to_complete', input: {} }) });
    return r.status + ' ' + (await r.text()).slice(0, 200);
  }, owed);
  ok('The drawer\'s Complete move is refused while money is owed, with the same words', /still owed: collect the balance first/.test(mv) && !/"ok":true|"success":true/.test(mv), mv);
  const a = await post({});
  ok('A Complete sent without the override is refused, nothing completed', /^error: .*still owed: collect the balance first/i.test(a.flash) && !a.complete, JSON.stringify(a));
  const bl = await post({ override: '1', why: '' });
  ok('The override with no reason is refused', /^error: Say why/.test(bl.flash) && !bl.complete, JSON.stringify(bl));
  const yes = await post({ override: '1', why: 'Settled outside the system (harness)' });
  await p.goto(`${base}/admin/backoffice/orders/${owed}`, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(800);
  const hist = await p.evaluate(() => [...document.querySelectorAll('.co-log li')].map((l) => l.textContent.trim()).find((t) => /^Completed owed/i.test(t)) || '');
  ok('The override with a reason completes it, the reason kept in the history', yes.complete && /\$[\d,]+\.\d\d still owed: Settled outside the system \(harness\)/.test(hist), JSON.stringify(yes) + ' | ' + hist.slice(0, 160));
  await b.close();
  console.log(fails ? `${fails} failed` : 'all passed');
  process.exit(fails ? 1 : 0);
})();
