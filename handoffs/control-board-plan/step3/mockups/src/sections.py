# The four build gauges' baseline (Jason, 2026-11-22): every item in scope per section, with its status, source, owner and check.
# Writes step3/sections/inventory.md and inventory.json. Merges the session manager's outline (step3/sections-outline.md) with
# Operations' inventory from the repo, the live mirror (Sync e229587, 22:55 UTC Nov 21) and the release records (#1-#247).
# Spec data, not site code. Status words: live (and its check: passing / not passing / not checked), held, decided, proposed.
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import board  # the page states from the rendered check, read the same way as the runtime board

ROOT = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', '..', '..')) + '/'   # the repo, wherever it's checked out
OUT = ROOT + 'handoffs/control-board-plan/step3/sections/'
PAGES_MD = open(ROOT + 'site-audit/pages.md').read().split('\n')
MIRROR_AT = 'Sync e229587, 22:55 UTC Nov 21'
REL = {}
for f in os.listdir(ROOT + 'deliveries/releases'):
    d = json.load(open(ROOT + 'deliveries/releases/' + f))
    REL[d['id']] = d

PATHS = {p.get('id'): p['status'] for p in board.PATHS['results']}
PSTATE = {}
for p in board.PAGES:
    PSTATE.setdefault(board.strip_q(p['route']), (p['state'], p['words']))


def pg(route):
    """pages.md's line for a route."""
    for i, l in enumerate(PAGES_MD, 1):
        if l.startswith('|') and f'`{route}' in l:
            return f'site-audit/pages.md:{i}'
    return 'site-audit/pages.md'


def rel(*ns):
    out = []
    for n in ns:
        d = REL[n]
        assert d['state'] == 'live', n
        out.append(f'release #{n} (live {d["live_at"][:10]})')
    return ', '.join(out)


# owners named by the hand-offs
O_DESIGN = 'Design and trips (handoffs/design.md, session_01CXLi5jkgtUikcdUQ4Wmv28)'
O_LISTINGS = 'Business listings (handoffs/business-listings.md, session_01CXLi5kipJLJoRvo3jqLnQQ)'
O_OPS = 'Operations (handoffs/operations-2026-11-20.md, session_01CXLi5mGX2sg5QS11gg3Wrh)'
O_COMMS = 'Communications (handoffs/communications.md, session_01CXLi5krYPakdEQtKuVw14F)'
O_DISP = 'The dispatcher (handoffs/dispatcher.md, session_01CXLi5hJ4C32FpJfNTaTa2z)'
O_ORDERS = 'Order processing (handoffs/order-processing.md)'
O_BUILDER = 'The builder (handoffs/0159-the-builder.md)'
O_COMMUNITY = 'The Community build (community-scope.md; finished)'
O_DOMAINS = 'Domains (handoffs/domains.md)'
O_BOARD = 'Control board (handoffs/control-board-2026-11-20.md)'
O_SM = 'Session manager (handoffs/session-manager.md, session_01CXLi5iruQZSTYKyuR7b1SN)'


def page_check(route):
    k = board.strip_q(route)
    st, words = PSTATE.get(k, ('n', 'not checked yet'))
    if words.startswith('Coming'):
        words = 'not opened yet (pages.md still says Coming; the page is in the mirror)'
    return {'g': 'passing', 'a': 'not passing', 'r': 'not passing', 'n': 'not checked'}[st], f'the rendered check at 1280 and 390: {words}'


def path_check(pid):
    s = PATHS.get(pid)
    if s is None:
        return 'not checked', 'no live check reads it'
    return {'passed': 'passing', 'issues': 'not passing', 'failed': 'not passing', 'skipped': 'not checked'}[s], f'the paths check "{pid}": {s}'


def I(name, kind, status, source, owner='', check=None, note='', outline=False):
    """status: live, held, decided, proposed. check: ('page', route) or ('path', id) or ('run', words, passing?) or None."""
    it = dict(name=name, kind=kind, status=status, source=source, owner=owner, note=note, outline=outline)
    if status == 'live':
        if check is None:
            it['check'], it['check_by'] = 'not checked', 'no live check reads it yet'
        elif check[0] == 'page':
            it['check'], it['check_by'] = page_check(check[1])
        elif check[0] == 'path':
            it['check'], it['check_by'] = path_check(check[1])
        else:
            it['check'], it['check_by'] = check[2], check[1]
    return it


def P(route, name, section_note='', owner='', extra_src='', check_route=None, note=''):
    """A page from pages.md, confirmed in the mirror."""
    return I(name, 'page', 'live', f'{pg(route)}; in the mirror ({MIRROR_AT})' + (f'; {extra_src}' if extra_src else ''), owner, ('page', check_route or route), note or section_note)


W1_RUN = ('run', 'the runtime board\'s "Gathering reconciles": W1 paused by two trips, 0 records in for 24 hours (alarms snapshot, Nov 21)', 'not passing')
W3_RUN = ('run', 'the runtime board: W3 does nothing while its two switches stay Off by the system (01-lift-reconstruction.md (b), (c))', 'not passing')

SECTIONS = {}

