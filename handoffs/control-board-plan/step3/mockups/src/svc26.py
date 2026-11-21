# The 26 services the needs name with no card yet (03 §26): their records, cards for the interactive board, and §26's text.
# Read from the live mirror (aeb5f31): service-defs.php, Preflight::NEEDS, Automation::WORKFLOWS and each service's callers.
# Static design data; not site code.
import html, re
import kit

E = html.escape
MIRROR = '/home/user/traverse-3way-sync/'
SD = open(MIRROR + 'sync-app/lib/service-defs.php').read()
Q = r"'((?:[^'\\]|\\.)*)'"


def unq(s):
    return s.replace("\\'", "'")


def defs(k):
    m = re.search(r"\n    '" + k + r"' => \[(.*?)\n    \],", SD, re.S)
    b = m.group(1)
    c = re.search(r"'company' => \[" + Q + r",\s*" + Q + r",\s*" + Q + r",\s*\[(.*?\])\],", b, re.S)
    links = [(unq(x), unq(u)) for x, u in re.findall(r"\[" + Q + r", " + Q + r"\]", c.group(4))]
    km = re.search(r"'keys' => \[(.*?\])\],", b, re.S)
    keys = [(unq(x), unq(u)) for x, u in re.findall(r"\[" + Q + r", " + Q + r"\]", km.group(1))] if km else []
    hosts = re.findall(Q, (re.search(r"'hosts' => \[(.*?)\]", b) or re.search('()', '')).group(1))
    caps = re.findall(Q, (re.search(r"'caps' => \[(.*?)\]", b) or re.search('()', '')).group(1))
    return dict(name=unq(c.group(1)), purpose=unq(c.group(2)), plan=unq(c.group(3)), links=links, keys=keys, hosts=hosts, caps=caps, code_keys=CODE_KEYS.get(k, []),
                staging=(re.search(r"'staging' => '(\w+)'", b) or re.search('()', '')).group(1), added_off="'added_off' => true" in b)


CODE_KEYS = {  # setting names the callers read that service-defs.php doesn't list (obs, the callers); names only
    'stripe': [('STRIPE_CLIENT_ID, STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET', '.env (MarketPay.php)'), ('MARKET_PAY_ENV', '.env: live, sandbox or mock')],
    'square': [('SQUARE_APP_ID, SQUARE_APP_SECRET, SQUARE_WEBHOOK_KEY', '.env (MarketPay.php)'), ('MARKET_PAY_ENV', '.env: live, sandbox or mock')],
    'apple': [('APPLE_CLIENT_ID', '.env (SocialAuth.php)')],
    'meta': [('FACEBOOK_APP_ID, FACEBOOK_APP_SECRET, FACEBOOK_GRAPH_VERSION', '.env (SocialAuth.php)')],
    'microsoft': [('MICROSOFT_CLIENT_ID, MICROSOFT_TENANT', '.env (SocialAuth.php: sign-in)'), ('MS_MAIL_CLIENT_ID, MS_MAIL_CLIENT_SECRET', '.env (MsGraphMail.php: mail)')],
    'heigit': [('ORS_API_URL', '.env (TravelLegs.php, optional)')],
}


def slot_type(label):
    l = label.lower()
    if 'policy' in l or 'overpass' in l:
        return None
    if re.search(r'billing|usage|limits', l):
        return 'billing'
    if re.search(r'\bkeys?\b|credentials|api settings', l):
        return 'keys'
    if re.search(r'dashboard|console', l):
        return 'console'
    if re.search(r'\baccount\b', l):
        return 'login'
    return None


