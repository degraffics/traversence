from cards import *

PAGES = []


def add(fname, title, desc, body, panels=''):
    page(fname, title, desc, body, panels)
    PAGES.append((fname, title, desc))


def panel(pid, title, inner):
    return (f'<aside class="panel" id="{pid}" hidden role="dialog" aria-label="{E(title)}">'
            f'<button class="x" aria-label="Close">×</button><h2 style="font-size:16px;margin:.2rem 0 .7rem">{E(title)}</h2>{inner}</aside>')


def intro(h):
    return f'<div class="intro">{h}{LEGEND}</div>'


# ================= (a) the board's first view =================
STILL_OFF_ROW = lambda name: (f'<div class="row hl"><div><div class="nm">{name}</div>{light("h", "Off by the system (left by a closed trip)")}</div>'
                              f'<div class="sub">{STILL_OFF_WORDS}</div><div class="btns">{b("Switch on", True)}</div></div>')

top_open = f"""<section class="top r" aria-label="The board's top line">
<div><div class="q">Is it healthy</div><div class="a">{light('r', '1 trip')} · <span class="cnt a">1 warning</span> · <span class="cnt a">2 switches still off</span></div>
<div class="why">The light is red while a trip's hold stands, acknowledged or not.</div></div>
<div><div class="q">What's held and why</div><div class="a">Calls to Common Crawl's index: it isn't answering (IR-####). Nothing in W3 uses it.</div></div>
<div><div class="q">What needs me</div><div class="a">1 trip · 2 switches still off. Both are listed just below, each with its button.</div></div>
</section>"""

top_ack = f"""<section class="top r" aria-label="The top line after Acknowledge">
<div><div class="q">Is it healthy</div><div class="a">{light('r', '1 trip (seen by Jason)')} · <span class="cnt a">1 warning</span> · <span class="cnt a">2 switches still off</span></div></div>
<div><div class="q">What's held and why</div><div class="a">Calls to Common Crawl's index: it isn't answering (IR-####).</div></div>
<div><div class="q">What needs me</div><div class="a">1 trip (seen) · 2 switches still off. Acknowledge never turns the light green; Check and start again stays on the trip's row.</div></div>
</section>"""

trip_cc = f"""<div class="need" id="trip-cc"><div class="l1">{light('r', 'Trip')}<span class="t">Common Crawl's index isn't answering</span><span class="why">T-#### · IR-####</span></div>
<div>Paused by the system (a trip) since Nov 20, 12:06 AM MST. Held: calls to Common Crawl's index. Unaffected: W3 and every workflow. Next check: <b>not read yet</b>.</div>
<div class="act"><div class="lbl">On our system</div><div class="btns">{b('Check and start again', True)}{b('Acknowledge')}</div></div>
<div class="act"><div class="lbl">At Common Crawl</div><div class="slots">{slot('billing','Common Crawl')}{slot('keys','Common Crawl')}{slot('console','Common Crawl', fix=True)}{slot('login','Common Crawl')}</div></div>
<details><summary>Details</summary><dl class="dl"><dt>Reading</dt><dd>504 Gateway Time-out (nginx), first seen Nov 19, 6:01 PM MST</dd><dt>Rule</dt><dd>02 §4 rows 1–2, 4, 5; group A's designed starting state</dd></dl></details></div>"""

closed_trip = f"""<details class="grp" open><summary><b>W3's trip of Nov 20</b> {light('n','Closed Nov 21')} <span class="cnt a">2 switches still off</span> <span class="why">opened by itself</span></summary>
<div class="in"><div class="sub" style="margin-bottom:.3rem">Still off: Read a source (W3), Common Crawl (W3): left off by this trip before trips paused instead. No one has switched them by hand, so they wait for you.</div>
<div class="rows">{STILL_OFF_ROW('Read a source (W3)')}{STILL_OFF_ROW('Common Crawl (W3)')}</div></div></details>"""

needs_att = f"""<details class="grp" open><summary><b>Paused now → Needs attention</b> <span class="cnt a">2 switches still off</span> <span class="why">opened by itself</span></summary>
<div class="in rows">{STILL_OFF_ROW('Read a source (W3)')}{STILL_OFF_ROW('Common Crawl (W3)')}</div></details>"""

