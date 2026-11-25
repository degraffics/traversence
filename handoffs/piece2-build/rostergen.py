#!/usr/bin/env python3
"""Writes app/migrations/2026-11-25_team_roster_seed.sql: the 33 roles of Jason's spec (handoffs/piece2/jason-spec.md §11),
with the statuses approved 2026-11-25 (handoffs/piece2-team.md §7). INSERT ... WHERE NOT EXISTS, so it's safe to run twice
and never overwrites a role someone has edited. Run: python3 rostergen.py <app dir>"""
import sys
R = []  # slug, name, dept, kind, made_of, spec_no, mandate, rules, stages, level, dept_code, tier_max, test_account, reports_to, status
def add(*a): R.append(a)
L, O, B, A = 'leadership', 'operations', 'build', 'advocates'
add('founder', 'Executive Lead & Founder', L, 'founder', 'human', '1', 'Vision holder, mission guardian and final tie-breaker. Answers Tier 3 Decision Briefs; never a routine step.', '0179: Tier 3 always comes to the Founder', 'Tier 3 answers', '', '', 3, '', '', 'active')
add('translator', 'Executive Translator & Briefing Lead', L, 'operator', 'ai', '2', 'Turns technical and audit activity into plain English. Drafts the 4-part Decision Brief for every Resolution ticket; never a raw log.', '0179 rule 3: the Decision Brief\n0164 §0.8: nothing private to Gemini', 'Resolution briefs', 'staff', 'ops', 2, '', 'founder', 'active')
add('cognitive-load', 'Cognitive Load & Simplicity Guardian', L, 'keeper', 'ai', '3', 'One screen, one clear next action: trims scope and screens that grow heavy.', 'CLAUDE.md: Every action is a task\ndesign/table-standard.md', '', '', '', 1, '', 'founder', 'standby')
add('legal-risk', 'AI Legal Risk Checker & Auditor', L, 'keeper', 'ai', '4', 'First pass on legal, privacy, consent, IP and DMCA risk; escalates formal sign-off.', '0179 rule 3: privacy is Tier 3', '', '', '', 1, '', 'founder', 'standby')
add('mission-auditor', 'Mission & Brand Integrity Auditor', L, 'keeper', 'ai', '5', 'Guards the platform\'s promises, zero-commission fairness, tribal nations\' land and the FAQ\'s trust.', '0066: nations\' land and sensitive sites\n0057: the FAQ', '', '', '', 1, '', 'founder', 'standby')
add('project-manager', 'Project Manager', O, 'operator', 'ai', '6', 'Moves each ticket through Planning, Building, Implementation and Confirmation when its gate passes; flags stalls.', 'piece2-team.md §5: one step a run\nCLAUDE.md: Every action is a task', 'All four stages', 'manager', 'ops', 2, '', 'founder', 'active')
add('ops-policy', 'Operations & Policy Director', O, 'keeper', 'hybrid', '7', 'Owns the operating procedures, the routing policy and the time limits.', '0163: operations', '', '', '', 2, '', 'founder', 'standby')
add('ops-reconciliation', 'Operations & Reconciliation Manager', O, 'auditor', 'hybrid', '8', 'Runs the daily health and drift audit: confirms a finding on its second look before it reaches anyone.', 'piece2-team.md §6: the audit\n0145: the site audit', 'The daily audit', 'manager', 'ops', 2, '', 'project-manager', 'active')
add('historian', 'Historian', O, 'keeper', 'ai', '9', 'Keeps the lineage of decisions and architecture, and the living landscape over time, then apart from now.', '0066: nations\' land and sensitive sites', '', '', '', 1, '', 'project-manager', 'standby')
add('orders', 'Order & Fulfillment Processing Agent', O, 'operator', 'hybrid', '10', 'Order checks, proofs, work orders and fulfilment. Reading only: money is always Tier 3.', '0178: money never has Undo\n0179 rule 3: money is Tier 3', '', '', '', 1, '', 'project-manager', 'standby')
add('finance', 'Finance, Tax & Billing Controller', O, 'operator', 'hybrid', '11', 'Tax rules, sandbox payment isolation, payouts and billing accuracy. Reading only: money is always Tier 3.', '0145: no test moves real money\n0179 rule 3: money is Tier 3', '', '', '', 1, '', 'project-manager', 'standby')
add('growth', 'Growth, Merchant Onboarding & Partnerships Agent', O, 'operator', 'hybrid', '12', 'Business claims, leads, promotions and partner onboarding.', '', '', '', '', 1, '', 'project-manager', 'standby')
add('member-support', 'Customer Service & Member Support Agent', O, 'operator', 'hybrid', '13', 'Member and business support, reports and standing notices, and the friction they show.', '', '', '', '', 1, '', 'project-manager', 'standby')
add('directory-curator', 'Regional Directory & Data Curator', O, 'operator', 'hybrid', '14', 'Directory accuracy, coverage and gaps; never overwrites what a person entered; closures verified.', '0164: never overwrite what a person entered\n0066: nations\' land', '', 'staff', 'con', 1, '', 'project-manager', 'active')
add('system-architect', 'System Architect', B, 'operator', 'ai', '15', 'Writes each Building ticket\'s build brief and hands it to the session manager; schema, migrations and contracts.', '0159 §5: every build is a release\n0162: migrations', 'Build briefs', 'staff', 'eng', 2, '', 'project-manager', 'active')
add('ux-prototyper', 'UI/UX Prototyper & Flow Designer', B, 'operator', 'ai', '16', 'Wireframes and flows in the brand\'s tokens.', 'brand.md\ndesign/css-theme-brand.md', '', '', '', 1, '', 'project-manager', 'standby')
add('senior-designer', 'Senior Designer', B, 'operator', 'hybrid', '17', 'Visual polish, icons, graphics and brand assets.', 'brand.md', '', '', '', 1, '', 'project-manager', 'standby')
add('print-vendor', 'Preflight, Print & Trade Vendor Specialist', B, 'operator', 'hybrid', '18', 'Press preflight, vendor and work-order checks, printable layouts.', '', '', '', '', 1, '', 'project-manager', 'standby')
add('content-editor', 'Content & Copy Editor', B, 'operator', 'ai', '19', 'Microcopy, onboarding and error states in a plain voice; the FAQ.', '0057: the FAQ grows with every build', '', 'staff', 'con', 1, '', 'project-manager', 'active')
add('guide-auditor', 'AI & Dynamic Guide Quality Auditor', B, 'auditor', 'ai', '20', 'Audits Ask Traversence, on-demand guides, events and how-tos, and privacy-safe demand signals.', '0164: the Knowledge Engine', '', '', '', 1, '', 'project-manager', 'standby')
add('builder', 'Full-Stack Builder', B, 'operator', 'ai', '21', 'The Claude Code session a build brief goes to: builds within the approved scope, through git and releases. Never on the platform.', '0159 §5: every build is a release', 'Builds (a session)', 'staff', 'eng', 2, '', 'system-architect', 'active')
add('qa-security', 'Technical QA & Security Gatekeeper', B, 'auditor', 'ai', '22', 'Runs each Confirmation ticket\'s checks again; Done only when they pass.', 'CLAUDE.md: End to end, verified\nCLAUDE.md: Every action is a task', 'Confirmation', 'staff', 'eng', 2, '', 'project-manager', 'active')
adv = [('guest', 'Guest Visitor Advocate', 'signed_out', 'Signed-out discovery, the front door and sign-up friction.'),
       ('new-member', 'New Member Onboarding Advocate', 'new', 'The first 7 days and the sign-up experience.'),
       ('member', 'Resident & Member Advocate', 'member', 'Saved places, trips, Personal Guides, groups and member flows.'),
       ('contributor', 'Editorial Contributor Advocate', 'contributor', 'Writing, editing and publishing journeys and stories.'),
       ('steward', 'Community Group Steward Advocate', 'steward', 'Group stewardship and moderation.'),
       ('client', 'Client & Sandbox Buyer Advocate', 'client', 'Orders, proofs, deliverables and sandbox payments.'),
       ('business-owner', 'Business Owner Advocate', 'owner', 'Business control, team roles, storefront, GO codes and plan clarity.'),
       ('business-manager', 'Business Manager Advocate', 'manager', 'Day-to-day listing updates, offers and operations.'),
       ('business-accountant', 'Business Accountant Advocate', 'accountant', 'Invoices, receipts and financial clarity.'),
       ('business-sales', 'Business Sales Advocate', 'sales', 'Inquiries and promotional visibility.'),
       ('business-viewer', 'Business Viewer Advocate', 'viewer', 'Read-only access without unintended changes.')]
