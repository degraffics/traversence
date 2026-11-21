# Bluehost's card (step 3), built with the same kit as Railway's and GitHub's. Static mockups; not site code.
import kit
from github import *  # Railway and GitHub pages are generated on import

ACCOUNT = 'https://my.bluehost.com/'
CP_NOTE = 'https://‹CPANEL_HOST›:2083, built from <code>CPANEL_HOST</code> in .env (value not read)'

kit.SETUP['bluehost'] = dict(
    provider='Bluehost',
    notice='Setup capture: Billing, Keys: why not recorded',
    keys=[('DB_HOST, DB_PORT, DB_CHARSET', '.env', 'the database server'),
          ('DB_DATABASE, DB_USERNAME, DB_PASSWORD', '.env', 'the main database; also read as DB_DATABASE-main and the like'),
          ('DB_ADMIN_DATABASE, DB_ADMIN_USERNAME, DB_ADMIN_PASSWORD', '.env', 'the admin realm'),
          ('DB_LISTINGS_DATABASE, DB_LISTINGS_USERNAME, DB_LISTINGS_PASSWORD', '.env', 'the listings realm'),
          ('DB_USERS_DATABASE, DB_USERS_USERNAME, DB_USERS_PASSWORD', '.env', 'the users realm'),
          ('CPANEL_HOST, CPANEL_USER, CPANEL_TOKEN', '.env', 'cPanel\'s API: cron lines and mailboxes; CPANEL_HOST also builds the Console slot'),
          ('SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD', '.env', 'the site\'s own email'),
          ('SMTP_FROM_EMAIL, SMTP_FROM_NAME, SMTP_REPLY_TO', '.env', 'the site\'s own email: who it\'s from'),
          ('STAFF_EMAIL_DOMAIN', '.env', 'staff mailboxes'),
          ('CRAWLER_API_TOKEN', '.env and Railway', 'the crawler\'s pass into our endpoints'),
          ('VIDEO_WORKER_TOKEN', '.env and Railway', 'the video worker\'s pass into our endpoints'),
          ('HEALTH_TOKEN', '.env and a GitHub secret (they must match)', 'the daily health report'),
          ('BLUEHOST_USD_MONTH', '.env (the setting “Bluehost plan, a month”)', 'the plan\'s price in the monthly total')],
    keys_note='Read from Database.php, CPanel.php, EnvSettings.php and the endpoints; names only, never values. The .env file itself sits above public_html.',
    slots=[('Billing', 'Not registered', ''),
           ('Keys', 'Not registered', ''),
           ('Console', 'Console · cPanel · Site settings (from CPANEL_HOST)', None),
           ('Sign in', 'Sign in · Account · Documented · release #212', None)])
kit.SETUP_MODS = ('railway', 'github', 'bluehost')


def bh_slots(fix=None):
    return ('<div class="slots">' + slot('billing', 'Bluehost', fix=fix == 'billing') + slot('keys', 'Bluehost', fix=fix == 'keys')
            + slot('console', 'Bluehost', 'cPanel', '#cpanel', fix == 'console', src='env', note=CP_NOTE)
            + slot('login', 'Bluehost', 'Account', ACCOUNT, fix == 'login') + '</div>')


BH_RADIUS = """<details><summary>If Bluehost itself is down: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>Everything that runs on Bluehost: pages, search, sign-in, payments, the admin, Pulse and the board, every site job, staging, trvgo.us</li>
<li>Our crawl and video endpoints, so the workers' reports wait</li></ul></div>
<div class="o"><b>On a backup</b>none</div>
<div class="u"><b>Unaffected</b><ul><li>Railway's workers keep running, but can't report</li><li>GitHub: releases wait in the inbox</li><li>The mirror keeps its last copy; OneDrive</li><li>Everything already stored</li></ul></div></div></details>"""

STAGE_BH = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Set them up now · '
            'Run database updates now · Take out (stage 6, <i>spec</i>)</span>')
STAGE_BH_TRIP = ('<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · '
                 'Acknowledge · Take out (stage 6, <i>spec</i>)</span>')
SPEND_BH = ('<span class="spend"><span>A flat hosting plan</span><b>price not read yet</b>'
            '<span class="why">(the setting “Bluehost plan, a month”, <code>BLUEHOST_USD_MONTH</code>)</span>'
            '<span class="why">Billing: No billing page registered for Bluehost yet.</span></span>')