# ---------------------------------------------------------------- Discovery ("Let's Explore")
SECTIONS['Discovery'] = dict(construct="Let's Explore", owners='Design and trips (pages, trips, the place page); Operations (the Knowledge Engine that fills it)', items=[
    P('/discovery/', "Let's Explore: overview, map, regions, outdoors, things to do, journeys, the Trip Planner", owner=O_DESIGN, extra_src=rel(158, 159, 202)),
    P('/hub/?hub=', 'Hub, geo-hub and cluster landing page', extra_src='pages.md says "Live, placeholder"; ' + rel(171), owner=O_DESIGN),
    P('/hub/build.php?city=&state=&q=', 'A guide built while you wait for a searched place (0147)', extra_src='settles "live once uploaded": hub/build.php is in the mirror'),
    P('/place/landmark.php?id=', 'Natural landmark page (USGS names)'),
    P('/place/recreation.php?id=', 'Public recreation place page (Recreation.gov)'),
    I('The place page: photo corner, what\'s in the photo, the numbers, ⋯ toolkit, Plan a trip here', 'feature', 'live', rel(211) + '; decisions/0058 notes 2026-11-19', O_DESIGN),
    I('Card photos for parks and landmarks (real photos first, looked up on open, on-the-spot photos)', 'feature', 'live', rel(205, 213, 217) + '; decisions/0143 notes', O_DESIGN),
    I('Hero tidy (⋯ starts Link to, Claim, Add to a trip)', 'feature', 'live', rel(230) + '; handoffs/hero-tidy.md', O_DESIGN),
    I('Park Service photos (NPS photo API)', 'integration', 'held', 'handoffs/design.md §3.8: built, skipped until NPS_API_KEY is set; decisions/0143', O_DESIGN, note='held for a key'),
    P('/experiences/view.php?id=', 'One experience (0059)'),
    P('/user/experiences/', 'Your experiences'),
    P('/journeys/view.php?id=', 'One journey (0056)'),
    P('/journeys/suggested.php?id=', 'Suggested journeys built from a guide\'s places (0146)', extra_src='decisions/0146:163 Built; settles "live once uploaded"'),
    P('/user/journeys/', 'Your journeys: write or edit (contributors)'),
    I('Journeys\' Link button resolves (place:jr:N)', 'feature', 'decided', 'handoffs/design.md §4: no case in Links::resolve (a gap found 2026-11-20)', O_DESIGN, outline=True),
    P('/guide/how.php?id=', 'How-to guides from official pages (0063)'),
    P('/guide/resources.php?id=', 'Resource guides: who to call in an area (0061)'),
    I('Place guides at every level of the funnel (0043, 0131)', 'feature', 'live', 'decisions/0131:105 Built (2026-10-30); admin explore/place_guides (manifests.php:57)', O_OPS, ('path', 'town-guide')),
    I('Guides and journeys on demand (0147), within the AI cap', 'workflow', 'live', 'decisions/0147; ' + rel(32), O_OPS),
    I('Stories for place pages (Admin → Explore → Stories)', 'admin tool', 'live', 'app/admin/manifests.php:56 (W); decisions/0043', O_OPS),
    I('Live events from the crawler in guides and on venue listings (0133)', 'feature', 'live', 'community-scope.md:23; app/lib/LiveEvents.php in the mirror', O_OPS, note='Shown in Things to do; the events tool itself is counted in Community'),
    I('Trips R1: Send to Maps', 'feature', 'live', rel(219) + '; decisions/0174 §4', O_DESIGN),
    I('Trips R2: Add to a trip, Your trips, a guest\'s trip on the device', 'feature', 'live', rel(220) + '; decisions/0174 §2', O_DESIGN, ('page', '/user/trips/')),
    I('Trips R3: the trip page (days, order, times against hours, the map, Print)', 'feature', 'live', rel(221) + '; decisions/0174 §3', O_DESIGN, ('page', '/user/trips/trip.php?id=')),
    I('Trips R4: share a link, Personal Guides, counts', 'feature', 'live', rel(227) + '; decisions/0174', O_DESIGN, ('page', '/guide/personal.php?id=')),
    I('Trips R5: routes on our own site (openrouteservice)', 'feature', 'decided', 'decisions/0174 §5; handoffs/design.md §3.2: not started, its terms to check first', O_DESIGN, outline=True),
    I('The map release: trails and routes in their own colour (--tv-route)', 'feature', 'decided', 'brand.md §4 note (Jason, 2026-11-18); handoffs/design.md §3.5', O_DESIGN, outline=True),
    I('Clean hub, geo-hub and cluster addresses /{hub}/{geohub}/{cluster}', 'page', 'decided', 'decisions/0013; ' + pg('/{hub-slug}') + ' (Coming)', outline=True),
    I('Editorial guides /guide/{slug} with a [directory_feed] shortcode', 'page', 'proposed', pg('/guide/{slug}') + ' (Coming, "Designed in —": no decision)', outline=True),
    I('Waypoints & routes: map waypoints and GPX/KML for journeys and guides', 'admin tool', 'decided', 'app/admin/manifests.php:62 (Coming: "A later wave"); aos-gap-review.md §8'),
    I('Everything placed in the discovery funnel, active or planned (0170)', 'feature', 'live', 'decisions/0170; app/lib/HubOpening.php in the mirror', O_OPS, note='Built parts by section to confirm with 0170\'s own notes'),
    I('Am here: live check-ins', 'feature', 'proposed', 'app/ui/faq.md:1121 ("coming as a separate feature"); no decision found'),
    I('The area data: micro-clusters misnamed under Ancient Borderlands', 'feature', 'decided', 'handoffs/design.md §4; sections-outline.md §1 (owned by the session manager, not started)', O_SM, outline=True),
])

