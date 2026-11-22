# The board's Workflows page (a draft for the session manager, then Jason; 2026-11-22). One generator for three things:
#   step3/workflows-page.md      the spec (requirements cited, the state model, the gauges, W0-W14, kept/moved/dropped, the case)
#   step3/board/workflows.html   the standalone mockup (1280 and 390)
#   the board's Workflows view   (board.py calls view() and panels())
# Real data only: the live mirror (Sync e229587), the alarms snapshot (3:19 AM MST, Nov 21), 03 §28.1, the crawler worker.
# What isn't real today is marked "illustration" or "proposed". Score gauges in Arc (no needle; Jason's choice, 2026-11-22).
# Design mockup, not site code.
import html, json, os, re

import assoc
import scorering

E = html.escape
HERE = os.path.dirname(os.path.abspath(__file__))
STEP3 = os.path.normpath(os.path.join(HERE, '..', '..')) + '/'
PLAN = os.path.normpath(STEP3 + '..') + '/'
B = None   # the board module, set by init() (board.py runs as __main__, so it hands itself in)


def init(board):
    global B
    B = board


# ---------------------------------------------------------------- sources
MIRROR = 'mirror Sync e229587'
RT = 'research/crawler-how-it-runs-2026-11-14.md'
TASK_INFO = {   # task: (what it does, where it says so)
    'coverage': ('Businesses of one kind in one town cluster ("Gallup × Health care")', RT + ':224'),
    'second_look': ('Checks listings with only one source', RT + ':225'),
    'targets': ('Searches people or staff queued ("cafés in Kayenta"); finds held for a person', RT + ':226'),
    'npi_bulk': ('The monthly NPI file (health-care organizations in our states), at night', RT + ':236'),
    'irs_bulk': ('The monthly IRS nonprofit list (confirms listings only), at night', RT + ':237'),
    'overture_bulk': ('Overture\'s town loads, posted to /api/crawl/loads.php', 'SystemStart.php:47; crawler.py:3552–3591, :3782'),
    'foursquare_bulk': ('Foursquare\'s open places, posted to /api/crawl/loads.php', 'SystemStart.php:48; crawler.py:3552–3591, :3783'),
    'geofabrik_bulk': ('OpenStreetMap from Geofabrik (our copy), posted to /api/crawl/loads.php', 'SystemStart.php:49; crawler.py:3704–3757, :3784'),
    'refresh': ('Rechecks live listings\' websites for changes', RT + ':227'),
    'identity': ('Confirms who a listing is', RT + ':228'),
    'geocode': ('Map points from street addresses (never confidential ones)', RT + ':229'),
    'places': ('Checks listings against Google (same place, closed or not; keeps only Google\'s ID)', RT + ':230'),
    'recheck': ('The identity recheck (site job identity_recheck_tick), shipped off', 'SystemStart.php:46; R155'),
    'source': ('Reads one "read regularly" source, only pages that changed; its fallback copy is cc_home()', RT + ':231; crawler.py:1880'),
    'commoncrawl': ('Our states\' local public sites, from Common Crawl\'s columnar index on data.commoncrawl.org', RT + ':232; crawler.py:1697–1703'),
    'vectors': ('The vector index (site job vectors_tick: Supabase, Gemini)', 'SystemStart.php:50'),
    'guide job': ('The site\'s guide job: busiest unwritten places first; Write a draft', RT + ':174'),
    'on-demand guide': ('A first-look guide for a place a visitor searched with none', RT + ':175'),
    'heritage': ('Catalog records (never the items) for each guide\'s place', RT + ':233'),
    'journeys job': ('Suggested journeys on a live guide, 3 a run (0146)', RT + ':177'),
    'find it': ('A name in a guide or story we don\'t hold, every 15 minutes', RT + ':178'),
    'landmarks_bulk': ('Natural landmarks and nations\' boundaries, at night', RT + ':239'),
    'rec_bulk': ('Monthly Recreation.gov places, at night', RT + ':238'),
    'live events': ('Upcoming events on a live guide, while the Live events template runs (the site\'s guide job)', RT + ':180'),
    'howto': ('Official pages for how-to guides', RT + ':234'),
    'learn': ('Searches gaps open data couldn\'t fill; queues W1', RT + ':235'),
    'indexer': ('Every URL logged; the machine classifies it (always on, its own Railway service)', 'decisions/0164 §1.4; Frontier.php:24'),
    'licence_lookup': ('The states\' licence lists (AZCC, NM SoS): not built yet', '03 §26.9–26.10, §28.3'),
}
TASKS = {k: list(v) for k, v in assoc.WF_TASKS.items()}
TASKS['W0'] = ['indexer']
TASKS['W3'] = TASKS['W3'] + ['vectors']            # SystemStart.php:50 puts the vector index under W3
TASKS['W10'] = ['live events']
TASKS['W13'] = []
TASKS['W14'] = ['licence_lookup']
TASK_NEEDS = dict(assoc.TASK_NEEDS)
TASK_NEEDS.update({'indexer': ['pages'], 'vectors': ['semantic'], 'commoncrawl': [], 'source': ['sources', 'prose'], 'licence_lookup': ['authority'],
                   'live events': ['websearch', 'prose', 'writing'], 'guide job': ['writing'], 'on-demand guide': ['writing'], 'journeys job': ['drive'],
                   'find it': ['writing', 'websearch'], 'heritage': ['heritage', 'writing'], 'howto': ['websearch', 'prose'], 'learn': ['websearch']})

# what starts each workflow: the run clock (research table "Started by", lines 171-183; W0 and W14 from Automation::WORKFLOWS)
STARTS = {
    'W0': ('always on: its own Railway service', 'Automation::WORKFLOWS; decisions/0164 §1.4'),
    'W1': ('coverage gaps, towns searched (0149), situation gaps, search targets, Send the crawler, places named on pages, the monthly NPI and IRS files; the bulk loads in the night window (1–5 AM Arizona)', RT + ':171'),
    'W2': ('time: refresh by score, Google every 90 days, map points while missing; a source changing', RT + ':172'),
    'W3': ('sites linked from pages we read, sites a second look accepted, Common Crawl\'s local public sites', RT + ':173'),
    'W4': ('the guide job (busiest unwritten places first), Write a draft', RT + ':174'),
    'W5': ('a visitor searching a place with no guide (0147)', RT + ':175'),
    'W6': ('a place with a guide, hourly', RT + ':176'),
    'W7': ('a live guide, hourly, 3 a run (0146)', RT + ':177'),
    'W8': ('a name in a guide or story that we don\'t hold, every 15 minutes', RT + ':178'),
    'W9': ('the monthly landmark file; a place someone opens or searches that we don\'t hold (0148)', RT + ':179'),
    'W10': ('a live guide, while the Live events template runs', RT + ':180'),
    'W11': ('a need with official guidance (0063 §8), 2 a run', RT + ':181'),
    'W12': ('what people search and open after', RT + ':182'),
    'W13': ('a person writing (0043): nothing to switch', RT + ':183; SystemStart.php:43'),
    'W14': ('each state\'s licence and registry sources, once licence_lookup is built', 'Automation::WORKFLOWS; 03 §26.9'),
}
# workflow → workflow: who sends work to whom (the research table's "Started by" and "Makes" columns)
SENDS = [('W5', 'W1', 'queues the town for W1', RT + ':175'), ('W12', 'W1', 'queues W1 with gaps', RT + ':182'), ('W13', 'W8', 'named places go to Find it', RT + ':183'),
         ('W4', 'W6', 'a place with a guide starts W6', RT + ':176'), ('W4', 'W7', 'a live guide starts W7', RT + ':177'), ('W4', 'W10', 'a live guide starts W10', RT + ':180'),
         ('W4', 'W8', 'a name in a guide starts Find it', RT + ':178'), ('W8', 'W1', 'its listings pass W1\'s gate (the listing score)', RT + ':178'),
         ('W1', 'W3', 'sites linked from pages W1 reads start W3', RT + ':173')]
PERSON = {'W1': ['Review: the grey zone and merge offers', 'Reactivate a site that blocks us (never retried by itself)'],
          'W4': ['A guide below its publish score waits as a draft for a person'],
          'W9': ['A place held for its name opens fully only on a person\'s link to its managing authority'],
          'W13': ['People write stories']}   # WorkflowSettings::PERSON (mirror)
