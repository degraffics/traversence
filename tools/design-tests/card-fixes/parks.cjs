// card-fixes on the real Parks page (discovery/index.php?view=parks), seeded by parks-seed.sql, at 1280 and 390, across the
// landmark grid's pages: no stock photo twice in the list, no "0 mi", ZIP-middle places without a distance, "under ½ mi",
// each photo's credit on it with who and the licence in words and nothing cut, no sideways scroll, contrast on a pale sky.
// Run: node parks.cjs [base] [screenshot dir]
const { chromium } = require('playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8790', SS = process.argv[3] || '';
let pass = 0, fail = 0; const ok = (c, m) => { c ? pass++ : fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m); };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } }); const errs = [];
    p.on('pageerror', e => errs.push(String(e))); await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => r.abort());
    await p.goto(BASE + '/discovery/?view=parks&cluster=st-johns', { waitUntil: 'load' }); await p.waitForTimeout(1200);
    console.log('— ' + w + 'px');
    const read = () => p.evaluate(() => [...document.querySelectorAll('a.xc')].filter(a => a.offsetParent).map(a => {
      const art = a.querySelector('.xc-art'), cr = a.querySelector('.xc-cr'), r = a.getBoundingClientRect();
      const bg = getComputedStyle(art).backgroundImage; const img = (bg.match(/url\("([^"]+)"\)/) || [])[1] || '';
      const box = e => e && e.getBoundingClientRect(); const ab = box(art);
      const parts = cr ? [...cr.children].map(e => { const q = e.getBoundingClientRect(); return { t: e.textContent, cut: e.scrollWidth > e.clientWidth + 1 || e.scrollHeight > e.clientHeight + 1 || q.right > ab.right + .5 || q.left < ab.left - .5 }; }) : [];
      return { name: a.querySelector('.xc-art > b').textContent, img: img.startsWith('data:') ? '' : img.replace(location.origin, ''), stats: (a.querySelector('.xc-stats') || {}).textContent || '',
               credit: cr ? cr.textContent : '', tip: cr ? cr.getAttribute('title') : '', parts, grid: !!a.closest('#dv-lm-grid'), right: r.right, left: r.left };
    }));
    const seen = new Map(); const all = [];
    let cards = await read(); all.push(...cards); if (process.env.DBG) console.log(JSON.stringify(cards.map(c => [c.name, c.parts.map(x => x.t + (x.cut ? '(cut)' : ''))])));
    const pages = await p.$$eval('#dv-lm-grid + * .tvp-b[aria-label^="Page "]', bs => bs.length);
    // the landmark grid's other pages (tv-pager): each one's cards read too
    for (let i = 1; i < 6; i++) {
      const btn = await p.$('#dv-lm-grid + * .tvp-b[aria-label="Page ' + (i + 1) + '"]'); if (!btn) break;
      await btn.click(); await p.waitForTimeout(300); const more = await read(); all.push(...more.filter(c => c.grid));
    }
    ok(pages > 1, 'the landmark grid pages (' + pages + ' pages, each read)');
    const byName = Object.fromEntries(all.map(c => [c.name, c]));
    const stock = all.filter(c => c.credit.startsWith('Stock photo')); const imgs = stock.map(c => c.img);
    ok(stock.length >= 4 && new Set(imgs).size === imgs.length, 'no stock photo twice in the list (' + stock.length + ' stock photos, all different)');
    ok(!all.some(c => /(^|\D)0 mi/.test(c.stats)), 'no card says "0 mi"');
    ok(byName['St. Johns Town Park'] && byName['St. Johns Town Park'].stats === '' && byName['Show Low Lake Park'].stats === '', 'a place at its ZIP\'s middle shows no distance (St. Johns Town Park, Show Low Lake Park 41 mi away)');
    ok(byName['Veterans Memorial Park'] && /under ½ mi/.test(byName['Veterans Memorial Park'].stats), 'a real point 0.3 mi away says "under ½ mi"');
    ok(/^\d+ mi$/.test(byName['Lyman Lake State Park'].stats.trim()), 'a normal distance: Lyman Lake ' + byName['Lyman Lake State Park'].stats);
    ok(byName['Veterans Memorial Park'].img === '/uploads/h/p2.jpg' && byName['Veterans Memorial Park'].credit === '', 'a memorial with its own photo shows it');
    ok(byName['Navajo Code Talkers Memorial'].img === '' && byName['Canyon de Chelly National Monument'].img === '', 'a memorial or monument with no photo of its own gets the pattern, never stock');
    const fh = byName['Fool Hollow Lake Recreation Area'];
    ok(fh && fh.parts.map(x => x.t).join('|') === '© Ken Lund|CC BY-SA 2.0|i' && !fh.parts.some(x => x.cut), 'a CC BY-SA card on a pale sky: "© Ken Lund · CC BY-SA 2.0" written on the photo, nothing cut');
    const el = byName['El Morro National Monument'];
    ok(el && el.parts[1].t === 'CC BY 3.0' && !el.parts[1].cut, 'a very long author name wraps to two lines (then shortened), its licence never cut');
    ok(all.filter(c => c.parts.length && !/very long name/.test(c.parts[0].t)).every(c => !c.parts.some(x => x.cut)), 'every other credit reads whole, author and licence, on every card including the strip\'s narrow ones');
    ok(byName['Petrified Forest National Park'].parts.map(x => x.t).join('|') === 'National Park Service|Public domain|i', 'a public-domain photo: no ©, "Public domain"');
    ok(all.filter(c => c.img).every(c => c.credit !== '' || c.name === 'Veterans Memorial Park'), 'every Commons or stock photo carries its credit on it');
    ok(all.every(c => !c.credit || /Wikimedia Commons|Stock photo \(representative\)/.test(c.tip)), 'each credit\'s full words are in its title too');
    const sw = await p.evaluate(() => document.documentElement.scrollWidth); ok(sw <= w, 'no sideways scroll (' + sw + ' ≤ ' + w + ')');
    // contrast: the credit's text against its own backing laid over the palest pixel of the sky (#EBF2FA)
    const con = await p.evaluate(() => { const e = document.querySelector('.xc-cr'); const c = getComputedStyle(e); const n = s => s.match(/[\d.]+/g).map(Number);
      const [r, g, b2, a] = n(c.backgroundColor); const sky = [235, 242, 250]; const B = [r * a + sky[0] * (1 - a), g * a + sky[1] * (1 - a), b2 * a + sky[2] * (1 - a)];
      const L = v => { const f = x => { x /= 255; return x <= .03928 ? x / 12.92 : Math.pow((x + .055) / 1.055, 2.4); }; return .2126 * f(v[0]) + .7152 * f(v[1]) + .0722 * f(v[2]); };
      const F = n(c.color); return (Math.max(L(F), L(B)) + .05) / (Math.min(L(F), L(B)) + .05); });
    ok(con >= 4.5, 'the credit reads on a pale sky: ' + con.toFixed(2) + ':1');
    ok(!errs.length, 'no script errors' + (errs.length ? ': ' + errs[0] : ''));
    if (SS) {
      await p.goto(BASE + '/discovery/?view=parks&cluster=st-johns', { waitUntil: 'load' }); await p.waitForTimeout(800);
      const car = await p.$('.xh-car'); if (car) { await car.scrollIntoViewIfNeeded(); await car.screenshot({ path: SS + '/parks-strip-' + w + '.png' }); }
      const fool = await p.$('a.xc[href*="id=105"]'); if (fool) { await fool.scrollIntoViewIfNeeded(); await fool.screenshot({ path: SS + '/card-ccbysa-' + w + '.png' }); }
      const grid = await p.$('#dv-lm-grid'); if (grid) { await grid.scrollIntoViewIfNeeded(); await p.screenshot({ path: SS + '/landmarks-' + w + '.png' }); }
    }
    await p.close();
  }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
