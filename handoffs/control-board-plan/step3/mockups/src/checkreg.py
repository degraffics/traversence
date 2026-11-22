# The one register of checks (the session manager's proposal to Jason, 2026-11-22): every check the page audit has, and the
# four it doesn't yet, each with its scope, measure, target, schedule and evidence. The development gauges read it: an item's
# "checked" needs every check that applies to it passing, and a missing check shows "no check yet".
# Writes step3/sections/check-register.md and .json. Spec data, not site code.
import json, os, re

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = '/home/user/traversence/'
OUT = ROOT + 'handoffs/control-board-plan/step3/sections/'
GH = 'https://github.com/degraffics/traversence/blob/main/'
RULES = open(ROOT + 'site-audit/rules.md').read()
SCORE_MD = open(ROOT + 'site-audit/scorecard.md').read()
AT_SCORE = re.search(r'on (\d{4}-\d\d-\d\d \d\d:\d\d) UTC', SCORE_MD).group(1) + ' UTC'
AT_RENDER = json.load(open(ROOT + 'site-audit/results/rendered.json'))['at'][:16].replace('T', ' ') + ' UTC'
AT_PATHS = json.load(open(ROOT + 'site-audit/results/paths.json'))['at'][:16].replace('T', ' ') + ' UTC'
SC = json.load(open(ROOT + 'site-audit/results/search-scorecard.json'))
AT_SEARCH = SC['at'][:16].replace('T', ' ') + ' UTC'


def _r(rid):
    m = re.search(r'^\| ' + rid + r' \| (.*?) \| (.*?) \| (yes|no) \|', RULES, re.M)
    w = re.search(r'^\| ' + rid + r' \| (.*?) \| (\d+) \| (yes|no) \|', SCORE_MD, re.M)
    sc = re.search(r'^\| ' + rid + r' \| (.*?) \| (\d+) \| (yes|no) \|', SCORE_MD, re.M)
    if not m:   # on the scorecard but not yet in rules.md (R15)
        return sc.group(1), 'scorecard.md (not yet in rules.md)', sc.group(3), int(sc.group(2))
    return m.group(1).replace('`', ''), m.group(2).replace('`', ''), m.group(3), int(w.group(2)) if w else None


CODE_SCOPE = {'R05': 'pages', 'R06': 'the FAQ', 'R07': 'endpoints', 'R08': 'migrations', 'R09': 'pages', 'R10': 'pages', 'R11': 'content pages',
              'R12': 'services', 'R13': 'services', 'R14': 'tables', 'R15': 'workflows (worker endpoints)'}

CHECKS = []
for n in range(1, 16):
    rid = f'R{n:02d}'
    what, src, blocks, fails = _r(rid)
    CHECKS.append(dict(id=rid, name=what, source=src, scope=CODE_SCOPE.get(rid, 'every file (the mirror and every zip)'), measure='failures in the files it reads',
                       target='0 failures' + (' (blocks a delivery)' if blocks == 'yes' else ''), runs='twice a day after the syncs (site-audit.yml, audit.py) and on every zip (zipcheck.py)',
                       evidence=GH + 'site-audit/scorecard.md', status='built', latest=f'{fails} failures ({AT_SCORE})' if fails is not None else 'not read', result='pass' if fails == 0 else 'fail'))
