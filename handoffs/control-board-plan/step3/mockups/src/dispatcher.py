# The Communications Dispatcher's card (step 3): a Service module. Channels: email, Pulse and push only. Static mockups.
import kit
from quickbooks import *  # every earlier page is generated on import

CD = 'The Communications Dispatcher'
AWSP = 'Amazon Web Services (SES)'
ATIP = 'Written by a Claude session from Amazon\'s own pages'
AWS_BILL = 'https://console.aws.amazon.com/billing/home'
AWS_IAM = 'https://us-east-1.console.aws.amazon.com/iam/home#/users'
AWS_SES = 'https://console.aws.amazon.com/ses/home#/get-set-up'
COMMS = 'https://traversence.com/admin/config/communications'

kit.SETUP['dispatcher'] = dict(
    provider=AWSP, notice='Setup capture: Sign in not recorded',
    keys=[('SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD', '.env', 'Bluehost\'s mail server: system email, first'),
          ('SMTP_FROM_EMAIL, SMTP_FROM_NAME, SMTP_REPLY_TO', '.env', 'who system email is from'),
          ('<SENDER>_SMTP_USERNAME, <SENDER>_SMTP_PASSWORD', '.env', 'a sender\'s own mailbox, where it has one'),
          ('SES_MODE, SES_REGION (else AWS_REGION)', '.env', 'Amazon SES: off, mock or live'),
          ('AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY (or SES_PERSONAL_KEY_ID, SES_PERSONAL_SECRET)', '.env', 'SES\'s personal account: notices, digests, the backup for system email'),
          ('SES_BULK_KEY_ID, SES_BULK_SECRET', '.env', 'SES\'s bulk account, when bulk mail is built'),
          ('SES_MONTHLY_CAP_USD, SES_USD_PER_1000, EMAIL_LINK_KEY', '.env', 'SES\'s cap and price; unsubscribe links'),
          ('VAPID_PRIVATE_PEM_B64, VAPID_PUBLIC_B64U', '.env (write-only)', 'web push: our own keys'),
          ('COMMS_SEAL_KEY', '.env (write-only)', 'seals the mailboxes\' passwords'),
          ('Mailboxes\' passwords and Microsoft\'s tokens', 'the database, sealed', 'read and answered in Pulse; never in .env')],
    keys_note='Names only. Channels are email, Pulse and push: no SMS, no webhooks (Jason\'s correction to the report).',
    slots=[('Billing', 'Billing · Documented · release #212', None), ('Keys', 'Keys · The site\'s key (IAM) · Documented · release #212', None),
           ('Console', 'Console · Documented · release #212 (its address filled in by the site)', None), ('Sign in', 'Not registered', '')])


def cd_slots(fix=None):
    def lk(ty, head, url, f, note=''):
        btn = {'billing': 'billing', 'keys': 'API keys', 'console': 'console'}[ty]
        h = '<div class="ty" style="color:var(--copper)">Fix it lands here</div>' if f else ''
        return (f'<div class="slot{" fix" if f else ""}">{h}<div class="ty">{head}</div><a class="pl" href="{url}" target="_blank" rel="noopener">Open {AWSP} {btn} ↗</a>'
                f'<span class="src" title="{ATIP}">Documented · release #212</span>{note}</div>')
    return ('<div class="slots">' + lk('billing', 'Billing', AWS_BILL, fix == 'billing') + lk('keys', 'Keys · The site\'s key (IAM)', AWS_IAM, fix == 'keys')
            + lk('console', 'Console', '#aws-console', fix == 'console', '<div class="why">its address is filled in by the site from the account (<code>{console}</code>), not read here</div>')
            + slot('login', AWSP, fix=fix == 'login') + '</div>')


CD_RADIUS = f"""<details><summary>Its Incident Radius, by fault</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>SES down: notices and the weekly digest by email, and system email's backup</li><li>Push keys refused: every push</li><li>The dispatcher itself failing (its database): every message, on every channel</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>SES down: system email (sign-in codes, alarms) still goes by Bluehost\'s SMTP, first anyway; Pulse and push carry the rest</li><li>Push refused: Pulse and email carry it</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Pulse, always: every message lands there</li><li>The site\'s pages and work</li></ul></div></div>
<div class="sub"><b>Who watches the watcher:</b> when the dispatcher can\'t tell anyone, the outside heartbeat on GitHub (Bluehost\'s card, 03 §14.6a) emails &lt;the address set as ALARM_TO&gt; (a GitHub secret, never hard-coded; unset, its run fails visibly on GitHub) through SES with its own sending-only secret.</div></details>"""

