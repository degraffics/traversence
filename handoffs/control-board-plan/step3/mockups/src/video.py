# The video worker's card (step 3, 03-cards §23): a Service hosted on Railway. Static mockups; not site code.
import kit
from sources import *  # every earlier page is generated on import

VW = 'The video worker'

kit.SETUP['video'] = dict(
    provider='Railway', notice='Setup capture: Sign in not recorded · Keys: why not recorded',
    keys=[('VIDEO_WORKER_TOKEN', '.env and Railway (the same value in both)', 'the worker\'s pass into /api/video/jobs.php and results.php'),
          ('TRAVERSENCE_SITE', 'Railway', 'the site it asks (https://traversence.com by default)'),
          ('RUN_SECONDS, JOBS_PER_ASK', 'Railway', 'a run\'s length (240 s) and jobs per ask (2)'),
          ('VIDEO_WORKER_PAUSED', '.env (Settings)', 'pauses the jobs on the site\'s side; staging sets it')],
    keys_note='The worker never holds the file store\'s keys: it reads and writes through short-lived links. Names only.',
    slots=kit.SETUP['railway']['slots'])

VW_RADIUS = """<details><summary>If the video worker stops: its Incident Radius</summary>
<div class="rad"><div class="h"><b>Held</b><ul><li>New 720p and 1080p copies: they queue</li></ul></div>
<div class="o"><b>On a backup</b><ul><li>Each posted video still plays: its original, until its copies are made</li></ul></div>
<div class="u"><b>Unaffected</b><ul><li>Everything else: posting, the feeds, the crawler and W0 (Railway's other services), the site</li></ul></div></div>
<div class="sub">Not a critical part (Jason: <code>ControlRegister::CRITICAL</code> leaves out the video worker). Its storage is the file store: <a href="aws-a-working.html">the AWS card</a> (S3, until the move to R2).</div></details>"""

STAGE_V = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check now · Pause · Take out (stage 6) · Deactivate (stage 7)</span>'
STAGE_VT = '<span class="st">Stage 5 of 8 · Operational</span><span class="nx">Next allowed: Check and start again · Acknowledge · Take out (stage 6)</span>'
SPEND_V = '<span class="spend"><span>Its cost: Railway\'s compute</span><b>use not priced yet</b><span class="why">counted on Railway\'s card</span></span>'
RESTART = (light('a', 'Restart rule differs') + ' Railway says <b>On failure, up to 10</b>; the repo says <b>Never</b> (<code>workers/video/railway.json</code>). '
           '<span class="why">For Jason to choose; the next deploy from the repo sets Never.</span>')


def vw_z2(q=None, q_cls=''):
    return rows([
        ('Copies of posted videos', 'decisions/0153 §8, 0154 §2: a video bigger than 720p gets a job', q or light('g', 'On · Working'),
         'Every 5 minutes (<code>*/5 * * * *</code>), a run of up to 240 s asks for 2 jobs at a time: it fetches the original through a short-lived link, makes a 720p copy for feeds '
         '(and a 1080p one when the poster\'s plan plays it) with ffmpeg, uploads them through short-lived links, and reports. A job is leased 20 minutes; a failure tries again, 3 times. '
         f'The queue: {NR} (needs live data: <code>video_jobs</code> queued, done today, failed).', '', q_cls),
        ('Its endpoints', 'the Site part it calls', '', '<code>/api/video/jobs.php</code> (posted videos waiting for their copies) · <code>/api/video/results.php</code> (each copy checked in the store before it\'s recorded)', '', ''),
        ('Its storage', 'the file store', '', 'Originals and copies sit side by side (parts <code>v720</code>, <code>v1080</code>): <a href="aws-a-working.html">the AWS card</a> (S3 until the move to R2).', '', ''),
    ])


def vw_z3(hb=None, hb_cls=''):
    return rows([
        ('Its heartbeat', 'spec: it checks in after each run; silent past its window (15 minutes, row 50) only this service trips', '', hb or f'Checked in at {sc("8:50 AM MST")} (spec: not built today)', '', hb_cls),
        ('On Railway', 'service <code>video-worker</code>, root <code>/workers/video</code>', '',
         'Latest deploy <code>628caa0d</code> SUCCESS, Nov 17, 6:30 PM MST (commit <code>430d4c3</code>). Next run 8:55 AM MST (Railway, read 8:51 AM MST).<div style="margin-top:.3rem">' + RESTART + '</div>', '', 'hl'),
        ('Its key', '<code>VIDEO_WORKER_TOKEN</code>, in .env and on Railway', '', 'not read yet (a run without it prints “nothing to do”)', '', ''),
    ]) + '<div class="sub" style="margin-top:.4rem">Its keys and settings, by name and place: Setup capture (zone 1).</div>'


VW_Z4 = act_rows(b('Check now') + b('Pause') + '<span class="why">new copies queue; originals play</span>', 'Railway', railway_slots(),
                 then='It runs on Railway: Railway\'s slots. Its own deploy log and schedule are on <a href="railway-b-working.html">Railway\'s card</a>.')


def vw_card(state, z2q=None, z3hb=None, thread_html=None, fault=None, stage=STAGE_V, z2cls='', z3cls=''):
    return card(VW, 'video', 'Not critical: no duty stops without it', state, stage, SPEND_V, VW_RADIUS,
                vw_z2(z2q, z2cls), vw_z3(z3hb, z3cls), VW_Z4, thread_html, fault, role='Service · hosted on Railway')


