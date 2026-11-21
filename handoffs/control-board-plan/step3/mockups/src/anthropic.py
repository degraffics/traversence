# Anthropic's card (step 3), built with the same kit. Static mockups; not site code.
import kit
from bluehost import *  # Railway, GitHub and Bluehost pages are generated on import

PROV = 'Anthropic (Claude)'
CON = 'https://console.anthropic.com/'
USAGE = 'https://console.anthropic.com/usage'
LIMITS = 'https://console.anthropic.com/settings/limits'
KEYS = 'https://console.anthropic.com/settings/keys'
TIP = 'Written by a Claude session from Anthropic\'s own pages'

kit.SETUP['anthropic'] = dict(
    provider=PROV,
    notice='Setup capture: Sign in not recorded',
    keys=[('ANTHROPIC_API_KEY', '.env', 'every Claude call; its check is one authenticated call'),
          ('ANTHROPIC_BASE_URL', '.env (optional)', 'another address for the Messages API; unset, it\'s api.anthropic.com'),
          ('AI_MONTHLY_CAP_USD', '.env (retired)', 'the cap\'s old place: read only when the database can\'t be; the cap is now the setting ai_month_usd')],
    keys_note='Settings, not keys (System Configuration, Thresholds): <code>ai_month_usd</code>, <code>guide_ondemand_share</code>, <code>ai_free_asks</code>, <code>ai_cover</code>, <code>po_claude_prose</code>. Names only, never values.',
    slots=[('Billing', 'Billing · Usage, and Limits · Documented · release #212', None),
           ('Keys', 'Keys · Documented · release #212', None),
           ('Console', 'Console · Documented · release #212', None),
           ('Sign in', 'Not registered', '')])


def tag():
    return f'<span class="src" title="{TIP}">Documented · release #212</span>'


def an_slots(fix=None):
    def box(ty, head, inner, fx):
        f = '<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if fx else ''
        return f'<div class="slot{" fix" if fx else ""}">{f}<div class="ty">{head}</div>{inner}</div>'
    billing = box('billing', 'Billing · Usage',
                  f'<a class="pl" href="{USAGE}" target="_blank" rel="noopener">Open {PROV} billing ↗</a>{tag()}'
                  f'<div class="ty" style="margin-top:.35rem">Billing · Limits</div><a class="pl" href="{LIMITS}" target="_blank" rel="noopener">Open {PROV} limits ↗</a>{tag()}',
                  fix == 'billing')
    keys = box('keys', 'Keys', f'<a class="pl" href="{KEYS}" target="_blank" rel="noopener">Open {PROV} API keys ↗</a>{tag()}', fix == 'keys')
    console = box('console', 'Console', f'<a class="pl" href="{CON}" target="_blank" rel="noopener">Open {PROV} console ↗</a>{tag()}', fix == 'console')
    return '<div class="slots">' + billing + keys + console + slot('login', PROV, fix=fix == 'login') + '</div>'


AN_RADIUS = """<details><summary>If Claude can't be called at all: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Every Claude call: guide research and drafts, names, fixes, heritage, topics, live events, Find it, place descriptions, Write it for me, AI Assist</li></ul></div>
<div class="o"><b>On a backup</b>none (Gemini reads pages first already; there's no backup for Claude's writing)</div>
<div class="u"><b>Unaffected</b><ul><li>Pages, search, sign-in, payments</li><li>The crawler's gathering; journeys built by rules from what we hold</li><li>Guides already written</li></ul></div></div></details>"""

STAGE_AN = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause our calls · Raise or lower our cap · '
            'Take out (stage 6) · Deactivate (stage 7)</span>')
STAGE_AN_TRIP = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · '
                 'Take out (stage 6)</span>')


def chip(spend, cap, real=True, warn=False):
    pct = round(spend / cap * 100)
    s = f'${spend:,.2f}'
    p = f'{pct}%'
    if not real:
        s, p = sc(s), sc(p)
    when = ' <span class="why">(the live reading, Nov 21, 3:19 AM MST)</span>' if real else ' <span class="why">(illustrative)</span>'
    return (f'<span class="spend{" warn" if warn else ""}"><span>Claude (AI):</span><b>{s} of ${cap:,.2f} ({p})</b>{when}'
            f'<span class="why">Every call counted, failed calls included</span>'
            f'<a href="{USAGE}" target="_blank" rel="noopener">Billing · Usage ↗</a></span>')