TASK_FIELDS = ['Order', 'Every (minutes)', 'Seconds a run at most', 'Pages a run at most', 'Paid calls a run at most', 'Items a run at most']   # WorkflowSettings::TASK_FIELDS

# ---------------------------------------------------------------- each workflow's real state (the alarms snapshot, 03 §28.1, sections.py KE_STATE)
STATE = {
    'W0': ('n', 'Off: shipped off until the team starts it', 'Built and shipped switched off (release #79; app/ui/faq.md:870). Whether it has been started is live data, not in the snapshot.'),
    'W1': ('r', 'Paused by the system (a trip): 2 tasks', 'overture_bulk failed (run #1290, 08:31 UTC Nov 21) and foursquare_bulk failed (run #1288, 08:20 UTC): two trips that paused W1 whole. "Records: 0 in, 0 stored, 0 refused" in 24 hours. Root cause so far: the response path at /api/crawl/loads.php, unconfirmed (IR-0001).'),
    'W2': ('a', 'Warning: its refresh count', '/api/crawl/refresh counted more than it received (received 1, counted 2; 09:05 UTC Nov 21), so its counts can\'t be trusted yet (03 §28.2).'),
    'W3': ('a', 'Off by the system: 2 switches', 'Its trip (Common Crawl #35, 09:00 UTC Nov 20) closed when Jason pressed Check and start again (Nov 21). Its two task switches, Read a source and Common Crawl, stay Off by the system, left by the closed trip, so W3 does nothing. Also: 77 sources are read regularly outside our region (a warning since Nov 18).'),
    'W13': ('n', 'Nothing to switch: people write', 'W13 is people\'s own (SystemStart.php:43): no crawler, no switch.'),
    'W14': ('n', 'Not built: licence_lookup', 'Its licence_lookup (AZCC, NM SoS) isn\'t built yet (03 §26.9–26.10, §28.3).'),
}
NO_ALARM = ('n', 'No open alarm', 'No open alarm in the snapshot (3:19 AM MST, Nov 21). Its switches and last run are live data the snapshot doesn\'t hold.')
TASK_STATE = {   # task switches the snapshot or §28.1 names; everything else "not read here"
    'overture_bulk': ('r', 'Trip: run #1290 failed'), 'foursquare_bulk': ('r', 'Trip: run #1288 failed'),
    'source': ('a', 'Off by the system (left by a closed trip)'), 'commoncrawl': ('a', 'Off by the system (left by a closed trip)'),
    'refresh': ('a', 'Warning: counted more than it received'), 'recheck': ('n', 'Off: shipped off (R155)'),
    'indexer': ('n', 'Off: shipped off'), 'licence_lookup': ('n', 'Not built'),
}

# ---------------------------------------------------------------- each workflow as a gauge (the approved runtime lifecycle, gauge-lifecycle.md §7)
# setup: [the probe built, its target set, its check passing, its alarm wired to the board]; the measure and target are proposals
CRAWL_PROBE = 'the Workflow board\'s Last 24 h per task (CrawlService::taskStats, operations-console.php:377) and the ledger\'s records'
JOB_PROBE = 'the watch\'s late and stuck check on its site job (R44; tools/control-board-tests/quiet-job.php)'
MEASURE = {   # (the proposed measure, the proposed target, its probe or None)
    'W0': ('Pages read and classified: its heartbeat inside every 5-minute watch', 'every watch', 'the worker\'s heartbeat line rw:beat (Preflight::NEEDS "pages")'),
    'W1': ('Records reconciled per run: in = stored + updated + sent to Review + refused (the "accounted" check, R25)', 'every run', CRAWL_PROBE),
    'W2': ('Listings due a recheck, rechecked on schedule', '95% within their due day', CRAWL_PROBE),
    'W3': ('Sources due a read, read on schedule (changed pages only)', '95% within their due day', CRAWL_PROBE),
    'W4': ('The guide job\'s runs on schedule, inside the AI cap', 'every scheduled run', JOB_PROBE),
    'W5': ('Visitors\' asks answered with a first look', 'every ask, inside half the daily AI allowance (0147)', JOB_PROBE),
    'W6': ('Heritage tasks done on schedule', 'every scheduled run', CRAWL_PROBE),
    'W7': ('Journeys runs on schedule (3 a run, 0146)', 'every scheduled run', JOB_PROBE),
    'W8': ('Names queued, found or sent to a person', 'within 7 days', JOB_PROBE),
    'W9': ('Monthly loads done in their night window', 'every month', CRAWL_PROBE),
    'W10': ('Live guides\' events refreshed on schedule', 'every scheduled run', JOB_PROBE),
    'W11': ('How-to tasks done on schedule', 'every scheduled run', CRAWL_PROBE),
    'W12': ('Learn tasks done on schedule', 'every scheduled run', CRAWL_PROBE),
    'W13': ('Named places in stories sent to Find it (W8)', 'every story', None),
    'W14': ('Authority sources read on schedule', 'every scheduled run', None),
}
STAGES = ['Planning', 'Phased Development', 'Audit & Review', 'Implementation', 'Monitoring']
SK = {'Planning': 'pl', 'Phased Development': 'pd', 'Audit & Review': 'ar', 'Implementation': 'im', 'Monitoring': 'mo'}


# "Alarm wired" (the session manager, 2026-11-22): the workflow's failures raise through the one alarm engine, with their Fix it or
# Check it now landing on one item. A pf:wf gate is part of "check passing", not this. From the mirror: a crawler task's failed runs
# raise on its workflow (ControlBoard::latest, ControlBoard.php:446–470; ControlAlarms.php:44–52); a site job tied to a workflow raises
# on it and pauses it (Automation::JOBS, Automation.php:50–61; ControlAlarms.php:279–285, 953–961); W0's worker raises on wf:W0
# (ControlAlarms.php:278, 743–744); every alarm raised lands on one item (tools/control-board-tests/routes.php).
WIRED = {
    'W0': 'its worker raises on wf:W0, its held sites on W0\'s page (ControlAlarms.php:278, 743–744)',
    'W1': 'its crawler tasks\' failed runs (ControlBoard.php:446–470) and listings_tick, data_cleanup_tick (Automation::JOBS)',
    'W2': 'its crawler tasks\' failed runs and identity_recheck_tick (Automation::JOBS)',
    'W3': 'its crawler tasks\' failed runs and page_read_tick, vectors_tick (Automation::JOBS)',
    'W4': 'guide_finds_tick raises on it and pauses it (Automation::JOBS; ControlAlarms.php:279–285)',
    'W5': 'guide_ondemand_tick (Automation::JOBS)', 'W6': 'heritage tasks\' failed runs and heritage_tick (Automation::JOBS)',
    'W7': 'journeys_tick (Automation::JOBS)', 'W8': 'guide_finds_tick (Automation::JOBS)',
    'W9': 'its bulk tasks\' failed runs and landmark_photos_tick (Automation::JOBS)', 'W10': 'guide_finds_tick (Automation::JOBS)',
    'W11': 'howto\'s failed runs (ControlBoard.php:446–470)', 'W12': 'learn\'s failed runs (ControlBoard.php:446–470)',
}
LIST_ONLY = {'W13': 'W13 is a list, not a gauge: people write stories, so it has no machine steps to measure (the session manager, 2026-11-22).'}


def setup(w):
    m, t, probe = MEASURE[w]
    alarm = w in WIRED
    return [('The probe built', probe is not None, probe or ('licence_lookup isn\'t built' if w == 'W14' else 'person-written: nothing runs to read')),
            ('Its target set', False, 'proposed: ' + t + ' (ours, for Jason)'),
            ('Its check passing', False, 'no target yet; its gate (pf:wf:' + w + ') is part of this check' + ('; ' + STATE[w][1].lower() if w in ('W1', 'W2', 'W3') else '')),
            ('Its alarm wired to the board', alarm, ('its failures raise through the one alarm engine: ' + WIRED[w]) if alarm else ('licence_lookup isn\'t built: nothing raises yet' if w == 'W14' else 'nothing raises'))]


def stage(w):
   m = [x[1] for x in setup(w)]
   if all(m):
       return 'Monitoring'
   if not any(m):
       return 'Planning'
   if not m[0]:
       return 'Phased Development'
   if not (m[1] and m[2]):
       return 'Audit & Review'
   return 'Implementation'


def pct(w):
    su = setup(w)
    return sum(1 for x in su if x[1]) / len(su) * 100


def state(w):
    return STATE.get(w, NO_ALARM)


