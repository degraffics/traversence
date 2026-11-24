// The decisions attached to a Work queue task (release task-adrs; Jason, 2026-11-24: "need task to show what ADR is attached to it
// or have the ability to look and assign adrs"), rendered at 1280 and 390: Attach a decision finds them in the admin's own list
// (DocsRepo::all(), System Configuration → Decisions) by number or title; each attached one shows its number and title and opens
// on the Decisions page beside the board; ✎ detaches, with History; the row names them. Run: node task-adrs.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('./board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const sql = q => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-N', '-e', q]).toString().trim();
const H = __dirname + '/../../handoffs/';
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + __dirname + '/../release-b-tests/start-schema.sql && php ' + __dirname + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + __dirname + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + __dirname + '/work-queue-seed.php ' + root + '/app '
  + H + 'task-scope/build/app/migrations/2026-11-24_task_scope.sql >/dev/null && php ' + __dirname + '/work-queue-seed.php ' + root + '/app ' + H + 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql']).toString().trim();
const port = 8875 + Math.floor(Math.random() * 25);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
const P = '#cb-sp-wq';
const answered = p => p.waitForResponse(r => r.url().includes('wqtask=') && r.request().method() === 'GET', { timeout: 10000 }).then(() => p.waitForTimeout(300));
const found = p => p.waitForResponse(r => r.url().includes('/admin/api/config/control') && r.request().method() === 'POST', { timeout: 10000 }).then(() => p.waitForTimeout(200));
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; seed();
      t(L + 'the migration makes the decisions table, safe to run twice', sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_task_adrs'") === '1');
      const id = sql("SELECT id FROM control_gauge_tasks WHERE what = 'Late test task' ORDER BY id LIMIT 1");
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(300);
      await Promise.all([answered(p), p.click('#wq-' + id + ' [data-wq=page]')]);
      t(L + 'Decisions on the page: none attached yet, Attach a decision, no ✎ while there\'s nothing to edit', /none attached yet/.test(await p.textContent(P + ' .wqp-body')) && await p.isVisible(P + ' [data-wqp=adradd]') && !(await p.$(P + ' [data-wqp=adredit]')));
      await Promise.all([found(p), p.click(P + ' [data-wqp=adradd]')]);
      t(L + 'Attach a decision: a search box over the admin\'s decisions, listed before typing', await p.isVisible(P + ' [data-wqp-aform] [name=q]') && (await p.$$eval(P + ' .wqp-ares [data-wqp=adrpick]', a => a.length)) > 0);
      await Promise.all([found(p), p.fill(P + ' [data-wqp-aform] [name=q]', '0175')]);
      const r175 = await p.$$eval(P + ' .wqp-ares [data-wqp=adrpick]', a => a.map(x => x.textContent));
      t(L + 'found by number: 0175 and its title, the number said once', r175.length === 1 && /^0175 · (?!0175)/.test(r175[0]), r175);
      if (shots) await p.locator(P).screenshot({ path: shots + '/ta-' + w + '-a.png' });
      await Promise.all([answered(p), p.click(P + ' .wqp-ares [data-adr="0175"]')]);
      t(L + 'attached: 0175 with its title, a link to the Decisions page opening beside the board', (await p.getAttribute(P + ' .wqp-adrs li[data-adr="0175"] a', 'href')) === '/admin/config/decisions?d=0175' && await p.getAttribute(P + ' .wqp-adrs li[data-adr="0175"] a', 'data-peek-frame') !== null && /1 attached/.test(await p.textContent(P + ' .wqp-body')));
      const word = (sql("SELECT 1") && require('fs').readFileSync(root + '/app/data/decisions-index.php', 'utf8').match(/\['0169', '([^']+)'/) || [])[1] || 'App';
      await Promise.all([found(p), p.click(P + ' [data-wqp=adradd]')]);
      await Promise.all([found(p), p.fill(P + ' [data-wqp-aform] [name=q]', word.split(/\s+/)[0].toLowerCase())]);
      t(L + 'found by a word in its title ("' + word.split(/\s+/)[0] + '"): 0169 among them', await p.$(P + ' .wqp-ares [data-adr="0169"]') !== null);
      await Promise.all([answered(p), p.click(P + ' .wqp-ares [data-adr="0169"]')]);
      await Promise.all([found(p), p.click(P + ' [data-wqp=adradd]')]); await Promise.all([found(p), p.fill(P + ' [data-wqp-aform] [name=q]', '0175')]);
      t(L + 'one already attached is greyed, saying so', await p.isDisabled(P + ' .wqp-ares [data-adr="0175"]') && /attached/.test(await p.textContent(P + ' .wqp-ares')));
      await Promise.all([found(p), p.fill(P + ' [data-wqp-aform] [name=q]', 'zzzz-no-such')]);
      t(L + 'nothing found says what to try', /No decision matches/.test(await p.textContent(P + ' .wqp-ares')));
      await p.click(P + ' [data-wqp-aform] [data-wqp=acancel]');
      t(L + '× closes the finder, Attach back', !(await p.isVisible(P + ' [data-wqp-aform]')) && await p.isVisible(P + ' [data-wqp=adradd]'));
      // ✎: detach
      await p.click(P + ' [data-wqp=adredit]');
      t(L + '✎ lists the attached ones, ticked', (await p.$$eval(P + ' .wqp-ae input[name=keep]:checked', a => a.map(x => x.value))).join(',') === '0169,0175');
      await p.uncheck(P + ' .wqp-ae input[value="0169"]'); await Promise.all([answered(p), p.click(P + ' .wqp-ae [type=submit]')]);
      t(L + 'saved: 0169 detached, 0175 kept', (await p.$$eval(P + ' .wqp-adrs li', a => a.map(x => x.getAttribute('data-adr')))).join(',') === '0175');
      if (shots) await p.locator(P).screenshot({ path: shots + '/ta-' + w + '-b.png' });
      await p.click(P + ' [data-wqp=hist]'); await p.waitForSelector(P + ' .wq-hist');
      const h = await p.textContent(P + ' .wq-hist');
      t(L + 'History: 0175 and 0169 attached, 0169 detached', /decision 0175 attached/.test(h) && /decision 0169 attached/.test(h) && /decision 0169 detached/.test(h), h);
      t(L + 'the row names its decisions', /decisions 0175/.test(await p.textContent('#wq-' + id + ' .wq-meta')));
      t(L + 'kept for the record: the detached row stays in the table', sql("SELECT COUNT(*) FROM control_gauge_task_adrs WHERE adr = '0169' AND removed_at IS NOT NULL") === '1');
      t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
      t(L + 'no page error', !errs.length, errs);
      await ctx.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
