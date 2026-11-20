// tools/trip-tests/sharing.cjs: sharing, Personal Guides and counts (decisions/0174 release 4), rendered at 1280 and 390.
// Harness as trips.cjs (user 1 signed in unless the cookie guest=1). Seed: trips-seed.php, then sharing-seed.php.
// Run: SEED="php trips-seed.php <root> && php sharing-seed.php <root>" LEAFLET=<dir> BASE=http://127.0.0.1:8790 node sharing.cjs
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8790', SEED = process.env.SEED || '', N = process.env.LEAFLET || '';
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('ok   ' + m); } else { fail++; console.log('FAIL ' + m); } };

(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const seed = SEED ? execSync(SEED).toString() : '';
    const trip = +((/trip=(\d+)/.exec(seed) || [])[1] || 0);
    const tag = '@' + w + ': ';
    const ctx = await b.newContext({viewport: {width: w, height: 900}});
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
    const route = async (pg) => pg.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => { const u = r.request().url();
      const m = {'leaflet.min.css': 'leaflet-1.9.4/dist/leaflet.css', 'leaflet.min.js': 'leaflet-1.9.4/dist/leaflet.js'}, k = Object.keys(m).find(x => u.endsWith('/' + x));
      if (k && N) return r.fulfill({contentType: k.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(N + '/' + m[k])});
      if (/tile\.openstreetmap\.org/.test(u)) return r.fulfill({contentType: 'image/png', body: Buffer.alloc(0)});
      return r.abort(); });
    await route(p);
    const go = async (pg, u) => { await pg.goto(BASE + u, {waitUntil: 'load'}); await pg.waitForTimeout(600); };

    // ---- Share a link ----
    await go(p, '/user/trips/trip.php?id=' + trip);
    ok(await p.locator('[data-tp-share]').count() === 1 && await p.locator('[data-tp-sharebox]').isHidden(), tag + 'the owner has Share; no link until asked');
    await p.click('[data-tp-share]'); await p.waitForFunction(() => document.querySelector('[data-tp-link]').value !== '');
    const link = await p.locator('[data-tp-link]').inputValue();
    ok(/^https:\/\/traversence\.com\/trip\/\?t=[a-f0-9]{32}$/.test(link) && /Anyone with this link can view/.test(await p.locator('[data-tp-sharebox]').innerText()), tag + 'Share makes a view-only link, and says so');
    const path = link.replace('https://traversence.com', '');
    // a guest opens it
    const g = await b.newContext({viewport: {width: w, height: 900}}); await g.addCookies([{name: 'guest', value: '1', url: BASE}]);
    const gp = await g.newPage(); await route(gp); const gerrs = []; gp.on('pageerror', e => gerrs.push(String(e)));
    await go(gp, path);
    const body = await gp.locator('body').innerText();
    ok(/A shared trip, view only/.test(body) && await gp.locator('h1').first().innerText() === 'Shared seed', tag + 'the link opens the trip, view only');
    ok(await gp.locator('.tp-ctl, [data-tp-set], [data-tp-delete], [data-tp-share], [data-tp-rename], [data-tp-stay], .tp-s[draggable]').count() === 0, tag + 'no controls: nothing to change');
    ok(!/Shelter/.test(body) && /1 stop in this trip can't be shown publicly/.test(body), tag + 'a place kept private never shows, nor its name: "1 stop … can\'t be shown"');
    ok(/Gone Grill/.test(body) && await gp.locator('.tp-s.off', {hasText: 'Gone Grill'}).count() === 1, tag + 'a business marked closed shows, greyed');
    ok(!/Test Owner/.test(await gp.locator('main, .tp-grid, .tp-h, .tp-dm').allInnerTexts().then(a => a.join(' '))), tag + 'nothing about its owner');
    ok(await gp.evaluate(() => document.documentElement.scrollWidth <= innerWidth), tag + 'shared view: no sideways scroll');
    // turned off
    await p.click('[data-tp-unshare]'); await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.click('.tv-ask .yes')]); await p.waitForTimeout(400);
    await go(gp, path);
    ok(/This trip isn't shared/.test(await gp.locator('body').innerText()) && await gp.locator('a[href="/discovery/?view=plan"]').count() >= 1, tag + 'Turn off: the link says so, with a way on');

    // ---- Publish as a Personal Guide ----
    await go(p, '/user/trips/trip.php?id=' + trip);
    await p.click('[data-tp-guide]');
    await p.fill('[data-tp-gintro]', 'Where I stop on a Saturday in Show Low: coffee first, then the hardware store.');
    await p.click('[data-tp-publish]'); await p.waitForNavigation({waitUntil: 'load'}).catch(() => {}); await p.waitForTimeout(500);
    ok(/published as a Personal Guide/.test(await p.locator('.tp-dm').first().innerText()), tag + 'Publish: the trip says it\'s a Personal Guide');
    const gid = await p.evaluate(() => { const a = document.querySelector('[data-tp-guidebox] a[href^="/guide/personal.php?id="]'); return a ? a.getAttribute('href') : ''; });
    ok(/\/guide\/personal\.php\?id=\d+/.test(gid), tag + 'its link');
    await go(gp, gid);
    const gb = await gp.locator('body').innerText();
    ok(await gp.locator('h1').first().innerText() === 'Shared seed' && /Personal Guide/.test(gb) && /By Test Owner/.test(gb) && /coffee first/.test(gb), tag + 'the guide: kind, title, credited to its author, its words');
    ok(await gp.locator('.eg-bar').count() === 1 && await gp.locator('#comments').count() === 1 && await gp.locator('[data-link^="place:pg:"]').count() === 1, tag + 'a content page: the engage bar, comments, the Link button');
    ok(!/Shelter/.test(gb) && await gp.locator('.tp-ctl').count() === 0, tag + 'the guide never shows the private place, and has no controls');
    ok(await gp.evaluate(() => document.documentElement.scrollWidth <= innerWidth), tag + 'guide: no sideways scroll');
    // staff hide it in Admin → Journeys
    await go(p, '/admin/journeys.php');
    const row = p.locator('#personal-guides div', {hasText: 'Shared seed'});
    ok(await row.count() === 1, tag + 'Admin → Journeys lists Personal Guides');
    await row.locator('input[name=why]').fill('Testing a hide');
    await row.locator('button', {hasText: 'Hide'}).click();
    if (await p.locator('.tv-ask .yes').count()) await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.click('.tv-ask .yes')]); else await p.waitForLoadState('load');
    await p.waitForTimeout(400);
    await go(gp, gid);
    ok(/Guide not found/.test(await gp.locator('body').innerText()), tag + 'hidden: readers can\'t open it, and are offered a way on');
    await go(p, '/user/trips/trip.php?id=' + trip); await p.click('[data-tp-guide]');
    ok(/Hidden by our team/.test(await p.locator('[data-tp-guidebox]').innerText()) && /Testing a hide/.test(await p.locator('[data-tp-guidebox]').innerText()), tag + 'its author sees it was hidden, and why');
    await go(p, '/admin/journeys.php');
    await p.locator('#personal-guides div', {hasText: 'Shared seed'}).locator('button', {hasText: 'Restore'}).click(); await p.waitForTimeout(800);
    await go(gp, gid);
    ok(await gp.locator('h1').first().innerText() === 'Shared seed', tag + 'Restore: back');
    // the author withdraws it
    await go(p, '/user/trips/trip.php?id=' + trip); await p.click('[data-tp-guide]');
    await p.click('[data-tp-withdraw]'); await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.click('.tv-ask .yes')]); await p.waitForTimeout(400);
    await go(gp, gid);
    ok(/Guide not found/.test(await gp.locator('body').innerText()), tag + 'Withdraw: it stops showing');

    // ---- counts ----
    // a place's page: "Added to N trips this month" beside its views (Jason, 2026-11-20: "Also on the place's page")
    execSync(`mysql lv_main -e "DELETE FROM platform_counts WHERE metric = 'trip' AND k = 'lm:1'; INSERT INTO platform_counts (day, metric, k, n, visits) VALUES (CURDATE(), 'trip', 'lm:1', 3, 3)"`);
    await go(p, '/place/landmark.php?id=1');
    ok((await p.locator('.eg-bar [data-eg-trips]').getAttribute('title').catch(() => '')) === 'Added to 3 trips this month', tag + 'a place\'s page: "Added to 3 trips this month" beside its views');
    await go(p, '/listing/view.php?id=3');
    ok(await p.locator('[data-eg-trips]').count() === 0, tag + 'a business\'s page shows no trip count (theirs is in their insights)');
    await go(p, '/admin/insights.php');
    ok(/Places added to trips/.test(await p.locator('body').innerText()), tag + 'Admin → Insights: Places added to trips');
    ok(!errs.length && !gerrs.length, tag + 'no script errors' + (errs.concat(gerrs).length ? ': ' + errs.concat(gerrs).join('; ') : ''));
    await g.close(); await ctx.close();
  }
  await b.close();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
