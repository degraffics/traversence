// Staff: send an order (Gate 2, 2026-11-27): a slow Duplicate post no longer reads as "the test copy has no Send".
// A stand-in server: orders/10's Test copy posts, the post takes 12 s (staging copies the proofs), then the copy
// (orders/13) has Send; orders/14 is a copy with none. The path's click waits up to 30 s and copyLanded
// (paths-staging.mjs) waits for the new page and its Send. The old way (an 8 s click, Send looked for at once) is
// kept as the regression: it timed out and saw no Send, as staging did.
// Run: node tools/path-checker-tests/slow-copy.mjs   (no site needed; about a minute)
import { createRequire } from 'node:module';
import http from 'node:http';
import { copyLanded } from '../page-audit/paths-staging.mjs';
const { chromium } = createRequire(import.meta.url)('playwright');
const SLOW = +(process.env.SLOW || 12000);
const page0 = (to) => `<!doctype html><title>Order 10</title><form method="post" action="/dup?to=${to}"><button class="hb-b go">Test copy, billed to you</button></form>`;
const copy = (send) => `<!doctype html><title>Order copy</title><h2>Send it</h2>${send ? '<button id="co-send-btn">Send to the client</button>' : '<p>No Send here</p>'}`;
const srv = http.createServer((q, r) => {
  const u = new URL(q.url, 'http://x');
  if (u.pathname === '/orders/10') { r.setHeader('Content-Type', 'text/html'); return r.end(page0(u.searchParams.get('to') || '13')); }
  if (u.pathname === '/dup') return setTimeout(() => { r.writeHead(303, { Location: '/orders/' + (u.searchParams.get('to') || '13') }); r.end(); }, SLOW);
  if (u.pathname === '/orders/13') { r.setHeader('Content-Type', 'text/html'); return r.end(copy(true)); }
  if (u.pathname === '/orders/14') { r.setHeader('Content-Type', 'text/html'); return r.end(copy(false)); }
  r.writeHead(404); r.end();
});
await new Promise(ok => srv.listen(0, '127.0.0.1', ok));
const SITE = 'http://127.0.0.1:' + srv.address().port;
const b = await chromium.launch(); let bad = 0;
const t = (n, c, x) => { if (!c) bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
const press = async (p, ms) => p.locator('button.hb-b.go').first().click({ timeout: ms }).then(() => 'ok', e => String(e).slice(0, 40));
for (const w of [1280, 390]) {
  const c = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await c.newPage();
  await p.goto(SITE + '/orders/10'); const from = p.url();
  t(`${w} the slow Test copy's click is waited for`, (await press(p, 30000)) === 'ok');
  t(`${w} the copy lands and its Send is found`, await copyLanded(p, from), p.url());
  await p.goto(SITE + '/orders/10?to=14');
  await press(p, 30000);
  t(`${w} a copy with no Send still says so`, !(await copyLanded(p, SITE + '/orders/10?to=14', { show: 2000 })), p.url());
  await c.close();
}
{ // the old way, as Gate 2 ran it: an 8 s click, then Send looked for at once
  const c = await b.newContext(); const p = await c.newPage(); await p.goto(SITE + '/orders/10');
  const r = await press(p, 8000);
  const seen = await p.locator('#co-send-btn').first().isVisible().catch(() => false);
  t('the old way times out and sees no Send, as staging did', r !== 'ok' && !seen, r);
  await c.close();
}
await b.close(); srv.close();
console.log(bad ? `${bad} failed` : 'all passed'); process.exit(bad ? 1 : 0);
