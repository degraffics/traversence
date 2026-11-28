// tools/page-audit/check-kit.mjs — what the browser checks share (decisions/0145): the site, the test accounts from the
// CHECK_ACCOUNTS block, signing in as a member (with the checks' token past the human check) or as staff (password and
// a two-step code made from the login's key), and a browser context that's never counted as a visit.
// Used by rendered.mjs (the page checks) and paths.mjs (the paths). Nothing here changes anything on the site.
import { createHmac } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

export const SITE = (process.env.SITE || 'https://traversence.com').replace(/\/$/, '');
export let ACC = null;
try { ACC = process.env.CHECK_ACCOUNTS ? JSON.parse(process.env.CHECK_ACCOUNTS) : null; } catch { console.log('::warning::CHECK_ACCOUNTS isn\'t valid JSON: signed-out checks only.'); }
export const CHECK_TOKEN = ACC?.health_token || process.env.HEALTH_TOKEN || '';
// staging (decisions/0145 Phase 3): its address, viewing password (HTTP Basic) and own health token join the block once
// Jason presses Set up staging; the same test sign-ins work there
export const STAGING = ACC?.staging?.site ? { site: String(ACC.staging.site).replace(/\/$/, ''), user: ACC.staging.user || '', password: ACC.staging.password || '', token: ACC.staging.health_token || '' } : null;
export const UA = 'Traversence site checks (support@traversence.com)';

function base32(s) { const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = ''; for (const c of s.toUpperCase().replace(/[^A-Z2-7]/g, '')) bits += a.indexOf(c).toString(2).padStart(5, '0'); const out = []; for (let i = 0; i + 8 <= bits.length; i += 8) out.push(parseInt(bits.slice(i, i + 8), 2)); return Buffer.from(out); }
export function totp(secret, step = Math.floor(Date.now() / 30000)) {
  const b = Buffer.alloc(8); b.writeUInt32BE(Math.floor(step / 2 ** 32), 0); b.writeUInt32BE(step >>> 0, 4);
  const h = createHmac('sha1', base32(secret)).update(b).digest(); const o = h[19] & 15;
  return String((((h[o] & 127) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3]) % 1e6).padStart(6, '0');
}

/** A member signs in through the API (the checks' token skips the human check for a check+…@ address). */
export async function memberIn(ctx, a, site = SITE, token = CHECK_TOKEN) {
  const s = await ctx.request.get(`${site}/api/auth/session.php`); const csrf = (await s.json()).csrf_token || '';
  const r = await ctx.request.post(`${site}/api/auth/login.php`, { headers: { 'X-CSRF-Token': csrf, 'X-Check-Token': token, 'Content-Type': 'application/json' }, data: { email: a.email, password: a.password } });
  if (!r.ok()) throw new Error(`member sign-in ${a.email}: ${r.status()} ${(await r.text()).slice(0, 160)}`);
}

// The site takes each two-step code once: a code from a 30-second step at or before the login's last used one is
// refused as "That code doesn't match" (StaffLogin::totpMatch, totp_last_step). The checks sign the same staff logins in
// several times a run (rendered, tools, load, paths), so a step is never reused here: the last one used per login is
// kept, in this process and in a file the run's other steps share, and a sign-in waits for the next step if needed.
const STEP_FILE = join(tmpdir(), 'tv-staff-steps.json');
const readSteps = () => { try { return JSON.parse(readFileSync(STEP_FILE, 'utf8')) || {}; } catch { return {}; } };
async function freshStep(id) {
  const last = Number(readSteps()[id] || 0);
  let step = Math.floor(Date.now() / 30000);
  if (step <= last) { await new Promise(r => setTimeout(r, (last + 1) * 30000 - Date.now() + 400)); step = Math.floor(Date.now() / 30000); }
  const all = readSteps(); all[id] = step; try { writeFileSync(STEP_FILE, JSON.stringify(all)); } catch {}
  return step;
}

/** A staff login signs in on the admin's page: staff ID and password, then the two-step code (a fresh step; once more on
 * the next step if another check took this one). */
export async function staffIn(ctx, a, site = SITE) {
  const p = await ctx.newPage();
  // up to 5 steps, starting after a random 0–25 s (the session manager, 2026-11-28: #331–#333's looks ran at once on other
  // runners, each spent the same one-time code and all but one failed to sign in; a step file can't reach another runner)
  await new Promise(r => setTimeout(r, Math.floor(Math.random() * 25000)));
  for (let tries = 0; tries < 5; tries++) {
    if (!(await p.$('#code'))) {
      await p.goto(`${site}/admin/login.php`, { waitUntil: 'domcontentloaded' });
      await p.fill('#sid', a.staff_id); await p.fill('#pw', a.password);
      await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }), p.click('button.b')]);   // the password step reloads the page with the code box
    }
    if (!(await p.$('#code'))) break;
    const code = totp(a.totp, await freshStep(a.staff_id));
    // the code box sends itself on the 6th digit (js/code-link.js, 3f A11, 2026-11-22), so wait for that first; press
    // Sign in only if the page is still on the code step with the code in it (an older page, or the script not loaded)
    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}), p.fill('#code', code)]);
    if (await p.$('#code') && (await p.inputValue('#code').catch(() => '')) === code) await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {}), p.click('button.b')]);
    if (!/\/admin\/login\.php/.test(p.url())) break;
  }
  const ok = !/\/admin\/login\.php/.test(p.url());
  const said = ok ? '' : ((await p.locator('p.no[role=alert], [data-tv-flash], [data-tv-status="bad"]').first().innerText().catch(() => '')) || '').slice(0, 160);
  await p.close();
  if (!ok) throw new Error(`staff sign-in ${a.staff_id} didn't get past the sign-in page${said ? ': ' + said : ''}`);
}

/** A fresh browser context for the checks: never counted as a visit; on staging, with its viewing password. */
export async function checkContext(browser, opts = {}) {
  const site = opts.site || SITE; const token = opts.token ?? CHECK_TOKEN;
  const ctx = await browser.newContext({ userAgent: UA, extraHTTPHeaders: token ? { 'X-Check-Token': token } : {},
    ...(opts.staging ? { httpCredentials: { username: opts.staging.user, password: opts.staging.password } } : {}), ...(opts.context || {}) });
  await ctx.addCookies([{ name: 'tv_nocount', value: '1', url: site }]);   // the checks are never counted as visits
  return ctx;
}
