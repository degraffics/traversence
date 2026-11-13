# ADR 0158: One way to publish: our site's releases and businesses' storefronts

**Status:** Accepted (Jason, 2026-11-13), one point left open (§6). Not built yet. It extends `decisions/0145` (planned,
phased releases, Phase 5: the Releases page and the staging bar), `0139` (storefronts on trvgo.us) and `0141` (Market
360°: publishing from the catalog card), and keeps `0127` (one uploader) and `0129` (the command strip).

## Context

Jason, 2026-11-13, after the Releases page and the staging bar went live: "what we have built for staging is the same tool
that listings will use for the domain stage on trvgo.us as their website. So we need to consider how that plays into this
build as well."

What each has today (checked against the live code, 2026-11-13):

| | Our site's releases (0145) | A business's storefront (0139) |
|---|---|---|
| What changes | files | data: the look, pages, photos, which items show |
| Draft | the uploaded release | only pages (Draft / Live / Hidden); the look saves straight to live |
| Tried first | staging.trvgo.us, a copy of the whole site | a signed preview link (7 days) |
| Changes and notes | the staging bar: Changes, Note | none |
| Who may publish | `releases.publish` and the other three (Permissions) | anyone with access |
| Schedule | yes | no |
| Steps | staff and test, a share of members, everyone | none |
| Way back | Turn off, Roll back | Pause only; no earlier version |

Catalog items already have draft and Publish (0141, "Publishing from the catalog card").

## Decision

1. **One way to publish.** Our site's releases and a business's storefront follow the same path:
   - draft;
   - look at it in preview, with the bar;
   - publish, now or (our site) on a schedule;
   - roll back to the version before.
   The same words, the same bar and the same notices wherever it's used.
2. **Every change is a draft until Publish** (Jason: the rule for every push, 2026-11-13). For a storefront that includes
   the small ones: a typo, a new photo, a color. Pages, the look (theme, accent, tagline, about, logo, cover) and the
   shop's settings are kept as a draft version beside the live one. Publish makes the draft the live version.
3. **No approval step; Publish is a role's permission** (Jason: "Publish action does not need approval, it requires role
   based access").
   - **A business's team:** whoever's role has **Publish** (`Team` perm `publish`, edit) publishes. Roles without it draft,
     and the draft waits for someone who has it. The owner sets this in Team & roles; nobody is asked per change.
   - **Our site:** `releases.publish`, `.schedule`, `.turn_off` and `.rollback` (0145 §5), as built. Jason's own look
     before sign-off (0145 note, 2026-11-13) is a rule of our process, not a step businesses get.
4. **Preview, with the bar.**
   - **Our site:** staging.trvgo.us, a full copy of the site; the bar is on every page there (built).
   - **A storefront:** its team's preview link shows the **draft version** with the same bar: what changed, Note, Publish
     (for a role that may), and Back to the portal. No copy of anything is needed: the preview draws the draft.
   - A storefront's public pages stay script-free, as 0139 requires. The bar's script loads only in the team's preview.
5. **Versions and the way back.**
   - **Our site:** each publish keeps the previous copy of every file; Roll back puts them back (built).
   - **A storefront:** each publish keeps the version it replaced (its look, pages and settings). **Roll back** makes the
     previous version live again, in one tap, and any number of times back. Pause stays as it is.
6. **Steps and schedules for shops: open.** A storefront goes from its team (the preview) to everyone when published.
   Scheduling (a sale page live Friday, down Monday) isn't built: Jason decides the rules once he sees it's needed ("I
   will need to see this as a need for me to determine how to apply rules").
7. **What's shared and what isn't.**
   - **Shared:** the bar (`js/stg-bar.js`, given a second backend), the publishing words and notices, the version history
     on the record, the Pulse case when something waits on someone, and the email when it's published or rolled back.
   - **Separate:** what moves. A release copies files to the live server; a storefront switches which saved version is live.

## Build order

1. **Storefront drafts and versions:** the look, pages and settings edited as a draft; Publish, Roll back, the version
   list in the portal's Storefront & pages; Publish behind the team's `publish` permission, greyed out with the reason for
   other roles.
2. **The bar on a storefront's preview:** the same bar, acting on the shop's draft.
3. **Catalog items** (0141): their own draft and Publish stay; they join the version list and Roll back.

Each is a release (0145 note, 2026-11-13), tried on staging first.

## Consequences

- A business never shows half-finished work, and can always go back.
- Who may publish is set once per role, by the owner; there's no queue of approvals.
- One bar to learn, for our team and for businesses.

**Note (2026-11-13, decisions/0159):** the shared core (blocks, versions, the bar) and Storefront Studio as the listings level of one builder.
