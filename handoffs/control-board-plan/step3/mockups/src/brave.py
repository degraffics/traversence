# Brave Search's card (step 3): a Source, Tavily's backup for web search. Static mockups; not site code.
import kit
from tavily import *  # every earlier page is generated on import

BV = 'Brave Search'
BDASH = 'https://api-dashboard.search.brave.com/'
BTIP = 'Written by a Claude session from Brave\'s own pages'

kit.SETUP['brave'] = dict(
    provider=BV, notice='Setup capture: Sign in not recorded · Billing, Keys: why not recorded',
    keys=[('BRAVE_API_KEY', 'Railway (the crawler service)', 'every Brave search; the worker reports it by name, never its value'),
          ('BRAVE_USD_PER_SEARCH', '.env', 'a search\'s price in the ledger (0.005 by default)')],
    keys_note='No settings of its own: Brave shares the web search allowance with Tavily (<code>websearch_monthly</code>, <code>websearch_stop_pct</code>, <code>worker_searches_per_run</code>, <code>paid_spread</code>). Names only.',
    slots=[('Billing', 'Not registered', ''), ('Keys', 'Not registered', ''), ('Console', 'Console · API dashboard · Documented · release #212', None), ('Sign in', 'Not registered', '')])


def bv_slots(fix=None):
    f = fix == 'console'
    head = '<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if f else ''
    console = (f'<div class="slot{" fix" if f else ""}">{head}<div class="ty">Console · API dashboard</div><a class="pl" href="{BDASH}" target="_blank" rel="noopener">Open {BV} console ↗</a>'
               f'<span class="src" title="{BTIP}">Documented · release #212</span></div>')
    return '<div class="slots">' + slot('billing', BV, fix=fix == 'billing') + slot('keys', BV, fix=fix == 'keys') + console + slot('login', BV, fix=fix == 'login') + '</div>'


BV_RADIUS = """<details><summary>If Brave Search can't be called: its Incident Radius (a Source: reroutes on the source map)</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Brave's calls</li><li>Nothing else while Tavily works: Brave is the second source of each need</li></ul></div>
<div class="o"><b>On a backup</b>none needed while Tavily, the first source, works</div>
<div class="u"><b>Unaffected</b><ul><li>Web search, A missing phone or website, Confirm a business: on Tavily</li><li>Every other source; pages, search, sign-in, payments</li></ul></div></div>
<div class="sub"><b>If Tavily is paused too:</b> Web search (W8, W10–W12) waits (“queued”); A missing phone or website (W2) waits where our copies can't say; Confirm a business (W1, W2) goes to the person queue. Only those tasks, in every workflow that names them (row 45).</div></details>"""

STAGE_BV = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause our calls · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_BVT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>'
SPEND_BV = ('<span class="spend"><span>Brave Search, this month (about $0.005 a search):</span>'
            '<span>its own searches: <b>not read yet</b> (count) · <b>not read yet</b> (dollars)</span><span>Tavily\'s fallbacks (their own line): <b>not read yet</b> (count) · <b>not read yet</b> (dollars)</span>'
            '<span class="why">the cap counts searches, as the code does; the dollars line sits beside it</span>'
            '<span class="why">one shared web search allowance with Tavily; failed calls counted</span><span class="why">Billing: No billing page registered for Brave Search yet.</span></span>')


def bv_z2(over=None):
    over = over or {}
    return rows([
        ('Web search', 'Web search when Tavily\'s key is missing, and the backup on any Tavily failure or hold', over.get('ws', light('g', 'On · Working') + ' <span class="why">standing by: Tavily answers first</span>'),
         'Feeds <b>Web search (W8, W10–W12)</b>, <b>A missing phone or website (W2)</b> and <b>Confirm a business (W1, W2)</b>, each after Tavily. '
         'It\'s the first source only when the crawler has no Tavily key. A search counts once against the shared allowance, whichever engine answers; about one a second.',
         over.get('ws_btn', b('Check now')), over.get('ws_cls', '')),
    ] + over.get('extra', []))