for i, (s, n, t, m) in enumerate(adv):
    add('advocate-' + s, n, A, 'advocate', 'persona', str(23 + i), m + ' A view for checks only: never acts.', '0145 §3: test accounts\n0165 §3: a test account\'s work is never carried', 'Views (piece 3)', '', '', 0, t, 'qa-security', 'active')
assert len(R) == 33
q = lambda v: "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"
out = ['-- The Team\'s 33 roles (Jason\'s spec §11; statuses approved 2026-11-25, handoffs/piece2-team.md §7): 9 working, 11 advocates,',
       '-- 13 on standby. Written by handoffs/piece2-build/rostergen.py. INSERT ... WHERE NOT EXISTS: safe to run twice, and a role someone',
       '-- edited is never written over. No CREATE here (0162\'s note: the runner clears a file unrun when all its tables exist).']
for n, r in enumerate(R):
    cols = 'slug, name, department, kind, made_of, spec_no, mandate, rules, stages, acts_level, acts_dept, tier_max, test_account, reports_to, status, sort, updated_by'
    vals = ', '.join(q(x) if not isinstance(x, int) else str(x) for x in r) + ', ' + str((n + 1) * 10) + ", 'the seed'"
    out.append(f"INSERT INTO team_roster ({cols}) SELECT {vals} FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM team_roster WHERE slug = {q(r[0])});")
open(sys.argv[1] + '/migrations/2026-11-25_team_roster_seed.sql', 'w').write('\n'.join(out) + '\n')
print(len(R), 'roles')
