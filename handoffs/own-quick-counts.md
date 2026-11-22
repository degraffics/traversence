# Your own quick searches counted (Design and trips, 2026-11-22)

Jason, 2026-11-22: "Count them" (B2's follow-up, `screens-proposal-2026-11-22.md`; queue item 3 in
`design-and-trips-2026-11-22.md`). Built by session_01CXLi5jNQCN3zFZ2Fh3qUNf on **Sync 13:57 UTC 2026-11-22 (70d0cb1)** as
`own-quick-counts.zip` (`own-quick-counts/build.sh` replays `src/oqc.patch` on the live mirror). Zip check clean.
Files (4): `app/lib/SearchPresets.php` (`mine`, `countMine` added), `website/api/search.php` (the presets answer's
`mine`), `website/js/search-panel.js`, `app/ui/faq.md`.

## Each behaviour and its source
| Behaviour | Source |
|---|---|
| Each of your own quick searches (on this device, up to 12) shows how many its search finds where you are | Jason "Count them" (2026-11-22) |
| The number is the search's own Results total: the phrase is run as the search it opens, with the same place, reach and section | B2's rule: no wrong number (decisions/0053's note of 2026-11-22) |
| Run with `no_side`: nothing to the crawler, nothing counted as demand or as a place's asked question | The queue's "mind the cost"; the suggestions' own rule while typing (`UniversalSearch` 2473) |
| Kept an hour per place and phrase (`search_index_state`, `pm:` keys), like `have()` | The queue: "one count per phrase, cached like have()" |
| "Open now" (a when:now reading) gets no number | B2 ("no counts on links whose answer changes by the minute") |
| A phrase that finds nothing shows no number (as the place's rows) | B2's build: no number rather than a wrong one |
| ⊕ and ✎ Done fetch the new phrase's count at once | words follow the state (playbook principle 4) |
| Only the words are sent; nothing about them or the person is stored | CLAUDE.md: anything about a user is private; FAQ says so |
| FAQ: Quick Search's line and "Can I save my own search phrases?" updated in place | CLAUDE.md "Keep the FAQ current" |

**Kept:** the place's quick searches and their counts, Open now without one, your own first, ✎ editing, ⊕, the filters,
Recent Searches. **Moved / dropped:** nothing. **Cost:** local database only; at most 12 searches per place per hour per
phrase set, the same kind the suggestions run per keystroke. No outside calls.

## Admin's test (principle 5)
- A member opens search with their own quick searches → each shows its number where they are, or none (open now, or
  nothing found there). Nothing for an admin to do; no new screen.

## Proof
`tools/design-tests/own-quick-counts.cjs` on the harness (search_index seeded: 4 pool listings, a pizza place): 17/17 at
1280 and 390 (`own-quick-counts/proof/`): "pools" 4 = its Results total; open now none; a phrase finding nothing none;
⊕ (1280) and ✎ Done (both) bring the count; `search_counts` unchanged; no sideways scroll; no script errors.

## State
Sent to `releases/inbox/`; waiting for the session manager's review, then Jason's OK.
