// tools/page-audit/page-check.mjs — one page in a real browser, held to the build standards (decisions/0145 §1): the check
// rendered.mjs has always made, moved here (Piece 3, P3-1) so the before-and-after check (beforeafter.mjs) makes the very
// same one. Nothing here changes anything on the site: it only reads.
export const SIZES = { phone: { width: 390, height: 844 }, desktop: { width: 1280, height: 900 } };

export const STANDARDS = {
  status: 'The page opens (no 4xx or 5xx)', console: 'No script errors', requests: 'Its own requests succeed', scroll: 'No sideways scroll at 390px (0058 §11)',
  edges: 'Cards 3px from the screen edge at 390px (0058 §11)', dialogs: 'No browser dialogs (alert, confirm, prompt)', alt: 'Images have alt text', names: 'Buttons and links have a name',
};
/** One page as `who` at `size`: the standards' readings. opts: site (required), must(url) (a 4xx must not happen), shots,
 * shotDir, allSizes (screenshots at both sizes, whole page), extra(page, size) (more readings from the loaded page). */
export async function pageCheck(ctx, who, url, size, opts = {}) {
  const SITE = opts.site; const shots = opts.shots || false; const shotDir = opts.shotDir || 'shots';
  const p = await ctx.newPage(); await p.setViewportSize(SIZES[size]);
  // a page that needs an id (album.php with no album) rightly answers 4xx: only the core pages, the guide paths and admin sections must open
  const must = opts.must ? opts.must(url) : (url.includes('?') || url.startsWith('/admin/'));
  const f = { who, url, size, must, status: 0, console: [], requests: [], dialogs: [], scroll: false, edges: 0, alt: 0, names: 0 };
  // a failed load is counted under requests; an error from another company's frame on the page (Cloudflare's Turnstile box,
  // a payment gateway's card fields) is theirs, not the page's, so only our own site's errors count
  const ours = (m) => { const u = (m.location && m.location().url) || ''; return !u || u.startsWith(SITE) || u.startsWith('about:') || u.startsWith('blob:' + SITE); };
  p.on('console', m => { if (m.type() === 'error' && ours(m) && !/^Failed to load resource|^%c/.test(m.text())) f.console.push(m.text().slice(0, 200)); });
  p.on('pageerror', e => f.console.push(String(e).slice(0, 200)));
  p.on('response', r => { const u = r.url(); if (u.startsWith(SITE) && r.status() >= 400 && r.request().resourceType() !== 'document' && !/\/api\/(count|out)\.php/.test(u)) f.requests.push(`${r.status()} ${u.replace(SITE, '')}`.slice(0, 160)); });
  p.on('dialog', d => { f.dialogs.push(`${d.type()}: ${d.message().slice(0, 120)}`); d.dismiss().catch(() => {}); });
  try {
    const t0 = Date.now();
    const r = await p.goto(SITE + url, { waitUntil: 'load', timeout: opts.timeout || 45000 });   // opts.timeout: the before-and-after check's late retry (90 s)
    f.ms = Date.now() - t0; f.status = r ? r.status() : 0; f.final = p.url().replace(SITE, '');
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
    if (shots && (size === 'phone' || opts.allSizes)) await p.screenshot({ path: `${shotDir}/${who}-${url.replace(/[^a-z0-9]+/gi, '_').slice(0, 80)}${opts.allSizes ? '-' + size : ''}.png`, fullPage: !!opts.allSizes }).catch(() => {});
    if (opts.extra) Object.assign(f, await opts.extra(p, size).catch(e => ({ extra_error: String(e).slice(0, 200) })));   // more of the same loaded page (the before-and-after check, Piece 3)
  } catch (e) { f.error = String(e).slice(0, 200); }
  await p.close();
  return f;
}
