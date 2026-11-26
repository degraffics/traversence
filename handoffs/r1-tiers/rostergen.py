#!/usr/bin/env python3
"""R1 of decisions/0180 (rename and roster): writes the release's migration and its Roll back.
  app/migrations/2026-11-26_0180_tiers_roster.sql  the stored tier 3 (Founder) becomes 5 (Tier 5 · Founder ruling, 0180 §1);
      the Team becomes 0180's 32 roles and 12 advocates (its appendix and Jason's answers): today's rows renamed in place
      (only where the name is still the seed's: a person's own words are never written over), the new roles inserted (only
      where the slug isn't there: 0162's note, nothing that already exists), all 32 active (0180's note "All roles on at R1", #1459,
      Jason, 2026-11-26), each switched on logged on its History,
      the roles 0180 replaced retired (kept so past tickets and runs still name them; never shown, never routed to).
  app/rollbacks/2026-11-26_0180_tiers_roster.sql   Roll back: the tiers back to 3, the Team back to Piece 2's seed.
Run: python3 rostergen.py <app dir>"""
import sys, os
q = lambda v: "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"
F, X, M, S, A = 'founder', 'executive', 'managers', 'specialists', 'advocates'
SEED = 'the 0180 seed'
# Piece 2's seed as it is on live (its rows' layer, kind, number, reporting line, highest tier, status; sort = its row x 10): Roll back's target
OLD = {}
for i, l in enumerate('''founder|leadership|founder|1||3|active
translator|leadership|operator|2|founder|2|active
cognitive-load|leadership|keeper|3|founder|1|standby
legal-risk|leadership|keeper|4|founder|1|standby
mission-auditor|leadership|keeper|5|founder|1|standby
project-manager|operations|operator|6|founder|2|active
ops-policy|operations|keeper|7|founder|2|standby
ops-reconciliation|operations|auditor|8|project-manager|2|active
historian|operations|keeper|9|project-manager|1|standby
orders|operations|operator|10|project-manager|1|standby
finance|operations|operator|11|project-manager|1|standby
growth|operations|operator|12|project-manager|1|standby
member-support|operations|operator|13|project-manager|1|standby
directory-curator|operations|operator|14|project-manager|1|active
system-architect|build|operator|15|project-manager|2|active
ux-prototyper|build|operator|16|project-manager|1|standby
senior-designer|build|operator|17|project-manager|1|standby
print-vendor|build|operator|18|project-manager|1|standby
content-editor|build|operator|19|project-manager|1|active
guide-auditor|build|auditor|20|project-manager|1|standby
builder|build|operator|21|system-architect|2|active
qa-security|build|auditor|22|project-manager|2|active'''.split('\n')):
    sl, dp, kd, no, to, tm, st = l.split('|'); OLD[sl] = (dp, kd, no, to, int(tm), st, (i + 1) * 10)
# slug: (0180 #, name, layer, kind, made_of, mandate, rules, reports_to, tier_max, old name or None for new, old spec_no, old department, old reports_to, old tier_max, old status)
R = {}
def role(slug, no, name, layer, kind, made, mandate, rules, to, tmax, old=None):
    R[slug] = (no, name, layer, kind, made, mandate, rules, to, tmax, old)
C = '0180 §1: the five tiers\n0180 §4: the high-risk territories and the hard money line'
role('founder', '1', 'Founder / Chair', F, 'founder', 'human', 'Strategic direction and the Tier 5 Founder rulings, answered as 4-part Decision Briefs; never a routine step.', '0180 §3: what reaches the Founder', '', 5, ('Executive Lead & Founder', '1', 'leadership', '', 3, 'active'))
for slug, no, name, m in [('ceo', '2', 'Chief Executive Officer', 'Leads the executive suite; delivers every Tier 5 Decision Brief to the Founder and settles executive deadlocks below it.'),
                          ('coo', '3', 'Chief Operating Officer', 'Operations: the Project Manager\'s and Customer Service Manager\'s lanes; reviews Tier 4 in its scope.'),
                          ('cfo', '4', 'Chief Financial Officer', 'Money and billing: the Accounting Manager\'s lane; reviews every change that touches money (0180 §4.1).'),
                          ('cto', '5', 'Chief Technology Officer', 'Engineering: the IT Manager\'s lane; reviews data, address and privacy changes in Tier 4.'),
                          ('cio', '6', 'Chief Information Officer', 'Information and data: with the CTO over the IT Manager\'s lane; reviews data mutations and privacy in Tier 4.'),
                          ('cmo', '7', 'Chief Marketing Officer', 'Marketing, editorial and sales: three directors\' lanes; guards the mission (the duty of the Mission & Brand Integrity Auditor).')]:
    role(slug, no, name, X, 'executive', 'ai', m + ' Runs the Prior Ruling Check before anything goes to the Founder.', C + '\n0180 §3: the Prior Ruling Check', 'founder' if slug == 'ceo' else 'ceo', 4)
