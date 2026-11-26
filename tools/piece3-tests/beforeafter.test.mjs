// Piece 3, P3-1's tests: beforeafter.mjs against fixture-router.php. Two looks before on the plain site, then one variant at
// a time after: each page made worse in one way must block with its rule, naming the page; a change the release names and
// a clock that moves must pass. Run: node tools/piece3-tests/beforeafter.test.mjs  (needs php and Playwright's Chromium)
import { spawn, execFileSync } from 'node:child_process';
import { writeFileSync, readFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os'; import path from 'node:path'; import { fileURLToPath } from 'node:url';
const here = path.dirname(fileURLToPath(import.meta.url)); const tool = path.join(here, '../page-audit/beforeafter.mjs');
const dir = mkdtempSync(path.join(tmpdir(), 'ba-')); const vf = path.join(dir, 'variant'); const port = 9200 + Math.floor(Math.random() * 50);
const site = 'http://127.0.0.1:' + port;
const ACC = { members: { member: { email: 'check+member@traversence.com', password: 'p' }, owner: { email: 'check+owner@traversence.com', password: 'p' } },
  staff: { executive: { staff_id: 'T-EXEC', password: 'p', totp: 'JBSWY3DPEHPK3PXP' } } };
const env = { ...process.env, BA_SITE: site, CHECK_ACCOUNTS: JSON.stringify(ACC) };
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(here, 'fixture-router.php')], { env: { ...process.env, VARIANT_FILE: vf }, stdio: 'ignore' });
const pages = path.join(dir, 'pages.json'); writeFileSync(pages, JSON.stringify(['/', '/faq.php', '/user/account.php', '/admin/x']));
const cap = (v, out) => { writeFileSync(vf, v); execFileSync('node', [tool, 'capture', '--pages', pages, '--out', out], { env, stdio: 'ignore' }); };
const cmp = (after, exp) => { const e = path.join(dir, 'exp.json'); writeFileSync(e, JSON.stringify(exp || {}));
  try { execFileSync('node', [tool, 'compare', '--before', path.join(dir, 'b1.json'), '--before2', path.join(dir, 'b2.json'), '--after', after, '--expected', e, '--out', path.join(dir, 'v.json')], { stdio: 'ignore' }); } catch (x) {}
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
    ['leak', 'block', 'access', 'a member opens an admin page: access'],
    ['lost', 'block', 'access', 'a member\'s own page sends it to sign in: access'],
    ['canary', 'block', 'access', 'another account\'s email shows: privacy'],
    ['canary0066', 'block', 'access', 'a held name shows: 0066', { pages: ['/'], canaries: ['Zz Held Burial Canary'] }],
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
  const r = cmp(path.join(dir, 'a-fewer.json'), { pages: ['/'] });
  t('a block names who, the page, the size, before and after', r.blocks[0].who && r.blocks[0].page === '/' && r.blocks[0].size && r.blocks[0].before !== '' && /Missing data/.test(r.first), r.blocks[0]);
  t('the verdict counts its views and seconds (DB4\'s budget)', r.views === 16 && r.seconds > 0);
} catch (e) { fail++; console.log('FAIL  ' + String(e).split('\n')[0]); }
srv.kill();
console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
