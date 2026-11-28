// tools/pause-tests/review-390.cjs: Review (/admin/crawler-review.php) while auto-publish is paused, on a bench with 1,000 staged
// finds (review-seed.php), at 390 and 1280: no sideways scroll (0058 §11), the "Paused:" line and its Show more inside the
// page, cards 3px from the screen's edges at 390; Show 200 more loads the next 200. #335's block (2026-11-28).
// Run: node review-390.cjs <url of the bench's /admin/crawler-review.php> [shots dir]
const { chromium } = require('playwright');
const url = process.argv[2], shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 500) : '')); } };
(async () => {
  const b = await chromium.launch();
  for (const w of [390, 1280]) {
    const p = await (await b.newContext({ viewport: { width: w, height: 900 } })).newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    for (const more of [0, 1]) {
      await p.goto(url + (more ? '?more=1' : '')); await p.waitForTimeout(1200);
      const L = w + 'px' + (more ? ', after Show more' : '') + ': ';
      const m = await p.evaluate(() => {
        const de = document.documentElement, vw = de.clientWidth;
        const wide = [...document.querySelectorAll('body *')].map(e => [e, e.getBoundingClientRect()]).filter(([e, r]) => r.width > 0 && (r.right > vw + 1 || r.left < -1) && getComputedStyle(e).position !== 'fixed' && !e.closest('header, nav, .tvn-mw, .tvn-acct'))   // the bench is signed out: its account menu isn't the page's
          .slice(0, 6).map(([e, r]) => e.tagName.toLowerCase() + '.' + String(e.className).slice(0, 40) + ' ' + Math.round(r.left) + '..' + Math.round(r.right) + ' "' + (e.textContent || '').trim().slice(0, 40) + '"');
        const note = [...document.querySelectorAll('.tv-note, [data-tv-status]')].find(e => /Showing the newest/.test(e.textContent));
        const nr = note ? note.getBoundingClientRect() : null;
        const cards = [...document.querySelectorAll('.rv-card')].filter(c => c.getClientRects().length).slice(0, 40).map(c => c.getBoundingClientRect());
        return { vw, sw: de.scrollWidth, wide, note: nr ? [Math.round(nr.left), Math.round(nr.right)] : null, noteText: note ? note.textContent.replace(/\s+/g, ' ').trim().slice(0, 140) : '',
          cardL: cards.length ? Math.round(Math.min(...cards.map(c => c.left))) : null, cardR: cards.length ? Math.round(Math.max(...cards.map(c => c.right))) : null, n: document.querySelectorAll('.rv-card').length, junk: [...document.querySelectorAll('.rv-name')].filter(e => /^https:\/\/www\.northeastern/.test(e.textContent.trim())).length };
      });
      t(L + 'no sideways scroll', m.sw <= m.vw + 1, [m.sw, m.vw, m.wide]);
      t(L + 'the finds with no place to break are among those shown', m.junk > 0, m.junk);
      t(L + 'nothing reaches past the screen', m.wide.length === 0, m.wide);
      t(L + 'the Paused line is there, inside the page: ' + m.noteText.slice(0, 60), !!m.note && m.note[0] >= 0 && m.note[1] <= m.vw, m.note);
      t(L + (more ? '400' : '200') + ' of the 1,000 are shown, with the true total', m.n >= (more ? 400 : 200) && /of 1,000 waiting/.test(m.noteText) && new RegExp('newest ' + (more ? 400 : 200)).test(m.noteText), [m.n, m.noteText]);
      if (w === 390) t(L + 'cards 3px from each edge', m.cardL === 3 && m.cardR === m.vw - 3, [m.cardL, m.cardR, m.vw]);
      if (shots) await p.screenshot({ path: shots + '/review-' + w + (more ? '-more' : '') + '.png' });
    }
    t(w + 'px: no page error', errs.length === 0, errs);
  }
  await b.close(); console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
