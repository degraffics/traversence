// The board at phone width with live content (Jason's screenshot, 2026-11-23: the board scrolled sideways): seeded with the
// live alarms snapshot, every view and stage tab at 390 and 360: nothing wider than the window. The gauges' source line
// names its file as a link, never a raw address that sets the page's width.
// Run after the start schema, 2a's ledger and board-live-seed.php: node board-live.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2]; const html = execFileSync('php', [path.join(__dirname, 'board-page.php'), root + '/app', 'page'], { stdio: ['ignore', 'pipe', 'ignore'] }).toString();
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + ' — ' + JSON.stringify(got).slice(0, 400)); } };
t('the gauges\' source line shows no raw address as text', !/Each gauge's setup is read from https?:\/\//.test(html.replace(/<[^>]+>/g, '')), (html.match(/cb-gsrc">.{0,160}/) || [''])[0]);
(async () => { const b = await chromium.launch();
 for (const w of [390, 360]) { const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.route('**/*', r => { const u = new URL(r.request().url()); if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
    if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' }); return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(1200);
  const tabs = await p.evaluate(() => [...document.querySelectorAll('[data-cb-tab]')].map(x => x.getAttribute('data-cb-tab')));
  for (const tab of tabs) {
   await p.evaluate(x => { const e = document.querySelector('[data-cb-tab="' + x + '"]'); e && e.click(); }, tab); await p.waitForTimeout(300);
   const r = await p.evaluate(() => { const W = document.documentElement.clientWidth, o = []; document.querySelectorAll('body *').forEach(el => { const b = el.getBoundingClientRect(); if (b.width && el.getClientRects().length && b.right > W + 1 && !el.closest('.akt-scroll, [data-ak-table] .akt-wrap, .cb-rtw')) o.push((el.id || String(el.className)).slice(0, 40)); }); return { sw: document.documentElement.scrollWidth, over: o.slice(0, 4) }; });
   t(w + 'px ' + tab + ': nothing wider than the window', r.sw <= w && !r.over.length, r); }
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
