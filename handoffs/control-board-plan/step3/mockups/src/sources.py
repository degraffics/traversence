# The batched sources (step 3, 03-cards §22): Overture, Foursquare, Geofabrik (our OSM copy), NPI (CMS), IRS.
# Static mockups; not site code.
import kit
from aws import *  # every earlier page is generated on import

OV, FQ, GF, NP, IR = 'Overture Maps (Places)', 'Foursquare open places', 'Geofabrik (OpenStreetMap extracts)', 'NPI Registry (CMS)', 'IRS (exempt organizations)'
U = dict(ov_rel='https://docs.overturemaps.org/release/latest/', fq_hf='https://huggingface.co/datasets/foursquare/fsq-os-places',
         gf_us='https://download.geofabrik.de/north-america/us.html', np_files='https://download.cms.gov/nppes/NPI_Files.html',
         np_reg='https://npiregistry.cms.hhs.gov/', ir_bmf='https://www.irs.gov/charities-non-profits/exempt-organizations-business-master-file-extract-eo-bmf',
         ir_eos='https://apps.irs.gov/app/eos/')
CRIT = 'Not critical: no duty stops without it'


def nr(ty, provider, why, fix=False):
    """A slot not registered, with its recorded why (§13; the session manager, 2026-11-21)."""
    s = slot(ty, provider, fix=fix)
    return s[:-6] + f'<div class="why">Why: {why}</div></div>'


OPEN = 'Open data: no account'
WHYS = {'foursquare': ('Open data: no bill', 'its key is a Hugging Face token: Hugging Face is a line on this card',
                       'Open data: its files are linked in zone 2', 'a Hugging Face account: a line on this card')}


def src_slots(mod, provider, fix=None):
    w = WHYS.get(mod, (OPEN,) * 4)
    return ('<div class="slots">' + ''.join(nr(t, provider, w[i], fix == t) for i, t in enumerate(['billing', 'keys', 'console', 'login'])) + '</div>')


SL = {m: (lambda m, pv: (lambda fix=None: src_slots(m, pv, fix)))(m, pv) for m, pv in
      [('overture', OV), ('foursquare', FQ), ('geofabrik', GF), ('cms', NP), ('irs', IR)]}
FILES = {'overture': ('Releases', U['ov_rel']), 'foursquare': ('On Hugging Face', U['fq_hf']), 'geofabrik': ('US extracts', U['gf_us']),
         'cms': ('Monthly files', U['np_files']), 'irs': ('EO BMF files', U['ir_bmf'])}


def files_row(mod):
    lab, url = FILES[mod]
    return ('Its files', f'its download page, from its record (“{lab}”): not a portal slot (§5)', '',
            f'<a href="{url}" target="_blank" rel="noopener">Its files ↗</a>', '', '')


def setup(mod, provider, label, keys, note, keys_why='no key'):
    w = WHYS.get(mod, (OPEN,) * 4)
    kit.SETUP[mod] = dict(provider=provider, notice='Setup capture', clear='Setup capture: each slot\'s why recorded (names and places in its Details)',
                          keys=keys, keys_note=note,
                          slots=[(n, 'Not registered', w[i]) for i, n in enumerate(['Billing', 'Keys', 'Console', 'Sign in'])])


setup('overture', OV, 'Releases', [('OVERTURE_RELEASE', 'Railway (optional)', 'pins a release; unset, the worker takes the newest on the bucket. Set or not: needs live data'),
                                   ('load_towns_per_run', 'a setting (Thresholds)', 'towns a run, shared with Foursquare')],
      'No key. Names only.')
setup('foursquare', FQ, 'On Hugging Face', [('HF_TOKEN', 'Railway', 'a Hugging Face token whose account accepted Foursquare\'s terms (set: the task ran, so its check let it, inf)'),
                                            ('FSQ_RELEASE', 'Railway (optional)', 'pins a release; unset, the code uses dt=2026-10-15'),
                                            ('load_towns_per_run', 'a setting (Thresholds)', 'towns a run, shared with Overture')],
      'Names only.')
