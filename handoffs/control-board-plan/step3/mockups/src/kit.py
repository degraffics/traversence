# Shared kit for the Railway card mockups (step 3). Generates static HTML; not site code.
import html

OUT = '/home/user/traversence/handoffs/control-board-plan/step3/mockups/'
E = html.escape

CSS = r"""
:root{
 --canvas:#E4DCC5;--sec:#EDE6D5;--sec-edge:#DBD2BA;--card:#F6F1E4;--card-edge:#E3DBC6;
 --ink:#2A1D14;--meta:#5E4F3C;--taupe:#7A6A52;--brown:#92400E;--umber:#3E2B1F;--void:#231D17;
 --red:#a62b2b;--amber:#8f5600;--sage:#92BEA5;--copper:#C85A17;--crimson:#8C3A23;--blue:#3B5A6B;
 --field-edge:#8D7E68;--btn:#92400E;--btn-text:#E4DCC5;
 --sans:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;--serif:Merriweather,"Playfair Display",Georgia,serif;
 --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{background:var(--canvas);color:var(--ink);font:14px/1.5 var(--sans);overflow-wrap:anywhere}
a{color:var(--brown)}
code,.k{font-family:var(--mono);font-size:.86em;overflow-wrap:anywhere;word-break:break-word}
h1,h2,h3{font-family:var(--serif);color:var(--ink);margin:0}
.bar{background:var(--umber);color:#EDE6D5;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;padding:.55rem 1.25rem;border-bottom:1px solid #EDE6D5}
.bar b{font-weight:600}
.bar .sp{flex:1}
.bar .chip{border:1px solid #6b5848;border-radius:999px;padding:.05rem .6rem;font-size:12px}
.wrap{max-width:1240px;margin:0 auto;padding:1.1rem 1.25rem 3rem}
.intro{background:var(--sec);border:1px solid var(--sec-edge);border-radius:.5rem;padding:.7rem .9rem;margin:0 0 1rem;color:var(--meta);font-size:13px}
.intro b{color:var(--ink)}
.note{border:1px dashed var(--field-edge);background:transparent;border-radius:.5rem;padding:.55rem .8rem;margin:.8rem 0;font-size:12.5px;color:var(--meta)}
.note::before{content:"Mockup note · ";font-weight:700;color:var(--ink)}
.sc{border-bottom:1.5px dotted var(--copper);background:rgba(200,90,23,.08)}
.sec{background:var(--sec);border:1px solid var(--sec-edge);border-radius:.5rem;padding:.9rem;margin:0 0 1rem}
.sec>h2{font-size:16px;margin:0 0 .6rem}
.grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
/* lights: never colour alone */
.lt{display:inline-flex;align-items:center;gap:.35rem;font-weight:600;white-space:normal}
.lt i{flex:none;width:11px;height:11px;border-radius:50%;display:inline-block}
.lt.g i{background:var(--sage);box-shadow:0 0 0 2px #6f9f86 inset}
.lt.a i{background:var(--amber)} .lt.a{color:var(--amber)}
.lt.r i{background:var(--red)} .lt.r{color:var(--red)}
.lt.n i{background:#b9ad95} .lt.n{color:var(--meta)}
.lt.b i{background:var(--blue)} .lt.b{color:var(--blue)}
.lt.h i{background:transparent;border:2px solid var(--amber);border-radius:2px;width:10px;height:10px}
.lt.h{color:var(--amber)}
/* card */
.card{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;margin:0 0 1rem;min-width:0}
.card.fault-r{border-left:5px solid var(--red)} .card.fault-a{border-left:5px solid var(--amber)}
.z{padding:.8rem .95rem;border-top:1px solid var(--card-edge);min-width:0}
.z:first-child{border-top:0}
.zh{font:600 11px/1.2 var(--sans);letter-spacing:.06em;text-transform:uppercase;color:var(--meta);margin:0 0 .5rem;display:flex;gap:.5rem;align-items:baseline;flex-wrap:wrap}
.zh .n{background:var(--sec);border:1px solid var(--sec-edge);border-radius:999px;padding:0 .45rem;letter-spacing:0}
.idl{display:flex;flex-wrap:wrap;gap:.45rem .6rem;align-items:center}
.name{font:700 19px/1.2 var(--serif);color:var(--brown);text-decoration:underline;text-underline-offset:3px}
.badge{display:inline-flex;flex-wrap:wrap;gap:.25rem;align-items:center;border:1px solid var(--sec-edge);background:var(--sec);border-radius:999px;padding:.1rem .6rem;font-size:12px;max-width:100%}
.badge.role{border-color:var(--umber);background:var(--umber);color:#EDE6D5;font-weight:600}
.badge.crit{color:var(--meta)}
.stage{display:flex;flex-wrap:wrap;gap:.3rem .6rem;align-items:center;margin-top:.55rem;font-size:13px}
.stage .st{border:1px solid var(--field-edge);border-radius:.4rem;padding:.1rem .5rem;font-weight:600;background:#fff8}
.stage .nx{color:var(--meta)}
.spend{display:inline-flex;flex-wrap:wrap;gap:.2rem .5rem;align-items:center;border:1px solid var(--field-edge);border-radius:.4rem;padding:.15rem .55rem;font-size:12.5px;max-width:100%}
.spend b{font-weight:700}
.spend.warn{border-color:var(--amber);color:var(--amber)}
/* thread (the six parts) */
.thread{background:#fff7;border:1px solid var(--card-edge);border-radius:.5rem;padding:.7rem .8rem;margin:.2rem 0 0}
.thread .tt{font:700 16px/1.3 var(--serif);margin:0 0 .25rem}
.thread .status{margin:.1rem 0 .5rem}
.rad{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem;margin:.4rem 0}
.rad>div{border-radius:.4rem;padding:.45rem .55rem;font-size:13px;min-width:0}
.rad .h{background:#a62b2b14;border:1px solid #a62b2b55}
.rad .o{background:#8f560012;border:1px solid #8f560055}
.rad .u{background:#92BEA522;border:1px solid #6f9f8666}
.rad b{display:block;font-size:11px;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.15rem}
.rad ul{margin:0;padding-left:1.05rem}
.doing{font-size:13px;color:var(--meta);margin:.35rem 0}
details{margin:.4rem 0 0}
summary{cursor:pointer;color:var(--brown);font-weight:600;font-size:13px}
.dl{display:grid;grid-template-columns:minmax(120px,190px) minmax(0,1fr);gap:.25rem .8rem;font-size:13px;margin:.4rem 0}
.dl>dt{color:var(--meta)} .dl>dd{margin:0;min-width:0}
/* zone 4 action rows */
.act{display:grid;grid-template-columns:minmax(120px,150px) minmax(0,1fr);gap:.5rem .8rem;align-items:start;margin:.35rem 0}
.act>.lbl{font-weight:700;font-size:12.5px;padding-top:.3rem}
.btns{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;min-width:0}
.btn{display:inline-block;border:1.5px solid var(--btn);color:var(--btn);background:transparent;border-radius:999px;padding:.22rem .8rem;font:600 13px/1.4 var(--sans);text-decoration:none;cursor:pointer;max-width:100%;white-space:normal;text-align:left}
.btn.f{background:var(--btn);color:var(--btn-text)}
.btn[disabled],.btn.off{opacity:.5;cursor:not-allowed}
.why{font-size:12px;color:var(--meta)}
.arrow{color:var(--meta);font-size:12px}
.slots{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.45rem;min-width:0}
.slot{border:1px solid var(--card-edge);background:var(--sec);border-radius:.4rem;padding:.4rem .5rem;font-size:12.5px;min-width:0}
.slot .ty{font:600 11px/1.2 var(--sans);letter-spacing:.05em;text-transform:uppercase;color:var(--meta)}
.slot a.pl{display:block;font-weight:700;margin:.15rem 0}
.slot .src{display:inline-block;border:1px solid var(--sage);border-radius:999px;padding:0 .4rem;font-size:11px;color:#3f5e4d;background:#92BEA522}
.slot.none{background:transparent;border-style:dashed}
.slot.none .miss{color:var(--meta);margin:.15rem 0 .3rem}
.slot .pub{font-size:11.5px;color:var(--brown);text-decoration:underline;cursor:pointer}
.slot.fix{outline:2px solid var(--copper)}
.then{font-size:12px;color:var(--meta);margin-top:.25rem}
/* rows inside zones */
.rows{display:grid;gap:0;min-width:0}
.row{display:grid;grid-template-columns:minmax(150px,1.1fr) minmax(0,2.4fr) auto;gap:.3rem .8rem;align-items:start;padding:.5rem 0;border-top:1px solid var(--card-edge);min-width:0}
.row:first-child{border-top:0}
.row>*{min-width:0}
.row .nm{font-weight:700}
.row .sub{font-size:12.5px;color:var(--meta)}
.row.hl{background:#8f56000d;margin:0 -.5rem;padding:.5rem}
.row.hr{background:#a62b2b0d;margin:0 -.5rem;padding:.5rem}
.mm{color:var(--amber);font-weight:700}
.kv{display:flex;flex-wrap:wrap;gap:.2rem .9rem;font-size:12.5px}
.kv span{white-space:normal}
/* board */
.top{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.8rem .95rem;margin:0 0 1rem;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}
.top .q{font:600 11px/1.2 var(--sans);letter-spacing:.06em;text-transform:uppercase;color:var(--meta);margin-bottom:.25rem}
.top .a{font-size:14px}
.top.r{border-left:5px solid var(--red)}
.need{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;margin:0 0 .6rem;padding:.65rem .8rem}
.need .l1{display:flex;flex-wrap:wrap;gap:.3rem .7rem;align-items:baseline}
.need .t{font-weight:700}
.grp{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;margin:0 0 .6rem}
.grp>summary{padding:.55rem .8rem;list-style:none;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;color:var(--ink)}
.grp>summary::-webkit-details-marker{display:none}
.grp>summary::before{content:"▸";color:var(--brown)}
.grp[open]>summary::before{content:"▾"}
.grp .in{padding:0 .8rem .6rem}
.cnt{border-radius:999px;padding:0 .5rem;font-size:12px;font-weight:700;border:1px solid var(--sec-edge);background:var(--sec)}
.cnt.a{border-color:var(--amber);color:var(--amber)} .cnt.r{border-color:var(--red);color:var(--red)}
.host{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.7rem .8rem;min-width:0}
.host .hn{display:flex;flex-wrap:wrap;gap:.35rem .5rem;align-items:center}
.host .hn a{font:700 16px/1.2 var(--serif)}
.host ul{margin:.4rem 0 0;padding-left:1rem;font-size:12.5px}
/* surfaces */
.frame{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;margin:0 0 .8rem;overflow:hidden;min-width:0}
.frame>.fh{background:var(--umber);color:#EDE6D5;font-size:12px;padding:.3rem .7rem;display:flex;gap:.5rem;flex-wrap:wrap}
.frame>.fb{padding:.7rem .8rem;min-width:0}
.prow{display:grid;grid-template-columns:150px 110px minmax(0,1fr) 150px;gap:.5rem;font-size:13px;padding:.45rem 0;border-top:1px solid var(--card-edge)}
.prow.hd{font-size:11.5px;color:var(--meta);border-top:0}
.prow>*{min-width:0}
.sev{color:var(--red);font-weight:700}
.unread{font-weight:700}
.email{font-size:13.5px}
.email .hd2{display:grid;grid-template-columns:70px minmax(0,1fr);gap:.1rem .5rem;font-size:12.5px;color:var(--meta);border-bottom:1px solid var(--card-edge);padding-bottom:.4rem;margin-bottom:.5rem}
.email .hd2 b{color:var(--ink)}
.push{max-width:380px;background:#2b2520;color:#f3ede0;border-radius:14px;padding:.6rem .75rem;font-size:13px;box-shadow:0 4px 14px #0003}
.push .pa{font-size:11px;opacity:.75;display:flex;justify-content:space-between;gap:.5rem}
.push .pt{font-weight:700;margin:.15rem 0}
.steps{counter-reset:s;display:grid;gap:.9rem}
.step{border-left:3px solid var(--copper);padding-left:.8rem;min-width:0}
.step>h3{font-size:15px;margin:0 0 .45rem}
.step>.when{font-size:12px;color:var(--meta);margin-bottom:.4rem}
/* side panel */
.scrim{position:fixed;inset:0;background:#231D1766;display:none;z-index:50}
.panel{position:fixed;top:0;right:0;bottom:0;width:min(560px,100%);background:var(--sec);border-left:1px solid var(--sec-edge);z-index:51;overflow:auto;transform:translateX(100%);transition:transform 150ms ease-out;padding:.8rem}
.open .scrim{display:block}.open .panel{transform:none}
.x{float:right;border:1.5px solid var(--btn);background:var(--card);color:var(--btn);border-radius:999px;width:30px;height:30px;font-size:16px;cursor:pointer}
.panel-static{border:1px solid var(--sec-edge);background:var(--sec);border-radius:.5rem;padding:.7rem;margin-left:auto;max-width:600px}
table.ir{width:100%;border-collapse:collapse;font-size:13px}
table.ir th,table.ir td{border-top:1px solid var(--card-edge);text-align:left;vertical-align:top;padding:.45rem .5rem}
table.ir th{width:210px;color:var(--meta);font-weight:600}
.uc{display:inline-block;border:2px solid var(--amber);color:var(--amber);font-weight:800;border-radius:.35rem;padding:.05rem .5rem;letter-spacing:.03em}
.prov{font-size:12px;color:var(--meta)}
.prov li{margin:.15rem 0}
.legend{display:flex;flex-wrap:wrap;gap:.4rem 1rem;font-size:12px;color:var(--meta);margin:.3rem 0 0}
@media (max-width:767px){
 .wrap{padding:.6rem 3px 2rem}
 .intro,.note{margin-left:.4rem;margin-right:.4rem}
 .sec{padding:.6rem 3px;border-radius:.5rem}
 .sec>h2{margin-left:.4rem}
 .card,.need,.grp,.host,.frame,.top{margin-left:0;margin-right:0;border-radius:.5rem}
 .card,.need,.grp,.host,.frame,.top{margin-bottom:3px}
 .grid3,.grid2{grid-template-columns:minmax(0,1fr);gap:3px}
 .top{grid-template-columns:minmax(0,1fr)}
 .rad{grid-template-columns:minmax(0,1fr)}
 .slots{grid-template-columns:repeat(2,minmax(0,1fr))}
 .act{grid-template-columns:minmax(0,1fr)}
 .act>.lbl{padding-top:0}
 .row{grid-template-columns:minmax(0,1fr)}
 .dl{grid-template-columns:minmax(0,1fr)} .dl>dt{font-weight:600;margin-top:.3rem}
 .prow{grid-template-columns:minmax(0,1fr)} .prow.hd{display:none}
 table.ir th{width:auto} table.ir,table.ir tbody,table.ir tr,table.ir th,table.ir td{display:block}
 table.ir th{border-top:1px solid var(--card-edge);padding-bottom:0}
 table.ir td{border-top:0}
 .bar{padding:.5rem .6rem}
 .z{padding:.7rem .6rem}
}
"""

