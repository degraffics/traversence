# The Knowledge Engine: gaps and dead ends review (2026-11-14)

Jason, 2026-11-14 (via the session manager): "I would like a review of the knowledge engine to identify anything that
causes a gap or dead end for processing information."

**Review only.** Nothing in the decisions, the site code or the releases was changed. What was read: `decisions/0164`
(the accepted record: start to finish, R1–R19, Phases 1–6, W1–W14, §7's new parts, §12, §14), `decisions/0163`
(§1–§12), `decisions/0160` (the workflows and their gaps), `decisions/0157` (the index and the page log), and
`research/rural-test-2026-11-14.md`. Where 0164 or 0163 describe something already built, the live code was checked in
`degraffics/traverse-3way-sync` at **"Sync: 2026-11-14 17:59 UTC"** (`a330548`): `sync-app/` = `app/`,
`sync-storage/` = `website/`. File references below are to `app/` and `website/` paths.

Severity: **blocks** (information stops for good, or a rule is broken), **degrades** (it gets through late, partly or by
luck), **cosmetic** (wording or where a fact lives).

---

## Executive summary

**29 findings: 8 block, 19 degrade, 2 cosmetic.** Plus a recommendation for each of the five items the approvals session
flagged (§F).

The engine is sound in shape: every piece of information has a phase to go to, and the "confidence, not restriction"
change removed most of the old places where finds got stuck. The gaps fall into five groups:

1. **Two gates that don't match (blocks).** The "real business" floor lets a find through with an address **or** a phone
   (§3.5), but readiness asks for a phone or website **and** a street number or map point (§5.4). A licence record (a name
   and an address, no phone) or a phone-only find passes the floor and then fails readiness. It ends up neither a
   sighting nor a listing, and nobody is asked about it. The live gate is stricter still: street, town, state, ZIP and a
   geocoded point (`AutoImport::evaluate`). Rural addresses (PO boxes, highway mile markers, the Navajo Nation's few
   street addresses: Kayenta) are the ones that fail. **P3-1.**
2. **Review items nobody is told about (blocks).** Pulse leaves out every suggestion the crawler or the system raised
   (`Pulse.php`, the `sugg:` case skips `crawler-%` and `system:%`). So "Possibly closed", "Moved?", a crawl that
   disagrees with a person (R4's "check this?"), finds that ran out of options, the grey zone's quarantine and held
   emails all sit in Review with no prompt. 0164 sends every exception to "a person", but only visitors' own reports
   reach one. **X3.**
3. **"Still open?" and the public "Closed" count aren't built (blocks),** and as written they have no way out. A
   listing with a closure signal waits for three devices to answer. A rural listing few people open never gets three,
   so it stays in caution for ever, and visitors may drive to a business that has shut. Our team (a call or a visit) is
   also missing from the list of who can say Closed, though the Call list is "also a check". **P5-1, P5-2.**
4. **Gemini and private data (blocks).** Three paths can put a person's words or a private detail in front of
   Gemini's free tier: a visitor's search words in W12's grounded search, names from a member's story sent to Find it
   (W13 → W8), and public pages naming private people (obituaries, court notices, census rows, a sole proprietor's home
   address in a licence record), which reach Phase 2's AI reader before Phase 3 removes private details. A held sacred
   name can reach a grounded-search prompt the same way. **P2-1, P2-2, T1.**
5. **Work that can't wait where 0164 says it waits (blocks).** When Gemini's allowance is used, "the work waits". But
   we never keep a page's body (Decision 1), and the page log keeps its entity tag at fetch. The next visit gets a 304,
   "unchanged, costs nothing", so the page is never read into facts. **P1-1.** The clocks also keep running while the
   switches are off: after 14 paused days every staged find counts as "out of options" and goes to Review untried
   (`AutoImport::exhausted`). **X1.**

**Do first** (before the build stages switch anything on): P3-1 (one rule for "complete"), X3 (one Pulse case for the
engine's Review items), P2-1/P2-2/T1 (a coded guard on what may reach Gemini), P1-1 (a "waiting to be read" state),
and X1/X2 (clocks that stop while paused; the switch-on check confirming W1 runs 0164's gate, not 0133's).

---

## How each kind of information moves, and where it stops

| Information | Path (0164) | Where it can stop | Findings |
|---|---|---|---|
| A URL lead | §1.1 frontier → §1.2 → §1.3 | a page fetched but not read (Gemini held) is never read again (304); a source that blocks us loops at high priority | P1-1, P1-2 |
| A page read | §1.3 → §1.4 → Phase 2 | private details reach Gemini before Phase 3 removes them | P2-1 |
| A licence or registry record | W14 staged → hub opens → §3.5 (establishes alone) | address-only fails readiness; vendor-hosted official lookups dropped; already-open hubs never "open" | P3-1, H2, H3 |
| An open-data record (Overture, Foursquare) | §1.5 → §3.5 floor → §5.4 | same readiness mismatch; a licence change upstream doesn't reach stored facts | P3-1, P4-2 |
| A Gemini lead | §1.5 → the cited page read by our crawler | the cited page is one we may not crawl (Facebook, a directory): the lead never resolves or expires | P1-4 |
| A heritage source | W6 → §2.3 → §3.2 → R7/R8 | an unsigned jurisdiction record holds the story back and nobody is asked; census rows and obituaries to Gemini | H4, P2-1 |
| A guide or story naming a place (Find it) | W8 | a member's story names go to grounded search; a held name can reach an AI prompt | P2-2, T1 |
| A person's edit | R4 → "check this?" | the crawler's "check this?" has no Pulse prompt; an owner isn't told of an offered change | X3, P3-5 |
| An owner's claim | R12 → claim band | any crawler item against it drops it out of the claim band | P3-4 |
| A visitor's "Still open?" and the public "Closed" count | R12, R10 | not built; no time limit; one person can count as three devices; our team isn't a closer | P5-1, P5-2, F2 |
| A search that found nothing (W12) | §4.5 → the frontier | only places reach the crawler; a topic or kind with no results stops in the term log | P1-3 |
| A new state's authority sources (W14) to a hub opening | §7.1 → R17 → 0163 §10 | its trigger reads a document the site can't see; no prompt for an unsigned record, a met target or a nation's call | H1, H4, H5 |
| A protected or tribal-nation place | R7, 0066 | a Wikipedia article flips a held name to fully public, with a pin; no "story only" for landmarks | P5-3 |
| Private data | §0.8, R5 | three routes to Gemini; a director can move any feature to Gemini | P2-1, P2-2, P2-3 |

---

## Across the phases: switches, budgets and people

### X1. The clocks keep running while the automation is paused · degrades

- **Where:** 0163 §9–§10; `app/lib/crawler/AutoImport.php` `exhausted()`; 0164 §3.2 (30-day quarantine), R12 (never more
  than 90 days), §1.3 (the two GONE clocks).
- **What gets stuck or lost:** `exhausted()` treats a staged find as out of options when it is 14 days old with no deep
  look started. With the master switch paused since 2026-11-14, nothing gets a look, so every staged find "runs out"
  and goes to Review untried. The 30-day quarantine, the 90-day listing check and the 30-day page re-check all fall due
  together while paused. At switch-on, W1 meets a Review queue of finds that were never tried and every listing overdue
  at once.
- **Why it matters:** "ready to resume" (0163 §10) means the queue picks up where it stopped, not that people get the
  backlog by hand.
- **Fix:** every clock in the engine counts only time its workflow was on (store paused time per workflow from
  `automation_log`, or move `due_at` on by the paused time at switch-on). The switch-on check shows the backlog for each
  workflow and its order (most-viewed or most-asked first), drained at the ramp's half budget.

### X2. The switch-on check doesn't check that W1 runs 0164's gate · degrades

- **Where:** 0163 §10, 1; `AutoImport::evaluate()`, `AutoImport::TRUSTED_REGISTRIES` (`['npi' => …]` only);
  `app/lib/Thresholds.php` (`listing_one_source` = 50, `guide_find_add` = 80 as gates).
- **What gets stuck or lost:** the live gate is still 0133/0160's. An NPI file record "needs its own website or one more
  reference" (C3 says it establishes alone), and the IRS file and licences aren't trusted registries (C2, C4). One source
  publishes only above a score of 50, and Find it adds only at 80, though 0164 now says scores only order the work
  (R10, §5.4). The check lists "the crawler's open fixes that must come first" but not this. Switching W1 on (first, per
  §10, 2) runs the old rules.
- **Fix:** add a line to the switch-on check: "W1 and W8 gate on the floor and readiness (0164 §3.5, §5.4)". Stage 8
  of 0163 §8 carries the change to `evaluate()` and retires `listing_one_source` and `guide_find_add` as gates (they stay
  as ordering only).

### X3. The engine's Review items have no Pulse prompt · blocks

- **Where:** `app/lib/Pulse.php` (the `sugg:` case: `AND NOT (s.device_hash LIKE 'crawler-%' OR s.device_hash LIKE
  'system:%')`, the same as `Suggestions::OWN_SQL`); `app/lib/crawler/Refresh.php` `ask()` (raises `crawler-refresh`
  suggestions); 0164 R4, §2.2, §3.2 step 5, §5.4 ("a person only for exceptions"), R12 ("Moved or wrong address?").
- **What gets stuck or lost:** these are raised for a person, and none of them opens a Pulse case:
  - "Possibly closed" from a listing's own site (when it's claimed, "It has an owner, so a person decides");
  - R4's "check this?" when a crawl disagrees with what a person entered;
  - "Moved or wrong address?" from Google;
  - Listing Intake's out-of-options finds (`AutoImport::reason()`: incomplete, dupe, category, moved, NPI only, one
    source, outside our areas), including search targets, which are always held (`CrawlTargets::complete`);
  - the grey zone's quarantine (§3.2);
  - a random email held for Review (Decision 2);
  - the place store's sites waiting for a person (a nation's sites, R7).

  Only visitors' own reports, claims and failed crawls reach Pulse.