setup('geofabrik', GF, 'US extracts', [('OSM_EXTRACT_BASE', 'Railway (optional)', 'another extracts address, a free mirror, set by hand'),
                                       ('geofabrik_days', 'a setting (Thresholds)', 'a state\'s file every 7 days at most, by default'),
                                       ('osmium-tool', 'the worker\'s image', 'reads the files; the task waits without it')],
      'No key. Names only.')
setup('cms', NP, 'Monthly files', [('NPI_BULK', 'Railway (on unless “off”)', 'the monthly file load on the worker\'s side'),
                                   ('NPI_TIME', 'Railway', 'seconds allowed for one monthly load (1500 by default)'),
                                   ('NpiBulk::OFF', 'the site\'s code', 'the monthly load off since 2026-11-02 (Jason)')],
      'No key. Names only.')
setup('irs', IR, 'EO BMF files', [('IRS_BULK', 'Railway (on unless “off”)', 'the monthly load on the worker\'s side')], 'No key. Names only.')

SPEND0 = '<span class="spend"><span>Free ($0), no bill</span><b>Our own cost: the worker\'s time on Railway</b><span class="why">counted on Railway\'s card</span></span>'
STAGE_S = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause our calls · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_ST = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>'
IRL = '<a href="railway-e-ir-0001.html">IR-0001</a>'


def licence(lic, use, credit, conf=False):
    c = '<span class="why"><b>licence: confirmation not recorded (0164 Decision 9)</b></span>' if conf else ''
    return (f'<div class="stage"><span class="spend"><span>Licence:</span><b>{lic}</b><span class="why">{use}</span>{c}</span></div>'
            f'<div class="stage"><span class="spend"><span>Credit:</span><b>{credit}</b></span></div>')


def scard(name, mod, state, lic, z2, z3, radius, z4=None, thread_html=None, fault=None, stage=STAGE_S, extra=''):
    return card(name, mod, CRIT, state, stage, SPEND0 + licence(*lic) + extra, radius, z2, z3, z4, thread_html, fault, role='Source')


def radius(name, held, backup, unaff, note=''):
    n = f'<div class="sub">{note}</div>' if note else ''
    return (f'<details><summary>If {name} can\'t be read: its Incident Radius (a Source: reroutes on the source map)</summary>'
            f'<div class="rad"><div class="h"><b>Held</b><ul>{held}</ul></div><div class="o"><b>On a backup</b><ul>{backup}</ul></div>'
            f'<div class="u"><b>Unaffected</b><ul>{unaff}</ul></div></div>{n}</details>')


def z4(mod, provider, then=''):
    return act_rows(b('Check now') + b('Pause our calls') + '<span class="why">its task only</span>', provider, SL[mod](), then=then)


NR = 'not read yet'
UPS = '<span class="why">its actions are in the thread above ↑</span>'

# ---------------- Overture ----------------
OV_LIC = ('CDLA-Permissive-2.0, Apache-2.0 or CC0, per record', 'corroboration, stored and shown with credit as its licences allow, once they\'re confirmed (decisions/0164 R9, Decision 9); its emails field is never imported',
          '“Overture Maps Foundation” plus the record\'s upstream source; each record keeps its source and licence', True)
OV_RAD = radius('Overture', '<li><code>overture_bulk</code>\'s town loads</li><li>Places by kind and town (W1), only where our OpenStreetMap copy can\'t say: Overture is its second source, then it waits</li>',
                '<li>Businesses in bulk (W1): our open-data copies first; the last load is kept</li>',
                '<li>Foursquare and Geofabrik: their own tasks</li><li>W1\'s other tasks; pages, search, sign-in, payments</li>')


