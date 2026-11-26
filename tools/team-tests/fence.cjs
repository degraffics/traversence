// P3-6, the fence (decisions/0179's note "agents work on the backend first", Jason, 2026-11-26), rendered on the bench at 1280
// and 390 through board-router.php: agents at Implementation, Backend only; a Building ticket whose brief reaches a public page
// says "Front end: waiting for you" with the file it names and the link to the Team's setting, its brief "waiting for you";
// a backend-only one says its brief is with a build session, no fence line; nothing wider than the window; no page error.
// Run: node fence.cjs <root> [shots dir]   (it resets lv_main at each width)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path'); const fs = require('fs'); const os = require('os');
const { view } = require('../control-board-tests/board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const T = __dirname, CB = path.join(__dirname, '../control-board-tests');
const gf = path.join(os.tmpdir(), 'fence-gauges-' + process.pid + '.json');
const g = JSON.parse(fs.readFileSync(path.join(T, '../../site-audit/results/gauges.json'), 'utf8'));
fs.writeFileSync(gf, JSON.stringify({ ...g, fence: { at: '2026-11-26T00:16:00Z', backend: ['app/lib/AdminReport.php'] }, fence_refusals: [] }));
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + T + '/../release-b-tests/start-schema.sql && php ' + CB + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + CB + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + T + '/tickets-seed.php ' + root + '/app >/dev/null && GAUGES_FILE=' + gf + ' php ' + T + '/fence-seed.php ' + root + '/app']).toString().trim().split(' ');
const port = 8935 + Math.floor(Math.random() * 20);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root, FOUNDER: '1', GAUGES_FILE: gf }, stdio: 'ignore' });
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
const W = '#cb-wq', P = '#cb-sp-wq';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
const openTask = async (p, id) => { await p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; }));
  await p.click(W + ' #wq-' + id + ' .wq-go'); await p.waitForSelector(P + ' .wqp[data-wqp-id="' + id + '"]', { timeout: 8000 }); await p.waitForTimeout(300); };
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; const [front, back] = seed();
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
      await openTask(p, front);
      const fp = await p.textContent(P + ' .wqp');
      t(L + 'a brief reaching a public page: the file it names, and its brief waiting for you', /app\/lib\/Orders\.php/.test(fp) && /Build brief\s*waiting for you/.test(fp), fp.slice(fp.indexOf('Build brief') - 50, fp.indexOf('Build brief') + 500));
      const box = await p.$$eval(P + ' .tv-note', a => a.filter(x => /Front end: waiting for you:/.test(x.innerText)).map(x => ({ info: x.classList.contains('info'), link: !!x.querySelector('a[href="/admin/config/team#tm-reach"]'), close: !!x.querySelector('.tv-note-x') })));
      t(L + '… drawn as the site\'s status line (a note box, labelled), with the link to the Team\'s setting, and no × (it\'s still open)', box.length === 1 && box[0].info && box[0].link && !box[0].close, box);
      t(L + 'nothing wider than the window with it open', await noSide(p));
      if (shots) { await p.$$eval(P + ' .tv-note', a => { const x = a.find(y => /Front end/.test(y.innerText)); if (x) x.scrollIntoView({ block: 'center' }); }); await p.waitForTimeout(200); await p.screenshot({ path: shots + '/fence-front-' + w + '.png', fullPage: false }); }
      await p.goto(home); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
      await openTask(p, back);
      const bp = await p.textContent(P + ' .wqp');
      t(L + 'a backend-only brief: with a build session, no fence line', /Build brief\s*with a build session/.test(bp) && !/Front end: waiting for you/.test(bp), bp.slice(0, 400));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srv.kill(); try { fs.unlinkSync(gf); } catch (x) {}
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
