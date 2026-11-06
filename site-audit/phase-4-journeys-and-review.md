# Phase 4: Journeys and the review

Decision 0145 §1, §2.

## Goal

Measure whether people can do what they came to do: intuitively, with accurate and complete information. Let the
findings improve the rules, not just check against them.

## What gets built

1. **The journey catalogue** (`site-audit/journeys.md`). Each journey is what someone comes to do, as a scripted path
   for the account types it applies to. A first set:
   - find a place to eat near me, open now; search with a typo ("swiming");
   - set my place and distance; plan a weekend trip;
   - read a town's guide (St. Johns); open a landmark (Agate Bridge);
   - sign up (all steps); sign in, including the human check; sign out;
   - like as a guest (3, then Keep going as a guest); like as a member (past 100); comment;
   - claim my business; edit my listing; see my listing as visitors do;
   - post on the Marketplace; message a business; join a group;
   - staff: decide a report, a like check, a held Marketplace post; send an order.
2. **The measures**, kept per run so trends show:
   - **Steps:** taps and pages to the goal.
   - **Dead ends:** empty pages with no next step, and actions with no visible result.
   - **Surprises:** stacked dialogs, a lost header, a page that flashes.
   - **Accuracy:** what's shown matches the data (counts, hours, phone, distance); what exists is reachable (a published
     guide shows where it should); links work.
   - **Clarity:** plain wording, one obvious next action, the site's patterns and brand standard.
3. **The page-to-decision map** (`site-audit/page-map.md`): each page and the decisions and docs that govern it, built
   from the paths named in them and `routes.md`.
4. **The review**, a Claude Code skill in this repository (`.claude/skills/page-review/`). For flagged or changed pages
   it reads the page, its screenshots, its journeys and its governing decisions. Every finding gets a verdict:
   - fix the page;
   - draft an amendment (the page does better than the rule, or the rule no longer fits);
   - missing decision, or conflict between decisions.

   The findings are ranked by how many journeys they block.
5. **The proposal list** (`site-audit/proposals.md`): improvements grouped from the findings, each with its evidence and
   the decisions it touches, ready for Jason to approve, reject or reword.
6. **The Pulse "Site health" case** on the site: a step for each open finding, with links to the page, the screenshot
   and the proposal.

## What Jason provides

1. **Approval** of this phase.
2. **The journeys that matter most.** Read the first set above, add or remove, and mark the top 10. They're checked on
   every run; the rest weekly.
3. **Approval of the measures.** For example, how many steps is too many for "find a place to eat" (suggested: 3 or
   fewer from the home page).
4. **Your decisions on the first proposals**, approve, reject or reword, so the review learns what you want.

## Done when

- [ ] The top journeys run for every account type that applies, with their measures in the scorecard.
- [ ] The page map covers every page in `routes.md`.
- [ ] The first review has produced a ranked list with verdicts, including at least one drafted amendment and any
      conflicts found.
- [ ] Site health shows in Pulse.

## Rollout and rollback

- The checks and review change nothing on the site.
- The Pulse case is released with a switch (Phase 2), to staff first.
- Rollback: turn off its switch, and disable the journeys in the Action.

## Cost

None. GitHub's free run time; the review runs in Claude Code sessions, not through the site's paid AI.
