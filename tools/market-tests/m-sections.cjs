// M-sections rendered (0135's note, Jason 2026-11-27; the CEO's rulings): the four sections' tabs and rail, the regional
// window's kinds and homes, the made-in label, Trade's coming panel, Browse all unchanged; at 390 and 1280.
// Run on the design harness with m-sections-seed.sql: node m-sections.cjs <shots dir>  (TW_INJECT: the Tailwind stand-in)
const { chromium } = require('playwright'); const fs = require('fs');
const TW = process.env.TW_INJECT ? fs.readFileSync(process.env.TW_INJECT, 'utf8') : ''; const out = process.argv[2] || '.';
const H = process.env.HARNESS || 'http://127.0.0.1:8790';
(async () => { const b = await chromium.launch(); let ok = 0, bad = 0; const t = (n, c, x) => { c ? ok++ : bad++; console.log((c ? 'PASS ' : 'FAIL ') + n + (x ? '  [' + x + ']' : '')); };
 for (const w of [390, 1280]) {
  const c = await b.newContext({ viewport: { width: w, height: 900 }, isMobile: w < 800, hasTouch: w < 800 });
  await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
  const go = async (u) => { await p.goto(H + u, { waitUntil: 'load' }); await p.waitForTimeout(700); };
  const titles = () => p.$$eval('#mk-grid > *', as => as.map(a => a.innerText.replace(/\s+/g, ' ')));
  const head = () => p.locator('.mk-h2').first().innerText();
  const cur = () => p.locator('.mk-sec [aria-current="page"]').innerText();
  // Browse all: the landing as before
  await go('/market/');
  const tabs = await p.$$eval('.mk-sec a', as => as.map(a => a.textContent.replace(/\s+/g, ' ').trim()));
  t(w + ' five tabs: Browse all and the four sections', JSON.stringify(tabs) === JSON.stringify(['Browse all', 'Shops', 'Experiences & Regional Goods', 'Trade Coming', 'Aftermarket']), tabs.join(' | '));
  t(w + ' Browse all is the landing, current', (await cur()).trim() === 'Browse all');
  const all = await titles();
  t(w + ' Browse all shows people and businesses together (9)', all.length === 9 && /Used saddle/.test(all.join(' ')), all.length);
  t(w + ' the category tiles stay on the plain landing', await p.locator('.mk-tiles').count() === 1);
  await p.screenshot({ path: out + '/msec-browse-' + w + '.png' });
  // Shops
  await go('/market/?section=shops'); const sh = await titles();
  t(w + ' Shops: businesses only (8), no people\'s posts', sh.length === 8 && !/Used saddle/.test(sh.join(' ')), sh.length);
  t(w + ' Shops: the heading names it', /^Shops: newest/.test(await head()));
  await p.screenshot({ path: out + '/msec-shops-' + w + '.png' });
  // Experiences & Regional Goods
  await go('/market/?section=regional'); const rg = (await titles()).join(' | ');
  t(w + ' Regional: tickets, stays, harvest, made to order (and a legacy class ticket)', /Harvest box: apples/.test(rg) && /barn dance/.test(rg) && /Cabin/.test(rg) && /branded sign/.test(rg) && /legacy kind/.test(rg), rg.slice(0, 160));
  t(w + ' Regional: no physical goods (ruling 5)', !/camp chair/.test(rg));
  t(w + ' Regional: no services (ruling 1)', !/Fence repair/.test(rg));
  t(w + ' Regional: none from a business outside our areas (ruling 2)', !/from nowhere/.test(rg));
  t(w + ' Regional: its line says what qualifies, with the FAQ', /Tickets, stays, harvest and made to order/.test(await p.locator('.mk-sd').innerText()) && await p.locator('.mk-sd a[href="/faq.php#what-are-the-marketplace-s-four-sections"]').count() === 1);
  t(w + ' Regional: the chosen tab is in view', await p.evaluate(() => { const n = document.querySelector('.mk-sec'), c = n.querySelector('[aria-current="page"]'); return c.offsetLeft - n.scrollLeft >= 0 && c.offsetLeft - n.scrollLeft + c.offsetWidth <= n.clientWidth + 1; }));
  t(w + ' Regional: the From chip is not repeated', await p.locator('.mk-chip').count() === 0);
  await p.screenshot({ path: out + '/msec-regional-' + w + '.png' });
  await go('/market/?section=regional&kind=physical');
  t(w + ' Regional with a kind it doesn\'t hold: says what shows here', /Nothing here yet\. Event tickets/.test(await p.locator('.mk-empty').innerText()));
  // Aftermarket
  await go('/market/?section=aftermarket'); const af = await titles();
  t(w + ' Aftermarket: people\'s own posts only', af.length === 1 && /Used saddle/.test(af[0]));
  await p.screenshot({ path: out + '/msec-aftermarket-' + w + '.png' });
  // Trade
  await go('/market/?section=trade');
  t(w + ' Trade: the coming panel, no grid, no search', await p.locator('#mk-grid').count() === 0 && await p.locator('#mk-find').count() === 0 && /Trade is coming/.test(await p.locator('.mk-h2').innerText()));
  t(w + ' Trade: a way forward (Shops, its FAQ)', await p.locator('a[href="/market/?section=shops"]').count() >= 1 && await p.locator('a[href="/faq.php#what-is-trade-on-the-marketplace"]').count() === 1);
  await p.screenshot({ path: out + '/msec-trade-' + w + '.png' });
  // the old From filter lights its section
  await go('/market/?from=business');
  t(w + ' From businesses (Filters) lights Shops', (await cur()).trim() === 'Shops');
  // the label
  await go('/market/item.php?id=1');
  t(w + ' a regional item says Made in <its area>', (await p.locator('.mv-made').innerText()).trim() === 'Made in Round Valley');
  await p.screenshot({ path: out + '/msec-item-' + w + '.png' });
  for (const [id, why] of [[5, 'physical goods'], [6, 'a service'], [7, 'no home area']]) { await go('/market/item.php?id=' + id); t(w + ' no label on ' + why, await p.locator('.mv-made').count() === 0); }
  // the rail
  await go('/market/?section=regional');
  const rail = await p.$$eval('a[href^="/market/?section="]', as => [...new Set(as.map(a => a.getAttribute('href')))]);
  t(w + ' the four sections are reachable from the page and its menu', rail.length === 4, rail.join(' '));
  t(w + ' no sideways scroll', (await p.evaluate(() => document.documentElement.scrollWidth)) <= w);
  t(w + ' no script errors', !errs.length, errs.slice(0, 2).join(' / '));
  await c.close();
 }
 console.log(ok + ' passed, ' + bad + ' failed'); await b.close(); process.exit(bad ? 1 : 0); })();
