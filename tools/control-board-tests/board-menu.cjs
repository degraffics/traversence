// The board's parts table: the last row's ⋯ menu is never cut off (Jason, 2026-11-19, "design issue": the menu on a table's
// last rows; the admin kit's fix is T4, tested on a plain table in design-tests/table-menu.cjs). Here on the System
// Control Board itself, every system opened, the table's bottom at the window's bottom: the menu opens upward, wholly in
// the window, each item tappable. At 1280 and 390. Run after the start schema and 2a's migration: node board-menu.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-page.php');
const html = execFileSync('php', [page, app, 'page']);
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch();
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 800 } });
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname.startsWith('/admin/api/config/processes')) return r.fulfill({ body: execFileSync('php', [page, app, 'rows', u.searchParams.get('q') || '', u.searchParams.get('show') || '']), contentType: 'application/json' });
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true}', contentType: 'application/json' });
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(1500);
  await p.evaluate(() => { const x = document.querySelector('[data-cb-tab="observe"]'); x && x.click(); });
  await p.click('#cb-systems [data-cb-all="1"]'); await p.waitForTimeout(400);
  const btns = p.locator('#cb-systems [data-ak-rm]'); const n = await btns.count();
  t(w + 'px: the parts table has its ⋯ menus (' + n + ')', n > 3, n);
  if (!n) { await p.close(); continue; }
  // worst case: the table's last row at the window's bottom
  await p.evaluate(() => { const r = [...document.querySelectorAll('#cb-systems [data-ak-rm]')].pop().getBoundingClientRect(); window.scrollTo(0, r.bottom + scrollY - innerHeight + 8); });
  await p.waitForTimeout(150); await btns.nth(n - 1).click(); await p.waitForTimeout(250);
  const r = await p.evaluate(() => { const l = [...document.querySelectorAll('#cb-systems .akt-rml')].find(x => !x.hidden); if (!l) return null; const bx = l.getBoundingClientRect();
    const items = [...l.querySelectorAll('button,a')]; const tap = items.every(x => { const q = x.getBoundingClientRect(); const h = document.elementFromPoint(q.x + q.width / 2, q.y + q.height / 2); return h && l.contains(h); });
    return { items: items.length, top: Math.round(bx.top), bottom: Math.round(bx.bottom), vh: innerHeight, tap }; });
  t(w + 'px: the last row\'s ⋯ menu is wholly in the window, every item tappable', !!r && r.items > 0 && r.top >= 0 && r.bottom <= r.vh && r.tap, r);
  await p.keyboard.press('Escape'); await p.waitForTimeout(150);
  t(w + 'px: …and Esc closes it', await p.evaluate(() => ![...document.querySelectorAll('#cb-systems .akt-rml')].some(x => !x.hidden)));
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
