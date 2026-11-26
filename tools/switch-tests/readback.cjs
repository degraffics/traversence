// Switches' Save reads the row back: Everyone saved and still Everyone after a reload; a row that doesn't take the choice says so, never Success.
// Run: ROOT=<root with app/ and website/> node readback.cjs (lv_main needs feature_switches; the bench .env four folders above app/lib)
const { chromium } = require('playwright'); const { spawn, execSync } = require('child_process');
const sql = (q) => execSync(`mysql -N -e "${q}" lv_main`).toString().trim();
let pass = 0, fail = 0; const t = (n, ok, got) => { ok ? pass++ : fail++; console.log((ok ? '  ok  ' : 'FAIL  ') + n + (ok ? '' : ' — ' + JSON.stringify(got))); };
(async () => {
  const srv = spawn('php', ['-S', '127.0.0.1:8934', '-t', process.env.ROOT + '/website', __dirname + '/router.php'], { env: { ...process.env, ROOT: process.env.ROOT }, stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 800));
  const b = await chromium.launch();
  for (const w of [1750, 1280, 390]) {
    sql('DROP TRIGGER IF EXISTS sw_force_bi; DROP TRIGGER IF EXISTS sw_force_bu; DELETE FROM feature_switches');
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    await p.goto('http://127.0.0.1:8934/admin/config/switches');
    t(w + ': the runner\'s card says only Everyone runs it', (await p.locator('#sw-team_runner').innerText()).includes('only Everyone runs it'));
    const top = await p.locator('#sw-team_runner select[name=state]').evaluate(el => { el.scrollIntoView({ block: 'center' }); const r = el.getBoundingClientRect(); const e = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2); return e === el || el.contains(e); });
    t(w + ': nothing covers the select (Jason\'s 1750 included)', top);
    await p.locator('#sw-team_runner select[name=state]').click(); await p.keyboard.press('End'); await p.keyboard.press('Enter');   // as a person chooses
    await p.locator('#sw-team_runner input[name=note]').fill('Start agents');
    await Promise.all([p.waitForNavigation(), p.locator('#sw-team_runner button.ak-b.go').click()]);
    const ok = await p.locator('[data-tv-flash], .tv-ask, .tv-note').allInnerTexts();
    t(w + ': Save says what it is now ("on for everyone")', ok.join(' ').toLowerCase().includes('on for everyone'), ok);
    await p.goto('http://127.0.0.1:8934/admin/config/switches');
    t(w + ': after a reload it still reads Everyone', (await p.locator('#sw-team_runner select[name=state]').inputValue()) === 'on');
    t(w + ': stored as on, with the note', sql("SELECT CONCAT(state,'|',note) FROM feature_switches WHERE switch_key='team_runner'") === 'on|Start agents');
    t(w + ': no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await p.screenshot({ path: (process.env.SHOTS || '/tmp') + `/saved-${w}.png` });
    // something puts it back as it's written: Save must say Not saved
    sql("CREATE TRIGGER sw_force_bu BEFORE UPDATE ON feature_switches FOR EACH ROW SET NEW.state = 'off'");
    await p.locator('#sw-team_runner select[name=state]').selectOption('test');
    await Promise.all([p.waitForNavigation(), p.locator('#sw-team_runner button.ak-b.go').click()]);
    const bad = (await p.locator('[data-tv-flash], .tv-ask, .tv-note').allInnerTexts()).join(' ');
    t(w + ': a save that didn\'t take says Not saved, naming the choice and what it reads', bad.includes('Not saved: you chose Staff and test accounts, but it reads Off') && !bad.includes('Success'), bad);
    await p.screenshot({ path: (process.env.SHOTS || '/tmp') + `/notsaved-${w}.png` });
    await p.close();
  }
  sql('DROP TRIGGER IF EXISTS sw_force_bu');
  await b.close(); srv.kill();
  console.log(`\n${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})();
