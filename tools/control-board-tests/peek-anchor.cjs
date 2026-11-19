// An alarm's "Open its card" opens the Service register (since decisions/0173, with Outside services' cards in it) beside the board ON that service's card (Jason, 2026-11-19: "i
// thought we fixed this. Open card shows the specific place to fix issues"): the side panel's own settings (embed, peek)
// go before the #, so the page inside lands on it, opened and in view. At 1280 and 390. Run: node peek-anchor.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', pg = path.join(__dirname, 'peek-anchor-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
const reg = execFileSync('php', [pg, app, 'register']).toString(), keys = [...reg.matchAll(/class="os-card os-r" id="svc-([a-z0-9_-]+)"/g)].map(m => m[1]);   // a card each (decisions/0173: Outside services' cards, in the register)
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
  const s = await fr.evaluate(k => { const el = document.getElementById('svc-' + k), tb = el && el.querySelector('.os-rh'), r = el && el.getBoundingClientRect();
    return { open: tb && tb.getAttribute('aria-expanded'), top: r ? Math.round(r.top) : null, h: innerHeight, landed: !!(el && (el.classList.contains('ak-landed') || /143, 188, 168|8FBCA8/i.test(el.style.boxShadow))) }; }, key);
  t(w + 'px: …it opens on that service\'s card: opened, in view, marked', s.open === 'true' && s.top !== null && s.top >= -5 && s.top < s.h / 2 && s.landed, s);
  const others = await fr.evaluate(k => [...document.querySelectorAll('[id^="svc-"] .os-rh[aria-expanded=true]')].map(x => x.closest('[id^="svc-"]').id).filter(id => id !== 'svc-' + k), key);
  t(w + 'px: …and only that one', others.length === 0, others);
  await p.close(); }
 // the old address (decisions/0173 §1): /admin/config/services answers as router.php's TV_ADMIN_MOVED does, a 302 to the
 // register with its query; the browser keeps the #svc-<key>, so a link from an old email lands on the same card
 const http = require('http'); const srv = http.createServer((q, s) => { const u = new URL(q.url, 'http://x');
   if (u.pathname.startsWith('/js/')) { try { s.writeHead(200, { 'Content-Type': 'text/javascript' }); return s.end(fs.readFileSync(root + '/website' + u.pathname)); } catch (e) { s.writeHead(404); return s.end(); } }
   if (u.pathname === '/admin/config/services') { s.writeHead(302, { Location: '/admin/config/register' + u.search }); return s.end(); }
   if (u.pathname === '/admin/config/register') { s.writeHead(200, { 'Content-Type': 'text/html' }); return s.end(reg); }
   s.writeHead(200, { 'Content-Type': 'application/json' }); s.end('{"ok":true}'); }).listen(8773);
 const mv = fs.readFileSync(app + '/../website/admin/router.php', 'utf8');
 t('router.php sends config/services to config/register, query kept', /'config\/services' => 'config\/register'/.test(mv) && /PHP_URL_QUERY/.test(mv));
 for (const w of [1280, 390]) { const p = await b.newPage({ viewport: { width: w, height: 800 } });
  await p.goto('http://127.0.0.1:8773/admin/config/services?embed=1&peek=1#svc-' + key); await p.waitForTimeout(900);
  const s = await p.evaluate(k => { const el = document.getElementById('svc-' + k), r = el && el.getBoundingClientRect();
    return { url: location.pathname + location.search + location.hash, open: el && el.querySelector('.os-rh').getAttribute('aria-expanded'), top: r ? Math.round(r.top) : null, h: innerHeight, sx: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }; }, key);
  t(w + 'px: the old address lands on the same card in the register, opened and in view, no sideways scroll', s.url === '/admin/config/register?embed=1&peek=1#svc-' + key && s.open === 'true' && s.top !== null && s.top >= -5 && s.top < s.h / 2 && s.sx <= s.cw, s);
  await p.close(); }
 srv.close();
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
