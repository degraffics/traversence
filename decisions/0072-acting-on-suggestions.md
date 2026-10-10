# 0072. Acting on visitors' suggestions in one step

- **Status:** Accepted
- **Date:** 2026-10-10
- **Builds on:** 0053 (suggestions), 0058 §24–25 (landmarks), 0066 (tribal nations' land), 0071 (adding a business)

## Context

Review shows what visitors send: something missing, a fix, closed or moved, listed twice, a story idea, search
feedback. Only a missing business had a one-step action (Create the listing, 0071). Every other kind meant opening
the editor, making the change, coming back and tapping **Done, used it**, and the visitor never heard back.

## Decision

Each kind of suggestion gets one green button on its Review card that makes the change, marks it used and, when
the visitor was signed in, emails them a thank-you. Guests leave no address, so they get no email.

| Kind | Button | What it does |
|---|---|---|
| Closed or moved | **Mark closed** (with a confirm) | Hides the listing (`is_suppressed`), keeps everything, notes when, who and why (`closed_note`). On a Recreation.gov place it hides that place, and the hide survives monthly reloads. **Moved: edit the address** opens the editor. |
| Suggested fix | **Apply the fix** | `/admin/suggestion.php` shows each field as it is and as sent (name, phone, website, address, hours), ticked when different. Staff can correct the value first. Written as staff edits (`admin`, locked), so the crawler never overwrites them. |
| Same business | **Merge these** | Both listings side by side, the likelier keeper preselected (owner-managed first, then the fuller one). Fills the kept listing's empty fields from the other, hides the other and records `same_as`; its old link 301s to the kept one. Waiting suggestions about it move over. |
| Something missing, outdoors | **Create the place** | Goes through `Landmarks::add`, the same path as Admin → Landmarks, so sensitive names (sacred, burial, protected) are held and nations' land follows 0066. The kinds gain **Trail or trailhead** and **Campground**. Needs a map point; the visitor's pin is filled in. |
| Something missing, event | none | Events aren't built yet. |

Rules:
- **Nothing is deleted.** Closed and merged-away listings are hidden and can be put back from their editor.
- **An owner's listing is the owner's.** A visitor's word alone never hides a managed listing or hides it in a merge, and
  never overwrites a field the owner set or the name of a managed listing. Staff use the editor if they're sure.
- What a merge leaves on the hidden listing (comments, likes, photos) stays with it for now. Moving them is later work.

## Consequences

- Visitor suggestions become real changes in one tap, and signed-in visitors hear back, which makes suggesting worthwhile.
- Code: `app/lib/SuggestionActions.php`, `website/admin/suggestion.php`, buttons and `sg_closed` in
  `website/admin/crawler-review.php`, the merged-listing redirect in `website/listing/view.php`, two new landmark kinds in
  `app/lib/Landmarks.php`. No migration.
