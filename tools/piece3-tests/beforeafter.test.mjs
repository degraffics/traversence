// Piece 3, P3-1's tests: beforeafter.mjs against fixture-router.php. Two looks before on the plain site, then one variant at
// a time after: each page made worse in one way must block with its rule, naming the page; a change the release names and
// a clock that moves must pass. Run: node tools/piece3-tests/beforeafter.test.mjs  (needs php and Playwright's Chromium)
import { spawn, execFileSync } from 'node:child_process';
import { writeFileSync, readFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os'; import path from 'node:path'; import { fileURLToPath } from 'node:url';
const here = path.dirname(fileURLToPath(import.meta.url)); const tool = path.join(here, '../page-audit/beforeafter.mjs');
const dir = mkdtempSync(path.join(tmpdir(), 'ba-')); const vf = path.join(dir, 'variant'); const port = 9200 + Math.floor(Math.random() * 50);
const site = 'http://127.0.0.1:' + port;
const KEY = 'test-checks-token'; const { heldHash, heldIn, norm } = await import(tool);
const ACC = { members: { member: { email: 'check+member@traversence.com', password: 'p' }, owner: { email: 'check+owner@traversence.com', password: 'p' } },
  staff: { executive: { staff_id: 'T-EXEC', password: 'p', totp: 'JBSWY3DPEHPK3PXP' } } };
const env = { ...process.env, BA_SITE: site, CHECK_ACCOUNTS: JSON.stringify(ACC) };
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(here, 'fixture-router.php')], { env: { ...process.env, VARIANT_FILE: vf }, stdio: 'ignore' });
const pages = path.join(dir, 'pages.json'); writeFileSync(pages, JSON.stringify(['/', '/faq.php', '/user/account.php', '/admin/x']));
const cap = (v, out, more = []) => { writeFileSync(vf, v); execFileSync('node', [tool, 'capture', '--pages', pages, '--out', out, ...more], { env, stdio: 'ignore' }); };
const cmp = (after, exp) => { const e = path.join(dir, 'exp.json'); writeFileSync(e, JSON.stringify(exp || {}));
  try { execFileSync('node', [tool, 'compare', '--before', path.join(dir, 'b1.json'), '--before2', path.join(dir, 'b2.json'), '--after', after, '--expected', e, '--out', path.join(dir, 'v.json')], { stdio: 'ignore', env: { ...process.env, CHECK_ACCOUNTS: '', HEALTH_TOKEN: KEY } }); } catch (x) {}
  return JSON.parse(readFileSync(path.join(dir, 'v.json'), 'utf8')); };
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
await new Promise(r => setTimeout(r, 600));
try {
  cap('clock', path.join(dir, 'b1.json')); cap('clock', path.join(dir, 'b2.json'));
  const b1 = JSON.parse(readFileSync(path.join(dir, 'b1.json'), 'utf8'));
  t('the before look: every account signed in, each advocate\'s views', Object.values(b1.signins).every(s => s === 'ok') && b1.results.length === 16, [b1.signins, b1.results.length]);
  t('… the member sees its own email, and that is never a leak', b1.results.some(f => f.who === 'member' && (f.ba?.canaries || []).includes('member')));
  const cases = [
    ['clock', 'pass', null, 'a clock and a counter that move on their own: pass'],
    ['neutral', 'pass', null, 'a change on a page the release names: pass', { pages: ['/'] }],
    ['neutral', 'block', 'drift', 'the same change on a page the release doesn\'t name: drift'],
    ['neutral', 'pass', null, '… named under also_changes with a reason: pass', { also_changes: [{ page: '/', why: 'the home page\'s new line' }] }],
    ['drift', 'block', 'drift', 'the FAQ reworded, not named: drift'],
    ['alarm', 'pass', null, 'an alarm, a status line and a late task raised between the looks: the system\'s state, not drift', { files: ['app/lib/Releases.php'] }],
    ['rowtext', 'pass', null, 'a queue row\'s name and stage changed between the looks (catch-up\'s data): a record\'s values, not drift'],
    ['column', 'block', 'drift', 'a column added to that table, out of scope: drift (its structure)'],
    ['newbutton', 'block', 'drift', 'a button added outside the table, out of scope: drift (its controls)'],
    ['adminnote', 'pass', null, 'a to-do line and its link appearing on an admin page (a queue\'s work, catch-up\'s data): its prose and content links aren\'t drift there'],
    ['helperitem', 'pass', null, 'a queue empty before ("All caught up.") holding an item card after, its buttons with it (#307\'s look, /admin/helper.php): a record, not drift'],
    ['emptygone', 'block', 'data', '… the same box gone with no item in its place: a part of the page gone (data)'],
    ['datasection', 'pass', null, 'a section that holds records appearing with its data (#308\'s look: "Recently published automatically"), its button with it: data, not drift'],
    ['newsection', 'block', 'drift', '… a section with no records appearing (a heading and a button of the page\'s own): drift'],
    ['countbtn', 'pass', null, 'a count in a button\'s name moved (Waiting 3, then 4; #320\'s look): its data, never a new control'],
    ['renamebtn', 'block', 'drift', 'a button renamed with no digits involved (Send, then Dispatch): still drift; the masking hasn\'t widened'],
    ['morebuttons', 'pass', null, 'one more of a button already there: more data, never a new control'],
    ['longrec', 'pass', null, 'a record\'s long address making the page wider at 390, on a page the change doesn\'t name (#308\'s look, crawler-review): its data, noted'],
    ['widebar', 'block', 'layout', '… a wide bar of the page\'s own on that page: still a layout block'],
    ['alarm', 'block', 'drift', '… the same, when the release changes what draws them (lib/Thread.php): drift', { files: ['app/lib/Thread.php'] }],
    ['alarm', 'block', 'drift', '… a drawer the release changes and reach named no pages (it can\'t say which it draws): still drift', { files: ['app/admin/module-cards.php'], pages: [] }],
    ['alarm', 'pass', null, '… a drawer the release changes, with reach naming its pages and not this one (#319\'s look: module-cards.php, the dashboard Inbox): not drift', { files: ['app/admin/module-cards.php'], pages: ['/user/account.php'] }],
    ['leak', 'block', 'access', 'a member opens an admin page: access'],
    ['lost', 'block', 'access', 'a member\'s own page sends it to sign in: access'],
    ['canary', 'block', 'access', 'another account\'s email shows: privacy'],
    ['canary0066', 'block', 'access', 'a held name shows: 0066', { pages: ['/'], held: { hashes: [heldHash('Zz Held Burial Canary', KEY)] } }],
    ['canary0066', 'incomplete', null, '… the held names unread: incomplete, never a pass', { pages: ['/'], held: { error: 'its answer: 401' } }],
    ['neutral', 'pass', null, '… a held name on no page: pass', { pages: ['/'], held: { hashes: [heldHash('Zz Held Burial Canary', KEY)] } }],
    ['scroll', 'block', 'layout', 'sideways scroll at 390: layout', { pages: ['/'] }],
    ['overlap', 'block', 'layout', 'two buttons drawn over each other: layout', { pages: ['/'] }],
    ['noprimary', 'block', 'layout', 'the page\'s main action gone: layout', { pages: ['/'] }],
    ['nonav', 'block', 'layout', 'the header\'s navigation gone: layout', { pages: ['/', '/faq.php', '/user/account.php', '/admin/x'] }],
    ['greyed', 'block', 'layout', 'a control that worked is greyed: layout', { pages: ['/admin/x'] }],
    ['fewer', 'block', 'data', 'fewer cards and a shorter list: missing data', { pages: ['/'] }],
  ];
  for (const [v, want, rule, name, exp] of cases) {
    const a = path.join(dir, 'a-' + v + '.json'); cap(v, a); const r = cmp(a, exp);
    const ok = r.verdict === want && (!rule || r.blocks.some(x => x.rule === rule)) && (want !== 'pass' || r.blocks_total === 0);
    t(name, ok, { verdict: r.verdict, first: r.first, rules: [...new Set(r.blocks.map(x => x.rule))] });
  }
  // #319's look: a part gone on a far page is looked at once more (capture given the before looks): back, a note; still gone, a block
  const bl = ['--before', path.join(dir, 'b1.json'), '--before2', path.join(dir, 'b2.json')];
  cap('flakygone', path.join(dir, 'a-flaky.json'), bl); const rf = cmp(path.join(dir, 'a-flaky.json'));
  const fl = JSON.parse(readFileSync(path.join(dir, 'a-flaky.json'), 'utf8')).results.filter(f => f.relook);
  t('a part gone on a far page, back when looked at again (#319\'s Storage tile): flaky on staging, a note, pass', rf.verdict === 'pass' && fl.length > 0 && rf.notes.some(n => /flaky on staging/.test(n)), { verdict: rf.verdict, first: rf.first, relooked: fl.length });
  cap('emptygone', path.join(dir, 'a-gone2.json'), bl); const rg = cmp(path.join(dir, 'a-gone2.json'));
  t('… still gone when looked at again: it blocks (data), and says it was looked at again', rg.verdict === 'block' && rg.blocks.some(x => x.rule === 'data') && rg.notes.some(n => /looked at again: .* still gone/.test(n)), { verdict: rg.verdict, first: rg.first });
  const { partsGone } = await import(tool); const vb = { url: '/admin/x', ba: { regions: { 'div.hp-done:1': { t: 'All caught up.' } } } }, va = { url: '/admin/x', ba: { regions: {} } };
  t('partsGone: gone on a far page, named; on a page the release reaches, or gone in a before look already, never', partsGone(vb, vb, va).length === 1 && partsGone(vb, vb, va, { pages: ['/admin/x'] }).length === 0 && partsGone(vb, va, va).length === 0);
  const rb = cmp(path.join(dir, 'a-newbutton.json'));
  t('… the drift block names the control that came (#320\'s look said only "its links and buttons")', rb.blocks.some(x => x.rule === 'drift' && /control: Export/.test(x.after)), rb.blocks.map(x => x.after));
  const rr = cmp(path.join(dir, 'a-renamebtn.json'));
  t('… and names both: the control gone and the one that came', rr.blocks.some(x => x.rule === 'drift' && /control: Send/.test(x.before) && /control: Dispatch/.test(x.after)), rr.blocks.map(x => [x.before, x.after]));
  const r0 = cmp(path.join(dir, 'a-canary0066.json'), { pages: ['/'], held: { hashes: [heldHash('Zz Held Burial Canary', KEY)] } });
  t('… the block names the held name by its hash only, never the name, in the verdict file', !readFileSync(path.join(dir, 'v.json'), 'utf8').toLowerCase().includes('burial') && r0.blocks.some(x => /held [0-9a-f]{8}/.test(x.what)), r0.blocks);
  t('heldIn: a name inside a sentence, any case and punctuation, found; one word alone never', heldIn('Visit the zz HELD burial, canary today', new Set([heldHash('zz held burial canary', KEY)]), KEY).size === 1 && heldIn('Canary', new Set([heldHash('canary', KEY)]), KEY).size === 0);
  const php = execFileSync('php', ['-r', 'require $argv[1]; echo HeldNames::hash("Zz Held — Burial Canary", "' + KEY + '"), "|", HeldNames::norm("Café  Ruins!");', path.join(here, 'heldnames-stub.php')]).toString();
  t('the site\'s hash (HeldNames) and the check\'s are the same', php === heldHash('Zz Held — Burial Canary', KEY) + '|' + norm('Café  Ruins!'), [php, heldHash('Zz Held — Burial Canary', KEY), norm('Café  Ruins!')]);
  // #307's look: a page that opened before and timed out after
  const slow = JSON.parse(readFileSync(path.join(dir, 'a-clock.json'), 'utf8'));
  for (const f of slow.results) if (f.url === '/admin/x') { f.error = 'TimeoutError: page.goto: Timeout 45000ms exceeded.'; delete f.ba; f.status = 0; }
  writeFileSync(path.join(dir, 'a-slow.json'), JSON.stringify(slow));
  const rs = cmp(path.join(dir, 'a-slow.json'), {});
  t('a page the release doesn\'t reach timing out after: staging\'s slowness, noted, never an access block', rs.verdict === 'pass' && rs.blocks_total === 0 && rs.notes.some(n => /timed out after on a page this release doesn't reach/.test(n)), { verdict: rs.verdict, first: rs.first });
  const rs2 = cmp(path.join(dir, 'a-slow.json'), { pages: ['/admin/x'] });
  t('… the same page, when the release reaches it: still an access block', rs2.verdict === 'block' && rs2.blocks.some(x => x.rule === 'access' && x.page === '/admin/x'), { verdict: rs2.verdict, first: rs2.first });
  const r = cmp(path.join(dir, 'a-fewer.json'), { pages: ['/'] });
  t('a block names who, the page, the size, before and after', r.blocks[0].who && r.blocks[0].page === '/' && r.blocks[0].size && r.blocks[0].before !== '' && /Missing data/.test(r.first), r.blocks[0]);
  t('the verdict counts its views and seconds (DB4\'s budget)', r.views === 16 && r.seconds > 0);
} catch (e) { fail++; console.log('FAIL  ' + String(e).split('\n')[0]); }
srv.kill();
console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
