# QuickBooks' card (step 3): a Service carrying the payments duty. Static mockups; not site code.
import kit
from brave import *  # every earlier page is generated on import

QB = 'QuickBooks (Intuit)'
QBO = 'https://qbo.intuit.com/'
QDEV = 'https://developer.intuit.com/app/developer/dashboard'
QTIP = 'Written by a Claude session from Intuit\'s own pages'

kit.SETUP['quickbooks'] = dict(
    provider=QB, notice='Setup capture: Sign in not recorded · Billing: why not recorded',
    keys=[('QB_ENV', '.env', 'production, sandbox or mock; the mode set on E-Commerce → Payments wins once set'),
          ('QB_CLIENT_ID, QB_CLIENT_SECRET', '.env', 'the Intuit developer app (production); a live payment key: never on staging'),
          ('QB_SANDBOX_CLIENT_ID, QB_SANDBOX_CLIENT_SECRET', '.env', 'the sandbox app, for test orders and staging'),
          ('QB_WEBHOOK_TOKEN (and its sandbox twin)', '.env', 'the verifier token that signs QuickBooks\' webhooks'),
          ('QB_REDIRECT_URI', '.env (optional)', 'where Intuit sends the admin back after connecting'),
          ('QB_INCOME_ACCOUNT_ID', '.env (optional)', 'the income account for our item'),
          ('The connection\'s tokens', 'the database (qb_connection), sealed', 'the access and refresh tokens from connecting; never in .env')],
    keys_note='Names only. On staging a live payment key (QB_CLIENT_ID, QB_CLIENT_SECRET, or QB_ENV=production) keeps payments off (decisions/0165 §1; StagingGate::liveKeys, r3f).',
    slots=[('Billing', 'Not registered', ''), ('Keys', 'Keys · Developer app · Documented · release #212', None),
           ('Console', 'Console · QuickBooks · Documented · release #212', None), ('Sign in', 'Not registered', '')])


def qb_slots(fix=None):
    def lk(ty, head, url, f):
        btn = {'keys': 'API keys', 'console': 'console'}[ty]
        h = '<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if f else ''
        return (f'<div class="slot{" fix" if f else ""}">{h}<div class="ty">{head}</div><a class="pl" href="{url}" target="_blank" rel="noopener">Open {QB} {btn} ↗</a>'
                f'<span class="src" title="{QTIP}">Documented · release #212</span></div>')
    return ('<div class="slots">' + slot('billing', QB, fix=fix == 'billing') + lk('keys', 'Keys · Developer app', QDEV, fix == 'keys')
            + lk('console', 'Console · QuickBooks', QBO, fix == 'console') + slot('login', QB, fix=fix == 'login') + '</div>')


QB_RADIUS = """<details><summary>If QuickBooks can't be reached: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Our orders' payments: new invoices and their pay links, card charges, payments recorded</li><li>Checkout for our own orders shows “opens soon”</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>“Wire or check”, until Stripe exists as its own stage-1 module</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Marketplace checkout: businesses' own Stripe and Square</li><li>Orders already paid; codes already issued</li><li>Pages, search, sign-in; everything else</li></ul></div></div>
<div class="sub">A missed webhook only delays, never loses, an order: the buyer's page asks QuickBooks, and “Check QuickBooks” on Orders catches any it missed.</div></details>"""

STAGE_QB = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Reconnect · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_QBT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Reconnect · Acknowledge</span>'
GUARD = ('<span class="spend"><span><b>No test moves real money</b></span><span class="why">a test order (made in the sandbox, “TEST · …”, or by a test account) never goes through live QuickBooks: '
         '<code>QuickBooksPay::guardReal()</code> before every charge, invoice and payment; <code>testHere()</code> runs test accounts and staging in the sandbox (decisions/0145)</span></span>')
FEES = ('<span class="spend"><span>Our subscription is paid to Intuit; each card payment carries a processing fee</span><b>fees not read yet</b>'
        f'<a href="{QBO}" target="_blank" rel="noopener">Console · QuickBooks ↗</a>'
        '<span class="why">reading them joins the step-4 ledger export (our own account\'s data, no cost)</span>'
        '<span class="why">Billing: No billing page registered for QuickBooks (Intuit) yet.</span></span>')


def qb_zone1(state, stage):
    return zone(1, 'Identity and role', f"""<div class="idl"><a class="name" href="#">{QB}</a><span class="badge role">Service</span>
<span class="badge crit">Critical: payments</span>{state}</div>
<div class="stage">{stage}</div>{setup_notice('quickbooks')}<div class="stage">{FEES}</div><div class="stage">{GUARD}</div>{QB_RADIUS}{setup_details('quickbooks')}""")


