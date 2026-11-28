# Review: listing write and visibility paths in `Suggestions.php`, `HelpFind.php` and `api/ingest.php` against ADR 0164

**Scope.** This covers six paths across the three files. Where a path hands off to code not attached (`CrawlerIngest::upsertEntity`, `ListingIdentity`, `IntakeStager`, the Review screen), I say what this layer does and does not do, and I don't guess at the rest. None of the three files calls `Landmarks::visibleSql`.

---

## Path A: `Suggestions::create`, a suggestion is filed

1. **Trigger.** A person (visitor, member, staff) or a machine. Machine callers pass a `device` starting `crawler-` or `system:` and skip the 12-a-day limit.
2. **What it writes.**
   - One row in `suggestions`: kind, topic, entity, `same_as`, target, `fields` JSON (name, phone, address, website, hours, `point`, …), note (up to 4,000 characters for `story`), `site_key`, `phone10`, `user_id` and `device_hash`.
   - For a signed-in person with a `place_key`, it calls `Guides::due()`, which brings a guide's check forward.
   - It changes no listing.
3. **Review.** Yes. The header says "A person reviews every suggestion in Review; nothing changes on the site by itself". `waiting()` feeds Review.
4. **Rule gaps.**
   - **R4 (claimed listings go to the owner):** silent. A `crawler-…` "check this?" on a claimed listing goes only into the general queue. Nothing routes it to the owner's **Check these** or sends the email.
   - **R4 / R11 (provenance):** the `source` field is optional free text. A visitor's phone or address carries no structured source or licence. §3.5 needs a structured source so the field can later "show how it's known".
   - **R7 / 0066 (tribal land via `Landmarks::visibleSql`):** silent. A visitor's `point` ("I'm here now") is stored with no check against protected places or a nation's land. It is not public here, but nothing marks it for the person who later applies it in Review.
   - **§0.8 / R13 (nothing private to a free tier):** silent. The `story` note and `note` are a person's words, and the row carries no input class. This file does not send them anywhere, so this is low.
5. **Smallest fix.**
   - When `entity_id` is claimed, insert the owner's Check-these step as well, or flag the row for it.
   - Store `point` with a computed `protected` flag from `Landmarks::visibleSql`.
   - Stamp each row `carries = person_words`.

## Path B: `Suggestions::decide`, staff settles a suggestion

1. **Trigger.** A person (admin).
2. **What it writes.** `status`, `handled_by` and `handled_at` on the suggestion. Applying the change happens elsewhere (not shown).
3. **Review.** It *is* the review.
4. **Rule gaps.**
   - **R11 (evidence and sources kept):** "used" records that the suggestion was used, not which field it supplied. Status can also be set back to `new`, which loses the earlier decision with no history row.
   - This is low here because the apply step isn't shown.
5. **Smallest fix.** Append-only history: keep the prior status and handler in a log row instead of overwriting.

## Path C: `Suggestions::hints`, match context during intake

1. **Trigger.** A machine (IntakeStager, on a crawled find).
2. **What it writes.** Nothing. It returns candidate listings and visitor notes to the person merging.
3. **Review.** Yes. A person sees the hints when merging.
4. **Rule gaps.**
   - It includes unreviewed (`new`) suggestions. That is fine as context, since R9 counts a visitor's confirmation as our evidence and a person decides.
   - No violation found.
5. **Fix.** None needed.

## Path D: `HelpFind::inPlace` / `near` / `block` / `card`, public "Help us find" cards

1. **Trigger.** A visitor loading a town page or a search.
2. **What it writes.**
   - It renders the names, kinds and towns of suppressed