# Google's cards (step 3): Google Places (a Source), Google Cloud (a Service) and Google AI Studio (Gemini, a Service).
# Jason (2026-11-21) split Places out of the live code's 'google' module; one Google billing account links both. Static mockups.
import kit
from anthropic import *  # every earlier page is generated on import

GP, GC, GM = 'Google Places', 'Google Cloud', 'Google AI Studio (Gemini)'
GTIP = 'Written by a Claude session from Google\'s own pages'
BUDGET = '<span class="why">Google\'s own budget alert: $10 (set at Google, not read here)</span>'
L = dict(gc_console='https://console.cloud.google.com/', gc_billing='https://console.cloud.google.com/billing',
         gc_cred='https://console.cloud.google.com/apis/credentials', gc_docai='https://console.cloud.google.com/ai/document-ai',
         gm_studio='https://aistudio.google.com/', gm_keys='https://aistudio.google.com/apikey', gm_rate='https://ai.google.dev/gemini-api/docs/rate-limits')
GSLOTS = [('Billing', 'Billing · Documented · release #212 (Google\'s one billing account, shared)', None), ('Keys', 'Keys · Credentials · Documented · release #212', None),
          ('Console', 'Console · Documented · release #212', None), ('Sign in', 'Not registered', '')]

kit.SETUP['places'] = dict(
    provider=GP, notice='Setup capture: Sign in not recorded',
    keys=[('GOOGLE_PLACES_KEY', 'Railway (the crawler service)', 'every Places check; the worker reports it by name'),
          ('GOOGLE_PLACES_MONTHLY', 'Railway (retired)', 'the month\'s checks: now the setting places_monthly'),
          ('GOOGLE_PLACES_USD_PER_CALL', '.env', 'a check\'s price in the ledger (0.032 by default)'),
          ('PLACES_STOP_PCT', '.env (retired)', 'now the setting places_stop_pct')],
    keys_note='Settings, not keys: <code>worker_places_per_run</code>, <code>places_monthly</code>, <code>places_stop_pct</code>, <code>paid_spread</code>, <code>po_places_confirm</code>. Names only.',
    slots=GSLOTS)
kit.SETUP['google'] = dict(
    provider=GC, notice='Setup capture: Sign in not recorded',
    keys=[('GOOGLE_DOCAI_KEY_FILE, GOOGLE_DOCAI_PROCESSOR', '.env', 'reading menus (Document AI)'),
          ('GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET', '.env', 'Continue with Google (sign-in)')],
    keys_note='Places moved to its own card (Jason, 2026-11-21), with its key and caps. Names only.',
    slots=GSLOTS)
kit.SETUP['gemini'] = dict(
    provider=GM, notice='Setup capture: Sign in not recorded · Billing: why not recorded',
    keys=[('GEMINI_API_KEY', '.env (whether it\'s set isn\'t known here; .env is never opened)', 'every Gemini call (the free tier, no billing linked)')],
    keys_note='Settings, not keys: <code>gemini_daily</code>, <code>gemini_per_minute</code>, <code>gemini_reserve_pct</code>. Its “Rate limits” page is documentation: it sits in zone 2, not in a slot (04 §5).',
    slots=[('Billing', 'Not registered', 'the free tier has no billing linked'), ('Keys', 'Keys · Documented · release #212', None),
           ('Console', 'Console · AI Studio · Documented · release #212', None), ('Sign in', 'Not registered', '')])


def link(ty, head, url, prov, fx=False):
    btn = {'billing': 'billing', 'keys': 'API keys', 'console': 'console'}[ty]
    f = '<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if fx else ''
    return (f'<div class="slot{" fix" if fx else ""}">{f}<div class="ty">{head}</div><a class="pl" href="{url}" target="_blank" rel="noopener">Open {prov} {btn} ↗</a>'
            f'<span class="src" title="{GTIP}">Documented · release #212</span></div>')


