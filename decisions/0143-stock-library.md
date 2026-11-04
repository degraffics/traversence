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

| Source | Held as | Credit | Key |
|---|---|---|---|
| Wikimedia Commons | A copy (WebP, our storage) | Author and licence, as the file states | None; requests carry a User-Agent with support@traversence.com |
| US government (Library of Congress, Park Service) | A copy, after checking each photo's own rights line | Agency and photographer | Park Service: free key |
| Pixabay | A copy (permanent links to Pixabay aren't allowed; search results cached 24 hours) | Photographer and Pixabay | Free key |
| Unsplash | **A link, not a copy** (Unsplash requires its own URLs and a download ping when an image is chosen) | Photographer and Unsplash, linked | Free key (50 requests an hour is enough for hand-picking) |
| Pexels | A copy, with a link back | Photographer and Pexels | When new keys reopen |

Each source switches on when its key is in `.env` and shows "not set up" until then. Wikimedia works with none.
The `.env` names: `PIXABAY_KEY` (set, 2026-11-04), `UNSPLASH_ACCESS_KEY`, `NPS_API_KEY`, `PEXELS_API_KEY`.
Park Service requests go to `https://developer.nps.gov/api/v1/…` (e.g. `/parks?parkCode=pefo`, whose `images` carry
title, credit and alt text) with the key in the `X-Api-Key` header, not the address. Its key comes from api.data.gov
(the Park Service sign-up form failed on 2026-11-04; api.data.gov/signup issues the same kind of key).

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
   Congress, Park Service, Pixabay, Unsplash, Pexels), copies through the one uploader's storage (0127).
3. System Configuration → Stock library: search, add with tags, the library list, retire.
4. The image picker's **Stock** tab in illustrative slots only; the "Stock photo" label and credit; the "Add your own"
   nudge; real photos replacing stock by themselves.
5. Card and hero fallbacks (home page doors and events, place guides, listings without photos, storefront banners)
   reading `pick()`.
6. FAQ entries.
