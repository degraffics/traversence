// The buying paths' runners (decisions/0181 §1 gate 2; tools/page-audit/paths-staging.mjs) on the design harness
// (handoffs/design-harness: the mirror with QB_ENV=mock in .env, member 2 signed in by test/login-member.php), at 390.
// Run: IDS=plan-buy,shop-buy node tools/path-checker-tests/buy-runners.mjs   (TW_INJECT: a script standing in for
// Tailwind's CDN, optional). Expect each goal true; then the guard: on a site that isn't staging, 0 taps.
import { createRequire } from 'node:module'; const { chromium } = createRequire(import.meta.url)('playwright');
import { readFileSync } from 'node:fs';
import { stagingRunners } from '../page-audit/paths-staging.mjs';
const SITE = 'http://127.0.0.1:8790'; const TW = (process.env.TW_INJECT ? readFileSync(process.env.TW_INJECT, 'utf8') : '');
const firstVisible = async (page, sels) => { for (const s of sels) { const l = page.locator(s).first(); if (await l.isVisible().catch(() => false)) return l; } return null; };
const textOf = async (page) => ((await page.locator('main').first().innerText().catch(() => '')) || (await page.locator('body').innerText().catch(() => ''))).replace(/\s+/g, ' ');
class W { constructor(page) { this.page = page; this.site = SITE; this.taps = 0; this.problems = []; this.notes = []; this.goal = false; this.stuck = ''; }
  here() { const u = new URL(this.page.url()); return u.pathname + u.search; }
  async goto(u) { await this.page.goto(SITE + u, { waitUntil: 'load' }); }
  async tap(loc, label, { wait = 4000, nav = false } = {}) { this.taps++; const l = typeof loc === 'string' ? this.page.locator(loc).first() : loc; if (nav) await Promise.all([this.page.waitForNavigation({ timeout: wait }).catch(() => {}), l.click()]); else { await l.click(); await this.page.waitForTimeout(Math.min(wait, 3000)); } return true; }
  async type(sel, text) { const el = this.page.locator(sel).first(); if (!(await el.isVisible().catch(() => false))) { this.stuck = 'no box ' + sel; return false; } await el.fill(''); await el.type(text, { delay: 10 }); return true; }
  add(k, r, p, what) { this.problems.push({ k, p, what }); } note(n) { this.notes.push(n); } async shot(t) { await this.page.screenshot({ path: 'buy-' + t + '.png' }); } }
const b = await chromium.launch();
for (const id of (process.env.IDS || 'shop-buy,plan-buy').split(',')) {
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 Chrome/130' });
  await ctx.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: TW }));
  const p0 = await ctx.newPage(); await p0.goto(SITE + '/test/login-member.php'); await p0.close();
  const SR = stagingRunners({ as: null, ACC: { staging: { site: SITE } }, firstVisible, textOf, guides: () => [], FULL: false });
  const page = await ctx.newPage(); const errs = []; page.on('pageerror', e => errs.push(String(e).slice(0, 100)));
  const w = new W(page); await SR[id](w, page).catch(e => { w.stuck = 'threw ' + String(e).slice(0, 160); });
  console.log(id, '| goal', w.goal, '| stuck', w.stuck, '| taps', w.taps, '| at', w.here(), '| problems', JSON.stringify(w.problems), '| notes', w.notes.join('; '), '| errs', errs.slice(0, 2));
  await page.screenshot({ path: 'buy-end-' + id + '.png' }); await ctx.close();
}
// the guard: not the staging site → refuses before anything
{ const ctx = await b.newContext(); const page = await ctx.newPage(); const SR = stagingRunners({ as: null, ACC: { staging: { site: 'https://staging.example' } }, firstVisible, textOf, guides: () => [], FULL: false });
  const w = new W(page); await SR['shop-buy'](w, page); console.log('guard on a non-staging site:', w.stuck, '| taps', w.taps); await ctx.close(); }
await b.close();