def ov_z2(task=None, task_cls=''):
    return rows([
        ('W1 · <code>overture_bulk</code>', 'the base layer of businesses by town for Arizona and New Mexico: name, address, phone, website, category and confidence, through the one identity check',
         task or light('g', 'On · Working'), f'Loads by town box, biggest gap first: <code>load_towns_per_run</code> towns a run ({NR}; 3 by default), from the monthly release. Last load: <b>{NR}</b> (needs live data: <code>open_loads</code>, seen and listed per town).', '', task_cls),
        ('Its place on the source map', '<code>Preflight::NEEDS</code>', '',
         '<b>Places by kind and town (W1):</b> our OpenStreetMap copy (Geofabrik), then Overture; then it waits. <b>Businesses in bulk (W1):</b> our open-data copies, Overture, Foursquare, Geofabrik; the last load is kept.', '', ''),
        files_row('overture'),
    ])


def ov_z3(load=None, load_cls=''):
    return rows([
        ('Its release', '<code>OVERTURE_RELEASE</code> pins one; unset, the newest on the bucket\'s listing', '', f'{NR} (set or not: needs live data). <b>No release found</b> is a malformed answer (rows 24–26), in its own words: “Overture: no release found · Its bucket listed no release at &lt;time&gt;. Trying again (1 of 3).”', '', ''),
        ('Its hosts', 'declared: <code>overturemaps-us-west-2.s3.amazonaws.com</code>, <code>overturemaps.org</code>', '',
         'The release listing calls <code>overturemaps-us-west-2.s3.us-west-2.amazonaws.com</code>, today filed under Amazon Web Services (step 2, g2 row 31). Under the contract its calls count here: <a href="aws-a-working.html">the AWS card</a> carries only our own storage and email.', '', 'hl'),
        ('Our load endpoint', '<code>/api/crawl/loads</code>, the Site part it sends each 500 rows to', '', load or 'Working', '', load_cls),
    ]) + '<div class="sub" style="margin-top:.4rem">Its settings, by name and place: Setup capture (zone 1).</div>'


def ov_ir():
    note = (f'<div class="stage" style="font-size:13px">{light("n", "Linked")} Its load at {sc("1:31 AM MST")} stopped before its first town: our load endpoint didn\'t reply within 120 s. '
            f'That\'s {IRL}, a warning on the Site part (<code>/api/crawl/loads</code> is slow). Overture wasn\'t at fault, and <code>overture_bulk</code> isn\'t held: it runs at the next night\'s window (row 14). '
            f'The other member: <a href="sources-c-foursquare.html">Foursquare</a>.</div>')
    tw = today_words(['Today (live, L2): “SYSTEM ALARM NOTICE: W1 Listings: Paused by the system” by email and push; a trip on the clock at 09:35 UTC with no try made; <b>all of W1 paused</b>, its other tasks left out of the next plan.',
                      f'New: no alarm on Overture. On the Site part: “/api/crawl/loads is slow · It didn\'t reply to the crawler within 120 s for 500 rows at 01:31 MST. The system is checking it.” (row 11), then “…seen 2 times (01:20, 01:31 MST)” (row 12).'])
    return scard(OV, 'overture', light('g', 'On · Working'), OV_LIC, ov_z2(light('g', 'On · Working') + '<div class="sub">its last run stopped: see IR-0001</div>', 'hl'),
                 ov_z3(light('a', 'Warning (Slow)') + f' · didn\'t reply within 120 s at {sc("1:31 AM MST")} ({IRL})', 'hl'), OV_RAD,
                 z4('overture', 'Overture Maps', 'Nothing to fix at Overture. The Site part\'s thread carries the endpoint\'s check.') + tw, extra=note)


# ---------------- Foursquare ----------------
FQ_LIC = ('Apache-2.0', 'corroboration, stored and shown with credit as its licence allows, once it\'s confirmed (R9, Decision 9); its licence and NOTICE.txt kept with any data we pass on, changes marked',
          '“Foursquare”', True)