CHECKS += [
    dict(id='RENDER', name='The rendered check: each page in a real browser', source='decisions/0145 §1, Phase 2; rules.md', scope='pages (public, member, every admin section)',
         measure='answers, console errors, failed requests, sideways scroll at 390, card edges 3px, the account bar', target='no failure at 1280 and 390, as each account',
         runs='daily 09:43 UTC (short), full on Mondays (site-checks.yml, rendered.mjs)', evidence=GH + 'site-audit/rendered.md', status='built', latest=f'run {AT_RENDER}', result='per page'),
    dict(id='PATHS', name='The paths check: what a visitor comes to do, end to end', source='decisions/0145 §1, Phase 4; rules J01–J03', scope='flows (core paths and guide paths)',
         measure='reaches its goal; steps, dead ends, accuracy, clarity; a page over 4 s on the way is flagged (D02)', target='every path passes (J01: 3 taps or fewer to eat and to a guide, 5 for the rest)',
         runs='daily after the rendered check (site-checks.yml, paths.mjs); guide paths a few a day in turn', evidence=GH + 'site-audit/paths.md', status='built', latest=f'run {AT_PATHS}', result='per path'),
    dict(id='SEARCH', name='The search probe and its scorecard', source='Jason, 2026-11-10; decisions/0060', scope='search (the search API)',
         measure='real searches: the place read, the words left, the guide offered, the first results; scored by case', target='every case passes (the probe\'s own bar)',
         runs='daily 10:17 UTC (search-probe.yml, search-probe.mjs, search-scorecard.mjs)', evidence=GH + 'site-audit/search-scorecard.md', status='built',
         latest=f'{SC["pass"]} of {SC["of"]} cases pass ({AT_SEARCH})', result='fail' if SC['pass'] < SC['of'] else 'pass'),
    dict(id='LAYOUT', name='The layout probe: what a phone shows at the top of a page', source='Jason, 2026-11-10; decisions/0058 §21', scope='pages (a landmark, Discovery, a place card)',
         measure='the gap between the top bar and the hero; anything drawn over a card\'s action icons', target='no gap (0058 §21); nothing over the icons',
         runs='daily with the search probe (search-probe.yml, layout-probe.mjs)', evidence=GH + 'site-audit/layout-probe.md', status='built', latest='5px above the hero on a landmark and on Discovery (2026-11-21 10:19 UTC)', result='fail'),
    dict(id='PHOTOS', name='Public photos load', source='2026-11-15 (the stock door photos\' 404); decisions/0123', scope='the uploads rule (every kind in Store::KINDS)',
         measure='a real stock photo and a member photo answer 200 as an image; every kind reaches media.php', target='all pass',
         runs='daily (site-checks.yml, photos-check.sh)', evidence=GH + '.github/workflows/site-checks.yml', status='built', latest='exit code in the run only: no result kept in git', result='not recorded'),
    dict(id='BOARD', name='The Control Board\'s readings: a workflow or service on real data', source='decisions/0171, 0175, 0176', scope='workflows and services',
         measure='records in and stored, its trips and warnings, its calls in the ledger', target='its process working, no open trip',
         runs='every 10 minutes (the board\'s watch) and as calls happen', evidence='https://traversence.com/admin/config/control', status='built', latest='per workflow (alarms snapshot, 2026-11-21 3:19 AM MST)', result='per item'),
    # the four gaps: proposed, not built
    dict(id='TOOLS', name='Each tool and endpoint, used by every type of account', source='decisions/0145 §1 "Function and tool checks"', scope='tools and endpoints (pages, admin sections, /api/, /admin/api/)',
         measure='used by each account that may (good, bad and empty input; the result against site-audit/tools.md) and refused, greyed with the reason, for each that may not',
         target='every tool does what tools.md says for every account', runs='proposed: daily for tools that only read; on staging for tools that change things',
         evidence='site-audit/tools.md (doesn\'t exist yet)', status='not built', latest='no check yet', result='no check'),
    dict(id='LOAD', name='Load time for every page against a target', source='decisions/0145 §1; the runtime gauge "Pages load fast"', scope='pages',
         measure='time to load at 1280 and 390, each page, each account', target='about 2.5 s, 99.5% of the time (proposed, des)', runs='proposed: with the rendered check',
         evidence='proposed: rendered.json timings for every page (today only paths flag a page over 4 s, and rendered records only failures)', status='not built', latest='no check yet', result='no check'),
    dict(id='PHOTOS-SHOWN', name='Every photo on a page shown', source='Jason, 2026-11-22: "the photo loads and display"', scope='pages that show photos',
         measure='each image on the page loaded and drawn (natural size above 0, no broken image), at 1280 and 390', target='every photo shown',
         runs='proposed: with the rendered check', evidence='proposed: a field per page in rendered.json', status='not built', latest='no check yet', result='no check'),
    dict(id='BRAND', name='The brand and design standards on every page', source='brand.md; design/css-theme-brand.md; CLAUDE.md', scope='pages',
         measure='the standards a browser can read: tokens and colours, one header per view, filters as one dropdown, card anatomy, icons, notices, busy states, forms. '
                 'Today only R10 (icons), R11 (breadcrumbs) and the rendered 3px edges are automatic', target='every readable standard met; the rest to the review',
         runs='proposed: with the rendered check', evidence='partial today: R10, R11 (scorecard.md) and RENDER\'s edges', status='not built (partial)', latest='partial: R10, R11 and the 3px edges only', result='partial'),
]
CHECKS.append(dict(id='AUDIT', name='The audit\'s own load: what each run asks of the site', source='Jason, 2026-11-22: "wont bog our platform or resources down creating slowness issues"',
                   scope='every run of every check', measure='each run\'s requests to the site, its time, and the probe pages\' response times against their baseline',
                   target='', runs='every run reports it', evidence='proposed: an "Audit" runtime card on the board', status='not built', latest='no check yet', result='no check'))
