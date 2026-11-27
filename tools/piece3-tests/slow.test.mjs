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
// the CEO's three (amended condition): 1. slow, then opens on the 90 s retry: a note, its time against the before look's
const b38 = { ...ok200, ms: 38000 }; const desk = v('executive', '/admin/crawler-review.php', 'desktop', { ms: 21000 });
const opened = { ...ok200, ms: 62000, late: 'TimeoutError: page.goto: Timeout 45000ms exceeded.' };
const r1 = compare(look([dev, desk, b38]), look([dev, desk, b38]), look([dev, desk, opened]), exp318, '318', 'k');
ok(r1.verdict === 'pass' && !r1.blocks.length && r1.notes.some(n => /far timeout, noted: timed out, then opened in 62 s when tried again after the look with 90 s \(the before looks: 38 s; at 1280 it opened in the same run \(21 s\); reached only through website\/js\/tv-ask\.js/.test(n)), '1. slow, then opened on the 90 s retry: a note, with its time, the before look\'s, and the other width in the same run', r1.notes);
// 2. hung before (it needed its retry) and after, even at 90 s: slowness already there, a note
const hungAfter = { ...late, late: 'TimeoutError: page.goto: Timeout 45000ms exceeded.' };
const slowB = { ...ok200, retried: TO, ms: 44000 };
const r2 = compare(look([dev, slowB]), look([dev, slowB]), look([dev, hungAfter]), exp318, '318', 'k');
ok(r2.verdict === 'pass' && !r2.blocks.length && r2.notes.some(n => /far timeout, noted: didn't open after, even with 90 s, but the before looks were slow there too \(44 s after a retry\): slowness already there/.test(n)), '2. hung in the before look too (it needed its retry) and after even at 90 s: pre-existing, a note', r2.notes);
const r2b = compare(look([dev, b38]), look([dev, { ...late }]), look([dev, hungAfter]), exp318, '318', 'k');
ok(r2b.verdict === 'pass' && r2b.notes.some(n => /before looks were slow there too \(38 s, then timed out\)/.test(n)), '… the second before look timing out counts too', r2b.notes);
// 3. opened before, hung after even at 90 s: it blocks
const r3x = compare(look([dev, desk, b38]), look([dev, desk, b38]), look([dev, desk, hungAfter]), exp318, '318', 'k');
ok(r3x.verdict === 'block' && r3x.blocks.some(x => x.rule === 'access' && x.page === '/admin/crawler-review.php') && r3x.notes.some(n => /didn't open after, even with 90 s .* though it opened before \(38 s; at 1280 it opened in the same run \(21 s\); reached through website\/js\/tv-ask\.js, which this release changes\): it blocks/.test(n)), '3. opened before, hung after even at 90 s: it blocks, the note saying why', r3x);
// the verdict's far list, for the release's card (the site writes the sentence): what happened to each, never a block's
ok(r1.far.length === 1 && r1.far[0].outcome === 'opened' && r1.far[0].page === '/admin/crawler-review.php' && r1.far[0].size === 'phone' && r1.far[0].who === 'executive' && r1.far[0].ms === 62000 && r1.far[0].via[0] === 'website/js/tv-ask.js', 'the verdict\'s far list: opened, with who, page, width, time and the shared file', r1.far);
ok(r2.far.length === 1 && r2.far[0].outcome === 'before_too' && r3x.far[0].outcome === 'blocks', '… slow before too: before_too; hung after a clean before: blocks', [r2.far, r3x.far]);
const hungB = { ...late }; const r6 = compare(look([dev, hungB]), look([dev, hungB]), look([dev, { ...late }]), exp318, '318', 'k');
ok(r6.verdict === 'pass' && r6.notes.some(n => /timed out in the before look and after: slowness already there/.test(n)) && r6.far.length === 1 && r6.far[0].outcome === 'before_too', 'timed out in the first before look and after (so not tried again): a note and before_too, never a block', r6);
const LATE = (await import('../page-audit/beforeafter.mjs')).LATE_MS;
ok(LATE === 90000 && /timeout: LATE_MS/.test(src) && /timeout: opts\.timeout \|\| 45000/.test(readFileSync(new URL('../page-audit/page-check.mjs', import.meta.url), 'utf8')), 'the late retry is given 90 s (double a view\'s 45 s); every other view keeps 45 s');
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
