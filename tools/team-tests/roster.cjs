// The Team page (decisions/0179's Piece 2, P2-2; handoffs/piece2-team.md §4.3), rendered on the bench at 1280 and 390 through
// router.php as the admin draws a manifest's table: the departments as group rows, closed until opened (on a phone too:
// the kit's phone cards kept a folded group's members showing), standby folded into one group, a group one row for paging;
// a role's drawer (its words, History, Permissions); Put on standby and Make active back, each through the drawer's form,
// the status and History updated in place; ×, Esc and a click outside close the drawer; no sideways scroll; no page error.
// Run: node roster.cjs <root> [shots dir]   (resets lv_main and runs the two migrations at each width)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const db = (sql) => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', '-e', sql]).toString().trim();
const seed = () => { db('DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4');
  for (const f of ['2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql']) execFileSync('bash', ['-c', 'mariadb --default-character-set=utf8mb4 -utv -ptv -h127.0.0.1 lv_main < ' + root + '/app/migrations/' + f]);
  db('USE lv_main; CREATE TABLE IF NOT EXISTS control_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT \'\', kind VARCHAR(12) NOT NULL DEFAULT \'control\', proc VARCHAR(60) NOT NULL DEFAULT \'\', what VARCHAR(400) NOT NULL DEFAULT \'\', who VARCHAR(80) NOT NULL DEFAULT \'\', why VARCHAR(400) NOT NULL DEFAULT \'\')');
  return db('SELECT COUNT(*) FROM lv_main.team_roster'); };
const port = 8960 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/team';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
const visibleRows = p => p.$$eval('.ak-t tbody tr[data-id]', a => a.filter(x => x.getClientRects().length && x.offsetHeight > 0).length);
const drawer = p => p.$('.ak-dr:not([hidden]) .ak-drc');
const openRole = async (p, id) => { await p.click('tr[data-id="' + id + '"] td.ak-tn'); await p.waitForSelector('.ak-dr .ak-drc .ak-dl', { timeout: 8000 }); await p.waitForTimeout(300); };
const drOpen = p => p.evaluate(() => { const d = document.querySelector('.ak-dr'); return !!d && d.getClientRects().length > 0 && !d.hidden && getComputedStyle(d).visibility !== 'hidden' && d.getBoundingClientRect().right > 10 && d.getBoundingClientRect().left < innerWidth - 10; });
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      t(L + 'the two migrations make 33 roles', seed() === '33');
      const pmId = db("SELECT id FROM lv_main.team_roster WHERE slug = 'project-manager'");
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForSelector('.ak-t tr.akt-grp'); await p.waitForTimeout(400);
      const groups = await p.$$eval('.ak-t tr.akt-grp', a => a.map(x => x.querySelector('b').textContent + ' ' + x.querySelector('.akt-gn').textContent));
      t(L + 'five group rows: the four departments, then standby', groups.length === 5 && /^Leadership 2/.test(groups[0]) && /^On standby/.test(groups[4]), groups);
      t(L + 'every group closed at first: no member shows', await visibleRows(p) === 0, await visibleRows(p));
      const pg = await p.innerText('.ak-list'); t(L + 'a group counts as one row for paging: one page, never "of 33"', !/of 33/.test(pg) && !/Show \d+ more/.test(pg), pg.match(/\d+–\d+ of \d+|Show \d+ more/));
      await p.click('tr.akt-grp[data-grp="dept-operations"] [data-ak-grp]'); await p.waitForTimeout(250);
      t(L + 'opening Operations shows its 3 working roles, only those', await visibleRows(p) === 3, await visibleRows(p));
      await p.click('tr.akt-grp[data-grp="dept-operations"] [data-ak-grp]'); await p.waitForTimeout(250);
      t(L + 'closing it folds them again', await visibleRows(p) === 0);
      await p.click('tr.akt-grp[data-grp="dept-operations"] [data-ak-grp]'); await p.waitForTimeout(250);
      t(L + 'no sideways scroll, groups open', await noSide(p));
      if (shots) await p.screenshot({ path: shots + '/team-' + w + '.png', fullPage: true });
      // the drawer
      await openRole(p, pmId);
      const d1 = await (await drawer(p)).textContent();
      t(L + 'the drawer: its mandate, steps, what it acts as and where that comes from', /Moves each ticket/.test(d1) && /gate, stall/.test(d1) && /Manager · Operations/.test(d1) && /what Permissions gives/.test(d1), d1.slice(0, 200));
      t(L + 'the drawer\'s moves: Edit, Put on standby, Retire it', JSON.stringify(await p.$$eval('.ak-dr .ak-actrow button', a => a.map(x => x.textContent.trim()))) === JSON.stringify(['✎ Edit its words', 'Put on standby', 'Retire it']));
      t(L + 'no History yet, said once', /Nothing yet/.test(d1));
      t(L + 'its words read as written (utf-8: "§", "·")', /piece2-team\.md §5/.test(d1) && !/Â/.test(d1));
      if (shots) await p.screenshot({ path: shots + '/team-drawer-' + w + '.png' });
      await p.click('.ak-dr .ak-x'); await p.waitForTimeout(500); t(L + '× closes the drawer', !(await drOpen(p)));
      await openRole(p, pmId); await p.keyboard.press('Escape'); await p.waitForTimeout(500); t(L + 'Esc closes the drawer', !(await drOpen(p)));
      await openRole(p, pmId); const left = await p.evaluate(() => document.querySelector('.ak-dr').getBoundingClientRect().left);
      if (left > 20) { await p.mouse.click(5, 450); await p.waitForTimeout(500); t(L + 'a click outside closes the drawer', !(await drOpen(p))); }
      else { t(L + 'the drawer fills the phone (no outside to click): × and Esc close it', left <= 20); await p.keyboard.press('Escape'); await p.waitForTimeout(500); }
      // Put on standby, then Make active: each through the drawer's form, its result in place
      await openRole(p, pmId);
      await p.click('.ak-dr .ak-actrow button[data-act="standby"]'); await p.waitForSelector('.ak-dr [data-ak-actbox] form');
      await p.fill('.ak-dr [data-ak-actbox] input[name="why"]', 'rendered test');
      await p.click('.ak-dr [data-ak-actbox] form button.ak-b.go'); await p.waitForTimeout(900);
      const d2 = await (await drawer(p)).textContent();
      t(L + 'Put on standby: the drawer says On standby and offers Make active', /On standby/.test(d2) && await p.$('.ak-dr .ak-actrow button[data-act="active"]') !== null, d2.slice(0, 160));
      t(L + 'its History has the move and the reason', /active → on standby/.test(d2) && /rendered test/.test(d2));
      t(L + 'the row says it in place in the database', db("SELECT status FROM lv_main.team_roster WHERE id = " + pmId) === 'standby');
      await p.click('.ak-dr .ak-actrow button[data-act="active"]'); await p.waitForSelector('.ak-dr [data-ak-actbox] form');
      await p.click('.ak-dr [data-ak-actbox] form button.ak-b.go'); await p.waitForTimeout(900);
      t(L + 'Make active brings it back', db("SELECT status FROM lv_main.team_roster WHERE id = " + pmId) === 'active' && /on standby → active/.test(await (await drawer(p)).textContent()));
      t(L + 'no sideways scroll with the drawer open', await noSide(p));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message); }
  await b.close(); srv.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
