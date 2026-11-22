// tools/page-audit/load-check.mjs — LOAD, the check register's load time for every page against a target
// (handoffs/control-board-plan/step3/sections/check-register.md, row LOAD; decisions/0145 §1; target approved by Jason,
// 2026-11-22: Google's Core Web Vitals "good", LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms, each at the 75th percentile).
// It is also the one page pass PHOTOS-SHOWN (photos-shown.mjs) and BRAND (brand-check.mjs) ride on, so each page is
// opened once for the three checks, not three times (the register's "How often": run 3 is "a page at a time").
//
//   Hourly light probe (register run 2: "LOAD (key pages)", "about 20, read-only, signed out, one at a time with a 1 s gap"):
//     SITE=https://traversence.com LOAD_MODE=light node tools/page-audit/load-check.mjs
//   Daily, after the rendered check (register run 3: "every page daily"), with the other two checks on the same visits:
//     SITE=… CHECK_ACCOUNTS='{…}' LOAD_MODE=full ALSO=PHOTOS-SHOWN,BRAND node tools/page-audit/load-check.mjs
//
// Pages: light: LOAD_PAGES (comma-separated; default the home page, Discovery, Get Local and the sign-in page), signed out.
// Full: every page the rendered check opened (site-audit/results/rendered.json), each once at 390 and 1280, as the first
// account that opened it (signed out first), so no page list is kept twice; without rendered.json, the core pages.
// Only reads: GETs, page loads, scrolling and the Tab key (focus only). Never clicks, posts, signs up or orders.
// Paced: GAP_MS (default 1000) between page loads, one at a time (register: "one at a time with a 1 s gap").
// Backs off when the audit says so (audit-sample.py writes site-audit/results/audit.json's backoff; register: "If a run
// slows the site … the next run backs off (an hour later, then two)").
//
// Writes site-audit/results/load.json (and photos-shown.json, brand.json when ALSO names them) in the register's one
// results format (check, item, who, target, result, measured, at, evidence, finding), plus each run's own load ("run").
// Lab numbers: a GitHub runner's headless Chromium, no throttling; the browser cache is warm after each account's first page.
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { pathToFileURL } from 'node:url';
import { SITE, ACC, memberIn, staffIn, checkContext } from './check-kit.mjs';

export const RES = process.env.RESULTS_DIR || 'site-audit/results';
export const RUN_URL = process.env.GITHUB_RUN_ID ? `${process.env.GITHUB_SERVER_URL || 'https://github.com'}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}` : '';
export const GAP_MS = Math.max(1000, +(process.env.GAP_MS || 1000));   // never under the register's 1 s
const SIZES = { phone: { width: 390, height: 844 }, desktop: { width: 1280, height: 900 } };

// The register's checks: name, target and its source, as check-register.json states them (approved, Jason, 2026-11-22)
export const REGISTER = {
  LOAD: { name: 'Load time for every page against a target', file: 'load.json',
    target: 'Core Web Vitals "good": LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms, each at the 75th percentile',
    target_source: 'Google\'s Core Web Vitals thresholds; the board\'s "Pages load fast" (about 2.5 s, des)' },
  'PHOTOS-SHOWN': { name: 'Every photo on a page shown', file: 'photos-shown.json',
    target: 'every image on the page loaded and drawn (decoded, wider than 0) and with a text alternative',
    target_source: 'Jason, 2026-11-22 ("the photo loads and display"); W3C WCAG 2.2 SC 1.1.1 Non-text Content' },
  BRAND: { name: 'The brand and design standards on every page', file: 'brand.json',
    target: 'the readable standards of brand.md met: its tokens, one header per view, filters as one dropdown, card anatomy, icons; text contrast at least 4.5:1, focus visible',
    target_source: 'brand.md §4; design/css-theme-brand.md; CLAUDE.md; W3C WCAG 2.2 SC 1.4.3 Contrast (Minimum) and 2.4.7 Focus Visible' },
  TOOLS: { name: 'Each tool and endpoint, used by every type of account', file: 'tools.json',
    target: 'every tool does what site-audit/tools.md says, for every account that may use it, and is refused with the reason for every account that may not',
    target_source: 'decisions/0145 §1 ("Function and tool checks")' },
  AUDIT: { name: 'The audit\'s own load: what each run asks of the site', file: 'audit.json',
    target: 'a light run asks at most about 20 requests, one at a time with a 1 s gap; the probe pages\' response times stay within 20% of their 7-day median, or the next run backs off',
    target_source: 'Jason, 2026-11-22 (the schedule below); the 20% is ours, approved with the schedule' },
};
const CWV = { lcp: 2500, cls: 0.1, inp: 200 };   // Google's "good" thresholds (the LOAD target)

