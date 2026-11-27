// A before look that timed out holds only the part of the page drawn by then (#323's look, 2026-11-27:
// /admin/crawler-review.php at 390, its header but not its "Find any listing to edit" box). Opened in full after, on a page the
// release doesn't reach, it's staging's slowness: noted, not compared, never drift. On a page the release reaches it's still
// compared, and a before look that opened still blocks drift. compare() on its own, no browser.
import { compare } from '../page-audit/beforeafter.mjs';
let pass = 0, fail = 0; const ok = (c, m, got) => { if (c) pass++; else { fail++; console.log('FAIL: ' + m + (got ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const page = (lines, extra = {}) => ({ lines, dlines: [], prose: [], state: [], controls: [], regions: {}, totals: [], cards: 0, rows: 0, recs: 0, pins: 0, overlap: [], clipped: [], offscreen: [], primary: [], nav: 1, canaries: [], words: '', ...extra });
const view = (url, ba, err) => ({ who: 'executive', advocate: 'advocate-staff-views', url, size: 'phone', status: err ? 0 : 200, ...(err ? { error: 'page.goto: Timeout 45000ms exceeded.' } : {}), ba });
const head = ['Review', 'Everything the crawler needs you for'], full = [...head, 'Find any listing to edit'];
const look = (vs) => ({ results: vs, signins: { executive: 'ok' } });
const exp = (pages) => ({ pages, files: ['app/lib/BeforeAfter.php'], held: { hashes: [] } });
// #323's case: the before looks timed out with the header drawn; after, the whole page
let v = compare(look([view('/admin/crawler-review.php', page(head), true)]), look([view('/admin/crawler-review.php', page(head), true)]), look([view('/admin/crawler-review.php', page(full))]), exp(['/admin/config/releases']));
ok(v.verdict !== 'block' && !v.blocks.length, 'a timed-out before look, the page opening in full after, on a page not reached: no block', v.blocks);
ok(v.notes.some(n => /before look timed out .* not compared/.test(n)), '… said in the notes (the IT Manager\'s lane)', v.notes);
// only the first before look timed out: still not a baseline
v = compare(look([view('/admin/crawler-review.php', page(head), true)]), look([view('/admin/crawler-review.php', page(full))]), look([view('/admin/crawler-review.php', page(full))]), exp([]));
ok(!v.blocks.length, '… the first before look timed out, the second opened: still no block', v.blocks);
// a before look that opened, with the box gone after: still drift
v = compare(look([view('/admin/crawler-review.php', page(full))]), look([view('/admin/crawler-review.php', page(full))]), look([view('/admin/crawler-review.php', page([...head, 'Something new']))]), exp([]));
ok(v.blocks.some(b => b.rule === 'drift'), 'before looks that opened, a part changed after: still drift', v.blocks);
// a page the release reaches, its before look timed out: still compared (its after is checked)
v = compare(look([view('/admin/x', page(head), true)]), look([view('/admin/x', page(head), true)]), look([view('/admin/x', page(full, { overlap: ['a over b'] }))]), exp(['/admin/x']));
ok(v.blocks.some(b => b.rule === 'layout'), 'a page the release reaches: compared even when its before look timed out (an overlap after still blocks)', v.blocks);
// after timed out too: the earlier rules (not compared on a page not reached), unchanged
v = compare(look([view('/admin/crawler-review.php', page(head), true)]), look([view('/admin/crawler-review.php', page(head), true)]), look([view('/admin/crawler-review.php', page(head), true)]), exp([]));
ok(!v.blocks.length && v.verdict !== 'block', 'timed out before and after, not reached: as before, no block', v);
console.log(pass + ' of ' + (pass + fail) + ' checks pass'); process.exit(fail ? 1 : 0);