MISMATCH = ('<div class="note" style="border-color:var(--amber)"><b>Observed today (step 2, g3 D6 and F5):</b> the live pre-flight line '
            '<code>svc:bluehost</code> is wired to the <b>Page load</b> duty (<code>ControlRegister.php:89</code>), but what it tests is the site\'s '
            'email sign-in and cPanel\'s cron list (<code>Preflight.php:978–987</code>). A cPanel or email blip trips “Page load” at once, and its title '
            'is the part that passed: “Bluehost: The site\'s email: signed in.” <code>site:env</code> is also wired to Page load while its test '
            'fails on an unwritable <code>.env</code> that still loads pages (<code>Preflight.php:744–747</code>). The card below is drawn as the spec '
            'says: one line per hosted part, each with its own duty.</div>')


def bh_zone1(state_light, stage=STAGE_BH):
    return zone(1, 'Identity and role', f"""
<div class="idl"><a class="name" href="#">Bluehost</a>
<span class="badge role">Host</span><span class="badge crit">Critical: page, database, procedures</span>
{state_light}</div>
<div class="stage">{stage}</div>
{setup_notice('bluehost')}
<div class="stage">{SPEND_BH}</div>{BH_RADIUS}{setup_details('bluehost')}""")


PARTS = [
    dict(k='site', name='The site (pages)', duty='Page load',
         what='traversence.com (<code>website/</code>; <code>app/</code> above the web root), the trvgo.us router (<code>trvgo/</code>), staging.trvgo.us',
         feeds='every visitor duty: pages, search, sign-in, payments; the admin, Pulse and the board',
         read='PHP 8.0.31 (the health report, 3:19 AM MST). The outside heartbeat (<i>spec</i>): not built; the health report answered at 3:19 AM MST (Search probe #63)'),
    dict(k='db', name='The databases', duty='Database connections',
         what='MySQL 5.7.44-48: four realms (main, admin, listings, users), each with its own login',
         feeds='search and listing pages (listings), sign-in (users), the admin and orders (admin), everything else (main)',
         read='414 tables, 630.89 MB (the health report, 3:19 AM MST). One query per realm at each watch (<i>spec</i>; today nightly): not read yet'),
    dict(k='env', name='.env (the site\'s settings)', duty='Page load (readable); Settings and Its key (writable)',
         what='above <code>public_html</code>; names only on the board',
         feeds='every page (read); Settings and Its key (write)', read='readable, writable, each name once: not read yet'),
    dict(k='sched', name='The scheduler (cPanel cron)', duty='System-critical procedures',
         what='50 jobs in <code>Scheduler::JOBS</code>, 40 of them set up by themselves (<code>AUTO</code>), 4 quiet; the board\'s own watch among them',
         feeds='every site job: the watch, releases, renewals, mail, Pulse follow-ups',
         read='the watch\'s last tick: not read yet · <span class="mm">real gap: <code>billing_tick</code> isn\'t in <code>AUTO</code>, so nothing checks its cron line</span>'),
    dict(k='cpanel', name='cPanel\'s API', duty='none (setting up jobs and mailboxes)',
         what='https://‹CPANEL_HOST›:2083, with <code>CPANEL_USER</code> and <code>CPANEL_TOKEN</code>',
         feeds='setting up cron lines and mailboxes; the jobs themselves run on their existing lines', read='its cron list: not read yet'),
    dict(k='mail', name='The site\'s own email (SMTP)', duty='System-critical procedures (through the need “The site\'s own email”)',
         what='Bluehost\'s mail server; its backup is Amazon SES (the need: Bluehost, then AWS)',
         feeds='sign-in links, codes and notices', read='a sign-in without sending (EHLO, AUTH, QUIT): not read yet'),
    dict(k='ep', name='Our endpoints for the workers', duty='none (gathering)',
         what='<code>/api/crawl/*</code> and <code>/api/video/*</code>', feeds='everything Railway\'s services store',
         read='<code>/api/crawl/loads</code>: a warning (Slow) in the 3:19 AM MST snapshot, under the model (02 row 11)'),
]


def bh_parts(over=None):
    over = over or {}
    out = ''
    for r in PARTS:
        o = over.get(r['k'], {})
        cls = 'row' + (' ' + o['cls'] if o.get('cls') else '')
        st = o.get('state', light('g', 'On · Working') if r['k'] != 'ep' else light('a', 'On · Warning (Slow)'))
        out += f"""<div class="{cls}"><div><div class="nm">{r['name']}</div><div class="sub">Duty: {r['duty']}</div>{st}</div>
<div><div>{r['what']}</div><div class="sub">Feeds {r['feeds']}</div><div class="sub">{o.get('read', r['read'])}</div>{o.get('more', '')}</div>
<div class="btns">{o.get('btn', b('Check now'))}</div></div>"""
    return '<div class="rows">' + out + '</div>'


