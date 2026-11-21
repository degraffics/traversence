# Tavily's card (step 3): a Source, web search for the crawler and research. Static mockups; not site code.
import kit
from google import *  # every earlier page is generated on import

TV = 'Tavily'
DASH = 'https://app.tavily.com/'
TTIP = 'Written by a Claude session from Tavily\'s own pages'

kit.SETUP['tavily'] = dict(
    provider=TV, notice='Setup capture: Sign in not recorded · Billing, Keys: why not recorded',
    keys=[('TAVILY_API_KEY', 'Railway (the crawler service)', 'every search and page read; the worker reports it by name, never its value'),
          ('TAVILY_USD_PER_CREDIT', '.env', 'a credit\'s price in the ledger (0.008 by default)'),
          ('WEBSEARCH_USD_PER_SEARCH', '.env (its old name)', 'read when the new name isn\'t set'),
          ('SEARCH_MONTHLY', 'Railway (retired)', 'the month\'s searches: now the setting websearch_monthly'),
          ('WEBSEARCH_STOP_PCT', '.env (retired)', 'now the setting websearch_stop_pct')],
    keys_note='Settings, not keys: <code>worker_searches_per_run</code>, <code>websearch_monthly</code>, <code>websearch_stop_pct</code>, <code>paid_spread</code>. Names only.',
    slots=[('Billing', 'Not registered', ''), ('Keys', 'Not registered', ''), ('Console', 'Console · Dashboard · Documented · release #212', None), ('Sign in', 'Not registered', '')])


def tv_slots(fix=None):
    f = fix == 'console'
    head = '<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if f else ''
    console = (f'<div class="slot{" fix" if f else ""}">{head}<div class="ty">Console · Dashboard</div><a class="pl" href="{DASH}" target="_blank" rel="noopener">Open {TV} console ↗</a>'
               f'<span class="src" title="{TTIP}">Documented · release #212</span></div>')
    return '<div class="slots">' + slot('billing', TV, fix=fix == 'billing') + slot('keys', TV, fix=fix == 'keys') + console + slot('login', TV, fix=fix == 'login') + '</div>'


TV_RADIUS = """<details><summary>If Tavily can't be called: its Incident Radius (a Source: reroutes on the source map)</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Tavily's calls: searches and its page reads (Read a page through Tavily, off by default: those pages wait “to be read”)</li></ul></div>
<div class="o"><b>On a backup: Brave Search</b><ul><li>Web search (W8, W10–W12)</li><li>A missing phone or website (W2), after our open-data copies</li><li>Confirm a business (W1, W2), after our copies, CMS and the Arizona Corporation Commission</li></ul>
<div class="why">Each, while Brave Search works; with neither, web search waits (“queued”) and the rest wait or go to the person queue.</div></div>
<div class="u"><b>Unaffected</b><ul><li>The crawler's other work; every other source</li><li>Pages, search, sign-in, payments</li></ul></div></div></details>"""

STAGE_TV = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause our calls · Raise or lower our cap · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_TVT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>'
SPEND_TV = ('<span class="spend"><span>Tavily, this month:</span><b>26 searches · 20.8¢</b>'
            '<span class="why">(the approved report, 2026-11-21; the ledger lives in the database, not the mirror)</span>'
            '<span>of <code>websearch_monthly</code>: not read yet · stops at 90% · paced through the day</span>'
            '<span class="why">failed calls counted</span><span class="why">Billing: No billing page registered for Tavily yet.</span></span>')


