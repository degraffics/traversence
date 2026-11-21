# The Fly.io storefront edge's card (step 3, 03-cards §25): a Host. Static mockups; not site code.
import kit
from cloudflare import *  # every earlier page is generated on import

FY = 'Fly.io'
FYL = dict(dash='https://fly.io/dashboard', bill='https://fly.io/dashboard/personal/billing', tok='https://fly.io/dashboard/traversence/tokens')

kit.SETUP['fly'] = dict(
    provider=FY, notice='Setup capture: Sign in not recorded',
    keys=[('FLY_API_TOKEN', '.env', 'a deploy token for the edge app: the site adds and removes certificates through Fly\'s API'),
          ('FLY_APP, FLY_IPV4, FLY_IPV6', '.env', 'the edge app\'s name (trvgo-edge) and its addresses, shown to businesses as DNS records; not secrets'),
          ('STOREFRONT_EDGE_KEY', '.env and a Fly secret (the same value)', 'signs each visit the edge passes to trvgo.us (X-TV-Edge)'),
          ('DOMAINS_MODE', '.env (Settings)', 'off, test or live: Connect your domain on a storefront')],
    keys_note='Names only. Domain sales (Name.com\'s keys, DOMAIN_SALES_MODE) belong to the Name.com card.',
    slots=[('Billing', 'Billing · Documented · release #212', None), ('Keys', 'Keys · Tokens · Documented · release #97', None),
           ('Console', 'Console · Dashboard · Documented · release #212', None), ('Sign in', 'Not registered', '')])


def fy_slots(fix=None):
    return ('<div class="slots">' + slot('billing', FY, 'Billing', FYL['bill'], fix == 'billing') + slot('keys', FY, 'Tokens', FYL['tok'], fix == 'keys', rel='97')
            + slot('console', FY, 'Dashboard', FYL['dash'], fix == 'console') + slot('login', FY, fix=fix == 'login') + '</div>')


FY_RADIUS = """<details><summary>If the edge is down: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Every storefront on a business\'s own domain: it doesn\'t load</li>
<li><b>Those shops\' trvgo.us addresses too:</b> trvgo.us/&lt;shop&gt; sends visitors on to the domain (a 301, cached 5 minutes), so they land on the outage</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>None today. The source map\'s fallback, “the storefront\'s trvgo.us address still works”, doesn\'t hold while that redirect stands (open word 2)</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>trvgo.us itself: short codes, GO Codes, storefronts without their own domain</li><li>The site: pages, search, sign-in, payments</li></ul></div></div>
<div class="sub">It hosts nothing of its own: no database, no copy. Each visit goes on to trvgo.us on Bluehost, so <a href="bluehost-b-working.html">Bluehost</a> down takes these storefronts down too.</div></details>"""

STAGE_FY = ('<span class="st">Stage: needs live data</span><span class="nx"><code>DOMAINS_MODE</code> off, test or live decides it; '
            'the app trvgo-edge was made 2026-11-02 (<code>fly.toml</code>)</span>')
SPEND_FY = (f'<span class="spend"><span>Per machine and certificate; small while few businesses use their own domain</span><b>use not read yet</b>'
            f'<a href="{FYL["bill"]}" target="_blank" rel="noopener">Billing ↗</a></span>')
CRIT_FY = 'Not critical: no duty stops without it (storefronts on trvgo.us carry on)'


def fy_z2(dom=None, dom_cls=''):
    return rows([
        ('Businesses\' own domains', 'decisions/0139 §3: Connect your domain on a storefront (Core and up)', light('g', 'On · Working'),
         'A domain points at the edge (an A record to its IPv4, or <code>www</code> as a CNAME to <code>trvgo-edge.fly.dev</code>); Caddy passes each visit to <code>trvgo.us</code> with the domain asked for (<code>X-TV-Host</code>) '
         'and our shared key (<code>X-TV-Edge</code>); the router shows that storefront. <code>www.</code> redirects to the bare domain. '
         f'Domains by stage: {NR} (needs live data: <code>business_domains</code>). <a href="#">Custom domains ↗</a> <span class="why">(the admin\'s list, /admin/market/domains)</span>', '', ''),
        ('Certificates (TLS)', 'one per hostname, asked for through Fly\'s API', dom or light('g', 'On · Working'),
         'The job <code>domains_tick</code> (every 15 minutes) checks each waiting domain\'s DNS, then its certificate: Added → DNS pointed → Certificate coming → Live; Failed; Removed. '
         'Live ones are checked once a day; one whose DNS has moved away for 3 days goes to Failed and its storefront back to trvgo.us. Disconnect removes its certificates.', '', dom_cls),
    ])


def fy_z3(dom=None, dom_cls=''):
    return rows([
        ('The edge', 'Fly\'s own health check: <code>GET /__edge</code> every 30 s (answers “ok”); one machine always running (shared-cpu-1x, 256 MB, <code>lax</code>)', '',
         f'{NR} · <b>spec sensor:</b> the board checks <code>https://trvgo-edge.fly.dev/__edge</code> on the diagnostic clock; a failure follows rows 24–26 (none reads it today)', '', ''),
        ('Its certificates', 'the pre-flight reads the app\'s certificate list (read only)', '', dom or f'{NR}', '', dom_cls),
        ('Its key', '<code>FLY_API_TOKEN</code>: a deploy token for trvgo-edge', '', NR, '', ''),
    ]) + '<div class="sub" style="margin-top:.4rem">Deploys: by hand with <code>fly deploy</code> from <code>workers/storefront-edge/</code> (Caddy 2.8); no deploy record is read here.</div>'