warn_grp = f"""<details class="grp"><summary><b>Warnings: the system is handling them</b> <span class="cnt a">1</span> <span class="why">folded: no switch is off inside</span></summary>
<div class="in"><div class="row"><div><div class="nm">/api/crawl/loads is slow</div>{light('a','Warning (Slow)')}</div>
<div class="sub">It didn't reply to the crawler within 120 s for 500 rows at 1:31 AM MST. The system is checking it.</div><div class="btns">{b('Check now')}</div></div></div></details>"""

hosts = f"""<div class="grid3">
<div class="host"><div class="hn"><a href="#">Bluehost</a><span class="badge role">Host</span><span class="badge crit">Critical: page, search, payments, the databases, procedures</span></div>
<div>{light('a','On · Warning (Slow)')}: /api/crawl/loads is slow</div>
<ul><li>Pages, search, sign-in, payments, the databases</li><li>Our endpoints and the site's jobs</li></ul></div>
<div class="host"><div class="hn"><a href="#" data-open-panel="p-railway">Railway</a><span class="badge role">Host</span><span class="badge crit">Not critical by itself</span></div>
<div>{light('g','On · Working')} · Stage 5 of 8 · Operational</div>
<ul><li>The crawler (every 5 min) → W1–W12 · <span class="cnt a">2 switches still off in W3</span></li><li>W0 Indexer (always on) → W0</li><li>The video worker (every 5 min) → posted videos · <span class="mm">restart rule differs from the repo</span></li></ul>
<div class="kv" style="margin-top:.3rem"><span>Use since Nov 1: {USAGE_UNITS} · <b>not priced yet</b> · <a href="{RW['usage_url']}" target="_blank" rel="noopener">Billing · Usage ↗</a></span></div>
<div class="rad" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)"><div class="h"><b>If it goes down, Held</b>the crawler (all gathering, W1–W12), W0, new video copies (they queue)</div>
<div class="u"><b>Unaffected</b>Bluehost's pages, search, sign-in, payments and databases; GitHub; everything stored</div></div>
<div class="btns" style="margin-top:.4rem">{b('Open Railway\'s card', True).replace('<button class="btn f">','<button class="btn f" data-open-panel="p-railway">')}</div></div>
<div class="host"><div class="hn"><a href="#">GitHub</a><span class="badge role">Host</span><span class="badge crit">Critical: procedures (releases, the sync)</span></div>
<div>{light('n','not read yet')}</div>
<ul><li>The release look: the 10-minute job's last look, 3:00 AM MST, got no answer (<code>found: -1</code>)</li><li>The sync, release ping, audit, checks and probe</li></ul></div>
</div>"""

wf = f"""<div class="rows" style="background:var(--card);border:1px solid var(--card-edge);border-radius:.5rem;padding:0 .8rem">
<div class="row"><div><div class="nm">W1 Listings</div>{light('g','On · Working')}</div><div class="sub">Its bulk loads' next nightly runs go ahead: /api/crawl/loads being slow is a warning on the site, never a hold on W1 (row 11).</div><div class="btns"></div></div>
<div class="row hl"><div><div class="nm">W3 Sources and the place store</div>{light('g','On · Working')} <span class="cnt a">2 switches still off</span></div><div class="sub">Working. Its backup source Common Crawl's index is paused (IR-####); nothing in W3 uses it. Read a source (W3) and Common Crawl (W3): Off by the system (left by a closed trip), each with Switch on above.</div><div class="btns"></div></div>
</div>"""

