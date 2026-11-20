// decisions/0176 release 3, rendered at 1280 and 390: Fix it on an alarm opens its one spot in the side panel, which closes
// by its ×, Esc and a click outside; landing on #svc-<k>-key opens the card at Its key and readies Replace (never a greyed
// one); #svc-<k>-caps lands on Caps and paces; their trouble's Pause it and greyed Use backup; nothing scrolls sideways.
// Run after r3.php (its state): node r3.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2];
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got) : '')); } };
const page = m => execFileSync('php', [path.join(__dirname, 'r3-page.php'), root + '/app', m], { stdio: ['ignore', 'pipe', 'ignore'] });
(async () => { const b = await chromium.launch(); const reg = page('register'), alarms = page('alarms'), pulse = page('pulse');
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname.startsWith('/admin/api/')) return r.fulfill({ body: '{"ok":true,"case_no":0,"ticked":[]}', contentType: 'application/json' });   // a case's answer, as the site's API gives it
   if (u.pathname === '/admin/dashboard/stream') return r.fulfill({ body: pulse, contentType: 'text/html' });
   if (u.pathname === '/board') return r.fulfill({ body: alarms, contentType: 'text/html' });
   return r.fulfill({ body: reg, contentType: 'text/html' }); });
  // the board's alarm: Fix it into the side panel
  await p.goto('http://t.local/board'); await p.waitForTimeout(800);
  await p.evaluate(() => document.querySelectorAll('#cb details').forEach(d => d.open = true)); await p.waitForTimeout(200);
  const fx = p.locator('#cb a.tv-fix[data-fix="key"]').first();
  t(w + ': the key alarm\'s first button is Fix it', await fx.count() === 1 && (await p.locator('#cb li[id^="cb-alarm-"]').filter({ has: p.locator('a.tv-fix[data-fix="key"]') }).first().locator('.cb-acts a, .cb-acts button').first().innerText()).trim() === 'Fix it');
  const box = await fx.boundingBox(); t(w + ': …fully inside the window', box && box.x >= 0 && box.x + box.width <= w, box);
  await fx.click(); await p.waitForTimeout(900);
  const fr = p.locator('.tv-pk iframe');
  t(w + ': …it opens the one side panel on Its key', await p.locator('.tv-pk').count() === 1 && /\/admin\/config\/register\?.*peek=1.*#svc-anthropic-key$/.test((await fr.getAttribute('src')) || ''), await fr.getAttribute('src'));
  await p.keyboard.press('Escape'); await p.waitForTimeout(400);
  t(w + ': …Esc closes it', await p.locator('.tv-pk:visible').count() === 0);
  await fx.click(); await p.waitForTimeout(700);
  const x = p.locator('.tv-pk [aria-label="Close"], .tv-pk .tv-pk-x').first();
  t(w + ': …it has a visible ×', await x.count() === 1 && await x.isVisible());
  await x.click(); await p.waitForTimeout(400);
  t(w + ': …which closes it', await p.locator('.tv-pk:visible').count() === 0);
  await fx.click(); await p.waitForTimeout(700); await p.mouse.click(1, 450);   // the shade: at 390 the panel sits 3px from each edge await p.waitForTimeout(400);
  t(w + ': …and a click outside closes it', await p.locator('.tv-pk:visible').count() === 0);
  t(w + ': the crash keeps its component button, no Fix it', await p.locator('#cb li[id^="cb-alarm-"]', { hasText: 'second_look' }).locator('.tv-fix').count() === 0);
  t(w + ': board: no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  // a push's link: the thread's Pulse row, its panel on Fix it
  await p.goto('http://t.local/admin/dashboard/stream#case=alarm:1&fix=1'); await p.waitForTimeout(1500);
  const pk = p.locator('.tv-pk iframe'), tf = p.locator('#pb-tool-f');
  const src = (await pk.count()) ? await pk.getAttribute('src') : (await p.locator('#pb-tool').isVisible() ? await tf.getAttribute('src') : null);
  t(w + ': a push\'s link opens its Pulse case with the panel on Fix it (Its key)', /\/admin\/config\/register\?.*#svc-anthropic-key$/.test(src || ''), src);
  if (await pk.count()) { await p.keyboard.press('Escape'); await p.waitForTimeout(300); }
  t(w + ': …Fix it is the case\'s first step, its button reading Fix it', (await p.locator('#pb-dr-play li').first().innerText()).includes('Fix it') && await p.locator('#pb-dr-play li').first().locator('button', { hasText: 'Fix it' }).count() === 1);
  await p.goto('about:blank'); await p.goto('http://t.local/admin/dashboard/stream#case=alarm:6'); await p.waitForTimeout(1200);
  t(w + ': …without &fix=1 the case opens with no panel', await p.locator('#pb-tool').isHidden() && await p.locator('.tv-pk:visible').count() === 0);
  await p.locator('#pb-dr-play li button', { hasText: 'Open' }).last().click(); await p.waitForTimeout(700);
  t(w + ': …a step\'s Open in a case opened in place shows its tool in the side panel (it showed nothing before)', await p.locator('.tv-pk:visible').count() === 1 && /\/admin\/config\/control\?.*#cb-alarm-6$/.test((await p.locator('.tv-pk iframe').getAttribute('src')) || ''));
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  // the card: landing on Its key
  await p.goto('http://t.local/admin/config/register#svc-anthropic-key'); await p.waitForTimeout(1000);
  const key = p.locator('#svc-anthropic-key');
  t(w + ': #svc-<k>-key opens its card at Its key', await key.isVisible() && await p.evaluate(() => { const r = document.getElementById('svc-anthropic-key').getBoundingClientRect(); return r.top >= 0 && r.bottom <= innerHeight; }));
  const rep = p.locator('#svc-anthropic-key + dd input[name="value"]').first();
  t(w + ': …Replace (or Add its key) is ready to paste into', (await rep.count()) === 1 && await rep.evaluate(e => e === document.activeElement));
  await p.evaluate(() => { document.activeElement.blur(); document.querySelectorAll('#svc-anthropic-key + dd button[type="submit"]').forEach(x => x.setAttribute('disabled', '')); location.hash = ''; location.hash = 'svc-anthropic-key'; }); await p.waitForTimeout(500);
  t(w + ': …a greyed one (not the Founder, or staging) is never focused', !(await rep.evaluate(e => e === document.activeElement)));
  await p.evaluate(() => { location.hash = 'svc-tavily-caps'; }); await p.waitForTimeout(500);
  t(w + ': #svc-<k>-caps lands on Caps and paces, its card open', await p.locator('#svc-tavily-caps').isVisible() && await p.evaluate(() => { const r = document.getElementById('svc-tavily-caps').getBoundingClientRect(); return r.top >= 0 && r.bottom <= innerHeight; }));
  await p.evaluate(() => { location.hash = 'issue-43'; }); await p.waitForTimeout(500);
  const i43 = p.locator('#issue-43');
  t(w + ': their trouble: Pause it, and Use backup greyed with its reason in words', await i43.locator('a[href="#stage-commoncrawl"]').isVisible() && await i43.locator('button[disabled]', { hasText: 'Use backup' }).count() === 1 && (await i43.innerText()).includes('Use backup as a button is to come'));
  await i43.locator('a[href="#stage-commoncrawl"]').click(); await p.waitForTimeout(400);
  t(w + ': …Pause it lands on the card\'s Pause', p.url().endsWith('#stage-commoncrawl') && await p.locator('#stage-commoncrawl').isVisible());
  t(w + ': register: no sideways scroll', await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p.close(); }
 console.log('\n' + pass + ' passed, ' + fail + ' failed'); await b.close(); process.exit(fail ? 1 : 0); })();
