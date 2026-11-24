// A Work queue task's page (release task-scope; Jason, 2026-11-24: "Each task must open and allow me to scope and set priorities
// and work that needs to be done"), rendered at 1280 and 390: the row's name opens it in the side panel; the scope's four fields,
// the priority (1–3) and the steps (see → do → confirm), each ✎ Edit with History, no Undo; the row shows the priority, the goal
// and the steps confirmed, and late/open sort by priority. Run: node task-scope.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('./board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const sql = q => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-N', '-e', q]).toString().trim();
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + __dirname + '/../release-b-tests/start-schema.sql && php ' + __dirname + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + __dirname + '/work-queue-seed.php ' + root + '/app && php ' + __dirname + '/work-queue-seed.php ' + root + '/app '
  + __dirname + '/../../handoffs/task-scope/build/app/migrations/2026-11-24_task_scope.sql']).toString().trim();
const port = 8900 + Math.floor(Math.random() * 25);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
const P = '#cb-sp-wq';
const answered = p => p.waitForResponse(r => r.url().includes('wqtask=') && r.request().method() === 'GET', { timeout: 10000 }).then(() => p.waitForTimeout(300));
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; seed();
      t(L + 'the migration adds the scope columns and the steps table, safe to run twice', sql("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME IN ('goal','in_scope','out_scope','done_when','priority')") === '5' && sql("SELECT COUNT(*) FROM control_gauge_task_steps") === '0');
      const id = sql("SELECT id FROM control_gauge_tasks WHERE what = 'Late test task' ORDER BY id LIMIT 1");
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(300);
      const nm = p.locator('#wq-' + id + ' [data-wq=page]');
      t(L + 'the task\'s name is a link-styled button that opens its page', (await nm.textContent()) === 'Late test task' && /Open task #/.test(await nm.getAttribute('title')));
      await Promise.all([answered(p), nm.click()]);
      t(L + 'its page in the side panel: the title, the state, Scope with four fields not set, Priority, Steps none yet', await p.isVisible(P) && /Late test task/.test(await p.textContent(P + ' .cb-title')) && (await p.$$eval(P + ' .wqp-f[data-wqp-f] .wqp-none', a => a.length)) === 4 && /none yet/.test(await p.textContent(P + ' .wqp-body')));
      t(L + 'every field\'s ✎ is a 30px titled icon; no Undo anywhere', await p.$$eval(P + ' .cbg-ic', a => a.length >= 6 && a.every(x => Math.round(x.getBoundingClientRect().width) === 30 && x.title)) && !/\bUndo\b/.test(await p.textContent(P)));
      if (shots) await p.locator(P).screenshot({ path: shots + '/ts-' + w + '-a.png' });
      // the goal
      await p.click(P + ' [data-wqp=edit][data-f=goal]');
      t(L + '✎ on Goal opens its field in place, with spell check', await p.isVisible(P + ' .wqp-ef textarea[data-spell]'));
      await p.fill(P + ' .wqp-ef textarea', 'Every late task taken within a day');
      await Promise.all([answered(p), p.click(P + ' .wqp-ef [type=submit]')]);
      t(L + 'saved: the goal shows in place', /Every late task taken within a day/.test(await p.textContent(P + ' [data-wqp-f=goal]')));
      await p.click(P + ' [data-wqp=edit][data-f=done_when]'); await p.fill(P + ' .wqp-ef textarea', 'Nothing in the queue is late');
      await Promise.all([answered(p), p.click(P + ' .wqp-ef [type=submit]')]);
      await p.click(P + ' [data-wqp=edit][data-f=goal]'); await p.fill(P + ' .wqp-ef textarea', 'Late tasks taken within a day');
      await Promise.all([answered(p), p.click(P + ' .wqp-ef [type=submit]')]);
      // priority
      await p.click(P + ' [data-wqp=prio]');
      t(L + 'the priority\'s choices, in place, each say what they mean', /first: the next one taken/.test(await p.textContent(P + ' .wqp-pf')) && (await p.$$eval(P + ' .wqp-pf input[type=radio]', a => a.length)) === 4);
      await p.check(P + ' .wqp-pf input[value="1"]'); await Promise.all([answered(p), p.click(P + ' .wqp-pf [type=submit]')]);
      t(L + 'priority set: Priority 1, said with its meaning', /Priority 1 · first/.test(await p.textContent(P + ' .wqp-pr')), await p.textContent(P + ' .wqp-pr'));
      // steps
      await p.click(P + ' [data-wqp=sadd]');
      await p.fill(P + ' [data-wqp-sform] [name=see]', 'The queue says 1 late'); await p.fill(P + ' [data-wqp-sform] [name=do_what]', 'Take the late task'); await p.fill(P + ' [data-wqp-sform] [name=confirm]', 'It reads Taken');
      await Promise.all([answered(p), p.click(P + ' [data-wqp-sform] [type=submit]')]);
      await p.click(P + ' [data-wqp=sadd]'); await p.fill(P + ' [data-wqp-sform] [name=do_what]', 'Name its release');
      await Promise.all([answered(p), p.click(P + ' [data-wqp-sform] [type=submit]')]);
      t(L + 'two steps, numbered, each with see, do and confirm as given', (await p.$$eval(P + ' .wqp-step .wqp-do', a => a.map(x => x.textContent))).join('|') === 'Take the late task|Name its release' && /See: The queue says 1 late/.test(await p.textContent(P + ' .wqp-step')) && /0 of 2 confirmed/.test(await p.textContent(P + ' .wqp-body')));
      await Promise.all([answered(p), p.click(P + ' .wqp-step >> nth=0 >> [data-wqp=confirm]')]);
      t(L + 'Confirm it\'s done: the step says who and when, its button gone; 1 of 2', /Confirmed by Jason \(test\)/.test(await p.textContent(P + ' .wqp-step')) && !(await p.$(P + ' .wqp-step.wqp-done [data-wqp=confirm]')) && /1 of 2 confirmed/.test(await p.textContent(P + ' .wqp-body')));
      await p.click(P + ' .wqp-step >> nth=0 >> [data-wqp=sedit]');
      t(L + 'a confirmed step\'s ✎: its words and Confirmed done, ticked', await p.isChecked(P + ' .wqp-se [name=done]'));
      await p.uncheck(P + ' .wqp-se [name=done]'); await Promise.all([answered(p), p.click(P + ' .wqp-se [type=submit]')]);
      t(L + 'corrected: not done after all (an Edit, never an Undo)', /0 of 2 confirmed/.test(await p.textContent(P + ' .wqp-body')));
      if (shots) await p.locator(P).screenshot({ path: shots + '/ts-' + w + '-b.png' });
      t(L + 'a step reads in order: See and Confirm before its press; one with neither says Not set yet', await p.$$eval(P + ' .wqp-step', a => a.length) === 2 && await p.$eval(P + ' .wqp-steps > li:last-child', li => /See: Not set yet/.test(li.textContent) && /Confirm: Not set yet/.test(li.textContent) && li.lastElementChild.previousElementSibling.matches('[data-wqp=confirm]')));
      // the task's own next step, at the top beside its state (the Design reviewer)
      t(L + 'its next step beside its state: Take it, outlined', (await p.textContent(P + ' .wqp-st [data-wqp=take]')) === 'Take it' && await p.$eval(P + ' .wqp-st [data-wqp=take]', b => b.classList.contains('cbg-o')));
      await Promise.all([answered(p), p.click(P + ' .wqp-st [data-wqp=take]')]);
      t(L + 'Take it: Taken, with Jason (test); Name its release next', /With Jason \(test\)/.test(await p.textContent(P + ' .wqp-st')) && await p.isVisible(P + ' .wqp-st [data-wqp=rel]'));
      await p.click(P + ' .wqp-st [data-wqp=rel]'); await p.fill(P + ' .wqp-rf [name=rel]', 'task-scope.zip');
      await Promise.all([answered(p), p.click(P + ' .wqp-rf [type=submit]')]);
      t(L + 'Name its release, in place: Being fixed in task-scope.zip', /Being fixed in task-scope\.zip/.test(await p.textContent(P + ' .wqp-st')));
      await p.click(P + ' [data-wqp=hist]'); await p.waitForSelector(P + ' .wq-hist');
      const h = await p.textContent(P + ' .wq-hist');
      t(L + 'History: the goal\'s old words, done when, the priority, the steps added, confirmed and corrected', /goal "Every late task taken within a day" → "Late tasks taken within a day"/.test(h) && /done when "" → "Nothing in the queue is late"/.test(h) && /priority not set → 1/.test(h) && /step 1 added/.test(h) && /confirmed done/.test(h) && /not done after all/.test(h), h);
      // the queue's row
      const row = await p.textContent('#wq-' + id);
      t(L + 'its row in the queue: P1, the goal, the steps (read again after each save)', /P1/.test(row) && /Goal: Late tasks taken within a day/.test(row) && /0 of 2 steps confirmed/.test(row), row);
      t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
      await p.keyboard.press('Escape'); await p.waitForTimeout(200);
      t(L + 'Esc closes the page', !(await p.isVisible(P)));
      // sorting by priority within open
      const a = sql("SELECT id FROM control_gauge_tasks WHERE bucket = 'task' ORDER BY id DESC LIMIT 1");
      sql("INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, priority) VALUES (UTC_TIMESTAMP(), '', 'area:orders', 'Open P3', 'fix', 'Open P3', 'task', '', 'x', 1, 'w', '', 3), (UTC_TIMESTAMP(), '', 'area:orders', 'Open P2', 'fix', 'Open P2', 'task', '', 'x', 1, 'w', '', 2), (UTC_TIMESTAMP(), '', 'area:orders', 'Open none', 'fix', 'Open none', 'task', '', 'x', 1, 'w', '', NULL)");
      await p.reload(); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(300);
      const order = await p.$$eval('#cb-wq .wq-main > li.wq-open', a => a.map(x => x.querySelector('.wq-nm').textContent));
      t(L + 'open tasks by priority: P2, P3, then not set', order.join('|') === 'Open P2|Open P3|Open none', order);
      t(L + 'no page error', !errs.length, errs);
      await ctx.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
