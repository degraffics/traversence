# Regional Deployment Map — Ancient America (Pilot Continental Hub)

*Version: 2026-09-22*
*Governance tier: Reference data — the concrete geography behind the pilot described in `decisions/0006-pilot-hub-scope.md`. This is scope/rollout data, not policy; the pattern it's an instance of (Continental Hub → Geo-Hub Zone → Micro-Cluster) is defined once in `architecture.md` §22 and not repeated here.*
*Source: "Ancient America: Regional Hub Architecture & Operational Blueprint."*

## Executive Overview

Ancient America (AA) is the foundational proof-of-concept and pilot Continental Hub — one of 20 planned Continental Hubs platform-wide — covering the Colorado Plateau and Mountain Gateway region across Arizona, New Mexico, Utah, and Colorado. It connects travelers and residents to content and directory listings through a two-tier structure that separates macro-editorial visitor content from hyper-local municipal directories, enforcing the Single Home Rule for business profiles (see `architecture.md` §22) and routing high-intent traffic into noise-free regional directories.

Cluster identifiers use the hierarchical namespace `hub_aa:...` behind the scenes to guarantee zero database collisions across all 20 Continental Hubs — confirming the bottom-up slug pattern documented in `architecture.md` §19.

## Geo-Hub Zones & Micro-Clusters

| Geo-Hub Zone | Macro Span | Micro-Clusters (towns) | Est. Businesses |
|---|---|---|---|
| The Ancient Borderlands & Painted Desert | ~20,000–25,000 sq. mi. | **1A** St. Johns, Springerville, Eagar, Show Low, Holbrook, Winslow, Petrified Forest gateway · **1B** Window Rock, Chinle, Kayenta, Tuba City, Ganado · **1C** Zuni, Hawikuh site, Gallup, Farmington, Bluff | 1A ~600 · 1B ~340 · 1C ~500 |
| Mesa Verde & San Juan Basin | ~12,000–14,000 sq. mi. | **2A** Durango, Cortez, Mancos, Dolores, Ignacio, Silverton | ~450 |
| Rio Grande & Taos/Santa Fe Corridor | ~10,000–12,000 sq. mi. | **3A** Santa Fe, Taos, Española, Los Alamos, Las Vegas (NM), Pecos | ~600 |
| Albuquerque Metropolitan Core | ~3,000–5,000 sq. mi. | **4A** Albuquerque proper, Old Town, Nob Hill · **4B** Rio Rancho, Corrales, Bernalillo, West Mesa · **4C** Tijeras, Edgewood, Los Lunas, Belen | 4A ~750 · 4B ~500 · 4C ~450 |
| Phoenix Metropolitan Core | ~3,000–5,000 sq. mi. | **5A** Phoenix proper, downtown · **5B** Scottsdale, Tempe, Mesa, Gilbert · **5C** Glendale, Peoria, Sun City, Goodyear | 5A ~700 · 5B ~800 · 5C ~600 |
| Bradshaw, Prescott & Verde Transition | ~10,000–12,000 sq. mi. | **6A** Prescott, Prescott Valley, Chino Valley, Dewey-Humboldt · **6B** Sedona, Cottonwood, Camp Verde, Jerome | 6A ~600 · 6B ~450 |
| Flagstaff, Grand Canyon & The Colorado Rim | ~14,000–18,000 sq. mi. | **7A** Flagstaff, Williams, Tusayan, Grand Canyon Village · **7B** Page, Lake Powell, Marble Canyon, Lechee, Cameron | 7A ~560 · 7B ~350 |
| The Apache Stronghold & Copper Corridor | ~10,000–12,000 sq. mi. | **8A** Globe, Miami, San Carlos, Peridot · **8B** Safford, Pima, Thatcher, Clifton, Morenci | 8A ~350 · 8B ~450 |
| Southern Desert & Tucson Basin | ~12,000–15,000 sq. mi. | **9A** Tucson proper, Oro Valley, Marana, Sahuarita, Green Valley | ~700 |
| Historic Old West & Border Frontier | ~8,000–10,000 sq. mi. | **10A** Tombstone, Huachuca City, Whetstone, Gleeson · **10B** Bisbee, Lowell, Naco, Douglas | 10A ~250 · 10B ~350 |
| Moab & Canyonlands Red-Rock | ~20,000–25,000 sq. mi. | **11A** Moab, Castle Valley, Green River (UT), La Sal · **11B** Bluff, Blanding, Mexican Hat | 11A ~450 · 11B ~300 |
| High Plateaus & Canyon Gateway | ~18,000–22,000 sq. mi. | **12A** Payson, Pine, Strawberry, Young, Tonto Basin · **12B** Torrey, Loa, Bicknell, Hanksville · **12C** Springdale, Kanab, Panguitch, Tropic, Escalante, Boulder | 12A ~350 · 12B ~250 · 12C ~450 |
| Salt Lake Front Core | ~4,000–6,000 sq. mi. | **13A** Salt Lake City proper, Bountiful, Ogden, Layton · **13B** Provo, Orem, Lehi, Park City | 13A ~700 · 13B ~650 |
| Denver Front Range Core | ~5,000–7,000 sq. mi. | **14A** Denver proper, Aurora, Lakewood, Littleton · **14B** Boulder, Longmont, Fort Collins, Loveland · **14C** Colorado Springs, Manitou Springs, Pueblo, Canon City | 14A ~900 · 14B ~700 · 14C ~750 |
| Wasatch, Pioneer & Dinosaur Heartland | ~15,000–18,000 sq. mi. | **15A** Vernal, Roosevelt, Duchesne, Manila | ~300 |