# role · status (obs from callers) · stage drawn · callers · open words · scenarios (real faults from the alarms snapshot)
OPEN = 'Open data: no account'
S = [
    ('census', 'Source', 'live', 5, 'Geocoder.php, PlaceProfile.php, ZipPopulation.php, admin/cluster-tools.php; the worker\'s Census backup (crawler.py)', True,
     ['Is `CENSUS_API_KEY` set (the data API\'s higher daily limit)? Needs live data.', 'Issue #18 has been a warning since Nov 18: under the contract a connection reset is a server answer (rows 24–26), tried, then a trip; the worker takes the batch (decisions/0175\'s note). Is it still open?']),
    ('commoncrawl', 'Source', 'live', 5, 'the worker (crawler.py: the index and the columnar reads)', True,
     ['#35 is a trip since Nov 20 (504 at index.commoncrawl.org) and holds W3 whole by its gate today; under the contract only the work that needs it waits (Sources read regularly, Re-read websites: the crawler reading directly first).', '#22 reads "Its server failed" with a 503 SlowDown: that\'s a rate wait (row 27), the L12 finding.']),
    ('wikimedia', 'Source', 'live', 5, 'Wdqs.php, Dossier.php, Identity.php, PlaceFinder.php, crawler/Sources.php, stock/StockWikimedia.php and more (16 files)', True,
     ['#26: a 429 since Nov 18, a rate wait (row 27), never a trip. Words today already say so.', 'Wikipedia is cite only (R9): its card says so, and nothing reads it in bulk.']),
    ('loc', 'Source', 'built', None, 'Heritage.php, stock/StockLoc.php, crawler/Sources.php, the worker', False, ['Its calls this month: needs live data.']),
    ('nps', 'Source', 'built', None, 'Dossier.php (NPGallery linked, not called); StockLibrary.php (photos, with the optional key)', False, ['Is `NPS_API_KEY` set? Needs live data.']),
    ('ridb', 'Source', 'built', None, 'the worker (the monthly load), crawler/Sources.php', False, ['`RIDB_API_KEY` on Railway: reported by the worker? Its last load (`rec_loads`): needs live data.']),
    ('usgs', 'Source', 'live', 5, 'js/map-view.js and listing/location.php (tiles in browsers); Identity.php; the worker (GNIS files)', False, ['The tiles load in visitors\' browsers: no check of ours reads them (like cdnjs, a step-4 rendered check).']),
    ('osm', 'Source', 'live', 5, 'map tiles in browsers; our copy is Geofabrik\'s card (§22.3); Overpass retired 2026-11-19', False,
     ['Two parts: the tiles (browsers, light use under its tile policy) and our copy (Geofabrik\'s card). One card with the tiles only, linking Geofabrik for the data?']),
    ('azcc', 'Source', 'planned', 1, 'none: W14\'s licence_lookup isn\'t in the worker yet', False, ['Its real use (bulk, lookup only, blocked, claim-time) is recorded by W14 once built (decisions/0164 §1.5).']),
    ('nmsos', 'Source', 'planned', 1, 'none: W14\'s licence_lookup isn\'t in the worker yet', False, ['As the Arizona Corporation Commission.']),
    ('dbip', 'Source', 'built', None, 'RegionGate.php (the monthly country file)', False, ['Its file\'s last load (`ip_eu_ranges`): needs live data.']),
    ('nws', 'Source', 'live', 5, 'Weather.php (every 15 minutes ahead of visitors)', False, ['Its user agent names a contact (its rule): which address? Names only here.']),
    ('az511', 'Source', 'built', None, 'Roads511.php, crawler/Sources.php, hub/index.php', False, ['Is `AZ511_API_KEY` set (the events API)? The WZDx feed needs none. Needs live data.']),
    ('cpsc', 'Source', 'built', None, 'MarketRules.php (a Marketplace item matched against recalls)', False, ['Called only when an item is posted; its calls: needs live data.']),
    ('pixabay', 'Source', 'built', None, 'stock/StockPixabay.php', False, ['Is `PIXABAY_KEY` set? Needs live data.']),
    ('heigit', 'Service', 'built', None, 'TravelLegs.php (one ask per leg, kept)', False, ['`ors_matrix_daily` cap\'s value and use: needs live data.']),
    ('supabase', 'Service', 'off', 2, 'Vectors.php and scripts/vectors_tick.php: built, not called (the service added off, the AI feature vector_index off)', False,
     ['Drawn "Off · added, not called yet" at stage 2, as Gemini.', 'Are `SUPABASE_URL` and `SUPABASE_SERVICE_KEY` set? Needs live data.']),
    ('stripe', 'Service', 'built', None, 'MarketPay.php (Connect, embedded checkout; decisions/0135)', False,
     ['Built, not "planned": MarketPay has its calls, test cards and keys. Which mode is live (`MARKET_PAY_ENV`) and whether any business is connected: needs live data.', 'The source map names it with Square for Marketplace checkout; QuickBooks\' "Stripe as a second processor" is a separate, future use.']),
    ('square', 'Service', 'built', None, 'MarketPay.php (OAuth, Web Payments, catalog sync); Pulse.php', False, ['As Stripe: `MARKET_PAY_ENV` and connected businesses need live data. Its tokens are refreshed a week before they expire.']),
    ('namecom', 'Service', 'built', None, 'Domains.php (Core API, since 2026-11-17)', False, ['`DOMAIN_SALES_MODE` and `DOMAINS_MODE`: needs live data (asks.md, visit 7).']),
    ('apple', 'Service', 'built', None, 'SocialAuth.php (Sign in with Apple)', False, ['Needs the $99 Developer Program: set up or not? Needs live data.']),
    ('microsoft', 'Service', 'live', 5, 'SocialAuth.php (sign-in), MsGraphMail.php (mail); OneDrive is the sync\'s (GitHub\'s card)', False,
     ['Three uses under one company: sign-in, mail through Microsoft Graph, and OneDrive for the sync. One card, or the sync\'s part on GitHub\'s card?']),
    ('meta', 'Service', 'built', None, 'SocialAuth.php (Sign in with Facebook)', False, ['Its app\'s review state: needs live data.']),
    ('onepassword', 'Service', 'linked', 1, 'none: each card\'s 1Password link only (decisions/0155)', False, ['decisions/0156\'s Vault holds staff logins now: is 1Password still in use, or Retired?']),
    ('tailwind', 'Service', 'live', 5, 'ui/page-shell.php, ui/auth-nav.php and the older pages (the browser loads cdn.tailwindcss.com)', False,
     ['Its own docs say the CDN is for development; the CSS rework replaces it (design/css-shells-brief.md). A Deactivate plan with that release?']),
    ('vimeo', 'Service', 'built', None, 'Journeys.php (embeds, played only after Play)', False, ['Called by visitors\' browsers only; no check of ours.']),
]
STATUS = {'live': ('g', 'On · Working'), 'built': ('n', 'not checked yet'), 'planned': ('n', 'Proposed (stage 1)'), 'off': ('n', 'Off · added, not called yet'), 'linked': ('n', 'Linked only: nothing is called')}
STATUS_WORDS = {'live': 'live and called (obs: its callers, and readings in the alarms snapshot or the browsers)', 'built': 'built and called by the code (obs); whether it\'s set up and used live needs live data',
                'planned': 'planned, not built: nothing calls it (obs)', 'off': 'built, not called yet: added off (obs)', 'linked': 'linked only: nothing calls it (obs)'}


