// Search release 2 rendered (decisions/0181's note, 2026-11-27, #1618; 0060's note #1619), at 1280 and 390:
//  - every listing row says where it stands now in its own zone (Hours::now): "Open · Closes …" in green, "Closed …" and
//    "Hours not known" quiet; "open now" keeps only those open where they are;
//  - an everyday local need with no place ("urgent care", "pharmacy") opens "Where are you?" (Auto-detect first), once
//    for those words; closed without a place, the results from everywhere stay; "near me" unchanged (where first, nothing
//    from everywhere); a word that isn't a need ("history") asks nothing;
//  - the listing page's line over its hours (data-hours-now).
// Run on a harness with search_index seeded (Owner Diner, OSM Grill, Staff Pizza open all day; Shut Owner Bar closed;
// Crawler Cafe and No Hours Taco with none; all "pizza" in Show Low, AZ): node hours-and-place.cjs [base]
const { chromium } = require('playwright'); const fs = require('fs');
const BASE = process.argv[2] || 'http://127.0.0.1:8790', TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : '';
const SHOTS = process.env.SHOTS || '';
let pass = 0, fail = 0; const ok = (c, m, x) => { if (c) pass++; else fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m + (x ? '  [' + x + ']' : '')); };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    console.log('— ' + w + 'px');
    const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36' });
    await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
    await c.route(/^https:\/\/(?!127)(?!cdn\.tailwindcss)/, r => r.abort());
    const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    // rows: a place set (Show Low), so the rows are ours with their hours
    await p.goto(BASE + '/?cluster=cluster-auto-show-low', { waitUntil: 'load' }); await p.waitForTimeout(800);
    await p.evaluate(() => TvSearch.open('pizza')); await p.waitForTimeout(2500);
    const rows = await p.$$eval('.tvs-full .tvs-row', x => x.map(r => [r.querySelector('b').textContent, (r.querySelector('em') || {}).textContent || '', (r.querySelector('em') || {}).className || '']));
    const by = n => rows.find(r => r[0] === n) || ['', '', ''];
    ok(/^Open · Closes /.test(by('Owner Diner')[1]) && by('Owner Diner')[2] === 'tvs-open', 'an open listing says when it closes, in green', by('Owner Diner')[1]);
    ok(by('Shut Owner Bar')[1] === 'Closed today' && by('Shut Owner Bar')[2] === 'tvs-unk', 'a closed one says so, quietly', by('Shut Owner Bar')[1]);
    ok(by('Crawler Cafe')[1] === 'Hours not known' && by('No Hours Taco')[1] === 'Hours not known', 'none known: "Hours not known", never open');
    if (SHOTS) await p.screenshot({ path: SHOTS + '/r2-rows-' + w + '.png' });
    await p.evaluate(() => TvSearch.open('pizza open now')); await p.waitForTimeout(2500);
    const open = await p.$$eval('.tvs-full .tvs-row b', x => x.map(e => e.textContent));
    ok(open.includes('Owner Diner') && !open.includes('Shut Owner Bar'), 'open now: those open where they are, the closed one left out', open.join(', '));
    // an everyday need, no place: "Where are you?"
    const c2 = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36' });
    await c2.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
    await c2.route(/^https:\/\/(?!127)(?!cdn\.tailwindcss)/, r => r.abort());
    const q = await c2.newPage(); q.on('pageerror', e => errs.push(e.message));
    await q.goto(BASE + '/', { waitUntil: 'load' }); await q.waitForTimeout(800);
    const modal = async () => q.evaluate(() => { const m = document.getElementById('location-modal'); const h = document.getElementById('location-modal-title'); return { on: !!m && !m.classList.contains('hidden'), title: h ? h.textContent : '' }; });
    await q.evaluate(() => TvSearch.open('pizza')); await q.waitForTimeout(2000);
    ok(!(await modal()).on, 'a search that isn\'t an everyday need ("pizza" is a kind, not "near me") asks nothing... ', JSON.stringify(await modal()));
    await q.evaluate(() => TvSearch.open('urgent care')); await q.waitForTimeout(2000);
    let m = await modal();
    ok(m.on && m.title === 'Where are you?', '"urgent care" with no place opens "Where are you?"', JSON.stringify(m));
    if (SHOTS) await q.screenshot({ path: SHOTS + '/r2-where-' + w + '.png' });
    await q.keyboard.press('Escape'); await q.waitForTimeout(500);
    m = await modal();
    const after = await q.evaluate(() => ({ words: (document.querySelector('.tvs-panel .tvs-in2, .tvs-in') || {}).value || '', placed: !!(typeof currentLocation !== 'undefined' && currentLocation && (currentLocation.scope || currentLocation.zip || currentLocation.city)), open: !!document.querySelector('.tvs-panel.full') }));
    ok(!m.on && after.words === 'urgent care' && !after.placed && after.open, 'closed without a place: the picker only, the words and search stay, everywhere (no place set)', JSON.stringify(after));
    await q.evaluate(() => TvSearch.open('urgent care')); await q.waitForTimeout(1500);
    ok(!(await modal()).on, 'asked once for the same words, not again');
    await q.evaluate(() => TvSearch.open('pharmacy')); await q.waitForTimeout(1500);
    ok((await modal()).on, 'another everyday need ("pharmacy") asks');
    await q.keyboard.press('Escape'); await q.waitForTimeout(400);
    await q.evaluate(() => TvSearch.open('history of show low')); await q.waitForTimeout(1500);
    ok(!(await modal()).on, 'words that aren\'t a need ("history of show low") ask nothing');
    await q.evaluate(() => TvSearch.open('pizza near me')); await q.waitForTimeout(1500);
    m = await modal();
    const wait = await q.$eval('.tvs-full', e => /Where are you\?/.test(e.innerText)).catch(() => false);
    ok(m.on && wait, '"near me" unchanged: where first, and nothing from everywhere');
    await q.keyboard.press('Escape');
    // the listing page: where it stands now, over its hours
    await q.goto(BASE + '/listing/view.php?id=101', { waitUntil: 'load' }); await q.waitForTimeout(600);
    const line = await q.$eval('[data-hours-now]', e => [e.getAttribute('data-hours-now'), e.textContent.trim()]).catch(() => null);
    ok(line && line[0] === 'open' && /^(Now:\s*)?Open · Closes /.test(line[1]) && !/×/.test(line[1]), 'the listing page says where it stands now, over its hours, with no ×', line ? line.join(': ') : 'none');
    if (SHOTS) { const h = await q.$('[data-hours-now]'); if (h) { await h.scrollIntoViewIfNeeded(); await q.screenshot({ path: SHOTS + '/r2-listing-' + w + '.png' }); } }
    ok((await q.evaluate(() => document.documentElement.scrollWidth)) <= w, 'no sideways scroll');
    ok(!errs.length, 'no script errors', errs.slice(0, 2).join(' / '));
    await c.close(); await c2.close();
  }
  console.log(pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0);
})();
