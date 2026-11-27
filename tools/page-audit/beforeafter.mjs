// tools/page-audit/beforeafter.mjs — Piece 3's before-and-after check (decisions/0179 rule 2 and its notes "How the Team's
// capacity grows" §4 and "Piece 3's decisions"; handoffs/piece3-check.md). Read only: it signs in as the test accounts and
// the staff test logins (check-kit.mjs), opens pages and reads them; it never sends a form or changes anything.
//
//   capture  node tools/page-audit/beforeafter.mjs capture --pages pages.json --out cap.json [--shots dir]
//            each advocate's view of each page (rendered.mjs's page check, page-check.mjs, plus what §1.5 adds), on
//            staging (CHECK_ACCOUNTS' staging block) or BA_SITE (a test site). pages.json: ["/", "/user/…", "/admin/…"].
//   compare  node tools/page-audit/beforeafter.mjs compare --before a.json --before2 b.json --after c.json
//            [--expected exp.json] [--release N] [--out verdict.json]
//            two looks before (what differs between them moves on its own and never counts) against the look after; the
//            four rules (§2). A problem already there before is noted, never blocking. exp.json: {"pages": [...],
//            "also_changes": [{"page", "why"}], "held": {"hashes": [...]} | {"error": why}}.
//   held     node tools/page-audit/beforeafter.mjs held --out held.json
//            the names the site holds back (0066) as keyed hashes only, from staging's /api/held-hashes.php (P3-5; Jason,
//            2026-11-25, Brief B: "a locked read against live-protected places rather than adding fake test records").
//            compare hashes every phrase of 2 to 8 words on a page the same way: a held name that shows after the change
//            and didn't before blocks it (rule 1, 0066), named by its hash, never by the name. Unread: incomplete.
//
// The advocates (team_roster, P2-2; Staff Views added in P3-2, Jason 2026-11-25): each bound to a test account key.
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { createHmac } from 'node:crypto';
import { ACC, STAGING, CHECK_TOKEN, memberIn, staffIn, checkContext } from './check-kit.mjs';
import { STANDARDS, pageCheck } from './page-check.mjs';

export const ADVOCATES = [
  // [advocate slug, test account key ('' = signed out), kind]
  ['advocate-guest', '', 'guest'],
  ['advocate-new-member', 'new', 'member'], ['advocate-member', 'member', 'member'], ['advocate-contributor', 'contributor', 'member'],
  ['advocate-steward', 'steward', 'member'], ['advocate-client', 'client', 'member'], ['advocate-business-owner', 'owner', 'member'],
  ['advocate-business-manager', 'manager', 'member'], ['advocate-business-accountant', 'accountant', 'member'],
  ['advocate-business-sales', 'sales', 'member'], ['advocate-business-viewer', 'viewer', 'member'],
  // Staff Views (Jason, 2026-11-25): the staff test logins on admin pages; the Executive at both sizes, a Staff login for access
  ['advocate-staff-views', 'executive', 'staff'], ['advocate-staff-views', 'ops-staff', 'staff'],
];
const MAX_PAGES = +(process.env.BA_MAX_PAGES || 40);
const kindOf = (u) => u.startsWith('/admin') ? 'admin' : (/^\/(user\/|business\/|dashboard\.php)/.test(u) ? 'member' : 'public');
const SIGNIN = /\/(admin\/login\.php|login\.php|user\/login|sign-?in)/i;

/**
 * Who opens which page at which sizes (piece3-check.md §1.3, kept inside the minutes' budget, DB4). One advocate of each kind
 * covers every page of its kinds: the guest, the Member advocate, the Executive and the Staff login. The other member
 * advocates look at a sample, each from a different offset so between them they cover more: their own member pages (up to
 * BA_MEMBER_PAGES, both sizes), and BA_SAMPLE public pages (the canary) and admin pages (a member is refused) at phone size.
 * Every rule each advocate checks is kept; only how many pages the extra advocates repeat it on is capped. Before
 * 2026-11-26 every member advocate opened every page: #303's 40 pages were 520 views planned, 424 run, 12.7 min a look.
 */
const PAR = Math.max(1, +(process.env.BA_PAR || 2));
// the files that draw the system's state lines: an alarm's thread, the dashboard's status rows, status lines and notices
export const STATE_DRAWERS = /(^|\/)(lib\/(Thread|ControlAlarms|ServiceHealth|Scheduler|Dispatch\w*|Notify)\.php|ui\/(admin-dashboard|app-shell|page-shell)\.php|admin\/(control-console|module-cards|widgets|kit-css)\.php|js\/(tv-ask|admin-kit|pulse-msg)\.js)$/;   // advocates looking at once (capture)
const SAMPLE = +(process.env.BA_SAMPLE || 2), MEMBER_PAGES = +(process.env.BA_MEMBER_PAGES || 6);
const spread = (list, n, at) => { if (list.length <= n) return list; const step = list.length / n; return Array.from({ length: n }, (_, j) => list[Math.floor(at + j * step) % list.length]).filter((u, j, a) => a.indexOf(u) === j); };
export function plan(pages, acc) {
  const out = []; const P = pages.slice(0, MAX_PAGES);
  const of = k => P.filter(u => kindOf(u) === k);
  let nth = 0;   // which extra member advocate this is: its sample starts further along
  for (const [slug, key, kind] of ADVOCATES) {
    if (kind === 'guest') { for (const u of of('public')) out.push([slug, key, kind, u, ['desktop', 'phone']]); continue; }
    if (kind === 'member') {
      if (!acc?.members?.[key]) continue;
      const full = key === 'member'; const at = full ? 0 : ++nth;
      for (const u of full ? of('member') : spread(of('member'), MEMBER_PAGES, at)) out.push([slug, key, kind, u, ['desktop', 'phone']]);
      for (const u of full ? of('public') : spread(of('public'), SAMPLE, at)) out.push([slug, key, kind, u, ['phone']]);   // a canary in a public view
      for (const u of full ? of('admin') : spread(of('admin'), SAMPLE, at)) out.push([slug, key, kind, u, ['phone']]);    // a member never opens admin
      continue;
    }
    if (!acc?.staff?.[key]) continue;
    for (const u of of('admin')) out.push([slug, key, kind, u, key === 'executive' ? ['desktop', 'phone'] : ['phone']]);
  }
  return out;
}