def g_slots(prov, fix=None):
    return ('<div class="slots">' + link('billing', 'Billing', L['gc_billing'], prov, fix == 'billing') + link('keys', 'Keys · Credentials', L['gc_cred'], prov, fix == 'keys')
            + link('console', 'Console', L['gc_console'], prov, fix == 'console') + slot('login', prov, fix=fix == 'login') + '</div>')


def gc_slots(fix=None):
    return g_slots(GC, fix)


def gp_slots(fix=None):
    return g_slots(GP, fix)


def gm_slots(fix=None):
    return ('<div class="slots">' + slot('billing', GM, fix=fix == 'billing') + link('keys', 'Keys', L['gm_keys'], GM, fix == 'keys')
            + link('console', 'Console · AI Studio', L['gm_studio'], GM, fix == 'console') + slot('login', GM, fix=fix == 'login') + '</div>')


def rows(items):
    out = ''
    for n, sub, st, body, btn, cls in items:
        out += (f'<div class="row{" " + cls if cls else ""}"><div><div class="nm">{n}</div><div class="sub">{sub}</div>{st}</div>'
                f'<div>{body}</div><div class="btns">{btn}</div></div>')
    return '<div class="rows">' + out + '</div>'


def card(name, mod, crit, state, stage, spend, radius, z2, z3, z4=None, thread_html=None, fault=None, role='Service'):
    z1 = zone(1, 'Identity and role', f"""<div class="idl"><a class="name" href="#">{name}</a><span class="badge role">{role}</span><span class="badge crit">{crit}</span>{state}</div>
<div class="stage">{stage}</div>{setup_notice(mod)}<div class="stage">{spend}</div>{radius}{setup_details(mod)}""")
    a = zone(2, 'Capabilities and feeds: what uses it', z2)
    s = zone(3, 'Sensors and key readiness', z3)
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + a + s
    else:
        body = z1 + a + s + zone(4, 'Actions and Fix it', z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="{name}">{body}</article>'


STAGE5 = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause our calls · Raise or lower our cap · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE5S = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE5T = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>'

# ---------------- Google Places (a Source) ----------------
GP_RADIUS = """<details><summary>If Google Places can't be called: its Incident Radius (a Source: reroutes on the source map)</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Places checks (the crawler's, on Railway)</li><li>Still open? (W2), only where our copies can't say: Places is its last source, so that part waits</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>Still open? (W2): our copies' open or closed status, its first source</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Confirm a business (W1, W2): Places is only its paid backup, off at 0</li><li>Google Cloud (Document AI, sign-in) and Gemini: their own keys</li><li>Pages, search, payments; the rest of every workflow</li></ul></div></div></details>"""


def gc_spend(n, real=True, warn=False):
    pct = round(n / 1000 * 100)
    nn, pp = (f'{n:,}', f'{pct}%') if real else (sc(f'{n:,}'), sc(f'{pct}%'))
    when = '<span class="why">(the live reading, Nov 21, 3:19 AM MST)</span>' if real else '<span class="why">(illustrative)</span>'
    return (f'<span class="spend{" warn" if warn else ""}"><span>Google Places check:</span><b>{nn} of 1,000 checks ({pp})</b>{when}'
            f'<span class="why">about $0.032 a check</span><a href="{L["gc_billing"]}" target="_blank" rel="noopener">Billing ↗</a>{BUDGET}</span>')


def gp_z2(over=None):
    over = over or {}
    return rows([
        ('Places check', 'the crawler on Railway, with <code>GOOGLE_PLACES_KEY</code>', over.get('places', light('g', 'On · Working')),
         'At most 5 a run, 1,000 a month, stopping at 90% and paced over the month. Defaults from the code; the live settings: not read yet.',
         over.get('places_btn', b('Check now')), over.get('places_cls', '')),
        ('Still open? (W2)', 'the need: our copies first, then Google Places', over.get('still', light('g', 'On · Working')),
         'Places answers where our copies can\'t say whether a business is still open; with neither, that part waits.', '', over.get('still_cls', '')),
        ('Confirm a business (W1, W2)', 'the need: a paid backup only', light('n', 'Off (po_places_confirm at 0)'),
         'A director\'s override: only when the free sources can\'t confirm it.', '', ''),
    ])


def gp_z3(over=None):
    over = over or {}
    return rows([
        ('The month\'s checks', 'the ledger\'s <code>google_places</code> against <code>places_monthly</code>', '', over.get('m', '544 of 1,000 checks (54%), the live reading at 3:19 AM MST'), '', over.get('m_cls', '')),
        ('Its key on Railway', '<code>GOOGLE_PLACES_KEY</code>: the worker\'s key test at its next run', '', over.get('k', 'not checked yet (the worker reports at its next run)'), '', over.get('k_cls', '')),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


GP_Z4 = act_rows(b('Check now') + b('Pause our calls') + '<span class="why">automatic calls only</span>' + b('Raise the cap') + b('Lower the cap'), GP, gp_slots(),
                 then='After Billing → <b>Check now</b>; after Keys → <b>Replace key</b> (on Railway), then the worker\'s key test.')


def gp_card(state, spend, thread_html=None, z2o=None, z3o=None, fault=None, stage=STAGE5):
    return card(GP, 'places', 'Not critical: no duty stops without it', state, stage, spend, GP_RADIUS, gp_z2(z2o), gp_z3(z3o), GP_Z4, thread_html, fault, role='Source')


# ---------------- Google Cloud (a Service) ----------------
GC_RADIUS = """<details><summary>If Google Cloud can't be called: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Reading menus through Document AI</li><li>Continue with Google</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>Reading menus: Amazon (Textract), then Claude</li><li>Sign-in: email and password (the critical duty), Microsoft, Apple, Facebook</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Pages, search, payments</li><li>Google Places and Gemini: their own keys</li></ul></div></div></details>"""
GC_SPEND = (f'<span class="spend"><span>Document AI: per page</span><b>use not read yet</b><span>Sign-in: free</span>'
            f'<a href="{L["gc_billing"]}" target="_blank" rel="noopener">Billing ↗</a>{BUDGET}</span>')


def gc_z2(over=None):
    return rows([
        ('Reading menus (Document AI)', 'the site, with <code>GOOGLE_DOCAI_KEY_FILE</code>', light('n', 'not read yet'),
         f'Feeds menus on listings: Google first, then Amazon (Textract), then Claude. Its page: <a href="{L["gc_docai"]}" target="_blank" rel="noopener">Document AI ↗</a> (not a portal slot).', '', ''),
        ('Continue with Google', 'the site, with <code>GOOGLE_CLIENT_ID</code>', light('n', 'not read yet'),
         'Feeds sign-in, one of four providers. Not critical: sign-in\'s critical duty is email and password. Free.', '', ''),
        ('Videos in journeys', 'YouTube embeds (youtube-nocookie.com)', light('g', 'On · Working'), 'Feeds journeys\' videos after Vimeo; otherwise a link out.', '', ''),
    ])


def gc_z3(over=None):
    return rows([('Document AI and sign-in', 'their keys set', '', 'not read yet', '', '')]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


GC_Z4 = act_rows(b('Check now') + b('Take out'), GC, gc_slots(), then='After Keys → <b>Replace key</b>, then its check. Only the board\'s check changes state.')


def gc_card(state, spend=GC_SPEND, thread_html=None, fault=None, stage=STAGE5S):
    return card(GC, 'google', 'Not critical: sign-in\'s critical duty is email and password', state, stage, spend, GC_RADIUS, gc_z2(), gc_z3(), GC_Z4, thread_html, fault)


# ---------------- Google AI Studio (Gemini) ----------------
GM_RADIUS = """<details><summary>If Gemini can't be called at all: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Page prose into facts (W1–W3, W10, W11): “waiting to be read; nothing lost”</li><li>Semantic search's vectors (off today)</li></ul></div>
<div class="o"><b>On a backup</b>only under a director's override: Claude reads pages (<code>po_claude_prose</code>, off at 0); no fallback to Claude otherwise</div>
<div class="u"><b>Unaffected</b><ul><li>Every Claude feature</li><li>Search uses keyword search</li><li>Pages, sign-in, payments; Google Places and Google Cloud</li></ul></div></div></details>"""
GM_STAGE_TODAY = '<span class="st">Stage 2 of 8 · Integration (pre-flight)</span><span class="nx">Next allowed: Run pre-flight · Edit · Activate (offered once its key is checked and its pre-flight passes)</span>'
GM_GUARD = ('<span class="spend"><span>Free ($0) on its free tier</span><b>Nothing private goes to it</b><span class="why">only public pages, cleaned first (decisions/0164 R5, §0.8)</span>'
            '<span>Calls: <b>{today}</b> of 1,000 today · 15 a minute · 20% held for visitors waiting</span></span>')


def gm_z2(over=None):
    over = over or {}
    return rows([
        ('Page prose into facts', 'the need, in W1–W3, W10, W11', over.get('prose', light('n', 'Off (not called yet)')),
         'Gemini first, then Claude only under a director\'s override (<code>po_claude_prose</code>, off at 0); with neither, pages wait “to be read; nothing lost”.', '', over.get('prose_cls', '')),
        ('Semantic search', 'the need, in W3', light('n', 'Off (the vector index is off)'), 'Supabase, then Gemini\'s free embeddings; without them, keyword search.', '', ''),
        ('What may go to it', 'the privacy guard (<code>Gemini::refused</code>)', over.get('guard', light('g', 'On · Working')),
         'Only features whose input is a public page: Read a public page into facts, Read a page through Tavily, Source terms, Read a scanned PDF, Live events, the vector index. '
         'Research, archives, a listing, a person\'s own words and private material stay on Claude.', over.get('guard_btn', ''), over.get('guard_cls', '')),
        ('Its limits', 'documentation, not a portal slot (04 §5)', '', f'<a href="{L["gm_rate"]}" target="_blank" rel="noopener">Rate limits ↗</a> · ours: <code>gemini_daily</code> 1,000, <code>gemini_per_minute</code> 15, <code>gemini_reserve_pct</code> 20%. When our own limits stop background work (the 80% reserve, or the day\'s pace), it\'s a Notice.', '', ''),
    ])


def gm_z3(over=None):
    over = over or {}
    return rows([
        ('Calls today, this minute', 'every call counted, failed calls included ($0 on the free tier)', '', over.get('calls', 'none yet: not called'), '', over.get('calls_cls', '')),
        ('Removed before sending', 'R6\'s private details cut from each page (<code>Gemini::safe</code>), counted by kind', '', over.get('safe', 'none yet: not called'), '', ''),
        ('Refused by the guard', 'a feature that isn\'t public, kept on Claude', '', over.get('ref', 'none'), '', over.get('ref_cls', '')),
        ('Its key', '<code>GEMINI_API_KEY</code> in .env: one authenticated call; whether it\'s set isn\'t known here', '', over.get('key', light('n', 'Not checked yet')), '', ''),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


GM_Z4 = act_rows(b('Run pre-flight') + b('Activate', off='offered once its pre-flight passes (R57)'), GM, gm_slots(),
                 then='After Keys → <b>Replace key</b>, then its check. Billing: none linked on the free tier.')


def gm_card(state, stage, calls, thread_html=None, z2o=None, z3o=None, fault=None, z4=GM_Z4):
    return card(GM, 'gemini', 'Not critical: no duty stops without it', state, stage, GM_GUARD.format(today=calls), GM_RADIUS, gm_z2(z2o), gm_z3(z3o), z4, thread_html, fault)


PROV_G = [
    'Jason (2026-11-21): Places is split out of the live code\'s \'google\' module as its own Source module; the code\'s module boundary changes in step 4. One Google billing account links both. '
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'google\' and \'gemini\' (purposes, plans, links, keys, caps; Gemini “Not called yet”, added off); '
    '<code>Preflight.php</code> NEEDS (stillopen, confirm, prose, semantic, signin, videos); <code>Gemini.php</code> (<code>refused()</code>, <code>safe()</code>, <code>limits()</code>, <code>room()</code>, the 429 words); '
    '<code>AiClient::INPUT</code>, <code>INPUT_CLASSES</code>, <code>ROUTES</code> (menu: google, aws, claude); <code>GoogleAuth.php</code>; <code>Thresholds.php</code> (places_monthly 1,000, places_stop_pct 90, worker_places_per_run 5, gemini_daily 1,000, gemini_per_minute 15, gemini_reserve_pct 20); '
    '<code>CrawlService</code> (GOOGLE_PLACES_USD_PER_CALL, 0.032); <code>workers/crawler/crawler.py</code> (GOOGLE_PLACES_KEY on Railway, reported by name). None is in <code>ControlRegister::CRITICAL</code>.',
    'The live reading: <code>alarms.json</code> at 10:19 UTC (3:19 AM MST): <code>ops:cap:google</code>, a warning since Nov 19, 3:21 PM MST: 544 of 1,000 Places checks. Shown by BL: round(544 ÷ 1,000 × 100) = 54%.',
    'The slots: release #212 carried <code>service-defs.php</code>; the mirror\'s earliest copy has every documented link. Credentials → Keys, AI Studio → Console (04 §5); Document AI and Rate limits aren\'t slots.',
    'Words: 02 rows 27, 29, 31, 36, 39, 106, 128–130; §4.8. Times in MST. No .env was opened; no call was made to Google.',
]


def gm_today():
    return gm_card(light('n', 'Off · added, not called yet'), GM_STAGE_TODAY, 'none')


# (a) working
add('google-a-working.html', 'Google cards, Working', 'Google Places and Google Cloud working, and Gemini as it is today: added, off, not called yet.',
    intro('<b>(a) Google: three modules.</b> Google Places, a Source (Jason, 2026-11-21: split out of the live code\'s \'google\' module), Working, its count <b>illustrative</b> '
          '(today\'s real reading is past 50%: page b). Google Cloud, a Service (Document AI, Continue with Google). Google AI Studio (Gemini) as it really is today: added, off, '
          'not called yet, its key not checked yet (stage 2). Places and Cloud share Google\'s one billing account.')
    + gp_card(light('g', 'On · Working'), gc_spend(312, real=False)) + gc_card(light('g', 'On · Working')) + gm_today() + prov(PROV_G))


# (b) a quota: the real Places reading, and Gemini's day at the provider
def gc_near_cap():
    th = thread('Google Places check: 544 of 1,000 checks (54%)',
                light('a', 'Warning (Near its cap)') + ' · 544 of 1,000 checks (54%), past its 50% warning. Since Nov 19, 3:21 PM MST.',
                'nothing', 'none', '<ul><li>Checks go on, paced over the month, stopping at 90%</li></ul>',
                'Reads the month\'s checks at each watch. The band reached decides the level; the number shown is round(checks ÷ cap × 100), the same in the title and here.',
                b('Raise the cap') + b('Lower the cap') + b('Pause our calls') + '<span class="why">automatic calls only</span>' + b('Check now', True),
                gp_slots(fix='billing'),
                f'Fix it: <b>{GP}\'s billing page</b> (Google\'s one billing account), then <b>Check now</b>. A board action changes our cap only, never Google\'s.',
                '<dl class="dl"><dt>Computed</dt><dd>round(544 ÷ 1,000 × 100) = 54 → band 50 → Warning; about $17.41 at $0.032 a check (inf)</dd><dt>Rule</dt><dd>02 BL; row 39; row 128</dd></dl>'
                + today_words(['Title “Google Cloud at 50%” (the band reached), reading “54%”, means “Google Places check: 544 of 1,000 checks (54%).” (obs, the live alarm).',
                               'New: one title and one figure, “Google Places check: 544 of 1,000 checks (54%)”, on Google Places\' own card.']), at=GP)
    return gp_card(light('a', 'On · Warning (Near its cap)'), gc_spend(544, warn=True), th, fault='a', z3o={'m': '<b>544 of 1,000 checks (54%)</b>, past its 50% warning', 'm_cls': 'hl'})


def gm_day():
    th = thread(f'{GM} used its day at the provider',
                light('r', 'Paused by the system') + ' until 00:00 Pacific; its check runs then.',
                '<ul><li>Gemini\'s calls: pages wait “to be read; nothing lost”</li></ul>', 'none (no fallback to Claude; only a director\'s override)',
                '<ul><li>Every Claude feature</li><li>Google Cloud</li><li>Everything else</li></ul>',
                'Google\'s per-day RESOURCE_EXHAUSTED is a quota with a known reset: the check runs at the reset, and closes it as Recovered when it passes.',
                b('Check now', True),
                gm_slots(fix='billing'),
                f'Fix it: <b>{GM}\'s billing page</b>. No billing page registered for {GM} yet (the free tier has none linked), so Fix it says so and never borrows Keys or AI Studio.',
                '<dl class="dl"><dt>Scenario</dt><dd>as it would read once Gemini is active (today it isn\'t called)</dd><dt>Per minute</dt><dd>a per-minute “too many” is a rate wait, never a trip (row 27)</dd>'
                '<dt>Rule</dt><dd>02 rows 27, 36, 37, 128; Q13</dd></dl>'
                + today_words(['Code: “Gemini\'s allowance answered “too many”: the work waits.” and “Gemini\'s 1000 calls for today are used: the work waits until tomorrow (no fallback to Claude).” (obs, <code>Gemini.php</code>).',
                               'Board today for a quota: “…: Over its quota or plan (the site, on Bluehost)”, held for good (sim, g2 row 6).']), at=GM)
    return gm_card(light('r', 'On · Paused by the system (its day at Google)'), f'{sc("Stage 5 of 8 · Operational")}<span class="nx">(once active; today stage 2)</span>',
                   sc('1,000'), th, z2o={'prose': light('h', 'Held (waiting on Gemini)'), 'prose_cls': 'hl'}, z3o={'calls': f'<b>Google answered RESOURCE_EXHAUSTED (per day)</b> at {sc("4:40 PM MST")}', 'calls_cls': 'hr'}, fault='r')


add('google-b-quota.html', 'Google cards: a quota', 'The real Places reading near its cap on its own card, and Gemini\'s day used at the provider.',
    intro('<b>(b) A quota.</b> First, today\'s real reading: Google Places at 544 of 1,000 checks (54%), past its 50% warning (row 39). Second, Gemini\'s per-day quota used at Google '
          '(row 36), drawn as it would read once Gemini is active (dotted).') + gc_near_cap() + gm_day() + prov(PROV_G))


# (c) a refused key: GOOGLE_PLACES_KEY on Railway
def gc_key():
    th = thread(f'{GP} refused our key',
                light('r', 'Paused by the system') + f': calls with GOOGLE_PLACES_KEY wait for a new key. Reported by the crawler\'s run {sc("#1342")} at {sc("2:05 PM MST")}.',
                '<ul><li>Places checks (calls with <code>GOOGLE_PLACES_KEY</code>)</li></ul>',
                '<ul><li>Still open? (W2): our copies answer first; where they can\'t, it waits</li></ul>',
                '<ul><li>Google Cloud (Document AI, Continue with Google) and Gemini: their own keys</li><li>Confirm a business (its paid backup is off)</li><li>Everything else</li></ul>',
                'A refused key gets no tries: it waits for a person (rows 29, 31). A Source: each need goes to its next source on the map, or waits.',
                b('Replace key', True) + '<span class="why">on Railway: the crawler service → Variables; its check is the worker\'s key test at its next run</span>' + b('Acknowledge'),
                gp_slots(fix='keys'),
                f'Fix it: <b>{GP}\'s API keys page</b> (Credentials), registered, then replace the key on Railway and let the worker test it.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Told</dt><dd>the Founder: Pulse, email, push</dd><dt>Rule</dt><dd>02 rows 29, 31, 106, 129</dd></dl>'
                + today_words(['Board today: “places.googleapis.com: Our key was refused (the crawler, on Railway)”; its check confirms only that the worker has the key (sim, g2 row 2).',
                               f'New: “{GP} refused our key · Paused by the system: calls with GOOGLE_PLACES_KEY wait for a new key.”']), at=GP)
    return gp_card(light('r', 'On · Paused by the system (its key)'), gc_spend(544, warn=True), th, fault='r', stage=STAGE5T,
                   z2o={'places': light('r', 'Paused by the system'), 'places_cls': 'hr', 'places_btn': '<span class="why">its actions are in the thread above ↑</span>',
                        'still': light('a', 'On a backup: our copies only') + ' <span class="why">where they can\'t say, it waits</span>', 'still_cls': 'hl'},
                   z3o={'k': f'<b>Refused (403)</b>, reported at {sc("2:05 PM MST")}', 'k_cls': 'hr'})


add('google-c-key-refused.html', 'Google Places: key refused', 'GOOGLE_PLACES_KEY refused: only Places checks wait; Fix it goes to Keys (Credentials).',
    intro('<b>(c) A refused key on Google Places\' card (rows 29, 31).</b> <code>GOOGLE_PLACES_KEY</code> lives on Railway and the worker reports it. Only Places checks wait; Fix it lands on Keys · Credentials.') + gc_key() + prov(PROV_G))


# (d) the free-tier privacy guard
def gm_guard():
    th = thread('Kept on Claude: this carries a person\'s own words (nothing private to Gemini\'s free tier)',
                light('n', 'Notice') + f' · Names in journeys and stories, {sc("2:31 PM MST")}: AI use asked to move it to Gemini. Nothing was sent to Google. No message.',
                'nothing: the feature runs on Claude as before', 'none', '<ul><li>Everything</li></ul>',
                'The guard working, not a fault: Gemini\'s free tier lets Google use what it\'s sent, so only public pages go to it. No message is sent.',
                b('Check now'),
                gm_slots(),
                'No Fix it: nothing is wrong. Keep the feature on Claude in AI use.',
                '<dl class="dl"><dt>Why</dt><dd><code>AiClient::INPUT</code> marks Names in journeys and stories (<code>content_names</code>) as “a person\'s own words, which go only to Claude, with their consent (R5)”</dd>'
                f'<dt>Also guarded</dt><dd>public pages are cleaned before sending: {sc("2 emails")} and {sc("1 sentence about a private person")} removed from today\'s pages (scenario)</dd>'
                '<dt>Rule</dt><dd>CLAUDE.md and decisions/0164 R5, §0.8 (“nothing private to Gemini\'s free tier”); 02 row 37a: a Notice on Gemini\'s card only, no message</dd></dl>'
                + today_words(['Code: “Gemini\'s free tier lets Google use what it\'s sent, so only public pages go to it (decisions/0164 §0.8); this feature carries a person\'s own words, which go only to Claude, with their consent (R5), so it stays on Claude.” (obs, <code>Gemini::refused()</code>), returned to the caller only.']), at=GM)
    return gm_card(light('g', 'On · Working') + ' <span class="cnt">Notice: a private feature kept on Claude</span>', f'{sc("Stage 5 of 8 · Operational")}<span class="nx">(once active; today stage 2)</span>',
                   sc('212'), th, z2o={'guard': light('n', 'Notice: 1 kept on Claude'), 'prose': light('g', 'On · Working') + ' <span class="why">(scenario: once active)</span>'}, z3o={'ref': f'<b>{sc("1")}: Names in journeys and stories</b> (a person\'s own words)', 'safe': f'{sc("2 emails, 1 private person")} today'})


add('google-d-privacy-guard.html', 'Gemini: the privacy guard', 'The free-tier guard keeps a private feature on Claude: a Notice, nothing sent to Google.',
    intro('<b>(d) The free-tier privacy guard.</b> “Nothing private to Gemini\'s free tier” (0164). A feature carrying a person\'s own words is asked onto Gemini; the guard keeps it on Claude. '
          'Drawn as it would read once Gemini is active (dotted).') + gm_guard() + prov(PROV_G))
build_index()
