# ADR 0054: The Nexus — Organizations With Many Locations

**Status:** Proposed (2026-09-30), from Jason's direction; to be accepted before build. Works with `decisions/0042`
(Single Home Rule), `decisions/0044` (auto-import guardrails), `decisions/0050` (no sensitive inferences) and the search
rework (`decisions/0053`, to be written).

## Context

One website often stands for many places: White Mountain Physical Therapy has seven clinics across the White Mountains
under one site; the VA runs a system of medical centers and clinics; a state agency has offices in many towns; a chain
has a store in every town; a church has congregations everywhere. Today each location is a separate listing with
nothing tying them together, so search shows six near-identical results, an owner has to claim each one, and nobody
can see "all the places this organization serves".

The crawler already makes one proposed listing per physical address it finds (the Single Home Rule), and since
2026-09-30 it follows a site's Locations menu. The missing piece is the organization above the locations: the Nexus.

## Decision

### 1. A Nexus is an organization record above its locations

- Each location stays its own listing, with its own address, phone, hours and home cluster (Single Home Rule
  unchanged).
- A Nexus holds what the locations share: name, website, about, services, logo, and who manages it.
- A listing points to at most one Nexus, with a role: location, clinic, office, campus, meetinghouse, branch.

### 2. Three kinds, because they behave differently

| Kind | Examples | Shape | Who manages |
|---|---|---|---|
| **Brand / chain** (one operator) | White Mountain Physical Therapy, a grocery chain | Flat: brand, then locations | The brand; a franchisee manages its own location under it |
| **System / agency** (a branch tree) | VA, then VA Northern Arizona Healthcare System, then a clinic; State of Arizona, then ADOT, then MVD offices | Nested: a Nexus can have a parent Nexus | Each level manages what's below it |
| **Affiliation** (independent units, shared identity) | LDS meetinghouses, Catholic parishes (under a diocese), Baptist churches, credit union networks | Umbrella, then units | Each unit manages itself; the umbrella is a label, not an owner |

- **Tribal nations** are always the top of their own tree, as sovereign nations, never under a US government node.
  The nation decides what's listed (`decisions/0043`).
- **Meetinghouses:** where several congregations share one building (e.g. LDS wards), the listing is the building;
  the congregations and their meeting times are details on it, not separate listings.

### 3. Search: what the person asked for decides grouping

- **A search that names the organization** ("VA", "White Mountain PT", "LDS") shows one grouped result: the Nexus,
  how many locations are in the area, the nearest few, "see all on the map". A system shows as a branch tree.
- **A search for a category** ("physical therapy", "gas station", "church") shows individual locations, nearest
  first, each tagged "Part of [Nexus]". One Nexus never floods the page: after its first 2–3 locations, the rest fold
  into "+N more [Nexus] locations".
- Recognizing an organization name in a query is part of query understanding in `decisions/0053`.

### 4. On the site

- Each location's page: "Part of [Nexus]" and "Other locations", nearest first.
- A Nexus page: about, services, and every location on a map and in a list, sortable by distance.
- Linking a location links that location only. A Nexus can be linked separately, to follow the organization
  (its news reaches you, not every location's).

### 5. Claims and management

- One verified claim of a Nexus covers its locations; its managers edit shared details once.
- A location can also have its own local manager (a clinic director, a franchisee), who edits that location only.
- Affiliations never grant management: a diocese doesn't manage a parish's listing through the Nexus.

### 6. Built from evidence, never from a similar name

A Nexus link needs evidence, and a person confirms it before it publishes (`decisions/0044`):
- the same website, with the locations listed on its own pages (the crawler's Locations menu);
- the same parent in public data (an NPI organization's other practice locations, IRS group exemptions, a VA
  facility list);
- the organization's own claim.

A similar name alone is never enough: White Mountain Precast is not White Mountain Physical Therapy.

### 7. Guardrails

- Affiliation is public information about a place only. It never says anything about the people who link, visit or
  message it (`decisions/0050`: no sensitive inferences, including religion).
- Government and tribal systems show only public offices and public services; no restricted or sacred sites are
  pinpointed (`decisions/0043`).

## Consequences

- Data: a `nexus` table (id, kind brand|system|affiliation, name, slug, website, about, parent_id, status) and
  `entities.nexus_id` + `entities.nexus_role`; management rows mirror `listing_access` at the Nexus level.
- Crawler: "Found in this crawl" offers "These N are one organization: create the Nexus" when several locations come
  from one site; the Review page groups them.
- Search (`decisions/0053`): grouping rule above; brand recognition joins place / category / condition chips.
- Business Portal: a Nexus view for multi-location owners.
- To build after messaging step 7 and alongside the search rework; this ADR is accepted first.
- Open: how far to seed systems from public data (VA facilities, state agencies) up front, versus as crawls find
  them; whether the Nexus page gets its own feed.
