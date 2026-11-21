from kit import *

STAGE_TRIP = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>')
Z4_WORKING = act_rows(b('Check now') + b('Take out') + b('Pause everything'), 'Railway', railway_slots(),
                      then='After a portal page: Billing → <b>Check now</b> · Console → <b>Check now</b>. Only the board\'s check changes state.')


def railway_card(state_light, stage=STAGE_WORKING, spend=SPEND_UNREAD, thread_html=None, z2=None, z3=None, z3k=None,
                 z4=None, fault=None, z2_title_extra='', z3_title_extra=''):
    z1 = zone1(state_light, stage, spend, RADIUS_DOWN)
    z2h = zone(2, 'Capabilities and feeds: the services it hosts', svc_rows(z2), z2_title_extra)
    z3h = zone(3, 'Sensors and key readiness', sensors(z3, z3k), z3_title_extra)
    if thread_html:
        z4h = zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>',
                   thread_html)
        body = z1 + z4h + z2h + z3h
    else:
        z4h = zone(4, 'Actions and Fix it', z4 or Z4_WORKING)
        body = z1 + z2h + z3h + z4h
    cls = 'card' + (f' fault-{fault}' if fault else '')
    return f'<article class="{cls}" aria-label="Railway">{body}</article>'


# ---------- (b) Working ----------
def card_working():
    return railway_card(light('g', 'On · Working'))


# ---------- (c1) a deploy failed: warning, row 51 ----------
def card_deploy_failed():
    title = f'Railway: the crawler\'s deploy {sc("<code>a1b2c3d4</code>")} failed at {sc("9:40 AM MST")}; the old version is running.'
    th = thread(
        title,
        light('a', 'Warning (the system is trying)') + ' · the old version, deploy <code>22f18475</code> (crawler 2.16.1), keeps running.',
        'nothing', 'none', '<ul><li>Everything: the crawler runs on its old version</li><li>W0 Indexer, the video worker, the site</li></ul>',
        'A warning, a trip only if runs then fail (rows 47–48). Nobody is told: a warning goes to the board and the card only.',
        b('Check now', True),
        railway_slots(fix='console'),
        'Fix it: <b>Railway\'s console page</b> (row 131), then <b>Check now</b> reads the latest deploy again. '
        f'From the alarm\'s evidence: <a href="https://railway.com/project/{RW_PROJECT}/service/{RW_SVC["traversence"]}?id=" target="_blank" rel="noopener">the failed deploy\'s log ↗</a> '
        '<span class="why">(spec: built from Railway\'s project, service and deployment ids; the scenario deploy has no real id)</span>',
        '<dl class="dl"><dt>Alarm</dt><dd>W-#### (assigned by the engine) · Warning · no Acknowledge on a warning (R80)</dd>'
        '<dt>Reading</dt><dd>Railway\'s API: the newest deploy of <code>traversence</code> FAILED; the running one is <code>22f18475</code> SUCCESS</dd>'
        '<dt>Messages</dt><dd>Pulse —, email —, push — (a warning, §7)</dd><dt>Rule</dt><dd>02 §4 row 51; manifest radius “A deploy failed or stalled”</dd></dl>')
    z2 = {'traversence': {'state': light('a', 'On · Warning: its newest deploy failed'), 'cls': 'hl',
                          'more': f'<div class="sub"><b>Newest deploy {sc("a1b2c3d4")}: FAILED at {sc("9:40 AM MST")}.</b> Running: <code>22f18475</code>.</div>',
                          'btn': '<span class="why">its actions are in the thread above ↑</span>'}}
    z3 = {'dep': f'<b>2 of 3 SUCCESS · the crawler\'s newest deploy FAILED</b> ({sc("9:40 AM MST")})', 'Deploys_cls': 'hl'}
    return railway_card(light('a', 'On · Warning (the system is trying)'), thread_html=th, z2=z2, z3=z3, fault='a')


# ---------- (c2) the crawler down: trip, rows 47–48 ----------
HELD_CRAWLER = '<ul><li>All gathering the crawler runs: W1–W12\'s crawler tasks</li></ul>'
UNAFF_CRAWLER = ('<ul><li>W0 Indexer and the video worker (Railway\'s other two services)</li>'
                 '<li>Bluehost: pages, search, sign-in, payments, the databases, the site\'s jobs</li>'
                 '<li>GitHub: releases, the sync</li><li>Everything already stored</li></ul>')


