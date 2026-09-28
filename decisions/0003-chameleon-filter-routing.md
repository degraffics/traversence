# ADR 0003: Chameleon Filter Routing Between Directory and Discovery

**Status:** Accepted

## Context

Traversence serves two structurally different intents from the same user base: everyday utility-seeking (residents wanting local services right now) and exploratory narrative-seeking (travelers wanting regional context and history). Mixing both into one generic search results view would dilute both — a bureaucratic directory list serves neither intent well.

## Decision

Route front-end discovery through a "Chameleon Filter" that splits traffic into two distinct intent vectors before it reaches a search backend:

- **Bottom-Up Discovery (`/directory/`)** — spatial radius feeds, ZIP-coordinate resolution, everyday municipal utility, for the Resident ("Get Local") track.
- **Top-Down Exploration (`/discovery/`)** — corridor-based navigation, historical memory, regional heritage loops, keyed by `corridor_id`, for the Traveler ("Let's Explore") track.

Each track gets its own accent color token (see `brand.md`) so the UI itself signals which mode the user is in, and switching tracks is framed as "re-centering an instrument dial" rather than a full navigation reset.

## Consequences

Every new discovery feature has to be classified into one track or explicitly built as track-agnostic — there's no default "just add it to search" option. This keeps the two experiences from blurring together over time, at the cost of some engineering duplication where a feature (e.g. a map view) genuinely serves both tracks and needs two entry points instead of one.