/** What §1.5 adds to the page check, read from the loaded page. */
export async function extra(p, size) {
  const r = await p.evaluate(() => {
    const vis = el => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && +s.opacity !== 0; };
    const clean = el => { const c = el.cloneNode(true); c.querySelectorAll('[data-tv-time], time, script, style, noscript').forEach(x => x.remove()); return (c.innerText || c.textContent || '').replace(/\s+/g, ' ').trim(); };
    const W = window.innerWidth;
    // lines and controls the system draws from its own state (an alarm's thread, the dashboard's status rows, a status line, a
    // notice) follow the system, not a release: marked, and left out of drift unless the release draws them (2026-11-26: #9304's
    // look blocked on staging's late scheduled tasks)
    const STATE = '.tv-th, a.ad-r, [data-tv-status], .tv-note, [data-tv-flash]';
    // the values of records (a list's or table's rows, a card, a detail's values) are data, never structure: catch-up changes
    // them on staging every minute, so drift compares a page's structure (headings, labels, controls, the sections present),
    // never what a record says (the session manager, 2026-11-26, after #305's look blocked on a queue row's name). A release
    // that changes how a list draws is still caught: its headings and column heads, the layout rules and the data counts.
    // an item card (the helper's hp-card, any <kind>-card) is a record too: a queue that was empty before ("All caught up.") and
    // holds an item after has a record more, not a new control (#307's look, /admin/helper.php); the admin kit's ak-card is a
    // section, so it stays structure
    const REC = 'tbody, tr[data-id], article, .card, [class*="-card"]:not(.ak-card), [data-id], main li, dd';   // a table's head (thead, th) stays structure
    // a section that holds records (a list, a table's rows, cards) comes and goes with its data: a queue with nothing in it is
    // often not drawn at all (#308's look: "Recently published automatically", "Geo-hub", "Technical details" came and went on
    // staging). Its heading and its own controls are marked with the section, so drift reads them as data
    const SEC = 'section, article, details, fieldset, .ak-card, .tv-bleed, [data-region]';
    const secOf = el => { const c = el.closest(SEC); return c && c.querySelector(REC) ? c : null; };
    const secName = c => { const h = c.querySelector('h1, h2, h3, h4, h5, h6, summary, legend'); return h ? (h.innerText || h.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 120) : (c.id || c.className || '').toString().slice(0, 60); };
    // a pill row too narrow for its tabs folds the last ones under "More ▾" (js/tv-ask.js, data-more): the same tabs, folded
    // or not by a few pixels of width (#322's look, /admin/people.php at 390px: Staff logins one look, More ▾ the next). Its
    // folded tabs count as shown and its More button as none, so a fold is never a control gone or new. A folded one counts
    // only if nothing up to the menu hides it of its own: the menu's ">*{display:flex!important}" would show a tab hidden in
    // the row, so its hidden attribute and inline display are read, not its computed display (the CTO's Tier 4, 2026-11-27)
    const CTL = 'a[href], button, [role=button], input[type=submit]';
    const foldVis = el => { const m = el.closest('.tvm-menu'); for (let x = el; x && x !== m; x = x.parentElement) { const cs = getComputedStyle(x); if (x.hasAttribute('hidden') || x.style.display === 'none' || cs.visibility === 'hidden' || (x.parentElement !== m && cs.display === 'none')) return false; } return true; };
    const ctl = [...document.querySelectorAll(CTL)].filter(el => !el.classList.contains('tvm-btn') && (el.closest('.tvm-menu') ? foldVis(el) : vis(el)));
    // a pill row's items are named from their text as written, shown or folded alike: a shown pill's innerText takes its
    // CSS text-transform, a folded one's doesn't (the CTO's Tier 4)
    const name = el => ((el.closest('.tvpills, [data-more]') ? el.textContent : (el.innerText || el.textContent || '')).trim() || el.getAttribute('aria-label') || el.getAttribute('title') || '').replace(/\s+/g, ' ').slice(0, 80);
    const controls = ctl.slice(0, 300).map(el => ({ n: name(el), h: (el.getAttribute('href') || '').replace(/^https?:\/\/[^/]+/, '').slice(0, 120), d: !!(el.disabled || el.getAttribute('aria-disabled') === 'true'), ...(el.closest(STATE) ? { st: 1 } : {}), ...(el.closest(REC) ? { rec: 1 } : {}), ...(secOf(el) ? { sec: secName(secOf(el)) } : {}), ...(el.tagName === 'A' && !el.closest('header, nav, [role=navigation], [role=tablist]') ? { lk: 1 } : {}) }));
    // regions: the header, main, and each card, by a stable key (its id, else its class and place among its kind)
    const regions = {}; const seen = {};
    const cards = [...document.querySelectorAll('header, main, nav, .ak-card, .tv-bleed, article, section[id], [data-region]')].filter(vis).slice(0, 120);
    for (const el of cards) {
      const base = el.id ? '#' + el.id : el.tagName.toLowerCase() + (el.classList[0] ? '.' + el.classList[0] : '');
      const k = base + (el.id ? '' : ':' + (seen[base] = (seen[base] || 0) + 1));
      const t = clean(el);
      regions[k] = { t: t.slice(0, 600), n: el.querySelectorAll('li, tr, .card, .ak-card, article').length };
    }
    const text = clean(document.body);
    // the words as drawn (the live body's innerText keeps a break between elements; a detached copy's runs them together),
    // for the held names' check (P3-5): "Plan a trip" then "Zz Held Burial" must not read as "tripZz Held Burial"
    const words = (document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40000);
    const totals = [...text.matchAll(/\b\d+\s*[–-]\s*\d+\s+of\s+([\d,]+)/g)].map(m => +m[1].replace(/,/g, ''));
    const cardsN = document.querySelectorAll('.card, .ak-card, .tv-card, article').length, rowsN = document.querySelectorAll('tbody tr').length, pins = document.querySelectorAll('.leaflet-marker-icon, [data-pin]').length;
    // overlap between controls and cards that aren't one inside the other
    const boxes = ctl.slice(0, 200).map(el => [el, el.getBoundingClientRect()]);
    const over = [];
    for (let i = 0; i < boxes.length && over.length < 5; i++) for (let j = i + 1; j < boxes.length && over.length < 5; j++) {
      const [a, ra] = boxes[i], [b, rb] = boxes[j]; if (a.contains(b) || b.contains(a) || (a.closest(REC) && b.closest(REC))) continue;   // two controls in records: their data
      const w = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left), h = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
      if (w > 4 && h > 4) over.push(name(a) + ' × ' + name(b));
    }
    const clipped = [...document.querySelectorAll('h1, h2, h3, p, a, button, td, th, label, span')].filter(el => vis(el) && el.children.length === 0 && (el.innerText || '').trim()
      && el.scrollWidth > el.clientWidth + 1 && /hidden|clip/.test(getComputedStyle(el).overflowX) && getComputedStyle(el).textOverflow !== 'ellipsis').slice(0, 5).map(el => (el.innerText || '').slice(0, 60));
    const offscreen = ctl.filter(el => !el.closest(REC)).filter(el => { const r = el.getBoundingClientRect(); return getComputedStyle(el).position !== 'fixed' && (r.right > W + 1 || r.left < -1); }).slice(0, 5).map(name);
    // what makes the page wider than the screen: when every element past its right edge is inside a record (a long address in a
    // found-on cell, #308's look at 390), the overflow is that record's data, read as such by compare on a page the change
    // doesn't name; a wide element of the page's own (a bar, a form) still blocks
    const past = [...document.querySelectorAll('body *')].filter(el => el.children.length === 0 && vis(el) && el.getBoundingClientRect().right > W + 1 && getComputedStyle(el).position !== 'fixed');
    const wideRec = past.length > 0 && past.every(el => el.closest(REC) || el.closest('table')?.querySelector(REC));   // a table holding records widens with them, its column heads too
    const primary = [...document.querySelectorAll('.ak-b.go, [data-tv-primary], .tv-strip [data-strip=save], .tv-strip [data-strip=close]')].filter(vis).map(name).slice(0, 5);
    const heading = (document.querySelector('h1, .tv-strip .tv-strip-name, h2') || {}).innerText || '';
    const nav = document.querySelectorAll('header a[href], header button, [data-tv-trail] a').length;
    const canaries = [...new Set([...text.matchAll(/check\+([a-z0-9._-]+)@traversence\.com/gi)].map(m => m[1].toLowerCase()))];
    // each block of words on its own line (a paragraph, heading, list item, cell, label, button or link), so one line that
    // moves on its own (a counter) never hides a line that changed
    const all = [...document.querySelectorAll('p, h1, h2, h3, h4, h5, h6, li, td, th, dt, dd, label, button, a, figcaption, small, summary')].filter(vis);
    // drift reads a page's structure (the session manager, 2026-11-26, after #305's and #306's looks blocked on catch-up's
    // data): its headings, column heads, labels, legends, folds and terms, its own buttons and its header's and menus' links.
    // On an admin page that's all: its paragraphs, list items, cells and links are queues and records that catch-up changes
    // every minute. On a public page its own words outside records (prose) count too (compare, by the page's address).
    const STRUCT = 'h1, h2, h3, h4, h5, h6, th, label, legend, summary, dt';
    const prose = [...new Set(all.filter(el => !el.matches(STRUCT) && !el.closest(STATE) && !el.closest(REC)).map(el => clean(el).slice(0, 200)).filter(Boolean))].slice(0, 400);
    const lines = [...new Set(all.filter(el => el.matches(STRUCT) && !el.closest(STATE) && !el.closest(REC)).map(el => clean(el).slice(0, 200)).filter(Boolean))].slice(0, 400);
    const dlines = [...new Set(all.filter(el => el.matches(STRUCT) && !el.closest(STATE) && !el.closest(REC) && secOf(el)).map(el => clean(el).slice(0, 200)).filter(Boolean))].slice(0, 400);   // headings of sections that hold records
    const state = [...new Set(all.filter(el => el.closest(STATE)).map(el => clean(el).slice(0, 200)).filter(Boolean))].slice(0, 200);   // checked only when the release draws them
    return { lines, dlines, wideRec, prose, state, controls, regions, totals, cards: cardsN, rows: rowsN, recs: document.querySelectorAll(REC).length, pins, overlap: over, clipped, offscreen, primary, heading: heading.replace(/\s+/g, ' ').trim().slice(0, 120), nav, canaries, text: text.slice(0, 20000), words };
  });
  r.landed_signin = SIGNIN.test(new URL(p.url()).pathname);
  return { ba: r };
}