def crawler_down_thread(t_since='2:40 AM MST', status_extra='', ack=False, closed=None):
    if closed:
        return closed
    st = (light('r', 'Paused by the system (a trip)') + f' since {sc(t_since)}.{status_extra}')
    if ack:
        st += ' <span class="why">Seen by Jason 8:05 AM MST. The light stays red while the hold stands.</span>'
    return thread(
        'The crawler is down',
        st,
        HELD_CRAWLER, 'none', UNAFF_CRAWLER,
        f'It checks again on the back-off ladder (10 min, doubling to 6 h). Next check {sc("2:50 AM MST")}. '
        'It closes as Recovered when a crawler run starts within its schedule plus 5 min and reports back.',
        b('Check and start again', True) + (b('Acknowledge') if not ack else '') + b('Take out'),
        railway_slots(fix='console'),
        'Fix it: <b>Railway\'s console page</b>, the crawler\'s deploy and schedule (row 48), then <b>Check and start again</b>. '
        'Opening Railway changes nothing on the board (row 134).',
        f'<dl class="dl"><dt>Ids</dt><dd>W-#### (the warning, {sc("2:05 AM")}) · T-#### (the trip, {sc("2:40 AM")}) · '
        f'IR-#### (<a href="railway-e-ir-0001.html">its Incident Report</a> opens at the trip)</dd>'
        f'<dt>Tries</dt><dd>{sc("2:10")}, {sc("2:20")}, {sc("2:40 AM MST")}: no run reported (3 of 3)</dd>'
        '<dt>Evidence</dt><dd>No crawler run started within its schedule (every 5 min) plus 5 min</dd>'
        '<dt>Told</dt><dd>the Founder: one Pulse row (Critical), one email, one push, in the same minute</dd>'
        '<dt>Rule</dt><dd>02 §4 rows 47–48; §6 “Railway down” narrowed to one service (manifest: “One service down”)</dd></dl>')


def card_crawler_down(ack=False):
    z2 = {'traversence': {'state': light('r', 'On · Paused by the system (a trip)'), 'cls': 'hr',
                          'more': f'<div class="sub"><b>No run reported since {sc("1:55 AM MST")}.</b></div>',
                          'btn': '<span class="why">its actions are in the thread above ↑</span>'}}
    z3 = {'runs': f'<b>No run reported since {sc("1:55 AM MST")}</b> (due every 5 min)', 'Runs_cls': 'hr'}
    return railway_card(light('r', 'On · Paused by the system: the crawler (a trip)'), stage=STAGE_TRIP,
                        thread_html=crawler_down_thread(ack=ack), z2=z2, z3=z3, fault='r')