TARGETS = {   # one target per check, with its source; approved by Jason, 2026-11-22
    **{f'R{n:02d}': ('0 failures in the files it reads', 'site-audit/rules.md (its rule); decisions/0145 §6 (the zip check blocks the "yes" rules)') for n in range(1, 16)},
    'RENDER': ('every page answers, with no console error, no failed request, no sideways scroll at 390 and card edges at 3px, as each account; the accessibility basics at WCAG 2.2 AA',
               'decisions/0145 §1; CLAUDE.md "Phone layout and visual standard"; decisions/0058 §11; W3C WCAG 2.2, level AA'),
    'PATHS': ('every path reaches its goal: 3 taps or fewer from home to a place to eat and to a town\'s guide, 5 or fewer for the rest', 'site-audit/punch-list.md A6 (decided 2026-11-08); rules.md J01'),
    'SEARCH': ('every probe case passes; typing suggestions answer within 300 ms', 'the search probe\'s own bar (site-audit/search-scorecard.md); decisions/0088 ("never wait")'),
    'LAYOUT': ('no gap between the top bar and the hero; nothing drawn over a card\'s action icons', 'decisions/0058 §21'),
    'PHOTOS': ('a real stock and member photo answer 200 as an image; every kind reaches media.php', 'decisions/0123; the 2026-11-15 404'),
    'BOARD': ('its process working, with no open trip', 'decisions/0171, 0175'),
    'TOOLS': ('every tool does what site-audit/tools.md says, for every account that may use it, and is refused with the reason for every account that may not', 'decisions/0145 §1 ("Function and tool checks")'),
    'LOAD': ('Core Web Vitals "good": LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms, each at the 75th percentile', 'Google\'s Core Web Vitals thresholds; the board\'s "Pages load fast" (about 2.5 s, des)'),
    'PHOTOS-SHOWN': ('every image on the page loaded and drawn (decoded, wider than 0) and with a text alternative', 'Jason, 2026-11-22 ("the photo loads and display"); W3C WCAG 2.2 SC 1.1.1 Non-text Content'),
    'BRAND': ('the readable standards of brand.md met: its tokens, one header per view, filters as one dropdown, card anatomy, icons; text contrast at least 4.5:1, focus visible',
              'brand.md §4; design/css-theme-brand.md; CLAUDE.md; W3C WCAG 2.2 SC 1.4.3 Contrast (Minimum) and 2.4.7 Focus Visible'),
    'AUDIT': ('a light run asks at most about 20 requests, one at a time with a 1 s gap; the probe pages\' response times stay within 20% of their 7-day median, or the next run backs off',
              'Jason, 2026-11-22 (the schedule below); the 20% is ours, approved with the schedule'),
}
RUNS = {   # Jason, 2026-11-22: "what would be an acceptable time, and wont bog our platform or resources down creating slowness issues"
    **{f'R{n:02d}': '1. on every zip and after every sync, on GitHub (no load on the site)' for n in range(1, 16)},
    'RENDER': '3. daily at 09:40 UTC (2:40 AM Arizona), and on staging with every release',
    'PATHS': '3. daily after RENDER, and on staging with every release; changing paths on staging only (4)',
    'SEARCH': '2. one search in the hourly light probe; the full probe daily with RENDER (3)',
    'LAYOUT': '3. daily with RENDER',
    'PHOTOS': '2. one photo in the hourly light probe; the full check daily (3)',
    'BOARD': 'the board\'s own watch, every 10 minutes (the operational clock; not the audit\'s)',
    'TOOLS': '3. read-only tools daily; 4. tools that change anything on staging only, per release',
    'LOAD': '2. key pages in the hourly light probe; 3. every page daily; 4. heavy speed tests with EXPLAIN on staging only, per release',
    'PHOTOS-SHOWN': '3. daily with RENDER',
    'BRAND': '3. daily with RENDER',
    'AUDIT': 'every run reports its own load',
}
for c in CHECKS:
    c['target'], c['target_source'] = TARGETS[c['id']]
    c['target_status'] = 'ours, approved with the schedule (Jason, 2026-11-22)' if c['id'] == 'AUDIT' else 'approved (Jason, 2026-11-22)'
    c['runs'] = RUNS[c['id']]