def bh_sensors(over=None):
    over = over or {}
    rows = [
        ('Outside heartbeat', 'GitHub loads the public home page, with no token, every 15–30 min, so it measures “pages load” (02 §2.7, O3; <i>spec</i>; whether <code>/api/health.php</code> needs <code>HEALTH_TOKEN</code> is a step-4 check, <i>inf</i>)', over.get('hb', 'not built; today only the daily health report: it answered at 3:19 AM MST')),
        ('Each realm', 'one query per realm at each watch (<i>spec</i>)', over.get('db', 'not read yet')),
        ('The watch and cron', 'a tick within 10 minutes; each critical job\'s cron line', over.get('cron', 'not read yet · <code>billing_tick</code>\'s line is never checked today')),
        ('cPanel\'s API', 'its cron list', over.get('cpanel', 'not read yet')),
        ('Site email', 'a sign-in without sending', over.get('mail', 'not read yet')),
        ('.env', 'readable, writable, each name once', over.get('env', 'not read yet')),
        ('Bot check', 'Bluehost\'s <code>humans_</code> 409 on a worker endpoint, as a refusal (<i>spec</i>)', over.get('bot', 'not built')),
    ]
    s = ''
    for n, what, val in rows:
        cls = 'row' + (' ' + over[n + '_cls'] if over.get(n + '_cls') else '')
        s += f'<div class="{cls}"><div class="nm">{n}</div><div><div class="sub">{what}</div><div>{val}</div></div><div class="btns"></div></div>'
    return f'<div class="rows">{s}</div><div class="sub" style="margin-top:.4rem">Its keys, by name and place: Setup capture (zone 1).</div>'


BH_Z4 = act_rows(b('Check now') + b('Set them up now') + b('Run database updates now') + b('Take out', off='spec: a module can\'t be taken out today (g3 M5)'),
                 'Bluehost', bh_slots(), then='After Console → <b>Check now</b>. Only the board\'s check changes state.')


def bh_card(state_light, thread_html=None, parts=None, sens=None, fault=None, stage=STAGE_BH):
    z1 = bh_zone1(state_light, stage)
    z2 = zone(2, 'Capabilities and feeds: what it hosts', bh_parts(parts))
    z3 = zone(3, 'Sensors and key readiness', bh_sensors(sens))
    if thread_html:
        body = z1 + zone(4, 'Actions and Fix it: <span style="text-transform:none;letter-spacing:0">where the problem is reported</span>', thread_html) + z2 + z3
    else:
        body = z1 + z2 + z3 + zone(4, 'Actions and Fix it', BH_Z4)
    return f'<article class="card{" fault-" + fault if fault else ""}" aria-label="Bluehost">{body}</article>'


UPB = '<span class="why">its actions are in the thread above ↑</span>'
HELD_BH = ('<ul><li>Everything that runs on Bluehost: pages, search, sign-in, payments, the admin, Pulse and the board, every site job, staging, trvgo.us</li>'
           '<li>Our crawl and video endpoints, so the workers\' reports wait</li></ul>')
UNAFF_BH = ('<ul><li>Railway\'s workers keep running, but can\'t report</li><li>GitHub: releases wait in the inbox</li><li>The mirror keeps its last copy; OneDrive</li>'
            '<li>Everything already stored</li></ul>')


def card_pages_down(t='2:15 AM MST'):
    th = thread('Page load', light('r', 'Down: the system is still trying') + f' since {sc(t)}.',
                HELD_BH, 'none', UNAFF_BH,
                f'The outside heartbeat (GitHub Actions) checks every 15–30 minutes (next {sc("2:30 AM MST")}) and writes the incident as an unnumbered record in the repo. '
                'Jason is told by email only, through the site\'s Amazon email (SES); no push while the host is gone. When the site is back it takes the record in, '
                'numbers it, and shows it in Pulse as “Raised while the site was down, by the outside heartbeat”.',
                b('Check and start again', True) + b('Acknowledge'),
                bh_slots(fix='console'),
                'Fix it: <b>Bluehost\'s console page</b> (cPanel, from <code>CPANEL_HOST</code>), and Sign in · Account. Both open off the host, so they work '
                'while it\'s down; the board\'s own buttons work once it\'s back.',
                '<dl class="dl"><dt>Ids</dt><dd>none while the host is down; T-#### and IR-#### are assigned when the site takes the record in</dd><dt>Evidence</dt><dd>the public home page (no token) gave no 200</dd>'
                '<dt>Told</dt><dd>the Founder, by email only: the heartbeat sends through Amazon SES with a GitHub secret limited to sending (Jason, 2026-11-21). No push: the push keys stay on Bluehost</dd>'
                '<dt>Rule</dt><dd>02 §4 row 53; 03 §14.6 (Jason\'s answers)</dd></dl>',
                at='Bluehost')
    parts = {'site': {'state': light('r', 'Down'), 'cls': 'hr', 'btn': UPB}, 'ep': {'state': light('h', 'Held (waiting on Bluehost)'), 'cls': 'hl'},
             'db': {'state': light('h', 'Held (waiting on Bluehost)'), 'cls': 'hl'}, 'sched': {'state': light('h', 'Held (waiting on Bluehost)'), 'cls': 'hl'}}
    return bh_card(light('r', 'On · Down: the system is still trying'), th, parts=parts, fault='r', stage=STAGE_BH_TRIP,
                   sens={'hb': f'<b>No 200</b> from the public home page at {sc(t)}', 'Outside heartbeat_cls': 'hr'})