def an_zone1(state_light, spend_html, stage=STAGE_AN):
    return zone(1, 'Identity and role', f"""
<div class="idl"><a class="name" href="#">{PROV}</a>
<span class="badge role">Service</span><span class="badge crit">Not critical: no duty stops without it</span>
{state_light}</div>
<div class="stage">{stage}</div>
{setup_notice('anthropic')}
<div class="stage">{spend_html}</div>{AN_RADIUS}{setup_details('anthropic')}""")


# The features that call Claude: AiClient::FEATURES (defaults) with CrawlService::FEATURE_WF (their workflows).
FEATURES = [
    ('W4', 'Research briefs', 'claude-sonnet-5-5', 5, True), ('W4', 'Guide drafts (asked for)', 'claude-sonnet-5-5', 20, True),
    ('W4', 'Guide drafts on their own', 'claude-sonnet-5-5', 3, True), ('W4', 'Names in guides', 'claude-haiku-5-5', 100, True),
    ('W4', 'Guide fixes before a rewrite', 'claude-haiku-5-5', 40, True), ('W4', 'Check my facts', 'claude-haiku-5-5', 30, True),
    ('W5', 'Guides on demand', 'claude-sonnet-5-5', 40, True), ('W5', 'Journey writing', None, None, 'coming'),
    ('W6', 'Heritage facts', 'claude-haiku-5-5', 10, True), ('W6', 'Topic summaries', 'claude-haiku-5-5', 20, True),
    ('W6', 'AI assist on a topic', 'claude-sonnet-5-5', 30, True), ('W6', 'Topic research', 'claude-sonnet-5-5', 3, True),
    ('W8', 'Names in journeys and stories', 'claude-haiku-5-5', 100, True), ('W8', 'Find it', 'claude-sonnet-5-5', 30, True),
    ('W9', 'Place descriptions', 'claude-haiku-5-5', 60, True), ('W10', 'Live events', 'claude-sonnet-5-5', 10, True),
    ('the site', 'Write it for me (catalog)', 'claude-haiku-5-5', 200, True),
    ('W1', 'Settle a find (second tier)', 'claude-sonnet-5-5', 20, False), ('W3', 'Source terms (second tier)', 'claude-haiku-5-5', 20, False),
    ('W3', 'Read a public page into facts (Step 2)', 'claude-haiku-5-5', 200, False), ('W3', 'Read a page through Tavily (second tier)', 'claude-haiku-5-5', 30, False),
    ('W3', 'Read a scanned PDF (second tier)', 'claude-haiku-5-5', 10, False), ('the site', 'The vector index (semantic search)', 'claude-haiku-5-5', 1000, False),
]


def an_features(over=None):
    over = over or {}
    groups = {}
    for wf, name, model, per, on in FEATURES:
        groups.setdefault(wf, []).append((name, model, per, on))
    out = ''
    for wf, items in groups.items():
        o = over.get(wf, {})
        cls = 'row' + (' ' + o['cls'] if o.get('cls') else '')
        li = ''.join((f'<li>{n} · <i>coming</i> (0147 §5 decided it; not built)</li>' if on == 'coming' else f'<li>{n} · <code>{m}</code> · {p} a day{"" if on else " · <i>off by default</i>"}</li>') for n, m, p, on in items)
        st = o.get('state', light('g', 'On · Working'))
        out += f"""<div class="{cls}"><div><div class="nm">{wf}</div>{st}</div><div><ul style="margin:0;padding-left:1.05rem;font-size:13px">{li}</ul>{o.get('more', '')}</div><div class="btns">{o.get('btn', '')}</div></div>"""
    rules = ('<div class="sub" style="margin-top:.5rem"><b>Its rules:</b> the AI monthly cap (<code>ai_month_usd</code>, $40.00 in the live reading) stops every feature for the month; '
             'Guides on demand may spend up to <b>half the day\'s allowance</b> (the cap ÷ the days in the month; <code>guide_ondemand_share</code>, 50 by default), and a build that would '
             'pass it <b>queues for tomorrow</b>, most-searched first; a build never stops part way (0147 §5). Each feature also has its own runs a day and budget (AI use). '
             'Every call\'s cost is counted, a failed call after a paid answer included (0160 §5, D16). Defaults from the code; the live settings per feature: not read yet.</div>')
    return '<div class="rows">' + out + '</div>' + rules


