// System Configuration → Team's head (Piece 2, P2-3; Jason, 2026-11-25; decisions/0179's note), rendered at 1280 and 390 through
// router.php: the decision tree drawn from the runner's constants (tiers, the Tier 3 areas, each role's step at each ceiling, what
// is always the Founder's); "Agents work up to: Planning"; as a staff member, Approve the decision tree and Raise greyed with why;
// as the Founder: Approve (the answer beside it after the page reads again), then Raise to Building with its evidence lines, then
// Lower to Planning; the History; nothing wider than the window; no page error.
// Run: node team-head.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const db = (sql) => execFileSync('mariadb', ['--default-character-set=utf8mb4', '-utv', '-ptv', '-h127.0.0.1', '-N', '-e', sql]).toString().trim();
const seed = () => { db('DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4');
  execFileSync('bash', ['-c', 'mariadb --default-character-set=utf8mb4 -utv -ptv -h127.0.0.1 lv_main < ' + __dirname + '/before-schema.sql']);
  for (const f of ['2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_runs.sql']) execFileSync('bash', ['-c', 'mariadb --default-character-set=utf8mb4 -utv -ptv -h127.0.0.1 lv_main < ' + root + '/app/migrations/' + f]);
  db("USE lv_main; CREATE TABLE IF NOT EXISTS control_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', kind VARCHAR(12) NOT NULL DEFAULT 'control', proc VARCHAR(60) NOT NULL DEFAULT '', what VARCHAR(400) NOT NULL DEFAULT '', who VARCHAR(80) NOT NULL DEFAULT '', why VARCHAR(400) NOT NULL DEFAULT '')"); };
const port = 9010 + Math.floor(Math.random() * 20);
const srvS = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const srvF = spawn('php', ['-S', '127.0.0.1:' + (port + 30), path.join(__dirname, 'router.php')], { env: { ...process.env, ROOT: root, FOUNDER: '1' }, stdio: 'ignore' });
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; seed();
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      // as a staff member
      await p.goto('http://127.0.0.1:' + port + '/admin/config/team'); await p.waitForSelector('#tm-tree');
      const tree = await p.textContent('#tm-tree');
      t(L + 'the decision tree: three tiers, the Tier 3 areas from the code, words only flag, what is always the Founder\'s', /Tier 1/.test(tree) && /Tier 2/.test(tree) && /Tier 3/.test(tree) && /QuickBooksPay/.test(tree) && /Landmarks/.test(tree) && /Words alone/.test(tree) && /Always the Founder/.test(tree), tree.slice(0, 200));
      t(L + 'each role\'s step at each ceiling: the Project Manager proposes at Planning, acts at Building; the Architect off at Planning', await p.$$eval('#tm-tree tbody tr', rs => { const pm = rs.find(r => /Project Manager/.test(r.textContent)), ar = rs.find(r => /System Architect/.test(r.textContent));
        return !!pm && !!ar && /proposes/.test(pm.children[1].textContent) && /acts/.test(pm.children[2].textContent) && /off/.test(ar.children[1].textContent) && /Piece 3/.test(pm.children[4].textContent); }));
      t(L + '"Agents work up to: Planning", its column marked', /Agents work up to: Planning/.test(await p.textContent('#tm-ceiling')) && await p.$$eval('#tm-tree td.tm-now', a => a.length === 4));
      t(L + 'a staff member: Approve the decision tree greyed, saying only the Founder', await p.$eval('[data-tm=tree]', x => x.disabled && /Only the Founder/.test(x.title)));
      t(L + 'Raise to Building greyed: approve the decision tree first; its evidence lines shown with sources', await p.$eval('[data-tm=ceiling]', x => x.disabled && /Approve the decision tree first/.test(x.title)) && await p.$$eval('#tm-ceiling .tm-ev tr', a => a.length === 3));
      t(L + 'no sideways scroll (the tree\'s table scrolls inside itself)', await noSide(p));
      if (shots) await p.screenshot({ path: shots + '/team-head-' + w + '.png', fullPage: true });
      // as the Founder
      await p.goto('http://127.0.0.1:' + (port + 30) + '/admin/config/team'); await p.waitForSelector('#tm-tree');
      await Promise.all([p.waitForNavigation(), p.click('[data-tm=tree]')]); await p.waitForTimeout(700);
      t(L + 'the Founder approves the tree: "Approved by Jason (test)", with its fingerprint', /Approved:\s*By Jason \(test\)/.test(await p.textContent('#tm-tree')) && /fingerprint [0-9a-f]{12}/.test(await p.textContent('#tm-tree')));
      t(L + '… the answer said beside it after the page reads again', /Approved\. Raising/.test(await p.textContent('body')));
      t(L + 'Raise to Building is open now', await p.$eval('[data-tm=ceiling][data-to=building]', x => !x.disabled));
      await Promise.all([p.waitForNavigation(), p.click('[data-tm=ceiling][data-to=building]')]); await p.waitForTimeout(700);
      t(L + 'raised: "Agents work up to: Building"; the next stage\'s evidence shown; Lower to Planning offered', /Agents work up to: Building/.test(await p.textContent('#tm-ceiling')) && /To raise it to Implementation/.test(await p.textContent('#tm-ceiling')) && await p.isVisible('[data-tm=ceiling][data-to=planning]'));
      t(L + 'its History names each change and who', /approved/.test(await p.textContent('#tm-ceiling .tm-hist')) && /Planning → Building/.test(await p.textContent('#tm-ceiling .tm-hist')));
      await Promise.all([p.waitForNavigation(), p.click('[data-tm=ceiling][data-to=planning]')]); await p.waitForTimeout(700);
      t(L + 'lowered to Planning again', /Agents work up to: Planning/.test(await p.textContent('#tm-ceiling')));
      t(L + 'nothing wider than the window', await noSide(p));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srvS.kill(); srvF.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
