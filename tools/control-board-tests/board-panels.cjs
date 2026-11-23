// What Jason opened on the live board (2026-11-23), seeded with the live alarms snapshot, at 1280 and 390:
//   item 2: the alarm's drawer → the part's own page → its register entry: one panel all the way (decisions/0175 §6), one
//           header, one ×, one "Open the full page", with Back to each step; Back, Esc, × and a click outside;
//   item 3: the register card's Identity and role heading has one "Copy" and one "Copy all", never two Copies stacked;
//           Copy all copies the whole card;
//   item 4: Google Cloud's alarm title carries its reading ("at 61%: 608 of 1,000 checks"), as its card does;
//   item 5: the panels are titled by the part's name ("Google Cloud"), never "Its process's page" or "Its register entry";
//           the drawer's subtitle names the part, never svc:google.
// Run after the start schema, 2a's ledger and board-panels-seed.php: node board-panels.cjs <root>
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]);
const id = execFileSync('php', [path.join(__dirname, 'board-panels-seed.php'), root + '/app', 'id']).toString().trim();
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const port = 8890 + Math.floor(Math.random() * 60);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root, REGISTER: 'full' }, stdio: 'ignore' });   // the real Service register, every card
const heads = p => p.evaluate(() => {
  const all = []; const walk = (d, depth) => { d.querySelectorAll('.ak-dr, .tv-pk').forEach(x => { if (x.getClientRects().length) all.push({ depth, title: (x.querySelector('.ak-drb b, .tv-pk-h span') || {}).textContent }); });
    d.querySelectorAll('iframe').forEach(f => { try { if (f.contentDocument) walk(f.contentDocument, depth + 1); } catch (e) {} }); };
  walk(document, 0); return all; });
const drawer = p => p.evaluate(() => { const d = document.querySelector('.ak-dr'); if (!d) return null; const h = d.querySelector('.ak-drb');
  return { title: h.querySelector('b').textContent, back: !!h.querySelector('.ak-drback') && !h.querySelector('.ak-drback').hidden,
    // the header's parts side by side, never over each other (Jason, 2026-11-23: "back and title conflict"); the framed page inset
    overlap: (() => { const r = [...h.children].filter(x => !x.hidden && x.getClientRects().length).map(x => x.getBoundingClientRect()); for (let i = 0; i < r.length; i++) for (let j = i + 1; j < r.length; j++) if (r[i].left < r[j].right - 1 && r[j].left < r[i].right - 1 && r[i].top < r[j].bottom - 1 && r[j].top < r[i].bottom - 1) return true; return false; })(),
    inset: (() => { const f = d.querySelector('iframe'); if (!f) return null; const a = f.getBoundingClientRect(), b = d.getBoundingClientRect(); return Math.round(a.left - b.left); })(), full: (h.querySelector('.ak-full, a[target=_top]') || { getAttribute: () => '' }).getAttribute('href') || '',
    x: h.querySelectorAll('.ak-x').length, fulls: [...document.querySelectorAll('.ak-drb a[target=_top]')].filter(a => !a.hidden).length, src: (d.querySelector('iframe') || {}).src || '', sub: (d.querySelector('.ak-sub') || {}).textContent || '',
    wide: d.getBoundingClientRect().right <= document.documentElement.clientWidth + 1 }; });
