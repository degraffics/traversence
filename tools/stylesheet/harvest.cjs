// tools/stylesheet/harvest.cjs: every class name the rendered pages use, checked against a built stylesheet (the built
// stylesheet's fix 4, proof that nothing is left to chance). Each page is opened at 390 and 1280 on the harness with the
// built CSS in place of the CDN; menus and panels that open are opened; each class on any element, after scripts ran, is
// looked up in the CSS. A Tailwind-looking name the CSS doesn't have is listed with its page: those are the misses.
// node harvest.cjs <tv.css> <pages.txt> <out.json>   (HARNESS; LOGIN_UID for pages behind a sign-in, "uid|/path" lines)
const { chromium } = require('playwright'); const fs = require('fs');
const css = fs.readFileSync(process.argv[2], 'utf8'); const pages = fs.readFileSync(process.argv[3], 'utf8').split('\n').map(s => s.trim()).filter(s => s && !s.startsWith('#'));
const H = process.env.HARNESS || 'http://127.0.0.1:8790';
const UTIL = /^(?:(?:sm|md|lg|xl|2xl|hover|focus|active|group-hover|disabled|first|last|odd|even|dark|peer-checked|focus-within|focus-visible|placeholder):)*-?(?:bg|text|border|ring|outline|divide|fill|stroke|from|via|to|shadow|w|h|min-w|max-w|min-h|max-h|size|p[xytrbl]?|m[xytrbl]?|space-[xy]|gap|inset|top|right|bottom|left|z|order|grid-cols|grid-rows|col-span|row-span|basis|flex|grow|shrink|rounded|opacity|font|leading|tracking|line-clamp|aspect|translate-[xy]|rotate|scale|duration|ease|justify|items|content|self|place|object|overflow|cursor|block|inline|hidden|grid|table|sticky|fixed|absolute|relative|static|truncate|underline|uppercase|lowercase|capitalize|italic|whitespace|break|sr-only|not-sr-only|container|transition|select|pointer-events|resize|list|align|antialiased|shrink|visible|invisible|isolate|appearance|outline|backdrop|blur|drop-shadow|decoration|underline-offset)(?:-|$)/;
const esc = c => c.replace(/([^a-zA-Z0-9_-])/g, '\\$1');
(async () => { const b = await chromium.launch(); const miss = {}; let seen = new Set();
 for (const w of [390, 1280]) {
  const c = await b.newContext({ viewport: { width: w, height: 900 }, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36' });
  await c.route(/cdn\.tailwindcss\.com/, r => r.fulfill({ contentType: 'application/javascript', body: '(function(){var s=document.createElement("style");s.textContent=' + JSON.stringify(css) + ';document.head.appendChild(s)})();' }));
  const p = await c.newPage(); let uid = '';
  for (const line of pages) {
   const [u, path] = line.includes('|') ? line.split('|') : ['', line];
   if (u !== uid) { await p.goto(H + (u ? '/__login?uid=' + u : '/__login?uid=0')).catch(() => {}); uid = u; }
   try { await p.goto(H + path, { waitUntil: 'load', timeout: 20000 }); await p.waitForTimeout(700);
     for (const sel of ['#icon-menu-btn', '[aria-haspopup]']) { const el = p.locator(sel).first(); if (await el.count() && await el.isVisible()) { await el.click({ timeout: 1500 }).catch(() => {}); await p.waitForTimeout(250); await p.keyboard.press('Escape'); } }
   } catch (e) { console.log('skip', path, String(e).slice(0, 80)); continue; }
   const cls = await p.evaluate(() => { const s = new Set(); document.querySelectorAll('[class]').forEach(e => String(e.getAttribute('class')).split(/\s+/).forEach(c => c && s.add(c))); return [...s]; });
   // the browser decides: a class is covered when some rule of the built CSS selects an element carrying it
   const known = await p.evaluate(({ cls, css }) => { const sh = new CSSStyleSheet(); sh.replaceSync(css); const sel = []; const walk = rs => { for (const r of rs) { if (r.selectorText) sel.push(r.selectorText); if (r.cssRules) walk(r.cssRules); } }; walk(sh.cssRules);
     const out = {}; for (const c of cls) { const e = document.createElement('tv-x'); e.className = c; out[c] = sel.some(s => { try { return s.split(/(?<!\\),/).some(x => { x = x.trim().split(/(?<!\\)[\s>~+]/)[0].replace(/(?<!\\)::?[a-z-]+(\([^)]*\))?/g, '').trim(); return x.includes('.') && e.matches(x); }); } catch (z) { return false; } }); } return out; }, { cls: cls.filter(k => UTIL.test(k)), css });
   for (const k of cls) { seen.add(k); if (!UTIL.test(k) || known[k]) continue; (miss[k] = miss[k] || new Set()).add(path + ' @' + w); }
  }
  await c.close();
 }
 const out = Object.fromEntries(Object.entries(miss).map(([k, v]) => [k, [...v]]));
 fs.writeFileSync(process.argv[4], JSON.stringify({ classes_seen: seen.size, missing: out }, null, 1));
 console.log(seen.size + ' class names seen; ' + Object.keys(out).length + ' Tailwind-looking names not in the CSS'); for (const [k, v] of Object.entries(out)) console.log('  ' + k + '  ← ' + v.slice(0, 3).join(', '));
 await b.close(); })();