JS = r"""
<script>
(function(){
 var b=document.body;
 function close(){b.classList.remove('open');}
 document.addEventListener('click',function(e){
  var o=e.target.closest('[data-open-panel]');
  if(o){e.preventDefault();var id=o.getAttribute('data-open-panel');document.querySelectorAll('.panel').forEach(function(p){p.hidden=p.id!==id;});b.classList.add('open');return;}
  if(e.target.closest('.x')||e.target.classList.contains('scrim')){close();}
 });
 document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
})();
</script>"""


def page(fname, title, desc, body, panels=''):
    for m in SETUP:
        if f'data-open-panel="p-setup-{m}"' in body + panels and f'id="p-setup-{m}"' not in panels:
            panels += setup_panel(m)
    doc = f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{E(title)}</title><meta name="description" content="{E(desc)}">
<style>{CSS}</style></head>
<body>
<div class="bar"><b>System Control Board</b><span class="chip">Design mockup · step 3 · not site code</span><span class="sp"></span><span>{E(title)}</span></div>
<main class="wrap">
{body}
</main>
<div class="scrim"></div>{panels}
{JS}
</body></html>"""
    open(OUT + fname, 'w').write(doc)


# ---------- Setup & Access Capture (03-cards §13; 04 §10) ----------
SETUP = {
    'railway': dict(
        provider='Railway',
        notice='Setup capture: Sign in not recorded · Keys: why not recorded',
        keys=[('CRAWLER_API_TOKEN', '.env and Railway', 'the crawler\'s pass into our site'),
              ('RAILWAY_API_TOKEN', '.env', 'watching deploys, setting the schedule'),
              ('RAILWAY_PROJECT_ID', '.env', 'an id, not a secret'),
              ('RAILWAY_SERVICE_ID', '.env', 'an id, not a secret')],
        keys_note='Keys the worker holds for other modules (e.g. <code>TAVILY_API_KEY</code>) are recorded on those modules\' cards, with Railway as their place.',
        slots=[('Billing', 'Billing · Usage · Documented · release #212', None),
               ('Keys', 'Not registered', 'Railway\'s project Variables page isn\'t recorded'),
               ('Console', 'Console · Dashboard · Documented · release #212', None),
               ('Sign in', 'Not registered', '')]),
    'github': dict(
        provider='GitHub',
        notice='Setup capture: Sign in not recorded · Keys, Console: why not recorded',
        keys=[('GITHUB_DOCS_TOKEN', '.env', 'reads the inbox, writes release records, asks for the checks'),
              ('GITHUB_SYNC_TOKEN', '.env', 'watches the sync'),
              ('HEALTH_TOKEN', 'a GitHub secret on degraffics/traversence and its .env copy (they must match)', 'the daily health report'),
              ('MIRROR_READ_TOKEN', 'a GitHub secret on degraffics/traversence', 'the audit'),
              ('CHECK_ACCOUNTS', 'a GitHub secret on degraffics/traversence', 'the checks'),
              ('CLAUDE_CODE_OAUTH_TOKEN', 'a GitHub secret on degraffics/traversence', 'Claude\'s workflow'),
              ('ONEDRIVE_CLIENT_ID', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('ONEDRIVE_TENANT_ID', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('ONEDRIVE_REFRESH_TOKEN', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('FTP_HOST', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('FTP_USER', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('FTP_PASS', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('FTP_REMOTE_PATH', 'a GitHub secret on degraffics/traverse-3way-sync', 'the sync'),
              ('GH_PAT', 'a GitHub secret on degraffics/traverse-3way-sync (optional)', 'saving a rotated OneDrive token')],
        keys_note='Planned: a read-only billing token for Actions minutes (Jason, 2026-11-21: Vault, then Its key; not added yet).',
        slots=[('Billing', 'Billing · Documented · release #212', None),
               ('Keys', 'Not registered', 'GitHub\'s token settings page isn\'t recorded'),
               ('Console', 'Not registered', '“The sync” is our own workflow\'s page: it sits on the Sync part as “Its runs”'),
               ('Sign in', 'Not registered', '')]),
}


def setup_notice(mod):
    s = SETUP[mod]
    if s.get('clear'):
        return f'<div class="stage" style="color:var(--meta);font-size:12.5px">{s["clear"]}</div>'
    return (f'<div class="stage" style="color:var(--meta);font-size:12.5px">{s["notice"]} '
            f'<a href="#" data-open-panel="p-setup-{mod}" style="color:var(--meta)">Record ▸</a></div>')


def setup_details(mod):
    s = SETUP[mod]
    keys = ''.join(f'<li><code>{k}</code>: {where} <span class="why">({what})</span></li>' for k, where, what in s['keys'])
    slots = ''.join(f'<li><b>{n}:</b> {st}' + (f' <span class="why">({why})</span>' if why else '') + (' · <span class="why">no why yet</span>' if st == 'Not registered' and not why else '') + '</li>'
                    for n, st, why in s['slots'])
    return (f'<details><summary>Setup capture</summary><div class="sub" style="margin:.3rem 0">Names only, never values. Credentials live only in the Vault.</div>'
            f'<div style="font-size:13px"><b>Where to sign in:</b> Sign in slot: Not registered.</div>'
            f'<div style="font-size:13px;margin-top:.3rem"><b>Where its keys live:</b></div><ul style="margin:.2rem 0;padding-left:1.1rem;font-size:13px">{keys}</ul>'
            f'<div class="sub">{s["keys_note"]}</div>'
            f'<div style="font-size:13px;margin-top:.3rem"><b>Its slots:</b></div><ul style="margin:.2rem 0;padding-left:1.1rem;font-size:13px">{slots}</ul></details>')


def setup_panel(mod):
    s = SETUP[mod]
    rows = ''
    for n, st, why in s['slots']:
        if st != 'Not registered':
            continue
        rows += (f'<div class="slot none" style="margin:.4rem 0"><div class="ty">{n}</div>'
                 f'<div class="miss">No {"sign-in" if n == "Sign in" else ("API keys" if n == "Keys" else n.lower())} page registered for {s["provider"]} yet.</div>'
                 f'<div class="btns"><button class="btn">Publish from your Vault</button><span class="why">owner only</span></div>'
                 f'<label class="sub" style="display:block;margin-top:.4rem">Or a one-line why'
                 f'<input type="text" data-spell maxlength="140" style="display:block;width:100%;margin-top:.2rem;padding:.3rem .45rem;border:1px solid var(--field-edge);border-radius:.4rem;background:var(--card);font:inherit"></label>'
                 f'<div class="btns" style="margin-top:.3rem"><button class="btn">Record</button></div></div>')
    return (f'<aside class="panel" id="p-setup-{mod}" hidden role="dialog" aria-label="Setup capture: {s["provider"]}">'
            f'<button class="x" aria-label="Close">×</button><h2 style="font-size:16px;margin:.2rem 0 .3rem">Setup capture: {s["provider"]}</h2>'
            f'<div class="sub" style="margin-bottom:.5rem">{s["notice"]}. A notice only: never an alarm, a hold or a Pulse item. Names only; credentials go only into the Vault.</div>'
            f'{rows}{setup_details(mod).replace("<details>", "<details open>")}</aside>')


def light(kind, words):
    return f'<span class="lt {kind}"><i aria-hidden="true"></i>{words}</span>'


def sc(x):
    """A scenario value: illustrative, not read from live."""
    return f'<span class="sc" title="Scenario value: illustrative, not read from live">{x}</span>'


LEGEND = ('<div class="legend"><span><span class="sc">dotted copper</span> = a scenario value, illustrative, '
          'not read from live</span><span><b>not read yet</b> = the board would read it; this mockup could not</span></div>')

# ---------- Railway's real values (read 2026-11-21) ----------
RW_PROJECT = 'a445f0be-b6de-46bb-a422-51ff835ca6e4'
RW_SVC = {'traversence': '8d254c0d-abcb-4520-b9ae-dfbdd362b243', 'w0-indexer': 'd2f49c7a-cc36-436b-bfc2-bf93111ab287', 'video-worker': 'cc4bccbb-4028-47da-9820-24039b59d309'}
RW_DEP = {'22f18475': '22f18475-dfd2-4b47-ab3a-233e733d0f94', 'd0e9413f': 'd0e9413f-3752-4170-bc0e-8719bde138ec', '628caa0d': '628caa0d-3073-49f6-8e5f-18da4162f2aa'}


def deploy_url(svc, dep_full):
    return f'https://railway.com/project/{RW_PROJECT}/service/{RW_SVC[svc]}?id={dep_full}'


RW = {
    'plan': 'Hobby: $5 a month including $5 of usage',
    'usage_url': 'https://railway.com/account/usage',
    'dash_url': 'https://railway.com/dashboard',
    'help_url': 'https://station.railway.com/',
}
SERVICES = [
    # name, railway service, schedule railway, schedule repo, restart railway, restart repo, feeds, deploy
    dict(name='The crawler', svc='traversence', root='workers/crawler',
         sched='every 5 min (<code>*/5 * * * *</code>)', sched_repo='not set in <code>workers/crawler/railway.json</code>',
         rs='Never', rs_repo='Never', feeds='W1–W12 (gathering)',
         dep='22f18475', dep_st='SUCCESS', dep_at='Nov 21, 5:22 AM MST',
         commit='b2ab69e', msg='Workflow audit: a source down holds only what uses it (W0–W14), Common Crawl first; crawler 2.16.1 (#1238)',
         nxt='Next run 8:55 AM MST (Railway)'),
    dict(name='W0 Indexer', svc='w0-indexer', root='workers/crawler',
         sched='always on (<code>python -u indexer.py</code>)', sched_repo='always on (<code>railway.indexer.json</code>)',
         rs='On failure, up to 10', rs_repo='On failure, up to 10', feeds='W0',
         dep='d0e9413f', dep_st='SUCCESS', dep_at='Nov 21, 5:22 AM MST',
         commit='b2ab69e', msg='Workflow audit: a source down holds only what uses it (W0–W14), Common Crawl first; crawler 2.16.1 (#1238)',
         nxt='Heartbeat: not read yet'),
    dict(name='The video worker', svc='video-worker', root='/workers/video',
         sched='every 5 min (<code>*/5 * * * *</code>)', sched_repo='every 5 min (<code>workers/video/railway.json</code>)',
         rs='On failure, up to 10', rs_repo='Never', feeds='posted videos (720p and 1080p copies)',
         dep='628caa0d', dep_st='SUCCESS', dep_at='Nov 17, 6:30 PM MST',
         commit='430d4c3', msg='Release #116: start-report.zip taken from the inbox onto staging',
         nxt='Next run 8:55 AM MST (Railway) · heartbeat: <i>spec</i>, not built'),
]
HOSTED_KEYS = [  # (name, module, where) from service-defs.php
    ('TAVILY_API_KEY', 'Tavily', 'the crawler service → Variables'),
    ('BRAVE_API_KEY', 'Brave Search', 'the crawler service → Variables'),
    ('GOOGLE_PLACES_KEY', 'Google Cloud', 'the crawler service → Variables'),
    ('RIDB_API_KEY', 'Recreation.gov (RIDB)', 'the crawler service → Variables'),
    ('HF_TOKEN', 'Foursquare (via Hugging Face)', 'the crawler service → Variables'),
]


def slot(ty, provider, label=None, url=None, fix=False, mine=True, src='doc', rel='212', note=''):
    words = {'billing': 'billing', 'keys': 'API keys', 'console': 'console', 'login': 'sign-in'}[ty]
    head = {'billing': 'Billing', 'keys': 'Keys', 'console': 'Console', 'login': 'Sign in'}[ty]
    btn = {'billing': 'billing', 'keys': 'API keys', 'console': 'console', 'login': 'sign-in'}[ty]
    cls = 'slot' + (' fix' if fix else '')
    fx = f'<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if fix else ''
    if url:
        sub = f' · {E(label)}' if label and label.lower() != head.lower() else ''
        return (f'<div class="{cls}">{fx}<div class="ty">{head}{sub}</div>'
                f'<a class="pl" href="{E(url)}" target="_blank" rel="noopener">Open {E(provider)} {btn} ↗</a>'
                + (f'<span class="src" title="Written by a Claude session from {E(provider)}\'s own pages">Documented · release #{rel}</span>' if src == 'doc' else '<span class="src">Site settings</span>') + (f'<div class="why">{note}</div>' if note else '') + '</div>')
    pub = '<div><span class="pub" role="button" tabindex="0">Publish from your Vault</span> <span class="why">(owner only)</span></div>' if mine else ''
    return (f'<div class="{cls} none">{fx}<div class="ty">{head}</div>'
            f'<div class="miss">No {words} page registered for {E(provider)} yet.</div>{pub}</div>')


def railway_slots(fix=None):
    return ('<div class="slots">'
            + slot('billing', 'Railway', 'Usage', RW['usage_url'], fix == 'billing')
            + slot('keys', 'Railway', fix=fix == 'keys')
            + slot('console', 'Railway', 'Dashboard', RW['dash_url'], fix == 'console')
            + slot('login', 'Railway', fix=fix == 'login')
            + '</div>')


def act_rows(ours, at_provider='Railway', slots_html=None, then=None, extra_at=''):
    t = f'<div class="then">{then}</div>' if then else ''
    return (f'<div class="act"><div class="lbl">On our system</div><div class="btns">{ours}</div></div>'
            f'<div class="act"><div class="lbl">At {E(at_provider)}</div><div style="min-width:0">{slots_html or railway_slots()}{t}{extra_at}</div></div>')


def b(words, f=False, off=None):
    if off:
        return f'<span><button class="btn off" disabled>{words}</button> <span class="why">{off}</span></span>'
    return f'<button class="btn{" f" if f else ""}">{words}</button>'


def zone(n, title, inner, extra=''):
    return f'<div class="z"><div class="zh"><span class="n">Zone {n}</span>{title}{extra}</div>{inner}</div>'


def zone1(state_light, stage_line, spend_html, extra=''):
    return zone(1, 'Identity and role', f"""
