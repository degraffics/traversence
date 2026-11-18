const { chromium } = require('playwright'); const page = require('./releases-live-page.js'); const fs = require('fs');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  let v = 1;
  await p.route('**/*', r => { const u = r.request().url(); if (u.endsWith('/admin-releases.js')) return r.fulfill({ body: fs.readFileSync(process.argv[2]), contentType: 'text/javascript' }); return r.fulfill({ body: page(v), contentType: 'text/html' }); });
  await p.goto('http://test.local/admin/config/releases');
  const res = []; const ok = (n, c) => res.push((c ? '  ok  ' : 'FAIL  ') + n);
  await p.evaluate(() => window.tvRelLook());  await p.waitForTimeout(300);   // first look: as drawn
  ok('the first look changes nothing', await p.evaluate(() => document.querySelectorAll('details[data-rel-fold]').length === 2 && document.getElementById('rel-121').open));
  v = 2;
  await p.evaluate(() => { document.getElementById('rel-120').open = true; });
  await p.evaluate(() => window.tvRelLook()); await p.waitForTimeout(300);
  ok('#121 is drawn again as withdrawn, its failed notice gone', await p.evaluate(() => { const d = document.getElementById('rel-121'); return d.dataset.relState === 'withdrawn' && !/check failed/.test(d.textContent) && /Withdrawn by itself/.test(d.textContent); }));
  ok('…and folded as the page draws a withdrawn one', await p.evaluate(() => !document.getElementById('rel-121').open));
  ok('a new release appears at the top, in order', await p.evaluate(() => { const l = [...document.querySelectorAll('details[data-rel-fold]')].map(d => d.id); return l.join() === 'rel-123,rel-121,rel-120'; }));
  ok('a card that didn\'t change keeps what was opened', await p.evaluate(() => document.getElementById('rel-120').open));
  // a field being typed in holds its card
  v = 1;
  await p.evaluate(() => { const i = document.querySelector('[data-rel-note="121:ui"]'); i.value = 'typing'; });
  await p.evaluate(() => window.tvRelLook()); await p.waitForTimeout(300);
  ok('a card with words typed in waits for the next look', await p.evaluate(() => document.getElementById('rel-121').dataset.relState === 'withdrawn' && document.querySelector('[data-rel-note="121:ui"]').value === 'typing'));
  ok('no sideways scroll at 390px', await p.evaluate(() => document.documentElement.scrollWidth <= 390));
  console.log(res.join('\n')); await b.close();
})();
