# Phase 1: Foundations (code checks, zip check, delivery log, scorecard)

Decision 0145 §1, §4. Changes nothing on the live site.

## Goal

Catch broken code and missed files before an upload, and know at any moment what's live, what's waiting and what's
failing.

## What gets built

1. **The rule catalogue** (`site-audit/rules.md`). Every rule is numbered, comes from `CLAUDE.md`, a decision or
   `ux-review.md`, and links back to its source. Each is marked as one of:
   - **code:** a script can check it;
   - **rendered:** it needs a browser;
   - **review:** it needs a person or a review session.
2. **The code checks** (`tools/page-audit/`), run against the live mirror. Each check names its rule, file and line:
   - every function and method a file calls still exists (would have caught `Engage::liked()`);
   - every page loads the session and the shared shell, or is listed as an exception with a reason;
   - no `alert()`, `confirm()` or `prompt()`;
   - every button's request shows busy and an answer;
   - uploads only through `Uploads.php` and `/api/upload.php`;
   - no PHP newer than 8.0;
   - FAQ links point at entries that exist, and every page is in `routes.md` or `archive.md`;
   - migrations are safe to run twice and add-only (§6);
   - the brand's icons, not emoji; no breadcrumbs on content pages; spell check on word fields; Show/Hide on password
     boxes.
3. **The zip check.** Before a zip is sent, the same checks run on the zip's files together with the live files they
   replace or rely on. The hand-off note says "checked clean" or lists what's still failing.
4. **The delivery and release log** (`deliveries/`). One entry per zip:
   - its files and the sync it was built on;
   - after each sync, whether each file matches the live copy byte for byte. A missed file stays "pending" until it does.
5. **The scorecard** (`site-audit/scorecard.md`):
   - each page's checks, pass or fail, with links;
   - the worst repeat failures first;
   - a trend from run to run.
6. **A GitHub Action** in this repository. It runs the checks after each sync commit on the mirror, weekly in full, and
   on demand (Run). It commits the scorecard and log.

## What Jason provides

- Approval of this phase. That's all.
- Nothing else is needed. It reads the mirror, which this repository can already read, and uses GitHub's free run time.

## Done when

- [ ] Every rule in `CLAUDE.md` and the decisions is in the catalogue, marked code, rendered or review.
- [ ] The code checks run on the full mirror, and the first scorecard is committed.
- [ ] The zip check runs on a real zip, and its result is in the hand-off note.
- [ ] The delivery log shows the last week's zips and which files matched.
- [ ] Jason has seen the first scorecard and said which failures to fix first.

## Rollout and rollback

Nothing on the site changes. To roll back, switch off the Action in this repository (Actions → the workflow → Disable).

## Cost

None. GitHub's free run time. A run reads the mirror only.