def qb_z2(over=None):
    over = over or {}
    return rows([
        ('Our orders\' payments', 'the need “Our orders\' payments”: QuickBooks, else wire or check', over.get('pay', light('g', 'On · Working')),
         'A checkout becomes a QuickBooks invoice (Automated Sales Tax adds the tax); the buyer pays on QuickBooks\' own payment page; no card number touches our server. '
         'Card charges, refunds and disputes too. A create runs once per request id, so a retry never makes a second invoice.', over.get('pay_btn', ''), over.get('pay_cls', '')),
        ('Plans and renewals', '<code>billing_tick</code> (critical: payments)', over.get('renew', light('g', 'On · Working')), 'Renewals invoice and charge through it.', '', over.get('renew_cls', '')),
        ('Its webhooks', '<code>/api/qb-webhook.php</code>, signed with the verifier token', over.get('wh', light('g', 'On · Working')),
         'Payments, invoice changes, refunds and credit memos. An unsigned one is refused (401). A missed one only delays: the buyer\'s page asks, and Check QuickBooks catches it.', '', over.get('wh_cls', '')),
        ('The mode', 'production, sandbox or mock (E-Commerce → Payments; QB_ENV until set)', over.get('mode', light('n', 'not read yet')), 'Staging and test accounts always run in the sandbox.', '', ''),
    ])


