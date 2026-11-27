// C.5b (0145 V06): a guide draws the stored alerts in force and the stored road reports with the page, so nothing jumps
// when the live answer (api/guide-live.php) comes; the live answer brings them up to date (an alert that ended goes); a
// cold cache behaves as before (they appear when the answer comes). On the design harness: SEED=<php> <root> seeds
// stored weather with an alert, and a road report (seed-wx.php in the build's README). At 390 and 1280.
const { chromium } = require('playwright'); const fs = require('fs'); const cp = require('child_process');
const TW = fs.readFileSync(process.env.TW_INJECT, 'utf8'); const H = process.env.HARNESS || 'http://127.0.0.1:8790', PG = '/hub/?cluster=show-low';
const sql = q => cp.execFileSync('mysql', ['-utv', '-ptv', 'tvh', '-e', q]);
const seed = () => cp.execFileSync('php', [process.env.SEED, process.env.ROOT], { cwd: process.env.ROOT + '/website' });
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [390, 1280]) {
  const ctx = async (live) => { const c = await b.newContext({ viewport: { width: w, height: 844 }, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36' });
    await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
    await c.route(/\/api\/guide-live\.php/, async r => { await new Promise(x => setTimeout(x, 1200)); if (live) r.fulfill({ contentType: 'application/json', body: JSON.stringify(live) }); else r.continue(); }); return c; };
  seed();
  let c = await ctx(null), p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  await p.goto(H + PG, { waitUntil: 'domcontentloaded' });
  t(w + ' the stored alert is there before the live answer', await p.locator('#wx-alerts:not([hidden]) .gd-alert', { hasText: 'Winter Storm Warning' }).count() === 1);
  t(w + ' the stored road report too', await p.locator('#roads:not([hidden]) li', { hasText: 'SR 260' }).count() === 1);
  t(w + ' Essentials isn\'t held back', await p.locator('#g-essentials[hidden]').count() === 0);
  await p.waitForTimeout(1800);
  t(w + ' the live answer leaves one of each, not two', await p.locator('#wx-alerts .gd-alert').count() === 1 && await p.locator('#roads li').count() === 1);
  await p.locator('#g-essentials').scrollIntoViewIfNeeded(); await p.screenshot({ path: (process.argv[2] || '.') + '/c5b-stored-' + w + '.png' });
  t(w + ' no script errors', !errs.length, errs.join(' / ')); await c.close();
  // the live answer says the alert has ended: it goes
  const j = JSON.parse(cp.execFileSync('curl', ['-s', H + '/api/guide-live.php?place=cluster:5']).toString()); j.alerts = [];
  c = await ctx(j); p = await c.newPage(); await p.goto(H + PG, { waitUntil: 'domcontentloaded' }); await p.waitForTimeout(1800);
  t(w + ' an alert the live answer no longer has goes', await p.locator('#wx-alerts').isHidden() && await p.locator('#wx-alerts .gd-alert').count() === 0); await c.close();
  // an alert whose time has passed isn't drawn from what's stored
  sql("UPDATE page_cache SET body = REPLACE(body, SUBSTRING_INDEX(SUBSTRING_INDEX(body, '\"ends\":\"', -1), '\"', 1), '2026-01-01T00:00:00+00:00') WHERE cache_key = 'wxs:34.254,-110.03'");
  c = await ctx({ ok: true, weather: null, alerts: [], roads: [], strip: [], roads_on: true }); p = await c.newPage(); await p.goto(H + PG, { waitUntil: 'domcontentloaded' });
  t(w + ' an ended alert isn\'t drawn', await p.locator('#wx-alerts .gd-alert').count() === 0); await c.close();
  // a cold cache: as before, they come with the live answer
  sql("DELETE FROM page_cache WHERE cache_key LIKE 'az511:%' OR cache_key LIKE 'wx%'");
  c = await ctx({ ok: true, weather: null, alerts: [{ event: 'Wind Advisory', headline: 'Gusts to 50 mph.', instruction: '' }], roads: [{ type: 'Closure', road: 'US 60', what: 'Closed.', where: '', closed: true }], strip: [], roads_on: true });
  p = await c.newPage(); await p.goto(H + PG, { waitUntil: 'domcontentloaded' });
  t(w + ' cold: nothing stored, nothing drawn, no service asked', await p.locator('#roads').isHidden() && await p.locator('#wx-alerts').isHidden());
  await p.waitForTimeout(1800);
  t(w + ' cold: they come with the live answer, as before', await p.locator('#roads li', { hasText: 'US 60' }).count() === 1 && await p.locator('#wx-alerts .gd-alert', { hasText: 'Wind Advisory' }).count() === 1); await c.close();
 }
 seed(); console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); process.exit(bad ? 1 : 0); })();
