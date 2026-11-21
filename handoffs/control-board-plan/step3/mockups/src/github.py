# GitHub's card (step 3), built with the same kit as Railway's. Static mockups; not site code.
from pages import *  # Railway pages are generated on import; PAGES collects every page

GH_BILL = 'https://github.com/settings/billing'
SYNC_RUNS = 'https://github.com/degraffics/traverse-3way-sync/actions'


def gh_slots(fix=None):
    return ('<div class="slots">' + slot('billing', 'GitHub', 'Billing', GH_BILL, fix == 'billing')
            + slot('keys', 'GitHub', fix=fix == 'keys') + slot('console', 'GitHub', fix=fix == 'console')
            + slot('login', 'GitHub', fix=fix == 'login') + '</div>')


GH_RADIUS = """<details><summary>If GitHub itself is down: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Releases: builds wait in the inbox; release records aren't written</li><li>The sync, and the mirror and OneDrive it keeps</li><li>New Railway deploys (Railway builds from <code>degraffics/traversence</code> <code>main</code>)</li></ul></div>
<div class="o"><b>On a backup</b>none</div>
<div class="u"><b>Unaffected</b><ul><li>Bluehost: pages, search, sign-in, payments, the databases</li><li>Gathering: the crawler, W0 Indexer and the video worker keep running on their current versions</li><li>Everything already stored</li></ul></div></div></details>"""

STAGE_GH = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Check for builds · '
            'Run the sync · Take out (stage 6) · Deactivate (stage 7)</span>')
STAGE_GH_TRIP = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · '
                 'Acknowledge · Take out (stage 6)</span>')
SPEND_GH = (f'<span class="spend"><span>Actions minutes: within the free allowance on the current plan</span>'
            f'<b>use not read yet</b>'
            f'<a href="{GH_BILL}" target="_blank" rel="noopener">Billing ↗</a></span>')


def gh_zone1(state_light, stage=STAGE_GH):
    return zone(1, 'Identity and role', f"""
<div class="idl"><a class="name" href="#">GitHub</a>
<span class="badge role">Host</span><span class="badge crit">Critical: procedures (releases and the sync)</span>
{state_light}</div>
<div class="stage">{stage}</div>
<div class="stage">{SPEND_GH}</div>{GH_RADIUS}""")


# What it runs (real runs, read from GitHub Actions, Nov 21 9:30 AM MST)
RUNS = [
    dict(k='sync', name='The sync', where='<code>sync.yml</code> on <code>degraffics/traverse-3way-sync</code>',
         sched='06:00 and 18:00 UTC (11:00 PM and 11:00 AM MST), or on demand; one at a time',
         feeds='the live mirror (<code>sync-storage/</code>, <code>sync-app/</code>, <code>sync-trvgo/</code>) and OneDrive',
         last='Run #691 · scheduled · success · Nov 21, 7:25 AM MST (61 s). Before it: #690 by hand, 5:24 AM MST, success; #689 by hand, Nov 20, 8:17 PM MST, success',
         runs=f'<a href="{SYNC_RUNS}" target="_blank" rel="noopener">Its runs ↗</a>', nxt='Next slot: 18:00 UTC (11:00 AM MST)'),
    dict(k='ping', name='Release ping', where='<code>release-ping.yml</code> on <code>degraffics/traversence</code>',
         sched='when a build lands in <code>releases/inbox/</code>', feeds='the site\'s release look (it takes the build within a minute)',
         last='Run #370 · push · success · Nov 21, 5:22 AM MST: “Release #247: workflow-holds.zip taken from the inbox onto staging”',
         runs='<a href="https://github.com/degraffics/traversence/actions/workflows/release-ping.yml" target="_blank" rel="noopener">Its runs ↗</a>', nxt=''),
    dict(k='look', name='The release look', where='the site\'s 10-minute job (not an Action; it reads the inbox with <code>GITHUB_DOCS_TOKEN</code>)',
         sched='every 10 minutes', feeds='releases onto staging, then live',
         last='Last looks: 3:00 AM MST, the 10-minute job: <b>no answer</b> (<code>found: -1</code>) · Nov 20, 8:17 PM MST, the ping from git: found 0 · Nov 20, 11:28 AM MST, Check for builds: found 0',
         runs='', nxt='Release #247 went live at 5:22 AM MST, so later looks answered'),
    dict(k='audit', name='Site audit and the delivery log', where='<code>site-audit.yml</code>',
         sched='twice a day, or asked by the site', feeds='the delivery log, Site health\'s findings',
         last='Run #49 · scheduled · success · Nov 21, 9:23 AM MST',
         runs='<a href="https://github.com/degraffics/traversence/actions/workflows/site-audit.yml" target="_blank" rel="noopener">Its runs ↗</a>', nxt=''),
    dict(k='checks', name='Site checks (browser)', where='<code>site-checks.yml</code>',
         sched='daily, or asked by the site', feeds='the rendered pages and paths report',
         last='Run #33 · asked by the site · success · Nov 21, 2:17 AM MST (26 min)',
         runs='<a href="https://github.com/degraffics/traversence/actions/workflows/site-checks.yml" target="_blank" rel="noopener">Its runs ↗</a>', nxt=''),
    dict(k='probe', name='Search probe and health report', where='<code>search-probe.yml</code>',
         sched='daily, or asked by the site', feeds='the health report, <code>site-audit/results/alarms.json</code>, the search and layout probes',
         last='Run #63 · asked by the site · success · Nov 21, 3:17 AM MST (4 min)',
         runs='<a href="https://github.com/degraffics/traversence/actions/workflows/search-probe.yml" target="_blank" rel="noopener">Its runs ↗</a>', nxt=''),
]


