# Crossover: one owning gauge ("agency") per inventory item (Jason, 2026-11-22: "items that need their agency identified";
# "dont combine or split the focus of the gauge"). Run inside sections.py (exec), so it sees SECTIONS and writes
# step3/sections/crossover.md; it also adds agency, reason, alternative and cross-links to inventory.json's items.
# Spec data, not site code.

GAUGES = ['Discovery', 'Directory', 'Community', 'Marketplace', 'Platform', 'Knowledge Engine']
OPTION_B = GAUGES + ['Business tools', 'Events']

# (item name starts with, owning gauge, reason, the alternative, shown as a cross-link in, owner under option B)
XO = [
    ('Home: search first', 'Platform', 'the way into all four sections; none owns it', 'Discovery (its search and doors lead there first)', 'all four', None),
    ('Sign in', 'Platform', 'accounts serve every section', 'none', '—', None),
    ('Sign up', 'Platform', 'accounts serve every section', 'none', '—', None),
    ('Two-step', 'Platform', 'accounts', 'none', '—', None),
    ('Delete my account', 'Platform', 'accounts', 'none', '—', None),
    ('Data access requests', 'Platform', 'accounts and privacy', 'none', '—', None),
    ('DMCA', 'Platform', 'site-wide takedowns', 'Community (posts are most takedowns)', 'Community', None),
    ('The FAQ', 'Platform', 'site-wide', 'none', '—', None),
    ('Ask Traversence', 'Platform', 'site-wide AI page', 'Discovery', 'Discovery', None),
    ('Universal search', 'Platform', 'one search for every section (0053)', 'Directory (most searches are for businesses)', 'Discovery, Directory', None),
    ('Search Phase C', 'Platform', 'the learning search (0061) serves every kind, not only listings', 'Directory', 'Directory', None),
    ('5 W\'s lexicon', 'Platform', 'the search parser; Emergency resources rides with it as an admin Coming card', 'Discovery (Emergency resources is a map of places)', 'Discovery', None),
    ('The Vault', 'Platform', 'staff logins', 'none', '—', None),
    ('The admin engine', 'Platform', 'the admin', 'none', '—', None),
    ('The admin\'s Pulse', 'Platform', 'Pulse and messages (0172, 0176)', 'none', '—', None),
    ('The System Control Board', 'Platform', 'the admin', 'a runtime board, not a build gauge', '—', None),
    ('Staging in safe mode', 'Platform', 'releases', 'none', '—', None),
    ('The page builder for our own pages', 'Platform', 'our own pages; the same builder\'s Storefront Studio is Marketplace\'s', 'Marketplace', 'Marketplace', None),
    ('One uploader', 'Platform', 'every file in every section goes through it (0127)', 'none', '—', None),
    ('The video worker', 'Platform', 'infrastructure for every video; the Channel tab is Community\'s', 'Community', 'Community', None),
    ('Back-office', 'Platform', 'our own orders, vendors and QuickBooks: Traversence\'s business, not a member\'s or a business\'s market', 'Marketplace ("orders, payments")', 'Marketplace', None),
    ('The order system\'s improvements', 'Platform', 'the same back-office', 'Marketplace', 'Marketplace', None),
    ('Growth', 'Platform', 'our sales to businesses', 'Directory (prospects are listings)', 'Directory', None),
    ('Quotes & deals', 'Platform', 'our sales and White Glove', 'none', '—', None),
    ('Fulfillment', 'Platform', 'the admin\'s Coming cards; the verification vault holds claim documents', 'Directory (the verification vault)', 'Directory', None),
    ('Templates', 'Platform', 'site-wide', 'none', '—', None),
    ('Text messages', 'Platform', 'one channel for every section (0172 §5)', 'none', '—', None),
    ('Terms and privacy', 'Platform', 'site-wide legal pages', 'Marketplace', 'Marketplace', None),
    ('Legal texts published', 'Platform', 'the attorney\'s pass covers the whole site, not only selling', 'Marketplace (where 0141 asked for it)', 'Marketplace', None),
    ('A promotion\'s page', 'Platform', 'our own promotions (0108, Growth)', 'Marketplace', 'Marketplace', None),
    ('The member Dashboard', 'Platform', 'the account\'s home across all four', 'Community', 'Community', None),
    ('The member\'s Pulse', 'Platform', 'Pulse and messages', 'Community', 'Community', None),
    ('Messages and notices through the one dispatcher', 'Platform', 'Pulse and messages', 'Community', 'all four', None),
    ('Email through Amazon SES', 'Platform', 'Pulse and messages', 'Community', '—', None),
    ('Pushes', 'Platform', 'Pulse and messages', 'Community', '—', None),
    ('The Gallery', 'Platform', 'every file is chosen through it (0143), not only albums', 'Community (people\'s albums)', 'Community', None),
    ('Gallery item', 'Platform', 'with the Gallery', 'Community', 'Community', None),
    ('Likes and comments', 'Platform', 'the engage bar sits on every content page', 'Community', 'all four', None),
    ('Like as a guest', 'Platform', 'the engage bar', 'Community', 'all four', None),
    ('Facebook and Instagram for members', 'Platform', 'sign-in and sharing are account-level; the business apps are Marketplace\'s (0169)', 'Community', 'Community', None),
    # Knowledge Engine: the engine is owned here; its outputs are counted where they show
    ('The engine\'s discovery workflows', 'Knowledge Engine', 'the engine; guides, journeys, landmarks and events it makes are counted in Discovery', 'Discovery', 'Discovery', None),
    ('The engine\'s listing workflows', 'Knowledge Engine', 'the engine; the listings are counted in Directory', 'Directory', 'Directory', None),
    ('The Knowledge Engine\'s shared parts', 'Knowledge Engine', 'the engine', 'Platform', '—', None),
    ('W2\'s refresh count', 'Knowledge Engine', 'a fix in the engine\'s ledger', 'Directory', 'Directory', None),
    ('Data cleanup', 'Knowledge Engine', 'brings the engine\'s old output into line', 'Directory', 'Directory', None),
    ('The duplicates fix', 'Knowledge Engine', 'the engine\'s identity check (0164 §3)', 'Directory', 'Directory', None),
    ('Help us find this business', 'Directory', 'a listing request people see on Get Local', 'Knowledge Engine', 'Knowledge Engine', None),
    ('OpenStreetMap businesses', 'Directory', 'shown on Get Local', 'Knowledge Engine', 'Knowledge Engine', None),
    ('Live events from the crawler', 'Discovery', 'an engine output, counted where it shows (Things to do, guides)', 'Events, under option B; Community', 'Community (Events)', 'Events'),
    ('Guides and journeys on demand', 'Discovery', 'an output counted where it shows', 'Knowledge Engine (W5)', 'Knowledge Engine', None),
    ('Stories for place pages', 'Discovery', 'shown on place pages', 'Knowledge Engine', '—', None),
    # Discovery
    ('Trips R4', 'Discovery', 'Personal Guides are trips published as guides (0174)', 'Community (member-published)', 'Community', None),
    ('Am here', 'Discovery', 'beside "Was here" on journeys', 'Community (a check-in is social)', 'Community', None),
    ('The map release', 'Discovery', 'trails and routes', 'Platform (js/map-view.js serves every map)', 'Platform', None),
    ('The area data', 'Discovery', 'the place taxonomy', 'Knowledge Engine (Data cleanup is the tool)', 'Knowledge Engine', None),
    # Directory: the listing face of the business tools
    ('The business portal', 'Directory', 'its listing face (claims, plan, the listing editor); the selling face is Marketplace\'s', 'Business tools, under option B', 'Marketplace', 'Business tools'),
    ('An owner opens', 'Directory', 'the listing face', 'Business tools', '—', 'Business tools'),
    ('Edit my listing', 'Directory', 'the listing face', 'Business tools', '—', 'Business tools'),
    ('Claim a listing', 'Directory', 'claim (ruling)', 'Business tools', '—', 'Business tools'),
    ('Verify a listing', 'Directory', 'claim (ruling)', 'Business tools', '—', 'Business tools'),
    ('Adding a business', 'Directory', 'a listing', 'Business tools', '—', 'Business tools'),
    ('The Nexus', 'Directory', 'many locations as listings', 'Business tools', '—', 'Business tools'),
    ('Checking a phone', 'Directory', 'claim; texts themselves are Platform\'s', 'Business tools', 'Platform', 'Business tools'),
    ('Message a business', 'Directory', 'a visitor reaching a listing', 'Platform (messages)', 'Platform', None),
    ('Plans made for agencies', 'Marketplace', 'plans are sold in the Shop', 'Directory (agencies claim listings)', 'Directory', None),
    # Marketplace: the selling face
    ('Market 360', 'Marketplace', 'the selling face (ruling)', 'Business tools', 'Directory', 'Business tools'),
    ('Owner ID check', 'Marketplace', 'a seller check (0141 §3)', 'Business tools', '—', 'Business tools'),
    ('State-record checks', 'Marketplace', 'a seller check (0141 §3); W14\'s licence lookup uses the same registries', 'Knowledge Engine', 'Knowledge Engine', 'Business tools'),
    ('Storefront', 'Marketplace', 'storefronts (ruling)', 'Business tools', '—', 'Business tools'),
    ('Connect your domain', 'Marketplace', 'storefronts', 'Business tools', '—', 'Business tools'),
    ('Builder step 6', 'Marketplace', 'AI writing by plan is sold with Studio', 'Platform (it writes our pages too)', 'Platform', 'Business tools'),
    ('The Shop\'s App store', 'Marketplace', 'a Shop department (0169)', 'Business tools (most apps are a business\'s)', '—', 'Business tools'),
    ('First apps', 'Marketplace', 'sold in the App store', 'Business tools', '—', 'Business tools'),
    ('Provider-neutral payments', 'Marketplace', 'businesses\' payments; our own books ride on it', 'Platform (our ledger)', 'Platform', 'Business tools'),
    ('Checkout through the business', 'Marketplace', 'selling', 'Business tools', '—', 'Business tools'),
    ('Square catalog sync', 'Marketplace', 'selling', 'Business tools', '—', 'Business tools'),
    ('Catalog rules', 'Marketplace', 'selling', 'Business tools', '—', 'Business tools'),
    ('GO Codes and QR', 'Marketplace', 'sold in the Shop (ruling)', 'Platform (trvgo.us short links serve every section)', 'Platform', None),
    ('A ticket bought on the Marketplace', 'Marketplace', 'an item kind (tickets, 0141)', 'Events, under option B', 'Community', 'Events'),
    ('Add-ons and 1Password', 'Marketplace', 'resold in the Shop', 'Platform', '—', None),
    ('Checkout and storefronts on the design tokens', 'Marketplace', 'its pages', 'Platform (design)', 'Platform', None),
    # Community
    ('Events, one tool', 'Community', 'people host them (ruling); shown in Discovery\'s Things to do, never counted there', 'Events, under option B', 'Discovery', 'Events'),
    ('Ticket Sales for events', 'Community', 'part of the Events tool (0134 §6b)', 'Marketplace (tickets on the order system); Events under option B', 'Marketplace', 'Events'),
    ('The profile\'s Videos tab', 'Community', 'the profile', 'Platform', 'Platform', None),
    ('Posting as a business', 'Community', 'posts', 'Business tools', 'Directory', None),
    ('Community threads into the AI corpus', 'Community', 'community talk', 'Knowledge Engine', 'Knowledge Engine', None),
    ('Admin → Local', 'Directory', 'its face\'s admin', 'Platform (the admin)', 'Platform', None),
    ('Admin → Social', 'Community', 'its face\'s admin', 'Platform (the admin)', 'Platform', None),
    ('Admin → Market', 'Marketplace', 'its face\'s admin', 'Platform (the admin)', 'Platform', None),
]


