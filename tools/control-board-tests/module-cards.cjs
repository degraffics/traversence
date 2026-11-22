// The four-zone module card rendered (03-cards.md §1, §8): Railway with the crawler's trip, Anthropic with a refused key,
// Tavily's 5xx, Bluehost and the video worker, at 1280 and 390: no sideways scroll and nothing past the right edge, the
// four slots two per row at 390, the radius' three lists stacked, the rows of zone 4 apart, every fold opened, and the
// board's buttons pressed once each (Check now, Check and start again, Acknowledge, Check it now): each posts its action
// to /admin/api/config/control and shows its answer beside it. Run: php module-cards-page.php <root>/app seed && node module-cards.cjs <root>
const { chromium } = require('playwright'); const fs = require('fs'); const { execFileSync } = require('child_process'); const path = require('path');
const root = process.argv[2], app = root + '/app', pg = path.join(__dirname, 'module-cards-page.php');
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const html = execFileSync('php', [pg, app, 'page']).toString();
(async () => { const b = await chromium.launch();
 for (const w of [1280, 390]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } }); const posts = [];
  await p.route('**/*', r => { const u = new URL(r.request().url());
   if (u.pathname.startsWith('/js/')) { try { return r.fulfill({ body: fs.readFileSync(root + '/website' + u.pathname), contentType: 'text/javascript' }); } catch (e) { return r.fulfill({ body: '', contentType: 'text/javascript' }); } }
   if (u.pathname === '/admin/api/config/control') { const bd = JSON.parse(r.request().postData() || '{}'); posts.push(bd); return r.fulfill({ body: JSON.stringify({ ok: true, message: 'Answered: ' + bd.action, bad: true }), contentType: 'application/json' }); }
   return r.fulfill({ body: html, contentType: 'text/html' }); });
  await p.goto('http://t.local/cards'); await p.waitForTimeout(300);
  await p.evaluate(() => document.querySelectorAll('details').forEach(d => d.open = true)); await p.waitForTimeout(200);
  const g = await p.evaluate(() => { const W = document.documentElement.clientWidth, past = [];
    document.querySelectorAll('.mc-card *').forEach(el => { const r = el.getBoundingClientRect(); if (r.width && r.right > W + 1) past.push((el.className || el.tagName) + ':' + Math.round(r.right)); });
    const cards = [...document.querySelectorAll('.mc-card')].filter(c => !c.parentElement.closest('.mc-card')).map(c => { const r = c.getBoundingClientRect(); return [Math.round(r.left), Math.round(W - r.right)]; });
    const slots = [...document.querySelectorAll('#mc-railway > .mc-z4 .mc-slot')].map(s => Math.round(s.getBoundingClientRect().top));
    const rad = [...document.querySelectorAll('#mc-railway > .mc-z1 .mc-rad > details')].map(s => Math.round(s.getBoundingClientRect().top));
    return { sw: document.documentElement.scrollWidth, W, past: past.slice(0, 8), cards, slots, rad }; });
  t(w + 'px: no sideways scroll (scrollWidth ' + g.sw + ' ≤ ' + g.W + ')', g.sw <= g.W, g);
  t(w + 'px: nothing inside a card past the right edge, every fold open', g.past.length === 0, g.past);
  t(w + 'px: each card within the window' + (w === 390 ? ', 3px from each side' : ''), g.cards.length === 5 && g.cards.every(([l, r]) => w === 390 ? l === 3 && r === 3 : l >= 0 && r >= 0), g.cards);
  if (w === 390) {
   t('390px: the four slots two per row (Billing · Keys, then Console · Sign in)', g.slots.length === 4 && g.slots[0] === g.slots[1] && g.slots[2] === g.slots[3] && g.slots[2] > g.slots[0], g.slots);
   t('390px: Held, On a backup and Unaffected stacked', g.rad.length === 3 && g.rad[0] < g.rad[1] && g.rad[1] < g.rad[2], g.rad);
  } else t('1280px: the four slots on one row', g.slots.length === 4 && new Set(g.slots).size === 1, g.slots);
  const rows = await p.evaluate(() => { const c = document.querySelector('#mc-anthropic'); const o = c.querySelector('[data-row="ours"]'), v = c.querySelector('[data-row="provider"]');
    return { ours: [...o.querySelectorAll('a,button')].map(x => x.textContent.trim()), oursOut: o.querySelectorAll('a[target=_blank]').length, provActs: v.querySelectorAll('[data-mc-act],form').length, firstOurs: o.querySelector('.mc-btns > *').textContent.trim(), z: [...c.querySelectorAll(':scope > .mc-z')].map(z => z.className.split(' ')[1]) }; });
  t(w + 'px: Anthropic\'s refused key: Fix it first on our system, no outside link there, no board action among the slots, zone 4 under zone 1', rows.firstOurs === 'Fix it' && rows.oursOut === 0 && rows.provActs === 0 && rows.z.join() === 'mc-z1,mc-z4,mc-z2,mc-z3', rows);
  const tv = await p.evaluate(() => { const c = document.querySelector('#mc-tavily'); return { fix: c.querySelectorAll('.tv-fix,.mc-slot-fix').length, acts: [...c.querySelectorAll('[data-row="ours"] button')].map(x => x.textContent.trim()) }; });
  t(w + 'px: Tavily\'s 5xx: no Fix it, Check it now and Pause', tv.fix === 0 && tv.acts.includes('Check it now') && tv.acts.includes('Pause'), tv);
  // press each board button once
  for (const [sel, act] of [['#mc-railway > .mc-z4 [data-mc-act="resolve"]', 'resolve'], ['#mc-railway > .mc-z4 [data-mc-act="ack"]', 'ack'], ['#mc-anthropic [data-mc-act="svccheck"]', 'svccheck'], ['#mc-railway > .mc-z4 [data-mc-act="check"]', 'check']]) {
   const el = await p.$(sel); if (!el) { t(w + 'px: ' + act + ' is on the card', false, sel); continue; }
   const before = posts.length; await el.scrollIntoViewIfNeeded(); const dis = await el.isDisabled();
   if (dis) { const why = await el.getAttribute('title'); t(w + 'px: ' + act + ' greyed with its reason (' + why + ')', !!why); continue; }
   await el.click(); await p.waitForTimeout(500);
   const said = await p.evaluate(() => document.body.innerText.includes('Answered:'));
   t(w + 'px: ' + act + ' pressed: posts {action: ' + act + '} and the answer shows', posts.length === before + 1 && posts[posts.length - 1].action === act && said, posts.slice(-1));
   await p.keyboard.press('Escape'); await p.waitForTimeout(150);
  }
  await p.screenshot({ path: path.join(process.env.SHOTS || '/tmp', 'module-cards-' + w + '.png'), fullPage: true });
  await p.close(); }
 await b.close(); console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0); })();
