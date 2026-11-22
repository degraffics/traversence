// Score gauges (Jason, 2026-11-22): Ring or Arc, no needle. Run in the page: every gauge's .sr is checked against its score.
// Returns {style, n, bad[]}: one visible drawing per gauge in the page's style, the band's length within 1% of the score, the number
// present and matching, role="img" with a label, the stage pill beside it (not for the big reading in a snapshot panel), and the Arc's
// band ending where its score says (50 at the top centre). Also counts any needle left anywhere.
module.exports.ringProblems = () => {
  const style = document.documentElement.getAttribute('data-gstyle') || 'ring';
  const bad = []; let n = 0, top50 = 0;
  document.querySelectorAll('.sr').forEach((r, i) => {
    n++;
    const id = (r.getAttribute('aria-label') || '#' + i).slice(0, 40);
    const s = parseFloat(r.dataset.score);
    const vis = [...r.querySelectorAll('svg')].filter(v => getComputedStyle(v).display !== 'none');
    if (vis.length !== 1 || !vis[0].classList.contains('sr-' + style)) bad.push(id + ': not one ' + style);
    r.querySelectorAll('.sr-a').forEach(a => {
      const d = parseFloat((a.getAttribute('stroke-dasharray') || '').split(' ')[0]);
      if (a.getAttribute('pathLength') !== '100' || !(Math.abs(d - s) <= 1)) bad.push(id + ': band ' + d + ' vs score ' + s);
    });
    const num = r.querySelector('.sr-n'); const t = num ? num.textContent.replace('%', '').trim() : '';
    if (!t || (t !== '—' && Math.abs(parseFloat(t) - s) > 0.6)) bad.push(id + ': number "' + t + '"');
    if (r.getAttribute('role') !== 'img' || !/percent|no reading/.test(r.getAttribute('aria-label') || '')) bad.push(id + ': aria');
    if (!r.classList.contains('big')) {
      const box = r.closest('.dg-g, .dg-rtg, .gcard, .wfg');
      if (!box || !box.querySelector('.dg-word, .band-word')) bad.push(id + ': no stage pill');
    }
    if (style === 'arc' && vis.length === 1 && vis[0].getBoundingClientRect().width > 0) {
      const a = vis[0].querySelector('.sr-a'); const L = a.getTotalLength(); const p = a.getPointAtLength(L * s / 100);
      const ang = Math.PI * (1 - s / 100); const ex = 36 + 30 * Math.cos(ang), ey = 38 - 30 * Math.sin(ang);
      if (Math.hypot(p.x - ex, p.y - ey) > 0.6) bad.push(id + ': arc end off');
      if (Math.abs(s - 50) < 0.01) { const q = a.getPointAtLength(L / 2); if (Math.abs(q.x - 36) < 0.3 && Math.abs(q.y - 8) < 0.3) top50++; else bad.push(id + ': 50 not at top centre'); }
    }
  });
  const needles = document.querySelectorAll('.needle, .dg-needle, .hub, .dg-hub, .sr line, [class*=needle]').length;
  return { style, n, bad: bad.slice(0, 8), nbad: bad.length, needles, top50 };
};