# ---------------------------------------------------------------- Directory ("Get Local")
SECTIONS['Directory'] = dict(construct='Get Local', owners='Design and trips (Get Local\'s page); Business listings (claim, the owner\'s tools); Operations (W1 and W2)', items=[
    P('/directory/', 'Get Local: businesses near you, list and map', owner=O_DESIGN, extra_src=rel(164, 178, 208)),
    I('Get Local R2: Filters, Arrange, Sort, Table, the list beside the map, the comments box, the card tools', 'feature', 'live', rel(208) + '; handoffs/get-local-r2.md', O_DESIGN, ('path', 'eat-near-me')),
    I('Set my place and distance on Get Local', 'feature', 'live', 'the paths check "set-place"; rule J01', O_DESIGN, ('path', 'set-place')),
    P('/listing/view.php?id=', 'A business\'s page (hero, engage bar, comments, Link)', extra_src=rel(240)),
    P('/listing/location.php?id=', 'Where a listing is: move the pin, I\'m here'),
    P('/claim.php?listing=', 'Claim a listing (email or text code, who might claim, the form that fits)', owner=O_DESIGN, extra_src=rel(215) + '; decisions/0070'),
    P('/verify-listing.php', 'Verify a listing from its link'),
    P('/listing/businessportal.php', 'The business portal: claims, plan, the listing editor (listing side)', owner=O_LISTINGS, extra_src='decisions/0070, 0115'),
    I('An owner opens their own hidden listing\'s editor (case #22)', 'feature', 'live', rel(191) + '; handoffs/business-listings.md', O_LISTINGS, ('path', 'my-listing')),
    I('Edit my listing as its owner', 'feature', 'live', '/listing/api/update-metadata (' + pg('/listing/api/*') + ')', O_LISTINGS, ('path', 'edit-listing')),
    P('/listing/confirm.php?t=', 'A guest\'s message to an unclaimed listing: confirm the email', extra_src='decisions/0172; ' + rel(135) + ' (comms-all)'),
    P('/listing/requests.php?t=', 'Stop requests / report spam for an unclaimed listing\'s email', extra_src='decisions/0172; ' + rel(135)),
    I('Message a business (guest inquiry, listing messages)', 'feature', 'live', '/api/guest-inquiry.php (' + pg('/api/guest-inquiry.php') + '); decisions/0052, 0172', O_COMMS, ('path', 'message-business')),
    I('Adding a business: live in minutes, verified with one tap (0071)', 'feature', 'live', 'decisions/0071; claim.php?new=1 (' + pg('/claim.php?listing=') + '); app/lib/AddBusiness.php', O_LISTINGS),
    I('Help us find this business (a public request)', 'feature', 'live', rel(51) + '; app/lib/HelpFind.php', O_OPS),
    I('The Nexus: organizations with many locations (0054)', 'feature', 'live', 'decisions/0054; app/lib/Nexus.php; Admin → Local → Nexus (manifests.php:41)'),
    I('Directory search, categories, "near me" with miles (0044, 0053, 0060 A–B, 0068)', 'feature', 'live', '/api/search_listings.php, /api/categories.php (' + pg('/api/search_listings.php') + '); decisions/0060 Phases A and B built', check=('path', 'eat-near-me')),
    I('Search Phase C (0060)', 'feature', 'live', 'decisions/0061:7 replaces 0060\'s Phase C and is built in three batches; settles the outline\'s "decided, not built"', check=('run', 'the search scorecard: 101 of 106 cases pass (Nov 21)', 'not passing')),
    I('"3 within 5 mi" coverage promise (0060)', 'feature', 'live', 'decisions/0060; the runtime board\'s "3 within 5 mi" snapshot', check=('run', 'no probe reads it as defined (runtime board: not checked yet)', 'not checked'), outline=True),
    I('OpenStreetMap businesses where we have no listings yet (0065)', 'feature', 'live', '/api/open-places.php (' + pg('/api/open-places.php') + '); decisions/0065'),
    I('W2\'s refresh count (L9: counted more than received)', 'workflow', 'decided', '03-cards.md §28.2: a step-4 fix, count at the boundary', O_OPS),
    I('Data cleanup: find, preview and act on what the old crawler made', 'admin tool', 'live', rel(49, 50, 52, 53, 54) + '; decisions/0163 §8 stage 1b; settles the outline\'s "to confirm"', O_OPS),
    I('The duplicates fix: one identity check, the recheck, Vet this place', 'workflow', 'live', rel(58) + ' (Release B part 1); handoffs/duplicates.md; decisions/0164 §3.1–3.2; settles the outline\'s "to confirm"', O_OPS),
    I('Clean listing addresses /listing/{slug}', 'page', 'proposed', pg('/listing/{slug}') + ' (Coming, "Designed in —": no decision)', outline=True),
    I('Single Home Rule: one home anchor per listing, conflicts settled', 'admin tool', 'decided', 'app/admin/manifests.php:50 (Coming: "The rule to be defined (Jason)"); aos-gap-review.md §8', outline=True),
    I('Admin → Local: listings, find/merge/bulk, intake, review, claims, identities, map pins, categories, taxonomy', 'admin tool', 'live', 'app/admin/manifests.php:36–49 (live sections)', O_OPS),
    I('Plans made for agencies and nonprofits', 'feature', 'proposed', 'app/ui/faq.md:938, 948 ("coming"); no decision found'),
    I('Checking a phone with a texted code (claim)', 'feature', 'decided', 'app/ui/faq.md:1867 ("coming"); decisions/0070; texts wait on 0172 §5', O_COMMS),
])