<div class="idl"><a class="name" href="#">Railway</a>
<span class="badge role">Host</span><span class="badge crit">Not critical by itself</span>
{state_light}</div>
<div class="stage">{stage_line}</div>
{setup_notice('railway')}
<div class="stage">{spend_html}</div>{extra}{setup_details('railway')}""")


STAGE_WORKING = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause everything · '
                 'Take out (stage 6) · Deactivate (stage 7)</span>')
USAGE_UNITS = ('<code>CPU_USAGE</code> 87.17 · <code>MEMORY_USAGE_GB</code> 2,626.44 · <code>NETWORK_TX_GB</code> 2.39 · '
               '<code>NETWORK_RX_GB</code> 22.56')
USAGE_WHEN = 'since Nov 1, 12:00 AM UTC; read from Railway\'s API Nov 21, 9:27 AM MST'
SPEND_UNREAD = (f'<span class="spend"><span>Hobby · $5 a month including $5 of usage</span>'
                f'<span>Use since Nov 1: {USAGE_UNITS}</span><b>not priced yet</b>'
                f'<a href="{RW["usage_url"]}" target="_blank" rel="noopener">Billing · Usage ↗</a></span>')


def svc_rows(over=None):
    over = over or {}
    out = []
    for s in SERVICES:
        o = over.get(s['svc'], {})
        mism = s['rs'] != s['rs_repo']
        rs = (f'Restart: Railway <b>{s["rs"]}</b> · repo <b>{s["rs_repo"]}</b>'
              + (' <span class="mm">! they differ</span>' if mism else ' · match'))
        cls = 'row' + (' hl' if mism and not o.get('cls') else '') + (' ' + o['cls'] if o.get('cls') else '')
        st = o.get('state', light('g', 'On · Working'))
        out.append(f"""<div class="{cls}">