def bv_z3(over=None):
    over = over or {}
    return rows([
        ('The month\'s searches', 'the ledger\'s <code>brave</code>, in two lines: its own searches, and Tavily\'s fallbacks (the worker\'s <code>search_switches</code>)', '', over.get('m', 'not read yet (the ledger is in the database; the step-4 export will carry it)'), '', over.get('m_cls', '')),
        ('Its key on Railway', '<code>BRAVE_API_KEY</code>: the worker\'s key test at its next run', '', over.get('k', light('n', 'Not checked yet') + ' <span class="why">the worker reports presence only today</span>'), '', over.get('k_cls', '')),
        ('Its answers', '401 refused key · 429 rate, or a quota when its answer names the plan · 5xx and timeouts', '', over.get('a', 'not read yet'), '', over.get('a_cls', '')),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


BV_Z4 = act_rows(b('Check now') + b('Pause our calls') + '<span class="why">automatic calls only</span>', BV, bv_slots(),
                 then='After Console → <b>Check now</b>. Keys and Billing aren\'t registered: Publish from your Vault.')


def bv_card(state, thread_html=None, z2o=None, z3o=None, fault=None, stage=STAGE_BV):
    return card(BV, 'brave', 'Not critical: no duty stops without it', state, stage, SPEND_BV, BV_RADIUS, bv_z2(z2o), bv_z3(z3o), BV_Z4, thread_html, fault, role='Source')


UNAFF_BV = '<ul><li>Web search and its needs: on Tavily, their first source</li><li>Every other source; pages, search, sign-in, payments</li></ul>'
UPB2 = '<span class="why">its actions are in the thread above ↑</span>'
PROV_BV = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'brave\' (purpose “The crawler\'s web search when it has a Brave key instead of Tavily\'s.”; plan “Its free plan or paid plan, per search; the site counts each one (about $0.005) inside the same web search allowance as Tavily”; '
    'API dashboard link; key on Railway; ledger brave); <code>CrawlService::PRICES</code> (BRAVE_USD_PER_SEARCH 0.005); <code>Preflight::NEEDS</code> (websearch, contact, confirm); <code>ServiceHealth::classify()</code>; '
    '<code>workers/crawler/crawler.py</code> (Tavily, then Brave; one count per search; <code>search_switches</code>; the key reported by name). Not in <code>ControlRegister::CRITICAL</code>.',
    'Its spend can\'t be read here: the ledger is in the database. The step-4 export (03 §17.6, item 1) carries it, with Tavily\'s fallbacks as their own line (ruling 4).',
    'The slot: release #212 carried <code>service-defs.php</code>; the mirror\'s earliest copy has the API dashboard link (API dashboard → Console, 02 §8). Billing, Keys and Sign in aren\'t registered.',
    'Words: 02 rows 29, 31, 36, 42–46, 106, 128–129; §4.8. Times in MST. No .env was opened; no call was made to Brave or Tavily.',
]


def bv_quota():
    th = thread(f'{BV} used its month at the provider',
                light('r', 'Paused by the system') + ' until its plan\'s month turns; its check runs then.',
                '<ul><li>Brave\'s calls</li></ul>', 'none needed: Tavily, the first source, works', UNAFF_BV,
                'A 429 whose answer names its plan\'s limit is a quota (row 36); any other 429 is a rate wait (row 27). Nothing waits while Tavily works; if Tavily pauses too, the needs wait (page d).',
                b('Check now', True) + b('Acknowledge'),
                bv_slots(fix='billing'),
                f'Fix it: <b>{BV}\'s billing page</b>. No billing page registered for {BV} yet, so Fix it says so and never borrows the API dashboard.',
                f'<dl class="dl"><dt>Evidence</dt><dd>429 with a plan-limit answer at {sc("3:12 PM MST")}, reported by the crawler\'s run {sc("#1360")}</dd>'
                '<dt>Basis</dt><dd>inf: Brave\'s real plan-limit wording isn\'t recorded; step 4 checks the classification on a real response</dd>'
                '<dt>Rule</dt><dd>02 rows 36, 37, 42 (a backup\'s own fault: “Nothing waits on it: its need\'s first choice is working”), 128</dd></dl>'
                + today_words(['Today: Brave\'s own calls go through the worker; its issue reads “…: Over its quota or plan (the crawler, on Railway)” (sim, g2 row 6), and its own calls weren\'t paused (g1 row 19).',
                               f'New: “{BV} used its month at the provider · Paused by the system until its plan\'s month turns.” “Nothing waits on it: its need\'s first choice is working.”']), at=BV)
    return bv_card(light('r', 'On · Paused by the system (its month at Brave)'), th, fault='r', stage=STAGE_BVT,
                   z2o={'ws': light('r', 'Paused by the system') + ' <span class="why">nothing waits on it: Tavily works</span>', 'ws_cls': 'hr', 'ws_btn': UPB2},
                   z3o={'a': f'<b>429, its plan\'s limit</b> at {sc("3:12 PM MST")}', 'a_cls': 'hr'})


def bv_key():
    th = thread(f'{BV} refused our key',
                light('r', 'Paused by the system') + f': calls with BRAVE_API_KEY wait for a new key. Reported by the crawler\'s run {sc("#1361")} at {sc("3:40 PM MST")}.',
                '<ul><li>Calls with <code>BRAVE_API_KEY</code></li></ul>', 'none needed: Tavily, the first source, works', UNAFF_BV,
                'A refused key gets no tries: it waits for a person (rows 29, 31). Nothing else waits while Tavily works.',
                b('Replace key', True) + '<span class="why">on Railway: the crawler service → Variables; its check is the worker\'s key test at its next run</span>' + b('Acknowledge'),
                bv_slots(fix='keys'),
                f'Fix it: <b>{BV}\'s API keys page</b>. No API keys page registered for {BV} yet, so Fix it says so and never borrows the API dashboard. The key itself lives on Railway.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Evidence</dt><dd>401 from api.search.brave.com</dd><dt>Rule</dt><dd>02 rows 29, 31, 42, 106, 129</dd></dl>'
                + today_words(['Board today: “api.search.brave.com: Our key was refused (the crawler, on Railway)”; its check confirms only that the worker has the key (sim, g2 row 2).',
                               f'New: “{BV} refused our key · Paused by the system: calls with BRAVE_API_KEY wait for a new key.”']), at=BV)
    return bv_card(light('r', 'On · Paused by the system (its key)'), th, fault='r', stage=STAGE_BVT,
                   z2o={'ws': light('r', 'Paused by the system') + ' <span class="why">nothing waits on it: Tavily works</span>', 'ws_cls': 'hr', 'ws_btn': UPB2},
                   z3o={'k': f'<b>Refused (401)</b>, reported at {sc("3:40 PM MST")}', 'k_cls': 'hr'})


def bv_both():
    held = ('<ul><li>Web search (W8, W10–W12): its tasks wait (“queued”)</li><li>A missing phone or website (W2): waits where our open-data copies can\'t say</li>'
            '<li>Confirm a business (W1, W2): after our copies, CMS and the Arizona Corporation Commission, it goes to the person queue</li>'
            '<li>Only those tasks, in every workflow the needs name; each task\'s row says “Waiting on Web search: no source works (Tavily paused, Brave Search paused).”</li></ul>')
    th = thread('Web search: no source works (Tavily paused, Brave Search paused)',
                light('h', 'Paused by the system, waiting on its sources') + f' · Tavily: its key refused (IR-####) since {sc("11:20 AM MST")}; Brave Search: its month used (IR-####) since {sc("3:12 PM MST")}.',
                held, 'none: Brave was the last search source; the paid backup for Confirm (<code>po_places_confirm</code>) is off',
                '<ul><li>Every workflow\'s other tasks: W1, W2, W8, W10–W12 read “Partly working”</li><li>Every other source; pages, search, sign-in, payments</li></ul>',
                'Each need\'s first fix is Tavily\'s, its first source. The tasks start again by themselves when either source\'s check passes. No new alarm: the two trips already told Jason, and the need is a roll-up (§2.2).',
                b('Fix it: Tavily\'s key', True) + b('Fix it: Brave\'s month') + '<span class="why"><b>Either fix lifts the hold.</b></span>',
                bv_slots(fix='billing'),
                'Side by side, Tavily first (the need\'s first source): <b>Tavily\'s key</b> (Replace key on Railway; Tavily\'s Keys page isn\'t registered), and '
                '<b>Brave\'s billing page</b> (“No billing page registered for Brave Search yet”). Either fix lifts the hold.',
                '<dl class="dl"><dt>Radius</dt><dd>the tasks that need web search, in every workflow the needs name; never a workflow whole unless every task waits (rows 45, 46; P6)</dd>'
                '<dt>Rule</dt><dd>02 rows 45, 46, 43, 44; §6</dd></dl>'
                + today_words(['Today: “Paused by the system: <task> (<first workflow only>)” (sim, g1 row 18): a need with no source held only the first workflow it named, whole.',
                               'New, on each held task\'s row in every workflow: “Waiting on Web search: no source works (Tavily paused, Brave Search paused).”']), at=BV)
    return bv_card(light('r', 'On · Paused by the system (its month)') + ' <span class="cnt a">Web search: no source works</span>', th, fault='r', stage=STAGE_BVT,
                   z2o={'ws': light('r', 'Paused by the system') + ' <span class="why">and Tavily is paused: web search waits</span>', 'ws_cls': 'hr', 'ws_btn': UPB2})


add('brave-a-working.html', 'Brave Search card, Working', 'Brave Search, a Source and Tavily\'s backup: its spend with the fallbacks as their own line.',
    intro('<b>(a) Brave Search\'s card, Working.</b> A Source, the second for each web search need, after Tavily. Its key lives on Railway (reported by name only). '
          'Its spend has two lines: its own searches, and Tavily\'s fallbacks (ruling 4); neither is readable here.') + bv_card(light('g', 'On · Working')) + prov(PROV_BV))
add('brave-b-quota.html', 'Brave Search card: its month used', 'Brave\'s plan limit: Brave paused, nothing waits while Tavily works.',
    intro('<b>(b) Its own quota (rows 36, 42).</b> A backup\'s own fault: only Brave\'s calls are held; nothing waits while Tavily, the first source, works.') + bv_quota() + prov(PROV_BV))
add('brave-c-key-refused.html', 'Brave Search card: key refused', 'BRAVE_API_KEY refused: only Brave\'s calls wait; Fix it names Keys (not registered).',
    intro('<b>(c) A refused key (rows 29, 31, 42).</b> Only Brave\'s calls wait; Fix it lands on Keys, which isn\'t registered, and says so.') + bv_key() + prov(PROV_BV))
add('brave-d-both-down.html', 'Web search: both sources down', 'Tavily and Brave both paused: only the tasks that need web search wait.',
    intro('<b>(d) Both search sources down (rows 45, 46).</b> Tavily is paused (its key) and Brave is paused (its month): the need “Web search” has no working source, so the tasks that need it wait, '
          'in every workflow that names them. Nothing else is held.') + bv_both() + prov(PROV_BV))
build_index()
