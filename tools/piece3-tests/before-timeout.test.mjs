// A before look that timed out holds only the part of the page drawn by then (#323's look, 2026-11-27:
// /admin/crawler-review.php at 390, its header but not its "Find any listing to edit" box). The CTO's Tier 4 (2026-11-27,
// with QA's check): the second before look is used when it opened; when neither did, on a page the release doesn't reach,
// only drift and layout are left out (access, 0066, canaries and errors are always read); any other before error is as it
// was; the 3rd release in a row skipped becomes a ticket for the IT Manager's lane. compare() on its own, no browser.
import { compare, priorSkips } from '../page-audit/beforeafter.mjs';
import { mkdtempSync, writeFileSync } from 'node:fs'; import { tmpdir } from 'node:os'; import path from 'node:path';
let pass = 0, fail = 0; const ok = (c, m, got) => { if (c) pass++; else { fail++; console.log('FAIL: ' + m + (got ? ' — ' + JSON.stringify(got).slice(0, 500) : '')); } };
const page = (lines, extra = {}) => ({ lines, dlines: [], prose: [], state: [], controls: [], regions: {}, totals: [], cards: 0, rows: 0, recs: 0, pins: 0, overlap: [], clipped: [], offscreen: [], primary: [], nav: 1, canaries: [], words: '', ...extra });
const view = (url, ba, err, status) => ({ who: 'executive', advocate: 'advocate-staff-views', url, size: 'phone', status: status ?? (err ? 0 : 200), ...(err ? { error: err === true ? 'page.goto: Timeout 45000ms exceeded.' : err } : {}), ba });
const CR = '/admin/crawler-review.php', K = 'executive|' + CR + '|phone';
const head = ['Review', 'Everything the crawler needs you for'], full = [...head, 'Find any listing to edit'];
const look = (vs) => ({ results: vs, signins: { executive: 'ok' } });
const exp = (pages, skips) => ({ pages, files: ['app/lib/BeforeAfter.php'], held: { hashes: [] }, ...(skips ? { skips } : {}) });
const cmp = (b1, b2, a, e) => compare(look([b1]), look([b2]), look([a]), e);
// 1. #323's case: both before looks timed out with the header drawn; after, the whole page
let v = cmp(view(CR, page(head), true), view(CR, page(head), true), view(CR, page(full)), exp(['/admin/config/releases']));
ok(!v.blocks.length, 'both before looks timed out, the page opening in full after, not reached: no drift', v.blocks);
ok(v.notes.some(n => n.startsWith(K + ': its before looks timed out') && /drift and layout not compared \(1 in a row; staging/.test(n)), '… said, with its count in a row', v.notes);
// 2. the first timed out, the second opened in full: compared with the second (a real change still blocks)
v = cmp(view(CR, page(head), true), view(CR, page(full)), view(CR, page(full)), exp([]));
ok(!v.blocks.length && !v.notes.some(n => /timed out/.test(n)), 'the second before look opened: compared with it, the same page passes', v);
v = cmp(view(CR, page(head), true), view(CR, page(full)), view(CR, page([...head, 'Something new'])), exp([]));
ok(v.blocks.some(b => b.rule === 'drift'), '… and a part changed after it still blocks drift', v.blocks);
// 3. before looks that opened, a part changed: drift as ever
v = cmp(view(CR, page(full)), view(CR, page(full)), view(CR, page([...head, 'Something new'])), exp([]));
ok(v.blocks.some(b => b.rule === 'drift'), 'before looks that opened, a part changed after: drift', v.blocks);
// 4. a canary (another account's email) on a skipped page: access still blocks
v = cmp(view(CR, page(head), true), view(CR, page(head), true), view(CR, page(full, { canaries: ['member'] })), exp([]));
ok(v.blocks.some(b => b.rule === 'access' && /check\+member@/.test(b.after)), 'a skipped page showing another account\'s email: access still blocks', v.blocks);
// 5. a before error that isn't a timeout (a network error with part of a page): compared as before, not skipped
v = cmp(view(CR, page(head), 'net::ERR_CONNECTION_RESET'), view(CR, page(head), 'net::ERR_CONNECTION_RESET'), view(CR, page(full)), exp([]));
ok(!v.notes.some(n => /its before looks timed out/.test(n)), 'a network error before: never the timeout skip', v.notes);
// … a 500 before: the earlier rule (no usable before look), unchanged
v = cmp(view(CR, page(head), null, 500), view(CR, page(head), null, 500), view(CR, page(full)), exp([]));
ok(v.notes.some(n => /before look wasn't usable \(staging answered 500\)/.test(n)) && !v.notes.some(n => /its before looks timed out/.test(n)), 'a 500 before: "wasn\'t usable", as before', v.notes);
// 6. a page the release reaches: compared even when its before looks timed out (an overlap after still blocks)
v = cmp(view('/admin/x', page(head), true), view('/admin/x', page(head), true), view('/admin/x', page(full, { overlap: ['a over b'] })), exp(['/admin/x']));
ok(v.blocks.some(b => b.rule === 'layout'), 'a page the release reaches: still compared', v.blocks);
// 7. no page unchecked forever: the 3rd in a row is a ticket for the IT Manager's lane
v = cmp(view(CR, page(head), true), view(CR, page(head), true), view(CR, page(full)), exp([], { [K]: 2 }));
ok(v.notes.some(n => /3 in a row: a ticket for the IT Manager's \(11\) lane/.test(n)) && v.tickets.length === 1 && v.tickets[0].lane === 'IT Manager (11)', 'the 3rd release in a row: the note says so and the verdict carries a ticket', v);
const dir = mkdtempSync(path.join(tmpdir(), 'bav-'));
const note = { notes: [K + ': its before looks timed out (part of the page drawn) …'] };
writeFileSync(path.join(dir, '320.json'), JSON.stringify({ notes: [] })); writeFileSync(path.join(dir, '321.json'), JSON.stringify(note)); writeFileSync(path.join(dir, '322.json'), JSON.stringify(note)); writeFileSync(path.join(dir, '330.json'), JSON.stringify(note));
const sk = priorSkips(dir, '323');
ok(sk[K] === 2, 'the count is read from the earlier verdicts, in a row (321, 322; 320 breaks it; a later 330 isn\'t earlier)', sk);
ok(Object.keys(priorSkips(dir, '321')).length === 0 && JSON.stringify(priorSkips('/nonexistent', '9')) === '{}', '… none before, or no folder: none', priorSkips(dir, '321'));
console.log(pass + ' of ' + (pass + fail) + ' checks pass'); process.exit(fail ? 1 : 0);
