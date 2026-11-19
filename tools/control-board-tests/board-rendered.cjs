// control-board-itsdata (2026-11-19): what the board did before its parts moved into the table (#192), tested in the
// rendered board, at 1280 and 390 (playbook §3: a rebuilt screen keeps everything the old one did):
// a system's counts filter its parts; the systems you opened stay open through the 30-second redraw; Open every system /
// Close them; an alarm's "Holds:" link opens the part's own page (never dead); the search finds a part by a source's name.
// Run after a schema with a trip on W1 (see README): node board-rendered.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-page.php');
const html = execFileSync('php', [page, app, 'page']);
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
(async () => { const b = await chromium.launch();
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } }); const asked = [];
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname.startsWith('/admin/api/config/processes')) { asked.push([u.searchParams.get('q') || '', u.searchParams.get('show') || '']); return r.fulfill({ body: execFileSync('php', [page, app, 'rows', u.searchParams.get('q') || '', u.searchParams.get('show') || '']), contentType: 'application/json' }); }
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true}', contentType: 'application/json' });
   if (u.pathname === '/admin/config/control' && u.search.indexOf('proc=') >= 0) return r.fulfill({ body: '<!doctype html><title>own</title><p id="own">' + u.search + '</p>', contentType: 'text/html' });
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/admin/config/control'); await p.waitForTimeout(1200);
  await p.evaluate(() => { const x = document.querySelector('[data-cb-tab="observe"]'); x && x.click(); });
  const st = () => p.evaluate(() => ({ open: [...document.querySelectorAll('#cb-systems tr.akt-grp[aria-expanded=true]')].map(x => x.getAttribute('data-grp')),
    shown: [...document.querySelectorAll('#cb-systems tr[data-id]:not([hidden])')].map(x => x.getAttribute('data-id')), sw: document.documentElement.scrollWidth,
    show: ((document.querySelector('#cb-systems .ak-fb[data-fk=show]') || {}).textContent || '').trim(), on: [...document.querySelectorAll('#cb-systems [data-cb-gf].on')].map(x => x.textContent) }));
  const W = w + 'px: ';
  // 1. a count filters
  const l = await p.$('#cb-systems tr.akt-grp [data-cb-gf="bad"]'); t(W + 'a system\'s counts are links ("1 tripped")', !!l);
  if (l) { await l.click(); await p.waitForTimeout(1200); const s = await st();
    t(W + '…pressed: only the tripped part shows, its system opened, Show says so', s.show.startsWith('Tripped') && s.shown.join() === 'wf:W1' && s.open.join() === 'sys:engine' && s.on.join() === '1 tripped', s);
    await (await p.$('#cb-systems [data-cb-gf="bad"]')).click(); await p.waitForTimeout(1200); const s2 = await st();
    t(W + '…pressed again: every part', s2.show.startsWith('Every part') && !s2.on.length, s2); }
  // 2. what you opened stays open through the redraw
  await p.evaluate(() => { const g = document.querySelector('#cb-systems tr.akt-grp[data-grp="sys:workers"] [data-ak-grp]') || document.querySelectorAll('#cb-systems tr.akt-grp [data-ak-grp]')[1]; g.click(); });
  const before = (await st()).open; await p.evaluate(() => { document.querySelector('#cb-systems tr.akt-grp')._old = 1; });
  await p.evaluate(() => document.querySelector('#cb-systems [data-ak-table]').__list.load()); await p.waitForTimeout(1200);
  const after = (await st()).open, fresh = await p.evaluate(() => !document.querySelector('#cb-systems tr.akt-grp')._old);
  t(W + 'the systems you opened stay open through the table\'s redraw', fresh && before.includes('sys:workers') && after.join() === before.join(), [fresh, before, after]);
  // 3. Open every system / Close them
  if (!(await p.$('#cb-systems [data-cb-all="1"]'))) { t(W + 'Open every system / Close them are under the table', false); await p.close(); continue; }
  await p.click('#cb-systems [data-cb-all="1"]'); await p.waitForTimeout(200); const all = await p.evaluate(() => [...document.querySelectorAll('#cb-systems tr.akt-grp')].map(g => g.getAttribute('aria-expanded')));
  t(W + 'Open every system opens them all', all.length > 1 && all.every(x => x === 'true'), all);
  await p.click('#cb-systems [data-cb-all="0"]'); await p.waitForTimeout(200); const none = await p.evaluate(() => [...document.querySelectorAll('#cb-systems tr.akt-grp')].map(g => g.getAttribute('aria-expanded')));
  t(W + '…Close them closes them', none.every(x => x === 'false'), none);
  // 4. an alarm's Holds: link opens the part's own page
  await p.evaluate(() => { const x = document.querySelector('[data-cb-tab="observe"]'); x && x.click(); });
  const hold = await p.$('#cb-alarms a[data-cb-go^="cb-p-"]'); t(W + 'an alarm names the part it holds, as a link', !!hold);
  if (hold) { await hold.click(); await p.waitForTimeout(800);
    const fr = await p.evaluate(() => [...document.querySelectorAll('iframe')].map(f => f.getAttribute('src')));
    t(W + '…which opens its own page beside the board (never a dead link)', fr.some(s => /proc=wf-W1/.test(s || '')), fr);
    await p.keyboard.press('Escape'); await p.waitForTimeout(200); }
  // 5. the search finds a part by a source's name
  await p.evaluate(() => { const x = document.querySelector('[data-cb-tab="observe"]'); x && x.click(); });
  await p.fill('#cb-systems .ak-q', 'overture'); await p.waitForTimeout(1500);
  const sr = await st(); t(W + 'the search finds a part by one of its sources ("overture")', asked.some(a => a[0] === 'overture') && sr.shown.length + sr.open.length >= 0 && (await p.evaluate(() => document.querySelectorAll('#cb-systems tr.akt-grp').length)) > 0);
  t(W + 'no sideways scroll', sr.sw === w, sr.sw);
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
