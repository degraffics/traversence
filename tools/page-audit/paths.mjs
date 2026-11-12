// tools/page-audit/paths.mjs — the paths (decisions/0145 §1, Phase 4): what someone comes to do, run end to end in a
// real browser (Playwright, Chromium, phone size), signed out and as the test accounts, and measured.
//
//   SITE=https://traversence.com CHECK_ACCOUNTS='{…}' node tools/page-audit/paths.mjs
//
// Two kinds of path:
//   - guide paths, generated from the live site: every live system guide and its suggested journeys (decision 0146),
//     read from /api/guide-paths.php (locked with the checks' token; Discovery's links when it isn't there yet). A few
//     guides a run (GUIDES_PER_RUN), rotating, so every guide is walked over the days. Open the guide, its journey,
//     then each stop: the page opens, its facts match our data (open now, phone, distance), its directions and links work.
//   - core paths, a short fixed list (site-audit/punch-list.md item 5). Those that only read run on live; those that
//     change something (sign up, like, comment, claim, post, message, join, staff decisions, an order) run only on
//     staging, once its address is in CHECK_ACCOUNTS; until then they're listed as waiting for staging.
//
// The measures (punch-list item 6, decided 2026-11-08): steps (taps and pages) to the goal, 3 or fewer from the home page
// for "find a place to eat" and "read a town's guide", 5 or fewer for the rest; dead ends (an empty page with no next
// step, a tap with no visible result, rule T06: a feature offered to an account that can't use it); surprises (stacked
// dialogs, a lost header, a page that loads twice or jumps); accuracy (what's shown matches the data, what exists shows,
// links work); clarity notes. Kept per run (results/paths-history.json) so trends show.
//
// Writes site-audit/results/paths.json, site-audit/results/paths-history.json and site-audit/paths.md; screenshots go
// to shots/ (kept with the run, never committed). Only reads on live. A time budget (PATHS_BUDGET_S) keeps the run short.
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { SITE, ACC, CHECK_TOKEN, STAGING, memberIn, staffIn, checkContext } from './check-kit.mjs';

const FULL = process.env.FULL === '1';
const GUIDES_PER_RUN = +(process.env.GUIDES_PER_RUN || (FULL ? 12 : 4));
const STOPS_PER_JOURNEY = +(process.env.STOPS_PER_JOURNEY || 6);
const JOURNEYS_PER_GUIDE = +(process.env.JOURNEYS_PER_GUIDE || (FULL ? 3 : 1));
const BUDGET_MS = 1000 * +(process.env.PATHS_BUDGET_S || (FULL ? 900 : 420));
const ONLY = (process.env.PATHS_ONLY || '').split(',').map(s => s.trim()).filter(Boolean);   // e.g. PATHS_ONLY=eat-near-me,guides
const OUT = process.env.PATHS_OUT || 'site-audit/results/paths.json';
const HIST = process.env.PATHS_HISTORY || 'site-audit/results/paths-history.json';
const MD = process.env.PATHS_MD || 'site-audit/paths.md';
const RUN_URL = process.env.GITHUB_RUN_ID ? `${process.env.GITHUB_SERVER_URL || 'https://github.com'}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}` : '';
const PHONE = { width: 390, height: 844 };
const HERE = { latitude: 34.2542, longitude: -110.0298 };   // Show Low, AZ: a pilot town (decisions/0006), where "near me" is
const SLOW_MS = 4000;
const T0 = Date.now();
const left = () => BUDGET_MS - (Date.now() - T0);
const prev = (() => { try { return JSON.parse(readFileSync(OUT, 'utf8')); } catch { return null; } })();