FQ_RAD = radius('Foursquare', '<li><code>foursquare_bulk</code>\'s town loads</li>', '<li>Businesses in bulk (W1): our open-data copies first; the last load is kept</li>',
                '<li>Overture and Geofabrik: their own tasks</li><li>W1\'s other tasks; pages, search, sign-in, payments</li>',
                'Where Overture already carries Foursquare\'s record, it counts as the same source.')


def fq_card():
    note = (f'<div class="stage" style="font-size:13px">{light("n", "Linked")} Its load at {sc("1:20 AM MST")} stopped before its first town, the same way: {IRL}\'s first member (Overture\'s, 11 minutes later, is the second). '
            f'A warning on the Site part; <code>foursquare_bulk</code> isn\'t held.</div>')
    z2 = rows([
        ('W1 · <code>foursquare_bulk</code>', 'more businesses by town, where Overture doesn\'t already carry Foursquare\'s record', light('g', 'On · Working') + '<div class="sub">its last run stopped: see IR-0001</div>',
         f'Loads by town box, <code>load_towns_per_run</code> towns a run (shared with Overture). Read from Hugging Face (gated: Foursquare\'s terms accepted by the token\'s account). Last load: <b>{NR}</b> (needs live data: <code>open_loads</code>).', '', 'hl'),
        ('Its place on the source map', '<code>Preflight::NEEDS</code>', '', '<b>Businesses in bulk (W1):</b> the third source, after our open-data copies and Overture; the last load is kept.', '', ''),
        files_row('foursquare'),
        ('Hugging Face', 'where its files are kept, gated', '', 'A line on this card, not a module (the session manager, 2026-11-21): its token is <code>HF_TOKEN</code>, its account accepted Foursquare\'s terms.', '', ''),
    ])
    z3 = rows([
        ('Its key', '<code>HF_TOKEN</code> on Railway, read by DuckDB', '', f'Not checked yet as a key: a refused token raises inside the task today, a task failure (step 2, g2 row 3). Under the contract: a refused key, rows 29–31, only <code>foursquare_bulk</code> held.', '', ''),
        ('Its release', '<code>FSQ_RELEASE</code>; unset, the code uses <code>dt=2026-10-15</code>', '', f'{NR} (set or not: needs live data). The code\'s note says “the newest on Hugging Face”, but it reads that fixed release.', '', 'hl'),
        ('Our load endpoint', '<code>/api/crawl/loads</code>', '', light('a', 'Warning (Slow)') + f' · didn\'t reply within 120 s at {sc("1:20 AM MST")} ({IRL})', '', 'hl'),
    ])
    tw = today_words(['Today (live, L3): a second “SYSTEM ALARM NOTICE: W1 Listings: Paused by the system”, a second trip at 09:25 UTC, W1 paused whole a second time.',
                      'New: no alarm here; the Site part\'s warning counts it (row 12).'])
    return scard(FQ, 'foursquare', light('g', 'On · Working'), FQ_LIC, z2, z3, FQ_RAD, z4('foursquare', 'Foursquare', 'Its token\'s page at Hugging Face isn\'t registered yet.') + tw, extra=note)


# ---------------- Geofabrik ----------------
GF_LIC = ('ODbL (OpenStreetMap\'s data)', 'its own layer: kept in its own tables, never copied into our listings (decisions/0065, 0164 R9); within its layer, show all it gives',
          '“© OpenStreetMap contributors”')
GF_RAD = radius('Geofabrik', '<li>That state\'s new file only; the other states load on their own runs</li>',
                '<li>Places by kind and town (W1): our OpenStreetMap copy, the last load kept; then Overture</li>',
                '<li>The other four states; W1\'s other tasks; pages, search, payments</li>',
                'Our only OpenStreetMap source since Overpass was retired (2026-11-19): outside our copy\'s five states there are none.')
STATES = ['Arizona', 'New Mexico', 'Utah', 'Colorado', 'Nevada']


