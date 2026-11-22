# Score gauges (Jason, 2026-11-22: "how about we dont use a needle and instead use the score like gauge"; then "you could also just
# show the top half of the circle and make the band go 0 - 100 with 50 being the half way mark"). Two styles, no needle in either,
# pending his choice: Ring (a full circle, the arc in proportion to the score, the number in the middle) and Arc (the top half only:
# 0 at the left end, 50 at the top, 100 at the right end, the number in the opening). Every gauge draws both; the page's switch
# (Gauge style: Ring | Arc, remembered in localStorage) shows one. Ring is the default until Jason chooses.
# The arc and the number take the colour of k: the stage (pl, pd, ar, im, mo) or, at Monitoring on a runtime gauge, the reading
# against its target (g met, a below, r a trip, n no reading). The stage word stays as a pill under the gauge.
import html

E = html.escape
ARC = 'M6 38 A30 30 0 0 1 66 38'           # the top half: left end (0), top (50), right end (100)


def score(value, k, label, unit='%', shown=None, extra='', ncls='', cls=''):
    """One gauge, both styles. value: 0-100 (the arc's share), or None for no reading (the track only, "—").
    shown: the number as written (defaults to value rounded). label: the aria-label, e.g. "Directory: 52 percent, Phased Development"."""
    v = None if value is None else max(0.0, min(100.0, float(value)))
    d = 0 if v is None else v
    num = '—' if v is None else (shown if shown is not None else f'{v:.0f}')
    u = '' if v is None else f'<small>{E(unit)}</small>'
    return (f'<span class="sr k-{k}{(" " + cls) if cls else ""}" role="img" aria-label="{E(label)}" data-score="{d:.1f}"{extra}>'
            f'<svg class="sr-ring" viewBox="0 0 72 72" aria-hidden="true"><circle class="sr-t" cx="36" cy="36" r="30"/>'
            f'<circle class="sr-a" cx="36" cy="36" r="30" pathLength="100" stroke-dasharray="{d:.1f} 100" transform="rotate(-90 36 36)"/></svg>'
            f'<svg class="sr-arc" viewBox="0 0 72 48" aria-hidden="true"><path class="sr-t" d="{ARC}"/>'
            f'<path class="sr-a" d="{ARC}" pathLength="100" stroke-dasharray="{d:.1f} 100"/>'
            f'<text class="sr-e" x="6" y="47">0</text><text class="sr-e" x="66" y="47">100</text></svg>'
            f'<span class="sr-n{" sr-long" if len(num) > 3 else ""}{(" " + ncls) if ncls else ""}">{E(num)}{u}</span></span>')


def switch():
    return ('<div class="sr-sw" role="group" aria-label="Gauge style"><span class="sr-swl">Gauge style:</span>'
            '<button type="button" data-gstyle-set="ring" aria-pressed="true">Ring</button>'
            '<button type="button" data-gstyle-set="arc" aria-pressed="false">Arc</button>'
            '<span class="sr-swn">both pending Jason\'s choice; Ring until he chooses</span></div>')


CSS = r"""
.sr{position:relative;display:block;width:68px;margin:.15rem auto 0;line-height:1;color:#7A6A52}
.sr svg{display:block;width:100%;height:auto;overflow:visible}.sr .sr-arc{display:none}
html[data-gstyle="arc"] .sr{width:76px}html[data-gstyle="arc"] .sr .sr-ring{display:none}html[data-gstyle="arc"] .sr .sr-arc{display:block}
.sr-t{fill:none;stroke:#E6DDC8;stroke-width:6}.sr-a{fill:none;stroke:currentColor;stroke-width:6;stroke-linecap:butt;transition:stroke-dasharray 150ms ease-out}
.sr-e{font:600 7px system-ui,sans-serif;fill:#8a7d6a;text-anchor:middle}
.sr-n{position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);text-align:center;font-weight:700;font-size:19px;color:currentColor;letter-spacing:-.02em}
.sr-n small{font-size:10px;font-weight:600;margin-left:1px}.sr .sr-n.sr-long{font-size:15px}.sr.big .sr-n.sr-long{font-size:26px}
html[data-gstyle="arc"] .sr-n{top:auto;bottom:17%;transform:none;font-size:18px}
.sr.k-pl{color:#7A6A52}.sr.k-pd{color:var(--amber,#B45309)}.sr.k-ar{color:var(--copper,#C2410C)}.sr.k-im{color:#3B5A6B}.sr.k-mo,.sr.k-g{color:#3d6b52}
.sr.k-a{color:var(--amber,#B45309)}.sr.k-r{color:var(--red,#B91C1C)}.sr.k-n{color:#7A6A52}
.sr.big{width:120px}html[data-gstyle="arc"] .sr.big{width:140px}.sr.big .sr-n{font-size:30px}.sr.big .sr-n small{font-size:14px}
.sr-sw{display:inline-flex;flex-wrap:wrap;gap:.25rem;align-items:center;font-size:12px;margin:.2rem 0}.sr-swl{color:var(--meta,#6b5f4f)}.sr-swn{color:var(--meta,#6b5f4f);font-size:11px}
.sr-sw button{border:1px solid var(--field-edge,#d8ccb4);background:var(--card,#fff);border-radius:999px;padding:.15rem .6rem;font:inherit;font-size:12px;cursor:pointer;color:var(--ink,#2b2118)}
.sr-sw button[aria-pressed="true"]{background:var(--btn,#92400E);color:var(--btn-text,#fff);border-color:var(--btn,#92400E)}
@media (max-width:767px){.sr{width:60px}html[data-gstyle="arc"] .sr{width:68px}.sr-n{font-size:17px}html[data-gstyle="arc"] .sr-n{font-size:16px}}
"""

JS = r"""<script>
(function(){if(window.__srInit)return;window.__srInit=1;
function set(s){if(s!=='arc')s='ring';document.documentElement.setAttribute('data-gstyle',s);
 document.querySelectorAll('[data-gstyle-set]').forEach(function(b){b.setAttribute('aria-pressed',b.getAttribute('data-gstyle-set')===s?'true':'false');});}
var s='ring';try{s=localStorage.getItem('tv-gauge-style')||'ring';}catch(e){}
set(s);if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){set(document.documentElement.getAttribute('data-gstyle'));});
document.addEventListener('click',function(e){var b=e.target.closest('[data-gstyle-set]');if(!b)return;var v=b.getAttribute('data-gstyle-set');set(v);try{localStorage.setItem('tv-gauge-style',v);}catch(x){}});
window.tvScore=function(el,v,k,label,num){if(!el)return;el.className=el.className.replace(/\bk-\w+/,'k-'+k);el.setAttribute('data-score',v);if(label)el.setAttribute('aria-label',label);
 el.querySelectorAll('.sr-a').forEach(function(a){a.setAttribute('stroke-dasharray',v+' 100');});var n=el.querySelector('.sr-n');if(n&&num!=null)n.innerHTML=num;};
})();
</script>"""
