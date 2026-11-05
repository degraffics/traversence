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
- **The Gallery is the one tool for art** (Jason, 2026-11-04: "the gallery is the tool, the artwork is theirs or stock
  separated by tabs or clear sections"; managing stock and their own art apart would split the user into two paths). The
  Gallery has a third tab, **Stock photos**, beside Photos & videos and Albums: search the library, each photo labelled
  with its credit, and, for a business, **Use as storefront cover** and where it uses stock now (Let us pick). Stock is
  never theirs: it never goes in their photos or albums. The picker has the same two tabs, **Your gallery** and
  **Stock** (`TvStock.pick({mine})`, `/api/stock.php?mine=1&b=`); the storefront's cover opens it on Your gallery.
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

Files in the zip (22): `app/admin/manifests.php`, `app/admin/sections.php`, `app/admin/tools.php`,
`app/business/storefront.php`, `app/lib/Store.php`, `app/lib/StockLibrary.php`, `app/lib/stock/StockSource.php`,
`app/lib/stock/StockWikimedia.php`, `app/lib/stock/StockLoc.php`, `app/lib/stock/StockPixabay.php`,
`app/migrations/2027-02-05_stock_library.sql`, `app/ui/explore-hero.php`, `app/ui/faq.md`, `app/ui/stock-ui.php`,
`app/ui/storefront-view.php`, `website/admin/api.php`, `website/api/stock.php`, `website/gallery/index.php`, `website/hub/index.php`,
`website/index.php`, `website/js/tv-stock.js`, `website/listing/view.php`. No dotfiles changed.

## Built (2026-11-04, second zip): the Gallery is where every file is chosen

Jason, 2026-11-04: "where ever a photo upload or select option is … it routes through the gallery tool. … The gallery
will be the tool for managing all artwork and documents so it must be easy to find and work with these files", "we
could also approach the gallery with catalogs for each segment", and the computer's file pop-ups shouldn't come first.
**Built on the sync of 14:45 UTC.** The first zip went live at 14:24/14:38, but a later upload replaced `app/ui/faq.md`
without its stock entries; this zip's `faq.md` puts them back on the 14:45 copy.

- **One panel for every file input** (`js/tv-art.js`, loaded by `tv-upload.js` on every page): any `<input type=file>`
  opens it instead of the computer's dialog. **Your gallery** (by catalog; one or several) on inputs that take photos
  alone; **Stock** only where the input illustrates (`data-art-slot`; the storefront cover now); **Upload new**: drag in,
  paste a screenshot, or Browse your device (the computer's dialog, only when asked). Inputs for orders, proofs, labels
  and documents (anything that takes more than photos) get Upload new alone: those files belong to their record, not to
  anyone's Gallery. `data-art="off"` keeps the old dialog; `data-art-b` names a business's Gallery.
- **Nothing kept twice:** a photo picked from the Gallery reaches the page as a file (so every page works as before) and is
  marked; `Uploads::finish` sees `_from_item` and, for a destination that keeps its photos in the Gallery anyway (a
  target's new `pick`: `gallery_photo` into an album, `market_photo`, `catalog_photo`, `storefront_photo`), uses the one
  already there and lets the sent copy go. Journeys, profiles and a listing's top photos keep their own copy, as before.
- **Catalogs** (`app/lib/GalleryCatalogs.php`): Uploaded here, Journeys, Profile (or Listing photos), Marketplace,
  Storefront, worked out from what the Gallery already knows; one **Catalog** button on Photos & videos (`?cat=`) and a
  catalog list in the panel. Albums stay the person's own; Stock photos keep their own tab.
- **Storefront:** Logo and Cover each say **Choose a photo** and open the panel (the cover with Stock); the separate
  "Choose from your gallery or stock" button and "Or choose one of your photos" list are gone, and `Storefront::photos()`,
  which only that list used, is deleted.
- **Order management:** its uploads (proofs, print-ready files, artwork, labels) already went through the one uploader;
  they now open the panel's Upload new (drop, paste, Browse) and are otherwise unchanged.
- Panels sit at z-index 1995, under the site's notices, so their messages show.
- **FAQ:** how to add a photo or a file; the Gallery's catalogs; the storefront answer updated.
- **Checked** at 390 px: a journey input opens Your gallery and Upload new with no system dialog; two Gallery photos
  arrive marked with their ids; the cover adds Stock; a proof input opens Upload new alone and takes a dropped PDF;
  Browse opens the system dialog; no sideways scroll. Catalog sorting tested.

Files in the zip (12): `app/business/storefront.php`, `app/lib/GalleryCatalogs.php`, `app/lib/Storefront.php`,
`app/lib/Uploads.php`, `app/lib/upload-targets.php`, `app/ui/faq.md`, `website/api/stock.php`,
`website/gallery/index.php`, `website/js/hero-media.js`, `website/js/tv-art.js`, `website/js/tv-stock.js`,
`website/js/tv-upload.js`.

## Built (2026-11-04, third zip): every file through the Gallery, which knows where each one went

Jason, 2026-11-04, on the second zip's "order files never show the Gallery": "This is bad segmenting. The gallery
should handle all access to files and have the intelligence to know how they are used. Choosing an existing document
from the Gallery's Brand & documents albums: build this." **Built on the sync of 15:39 UTC**, which had the second zip
live (all 12 files checked byte for byte).

- **Every file input gets Your gallery**, orders, proofs, labels and quotes included: photos (by catalog) and documents,
  as the input accepts them, each showing where it's been used. Stock stays only where a photo illustrates.
- **New files go into the Gallery first:** a document uploaded at any input is kept in the owner's **Documents** album
  (Brand & documents, private, made the first time: `GalleryCatalogs::documentsAlbum`), then handed to the page as a
  Gallery item. Photos go straight to the page as before (where they're used already puts them in the Gallery).
- **Nothing sent twice:** `tv-upload.js` sends a Gallery item as `{do: 'gallery', ctx: {_from_item}}`, no bytes;
  `Uploads::gallery` checks it by the destination's own rules, then uses it where it is (a target's `pick`) or copies it
  S3 to S3 into the destination's own place (an order still keeps its own copy: its record outlives an account). A
  Gallery photo the page needs in hand (to crop, to show) comes from `/api/art-file.php`, on this site's address, never
  through an S3 redirect.
- **Where it's used** (`gallery_uses`, `app/lib/GalleryUses.php`; migration `2027-02-06_gallery_uses.sql`): each time a
  Gallery item goes somewhere, a row with its label and link ("Order #9 · proof", "Storefront cover", "Journey"). Shown in
  the panel's tiles, on the photo in the Gallery's viewer ("Used in …"), and beside each document.
- **Documents tab** in the Gallery: every file, newest first, with size, version, albums and where it's been used;
  search; download; **Add documents** (into the Documents album).
- **FAQ:** the add-a-file entry now covers orders and documents; new: does the Gallery know where my files are used;
  where are my documents.
- **Checked:** an order-proof input shows Your gallery and Upload new; choosing a kept PDF sends one `gallery` request
  (no bytes) naming it; uploading a new PDF there stores it once in Documents, then sends it to the order as a Gallery
  item; the photo-input tests still pass; Documents tab and panel at 390 px, no sideways scroll. The server copy (S3 to
  S3) and `gallery_uses` rows need the live site: after upload, add a proof from the Gallery to a test order.
- **For Jason:** on admin pages (client orders) the Gallery used is the staff member's own, so a proof a staff member
  uploads is kept in their Documents. If Traversence should have its own company Gallery for staff work, that's a
  decision to make.

Files in the zip (12): `app/lib/GalleryCatalogs.php`, `app/lib/GalleryUses.php`, `app/lib/Uploads.php`,
`app/migrations/2027-02-06_gallery_uses.sql`, `app/ui/faq.md`, `website/api/art-file.php`, `website/api/stock.php`,
`website/api/upload.php`, `website/gallery/index.php`, `website/js/gallery.js`, `website/js/tv-art.js`,
`website/js/tv-upload.js`.

## Built (2026-11-04, fourth zip): the Stock search finds photos at the sources

Jason, 2026-11-04, searching "flowers in a field" on the Gallery's Stock tab: "search not working." It was searching only
the library, which nobody had filled yet. **Built on the sync of 15:55 UTC**, which had the third zip live (its
migration ran and was deleted; `faq.md` had been replaced again by an older copy, so this zip carries it rebuilt).

- **Search reaches the sources** (`StockLibrary::discover`): wherever stock is chosen (the Gallery's Stock tab, the
  picker's Stock tab, the admin's door picker), a search shows what's in the library, then free photos found at
  Wikimedia Commons, the Library of Congress and Pixabay, interleaved, each naming its source with its credit and licence.
  The same rules as the admin search: kept 24 hours; non-commercial, no-derivatives and unknown licences, unclear
  Library of Congress rights lines and anything that may show a protected site are left out; photos already in the
  library aren't repeated.
- **Choosing one adds it to the library then** (`StockLibrary::adopt`, `/api/stock.php` action `adopt`): one at a time,
  by the person who chose it, with its licence and place checked again, copied as WebP and tagged with what they
  searched for, "representative". One already in the library is used as it is; one the team retired is refused. The
  admin can retag or retire any of them in System Configuration → Stock library. This widens §3 (staff add from System
  Configuration): anyone choosing stock for an illustrative spot now hand-picks it into the library too.
- `tv-upload.js` loads the new `tv-art.js` (version bumped so browsers don't keep the old one).
- **FAQ:** "Where do I find stock photos?" now says the search covers the sources.
- **Checked:** with sample answers from each source, the filtering keeps one from each and leaves out a non-commercial
  licence, a cemetery photo, an unclear rights line and one already in; adopting an existing photo returns it and a
  retired one is refused; in the browser, choosing a found photo in the picker and on the Gallery's Stock tab adds it then
  sets the storefront cover; 390 px, no sideways scroll. The live sources can't be reached from the build session: try a
  search after upload (Pixabay needs `PIXABAY_KEY` in `.env`).

Files in the zip (7): `app/lib/StockLibrary.php`, `app/ui/faq.md`, `app/ui/stock-ui.php`, `website/api/stock.php`,
`website/js/tv-art.js`, `website/js/tv-stock.js`, `website/js/tv-upload.js`.

## Built (2026-11-05, fifth zip): our team has no Gallery of its own; files belong to records

Jason, 2026-11-05: "Trv staff don't need individual gallery, all photos, videos, and file[s] will be assigned to an
order or something other than individual." This replaces the third zip's open question (a staff member's uploads kept
in their own Documents). **Built on the sync of 23:26 UTC (2026-11-04)**; the fourth zip was live at 16:01, all 7 files
checked byte for byte.

- **On admin pages** (`/admin/…`) the Gallery panel never shows or fills a staff member's own Gallery. On an order
  (`/admin/backoffice/orders/<id>` or `client-order.php?id=`, or an input's `data-art-record="order:<id>"`) its first tab
  is **This order's files**: the order's proofs and files, then **This client's earlier orders** (up to 12, by the
  client's account or email), to reuse approved art on a reorder (`app/lib/RecordFiles.php`, `/api/stock.php?record=`).
  Elsewhere in the admin it's **Upload new** only.
- A file chosen there travels as `ctx._from_record` (`order_proof:<id>`, `order_file:<id>`) and is copied server to server
  into its new place (`Uploads::gallery` → `fromRecord`), under the order-files rule (`backoffice/orders.edit`). New
  files on admin pages go straight to the record, never to a Documents album.
- Members and businesses keep their Galleries as built: a person's own, a business's for its team.
- **FAQ:** the add-a-file entry no longer says order files go through your Gallery; it says our team's files for your
  order are kept with the order and reused when you reorder.
- **CLAUDE.md** carries the rule: every file input opens the Gallery panel; staff have no personal Gallery.
- **Checked** in the browser on an order page: the first tab is "This order's files"; choosing a client's earlier proof
  sends only its reference; a new upload goes straight to the order with no Documents step; 390 px, no sideways scroll.
- Any Documents album already made for a staff member during testing stays until someone removes it; nothing new goes in.

Files in the zip (6): `app/lib/RecordFiles.php`, `app/lib/Uploads.php`, `app/ui/faq.md`, `website/api/stock.php`,
`website/js/tv-art.js`, `website/js/tv-upload.js`.
