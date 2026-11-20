// One panel at a time (decisions/0175 §6; Jason, 2026-11-20: W1's panel, then a listing opened from it, stacked a second
// header over the first): a link inside a panel replaces what the panel shows, with Back to where it was; never a panel in
// a panel. Esc, × and a click outside still close it. Run: node one-panel.cjs <website dir>
const { chromium } = require('playwright'); const fs = require('fs');
const web = process.argv[2];
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
const page = (title, body) => '<!doctype html><html><head><title>' + title + ' | Traversence</title><script src="/js/tv-ask.js"></script></head><body>' + body + '</body></html>';
const pages = {
  '/board': page('Board', '<a id="w1" href="/w1" data-peek-frame>W1 Listings</a>'),
  '/w1': page('W1 Listings', '<h1>W1</h1><a id="rec" href="/listing?id=16544" data-peek-frame>Listing #16544</a>'),
  '/listing': page('Listing', '<h1>Listing 16544</h1><a id="rev" href="/review?row=26906" data-peek-frame>Review item #26906</a>'),
  '/review': page('Review', '<h1>Review 26906</h1>'),
};
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await p.route('**/*', r => { const u = new URL(r.request().url());
    if (u.pathname === '/js/tv-ask.js') return r.fulfill({ body: fs.readFileSync(web + '/js/tv-ask.js'), contentType: 'text/javascript' });
    return r.fulfill({ body: pages[u.pathname] || page('?', ''), contentType: 'text/html' }); });
  await p.goto('http://t.local/board'); await p.waitForTimeout(300);
  await p.click('#w1'); await p.waitForTimeout(500);
  const fr = () => p.frames().find(f => /\/w1|\/listing|\/review/.test(f.url()) && f.parentFrame() === p.mainFrame());
  t('a panel opens over the board, titled W1 Listings', await p.locator('.tv-pk').count() === 1 && (await p.locator('.tv-pk-h span').first().textContent()) === 'W1 Listings');
  t('…with no Back yet', await p.locator('.tv-pk-back').isHidden());
  await fr().click('#rec'); await p.waitForTimeout(600);
  const heads = await p.locator('.tv-pk-h').count(); const nested = await fr().locator('.tv-pk').count().catch(() => 0);
  t('a link inside it replaces the panel: still one panel, no panel inside the panel', await p.locator('.tv-pk').count() === 1 && heads === 1 && nested === 0, { heads, nested });
  t('…titled by the link, showing the listing', (await p.locator('.tv-pk-h span').first().textContent()) === 'Listing #16544' && /\/listing\?id=16544/.test(fr().url()));
  t('…with Back, and "Open the full page" pointing at the listing', await p.locator('.tv-pk-back').isVisible() && /\/listing\?id=16544$/.test(await p.locator('.tv-pk-h a:not(.tv-pk-back)').getAttribute('href')));
  await fr().click('#rev'); await p.waitForTimeout(600);
  t('a third step: still one panel', await p.locator('.tv-pk').count() === 1 && (await p.locator('.tv-pk-h span').first().textContent()) === 'Review item #26906');
  await p.click('.tv-pk-back'); await p.waitForTimeout(500);
  t('Back returns to the listing', (await p.locator('.tv-pk-h span').first().textContent()) === 'Listing #16544' && /\/listing/.test(fr().url()));
  await p.click('.tv-pk-back'); await p.waitForTimeout(500);
  t('…and Back again to W1, where Back hides', (await p.locator('.tv-pk-h span').first().textContent()) === 'W1 Listings' && await p.locator('.tv-pk-back').isHidden());
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  t('Esc closes it', await p.locator('.tv-pk').count() === 0);
  await p.click('#w1'); await p.waitForTimeout(400); await p.click('.tv-pk-h button'); await p.waitForTimeout(300);
  t('× closes it', await p.locator('.tv-pk').count() === 0);
  await p.click('#w1'); await p.waitForTimeout(400); await fr().click('#rec'); await p.waitForTimeout(400); await p.mouse.click(5, 5); await p.waitForTimeout(300);
  t('a click outside closes it, Back and all', await p.locator('.tv-pk').count() === 0);
  await p.click('#w1'); await p.waitForTimeout(400);
  t('opened again: fresh, no Back left over', await p.locator('.tv-pk-back').isHidden() && (await p.locator('.tv-pk-h span').first().textContent()) === 'W1 Listings');
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0);
})();
