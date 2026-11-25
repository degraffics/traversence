// config-rail (Jason, 2026-11-25: "the team should be a page in the side menu for the system configuration … Development,
// board, workflow, services, team"), rendered at 1280 and 390 through router.php: System Configuration's five pages under
// it in the side menu while it's the open module, in the order Jason named; the page you're on marked (Development and the
// Board told apart by the tab); not listed under another module; an Engineering Director (opens System Configuration, not
// an Executive) sees Team greyed with why, the other four as links; at 390 the ☰ menu shows them the same way; nothing
// wider than the window; no page error. Run: node config-rail.cjs <root> [shots dir]
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const port = 9340 + Math.floor(Math.random() * 20);
const srv = (as, p) => spawn('php', ['-S', '127.0.0.1:' + p, '-t', path.join(root, 'website'), path.join(__dirname, 'router.php')], { env: { ...process.env, ROOT: root, AS: as }, stdio: 'ignore' });
const S = [srv('founder', port), srv('engdir', port + 30)];
const kids = p => p.$$eval('.ta-sub .ta-ch:not(.ta-ph)', a => a.map(x => ({ l: x.getAttribute('aria-label'), on: x.classList.contains('on'), off: x.getAttribute('aria-disabled') === 'true', href: x.getAttribute('href') || '', title: x.getAttribute('title') })));
(async () => {
  await new Promise(r => setTimeout(r, 800));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: '; const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      // on a phone, your photo opens the menu: it presses the shell's own button, hidden there
      const go = async (url, pt = port) => { await p.goto('http://127.0.0.1:' + pt + url); if (w === 390) { await p.evaluate(() => document.getElementById('ta-menu').click()); await p.waitForTimeout(300); } };
      await go('/admin/config/team');
      let k = await kids(p);
      t(L + 'System Configuration lists its five pages, in Jason\'s order', JSON.stringify(k.map(x => x.l)) === JSON.stringify(['Development', 'Board', 'Workflows', 'Services', 'Team']), k.map(x => x.l));
      t(L + '… with their addresses', k.map(x => x.href).join(' ') === '/admin/config/control?tab=development /admin/config/control /admin/config/workflows /admin/config/register /admin/config/team');
      t(L + 'on the Team page: Team marked, nothing else', k.filter(x => x.on).map(x => x.l).join() === 'Team', k);
      t(L + '… they\'re visible (the group open)', await p.isVisible('.ta-sub .ta-ch[aria-label="Team"]'));
      if (shots) { if (w === 1280) { await p.click('#ta-tog'); await p.waitForTimeout(300); } await p.screenshot({ path: shots + '/config-rail-' + w + '.png' }); }   // at 1280 with its labels shown
      await go('/admin/config/control?tab=development'); k = await kids(p);
      t(L + 'Development is marked on the Work queue\'s tab, the Board isn\'t', k.filter(x => x.on).map(x => x.l).join() === 'Development', k.filter(x => x.on));
      await go('/admin/config/control'); k = await kids(p);
      t(L + 'the Board is marked on the board itself', k.filter(x => x.on).map(x => x.l).join() === 'Board');
      await go('/admin/config/control?area=insights'); 
      t(L + 'under another module, they aren\'t listed', (await kids(p)).length === 0);
      await go('/admin/config/workflows', port + 30); k = await kids(p);
      const team = k.find(x => x.l === 'Team');
      t(L + 'an Engineering Director: Team greyed, saying why; the other four are links', team && team.off && /Executive/.test(team.title) && !team.href && k.filter(x => !x.off).length === 4, k);
      if (shots) await p.screenshot({ path: shots + '/config-rail-greyed-' + w + '.png' });
      // the menu's own items stay inside the window (the bench's signed-out header, unstyled without the site's Tailwind, is wider
      // than 390 on the live copy too: 412 with or without this release, so the page's width isn't this release's measure)
      t(L + 'the menu\'s items stay inside the window', await p.evaluate(() => [...document.querySelectorAll('.ta-list .ta-i')].filter(e => e.getBoundingClientRect().width > 0).every(e => e.getBoundingClientRect().right <= window.innerWidth + 1)));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); S.forEach(s => s.kill());
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
