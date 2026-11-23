// tools/order-tests/m1b.cjs <base> <seed.php>: order management M1b rendered on the design harness (staff session
// harness000…0) at 1280 and 390, after m1b-seed.php (run again before each width). QuickBooks in mock mode. pdf.js
// 3.11.174 is served from PDFJS_DIR (npm's pdfjs-dist, the same version as cdnjs) when the test browser can't reach
// cdnjs. Made-up invoices from tools/order-tests/m1b/make-invoice.py. Prints PASS/FAIL; exit 1 on a FAIL. Screens to $SHOTS.
//   §3.6.1   QuickBooks first: the order opens, its hand-made invoice is linked and its payment applied: "Paid in
//            QuickBooks Oct 19 · check 2231", ↗ to the invoice, Order details' QuickBooks row; the checkout line gone
//   §3.6.2–3 the Vendor line: the drop zone (the one uploader), a real upload read without AI (✓ PO, ✓ amount, ≠ ship via),
//            only the mismatch asked, Record greyed until it's answered, the tracking asked when it shipped; Record the
//            vendor bill → Billed $256.83 (inv 130929, Nov 17) · due Dec 17, ↗ to the bill, ✎ greyed (in QuickBooks)
//            an amount billed over the agreed cost, disputed; a scan with no text asks the four fields; by hand;
//            Not billed by them and back; ⋯ open and closed by ×, Esc and a click outside
//   (b)      a payment recorded on the linked invoice says it was received there too
//   board    Record the vendor bill is the Next step for the order whose vendor made it
//   390      no sideways scroll
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path'), os = require('os');
const [base, seed] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3]];
const shots = process.env.SHOTS || '.', pdfjs = process.env.PDFJS_DIR || '';
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + (pass ? '' : '\n     ' + JSON.stringify(saw))); };
const sql = (q) => execFileSync('mysql', ['-uroot', 'tvh', '-N', '-e', q]).toString().trim();
const id = (name) => +sql(`SELECT id FROM orders WHERE client_company = '${name}'`);
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'm1b-'));
for (const v of ['match', 'amount']) execFileSync('python3', [path.join(__dirname, 'm1b/make-invoice.py'), path.join(tmp, `inv-${v}.pdf`), v]);
execFileSync('python3', ['-c', `from reportlab.pdfgen import canvas\nc=canvas.Canvas(${JSON.stringify(path.join(tmp, 'inv-scan.pdf'))}); c.rect(50,50,400,600,fill=1); c.save()`]);
const line = (p, label) => p.evaluate((label) => { const l = [...document.querySelectorAll('.cs-line')].find((x) => x.querySelector('.cs-ll').textContent === label); if (!l) return null;
  const c = l.querySelector('.cs-lt').cloneNode(true); c.querySelectorAll('script, [data-cs-edf], [data-vb-read], [data-vb-drop]').forEach((x) => x.remove());
  return { text: c.textContent.replace(/\s+/g, ' ').trim(), btns: [...l.querySelectorAll('.cs-lb > .hb-b, .cs-lb > .cs-ways > .hb-b, .cs-lb > form > .hb-b')].map((b) => b.textContent.trim()), icons: [...l.querySelectorAll('.cs-la .cs-ib')].map((b) => ({ t: b.getAttribute('title'), off: b.disabled || false, href: b.getAttribute('href') })) }; }, label);
