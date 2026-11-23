// Insights → Crawler at phone width (Jason's screenshot, 2026-11-23: the task table clipped on the left in the case's side
// panel): every table is cards below 768px with labelled lines, nothing scrolls sideways; at 1280 it's still a table; a
// failure reads in words ("503 Service Unavailable"), never the server's HTML; no "From ." and no empty "Its queue".
// Run after the start schema: node crawler-phone.cjs <root>   (crawler-phone-page.php seeds W1's bulk tasks, one with the 503 page)
const { chromium } = require('playwright'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2];
const html = execFileSync('php', [path.join(__dirname, 'crawler-phone-page.php'), root + '/app', path.join(__dirname, 'crawler-phone-why.txt')], { stdio: ['ignore', 'pipe', 'ignore'] }).toString();
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + ' — ' + JSON.stringify(got).slice(0, 300)); } };
t('a failure in words, never the server\'s page', html.includes('503 Service Unavailable') && !html.includes('&lt;!DOCTYPE'), '');
t('no "From ." for a task with no source named', !html.includes('From .'), '');
(async () => { const b = await chromium.launch();
 for (const w of [1280, 390, 360]) { const p = await b.newPage({ viewport: { width: w, height: 1000 } });
  await p.route('**/*', r => r.fulfill({ body: html, contentType: 'text/html' })); await p.goto('http://t.local/admin/insights/crawler');
  await p.evaluate(() => { document.querySelectorAll('.ak-foldb').forEach(b => b.hidden = false); });
  const r = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, side: [...document.querySelectorAll('.ak-aa')].filter(x => x.getClientRects().length && x.scrollWidth > x.clientWidth + 1).length,
    disp: getComputedStyle(document.querySelector('.ak-aa table')).display, n: document.querySelectorAll('.ak-aa').length, q: [...document.querySelectorAll('td[data-th="Its queue"]')].every(td => td.textContent.trim() !== '') }));
  t(w + 'px: no sideways scroll, in the page or in any table (' + r.n + ' tables)', r.sw <= w && r.side === 0, r);
  t(w + 'px: ' + (w >= 768 ? 'a table' : 'cards'), w >= 768 ? r.disp === 'table' : r.disp === 'block', r.disp);
  t(w + 'px: every queue says something', r.q, r.q);
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
