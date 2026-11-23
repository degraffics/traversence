// tools/order-tests/release-a.cjs <base> <order id>: the order page's Add forms in the browser, on the design harness
// (staff session harness000…0). A double press of Add it is one request (its one-time key, client-order.php "once"):
// the same form sent twice at once makes one line (the ordering survey, #15); "abc" as How many is refused with words
// (#14). Prints PASS/FAIL; exit 1 on a FAIL.
const { chromium } = require('playwright');
const [base, oid] = [process.argv[2] || 'http://127.0.0.1:8790', process.argv[3]];
let fails = 0; const ok = (what, pass, saw) => { if (!pass) fails++; console.log((pass ? 'PASS ' : 'FAIL ') + what + '\n     ' + saw); };
(async () => {
  const b = await chromium.launch();
  const c = await b.newContext({ viewport: { width: 1280, height: 900 } });
  await c.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: base }]);
  await c.route('**/*', (q) => q.request().url().startsWith(base) ? q.continue() : q.abort());
  const p = await c.newPage();
  const lines = async () => { await p.goto(base + '/admin/backoffice/orders/' + oid, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(600); return p.evaluate(() => new Set([...document.querySelectorAll('[data-line]')].filter((e) => /Double press check/.test(e.textContent)).map((e) => e.getAttribute('data-line'))).size); };   // distinct item rows
  const before = await lines();
  // the Custom form, sent twice at once with the same fields (what a double click sends)
  const sent = await p.evaluate(async () => {
    const f = [...document.querySelectorAll('form')].find((x) => x.querySelector('input[name=do][value=add_custom]'));
    const d = new FormData(f); d.set('title', 'Double press check'); d.set('qty', '1'); d.set('price', '12.00');
    const send = () => fetch(location.href, { method: 'POST', body: new URLSearchParams(d), credentials: 'same-origin', redirect: 'manual' }).then((r) => r.status);
    return Promise.all([send(), send()]);
  });
  const after = await lines();
  ok('A double press of Add it adds one line', after - before === 1, `sent ${JSON.stringify(sent)}; lines named "Double press check": ${before} → ${after}`);
  // "abc" as How many
  // the reply page carries the notice once (the flash is read on the page the post lands on)
  const flash = await p.evaluate(async () => {
    const f = [...document.querySelectorAll('form')].find((x) => x.querySelector('input[name=do][value=add_custom]'));
    const d = new FormData(f); d.set('title', 'Quantity check'); d.set('qty', 'abc'); d.set('price', '5.00');
    const html = await (await fetch(location.href, { method: 'POST', body: new URLSearchParams(d), credentials: 'same-origin' })).text();
    const el = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-tv-flash]');
    return el ? el.textContent : '';
  });
  await p.goto(base + '/admin/backoffice/orders/' + oid, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(600);
  const added = await p.evaluate(() => [...document.querySelectorAll('[data-line]')].some((e) => /Quantity check/.test(e.textContent)));
  ok('"abc" as How many is refused with words, nothing added', /whole number/.test(flash) && !added, `notice: "${flash.trim().slice(0, 140)}"; added: ${added}`);
  await b.close();
  console.log(fails ? `${fails} failed` : 'all passed');
  process.exit(fails ? 1 : 0);
})();