board_body = (intro('<b>(a) The board\'s first view, 1280.</b> The live alarms of 10:19 UTC (3:19 AM MST) as the state model translates them '
                    '(02 §4 group A: one trip on Common Crawl\'s index, one warning on our endpoint, two switches Off by the system (left by a closed trip)), '
                    'with Railway\'s real values read at 8:51 AM MST. It answers three questions in order: is it healthy, what\'s held and why, what needs me. '
                    'Each item carries its own buttons where it\'s reported: no banner first, nothing in a closed group.')
              + top_open
              + '<div class="sec"><h2>What needs me</h2>' + trip_cc + closed_trip + needs_att + warn_grp + '</div>'
              + '<div class="sec"><h2>Hosts</h2>' + hosts + '</div>'
              + '<div class="sec"><h2>The Knowledge Engine (2 of its workflows)</h2>' + wf + '</div>'
              + '<div class="sec"><h2>After Acknowledge on the trip</h2>' + top_ack + '</div>'
              + '<div class="note">The 10:19 snapshot also had 8 other alarms (Claude (AI) and Google caps, the identity recheck job, 77 off-region sources, '
                '/api/crawl/refresh\'s count, Wikidata\'s 429, Common Crawl\'s data host, the Census geocoder). Their state under the model needs their live '
                'readings (tries, times), so they\'re left out here rather than guessed. GitHub\'s last release look got no answer at 3:00 AM MST; whether '
                'that became a trip under row 57 depends on later looks the snapshot doesn\'t hold, so its state shows “not read yet”. '
                'Only W1 and W3 are drawn: the other workflows\' states weren\'t read.</div>'
              + prov(PROV_RW + ['Alarms: <code>site-audit/results/alarms.json</code> on traversence <code>main</code>, written 10:19:49 UTC; Common Crawl\'s index trip: since 2026-11-20 01:01 UTC, tripped 07:06 UTC.']))
add('railway-a-board.html', 'The board, first view', 'The board\'s first view with Railway among the hosts: healthy, held, needs me.',
    board_body, panel('p-railway', 'Railway', card_working()))

# ================= (b) Working =================
add('railway-b-working.html', 'Railway card, Working', 'Railway\'s four-zone card, Working, with its real values.',
    intro('<b>(b) Railway\'s card, Working.</b> Every value read from Railway\'s API or the live service definition. '
          'Zones in order: identity and role, the services it hosts, sensors and keys, then actions. The keys kept on Railway for other '
          'modules read “not checked yet” (grey, holding nothing) because the worker reports presence only today (Q4).')
    + card_working() + prov(PROV_RW))

# ================= (c) faults =================
FAULTS = [
    ('railway-c1-deploy-failed.html', 'Railway card: a deploy failed', 'A warning: the old version keeps running; nothing held.',
     '<b>(c1) A deploy failed (warning, row 51).</b> The failed deploy\'s id and time are a scenario; the running deploy is real.', card_deploy_failed),
    ('railway-c2-crawler-down.html', 'Railway card: the crawler down', 'A trip: all gathering held, everything else unaffected.',
     '<b>(c2) The crawler down (trip, rows 47–48).</b> When a fault is open, zone 4 moves up under zone 1, so the thread and its actions come first. '
     'The second card is the same trip after Acknowledge: the light stays red.', lambda: card_crawler_down() + card_crawler_down(ack=True)),
    ('railway-c3-w0-silent.html', 'Railway card: W0 Indexer silent', 'W0 Indexer\'s heartbeat stopped: a warning while it tries, then a trip holding W0.',
     '<b>(c3) W0 Indexer silent (row 50).</b> First while the system tries (nothing held, nobody told), then after its third try (W0 held).',
     lambda: card_w0_silent() + card_w0_silent(trip=True)),
    ('railway-c4-key-missing.html', 'Railway card: a key missing', 'A key the worker reported missing: only calls with that key held.',
     '<b>(c4) A key reported missing (rows 30, 31).</b> Until the worker reports, a Railway key is “not checked yet”: grey, never red, holding nothing. '
     'Once the worker reports <code>TAVILY_API_KEY</code> missing, only the calls that use it are held, each need goes to Brave Search, and Railway stays green.',
     card_key_missing),
    ('railway-c5-spend.html', 'Railway card: spend near the cap', 'Spend near the plan: one percentage, the same in title and body.',
     '<div style="border:2px solid var(--amber);border-radius:.5rem;padding:.5rem .7rem;margin:0 0 .6rem;font-weight:700;color:var(--amber)">Illustrative: no price source yet; today the chip shows units, not priced yet</div>'
     '<b>(c5) Spend near the cap (row 39, BL).</b> The dotted dollars show the rule once a price source is registered: round(spend ÷ cap × 100), one figure in the title, '
     'the body, the chip and the sensor. Today\'s real chip (units, not priced yet, read 9:27 AM MST) is shown beside the illustrative one.', card_spend),
    ('railway-c6-switch-left-off.html', 'Railway card: a switch left off', 'Two W3 switches left off by a closed trip, with Switch on on the row.',
     '<b>(c6) A switch left off by a closed trip (§0 CS-3, case None; row 7).</b> Real: W3\'s two tasks on the crawler. The group opens by itself '
     'because a switch inside is still off, and Switch on sits on each task\'s row.', card_switch_left),
]
for f, t, d, i, fn in FAULTS:
    add(f, t, d, intro(i) + fn() + prov(PROV_RW))