/** The views that timed out in a look (its file), as "who|page|size": a later look doesn't try them twice. */
export function slowViews(file) {
  if (!file) return new Set();
  try { return new Set((JSON.parse(readFileSync(file, 'utf8')).results || []).filter(f => f.error && /Timeout/i.test(f.error)).map(f => f.who + '|' + f.url + '|' + f.size)); } catch (e) { return new Set(); }
}

/** The views to try once more, with LATE_MS, after an advocate's whole look (the CEO's amended Tier 4 condition, 2026-11-27,
 *  #318): a timeout on a page the release reaches only through a shared file it changes (reach.py's expected, not drawn), and
 *  not already slow in the first before look. Opened then: a note with its time against the before look's; still not: a
 *  block, unless a before look of it timed out or needed its retry too (slowness already there: a note). */
export const LATE_MS = 90000;   // the late retry's time: double a view's 45 s (the CEO's amended condition, 2026-11-27)
export function farTimeouts(results, exp = {}, slow = new Set()) {
  if (!Array.isArray(exp.drawn) || !(exp.shared || []).length) return [];
  const expected = new Set(exp.pages || []), drawn = new Set(exp.drawn);
  return results.map((f, i) => [f, i]).filter(([f]) => f.error && /Timeout/i.test(f.error) && expected.has(f.url) && !drawn.has(f.url) && !slow.has(f.who + '|' + f.url + '|' + f.size)).map(([, i]) => i);
}

/** The parts of a page (its regions: the header, main, each card) there in both before looks and gone in this one, on a page the
 *  release doesn't reach (the session manager, 2026-11-27, after #319's look: /business/'s Storage tile, dropped by a silent catch
 *  on staging). Capture looks at such a view once more after the advocate's look: back then, compare notes it as flaky on
 *  staging; still gone, it blocks as before. A release that really removes a part stays gone on the re-look. */