def _apply():
    seen = set()
    for sec, d in SECTIONS.items():
        for it in d['items']:
            m = next((x for x in XO if it['name'].startswith(x[0])), None)
            if m:
                seen.add(m[0])
                it.update(agency=m[1], crossover=True, reason=m[2], alternative=m[3], cross_links=m[4], option_b=m[5] or m[1])
            else:
                assert sec != 'Platform', 'Platform item with no agency: ' + it['name']
                it.update(agency=sec, crossover=False, reason='its own section', alternative='—', cross_links='—', option_b=sec)
            it['listed_in'] = sec
    missing = [x[0] for x in XO if x[0] not in seen]
    assert not missing, missing


_apply()


def _gauge_stats(key):
    out = {}
    for g in (OPTION_B if key == 'option_b' else GAUGES):
        its = [it for d in SECTIONS.values() for it in d['items'] if it[key] == g]
        scope = [i for i in its if i['status'] != 'proposed']
        passing = sum(1 for i in scope if i['status'] == 'live' and i['check'] == 'passing')
        out[g] = dict(items=len(its), in_scope=len(scope), complete=passing, pct=round(passing / len(scope) * 100, 1) if scope else 0,
                      held=sum(1 for i in scope if i['status'] == 'held'), decided=sum(1 for i in scope if i['status'] == 'decided'),
                      live_not_passing=sum(1 for i in scope if i['status'] == 'live' and i['check'] == 'not passing'),
                      live_not_checked=sum(1 for i in scope if i['status'] == 'live' and i['check'] == 'not checked'),
                      proposed=len(its) - len(scope), names=[i['name'] for i in its])
    return out


