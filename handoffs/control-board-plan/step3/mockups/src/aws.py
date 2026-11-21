# The AWS account's card (step 3): one module, two parts, email (SES) and storage (S3). Static mockups; not site code.
import kit
from dispatcher import *  # every earlier page is generated on import

AW = 'Amazon Web Services'

kit.SETUP['aws'] = dict(
    provider=AW, notice='Setup capture: Sign in not recorded',
    keys=[('AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY', '.env', 'the site\'s one key (IAM): SES, S3 and Textract'),
          ('AWS_REGION', '.env', 'the region it signs for'),
          ('S3_BUCKET', '.env', 'the file store\'s bucket'),
          ('S3_ENDPOINT, S3_ACCESS_KEY_ID, S3_SECRET_ACCESS_KEY, S3_REGION', '.env (optional)', 'Cloudflare R2\'s own key, once the files move there (decisions/0151): then the AWS key stays for SES and Textract'),
          ('SES_MODE, SES_REGION, SES_PERSONAL_KEY_ID, SES_PERSONAL_SECRET, SES_BULK_KEY_ID, SES_BULK_SECRET', '.env', 'SES: its mode and its two accounts (the Dispatcher\'s card lists them too)'),
          ('SES_MONTHLY_CAP_USD, SES_USD_PER_1000', '.env', 'SES\'s cap and price')],
    keys_note='Names only. One key (IAM), one bill.',
    slots=kit.SETUP['dispatcher']['slots'])

AW_RADIUS = f"""<details><summary>Its Incident Radius, per part</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li><b>SES down:</b> notices and the weekly digest by email; inbound mail waits at AWS; bounce reports wait</li>
<li><b>The file store down</b> (while it's on S3): new uploads (the browser sends them straight to the store), video copies, staging's publish of files; stored photos served from the store don\'t show</li>
<li><b>The key refused:</b> both parts, and Textract</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>Alarms and sign-in codes: Bluehost\'s SMTP, first anyway</li><li>Reading menus: Claude, after Google and Amazon</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Pulse and push</li><li>Files still on the server (not moved yet) keep serving</li><li>Pages, search, sign-in, payments</li></ul></div></div>
<div class="sub">S3\'s SlowDown is a rate wait, never a trip: calls wait, then start again by themselves (row 27).</div></details>"""

STAGE_AW = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause a part · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_AWT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>'
SPEND_AW = ('<span class="spend"><span>One bill: SES about $0.10 per 1,000 emails (its cap <code>SES_MONTHLY_CAP_USD</code>, <code>ses_month_usd</code>); S3 $0.023 a GB-month, and about $0.09 a GB downloaded after 100 GB free; Textract per page</span>'
            '<b>use not read yet</b><span class="why">each billing lookup costs about a cent, so it isn\'t read</span></span>')


def aw_zone1(state, stage):
    return zone(1, 'Identity and role', f"""<div class="idl"><a class="name" href="#">{AW}</a><span class="badge role">Service</span>
<span class="badge crit">Critical: procedures, through its email (svc:aws, “system email”)</span>{state}</div>
<div class="stage">{stage}</div>{setup_notice('aws')}<div class="stage">{SPEND_AW}</div>
<div class="stage"><span class="spend"><b>Two parts: email (SES) and storage (S3)</b><span class="why">one key (IAM), one bill</span>
<a href="dispatcher-a-working.html">The Communications Dispatcher\'s card</a><span class="why">(SES is one of its email providers)</span></span></div>{AW_RADIUS}{setup_details('aws')}""")


