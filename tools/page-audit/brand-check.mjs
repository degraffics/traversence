// tools/page-audit/brand-check.mjs — BRAND, the check register's "The brand and design standards on every page"
// (handoffs/control-board-plan/step3/sections/check-register.md, row BRAND). Target (approved, Jason, 2026-11-22): the
// readable standards of brand.md met: its tokens, one header per view, filters as one dropdown, card anatomy, icons; text
// contrast at least 4.5:1, focus visible (brand.md §4; design/css-theme-brand.md; CLAUDE.md; W3C WCAG 2.2 SC 1.4.3 and 2.4.7).
//
// What a browser reads here, each with its source:
//   tokens   the design tokens are on the page (app/ui/theme-tokens.php, drawn by tv_page_start; css-theme-brand.md §3:
//            "must strictly bind to our established design tokens")
//   icons    no emoji in the page's controls, labels and headings (CLAUDE.md "Icons are the brand's flat SVGs (tv_icon()),
//            never emoji"; the same pictograph range as audit.py's R10, so ✓ ✕ ✎ ☰ ▾ stay allowed)
//   contrast text at least 4.5:1 on what's behind it, 3:1 for large text (WCAG 2.2 SC 1.4.3, which the target cites;
//            css-theme-brand.md §3 "a minimum 4.5:1 contrast"); disabled controls are exempt (SC 1.4.3), text over a photo or
//            gradient is counted as not measurable
//   focus    each control reached with the Tab key shows a visible focus mark (WCAG 2.2 SC 2.4.7; css-theme-brand.md §3
//            "render visible focus indicator rings")
// Not read yet, so every page is "partial" at best: one header per view, filters as one dropdown and card anatomy. The
// spec names them but not how a browser tells them apart on our pages (a question for Jason; R10, R11 and RENDER's 3px
// edges already cover icons in the code, breadcrumbs and phone edges).
//
// Daily it rides on load-check.mjs's page pass (ALSO=BRAND). On its own:
//   SITE=https://traversence.com CHECK_ACCOUNTS='{…}' node tools/page-audit/brand-check.mjs
// Only reads (the Tab key moves focus; nothing is pressed). Writes site-audit/results/brand.json in the one results format.
import { pathToFileURL } from 'node:url';
// no static import of load-check.mjs: it loads this file while its own run is under way, and a module importing it back
// would wait on that run forever
const isMain = (url) => !!process.argv[1] && url === pathToFileURL(process.argv[1]).href;

const TOKENS = ['--tv-canvas', '--tv-ink', '--tv-link', '--tv-btn', '--tv-meta', '--tv-umber'];
export const NOT_READ = ['one header per view', 'filters as one dropdown', 'card anatomy'];

/** Presses Tab up to 12 times and asks of each control reached: does it look different focused? */
export async function focusProbe(p, n = 12) {
  await p.evaluate(() => { window.__tvf = []; if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); }).catch(() => {});
  for (let i = 0; i < n; i++) {
    await p.keyboard.press('Tab').catch(() => {});
    await p.evaluate(() => {
      const a = document.activeElement; if (!a || a === document.body || a.tagName === 'IFRAME' || window.__tvf.some((x) => x.el === a)) return;
      const s = getComputedStyle(a);
      window.__tvf.push({ el: a, on: [s.outlineStyle, s.outlineWidth, s.outlineColor, s.boxShadow, s.borderColor, s.backgroundColor, s.textDecorationLine].join('|'), ring: s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0 });
    }).catch(() => {});
  }
  return p.evaluate(() => {
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
    const out = { reached: 0, without: 0, eg: [] };
    for (const x of window.__tvf || []) {
      const s = getComputedStyle(x.el);
      const off = [s.outlineStyle, s.outlineWidth, s.outlineColor, s.boxShadow, s.borderColor, s.backgroundColor, s.textDecorationLine].join('|');
      out.reached++;
      if (!(x.ring || x.on !== off)) { out.without++; if (out.eg.length < 4) out.eg.push(x.el.outerHTML.replace(/\s+/g, ' ').slice(0, 200)); }
    }
    return out;
  }).catch(() => ({ reached: 0, without: 0, eg: [] }));
}