# ---------------------------------------------------------------- Community ("Connect")
SECTIONS['Community'] = dict(construct='Connect', owners='The Community build (finished); Design (3d, its look); Communications (0172, 0176)', items=[
    P('/community/', 'Community groups: find, join or start one (built to the mockup, adapted)', owner=O_DESIGN, extra_src=rel(237) + ' (3d); settles the outline\'s "is 3d live"'),
    P('/community/group.php?id=', 'One group as a content page (0137 §4)', owner=O_DESIGN, extra_src=rel(237)),
    I('Join a group', 'feature', 'live', 'community-scope.md:10; decisions/0047', O_COMMUNITY, ('path', 'join-group')),
    I('Group roles: owner, moderator, member', 'feature', 'live', 'community-scope.md:12; decisions/0102 §4b', O_COMMUNITY),
    I('Charters, business-owned groups, moderation run by the group, notices sent once (0137 steps 1–5)', 'feature', 'live', 'community-scope.md:16–20, 242', O_COMMUNITY),
    I('Tags on groups, places a group is about, chapters, topic plus place in search, growing a group (0136)', 'feature', 'live', 'community-scope.md:247–250 (live 2026-11-02)', O_COMMUNITY),
    I('Admin → Social → Groups, Reports, Standing, Takedowns, Galleries', 'admin tool', 'live', 'app/admin/manifests.php:83–88', O_COMMUNITY, ('path', 'staff-report')),
    I('Private circles; groups in Universal Search; the Community feed', 'feature', 'live', 'community-scope.md:21–22', O_COMMUNITY),
    P('/join.php?i=', 'Invite link'),
    I('Moments and Keep (0153 zip 1)', 'feature', 'live', 'decisions/0153:147 Built; /user/post.php in the mirror', check=('page', '/user/post.php?id=')),
    I('Snaps (0153 zip 2)', 'feature', 'live', 'decisions/0153:172 Built'),
    I('Posting as a business, inviting from the To line, the visitor view (0153 zip 3)', 'feature', 'live', 'decisions/0153:190 Built'),
    I('0153 zip 4', 'feature', 'decided', 'decisions/0153:3 ("zips 2–4 to come"; zips 2 and 3 have Built notes, zip 4 none)'),
    P('/user/profile.php?id=', 'A person\'s public profile (Posts tab)'),
    P('/user/profile.php?id=&tab=videos', 'The profile\'s Videos tab: the Channel and playlists (0153 §7)', extra_src='decisions/0154:116 Built'),
    P('/user/dashboard.php', 'The member Dashboard: Pulse, Address Book, links, saved searches, roles', owner=O_DESIGN, extra_src=rel(237)),
    I('The member\'s Pulse: needs as rows, notices', 'feature', 'live', 'app/lib/MemberPulse.php; tools/notify-tests/member-pulse.php; ' + rel(237), O_DISP),
    I('Likes and comments on content pages (engage bar)', 'feature', 'live', '/api/engage.php (' + pg('/api/engage.php') + '); decisions/0058', check=('path', 'comment')),
    I('Like as a guest (3, then Keep going)', 'feature', 'live', 'decisions/0058', check=('path', 'like-guest')),
    I('Messages and notices through the one dispatcher (0172 steps 2–7)', 'feature', 'live', rel(130, 135), O_COMMS),
    I('Email through Amazon SES, live mode', 'integration', 'held', 'community-scope.md:244–246 (mock until AWS production access); live mode is .env data: to confirm (asks)', O_COMMS, note='to confirm: SES_MODE', outline=True),
    I('Pushes: one per thing, paced, the site installable as an app', 'feature', 'live', rel(214) + '; handoffs/push-polish.md', O_COMMS),
    P('/gallery/', 'The Gallery: your photos and files (0126)', note='Ambiguous: Community or Platform (asked below)'),
    I('Gallery item, album and drop pages', 'page', 'live', pg('/gallery/album.php') + '; in the mirror', check=('page', '/gallery/album.php'), note='Answered 404 without an id in the rendered check'),
    I('Events, one tool for the whole site (0134 steps 1–8)', 'feature', 'decided', 'decisions/0134:248 Build order; no events store in the mirror; Admin → Social → Events is Coming (manifests.php:89)', note='Shown in Discovery\'s Things to do as a cross-link, counted here (the session manager\'s ruling)', outline=True),
    I('Ticket Sales for events (0134 §6b)', 'feature', 'decided', 'decisions/0134 §6a–6b, build step 6; app/ui/faq.md:17 ("Buying tickets is coming")', outline=True),
    I('Inactivity and the lifecycle job for groups (C4, 0103 §6)', 'workflow', 'decided', 'community-scope.md:216, 250; no lifecycle table in the mirror', O_COMMUNITY, outline=True),
    I('A member\'s private home place (C3)', 'feature', 'decided', 'community-scope.md:215, 251', O_COMMUNITY, outline=True),
    I('Flyers and badges (0137 step 7, C7)', 'feature', 'decided', 'community-scope.md:219, 251', O_COMMUNITY, outline=True),
    I('A business\'s bulk invitations, non-system mail to SES (C1)', 'feature', 'decided', 'community-scope.md:213, 252', O_COMMUNITY, outline=True),
    I('Steward member reports and Level 2 escalation', 'feature', 'decided', 'community-scope.md:33; master-aos §6', O_COMMUNITY, outline=True),
    I('Admin → Social → Business groups', 'admin tool', 'decided', 'community-scope.md:35; admin-architecture §6', O_COMMUNITY, outline=True),
    I('Group creation gated by trust score; signal-based visibility', 'feature', 'proposed', 'community-scope.md:41 (architecture §27; not in a decision)', outline=True),
    I('Community threads into the AI corpus (abstraction only)', 'feature', 'decided', 'community-scope.md:42; decisions/0010, 0011', outline=True),
    I('Facebook and Instagram for members (0168 Phase 1)', 'integration', 'decided', 'decisions/0168:5 ("accepted as the outline… Not built")'),
])

