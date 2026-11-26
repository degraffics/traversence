-- Piece 3, P3-2 (Jason, 2026-11-25: "Staff Views yes"; decisions/0179's note "Piece 3's decisions"): the Staff Views
-- advocate, bound to the staff test logins (TestAccounts::STAFF; the check looks as the Test Executive and the Test Ops
-- Staff, tools/page-audit/beforeafter.mjs). A view for checks only: never acts. Added once; safe to run twice.
INSERT INTO team_roster (slug, name, department, kind, made_of, spec_no, mandate, rules, stages, acts_level, acts_dept, tier_max, test_account, reports_to, status, sort, updated_by) SELECT 'advocate-staff-views', 'Staff Views Advocate', 'advocates', 'advocate', 'persona', '34', 'The admin as staff see it: the staff test logins on admin pages, for the before-and-after check. A view for checks only: never acts.', '0145 §3: test accounts
0165 §3: a test account''s work is never carried
The Founder is never a test login', 'Views (piece 3)', '', '', 0, 'executive', 'qa-security', 'active', 340, 'the seed' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM team_roster WHERE slug = 'advocate-staff-views');
