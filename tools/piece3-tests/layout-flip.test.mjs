// A page whose width at 390 comes and goes with its records (/admin/crawler-review.php: #323's look noted "missed after, from
// a record's own content", then #325's block, met before and missed after, on a release that reached it only through
// js/tv-ask.js). compare() on its own, no browser:
//   1. the second before look (usable) already missed it: a note, never a block;
//   2. the page's records made it miss on an earlier release (priorMissed, page|size, the last 10 verdicts): a note and one
//      ticket for the IT lane, never a block, only on a page reached through a shared file alone, no stylesheet or shell;
//   3. a page the release draws, a stylesheet or the shell, no reach list, another standard: still a block;
//   4. priorMissed counts only "from a record's own content" (never "already missed", never its own notes), before this release.
import { compare, priorMissed } from '../page-audit/beforeafter.mjs';
import { mkdtempSync, writeFileSync } from 'node:fs'; import { tmpdir } from 'node:os'; import path from 'node:path';
let pass = 0, fail = 0; const ok = (c, m, got) => { if (c) pass++; else { fail++; console.log('FAIL: ' + m + (got ? ' — ' + JSON.stringify(got).slice(0, 500) : '')); } };
const page = (lines, extra = {}) => ({ lines, dlines: [], prose: [], state: [], controls: [], regions: {}, totals: [], cards: 0, rows: 0, recs: 0, pins: 0, overlap: [], clipped: [], offscreen: [], primary: [], nav: 1, canaries: [], words: '', ...extra });
const view = (url, ba, err, status) => ({ who: 'executive', advocate: 'advocate-staff-views', url, size: 'phone', status: status ?? (err ? 0 : 200), ...(err ? { error: err === true ? 'page.goto: Timeout 45000ms exceeded.' : err } : {}), ba });
const CR = '/admin/crawler-review.php', K = 'executive|' + CR + '|phone', P = CR + '|phone', S = 'No sideways scroll at 390px (0058 §11)';
const lines = ['Review', 'Find any listing to edit'];
const narrow = (u = CR) => ({ ...view(u, page(lines)), scroll: false }), wide = (u = CR) => ({ ...view(u, page(lines)), scroll: true });
const look = (vs) => ({ results: vs, signins: { executive: 'ok', 'ops-staff': 'ok' } });
// #325's expected: crawler-review reached only through js/tv-ask.js (reach.py: in pages, not drawn)
const exp = (extra = {}) => ({ pages: [CR], drawn: [], shared: ['website/js/tv-ask.js'], files: ['website/js/tv-ask.js'], held: { hashes: [] }, ...extra });
const cmp = (b1, b2, a, e) => compare(look([b1]), look([b2]), look([a]), e, '325');
const H = { missed: { [P]: { [S]: 323 } } };

// #325 as it was: no history, met before (both looks), missed after: a block (unchanged without history)
let v = cmp(narrow(), narrow(), wide(), exp());
ok(v.blocks.some(b => b.rule === 'layout' && b.what === S), '#325 with no history: still a block', v.blocks);
// 1. the second before look missed it
v = cmp(narrow(), wide(), wide(), exp());
ok(!v.blocks.length && v.notes.some(n => n.startsWith(K + ': "' + S + '" missed after and in the second before look')), '1. the second before look missed it: a note, no block', v);
v = cmp(narrow(), { ...wide(), status: 500 }, wide(), exp());
ok(v.blocks.some(b => b.what === S), '1. … but a second before look that answered 500 never excuses it', v.blocks);
// 2. the page's records made it miss on an earlier release (#323): a note and one ticket, for every account on it
v = compare(look([narrow(), { ...narrow(), who: 'ops-staff' }]), look([narrow(), { ...narrow(), who: 'ops-staff' }]), look([wide(), { ...wide(), who: 'ops-staff' }]), exp(H), '325');
ok(!v.blocks.length, '2. #323 noted its records widen it: no block for executive or ops-staff', v.blocks);
ok(v.notes.some(n => n.startsWith(K + ': "' + S + '" missed after; it was missed on this view before too (#323)')), '2. … said, naming #323', v.notes);
ok(v.tickets.length === 1 && v.tickets[0].lane === 'IT Manager (11)' && v.tickets[0].release === '325', '2. … one ticket for the page, not one per account', v.tickets);
// 3. tightenings (the Tier 4 check, 2026-11-28)
v = cmp(narrow(), narrow(), wide(), exp({ ...H, drawn: [CR] }));
ok(v.blocks.some(b => b.what === S), '3. a page the release draws itself still blocks', v.blocks);
v = cmp(narrow(), narrow(), wide(), exp({ ...H, files: ['website/js/tv-ask.js', 'website/css/admin.css'] }));
ok(v.blocks.some(b => b.what === S), '3. a release with a stylesheet still blocks', v.blocks);
v = cmp(narrow(), narrow(), wide(), exp({ ...H, files: ['app/ui/app-shell.php'] }));
ok(v.blocks.some(b => b.what === S), '3. a release with the shell still blocks', v.blocks);
v = cmp(narrow(), narrow(), wide(), exp({ ...H, drawn: undefined }));
ok(v.blocks.some(b => b.what === S), '3. no drawn list (reach not run): still blocks', v.blocks);
v = cmp(narrow(), narrow(), { ...wide(), ...view(CR, page(lines, { overlap: ['a over b'] })), scroll: true }, exp(H));
ok(v.blocks.some(b => b.rule === 'layout' && b.what !== S), '3. an overlap after on that page still blocks', v.blocks);
v = cmp(narrow(), narrow(), wide(), exp({ missed: { [P]: { 'Cards 3px from the screen edge at 390px (0058 §11)': 323 } } }));
ok(v.blocks.some(b => b.what === S), '3. a history of another standard doesn\'t excuse this one', v.blocks);
// 4. priorMissed: only "from a record's own content", the last 10 before this release
const dir = mkdtempSync(path.join(tmpdir(), 'flip-'));
writeFileSync(path.join(dir, '322.json'), JSON.stringify({ notes: ['x|/always|phone: already missed "' + S + '" before (Site health\'s, not this change\'s)'] }));
writeFileSync(path.join(dir, '323.json'), JSON.stringify({ notes: [K + ': "' + S + '" missed after, from a record\'s own content (its data); not this change\'s'] }));
writeFileSync(path.join(dir, '324.json'), JSON.stringify({ notes: [K + ': "' + S + '" missed after; it was missed on this view before too (#323), so it comes and goes'] }));
writeFileSync(path.join(dir, '326.json'), JSON.stringify({ notes: ['x|/later|phone: "' + S + '" missed after, from a record\'s own content'] }));
const m = priorMissed(dir, '325');
ok(m[P]?.[S] === 323, '4. "from a record\'s own content" read, by page and size, with its release', m);
ok(!m['/always|phone'], '4. "already missed … before" (a page that always misses) never counts', m);
ok(!m['/later|phone'], '4. a later release\'s verdict is never read', m);
ok(Object.keys(priorMissed(dir, '')).length === 0, '4. no release number: nothing read', null);
// the repo's own verdicts before #325: crawler-review's phone look from #323
const real = priorMissed('site-audit/results/beforeafter', '325');
ok(real[P]?.[S] === 323, '4. the repo\'s verdicts before #325 name crawler-review at 390 (#323)', real);
console.log(`\n${pass + fail} checks, ${fail} failed`);
process.exit(fail ? 1 : 0);