# the runtime board's gauge cards that show more than one reading (board.METRIC), for one measure or a graph
TWO_READINGS = [
    ('o-phone', 'The phone layout', '"1 sideways · 13 edges off": two measures (sideways scroll, card edges)', 'one measure: pages passing the phone check (both rules) out of pages checked; the two counts to the drill-down'),
    ('o-paths', 'Paths complete', '"N passed · 1 failed"', 'one measure: paths passed out of paths run; failed and skipped to the drill-down'),
    ('o-gather', 'Gathering reconciles', '"W1 paused · W3 off · 0 records in": two workflows\' states and a count', 'one measure: records in over 24 hours against the day\'s expected; each workflow\'s state to the drill-down (it already has the Workflows path)'),
    ('o-complete', 'Complete', 'the reading also counts staged finds with no kind beside the map points', 'one measure: share of public listings with an exact map point; "no kind" to the drill-down or its own gauge'),
    ('o-ir', 'Incident Reports close', '"IR-0001 open · Unconfirmed": a count and a state', 'one measure: IRs closed with a confirmed cause out of IRs raised; each IR\'s state to the drill-down'),
    ('o-search-cap', 'Within our means: web search', '"26 searches · 20.8¢": two units', 'one measure: searches against websearch_monthly; the money beside it as a note or a graph'),
    ('o-signin', 'Sign-in works end to end', 'two paths (sign in, sign out) in one reading', 'acceptable as one pass/fail of the whole path; or one measure: steps passed out of steps'),
]


