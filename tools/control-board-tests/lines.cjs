// decisions/0175 release 2a, part 2, rendered at 1280 and 390: the board's "Paused now" line opens a fix in the side panel;
// a workflow's own page lists its steps with their switches; Switch off asks why in the site's own box and sends it to the
// board's taskoff with that reason; nothing scrolls sideways. Run after lines.php (its state): node lines.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch();
 const board = execFileSync('php', [page, app, 'page']), wf = execFileSync('php', [page, app, 'page'], { env: Object.assign({}, process.env, { PROC: 'wf-W1' }) });
 for (const w of [1280, 390]) {
  const posted = [];
  const open = async (html) => { const p = await b.newPage({ viewport: { width: w, height: 900 } });
   await p.route('**/*', r => { const u = new URL(r.request().url());
    if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
    if (u.pathname === '/admin/api/config/control') { posted.push(r.request().postDataJSON ? r.request().postDataJSON() : r.request().postData()); return r.fulfill({ body: '{"ok":true,"message":"Switched off."}', contentType: 'application/json' }); }
    if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' });
    if (u.searchParams.get('peek') === '1') return r.fulfill({ body: '<!doctype html><title>Its fix | Traversence</title><h1>' + u.pathname + '</h1>', contentType: 'text/html' });
    return r.fulfill({ body: html, contentType: 'text/html' }); });
   await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(1200); return p; };
  let p = await open(board);
  // since the opening view (2026-11-23) Paused now is its own page, from its chip (or ?tab=paused): open it first
  await p.evaluate(() => { const b = document.querySelector('[data-cb-tab="paused"]'); if (b) b.click(); }); await p.waitForTimeout(300);
  t(w + ': "Paused now" (its page, from its chip), listing each', await p.locator('.cb-strip .cb-pl li').count() >= 3);
  await p.locator('.cb-strip a[href="/admin/config/register#svc-commoncrawl"]').click(); await p.waitForTimeout(600);
  t(w + ': a paused item opens its fix in the side panel, the board kept', await p.locator('.tv-pk').count() === 1 && p.url().endsWith('/admin/config/control'));
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  t(w + ': the pre-flight\'s result is in Start-up, not on Observation', await p.evaluate(() => { const r = document.getElementById('cb-pfres'); const vis = el => el && el.offsetParent !== null; const before = vis(r); document.querySelector('[data-cb-tab="startup"]').click(); return [before, vis(r)]; }).then(v => !v[0] && v[1]));
  t(w + ': no sideways scroll on the board', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.close();
  p = await open(wf);
  t(w + ': W1\'s page lists its steps', await p.locator('#cb-steps li[id^="task-"]').count() >= 2);
  const off = p.locator('#task-geofabrik_bulk [data-cb="taskoff"]'); await off.scrollIntoViewIfNeeded(); await off.click(); await p.waitForTimeout(400);
  const box = p.locator('input:visible, textarea:visible').last();
  t(w + ': Switch off asks why in the site\'s own box', await box.count() === 1);
  await box.fill('Testing the switch'); await p.keyboard.press('Enter'); await p.waitForTimeout(800);
  const sent = posted.find(x => x && x.action === 'taskoff');
  t(w + ': …and sends taskoff for that task with the reason', !!sent && sent.id === 'geofabrik_bulk' && sent.value === 'Testing the switch', posted);
  t(w + ': no sideways scroll on W1\'s page', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
