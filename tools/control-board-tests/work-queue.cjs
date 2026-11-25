// The Work queue on Development (Jason, 2026-11-24: "Make a gauge for this and have its queue from the development board. We
// need to be able to add tasks to the queue and manage them so we don't fall out of score or forget what we identified as
// needing to be done"), rendered on the bench at 1280 and 390 with its own migration's seed (handoffs/work-queue.md):
//   - its gauge first on Development: the stage, the reading ("N of M on time"), the counts, its target set by Jason;
//   - the list late first, taken rows grouped by who has them (table-standard §8), the first 10 then Show all, Why and source folded;
//   - one filled button in the view (Add a task), the target said once, a new task first and marked; each row's status line in place; Take it / Name its release as the one suggested action;
//     ✎ and History as 30px round icons with titles; ⋯ for every other move, Take greyed with the reason when it's with someone;
//   - Add a task: gauge or area (a new area too), what, why, source; Cancel, ×, Esc close it; the task appears, Open, counted;
//   - one cycle each, the section read again so its counts follow: Take it → Taken; Name its release → Being fixed; Edit →
//     the new words, the old ones in its History; Withdraw with a reason → under "Done and withdrawn this week";
//   - a task whose release is live is Done by itself (board-phone-header.zip, seeded live);
//   - the filter is one dropdown button, its count the rows shown; nothing wider than the window; no page error.
// Run: node work-queue.cjs <root> [shots dir]   (it resets lv_main with the start schema and both seeds, at each width)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('./board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + __dirname + '/../release-b-tests/start-schema.sql && php ' + __dirname + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + __dirname + '/work-queue-seed.php ' + root + '/app']).toString().trim();
const port = 8930 + Math.floor(Math.random() * 25);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
const W = '#cb-wq';
const shown = p => p.$$eval(W + ' .wq-main > li', a => a.filter(x => x.getClientRects().length).map(x => x.classList.contains('wq-grp') ? 'group' : x.getAttribute('data-wq-st')));
const openGroups = p => p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; }));
const row = (p, what) => p.locator(W + ' .wq-it', { hasText: what }).first();
const rowOpen = async (p, what) => { await openGroups(p); return row(p, what); };
const settle = p => p.waitForResponse(r => r.url().includes('/admin/config/control') && r.request().method() === 'GET', { timeout: 10000 }).then(() => p.waitForTimeout(350));
const answer = async (p, v) => { await p.waitForSelector('.tv-ask .tv-ask-in'); await p.fill('.tv-ask .tv-ask-in', v); await p.click('.tv-ask .tv-ask-b.yes'); };
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      t(L + 'the migration seeds 20 tasks and is safe to run twice (+ the late test task)', seed() === '21');
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(1000);
      await view(p, 'development'); await p.waitForTimeout(400);
      t(L + 'the Work queue is first on Development, above the 16 gauges', await p.evaluate(() => { const v = document.querySelector('#cb-dev'), q = v && v.querySelector('#cb-wq'), g = v && v.querySelector('[data-copy^="Development: the"]'); return !!(q && g && (q.compareDocumentPosition(g) & 4)); }));
      if (shots) await p.locator(W).screenshot({ path: shots + '/wq-' + w + '-a.png' });
      const top = await p.textContent(W + ' .wq-read');
      t(L + 'its gauge: Audit & Review (target not set), on time out of the active tasks, one late, done this week counted', /Audit & Review/.test(top) && /19 of 20 on time/.test(top) && /1 late/.test(top) && /19 taken/.test(top) && /1 done this week/.test(top), top);
      t(L + 'its target said once: "Target: not set · proposed: nothing late", Approve the target', /Target: not set · proposed: nothing late/.test(top) && !/its target isn.t set/.test(top) && await p.isVisible(W + ' [data-cbg=target]'), top);
      t(L + 'one filled button in the view: Add a task (each next step and Approve the target outlined)', (await p.$$eval(W + ' .cbg-b:not(.cbg-o)', a => a.filter(x => x.getClientRects().length).map(x => x.textContent.trim()))).join('|') === 'Add a task');
      t(L + 'the list late first', (await shown(p))[0] === 'late', await shown(p));
      t(L + 'a task whose release is live is done by itself (board-phone-header.zip), under Done and withdrawn', /Done: board-phone-header\.zip is live/.test(await p.textContent(W + ' details.wq-closed')));
      t(L + 'taken rows grouped by who has them, after the late one: late, two groups (the phone header\'s task is done: its release is live)', (await shown(p)).join(' ') === 'late group group', await shown(p));
      const gs = await p.$$eval(W + ' .wq-grp > details > summary', a => a.map(x => x.textContent.replace(/^▾/, '').replace(/\s+/g, ' ').trim()));
      t(L + 'each group says who, how many and since when, folded: "Control Board build session · 11 taken · since …", "Orders session · 8 taken"', /^Control Board build session · 11 taken · since \w+ \d+$/.test(gs[0]) && /^Orders session · 8 taken/.test(gs[1]) && await p.$$eval(W + ' .wq-grp > details', a => a.every(d => !d.open)), gs);
      const rot = () => p.$eval(W + ' .wq-grp .wq-gc', x => getComputedStyle(x).transform);
      const closedRot = await rot();
      t(L + 'a group row shows its ▾, turned while folded', (await p.textContent(W + ' .wq-grp .wq-gc')) === '▾' && closedRot !== 'none', closedRot);
      await p.click(W + ' .wq-grp > details > summary'); await p.waitForTimeout(250);
      t(L + '…and pressed, it opens, the ▾ upright', await p.$eval(W + ' .wq-grp > details', d => d.open) && (await rot()) === 'none');
      await openGroups(p);
      t(L + 'opened, the group says its sentence once; its rows don\'t repeat it', /Each is being fixed once its release is named/.test(await p.textContent(W + ' .wq-grp .wq-gl')) && await p.$$eval(W + ' .wq-grp .wq-it', a => a.every(li => !li.querySelector('.wq-st'))));
      t(L + 'Why and source folded under each row', await p.$$eval(W + ' .wq-main .wq-it', a => a.every(li => { const d = li.querySelector('details.wq-ws'); return d && !d.open && /Why/.test(d.textContent); })));
      t(L + 'no Show all for 3 rows', !(await p.isVisible(W + ' [data-wq=all]')));
      t(L + 'the late task\'s time in its own zone, labelled (CLAUDE.md "Times where they happened")', /added Nov \d+, [\d:]+ [AP]M MST/.test(await row(p, 'Late test task').textContent()));
      const late = row(p, 'Late test task');
      t(L + 'a late row says so, with Take it as its next step', /Nobody has taken it/.test(await late.textContent()) && (await late.locator('.cbg-b').allTextContents()).join('|') === 'Take it');
      t(L + 'inline actions are 30px round icons with titles (Edit, History)', await late.evaluate(li => [...li.querySelectorAll('.cbg-ic')].every(x => { const r = x.getBoundingClientRect(); return Math.round(r.width) === 30 && Math.round(r.height) === 30 && x.title && x.getAttribute('aria-label'); }) && li.querySelectorAll('.cbg-ic').length === 2));
      const taken = await rowOpen(p, 'No Undo on any alarm');
      await taken.locator('[data-cbg-rm]').click();
      t(L + '⋯ on a taken task: Take it greyed with the reason, Move, its release, Withdraw', await taken.evaluate(li => { const m = li.querySelector('[role=menu]'); const tk = m.querySelector('[data-wq=take]'); return !m.hidden && tk.disabled && /Move it instead/.test(tk.textContent) && m.querySelectorAll('button').length === 4; }));
      await p.keyboard.press('Escape'); await p.mouse.click(5, 5);
      // the filter: one dropdown button
      await p.click(W + ' [data-wq-fb]');
      t(L + 'the filter opens as one menu with counts', await p.isVisible(W + ' .wq-fm [role=menu]') && /Late 1/.test(await p.textContent(W + ' .wq-fm [role=menu]')));
      await p.click(W + ' [data-wq-f=late]');
      const fx = await shown(p);
      t(L + 'Late shows only its rows, and the button says it and its count', fx.length === 1 && fx.every(x => x === 'late') && /Late 1/.test(await p.textContent(W + ' [data-wq-fb]')), fx);
      await p.click(W + ' [data-wq-fb]'); await p.keyboard.press('Escape');
      t(L + 'Esc closes the filter', !(await p.isVisible(W + ' .wq-fm [role=menu]')));
      await p.click(W + ' [data-wq-fb]'); await p.click(W + ' [data-wq-f=all]');
      // Add a task
      await p.click(W + ' [data-wq=add]');
      t(L + 'Add a task opens its form: gauge or area, its type (Piece 2, Work by default), what, why, source', await p.isVisible(W + ' [data-wq-form]') && (await p.$$eval(W + ' [data-wq-form] [name]', a => a.map(x => x.name).join(','))).replace(',ticket', '') === 'gauge,area,what,why,source');   // Type (ticket) since Piece 2's P2-1
      await p.keyboard.press('Escape');
      t(L + 'Esc closes it, Add a task back', !(await p.isVisible(W + ' [data-wq-form]')) && await p.isVisible(W + ' [data-wq=add]'));
      await p.click(W + ' [data-wq=add]'); await p.click(W + ' [data-wq-form] .wq-x');
      t(L + '× closes it', !(await p.isVisible(W + ' [data-wq-form]')));
      await p.click(W + ' [data-wq=add]');
      await p.selectOption(W + ' [data-wq-form] select', 'area:new');
      t(L + 'A new area… asks its name', await p.isVisible(W + ' [data-wq-form] [name=area]'));
      await p.fill(W + ' [data-wq-form] [name=area]', 'Market place');
      await p.fill(W + ' [data-wq-form] [name=what]', 'Test task from the board');
      await p.fill(W + ' [data-wq-form] [name=why]', 'So it is not forgotten');
      await p.fill(W + ' [data-wq-form] [name=source]', 'work-queue.cjs');
      t(L + 'what and why carry spell check', await p.$$eval(W + ' [data-wq-form] [data-spell]', a => a.map(x => x.name).join(',')) === 'area,what,why');
      await Promise.all([settle(p), p.click(W + ' [data-wq-form] [type=submit]')]);
      const nw = row(p, 'Test task from the board');
      t(L + 'the new task is first, marked, Open, in its new area, with its why and source', await nw.isVisible() && await nw.evaluate(li => li.classList.contains('wq-new') && li.parentNode.firstElementChild === li) && await nw.getAttribute('data-wq-st') === 'open' && /Market place/.test(await nw.textContent()) && /So it is not forgotten/.test(await nw.textContent()) && /work-queue\.cjs/.test(await nw.textContent()));
      t(L + '…and counted: 1 open', /1 open/.test(await p.textContent(W + ' .wq-read')));
      if (shots) await p.locator(W).screenshot({ path: shots + '/wq-' + w + '-b.png' });
      // the same task added again: the one task stands, and it says so
      execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-e', "UPDATE control_gauge_tasks SET at = at - INTERVAL 1 MINUTE WHERE what = 'Test task from the board'"]);
      await p.click(W + ' [data-wq=add]'); await p.selectOption(W + ' [data-wq-form] select', 'area:market-place');
      await p.fill(W + ' [data-wq-form] [name=what]', 'Test task from the board'); await p.fill(W + ' [data-wq-form] [name=why]', 'Again');
      await Promise.all([settle(p), p.click(W + ' [data-wq-form] [type=submit]')]);
      t(L + 'the same task added again: "already in the queue", still one row', /already in the queue as task #\d+/.test(await p.textContent('body')) && await p.locator(W + ' .wq-it', { hasText: 'Test task from the board' }).count() === 1);
      // Take it → Taken
      await Promise.all([settle(p), nw.locator('[data-wq=take].cbg-b').click()]);
      await openGroups(p);
      t(L + 'Take it: Taken, in Jason (test)\'s group, Name its release now its action', await row(p, 'Test task from the board').getAttribute('data-wq-st') === 'taken' && await row(p, 'Test task from the board').evaluate(li => li.closest('.wq-grp') && li.closest('.wq-grp').getAttribute('data-wq-grp') === 'Jason (test)') && await row(p, 'Test task from the board').locator('.cbg-b').first().textContent() === 'Name its release');
      // Edit → new words; History keeps the old
      await row(p, 'Test task from the board').locator('[data-wq=edit]').click();
      t(L + 'Edit opens in place, its words filled in', await row(p, 'Test task from the board').locator('.wq-edit [name=what]').inputValue() === 'Test task from the board');
      await row(p, 'Test task from the board').locator('.wq-edit [name=what]').fill('Test task, reworded');
      await Promise.all([settle(p), row(p, 'Test task from the board').locator('.wq-edit [type=submit]').click()]);
      await openGroups(p); const rw = row(p, 'Test task, reworded');
      t(L + 'Saved: the row has the new words', await rw.count() === 1);
      await rw.locator('[data-wq=hist]').click(); await rw.locator('.wq-hist').waitFor();
      const hist = await rw.locator('.wq-hist').textContent();
      t(L + 'History: added, taken, and the old words → new', /added to the Work queue/.test(hist) && /taken by Jason \(test\)/.test(hist) && /"Test task from the board" → "Test task, reworded"/.test(hist), hist);
      if (shots) await p.locator(W).screenshot({ path: shots + '/wq-' + w + '-c.png' });
      // Name its release → Being fixed
      await rw.locator('.cbg-b', { hasText: 'Name its release' }).click(); await answer(p, 'work-queue.zip'); await settle(p);
      t(L + 'Name its release: Being fixed in work-queue.zip, done by itself when it\'s live', await row(p, 'Test task, reworded').getAttribute('data-wq-st') === 'fixing' && /Being fixed in work-queue\.zip/.test(await row(p, 'Test task, reworded').textContent()));
      // Move to a session
      await row(p, 'Test task, reworded').locator('[data-cbg-rm]').click(); await row(p, 'Test task, reworded').locator('[data-wq=move]').click();
      await answer(p, 'Orders session'); await settle(p);
      t(L + 'Move to a session: with Orders session', /by Orders session/.test(await row(p, 'Test task, reworded').textContent()));
      // Withdraw with a reason
      await row(p, 'Late test task').locator('[data-cbg-rm]').click(); await row(p, 'Late test task').locator('[data-wq=withdraw]').click();
      await answer(p, 'A test, done'); await settle(p);
      const cl = await p.textContent(W + ' details.wq-closed');
      t(L + 'Withdraw: under Done and withdrawn, with its reason; no late left', /Withdrawn: A test, done/.test(cl) && /0 late/.test(await p.textContent(W + ' .wq-read')));
      t(L + 'a withdrawn task has no Edit or ⋯ (History only)', await p.$$eval(W + ' details.wq-closed .wq-withdrawn', a => a.every(li => !li.querySelector('[data-wq=edit]') && !li.querySelector('[data-cbg-rm]') && li.querySelector('[data-wq=hist]'))));
      // Approve the target (Executive): Implementation (its alarm isn't wired), the reading counted from now
      await p.click(W + ' [data-cbg=target]'); await p.waitForTimeout(400);
      await p.reload(); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(300);
      t(L + 'once its target is approved, nothing late: Implementation, "its alarm isn\'t wired"', /Implementation/.test(await p.textContent(W + ' .wq-read')) && /alarm isn.t wired/.test(await p.textContent(W + ' .wq-read')), await p.textContent(W + ' .wq-read'));
      execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-e', "INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source) SELECT UTC_TIMESTAMP(), '', 'area:orders', CONCAT('Paging task ', n), 'fix', CONCAT('Paging task ', n), 'task', '', 'x', 1, 'paging', '' FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) k"]);
      await p.reload(); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(300);
      const nTop = await p.$$eval(W + ' .wq-main > li', a => a.length);
      t(L + 'over 10 rows: the first 10 (a group counts as one), then Show all', (await shown(p)).length === 10 && (await p.textContent(W + ' [data-wq=all]')).trim() === 'Show ' + (nTop - 10) + ' more', [nTop, await shown(p)]);
      await p.click(W + ' [data-wq=add]'); await p.selectOption(W + ' [data-wq-form] select', 'area:orders');
      await p.fill(W + ' [data-wq-form] [name=what]', 'Added with Show all folded'); await p.fill(W + ' [data-wq-form] [name=why]', 'Paging');
      await Promise.all([settle(p), p.click(W + ' [data-wq-form] [type=submit]')]);
      const fr = await p.$eval(W + ' .wq-main > li', li => [li.classList.contains('wq-new'), li.textContent.includes('Added with Show all folded')]);
      t(L + 'after an add: still the first 10 and Show all, the new task first and marked', fr[0] && fr[1] && (await shown(p)).length === 10 && await p.isVisible(W + ' [data-wq=all]'), [fr, (await shown(p)).length]);
      if (shots) await p.locator(W).screenshot({ path: shots + '/wq-' + w + '-d.png' });
      t(L + 'nothing wider than the window', await noSide(p));
      t(L + 'no page error', !errs.length, errs);
      await ctx.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
