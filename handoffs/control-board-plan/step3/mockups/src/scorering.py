# Score gauges (Jason, 2026-11-22: "how about we dont use a needle and instead use the score like gauge"; then "you could also just
# show the top half of the circle and make the band go 0 - 100 with 50 being the half way mark"). **Arc, Jason's choice, 2026-11-22**:
# the top half only, 0 at the left end, 50 at the top, 100 at the right end, the number in the opening. No needle. The board draws
# Arc only (no switch); Ring (a full circle, the number in the middle) stays drawable here with style='ring', and is never output.
# The arc and the number take the colour of k: the stage (pl, pd, ar, im, mo) or, at Monitoring on a runtime gauge, the reading
# against its target (g met, a below, r a trip, n no reading). The stage word stays as a pill under the gauge.
import html

E = html.escape
ARC = 'M6 38 A30 30 0 0 1 66 38'           # the top half: left end (0), top (50), right end (100)


def score(value, k, label, unit='%', shown=None, extra='', ncls='', cls='', style='arc'):
    """One gauge, in Arc (or Ring when asked). value: 0-100 (the arc's share), or None for no reading (the track only, "—").
    shown: the number as written (defaults to value rounded). label: the aria-label, e.g. "Directory: 52 percent, Phased Development"."""
    v = None if value is None else max(0.0, min(100.0, float(value)))
    d = 0 if v is None else v
    num = '—' if v is None else (shown if shown is not None else f'{v:.0f}')
    u = '' if v is None else f'<small>{E(unit)}</small>'
    if style == 'ring':
        draw = (f'<svg class="sr-ring" viewBox="0 0 72 72" aria-hidden="true"><circle class="sr-t" cx="36" cy="36" r="30"/>'
                f'<circle class="sr-a" cx="36" cy="36" r="30" pathLength="100" stroke-dasharray="{d:.1f} 100" transform="rotate(-90 36 36)"/></svg>')
    else:
        draw = (f'<svg class="sr-arc" viewBox="0 0 72 48" aria-hidden="true"><path class="sr-t" d="{ARC}"/>'
                f'<path class="sr-a" d="{ARC}" pathLength="100" stroke-dasharray="{d:.1f} 100"/>'
                f'<text class="sr-e" x="6" y="47">0</text><text class="sr-e" x="66" y="47">100</text></svg>')
    return (f'<span class="sr sr-{style} k-{k}{(" " + cls) if cls else ""}" role="img" aria-label="{E(label)}" data-score="{d:.1f}"{extra}>'
            f'{draw}<span class="sr-n{" sr-long" if len(num) > 3 else ""}{(" " + ncls) if ncls else ""}">{E(num)}{u}</span></span>')

CSS = r"""
.sr{position:relative;display:block;width:76px;margin:.15rem auto 0;line-height:1;color:#7A6A52}
.sr svg{display:block;width:100%;height:auto;overflow:visible}
.sr-t{fill:none;stroke:#E6DDC8;stroke-width:6}.sr-a{fill:none;stroke:currentColor;stroke-width:6;stroke-linecap:butt;transition:stroke-dasharray 150ms ease-out}
.sr-e{font:600 7px system-ui,sans-serif;fill:#8a7d6a;text-anchor:middle}
.sr-n{position:absolute;left:0;right:0;bottom:17%;text-align:center;font-weight:700;font-size:18px;color:currentColor;letter-spacing:-.02em}
.sr-n small{font-size:10px;font-weight:600;margin-left:1px}.sr .sr-n.sr-long{font-size:15px}.sr.big .sr-n.sr-long{font-size:26px}
.sr.k-pl{color:#7A6A52}.sr.k-pd{color:var(--amber,#B45309)}.sr.k-ar{color:var(--copper,#C2410C)}.sr.k-im{color:#3B5A6B}.sr.k-mo,.sr.k-g{color:#3d6b52}
.sr.k-a{color:var(--amber,#B45309)}.sr.k-r{color:var(--red,#B91C1C)}.sr.k-n{color:#7A6A52}
.sr.big{width:140px}.sr.big .sr-n{font-size:30px}.sr.big .sr-n small{font-size:14px}
@media (max-width:767px){.sr{width:68px}.sr-n{font-size:16px}}
"""

# Ring's own rules, kept here and never output (Arc is the board's only style); a page that asks for style='ring' adds them.
RING_CSS = '.sr-ring{width:68px}.sr-ring .sr-n{top:50%;bottom:auto;transform:translateY(-50%);font-size:19px}'

JS = r"""<script>
(function(){if(window.__srInit)return;window.__srInit=1;
window.tvScore=function(el,v,k,label,num){if(!el)return;el.className=el.className.replace(/\bk-\w+/,'k-'+k);el.setAttribute('data-score',v);if(label)el.setAttribute('aria-label',label);
 el.querySelectorAll('.sr-a').forEach(function(a){a.setAttribute('stroke-dasharray',v+' 100');});var n=el.querySelector('.sr-n');if(n&&num!=null)n.innerHTML=num;};
})();
</script>"""