- **Why it matters:** Pulse is how a person is asked (CLAUDE.md: one case per kind of chore, a step per item, steps as
  buttons). Without it, R4's protection becomes a silent queue.
- **Fix:** one Pulse case, "The engine needs a person", with a step per kind (each a button that does it: Confirm
  closed, Keep open, Merge, List anyway, Choose category, Clear email), grouped as `AutoImport::reason()` already
  groups them. Ordered by value (R12: "Review ordered by value"). Claimed listings' items go first, and to the owner too
  (P3-5).

### X4. The AI monthly cap has no shares between workflows · degrades

- **Where:** 0163 §7 (`AI_MONTHLY_CAP_USD` $40; W5 up to half the daily allowance); 0164 R15 ("an ask we can't answer
  is researched … at our cost"), R19, §2.3 (a person's pages to Claude), W4, W6, W8.
- **What gets stuck or lost:** W4's renewals, W6's readings, W8's Find it, Phase 2's reads of a person's pages, AI
  Assist's follow-up research and W5 all draw on one cap. Only W5 has a share. The crawler's run has per-workflow shares
  (`CrawlService::plan`); the AI cap doesn't. A busy month for one starves the others, and nothing says which waits.
- **Fix:** shares of the AI cap per workflow in 0163 §7, set like the crawler's, with unused share passing on at
  month's end. The 50/75/95% warnings (§4) apply per share.

### X5. Two homes for AI Assist's allowances · cosmetic

- **Where:** 0163 §7 (moved to AI use, §14 point 10); live `app/lib/Thresholds.php` still holds `ai_free_asks` and
  `ai_cover`.
- **Fix:** move them with stage 2 (the register), with the Thresholds rows reading from AI use until then.

---

## Phase 1: discovery, the frontier and leads

### P1-1. A page whose reading must wait is never read · blocks

- **Where:** 0164 §1.1 ("an unchanged page answers 304 and costs nothing"), §1.3 (never the page body, Decision 1),
  §2.3 and §0.8 (Gemini's allowance used: "the work waits"); 0163 §11; live `app/lib/crawler/SourceIndex.php` (the
  page log keeps `fingerprint`, `etag` and `last_modified` at fetch).
- **What gets stuck or lost:** a page is fetched, Step 1 (code) leaves fields empty, and Tier C must wait for Gemini.
  The body isn't kept, so the page must be fetched again. But the page log already holds its entity tag and
  fingerprint, so the next visit answers 304 ("unchanged") and the page is never read into facts. The same happens when
  the second tier's budget is used up (Textract, Tavily Extract).
- **Why it matters:** in a busy hour Gemini's allowance is used by design (0163 §11 paces it to the day), so this is
  routine, not rare.
- **Fix:** a page-log state **"waiting to be read"** (`PENDING_EXTRACT`), set when Phase 2 stops short. A page in it is
  re-fetched without `If-None-Match`/`If-Modified-Since`, and its fingerprint is saved only once its facts are. Or keep
  the stripped main text for at most 48 hours in a private buffer, never served and deleted once read (Decision 1
  amended for a buffer). The first is simpler and keeps Decision 1 as it is.

### P1-2. A source that blocks us is re-checked at high priority without end · degrades

- **Where:** 0164 §3.4 (`HOLD_NEEDS_RECHECK`: "queued back at high priority"), §6.1 (freshness sweeper), §1.2, 5
  (`AUTH_WALL` and `PAYWALL` re-checked once a year), §1.3.
- **What gets stuck or lost:** a listing's facts go stale. Its source now answers 403 or robots.txt refuses, so the
  sweeper sends it to the frontier at high priority. §1.2 refuses it, and the next sweep sends it back. Two rules route
  the same URL differently (high priority now, or once a year), with no limit. Meanwhile the record stays on hold, and
  nothing tries another source.
- **Fix:** a recheck refused by §1.2 takes the negative cache's clock (yearly for `AUTH_WALL`, on change for robots.txt).
  The record's own refresh then moves to its other sources (the identity record, open data, the Call list), and it
  shows "last checked" (Decision 3).

### P1-3. A search that finds nothing reaches the crawler only when it names a town · degrades

- **Where:** 0164 W12, §4.5, R15 ("gaps drive the crawler"); live `website/api/search.php` (`Situations::logTerm` logs
  the zero-result flag; `SearchDemand::fromSearch` counts only a search someone acted on, plus `lacking` for "near
  me"); `app/lib/SearchDemand.php` (`DESTS` has no "found nothing"); `app/lib/crawler/CrawlTargets.php` (queued only by
  an admin, `complete()` holds every find for a person).
- **What gets stuck or lost:** a town named in a search goes to the crawler (`TownCrawl::fromSearch`). A kind of
  business or a topic with no results ("farrier", "history of Concho") stops in the term log, read only by search's own
  learning (0093). R15's topic research queue is fed only by AI Assist. Search targets, once found, are held, and they
  land in Review without a prompt (X3).
- **Fix:** a search with no results records demand (place, the kind or topic from our own vocabulary, never the words:
  see P2-2). A kind becomes a `TownCrawl::want`; a topic becomes a topic gap for W6 (R15), ordered by how often it's
  asked. Search targets inside an open hub aren't held.

### P1-4. A Gemini lead whose cited page we may not read never resolves · degrades

- **Where:** 0164 §1.5 ("a lead is kept only with a cited link, and nothing it says becomes a fact until …"), R13, R14
  (Facebook is never crawled); 0163 §12 (a directory found by grounded search, 15; a Gemini lead itself, 0).
- **What gets stuck or lost:** grounded search often cites a Facebook page, Yelp or a directory: pages we don't crawl, or
  may only cite. The lead has a link but no page we can read. It adds witness weight but never a keepable phone or
  address (P3-2), and leads have no expiry or "tried" state, so they pile up.
- **Fix:** a lead's states: **new → read** (its cited page read) **→ witness only** (the cited pages are cite-only or
  social: counted in the collaborative score, then closed) **→ expired** (90 days with nothing readable). Each lead
  counts once in the register's "what it produced" (0163 §1).