# ================= (d) the crawler down at 2 AM, every surface =================
SUBJ = 'SYSTEM ALARM NOTICE: The crawler is down: Paused by the system'
PUSH_T = 'SYSTEM ALARM: The crawler is down'
PUSH_B = f'Paused by the system (a trip) since {sc("2:40 AM MST")}. All gathering waits.'
HELD_LINE = 'Held: all gathering the crawler runs (W1–W12\'s crawler tasks). Unaffected: W0 Indexer, the video worker, Bluehost\'s pages, search, sign-in, payments and databases, GitHub.'
REC = f'Recovered: The crawler was down {sc("1 h 5 min")}, the system fixed it.'
STILL = ('Still off: Read a source (W3), Common Crawl (W3): left off by W3\'s trip of Nov 20 before trips paused instead. '
         'No one has switched them by hand, so they wait for you.')


def board_row(state):
    if state == 'warn':
        return (f'<div class="need"><div class="l1">{light("a","!")}<span class="t">The crawler is down</span><span class="why">W-####</span></div>'
                f'<div>The crawler hasn\'t reported since {sc("1:55 AM MST")} · Trying again (1 of 3). Next check {sc("2:10 AM MST")}.</div>'
                f'<div class="why">Nothing held yet. Nobody is told while the system tries.</div></div>')
    if state == 'trip':
        return (f'<div class="need"><div class="l1">{light("r","Trip")}<span class="t">The crawler is down</span><span class="why">T-#### · IR-####</span></div>'
                f'<div>Paused by the system: all gathering waits. Since {sc("2:40 AM MST")}.</div><div class="sub">{HELD_LINE}</div>'
                f'<div class="act"><div class="lbl">On our system</div><div class="btns">{b("Check and start again", True)}{b("Acknowledge")}'
                f'<button class="btn" data-open-panel="p-fix">Fix it: Railway\'s console page</button></div></div></div>')
    return (f'<div class="need"><div class="l1">{light("g","Closed: Recovered")}<span class="t">The crawler is down</span><span class="why">T-#### · IR-####</span></div>'
            f'<div>{REC}</div><div class="sub">Its check passed: a crawler run started at {sc("3:10 AM MST")} and reported back.</div>'
            f'<div class="row hl" style="margin-top:.4rem"><div class="sub" style="grid-column:1/3">{STILL}</div><div class="btns">{b("Switch on: Read a source (W3)", True)}{b("Switch on: Common Crawl (W3)")}</div></div></div>')


def pulse_row(state):
    hd = '<div class="prow hd"><div>From</div><div>Severity</div><div>Subject</div><div>Received</div></div>'
    if state == 'trip':
        r = (f'<div class="prow unread"><div>Control Board</div><div class="sev">Critical</div><div>{SUBJ}<div class="sub" style="font-weight:400">{HELD_LINE}</div>'
             f'<div class="btns" style="margin-top:.3rem">{b("Check and start again", True)}<button class="btn" data-open-panel="p-fix">Fix it</button></div></div><div>Nov 21, {sc("2:40 AM")} MST</div></div>')
    else:
        r = (f'<div class="prow"><div>Control Board</div><div class="sev" style="color:var(--meta)">Critical</div><div>SYSTEM ALARM NOTICE: The crawler is down: Recovered'
             f'<div class="sub">Last note: {REC} {STILL}</div><div class="why">It leaves the open list; the bell\'s count drops.</div></div><div>Nov 21, {sc("3:10 AM")} MST</div></div>')
    return hd + r


