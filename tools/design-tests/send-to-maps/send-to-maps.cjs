// tools/design-tests/send-to-maps/send-to-maps.cjs: Send to Maps (decisions/0174 §4, release 1), rendered.
// Run against the harness: copy page.php to <root>/website/test/maps.php, then BASE=http://127.0.0.1:8790 node send-to-maps.cjs
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8790';
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('ok   ' + m); } else { fail++; console.log('FAIL ' + m); } };
const pts = n => Array.from({length: n}, (_, i) => [+(34.2 + i * 0.011).toFixed(5), +(-110.03 - i * 0.013).toFixed(5)].join(','));
const qs = u => new URL(u).searchParams;

(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const ctx = await b.newContext({viewport: {width: w, height: 800}});
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
    await p.goto(BASE + '/test/maps.php?n=12', {waitUntil: 'load'});
    const P = pts(12), tag = '@' + w + ': ';
    const a = p.locator('.sj-route');
    // without script it is still Google's route, first ten stops
    const href = await a.getAttribute('href'), q0 = qs(href);
    ok(href.startsWith('https://www.google.com/maps/dir/') && q0.get('origin') === P[0] && q0.get('destination') === P[9] && q0.get('waypoints').split('|').length === 8, tag + 'the plain link is Google with the first ten stops');
    await a.click();
    const box = p.locator('.tvsm[role=dialog]');
    ok(await box.isVisible(), tag + 'the route icon opens Send to Maps');
    ok((await box.innerText()).includes('12 stops, in order'), tag + 'it names every stop');
    let links = await box.locator('.tvsm-go a').evaluateAll(as => as.map(x => [x.href, x.innerText]));
    ok(links.length === 2 && /Leg 1 of 2 in Google Maps/.test(links[0][1]) && /Leg 2 of 2/.test(links[1][1]), tag + 'twelve stops from My location go in two legs');
    let l1 = qs(links[0][0]), l2 = qs(links[1][0]);
    ok(!l1.has('origin') && l1.get('destination') === P[8] && l1.get('waypoints').split('|').join() === P.slice(0, 8).join(), tag + 'leg 1 starts at My location: stops 1 to 9');
    ok(l2.get('origin') === P[8] && l2.get('destination') === P[11] && l2.get('waypoints').split('|').join() === P.slice(9, 11).join(), tag + 'leg 2 starts where leg 1 ended: stops 10 to 12');
    ok(links.every(l => l[0].includes('travelmode=driving')), tag + 'driving');
    // Apple
    await box.locator('button[data-v=apple]').click();
    links = await box.locator('.tvsm-go a').evaluateAll(as => as.map(x => [x.href, x.innerText]));
    l1 = qs(links[0][0]); l2 = qs(links[1][0]);
    ok(links[0][0].startsWith('https://maps.apple.com/directions?') && !l1.has('source') && l1.get('destination') === P[8] && l1.getAll('waypoint').join() === P.slice(0, 8).join() && l1.get('mode') === 'driving', tag + 'Apple: no source is My location, a waypoint per stop, in order');
    ok(l2.get('source') === P[8] && l2.get('destination') === P[11] && l2.getAll('waypoint').join() === P.slice(9, 11).join(), tag + 'Apple: leg 2 from the end of leg 1');
    ok((await box.innerText()).includes('iOS 18.4'), tag + "Apple's version is said");
    // first stop
    await box.locator('button[data-v=first]').click();
    links = await box.locator('.tvsm-go a').evaluateAll(as => as.map(x => x.href));
    l1 = qs(links[0]);
    ok(links.length === 2 && l1.get('source') === P[0] && l1.get('destination') === P[9] && l1.getAll('waypoint').length === 8, tag + 'from the first stop: ten points in leg 1');
    // every stop, once, in order, across the legs
    const seen = []; links.forEach((u, i) => { const q = qs(u); if (i === 0) seen.push(q.get('source')); seen.push(...q.getAll('waypoint'), q.get('destination')); });
    ok(seen.join() === P.join(), tag + 'nothing is cut: every stop once, in order');
    ok(await p.evaluate(() => document.activeElement && document.activeElement.closest('.tvsm') !== null), tag + 'focus stays in the chooser');
    // the box fits the window
    const bb = await box.boundingBox();
    ok(bb.x >= 0 && bb.x + bb.width <= w + 0.5 && bb.y >= 0 && bb.y + bb.height <= 800.5, tag + 'the chooser is wholly in the window');
    ok(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), tag + 'no sideways scroll');
    // closing: Esc, ×, outside
    await p.keyboard.press('Escape'); ok(!(await box.count()), tag + 'Esc closes it');
    await a.click(); await p.locator('.tvsm-x').click(); ok(!(await p.locator('.tvsm').count()), tag + '× closes it');
    await a.click();
    const spot = await p.evaluate(() => {   // a plain spot outside the chooser: no link or button under it
      const r = document.querySelector('.tvsm').getBoundingClientRect();
      for (let y = 4; y < innerHeight; y += 6) for (let x = 4; x < innerWidth; x += 12) {
        if (y >= r.top - 2 && y <= r.bottom + 2 && x >= r.left - 2 && x <= r.right + 2) continue;
        const el = document.elementFromPoint(x, y); if (el && !el.closest('a,button,input,select,textarea,label,summary,[role=button],[onclick]')) return [x, y];
      } return null; });
    await p.mouse.click(spot[0], spot[1]); ok(!(await p.locator('.tvsm').count()), tag + 'a click outside closes it');
    await a.click(); await a.click(); ok(!(await p.locator('.tvsm').count()), tag + 'the icon again closes it');
    // remembered on the device
    await p.reload({waitUntil: 'load'});
    await p.locator('.sj-route').click();
    ok(await p.locator('.tvsm button[data-v=apple][aria-pressed=true]').count() === 1 && await p.locator('.tvsm button[data-v=first][aria-pressed=true]').count() === 1, tag + 'Apple and the first stop are remembered');
    await p.keyboard.press('Escape');
    // a short route: one link
    await p.evaluate(() => { try { localStorage.clear(); } catch (e) {} });
    await p.goto(BASE + '/test/maps.php?n=3', {waitUntil: 'load'});
    await p.locator('.sj-route').click();
    links = await p.locator('.tvsm-go a').evaluateAll(as => as.map(x => [x.href, x.innerText]));
    ok(links.length === 1 && /^Open in Google Maps/.test(links[0][1]) && !(await p.locator('.tvsm').innerText()).includes('legs'), tag + 'three stops: one link, no legs');
    await p.keyboard.press('Escape');
    // a landmark's Make a day of it
    await p.locator('#day a').click();
    ok((await p.locator('.tvsm').innerText()).includes('Lyman Lake: a day out') && (await p.locator('.tvsm').innerText()).includes('3 stops'), tag + "Make a day of it sends its route the same way");
    await p.keyboard.press('Escape');
    // one journey stop, no route icon
    await p.goto(BASE + '/test/maps.php?n=1', {waitUntil: 'load'});
    ok(!(await p.locator('.sj-route').count()), tag + 'one stop: no route icon');
    ok(!errs.length, tag + 'no script errors' + (errs.length ? ': ' + errs.join('; ') : ''));
    await ctx.close();
  }
  await b.close();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
