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
const db = sql => execFileSync('mariadb', ['--default-character-set=utf8mb4', '-utv', '-ptv', '-h127.0.0.1', '-N', 'lv_main', '-e', sql]).toString().trim();
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
      const [work, res, work2, flagged] = seed(); t(L + 'three runs made on staging', db('SELECT COUNT(*) FROM team_runs') === '3');
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto('http://127.0.0.1:' + port + '/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
      t(L + 'the queue says what agents suggested: "1 suggestion" on the Work ticket', /1 suggestion/.test(await p.textContent(W + ' #wq-' + work + ' .wq-ag')));
      await p.click(W + ' [data-wq-fb]'); const fi = await p.$$eval(W + ' [data-wq-f]', a => a.map(x => x.textContent.replace(/\s+/g, ' ').trim()));
      t(L + 'the filter counts them: "Has suggestions 2", "Found by agents"', fi.includes('Has suggestions 2') && fi.some(x => /^Found by agents \d+$/.test(x)), fi);
      await p.click(W + ' [data-wq-f="agent:suggest"]'); await p.waitForTimeout(200);
      t(L + 'filtered to Has suggestions: the two rows', await p.$$eval(W + ' .wq-main .wq-it', a => a.filter(x => x.getClientRects().length).length) === 2);
      await p.click(W + ' [data-wq-fb]'); await p.click(W + ' [data-wq-f="all"]'); await p.waitForTimeout(200);
      // at Planning: the Project Manager's proposal, accepted
      await openTask(p, work);
      const pr0 = await p.textContent(P + ' .wqp-runs');
      t(L + 'at Planning the Project Manager only proposes: "Proposed: ready for Building", with Accept and Dismiss', /Proposed: ready for Building/.test(pr0) && await p.isVisible(P + ' [data-wqp=runaccept]') && !(await p.$(P + ' [data-wqp=runundo]')), pr0);
      if (shots) await p.screenshot({ path: shots + '/runs-gate-' + w + '.png' });
      await press(p, P + ' [data-wqp=runaccept]');
      t(L + 'Accept: moved to Building, as the person who accepted', await p.$eval(P + ' .wqp-strip [aria-current=step]', x => x.textContent) === 'Building' && /Accepted by Jason \(test\)/.test(await p.textContent(P + ' .wqp-runs')));
      await p.click(P + ' .cb-spx'); await p.waitForTimeout(300);
      // at Building (the seed raised then lowered the ceiling): a move with Undo
      await openTask(p, work2);
      const run = await p.textContent(P + ' .wqp-runs');
      t(L + 'a move the Project Manager made at Building: one line with Undo', /system · Project Manager/.test(run) && /Moved to Building/.test(run) && await p.isVisible(P + ' [data-wqp=runundo]'), run);
      t(L + 'Undo says what it undoes', /Moves it back to Planning/.test(await p.getAttribute(P + ' [data-wqp=runundo]', 'title')));
      if (shots) await p.screenshot({ path: shots + '/runs-work-' + w + '.png' });
      await press(p, P + ' [data-wqp=runundo]');
      t(L + 'Undo pressed: back to Planning', await p.$eval(P + ' .wqp-strip [aria-current=step]', x => x.textContent) === 'Planning');
      t(L + 'the run says it\'s undone, by whom; no Undo again', /Undone by Jason \(test\)/.test(await p.textContent(P + ' .wqp-runs')) && !(await p.$(P + ' [data-wqp=runundo]')));
      await p.click(P + ' .cb-spx'); await p.waitForTimeout(300);
      // Give it to the Team: in ⋯, the Architect greyed at Planning
      await p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; }));
      const items = await p.$$eval(W + ' #wq-' + work2 + ' [data-wq="team"]', a => a.map(x => [x.textContent, x.disabled]));
      t(L + '⋯ offers "Give it to the Team: <role>" for each working role; the System Architect greyed at Planning', items.length === 3 && items.some(x => /System Architect/.test(x[0]) && x[1] && /Waits for agents to work past Planning/.test(x[0])) && items.some(x => /Executive Translator/.test(x[0]) && !x[1]), items);
      await p.click(W + ' #wq-' + work2 + ' [data-cbg-rm]'); await p.click(W + ' #wq-' + work2 + ' [data-wq="team"][data-owner="system · Executive Translator & Briefing Lead"]'); await p.waitForTimeout(1200);
      t(L + 'Give it to the Team: the task is with the Translator', db('SELECT owner FROM control_gauge_tasks WHERE id = ' + work2) === 'system · Executive Translator & Briefing Lead');
      // the Translator's proposal on the brief
      await openTask(p, res);
      const pr = await p.textContent(P + ' .wqp-runs');
      t(L + 'the Translator\'s proposal: its words, Accept and Dismiss', /Proposed 1 part of its brief: why it needs you/.test(pr) && /Founder ruling/.test(pr) && await p.isVisible(P + ' [data-wqp=runaccept]') && await p.isVisible(P + ' [data-wqp=rundismiss]'), pr);
      t(L + 'until accepted, the brief\'s part is empty', /Not set yet/.test(await p.textContent(P + ' [data-wqp-f="why_you"]')));
      if (shots) await p.screenshot({ path: shots + '/runs-proposal-' + w + '.png' });
      await press(p, P + ' [data-wqp=runaccept]');
      t(L + 'Accept: the brief\'s part filled', /Founder ruling/.test(await p.textContent(P + ' [data-wqp-f="why_you"]')));
      t(L + '… said as accepted, by whom; no buttons left', /Accepted by Jason \(test\)/.test(await p.textContent(P + ' .wqp-runs')) && !(await p.$(P + ' [data-wqp=runaccept]')));
      if (+flagged) {   // P2-4: words that suggest a high-risk territory, checked by a person
        await p.goto('http://127.0.0.1:' + port + '/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
        t(L + 'the flagged row says "Words suggest a high-risk territory: a person checks"', /Words suggest a high-risk territory: a person checks/.test(await p.textContent(W + ' #wq-' + flagged + ' .wq-ag')));
        await p.click(W + ' [data-wq-fb]'); const fl = await p.$$eval(W + ' [data-wq-f]', a => a.map(x => x.textContent.replace(/\s+/g, ' ').trim())); await p.keyboard.press('Escape');
        t(L + 'the filter counts it: "Words suggest a high-risk territory 1"', fl.includes('Words suggest a high-risk territory 1'), fl);
        await openTask(p, flagged);
        t(L + 'its page says so, with It\'s Tier 2 and It\'s a Founder ruling: open its Resolution', /Words suggest a high-risk territory \(Money,/.test(await p.textContent(P + ' .wqp-flag')) && await p.isVisible(P + ' [data-wqp=flag][data-v=tier2]') && /open its Resolution/.test(await p.textContent(P + ' [data-wqp=flag][data-v=tier5]')));
        if (shots) await p.screenshot({ path: shots + '/flag-' + w + '.png' });
        await press(p, P + ' [data-wqp=flag][data-v=tier5]');
        t(L + 'It\'s a Founder ruling pressed: the flag line gone, its Resolution opened and named', !(await p.$(P + ' .wqp-flag')) && db("SELECT COUNT(*) FROM control_gauge_tasks WHERE parent_id = " + flagged + " AND ticket = 'resolution' AND tier = 5") === '1');
      }
      t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srv.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
