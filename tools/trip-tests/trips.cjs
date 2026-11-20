// tools/trip-tests/trips.cjs: trips you can save (decisions/0174 release 2), rendered at 1280 and 390.
// Harness: the release's files over the live mirror, route.php signing in user 1 unless the cookie guest=1 is set.
// Run: php trips-seed.php <root> && BASE=http://127.0.0.1:8790 SEED="php trips-seed.php <root>" node trips.cjs
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8790', SEED = process.env.SEED || '';
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('ok   ' + m); } else { fail++; console.log('FAIL ' + m); } };
const api = (p) => p.evaluate(() => fetch('/api/trips.php', {credentials: 'include'}).then(r => r.json()));

(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    if (SEED) execSync(SEED);
    const tag = '@' + w + ': ';
    const ctx = await b.newContext({viewport: {width: w, height: 820}});
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
    const go = async (u) => { await p.goto(BASE + u, {waitUntil: 'load'}); await p.waitForTimeout(500); };
    const fits = async () => { const bb = await p.locator('.tvtp').boundingBox(); return bb && bb.x >= 0 && bb.x + bb.width <= w + .5 && bb.y >= 0 && bb.y + bb.height <= 820.5; };

    // ---- a member ----
    await go('/place/landmark.php?id=1');
    await p.click('.eg-bar .eg-more');
    const item = p.locator('.eg-ml [data-trip-add]');
    ok(await item.isVisible(), tag + '⋯ has Add to a trip, switched on');
    await item.click();
    await p.waitForSelector('.tvtp .tvtp-t.new');
    ok(await p.evaluate(() => !document.querySelector('.eg-ml:not([hidden])')), tag + 'the ⋯ menu closes as the picker opens');
    ok((await p.locator('.tvtp').innerText()).includes('Add to a trip') && /New trip: Trip to /.test(await p.locator('.tvtp .new').innerText()), tag + 'the picker: New trip, named for the place');
    ok(await fits(), tag + 'the picker is wholly in the window');
    await p.click('.tvtp .new');
    await p.waitForSelector('.tvtp .tvtp-done');
    const done = await p.locator('.tvtp .tvtp-done').innerText();
    ok(/Added to Trip to /.test(done) && done.includes('Open it') && done.includes('Undo'), tag + '"Added to Trip to …" with Open it and Undo');
    let d = await api(p);
    ok(d.trips.length === 1 && d.trips[0].stops === 1, tag + 'the trip is saved with its stop');
    ok(/\/user\/trips\/trip\.php\?id=\d+$/.test(await p.locator('.tvtp-done a').getAttribute('href')), tag + 'Open it goes to the trip\'s page');
    await p.click('.tvtp [data-undo]'); await p.waitForTimeout(400);
    d = await api(p);
    ok(d.trips[0].stops === 0 && (await p.locator('.tvtp').innerText()).includes('Taken out'), tag + 'Undo takes it out again');
    await p.keyboard.press('Escape'); ok(!(await p.locator('.tvtp').count()), tag + 'Esc closes the picker');

    // a listing: the existing trip is offered first; the same place twice says already
    await go('/listing/view.php?id=3');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml [data-trip-add]');
    await p.waitForSelector('.tvtp .tvtp-t');
    const rows = await p.locator('.tvtp .tvtp-t').allInnerTexts();
    ok(rows.length === 2 && /Trip to/.test(rows[0]) && /New trip/.test(rows[1]), tag + 'your trips first, newest first, then New trip');
    await p.locator('.tvtp .tvtp-t').first().click(); await p.waitForSelector('.tvtp .tvtp-done');
    await p.locator('.tvtp-x').click(); ok(!(await p.locator('.tvtp').count()), tag + '× closes the picker');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml [data-trip-add]'); await p.waitForSelector('.tvtp .tvtp-t');
    await p.locator('.tvtp .tvtp-t').first().click(); await p.waitForSelector('.tvtp .tvtp-done');
    ok((await p.locator('.tvtp .tvtp-done').innerText()).includes('Already in'), tag + 'the same place twice: "Already in", not added twice');
    await p.mouse.click(w - 10, 10); await p.waitForTimeout(150);
    ok(!(await p.locator('.tvtp').count()), tag + 'a click outside closes the picker');

    // greyed with why
    await go('/listing/view.php?id=7006');
    await p.click('.eg-bar .eg-more');
    ok(/Add to a trip\s*Marked closed/.test(await p.locator('.eg-ml').innerText()) && !(await p.locator('.eg-ml [data-trip-add]').count()), tag + 'a closed business: greyed, with why');
    await p.keyboard.press('Escape');

    // Make it my trip
    await go('/journeys/suggested.php?id=2');
    await p.click('[data-trip-journey]'); await p.waitForSelector('.tvtp .tvtp-done');
    ok(/Show Low outdoors: 3 stops/.test(await p.locator('.tvtp').innerText()), tag + 'Make it my trip copies the journey\'s stops into a trip');
    await p.keyboard.press('Escape');

    // Get Local: a card's ⋯
    await go('/directory/?lat=34.25&lon=-110.03&radius=25&near=Show%20Low'); await p.waitForSelector('.dr-it', {timeout: 8000}); await p.waitForTimeout(600);
    await p.click('.dr-it >> nth=0 >> .dr-mb');
    const gl = p.locator('.dr-ml:not([hidden]) [data-trip-add]');
    ok(await gl.count() === 1, tag + 'Get Local: a card\'s ⋯ has Add to a trip');
    await gl.click(); await p.waitForSelector('.tvtp .tvtp-t');
    ok(await p.evaluate(() => !document.querySelector('.dr-ml:not([hidden])')) && await fits(), tag + 'Get Local: its ⋯ closes and the picker fits');
    await p.keyboard.press('Escape');

    // Your trips
    await go('/user/trips/');
    const secs = await p.locator('section.tvtr').count();
    ok(secs === 2, tag + 'Your trips: both trips (' + secs + ')');
    const js = p.locator('section.tvtr', {hasText: 'Show Low outdoors'});
    ok(await js.locator('.tvtr-s li').count() === 3 && await js.locator('a[data-maps]').count() === 1, tag + 'a trip: its stops in order and Send to Maps');
    await js.locator('[data-trip-out]').first().click(); await p.waitForTimeout(400);
    ok((await js.locator('.tvtr-s li').first().innerText()).includes('Taken out'), tag + '× takes a stop out, with Undo');
    await js.locator('.tvtr-u').first().click(); await p.waitForLoadState('load'); await p.waitForTimeout(500);
    ok(await p.locator('section.tvtr', {hasText: 'Show Low outdoors'}).locator('.tvtr-s li a').count() === 3, tag + 'Undo puts it back');
    const js2 = p.locator('section.tvtr', {hasText: 'Show Low outdoors'});
    await js2.locator('[data-trip-rename]').click();
    await p.fill('.tv-ask input, .tv-ask textarea', 'Saturday out'); await p.click('.tv-ask .yes');
    await p.waitForTimeout(400);
    ok((await p.locator('section.tvtr [data-trip-name]').allInnerTexts()).includes('Saturday out'), tag + 'Rename');
    await p.locator('section.tvtr', {hasText: 'Saturday out'}).locator('[data-trip-delete]').click();
    await p.click('.tv-ask .yes'); await p.waitForLoadState('load'); await p.waitForTimeout(500);
    ok(await p.locator('section.tvtr').count() === 1 && (await p.locator('.tvtr-del summary').innerText()).includes('Deleted trips (1)'), tag + 'Delete: kept under Deleted trips');
    await p.click('.tvtr-del summary'); await p.click('[data-trip-undelete]'); await p.waitForLoadState('load'); await p.waitForTimeout(500);
    ok(await p.locator('section.tvtr').count() === 2, tag + 'Put back');
    ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth), tag + 'Your trips: no sideways scroll');
    if (w === 390) ok(await p.evaluate(() => { const r = document.querySelector('section.tvtr').getBoundingClientRect(); return r.left <= 4 && innerWidth - r.right <= 4; }), tag + 'Your trips: cards edge to edge');

    // ---- a guest: one trip on the device ----
    await ctx.addCookies([{name: 'guest', value: '1', url: BASE}]);
    await go('/place/landmark.php?id=1');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml [data-trip-add]'); await p.waitForSelector('.tvtp [data-trip="device"]');
    ok((await p.locator('.tvtp').innerText()).includes('one trip is kept on this device') && await p.locator('.tvtp [data-join]').count() === 1, tag + 'guest: one trip on this device, and an account offered');
    await p.click('.tvtp [data-trip="device"]'); await p.waitForSelector('.tvtp .tvtp-done');
    let dev = await p.evaluate(() => JSON.parse(localStorage.getItem('tv-trip-device')));
    ok(dev && dev.stops.length === 1 && dev.stops[0].kind === 'lm', tag + 'guest: kept on the device');
    await p.keyboard.press('Escape');
    await go('/listing/view.php?id=3');
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml [data-trip-add]'); await p.waitForSelector('.tvtp [data-trip="device"]');
    await p.click('.tvtp [data-trip="device"]'); await p.waitForSelector('.tvtp .tvtp-done'); await p.keyboard.press('Escape');
    await go('/user/trips/'); await p.waitForSelector('#trip-device');
    ok(await p.locator('#trip-device .tvtr-s li a').count() === 2 && (await p.locator('#trip-device').innerText()).includes('kept on this device only'), tag + 'guest: Your trips draws the device\'s trip');
    await p.locator('#trip-device [data-device-out]').first().click(); await p.waitForSelector('#trip-device');
    ok(await p.locator('#trip-device .tvtr-s li').count() === 1, tag + 'guest: × takes a stop out');
    await p.locator('.tvtr-u').click(); await p.waitForTimeout(500);
    ok(await p.locator('#trip-device .tvtr-s li').count() === 2, tag + 'guest: Undo puts it back');
    ok(await p.locator('#trip-device [data-join]').count() === 1, tag + 'guest: Make an account to keep it');

    // signing in moves it into the account
    await ctx.clearCookies();
    const before = (await api(p)).trips.length;
    await go('/user/trips/'); await p.waitForTimeout(1500);
    dev = await p.evaluate(() => localStorage.getItem('tv-trip-device'));
    d = await api(p);
    ok(dev === null && d.trips.length === before + 1 && d.trips[0].stops === 2, tag + 'signed in: the device\'s trip moves into the account, then leaves the device');
    ok(!errs.length, tag + 'no script errors' + (errs.length ? ': ' + errs.join('; ') : ''));
    await ctx.close();
  }
  await b.close();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