def card_db():
    th = thread('The listings database', light('r', 'Paused by the system (the listings endpoints)') + f': still trying, {sc("1 of 3")}, next at {sc("9:17 AM MST")}. Since {sc("9:12 AM MST")}.',
                '<ul><li>What reads the listings realm: search and listing pages</li><li>The endpoints that write listings (their tasks wait)</li></ul>', 'none',
                '<ul><li>Sign-in (the users realm), the admin and orders (the admin realm)</li><li>Pages that read only the main database</li><li>Railway\'s other calls</li></ul>',
                'A critical part (database) trips at once while the system keeps trying: one query on the listings realm at each watch.',
                b('Check and start again', True) + b('Acknowledge'),
                bh_slots(fix='console'),
                'Fix it: <b>Bluehost\'s console page</b> (cPanel: MySQL databases), then <b>Check and start again</b> runs the realm\'s query.',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Visitors\' errors</dt><dd>their 5xx are filed under the listings database, not under search</dd>'
                '<dt>Rule</dt><dd>02 §4 row 54; §6 “The listings database realm refused”</dd></dl>', at='Bluehost')
    parts = {'db': {'state': light('r', 'Down: the listings realm'), 'cls': 'hr', 'btn': UPB,
                    'read': f'main, admin, users answer · <b>listings refused</b> at {sc("9:12 AM MST")}'},
             'site': {'state': light('h', 'Partly working: search and listing pages wait'), 'cls': 'hl'}}
    return bh_card(light('r', 'On · Paused by the system (the listings endpoints)'), th, parts=parts, fault='r', stage=STAGE_BH_TRIP,
                   sens={'db': f'<b>listings refused</b> at {sc("9:12 AM MST")}; main, admin, users answered', 'Each realm_cls': 'hr'})


def card_cpanel_key():
    th = thread('cPanel\'s key refused: mailboxes and cron jobs wait', light('r', 'Paused by the system') + f' (setting up mailboxes and cron lines): waits for a new key. Since {sc("10:05 AM MST")}.',
                '<ul><li>Calls that use <code>CPANEL_TOKEN</code>: setting up or changing cron lines and mailboxes</li></ul>', 'none',
                '<ul><li>Every job: they run on their existing cron lines</li><li>Pages, search, sign-in, payments, the databases, email</li></ul>',
                'A refused key gets no tries: it waits for a person (row 29). Not critical: no duty stops.',
                b('Replace key', True) + b('Acknowledge'),
                bh_slots(fix='keys'),
                'Fix it, on our system: <b>Its key</b>, with <b>Replace key</b> ready (<code>CPANEL_TOKEN</code> lives in <code>.env</code>); its check reads cPanel\'s cron list with the new token, past its own hold. '
                'At Bluehost: No API keys page registered for Bluehost yet, with Publish from your Vault. It never borrows Console (row 129).',
                '<dl class="dl"><dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Evidence</dt><dd>cPanel\'s API answered 401 to its cron list</dd><dt>Rule</dt><dd>02 rows 29, 56, 106, 129</dd></dl>', at='Bluehost')
    parts = {'cpanel': {'state': light('r', 'Paused by the system'), 'cls': 'hr', 'btn': UPB}}
    return bh_card(light('a', 'On · Working, one part paused: cPanel\'s API'), th, parts=parts, fault='r',
                   sens={'cpanel': f'<b>401</b> to its cron list at {sc("10:05 AM MST")}', 'cPanel\'s API_cls': 'hr'})


