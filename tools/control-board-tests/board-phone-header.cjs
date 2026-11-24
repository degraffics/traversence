// board-phone (Jason, 2026-11-24, his 390px screenshot: "1 full width dropdown for alerts … instead of this visual chaos",
// then "Or a hamburger menu for pages like we've used before?"), rendered at 1280 and 390 with W1's two trips pressed and
// waiting for their run, warnings seen and unseen, and notices:
//   one alarm count everywhere (ControlAlarms::counts; 02-state-model P9): Alerts, the rail's (or the ☰'s) Board and the
//   alarm filter's Open all say trips + unseen warnings; pressed trips count; notices and seen warnings don't;
//   at 390 no rail: the ☰ on the left of Alerts on one line; at 1280 the rail, no ☰; Alerts' groups add up to its count;
//   each dropdown closes by ×, Esc and a click outside, one open at a time; the ☰ marks and names the page it's on;
//   the alarm filter shows only with its list (Diagnostics → Alarms); W1's page lists its pressed trips without
//   "Nothing here for this filter."; the pills are in the body font (design/table-standard.md, its Control Board note);
//   an answer placed beside a card in an alarm's row goes under the row, never a tall bar beside it (Jason's 7:46 PM screenshot);
//   nothing wider than the window. node board-phone-header.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('./board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const sql = (q) => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', '-e', q]).toString().trim();
sql('DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main'); execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 < ' + path.join(__dirname, '../release-b-tests/start-schema.sql')]);
execFileSync('php', [path.join(__dirname, 'task-trip-seed.php'), root + '/app', 'waiting'], { stdio: 'ignore' });
// five warnings (two of them seen) and four notices beside W1's two pressed trips
const row = (k, lv, ack) => `('${k}','job:${k}','late','${lv}','${k} (job) is late','due','It runs late.','Nothing to do.',UTC_TIMESTAMP(),UTC_TIMESTAMP(),1,${ack ? "UTC_TIMESTAMP()" : 'NULL'},${ack ? "'Jason'" : 'NULL'})`;
sql('USE lv_main; INSERT INTO control_alarms (akey,proc,kind,level,title,reading,means,todo,first_at,last_at,n_times,ack_at,ack_name) VALUES ' + [row('w1', 'warning'), row('w2', 'warning'), row('w3', 'warning'), row('w4', 'warning', 1), row('w5', 'warning', 1), row('n1', 'notice'), row('n2', 'notice'), row('n3', 'notice'), row('n4', 'notice')].join(','));
const want = +sql("USE lv_main; SELECT COUNT(*) FROM control_alarms WHERE cleared_at IS NULL AND (level = 'trip' OR (level = 'warning' AND ack_at IS NULL))");   // ControlAlarms::counts' rule
const port = 8960 + Math.floor(Math.random() * 20);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const open = p => p.evaluate(() => [...document.querySelectorAll('details.cb-pop[open]')].map(d => d.className));
(async () => { await new Promise(r => setTimeout(r, 900)); const b = await chromium.launch();
 try { t('the seed: 2 pressed trips + 3 unseen warnings = the one count, 5', want === 5, want);
  for (const w of [1280, 390]) { const L = w + 'px: ';
   const p = await b.newPage({ viewport: { width: w, height: 900 } }); const errs = []; p.on('pageerror', e => errs.push(String(e)));
   await p.goto(`http://127.0.0.1:${port}/admin/config/control`); await p.waitForTimeout(1200);
   const c = await p.evaluate(() => ({ alerts: +document.querySelector('.cb-alerts .cb-qgs > b').textContent, board: +document.querySelector('[data-cb-rail="deliver"] .cb-rcnt').textContent,
     menu: +document.querySelector('[data-cb-prail="deliver"] .cb-rcnt').textContent, filter: +document.querySelector('[data-cb-afcount]').textContent,
     says: document.querySelector('.cb-als').textContent,
     sum: [...document.querySelectorAll('.cb-alerts .cb-ag')].filter(g => !/^(Paused now|Hosts)$/.test(g.getAttribute('aria-label'))).reduce((a, g) => a + +g.querySelector('.cb-agh b').textContent, 0) }));
   t(L + 'one count: Alerts, the rail\'s Board, the ☰\'s Board and the filter\'s Open all say ' + want, [c.alerts, c.board, c.menu, c.filter].every(x => x === want), c);
   t(L + '…Alerts\' groups add up to it, and it says what they are ("2 trips · 3 warnings")', c.sum === want && c.says === '2 trips · 3 warnings', c);
   const phone = w < 768;
   t(L + (phone ? 'the ☰ on the left of Alerts on one line, no rail' : 'the rail on the left, no ☰'), await p.evaluate(ph => { const v = s => { const e = document.querySelector(s); return !!e && getComputedStyle(e).display !== 'none' && !!e.getClientRects().length; };
     if (!ph) return v('nav.cb-rail') && !v('.cb-pmenu'); const a = document.querySelector('.cb-pmenu').getBoundingClientRect(), b = document.querySelector('.cb-alerts').getBoundingClientRect(); return !v('nav.cb-rail') && a.right <= b.left && Math.abs(a.top - b.top) < 2 && Math.abs(a.height - b.height) < 2; }, phone));
   t(L + 'the alarm filter isn\'t shown on the Board page (no list under it)', await p.evaluate(() => getComputedStyle(document.querySelector('.cb-afw')).display === 'none'));
   const dds = phone ? ['.cb-pmenu', '.cb-alerts'] : ['.cb-alerts'];
   for (const d of dds) { const nm = d === '.cb-pmenu' ? '☰' : 'Alerts';
     await p.click(d + ' > summary'); await p.waitForTimeout(150); const pb = await p.evaluate(s => { const r = document.querySelector(s + ' > .cb-popb').getBoundingClientRect(); return [r.left, r.right]; }, d);
     t(L + nm + ' opens inside the window', (await open(p)).length === 1 && pb[0] >= 0 && pb[1] <= w + 1, pb);
     await p.click(d + ' .cb-px'); await p.waitForTimeout(150); t(L + nm + ': × closes it', !(await open(p)).length);
     await p.click(d + ' > summary'); await p.keyboard.press('Escape'); await p.waitForTimeout(150); t(L + nm + ': Esc closes it', !(await open(p)).length);
     await p.click(d + ' > summary'); await p.waitForTimeout(100); await p.mouse.click(w - 5, 880); await p.waitForTimeout(150); t(L + nm + ': a click outside closes it', !(await open(p)).length); }
   if (phone) { await p.click('.cb-pmenu > summary'); await p.click('.cb-alerts > summary'); await p.waitForTimeout(150); t(L + 'one open at a time (☰, then Alerts)', JSON.stringify(await open(p)) === JSON.stringify(['cb-pop cb-alerts r']), await open(p)); await p.keyboard.press('Escape'); }
   if (shots) { await p.click('.cb-alerts > summary'); await p.waitForTimeout(200); await p.screenshot({ path: `${shots}/alerts-${w}.png` }); await p.keyboard.press('Escape');
     if (phone) { await p.click('.cb-pmenu > summary'); await p.waitForTimeout(200); await p.screenshot({ path: `${shots}/pages-${w}.png` }); await p.keyboard.press('Escape'); } }
   await view(p, 'diagnostics'); await p.waitForTimeout(300); await p.click('.cb-diagnav [data-cb-tab="alarms"]'); await p.waitForTimeout(400);
   const d = await p.evaluate(() => ({ name: (document.querySelector('[data-cb-pname]') || {}).textContent, cur: (document.querySelector('[data-cb-prail][aria-current="page"]') || {}).getAttribute?.('data-cb-prail'), rail: (document.querySelector('[data-cb-rail][aria-current="page"]') || {}).getAttribute?.('data-cb-rail'), filter: getComputedStyle(document.querySelector('.cb-afw')).display !== 'none', menuOpen: !!document.querySelector('details.cb-pop[open]') }));
   t(L + 'Diagnostics → Alarms: the ☰ says Diagnostics, marked current in both, the menu closed, the filter shown with its list', d.name === 'Diagnostics' && d.cur === 'diagnostics' && d.rail === 'diagnostics' && d.filter && !d.menuOpen, d);
   const pill = await p.evaluate(() => { const x = document.querySelector('#cb .cb-pill'); return x ? getComputedStyle(x).fontFamily : ''; });
   t(L + 'the pills are in the body font, never a code font', pill !== '' && !/mono/i.test(pill) && pill === await p.evaluate(() => getComputedStyle(document.body).fontFamily), pill);
   t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
   // W1's own page: its pressed trips are listed, never "Nothing here for this filter."
   await p.goto(`http://127.0.0.1:${port}/admin/config/control/wf-W1`); await p.waitForTimeout(900);
   const w1 = await p.evaluate(() => ({ none: (() => { const n = document.querySelector('.cb-anone'); return !!n && !n.hidden; })(), shown: [...document.querySelectorAll('#cb-alarms li[data-astate="started"]')].filter(l => !l.hidden).length }));
   t(L + 'W1\'s page: its two pressed trips listed, no "Nothing here for this filter."', !w1.none && w1.shown === 2, w1);
   // the green bar (Jason's 7:46 PM screenshot): an answer placed beside a card inside an alarm's row (a flex row, its mark
   // then its card), as Check and start again's on a grouped trip, takes a line of its own under the row, never a column
   const bar = await p.evaluate(() => { const li = document.querySelector('#cb-alarms li[data-astate]'); const at = li.lastElementChild;
     TvAsk.say('Success: This trip\'s pause is lifted.', { anchor: at }); const n = document.querySelector('.tv-note'); const r = n.getBoundingClientRect(), q = li.getBoundingClientRect();
     const out = { w: Math.round(r.width), h: Math.round(r.height), row: Math.round(q.width), below: r.top >= q.bottom - 1 }; n.remove(); return out; });
   t(L + 'an answer beside a card in an alarm\'s row takes a line under the row, as wide as the row (no tall bar beside the card)', bar.below && bar.w >= bar.row - 2 && bar.h < 120, bar);
   t(L + 'no script errors', !errs.length, errs); await p.close(); }
 } catch (x) { t('the walk through finished', false, String(x.message).split('\n')[0]); }
 finally { srv.kill(); await b.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0); })();