def gf_z2(utah):
    st = ''.join(f'<li>{s}: {utah if s == "Utah" else "last file " + NR}</li>' for s in STATES)
    return rows([
        ('W1 · <code>geofabrik_bulk</code>', 'OpenStreetMap for five states as weekly state files, read by osmium-tool, sent in 500-place batches to OpenStreetMap\'s own table',
         light('r', 'Paused by the system (Utah\'s file)'), f'One state a run; each state\'s file every <code>geofabrik_days</code> days at most ({NR}; 7 by default). Our copy by state:<ul style="margin:.2rem 0;padding-left:1.1rem">{st}</ul>'
         'Needs live data: <code>open_loads</code> per state (seen, kept).', UPS, 'hr'),
        ('Its place on the source map', '<code>Preflight::NEEDS</code>', '', '<b>Places by kind and town (W1):</b> our OpenStreetMap copy first. <b>Businesses in bulk (W1):</b> the fourth source; the last load is kept.', '', ''),
        files_row('geofabrik'),
    ])


def gf_card():
    utah = light('r', 'Paused by the system: its download failed') + f' at {sc("2:05 AM MST")}'
    th = thread('Geofabrik: Utah\'s file didn\'t download',
                light('r', 'Paused by the system (Utah\'s file)') + f' since {sc("2:05 AM MST")}: {sc("503 Service Unavailable")}. The system checks once a day for 3 days; after that it\'s your call.',
                '<ul><li>Utah\'s new file only: no download each run while it\'s held</li></ul>',
                '<ul><li>Utah\'s places: our copy\'s last load is kept (if Utah\'s first load finished: needs live data)</li></ul>',
                '<ul><li>Arizona, New Mexico, Colorado, Nevada</li><li>W1\'s other tasks</li></ul>',
                'A failure (a timeout or a 5xx): it follows row 32\'s probe, one polite download a day for at most 3 days, never one each run. '
                '<b>The other case, a refusal</b> (a 403 or a block): it waits for a person at once, under Jason\'s refusal rule, never retried; a free mirror instead, set by hand (<code>OSM_EXTRACT_BASE</code>).',
                b('Check now', off='its next probe is once a day') + b('Acknowledge'),
                SL['geofabrik'](),
                'Their trouble: no Fix it, and no slot registered (open data). Its files, in zone 2, show whether Utah\'s file is there (row 130).',
                f'<dl class="dl"><dt>Evidence</dt><dd>the worker\'s line “geofabrik: utah: download failed: {sc("HTTPError 503")}”</dd><dt>Part</dt><dd>Geofabrik, Utah\'s file: one state\'s file is the held part</dd>'
                '<dt>Rule</dt><dd>by classification (the session manager, 2026-11-21): a failure, row 32\'s probe; a refusal, waits for a person; row 130 (their trouble)</dd></dl>'
                + today_words(['Today: the line goes to Railway\'s log only and the run moves on. The site still lists Utah as due, so every run downloads it again: not “held for a person, never retried” as its record says. Only the test download (<code>geofabrik_probe</code>) raises a case.',
                               'New: “Geofabrik: Utah\'s file didn\'t download · Paused by the system (Utah\'s file). The system checks once a day for 3 days; after that it\'s your call.”']), at='Geofabrik')
    return scard(GF, 'geofabrik', light('r', 'On · Paused by the system (Utah\'s file)'), GF_LIC, gf_z2(utah),
                 rows([('Its download', f'<code>download.geofabrik.de</code> over IPv4 (Railway has no IPv6 route, 2026-11-15)', '', f'<b>503</b> for Utah at {sc("2:05 AM MST")}', '', 'hr'),
                       ('osmium-tool', 'in the worker\'s image', '', 'present (the task ran)', '', '')]), GF_RAD, thread_html=th, fault='r', stage=STAGE_ST)