export function partsGone(b, bb, f, exp = {}) {
  if (!b?.ba?.regions || !f?.ba?.regions || f.error || new Set([...(exp.pages || []), ...(exp.also_changes || []).map(x => x.page)]).has(f.url)) return [];
  const two = bb?.ba?.regions || b.ba.regions;
  return Object.keys(b.ba.regions).filter(r => (b.ba.regions[r]?.t || '') && !f.ba.regions[r] && two[r]);
}

async function capture(pages, out, shotDir, slow = new Set(), exp = {}, before = null, before2 = null) {
  const site = process.env.BA_SITE || STAGING?.site; if (!site) throw new Error('No staging in CHECK_ACCOUNTS and no BA_SITE: nothing to check.');
  const st = process.env.BA_SITE ? null : STAGING; const token = st ? st.token : CHECK_TOKEN;
  const t0 = Date.now(); const browser = await chromium.launch(); const results = []; const signins = {};
  if (shotDir) mkdirSync(shotDir, { recursive: true });
  const byWho = {}; for (const row of plan(pages, ACC)) (byWho[row[0] + '|' + row[1]] = byWho[row[0] + '|' + row[1]] || []).push(row);
  // the advocates look side by side, PAR at a time (2 unless BA_PAR says; 2026-11-26: staging at 3,733 s a look, raised only when it answers cleanly), each in its own browser context (2026-11-26: #9304's look took 22 minutes
  // a pass one view after another, 6.4 s a view on an open staging; the 1.79 s it was planned at was a closed staging's 503s).
  // Results keep the plan's order, so a look compares view for view as before.
  const groups = Object.values(byWho); const done = groups.map(() => []);
  const one = async (gi) => {
    const rows = groups[gi]; const [slug, key, kind] = rows[0]; const who = key || 'guest';
    const ctx = await checkContext(browser, { site, token, staging: st });
    try {
      try {
        if (kind === 'member') await memberIn(ctx, ACC.members[key], site, token);
        if (kind === 'staff') await staffIn(ctx, ACC.staff[key], site);
        signins[who] = 'ok';
      } catch (e) { signins[who] = String(e).slice(0, 200); return; }
      for (const [, , , url, sizes] of rows) for (const size of sizes) {
        const opts = { site, shots: !!shotDir, shotDir, allSizes: true, must: () => false, extra };
        let f = await pageCheck(ctx, who, url, size, opts);
        // a view that timed out in the first before look isn't tried again in a later look (the session manager, 2026-11-26:
        // #305's two slow pages cost half of each pass); it's noted slow on staging, never a block, since it timed out before too
        if (f.error && /Timeout/i.test(f.error) && slow.has(who + '|' + url + '|' + size)) console.log(`not retried: ${who} ${url} ${size} timed out in the first before look too`);
        else if (f.error && /Timeout/i.test(f.error)) {   // a slow staging: once more, once only, logged, before it counts
          const again = await pageCheck(ctx, who, url, size, opts); again.retried = f.error;
          console.log(`retried once: ${who} ${url} ${size} timed out (${f.error.split('\n')[0].slice(0, 80)}); again: ${again.error ? 'timed out too' : again.status}`); f = again;
        }
        f.advocate = slug; done[gi].push(f);
      }
      // once the advocate's whole look is done: a far timeout (farTimeouts) once more, same account and width
      for (const i of farTimeouts(done[gi], exp, slow)) {
        const f = done[gi][i]; const opts = { site, shots: !!shotDir, shotDir, allSizes: true, must: () => false, extra, timeout: LATE_MS };
        const again = await pageCheck(ctx, f.who, f.url, f.size, opts); again.retried = f.retried; again.late = f.error.split('\n')[0].slice(0, 120); again.advocate = f.advocate;
        console.log(`tried again after the look: ${f.who} ${f.url} ${f.size} (reached only through ${(exp.shared || []).join(', ')}): ${again.error ? 'timed out again' : again.status}`);
        done[gi][i] = again;
      }
      // then a part gone on a far page (partsGone), once more: back, it's staging's flake (compare notes it); still gone, it blocks
      if (before) {
        const k = x => x.who + '|' + x.url + '|' + x.size;
        const B1 = new Map((before.results || []).map(x => [k(x), x])), B2 = new Map(((before2 || before).results || []).map(x => [k(x), x]));
        for (let i = 0; i < done[gi].length; i++) {
          const f = done[gi][i]; const gone = partsGone(B1.get(k(f)), B2.get(k(f)), f, exp); if (!gone.length) continue;
          const opts = { site, shots: !!shotDir, shotDir, allSizes: true, must: () => false, extra };
          const again = await pageCheck(ctx, f.who, f.url, f.size, opts); again.retried = f.retried; again.late = f.late; again.relook = gone.slice(0, 5); again.advocate = f.advocate;
          const still = partsGone(B1.get(k(f)), B2.get(k(f)), again, exp);
          console.log(`looked again: ${f.who} ${f.url} ${f.size}, gone ${gone.slice(0, 2).join(', ')}: ${again.error ? 'didn\'t open' : still.length ? 'still gone' : 'back'}`);
          done[gi][i] = again.error ? Object.assign(f, { relook: gone.slice(0, 5), relook_error: again.error.split('\n')[0].slice(0, 120) }) : again;
        }
      }
    } finally { await ctx.close(); }
  };
  let next = 0; await Promise.all(Array.from({ length: Math.min(PAR, groups.length) }, async () => { while (next < groups.length) await one(next++); }));
  for (const d of done) results.push(...d);
  await browser.close();
  writeFileSync(out, JSON.stringify({ at: new Date().toISOString(), site, pages, signins, loads: results.length, seconds: Math.round((Date.now() - t0) / 1000), results }));
  console.log(`captured ${results.length} views in ${Math.round((Date.now() - t0) / 1000)}s → ${out}`);
}

// ---- compare ---------------------------------------------------------------------------------------------------------
const RULES = { access: 'Access or privacy (0066 included)', layout: 'Layout or phone', data: 'Missing data or a broken flow', drift: 'Out-of-scope drift' };
const key = f => `${f.who}|${f.url}|${f.size}`;

