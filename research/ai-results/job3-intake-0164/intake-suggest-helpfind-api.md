# Review: listing write paths against ADR 0164

**Scope.** Three files: `lib/Suggestions.php`, `lib/HelpFind.php` and `api/ingest.php`. Several functions they call are not shown: `CrawlerIngest::upsertEntity`, `ListingIdentity::*`, `IntakeStager`, `Guides::due`, `DataCleanup`, and the Review screen that applies a suggestion. Where a rule depends on that code, I say "not visible" rather than guess.

I found six paths.

---

## Path A: A suggestion is created (`Suggestions::create`)

**(1) Trigger.**
- A person: a visitor, member or staff member, or the "Help us find" button, which posts `kind: fix`.
- A machine: callers whose `$device` starts with `crawler-` or `system:`.

**(2) What it writes.**
- One row in `suggestions`, holding kind, topic, entity, target, fields (JSON), note, context, `site_key`, `phone10`, `user_id` and `device_hash`.
- When a signed-in user (`$userId !== null`) sends a story, fix, closed or missing suggestion with a `place_key`, it also calls `Guides::due()`. That moves the place's guide check forward.

**(3) Person review.**
- Yes for listing changes. The file header says "A person reviews every suggestion in Review; nothing changes on the site by itself", and `create` writes no listing field.
- The exception is `Guides::due()`. It reschedules a guide check before anyone reviews the suggestion.

**(4) Rule gaps.**
- **Evidence and sources kept (R4 "with its source"; §3.5 "Each NAP field shows how it's known").**
  - `FIELDS['source']` is optional.
  - A `fix` that supplies a phone or address, which is the exact answer HelpFind asks for, can arrive with no source.
  - R4 and §3.5 expect