# ---------------- NPI (CMS) ----------------
NP_LIC = ('public domain (an official registry)', 'owned: stored with the source (decisions/0164 R9)', 'the source, “NPI Registry (CMS)”')
NP_RAD = radius('the NPI Registry', '<li>Health providers by town (<code>npi_town</code>) and single lookups</li>',
                '<li>Health providers (W1): our NPI copy first; the last load is kept</li><li>Confirm a business (W1, W2): our copies agreeing, then the Arizona Corporation Commission, Tavily, Brave</li>',
                '<li>Every other source; pages, search, payments</li>')


def np_card():
    z2 = rows([
        ('W1 · <code>npi_bulk</code>', 'health-care organizations in Arizona and New Mexico from the monthly file', light('n', 'Off'),
         f'“Off on purpose: CMS took its file page down (Jason, 2026-11-02).” A real switch with its why in step 4; today it\'s the code\'s <code>NpiBulk::OFF</code>. The records already loaded keep confirming listings. Our copy: <b>{NR}</b> (needs live data: <code>npi_loads</code>, <code>npi_orgs</code>).', '', ''),
        ('W1, W2 · <code>second_look</code>, <code>identity</code>, <code>npi_town</code>', 'single lookups on the NPI Registry API, one at a time', light('g', 'On · Working'),
         'A listing\'s second look and its identity check; health providers by town for Arizona and New Mexico (stage 8), with its two guards.', '', ''),
        ('Its place on the source map', '<code>Preflight::NEEDS</code>', '', '<b>Health providers (W1):</b> our NPI copy, then CMS; the last load is kept. <b>Confirm a business (W1, W2):</b> CMS after our copies agreeing.', '', ''),
        files_row('cms'),
    ])
    z3 = rows([('The NPI Registry API', '<code>npiregistry.cms.hhs.gov</code>', '', f'Working (its last real call passed: {NR})', '', ''),
               ('The monthly file page', '<code>download.cms.gov</code>', '', 'not read while the load is off. When it\'s on, “no monthly file found on the NPI download page” is a malformed answer (rows 24–26).', '', '')])
    return scard(NP, 'cms', light('g', 'On · Working'), NP_LIC, z2, z3, NP_RAD,
                 z4('cms', 'CMS', 'The NPI Registry itself: <a href="' + U['np_reg'] + '" target="_blank" rel="noopener">NPI Registry ↗</a> (a link on its record, not a slot).'))


# ---------------- IRS ----------------
IR_LIC = ('public domain', 'owned, but used as evidence only: it confirms a nonprofit exists; never shown, never creates a listing (decisions/0164 R9 and §12, C2)', 'the source; its search linked on a listing\'s identity record')
IR_RAD = radius('the IRS files', '<li>This month\'s load of exempt organizations</li>', '<li>Nonprofits (W1): our IRS copy; the last load is kept</li>',
                '<li>Every listing (nothing is shown from it); every other source; pages, search, payments</li>')
COLS = 'EIN, NAME, STREET, CITY, STATE, ZIP, NTEE_CD'


