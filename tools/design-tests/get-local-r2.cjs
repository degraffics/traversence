// Get Local R2's rendered checks (Jason, 2026-11-19): the line over the list (Filters ▾, Arrange ▾, Sort), the cards' tools
// (Call, Directions, ⋯ with words, Link; the score chip; a PO Box has no Directions), the comments box, the header's
// search (the split look; folded to ⌕ here; Ctrl K), Arrange, the Table view and the list beside the map (the A/B test).
// Needs: a copy of the site at BASE with listings round Show Low (see the hand-off) and Leaflet 1.9.4 unpacked into NPM_DIR.
// Run: BASE=http://127.0.0.1:8790 NPM_DIR=<dir> node get-local-r2.cjs <shots dir>
const { chromium } = require('playwright'); const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8790', N = process.env.NPM_DIR || '.', out = process.argv[2] || '.';
const URL = BASE + '/directory/?lat=34.25&lon=-110.03&radius=25&near=Show%20Low';
let pass = 0, fail = 0; const ok = (c, m) => { if (c) pass++; else { fail++; console.log('FAIL ' + m); } };
async function page(b, w, h, ab, url) {
  const ctx = await b.newContext({ viewport: { width: w, height: h } }); const p = await ctx.newPage(); const errs = [], counts = [];
  p.on('pageerror', e => errs.push(e.message));
  p.on('request', r => { if (r.url().endsWith('/api/count.php')) { const m = (r.postData() || '').match(/name="k"\r\n\r\n([^\r]*)/); if (m) counts.push(m[1]); } });
  await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => { const u = r.request().url();
    const m = { 'leaflet.min.css': 'leaflet-1.9.4/dist/leaflet.css', 'leaflet.min.js': 'leaflet-1.9.4/dist/leaflet.js' }, k = Object.keys(m).find(x => u.endsWith('/' + x));
    if (k) return r.fulfill({ contentType: k.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(N + '/' + m[k]) });
    return r.abort(); });
  await p.addInitScript(a => { try { localStorage.clear(); if (a) localStorage.setItem('tv_dir_ab', a); } catch (e) {} }, ab);
  await p.goto(url || URL, { waitUntil: 'load' }); if (!url) { await p.waitForSelector('.dr-it', { timeout: 8000 }); await p.waitForTimeout(900); }
  return { p, ctx, errs, counts };
}
(async () => {
  const b = await chromium.launch();
  // ---- 1280, the list beside the map ----
  let { p, ctx, errs, counts } = await page(b, 1280, 860, 'beside');
  const s = await p.evaluate(() => ({ side: document.getElementById('dr-grid').classList.contains('side'), map: !document.querySelector('.dr-mapbox').hidden,
    pins: document.querySelectorAll('.dr-pin').length, pin1: (document.querySelector('.dr-pin b') || {}).textContent, ring: !!document.querySelector('.leaflet-overlay-pane path'),
    sx: document.documentElement.scrollWidth > innerWidth, fold: document.getElementById('tv-search').classList.contains('tvs-fold'),
    ctl: [...document.querySelectorAll('.dr-ctl > *')].map(e => e.className), num: (document.querySelector('.dr-num') || {}).textContent }));
  ok(s.side && s.map, 'beside: the list and the map side by side'); ok(s.pins === 10 && s.pin1 === '1', 'beside: numbered pins match the cards'); ok(s.ring, 'beside: the distance ring');
  ok(!s.sx, '1280: no sideways scroll'); ok(s.fold, 'header search folded to ⌕ on Get Local'); ok(s.ctl.join() === 'dr-fdd,dr-fdd dr-add,dr-srt', 'Filters, Arrange, Sort on the sentence line');
  ok(s.num === '1.', 'cards numbered');
  // the cards' tools
  const c = await p.evaluate(() => { const card = n => [...document.querySelectorAll('.dr-it')].find(x => x.textContent.includes(n));
    const po = card('M2 Serv Core'), mill = card('Old Mill Bakery');
    return { poDir: !!po.querySelector('[data-out=directions]'), poNote: po.textContent.includes('Mailing address only'), millDir: !!mill.querySelector('[data-out=directions]'),
      more: !!mill.querySelector('.dr-mb'), chip: !!document.querySelector('.dr-hd .tv-conf-chip, .dr-hd .tv-conf') }; });
  ok(!c.poDir && c.poNote, 'a PO Box: no Directions, "Mailing address only"'); ok(c.millDir, 'a street address keeps Directions'); ok(c.more, 'the ⋯ button on each card');
  await p.click('.dr-it >> nth=0 >> .dr-mb'); ok(await p.isVisible('.dr-ml:not([hidden])'), '⋯ opens'); await p.keyboard.press('Escape'); ok(!(await p.isVisible('.dr-ml:not([hidden])')), '⋯ closes with Esc');
  // Filters
  await p.click('#dr-fdd summary'); await p.click('.dr-tg[data-f=phone]'); await p.waitForTimeout(700);
  ok(await p.evaluate(() => document.getElementById('dr-fn').textContent === '1' && !!document.querySelector('[data-tok="unf-phone"]')), 'a filter on: counted on Filters, a chip in the sentence');
  await p.click('[data-tok="unf-phone"]'); await p.waitForTimeout(700); ok(await p.evaluate(() => !document.querySelector('[data-tok="unf-phone"]')), 'the chip\'s × turns it off');
  // Search this area
  await p.mouse.move(1000, 500); await p.mouse.down(); await p.mouse.move(900, 450, { steps: 5 }); await p.mouse.up(); await p.waitForTimeout(900);
  ok(await p.isVisible('#dr-sarea'), 'moving the map offers Search this area'); await p.click('#dr-sarea'); await p.waitForTimeout(900);
  ok(await p.evaluate(() => /this area/.test(document.getElementById('dr-sent').textContent)), 'Search this area searches there');
  await p.screenshot({ path: out + '/r2-1280-beside.png' });
  // Arrange
  await p.click('#dr-arr summary'); await p.waitForTimeout(200);
  ok((await p.evaluate(() => [...document.querySelectorAll('#dr-adl h4')].map(h => h.textContent).join('|'))) === 'Results per page|Show|On each card|On a computer', 'Arrange\'s four parts');
  for (let i = 0; i < 3; i++) await p.click('#dr-dl li[data-det="phone"] [data-mv="-1"]');
  ok(await p.evaluate(() => document.getElementById('dr-arr').open), 'Arrange stays open while reordering');
  await p.selectOption('[data-ar-add]', 'web'); await p.waitForTimeout(200);
  const mill = await p.evaluate(() => [...document.querySelectorAll('.dr-it')].find(x => x.textContent.includes('Old Mill')).querySelector('.dr-m').textContent);
  ok(/^Restaurants · 928-555-0203 · /.test(mill), 'the card\'s details follow the order chosen: ' + mill);
  await p.screenshot({ path: out + '/r2-1280-arrange.png' });
  await p.uncheck('[data-ar-closed]'); await p.waitForTimeout(700);
  ok(await p.evaluate(() => ![...document.querySelectorAll('.dr-it')].some(x => x.textContent.includes('Closed Cafe'))), 'Show: closed for good hidden for this person');
  await p.click('[data-per="25"]'); await p.waitForTimeout(700); ok(await p.evaluate(() => localStorage.getItem('tvp:directory') === '25'), 'per page kept');
  await p.click('[data-ar-reset]'); await p.waitForTimeout(700); ok(await p.evaluate(() => JSON.parse(localStorage.getItem('tv_dir_arrange')).closed === true), 'Put it all back');
  await p.keyboard.press('Escape'); ok(await p.evaluate(() => !document.getElementById('dr-arr').open), 'Arrange closes with Esc');
  // Table
  await p.click('.dr-view [data-view=table]'); await p.waitForTimeout(400);
  ok(await p.evaluate(() => document.querySelectorAll('.dr-tb tbody tr').length >= 10 && document.querySelector('.dr-mapbox').hidden), 'Table: the rows, no map beside');
  await p.click('[data-col=kind]'); const n0 = await p.evaluate(() => document.querySelectorAll('.dr-tb tbody tr').length);
  ok(await p.evaluate(() => [...document.querySelectorAll('.dr-cm label')].every(l => /^Show .+\(\d+\)$/.test(l.textContent.trim()))), 'column menu: "Show X (n)" ticks');
  await p.screenshot({ path: out + '/r2-1280-table.png' });
  await p.locator('.dr-cm label').first().locator('input').uncheck(); await p.waitForTimeout(200);
  ok(await p.evaluate(n => document.querySelectorAll('.dr-tb tbody tr').length < n && !!document.querySelector('.dr-cm') && !!document.querySelector('th.f'), n0), 'a tick filters the rows, the menu stays');
  await p.click('.dr-cm [data-cm=x]'); ok(await p.evaluate(() => !document.querySelector('.dr-cm')), 'column menu closes with ×');
  await p.click('[data-col=name]'); await p.click('.dr-cm [data-cm=down]'); await p.waitForTimeout(200);
  const names = await p.evaluate(() => [...document.querySelectorAll('.dr-tb tbody td:nth-child(2) a')].map(a => a.textContent));
  ok(names.join() === [...names].sort().reverse().join(), 'Sort Z to A');
  await p.click('[data-col=town]'); await p.mouse.click(5, 850); ok(await p.evaluate(() => !document.querySelector('.dr-cm')), 'column menu closes with a click outside');
  ok(counts.includes('gl-ab:beside') && counts.includes('gl-view:table') && counts.includes('gl-ab:beside:area'), 'the test\'s counts: ' + counts.join(','));
  // the header's Ctrl K
  await p.keyboard.press('Control+k'); await p.waitForTimeout(300); ok(await p.evaluate(() => !!document.querySelector('.tvs-panel.full')), 'Ctrl K opens the site search');
  ok(!errs.length, 'no script errors: ' + errs.join('; ')); await ctx.close();
  // ---- 1280, the switch side ----
  ({ p, ctx, errs } = await page(b, 1280, 860, 'switch'));
  ok(await p.evaluate(() => !document.getElementById('dr-grid').classList.contains('side') && document.querySelector('.dr-mapbox').hidden), 'switch: the list alone, List | Table | Map');
  await p.screenshot({ path: out + '/r2-1280-switch.png' }); ok(!errs.length, 'no script errors (switch)'); await ctx.close();
  // ---- 390 ----
  ({ p, ctx, errs } = await page(b, 390, 844, 'beside'));
  const ph = await p.evaluate(() => ({ side: document.getElementById('dr-grid').classList.contains('side'), table: getComputedStyle(document.querySelector('.dr-view [data-view=table]')).display,
    sx: document.documentElement.scrollWidth > innerWidth, edge: (() => { const r = document.querySelector('.dr-it').getBoundingClientRect(); return [Math.round(r.left), Math.round(innerWidth - r.right)]; })(),
    clear: (() => { const l = document.querySelector('.dr-it [data-link]').getBoundingClientRect(); return !l.width || [...document.querySelectorAll('.dr-it')].every(c => [...c.querySelector('.dr-hd').children].every(x => { const r = x.getBoundingClientRect(), k = c.querySelector('[data-link]').getBoundingClientRect(); return r.right <= k.left + 1 || r.top >= k.bottom || r.bottom <= k.top; })); })() }));
  ok(!ph.side && ph.table === 'none', '390: List | Map only'); ok(!ph.sx, '390: no sideways scroll'); ok(ph.edge[0] === 3 && ph.edge[1] === 3, '390: cards 3px from each side ' + ph.edge);
  ok(ph.clear, '390: the name line clear of Link');
  await p.screenshot({ path: out + '/r2-390.png' });
  await p.click('#dr-arr summary'); await p.waitForTimeout(200);
  ok(await p.evaluate(() => { const r = document.getElementById('dr-adl').getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; }), '390: Arrange on screen');
  await p.screenshot({ path: out + '/r2-390-arrange.png' }); ok(!errs.length, 'no script errors (390)'); await ctx.close();
  // ---- the comments box (a listing) ----
  ({ p, ctx, errs } = await page(b, 390, 844, '', BASE + '/listing/view.php?id=4')); await p.waitForTimeout(800);
  ok(await p.evaluate(() => document.querySelectorAll('[data-eg-start]').length === 4 && /No comments yet/.test(document.body.textContent)), 'comments: the starters and "No comments yet"');
  await p.screenshot({ path: out + '/r2-390-comments.png', fullPage: true }); await ctx.close();
  // ---- the header's search elsewhere: the split look ----
  ({ p, ctx } = await page(b, 1280, 800, '', BASE + '/faq.php')); await p.waitForTimeout(500);
  ok(await p.evaluate(() => { const s = document.getElementById('tv-search'); return !s.classList.contains('tvs-fold') && !!s.querySelector('[data-tvs-pl]') && getComputedStyle(s).borderRadius === '999px'; }), 'header search: the pill, the place chip');
  await ctx.close(); await b.close();
  console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
