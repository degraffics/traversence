// "Run a test ticket" in a Team role's drawer (TeamVet; Jason, 2026-11-28), rendered on the bench at 1280 and 390 through
// router.php, on staging (STAGING=1): the drawer shows Test ticket and AI access; Run a test ticket runs it and the result and
// its cost show in place; a role that can't run yet has the button greyed with why; no sideways scroll; no page error.
// Run: node test-ticket.cjs <root> [shots dir]   (<root>/app = the live app plus this build with the migrations, <root>/website
// = the live website; it runs test-ticket.php first, which resets lv_main)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const db = (sql) => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-N', '-e', sql]).toString().trim();
const env = { ...process.env, ROOT: root, STAGING: '1', FOUNDER: '1', DB_HOST: '127.0.0.1', DB_DATABASE: 'lv_main', DB_USERNAME: 'tv', DB_PASSWORD: 'tv' };
const port = 8990 + Math.floor(Math.random() * 30);
const home = 'http://127.0.0.1:' + port + '/admin/config/team';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
const drawer = p => p.$('.ak-dr:not([hidden]) .ak-drc');
const openAll = async p => { for (const g of await p.$$('tr.akt-grp [data-ak-grp]')) { await g.click(); await p.waitForTimeout(120); } };
const openRole = async (p, id) => { await p.click('tr[data-id="' + id + '"] td.ak-tn'); await p.waitForSelector('.ak-dr .ak-drc .ak-dl', { timeout: 8000 }); await p.waitForTimeout(300); };
(async () => {
  execFileSync('php', [path.join(__dirname, 'test-ticket.php'), root + '/app'], { env, stdio: 'ignore' });   // the bench: roster, queue, runner on
  execFileSync('php', [path.join(__dirname, 'run-migration.php'), root + '/app', root + '/app/migrations/2026-11-26_0180_tiers_roster.sql'], { env, stdio: 'ignore' });   // decisions/0180's layers, as live
  const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'router.php')], { env, stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      const arId = db("SELECT id FROM lv_main.team_roster WHERE slug = 'system-architect'"), qaId = db("SELECT id FROM lv_main.team_roster WHERE slug = 'builder'");
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForSelector('.ak-t tr.akt-grp'); await p.waitForTimeout(400); await openAll(p);
      await openRole(p, arId);
      const d1 = await (await drawer(p)).textContent();
      t(L + 'the drawer shows Test ticket, with its last result and cost', /Test ticket/.test(d1) && /Passed: /.test(d1) && /Cost \$0\.00/.test(d1), d1.slice(0, 300));
      t(L + 'the drawer shows AI access: code only, $0 a step, the paid path off', /AI access/.test(d1) && /Code only, at \$0\.00 a step/.test(d1) && /stays off until/.test(d1));
      const btn = await p.$('.ak-dr .ak-actrow button[data-act="test"]');
      t(L + 'Run a test ticket is a button in the drawer, ready', !!btn && (await btn.textContent()).trim() === 'Run a test ticket' && !(await btn.isDisabled()));
      if (shots) await p.screenshot({ path: shots + '/test-ticket-before-' + w + '.png' });
      const runsBefore = db('SELECT COUNT(*) FROM lv_main.team_runs');
      await btn.click(); await p.waitForTimeout(400);
      const go = await p.$('.ak-dr [data-ak-actbox] form button.ak-b.go');
      if (go) { await go.click(); }
      await p.waitForTimeout(1500);
      const d2 = await (await drawer(p)).textContent();
      t(L + 'pressing it ran one more step on staging', Number(db('SELECT COUNT(*) FROM lv_main.team_runs')) === Number(runsBefore) + 1);
      t(L + 'after it runs: Passed, what it did and its cost, in place', /Passed: Wrote its build brief/.test(d2) && /Cost \$0\.00/.test(d2) && /staging copy/.test(d2), d2.slice(0, 400));
      t(L + 'it is in the role\'s History', /test ticket #\d+ passed/.test(d2));
      t(L + 'nothing left in the queue', db("SELECT COUNT(*) FROM lv_main.control_gauge_tasks WHERE gauge = 'area:team-test' AND undone_at IS NULL") === '0');
      t(L + 'no sideways scroll with the drawer open', await noSide(p));
      if (shots) await p.screenshot({ path: shots + '/test-ticket-after-' + w + '.png' });
      await p.goto(home); await p.waitForSelector('.ak-t tr.akt-grp'); await p.waitForTimeout(400); await openAll(p);   // the list again, every group open
      await openRole(p, qaId);
      const off = await p.$('.ak-dr .ak-actrow button[data-act="test"]');
      const why = off ? ((await off.getAttribute('title')) || '') + ' ' + await (await drawer(p)).textContent() : '';
      t(L + 'a role with no step yet: the button is greyed, with why and the way forward', !!off && await off.isDisabled() && /no step in the runner yet/.test(why) && /Work queue/.test(why), why.slice(0, 300));
      t(L + 'and the reason is in the drawer\'s words, not only the button\'s title', /It can\'t run now: It has no step in the runner yet/.test(await (await drawer(p)).textContent()));
      if (shots) await p.screenshot({ path: shots + '/test-ticket-greyed-' + w + '.png' });
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