def needs_of(w):
    return [n for n in B.NEEDS if w in n['wfs']]


def wname(w):
    return next(n for x, n, _, _ in B.WORKFLOWS if x == w).replace('\\', '')


def wwhat(w):
    return next(t for x, _, t, _ in B.WORKFLOWS if x == w).replace('\\', '')


def wwhere(w):
    return next(t for x, _, _, t in B.WORKFLOWS if x == w)


# ---------------------------------------------------------------- the three paths, starting from a workflow
def feeds(w):
    """The board's gauges this workflow's tasks make (assoc.SNAP, inverted)."""
    out = []
    for o, s in assoc.SNAP.items():
        if s['wf'] and w in s['wf']:
            name = next((x['name'] for g in B.PROMISES for x in g[2] if x['id'] == o), o)
            out.append((o, name, s['wf'][w]))
    return out


def core(w):
    where = wwhere(w)
    c = ['db']
    if 'crawler' in where or 'indexer' in where:
        c += ['workers']
    if 'site' in where or 'crawler' in where:
        c += ['jobs']
    if w not in ('W13',):
        c += ['dispatcher']
    return c


def src_html(n, links):
    parts = []
    for k, v, lab in n['srcs']:
        mm = B.mod_for(k, v)
        if mm and links:
            parts.append(B.modlink(mm))
        elif mm:
            parts.append(f'<b>{E(next((x["label"] for x in B.MODULES if x["id"] == mm), lab))}</b>')
        else:
            parts.append(f'<span class="own">{E(lab if k != "svc" else v)}</span>')
    return ' → '.join(parts)


# ---------------------------------------------------------------- what a source down holds (R88, model 1.2), per workflow
def holds(w):
    """For each need: its sources in order and what happens when the first one fails (real rules; drawn per need)."""
    rows = []
    for n in needs_of(w):
        users = [t for t in TASKS.get(w, []) if n['key'] in TASK_NEEDS.get(t, [])]
        names = [s[2] for s in n['srcs']]
        if len(names) > 1:
            then = f'its next source carries on ("On a backup": {names[1]}); nothing is held'
        else:
            then = 'no backup: ' + (', '.join(users) if users else 'the tasks that use it') + ' pause; the rest of ' + w + ' runs'
        rows.append((n, users, then))
    return rows


# ---------------------------------------------------------------- the case: Common Crawl's index down (03 §28.1)
CASE = [
    ('Fault', 'r', 'real', 'index.commoncrawl.org answers 504: Issue #35, "Common Crawl: its server failed at index.commoncrawl.org" (Held, 09:00 UTC Nov 20, the alarms snapshot).'),
    ('What it holds', 'a', 'real code; the drawn state is an illustration',
     'Only W3\'s per-site page copy waits: cc_home() (crawler.py:1672–1680), the fallback in read_source() (:1880), and W2\'s Re-read websites fallback (:2415). '
     'W3\'s bulk reads go to data.commoncrawl.org and carry on: common_crawl() holds only on that host, "never the index server" (crawler.py:1697–1703; worker 2.16.1). '
     'Since release #247 (12:22 UTC Nov 21) the gate checks data.commoncrawl.org first, then the index as a second part (Preflight.php:1110–1113): a failing second part is amber, '
     '"Partly working" (reachAll, :895–907). Before #247 the 504 turned the line red and held W3 whole (pf:wf:W3): the snapshot predates #247.'),
    ('The system tries', 'a', 'illustration', 'On the diagnostic clock: the component\'s check, 3 tries or 60 minutes (control_fix_tries, control_fix_minutes; R67–R68). '
     'A scheduled W3 run is not a try. Status: "Trying again (1 of 3)", then "Checked 09:42 MST: still 504. Next check 10:12 MST." (R104).'),
    ('Its card, from the alarm', 'a', 'illustration', 'The alarm\'s first button opens Common Crawl\'s card (R116). A 504 is their trouble, so the card shows no Fix it (R118, the session manager\'s ruling, 2026-11-22): '
     'only Check it now and Pause it, with "the system is waiting on them". Fix it shows only for a cause a person can fix (a key, a quota, a block; R117).'),
    ('Recovered', 'g', 'illustration', 'The check passes: the pause lifts and the thread closes as "Recovered: Common Crawl\'s index was down <n> min, the system fixed it.", a notice with nothing to press '
     '(R73, R91, R92: a server error is a back-off kind, closed by its check).'),
    ('Switches back', 'a', 'real today: still off', 'A trip places its pause beside the switches, never in them, so nothing is left to restore (0175 release 1, paused.php). '
     'W3\'s two switches today were written by the older trip: Off by the system, left by a closed trip. With no person\'s setting to go back to they stay off, '
     'listed under Needs attention with their switch (R98). A person presses Switch on (who and when logged, R143): drawn here, not pressed.'),
]

# ---------------------------------------------------------------- today's Workflows page: kept, moved, dropped (0175 R2b part C, release #243)
LIVE = [
    ('kept', 'The stage workflows\' builder (orders\' stages and moves)', 'sections.php:98 tv_admin_mod_workflows', 'It is the admin\'s Workflow engine (decisions/0109), not the crawler\'s: it stays at System Configuration → Workflows, above the crawler\'s section, unchanged.'),
    ('kept', 'Each workflow\'s own page, W0–W14, opened in a drawer', 'operations-console.php:475 (/admin/config/control/wf-W#, data-peek-frame)', 'The card opens it; it is where a task\'s switch lives (R144).'),
    ('kept', '"By workflow, this month": spend by workflow and service', 'operations-console.php:477–479, 482 (Operations::costs)', 'R18: By workflow shows on Workflows. On each card as its month\'s spend, and the fold below.'),
    ('kept', 'Each workflow\'s switch line: Running, Paused (why), or held by the master switch', 'operations-console.php:397, 402', 'Words follow 0175 (R86, R105): "Paused by the system (a trip): …" for a trip, never "paused by a person".'),
    ('kept', 'Last run: the newest task\'s time, outcome and why', 'operations-console.php:398–399, 403', 'On its own page, labelled with its zone (CLAUDE.md: times where they happened).'),
    ('kept', '"Returns empty": tasks with work and answers but nothing back', 'operations-console.php:404–405 (Operations::empties)', 'R62; on the card\'s state line and its own page.'),
    ('kept', 'The settings form: what starts it, its triggers, the task schedule, its share of a run, its budgets (share of the AI cap, monthly budget), its gates; one save as a version, and its versions', 'operations-console.php:396, 407–446 (WorkflowSettings; its save: wf_save, :107; versions :445–446)', 'R154: on its own page, unchanged; each save passes the switch-on check.'),
    ('kept', 'The task table: each task\'s limits, the services it may use, its last 24 hours', 'operations-console.php:409–416 (TASK_FIELDS, CrawlService::taskStats :377)', 'On its own page as "Its steps and their switches" with each step\'s sources in order (R144, tv_cb_steps).'),
    ('moved', 'Start the system (the same card as on Automation)', 'operations-console.php:383–385 (SystemStart::panel)', 'To the board\'s Start-up stage only (R17, R146); the page links there. One home for one button.'),
    ('moved', '"Check before switching on": the switch-on check\'s lines', 'operations-console.php:388–391, 394 (Operations::switchOnCheck)', 'Each red line moves onto the row of what it holds (R88, R99); the whole check stays in Start-up\'s pre-flight (R58–R60).'),
    ('dropped', 'The interlocks note pointing to the System Control Board', 'operations-console.php:393', 'The page is on the board now; the interlock shows on each card as what holds it.'),
    ('dropped', 'The list of W1–W14 page buttons above the board', 'operations-console.php:475, 481', 'Replaced by the gauge cards, each opening its own page; a second list of the same links would say it twice.'),
    ('dropped', 'The intro line "Each workflow, W1 to W13 (W14 from when it\'s built)…"', 'operations-console.php:381', 'W0–W14 are all listed now, each with its state; the line\'s promise (every setting edited here, each save a version) moves to the settings fold.'),
]

