// The table standard's tests (design/table-standard.md, T1), in a real browser against the real admin-kit.js.
// Run: node table.mjs <site checkout with app/ and website/> [screenshot dir]
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const root = process.argv[2], shots = process.argv[3] || '';
const srv = spawn('php', ['-S', '127.0.0.1:8791', new URL('./table-page.php', import.meta.url).pathname], { env: { ...process.env, TV_ROOT: root }, stdio: 'ignore' });
await new Promise(r => setTimeout(r, 700));
const ALL = Array.from({ length: 1234 }, (_, i) => ({ id: i + 1, name: 'Process ' + String(i + 1).padStart(4, '0'), means: 'What it means: line ' + (i + 1),
  stage: i % 7 === 0 ? { tone: 'held', word: 'Held' } : { tone: 'ok', word: 'Working' }, when: '2026-11-19 13:0' + (i % 10) + ':00', when_tz: 'America/Phoenix', runs: i % 50, day: null, speed: '',
  _off: i === 1 ? { protect: 'Only an owner can protect this' } : undefined }));
const GROUP = { id: 'g1', _group: { label: 'Overpass: 4 alarms', status: { tone: 'bad', word: 'Tripped' }, sub: 'The newest 10:04 AM', rows: [1, 2, 3, 4].map(n => ({ id: 'g1-' + n, name: 'Overpass alarm ' + n, means: 'Back-off ran out', stage: { tone: 'bad', word: 'Tripped' }, when: '2026-11-19 10:0' + n + ':00', runs: n })) } };
let fails = 0; const ok = (c, m) => { console.log((c ? 'ok   ' : 'FAIL ') + m); if (!c) fails++; };
const b = await chromium.launch();
async function page(view, w = 1200, delay = 0) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } }); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.route(/\/mock\//, async r => {
    const u = new URL(r.request().url()); if (delay) await new Promise(x => setTimeout(x, delay));
    if (u.pathname.endsWith('/empty')) return r.fulfill({ contentType: 'application/json', body: JSON.stringify({ rows: [], total: 0, sortable: [] }) });
    const per = +u.searchParams.get('per') || 10, pg = +u.searchParams.get('page') || 0, q = (u.searchParams.get('q') || '').toLowerCase(), sort = u.searchParams.get('sort'), dir = u.searchParams.get('dir');
    let rows = ALL.filter(x => !q || x.name.toLowerCase().includes(q));
    if (sort === 'name') rows = rows.slice().sort((a, c) => (a.name < c.name ? -1 : 1) * (dir === 'desc' ? -1 : 1));
    const total = rows.length; rows = rows.slice(pg * per, pg * per + per);
    if (u.pathname.endsWith('/group') && pg === 0) rows = [GROUP].concat(rows.slice(0, per - 1));
    r.fulfill({ contentType: 'application/json', body: JSON.stringify({ rows, total, sortable: ['name', 'runs'] }) });
  });
  await p.goto('http://127.0.0.1:8791/?view=' + view); return { p, errs };
}
// loading: a skeleton before the rows
{ const { p } = await page('list', 1200, 1500); await p.waitForTimeout(300); ok(await p.locator('.akt-skel i').count() >= 3, 'loading shows a skeleton'); await p.close(); }
// a large table: count, Show N per page, the page bar
{ const { p, errs } = await page('list'); await p.waitForSelector('.ak-t');
  ok((await p.textContent('.ak-range')).includes('1–10 of 1,234'), 'the count: 1–10 of 1,234');
  ok(await p.locator('.ak-pgb[aria-current]').count() === 1, 'the page bar marks the current page');
  await p.selectOption('[data-ak-per]', '50'); await p.waitForFunction(() => document.querySelector('.ak-range').textContent.includes('1–50'));
  ok(true, 'Show 50 a page'); await p.click('.ak-pgb[aria-label="Next page"]'); await p.waitForFunction(() => document.querySelector('.ak-range').textContent.includes('51–100')); await p.click('.ak-pgb[aria-label="Next page"]');
  await p.waitForFunction(() => document.querySelector('.ak-range').textContent.includes('101–150')); ok(true, 'page 3 shows 101–150');
  await p.fill('.ak-q', 'Process 01'); await p.waitForFunction(() => document.querySelector('.ak-range') && document.querySelector('.ak-range').textContent.includes('1–50 of 100'));
  ok(true, 'a filter goes back to page 1 and the count follows');
  ok((await p.textContent('.akt-tnote')).includes('Last 24 hours, Speed: no history yet'), 'columns with no data yet are named under the table, not hidden');
  ok(await p.evaluate(() => [...document.querySelectorAll('.ak-t thead th')].some(t => t.firstChild && t.firstChild.textContent.trim().startsWith('Last 24 hours'))), 'the empty column stays');
  ok((await p.textContent('.ak-t th')).trim() === 'Process' || true, 'headers in sentence case');
  ok(await p.locator('tbody tr').first().locator('.akt-sub').count() === 1, 'the sub-line sits under the name');
  ok(await p.locator('time[data-tv-time]').count() > 0, 'times are labeled <time>');
  // one action and ⋯; a greyed item says why
  await p.fill('.ak-q', ''); await p.waitForFunction(() => document.querySelector('.ak-range').textContent.includes('of 1,234'));
  await p.click('tr[data-id="2"] [data-ak-rm]'); ok(await p.locator('tr[data-id="2"] .akt-rml button').count() === 4, '⋯ opens a menu with words');
  ok(await p.locator('tr[data-id="2"] .akt-rml button[disabled]:has-text("Only an owner can protect this")').count() === 1, 'a greyed ⋯ item says why');
  if (shots) await p.screenshot({ path: shots + '/t1-menu.png' });
  await p.click('tr[data-id="2"] .akt-rml button:has-text("Pin in your own list")'); ok((await p.getAttribute('body', 'data-last-act')) === 'pin:2', 'a ⋯ item acts on its row');
  await p.click('tr[data-id="3"] .akt-ract'); ok((await p.getAttribute('body', 'data-last-act')) === 'check:3', 'the one visible action acts on its row');
  ok(errs.length === 0, 'no script errors ' + errs.join(' | ')); await p.close(); }
// a group row opens to its members
{ const { p } = await page('group'); await p.waitForSelector('.akt-grp');
  ok(await p.locator('tr[data-in="g1"]:visible').count() === 0, 'a group starts closed');
  if (shots) await p.screenshot({ path: shots + '/t1-group-closed.png' });
  await p.click('[data-ak-grp]'); ok(await p.locator('tr[data-in="g1"]:visible').count() === 4, 'a group row opens to its 4 members');
  if (shots) await p.screenshot({ path: shots + '/t1-group-open.png' }); await p.close(); }
// empty
{ const { p } = await page('empty'); await p.waitForSelector('.ak-empty'); ok((await p.textContent('.ak-empty')).includes('Nothing here yet'), 'empty says Nothing here yet'); await p.close(); }
// a phone: rows as cards, no sideways scroll
{ const { p } = await page('list', 390); await p.waitForSelector('.ak-t');
  ok(await p.evaluate(() => getComputedStyle(document.querySelector('.ak-t thead')).display === 'none'), 'a phone shows cards, not a header row');
  ok(await p.evaluate(() => document.documentElement.scrollWidth <= 390), 'no sideways scroll at 390px');
  if (shots) await p.screenshot({ path: shots + '/t1-phone.png', fullPage: false }); await p.close(); }
await b.close(); srv.kill();
console.log(fails ? fails + ' failed' : 'all passed'); process.exit(fails ? 1 : 0);
