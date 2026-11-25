// Piece 2, P2-5 (decisions/0179 rule 1; Jason's Q3 A): the daily batch at the top of the Work queue, rendered at 1280 and 390
// through board-router.php over tickets.cjs's seed, with gauges.json carrying today's batch: the strip's line, the Tier 3
// decision waiting first, each release's line; as a staff member Approve the batch greyed with why; as the Founder pressed:
// "Approved by Jason (test)", no button left, the approval recorded once for the day; nothing wider than the window; no page
// error. Run: node batch.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path'); const fs = require('fs');
const { view } = require('../control-board-tests/board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const T = __dirname, CB = path.join(__dirname, '../control-board-tests');
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + T + '/../release-b-tests/start-schema.sql && php ' + CB + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + CB + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + T + '/tickets-seed.php ' + root + '/app']).toString().trim().split(/\s+/);
const db = sql => execFileSync('mariadb', ['--default-character-set=utf8mb4', '-utv', '-ptv', '-h127.0.0.1', '-N', 'lv_main', '-e', sql]).toString().trim();
const today = new Date().toISOString().slice(0, 10);
const gfile = '/tmp/claude-0/p25-batch-gauges.json';
const g = JSON.parse(fs.readFileSync(path.join(T, '../../site-audit/results/gauges.json'), 'utf8'));
const port = 9060 + Math.floor(Math.random() * 20);
const env = { ...process.env, ROOT: root, GAUGES_FILE: gfile };
const srvS = spawn('php', ['-S', '127.0.0.1:' + port, path.join(T, 'board-router.php')], { env, stdio: 'ignore' });
const srvF = spawn('php', ['-S', '127.0.0.1:' + (port + 30), path.join(T, 'board-router.php')], { env: { ...env, FOUNDER: '1' }, stdio: 'ignore' });
const W = '#cb-wq';
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      const [res] = seed();
      fs.writeFileSync(gfile, JSON.stringify({ ...g, batch: { date: today, lines: [
        { release: 'team-runner.zip', what: 'The Team\'s runner on staging', why: 'Piece 2 (0179)', decision: '0179', checks: 'runner.php 45, runner.cjs 44 on staging', way_back: 'Roll back on the Releases page', tickets: [+res] },
        { release: 'team-audit.zip', what: 'The daily audit', why: 'Piece 2', decision: '0179', checks: 'audit.php 33', way_back: 'Roll back', tickets: [] }] }, answers: [] }));
      try { db('DELETE FROM page_cache'); } catch (x) {}
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      for (const [who, pt] of [['staff', port], ['founder', port + 30]]) {
        await p.goto('http://127.0.0.1:' + pt + '/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
        const tx = (await p.textContent(W + ' .wq-batch')).replace(/\s+/g, ' ');
        if (who === 'staff') {
          t(L + 'the strip: "Today\'s batch: 2 releases waiting for your OK · 1 decision waiting for you"', /Today's batch \([A-Z][a-z]{2} \d+\):\s*2 releases waiting for your OK · 1 decision waiting for you/.test(tx), tx.slice(0, 200));
          t(L + 'the decision first, a link to its ticket, then each release with its checks and way back', await p.$eval(W + ' .wq-batch', (el, id) => { const d = el.querySelector('.wq-bw a[href="#wq-' + id + '"]'), r = [...el.querySelectorAll('.wq-bl b')].find(x => x.textContent === 'team-runner.zip');
            return !!d && !!r && (d.compareDocumentPosition(r) & Node.DOCUMENT_POSITION_FOLLOWING) > 0; }, res) && /Checks on staging: runner\.php 45/.test(tx) && /Way back: Roll back on the Releases page/.test(tx));
          t(L + 'a staff member: Approve the batch greyed, saying only the Founder', await p.$eval(W + ' [data-wq=batchok]', x => x.disabled && /Founder/.test(x.title)));
          if (shots) await p.screenshot({ path: shots + '/batch-' + w + '.png' });
        } else {
          t(L + 'the Founder: Approve the batch open', await p.$eval(W + ' [data-wq=batchok]', x => !x.disabled));
          await p.click(W + ' [data-wq=batchok]'); await p.waitForTimeout(1200);
          t(L + 'pressed: "Approved by Jason (test)", no button left', /Approved by Jason \(test\)/.test((await p.textContent(W + ' .wq-batch')).replace(/\s+/g, ' ')) && !(await p.$(W + ' [data-wq=batchok]')));
          t(L + 'recorded once for today, with the releases', db("SELECT CONCAT(COUNT(*), '|', MAX(value)) FROM control_gauge_tasks WHERE kind = 'batch' AND gauge = 'area:batch-" + today + "'") === '1|team-runner.zip, team-audit.zip');
          if (shots) await p.screenshot({ path: shots + '/batch-approved-' + w + '.png' });
        }
      }
      // not today's batch
      fs.writeFileSync(gfile, JSON.stringify({ ...g, batch: { date: '2026-01-01', lines: [] }, answers: [] })); try { db('DELETE FROM page_cache'); } catch (x) {}
      await p.goto('http://127.0.0.1:' + port + '/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
      t(L + 'another day\'s batch: "No batch from the session manager today", the decision still listed', /No batch from the session manager today · 1 decision waiting for you/.test((await p.textContent(W + ' .wq-batch')).replace(/\s+/g, ' ')) && !(await p.$(W + ' [data-wq=batchok]')));
      t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srvS.kill(); srvF.kill(); try { fs.unlinkSync(gfile); } catch (x) {}
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