(async () => {
  await new Promise(r => setTimeout(r, 900));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) { try {
      const p = await b.newPage({ viewport: { width: w, height: 900 } }); const L = w + 'px: ';
      // Jason's path: the Alarms tab, a click on Google Cloud's row
      await p.goto(`http://127.0.0.1:${port}/admin/config/control?tab=alarms`); await p.waitForTimeout(1800);
      const row = p.locator('[data-ak-item="' + id + '"]').first();
      t(L + 'the Alarms tab lists Google Cloud\'s alarm as a row', await row.count() === 1, await p.locator('[data-ak-item]').count());
      if (await row.count()) { await row.click(); await p.waitForTimeout(1500); }
      let d = await drawer(p);
      t(L + 'a click on the row opens the alarm in its drawer, titled by the alarm, the address its own (#open=config/alarms:' + id + ')', d && /^Google Cloud at 61%: 608 of 1,000/.test(d.title) && new RegExp('#open=config/alarms:' + id + '$').test(p.url()), [d, p.url()]);
      t(L + '…its subtitle names the part, never svc:google (item 5)', d && /Google Cloud/.test(d.sub) && !/svc:/.test(d.sub), d && d.sub);
      const link = await p.evaluate(() => [...document.querySelectorAll('.ak-dr .ak-links a')].map(a => a.textContent));
      t(L + '…its own page is a link named by the part, never "Its process\'s page" (item 5)', link.includes('Google Cloud') && !link.some(x => /Its process/.test(x)), link);
      t(L + '…and its fix first: "Open its card: Google Cloud" (never a dead end: what to do is beside it)', link[0] === 'Open its card: Google Cloud', link);
      await p.click('.ak-dr .ak-links a:text-is("Google Cloud")'); await p.waitForTimeout(1800);
      d = await drawer(p); let hs = await heads(p);
      t(L + 'its own page replaces the drawer\'s content: one panel, titled "Google Cloud", with Back (item 2)', hs.length === 1 && d.title === 'Google Cloud' && d.back && /\/admin\/config\/control\/svc-google/.test(d.src), { hs, d });
      const fr = p.frames().find(f => /control\/svc-google/.test(f.url()));
      const reg = fr && await fr.evaluate(() => { const a = [...document.querySelectorAll('a[data-peek-frame]')].find(x => /register#svc-google$/.test(x.getAttribute('href')) && x.textContent !== 'Fix it' && x.textContent !== 'Raise the cap'); return a ? { text: a.textContent, title: a.getAttribute('data-peek-frame') } : null; });
      t(L + '…its fix "Google Cloud in the register" opens a panel titled "Google Cloud" (item 5)', reg && reg.text === 'Google Cloud in the register' && reg.title === 'Google Cloud', reg);
      if (reg) { await fr.click('a[data-peek-frame]:text-is("' + reg.text + '") >> nth=0'); await p.waitForTimeout(1800); }
      d = await drawer(p); hs = await heads(p);
      t(L + 'the register entry replaces it too: still one panel, one header, one ×, one "Open the full page" (item 2)', hs.length === 1 && d.x === 1 && d.fulls === 1 && /register/.test(d.src), { hs, d });
      t(L + '…titled "Google Cloud", Back shown, "Open the full page" the register entry\'s', d.title === 'Google Cloud' && d.back && /\/admin\/config\/register#svc-google$/.test(d.full), d);
      t(L + '…its header\'s Back, title, "Open the full page" and × side by side, none over another', d.back && d.overlap === false, d);
      t(L + '…the framed page inset from the drawer\'s edge (a margin, at least 3px)', d.inset !== null && d.inset >= 3, d.inset);
      t(L + '…the panel fits the window', d.wide, d);
      const rf0 = p.frames().find(f => /config\/register/.test(f.url()));
      const one = rf0 && await rf0.evaluate(() => ({ shown: [...document.querySelectorAll('.os-r')].filter(x => x.getClientRects().length).map(x => x.id),
        all: document.querySelectorAll('.os-r').length, other: [...document.body.children].filter(x => x.getClientRects().length && !x.contains(document.getElementById('svc-google')) && !/^(SCRIPT|STYLE)$/.test(x.tagName)).map(x => x.tagName + '.' + x.className).slice(0, 4) }));
      t(L + '…the panel shows Google Cloud\'s card alone, never the whole register (Jason, 2026-11-23; "Open the full page" has the list)', one && one.all > 5 && one.shown.length === 1 && one.shown[0] === 'svc-google' && !one.other.length, one);
      const rf = p.frames().find(f => /config\/register/.test(f.url()));
      const cp = rf && await rf.evaluate(() => { const g = document.getElementById('svc-google') || document; const h = g.querySelector('.mc-z1 > h3'); const hb = h ? [...h.querySelectorAll('.tv-copy-b')].map(x => x.textContent) : [];
        const card = g.querySelector('.mc-card'); const abs = card ? [...card.children].filter(x => x.classList.contains('tv-copy-b')).length : -1;
        const rects = [...g.querySelectorAll('.mc-z1 .tv-copy-b, .mc-card > .tv-copy-b')].map(x => x.getBoundingClientRect()).map(r => [Math.round(r.left), Math.round(r.top), Math.round(r.right), Math.round(r.bottom)]);
        let overlap = false; for (let i = 0; i < rects.length; i++) for (let j = i + 1; j < rects.length; j++) { const a = rects[i], c = rects[j]; if (a[0] < c[2] && c[0] < a[2] && a[1] < c[3] && c[1] < a[3]) overlap = true; }
        return { hb, abs, overlap, sc: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }; });
      t(L + 'Identity and role\'s heading: one "Copy" and one "Copy all", none stacked in the card\'s corner (item 3)', cp && cp.hb.filter(x => x === 'Copy').length === 1 && cp.hb.filter(x => x === 'Copy all').length === 1 && cp.abs === 0 && !cp.overlap, cp);
      t(L + '…the card has no sideways scroll in the panel', cp && cp.sc <= cp.cw + 1, cp);
      if (w === 1280 && rf) {
        await p.context().grantPermissions(['clipboard-read', 'clipboard-write'], { origin: `http://127.0.0.1:${port}` });
        await rf.click('#svc-google .mc-z1 > h3 .tv-copy-b[data-copy-all]'); await p.waitForTimeout(400);
        const all = await rf.evaluate(() => navigator.clipboard.readText()).catch(() => '');
        await rf.click('#svc-google .mc-z1 > h3 .tv-copy-b:not([data-copy-all])'); await p.waitForTimeout(400);
        const one = await rf.evaluate(() => navigator.clipboard.readText()).catch(() => '');
        t(L + 'Copy all copies the whole card (every zone); Copy copies Identity and role only', /Identity and role/i.test(all) && /Actions and Fix it/i.test(all) && /Identity and role/i.test(one) && !/Actions and Fix it/i.test(one) && !/Copy all/.test(all), [all.length, one.length, all.slice(0, 80)]);
      }
      await p.click('.ak-dr .ak-drback'); await p.waitForTimeout(1200); d = await drawer(p);
      t(L + 'Back returns to its own page, still one panel', d && d.title === 'Google Cloud' && /control\/svc-google/.test(d.src) && d.back && (await heads(p)).length === 1, d);
      await p.click('.ak-dr .ak-drback'); await p.waitForTimeout(800); d = await drawer(p);
      t(L + '…and again to the alarm, where Back hides and the address is the alarm\'s', d && /^Google Cloud at 61%/.test(d.title) && !d.back && new RegExp('#open=config/alarms:' + id + '$').test(p.url()), [d, p.url()]);
      await p.click('.ak-dr .ak-links a:text-is("Google Cloud")'); await p.waitForTimeout(1200);
      await p.keyboard.press('Escape'); await p.waitForTimeout(800);
      t(L + 'Esc closes it, Back and all', (await heads(p)).length === 0 && !/#open=/.test(p.url()), p.url());
      await p.goto('about:blank'); await p.goto(`http://127.0.0.1:${port}/admin/config/control?tab=alarms#open=config/alarms:${id}`); await p.waitForTimeout(1300);
      await p.click('.ak-dr .ak-links a:text-is("Google Cloud")'); await p.waitForTimeout(1200);
      await p.click('.ak-dr .ak-x'); await p.waitForTimeout(800);
      t(L + '× closes it from a step in, Back and all', (await heads(p)).length === 0, await heads(p));
      await p.goto('about:blank'); await p.goto(`http://127.0.0.1:${port}/admin/config/control?tab=alarms#open=config/alarms:${id}`); await p.waitForTimeout(1300);
      await p.click('.ak-dr .ak-links a:text-is("Google Cloud")'); await p.waitForTimeout(1200);
      const left = await p.evaluate(() => Math.round(document.querySelector('.ak-dr').getBoundingClientRect().left));
      if (left > 3) { await p.mouse.click(3, 450); await p.waitForTimeout(800); t(L + 'a click outside closes it', (await heads(p)).length === 0, await heads(p)); }
      else { t(L + 'on a phone the panel takes the whole width (nothing outside it), and × and Esc close it', left <= 0); await p.click('.ak-dr .ak-x'); await p.waitForTimeout(600); }
      const sw = await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
      t(L + 'the board has no sideways scroll', sw);
      await p.close();
    } catch (x) { t(w + 'px: the walk through finished', false, String(x.message).split('\n')[0]); } }
  } finally { srv.kill(); await b.close(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