role('project-manager', '8', 'Project Manager', M, 'manager', 'ai', 'Swimlane: delivery. Moves each ticket through Planning, Building, Implementation and Confirmation when its gate passes; flags stalls.', 'piece2-team.md §5: one step a run\nCLAUDE.md: Every action is a task\n' + C, 'coo', 3, ('Project Manager', '6', 'operations', 'founder', 2, 'active'))
role('customer-service-manager', '9', 'Customer Service Manager', M, 'manager', 'ai', 'Swimlane: members and businesses. Support, onboarding edge cases, orders and fulfilment; routes advocates\' experience tickets for its screens.', C, 'coo', 3)
role('accounting-manager', '10', 'Accounting Manager', M, 'manager', 'ai', 'Swimlane: accounting. Verifies billing workflow fixes and reconciliations; prepares refund records only (0178: a refund runs with Jason\'s OK).', '0178: money never has Undo\n' + C, 'cfo', 3)
role('it-manager', '11', 'IT Manager', M, 'manager', 'ai', 'Swimlane: engineering. Builds, QA, releases, schema and the guards; verifies crawler batch fixes.', '0159 §5: every build is a release\n0162: migrations\n' + C, 'cto', 3)
role('editorial-director', '12', 'Editorial Director', M, 'manager', 'ai', 'Swimlane: editorial. Content, copy, public design and briefs; may hold a screen for clutter (0180 §8).', '0057: the FAQ\nbrand.md\n' + C, 'cmo', 3)
role('marketing-director', '13', 'Marketing Director', M, 'manager', 'ai', 'Swimlane: marketing. Brand, campaigns, SEO, taxonomy and funnels.', C, 'cmo', 3)
role('sales-director', '14', 'Sales Director', M, 'manager', 'ai', 'Swimlane: sales. Leads, merchant onboarding, partnerships and retention.', C, 'cmo', 3)
def spec(slug, no, name, kind, made, m, rules, to, old=None):
    role(slug, no, name, S, kind, made, m, rules, to, 2, old)
