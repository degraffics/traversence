// The board's views rendered at 1280 and 390 (the control board's build, step 4; CLAUDE.md "End to end, verified"): "Is it
// delivering?" first (03 §4, §27), Development with Site health (gauge-lifecycle.md; check-register.md), Incidents and
// IR-0001's page (03 §7), the folded report and the page as a file (D16), Copy on every section (D15), D5's acknowledged trip.
// Each view: no sideways scroll; every gauge one Arc, its band within 1% of its score, 50 at the top (the model:
// step3/mockups/src/ring-check.cjs, with the board's own classes); a card opens its snapshot by keyboard; the three paths
// with Back; a panel closes by ×, Esc and a click outside; a gauge's detail loads as its panel opens.
// Run after the start schema, 2a's ledger and board-views-seed.php: node board-views.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', page = path.join(__dirname, 'board-views-page.php');
const php = (...a) => execFileSync('php', [page, app, ...a], { stdio: ['ignore', 'pipe', 'ignore'] });
const html = php('page');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
// ring-check.cjs's checks, on the board's classes (.cb-gc, .cb-gbig; the stage pill .sr-pill)
const rings = () => { const bad = []; let n = 0, top50 = 0;
  document.querySelectorAll('.sr').forEach((r, i) => { if (!r.getClientRects().length) return; n++;
    const id = (r.getAttribute('aria-label') || '#' + i).slice(0, 40), s = parseFloat(r.dataset.score);
    const vis = [...r.querySelectorAll('svg')]; if (vis.length !== 1 || !vis[0].classList.contains('sr-arc')) bad.push(id + ': not one arc');
    const a = r.querySelector('.sr-a'), d = parseFloat((a.getAttribute('stroke-dasharray') || '').split(' ')[0]);
    if (a.getAttribute('pathLength') !== '100' || !(Math.abs(d - s) <= 1)) bad.push(id + ': band ' + d + ' vs ' + s);
    const t = (r.querySelector('.sr-n') || {}).textContent || ''; const v = t.replace('%', '').trim(); if (!v || (v !== '—' && Math.abs(parseFloat(v) - s) > 0.6)) bad.push(id + ': number ' + t);
    if (r.getAttribute('role') !== 'img' || !/percent|no reading/.test(r.getAttribute('aria-label') || '')) bad.push(id + ': aria');
    const box = r.closest('.cb-gc, .cb-gbig'); if (!box || !box.querySelector('.sr-pill')) bad.push(id + ': no stage pill');
    const L = a.getTotalLength(), p = a.getPointAtLength(L * s / 100), ang = Math.PI * (1 - s / 100);
    if (Math.hypot(p.x - (36 + 30 * Math.cos(ang)), p.y - (38 - 30 * Math.sin(ang))) > 0.6) bad.push(id + ': arc end off');
    const q = a.getPointAtLength(L / 2); if (Math.abs(q.x - 36) < 0.3 && Math.abs(q.y - 8) < 0.3) top50++; else bad.push(id + ': 50 not at the top');
  });
  return { n, bad: bad.slice(0, 6), nbad: bad.length, needles: document.querySelectorAll('[class*=needle], .sr line').length, top50 }; };
const wide = () => { const W = document.documentElement.clientWidth, o = []; document.querySelectorAll('body *').forEach(el => { const r = el.getBoundingClientRect(); if (r.width && el.getClientRects().length && r.right > W + 1 && !el.closest('.cb-rtw, .akt-scroll, [data-ak-table] .akt-wrap')) o.push((el.id || el.className || el.tagName).toString().slice(0, 40)); });
    return { sw: document.documentElement.scrollWidth, W, over: o.slice(0, 4) }; };
// every section and card in view carries Copy (D15)
const copies = () => { const miss = []; document.querySelectorAll('.ak-card, .cb-gc, [data-copy]').forEach(el => { if (!el.getClientRects().length || el.closest('.cb-sp[hidden]')) return;
  const c = el.closest('[data-copy]'); if (!c || !c.querySelector('.tv-copy-b')) miss.push((el.id || el.className).toString().slice(0, 40)); }); return miss; };
