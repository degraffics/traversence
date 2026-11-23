// tools/page-audit/tools-check.mjs — TOOLS, the check register's "Each tool and endpoint, used by every type of account"
// (handoffs/control-board-plan/step3/sections/check-register.md, row TOOLS; decisions/0145 §1 "Function and tool checks").
// Target (approved, Jason, 2026-11-22): every tool does what site-audit/tools.md says, for every account that may use it,
// and is refused with the reason for every account that may not.
//
// This is the register's run 3 part, "read-only tools daily" (run 4, tools that change anything, is staging only, per release:
// not here). The inventory is built from the code (0145 §1: "built from the code"): every admin tool in the live mirror's
// app/admin/tools.php, called the way its list is drawn (GET /admin/api/<module>/<tool>?action=list, the read every admin
// section makes when it opens), as each account:
//   - signed out and as a member: refused with a reason (api.php: "Staff only.");
//   - as every staff login in CHECK_ACCOUNTS: used (its rows) or refused with the reason ("Needs a Manager or above in
//     Sales"), compared with the tool's rule in the code (its 'view', else its module's departments; Access::can's levels);
//   - as the first login that may, with bad input (impossible paging, sort and stage values) and empty input (no action):
//     a clean answer in the site's one format, never a server error (0145 §1: "with good input, bad input and empty input").
// A tool fails when it answers with a server error or not in the one format, refuses without a reason, or lets in someone
// signed out or a member (0145 §1: "lets in someone it shouldn't fails"). A staff answer that differs from the code's rule is
// "partial" (a rule saved in System Configuration → Permissions can change who may). A refusal with the reason, where the
// rule says so, passes. A tool that lets in whom it should is "partial" until site-audit/tools.md exists to say what its
// result should be: the target compares against it, and it isn't written yet.
// The business portal's tools (business/…) are reached in the portal, not this endpoint: listed as "no check".
//
//   SITE=https://traversence.com CHECK_ACCOUNTS='{…}' TOOLS_PHP=mirror/sync-app/admin/tools.php \
//   WORKSPACES=mirror/sync-app/lib/Workspaces.php ACCESS_PHP=mirror/sync-app/admin/Access.php node tools/page-audit/tools-check.mjs
//
// Only GETs (and the staff sign-in the other checks make). One at a time, GAP_MS (default 1000) apart; TOOLS_BUDGET_S
// (default 1500) keeps the run short; what's left is "no check" with the reason. Writes site-audit/results/tools.json.
import { chromium } from 'playwright';
import { readFileSync, existsSync } from 'node:fs';
import { SITE, ACC, memberIn, staffIn, checkContext } from './check-kit.mjs';
import { row, writeResults, Meter, backoff, RUN_URL, isMain } from './load-check.mjs';

const read = (f) => { try { return readFileSync(f, 'utf8'); } catch { return ''; } };
const TOOLS_PHP = process.env.TOOLS_PHP || 'mirror/sync-app/admin/tools.php';
const INVENTORY = process.env.TOOLS_MD || 'site-audit/tools.md';

