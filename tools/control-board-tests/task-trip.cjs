// task-trip's after-press states rendered at 1280 and 390 (the Design reviewer: "on record"): one trip pressed while another
// holds W1, both pressed (waiting for the run), and failed again. node task-trip.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const sql = (q) => execFileSync('mariadb', ['-utv', '-ptv', '-h127.0.0.1', '-e', q]);
const fresh = () => { sql('DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main'); execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 < ' + path.join(__dirname, '../release-b-tests/start-schema.sql')]); };
const port = 8930 + Math.floor(Math.random() * 20);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });
(async () => {
  await new Promise(r => setTimeout(r, 800)); const b = await chromium.launch();
  for (const state of ['one', 'waiting', 'failed']) {
    fresh(); const [ov, fs] = execFileSync('php', [path.join(__dirname, 'task-trip-seed.php'), root + '/app', state]).toString().trim().split(' ');
    for (const w of [1280, 390]) {
      const p = await b.newPage({ viewport: { width: w, height: 900 } }); await p.goto(`http://127.0.0.1:${port}/admin/config/control/wf-W1`); await p.waitForTimeout(900);
      await p.evaluate(() => document.querySelectorAll('details.cb-grp').forEach(d => { d.open = true; }));   // its group opened, as a press on its header does
      const grp = await p.evaluate(() => { const g = document.querySelector('li.cb-al-grp'); return g ? { st: g.getAttribute('data-astate'), txt: g.querySelector('summary').textContent.replace(/\s+/g, ' ') } : null; });
      const card = (id) => p.locator('#cb-alarm-' + id);
      const txt = async (id) => (await card(id).textContent()).replace(/\s+/g, ' ');
      const L = `${w}px ${state}: `;
      if (state === 'one') {
        const a = await txt(ov);
        t(L + 'Overture\'s card: "This trip\'s pause is lifted (Jason, …). W1 is still paused by one more trip: Foursquare failed (run #1288)."', /This trip's pause is lifted \(Jason, \d\d:\d\d UTC\)\. W1 is still paused by one more trip: Foursquare failed \(run #1288\)/.test(a), a);
        t(L + '…with that trip\'s own button on this card, and no Check and start again of its own', (await card(ov).locator('button', { hasText: 'Check and start again: Foursquare failed (run #1288)' }).count()) === 1 && (await card(ov).locator('button', { hasText: /^Check and start again$/ }).count()) === 0);
        t(L + '…What to do: press the other trip\'s Check and start again', a.includes('Press the other trip\'s Check and start again (below)'), a.slice(0, 400));
        const bb = await card(ov).locator('button', { hasText: 'Check and start again: Foursquare' }).boundingBox(), cb = await card(ov).boundingBox();
        t(L + '…its button wholly inside the card (wraps on a phone)', bb && cb && bb.x >= cb.x - 1 && bb.x + bb.width <= cb.x + cb.width + 1, { bb, cb });
        if (shots) await card(ov).screenshot({ path: `${shots}/task-trip-one-${w}.png` });
      } else if (state === 'waiting') {
        const a = await txt(ov), c = await txt(fs);
        t(L + 'both cards: "W1 runs again (Jason, …). Its next run is this task\'s check: it closes as Fixed if it works, or trips again if not."', [a, c].every(x => /W1 runs again \(Jason, \d\d:\d\d UTC\)\. Its next run is this task's check: it closes as Fixed if it works, or trips again if not\./.test(x)), [a.slice(0, 200), c.slice(0, 200)]);
        t(L + '…"Nothing is paused for this trip. Its next run is the check."; no "Holds:", no Check and start again, Try it first or Acknowledge', [a, c].every(x => x.includes('Nothing is paused for this trip. Its next run is the check.') && x.includes('Nothing to do now: it closes by itself when that run works.') && !x.includes('Paused by the system') && !x.includes('Holds:') && !/Check and start again|Try it first|Acknowledge/.test(x)));
        t(L + '…amber (its row "started"), never red', (await card(fs).getAttribute('data-astate')) === 'started');
        t(L + '…their group\'s header follows: amber, "W1 Listings (nothing held: each waits for its next run)", no "Holds:"', (grp === null || (grp.st === 'started' && grp.txt.includes('(nothing held: each waits for its next run)') && !grp.txt.includes('Holds:'))), grp);   // grouped on the board, one by one on W1's page
        if (shots) await card(fs).screenshot({ path: `${shots}/task-trip-waiting-${w}.png` });
      } else {
        const c = await txt(fs);
        t(L + 'Foursquare\'s card: "Tried again at … after Jason started it: it failed again (…). Fix its cause, then Check and start again.", the button back', /Tried again at \d\d:\d\d UTC after Jason started it: it failed again/.test(c) && (await card(fs).locator('button', { hasText: /^Check and start again$/ }).count()) === 1, c.slice(0, 300));
        if (shots) await card(fs).screenshot({ path: `${shots}/task-trip-failed-${w}.png` });
      }
      t(L + 'nothing wider than the window', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
      await p.close();
    }
  }
  await b.close(); srv.kill(); console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