PROV_V = [
    'The repo: <code>workers/video/video.py</code> (every 5 minutes, 240 s, 2 jobs an ask, ffmpeg 720p and 1080p, never the store\'s keys), <code>workers/video/railway.json</code> (cron <code>*/5 * * * *</code>, restart Never).',
    'The live code, mirror <code>aeb5f31</code>: <code>VideoJobs.php</code> (TRIES 3, LEASE_MIN 20, OUTPUTS v720 and v1080, <code>paused()</code>); <code>MemberPosts.php</code> (feeds play the 720p copy, else the original); '
    '<code>ControlRegister.php</code> (the part <code>video-worker</code>, endpoints video/jobs and video/results; not critical).',
    'Railway\'s API (read-only, 2026-11-21 8:51 AM MST): deploy, commit, next run and its restart rule “On failure, up to 10”. No variables read.',
    'Step 2: root cause 4, “The video worker has no heartbeat”. Words: 02 rows 47–50 (heartbeat), 49 (one job failing is no fault on the worker), §3b. Scenario values dotted copper.',
]


def vw_silent():
    th = thread('The video worker hasn\'t checked in',
                light('r', 'Paused by the system') + f': no heartbeat since {sc("8:50 AM MST")}. Its 3 tries failed; new video copies queue.',
                '<ul><li>New 720p and 1080p copies: they queue</li></ul>', '<ul><li>Posted videos play their originals</li></ul>',
                '<ul><li>The crawler and W0 Indexer (Railway\'s other services)</li><li>Posting, feeds, the site</li></ul>',
                'Silent past its window (15 minutes): a warning, then a trip after 3 tries (row 50). Only this service trips: Railway as a host is up (its other services report).',
                b('Check and start again', True) + b('Acknowledge'),
                railway_slots(fix='console'),
                'Fix it: <b>Railway\'s console</b>, the video-worker service: its last deploy and its cron runs. Then Check and start again: it passes when the worker checks in.',
                f'<dl class="dl"><dt>Last check-in</dt><dd>{sc("8:50 AM MST")}, run done, {sc("1")} video</dd><dt>Window</dt><dd>15 minutes (three 5-minute runs)</dd>'
                '<dt>Rule</dt><dd>02 row 50 (in rows 47–48\'s shape); the heartbeat is spec (step 2, root cause 4)</dd></dl>'
                + today_words(['Today: nothing. The video worker has no heartbeat; a stopped worker is seen only when someone notices copies aren\'t made.',
                               '“The video worker hasn\'t checked in since <time>” (§4.8 row 50), then “Paused by the system: new video copies queue.”']), at='Railway')
    return vw_card(light('r', 'On · Paused by the system (no heartbeat)'), z2q=light('h', 'Held: copies queue'), z2cls='hl',
                   z3hb=f'<b>Silent since {sc("8:50 AM MST")}</b>: 3 runs missed', z3cls='hr', thread_html=th, fault='r', stage=STAGE_VT)


def vw_job():
    note = (f'<div class="stage" style="font-size:13px">{light("n", "Notice")} One video\'s copies failed: Moment {sc("#4182")}\'s video, after 3 tries '
            f'(“ffmpeg: {sc("Invalid data found when processing input")}”). It plays its original; nothing else waits. No alarm: a job failing isn\'t a fault on the worker (row 49\'s shape).</div>')
    z2q = light('g', 'On · Working') + f'<div class="sub">{sc("1")} job failed after 3 tries · the rest done</div>'
    tw = today_words(['Today: the job\'s row keeps its error (<code>video_jobs.error</code>, status “failed”); nothing on the board.',
                      'New: a Notice on this card naming the video, with the post linked, and nothing held.'])
    return card(VW, 'video', 'Not critical: no duty stops without it', light('g', 'On · Working'), STAGE_V, SPEND_V + note, VW_RADIUS,
                vw_z2(z2q, 'hl'), vw_z3(), act_rows(b('Check now') + b('Try this video again') + b('Pause'), 'Railway', railway_slots(),
                                                   then='Try this video again queues it once more; if it fails, the original stays.') + tw, role='Service · hosted on Railway')


add('video-a-working.html', 'Video worker card, Working', 'The video worker on Railway: copies of posted videos, its heartbeat (spec), its restart rule for Jason.',
    intro('<b>(a) The video worker, Working.</b> A Service hosted on Railway: 720p and 1080p copies of posted videos (decisions/0153 §8). Its heartbeat is spec. Its restart rule differs between Railway and the repo: amber, for Jason.')
    + vw_card(light('g', 'On · Working')) + prov(PROV_V))
add('video-b-silent.html', 'Video worker card: silent', 'No heartbeat past its window: only the video worker trips; copies queue, originals play.',
    intro('<b>(b) Silent: no heartbeat (row 50).</b> Only this service trips; new copies queue and each video plays its original.') + vw_silent() + prov(PROV_V))
add('video-c-job-failed.html', 'Video worker card: one copy failing', 'One video\'s ffmpeg error: only that video waits; no alarm.',
    intro('<b>(c) One copy failing (an ffmpeg error).</b> Only that video waits, on its original; the worker stays Working.') + vw_job() + prov(PROV_V))
build_index()