/** A name or a phrase reduced as HeldNames::norm does it (lower case, letters and digits, single spaces). */
export const norm = s => String(s).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
/** HeldNames::hash: the first 24 hex of HMAC-SHA256(the reduced name, the checks' token). */
export const heldHash = (s, k) => createHmac('sha256', k).update(norm(s)).digest('hex').slice(0, 24);
/** The held names' hashes a page's text holds: every run of 2 to 8 words, hashed, kept if it's in the set. */
export function heldIn(text, hashes, k) {
  const out = new Set(); if (!hashes.size || !k) return out;
  const w = norm(text).split(' ').filter(Boolean);
  for (let i = 0; i < w.length; i++) for (let n = 2; n <= 8 && i + n <= w.length; n++) { const h = heldHash(w.slice(i, i + n).join(' '), k); if (hashes.has(h)) out.add(h); }
  return out;
}
const refused = f => !f || f.error || f.status >= 400 || f.ba?.landed_signin;
const errs = f => f && (f.status >= 500 || (f.console || []).length || (f.requests || []).length);
// a page's controls as the set of distinct ones (more of the same button is more data, never a new control: #308's look), less
// those of a section that holds records and came or went between the looks (skip: its names)
export const ctlSig = (f, st = true, skip = new Set()) => [...new Set((f?.ba?.controls || []).filter(c => (st || !c.st) && !c.rec && !(c.sec && skip.has(c.sec)) && !(c.lk && /^\/admin(\/|\.|$)/.test(f?.url || ''))).map(c => (c.n + '→' + c.h).replace(/\d+/g, '#')))].sort().join('\n');

/** The verdict for one release: blocks (each a rule, who, page, size, what), notes, and pass / block / incomplete. */
/** How many releases in a row, just before this one, left each view's drift and layout unchecked because its before looks
 *  timed out (the CTO's Tier 4, 2026-11-27: the 3rd in a row becomes a ticket): read from the earlier verdicts' notes. */
export function priorSkips(dir, release) {
  const n = Number(release) || Infinity; let files = [];
  try { files = readdirSync(dir).map(f => [Number(f.replace(/\.json$/, '')), f]).filter(([r]) => r > 0 && r < n).sort((x, y) => y[0] - x[0]); } catch (x) { return {}; }
  const skipped = v => new Set((v.notes || []).map(t => (t.match(/^([^|]+\|[^|]+\|[a-z]+): its before looks? timed out/) || [])[1]).filter(Boolean));
  const vs = []; for (const [, f] of files.slice(0, 20)) { try { vs.push(JSON.parse(readFileSync(dir + '/' + f, 'utf8'))); } catch (x) { vs.push({}); } }
  const out = {}; if (!vs.length) return out;
  for (const k of skipped(vs[0])) { let c = 0; for (const v of vs) { if (skipped(v).has(k)) c++; else break; } out[k] = c; }
  return out;
}