# ---------------------------------------------------------------- requirements this page answers (step1-requirements.md)
REQ_HOW = {
    8: 'The Knowledge Engine system: W0–W14 and their tasks, each a gauge card.', 11: 'The counts at the top filter the cards (Needs attention, Warnings, Off on purpose, No open alarm); pressed again, every card shows.',
    17: 'Workflows is the board\'s tab; Start-up keeps Start the system.', 18: 'By workflow, this month: on each card and in its fold.',
    21: 'Paused now for the workflows sits at the top, each item opening its fix.', 22: 'Its three groups: Needs attention, Waiting on those, Off on purpose.',
    27: 'Each workflow\'s tasks, needs, services and what it feeds come from the unit register (Automation::WORKFLOWS, Preflight::NEEDS, SystemStart::TASKS).',
    42: 'Each workflow\'s own page links Its data.', 44: 'The watch (silence) is the site-job workflows\' probe.', 47: 'Start re-tests the nd: lines a workflow depends on (named on its page).',
    53: 'W0\'s "N sites blocked us" sits on W0\'s own page.', 58: 'The pre-flight is in Start-up; a workflow\'s red line shows on its card.', 59: 'Its tasks are listed in Start\'s order.',
    60: 'A switch-on runs the switch-on check; its red line names its fix.', 61: 'Each need\'s sources in priority order, ours first.', 62: 'Returns empty on the card.',
    66: 'A workflow\'s alarms: Trip, Warning, Notice, on its page.', 67: 'The system tries first: the case\'s step 3.', 68: 'Keep the hour, on the diagnostic clock: the clocks table.',
    71: 'A red pre-flight line holds only the tasks that can\'t reroute.', 73: 'Recovered: the case\'s step 5.', 74: 'Escalation Notice when tries run out (its status words).',
    75: 'An escalated trip recovering closes itself.', 77: 'Trip and fault: "Paused by the system (a trip): …" on W1.', 86: 'A fault pauses, never switches off.',
    87: 'A trip pauses only what its cause touches: W1\'s gap is named.', 88: 'A source down holds only what uses it: every page\'s "What a source down holds".',
    89: 'Component and workflow both checked: the case.', 90: 'On a backup is working, never red.', 91: 'A pause clears itself when its check passes.', 92: 'A 5xx closes by its check.',
    96: 'The Census geocoder\'s backup on W2\'s Map points.', 97: 'Overture stays a backup for places by kind (W1).', 98: 'W3\'s switches left off by a closed trip, under Needs attention.',
    99: 'The interlock: a workflow starts only when its needs have a working source.', 101: 'Words follow the state now.', 102: 'An alarm\'s thread shape on the page.',
    105: 'Paused now\'s words are true.', 107: 'Lights: green, amber, Held, grey, red.', 108: 'A workflow is red only on a trip; held parts make it amber.',
    109: 'Off on purpose is never a failure.', 110: 'Every state carries its reason.', 116: 'An alarm\'s first button opens its component.', 117: 'Fix it routes by cause.',
    118: 'No Fix it for their trouble (5xx): the case.', 119: 'The spot\'s switch is the home switch.', 140: 'Four layers under the master switch: the page\'s layer line.',
    141: 'The master switch stops gathering only.', 142: 'Pausing a workflow stops its tasks, jobs and AI features.', 143: 'Each switch shows reason, who, when.',
    144: 'A task\'s switch lives on its workflow\'s own page.', 145: 'Check it now, Check and start again stay.', 146: 'Start the system stays in Start-up.',
    147: 'Shut down one workflow, reversed order.', 149: 'Shutting down a workflow records who (Deactivate).', 150: 'Maintenance: Take out, Back by, Put back.',
    153: 'Half budget for 14 days, said on the card.', 154: 'Settings, each save a version.', 155: 'The identity recheck on W2, shipped off.', 162: 'Spend per workflow this month.',
    164: 'AI features per workflow (W4, W5, W6, W8, W10 write with Claude).', 170: 'Its logbook, filtered to the workflow.', 172: 'Its report, on demand.',
    188: 'Nothing here changes how a workflow gathers, Railway\'s cron, or spends money.',
}


def reqs():
    L = open(PLAN + 'step1-requirements.md').read().split('\n')
    rows, total = [], 0
    for i, l in enumerate(L):
        m = re.match(r'^\| R(\d+) \|', l)
        if m:
            total += 1
            r = int(m.group(1))
            if r in REQ_HOW:
                rows.append((r, i + 1, l.split('|')[2].strip()))
    return rows, total


# ---------------------------------------------------------------- the page
def ring(w, big=False):
    st = stage(w)
    p = pct(w)
    return scorering.score(p, SK[st], f'{w} {wname(w)}: setup {p:.0f} percent, {st}', cls='big' if big else '')


GROUPS = {'att': ['W1', 'W3'], 'warn': ['W2', 'W3'], 'off': ['W0', 'W13', 'W14', 'W2']}   # Paused now's groups, by the cards they name


def groups(w):
    g = [k for k, ws in GROUPS.items() if w in ws]
    return ' '.join(g) if g else 'none'


def list_card(w):
    k, words, why = state(w)
    return (f'<article class="wfg wfl s-{k}" id="wf-{w}" data-wf-g="{groups(w)}"><button type="button" class="wfg-open" data-open-panel="p-wfp-{w}" aria-label="Open {w} {E(wname(w))}: a list, not a gauge">'
            f'<span class="wfg-h"><b>{w}</b> {E(wname(w))}</span><span class="wfg-list">{E(LIST_ONLY[w])}</span>{B.lt(k, words)}</button>'
            f'<div class="btns"><button class="btn path" data-open-panel="p-wfa-{w}">Workflows ▸</button><button class="btn path" data-open-panel="p-wfc-{w}">Core Services ▸</button>'
            f'<button class="btn path" data-open-panel="p-wfr-{w}">Registered Services ▸</button></div></article>')


def card(w, links):
    if w in LIST_ONLY:
        return list_card(w)
    k, words, why = state(w)
    st = stage(w)
    m, t, _ = MEASURE[w]
    nsu = sum(1 for x in setup(w) if x[1])
    return (f'<article class="wfg s-{k}" id="wf-{w}" data-wf-g="{groups(w)}"><button type="button" class="wfg-open" data-open-panel="p-wfp-{w}" aria-label="Open {w} {E(wname(w))}: {E(words)}; {E(st)}, setup {nsu} of 4">'
            f'<span class="wfg-h"><b>{w}</b> {E(wname(w))}</span>{ring(w)}<span class="band-word stage st-{SK[st]}">{E(st)}</span>'
            f'<span class="wfg-su">setup: {nsu} of 4</span>{B.lt(k, words)}'
            f'<span class="wfg-m"><span class="proposed">proposed</span> {E(m)}</span></button>'
            f'<div class="btns"><button class="btn path" data-open-panel="p-wfa-{w}">Workflows ▸</button><button class="btn path" data-open-panel="p-wfc-{w}">Core Services ▸</button>'
            f'<button class="btn path" data-open-panel="p-wfr-{w}">Registered Services ▸</button></div></article>')


def paused_now():
    att = [('W1', 'Two trips: overture_bulk (#1290) and foursquare_bulk (#1288) failed at /api/crawl/loads.php; W1 is paused whole. Its fix: IR-0001 (the endpoint\'s timings), then Check and start again.'),
           ('W3', 'Read a source and Common Crawl: Off by the system, left by a closed trip (Common Crawl #35). Its fix: switch them on (nothing switches them back by itself, R98).')]
    warn = [('W2', '/api/crawl/refresh counted more than it received (received 1, counted 2).'), ('W3', '77 sources are read regularly outside our region (ebi.ac.uk, va.gov, jcis.jp and more).')]
    off = [('W0', 'shipped off until the team starts it (release #79)'), ('W13', 'people\'s own: nothing to switch'), ('W14', 'licence_lookup isn\'t built'),
           ('W2', 'its identity recheck task, shipped off (R155)')]
    li = lambda xs, k: ''.join(f'<li>{B.lt(k, w)} <a href="#" data-open-panel="p-wfp-{w}">{E(wname(w))}</a>: {E(t)}</li>' for w, t in xs)
    return (f'<div class="wp-now" role="status"><h2 class="h2">Paused now, on the workflows <span class="live">live · {E(B.T_AL)}</span></h2>'
            f'<div class="wp-cnt">' + ''.join(f'<button type="button" class="wp-f" data-wf-filter="{g}" aria-pressed="false">{lab} <b>{len(GROUPS[g]) if g != "none" else sum(1 for w, *_ in B.WORKFLOWS if groups(w) == "none")}</b></button>'
                                       for g, lab in (('att', 'Needs attention'), ('warn', 'Warnings'), ('off', 'Off on purpose'), ('none', 'No open alarm'))) + '</div>'
            f'<h3 class="h3">Needs attention</h3><ul class="wp-l">{li(att, "r")}</ul>'
            f'<details class="more"><summary>Waiting on those</summary><ul class="wp-l"><li>W1\'s other tasks (coverage, second_look, targets, npi_bulk, irs_bulk, geofabrik_bulk) wait while W1 is paused whole. '
            f'The build makes /api/crawl/loads.php a component with its own check, so a failure there holds only overture_bulk, foursquare_bulk and geofabrik_bulk (R87; crawler.py:3552–3591, 3704–3757).</li></ul></details>'
            f'<h3 class="h3">Warnings</h3><ul class="wp-l">{li(warn, "a")}</ul>'
            f'<h3 class="h3">Off on purpose</h3><ul class="wp-l">{li(off, "n")}</ul></div>')


