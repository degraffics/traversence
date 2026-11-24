// The board's opening view (step3/board/index.html, approved 2026-11-22; Jason's ruling of 2026-11-23, 03 §27), seeded with
// the live alarms snapshot, at 1280 and 390:
//   the rail: Development · Board · Modules · Workflows · Diagnostics, in that order, Board current on landing; sticky on
//   the left at 1280; at 390 the same pages in a ☰ beside Alerts, on one line (board-phone, Jason 2026-11-24);
//   "What's wrong now": one Alerts dropdown in place of the alarm text and the row of chips (Jason, 2026-11-24: "1 full
//   width dropdown for alerts"), its count the board's one count, its groups inside adding up to it; each dropdown
//   pressed: it opens, one at a time, × / Esc / outside close it, an item opens its part in the one side panel titled by
//   the part's name; Paused now (a group in Alerts) opens its page, which keeps the strip and the three boxes whole;
//   Board lands on "Is it delivering?" alone, full width (Jason, 2026-11-23: "development is its own page, not side by
//   side on control board"); every rail item pressed: Development alone, Diagnostics with the stages and tables (Incidents among them),
//   Modules and Workflows their own pages; nothing wider than the window. Screenshots beside the mockup's, to <shots>.
// Run after the start schema, 2a's ledger and board-panels-seed.php: node board-opening.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const { view } = require('./board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
const mock = path.join(__dirname, '../../handoffs/control-board-plan/step3/board/index.html');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const port = 8920 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const vis = (p, sel) => p.evaluate(s => { const e = document.querySelector(s); return !!e && !!e.getClientRects().length && getComputedStyle(e).display !== 'none'; }, sel);
const box = (p, sel) => p.evaluate(s => { const e = document.querySelector(s); if (!e) return null; const r = e.getBoundingClientRect(); return { l: Math.round(r.left), t: Math.round(r.top), r: Math.round(r.right), b: Math.round(r.bottom), w: Math.round(r.width) }; }, sel);
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
// every visible gauge title whole: not cut (its words fit its box), no button over it, at most 3 lines (the session
// manager, 2026-11-23: "Communit", "Marketplac" were cut by the per-card Copy, and titles wrapped to 4 lines)
const titles = p => p.evaluate(() => { const bad = []; let n = 0;
  document.querySelectorAll('#cb .cb-gc .cb-gh').forEach(h => { if (!h.getClientRects().length) return; n++; const r = h.getBoundingClientRect(), lh = parseFloat(getComputedStyle(h).lineHeight) || 18;
    const over = [...h.closest('.cb-gc').querySelectorAll('button, a, .tv-copy-b')].filter(b => b.getClientRects().length && !h.contains(b) && !b.contains(h)).some(b => { const q = b.getBoundingClientRect(); return q.left < r.right - 1 && r.left < q.right - 1 && q.top < r.bottom - 1 && r.top < q.bottom - 1; });
    const cut = h.scrollWidth > h.clientWidth + 1 || [...h.textContent.split(/\s+/)].some(w => { const sp = document.createElement('span'); sp.textContent = w; sp.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;font:' + getComputedStyle(h).font; document.body.appendChild(sp); const ww = sp.getBoundingClientRect().width; sp.remove(); return ww > r.width + 1; });
    const lines = Math.round(r.height / lh);
    if (over || cut || lines > 3) bad.push({ t: h.textContent, over, cut, lines }); });
  return { n, bad: bad.slice(0, 5) }; });
(async () => {
  await new Promise(r => setTimeout(r, 900));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) { try {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage(); const L = w + 'px: ';
      const home = `http://127.0.0.1:${port}/admin/config/control`;
      await p.goto(home); await p.waitForTimeout(1500);
      // the rail
      // at 768px and wider the rail; on a phone the same pages in the ☰ beside Alerts (board-phone, Jason 2026-11-24)
      const rail = await p.evaluate(ph => [...document.querySelectorAll(ph ? '.cb-pml [data-cb-prail]' : 'nav.cb-rail[aria-label="Board views"] > *')].map(x => ({ v: x.getAttribute('data-cb-rail') || x.getAttribute('data-cb-prail'), text: x.textContent.replace(/\d+$/, '').trim(), cur: x.getAttribute('aria-current') })), w < 768);
      t(L + 'the rail: Development · Board · Modules · Workflows · Diagnostics, in that order (Jason, 2026-11-23)', rail.map(x => x.text).join(' · ') === 'Development · Board · Modules · Workflows · Diagnostics', rail);
      t(L + '…Board is current on landing', rail.find(x => x.v === 'deliver')?.cur === 'page' && rail.filter(x => x.cur === 'page').length === 1, rail);
      const rb = await box(p, 'nav.cb-rail'), mb = await box(p, '.cb-main');
      if (w > 767) t(L + '…on the left of the board\'s main column; no ☰', rb.r <= mb.l + 2 && rb.w < 230 && !(await vis(p, '.cb-pmenu')), { rb, mb });
      else { const pm = await box(p, '.cb-pmenu'), al = await box(p, '.cb-alerts');
        t(L + '…on a phone: no rail, the ☰ "Board" on the left of Alerts on one line, Alerts filling the rest', !(await vis(p, 'nav.cb-rail')) && pm && al && pm.r <= al.l && Math.abs(pm.t - al.t) < 4 && al.r >= w - 30 && (await p.textContent('[data-cb-pname]')) === 'Board', { pm, al }); }
      // "What's wrong now" as one Alerts dropdown (Jason, 2026-11-24): its count is the board's one count, the groups inside
      const al = await p.evaluate(() => ({ n: +document.querySelector('.cb-alerts .cb-qgs > b').textContent, light: +((document.querySelector('.ak-light') || {}).textContent || NaN),
        rail: +(document.querySelector('[data-cb-rail="deliver"] .cb-rcnt') || {}).textContent, groups: [...document.querySelectorAll('.cb-alerts .cb-ag')].map(g => g.getAttribute('aria-label') + ' ' + g.querySelector('.cb-agh b').textContent),
        sum: [...document.querySelectorAll('.cb-alerts .cb-ag')].filter(g => !/^(Paused now|Hosts)$/.test(g.getAttribute('aria-label'))).reduce((a, g) => a + +g.querySelector('.cb-agh b').textContent, 0),
        chips: document.querySelectorAll('nav.cb-qbar > details.cb-pop').length }));
      t(L + '"What\'s wrong now": one Alerts dropdown, groups inside with their counts (Trips, Warnings, Caps, …), no row of chips', al.chips === 2 && al.groups.some(g => /^Warnings \d+$/.test(g)) && al.groups.some(g => /^Caps \d+$/.test(g)) && al.groups.some(g => /^Paused now \d+$/.test(g)), al);
      t(L + '…Alerts\' count is the board\'s one count: its alarm groups add up to it, and the rail\'s Board says the same', al.n > 0 && al.n === al.sum && al.n === al.rail, al);
      t(L + '…no alarm text wall on the landing: the strip and the healthy, held and needs-me boxes are off it', !(await vis(p, '#cb .cb-strip')) && !(await vis(p, '#cb-q3')), [await vis(p, '#cb .cb-strip'), await vis(p, '#cb-q3')]);
      t(L + '…no tile rows or stage line on the landing (the stages, tables and the stage\'s words are Diagnostics\')', !(await vis(p, '#cb .cb-stages')) && await p.evaluate(() => [...document.querySelectorAll('#cb .cb-diagnav')].every(x => !x.getClientRects().length)), await vis(p, '#cb .cb-stages'));
      // the landing: Board is "Is it delivering?" alone, full width; Development is its own page (Jason, 2026-11-23)
      t(L + 'Board: "Is it delivering?" alone, full width; Development not on it; no 2 columns / Stacked switch', await vis(p, '#cb-deliver') && !(await vis(p, '#cb-dev')) && await vis(p, '.cb-landh') && !(await p.$('[data-cb-layout]'))
        && await p.evaluate(() => { const d = document.getElementById('cb-deliver').getBoundingClientRect(), m = document.querySelector('.cb-main').getBoundingClientRect(); return d.width >= m.width - 4; }), [await vis(p, '#cb-deliver'), await vis(p, '#cb-dev')]);
      let tt = await titles(p);
      t(L + 'every gauge title on Board whole: not cut, nothing over it, at most 3 lines', tt.n > 10 && !tt.bad.length, tt);
      const cpy = await p.evaluate(() => [...document.querySelectorAll('#cb .cb-gc')].filter(c => c.getClientRects().length).every(c => { const b = c.querySelector(':scope > .cb-gcopy > .tv-copy-b'); return !!b; }));
      t(L + '…each card keeps its Copy, under its buttons (a slot at the card\'s foot), none on the title line', cpy);
      if (shots) await p.screenshot({ path: shots + '/build-landing-' + w + '.png' });
      // every chip pressed
      const n = await p.locator('nav.cb-qbar > details.cb-pop').count();
      for (let i = 0; i < n; i++) {
        const c = p.locator('nav.cb-qbar > details.cb-pop').nth(i); if (!(await c.isVisible())) continue; const nm = (await c.locator('summary').textContent()).replace('▾', '').trim();
        await c.locator('summary').click(); await p.waitForTimeout(200);
        const open = await p.evaluate(() => [...document.querySelectorAll('details.cb-pop[open]')].length);
        const pb = await p.evaluate(i => { const e = document.querySelectorAll('nav.cb-qbar > details.cb-pop')[i].querySelector('.cb-popb'); const r = e.getBoundingClientRect(); return { l: r.left, r: r.right, n: e.querySelectorAll('.cb-qj, [data-cb-prail]').length }; }, i);
        t(L + 'dropdown ' + nm + ': its list opens, one at a time, inside the window', open === 1 && pb.n > 0 && pb.l >= 0 && pb.r <= w + 1, { open, pb });
        if (i === 0) { await p.click('details.cb-pop[open] .cb-px'); await p.waitForTimeout(150); t(L + '…× closes it', await p.evaluate(() => !document.querySelector('details.cb-pop[open]'))); await c.locator('summary').click(); await p.waitForTimeout(150);
          await p.keyboard.press('Escape'); await p.waitForTimeout(150); t(L + '…Esc closes it', await p.evaluate(() => !document.querySelector('details.cb-pop[open]'))); await c.locator('summary').click(); await p.waitForTimeout(150);
          await p.mouse.click(w - 5, 880); await p.waitForTimeout(150); t(L + '…a click outside closes it', await p.evaluate(() => !document.querySelector('details.cb-pop[open]'))); await c.locator('summary').click(); await p.waitForTimeout(150); }
        const a = c.locator('.cb-popb a.cb-qj').first();
        if (await a.count()) {
          const want = await a.getAttribute('data-peek-frame');
          await a.click(); await p.waitForTimeout(900);
          const pk = await p.evaluate(() => [...document.querySelectorAll('.tv-pk, .ak-dr')].filter(x => x.getClientRects().length).map(x => (x.querySelector('.tv-pk-h span, .ak-drb b') || {}).textContent));
          t(L + 'dropdown ' + nm + ': an item opens its part in one side panel, titled by the part ("' + want + '"), its list closed', pk.length === 1 && pk[0] === want && await p.evaluate(() => !document.querySelector('details.cb-pop[open]')), pk);
          await p.keyboard.press('Escape'); await p.waitForTimeout(400);
        }
        if (/^Alerts/.test(nm)) {
          await c.locator('summary').click(); await p.waitForTimeout(200);
          await c.locator('.cb-ag[aria-label="Paused now"] .cb-qall').click(); await p.waitForTimeout(500);
          t(L + 'Paused now: its page, the strip and the three boxes whole, nothing else of the board', await vis(p, '#cb-paused-page') && await vis(p, '#cb .cb-strip') && await vis(p, '#cb-q3') && !(await vis(p, '#cb-deliver')), [await vis(p, '#cb-paused-page'), await vis(p, '#cb-q3')]);
          await view(p, 'deliver'); await p.waitForTimeout(300);
        }
      }
      // every rail item pressed
      await view(p, 'development'); await p.waitForTimeout(400);
      t(L + 'rail Development: Development alone, current', await vis(p, '#cb-dev') && !(await vis(p, '#cb-deliver')) && !(await vis(p, '.cb-landh')) && await p.getAttribute('[data-cb-rail="development"]', 'aria-current') === 'page');
      tt = await titles(p); t(L + '…its gauge titles whole too', tt.n >= 16 && !tt.bad.length, tt);
      if (shots) await p.screenshot({ path: shots + '/build-development-' + w + '.png' });
      const gap = await p.evaluate(() => { const d = document.getElementById('cb-dev'), f = d && [...d.children].find(x => x.getClientRects().length); return f ? Math.round(f.getBoundingClientRect().top - d.getBoundingClientRect().top) : -1; });
      t(L + '…its page starts at the top (no stretched gap)', gap >= 0 && gap < 40, gap);
      await view(p, 'diagnostics'); await p.waitForTimeout(400);
      const dg = await p.evaluate(() => [...document.querySelectorAll('.cb-diagnav [data-cb-tab]')].map(x => x.getAttribute('data-cb-tab')));
      t(L + 'rail Diagnostics: the stages and the tables (Incidents, Alarms, Logbook, Ledger, Upkeep, Reports, Scheduled tasks), current', await vis(p, '.cb-stages.cb-diagnav') && ['incidents', 'alarms', 'log', 'ledger', 'upkeep', 'reports', 'scheduler', 'observe'].every(k => dg.includes(k)) && !(await vis(p, '#cb-deliver')) && await p.getAttribute('[data-cb-rail="diagnostics"]', 'aria-current') === 'page', dg);
      await p.click('.cb-diagnav [data-cb-tab="alarms"]'); await p.waitForTimeout(500);
      t(L + '…a table (Alarms) keeps Diagnostics current', await p.getAttribute('[data-cb-rail="diagnostics"]', 'aria-current') === 'page' && await vis(p, '#cb-tab-alarms'));
      await view(p, 'deliver'); await p.waitForTimeout(400);
      t(L + 'rail Board: back to the landing ("Is it delivering?" alone)', await vis(p, '#cb-deliver') && !(await vis(p, '#cb-dev')) && await p.getAttribute('[data-cb-rail="deliver"]', 'aria-current') === 'page');
      t(L + 'nothing wider than the window', await noSide(p));
      for (const [v, u] of [['modules', '/admin/config/register'], ['workflows', '/admin/config/workflows']]) {
        await p.goto(home); await p.waitForTimeout(900);
        await Promise.all([p.waitForNavigation().catch(() => {}), view(p, v)]); await p.waitForTimeout(300);
        t(L + 'rail ' + v[0].toUpperCase() + v.slice(1) + ': its own page (' + u + ')', new URL(p.url()).pathname === u, p.url());
      }
      if (shots) { const m = await ctx.newPage(); await m.goto('file://' + mock); await m.waitForTimeout(1200); await m.screenshot({ path: shots + '/mockup-landing-' + w + '.png' }); await m.close(); }
      await ctx.close();
    } catch (x) { t(w + 'px: the walk through finished', false, String(x.message).split('\n')[0]); } }
  } finally { srv.kill(); await b.close(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