def card_cron():
    th = thread('The site\'s scheduled jobs', light('r', 'Paused by the system (renewals)') + f': still trying, {sc("1 of 3")}, next at {sc("12:45 PM MST")}. Since {sc("12:40 PM MST")}.',
                '<ul><li>Plans and renewals (<code>billing_tick</code>): renewals wait</li></ul>', 'none',
                '<ul><li>Every other job, on its own line</li><li>Pages, search, sign-in, checkout (but not renewals)</li></ul>',
                'A critical job\'s cron line is missing (procedures), so it trips at once while the system keeps looking at each watch.',
                b('Set them up now', True) + b('Acknowledge'),
                bh_slots(fix='console'),
                'Fix it: <b>Scheduled tasks</b>, “Set them up now” (writes the line through cPanel\'s API); or Bluehost\'s console page (cPanel: Cron jobs). '
                'Then the check reads the cron list again.',
                '<dl class="dl"><dt>Scenario</dt><dd><code>billing_tick</code>\'s cron line missing. Real: it isn\'t in <code>Scheduler::AUTO</code>, so today nothing checks its line (g3 J8)</dd>'
                '<dt>Ids</dt><dd>T-#### · IR-####</dd><dt>Rule</dt><dd>02 §4 row 55; P8</dd></dl>', at='Bluehost')
    parts = {'sched': {'state': light('r', 'Down: a critical line missing'), 'cls': 'hr', 'btn': UPB,
                       'read': f'<b><code>billing_tick</code>\'s cron line isn\'t in cPanel</b> ({sc("12:40 PM MST")})'}}
    return bh_card(light('r', 'On · Paused by the system (renewals)'), th, parts=parts, fault='r', stage=STAGE_BH_TRIP,
                   sens={'cron': f'<b><code>billing_tick</code>\'s line missing</b> at {sc("12:40 PM MST")}', 'The watch and cron_cls': 'hr'})


def card_mail():
    th = thread('Bluehost\'s email refused our key', light('r', 'Paused by the system') + f': calls with SMTP_PASSWORD wait for a new key. Since {sc("7:48 AM MST")}.',
                '<ul><li>Sending through Bluehost\'s mail server (<code>SMTP_PASSWORD</code>)</li></ul>',
                '<ul><li>The site\'s own email: Amazon SES</li></ul><div class="why">The site\'s own email is on its backup (Amazon SES) while Bluehost\'s email is paused (IR-####).</div>',
                '<ul><li>Sign-in links, codes and notices still go out</li><li>Everything else</li></ul>',
                'A refused key gets no tries: it waits for a person. The need goes to its next source by itself.',
                b('Replace key', True) + b('Acknowledge'),
                bh_slots(fix='keys'),
                'Fix it: <b>Bluehost\'s API keys page</b>. No API keys page registered for Bluehost yet, so Fix it says so and never borrows Console. '
                'The mailbox\'s password is changed in cPanel (Email accounts); then <b>Replace key</b> and its check, a sign-in without sending.',
                '<dl class="dl"><dt>Evidence</dt><dd>SMTP 535 at sign-in</dd><dt>Rule</dt><dd>02 rows 29, 43, 44; the need “The site\'s own email” (Preflight.php: Bluehost, then AWS)</dd></dl>', at='Bluehost')
    parts = {'mail': {'state': light('r', 'Paused by the system') + ' <span class="why">its need is on Amazon SES</span>', 'cls': 'hr', 'btn': UPB}}
    return bh_card(light('a', 'On · Working, one part paused: its email'), th, parts=parts, fault='r',
                   sens={'mail': f'<b>535</b> at sign-in, {sc("7:48 AM MST")}', 'Site email_cls': 'hr'})


def card_env():
    th = thread('HEALTH_TOKEN isn\'t in .env', light('r', 'Paused by the system') + ': the daily health report waits.',
                '<ul><li>What <code>HEALTH_TOKEN</code> does: GitHub\'s read of the health report (<code>alarms.json</code>, <code>server.json</code>)</li></ul>', 'none',
                '<ul><li>Pages, search, sign-in, payments, the databases, the jobs</li></ul>',
                'A missing key gets no tries: it waits for a person. Its check is the next read of <code>/api/health.php</code> with the token.',
                b('Add its key', True) + b('Acknowledge'),
                bh_slots(fix='keys'),
                'Fix it: <b>Its key</b> on the card (writes the name into .env; the value never shown), with the same value as GitHub\'s secret. '
                'Bluehost\'s API keys page: No API keys page registered for Bluehost yet.',
                '<dl class="dl"><dt>Scenario</dt><dd>a .env setting missing: <code>HEALTH_TOKEN</code> (its line: <code>site:health</code>, not critical)</dd>'
                '<dt>Rule</dt><dd>02 rows 31 (as 29), 71, 107</dd></dl>', at='Bluehost')
    parts = {'env': {'state': light('r', 'Paused by the system: a name missing'), 'cls': 'hr', 'btn': UPB, 'read': '<b><code>HEALTH_TOKEN</code> isn\'t in .env</b>'}}
    return bh_card(light('a', 'On · Working, one part paused: .env'), th, parts=parts, fault='r',
                   sens={'env': '<b><code>HEALTH_TOKEN</code> missing</b>', '.env_cls': 'hr'})


