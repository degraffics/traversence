# Hand-off: the session manager

For the Claude session that manages Jason's other sessions (CLAUDE.md, "The session manager"). Written 2026-11-14 by the
first one, moved to a fresh session at 600K context. Jason works mostly from his phone: short messages, one line per
item, decisions as "reply go".

## The job

Keep sessions fast and affordable, and keep Jason told only of what needs him.
- **Watch:** context size (hand off past ~400K), restarts, cost, sessions waiting on Jason, release clashes, file claims
  (`tools/page-audit/checkout.py list`, decisions/0161), stuck syncs (traverse-3way-sync `sync.yml`), Railway deploys.
- **Propose, Jason approves:** splitting, a fresh session per phase (the old one updates its `handoffs/` file and stops),
  starting a session for a problem he reports. At most about three working sessions at once.
- **Without asking:** archive a session that has said it's done or handed off, work in `main`, nothing waiting on him
  (Jason, 2026-11-14). Never archive one waiting on him, the session manager itself, or "Community Build" (he keeps it).
  If the permission check refuses an archive, ask him for "archive it".
- **Relay:** when Jason answers here for another session ("merge it", a choice), send it to that session with
  `send_message` in his words. Never approve anything on his behalf.

## How the checks run

- **Hourly, while he's checked in:** a scheduled task named "Hourly session check" (cron `14 * * * *`) fires into the
  session manager. "Checking out" disables it; "checking in" (or "check in") enables it. Its prompt lists every check.
- **Notify** (PushNotification) only when something needs him: a session waiting, or a proposal. One line.
- **Claims check without stray files:** `git status -s` empty, then `git checkout -B <own branch> origin/main`, push it,
  `checkout.py list`. Never leave main's files as uncommitted changes (the stop hook complains).
- **Railway:** a token for `backboard.railway.com` is saved on the "cloud" environment; query
  `https://backboard.railway.com/graphql/v2` (deployments, `deploymentLogs`). Project "gracious-sparkle": services
  `traversence` (the crawler worker, cron every 5 minutes) and `video-worker`. "CRASHED" on a cron service means a run
  exited non-zero; read the logs before anyone presses Restart.
- **Reading another session cheaply:** `get_session` (size, restarts, cost, last summary) and `list_events` with
  `kinds: ["result"]`. Large results land in a file: parse with python, don't load them whole. Reading never wakes it.

## Rules Jason set (all in CLAUDE.md)

- Every build is a release through git; he approves each merge in chat; no zips for him to upload.
- Both repos: sessions add `traversence` / `traverse-3way-sync` with add_repo without asking. The permission check may
  still refuse a session's own add or `create_session` with pre-approved tools: then he types "add the sync repo" there.
- FAQ clashes resolve themselves; check-out per 0161; "how it works" questions go to the documents, not to him.
- Cost: on Max 5x ($100/month); `cost_usd` on sessions is API-rate equivalent, not a bill. Watch the 5-hour window.
  The archived "Get" session is 903K context (~$2,700 equivalent): read its record, don't wake it.

## Paused (2026-11-14)