def aw_z2(over=None):
    over = over or {}
    return rows([
        ('Email: sending (SES)', 'the Dispatcher\'s email backup, and its sender for notices and the weekly digest (<code>SesMail</code>: a personal account; a bulk account when bulk mail is built)', over.get('send', light('g', 'On · Working')),
         'A bounce or complaint stops all mail to that address (the suppression list) and may pause its sender. '
         f'Its page: <a href="{AWS_SES}" target="_blank" rel="noopener">SES ↗</a> (not a portal slot).', over.get('send_btn', b('Check now')), over.get('send_cls', '')),
        ('Email: receiving (SES inbound)', 'mail to an address on our domains, through SNS to <code>/api/ses-inbound.php</code>, into its mailbox\'s Pulse inbox', over.get('recv', light('g', 'On · Working')),
         'Each message\'s signature checked against AWS\'s certificate; each handled once. Bounce and complaint reports come the same way (<code>/api/ses-events.php</code>).', '', over.get('recv_cls', '')),
        ('Storage (S3)', 'every file the site keeps, by owner (<code>Store::KINDS</code>: members, businesses, guests, orders, system)', over.get('s3', light('g', 'On · Working')),
         'Profile, cover and place photos; a business\'s photos and quote files; GO Code logos; orders\' proofs and artwork; the stock library\'s copies; posted videos and their 720p and 1080p copies (the video worker reads and writes through short-lived links). '
         'Uploads go from the browser straight to the store. <b>Until the move to Cloudflare R2 is finished</b> (decisions/0151): whether it\'s moved isn\'t known here (needs live data). '
         'The code keeps no backups there.', over.get('s3_btn', b('Check now')), over.get('s3_cls', '')),
        ('Textract', 'reading tables, forms and scanned PDFs (<code>scan_read</code>; menus after Google)', light('n', 'not read yet'), 'Per page; menus fall to Claude when it can\'t.', '', ''),
    ])


