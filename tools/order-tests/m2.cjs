// tools/order-tests/m2.cjs <base> <root>: order management M2 rendered on the design harness (staff session harness000…0,
// no staff login: Access::who() reads it as the Founder) at 1280 and 390, after m2-seed.php <root> (run again before each
// width). Prints PASS/FAIL; exit 1 on a FAIL. Screens in $SHOTS. Each button is pressed in the page:
//   Money section (design §4.3a): Total · Paid · Owed (Credits, Refunded only when not $0.00); each payment's one way to
//     correct it (↩ Refund… / ✕ Cancel the record / ↗), greyed with why; no sideways scroll; ⋯ on the Money line holds
//     Refund…, Issue a credit memo, Record a card dispute, Check QuickBooks, Copy the pay link
//   §1  ↩ opens Refund… with its payment chosen and its amount; Send to Jason for his OK (asked first); the Money line says
//       "Refund of $X waiting for Jason's OK" and the section links its Pulse case; ↩ greyed while it waits; Withdraw
//   note  ✕ opens Cancel the record with its payment; asked first; Paid drops, the row struck through with why
//   §2  Order ⋯ Cancel the order… greyed "The vendor accepted the work order on Nov 3…"; on a paid order before acceptance
//       it goes to its form, and Send to Jason prepares the refund and the cancel
//   old address  /admin/order.php?id=<a client order> lands on the order page's Money
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const [base, root] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3]];
const shots = process.env.SHOTS || '.';
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + (pass ? '' : '\n     ' + JSON.stringify(saw))); };
const sql = (q) => execFileSync('mysql', ['-uroot', 'tvh', '-N', '-e', q]).toString().trim();
const id = (name) => +sql(`SELECT id FROM orders WHERE client_company = '${name}'`);
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    execFileSync('php', [__dirname + '/m2-seed.php', root], { env: { ...process.env, QB_ENV: 'mock' } });
    const c = await b.newContext({ viewport: { width: w, height: 900 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    const p = await c.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
    const open = async (n, hash = '') => { await p.goto(base + '/admin/backoffice/orders/' + n + hash, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(400); };
    const yes = async (label) => { const y = await p.$(`.tv-ask button:has-text("${label}"), [role=dialog] button:has-text("${label}")`); if (y) { await Promise.all([p.waitForNavigation({ timeout: 10000 }).catch(() => {}), y.click()]); await p.waitForTimeout(700); } return !!y; };
    const moneyLine = () => p.evaluate(() => { const l = [...document.querySelectorAll('.cs-line')].find((x) => /^Money/.test(x.textContent.trim())); return l ? l.textContent.replace(/\s+/g, ' ').trim() : ''; });
    const toolkit = async (label) => { const i = await p.evaluate((label) => [...document.querySelectorAll('.cs-line')].findIndex((x) => x.textContent.trim().startsWith(label)), label);
      await p.click(`.cs-line:nth-of-type(${i + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
      const items = await p.evaluate((i) => [...document.querySelectorAll('.cs-line')[i].querySelectorAll('.cs-tki')].map((x) => ({ t: x.querySelector('button, a').textContent, off: !!x.querySelector('button[disabled]'), say: x.querySelector('small').textContent })), i);
      return { i, items }; };
    const wide = () => p.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    const flash = () => p.evaluate(() => (document.querySelector('[data-tv-flash]') || {}).textContent || '');

    // ---- the Money section on a paid order ----
    const pc = id('Paid Co');
    await open(pc, '#money');
    const sec = await p.evaluate(() => { const s = document.querySelector('.co-money'); return s && { sum: [...s.querySelectorAll('.mo-sum > span')].map((x) => x.textContent.replace(/\s+/g, ' ').trim()).join(' · '), rows: [...s.querySelectorAll('.co-pay-t tbody tr')].map((r) => ({ t: r.textContent.replace(/\s+/g, ' ').trim(), ic: [...r.querySelectorAll('.cs-ib')].map((b) => ({ title: b.title, off: b.disabled })) })) }; });
    ok(`${w}: Money: Total $809.10 · Paid $809.10 · Owed $0.00, and no Credits or Refunded at $0.00`, sec && sec.sum === 'Total $809.10 · Paid $809.10 · Owed $0.00', sec);
    ok(`${w}: its payment is a row with one round icon, Refund… from the $809.10 payment`, sec && sec.rows.length === 1 && sec.rows[0].ic.length >= 1 && sec.rows[0].ic.some((x) => x.title === 'Refund… from the $809.10 payment' && !x.off), sec && sec.rows);
    ok(`${w}: no sideways scroll`, !(await wide()));
    let tk = await toolkit('Money');
    await p.screenshot({ path: `${shots}/m2-money-toolkit-${w}.png` });
    const names = tk.items.map((x) => x.t);
    ok(`${w}: Money ⋯ holds Refund…, Issue a credit memo, Record a card dispute, Check QuickBooks, Copy the pay link (greyed: nothing owed)`, ['Refund…', 'Issue a credit memo', 'Record a card dispute', 'Check QuickBooks', 'Copy the pay link'].every((n) => names.includes(n)) && tk.items.find((x) => x.t === 'Copy the pay link').off && !tk.items.find((x) => x.t === 'Refund…').off, tk.items);
    ok(`${w}: nothing on the Money line points to the old money page`, !(await p.evaluate(() => [...document.querySelectorAll('.cs-lines a')].some((a) => /admin\/order\.php/.test(a.getAttribute('href') || '')))));
    await p.keyboard.press('Escape');
    await p.screenshot({ path: `${shots}/m2-money-${w}.png`, fullPage: false });
    // §1: ↩ opens Refund… with its payment; Send to Jason (asked first)
    await p.click('.co-pay-t .cs-ib[title^="Refund…"]'); await p.waitForTimeout(500);
    const rf = await p.evaluate(() => ({ open: document.getElementById('co-refund').open, amt: document.getElementById('co-rf-amt').value, tz: (document.querySelector('#co-refund [data-mo-tz]') || {}).value }));
    ok(`${w}: §1 ↩ opens Refund… with its payment chosen, $809.10, the device's time zone`, rf.open && rf.amt === '809.10' && rf.tz !== '', rf);
    await p.fill('#co-refund input[name=amount]', '200.00'); await p.fill('#co-refund input[name=reason]', 'Two cartons arrived crushed');
    await p.screenshot({ path: `${shots}/m2-refund-form-${w}.png` });
    await p.click('#co-refund button.go');
    ok(`${w}: §1 Send to Jason asks first`, await yes('Send it'));
    ok(`${w}: §1 sent: "Sent to Jason for his OK…"; nothing refunded`, /Sent to Jason for his OK/.test(await flash()) && sql(`SELECT COUNT(*) FROM order_ledger WHERE order_id = ${pc} AND kind = 'refund'`) === '0', await flash());
    ok(`${w}: §1 the Money line says "Refund of $200.00 waiting for Jason's OK"`, /Refund of \$200\.00 waiting for Jason's OK/.test(await moneyLine()), await moneyLine());
    const wt = await p.evaluate(() => { const s = document.getElementById('mo-wait'); return s && { t: s.textContent.replace(/\s+/g, ' '), x: s.querySelector('.tv-note-x') ? '×' : '', href: (s.querySelector('a') || {}).getAttribute('href'), ic: (document.querySelector('.co-pay-t .cs-ib[title^="Refund…"]') || {}).disabled, why: (document.querySelector('.co-pay-t .cs-ib[title^="Refund…"]') || {}).title }; });
    const rq = sql(`SELECT id FROM order_refund_requests WHERE order_id = ${pc} AND status = 'waiting'`);
    ok(`${w}: §1 the section: waiting for Jason's OK, its Pulse case linked; ↩ greyed with why`, wt && /Waiting for Jason's OK/.test(wt.t) && /A refund of \$200\.00/.test(wt.t) && !/×/.test(wt.x) && wt.href === '/admin/dashboard/stream#case=refundok%3A' + rq && wt.ic && /waiting for Jason's OK already/.test(wt.why), wt);
    await p.screenshot({ path: `${shots}/m2-refund-waiting-${w}.png` });
    await p.click('#money ~ .co-money button:has-text("Withdraw the refund"), .co-money button:has-text("Withdraw the refund")');
    ok(`${w}: §1 Withdraw asks first, then nothing waits and nothing was refunded`, await yes('Withdraw it') && sql(`SELECT status FROM order_refund_requests WHERE id = ${rq}`) === 'withdrawn' && !/waiting for Jason/.test(await moneyLine()));

    // ---- Cancel the record (0178's note) ----
    const dc = id('Deposit Co');
    await open(dc, '#money');
    const cx = await p.evaluate(() => [...document.querySelectorAll('.co-pay-t .cs-ib')].map((b) => ({ title: b.title, off: b.disabled })));
    ok(`${w}: note: a check recorded here has ✕ Cancel the record of $412.00 (no Refund…)`, cx.length === 1 && cx[0].title === 'Cancel the record of $412.00' && !cx[0].off, cx);
    await p.click('.co-pay-t .cs-ib[title^="Cancel the record"]'); await p.waitForTimeout(500);
    ok(`${w}: note: ✕ opens Cancel the record with its payment, the cursor in why`, await p.evaluate(() => document.getElementById('co-cancelrec').open && document.activeElement && document.activeElement.name === 'why'));
    await p.fill('#co-cx-why', 'Recorded on the wrong order');
    await p.click('#co-cancelrec button.no');
    ok(`${w}: note: asked first`, await yes('Cancel the record'));
    const after = await p.evaluate(() => ({ sum: [...document.querySelectorAll('.mo-sum > span')].map((x) => x.textContent.trim()).join(' · '), gone: [...document.querySelectorAll('.co-pay-t tr.gone')].map((r) => r.textContent.replace(/\s+/g, ' ').trim()) }));
    ok(`${w}: note: Paid $0.00; the row struck through, "Record cancelled …: Recorded on the wrong order"`, /Paid \$0\.00/.test(after.sum) && after.gone.length === 1 && /Recorded on the wrong order/.test(after.gone[0]), after);
    await p.screenshot({ path: `${shots}/m2-record-cancelled-${w}.png` });

    // ---- §2: the cancel window ----
    await open(id('Vendor Co'));
    tk = await toolkit('Order');
    const cv = tk.items.find((x) => x.t === 'Cancel the order…');
    ok(`${w}: §2 Order ⋯ Cancel the order… greyed: "The vendor accepted the work order on Nov 3: it can't be cancelled; a credit or a refund instead"`, cv && cv.off && cv.say.includes('The vendor accepted the work order on Nov 3') && cv.say.includes('a credit or a refund instead'), cv);
    await p.screenshot({ path: `${shots}/m2-cancel-greyed-${w}.png` });
    await p.keyboard.press('Escape');
    const dp = id('Deposit Paid Co');
    await open(dp);
    tk = await toolkit('Order');
    const cp = tk.items.find((x) => x.t === 'Cancel the order…');
    ok(`${w}: §2 before acceptance, with a deposit paid: open, saying its refund goes to Jason first`, cp && !cp.off && /refunded with Jason's OK/.test(cp.say), cp);
    await p.click(`.cs-line:nth-of-type(${tk.i + 1}) .cs-tki button:has-text("Cancel the order…")`); await p.waitForTimeout(600);
    ok(`${w}: §2 it goes to its form, the cursor in why`, await p.evaluate(() => document.getElementById('co-cancel').open && document.activeElement && document.activeElement.id === 'co-cancel-why'));
    await p.fill('#co-cancel-why', 'The client changed their plans');
    await p.click('#co-cancel button.no');
    ok(`${w}: §2 Send to Jason asks first; the refund of $412.00 and the cancel wait; the order unchanged`, await yes('Send it') && sql(`SELECT CONCAT(amount_cents, ':', cancel_order, ':', status) FROM order_refund_requests WHERE order_id = ${dp}`) === '41200:1:waiting' && sql(`SELECT status FROM orders WHERE id = ${dp}`) === 'pending');
    ok(`${w}: §2 the section says the refund and the cancel wait`, /and the order cancelled/.test(await p.evaluate(() => (document.getElementById('mo-wait') || {}).textContent || '')));
    await p.screenshot({ path: `${shots}/m2-cancel-paid-${w}.png` });

    // ---- the old address ----
    await p.goto(base + '/admin/order.php?id=' + pc, { waitUntil: 'domcontentloaded' });
    ok(`${w}: /admin/order.php?id=<a client order> lands on its order page's Money`, new URL(p.url()).pathname === '/admin/backoffice/orders/' + pc && !!(await p.$('#money')), p.url());
    ok(`${w}: no script errors`, !errs.length, errs);
    await c.close();
  }
  await b.close();
  console.log(fails ? `\n${fails} failed` : '\nall passed');
  process.exit(fails ? 1 : 0);
})();