def qb_z3(over=None):
    over = over or {}
    return rows([
        ('Its token', 'read locally every 5 minutes; a real check (the company\'s info) at most once an hour, held on staging (<code>ControlSensor::probePayments</code>)', '',
         over.get('tok', 'not read yet: “Connected; its token is good until <date>” when it is') + '<div class="sub">A warning 14 days before the refresh token runs out, as GitHub\'s tokens (Intuit\'s refresh lifetime: inf, a step-4 check).</div>', '', over.get('tok_cls', '')),
        ('Checkout\'s paths', 'site:payments: visitors\' server errors on checkout and QuickBooks\' paths (critical: payments)', '', over.get('paths', 'no alarm open in the 3:19 AM MST snapshot'), '', over.get('paths_cls', '')),
        ('Webhooks', 'signed or refused, logged', '', over.get('wh', 'not read yet'), '', ''),
        ('Staging\'s keys', 'no live payment key on staging (<code>StagingGate::liveKeys</code>)', '', over.get('stg', 'not read yet'), '', ''),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


QB_Z4 = act_rows(b('Check now') + b('Reconnect') + '<span class="why">the owner, on E-Commerce → Payments</span>' + b('Take out'), QB, qb_slots(),
                 then='After Console → <b>Check now</b>; after Keys (the developer app) → <b>Reconnect</b>, then its check.')


def qb_card(state, thread_html=None, z2o=None, z3o=None, fault=None, stage=STAGE_QB):
    z1 = qb_zone1(state, stage)
    a = zone(2, 'Capabilities and feeds: what uses it', qb_z2(z2o))
    s = zone(3, 'Sensors and key readiness', qb_z3(z3o))
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + a + s
    else:
        body = z1 + a + s + zone(4, 'Actions and Fix it', QB_Z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="{QB}">{body}</article>'


HELD_QB = ('<ul><li>Our orders\' payments: new invoices and pay links, card charges, recorded payments; checkout for our orders shows “opens soon”</li>'
           '<li>Renewals (<code>billing_tick</code>) wait</li></ul>')
OB_QB = '<ul><li>“Wire or check” for our orders, until Stripe exists as its own stage-1 module</li></ul>'
UNAFF_QB = '<ul><li>Marketplace checkout (businesses\' Stripe and Square)</li><li>Paid orders and issued codes</li><li>Pages, search, sign-in</li></ul>'
UPQ = '<span class="why">its actions are in the thread above ↑</span>'

PROV_QB = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'quickbooks\' (purpose, plan, links QuickBooks and Developer app, staging “sandbox”); <code>QuickBooksPay.php</code> '
    '(the .env names L9–10, <code>testHere()</code> L92, <code>guardReal()</code> L115, <code>guardStaging()</code> L128, <code>access()</code> and <code>markReconnect()</code>: invalid_grant → “QuickBooks needs reconnecting”, '
    '<code>call()</code>: a 401 gets a fresh token once, <code>httpRetry()</code>: one retry after 2 s on 429 or 5xx, request ids); <code>api/qb-webhook.php</code>; <code>ControlSensor::probePayments()</code>; '
    '<code>ControlRegister::CRITICAL</code> (svc:quickbooks, job:billing_tick and site:payments → payments); <code>Preflight::NEEDS</code> (payments: QuickBooks, then “wire or check”; Stripe planned); '
    '<code>StagingGate::LIVE_PAY_KEYS</code>; <code>tools/control-board-tests/sensor.php</code> and <code>r3f.php</code>.',
    'The 10:19 UTC snapshot (<code>alarms.json</code>) has no QuickBooks or payments alarm open. Its token\'s date, the mode and the webhooks\' log weren\'t read.',
    'The slots: release #212 carried <code>service-defs.php</code>; the mirror\'s earliest copy has both links. QuickBooks (the app) → Console, Developer app → Keys (04 §5 rulings).',
    'Words: 02 rows 24–26, 69, 70, 87, 88, 90, 106, 128–131; §4.8; §7. Times in MST. No .env was opened; no call was made to QuickBooks.',
]


def qb_token():
    th = thread('Checkout can\'t take cards: QuickBooks needs reconnecting',
                light('r', 'Paused by the system') + f' since {sc("6:02 AM MST")}: its refresh token was refused (invalid_grant).',
                HELD_QB, OB_QB, UNAFF_QB,
                'A refused token can\'t be fixed by trying: it waits for the owner\'s Reconnect. Critical (payments), so the Founder is told at once, in quiet hours too.',
                b('Reconnect', True) + '<span class="why">the owner, on E-Commerce → Payments: Intuit\'s sign-in, then back here</span>' + b('Acknowledge'),
                qb_slots(fix='keys'),
                f'Fix it: <b>Its key</b> on the card (Reconnect). At Intuit: <b>{QB}\'s API keys page</b> (the developer app), registered, if the app\'s keys changed. '
                'It closes as “Fixed by <name>: reconnected QuickBooks. Its check passed: <proof>.”',
                f'<dl class="dl"><dt>Evidence</dt><dd>the token call answered 400 <code>invalid_grant</code> at {sc("6:02 AM MST")}</dd><dt>Ids</dt><dd>T-#### · IR-####</dd>'
                '<dt>Told</dt><dd>the Founder at once (Critical): Pulse, email, push</dd><dt>Rule</dt><dd>02 rows 70, 106, 129; P8</dd></dl>'
                + today_words(['Code: “QuickBooks needs reconnecting (Admin → E-Commerce → Payments).” (obs); the sensor: “QuickBooks\' connection ran out or was refused: it needs reconnecting.” (obs, a trip at once).',
                               'Board today: “SYSTEM ALARM NOTICE: Checkout and payments: Paused by the system”, closing as “Recovered: … the system fixed it” after a person reconnected (sim, g3 C12).',
                               'New: “Checkout can\'t take cards: QuickBooks needs reconnecting · Paused by the system.”, closing as “Fixed by <name>: reconnected QuickBooks.”']), at=QB)
    return qb_card(light('r', 'On · Paused by the system: needs reconnecting'), th, fault='r', stage=STAGE_QBT,
                   z2o={'pay': light('r', 'Paused by the system'), 'pay_cls': 'hr', 'pay_btn': UPQ, 'renew': light('h', 'Held (waiting on QuickBooks)'), 'renew_cls': 'hl'},
                   z3o={'tok': f'<b>Refused (invalid_grant)</b> at {sc("6:02 AM MST")}: it needs reconnecting', 'tok_cls': 'hr'})


def qb_down():
    th = thread('Checkout and payments: QuickBooks isn\'t answering',
                light('r', 'Paused by the system (our orders\' payments)') + f': still trying, {sc("2 of 3")}, next at {sc("1:53 PM MST")}. It answered 503 at {sc("1:33 PM MST")}; since {sc("1:18 PM MST")}.',
                f'<ul><li>Order {sc("#1042")}\'s card charge waits: nothing is charged twice (its request id makes a create run once)</li><li>New invoices and pay links</li></ul>', OB_QB, UNAFF_QB,
                'A critical part trips at once while the system keeps trying (P8): each call already retries once after 2 seconds; the system checks at about 5, 15 and 35 minutes. '
                'Its tries running out sends the Escalation Notice once.',
                b('Check and start again', True) + b('Acknowledge'),
                qb_slots(fix='console'),
                f'Their trouble: no Fix it. <b>{QB}\'s console</b> (QuickBooks), to see its state, then <b>Check and start again</b> (rows 130, 131).',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Told</dt><dd>the Founder at once: Pulse (Critical), email, push</dd>'
                '<dt>The charge</dt><dd>waits, then goes once QuickBooks answers; the buyer sees it pending, never charged twice</dd><dt>Rule</dt><dd>02 rows 24–26, 69, 87, 88, 90; P8</dd></dl>'
                + today_words(['Code: “QuickBooks (503): …” after one retry (obs, <code>httpRetry()</code>); the sensor: “QuickBooks didn\'t answer: …” (obs).',
                               'Board today for a critical part: “Tripped at once … The system is still trying”, with P/E/Pu “…: Paused by the system” though nothing is paused (sim, g4 row 4).',
                               'New: “Checkout and payments: QuickBooks isn\'t answering · Paused by the system (our orders\' payments): still trying, 2 of 3, next at <time>.” (words follow the state: a charge is held; Jason\'s ruling, 03 §12.6a)']), at=QB)
    return qb_card(light('r', 'On · Paused by the system (our orders\' payments): still trying'), th, fault='r', stage=STAGE_QBT,
                   z2o={'pay': light('r', 'Down: charges wait'), 'pay_cls': 'hr', 'pay_btn': UPQ},
                   z3o={'tok': f'Connected; <b>its check answered 503</b> at {sc("1:33 PM MST")}', 'tok_cls': 'hr', 'paths': f'<b>{sc("3")} server errors on checkout in 5 minutes</b>', 'paths_cls': 'hr'})


def qb_guard():
    th = thread(f'Order {sc("#1057")} is a test order: it never goes through live QuickBooks. No real money moved.',
                light('n', 'Notice') + f' · {sc("10:41 AM MST")}: its invoice was stopped before it reached the live gateway. No message.',
                'nothing: the guard working', 'none', '<ul><li>Everything: live payments go on</li></ul>',
                'The guard, not a fault: a test order (made in the sandbox, “TEST · …”, or by a test account) never reaches live QuickBooks. Run it through the sandbox instead. No message is sent.',
                b('Switch E-Commerce → Payments to Sandbox') + '<span class="why">to run the test order through</span>',
                qb_slots(),
                'No Fix it: nothing is wrong.',
                '<dl class="dl"><dt>Why</dt><dd>its buyer is a test account (or it was made in the sandbox, or it\'s a “TEST · …” copy): <code>guardReal()</code>, called before every charge, invoice and payment</dd>'
                '<dt>Refunds</dt><dd>refunds and credits only send money back, so a test copy that once took real money can still be refunded</dd>'
                '<dt>Rule</dt><dd>CLAUDE.md “No test moves real money”; decisions/0145; 02 row 70a: a Notice on QuickBooks\' card only, no message</dd></dl>'
                + today_words(['Code: “Order #… is a test order: it never goes through live QuickBooks, so no real money moves. Switch E-Commerce → Payments to Sandbox to run it through.” (obs, <code>guardReal()</code>), shown to the person who pressed.']), at=QB)
    return qb_card(light('g', 'On · Working') + ' <span class="cnt">Notice: a test order stopped</span>', th,
                   z2o={'pay': light('g', 'On · Working') + ' <span class="why">the guard stopped a test order</span>'})


add('quickbooks-a-working.html', 'QuickBooks card, Working', 'QuickBooks, a Service carrying the payments duty, with its no-real-money guard.',
    intro('<b>(a) QuickBooks\' card, Working.</b> A Service that carries the critical payments duty (with renewals and checkout\'s paths). '
          'No QuickBooks or payments alarm was open in the 3:19 AM MST snapshot; its token\'s date and mode weren\'t read here.') + qb_card(light('g', 'On · Working')) + prov(PROV_QB))
add('quickbooks-b-token.html', 'QuickBooks card: needs reconnecting', 'The refresh token refused: payments paused until the owner reconnects.',
    intro('<b>(b) The token expired or its refresh refused (row 70).</b> Critical: a trip at once, told in quiet hours too; only the owner\'s Reconnect fixes it.') + qb_token() + prov(PROV_QB))
add('quickbooks-c-down.html', 'QuickBooks card: not answering', 'QuickBooks 5xx while a charge waits: a critical trip while the system tries.',
    intro('<b>(c) QuickBooks down or answering 5xx while a charge waits (rows 24–26, 69, 87).</b> Critical: a trip at once while the system keeps trying; the charge waits and is never made twice.') + qb_down() + prov(PROV_QB))
add('quickbooks-d-test-guard.html', 'QuickBooks: a test order stopped', 'The no-real-money guard stops a test order before the live gateway: a Notice.',
    intro('<b>(d) A test order stopped by the guard.</b> “No test moves real money” (decisions/0145): <code>guardReal()</code> stops it before the live gateway.') + qb_guard() + prov(PROV_QB))
build_index()