SCHEDULE = [
    ('1. Code checks', 'R01–R15', 'on every zip and after every sync', 'none to the site (reads the mirror and the zip)', 'a few minutes', 'GitHub\'s runners'),
    ('2. Light live probes', 'LOAD (key pages), SEARCH (one search), the sign-in page, PHOTOS (one photo), the payment page\'s answer', 'every hour',
     'about 20, read-only, signed out, one at a time with a 1 s gap', 'about 40 seconds', 'GitHub\'s runners, against the live site'),
    ('3. The full rendered check and the paths', 'RENDER (412 views), PATHS (25), LAYOUT, PHOTOS-SHOWN, BRAND, read-only TOOLS, the full SEARCH probe', 'daily at 09:40 UTC (2:40 AM Arizona), as today; and on staging with every release',
     'about 412 page views and the paths\' steps, a page at a time', 'the paths\' last run took 192 s of a 420 s budget; the rendered check runs inside the job\'s 120 minutes', 'GitHub\'s runners'),
    ('4. Function checks that change anything, and heavy speed tests with EXPLAIN', 'TOOLS (those that change things), LOAD\'s query plans', 'per release', 'on staging only, never on live (live: read-only checks and the test accounts\' own records)', 'per release', 'staging.trvgo.us'),
    ('5. Run checks now', 'only that gauge\'s checks', 'on a press: one run at a time, at most once per 10 minutes per gauge', 'the gauge\'s checks only', 'minutes', 'GitHub\'s runners (as 2 and 3)'),
]

BY = {c['id']: c for c in CHECKS}
CODE_IDS = [f'R{n:02d}' for n in range(1, 16)]

RESULTS_FORMAT = {
    'note': 'One results format for every check, extending site-audit/results/findings.json (version, generated_at, findings[]). Each result is one row.',
    'fields': {'check': 'the check ID in the register (R01, RENDER, PATHS, LOAD…)', 'item': 'what it checked: a route, a path id, an endpoint, a workflow or a service',
               'who': 'the account it ran as, where it matters', 'target': 'the target, as the register states it at the time',
               'result': 'pass | fail | partial | no check', 'measured': 'the measured value (a time, a count, a status)', 'at': 'when (ISO 8601, UTC)',
               'evidence': 'a link to the run, the screenshot or the record', 'finding': 'the f-… id when it fails (as findings.json has today)'},
    'example': {'check': 'LOAD', 'item': '/directory/', 'who': 'guest', 'target': '2.5 s, 99.5% of the time', 'result': 'no check', 'measured': None,
                'at': None, 'evidence': None, 'finding': None},
}


