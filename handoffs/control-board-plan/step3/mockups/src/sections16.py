# Jason's 16 development gauges (approved 2026-11-22): 6 front end, 10 back end (the admin's modules as manifests.php has them).
# Ownership is where the item lives: a public page or flow -> its front-end gauge; an admin section -> the module manifests.php
# puts it in (computed, not judged). A feature with both faces is two items, each owned once, each linking the other.
# Writes step3/sections/development-gauges.md and adds owner16 / other_face to inventory.json. Spec data, not site code.
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import sections as S   # the inventory (184 items) with its checks

MANIFEST = '/tmp/claude-0/-home-user/eed694bf-93ab-528f-9827-430e8b4ec8c4/scratchpad/mirror/sync-app/admin/manifests.php'
MANIFEST_SRC = 'app/admin/manifests.php (mirror Sync e229587, 22:55 UTC Nov 21)'
OUT = S.OUT

FRONT = ['Discovery', 'Directory', 'Community', 'Marketplace', 'TRVGO – Storefront', 'Accounts']
BACK = {'dashboard': 'Dashboard & Pulse', 'pulse': 'Dashboard & Pulse', 'local': 'Get Local 360°', 'explore': 'Let\'s Explore 360°', 'market': 'Market 360°',
        'social': 'Social 360°', 'growth': 'Sales & Growth', 'concierge': 'White Glove Concierge', 'backoffice': 'Back-Office', 'insights': 'Insights & Research',
        'config': 'System Configuration'}
GAUGES = FRONT + ['Dashboard & Pulse', 'Get Local 360°', 'Let\'s Explore 360°', 'Market 360°', 'Social 360°', 'Sales & Growth', 'White Glove Concierge',
                  'Back-Office', 'Insights & Research', 'System Configuration']
QUESTION = 'Question (no gauge yet)'

# ---- the back end, computed from manifests.php: each section an item with its live, partial or coming status
lines = open(MANIFEST).read().split('\n')
MITEMS, mod = [], None
merged = {'stream': 'stream', 'settings': 'streamset', 'mail': 'mail'}   # Workspaces::MERGED: Pulse's sections are the Dashboard's
for n, l in enumerate(lines, 1):
    m = re.match(r"\s{4}'(\w+)' => \['sections'", l)
    if m:
        mod = m.group(1)
        continue
    m = re.match(r"\s{8}'([\w-]+)' => \$([BCW])\('((?:[^'\\]|\\.)*)'", l)
    if not (m and mod):
        continue
    key, typ, label = m.group(1), m.group(2), m.group(3).replace("\\'", "'")
    if mod == 'pulse':
        continue   # the same widgets as the Dashboard's (Workspaces::MERGED), counted once there
    status = 'partial' if "'partial'" in l else ('coming' if typ == 'C' else 'live')
    route = f'/admin/{mod}/{key}'
    it = dict(name=f'{label} ({route})', kind='admin tool', source=f'{MANIFEST_SRC}:{n} (${typ}{", partial" if status == "partial" else ""})', owner='', note='', outline=False,
              manifest=f'{mod}/{key}', owner16=BACK[mod], other_face=[])
    if status == 'coming':
        waits = re.search(r"\$C\('(?:[^'\\]|\\.)*', '\w+', '(?:[^'\\]|\\.)*', '((?:[^'\\]|\\.)*)'", l)
        w = waits.group(1).replace("\\'", "'") if waits else ''
        it['status'] = 'proposed' if re.search(r'definition|defined|decision first|\(Jason\)', w) else 'decided'
        it['note'] = 'Coming: ' + (w or 'no wait named')
    else:
        it['status'] = 'live'
        if status == 'partial':
            it['check'], it['check_by'] = 'not passing', 'manifests.php marks it partial'
        else:
            it['check'], it['check_by'] = S.page_check(route)
    MITEMS.append(it)
if True:
    it = next(i for i in MITEMS if i['manifest'] == 'dashboard/stream')
    it['note'] = 'Also Pulse → Stream (Workspaces::MERGED, the same section)'