spec('cognitive-load', '15', 'Cognitive Load & Simplicity Guardian', 'keeper', 'ai', 'One screen, one clear next action: trims scope and screens that grow heavy; may hold a screen for clutter (0180 §8).', 'CLAUDE.md: Every action is a task\ndesign/table-standard.md', 'project-manager', ('Cognitive Load & Simplicity Guardian', '3', 'leadership', 'founder', 1, 'standby'))
spec('ops-policy', '16', 'Operations & Policy Director', 'keeper', 'hybrid', 'Owns the operating procedures, the routing policy and the time limits.', '0163: operations', 'project-manager', ('Operations & Policy Director', '7', 'operations', 'founder', 2, 'standby'))
spec('orders', '17', 'Order & Fulfillment Lead', 'operator', 'hybrid', 'Order checks, proofs, work orders, fulfilment, and press preflight and trade vendors (the Preflight, Print & Trade Vendor Specialist\'s duties). Reading only: money moves only with a person\'s press.', '0178: money never has Undo\n0180 §4.1: the hard money line', 'customer-service-manager', ('Order & Fulfillment Processing Agent', '10', 'operations', 'project-manager', 1, 'standby'))
spec('member-support', '18', 'Customer Support Specialists', 'operator', 'hybrid', 'Member and business support, reports and standing notices, and the friction they show.', '', 'customer-service-manager', ('Customer Service & Member Support Agent', '13', 'operations', 'project-manager', 1, 'standby'))
spec('finance', '19', 'Finance, Tax & Billing Ops', 'operator', 'hybrid', 'Tax rules, sandbox payment isolation, billing accuracy and refund records. Read and prepare only: money moves only with a person\'s press.', '0145: no test moves real money\n0180 §4.1: the hard money line', 'accounting-manager', ('Finance, Tax & Billing Controller', '11', 'operations', 'project-manager', 1, 'standby'))
spec('system-architect', '20', 'System Architect', 'operator', 'ai', 'Writes each Building ticket\'s build brief and hands it to a build session; schema, migrations and contracts.', '0159 §5: every build is a release\n0162: migrations', 'it-manager', ('System Architect', '15', 'build', 'project-manager', 2, 'active'))
spec('builder', '21', 'Build & QA Sessions', 'operator', 'ai', 'The Claude Code sessions a build brief goes to: build within the approved scope through git and releases, and run each Confirmation ticket\'s checks again (the Technical QA & Security Gatekeeper\'s duties). Never on the platform.', '0159 §5: every build is a release\nCLAUDE.md: End to end, verified', 'it-manager', ('Full-Stack Builder', '21', 'build', 'project-manager', 2, 'active'))
spec('staging-release', '22', 'Staging & Release Workers', 'keeper', 'ai', 'The release pipeline and the before-and-after check, as a role: staging, the advocates\' looks, rule-based Roll back.', '0165: staging\n0179 rule 2: the before-and-after check', 'it-manager')
spec('ops-reconciliation', '23', 'Operations & Reconciliation Manager', 'auditor', 'hybrid', 'Runs the daily health and drift audit: confirms a finding on its second look before it reaches anyone.', 'piece2-team.md §6: the audit\n0145: the site audit', 'it-manager', ('Operations & Reconciliation Manager', '8', 'operations', 'project-manager', 2, 'active'))
spec('schema-guards', '24', 'Schema & Migration Guards', 'keeper', 'ai', 'The zip check\'s migration rules: additive is Tier 2; UPDATE, DELETE, DROP, MODIFY, RENAME, TRUNCATE and CHANGE are a high-risk territory.', '0162: migrations\n0180 §4: major data mutations', 'it-manager')
spec('privacy-guards', '25', 'Privacy & Decision 0066 Guards', 'keeper', 'ai', 'Privacy, identity and tribal nations\' land: the held-names check and every surface that could expose them; legal, consent and IP first pass (the AI Legal Risk Checker\'s duties).', '0066: nations\' land and sensitive sites\n0164: nothing private to free external APIs', 'it-manager')
spec('content-editor', '26', 'Front-End Content Writers', 'operator', 'ai', 'Microcopy, onboarding and error states in a plain voice; the FAQ; directory accuracy and gaps (the Regional Directory & Data Curator\'s duties) and guide quality (the AI & Dynamic Guide Quality Auditor\'s).', '0057: the FAQ grows with every build\n0164: never overwrite what a person entered', 'editorial-director', ('Content & Copy Editor', '19', 'build', 'project-manager', 1, 'active'))
spec('ux-prototyper', '27', 'Public UI/UX Designers', 'operator', 'ai', 'Wireframes, flows and visual assets in the brand\'s tokens (the Senior Designer\'s duties too).', 'brand.md\ndesign/css-theme-brand.md', 'editorial-director', ('UI/UX Prototyper & Flow Designer', '16', 'build', 'project-manager', 1, 'standby'))
spec('translator', '28', 'Executive Translator & Briefing Lead', 'operator', 'ai', 'Turns technical and audit activity into plain English. Drafts the 4-part Decision Brief for every Tier 5 ticket; never a raw log.', '0180 §1: the 4-part Decision Brief', 'editorial-director', ('Executive Translator & Briefing Lead', '2', 'leadership', 'founder', 2, 'active'))
spec('brand-strategists', '29', 'Brand & Campaign Strategists', 'operator', 'ai', 'Brand and campaign work within the zero-commission commitment.', 'brand.md', 'marketing-director')
spec('seo-analysts', '30', 'SEO, Taxonomy & Funnel Analysts', 'operator', 'ai', 'SEO, the taxonomy and the funnels, from privacy-safe counts only.', '0164: the Knowledge Engine', 'marketing-director')
spec('growth', '31', 'Merchant Onboarding Specialists', 'operator', 'hybrid', 'Business claims and merchant onboarding.', '', 'sales-director', ('Growth, Merchant Onboarding & Partnerships Agent', '12', 'operations', 'project-manager', 1, 'standby'))
spec('partnerships', '32', 'Partnerships & Retention Leads', 'operator', 'hybrid', 'Partners and retention.', '', 'sales-director')
assert len(R) == 32, len(R)
# retired: no seat in the 32 (0180's answer 1 and the appendix's Retired row), plus the two rows folded into one role each
RETIRED = {'legal-risk': ('AI Legal Risk Checker & Auditor', 'standby', 'privacy-guards'), 'mission-auditor': ('Mission & Brand Integrity Auditor', 'standby', 'cmo'),
           'historian': ('Historian', 'standby', 'editorial-director'), 'directory-curator': ('Regional Directory & Data Curator', 'active', 'content-editor'),
           'print-vendor': ('Preflight, Print & Trade Vendor Specialist', 'standby', 'orders'), 'guide-auditor': ('AI & Dynamic Guide Quality Auditor', 'standby', 'content-editor'),
           'qa-security': ('Technical QA & Security Gatekeeper', 'active', 'builder'), 'senior-designer': ('Senior Designer', 'standby', 'ux-prototyper')}
