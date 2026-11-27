// Events P2 rendered (decisions/0134's note #1594): an event page's Taking part, by name, each card to 0058 §11 (the name
// the link, Link top right, round icons with their names, Add to a trip), its own offer shown as written (line and/or code;
// a code past its last day not shown; none for a listing nobody runs), its GO Code; Send the evening to Maps ending at the
// venue; none on a cancelled event; the listing's "Part of <event>"; at 1280 and 390 on the design harness.
// Seeds: event EV (host 7) with 8 (line + code), 101 (line, an ended code), 102 (unclaimed, an offer that must not show),
// 103 removed; the cancelled event 6 with 8 taking part.
const { chromium } = require('playwright'); const fs = require('fs'); const cp = require('child_process');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.';
const H = process.env.HARNESS || 'http://127.0.0.1:8790', EV = '5';
const sh = (q, db) => cp.execFileSync('mysql', ['-utv', '-ptv', db || 'tvh', '-e', q]);
sh(`DELETE FROM event_participants; INSERT INTO event_participants (event_id, entity_id, status, offer_text, offer_code, offer_code_words, offer_code_ends, invited_at, changed_at) VALUES
 (5, 8, 'accepted', '10% off a pizza with your ticket', 'SHOW10', '10% off, until Dec 31', '2026-12-31', NOW(), NOW()),
 (5, 101, 'accepted', 'Free dessert before the show', 'OLDCODE', '5% off', '2026-11-01', NOW(), NOW()),
 (5, 102, 'accepted', 'Must not show', 'NOPE', '1% off', NULL, NOW(), NOW()),
 (5, 103, 'removed', 'Removed one', '', '', NULL, NOW(), NOW()),
 (6, 8, 'accepted', 'Cancelled night offer', '', '', NULL, NOW(), NOW())`);
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [1280, 390]) {
  const ua = w < 800 ? 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36' : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
  const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800, userAgent: ua }); await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  await p.goto(H + '/event/?id=' + EV, { waitUntil: 'load' }); await p.waitForTimeout(700);
  const sec = p.locator('#evp-parts'), cards = sec.locator('.evp-pc');
  t(w + ' Taking part shows', await sec.count() === 1);
  const names = await cards.locator('.evp-name').allTextContents();
  t(w + ' by name, never ranked; the removed one isn\'t there', names.join('|') === 'Crawler Cafe|Owner Diner|Show Low Pizza', names.join('|'));
  t(w + ' the lead says how they\'re listed', /in order of their names/.test(await sec.innerText()));
  const pz = cards.filter({ hasText: 'Show Low Pizza' });
  t(w + ' the name is the link to its listing', await pz.locator('a.evp-name[href="/listing/view.php?id=8"]').count() === 1);
  t(w + ' Link sits top right', await pz.locator('.evp-plink[data-link="business:8"]').count() === 1);
  const ics = pz.locator('.evp-ic'); const labels = await ics.evaluateAll(a => a.map(x => x.getAttribute('aria-label') + '/' + (x.getAttribute('title') ? 'T' : '')));
  t(w + ' round icons each name their action and its business', labels.length >= 2 && labels.every(l => /Show Low Pizza/.test(l) && /\/T$/.test(l)), labels.join(' , '));
  t(w + ' the icons are 30px rounds', (await ics.first().evaluate(x => { const r = x.getBoundingClientRect(); return Math.round(r.width) + 'x' + Math.round(r.height); })) === '30x30');
  t(w + ' Directions counts its tap', await pz.locator('.evp-ic[data-out="directions"][data-e="8"]').count() === 1);
  t(w + ' nothing else of its codes reaches the page (its other code OLD5, counts)', !/OLD5|uses/.test(await sec.innerHTML()));
  t(w + ' its offer as written, then only its code and last day', /Their offer:\s*10% off a pizza with your ticket · code SHOW10 until Dec 31/.test(await pz.innerText()) && !/10% off, until/.test(await pz.innerText()));
  t(w + ' its GO Code', /GO Code\s*\S*\/\w{6}/.test(await pz.locator('.evp-go-c').innerText()) && await pz.locator('.evp-go-c a').count() === 1);
  const od = cards.filter({ hasText: 'Owner Diner' });
  t(w + ' a code past its last day isn\'t shown; the line is', /Free dessert before the show/.test(await od.innerText()) && !/OLDCODE/.test(await od.innerText()));
  const cc = cards.filter({ hasText: 'Crawler Cafe' });
  t(w + ' a listing nobody runs shows no offer', await cc.locator('.evp-offer').count() === 0 && !/Must not show|NOPE/.test(await cc.innerText()));
  const rt = sec.locator('a.evp-rt[data-maps]');
  const stops = rt.count() ? JSON.parse(await rt.getAttribute('data-maps')) : [];
  t(w + ' Send the evening to Maps: every stop with Directions, the venue last', stops.length >= 3 && stops[stops.length - 1][2] === 'Test Cafe', JSON.stringify(stops.map(s => s[2])));
  await sec.screenshot({ path: out + '/p2-taking-part-' + w + '.png' }); await p.screenshot({ path: out + '/p2-event-page-' + w + '.png', fullPage: true });
  await rt.click(); await p.waitForTimeout(500);
  const dlg = p.locator('[role=dialog]').filter({ hasText: /Google|Apple/ });
  t(w + ' its chooser opens', await dlg.count() >= 1 && await dlg.first().isVisible());
  await p.screenshot({ path: out + '/p2-maps-' + w + '.png' });
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  t(w + ' and closes with Esc', !(await dlg.first().isVisible().catch(() => false)));
  await p.goto(H + '/event/?id=' + EV, { waitUntil: 'load' }); await p.waitForTimeout(500);
  await pz.locator('[data-trip-add]').click(); await p.waitForTimeout(1200);
  const tp = p.locator('.tvtp[role=dialog]');
  t(w + ' Add to a trip opens the trip picker, for that business', await tp.count() === 1 && /Add to a trip/.test(await tp.innerText()) && /Show Low Pizza/.test(await tp.innerText()), await tp.count() ? (await tp.innerText()).slice(0, 80) : 'none');
  await p.screenshot({ path: out + '/p2-trip-' + w + '.png' }); await p.keyboard.press('Escape');
  t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  if (w < 800) { const e = await cards.first().evaluate(x => { const r = x.getBoundingClientRect(); return [Math.round(r.left), Math.round(innerWidth - r.right)]; }); t(w + ' cards edge to edge on phones', e[0] <= 16 && e[1] <= 16, e.join(',')); }
  // the code read live when the page is drawn: switched off, then past its last day, the line alone stays
  sh("SET @tv_tenant=8; UPDATE biz_codes SET active = 0 WHERE code = 'SHOW10'", 'tvl');
  await p.goto(H + '/event/?id=' + EV, { waitUntil: 'load' }); await p.waitForTimeout(300);
  let pz2 = p.locator('#evp-parts .evp-pc').filter({ hasText: 'Show Low Pizza' });
  t(w + ' a code switched off isn\'t shown; its line is', !/SHOW10/.test(await pz2.innerText()) && /10% off a pizza with your ticket/.test(await pz2.innerText()));
  sh("SET @tv_tenant=8; UPDATE biz_codes SET active = 1, ends = '2026-11-01' WHERE code = 'SHOW10'", 'tvl');
  await p.goto(H + '/event/?id=' + EV, { waitUntil: 'load' }); await p.waitForTimeout(300);
  pz2 = p.locator('#evp-parts .evp-pc').filter({ hasText: 'Show Low Pizza' });
  t(w + ' a code past its last day isn\'t shown either', !/SHOW10/.test(await pz2.innerText()));
  sh("SET @tv_tenant=8; UPDATE biz_codes SET ends = '2026-12-31' WHERE code = 'SHOW10'", 'tvl');
  // a cancelled event: no Taking part
  await p.goto(H + '/event/?id=6', { waitUntil: 'load' }); await p.waitForTimeout(300);
  t(w + ' a cancelled event shows no Taking part', await p.locator('#evp-parts').count() === 0 && !/Cancelled night offer/.test(await p.content()));
  // the listing: Part of <event>
  await p.goto(H + '/listing/view.php?id=8', { waitUntil: 'load' }); await p.waitForTimeout(500);
  const po = p.locator('a.lv-ev-n', { hasText: 'Part of Community night' });
  t(w + ' the listing says it\'s Part of the event, with its venue', await po.count() === 1 && await po.getAttribute('href') === '/event/?id=5' && /Test Cafe/.test(await po.locator('xpath=ancestor::article').innerText()));
  t(w + ' not Part of the cancelled one', await p.locator('a.lv-ev-n', { hasText: 'Holiday matinee' }).count() === 0);
  await p.locator('#tv-ev-h').scrollIntoViewIfNeeded(); await p.screenshot({ path: out + '/p2-listing-part-of-' + w + '.png' });
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / '));
  await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); process.exit(bad ? 1 : 0); })();