def gh_runs(over=None):
    over = over or {}
    out = ''
    for r in RUNS:
        o = over.get(r['k'], {})
        cls = 'row' + (' ' + o['cls'] if o.get('cls') else '')
        st = o.get('state', light('g', 'On · Working'))
        out += f"""<div class="{cls}"><div><div class="nm">{r['name']}</div><div class="sub">{r['where']}</div>{st}</div>
<div><div>Feeds <b>{r['feeds']}</b></div><div class="sub">Runs: {r['sched']}</div><div class="sub">{o.get('last', r['last'])}</div>
<div class="sub">{r['nxt']}{' · ' if r['nxt'] and r['runs'] else ''}{r['runs']}</div>{o.get('more', '')}</div>
<div class="btns">{o.get('btn', '')}</div></div>"""
    repos = ('<div class="sub" style="margin-top:.5rem">It holds <code>degraffics/traversence</code> (decisions, hand-offs, <code>releases/inbox</code>, '
             'the delivery log, the workers\' code: Railway deploys from its <code>main</code>) and <code>degraffics/traverse-3way-sync</code> (the sync and the live mirror).</div>')
    return '<div class="rows">' + out + '</div>' + repos


TOKENS = [('GITHUB_DOCS_TOKEN', '.env: reads the inbox, writes release records, asks for the checks'),
          ('GITHUB_SYNC_TOKEN', '.env: watches the sync'),
          ('HEALTH_TOKEN', 'a GitHub secret and its .env copy, which must match: the daily health report')]


def gh_sensors(tok_over=None, extra=None):
    tok_over = tok_over or {}
    extra = extra or {}
    h = ''
    for k, where in TOKENS:
        d = tok_over.get(k)
        h += d or (f'<div class="row"><div><div class="nm"><code>{k}</code></div><div class="sub">{where}</div></div>'
                   f'<div>Reads its repository: <b>not read yet</b> · expiry: <b>not read yet</b><div class="sub">Checked daily; amber 14 days before it expires.</div></div><div class="btns"></div></div>')
    rows = [
        ('Release look', 'each look and what it found; a look GitHub didn\'t answer is a fault (spec)', extra.get('look', 'Last: 3:00 AM MST, <b>no answer</b> (<code>found: -1</code>). Later looks answered (release #247 went live 5:22 AM MST).')),
        ('Release job quiet', 'no look in 30 minutes (built)', extra.get('quiet', '<b>not read yet</b>')),
        ('The sync\'s runs', 'failed, stuck over 15 min, none in 14 h; a slot missed an hour after it', extra.get('sync', 'Last: #691 success, 7:25 AM MST. The 06:00 UTC slot: see the logbook (it ran late or was dropped; #690 by hand caught up at 5:24 AM MST).')),
        ('Other Actions', 'the last run of each (spec)', extra.get('other', 'Site audit #49 success · Site checks #33 success · Search probe #63 success · Release ping #370 success')),
    ]
    s = ''
    for n, what, val in rows:
        cls = 'row' + (' ' + extra[n + '_cls'] if extra.get(n + '_cls') else '')
        s += f'<div class="{cls}"><div class="nm">{n}</div><div><div class="sub">{what}</div><div>{val}</div></div><div class="btns"></div></div>'
    h += ('<div class="row"><div><div class="nm">Planned</div><div class="sub">GitHub\'s manifest, a required key once built</div></div>'
          '<div>' + light('n', 'Not added yet') + '<div>Planned: a read-only token for Actions minutes (not added yet)</div>'
          '<div class="sub">A separate fine-grained token with only the billing or plan read it needs, entered later through Its key, never in chat.</div></div><div class="btns"></div></div>')
    return (f'<div class="rows">{s}</div><h3 style="font:600 13px var(--sans);margin:.8rem 0 .2rem">Its tokens (names only, never values)</h3>'
            f'<div class="rows">{h}</div><div class="sub" style="margin-top:.4rem">The sync\'s own secrets on GitHub (OneDrive, FTP, its token): names only, not read here.</div>')


GH_Z4 = act_rows(b('Check now') + b('Check for builds') + b('Run the sync') + '<span class="why">Run the sync lists the runs first, never two at once</span>',
                 'GitHub', gh_slots(), then='After Billing → <b>Check now</b>. Only the board\'s check changes state.')


