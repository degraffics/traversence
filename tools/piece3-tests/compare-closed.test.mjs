// The before look taken while staging was closed (2026-11-26, the session manager's case): every page answered 503 "Staging
// is being refreshed". Compared with it, the layout and drift rules read the page coming back as a change and blocked, and
// a block withdraws the release. Now an unusable before look is said and not compared; with none usable the verdict is
// incomplete (a person looks), never pass and never block. A usable before look is compared exactly as before.
// Run: node tools/piece3-tests/compare-closed.test.mjs
import { compare } from '../page-audit/beforeafter.mjs';
let fails = 0, n = 0; const ok = (c, w) => { n++; console.log((c ? '  ok  ' : 'FAIL  ') + w); if (!c) fails++; };
const page = (url, extra = {}) => ({ url, who: 'member', advocate: 'Members', size: 'phone', status: 200, scroll: false, edges: 0, names: 2, alt: 1, dialogs: [],
  ba: { lines: ['Welcome', 'Your trips'], cards: 4, rows: 0, pins: 0, totals: [], primary: ['Save'], nav: 3, controls: [{ n: 'Save', h: '#', d: false }], regions: { main: { t: 'x' } }, canaries: [] }, ...extra });
const closed = url => ({ url, who: 'member', advocate: 'Members', size: 'phone', status: 503, error: '', ba: { lines: ['Staging is being refreshed'] } });
const shot = (results, signins = { member: 'ok' }) => ({ results, signins, seconds: 10 });
const held = { held: { hashes: [] } };

// 1. staging closed for the before look, open after: incomplete, no block
let v = compare(shot([closed('/me'), closed('/admin/x')], { member: 'fail' }), shot([closed('/me'), closed('/admin/x')], { member: 'fail' }), shot([page('/me'), page('/admin/x')]), { pages: [], ...held }, '1');
ok(v.verdict === 'incomplete', 'a before look taken while staging was closed: incomplete (' + v.verdict + ')');
ok(v.blocks_total === 0, 'and nothing blocked (' + (v.first || 'no block') + ')');
ok(v.notes.some(x => x.includes('No view had a usable before look')), 'and it says why: ' + v.notes[0]);

// 2. one page closed, one usable and unchanged: the usable one is compared, the verdict passes
v = compare(shot([closed('/me'), page('/trips')]), shot([closed('/me'), page('/trips')]), shot([page('/me'), page('/trips')]), { pages: [], ...held }, '2');
ok(v.verdict === 'pass' && v.blocks_total === 0, 'one view unusable, one compared and unchanged: pass (' + v.verdict + ')');
ok(v.notes.some(x => x.startsWith('member /me') || x.includes("before look wasn't usable")), 'the unusable one is named in the notes');

// 3. the rules still block on a usable before look (nothing loosened)
v = compare(shot([page('/trips')]), shot([page('/trips')]), shot([page('/trips', { ba: { ...page('/trips').ba, cards: 1 } })]), { pages: [], ...held }, '3');
ok(v.verdict === 'block' && v.blocks.some(b => b.rule === 'data'), 'fewer cards on a usable before look still blocks (' + v.first + ')');
v = compare(shot([page('/trips')]), shot([page('/trips')]), shot([page('/trips', { status: 500 })]), { pages: [], ...held }, '4');
ok(v.verdict === 'block' && v.blocks.some(b => b.rule === 'access'), 'a page that worked and errors after still blocks (' + v.first + ')');
v = compare(shot([page('/trips', { status: 401, ba: undefined })]), shot([page('/trips', { status: 401, ba: undefined })]), shot([page('/trips')]), { pages: [], ...held }, '5');
ok(v.verdict === 'block' && v.blocks.some(b => b.rule === 'access'), 'a page that refused this account and opens now still blocks (a 401 before is usable)');
v = compare(shot([page('/trips')]), shot([page('/trips')]), shot([page('/trips', { ba: { ...page('/trips').ba, lines: ['Welcome', 'Something new'] } })]), { pages: [], ...held }, '6');
ok(v.verdict === 'block' && v.blocks.some(b => b.rule === 'drift'), 'a page that changed and isn\'t named still blocks as drift');
v = compare(shot([page('/trips')]), shot([page('/trips')]), shot([page('/trips')]), { pages: [], ...held }, '7');
ok(v.verdict === 'pass', 'nothing changed: pass');

console.log(`\n${n - fails} of ${n} checks pass`); process.exit(fails ? 1 : 0);