def clocks():
    return ('<details class="more wp-clk"><summary>The two clocks (02, model 1.2)</summary><div class="wp-2"><div><h3 class="h3">The run schedule: when work happens</h3>'
            '<p class="sub">Each workflow\'s "What starts it" (on its card\'s page); a task\'s every N minutes and its share of a run; the bulk loads\' night window (1–5 AM Arizona); '
            'the site\'s jobs (Scheduler::JOBS); the 2 AM pre-flight; the watch every 5 minutes.</p></div>'
            '<div><h3 class="h3">The diagnostic clock: how a fault is judged</h3><p class="sub">A failing component\'s check: 3 tries or 60 minutes (control_fix_tries, control_fix_minutes; '
            '"Keep the hour"); slow is never a trip; the back-off ladder; late and stuck (a job past its time + 5 min; a run unfinished after 2 hours; W0 silent over 5 min); '
            'Pulse\'s escalation (Critical: the Manager at 2 hours, the Director at 4). <b>A scheduled run is not a try.</b></p></div></div></details>')


def case_html():
    steps = ''.join(f'<li class="wp-cs s-{k}"><span class="wp-csh">{B.lt(k, t)} <span class="{"live" if tag.startswith("real") else "sctag"}">{E(tag)}</span></span><p class="sub">{E(x)}</p></li>'
                    for t, k, tag, x in CASE)
    return (f'<section class="wp-case" id="wf-case" aria-label="One case, end to end"><h2 class="h2">One case, end to end: Common Crawl\'s index down holds only W3\'s per-site copy</h2>'
            f'<p class="sub">From 03 §28.1. A source down holds only what uses it (R88, release #247). Stages that aren\'t real today are marked illustration.</p><ol class="wp-csl">{steps}</ol></section>')


def kmd_html():
    rows = ''.join(f'<tr><td>{B.lt({"kept": "g", "moved": "a", "dropped": "n"}[k], k.capitalize())}</td><td><b>{E(what)}</b><div class="micro">{E(src)}</div></td><td>{E(why)}</td></tr>' for k, what, src, why in LIVE)
    n = {k: sum(1 for x in LIVE if x[0] == k) for k in ('kept', 'moved', 'dropped')}
    return (f'<details class="more wp-kmd"><summary>Today\'s Workflows page: {n["kept"]} kept, {n["moved"]} moved, {n["dropped"]} dropped</summary>'
            f'<p class="sub">The live page (https://traversence.com/admin/config/workflows, 0175 R2b part C, release #243, {MIRROR}): the stage builder above, the crawler\'s section (#crawler) below, '
            f'with the Workflow board folded in.</p><table class="wp-t"><thead><tr><th>What</th><th>Today</th><th>Why</th></tr></thead><tbody>{rows}</tbody></table></details>')


LOADS = ('<div class="wp-comp"><h3 class="h3">A Core Services component: <code>/api/crawl/loads.php</code> <span class="proposed">build requirement</span></h3>'
         '<p class="sub">The session manager (2026-11-22, R87, #247\'s rule): the loads endpoint is a component with its own check, holding only the three tasks that post to it. '
         '<b>Its check:</b> the endpoint answers within the worker\'s timeout (120 s, crawler.py:3588, :3711), read from its calls in the ledger (control_ledger, endpoint crawl/loads). '
         '<b>What it holds when it fails:</b> overture_bulk, foursquare_bulk (open_load, crawler.py:3552–3591) and geofabrik_bulk (geofabrik_send, :3704–3757). '
         'coverage, second_look, targets, npi_bulk and irs_bulk don\'t call it and carry on. <b>Today</b> its failure paused W1 whole (two trips): the build fixes that.</p></div>')


def body(links=True):
    cards = ''.join(card(w, links) for w, *_ in B.WORKFLOWS)
    return (f'<div class="wp-head"><h1>Workflows</h1><span class="dg-draft">draft for the session manager, then Jason</span></div>'
            f'<p class="sub">W0–W14 from the live <code>Automation::WORKFLOWS</code>, each one a gauge under the approved lifecycle: setup % through Planning to Implementation, '
            f'its live reading against its target at Monitoring (Jason, 2026-11-22). The measures and targets are <span class="proposed">proposed</span>, ours. '
            f'A call happens only when the master switch, its workflow, its task and its service are all on (R140); a source down holds only what uses it (R88).</p>'
            f'{paused_now()}{clocks()}<div class="wp-grid" id="wp-grid">{cards}</div>{case_html()}{kmd_html()}'
            f'<details class="more"><summary>By workflow, this month</summary><p class="sub">Operations::costs is live data the snapshot doesn\'t hold: not read here. '
            f'On the live page it is the spend per workflow and service (operations-console.php:477–479).</p></details>')


def _list(xs):
    return '<ul class="wp-l">' + ''.join(f'<li>{x}</li>' for x in xs) + '</ul>' if xs else '<p class="sub">None.</p>'


