# Automation operations: the crawler, the website's workflows and the services they use (2026-11-14)

For Jason's review, before any build. Scope (Jason, 2026-11-14): oversee **all of our automation**, not just the Railway
crawler: every workflow that gathers or creates content (the crawler's and the website's own), every task in them, every
outside service they call, and the spend, caps and on/off state of each. The aim: bring usage, expense, caps and what's
active or inactive together in one place, **without new gaps or more manual work**. How it runs today is in the
reference at the end.

## 1. Where we stand (2026-11-14, ~16:00 UTC)

- **Everything is being paused for this review** (Jason). The Railway crawler is off (its schedule removed;
  `handoffs/crawler-revamp.md`, top). The website's workflows are paused by the switches release now being built
  (§4), which ships with the master switch off.
- **Live today, kept:** the check before every outside call (`ServiceHealth`): a ban, a refused key or a used-up quota
  holds that server, a busy one backs off, a held one returns by itself when its status check answers normally (half
  budget for 14 days); server lists as settings; a pace per service; OpenStreetMap's daily budget (2,500 queries /
  250 MB, crawler 1,500, site 1,000) and 3-month cache (releases #43, #44, #46; worker 2.6).
- **Anthropic (Claude), set today:** auto-reload back to $20 at $5; the site's AI cap $40 a month; Anthropic's monthly limit
  $50; Console email alerts at $20 and $35. No Admin API key (an individual Console account can't make one;
  `future-considerations.md`, "The Claude Console as an organization").

## 2. The layers

| Layer | What it is | Examples |
|---|---|---|
| Phase (decisions/0133) | The stage knowledge goes through | 1 sources, 2 extraction, 3 vetting, 4 catalog, 5 publishing, 6 maintenance |
| Workflow (0160, W1–W13) | A thing we make, start to finish | W1 listings, W4 place guides, W6 heritage, W10 live events |
| Task | One job inside a workflow | `coverage` (find businesses in a town), `refresh`, the guide job, journeys |
| Route | A task's use of a service | `coverage` → OpenStreetMap; `places` → Google Places; menus → Google Document AI, then AWS, then Claude (`AiClient::ROUTES`) |
| Service | The outside provider at the end of a route | OpenStreetMap (each server), Google Places, Tavily, Claude, SES, Census, Library of Congress; proposed 2026-11-14: Gemini (free tier), Overture and Foursquare's open places data, state licence registries (`knowledge-engine-architecture-2026-11-14.md` §1.5) |

Where they run: the **Railway crawler** runs the crawler's tasks every 5 minutes (when on); the **website** runs its own
workflow jobs on its schedule (Find it and guide drafts, guides on demand, journeys, heritage, the listings pipeline,
landmark photos) and some on demand when a visitor asks (a first-look guide, a place description, OpenStreetMap near a
search).

## 3. Gaps today

| Gap | What it costs us |
|---|---|
| **No way to stop or start the automation as a whole** | Pausing took removing Railway's schedule by hand and a list of admin steps; nothing says "paused, by whom, why" |
| **Caps live in three places** (Railway settings, the site's `.env`, Thresholds & gates) and count only our own use | A cap changed in one place and missed in another; the provider can stop us before our counter says so (the Anthropic "almost out" warning: $3.47 left) |
| **Costs sit in separate ledgers** (crawler calls, Claude, email) | No total per service, workflow or month; nothing checks spend against revenue or the community budget ("no income, no expense") |
| **A service can be off or paced to nothing silently** | Web search and Google Places sat at 0 a run with no reason logged (the day's share spent early) |
| **A run can be "ok" and produce nothing** | OpenStreetMap found nothing from 2026-11-13 while every run reported ok |
| **The schedule and the website's workflows are invisible** | Each task's frequency, share and last outcome are only in the database; the website's workflows run outside the crawler's plan and report |
| **Warnings come after the failure** | A Pulse case opens when a service breaks, never before a cap |
| **Watching depends on a Claude session** | Railway deploys, stuck syncs and sessions are checked only because the session manager does it hourly |

## 4. What the new version does for us

One operations view on the layers, where **the system counts, checks and switches**, and a person only decides what the
system can't.

**Switches at every layer (being built now).**
- **A master switch** (System Configuration): off stops everything that gathers or creates content, the Railway crawler's
  runs included (its plan says "paused", so a run does nothing even if its schedule returns), the website's workflow jobs,
  the AI features serving them, and their outside calls. It never stops orders, billing, payments, email, releases,
  staging, Moments, members' own actions, guide weather and roads, or the admin.
- **A switch per workflow** (W1–W13, the website's included), **per task**, and **per service** (each OpenStreetMap
  server too).
- **The rule:** a call happens only when the master, its workflow, its task and its service are all on. Each switch shows
  who set it, when and why. Paused is "paused", never a failure and never a warning. Visitors get our own listings, never
  a dead end.

**One register of services.** Every outside service is one row:
- the workflows, tasks and routes that use it, and where its key lives;
- its cap and its pace, in one place (the one setting the code reads; Railway's and `.env`'s copies retired, nothing
  retyped);
- this month's use and cost by our count, and by the provider's where it has a usage feed (else "our count only");
- its status with the reason: **active**, **paced**, **held**, **paused** (by whom), **off** (no key), **out of budget**;
- what it produced and the cost per result.

**One board of workflows (W1–W13), the website's own included:** state (on, paused), queue, last run, outcome, output,
cost, and the phase where work waits.

**The task schedule, visible and editable:** each task's frequency, share of a run, limits, last run and result.

**Costs together:** the crawler's ledger, Claude's and email's, per service, workflow and month, set against revenue and
the community budget.

**Warnings before limits:** at **50%, 75% and 95%** of any cap (Jason), one Pulse case per service with the fix as a
button (raise the cap, top up, pause).

**"Returns empty":** a task that had work and got answers but nothing came back (not "nothing to do", not "held"). A
frequent task after 12 empty runs in 24 hours, a monthly load on its first. **One Pulse case, updated every hour**,
listing every service and task that returned empty, a step each (Check it now, Pause the task); a step clears itself when
the task finds something again.

**Watching built into the site:** the site checks Railway's deploys and the sync itself and raises a Pulse case when one
fails; the session manager's hourly check then covers sessions only.

## 5. Less manual work, not more

| Today, a person… | With the new version… |
|---|---|
| removes Railway's schedule and works through admin pages to pause | one master switch, or one per workflow, task or service |
| reads logs to learn why a service made 0 calls | the register shows the reason on the service's row |
| compares the Console with the admin to check the AI spend | the register shows both where the provider has a usage feed |
| edits a cap in Railway, `.env` or Thresholds and hopes the others match | one setting; the old places follow it |
| notices that a task found nothing | the "returns empty" case, hourly, with the step to take |
| re-adds a server after a ban lifts | it returns by itself (live since 2026-11-14) |
| learns a cap was hit when work stops | warnings at 50, 75 and 95%, with the fix as a button |
| relies on a Claude session to watch deploys and syncs | the site watches them |

What a person still does (Jason, 2026-11-14: nothing else by hand): approve releases; switch things on or off; decide on a
cap, a top-up or a pause when warned; contact a provider about a ban (Pulse gives the issue and evidence).

## 6. How we avoid new gaps

- **Replace, never add beside:** each cap, count, status and switch lives in one place and the old places read from it.
- **Built from what exists:** `ServiceHealth`, `crawl_costs`, `ai_jobs`, `CrawlService` (its workflow and task switches),
  `AiClient` (its features and routes), `Scheduler`, Outside services, Thresholds & gates. No new ledgers.
- **Every path through the same check:** the website's own calls pass the same switches, budgets and holds as the
  crawler's.
- **Silence is a status:** nothing can be quietly off; paused says who paused it.
- **No new spend:** usage feeds and status checks are free.
- **One case per kind of chore,** steps as buttons (Pulse's rules), so warnings don't become a pile.

## 7. How it will be built

Each stage a release Jason approves; one session per stage, from its handoff (`decisions/0163`, proposed).

1. **Live:** the check before every call, holds and automatic return, server lists as settings, pacing (2026-11-14).
2. **Being built:** the switches (master, workflow, task, service), shipped with the master off
   (`handoffs/automation-switches.md`).
3. **After Jason's review of this document and 0163:**
   1. the crawler's open items (the four approved releases W6, W9, W10/W11, W12; junk listing names; the map-tile check);
   2. the service register (caps and paces into one place; the website's other callers onto the check);
   3. costs together;
   4. the workflow board and the task schedule;
   5. warnings (50/75/95%) and "returns empty";
   6. watching built into the site.
4. **Resume:** Jason turns the master switch on (or one workflow at a time); the Railway crawler's schedule goes back on.

## 8. For Jason

- Mark anything wrong or missing here or in `decisions/0163`; both are updated before anything in §7.3 is built.

---

## Reference: how the crawler runs today, in three layers

From the live code (`workers/crawler/crawler.py`, worker 2.5 on Railway; `app/lib/crawler/CrawlService.php`) and the
decisions. Each task's schedule is a setting in the site's database (`crawl_capture_tasks`, `crawl_workflows`).

### A. Phases (decisions/0133)

Phases 1 and 2 are always-on automation within fixed guardrails; configuration starts at Phase 3.

| Phase | What it does |
|---|---|
| 1. Source building (the index) | Find and catalog every repository of real information in our areas, on any subject: places' own sites; city, county, state, tribal and federal sites; school, health, transit; registries. Each source gets a dossier entry (what it covers, who made it, how credible, its terms). Public pages only; robots.txt and Crawl-delay obeyed; restricted sources never read. Every page read is logged (decisions/0157 §1) |
| 2. Extraction (reading pages) | Turn pages and files into facts, each keeping its page and date: code first (structured data, registries, tables: about 80%, free), boilerplate stripped, AI (Haiku) only for the narrative remainder. List pages split into their items; each item placed and linked to what we hold (0157 §2) |
| 3. Vetting and configuration | Cross-check against official registries and our own index; identity and duplicates; privacy and the protected-information tiers. Configuration lives here: which areas and themes are vetted and cataloged first |
| 4. The catalog (research) | Every vetted fact in our own record with its source: listings, landmarks, outdoor places, the Human Record, the Living Landscape, footprints, dossiers and research briefs, and the Topic index (our encyclopedia). Gaps in the catalog become research targets for the crawler |
| 5. Publishing | Writing from the catalog (guides, journeys, stories, events), behind rule-based gates and the protected-information standard ("tell the story, never the way in"); freshness shown |
| 6. Content maintenance | A loop back through Phases 1–3 that keeps what's published true: clocks (listings monthly to quarterly, never over 90 days; guides 30 days; dossiers 6 months; research briefs a year), events that start a check (a source changes, a listing closes); outcomes Refresh, Renew or Review |

### B. Workflows (decisions/0160 §3)

Each workflow has its own queue and status, its own share of the crawler, its own report (in the admin, and Pulse when it
stalls), its own costs, and its decision named.

| Workflow | Started by | Phases | Crawler services it asks for | Worker tasks | Gate | Makes |
|---|---|---|---|---|---|---|
| W1 Listings | coverage gaps, towns searched (0149), situation gaps, search targets, Send the crawler, places named on pages, the monthly NPI/IRS/Recreation.gov files | 1–4 | ask open data, fetch, read a page, read a site, search the web | `coverage`, `second_look`, `targets`, `npi_bulk`, `irs_bulk` | the listing score (0044, 0076); one source can establish a listing, a government page by itself, vetted continuously | public listings (`unclaimed`) with their sources |
| W2 Keeping listings current | time (refresh by score, Google every 90 days, map points while missing), a source changing | 6 (through 1–3) | fetch, read a page, ask open data (Census), search the web, Google Places | `refresh`, `identity`, `geocode`, `places` | scoring decides; one site's "closed" is a signal, not a verdict | current details, map points, "closed?" items for Review |
| W3 Source register and place store | sites linked from pages we read, sites a second look accepted, Common Crawl's local public sites | 1–2 | read a site, read a page, read the hard way, search the web (terms) | `source`, `commoncrawl` | the register's call and each site's use rule (licence) | the source register; the place store ("Named on pages we read", staged into W1) |
| W4 Place guides | the guide job (busiest unwritten places first), Write a draft | 4–5 | ask open data (Library of Congress, Wikidata); Claude's own searches | (the site's guide job) | the confidence score; protected-place levels | public guides; journeys follow (W7) |
| W5 Guides on demand | a visitor searching a place with no guide (0147) | 4–5 | ask open data; queues the town for W1 | (the site) | half the daily AI allowance | a first-look guide, labelled |
| W6 Heritage catalog and topics | a place with a guide, hourly | 2–4 | ask open data (Library of Congress) | `heritage` | vetting (sources counted) and the protected tiers | the heritage catalog (Seeker), the Topic index, facts for guides |
| W7 Suggested journeys | a live guide, hourly, 3 a run (0146) | 5 | none (openrouteservice for travel times) | (the site) | rules | suggested journeys on the guide |
| W8 Find it | a name in a guide or story that we don't hold, every 15 minutes | 3–4 | ask open data, fetch, read a page, search the web | (the site, through the crawler's request queue) | the listing score (W1's gate) | listings, and linked names |
| W9 Landmarks and places on demand | the monthly landmark file; a place someone opens or searches that we don't hold (0148) | 1–4 | ask open data | `landmarks_bulk`, `rec_bulk` | 0066: `Landmarks::visibleSql()`; pins on a nation's land move to a public place | landmarks and places, with descriptions |
| W10 Live events | a live guide, while the Live events template runs | 1–5 | read a site (event pages), search the web | (the site's guide job) | official sources first; the allowance | upcoming events on the guide |
| W11 How-to guides | a need with official guidance (0063 §8), 2 a run | 1–5 | search the web, fetch, read a page | `howto` | official pages only | how-to guides |
| W12 Search learning | what people search and open after | 1, 4 | search the web (Tier 2); queues W1 | `learn` | 3 or more searches in 30 days | learned words, situation gaps, new sources |
| W13 Stories | a person writing (0043) | 5 | none; named places go to Find it (W8) | none | person-written only | stories |

### C. Services

#### C.1 What the crawler can do (decisions/0160 §2)

The crawler is one service: it fetches and reads, politely and within budgets, for whichever workflow asks. It makes
nothing on its own and decides nothing about what's published.

| Service | What it does |
|---|---|
| 1. Fetch | One page; robots.txt and Crawl-delay honoured, 1.5 s apart; private addresses refused; 401/403/451 never tried another way |
| 2. Read a page | Its structured data, then its text: places with name, address, phone, hours, website; the kind of page; its town |
| 3. Read a site | Its sitemap and own links, the page log (an unchanged page answers 304), links to other sites for the register |
| 4. Ask open data | OpenStreetMap, Wikidata, NPI, IRS, Recreation.gov, Library of Congress, Census, Common Crawl |
| 5. Search the web (paid) | Tavily or Brave, only within the allowance |
| 6. Read the hard way (paid) | Tavily Extract for a page it can't fetch; Textract for a scanned PDF |

It keeps two shared memories every workflow uses: the **source register** (which sites, read or cite only, their terms and
trust) and the **place store** (every place named on a page we read, with its page).

#### C.2 One run, every 5 minutes

Railway starts the worker every 5 minutes. A run lasts at most about 4 minutes (`TIME_BUDGET` 240 s) and 60 page reads
(`MAX_FETCHES`).

1. **Ask the site for the plan** (`CrawlService::plan`): the tasks due (paused workflows and switched-off tasks left out),
   coverage first, then whichever task waited longest, each with its share of the run's time, page reads and paid calls.
   Bulk loads only at night (1–5 AM Arizona time), one a night, after the rest.
2. **Check outside services:** held servers (`ServiceHealth`: a ban, a refused key, a quota, or backing off), status
   checks due, the day's OpenStreetMap budget, and the towns and kinds asked recently (the cache). A held server is never
   called.
3. **Work each task inside its share.** A task starts only with at least 15 s left; unfinished work goes back unchanged:
   "not reached", never a failure.
4. **Report** each task: done, nothing to do, not reached, failed, off (no key or switch), or held. Paid calls go in the
   cost ledger, failed ones included.

#### C.3 The worker's tasks

| Task | Workflow | What it does | Outside services it calls |
|---|---|---|---|
| `coverage` | W1 | Businesses of one kind in one town cluster ("Gallup × Health care") | OpenStreetMap (1 query per job), Wikidata, then each place's website |
| `second_look` | W1 | Checks listings with only one source | NPI registry; web search, at most 2 per listing |
| `targets` | W1 | Searches people or staff queued ("cafés in Kayenta"); finds held for a person | OpenStreetMap, then the places' websites |
| `refresh` | W2 | Rechecks live listings' websites for changes | businesses' websites |
| `identity` | W2 | Confirms who a listing is | NPI registry, Census, web search |
| `geocode` | W2 | Map points from street addresses (never confidential ones) | U.S. Census geocoder |
| `places` | W2 | Checks listings against Google (same place, closed or not; keeps only Google's ID) | Google Places |
| `source` | W3 | Reads one "read regularly" source, only pages that changed | that source's site |
| `commoncrawl` | W3 | Finds our states' local public sites (az.us, nm.us) for the register | Common Crawl index |
| `heritage` | W6 | Catalog records (never the items) for each guide's place | Library of Congress |
| `howto` | W11 | Official pages for how-to guides | web search, then those pages |
| `learn` | W12 | Searches gaps open data couldn't fill; queues W1 | web search |
| `npi_bulk` | W1 | Monthly NPI file (health-care organizations in our states) | CMS download, at night |
| `irs_bulk` | W1 | Monthly IRS nonprofit list (confirms listings only) | IRS download, at night |
| `rec_bulk` | W9 | Monthly Recreation.gov places | Recreation.gov, at night |
| `landmarks_bulk` | W9 | Natural landmarks and nations' boundaries | USGS, Census, Wikidata, Wikipedia, Commons, at night |

The website (Bluehost) runs W4, W5, W7, W8 and W10 itself, and calls some services directly: OpenStreetMap when a visitor's
search finds little nearby and for the deep look on one business, openrouteservice for journeys, and Claude for guides
and research (inside the monthly AI cap).

#### C.4 Outside services and their limits

| Service | Who calls it | Limit |
|---|---|---|
| OpenStreetMap (Overpass) | `coverage`, `targets` (1 query per job); the website (visitor searches, deep look) | 2,500 queries / 250 MB a day together (crawler 1,500, site 1,000); half while a ban is open and two weeks after; each town and kind cached 3 months; one query at a time |
| Google Places | `places` | ~30 a day (the site paces the monthly allowance) |
| Web search (Tavily) | `second_look`, `identity`, `howto`, `learn` | the monthly allowance, paced over the days left; at most 10 a run |
| Claude | the website (guides, research, Find it, events) | the monthly AI cap; on-demand guides up to half the daily allowance |
| NPI registry | `second_look`, `identity`; monthly file | free |
| Census geocoder | `geocode` | free; a batch a run when due |
| Library of Congress | `heritage` | free; a few places a run |
| Common Crawl | `commoncrawl` | free; when due |
| Businesses' and sources' own sites | `coverage`, `targets`, `refresh`, `source`, `howto` | 60 page reads a run shared; robots.txt; 1.5 s apart |

At 5 queries a run × 288 runs a day, OpenStreetMap stays near one query a minute, inside the crawler's 1,500.

#### C.5 The check before every call (live 2026-11-14)

Every call to an outside service passes `ServiceHealth` first:
- **A ban, a refused key or a used-up quota** holds that server; nothing is sent to it. A Pulse case names the exact issue
  with its evidence, the service's link, "Check it now" and "Mark resolved". Work that doesn't need it carries on.
- **A busy or slow server** (429, timeouts, 5xx) is skipped until its wait is over; the provider's own wait is followed.
- **The hold lifts** when a person marks it resolved, or (being built) when its status check answers normally: it then
  returns by itself at half budget for two weeks.