# ---------------------------------------------------------------- Marketplace ("Market")
SECTIONS['Marketplace'] = dict(construct='Market', owners='Order processing (held); the Market 360° and Storefronts builds (finished); Design (3e); Domains', items=[
    P('/market/', 'The Marketplace landing (post and connect)', extra_src='pages.md: "Live, placeholder"; decisions/0135'),
    P('/market/view.php', 'One Marketplace post'),
    P('/market/item.php', 'One Marketplace item'),
    P('/market/cart.php', 'The Marketplace cart'),
    P('/market/order.php', 'A Marketplace order'),
    P('/market/rules.php', 'The Marketplace rules'),
    P('/market/ticket.php', 'A ticket bought on the Marketplace'),
    P('/market/download.php', 'A bought download'),
    P('/user/market/', 'Your Marketplace posts'),
    P('/user/orders.php', 'Your orders'),
    I('Post on the Marketplace; offers, Sold / Got it (0135 steps 1–2)', 'feature', 'live', 'decisions/0135:161, 190 Built', check=('path', 'market-post')),
    I('Businesses\' items (0135 step 3)', 'feature', 'live', 'decisions/0135:226 Built'),
    I('Checkout through the business\'s own gateway (0135 step 4a)', 'feature', 'live', 'decisions/0135:353 Built; /business/pay-connect.php in the mirror'),
    I('Square catalog sync, card-page protection (0135 step 4b)', 'feature', 'live', 'decisions/0135:405 Built; settles the outline\'s "decided, not built"'),
    I('Market 360° Phase A: the modules and the Trust score (zip 1)', 'feature', 'live', 'decisions/0141:126 Built'),
    I('Market 360° Phase B: acceptance log, store policies, seller agreement, rulebook, chargeback packet (zips 2–3)', 'feature', 'live', 'decisions/0142:123, 151 Built'),
    I('Market 360° Phase C: the nine item kinds (zips 4–6 as one build: physical, service, made to order, tickets, digital, perishables, rentals, bookings, subscriptions)', 'feature', 'live',
      'decisions/0141:152 Built; market360-build-briefs.md:99 ("Built with zips 5 and 6 as one build"); settles the outline\'s "zips 5–6"'),
    I('Market 360° catalog: photos, publishing, deleting; the acting-as bar; the account bar', 'feature', 'live', 'decisions/0141:193, 203, 210, 221, 232'),
    I('Market 360° zip 7, Phase D and E: bundles, tax-exempt buyers, legal identity fields, support line, storefront themes, local deals digest, email campaigns', 'feature', 'decided',
      'market360-build-briefs.md:149–162; decisions/0141 §2–8; no Built note; settles the outline\'s "zip 7" (storefront themes are live as Studio 5b, #42)'),
    I('Owner ID check (Stripe Identity)', 'integration', 'held', 'market360-build-briefs.md:166 ("Waiting on setup"); decisions/0141 §3', note='held for setup', outline=True),
    I('State-record checks (AZCC, Arizona TPT, NM SoS)', 'integration', 'held', 'market360-build-briefs.md:167; 03-cards.md §28.3 (AZCC answers 403 pending our records request)', note='held for setup', outline=True),
    I('Legal texts published (terms, privacy, policies), an attorney\'s pass', 'page', 'held', pg('/terms.php') + ' (placeholder "to replace before launch"); market360-build-briefs.md:169', note='held for the attorney', outline=True),
    I('Catalog rules: pricing rules for businesses\' catalogs', 'admin tool', 'decided', 'app/admin/manifests.php:73 (Coming: "A later step")'),
    I('Admin → Market: items, rules, business items, offers, storefronts, gateways, compliance, domains, GO Codes, our pages, form entries', 'admin tool', 'live', 'app/admin/manifests.php:68–79', check=('path', 'staff-held-post')),
    I('Storefronts on trvgo.us/<shop> (0139 step 5a)', 'feature', 'live', 'decisions/0139:58 Built; trvgo/index.php in the mirror'),
    I('Connect your domain, bought or brought (0139 step 5b), through the Fly.io edge', 'feature', 'live', 'decisions/0139:91 Built; ' + rel(93), note='Review item 7: a failing custom domain also takes trvgo.us/<shop> down'),
    I('Storefront Studio 5a: a business\'s pages as blocks', 'feature', 'live', rel(41) + '; decisions/0159; settles the outline\'s "to confirm"', O_BUILDER),
    I('Storefront Studio 5b: the four themes', 'feature', 'live', rel(42) + '; decisions/0159', O_BUILDER),
    I('Storefront Studio 5c commerce blocks, 5d the studio bar', 'feature', 'decided', 'decisions/0159:7 ("Next: 5c… then 5d"); handoffs/0159-the-builder.md §0', O_BUILDER, outline=True),
    I('Builder step 6: AI writing, by plan', 'feature', 'decided', 'decisions/0159 Build order 6', O_BUILDER),
    P('/shop/', 'The Traversence Shop: plans, packs, GO Codes, one cart (0154)', extra_src='decisions/0154:116, 143 Built'),
    P('/plans.php', 'Plans and prices'),
    P('/promo.php', 'A promotion\'s page'),
    P('/qr/', 'GO Codes and QR codes (0094–0096)'),
    P('/qr/cart.php', 'Buying GO Codes'),
    P('/qr/manage.php', 'Managing your GO Codes'),
    P('/shop/domains.php', 'Shop → Domains: search, price, add to cart (0169 §1a)', owner=O_DOMAINS, extra_src=rel(71, 76)),
    P('/user/domains/', 'Your domains and your wallet (0169 §1a, 0166 §6a)', owner=O_DOMAINS, extra_src=rel(71)),
    I('Domain sales switched on (Domain sales setting)', 'feature', 'held', rel(72, 75) + ' (its own switch; Test only until set); sections-outline.md §4', O_DOMAINS, note='held for a setting', outline=True),
    I('The Shop\'s App store and one lifecycle for every app (0169 §1–§6)', 'feature', 'decided', 'decisions/0169:141 Build order 1', outline=True),
    I('First apps: QuickBooks Bookkeeper, QuickBooks Payments, Stripe Payments, Facebook Personal, Facebook Business, Storefront (0169 build order 2)', 'feature', 'decided', 'decisions/0169:145–156; decisions/0166, 0168'),
    I('Provider-neutral payments and our own ledger (0166 steps 1–3)', 'feature', 'decided', 'decisions/0166:5 ("queue for future work"; §6a wallet built)'),
    I('Add-ons and 1Password resold (0155)', 'feature', 'proposed', 'decisions/0155:5 (Proposed)'),
    I('Checkout and storefronts on the design tokens (Release 3e)', 'feature', 'decided', 'handoffs/design.md §3.4; design/css-theme-brand.md', O_DESIGN, outline=True),
])