PROV_BH = [
    'The live code, mirror <code>aeb5f31</code>: <code>ControlRegister::DUTIES</code> and <code>CRITICAL</code> (svc:bluehost and site:env → page; site:db, site:dbup → database; site:sched → procedures); '
    '<code>Preflight.php</code> (the site lines, L139–149; Bluehost\'s line, L978–987; the needs “Hosting” and “The site\'s own email”); <code>Scheduler::JOBS</code> (50), <code>AUTO</code> (40), <code>QUIET</code> (4); '
    '<code>Database.php</code> (realms and their .env names); <code>CPanel.php</code> (https://‹CPANEL_HOST›:2083); <code>EnvSettings.php</code> (BLUEHOST_USD_MONTH).',
    'The service definition: <code>service-defs.php</code> \'bluehost\' (Account → Sign in, Documented · release #212: #212 carried the file and the mirror\'s earliest copy has the link; cPanel → Console, built from CPANEL_HOST: Site settings).',
    'Readings: the health report of 10:19 UTC (3:19 AM MST), <code>site-audit/results/server.json</code>: PHP 8.0.31, MySQL 5.7.44-48, 414 tables, 630.89 MB; written by Search probe run #63. No .env was opened; no live page was called.',
    'The audit: step2/g3-jobs-hosts.md §1c (D6), §3 and F5. Words: 02 rows 29, 43, 53–56, 71, 107; §6; §7. Times in MST, labelled.',
]


# ---- (a) the board, and when the board can't load ----
def bh_board():
    tile = f"""<div class="host"><div class="hn"><a href="#" data-open-panel="p-bluehost">Bluehost</a><span class="badge role">Host</span><span class="badge crit">Critical: page, database, procedures</span></div>
<div>{light('a', 'On · Warning (Slow)')}: /api/crawl/loads is slow</div>
<ul><li>The site (pages) → every visitor duty · PHP 8.0.31</li><li>The databases (4 realms) → search, sign-in, the admin · 414 tables, 630.89 MB</li>
<li>The scheduler → 50 jobs, 40 set up by themselves</li><li>The site's email → sign-in links (backup: Amazon SES)</li><li>Our endpoints → what Railway's workers store</li></ul>
<div class="rad" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)"><div class="h"><b>If it goes down, Held</b>pages, search, sign-in, payments, the admin, Pulse and the board, the site's jobs, our crawl endpoints</div>
<div class="u"><b>Unaffected</b>Railway's workers (they can't report); GitHub; the mirror</div></div>
<div class="btns" style="margin-top:.4rem"><button class="btn f" data-open-panel="p-bluehost">Open Bluehost's card</button></div></div>"""
    i = hosts.index('<div class="host"><div class="hn"><a href="#">Bluehost</a>')
    j = hosts.index('<div class="host"><div class="hn"><a href="#" data-open-panel="p-railway">Railway</a>')
    hh = hosts[:i] + tile + hosts[j:]
    when_down = f"""<div class="sec"><h2>When Bluehost is down, the board can't load</h2>
<div class="sub" style="margin:0 .2rem .6rem">The board and Pulse live on Bluehost. The outside heartbeat (GitHub Actions, every 15–30 min, O3) notices, writes an unnumbered record in the repo, and emails Jason through the site's Amazon email (SES). No push: the push keys stay on Bluehost.</div>
<div><div class="frame"><div class="fh">Email · from the outside heartbeat, through Amazon SES (<i>spec</i>)</div><div class="fb"><div class="email"><div class="hd2"><span>From</span><b>Traversence Control Board</b><span>Subject</span><b>SYSTEM ALARM NOTICE: Page load: Down: the system is still trying</b></div>
<p>Held: everything that runs on Bluehost. Unaffected: Railway's workers (they can't report), GitHub, the mirror.</p>
<p>At Bluehost: <a href="#cpanel">Console · cPanel</a> · <a href="{ACCOUNT}">Sign in · Account</a></p><p class="sub">No incident number yet: it's assigned when the site is back.</p></div></div></div>
<div class="frame"><div class="fh">Push</div><div class="fb"><div class="sub">None while the host is gone: the push keys stay on Bluehost.</div></div></div></div>
<div class="sub" style="margin:.4rem .2rem 0">When the site is back, it takes the heartbeat's record in, numbers it, and shows it in Pulse as “Raised while the site was down, by the outside heartbeat”: see page d.</div></div>"""
    body = (intro('<b>(a) Bluehost on the board\'s first view, 1280.</b> The same 10:19 UTC (3:19 AM MST) board, with Bluehost\'s tile drawn from the live code and the '
                  'health report. Below it: what stays honest when the board itself can\'t load because Bluehost is down.')
            + top_open + '<div class="sec"><h2>What needs me</h2>' + trip_cc + closed_trip + needs_att + warn_grp + '</div>'
            + '<div class="sec"><h2>Hosts</h2>' + hh + '</div>' + when_down + prov(PROV_BH))
    add('bluehost-a-board.html', 'Bluehost on the board', 'The board\'s first view with Bluehost among the hosts, and what Jason gets when the board can\'t load.',
        body, panel('p-railway', 'Railway', card_working()) + panel('p-github', 'GitHub', card_working_gh()) + panel('p-bluehost', 'Bluehost', bh_card(light('a', 'On · Warning (Slow): our endpoint'))))