---

## Phase 2: extraction, and what may reach Gemini

### P2-1. Private details reach Gemini before Phase 3 removes them · blocks

- **Where:** 0164 §0.8 ("nothing private ever goes to it"), §2.3 Tier C ("public pages go to Gemini's free tier"), §3.4
  (private non-business home addresses, numbers and accounts removed "before storage", which is Phase 3), R6, W6.
- **What gets stuck or lost:** nothing is lost; something leaks. "Public page" isn't defined. A public page can name
  private people: obituaries, court and legal notices, census rows and deeds in W6's newspaper and archive sources, a
  sole proprietor's home address in a licence record, a church bulletin. Phase 2 sends the text to Gemini before
  Phase 3's removal runs. The free tier lets Google use what we send.
- **Fix:** define "public" for Gemini as **a public page with R6's private details removed first**. Run the code-first
  removal (§3.4's list, plus the people rule: names beside birth, death, court or address words) on the main text before
  Tier C, so Phase 3's removal also runs at the start of Phase 2. Until W6's sources have that pass, keep W6 and every
  archive, newspaper and registry feature on Claude.

### P2-2. A person's words go to Gemini through W12 and W8 · blocks

- **Where:** 0164 R5 ("a member's words go to AI … only to Claude"), §0.8; W12 ("grounded search for gaps open data
  couldn't fill"); W8 (Gemini grounded search before paid web search); W13 (a story's named places to Find it); live
  `app/lib/ContentTags.php` `tagPiece()` and `app/lib/GuideLinks.php` (a member's piece sends its names to Find it).
- **What gets stuck or lost:** two rules route the same thing differently. A visitor's search words are a person's
  words; W12 would send them to grounded search. A name from a member's story ("Grandma's place on Elm St") is a
  person's words; W13 → W8 would send it to grounded search. Today Find it uses Claude, so it's within R5. Stage 9 of
  0163 §8 moves it to Gemini.
- **Fix:** what goes to Gemini is built by the system from our own vocabulary (a kind from `OpenPlaces::KINDS` and a
  town from `zip_coordinates`, or a name from a guide the system wrote), never a person's words. Names from a member's
  piece go only to Claude, with their consent, as today.

### P2-3. Nothing in code stops a person's feature being moved to Gemini · degrades

- **Where:** 0164 R13 ("a director changes a feature's provider after the sample comparison"); 0163 §7, §11;
  `app/lib/AiClient.php` `ROUTES`; `app/lib/Automation.php` `PERSON_FEATURES`.
- **What gets stuck or lost:** the provider is a director's setting per feature. Nothing marks which features carry a
  person's words or private data, so a director could move one (Check my facts, a card scan, AI Assist) to Gemini.
- **Fix:** each feature in `AiClient::ROUTES` declares its input class (public / a person's / private). `providerFor()`
  refuses Gemini for any but public, and AI use greys the choice out with the reason (CLAUDE.md: greyed, never hidden).

---

## Phase 3: resolution, vetting and establishing

### P3-1. The floor and readiness disagree, so finds stop between them · blocks

- **Where:** 0164 §3.5 (floor: "a street address **or** a phone we may keep"), §5.4 (complete: "name, phone or website,
  a street number or point"), R10, C4, §14 point 4; live `AutoImport::evaluate()` ("complete" needs a phone or website
  **and** a street, town, state and ZIP **and** a geocoded point).
- **What gets stuck or lost:**
  - a **licence or IRS record** (name and street address, no phone) passes the floor and establishes "alone" (C2, C4),
    then fails readiness: there's no phone or website;
  - a **phone-only find** (an open dataset with a phone and a PO box, or a highway mile marker) passes the floor, then
    fails "street number or point" unless it has a point;
  - on the **Navajo Nation** (Kayenta) few businesses have a street address.

  0164 has three states (a sighting, established, published) and only says what happens without either detail. A find
  with one of the two is established, never published, and in no list.
- **Why it matters:** these are the rural businesses §0.7 and the rural test were written for.
- **Fix:** make "complete" match the floor: **a name; a phone, website or street address we may keep; and a point in an
  area we cover** (from the address, the dataset's own point, or the town's middle, marked approximate). A listing with
  only an address shows Directions and "No phone yet: know it?". One rule, written once in §5.4, with §3.5 pointing to
  it.

### P3-2. A sighting with no keepable phone or address has no way out · degrades

- **Where:** 0164 §3.5 ("it stays a sighting until a keepable source supplies one"), §14 point 4, R9 (Google keeps only
  its place ID), R14 (a Facebook page is never evidence of a phone; "our team can read it and confirm details"), §4.5
  (gap analysis covers "records").
- **What gets stuck or lost:** a business known only from Google (a place ID) and a Facebook page, which is the rural
  case §0.7 names, can't get a keepable detail from either. Nothing asks for one. Sightings aren't in §4.5's
  completeness queries, the Call list needs a phone, and R14's "our team can confirm" has no step that asks the team.
  Overture's Meta record helps when Overture has it (its phone is keepable under CDLA-Permissive), but not always.
- **Fix:** sightings join §4.5 as discovery targets, retried on the evidence's clock. Sightings with a Facebook link go
  to a Pulse case, "Confirm from its page" (a step each, the link, the fields to type: R14). The sighting's town page
  asks visitors "Know its phone or address?", as a suggestion. After a year with nothing, the sighting is kept in the
  identity record (R11) and stops being retried.

### P3-3. The grey zone can quarantine without end · degrades

- **Where:** 0164 §3.2 step 5 ("still grey goes to Review with a 30-day quarantine"), §6.1 (quarantine worker).
- **What gets stuck or lost:** after 30 days the worker re-checks it, with paid checks only within budget. If it's still
  grey, it is quarantined again. There's no limit, and it's unclear whether a person acting in Review during quarantine
  wins. With Google's budget used (or below the 35+ line, F5), it never leaves.
- **Fix:** at most two quarantines, then it stays in Review with its X3 step and no more automatic retries. A person's
  answer ends the quarantine at once.

### P3-4. One crawler item against a claimed listing drops it out of the claim band · degrades

- **Where:** 0164 R12 ("any claim puts a listing in the top band … unless evidence turns against it"); live
  `app/lib/ListingConfidence.php` `compute()`: the claim and team floors apply only `if (!$against)`; any `suggestions`
  row in `closed|moved|fix|wrong`, including the crawler's own `crawler-refresh` ones, adds to `$against`, shown as "A
  visitor reported something to check".
- **What gets stuck or lost:** a claimed, verified listing goes from 90+ to its machine score on one unreviewed item
  that no one is prompted about (X3). The line also says "a visitor" when it was the crawler.
- **Fix:** a claim keeps its band, and things against it show as a caution line and lower it within the band (80 to 89
  claimed, 90 to 99 proved). Only a claim found false, or Closed, leaves the band. Label the source of each item truly
  ("Its own website says it closed", "A visitor reported …").

### P3-5. An owner isn't told when the crawler disagrees or asks "Still open?" · degrades

- **Where:** 0164 R4 ("a claimed listing is only offered a change"), R12 ("Still open?" asks the owner, if claimed);
  live `Refresh::siteResult()` ("It has an owner, so a person decides": our staff, not the owner).
- **What gets stuck or lost:** the offer and the question have no channel. The owner learns nothing until the twice-
  yearly "Still right?" email.
- **Fix:** the owner's business portal gets "Check these" (each offered change with its source: Accept / Keep mine),
  and an email the day one is raised, as a system email (free, CLAUDE.md). An answer from the owner settles the X3 step.

---

## Phase 4: the catalog and gap analysis

### P4-1. Gap analysis re-queues gaps that can't be filled · degrades

- **Where:** 0164 §4.5 ("completeness queries flag records missing key fields; each becomes a discovery target … at high
  priority"), R12 ("having no website … never counts against a listing").
- **What gets stuck or lost:** a business with no website, hours or email is flagged on every run and re-queued at high
  priority. This is a loop with no exit, and it crowds out real gaps.
- **Fix:** a gap records "looked, none found" with the date, and is retried on the evidence's clock (R12's cadence), not
  every run. "No website" isn't a gap once two looks found none.

### P4-2. When a source's licence changes, facts already stored don't follow · degrades

- **Where:** 0164 R9 ("the licence travels with the fact"; terms checked yearly in Pulse); §1.5 (Overture's licence is
  per record, `sources[].license`, per monthly release; the rural test §6).
- **What gets stuck or lost:** the licence is checked going forward only. If a source's terms tighten (a registry adds
  "no automated use", or an Overture upstream changes licence), the facts already stored stay stored and shown.
- **Fix:** a licence change on a source (or on an upstream, per record, at each load) re-sorts its facts. Those no
  longer keepable become evidence only. A listing field resting only on them is taken off (R11: kept as taken off),
  else it is kept from its other sources. One Pulse step per source shows the count.

---

## Phase 5: publishing, closures and protected places

### P5-1. Our team isn't among those who may say Closed, and "a sign" has no way in · degrades

- **Where:** 0164 R10 ("Closed shown": the owner, the public, or the business's own site or sign), R12 (our team is
  "strongest"; "every call to a business is also a check"), C7; live `app/lib/SuggestionActions.php` `markClosed()`
  (today only staff close a listing, from a visitor's report).
- **What gets stuck or lost:** a staff call that reaches a disconnected line, or a staff visit to an empty shop, can't
  close a listing under 0164. "A sign" can only be seen by a person, and there's no field for it: a visitor's photo, a
  staff visit.
- **Fix:** add **our team (a call, a visit, a photo of the sign)** to R10 and R12's Closed sources. A visitor's photo
  of a sign comes as a suggestion, and our team confirms it.

### P5-2. "Still open?" isn't built, and as written it has no way out · blocks

- **Where:** 0164 R10, R12, W2; live `website/api/listing-confirm.php` (only "Yes": "No" opens the correction form);
  `ListingConfidence::yes()` (a count per listing, detail and day); no "Still open?" anywhere in `app/` or `website/`.
- **What gets stuck or lost:**
  - nothing collects a visitor's "Closed" answer per device, so the public can't confirm Closed;
  - a listing with a closure signal that few people open never reaches three answers, so it stays in caution for ever,
    shown in browse lists (sorted lower), with no step for a person (the rural test's "two signals send it to Review"
    was dropped);
  - one person can answer from three browsers.
- **Fix:** build it with W2 (stage 8):
  - "Still open? Yes / It's closed" on a listing with a signal, counted per device a day (as Yes is);
  - after 30 days with a signal and fewer than the count, a step on the Call list (or the X3 case when it has no
    phone);
  - two closure signals from different origins go straight to that step;
  - the public count's guard: F2.

### P5-3. Landmarks have no "story only", and a Wikipedia article makes a held name public · blocks

- **Where:** 0164 R7 (three levels; "Story only": "never location, pin, coordinates, directions"; "a sacred, burial or
  protected place is held by default; it moves to story only when a public source presents it to visitors and our
  crawler confirms that"; "decisions about … sensitive sites are never made by a machine"); CLAUDE.md (2026-11-12, a
  place "plainly public (its own article, open to visitors) shows"); live `app/lib/Landmarks.php` `publiclyKnown()`
  (true for any off-nation place with a Wikipedia title whose name doesn't match `SACRED`), `decide()`;
  `app/lib/GuideLinks.php` `find()` (a guide naming a held-on-a-word landmark flips it to `shown`).
- **What gets stuck or lost:** two rules route the same place differently. Landmarks have only shown, waiting, closed
  and hidden: no story-only level. So an archaeological site, ruin, rock art or cliff dwelling held "on a word", with a
  Wikipedia article and off a nation's land, becomes fully public, pin and directions, by the machine. R7 would make it
  story only. `SACRED` covers graves and the sacred, not archaeological words, and `wiki_title` alone counts as public.
  Heritage facts do have a story tier (`Heritage::TIERS`); landmarks don't.
- **Fix:** add the **story-only** status to landmarks: named and told, no pin, no coordinates in any public endpoint,
  the courtesy line. `publiclyKnown()` moves a held place to story only (not shown), and only "open to visitors" from
  the managing authority's page makes it public. Archaeological words join the held list. Update CLAUDE.md's 2026-11-12
  line to match R7 (Jason's call: it's an earlier decision).

### T1. A held or confidential name can reach a grounded-search prompt · blocks

- **Where:** 0164 R7 ("held: never named or located, even to AI prompts"; "Find it never looks up a confidential
  name"), W8, W12; live `GuideLinks::crawlFind()` and `findTick()` (both check `Landmarks::isProtected` and
  `ListingSafety::isConfidential` before a lookup).
- **What gets stuck or lost:** the checks guard today's Claude and map paths. Stage 9 adds Gemini grounded search to W1,
  W8, W12 and W14, and 0164 doesn't say the same checks apply there. A visitor searching a sacred site's name (W12) or a
  guide naming a shelter (W8) would send the name.
- **Fix:** one guard in the Gemini client (beside P2-3), not in each workflow: `isProtected`, `isConfidential` and
  `Landmarks::NATION_SENSITIVE_CLASSES` names never leave in a prompt. Write it into §1.5's grounded-search bullet.

---

## Phase 6: upkeep

### P6-1. A site paused for parser drift waits for a fix nobody is asked to make · degrades

- **Where:** 0164 §6.1 (canary: "dry-run after 7 days and unpaused only if the new rules work"); 0163 §4 ("returns
  empty": steps Check it now, Pause the task).
- **What gets stuck or lost:** new rules come only from a release. The "returns empty" case has no step that asks for
  one, so a drifted site re-runs the same failing dry run every 7 days.
- **Fix:** after two failed dry runs, the case gets a step "Needs a reader fix", which names the site and the empty
  fields and is picked up by the crawler session's handoff. The site's facts keep showing with "last checked".

---

## W14 and opening a hub

### H1. W14's trigger reads a document the site can't see · degrades

- **Where:** 0164 §7.1 ("started by: a state named in `regions.md` as planned or next").
- **What gets stuck or lost:** `regions.md` is in this repository, not on the site. W14's scheduled start can't read it,
  so a planned state is found only by the yearly wave or a person.
- **Fix:** the hub record's stage (R17: Planned …) is the trigger. `regions.md` is the plan; a director marks the hub
  Planned on the meter and W14 starts.

### H2. Only government domains are kept, but many official lookups are hosted elsewhere · degrades

- **Where:** 0164 §7.1 step 1 and Gate ("only government domains (or a nation's own) are kept").
- **What gets stuck or lost:** licensing boards and county health inspections often run on a vendor's portal, not a
  `.gov` domain. W14 drops them, so the state's sources stay incomplete.
- **Fix:** keep a vendor-hosted page when the agency's own government page links to it (record the link as its
  provenance, Tier 1). A person's call when no government page links to it.

### H3. Arizona and New Mexico never "open", so their staged sources have no switch-on path · degrades

- **Where:** 0164 R17 ("opening a hub switches that state's staged sources on"), 0163 §8 stage 7 ("or by hand for
  Arizona and New Mexico"), §10, 1 (the hub line runs "opening a geo-hub").
- **What gets stuck or lost:** our pilot hubs are open already. A licence source staged for them has no trigger to switch
  on, and the switch-on check's hub line never runs for it.
- **Fix:** for a hub already open, a staged source switches on by itself (its card, 0163 §10, 3), with the same check
  line run then.

### H4. Steps that need a person in a hub's life have no Pulse prompt · degrades

- **Where:** 0164 R8 ("a region opens only after its records are reviewed"; the attorney signs once), R17 ("a director
  moves each stage"), §7.1 (a nation's sources "read only after a person's call"); live `app/lib/Jurisdictions.php`
  `tier()` (restricted until every layer is reviewed), `app/lib/Heritage.php` (refuses to tell a protected place's story
  until the law of the place is reviewed).
- **What gets stuck or lost:** none of these opens a Pulse case: an unreviewed jurisdiction record (W6 tells nothing
  protected there, and a state's first hub stays Planned), a hub whose readiness targets are met, a nation's source
  waiting for a call.
- **Fix:** one Pulse case, "Hubs", with a step per hub (Review its law, Move to Seeding, Decide on the nation's sources),
  each a button.

### H5. A hub's opening budget is unset, and the check doesn't check it · cosmetic

- **Where:** 0163 §2 ("none is set yet"), §10, 1.
- **Fix:** the switch-on check's hub line names "opening budget not set" with a button to set it.

---

## §F. The five items flagged by the approvals session

### F1. "Dissolved or revoked" at −60 (0163 §12)

**Doesn't fit cleanly because** the collaborative score is defined as independent sources *agreeing* on the name plus
the phone or address (0164 §3.5, 4), yet its table also holds closure penalties. And a licence is not part of the
collaborative score at all: it establishes by itself (§3.5, 3). The penalties don't age (R12: "evidence ages"), aren't
said to reverse when a licence is renewed, and aren't said to count once per origin (a monthly Overture reload could
count "dropped" again each month).

**Recommendation:** keep −40 and −60 as starting values, but move them out of the collaborative score's table into a
second table in 0163 §12, **"Signals against (R12)"**. Each counts once per origin, ages like other evidence, and is
cleared when its source reverses (licence renewed, the record back in the dataset) or when the owner or the public says
"still open". A claimed listing stays in its band (P3-4). Note in 0164 R12 that a dissolved company is not a closed
business (sole proprietors often carry on), which is why it asks rather than closes.

### F2. "The public confirms Closed" = 3 devices, none saying open since, kept in R10

**Doesn't fit cleanly because** it's a number in 0164 against the one-home rule, and three devices can be one person.

**Recommendation:** **keep it in R10.** It is a data gate like the guide score or the entity match, and 0164's own
start-to-finish says "the numbers are in 0163 (and the data gates in R10)". Add those words to 0163's one-home header so
both records say the same. Tighten the rule: **three devices on at least two different days within 60 days, none "Still
open" since the first**. Where there was no machine closure signal before, our team's call (the Call list) confirms it
first. A claimed listing's owner is told and can undo it. Build it with P5-2.

### F3. The "Reported closed" label folded into caution

**Doesn't fit cleanly because** the live code shows two different "against" lines (`ListingConfidence::compute()`):
"Reported closed" (−20) is shown for the `closed` key, which `markClosed()` sets when a listing *is* Closed, and
"Possibly closed: its own website says so" (−10). 0164 has three states (open, caution with "Still open?", Closed),
and "Reported closed" maps to none of them.

**Recommendation:** three states on the card, no others. **Open**. **Caution:** "Still open?" with the signal's own
line and source ("Arizona's sales-tax licence lapsed, Oct 2026"; "Its own website says it closed"). **Closed**: found
by name, out of browse lists. Retire "Reported closed" and "Possibly closed" as labels. A listing that *is* Closed
isn't scored down: it's Closed.

### F4. Gemini's allowance now warns (0163 §11)

**Doesn't fit cleanly because** the 50/75/95% levels were made for monthly caps that should not be reached. Gemini's
allowance resets each day and is *paced to be used*: background work runs to 80% every day by design. So the 50% and
75% warnings would open a case every day, and nobody can act on it (the fix "raise the cap" doesn't exist on the free
tier).

**Recommendation:** for a paced daily allowance, warn on what is **unexpected**, not on use:
- background work reached its 80% stop before 18:00 UTC three days running (there's more work than allowance: slow a
  feature or move one back to Claude);
- the 20% on-demand reserve fell below 5% on any day (a visitor may wait);
- a refusal or ban is "held", as today.

One case, updated, as 0163 §4. Keep 50/75/95% for monthly caps.

### F5. "Google's open check only at 35+" not yet in 0163

**Doesn't fit cleanly because** it's the rural test's order of work (§7, step 3), not written into either record. It
doesn't say 35 of what: with no threshold left, it can only be the collaborative score before Google. And it's a
one-way door: a find below 35 can't get Google's 30, the biggest witness, so it climbs only through the owner, a
visitor or a new source.

**Recommendation:** add it to 0163 §12 as a setting: **"Google open check from a collaborative score of 35 (before
Google)"**, director-tuned, an ordering rule only (0164 R10's spirit). Two exceptions inside the paced budget: a listing
a visitor opened or searched for this month, and a Review item where Google would settle it (the grey zone, P3-3).
Point to it from 0164 §1.5's witness row.

---

## Counts

| Severity | Findings |
|---|---|
| blocks (8) | X3, P1-1, P2-1, P2-2, P3-1, P5-2, P5-3, T1 |
| degrades (19) | X1, X2, X4, P1-2, P1-3, P1-4, P2-3, P3-2, P3-3, P3-4, P3-5, P4-1, P4-2, P5-1, P6-1, H1, H2, H3, H4 |
| cosmetic (2) | X5, H5 |

Most fixes fit stages already in 0163 §8: P3-1, P5-1, P5-2, F1–F3 and F5 with stage 8; P1-1, P2-1, P2-2, P2-3, T1 and
F4 with stage 9; H1–H5 with stage 10; X1–X3 before any switch-on. P5-3 and the CLAUDE.md line need Jason's OK (an earlier
decision); the rest extend 0164 and 0163 in place.