STAGE_CD = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Send test dispatch · Pause a channel · Take out (stage 6)</span>'
STAGE_CDT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Send test dispatch · Acknowledge</span>'
SPEND_CD = ('<span class="spend"><span>Bluehost\'s mail and our own push keys: free</span><span>SES: about $0.10 per 1,000 emails, at its cap <code>SES_MONTHLY_CAP_USD</code></span>'
            '<b>use not read yet</b></span>')
CRIT = ('<div class="note" style="border-color:var(--amber)"><b>What the code says, against the spec:</b> <code>ControlRegister::CRITICAL</code> has <b>no dispatcher part</b> '
        '(nor any Notify part in <code>processes</code>); the procedures duty names <code>svc:aws</code> (SES, “system email”). 02 §7 and the report call the dispatcher critical '
        '(procedures). They differ: the card shows the spec, “Critical: procedures (by the spec; not in the register today)”.</div>')


def cd_zone1(state, stage):
    return zone(1, 'Identity and role', f"""<div class="idl"><a class="name" href="#">{CD}</a><span class="badge role">Service</span>
<span class="badge crit">Critical: procedures (by the spec; not in the register today)</span>{state}</div>
<div class="stage">{stage}</div>{setup_notice('dispatcher')}<div class="stage">{SPEND_CD}</div>
<div class="stage"><span class="spend"><b>Channels: email, Pulse, push</b><span class="why">no SMS, no webhooks</span></span></div>{CD_RADIUS}{setup_details('dispatcher')}""")


def cd_z2(over=None):
    over = over or {}
    return rows([
        ('Pulse', 'every message lands here (<code>comms_messages</code>); the inbox', over.get('pulse', light('g', 'On · Working')),
         'One record per recipient, routed by their choices along one route (created → addressed → held → sent → delivered → read). One thread per alarm: an alarm\'s message is updated where it is (<code>IN_PLACE</code>).', '', over.get('pulse_cls', '')),
        ('Email', 'Bluehost\'s SMTP first, then Amazon SES the backup (<code>Mailer</code>); SES also sends notices and the weekly digest (<code>SesMail</code>)', over.get('email', light('g', 'On · Working')),
         'Quiet hours hold Notice, General and Special; Critical, Urgent and Alert go anyway. One email per conversation while it\'s unread. A bounce or complaint stops all mail to that address (SES\'s suppression list). '
         f'Its SES page: <a href="{AWS_SES}" target="_blank" rel="noopener">SES ↗</a> (not a portal slot). SES is a part of <a href="aws-a-working.html">Amazon Web Services\' card</a>.', over.get('email_btn', b('Check now')), over.get('email_cls', '')),
        ('Push', 'web push with our own VAPID keys (<code>WebPush</code>, RFC 8291 and 8292)', over.get('push', light('g', 'On · Working')),
         'On by default for Alert and up, once a person has a device. A pace per person: past 3 pushes in 5 minutes the rest fold into one summary; Critical always goes on its own. '
         'One tag per thread, alerting again only on a rise or Critical; Recovered replaces it quietly.', over.get('push_btn', b('Check now')), over.get('push_cls', '')),
        ('Mailboxes (receiving)', 'read and answered in Pulse (<code>Mailboxes</code>): cPanel, IMAP, POP, Gmail, Microsoft (Graph), SES inbound', light('g', 'On · Working'),
         'Every 5 minutes, and at once when the inbox opens; passwords sealed.', '', ''),
        ('Fallback between channels', 'a message that fails on one channel goes by the next, logged (02 row 81)', over.get('fb', light('n', 'Spec: not built')),
         '<b>Today:</b> none across channels (root cause 6; g4 §c): email\'s only fallback is SMTP → SES; when both fail, the failure goes to Pulse as <code>mail.failure</code>, '
         'which reaches the overseers by the same failing email. <b>Spec:</b> “Fell back to push (email failed: <answer>)” on the message.', '', over.get('fb_cls', '')),
        ('Message control', 'pause a kind, a channel or a sender (<code>comms_controls</code>)', light('g', 'On · Working'),
         '<b>Today:</b> a paused channel holds Critical too (g4 row 15). <b>Spec (row 82):</b> Critical is sent anyway; the row says “email paused for everything but Critical”.', '', ''),
    ])