# ---- the front end and the rest of the inventory
DROP = {  # inventory items that are an admin section: replaced by the manifest's own item
    'Stories for place pages': ['explore/stories'], 'Waypoints & routes': ['explore/routes'], 'Admin → Local': ['local/listings'], 'Single Home Rule': ['local/homes'],
    'Admin → Social → Groups': ['social/groups'], 'Admin → Market': ['market/items'], 'Catalog rules': ['market/catalog'], 'The Vault': ['config/vault'],
    'The admin\'s Pulse': ['dashboard/stream'], 'The System Control Board': ['config/control'], 'Staging in safe mode': ['config/staging', 'config/releases'],
    'The page builder for our own pages': ['market/pages'], 'Back-office': ['backoffice/orders'], 'Growth': ['growth/calls'], 'Quotes & deals': ['growth/quotes', 'concierge/clients'],
    'Fulfillment': ['backoffice/fulfillment', 'backoffice/inventory', 'backoffice/consent', 'backoffice/vault'], 'Templates': ['config/templates', 'config/branding', 'config/storage'],
    'Text messages': ['config/notify'], '5 W\'s lexicon': ['insights/fivew', 'insights/emergency'],
}
OWN = [  # (name starts with, owner16); anything listed in Platform must match one
    ('W2\'s refresh count', 'System Configuration'), ('Data cleanup', 'System Configuration'), ('The duplicates fix', 'System Configuration'),
    ('Plans made for agencies', 'Marketplace'),
    ('A person\'s public profile', 'Accounts'), ('The profile\'s Videos tab', 'Accounts'), ('The member Dashboard', 'Accounts'), ('The Gallery', 'Accounts'), ('Gallery item', 'Accounts'),
    ('Facebook and Instagram for members', 'Accounts'),
    ('The member\'s Pulse', 'Dashboard & Pulse'), ('Messages and notices through the one dispatcher', 'Dashboard & Pulse'), ('Email through Amazon SES', 'Dashboard & Pulse'),
    ('Pushes', 'Dashboard & Pulse'),
    ('Admin → Social → Business groups', 'Social 360°'),
    ('Storefront', 'TRVGO – Storefront'), ('Connect your domain', 'TRVGO – Storefront'), ('Builder step 6', 'TRVGO – Storefront'), ('Shop → Domains', 'TRVGO – Storefront'),
    ('Your domains', 'TRVGO – Storefront'), ('Domain sales', 'TRVGO – Storefront'),
    ('Legal texts published', QUESTION), ('Terms and privacy', QUESTION), ('Home: search first', QUESTION), ('The FAQ', QUESTION), ('Ask Traversence', QUESTION),
    ('Universal search', QUESTION), ('Search Phase C', QUESTION), ('DMCA notices', QUESTION), ('The admin engine', QUESTION),
    ('Sign in', 'Accounts'), ('Sign up', 'Accounts'), ('Two-step', 'Accounts'), ('Delete my account', 'Accounts'), ('Data access requests', 'Accounts'),
    ('One uploader', 'System Configuration'), ('The video worker', 'System Configuration'), ('The order system\'s improvements', 'Back-Office'),
]
DEFAULT = {'Discovery': 'Discovery', 'Directory': 'Directory', 'Community': 'Community', 'Marketplace': 'Marketplace', 'Knowledge Engine': 'System Configuration'}
WHY_Q = {
    'Legal texts published': 'site-wide legal text; no front-end gauge holds site-wide pages, and its admin face (Content templates?) isn\'t named',
    'Terms and privacy': 'a site-wide public page with no front-end gauge',
    'Home: search first': 'the way into all four; no front-end gauge holds it',
    'The FAQ': 'a site-wide public page; its admin face is none',
    'Ask Traversence': 'a site-wide public page',
    'Universal search': 'its public face is on every page; its admin face is Insights → Search workbench (owned there)',
    'Search Phase C': 'the learning search, with Universal search',
    'DMCA notices': 'its public pages (proposed) have no front-end gauge; its admin face is Back-Office → DMCA queue (owned there)',
    'The admin engine': 'the admin\'s shell (router, manifests, the one endpoint) is no module\'s section',
}