const press = async (p, sel, yes) => {   // a button that asks first: its yes in the site's pop-up, then the page posts
  await p.click(sel); await p.waitForTimeout(250);
  const y = await p.$(`.tv-ask button:has-text("${yes}"), [role=dialog] button:has-text("${yes}")`);
  if (y) await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), y.click()]);
  else await p.waitForLoadState('load').catch(() => {});
  await p.waitForTimeout(600);
};
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    execFileSync('php', [seed]);
    const c = await b.newContext({ viewport: { width: w, height: 900 } });
    await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
    await c.route('**/*', (q) => { const u = q.request().url();
      if (pdfjs && u.startsWith('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/')) return q.fulfill({ path: path.join(pdfjs, u.split('/').pop()), contentType: 'application/javascript' });
      return u.startsWith(base) || u.startsWith('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/') ? q.continue() : q.abort(); });
    const p = await c.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
    const A = id('QB Paid Co'), O = id('QB Open Co'), T = id('Typed Co');
    const open = async (oid) => { await p.goto(`${base}/admin/backoffice/orders/${oid}`, { waitUntil: 'load' }); await p.waitForTimeout(400); };

    // ---- QuickBooks first ----
    await open(A);
    let m = await line(p, 'Money');
    ok(`${w}: Money reads QuickBooks as it opens: Paid in QuickBooks Oct 19 · check 2231 · $1,111.14 on invoice 112906`, m && m.text === 'Paid in QuickBooks Oct 19 · check 2231 · $1,111.14 on invoice 112906.', m);
    ok(`${w}: Money's ↗ opens invoice 112906 in QuickBooks, beside ✎ and ⋯`, m && m.icons[0] && /txnId=9112/.test(m.icons[0].href) && m.icons[0].t === 'Open invoice 112906 in QuickBooks', m && m.icons);
    ok(`${w}: Order details: QuickBooks invoice 112906, linked by its number, with ↗`, await p.evaluate(() => /Invoice 112906, linked by its number/.test(document.body.textContent) && !!document.querySelector('dd a[title="Open invoice 112906 in QuickBooks"]')));
    ok(`${w}: the ledger has the one QuickBooks payment`, sql(`SELECT COUNT(*) FROM order_ledger WHERE order_id = ${A} AND request_key LIKE 'qbpay-%'`) === '1');
    // ---- the Vendor line ----
    let v = await line(p, 'Vendor');
    ok(`${w}: Vendor: $256.83 to the print vendor (work order 20261027-WO0001), not billed yet; Record the bill by hand, ✎, ⋯`, v && v.text === '$256.83 to the print vendor (work order 20261027-WO0001), not billed yet.' && v.btns.includes('Record the bill by hand') && v.icons.length === 2, v);
    ok(`${w}: the drop zone says where it goes: kept with this order's files; not sent to anyone`, await p.evaluate(() => /Kept with this order's files; not sent to anyone/.test((document.querySelector('[data-vb-drop]') || {}).textContent || '')));
    const dl = await line(p, 'Delivery'), od = await line(p, 'Order');
    ok(`${w}: Delivery's one action is Yes, picked up (filled); Remind the client is in its ⋯`, dl && dl.btns.join('|') === 'Yes, picked up' && await p.evaluate(() => !![...document.querySelectorAll('.cs-line')].find((x) => x.querySelector('.cs-ll').textContent === 'Delivery').querySelector('.cs-tk button:not([disabled])[value=remind_got]')), dl);
    ok(`${w}: Complete is greyed "once it's picked up" while Delivery still asks`, await p.evaluate(() => { const l = [...document.querySelectorAll('.cs-line')].find((x) => x.querySelector('.cs-ll').textContent === 'Order'); const b = [...l.querySelectorAll('.cs-lb > .hb-b')].find((x) => x.textContent === 'Complete'); return !!b && b.disabled && /answer the Delivery line first/.test(b.title) && /once it's picked up/.test(l.querySelector('.cs-la > small').textContent); }));
    // ⋯ open and closed three ways
    const vi = await p.evaluate(() => [...document.querySelectorAll('.cs-line')].findIndex((x) => x.querySelector('.cs-ll').textContent === 'Vendor'));
    for (const how of ['×', 'Esc', 'a click outside']) {
      await p.click(`.cs-line:nth-of-type(${vi + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
      const tk = await p.evaluate((i) => { const m = document.querySelectorAll('.cs-line')[i].querySelector('.cs-tk'); const r = m.getBoundingClientRect(); return { open: !m.hidden, items: [...m.querySelectorAll('.cs-tki')].map((x) => [x.querySelector('button, a').textContent, !!x.querySelector('button[disabled]'), x.querySelector('small').textContent]), inView: r.left >= 0 && r.right <= innerWidth }; }, vi);
      if (how === '×') ok(`${w}: Vendor ⋯: Drop their invoice · Not billed by them · Dispute… greyed (nothing billed yet) · Cancel the record greyed · Work orders, in the window`,
        tk.open && tk.inView && tk.items.map((x) => x[0]).join('|') === 'Drop their invoice|Not billed by them|Dispute…|Cancel the record|Work orders' && tk.items[2][1] && tk.items[2][2] === 'Nothing billed yet to dispute' && tk.items[3][1] && !tk.items[0][1], tk);
      if (how === '×') await p.click(`.cs-line:nth-of-type(${vi + 1}) [data-cs-tkx]`); else if (how === 'Esc') await p.keyboard.press('Escape'); else await p.mouse.click(5, 880);
      await p.waitForTimeout(150);
      ok(`${w}: Vendor ⋯ closes by ${how}`, await p.evaluate(() => [...document.querySelectorAll('.cs-tk')].every((m) => m.hidden)));
    }
    // a real upload, read in the browser
    await p.setInputFiles('[data-vb-file]', path.join(tmp, 'inv-match.pdf'));
    await p.waitForSelector('[data-vb-read]:not([hidden]) .vb-tbl', { timeout: 20000 }).catch(() => {});
    let rd = await p.evaluate(() => { const r = document.querySelector('[data-vb-read]'); if (!r || r.hidden) return null; return { head: r.querySelector('.vb-q').textContent, rows: [...r.querySelectorAll('.vb-tbl tr')].map((t) => [...t.cells].map((c) => c.textContent)), ans: [...r.querySelectorAll('.vb-ans button')].map((x) => x.textContent), rec: r.querySelector('.vb-btns .go').disabled, zoneHidden: document.querySelector('[data-vb-drop]').hidden }; });
    ok(`${w}: the dropped invoice is kept as the order's file (kind invoice)`, sql(`SELECT COUNT(*) FROM order_files WHERE order_id = ${A} AND kind = 'invoice'`) === '1');
    ok(`${w}: read without AI: ✓ PO = our work order, ✓ the agreed cost, ✓ tax and shipping, ≠ ship via (Will Call)`, rd && rd.rows[0][2] === '✓ our work order' && rd.rows[1][2] === '✓ the agreed cost' && rd.rows[2][2] === '✓ ' && rd.rows[3][1] === 'USPS Priority Mail' && rd.rows[3][2] === '≠ Will Call' && rd.zoneHidden, rd);
    ok(`${w}: only the mismatch is asked, with its two answers, and Record waits for it (greyed), saying why in words`, rd && /The vendor says it shipped by USPS Priority Mail; the work order says Will Call\. Which is right\?/.test(rd.head) && rd.ans.join('|') === 'It was picked up|It shipped: add tracking' && rd.rec && await p.evaluate(() => document.querySelector('[data-vb-read] .vb-why').textContent === 'Answer the shipping question first'), rd);
    await p.click('[data-vb-read] .vb-ans button:has-text("It shipped: add tracking")'); await p.waitForTimeout(100);
    ok(`${w}: "It shipped" asks for its tracking`, await p.evaluate(() => !!document.querySelector('[data-vb-read] input[name=tracking]')));
    await p.click('[data-vb-read] .vb-ans button:has-text("It was picked up")'); await p.waitForTimeout(100);
    ok(`${w}: "It was picked up" takes the tracking away, shows ✓ chosen with the other dimmed, and Record the vendor bill is ready, the QuickBooks line under it`, await p.evaluate(() => { const r = document.querySelector('[data-vb-read]'), [a, b] = r.querySelectorAll('.vb-ans button');
      return !r.querySelector('input[name=tracking]') && !r.querySelector('.vb-btns .go').disabled && a.getAttribute('aria-pressed') === 'true' && getComputedStyle(a, '::before').content.includes('✓') && b.classList.contains('vb-dim') && /picked up is recorded on the Delivery line too/.test(r.querySelector('.vb-why').textContent); }));
    await p.screenshot({ path: `${shots}/m1b-read-${w}.png`, fullPage: false });
    await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('[data-vb-read] .vb-btns .go')]); await p.waitForTimeout(700);
    v = await line(p, 'Vendor');
    ok(`${w}: Record the vendor bill → Billed $256.83 (inv 130929, Nov 17) · due Dec 17, ↗ to their bill, ✎ greyed (it's in QuickBooks)`, v && v.text === 'Billed $256.83 (inv 130929, Nov 17) · due Dec 17.' && /bill/.test(v.icons[0].href || '') && v.icons[1].off && /change it there/.test(v.icons[1].t), v);
    ok(`${w}: the bill is in QuickBooks (mock), its answer kept`, sql(`SELECT CONCAT(qb_bill_id, '|', source, '|', answers LIKE '%It was picked up%') FROM order_vendor_bills WHERE order_id = ${A}`) === `MBL-${sql(`SELECT id FROM order_vendor_bills WHERE order_id = ${A}`)}|read|1`);
    ok(`${w}: the page says what happened (Success), the pickup too`, await p.evaluate(() => /Recorded Competition Proofs's invoice 130929/.test(document.body.textContent) && /Picked up is recorded on the Delivery line too/.test(document.body.textContent)));
    const dl2 = await line(p, 'Delivery');
    ok(`${w}: one fact, answered once: Vendor's "It was picked up" recorded the pickup on Delivery (paid, so the order completed)`, dl2 && /^Delivered on .*, recorded by Staff\.$/.test(dl2.text) && sql(`SELECT stage FROM orders WHERE id = ${A}`) === 'complete', dl2);
    await p.click(`.cs-line:nth-of-type(${vi + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
    const tk2 = await p.evaluate((i) => [...document.querySelectorAll('.cs-line')[i].querySelectorAll('.cs-tki')].map((x) => [x.querySelector('button, a').textContent, !!x.querySelector('button[disabled]'), x.querySelector('small').textContent]), vi);
    ok(`${w}: billed ⋯: Drop greyed, Not billed greyed (billed already), Dispute… open, Cancel the record greyed (void it in QuickBooks)`, tk2[0][1] && tk2[1][1] && tk2[1][2] === 'They billed it already' && !tk2[2][1] && tk2[3][1] && /void it there/.test(tk2[3][2]), tk2);
    await p.click(`.cs-line:nth-of-type(${vi + 1}) .cs-tk button:has-text("Dispute…")`); await p.waitForTimeout(500);
    await p.fill('#vb-disp-why', 'Billed shipping on a pickup');
    await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('form:has(#vb-disp-why) button.go')]); await p.waitForTimeout(500);
    v = await line(p, 'Vendor');
    ok(`${w}: Dispute… → Disputed: their invoice 130929 for $256.83 (Billed shipping on a pickup)`, v && v.text === 'Disputed: their invoice 130929 for $256.83 (Billed shipping on a pickup).', v);
    ok(`${w}: no sideways scroll on the order`, await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await p.screenshot({ path: `${shots}/m1b-billed-${w}.png` });

    // ---- the other way: Yes, picked up on Delivery first, then the read finds it answered ----
    const K = id('Picked Co'); await open(K);
    await press(p, '.cs-lines button[value=picked_up]', 'Record it');
    await p.setInputFiles('[data-vb-file]', path.join(tmp, 'inv-match.pdf'));
    await p.waitForSelector('[data-vb-read]:not([hidden]) .vb-tbl', { timeout: 20000 }).catch(() => {});
    rd = await p.evaluate(() => { const r = document.querySelector('[data-vb-read]'); return r && !r.hidden ? { rows: [...r.querySelectorAll('.vb-tbl tr')].map((t) => [...t.cells].map((c) => c.textContent)), qs: r.querySelectorAll('.vb-ans').length, rec: r.querySelector('.vb-btns .go').disabled } : null; });
    ok(`${w}: Delivery answered first: the read says ✓ Will Call, picked up (today), asks nothing, and Record is ready`, rd && /^✓ Will Call, picked up [A-Z][a-z]{2} \d+$/.test(rd.rows[3][2]) && rd.qs === 0 && !rd.rec, rd);
    await p.screenshot({ path: `${shots}/m1b-answered-${w}.png` });

    // ---- an amount over the agreed cost, on Typed Co's two work orders: the PO names WO0001 (not this order) ----
    await open(T);
    await p.setInputFiles('[data-vb-file]', path.join(tmp, 'inv-amount.pdf'));
    await p.waitForSelector('[data-vb-read]:not([hidden]) .vb-ans', { timeout: 20000 }).catch(() => {});
    rd = await p.evaluate(() => { const r = document.querySelector('[data-vb-read]'); return r && !r.hidden ? { qs: [...r.querySelectorAll('.vb-q')].map((x) => x.textContent), ans: [...r.querySelectorAll('.vb-ans')].map((x) => [...x.querySelectorAll('button')].map((b) => b.textContent)) } : null; });
    ok(`${w}: a PO that isn't this order's work order is asked first ("Is it this order's bill?")`, rd && /Their PO 20261027-WO0001 isn't this order's work order/.test(rd.qs[0]) && rd.ans[0].join('|') === 'Yes, it\'s this order\'s|No: another order\'s', rd);
    await p.click('[data-vb-read] .vb-ans button:has-text("No: another order")'); await p.waitForTimeout(100);
    ok(`${w}: "another order's" keeps Record greyed, with why`, await p.evaluate(() => { const g = document.querySelector('[data-vb-read] .vb-btns .go'); return g.disabled && /another order/.test(g.title); }));
    await p.click('[data-vb-read] > button.cs-ib'); await p.waitForTimeout(100);
    ok(`${w}: the read invoice closes (×) and the drop zone is back`, await p.evaluate(() => document.querySelector('[data-vb-read]').hidden && !document.querySelector('[data-vb-drop]').hidden));
    // a scan: no text, the four fields asked
    await p.setInputFiles('[data-vb-file]', path.join(tmp, 'inv-scan.pdf'));
    await p.waitForSelector('[data-vb-read]:not([hidden]) input[name=bill_no]', { timeout: 20000 }).catch(() => {});
    ok(`${w}: a scan with no text says so and asks the four key fields (and which work order)`, await p.evaluate(() => { const r = document.querySelector('[data-vb-read]'); return !r.hidden && /no text to read/.test(r.textContent) && ['bill_no', 'bill_date', 'total', 'due_date'].every((n) => r.querySelector(`input[name=${n}]`)) && !!r.querySelector('select[name=wo]'); }));
    await p.fill('[data-vb-read] input[name=bill_no]', 'MB-12'); await p.fill('[data-vb-read] input[name=total]', '45.00'); await p.selectOption('[data-vb-read] select[name=wo]', { index: 1 });
    await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('[data-vb-read] .vb-btns .go')]); await p.waitForTimeout(600);
    v = await line(p, 'Vendor');
    ok(`${w}: the scan's bill recorded for Mesa Bindery: $45.00 billed of $345.00, $300.00 not billed yet`, v && v.text === '$45.00 billed of $345.00 to the vendors (work orders 20261102-WO0003, 20261102-WO0004); $300.00 not billed yet.', v);
    // by hand
    await p.click('.cs-line button:has-text("Record the bill by hand")'); await p.waitForTimeout(500);
    ok(`${w}: Record the bill by hand opens its form at Their invoice #`, await p.evaluate(() => document.activeElement && document.activeElement.id === 'vb-no'));
    await p.click('form:has(#vb-no) [data-vb-shut]'); await p.waitForTimeout(100);
    ok(`${w}: its Cancel closes it`, await p.evaluate(() => document.getElementById('vb-no').closest('[data-cs-edf]').hidden));
    await p.click('.cs-line button:has-text("Record the bill by hand")'); await p.waitForTimeout(500);
    await p.fill('#vb-no', 'A-77'); await p.fill('form:has(#vb-no) input[name=total]', '300.00'); await p.selectOption('form:has(#vb-no) select[name=wo]', { index: 0 });
    await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('form:has(#vb-no) button.go')]); await p.waitForTimeout(600);
    v = await line(p, 'Vendor');
    ok(`${w}: by hand: Billed $345.00 on 2 invoices`, v && v.text.startsWith('Billed $345.00 on 2 invoices'), v);

    // ---- Not billed by them, and back (Billed Co's sibling: QB Open Co has no work order, so use Mesa's? use Typed Co after cancelling) ----
    const vi2 = await p.evaluate(() => [...document.querySelectorAll('.cs-line')].findIndex((x) => x.querySelector('.cs-ll').textContent === 'Vendor'));
    // the newest bill isn't in QuickBooks (mock makes it so): cancel both records first through ⋯ (only before QuickBooks)
    sql(`UPDATE order_vendor_bills SET qb_bill_id = NULL WHERE order_id = ${T}`);
    for (let k = 0; k < 2; k++) {
      await open(T); await p.click(`.cs-line:nth-of-type(${vi2 + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
      await press(p, `.cs-line:nth-of-type(${vi2 + 1}) .cs-tk button:has-text("Cancel the record")`, 'Cancel it');
    }
    v = await line(p, 'Vendor');
    ok(`${w}: Cancel the record (before QuickBooks) twice: not billed yet again`, v && /not billed yet\.$/.test(v.text), v);
    await p.click(`.cs-line:nth-of-type(${vi2 + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
    await p.click(`.cs-line:nth-of-type(${vi2 + 1}) .cs-tk button:has-text("Not billed by them")`); await p.waitForTimeout(500);
    await p.fill('#vb-none-why', 'A remake on them');
    await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('form:has(#vb-none-why) button.go')]); await p.waitForTimeout(500);
    v = await line(p, 'Vendor');
    ok(`${w}: Not billed by them: A remake on them`, v && v.text.startsWith('Not billed by them: A remake on them'), v);
    await p.click(`.cs-line:nth-of-type(${vi2 + 1}) [data-cs-tk]`); await p.waitForTimeout(200);
    await press(p, `.cs-line:nth-of-type(${vi2 + 1}) .cs-tk button:has-text("They billed after all")`, 'Yes');
    v = await line(p, 'Vendor');
    ok(`${w}: They billed after all: the drop zone is back`, v && /not billed yet\.$/.test(v.text) && await p.evaluate(() => !!document.querySelector('[data-vb-drop]')), v);

    // ---- (b): a payment recorded on the linked invoice ----
    await open(O);
    ok(`${w}: QB Open Co: Order details say invoice 112907 is linked by its number`, await p.evaluate(() => /Invoice 112907, linked by its number/.test(document.body.textContent)));
    await p.fill('#co-pay-amt', '100.00'); await p.selectOption('form:has(#co-pay-amt) select[name=method]', 'check'); await p.fill('form:has(#co-pay-amt) input[name=ref]', '5512');
    await press(p, 'form:has(#co-pay-amt) button.go', 'Record');
    ok(`${w}: (b) the recorded check is received on QuickBooks invoice 112907 too (the history)`, sql(`SELECT note FROM order_events WHERE order_id = ${O} AND event = 'payment_recorded' ORDER BY id DESC LIMIT 1`).includes('Received on QuickBooks invoice 112907'));

    // ---- the stale checkout line: released and handled outside, nothing chosen ----
    await open(id('Mesa Ridge Lodge'));
    ok(`${w}: a released order handled outside: no "The client's checkout" line`, await p.evaluate(() => !/The client's checkout:/.test(document.body.textContent)));

    // ---- the board ----
    await p.goto(`${base}/admin/backoffice/orders`, { waitUntil: 'domcontentloaded' });
    await p.waitForFunction(() => document.querySelector('tbody tr[data-id]'), null, { timeout: 15000 }).catch(() => {});
    await p.waitForTimeout(600);
    const nx = await p.evaluate((A) => { const r = document.querySelector(`tbody tr[data-id="${A}"] [data-col=next]`); return r ? r.textContent : null; }, T);
    ok(`${w}: the board: Typed Co's Next step is Record the vendor bill (Needs us), its vendors having shipped`, nx && /Record the vendor bill/.test(nx), nx);
    ok(`${w}: no script errors`, !errs.length, errs);
    await c.close();
  }
  await b.close(); fs.rmSync(tmp, { recursive: true, force: true });
  console.log(fails ? `\n${fails} FAILED` : '\nall passed'); process.exit(fails ? 1 : 0);
})();
