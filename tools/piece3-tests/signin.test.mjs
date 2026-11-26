// The verdict when an account couldn't sign in (#304, 2026-11-26: both staff logins failed before and after, the change was
// admin only, and the verdict read pass). An account that alone checks a kind of page (the Member advocate; the Executive
// and the Staff login for admin pages) failing while the change names a page of that kind makes the look incomplete (a
// person looks), never pass. One that isn't alone in it (another member advocate) is a note. A block still wins, and one
// that signed in before and can't after is still an access block (the existing rule).
// Run: node tools/piece3-tests/signin.test.mjs
import { compare } from '../page-audit/beforeafter.mjs';
let fails = 0, n = 0; const ok = (c, w) => { n++; console.log((c ? '  ok  ' : 'FAIL  ') + w); if (!c) fails++; };
const view = (url, who = 'member', extra = {}) => ({ url, who, advocate: 'x', size: 'phone', status: 200, names: 0, alt: 0, dialogs: [], ba: { lines: ['Same'], cards: 2, primary: [], nav: 1, controls: [], regions: {}, canaries: [] }, ...extra });
const shot = (results, signins) => ({ results, signins, seconds: 5 });
const held = { held: { hashes: [] } };
const all = { member: 'ok', contributor: 'ok', executive: 'ok', 'ops-staff': 'ok' };
const staffFail = { ...all, executive: 'fail: code', 'ops-staff': 'fail: code' };
const R = [view('/admin/config/releases'), view('/place/x')];

let v = compare(shot(R, staffFail), shot(R, staffFail), shot(R, staffFail), { pages: ['/admin/config/releases'], ...held }, '304');
ok(v.verdict === 'incomplete', "#304's case: both staff logins failed, the change names an admin page: incomplete (" + v.verdict + ')');
ok(v.notes[0].startsWith('Not checked as executive and ops-staff'), 'and it says so first: ' + v.notes[0]);
v = compare(shot(R, staffFail), shot(R, staffFail), shot(R, staffFail), { pages: ['/place/x'], ...held }, 'a');
ok(v.verdict === 'pass', 'staff failed but the change names only a public page: pass (the staff look at admin pages only)');
v = compare(shot(R, { ...all, executive: 'fail' }), shot(R, all), shot(R, all), { pages: ['/admin/config/releases'], ...held }, 'b');
ok(v.verdict === 'incomplete', 'the Executive failed only before: still incomplete (its before look is missing)');
const mFail = { ...all, member: 'fail' }; v = compare(shot(R, mFail), shot(R, mFail), shot(R, mFail), { pages: ['/user/trips.php'], ...held }, 'c');
ok(v.verdict === 'incomplete', 'the Member advocate failed before and after, and the change names a member page: incomplete (' + v.verdict + ')');
const cFail = { ...all, contributor: 'fail' }; v = compare(shot(R, cFail), shot(R, cFail), shot(R, cFail), { pages: ['/user/trips.php'], ...held }, 'd');
ok(v.verdict === 'pass' && v.notes.some(x => x.startsWith('contributor couldn\'t sign in')), 'another member advocate failed: a note, still pass (the Member advocate covers its pages)');
v = compare(shot(R, all), shot(R, all), shot(R, all), { pages: ['/admin/config/releases'], ...held }, 'e');
ok(v.verdict === 'pass', 'everyone signed in: pass');
v = compare(shot(R, all), shot(R, all), shot(R, { ...all, executive: 'fail' }), { pages: ['/place/x'], ...held }, 'g');
ok(v.verdict === 'block' && v.first.includes('could sign in before and can\'t after'), 'signed in before and not after: still an access block (' + v.first + ')');
const worse = [view('/admin/config/releases', 'member', { ba: { lines: ['Same'], cards: 0, primary: [], nav: 1, controls: [], regions: {}, canaries: [] } }), view('/place/x')];
v = compare(shot(R, staffFail), shot(R, staffFail), shot(worse, staffFail), { pages: ['/admin/config/releases'], ...held }, 'f');
ok(v.verdict === 'block', 'a block still wins over incomplete (' + v.first + ')');
console.log(`\n${n - fails} of ${n} checks pass`); process.exit(fails ? 1 : 0);