export function compare(b1, b2, a, exp = {}, release = '', heldKey = STAGING?.token || CHECK_TOKEN) {
  const B = new Map(b1.results.map(f => [key(f), f])), B2 = new Map(b2.results.map(f => [key(f), f]));
  const A = new Map(a.results.map(f => [key(f), f]));
  const expected = new Set([...(exp.pages || []), ...(exp.also_changes || []).map(x => x.page)]);
  // the system's state lines (alarms, status lines, Scheduled tasks) are drift only for a release that touches what draws them
  // (the session manager, 2026-11-26); unknown files (no list given) count as touching them. Drift is read only on pages the
  // release doesn't reach, and reach.py puts every page a drawer draws into expected; so once reach has named the pages, a page
  // outside them isn't drawn by the release's drawers and its state lines are catch-up's (#319's look, 2026-11-27: module-cards.php
  // made the dashboard Inbox's new Visitor row drift on /admin/). With no pages named, a drawer still turns them on everywhere
  const stateDrawn = !Array.isArray(exp.files) || (exp.files.some(f => STATE_DRAWERS.test(f)) && !(exp.pages || []).length);
  const held = new Set(exp.held?.hashes || []); const heldUnread = !!exp.held?.error;
  const blocks = [], notes = [], far = [], tickets = [];   // tickets: a page skipped 3 releases in a row (the CTO's Tier 4)   // far: the slow pages reached only through a shared file, for the release's card
  if (heldUnread) notes.push('0066 not checked: the held names couldn\'t be read (' + String(exp.held.error).slice(0, 120) + ')');
  else if (!exp.held) notes.push('0066: no held names given to this run (held.json)');
  const block = (rule, f, what, before = '', after = '') => blocks.push({ rule, rule_name: RULES[rule], advocate: f.advocate, who: f.who, page: f.url, size: f.size, what, before: String(before).slice(0, 300), after: String(after).slice(0, 300) });
  for (const who of Object.keys(a.signins || {})) {
    if (a.signins[who] !== 'ok' && (b1.signins || {})[who] === 'ok') blocks.push({ rule: 'access', rule_name: RULES.access, who, page: '(sign-in)', size: '-', what: 'This test account could sign in before and can\'t after', before: 'ok', after: a.signins[who] });
    else if (a.signins[who] !== 'ok') notes.push(`${who} couldn't sign in before or after (${a.signins[who]}): its views weren't checked`);
  }
  // a before look staging couldn't give (it answered 500 or more, as while a fresh copy goes in and every page says "Staging is
  // being refreshed", or it drew nothing to read) is no before look: compared with it, every rule would read the page coming
  // back as a change and block, and a block withdraws the release (2026-11-26, the session manager's case). Said, not blocked;
  // with no usable before look at all, the verdict is incomplete (a person looks), never pass
  const unusable = x => !x || x.status >= 500 || (!x.ba && !refused(x));
  let compared = 0;
  const timedOut = x => !!(x && x.error && /Timeout/i.test(x.error));
  for (const f of a.results) {
    let b = B.get(key(f)), bb = B2.get(key(f)); if (!b) { notes.push(`${key(f)}: no before look to compare`); continue; }
    // a before look that timed out holds only the part of the page drawn by then (#323's look, /admin/crawler-review.php at
    // 390: its header, not its "Find any listing to edit" box). When the second before look opened, it's the one compared
    // with (the CTO's Tier 4, 2026-11-27); when neither did, on a page the release doesn't reach, only drift and layout are
    // left out (partial, below): access, 0066, canaries and errors are always read
    if (timedOut(b) && bb && !bb.error && bb.ba) { b = bb; }
    const partial = timedOut(b) && !f.error && !expected.has(f.url);
    if (f.retried) notes.push(`${key(f)}: timed out once, opened again (${f.error ? 'timed out again' : 'answered ' + f.status})`);
    if (unusable(b)) { notes.push(`${key(f)}: its before look wasn't usable (staging answered ${b.status || b.error || 'nothing'}); not compared`); continue; }
    compared++;
    // a view that timed out after, on a page the release doesn't reach (reach.py's pages, the names it gives), is staging's
    // slowness, not the change: noted for the IT Manager's lane, never an access block (#307's look, /admin/crawler-review.php,
    // which timed out in #305's look too); on a page the release reaches it still blocks, below
    // timed out in the first before look and after (so not tried again): slowness already there, never a block (the access rule
    // needs a before look that opened); said, and on the card when the release reaches it only through a shared file
    if (f.error && /Timeout/i.test(f.error) && b.error && /Timeout/i.test(b.error) && expected.has(f.url)) {
      const sharedOnly = Array.isArray(exp.drawn) && (exp.shared || []).length && expected.has(f.url) && !new Set(exp.drawn).has(f.url);
      notes.push(`${key(f)}: far timeout, noted: timed out in the before look and after: slowness already there, not this change's (the slow-pages ticket)`);
      if (sharedOnly) far.push({ who: f.who, page: f.url, size: f.size, outcome: 'before_too', ms: 0, via: exp.shared });
    }
    if (f.error && /Timeout/i.test(f.error) && !refused(b) && !expected.has(f.url)) { compared--; notes.push(`${key(f)}: timed out after on a page this release doesn't reach (staging is slow there; the IT Manager's lane), not compared`); continue; }
    // reached only through a shared file the release changes (tv-ask.js, the shell): tried once more after the look (capture's
    // farTimeouts). Opened then: compared as usual, and noted; timed out again, it blocks below as any reached page does
    if (f.late) {
      const via = (exp.shared || []).join(', ') || 'a shared file';
      const secs = x => x?.error ? 'timed out' : (x && x.ms ? Math.round(x.ms / 1000) + ' s' : 'no time kept') + (x?.retried && !x?.late ? ' after a retry' : '');
      const w2 = f.size === 'phone' ? '1280' : '390'; const other = A.get(f.who + '|' + f.url + '|' + (f.size === 'phone' ? 'desktop' : 'phone'));
      const also = other ? (other.error ? `; at ${w2} it timed out too` : `; at ${w2} it opened in the same run (${secs(other)})`) : '';
      const slowBefore = x => x && ((x.error && /Timeout/i.test(x.error)) || x.retried);
      const was = secs(b) + (bb && bb !== b ? ', then ' + secs(bb) : '');
      far.push({ who: f.who, page: f.url, size: f.size, outcome: !f.error ? 'opened' : (slowBefore(b) || slowBefore(bb) ? 'before_too' : 'blocks'), ms: f.ms || 0, via: exp.shared || [] });
      if (!f.error) notes.push(`${key(f)}: far timeout, noted: timed out, then opened in ${secs(f)} when tried again after the look with ${LATE_MS / 1000} s (the before looks: ${was}${also}; reached only through ${via}; staging is slow there, the IT Manager's lane)`);
      else if (slowBefore(b) || slowBefore(bb)) { compared--; notes.push(`${key(f)}: far timeout, noted: didn't open after, even with ${LATE_MS / 1000} s, but the before looks were slow there too (${was}${also}): slowness already there, not this change's (reached only through ${via}; the slow-pages ticket)`); continue; }
      else notes.push(`${key(f)}: didn't open after, even with ${LATE_MS / 1000} s when tried again after the look, though it opened before (${was}${also}; reached through ${via}, which this release changes): it blocks`);
    }
    const moving = (get) => JSON.stringify(get(b)) !== JSON.stringify(get(bb || b));
    // 1. access or privacy (0066 included)
    const shut = x => x && (x.status === 401 || x.status === 403 || x.ba?.landed_signin);   // refused this account (not merely missing)
    if (shut(b) && !refused(f)) block('access', f, f.url.startsWith('/admin') && f.who !== 'executive' && f.who !== 'ops-staff' ? 'A member or visitor opens an admin page' : 'A page that refused this account opens now', b.status + (b.ba?.landed_signin ? ' (sign-in)' : ''), f.status);
    if (!refused(b) && refused(f) && !(b.status >= 400)) block('access', f, f.ba?.landed_signin ? 'Sent to the sign-in page, where it opened before' : 'Doesn\'t open now: ' + (f.error || f.status), b.status, f.error || f.status);
    else if (!errs(b) && errs(f)) block('access', f, 'An error on a view that worked: ' + ((f.console || [])[0] || (f.requests || [])[0] || f.status), '', (f.console || [])[0] || (f.requests || [])[0] || f.status);
    const own = f.who.toLowerCase(); const was = new Set(b.ba?.canaries || []);
    for (const c of f.ba?.canaries || []) if (c !== own && !was.has(c)) block('access', f, `Another account's email shows (check+${c}@)`, '', 'check+' + c + '@');
    if (held.size) { const was0 = heldIn(b.ba?.words ?? b.ba?.text ?? '', held, heldKey); for (const h of heldIn(f.ba?.words ?? f.ba?.text ?? '', held, heldKey)) if (!was0.has(h)) block('access', f, '0066: a name the site holds back shows (held ' + h.slice(0, 8) + ')', '', 'held ' + h.slice(0, 8)); }
    if (refused(f)) continue;
    if (partial) {
      const n = 1 + ((exp.skips || {})[key(f)] || 0);
      notes.push(`${key(f)}: its before looks timed out (part of the page drawn) and it opened in full after, on a page this release doesn't reach: access, 0066 and errors read; drift and layout not compared (${n} in a row${n >= 3 ? ': a ticket for the IT Manager\'s (11) lane' : ''}; staging is slow there)`);
      if (n >= 3) tickets.push({ lane: 'IT Manager (11)', what: `${f.url} (${f.who}, ${f.size}): its before look timed out ${n} releases in a row, so drift and layout there go unchecked`, why: 'the CTO\'s Tier 4, 2026-11-27: no page unchecked forever', release: String(release) });
      for (const k of ['cards', 'rows', 'pins']) if ((f.ba?.[k] || 0) < (b.ba?.[k] || 0)) block('data', f, `Fewer ${k}: ${b.ba[k]} before, ${f.ba[k]} after`, b.ba[k], f.ba[k]);
      continue;
    }
    // 2. layout or phone
    for (const [k, on] of [['scroll', x => x.scroll && x.size === 'phone'], ['edges', x => x.edges > 0], ['dialogs', x => (x.dialogs || []).length], ['names', x => x.names > 0], ['alt', x => x.alt > 0]])
      if (on(f) && !on(b)) {
        // wider than the screen only because of a record's own content (a long address), on a page the change doesn't name:
        // that record's data, noted for Site health, never this change's (#308's look, /admin/crawler-review.php at 390)
        if ((k === 'scroll' || k === 'edges') && f.ba?.wideRec && !expected.has(f.url)) notes.push(`${key(f)}: "${STANDARDS[k]}" missed after, from a record's own content (its data); not this change's`);
        else block('layout', f, STANDARDS[k], 'met', 'missed');
      }
    const more = (k, what) => { const n0 = (b.ba?.[k] || []).length, n1 = (f.ba?.[k] || []).length; if (n1 > n0 && !moving(x => (x.ba?.[k] || []).length)) block('layout', f, what + ': ' + (f.ba[k] || []).slice(0, 2).join('; '), n0, n1); };
    more('overlap', 'Controls drawn over each other'); more('clipped', 'Text cut off'); more('offscreen', 'A control off the screen');
    for (const pmy of b.ba?.primary || []) if (!(f.ba?.primary || []).includes(pmy)) block('layout', f, 'The page\'s main action is gone: ' + pmy, pmy, '');
    if ((b.ba?.nav || 0) > 0 && (f.ba?.nav || 0) === 0) block('layout', f, 'The header\'s navigation is gone', b.ba.nav, 0);
    const bc = new Map((b.ba?.controls || []).map(c => [c.n + '→' + c.h, c])); const fc = new Map((f.ba?.controls || []).map(c => [c.n + '→' + c.h, c]));
    for (const [k2, c] of bc) if (!c.d && fc.has(k2) && fc.get(k2).d && !(bb && new Map((bb.ba?.controls || []).map(x => [x.n + '→' + x.h, x])).get(k2)?.d)) block('layout', f, 'A control that worked is greyed now: ' + c.n, 'enabled', 'disabled');
    // 3. missing data
    for (const k of ['cards', 'rows', 'pins']) if ((f.ba?.[k] || 0) < (b.ba?.[k] || 0) && !moving(x => x.ba?.[k])) block('data', f, `Fewer ${k}: ${b.ba[k]} before, ${f.ba[k]} after`, b.ba[k], f.ba[k]);
    const t0 = b.ba?.totals || [], t1 = f.ba?.totals || [];
    if (t0.length && Math.max(...t1, 0) < Math.max(...t0) && !moving(x => x.ba?.totals)) block('data', f, `A list holds fewer: ${Math.max(...t0)} before, ${Math.max(...t1, 0)} after`, Math.max(...t0), Math.max(...t1, 0));
    // 4. drift: a page that changed (beyond what moves on its own) that the change doesn't name
    // lines compared with their numbers masked (a count is the data rule's), less what differed between the two before looks
    const adm = /^\/admin(\/|\.|$)/.test(f.url);   // an admin page: structure only; a public page: its prose too
    const N = x => new Set([...(x?.ba?.lines || []), ...(adm ? [] : x?.ba?.prose || []), ...(stateDrawn ? x?.ba?.state || [] : [])].map(l => l.replace(/\d+/g, '#')));
    const L0 = N(b), L2 = N(bb || b), L1 = N(f);
    const movingL = new Set([...L0].filter(l => !L2.has(l)).concat([...L2].filter(l => !L0.has(l))));
    // a section that holds records, there in one look and not the other, came or went with its data: its heading isn't drift,
    // and its controls go with it (#308's look); a heading of the page's own, or a section with no records, still is
    const D = x => new Set((x?.ba?.dlines || []).map(l => l.replace(/\d+/g, '#')));
    const D0 = D(b), D1 = D(f);
    const goneL = [...L0].filter(l => !L1.has(l) && !movingL.has(l) && !D0.has(l)), newL = [...L1].filter(l => !L0.has(l) && !movingL.has(l) && !D1.has(l));
    const toggled = new Set([...(b.ba?.dlines || [])].filter(l => !(f.ba?.lines || []).includes(l)).concat([...(f.ba?.dlines || [])].filter(l => !(b.ba?.lines || []).includes(l))));
    const ctlChanged = ctlSig(b, stateDrawn, toggled) !== ctlSig(f, stateDrawn, toggled) && ctlSig(b, stateDrawn, toggled) === ctlSig(bb || b, stateDrawn, toggled);
    // an empty state giving way to records (a queue's "All caught up." gone, its first item there: #307's look, /admin/helper.php)
    // is the queue filling, not a part of the page gone: a region that held no records, with the page holding more records after
    const filled = r => !(b.ba.regions[r]?.n) && (f.ba?.recs ?? 0) > (b.ba?.recs ?? 0);
    const regionsGone = Object.keys(b.ba?.regions || {}).filter(r => (b.ba.regions[r]?.t || '') && !(f.ba?.regions || {})[r] && (bb?.ba?.regions || b.ba.regions)[r] && !filled(r));
    if (f.relook && !regionsGone.length) notes.push(`${key(f)}: flaky on staging, noted: ${f.relook.slice(0, 2).join(', ')} was gone after, then back when looked at again (in both before looks; a page this release doesn't reach)`);
    else if (f.relook) notes.push(`${key(f)}: looked at again: ${regionsGone.slice(0, 2).join(', ')} still gone${f.relook_error ? ' (the re-look didn\'t open: ' + f.relook_error + ')' : ''}: it blocks`);
    if (!expected.has(f.url)) {
      // a part of the page gone, on a page the change doesn't name, is missing data; any other change there is drift
      if (regionsGone.length) block('data', f, 'A part of the page is gone: ' + regionsGone.slice(0, 2).join(', '), regionsGone[0], '');
      else if (goneL.length || newL.length || ctlChanged) {
        // the controls by name (#320's look said only "(its links and buttons)", which no one could act on)
        const cs = x => new Set(ctlSig(x, stateDrawn, toggled).split('\n').filter(Boolean));
        const c0 = cs(b), c1 = cs(f); const cGone = [...c0].filter(c => !c1.has(c)), cNew = [...c1].filter(c => !c0.has(c));
        block('drift', f, 'This page changed, and the change doesn\'t name it', goneL[0] || (ctlChanged && cGone.length ? 'control: ' + cGone.slice(0, 3).join(' | ') : ''), newL[0] || (ctlChanged ? (cNew.length ? 'control: ' + cNew.slice(0, 3).join(' | ') : '(its links and buttons)') : ''));
      }
    }
    for (const [k] of Object.entries({ scroll: 1, edges: 1, names: 1 })) if (b[k] && f[k]) notes.push(`${key(f)}: already missed "${STANDARDS[k]}" before (Site health's, not this change's)`);
  }
  // one block per rule, page and size is enough to read (the rest are counted)
  const uniq = []; const seen = new Set(); for (const x of blocks) { const k = [x.rule, x.who, x.page, x.size, x.what].join('|'); if (!seen.has(k)) { seen.add(k); uniq.push(x); } }
  // an account that alone checks a kind of page (the Member advocate every page as a member, the Executive and the Staff login
  // the admin pages as staff) and couldn't sign in, before or after, while the change names a page of that kind: that view
  // of the change wasn't checked, so the look is incomplete (a person looks), never pass (#304, 2026-11-26: both staff logins
  // failed, the change was admin only, and it read pass)
  const SOLE = { member: ['member', 'public', 'admin'], executive: ['admin'], 'ops-staff': ['admin'] };
  const failed = new Set([...Object.entries(b1.signins || {}), ...Object.entries(a.signins || {})].filter(([, s]) => s !== 'ok').map(([w]) => w));
  const unchecked = [...failed].filter(w => SOLE[w] && [...expected].some(u => SOLE[w].includes(kindOf(u))));
  if (unchecked.length) notes.unshift('Not checked as ' + unchecked.join(' and ') + ' (couldn\'t sign in), though the change names its pages: a person looks before it goes on');
  const incomplete = !a.results.length || !compared || Object.values(a.signins || {}).every(s => s !== 'ok') || heldUnread || unchecked.length > 0;
  if (!compared && a.results.length) notes.unshift('No view had a usable before look (staging was closed or erroring when it was taken): a person looks before it goes on');
  return { release: String(release), at: new Date().toISOString(), verdict: uniq.length ? 'block' : (incomplete ? 'incomplete' : 'pass'),
    first: uniq[0] ? `${uniq[0].rule_name}: ${uniq[0].what} (${uniq[0].who}, ${uniq[0].page}, ${uniq[0].size})` : '',
    blocks: uniq.slice(0, 50), blocks_total: uniq.length, notes: notes.slice(0, 30), far: far.slice(0, 10), tickets: tickets.slice(0, 10), views: a.results.length, seconds: (b1.seconds || 0) + (b2.seconds || 0) + (a.seconds || 0) };
}