/** The tools from tools.php: key and its 'view' rule (null when it takes its module's default). */
export function inventory(src) {
  const out = []; const lines = src.split('\n'); let cur = null;
  for (const line of lines) {
    const m = line.match(/^    '([a-z]+\/[a-z0-9_-]+)' => \[/);
    if (m) { cur = { key: m[1], view: null }; out.push(cur); continue; }
    if (cur && !cur.view) {
      const v = line.match(/'view' => \['min' => '([a-z]+)'(?:, 'depts' => \[([^\]]*)\])?\]/);
      if (v) cur.view = { min: v[1], depts: (v[2] || '').match(/'([a-z]+)'/g)?.map((s) => s.slice(1, -1)) || [] };
    }
  }
  return out;
}
/** Workspaces::DEPARTMENTS (dept => modules) and Access::RANK from the mirror, for the code's default rule. */
export function rules(ws, acc) {
  const depts = {};
  for (const m of ws.matchAll(/'([a-z]+)' => \['[^']+', \[([^\]]*)\]\]/g)) depts[m[1]] = (m[2].match(/'([a-z]+)'/g) || []).map((s) => s.slice(1, -1));
  const rank = {}; const r = acc.match(/RANK = \[([^\]]*)\]/);
  if (r) for (const m of r[1].matchAll(/'([a-z]+)' => (\d+)/g)) rank[m[1]] = +m[2];
  return { depts, rank };
}
/** May this staff login use the tool, by the code's rule? (Access::can: executives everything; level, then department.) */
export function mayByCode(tool, acct, R) {
  if (!Object.keys(R.rank).length) return null;
  const lv = acct.level || ''; const dep = acct.department || '';
  const rank = R.rank[lv] ?? 0;
  if (rank >= (R.rank.executive ?? 5)) return true;
  const mod = tool.key.split('/')[0];
  const rule = tool.view || { min: 'staff', depts: Object.keys(R.depts).filter((d) => R.depts[d].includes(mod)) };
  return rank >= (R.rank[rule.min] ?? 2) && (!rule.depts.length || rule.depts.includes(dep));
}

/** What the answer was: used (its rows), refused (with its reason), or broken. */
export function classify(status, body) {
  let j = null; try { j = JSON.parse(body); } catch {}
  if (!j || typeof j !== 'object' || typeof j.ok !== 'boolean') return { answer: 'broken', status, why: `not the site's one answer format (${String(body).slice(0, 80).replace(/\s+/g, ' ')})` };
  if (status >= 500) return { answer: 'broken', status, why: j.error?.message || 'a server error' };
  if (j.ok) return { answer: 'used', status, rows: Array.isArray(j.rows) ? j.rows.length : null, total: j.total ?? null };
  const msg = String(j.error?.message || '').trim();
  if (!msg) return { answer: 'broken', status, why: 'refused without a reason' };
  return { answer: 'refused', status, reason: msg };
}

export async function main() {
  const meter = new Meter('TOOLS', 'full');
  const held = backoff();
  if (held) { writeResults('TOOLS', [], meter.summary({ skipped: `backing off until ${held.until}` })); console.log('Backing off.'); return; }
  const tools = inventory(read(TOOLS_PHP));
  const R = rules(read(process.env.WORKSPACES || 'mirror/sync-app/lib/Workspaces.php'), read(process.env.ACCESS_PHP || 'mirror/sync-app/admin/Access.php'));
  const hasInventory = existsSync(INVENTORY);
  const rows = [];
  const extra = { inventory: hasInventory ? INVENTORY : `${INVENTORY} doesn't exist yet: each tool is checked for its answer and who may use it, not yet for what its result should be`,
    tools: tools.length, from: TOOLS_PHP };
  if (!tools.length) {
    rows.push(row('TOOLS', '(the tool inventory)', null, 'no check', null, null, { why: `no tools read from ${TOOLS_PHP} (the mirror's app/admin/tools.php)` }));
    writeResults('TOOLS', rows, meter.summary(), extra); console.log('No tools read.'); return;
  }
  const portal = tools.filter((t) => t.key.startsWith('business/'));
  const admin = tools.filter((t) => !t.key.startsWith('business/'));
  for (const t of portal) rows.push(row('TOOLS', `/admin/api/${t.key}`, null, 'no check', null, null, { why: 'a business portal tool: reached in the portal, not through the admin\'s endpoint' }));

  const BUDGET = Date.now() + 1000 * +(process.env.TOOLS_BUDGET_S || 1500);
  const browser = await chromium.launch();
  const accounts = [['guest', null, null]];
  if (ACC?.members?.member) accounts.push(['member', (ctx) => memberIn(ctx, ACC.members.member), null]);
  for (const [k, a] of Object.entries(ACC?.staff || {})) accounts.push(['staff-' + k, (ctx) => staffIn(ctx, a), a]);
  if (!ACC) extra.accounts = 'CHECK_ACCOUNTS isn\'t set: signed out only';
  const firstMay = {};   // the first staff login each tool let in: it gets the bad and empty input

  const get = async (ctx, url) => {
    await meter.pace(); meter.calls++;
    try { const r = await ctx.request.get(SITE + url, { timeout: 30000, maxRedirects: 0 }); return [r.status(), await r.text()]; }
    catch (e) { return [0, String(e).slice(0, 120)]; }
  };
  for (const [who, signIn, acct] of accounts) {
    const ctx = await checkContext(browser); meter.attach(ctx);
    try { if (signIn) { await meter.pace(); await signIn(ctx); } }
    catch (e) { for (const t of admin) rows.push(row('TOOLS', `/admin/api/${t.key}`, who, 'no check', null, null, { why: 'sign-in failed: ' + String(e).slice(0, 120) })); await ctx.close(); continue; }
    for (const t of admin) {
      const item = `/admin/api/${t.key}`;
      if (Date.now() > BUDGET) { rows.push(row('TOOLS', item, who, 'no check', null, null, { why: 'out of the run\'s time (TOOLS_BUDGET_S)' })); continue; }
      const [status, body] = await get(ctx, `${item}?action=list&per=10`);
      const a = classify(status, body);
      const expected = !acct ? false : mayByCode(t, acct, R);
      a.expected = expected === null ? 'unknown (the code\'s levels weren\'t read)' : expected ? 'may' : 'may not';
      a.rule = t.view || 'its module\'s departments';
      let result;
      if (a.answer === 'broken') result = 'fail';
      else if (!acct && a.answer === 'used') { result = 'fail'; a.why = 'let in someone who isn\'t staff'; }
      else if (acct && expected !== null && (a.answer === 'used') !== expected) { result = 'partial'; a.why = `${a.answer} where the code's rule says ${a.expected}: a rule saved in System Configuration → Permissions may have changed it`; }
      else if (a.answer === 'refused') result = 'pass';   // refused with the reason, as the rule says: the target's second half, fully read
      else result = 'partial';   // let in, as the rule says: what it should return waits on site-audit/tools.md
      if (result === 'partial' && !a.why) a.why = hasInventory ? 'its expected result isn\'t compared yet' : 'site-audit/tools.md doesn\'t exist yet';
      rows.push(row('TOOLS', item, who, result, a, RUN_URL || null, { input: 'good' }));
      if (acct && a.answer === 'used' && !firstMay[t.key]) firstMay[t.key] = who;
    }
    // bad and empty input, for each tool, as the first login it let in (still only reads)
    for (const t of admin) {
      if (firstMay[t.key] !== who) continue;
      const item = `/admin/api/${t.key}`;
      for (const [input, q] of [['bad', '?action=list&per=7&page=-3&sort=__none__&dir=sideways&stage=__none__'], ['empty', '?action=']]) {
        if (Date.now() > BUDGET) { rows.push(row('TOOLS', item, who, 'no check', null, null, { input, why: 'out of the run\'s time (TOOLS_BUDGET_S)' })); continue; }
        const [status, body] = await get(ctx, item + q);
        const a = classify(status, body);
        // bad input: the tool copes (its rows, or a refusal saying why); empty input (no action): a clean refusal, never a 5xx
        const ok = a.answer !== 'broken';
        rows.push(row('TOOLS', item, who, ok ? 'pass' : 'fail', a, RUN_URL || null, { input }));
      }
    }
    await ctx.close();
  }
  await browser.close();
  writeResults('TOOLS', rows, meter.summary({ accounts: accounts.length }), extra);
  const c = {}; for (const r of rows) c[r.result] = (c[r.result] || 0) + 1;
  console.log(`TOOLS: ${admin.length} admin tools × ${accounts.length} accounts; ${JSON.stringify(c)}; ${meter.calls} calls.`);
}

if (isMain(import.meta.url)) await main();
