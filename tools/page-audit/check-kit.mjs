// tools/page-audit/check-kit.mjs — what the browser checks share (decisions/0145): the site, the test accounts from the
// CHECK_ACCOUNTS block, signing in as a member (with the checks' token past the human check) or as staff (password and
// a two-step code made from the login's key), and a browser context that's never counted as a visit.
// Used by rendered.mjs (the page checks) and paths.mjs (the paths). Nothing here changes anything on the site.
import { createHmac } from 'node:crypto';

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

/** A staff login signs in on the admin's page: staff ID and password, then the two-step code. */
export async function staffIn(ctx, a, site = SITE) {
  const p = await ctx.newPage();
  await p.goto(`${site}/admin/login.php`, { waitUntil: 'domcontentloaded' });
  await p.fill('#sid', a.staff_id); await p.fill('#pw', a.password);
  await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }), p.click('button.b')]);   // the password step reloads the page with the code box
  if (await p.$('#code')) {
    await p.fill('#code', totp(a.totp));
    await Promise.all([p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => {}), p.click('button.b')]);
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
