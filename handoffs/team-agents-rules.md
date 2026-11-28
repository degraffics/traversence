# The 42 agents' rule changes, in the four waves' order (for the Founder's one approval)

Ticket: "Agent online 01 (wave 1): Project Manager (8), upgraded in place" (task-seed-6). Jason's ruling B (decisions/0180 §2, #1681):
one approval covers all 42 agents' rule changes, provided he has seen them listed. This is that list. Written by the release line
(2026-11-28) from the live `TeamRunner.php` (mirror 5b39084), the ready drafts in `research/ai-results/ready/team-*`, and each
role's mandate on the live roster (decisions/0180's appendix, `2026-11-26_0180_tiers_roster.sql`).

## What a "rule" is here

The decision tree's approval is tied to `TeamRunner::ruleData()`: each step's name, its role, the Permissions action it asks for and
that action's default, what the step does at each ceiling (acts, proposes, off), what it may cost, the ceilings, the high-risk
territories and the word lists. A step's description and its code are not in it: "a fingerprint of its rules, not its wording".

**Live today (already approved once):** gate (Project Manager), handoff (System Architect), brief (Translator), audit (Operations &
Reconciliation Manager). Every step costs $0 and calls no paid AI or outside service.

**Three rules that come once, with agent 01, and apply to every agent:**
- `STEP_AI`: each step's order of reasoning: code first, then the agents' paid API, then a session.
- `AGENTS_API_LIVE = false`: the agents' paid API is off. Turning it on is its own rule change and its own approval.
- `STALL_DAYS = 3`: how long a ticket sits before the Project Manager's stall step notices it.

**In the list:** "drafted" means the batch wrote the step and its file is in `research/ai-results/ready/`; "verified" means the release
line ran its tests on the live copy; "planned" means no draft exists: the step is written here from the role's mandate, for the
Founder to see, and is designed with the agent's own release. Every planned step only proposes (a person accepts), costs $0, and
asks Permissions for `team.<step>` with the role's own level. The batch's team drafts for the other roles (jobs 18, 18b, 18c) came
back empty (0 bytes), so nothing more than the mandate exists for them.

