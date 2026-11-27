// The More fold rendered by the real js/tv-ask.js (the CTO's Tier 4 on #322's look, 2026-11-27): a data-more row too narrow
// for its tabs folds the last ones under "More ▾"; the check's controls must read the same at 1280 (nothing folded) and at
// 390 (folded), and still differ when a tab is really gone or hidden inside the fold. Uses the live mirror's tv-ask.js:
// TV_ASK_JS, else mirror/sync-storage/js/tv-ask.js beside the repo (site-audit.yml's checkout), else ../traverse-3way-sync.
import { chromium } from 'playwright';
import { existsSync, readFileSync } from 'node:fs'; import path from 'node:path'; import { fileURLToPath } from 'node:url';
import { extra, ctlSig } from '../page-audit/beforeafter.mjs';
const here = path.dirname(fileURLToPath(import.meta.url)), root = path.join(here, '../..');
const js = [process.env.TV_ASK_JS, path.join(root, 'mirror/sync-storage/js/tv-ask.js'), path.join(root, '../traverse-3way-sync/sync-storage/js/tv-ask.js')].filter(Boolean).find(f => existsSync(f));
if (!js) { console.log('FAIL: no tv-ask.js (set TV_ASK_JS to the mirror\'s sync-storage/js/tv-ask.js)'); process.exit(1); }
const ask = readFileSync(js, 'utf8');
const TABS = ['Members', 'Staff &amp; roles', 'Staff logins', 'Waiting lists', 'Archive'];
const page = (v) => '<!doctype html><html><head><meta name=viewport content="width=device-width,initial-scale=1"><style>'
  + '.t{display:inline-flex;padding:.4rem .9rem;border:1px solid #CBB98D;border-radius:999px;white-space:nowrap;text-transform:uppercase}'
  + '.row{display:flex;gap:.35rem}</style></head><body><main><h1>People</h1><nav class="row" data-more role="tablist">'
  + TABS.map((t, i) => (v === 'gone' && i === 3) ? '' : '<a class="t' + (i === 0 ? ' on' : '') + '" href="/admin/people.php#t' + i + '">' + t
    + (v === 'hidden' && i === 3 ? ' <button hidden>Export</button>' : (i === 3 ? ' <button>Export</button>' : '')) + '</a>').join('')
  + '</nav></main><script>' + ask.replace(/<\/script/gi, '<\\/script') + '</script></body></html>';
let pass = 0, fail = 0; const ok = (c, m) => { if (c) pass++; else { fail++; console.log('FAIL: ' + m); } };
const b = await chromium.launch();
const look = async (v, w) => { const p = await b.newPage({ viewport: { width: w, height: 800 } });
  await p.route('**/*', r => r.fulfill({ contentType: 'text/html', body: page(v) })); await p.goto('https://fx.test/admin/people.php');
  await p.waitForTimeout(400); const { ba } = await extra(p, w < 768 ? 'phone' : 'desktop');
  const folded = await p.evaluate(() => ({ btn: !!document.querySelector('.tvm-btn'), inMenu: document.querySelectorAll('.tvm-menu > *').length }));
  await p.close(); return { f: { url: '/admin/people.php', ba }, folded }; };
const wide = await look('base', 1280), narrow = await look('base', 390), gone = await look('gone', 390), hid = await look('hidden', 390), hidW = await look('hidden', 1280);
ok(!wide.folded.btn, 'at 1280 nothing folds'); ok(narrow.folded.btn && narrow.folded.inMenu >= 1, 'at 390 the real tv-ask.js folds tabs under More (' + JSON.stringify(narrow.folded) + ')');
ok(ctlSig(wide.f) === ctlSig(narrow.f), 'folded or not, the same controls:\n' + ctlSig(wide.f) + '\n--\n' + ctlSig(narrow.f));
ok(!/More/.test(ctlSig(narrow.f)), 'the More button is no control');
ok(ctlSig(gone.f) !== ctlSig(narrow.f), 'a tab really gone still differs');
ok(ctlSig(hid.f) !== ctlSig(narrow.f), 'a button hidden inside a folded tab is not counted');
ok(ctlSig(hid.f) === ctlSig(hidW.f), 'the hidden button is uncounted at 1280 and 390 alike');
await b.close();
console.log(pass + ' of ' + (pass + fail) + ' checks pass'); process.exit(fail ? 1 : 0);
