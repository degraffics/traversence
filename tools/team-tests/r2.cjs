// decisions/0180 R2 (swimlanes and locks), rendered at 1280 and 390 on the bench (board-router.php, as tickets.cjs): each row
// says its lane (the owning middle manager) as a quiet tag; the one filter button has Lane: … with counts; ⋯ Hand off to
// another lane… for the Founder: the lanes to choose from (never its own), then the note, then the row in its new lane with
// the note shown and the filter's counts moved; for a staff member the item is greyed with who may; nothing wider than the
// window; no page error.
// Run: node r2.cjs <root> [shots dir]   (it resets lv_main at each width)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('../control-board-tests/board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const T = __dirname, CB = path.join(__dirname, '../control-board-tests');
const db = (sql) => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', 'lv_main', '-e', sql]).toString().trim();
const seed = () => { execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + T + '/../release-b-tests/start-schema.sql && php ' + CB + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + CB + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + T + '/tickets-seed.php ' + root + '/app >/dev/null']);
  execFileSync('php', [path.join(T, 'run-migration.php'), root + '/app', root + '/app/migrations/2026-11-26_0180_r2_lanes.sql']);
  db("INSERT INTO control_gauge_tasks (at, gauge, item, kind, what, why, ticket, stage) VALUES (UTC_TIMESTAMP(), 'area:backlog', 'A renewal posts twice', 'fix', 'A renewal posts twice', 'app/lib/Billing.php charges a subscription twice', 'work', 'planning')");
  return db("SELECT MAX(id) FROM control_gauge_tasks"); };
const port = 8935 + Math.floor(Math.random() * 20);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root, FOUNDER: '1' }, stdio: 'ignore' });   // the Founder
const srv2 = spawn('php', ['-S', '127.0.0.1:' + (port + 40), path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });   // a staff member
const W = '#cb-wq';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
const open = async (p, base) => { await p.goto(base + '/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400); await p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; })); };
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; const id = seed();
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await open(p, 'http://127.0.0.1:' + port);
      const row = W + ' #wq-' + id;
      const chip = await p.$eval(row + ' .wq-lane', x => ({ t: x.textContent.trim(), title: x.title, bg: getComputedStyle(x).backgroundColor }));
      t(L + 'the row says its lane: billing is the Accounting Manager\'s', /Accounting Manager/.test(chip.t) && /decisions\/0180 §5/.test(chip.title), chip);
      t(L + '… as a quiet tag (no fill, the meta colour), beside its type', chip.bg === 'rgba(0, 0, 0, 0)' || chip.bg === 'transparent', chip.bg);
      await p.click(W + ' [data-wq-fb]');
      const items = await p.$$eval(W + ' [data-wq-f^="lane:"]', a => a.map(x => x.textContent.replace(/\s+/g, ' ').trim()));
      t(L + 'the one filter button has the seven lanes, each with its count', items.length === 7 && items.some(x => /^Lane: Accounting Manager [1-9]/.test(x)), items);
      await p.click(W + ' [data-wq-f="lane:accounting-manager"]'); await p.waitForTimeout(200);
      const shown = await p.$$eval(W + ' .wq-main .wq-it', a => a.filter(x => x.getClientRects().length).map(x => x.getAttribute('data-wq-lane')));
      t(L + 'filtered to Accounting\'s lane: only its rows', shown.length >= 1 && shown.every(x => x === 'accounting-manager'), shown);
      await p.click(W + ' [data-wq-fb]'); await p.click(W + ' [data-wq-f="all"]'); await p.waitForTimeout(200);
      // Hand off, as the Founder
      await p.click(row + ' [data-cbg-rm]'); const hb = row + ' [data-wq="handoff"]';
      t(L + 'the Founder: ⋯ Hand off to another lane…, enabled', await p.$eval(hb, x => !x.disabled && /Hand off to another lane/.test(x.textContent)));
      await p.click(hb); await p.waitForSelector('.tv-ask .tv-ch');
      const ch = await p.$$eval('.tv-ask .tv-ch input', a => a.map(x => x.value));
      t(L + 'the lanes to choose from: the other six, never its own', ch.length === 6 && !ch.includes('accounting-manager'), ch);
      await p.check('.tv-ask .tv-ch input[value="customer-service-manager"]'); await p.click('.tv-ask .tv-ask-b.yes'); await p.waitForTimeout(300);
      await p.fill('.tv-ask textarea, .tv-ask input[type=text]', 'The order this renewal belongs to needs its page checked.');
      await Promise.all([p.waitForResponse(r => r.url().includes('/admin/api/config/control') && r.request().method() === 'POST', { timeout: 10000 }), p.click('.tv-ask .tv-ask-b.yes')]);
      await p.waitForTimeout(1500); await p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; }));
      const after = await p.$eval(row, li => ({ lane: li.getAttribute('data-wq-lane'), chip: li.querySelector('.wq-lane').textContent, note: li.textContent }));
      t(L + 'handed off: the row in Customer Service\'s lane, its chip and the note shown', after.lane === 'customer-service-manager' && /Customer Service Manager/.test(after.chip) && /Handed off:.*needs its page checked/.test(after.note), after);
      t(L + '… stored, and in the History (the logbook)', db('SELECT lane FROM control_gauge_tasks WHERE id = ' + id) === 'customer-service-manager' && +db("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Task #" + id + " handed off from %'") === 1);
      await p.click(W + ' [data-wq-fb]'); const cs = await p.$eval(W + ' [data-wq-f="lane:customer-service-manager"]', x => x.textContent.replace(/\s+/g, ' ').trim()); await p.keyboard.press('Escape');
      t(L + 'the filter\'s counts moved with it', /^Lane: Customer Service Manager [1-9]/.test(cs), cs);
      t(L + 'nothing wider than the window', await noSide(p));
      if (shots) await p.screenshot({ path: path.join(shots, 'r2-' + w + '.png'), fullPage: false });
      // a staff member: greyed with who may
      const p2 = await ctx.newPage(); await open(p2, 'http://127.0.0.1:' + (port + 40));
      await p2.click(row + ' [data-cbg-rm]');
      const g = await p2.$eval(row + ' [data-wq="handoff"]', x => ({ d: x.disabled, why: x.title }));
      t(L + 'a staff member: Hand off greyed, saying who may (the lane\'s manager, an executive above it, the Founder)', g.d && /lane manager/.test(g.why) && /Founder/.test(g.why), g);
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + String(e).split('\n')[0]); }
  await b.close(); srv.kill(); srv2.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