def tv_z2(over=None):
    over = over or {}
    return rows([
        ('Web search', 'the crawler on Railway: Tavily first, then Brave Search when Tavily fails or is held', over.get('ws', light('g', 'On · Working')),
         'Feeds <b>Web search (W8, W10–W12)</b>: Tavily, then Brave; <b>A missing phone or website (W2)</b>: our open-data copies, then Tavily, then Brave; '
         '<b>Confirm a business (W1, W2)</b>: our copies, CMS, the Arizona Corporation Commission, then Tavily, then Brave. At most 10 a run, about one a second, '
         'stopping at 90% of the month and paced through the day (defaults; the live settings: not read yet). Each switch to Brave is counted.',
         over.get('ws_btn', b('Check now')), over.get('ws_cls', '')),
        ('Read a page through Tavily', 'Tavily Extract (second tier)', light('n', 'Off by default'),
         'Reads a page the crawler can\'t, within the allowance the site gives; never a page robots.txt forbids.', '', ''),
    ])


def tv_z3(over=None):
    over = over or {}
    return rows([
        ('The month\'s searches', 'the ledger\'s <code>tavily</code> and <code>tavily_extract</code> against <code>websearch_monthly</code>, failed calls counted', '',
         over.get('m', '26 searches, 20.8¢ (the approved report; 26 × $0.008 a credit = 20.8¢, consistent with the code\'s price)'), '', over.get('m_cls', '')),
        ('Switches to Brave', 'the worker\'s <code>search_switches</code>, logged (today dropped, g2 row 27)', '', over.get('sw', 'not read yet'), '', over.get('sw_cls', '')),
        ('Its key on Railway', '<code>TAVILY_API_KEY</code>: the worker\'s key test at its next run (one free call)', '', over.get('k', light('n', 'Not checked yet') + ' <span class="why">the worker reports presence only today</span>'), '', over.get('k_cls', '')),
        ('Its answers', '401 refused key · 432 plan limit · 429 rate · 5xx and timeouts', '', over.get('a', 'not read yet'), '', over.get('a_cls', '')),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


TV_Z4 = act_rows(b('Check now') + b('Pause our calls') + '<span class="why">automatic calls only</span>' + b('Raise the cap') + b('Lower the cap'), TV, tv_slots(),
                 then='After Console → <b>Check now</b>. Keys and Billing aren\'t registered: Publish from your Vault.')


def tv_card(state, thread_html=None, z2o=None, z3o=None, fault=None, stage=STAGE_TV, spend=SPEND_TV):
    return card(TV, 'tavily', 'Not critical: no duty stops without it', state, stage, spend, TV_RADIUS, tv_z2(z2o), tv_z3(z3o), TV_Z4, thread_html, fault, role='Source')


BRAVE_OB = ('<ul><li>Web search (W8, W10–W12): Brave Search</li><li>A missing phone or website (W2): Brave Search</li><li>Confirm a business (W1, W2): Brave Search</li></ul>'
            f'<div class="why">Each, while Brave Search works ({sc("its last search passed")}); a switch is counted.</div>')
UNAFF_TV = '<ul><li>The crawler\'s other work; every other source</li><li>Pages, search, sign-in, payments</li></ul>'
UPT = '<span class="why">its actions are in the thread above ↑</span>'

PROV_TV = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'tavily\' (purpose “The crawler\'s web search”, plan, Dashboard link, key on Railway, caps, ledger tavily and tavily_extract); '
    '<code>Thresholds.php</code> (worker_searches_per_run 10, websearch_monthly 0 by default: “0 stops web search”, websearch_stop_pct 90, paid_spread on); '
    '<code>CrawlService::PRICES</code> (TAVILY_USD_PER_CREDIT 0.008); <code>Preflight::NEEDS</code> (websearch, contact, confirm); <code>ServiceHealth::classify()</code>; '
    '<code>workers/crawler/crawler.py</code> (Tavily, then Brave when Tavily fails or is held; about one search a second; the key reported by name). Not in <code>ControlRegister::CRITICAL</code>.',
    'The month\'s figure, 26 searches and 20.8¢, is from the approved report (2026-11-21). The ledger is in the database, not the mirror, so it can\'t be re-read here; it matches the code\'s price (26 × $0.008).',
    'The slot: release #212 carried <code>service-defs.php</code>; the mirror\'s earliest copy has the Dashboard link. Billing, Keys and Sign in aren\'t registered (02 §8; 04 §5).',
    'Words: 02 rows 24–26, 29, 31, 36, 43, 44, 106, 128–131; §4.8. Times in MST. No .env was opened; no call was made to Tavily.',
]


def tv_plan():
    th = thread(f'{TV} used its month at the provider',
                light('r', 'Paused by the system') + ' until the 1st; its check runs then.',
                '<ul><li>Tavily\'s calls, until its month turns</li></ul>', BRAVE_OB, UNAFF_TV,
                'Tavily answered 432 (its plan\'s limit): a quota with a known reset, the month. Its check runs on the 1st and closes it as Recovered when it passes. '
                'Meanwhile each need uses its next source.',
                b('Check now', True) + b('Acknowledge'),
                tv_slots(fix='billing'),
                f'Fix it: <b>{TV}\'s billing page</b>. No billing page registered for {TV} yet, so Fix it says so and never borrows the Dashboard. '
                'Keep PAYGO and auto-upgrade off in its dashboard (its plan note).',
                f'<dl class="dl"><dt>Evidence</dt><dd>432 from api.tavily.com at {sc("10:14 AM MST")}, reported by the crawler\'s run {sc("#1350")}</dd>'
                '<dt>Classified</dt><dd>by the engine as a quota (P2): <b>today</b> <code>ServiceHealth::classify()</code> returns nothing for a 432 (“not the service\'s”), so the plan limit raises no issue; the worker simply falls to Brave each time</dd>'
                '<dt>Rule</dt><dd>02 rows 36, 37 (a quota with a known reset), 43, 44, 128</dd></dl>'
                + today_words(['Today: no words: a 432 is classified as nothing (obs, <code>ServiceHealth::classify()</code>); the crawler logs “search: brave instead (tavily failed: …)” (obs, <code>crawler.py</code>).']), at=TV)
    return tv_card(light('r', 'On · Paused by the system (its month at Tavily)'), th, fault='r', stage=STAGE_TVT,
                   z2o={'ws': light('a', 'On a backup: Brave Search'), 'ws_cls': 'hl', 'ws_btn': UPT},
                   z3o={'a': f'<b>432</b> (plan limit) at {sc("10:14 AM MST")}', 'a_cls': 'hr', 'sw': f'{sc("14")} searches went to Brave since', 'sw_cls': 'hl'})


def tv_key():
    th = thread(f'{TV} refused our key',
                light('r', 'Paused by the system') + f': calls with TAVILY_API_KEY wait for a new key. Reported by the crawler\'s run {sc("#1351")} at {sc("11:20 AM MST")}.',
                '<ul><li>Calls with <code>TAVILY_API_KEY</code></li></ul>', BRAVE_OB, UNAFF_TV,
                'A refused key gets no tries: it waits for a person (rows 29, 31). Each need goes to Brave Search meanwhile.',
                b('Replace key', True) + '<span class="why">on Railway: the crawler service → Variables; its check is the worker\'s key test at its next run</span>' + b('Acknowledge'),
                tv_slots(fix='keys'),
                f'Fix it: <b>{TV}\'s API keys page</b>. No API keys page registered for {TV} yet, so Fix it says so and never borrows the Dashboard. The key itself lives on Railway.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Evidence</dt><dd>401 from api.tavily.com</dd><dt>Told</dt><dd>the Founder: Pulse, email, push</dd><dt>Rule</dt><dd>02 rows 29, 31, 43, 106, 129</dd></dl>'
                + today_words(['Board today: “api.tavily.com: Our key was refused (the crawler, on Railway)”; its check confirms only that the worker has the key, never that it works (sim, g2 row 2).',
                               f'New: “{TV} refused our key · Paused by the system: calls with TAVILY_API_KEY wait for a new key.”']), at=TV)
    return tv_card(light('r', 'On · Paused by the system (its key)'), th, fault='r', stage=STAGE_TVT,
                   z2o={'ws': light('a', 'On a backup: Brave Search'), 'ws_cls': 'hl', 'ws_btn': UPT},
                   z3o={'k': f'<b>Refused (401)</b>, reported at {sc("11:20 AM MST")}', 'k_cls': 'hr', 'sw': f'{sc("9")} searches went to Brave since', 'sw_cls': 'hl'})


def tv_server():
    th = thread(f'{TV} isn\'t answering',
                light('a', 'Warning (the system is trying)') + f' · Trying again (2 of 3): it answered 502 Bad Gateway at {sc("1:47 PM MST")}. Next check {sc("2:07 PM MST")}.',
                'nothing yet', BRAVE_OB, UNAFF_TV,
                'Each search that fails goes to Brave Search at once (the crawler\'s own failover); the system checks Tavily at about 5, 15 and 35 minutes. '
                'Three failed tries make it a trip; any real 2xx from any caller clears it (row 25).',
                b('Check now', True),
                tv_slots(fix='console'),
                f'Their trouble: no Fix it. <b>{TV}\'s console</b> (Dashboard) to see its health, then <b>Check now</b> (row 130).',
                '<dl class="dl"><dt>Alarm</dt><dd>W-#### · Warning · no Acknowledge (R80)</dd><dt>A burst</dt><dd>failures inside one run are one fault (row 24)</dd>'
                '<dt>Rule</dt><dd>02 rows 24–26, 43, 44, 83, 130</dd></dl>'
                + today_words(['Board today: “api.tavily.com: Its server failed (the crawler, on Railway)”, with the back-off counting the reports\' failures (sim, g2 rows 16–18); the switch to Brave isn\'t logged (g2 row 27).',
                               f'New: “{TV} isn\'t answering · Trying again (2 of 3): it answered 502 Bad Gateway at <time>. Next check <time>.”']), at=TV)
    return tv_card(light('a', 'On · Warning (the system is trying)'), th, fault='a',
                   z2o={'ws': light('a', 'On a backup: Brave Search, while Tavily is checked'), 'ws_cls': 'hl', 'ws_btn': UPT},
                   z3o={'a': f'<b>502</b> at {sc("1:47 PM MST")}; timeouts at {sc("1:42")}', 'a_cls': 'hl', 'sw': f'{sc("6")} searches went to Brave since', 'sw_cls': 'hl'})


add('tavily-a-working.html', 'Tavily card, Working', 'Tavily, a Source: web search for the crawler and research, Brave its backup.',
    intro('<b>(a) Tavily\'s card, Working.</b> A Source: the crawler\'s web search, with Brave Search as the next source on the map. Its month\'s figure is the approved report\'s '
          '(the ledger isn\'t in the mirror). Its key lives on Railway, reported by name only.') + tv_card(light('g', 'On · Working')) + prov(PROV_TV))
add('tavily-b-plan-limit.html', 'Tavily card: its month used', 'A 432 at Tavily: paused until the 1st; each need goes to Brave Search.',
    intro('<b>(b) Its plan\'s limit used at Tavily (a 432).</b> A quota with a known reset (rows 36, 43). Today the code reads a 432 as nothing, so the board says nothing while the worker falls to Brave.') + tv_plan() + prov(PROV_TV))
add('tavily-c-key-refused.html', 'Tavily card: key refused', 'TAVILY_API_KEY refused: Tavily\'s calls wait; Brave carries the searches; Fix it names Keys (not registered).',
    intro('<b>(c) A refused key (rows 29, 31).</b> Each need goes to Brave Search; Fix it lands on Keys, which isn\'t registered, and says so.') + tv_key() + prov(PROV_TV))
add('tavily-d-server.html', 'Tavily card: not answering', 'Tavily\'s 5xx and timeouts: a warning while it tries; each search goes to Brave.',
    intro('<b>(d) A 5xx or timeout (rows 24–26, 43).</b> A warning while the system tries; the searches already go to Brave Search, so nothing waits.') + tv_server() + prov(PROV_TV))
build_index()
