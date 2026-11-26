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
import { SIZES, STANDARDS, pageCheck } from './page-check.mjs';   // the page check itself, shared with beforeafter.mjs (Piece 3, P3-1)

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
const check = (ctx, who, url, size, shots) => pageCheck(ctx, who, url, size, { site: SITE, shots, must: u => CORE.has(u) || u.includes('?') || u.startsWith('/admin/') });

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