<div><div class="nm">{s['name']}</div><div class="sub">Railway service <code>{s['svc']}</code> · from <code>{s['root']}</code></div>{st}</div>
<div><div>Feeds <b>{s['feeds']}</b></div>
<div class="sub">Schedule: Railway {s['sched']} · repo {s['sched_repo']}</div>
<div class="sub">{rs}</div>
<div class="sub">Deploy <code>{s['dep']}</code> {s['dep_st']} · {s['dep_at']} · commit <code>{s['commit']}</code> “{E(s['msg'])}” · <a href="{deploy_url(s['svc'], RW_DEP[s['dep']])}" target="_blank" rel="noopener">its log ↗</a> <span class="why">(spec: built from Railway's project, service and deployment ids)</span></div>
<div class="sub">{s['nxt']}</div>{o.get('more','')}</div>
<div class="btns">{o.get('btn', b('Check now'))}</div></div>""")
    return '<div class="rows">' + ''.join(out) + '</div>'


def sensors(over=None, keys_over=None):
    over = over or {}
    keys_over = keys_over or {}
    rows = [
        ('Runs', 'The crawler: each run reports back. A run 35 min late, or failing outright, is the host\'s fault.', over.get('runs', 'Last run: <b>not read yet</b>')),
        ('Heartbeats', 'W0 Indexer: silent 15 min is a fault. The video worker: <i>spec</i>, not built.', over.get('hb', 'W0 Indexer: <b>not read yet</b> · the video worker: not built')),
        ('Deploys', 'The latest deploy of each service (today the site watches the crawler only; all three is <i>spec</i>).', over.get('dep', '3 of 3 SUCCESS (read from Railway\'s API, 8:51 AM MST)')),
        ('Spend', 'The month\'s use against the plan.', over.get('spend', f'{USAGE_UNITS} ({USAGE_WHEN}; no disk measures returned) · <b>not priced yet</b>: no price source is registered, so no dollar figure · <a href="{RW["usage_url"]}" target="_blank" rel="noopener">Billing · Usage ↗</a>')),
    ]
    h = ''
    for n, what, val in rows:
        cls = 'row' + (' ' + over[n + '_cls'] if over.get(n + '_cls') else '')
        h += f'<div class="{cls}"><div class="nm">{n}</div><div><div class="sub">{what}</div><div>{val}</div></div><div class="btns">{over.get(n + "_btn", "")}</div></div>'
    own = ''
    for k, where, st in [('CRAWLER_API_TOKEN', '.env and Railway', 'not read yet'), ('RAILWAY_API_TOKEN', '.env (watching deploys)', 'not read yet')]:
        own += f'<div class="row"><div class="nm"><code>{k}</code></div><div><div class="sub">Railway\'s own key · {where}</div><div>{keys_over.get(k, st)}</div></div><div class="btns"></div></div>'
    hosted = ''
    for k, mod, where in HOSTED_KEYS:
        d = keys_over.get(k)
        if d:
            hosted += d
        else:
            hosted += (f'<div class="row"><div><div class="nm"><code>{k}</code></div><div class="sub">{mod}\'s key, kept on Railway ({where})</div></div>'
                       f'<div>{light("n", "Not checked yet")}<div class="sub">{k} on Railway: not checked yet (the worker reports at its next run).</div></div><div class="btns"></div></div>')
    return (f'<div class="rows">{h}</div><h3 style="font:600 13px var(--sans);margin:.8rem 0 .2rem">Railway\'s own keys</h3><div class="rows">{own}</div>'
            f'<h3 style="font:600 13px var(--sans);margin:.8rem 0 .2rem">Kept on Railway for other modules '
            f'<span class="why">(each belongs to its module; its state never colours Railway)</span></h3><div class="rows">{hosted}</div>')


RADIUS_DOWN = """<details><summary>If Railway itself is down: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>The crawler: all gathering (W1–W12)</li><li>W0 Indexer (W0)</li><li>New video copies (they queue)</li></ul></div>
<div class="o"><b>On a backup</b>none</div>
<div class="u"><b>Unaffected</b><ul><li>Bluehost: pages, search, sign-in, payments, the databases</li><li>GitHub: releases, the sync</li><li>Everything already stored</li></ul></div></div></details>"""


def thread(title, status, held, backup, unaff, doing, ours, slots_html, then, details, at='Railway', extra_at=''):
    return f"""<div class="thread" role="region" aria-label="{E(title)}">
<div class="tt">{title}</div>
<div class="status">{status}</div>
<div class="rad"><div class="h"><b>Held</b>{held}</div><div class="o"><b>On a backup</b>{backup}</div><div class="u"><b>Unaffected</b>{unaff}</div></div>
<div class="doing"><b>What the system is doing:</b> {doing}</div>
{act_rows(ours, at, slots_html, then, extra_at)}
<details><summary>Details</summary>{details}</details>
</div>"""


def prov(items):
    return '<div class="sec"><h2>Where each value comes from</h2><ul class="prov">' + ''.join(f'<li>{i}</li>' for i in items) + '</ul></div>'


PROV_RW = [
    'Railway\'s API (GraphQL, read-only, 2026-11-21 15:51 UTC = 8:51 AM MST): project “gracious-sparkle”, services traversence, w0-indexer and video-worker; each one\'s cron, restart rule, root folder, start command, next cron run and latest SUCCESS deploy with its commit. No variables were read; no mutations.',
    'The repo\'s settings: <code>workers/crawler/railway.json</code> (restart Never, no schedule), <code>railway.indexer.json</code> (On failure, 10), <code>workers/video/railway.json</code> (every 5 min, restart Never).',
    'Plan, links, keys and caps: the live <code>service-defs.php</code> (\'railway\', mirror <code>aeb5f31</code>, Sync 14:26 UTC). Portal slots: 02 §8\'s coverage table (Usage → billing, Dashboard → console; keys and sign-in not registered).',
    'Words: 02-state-model.md (§0, §3b, §3c, §4 rows and §4.8 New column, §6, §7). Words the spec doesn\'t have are listed in 03-cards.md, “Open words”.',
    'Railway\'s usage: its API\'s <code>usage</code> query, read-only, since Nov 1 00:00 UTC, read 16:27 UTC (9:27 AM MST): CPU_USAGE, MEMORY_USAGE_GB, NETWORK_TX_GB, NETWORK_RX_GB as given; no disk measures returned. Not priced: no price source is registered.',
    'Times are shown in MST (Arizona), labelled.',
]