ADV = ['guest', 'new-member', 'member', 'contributor', 'steward', 'client', 'business-owner', 'business-manager', 'business-accountant', 'business-sales', 'business-viewer', 'staff-views']
up, down = [], []
# 0180's note "The CEO builds the team" (Jason, 2026-11-26): active at R1 are the CEO (2) and today's working seats; every other
# role starts on standby, its work going up its reports-to line (TeamRoster::doer). The advocates stay active (views only).
# All roles on at R1 (0180's note, #1459, Jason, 2026-11-26: "All agents should be on and ready to deploy their duties and
# responsibilities"): it replaces the standby set; Put on standby stays the CEO's way back, logged
ACTIVE = {str(n) for n in range(1, 33)}
st = lambda no: 'active' if no in ACTIVE else 'standby'
H = '-- decisions/0180 R1 (rename and roster). Written by handoffs/r1-tiers/rostergen.py. Safe to run twice.'
up.append(H + '\n-- 1. The stored tier: 3 meant the Founder; it is 5 now (Tier 5 · Founder ruling, 0180 §1), one meaning per number.')
up.append("UPDATE control_gauge_tasks SET tier = 5 WHERE tier = 3;")
down.append(H.replace('R1 (rename and roster)', 'R1, its Roll back') + '\n-- 1. The tier back to 3 (the code put back reads 3 as the Founder).')
down.append("UPDATE control_gauge_tasks SET tier = 3 WHERE tier = 5;")
up.append('-- 2. The 32 roles: renamed in place where the name is still the seed\'s, then the layers, numbers, reporting lines and tiers;\n-- active: the CEO and today\'s working seats (1, 2, 8, 20, 21, 23, 26, 28), every other role on standby (0180, "The CEO builds the team").')
down.append('-- 2. The roles back to Piece 2\'s seed.')
cols = 'slug, name, department, kind, made_of, spec_no, mandate, rules, stages, acts_level, acts_dept, tier_max, test_account, reports_to, status, sort, updated_by'
for n, (slug, (no, name, layer, kind, made, mand, rules, to, tmax, old)) in enumerate(R.items()):
    sort = (n + 1) * 10
    if old:
        oname, ono, odept, oto, otmax, ost = old
        if oname != name:
            up.append(f"UPDATE team_roster SET name = {q(name)} WHERE slug = {q(slug)} AND name = {q(oname)};")
            down.append(f"UPDATE team_roster SET name = {q(oname)} WHERE slug = {q(slug)} AND name = {q(name)};")
        up.append(f"UPDATE team_roster SET department = {q(layer)}, kind = {q(kind)}, spec_no = {q(no)}, reports_to = {q(to)}, tier_max = {tmax}, status = {q(st(no))}, sort = {sort} WHERE slug = {q(slug)};")
        dp, kd, ono2, oto2, otm, ost2, osort = OLD[slug]
        down.append(f"UPDATE team_roster SET department = {q(dp)}, kind = {q(kd)}, spec_no = {q(ono2)}, reports_to = {q(oto2)}, tier_max = {otm}, status = {q(ost2)}, sort = {osort} WHERE slug = {q(slug)};")
    else:
        vals = ', '.join([q(slug), q(name), q(layer), q(kind), q(made), q(no), q(mand), q(rules), "''", "''", "''", str(tmax), "''", q(to), q(st(no)), str(sort), q(SEED)])
        up.append(f"INSERT INTO team_roster ({cols}) SELECT {vals} FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM team_roster WHERE slug = {q(slug)});")
        down.append(f"DELETE FROM team_roster WHERE slug = {q(slug)} AND updated_by = {q(SEED)};")