# the other face: manifest section -> front-end items it pairs with (each link shown from both gauges)
FACES = {
    'local/listings': ['A business\'s page'], 'local/claims': ['Claim a listing', 'Verify a listing'], 'local/review': ['Adding a business'], 'local/nexus': ['The Nexus'],
    'local/identities': ['The duplicates fix'], 'local/places': ['Natural landmark page'], 'local/recreation': ['Public recreation place page'],
    'local/pins': ['Where a listing is'], 'local/hidden': ['An owner opens'], 'local/categories': ['Directory search'], 'local/taxonomy': ['Hub, geo-hub', 'The area data'],
    'explore/journeys': ['One journey', 'Your journeys', 'Suggested journeys'], 'explore/experiences': ['One experience', 'Your experiences'],
    'explore/stories': ['The place page'], 'explore/place_guides': ['Place guides at every level', 'A guide built while you wait'],
    'explore/events': ['Live events from the crawler'], 'explore/guides': ['Resource guides'], 'explore/howto': ['How-to guides'], 'explore/routes': ['Trips R5'],
    'market/items': ['One Marketplace post', 'One Marketplace item', 'Your Marketplace posts'], 'market/rules': ['The Marketplace rules'],
    'market/bizitems': ['Businesses\' items'], 'market/exchange': ['Post on the Marketplace'], 'market/storefronts': ['Storefronts on trvgo.us', 'Storefront Studio 5a'],
    'market/catalog': ['Market 360° catalog'], 'market/gateways': ['Checkout through the business'], 'market/compliance': ['Market 360° Phase B'],
    'market/domains': ['Connect your domain'], 'market/codes': ['GO Codes and QR codes', 'Managing your GO Codes'],
    'social/groups': ['Community groups', 'One group as a content page'], 'social/reports': ['Charters, business-owned groups'], 'social/galleries': ['The Gallery'],
    'social/events': ['Events, one tool'], 'growth/promotions': ['A promotion\'s page'], 'backoffice/orders': ['Your orders', 'The order system\'s improvements'],
    'backoffice/plans': ['Plans and prices'], 'backoffice/dmca': ['DMCA notices'], 'backoffice/vault': ['Claim a listing'],
    'insights/crawler': ['W1 ', 'W2 ', 'W3 ', 'W4 ', 'W5 ', 'W6 ', 'W7 ', 'W8 ', 'W9 ', 'W10 ', 'W11 ', 'W12 ', 'W13 ', 'W14 '], 'insights/index': ['W0 '],
    'insights/cleanup': ['Data cleanup'], 'insights/sources': ['W3 '], 'insights/search': ['Universal search', 'Search Phase C'], 'insights/topics': ['W6 '],
    'config/workflows': ['W0 ', 'W1 ', 'W2 ', 'W3 ', 'W4 ', 'W5 ', 'W6 ', 'W7 ', 'W8 ', 'W9 ', 'W10 ', 'W11 ', 'W12 ', 'W13 ', 'W14 '],
    'config/communications': ['Messages and notices through the one dispatcher', 'Email through Amazon SES', 'Pushes'], 'config/stock': ['Card photos'],
    'config/ai': ['Guides and journeys on demand'], 'config/storage': ['One uploader'], 'config/register': ['The video worker', 'Park Service photos'],
    'config/notify': ['Checking a phone'], 'dashboard/stream': ['The member\'s Pulse'], 'config/content': ['Legal texts published'],
}


def build():
    items, dropped = [], []
    for sec, d in S.SECTIONS.items():
        for it in d['items']:
            it = dict(it, listed_in=sec, other_face=[])
            dk = next((k for k in DROP if it['name'].startswith(k)), None)
            if dk:
                dropped.append((it['name'], DROP[dk]))
                it['owner16'] = 'replaced by ' + ', '.join(DROP[dk])
                continue
            o = next((g for p, g in OWN if it['name'].startswith(p)), None) or DEFAULT.get(sec)
            assert o, 'no owner for ' + it['name']
            it['owner16'] = o
            if o == QUESTION:
                it['why'] = next(v for k, v in WHY_Q.items() if it['name'].startswith(k))
            items.append(it)
    items += MITEMS
    # each item owned exactly once, every rule used
    names = [i['name'] for i in items]
    assert len(names) == len(set(names)), 'an item counted twice'
    assert all(i['owner16'] in GAUGES + [QUESTION] for i in items)
    used = {k for k in DROP if any(n.startswith(k) for n, _ in dropped)}
    assert used == set(DROP), set(DROP) - used
    for mk, prefixes in FACES.items():
        m = next(i for i in MITEMS if i['manifest'] == mk)
        for p in prefixes:
            fs = [i for i in items if i['name'].startswith(p) and 'manifest' not in i]
            assert fs, (mk, p)
            for f in fs:
                m['other_face'].append(f'{f["name"]} ({f["owner16"]})')
                f['other_face'].append(f'{m["name"]} ({m["owner16"]})')
    return items, dropped