That's roughly 34 micro-clusters and, on these estimates, somewhere in the neighborhood of 13,000–14,000 local businesses across the full Ancient America footprint once every zone is live — useful context for sequencing which clusters launch first rather than a claim that all of them launch together.

**On the site (2026-09-30):** all 15 zones exist as geo-hubs under Ancient America. The Ancient Borderlands is
live; the other 14 are `planned` and show as "Coming soon" on the Ancient America page, in this table's order,
each with a short description of its towns (`api/migrations/2026-10-01_ancient_america_geohubs.sql`). A zone goes
live when its first town cluster is built in Admin → Cluster tools and its status is set to active.

## Thematic Messaging Overlay: Pre-Colonial Heavy vs. Frontier & Industrial Transition Zones

Per direction, 2026-09-24 — a thematic overlay for `commercial.md` §2's Sales & Partner Onboarding Framework and `brand.md` §2's editorial voice, not a replacement for the operational Geo-Hub Zone geography above. It answers a different question than the table above does: not "where is this zone," but "which layer of history should the Authority Engine lean on when writing about it."

**The two buckets, as defined per direction:**

- **Pre-Colonial Heavy Zones:** the landscape is fundamentally defined by deep indigenous heritage, monumental ancient urban structures, and pre-existing trade networks that predate European contact by centuries (ancient agrarian valleys, major historical trade arteries). Editorial focus centers on the Living Geography and the unbroken continuity of human civilization.
- **Frontier & Industrial Transition Zones:** the narrative surface leans toward western expansion, mining booms, historic rail corridors, and industrial or homesteading transformation. These zones still sit on the same older paths, but their *immediate* surface narrative is shaped by later historical waves — resource trails, the traditional "frontier" dynamic.
- **Dual-Layer Zones (True Hybrid) — a third category, refined 2026-09-24, not a forced tiebreak between the two above:** for a zone where neither historical layer meaningfully erases the other — where a pioneer-era canal or settlement corridor literally reuses the same water and footpaths carved out centuries earlier — the classification doesn't pick a side. The editorial approach explicitly narrates the dialogue between the two layers as the actual subject, rather than treating one as ground truth and the other as decoration.

**Refined classification of Ancient America's 15 Geo-Hub Zones, 2026-09-24 — the three closest calls resolved with real verdicts rather than left as flagged ambiguity:**