def an_sensors(over=None):
    over = over or {}
    rows = [
        ('The month\'s spend', 'the sum of every call\'s cost this month (<code>ai_jobs.cost_micros</code>), against the cap', over.get('spend', '$27.05 of $40.00 (68%), the live reading at 3:19 AM MST')),
        ('Today\'s on-demand spend', 'Guides on demand against half the day\'s allowance ($40.00 ÷ 30 = $1.33; half: $0.67)', over.get('od', 'not read yet')),
        ('Its key', '<code>ANTHROPIC_API_KEY</code> in .env: one authenticated call (02 §2.7)', over.get('key', 'not read yet')),
        ('Its answers', 'refused key (401, 403), out of credit (400 “credit”), busy (429, 529, 5xx)', over.get('ans', 'not read yet')),
    ]
    s = ''
    for n, what, val in rows:
        cls = 'row' + (' ' + over[n + '_cls'] if over.get(n + '_cls') else '')
        s += f'<div class="{cls}"><div class="nm">{n}</div><div><div class="sub">{what}</div><div>{val}</div></div><div class="btns"></div></div>'
    return f'<div class="rows">{s}</div><div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


AN_Z4 = act_rows(b('Check now') + b('Pause our calls') + '<span class="why">automatic calls only</span>' + b('Raise the cap') + b('Lower the cap') + b('Take out'), PROV, an_slots(),
                 then='After Billing → <b>Check now</b>; after Keys → <b>Replace key</b>, then its check. Only the board\'s check changes state.')


def an_card(state_light, spend_html, thread_html=None, feats=None, sens=None, fault=None, stage=STAGE_AN):
    z1 = an_zone1(state_light, spend_html, stage)
    z2 = zone(2, 'Capabilities and feeds: what uses it', an_features(feats))
    z3 = zone(3, 'Sensors and key readiness', an_sensors(sens))
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + z2 + z3
    else:
        body = z1 + z2 + z3 + zone(4, 'Actions and Fix it', AN_Z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="{PROV}">{body}</article>'


def today_words(lines):
    return ('<div class="sub" style="margin-top:.4rem;border-left:3px solid var(--field-edge);padding-left:.5rem"><b>Today\'s words, beside the new:</b> '
            + ' '.join(lines) + '</div>')


PROV_AN = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'anthropic\' (purpose, plan, links Console, Usage, Keys, Limits, key, caps); '
    '<code>AiClient.php</code> (<code>FEATURES</code>, <code>capUsd()</code> L67–74, <code>allow()</code> L160–180, <code>shareLeft()</code>, the call\'s answers L383–386, the paid failure kept with its cost); '
    '<code>OnDemand.php</code> <code>allowance()</code> L127–137; <code>Thresholds.php</code> (ai_month_usd default $40, guide_ondemand_share 50); <code>CrawlService::FEATURE_WF</code>; '
    '<code>ControlRegister::CRITICAL</code> (no Anthropic entry: not critical).',
    'The live reading: <code>site-audit/results/alarms.json</code> at 10:19 UTC (3:19 AM MST): <code>ops:cap:anthropic</code>, a warning since Nov 19, 12:20 AM MST: '
    '$27.05 spent of a $40.00 cap. Shown everywhere by BL: round(27.05 ÷ 40.00 × 100) = 68%.',
    'The slots: release #212 carried <code>service-defs.php</code> and the mirror\'s earliest copy (<code>86c278d</code>) has all four links. Limits is a second billing page (04 §5 ruling).',
    'Decisions 0131, 0147 §5 and 0160 §5. Words: 02 rows 27, 29, 38–41, 106, 128–129; §4.8. Times in MST, labelled. No .env was opened; no call was made to Anthropic.',
]


def an_working():
    return an_card(light('g', 'On · Working'), chip(12.40, 40.00, real=False))


