// decisions/0176 release 2, rendered at 1280 and 390: a service's issue on its card is one thread with no ×; the card's
// "Is it working" line for it has no × and "Its issue" lands on the box; nothing scrolls sideways.
// Run after r2.php (its state): node r2.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2];
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch(); const html = execFileSync('php', [path.join(__dirname, 'r2-page.php'), root + '/app'], { stdio: ['ignore', 'pipe', 'ignore'] });
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true}', contentType: 'application/json' });
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/register#svc-geofabrik'); await p.waitForTimeout(1200);
  await p.evaluate(() => document.querySelectorAll('details').forEach(d => d.open = true)); await p.waitForTimeout(300);
  const box = p.locator('#issue-31');
  t(w + ': the issue is one thread on its card', await box.count() === 1 && await box.locator('.tv-th').count() === 1);
  t(w + ': …with no ×', await box.locator('.tv-note-x').count() === 0 && !(await box.innerText()).includes('×'));
  const line = p.locator('.os-part', { hasText: 'download.geofabrik.de' }).filter({ has: p.locator('a[href="#issue-31"]') });
  t(w + ': the card\'s "Is it working" line for it has no ×', await line.count() >= 1 && await line.first().locator('.tv-note-x').count() === 0, await line.count());
  await line.first().locator('a[href="#issue-31"]').click(); await p.waitForTimeout(500);
  t(w + ': "Its issue" lands on the box, in the same card', p.url().endsWith('#issue-31') && await p.locator('.tv-pk').count() === 0);
  t(w + ': no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
