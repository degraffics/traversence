// The phone menu (Jason, 2026-11-22: option B; the two defects of 2026-11-20 fixed), on the harness: at 390 and 1280 (option B, the defects fixed); pressed: open from ☰, ×, Esc, × and Esc (it fills the screen, so there's no outside)
const { chromium } = require('playwright');
let pass = 0, fail = 0; const ok = (c, m) => { c ? pass++ : fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m); };
(async () => { const b = await chromium.launch();
  for (const w of [390, 1280]) { console.log('— ' + w);
    const c = await b.newContext({ userAgent: 'Mozilla/5.0 Chrome/130', viewport: { width: w, height: 844 } }); const p = await c.newPage(); const errs = []; p.on('pageerror', e => errs.push(String(e)));
    await p.route(/cdn\.tailwindcss/, r => r.fulfill({ path: '/home/user/h/h/node_modules/@tailwindcss/browser/dist/index.global.js', contentType: 'text/javascript' })); await p.route(/^https:\/\/(?!127)/, r => r.abort());
    await p.goto('http://127.0.0.1:8790/privacy.php', { waitUntil: 'load' }); await p.waitForTimeout(800);
    if (w === 390) {
      await p.click('#brand-link'); await p.waitForTimeout(400);   // phones: the compass opens the site menu
      ok(await p.evaluate(() => !document.getElementById('tv-phone-menu').hidden), 'a tap on the compass opens the phone menu');
      const m = await p.evaluate(() => { const pm = document.getElementById('tv-phone-menu'), col = pm.querySelector('.tvpm-in'), si = pm.querySelector('a[href="/login.php"]'), h = pm.querySelector('.tvpm-help'), as = [...pm.querySelectorAll('.tvpm-a')], ai = as.find(a => /AI Assist/.test(a.textContent)), last = as[as.length - 1];
        const cs = getComputedStyle(as[0]), logo = pm.querySelector('.tvpm-logo').getBoundingClientRect(), foot = [...pm.querySelectorAll('.tvpm-sub a, .tvpm-sub button')].pop();
        return { colBorder: getComputedStyle(col).borderTopWidth, signW: Math.round(si.getBoundingClientRect().width), ai: !!ai, noAi: !/Ai Assist/.test(pm.textContent), gap: Math.round(h.getBoundingClientRect().top - last.getBoundingClientRect().bottom), fs: cs.fontSize, fw: cs.fontWeight, logo: Math.round(logo.width), tt: getComputedStyle(h).textTransform, fits: foot ? foot.getBoundingClientRect().bottom <= innerHeight : true, sw: document.documentElement.scrollWidth }; });
      ok(m.colBorder === '0px' && m.signW > 300, 'no outline round the menu; Sign in full width (' + m.signW + 'px)');
      ok(m.ai && m.noAi, '"AI Assist", no "Ai"');
      ok(m.gap > 0, 'Help Center sits below the last line (' + m.gap + 'px), no overlap');
      ok(m.fs === '21.6px' && m.fw === '700' && m.logo === 96 && m.tt === 'none', 'option B: 1.35rem/700 words, 96px logo, Help Center in sentence case');
      ok(m.fits, 'the whole menu fits one 844px screen');
      ok(m.sw <= w, 'no sideways scroll');
      await p.click('[data-tvpm-close]'); await p.waitForTimeout(300); ok(await p.evaluate(() => document.getElementById('tv-phone-menu').hidden), '× closes it');
      await p.click('#brand-link'); await p.waitForTimeout(300); await p.keyboard.press('Escape'); await p.waitForTimeout(300);
      ok(await p.evaluate(() => document.getElementById('tv-phone-menu').hidden), 'Esc closes it');
      await p.screenshot({ path: '/tmp/claude-0/shots/pm-built-390.png' });
    } else {
      const sw = await p.evaluate(() => document.documentElement.scrollWidth); ok(sw <= w, '1280: the page unchanged, no sideways scroll');
    }
    ok(errs.length === 0, 'no script errors' + (errs.length ? ': ' + errs.join(' | ') : '')); await c.close(); }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0); })();