def card_near_cap():
    th = thread('Claude (AI): $27.05 of $40.00 (68%)',
                light('a', 'Warning (Near its cap)') + ' · $27.05 of $40.00 (68%), past its 50% warning. Since Nov 19, 12:20 AM MST.',
                'nothing', 'none', '<ul><li>Every feature runs; each keeps its own runs a day and budget</li></ul>',
                'Reads the month\'s spend against the cap at each watch. The band reached (50, 75, 95) decides the level; the number shown is round(spend ÷ cap × 100), the same in the title and here.',
                b('Raise the cap') + b('Lower the cap') + b('Pause our calls') + '<span class="why">automatic calls only; a person\'s press still goes through under the monthly cap</span>' + b('Check now', True),
                an_slots(fix='billing'),
                f'Fix it: <b>{PROV}\'s billing page</b> (Usage, and Limits for the spend limit at Anthropic), then <b>Check now</b>. '
                'A board action changes our cap only: “raised our cap for Claude (AI)”, never “raised at Anthropic”.',
                '<dl class="dl"><dt>Computed</dt><dd>round(27.05 ÷ 40.00 × 100) = 68 → band 50 → Warning</dd><dt>Messages</dt><dd>none (a warning)</dd>'
                '<dt>Rule</dt><dd>02 BL; row 39 (its own example); group G row 128</dd></dl>'
                + today_words(['Board title “Anthropic (Claude) at 50%” (the band reached), reading “67%”, means “Claude (AI): $27.05 of $40.00 (67%)” (obs, the live alarm).',
                               'New: one title and one figure, “Claude (AI): $27.05 of $40.00 (68%)”.']), at=PROV)
    return an_card(light('a', 'On · Warning (Near its cap)'), chip(27.05, 40.00, warn=True), th, fault='a',
                   sens={'spend': '<b>$27.05 of $40.00 (68%)</b>, past its 50% warning', "The month's spend_cls": 'hl'})


def card_ondemand_share():
    th = thread('New builds wait until midnight (the on-demand half is used); the queue carries on',
                light('n', 'Notice') + f' · {sc("$0.67")} of $0.67 today, half the day\'s AI allowance. It lifts by itself at midnight.',
                '<ul><li>New on-demand guide builds (W5) wait in the queue until midnight, most-searched first</li></ul>', 'none',
                '<ul><li>Every other Claude feature: the other half of the day\'s allowance, and its own budgets</li><li>A build already started finishes</li>'
                '<li>A waiting place still gets its free work at once: the crawler gathers, journeys are built by rules, shown without Claude\'s words</li></ul>',
                'A Notice: the rule working, not a fault. No message is sent, and nothing needs a person. It lifts by itself at midnight.',
                b('Check now'),
                an_slots(),
                'No Fix it: nothing is wrong. To let more through today, raise the share (<code>guide_ondemand_share</code>) or the monthly cap, on our system.',
                '<dl class="dl"><dt>Computed</dt><dd>the day\'s allowance $40.00 ÷ 30 days = $1.33; half = $0.67 (inf: from the cap in the live reading and the default share)</dd>'
                '<dt>Rule</dt><dd>decisions/0147 §5; <code>OnDemand::allowance()</code>; a Notice (02 §3c), the session manager\'s ruling on 03 §15.6 item 1</dd></dl>'
                + today_words(['The place\'s page: “Claude\'s write-up is queued for tomorrow” (0147).']), at=PROV)
    return an_card(light('a', 'On · Warning (Near its cap)') + ' <span class="cnt">Notice: on-demand builds wait until midnight</span>', chip(27.05, 40.00, warn=True), th,
                   feats={'W5': {'state': light('n', 'Notice: new builds wait until midnight'), 'more': f'<div class="sub">{sc("4")} places queued, most-searched first.</div>'}},
                   sens={'od': f'<b>{sc("$0.67")} of $0.67</b>: today\'s half is used'})


def card_month_cap():
    th = thread(f'Claude (AI): {sc("$40.00")} of $40.00 ({sc("100%")})',
                light('r', 'Paused by the system') + ' until our cap is raised or the month turns.',
                '<ul><li>Every Claude call, a person\'s press included: nothing passes at the cap (Jason, 2026-11-21)</li></ul>', 'none',
                '<ul><li>The queue is kept: what waits runs on the 1st, most-searched first</li>'
                '<li>Pages, search, the crawler, journeys built by rules</li></ul>',
                'Nothing more is spent this month, and nothing passes, unless the owner raises the cap. On the 1st its check finds the new month and closes this as Recovered.',
                b('Raise the cap', True) + '<span class="why">the owner\'s, logged: the one way past the cap</span>' + b('Check now'),
                an_slots(fix='billing'),
                f'Fix it: <b>our cap</b> (Raise the cap, the owner\'s, logged). At Anthropic: <b>{PROV}\'s billing page</b>, to see the account\'s own limit; it doesn\'t raise ours.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Told</dt><dd>the Founder: Pulse, email, push</dd><dt>Rule</dt><dd>02 rows 40 (Jason, 2026-11-21), 41, 109; the cap $40 (0163 §7)</dd></dl>'
                + today_words(['Code: “The month\'s AI cap ($40.00) is reached: it starts again on the 1st.” (obs, <code>AiClient::allow()</code>), refusing a person\'s asks too.',
                               'New (02 row 40): “Paused by the system until our cap is raised or the month turns.”: nothing passes, a person\'s press included; Raise the cap is the owner\'s way past it.']), at=PROV)
    return an_card(light('r', 'On · Paused by the system (our cap)'), chip(40.00, 40.00, real=False, warn=True), th, fault='r', stage=STAGE_AN_TRIP,
                   sens={'spend': f'<b>{sc("$40.00")} of $40.00 ({sc("100%")})</b>', "The month's spend_cls": 'hr'})


