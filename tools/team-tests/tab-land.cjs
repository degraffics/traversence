// Where the System Control Board's addresses land (Jason, 2026-11-26: ?tab=development opened the Board), rendered on the
// bench at 1280 and 390 through board-router.php: ?tab=development is Development (its rail item current, the Work queue
// shown); #cb-dev too; ?tab=deliver and no tab the Board; an unknown tab the Board; the old addresses' tabs (?tab=scheduler,
// ?tab=alarms) still land. Run: node tab-land.cjs <root>   (it resets lv_main)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]);
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const T = __dirname, CB = path.join(__dirname, '../control-board-tests');
execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4" && mariadb -utv -ptv -h127.0.0.1 lv_main < ' + T + '/../release-b-tests/start-schema.sql && php ' + CB + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + CB + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + T + '/tickets-seed.php ' + root + '/app >/dev/null']);
const port = 8915 + Math.floor(Math.random() * 20);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root, FOUNDER: '1' }, stdio: 'ignore' });
const base = 'http://127.0.0.1:' + port + '/admin/config/control';
const where = p => p.evaluate(() => { const b = document.getElementById('cb'); const dev = document.getElementById('cb-dev'); const cur = document.querySelector('[data-cb-rail][aria-current="page"]');
  return { tab: b && b.getAttribute('data-tab'), cur: cur ? cur.getAttribute('data-cb-rail') : '', devShown: !!dev && !dev.classList.contains('cb-offtab') && dev.offsetParent !== null, wide: document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 }; });
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; const p = await (await b.newContext({ viewport: { width: w, height: 900 } })).newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      const go = async (q) => { await p.goto(base + q); await p.waitForTimeout(900); return where(p); };
      let x = await go('?tab=development');
      t(L + '?tab=development lands on Development: the Work queue shown, Development the rail\'s current page', x.tab === 'development' && x.devShown && x.cur === 'development', x);
      x = await go('#cb-dev');
      t(L + '#cb-dev lands on Development too', x.tab === 'development' && x.devShown, x);
      x = await go('?tab=deliver'); t(L + '?tab=deliver is the Board, Development hidden', x.tab === 'deliver' && !x.devShown && x.cur === 'deliver', x);
      x = await go(''); t(L + 'no tab: the Board', x.tab === 'deliver' && !x.devShown, x);
      x = await go('?tab=nothing'); t(L + 'an unknown tab: the Board, never a blank page', x.tab === 'deliver', x);
      x = await go('?tab=scheduler'); t(L + 'an old address\'s tab (?tab=scheduler, Scheduled tasks) still lands', x.tab === 'scheduler', x);
      x = await go('?tab=alarms'); t(L + '… and ?tab=alarms', x.tab === 'alarms', x);
      t(L + 'nothing wider than the window', x.wide);
      t(L + 'no page error', errs.length === 0, errs);
      await p.context().close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srv.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