def write():
    L = ['# The check register: every check the gauges read (a spec for Jason\'s review)', '',
         'The session manager\'s proposal to Jason (2026-11-22): the page audit becomes the diagnostics tool the gauges use. Every check is one',
         'entry here, with its scope, measure, target, schedule and evidence. Generated by `mockups/src/checkreg.py` from `site-audit/rules.md`,',
         '`scorecard.md`, the results in `site-audit/results/` and the workflows (`site-audit.yml`, `site-checks.yml`, `search-probe.yml`);',
         'also as `check-register.json`. A spec: nothing here is built.', '',
         f'**{len(CHECKS)} checks: {sum(1 for c in CHECKS if c["status"] == "built")} existing, {sum(1 for c in CHECKS if c["status"] != "built")} proposed** (the four gaps Jason\'s question found).', '',
         '| ID | Check | Source rule | Scope | Measure | When it runs | Latest | Evidence | Status |', '|---|---|---|---|---|---|---|---|---|']
    for c in CHECKS:
        L.append(f'| **{c["id"]}** | {c["name"]} | {c["source"]} | {c["scope"]} | {c["measure"]} | {c["runs"]} | {c["latest"]} | {c["evidence"]} | {c["status"]} |'.replace('\n', ' '))
    L += ['', '## Targets: one per check, with its source (approved, Jason, 2026-11-22)', '',
          'Each check has one target. Where a published standard fits, it\'s cited by name (Google\'s Core Web Vitals; W3C WCAG 2.2);',
          'otherwise the decision line it comes from. **Jason approved every target as proposed (2026-11-22).** The AUDIT row\'s 20% is ours,',
          'approved with the schedule.', '',
          '| Check | Target | Source | Status |', '|---|---|---|---|']
    L += ['| R01–R15 | 0 failures in the files each reads | site-audit/rules.md (each rule); decisions/0145 §6 | approved (Jason, 2026-11-22) |']
    L += [f'| {c["id"]} | {c["target"]} | {c["target_source"]} | {c["target_status"]} |' for c in CHECKS if not re.match(r'R\d\d$', c['id'])]
    L += ['', '## How often, and what each run asks of the site (approved, Jason, 2026-11-22)', '',
          'Jason (2026-11-22): "what would be an acceptable time, and wont bog our platform or resources down creating slowness issues".', '',
          '| Run | Checks | When | Requests to the site | Time | Where |', '|---|---|---|---|---|---|']
    L += [f'| {a} | {b} | {c} | {d} | {e} | {f} |' for a, b, c, d, e, f in SCHEDULE]
    L += ['', '**Load measured:** each run\'s requests and time show on the audit\'s own card (the **AUDIT** row above; a proposed "Audit"',
          'runtime card on the board). If a run slows the site (the probe pages\' response times more than 20% over their 7-day median), the',
          'next run backs off (an hour later, then two) and says so on that card. Live checks only read; anything that changes data runs on staging.', '',
          '## Notes', '',
          '- **PHOTOS keeps only its exit code:** no result is kept in git (`photos-check.sh` exits 1 and the run fails). Its results go',
          '  into the one results format (build order, 3).',
          '- **A live finding, handed to the Design and trips session (not fixed here):** LAYOUT measures a **5px gap above the hero** on a',
          '  landmark page (`/place/landmark.php?id=900000013`) and on `/discovery/` (`site-audit/layout-probe.md`, 2026-11-21 10:19 UTC),',
          '  against decisions/0058 §21 (no gap). The layout probe doesn\'t feed `findings.py` today, so it isn\'t in `results/findings.json`',
          '  (which is generated, never hand-edited); feeding it there is on the build order (3).', '']
    L += ['', '## The four gaps (proposed checks)', '',
          'Jason asked whether the audit checks "end to end … search on a page and its end points function correctly, the page load speed, the function speed, the photo loads and display … the brand of design standards". Four of those have no check today:', '',
          '1. **TOOLS:** each tool and endpoint by every type of account. `site-audit/tools.md` doesn\'t exist, and 0145 §1\'s function and tool checks aren\'t built.',
          '2. **LOAD:** a load time for every page against a target. The paths check flags only a page over 4 s on its way (`paths.mjs`, `SLOW_MS = 4000`), and the rendered check records only failures.',
          '3. **PHOTOS-SHOWN:** every photo on a page shown. `photos-check.sh` proves the uploads rule, not that a page\'s photos are drawn.',
          '4. **BRAND:** most of `brand.md`. Only R10 (icons), R11 (breadcrumbs) and the rendered check\'s 3px edges are automatic.', '',
          '## One results format', '', RESULTS_FORMAT['note'], '', '| Field | What |', '|---|---|']
    L += [f'| `{k}` | {v} |' for k, v in RESULTS_FORMAT['fields'].items()]
    L += ['', 'Example: `' + json.dumps(RESULTS_FORMAT['example']) + '`', '',
          '## Build order (a spec note)', '', '1. **The register** (this file), its IDs referenced by every gauge criterion.',
          '2. **The four gaps:** TOOLS first (with `site-audit/tools.md`), then LOAD, PHOTOS-SHOWN, BRAND; and the AUDIT card (each run\'s load).',
          '3. **One results format** for every check, extending findings.json: PHOTOS\'s results (today only an exit code) and LAYOUT\'s go into it.',
          '4. **The gauges read the register**, with **Run checks now** in each gauge\'s drawer.', '',
          '## Site health: one notification path (decided, Jason, 2026-11-22)', '',
          'Jason asked: "Failures = notifications, not sure if this is a replacement or side-by-side". **He chose one path, as recommended.**',
          '- Each failure goes through the dispatcher once (decisions/0172, 0176). A **build regression** is a Pulse item with no push. A',
          '  **runtime failure** is an alarm, with a push only for a trip or a critical part.',
          '- **Site health stops sending its own notices.** Its list stays as the audit\'s view, reached from a gauge\'s drill-down.',
          '- **Rejected alternative:** side by side for a trial week.',
          '- The audit stays the scheduled diagnostic: a failing check never trips anything on its own (the two clocks, decisions/0175).', '',
          '## Decided (Jason, 2026-11-22)', '', '1. **Targets:** approved as proposed, each with its source (the AUDIT row\'s 20% is ours, approved with the schedule).',
          '2. **The schedule:** approved as proposed.', '3. **Site health:** one notification path.']
    open(OUT + 'check-register.md', 'w').write('\n'.join(L) + '\n')
    json.dump(dict(checks=CHECKS, results_format=RESULTS_FORMAT), open(OUT + 'check-register.json', 'w'), indent=1)


if __name__ == '__main__':
    write()
    print(len(CHECKS), sum(1 for c in CHECKS if c['status'] == 'built'))
    for c in CHECKS:
        if not re.match(r'R\d\d$', c['id']):
            print(c['id'], '|', c['target'][:80], '|', c['target_source'][:80])