export const isMain = (url) => !!process.argv[1] && url === pathToFileURL(process.argv[1]).href;
const sha = (s) => createHash('sha1').update(s).digest('hex');
/** A finding id like findings.py's ('f-' + 10 hex), stable for the check and the item. */
export const fid = (check, item) => 'f-' + sha(`${check.toLowerCase()}|${item}`).slice(0, 10);
/** One row of the register's one results format (check-register.md "One results format"). */
// item is the bare route (as in site-audit/pages.md), so the gauges (gauges.py) match rows by route; the size, the input
// and why ride beside it. The finding id also takes the size and input, so each row's failure has its own id.
export function row(check, item, who, result, measured, evidence = RUN_URL || null, extra = {}) {
  const key = [item, extra.size, extra.input].filter(Boolean).join(' ');
  return { check, item, who: who || null, target: REGISTER[check].target, result, measured, at: new Date().toISOString().replace(/\.\d+Z$/, 'Z'),
    evidence, finding: result === 'fail' ? fid(check, key) : null, ...extra };
}
export const p75 = (xs) => { const a = xs.filter((x) => typeof x === 'number' && isFinite(x)).sort((x, y) => x - y); if (!a.length) return null; return a[Math.min(a.length - 1, Math.ceil(0.75 * a.length) - 1)]; };

/** What a run asks of the site (the AUDIT row): our own page loads and calls, paced one at a time; what the browser fetched for them. */
export class Meter {
  constructor(check, mode) { Object.assign(this, { id: `${check.toLowerCase()}-${mode}-${Date.now()}`, check, mode, t0: Date.now(), views: 0, calls: 0, sub: 0, last: 0, gaps: [] }); }
  async pace() {   // one at a time with at least GAP_MS between the starts of our own requests
    const now = Date.now();
    if (this.last) { const wait = GAP_MS - (now - this.last); if (wait > 0) await new Promise((r) => setTimeout(r, wait)); this.gaps.push(Date.now() - this.last); }
    this.last = Date.now();
  }
  attach(ctx) {   // everything the browser asks of our site: documents are page views, the rest is what they load
    const host = new URL(SITE).host;
    ctx.on('request', (r) => { let h = ''; try { h = new URL(r.url()).host; } catch {} if (h !== host) return; let main = false; try { main = r.resourceType() === 'document' && r.frame() === r.frame().page().mainFrame(); } catch {} if (main) this.views++; else this.sub++; });
  }
  summary(extra = {}) {
    return { id: this.id, check: this.check, mode: this.mode, started_at: new Date(this.t0).toISOString(), seconds: Math.round((Date.now() - this.t0) / 100) / 10,
      requests: this.views + this.calls, page_views: this.views, calls: this.calls, subrequests: this.sub, one_at_a_time: true,
      gap_ms: GAP_MS, min_gap_ms: this.gaps.length ? Math.min(...this.gaps) : null, ...extra };
  }
}

/** audit.json's back-off (audit-sample.py): skip this run while it holds. */
export function backoff() {
  try { const b = JSON.parse(readFileSync(`${RES}/audit.json`, 'utf8')).backoff || {}; if (b.until && Date.parse(b.until) > Date.now()) return b; } catch {}
  return null;
}

/** Writes one check's results file in the one format. A light run replaces only the light rows (the daily rows are kept). */
export function writeResults(check, rows, run, extra = {}) {
  const R = REGISTER[check]; const file = `${RES}/${R.file}`;
  let old = {}; try { old = JSON.parse(readFileSync(file, 'utf8')); } catch {}
  const mode = run.mode;
  const keep = (old.results || []).filter((r) => (r.mode || 'full') !== mode);
  const results = [...keep, ...rows.map((r) => ({ ...r, mode }))];
  const counts = {}; for (const r of results) counts[r.result] = (counts[r.result] || 0) + 1;
  const data = { version: 1, check, name: R.name, generated_at: new Date().toISOString().replace(/\.\d+Z$/, 'Z'), site: SITE, run_url: RUN_URL,
    target: R.target, target_source: R.target_source, source: 'handoffs/control-board-plan/step3/sections/check-register.md',
    runs: { ...(old.runs || {}), [mode]: run }, counts, ...extra,
    note: `Generated by tools/page-audit/ for the check register's ${check} row. Read by the gauges and audit-sample.py. Don't edit by hand.`, results };
  mkdirSync(RES, { recursive: true });
  writeFileSync(file, JSON.stringify(data, null, 1) + '\n');
  return data;
}