up.append('-- 3. Retired: kept so past tickets and runs still name them; never shown, never given work (0180, #1441).')
down.append('-- 3. The retired roles back to their status.')
for slug, (oname, ost, home) in RETIRED.items():
    up.append(f"UPDATE team_roster SET status = 'inactive', reports_to = {q(home)} WHERE slug = {q(slug)};")
    dp, kd, ono2, oto2, otm, ost2, osort = OLD[slug]
    down.append(f"UPDATE team_roster SET status = {q(ost2)}, reports_to = {q(oto2)} WHERE slug = {q(slug)};")
up.append('-- 4. The 12 advocates, A1–A12: views only, active, beside the layers.')
down.append('-- 4. The advocates back to their spec numbers.')
for i, a in enumerate(ADV):
    up.append(f"UPDATE team_roster SET spec_no = 'A{i + 1}', reports_to = 'builder', status = 'active', sort = {1000 + i * 10} WHERE slug = 'advocate-{a}';")
    down.append(f"UPDATE team_roster SET spec_no = '{23 + i}', reports_to = 'qa-security', status = 'active', sort = {(23 + i) * 10} WHERE slug = 'advocate-{a}';")
app = sys.argv[1]
# 7. A Work ticket for the IT Manager's lane (the session manager, 2026-11-26): two admin pages time out at 45 s on staging.
ITEM = 'Two admin pages time out on staging (sources, crawler review)'
WHY = ("In #305's before-and-after look (runs 45835669559 and 45842645867, 2026-11-26), /admin/sources.php (staff, phone and desktop) "
       "and /admin/crawler-review.php (phone) timed out at 45 s on staging in every pass, the retry too. A page that slow is a defect "
       "to find, not check noise. For the IT Manager's lane (decisions/0180 §5).")
up.append('-- 7. A Work ticket for the IT Manager\'s lane: two admin pages time out on staging (added once).')
up.append(f"INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, rel) SELECT UTC_TIMESTAMP(), '', 'area:backlog', {q(ITEM)}, 'fix', {q(ITEM)}, 'task', '', 'the build session', 0, {q(WHY)}, 'the before-and-after check, release #305, 2026-11-26', '' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM control_gauge_tasks WHERE kind = 'fix' AND gauge = 'area:backlog' AND item = {q(ITEM)});")
# 8. Two more Work tickets, each added once (the session manager and the CEO, 2026-11-26)
def ticket(item, why, source):
    up.append(f"INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source, rel) SELECT UTC_TIMESTAMP(), '', 'area:backlog', {q(item)}, 'fix', {q(item)}, 'task', '', 'the build session', 0, {q(why)}, {q(source)}, '' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM control_gauge_tasks WHERE kind = 'fix' AND gauge = 'area:backlog' AND item = {q(item)});")
