# writeOrder: a Marketplace sale's copy in the business's books (gate 5)

The IT Manager's (11) ticket from the session manager, 2026-11-27. Build in `writeorder-tenant/build/`, built on Sync 19:50 UTC (dd02bdd); tests in `tools/market-tests/write-order.php` (21) and `write-order-place.php` (24), MariaDB.

## What it fixes (live, read on the mirror)
- `BusinessData::open` switched the whole request to one business (`Scope::enterBusiness`) and never switched back, so in the daily tick's reconcile (`MarketCheckout.php:756`) every card order after the first lost its copy (`Scope.php:19`, "already working for a business").
- Placing a direct-pay order ran `writeOrder` outside any try (`:391`): a failed copy reached the buyer as an error.
- A copy failing after its first line was left half-written.

## What's in it
- `BusinessData::scoped()` + `Scope::leave()`: the tenant put back in a finally; a request already inside a business refused.
- `writeOrder`: one transaction (lines, total, card payment, or nothing); the link after commit, a failed link raising a "link it" ticket.
- `copyFailed()`: the order's note (`COPY_HELD`), one Work queue ticket per order naming the hold's deadline; `copyLater(card)` for the business.
- `tick()`: a direct-pay order whose copy failed isn't expired, nor its dates released, until the copy is linked.

## Reviews
- **CFO (4), Tier 4.** Three passes; approved on the hold condition, which is built. The first pass found the half-written copy, the dead end, the raw error to the business, the card path's words and the webhook claim; all fixed. The second pass found seven defects in the second try and backfill, which moved to their own release (below).
- **QA**, independent, three passes. Its last finding (the note outside its try) is fixed. Its third pass ran 50 checks.
- **Design** approved the words, with two conditions:
  - the team's case is raised in the same step (it is: tested);
  - the retry release's "it's there" message also covers invoices the team adds by hand.

## The retry and backfill release (not built; after MarketSquare/Customers, before about Dec 10, the theater's ticket sales)
Behind `market_copy_backfill`, off as it ships, with a dry run that lists each order as a step for the CFO. The CFO's seven points:
1. Reuse only an approved or paid copy; finish or replace a draft. Count live drafts titled "Marketplace order #…" first.
2. The order's own tax, so the invoice total equals `total_cents`.
3. `paid_on` set to `paid_at`.
4. A retry cap or attempt order, so failing orders can't starve newer ones, plus an alert.
5. Expired orders included.
6. A duplicate-payment key that isn't the note.
7. A stricter key than the title: never reuse a quote already linked to another order (QA H2).

Also:
- A button on the ticket that writes or links the copy.
- QA's G5: an order copied and expired in one pass.
- QA's F2d: a second write for an unlinked order duplicates.
- Design's message 2 ("now in Quotes & invoices"), covering copies the team adds by hand.

## Logged as their own IT tickets (gate 5), one release after the W1 trips
- `MarketSquare::import` and `sync` (`MarketSquare.php:120`, `:160`) open a business and never leave, so every Square sync after the first in `market_tick` fails. Move them onto `scoped()`.
- `Customers::$ready` caches per request, so later businesses in one request skip their starter tags.
