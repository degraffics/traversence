const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const fs = require('fs');
const root = '/home/user/bench/x/y/root', D = '/home/user/traversence/tools/control-board-tests', H = '/home/user/traversence/handoffs';
execFileSync('bash', ['-c', `mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main" && mariadb -utv -ptv -h127.0.0.1 lv_main < ${D}/../release-b-tests/start-schema.sql && php ${D}/board-panels-seed.php ${root}/app >/dev/null 2>&1; php ${D}/work-queue-seed.php ${root}/app >/dev/null; php ${D}/work-queue-seed.php ${root}/app ${H}/task-scope/build/app/migrations/2026-11-24_task_scope.sql >/dev/null; php ${D}/work-queue-seed.php ${root}/app ${H}/task-adrs/build/app/migrations/2026-11-24_task_adrs.sql >/dev/null`]);
const id = execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', 'lv_main', '-N', '-e', "SELECT id FROM control_gauge_tasks WHERE what='Late test task' LIMIT 1"]).toString().trim();
const th = fs.readFileSync(H + '/work-queue-phase2/thread.html', 'utf8');
const srv = spawn('php', ['-S', '127.0.0.1:8899', D + '/board-panels-router.php'], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
const { view } = require(D + '/board-view-go.cjs');
(async () => { await new Promise(r => setTimeout(r, 700)); const b = await chromium.launch();
  for (const w of [1280, 390]) { const p = await b.newPage({ viewport: { width: w, height: 1400 } });
    await p.goto('http://127.0.0.1:8899/admin/config/control'); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(300);
    await p.click('#wq-' + id + ' [data-wq=page]'); await p.waitForTimeout(1200);
    await p.evaluate(h => { const pg = document.querySelector('#cb-sp-wq .wqp'); pg.querySelector('.wqp-foot').insertAdjacentHTML('beforebegin', h); }, th);
    await p.evaluate(() => { const s = document.querySelector('#cb-sp-wq'); s.scrollTop = s.scrollHeight; });
    await p.locator('#cb-sp-wq').screenshot({ path: H + '/work-queue-phase2/p2-' + w + '.png' });
    console.log(w, await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)); await p.close(); }
  await b.close(); srv.kill(); })();