def write_crossover():
    A = _gauge_stats('agency')
    B = _gauge_stats('option_b')
    allits = [it for d in SECTIONS.values() for it in d['items']]
    xo = [it for it in allits if it['crossover']]
    total = len(allits)
    L = ['# Crossover: one owning gauge for every item (a proposal for Jason)', '',
         'Generated by `mockups/src/sections.py` with `sections_crossover.py` from `inventory.json` (Operations\' inventory merged with the',
         'session manager\'s outline). A proposal only: no gauge is built and nothing is merged.', '',
         '**Jason (2026-11-22):** "these highlights that we have multiple items that need their agency identified. Maybe consider suggesting',
         'additional gauges that would over see such crossover or which section would gain those lacking assignment." And: "dont combine or',
         'split the focus of the gauge. if a two needs are required it\'s no longer a gauge is is a graph or some other representation of the',
         'measured data."', '',
         '## The rule: one gauge, one measure, one needle', '',
         '- Each gauge\'s one measure is **Complete**: items live and passing their check, over the gauge\'s signed scope.',
         '- Live, held, not passing and not checked never go on the dial. They sit in the drill-down as a small breakdown (a stacked bar or',
         '  a list). Anything that needs two measures at once is a graph beside the gauge, not a gauge.',
         '- Every item has **one owning gauge** (its agency). Other sections may show it as a cross-link, never count it.',
         '- The session manager agrees and withdraws the two-needle idea (2026-11-22). Recorded in 03 §27 as Jason\'s direction.', '',
         f'## The count: {len(xo)} crossover items of {total}', '',
         f'{len(xo)} of the inventory\'s {total} items span sections or had no clear owner (the 8 questions, the Platform list and a sweep',
         'of every item). Each now has one agency, its reason, the alternative and where it\'s cross-linked. **No item is left without an',
         'owner, and none is counted twice** (checked by the generator: every Platform item needs an agency, every rule must match).', '']
    # options
    L += ['## The options, tested against the inventory', '',
          '**Recommended: 4 + Platform + Knowledge Engine (six gauges)**, the session manager\'s suggestion: business tools split by face (the',
          'listing face in Directory, the selling face in Marketplace), Events under Community.', '',
          '| Gauge | Items owned | In scope | Complete (draft) | Held | Decided, not built | Live, not passing | Live, not checked |', '|---|---|---|---|---|---|---|---|']
    for g, s in A.items():
        L.append(f'| **{g}** | {s["items"]} | {s["in_scope"]} | **{s["pct"]}%** ({s["complete"]}) | {s["held"]} | {s["decided"]} | {s["live_not_passing"]} | {s["live_not_checked"]} |')
    L += [f'| **All** | {sum(s["items"] for s in A.values())} | | | | | | |', '',
          '**The alternative: 4 + Platform + Knowledge Engine + Business tools + Events (eight gauges).**', '',
          '| Gauge | Items owned | In scope | Complete (draft) | Held | Decided, not built |', '|---|---|---|---|---|---|']
    for g, s in B.items():
        L.append(f'| {g} | {s["items"]} | {s["in_scope"]} | {s["pct"]}% ({s["complete"]}) | {s["held"]} | {s["decided"]} |')
    L += [f'| **All** | {sum(s["items"] for s in B.values())} | | | | |', '',
          '**Why six, not eight:**',
          f'- **Business tools** would take {B["Business tools"]["items"]} items: Directory\'s listing face (claim, verify, the portal, the',
          '  owner\'s editor) and Marketplace\'s selling face (Market 360°, Studio, storefronts, the App store). It cuts by audience (the',
          '  business) where the four cut by face. Directory and Marketplace would each lose their core, and a claim would count toward',
          '  neither the listing it creates nor the shop it opens. Split by face, every item still has one owner, and the portal\'s two',
          '  faces cross-link each other.',
          f'- **Events** would take {B["Events"]["items"]} items, {B["Events"]["decided"]} of them decided and not built: a gauge that stays near',
          f'  {B["Events"]["pct"]}% until 0134 is built, with almost nothing to drill into. Under Community (the ruling) it\'s one large gap',
          '  in a section that already holds people\'s hosting. When 0134 is built, an Events gauge can be split off without moving anything else.',
          '- **Knowledge Engine** earns its own gauge: its parts (W0–W14, the identity check, data cleanup) feed Discovery and Directory',
          '  alike, and their runtime state (W1 paused, W3\'s switches off) would otherwise drag one section\'s needle for the other\'s',
          '  content. Its outputs stay counted where they show.',
          '- **Platform** earns its own gauge: accounts, Pulse and messages, the Gallery, the uploader, the admin, staging and releases',
          '  serve all four, and folding them in would count each one four times or pick a section by chance.', '']
    # per gauge item lists
    L += ['## The recommended gauges and the items each owns', '']
    for g, s in A.items():
        L += [f'### {g} ({s["items"]} items; Complete {s["pct"]}%, draft)', '']
        its = [it for it in allits if it['agency'] == g]
        for it in its:
            mv = '' if it['listed_in'] == g else f' *(moved from {it["listed_in"]})*'
            st = {'live': 'Live, ' + it.get('check', ''), 'held': 'Built, held', 'decided': 'Decided, not built', 'proposed': 'Proposed (out of scope)'}[it['status']]
            L.append(f'- {it["name"]} · {st}{mv}')
        L.append('')
    moves = [it for it in allits if it['agency'] != it['listed_in']]
    L += ['## Moves against the inventory', '', f'{len(moves)} items change owner from where `inventory.md` listed them; every other item stays where it was.', '',
          '| Item | Listed in | Owned by |', '|---|---|---|'] + [f'| {it["name"].replace("|", "/")} | {it["listed_in"]} | {it["agency"]} |' for it in moves] + ['']
    # crossover table
    L += ['## Every crossover item: recommended owner, the alternative, where it\'s cross-linked', '',
          '| Item | Recommended owner | Why | The alternative | Cross-linked in (never counted) |', '|---|---|---|---|---|']
    for it in xo:
        L.append(f'| {it["name"].replace("|", "/")} | **{it["agency"]}** | {it["reason"]} | {it["alternative"]} | {it["cross_links"]} |')
    L += ['', '## The runtime board: gauges showing two readings', '',
          'Under the same rule, the runtime board\'s gauge cards (Board view, 03 §27) should each carry one measure. These show more than one',
          'reading today, each with a proposed single measure (the rest to the drill-down, or a graph beside it):', '',
          '| Gauge | Today | One measure |', '|---|---|---|'] + [f'| {n} (`{i}`) | {t} | {f} |' for i, n, t, f in TWO_READINGS]
    L += ['', 'The caps (`o-claude`, `o-places`) show one measure in two forms ("$27.05 / $40 · 68%"): one reading, kept.', '',
          '## For Jason', '', '1. The gauge set: six (recommended) or eight.', '2. Each crossover item\'s owner in the table above.',
          '3. The runtime gauges above: one measure each, as proposed, or a graph.', '4. Then each gauge\'s scope list, signed, before anything is built.']
    open(OUT + 'crossover.md', 'w').write('\n'.join(L) + '\n')
    d = json.load(open(OUT + 'inventory.json'))
    for sec in d['sections'].values():
        for it in sec['items']:
            src = next(x for s2 in SECTIONS.values() for x in s2['items'] if x['name'] == it['name'])
            for k in ('agency', 'crossover', 'reason', 'alternative', 'cross_links', 'option_b', 'listed_in'):
                it[k] = src[k]
    d['gauge_rule'] = 'One gauge, one measure, one needle: Complete = live and passing its check, over its signed scope; the rest in the drill-down (Jason, 2026-11-22).'
    d['gauges_recommended'] = {g: {k: v for k, v in s.items() if k != 'names'} for g, s in A.items()}
    d['gauges_option_b'] = {g: {k: v for k, v in s.items() if k != 'names'} for g, s in B.items()}
    d['runtime_two_readings'] = [dict(id=i, name=n, today=t, one_measure=f) for i, n, t, f in TWO_READINGS]
    json.dump(d, open(OUT + 'inventory.json', 'w'), indent=1)
    print('crossover', len(xo), 'of', total, '; moves', len(moves))
    for g, s in A.items():
        print('A', g, s['items'], s['in_scope'], s['pct'], 'held', s['held'])
    for g, s in B.items():
        print('B', g, s['items'], s['pct'])