| Geo-Hub Zone | Bucket | Why |
|---|---|---|
| The Ancient Borderlands & Painted Desert | Pre-Colonial Heavy | Zuni/Hawikuh, Navajo Nation towns (Window Rock, Chinle, Kayenta), Canyon de Chelly and Chaco-adjacent territory (Farmington) — the zone's own name says "Ancient." |
| Mesa Verde & San Juan Basin | Pre-Colonial Heavy | Mesa Verde's cliff dwellings are among the most famous Ancestral Puebloan sites in North America — unambiguous. |
| Rio Grande & Taos/Santa Fe Corridor | Pre-Colonial Heavy | Taos Pueblo (continuously inhabited pre-contact) and Pecos Pueblo anchor this corridor; the Spanish-colonial layer (El Camino Real) sits on top of, not instead of, the older Pueblo trade network. |
| Albuquerque Metropolitan Core | **Pre-Colonial Heavy** (with a modern industrial-transition overlay) | Resolved verdict, 2026-09-24: even though modern infrastructure and Route 66 dominate immediate surface traffic, Albuquerque can't be separated from the continuous Rio Grande Pueblo agricultural and trading network (same corridor as zone 3) that preceded it by a millennium — Petroglyph National Monument, Tijeras Canyon as an ancient inter-pueblo/Plains trade route. Editorial approach: use the Living Geography lens to unmask the modern metro as an ancient river-valley convergence point, not to erase the modern-metro overlay. |
| Phoenix Metropolitan Core | Pre-Colonial Heavy | A genuinely literal case of the Living Geography: modern Phoenix-area canals largely retrace the Hohokam canal system underneath them, 1,000+ years old. |
| Bradshaw, Prescott & Verde Transition | Frontier & Industrial Transition | Jerome (copper boomtown), Prescott (territorial capital) — the zone's own name already says "Transition." |
| Flagstaff, Grand Canyon & The Colorado Rim | **Frontier & Industrial Transition** (anchored by a Pre-Colonial foundation) | Resolved verdict, 2026-09-24: leans into Transition because the modern visitor experience is so heavily shaped by the Santa Fe Railway, Route 66, and westward-expansion mythology — real ancestral/sacred sites (Wupatki, Grand Canyon native communities) sit right alongside that surface narrative rather than being absent. Editorial approach: treat the industrial layer as the top coat, and the Authority Engine's job is to peel it back to reveal the ancient trails and ancestral homelands the railroad and highway were literally paved over. |
| The Apache Stronghold & Copper Corridor | Frontier & Industrial Transition | Worth a precise distinction: "Apache Stronghold" names real indigenous heritage, but the Apache Wars era (1860s–1880s) is post-contact conflict, not pre-colonial by the definition above — paired with "Copper Corridor" (Morenci, Globe/Miami mining), this reads as frontier-conflict-and-mining, not ancient trade. |
| Southern Desert & Tucson Basin | Pre-Colonial Heavy | The Santa Cruz River valley around Tucson has some of the oldest known irrigation agriculture in North America (~4,000 years) — a textbook "ancient agrarian valley" per the definition. |
| Historic Old West & Border Frontier | Frontier & Industrial Transition | Tombstone, Bisbee — the zone's own name says "Historic Old West." |
| Moab & Canyonlands Red-Rock | Pre-Colonial Heavy | Moab's own boom history is 1950s uranium mining, but the broader zone (Bluff, Blanding, Mexican Hat, the Bears Ears/Cedar Mesa area) carries an extremely high density of ancestral Puebloan sites — the zone as a whole outweighs Moab's own frontier-mining thread. |
| High Plateaus & Canyon Gateway | **Dual-Layer Zone (True Hybrid)** — not forced into either bucket | Resolved verdict, 2026-09-24: the clearest literal embodiment of the Living Geography in the whole pilot footprint — Tonto Basin's Salado cliff dwellings and the Utah towns' 19th-century Mormon pioneer-settlement/ranching networks don't erase each other; pioneer irrigation canals and settlement corridors frequently reuse the exact same water and footpaths carved out centuries earlier. Editorial approach: don't pick a side — explicitly narrate the dialogue between the ancient agrarian footprint and the later homesteading era as the subject itself. |
| Salt Lake Front Core | Frontier & Industrial Transition | The paradigmatic homesteading-transformation region of the American West — 1847 Mormon pioneer settlement is the region's own dominant civic narrative. |
| Denver Front Range Core | Frontier & Industrial Transition | Denver was founded by the 1859 Pike's Peak Gold Rush — mining-boom origin story, unambiguous. |
| Wasatch, Pioneer & Dinosaur Heartland | Frontier & Industrial Transition | "Pioneer" is in the zone's own name. |

**Net split, 2026-09-24:** 7 zones Pre-Colonial Heavy (1, 2, 3, 4, 5, 9, 11 — Albuquerque's overlay qualifier doesn't move it out of this bucket), 7 zones Frontier & Industrial Transition (6, 7, 8, 10, 13, 14, 15), 1 zone Dual-Layer/True Hybrid (12, High Plateaus & Canyon Gateway). This closes out the three rows previously flagged as the closest calls — Jason's own verdicts above, not a forced binary read.

## Behavioral Routing & Conversion

- **Local users** (Resident track) are routed straight to Tier 2 Micro-Clusters, locked to their immediate municipal radius — no tourism content in the way.
- **Global visitors** (Traveler track) are led with Tier 1 Macro-Editorial content — historical narratives and routed itineraries — with contextual in-content hand-offs into the local partner directory as they move through a route.