// ---- command line ----------------------------------------------------------------------------------------------------
if (import.meta.url === `file://${process.argv[1]}`) {
  const arg = (k, d = '') => { const i = process.argv.indexOf('--' + k); return i > 0 ? process.argv[i + 1] : d; };
  const mode = process.argv[2];
  if (mode === 'held') {
    // staging's locked read (BA_SITE for a test site): the checks' token, and staging's viewing password when it has one
    const site = (process.env.BA_SITE || STAGING?.site || '').replace(/\/$/, ''); const tok = STAGING?.token || CHECK_TOKEN; let out;
    try {
      if (!site || !tok) throw new Error('no staging site or checks\' token in CHECK_ACCOUNTS');
      const h = { 'X-Check-Token': tok, 'User-Agent': 'Traversence site checks (support@traversence.com)' };
      if (!process.env.BA_SITE && STAGING?.user) h.Authorization = 'Basic ' + Buffer.from(STAGING.user + ':' + STAGING.password).toString('base64');
      const r = await fetch(site + '/api/held-hashes.php', { headers: h }); const j = await r.json().catch(() => ({}));
      if (!r.ok || !j.ok || !Array.isArray(j.hashes)) throw new Error('its answer: ' + r.status + ' ' + (j.error || j.message || ''));
      out = { hashes: j.hashes.filter(x => /^[0-9a-f]{24}$/.test(x)), count: j.count, skipped_one_word: j.skipped_one_word, at: j.at };
    } catch (x) { out = { error: String(x.message || x).slice(0, 200) }; }
    writeFileSync(arg('out', 'ba-held.json'), JSON.stringify(out) + '\n');
    console.log(out.error ? 'held names: unread (' + out.error + ')' : `held names: ${out.hashes.length} hashes`);
  } else if (mode === 'capture') await capture(JSON.parse(readFileSync(arg('pages'), 'utf8')), arg('out', 'ba-capture.json'), arg('shots'), slowViews(arg('slow')), arg('expected') ? JSON.parse(readFileSync(arg('expected'), 'utf8')) : {}, arg('before') ? JSON.parse(readFileSync(arg('before'), 'utf8')) : null, arg('before2') ? JSON.parse(readFileSync(arg('before2'), 'utf8')) : null);
  else if (mode === 'compare') {
    const rd = f => JSON.parse(readFileSync(f, 'utf8'));
    const exp = arg('expected') ? rd(arg('expected')) : {};
    if (arg('held')) { try { exp.held = rd(arg('held')); } catch (x) { exp.held = { error: 'held.json unreadable' }; } }
    exp.skips = priorSkips(arg('verdicts', 'site-audit/results/beforeafter'), arg('release'));   // the CTO's Tier 4: no page unchecked forever
    const v = compare(rd(arg('before')), rd(arg('before2') || arg('before')), rd(arg('after')), exp, arg('release'));
    writeFileSync(arg('out', 'ba-verdict.json'), JSON.stringify(v, null, 1) + '\n');
    console.log(`${v.verdict}${v.first ? ': ' + v.first : ''} (${v.views} views, ${v.blocks_total} block${v.blocks_total === 1 ? '' : 's'})`);
    process.exit(v.verdict === 'pass' ? 0 : 1);
  } else { console.log('usage: beforeafter.mjs capture --pages p.json --out c.json [--slow a] [--expected e] [--before a --before2 b] | compare --before a --before2 b --after c [--expected e] [--release N] [--out v]'); process.exit(2); }
}