def stats(items, g):
    its = [i for i in items if i['owner16'] == g]
    scope = [i for i in its if i['status'] != 'proposed']
    done = sum(1 for i in scope if i['status'] == 'live' and i.get('check') == 'passing')
    return dict(items=len(its), in_scope=len(scope), complete=done, pct=round(done / len(scope) * 100, 1) if scope else 0.0,
                held=sum(1 for i in scope if i['status'] == 'held'), decided=sum(1 for i in scope if i['status'] == 'decided'),
                not_passing=sum(1 for i in scope if i['status'] == 'live' and i.get('check') == 'not passing'),
                not_checked=sum(1 for i in scope if i['status'] == 'live' and i.get('check') == 'not checked'), proposed=len(its) - len(scope))


def write():
    items, dropped = build()
    old = sum(len(d['items']) for d in S.SECTIONS.values())
    A = {g: stats(items, g) for g in GAUGES}
    q = [i for i in items if i['owner16'] == QUESTION]
    two = sum(1 for i in items if i['other_face'] and 'manifest' not in i)
    LAB = {'live': 'Live', 'held': 'Built, held', 'decided': 'Decided, not built', 'proposed': 'Proposed (out of scope)'}
    L = ['# The 16 development gauges: every item, its one owner (a proposal for Jason)', '',
         'Jason approved remapping the inventory onto his 16 development gauges (2026-11-22): 6 for the front end, 10 for the back end, the',
         'admin\'s modules as `app/admin/manifests.php` has them. Generated by `mockups/src/sections16.py` from `inventory.json` and the newest',
         f'mirror\'s manifests.php ({MANIFEST_SRC}). Specs only: no gauge built on the board, nothing merged.', '',
         '**Ownership is where the item lives.** A public page or flow belongs to its front-end gauge. An admin section belongs to the module',
         'manifests.php puts it in: computed from the file, not judged. A feature with both faces is two items, each owned once, and each',
         'one\'s drill-down links the other. **Complete** = live and passing its check, over the gauge\'s signed scope; one needle per gauge,',
         'everything else in the drill-down. Every % is a **draft: the scope isn\'t signed off**. The generator stops if any item is owned',
         'twice, if any rule matches nothing, or if a face link points at nothing.', '',
         '## The 16 gauges', '', '| # | Gauge | Items | In scope | Complete (draft) | Held | Decided, not built | Live, not passing | Live, not checked |',
         '|---|---|---|---|---|---|---|---|---|']
    for n, g in enumerate(GAUGES, 1):
        s = A[g]
        L.append(f'| {n} | **{g}**{" (back end)" if n > 6 else ""} | {s["items"]} | {s["in_scope"]} | **{s["pct"]}%** | {s["held"]} | {s["decided"]} | {s["not_passing"]} | {s["not_checked"]} |')
    L += [f'| | Questions (no gauge yet) | {len(q)} | | | | | | |', f'| | **All** | {len(items)} | | | | | | |', '',
          f'**The count: {len(items)} items, up {len(items) - old} from {old}.** {len(MITEMS)} admin sections come from manifests.php (Pulse\'s three',
          'sections are the Dashboard\'s own, `Workspaces::MERGED`, counted once), and the '
          f'{len(dropped)} inventory items that were an admin section, or a bundle of them, are replaced by the sections themselves. '
          f'{two} inventory items (front-end pages and flows, and the engine\'s workflows) now link an admin face, each counted once on its own side.', '',
          '**Where the Knowledge Engine landed: System Configuration.** manifests.php puts the Workflows section in `config`',
          '(`config/workflows`, line 168, "Workflows"), so W0–W14 and the data fixes (data cleanup, the duplicates fix, W2\'s count fix) are owned',
          'there. Their outputs still count on the front end (guides, journeys, landmarks and events in Discovery; listings in Directory). Their',
          'admin consoles are Insights sections (the crawler console, `insights/crawler`; the index, `insights/index`; data cleanup,',
          '`insights/cleanup`; sources), owned by Insights & Research and linked as each workflow\'s other face.', '',
          '## Questions (never guessed)', '']
    for i in q:
        L.append(f'- **{i["name"]}** ({LAB[i["status"]]}): {i["why"]}.')
    L += ['- **Two faces of the engine:** the data cleanup workflow (System Configuration) and the Data cleanup section (Insights) are two',
          '  items, as the rule asks. Is that the split Jason wants, or should a workflow and its console be one item?',
          '- **Coming cards whose wait names a definition or decision** (Single Home Rule, Events, Consent hub, 5 W\'s, Emergency resources, Text',
          '  messages) are Proposed, out of scope until defined; the rest of the Coming cards are Decided, not built.', '',
          '## What changed from the 6-gauge proposal', '',
          '- **Platform** is gone: sign-in, profile, the Gallery and account pieces went to **Accounts**; Pulse and messages to **Dashboard &',
          '  Pulse**; the uploader and the video worker to **System Configuration** (their admin homes, Storage & backups and the Service register);',
          '  our orders to **Back-Office**. Home, the FAQ, the AI page, universal search, legal pages, DMCA\'s public pages and the admin shell have',
          '  no home and are questions.',
          '- **Knowledge Engine** is no longer its own gauge: manifests.php puts Workflows in System Configuration.',
          '- **Storefronts, Studio and domains** moved from Marketplace to **TRVGO – Storefront**.',
          '- **Every admin section is its own item** in its module\'s gauge (they were bundled or missing before), so the back end is computed',
          '  from manifests.php rather than judged.',
          '- `crossover.md` and `ownership-table.md` stay as the history of the 6-gauge proposal.', '']
    for n, g in enumerate(GAUGES, 1):
        s = A[g]
        L += ['---', '', f'## {n}. {g}: {s["items"]} items, Complete {s["pct"]}% (draft), held {s["held"]}', '',
              '| Item | Status | Source | The other face |', '|---|---|---|---|']
        for st in ('live', 'held', 'decided', 'proposed'):
            for i in [i for i in items if i['owner16'] == g and i['status'] == st]:
                stat = LAB[st] + (f', {i["check"]}' if st == 'live' else '') + (f' <br>*{i["note"]}*' if i.get('note') else '')
                L.append(f'| {i["name"].replace("|", "/")} | {stat} | {i["source"].replace("|", "/")} | {"; ".join(i["other_face"]) or "—"} |')
        L.append('')
    L += ['---', '', '## Inventory items replaced by their admin sections', '', '| Inventory item | Replaced by (manifests.php) |', '|---|---|']
    L += [f'| {n} | {", ".join(k)} |' for n, k in dropped]
    open(OUT + 'development-gauges.md', 'w').write('\n'.join(L) + '\n')
    d = json.load(open(OUT + 'inventory.json'))
    by = {i['name']: i for i in items}
    for sec in d['sections'].values():
        for it in sec['items']:
            src = by.get(it['name'])
            it['owner16'] = src['owner16'] if src else next(f'replaced by {", ".join(k)}' for n, k in dropped if n == it['name'])
            it['other_face'] = src['other_face'] if src else []
    d['manifest_items'] = MITEMS
    d['gauges16'] = {g: A[g] for g in GAUGES}
    d['gauges16_rule'] = 'Ownership is where the item lives; admin sections by manifests.php; two faces are two items, each linking the other (Jason, 2026-11-22).'
    d['questions16'] = [dict(name=i['name'], why=i['why']) for i in q]
    json.dump(d, open(OUT + 'inventory.json', 'w'), indent=1)
    print('items', len(items), 'from', old, 'manifest', len(MITEMS), 'dropped', len(dropped), 'two-faced front items', two, 'questions', len(q))
    for g in GAUGES:
        print(g, A[g]['items'], A[g]['in_scope'], A[g]['pct'], 'held', A[g]['held'])


if __name__ == '__main__':
    S.write()
    S.write_crossover()
    write()