# ---- (d) pages down, every surface ----
def bh_story():
    SUBJ = 'SYSTEM ALARM NOTICE: Page load: Down: the system is still trying'
    HELD = ('Held: everything that runs on Bluehost (pages, search, sign-in, payments, the admin, the site\'s jobs, our crawl endpoints). '
            'Unaffected: Railway\'s workers keep running but can\'t report; GitHub; the mirror.')
    REC = f'Recovered: Page load was down {sc("45 min")}, the system fixed it.'
    hd = '<div class="prow hd"><div>From</div><div>Severity</div><div>Subject</div><div>Received</div></div>'
    steps = f"""<div class="steps">
<div class="step"><h3>1 · The board and Pulse can't open: they're on Bluehost</h3><div class="when">{sc('2:15 AM MST')}: the outside heartbeat on GitHub gets no 200 from the public home page (no token). Page load is critical: a trip at once.</div>
<div class="note">The board and Pulse show nothing while Bluehost is down: no page of ours answers. The heartbeat writes the incident as an unnumbered record in the repo (its times, what failed, its checks) and sends the email itself.</div></div>
<div class="step"><h3>2 · Email only, from outside the host, in the same minute</h3><div class="when">One title: “Page load”. One status: “Down: the system is still trying” (the host itself is gone, so nothing could place a hold). One Held/Unaffected line.</div>
<div class="frame"><div class="fh">Email · a new thread to the Founder, from the outside heartbeat through Amazon SES (<i>spec</i>)</div><div class="fb"><div class="email"><div class="hd2"><span>From</span><b>Traversence Control Board</b><span>To</span><b>the Founder (Jason)</b><span>Subject</span><b>{SUBJ}</b><span>Sent</span><span>Nov 21, {sc('2:15 AM')} MST</span></div>
<p><b>Page load</b></p><p><b>Status:</b> Down: the system is still trying, since {sc('2:15 AM MST')}.</p><p><b>What it affects:</b> {HELD}</p>
<p><b>What the system is doing:</b> the outside heartbeat checks again every 15–30 minutes; next {sc('2:30 AM MST')}. No incident number yet: it's assigned when the site is back.</p>
<p><b>Fix it, at Bluehost</b> (these open off the host): Console · cPanel (https://‹CPANEL_HOST›:2083) · Sign in · Account <a href="{ACCOUNT}">{ACCOUNT}</a></p></div></div></div>
<div class="frame"><div class="fh">Push</div><div class="fb"><div class="sub">None while the host is gone: the push keys stay on Bluehost (Jason, 2026-11-21).</div></div></div></div>
<div class="step"><h3>3 · Fix it: Bluehost's own pages, then its card once the site answers</h3><div class="when">While it's down, the email's two links open Bluehost's cPanel and account. Once pages load, Fix it opens Bluehost's card in the side panel.</div>
<div class="frame"><div class="fh">Side panel over the board, once it loads (× · Esc · click outside to close)</div><div class="fb"><div class="panel-static"><button class="x" aria-label="Close" style="pointer-events:none">×</button><h2 style="font-size:16px;margin:.2rem 0 .7rem">Bluehost</h2>{card_pages_down()}</div>
<p><button class="btn f" data-open-panel="p-fix">Open it live</button></p></div></div></div>
<div class="step"><h3>4 · Back: numbered, then Recovered on every channel</h3><div class="when">{sc('3:00 AM MST')}: the heartbeat gets 200. The site takes the heartbeat's record in, assigns T-#### and IR-#### then, and closes it as Recovered on every channel.</div>
<div class="frame"><div class="fh">Board</div><div class="fb"><div class="need"><div class="l1">{light('g', 'Closed: Recovered')}<span class="t">Page load</span><span class="why">T-#### · IR-####</span></div>
<div>{REC}</div><div class="sub">Its check passed: the public home page answered 200 at {sc('3:00 AM MST')}. The workers' reports that waited are taken in again.</div><div class="sub">Still off: none.</div></div></div></div>
<div class="frame"><div class="fh">Pulse · the incident's row, taken in when the site came back</div><div class="fb">{hd}<div class="prow"><div>Control Board</div><div class="sev" style="color:var(--meta)">Critical</div><div>SYSTEM ALARM NOTICE: Page load: Recovered<div class="sub">Raised while the site was down, by the outside heartbeat: {sc('2:15 AM MST')}, checks at {sc('2:30')} and {sc('2:45 AM MST')}, back at {sc('3:00 AM MST')}.</div><div class="sub">Last note: {REC} Still off: none.</div></div><div>Nov 21, {sc('2:15 AM')} MST</div></div></div></div>
<div class="frame"><div class="fh">Email · a reply in the same thread</div><div class="fb"><div class="email"><div class="hd2"><span>Subject</span><b>SYSTEM ALARM NOTICE: Page load: Recovered</b><span>In reply to</span><span>{SUBJ}</span><span>Sent</span><span>Nov 21, {sc('3:00 AM')} MST</span></div>
<p>{REC}</p><p>Its check passed: the public home page answered 200 at {sc('3:00 AM MST')}. Still off: none.</p></div></div></div>
<div class="frame"><div class="fh">Push</div><div class="fb"><div class="sub">None: no push went out during the outage, so none is replaced.</div></div></div></div>
</div>"""
    add('bluehost-d-pages-down.html', 'Pages down, every surface', 'Bluehost\'s pages down walked through the outside heartbeat, email, push, Fix it and Recovered.',
        intro('<b>(d) “Pages down”, every surface.</b> A scenario: every time is illustrative (dotted). Words: 02 row 53, §3c, §7. While Bluehost is down the board and Pulse '
              'can\'t answer, so the outside heartbeat on GitHub sends the email itself, through Amazon SES; no push (Jason, 2026-11-21; <i>spec</i>, O3).') + steps + prov(PROV_BH),
        panel('p-fix', 'Bluehost', card_pages_down()))


