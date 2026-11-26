// Keep staging caught up's words follow where it stands (Jason, 2026-11-26), rendered at 1280 and 390: off, turning on, on,
// and a copy that stopped. The page is System Configuration → Staging as tv_admin_mod_staging draws it (page-router.php),
// each state seeded by mirror-seed.php. Run: ROOT=<bench root> node mirror-words.cjs (it starts php -S itself). Never live.
const { chromium } = require('playwright');
const { execFileSync, spawn } = require('child_process');
const path = require('path');
const ROOT = process.env.ROOT, APP = ROOT + '/app', PORT = 8793;
let fails = 0, n = 0;
const ok = (c, w) => { n++; console.log((c ? '  ok  ' : 'FAIL  ') + w); if (!c) fails++; };
const seed = (st) => execFileSync('php', [path.join(__dirname, 'mirror-seed.php'), APP, st, 'fresh']);
(async () => {
  const srv = spawn('php', ['-S', '127.0.0.1:' + PORT, path.join(__dirname, 'page-router.php')], { env: { ...process.env, ROOT }, stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 800));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const page = await b.newPage({ viewport: { width: w, height: 900 } });
      const read = async () => page.evaluate(() => {
        const notes = [...document.querySelectorAll('.tv-note')].map(x => x.innerText.replace(/\s+/g, ' ').trim());
        const btn = {}; document.querySelectorAll('button').forEach(x => { btn[x.innerText.trim()] = x.disabled ? 'disabled' : 'on'; });
        const box = [...document.querySelectorAll('.ak-note')].map(x => x.innerText.replace(/\s+/g, ' ')).find(t => t.startsWith('Keep staging caught up')) || '';
        return { notes, btn, box, wide: document.documentElement.scrollWidth > window.innerWidth, all: document.body.innerText };
      });
      const go = async (st) => { seed(st); await page.goto(`http://127.0.0.1:${PORT}/admin/config/staging`); await page.waitForTimeout(400); return read(); };
      let r = await go('off');
      ok(r.box.startsWith('Keep staging caught up (off)') && r.btn['Keep staging caught up'] === 'on', `${w}: off: "(off)" and Keep staging caught up`);
      ok(r.notes.some(t => t.includes('not kept caught up')), `${w}: off: Now says not kept caught up`);
      ok(r.btn['Preview again'] === 'on' && r.btn['Publish'] === 'on', `${w}: off: Preview and Publish open`);
      r = await go('turning_on');
      const top = r.notes.find(t => t.startsWith('Keep staging caught up:')) || '';
      ok(/turning on\. A fresh copy is going in \(40%, step 475 of 1184/.test(top) && top.includes('it switches on by itself when it finishes, and we email you'), `${w}: turning on: the notice says turning on, 40%, step 475 of 1184, switches on by itself, email`);
      ok(!('Keep staging caught up' in r.btn), `${w}: turning on: no Keep staging caught up button`);
      ok(r.btn["Don't turn it on"] === 'on', `${w}: turning on: Don't turn it on (safe: the copy carries on, no outbox goes on live)`);
      ok(r.notes.some(t => t.includes('keeping caught up: turning on')) && !r.all.includes('not kept caught up'), `${w}: turning on: Now says turning on, never "not kept caught up"`);
      ok(r.box === 'Keep staging caught up (turning on): the fresh copy in the notice above is going in.', `${w}: turning on: the box points to the notice, no repeat`);
      ok(!r.all.includes('(off)') || r.all.match(/\(off\)/g).length === 1, `${w}: turning on: "(off)" only on Staging runs its own jobs`);
      ok(r.btn['Set up staging'] === 'disabled' && r.btn['Preview again'] === 'disabled' && r.btn['Schedule'] === 'disabled' && !('Publish' in r.btn), `${w}: turning on: Set up, Preview and Schedule greyed, no Publish`);
      ok(r.all.includes('Publish waits for a whole copy in staging.') && !r.all.includes('Publish is open.'), `${w}: turning on: the preview line says Publish waits`);
      const t = await page.getAttribute('button:has-text("Preview again")', 'title');
      ok(/going into staging/.test(t || ''), `${w}: turning on: Preview's greyed reason says why`);
      r = await go('on');
      ok(r.box.startsWith('Keep staging caught up (on)') && r.btn['Stop keeping caught up'] === 'on' && !('Keep staging caught up' in r.btn), `${w}: on: "(on)" and Stop keeping caught up`);
      ok(!r.all.includes('not kept caught up') && !r.all.includes('turning on'), `${w}: on: no "not kept caught up", no "turning on"`);
      r = await go('stuck');
      ok(r.notes.some(t => t.startsWith("Keep staging caught up didn't turn on:") && t.includes('the notice above says where')), `${w}: stopped: didn't turn on, pointing to the notice`);
      ok(r.notes.some(t => t.startsWith('Closed:') && t.includes('Start staging over')), `${w}: stopped: the notice names the button that exists (Start staging over)`);
      ok(r.btn['Try again'] === 'on' && r.btn['Leave it off'] === 'on' && !('Keep staging caught up' in r.btn), `${w}: stopped: Try again and Leave it off`);
      ok(r.btn['Preview again'] === 'disabled' && r.btn['Schedule'] === 'disabled' && !('Publish' in r.btn), `${w}: stopped: nothing previewed or published from half a copy`);
      ok(r.notes.some(t => t.includes("keeping caught up didn't turn on")), `${w}: stopped: Now says it didn't turn on`);
      ok(!r.wide, `${w}: no sideways scroll`);
      ok(await page.evaluate(() => [...document.querySelectorAll('.tv-note')].filter(x => /^(Closed:|Now:|Keep staging caught up:)/.test(x.innerText.trim())).every(x => !x.querySelector('button[aria-label*="lose"], .tv-note-x'))), `${w}: the state notice and Now carry no ×`);
      await page.screenshot({ path: `/tmp/claude-0/-home-user/6d344f25-c9a3-51f7-a66b-64340c765767/scratchpad/stg-stuck-${w}.png`, fullPage: true });
      seed('turning_on'); await page.goto(`http://127.0.0.1:${PORT}/admin/config/staging`); await page.waitForTimeout(400);
      await page.screenshot({ path: `/tmp/claude-0/-home-user/6d344f25-c9a3-51f7-a66b-64340c765767/scratchpad/stg-turning-${w}.png`, fullPage: true });
      await page.close();
    }
  } finally { await b.close(); srv.kill(); }
  console.log(`\n${n - fails} of ${n} checks pass`); process.exit(fails ? 1 : 0);
})();
