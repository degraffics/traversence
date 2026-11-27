// Events P1 rendered (decisions/0134's note #1589): the host invites a business from its event in the portal, that business
// takes part with its offer in its own portal, both see it, the host removes and undoes, the business leaves and undoes;
// its offer a line in its owner's own words, its deal given in person (Jason's pilot scope, decisions/0134's note #1606: no codes);
// at 1280 and 390 on the design harness (HOST listing owned by HUID, GUEST listing owned by GUID; EV a published event).
const { chromium } = require('playwright'); const fs = require('fs'); const cp = require('child_process');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.';
const H = process.env.HARNESS || 'http://127.0.0.1:8790', HOST = process.env.HOST || '7', HUID = process.env.HUID || '2', GUEST = process.env.GUEST || '8', GUID = process.env.GUID || '3', GNAME = process.env.GNAME || 'Show Low Pizza', EV = process.env.EV || '5';
const sh = (q, db) => require('child_process').execFileSync('mysql', ['-utv', '-ptv', db || 'tvh', '-e', q]);
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [1280, 390]) {
  const ua = w < 800 ? 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36' : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
  const mk = async () => { const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800, userAgent: ua }); await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW })); return c; };
  const hc = await mk(), gc = await mk(); const hp = await hc.newPage(), gp = await gc.newPage(); const errs = []; hp.on('pageerror', e => errs.push(String(e))); gp.on('pageerror', e => errs.push(String(e)));
  await hp.goto(H + '/__login?uid=' + HUID); await gp.goto(H + '/__login?uid=' + GUID);
  const hostRow = () => hp.locator('.ev-row[data-ev="' + EV + '"]');
  const gRow = () => gp.locator('.ev-row', { has: gp.locator('a[href="/event/?id=' + EV + '"]') });
  await hp.goto(H + '/business/' + HOST + '/events', { waitUntil: 'load' }); await hp.waitForTimeout(500);
  t(w + ' the host sees Taking part with an invite box under its event', await hostRow().locator('.ev-parts').count() === 1 && await hostRow().locator('[data-ev-find]').count() === 1);
  await hostRow().locator('[data-ev-find]').fill(GNAME.slice(0, 8)); await hp.waitForTimeout(900);
  const pick = hostRow().locator('[data-ev-pick]', { hasText: GNAME });
  t(w + ' typing a name offers the business, with its town', await pick.count() === 1);
  await pick.click(); await hp.waitForTimeout(1800);
  t(w + ' invited: the business is on the list as Invited', /Invited/.test(await hostRow().locator('.ev-parts').innerText()) && new RegExp(GNAME).test(await hostRow().locator('.ev-parts').innerText()));
  await hp.screenshot({ path: out + '/p1-host-invited-' + w + '.png', fullPage: true });
  await gp.goto(H + '/business/' + GUEST + '/events', { waitUntil: 'load' }); await gp.waitForTimeout(500);
  t(w + ' the business sees the invitation, with Take part and Decline', await gRow().count() === 1 && /Invited:/.test(await gRow().innerText()) && await gRow().locator('[data-ev-part=accept]').count() === 1 && await gRow().locator('[data-ev-part=decline]').count() === 1);
  await gp.screenshot({ path: out + '/p1-guest-invited-' + w + '.png', fullPage: true });
  t(w + ' no discount code to choose (the pilot: the owner\'s line only)', await gRow().locator('[data-ev-code], select').count() === 0);
  await gRow().locator('[data-ev-offer]').fill('10% off a pizza with your ticket'); await gRow().locator('[data-ev-part=accept]').click(); await gp.waitForTimeout(1800);
  t(w + ' Take part: its line says so, honestly (the page comes with the next update)', /Taking part:/.test(await gRow().innerText()) && /next update/.test(await gRow().innerText()) && await gRow().locator('[data-ev-part=leave]').count() === 1);
  await gp.screenshot({ path: out + '/p1-guest-taking-part-' + w + '.png', fullPage: true });
  const geo = await gRow().evaluate(r => { const b = s => r.querySelector(s).getBoundingClientRect(); const o = b('[data-ev-offer]'), sv = b('[data-ev-part=offer]'), lv = b('[data-ev-part=leave]'); return { oh: o.height, ow: o.width, rw: r.getBoundingClientRect().width, same: Math.abs(sv.top - lv.top) < 2, below: sv.top >= o.bottom - 1 }; });
  t(w + ' the offer field is one line tall', geo.oh <= 48, JSON.stringify(geo));
  if (w < 800) t(w + ' on a phone the offer has the full width, Save offer and Leave together below it', geo.ow >= geo.rw - 40 && geo.same && geo.below, JSON.stringify(geo));
  await hp.reload(); await hp.waitForTimeout(500);
  t(w + ' one filled button in each view (brand.md §4): New event only', await hp.locator('.ak-b.go:visible').count() === 1 && await gp.locator('.ak-b.go:visible').count() <= 1);
  t(w + ' the offer has a visible label', await gp.locator('label.ev-lab', { hasText: 'Your offer' }).count() >= 1);
  t(w + ' the host sees it taking part, with its offer', /Taking part/.test(await hostRow().locator('.ev-parts').innerText()) && /10% off a pizza/.test(await hostRow().locator('.ev-parts').innerText()));
  t(w + ' Remove is sized like the row buttons, the row aligned with its heading', await (async () => { const r = await hostRow().locator('[data-ev-part=remove]').boundingBox(), h = await hostRow().locator('.ev-parts > b').boundingBox(), n = await hostRow().locator('.ev-pn a').first().boundingBox(); return r.height <= 30 && Math.abs(n.x - h.x) <= 2; })());
  await hostRow().locator('[data-ev-part=remove]').click(); await hp.waitForTimeout(300); if (await hp.locator('.tv-ask-b.yes').count()) await hp.locator('.tv-ask-b.yes').click(); await hp.waitForTimeout(1800);
  t(w + ' Remove asks first, then Undo is offered', /Removed by the host/.test(await hostRow().locator('.ev-parts').innerText()) && await hostRow().locator('[data-ev-part=unremove]').count() === 1);
  await hostRow().locator('[data-ev-part=unremove]').click(); await hp.waitForTimeout(1800);
  t(w + ' Undo: taking part again', /Taking part/.test(await hostRow().locator('.ev-parts').innerText()));
  await hp.screenshot({ path: out + '/p1-host-parts-' + w + '.png', fullPage: true });
  await gp.reload(); await gp.waitForTimeout(500);
  await gRow().locator('[data-ev-part=leave]').click(); await gp.waitForTimeout(300); if (await gp.locator('.tv-ask-b.yes').count()) await gp.locator('.tv-ask-b.yes').click(); await gp.waitForTimeout(1800);
  t(w + ' Leave asks first; Undo: take part again', /Left:/.test(await gRow().innerText()) && await gRow().locator('[data-ev-part=rejoin]').count() === 1);
  await gRow().locator('[data-ev-part=rejoin]').click(); await gp.waitForTimeout(1800);
  t(w + ' back in', /Taking part:/.test(await gRow().innerText()));
  for (const p of [hp, gp]) t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / '));
  // reset for the next width
  await hc.close(); await gc.close();
  sh('DELETE FROM event_participants');
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); process.exit(bad ? 1 : 0); })();