def card_key():
    th = thread('Claude (AI) refused our key',
                light('r', 'Paused by the system') + f': calls with ANTHROPIC_API_KEY wait for a new key. Since {sc("11:42 AM MST")}.',
                '<ul><li>Every call with <code>ANTHROPIC_API_KEY</code>: all Claude features</li></ul>', 'none',
                '<ul><li>Pages, search, sign-in, payments</li><li>The crawler\'s gathering; journeys built by rules</li><li>Guides already written</li></ul>',
                'A refused key gets no tries: it waits for a person (row 29). Its check runs past its own hold once the key is replaced (row 106).',
                b('Replace key', True) + b('Acknowledge'),
                an_slots(fix='keys'),
                f'Fix it: <b>{PROV}\'s API keys page</b>, registered (Documented · release #212), then <b>Replace key</b> and its check: one authenticated call.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Evidence</dt><dd>401 from api.anthropic.com</dd><dt>Told</dt><dd>the Founder: Pulse, email, push</dd>'
                '<dt>Rule</dt><dd>02 rows 29, 106, 129; §4.8 row 29</dd></dl>'
                + today_words(['Code: “Claude refused the key: check ANTHROPIC_API_KEY.” (obs, <code>AiClient.php</code>).',
                               'Board today: “api.anthropic.com: Our key was refused (the site, on Bluehost)” with “Nothing could be paused from here (it only trips)” (sim, g4 row 9a).',
                               'New: “Claude (AI) refused our key · Paused by the system: calls with ANTHROPIC_API_KEY wait for a new key.”']), at=PROV)
    feats = {wf: {'state': light('h', 'Held (waiting on its key)'), 'cls': 'hl'} for wf in ('W4', 'W5', 'W6', 'W8', 'W9', 'W10', 'the site')}
    return an_card(light('r', 'On · Paused by the system (its key)'), chip(27.05, 40.00, warn=True), th, feats=feats, fault='r', stage=STAGE_AN_TRIP,
                   sens={'key': f'<b>Refused (401)</b> at {sc("11:42 AM MST")}', 'Its key_cls': 'hr'})


add('anthropic-a-working.html', 'Anthropic card, Working', 'Anthropic\'s four-zone card, Working: what uses Claude, its cap and its rules.',
    intro(f'<b>(a) {PROV}\'s card, Working.</b> A Service, not critical. Every feature that calls Claude, by workflow, from the code. The spend here is '
          '<b>illustrative</b> (dotted): today\'s real reading is past its 50% warning (page b).') + an_working() + prov(PROV_AN))
add('anthropic-b-near-cap.html', 'Anthropic card: near the cap', 'Today\'s real reading: $27.05 of $40.00 (68%), one figure in the title and body.',
    intro('<b>(b) A warning near the cap (row 39): today\'s real reading.</b> $27.05 of $40.00, shown as 68% everywhere (BL). Today\'s words, kept in Details for the pairing, are the ones being replaced.') + card_near_cap() + prov(PROV_AN))
add('anthropic-c-at-cap.html', 'Anthropic card: at the cap', 'Today\'s on-demand half used (builds wait, the queue carries on), then the monthly cap reached.',
    intro('<b>(c) At the cap.</b> First, today\'s half of the allowance for on-demand guides is used: new builds wait for tomorrow and the queue carries on, the rest of Claude\'s work '
          'goes on (0147 §5). Second, the monthly cap reached (row 40): automatic calls are paused until the cap is raised or the month turns. Amounts are illustrative (dotted).')
    + card_ondemand_share() + card_month_cap() + prov(PROV_AN))
add('anthropic-d-key-refused.html', 'Anthropic card: key refused', 'A refused key: every Claude call waits; Fix it goes to the Keys page.',
    intro('<b>(d) A refused key (rows 29, 106, 129).</b> Fix it lands on Anthropic\'s Keys page, which is registered; then Replace key.') + card_key() + prov(PROV_AN))
build_index()