def page_panel(w, links):
    k, words, why = state(w)
    st = stage(w)
    m, t, _ = MEASURE[w]
    al = B.WF_ALARMS.get(w, [])
    alh = ''.join(f'<li>{B.lt("r" if a["level"] == "trip" else "a", "Trip" if a["level"] == "trip" else "Warning")} {E(a["title"][:220])} <span class="micro">{E(a.get("since", "")[:16].replace("T", " "))} UTC</span></li>' for a in al)
    su = ''.join(f'<li>{"✓" if ok else "·"} <b>{E(n)}</b>: {E(x)}</li>' for n, ok, x in setup(w))
    tk = ''
    for t_ in TASKS.get(w, []):
        what, src = TASK_INFO.get(t_, ('', ''))
        ts = TASK_STATE.get(t_, ('n', 'Its switch: not read here'))
        nds = [n for n in B.NEEDS if n['key'] in TASK_NEEDS.get(t_, [])]
        nh = ''.join(f'<div class="micro"><b>{E(n["label"])}</b>: {src_html(n, links)} · else {E(n["fallback"])}</div>' for n in nds) or '<div class="micro">no need in the map</div>'
        tk += f'<tr><td><b>{E(t_)}</b><div class="micro">{E(what)} ({E(src)})</div></td><td>{nh}</td><td>{B.lt(*ts)}</td></tr>'
    ho = ''.join(f'<li><b>{E(n["label"])}</b>: if {E(n["srcs"][0][2])} fails, {E(then)}.{(" Used by " + E(", ".join(users)) + ".") if users else ""}</li>' for n, users, then in holds(w))
    case = (f'<p class="sub"><a href="#wf-case" data-wf-case>The case drawn end to end: Common Crawl\'s index down</a> holds only the per-site copy; the bulk reads carry on.</p>' if w in ('W2', 'W3') else '')
    gap = LOADS if w == 'W1' else ''
    person = _list([E(x) for x in PERSON.get(w, [])])
    st_src = STARTS[w]
    fields = ''.join(f'<th>{E(f)}</th>' for f in TASK_FIELDS)
    return (f'<aside class="panel" id="p-wfp-{w}" hidden role="dialog" aria-label="{w} {E(wname(w))}: its page"><button class="x" aria-label="Close">×</button>'
            f'<div class="crumb">Workflows · its own page (https://traversence.com/admin/config/control/wf-{w})</div><h2 class="h2">{w} {E(wname(w))}</h2>'
            f'<p class="sub">{E(wwhat(w))} Runs on: {E(wwhere(w))}.</p>'
            f'<div class="wp-st">{B.lt(k, words)}<p class="sub">{E(why)}</p></div>{gap}'
            + (f'<h3 class="h3">Its alarms <span class="live">live · {E(B.T_AL)}</span></h3><ul class="wp-l">{alh}</ul>' if al else '')
            + (f'<h3 class="h3">A list, not a gauge</h3><p class="sub">{E(LIST_ONLY[w])}</p>' if w in LIST_ONLY else
               f'<h3 class="h3">Its gauge</h3><div class="wp-gz">{ring(w, True)}<span class="band-word stage st-{SK[st]}">{E(st)}</span></div>'
               f'<p class="sub">Setup through Planning to Implementation (Jason approved, 2026-11-22). <span class="proposed">proposed</span> measure: {E(m)}; target: {E(t)}.</p><ul class="wp-l wp-su">{su}</ul>')
            + f'<h3 class="h3">What starts it (the run schedule)</h3><p class="sub">{E(st_src[0])} <span class="micro">({E(st_src[1])})</span></p>'
            f'<h3 class="h3">Its steps and their switches</h3>'
            + (f'<table class="wp-t"><thead><tr><th>Task</th><th>Its needs: sources in order, then the fallback</th><th>Its switch</th></tr></thead><tbody>{tk}</tbody></table>' if tk else '<p class="sub">No tasks: nothing runs.</p>')
            + f'<h3 class="h3">What a source down holds (R88)</h3>{("<ul class=wp-l>" + ho + "</ul>") if ho else "<p class=sub>No need in the map.</p>"}{case}'
            f'<h3 class="h3">Steps that need a person</h3>{person}'
            f'<details class="more"><summary>Its settings (kept from today\'s page; each save a version)</summary><p class="sub">What starts it, its triggers (in words), its share of a run, its budgets (its share of the AI cap, its monthly budget), its gates, its last 12 versions (go back to one), and per task:</p>'
            f'<table class="wp-t wp-f6"><thead><tr><th>Task</th>{fields}</tr></thead><tbody><tr><td colspan="7" class="micro">Live values: not read here (WorkflowSettings::current)</td></tr></tbody></table>'
            f'<div class="btns"><button class="btn" disabled title="Drawn: inert in the mockup">Save (a new version; the switch-on check runs first)</button></div></details>'
            f'<div class="btns"><button class="btn" disabled title="Drawn: inert in the mockup">Check and start again</button><button class="btn" disabled title="Drawn: inert in the mockup">Shut down {w} (asks why)</button>'
            f'<button class="btn" disabled title="Drawn: inert in the mockup">Take out for maintenance</button><button class="btn" disabled title="Drawn: inert in the mockup">Its data</button>'
            f'<button class="btn" disabled title="Drawn: inert in the mockup">Its logbook</button><button class="btn" disabled title="Drawn: inert in the mockup">Its report</button></div>'
            f'<div class="btns"><button class="btn path" data-open-panel="p-wfa-{w}">Workflows ▸</button><button class="btn path" data-open-panel="p-wfc-{w}">Core Services ▸</button>'
            f'<button class="btn path" data-open-panel="p-wfr-{w}">Registered Services ▸</button></div>'
            f'<div class="btns navb"><button class="btn back" data-wf-back>← Back</button></div></aside>')


def path_panels(w, links):
    fd = feeds(w)
    fh = _list([f'<b>{E(n)}</b> (its tasks: {E(", ".join(ts))})' for o, n, ts in fd]) if fd else '<p class="sub">No gauge on the board names it yet: association not mapped.</p>'
    to = [x for x in SENDS if x[0] == w]
    fr = [x for x in SENDS if x[1] == w]
    th = _list([f'<a href="#" data-open-panel="p-wfp-{b}">{b} {E(wname(b))}</a>: {E(why)} <span class="micro">({E(s)})</span>' for a, b, why, s in to])
    frh = _list([f'<a href="#" data-open-panel="p-wfp-{a}">{a} {E(wname(a))}</a>: {E(why)} <span class="micro">({E(s)})</span>' for a, b, why, s in fr])
    cs = _list([f'<b>{E(assoc.CORE_PARTS[c][0])}</b> <span class="micro">({E(assoc.CORE_PARTS[c][2])})</span>' for c in core(w)]) + (LOADS if w == 'W1' else '')
    rs = _list([f'<b>{E(n["label"])}</b>: {src_html(n, links)} <span class="micro">· else {E(n["fallback"])}{" (soft: its fallback always works)" if n["soft"] else ""}</span>' for n in needs_of(w)])
    back = f'<div class="btns navb"><button class="btn back" data-wf-back>← Back</button></div>'
    head = lambda pid, t: f'<aside class="panel" id="{pid}" hidden role="dialog" aria-label="{w}: {t}"><button class="x" aria-label="Close">×</button><div class="crumb">Workflows · <a href="#" data-open-panel="p-wfp-{w}">{w} {E(wname(w))}</a></div><h2 class="h2">{t}</h2>'
    return (head(f'p-wfa-{w}', 'Workflows: what it feeds, and what feeds it') + f'<h3 class="h3">The board\'s gauges it feeds</h3>{fh}<h3 class="h3">It sends work to</h3>{th}<h3 class="h3">Work comes from</h3>{frh}{back}</aside>'
            + head(f'p-wfc-{w}', 'Core Services: our own parts it rests on') + f'{cs}{back}</aside>'
            + head(f'p-wfr-{w}', 'Registered Services: its needs, sources in order') + f'<p class="sub">Ours first, then outside free before paid; the first that works wins (R61). Each from Preflight::NEEDS ({MIRROR}).</p>{rs}{back}</aside>')


def panels(links=True):
    return ''.join(page_panel(w, links) + path_panels(w, links) for w, *_ in B.WORKFLOWS)


CSS = r"""
.wp-head{display:flex;flex-wrap:wrap;gap:.4rem .7rem;align-items:center}.wp-head h1{font:700 22px/1.2 var(--serif);margin:0}
.wp-now{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.5rem .7rem;margin:.6rem 0}.wp-now .h2{margin:0 0 .3rem;font-size:16px}
.wp-cnt{display:flex;flex-wrap:wrap;gap:.3rem;margin:.2rem 0 .4rem}.wp-f{border:1px solid var(--field-edge);background:var(--card);border-radius:999px;padding:.15rem .6rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--ink)}
.wp-f b{margin-left:.3rem}.wp-f[aria-pressed="true"]{background:var(--btn);color:var(--btn-text);border-color:var(--btn)}
.wp-l{margin:.2rem 0 .4rem 1rem;padding:0;font-size:13px;display:flex;flex-direction:column;gap:.25rem;overflow-wrap:anywhere}
.wp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem;margin:.6rem 0}
.wfg{display:flex;flex-direction:column;background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.5rem;min-width:0}.wfg.s-r{border-color:var(--red)}.wfg.hit{box-shadow:0 0 0 2px var(--copper)}
.wfg[hidden]{display:none}.wfg-open{display:flex;flex-direction:column;align-items:center;gap:.2rem;background:none;border:0;font:inherit;color:inherit;cursor:pointer;text-align:center;padding:0}
.wfg .lt{font-size:12.5px;font-weight:600}.wfg-list{font-size:12.5px;color:var(--meta);line-height:1.35;margin:.3rem 0}.wfg-h{font-size:13.5px;line-height:1.25;min-height:2.5em}.wfg-su{font-size:12px;color:var(--meta)}.wfg-m{font-size:11.5px;color:var(--meta);line-height:1.3}
.wfg .btns{justify-content:center;margin-top:.35rem}.wfg .btn.path{font-size:11.5px;padding:.1rem .5rem}
.wp-case{background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:.5rem .7rem;margin:.8rem 0}.wp-case .h2{font-size:16px;margin:0 0 .2rem}
.wp-csl{margin:.3rem 0 0 1.1rem;padding:0;display:flex;flex-direction:column;gap:.45rem}.wp-csh{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}.wp-cs .sub{margin:.15rem 0 0}
.wp-t{width:100%;border-collapse:collapse;font-size:12.5px}.wp-t td,.wp-t th{border-top:1px solid var(--card-edge);padding:.3rem .35rem;text-align:left;vertical-align:top;overflow-wrap:anywhere}
.wp-2{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}.wp-gz{display:flex;flex-direction:column;align-items:center;gap:.2rem}.wp-st{margin:.3rem 0}.wp-gap{font-size:12.5px}
.wp-su{list-style:none;margin-left:0}
@media (max-width:767px){.wp-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}.wfg{padding:.4rem .25rem}.wp-2{grid-template-columns:1fr}
 .wp-t thead{display:none}.wp-t tr{display:block;border-top:1px solid var(--card-edge);padding:.3rem 0}.wp-t td{display:block;border:0;padding:.1rem 0}.wfg .btn.path{font-size:11px;padding:.1rem .35rem}}
"""

