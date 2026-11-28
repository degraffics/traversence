// A page whose width at 390 comes and goes with its data (/admin/crawler-review.php: missed before on #322 and #324, from a
// record's content on #323, then #325's block "No sideways scroll at 390px", met before and missed after, on a release whose
// only file there, js/tv-ask.js, changes nothing a page draws as it loads). compare() on its own, no browser:
//   1. the second before look already missed it: a note, never a block;
//   2. missed on this view in an earlier release's looks: a note and a ticket for the IT Manager's lane, never a block;
//   3. a view that never missed it before still blocks, and so does any other layout standard (overlap, dialogs);
//   4. priorMissed reads the last 10 verdicts' notes, never this release's or later ones.
import { compare, priorMissed } from '../page-audit/beforeafter.mjs';
import { mkdtempSync, writeFileSync } from 'node:fs'; import { tmpdir } from 'node:os'; import path from 'node:path';
let pass = 0, fail = 0; const ok = (c, m, got) => { if (c) pass++; else { fail++; console.log('FAIL: ' + m + (got ? ' — ' + JSON.stringify(got).slice(0, 500) : '')); } };
const page = (lines, extra = {}) => ({ lines, dlines: [], prose: [], state: [], controls: [], regions: {}, totals: [], cards: 0, rows: 0, recs: 0, pins: 0, overlap: [], clipped: [], offscreen: [], primary: [], nav: 1, canaries: [], words: '', ...extra });
const view = (url, ba, err, status) => ({ who: 'executive', advocate: 'advocate-staff-views', url, size: 'phone', status: status ?? (err ? 0 : 200), ...(err ? { error: err === true ? 'page.goto: Timeout 45000ms exceeded.' : err } : {}), ba });
const CR = '/admin/crawler-review.php', K = 'executive|' + CR + '|phone', S = 'No sideways scroll at 390px (0058 §11)';
const lines = ['Review', 'Find any listing to edit'];
const narrow = (u = CR) => ({ ...view(u, page(lines)), scroll: false }), wide = (u = CR) => ({ ...view(u, page(lines)), scroll: true });
const look = (vs) => ({ results: vs, signins: { executive: 'ok' } });
const exp = (extra = {}) => ({ pages: [CR], files: ['website/js/tv-ask.js'], held: { hashes: [] }, ...extra });
const cmp = (b1, b2, a, e) => compare(look([b1]), look([b2]), look([a]), e, '325');

// #325 as it was: no history given, met before (both looks), missed after: a block (the rule unchanged without history)
let v = cmp(narrow(), narrow(), wide(), exp());
ok(v.blocks.some(b => b.rule === 'layout' && b.what === S), '#325 with no history: still a block', v.blocks);
// 1. the second before look missed it
v = cmp(narrow(), wide(), wide(), exp());
ok(!v.blocks.length && v.notes.some(n => n.startsWith(K + ': "' + S + '" missed after and in the second before look')), '1. the second before look missed it: a note, no block', v);
// 2. missed on this view before: a note and a ticket
v = cmp(narrow(), narrow(), wide(), exp({ missed: { [K]: { [S]: 324 } } }));
ok(!v.blocks.length, '2. missed on this view on #324: no block', v.blocks);
ok(v.notes.some(n => n.startsWith(K + ': "' + S + '" missed after; it was missed on this view before too (#324)')), '2. … said, naming #324', v.notes);
ok(v.tickets.some(t => t.lane === 'IT Manager (11)' && /comes and goes with its data \(missed on #324 and #325\)/.test(t.what)), '2. … and a ticket for the IT lane', v.tickets);
// 3. another view (ops-staff) with no history still blocks; another standard on the same view still blocks
const ops = (x) => ({ ...x, who: 'ops-staff' });
v = compare(look([ops(narrow())]), look([ops(narrow())]), look([ops(wide())]), exp({ missed: { [K]: { [S]: 324 } } }), '325');
ok(v.blocks.some(b => b.rule === 'layout' && b.who === 'ops-staff'), '3. a view with no history of it still blocks', v.blocks);
v = cmp(narrow(), narrow(), { ...wide(), ...view(CR, page(lines, { overlap: ['a over b'] })), scroll: true }, exp({ missed: { [K]: { [S]: 324 } } }));
ok(v.blocks.some(b => b.rule === 'layout' && b.what !== S), '3. an overlap after on that view still blocks', v.blocks);
v = cmp(narrow(), narrow(), wide(), exp({ missed: { [K]: { 'Cards 3px from the screen edge at 390px (0058 §11)': 324 } } }));
ok(v.blocks.some(b => b.what === S), '3. a history of another standard doesn\'t excuse this one', v.blocks);
// 4. priorMissed: the last verdicts before this release only
const dir = mkdtempSync(path.join(tmpdir(), 'flip-'));
writeFileSync(path.join(dir, '322.json'), JSON.stringify({ notes: [K + ': already missed "' + S + '" before (Site health\'s, not this change\'s)'] }));
writeFileSync(path.join(dir, '323.json'), JSON.stringify({ notes: ['ops-staff|' + CR + '|phone: "' + S + '" missed after, from a record\'s own content (its data); not this change\'s'] }));
writeFileSync(path.join(dir, '326.json'), JSON.stringify({ notes: ['x|/y|phone: already missed "' + S + '" before (Site health\'s, not this change\'s)'] }));
const m = priorMissed(dir, '325');
ok(m[K]?.[S] === 322, '4. "already missed … before" read, with its release', m);
ok(m['ops-staff|' + CR + '|phone']?.[S] === 323, '4. "missed after, from a record\'s own content" read too', m);
ok(!m['x|/y|phone'], '4. a later release\'s verdict is never read', m);
ok(Object.keys(priorMissed('/nonexistent-dir', '325')).length === 0, '4. no verdicts: nothing', null);
// the real #325 with the real history: run it on the repo's verdicts
const real = priorMissed('site-audit/results/beforeafter', '325');
ok(real[K]?.[S] || real['ops-staff|' + CR + '|phone']?.[S], '4. the repo\'s own verdicts before #325 name crawler-review\'s scroll', real[K]);
console.log(`\n${pass + fail} checks, ${fail} failed`);
process.exit(fail ? 1 : 0);
