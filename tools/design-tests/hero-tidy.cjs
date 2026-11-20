// tools/design-tests/hero-tidy.cjs: the hero tidy (Jason, 2026-11-20), rendered at 1280 and 390.
//   ⋯ starts Link to, Claim, Add to a trip; the page's Link icon hides while ⋯ is open; Link to runs the page's Link;
//   the stock photo's details see-through; Explore's default place in one slim line; the hub's stray text gone;
//   its areas listed as the data has them (seed: hero-tidy-seed.php adds three "Twin Town" areas to geo hub 1). Copy
//   stock-page.php to the harness's website/test/stock.php first.
// Run: SEED="php hero-tidy-seed.php <root>" BASE=http://127.0.0.1:8790 node hero-tidy.cjs
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8790';
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('ok   ' + m); } else { fail++; console.log('FAIL ' + m); } };
(async () => {
  if (process.env.SEED) execSync(process.env.SEED);
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const tag = '@' + w + ': ';
    const ctx = await b.newContext({viewport: {width: w, height: 900}});
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
    await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => r.abort());
    const go = async (u) => { await p.goto(BASE + u, {waitUntil: 'load'}); await p.waitForTimeout(700); };
    for (const [u, link] of [['/listing/view.php?id=3', 'Link to Bluebird Diner'], ['/place/landmark.php?id=1', 'Link to Painted Desert Inn']]) {
      await go(u);
      const lk = p.locator('.tv-link').first();
      const before = await lk.count() ? await lk.isVisible() : false;
      await p.click('.eg-bar .eg-more');
      const items = (await p.locator('.eg-ml:not([hidden]) [role=menuitem]').allInnerTexts()).map(x => x.split('\n')[0].trim());
      ok(items[0] === link && items[1] === 'Claim' && items[2] === 'Add to a trip', tag + u + ': ⋯ starts Link to, Claim, Add to a trip: ' + items.slice(0, 4).join(' | '));
      if (before) ok(!(await lk.isVisible()) || await lk.evaluate(e => getComputedStyle(e.closest('.eg-hid') || e).visibility === 'hidden'), tag + u + ': the page\'s Link icon hides while ⋯ is open');
      await p.keyboard.press('Escape'); await p.waitForTimeout(150);
      if (before) ok(await lk.isVisible() && await lk.evaluate(e => !e.closest('.eg-hid')), tag + u + ': and comes back when ⋯ closes');
    }
    // Link to runs the page's own Link (signed in: it links, or asks to activate Linking)
    await go('/listing/view.php?id=3');
    // a listing's Claim goes where the corner's Claim goes
    const claim = await p.evaluate(() => { const a = [...document.querySelectorAll('.eg-ml [role=menuitem]')].find(x => /^Claim/.test(x.textContent.trim())); return a ? (a.getAttribute('href') || 'greyed: ' + x_why(a)) : null; function x_why(a) { return (a.querySelector('small') || {}).textContent || ''; } });
    ok(claim === '/claim.php?listing=3' || /^greyed: ./.test(claim || ''), tag + 'Claim: the claim form, or greyed with why (' + claim + ')');
    // Link to runs the page's own Link button (its own flow from there: sign in, Activate, or linked)
    await p.evaluate(() => { window.__lk = 0; document.querySelector('[data-link="business:3"] .tv-link').addEventListener('click', e => { window.__lk++; e.preventDefault(); e.stopImmediatePropagation(); }, true); });
    await p.click('.eg-bar .eg-more'); await p.click('.eg-ml:not([hidden]) [data-eg-linkto]'); await p.waitForTimeout(300);
    ok(!(await p.locator('.eg-ml:not([hidden])').count()) && await p.evaluate(() => window.__lk) === 1, tag + 'Link to closes ⋯ and runs the page\'s Link button');
    // the stock photo's details: see-through, still readable (test/stock.php: stock-page.php)
    await go('/test/stock.php');
    const stk = await p.locator('.tv-stk summary').count();
    ok(stk === 1, tag + 'the stock test page draws its label');
    if (stk) {
      await p.locator('.tv-stk summary').first().click(); await p.waitForTimeout(200);
      const st = await p.locator('.tv-stk-d').first().evaluate(e => { const c = getComputedStyle(e); return {bg: c.backgroundColor, blur: c.backdropFilter || c.webkitBackdropFilter, text: e.innerText}; });
      const a = /(?:,|\/)\s*([\d.]+)\)$/.exec(st.bg);
      ok(a && +a[1] < 1 && +a[1] >= .7 && /blur/.test(st.blur), tag + 'the stock details: see-through (' + st.bg + '), blurred behind');
      ok(!/Representative photo:/.test(st.text), tag + 'its words don\'t repeat "Representative photo"');
    }
    // Explore: the default place, one slim line
    await go('/discovery/');
    const ask = await p.evaluate(() => { const b = document.getElementById('dv-ask'); if (!b) return null; b.hidden = false; const t = document.getElementById('dv-ask-t'); t.textContent = 'Near your default place (ZIP 85936): '; const x = document.createElement('b'); x.textContent = 'The Greater St. Johns Area'; t.appendChild(x); document.getElementById('dv-ask-go').textContent = 'Explore it'; const r = b.getBoundingClientRect(); return {h: r.height, x: !!b.querySelector('.dv-ask-x[aria-label]'), sx: document.documentElement.scrollWidth <= innerWidth}; });
    ok(ask && ask.h <= (w < 768 ? 96 : 50) && ask.x && ask.sx, tag + 'Explore: the default place in one slim line (' + (ask && Math.round(ask.h)) + 'px), with × for "no thanks"');
    // the hub: no stray text; same-named areas once
    await go('/hub/?geo=show-low-geo');
    const body = await p.locator('body').innerText();
    ok(!/\(2026-11-19\)/.test(body), tag + 'the hub: no stray "// ⋯ (2026-11-19)"');
    // same-named areas are listed as the data has them (Jason, 2026-11-20: "the page should show what is properly assigned to
    // that area based on its funnel level"): the merge was taken out, and the data is the area data plan's
    const twins = await p.locator('.gd-stop', {hasText: 'Twin Town'}).count();
    ok(twins === 3, tag + 'the hub lists its areas as the data has them (3 "Twin Town" rows, nothing merged)');
    ok(!errs.length, tag + 'no script errors' + (errs.length ? ': ' + errs.join('; ') : ''));
    await ctx.close();
  }
  await b.close();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