- **`decisions/0163` and `decisions/0164` are accepted** (Jason, 2026-11-14: "all approved"; no shadow mode; 0164 §14
  settled). **The crawler and the automation stay paused** until the build stages (0163 §8: operations stages 2–6, then
  7–11 for the Knowledge Engine's new parts) and the switch-on check (0163 §10) are done and Jason switches them on.
  Each stage is a release he approves, one session per stage from its handoff (`handoffs/crawler-revamp.md`).
- **The Railway crawler's cron** was removed through the API (~15:55 UTC); `railway.json` still says every 5 minutes, so
  a worker deploy restarts it (details at the top of `handoffs/crawler-revamp.md`); the automation master switch (release
  #48) makes any run that starts end at once. The hourly check expects no crawler runs meanwhile.

## Where things stand (2026-11-16, 14:30 UTC)

- **Built and live:** decisions/0165 (#56, #57), Release B (#58–#67), the photo fix (#68), and Start the system plus the
  review link's login redirect (#69). Nothing is running. Automation and the crawler stay off until Jason presses
  **Start the system** (keys first: `handoffs/release-b.md`).
- **Keys, traced with Jason by service on 2026-11-16:**
  - GitHub: `GITHUB_SYNC_TOKEN`, read-only on both repos.
  - Railway: limits set, compute $25 stop and $10 alert, agent $10 and $5; `RAILWAY_API_TOKEN` made; `HF_TOKEN` added.
  - Google and Gemini: done. m2servcore owns the projects and billing. The Places key is restricted to Places API (New),
    with SearchText at 200 a day and a $10 budget. `GOOGLE_PLACES_MONTHLY`=1000 on Railway. One Gemini key, …rCWw, on the
    free tier.
  - Finished 2026-11-16 (Jason put each block in `.env`): cPanel and `STAFF_EMAIL_DOMAIN=trvgo.us`; email (Bluehost
    `donotreply@traversence.com` on 465, the orders mailbox, SES in `mock` until production access, `EMAIL_LINK_KEY`);
    Cloudflare R2 (`S3_…`; the AWS bucket `traversence-media` to empty after Nov 18) and Turnstile; Meta sign-in; Stripe
    and Square in sandbox (test values in both the plain and `…_TEST_`/`…_SANDBOX_` names; no live keys yet; setup at
    Stripe and Square skipped for now); Fly and Name.com (`DOMAINS_MODE=off`, setup later); the legal identity (Jason
    to consider a shared `LEGAL_EMAIL` and a business `LEGAL_PHONE`); Pixabay, OpenRouteService, `HEALTH_TOKEN` (must
    match the GitHub secret); Hugging Face on Railway only; Supabase (setup SQL from its card still to run).
- **Queued after the usage reset (~Nov 17 23:00 UTC):**
  1. **W0 Indexer: every URL logged, always on** (**done** 2026-11-17 in session_01CXLi5jAPsvr7mrCV6roykv: site side live as release #79, the worker in `main` (#803), Railway service `w0-indexer` running and asleep while W0 is paused; handoffs/w0-indexer.md) (0164 §1.4's note, Jason 2026-11-17: "Put it first in the queue"): every link on every page read into the frontier (same site or not, with its page and link text);
     classification runs by itself on new sites; reading only where §1.2's rules allow; a person's call overrides,
     never gates; today's "Waiting for you" sources classified on the first pass. Worker (`own_site_links`, the
     40/60 caps, `read_source`) and site (`Sources.php`, `SourceIndex.php`, `/api/crawl/sources.php`).
     **And the indexer always on** (Jason, 2026-11-17): its own continuous Railway service (restart on failure), not
     the cron; indexing only; first in Start the system; Pause everything stops it; its own switch and budget line.
     With the reader's verdicts looped back into the index (promoted, normal, demoted, junk kept compact so it's never
     recaptured; patterns become skip rules), reading priority, a size guard, and pace on the site: writes to the site in
     batches: 250 found URLs or 25 read pages, 100 KB at most, one batch per 10 s, self-adjusting (halve on 409/413/slow,
     grow back to 250, cap 500), settings in Thresholds, keyed; the pre-flight sends one full-size test batch.
     **Every batch gets a receipt** (stored count and checksum; the indexer resends what's missing; three mismatches slow
     it and open a Pulse case).
     No per-page or per-source caps (the old 60/40 go); a safety net only for a broken page with an absurd number of
     links: logged over several passes and flagged, nothing dropped.
  2. **The pre-flight check** (**done** 2026-11-17: live as #88, #90, #91, #92, the Outside services table #94 and the Fly fix #93, synced 15:07 UTC; session_01CXLi5kGG8gG4QBmC4uzMzN stands by for Jason's first report after his keys. Earlier: release 1 `preflight.zip`
     sent (built on Sync 14:05 UTC), waiting for Jason's OK; worker 1.1.0 in the same PR; Outside services as a table is
     release 2, after release 1 is live; handoffs/preflight.md) (decisions/0163 §10's note of 2026-11-16; Jason asked for it before launching the
     engine): System Configuration → Pre-flight, a real, free, harmless test of every service and setting, and every
     phase, workflow and task (services, scheduler, settings, caps, gates, a dry run where there's a preview), ready or
     not with the service's reason and the fix, required or optional for launch; Start the system shows it first;
     nightly and after a `.env` change, red items to Pulse; each Outside services card's Test is its item. Laid out as a
     queue log: one line per check, green ✓ / red ✗, each line opens to its status and its fix.
     Domains in it: Fly's API token can read the edge's certificates; Name.com's sandbox answers a search with a price.
     **Query timings** (Gemini's review, 2026-11-17): the heaviest queries (a radius search, a zone roll-up, a graph walk,
     an index lookup) timed against set limits, a ✗ when one slows as the data grows. Supabase awake and the index complete.
     Its own process: runs any time (stopped, starting or running) to diagnose, never starts or pauses anything.
     **With it, Outside services becomes a table** (Jason, 2026-11-17: "maybe we treat it like the table view with search
     and filters sorting"): the admin's table widget, one row per company (name, what we use it for, status ✓/✗/—, this
     month's cost, last checked); search across names, uses and key names; one filter dropdown (status: needs a look, in
     use, not in use; kind: storage, email, payments, AI, sign-in, data sources, domains, workers, hosting); sort by
     status (default), name or cost; a row opens to today's full card in place (nothing dropped); filter and search
     remembered. One list and one status shared with the pre-flight; a ✗ there links to the company's row.
  3. **W15 Funnel: every find and every piece of content into the discovery funnel** (decisions/0170, accepted) (Jason, 2026-11-16, found in the `.env` trace):
     `CrawlerIngest::resolveMicroClusterId()` files any ZIP outside an existing area under `DEFAULT_GEO_HUB_SLUG`
     (ancient-borderlands), wherever it is (callers: `api/ingest.php`, `IntakeStager`, `IntakeClusters`,
     `AddBusiness`). Fix: an existing area first; else the hub whose region holds it, or the nearest in reach; else
     "no hub yet", held for 0164's hub opening and counted as demand. **No default hub** (Jason: "I dont want the
     ancient-borderlands as the default. It is the pilot."): remove the hard-coded `'ancient-borderlands'` fallbacks
     (`api/ingest.php`, `IntakeStager::defaultGeoHubId`, `IntakeClusters`) and retire `DEFAULT_GEO_HUB_SLUG` (Settings'
     "Default place" row goes; nothing else reads it). Intake approvals never refuse for a missing hub: "no hub yet" is
     a valid answer. Data cleanup proposes moving areas already seeded outside Ancient Borderlands (preview, Undo).
     **Use the hub framework we have** (Jason, 2026-11-17: "dont we have a way this can use our existing hub Framework to
     put a find into the right hub even if it isn't an active hub yet?"): Ancient America → its 15 zones (`geo_hubs`;
     the Ancient Borderlands active, 14 `planned`, regions.md) → towns (`micro_clusters`). Give every zone its geography
     (its towns' ZIPs from regions.md through `zip_coordinates`, then the counties they sit in), so a find goes to its
     zone **whether the zone is active or planned**; an existing town first, else a town seeded in that zone
     ("needs review"). A find in a planned zone is filed there and shown as usual (open or planned is about our team's
     support, not visibility; Jason 2026-11-17), and counts toward that zone's readiness (HubOpening's stages, Planned → Open). Only a find outside all 15 zones is "no hub
     yet" (demand, held for 0164's hub opening). No default hub.
     **And across continental hubs** (Jason, 2026-11-17: "if it is not in this hub, but another hub it could be aligned
     within the correct main hub"): the plan is 20 continental hubs (Ancient America is Hub 9 of 20, platform spec);
     each gets its states (and its zones when they're drawn) as **planned**, so a find outside Ancient America is filed
     under the continental hub that covers it, held there and counted as its demand, and placed in a zone once that hub's
     zones exist. Only Ancient America is defined in the repo: **the other 19 hubs' names and states are needed from
     Jason** before this part is built.
     **For all content, not just listings** (Jason, 2026-11-17: "this is not just about listings but how all content
     gets organized into the discovery funnel too"): one place resolver, "where does this belong", used by every kind
     of content as it's made or found: listings, landmarks and outdoor places, places, guides, journeys, events,
     stories, heritage and topics, research and dossiers, Moments and Marketplace posts, sources. Each is placed on the
     place taxonomy (0042, 0043: Continental Hub → Geo-Hub zone → town cluster → anchor → sub-group) from its
     location, active or planned; shown as usual (0170: open or planned is about support, not visibility); feeding that level's discovery guide and counts.
     Something about a whole region (a state, a river, a trail across zones) is placed at the level that spans it.
     Existing content is re-placed by Data cleanup (preview, Undo). Today each kind places itself its own way.
  3a. **State licence lists: a write-up (a decision) and a build plan, then its own build** (Jason, 2026-11-17: "this
     seems like it needs a build of its own. Let's put it into the queue for a write up and build plan"). After W15,
     since it feeds the funnel and uses the one identity check.
     - **Why:** licensing sites block automation (azliquor.gov and liquor.az.gov, roc.az.gov, apachecountyaz.gov: a
       Cloudflare challenge on every page, `robots.txt` too; never worked around, 0164), but a person can export a
       county's list. Jason's Apache County export (2026-11-17, `license_search_export.csv`, DLLC): 250 licences, 23
       columns; 62 active, 188 lapsed (closure signals); 60 active premises (bars, liquor and beer-and-wine stores,
       restaurants, lodges, wineries), 59 with a phone. The value is the rural places open data misses (Molly Butler
       Lodge, Mulloys Public House, Hawley Lake Lodge, RL Winery), proof of open or closed, a phone and "serves
       alcohol"; chains are already in Overture. Exactly 250 rows: check for an export cap (a statewide export).
     - **Workflow:** one source per licensing agency (export page, terms, column map), for Ancient America's states
       (Jason to confirm which); a monthly Pulse case "Licence lists due", a step per agency with its export link and an
       Upload button; a new target in `upload-targets.php` through the one uploader (0127); an agency's own file feed,
       where one exists, replaces its step. About 5 minutes an agency a month. Records requests for a statewide file
       (DLLC, ROC, Apache County) only if exports prove capped. Contractors (ROC) next, the same way.
     - **Rules:** personal columns dropped on load (licensee and agent names, owners' mailing addresses; "anything
       about an owner is private"); kept in its own licence tables, credited, never copied into listings (like OSM,
       0065); never overwrites what a person entered.
     - **Duplicates and names** (Jason, 2026-11-17: "dups with one active and one closed or the name of record is
       different than what the local store brand is publicly"): in Apache County 110 premises licences are only 90
       addresses; 15 have more than one licence, 10 an active beside a lapsed one. Group by premises (address, phone,
       type), not licence: the newest active decides; a lapsed licence beside an active one is history, not a closure
       signal; a closure signal only when nothing at the address is active, and to Review, never automatic; a new name
       at the same address goes to the identity queue ("same place, new owner?"), never a second listing. The licence
       name is never the public name: kept as an alias for search ("Mickey's Again Inc", "Short Stop Markets"); the
       listing shows the owner's or best-attested name. Names cleaned before matching (case, spaces, Inc/LLC, store
       numbers); one identity check (0164 §3.1, `handoffs/duplicates.md`), no second path that makes duplicates.
     - **Write-up first:** a decision for Jason with these rules and the build plan (releases in order), then the build.
  4. **decisions/0169, the Shop's App store and one lifecycle for every app** (accepted 2026-11-16). First: the Shop
     with departments, the App store, the app lifecycle (choose, install, configure, use, update, remove), the Apps page
     and the staff view. Today's Stripe and Square connections keep working until their app is built.
  5. **Then the apps, one release each, in Jason's order** (each built on 0169's lifecycle):
     1. **QuickBooks Bookkeeper:** decisions/0166 steps 1–2 (our ledger and a QuickBooks connector matching today's
        path, run side by side and compared; then the setup step: A or B, the starter chart, the catalog's type,
        income, cost and link, the plan detected);
     2. **QuickBooks Payments:** 0166 step 3's payments layer (routing, the fallback, refunds through the original
        provider, clearing accounts), QuickBooks Payments its first processor;
     3. **Stripe Payments:** 0135's connection moved in, with "Which should I use?" and "Don't have an account?";
     4. **Facebook Personal:** decisions/0168 Phase 1;
     5. **Facebook Business:** 0168 Phase 3 on (waits for Jason's Business Verification and Meta's App Review).
     6. **Storefront:** 0139 moved in; its setup includes the address (keep trvgo.us/<name>, bring a domain, or buy one).
        **With it, trvgo.us's hyphenated addresses** (found 2026-11-17 by the favicon build; Jason: "add to storefront
        app"): `https://trvgo.us/round-valley`, `/sj-portables` and pages like `/shop/about-us` get Apache's own 404
        ("…while trying to use an ErrorDocument") and never reach `trvgo/index.php`; `/abc12` and `/sjportables` do.
        Likely `trvgo/.htaccess`'s rewrite pattern allows only letters and digits: widen it to hyphens and slashes
        (real files and folders still excluded), give trvgo a working ErrorDocument, name the `.htaccess` separately in
        the release (dotfiles aren't synced), and test a hyphenated storefront end to end, including a business's own
        domain through the Fly edge (`X-TV-Host`), untested.
     7. **Domains** (0169 §1a): **live as release #71** (2026-11-17 03:30 UTC, synced and verified; session archived) from session_01CXLi5j7MKBy27a6L7AXRHN (started 2026-11-17 00:53 UTC at
        Jason's ask): Shop → Domains, the account's Domains tab, and the shared wallet (0166 §6a) only. Joining storefronts to the account's domains
        stays with app 6 (Storefront), queued; the storefront's Connect your domain is left as it is until then.

     Others (Square, Tickets, Appointments, Gallery storage, calendars, 1Password…) to be discussed with Jason next.
  6. **The cleanup stage** (its own area; can run beside the above). **Fixes in the code:**
     - Pulse #31's 62 unused names;
     - Pulse #22's Site health items;
     - Data cleanup's "From visitors" label;
     - `/admin/crawler.php`'s outdated "Cron Schedule */5" instruction (Start the system sets the cron now).
     - Outside services' Stripe card checks `STRIPE_LIVE_SECRET_KEY`/`STRIPE_LIVE_CLIENT_ID` for live, but `MarketPay` reads
       live keys as `STRIPE_SECRET_KEY`… (no `LIVE_`), so a correct live setup shows "keys missing" (found 2026-11-16).
       Make the card read what `MarketPay::missingAll()` reads; same check for Square's card.
     - `Vectors::setupSql()` (Supabase card) leaves `tv_vectors` without row level security and `tv_match` callable by
       Supabase's public roles. Add `enable row level security` and revoke `tv_match` from anon/authenticated/public with
       a grant to service_role (Jason ran these by hand on 2026-11-16).
     - Admin search on a phone (seen 2026-11-17, 390px): a result's where-it-lives line takes the row, so names shrink
       to "Na…", "Bus…", "Em…". On phones, put that line under the name, full width.
     - From the pre-flight's first report (2026-11-17): 36 outside calls skip `StagingGate::hold()`
       (`StagingGate::scan`, older than the pre-flight). (The switch-on check's `BRAVE_API_KEY` hold is correct: Jason,
       2026-11-17, Brave and Tavily both present and ready; which one a workflow uses is the workflow's matter.)
     - Outside requests don't save (Name.com ticket #3113488, after #84/#87/#89): likely the same cause the pre-flight
       found in `search_index_state.v`, a live column shorter than the code assumes, cutting the JSON silently; fix as
       the pre-flight did (their own table), confirmed by the card's "Can't keep requests" line.
     **For Jason** (from the same report): `HF_TOKEN` and `BRAVE_API_KEY` on Railway's `traversence` service's Variables; `GITHUB_SYNC_TOKEN`
     expires Dec 16, 2026.
     **To purge or remove** (Jason, 2026-11-17: "put anything that needs to be purged or removed into queue under
     cleanup stage"). Each is checked against the live site first and removed only with Jason's OK where marked ⚑:
     - **Amazon S3:** empty the old bucket `traversence-media` (from 2026-11-18, once a week on R2 shows nothing missing;
       0151 step 5) ⚑, then narrow the AWS key (`AWS_ACCESS_KEY_ID`) to SES sending only, then delete the `#OLD
       S3_BUCKET=traversence-media` line from `.env`.
     - **`.env` leftovers** (Jason removes them; nothing reads them): `MIGRATION_RUNNER_TOKEN` (its scripts are gone),
       `AUTO_IMPORT_MIN_CONFIDENCE` (moved to Thresholds), Cloudflare's stray `token_value=` (the R2 page's token value;
       not read; keep it in 1Password if wanted, don't revoke: it may be the R2 key's own), `DEFAULT_GEO_HUB_SLUG`
       (with the hubs-by-location fix), and `QB_INCOME_ACCOUNT_ID`/`QB_REFUND_ACCOUNT_ID` once 0166's setup step is live.
     - **Database leftovers:** the `_moved_npi_orgs` (38 MB) and `_moved_irs_orgs` (16 MB) tables in the health report:
       confirm they're leftovers of a finished move (decisions/0110, 0162's leftover check in Pulse) and nothing reads
       them, then drop them through a release's `"removes"` ⚑.
     - **Settings:** the "Default place" row (goes with the hubs-by-location fix).
     - **Claims:** the lapsed claim on `app/lib/Store.php` by the archived photos session: clear it with `checkout.py`.
     - **Pulse:** the old Overpass/OSM cases from before the pause (resolve, keeping the overpass-api.de one), and the
       62 unused names (above).
     - **GitHub:** merged `claude/…` branches (Branches → trash), keeping `claude/session-manager-checks`.
  7. **Release B's small leftovers:** P3-5 (the owner's "Check these") and P3-2's Facebook-link case.
  8. **decisions/0167, communications in one place** (accepted 2026-11-16). Build it in its four steps, each a release
     for Jason's review:
     1. System Configuration → Communications: senders, mailboxes from cPanel on every domain, Check and Send me a
        test, domain health read-only; Bluehost sends logged;
     2. manage: make, suspend and reset mailboxes, passwords written to `.env` by the site, the routing table, DNS fixes;
     3. Pulse: incoming mail in the Stream, bounces and complaints as items, the Sent view, failures as cases;
     4. compose in Pulse, with threading; one inbox per person across the mailboxes they oversee, and a From choice (§2a).
  (decisions/0168's phases are apps 4 and 5 in item 2; its later phases follow those.)
  9. **Backups for the needs that have none** (Jason, 2026-11-17: "this needs to be a future build so pass it to the
     session manager for adding to the work queue"). Recorded in decisions/0163's note "the source map, ours first, a
     backup for every need", table "Planned backups" (in PR #840). Not needed for launch; until each is built the
     pre-flight shows it as "no backup yet, planned". Jason's order: database backups and the read-only copy first.
     1. Hosting (Bluehost): nightly database backups to R2 or Supabase; a nightly read-only copy of the public pages
        (guides, listings, places) served from Cloudflare or the Fly edge when Bluehost stops answering (Supabase can't
        run the PHP site).
     2. The engine's host (Railway): Bluehost jobs keep the light tasks; the same crawler image on the traversence Fly
        organization for the heavy ones.
     3. The sync (GitHub Actions): Railway runs `sync.py` on its schedule; the result recorded in git once GitHub is back.
     4. Our payments (QuickBooks): Stripe as a second processor, then wire or check; no card processing of our own (PCI).
     5. Marketplace checkout: the business's other processor if both are connected, else pay at pickup or contact the
        seller.
     (The source map's own builds stay with the pre-flight session after PR #840: (a) the pre-flight judging by need,
     our own data copies checked; (b) W1 reading `open_places` (the Geofabrik copy) before Overpass; (c) the other
     workflows' fallbacks.)
- **Jason, from the Domains build:** set **Own domains on storefronts** to **test** on the Name.com card
  (https://traversence.com/admin/config/services) to try domain sales; **withdraw release #70** (staged, never shipped:
  its migration wasn't add-only; #71 replaced it) on the site manager's Releases page.
- **For Jason to sort out:** the **Chameleon Filter** (brand.md §5, architecture.md §22, 0003, 0131 §3). **Already built
  in guides:** the Visiting / Live here dial and each section's picks (relevance, proximity, kind, season; never paid);
  **Jason's frame (2026-11-17):** the place tree and the topic tree joined by home, touch points and reach (0170 §2)
  "is the chameleon filter in my head": the filter is the lens that, for a visitor's intent, picks which links to follow.
  **still to decide:** the Community track, the filter beyond guides, and its use of the intent tags (0170 §1a): how each place's page shifts emphasis by the visitor's track (Get Local, Let's Explore, Community), which
  tags and items show for each, and the DIKW climb. Phase 4's intent tags (decisions/0170 §1a) are its data; its
  design is Jason's to decide (Jason, 2026-11-17: "put chameleon filter on the queue for me to sort out"), then a
  decision and a build. Two questions with it (2026-11-17): (1) where research finds a real community organization
  with no group, should the Community track invite it to claim its listing and start one? Groups are never
  auto-created; they stay person-made (Jason). (2) When an item is both Get Local and Community (a church, a library),
  which track leads on a place's page? Tabled by Jason for another day.
- **Jason's own, any time:** Meta Business portfolio and Business Verification (0168 Phase 2); the OSM forum statement (`research/osm-overpass-2026-11-13.md` §2), and the SES dashboard
  check for bounces lost while the bot check blocked them.