def needs_for(key, needs):
    return [n for n in needs if any(k == 'svc' and v == key for k, v, _ in n['srcs'])]


def place(key, n):
    order = [lab if k != 'svc' else v for k, v, lab in n['srcs']]
    i = [v for k, v, _ in n['srcs']].index(key)
    return f"{n['label']}: {' → '.join(order)} (it's {['first', 'second', 'third', 'fourth', 'fifth', 'sixth'][i]}); else {n['fallback']}"


def stage_line(st, stage):
    if stage == 1:
        return '<span class="st">Stage 1 of 8 · Selection and configuration</span><span class="nx">Proposed: nothing calls it yet</span>'
    if stage == 2:
        return '<span class="st">Stage 2 of 8 · Integration (pre-flight)</span><span class="nx">Next allowed: Run pre-flight · Edit · Activate (once its keys are checked)</span>'
    if stage == 5:
        return '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause our calls · Take out (stage 6) · Deactivate (stage 7)</span>'
    return '<span class="st">Stage: needs live data</span><span class="nx">built; whether it\'s set up and used live isn\'t read here</span>'


def slots(d, opendata, fix=None):
    by = {}
    for lab, url in d['links']:
        t = slot_type(lab)
        if t and t not in by:
            by[t] = (lab, url)
    out = ''
    for t in ('billing', 'keys', 'console', 'login'):
        if t in by:
            out += kit.slot(t, d['name'], by[t][0], by[t][1], fix == t)
        else:
            s = kit.slot(t, d['name'], fix=fix == t)
            if opendata:
                s = s[:-6] + f'<div class="why">Why: {OPEN}</div></div>'
            out += s
    return '<div class="slots">' + out + '</div>', by