def cd_z3(over=None):
    over = over or {}
    return rows([
        ('The route', 'each message\'s stages (<code>comms_route</code>); each sender\'s last sent and failed, 24 h counts', '', over.get('route', 'not read yet'), '', over.get('route_cls', '')),
        ('Queue depth', 'held now, and the oldest held (<i>spec</i>)', '', over.get('q', 'not built'), '', ''),
        ('Bounces and complaints', 'SES\'s rates, read at no cost; a warning at AWS\'s review levels, 5% bounce and 0.1% complaint (inf: a step-4 check against AWS\'s docs). Today an address is stopped, no rate shown', '', over.get('bounce', 'not built'), '', over.get('bounce_cls', '')),
        ('Push keys', '<code>VAPID_*</code> present and accepted (<i>spec</i>); dead devices (<code>push_subs.failed_at</code>)', '', over.get('vapid', 'not read yet'), '', over.get('vapid_cls', '')),
        ('Reached no one', 'a trip with no active receiver (<i>spec</i>, row 80); today nothing is sent and nothing logged (g4 row 14)', '', over.get('none', 'not built'), '', over.get('none_cls', '')),
        ('Founders without a device', 'push can\'t reach them (<i>spec</i>)', '', 'not read yet', '', ''),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


CD_Z4 = act_rows(b('Send test dispatch') + '<span class="why">to the owner only</span>'
                 + f'<a class="btn" href="{COMMS}#cs-routing">Open the routing matrix</a><span class="why">who gets what, by role and level (today email only; Pulse and push in step 4, as 0172 decided)</span>'
                 + b('Fix it', off='nothing open') + b('Check now'), AWSP, cd_slots(),
                 then=f'Fix it routes: a sender failing → <a href="{COMMS}#cs-controls">{COMMS}#cs-controls</a> and its sender\'s card; push keys → Its key. After Keys → Replace key, then its check.')


def cd_card(state, thread_html=None, z2o=None, z3o=None, fault=None, stage=STAGE_CD):
    z1 = cd_zone1(state, stage)
    a = zone(2, 'Capabilities and feeds: the channel adapters', cd_z2(z2o))
    s = zone(3, 'Sensors and readiness', cd_z3(z3o))
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + a + s
    else:
        body = z1 + a + s + zone(4, 'Actions and Fix it', CD_Z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="{CD}">{body}</article>'


UPD = '<span class="why">its actions are in the thread above ↑</span>'
PROV_CD = [
    'The live code, mirror <code>aeb5f31</code>: <code>Notify.php</code> (the one dispatcher, decisions/0172 §4: <code>CHANNELS</code> pulse, email, push; quiet hours; <code>IN_PLACE</code>; <code>PUSH_PACE</code> 3 in <code>PUSH_WINDOW</code> 5 minutes; renotify; never throws); '
    '<code>Mailer.php</code> (Bluehost\'s SMTP first, then SES, “the backup”); <code>SesMail.php</code> (accounts, the suppression list, the cap and price); <code>WebPush.php</code> (VAPID keys); <code>Mailboxes.php</code>; '
    '<code>ControlRegister.php</code> (no dispatcher part; <code>svc:aws</code> → procedures); <code>service-defs.php</code> \'aws\' (links).',
    'Decisions 0172 and 0176 (one thread per alarm, one shape on every surface); step 2\'s g4 §c (the dispatcher to the contract) and root cause 6; the tests <code>tools/notify-tests/</code> and <code>tools/dispatcher-tests/</code>.',
    'The slots: AWS\'s Billing, The site\'s key (IAM) → Keys, and Console (its address <code>{console}</code> filled in by the site), each Documented · release #212; “SES” and “S3” aren\'t slots.',
    'Words: 02 rows 80–82, 29, 106; §7; §4.8. Times in MST. No .env was opened; no message was sent.',
]


def cd_email():
    th = thread('Email isn\'t sending (SMTP 535, SES refused) · Alarms go by Pulse and push.',
                light('r', 'Paused by the system (email)') + f': still trying, {sc("1 of 3")}, next at {sc("4:25 PM MST")}. Bluehost refused the sign-in (535) and SES refused the send at {sc("4:05 PM MST")}.',
                '<ul><li>Email of every kind</li></ul>', '<ul><li>Pulse and push carry every message; each email that failed says “Fell back to push (email failed: SMTP 535, SES refused)”</li></ul>',
                '<ul><li>Pulse, push</li><li>The site\'s pages and work</li></ul>',
                'The dispatcher\'s own channel part: a warning, then a trip after 3 tries. A Critical message still reaches the Founder by push.',
                b('Check and start again', True) + b('Send test dispatch') + '<span class="why">to the owner only</span>',
                cd_slots(fix='console'),
                f'Fix it: the sender\'s card on <a href="{COMMS}#cs-controls">{COMMS}#cs-controls</a> (its mailbox password), and <b>{AWSP}\'s console</b> for SES\'s side (its sending status). '
                'Then Check and start again sends one test through each.',
                f'<dl class="dl"><dt>Bounces</dt><dd>SES\'s bounce and complaint rate: <i>spec</i>; today a bounced address is stopped, no rate is shown</dd><dt>Ids</dt><dd>T-#### · IR-#### at the trip</dd><dt>Rule</dt><dd>02 rows 81, 82; §7</dd></dl>'
                + today_words(['Today: email\'s only fallback is SMTP → SES; when both fail, a <code>mail.failure</code> message in Pulse, which reaches the overseers by the same failing email (inf, g4 §c). No board alarm; nothing falls back to push.',
                               'New: “Email isn\'t sending (SMTP 535, SES refused) · Alarms go by Pulse and push.”']), at=AWSP)
    return cd_card(light('r', 'On · Paused by the system (email): still trying'), th, fault='r', stage=STAGE_CDT,
                   z2o={'email': light('r', 'Paused by the system'), 'email_cls': 'hr', 'email_btn': UPD, 'fb': light('g', 'On: messages fall back to push'), 'fb_cls': 'hl'},
                   z3o={'route': f'<b>{sc("7")} failed</b> since {sc("4:05 PM MST")}; each fell back to push', 'route_cls': 'hr'})


def cd_push():
    th = thread('Push isn\'t sending (VAPID keys refused) · Alarms go by Pulse and email.',
                light('r', 'Paused by the system (push)') + f' since {sc("9:30 AM MST")}: the push services refused our keys (403).',
                '<ul><li>Every push</li></ul>', '<ul><li>Pulse and email carry every message; each says “Fell back to email (push refused: 403)”</li></ul>',
                '<ul><li>Pulse, email</li><li>The site\'s pages and work</li></ul>',
                'A refused key gets no tries: it waits for a person (rows 29, 81). A Critical message reaches the Founder by email at once, in quiet hours too.',
                b('Replace key', True) + '<span class="why">VAPID keys, write-only in .env, through Its key; every device subscribes again</span>' + b('Send test dispatch') + '<span class="why">to the owner only</span>',
                cd_slots(),
                'Fix it: <b>Its key</b> on the card (the VAPID keys are ours, not a provider\'s, so no portal page applies).',
                f'<dl class="dl"><dt>Evidence</dt><dd>403 from the push services at {sc("9:30 AM MST")}</dd><dt>Rule</dt><dd>02 rows 29, 81, 106</dd></dl>'
                + today_words(['Today: the push is <code>skipped</code> or <code>failed</code> on its route; nothing tells anyone (g4 §c, extra M).',
                               'New: “Push isn\'t sending (VAPID keys refused) · Alarms go by Pulse and email.”']), at=AWSP)
    return cd_card(light('r', 'On · Paused by the system (push)'), th, fault='r', stage=STAGE_CDT,
                   z2o={'push': light('r', 'Paused by the system'), 'push_cls': 'hr', 'push_btn': UPD, 'fb': light('g', 'On: messages fall back to email'), 'fb_cls': 'hl'},
                   z3o={'vapid': f'<b>Refused (403)</b> at {sc("9:30 AM MST")}', 'vapid_cls': 'hr'})


def cd_nobody():
    th = thread(f'{CD}: a trip reached no one (no active founder)',
                light('r', 'Down') + f' since {sc("3:14 AM MST")}: the trip “The crawler is down” (T-####) had no one to receive it.',
                '<ul><li>Every alarm\'s message: no active person can receive it</li></ul>', 'none',
                '<ul><li>The board shows every trip, whoever is told</li><li>The site\'s work</li></ul>',
                'A critical trip at once on the dispatcher (row 80). The trip\'s row says “Reached no one: no active founder”, logged. Nothing held: the alarms themselves stand on the board.',
                f'<a class="btn f" href="{COMMS}#cs-routing">Open the routing matrix</a>' + b('Acknowledge'),
                cd_slots(),
                f'Fix it: <b>the routing matrix</b> (<a href="{COMMS}#cs-routing">{COMMS}#cs-routing</a>): make sure a founder is active, with email and a device. '
                'With no active founder, the outside heartbeat emails &lt;the address set as ALARM_TO&gt; (a GitHub secret, never hard-coded; unset, its run fails visibly on GitHub).',
                '<dl class="dl"><dt>Who watches the watcher</dt><dd>this alarm can reach no one through the dispatcher by definition; it stands on the board, and the outside heartbeat (GitHub, every 15–30 min) emails &lt;the address set as ALARM_TO&gt; from outside (03 §14.6a, §20.6)</dd>'
                '<dt>Rule</dt><dd>02 rows 80, 53 (the outside heartbeat); P11 (a trip always reaches someone; if it can\'t, the dispatcher\'s own fault is raised and shown)</dd></dl>'
                + today_words(['Today: nothing is sent and nothing is logged (g4 row 14, extra N).',
                               f'New: “{CD}: a trip reached no one (no active founder) · Down.”']), at=AWSP)
    return cd_card(light('r', 'On · Down: a trip reached no one'), th, fault='r', stage=STAGE_CDT,
                   z3o={'none': f'<b>{sc("1")} trip reached no one</b> at {sc("3:14 AM MST")}: no active founder', 'none_cls': 'hr'})


add('dispatcher-a-working.html', 'Dispatcher card, Working', 'The Communications Dispatcher, a Service: email, Pulse and push, with its readiness and rules.',
    intro('<b>(a) The Communications Dispatcher\'s card, Working.</b> A Service module, built from decisions/0172 and 0176 and the live code. Channels: email, Pulse and push only. '
          'Its readings aren\'t read here (“not read yet”); the sensors the code lacks are marked <i>spec</i>.') + CRIT + cd_card(light('g', 'On · Working')) + prov(PROV_CD))
add('dispatcher-b-email.html', 'Dispatcher card: email failing', 'Bluehost\'s SMTP and SES both refuse: email paused, alarms go by Pulse and push.',
    intro('<b>(b) Email failing: SMTP refused, then SES refused (row 81).</b> The spec\'s fallback between channels: each message goes by push instead, said on the message. Today there is no such fallback.') + cd_email() + prov(PROV_CD))
add('dispatcher-c-push.html', 'Dispatcher card: push keys refused', 'The push services refuse our VAPID keys: push paused, alarms go by Pulse and email.',
    intro('<b>(c) Push keys refused (rows 29, 81).</b> Every push is held; Pulse and email carry them. Today nothing tells anyone.') + cd_push() + prov(PROV_CD))
add('dispatcher-d-nobody.html', 'Dispatcher card: a trip reached no one', 'A trip with no one to receive it: a critical trip on the dispatcher, shown and logged.',
    intro('<b>(d) A trip with nobody to receive it (row 80).</b> g4 found it\'s sent nowhere today, with no record. The spec raises it on the dispatcher itself.') + cd_nobody() + prov(PROV_CD))
build_index()
