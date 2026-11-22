// Checks the review pack (step3/board/review/): each part under 300 KB, valid (board-validate.py), its note visible,
// and it opens with no console errors and no sideways scroll at 1280 and 390; its panels close; the zip holds every part.
const { chromium } = require('playwright');
const fs = require('fs');
const cp = require('child_process');
const dir = '/home/user/traversence/handoffs/control-board-plan/step3/board/review/';
(async () => {
  const out = []; let fails = 0;
  const ok = (c, m) => { out.push((c ? 'PASS ' : 'FAIL ') + m); if (!c) fails++; };
  const parts = fs.readdirSync(dir).filter(f => /^part-\d+.*\.html$/.test(f)).sort();
  for (const f of parts) {
    const size = fs.statSync(dir + f).size;
    ok(size < 300 * 1024, `${f}: ${Math.round(size / 1024)} KB, under 300 KB`);
    const v = cp.spawnSync('python3', [__dirname + '/board-validate.py', dir + f], { encoding: 'utf8' });
    ok(v.status === 0, `${f}: valid (${v.stdout.trim().split('\n').length} validator checks)` + (v.status ? ': ' + v.stdout.split('\n').filter(l => l.startsWith('FAIL')).join(' | ') : ''));
    const head = fs.readFileSync(dir + f, 'utf8').slice(0, 400);
    ok(/<!-- Part \d+ of \d+ of the control board review pack: .*The full board is index\.html\. -->/.test(head), `${f}: opens with its one-line header comment`);
  }
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    for (const f of parts) {
      const p = await (await b.newContext({ viewport: { width: w, height: 900 } })).newPage();
      const errs = []; p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); }); p.on('pageerror', e => errs.push(String(e)));
      await p.goto('file://' + dir + f);
      const r = await p.evaluate(() => [document.documentElement.scrollWidth, !!document.querySelector('.rnote') && /Part \d+ of \d+ of the control board review pack/.test(document.querySelector('.rnote').textContent),
        [...document.querySelectorAll('.view')].filter(v => !v.hidden).length]);
      let panelOk = true;
      const opener = await p.$('[data-open-panel]:visible');
      if (opener) { await opener.click(); const id = await opener.getAttribute('data-open-panel'); const o1 = await p.evaluate(id => document.body.classList.contains('open') && !document.getElementById(id).hidden, id); await p.keyboard.press('Escape'); const o2 = await p.evaluate(() => document.body.classList.contains('open')); panelOk = o1 && !o2; }
      ok(errs.length === 0 && r[0] <= w && r[1] && r[2] === 1 && panelOk, `@${w} ${f}: opens with no console errors, scrollWidth ${r[0]}, its note shown, its view shown, a panel opens and closes` + (errs.length ? ': ' + errs.slice(0, 2).join(' | ') : ''));
      await p.close();
    }
  }
  await b.close();
  const z = cp.spawnSync('python3', ['-c', `import zipfile;print(len(zipfile.ZipFile('${dir}control-board-review-pack.zip').namelist()))`], { encoding: 'utf8' });
  ok(+z.stdout.trim() === parts.length, `the zip holds all ${parts.length} parts`);
  console.log(out.join('\n')); console.log(fails ? `${fails} FAILED` : 'ALL PASS');
})();
