// The board on a phone (Jason's screenshots, 2026-11-19: the page scrolled sideways, the account bar ran off the right,
// the command header stopped short, a part's "Where it stands" ran off the edge): at 390, nothing is wider than the
// window, on the board with every tab, system, panel and alarm open, and on a part's own page with a long address.
// Run after the start schema, 2a's migration and board-phone-seed.php: node board-phone.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
const wide = () => { const W = document.documentElement.clientWidth, o = [];
  document.querySelectorAll('body *').forEach(el => { const r = el.getBoundingClientRect(); if (r.width > 0 && r.right > W + 1) { let a = el.parentElement, clip = false; while (a && a !== document.body) { if (/(hidden|auto|scroll|clip)/.test(getComputedStyle(a).overflowX)) { clip = true; break; } a = a.parentElement; } if (!clip) o.push((el.id ? '#' + el.id : el.tagName.toLowerCase() + '.' + String(el.className).split(' ')[0]) + ' ' + Math.round(r.right)); } });
  return { sw: document.documentElement.scrollWidth, W, over: o.slice(0, 6) }; };
(async () => { const b = await chromium.launch();
 for (const [label, proc] of [['the board', ''], ['a part\'s own page (a long address)', 'svc-azcc'], ['a workflow\'s own page', 'wf-W1']]) {
  const html = execFileSync('php', [page, app, 'page'], { env: Object.assign({}, process.env, { PROC: proc }) });
  const p = await b.newPage({ viewport: { width: 390, height: 900 } });
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname.startsWith('/admin/api/config/processes')) return r.fulfill({ body: execFileSync('php', [page, app, 'rows', u.searchParams.get('q') || '', u.searchParams.get('show') || '']), contentType: 'application/json' });
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' });
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(1500);
  const a = await p.evaluate(wide); t('390px, ' + label + ': nothing wider than the window', a.sw <= a.W && !a.over.length, a);
  for (const tab of ['observe', 'alarms', 'stages']) { await p.evaluate(x => { const e = document.querySelector('[data-cb-tab="' + x + '"]'); e && e.click(); }, tab); await p.waitForTimeout(300); }
  await p.evaluate(() => { const x = document.querySelector('#cb-systems [data-cb-all="1"]'); x && x.click(); }); await p.waitForTimeout(700);
  await p.evaluate(() => document.querySelectorAll('details').forEach(d => d.open = true)); await p.waitForTimeout(300);
  const c = await p.evaluate(wide); t('…with every tab, system, panel and alarm open', c.sw <= c.W && !c.over.length, c);
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
