# pause-1a: the IT Manager's (11) Tier 4 (2026-11-28)

Reviewed: claude/pause-1a @84d2b031 (handoffs/pause-1a/build, tools/pause-tests/pause.php), read against the live mirror
at Sync 01:15 UTC (d897347). Earlier pass: @d32f440b, 01:38 UTC.

**Verdict: not yet.** First pass: PASS on one condition (the identity layer). The independent check (`tier4-independent.md`)
widens that condition to four paths and two defects. It passes once they're closed and run in a test.

## Confirmed
- `AutoImport::enabled()` is false while `PAUSED` holds words, whatever .env says. `pausedWhy()` is shown on the dashboard
  and on Auto-imports, and emptying the constant is the way back.
- **Every automatic publish asks first.** `tryImport` returns before approve, because `enabled()` is false
  (AutoImport.php:696) and a held find stays held (:697). `advance` asks `enabled()` before `tryImport`. A person's
  approval still publishes, by design: Review, Helper's `l_approve` (Helper.php:309) and quick approve
  (AutoImport.php:748).
- **`api/ingest.php` can't publish while paused.** It normalizes each record, adds `hold`, and goes through
  `IntakeStager::stage` → `tryImport`, which returns before approve. `upsertEntity` isn't reached on the paused branch. It
  answers "held for review" (200).
- **`fillGaps` with no person** (obvious merges, AutoImport.php:609 and :693; Helper's sweep, Helper.php:66) holds
  description and hours for Review, one open per listing. Phone, website, offerings and the pin still fill. A person's
  merge is untouched.
- **`Refresh::complete`** (W2) holds the website's description and hours, and the Common Crawl copy's description, the
  same way. Phone, offerings and the point still fill.
- `tools/pause-tests/pause.php`: 21/21 on the 01:15 live lib.

## The condition
`Identity::applyEvidence` (Identity.php:279-302, run after each listing's facts are recomputed, :208) writes settled facts
onto an unclaimed listing as `ai_crawler`. Its facts (`KNOWABLE`, :449-450) include `description` and `hours`. One source
can fill an empty field, and two can replace one (:296).

That is the audit's C1 and C2 harm by a third path. The fix: hold `description` and `hours` for Review while
`pausedWhy()` holds words, by the same pattern (`AutoImport::holdForReview`, device `identity-paused`), with a test. Sent
to Data & Content at 01:52 UTC.

## Not in this release
- Retiring `api/ingest.php` (nothing calls it; it has no gate of its own): for 1c or later, as a Suggest-a-change with its
  caller check.
- Search 1b rebuilds `AutoImport.php` and `Identity.php` on this release once it's live and synced.

## Second pass (@5576476c, 2026-11-28)
**Verdict: PASS on one condition.** `HelpFind::promoteSome` must not un-hide a card on a machine-filled phone or address
while paused (the independent check's second round). Everything else checks out:
- `Identity::applyEvidence` and `ListingIdentity::merge` hold About text and hours;
- `Suggestions` keeps the held About (`HELD_FIELDS`, machine devices only) and appends a second find;
- the real path runs on MariaDB (`identity.php` 11/11) and `pause.php` passes 21/21;
- people's saves (`Claims::writeField`, `IntakeStager::adminSave`) are unchanged and never held.

The smaller issues in `tier4-independent.md` can follow in 1b or the health corrections, except that the note "A person
applies it there" should say staff copy it into the editor until Review can apply an About.

## Third pass (@fae50a77, 2026-11-28): PASS
- **The condition is met.** `HelpFind::promoteSome`, while paused, takes a card back only when its phone or address is locked or
  was set by a person (source admin, owner or user). A machine-filled value no longer puts a card back.
  `tools/pause-tests/helpfind.php` on MariaDB: paused, 2 of 4 come back (the owner's phone, our team's address); pause
  emptied, all 4.
- The held About's note now says staff copy it into the editor. Appended values are stripped of tags, and a non-JSON drop
  value is held as it is. The twin merge's double filing is known and noted in the README.
- `pause.php` 21/21, `identity.php` 11/11, `helpfind.php` all pass, run by the IT Manager on the 01:15 live lib.
- **Before it sends:** `faq.md` is re-applied on the live copy after R2 (r2-lanes-4) syncs.
