# ADR 0143: The Stock Library: a System Repository of Non-User Photos

**Status:** Accepted (Jason, 2026-11-04). Replaces 0138 §6's plan of a curated Galleries collection: the library is a
**system repository** in System Configuration, and anyone publishing on the site can pick from it, within the rules
below. Builds on 0133 (the licence decides the use), 0066 (nations' land), 0127 (one uploader), 0138 §5 (honest when
thin), CLAUDE.md (no income, no expense).

## 1. What it's for

High-quality photos that **no one on the site created**, so cards, heroes and published items look good until their
owners add their own. Jason: "a way to add an image that is not user created, that way they can have high quality
images until they replace them with their own, but we don't want them used to represent something when they should
not, like here is a photo of my custom product, or my journey, because authenticity matters."

## 2. Where it can be used, and where it can't

Every place that takes an image is a **slot**, and each slot is one of two kinds:

| Slot kind | Stock allowed | Examples |
|---|---|---|
| **Illustrative** (stands for a subject or a place, not a claim) | Yes, labelled | Card and hero placeholders; a storefront or page banner; a place guide's or article's illustration; a home-page door; an event's backdrop before its own photos; a category tile |
| **Authentic** (says "this is the real thing") | **Never** | A product's or item's photos; a journey's, story's or review's photos; a gallery album; an event's own photos; a listing's own photos of its premises, people or work; a profile photo; proofs and orders |

- Stock is always **labelled**: "Stock photo · [credit]" on hover and in the image's details; "Representative photo"
  when it doesn't show the actual place.
- A stock image is a **placeholder** with a nudge to its owner ("Add your own photo"), and the moment a real one is
  added, the real one takes over, with nobody needing to swap it.
- Never sold, never shown as a specific business's, person's or event's own work. Nothing showing sacred, burial or
  protected sites (0066, `Landmarks::SENSITIVE`).

## 3. Where it lives

**System Configuration → Stock library** (admin): search the sources, add to the library, tag (subject; place from the
places tree; "this place" or "representative"), retire an image. Every image keeps its source, licence, author, credit
and the source's link. **Anyone publishing** picks from it through a **Stock** tab in the site's image picker, shown
only in illustrative slots.

## 4. Sources and how each is held

The licence decides the use (0133): only licences that allow commercial use; non-commercial, no-derivatives (where we'd
crop) and unknown are left out automatically. Hand-picked only: no bulk downloading, which every source forbids.

