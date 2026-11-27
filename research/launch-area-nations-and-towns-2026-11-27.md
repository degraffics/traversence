# The launch area: nations' land (E.2) and the plan for its 340 towns (D.1)

Data & Content build session, 2026-11-27. **Research and plan only:** no site file changed, no release sent, nothing
claimed. Built on the live mirror at **Sync 2026-11-27 01:35 UTC** (`traverse-3way-sync` `7a839cb`) and `main` at
`aa81be2` (decisions/0181). Live records read through `live-read.yml` (runs #76 and #77, logs deleted once read):
`jurisdictions` and `nations`, both registers with no personal details.

Governing: decisions/0181 §3, 0066, 0058 §26–27, 0164 R7, R8, R17, 0149's 2026-11-27 note, 0163, 0131, 0147; the
launch plan `research/launch-plan-jan1-2026-11-26.md` (branch `claude/launch-plan-jan1`) §2a, D and E; the rural test
§8.9.

**Method.** The area is every ZIP in the site's `app/data/US.txt` within 200 miles of the Four Corners Monument
(36.999, -109.045): **419 ZIPs, 340 towns**, the same count as 0181 §3. Nations' land is the Census AIANNH 2024 file the
crawler itself reads (`workers/crawler/crawler.py:3006`), intersected with the circle. A ZIP "on a nation's land" means its
middle point lies inside the boundary (so Gallup, Durango and Española appear by one of their ZIPs, not because the town
is the nation's).

---

## Part 1. E.2: every nation's land in the area, against 0066

### 1.1 What's in the circle

| Census area | sq mi in the circle | share inside | ZIP middles on it | In `nations`? | Boundary loaded? |
|---|---:|---:|---:|---|---|
| Navajo Nation (reservation, and its trust land: Ramah, Alamo, Tóhajiilee, the checkerboard) | 24,335 | 100% | 52 | yes | yes |
| Hopi | 2,526 | 100% | 5 | yes (ids 1–22, page 2 not read) | yes |
| **Uintah and Ouray** (UT) | 1,792 | 26% | 0 | **not on page 1; to confirm** | **no** (G1) |
| Jicarilla Apache Nation | 1,371 | 100% | 1 (Dulce) | yes (#23) | yes |
| Southern Ute (CO) | 1,062 | 100% | 5 (Ignacio, Arboles, part of Durango…) | yes (#26) | likely; to confirm (G1) |
| Ute Mountain Ute (CO, NM, UT) | 898 | 100% | 1 (Towaoc) | yes (#25) | yes |
| Pueblo of Laguna | 781 | 100% | 4 | yes (#28) | yes |
| Pueblo of Zuni | 722 | 100% | 1 | yes | yes |
| Pueblo of Acoma | 601 | 100% | 1 | yes (#27) | yes |
| Pueblo of Isleta | 236 | 50% | 2 | yes (#29) | yes |
| Pueblo of Zia, Jemez, Santo Domingo (Kewa), Santa Ana, San Felipe, Cochiti, Santa Clara, San Ildefonso, Sandia, Nambe, Pojoaque, Tesuque, Picuris, Ohkay Owingeh, Taos (48% inside) | 20–190 each | | 0–3 each | yes (#30–44) | yes |
| Kaibab Paiute (AZ) | 86 | 46% | 0 | yes (ids 1–22, to confirm) | yes |
| Havasupai (AZ) | 24 | 9% | 0 | yes (to confirm) | yes |
| Paiute Indian Tribe of Utah | 1 | 2% | 0 | to confirm | no |
| Fort Apache (White Mountain Apache) | under 1 | 0% | 0 | yes | yes |

**84 of the 340 towns** have a ZIP whose middle is on a nation's land: 12 within 50 mi, 25 at 50–100, 25 at 100–150, 22
at 150–200. Every town of 1B (Window Rock, Chinle, Kayenta, Tuba City, Ganado) is on Navajo land; 1C's Zuni is on
Zuni land; 11B's Mexican Hat and Bluff's neighbours Aneth and Montezuma Creek are on Navajo land in Utah.

The `nations` register (live, 47 rows seeded 2026-10-01 from `2026-10-17_nations.sql`, 0058 §26) holds the nations
"whose Census areas touch our regions". **None has its rules checked** (`verified_at`; the rows show no status), so
pages show a pointer to the nation's own visitor information, never rules (`Nations::rules()`), as 0058 §26 says.

### 1.2 How 0066 is applied today, and that it holds

Checked in the live code (`Landmarks.php`, `Nations.php`, `Jurisdictions.php`):
- **Public shows, on a nation's land too** (0066 §3; 0058 §27): `Landmarks::decide()` shows a public feature on a
  nation's land, with the nation's own name (`Nations::label()`).
- **Held, never shown** (0066 §3; 0164 R7): any name or description with a protected word (`PROTECTED`, `PROTECTED_KINDS`;
  archaeological words since 2026-11-14) is `waiting`; on a nation's land a "publicly known" place is never released
  (`publiclyKnown()` returns false when `nation` is set), and the nation's springs are held (`NATION_SENSITIVE_CLASSES`).
  A civic cemetery is protected on a nation's land or when it names a nation's people (`isProtected`, `loadPeoples`).
- **Story only** applies only off a nation's land (0164 R7, P5-3).
- **Pins** on a nation's land move to a public place within half a mile or are dropped (`Nations::pin()`, 0058 §26).
- **Readers** go through `visibleSql()` / `visible()` (`shown`, `approved`); waiting and closed never show.
- **Nation tools** are off (`NATION_TOOLS = false`): no review queue, held items kept unused (0058 §27).

So for the nations whose boundaries are loaded, nothing 0066 holds is shown. The gaps are where the 200-mile area goes
beyond what was built for Arizona and New Mexico.

### 1.3 Gaps (each with its source; none is fixed in this document)

- **G1. Boundaries stop at the Arizona and New Mexico boxes.** The worker loads nations' boundaries only where they touch
  the box of the GNIS features of the states it's given (`crawler.py:3087` `nations_map`, states from
  `/api/crawl/landmarks.php`, which returns `NpiBulk::STATES = ['AZ', 'NM']`). **Uintah and Ouray** (1,792 sq mi inside
  the circle) and the **Paiute Indian Tribe of Utah** are north of that box and aren't loaded; **Southern Ute** sits on
  the line (37.0°) and is loaded only if a New Mexico feature reaches 37.0°. Where no boundary is loaded,
  `Nations::at()` returns null: a pin isn't moved, a landmark isn't marked as the nation's, and a cemetery or spring
  there would be judged as off a nation's land. **Rule this follows:** 0066 §3 and CLAUDE.md "Tribal nations' land".
  **Must be closed before any Colorado or Utah record is loaded.** Confirm on live: `nation_shapes` (a `rows` read).
- **G2. Colorado and Utah have no landmarks yet.** GNIS, NPI, IRS and Recreation.gov loads all take `NpiBulk::STATES`
  (AZ, NM); `OpenLoads::STATES` is the same. Only Geofabrik reads Utah and Colorado (Jason, 2026-11-19,
  `OpenLoads::GEOFABRIK_STATES`). Widening them is what Part 2's Q1 decides.
- **G3. A held place in a cluster's town list.** regions.md lists **"Hawikuh site"** as a 1C town. Hawikuh is an
  ancestral Zuni village site on Zuni land: under 0164 R7 and `Jurisdictions::tier()` (sensitive, on a nation's land)
  it is **restricted**. It must not become a town, a stop or a destination in 1C's guide. *Suggest a change to
  regions.md: drop "Hawikuh site" from 1C's town list (0164 R7).* For Jason via the session manager; I check the live
  `micro_clusters` for it before E.1.
- **G4. The nations' rules are unchecked everywhere.** Not a block (pages point to the nation's own site), but 1A's
  neighbours (Navajo, Hopi, Zuni, White Mountain Apache) are where visitors from 1A go. Filling them is a person's job
  from each nation's own site (0058 §26: "needs an Editor"); nations' sites are never crawled (0058 §26, 0164 R7).
  Gap: no decision names who does it before launch. *Possible solutions:* the Content lead fills the four nearest 1A
  nations first; or launch with pointers only, as today.
- **G5. 84 towns sit on or touch a nation's land.** Building them (D.1) follows 0066 as listings: public business
  listings show (0058 §27); anything protected in a listing's own words is caught at staging by the same scrub (0164
  R6–R7). Pins and maps follow `Nations::pin()`. Nothing new is needed for Arizona and New Mexico.
- **G6. Guides on a nation's land.** A cluster guide for 1B, or one touching Zuni, Acoma, Laguna or the Rio Grande
  Pueblos, names only public places and tells held ones only in general ("the area holds sacred places that aren't for
  visiting without permission, and whose": 0164 R7, Held). The guide writer's guard sends no protected name in a prompt
  (0164 T1, §0.8). E.1 checks each ring's guides against this before a person's read (0181 §1.3).

### 1.4 What the attorney's sign-off (0164 R8, `Jurisdictions::OPENED`) means for each state

**Live today:** `jurisdictions` holds three records, **US, US-AZ and US-NM, all `draft`**, updated 2026-10-30, with no
attorney signature. **Colorado and Utah have no record.** No nation has a record.

What the code does with that (`Jurisdictions.php`):
- `regionOpen()`: a state is open if it's in `OPENED` (AZ, NM: "open before the register", decisions/0048), else only
  when **both** the federal record and the state's are `reviewed`, which for a state needs the attorney's name and
  date (`review()`).
- In a closed state, **guides don't go live (by themselves or by a person) and the crawler doesn't gather history**
  (`regionOpen` comment; 0164 R8: "In a closed region a guide doesn't go live and history isn't gathered").
- `tier()`: a sensitive piece of heritage off a nation's land is `story` only once **every** layer is reviewed, and
  `restricted` until then; on a nation's land it's always `restricted`.

| State | Towns in the area | Open? | What it means for the launch |
|---|---:|---|---|
| **Arizona** | 60 (+ Eagar) | open, by the constant | Listings, landmarks and guides can go live. The heritage catalog's sensitive facts stay **restricted** (the strictest rule) while US and US-AZ are drafts. 0164 R8 wants the attorney's signature on US-AZ; the constant lets the launch run without it, as 0048 intended "while their records are reviewed". |
| **New Mexico** | 126 | open, by the constant | The same as Arizona (US-NM is a draft). |
| **Colorado** | 106 | **closed** (no record) | No guide can go live there, by itself or by a person: **2A** (Durango, Cortez, Mancos, Dolores, Ignacio, Silverton) can't have its micro-cluster guide. The crawler gathers no history. Listings: 0164 R17 says bulk loads run "as regions open (Arizona and New Mexico now)", and "a state's first hub stays Planned until that state's R8 record is signed". |
| **Utah** | 48 | **closed** (no record) | The same: **11A** (Moab), **11B** (Bluff, Blanding, Mexican Hat), **12B** (Torrey…) and **12C** (Kanab…) can't have live guides. |

So **5 of the ~13 micro-cluster guides** in E.1's second ring (2A, 11A, 11B, 12B, 12C), and Colorado's and Utah's
154 towns, depend on the sign-off. 1A, the zone guide and every 1A town guide don't.

**There is a conflict to rule on (0181 §3 against 0164 R17).** 0181 §3 says every town in the area is built ahead of
demand, and left notes on 0149, 0165 and 0180, but none on 0164. R17 still says bulk loads run only as regions open,
and R8 keeps Colorado and Utah closed until an attorney signs. This is a Founder ruling (an ADR amendment, 0180 Tier 5),
put as Q1 below.

**A second, smaller question (Q2).** Landmarks' story-only level (0164 R7, P5-3) names and tells a publicly known
protected place off a nation's land without consulting the jurisdiction register, while R8 says "where a record is
missing or unclear, the strictest known rule applies (restricted until reviewed)", and `Jurisdictions::tier()` does
apply it to the heritage catalog. So in Arizona and New Mexico today, a story-only landmark is told while the same fact
in the catalog is restricted. It shows nothing 0066 holds on a nation's land (story only never applies there), but the
two rules disagree off it.

---

## Part 2. D.1: the plan for the area's 340 towns, 1A first

### 2.1 The area by ring

| Distance | Towns | AZ | NM | CO | UT | ZIP on a nation's land |
|---|---:|---:|---:|---:|---:|---:|
| 1A (St. Johns, Springerville, **Eagar**, Show Low, Holbrook, Winslow, Petrified Forest) | 7 | 7 | | | | 1 (the park's ZIP) |
| 0–50 mi | 24 | 6 | 6 | 8 | 4 | 12 |
| 50–100 mi | 50 | 11 | 14 | 20 | 5 | 25 |
| 100–150 mi | 82 | 16 | 36 | 25 | 5 | 25 |
| 150–200 mi | 184 | 27 | 70 | 53 | 34 | 22 |

(The rings count all 340; 1A's six inside the circle are in its outer two rings.)

**Eagar isn't in the 340.** Its ZIP (85925) measures just past 200 mi. 0181 §3 and the plan's §2a keep all of 1A ("Eagar
sits right on the line… so all of 1A is kept"), so the build list is **the 340 plus Eagar: 341 towns**. 1A's other six
are inside (Show Low, St. Johns, Springerville, Holbrook, Winslow, the park's ZIP for the gateway).

### 2.2 Order (each step sourced)

1. **The W1 trips cleared first** (plan D.1: "Depends on the W1 trips being cleared (#10, #11)"). They're the Piece 2
   session's (stream B.6 and B.7). I don't start a load until it says they're clear.
2. **1A first**, then nearest-out by ring (plan D.1: "1A first, then nearest-out, paced so the server stays inside its
   limits"). Today's order is different: `OpenLoads::due()` takes "the fewest listings we have first" across all of
   Arizona and New Mexico. A ring order for the launch area is a change to that function (a release, R-D1 below).
3. **Free bulk sources first, in the rural test's order** (§8.9; 0181 §3):
   1. **Overture** as the base layer (`overture_bulk`, W1; CDLA-Permissive-2.0, credited);
   2. **NPI by town** for health care, departments folded into their parent, non-public kinds kept as records but not
      shown as places to visit (§8.9's two guards);
   3. **our OpenStreetMap copy** (Geofabrik's state files, decisions/0065; it already covers Utah and Colorado);
   4. **Foursquare's open places** only where Overture doesn't carry the record (`OpenLoads::DATASETS`; it needs a
      Hugging Face account that accepted its terms, §8.9 step 6: Jason's call; whether the site's worker has one is to check);
   5. **Google's paid check on 1A only** (0181 §3), for businesses with no website but a phone (§8.9 step 3: about 100
      calls for three towns, ~$3). For all seven 1A towns, estimated at **~$7–12** from §8.9's rate. That spend is
      allowed by 0181 §3; its budget line is 0163's register.
4. **Every load goes through the one identity check** (`OpenLoads::batch` → `ListingIdentity::fold`, the recheck after
   a load: 0163 §12, 0164 §3.1–3.2), and **nothing publishes unless it passes the floor and readiness** (0164 §3.5, 0076).
5. **Nations' land** under Part 1: for Arizona and New Mexico nothing new; for Colorado and Utah, G1 first.

### 2.3 Pacing

- 1A is about 600 businesses (regions.md); the whole area about 6,100 (plan §2a), "about 10 times 1A's records, into the
  same MySQL that took 1.78M index rows in a day". Each town's Overture box is about 7 km each way (`OpenLoads::BOX`).
- The existing pace stays: W1 load tasks run as scheduled tasks on the switches (`ListingIdentity::ensureTask`: Overture
  3 a run, every 1,440 minutes), and IR-0001's lesson holds (500 rows took 101–130 s against the worker's 120 s read).
- **Proposed pace (a suggestion, not a decision: no decision sets one):** 1A in one day (7 towns); then about 20 towns a
  day, in quiet hours, so the area's 334 others take about 17 days (Dec 1–17, inside the plan's Dec 1–7 for 1A and
  before the Dec 22 freeze). If a load trips or the server's limits show (B.6), the rings stop where they've reached, and
  Jason is told (plan §8, first risk).

### 2.4 The releases this needs (each to the session manager for the order OK first)

- **R-D1 "The launch area's loads"** (after the W1 trips clear):
  - the launch area as one list (US.txt within 200 mi, plus Eagar), 1A first, then by ring: `OpenLoads::due()`, and
    the towns made ours first where they aren't (`OnDemand::townFor`, 0149) so each has a row to load into;
  - Google's check limited to 1A's towns;
  - for Colorado and Utah, only as Q1 rules: G1's boundaries before any of their records, then their states in the
    loads.
  - Tests on MariaDB: the order, 1A first, Eagar in, nothing outside the area, Google's check refused outside 1A, no
    Colorado or Utah record before their boundaries are loaded.
  - **The load's unit today is a community, not a town** (found 2026-11-27, live code): `OpenLoads::due()` walks
    `micro_clusters` and loads a 7 km box around each one's main ZIP (`place_key` `cluster:<id>`). A town made on demand
    is an `anchors` row (`town:<id>`, `OnDemand::townFor`, 0149), and `TownCrawl` works on anchors. So a launch-area town
    that isn't a community's main ZIP never gets an Overture or Foursquare load. R-D1 makes the launch area's towns the
    unit (each made an anchor first, `town:<id>` as the key). That carries out 0181 §3 ("every town… built") with the
    loads 0164 §1.5 already names ("by town box"). A box already loaded under a `cluster:` key is read again
    once under its town's key; the one identity check drops what's already listed (`OpenLoads::batch`,
    `ListingIdentity::fold`), so nothing is listed twice, at the cost of one read.
  - **The trip state (live read, 2026-11-27 02:04 UTC):** W1 is paused whole by the system since Nov 21 after
    foursquare_bulk crashed (run #2148); overture_bulk crashed too (run #1290), and five W1 tasks were left off by a
    closed trip (coverage, the second look, targets, the NPI file, the IRS file), to be switched on by a person (R98).
    The session manager has widened Piece 2's "W1 load trips" release to cover both crashes, #10, #11, W2's hung identity
    recheck if it shares the cause, and one press for Jason to switch the five on. Nothing loads until that release is
    live and the five are on.
- **D.2** after 1A's first loads: clear 1A's staged backlog and Review (the plan counts 31,057 staged, 700 ready but
  stuck, 2,107 with no kind), then the identity check on 1A's duplicates (`handoffs/duplicates.md`; 0164 §3.2; 0163
  §12). I'll read Insights → Data cleanup and Listing Intake for 1A through the live read first.
- **E.1** (guides in rings) starts with the zone guide (geo_hub level, `Guides::TIERS`) once 1A has its data, inside the
  AI cap (0147: on-demand up to 50% of the daily allowance; 0163 §7's $40 a month).

---

## Questions for Jason (Founder rulings; reviewed by the session manager, 2026-11-27; delivered by the CEO in Brief C, 0180)

**Q1. Decision (nothing changes on the site yet): the launch area's four states and R8's sign-off.** 0164 R8 and R17
open a region only after its jurisdiction record is signed. **No state is signed today:** Arizona and New Mexico are
open only by the `Jurisdictions::OPENED` constant (0048) while their records are drafts, and Colorado and Utah have
no record, so they're closed (no guide live, no history gathered). 0181 §3 builds every town in the area and didn't
address this. Options (Colorado's and Utah's 154 towns turn on it; Arizona and New Mexico keep running on the constant
in A and C):
- **A (recommended):** load Colorado's and Utah's public business listings now, as 0181 §3 says (business records
  aren't heritage), after G1's boundaries; their guides (2A, 11A, 11B, 12B, 12C) and history wait for the attorney's
  sign-off on US, US-CO and US-UT. A dated note on 0164 R17 records it.
- **B:** the attorney signs US, US-CO and US-UT (and US-AZ, US-NM) by about Dec 10, and everything goes ahead.
- **C:** Colorado and Utah after launch; the launch area is Arizona's and New Mexico's 187 towns.

**Q2. Decision: story-only landmarks while a state's record is a draft.** P5-3 (0164 R7) tells them; R8's strictest
rule would restrict them. Keep P5-3 as it is in Arizona and New Mexico (it never applies on a nation's land), or hold
story-only places until the state's record is signed?

**Suggest a change to regions.md (G3):** drop "Hawikuh site" from 1C's towns: it's a restricted place under 0164 R7.

**G4, a gap:** who checks the four nearest nations' visitor rules for 1A before launch, or launch with pointers only.

Data used: U.S. Census Bureau TIGER/Line 2024 AIANNH (public domain); the site's `app/data/US.txt` (GeoNames, CC BY
4.0); live `jurisdictions` and `nations` registers.