(async () => { const b = await chromium.launch();
 for (const w of [1280, 390]) {
  const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); await ctx.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: 'http://localhost' });
  const p = await ctx.newPage(); const posts = []; const W = w + 'px: '; const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname === '/admin/api/config/control' && r.request().method() === 'POST') { let b = {}; try { b = JSON.parse(r.request().postData() || '{}'); } catch (e) {}
     if (b.action === 'ircause') { const j = JSON.parse(php('ircause', String(b.id || ''), String(b.value || '')).toString()); return r.fulfill({ status: j.ok ? 200 : (j.code || 422), body: JSON.stringify(j), contentType: 'application/json' }); } }
   if (u.pathname === '/admin/api/pulse') { posts.push(JSON.parse(r.request().postData() || '{}')); return r.fulfill({ body: '{"ok":true,"message":"Recorded."}', contentType: 'application/json' }); }
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' });
   if (u.pathname === '/admin/config/control' && u.searchParams.get('gauge')) return r.fulfill({ body: php('gauge', u.searchParams.get('gauge')), contentType: 'text/html' });
   if (u.pathname === '/admin/config/control' && u.searchParams.get('ir')) return r.fulfill({ body: php('ir', u.searchParams.get('ir')), contentType: 'text/html' });
   if (u.pathname === '/admin/config/control' && u.searchParams.has('report')) return r.fulfill({ body: php('report', u.searchParams.get('report'), u.searchParams.get('range') || 'week'), contentType: 'text/html' });
   if (u.pathname === '/report-file') return r.fulfill({ body: php('file', '', 'week'), contentType: 'text/html' });
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://localhost/admin/config/control'); await p.waitForTimeout(1200);
  const shown = () => p.evaluate(() => ['cb-deliver', 'cb-dev', 'cb-incidents', 'cb-alarms'].filter(id => { const e = document.getElementById(id); return e && e.getClientRects().length; }));
  // 1. "Is it delivering?" first
  // since the opening view (2026-11-23, step3/board/index.html): Board lands on "Is it delivering?" beside Development
  t(W + '"Is it delivering?" is the view on load, Development beside it (Board)', (await shown()).join() === 'cb-deliver,cb-dev', await shown());
  const q3 = await p.evaluate(() => document.getElementById('cb-q3').innerText);
  t(W + 'the three questions; the light honest after Acknowledge ("1 trip (seen by Jason)")', /Is it healthy/i.test(q3) && /held and why/i.test(q3) && /needs me/i.test(q3) && q3.includes('1 trip (seen by Jason)'), q3.slice(0, 200));
  const rg = await p.evaluate(rings); t(W + 'every gauge one Arc, its band within 1% of its score, the number, its label and stage pill, no needle (' + rg.n + ')', rg.n >= 20 && rg.nbad === 0 && rg.needles === 0 && rg.top50 === rg.n, rg);
  let ws = await p.evaluate(wide); t(W + 'no sideways scroll', ws.sw <= w && !ws.over.length, ws);
  let cm = await p.evaluate(copies); t(W + 'Copy on every section and card', cm.length === 0, cm);
  // 2. a card by keyboard; the snapshot; its detail loads; closes three ways
  const first = await p.evaluate(() => document.querySelector('#cb-deliver .cb-gopen').getAttribute('data-cb-sp'));
  await p.focus('#cb-deliver .cb-gopen'); await p.keyboard.press('Enter'); await p.waitForTimeout(700);
  const open = () => p.evaluate(() => [...document.querySelectorAll('.cb-sp')].filter(x => !x.hidden).map(x => x.id));
  t(W + 'the whole card is a keyboard target: Enter opens its snapshot', (await open()).join() === first, await open());
  const det = await p.evaluate(id => { const s = document.getElementById(id).querySelector('[data-cb-gd]'); return s ? s.innerText : ''; }, first);
  t(W + '…its detail loaded as it opened (setup words, what it counts)', /Its setup, in words|What it counts|No detail/.test(det), det.slice(0, 120));
  ws = await p.evaluate(wide); t(W + '…the panel open, no sideways scroll', ws.sw <= w, ws);
  const rg2 = await p.evaluate(rings); t(W + '…its big Arc too', rg2.nbad === 0, rg2);
  await p.keyboard.press('Escape'); await p.waitForTimeout(200); t(W + '…Esc closes it', (await open()).length === 0);
  await p.click('#cb-deliver .cb-gopen'); await p.waitForTimeout(300); await p.click('#' + first + ' [data-cb-spx]'); await p.waitForTimeout(200); t(W + '…× closes it', (await open()).length === 0);
  await p.click('#cb-deliver .cb-gopen'); await p.waitForTimeout(300); await p.mouse.click(2, 450); await p.waitForTimeout(200); t(W + '…a click outside closes it', (await open()).length === 0);
  // 3. the three paths, with Back
  const drills = await p.evaluate(() => [...document.querySelectorAll('#cb-deliver .cb-gc')[0].querySelectorAll('.cb-drill')].map(x => x.textContent));
  t(W + 'three drill paths on a card: Workflows ▸, Core Services ▸, Registered Services ▸', drills.join('|') === 'Workflows ▸|Core Services ▸|Registered Services ▸', drills);
  await p.click('#cb-deliver .cb-gc .cb-drill'); await p.waitForTimeout(300);
  const wfp = (await open())[0] || ''; t(W + '…Workflows ▸ opens its panel', /-wf$/.test(wfp), wfp);
  await p.click('#' + wfp + ' button:has-text("Back")'); await p.waitForTimeout(300); t(W + '…Back goes to its snapshot', (await open()).join() === first, await open());
  await p.click('#' + first + ' .cb-drill:has-text("Registered")'); await p.waitForTimeout(300); t(W + '…Registered Services ▸ from the snapshot', /-rs$/.test((await open())[0] || ''));
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
  // 4. Copy writes the section's visible text, never the Copy word or a hidden row
  // since the opening view (2026-11-23) healthy, held and needs me are on the Paused now page, from its chip
  await p.evaluate(() => document.querySelector('[data-cb-tab="paused"]').click()); await p.waitForTimeout(300);
  await p.click('#cb-q3 .tv-copy-b'); await p.waitForTimeout(400);
  const clip = await p.evaluate(() => navigator.clipboard.readText().catch(e => 'ERR ' + e.message));
  t(W + 'Copy puts the section\'s text on the clipboard, with the site\'s notice', /is it healthy/i.test(clip) && clip.includes('1 trip (seen by Jason)') && !/\bCopy\b/.test(clip) && await p.evaluate(() => !!document.querySelector('.tv-note')), clip.slice(0, 160));
  await p.click('[data-cb-rail="deliver"]'); await p.waitForTimeout(300);   // back to Board, where the cards are
  const card = await p.evaluate(() => { const c = document.querySelector('#cb-deliver .cb-gc'); return [TvCopy.text(c), c.querySelector('.cb-gh').textContent]; });
  t(W + '…a card\'s text: its name, its number, its stage; no arc ends ("0", "100") or buttons\' "Copy"', card[0].startsWith(card[1]) && /%/.test(card[0]) && !/\b100\b/.test(card[0].split('\n').slice(0, 3).join(' ')) && !/\bCopy\b/.test(card[0]), card[0].slice(0, 160));
  // 5. D5: the acknowledged trip stays in the Open list
  await p.click('[data-cb-rail="diagnostics"]'); await p.waitForTimeout(200); await p.click('.cb-diagnav [data-cb-tab="observe"]'); await p.waitForTimeout(300);   // under Diagnostics since the opening view
  const al = await p.evaluate(() => { const li = document.getElementById('cb-alarm-1'); return { shown: !!(li && !li.hidden && li.getClientRects().length), seen: li ? li.innerText.includes('by Jason') : false, n: (document.querySelector('[data-cb-afcount]') || {}).textContent }; });
  t(W + 'D5: the acknowledged trip stays in the Open list, "Seen: by Jason", counted (2 open)', al.shown && al.seen && al.n === '2', al);
  // 6. Development: 16 gauges at Planning 0, its panel and items, Site health and its actions
  await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(300);   // the rail's Development since the opening view
  t(W + 'Development: its tab shows the 16 gauges and Site health', (await shown()).join() === 'cb-dev' && await p.evaluate(() => document.querySelectorAll('#cb-dev .cb-dc').length === 16 && !!document.getElementById('site-health').getClientRects().length));
  const dz = await p.evaluate(() => [...document.querySelectorAll('#cb-dev .cb-dc')].filter(c => /awaiting sign-off|no configuration yet/.test(c.innerText)).every(c => c.querySelector('.sr').dataset.score === '0.0' && c.querySelector('.sr-pill').textContent === 'Planning'));
  t(W + '…each unsigned one at 0 in Planning, "configuration drafted … awaiting sign-off"', dz);
  const rg3 = await p.evaluate(rings); t(W + '…every Arc within 1%', rg3.nbad === 0 && rg3.n >= 16, rg3);
  ws = await p.evaluate(wide); t(W + '…no sideways scroll', ws.sw <= w && !ws.over.length, ws);
  cm = await p.evaluate(copies); t(W + '…Copy on every section and card', cm.length === 0, cm);
  await p.click('#cb-dev .cb-dc .cb-gopen'); await p.waitForTimeout(900);
  const dp = await p.evaluate(() => { const x = [...document.querySelectorAll('.cb-sp')].find(s => !s.hidden); return x ? x.innerText : ''; });
  t(W + '…a gauge opens its panel: its items by status (loaded as it opens), its three paths', dp.includes('Its items, by status') && dp.includes('Registered Services'), dp.slice(0, 200));
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
  posts.length = 0;
  await p.click('#site-health-f-bbbbbb02 [data-state="fixed"]'); await p.waitForTimeout(300);
  const yes = await p.$('.tv-ask .tv-ask-b.yes'); t(W + 'Site health: Mark fixed asks first, in the site\'s own box', !!yes);
  if (yes) { await yes.click(); await p.waitForTimeout(600); }
  t(W + '…then posts Pulse\'s own action for sitehealth:open', posts.some(x => x.do === 'manage' && x.key === 'sitehealth:open' && x.what === 'sh_mark:f-bbbbbb02' && x.args && x.args.state === 'fixed'), posts);
  posts.length = 0;
  await p.click('#site-health-f-bbbbbb02 [data-state="aside"]'); await p.waitForTimeout(300);
  const inp = await p.$('.tv-ask-in'); if (inp) { await inp.fill('Fine for guests: it\'s a staff page.'); await p.click('.tv-ask .tv-ask-b.yes'); await p.waitForTimeout(600); }
  t(W + '…Set aside asks why, and sends it', posts.some(x => x.what === 'sh_mark:f-bbbbbb02' && x.args.state === 'aside' && /staff page/.test(x.args.note || '')), posts);
  posts.length = 0; await p.click('#site-health [data-cb-sh="sh_refresh"]'); await p.waitForTimeout(500);
  t(W + '…Check again reads the newest', posts.some(x => x.what === 'sh_refresh'), posts);
  // a link from Pulse lands on its finding: ?tab=development#site-health-<id>
  const p2 = await ctx.newPage(); await p2.route('**/*', r => { const u = new URL(r.request().url()); if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '' }); } }
    if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"rows":[],"total":0,"counts":{}}', contentType: 'application/json' }); return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p2.goto('http://localhost/admin/config/control?tab=development#site-health-f-aaaaaa01'); await p2.waitForTimeout(1200);
  const land = await p2.evaluate(() => { const li = document.getElementById('site-health-f-aaaaaa01'); const r = li.getBoundingClientRect(); return { vis: !!li.getClientRects().length, inView: r.top >= 0 && r.top < innerHeight }; });
  t(W + '…a Pulse item\'s link (?tab=development#site-health-<id>) lands on its finding, in view', land.vis && land.inView, land); await p2.close();
  // 7. Incidents and IR-0001's page
  await p.click('[data-cb-rail="diagnostics"]'); await p.waitForTimeout(200); await p.click('.cb-diagnav [data-cb-tab="incidents"]'); await p.waitForTimeout(300);   // under Diagnostics since the opening view
  t(W + 'Incidents: the IR list, IR-0001 in it', (await shown()).join() === 'cb-incidents' && (await p.evaluate(() => document.getElementById('cb-irs').innerText)).includes('IR-0001'));
  ws = await p.evaluate(wide); t(W + '…no sideways scroll', ws.sw <= w && !ws.over.length, ws);
  await p.goto('http://localhost/admin/config/control?ir=1'); await p.waitForTimeout(900);
  const irt = await p.evaluate(() => document.body.innerText);
  t(W + 'IR-0001\'s page: title, root cause Unconfirmed, members W-0001 / T-0001, radius, timeline, evidence', /IR-0001 · 1 part: W1 Listings · Paused by the system/.test(irt) && (w === 390 ? /Confirmed:/ : /Unconfirmed/).test(irt) && /W-0001/.test(irt) && /T-0001/.test(irt) && /Held/.test(irt) && /Timeline/.test(irt), irt.slice(0, 300));
  ws = await p.evaluate(wide); t(W + '…no sideways scroll', ws.sw <= w && !ws.over.length, ws);
  cm = await p.evaluate(copies); t(W + '…Copy on every section', cm.length === 0, cm);
  // 7b. Write the root cause, pressed once (02 §2.6, P12): the one-field form, its answer beside the button, the words in place, kept
  { const words = w === 1280 ? 'Our load endpoint timed out under the batch.' : 'The batch was too big for the load endpoint\'s timeout.';
    const btn = await p.$('[data-cb-ircause]'); const was = btn ? (await btn.textContent()).trim() : '';
    t(W + 'Write the root cause: a button on the report (' + was + ')', !!btn && /^(Write|Change) the root cause$/.test(was) && !(await btn.isDisabled()), was);
    if (btn) { await btn.click(); await p.waitForTimeout(300);
      const f = await p.$('.tv-ask-in'); t(W + '…opens the one-field form, its words to fill', !!f);
      if (f) { await f.fill(words); await p.click('.tv-ask-b.yes'); await p.waitForTimeout(900);
        const after = await p.evaluate(() => { const s = document.querySelector('[data-cb-ircause]').closest('section'); const rc = s.querySelector('.cb-rc'), n = rc && rc.querySelector('.tv-note');
          return { note: !!n, x: !!(n && n.querySelector('.tv-note-x')), text: rc && rc.textContent.trim(), btn: document.querySelector('[data-cb-ircause]').textContent,
            say: [...document.querySelectorAll('.tv-note')].map(x => x.textContent).join(' | ') }; });
        t(W + '…pressed: Confirmed with its words, the button now Change the root cause, the answer beside it', after.note && !after.x && after.text === 'Confirmed:' + words && after.btn === 'Change the root cause' && /Root cause written/.test(after.say), after);
        await p.goto('http://localhost/admin/config/control?ir=1'); await p.waitForTimeout(900);
        const kept = await p.evaluate(() => document.body.innerText);
        t(W + '…kept: the report opened again shows it Confirmed, with who wrote it', kept.includes('Confirmed:') && kept.includes(words) && kept.includes('(Jason)'), kept.slice(0, 300));
        const lg = execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-N', '-e', "SELECT COUNT(*) FROM control_log WHERE what LIKE 'Root cause written on IR-0001%'"]).toString().trim();
        t(W + '…and in the logbook', +lg >= 1, lg); } } }
  // 8. the folded report, and the page as a file on a phone
  await p.goto('http://localhost/admin/config/control?report=&range=week'); await p.waitForTimeout(900);
  const order = await p.evaluate(() => [...document.querySelectorAll('.cb-rsec')].map(x => x.id));
  t(W + 'the report: the summary and spend first, the rest after (D16)', order[0] === 'r-summary' && order[1] === 'r-spend' && order.indexOf('r-ran') > order.indexOf('r-changes'), order);
  ws = await p.evaluate(wide); t(W + '…no sideways scroll', ws.sw <= w && !ws.over.length, ws);
  cm = await p.evaluate(copies); t(W + '…Copy on every section', cm.length === 0, cm);
  await p.goto('http://localhost/report-file'); await p.waitForTimeout(300);
  ws = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth })); t(W + 'the page as a file still works here: no sideways scroll', ws.sw <= w, ws);
  t(W + 'no script error on any view', errs.length === 0, errs);
  await ctx.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