/** In the page: the tokens, emoji in the controls, and text contrast. */
export async function brandProbe(p) {
  return p.evaluate((TOKENS) => {
    const out = { tokens_missing: [], emoji: [], contrast: { texts: 0, low: 0, unmeasured: 0, eg: [] } };
    const root = getComputedStyle(document.documentElement);
    for (const t of TOKENS) if (!root.getPropertyValue(t).trim()) out.tokens_missing.push(t);
    const vis = (el) => { const s = getComputedStyle(el); if (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity) === 0) return false; if (el.closest('[hidden], template, details:not([open]) > :not(summary)')) return false; const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
    // icons: the same range as audit.py's R10, in what the page offers (controls, labels, headings, menus)
    const EMOJI = /[\u{1F000}-\u{1FAFF}]|️/u;
    for (const el of document.querySelectorAll('a, button, label, summary, [role=button], [role=menuitem], [role=tab], h1, h2, h3, nav, th, option')) {
      if (!vis(el)) continue; const t = (el.innerText || '').trim();
      if (EMOJI.test(t) && out.emoji.length < 5) out.emoji.push(t.replace(/\s+/g, ' ').slice(0, 80));
    }
    // contrast (WCAG 2.2 SC 1.4.3): colours as computed, the background found by walking up and compositing
    const alpha = (v) => (v === undefined ? 1 : String(v).endsWith('%') ? parseFloat(v) / 100 : parseFloat(v));
    const parse = (c) => {   // rgb()/rgba(), or color(srgb …) for mixed colours; anything else isn't measured
      let m = c.match(/^rgba?\(([\d.]+)[, ]+([\d.]+)[, ]+([\d.]+)(?:[, /]+([\d.]+%?))?\)$/);
      if (m) return [+m[1], +m[2], +m[3], alpha(m[4])];
      m = c.match(/^color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+%?))?\)$/);
      if (m) return [m[1] * 255, m[2] * 255, m[3] * 255, alpha(m[4])];
      return null;
    };
    const over = (top, bot) => { const a = top[3] + bot[3] * (1 - top[3]); if (!a) return [0, 0, 0, 0]; return [0, 1, 2].map((i) => (top[i] * top[3] + bot[i] * bot[3] * (1 - top[3])) / a).concat(a); };
    const lum = (c) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
    const bgOf = (el) => {
      const layers = [];
      for (let e = el; e; e = e.parentElement) {
        const s = getComputedStyle(e);
        if (s.backgroundImage && s.backgroundImage !== 'none') return null;   // a photo or gradient behind the text: not measurable here
        const c = parse(s.backgroundColor); if (c && c[3] > 0) { layers.push(c); if (c[3] >= 1) break; }
      }
      let bg = [255, 255, 255, 1];   // the browser's own white under everything
      for (let i = layers.length - 1; i >= 0; i--) bg = over(layers[i], bg);
      return bg;
    };
    const seen = new Set();
    const tw = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let n = tw.nextNode(); n && seen.size < 600; n = tw.nextNode()) {
      const el = n.parentElement; if (!el || seen.has(el) || !n.textContent.trim()) continue;
      if (el.closest('svg, script, style, noscript, [disabled], [aria-disabled="true"], select, option')) continue;   // disabled controls: exempt in SC 1.4.3
      seen.add(el); if (!vis(el)) continue;
      out.contrast.texts++;
      const s = getComputedStyle(el); const fg = parse(s.color); const bg = bgOf(el);
      if (!fg || !bg) { out.contrast.unmeasured++; continue; }
      const f = over(fg, bg); const L1 = lum(f), L2 = lum(bg); const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
      const px = parseFloat(s.fontSize), bold = parseInt(s.fontWeight, 10) >= 700;
      const need = px >= 24 || (bold && px >= 18.66) ? 3 : 4.5;   // large text: 18pt, or 14pt bold
      if (ratio + 1e-6 < need) { out.contrast.low++; if (out.contrast.eg.length < 5) out.contrast.eg.push(`${ratio.toFixed(2)}:1 (needs ${need}:1) ${s.color} on rgb(${bg.slice(0, 3).map(Math.round).join(', ')}): "${n.textContent.trim().replace(/\s+/g, ' ').slice(0, 60)}"`); }
    }
    return out;
  }, TOKENS);
}

/** Against the target: a standard read and missed fails the page; all read and met is "partial" (three aren't read yet). */
export function judge(b, focus) {
  if (!b || b.error) return 'fail';
  const missed = b.tokens_missing.length > 0 || b.emoji.length > 0 || b.contrast.low > 0 || (focus && focus.without > 0);
  b.standards = { tokens: b.tokens_missing.length ? 'fail' : 'pass', icons: b.emoji.length ? 'fail' : 'pass', contrast: b.contrast.low ? 'fail' : (b.contrast.texts ? 'pass' : 'no check'),
    focus: !focus || !focus.reached ? 'no check' : focus.without ? 'fail' : 'pass', ...Object.fromEntries(NOT_READ.map((k) => [k, 'no check'])) };
  return missed ? 'fail' : 'partial';
}

if (isMain(import.meta.url)) {
  const { runPass } = await import('./load-check.mjs');
  await runPass({ checks: ['BRAND'], mode: 'full', mods: { B: { focusProbe, brandProbe, judge } } });
}
