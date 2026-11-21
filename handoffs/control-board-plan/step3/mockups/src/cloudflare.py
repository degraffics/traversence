# Cloudflare's card (step 3, 03-cards §24). Static mockups; not site code.
import kit
from video import *  # every earlier page is generated on import

CF = 'Cloudflare'
CFL = dict(dash='https://dash.cloudflare.com/', r2='https://dash.cloudflare.com/?to=/:account/r2/overview',
           ts='https://dash.cloudflare.com/?to=/:account/turnstile', bill='https://dash.cloudflare.com/?to=/:account/billing')

kit.SETUP['cloudflare'] = dict(
    provider=CF, notice='Setup capture: Sign in not recorded · Keys: why not recorded',
    keys=[('TURNSTILE_SITE_KEY, TURNSTILE_SECRET_KEY', '.env', 'the sign-in captcha (Turnstile): off until both are set'),
          ('S3_ENDPOINT, S3_ACCESS_KEY_ID, S3_SECRET_ACCESS_KEY, S3_REGION', '.env (optional)', 'R2\'s own key, once the file store points at R2 (decisions/0151); the AWS card lists them too')],
    keys_note='Names only. Whether each is set is live data.',
    slots=[('Billing', 'Billing · Documented · release #212', None), ('Keys', 'Not registered', ''),
           ('Console', 'Console · Dashboard · Documented · release #212', None), ('Sign in', 'Not registered', '')])


def cf_slots(fix=None):
    return ('<div class="slots">' + slot('billing', CF, 'Billing', CFL['bill'], fix == 'billing') + slot('keys', CF, fix=fix == 'keys')
            + slot('console', CF, 'Dashboard', CFL['dash'], fix == 'console') + slot('login', CF, fix=fix == 'login') + '</div>')


CF_RADIUS = """<details><summary>If Cloudflare can't be reached: its Incident Radius, per part</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li><b>R2</b> (once the store is on it): uploads wait on the server (<code>files_tick</code> tries them again); stored files don't show</li>
<li><b>cdnjs</b>: maps (Leaflet) and PDF previews (pdf.js) don't draw in browsers</li></ul></div>
<div class="o"><b>On a backup</b><ul><li><b>Turnstile down:</b> sign-in lets people through without the check (“don't lock everyone out”, <code>Captcha::verify()</code>)</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Pages, search, payments (unless the site's address is on Cloudflare: needs live data)</li><li>The storefront edge: Fly.io, its own Host</li></ul></div></div></details>"""

STAGE_CF = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_CFT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Replace key · Acknowledge · Take out (stage 6)</span>'
SPEND_CF = ('<span class="spend"><span>R2: $0.015 a GB-month after 10 GB free; uploads $4.50 a million after 1 million; views $0.36 a million after 10 million; nothing for downloads. Turnstile: free</span>'
            f'<b>use not read yet</b><a href="{CFL["bill"]}" target="_blank" rel="noopener">Billing ↗</a></span>')
CRIT_CF = 'Critical: page load (svc:cloudflare), flagged “unsure” in the code: whether it\'s in front of the site\'s address needs live data'


def cf_z2(ts=None, ts_cls='', ts_btn=''):
    return rows([
        ('Sign-in check (Turnstile)', 'a captcha on sign-in, sign-up and Forgot password (decisions/0097); likes\' check too', ts or light('n', 'not read yet: on when both keys are set'),
         f'Free, usually invisible. If Cloudflare doesn\'t answer, sign-in lets people through. Its page: <a href="{CFL["ts"]}" target="_blank" rel="noopener">Turnstile ↗</a> (not a portal slot).', ts_btn, ts_cls),
        ('File storage (R2)', 'decisions/0151: the file store moved from S3', light('n', 'Proposed (stage 1)'),
         'Drawn as Proposed until <code>S3_ENDPOINT</code> (set or not, the name only) is answered: <b>needs live data</b>. Built: the store\'s own key, the pre-flight\'s R2 line (“uploads need the CORS rule”), <code>files_tick</code> moving files every 10 minutes. '
         f'Today the storage part is drawn on <a href="aws-a-working.html">the AWS card</a>. Its page: <a href="{CFL["r2"]}" target="_blank" rel="noopener">R2 buckets ↗</a> (not a slot).', '', ''),
        ('cdnjs (browser scripts)', 'Leaflet (maps) and pdf.js (PDF previews), loaded by visitors\' browsers', light('n', 'not checked by us'),
         'Our pages load them from <code>cdnjs.cloudflare.com</code> (13 files name it); the server never calls it, so no sensor reads it.', '', ''),
        ('The site\'s address (DNS)', 'in front of traversence.com?', light('n', 'needs live data'),
         'The code says “unsure” (<code>ControlRegister::CRITICAL</code>); the captcha\'s notes say the DNS “doesn\'t have to be on Cloudflare”; <code>RegionGate</code> reads <code>CF-IPCountry</code> only “when the site is behind Cloudflare”.', '', ''),
    ])


def cf_z3(ts=None, ts_cls=''):
    return rows([
        ('Turnstile\'s secret', 'the pre-flight\'s check: a made-up token; a good secret answers “invalid-input-response”, a bad one “invalid-input-secret”', '', ts or 'not read yet', '', ts_cls),
        ('R2\'s bucket', '<code>Aws::checkBucket()</code> and the CORS rule, when <code>S3_ENDPOINT</code> names R2', '', 'not read yet (R2 not in use, or live data)', '', ''),
    ]) + '<div class="sub" style="margin-top:.4rem">The storefront edge for businesses\' own domains runs on <b>Fly.io</b> (<code>workers/storefront-edge</code>, decisions/0139), not Cloudflare: its own Host card.</div>'


