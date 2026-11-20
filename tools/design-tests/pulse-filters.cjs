// Pulse filters (design/table-standard.md, Jason's note of 2026-11-20 and its answers), rendered at 1280 and 390.
// Run on the harness serving the admin at 127.0.0.1:8790, signed in as staff (cookie traversence_session=harness0…0),
// with rows from several senders and levels seeded: node pulse-filters.cjs
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8790', URL = BASE + '/admin/dashboard/today';
let pass = 0, fail = 0; const ok = (c, m) => { if (c) { pass++; console.log('  ✓ ' + m); } else { fail++; console.log('  ✗ ' + m); } };
(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    console.log('— ' + w + 'px');
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 Chrome/130' });
    await ctx.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: BASE }]);
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.route(/^https:\/\/(?!127)/, r => r.abort());
    // clean slate: no saved views, no remembered choices
    await p.goto(URL, { waitUntil: 'load' });
    await p.evaluate(async () => { localStorage.clear(); const j = await (await fetch('/user/api/pulse-views.php')).json(); for (const v of j.views || []) await fetch('/user/api/pulse-views.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': j.csrf }, body: JSON.stringify({ delete: v.id }) }); });
    await p.reload({ waitUntil: 'load' }); await p.waitForTimeout(600);
    const shown = () => p.evaluate(() => [...document.querySelectorAll('#ps-list .ps-row:not(.ps-ghead)')].filter(r => !r.hidden).length);
    const total = await shown();
    ok(await p.$('#pb-gdd') === null && await p.$('.ps-flt') === null, 'Group ▾ and the header funnels are gone');
    ok(await p.$$eval('#ps-hd [data-sort]', x => x.length) === 4, 'sorting stays on the four headers');
    // the words beside the icons (note 6)
    const words = await p.$$eval('.pb-tools .pb-icw', x => x.map(e => getComputedStyle(e).display !== 'none' && e.getBoundingClientRect().width > 0));
    ok(words.length === 4 && words.every(v => v === (w >= 768)), w >= 768 ? 'the bell, Your day, Quick actions and Settings say their names' : 'icons only on a phone');
    // the panel opens under its button (note 3)
    await p.click('#pb-fdd > summary');
    const pos = await p.evaluate(() => { const s = document.querySelector('#pb-fdd > summary').getBoundingClientRect(), c = document.querySelector('#pb-fdd .pb-fddc').getBoundingClientRect(); return { top: c.top, sb: s.bottom, l: c.left, r: c.right, vw: innerWidth }; });
    ok(pos.top >= pos.sb && pos.l >= 0 && pos.r <= pos.vw, 'the panel opens under its button, inside the screen');
    const senders = await p.$$eval('#pb-from .pb-tick', x => x.map(e => [e.querySelector('span').textContent, +e.querySelector('b').textContent]));
    ok(senders.length === 6 && senders.reduce((a, s) => a + s[1], 0) === total, 'Senders lists every sender with its count (' + senders.map(s => s.join(' ')).join(', ') + ')');
    ok(await p.$$eval('#pb-sev .pb-tick', x => x.length) === 4, 'Levels lists the four levels in the stream');
    // a tick applies at once; the panel stays open; a tag shows it
    await p.click('#pb-from .pb-tick:has-text("Team") input');
    const afterTeam = await shown();
    ok(afterTeam === total - senders.find(s => s[0] === 'Team')[1], 'unticking Team hides its rows at once (' + afterTeam + ' of ' + total + ')');
    ok(await p.$eval('#pb-fdd', d => d.open), 'the panel stays open after a tick');
    ok(/Senders: 5/.test(await p.textContent('#pb-tags')), 'a tag says Senders: 5');
    // counts within the other filters; none greyed at 0 (note 2)
    for (const lv of ['Alert', 'Notice', 'General']) await p.click('#pb-sev .pb-tick:has-text("' + lv + '") input');
    const crit = await p.$$eval('#pb-from .pb-tick', x => x.map(e => [e.querySelector('span').textContent, +e.querySelector('b').textContent, e.classList.contains('none')]));
    ok(crit.find(s => s[0] === 'Railway')[1] === 1 && crit.find(s => s[0] === 'Square')[1] === 1 && crit.filter(s => s[1] === 0).every(s => s[2]) && crit.filter(s => s[2]).length === 4, 'with Critical only: Railway 1, Square 1, the rest greyed at 0');
    ok(await shown() === 2, 'two critical rows show');
    // Esc closes; the tags stay; × on a tag takes it off
    await p.keyboard.press('Escape'); ok(!(await p.$eval('#pb-fdd', d => d.open)), 'Esc closes the panel');
    await p.click('#pb-tags [data-untag="sev"]');
    ok(await shown() === afterTeam && !/Levels/.test(await p.textContent('#pb-tags')), '× on Levels takes it off');
    // a click outside closes; the × closes
    await p.click('#pb-fdd > summary'); await p.mouse.click(5, 880); ok(!(await p.$eval('#pb-fdd', d => d.open)), 'a click outside closes the panel');
    await p.click('#pb-fdd > summary'); await p.click('#pb-fdd .pb-x'); ok(!(await p.$eval('#pb-fdd', d => d.open)), 'its × closes the panel');
    // remembered on the device
    await p.reload({ waitUntil: 'load' }); await p.waitForTimeout(500);
    ok(await shown() === afterTeam && /Senders: 5/.test(await p.textContent('#pb-tags')), 'Senders is remembered on reload');
    // saved views, with the account (note 5)
    await p.click('#pb-fdd > summary'); await p.waitForTimeout(300);
    await p.fill('#pb-svn', 'No team posts'); await p.click('#pb-svf button'); await p.waitForTimeout(700);
    ok(await p.$$eval('#pb-saved [data-sv]', x => x.length === 1 && x[0].textContent === 'No team posts' && x[0].getAttribute('aria-pressed') === 'true'), 'Save names the mix; it shows as the current view');
    await p.click('#pb-tags [data-untag="from"]'); ok(await shown() === total, '× on Senders shows everything again');
    await p.click('#pb-saved [data-sv]'); await p.waitForTimeout(200);
    ok(await shown() === afterTeam, 'one click on the saved view puts its filters back');
    // Group by in the same panel; 3 rows or fewer open by themselves
    await p.click('.pb-gby [data-gb="from"]'); await p.waitForTimeout(200);
    const heads = await p.$$eval('#ps-list .ps-ghead', x => x.map(h => [h.querySelector('span:nth-child(2)').textContent, +h.querySelector('.ps-gcount').textContent, h.getAttribute('aria-expanded')]));
    ok(heads.length === 5 && heads.every(h => (h[1] <= 3) === (h[2] === 'true')), 'grouped by sender: a group of 3 or fewer opens by itself (' + heads.map(h => h.join(' ')).join(', ') + ')');
    ok(/Group: Sender/.test(await p.textContent('#pb-tags')), 'a tag says Group: Sender');
    await p.keyboard.press('Escape');
    ok(await p.isVisible('#ps-gbar'), 'Open all and Close all show while grouped');
    await p.click('#ps-gbar [data-gall="1"]'); ok(await p.$$eval('#ps-list .ps-ghead', x => x.every(h => h.getAttribute('aria-expanded') === 'true')), 'Open all opens every group');
    await p.click('#ps-gbar [data-gall="0"]'); ok(await p.$$eval('#ps-list .ps-ghead', x => x.every(h => h.getAttribute('aria-expanded') === 'false')), 'Close all closes every group');
    await p.click('#ps-list .ps-ghead:has-text("Square")');
    await p.reload({ waitUntil: 'load' }); await p.waitForTimeout(500);
    const rem = await p.$$eval('#ps-list .ps-ghead', x => x.map(h => [h.querySelector('span:nth-child(2)').textContent, h.getAttribute('aria-expanded')]));
    ok(rem.find(h => h[0] === 'Square')[1] === 'true' && rem.filter(h => h[1] === 'true').length === 1, 'what you opened and closed is remembered on reload');
    // the saved view is kept with the account: a fresh device has it
    const ctx2 = await b.newContext({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 Chrome/130' });
    await ctx2.addCookies([{ name: 'traversence_session', value: 'harness0000000000000000000000', url: BASE }]);
    const p2 = await ctx2.newPage(); await p2.route(/^https:\/\/(?!127)/, r => r.abort()); await p2.goto(URL, { waitUntil: 'load' }); await p2.waitForTimeout(700);
    ok(await p2.$$eval('#pb-saved [data-sv]', x => x.map(e => e.textContent).join()) === 'No team posts', 'another device, same account: the saved view is there');
    await ctx2.close();
    // remove, with Undo
    await p.click('#pb-fdd > summary'); await p.waitForTimeout(300);
    await p.click('#pb-saved [data-sv-del]'); await p.waitForTimeout(700);
    ok(await p.$$eval('#pb-saved [data-sv]', x => x.length) === 0 && await p.isVisible('#pb-saved [data-sv-undo]'), 'remove takes it off, with Undo in its place');
    ok(await p.$eval('#pb-fdd', d => d.open), 'the panel stays open after Remove');
    await p.click('#pb-saved [data-sv-undo]'); await p.waitForTimeout(700);
    ok(await p.$$eval('#pb-saved [data-sv]', x => x.map(e => e.textContent).join()) === 'No team posts', 'Undo puts it back');
    // Clear all
    await p.keyboard.press('Escape'); await p.click('#pb-tags [data-untag="all"]'); await p.waitForTimeout(200);
    ok(await shown() === total && (await p.textContent('#pb-tags')).trim() === '', 'Clear all takes off every filter');
    // sorting still works
    await p.click('#ps-hd [data-sort="from"]'); const fr = await p.$$eval('#ps-list .ps-row:not([hidden])', x => x.map(r => r.dataset.from.toLowerCase()));
    ok(fr.join() === fr.slice().sort().join(), 'a tap on From sorts by sender');
    await p.click('#ps-hd [data-sort="from"]'); await p.click('#ps-hd [data-sort="from"]');
    // phone: nothing sideways
    if (w === 390) { await p.click('#pb-fdd > summary'); await p.waitForTimeout(200); ok(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'at 390, nothing scrolls sideways with the panel open'); await p.keyboard.press('Escape'); }
    ok(errs.length === 0, 'no script errors' + (errs.length ? ': ' + errs.join(' | ') : ''));
    await p.screenshot({ path: '/tmp/claude-0/pf-' + w + '.png', fullPage: false });
    await ctx.close();
  }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