def ir_card():
    th = thread('The IRS\'s exempt-organization file changed shape',
                light('r', 'Paused by the system (its file changed shape)') + f' since {sc("3:12 AM MST")}: New Mexico\'s file has no {sc("NTEE_CD")} column. Nothing of this month\'s load was kept.',
                '<ul><li>This month\'s IRS load</li></ul>', '<ul><li>Nonprofits (W1): our IRS copy, the last load kept</li></ul>',
                '<ul><li>Confirming nonprofits from our copy; every other source</li></ul>',
                f'The load checks each file\'s columns ({COLS}) before any write. A changed shape stops it there, and nothing is deleted; nor when a load\'s rows fall below its floor (a share of last month\'s rows: 90% proposed). Trying again won\'t change the file, so it waits for a person (the loader updated for the new columns), then Check and start again.',
                b('Check and start again', True) + b('Acknowledge'),
                SL['irs'](fix='console'),
                'Fix it: the IRS\'s EO BMF files page, to read what changed; then the loader is updated (a build), and Check and start again.',
                f'<dl class="dl"><dt>Evidence</dt><dd>eo_nm.csv\'s header without {sc("NTEE_CD")}</dd><dt>Floor</dt><dd>a share of last month\'s rows (90% proposed, des): below it, nothing is deleted</dd><dt>Rule</dt><dd>02 row 38b (a bulk file that changed shape: a stop that waits for a person)</dd></dl>'
                + today_words(['Today: the worker reads columns by name, so a missing one loads as blank, silently; a missing EIN or NAME drops every row, and “the IRS files had no rows the site accepted” keeps the old copy. '
                               '<b>If one state\'s file fails and the other loads</b>, the finish removes every record not in this month\'s file: that state\'s nonprofits are deleted (<code>IrsBulk::finish()</code>).',
                               'New: “The IRS\'s exempt-organization file changed shape · Paused by the system: nothing of this month\'s load was kept.”']), at='the IRS')
    z2 = rows([('W1 · <code>irs_bulk</code>', 'exempt organizations in Arizona and New Mexico from the monthly EO BMF file', light('r', 'Paused by the system'),
                f'Monthly: due when the last finished load is 30 days old. Last finished load: <b>{NR}</b> (needs live data: <code>irs_loads</code>, rows seen and removed).', UPS, 'hr'),
               ('Its place on the source map', '<code>Preflight::NEEDS</code>', '', '<b>Nonprofits (W1):</b> our IRS copy, then the IRS; the last load is kept.', '', ''), files_row('irs')])
    z3 = rows([('Its files', '<code>www.irs.gov/pub/irs-soi/eo_{state}.csv</code>', '', f'Arizona: the expected columns · New Mexico: <b>no {sc("NTEE_CD")}</b> at {sc("3:12 AM MST")}', '', 'hr')])
    return scard(IR, 'irs', light('r', 'On · Paused by the system (its file changed shape)'), IR_LIC, z2, z3, IR_RAD, thread_html=th, fault='r', stage=STAGE_ST)


PROV_S = [
    'The live code, mirror <code>aeb5f31</code>: <code>service-defs.php</code> \'overture\', \'foursquare\', \'geofabrik\', \'cms\', \'irs\' (purpose, licence, credit, links, hosts, keys, caps, <code>added_off</code>); '
    '<code>Preflight::NEEDS</code> (places, bulk, health, nonprofits, confirm); <code>OutsideServices</code>\' tasks map; <code>OpenLoads.php</code> (<code>GEOFABRIK_STATES</code>, <code>geofabrikDue()</code>, <code>probed()</code>); '
    '<code>NpiBulk.php</code> (<code>OFF</code> since 2026-11-02, 30 days); <code>IrsBulk.php</code> (30 days, <code>batch()</code>, <code>finish()</code>); <code>Thresholds.php</code> (<code>load_towns_per_run</code> 3, <code>geofabrik_days</code> 7).',
    'The crawler (<code>workers/crawler/crawler.py</code>): <code>open_load</code>, <code>_overture_release</code>, <code>FSQ_RELEASE</code>\'s default, <code>geofabrik_load</code> (one state a run; a failed download logged, then the next state), <code>npi_bulk</code>, <code>irs_bulk</code>, the task table\'s checks.',
    'IR-0001: <code>step2/ir-0001.md</code> and the live alarms L2, L3 (<code>step2/live-snapshot.md</code>). Licences: decisions/0164 R9, read only.',
    'Last loads and their counts aren\'t read here: needs live data. Scenario values are dotted copper. Words: 02 rows 11–14, 24–26, 29–32, 38b, 130 and §3b. Times in MST. No .env opened; no outside call made.',
]