def rows(items):
    o = ''
    for n, sub, st, body, cls in items:
        o += f'<div class="row{" " + cls if cls else ""}"><div><div class="nm">{n}</div><div class="sub">{sub}</div>{st}</div><div>{body}</div><div class="btns"></div></div>'
    return '<div class="rows">' + o + '</div>'


def lt(k, w):
    return f'<span class="lt {k}"><i aria-hidden="true"></i>{E(w)}</span>'


FAULTS = {
    'commoncrawl': [
        ('Trip at index.commoncrawl.org (#35)', 'r', 'Paused by the system (index.commoncrawl.org)',
         'Common Crawl isn\'t answering',
         lt('r', 'Paused by the system (a trip)') + ' since Nov 20, 12:06 AM MST: 504 Gateway Time-out at index.commoncrawl.org; its 5 tries failed.',
         '<ul><li>Calls to index.commoncrawl.org</li><li>Under the contract, only the work that needs it: Sources read regularly (W3) and Re-read websites (W2) move to their other sources first</li></ul>',
         '<ul><li>Sources read regularly (W3): what our sources said, then the crawler reading directly</li><li>Re-read websites (W2): the crawler reading directly</li></ul>',
         '<ul><li>data.commoncrawl.org (its own line)</li><li>Every other source; pages, search, payments</li></ul>',
         'A server answer: tried, then a trip (rows 24–26). Its check runs on the diagnostic clock; Recovered closes it when the index answers.',
         ['Today (live): "index.commoncrawl.org: Its server failed (the crawler, on Railway)", and W3 held whole by its gate (pf:wf:W3, "W3 (its tasks wait with it)") since Nov 20, 8:05 AM MST.',
          'New: "Common Crawl isn\'t answering · Paused by the system (a trip) since <time>."; W3 waits only where no other source covers its need.'],
         'Nov 19, 6:01 PM MST: first failure · Nov 20, 12:06 AM MST: trip (alarms snapshot, 3:19 AM MST)'),
        ('Asked us to slow down at data.commoncrawl.org (#22)', 'a', 'Warning (a rate wait): data.commoncrawl.org',
         'Common Crawl asked us to slow down',
         lt('a', 'Warning (a rate wait)') + ' since Nov 18, 1:05 PM MST: 503 SlowDown "Please reduce your request rate".',
         'nothing: a back-off in its wait isn\'t a hold', 'none', '<ul><li>Everything: calls wait, then start again by themselves</li></ul>',
         'A rate wait, never a trip (row 27).',
         ['Today (live): "data.commoncrawl.org: Its server failed (the crawler, on Railway)" with a SlowDown reading (L12).', 'New: "Common Crawl asked us to slow down · Calls wait until <time>, then start again by themselves."'],
         'Nov 18, 1:05 PM MST (alarms snapshot, 3:19 AM MST)'),
    ],
    'wikimedia': [
        ('Asked us to slow down at query.wikidata.org (#26)', 'a', 'Warning (a rate wait): query.wikidata.org',
         'Wikimedia asked us to slow down',
         lt('a', 'Warning (a rate wait)') + ' since Nov 18, 1:05 PM MST: 429 at query.wikidata.org.',
         'nothing: a back-off in its wait isn\'t a hold', 'none', '<ul><li>Everything: Wikidata\'s calls wait twice as long, then start again by themselves</li></ul>',
         'A rate wait, never a trip (row 27); its Retry-After honoured by every caller (tools/place-tests/wdqs.php).',
         ['Today (live): "query.wikidata.org: Asked us to slow down (the site, on Bluehost)": the words already follow the state.', 'New: the same, on its card.'],
         'Nov 18, 1:05 PM MST (alarms snapshot, 3:19 AM MST)'),
    ],
    'census': [
        ('Didn\'t answer at the geocoder (#18)', 'a', 'Warning (the system is trying): the geocoder',
         'The U.S. Census Bureau isn\'t answering',
         lt('a', 'Warning (the system is trying)') + ' since Nov 18, 1:05 PM MST: "Connection reset by peer" at geocoding.geo.census.gov.',
         'nothing yet', '<ul><li>Map points (W2): the batch goes to the worker on Railway (the Census backup, decisions/0175\'s note)</li></ul>',
         '<ul><li>Populations (our loads, the last kept); every other source</li></ul>',
         'A server answer from the site\'s side: tried (rows 24–26); the worker\'s backup places the batch by the same rules.',
         ['Today (live): "geocoding.geo.census.gov: Didn\'t answer (the site, on Bluehost)", a warning since Nov 18.', 'New: "The U.S. Census Bureau isn\'t answering · Trying again (1 of 3): connection reset at <time>."'],
         'Nov 18, 1:05 PM MST (alarms snapshot, 3:19 AM MST)'),
    ],
}


