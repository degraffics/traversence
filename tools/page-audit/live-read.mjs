// tools/page-audit/live-read.mjs — a build session's read of live data (Jason, 2026-11-23: "this is why we have those
// accounts, we use them for building and audits, so we should not be reinventing things when we already have the tools to
// do our job"). It signs in as the test Executive from CHECK_ACCOUNTS (check-kit.mjs, as the page checks do), opens only
// the named admin pages, presses nothing, and saves what each page shows (its text, its links' addresses, a picture at
// 1280 and 390) to OUT. The workflow keeps OUT with the run for one day; it is never committed.
// Read-only by construction: after the sign-in, every request that isn't a GET or HEAD is refused in the browser.
// TO_LOG=1 also prints each page's text into the run's log, for a session whose network can't fetch the artifact; that
// session deletes the run's log once it has read it (Actions → the run → Delete logs; the MCP's delete_workflow_run_logs).
// READ (one per run): board (the board, W1's page, the alarms); orders (Back-Office → Orders) or order (one order, ORDER_ID); meta: one table's columns and their
// types from the admin's data API, TABLE its name only (letters, digits and _; the session manager, 2026-11-24, for M2's
// order_ledger.kind).
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { SITE, ACC, staffIn, checkContext } from './check-kit.mjs';

const READ = process.env.READ || 'orders';
const ID = String(process.env.ORDER_ID || '').trim();
const TABLE = String(process.env.TABLE || '').trim();
const OUT = process.env.OUT || 'live-read';
const PAGES = { orders: () => ['/admin/backoffice/orders'], order: () => (/^\d{1,9}$/.test(ID) ? [`/admin/backoffice/orders/${ID}`] : null),
  // IR-0001 (the session manager, 2026-11-24: confirm on live data before building loads-reply.zip): the ledger's calls to the
  // load endpoint (their time and answer), the crawler run's page, and the alarms (W1's trip after Check and start again)
  // W0's "received 250, accounted for 0" alarm (the session manager, 2026-11-24): the ledger's index calls and W0's own page (its receipts)
  w0: () => ['/admin/config/ledger?q=crawl/index', '/admin/config/control/wf-W0'],
  ir0001: () => ['/admin/config/ledger?q=crawl/loads', '/admin/insights/crawler?run=' + (/^\d{1,9}$/.test(ID) ? ID : '1290'), '/admin/config/alarms'],
  // the board's phone header (the Control Board session, 2026-11-24): the board, W1's own page (its trips after the presses)
  // and the alarm list, at 1280 and 390, for the header's counts and the side panel's look
  // the jobs (the Control Board session, 2026-11-24, queue item 7): Scheduled tasks, each job's last run and next due
  jobs: () => ['/admin/config/scheduler'],
  board: () => ['/admin/config/control', '/admin/config/control/wf-W1', '/admin/config/alarms'],
  meta: () => (/^[a-z0-9_]{1,64}$/.test(TABLE) ? [`/admin/api/data.php?action=meta&table=${TABLE}`] : null) };

const fail = (m) => { console.log(`::error::${m}`); process.exit(1); };
if (!PAGES[READ]) fail(`READ must be one of: ${Object.keys(PAGES).join(', ')}.`);
const paths = PAGES[READ]();
if (!paths) fail(READ === 'meta' ? 'TABLE must be a table\'s name (letters, digits and _).' : 'ORDER_ID must be the order\'s number (digits only).');
const exec = ACC?.staff?.executive;
if (!exec) fail('CHECK_ACCOUNTS has no staff.executive login (System Configuration → Test accounts).');
mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();
const summary = [];
try {
  // one sign-in for both sizes: a two-step code works once, so a second sign-in in the same 30 s is refused
  const ctx = await checkContext(browser, { context: { viewport: { width: 1280, height: 900 } } });
  await staffIn(ctx, exec);
  let refused = 0;
  await ctx.route('**/*', (r) => (['GET', 'HEAD'].includes(r.request().method()) ? r.continue() : (refused++, r.abort())));   // nothing but reads from here
  const p = await ctx.newPage();
  for (const w of [1280, 390]) {
    await p.setViewportSize({ width: w, height: w > 500 ? 900 : 844 });
    for (const path of paths) {
      const res = await p.goto(SITE + path, { waitUntil: 'load', timeout: 60000 }).catch((e) => ({ status: () => 0, err: String(e) }));
      await p.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});   // the admin's tables fill in after load
      await p.waitForTimeout(800);
      const name = `${READ}${READ === 'meta' ? '-' + TABLE : (ID ? '-' + ID : '')}-${w}`;
      const read = await p.evaluate(() => ({
        url: location.pathname + location.search,
        title: document.title,
        text: document.body.innerText,
        links: [...document.querySelectorAll('a[href]')].map((a) => [a.textContent.trim().replace(/\s+/g, ' ').slice(0, 80), a.getAttribute('href')]).filter((x) => x[1].startsWith('/admin/')),
        wide: document.documentElement.scrollWidth <= window.innerWidth + 1,
      }));
      writeFileSync(`${OUT}/${name}.json`, JSON.stringify({ path, width: w, status: res.status(), refused_writes: refused, ...read }, null, 1));
      writeFileSync(`${OUT}/${name}.txt`, read.text);
      await p.screenshot({ path: `${OUT}/${name}.png`, fullPage: true });
      const line = `${w}px ${path}: HTTP ${res.status()}, landed on ${read.url}, ${read.text.length} characters, ${read.links.length} admin links, no sideways scroll: ${read.wide}, writes refused: ${refused}`;
      summary.push(line); console.log(line);
      if (process.env.TO_LOG === '1') { console.log(`::group::${name} (the page's text; the session deletes this run's log once read)`); console.log(read.text); console.log('::endgroup::'); }
      if (/\/admin\/login\.php/.test(read.url)) fail(`${path} sent the test Executive back to sign in.`);
    }
  }
  await ctx.close();
} finally { await browser.close(); }
writeFileSync(`${OUT}/summary.txt`, summary.join('\n') + '\n');