# ---------- (c3) W0 Indexer silent: row 50 ----------
def card_w0_silent(trip=False):
    if not trip:
        th = thread(
            f'W0 Indexer hasn\'t checked in since {sc("6:12 AM MST")}',
            light('a', 'Warning (the system is trying)') + f' · Trying again (2 of 3): no heartbeat at {sc("6:37 AM MST")}. Next check {sc("6:57 AM MST")}.',
            'nothing yet', 'none', '<ul><li>W0 keeps its place: nothing is held while the system tries</li><li>The crawler, the video worker, the site</li></ul>',
            'Checks W0 Indexer\'s heartbeat at about 5, 15 and 35 minutes. Nobody is told while it tries.',
            b('Check now', True),
            railway_slots(fix='console'),
            'Fix it: <b>Railway\'s console page</b> (the <code>w0-indexer</code> service), then <b>Check now</b>.',
            '<dl class="dl"><dt>Alarm</dt><dd>W-#### · Warning · no Acknowledge (R80)</dd><dt>Check</dt><dd>a heartbeat within 15 minutes (02 §2.7)</dd>'
            '<dt>Railway says</dt><dd><code>w0-indexer</code>: always on, restart on failure up to 10 times</dd><dt>Rule</dt><dd>02 §4 row 50, in rows 47–48\'s shape</dd></dl>')
        st = light('a', 'On · Warning (the system is trying)')
        z2l = light('a', 'On · Warning: no heartbeat')
        fault = 'a'
    else:
        th = thread(
            f'W0 Indexer hasn\'t checked in since {sc("6:12 AM MST")}',
            light('r', 'Paused by the system (a trip)') + f' since {sc("6:57 AM MST")}.',
            '<ul><li>W0 (W0 Indexer\'s work)</li></ul>', 'none',
            '<ul><li>The crawler: W1–W12</li><li>The video worker</li><li>Bluehost: pages, search, sign-in, payments, the databases</li><li>GitHub</li></ul>',
            f'Checks the heartbeat on the back-off ladder. Next check {sc("7:07 AM MST")}.',
            b('Check and start again', True) + b('Acknowledge') + b('Take out'),
            railway_slots(fix='console'),
            'Fix it: <b>Railway\'s console page</b> (<code>w0-indexer</code>), then <b>Check and start again</b>.',
            '<dl class="dl"><dt>Ids</dt><dd>W-#### · T-#### · IR-####</dd><dt>Told</dt><dd>the Founder: Pulse, email, push</dd></dl>')
        st = light('r', 'On · Paused by the system: W0 Indexer (a trip)')
        z2l = light('r', 'On · Paused by the system (a trip)')
        fault = 'r'
    z2 = {'w0-indexer': {'state': z2l, 'cls': 'hl' if not trip else 'hr', 'btn': '<span class="why">its actions are in the thread above ↑</span>'}}
    z3 = {'hb': f'<b>W0 Indexer: no heartbeat since {sc("6:12 AM MST")}</b> · the video worker: not built', 'Heartbeats_cls': 'hl' if not trip else 'hr'}
    return railway_card(st, thread_html=th, z2=z2, z3=z3, fault=fault)


# ---------- (c4) a key reported missing: rows 30, 31, 29 ----------
def card_key_missing():
    tav = f"""<div class="row hr"><div><div class="nm"><code>TAVILY_API_KEY</code></div><div class="sub">Tavily's key, kept on Railway (the crawler service → Variables)</div>
{light('r', 'Paused by the system')}</div>
<div>{thread(
        'Tavily: TAVILY_API_KEY isn\'t on Railway',
        light('r', 'Paused by the system') + ': calls with TAVILY_API_KEY wait for a new key. Reported by the crawler\'s run ' + sc('#1311') + ' at ' + sc('9:05 AM MST') + '.',
        '<ul><li>Calls that use <code>TAVILY_API_KEY</code></li></ul>',
        '<ul><li>Web search (W8, W10–W12): Brave Search</li><li>A missing phone or website (W2): Brave Search</li><li>Confirm a business (W1, W2): Brave Search</li></ul>'
        '<div class="why">Each, only while Brave Search\'s check passes (' + sc('it passes') + '); else that need waits.</div>',
        '<ul><li>Railway: the crawler, W0 Indexer, the video worker</li><li>The rest of every workflow</li><li>The site</li></ul>',
        'It waits for a person: a refused or missing key gets no tries (row 29). Each task goes to the next source on the map.',
        b('Check now', True, ) + '<span class="why">the worker\'s key test at its next run</span>',
        '<div class="slots">' + slot('billing', 'Railway', 'Usage', RW['usage_url']) + slot('keys', 'Railway', fix=True)
        + slot('console', 'Railway', 'Dashboard', RW['dash_url']) + slot('login', 'Railway') + '</div>',
        'Fix it: <b>Railway\'s API keys page</b>. No API keys page registered for Railway yet, so Fix it says so and never borrows Console or Billing. The key itself lives in the crawler service → Variables. After the key is put back: <b>Check now</b> (the worker\'s key test at its next run).',
        '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-#### (a stop kind: no warning first)</dd><dt>Rule</dt><dd>02 §4 rows 30, 31 (as 29), 43, 129</dd>'
        '<dt>Presence alone</dt><dd>Until the worker reports, every Railway key reads “not checked yet”, grey, and holds nothing (row 30, Q4)</dd></dl>',
        at='Railway',
        extra_at='<div style="margin-top:.5rem"><div class="lbl" style="font-weight:700;font-size:12.5px">At Tavily</div><div class="slots">'
        + slot('billing', 'Tavily') + slot('keys', 'Tavily') + slot('console', 'Tavily', 'Dashboard', 'https://app.tavily.com/')
        + slot('login', 'Tavily') + '</div><div class="then">Tavily\'s keys page isn\'t registered: Fix it names it and never borrows the console\'s link.</div></div>')}</div>
<div class="btns"></div></div>"""
    return railway_card(light('g', 'On · Working') + ' <span class="why">Railway itself is fine: the key is Tavily\'s</span>',
                        z3k={'TAVILY_API_KEY': tav}, z3_title_extra='<span style="color:var(--red);text-transform:none;letter-spacing:0">· 1 key paused, 4 not checked yet</span>')