| # | Wave | Agent (role in 0180) | Step it adds | What the step does | When it acts | State |
|---|---|---|---|---|---|---|
| 01 | 1 | Project Manager (8) | `sequence` | Proposes a Work ticket's priority from what it depends on: its goal, or the tickets waiting on it. Never over a priority a person set | proposes at every ceiling | drafted, verified (50 + 9 + 85 checks) |
| 01 | 1 | Project Manager (8) | `stall` | Finds a Priority 1 or 2 Work ticket idle 3 days at its stage and opens one "Unstick" ticket in its lane; if that sits 3 days too, asks a session once | proposes at Planning; acts from Building on, with Undo | drafted, verified |
| 02 | 1 | Staging & Release Workers (22) | `release-watch` | Proposes the next step for a release that stopped on staging (a failed check, a look that didn't finish): send again, withdraw, or Roll back by the rules | proposes at every ceiling | planned |
| 03 | 1 | Operations & Policy Director (16) | `policy` | Proposes the operating procedure and time limit that apply to a ticket, from the routing policy | proposes at every ceiling | planned |
| 04 | 1 | IT Manager (11) | `triage` | Proposes a triage for a Work ticket in the IT Manager's lane: its tier, the checks its specialists record, and where any reasoning goes | proposes at every ceiling | drafted, not verified |
| 05 | 1 | Chief Operating Officer (3) | `tier4-ops` | Proposes the Tier 4 review of a change in the Project Manager's and Customer Service Manager's lanes, with the Prior Ruling Check | proposes at every ceiling | planned |
| 06 | 2 | Build & QA Sessions (21) | None | None: it is the Claude Code session a build brief goes to, never a step on the platform | as today | no rule change |
| 07 | 2 | Schema & Migration Guards (24) | `schema` | Proposes a migration's tier from what it does: only adds (Tier 2), or updates, deletes, drops, renames, truncates or alters a column (a high-risk territory) | proposes at every ceiling | planned |
| 08 | 2 | Privacy & Decision 0066 Guards (25) | `privacy` | Proposes the privacy and 0066 check for a ticket: held names, and every surface that could expose them | proposes at every ceiling | planned |
| 09 | 2 | System Architect (20) | None | None new: it keeps its live step, handoff (writes a Building ticket's build brief) | as today | no rule change |
| 10 | 2 | Operations & Reconciliation Manager (23) | None | None new: it keeps its live step, audit (the daily health and drift audit) | as today | no rule change |
| 11 | 2 | Executive Translator & Briefing Lead (28) | None | None new: it keeps its live step, brief (proposes a Resolution brief's words) | as today | no rule change |
| 12 | 3 | Editorial Director (12) | `editorial` | Proposes an Editorial lane ticket's checks as steps: the FAQ, the words, 390px, one clear next action, a territory's Tier 4, held names, a person's words | proposes at every ceiling | drafted, not verified |
| 13 | 3 | Front-End Content Writers (26) | `content` | Proposes an editorial Work ticket's goal, scope and Done when from its kind (FAQ, words, guide, listing); a person accepts | proposes at every ceiling | drafted, not verified; the draft names the role "content-writers", the live slug is "content-editor" |
| 14 | 3 | Test-account advocate A1 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 15 | 3 | Test-account advocate A2 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 16 | 3 | Test-account advocate A3 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 17 | 3 | Test-account advocate A4 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 18 | 3 | Test-account advocate A5 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 19 | 3 | Test-account advocate A6 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 20 | 3 | Test-account advocate A7 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 21 | 3 | Test-account advocate A8 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 22 | 3 | Test-account advocate A9 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 23 | 3 | Test-account advocate A10 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 24 | 3 | Test-account advocate A11 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 25 | 3 | Test-account advocate A12 | None | An advocate only looks: it never works a ticket and has no step in the runner (`TeamRoster::can` refuses it). Its audit of the live site opens tickets from outside the decision tree | never acts | no rule change (the draft, `team-advocates`, is cut short and adds no step) |
| 26 | 3 | Cognitive Load & Simplicity Guardian (15) | `simplicity` | Proposes trimming a ticket's scope or a screen that grows heavy: one screen, one clear next action | proposes at every ceiling | planned |
| 27 | 3 | Public UI/UX Designers (27) | `flow` | Proposes the wireframe or flow a ticket needs, in the brand's tokens, as a step for a person | proposes at every ceiling | planned |
| 28 | 3 | Customer Service Manager (9) | `triage-service` | Proposes a triage for a ticket in the Customer Service lane (members and businesses), and routes an advocate's experience ticket for its screens | proposes at every ceiling | planned |
| 29 | 3 | Customer Support Specialists (18) | `support` | Proposes the answer or standing notice for a member or business report, and names the friction it shows | proposes at every ceiling | planned |
| 30 | 3 | Order & Fulfillment Lead (17) | `order-check` | Proposes an order's checks (proof, work order, fulfilment). Reading only: it never moves money | proposes at every ceiling | planned |
| 31 | 4 | Chief Technology Officer (5) | `tier4-eng` | Proposes the Tier 4 review of a data, address or privacy change in the IT Manager's lane, with the Prior Ruling Check | proposes at every ceiling | planned |
| 32 | 4 | Chief Information Officer (6) | `tier4-data` | Proposes the Tier 4 review of a data mutation or privacy change, with the CTO, with the Prior Ruling Check | proposes at every ceiling | planned |
| 33 | 4 | Chief Marketing Officer (7) | `tier4-mission` | Proposes the Tier 4 review of a marketing, editorial or sales change against the mission, with the Prior Ruling Check | proposes at every ceiling | planned |
| 34 | 4 | Chief Financial Officer (4) | `tier4-money` | Proposes the Tier 4 review of every edit that touches money (0180 §4.1), with the Prior Ruling Check. It never moves money | proposes at every ceiling | planned |
| 35 | 4 | Accounting Manager (10) | `triage-accounting` | Proposes a triage for a ticket in the Accounting lane; prepares a refund record only (a refund runs with Jason's OK, 0178) | proposes at every ceiling | planned |
| 36 | 4 | Finance, Tax & Billing Ops (19) | `billing-check` | Proposes the tax, sandbox-isolation and billing-accuracy checks for a ticket. Reading only: it never moves money | proposes at every ceiling | planned |
| 37 | 4 | Marketing Director (13) | `triage-marketing` | Proposes a triage for a ticket in the Marketing lane (brand, campaigns, SEO, taxonomy, funnels) | proposes at every ceiling | planned |
| 38 | 4 | Sales Director (14) | `triage-sales` | Proposes a triage for a ticket in the Sales lane (leads, onboarding, partnerships, retention) | proposes at every ceiling | planned |
| 39 | 4 | Brand & Campaign Strategists (29) | `brand` | Proposes a brand or campaign ticket's checks, within the zero-commission commitment | proposes at every ceiling | planned |
| 40 | 4 | SEO, Taxonomy & Funnel Analysts (30) | `seo` | Proposes an SEO, taxonomy or funnel ticket's checks, from privacy-safe counts only | proposes at every ceiling | planned |
| 41 | 4 | Merchant Onboarding Specialists (31) | `onboarding` | Proposes the next step for a business claim, lead or partner onboarding | proposes at every ceiling | planned |
| 42 | 4 | Partnerships & Retention Leads (32) | `retention` | Proposes the next step for a partner or a retention case | proposes at every ceiling | planned |

**Count:** 42 agents; 27 new steps (5 drafted, 22 planned) across 26 agents; 16 agents add no rule (the 12 advocates, and roles 20, 21, 23, 28).
Roles 1 (the Founder) and 2 (the CEO) get no agent release.

## The fewest presses: one, with one release first (checked against the code)

**It works.** One small "rules" release lands every step's rule entries in `TeamRunner.php` (STEPS, STEP_AT, STEP_COST_MICROS, STEP_AI,
AGENTS_API_LIVE, STALL_DAYS) with no step code. That changes the fingerprint once; Jason presses **Approve the decision tree** once on
https://traversence.com/admin/config/team#tm-tree (signed in with the Founder's own staff login). Each agent's release then adds only
its step's code (its branches in `applies()`, `run()`, `accept()`, `undo()`), which is not in `ruleData()`, so the fingerprint stays.

**Checked:** the live copy's fingerprint is `7b521b97b375a8fc…`. A copy of the live file with only agent 01's rule entries added (no
step code) is `6bb0fdf81313c33b…`, and agent 01's full build is `6bb0fdf81313c33b…` too: the same. So the agent's own release does
not change the fingerprint once its entries are in.

**Off until its release, by the code as it is:** a step with an entry but no code does nothing: `applies()` ends `return false` for a
step it doesn't know, so `pick()` never chooses it and nothing runs. No extra switch is needed, and none may be added to `ruleData()`.

**What the rules release must also do, so the pages stay true:**
1. The Team page's table and a role's "Its steps" list every STEPS entry. A step without code must read "coming with its release",
   not "proposes". Suggest a `TeamRunner::BUILT` list (outside `ruleData()`) that the pages and `TeamRoster::steps()` read.
2. Run a test ticket (`TeamVet`) must treat a step that isn't built as "no step yet", from the same list.
3. Step keys are unique across roles, so the IT Manager's `triage` and the other managers' triage steps need their own keys (above).

**When one press is not enough (said plainly):**
- If an agent's release needs a rule different from its line above (another Permissions action or default, acting where it only
  proposed, a cost above $0), the fingerprint changes and that is a new press. The 22 planned steps carry that risk: none is designed.
- Turning the agents' paid API on (`AGENTS_API_LIVE`), or changing how far Implementation reaches, is always its own press.
- The roles' Permissions: most roles have no level yet ("only the Founder gives a role permissions", 0180 §2). A step can't run
  until its role has one. That is set on https://traversence.com/admin/config/permissions and is not part of this fingerprint.

## Not verified here

- The drafts for roles 11, 12 and 26 are not run or tested by the release line yet; role 26's names a slug that isn't on the roster.
- The planned steps' names and words are the release line's reading of each mandate, for the Founder's list. They are suggestions.
