// tools/page-audit/rendered.mjs — the rendered checks (decisions/0145 §1, Phase 2): each page in a real browser
// (Playwright, Chromium), at phone (390px) and desktop size, signed out and as the test accounts, held to the build
// standards. Run by .github/workflows/site-checks.yml.
//
//   SITE=https://traversence.com CHECK_ACCOUNTS='{…}' MANIFESTS=mirror/sync-app/admin/manifests.php \
//   PAGE_TREE=site-audit/page-tree.md FULL=0 node tools/page-audit/rendered.mjs
//
// CHECK_ACCOUNTS is the block System Configuration → Test accounts shows once (never in git). Without it, only the
// signed-out checks run. Writes site-audit/results/rendered.json and site-audit/rendered.md; screenshots go to
// shots/ (kept with the run as an artifact, never committed). Nothing here changes anything on the site: it only reads.
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { SITE, ACC, memberIn, staffIn, checkContext } from './check-kit.mjs';   // the site, the accounts and signing in, shared with paths.mjs

const FULL = process.env.FULL === '1';
const SIZES = { phone: { width: 390, height: 844 }, desktop: { width: 1280, height: 900 } };

// ---- which pages ----------------------------------------------------------------------------------------------------
// Public pages: the page tree's front-end pages that open without an id, plus the guide paths found on Discovery
// (each system guide, its first suggested journey, its first named place). Member pages: /user/ and the dashboard.
// Admin pages: every module section in the manifests.
function treePages() {
  const out = [];
  try {
    for (const line of readFileSync(process.env.PAGE_TREE || 'site-audit/page-tree.md', 'utf8').split('\n')) {
      const m = line.match(/^\| `([^`]+)` \| `website\/[^`]+` \| (yes|redirect|—|\*\*no\*\*)/);
      if (m) out.push(m[1]);
    }
  } catch {}
  return out;
}
const tree = treePages();
const NEEDS_ID = /\/(view|post|edit|order|item|manifest|suggestion|review-item|client-order|content-template|guide-edit|landmark|recreation|group|listing|wo|q|q-file|promo|takedown|verify|reset|two-step|email-revert|verify-email|setup|logout|login)\.php$|\/(api|crawl|auth)\//;
const CORE = new Set(['/', '/discovery/', '/directory/', '/faq.php', '/experiences/', '/community/', '/market/', '/plans.php', '/terms.php', '/privacy.php', '/dashboard.php', '/admin/']);
const PUBLIC = [...new Set([...[...CORE].filter(u => u !== '/dashboard.php' && u !== '/admin/'),
  ...tree.filter(p => !p.startsWith('/admin') && !p.startsWith('/user') && !p.startsWith('/business') && !p.startsWith('/dashboard') && !NEEDS_ID.test(p))])];