// ---- in the page: the load metrics ------------------------------------------------------------------------------------
// LCP and layout shifts from the browser's own buffered entries; CLS as the largest session window (shifts under 1 s
// apart, a window at most 5 s: web.dev's CLS definition); INP from the interactions that happened, here only the Tab key
// presses this pass makes (the live checks never click).
const INIT = () => {
  window.__tvl = { lcp: 0, shifts: [], ev: [] };
  try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__tvl.lcp = e.renderTime || e.loadTime || e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}
  try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__tvl.shifts.push([e.startTime, e.value]); }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {}
  try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (e.interactionId) window.__tvl.ev.push(e.duration); }).observe({ type: 'event', buffered: true, durationThreshold: 16 }); } catch (e) {}
};
async function loadMetrics(p) {
  return p.evaluate(() => {
    const t = window.__tvl || { lcp: 0, shifts: [], ev: [] };
    let cls = 0, win = 0, start = -1, prev = -1;
    for (const [at, v] of t.shifts) { if (start < 0 || at - prev > 1000 || at - start > 5000) { win = 0; start = at; } win += v; prev = at; cls = Math.max(cls, win); }
    const n = performance.getEntriesByType('navigation')[0] || {};
    return { lcp_ms: t.lcp ? Math.round(t.lcp) : null, cls: Math.round(cls * 1000) / 1000, ttfb_ms: n.responseStart ? Math.round(n.responseStart - n.requestStart) : null,
      dcl_ms: n.domContentLoadedEventEnd ? Math.round(n.domContentLoadedEventEnd) : null, load_ms: n.loadEventEnd ? Math.round(n.loadEventEnd) : null };
  });
}
// The browser reports only interactions of 16 ms or longer: none reported means every Tab press answered under 16 ms
const inpNow = (p) => p.evaluate(() => { const e = (window.__tvl || {}).ev || []; return e.length ? Math.round(Math.max(...e)) : null; });

function judgeLoad(m) {
  if (m.lcp_ms == null) return 'no check';   // nothing painted that the browser counts: the page is measured, not judged
  const ok = m.lcp_ms <= CWV.lcp && m.cls <= CWV.cls && (m.inp_ms == null || m.inp_ms <= CWV.inp);
  return ok ? 'pass' : 'fail';
}

// ---- which pages, as whom -------------------------------------------------------------------------------------------
const CORE = ['/', '/discovery/', '/directory/', '/faq.php', '/experiences/', '/community/', '/market/', '/plans.php'];
function pagesFor(mode) {
  if (mode === 'light') return (process.env.LOAD_PAGES || '/,/discovery/,/directory/,/login.php').split(',').map((s) => s.trim()).filter(Boolean).map((url) => ({ url, who: 'guest' }));
  let rendered = null; try { rendered = JSON.parse(readFileSync(process.env.RENDERED || `${RES}/rendered.json`, 'utf8')); } catch {}
  if (!rendered?.results?.length) return CORE.map((url) => ({ url, who: 'guest' }));
  const rank = (w) => (w === 'guest' ? 0 : w.startsWith('staff-') ? 2 : 1);
  const seen = new Map();
  for (const r of rendered.results) {
    if (!r.url || !r.url.startsWith('/') || r.error || (r.status && r.status >= 400)) continue;   // a page that doesn't open is RENDER's finding
    if (/\.(xml|json|txt)$/.test(r.url)) continue;
    const cur = seen.get(r.url); if (!cur || rank(r.who) < rank(cur.who)) seen.set(r.url, { url: r.url, who: r.who });
  }
  return [...seen.values()];
}
function signInFor(who) {
  if (who === 'guest') return null;
  if (who.startsWith('staff-')) { const a = ACC?.staff?.[who.slice(6)]; return a ? (ctx) => staffIn(ctx, a) : undefined; }
  const a = ACC?.members?.[who]; return a ? (ctx) => memberIn(ctx, a) : undefined;
}

