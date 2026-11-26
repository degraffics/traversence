// The Team page (decisions/0179's Piece 2, P2-2; handoffs/piece2-team.md §4.3), rendered on the bench at 1280 and 390 through
// router.php as the admin draws a manifest's table: the departments as group rows, closed until opened (on a phone too:
// the kit's phone cards kept a folded group's members showing), standby folded into one group, a group one row for paging;
// a role's drawer (its words, History, Permissions); Put on standby and Make active back, each through the drawer's form,
// the status and History updated in place; ×, Esc and a click outside close the drawer; no sideways scroll; no page error.
// decisions/0180 R1 (2026-11-26): the seed then R1's migration: the layers as groups, the standby roles folded (0180, "The CEO builds the team"), no Retire, the
// retired roles never shown.
// Run: node roster.cjs <root> [shots dir]   (resets lv_main and runs the migrations at each width)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const db = (sql) => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', '-e', sql]).toString().trim();
const seed = () => { db('DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4');
  for (const f of ['2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_staff_views.sql']) execFileSync('bash', ['-c', 'mariadb --default-character-set=utf8mb4 -utv -ptv -h127.0.0.1 lv_main < ' + root + '/app/migrations/' + f]);
  db('USE lv_main; CREATE TABLE IF NOT EXISTS control_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT \'\', kind VARCHAR(12) NOT NULL DEFAULT \'control\', proc VARCHAR(60) NOT NULL DEFAULT \'\', what VARCHAR(400) NOT NULL DEFAULT \'\', who VARCHAR(80) NOT NULL DEFAULT \'\', why VARCHAR(400) NOT NULL DEFAULT \'\')');
  db('USE lv_main; CREATE TABLE IF NOT EXISTS control_gauge_tasks (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, at DATETIME NULL, tz VARCHAR(64) NOT NULL DEFAULT \'\', gauge VARCHAR(60) NOT NULL DEFAULT \'\', item VARCHAR(250) NOT NULL DEFAULT \'\', kind VARCHAR(10) NOT NULL DEFAULT \'fix\', what VARCHAR(250) NOT NULL DEFAULT \'\', bucket VARCHAR(20) NOT NULL DEFAULT \'\', value VARCHAR(60) NOT NULL DEFAULT \'\', who VARCHAR(80) NOT NULL DEFAULT \'\', uid INT NOT NULL DEFAULT 0, why TEXT NULL, source VARCHAR(250) NOT NULL DEFAULT \'\', rel VARCHAR(60) NOT NULL DEFAULT \'\', tier TINYINT NULL, owner VARCHAR(80) NOT NULL DEFAULT \'\', undone_at DATETIME NULL, cleared_at DATETIME NULL)');
  db('USE lv_main; CREATE TABLE IF NOT EXISTS feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(8) NOT NULL DEFAULT \'off\', share_pct TINYINT UNSIGNED NOT NULL DEFAULT 0, killed TINYINT UNSIGNED NOT NULL DEFAULT 0, note VARCHAR(255) NOT NULL DEFAULT \'\', updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL)');
  execFileSync('php', [path.join(__dirname, 'run-migration.php'), root + '/app', root + '/app/migrations/2026-11-26_0180_tiers_roster.sql']);
  return db("SELECT COUNT(*) FROM lv_main.team_roster WHERE status <> 'inactive'"); };
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
      t(L + 'the seed and R1: 44 shown (32 roles, 12 advocates)', seed() === '44');
      const pmId = db("SELECT id FROM lv_main.team_roster WHERE slug = 'project-manager'");
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForSelector('.ak-t tr.akt-grp'); await p.waitForTimeout(400);
      const groups = await p.$$eval('.ak-t tr.akt-grp', a => a.map(x => x.querySelector('b').textContent + ' ' + x.querySelector('.akt-gn').textContent));
      t(L + 'six group rows: the layers (decisions/0180 §5), the standby roles in one group, no retired group', JSON.stringify(groups) === JSON.stringify(['Founder 1', 'Executive suite 1', 'Middle managers 2', 'Specialists 8', 'Advocates 12', 'On standby 20']), groups);
      t(L + 'no retired role anywhere on the page (Historian, Senior Designer)', !/Historian|Senior Designer|Retired/.test(await p.innerText('.ak-list')));
      t(L + 'every group closed at first: no member shows', await visibleRows(p) === 0, await visibleRows(p));
      const pg = await p.innerText('.ak-list'); t(L + 'a group counts as one row for paging: one page, never "of 44"', !/of 44/.test(pg) && !/Show \d+ more/.test(pg), pg.match(/\d+–\d+ of \d+|Show \d+ more/));
      await p.click('tr.akt-grp[data-grp="dept-managers"] [data-ak-grp]'); await p.waitForTimeout(250);
      t(L + 'opening Middle managers shows its 2 active (the Project Manager, the IT Manager), only those', await visibleRows(p) === 2, await visibleRows(p));
      await p.click('tr.akt-grp[data-grp="dept-managers"] [data-ak-grp]'); await p.waitForTimeout(250);
      t(L + 'closing it folds them again', await visibleRows(p) === 0);
      await p.click('tr.akt-grp[data-grp="dept-managers"] [data-ak-grp]'); await p.waitForTimeout(250);
      t(L + 'no sideways scroll, groups open', await noSide(p));
      if (shots) await p.screenshot({ path: shots + '/team-' + w + '.png', fullPage: true });
      // the drawer
      await openRole(p, pmId);
      const d1 = await (await drawer(p)).textContent();
      t(L + 'the drawer: its mandate, steps, what it acts as and where that comes from', /Moves each ticket/.test(d1) && (/None yet/.test(d1) || /Moves a Work ticket from Planning to Building/.test(d1)) && /Manager · Operations/.test(d1) && /what Permissions gives/.test(d1), d1.slice(0, 200));
      t(L + 'the drawer\'s moves: Edit, Put on standby (no Retire)', JSON.stringify(await p.$$eval('.ak-dr .ak-actrow button', a => a.map(x => x.textContent.trim()))) === JSON.stringify(['✎ Edit its words', 'Put on standby']));
      t(L + 'its layer, its number in decisions/0180, who it reports to', /Middle managers/.test(d1) && /Role #8/.test(d1) && /Chief Operating Officer/.test(d1), d1.slice(0, 300));
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
