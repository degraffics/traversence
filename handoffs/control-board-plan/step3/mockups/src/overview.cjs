// Builds step3/control-board-overview.pdf from the mockups, at 1280 (one sample page at 390). Static; not site code.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const step3 = '/home/user/traversence/handoffs/control-board-plan/step3/';
const dir = step3 + 'mockups/';
const tmp = process.argv[2] || '/tmp/overview';
const GH = 'https://github.com/degraffics/traversence/blob/main/handoffs/control-board-plan/step3/';
fs.mkdirSync(tmp, { recursive: true });

// [section, title strip, file, card aria-label (null: the whole page), width]
const S = [];
const add = (sec, title, file, card, w = 1280) => S.push({ sec, title, file, card, w });
add('The board', 'The board\'s first view: healthy, held, needs me', 'railway-a-board.html', null);
const hosts = [['Bluehost', 'bluehost-b-working.html'], ['Railway', 'railway-b-working.html'], ['GitHub', 'github-b-working.html'], ['Fly.io', 'flyio-a-working.html']];
for (const [n, f] of hosts) add('Hosts', 'Host · ' + n + ' · Working', f, n === 'Fly.io' ? 'Fly.io' : n);
const services = [['Anthropic (Claude)', 'anthropic-a-working.html'], ['Google Cloud', 'google-a-working.html'], ['Google AI Studio (Gemini)', 'google-a-working.html'],
  ['QuickBooks (Intuit)', 'quickbooks-a-working.html'], ['The Communications Dispatcher', 'dispatcher-a-working.html'], ['Amazon Web Services', 'aws-a-working.html'],
  ['The video worker', 'video-a-working.html'], ['Cloudflare', 'cloudflare-a-working.html']];
for (const [n, f] of services) add('Services', 'Service · ' + n + ' · Working', f, n);
const sources = [['Google Places', 'google-a-working.html'], ['Tavily', 'tavily-a-working.html'], ['Brave Search', 'brave-a-working.html'], ['The batched sources', 'sources-a-list.html']];
for (const [n, f] of sources) add('Sources', 'Source · ' + n + (n === 'The batched sources' ? ': Overture, Foursquare, Geofabrik, NPI, the IRS' : ' · Working'), f, n);
const fails = [
  ['A refused key', 'tavily-c-key-refused.html', 'Tavily'],
  ['A quota or cap: at the monthly cap', 'anthropic-c-at-cap.html', 'Anthropic (Claude)'],
  ['A rate wait: S3 asked us to slow down', 'aws-c-s3-slowdown.html', 'Amazon Web Services'],
  ['A server error, the system trying', 'tavily-d-server.html', 'Tavily'],
  ['A host down: Bluehost\'s pages', 'bluehost-c1-pages-down.html', 'Bluehost'],
  ['A correlated incident: IR-0001', 'railway-e-ir-0001.html', null],
  ['A privacy guard Notice: Gemini', 'google-d-privacy-guard.html', 'Google AI Studio (Gemini)'],
  ['A file that changed shape: the IRS', 'sources-f-irs.html', 'IRS (exempt organizations)'],
  ['Both sources down: Tavily and Brave', 'brave-d-both-down.html', 'Brave Search'],
  ['A message that reached no one', 'dispatcher-d-nobody.html', 'The Communications Dispatcher'],
];
for (const [t, f, c] of fails) add('Failures, one per kind', 'Failure · ' + t, f, c);
add('The 2 AM walk-through', 'The 2 AM walk-through: the crawler down, on every surface', 'railway-d-2am.html', null);
add('At 390', 'A sample at 390: Bluehost\'s pages down', 'bluehost-c1-pages-down.html', 'Bluehost', 390);

const PAGE_W = 1280, PAGE_H = 1810, STRIP = 70, MAXH = PAGE_H - STRIP - 40, MINSCALE = 0.62;

