// decisions/0175 release 2a, part 1, rendered: on the board, an alarm's main button opens its component in the side panel
// (one panel, the board stays), and ×, Esc and a click outside close it. At 1280 and 390.
// Run after the start schema and board-phone-seed.php: node one-place.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch(); const html = execFileSync('php', [page, app, 'page']);
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' });
   if (u.searchParams.get('peek') === '1') return r.fulfill({ body: '<!doctype html><title>The card | Traversence</title><h1>' + u.pathname + '</h1>', contentType: 'text/html' });
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(1200);
  await p.evaluate(() => { document.querySelectorAll('details').forEach(d => d.open = true); const tab = document.querySelector('[data-cb-tab="observe"]'); tab && tab.click(); }); await p.waitForTimeout(300);
  const mains = await p.locator('#cb-alarms .cb-main').count();
  t(w + ': each open alarm has its main button', mains >= 3, mains);
  const card = p.locator('#cb-alarms .cb-main[href="/admin/config/register#svc-azcc"]').first();
  t(w + ': the service\'s alarm opens its card', await card.count() === 1);
  await card.scrollIntoViewIfNeeded(); await card.click(); await p.waitForTimeout(600);
  const fr = p.frames().find(f => f.parentFrame() === p.mainFrame() && /peek=1/.test(f.url()));
  t(w + ': one side panel over the board, on the card\'s spot', await p.locator('.tv-pk').count() === 1 && !!fr && /\/admin\/config\/register\?.*peek=1#svc-azcc$/.test(fr.url()) && p.url().endsWith('/admin/config/control'), fr && fr.url());
  await p.keyboard.press('Escape'); await p.waitForTimeout(300); t(w + ': Esc closes it', await p.locator('.tv-pk').count() === 0);
  await card.click(); await p.waitForTimeout(400); await p.click('.tv-pk-h button'); await p.waitForTimeout(300); t(w + ': × closes it', await p.locator('.tv-pk').count() === 0);
  await card.click(); await p.waitForTimeout(400); await p.mouse.click(3, 3); await p.waitForTimeout(300); t(w + ': a click outside closes it', await p.locator('.tv-pk').count() === 0);
  const wide = await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth); t(w + ': no sideways scroll', wide);
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