def list_page():
    rs = [
        (f'<a href="sources-b-overture.html">{OV}</a>', 'Source · Stage 5', light('g', 'On · Working') + f'<div class="sub">its last run stopped: {IRL}, the Site part\'s warning</div>', 'monthly release; <code>load_towns_per_run</code> towns a run', f'{NR} (<code>open_loads</code>)'),
        (f'<a href="sources-c-foursquare.html">{FQ}</a>', 'Source · Stage 5', light('g', 'On · Working') + f'<div class="sub">{IRL}\'s other member</div>', 'its release; towns a run, shared with Overture', f'{NR} (<code>open_loads</code>)'),
        (f'<a href="sources-d-geofabrik.html">{GF}</a>', 'Source · Stage 5', light('r', 'On · Paused by the system (Utah\'s file)'), 'one state a run; each state every 7 days at most', f'{NR} per state (<code>open_loads</code>)'),
        (f'<a href="sources-e-npi.html">{NP}</a>', 'Source · Stage 5', light('g', 'On · Working') + '<div class="sub">its monthly file: Off on purpose (Jason, 2026-11-02)</div>', 'lookups one at a time; the monthly file off', f'{NR} (<code>npi_loads</code>)'),
        (f'<a href="sources-f-irs.html">{IR}</a>', 'Source · Stage 5', light('r', 'On · Paused by the system (its file changed shape)'), 'monthly (30 days); Arizona, New Mexico', f'{NR} (<code>irs_loads</code>)'),
    ]
    body = ''.join(f'<div class="row"><div><div class="nm">{n}</div><div class="sub">{r}</div>{s}</div><div><b>Cadence:</b> {c}<div class="sub"><b>Last load:</b> {l}</div></div><div class="btns"></div></div>' for n, r, s, c, l in rs)
    return (f'<article class="card" aria-label="The batched sources"><div class="z"><div class="zh"><span class="n">Sources</span>The batched sources: free bulk files, loaded by the worker (W1)</div>'
            f'<div class="rows">{body}</div><div class="sub" style="margin-top:.4rem">Each one\'s last load (when, rows seen, kept, removed) is read from its load table: needs live data, so none is shown. '
            'The states are this set\'s scenarios, one per card.</div></div></article>')


add('sources-a-list.html', 'The batched sources, listed', 'Overture, Foursquare, Geofabrik, NPI and the IRS: role, stage, state, cadence and last load.',
    intro('<b>(a) The batched sources.</b> Five free Sources the Railway worker loads in bulk for W1. Each row opens its card.') + list_page() + prov(PROV_S))
add('sources-b-overture.html', 'Overture card: IR-0001 under the contract', 'IR-0001 drawn under the contract: a warning on the Site endpoint, Overture\'s task not held.',
    intro('<b>(b) Overture, during IR-0001 (rows 11–14).</b> The timeout was our endpoint\'s, so the warning sits on the Site part; Overture stays Working and its task isn\'t held.') + ov_ir() + prov(PROV_S))
add('sources-c-foursquare.html', 'Foursquare card, Working', 'Foursquare Working, as IR-0001\'s other member, linked.',
    intro('<b>(c) Foursquare, Working: IR-0001\'s other member.</b>') + fq_card() + prov(PROV_S))
add('sources-d-geofabrik.html', 'Geofabrik card: a state file failed', 'One state\'s Geofabrik file didn\'t download: only that state waits.',
    intro('<b>(d) Geofabrik: Utah\'s file didn\'t download, a 503: a failure, so row 32\'s probe (a refusal would wait for a person).</b> Only that state\'s file is held; our copy\'s last load is kept.') + gf_card() + prov(PROV_S))
add('sources-e-npi.html', 'NPI Registry card, Working', 'CMS\'s NPI Registry Working for lookups; its monthly file off since Nov 2.',
    intro('<b>(e) The NPI Registry (CMS), Working.</b> Its lookups work; its monthly file has been off since Nov 2, by Jason.') + np_card() + prov(PROV_S))
add('sources-f-irs.html', 'IRS card: the file changed shape', 'The IRS file changed shape: the load stops before it sends a row; the last load is kept.',
    intro('<b>(f) The IRS file changed shape (02 row 38b).</b> The shape is checked before any write; nothing is deleted, and the last load is kept.') + ir_card() + prov(PROV_S))
build_index()
