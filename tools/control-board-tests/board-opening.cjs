// The board's opening view (step3/board/index.html, approved 2026-11-22; Jason's ruling of 2026-11-23, 03 §27), seeded with
// the live alarms snapshot, at 1280 and 390:
//   the rail: Development · Board · Modules · Workflows · Diagnostics, in that order, Board current on landing; sticky on
//   the left at 1280, a wrapped row at 390;
//   "What's wrong now": chips in place of the alarm text (no strip, no healthy/held/needs-me boxes on the landing); each
//   chip pressed: its list opens, one at a time, × / Esc / outside close it, an item opens its part in the one side panel
//   titled by the part's name; Paused now opens its page, which keeps the strip and the three boxes whole;
//   Board lands on "Is it delivering?" beside Development (side by side at 1280, Stacked switch, kept on reload; stacked
//   at 390); every rail item pressed: Development alone, Diagnostics with the stages and tables (Incidents among them),
//   Modules and Workflows their own pages; nothing wider than the window. Screenshots beside the mockup's, to <shots>.
// Run after the start schema, 2a's ledger and board-panels-seed.php: node board-opening.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
const mock = path.join(__dirname, '../../handoffs/control-board-plan/step3/board/index.html');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const port = 8920 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const vis = (p, sel) => p.evaluate(s => { const e = document.querySelector(s); return !!e && !!e.getClientRects().length && getComputedStyle(e).display !== 'none'; }, sel);
const box = (p, sel) => p.evaluate(s => { const e = document.querySelector(s); if (!e) return null; const r = e.getBoundingClientRect(); return { l: Math.round(r.left), t: Math.round(r.top), r: Math.round(r.right), b: Math.round(r.bottom), w: Math.round(r.width) }; }, sel);
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
(async () => {
  await new Promise(r => setTimeout(r, 900));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) { try {
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage(); const L = w + 'px: ';
      const home = `http://127.0.0.1:${port}/admin/config/control`;
      await p.goto(home); await p.waitForTimeout(1500);
      // the rail
      const rail = await p.evaluate(() => [...document.querySelectorAll('nav.cb-rail[aria-label="Board views"] > *')].map(x => ({ v: x.getAttribute('data-cb-rail'), text: x.textContent.replace(/\d+$/, '').trim(), cur: x.getAttribute('aria-current') })));
      t(L + 'the rail: Development · Board · Modules · Workflows · Diagnostics, in that order (Jason, 2026-11-23)', rail.map(x => x.text).join(' · ') === 'Development · Board · Modules · Workflows · Diagnostics', rail);
      t(L + '…Board is current on landing', rail.find(x => x.v === 'deliver')?.cur === 'page' && rail.filter(x => x.cur === 'page').length === 1, rail);
      const rb = await box(p, 'nav.cb-rail'), mb = await box(p, '.cb-main');
      t(L + (w > 767 ? '…on the left of the board\'s main column' : '…a row above it on a phone'), w > 767 ? rb.r <= mb.l + 2 && rb.w < 230 : rb.b <= mb.t + 2, { rb, mb });
      // the chips
      const chips = await p.evaluate(() => [...document.querySelectorAll('nav.cb-qbar[aria-label="What\'s wrong now"] .cb-qgs')].map(x => x.textContent.replace('▾', '').trim()));
      t(L + '"What\'s wrong now": chips with their counts (Trips, Warnings, Caps, …)', chips.length >= 3 && chips.some(c => /^Warnings\d+$/.test(c)) && chips.some(c => /^Caps\d+$/.test(c)), chips);
      t(L + '…no alarm text wall on the landing: the strip and the healthy, held and needs-me boxes are off it', !(await vis(p, '#cb .cb-strip')) && !(await vis(p, '#cb-q3')), [await vis(p, '#cb .cb-strip'), await vis(p, '#cb-q3')]);
      t(L + '…no tile rows or stage line on the landing (the stages, tables and the stage\'s words are Diagnostics\')', !(await vis(p, '#cb .cb-stages')) && await p.evaluate(() => [...document.querySelectorAll('#cb .cb-diagnav')].every(x => !x.getClientRects().length)), await vis(p, '#cb .cb-stages'));
      const gap = await p.evaluate(() => { const d = document.getElementById('cb-dev'), f = d && [...d.children].find(x => x.getClientRects().length); return f ? Math.round(f.getBoundingClientRect().top - d.getBoundingClientRect().top) : -1; });
      t(L + '…Development starts at the top of its column (no stretched gap)', gap >= 0 && gap < 40, gap);
      // the landing
      t(L + 'Board: "Is it delivering?" and Development both shown', await vis(p, '#cb-deliver') && await vis(p, '#cb-dev') && await vis(p, '.cb-landh'), [await vis(p, '#cb-deliver'), await vis(p, '#cb-dev')]);
      let d1 = await box(p, '#cb-deliver'), d2 = await box(p, '#cb-dev');
      if (w > 767) {
        t(L + '…side by side ("2 columns")', d2.l > d1.r - 2 && Math.abs(d2.t - d1.t) < 30, { d1, d2 });
        await p.click('[data-cb-layout="rows"]'); await p.waitForTimeout(300); d1 = await box(p, '#cb-deliver'); d2 = await box(p, '#cb-dev');
        t(L + '…Stacked puts Development under it', d2.t >= d1.b - 2 && Math.abs(d2.l - d1.l) < 4, { d1, d2 });
        await p.reload(); await p.waitForTimeout(1300); d1 = await box(p, '#cb-deliver'); d2 = await box(p, '#cb-dev');
        t(L + '…and is kept on reload', d2.t >= d1.b - 2 && await p.getAttribute('[data-cb-layout="rows"]', 'aria-pressed') === 'true', { d1, d2 });
        await p.click('[data-cb-layout="grid"]'); await p.waitForTimeout(300);
      } else t(L + '…stacked on a phone (Development under it), the switch out of the way', d2.t >= d1.b - 2 && !(await vis(p, '.cb-seg')), { d1, d2 });
      if (shots) await p.screenshot({ path: shots + '/build-landing-' + w + '.png' });
      // every chip pressed
      const n = await p.locator('details.cb-pop').count();
      for (let i = 0; i < n; i++) {
        const c = p.locator('details.cb-pop').nth(i); const nm = (await c.locator('summary').textContent()).replace('▾', '').trim();
        await c.locator('summary').click(); await p.waitForTimeout(200);
        const open = await p.evaluate(() => [...document.querySelectorAll('details.cb-pop[open]')].length);
        const pb = await p.evaluate(i => { const e = document.querySelectorAll('details.cb-pop')[i].querySelector('.cb-popb'); const r = e.getBoundingClientRect(); return { l: r.left, r: r.right, n: e.querySelectorAll('.cb-qj').length }; }, i);
        t(L + 'chip ' + nm + ': its list opens, one at a time, inside the window', open === 1 && pb.n > 0 && pb.l >= 0 && pb.r <= w + 1, { open, pb });
        if (i === 0) { await p.click('details.cb-pop[open] .cb-px'); await p.waitForTimeout(150); t(L + '…× closes it', await p.evaluate(() => !document.querySelector('details.cb-pop[open]'))); await c.locator('summary').click(); await p.waitForTimeout(150);
          await p.keyboard.press('Escape'); await p.waitForTimeout(150); t(L + '…Esc closes it', await p.evaluate(() => !document.querySelector('details.cb-pop[open]'))); await c.locator('summary').click(); await p.waitForTimeout(150);
          await p.mouse.click(w - 5, 880); await p.waitForTimeout(150); t(L + '…a click outside closes it', await p.evaluate(() => !document.querySelector('details.cb-pop[open]'))); await c.locator('summary').click(); await p.waitForTimeout(150); }
        const a = c.locator('.cb-popb a.cb-qj').first();
        if (await a.count()) {
          const want = await a.getAttribute('data-peek-frame');
          await a.click(); await p.waitForTimeout(900);
          const pk = await p.evaluate(() => [...document.querySelectorAll('.tv-pk, .ak-dr')].filter(x => x.getClientRects().length).map(x => (x.querySelector('.tv-pk-h span, .ak-drb b') || {}).textContent));
          t(L + 'chip ' + nm + ': an item opens its part in one side panel, titled by the part ("' + want + '"), its list closed', pk.length === 1 && pk[0] === want && await p.evaluate(() => !document.querySelector('details.cb-pop[open]')), pk);
          await p.keyboard.press('Escape'); await p.waitForTimeout(400);
        }
        if (/^Paused now/.test(nm)) {
          await c.locator('summary').click(); await p.waitForTimeout(200);
          await c.locator('.cb-qall').click(); await p.waitForTimeout(500);
          t(L + 'Paused now: its page, the strip and the three boxes whole, nothing else of the board', await vis(p, '#cb-paused-page') && await vis(p, '#cb .cb-strip') && await vis(p, '#cb-q3') && !(await vis(p, '#cb-deliver')), [await vis(p, '#cb-paused-page'), await vis(p, '#cb-q3')]);
          await p.click('[data-cb-rail="deliver"]'); await p.waitForTimeout(300);
        }
      }
      // every rail item pressed
      await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(400);
      t(L + 'rail Development: Development alone, current', await vis(p, '#cb-dev') && !(await vis(p, '#cb-deliver')) && !(await vis(p, '.cb-landh')) && await p.getAttribute('[data-cb-rail="development"]', 'aria-current') === 'page');
      await p.click('[data-cb-rail="diagnostics"]'); await p.waitForTimeout(400);
      const dg = await p.evaluate(() => [...document.querySelectorAll('.cb-diagnav [data-cb-tab]')].map(x => x.getAttribute('data-cb-tab')));
      t(L + 'rail Diagnostics: the stages and the tables (Incidents, Alarms, Logbook, Ledger, Upkeep, Reports, Scheduled tasks), current', await vis(p, '.cb-stages.cb-diagnav') && ['incidents', 'alarms', 'log', 'ledger', 'upkeep', 'reports', 'scheduler', 'observe'].every(k => dg.includes(k)) && !(await vis(p, '#cb-deliver')) && await p.getAttribute('[data-cb-rail="diagnostics"]', 'aria-current') === 'page', dg);
      await p.click('.cb-diagnav [data-cb-tab="alarms"]'); await p.waitForTimeout(500);
      t(L + '…a table (Alarms) keeps Diagnostics current', await p.getAttribute('[data-cb-rail="diagnostics"]', 'aria-current') === 'page' && await vis(p, '#cb-tab-alarms'));
      await p.click('[data-cb-rail="deliver"]'); await p.waitForTimeout(400);
      t(L + 'rail Board: back to the landing', await vis(p, '#cb-deliver') && await vis(p, '#cb-dev') && await p.getAttribute('[data-cb-rail="deliver"]', 'aria-current') === 'page');
      t(L + 'nothing wider than the window', await noSide(p));
      for (const [v, u] of [['modules', '/admin/config/register'], ['workflows', '/admin/config/workflows']]) {
        await p.goto(home); await p.waitForTimeout(900);
        await Promise.all([p.waitForNavigation().catch(() => {}), p.click('[data-cb-rail="' + v + '"]')]); await p.waitForTimeout(300);
        t(L + 'rail ' + v[0].toUpperCase() + v.slice(1) + ': its own page (' + u + ')', new URL(p.url()).pathname === u, p.url());
      }
      if (shots) { const m = await ctx.newPage(); await m.goto('file://' + mock); await m.waitForTimeout(1200); await m.screenshot({ path: shots + '/mockup-landing-' + w + '.png' }); await m.close(); }
      await ctx.close();
    } catch (x) { t(w + 'px: the walk through finished', false, String(x.message).split('\n')[0]); } }
  } finally { srv.kill(); await b.close(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
