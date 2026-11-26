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
console.log(`\n${pass} of ${pass + fail} checks pass`); process.exit(fail ? 1 : 0);