# ---------------------------------------------------------------- Platform (not gauged unless Jason says so)
SECTIONS['Platform'] = dict(construct='(the platform underneath)', owners='Operations; Control board; Communications; the dispatcher; Design (admin)', items=[
    P('/', 'Home: search first, four doors, five tabs (0138)', extra_src='decisions/0138:95, 114 Built', note='Ambiguous: the way into all four'),
    P('/login.php', 'Sign in; create an account (18+, consent)', check_route='/login.php'),
    I('Sign in, including the human check; sign out', 'feature', 'live', 'decisions/0014, 0097', check=('path', 'sign-in')),
    I('Sign up (all steps)', 'feature', 'live', pg('/login.php'), check=('path', 'sign-up')),
    I('Sign in with Google, Apple, Microsoft, Facebook (0085, 0090)', 'integration', 'live', 'decisions/0090; app/lib/SocialAuth.php', note='Apple needs the Developer Program; Meta\'s app review (asks.md visit 16)'),
    I('Two-step sign-in for staff; captcha (0097)', 'feature', 'live', 'decisions/0097; /two-step.php in the mirror', check=('page', '/two-step.php')),
    I('Delete my account; restore (0091)', 'feature', 'live', 'decisions/0091; /user/delete-account.php in the mirror', check=('page', '/user/delete-account.php')),
    I('Data access requests, the export (DSAR, 0026)', 'feature', 'decided', pg('/privacy/dsar') + ' (Coming); decisions/0026; nothing in the mirror'),
    I('DMCA notices and counter-notices (0019)', 'feature', 'proposed', 'decisions/0019 (Proposed); ' + pg('/dmca-notice.php') + '; manifests.php:136 (Coming)'),
    P('/faq.php', 'The FAQ (0057)'),
    P('/ai/', 'Ask Traversence (AI page)'),
    I('Universal search, in place (0053, 0061, 0063, 0067)', 'feature', 'live', '/api/search.php (' + pg('/api/search.php') + ')', check=('path', 'search-typo')),
    I('The Vault (0156)', 'feature', 'live', 'decisions/0156; app/lib/Vault.php; ' + rel(235)),
    I('The admin engine: 10 modules, manifests, widgets, workflows (0109)', 'admin tool', 'live', 'decisions/0109; app/admin/manifests.php'),
    I('The admin\'s Pulse and Support inbox (0172, 0176)', 'admin tool', 'live', rel(138, 141, 147, 241), O_DISP),
    I('The System Control Board (0171, 0175, 0176)', 'admin tool', 'live', rel(127, 229, 231, 238, 239, 243, 245), O_BOARD),
    I('Staging in safe mode, releases through git (0145, 0159 steps 1–2, 0165)', 'workflow', 'live', rel(7, 10, 56, 57)),
    I('The page builder for our own pages (0159 steps 3–4)', 'feature', 'live', rel(18, 23, 27, 33, 34), O_BUILDER),
    I('One uploader; files on S3 and Cloudflare R2 (0122–0127, 0151)', 'feature', 'live', 'decisions/0127, 0151'),
    I('The video worker (0153 §8)', 'workflow', 'live', '/api/video/jobs.php in the mirror; decisions/0153 §8'),
    I('Back-office: our orders, products, vendors, work orders, QuickBooks (0098–0101, 0106, 0128, 0150)', 'admin tool', 'live', 'decisions/0098:237, 0128:140; app/admin/manifests.php:113–131; /vendor.php, /wo.php in the mirror', O_ORDERS,
      ('path', 'send-order'), note='Ambiguous: Traversence\'s own orders, or Marketplace (asked below)'),
    I('The order system\'s improvements (the first release carries the chargeFor fix)', 'feature', 'held', 'handoffs/order-processing.md; research/order-system-map-2026-11-19.md (Jason reviewing)', O_ORDERS, note='held for Jason\'s review', outline=True),
    I('Growth: calls, prospects, customers, scan cards, promotions, territories, funnel (0081, 0108)', 'admin tool', 'live', 'app/admin/manifests.php:93–100'),
    I('Quotes & deals; Concierge (White Glove clients, engagements, service catalog, asset studio)', 'admin tool', 'decided', 'app/admin/manifests.php:101, 105–108 (Coming)'),
    I('Fulfillment; Inventory & COGS; Consent hub; Verification vault', 'admin tool', 'decided', 'app/admin/manifests.php:132, 134, 135, 137 (Coming)'),
    I('Templates; Branding & SEO; Storage & backups', 'admin tool', 'decided', 'app/admin/manifests.php:193, 194, 196 (Coming)'),
    I('Text messages (Twilio)', 'integration', 'proposed', 'app/admin/manifests.php:195 (Coming: "Its own decision first", 0172 §5)'),
    I('5 W\'s lexicon; Emergency resources', 'admin tool', 'decided', 'app/admin/manifests.php:155–156 (Coming)'),
    I('Terms and privacy pages', 'page', 'live', pg('/terms.php'), check=('page', '/terms.php'), note='Live as placeholders; the real texts are held (Marketplace\'s legal texts item)'),
])


