// The buying runners' token watch (tools/page-audit/paths-staging.mjs watchToken): why the card's token request to
// Intuit failed, as the browser saw it, for that URL only. A page calls a stand-in token URL the way js/go-codes.js does
// (fetch, POST, JSON); Playwright's routing refuses it, answers it 400, answers it 200, or a CSP blocks it. No network.
// Run: node tools/path-checker-tests/token-log.mjs
import { chromium } from 'playwright';
import http from 'node:http';
import { watchToken } from '../page-audit/paths-staging.mjs';

let n = 0, bad = 0;
const t = (what, ok) => { n++; if (!ok) bad++; console.log((ok ? '  ok  ' : 'FAIL  ') + what); };
const TOKEN = 'https://sandbox.api.intuit.com/quickbooks/v4/payments/tokens';
const browser = await chromium.launch();
const page = await browser.newPage();
const call = (url = TOKEN) => page.evaluate(u => fetch(u, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: '{"card":{}}' }).then(r => r.status, () => 'unreachable'), url);
await page.route('https://example.test/**', r => r.fulfill({ status: 200, contentType: 'text/html', body: '<p>page</p>' }));
await page.goto('https://example.test/qr/cart.php');

// refused: a connection error
await page.route(TOKEN, r => r.abort('connectionrefused'));
let w = watchToken(page); let got = await call(); w.stop();
t('a refused token request is seen as unreachable by the page', got === 'unreachable');
t('its cause is kept: the request failed, with the browser\'s reason', w.why.length === 1 && /^the request failed: net::ERR_CONNECTION_REFUSED/.test(w.why[0]));

// another URL failing is not counted
await page.route('https://other.test/**', r => r.abort('failed'));
w = watchToken(page); await call('https://other.test/x'); w.stop();
t('another URL failing is not counted', w.why.length === 0);

// Intuit's own error answer
await page.unroute(TOKEN); await page.route(TOKEN, r => r.fulfill({ status: 400, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: '{"errors":[{"message":"bad card"}]}' }));
w = watchToken(page); got = await call(); w.stop();
t('an error answer is said with its status', got === 400 && w.why.join() === 'Intuit answered 400');

// a good answer leaves nothing
await page.unroute(TOKEN); await page.route(TOKEN, r => r.fulfill({ status: 200, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: '{"value":"tok"}' }));
w = watchToken(page); got = await call(); w.stop();
t('a token given leaves nothing to say', got === 200 && w.why.length === 0);

// CORS: a real second origin whose answer has no Access-Control-Allow-Origin (routing can't stand in for CORS)
const srv = http.createServer((q, r) => { r.writeHead(200, { 'Content-Type': 'application/json' }); r.end('{"value":"tok"}'); });
await new Promise(ok => srv.listen(0, '127.0.0.1', ok));
const far = `http://127.0.0.1:${srv.address().port}/quickbooks/v4/payments/tokens`;
const home = http.createServer((q, r) => { r.writeHead(200, { 'Content-Type': 'text/html' }); r.end('<p>home</p>'); });
await new Promise(ok => home.listen(0, '127.0.0.1', ok));
await page.goto(`http://127.0.0.1:${home.address().port}/qr/cart.php`);
w = watchToken(page); got = await call(far); await page.waitForTimeout(300); w.stop();
t('a CORS refusal is unreachable to the page', got === 'unreachable');
t('and its cause is kept (the failed request, or the browser\'s CORS words)', w.why.length > 0 && w.why.some(x => /CORS|request failed/.test(x)));
srv.close(); home.close();

// CSP: a page whose policy leaves Intuit out
await page.route('https://csp.test/**', r => r.fulfill({ status: 200, contentType: 'text/html', headers: { 'Content-Security-Policy': "connect-src 'self'" }, body: '<p>csp</p>' }));
await page.goto('https://csp.test/qr/cart.php');
w = watchToken(page); got = await call(); await page.waitForTimeout(200); w.stop();
t('a CSP block is unreachable to the page', got === 'unreachable');
t('and it\'s said (Content Security Policy or the blocked request)', w.why.some(x => /Content Security Policy|blocked|csp/i.test(x)));

// stop() really stops
await page.goto('https://example.test/qr/cart.php');
await page.unroute(TOKEN); await page.route(TOKEN, r => r.abort('connectionrefused'));
w = watchToken(page); w.stop(); await call();
t('after stop() nothing more is recorded', w.why.length === 0);

await browser.close();
console.log(`\n${n} checks, ${bad} failed`);
process.exit(bad ? 1 : 0);