up.append('-- 8. Two more Work tickets (added once): the 6:13 jobs that never started; the before-and-after check\'s false blocks.')
ticket('Two 6:13 jobs never started on live (Plans and renewals, Earned storage)',
       "On 2026-11-26 live's billing_tick (Plans and renewals) and earned_storage_tick have no run at 6:13 MST; Guests' 30 days at the same "
       "minute ran, and both cron lines are there. Alarm #776 tripped at 13:30 UTC and mailed the Founder (a live alarm: staging's mail is "
       "held by Staging::holdMail). Likely the 6:13 burst: nine live daily jobs and staging's copies at the same minute "
       "(Scheduler::syncStaging copies live's minute) against max_user_connections 25; storage/scheduler-missed.json would show a refused "
       "start. No money harm: the next run takes everything due on or before its day. For the IT Manager's lane (decisions/0180 §5); "
       "the fix to look at goes with the Scheduler bundle after R2: spread the 6:13 starts, staging's copies at live's minute + 7.",
       'live alarm #776, 2026-11-26 (the live read, runs 57-61)')
ticket("Before-and-after check: false blocks from staging's live data",
       "#305 to #307 were withdrawn on data drift, state lines, to-do links appearing and slow-page timeouts. Lane: IT Manager (11); "
       "owner: Staging & Release Workers (22, decisions/0180's appendix); builder: Build & QA Sessions (21). The check is paused for four "
       "releases only and is back on before any agent-built release and before R2. Done when it passes a clean admin-only release on "
       "today's staging, and the fixture proves it.",
       "decisions/0179's note of 2026-11-26, 18:30 UTC (#1456)")
# 9. Each role switched on, on its History (the board's logbook, as TeamRoster::setStatus writes it), once each: a role that
#    was on standby says so; a new role says it was added active; one already active has nothing to say (words follow the state)
up.append('-- 9. Each role switched on, on its History (added once): "All roles on at R1" (decisions/0180, #1459).')
down.append('-- 9. Those History lines taken back off.')
WHO = 'Jason (decisions/0180, #1459)'
WHY = 'All roles on at R1 (Jason, 2026-11-26: "All agents should be on and ready to deploy their duties and responsibilities"). Put on standby is its way back.'
for slug in R:
    if slug in OLD and OLD[slug][5] == 'standby': what = f'Team role {slug}: on standby → active'
    elif slug not in OLD: what = f'Team role {slug}: added, active'
    else: continue
    up.append(f"INSERT INTO control_log (at, tz, kind, proc, what, who, why) SELECT UTC_TIMESTAMP(), '', 'control', '', {q(what)}, {q(WHO)}, {q(WHY)} FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM control_log WHERE what = {q(what)} AND who = {q(WHO)});")
    down.append(f"DELETE FROM control_log WHERE what = {q(what)} AND who = {q(WHO)};")
# 5. Staging's Team runner on for everyone, on staging's database only (CLAUDE.md's standing OK: "the build session may switch
#    on staging's Team runner"; Jason, 2026-11-26, decisions/0180's note "run it now"). The tree's approval and the ceiling
#    stay his own presses (CLAUDE.md, "The organization runs now"): nothing here writes them.
up.append('-- 5. Staging\'s Team runner on for everyone, on staging\'s database only (its name ends stg: StagingSite::stg).')
up.append("INSERT INTO feature_switches (switch_key, state, share_pct, killed, note, updated_by, updated_at) SELECT 'team_runner', 'on', 0, 0, 'Switched on for staging by the build (CLAUDE.md standing OK; decisions/0180 run it now)', NULL, NOW() FROM DUAL WHERE DATABASE() REGEXP 'stg(_[a-z]+)?$' ON DUPLICATE KEY UPDATE state = 'on', killed = 0, note = VALUES(note), updated_at = NOW();")
os.makedirs(app + '/migrations', exist_ok=True); os.makedirs(app + '/rollbacks', exist_ok=True)
open(app + '/migrations/2026-11-26_0180_tiers_roster.sql', 'w').write('\n'.join(up) + '\n')
open(app + '/rollbacks/2026-11-26_0180_tiers_roster.sql', 'w').write('\n'.join(down) + '\n')
print(len(R), 'roles,', len(RETIRED), 'retired,', len(ADV), 'advocates')