JS = r"""<script>
(function(){if(window.__wpInit)return;window.__wpInit=1;var trail=[],go=0;
document.addEventListener('click',function(e){
 var f=e.target.closest('[data-wf-filter]');if(f){var k=f.getAttribute('data-wf-filter'),on=f.getAttribute('aria-pressed')!=='true';
  document.querySelectorAll('[data-wf-filter]').forEach(function(x){x.setAttribute('aria-pressed',x===f&&on?'true':'false');});
  document.querySelectorAll('.wfg').forEach(function(c){c.hidden=on&&(' '+c.getAttribute('data-wf-g')+' ').indexOf(' '+k+' ')<0;});return;}
 var c=e.target.closest('[data-wf-case]');if(c){e.preventDefault();var x=document.querySelector('aside.panel:not([hidden]) .x');if(x)x.click();var s=document.getElementById('wf-case');if(s)s.scrollIntoView({block:'start'});return;}
 var o=e.target.closest('[data-open-panel]');if(o&&!go&&/^p-wf[parc]-/.test(o.getAttribute('data-open-panel'))){var cur=o.closest('aside.panel');if(cur)trail.push(cur.id);else trail.length=0;}
 var b=e.target.closest('[data-wf-back]');if(b){e.preventDefault();var p=trail.pop();if(p){var t=document.createElement('a');t.setAttribute('data-open-panel',p);t.hidden=true;document.body.appendChild(t);go=1;t.click();go=0;t.remove();}
  else{var xx=b.closest('aside.panel').querySelector('.x');if(xx)xx.click();}}},true);
})();
</script>"""

JS_PAGE = r"""<script>
(function(){var b=document.body;function close(){b.classList.remove('open');document.querySelectorAll('aside.panel').forEach(function(p){p.hidden=true;});}
function open(id){var p=document.getElementById(id);if(!p)return;document.querySelectorAll('aside.panel').forEach(function(q){q.hidden=q.id!==id;});b.classList.add('open');p.scrollTop=0;var x=p.querySelector('.x');if(x)x.focus({preventScroll:true});}
document.addEventListener('click',function(e){var t=e.target.closest('[data-open-panel]');if(t){e.preventDefault();open(t.getAttribute('data-open-panel'));return;}
 if(e.target.closest('aside.panel .x')){close();return;}if(e.target.classList.contains('scrim'))close();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});})();
</script>"""


def view():
    """The board's Workflows view (board.py): the page, then today's needs table kept in a fold."""
    return body(True)


def extra_css(css):
    """The board's rules this page needs beyond the Development page's set (stage pills, lights' words, proposed, own sources)."""
    keep = re.compile(r'\.band-word|\.st-(pl|pd|ar|im|mo)\b|\.proposed|\.own\b|\.mlink|\.pill\b')
    out = []
    for m in re.finditer(r'(?<![@\w-])([^{}@]+)\{([^{}]*)\}', css):
        if keep.search(m.group(1)):
            out.append(m.group(0).strip())
    return ''.join(out)


def page(base_css, dev_css):
    b = (f'<header class="bhead"><b>System Control Board</b><span class="chip">Workflows · design mockup · step 3 · not site code</span></header>'
         f'<main class="wp-main">{body(False)}<p class="sub">Generated by <code>step3/mockups/src/workflows.py</code> from the live mirror ({MIRROR}), the alarms snapshot ({E(B.T_AL)}) and 03 §28.1. '
         f'Spec: <code>step3/workflows-page.md</code>.</p></main><div class="scrim"></div>{panels(False)}{JS}{scorering.JS}{JS_PAGE}')
    return (f'<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            f'<title>Workflows Page</title><meta name="description" content="Step 3 mockup: the board\'s Workflows page, W0–W14 as gauges, what a source down holds, and one case end to end.">'
            f'<style>{dev_css(base_css)}{extra_css(base_css)}{CSS}.wp-main{{padding:1rem 16px 3rem;max-width:1280px;margin:0 auto}}@media (max-width:767px){{.wp-main{{padding:.6rem 3px 3rem}}}}</style></head><body>{b}</body></html>')