bh_board()
add('bluehost-b-working.html', 'Bluehost card, Working', 'Bluehost\'s four-zone card, Working, with what the live code and the health report give.',
    intro('<b>(b) Bluehost\'s card, Working.</b> One line per hosted part, each with its own duty (the spec), not today\'s single line. Readings come from the live code '
          'and the 3:19 AM MST health report; the rest is “not read yet”.') + MISMATCH + bh_card(light('a', 'On · Warning (Slow): our endpoint')) + prov(PROV_BH))
BH_FAULTS = [
    ('bluehost-c1-pages-down.html', 'Bluehost card: pages not loading', 'Critical: a trip at once while the outside heartbeat keeps trying.',
     '<b>(c1) Pages not loading (row 53).</b> Critical (page): a trip at once while it keeps trying. This card is what the board shows once it can load again; while it\'s down, Jason has the email only (page d).', card_pages_down),
    ('bluehost-c2-database.html', 'Bluehost card: a database refused', 'The listings realm refused: only what reads it waits.',
     '<b>(c2) The database unreachable (row 54):</b> the listings realm refused. Critical (database).', card_db),
    ('bluehost-c3-cpanel-key.html', 'Bluehost card: cPanel refused our key', 'cPanel\'s API refused its token: only setting up jobs and mailboxes waits.',
     '<b>(c3) cPanel\'s API refusing (a key, rows 29, 56).</b> Not critical: the jobs keep running on their lines.', card_cpanel_key),
    ('bluehost-c4-cron.html', 'Bluehost card: a cron line missing', 'A critical job\'s cron line missing: only its work waits.',
     '<b>(c4) The scheduler\'s cron missing (row 55).</b> The scenario is the real gap: <code>billing_tick</code> isn\'t in <code>AUTO</code>, so today nothing checks its line.', card_cron),
    ('bluehost-c5-email.html', 'Bluehost card: the site\'s email', 'Bluehost\'s mail server refused: the site\'s email goes on its backup, Amazon SES.',
     '<b>(c5) The site\'s email failing.</b> The need “The site\'s own email” goes to Amazon SES by itself (rows 43–44), so sign-in links still go out.', card_mail),
    ('bluehost-c6-env.html', 'Bluehost card: a .env setting missing', 'HEALTH_TOKEN missing from .env: only the daily health report waits.',
     '<b>(c6) A .env setting missing (rows 31, 71, 107):</b> <code>HEALTH_TOKEN</code>, a scenario on a real name.', card_env),
]
for f, t, d, i, fn in BH_FAULTS:
    add(f, t, d, intro(i) + fn() + prov(PROV_BH))
bh_story()
build_index()
