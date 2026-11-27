// Events E1 rendered (decisions/0134's minimum): the business portal's Events at 1280 and 390, as the business's owner on
// the design harness: New event, the rules said in place (a link refused), the ticket picker (only the business's own
// Event tickets items), save as a draft, Publish, the list's states and moves, Edit with History, Cancel and Put back on.
// Run on the harness (a signed-in owner of listing 7 via /__login?uid=2): node portal.cjs <shots dir>
const { chromium } = require('playwright'); const fs = require('fs');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.';
const H = process.env.HARNESS || 'http://127.0.0.1:8790', BIZ = process.env.BIZ || '7', UID = process.env.UID || '2';
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [1280, 390]) {
  const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800, timezoneId: 'America/Phoenix', userAgent: w < 800 ? 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36' : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36' });   // a headless user agent gets no session (the site's bot check)
  await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  await p.goto(H + '/__login?uid=' + UID);
  await p.goto(H + '/business/' + BIZ + '/events', { waitUntil: 'load' }); await p.waitForTimeout(600);
  t(w + ' the section opens with New event and the list', await p.locator('a:has-text("New event")').count() === 1 && /Your events/.test(await p.content()));
  await p.screenshot({ path: out + '/ev1-list-empty-' + w + '.png' });
  await p.click('a:has-text("New event")'); await p.waitForTimeout(500);
  const f = p.locator('#ev-f');
  t(w + ' the form: name, kind, who for, all day, starts, ends, description, showtimes, tickets, cover', await f.locator('[name=title],[name=kind],[name=age],[name=all_day],[name=starts_at],[name=ends_at],[name=about],[name=showtimes],[name=ticket_item_id],[name=gallery_item_id]').count() === 10);
  const kinds = await f.locator('[name=kind] option').allTextContents();
  t(w + ' the kinds are 0134 §3\'s', kinds.includes('Arts') && kinds.includes('Sports & rodeo') && !kinds.includes('Film'), kinds.join(','));
  const tix = await f.locator('[name=ticket_item_id] option').allTextContents();
  t(w + ' tickets: None (free) and only this business\'s ticket items', tix[0].startsWith('None') && tix.some(x => /barn dance/.test(x)) && !tix.some(x => /camp chair|Harvest/.test(x)), tix.join(' | '));
  t(w + ' the zone is named', /America\/Phoenix|America\/Denver/.test(await f.innerText()) || /Phoenix|Denver/.test(await f.innerText()));
  await p.screenshot({ path: out + '/ev1-form-' + w + '.png', fullPage: true });
  const day = new Date(Date.now() + 21 * 864e5).toISOString().slice(0, 10);
  await f.locator('[name=title]').fill('Community night at the movies ' + w);
  await f.locator('[name=kind]').selectOption('arts');
  await f.locator('[name=starts_at]').fill(day + 'T19:00'); await f.locator('[name=ends_at]').fill(day + 'T21:30');
  await f.locator('[name=about]').fill('Tickets also at https://elsewhere.example');
  await f.locator('button.go').click(); await p.waitForTimeout(900);
  t(w + ' a link is refused in place, with why', /No links/.test(await p.innerText('body')));
  await f.locator('[name=about]').fill('A night out for the whole town, with the film everyone is talking about.');
  await f.locator('[name=showtimes]').fill('Regular showings nightly at 4:00 and 7:15.');
  await f.locator('[name=ticket_item_id]').selectOption({ index: 1 });
  await f.locator('button.go').click(); await p.waitForURL(/\/events$/, { timeout: 8000 }).catch(() => {}); await p.waitForTimeout(700);
  const row = p.locator('.ev-row', { hasText: 'Community night at the movies ' + w });
  t(w + ' saved as a draft, in the list, with its time and zone', await row.count() === 1 && /Draft:/.test(await row.innerText()) && /7:00 PM to 9:30 PM M[SD]T/.test(await row.innerText()) && /tickets linked/.test(await row.innerText()), (await row.innerText().catch(() => '')).slice(0, 160));
  await row.locator('[data-ev-act=publish]').click(); await p.waitForTimeout(1800);
  const row2 = p.locator('.ev-row', { hasText: 'Community night at the movies ' + w });
  t(w + ' Publish: its line says Published, with Unpublish and Cancel the event', /Published:/.test(await row2.innerText()) && await row2.locator('[data-ev-act=unpublish]').count() === 1 && await row2.locator('[data-ev-act=cancel]').count() === 1);
  await p.screenshot({ path: out + '/ev1-list-' + w + '.png', fullPage: true });
  await row2.locator('[data-ev-act=cancel]').click(); await p.waitForTimeout(400);
  const yes = p.locator('.tv-ask-b.yes'); if (await yes.count()) await yes.click(); await p.waitForTimeout(1800);
  const row3 = p.locator('.ev-row', { hasText: 'Community night at the movies ' + w });
  t(w + ' Cancel asks first, then says Cancelled with Put back on', /Cancelled:/.test(await row3.innerText()) && await row3.locator('[data-ev-act=restore]').count() === 1);
  await row3.locator('[data-ev-act=restore]').click(); await p.waitForTimeout(1800);
  t(w + ' Put back on: published again', /Published:/.test(await p.locator('.ev-row', { hasText: 'Community night at the movies ' + w }).innerText()));
  // edit, then its History
  await p.locator('.ev-row', { hasText: 'Community night at the movies ' + w }).locator('a.ev-ic').click(); await p.waitForTimeout(500);
  await p.locator('#ev-f [name=title]').fill('Community night: the big show ' + w); await p.locator('#ev-f button.go').click(); await p.waitForURL(/\/events$/, { timeout: 8000 }).catch(() => {}); await p.waitForTimeout(700);
  const row4 = p.locator('.ev-row', { hasText: 'the big show ' + w });
  await row4.locator('summary.ev-ic').click(); await p.waitForTimeout(200);
  t(w + ' History keeps the old name, who and when', /changed title \(was "Community night at the movies/.test(await row4.innerText()) && /Ann Owner/.test(await row4.innerText()), (await row4.innerText()).slice(0, 200));
  await p.screenshot({ path: out + '/ev1-history-' + w + '.png', fullPage: true });
  t(w + ' round icon buttons carry their names', (await row4.locator('a.ev-ic').getAttribute('aria-label') || '').startsWith('Edit '));
  t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / '));
  await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); process.exit(bad ? 1 : 0); })();
