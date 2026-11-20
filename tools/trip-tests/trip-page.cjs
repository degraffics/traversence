// tools/trip-tests/trip-page.cjs: the trip page (decisions/0174 release 3), rendered at 1280 and 390.
// Harness as trips.cjs; Leaflet is served from a local copy (LEAFLET=<dir holding leaflet-1.9.4/>).
// Run: SEED="php trips-seed.php <root>" LEAFLET=<dir> BASE=http://127.0.0.1:8790 node trip-page.cjs
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8790', SEED = process.env.SEED || '', N = process.env.LEAFLET || '';
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('ok   ' + m); } else { fail++; console.log('FAIL ' + m); } };

(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    if (SEED) execSync(SEED);
    const tag = '@' + w + ': ';
    const ctx = await b.newContext({viewport: {width: w, height: 900}});
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
    await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => { const u = r.request().url();
      const m = {'leaflet.min.css': 'leaflet-1.9.4/dist/leaflet.css', 'leaflet.min.js': 'leaflet-1.9.4/dist/leaflet.js'}, k = Object.keys(m).find(x => u.endsWith('/' + x));
      if (k && N) return r.fulfill({contentType: k.endsWith('.css') ? 'text/css' : 'text/javascript', body: fs.readFileSync(N + '/' + m[k])});
      if (/tile\.openstreetmap\.org/.test(u)) return r.fulfill({contentType: 'image/png', body: Buffer.alloc(0)});
      return r.abort(); });
    await p.addInitScript(() => { window.print = () => { window.__printed = true; }; });
    const go = async (u) => { await p.goto(BASE + u, {waitUntil: 'load'}); await p.waitForTimeout(700); };
    const names = () => p.locator('.tp-s .tp-nm a').allInnerTexts();

    // the Trip Planner: Save as a trip, then Use these stops
    await go('/discovery/?view=plan');
    const hasPlan = await p.locator('#dv-plan-save').count();
    ok(hasPlan === 1, tag + 'the Trip Planner has Save as a trip');
    await p.evaluate(() => { const f = document.getElementById('dv-plan'); f.where.value = 'Show Low, AZ'; f.where_slug.value = 'show-low'; f.where_level.value = 'cluster'; f.days.value = 'weekend'; f.when.value = 'this weekend'; });
    await p.evaluate(() => document.getElementById('dv-plan-save').click());
    await p.waitForURL(/\/user\/trips\/trip\.php\?id=\d+/, {timeout: 8000}); await p.waitForTimeout(600);
    ok((await p.locator('h1[data-trip-name]').innerText()) === 'Trip to Show Low' && await p.locator('select[name=days]').inputValue() === '2' && /^\d{4}-\d\d-\d\d$/.test(await p.locator('input[name=starts_on]').inputValue()), tag + 'Save as a trip: named for the place, a weekend is 2 days, this weekend dated');
    ok(await p.locator('[data-tp-journey]').count() >= 1, tag + 'an empty trip offers the place\'s ready-made journeys');
    const url = p.url();
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('[data-tp-journey]').last().click()]); await p.waitForTimeout(400);
    ok((await names()).length === 3, tag + 'Use these stops: the journey\'s 3 stops are in');

    // the page: times, legs, labelled zone, map
    const tms = await p.locator('.tp-tm').allInnerTexts();
    ok(tms.length === 3 && tms.every(t => /^\d{1,2}:\d\d (AM|PM) [A-Z]{3,4}/.test(t)) && tms[0].startsWith('9:00 AM'), tag + 'every stop has a time, labelled with its zone; the first at 9:00 AM');
    ok(await p.locator('.tp-leg').count() === 2 && (await p.locator('.tp-leg').first().innerText()).startsWith('about '), tag + 'the legs between say "about"');
    ok(/About .* of travel, [\d.]+ mi · done about/.test(await p.locator('.tp-tot').first().innerText()), tag + 'a day\'s travel, miles and when it\'s done');
    await p.waitForSelector('.tp-pin', {timeout: 6000}).catch(() => {});
    ok(await p.locator('.tp-pin').count() === 3 && (await p.locator('.tp-pin b').first().innerText()) === '1' && await p.locator('#tp-map path.leaflet-interactive').count() >= 1, tag + 'the map: numbered pins and the line between');
    if (w === 390) ok(await p.evaluate(() => document.querySelector('.tp-mapw').getBoundingClientRect().top < document.querySelector('.tp-day').getBoundingClientRect().top), tag + 'on a phone the map comes before the list');

    // reorder with ↓, then ↑ back
    const n0 = await names();
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').first().locator('[data-tp-down]').click()]); await p.waitForTimeout(400);
    const n1 = await names();
    ok(n1[0] === n0[1] && n1[1] === n0[0], tag + '↓ moves a stop down');
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').nth(1).locator('[data-tp-up]').click()]); await p.waitForTimeout(400);
    ok((await names()).join() === n0.join(), tag + '↑ moves it back');
    // drag (desktop)
    if (w === 1280) {
      await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').nth(2).dragTo(p.locator('.tp-s').first(), {targetPosition: {x: 20, y: 4}})]); await p.waitForTimeout(400);
      ok((await names())[0] === n0[2], tag + 'drag a stop to the top');
      await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').first().locator('[data-tp-down]').click()]); await p.waitForTimeout(400);
      await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').nth(1).locator('[data-tp-down]').click()]); await p.waitForTimeout(400);
    }
    // to another day
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').last().locator('[data-tp-day-of]').selectOption('2')]); await p.waitForTimeout(400);
    ok(await p.locator('[data-tp-day="2"] .tp-s').count() === 1 && (await p.locator('[data-tp-day="2"] .tp-tm').innerText()).startsWith('9:00 AM'), tag + 'moved to day 2: it starts that day at 9:00 AM');
    // a later start: a stop closed when you'd arrive, and Move to day N
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('select[name=start_min]').selectOption('780')]); await p.waitForTimeout(400);
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-s').first().locator('[data-tp-stay]').selectOption('240')]); await p.waitForTimeout(400);
    const bad = p.locator('.tp-s .tv-note.bad');   // the status line, drawn as the site's notice (tv-ask.js)
    ok(await bad.count() >= 1 && /Closed when you'd arrive|After dark|Before sunrise/.test(await bad.first().innerText()) && await bad.first().locator('[data-tp-move]').count() === 1, tag + 'a stop shut on arrival (its hours, or after dark outdoors): said, with Move to day 2: ' + (await bad.count() ? await bad.first().innerText() : 'none'));
    const shut = (await bad.first().locator('xpath=ancestor::li').locator('.tp-nm a').innerText());
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), bad.first().locator('[data-tp-move]').click()]); await p.waitForTimeout(400);
    ok((await p.locator('[data-tp-day="2"] .tp-nm a').allInnerTexts()).includes(shut), tag + 'Move to day 2 moves it');
    // take one out, Undo
    const before = (await names()).length;
    await p.locator('.tp-s').first().locator('[data-tp-out]').click(); await p.waitForTimeout(500);
    ok((await p.locator('.tp-s').first().innerText()).includes('Taken out'), tag + '× takes it out, with Undo');
    await Promise.all([p.waitForNavigation({waitUntil: 'load'}), p.locator('.tp-undo').click()]); await p.waitForTimeout(400);
    ok((await names()).length === before, tag + 'Undo puts it back');
    // print, rename
    await p.click('[data-tp-print]'); ok(await p.evaluate(() => window.__printed === true), tag + 'Print');
    await p.emulateMedia({media: 'print'});
    ok(await p.evaluate(() => getComputedStyle(document.querySelector('.tp-ctl')).display === 'none' && getComputedStyle(document.querySelector('.tp-s')).display !== 'none'), tag + 'printed: the stops, not the buttons');
    await p.emulateMedia({media: 'screen'});
    await p.click('[data-tp-rename]'); await p.fill('.tv-ask input, .tv-ask textarea', 'Show Low weekend'); await p.click('.tv-ask .yes'); await p.waitForTimeout(500);
    ok((await p.locator('h1[data-trip-name]').innerText()) === 'Show Low weekend', tag + 'Rename');
    ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth), tag + 'no sideways scroll');
    if (w === 390) ok(await p.evaluate(() => [...document.querySelectorAll('.tp-day, .tp-set')].every(x => { const r = x.getBoundingClientRect(); return r.left <= 4 && innerWidth - r.right <= 4; })), tag + 'days and settings edge to edge');
    // Your trips links here; Delete goes back to Your trips
    await go('/user/trips/');
    ok(await p.locator('a[href^="/user/trips/trip.php?id="]', {hasText: 'Show Low weekend'}).count() === 1, tag + 'Your trips links to the trip page');
    await go(url.replace(BASE, ''));
    await p.click('[data-tp-delete]'); await p.click('.tv-ask .yes'); await p.waitForURL(/\/user\/trips\/$/, {timeout: 6000});
    ok(true, tag + 'Delete: back to Your trips');
    // someone else's, and a guest
    await go('/user/trips/trip.php?id=999999');
    ok((await p.locator('h1').first().innerText()) === 'Trip not found' && await p.locator('a[href="/user/trips/"]').count() >= 1, tag + 'another\'s or a missing trip: Trip not found, with the way back');
    // search's Peek: Add to a trip
    await go('/directory/?lat=34.25&lon=-110.03&radius=25&near=Show%20Low'); await p.waitForSelector('.dr-it', {timeout: 8000}); await p.waitForTimeout(500);
    await p.locator('.dr-it').first().locator('.dr-mb').click(); await p.locator('.dr-ml:not([hidden]) [data-tv-peek]').click();
    await p.waitForSelector('.tvs-peek [data-trip-add]', {timeout: 6000}).catch(() => {});
    ok(await p.locator('.tvs-peek [data-trip-add]').count() === 1, tag + 'Peek (the planner\'s results open there) has Add to a trip');
    await p.locator('.tvs-peek [data-trip-add]').click(); await p.waitForSelector('.tvtp .tvtp-t', {timeout: 6000}).catch(() => {});
    ok(await p.locator('.tvtp .tvtp-t').count() >= 1, tag + 'and it opens the picker');
    await p.keyboard.press('Escape');
    await ctx.addCookies([{name: 'guest', value: '1', url: BASE}]);
    await go('/user/trips/trip.php?id=1');
    ok(/\/user\/trips\/$/.test(p.url()), tag + 'a guest goes to Your trips (their trip is on the device)');
    ok(!errs.length, tag + 'no script errors' + (errs.length ? ': ' + errs.join('; ') : ''));
    await ctx.close();
  }
  const mv = fs.readFileSync(process.env.MAPVIEW || '/dev/null', 'utf8');
  if (mv) ok(/data-trip-add="' \+ esc\(JSON\.stringify\(tripRef\(l\)\)\)/.test(mv) && /tv-trips\.js/.test(mv), 'the map\'s card has Add to a trip and loads the picker');
  await b.close();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
