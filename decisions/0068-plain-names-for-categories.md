# 0068. Plain names for categories

Date: 2026-10-07
Status: Accepted

## Context

Our categories come from business-listing data and read like trade headings: "Physicians & Surgeons",
"Storage-Household & Commercial", "Air Conditioning & Heating-Service/Repair", "Physicians & Surgeons Equip & Supls-Whls".
Visitors saw those words on cards, listing pages, the directory and search. Some were also near-duplicates: Storage,
Storage-Household & Commercial and Warehouses-Merchandise & Self Storage are one kind of place to a visitor.

A three-tier facility classification proposal was reviewed at the same time. Its first tier (a fixed set of broad
groups) we already have. Its second tier (a curated list of facility types) is what the categories plus the concept
layer (decisions/0067) and the kinds search adds on its own already do. Its useful part was the one we lacked: a name a
visitor would use. Its third tier (attributes and tags: wheelchair access, by appointment, serves your area, 21+) is
still open and is the next piece worth building.

## Decision

1. **Each category has a plain name**, `categories.display_name` (migration `2026-11-09_category_plain_names.sql`),
   shown wherever a visitor sees the category: listing cards and pages, the directory, the category picker and search.
   "Physicians & Surgeons" shows as "Doctors", "Attorneys" as "Lawyers". Wholesale and manufacturer categories say so:
   "Medical Equipment (wholesale)".
2. **The category itself is unchanged.** Its name, slug and id stay, as does everything that uses them: imports, the
   crawler, situations, concepts, staff tools. Search reads both names.
3. **Categories that share a plain name are one kind to a visitor.** The directory, a category's search and the picker
   treat them as one (Self Storage is three of ours: 35 listings, one row).
4. **The source is a list a person can read**, `workers/lexicon/category-plain-names.tsv` (raw name → plain name), and
   the migration is written from it. A category with no plain name shows its own name, tidied as before. If the column
   isn't there yet, everything reads the old name.

## Consequences

- New categories (including the kinds search adds, decisions/0067) are named plainly when they're made, so they need
  no entry.
- Rewording a plain name is one UPDATE and a search index rebuild; no code changes.
- The 23 top-level groups still hold some misfiled categories; tidying them is separate.
