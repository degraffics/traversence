# ADR 0146: Suggested Journeys from Guides

**Status:** Proposed (Jason, 2026-11-06), with the questions in §6 to answer before it is built.

## Why

Jason: where we have a city guide like Show Low, Arizona, a journey would be likely places along the guide's outline,
using our APIs and the crawler to suggest a journey: a route and experiences. The same journeys become the site audit's
guide paths (decision 0145 §1), so what the system suggests is also what it tests.

## 1. What a suggested journey is

- **What it is:** a Traversence journey (decision 0056: our editorial voice, labelled as ours) that the system writes
  from a live guide.
- **What's in it:** a short route through the guide's own places, the things it already recommends:
  - what to see: landmarks, parks, heritage;
  - where to eat and stay;
  - what to do: experiences, outdoor places, events while they're on.
- **The route:**
  - the stops are ordered for travel (the nearest next, ending near where it began, or along the road for a drive);
  - each stop is timed against its open hours and the time of day, so breakfast comes in the morning and a museum
    falls inside its hours;
  - each stop has directions (a map link, decision 0058 §8) and its own page.
- **Shapes:** a few per guide where the places allow:
  - "A day in Show Low";
  - "A morning outdoors";
  - "Heritage and history";
  - "Where to eat";
  - a drive between nearby guides.

## 2. Where it comes from: data we already have

- **The places:** the guide's picks and mentions (decisions 0131, 0133), our listings, landmarks and outdoor places.
- **The facts:** open hours, phone, website and photos (the landmark photo finder, decision 0143 note).
- **Distances:** straight-line between stops at first; driving distances only if they can be got free.
- **The checks:** the crawler confirms each place is still open and still there before it's used, and again when the
  guide is renewed.
- **Writing:** the route and the stop order are built by rules, with no AI and no cost. A one-line introduction per
  stop can come from the guide's own text. The guide's AI writing budget is never used without its own allowance
  (decision 0133; no income, no expense).

## 3. The rules it follows

- **Decision 0133:** curators, not controllers. "Tell the story, never the way in": no protected or sensitive places
  (decision 0066, `Landmarks::visibleSql()`). The licence decides each fact's use.
- **Fairness:** a business is included because it fits the route, never because it pays. Paid placement, if ever
  offered, is labelled and kept apart.
- **Accuracy:** a stop that's closed, moved or gone is dropped at the next check and the route is rebuilt. A journey
  with too few stops left is taken down until it can be rebuilt.
- **Labelling:** suggested journeys are labelled "Suggested by Traversence" and kept apart from contributors' own
  journeys in feeds and counts.

## 4. Publishing

- **Like guides** (decision 0131): built and checked by the system, published by rule.
- **Staff:** may edit, pin or unpublish one, and their changes are kept.
- **People:** visitors can save a suggested journey to their trips (the Trip Planner), change it, and share it.

## 5. The site audit uses them

Every suggested journey is a guide path in the audit (decision 0145 §1). A visitor follows it in a browser: the
journey's page, then each stop. The audit checks:
- each page loads;
- its facts are true now;
- the directions open;
- the order makes sense.

A failure reaches the guide's Pulse case as well as the scorecard.

## 6. Questions for Jason before it's built

1. **Which shapes first:** "A day in [town]" only, or also themed ones (outdoors, heritage, food) and drives between
   towns?
2. **How many stops:** suggested 4–7 for a day, 3–4 for a morning.
3. **Driving distances:** straight-line only (free), or a free routing service if one fits our no-cost rule. Straight
   line is suggested to start.
4. **Where they appear:** on the guide's page ("Suggested journeys"), in Discovery and the Trip Planner, and in
   search. Suggested: all of them.
5. **Businesses:** may a business ask to be left out of suggested journeys? Suggested: yes, from its business portal.
