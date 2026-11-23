// tools/page-audit/photos-shown.mjs — PHOTOS-SHOWN, the check register's "Every photo on a page shown"
// (handoffs/control-board-plan/step3/sections/check-register.md, row PHOTOS-SHOWN; Jason, 2026-11-22: "the photo loads and
// display"). Target (approved, Jason, 2026-11-22): every image on the page loaded and drawn (decoded, wider than 0) and with a
// text alternative (W3C WCAG 2.2 SC 1.1.1 Non-text Content). Measured at 1280 and 390 (the register's measure).
// photos-check.sh proves the uploads rule; this proves a page's photos are drawn.
//
// Daily it rides on load-check.mjs's page pass (ALSO=PHOTOS-SHOWN), so each page is opened once for LOAD, PHOTOS-SHOWN and
// BRAND. On its own:  SITE=https://traversence.com CHECK_ACCOUNTS='{…}' node tools/page-audit/photos-shown.mjs
// Only reads: it scrolls each page down (at most 15 screens, so lazy photos load) and back up. Writes
// site-audit/results/photos-shown.json in the register's one results format; a row for every page that shows a photo.
import { pathToFileURL } from 'node:url';
// no static import of load-check.mjs: it loads this file while its own run is under way, and a module importing it back
// would wait on that run forever
const isMain = (url) => !!process.argv[1] && url === pathToFileURL(process.argv[1]).href;

/** In the page: scroll so lazy photos load, then every <img> with a box and every CSS background photo. */
export async function photosProbe(p) {
  await p.evaluate(async () => {
    const step = Math.round(window.innerHeight * 0.8);
    for (let i = 0, y = 0; i < 15 && y < document.documentElement.scrollHeight; i++, y += step) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 200)); }
    window.scrollTo(0, 0);
  }).catch(() => {});
  await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  return p.evaluate(async () => {
    const shown = (el) => { const s = getComputedStyle(el); if (s.display === 'none' || s.visibility === 'hidden') return false; if (el.closest('[hidden], template, details:not([open]) > :not(summary)')) return false; const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
    const short = (u) => String(u || '').replace(location.origin, '').slice(0, 160);
    const out = { images: 0, drawn: 0, broken: [], no_alt: [], decorative: 0, hidden: 0, backgrounds: 0, bg_broken: [] };
    const wait = (im) => new Promise((res) => { if (im.complete) return res(); const t = setTimeout(res, 5000); im.addEventListener('load', () => { clearTimeout(t); res(); }, { once: true }); im.addEventListener('error', () => { clearTimeout(t); res(); }, { once: true }); });
    for (const im of document.querySelectorAll('img')) {
      if (!im.getAttribute('src') && !im.getAttribute('srcset') && !im.currentSrc) continue;   // a placeholder with nothing to load yet
      if (!shown(im)) { out.hidden++; continue; }   // a slide not on screen, a folded part: not drawn by design
      out.images++;
      await wait(im);
      let decoded = im.complete && im.naturalWidth > 0;
      if (decoded && im.decode) { try { await im.decode(); } catch (e) { decoded = false; } }
      if (decoded) out.drawn++; else if (out.broken.length < 8) out.broken.push(short(im.currentSrc || im.src)); else out.broken.push('…');
      // a text alternative: alt (alt="" marks a decorative image, WCAG 1.1.1), or aria-label / aria-labelledby, or role presentation
      if (im.hasAttribute('alt')) { if (im.getAttribute('alt') === '') out.decorative++; }
      else if (!im.getAttribute('aria-label') && !im.getAttribute('aria-labelledby') && !/^(presentation|none)$/.test(im.getAttribute('role') || '')) { if (out.no_alt.length < 8) out.no_alt.push(short(im.currentSrc || im.src)); else out.no_alt.push('…'); }
    }
    // photos drawn as a CSS background (heroes, avatars): loaded from the browser's cache to see they decode. No alternative
    // is asked of them here: a background is decoration to WCAG unless its element has a role of img (then its label counts).
    const bgs = new Map();
    for (const el of document.querySelectorAll('body *')) {
      const bi = getComputedStyle(el).backgroundImage; if (!bi || bi === 'none' || !bi.includes('url(')) continue;
      const m = bi.match(/url\(["']?([^"')]+)["']?\)/); if (!m || m[1].startsWith('data:') || /\.svg(\?|$)/i.test(m[1])) continue;   // inline and vector art aren't photos
      if (!shown(el)) continue;
      bgs.set(m[1], el);
    }
    for (const [u] of bgs) {
      out.backgrounds++;
      const ok = await new Promise((res) => { const im = new Image(); const t = setTimeout(() => res(false), 8000); im.onload = () => { clearTimeout(t); res(im.naturalWidth > 0); }; im.onerror = () => { clearTimeout(t); res(false); }; im.src = u; });
      if (!ok && out.bg_broken.length < 8) out.bg_broken.push(short(u));
    }
    out.broken = out.broken.slice(0, 9); out.no_alt = out.no_alt.slice(0, 9);
    return out;
  });
}

/** Against the target: every photo drawn, every <img> with a text alternative. */
export function judge(m) {
  if (m.error) return 'fail';
  return m.broken.length || m.no_alt.length || m.bg_broken.length ? 'fail' : 'pass';
}

if (isMain(import.meta.url)) {
  const { runPass } = await import('./load-check.mjs');
  await runPass({ checks: ['PHOTOS-SHOWN'], mode: 'full', mods: { P: { photosProbe, judge } } });
}