def card(rec, needs, wfnames, fault=None):
    key, role, st, stage, callers, has_fault, opens = rec
    d = defs(key)
    opendata = not d['keys'] and ('Free ($0)' in d['plan'] or 'Free.' in d['plan']) and role == 'Source'
    sl, by = slots(d, opendata, fix='console' if fault and 'console' in by_types(d) else None)
    k, words = STATUS[st]
    if fault:
        k, words = fault[1], 'On · ' + fault[2]
    ns = needs_for(key, needs)
    wfs = sorted({w for n in ns for w in n['wfs']}, key=lambda w: int(w[1:]))
    feeds = ', '.join(f'{w} {wfnames.get(w, "")}' for w in wfs) or 'the site\'s own needs (no workflow)'
    rad = radius(key, ns)
    pagelinks = ' · '.join(f'<a href="{E(u)}" target="_blank" rel="noopener">{E(l)} ↗</a>' for l, u in d['links'] if not slot_type(l))
    z1 = kit.zone(1, 'Identity and role', f"""<div class="idl"><a class="name" href="#">{E(d['name'])}</a><span class="badge role">{role}</span><span class="badge crit">Not critical: no duty stops without it</span>{lt(k, words)}</div>
<div class="stage">{stage_line(st, stage)}</div><div class="stage" style="color:var(--meta);font-size:12.5px">{'Setup capture: each slot\'s why recorded' if opendata else 'Setup capture: ' + ', '.join(t for t in ('Billing', 'Keys', 'Console', 'Sign in') if t.lower().replace('sign in', 'login') not in by) + ' not recorded'}</div>
<div class="stage"><span class="spend"><span>{E(d['plan'])}</span></span></div>{rad}""")
    z2 = kit.zone(2, 'Capabilities and feeds: what uses it', rows([
        ('What it does', 'service-defs.php', '', E(d['purpose']) + ' <span class="basis">obs</span>', ''),
        ('Feeds', 'Preflight::NEEDS', '', E(feeds), ''),
        ('Its place on the source map', 'in the map\'s order, then the fallback', '', '<ul>' + ''.join(f'<li>{E(place(key, n))}</li>' for n in ns) + '</ul>' if ns else 'not named', ''),
        ('Its callers', 'the code that calls it', lt('g' if st == 'live' else 'n', STATUS_WORDS[st].split(' (')[0]), E(callers) + ' <span class="basis">obs</span>', ''),
    ] + ([('Its pages', 'not portal slots (§5)', '', pagelinks, '')] if pagelinks else [])))
    keys = ', '.join(f'<code>{E(a)}</code> ({E(b)})' for a, b in d['keys'] + d['code_keys']) or 'none'
    z3 = kit.zone(3, 'Sensors and key readiness', rows([
        ('Its hosts', 'declared', '', ', '.join(f'<code>{E(h)}</code>' for h in d['hosts']), ''),
        ('Its keys', 'names and places only', '', keys, ''),
        ('Its check', 'the diagnostic clock', '', (fault[-1] if fault else ('not read yet' if st != 'planned' else 'none until it\'s built')), 'hl' if fault else ''),
    ] + ([('Its caps', 'Thresholds', '', ', '.join(f'<code>{c}</code>' for c in d['caps']) + ': not read yet', '')] if d['caps'] else [])))
    if fault:
        title, kk, _, ttl, status, held, backup, unaff, doing, today, _ = fault
        th = kit.thread(ttl, status, held, backup, unaff, doing, kit.b('Check now', off='its next try is set by the back-off') if kk == 'a' else kit.b('Check and start again', True) + kit.b('Acknowledge'),
                        sl, 'Their trouble: no Fix it at our end. Its console, where registered, to see its health (row 130).',
                        '<div class="sub" style="border-left:3px solid var(--field-edge);padding-left:.5rem"><b>Today\'s words, beside the new:</b> ' + ' '.join(E(t) for t in today) + '</div>', at=d['name'])
        body = z1 + kit.zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', th) + z2 + z3
    else:
        z4 = kit.act_rows(kit.b('Check now') + (kit.b('Pause our calls') if st == 'live' else ''), d['name'], sl,
                          then='Open words: ' + ' '.join(re.sub(r'`([^`]+)`', r'<code>\1</code>', E(o)) for o in opens))
        body = z1 + z2 + z3 + kit.zone(4, 'Actions and Fix it', z4)
    return f'<article class="card{" fault-" + fault[1] if fault else ""}" aria-label="{E(d["name"])}">{body}</article>', (k, words)


