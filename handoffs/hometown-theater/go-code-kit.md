# The GO Code poster kit

*Draft for the CMO (7) and Marketing (13); built from the night's event page once it exists (0134 note: "GO Codes come
from the page's Link button").*

| Poster | Where | Its code opens | Words (draft) |
|---|---|---|---|
| The night | The theater's lobby and box office window | The night's event page | "Community night at the Hometown Theater. Scan for tickets and dinner nearby." |
| Dinner and a movie | Each trail restaurant, by the register | The "Dinner and a movie" trail | "Dinner here, then the movie at the Hometown Theater. Scan for the night." |
| Claim your listing | Handed to the theater in person | Listing #8807's claim page | "Your theater is on Traversence. Scan to claim it, free." |

**Rules for the kit:**
- Every code goes through trvgo.us, so scans are counted and a code can be corrected after it's printed (commercial.md
  §1.3, decisions/0098). The theater's 5 free codes cover the night and its claim poster. The restaurants' codes are
  their own 5 free codes once they claim; one that hasn't claimed follows the CMO's ruling below.
- No studio artwork or logos; the film's title in plain type only (0134's brand line). Brand colours and type from
  brand.md; a poster goes to the Design reviewer before print (CLAUDE.md "Design review before Jason sees it").
- Every code's landing passes the promise check before print: it lands on exactly what the poster names, and its words
  match the real state (0181 gate 1 note, #1579). A night not yet on sale says so, never a dead end.
- Counts only: scans, never who scanned (CLAUDE.md "Count outbound taps").

## The CMO's (7) ruling on GO Codes (2026-11-27, independent check passed; via the session manager)

1. **An unclaimed restaurant's poster** uses its listing page's own free Share code (`ShortCodes::forPage`,
   ShortCodes.php:109: one short address per page, the same for everyone). It needs no plan, and no team code is made
   for a listing we don't run (`ShortCodes::create` refuses one, ShortCodes.php:234, "You don't run that listing").
2. **No offer is printed or promised for a business that hasn't agreed** (decisions/0108; owner privacy). A diner
   offer appears only after its owner makes it, as their own promotion, through the participants tool (0134's note,
   #1589).
3. **A business that has claimed** (the theater, or a restaurant) makes and prints its own codes.
4. **No sponsored or ranked wording** on any poster (decisions/0007).
5. **The ticket names** gate 2 and the revenue ladder: claims from the pilot (decisions/0181's ladder note).

**Logged for Q1, the IT lane (11):** short codes don't move to a business when it claims. Suggest a change to
decisions/0094: a listing's page code passes to its owner at claim, with its scan counts. For Jason via the session manager.
