// Search fixes (handoffs/screens-proposal-2026-11-22.md: A1-A3, B1; Jason's approval 2026-11-22), rendered at 1280 and 390.
// Run on the harness at 127.0.0.1:8790 with search_index seeded (4 pool listings, an outdoor place, a story and the Public
// Pools category, all matching "pools"): node search-fixes.cjs
// The real search answer is used; "near" (decisions/0060's lacking note) and "guide" (0147's offer) are added to it, as
// a place with few results near and no guide gets, and a Peek's card answer is stubbed for the outdoor place.
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8790';
let pass = 0, fail = 0; const ok = (c, m) => { if (c) { pass++; console.log('  ✓ ' + m); } else { fail++; console.log('  ✗ ' + m); } };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    console.log('— ' + w + 'px');
    const p = await b.newPage({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 Chrome/130' }); const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.route(/^https:\/\/(?!127)/, r => r.abort());
    let peeked = '';
    await p.route(/\/api\/search\.php\?/, async r => {
      const u = new URL(r.request().url());
      if (u.searchParams.has('peek')) { peeked = u.searchParams.get('peek'); return r.fulfill({ json: { ok: true, listing: { id: peeked, kind: 'outdoors', name: 'Gila Lower Box Canyon', category: 'Recreation area', category_slug: '', town: 'NM', about: 'A canyon.', hours: [], url: '/place/landmark.php?id=77', directions: '' } } }); }
      if (!u.searchParams.has('q') || u.searchParams.has('suggest') || u.searchParams.has('presets')) return r.continue();
      const res = await r.fetch(); const j = await res.json();
      if (j.results) { j.results.near = { ours: 1, within: 5, nearest: 43, enough: 3, place: 'St. Johns, AZ', words: 'pools' }; j.guide = { state: 'none', label: 'St. Johns, AZ', text: 'We don’t have a guide to St. Johns, AZ yet.', url: '/guide/build?place=st-johns-az' }; }
      return r.fulfill({ response: res, json: j });
    });
    await p.goto(BASE + '/', { waitUntil: 'load' }); await p.waitForTimeout(600);
    await p.evaluate(() => TvSearch.open('pools')); await p.waitForTimeout(2500);
    const head = await p.$eval('.tvs-full', f => { const t = f.innerText; return { res: +(/Results\s*\((\d+)\)/i.exec(t) || [])[1], tabs: [...f.querySelectorAll('.tvs-tab')].map(x => x.innerText.replace(/\s+/g, ' ').trim()) }; });
    const rows = await p.$$eval('.tvs-full .tvs-row', x => x.map(r => [r.querySelector('b').textContent, (r.querySelector('.tvs-kind') || {}).textContent, !!r.querySelector('.tvs-peekb')]));
    const tabSum = head.tabs.filter(t => t !== 'All').reduce((a, t) => a + +(/(\d+)$/.exec(t) || [0, 0])[1], 0);
    ok(head.res === rows.length && tabSum === rows.length, 'Results (' + head.res + ') = the rows listed, 5 shown and the rest under Show more (' + rows.length + ') = the tabs added up (' + head.tabs.join(', ') + ')');
    ok(head.tabs.includes('Discovery 2') && head.tabs.includes('Directory 4'), 'the tabs use Search Directory\'s names, as the rows do (0058 §38)');
    ok(rows.every(r => r[2]), 'every row has Peek (' + rows.map(r => r[0] + ': ' + r[1]).join('; ') + ')');
    ok(rows.every(r => ['Directory', 'Discovery'].includes(r[1])), 'each row\'s tag matches a tab');
    // A3 and B1: the order on the page
    const order = await p.$eval('.tvs-full', f => { const at = s => { const e = f.querySelector(s); if (!e) return -1; let i = 0, n = f.querySelectorAll('*'); for (; i < n.length; i++) if (n[i] === e) return i; return -1; }; return { note: at('.tvs-nearfoot'), first: at('.tvs-row'), guide: at('.tvs-top-card') }; });
    const noteText = await p.$eval('.tvs-nearfoot', e => e.innerText).catch(() => '');
    ok(/lacking/.test(noteText) && order.note >= 0 && order.note < order.first, 'the "results are lacking" note is above the rows: "' + noteText.slice(0, 70) + '…"');
    ok(order.guide > order.first, 'the "Build the guide" card is under the results');
    // Peek on the outdoor place opens its card, asked as rec:<id>
    await p.click('.tvs-full .tvs-row:has-text("Gila") .tvs-peekb'); await p.waitForTimeout(600);
    ok(peeked === 'rec:g0000000077' && await p.isVisible('.tvs-peek'), 'Peek on the outdoor place opens its card (asked for ' + peeked + ')');
    await p.click('.tvs-peek [data-peek-close]'); await p.waitForTimeout(200); ok(!(await p.$('.tvs-peek')) && await p.isVisible('.tvs-full'), 'its × closes the card, the results stay');
    // a listing's Peek still asks by its number
    peeked = ''; await p.click('.tvs-full .tvs-row:has-text("Show Low") .tvs-peekb'); await p.waitForTimeout(600);
    ok(peeked === '102', 'a listing\'s Peek still asks by its number (' + peeked + ')');
    await p.click('.tvs-peek [data-peek-close]'); await p.waitForTimeout(200);
    // a row with only a page (a story) opens it in the page peek
    await p.click('.tvs-full [data-show="all"]').catch(() => {}); await p.click('.tvs-full .tvs-row:has-text("Pools of the White") .tvs-peekb'); await p.waitForTimeout(900);
    ok(await p.evaluate(() => [...document.querySelectorAll('iframe')].some(f => /faq\.php/.test(f.src))), 'a story\'s Peek opens its page in the page peek');
    await p.keyboard.press('Escape'); await p.waitForTimeout(300);
    if (w === 390) ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'at 390, nothing scrolls sideways');
    ok(errs.length === 0, 'no script errors' + (errs.length ? ': ' + errs.join(' | ') : ''));
    await p.screenshot({ path: '/tmp/claude-0/sf-' + w + '.png' });
    await p.close();
  }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
