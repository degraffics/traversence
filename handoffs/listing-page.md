# The listing page (Design and trips, 2026-11-22)

B5, Option 1 of `handoffs/screens-proposal-2026-11-22.md`, approved by Jason 2026-11-22. Build:
`listing-page/build-lp.py` (reads the mirror; rerun to rebuild). Built on Sync 12:55 UTC (114f938), after card-fixes.zip went live as #249 (both change `listing/view.php`).

| Behaviour | Source |
|---|---|
| The card's top-right corner holds the **Link** and, just left of it, **⋯**, the toolkit: Call, Email, Directions, Map, Website, Contact, Message (or Leave a message), Save contact, QR code, each with its icon and name. It opens under the corner; ×, Esc, a click outside, or pressing an action closes it. Same on a phone | B5 Option 1 as Jason refined it, 2026-11-22: "dont change to pills"; "Use link icon in right top corner and ... tool kit just to the left of it" |
| The listing's name heads the location block: name, then address, town and phone | Jason, 2026-11-22: "add title above address so it blocks the whole location info" |
| The categories sit under the contact details as small words separated by · (still each a search), not pills above | Jason, 2026-11-22: "the detail pills are distracting from the contact" |
| How sure we are is said in words under the details ("How sure we are: Unconfirmed · How we know ▾"), plain text, not the ring; it opens the same How we know panel (`tv_confidence_words`, beside the ring, which stays on cards) | B5 ("the score ring said in words"); decisions/0133 (How we know) |
| The photo is capped lower on a listing: `clamp(200px, 32vh, 340px)` (it was up to 480px, half the window) | B5 ("photo about 40% of the screen") |
| "Wrong spot on the map? Fix the pin" shows only where there's a pin to fix: not on a PO box or mail-only address, not without a point; staff always see "Location and map pin" | B5 |
| Categories' order: the most specific first, any "Other …" last (the main category too when it's "Other" and there are real ones) | B5 ("'Other' first among the categories") |
| About isn't drawn for visitors when it has nothing to say (no description, nothing added); its owner still sees it with how to fill it. About is as tall as its words, not stretched beside the side card | B5 ("a large empty card") |
| FAQ: "What's on a listing's page?" added; the circle and Fix the pin entries say where each now shows (anchors kept) | CLAUDE.md "Keep the FAQ current" |

**Kept:** every action and its counting (`data-out`), See on map's panel (`#lv-map`), QR's pop-up, Save contact, Leave a
message's form (`#lm`, `data-lm-open`), the Link and its consent, the category pills that search, the badges, How we
know's panel and its Yes/No, the hero's ⋯ (Link to, Claim, Add to a trip). **Moved:** the actions from a column down the
right into ⋯; Message from beside the Link into ⋯; the categories from above the name to under the contact details;
the ring's job to the words. **Dropped:** nothing.

## Admin's test
- A visitor presses ⋯ beside the Link: the list of actions by name; pressing one does what it did before (call,
  directions, map, QR…) and closes the list.
- "How sure we are: <band>": what we know of the listing; How we know shows the sources and Is this right?
- No Fix the pin on a PO box: there's no place to pin. Staff still have Location and map pin.
- No About: nothing written yet. The owner sees "No description yet: add one from your business dashboard."

## Tests
`tools/design-tests/listing-page/run.cjs` on the harness (card-fixes' `parks-seed.sql`, then listing 107 given a street
address, "Other Recreation" first with Boat Ramps and Fishing, no description), at 1280 and 390 for a PO box with a
photo (101) and a street address with a pin (107): ⋯ beside the Link in the corner, the actions in it by name, the name over the address,
the words not the ring, the photo's height, Fix the pin, categories' order, no empty About, no sideways scroll; pressed: ⋯ opens and stays in the window, ×, Esc and a click outside each close it;
How we know opens and its × closes, the words again close it, QR opens from ⋯ (closing it) and Esc closes it, Leave a message reaches its
form; no script errors. 86 checks. Rendered with sample state; the real run happens on live. Screenshots: `listing-page/proof/`.
