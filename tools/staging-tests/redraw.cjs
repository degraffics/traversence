// The Staging page's redraw after a press (Jason's screen, 2026-11-26): "Staging against live" was drawn again as bare text,
// the Now line and "Outside calls that skip the gate" without their boxes and labels, because admin-staging.js's redraw
// didn't run TvAsk.statuses. Pressed here on the bench (page-router.php; its api answers ok), the status boxes are counted
// before and after, at 1280 and 390. Run: ROOT=<bench root> node redraw.cjs   (it starts php -S itself). Never live.
const { chromium } = require('playwright');
const { execFileSync, spawn } = require('child_process');
const path = require('path');
const ROOT = process.env.ROOT, APP = ROOT + '/app', PORT = 8794;
let fails = 0, n = 0; const ok = (c, w) => { n++; console.log((c ? '  ok  ' : 'FAIL  ') + w); if (!c) fails++; };
execFileSync('php', [path.join(__dirname, 'mirror-seed.php'), APP, 'on', 'fresh']);
(async () => {
  const srv = spawn('php', ['-S', '127.0.0.1:' + PORT, path.join(__dirname, 'page-router.php')], { env: { ...process.env, ROOT }, stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 800));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const page = await b.newPage({ viewport: { width: w, height: 900 } });
      await page.goto(`http://127.0.0.1:${PORT}/admin/config/staging`); await page.waitForTimeout(400);
      const count = () => page.evaluate(() => { const box = document.getElementById('stg2'); return { notes: box ? box.querySelectorAll('.tv-note').length : -1, bare: box ? [...box.querySelectorAll('[data-tv-status]')].filter(x => !x.closest('.tv-note')).length : -1, now: box ? [...box.querySelectorAll('.tv-note')].some(x => /^Now:/.test(x.innerText.trim())) : false }; });
      const before = await count();
      ok(before.notes > 0 && before.now, `${w}: before the press, "Staging against live" has ${before.notes} status boxes, the Now line among them`);
      await page.click('#stg2 [data-stg2="jobs"]'); await page.waitForTimeout(2200);
      const after = await count();
      ok(after.notes === before.notes && after.now, `${w}: after the press and its redraw, the same ${after.notes} status boxes, the Now line boxed and labelled (before: ${before.notes})`);
      ok(after.bare === 0, `${w}: no status line left as bare text (${after.bare})`);
      await page.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log(`\n${n - fails} of ${n} checks pass`); process.exit(fails ? 1 : 0);
})();
