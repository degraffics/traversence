// tools/order-tests/m2b.cjs <base> <root>: order management M2b, how it goes (decisions/0101's note, 2026-11-24; mockup
// handoffs/order-m2b/), rendered on the design harness at 1280 and 390, after m2b-seed.php <root> (run again before each
// width). Prints PASS/FAIL; exit 1 on a FAIL. Screens in $SHOTS. Each button is pressed in the page:
//   Order #12's case: a Will Call ready at the vendor says so, its Receive lands on the work order's Receive form
//   Delivery ⋯: Courier booked: add tracking (a courier we book collects it) and Change how it goes…, each with its meaning
//   the courier: saved, the line says Out by courier from the vendor; ✎ edits it in place
//   Change how it goes…: the method's meaning follows the choice; Tell the vendor only when it changed; × Esc Cancel and a
//     click outside close it; saved, the line's words follow; an item's courier number sends it out from our shop
//   received, Courier with no number: Add the courier's number opens the item's shipping at Tracking
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const [base, root] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3]];
const shots = process.env.SHOTS || '.';
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + (pass ? '' : '\n     ' + JSON.stringify(saw))); };
const sql = (q) => execFileSync('mysql', ['-utv', '-ptv', 'tvh', '-N', '-e', q]).toString().trim();
const id = (name) => +sql(`SELECT id FROM orders WHERE client_company = '${name}'`);
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    execFileSync('php', [__dirname + '/m2b-seed.php', root], { env: { ...process.env, QB_ENV: 'mock' }, stdio: ['ignore', 'ignore', 'ignore'] });
    const c = await b.newContext({ viewport: { width: w, height: 900 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
    const p = await c.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
    const open = async (n) => { await p.goto(base + '/admin/backoffice/orders/' + n, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(400); };
    const dIdx = () => p.evaluate(() => [...document.querySelectorAll('.cs-line')].findIndex((x) => /^Delivery/.test(x.textContent.trim())));
    const dLine = () => p.evaluate(() => { const l = [...document.querySelectorAll('.cs-line')].find((x) => /^Delivery/.test(x.textContent.trim())); return l ? l.textContent.replace(/\s+/g, ' ').trim() : ''; });
    const oLine = () => p.evaluate(() => { const l = [...document.querySelectorAll('.cs-line')].find((x) => /^Order/.test(x.textContent.trim())); return l ? l.textContent.replace(/\s+/g, ' ').trim() : ''; });
    const toolkit = async () => { const i = await dIdx(); await p.click(`.cs-line:nth-of-type(${i + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
      return p.evaluate((i) => [...document.querySelectorAll('.cs-line')[i].querySelectorAll('.cs-tki')].map((x) => ({ t: x.querySelector('button, a').textContent, off: !!x.querySelector('button[disabled]'), say: (x.querySelector('small') || {}).textContent || '' })), i); };
    const pick = async (label) => { const i = await dIdx(); await p.click(`.cs-line:nth-of-type(${i + 1}) .cs-tki button:has-text("${label}")`); await p.waitForTimeout(500); };
    const vis = (sel) => p.evaluate((sel) => { const x = document.querySelector(sel); return !!x && !x.hidden && !!x.offsetParent; }, sel);
    const wide = () => p.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    const flash = () => p.evaluate(() => (document.querySelector('[data-tv-flash]') || {}).textContent || '');
    const submit = async (sel) => { await Promise.all([p.waitForNavigation({ timeout: 10000 }).catch(() => {}), p.click(sel)]); await p.waitForTimeout(500); };

    // ---- Order #12's case: a Will Call ready at the vendor ----
    const rd = id('Leg Ready Co'); await open(rd);
    let dl = await dLine();
    ok(`${w}: a Will Call ready at the vendor says it's ours to collect, since when`, /Ready at Competition Printing for us to collect, since \w+ \d+ \(22 days\)/.test(dl) && !/Ready for pickup/.test(dl), dl);
    ok(`${w}: the leg's words under it: Will Call · work order`, /Will Call · work order 20261027-CP/.test(dl), dl);
    ok(`${w}: the Order line isn't Out the door`, /Ready at the vendor/.test(await oLine()) && !/Out the door/.test(await oLine()), await oLine());
    ok(`${w}: the action is said once, on the Delivery line: the Order line has no Follow the work order`, !/Follow the work order/.test(await oLine()), await oLine());
    ok(`${w}: the Shipping box doesn't call the vendor's Will Call a pickup at our shop`, !(await p.evaluate(() => /Pickup at our shop/.test(document.body.innerText))));
    ok(`${w}: no sideways scroll`, !(await wide()));
    await p.screenshot({ path: `${shots}/m2b-ready-${w}.png` });
    const items = await toolkit();
    const cb = items.find((x) => x.t === 'Courier booked: add tracking'), ch = items.find((x) => x.t === 'Change how it goes…');
    ok(`${w}: Delivery ⋯ has Courier booked: add tracking, with its meaning`, cb && !cb.off && /A courier we book collects it from Competition Printing; the vendor is emailed its number/.test(cb.say), items);
    ok(`${w}: and Change how it goes…, with its meaning`, ch && !ch.off && /The work order's delivery, or how each item goes from our shop/.test(ch.say), items);
    ok(`${w}: Yes, picked up isn't offered as if it were at our shop`, !items.some((x) => x.t === 'Yes, picked up' && !x.off), items);
    await p.screenshot({ path: `${shots}/m2b-ready-toolkit-${w}.png` });
    await p.keyboard.press('Escape'); await p.waitForTimeout(200);
    const i0 = await dIdx(); await p.click(`.cs-line:nth-of-type(${i0 + 1}) button:has-text("Receive")`); await p.waitForTimeout(700);
    ok(`${w}: Receive lands on the work order's Receive form, open, on Received by`, await p.evaluate(() => { const a = document.activeElement; return !!a && /^wo-rcv-by-/.test(a.id) && a.closest('details').open; }));

    // ---- the courier we book ----
    await open(rd); await toolkit(); await pick('Courier booked: add tracking');
    ok(`${w}: Courier booked opens its pane, on the courier's name`, await vis('#co-cour-p') && await p.evaluate(() => document.activeElement && document.activeElement.id === 'co-cour-trk'));
    await p.screenshot({ path: `${shots}/m2b-courier-pane-${w}.png` });
    await p.fill('#co-cour-trk', 'Rapid Couriers'); await p.fill('#co-cour-p input[name=tracking]', '55821');
    await submit('#co-cour-p button:has-text("Save the courier")');
    ok(`${w}: saved, and never says emailed when the email didn't go (the harness has no mail server)`, /^Courier saved; the email to Competition Printing didn't go, so tell them yourself\.$/.test((await flash()).replace(/^\s*(Success:\s*)?/, '').trim()), await flash());
    dl = await dLine();
    ok(`${w}: the line says Out by courier from the vendor, with its number`, /Out by courier from Competition Printing: Rapid Couriers · 55821/.test(dl) && /Out today\. Delivered\?/.test(dl), dl);
    ok(`${w}: and its one action is Yes, delivered`, await p.evaluate(() => [...document.querySelectorAll('.cs-line')].find((x) => /^Delivery/.test(x.textContent.trim())).querySelector('button.go, .cs-la button[type=submit]') !== null));
    await p.screenshot({ path: `${shots}/m2b-courier-out-${w}.png` });
    const i1 = await dIdx(); await p.click(`.cs-line:nth-of-type(${i1 + 1}) button[title="Edit the courier and its number"]`); await p.waitForTimeout(600);
    ok(`${w}: ✎ edits the courier in place, filled in`, await vis('#co-cour-p') && (await p.inputValue('#co-cour-trk')) === 'Rapid Couriers');
    await p.keyboard.press('Escape'); await p.waitForTimeout(200);
    ok(`${w}: Esc closes it`, !(await vis('#co-cour-p')));
    ok(`${w}: the work order's History says so`, /Courier booked: Rapid Couriers 55821/.test(sql(`SELECT l.note FROM order_work_order_log l JOIN order_work_orders w ON w.id = l.wo_id WHERE w.order_id = ${rd} ORDER BY l.id DESC LIMIT 1`)));

    // ---- Change how it goes… ----
    const mk = id('Leg Make Co'); await open(mk);
    ok(`${w}: in production: At the vendor, with Will Call: we collect it.`, /At the vendor: work order .* at Competition Printing, in production\. Will Call: we collect it\./.test(await dLine()), await dLine());
    await toolkit(); await pick('Change how it goes…');
    ok(`${w}: Change how it goes… opens on the work order's Delivery`, await vis('#co-howgo-p') && await p.evaluate(() => document.activeElement && document.activeElement.id === 'co-howgo'));
    const means = () => p.evaluate(() => document.querySelector('#co-howgo-p [data-hg-means]').textContent);
    const tell = () => vis('#co-howgo-p [data-hg-tell]');
    ok(`${w}: its meaning in a line; Tell the vendor hidden while nothing changed`, /We collect it from the vendor, or a courier we book does/.test(await means()) && !(await tell()), await means());
    await p.selectOption('#co-howgo', 'blind_drop'); await p.waitForTimeout(150);
    ok(`${w}: Blind Drop Ship: its meaning, and Tell the vendor shows, ticked`, /straight to the client, under our name/.test(await means()) && await tell() && await p.isChecked('#co-howgo-p [data-hg-tell] input'), await means());
    await p.screenshot({ path: `${shots}/m2b-howgo-${w}.png` });
    ok(`${w}: no sideways scroll with it open`, !(await wide()));
    await p.click('#co-howgo-p .mo-paneh [data-hg-shut]'); await p.waitForTimeout(150);
    ok(`${w}: × closes it`, !(await vis('#co-howgo-p')));
    await toolkit(); await pick('Change how it goes…'); await p.click('#co-howgo-p button:has-text("Cancel")'); await p.waitForTimeout(150);
    ok(`${w}: Cancel closes it`, !(await vis('#co-howgo-p')));
    await toolkit(); await pick('Change how it goes…'); await p.mouse.click(200, 20); await p.waitForTimeout(150);
    ok(`${w}: a click outside closes it`, !(await vis('#co-howgo-p')));
    await toolkit(); await pick('Change how it goes…'); await p.selectOption('#co-howgo', 'blind_drop'); await p.uncheck('#co-howgo-p [data-hg-tell] input');
    await submit('#co-howgo-p button:has-text("Save how it goes")');
    ok(`${w}: saved: How it goes is saved (Tell the vendor unticked: no email said)`, /How it goes is saved\.$/.test((await flash()).trim()), await flash());
    ok(`${w}: the line follows: Blind Drop Ship: straight to the client.`, /Blind Drop Ship: straight to the client\./.test(await dLine()), await dLine());
    ok(`${w}: its History keeps the old value`, /Delivery changed from Will Call to Blind Drop Ship/.test(sql(`SELECT l.note FROM order_work_order_log l JOIN order_work_orders w ON w.id = l.wo_id WHERE w.order_id = ${mk} ORDER BY l.id DESC LIMIT 1`)));

    // ---- received at our shop, Courier with no number ----
    const sd = id('Leg Send Co'); await open(sd);
    dl = await dLine();
    ok(`${w}: received, by courier from our shop, waiting for its number`, /Received from Competition Printing \w+ \d+; it goes by courier from our shop\. Courier: its number once it's booked\./.test(dl), dl);
    ok(`${w}: the Order line: At our shop`, /At our shop/.test(await oLine()), await oLine());
    await p.screenshot({ path: `${shots}/m2b-received-${w}.png` });
    const i2 = await dIdx(); await p.click(`.cs-line:nth-of-type(${i2 + 1}) button:has-text("Add the courier's number")`); await p.waitForTimeout(500);
    ok(`${w}: Add the courier's number opens the item's shipping at Tracking`, await p.evaluate(() => [...document.querySelectorAll('#co-ship, #co-lots')].some((d) => !d.hidden) && (!document.querySelector('#co-ship') || document.querySelector('#co-ship').hidden || document.activeElement.name === 'tracking')));
    await p.keyboard.press('Escape'); await p.waitForTimeout(200);
    await open(sd); await toolkit(); await pick('Change how it goes…');
    ok(`${w}: with nothing out at the vendor, it opens on the item's Ship style (Courier)`, await p.evaluate(() => document.activeElement && document.activeElement.id === 'co-howgo' && document.activeElement.value === 'courier'));
    await p.fill('#co-howgo-p input[name^=ship_tracking]', 'Rapid Couriers 55830');
    await submit('#co-howgo-p button:has-text("Save how it goes")');
    dl = await dLine();
    ok(`${w}: its number sends it out: Out by courier from our shop, Delivered?`, /Out by courier from our shop: Rapid Couriers 55830/.test(dl) && /Delivered\?/.test(dl), dl);
    await p.screenshot({ path: `${shots}/m2b-out-from-us-${w}.png` });

    // ---- straight to the client, and pickup at our shop ----
    await open(id('Leg Blind Co'));
    ok(`${w}: Blind Drop Ship shipping: from the vendor to the client`, /Shipping from the vendor to the client: FedEx · 7712 3456 7890/.test(await dLine()), await dLine());
    await open(id('Leg Pick Co'));
    ok(`${w}: received and Pick Up: Ready for pickup at our shop, received from the vendor`, /Ready for pickup at our shop\. Received from Competition Printing/.test(await dLine()), await dLine());
    ok(`${w}: no script errors`, !errs.length, errs);
    await c.close();
  }
  await b.close();
  console.log(fails ? `\n${fails} failed` : '\nall passed');
  process.exit(fails ? 1 : 0);
})();