def radius(key, ns):
    """Its Incident Radius from the source map (Preflight::NEEDS): where it's the last source, the need falls to its fallback; else
    the need moves to its next source."""
    held, backup = [], []
    for n in ns:
        vals = [v for k, v, _ in n['srcs']]
        i = vals.index(key)
        rest = n['srcs'][i + 1:]
        if rest:
            backup.append(f"{n['label']}: its next source, {rest[0][2] if rest[0][0] != 'svc' else rest[0][1]}")
        else:
            held.append(f"{n['label']}: {n['fallback']}")
    li = lambda xs: '<ul>' + ''.join(f'<li>{E(x)}</li>' for x in xs) + '</ul>' if xs else 'none'
    return (f'<details><summary>If it can\'t be called: its Incident Radius (from the source map)</summary><div class="rad"><div class="h"><b>Held</b>{li(held)}</div>'
            f'<div class="o"><b>On a backup</b>{li(backup)}</div><div class="u"><b>Unaffected</b><ul><li>Every need that doesn\'t name it; pages, search, payments</li></ul></div></div></details>')


def by_types(d):
    return {slot_type(l) for l, _ in d['links'] if slot_type(l)}


def scenarios(needs, wfnames):
    """[(key, role, name, [(title, card, light, real)])] for the board."""
    out = []
    for rec in S:
        key = rec[0]
        d = defs(key)
        c, l = card(rec, needs, wfnames)
        sc = [(('Working' if rec[2] == 'live' else STATUS[rec[2]][1]) + ' (its record and callers)', c, l, True)]
        for f in FAULTS.get(key, []):
            fc, fl = card(rec, needs, wfnames, f)
            sc.append((f[0], fc, fl, True))
        out.append((key, rec[1], d['name'], sc))
    return out


