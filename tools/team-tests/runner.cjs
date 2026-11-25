// The Team's runs on a ticket's page (Piece 2, P2-3; handoffs/piece2-team.md §4.2, §5), rendered at 1280 and 390 through
// board-router.php over tickets.cjs's seed and runner-seed.php (two runs as staging makes them): the Project Manager's move
// as one line with Undo, pressed: back to Planning, said as undone, no Undo again; the Translator's proposal with Accept and
// Dismiss, Accept pressed: the brief's part filled, said as accepted; nothing wider than the window; no page error.
// Run: node runner.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('../control-board-tests/board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const T = __dirname, CB = path.join(__dirname, '../control-board-tests');
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + T + '/../release-b-tests/start-schema.sql && php ' + CB + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + CB + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + T + '/tickets-seed.php ' + root + '/app >/dev/null && php ' + T + '/runner-seed.php ' + root + '/app']).toString().trim().split(' ');
const db = sql => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', 'lv_main', '-e', sql]).toString().trim();
const port = 8995 + Math.floor(Math.random() * 4);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const W = '#cb-wq', P = '#cb-sp-wq';
const openTask = async (p, id) => { await p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; }));
  await p.click(W + ' #wq-' + id + ' .wq-go'); await p.waitForSelector(P + ' .wqp[data-wqp-id="' + id + '"]', { timeout: 8000 }); await p.waitForTimeout(300); };
const press = async (p, sel) => { await Promise.all([p.waitForResponse(r => r.url().includes('wqtask'), { timeout: 10000 }), p.click(sel)]); await p.waitForTimeout(400); };
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      const [work, res] = seed(); t(L + 'two runs made on staging', db('SELECT COUNT(*) FROM team_runs') === '2');
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto('http://127.0.0.1:' + port + '/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
      await openTask(p, work);
      const run = await p.textContent(P + ' .wqp-runs');
      t(L + 'the move as one line: who, what, when, with Undo', /system · Project Manager/.test(run) && /Moved to Building/.test(run) && /UTC|M[SD]T|[A-Z]{2,4}/.test(run) && await p.isVisible(P + ' [data-wqp=runundo]'), run);
      t(L + 'Undo says what it undoes', /Moves it back to Planning/.test(await p.getAttribute(P + ' [data-wqp=runundo]', 'title')));
      if (shots) await p.screenshot({ path: shots + '/runs-work-' + w + '.png' });
      await press(p, P + ' [data-wqp=runundo]');
      t(L + 'Undo pressed: back to Planning', await p.$eval(P + ' .wqp-strip [aria-current=step]', x => x.textContent) === 'Planning');
      t(L + 'the run says it\'s undone, by whom; no Undo again', /Undone by Jason \(test\)/.test(await p.textContent(P + ' .wqp-runs')) && !(await p.$(P + ' [data-wqp=runundo]')));
      await p.click(P + ' .cb-spx'); await p.waitForTimeout(300);
      await openTask(p, res);
      const pr = await p.textContent(P + ' .wqp-runs');
      t(L + 'the Translator\'s proposal: its words, Accept and Dismiss', /Proposed 1 part of its brief: why it needs you/.test(pr) && /Tier 3 line/.test(pr) && await p.isVisible(P + ' [data-wqp=runaccept]') && await p.isVisible(P + ' [data-wqp=rundismiss]'), pr);
      t(L + 'until accepted, the brief\'s part is empty', /Not set yet/.test(await p.textContent(P + ' [data-wqp-f="why_you"]')));
      if (shots) await p.screenshot({ path: shots + '/runs-proposal-' + w + '.png' });
      await press(p, P + ' [data-wqp=runaccept]');
      t(L + 'Accept: the brief\'s part filled', /Tier 3 line/.test(await p.textContent(P + ' [data-wqp-f="why_you"]')));
      t(L + '… said as accepted, by whom; no buttons left', /Accepted by Jason \(test\)/.test(await p.textContent(P + ' .wqp-runs')) && !(await p.$(P + ' [data-wqp=runaccept]')));
      t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srv.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
