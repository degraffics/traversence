// The listing page, B5 Option 1 (handoffs/screens-proposal-2026-11-22.md; Jason 2026-11-22), rendered at 1280 and 390 on
// the harness (card-fixes/parks-seed.sql plus seed.sql here): 101 Lyman Lake (a PO box, a stock photo, a description),
// 107 Becker Lake (a street address with a pin, "Other Recreation" first with two real categories, no description).
// Run: node run.cjs [base] [tailwind browser build, served for cdn.tailwindcss.com]
const { chromium } = require('playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8790', TW = process.argv[3] || '';
let pass = 0, fail = 0; const ok = (c, m) => { c ? pass++ : fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m); };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) for (const id of [101, 107]) {
    const p = await b.newPage({ viewport: { width: w, height: w > 800 ? 900 : 844 } }); const errs = [];
    p.on('pageerror', e => errs.push(String(e)));
    await p.route(/^https?:\/\/(?!127\.0\.0\.1)/, r => TW && /cdn\.tailwindcss\.com/.test(r.request().url()) ? r.fulfill({ contentType: 'application/javascript', path: TW }) : r.abort());
    await p.goto(BASE + '/listing/view.php?id=' + id, { waitUntil: 'load' }); await p.waitForTimeout(1000);
    console.log('— ' + w + 'px, listing ' + id);
    const r = await p.evaluate(() => {
      const card = document.querySelector('.lv-card'), acts = [...card.querySelectorAll('.lv-acts .lv-a')];
      const lab = acts.map(a => { const l = a.querySelector('.lv-al'); return { n: l && l.textContent, aria: a.getAttribute('aria-label') }; });
      const lk = card.querySelector('[data-link]'), tk = card.querySelector('.lv-tk-b'); const L = lk && lk.closest('*').getBoundingClientRect(), T = tk && tk.getBoundingClientRect(), C = card.getBoundingClientRect();
      const name = card.querySelector('.lv-info > h2.lv-name'), addr = card.querySelector('.lv-info > h2.lv-name + div');
      const pills = [...card.querySelectorAll('.lv-kick .lv-q')].map(x => x.textContent.trim());
      const xh = document.querySelector('.xh').getBoundingClientRect();
      return { n: acts.length, lab, pills, corner: card.querySelectorAll('[data-link]').length, extra: card.querySelectorAll('[data-link-extra]').length, hidden: document.getElementById('lv-tk').hidden,
               tkTop: T && T.top - C.top < 40 && C.right - T.right < 90, name: name && name.textContent, addrAfter: !!addr,
               ring: !!document.querySelector('.lv-ring'), sure: (document.querySelector('.lv-sure') || {}).textContent || '', fix: !!card.querySelector('.lv-fix'),
               about: !!document.getElementById('about'), hero: Math.round(xh.height), vh: innerHeight, sw: document.documentElement.scrollWidth,
               cardH: Math.round(card.getBoundingClientRect().height), dir: !!card.querySelector('[data-out="directions"]') };
    });
    ok(r.corner === 1 && r.extra === 1 && r.tkTop, 'the Link top right, the ⋯ toolkit just left of it');
    ok(r.n >= 6 && r.hidden, 'the actions (' + r.n + ') wait in ⋯, closed');
    ok(r.lab.every(l => l.n && l.aria), 'each action named: ' + r.lab.map(l => l.n).join(', '));
    ok(r.name && r.addrAfter, 'the name heads the location block, over the address: ' + r.name);
    ok(!r.ring && /How sure we are: Unconfirmed/.test(r.sure), 'how sure we are in words: "' + r.sure.trim() + '", no ring');
    ok(r.hero <= 340 && (r.hero <= Math.round(r.vh * .32) + 1 || r.hero <= 200), 'the photo capped lower: ' + r.hero + 'px of a ' + r.vh + 'px window');
    ok(r.sw <= w, 'no sideways scroll (' + r.sw + ')');
    if (id === 101) {
      ok(!r.fix, 'a PO box: no "Fix the pin"'); ok(!r.dir, 'a PO box: no Directions (kept from 2026-11-19)');
      ok(r.about, 'a description: About shown');
      ok(r.cardH < (w > 800 ? 260 : 340), 'the card is only as tall as what it holds: ' + r.cardH + 'px');
    } else {
      ok(r.fix, 'a pin to fix: "Fix the pin" shown');
      ok(r.pills[0] !== 'Other Recreation' && r.pills[r.pills.length - 1] === 'Other Recreation', 'real categories before "Other": ' + r.pills.join(', '));
      ok(!r.about, 'nothing to say: no empty About card');
    }
    // press: ⋯ opens; ×, Esc, a click outside and an action each close it
    const isOpen = () => p.evaluate(() => !document.getElementById('lv-tk').hidden && document.querySelector('.lv-tk-b').getAttribute('aria-expanded') === 'true');
    await p.click('.lv-tk-b'); await p.waitForTimeout(150); ok(await isOpen(), '⋯ opens the toolkit');
    const inView = await p.evaluate(() => { const q = document.getElementById('lv-tk').getBoundingClientRect(); return q.left >= 0 && q.right <= innerWidth; }); ok(inView, 'the toolkit is wholly in the window');
    await p.click('#lv-tk [data-tk-close]'); await p.waitForTimeout(100); ok(!(await isOpen()), '× closes it');
    await p.click('.lv-tk-b'); await p.keyboard.press('Escape'); await p.waitForTimeout(100); ok(!(await isOpen()), 'Esc closes it');
    await p.click('.lv-tk-b'); await p.click('.lv-badge'); await p.waitForTimeout(100); ok(!(await isOpen()), 'a click outside closes it');
    // press: How we know opens, × closes, Esc doesn't leave it stuck; QR opens and closes; Leave a message goes to its form
    await p.click('.lv-sure'); await p.waitForTimeout(250);
    ok(await p.evaluate(() => !document.getElementById('lv-know').hidden && document.querySelector('.lv-sure').getAttribute('aria-expanded') === 'true'), 'How we know opens from the words');
    await p.click('#lv-know [data-know-close]'); await p.waitForTimeout(150);
    ok(await p.evaluate(() => document.getElementById('lv-know').hidden), 'and its × closes it');
    await p.click('.lv-sure'); await p.click('.lv-sure'); await p.waitForTimeout(150);
    ok(await p.evaluate(() => document.getElementById('lv-know').hidden), 'pressing the words again closes it');
    const qr = await p.$('.lv-acts [data-qr]');
    if (qr) { await p.click('.lv-tk-b'); await p.waitForTimeout(100); await qr.click(); await p.waitForTimeout(400); const open = await p.evaluate(() => [...document.querySelectorAll('.tvq')].some(e => e.getBoundingClientRect().width > 0)); await p.keyboard.press('Escape'); await p.waitForTimeout(200);
      const shut = await p.evaluate(() => ![...document.querySelectorAll('.tvq')].some(e => e.getBoundingClientRect().width > 0)); ok(open && shut && !(await isOpen()), 'QR code opens from ⋯ (closing the toolkit) and Esc closes it'); }
    const lm = await p.$('.lv-acts [data-lm-open]');
    if (lm) { await p.click('.lv-tk-b'); await p.waitForTimeout(100); await lm.click(); await p.waitForTimeout(400); ok(await p.evaluate(() => { const e = document.getElementById('lm'); const q = e && e.getBoundingClientRect(); return !!q && q.top < innerHeight && q.bottom > 0; }), 'Leave a message goes to its form'); }
    ok(!errs.length, 'no script errors' + (errs.length ? ': ' + errs[0] : ''));
    await p.close();
  }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
