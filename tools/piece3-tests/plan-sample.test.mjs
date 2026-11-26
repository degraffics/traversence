// The views a look takes (the session manager, 2026-11-26: the busiest release inside about 20 minutes a phase; DB4). Each
// page stays under every rule: every admin page opened by a member (refused), the Executive and the Staff login; every
// public page by the guest and a member (the canary); every member page by the Member advocate. Only how many pages the
// extra member advocates repeat a rule on is sampled. At 6.4 s a view on an open staging (release 9304: 205 views in 1317 s, 2026-11-26; the 1.79 s from run 45802040017 was a closed staging answering 503), two advocates at once (BA_PAR, set to 2 while staging is slow, the session manager 2026-11-26). At two, the busiest release's before phase runs past 20 minutes; the check here is that it ends inside the workflow's 45-minute limit with room (40), and the 20-minute aim is said beside it.
// Run: node tools/piece3-tests/plan-sample.test.mjs
import { plan } from '../page-audit/beforeafter.mjs';
let fails = 0, n = 0; const ok = (c, w) => { n++; console.log((c ? '  ok  ' : 'FAIL  ') + w); if (!c) fails++; };
const acc = { members: Object.fromEntries(['new', 'member', 'contributor', 'steward', 'client', 'owner', 'manager', 'accountant', 'sales', 'viewer'].map(k => [k, 1])), staff: { executive: 1, 'ops-staff': 1 } };
const mk = (pre, k) => Array.from({ length: k }, (_, i) => pre + i);
const cases = [['40 admin pages', mk('/admin/p', 40)], ['40 public pages', mk('/place/p', 40)], ['40 member pages', mk('/user/p', 40)], ['a mix of 14, 13 and 13', [...mk('/admin/p', 14), ...mk('/place/p', 13), ...mk('/user/p', 13)]]];
for (const [name, pages] of cases) {
  const pl = plan(pages, acc); const views = pl.reduce((s, x) => s + x[4].length, 0); const phase = 2 * views * 6.4 / 2 / 60 + 1.5;
  ok(phase <= 40, `${name}: ${views} views, the before phase about ${phase.toFixed(0)} min (inside the job's 45; ${phase <= 20 ? 'within' : 'over'} the 20-minute aim)`);
  const by = pred => new Set(pl.filter(pred).map(x => x[3]));
  const adm = pages.filter(u => u.startsWith('/admin')), pub = pages.filter(u => u.startsWith('/place')), mem = pages.filter(u => u.startsWith('/user'));
  ok(adm.every(u => by(x => x[2] === 'member').has(u) && by(x => x[1] === 'executive').has(u) && by(x => x[1] === 'ops-staff').has(u)), `${name}: every admin page opened by a member, the Executive and the Staff login`);
  ok(pub.every(u => by(x => x[2] === 'guest').has(u) && by(x => x[2] === 'member').has(u)) && mem.every(u => by(x => x[1] === 'member').has(u)), `${name}: every public page by the guest and a member, every member page by the Member advocate`);
}
// the same plan twice (the before and after looks must match view for view)
const p1 = JSON.stringify(plan(cases[3][1], acc)), p2 = JSON.stringify(plan(cases[3][1], acc));
ok(p1 === p2, 'the plan is the same each time it is made (before and after compare view for view)');
// the extra advocates spread: between them they open more admin pages than any one of them
const pl = plan(mk('/admin/p', 40), acc); const extra = new Set(pl.filter(x => x[2] === 'member' && x[1] !== 'member').map(x => x[3]));
ok(extra.size > 2 * 2, 'the extra member advocates start at different pages: ' + extra.size + ' admin pages between them');
console.log(`\n${n - fails} of ${n} checks pass`); process.exit(fails ? 1 : 0);