def cf_card(state, thread_html=None, fault=None, stage=STAGE_CF, z2=None, z3=None):
    return card(CF, 'cloudflare', CRIT_CF, state, stage, SPEND_CF, CF_RADIUS, z2 or cf_z2(), z3 or cf_z3(),
                act_rows(b('Check now'), CF, cf_slots(), then='Turnstile and R2 buckets are pages, not slots: linked on their lines in zone 2.'), thread_html, fault)


PROV_CF = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'cloudflare\' (purpose “File storage (R2) and the captcha on sign-in (Turnstile)”, prices, links Dashboard, R2 buckets, Turnstile, Billing; hosts; key TURNSTILE_SECRET_KEY); '
    '<code>Captcha.php</code> (Turnstile; fails open when Cloudflare doesn\'t answer; refuses when its answer isn\'t a success); <code>Preflight.php</code> (the storage need: cloudflare, then aws; Cloudflare\'s test: R2 and the Turnstile secret); '
    '<code>S3.php</code>, <code>Aws.php</code> (R2 by <code>S3_ENDPOINT</code>); <code>Scheduler.php</code> (<code>files_tick</code>); <code>RegionGate.php</code>; <code>ControlRegister::CRITICAL</code> (“unsure”); <code>Pulse::SERVICES</code> (Dashboard link).',
    '<code>Pulse::SERVICES</code> says R2 holds “every photo, video and file”, while the AWS record says files are on S3 “until the move to R2 is finished”: which is live needs live data (<code>S3_ENDPOINT</code>).',
    '<code>workers/storefront-edge/README.md</code>: the edge runs on Fly.io with Caddy. Words: 02 rows 29, 106, §3b. Scenario values dotted copper. No .env opened; no outside call made.',
]


def cf_secret():
    th = thread('Cloudflare refused our Turnstile secret',
                light('r', 'Paused by the system') + f': the sign-in check with TURNSTILE_SECRET_KEY waits for a new key. Since {sc("7:40 AM MST")}.',
                '<ul><li><b>Password sign-in, sign-up and Forgot password:</b> every check answers “didn\'t pass”</li><li>Likes\' check</li></ul>',
                '<ul><li>Sign in with Google, Microsoft or Facebook</li></ul>',
                '<ul><li>R2, cdnjs; pages, search, payments</li></ul>',
                'A refused key gets no tries; it waits for a person (row 29). It touches the sign-in duty (<code>site:signin</code>, procedures), so the Founder is told at once. '
                'A Cloudflare outage would let people through; a refused secret doesn\'t (open word 3).',
                b('Replace key', True) + b('Acknowledge'),
                cf_slots(fix='console'),
                'Fix it: <b>Cloudflare\'s console</b> (Turnstile: the widget\'s secret), then Replace key and its check, the made-up token answered “invalid-input-response”.',
                f'<dl class="dl"><dt>Evidence</dt><dd>siteverify answered “invalid-input-secret” at {sc("7:40 AM MST")}</dd><dt>Told</dt><dd>the Founder: email by Bluehost\'s SMTP, Pulse, push</dd>'
                '<dt>Rule</dt><dd>02 rows 29, 106</dd></dl>'
                + today_words(['Today: each visitor sees “The “verify you\'re human” check didn\'t pass. Please try again.” and the server logs “captcha refused: invalid-input-secret”; the pre-flight names it only when it runs.',
                               'New: “Cloudflare refused our Turnstile secret · Paused by the system: the sign-in check with TURNSTILE_SECRET_KEY waits for a new key.”']), at=CF)
    return cf_card(light('r', 'On · Paused by the system (its Turnstile secret)'), th, fault='r', stage=STAGE_CFT,
                   z2=cf_z2(light('r', 'Paused (its secret)'), 'hr', '<span class="why">its actions are in the thread above ↑</span>'),
                   z3=cf_z3(f'<b>Refused: invalid-input-secret</b> at {sc("7:40 AM MST")}', 'hr'))


add('cloudflare-a-working.html', 'Cloudflare card, Working', 'Cloudflare: the sign-in check (Turnstile), R2 as Proposed until S3_ENDPOINT is answered, cdnjs, and DNS as live data.',
    intro('<b>(a) Cloudflare, Working.</b> What the code uses it for: the sign-in check (Turnstile), file storage (R2, drawn Proposed until <code>S3_ENDPOINT</code> is answered), browser scripts (cdnjs). '
          'Whether it\'s in front of the site\'s address is live data. The storefront edge is Fly.io, not Cloudflare.') + cf_card(light('g', 'On · Working')) + prov(PROV_CF))
add('cloudflare-b-turnstile-refused.html', 'Cloudflare card: Turnstile secret refused', 'Its most real fault: a refused Turnstile secret stops password sign-in; a Cloudflare outage wouldn\'t.',
    intro('<b>(b) Cloudflare refused our Turnstile secret (rows 29, 106).</b> Password sign-in, sign-up and Forgot password fail the check; Google, Microsoft and Facebook sign-in still work.') + cf_secret() + prov(PROV_CF))
build_index()