def email(state):
    if state == 'trip':
        return f"""<div class="email"><div class="hd2"><span>From</span><b>Traversence Control Board</b><span>To</span><b>the Founder (Jason)</b><span>Subject</span><b>{SUBJ}</b><span>Sent</span><span>Nov 21, {sc('2:40 AM')} MST</span></div>
<p><b>The crawler is down</b></p>
<p><b>Status:</b> Paused by the system (a trip) since {sc('2:40 AM MST')}.</p>
<p><b>What it affects:</b> {HELD_LINE}</p>
<p><b>What the system is doing:</b> it checks again on the back-off ladder and closes this as Recovered when a crawler run reports back. Next check {sc('2:50 AM MST')}.</p>
<p><b>Fix it:</b> <a href="https://traversence.com/admin/dashboard/stream#case=alarm:T-####&amp;fix=1">https://traversence.com/admin/dashboard/stream#case=alarm:T-####&amp;fix=1</a></p>
<p><b>Console at Railway:</b> <a href="https://railway.com/dashboard">https://railway.com/dashboard</a></p>
<details><summary>Details</summary><p class="sub">W-#### · T-#### · IR-####. Tries {sc('2:10, 2:20, 2:40 AM MST')}: no run reported.</p></details></div>"""
    return f"""<div class="email"><div class="hd2"><span>Subject</span><b>SYSTEM ALARM NOTICE: The crawler is down: Recovered</b><span>In reply to</span><span>{SUBJ}</span><span>Sent</span><span>Nov 21, {sc('3:10 AM')} MST</span></div>
<p>{REC}</p><p>Its check passed: a crawler run started at {sc('3:10 AM MST')} and reported back. Gathering runs again.</p><p>{STILL}<br>Switch them on: <a href="https://traversence.com/admin/config/control/wf-W3#cb-steps">https://traversence.com/admin/config/control/wf-W3#cb-steps</a></p></div>"""


def push(state):
    if state == 'trip':
        return f'<div class="push"><div class="pa"><span>Traversence</span><span>{sc("2:40 AM")}</span></div><div class="pt">{PUSH_T}</div><div>{PUSH_B}</div></div>'
    return (f'<div class="push"><div class="pa"><span>Traversence · replaced quietly, no sound</span><span>{sc("3:10 AM")}</span></div><div class="pt">{PUSH_T}</div>'
            f'<div>Recovered. 2 switches still off: open to switch on.</div></div>')


fix_panel_inner = card_crawler_down()
steps = f"""<div class="steps">
<div class="step"><h3>1 · The board: a warning, while the system tries</h3><div class="when">{sc('2:05 AM MST')}: no crawler run since {sc('1:55 AM')} (schedule every 5 min, plus 5 min). Pulse, email, push: nothing.</div>
<div class="frame"><div class="fh">Board · What needs me</div><div class="fb">{board_row('warn')}</div></div></div>
<div class="step"><h3>2 · Three tries fail: the trip, on four surfaces in the same minute</h3><div class="when">Tries at {sc('2:10, 2:20 and 2:40 AM MST')}. One title everywhere: “The crawler is down”. One status: “Paused by the system”. One Held/Unaffected line.</div>
<div class="frame"><div class="fh">Board · What needs me</div><div class="fb">{board_row('trip')}</div></div>
<div class="frame"><div class="fh">Pulse · one row, Critical, unread</div><div class="fb">{pulse_row('trip')}</div></div>
<div class="frame"><div class="fh">Email · a new thread to the Founder</div><div class="fb">{email('trip')}</div></div>
<div class="frame"><div class="fh">Push · one, tagged for this incident</div><div class="fb">{push('trip')}</div></div></div>
<div class="step"><h3>3 · In the morning: Fix it opens Railway's card in the side panel</h3><div class="when">From the board row, the Pulse row or the email's link. No other page.</div>
<div class="frame"><div class="fh">Side panel over the board (× · Esc · click outside to close)</div><div class="fb"><div class="panel-static"><button class="x" aria-label="Close" style="pointer-events:none">×</button><h2 style="font-size:16px;margin:.2rem 0 .7rem">Railway</h2>{fix_panel_inner}</div>
<p><button class="btn f" data-open-panel="p-fix">Open it live</button></p></div></div></div>
<div class="step"><h3>4 · Recovered closes all four</h3><div class="when">{sc('3:10 AM MST')}: the after-trip check passes. The hold lifts; the incident closes; no person was needed. A switch that stays off is named on every surface.</div>
<div class="frame"><div class="fh">Board</div><div class="fb">{board_row('rec')}</div></div>
<div class="frame"><div class="fh">Pulse · the same row, its last note</div><div class="fb">{pulse_row('rec')}</div></div>
<div class="frame"><div class="fh">Email · a reply in the same thread</div><div class="fb">{email('rec')}</div></div>
<div class="frame"><div class="fh">Push · the earlier push replaced in place under its tag, silently</div><div class="fb">{push('rec')}</div></div></div>
</div>"""
add('railway-d-2am.html', 'The crawler down at 2 AM', 'One failure walked through the board, Pulse, email, push, Fix it and Recovered.',
    intro('<b>(d) “The crawler down at 2 AM”, every surface.</b> A scenario: every time is illustrative (dotted). The names, the held and unaffected '
          'parts, the links and the two switches still off are real. Words: 02 §4 rows 47–48, 90; §4.8; §7; CS-5.')
    + steps
    + '<div class="note">Nobody is escalated: it recovered inside Pulse\'s 2-hour clock. Had it stayed open, Pulse would raise it to the Manager at 2 h and the Director at 4 h (rows 121–122), each a reply in the same thread.</div>'
    + prov(PROV_RW), panel('p-fix', 'Railway', card_crawler_down()))