**Built now: three sources** (Jason, 2026-11-04: "use the first three and have the last three as a possible use for
later on"):

| Source | Held as | Credit | Key |
|---|---|---|---|
| Wikimedia Commons | A copy (WebP, our storage) | Author and licence, as the file states | None; requests carry a User-Agent with support@traversence.com |
| Library of Congress | A copy, after checking each photo's own rights line | The collection and photographer | None |
| Pixabay | A copy (permanent links to Pixabay aren't allowed; search results cached 24 hours) | Photographer and Pixabay | `PIXABAY_KEY` (set, 2026-11-04) |

**Possible later, not built:** the National Park Service (key from api.data.gov as `NPS_API_KEY`; requests to
`https://developer.nps.gov/api/v1/…` with the key in the `X-Api-Key` header; each photo's credit line checked),
Unsplash (`UNSPLASH_ACCESS_KEY`; held as a link, not a copy, with its download ping) and Pexels (`PEXELS_API_KEY`, once
new keys reopen; a copy with a link back). The library is built so each can be added as one more source.

## 5. Picking for cards

`StockLibrary::pick(subject, place)` finds the best fit for a card or hero with no image: this place first, then its
region, then representative images of the subject. The same image isn't repeated on one page where another fits.

## 6. Cost

The sources are free; copies are small WebP files in our storage. Within the no-income-no-expense rule: no paid source,
no per-use charge.

## 7. Build (one zip)

1. Migration: `stock_images` (source, source id, licence, author, credit, source URL, our file or the source's URL,
   subjects, place id, this place or representative, status, added by, added at) and a slot register.
2. `StockLibrary` (search per source, add, pick, credit text) and a source adapter each (Wikimedia, Library of
   Congress, Pixabay; written so another source is one more adapter), copies through the one uploader's storage (0127).
3. System Configuration → Stock library: search, add with tags, the library list, retire.
4. The image picker's **Stock** tab in illustrative slots only; the "Stock photo" label and credit; the "Add your own"
   nudge; real photos replacing stock by themselves.
5. Card and hero fallbacks (home page doors and events, place guides, listings without photos, storefront banners)
   reading `pick()`.
6. FAQ entries.

## Built (2026-11-04)

One zip, **built on the sync of 14:06 UTC** (2026-11-04; first built on 12:55, and `tools.php`, `faq.md`,
`storefront-view.php` and `admin/api.php` re-based onto 14:06 after another upload changed them). Waiting for Jason's
upload; the sync is then run and the live files checked byte for byte against the zip.

- **Migration** `app/migrations/2027-02-05_stock_library.sql`: `stock_images` (source, source id, title, licence and its
  link, author, credit, source link, our WebP copy and card, or a source's link for a later source held that way;
  subjects; place key and "this place" or "representative"; status; who added and retired it), `stock_slots` (the slot
  register, seeded; the code's `StockLibrary::SLOTS` wins), `stock_uses` (the photo chosen for an illustrative slot) and
  `stock_search_cache` (24 hours). Safe to run twice; runs by itself.
- **`app/lib/StockLibrary.php`**: `search` (cached 24 hours; licences checked: commercial use and changes allowed, so
  non-commercial, no-derivatives and unknown are left out automatically; the Library of Congress's own rights line read
  for each photo; `Landmarks::SENSITIVE` keeps out what may show a sacred, burial or protected site), `add` (hand-picked,
  at most 30 per person in 10 minutes; the photo re-read from its source, never trusted from the page; fetched only from
  the source's own hosts; kept as WebP, 2000 px and a 640 px card, with nothing from the original file), `pick(subject,
  place)` (this place, then the places it's in, then the subject; a photo of somewhere else never stands for this place;
  no repeats on a page where another fits), `forSlot`, `setUse`, `find`, `credit` and `label`, retag, retire, restore.
- **Sources**, one adapter each in `app/lib/stock/` behind `StockSource`: `StockWikimedia` (no key; User-Agent names
  Traversence and support@traversence.com), `StockLoc` (no key; the loc.gov JSON API the crawler uses), `StockPixabay`
  (`PIXABAY_KEY`; copies only, searches cached 24 hours). The Park Service, Unsplash and Pexels are listed in
  `StockLibrary::LATER`; each is one more adapter and one line in `SOURCES`.
- **Storage**: `Store::KINDS` gains `system`; copies live at `/uploads/system/<folder>/stock/…` and move to S3 like every
  other photo (decisions/0127). No upload path was added; `$_FILES` is still only in `Uploads.php` and `api/upload.php`.
- **System Configuration → Stock library** (`config/stock`): search a source, add with subjects, a place from the places
  tree and "this place" or "representative"; choose the home page's doors (or leave them automatic); where stock may and
  may never go; the later sources. The library is the `config/stock` tool under it: each photo opens to retag, retire
  (off every card at once; choices fall back to automatic) or put back. Search needs the tool's view; adding, choosing
  and retiring need `config/stock.edit` (Content, Operations, Engineering), greyed with the reason otherwise.
- **The image picker's Stock tab** (`js/tv-stock.js`, `/api/stock.php`): illustrative slots only; the endpoint refuses
  authentic ones. Wired now for a storefront's cover (Storefront & pages → "Choose a stock photo", "Let us pick"; never
  the logo) and the home page's doors. Busy spinners and the site's notices throughout.
- **Labels** (`app/ui/stock-ui.php`): "Stock photo" on the photo, the credit on hover and in its details (who took it,
  the licence, the source, "About stock photos"); "Representative" where it isn't the actual place (always, for a
  business). "Add your own photo" for the owner of a listing's hero.
- **Fallbacks** reading `forSlot`/`pick`: the hero (`explore-hero.php` 'stock' option) on place guides (`hub/`) and
  listings with no photos (`listing/view.php`); the home page's doors, event backdrops (real and sample) and feed cards
  for events and businesses without photos; storefront banners on trvgo.us. A real photo takes over by itself: stock is
  looked up only where nothing of its own exists.
- **FAQ**: why some photos say Stock photo; what Representative means; where stock can and can't be used; replacing it
  with your own; choosing one for a storefront; and a line on the place photos answer.
- **Checked**: licence, rights-line, sensitivity, tagging, ranking, choosing, retiring and WebP tests (60 passing); the
  hero, doors, event card, storefront banner and picker at 390 px and 1200 px (3 px edges, no sideways scroll). The
  sources' live APIs couldn't be reached from the build session, so the adapters were tested against sample answers in
  each API's documented shape: try one search per source after upload.
- **Not now**: a Stock tab for guide and article illustrations, page banners and category tiles (the slots are in the
  register; their editors add the picker when they're built), and the directory's own result cards.
- **No income, no expense**: free sources, small WebP copies in our storage; no paid source, no per-use charge.

Files in the zip (21): `app/admin/manifests.php`, `app/admin/sections.php`, `app/admin/tools.php`,
`app/business/storefront.php`, `app/lib/Store.php`, `app/lib/StockLibrary.php`, `app/lib/stock/StockSource.php`,
`app/lib/stock/StockWikimedia.php`, `app/lib/stock/StockLoc.php`, `app/lib/stock/StockPixabay.php`,
`app/migrations/2027-02-05_stock_library.sql`, `app/ui/explore-hero.php`, `app/ui/faq.md`, `app/ui/stock-ui.php`,
`app/ui/storefront-view.php`, `website/admin/api.php`, `website/api/stock.php`, `website/hub/index.php`,
`website/index.php`, `website/js/tv-stock.js`, `website/listing/view.php`. No dotfiles changed.
