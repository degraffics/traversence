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
         '| ID | Check | Source rule | Scope | Measure | Target | When it runs | Latest | Evidence | Status |', '|---|---|---|---|---|---|---|---|---|---|']
    for c in CHECKS:
        L.append(f'| **{c["id"]}** | {c["name"]} | {c["source"]} | {c["scope"]} | {c["measure"]} | {c["target"]} | {c["runs"]} | {c["latest"]} | {c["evidence"]} | {c["status"]} |'.replace('\n', ' '))
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
          '2. **The four gaps:** TOOLS (with `site-audit/tools.md`), LOAD, PHOTOS-SHOWN, BRAND.', '3. **One results format** for every check, extending findings.json.',
          '4. **The gauges read the register**, with **Run checks now** in each gauge\'s drawer.', '',
          '## Open questions (Jason\'s calls)', '', '1. **Targets:** his own, or the proposed target per check?', '2. **How often** each check runs.',
          '3. **Site health:** should it go through the one alarm engine (a check that fails is a sensor, with Fix it), replacing the separate Pulse case?']
    open(OUT + 'check-register.md', 'w').write('\n'.join(L) + '\n')
    json.dump(dict(checks=CHECKS, results_format=RESULTS_FORMAT), open(OUT + 'check-register.json', 'w'), indent=1)


if __name__ == '__main__':
    write()
    print(len(CHECKS), sum(1 for c in CHECKS if c['status'] == 'built'))
