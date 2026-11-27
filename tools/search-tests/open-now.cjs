// C.8 (P10) rendered on the design harness at 390 and 1280: Quick Search's open now and Get Local's Open now.
const { chromium } = require('playwright'); const fs = require('fs');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.';
const loc = { state: 'AZ', city: 'Show Low', zip: '85901', lat: 34.254, lon: -110.03, radius: 10, scope: null, source: 'detect' };
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [390, 1280]) {
  const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800 });
  await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  await c.addInitScript(l => { try { sessionStorage.setItem('traversence_location', JSON.stringify(l)); } catch (e) {} }, loc);
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  // Quick Search
  await p.goto('http://127.0.0.1:8790/directory/', { waitUntil: 'load' }); await p.waitForTimeout(1200);
  await p.evaluate(() => TvSearch.open('pizza open now')); await p.waitForTimeout(3500);
  const rows = await p.$$eval('.tvs-full a.tvs-row, .tvs-panel a.tvs-row', rs => rs.map(r => r.innerText.replace(/\s+/g, ' ')));
  const open = rows.filter(r => / Open( |$)/.test(r)).length, unk = rows.filter(r => /Hours not known/.test(r)).length;
  const cnt = ((await p.locator('[data-hours-known]').first().innerText().catch(() => '')) || '').trim();
  t(w + ' search: 3 marked Open (owner, staff, OpenStreetMap)', open === 3, rows.slice(0, 7).join(' | '));
  t(w + ' search: the rest say Hours not known', unk >= 2 && rows.some(r => /Crawler Cafe.*Hours not known/.test(r)));
  t(w + ' search: no "Hours not listed" left', !rows.some(r => /Hours not listed/.test(r)));
  t(w + ' search: the closed bar left out', !rows.some(r => /Shut Owner Bar/.test(r)));
  t(w + ' search: says how many have known hours', /^3 of \d+ have known hours; the rest say Hours not known\.$/.test(cnt), cnt);
  t(w + ' search: open ones first', rows.findIndex(r => /Hours not known/.test(r)) > rows.map(r => / Open( |$)/.test(r)).lastIndexOf(true));
  await p.screenshot({ path: out + '/c8-search-' + w + '.png' });
  // Get Local, Open now on
  await p.goto('http://127.0.0.1:8790/directory/?lat=34.25&lon=-110.03&radius=10&near=Show%20Low&open=now', { waitUntil: 'load' }); await p.waitForTimeout(3500);
  const cards = await p.$$eval('#dr-list article.dr-it', as => as.map(a => a.innerText.replace(/\s+/g, ' ')));
  const sum = ((await p.locator('#dr-sum').innerText().catch(() => '')) || '').trim();
  t(w + ' Get Local: open first, then Hours not known', cards.length >= 5 && /Owner Diner|Staff Pizza|OSM Grill/.test(cards[0]) && /Hours not known/.test(cards[cards.length - 1]), cards.map(x => x.slice(0, 40)).join(' | '));
  t(w + ' Get Local: no crawler place marked open', cards.some(x => /Crawler Cafe.*Hours not known/.test(x)));
  t(w + ' Get Local: says how many have known hours', /3 of 6 have known hours; the rest say Hours not known\./.test(sum), sum);
  t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / '));
  await p.screenshot({ path: out + '/c8-getlocal-' + w + '.png', fullPage: false }); await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); })();