// ---- the pass -------------------------------------------------------------------------------------------------------
/** Opens each page once per size and runs the named checks on it. checks: any of LOAD, PHOTOS-SHOWN, BRAND. */
// mods: {P, B} when the caller is photos-shown.mjs or brand-check.mjs run on its own (it can't be imported back while it runs)
export async function runPass({ checks, mode = process.env.LOAD_MODE || 'full', budgetS = +(process.env.LOAD_BUDGET_S || 2400), mods = {} }) {
  const meter = new Meter(checks.join('+'), mode);
  const held = backoff();
  if (held) {   // the audit asked the next run to wait (register "How often": "the next run backs off")
    const run = meter.summary({ skipped: `backing off until ${held.until}: ${held.why || 'the probe pages were slow'}`, shared_by: checks });
    for (const c of checks) writeResults(c, [], run);
    console.log(`Backing off until ${held.until}: nothing asked of the site.`);
    return;
  }
  const P = checks.includes('PHOTOS-SHOWN') ? (mods.P || await import('./photos-shown.mjs')) : null;
  const B = checks.includes('BRAND') ? (mods.B || await import('./brand-check.mjs')) : null;
  const out = Object.fromEntries(checks.map((c) => [c, []]));
  const pages = pagesFor(mode);
  const sizes = ['phone', 'desktop'];   // the register's LOAD and PHOTOS-SHOWN: "at 1280 and 390"
  const deadline = Date.now() + budgetS * 1000;
  const browser = await chromium.launch();
  const byWho = new Map(); for (const pg of pages) { if (!byWho.has(pg.who)) byWho.set(pg.who, []); byWho.get(pg.who).push(pg.url); }
  const skipped = [];
  for (const [who, urls] of byWho) {
    const signIn = signInFor(who);
    if (signIn === undefined) { skipped.push(...urls.map((u) => [u, who, `no ${who} account in CHECK_ACCOUNTS`])); continue; }
    const ctx = await checkContext(browser); meter.attach(ctx); await ctx.addInitScript(INIT);
    try { if (signIn) { await meter.pace(); await signIn(ctx); } } catch (e) { skipped.push(...urls.map((u) => [u, who, 'sign-in failed: ' + String(e).slice(0, 120)])); await ctx.close(); continue; }
    for (const url of urls) for (const size of sizes) {
      const item = url;
      if (Date.now() > deadline) { skipped.push([url, who, `out of the run's time (LOAD_BUDGET_S=${budgetS})`, size]); continue; }
      const p = await ctx.newPage(); await p.setViewportSize(SIZES[size]);
      try {
        await meter.pace();
        const r = await p.goto(SITE + url, { waitUntil: 'load', timeout: 45000 });
        await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await p.waitForTimeout(500);
        const status = r ? r.status() : 0;
        const html = /html/i.test((r && r.headers()['content-type']) || 'text/html');
        if (!html) { await p.close(); continue; }
        const m = { status, ...(await loadMetrics(p)) };
        // the Tab presses: BRAND's focus check, or three for INP alone (focus only, nothing activated)
        const focus = B ? await B.focusProbe(p) : null;
        if (!B) for (let i = 0; i < 3; i++) await p.keyboard.press('Tab').catch(() => {});
        m.inp_ms = await inpNow(p);
        if (m.inp_ms == null) m.inp_note = 'every Tab press answered under 16 ms (the browser reports only slower ones)';
        if (out.LOAD) out.LOAD.push(row('LOAD', item, who, status >= 400 ? 'fail' : judgeLoad(m), m, RUN_URL || null, { size }));
        if (B) { const b = await B.brandProbe(p); out.BRAND.push(row('BRAND', item, who, B.judge(b, focus), { ...b, focus }, RUN_URL || null, { size })); }
        if (P) { const ph = await P.photosProbe(p); if (ph.images + ph.backgrounds > 0) out['PHOTOS-SHOWN'].push(row('PHOTOS-SHOWN', item, who, P.judge(ph), ph, RUN_URL || null, { size })); }
      } catch (e) {
        for (const c of checks) out[c].push(row(c, item, who, 'fail', { error: String(e).slice(0, 200) }, RUN_URL || null, { size }));
      }
      await p.close();
    }
    await ctx.close();
  }
  await browser.close();
  for (const [url, who, why, size] of skipped) for (const c of checks) for (const s of size ? [size] : sizes)
    out[c].push(row(c, url, who, 'no check', null, null, { size: s, why }));
  const run = meter.summary({ shared_by: checks, pages: pages.length });
  // LOAD's summary row: the 75th percentile over every page measured, against the target
  if (out.LOAD) {
    const got = out.LOAD.filter((r) => r.measured && r.measured.lcp_ms != null);
    const s = { pages: got.length, lcp_ms: p75(got.map((r) => r.measured.lcp_ms)), cls: p75(got.map((r) => r.measured.cls)), inp_ms: p75(got.map((r) => r.measured.inp_ms)),
      inp_from: 'Tab key presses only (the live checks never click)', lab: 'headless Chromium on a GitHub runner, no throttling; cache warm after each account\'s first page' };
    out.LOAD.unshift(row('LOAD', `(every page measured, 75th percentile, ${mode})`, null, got.length ? judgeLoad(s) : 'no check', s));
  }
  for (const c of checks) writeResults(c, out[c], run);
  console.log(`${checks.join(', ')} (${mode}): ${pages.length} pages, ${run.page_views} page views, ${run.subrequests} subrequests, ${run.seconds} s.`
    + Object.entries(out).map(([c, rs]) => ` ${c}: ${rs.filter((r) => r.result === 'fail').length} fail of ${rs.length}.`).join(''));
}

if (isMain(import.meta.url)) {
  const also = (process.env.ALSO || '').split(',').map((s) => s.trim().toUpperCase()).filter((c) => c === 'PHOTOS-SHOWN' || c === 'BRAND');
  const mode = process.env.LOAD_MODE || 'full';
  await runPass({ checks: ['LOAD', ...(mode === 'light' ? [] : also)], mode });
}
