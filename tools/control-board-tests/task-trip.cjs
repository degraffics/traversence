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
  for (const state of ['before', 'one', 'waiting', 'failed']) {
    fresh(); const [ov, fs] = execFileSync('php', [path.join(__dirname, 'task-trip-seed.php'), root + '/app', state]).toString().trim().split(' ');
    for (const w of [1280, 390]) {
      const p = await b.newPage({ viewport: { width: w, height: 900 } }); await p.goto(`http://127.0.0.1:${port}/admin/config/control/wf-W1`); await p.waitForTimeout(900);
      await p.evaluate(() => document.querySelectorAll('details.cb-grp').forEach(d => { d.open = true; }));   // its group opened, as a press on its header does
      const grp = await p.evaluate(() => { const g = document.querySelector('li.cb-al-grp'); return g ? { st: g.getAttribute('data-astate'), txt: g.querySelector('summary').textContent.replace(/\s+/g, ' ') } : null; });
      const card = (id) => p.locator('#cb-alarm-' + id);
      const txt = async (id) => (await card(id).textContent()).replace(/\s+/g, ' ');
      const L = `${w}px ${state}: `;
      if (state === 'before') {
        const a = await txt(ov);
        t(L + 'one Status sentence: "W1 is paused by the system since …, after Overture Maps Places\' read failed (run #1290). Seen by Jason, …."', /W1 is paused by the system since .+, after Overture Maps Places' read failed \(run #1290\)\. Seen by Jason, /.test(a) && !a.includes('(W1 ('), a.slice(0, 300));
        t(L + '…one action, Check and start again; no Undo; no loose "by Jason…" line', (await card(ov).locator('button', { hasText: /^Check and start again$/ }).count()) === 1 && !/\bUndo\b/.test(a) && !/It stays open while it holds/.test(a));
        t(L + '…What the system is doing and What to do in its words, no brackets in brackets, no "Recovered"', a.includes('Only W1 waits; everything else carries on. It closes as Fixed when this task\'s next run works.') && a.includes('Then Check and start again: W1 runs, and its next run is this task\'s check.') && !a.includes('Recovered') && !a.includes('(its tasks wait with it)'), a.slice(0, 500));
        t(L + '…its title in plain words: "Overture Maps Places\' read failed (run #1290)", no raw id', a.includes("Overture Maps Places' read failed (run #1290)") && !a.includes('overture_bulk'), a.slice(0, 120));
        t(L + '…Check and start again the one filled button; no second "Open its run"', (await card(ov).locator('button.go', { hasText: /^Check and start again$/ }).count()) === 1 && !a.includes('Open its run'));
        t(L + '…its pill "Trip" (red) while it holds', (await card(ov).locator('.cb-pill-bad', { hasText: 'Trip' }).count()) === 1);
        t(L + '…Run #1290 an evidence icon with its title', (await card(ov).locator('a.cb-ic[title="Open Run #1290: what it did and why"]').count()) === 1);
        const more = card(ov).locator('details.cb-more');
        await more.locator('summary').click(); await p.waitForTimeout(150);
        t(L + '…⋯ opens Try it first on the next run and Pause the task', await more.evaluate(d => d.open) && (await more.locator('.cb-menu button').allTextContents()).join('|') === 'Try it first on the next run|Pause the task');
        const mb = await more.locator('.cb-menu').boundingBox();
        t(L + '…the menu wholly in the window', mb && mb.x >= 0 && mb.x + mb.width <= w + 1, mb);
        const cbx = await card(ov).boundingBox();
        t(L + '…and wholly inside the card, never clipped by its edge', mb && cbx && mb.y >= cbx.y - 1 && mb.y + mb.height <= cbx.y + cbx.height + 1 && mb.x >= cbx.x - 1 && mb.x + mb.width <= cbx.x + cbx.width + 1, { mb, cbx });
        if (shots) await card(ov).screenshot({ path: `${shots}/task-trip-before-${w}.png` });
        await p.keyboard.press('Escape'); await p.waitForTimeout(100);
        t(L + '…Esc closes it', !(await more.evaluate(d => d.open)));
        await more.locator('summary').click(); await p.mouse.click(5, 5); await p.waitForTimeout(100);
        t(L + '…a click outside closes it', !(await more.evaluate(d => d.open)));
        t(L + '…its mark: stopped (power), never an ×', (await card(ov).locator('.cb-m[aria-label="Stopped: needs an admin"]').count()) === 1);
      } else if (state === 'one') {
        const a = await txt(ov);
        t(L + 'Overture\'s card: "This trip\'s pause is lifted (Jason, …). W1 is still paused by one more trip: Foursquare\'s read failed (run #1288)."', /This trip's pause is lifted \(Jason, \d\d:\d\d UTC\)\. W1 is still paused by one more trip: Foursquare's read failed \(run #1288\)/.test(a), a);
        t(L + '…with that trip\'s own button on this card, and no Check and start again of its own', (await card(ov).locator('button', { hasText: "Check and start again: Foursquare's read failed (run #1288)" }).count()) === 1 && (await card(ov).locator('button', { hasText: /^Check and start again$/ }).count()) === 0);
        t(L + '…What to do: press the other trip\'s Check and start again', a.includes('Press the other trip\'s Check and start again (below)'), a.slice(0, 400));
        const bb = await card(ov).locator('button', { hasText: 'Check and start again: Foursquare' }).boundingBox(), cb = await card(ov).boundingBox();
        t(L + '…its button wholly inside the card (wraps on a phone)', bb && cb && bb.x >= cb.x - 1 && bb.x + bb.width <= cb.x + cb.width + 1, { bb, cb });
        if (shots) await card(ov).screenshot({ path: `${shots}/task-trip-one-${w}.png` });
      } else if (state === 'waiting') {
        const a = await txt(ov), c = await txt(fs);
        t(L + 'both cards: "W1 runs again (Jason, …). Its next run is this task\'s check: it closes as Fixed if it works, or trips again if not."', [a, c].every(x => /W1 runs again \(Jason, \d\d:\d\d UTC\)\. Its next run is this task's check: it closes as Fixed if it works, or trips again if not\./.test(x)), [a.slice(0, 200), c.slice(0, 200)]);
        t(L + '…"Nothing is paused for this trip. Its next run is the check."; no "Holds:", no Check and start again, Try it first or Acknowledge', [a, c].every(x => x.includes('Nothing is paused for this trip. Its next run is the check.') && x.includes('Nothing to do now: it closes by itself when that run works.') && !x.includes('Paused by the system') && !x.includes('Holds:') && !/Check and start again|Try it first|Acknowledge/.test(x)));
        t(L + '…amber (its row "started"), never red', (await card(fs).getAttribute('data-astate')) === 'started');
        t(L + '…its pill "Waiting for its run" in amber, never the red "Trip"', (await card(fs).locator('.cb-pill-warn', { hasText: 'Waiting for its run' }).count()) === 1 && (await card(fs).locator('.cb-pill-bad').count()) === 0);
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
