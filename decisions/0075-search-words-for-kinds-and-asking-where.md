# 0075. Search: words that cover several kinds, and asking where inside the search

- **Status:** Accepted
- **Date:** 2026-10-10
- **Builds on:** 0060 (search reach), 0067 (what words mean; staff words for kinds), 0073 (categories)

## Context

Jason searched "mental health" with no place set and felt "something off": 19 results, all businesses with "mental health"
in their name or category (several of them filed under plain "Health Services"), while about 80 counselors,
psychologists, counseling services, social workers and treatment centers were missing. The closest kinds offered were
only the three whose names say "mental health" (11 listings between them). The counts disagreed with each other:
Results (19) counted the three categories as results, and "See all 17 listings in the directory" came from the
directory's own count. Searching "dentist" offered **Home Health Care** ("20 listed · for 'dentist'") because the word
data ties "dentist" loosely (0.62) to Home Health Service, and anything over 0.6 counted as sure.

Pressing Enter on "dentist" with no place opened the place picker over the page. Jason: "abrupt and feels like a gate vs
'please set your location' within the search experience."

## Decision

1. **Staff words for kinds are also words a listing is found by.** `category_terms` (the words staff give a kind, as
   the search-misses tool adds them) now go into each listing's search terms, for its primary and secondary categories.
   A new migration (`2026-11-15_mental_health_words.sql`, safe to run again) gives "mental health", "behavioral health"
   and "behavioural health" to the twelve kinds of mental health and addiction care, and "addiction" and "substance
   abuse" to the four treatment kinds. "recovery" was left out: it also means towing and data recovery. The search index
   must be rebuilt once after the upload (Admin → Search index → Build the index). Locally, "mental health" went from 19
   listings to 92, with the ones named for it first.
2. **Closest kinds** take every kind the words give, then keep the best four: the one the words name first, then the
   meaning's order, and among equals (staff words give several at once) the most listed. "Mental health" shows Mental
   Health Services, the outpatient clinics, Mental Health Counselors and Counselors (29).
3. **A word that names a kind outright** ("dentist" → Dentists) takes other kinds only when they're close: 0.75, not the
   general 0.6. Home Health drops from "dentist"; Mental Health Counselors (0.81) stays for "mental health".
4. **Counts agree.** Results counts what's listed under it (categories found aren't counted; they're in Suggestions),
   and the directory link reads "See them all in the directory", without a second number that could disagree.
5. **Where to look is asked inside the search.** No pop-up opens on Enter. Above the results, still showing, search asks
   **Where should we look?** ("Showing everywhere for now; a place puts what's near it first") with a **Town or ZIP**
   box that finds places as the picker does (our places first, then towns; a ZIP as it is), **Use my location**,
   **Keep everywhere** and **More choices** (the full picker, only when tapped). Picking a place applies it and the
   results refresh in place. "Near me" asks the same way, without Keep everywhere.

## Consequences

- Staff words added later from the search-misses tool reach listings at the next index build.
- Mental health listings filed under plain "Health Services" (Compass Mental Health-Wellness, Renewal Mental Health,
  Vital Mental Health & Wellness) still read as Health Services; recategorizing them is staff's call in the listing editor.
- "counseling" alone is read as the situation "Someone to talk to", which lists by place; with no place set it asks where.
- Code: `app/lib/SearchIndex.php` (`kindWords`), `app/lib/UniversalSearch.php` (`meant`), `app/lib/Concepts.php`
  (`SEEDED`), `website/js/search-panel.js` (`nearAsk`, `nearType`, `nearPick`; Results count; directory link),
  `app/ui/header.php` (styles), `app/migrations/2026-11-15_mental_health_words.sql`.
