import json
LP = 'research/launch-plan-jan1-2026-11-26.md'
DUE_GATES = 'Due: all six green 5 runs in a row by Mon Dec 21, 2026; code freeze Dec 22; go/no-go Thu Dec 31, 2026 (0181 §1, launch plan §5)'
def c(what, why, source, done='', adrs=(), prio=2, **k):
    d = dict(what=what, why=why, source=source, done=done, adrs=list(adrs), priority=prio); d.update(k); return d
goals = [
 dict(key='records', what='Goals and records with the roles, live by Mon Nov 30 13:00 UTC', owner='Chief Executive Officer (2)',
  why='Jason, 2026-11-27: "Yes, approve the plan… up and running by Monday morning 6 am". No goal, ruling or order lives only in a session.',
  source='CLAUDE.md "Goals and records live with the roles" (#1552), recorded as Operations & Policy (16)',
  goal='Each long-term goal is a parent task with an owner from 0180\'s appendix, a measure, a due date and its decision, and every ticket links to its goal',
  done='Measure: every goal is a parent task on live with owner, measure, due and decision, and every open ticket has a goal. Due: Mon Nov 30, 2026 13:00 UTC (06:00 MST)',
  adrs=['0180','0179'], priority=1, children=[
   c('Due dates on tasks: a goal\'s date in a field of its own', 'Gap: control_gauge_tasks has no due-date column, so a goal\'s due date is written in its Done when for now', 'GaugeTasks.php ensure() (no due column) · CLAUDE.md #1552', 'A task has a due date the goal check can read', ['0180'], 1),
   c('The daily goal check: a ticket for the owner when a goal\'s tickets sit 3 days or its date is at risk', 'CLAUDE.md #1552: a live-side $0 daily goal check, escalating by the tiers', 'CLAUDE.md #1552 · decisions/0180 (tiers)', 'The check runs daily on live at $0 and opens the owner\'s ticket', ['0180'], 1),
   c('Every open ticket placed under its goal by the Project Manager (8)', 'Tickets made before the goals existed have no parent; queue order and goal status are the PM\'s', 'CLAUDE.md #1552 · handoffs/decided-not-live.md (for the PM (8))', 'No open ticket without a goal', ['0180'], 1),
 ]),
 dict(key='gate-paths', what='Launch gate 1: every core path passes as guest and member at 390px, 5 runs in a row', owner='IT Manager (11)',
  why='0181 §1 gate 1: "Ready" is measured, never judged', source='decisions/0181 §1 · site-audit/paths.md · ' + LP + ' §4 C',
  goal='Search, explore, read, act, sign up, sign in, claim, edit, post and message pass on live', done='Measure: paths.mjs green 5 runs in a row. ' + DUE_GATES, adrs=['0181','0145'], priority=1, children=[
   c('Repair the path checker: the sign-in selector and the staff two-step (C.2)', 'Without it joining and claiming can\'t be called ready', LP + ' §4 C.2 · tools/path-checker-tests/', 'The checker signs in as guest, member and staff every run', ['0145'], 1),
   c('The device\'s place read as outside one of our areas (C.1)', 'Likely behind the Show Low dead end', LP + ' §4 C.1 · tools/search-tests/device-place.php', 'A device in an area\'s town is that town', ['0060'], 1),
   c('Layout shift: the shared 0.212, then the directory, hub, login and /trip/ (C.5)', 'Core Web Vitals on the core pages', LP + ' §4 C.5', 'CLS under 0.1 on those pages', ['0145'], 2),
   c('Contrast and focus (C.6)', '#8A7355 and location-scope.js:249 against brand.md §4', LP + ' §4 C.6 · brand.md §4', 'Contrast 4.5:1 and a visible focus on those', ['0058'], 2),
   c('Get Local\'s filter at around 10,000 rows (C.7)', 'The filter slows at that size', LP + ' §4 C.7 · 0058 note 2026-11-19', 'Filtering stays quick at 10,000 rows', ['0058'], 3),
   c('The site\'s stylesheet built once with the tailwindcss CLI, in place of the Play CDN', 'Jason, 2026-11-27: "Yes to the stylesheet"', 'handoffs/decided-not-live.md · design/css-theme-brand.md note', 'Every page kind passes the Design reviewer at 1280 and 390 on the built sheet', [], 2),
 ]),
 dict(key='gate-new-paths', what='Launch gate 2: the new paths pass (each 1A town; Shop, plan-buy and Marketplace-buy in the sandbox)', owner='Chief Executive Officer (2)',
  why='0181 §1 gate 2; it crosses the data, front-end and shop lanes, so the CEO holds it', source='decisions/0181 §1 · ' + LP + ' §4 C.3, F.3',
  goal='One path per 1A town (eat, stay, see, read its guide) and the three buying paths, sandbox only', done='Measure: the new paths green 5 runs in a row. ' + DUE_GATES, adrs=['0181','0145'], priority=1, children=[
   c('New paths in paths.mjs: each 1A town, the Shop, plan-buy and Marketplace-buy (C.3)', 'No test moves real money: sandbox only', LP + ' §4 C.3 · tools/path-checker-tests/buy-runners.mjs', 'The paths run on live, failing honestly until they pass', ['0145'], 1),
   c('Plans and the Shop buyable end to end: the QR plan and the Maps add-on (F.3)', 'Priced (commercial §1.3, §2) but can\'t be bought', LP + ' §4 F.3 · commercial.md', 'Each priced item buys in the sandbox; which sell on Jan 1 is Brief D\'s', ['0145'], 2),
 ]),
 dict(key='gate-data', what='Launch gate 3: 1A\'s towns built, each with a guide that passed 0164\'s checks and a person\'s read', owner='Editorial Director (12)',
  why='0181 §1 gate 3: data. Directory accuracy and guides are Front-End Content Writers\' (26) duties (0180 appendix)', source='decisions/0181 §1 and notes (E1, C7, W15) · ' + LP + ' §4 D, E',
  goal='Every 1A town has listings, a guide passed and read, and no "no listings" note where we have listings', done='Measure: the scorecard for 1A green. ' + DUE_GATES, adrs=['0181','0164','0131'], priority=1, children=[
   c('Build the 341 towns within 200 miles of Four Corners, 1A first, from free sources (D.1)', 'Brief B1 and E1: Overture and our OpenStreetMap copy, then NPI, Google\'s check on 1A only, paced', LP + ' §4 D.1 · research/launch-area-nations-and-towns-2026-11-27.md (D.1)', 'Every 1A town has data, then the rings outward', ['0149','0164','0181'], 1),
   c('1A\'s staged backlog and Review cleared, and the identity check on its duplicates (D.2)', 'Duplicates and misfiled towns', LP + ' §4 D.2 · handoffs/duplicates.md', '1A has no open duplicates or staged finds', ['0164'], 1),
   c('W15: every find and listing in its real area, the duplicate areas merged with Undo (D.3)', 'Jason, 2026-11-27, C8: "Yes". It changes existing records, so a Tier 4 review before live', 'decisions/0181 note (W15) · decisions/0170 note', 'No duplicate areas; 1A under its real area', ['0170','0181'], 1, tier=4),
   c('Colorado and Utah open on team-reviewed records, every law read against its official text', 'Jason, 2026-11-27 (R8 note): CO\'s and UT\'s towns open once their records are reviewed; J1 builds Mark read', 'decisions/0181 note · decisions/0164 R8 note · handoffs/j1-jurisdictions/', 'US, AZ, NM, CO and UT records reviewed; J1b removes OPENED', ['0164','0066'], 1),
   c('The nations\' boundaries in CO and UT under 0066 (G1)', 'Story only and held names follow once CO and UT open', 'research/launch-area-nations-and-towns-2026-11-27.md (G1)', 'Every nation\'s land in the area holds what 0066 holds', ['0066'], 2),
   c('Guides in rings: the zone, the ~13 cluster guides, then 1A\'s towns, inside the $40 cap (E.1)', 'Jason, E1 and C7: guides publish themselves at 70; the Editorial lane works drafts under 70 and reads St. Johns first', LP + ' §4 E.1 · decisions/0181 notes (E1, C7) · decisions/0131 note', 'Each 1A town\'s guide at 70 or more, read by the lane', ['0131','0147','0164'], 1),
   c('Hours: owners add their hours at claim (D.4)', 'The one fix the decisions allow (0164\'s Google rule)', LP + ' §4 D.4', 'Claim asks for hours', ['0164'], 3),
   c('One starter community group per 1A town (D.5)', 'Who starts them is open', LP + ' §4 D.5', 'A group per 1A town', ['0047','0136','0137'], 3),
   c('FAQ truth: faq.md:983, an entry on the lock, the repo\'s faq.md in step with live (E.3)', 'The README\'s claim hides the gap', LP + ' §4 E.3', 'The repo\'s faq.md equals the live app/ui/faq.md', ['0057'], 2),
   c('Laws read from their sources: GovInfo\'s API (api.data.gov key), the eCFR and Federal Register', 'Jason, 2026-11-27: "is there an api to federal and states laws?"', 'decisions/0164 note 2026-11-27 (Laws read from their sources)', 'Federal laws read through the API; states through their official sites', ['0164'], 2),
 ]),
 dict(key='gate-safety', link_ids=[51, 32, 13, 45, 57, 42, 11, 10, 56], what='Launch gate 4: access closed, nightly backups with one restore drill, scheduled jobs on time for 7 days', owner='IT Manager (11)',
  why='0181 §1 gate 4: safety. The access hole is closed (live as #314)', source='decisions/0181 §1 · ' + LP + ' §4 B',
  goal='Backups nightly with a restore drilled, and every scheduled job on time 7 days running', done='Measure: 7 days of jobs on time and one restore done. ' + DUE_GATES, adrs=['0181','0163'], priority=1, children=[
   c('Mail: SES_MODE into the Service register, DKIM verified on both domains, DMARC to quarantine (B.5)', 'Production access is granted; only SES_MODE=mock stops sending, Jason\'s call to switch', LP + ' §4 B.5 · decisions/0181 note (SES)', 'DKIM verified, DMARC quarantine, SES_MODE shown in the register', ['0137','0172'], 2),
   c('Capacity: one test of many visitors at once on staging; W0 paced in busy hours (B.6)', 'Gap: no capacity target exists. Suggestion, not a decision: 50 at once, median TTFB under 500 ms', LP + ' §4 B.6', 'The test run and its numbers recorded', ['0163'], 2),
   c('releases_tick every 10 minutes, in the Scheduler bundle (A.2)', 'As Piece 3 designed', LP + ' §4 A.2 · handoffs/piece3-check.md', 'The tick runs every 10 minutes', ['0179'], 2),
 ]),
 dict(key='gate-money', link_ids=[47], what='Launch gate 5: the payments checklist passed in the sandbox, and one real $4.95 purchase and refund by Jason', owner='Accounting Manager (10)',
  why='0181 §1 gate 5. Real money moves only by the Founder\'s press (0180 §4.1)', source='decisions/0181 §1 · ' + LP + ' §4 F',
  goal='Paying works end to end, proved in the sandbox and once for real by Jason', done='Measure: the checklist green and Jason\'s purchase and refund done. ' + DUE_GATES, adrs=['0181','0096','0098','0178'], priority=1, children=[
   c('The payments checklist on the test accounts, sandbox only (F.2)', 'No test moves real money', LP + ' §4 F.2 · site-audit/payments-checklist.md', 'Every line passes in the sandbox', ['0145'], 1),
   c('Marketplace payments live: Stripe platform, Square app, MARKET_PAY_ENV=live (F.4)', 'The keys are entered by Jason in .env, never by an agent', LP + ' §4 F.4 · MarketPay.php', 'A sandbox buy, then live once the keys are in', ['0135'], 2),
   c('Sales tax: Automated Sales Tax in QuickBooks, plan taxability, Arizona TPT (F.6)', 'Founder and accountant', LP + ' §4 F.6', 'Tax set and checked on a sandbox order', [], 2),
   c('Legal texts: the Terms and Seller Agreement reviewed, then published (F.7)', 'Founder and attorney', LP + ' §4 F.7 · legal-seed.php', 'Both published', ['0142'], 2),
 ]),
 dict(key='gate-search', what='Launch gate 6: per-page noindex holds on live, then the lock lifts on Jason\'s date', owner='Marketing Director (13)',
  why='0181 §1 gate 6 and §4, the search lock (SEO, Taxonomy & Funnel Analysts, 30, in this lane)', source='decisions/0181 §1, §4 · ' + LP + ' §4 G',
  goal='Only what should be indexed is, from the day the lock lifts', done='Measure: a guide indexable and a trial listing not, on live. ' + DUE_GATES, adrs=['0181'], priority=1, children=[
   c('Per-page holds: trial and unverified listings, first-look guides, drafts, story-only places, posts and results (G.2)', 'Then page-shell.php prints noindex only for those and staging', LP + ' §4 G.2', 'Each kind carries its noindex on live', ['0034','0070','0147'], 1),
   c('Sitemap and tags: guides, journeys and public landmarks in; canonical, Open Graph and real descriptions (G.3)', 'Landmarks say "Traversence placeholder page." today', LP + ' §4 G.3', 'The sitemap lists them and no page says placeholder', [], 2),
   c('Lift the lock: one release removes the fixed noindex, with the FAQ, then Search Console (G.4)', 'The date and the Search Console account are the Founder\'s (Brief D)', LP + ' §4 G.4', 'The lock off on Jason\'s date and checked on live', ['0181'], 2),
 ]),
 dict(key='launch', link_ids=[58], link=[('area:orders', "Order #12's books")], what='The Jan 1, 2027 launch: front end and back end ready for the market roll-out', owner='Chief Executive Officer (2)',
  why='Jason, 2026-11-26: "both the frontend and back end being ready by Jan 1 for market roll out"', source='decisions/0181 · handoffs/decided-not-live.md (company goal) · ' + LP,
  goal='Go on Dec 31 against the six gates; launch Jan 1 in the 200-mile area', done='Measure: go/no-go passed on Thu Dec 31, 2026. Due: Fri Jan 1, 2027', adrs=['0181'], priority=1, children=[
   c('The Marketplace\'s four sections for Jan 1: Shops and the Aftermarket live, Regional showing what qualifies, Trade "coming"', 'Jason, 2026-11-27: "Yes." The CEO\'s Tier 4 ruling sets how it\'s built', 'decisions/0135 note 2026-11-27 (four sections, How it\'s built for Jan 1)', 'All four named on live as the note says', ['0135','0141','0007'], 1),
   c('Q1: Trade (B2B), the guided flag, and the seller\'s "made here" tick with the Native-made attestation', 'Out of Jan 1 by the CEO\'s Tier 4 ruling', 'decisions/0135 note 2026-11-27 (items 1, 4, 5) · ' + LP + ' §10.1', 'Built in Q1 2027', ['0135','0112'], 3),
   c('Three authenticity guides and their FAQ entries before Jan 1 (Native-made vs made in the region)', 'Jason, 2026-11-27: "NATIVE MADE VS REGIONAL AUTHENTICITY". From the Indian Arts and Crafts Board\'s text; team review', LP + ' §10.2 (1-3, 5), §10.4', 'Three guides and the FAQ entries live, each read by the Editorial lane', ['0164','0135','0057'], 1),
   c('Nations\' visitor rules: after launch, a person checks the four nearest nations', 'Jason, Q9 "A": pointers only at launch; Navajo, Hopi, Zuni, White Mountain Apache after', 'decisions/0181 note (Q9) · decisions/0058 §26', 'The four checked and recorded', ['0066','0058'], 3),
   c('Piece 3: Undo per agent action and the spend limit per agent', 'Switches on 0179 rule 2', 'handoffs/decided-not-live.md · handoffs/piece3-check.md', 'Both live', ['0179'], 3),
 ] + [c('Brief D: ' + w, 'Brief D, by Dec 14–21: money and going to market (Founder rulings, 0180 §3)', LP + ' §7 Brief D (' + str(i) + ')', 'His answer recorded', ['0181','0180'], 2, ticket='resolution', stage='brief', tier=5)
      for i, w in enumerate(['which priced items sell on Jan 1 (F.3)', 'the Marketplace keys (F.4)', 'print at launch: quote by email or self-serve (F.8)', 'Order #12 and M2b (F.9)',
          'verification: the verify number, and a rep\'s in-person visit counting (0070; H.1)', 'real money once (F.5)', 'tax (F.6)', 'legal texts (F.7)', 'promos and outreach spend (H.2)',
          'the revenue mix (H.3)', 'the lock\'s lift date and Search Console (G.4)', 'hosting, after B.6\'s test', 'GITHUB_SYNC_TOKEN, which expires Dec 16'], 1)]),
 dict(key='ladder', what='The pilot\'s success ladder: 4a 10 Core trials convert by ~Apr 2027, 4b ~73 paying (~$69,800 a year) by ~Oct 2027', owner='Chief Marketing Officer (7)',
  why='Jason, 2026-11-27: "Yes" to the CEO\'s proposal (launch plan §9). The pilot is the Ancient Borderlands zone (1A, 1B, 1C)', source='decisions/0181 notes (ladder, pilot, multipliers) · ' + LP + ' §9',
  goal='Not a gate: logged, built after Jan 1 unless it blocks one (CLAUDE.md #1553). The pilot zone clears its ladder; stretch 100 paying by Dec 31, 2027', done='Measure: 4a by ~Apr 2027, 4b by ~Oct 2027, reported weekly (counts only). Due: 4a Apr 30, 2027 · 4b Oct 31, 2027', adrs=['0181','0108','0034'], priority=2, children=[
   c('The weekly ladder report, counts only: visitors, taps per listing, invites, claims, multipliers partnered', 'Reported weekly (0181 note)', 'decisions/0181 note (ladder)', 'The first weekly report filed', ['0181'], 2),
   c('The multiplier kit: a live enhanced page, a prototype store, GO Codes, a leave-behind, members\' Core trials', 'Existing parts only, for the Founder\'s meetings with chambers, guide influencers and venues', 'decisions/0181 note (multiplier kit)', 'The kit ready for the first meeting', ['0034','0139','0094','0081'], 2),
   c('Up to 3 chambers get the Co-Op Master Hub by a comped grant, each with a review date, AI credits capped', 'Jason, 2026-11-27: option A; a named exception to Core-only trials', 'decisions/0181 note (chambers)', 'Each grant recorded with its review date', ['0034','0108','0137'], 2),
   c('Core-only trials and grants during the pilot', 'Jason: "Free trials for core subscribers only"', 'decisions/0181 note (pilot people and trials) · 0108 and 0034 notes', 'Trials offered are Core only', ['0108','0034'], 2),
   c('Maker and product stories, magazine-style, and contributors\' own journeys, from January', 'Jason, 2026-11-27: the magazine model and the privacy line (public role, public act)', 'decisions/0164 note 2026-11-27 · ' + LP + ' §10.2(4)', 'The first stories live under the privacy line', ['0164','0056','0007'], 3),
   c('Relationships with Native makers through the pilot\'s multipliers, never outreach to a nation', 'Free listing, Core trial, their story, GO Codes, 0% on sales', LP + ' §10.3 · decisions/0066 (Q6)', 'Recorded per maker, with their consent to anything private', ['0066'], 3),
   c('The 1A territory: prospects from 1A\'s listings and GO Code promo codes (H.2)', 'Promos and spend are Brief D\'s; no rep for now', LP + ' §4 H.2 · decisions/0181 note (no rep)', 'Prospects seeded and codes ready', ['0108'], 3),
 ]),
 dict(key='transition', what='The transition cutover: our business and conversations on the platform by Dec 15', owner='Project Manager (8)',
  why='Jason, 2026-11-27: "Yes, the transition plan". Tier 3, the PM\'s lane', source='handoffs/decided-not-live.md (the transition plan) · handoffs/work-queue-phase2.md',
  goal='Not a gate: built only where it blocks one before Jan 1 (CLAUDE.md #1553). The Work queue holds every goal, ticket and ruling; sessions start from it', done='Measure: a parallel week, then cutover with nothing living only in a session. Due: Tue Dec 15, 2026', adrs=['0180','0179'], priority=2, children=[
   c('Role queues as the agents\' inbox: each department and role a queue view with counts and SLA', 'Jason, 2026-11-27: "Yes to role queues; mail only, OneDrive stays"', 'handoffs/decided-not-live.md · handoffs/piece2/jason-spec.md §7', 'Each role\'s queue on the Work queue; an alarm emailed to Jason also a row', ['0180'], 2),
   c('The Executive channel: + Add with To: the CEO, answered in its thread', 'Jason, 2026-11-26; built after R2 with phase 2\'s thread', 'handoffs/decided-not-live.md · decisions/0180 notes', 'A post to the CEO answered in its thread', ['0180'], 2),
   c('task-notes.json and task-rulings.json carried into phase 2 release 1', 'The transition plan\'s order', 'handoffs/decided-not-live.md (the transition plan)', 'Both files read into the queue', ['0179'], 2),
 ], link=[('area:control-board', 'Work queue phase 2'), ('area:control-board', "The Work queue redesigned around Jason's four stages")]),
]
json.dump({'goals': goals}, open('items.json', 'w'), indent=1)
print(len(goals), sum(len(g['children']) for g in goals))