def fy_card(state, thread_html=None, fault=None, z2=None, z3=None, note=''):
    return card(FY, 'fly', CRIT_FY, state, STAGE_FY, SPEND_FY + note, FY_RADIUS, z2 or fy_z2(), z3 or fy_z3(),
                act_rows(b('Check now'), FY, fy_slots(), then='Keys · Tokens: the link release #97 added to the pre-flight\'s Fly line (live 2026-11-17).'),
                thread_html, fault, role='Host')


PROV_FY = [
    'The repo: <code>workers/storefront-edge/</code> (<code>fly.toml</code>: app trvgo-edge, lax, one machine always on, the /__edge check every 30 s; <code>Caddyfile</code>: the hand-over to trvgo.us with X-TV-Host and X-TV-Edge; <code>Dockerfile</code>: Caddy 2.8; <code>README.md</code>).',
    'The live code, mirror <code>aeb5f31</code>: <code>Domains.php</code> (stages, <code>check()</code>, <code>certificate()</code> through Fly\'s GraphQL, 3 days before a moved domain fails, “Fly didn\'t answer. It\'s tried again on the next check.”); '
    '<code>Scheduler.php</code> (<code>domains_tick</code>, 15 minutes); <code>storefront-view.php</code> (trvgo.us/&lt;shop&gt; 301s to its own domain); <code>Preflight.php</code> (owndomains need; Fly\'s test); <code>service-defs.php</code> \'fly\' (links Dashboard, Billing).',
    'Keys slot: release #97 (deliveries/releases, live 2026-11-17: “links the traversence organization\'s tokens”). Words: 02 rows 24–26, 49 (one domain is no fault on the edge), 130; §3a, §3b. Scenario values dotted copper. No .env opened; no outside call made.',
]


def fy_cert():
    note = (f'<div class="stage" style="font-size:13px">{light("a", "One domain")} {sc("pinecafe.com")}: its certificate isn\'t ready after {sc("26 hours")} '
            f'(“The certificate: Fly didn\'t take it: {sc("too many certificates requested for this hostname")}”). Only that storefront waits on its trvgo.us address; the edge and every other domain are Working.</div>')
    th = thread(f'{sc("pinecafe.com")}\'s certificate isn\'t ready',
                light('a', 'Warning (that domain)') + f' since {sc("Nov 20, 6:15 AM MST")}: the system asks again at each check (every 15 minutes).',
                '<ul><li>That one domain\'s storefront: it stays on its trvgo.us address until the certificate is ready</li></ul>', 'none',
                '<ul><li>The edge; every other domain; trvgo.us; the site</li></ul>',
                'A domain\'s certificate is its own line: one domain failing is no fault on the edge (row 49\'s shape). Its DNS points here; Fly hasn\'t issued it. '
                'After 24 hours (des) it\'s told to the business\'s team and ours with what Fly said; Fly\'s 5xx is a server answer, tried at the next check (rows 24–26).',
                b('Check this domain now') + b('Acknowledge'),
                fy_slots(fix='console'),
                'Fix it: <b>Fly.io\'s console</b>, the trvgo-edge app\'s certificates, to read why Fly held it; then Check this domain now. The domain\'s own page is on the admin\'s Custom domains list.',
                f'<dl class="dl"><dt>Evidence</dt><dd>the domain\'s row: status “Certificate coming”, last error “{sc("The certificate: Fly didn&#x27;t take it: …")}”</dd>'
                '<dt>Rule</dt><dd>02 row 49\'s shape (a part\'s one item, no fault on the part), 24–26 for Fly\'s 5xx; the 24 hours is des (§25.4, 3)</dd></dl>'
                + today_words(['Today: the domain\'s row keeps “Certificate coming” and its last error, on Custom domains only; there\'s no time limit, and nothing on the board.',
                               '“pinecafe.com\'s certificate isn\'t ready · Warning (that domain): the system asks again at each check.”']), at=FY)
    return fy_card(light('g', 'On · Working') + ' ' + light('a', '1 domain waiting'), th, fault='a', note=note,
                   z2=fy_z2(light('a', 'Warning: 1 domain'), 'hl'),
                   z3=fy_z3(f'{sc("pinecafe.com")}: <b>not issued</b> (Fly: “{sc("too many certificates requested for this hostname")}”)', 'hl'))


add('flyio-a-working.html', 'Fly.io edge card, Working', 'The storefront edge on Fly.io: businesses\' own domains, their certificates, and what an outage reaches.',
    intro('<b>(a) The Fly.io storefront edge, Working.</b> A Host: it serves businesses\' own domains for their storefronts, holding each one\'s certificate and passing each visit to trvgo.us. '
          'If it\'s down, those storefronts don\'t load, and their trvgo.us addresses redirect into the outage; trvgo.us and the site carry on.') + fy_card(light('g', 'On · Working')) + prov(PROV_FY))
add('flyio-b-cert-failing.html', 'Fly.io edge card: a certificate failing', 'One domain\'s certificate not issued: only that storefront waits; the edge stays Working.',
    intro('<b>(b) One domain\'s certificate failing.</b> Its most real fault in the code: a domain stuck at “Certificate coming”. Only that storefront waits, on its trvgo.us address.') + fy_cert() + prov(PROV_FY))
build_index()
