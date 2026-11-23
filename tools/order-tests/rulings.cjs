// tools/order-tests/rulings.cjs <base> <outdir>: the Orders rulings release (rebuilt on the order model) rendered at 1280
// and 390, against the design harness (handoffs/design-harness) seeded by rulings-seed.php: order 101 (approved, paid in
// full on approval, nothing paid, a work order saved), 102 (a deposit order; the vendor shipped before the balance), 103
// (a draft, Pay in full), 104 (an old draft on invoice-later terms), 105 (sent on deposit terms: the client's page).
// Cookies: harness…0 is an admin (no staff login: the Founder), harness…9 a staff login at the Staff level. Presses:
// Send on 101's work order (refused, with why), the admin's Release before payment (no reason, then a reason), Send again
// (the hold lifted). Checks each page for sideways scroll at 390.
const { chromium } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8790', out = process.argv[3] || '.';
let ok = 0, bad = 0;
const t = (name, c) => { console.log((c ? 'ok   ' : 'FAIL ') + name); c ? ok++ : bad++; };
const ADMIN = 'harness0000000000000000000000', STAFF = 'harness0000000000000000000009';
(async () => {
  const b = await chromium.launch();
  const ctx = async (sid, w) => { const c = await b.newContext({ viewport: { width: w, height: w > 500 ? 900 : 844 }, userAgent: 'Mozilla/5.0 Chrome/130' });
    await c.addCookies([{ name: 'traversence_session', value: sid, url: base }]);
    await c.route('**/*', (r) => /^https:\/\/(cdn\.tailwindcss\.com|fonts\.(googleapis|gstatic)\.com|cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com)\//.test(r.request().url()) || r.request().url().startsWith(base) ? r.continue() : r.abort());   // the site and its style hosts; nothing else
    return c; };
  const noSideways = async (p) => p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
  const notice = async (p) => (await p.evaluate(() => (window.__coFlash && window.__coFlash.text) || '')) || '';   // the page's answer to the press (shown by TvAsk.say)
  const yes = async (p, label) => { const y = p.locator(`button:has-text("${label}")`).last(); await y.waitFor({ timeout: 5000 }); await y.click(); };
  // a picture of a section as it's seen: scrolled to just under the sticky bars, the viewport as it is
  const shot = async (p, sel, name) => { const el = p.locator(sel).first(); if (await el.count()) await el.evaluate((e) => window.scrollTo(0, e.getBoundingClientRect().top + window.scrollY - (window.innerWidth < 500 ? 150 : 110))); await p.waitForTimeout(300); await p.screenshot({ path: `${out}/${name}.png` }); };

  // a Staff-level login: the release is greyed with its reason, on the payment hold and on the work orders
  for (const w of [1280, 390]) {
    const c = await ctx(STAFF, w), p = await c.newPage();
    await p.goto(base + '/admin/backoffice/orders/101#work', { waitUntil: 'load' });
    const grey = p.locator('button[disabled]:has-text("Release before payment")');
    t(`${w}: Staff level: Release before payment greyed, twice (payment hold, work orders)`, (await grey.count()) === 2);
    t(`${w}: ... with the reason beside it`, (await p.locator('text=Needs an Executive or above: only an admin releases an order before payment.').count()) === 2);
    t(`${w}: ... and no reason field to fill`, (await p.locator('input[name=why]').count()) === 0);
    t(`${w}: Staff level: no sideways scroll`, await noSideways(p));
    await shot(p, '#work', `staff-101-work-${w}`);
    await c.close();
  }

  // an admin, 101: the vendor refused with why; release with a reason; then it can go
  for (const w of [390, 1280]) {
    const c = await ctx(ADMIN, w), p = await c.newPage();
    await p.goto(base + '/admin/backoffice/orders/101#work', { waitUntil: 'load' });
    const work = p.locator('#work + .hb-card');
    if (w === 390) {
      t('390: Work orders says it waits for payment, with what\'s left', (await work.locator('.tv-note.bad').first().textContent()).includes('goes to the vendor once it\'s paid in full ($480.00 still to pay)'));
      t('390: no sideways scroll on the order', await noSideways(p));
      await shot(p, '#work', 'admin-101-work-390');
      await shot(p, '#payhold', 'admin-101-payhold-390');
      // Send on the saved work order
      await work.locator('form.co-wo-again button:has-text("Send")').first().click();
      await yes(p, 'Send it');
      await p.waitForLoadState('load'); await p.waitForTimeout(400);
      const n1 = await notice(p);
      t('390: Send refused, saying why and what to do: "' + n1.slice(0, 90) + '…"', n1.includes('goes to the vendor once it\'s paid in full') && n1.includes('an admin can release'));
      t('390: the press came back to the work orders', p.url().includes('#work'));
      // the release without a reason: the form asks for it (required), nothing sent
      const why = p.locator('#rel-why-wo');
      t('390: the reason box is there for an admin, with spell check', (await why.count()) === 1 && (await why.getAttribute('data-spell')) !== null && (await why.getAttribute('required')) !== null);
      await why.fill('Net 30 agreed with Dana by phone');
      await p.locator('form.co-rel:has(#rel-why-wo) button:has-text("Release before payment")').click();
      await yes(p, 'Release it');
      await p.waitForLoadState('load'); await p.waitForTimeout(400);
      const n2 = await notice(p);
      t('390: released: "' + n2.slice(0, 80) + '…"', n2.includes('Released before payment'));
      t('390: the press came back to the payment hold', p.url().includes('#payhold'));
      const relN = await p.locator('.tv-note:has-text("Released before payment:")').count(), waitN = await p.locator('#work + .hb-card .tv-note:has-text("Waiting for payment:")').count();
      t(`390: the order now says it's released (${relN}), and the work orders' waiting line is gone (${waitN})`, relN >= 1 && waitN === 0);
      t('390: no release box once released', (await p.locator('input[name=why]').count()) === 0);
      await shot(p, '#payhold', 'admin-101-released-390');
      // Send again: the hold is lifted (the harness has no mail server, so the send itself reports that)
      await p.goto(base + '/admin/backoffice/orders/101#work', { waitUntil: 'load' });
      await p.locator('#work + .hb-card form.co-wo-again button:has-text("Send")').first().click();
      await yes(p, 'Send it');
      await p.waitForLoadState('load'); await p.waitForTimeout(400);
      const n3 = await notice(p);
      t('390: Send after the release passes the payment check (the harness can\'t email: "' + n3.slice(0, 70) + '…")', !n3.includes('goes to the vendor once') && (n3.includes('email didn') || n3.includes('emailed')));
    } else {
      // 1280: the same order after the release, and the others
      t('1280: no sideways scroll on the order', await noSideways(p));
      await shot(p, '#payhold', 'admin-101-released-1280');
    }
    // 102: the vendor shipped before the balance
    await p.goto(base + '/admin/backoffice/orders/102#work', { waitUntil: 'load' });
    const held = p.locator('.tv-note:has-text("Held for its balance:")');
    t(`${w}: 102: the work order shows Shipped, and the order is held for its balance`, (await held.count()) === 1 && (await p.locator('#work + .hb-card .hb-pill:has-text("Shipped")').count()) === 1 && (await held.textContent()).includes('$168.00 still to pay'));
    t(`${w}: 102: the deposit order isn't held at the vendor (its deposit is in)`, (await p.locator('.tv-note:has-text("Waiting for payment:")').count()) === 0 || !(await p.locator('.tv-note:has-text("Waiting for payment:")').first().textContent()).includes('goes to the vendor'));
    t(`${w}: 102: no sideways scroll`, await noSideways(p));
    await shot(p, '#work', `admin-102-work-${w}`);
    // 103 / 104: the pay-mode list
    await p.goto(base + '/admin/backoffice/orders/103', { waitUntil: 'load' });
    const o103 = await p.locator('select[data-f=pay_mode] option').allTextContents();
    t(`${w}: 103 (a new order): Invoice later isn't offered [${o103.join(', ')}]`, o103.length >= 2 && !o103.some((x) => x.includes('Invoice')));
    await p.goto(base + '/admin/backoffice/orders/104', { waitUntil: 'load' });
    const o104 = await p.locator('select[data-f=pay_mode] option').allTextContents();
    t(`${w}: 104 (an old invoice-later draft): it still reads, marked no longer offered`, o104.includes('Invoice later (no longer offered)'));
    // choosing a deposit on 103 starts at 65%
    if (w === 1280) {
      await p.goto(base + '/admin/backoffice/orders/103', { waitUntil: 'load' });
      await p.selectOption('select[data-f=pay_mode]', 'deposit');
      await p.waitForTimeout(1500);
      await p.goto(base + '/admin/backoffice/orders/103', { waitUntil: 'load' });
      t('1280: 103: choosing Deposit starts at 65%', (await p.locator('dt:has-text("Deposit %") + dd').textContent()).includes('65'));
    }
    // the client's page on deposit terms
    await p.goto(base + '/order.php?t=' + 'e'.repeat(40), { waitUntil: 'load' });
    const note = await p.locator('p.go-note:has-text("Approving makes your invoice")').first().textContent().catch(() => '');
    t(`${w}: the client's page: printing starts once the deposit is paid`, note.includes('printing starts once the deposit is paid'));
    t(`${w}: the client's page: no sideways scroll`, await noSideways(p));
    await p.screenshot({ path: `${out}/client-105-${w}.png`, fullPage: false });
    await c.close();
  }
  // Pulse: 102's case asks our team to collect the balance, with a button that records it in place
  for (const w of [1280, 390]) {
    const c = await ctx(ADMIN, w), p = await c.newPage();
    await p.goto(base + '/admin/dashboard/stream', { waitUntil: 'load' }); await p.waitForTimeout(600);
    const row = p.locator('[data-key="order:102"]').first();
    t(`${w}: Pulse: one row for order 102, saying the balance is due before delivery`, (await row.count()) === 1 && (await row.getAttribute('data-text')).includes('balance due before delivery') && (await p.locator('[data-key^="wobal:"]').count()) === 0);
    t(`${w}: Pulse: the row's own button isn't "Mark shipped" (it would be refused)`, !(await row.innerText()).includes('Mark shipped'));
    await row.click(); await p.waitForTimeout(900);
    const body = await p.evaluate(() => document.body.innerText);
    t(`${w}: Pulse: its steps: ask the client, record the balance, an admin's release, resolve`, ['Ask the client for the balance', 'Record the balance', 'Or: release it before payment', 'The payment resolves it on its own'].every((x) => body.includes(x)));
    t(`${w}: Pulse: no sideways scroll with the case open`, await noSideways(p));
    await p.screenshot({ path: `${out}/pulse-102-${w}.png` });
    if (w === 390) {
      await p.locator('button:has-text("Record it")').first().click(); await p.waitForTimeout(500);
      t('390: Pulse: Record it opens its form, filled with what\'s left', (await p.locator('input[name=amount]').inputValue()) === '168.00');
      await p.locator('input[name=ref]').fill('2002');
      await p.screenshot({ path: `${out}/pulse-102-form-390.png` });
      await p.locator('button:has-text("Record it")').last().click(); await p.waitForTimeout(2500);   // the case reloads, then says its answer (pulse-ui's done(), pb_said)
      const said = await p.waitForFunction(() => /Payment of \$168\.00 recorded/.test(document.body.innerText), null, { timeout: 6000 }).then(() => true, () => false);
      t('390: Pulse: the answer shows once the case is back ("Payment of $168.00 recorded")', said);
      await p.screenshot({ path: `${out}/pulse-102-recorded-390.png` });
      await p.goto(base + '/admin/backoffice/orders/102#work', { waitUntil: 'load' });
      const page = await p.evaluate(() => document.body.innerText);
      t('390: order 102 moved on by itself once paid: no hold, the order shipped', !page.includes('Held for its balance') && /Shipped/.test(page));
      await shot(p, '#work', 'admin-102-after-390');
    }
    await c.close();
  }
  await b.close();
  console.log(`${ok} passed, ${bad} failed`);
  process.exit(bad ? 1 : 0);
})();
