// tools/order-tests/m1.cjs <base> <seed.php>: order management M1 rendered on the design harness (staff session
// harness000…0) at 1280 and 390, after m1-seed.php (run again before each width). Prints PASS/FAIL; exit 1 on a FAIL.
// The board (research/order-management-design-2026-11-23.md §4.1, mockups board, board-after, board-empty): it opens on
// Needs us; each tile counts and filters; Money owed apart; one filter button; the count under the table; Send it again
// done in the row (✓ Sent, Copy the link, Moved to…, the tiles recount); a tile's empty state with its way forward.
// The order page (§4.3, mockups order, order-after): Order · Money · Delivery, each with its one button; Complete greyed
// with what's owed; Delivered, balance due's three ways (0178 §6); pickup shows no address fields; the email in Bill to;
// Complete said once. The client's "Yes, I have it" with money owed thanks them and leaves it open.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const [base, seed] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3]];
const shots = process.env.SHOTS || '.';
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + (pass ? '' : '\n     ' + JSON.stringify(saw))); };
const sql = (q) => execFileSync('mysql', ['-uroot', 'tvh', '-N', '-e', q]).toString().trim();
const id = (name) => +sql(`SELECT id FROM orders WHERE client_company = '${name}'`);
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    execFileSync('php', [seed]);
    const c = await b.newContext({ viewport: { width: w, height: 900 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    await c.addInitScript(() => { window.BL = () => [...document.querySelectorAll('[data-ak-table]')].find((x) => x.__list && x.__list.C.tool === 'backoffice/orders'); });
    const p = await c.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
    const tiles = () => p.evaluate(() => [...document.querySelectorAll('[data-ak-stage^="backoffice/orders|"]')].map((t) => ({ k: t.getAttribute('data-ak-stage').split('|')[1], n: t.querySelector('b').textContent, on: t.classList.contains('on'), sub: (t.querySelector('small') || {}).textContent || '' })));
    const rows = () => p.evaluate(() => [...(window.BL() || document).querySelectorAll('tbody tr[data-id]')].map((r) => ({ id: +r.getAttribute('data-id'), next: (r.querySelector('[data-col=next]') || {}).textContent || '', a: (r.querySelector('[data-col=next] .akt-act') || {}).getAttribute ? r.querySelector('[data-col=next] .akt-act').getAttribute('href') : null })));
    const board = async () => { await p.goto(base + '/admin/backoffice/orders', { waitUntil: 'domcontentloaded' }); await p.waitForFunction(() => window.BL() && window.BL().querySelector('tbody tr, .ak-empty'), null, { timeout: 15000 }).catch(() => {}); await p.waitForTimeout(500); };
    await board();
    let t = await tiles(), r = await rows();
    ok(`${w}: the board opens on Needs us, its 10 orders`, t[0].k === 'needs' && t[0].on && t[0].n === '10' && r.length === 10, { t, n: r.length });
    ok(`${w}: Money owed stands apart: $3,648.40, 5 orders, in any stage`, t[4].k === 'owed' && t[4].n === '$3,648.40' && t[4].sub === '5 orders, in any stage', t[4]);
    const range = await p.evaluate(() => (window.BL().querySelector('[data-ak-pg]') || {}).textContent);
    ok(`${w}: the count under the table: "1–10 of 10 that need us"`, range === '1–10 of 10 that need us', range);
    const f = await p.evaluate(() => { const l = window.BL(); const fb = l.querySelectorAll('.ak-fb'), q = l.querySelector('.ak-q'); return { n: fb.length, text: fb[0] && fb[0].textContent.trim(), ph: q.placeholder, under: fb[0] && fb[0].getBoundingClientRect().top >= q.getBoundingClientRect().bottom - 1, wide: document.documentElement.scrollWidth > innerWidth }; });
    ok(`${w}: one filter button, Everyone's · Open, beside the search (390: on its own line under it); no sideways scroll`, f.n === 1 && /Everyone's · Open/.test(f.text) && f.ph === 'Search orders: number, client, email' && (w === 390 ? f.under : !f.under) && !f.wide, f);
    await p.screenshot({ path: `${shots}/m1-board-${w}.png`, fullPage: true });
    const want = { client: 1, production: 1, door: 1, owed: 5 };
    for (const k of Object.keys(want)) {
      await p.click(`[data-ak-stage="backoffice/orders|${k}"]`); await p.waitForTimeout(700);
      const rr = await rows(), tt = await tiles();
      ok(`${w}: the ${k} tile filters the board to its ${want[k]} and shows pressed`, rr.length === want[k] && tt.find((x) => x.k === k).on, { n: rr.length });
    }
    await p.click('[data-ak-stage="backoffice/orders|needs"]'); await p.waitForTimeout(700);
    r = await rows();
    const mesa = r.find((x) => x.id === id('Mesa Ridge Lodge'));
    ok(`${w}: Order #12's case: Collect the balance, landing on its payment step`, mesa && /Collect the balance/.test(mesa.next) && /#payhold$/.test(mesa.a), mesa);
    // Send it again, done in the row (the harness's Mailer "sends" while <root>/harness-mail-ok exists; MAILOK names it)
    if (process.env.MAILOK) require('fs').writeFileSync(process.env.MAILOK, '1');
    const ex = id('Expired Co');
    await p.click(`tr[data-id="${ex}"] button[data-akt-act="resend"]`); await p.waitForTimeout(1500);
    const done = await p.evaluate((ex) => { const r = document.querySelector(`tr[data-id="${ex}"]`); return r && { cell: r.querySelector('[data-col=next]').textContent, copy: !!r.querySelector('[data-akt-copy]'), where: r.querySelector('[data-col=where]').textContent, foot: window.BL().querySelector('[data-ak-pg]').textContent }; }, ex);
    t = await tiles();
    ok(`${w}: Send it again: ✓ Sent with Copy the link in the row, its new state, "Moved to Waiting on the client"`, done && /✓ Sent/.test(done.cell) && done.copy && /With the client/.test(done.where) && /Moved to Waiting on the client/.test(done.cell) && /moved to Waiting on the client \(shown until you refresh\)/.test(done.foot), done);
    ok(`${w}: the tiles recount: Needs us 9, Waiting on the client 2`, t[0].n === '9' && t[1].n === '2', t);
    await c.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: base });
    await p.click(`tr[data-id="${ex}"] [data-akt-copy]`); await p.waitForTimeout(500);
    const cpy = await p.evaluate((ex) => ({ title: document.querySelector(`tr[data-id="${ex}"] [data-akt-copy]`).title, said: /Copied the link/.test(document.body.textContent), clip: '' }), ex);
    cpy.clip = await p.evaluate(() => navigator.clipboard.readText()).catch(() => '');
    ok(`${w}: Copy the link is a round icon naming its order, and copies the client's link`, /^Copy the link to #\d+$/.test(cpy.title) && cpy.said && /\/order\.php\?t=[a-f0-9]{40}/.test(cpy.clip), cpy);
    await p.screenshot({ path: `${shots}/m1-board-after-${w}.png`, fullPage: true });
    if (process.env.MAILOK) {   // the email that doesn't go: sent all the same, said in red with the link to copy
      require('fs').unlinkSync(process.env.MAILOK);
      await p.click('[data-ak-stage="backoffice/orders|client"]'); await p.waitForTimeout(700);
      const sc = id('Sent Co'); await p.click(`tr[data-id="${sc}"] button[data-akt-act="resend"]`); await p.waitForTimeout(1500);
      const bad = await p.evaluate((sc) => { const r = document.querySelector(`tr[data-id="${sc}"] .akt-done`); return r && { bad: r.classList.contains('bad'), text: r.textContent, copy: !!r.querySelector('[data-akt-copy]') }; }, sc);
      ok(`${w}: an email that didn't go says so in the row, with Copy the link`, bad && bad.bad && /The email didn't go to ana@example\.com/.test(bad.text) && bad.copy, bad);
    }
    // a tile's empty state
    sql(`UPDATE orders SET stage = 'complete' WHERE id = ${id('Vendor Co')}`);
    await board(); await p.click('[data-ak-stage="backoffice/orders|production"]'); await p.waitForTimeout(700);
    const em = await p.evaluate(() => { const e = document.querySelector('.akt-empty'); return e && { text: e.textContent, place: !!e.querySelector('a[href="/admin/backoffice/orders/new"]'), show: (e.querySelector('[data-ak-show]') || {}).textContent }; });
    ok(`${w}: an empty tile says so, with Place an order and the tile that has some`, em && /Nothing is at the vendor/.test(em.text) && em.place && /^Show needs us \(\d+\)$/.test(em.show), em);
    await p.screenshot({ path: `${shots}/m1-board-empty-${w}.png`, fullPage: true });
    await p.click('[data-ak-show]'); await p.waitForTimeout(700);
    ok(`${w}: Show needs us goes there`, (await rows()).length >= 9 && (await tiles())[0].on);
    execFileSync('php', [seed]);
    // the order page's three lines
    const lines = async (oid) => { await p.goto(base + '/admin/backoffice/orders/' + oid, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(900);
      return p.evaluate(() => [...document.querySelectorAll('.cs-line')].map((l) => ({ label: l.querySelector('.cs-ll').textContent, text: l.querySelector('.cs-lt').textContent, btns: [...l.querySelectorAll('.cs-la button, .cs-la a')].map((x) => ({ t: x.textContent, go: x.classList.contains('go'), off: x.disabled })), note: (l.querySelector('.cs-la small') || {}).textContent || '' }))); };
    let L = await lines(id('Mesa Ridge Lodge'));
    ok(`${w}: Order #12's page: Order · Money · Delivery, one line each`, L.map((x) => x.label).join(',') === 'Order,Money,Delivery' && /^Out the door\. Ready for pickup since/.test(L[0].text) && /^\$809\.10 owed\. Released before payment by an admin on/.test(L[1].text) && /^Ready for pickup at our shop\.$/.test(L[2].text), L);
    ok(`${w}: Complete greyed with "$809.10 still owed"; Collect the balance the one filled button`, L[0].btns[0].t === 'Complete' && L[0].btns[0].off && L[0].note === '$809.10 still owed' && L[1].btns.length === 1 && L[1].btns[0].t === 'Collect the balance' && L[1].btns[0].go, L);
    const page = await p.evaluate(() => ({ menu: [...document.querySelectorAll('.cs-menu button')].map((b) => b.textContent), mail: !!document.querySelector('.co-mail'), billMail: /ana@example\.com/.test((document.querySelector('.co-inv .co-box') || {}).textContent || ''), pickup: /Picked up at our shop/.test(document.body.textContent), shipFields: !!document.querySelector('[data-f="ship_street"], [data-ef="ship_street"]'), filled: document.querySelectorAll('.cs-lines .hb-b.go').length, pay: (document.body.textContent.match(/Outside QuickBooks: recorded here when it comes in/) || []).length, wide: document.documentElement.scrollWidth > innerWidth }));
    ok(`${w}: said once: no Complete in ▾; the email in Bill to; pickup without address fields; the terms never read as paid; one filled button`, !page.menu.some((m) => /Complete the order|Collect the balance/.test(m)) && !page.mail && page.billMail && page.pickup && !page.shipFields && page.filled === 1 && page.pay === 1 && !page.wide, page);
    await p.screenshot({ path: `${shots}/m1-order-${w}.png` });
    await p.click('.cs-lines button:has-text("Collect the balance")'); await p.waitForTimeout(900);
    const at = await p.evaluate(() => { const a = document.getElementById('co-pay-amt'); const r = a && a.getBoundingClientRect(); return r && document.activeElement === a && r.top >= 0 && r.top < innerHeight; });
    ok(`${w}: Collect the balance goes into the payment step's Amount`, at);
    await p.click('.cs-lx'); await p.waitForTimeout(200);
    ok(`${w}: × closes the lines`, await p.evaluate(() => document.querySelector('.cs-news').hidden));
    L = await lines(id('Delivered Owed Co'));
    ok(`${w}: Delivered, balance due: three ways on the Money line (0178 §6)`, L[1].btns.map((x) => x.t).join('|') === 'Record the payment|Collect the balance|Override with a reason' && L[1].btns[0].go && /The client told us they have it on .*; it completes once the balance is settled\./.test(L[0].text) && /^Delivered: the client told us/.test(L[2].text), L);
    await p.screenshot({ path: `${shots}/m1-delivered-owed-${w}.png` });
    await p.click('.cs-lines button:has-text("Record the payment")'); await p.waitForTimeout(800);
    ok(`${w}: Record the payment puts you in the amount`, await p.evaluate(() => document.activeElement && document.activeElement.id === 'co-pay-amt'));
    await p.click('.cs-lines button:has-text("Override with a reason")'); await p.waitForTimeout(800);
    ok(`${w}: Override with a reason opens its form, in the reason`, await p.evaluate(() => document.activeElement && document.activeElement.id === 'co-owed-why' && document.activeElement.closest('details').open));
    L = await lines(id('Shipped Paid Co'));
    ok(`${w}: paid and out the door: Complete is the filled button`, L[0].btns[0].t === 'Complete' && L[0].btns[0].go && !L[0].btns[0].off && !L[1].btns.length, L);
    await p.click('.cs-lines button:has-text("Complete")'); await p.waitForTimeout(900);
    ok(`${w}: Complete asks first (its confirm)`, await p.evaluate(() => !!document.querySelector('.tv-ask, [role=dialog], .tv-confirm')));
    await p.screenshot({ path: `${shots}/m1-order-after-${w}.png` });
    for (const [n, want] of [['Draft Co', 'Send to the client'], ['Changes Co', 'Upload the new proof'], ['Deposit Co', 'Take a payment'], ['Paid Co', 'Send the work order']]) {
      L = await lines(id(n)); const all = L.flatMap((x) => x.btns); const g = all.filter((x) => x.go);
      ok(`${w}: ${n}: ${want} is the one filled button`, g.length === 1 && g[0].t === want, L);
    }
    // the client's "Yes, I have it" with money owed
    const tok = sql(`SELECT access_token FROM orders WHERE id = ${id('Released Co')}`);
    const cc = await b.newContext({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' });   // the client: no staff session (a staff view can't act)
    await cc.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    const cp = await cc.newPage(); cp.on('pageerror', (e) => errs.push(e.message));
    await cp.goto(base + '/order.php?t=' + tok, { waitUntil: 'domcontentloaded' }); await cp.waitForTimeout(700);
    const yes = await cp.$('button:has-text("Yes, I have it")');
    if (yes) { await Promise.all([cp.waitForNavigation({ timeout: 10000 }).catch(() => {}), yes.click()]); await cp.waitForTimeout(700); }
    const cl = await cp.evaluate(() => ({ thanks: /Thank you!/.test(document.body.textContent) && /You told us you have it on/.test(document.body.textContent), ask: /Do you have it\?/.test(document.body.textContent), complete: /Your order is complete/.test(document.body.textContent) }));
    const st = sql(`SELECT CONCAT(stage, '/', received_at IS NOT NULL) FROM orders WHERE id = ${id('Released Co')}`);
    ok(`${w}: the client's "Yes, I have it" with money owed: thanked, not asked again, not complete`, !!yes && cl.thanks && !cl.ask && !cl.complete && st === 'ready/1', { yes: !!yes, cl, st });
    await cp.screenshot({ path: `${shots}/m1-client-thanks-${w}.png` });
    await cc.close();
    ok(`${w}: no script errors`, !errs.length, errs);
    await c.close();
  }
  await b.close(); console.log(fails ? `\n${fails} FAILED` : '\nall passed'); process.exit(fails ? 1 : 0);
})();