# ---------------------------------------------------------------- Knowledge Engine: one item per workflow (the session manager, 2026-11-22)
KE_STATE = {  # each workflow's real state; the check where one exists
    'W0': ('held', None, 'built and switched off until the team starts it (app/ui/faq.md:870; release #79 "shipped switched off"); whether it\'s been started is live data'),
    'W1': ('live', ('run', 'the runtime board: W1 paused by two task trips (overture_bulk #1290, foursquare_bulk #1288), 0 records in for 24 hours', 'not passing'), ''),
    'W2': ('live', ('run', 'the ledger\'s count check: ep:crawl/refresh:count open (received 1, counted 2), so its counts can\'t be trusted (03 §28.2)', 'not passing'), ''),
    'W3': ('live', ('run', 'the runtime board: W3 does nothing while its two switches stay Off by the system, left by a closed trip (01-lift-reconstruction.md (b), (c))', 'not passing'), ''),
    'W14': ('live', None, 'its licence_lookup (AZCC, NM SoS) isn\'t built yet (03 §26.9–26.10, §28.3)'),
}
KE_ITEMS = []
for _w, _n, _what, _where in board.WORKFLOWS:
    _st, _chk, _note = KE_STATE.get(_w, ('live', None, ''))
    KE_ITEMS.append(I(f'{_w} {_n.replace(chr(92), "")}', 'workflow', _st, f'app/lib/Automation.php WORKFLOWS (mirror); decisions/0164; carried by: {_where}', O_OPS, _chk,
                      (_note + '; ' if _note else '') + f'{_what.replace(chr(92), "")[:120]}'))
SECTIONS['Knowledge Engine'] = dict(construct='(the engine underneath Discovery and Directory)', owners='Operations', items=KE_ITEMS)

STATUS_ORDER = ['live', 'held', 'decided', 'proposed']
LABEL = {'live': 'Live', 'held': 'Built, held', 'decided': 'Decided, not built', 'proposed': 'Proposed'}


def summary(sec):
    its = sec['items']
    c = {k: sum(1 for i in its if i['status'] == k) for k in STATUS_ORDER}
    passing = sum(1 for i in its if i['status'] == 'live' and i['check'] == 'passing')
    lnp = sum(1 for i in its if i['status'] == 'live' and i['check'] == 'not passing')
    lnc = sum(1 for i in its if i['status'] == 'live' and i['check'] == 'not checked')
    inscope = [i for i in its if i['status'] != 'proposed']   # proposed items aren't in scope until Jason signs them in
    n = len(inscope)
    return dict(counts=c, live_passing=passing, live_not_passing=lnp, live_not_checked=lnc, held=c['held'], in_scope=n,
                complete_pct=round(passing / n * 100, 1) if n else 0, live_pct=round(c['live'] / n * 100, 1) if n else 0,
                gaps=[i['name'] for i in its if i['status'] == 'decided'])


QUESTIONS = [
    'Get Local: counted in Directory (the session manager\'s ruling). Its map and "near me" also serve Discovery: one count, Directory.',
    'The business portal: its listing side (claims, the listing editor) is in Directory; its Shop side (Market 360°, Storefront & pages, catalog, payments) is in Marketplace. Is that split right?',
    'Trips: in Discovery (ruling). Personal Guides are member-published: Discovery or Community?',
    'Traversence\'s own back-office (our orders, print products, vendors, work orders, QuickBooks): Platform, or Marketplace ("orders, payments")? Listed under Platform until Jason says.',
    'The Gallery (0126): Community (people\'s albums) or Platform (every file through it)? Listed under Community.',
    'Home (/): the way into all four; listed under Platform.',
    'Live events from the crawler (0133): listed in Discovery as feeding Things to do; Events the tool (0134) is Community. Is that the line?',
    'Facebook and Instagram (0168): members\' part listed in Community, the business apps in Marketplace (0169\'s first apps).',
    'Proposed items (no decision) are listed but kept out of the denominator until Jason signs them into scope.',
]
CANT = [
    'SES live mode (SES_MODE is .env data): asks.md visit 5.',
    'The Nationwide Narrative (brand.md §2): a framework with no decision of its own; its executing parts here are the guides, stories, heritage and journeys, each counted in Discovery. What else counts as its scope is for Jason.',
    'Whether "Answers (404 without its id)" counts as passing: the rendered check opens id pages without an id; a real record\'s page isn\'t checked.',
    '0153 zip 4\'s content: the decision says "zips 2–4 to come" and records zips 1–3 built; zip 4\'s scope needs 0153 §11 read with its owner.',
    '0170\'s built parts: the mirror has HubOpening.php; which of 0170\'s placements are live needs its owner.',
]
SETTLED = [
    ('Is 3d live?', 'Yes: release #237 "3d: Community groups to the mockup" went live 2026-11-20.'),
    ('Market 360° zips 5–6', 'Live: built with zip 4 as one Phase C build (decisions/0141:152; market360-build-briefs.md:99).'),
    ('Market 360° zip 7 (Phases D and E)', 'Decided, not built: no Built note, and none of its pieces (bundles, tax-exempt buyers, the deals digest) is in the mirror. Its storefront themes are live as Studio 5b (#42).'),
    ('Storefront Studio (0159)', 'Steps 1–4 live (#4–#34); 5a and 5b live (#41, #42); 5c commerce blocks, 5d the studio bar and step 6 AI writing are decided, not built.'),
    ('Data cleanup', 'Live: #49, #50, #52, #53, #54.'),
    ('The duplicates fix', 'Live: Release B part 1, #58 (the one identity check), with 0164 §3.1–3.2.'),
    ('Suggested journeys and on-demand guides, "live once uploaded"', 'Live: journeys/suggested.php and hub/build.php are in the mirror (Sync e229587).'),
    ('Square catalog sync (outline: decided, not built)', 'Live: decisions/0135:405, step 4b built.'),
    ('Search Phase C (outline: decided, not built)', 'Live: decisions/0061 replaced 0060\'s Phase C and is built.'),
    ('Messages through the dispatcher, comms-all pages ("Coming" in pages.md)', 'Live: release #135 and the files are in the mirror; pages.md\'s "Coming" is stale.'),
]


