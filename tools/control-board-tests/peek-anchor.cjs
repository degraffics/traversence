// An alarm's "Open its card" opens the Service register beside the board ON that service's card (Jason, 2026-11-19: "i
// thought we fixed this. Open card shows the specific place to fix issues"): the side panel's own settings (embed, peek)
// go before the #, so the page inside lands on it, opened and in view. At 1280 and 390. Run: node peek-anchor.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', pg = path.join(__dirname, 'peek-anchor-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
const reg = execFileSync('php', [pg, app, 'register']).toString(), keys = [...reg.matchAll(/id="svc-([a-z0-9_-]+)"/g)].map(m => m[1]);
const key = keys[Math.floor(keys.length * 0.7)] || keys[keys.length - 1];   // one well down the list, so landing on it means scrolling to it
(async () => { const b = await chromium.launch(); t('the register has a card per service (' + keys.length + ')', keys.length > 5);
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 800 } }); const srcs = [];
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname === '/admin/config/register') { srcs.push(r.request().url()); return r.fulfill({ body: reg, contentType: 'text/html' }); }
   if (u.pathname.startsWith('/admin/api/') || u.pathname.startsWith('/api/')) return r.fulfill({ body: '{"ok":true}', contentType: 'application/json' });
   return r.fulfill({ body: execFileSync('php', [pg, app, 'board', key]), contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(300);
  await p.click('#go'); await p.waitForTimeout(1800);
  const fr = p.frames().find(f => f.url().includes('/admin/config/register')), src = await p.evaluate(() => (document.querySelector('iframe') || {}).getAttribute ? document.querySelector('iframe').getAttribute('src') : '');
  t(w + 'px: the panel opens the register with its settings before the # (' + src + ')', !!fr && /\?embed=1&peek=1#svc-/.test(src || '') && srcs.length > 0, src);
  if (!fr) { await p.close(); continue; }
  const s = await fr.evaluate(k => { const el = document.getElementById('svc-' + k), tb = el && el.querySelector('.ak-foldt'), r = el && el.getBoundingClientRect();
    return { open: tb && tb.getAttribute('aria-expanded'), top: r ? Math.round(r.top) : null, h: innerHeight, landed: !!(el && el.classList.contains('ak-landed')) }; }, key);
  t(w + 'px: …it opens on that service\'s card: opened, in view, marked', s.open === 'true' && s.top !== null && s.top >= -5 && s.top < s.h / 2 && s.landed, s);
  const others = await fr.evaluate(k => [...document.querySelectorAll('[id^="svc-"] .ak-foldt[aria-expanded=true]')].map(x => x.closest('[id^="svc-"]').id).filter(id => id !== 'svc-' + k), key);
  t(w + 'px: …and only that one', others.length === 0, others);
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
