// A view that timed out in the first before look isn't tried twice in a later look (the session manager, 2026-11-26: #305's two
// slow pages, /admin/sources.php and /admin/crawler-review.php, cost half of each pass); timed out before and after, it's a
// note at most, never a block (compare). Run: node tools/piece3-tests/slow.test.mjs
import { writeFileSync, readFileSync, mkdtempSync } from 'node:fs'; import { tmpdir } from 'node:os'; import path from 'node:path';
import { slowViews, compare } from '../page-audit/beforeafter.mjs';
let pass = 0, fail = 0; const ok = (c, n, got) => { if (c) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const dir = mkdtempSync(path.join(tmpdir(), 'slow-')); const f = path.join(dir, 'b1.json');
const TO = 'TimeoutError: page.goto: Timeout 45000ms exceeded.';
const v = (who, url, size, extra = {}) => ({ who, url, size, status: 200, advocate: 'a', ba: { lines: ['A heading'], controls: [], regions: [], totals: [], cards: 0, rows: 0, pins: 0, overlap: [], clipped: [], offscreen: [], primary: ['Save'], heading: 'H', nav: 3, canaries: [], text: 'A heading', landed_signin: false }, ...extra });
const slow = v('executive', '/admin/sources.php', 'desktop', { status: 0, error: TO, ba: undefined });
writeFileSync(f, JSON.stringify({ results: [v('executive', '/admin/x', 'desktop'), slow, v('ops-staff', '/admin/crawler-review.php', 'phone', { status: 0, error: TO, ba: undefined })], signins: { executive: 'ok', 'ops-staff': 'ok' } }));
const s = slowViews(f);
ok(s.size === 2 && s.has('executive|/admin/sources.php|desktop') && s.has('ops-staff|/admin/crawler-review.php|phone'), 'the first before look\'s timed-out views, by who, page and size', [...s]);
ok(slowViews('').size === 0 && slowViews(path.join(dir, 'none.json')).size === 0, 'no file, or one that isn\'t there: none (every timeout tried once more, as before)');
const src = readFileSync(new URL('../page-audit/beforeafter.mjs', import.meta.url), 'utf8');
ok(/slow\.has\(who \+ '\|' \+ url \+ '\|' \+ size\)\) console\.log\(`not retried:/.test(src), 'capture: a view in that set is logged "not retried" and not opened again');
const wf = readFileSync(new URL('../../.github/workflows/beforeafter.yml', import.meta.url), 'utf8');
ok(/--out ba-before-2\.json[^\n]*--slow ba-before-1\.json/.test(wf) && /--out ba-after\.json[^\n]*--slow before\/ba-before-1\.json/.test(wf), 'the workflow passes the first before look to the second before look and to the after look');
const look = r => ({ results: r, signins: { executive: 'ok', 'ops-staff': 'ok' }, seconds: 1 });
const verdict = compare(look([v('executive', '/admin/x', 'desktop'), slow]), look([v('executive', '/admin/x', 'desktop'), slow]), look([v('executive', '/admin/x', 'desktop'), slow]), { pages: ['/admin/x'], held: { hashes: [] } }, '9999', 'k');
ok(verdict.verdict === 'pass' && !verdict.blocks.length, 'timed out before and after: never a block', verdict);
// #318 (2026-11-27; the CEO's Tier 4 condition): a timeout on a page the release reaches only through a shared file it changes
// (reach.py's expected, not drawn; tv-ask.js) is tried once more after the advocate's whole look. Opened then: compared as
// usual and noted ("far timeout, noted"); timed out again: it blocks. A timeout on a page its own files draw blocks as before.
import { farTimeouts } from '../page-audit/beforeafter.mjs';
const ok200 = v('executive', '/admin/crawler-review.php', 'phone'); const late = v('executive', '/admin/crawler-review.php', 'phone', { status: 0, error: TO, ba: undefined });
const dev = v('executive', '/admin/config/control?tab=development', 'desktop');   // a view compared as usual, as in #318's 174
const exp318 = { pages: ['/admin/config/control?tab=development', '/admin/crawler-review.php', '/admin/other.php'], drawn: ['/admin/config/control?tab=development'], shared: ['website/js/tv-ask.js'], held: { hashes: [] } };
const devTO = v('executive', '/admin/config/control?tab=development', 'desktop', { status: 0, error: TO, ba: undefined });
const gone = v('executive', '/admin/not-reached.php', 'phone', { status: 0, error: TO, ba: undefined });
const slowOne = v('ops-staff', '/admin/other.php', 'phone', { status: 0, error: TO, ba: undefined });
const pick = farTimeouts([dev, late, devTO, gone, slowOne], exp318, new Set(['ops-staff|/admin/other.php|phone']));
ok(JSON.stringify(pick) === '[1]', 'after the look, tried again: only the timeout on an expected page that isn\'t drawn (not a drawn page, not one it doesn\'t reach, not one slow in the first before look)', pick);
ok(farTimeouts([late], { ...exp318, shared: [] }).length === 0 && farTimeouts([late], { pages: exp318.pages }).length === 0, '… none when the release changes no shared file, or reach.py gave no drawn list');
const opened = { ...ok200, late: 'TimeoutError: page.goto: Timeout 45000ms exceeded.' };
const r1 = compare(look([dev, ok200]), look([dev, ok200]), look([dev, opened]), exp318, '318', 'k');
ok(r1.verdict === 'pass' && !r1.blocks.length && r1.notes.some(n => /far timeout, noted: timed out, then opened when tried again after the look \(reached only through website\/js\/tv-ask\.js/.test(n)), 'tried again after the look and it opened: compared as usual, passes, and "far timeout, noted" is kept', r1);
const twice = { ...late, late: 'TimeoutError: page.goto: Timeout 45000ms exceeded.' };
const r2 = compare(look([dev, ok200]), look([dev, ok200]), look([dev, twice]), exp318, '318', 'k');
ok(r2.verdict === 'block' && r2.blocks.some(x => x.rule === 'access' && x.page === '/admin/crawler-review.php') && r2.notes.some(n => /and again when tried after the look .* it blocks/.test(n)), 'tried again after the look and it timed out again: it blocks (the release\'s own change to the shared file could be the hang)', r2);
const devOk = v('executive', '/admin/config/control?tab=development', 'phone'); const devLate = v('executive', '/admin/config/control?tab=development', 'phone', { status: 0, error: TO, ba: undefined });
const r3 = compare(look([dev, devOk]), look([dev, devOk]), look([dev, devLate]), exp318, '318', 'k');
ok(r3.verdict === 'block' && r3.blocks.some(x => x.rule === 'access' && x.size === 'phone'), 'the counter-case: a timeout on a page the release\'s own files draw blocks, as before', r3);
const shut403 = v('executive', '/admin/crawler-review.php', 'phone', { status: 403 });
const r4 = compare(look([dev, ok200]), look([dev, ok200]), look([dev, shut403]), exp318, '318', 'k');
ok(r4.verdict === 'block' && r4.blocks.some(x => x.rule === 'access'), 'a real "doesn\'t open" (403) on a page reached only through a shared file still blocks', r4);
const toSign = v('executive', '/admin/crawler-review.php', 'phone', { ba: { ...ok200.ba, landed_signin: true } });
const r5 = compare(look([dev, ok200]), look([dev, ok200]), look([dev, toSign]), exp318, '318', 'k');
ok(r5.verdict === 'block' && r5.blocks.some(x => x.rule === 'access' && /sign-in/i.test(x.what)), '… and so does a redirect to sign-in', r5);
ok(/--out ba-after\.json[^\n]*--expected before\/ba-expected\.json/.test(wf), 'the workflow gives the after look reach.py\'s expected list (drawn and shared with it)');
console.log(`\n${pass} of ${pass + fail} checks pass`); process.exit(fail ? 1 : 0);