const MEMBER = [...new Set(['/dashboard.php', ...tree.filter(p => (p.startsWith('/user/') || p.startsWith('/business/')) && !NEEDS_ID.test(p))])];
function adminSections() {
  const out = ['/admin/'];
  try {
    const src = readFileSync(process.env.MANIFESTS || 'mirror/sync-app/admin/manifests.php', 'utf8');
    let mod = '';
    for (const line of src.split('\n')) {
      const m = line.match(/^\s{4}'([a-z_]+)' => \['sections' => \[/); if (m) { mod = m[1]; continue; }
      const s = line.match(/^\s{8}'([a-z_0-9]+)' => \$[WB]\(/); if (s && mod) out.push(`/admin/${mod}/${s[1]}`);
    }
  } catch {}
  return out;
}
const ADMIN = adminSections();

// ---- signing in: memberIn() and staffIn() are in check-kit.mjs -------------------------------------------------------

// ---- one page -------------------------------------------------------------------------------------------------------
const STANDARDS = {
  status: 'The page opens (no 4xx or 5xx)', console: 'No script errors', requests: 'Its own requests succeed', scroll: 'No sideways scroll at 390px (0058 §11)',
  edges: 'Cards 3px from the screen edge at 390px (0058 §11)', dialogs: 'No browser dialogs (alert, confirm, prompt)', alt: 'Images have alt text', names: 'Buttons and links have a name',
};
async function check(ctx, who, url, size, shots) {
  const p = await ctx.newPage(); await p.setViewportSize(SIZES[size]);
  // a page that needs an id (album.php with no album) rightly answers 4xx: only the core pages, the guide paths and admin sections must open
  const must = CORE.has(url) || url.includes('?') || url.startsWith('/admin/');
  const f = { who, url, size, must, status: 0, console: [], requests: [], dialogs: [], scroll: false, edges: 0, alt: 0, names: 0 };
  // a failed load is counted under requests; an error from another company's frame on the page (Cloudflare's Turnstile box,
  // a payment gateway's card fields) is theirs, not the page's, so only our own site's errors count
  const ours = (m) => { const u = (m.location && m.location().url) || ''; return !u || u.startsWith(SITE) || u.startsWith('about:') || u.startsWith('blob:' + SITE); };
  p.on('console', m => { if (m.type() === 'error' && ours(m) && !/^Failed to load resource|^%c/.test(m.text())) f.console.push(m.text().slice(0, 200)); });
  p.on('pageerror', e => f.console.push(String(e).slice(0, 200)));
  p.on('response', r => { const u = r.url(); if (u.startsWith(SITE) && r.status() >= 400 && r.request().resourceType() !== 'document' && !/\/api\/(count|out)\.php/.test(u)) f.requests.push(`${r.status()} ${u.replace(SITE, '')}`.slice(0, 160)); });
  p.on('dialog', d => { f.dialogs.push(`${d.type()}: ${d.message().slice(0, 120)}`); d.dismiss().catch(() => {}); });
  try {
    const r = await p.goto(SITE + url, { waitUntil: 'load', timeout: 45000 });
    f.status = r ? r.status() : 0; f.final = p.url().replace(SITE, '');
    await p.waitForTimeout(800);
    if (!/html/i.test((r && r.headers()['content-type']) || 'text/html')) { await p.close(); return f; }   // an XML or JSON file (the sitemap): no page to lay out
    Object.assign(f, await p.evaluate((phone) => {
      const vis = el => { if (el.checkVisibility && !el.checkVisibility({ contentVisibilityAuto: true, opacityProperty: true, visibilityProperty: true })) return false; const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && !el.closest('[hidden], details:not([open]) > :not(summary)'); };   // folded parts (closed answers, a folded menu) aren't on screen
      const out = { scroll: document.documentElement.scrollWidth > window.innerWidth + 1, edges: 0, alt: 0, names: 0, eg: {} };
      const eg = (k, el) => { (out.eg[k] = out.eg[k] || []).length < 3 && out.eg[k].push(el.outerHTML.replace(/\s+/g, ' ').slice(0, 320)); };   // a sample of each, so it can be found
      if (out.scroll) {   // what pokes out past the right edge: the deepest elements wider than the screen, so the fix can find them (P12)
        const wide = [...document.querySelectorAll('body *')].filter(el => { const r = el.getBoundingClientRect(); return r.right > window.innerWidth + 1 && r.width > 0 && getComputedStyle(el).position !== 'fixed'; })
          .filter((el, i, all) => !all.some(o => o !== el && el.contains(o)));
        for (const el of wide.slice(0, 3)) (out.eg.scroll = out.eg.scroll || []).push(`${Math.round(el.getBoundingClientRect().right)}px: ` + el.outerHTML.replace(/\s+/g, ' ').slice(0, 260));
      }
      if (phone) for (const el of document.querySelectorAll('.tv-bleed')) { if (!vis(el) || el.parentElement.closest('.tv-bleed')) continue;   // one inside another keeps its card's padding
        const r = el.getBoundingClientRect(); if (r.width > window.innerWidth * 0.8 && (r.left > 6 || window.innerWidth - r.right > 6)) { out.edges++; let a = el.parentElement, pad = ''; while (a && !pad) { const cs = getComputedStyle(a); if (parseFloat(cs.paddingLeft) > 6 || parseFloat(cs.paddingRight) > 6) pad = (a.id ? '#' + a.id : a.tagName.toLowerCase() + '.' + [...a.classList].join('.')) + ' pads ' + cs.paddingLeft + '/' + cs.paddingRight; a = a.parentElement; } const c = el.cloneNode(false); c.setAttribute('data-gap', Math.round(r.left) + 'px left, ' + Math.round(window.innerWidth - r.right) + 'px right' + (pad ? '; ' + pad : '')); eg('edges', c); } }
      for (const im of document.querySelectorAll('img')) if (vis(im) && !im.hasAttribute('alt')) out.alt++;
      for (const el of document.querySelectorAll('a[href], button')) if (vis(el) && !(el.innerText || el.textContent || '').trim() && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby') && !el.querySelector('svg title') && !el.getAttribute('title') && !el.querySelector('img[alt]:not([alt=""])')) { out.names++; eg('names', el); }
      return out;
    }, size === 'phone'));
    if (shots && size === 'phone') await p.screenshot({ path: `shots/${who}-${url.replace(/[^a-z0-9]+/gi, '_').slice(0, 80)}.png` }).catch(() => {});
  } catch (e) { f.error = String(e).slice(0, 200); }
  await p.close();
  return f;
}

// ---- run ------------------------------------------------------------------------------------------------------------
const browser = await chromium.launch();
mkdirSync('shots', { recursive: true });
const results = [];
async function as(who, signIn, pages, sizes, opts = {}) {
  const ctx = await checkContext(browser);   // never counted as a visit; carries the checks' token
  try { if (signIn) await signIn(ctx); } catch (e) { results.push({ who, url: '(sign-in)', size: '-', error: String(e).slice(0, 200) }); await ctx.close(); return; }
  for (const url of pages) for (const size of sizes) results.push(await check(ctx, who, url, size, opts.shots !== false));
  await ctx.close();
}
// guide paths: the system guides on Discovery, and from each the first journey and named place
async function guidePaths() {
  const ctx = await browser.newContext(); const p = await ctx.newPage(); const out = [];
  try {
    let found = [];
    for (const from of ['/discovery/', '/pages/sitemap.php', '/directory/']) {   // wherever the guides are linked (some draw their links after loading)
      await p.goto(SITE + from, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => {});
      found = found.concat(await p.$$eval('a[href*="/hub/?"]', as => as.map(a => a.getAttribute('href'))).catch(() => []));
    }
    const guides = [...new Set(found.map(h => h.replace(/^https?:\/\/[^/]+/, '')))].slice(0, FULL ? 40 : 8);
    for (const g of guides) {
      out.push(g);
      await p.goto(SITE + g, { waitUntil: 'load', timeout: 45000 }).catch(() => {});
      const j = await p.$eval('a[href*="/journeys/suggested.php"]', a => a.getAttribute('href')).catch(() => null); if (j) out.push(j);
      const l = await p.$eval('a.gl[data-tag]', a => a.getAttribute('href')).catch(() => null); if (l && l.startsWith('/')) out.push(l);
    }
  } catch {}
  await ctx.close();
  return [...new Set(out)];
}
const paths = await guidePaths();
await as('guest', null, [...PUBLIC, ...paths], ['phone', 'desktop']);
if (ACC) {
  for (const k of FULL ? Object.keys(ACC.members || {}) : ['member', 'owner']) { const a = ACC.members?.[k]; if (a) await as(k, ctx => memberIn(ctx, a), MEMBER, FULL ? ['phone', 'desktop'] : ['phone']); }
  for (const k of Object.keys(ACC.staff || {})) {
    const a = ACC.staff[k]; const all = k === 'executive' || FULL;
    await as('staff-' + k, ctx => staffIn(ctx, a), all ? ADMIN : ['/admin/'], k === 'executive' ? ['phone', 'desktop'] : ['phone'], { shots: k === 'executive' });
  }
}
await browser.close();

// ---- report ---------------------------------------------------------------------------------------------------------
const fails = f => {
  const why = [];
  if (f.error) why.push(['status', f.error]);
  if (f.status >= 500 || (f.status >= 400 && f.must)) why.push(['status', String(f.status)]);
  if (f.console?.length) why.push(['console', f.console[0]]);
  if (f.requests?.length) why.push(['requests', f.requests[0]]);
  if (f.scroll && f.size === 'phone') why.push(['scroll', '']);
  if (f.edges) why.push(['edges', f.edges + ' card(s)' + (f.eg?.edges ? ' e.g. ' + f.eg.edges[0] : '')]);
  if (f.dialogs?.length) why.push(['dialogs', f.dialogs[0]]);
  if (f.alt) why.push(['alt', f.alt + ' image(s)']);
  if (f.names) why.push(['names', f.names + ' control(s)' + (f.eg?.names ? ' e.g. ' + f.eg.names[0] : '')]);
  return why;
};
const rows = results.map(f => ({ ...f, why: fails(f) }));
const by = {}; for (const r of rows) for (const [k] of r.why) by[k] = (by[k] || 0) + 1;
mkdirSync('site-audit/results', { recursive: true });
const RUN_URL = process.env.GITHUB_RUN_ID ? `${process.env.GITHUB_SERVER_URL || 'https://github.com'}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}` : '';   // where the screenshots are (findings.py links them)
writeFileSync('site-audit/results/rendered.json', JSON.stringify({ at: new Date().toISOString(), site: SITE, run_url: RUN_URL, full: FULL, signed_in: !!ACC, pages: rows.length, by, results: rows }, null, 1));
const L = ['# Rendered checks', '', `Decision 0145, Phase 2. Generated by \`tools/page-audit/rendered.mjs\` on ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC against ${SITE}`
  + (ACC ? ', signed out and as the test accounts' : ', signed out only (CHECK_ACCOUNTS isn\'t set yet)') + `. ${rows.length} page views. Screenshots are kept with the run. Don't edit by hand.`, '',
  '| Standard | Pages that miss it |', '|---|---|', ...Object.entries(STANDARDS).map(([k, t]) => `| ${t} | ${by[k] || 0} |`), '', '## What to fix', '',
  '| Who | Page | Size | What |', '|---|---|---|---|'];
for (const r of rows.filter(r => r.why.length).slice(0, 300)) L.push(`| ${r.who} | \`${r.url}\` | ${r.size} | ${r.why.map(([k, d]) => STANDARDS[k] + (d ? ': ' + d.replace(/\|/g, '/') : '')).join('; ')} |`);
writeFileSync('site-audit/rendered.md', L.join('\n') + '\n');
console.log(`Checked ${rows.length} page views; ${rows.filter(r => r.why.length).length} miss a standard.`);