# ================= (e) IR-0001 =================
ir = f"""<article class="card fault-r"><div class="z">
<div class="zh"><span class="n">Incident Report</span>opened at the trip · kept 365 days</div>
<h1 style="font-size:20px">IR-0001 · Correlated · 2 parts: foursquare_bulk, overture_bulk · Paused by the system</h1>
<div class="stage"><span class="st">Opened Nov 21, 2:25 AM MST (09:25 UTC)</span><span class="nx">Status at the 3:19 AM MST snapshot: open</span></div>
</div><div class="z"><table class="ir"><tbody>
<tr><th>Root cause</th><td><span class="uc">Unconfirmed</span><p style="margin:.35rem 0 0">Both tasks timed out while a bulk load was sending its rows to our site's load endpoint. Jason's question stands open: did the timeout occur inside our load handler, or on the outbound/response path? Settling it needs our endpoint's own timings for those minutes (the ledger rows for <code>/api/crawl/loads.php</code>, 1:15–1:21 and 1:26–1:32 AM MST, or Bluehost's access log). Until then, this report doesn't name a root cause.</p></td></tr>
<tr><th>Confirmed so far</th><td>The worker sent its request to our site's load endpoint and no reply came within its timeout. It was not a data source (Overture or Foursquare), and not Railway.</td></tr>
<tr><th>Plug-ins</th><td>The crawler on Railway: worker 2.16.0 (deploy <code>560ff94b</code>) then; 2.16.1 from 5:22 AM MST (deploy <code>22f18475</code>)</td></tr>
<tr><th>Signature</th><td><code>TimeoutError('The read operation timed out')</code>, a bulk load sending its rows, both within 11 minutes</td></tr>
<tr><th>Members, warning and trip ids</th><td><ul style="margin:0;padding-left:1rem">
<li><b>foursquare_bulk</b> (run #1288): warning <b>W-####</b> first failure Nov 21, 1:20 AM MST (08:20:35 UTC) · trip <b>T-####</b> 2:25 AM MST (09:25:02 UTC)</li>
<li><b>overture_bulk</b> (run #1290): warning <b>W-####</b> first failure 1:31 AM MST (08:31:41 UTC) · trip <b>T-####</b> 2:35 AM MST (09:35:01 UTC)</li></ul>
<div class="why">The live system gave these alarms keys, not ids (<code>task:failed:foursquare_bulk</code>, <code>task:failed:overture_bulk</code>); W- and T- numbers are assigned by the engine once built.</div></td></tr>
<tr><th>Timeline</th><td><ul style="margin:0;padding-left:1rem">
<li>1:15 AM MST: the container starts; foursquare_bulk's run begins</li>
<li>1:20:34 AM: <code>foursquare_bulk stopped: TimeoutError('The read operation timed out')</code></li>
<li>1:25:30 AM: overture_bulk's run 4fbc278e starts (its share 600 s)</li>
<li>1:31:41 AM: <code>overture_bulk stopped: TimeoutError('The read operation timed out')</code></li>
<li>Neither run logged a town's “records sent”; nothing else failed in those runs</li>
<li>2:25 AM and 2:35 AM: the hour ran out with no second try (each task runs nightly); the watch tripped them</li>
<li>Both trips paused all of W1; two emails and two pushes went out (today's code)</li></ul></td></tr>
<tr><th>Incident Radius, as it happened</th><td><div class="rad"><div class="h"><b>Held</b>all of W1 (both trips paused the whole workflow)</div><div class="o"><b>On a backup</b>none</div><div class="u"><b>Unaffected</b>pages, search, sign-in, payments, W0, W2–W12</div></div></td></tr>
<tr><th>Incident Radius, under the contract</th><td>Depends on the confirmed root cause.
<div class="rad"><div class="h"><b>If our endpoint was slow</b>Held: nothing. A warning on the Site part; the two bulk loads' next nightly runs go ahead.</div>
<div class="o"><b>If our endpoint was failing</b>Held: only overture_bulk and foursquare_bulk (and geofabrik_bulk, which calls the same endpoint).</div>
<div class="u"><b>Unaffected either way</b>the rest of W1, Railway, every source, the site</div></div></td></tr>
<tr><th>Where it would sit</th><td>Inside our load handler: the <b>Site</b> endpoint's fault. On the outbound/response path: that path's (the worker's connection, or the network). Neither is Overture or Foursquare as a source.</td></tr>
<tr><th>Proportionate actions</th><td><div class="act"><div class="lbl">On our system</div><div class="btns">{b('Check and start again', True)}{b('Pause only this task')}{b('Fix it: /api/crawl/loads, its timings')}</div></div>
<div class="act"><div class="lbl">At Bluehost</div><div class="slots">{slot('billing','Bluehost')}{slot('keys','Bluehost')}{slot('console','Bluehost','cPanel','#cpanel', fix=True, src='env', note='https://‹CPANEL_HOST›:2083, built from <code>CPANEL_HOST</code> in .env (value not read)')}{slot('login','Bluehost','Account','https://my.bluehost.com/')}</div></div></td></tr>
<tr><th>Recovery</th><td>Closes as Recovered when the endpoint's check passes and the tasks next do their work.</td></tr>
<tr><th>What would stop it happening again</th><td>Smaller batches to the endpoint, and a timeout reported as slow, not as a crash (case #51's draft, held for this build). The worker logs each site call's duration and size.</td></tr>
<tr><th>Evidence</th><td>Railway's logs for deployment <code>560ff94b</code>, 1:15–1:45 AM MST, read-only; the crawler's code as live then (<code>open_load</code>, <code>site_call</code>). Source: <code>step2/ir-0001.md</code>; <code>manifests-railway-github.md</code> §1.</td></tr>
</tbody></table></div></article>"""
add('railway-e-ir-0001.html', 'IR-0001, Incident Report', 'This morning\'s two W1 timeouts as one correlated Incident Report, its root cause Unconfirmed.',
    intro('<b>(e) IR-0001, rendered as an Incident Report.</b> Real data from the live alarms (10:19 UTC), Railway\'s logs and step 2\'s audit. '
          'Its root cause is <b>Unconfirmed</b>, in its own row.') + ir)

