# The association map (step3/association-map.md): snapshot → records → workflow → task → need → sources in order.
# One table, read by board.py for the gauge cards' two drill paths and their Fix it links (Jason, 2026-11-22).
# Workflows: Automation::WORKFLOWS (live code). Tasks per workflow: research/crawler-how-it-runs-2026-11-14.md §2's table
# and SystemStart::TASKS (the bulk loads, the recheck). Needs and their sources in order: Preflight::NEEDS (live code).
# A need's sources are not written here: board.py reads them from Preflight::NEEDS, so the order is the code's.
# Static design data; not site code.

# task → the needs it calls (from what each task does; checked against OutsideServices' task lists where they name one)
TASK_NEEDS = {
    'coverage': ['places', 'bulk'], 'second_look': ['confirm', 'prose'], 'targets': ['confirm'], 'npi_bulk': ['health'], 'irs_bulk': ['nonprofits'],
    'overture_bulk': ['bulk', 'places'], 'foursquare_bulk': ['bulk'], 'geofabrik_bulk': ['places', 'bulk'], 'npi_town': ['health'],
    'refresh': ['reread', 'contact', 'stillopen'], 'identity': ['confirm'], 'geocode': ['geocode'], 'places': ['stillopen'], 'recheck': ['confirm'],
    'source': ['sources'], 'commoncrawl': ['sources', 'reread'], 'heritage': ['heritage'], 'landmarks_bulk': ['landmarks', 'official'], 'rec_bulk': ['recreation'],
    'howto': ['websearch'], 'learn': ['websearch'], 'guide job': ['writing'], 'on-demand guide': ['writing'], 'journeys job': ['drive'], 'find it': ['writing', 'websearch'],
}
# workflow → its tasks (worker tasks, or the site's own job where it has no worker task)
WF_TASKS = {
    'W1': ['coverage', 'second_look', 'targets', 'npi_bulk', 'irs_bulk', 'overture_bulk', 'foursquare_bulk', 'geofabrik_bulk'],
    'W2': ['refresh', 'identity', 'geocode', 'places', 'recheck'], 'W3': ['source', 'commoncrawl'], 'W4': ['guide job'], 'W5': ['on-demand guide'],
    'W6': ['heritage'], 'W7': ['journeys job'], 'W8': ['find it'], 'W9': ['landmarks_bulk', 'rec_bulk'], 'W10': ['guide job'], 'W11': ['howto'], 'W12': ['learn'],
}

# snapshot → what it counts, which workflows and tasks make it (or "none"), and the site's own needs it rests on.
# 'unmapped': a link the code's map can't compute, named, listed in coverage.
SNAP = {
    'o-pages': dict(records='page answers (the rendered check: every page as each visitor, 1280 and 390)', wf=None, needs=['hosting'], unmapped=[]),
    'o-speed': dict(records='page load times (no probe yet)', wf=None, needs=['hosting', 'storage'], unmapped=[]),
    'o-signin': dict(records='sign-ins and sign-outs (the paths check)', wf=None, needs=['signin', 'siteemail'],
                     unmapped=['Cloudflare Turnstile (the sign-in check) has no need in Preflight::NEEDS']),
    'o-phone': dict(records='page views at 390 (the rendered check, the layout probe)', wf=None, needs=['hosting', 'styles'], unmapped=[]),
    'o-search': dict(records='search answers (the scorecard\'s 106 cases)', wf={'W1': ['coverage'], 'W9': ['landmarks_bulk'], 'W12': ['learn']}, needs=['semantic'],
                     unmapped=['the typing suggestions\' speed: search\'s own index, no need names it']),
    'o-near': dict(records='listings within 5 mi of a town (no probe yet)', wf={'W1': ['coverage', 'overture_bulk', 'geofabrik_bulk']}, needs=[], unmapped=[]),
    'o-paths': dict(records='visitors\' whole tasks (the paths check)', wf=None, needs=['hosting'], unmapped=[]),
    'o-fresh': dict(records='each copy\'s last load', wf={'W1': ['npi_bulk', 'irs_bulk', 'overture_bulk', 'foursquare_bulk', 'geofabrik_bulk'], 'W9': ['rec_bulk']}, needs=[], unmapped=[]),
    'o-complete': dict(records='listings with an exact map point, a kind, a phone and a website', wf={'W2': ['geocode', 'refresh', 'identity', 'places']}, needs=[], unmapped=[]),
    'o-leak': dict(records='held words and places (no probe yet)', wf={'W9': ['landmarks_bulk']}, needs=[], unmapped=[]),
    'o-photos': dict(records='photos served (no results file yet)', wf=None, needs=['storage', 'stock'], unmapped=[]),
    'o-gather': dict(records='records in and stored (the alarms snapshot: 0 in 24 hours)', wf={'W1': WF_TASKS['W1'], 'W3': ['source', 'commoncrawl']}, needs=['enginehost', 'hosting'], unmapped=[]),
    'o-grow': dict(records='published listings (the crawler snapshot)', wf={'W1': ['coverage', 'second_look', 'targets'], 'W2': ['identity']}, needs=[], unmapped=[]),
    'o-pay': dict(records='orders paid or invoiced', wf=None, needs=['payments', 'checkout'], unmapped=[]),
    'o-renew': dict(records='plan renewals (billing_tick)', wf=None, needs=['payments', 'hosting'], unmapped=[]),
    'o-email': dict(records='emails delivered', wf=None, needs=['siteemail', 'bulkemail'], unmapped=[]),
    'o-claude': dict(records='Claude\'s spend this month', wf={'W4': ['guide job'], 'W5': ['on-demand guide'], 'W6': ['heritage'], 'W8': ['find it'], 'W10': ['guide job']}, needs=[], unmapped=[]),
    'o-places': dict(records='Google Places checks this month', wf={'W2': ['places']}, needs=[], unmapped=[]),
    'o-search-cap': dict(records='web searches this month', wf={'W8': ['find it'], 'W11': ['howto'], 'W12': ['learn']}, needs=[], unmapped=[]),
    'o-told': dict(records='trips and the messages that reached a person', wf=None, needs=['siteemail'],
                   unmapped=['the Communications Dispatcher (push, Pulse) has no need in Preflight::NEEDS']),
    'o-uptime': dict(records='the whole system\'s hours up (the historian)', wf=None, needs=['hosting', 'enginehost', 'sync'], unmapped=[]),
    'o-ir': dict(records='open Incident Reports', wf={'W1': ['overture_bulk', 'foursquare_bulk']}, needs=['enginehost', 'hosting'], unmapped=[]),
    'o-selfrec': dict(records='trips closed by their own check (the historian)', wf=None, needs=[], unmapped=['the self-recovery rate: no part owns it yet']),
}

