// The place-page release's rendered checks (Jason, 2026-11-19, approved): the photo viewer names what's in the photo (its
// page, See on the map, Copy link) and shows its counts; views at 0; ⋯ on the engage bar (See on the map, Directions only
// for a street that isn't private, Plan a trip here with the place's ready-made journeys, Add to a trip greyed, Save
// contact, Suggest an edit, Report); every pop-up closes with ×, Esc and a click outside; a mailbox has no Directions on its
// own page. Needs: a copy of the site at BASE with landmarks 1 and 2 (photos, near Holbrook), listings 7003 (a street) and
// 7005 (a PO Box), micro_cluster 1 (Show Low) with two live suggested journeys, and Leaflet 1.9.4 in NPM_DIR.
// Run: BASE=http://127.0.0.1:8790 NPM_DIR=<dir> node place-page.cjs <shots dir>
const { chromium } = require('playwright'); const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8790', N = process.env.NPM_DIR || '.', out = process.argv[2] || '.';
let pass = 0, fail = 0; const ok = (c, m) => { if (c) pass++; else { fail++; console.log('FAIL ' + m); } };
async function page(b, w, h, url) {
  const ctx = await b.newContext({ viewport: { width: w, height: h } }); const p = await ctx.newPage(); const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => { const u = r.request().url();
    const m = { 'leaflet.min.css': 'leaflet-1.9.4/dist/leaflet.css', 'leaflet.min.js': 'leaflet-1.9.4/dist/leaflet.js' }, k = Object.keys(m).find(x => u.endsWith('/' + x));
    if (k) return r.fulfill({ contentType: k.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(N + '/' + m[k]) });
    return r.abort(); });
  await p.goto(BASE + url, { waitUntil: 'load' }); await p.waitForTimeout(700);
  return { p, ctx, errs };
}
(async () => {
  const b = await chromium.launch();
  for (const [w, h] of [[1280, 860], [390, 844]]) {
    // ---- Let's Explore: a photo of another place ----
    let { p, ctx, errs } = await page(b, w, h, '/discovery/?view=parks');
    ok(await p.evaluate(() => !!document.querySelector('[data-lb-href^="/place/landmark.php?id="][data-lb-kind="lm"][data-lb-lat]')), w + ': the hero\'s landmark photos name their place');
    await p.click('[data-lb-href]'); await p.waitForTimeout(800);
    const v = await p.evaluate(() => { const s = document.querySelector('.xlb-sub'); return { name: s && s.querySelector('a.xlb-to') && s.querySelector('a.xlb-to').getAttribute('href'), btns: s ? [...s.querySelectorAll('button')].map(x => x.textContent) : [],
      views: (document.querySelector('.xlb-acts .eg-n span') || {}).textContent, likes: (document.querySelector('.xlb-acts [data-eg-like] span') || {}).textContent,
      sx: document.documentElement.scrollWidth > innerWidth }; });
    ok(/^\/place\/landmark\.php\?id=\d+$/.test(v.name || ''), w + ': the viewer links to the place\'s page');
    ok(v.btns.join() === 'See on the map,Copy link', w + ': See on the map and Copy link: ' + v.btns);
    ok(v.views === '0' && v.likes != null, w + ': its counts, views at 0: ' + v.views + '/' + v.likes);
    await p.screenshot({ path: out + '/pp-' + w + '-viewer.png' });
    await p.click('.xlb-sub [data-xlb=copy]'); await p.waitForTimeout(300);
    await p.click('.xlb-sub [data-xlb=map]'); await p.waitForTimeout(1200);
    ok(await p.evaluate(() => !document.querySelector('.xlb') && !!document.querySelector('.tvm, .tvm-side, #tvm, [class^="tvm"]')), w + ': See on the map closes the viewer and opens the map');
    ok(!errs.length, w + ': no script errors (Explore): ' + errs.join('; ')); await ctx.close();
    // ---- a landmark's own page ----
    ({ p, ctx, errs } = await page(b, w, h, '/place/landmark.php?id=1'));
    ok(await p.evaluate(() => (document.querySelector('.eg-bar .eg-n span') || {}).textContent === '0'), w + ': views show at 0 on the page');
    await p.click('.xh-acts .eg-more, .eg-bar .eg-more'); await p.waitForTimeout(200);
    const items = await p.evaluate(() => { const m = document.querySelector('.eg-ml:not([hidden])'); if (!m) return null; const r = m.getBoundingClientRect();
      return { t: [...m.querySelectorAll('[role=menuitem]')].map(x => x.textContent.trim()), on: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight }; });
    ok(items && items.t[0] === 'See on the map' && items.t.some(x => x === 'Directions') && items.t.some(x => /^Add to a tripComing/.test(x)) && items.t.some(x => x === 'Suggest an edit'), w + ': ⋯ items: ' + (items && items.t));
    ok(items && items.on, w + ': ⋯ fully on screen');
    await p.screenshot({ path: out + '/pp-' + w + '-menu.png' });
    await p.keyboard.press('Escape'); ok(await p.evaluate(() => !document.querySelector('.eg-ml:not([hidden])')), w + ': ⋯ closes with Esc');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml .eg-mx'); ok(await p.evaluate(() => !document.querySelector('.eg-ml:not([hidden])')), w + ': ⋯ closes with ×');
    await p.click('.eg-bar .eg-more'); await p.mouse.click(5, h - 5); ok(await p.evaluate(() => !document.querySelector('.eg-ml:not([hidden])')), w + ': ⋯ closes with a click outside');
    // the viewer on its own photo: the page itself
    if (await p.$('.xh [data-lb]')) { await p.click('.xh [data-lb]'); await p.waitForTimeout(400);
      ok(await p.evaluate(() => (document.querySelector('.xlb-sub a.xlb-to') || {}).textContent === 'Painted Desert Inn' && !!document.querySelector('.xlb-acts .eg-bar')), w + ': on its own page the viewer names the page, with its bar');
      await p.keyboard.press('Escape'); }
    ok(!errs.length, w + ': no script errors (landmark): ' + errs.join('; ')); await ctx.close();
    // ---- a business on a street: Plan a trip here ----
    ({ p, ctx, errs } = await page(b, w, h, '/listing/view.php?id=7003'));
    await p.click('.eg-bar .eg-more'); await p.waitForTimeout(150);
    const li = await p.evaluate(() => [...document.querySelectorAll('.eg-ml:not([hidden]) [role=menuitem]')].map(x => x.textContent.trim()));
    ok(li.includes('Directions') && li.includes('Save contact') && li.includes('Plan a trip here'), w + ': a business with a street: ' + li);
    await p.click('.eg-ml [data-eg-trip]'); await p.waitForSelector('.eg-trip', { timeout: 4000 });
    const tp = await p.evaluate(() => { const t = document.querySelector('.eg-trip'), r = t.getBoundingClientRect(); return { j: t.querySelectorAll('li a').length, plan: t.querySelector('.eg-plan').getAttribute('href'), on: r.left >= 0 && r.right <= innerWidth }; });
    ok(tp.j === 2 && tp.plan === '/discovery/?view=plan&cluster=1', w + ': Plan a trip here: the ready-made journeys first, then the planner: ' + JSON.stringify(tp));
    ok(tp.on, w + ': the trip panel on screen');
    await p.screenshot({ path: out + '/pp-' + w + '-trip.png' });
    await p.keyboard.press('Escape'); ok(await p.evaluate(() => !document.querySelector('.eg-trip')), w + ': the trip panel closes with Esc');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml [data-eg-trip]'); await p.waitForSelector('.eg-trip'); await p.click('.eg-trip .eg-tx'); ok(await p.evaluate(() => !document.querySelector('.eg-trip')), w + ': … and with ×');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml [data-eg-trip]'); await p.waitForSelector('.eg-trip'); await p.mouse.click(3, 3); await p.waitForTimeout(100); ok(await p.evaluate(() => !document.querySelector('.eg-trip')), w + ': … and with a click outside');
    ok(!errs.length, w + ': no script errors (business): ' + errs.join('; ')); await ctx.close();
    // ---- a business with only a PO Box ----
    ({ p, ctx, errs } = await page(b, w, h, '/listing/view.php?id=7005'));
    const po = await p.evaluate(() => ({ page: !!document.querySelector('.lv-acts [data-out=directions]'), menu: [...document.querySelectorAll('.eg-ml .eg-mo')].map(x => x.textContent) }));
    ok(!po.page, w + ': a mailbox: no Directions on its page');
    ok(po.menu.some(x => /^DirectionsMailing address only/.test(x)), w + ': ⋯ says why: ' + po.menu);
    ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth), w + ': no sideways scroll'); await ctx.close();
  }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
