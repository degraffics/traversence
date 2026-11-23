// decisions/0175 release 2b part A, rendered at 1280 and 390: one control board in the menu. The board's tables are its tabs
// (Alarms, Logbook, Ledger, Upkeep, Reports, Scheduled tasks); Automation is its Start-up stage; an old address lands on
// its tab with its own spot (?tab=scheduler#job-x); a page with its own forms loads in place when its tab opens.
// Run after the start schema and board-phone-seed.php: node board-tabs.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch(); const html = execFileSync('php', [page, app, 'page'], { stdio: ['ignore', 'pipe', 'ignore'] });
 for (const w of [1280, 390]) {
  const loaded = [];
  const open = async (url) => { const p = await b.newPage({ viewport: { width: w, height: 900 } });
   await p.route('**/*', r => { const u = new URL(r.request().url());
    if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
    if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' });
    if (u.searchParams.get('panel') === '1') { loaded.push(u.pathname + u.search); return r.fulfill({ body: '<!doctype html><title>x</title><body style="margin:0"><h1>' + u.pathname + '</h1><div style="height:900px"></div><p id="job-control_tick">its job</p></body>', contentType: 'text/html' }); }
    return r.fulfill({ body: html, contentType: 'text/html' }); });
   await p.goto(url); await p.waitForTimeout(1200); return p; };
  let p = await open('http://t.local/admin/config/control');
  const tabs = await p.$$eval('.cb-tabs2 [data-cb-tab]', a => a.map(x => x.firstChild.textContent));
  t(w + ': the board has its tables as tabs (Incidents among them since the opening view, under Diagnostics)', JSON.stringify(tabs) === JSON.stringify(['Incidents', 'Alarms', 'Logbook', 'Ledger', 'Upkeep', 'Reports', 'Scheduled tasks']), tabs);
  t(w + ': on Observation, no tab\'s part shows, and nothing loads in place yet', await p.locator('#cb-tab-alarms').isHidden() && loaded.length === 0, loaded);
  // since the opening view (2026-11-23) the tables are Diagnostics': the rail's Diagnostics first, as a person does
  await p.click('[data-cb-rail="diagnostics"]'); await p.waitForTimeout(300);
  await p.click('.cb-tabs2 [data-cb-tab="alarms"]'); await p.waitForTimeout(400);
  t(w + ': Alarms shows the Alarms table, the alarm queue hidden', await p.locator('#cb-tab-alarms').isVisible() && await p.locator('#cb-tab-alarms .ak-list[data-ak-table]').count() >= 1 && await p.locator('#cb-alarms').isHidden());
  await p.click('.cb-tabs2 [data-cb-tab="reports"]'); await p.waitForTimeout(300);
  t(w + ': Reports shows the reports', await p.locator('#reports').isVisible());
  await p.click('.cb-tabs2 [data-cb-tab="scheduler"]'); await p.waitForTimeout(800);
  t(w + ': Scheduled tasks loads its page in place, its links in the side panel (peek)', loaded.some(x => x.startsWith('/admin/config/scheduler?panel=1&peek=1')) && await p.locator('#cb-tab-scheduler iframe.cb-lazyf').count() === 1, loaded);
  await p.click('[data-cb-tab="startup"]'); await p.waitForTimeout(800);
  t(w + ': Start-up loads Automation (the master switch, Start the system, Pause everything) and the pre-flight\'s page', loaded.some(x => x.startsWith('/admin/config/automation?panel=1')) && loaded.some(x => x.startsWith('/admin/config/preflight?panel=1')), loaded);
  t(w + ': no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.close(); loaded.length = 0;
  p = await open('http://t.local/admin/config/control?tab=scheduler#job-control_tick');
  t(w + ': an old address lands on its tab, the link\'s spot carried into the page', await p.locator('#cb-tab-scheduler').isVisible() && loaded.some(x => x === '/admin/config/scheduler?panel=1&peek=1') && /#job-control_tick$/.test(await p.locator('#cb-tab-scheduler iframe').getAttribute('src')), loaded);
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