# ---------------------------------------------------------------- the spec
def spec():
    rows, total = reqs()
    W = [w for w, *_ in B.WORKFLOWS]
    st = {w: stage(w) for w in W}
    out = ['# The board\'s Workflows page (a draft for the session manager, then Jason)', '',
           'Step 3, 2026-11-22. A spec and a mockup: nothing is built. The mockup is `board/workflows.html` (standalone, 1280 and 390) and the board\'s',
           'Workflows view (`board/index.html`), both generated by `mockups/src/workflows.py`. Real data only: the live mirror (' + MIRROR + '), the alarms',
           'snapshot (' + B.T_AL + '), 03 §28.1 and the crawler worker (`workers/crawler/crawler.py`). What isn\'t real today is marked',
           '**illustration** or **proposed**. Every gauge is a score gauge in Arc (no needle; Jason\'s choice, 2026-11-22).', '',
           '## 1. What the page is', '',
           '- One page for the Knowledge Engine\'s workflows, W0–W14, on the System Control Board (R8, R17). It merges today\'s Workflows page and the folded-in',
           '  Workflow board (decisions/0175 L421–422: "Each workflow\'s page has its steps, services, backups and task switches"; release #243).',
           '- Top: **Paused now, on the workflows** in three groups (R21, R22), with counts that filter the cards (R11). Then the two clocks, then one gauge card per',
           '  workflow, then one case drawn end to end, then what happens to today\'s page.',
           '- Each card opens the workflow\'s own page (https://traversence.com/admin/config/control/wf-W1 and so on, R144) in the side panel; its three paths open',
           '  beside it. Every panel closes by ×, Esc and a click outside, with Back.', '',
           '## 2. Requirements it answers: ' + str(len(rows)) + ' of ' + str(total) + ' cited', '',
           'Each from `handoffs/control-board-plan/step1-requirements.md`, by its R-number and line. The rest (' + str(total - len(rows)) + ') belong to other pages',
           '(Modules, the Service register, Diagnostics, Pulse, the alarms\' channels) and aren\'t restated here.', '',
           '| R | Line | The requirement (shortened) | How this page answers it |', '|---|---|---|---|']
    for r, ln, txt in rows:
        out.append(f'| R{r} | L{ln} | {txt[:150].replace("|", "/")}{"…" if len(txt) > 150 else ""} | {REQ_HOW[r]} |')
    out += ['', '## 3. The state model applied (02, model 1.2)', '',
            '- **A source down holds only what uses it (R88, release #247).** For each need of a workflow, its sources are in priority order (ours first, then',
            '  outside free before paid; R61). When the first fails, the next carries on and the workflow reads **On a backup**, a working state, never red (R90).',
            '  With no source left, only the tasks that use that need pause; the rest of the workflow runs (R87, R99). A workflow is paused whole only when every',
            '  task is held. Every workflow\'s page lists this need by need ("What a source down holds").',
            '- **Component and workflow are checked separately (R89).** The component (a service, our endpoint, a worker, a job) has its own check, pause and',
            '  card; the workflow reroutes. A trip places its pause **beside** the switches, never in them (0175 release 1), so nothing is left to restore when it',
            '  clears (R91).',
            '- **The two clocks.** The run schedule says when work happens (a task\'s every N minutes, its share of a run, the bulk loads\' night window 1–5 AM',
            '  Arizona, the site\'s jobs, the 2 AM pre-flight, the watch every 5 minutes). The diagnostic clock judges a fault (a component\'s check: 3 tries or 60',
            '  minutes; slow is never a trip; the back-off ladder; late and stuck; Pulse\'s escalation). **A scheduled run is not a try** (R67, R68).',
            '- **Words follow the state (R101, R105, R110).** "Paused by the system (a trip): …" for a trip (R77); "Off by the system, left by a closed trip" for',
            '  W3\'s switches; "Off" with its reason for what\'s off on purpose, never a failure (R109).', '',
            '### Today against the model, and a build requirement', '',
            '- **W1 is paused whole by two task trips** (overture_bulk #1290, foursquare_bulk #1288; the alarms snapshot).',
            '- **Build requirement (the session manager, 2026-11-22; R87, #247\'s rule): `/api/crawl/loads.php` becomes a Core Services component with its own',
            '  check**, holding only the three tasks that post to it: `overture_bulk`, `foursquare_bulk` (`open_load`, crawler.py:3552–3591) and `geofabrik_bulk`',
            '  (`geofabrik_send`, :3704–3757). Its check: the endpoint answers within the worker\'s timeout (120 s, crawler.py:3588, :3711), read from its calls in',
            '  the ledger. `coverage`, `second_look`, `targets`, `npi_bulk` and `irs_bulk` don\'t call it and carry on. Shown on W1\'s page and its Core Services path.',
            '- **W3 does nothing** while its two switches (Read a source, Common Crawl) stay Off by the system, written by the trip before 0175 release 1. They stay',
            '  off until a person switches them on (R98). Open question 3.', '',
            '## 4. The three paths, starting from a workflow', '',
            '- **Workflows ▸**: the board\'s gauges it feeds (the association map, inverted: `assoc.SNAP`), the workflows it sends work to, and those that send it work',
            '  (the research table\'s "Started by", ' + RT + ':171–183).',
            '- **Core Services ▸**: our own parts it rests on: the databases (always), the workers on Railway (crawler and indexer workflows), the scheduled jobs (site',
            '  and crawler workflows), the dispatcher (every workflow with alarms).',
            '- **Registered Services ▸**: its needs, each with its sources in order and its fallback, from `Preflight::NEEDS`; each source links its card on the board.', '',
            '| Workflow | Feeds (gauges) | Sends work to | Work from | Core |', '|---|---|---|---|---|']
    for w in W:
        out.append(f'| {w} {wname(w)} | {", ".join(n for _, n, _ in feeds(w)) or "not mapped"} | {", ".join(b for a, b, *_ in SENDS if a == w) or "—"} | '
                   f'{", ".join(a for a, b, *_ in SENDS if b == w) or "—"} | {", ".join(core(w))} |')
    out += ['', '## 5. Each workflow as a gauge (the approved lifecycle)', '',
            'Each workflow is a runtime gauge under the lifecycle Jason approved (2026-11-22; `sections/gauge-lifecycle.md` §7): four setup criteria (the probe',
            'built, its target set, its check passing, its alarm wired to the board). Below Monitoring the score is setup %, in the stage\'s colour; at Monitoring it',
            'is the live reading against its target, coloured by it. The measures and targets are **proposed** (ours) for Jason to set.', '',
            '| Workflow | Stage | Setup | Proposed measure | Proposed target | The probe |', '|---|---|---|---|---|---|']
    for w in W:
        if w in LIST_ONLY:
            out.append(f'| {w} | a list, not a gauge | — | {LIST_ONLY[w]} | — | — |')
            continue
        m, t, p = MEASURE[w]
        out.append(f'| {w} | {st[w]} | {sum(1 for x in setup(w) if x[1])} of 4 ({pct(w):.0f}%) | {m} | {t} | {p or "none: " + setup(w)[0][2]} |')
    cnt = {s: sum(1 for w in W if st[w] == s and w not in LIST_ONLY) for s in STAGES}
    out += ['', 'Today: ' + '; '.join(f'{s} {n}' for s, n in cnt.items() if n) + '. No workflow passes Audit & Review until Jason sets its target (proposed above).',
            '**Alarm wired** (the session manager, 2026-11-22) means the workflow\'s failures raise through the one alarm engine, with their Fix it or Check it now',
            'landing on one item; a `pf:wf:W#` gate is part of "check passing", not this. From the mirror: a crawler task\'s failed runs raise on its workflow',
            '(ControlBoard.php:446–470; ControlAlarms.php:44–52); a site job tied to a workflow raises on it and pauses it (Automation::JOBS, Automation.php:50–61;',
            'ControlAlarms.php:279–285, 953–961); W0\'s worker raises on wf:W0 (ControlAlarms.php:278, 743–744); every alarm raised lands on one item',
            '(tools/control-board-tests/routes.php). So W0–W12 are wired; W14 has nothing built to raise; W13 is a list.', '',
            '## 6. W0–W14 in full', '',
            'Each workflow\'s tasks (with where each is described), its needs with sources in priority order and fallback, what starts it, and its real state.', '']
    for w in W:
        k, words, why = state(w)
        out += [f'### {w} {wname(w)}', '', f'- **What it does:** {wwhat(w)} Runs on: {wwhere(w)}.', f'- **What starts it:** {STARTS[w][0]} ({STARTS[w][1]}).',
                f'- **State ({B.T_AL}):** {words}. {why}']
        if TASKS.get(w):
            out.append('- **Tasks:**')
            for t_ in TASKS[w]:
                what, src = TASK_INFO.get(t_, ('', ''))
                ts = TASK_STATE.get(t_, ('n', 'switch not read here'))[1]
                out.append(f'  - `{t_}`: {what} ({src}). Switch: {ts}.')
        else:
            out.append('- **Tasks:** none (nothing runs).')
        nd = needs_of(w)
        if nd:
            out.append('- **Needs, sources in order:**')
            for n in nd:
                out.append(f'  - {n["label"]}: {" → ".join(s[2] for s in n["srcs"])}; else {n["fallback"]}.')
        if PERSON.get(w):
            out.append('- **Steps that need a person:** ' + '; '.join(PERSON[w]) + ' (WorkflowSettings::PERSON).')
        out.append('')
    n = {k: sum(1 for x in LIVE if x[0] == k) for k in ('kept', 'moved', 'dropped')}
    out += [f'## 7. Today\'s Workflows page: {n["kept"]} kept, {n["moved"]} moved, {n["dropped"]} dropped', '',
            'The live page is https://traversence.com/admin/config/workflows (`manifests.php:168`: the stage builder, then `tv_admin_mod_workflows_crawler` with the',
            'Workflow board folded in at #crawler; 0175 R2b part C, release #243). Its old address /admin/config/board still opens it.', '',
            '| | What | Today | Why |', '|---|---|---|---|']
    for k, what, src, why in LIVE:
        out.append(f'| {k.capitalize()} | {what} | `{src}` | {why} |')
    out += ['', '## 8. One case, end to end: Common Crawl\'s index down holds only W3\'s per-site copy', '',
            'Drawn on the page (and the board\'s view) as six steps. From 03 §28.1.', '']
    for i, (t, k, tag, x) in enumerate(CASE, 1):
        out.append(f'{i}. **{t}** ({tag}). {x}')
    out += ['', '## 9. The mockup', '',
            '- `board/workflows.html`: standalone, under 300 KB, at 1280 and 390 (two cards a row on a phone, no sideways scroll). Arc gauges,',
            '  Jason\'s choice. Checked by `mockups/src/workflows-check.cjs`: 14 gauges and W13\'s list, one Arc each with its stage pill and its band within 1% of',
            '  its score, every panel closing three ways with Back, the filters, the case\'s six steps, the validator.',
            '- The board\'s Workflows view (`board/index.html`, `board.py` calls `workflows.view()`), and its review-pack part.', '',
            '## 10. Settled by the session manager (2026-11-22)', '',
            '- **Fix it in the case: R118 wins.** A 504 is their trouble: no Fix it, only Check it now and Pause it, with "the system is waiting on them". (Was open question 1.)',
            '- **The loads endpoint** is a component with its own check: a build requirement (§3). (Was open question 2.)',
            '- **W13 is a list, not a gauge**: it has no machine steps to measure. (Was open question 6.)',
            '- **Spend and last runs** come from the build\'s data, as written. (Was open question 7.)',
            '- **"Alarm wired"** is the one alarm engine, not a pf:wf gate (§5); the setup recounted. (Was open question 8.)',
            '- **W0 started** stays in `asks.md` (visit 17) as live data. (Was open question 5.)', '',
            '## 11. Open questions', '',
            '1. **W3\'s two switches.** They were written Off by the older trip and stay off (R98). Switch them on now, or wait for IR-0001 and the Common Crawl',
            '   index to be read again?',
            '2. **The measures and targets** in §5 are ours. Jason sets each (the "target set" criterion); until then every workflow stays at or below Audit & Review.']
    open(STEP3 + 'workflows-page.md', 'w').write('\n'.join(out) + '\n')
    return dict(cited=len(rows), total=total, stages=cnt, kmd=n, case=len(CASE))