# ---------- (c5) spend near the cap: row 39, BL ----------
def card_spend():
    spend = 3.85
    cap = 5.00
    pct = round(spend / cap * 100)
    title = f'Railway: {sc("$3.85")} of $5.00 ({sc(str(pct) + "%")})'
    sp = (f'<span class="spend warn"><span>Hobby · $5 a month including $5 of usage</span><b>{sc("$3.85")} of $5.00 ({sc(str(pct)+"%")})</b>'
          f'<a href="{RW["usage_url"]}" target="_blank" rel="noopener">Billing · Usage ↗</a></span>'
          '<span class="why" style="margin:0 .3rem">beside today\'s real chip:</span>' + SPEND_UNREAD)
    th = thread(
        title,
        light('a', 'Warning (Near its cap)') + f' · {sc("$3.85")} of $5.00 ({sc(str(pct)+"%")}), past its 75% warning.',
        'nothing', 'none', '<ul><li>Everything Railway hosts keeps running</li></ul>',
        'Reads the month\'s use against the plan at each watch. The band reached (50, 75, 95) decides the level; the number shown is round(spend ÷ cap × 100), the same here and in the title.',
        b('Raise the cap') + b('Lower the cap') + b('Check now', True),
        railway_slots(fix='billing'),
        'Fix it: <b>Railway\'s billing page</b> (Usage), then <b>Check now</b>. A board action changes our cap only: “raised our cap for Railway”, never “raised at Railway”.',
        f'<dl class="dl"><dt>Computed</dt><dd>round({sc("3.85")} ÷ 5.00 × 100) = {sc(str(pct))} → band 75 → Warning</dd>'
        '<dt>Messages</dt><dd>none (a warning)</dd><dt>Rule</dt><dd>02 BL; §4 row 39; group G row 128</dd></dl>')
    return railway_card(light('a', 'On · Warning (Near its cap)'), spend=sp, thread_html=th, fault='a',
                        z3={'spend': f'<b>{sc("$3.85")} of $5.00 ({sc(str(pct)+"%")})</b>, past its 75% warning', 'Spend_cls': 'hl'})


# ---------- (c6) a switch left off by a closed trip: §0 CS-3 None, row 7 ----------
STILL_OFF_WORDS = ('Off by the system (left by a closed trip): left off by W3\'s trip of Nov 20 (closed Nov 21). '
                   'No one has switched it on or off by hand, so it waits for you.')


def card_switch_left():
    more = f"""<details class="grp" open style="margin-top:.5rem"><summary><b>W3 Sources and the place store</b> <span class="cnt a">2 switches still off</span> <span class="why">opened by itself: a switch is still off</span></summary>
<div class="in">
<div class="row hl"><div><div class="nm">Read a source (W3)</div>{light('h', 'Off by the system (left by a closed trip)')}</div><div class="sub">{STILL_OFF_WORDS}</div><div class="btns">{b('Switch on', True)}</div></div>
<div class="row hl"><div><div class="nm">Common Crawl (W3)</div>{light('h', 'Off by the system (left by a closed trip)')}</div><div class="sub">{STILL_OFF_WORDS}</div><div class="btns">{b('Switch on', True)}</div></div>
<div class="sub">The same two switches, with the same Switch on, are on W3's page (<a href="https://traversence.com/admin/config/control/wf-W3#cb-steps">https://traversence.com/admin/config/control/wf-W3#cb-steps</a>), on the closed trip's row, and in Paused now → Needs attention.</div>
</div></details>"""
    z2 = {'traversence': {'state': light('g', 'On · Working') + ' <span class="cnt a">2 switches still off</span>', 'more': more}}
    return railway_card(light('g', 'On · Working') + ' <span class="cnt a">Needs attention: 2 switches still off</span>', z2=z2)
