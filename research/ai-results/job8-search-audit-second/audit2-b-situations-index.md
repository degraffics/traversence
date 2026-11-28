# Review: search ranking and situation kinds (decisions 0060, 0061, 0067)

## What the decisions require

**Ranking**
- **R1.** Search never changes the place someone set. Results are local first, never only local, and anything beyond the place comes nearest first with its miles. (0060 §Principles 1–2)
- **R2.** Beyond takes the best matches first, then the nearest, "so a nearby real match isn't crowded out". (0060 §Phase B, "Beyond")
- **R3.** A need like "urgent care" ranks that exact kind first, then the broader health kinds, and never businesses outside the kind. Hours must be honest: the listing's own time zone, read by one shared function. (0060, Note 2026-11-27)
- **R4.** For the first need of Feeling sick and An injury:
  - Categories come in this order: Urgent Medical Care Centers And Clinics, then Clinics and Medical Centers, then Hospitals. Health Services and Ambulatory Health Care Services come last, and only with a health category.
  - Rows are ordered by the need's category position, then distance, and never by listing number. The same applies to Pharmacies: pharmacies before pharmacists, and individuals never shown as places.
  - "Urgent care", "walk in clinic" and "need a doctor today" read as urgent for ordering: open now first by `Hours::now`, never hiding the rest.
  
  (0061, Note 2026-11-27)
- **R5.** Each need goes to its own track. The page someone searched from only breaks ties. (0061 §Principles 4)
- **R6.** A listing missing what its need requires isn't offered for that need. (0061 §Principles 5)
- **R7.** Several situations combine; matching is whole phrases, longest first. (0061 §"The situation map", Matching)
- **R8.** When the words surely mean a kind, that kind comes first, then its places. Names that only share a word come after, and only when the word is whole ("music", never Musick). (0067, 2026-10-07 "essence" item 3)

**Kinds**
- **K1.** A need's categories are what actually solves it. (0061 §"The situation map",