def section_md(needs, wfnames):
    L = ['## 26. The services the needs name with no card yet (in the interactive board, `board/index.html`)', '',
         'Twenty-six services named by `Preflight::NEEDS` had no card. Each is drawn in the interactive board\'s Modules view, generated by',
         '`mockups/src/svc26.py` from the live mirror (`aeb5f31`): `service-defs.php`, `Preflight::NEEDS`, `Automation::WORKFLOWS` and each',
         'service\'s callers (a search of the code for its hosts). No `.env` was opened and no outside call was made. **Never drawn as working**',
         'unless its callers and a reading show it live; a service nothing calls is Proposed (stage 1) or Off.', '',
         '**Shared:** criticality "Not critical: no duty stops without it" (none is in `ControlRegister::CRITICAL`, obs). Slots from',
         'its record\'s links under 02 §8\'s mapping (Billing, Usage, Limits → Billing; Keys, Credentials, API settings → Keys;',
         'Dashboard, Console → Console; Account → Sign in); any other link is "Its pages", not a slot. An open-data Source with no',
         'key and no account has all four slots Not registered with "Open data: no account", so its setup notice clears.', '',
         '**The real faults** (the alarms snapshot, 3:19 AM MST) are each a state on the card\'s switch: Common Crawl #35 (a trip) and',
         '#22 (a rate wait under the contract), Wikimedia #26 (a rate wait), the Census geocoder #18 (a warning, with the worker\'s backup).', '']
    for n_, rec in enumerate(S, 1):
        key, role, st, stage, callers, has_fault, opens = rec
        d = defs(key)
        ns = needs_for(key, needs)
        wfs = sorted({w for n in ns for w in n['wfs']}, key=lambda w: int(w[1:]))
        opendata = not d['keys'] and ('Free ($0)' in d['plan'] or 'Free.' in d['plan']) and role == 'Source'
        _, by = slots(d, opendata)
        sl = ', '.join(f"{t.replace('login', 'Sign in').capitalize()} · {by[t][0]}" for t in by) or 'none registered'
        L += [f'### 26.{n_} {d["name"]} (`{key}`)', '',
              f'| Field | Value | Basis |', '|---|---|---|',
              f'| role · status | {role} · **{STATUS_WORDS[st]}** | obs |',
              f'| stage drawn | {"Stage " + str(stage) if stage else "needs live data"}; state "{STATUS[st][1]}" | {"inf" if stage == 5 else "des"} |',
              f'| purpose | "{d["purpose"]}" | obs |',
              f'| plan and licence | {d["plan"]} | obs |',
              f'| feeds | {", ".join(f"{w} {wfnames.get(w, chr(32)).strip()}" for w in wfs) or "the site\'s own needs (no workflow)"} | obs |',
              f'| priority map | {"; ".join(place(key, n) for n in ns)} | obs |',
              f'| callers | {callers} | obs |',
              f'| hosts | {", ".join("`" + h + "`" for h in d["hosts"])} | obs |',
              f'| keys (names only) | {", ".join(f"`{a}` ({b})" for a, b in d["keys"] + d["code_keys"]) or "none"} | obs |',
              f'| slots (04) | {sl}; {"the rest Not registered, \"" + OPEN + "\"" if opendata else "the rest Not registered"} | obs; des |',
              f'| states (02) | {"working, from its callers" if st == "live" else STATUS[st][1]}' + (''.join(f'; {f[0]}' for f in FAULTS.get(key, []))) + ' | ' + ('obs (alarms) · rows 24–27, 130' if key in FAULTS else 'des') + ' |', '',
              '**Open words:** ' + ' '.join(f'({i}) {o}' for i, o in enumerate(opens, 1)), '']
    return '\n'.join(L)