# real shares, by level, where the readings hold them (obs); anything else drawn is marked scenario
SHARES = {
    ('o-gather', 'W1'): ('obs', '2 of 2 open W1 trips (overture_bulk #1290, foursquare_bulk #1288): W1 paused whole (alarms, 3:19 AM MST)'),
    ('o-gather', 'W3'): ('obs', 'W3 does nothing while its two switches (source, commoncrawl) stay Off by the system, left by a closed trip (01-lift-reconstruction.md (b), (c))'),
    ('o-gather', 'overture_bulk'): ('obs', 'run #1290: TimeoutError reading /api/crawl/loads.php, 08:31:41 UTC'),
    ('o-gather', 'foursquare_bulk'): ('obs', 'run #1288: TimeoutError reading /api/crawl/loads.php, 08:20:34 UTC'),
    ('o-ir', 'W1'): ('obs', 'IR-0001: 2 members, root cause Unconfirmed'),
    ('o-search', 'W1'): ('obs', '1 of the 5 short cases: "places to shoot", Saint Johns, AZ: 0 results'),
    ('o-search', 'W9'): ('obs', '1 of the 5: "Besh-Ba-Gowah": 0 results (an archaeological name, 0164 R7)'),
    ('o-search', 'W12'): ('obs', 'queues the gaps for the crawler; its own count: not read'),
    ('o-complete', 'W2'): ('obs', '1,708 of 13,184 public map points approximate; 2,487 staged finds with no kind (crawler snapshot, 3:19 AM MST)'),
    ('o-complete', 'geocode'): ('sim', 'scenario: the approximate points are geocode\'s to place'),
    ('o-complete', 'refresh'): ('sim', 'scenario: refresh fills a missing phone or website, through "A missing phone or website"'),
    ('o-complete', 'contact'): ('sim', 'scenario: Tavily filled 0 of 412 missing phones this week · Brave covered 37'),
    ('o-places', 'W2'): ('obs', '544 of 1,000 checks this month, all W2\'s places task'),
    ('o-claude', 'W4'): ('sim', 'scenario: its share of $27.05 isn\'t split by workflow here (the ledger is)'),
}
# the worked examples, drawn end to end (Jason, 2026-11-22)
EXAMPLES = ['o-speed', 'o-complete', 'o-gather']

# Core Services (Jason, 2026-11-22: "Core Services: the platform specific dependencies. Registered Services: the outside specific
# dependencies."). Ours: the databases, jobs, staging, the sync, the workers on Railway and the dispatcher. Registered Services are the
# outside companies in the Service register (0173): the needs' sources above. Each core part: its label, the card it's drawn on, its source.
CORE_PARTS = {
    'db': ('The site and its databases', 'bluehost', 'Database::t, decisions/0110 (on Bluehost)'),
    'jobs': ('The scheduled jobs', 'bluehost', 'Scheduler::JOBS through the cPanel API, decisions/0113'),
    'staging': ('Staging and releases', 'github', 'decisions/0165, 0159 §5'),
    'sync': ('The 3-way sync', 'github', 'degraffics/traverse-3way-sync'),
    'workers': ('The workers on Railway: the crawler and W0 Indexer', 'railway', 'decisions/0160, 0164 §1.4'),
    'video': ('The video worker', 'video', 'decisions/0153 §8'),
    'dispatcher': ('The dispatcher', 'dispatcher', 'decisions/0172, 0176'),
}
_GATHER = ['db', 'jobs', 'workers']
CORE = {
    'o-pages': ['db', 'staging', 'sync'], 'o-speed': ['db'], 'o-signin': ['db', 'dispatcher'], 'o-phone': ['db', 'staging'], 'o-search': ['db', 'jobs'],
    'o-near': ['db', 'workers'], 'o-paths': ['db', 'staging'], 'o-fresh': _GATHER, 'o-complete': _GATHER, 'o-leak': ['db', 'workers'],
    'o-photos': ['db', 'video'], 'o-gather': _GATHER, 'o-grow': _GATHER, 'o-pay': ['db', 'jobs', 'dispatcher'], 'o-renew': ['db', 'jobs', 'dispatcher'],
    'o-email': ['dispatcher', 'jobs'], 'o-claude': ['db', 'jobs'], 'o-places': ['db', 'workers'], 'o-search-cap': ['db', 'workers'],
    'o-told': ['dispatcher', 'jobs'], 'o-uptime': ['db', 'jobs', 'workers', 'dispatcher'], 'o-ir': ['db', 'workers', 'dispatcher'], 'o-selfrec': ['db', 'jobs', 'workers'],
}