def aw_z3(over=None):
    over = over or {}
    return rows([
        ('SES\'s sending', 'its account\'s sending status, and the bounce and complaint rates (read at no cost; warnings at 5% and 0.1%, inf)', '', over.get('ses', 'not read yet'), '', over.get('ses_cls', '')),
        ('The file store', 'a signed check of the bucket; SlowDown read as a rate wait (<code>ServiceHealth::classify()</code>, tested in <code>feeds-words.php</code>)', '', over.get('s3', 'not read yet'), '', over.get('s3_cls', '')),
        ('Its key', '<code>AWS_ACCESS_KEY_ID</code>: one signed call', '', over.get('key', 'not read yet'), '', over.get('key_cls', '')),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


AW_Z4 = act_rows(b('Check now') + b('Pause a part') + '<span class="why">email or storage, automatic calls only</span>', AW, cd_slots(),
                 then='The same four slots as the Dispatcher\'s. After Keys (IAM) → Replace key, then its check.')


def aw_card(state, thread_html=None, z2o=None, z3o=None, fault=None, stage=STAGE_AW):
    z1 = aw_zone1(state, stage)
    a = zone(2, 'Capabilities and feeds: its two parts', aw_z2(z2o))
    s = zone(3, 'Sensors and key readiness', aw_z3(z3o))
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + a + s
    else:
        body = z1 + a + s + zone(4, 'Actions and Fix it', AW_Z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="{AW}">{body}</article>'


UPA = '<span class="why">its actions are in the thread above ↑</span>'
PROV_AW = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'aws\' (purpose “Email (SES), tables and forms (Textract), and file storage until the move to R2 is finished”, its prices, links, key, cap); '
    '<code>ControlRegister::CRITICAL</code> (<code>svc:aws</code> → procedures, “system email”); <code>SesMail.php</code>; <code>Mailer.php</code> (Bluehost first, SES the backup); <code>api/ses-inbound.php</code>, <code>api/ses-events.php</code>; '
    '<code>Store.php</code> (KINDS and folders), <code>S3.php</code> (presigned uploads, R2\'s own key via <code>S3_ENDPOINT</code>), <code>Aws.php</code>; <code>VideoJobs.php</code>; <code>StagingPublish.php</code>; '
    '<code>ServiceHealth::classify()</code> (SlowDown → rate) and <code>tools/control-board-tests/feeds-words.php</code>; decisions/0151 (files to R2).',
    'Whether the files have moved to R2 (<code>S3_ENDPOINT</code> set) can\'t be read here: needs live data. Its spend isn\'t read: each billing lookup costs about a cent.',
    'The slots: the same as the Dispatcher\'s (AWS\'s Billing, Keys · The site\'s key (IAM), Console), each Documented · release #212; SES and S3 pages aren\'t slots.',
    'Words: 02 rows 27, 28, 29, 38, 81, 106, 128–131; §4.8. Times in MST. No .env was opened; no call was made to AWS.',
]


def aw_ses():
    th = thread('Amazon SES paused our sending',
                light('r', 'Paused by the system (email by SES)') + f' since {sc("8:10 AM MST")}: AWS paused the account\'s sending (its review).',
                '<ul><li>Notices and the weekly digest by email</li><li>System email\'s backup (it has none while this lasts)</li></ul>',
                '<ul><li>Alarms and sign-in codes: Bluehost\'s SMTP, first anyway</li><li>Pulse and push carry every notice</li></ul>',
                '<ul><li>Receiving (SES inbound) and storage</li><li>Pages, search, sign-in, payments</li></ul>',
                'A stop kind: AWS lifts it, not a try. It waits for a person: AWS\'s case, then Check and start again.',
                b('Check and start again', True) + b('Acknowledge'),
                cd_slots(fix='console'),
                f'Fix it: <b>{AWSP}\'s console</b> (SES, its account\'s status and AWS\'s case), then Check and start again. The bounce and complaint rates on the card show why AWS looked.',
                f'<dl class="dl"><dt>Evidence</dt><dd>SES answered that sending is paused for the account at {sc("8:10 AM MST")}</dd><dt>Rates</dt><dd>bounce {sc("6.2%")}, complaint {sc("0.04%")}: past the 5% bounce level (inf)</dd>'
                '<dt>Rule</dt><dd>02 rows 38 (a stop kind that waits for a person), 81; 03 §20.6, 5</dd></dl>'
                + today_words(['Today: a failed SES send is logged on the route; SES\'s rates aren\'t read; nothing on the board (g4 §c).',
                               'New: “Amazon SES paused our sending · Paused by the system (email by SES).”']), at=AWSP)
    return aw_card(light('r', 'On · Paused by the system (email by SES)'), th, fault='r', stage=STAGE_AWT,
                   z2o={'send': light('r', 'Paused by the system'), 'send_cls': 'hr', 'send_btn': UPA},
                   z3o={'ses': f'<b>Sending paused by AWS</b> at {sc("8:10 AM MST")}; bounce {sc("6.2%")}, complaint {sc("0.04%")}', 'ses_cls': 'hr'})


def aw_s3():
    th = thread('Amazon S3 asked us to slow down',
                light('a', 'Warning (a rate wait)') + f' · Calls wait until {sc("2:46 PM MST")}, then start again by themselves.',
                'nothing: a back-off in its wait isn\'t a hold', 'none',
                '<ul><li>Stored files keep serving</li><li>SES; pages, search, sign-in, payments</li></ul>',
                f'S3 answered 503 SlowDown (“Please reduce your request rate”): a rate wait, never a trip. New uploads and video copies wait the few minutes and go on; '
                f'{sc("3")} uploads are waiting now. If calls still fail after the wait, it\'s a server fault (rows 24–26).',
                b('Check now', off='its next try is at the time above'),
                cd_slots(fix='console'),
                f'Their trouble: no Fix it. <b>{AWSP}\'s console</b> (S3) to see its health (row 130).',
                '<dl class="dl"><dt>Classified</dt><dd>503 with SlowDown → a rate wait (<code>ServiceHealth::classify()</code>, tested in <code>feeds-words.php</code>)</dd>'
                '<dt>Rule</dt><dd>02 rows 27, 28, 130; §4.8 row 27</dd></dl>'
                + today_words(['Today, Common Crawl\'s S3: “data.commoncrawl.org: Its server failed (the crawler, on Railway)” with a SlowDown reading, words opened before the fix and never changed (obs, L12).',
                               'New: “Amazon S3 asked us to slow down · Calls wait until <time>, then start again by themselves.”']), at=AWSP)
    return aw_card(light('a', 'On · Warning (a rate wait): storage'), th, fault='a',
                   z2o={'s3': light('a', 'Warning: calls wait, then start again'), 's3_cls': 'hl', 's3_btn': UPA},
                   z3o={'s3': f'<b>503 SlowDown</b> at {sc("2:41 PM MST")}; calls wait until {sc("2:46 PM MST")}', 's3_cls': 'hl'})


def aw_key():
    th = thread(f'{AW} refused our key',
                light('r', 'Paused by the system') + f': calls with AWS_ACCESS_KEY_ID wait for a new key. Since {sc("5:22 PM MST")}.',
                '<ul><li><b>Email by SES:</b> notices and the digest; inbound mail waits at AWS</li><li><b>Storage</b> (while it\'s on S3): new uploads, video copies, stored photos served from the store</li><li>Textract</li></ul>',
                '<ul><li>Alarms and sign-in codes: Bluehost\'s SMTP</li><li>Menus: Claude</li></ul>',
                '<ul><li>Files still on the server; Pulse and push; pages, search, payments</li></ul>',
                'One key, both parts: a refused key gets no tries; it waits for a person (row 29). Once storage is on R2 (its own key), only email and Textract would wait.',
                b('Replace key', True) + b('Acknowledge'),
                cd_slots(fix='keys'),
                f'Fix it: <b>{AWSP}\'s API keys page</b> (The site\'s key, IAM), registered, then Replace key and its check: one signed call to each part.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Told</dt><dd>the Founder (Pulse, push; email by Bluehost)</dd><dt>Rule</dt><dd>02 rows 29, 106, 129</dd></dl>'
                + today_words(['Today: each caller fails on its own (SES\'s route, an upload\'s error, the video worker\'s run); no one alarm names the key.',
                               f'New: “{AW} refused our key · Paused by the system: calls with AWS_ACCESS_KEY_ID wait for a new key.”']), at=AWSP)
    return aw_card(light('r', 'On · Paused by the system (its key)'), th, fault='r', stage=STAGE_AWT,
                   z2o={'send': light('r', 'Paused (its key)'), 'send_cls': 'hr', 'send_btn': UPA, 'recv': light('h', 'Held (waiting on its key)'), 'recv_cls': 'hl',
                        's3': light('r', 'Paused (its key)'), 's3_cls': 'hr', 's3_btn': UPA},
                   z3o={'key': f'<b>Refused (403 InvalidClientTokenId)</b> at {sc("5:22 PM MST")}', 'key_cls': 'hr'})


add('aws-a-working.html', 'AWS account card, Working', 'One AWS module, two parts: email (SES) and storage (S3), one key and one bill.',
    intro('<b>(a) The AWS account\'s card, Working.</b> One module with two parts: email (SES: sending, receiving, the suppression list, the rates) and storage (S3: every file the site keeps, '
          'until the move to Cloudflare R2). One key (IAM), one bill. Its SES carries the “system email” critical duty (<code>svc:aws</code>); the Dispatcher\'s card names it as an email provider.')
    + aw_card(light('g', 'On · Working')) + prov(PROV_AW))
add('aws-b-ses-paused.html', 'AWS card: SES sending paused', 'AWS paused our SES sending: notices and the digest wait; alarms still go by SMTP.',
    intro('<b>(b) SES sending paused by AWS (rows 38, 81).</b> Notices and the digest wait; alarms and sign-in codes still go by Bluehost\'s SMTP; Pulse and push carry the rest.') + aw_ses() + prov(PROV_AW))
add('aws-c-s3-slowdown.html', 'AWS card: S3 asked us to slow down', 'S3\'s SlowDown while uploads wait: a rate wait, never a trip.',
    intro('<b>(c) S3\'s SlowDown while uploads wait (rows 27, 28).</b> A rate wait: uploads and video copies wait a few minutes and go on by themselves.') + aw_s3() + prov(PROV_AW))
add('aws-d-key-refused.html', 'AWS card: key refused', 'The one IAM key refused: both parts and Textract wait; Fix it goes to Keys (IAM).',
    intro('<b>(d) The IAM key refused (rows 29, 106, 129).</b> One key, both parts.') + aw_key() + prov(PROV_AW))
build_index()