// ---- one path being walked --------------------------------------------------------------------------------------------
// Counts taps and pages, and watches each page for surprises. Every problem is {kind, rule, page, what}.
const INIT = () => {   // in every page: changes after a tap, and how much the layout jumps
  window.__tv = { mut: 0, cls: 0 };
  try { new MutationObserver(m => { window.__tv.mut += m.length; }).observe(document, { subtree: true, childList: true, attributes: true, characterData: true }); } catch (e) {}
  try { new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__tv.cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {}
};
class Walk {
  constructor(page, path, who, site) {
    Object.assign(this, { page, path, who, site, taps: 0, loads: 0, pages: [], problems: [], notes: [], shots: [], timings: [], goal: false, stuck: '' });
    this.signedIn = who !== 'guest';   // what the page should offer: Sign in only to someone signed out
    this.inTap = 0;
    page.on('framenavigated', f => { if (f === page.mainFrame() && !/^about:/.test(f.url())) { this.loads++; this.inTap++; const u = this.rel(f.url()); if (this.pages[this.pages.length - 1] !== u) this.pages.push(u); } });
    page.on('dialog', d => { this.add('surprise', 'R02', this.here(), `a browser dialog: ${d.type()} "${d.message().slice(0, 80)}"`); d.dismiss().catch(() => {}); });
    page.on('pageerror', e => this.add('surprise', 'V14', this.here(), `a script error: ${String(e).slice(0, 120)}`));
  }
  rel(u) { return u.startsWith(this.site) ? (u.slice(this.site.length) || '/') : u; }
  here() { return this.rel(this.page.url()); }
  add(kind, rule, page, what) { if (!this.problems.some(p => p.kind === kind && p.page === page && p.what === what)) this.problems.push({ kind, rule, page, what }); }
  note(t) { this.notes.push(t); }
  async shot(tag) {
    const f = `shots/path-${this.path}-${this.who}-${this.shots.length + 1}-${tag}`.replace(/[^a-z0-9_./-]+/gi, '_').slice(0, 120) + '.png';
    await this.page.screenshot({ path: f }).catch(() => {}); this.shots.push(f.replace(/^shots\//, '')); return f;
  }
  async goto(url, label = '') {
    const t = Date.now();
    let r = null;
    try { r = await this.page.goto(url.startsWith('http') ? url : this.site + url, { waitUntil: 'load', timeout: 45000 }); } catch (e) { this.add('dead_end', 'J01', url, `${label || 'the page'} didn't open: ${String(e).slice(0, 100)}`); await this.shot('noopen'); return null; }
    await this.page.waitForTimeout(700);
    const ms = Date.now() - t; this.timings.push({ page: this.here(), ms });
    if (ms > SLOW_MS) this.add('slow', 'D02', this.here(), `took ${(ms / 1000).toFixed(1)} s to open`);
    const st = r ? r.status() : 0;
    if (st >= 400) { this.add('dead_end', 'J01', this.rel(url), `${label || 'the page'} answered ${st}`); await this.shot('status'); }
    await this.look();
    return r;
  }
  /** A tap, as a visitor makes it: counted, and checked for a visible result (a new page, a change on this one, a notice). */
  async tap(loc, label, { wait = 4000, nav = false } = {}) {
    const el = typeof loc === 'string' ? this.page.locator(loc).first() : loc;
    if (!(await el.count().catch(() => 0)) || !(await el.isVisible().catch(() => false))) { this.stuck = this.stuck || `couldn't find "${label}" to tap on ${this.here()}`; await this.shot('stuck'); return false; }
    this.taps++; this.inTap = 0;
    const before = await this.page.evaluate(() => (window.__tv ? window.__tv.mut : 0)).catch(() => 0);
    const url = this.page.url();
    const t = Date.now();
    await el.click({ timeout: 8000 }).catch(e => this.add('dead_end', 'J01', this.here(), `"${label}" couldn't be tapped: ${String(e).slice(0, 80)}`));
    if (nav) await this.page.waitForLoadState('load', { timeout: 30000 }).catch(() => {});
    await this.page.waitForTimeout(Math.min(wait, 1500));
    let changed = this.page.url() !== url;
    for (let i = 0; !changed && i < wait / 250; i++) {
      const now = await this.page.evaluate(() => (window.__tv ? window.__tv.mut : 0)).catch(() => before + 1);
      if (now !== before) changed = true; else await this.page.waitForTimeout(250);
    }
    if (!changed) { this.add('dead_end', 'J01', this.here(), `tapping "${label}" showed nothing (no new page, no change, no notice)`); await this.shot('nothing'); }
    if (this.inTap > 1) this.add('surprise', 'V06', this.here(), `"${label}" loaded ${this.inTap} pages one after another (the page flashes)`);
    if (this.page.url() !== url) { this.timings.push({ page: this.here(), ms: Date.now() - t }); await this.page.waitForTimeout(500); await this.look(); }
    else await this.look(false);
    return true;
  }
  /** Type into a box (not a tap), then wait for the page to answer. */
  async type(sel, text, { enter = false } = {}) {
    const el = this.page.locator(sel).first();
    if (!(await el.isVisible().catch(() => false))) { this.stuck = this.stuck || `couldn't find the box to type "${text}" in, on ${this.here()}`; await this.shot('stuck'); return false; }
    await el.fill('').catch(() => {}); await el.type(text, { delay: 40 }).catch(() => {});
    if (enter) { this.taps++; await el.press('Enter').catch(() => {}); }
    await this.page.waitForTimeout(1800);
    return true;
  }
  /** What every page is held to on the way: the header, dialogs, empty pages with no way on, the account's own version. */
  async look(full = true) {
    const m = await this.page.evaluate((who) => {
      const vis = el => { if (!el) return false; const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && !el.closest('[hidden]'); };
      const dialogs = [...document.querySelectorAll('[role=dialog], [aria-modal=true], dialog[open], .tvj')].filter(vis).filter((d, i, a) => !a.some(o => o !== d && o.contains(d)));
      const nav = document.getElementById('tv-main-nav');
      const main = document.querySelector('main') || document.body;
      const text = (main.innerText || '').replace(/\s+/g, ' ').trim();
      const ways = [...main.querySelectorAll('a[href], button')].filter(vis).filter(a => !a.closest('#tv-main-nav, header, footer, nav')).length;
      const outVisible = [...document.querySelectorAll('[data-auth="out"]')].some(vis);
      return { dialogs: dialogs.length, nav: !!nav, navVis: vis(nav) || (nav && nav.getBoundingClientRect().height > 0), cls: window.__tv ? window.__tv.cls : 0,
        empty: /\b(no results|nothing (here|yet|found)|isn't here|not found|couldn't find)\b/i.test(text.slice(0, 600)), ways, outVisible,
        paidOffer: /comes with a paid plan|upgrade to (use|get)|choose a plan to/i.test(text), h1: !!document.querySelector('h1'), short: text.length < 80 };
    }, this.who).catch(() => null);
    if (!m) return;
    const page = this.here();
    if (m.dialogs > 1) { this.add('surprise', 'V06', page, `${m.dialogs} dialogs open on top of each other`); await this.shot('stacked'); }
    if (full && !m.nav && !/\/admin\//.test(page) && !/^https?:/.test(page)) this.add('surprise', 'V04', page, 'the site\'s header is missing on this page');
    if (m.cls > 0.25) this.add('surprise', 'V06', page, `the page jumps as it loads (layout shift ${m.cls.toFixed(2)})`);
    if (m.empty && m.ways < 1) { this.add('dead_end', 'T06', page, 'an empty page with no next step'); await this.shot('empty'); }
    if (this.signedIn && m.outVisible) this.add('dead_end', 'T06', page, 'Create account / Sign in offered to someone already signed in');
    if (this.who.startsWith('staff') && m.paidOffer) this.add('dead_end', 'T06', page, 'a paid plan offered to staff (they have it in the admin)');
    if (full && !m.h1 && !m.short && !/^https?:/.test(page)) this.add('clarity', 'J03', page, 'no page title (h1): what this page is isn\'t said');
  }
  /** Internal links on the page work (a sample, each checked once per run). */
  async links(max = 4, checked = new Set()) {
    const hrefs = await this.page.$$eval('main a[href^="/"], .sj-list a[href^="/"]', as => [...new Set(as.map(a => a.getAttribute('href')))].filter(h => !/^\/(api|admin|login|logout)|#$/.test(h))).catch(() => []);
    for (const h of hrefs.filter(h => !checked.has(h)).slice(0, max)) {
      checked.add(h);
      const r = await this.page.request.get(this.site + h, { maxRedirects: 3, timeout: 20000 }).catch(() => null);
      if (!r || r.status() >= 400) this.add('accuracy', 'J02', this.here(), `the link ${h} ${r ? 'answers ' + r.status() : 'doesn\'t answer'}`);
    }
  }
  result(def, extra = {}) {
    const steps = this.taps;
    const over = def.limit && steps > def.limit;
    if (over) this.add('steps', 'J01', this.pages[this.pages.length - 1] || '', `${steps} taps to the goal (the measure is ${def.limit} or fewer)`);
    const n = k => this.problems.filter(p => p.kind === k).length;
    const status = this.stuck && !this.goal ? 'stuck' : !this.goal || n('dead_end') || over ? 'failed' : (n('accuracy') || n('surprise')) ? 'issues' : 'passed';
    return { id: def.id, kind: def.kind || 'core', title: def.title, who: this.who, status, goal: this.goal, stuck: this.stuck, steps: { taps: steps, pages: this.pages.length, limit: def.limit || null, over: !!over },
      counts: { dead_ends: n('dead_end'), surprises: n('surprise'), accuracy: n('accuracy'), clarity: n('clarity'), slow: n('slow') },
      problems: this.problems, notes: this.notes, pages: this.pages, shots: this.shots, timings: this.timings, ...extra };
  }
}

// ---- who walks it -----------------------------------------------------------------------------------------------------
const browser = await chromium.launch();
mkdirSync('shots', { recursive: true });
const contexts = {};
async function as(who, where = 'live') {
  const k = who + '@' + where;
  if (contexts[k]) return contexts[k];
  const st = where === 'staging' ? STAGING : null;
  const site = st ? st.site : SITE; const token = st ? st.token : CHECK_TOKEN;
  const ctx = await checkContext(browser, { site, token, staging: st, context: { viewport: PHONE, isMobile: true, hasTouch: true, geolocation: HERE, permissions: ['geolocation'],
    userAgent: 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Mobile Safari/537.36 Traversence site checks (support@traversence.com)' } });
  await ctx.addInitScript(INIT);
  if (who !== 'guest') {
    const staff = who.startsWith('staff-') ? ACC?.staff?.[who.slice(6)] : null;
    const member = !staff ? ACC?.members?.[who] : null;
    if (!staff && !member) { await ctx.close(); return contexts[k] = { error: `no ${who} account in CHECK_ACCOUNTS` }; }
    try { if (staff) await staffIn(ctx, staff, site); else await memberIn(ctx, member, site, token); }
    catch (e) { await ctx.close(); return contexts[k] = { error: String(e).slice(0, 200) }; }
  }
  return contexts[k] = { ctx, site };
}
async function walk(def, who, where = 'live') {
  const a = await as(who, where);
  if (a.error) return { id: def.id, kind: def.kind || 'core', title: def.title, who, status: 'skipped', notes: [a.error], problems: [], steps: { taps: 0, pages: 0, limit: def.limit || null, over: false }, counts: {} };
  const page = await a.ctx.newPage();
  const w = new Walk(page, def.id, who, a.site);
  if (def.signedOut) w.signedIn = false;   // it starts signed out (sign in)
  const t = Date.now();
  try { await def.run(w, page, who); } catch (e) { w.stuck = w.stuck || `the runner stopped: ${String(e).slice(0, 160)}`; await w.shot('error'); }
  const r = w.result(def, { where, ms: Date.now() - t });
  await page.close().catch(() => {});
  return r;
}
const textOf = async (page) => ((await page.locator('main').first().innerText().catch(() => '')) || (await page.locator('body').innerText().catch(() => ''))).replace(/\s+/g, ' ');
const firstVisible = async (page, sels) => { for (const s of sels) { const l = page.locator(s).first(); if (await l.isVisible().catch(() => false)) return l; } return null; };
const searchBox = '#tv-search .tvs-in';
const panelBox = '.tvs-panel .tvs-in2';

/** Opens the search from the header and types the words, as a visitor does (the tap on the box counts). */
async function search(w, page, words, { enter = true } = {}) {
  const box = await firstVisible(page, [searchBox, '#tv-search .tvs-sb', '#tv-search button']);
  if (!box) { w.stuck = `no search box in the header on ${w.here()}`; await w.shot('nosearch'); return false; }
  await w.tap(box, 'Search');
  const inBox = (await page.locator(panelBox).first().isVisible().catch(() => false)) ? panelBox : searchBox;
  if (!(await w.type(inBox, words))) return false;
  // the search answers as you type: Enter (Go on a phone's keyboard, a tap) only when nothing has come up
  const came = await page.locator('.tvs-panel a.tvs-row:visible, .tvs-panel a.tvs-btn:visible, .tvs-panel [data-near-detect]:visible, .tvs-panel a[href*="/hub/"]:visible').count().catch(() => 0);
  if (!came && enter) { w.taps++; await page.locator(inBox).first().press('Enter').catch(() => {}); await page.waitForTimeout(2000); }
  return true;
}

// ---- the core paths ---------------------------------------------------------------------------------------------------
// limit: the most taps to the goal (3 for eating and a town's guide, 5 for the rest; punch-list item 6).
// changes: true → it changes something, so it runs on staging only. run: null → its steps are written, its runner comes
// with staging (it can't be tried from here before then).
const CORE = [
  { id: 'eat-near-me', title: 'Find a place to eat near me, open now', who: ['guest', 'member'], limit: 3, run: async (w, page) => {
    await w.goto('/', 'the home page');
    if (!(await search(w, page, 'places to eat open now near me'))) return;
    const near = await firstVisible(page, ['.tvs-panel [data-near-detect]']);   // "Location needed": use my location (the browser gives Show Low)
    if (near) { await w.tap(near, 'Use my location'); await page.waitForTimeout(2500); }
    const rows = page.locator('.tvs-panel a.tvs-row[href]:visible');
    if (!(await rows.count())) { const t = await textOf(page); w.add('dead_end', 'J01', w.here(), 'the search showed no places to eat near Show Low' + (/location/i.test(t) ? ' (it still asks for a location)' : '')); await w.shot('noresults'); return; }
    const n = await rows.count(); const open = await page.locator('.tvs-panel .tvs-open:visible').count();
    w.note(`${n} results, ${open} marked open now`);
    if (!open) w.add('clarity', 'J03', w.here(), 'none of the results says it\'s open now (the search asked for open now)');
    const first = rows.first(); const said = (await first.innerText().catch(() => '')).replace(/\s+/g, ' ');
    const href = await first.getAttribute('href');
    if (!(await w.tap(first, 'the first place', { nav: true }))) return;
    w.goal = /\/listing\/view\.php|\/place\//.test(w.here()) || (!!href && /^https?:/.test(href));
    if (!w.goal) { w.add('dead_end', 'J01', w.here(), `the first result (${said.slice(0, 60)}) didn't open a place's page`); return; }
    const t = await textOf(page);
    if (/Open now/i.test(said) && /\bClosed\b/.test(t) && !/\bOpen\b/.test(t)) w.add('accuracy', 'J02', w.here(), 'the search said open now; the place\'s page says closed');
    if (!(await page.locator('a[href^="tel:"]').count())) w.add('clarity', 'J02', w.here(), 'no phone to call on the place\'s page');
    if (!(await page.locator('a[data-out="directions"]').count())) w.add('accuracy', 'J02', w.here(), 'no directions on the place\'s page');
    if (!/\bOpen\b|\bClosed\b|Hours/i.test(t)) w.add('clarity', 'J03', w.here(), 'the place\'s page doesn\'t say whether it\'s open now');
  } },
  { id: 'town-guide', title: 'Read a town\'s guide', who: ['guest'], limit: 3, run: async (w, page) => {
    const town = GUIDE_TOWN || 'Show Low, AZ';
    w.note(`the town: ${town}`);
    await w.goto('/', 'the home page');
    if (!(await search(w, page, town, { enter: false }))) return;
    const g = await firstVisible(page, ['.tvs-panel a[href*="/hub/?"]:visible', '.tvs-panel a.tvs-btn[href*="/hub/"]', '.tvs-panel a[href*="/hub/build.php"]']);
    if (!g) { await page.keyboard.press('Enter').catch(() => {}); w.taps++; await page.waitForTimeout(2000); }
    const g2 = g || await firstVisible(page, ['.tvs-panel a[href*="/hub/?"]:visible', 'a[href*="/hub/?"]:visible']);
    if (!g2) { w.add('dead_end', 'J02', w.here(), `searching "${town}" offered no guide`); await w.shot('noguide'); return; }
    await w.tap(g2, 'the guide', { nav: true });
    w.goal = /\/hub\//.test(w.here());
    if (w.goal && (await textOf(page)).length < 400) w.add('clarity', 'J03', w.here(), 'the guide has little to read');
  } },
  { id: 'search-typo', title: 'Search with a typo ("swiming")', who: ['guest'], limit: 5, run: async (w, page) => {
    await w.goto('/', 'the home page');
    if (!(await search(w, page, 'swiming near show low'))) return;
    const pt = ((await page.locator('.tvs-panel').first().innerText().catch(() => '')) || (await textOf(page))).replace(/\s+/g, ' ');
    const fixed = /swimming|pool|swim/i.test(pt);
    if (!fixed) w.add('accuracy', 'J02', w.here(), 'the typo wasn\'t read as swimming: nothing about swimming in the results');
    const rows = page.locator('.tvs-panel a.tvs-row[href]:visible');
    if (!(await rows.count())) { w.add('dead_end', 'J01', w.here(), 'no results for "swiming near show low"'); await w.shot('noresults'); return; }
    await w.tap(rows.first(), 'the first result', { nav: true });
    w.goal = fixed && w.here() !== '/';
  } },
  { id: 'set-place', title: 'Set my place and distance', who: ['guest'], limit: 5, run: async (w, page) => {
    await w.goto('/directory/', 'Get Local');
    const pin = await firstVisible(page, ['#tv-search .tvs-pin', '[data-tv-click="tvSearchWhere"]']);
    if (!pin) { w.stuck = 'no "Choose where to search" pin in the header'; return; }
    await w.tap(pin, 'Choose where to search');
    const box = await firstVisible(page, ['#location-modal input[type=search]:visible', '#location-modal input[type=text]:visible', '.tvs-panel .tv-pbox input:visible', '.tvs-panel .tvs-nearin:visible']);
    if (!box) { w.stuck = 'the place picker has no Town or ZIP box'; await w.shot('nobox'); return; }
    await box.fill(''); await box.type('Show Low', { delay: 50 }); await page.waitForTimeout(1800);
    const opt = await firstVisible(page, ['[role=option]:visible >> text=/Show Low/i', 'button:visible >> text=/Show Low/i', '[role=option]:visible']);
    if (!opt) { w.add('dead_end', 'J01', w.here(), 'typing "Show Low" in the place picker offered no town'); await w.shot('notown'); return; }
    await w.tap(opt, 'Show Low');
    const label = (await page.locator('#menu-location-label').first().textContent().catch(() => '')) || '';
    const panel = (await page.locator('.tvs-panel, #location-modal').first().innerText().catch(() => '')) || '';
    w.goal = /Show Low/i.test(label + ' ' + panel);
    if (!w.goal) { w.add('accuracy', 'J02', w.here(), 'after choosing Show Low, the page doesn\'t show it as the place'); await w.shot('noplace'); }
    const rng = await firstVisible(page, ['.tvs-panel .tvs-rng:not([disabled])', '#location-modal input[type=range]']);
    if (rng) { w.taps++; await rng.focus().catch(() => {}); await page.keyboard.press('ArrowRight').catch(() => {}); await page.waitForTimeout(800);
      const v = (await page.locator('.tvs-rngv').first().textContent().catch(() => '')) || ''; w.note(`distance now ${v.trim() || '(not shown)'}`); if (!v.trim()) w.add('clarity', 'J03', w.here(), 'the distance slider doesn\'t say how far'); }
    else w.add('dead_end', 'J01', w.here(), 'no distance to set once the place is chosen');
  } },
  { id: 'sign-in', title: 'Sign in, including the human check', who: ['member'], limit: 5, signedOut: true, run: async (w, page) => {
    const a = ACC?.members?.member; if (!a) { w.stuck = 'no member account in CHECK_ACCOUNTS'; return; }
    await w.goto('/', 'the home page');
    const menu = await firstVisible(page, ['#tv-main-nav [aria-haspopup]', '#tv-main-nav button[aria-controls]', '#tv-main-nav .tvn-burger', '#tv-main-nav button']);
    let si = await firstVisible(page, ['[data-auth="out"] a.tvn-in', '[data-auth="out"] a[href="/login.php"]', 'a.tvpm-in']);
    if (!si && menu) { await w.tap(menu, 'the menu'); si = await firstVisible(page, ['[data-auth="out"] a.tvn-in', 'a.tvpm-in', 'a[href="/login.php"]']); }
    if (!si) { w.stuck = 'no Sign in in the menu'; await w.shot('nosignin'); return; }
    await w.tap(si, 'Sign in', { nav: true });
    if (!(await w.type('#email', a.email)) || !(await w.type('#password', a.password))) return;
    const human = await page.locator('.cf-turnstile').count();
    await w.tap('#go-in', 'Sign in', { wait: 25000 });   // the human check runs unseen and can take several seconds
    await page.waitForURL(u => !/\/login\.php/.test(String(u)), { timeout: 30000 }).catch(() => {});
    const tok = await page.evaluate(() => (window.tvCaptcha ? window.tvCaptcha() : '')).catch(() => '');
    w.note(!human ? 'no human check on the page (Turnstile is off here)' : tok ? 'the human check passed in the browser' : 'the human check (Turnstile) can\'t pass in a headless browser on GitHub\'s runners: the checks\' token let the test address through, as TestAccounts::checkRequest allows. A person\'s sign-in still needs it.');
    w.goal = !/\/login\.php/.test(w.here());
    if (!w.goal) { const said = (await page.locator('#status').innerText().catch(() => '')).trim(); w.add('dead_end', 'J01', w.here(), `signing in didn't get past the sign-in page${said ? ': "' + said.slice(0, 100) + '"' : ''}`); await w.shot('notin'); }
  } },
  { id: 'sign-out', title: 'Sign out', who: ['member'], limit: 5, run: async (w, page) => {
    await w.goto('/user/dashboard.php', 'your dashboard');
    const out = await firstVisible(page, ['[data-tv-as-out]', 'button[aria-label="Sign out"]']);
    if (!out) { w.stuck = 'no Sign out button in the account bar'; await w.shot('noout'); return; }
    w.signedIn = false;   // from here the page should offer Sign in
    await w.tap(out, 'Sign out', { nav: true, wait: 6000 });
    await page.waitForTimeout(1200);
    const s = await page.request.get(w.site + '/api/auth/session.php').then(r => r.json()).catch(() => ({}));
    w.goal = !s.user && !s.authenticated && !s.user_id;
    if (!w.goal) w.add('dead_end', 'J01', w.here(), 'Sign out was tapped and the session is still signed in');
    const shown = await page.locator('[data-auth="out"]:visible').count();
    if (w.goal && !shown) w.add('accuracy', 'J02', w.here(), 'signed out, but the menu doesn\'t offer Sign in');
  } },
  { id: 'my-listing', title: 'See my listing as visitors do', who: ['owner'], limit: 5, run: async (w, page) => {
    await w.goto('/', 'the home page');
    const tries = [['a.tv-as-dash', 'your dashboard'], ['a[href^="/listing/businessportal.php"]:visible', 'your business'], ['a[href^="/business/"]:visible', 'your business'],
      ['text=/See it as visitors do/i', 'See it as visitors do'], ['a[href^="/listing/view.php"]:visible', 'your listing']];
    for (let i = 0; i < 5 && !/\/listing\/view\.php/.test(w.here()); i++) {
      let hit = null;
      for (const [sel, label] of tries.slice().reverse()) { const l = await firstVisible(page, [sel]); if (l) { hit = [l, label]; break; } }   // the closest to the goal first
      if (!hit) break;
      await w.tap(hit[0], hit[1], { nav: true });
    }
    w.goal = /\/listing\/view\.php/.test(w.here());
    if (!w.goal) { w.stuck = w.stuck || `no way from ${w.here()} to the listing as visitors see it`; await w.shot('nolisting'); return; }
    const t = await textOf(page);
    if (/not found|isn't here/i.test(t)) {
      const test = ACC?.listing?.id && w.here().includes('id=' + ACC.listing.id);
      if (test) w.note('the test listing is hidden from the public, so its page says not found to visitors (expected for the test listing only)');
      else w.add('dead_end', 'J01', w.here(), '"See it as visitors do" opens a page that says not found');
    }
  } },
  // ---- these change something: staging only ----
  { id: 'sign-up', title: 'Sign up (all steps)', who: ['guest'], limit: 5, changes: true, run: null },
  { id: 'like-guest', title: 'Like as a guest (3, then Keep going as a guest)', who: ['guest'], limit: 5, changes: true, run: async (w, page) => {
    const pages = (GUIDE_LIST.flatMap(g => g.journeys.map(j => j.url))).slice(0, 4);
    if (pages.length < 4) { w.stuck = 'not enough pages with a Like to try (suggested journeys)'; return; }
    for (const u of pages) {
      await w.goto(u);
      const like = await firstVisible(page, ['[data-eg-like]']);
      if (!like) { w.add('dead_end', 'J01', w.here(), 'no Like on the page'); continue; }
      await w.tap(like, 'Like');
      const more = await firstVisible(page, ['.tvj-more']);
      if (more) { await w.tap(more, 'Keep going as a guest'); w.goal = (await like.getAttribute('aria-pressed')) === 'true'; break; }
    }
    if (!w.goal) w.add('accuracy', 'J02', w.here(), 'after several likes, the account panel with Keep going as a guest didn\'t come, or the like didn\'t take');
  } },
  { id: 'like-member', title: 'Like as a member (past 100)', who: ['member'], limit: 5, changes: true, run: null },
  { id: 'comment', title: 'Comment', who: ['member'], limit: 5, changes: true, run: async (w, page) => {
    const u = GUIDE_LIST[0]?.journeys?.[0]?.url; if (!u) { w.stuck = 'no suggested journey to comment on'; return; }
    await w.goto(u);
    if (!(await w.type('[data-eg-form] textarea', 'TEST · path check, removed at once'))) return;
    await w.tap('[data-eg-form] button[type=submit]', 'Post comment');
    const mine = page.locator('.eg-cm', { hasText: 'TEST · path check' }).first();
    w.goal = await mine.isVisible().catch(() => false);
    if (!w.goal) { w.add('dead_end', 'J01', w.here(), 'the comment didn\'t show after Post comment'); return; }
    const del = mine.locator('[data-eg-del]'); if (await del.count()) { await del.click().catch(() => {}); const y = await firstVisible(page, ['.tva-yes', 'button:has-text("Remove")']); if (y) await y.click().catch(() => {}); }
  } },
  { id: 'claim', title: 'Claim my business', who: ['member'], limit: 5, changes: true, run: null },
  { id: 'edit-listing', title: 'Edit my listing', who: ['owner'], limit: 5, changes: true, run: null },
  { id: 'market-post', title: 'Post on the Marketplace', who: ['member'], limit: 5, changes: true, run: null },
  { id: 'message-business', title: 'Message a business', who: ['member'], limit: 5, changes: true, run: null },
  { id: 'join-group', title: 'Join a group', who: ['member'], limit: 5, changes: true, run: null },
  { id: 'staff-report', title: 'Staff: decide a report', who: ['staff-ops-staff'], limit: 5, changes: true, run: null },
  { id: 'staff-like-check', title: 'Staff: decide a like check', who: ['staff-ops-staff'], limit: 5, changes: true, run: null },
  { id: 'staff-held-post', title: 'Staff: decide a held Marketplace post', who: ['staff-ops-staff'], limit: 5, changes: true, run: null },
  { id: 'send-order', title: 'Staff: send an order', who: ['staff-sal-staff'], limit: 5, changes: true, run: null },
];

// ---- the guide paths --------------------------------------------------------------------------------------------------
let GUIDE_LIST = [], GUIDE_TOWN = '', GUIDE_SOURCE = '', GUIDE_TOTAL = 0, ROT = 0;
async function guides() {
  ROT = prev?.rotation?.next ?? 0;
  if (CHECK_TOKEN) {
    try {
      const r = await fetch(`${SITE}/api/guide-paths.php?offset=${ROT}&limit=${GUIDES_PER_RUN}`, { headers: { 'X-Check-Token': CHECK_TOKEN, 'User-Agent': 'Traversence site checks' } });
      const d = await r.json().catch(() => null);
      if (r.ok && d?.ok) { GUIDE_SOURCE = 'endpoint'; GUIDE_TOTAL = d.total; ROT = d.offset; return d.guides; }
      console.log(`::notice::/api/guide-paths.php answered ${r.status} (${d?.error?.message || 'not uploaded yet?'}): the guides come from Discovery's links instead.`);
    } catch (e) { console.log('::notice::/api/guide-paths.php: ' + e); }
  }
  // until the endpoint is live: the guides Discovery links to, and each one's journeys and stops as the pages show them
  GUIDE_SOURCE = 'discovery';
  const ctx = await checkContext(browser); const p = await ctx.newPage(); const out = [];
  try {
    let found = [];
    for (const from of ['/discovery/', '/directory/']) {
      await p.goto(SITE + from, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => {});
      found = found.concat(await p.$$eval('a[href*="/hub/?"]', as => as.map(a => a.getAttribute('href').replace(/^https?:\/\/[^/]+/, ''))).catch(() => []));
    }
    const all = [...new Set(found)].filter(h => /[?&](town|cluster|geo|sub)=/.test(h));
    GUIDE_TOTAL = all.length; if (ROT >= all.length) ROT = 0;
    for (const url of all.slice(ROT, ROT + GUIDES_PER_RUN)) {
      await p.goto(SITE + url, { waitUntil: 'load', timeout: 45000 }).catch(() => {});
      const name = ((await p.locator('h1').first().textContent().catch(() => '')) || url).trim();
      const js = await p.$$eval('a.sj-t[href*="/journeys/suggested.php"]', as => as.map(a => ({ url: a.getAttribute('href'), title: a.textContent.trim() }))).catch(() => []);
      out.push({ key: url, name, url, journeys: js.map(j => ({ ...j, id: +(j.url.match(/id=(\d+)/) || [])[1], stops: null })) });
    }
  } catch {}
  await ctx.close();
  return out;
}
const digits = s => String(s || '').replace(/\D+/g, '').replace(/^1(?=\d{10}$)/, '');
async function guidePath(g) {
  const def = { id: 'guide:' + g.key, kind: 'guide', title: `Follow the ${g.name}${g.state ? ', ' + g.state : ''} guide`, limit: 3 };
  const a = await as('guest');
  const page = await a.ctx.newPage();
  const w = new Walk(page, ('guide-' + g.key).replace(/[^a-z0-9]+/gi, '-'), 'guest', a.site);
  const t = Date.now(); const checked = new Set(); const stopsOut = [];
  try {
    await w.goto(g.url, 'the guide');
    await w.links(4, checked);
    if (!(g.journeys || []).length) w.note('this guide has no suggested journey yet');
    for (const j of (g.journeys || []).slice(0, JOURNEYS_PER_GUIDE)) {
      if (w.here() !== g.url) await w.goto(g.url, 'the guide');
      const link = page.locator(`a[href*="/journeys/suggested.php?id=${j.id}"]`).first();
      if (!(await link.isVisible().catch(() => false))) {
        const folded = await link.count();
        w.add('accuracy', 'J02', g.url, `its journey "${j.title}" ${folded ? 'is on the guide but folded away' : 'doesn\'t show on the guide'}`);
        await w.goto(j.url, 'the journey');
      } else await w.tap(link, j.title, { nav: true });
      if (!/\/journeys\/suggested\.php/.test(w.here())) continue;
      w.goal = true;
      const shown = await page.$$eval('.sj-stop a.sj-n', as => as.map(a => ({ href: a.getAttribute('href'), name: a.textContent.trim() }))).catch(() => []);
      const legs = await page.$$eval('.sj-leg', ls => ls.map(l => l.textContent.trim())).catch(() => []);
      const stops = j.stops || shown.map(s => ({ name: s.name, url: s.href }));
      if (!stops.length) { w.add('dead_end', 'J01', w.here(), 'the journey shows no stops'); continue; }
      if (stops.length > 1 && !legs.some(l => /\bmi\b|\bmin\b/.test(l))) w.add('clarity', 'J02', w.here(), 'no time or distance between the stops');
      if (!(await page.locator('.sj-route[href], a[data-out="directions"]').count())) w.add('accuracy', 'J02', w.here(), 'no directions for the route');
      await w.links(3, checked);
      // the route makes sense: times go forward, no leg longer than an hour's drive, no stop twice
      const seen = new Set();
      for (let i = 0; i < stops.length; i++) {
        const s = stops[i];
        if (i && s.arrive != null && stops[i - 1].leave != null && s.arrive < stops[i - 1].leave) w.add('accuracy', 'J02', w.here(), `${s.name} is timed before the stop ahead of it ends`);
        if (s.leg_miles != null && s.leg_miles > 50) w.add('clarity', 'J02', w.here(), `${s.leg_miles} mi to ${s.name}: a long way for one leg`);
        if (seen.has(s.url)) w.add('accuracy', 'J02', w.here(), `${s.name} is on the route twice`); seen.add(s.url);
        if (j.stops && !shown.some(x => x.href === s.url)) w.add('accuracy', 'J02', w.here(), `${s.name} is in the journey but not shown on its page`);
        if (s.live === false) w.add('accuracy', 'J02', w.here(), `${s.name} is on the route but its listing isn't public any more (closed, hidden or removed)`);
      }
      for (const s of stops.slice(0, STOPS_PER_JOURNEY)) {
        if (left() < 30000) { w.note('stopped early: the run\'s time budget'); break; }
        const row = { name: s.name, url: s.url, opened: false };
        if (/^https?:/.test(s.url)) {   // an OpenStreetMap place: its link goes out (directions); checked to be a real address
          row.opened = /^https:\/\/(www\.)?(openstreetmap\.org|google\.com\/maps|maps\.apple\.com)/.test(s.url);
          if (!row.opened) w.add('accuracy', 'J02', w.here(), `${s.name}'s link goes somewhere unexpected: ${s.url.slice(0, 80)}`);
          stopsOut.push(row); continue;
        }
        const back = w.here();
        const sl = page.locator(`.sj-stop a.sj-n[href="${s.url}"]`).first();
        if (await sl.isVisible().catch(() => false)) await w.tap(sl, s.name, { nav: true }); else await w.goto(s.url, s.name);
        row.opened = w.here().split('#')[0] === s.url || w.here().startsWith(s.url.split('?')[0]);
        const text = await textOf(page);
        if (/not found|isn't here/i.test(text.slice(0, 400))) { w.add('dead_end', 'J01', w.here(), `${s.name}'s page says not found`); await w.shot('stop-404'); }
        if (/\/listing\/view\.php/.test(s.url)) {
          const tel = await page.$$eval('a[href^="tel:"]', as => as.map(a => a.getAttribute('href'))).catch(() => []);
          row.phone = tel.length > 0;
          if (s.phone && !tel.some(h => digits(h) === digits(s.phone))) w.add('accuracy', 'J02', w.here(), tel.length ? `${s.name}'s page shows another phone than our data has` : `${s.name} has a phone in our data but its page has none to call`);
          if (!(await page.locator('a[data-out="directions"]').count())) w.add('accuracy', 'J02', w.here(), `no directions on ${s.name}'s page`);
          const says = /\bOpen\b ·|Open now|Open 24 hours|\bClosed\b/.test(text);
          row.open_shown = says;
          if (s.has_hours && !says) w.add('clarity', 'J02', w.here(), `${s.name}'s page lists hours but doesn't say whether it's open now${s.status ? ' (our data: ' + s.status + ')' : ''}`);
          if (says && s.open_now === true && /\bClosed\b/.test(text) && !/\bOpen\b/.test(text)) w.add('accuracy', 'J02', w.here(), `${s.name}'s page says closed; our data says ${s.status}`);
          if (says && s.open_now === false && /Open now|\bOpen\b ·/.test(text) && !/\bClosed\b/.test(text)) w.add('accuracy', 'J02', w.here(), `${s.name}'s page says open; our data says ${s.status}`);
        }
        await w.links(3, checked);
        stopsOut.push(row);
        if (back) await page.goBack({ waitUntil: 'load', timeout: 30000 }).catch(() => w.goto(back));
      }
    }
  } catch (e) { w.stuck = w.stuck || `the runner stopped: ${String(e).slice(0, 160)}`; await w.shot('error'); }
  // steps: the taps from the guide to its first stop (a guide is read from the guide; "read a town's guide" counts from home)
  const r = w.result(def, { where: 'live', ms: Date.now() - t, guide: { key: g.key, name: g.name, url: g.url, journeys: (g.journeys || []).length, stops: stopsOut } });
  r.steps.taps = Math.min(w.taps, 2); r.steps.over = false;
  await page.close().catch(() => {});
  return r;
}

// ---- cost and effectiveness inputs (the health report: totals only, nothing about a person) ---------------------------
async function healthInputs() {
  if (!CHECK_TOKEN) return null;
  try {
    const r = await fetch(`${SITE}/api/health.php`, { headers: { Authorization: `Bearer ${CHECK_TOKEN}`, 'User-Agent': 'Traversence site checks' } });
    const d = await r.json(); if (!r.ok || d.ok === false) return { error: `answered ${r.status}` };
    // only what's safe in a public repository: no money, no row counts of people
    const jobs = (d.jobs || []).filter(j => j.fails_today > 0 || j.ok === 0 || j.ok === false || (j.last && Date.now() - Date.parse(String(j.last).replace(' ', 'T') + 'Z') > 2 * 86400000)).map(j => ({ job: j.job, fails_today: j.fails_today || 0, last: j.last || null, ok: j.ok }));
    const cache = d.cache ? { entries: d.cache.entries, expired: d.cache.expired, expired_share: d.cache.entries ? Math.round(100 * d.cache.expired / d.cache.entries) : 0 } : null;
    const ai = d.ai && d.ai.cap_usd ? { over_80_pct_of_cap: d.ai.month_usd >= 0.8 * d.ai.cap_usd, features: Object.keys(d.ai.by_feature || {}) } : null;
    const big = (d.tables || []).slice().sort((a, b) => b.mb - a.mb).slice(0, 8).map(t => ({ name: t.name, mb: t.mb }));
    const status = (d.status || []).filter(s => !/ok|good|green/i.test(s.state)).map(s => ({ name: s.name, state: s.state }));
    return { php: d.php_major_minor || d.php, jobs, cache, ai, biggest_tables: big, status };
  } catch (e) { return { error: String(e).slice(0, 120) }; }
}

// ---- run --------------------------------------------------------------------------------------------------------------
const want = id => !ONLY.length || ONLY.includes(id) || (id.startsWith('guide:') && ONLY.includes('guides'));
GUIDE_LIST = await guides();
{ const g = GUIDE_LIST.find(x => x.level === 'town' && x.state); GUIDE_TOWN = g ? `${g.name}, ${g.state}` : ''; }   // the town for "read a town's guide": one with a live guide
const results = [];
for (const def of CORE) {
  for (const who of def.who) {
    if (!want(def.id)) continue;
    if (def.changes && !STAGING) { results.push({ id: def.id, kind: 'core', title: def.title, who, status: 'waits', notes: ['Waits for staging: it changes something, so it runs on staging.trvgo.us only (System Configuration → Staging → Set up staging, then the new CHECK_ACCOUNTS block).'], problems: [], steps: { taps: 0, pages: 0, limit: def.limit, over: false }, counts: {}, changes: true }); continue; }
    if (def.changes && !def.run) { results.push({ id: def.id, kind: 'core', title: def.title, who, status: 'waits', notes: ['Staging is set up; this path\'s runner is still to write (its steps are listed in paths.md).'], problems: [], steps: { taps: 0, pages: 0, limit: def.limit, over: false }, counts: {}, changes: true }); continue; }
    if (who !== 'guest' && !ACC) { results.push({ id: def.id, kind: 'core', title: def.title, who, status: 'skipped', notes: ['CHECK_ACCOUNTS isn\'t set: signed-out paths only.'], problems: [], steps: { taps: 0, pages: 0, limit: def.limit, over: false }, counts: {} }); continue; }
    if (left() < 60000) { results.push({ id: def.id, kind: 'core', title: def.title, who, status: 'skipped', notes: ['The run\'s time budget ran out before this path: it runs first next time.'], problems: [], steps: { taps: 0, pages: 0, limit: def.limit, over: false }, counts: {} }); continue; }
    if (def.signedOut && who !== 'guest') {   // sign in from signed out: a fresh context, not the signed-in one
      const ctx = await checkContext(browser, { context: { viewport: PHONE, isMobile: true, hasTouch: true } }); await ctx.addInitScript(INIT);
      contexts['tmp@live'] = { ctx, site: SITE };
      const r = await walk(def, 'tmp'); r.who = who; results.push(r); await ctx.close(); delete contexts['tmp@live']; continue;
    }
    results.push(await walk(def, who, def.changes ? 'staging' : 'live'));
    console.log(`${def.id} as ${who}: ${results[results.length - 1].status}`);
  }
}
let walked = 0;
for (const g of GUIDE_LIST) {
  if (!want('guide:' + g.key)) continue;
  if (left() < 45000) { results.push({ id: 'guide:' + g.key, kind: 'guide', title: `Follow the ${g.name} guide`, who: 'guest', status: 'skipped', notes: ['The run\'s time budget ran out: next run.'], problems: [], steps: {}, counts: {} }); continue; }
  const r = await guidePath(g); results.push(r); walked++;
  console.log(`${r.id}: ${r.status}`);
}
const health = await healthInputs();
for (const c of Object.values(contexts)) if (c.ctx) await c.ctx.close().catch(() => {});
await browser.close();

// ---- report -----------------------------------------------------------------------------------------------------------
const at = new Date().toISOString();
const next = GUIDE_TOTAL ? (ROT + walked) % GUIDE_TOTAL : 0;
mkdirSync('site-audit/results', { recursive: true });
const data = { at, site: SITE, run_url: RUN_URL, staging: STAGING ? STAGING.site : null, signed_in: !!ACC, full: FULL, budget_s: BUDGET_MS / 1000, took_s: Math.round((Date.now() - T0) / 1000),
  guides: { source: GUIDE_SOURCE, total: GUIDE_TOTAL, this_run: GUIDE_LIST.map(g => g.key), per_run: GUIDES_PER_RUN }, rotation: { from: ROT, next },
  measures: { 'eat-near-me': 3, 'town-guide': 3, other: 5 }, results, health };
writeFileSync(OUT, JSON.stringify(data, null, 1));
// history: a line per path per run, the last 60 runs
let hist = []; try { hist = JSON.parse(readFileSync(HIST, 'utf8')); } catch {}
hist.push({ at, run: RUN_URL, paths: results.filter(r => r.status !== 'waits').map(r => ({ id: r.id, who: r.who, status: r.status, taps: r.steps?.taps ?? null, pages: r.steps?.pages ?? null, ...(r.counts || {}), ms: r.ms || null })) });
hist = hist.slice(-60);
writeFileSync(HIST, JSON.stringify(hist, null, 0).replace(/\},\{"at"/g, '},\n{"at"'));

const esc = s => String(s ?? '').replace(/\|/g, '/').replace(/\n/g, ' ');
const trend = (id, who) => hist.slice(-7).map(h => { const p = h.paths.find(x => x.id === id && x.who === who); return !p ? '·' : p.status === 'passed' ? 'P' : p.status === 'issues' ? 'i' : p.status === 'skipped' ? 's' : p.status === 'stuck' ? '?' : 'F'; }).join(' ');
const stepTxt = r => r.steps && r.steps.limit != null && r.status !== 'waits' && r.status !== 'skipped' ? `${r.steps.taps} (≤${r.steps.limit})${r.steps.over ? ' **over**' : ''}` : '—';
const c = (r, k) => r.counts && r.counts[k] ? String(r.counts[k]) : (r.status === 'waits' || r.status === 'skipped' ? '—' : '0');
const L = ['# Paths', '', `Decision 0145, Phase 4. Generated by \`tools/page-audit/paths.mjs\` on ${at.slice(0, 16).replace('T', ' ')} UTC against ${SITE}` + (ACC ? ', signed out and as the test accounts' : ', signed out only (CHECK_ACCOUNTS isn\'t set)') + `, at phone size. Took ${data.took_s} s. Don't edit by hand.` + (RUN_URL ? ` Screenshots: [the run](${RUN_URL}).` : ''), '',
  '**Status:** P passed · i reached the goal with issues (accuracy or surprises) · F failed (goal not reached, over the step measure, or a dead end) · ? the runner couldn\'t find what a visitor taps (the page or the runner may be wrong) · s skipped · waits: needs staging. **Steps** are taps to the goal; the measure is 3 or fewer from the home page for finding a place to eat and reading a town\'s guide, 5 or fewer for the rest (punch-list item 6).', '',
  '## Core paths', '', '| Path | Who | Status | Steps | Dead ends | Surprises | Accuracy | Clarity | Last 7 runs |', '|---|---|---|---|---|---|---|---|---|'];
for (const r of results.filter(r => r.kind === 'core' && r.status !== 'waits')) L.push(`| ${esc(r.title)} | ${r.who} | ${r.status} | ${stepTxt(r)} | ${c(r, 'dead_ends')} | ${c(r, 'surprises')} | ${c(r, 'accuracy')} | ${c(r, 'clarity')} | ${trend(r.id, r.who)} |`);
const waits = results.filter(r => r.status === 'waits');
if (waits.length) { L.push('', '### Waiting for staging', '', 'These change something (an account, a like, a post, a decision), so they run on the staging copy only, as the test accounts, never on live.' + (STAGING ? '' : ' Staging isn\'t in CHECK_ACCOUNTS yet: they start once Jason presses **Set up staging** (System Configuration → Staging) and the new block replaces the secret.'), '', '| Path | Who | Runner |', '|---|---|---|');
  for (const r of waits) { const d = CORE.find(x => x.id === r.id); L.push(`| ${esc(r.title)} | ${r.who} | ${d && d.run ? 'written' : 'to write once staging can be looked at'} |`); } }
L.push('', '## Guide paths', '', `${GUIDE_TOTAL} live guide${GUIDE_TOTAL === 1 ? '' : 's'} (from ${GUIDE_SOURCE === 'endpoint' ? '`/api/guide-paths.php`, with what our data says about each stop' : 'Discovery\'s links, until `/api/guide-paths.php` is live: stops as their pages show them, without our data to compare'}). ${walked} walked this run, from number ${ROT + 1}; the next run starts at ${next + 1}, so every guide is walked in turn. Each: the guide opens and shows its suggested journey; the journey shows its stops, their times and the distance between; each stop's page opens and says what our data says (open now, its phone), with directions; its links work.`, '',
  '| Guide | Status | Journeys | Stops opened | Dead ends | Surprises | Accuracy | Clarity | Last 7 runs |', '|---|---|---|---|---|---|---|---|---|');
for (const r of results.filter(r => r.kind === 'guide')) L.push(`| [${esc(r.guide?.name || r.title)}](${SITE}${r.guide?.url || ''}) | ${r.status} | ${r.guide?.journeys ?? '—'} | ${r.guide ? r.guide.stops.filter(s => s.opened).length + ' of ' + r.guide.stops.length : '—'} | ${c(r, 'dead_ends')} | ${c(r, 'surprises')} | ${c(r, 'accuracy')} | ${c(r, 'clarity')} | ${trend(r.id, 'guest')} |`);
const probs = results.filter(r => (r.problems || []).length || r.stuck);
if (probs.length) {
  L.push('', '## What got in the way', '', '| Path | Who | Kind | Rule | Page | What |', '|---|---|---|---|---|---|');
  for (const r of probs) {
    if (r.stuck) L.push(`| ${esc(r.title)} | ${r.who} | stuck | J01 | | ${esc(r.stuck)} |`);
    for (const p of r.problems) L.push(`| ${esc(r.title)} | ${r.who} | ${p.kind.replace('_', ' ')} | ${p.rule} | \`${esc(p.page)}\` | ${esc(p.what)} |`);
  }
}
const slow = results.flatMap(r => (r.timings || []).map(t => ({ ...t, path: r.title }))).sort((a, b) => b.ms - a.ms).slice(0, 8);
if (slow.length) { L.push('', '## Slowest pages on the way', '', '| Page | Seconds | Path |', '|---|---|---|'); for (const t of slow) L.push(`| \`${esc(t.page)}\` | ${(t.ms / 1000).toFixed(1)} | ${esc(t.path)} |`); }
const notes = results.filter(r => (r.notes || []).length && r.status !== 'waits');
if (notes.length) { L.push('', '## Notes', ''); for (const r of notes) L.push(`- **${esc(r.title)}** (${r.who}): ${r.notes.map(esc).join('; ')}`); }
if (health) {
  L.push('', '## Cost and effectiveness (from the health report, totals only)', '');
  if (health.error) L.push(`The health report couldn't be read: ${esc(health.error)}.`);
  else {
    L.push(`- **Scheduled jobs that failed today or haven't run in two days:** ${health.jobs.length ? health.jobs.map(j => `${j.job}${j.fails_today ? ' (' + j.fails_today + ' failed today)' : ''}${j.last ? ', last ' + j.last : ', never'}`).map(esc).join('; ') : 'none'}`);
    if (health.cache) L.push(`- **Page cache:** ${health.cache.entries} entries, ${health.cache.expired_share}% expired (a hit rate isn't in the health report yet)`);
    if (health.ai) L.push(`- **AI this month:** ${health.ai.over_80_pct_of_cap ? 'over 80% of the cap' : 'under 80% of the cap'} (money isn't written here: this repository is public)`);
    if (health.biggest_tables.length) L.push(`- **Largest tables:** ${health.biggest_tables.map(t => `${t.name} ${t.mb} MB`).map(esc).join(', ')}`);
    if (health.status.length) L.push(`- **Admin status checks not OK:** ${health.status.map(s => `${s.name}: ${s.state}`).map(esc).join('; ')}`);
  }
}
writeFileSync(MD, L.join('\n') + '\n');
console.log(`Paths: ${results.length} (${results.filter(r => r.status === 'passed').length} passed, ${results.filter(r => r.status === 'waits').length} wait for staging), ${data.took_s} s.`);
