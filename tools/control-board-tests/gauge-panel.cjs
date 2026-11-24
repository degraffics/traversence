// The gauge panel as a drill-down (approved by Jason 2026-11-23; handoffs/gauge-panel.md; CLAUDE.md "Every action is a task"),
// rendered on the bench at 1280 and 390 with the gauges from the repo's own files:
//   - nothing in a gauge panel is inert: every row's name, every chip, every bar part and legend chip is a link or a button,
//     on a development panel and on a runtime one (Jason: "every single listed item is a dead end");
//   - one filled button per box; no Sign off on items; no red;
//   - the bar's parts and the legend filter the list, pressed again show all; the first 10 rows, then Show all;
//   - the dots' legend opens by ⓘ and closes by ×, Esc and a click outside;
//   - a row opens in place; a finding or a check opens over the panel with Back; its Request a fix presses the row's;
//   - one cycle: Request a fix → "Requested: the <gauge> build has it" with Undo, the button gone; Undo puts it back; no Next;
//     a request is kept (drawn again after a reload), and past a day untaken says "Not picked up yet" (Jason, 2026-11-23: "fix it
//     sounds like an action to be done right now": nothing on this panel acts now, so no button here says Fix it); Approve the scope → its line with Undo;
//   - a runtime panel: its setup in words, each step's link to its card above, its needs folded;
//   - nothing wider than the window.
// Run after the start schema and board-panels-seed.php: node gauge-panel.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const fs = require('fs'); const gdir = fs.mkdtempSync(path.join(require('os').tmpdir(), 'gauge-panel-')); const gfile = path.join(gdir, 'gauges.json');
for (const f of ['gauges.json', 'gauges-items.json']) fs.copyFileSync(path.join(__dirname, '../../site-audit/results/', f), path.join(gdir, f));   // the detail is read beside it
const port = 8960 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')],
  { env: { ...process.env, ROOT: root, GAUGES_FILE: gfile }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
// a pickup written into a copy of gauges.json (never the repo's), the site's copy of it forgotten so the page reads it again
function regauge(edit) { const gj = JSON.parse(fs.readFileSync(gfile, 'utf8')); edit(gj); fs.writeFileSync(gfile, JSON.stringify(gj));
  require('child_process').spawnSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-e', "DELETE FROM page_cache WHERE cache_key LIKE 'gauges:%'"]);
  for (const f of (fs.existsSync(root + '/app/storage') ? fs.readdirSync(root + '/app/storage') : [])) if (/^gauges-cache-/.test(f)) fs.unlinkSync(root + '/app/storage/' + f); }
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
// an element that is text a person might press, but neither a link nor a button
const inert = (p, sel) => p.evaluate(s => [...document.querySelectorAll(s)].filter(e => e.getClientRects().length).flatMap(sp =>
  [...sp.querySelectorAll('.cbg-nm, .cbg-chip:not(.cbg-plain), .cbg-seg, .cbg-lg')].filter(e => !/^(A|BUTTON)$/.test(e.tagName)).map(e => e.outerHTML.slice(0, 90))), sel);
const red = (p, sel) => p.evaluate(s => [...document.querySelectorAll(s + ' *')].filter(e => e.getClientRects().length).map(e => getComputedStyle(e))
  .filter(c => [c.color, c.backgroundColor, c.borderLeftColor].some(x => x.replace(/\s/g, '') === 'rgb(166,43,43)')).length, sel);
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(1200);
      await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(500);
      await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector('#cb-sp-d-discovery-snap .cbg', { timeout: 15000 });
      const sp = '#cb-sp-d-discovery-snap';
      if (shots) await p.screenshot({ path: shots + '/gauge-dev-' + w + '.png' });
      t(L + 'Discovery: its bar reads Issues to fix · Not built yet · No check yet (Done hidden at 0)', (await p.$$eval(sp + ' .cbg-seg:not([hidden])', a => a.map(x => x.getAttribute('data-cbg-f')).join(' '))) === 'fail build check');
      t(L + 'nothing in the panel is inert text (names, chips, bar, legend)', !(await inert(p, sp)).length, await inert(p, sp));
      t(L + 'one filled button among the cards (Approve the scope), the rest outlined', (await p.$$eval(sp + ' .cbg-ac .cbg-b:not(.cbg-o)', a => a.map(x => x.textContent))).join('|') === 'Approve the scope');
      t(L + 'no Sign off anywhere on items, no red', (await p.$$eval(sp + ' button', a => a.filter(x => /sign off/i.test(x.textContent)).length)) === 0 && (await red(p, sp)) === 0);
      t(L + 'the first 10 rows, then Show all 30', (await p.$$eval(sp + ' .cbg-it', a => a.filter(x => x.getClientRects().length).length)) === 10 && /Show all 30/.test(await p.textContent(sp + ' [data-cbg-more]')));
      for (const f of ['fail', 'build', 'check']) {
        await p.click(sp + ' .cbg-seg[data-cbg-f="' + f + '"]');
        const vis = await p.$$eval(sp + ' .cbg-it', a => a.filter(x => x.getClientRects().length).map(x => x.getAttribute('data-cbg-b')));
        t(L + 'the bar\'s ' + f + ' part shows only its rows, all of them', vis.length > 0 && vis.every(x => x === f), vis);
        await p.click(sp + ' .cbg-seg[data-cbg-f="' + f + '"]');
      }
      await p.click(sp + ' .cbg-info'); t(L + 'the dots\' legend opens', await p.isVisible(sp + ' .cbg-dleg'));
      await p.keyboard.press('Escape'); t(L + '…Esc closes it, the panel stays', !(await p.isVisible(sp + ' .cbg-dleg')) && await p.isVisible(sp));
      await p.click(sp + ' .cbg-info'); await p.click(sp + ' .cbg-dlx'); t(L + '…× closes it', !(await p.isVisible(sp + ' .cbg-dleg')));
      await p.click(sp + ' .cbg-info'); await p.click(sp + ' .cbg-top'); t(L + '…a click outside closes it', !(await p.isVisible(sp + ' .cbg-dleg')));
      // an issue: open it, its evidence in place, every chip opens its view with Back
      await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]');
      const row = sp + ' .cbg-it[data-cbg-b="fail"] >> nth=0';
      await p.click(row + ' >> .cbg-ex');
      t(L + 'an issue opens in place: one sentence, its chips, one filled Request a fix (no Fix it: it only queues)', /^The issue: /.test(await p.textContent(row + ' >> .cbg-st')) && (await p.locator(row).locator('.cbg-acts .cbg-b:not(.cbg-o)').count()) === 1 && (await p.locator(row).locator('.cbg-acts .cbg-b:not(.cbg-o)').textContent()).trim() === 'Request a fix' && !(await p.locator(sp).evaluate(n => [...n.querySelectorAll('button')].some(b => /^Fix it/.test(b.textContent.trim())))));
      for (const c of await p.locator(row).locator('.cbg-chip[data-cbg-open]').all()) {
        await c.click(); const v = await p.locator(sp + ' .cbg-sub:not([hidden])').count();
        t(L + 'a chip opens its view over the panel', v === 1); await p.click(sp + ' .cbg-sub:not([hidden]) [data-cbg-back]');
      }
      // ⋯: the row's other moves, the defined ones only (Jason, 2026-11-23: suggested action, then ⋯; the session manager: no
      // "Send to another build", no "Take the check out"); Esc, a click outside and choosing one close it
      const rm = p.locator(row).locator('[data-cbg-rm]');
      await rm.click();
      const items = (await p.locator(row).locator('.cbg-rm [role=menuitem]').allTextContents()).map(x => x.trim());
      t(L + '⋯ opens the row\'s other moves: See the evidence (or what a check found), Its row in Site health, Where this comes from', items.length >= 2 && /^(See the evidence|See what .* found)$/.test(items[0]) && items.includes('Where this comes from') && items.every(x => !/Send to another build|Take the check out|Fix it/.test(x)), items);
      const mb = await p.locator(row).locator('.cbg-rm [role=menu]').boundingBox();
      t(L + '…the menu wholly in the window', mb && mb.x >= 0 && mb.x + mb.width <= w + 1 && mb.y >= 0 && mb.y + mb.height <= (w > 500 ? 900 : 844) + 1, mb);
      if (shots) await p.screenshot({ path: shots + '/gauge-more-' + w + '.png' });
      await p.keyboard.press('Escape');
      t(L + '…Esc closes the menu, not the panel', await p.locator(row).locator('.cbg-rm [role=menu]').isHidden() && await p.locator(sp).isVisible());
      await rm.click(); await p.click(sp + ' .cbg-st >> nth=0');
      t(L + '…a click outside closes it', await p.locator(row).locator('.cbg-rm [role=menu]').isHidden());
      await rm.click(); await p.locator(row).locator('.cbg-rm [data-cbg-src]').click();
      t(L + '…Where this comes from opens its fold, the menu closed', await p.locator(row).locator('.cbg-src').evaluate(d => d.open) && await p.locator(row).locator('.cbg-rm [role=menu]').isHidden());
      if (shots) { await p.locator(row).locator('.cbg-chip[data-cbg-open]').first().click(); await p.screenshot({ path: shots + '/gauge-evidence-' + w + '.png' }); await p.click(sp + ' .cbg-sub:not([hidden]) [data-cbg-back]'); }
      // one cycle: Request a fix → Requested with Undo; Undo puts it back; no Next
      await p.locator(row).locator('[data-cbg="fix"]').click(); await p.waitForSelector(sp + ' .cbg-done', { timeout: 8000 });
      const line = await p.locator(row).locator('.cbg-done').textContent();
      t(L + 'Request a fix → "Requested · waiting for a build to take it" (no session has it yet), Undo beside it, the button gone', /^Requested · waiting for a build to take it/.test(line) && !/Fixing/.test(line) && /Undo/.test(line) && !(await p.locator(row).locator('[data-cbg="fix"]').isVisible()), line);
      t(L + '…and no Next: the cycle ends there', !/Next/.test(line) && (await p.locator(row).locator('.cbg-done .cbg-b').count()) === 0);
      if (shots) await p.screenshot({ path: shots + '/gauge-requested-' + w + '.png' });
      await p.locator(row).locator('.cbg-done [data-cbg="undo"]').click(); await p.waitForTimeout(600);
      t(L + 'Undo puts Request a fix back', await p.locator(row).locator('[data-cbg="fix"]').isVisible() && (await p.locator(row).locator('.cbg-done').count()) === 0);
      // kept: pressed again, then the panel drawn again, the line is there
      await p.locator(row).locator('[data-cbg="fix"]').click(); await p.waitForSelector(sp + ' .cbg-done', { timeout: 8000 });
      await p.goto(home); await p.waitForTimeout(900); await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(400);
      await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector(sp + ' .cbg', { timeout: 15000 });
      await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]'); await p.click(row + ' >> .cbg-ex');
      t(L + 'a request is kept: drawn again with its Requested line and Undo', /^Requested · waiting/.test(await p.locator(row).locator('.cbg-done').textContent()));
      t(L + '…and within its first day it doesn\'t say "Not picked up yet"', !/Not picked up yet/.test(await p.locator(row).locator('.cbg-done').textContent()));
      // a day later with no build taking it: "Not picked up yet", the session manager told
      require('child_process').spawnSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-e', "UPDATE control_gauge_tasks SET at = UTC_TIMESTAMP() - INTERVAL 25 HOUR WHERE kind = 'fix' AND undone_at IS NULL"]);
      await p.goto(home); await p.waitForTimeout(900); await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(400);
      await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector(sp + ' .cbg', { timeout: 15000 });
      await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]'); await p.click(row + ' >> .cbg-ex');
      t(L + 'past a day untaken: "Not picked up yet: the session manager has been told", in amber (--cb-hold), and still Undo (a request is withdrawn at any age)', /Not picked up yet: the session manager has been told/.test(await p.locator(row).locator('.cbg-done').textContent()) && (await p.locator(row).locator('.cbg-done [data-cbg="undo"]').count()) === 1 && await p.locator(row).locator('.cbg-late').evaluate(b => getComputedStyle(b).color) === 'rgb(143, 86, 0)');
      // a session takes it (releases/gauge-pickups.json through gauges.json): the line names it, then its release
      const tid = (require('child_process').spawnSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', 'lv_main', '-e', "SELECT MAX(id) FROM control_gauge_tasks WHERE kind = 'fix' AND undone_at IS NULL"]).stdout + '').trim();
      for (const [rel, want] of [['', /^Requested: the Control Board build session has it · Jason/], ['kit-buttons.zip', /^Requested: the Control Board build session has it · Being fixed in kit-buttons\.zip · Jason/]]) {
        regauge(gj => { gj.pickups = { [tid]: ['Control Board build session', rel] }; });
        await p.goto(home); await p.waitForTimeout(900); await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(400);
        await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector(sp + ' .cbg', { timeout: 15000 });
        await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]'); await p.click(row + ' >> .cbg-ex');
        const ln = (await p.locator(row).locator('.cbg-done').textContent()).trim();
        if (shots && rel) await p.screenshot({ path: shots + '/gauge-pickedup-' + w + '.png' });
        t(L + (rel ? '…its release named: "Being fixed in kit-buttons.zip"' : 'taken by a session: "Requested: the Control Board build session has it", no "Not picked up yet"'), want.test(ln) && !/Not picked up yet/.test(ln), ln);
      }
      { regauge(gj => { delete gj.pickups; });
        await p.goto(home); await p.waitForTimeout(900); await p.click('[data-cb-rail="development"]'); await p.waitForTimeout(400);
        await p.click('#cb-d-discovery .cb-gopen'); await p.waitForSelector(sp + ' .cbg', { timeout: 15000 });
        await p.click(sp + ' .cbg-lg[data-cbg-f="fail"]'); await p.click(row + ' >> .cbg-ex'); }
      if (shots) await p.screenshot({ path: shots + '/gauge-notpicked-' + w + '.png' });
      await p.locator(row).locator('.cbg-done [data-cbg="undo"]').click(); await p.waitForTimeout(500);
      // Approve the scope: its line in place, Undo
      await p.click(sp + ' [data-cbg="scope"]'); await p.waitForSelector(sp + ' .cbg-ac .cbg-done', { timeout: 8000 });
      t(L + 'Approve the scope → "Scope approved by …" with Undo, the button gone', /^Scope approved by /.test(await p.textContent(sp + ' .cbg-ac .cbg-done')) && !(await p.isVisible(sp + ' [data-cbg="scope"]')));
      await p.click(sp + ' .cbg-ac .cbg-done [data-cbg="undo"]'); await p.waitForTimeout(500);
      t(L + '…Undo puts Approve the scope back', await p.isVisible(sp + ' [data-cbg="scope"]'));
      t(L + 'the development panel: nothing wider than the window', await noSide(p));
      await p.click(sp + ' [data-cb-spx]');
      // a runtime gauge: Search finds what they came for
      await p.click('[data-cb-rail="deliver"]'); await p.waitForTimeout(400);
      await p.click('#cb-g-o-search .cb-gopen'); await p.waitForTimeout(400);
      const rs = '#cb-sp-o-search-snap';
      await p.waitForSelector(rs + ' [data-cb-gd][data-loaded]', { timeout: 15000 }).catch(() => {});
      if (shots) await p.screenshot({ path: shots + '/gauge-runtime-' + w + '.png' });
      t(L + 'runtime: What moves it, its setup in words (four rows)', /What moves it: \d of 4 setup steps done/.test(await p.textContent(rs + ' .cbg-top')) && (await p.$$eval(rs + ' .cbg-items .cbg-it', a => a.length)) === 4);
      t(L + 'runtime: one filled button among the cards, Approve the target with today\'s evidence', (await p.$$eval(rs + ' .cbg-ac .cbg-b:not(.cbg-o)', a => a.map(x => x.textContent))).join('|') === 'Approve the target' && /Today: /.test(await p.textContent(rs + ' .cbg-ac-sign')));
      await p.click(rs + ' [data-cbg-to] >> nth=0'); await p.waitForTimeout(400);
      t(L + 'runtime: a setup row\'s link brings its card into view, flashed', (await p.$$eval(rs + ' .cbg-ac.cbg-flash', a => a.length)) === 1);
      t(L + 'runtime: its needs folded to one line', /What it needs: \d+ needs?, \d+ sources?/.test(await p.textContent(rs + ' details.cbg-nd summary')) && !(await p.getAttribute(rs + ' details.cbg-nd', 'open')));
      t(L + 'runtime: nothing inert, no red, nothing wider than the window', !(await inert(p, rs)).length && (await red(p, rs)) === 0 && await noSide(p), await inert(p, rs));
      t(L + 'no page error', !errs.length, errs);
      await ctx.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