(async () => {
  const b = await chromium.launch();
  const pages = [];
  for (let i = 0; i < S.length; i++) {
    const s = S[i];
    const ctx = await b.newContext({ viewport: { width: s.w, height: 900 }, deviceScaleFactor: 1.5 });
    const p = await ctx.newPage();
    await p.goto('file://' + dir + s.file);
    const shot = path.join(tmp, `s${i}.jpg`);
    if (s.card) {
      const el = p.locator(`article.card[aria-label="${s.card}"]`).first();
      await el.screenshot({ path: shot, type: 'jpeg', quality: 82 });
    } else {
      await p.screenshot({ path: shot, fullPage: true, type: 'jpeg', quality: 82 });
    }
    const dims = await p.evaluate(async (src) => { const im = new Image(); im.src = src; await im.decode(); return [im.naturalWidth / 1.5, im.naturalHeight / 1.5]; },
      'data:image/jpeg;base64,' + fs.readFileSync(shot).toString('base64'));
    await ctx.close();
    const [w, h] = dims;
    const fitW = s.w === 390 ? 1 : Math.min(1, (PAGE_W - 60) / w);
    const fullH = h * fitW;
    // one page if it fits at a legible scale, else slices of MAXH each
    if (s.w === 390) { pages.push({ ...s, shot, cols: Math.ceil(h / MAXH), part: '', iw: w, ih: h }); }
    else if (fullH * Math.max(MINSCALE, MAXH / fullH) <= MAXH + 1 && fullH * MINSCALE <= MAXH) {
      pages.push({ ...s, shot, scale: fitW * Math.min(1, MAXH / fullH), part: '', iw: w, ih: h });
    } else {
      const sc = fitW * MINSCALE, sliceH = MAXH / sc; const n = Math.ceil(h / sliceH);
      for (let k = 0; k < n; k++) pages.push({ ...s, shot, scale: sc, top: k * sliceH, sliceH, part: ` (${k + 1} of ${n})`, iw: w, ih: h });
    }
    console.log(s.file, Math.round(w), Math.round(h));
  }
  const esc = t => t.replace(/&/g, '&amp;').replace(/</g, '&lt;');
  const img = f => 'data:image/jpeg;base64,' + fs.readFileSync(f).toString('base64');
  const imgs = {}; for (const pg of pages) imgs[pg.shot] = imgs[pg.shot] || img(pg.shot);
  const pdfs = [['Railway', 'railway-mockups.pdf'], ['GitHub', 'github-mockups.pdf'], ['Bluehost', 'bluehost-mockups.pdf']];
  const htmlLinks = fs.readdirSync(dir).filter(f => f.endsWith('.html') && f !== 'index.html' && !/^(railway|github|bluehost)-/.test(f)).sort();
  const groups = {}; for (const f of htmlLinks) { const k = f.split('-')[0]; (groups[k] = groups[k] || []).push(f); }
  let body = `<section class="pg cover"><div class="k">Traversence · System Control Board · step 3</div><h1>The control board, card by card</h1>
<p>An overview of step 3's card spec (<code>03-cards.md</code>) and its mockups: the board's first view, each module's working card in role order, one failure of each kind, the 2 AM walk-through, and a sample at 390.</p>
<p>Static design mockups, not site code. Values come from the live code and read-only reads; dotted copper marks a scenario value. Built 2026-11-21 at 1280.</p></section>`;
  // contents
  let toc = '', n = 3, last = '';
  for (const pg of pages) { if (pg.sec !== last) { toc += `<h3>${esc(pg.sec)}</h3>`; last = pg.sec; } toc += `<div class="tl"><span>${esc(pg.title + pg.part)}</span><span>${n}</span></div>`; n++; }
  body += `<section class="pg"><h2>Contents</h2><div class="cols"><div>${toc}</div><div><h3>Per-module PDFs</h3>${pdfs.map(([m, f]) => `<div class="tl"><a href="${GH}${f}">${m}: ${f}</a></div>`).join('')}
<h3>Other modules' mockups (HTML, in <code>mockups/</code>)</h3>${Object.entries(groups).map(([k, fs_]) => `<div class="hl"><b>${esc(k)}</b>: ${fs_.map(f => `<a href="${GH}mockups/${f}">${f.replace('.html', '')}</a>`).join(', ')}</div>`).join('')}
<p class="note">Links point at <code>main</code> on GitHub (degraffics/traversence, <code>handoffs/control-board-plan/step3/</code>).</p></div></div></section>`;
  n = 3;
  for (const pg of pages) {
    const sh = `<div class="strip"><span class="sec">${esc(pg.sec)}</span><b>${esc(pg.title + pg.part)}</b><span class="src">${esc(pg.file)}${pg.w === 390 ? ' · at 390' : ''}</span><span class="pn">${n}</span></div>`;
    let inner;
    if (pg.cols) inner = '<div style="display:flex;gap:24px">' + Array.from({ length: pg.cols }, (_, k) => `<div class="clip" style="width:${Math.round(pg.iw)}px;height:${Math.round(Math.min(MAXH, pg.ih - k * MAXH))}px;border:1px solid #d9c9b3"><img src="${imgs[pg.shot]}" style="width:${Math.round(pg.iw)}px;margin-top:-${k * MAXH}px"></div>`).join('') + '</div>';
    else if (pg.top !== undefined) inner = `<div class="clip" style="width:${Math.round(pg.iw * pg.scale)}px;height:${Math.round(Math.min(pg.sliceH, pg.ih - pg.top) * pg.scale)}px"><img src="${imgs[pg.shot]}" style="width:${Math.round(pg.iw * pg.scale)}px;margin-top:-${Math.round(pg.top * pg.scale)}px"></div>`;
    else inner = `<img class="one" src="${imgs[pg.shot]}" style="width:${Math.round(pg.iw * pg.scale)}px">`;
    body += `<section class="pg">${sh}<div class="fit">${inner}</div></section>`; n++;
  }
  const css = `@page{size:${PAGE_W}px ${PAGE_H}px;margin:0}*{box-sizing:border-box}body{margin:0;font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#2b2118;background:#fff}
.pg{width:${PAGE_W}px;height:${PAGE_H}px;padding:20px 30px;page-break-after:always;overflow:hidden;position:relative}.cover{background:#3d2b1f;color:#f6efe4;padding:220px 120px}.cover h1{font:700 54px/1.15 Georgia,serif;margin:.3em 0}.cover p{font-size:20px;max-width:900px}.cover .k{letter-spacing:.12em;text-transform:uppercase;font-size:15px;color:#e3c9a8}
h2{font:700 30px Georgia,serif;margin:10px 0 16px}h3{font-size:15px;text-transform:uppercase;letter-spacing:.06em;color:#92400E;margin:16px 0 6px}.cols{display:grid;grid-template-columns:1fr 1fr;gap:40px}.tl{display:flex;justify-content:space-between;gap:12px;border-bottom:1px dotted #d9c9b3;padding:3px 0}.hl{padding:3px 0;font-size:14px}a{color:#92400E}.note{font-size:13px;color:#6b5a48}
.strip{display:flex;align-items:center;gap:14px;height:${STRIP - 14}px;margin-bottom:14px;padding:0 16px;background:#3d2b1f;color:#f6efe4;border-radius:8px}.strip b{font-size:18px}.strip .sec{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#e3c9a8}.strip .src{margin-left:auto;font:12px ui-monospace,monospace;color:#e3c9a8}.strip .pn{font-size:13px}
.fit{display:flex;justify-content:center}.clip{overflow:hidden}img{display:block}`;
  fs.writeFileSync(path.join(tmp, 'overview.html'), `<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head><body>${body}</body></html>`);
  const ctx = await b.newContext(); const p = await ctx.newPage();
  await p.goto('file://' + path.join(tmp, 'overview.html'));
  await p.pdf({ path: step3 + 'control-board-overview.pdf', width: PAGE_W + 'px', height: PAGE_H + 'px', printBackground: true });
  console.log('pages', 2 + pages.length);
  await b.close();
})();
