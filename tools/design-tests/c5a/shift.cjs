// C.5a (0145 V06, the launch plan's C.5): layout shift measured as the live site loads it. The Play CDN builds Tailwind's
// styles after the page has painted, so on the bench the stand-in (TW_INJECT) runs late here too: DELAY ms after
// DOMContentLoaded. Each page's cumulative layout shift (CLS, the browser's own layout-shift entries, recent input left
// out) at 390 and 1280, the worst of RUNS loads; and the elements that moved most, for the record. (Headless Chrome's
// phone emulation holds the first paint until the styles are in, hiding the shift, so 390 is a narrow window, not isMobile.)
// DELAY=-1: the styles in before the first paint (as the built stylesheet will be), so only the page's own shifts count.
// node shift.cjs <out.json> [budget]   (HARNESS, TW_INJECT, DELAY=400, RUNS=3, PAGES="/login.php,/directory/?town=Show%20Low")
const { chromium } = require('playwright'); const fs = require('fs');
const TW = fs.readFileSync(process.env.TW_INJECT, 'utf8'); const H = process.env.HARNESS || 'http://127.0.0.1:8790';
const DELAY = +(process.env.DELAY || 400), RUNS = +(process.env.RUNS || 3), budget = +(process.argv[3] || 0);
const PAGES = (process.env.PAGES || '/login.php,/directory/?town=Show%20Low').split(',');
const late = DELAY < 0 ? TW : 'document.addEventListener("DOMContentLoaded",function(){setTimeout(function(){' + TW + '},' + DELAY + ')});';
(async () => { const b = await chromium.launch(); const res = {}; let over = 0;
 for (const w of [390, 1280]) for (const pg of PAGES) {
  let worst = { cls: -1 };
  for (let i = 0; i < RUNS; i++) {
   const ua = w < 800 ? 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36' : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
   const c = await b.newContext({ viewport: { width: w, height: 844 }, hasTouch: w < 800, userAgent: ua });
   await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: late }));
   await c.addInitScript(() => { window.__ls = []; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__ls.push({ v: e.value, t: Math.round(e.startTime), src: (e.sources || []).map(s => { const n = s.node; return n && n.nodeType === 1 ? (n.id ? '#' + n.id : n.tagName.toLowerCase() + (n.className && typeof n.className === 'string' ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : '')) : (n ? n.nodeName : '?'); }) }); }).observe({ type: 'layout-shift', buffered: true }); });
   const p = await c.newPage(); await p.goto(H + pg, { waitUntil: 'load' }); await p.waitForTimeout(DELAY + 4500);
   const ls = await p.evaluate(() => window.__ls); const cls = +ls.reduce((a, e) => a + e.v, 0).toFixed(4);
   if (cls > worst.cls) worst = { cls, shifts: ls.sort((a, b) => b.v - a.v).slice(0, 4).map(e => ({ v: +e.v.toFixed(4), t: e.t, src: e.src.slice(0, 4) })) };
   await c.close();
  }
  res[w + ' ' + pg] = worst; if (budget && worst.cls > budget) over++;
  console.log(w + '\t' + worst.cls + '\t' + pg + '\t' + JSON.stringify(worst.shifts.slice(0, 2)));
 }
 if (process.argv[2]) fs.writeFileSync(process.argv[2], JSON.stringify(res, null, 1));
 await b.close(); if (budget) { console.log(over ? over + ' over ' + budget : 'all within ' + budget); process.exit(over ? 1 : 0); } })();