# ================= index =================
def build_index():
    items = ''
    for f, t, d in PAGES:
        s = f.replace('.html', '')
        items += (f'<div class="card"><div class="z"><a class="name" href="{f}">{E(t)}</a><div class="sub" style="margin:.2rem 0 .5rem">{E(d)}</div>'
                  f'<div class="grid2"><a href="shots/{s}-1280.png"><img src="shots/{s}-1280.png" alt="{E(t)} at 1280" style="width:100%;border:1px solid var(--card-edge);border-radius:.4rem"></a>'
                  f'<a href="shots/{s}-390.png"><img src="shots/{s}-390.png" alt="{E(t)} at 390" style="max-width:220px;width:100%;border:1px solid var(--card-edge);border-radius:.4rem"></a></div>'
                  f'<div class="kv"><a href="shots/{s}-1280.png">1280 screenshot</a><a href="shots/{s}-390.png">390 screenshot</a></div></div></div>')
    page('index.html', 'Railway mockups', 'Step 3\'s first card spec: Railway\'s four-zone card in every state.',
         '<div class="intro"><b>The card spec\'s mockups (step 3): Railway, GitHub, Bluehost and Anthropic.</b> Static design pages, not site code. The spec is <code>../03-cards.md</code>; '
         'the words come from <code>../02-state-model.md</code>.</div>' + items)


if __name__ == '__main__':
    build_index()