def write():
    os.makedirs(OUT, exist_ok=True)
    data = dict(generated_by='handoffs/control-board-plan/step3/mockups/src/sections.py', mirror=MIRROR_AT, releases='#1–#247',
                rule='Complete = live and passing its check. Built, held is not complete and is counted beside the %. Proposed items are listed, not in scope.',
                draft='draft: the scope isn\'t signed off', sections={})
    L = ['# The four build gauges: the baseline inventory (Discovery, Directory, Community, Marketplace, and Platform)', '',
         f'Generated by `mockups/src/sections.py` from the repo, the live mirror ({MIRROR_AT}), the release records (#1–#247) and the',
         'session manager\'s outline (`step3/sections-outline.md`, cited where it adds an item: **[outline]**). Specs only: nothing changed on the',
         'board or the site.', '',
         '**The rule (the session manager\'s ruling):** an item is **complete** when it\'s live and passing its check. Built but held is not',
         'complete, and is shown beside the % as a held count. A live item whose check fails is "live, not passing"; one no check reads is',
         '"live, not checked"; both count as not complete, and each names the check it relies on. **Proposed** items (no decision) are listed',
         'but kept out of the denominator until Jason signs them into scope. Every % is a **draft: the scope isn\'t signed off**.', '',
         '**Checks used:** the rendered check (`site-audit/results/rendered.json`: each page at 1280 and 390, read through the runtime board),',
         'the paths check (`paths.json`: a visitor\'s whole task), the search scorecard, and the runtime board\'s snapshots. "Answers (404',
         'without its id)" is counted as passing, with the caveat below.', '',
         '## Summary', '', '| Section | Live (passing · not passing · not checked) | Built, held | Decided, not built | Proposed | In scope | Complete % (draft) | Live % |',
         '|---|---|---|---|---|---|---|---|']
    for name, sec in SECTIONS.items():
        s = summary(sec)
        c = s['counts']
        L.append(f'| **{name}**{" (not gauged)" if name == "Platform" else ""} | {c["live"]} ({s["live_passing"]} · {s["live_not_passing"]} · {s["live_not_checked"]}) | {c["held"]} | {c["decided"]} | {c["proposed"]} | {s["in_scope"]} | **{s["complete_pct"]}%** | {s["live_pct"]}% |')
        data['sections'][name] = dict(construct=sec['construct'], owners=sec['owners'], summary=s, items=sec['items'])
    L += ['', 'Complete % = live and passing ÷ in scope (live, held and decided). Live % = live ÷ in scope, shown for comparison.', '']
    L += ['## The outline\'s "to confirm" items', '', '**Settled from the mirror and the release records:**', '']
    L += [f'- **{q}** {a}' for q, a in SETTLED]
    L += ['', '**Not settled (live-data or Jason\'s asks):**', ''] + [f'- {x}' for x in CANT]
    L += ['', '## Ownership questions (for Jason)', '', '**Rulings applied (the session manager, 2026-11-22):** Journeys, Trips and Guides in Discovery; Get Local,',
          'listings and claim in Directory; Events (0134) counted in Community, shown in Discovery\'s Things to do as a cross-link not counted',
          'twice; Market 360°, the Shop, Domains and Storefronts in Marketplace; the platform pieces as a possible fifth gauge.', '']
    L += [f'{i}. {q}' for i, q in enumerate(QUESTIONS, 1)]
    for name, sec in SECTIONS.items():
        s = summary(sec)
        L += ['', '---', '', (f'## {name} ("{sec["construct"]}")' if name not in ('Platform', 'Knowledge Engine') else f'## {name} (a proposed gauge: sections/crossover.md)'), '',
              f'**Owner sessions:** {sec["owners"]}.', '',
              f'**Summary ({data["draft"]}):** {s["counts"]["live"]} live ({s["live_passing"]} passing, {s["live_not_passing"]} not passing, {s["live_not_checked"]} not checked), '
              f'{s["held"]} built and held, {s["counts"]["decided"]} decided and not built, {s["counts"]["proposed"]} proposed. '
              f'**Complete: {s["complete_pct"]}%** of {s["in_scope"]} in scope; **held: {s["held"]}**; live: {s["live_pct"]}%.', '']
        if s['gaps']:
            L += ['**Top gaps (decided, not built):**', ''] + [f'- {g}' for g in s['gaps'][:8]] + ['']
        L += ['| Item | Kind | Status | Check | Source | Owner |', '|---|---|---|---|---|---|']
        for st in STATUS_ORDER:
            for it in [i for i in sec['items'] if i['status'] == st]:
                stat = LABEL[st] + (f', {it["check"]}' if st == 'live' else '')
                chk = it.get('check_by', '—')
                name_ = it['name'].replace('|', '/') + (' **[outline]**' if it['outline'] else '') + (f' <br>*{it["note"]}*' if it['note'] else '')
                L.append(f'| {name_} | {it["kind"]} | {stat} | {chk.replace("|", "/")} | {it["source"].replace("|", "/")} | {it["owner"] or "—"} |')
    open(OUT + 'inventory.md', 'w').write('\n'.join(L) + '\n')
    json.dump(data, open(OUT + 'inventory.json', 'w'), indent=1)
    for name in SECTIONS:
        print(name, json.dumps(data['sections'][name]['summary']['counts']), data['sections'][name]['summary']['complete_pct'], 'held', data['sections'][name]['summary']['held'], 'live%', data['sections'][name]['summary']['live_pct'])


exec(open(os.path.join(HERE, 'sections_crossover.py')).read())

if __name__ == '__main__':
    write()
    write_crossover()