def gh_card(state_light, thread_html=None, runs=None, sens=None, toks=None, fault=None, stage=STAGE_GH):
    z1 = gh_zone1(state_light, stage)
    z2 = zone(2, 'Capabilities and feeds: what it runs', gh_runs(runs))
    z3 = zone(3, 'Sensors and key readiness', gh_sensors(toks, sens))
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + z2 + z3
    else:
        body = z1 + z2 + z3 + zone(4, 'Actions and Fix it', GH_Z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="GitHub">{body}</article>'


UP = '<span class="why">its actions are in the thread above ↑</span>'
HELD_GH = ('<ul><li>Releases: builds wait in the inbox; release records aren\'t written</li><li>The sync, and the mirror and OneDrive it keeps</li>'
           '<li>New Railway deploys (they build from GitHub)</li></ul>')
UNAFF_GH = ('<ul><li>Bluehost: pages, search, sign-in, payments, the databases</li><li>Gathering: the crawler, W0 Indexer, the video worker, on their current versions</li>'
            '<li>Everything already stored</li></ul>')


# ---- (c1) GitHub's API not answering: row 57, critical ----
def card_api_down(t='9:40 AM MST', ack=False):
    st = light('r', 'Paused by the system (releases and the sync)') + f': still trying, {sc("1 of 3")}, next at {sc("9:50 AM MST")}. Since {sc(t)}; releases wait in the inbox.'
    if ack:
        st += ' <span class="why">Seen by Jason. The light stays red while it\'s down.</span>'
    th = thread('GitHub', st, HELD_GH, 'none', UNAFF_GH,
                f'A critical part trips at once (procedures: releases and the sync) and the system keeps trying at each 10-minute look. Next look {sc("9:50 AM MST")}. '
                'It closes as Recovered when a look succeeds.',
                b('Check and start again', True) + ('' if ack else b('Acknowledge')) + b('Check for builds'),
                gh_slots(fix='console'),
                'Fix it: <b>GitHub\'s console page</b>. No console page registered for GitHub yet, so Fix it says so and never borrows Billing. '
                'Then <b>Check and start again</b> runs a look.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-#### (critical: no warning first)</dd><dt>Evidence</dt><dd>the release look got no answer from GitHub\'s API</dd>'
                '<dt>Told</dt><dd>the Founder at once, in quiet hours too: Pulse (Critical), email, push</dd><dt>Rule</dt><dd>02 §4 row 57; §6 “GitHub down”; manifest §2</dd></dl>',
                at='GitHub')
    runs = {'look': {'state': light('r', 'Paused by the system: no answer'), 'cls': 'hr', 'btn': UP}, 'sync': {'state': light('h', 'Held (waiting on GitHub)'), 'cls': 'hl'},
            'ping': {'state': light('h', 'Held (waiting on GitHub)'), 'cls': 'hl'}}
    return gh_card(light('r', 'On · Paused by the system (releases and the sync)'), th, runs=runs, fault='r', stage=STAGE_GH_TRIP,
                   sens={'look': f'<b>No answer</b> at {sc("9:40 AM MST")} (and each look since)', 'Release look_cls': 'hr'})


# ---- (c2) a token expiring, then refused: rows 58, 59 ----
def card_token(refused=False):
    if not refused:
        th = thread(f'GITHUB_SYNC_TOKEN expires {sc("Dec 3, 2026")} (in {sc("12 days")})',
                    light('a', 'Warning') + ' · nothing is held yet; it works until then.',
                    'nothing', 'none', '<ul><li>Everything: the token still reads its repository</li></ul>',
                    'Checks each token daily; amber from 14 days before it expires.',
                    b('Replace key', True) + b('Check now'),
                    gh_slots(fix='keys'),
                    'Fix it: <b>GitHub\'s API keys page</b>. No API keys page registered for GitHub yet, so Fix it says so and never borrows Billing. '
                    'Then <b>Replace key</b> and its Check.',
                    '<dl class="dl"><dt>Alarm</dt><dd>W-#### · Warning · no Acknowledge (R80)</dd><dt>Rule</dt><dd>02 §4 row 58</dd></dl>', at='GitHub')
        tok = {'GITHUB_SYNC_TOKEN': f'<div class="row hl"><div><div class="nm"><code>GITHUB_SYNC_TOKEN</code></div><div class="sub">.env: watches the sync</div>{light("a", "Warning")}</div>'
                                    f'<div>Reads its repository: yes · expires {sc("Dec 3, 2026")}</div><div class="btns">{UP}</div></div>'}
        return gh_card(light('a', 'On · Warning (the system is trying)'), th, toks=tok, fault='a')
    th = thread('GitHub refused GITHUB_DOCS_TOKEN',
                light('r', 'Paused by the system') + ': releases wait.' + f' Since {sc("9:12 AM MST")}.',
                '<ul><li>What <code>GITHUB_DOCS_TOKEN</code> does: the release look (builds wait in the inbox), release records, asking for the checks</li></ul>', 'none',
                '<ul><li>The sync (its own token)</li><li>The health report (<code>HEALTH_TOKEN</code>)</li><li>Railway\'s deploys, the site, gathering</li></ul>',
                'A refused key gets no tries: it waits for a person (row 59).',
                b('Replace key', True) + b('Acknowledge'),
                gh_slots(fix='keys'),
                'Fix it: <b>GitHub\'s API keys page</b>. No API keys page registered for GitHub yet, so Fix it says so and never borrows Billing. '
                'Then <b>Replace key</b>, whose check reads the repository with the new token, past its own hold.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Told</dt><dd>the Founder: Pulse, email, push</dd><dt>Rule</dt><dd>02 §4 rows 59, 106, 129</dd></dl>', at='GitHub')
    tok = {'GITHUB_DOCS_TOKEN': f'<div class="row hr"><div><div class="nm"><code>GITHUB_DOCS_TOKEN</code></div><div class="sub">.env: reads the inbox, writes release records</div>{light("r", "Paused by the system")}</div>'
                                f'<div>Refused (401) at {sc("9:12 AM MST")}</div><div class="btns">{UP}</div></div>'}
    runs = {'look': {'state': light('h', 'Held (waiting on its token)'), 'cls': 'hl'}}
    return gh_card(light('r', 'On · Paused by the system: GITHUB_DOCS_TOKEN'), th, runs=runs, toks=tok, fault='r', stage=STAGE_GH_TRIP)


# ---- (c3) the sync stuck over 15 min: row 60 ----
def card_sync_stuck():
    th = thread(f'The sync\'s run {sc("#692")} is stuck',
                light('a', 'Warning (the system is trying)') + f' · started {sc("11:04 AM MST")}, its job finished, the run still shows running after 15 min. Trying again (1 of 3).',
                'nothing yet (a trip would hold the sync, and the mirror and OneDrive it keeps)', 'none',
                '<ul><li>Releases: they publish on their own</li><li>The site, gathering</li></ul>',
                'One sync at a time: it never starts another while this one shows running. At its third try it trips and holds the sync.',
                b('Check now', True) + b('Cancel the run'),
                gh_slots(),
                f'Fix it: <b>the run\'s page</b>, from the alarm\'s evidence (<a href="{SYNC_RUNS}" target="_blank" rel="noopener">Its runs ↗</a>, the run by its id), with Cancel. '
                'GitHub\'s console slot: No console page registered for GitHub yet.',
                '<dl class="dl"><dt>Alarm</dt><dd>W-####</dd><dt>Rule</dt><dd>02 §4 row 60; manifest §2 “The sync failed, stuck or stalled”; CLAUDE.md “One sync at a time”</dd></dl>', at='GitHub')
    runs = {'sync': {'state': light('a', 'On · Warning: stuck'), 'cls': 'hl', 'btn': UP,
                     'last': f'Run {sc("#692")} · scheduled · started {sc("11:04 AM MST")} · <b>still running after its job finished</b>. Before it: #691 success, 7:25 AM MST'}}
    return gh_card(light('a', 'On · Warning (the system is trying)'), th, runs=runs, fault='a',
                   sens={'sync': f'<b>Run {sc("#692")} stuck</b> (job finished, run running over 15 min)', "The sync's runs_cls": 'hl'})


# ---- (c4) the real missed 06:00 UTC sync: row 61, warning, nothing held ----
def card_sync_missed():
    th = thread('The 06:00 UTC sync didn\'t run; the mirror is behind since 03:19 UTC.',
                light('a', 'Warning') + ' · raised 12:00 AM MST (07:00 UTC), an hour past the slot (<i>spec</i>: not raised today).',
                'nothing', 'none',
                '<ul><li>Releases (#247 went live at 5:22 AM MST on its own)</li><li>The site, gathering</li></ul>'
                '<div class="why"><b>Late:</b> the live mirror and OneDrive, behind since 03:19 UTC (Nov 20, 8:19 PM MST).</div>',
                'Nothing is held: the next run catches up. It trips only if no run comes for 14 hours.',
                b('Run the sync', True) + '<span class="why">it lists the runs first</span>' + b('Check now'),
                gh_slots(),
                f'Fix it: <b>the sync\'s runs</b> (<a href="{SYNC_RUNS}" target="_blank" rel="noopener">Its runs ↗</a>), on the Sync part. GitHub\'s console slot: No console page registered for GitHub yet.',
                '<dl class="dl"><dt>Record</dt><dd>W-sync-0621 (the manifest\'s worked record)</dd>'
                '<dt>Timeline (real)</dt><dd>#689 by hand Nov 20, 8:17 PM MST (03:17 UTC) · 06:00 UTC slot: no run · #690 by hand 5:24 AM MST (12:24 UTC), success · '
                '#691 “schedule” 7:25 AM MST (14:25 UTC), success. GitHub doesn\'t say which slot #691 was; the yesterday-18:00 slot ran at 4:48 PM MST (23:48 UTC), about six hours late.</dd>'
                '<dt>Root cause</dt><dd>GitHub\'s own scheduler starts scheduled runs late or drops them at busy times</dd><dt>Rule</dt><dd>02 §4 row 61; manifest §2 worked record</dd></dl>', at='GitHub')
    closed = (f'<div class="need" style="margin-top:.6rem"><div class="l1">{light("g", "Cleared")}<span class="t">The 06:00 UTC sync didn\'t run</span></div>'
              '<div>Cleared: the sync\'s run #690 finished well (5:26 AM MST).</div></div>')
    runs = {'sync': {'state': light('a', 'On · Warning: a slot missed'), 'cls': 'hl', 'btn': UP,
                     'last': 'At 3:19 AM MST: last run #689, Nov 20, 8:17 PM MST (by hand). The 06:00 UTC slot passed with no run.'}}
    return gh_card(light('a', 'On · Warning'), th + closed, runs=runs, fault='a',
                   sens={'sync': '<b>06:00 UTC slot: no run.</b> Last: #689 at Nov 20, 8:17 PM MST.', "The sync's runs_cls": 'hl'})


# ---- (c5) a failed check run ----
def card_check_failed():
    th = thread(f'Site checks (browser) failed: run {sc("#34")}',
                light('a', 'Warning') + f' · failed at {sc("2:31 AM MST")}. Its next run is its check.',
                'nothing: that report waits', 'none', '<ul><li>Everything: releases, the sync, the site</li></ul>',
                'Nothing is held. Its next run (daily, or when the site asks) is its check (row 16\'s rule).',
                b('It\'s fixed: try it again', True),
                gh_slots(),
                'Fix it: <b>its run\'s log</b>, from the alarm\'s evidence (<a href="https://github.com/degraffics/traversence/actions/workflows/site-checks.yml" target="_blank" rel="noopener">Its runs ↗</a>). '
                'GitHub\'s console slot: No console page registered for GitHub yet.',
                '<dl class="dl"><dt>Real last runs</dt><dd>Site checks #33 success (2:17 AM MST, 26 min) · Site audit #49 success · Search probe #63 success: no check run failed today</dd>'
                '<dt>Rule</dt><dd>manifest §2 “A check run failed”; 02 rows 16, 62, 110</dd></dl>', at='GitHub')
    runs = {'checks': {'state': light('a', 'On · Warning: its run failed'), 'cls': 'hl', 'btn': UP,
                       'last': f'Run {sc("#34")} · failed · {sc("2:31 AM MST")}. Before it: #33 success, Nov 21, 2:17 AM MST'}}
    return gh_card(light('a', 'On · Warning'), th, runs=runs, fault='a')


# ---- (c6) the release look failing: real found -1 at 10:00 UTC, as the spec scenario ----
def card_look_failed():
    th = thread('GitHub',
                light('r', 'Paused by the system (releases and the sync)') + f': still trying, {sc("1 of 3")}, next at {sc("3:10 AM MST")}. Since 3:00 AM MST; releases wait in the inbox.',
                '<ul><li>Releases: builds wait in the inbox</li></ul>', 'none', '<ul><li>Everything else: the sync, the site, gathering, Railway\'s workers</li></ul>',
                'GitHub is critical (procedures), so it\'s a trip at once while the system tries again at each 10-minute look. It closes as Recovered when a look succeeds.',
                b('Check and start again', True) + b('Acknowledge') + b('Check for builds'),
                gh_slots(fix='console'),
                'Fix it: <b>GitHub\'s console page</b>. No console page registered for GitHub yet, so Fix it says so and never borrows Billing.',
                '<dl class="dl"><dt>What really happened</dt><dd>At 3:00 AM MST (10:00 UTC) the site\'s 10-minute look got no answer, recorded <code>found: -1</code>. '
                '<b>Nothing was raised</b>: no alarm, no Pulse item, no message. Later looks went on; release #247 went live at 5:22 AM MST.</dd>'
                '<dt>Ids</dt><dd>T-#### · IR-#### (as the contract would assign them)</dd><dt>Rule</dt><dd>02 row 57; manifest §2 “Spec scenario: not an observed trip”</dd></dl>', at='GitHub')
    banner = ('<div class="note" style="border-color:var(--amber)"><b>Not an observed trip.</b> The <code>found: -1</code> at 10:00 UTC is real; the trip drawn below '
              'is what the contract would do with it (a spec scenario for step 4\'s test). Live, nothing was raised.</div>')
    runs = {'look': {'state': light('r', 'Paused by the system: no answer'), 'cls': 'hr', 'btn': UP}}
    return banner + gh_card(light('r', 'On · Paused by the system (releases and the sync)'), th, runs=runs, fault='r', stage=STAGE_GH_TRIP,
                            sens={'look': '<b>No answer</b> at 3:00 AM MST (<code>found: -1</code>)', 'Release look_cls': 'hr'})


PROV_GH = [
    'GitHub Actions, read-only (2026-11-21, about 9:30 AM MST): <code>sync.yml</code> runs #682–#691 on degraffics/traverse-3way-sync; '
    'Release ping #367–#370, Site audit #46–#49, Site checks (browser) #30–#33 and Search probe #60–#63 on degraffics/traversence; their events, times and results.',
    'The release looks: <code>site-audit/results/alarms.json</code> (10:19 UTC), <code>releases_looked</code>.',
    'Plan, links and keys: the live <code>service-defs.php</code> (\'github\', mirror <code>aeb5f31</code>). Billing → Billing slot, Documented · release #212 '
    '(release #212 carried <code>app/lib/service-defs.php</code>, live Nov 19, 11:25 PM UTC; the mirror\'s earliest copy, <code>86c278d</code>, already has the Billing link). '
    '“The sync” isn\'t a portal slot (Jason\'s ruling): it\'s the Sync part\'s “Its runs”.',
    'Criticality: <code>ControlRegister</code> <code>\'svc:github\' => \'procedures\'</code>. Radius and fix routes: <code>manifests-railway-github.md</code> §2.',
    'Words: 02-state-model.md rows 57–61, §6, §7. Times are shown in MST (Arizona), labelled; UTC where the record is in UTC.',
]


# ---- (a) the board's first view with GitHub ----
def gh_board():
    gh_tile = f"""<div class="host"><div class="hn"><a href="#" data-open-panel="p-github">GitHub</a><span class="badge role">Host</span><span class="badge crit">Critical: procedures (releases and the sync)</span></div>
<div>{light('a', 'On · Warning')}: The 06:00 UTC sync didn't run; the mirror is behind since 03:19 UTC.</div>
<ul><li>The sync → the live mirror and OneDrive · last #689, Nov 20, 8:17 PM MST</li><li>Release ping and the release look → releases · last look 3:00 AM MST: no answer (<code>found: -1</code>)</li>
<li>Site audit, site checks, search probe → reports</li><li>Railway deploys from its <code>main</code></li></ul>
<div class="rad" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)"><div class="h"><b>If it goes down, Held</b>releases (builds wait in the inbox), the sync and the mirror, new Railway deploys</div>
<div class="u"><b>Unaffected</b>Bluehost's pages, search, sign-in, payments and databases; gathering on current versions; everything stored</div></div>
<div class="btns" style="margin-top:.4rem"><button class="btn f" data-open-panel="p-github">Open GitHub's card</button></div></div>"""
    top = f"""<section class="top r"><div><div class="q">Is it healthy</div><div class="a">{light('r', '1 trip')} · <span class="cnt a">2 warnings</span> · <span class="cnt a">2 switches still off</span></div>
<div class="why">The light is red while a trip's hold stands, acknowledged or not.</div></div>
<div><div class="q">What's held and why</div><div class="a">Calls to Common Crawl's index: it isn't answering (IR-####). Nothing in W3 uses it. Nothing on GitHub is held.</div></div>
<div><div class="q">What needs me</div><div class="a">1 trip · 2 switches still off. Listed below, each with its button.</div></div></section>"""
    warn = f"""<details class="grp" open><summary><b>Warnings: the system is handling them</b> <span class="cnt a">2</span></summary>
<div class="in"><div class="row"><div><div class="nm">The 06:00 UTC sync didn't run</div>{light('a', 'Warning')}</div><div class="sub">The mirror is behind since 03:19 UTC. Nothing is held: the next run catches up.</div><div class="btns">{b('Run the sync')}</div></div>
<div class="row"><div><div class="nm">/api/crawl/loads is slow</div>{light('a', 'Warning (Slow)')}</div><div class="sub">It didn't reply to the crawler within 120 s for 500 rows at 1:31 AM MST. The system is checking it.</div><div class="btns">{b('Check now')}</div></div></div></details>"""
    hosts = hosts_html_for_gh(gh_tile)
    body = (intro('<b>(a) GitHub on the board\'s first view, 1280.</b> The same 10:19 UTC (3:19 AM MST) board as Railway\'s page a, with GitHub\'s tile drawn from its real runs: '
                  'at 3:19 AM MST the 06:00 UTC slot had passed with no run (a warning under row 61), and the 3:00 AM MST release look got no answer. '
                  'Folded warnings keep their count; this group is drawn open to show the sync\'s warning.')
            + top + '<div class="sec"><h2>What needs me</h2>' + trip_cc + closed_trip + warn + '</div>'
            + '<div class="sec"><h2>Hosts</h2>' + hosts + '</div>'
            + '<div class="note">GitHub\'s 3:00 AM MST look (<code>found: -1</code>) isn\'t drawn as a trip here: live it raised nothing, and whether the next look answered isn\'t in the snapshot. '
              'Page c6 draws it as the contract\'s scenario, labelled “not an observed trip”.</div>'
            + prov(PROV_GH))
    add('github-a-board.html', 'GitHub on the board', 'The board\'s first view with GitHub among the hosts.', body,
        panel('p-railway', 'Railway', card_working()) + panel('p-github', 'GitHub', card_working_gh()))


def hosts_html_for_gh(gh_tile):
    i = hosts.index('<div class="host"><div class="hn"><a href="#">GitHub</a>')
    return hosts[:i] + gh_tile + '</div>'


def card_working_gh():
    return gh_card(light('g', 'On · Working'))


# ---- (d) GitHub's API down, every surface ----
def gh_story():
    STAT = f'Paused by the system (releases and the sync): still trying, {sc("1 of 3")}, next at {sc("9:50 AM MST")}'
    SUBJ = 'SYSTEM ALARM NOTICE: GitHub: ' + STAT
    HELD = ('Held: releases (builds wait in the inbox), the sync and the mirror, new Railway deploys. '
            'Unaffected: Bluehost\'s pages, search, sign-in, payments and databases; gathering on current versions.')
    REC = f'Recovered: GitHub was down {sc("20 min")}, the system fixed it.'
    hd = '<div class="prow hd"><div>From</div><div>Severity</div><div>Subject</div><div>Received</div></div>'
    steps = f"""<div class="steps">
<div class="step"><h3>1 · The trip, at once (critical), on four surfaces in the same minute</h3><div class="when">{sc('9:40 AM MST')}: the 10-minute release look gets no answer from GitHub's API. GitHub is critical (procedures), so it trips at once and holds releases and the sync, while it keeps trying.</div>
<div class="frame"><div class="fh">Board · What needs me</div><div class="fb"><div class="need"><div class="l1">{light('r', 'Trip')}<span class="t">GitHub</span><span class="why">T-#### · IR-####</span></div>
<div>{STAT}. Since {sc('9:40 AM MST')}.</div><div class="sub">{HELD}</div>
<div class="act"><div class="lbl">On our system</div><div class="btns">{b('Check and start again', True)}{b('Acknowledge')}<button class="btn" data-open-panel="p-fix">Fix it: GitHub's console page</button></div></div></div></div></div>
<div class="frame"><div class="fh">Pulse · one row, Critical, unread</div><div class="fb">{hd}<div class="prow unread"><div>Control Board</div><div class="sev">Critical</div><div>{SUBJ}<div class="sub" style="font-weight:400">{HELD}</div>
<div class="btns" style="margin-top:.3rem">{b('Check and start again', True)}<button class="btn" data-open-panel="p-fix">Fix it</button></div></div><div>Nov 21, {sc('9:40 AM')} MST</div></div></div></div>
<div class="frame"><div class="fh">Email · a new thread to the Founder</div><div class="fb"><div class="email"><div class="hd2"><span>From</span><b>Traversence Control Board</b><span>To</span><b>the Founder (Jason)</b><span>Subject</span><b>{SUBJ}</b><span>Sent</span><span>Nov 21, {sc('9:40 AM')} MST</span></div>
<p><b>GitHub</b></p><p><b>Status:</b> {STAT}. Since {sc('9:40 AM MST')}.</p><p><b>What it affects:</b> {HELD}</p>
<p><b>What the system is doing:</b> it tries again at each 10-minute look and closes this as Recovered when a look succeeds. Next look {sc('9:50 AM MST')}.</p>
<p><b>Fix it:</b> <a href="https://traversence.com/admin/dashboard/stream#case=alarm:T-####&amp;fix=1">https://traversence.com/admin/dashboard/stream#case=alarm:T-####&amp;fix=1</a></p>
<p><b>Console at GitHub:</b> No console page registered for GitHub yet.</p></div></div></div>
<div class="frame"><div class="fh">Push · one, tagged for this incident</div><div class="fb"><div class="push"><div class="pa"><span>Traversence</span><span>{sc('9:40 AM')}</span></div><div class="pt">SYSTEM ALARM: GitHub</div><div>{STAT}.</div></div></div></div></div>
<div class="step"><h3>2 · Fix it opens GitHub's card in the side panel</h3><div class="when">From the board row, the Pulse row or the email's link.</div>
<div class="frame"><div class="fh">Side panel over the board (× · Esc · click outside to close)</div><div class="fb"><div class="panel-static"><button class="x" aria-label="Close" style="pointer-events:none">×</button><h2 style="font-size:16px;margin:.2rem 0 .7rem">GitHub</h2>{card_api_down()}</div>
<p><button class="btn f" data-open-panel="p-fix">Open it live</button></p></div></div></div>
<div class="step"><h3>3 · Recovered closes all four</h3><div class="when">{sc('10:00 AM MST')}: its second try, the 10-minute look, succeeds. Releases waiting in the inbox are taken; the sync and Railway's deploys run again.</div>
<div class="frame"><div class="fh">Board</div><div class="fb"><div class="need"><div class="l1">{light('g', 'Closed: Recovered')}<span class="t">GitHub</span><span class="why">T-#### · IR-####</span></div>
<div>{REC}</div><div class="sub">Its check passed: the release look answered (found {sc('1')}) at {sc('10:00 AM MST')}.</div><div class="sub">Still off: none. GitHub's hold wrote no switch.</div></div></div></div>
<div class="frame"><div class="fh">Pulse · the same row, its last note</div><div class="fb">{hd}<div class="prow"><div>Control Board</div><div class="sev" style="color:var(--meta)">Critical</div><div>SYSTEM ALARM NOTICE: GitHub: Recovered<div class="sub">Last note: {REC} Still off: none.</div><div class="why">It leaves the open list; the bell's count drops.</div></div><div>Nov 21, {sc('10:00 AM')} MST</div></div></div></div>
<div class="frame"><div class="fh">Email · a reply in the same thread</div><div class="fb"><div class="email"><div class="hd2"><span>Subject</span><b>SYSTEM ALARM NOTICE: GitHub: Recovered</b><span>In reply to</span><span>{SUBJ}</span><span>Sent</span><span>Nov 21, {sc('10:00 AM')} MST</span></div>
<p>{REC}</p><p>Its check passed: the release look answered at {sc('10:00 AM MST')}. Still off: none.</p></div></div></div>
<div class="frame"><div class="fh">Push · the earlier push replaced in place under its tag, silently</div><div class="fb"><div class="push"><div class="pa"><span>Traversence · replaced quietly, no sound</span><span>{sc('10:00 AM')}</span></div><div class="pt">SYSTEM ALARM: GitHub</div><div>Recovered.</div></div></div></div></div>
</div>"""
    add('github-d-api-down.html', 'GitHub down, every surface', 'GitHub\'s API down walked through the board, Pulse, email, push, Fix it and Recovered.',
        intro('<b>(d) GitHub\'s API down, every surface.</b> A scenario: every time is illustrative (dotted). The radius, links, ids\' shape and words are the spec\'s and the manifest\'s. '
              'Words: 02 row 57, §3c, §7. A critical part isn\'t escalated by Pulse\'s clock here: it recovered inside 2 hours.')
        + steps + prov(PROV_GH), panel('p-fix', 'GitHub', card_api_down()))


gh_board()
add('github-b-working.html', 'GitHub card, Working', 'GitHub\'s four-zone card, Working, with its real runs.',
    intro('<b>(b) GitHub\'s card, Working.</b> Its runs read from GitHub Actions. Zones in order; the sync\'s page is the Sync part\'s “Its runs”, not a portal slot. '
          'The tokens\' expiry and reads are “not read yet”: this mockup can\'t read them.') + card_working_gh() + prov(PROV_GH))
GH_FAULTS = [
    ('github-c1-api-down.html', 'GitHub card: API not answering', 'Critical: releases and the sync held, everything else unaffected.',
     '<b>(c1) GitHub\'s API not answering (row 57).</b> Critical (procedures): a trip at once while the system keeps trying. Held: releases, the sync, new Railway deploys only. '
     'The second card is after Acknowledge: the light stays red.', lambda: card_api_down() + card_api_down(ack=True)),
    ('github-c2-token.html', 'GitHub card: a token', 'A token expiring within 14 days, then a token refused.',
     '<b>(c2) A token expiring in under 14 days (row 58), then refused (row 59).</b> The expiry date is a scenario: the tokens\' expiry isn\'t read here.',
     lambda: card_token() + card_token(refused=True)),
    ('github-c3-sync-stuck.html', 'GitHub card: the sync stuck', 'The sync\'s run stuck over 15 minutes: a warning while it tries.',
     '<b>(c3) The sync stuck over 15 min (row 60).</b> The run number and times are a scenario; #691 before it is real.', card_sync_stuck),
    ('github-c4-sync-missed.html', 'GitHub card: a sync slot missed', 'Today\'s real missed 06:00 UTC sync: a warning, nothing held.',
     '<b>(c4) A scheduled sync missed: today\'s real 06:00 UTC slot (row 61).</b> A warning, nothing held; it cleared when run #690 (by hand) finished at 5:26 AM MST. '
     'The warning\'s 07:00 UTC raise is <i>spec</i>: live, nothing was raised.', card_sync_missed),
    ('github-c5-check-failed.html', 'GitHub card: a check run failed', 'A failed site check run: nothing held, that report waits.',
     '<b>(c5) A failed check run.</b> No check run failed today (the real last runs all succeeded), so the failure is a scenario on the real workflow.', card_check_failed),
    ('github-c6-release-look.html', 'GitHub card: the release look', 'Today\'s real found -1 drawn as the contract\'s scenario: not an observed trip.',
     '<b>(c6) The release look failing: today\'s real <code>found: -1</code> at 10:00 UTC, drawn as the spec scenario.</b>', card_look_failed),
]
for f, t, d, i, fn in GH_FAULTS:
    add(f, t, d, intro(i) + fn() + prov(PROV_GH))
gh_story()
build_index()
