// Events E2 rendered (decisions/0134 §5, its minimum: the note of 2026-11-27): an event's page at 1280 and 390 to the
// content-page standard (0058 §20–21), a cancelled one, one not there, and the event on its listing's "Coming up here"
// and its town's guide. Run on the design harness with a published event (EV), a cancelled one (OFF) at listing BIZ in
// town CLUSTER: node event-page.cjs <shots dir>
const { chromium } = require('playwright'); const fs = require('fs');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.';
const H = process.env.HARNESS || 'http://127.0.0.1:8790', EV = process.env.EV || '5', OFF = process.env.OFF || '6', BIZ = process.env.BIZ || '7', CLUSTER = process.env.CLUSTER || 'show-low';
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [1280, 390]) {
  const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800, userAgent: w < 800 ? 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36' : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36' });
  await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  await p.goto(H + '/event/?id=' + EV, { waitUntil: 'load' }); await p.waitForTimeout(900);
  const head = p.locator('#evp');
  t(w + ' the hero, then the header card', await p.locator('.xh, [class*="xh-"]').first().count() === 1 && await head.count() === 1);
  t(w + ' its kind is a pill that searches', await head.locator('.evp-pill[data-q]').count() === 1);
  t(w + ' the title is a heading, not a link', await head.locator('h1.evp-h').count() === 1 && await head.locator('h1 a').count() === 0);
  t(w + ' the Link button top right', await head.locator('[data-link="place:ev:' + EV + '"]').count() === 1);
  const facts = await head.locator('.evp-facts').innerText();
  t(w + ' when (with its zone), where (the host), cost', /When[\s\S]*M[SD]T[\s\S]*Where[\s\S]*the host[\s\S]*Cost[\s\S]*Tickets/i.test(facts), facts.replace(/\s+/g, ' ').slice(0, 160));
  t(w + ' Get tickets opens the host\'s Marketplace item', /\/market\/item\.php\?id=\d+/.test(await head.locator('a.evp-go').getAttribute('href') || ''));
  t(w + ' the engage bar: views, Share and the QR', await head.locator('.eg-bar').count() === 1);
  const st = p.locator('section.evp-card:has(h2:has-text("Showtimes"))');
  t(w + ' showtimes as information only: no link, no button', await st.count() === 1 && await st.locator('a, button').count() === 0);
  t(w + ' comments at the end', await p.locator('[id*="comment"], .eg-comments, [class*="eg-c"]').count() > 0);
  t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  await p.screenshot({ path: out + '/ev2-page-' + w + '.png', fullPage: true });
  await p.goto(H + '/event/?id=' + OFF, { waitUntil: 'load' }); await p.waitForTimeout(600);
  t(w + ' a cancelled event says so, kept, with what else is on; no Get tickets', /Cancelled:/.test(await p.innerText('#evp')) && await p.locator('a.evp-go').count() === 0 && await p.locator('#evp a[href*="/listing/view.php"]').count() >= 1);
  await p.screenshot({ path: out + '/ev2-cancelled-' + w + '.png' });
  const r = await p.goto(H + '/event/?id=999999', { waitUntil: 'load' });
  t(w + ' one not there: 404 with a way forward', r.status() === 404 && /Not here:/.test(await p.innerText('body')) && await p.locator('a[href="/directory/"]').count() >= 1);
  await p.goto(H + '/listing/view.php?id=' + BIZ, { waitUntil: 'load' }); await p.waitForTimeout(800);
  const cu = p.locator('section[aria-labelledby="tv-ev-h"]');
  t(w + ' the listing\'s Coming up here lists its events, each opening its page', await cu.count() === 1 && await cu.locator('a.lv-ev-n[href="/event/?id=' + EV + '"]').count() === 1);
  t(w + ' a cancelled one is marked', /Cancelled/.test(await cu.innerText()));
  await cu.scrollIntoViewIfNeeded(); await p.screenshot({ path: out + '/ev2-listing-' + w + '.png' });
  await p.goto(H + '/hub/?cluster=' + CLUSTER, { waitUntil: 'load' }); await p.waitForTimeout(900);
  t(w + ' the town\'s guide lists it under Coming up', await p.locator('a.lv-ev-n[href="/event/?id=' + EV + '"]').count() >= 1);
  const ev = p.locator('a.lv-ev-n[href="/event/?id=' + EV + '"]').first(); if (await ev.count()) { await ev.scrollIntoViewIfNeeded(); await p.screenshot({ path: out + '/ev2-guide-' + w + '.png' }); }
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / '));
  await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); process.exit(bad ? 1 : 0); })();
