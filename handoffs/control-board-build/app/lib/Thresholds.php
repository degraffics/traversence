<?php
// app/lib/Thresholds.php — thresholds and gates (decisions/0133): one table, in System Configuration → Thresholds & gates,
// each value named by what it gates. Some are numbers a director may tune here (stored in search_index_state as
// gate:<key>, with who changed it and when); some live with their feature (the AI switches, kept in ai_features, are
// changed from here through AiClient); some are standards, shown so the whole picture is in one place but changed only by
// a decision. Readers ask Thresholds::get() and fall back to the default when nothing is stored.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Thresholds
{
    /**
     * key => [what it gates, kind, default, min, max, step, unit, where it is read / what it means].
     * kind: 'number' (tuned here), 'switch' (an AI switch's lowest score, AiClient::SWITCHES), 'rule' (a standard: not tuned here).
     */
    public const GATES = [
        'listing_publishes' => ['A listing publishes', 'rule', 'The readiness rules: a real place (its own website, a registry, two sources, a government page we read, or one source scoring past the line below), a way to reach it with a street address, a category that fits, not listed already', null, null, null, '', 'AutoImport::evaluate (decisions/0076).'],
        'listing_one_source' => ['One source establishes a listing at', 'number', 50, 20, 100, 1, 'score', 'Retired as a gate (decisions/0164 §3.5, §5.4; gap review X2, Release B): a find goes public once it passes the \'real business\' floor and readiness, at its confidence; this number no longer decides anything.'],
        'guide_publish' => ['A guide publishes itself', 'switch', 70, 0, 100, 1, 'score', 'Below it a guide waits as a draft for a person. A name that may mark a protected site, or a closed business, always holds it back.'],
        'guide_ondemand_share' => ['Guides on demand: share of the day\'s AI allowance', 'number', 50, 0, 100, 5, '%', 'A searched place\'s first look is written only while today\'s on-demand spend stays under this share of the daily AI allowance (the AI monthly cap ÷ days in the month); past it, places wait for tomorrow, most asked first (decisions/0147). 0 turns Claude\'s writing off; places still get their journeys and what we hold.'],
        'guide_find_add' => ['Find it adds a listing', 'switch', 80, 0, 100, 1, 'score', 'Retired as a gate (decisions/0164 §3.5, §5.4; gap review X2, Release B): a find goes public once it passes the \'real business\' floor and readiness, at its confidence; this number no longer decides anything.'],
        'category_auto' => ['A category is set without a person', 'number', 80, 60, 99, 1, '% confidence', 'CategoryClassifier (decisions/0024). "Other" never is. Also the line for correcting listings already live.'],
        'category_review' => ['Below this, a person picks the category', 'number', 50, 20, 79, 1, '% confidence', 'CategoryClassifier. Between the two lines the readiness rules decide whether the best fit stands.'],
        'search_publish' => ['Search publishes what it learned (it leads the results)', 'number', 0.80, 0.50, 0.99, 0.01, 'relevance', 'Learning (decisions/0093). Must stay above "may also help".'],
        'search_may_help' => ['Search shows "May also help" under the results', 'number', 0.60, 0.30, 0.95, 0.01, 'relevance', 'Learning (decisions/0093). Below it a mapping keeps learning; one that was shown and falls below it retires.'],
        'heritage_cataloged' => ['A heritage fact is cataloged (and may be told)', 'rule', 'A primary record, or two independent sources, at least one of which our source register lets us keep and show', null, null, null, '', 'Heritage::extract (decisions/0133). The research brief never counts as a source.'],
        'ai_free_asks' => ['Free AI Assist asks a day (members with a paid plan)', 'number', 3, 0, 10, 1, 'asks', 'AiCredits. Then each ask uses a credit. Each ask costs us about 1 to 2 cents.'],
        'ai_cover' => ['The most a member\'s asks may cost in a month', 'number', 0.5, 0.1, 0.9, 0.05, 'share of what their plans pay', 'AiCredits: whatever the credits say, a member\'s AI cost stays under this share of their plans\' monthly price.'],
        'takedown_hours' => ['A takedown request is hidden within', 'rule', '24 hours (at once for a protected reason)', null, null, null, '', 'Takedowns (decisions/0133, the protected-information standard).'],
        'takedown_per_day' => ['Takedown requests one person may send a day', 'number', 5, 1, 20, 1, 'requests', 'Takedowns. Counted by a one-way daily hash, never who.'],
        // paid backups by a director's override (decisions/0163's source map, step 3; PaidOverride): 0 = off
        'po_places_confirm' => ['Paid backup: Google Places confirms a business', 'number', 0, 0, 1000, 10, 'checks a month', 'Off at 0. A director\'s override (decisions/0163, the source map): only when the free sources (its own website, NPI, Tavily, Brave) found nothing, in the second look at a new find (W1) or the identity check of a listing (W2); at most 3 a crawler run, about 3 cents each, counted apart from the Google check and written to the cost ledger. Only Google\'s Maps link is kept, never its details: it raises the find\'s or listing\'s confidence and never counts as a source we keep.'],
        'po_claude_prose' => ['Paid backup: Claude reads pages when Gemini can\'t', 'number', 0.0, 0, 200, 1, 'dollars a month', 'Off at 0. A director\'s override (decisions/0163, the source map): when page reading is on Gemini\'s free tier and Gemini can\'t answer (its day\'s allowance, a hold, no key), Claude (Haiku) reads the waiting pages until this month\'s dollars are used, inside the AI cap. Each call is kept on AI use as the backup.'],
        'places_monthly' => ['Google checks a month (the most)', 'number', 1000, 0, 10000, 100, 'checks', 'The Google Places check (decisions/0085), about 3 cents each. In the service register: it started from the worker\'s GOOGLE_PLACES_MONTHLY on Railway, recorded once, and is the one number now (Release A). The site stops at 90% of it and spreads it over the month; 0 stops the checks (Jason, 2026-11-01).'],
        'audit_share' => ['Guides read in the quarterly audit', 'number', 0.1, 0.05, 0.5, 0.05, 'share of live guides (5 to 40)', 'GuideAudits.'],
        // OpenStreetMap's Overpass (ServiceHealth::osmBudget; Jason, 2026-11-14). Overpass guidance for the public instances (wiki.openstreetmap.org/wiki/Overpass_API; FOSSGIS): about 10,000 queries and 1 GB a day per user. Jason, 2026-11-14: our own is a quarter of it, crawler and site together.
        'osm_daily_crawler' => ['OpenStreetMap queries a day: the crawler (retired)', 'number', 0, 0, 0, 50, 'queries', 'Retired 2026-11-19: we no longer use OpenStreetMap\'s public Overpass servers (its community\'s guidance, community.openstreetmap.org/t/150369, about 50 requests a day for an app; Jason: \"Stop entirely\"). Our own copy (Geofabrik, weekly) is what we read. Held at 0.'],
        'osm_daily_site' => ['OpenStreetMap queries a day: the site (retired)', 'number', 0, 0, 0, 50, 'queries', 'Retired 2026-11-19: we no longer use OpenStreetMap\'s public Overpass servers (its community\'s guidance, community.openstreetmap.org/t/150369, about 50 requests a day for an app; Jason: \"Stop entirely\"). Our own copy (Geofabrik, weekly) is what we read. Held at 0.'],
        'osm_daily_mb' => ['OpenStreetMap downloads a day, crawler and site together', 'number', 250, 10, 1000, 10, 'MB', 'A quarter of the public guidance (about 1 GB), split like the queries (crawler 60%, site 40%).'],
        'osm_cache_days' => ['A town and kind OpenStreetMap answered isn\'t asked again for', 'number', 90, 7, 365, 1, 'days', 'The crawler\'s town-and-kind asks (crawl_osm_asks) and the site\'s nearby spots (open_place_asks). Jason, 2026-11-14: quarterly.'],
        'osm_per_minute_crawler' => ['OpenStreetMap queries a minute at most: the crawler (retired)', 'number', 0, 0, 0, 1, 'a minute', 'Retired 2026-11-19: we no longer use OpenStreetMap\'s public Overpass servers (its community\'s guidance, community.openstreetmap.org/t/150369, about 50 requests a day for an app; Jason: \"Stop entirely\"). Our own copy (Geofabrik, weekly) is what we read. Held at 0.'],
        'osm_per_minute_site' => ['OpenStreetMap queries a minute at most: the site (retired)', 'number', 0, 0, 0, 1, 'a minute', 'Retired 2026-11-19: we no longer use OpenStreetMap\'s public Overpass servers (its community\'s guidance, community.openstreetmap.org/t/150369, about 50 requests a day for an app; Jason: \"Stop entirely\"). Our own copy (Geofabrik, weekly) is what we read. Held at 0.'],
        'paid_spread' => ['Google checks and web searches spread evenly through the day', 'number', 1, 0, 1, 1, '1 on, 0 off', 'api/crawl/usage.php (Jason, 2026-11-14): on, a run may use only the day\'s share up to now, so the day\'s allowance lasts until midnight; off, the day\'s share may go in the first runs.'],
        // the service register's caps and paces (decisions/0163 §1, §6: "each cap moves into the register and the old place
        // reads from it"; Release A). Each starts from today's value, found where it was (FOUND) and recorded once.
        'ai_month_usd' => ['Claude: the most the site\'s AI may cost in a month', 'number', 40.0, 0.0, 500.0, 1.0, 'dollars', 'AiClient::capUsd (was AI_MONTHLY_CAP_USD in .env, decisions/0163 §7: $40). Every AI feature stops for the month at it; each feature also has its own runs a day on AI use.'],
        'ses_month_usd' => ['Amazon SES: the most bulk email may cost in a month', 'number', 10.0, 0.0, 500.0, 1.0, 'dollars', 'SesMail (was SES_MONTHLY_CAP_USD in .env). Bulk mail stops at it; notices and receipts keep going.'],
        'websearch_monthly' => ['Web searches a month (Tavily)', 'number', 0, 0, 1000000, 100, 'searches', 'api/crawl/usage.php (was the worker\'s SEARCH_MONTHLY on Railway; the site\'s number wins now). 0 stops web search.'],
        'websearch_stop_pct' => ['Web search stops at', 'number', 90, 10, 100, 1, '% of the month\'s searches', 'api/crawl/usage.php (was WEBSEARCH_STOP_PCT in .env), and it\'s paced over the month.'],
        'places_stop_pct' => ['Google checks stop at', 'number', 90, 10, 100, 1, '% of the month\'s checks', 'api/crawl/usage.php (was PLACES_STOP_PCT in .env), and they\'re paced over the month.'],
        'start_gate_runs' => ['Start the system: watched until this many good runs', 'number', 2, 1, 50, 1, 'runs', 'Start the system confirms every workflow and task is operational first, then switches them on in order without waiting (Jason, 2026-11-17; decisions/0163 §10.2\'s note). Each one on is then watched by itself until it has had this many good runs, or the hours below have passed: a service issue that holds it, running out of budget, or more than one run in ten failing stops that one alone, with its fixes on the card.'],
        'start_gate_hours' => ['Start the system: or watched for this long', 'number', 6, 1, 168, 1, 'hours', 'The other way out of the watch: this many hours since it came on, clean (no service issue holding it, nothing out of budget, no more than one run in ten failing), even with fewer good runs. Nothing waits for it: the queue has gone on already.'],
        'osm_ramp_days' => ['After a ban lifts, OpenStreetMap stays at half for', 'number', 14, 0, 60, 1, 'days', 'While any OpenStreetMap server has an open ban (ServiceHealth), and this many days after the last one is resolved, the daily queries above are halved.'],
        // Release B (decisions/0163 §8 stage 7, §11): the new services' caps and paces, in the register
        'gemini_daily' => ['Gemini\'s calls a day (free tier)', 'number', 1000, 0, 1500, 10, 'calls', 'Google AI Studio\'s free allowance (about 1,000 a day on Flash and Flash-Lite). When it\'s used the work waits until tomorrow; no fallback to Claude (0163 §11).'],
        // caps that were in code (Release A's leftover, 0163 stage 2): now in the register, same numbers
        'osm_site_per_hour' => ['OpenStreetMap asks from the site, an hour (retired)', 'number', 0, 0, 0, 5, 'asks', 'Retired 2026-11-19: we no longer use OpenStreetMap\'s public Overpass servers (its community\'s guidance, community.openstreetmap.org/t/150369, about 50 requests a day for an app; Jason: \"Stop entirely\"). Our own copy (Geofabrik, weekly) is what we read. Held at 0.'],
        'osm_verify_per_hour' => ['OpenStreetMap checks of a find, an hour', 'number', 40, 5, 300, 5, 'asks', 'Deep look\'s checks of a find against OpenStreetMap, an hour, counted with the site\'s asks.'],
        'ors_matrix_daily' => ['openrouteservice travel-time asks a day', 'number', 400, 10, 2500, 10, 'asks', 'Its free plan allows 500 matrix asks a day; this keeps a margin.'],
        'gemini_per_minute' => ['Gemini\'s calls a minute', 'number', 15, 1, 30, 1, 'calls', 'The free tier\'s rate (about 15 a minute); the day\'s calls are also spread through the day.'],
        'gemini_reserve_pct' => ['Gemini held for visitors waiting', 'number', 20, 0, 50, 5, '%', 'Each day\'s share held back for on-demand work (W5, Find it, a search): background work stops before it (0163 §11).'],
        'supabase_rows' => ['Vectors kept in Supabase, at most', 'number', 400000, 0, 2000000, 10000, 'rows', 'Its free tier\'s database (500 MB): the vector index (0164 §5.2) stops adding at this many rows.'],
        'supabase_daily' => ['Supabase writes a day', 'number', 5000, 0, 50000, 100, 'rows', 'The vector index\'s upkeep, paced.'],
        // the worker's per-run ceilings, from the site's plan (0163 §1: "the worker takes its limits from the site's plan")
        'worker_searches_per_run' => ['Web searches a crawler run, at most', 'number', 10, 0, 100, 1, 'searches', 'The month\'s allowance and its pace still apply (usage.php); this is the most one run may make.'],
        'worker_places_per_run' => ['Google checks a crawler run, at most', 'number', 5, 0, 100, 1, 'checks', 'The month\'s allowance and its pace still apply; this is the most one run may make.'],
        'worker_osm_per_run' => ['OpenStreetMap asks a crawler run, at most', 'number', 5, 0, 50, 1, 'asks', 'About 1,500 a day over 288 runs; the daily and per-minute caps still apply.'],
        'load_towns_per_run' => ['Towns loaded a run (open datasets)', 'number', 3, 0, 50, 1, 'towns', 'Overture and Foursquare by town box (stage 8): each town\'s finds go through the one identity check and the recheck before the next.'],
        'geofabrik_days' => ['Geofabrik: a state\'s file every', 'number', 7, 1, 60, 1, 'days', 'OpenStreetMap\'s state extracts (Arizona, New Mexico), downloaded by the Railway worker.'],
        // W0 Indexer (decisions/0164 §1.4's note, Jason 2026-11-17): its batches to the site and its reading, settings not code
        'indexer_found_batch' => ['W0 Indexer: found URLs a batch', 'number', 250, 25, 500, 25, 'URLs', 'api/crawl/index.php: the indexer writes what it finds to the site in batches, so the host sees a few requests, not a stream (the Bluehost bot check). A 409, 413 or slow answer halves it; an hour of clean answers grows it back to this.'],
        'indexer_page_batch' => ['W0 Indexer: read pages a batch', 'number', 25, 5, 100, 5, 'pages', 'Read pages\' answers (status, fingerprint, title) a batch; their links go as found URLs.'],
        'indexer_batch_kb' => ['W0 Indexer: a batch\'s size at most', 'number', 100, 20, 120, 5, 'KB', 'Under ModSecurity\'s usual 128 KB limit on a request without files, wrapped links included.'],
        'indexer_gap_s' => ['W0 Indexer: one batch every, at least', 'number', 10, 2, 120, 1, 'seconds', 'About 1,500 URLs a minute at most. A 409, 413 or slow answer doubles the gap; it comes back down after an hour clean.'],
        'indexer_batch_max' => ['W0 Indexer: a batch never grows past', 'number', 500, 250, 500, 25, 'URLs', 'The ceiling for found URLs when the site answers fast and clean.'],
        'indexer_per_host' => ['W0 Indexer: pages of one site handed out at a time', 'number', 3, 1, 10, 1, 'pages', 'Spread across sites; each site\'s own pace still holds (1.5 s at least, or its robots.txt Crawl-delay, R18).'],
        'indexer_max_depth' => ['W0 Indexer: links away from a trusted source', 'number', 3, 1, 10, 1, 'links', 'Reading priority (0164 §1.4): URLs further than this from an official or first-party site, or a promoted page, wait (logged, never dropped).'],
        'indexer_link_net' => ['W0 Indexer: links on one page before it\'s flagged', 'number', 2000, 500, 20000, 100, 'links', 'The safety net for a broken page: past this its links are logged over several passes and the page goes to Pulse; nothing is dropped.'],
        'indexer_compress_days' => ['W0 Indexer: a URL never read, kept in full for', 'number', 180, 30, 720, 30, 'days', 'The size guard: after this, a URL never read and not linked from anything promoted keeps only its hash, so it\'s still recognised when found again.'],
        'indexer_frontier_mb' => ['W0 Indexer: the frontier\'s size before Pulse asks', 'number', 1024, 100, 20000, 100, 'MB', 'Bluehost\'s database is shared: past this, Pulse asks. If it outgrows it, the frontier moves to the worker\'s side (Railway or Supabase, 0164 §1.4).'],
        'indexer_month_usd' => ['W0 Indexer: Railway a month, at most', 'number', 5, 1, 25, 1, 'dollars', 'W0\'s budget line (0163 §1): its always-on time on Railway by our count (INDEXER_USD_HOUR in .env, about $0.005 an hour). At it the indexer sleeps until the 1st.'],
        // the pre-flight's query timings (decisions/0163 §10's note; Gemini's review, 2026-11-17): the heaviest queries, timed each night against these
        'pf_ms_radius' => ['Pre-flight: a radius search may take', 'number', 800, 50, 20000, 50, 'ms', 'Listings within 50 miles of a town (the search\'s "near"): past it, the pre-flight\'s line is red, as the data grows.'],
        'pf_ms_rollup' => ['Pre-flight: a zone roll-up may take', 'number', 1500, 50, 30000, 50, 'ms', 'Live listings counted by geo-hub (the hubs\' readiness and counts).'],
        'pf_ms_graph' => ['Pre-flight: a graph walk may take', 'number', 500, 20, 20000, 20, 'ms', 'Two steps out from the best-linked record (0164 §4.4: walks stay small, bounded queries).'],
        'pf_slow_ms' => ['Pre-flight: a check slower than this is marked slow', 'number', 5000, 500, 60000, 500, 'ms', 'Marked on its line in amber with its time, listed by "Show the slow ones" and in the report, to come back to why (Jason, 2026-11-17). Not a failure.'],
        'pf_ms_index' => ['Pre-flight: an index lookup may take', 'number', 100, 5, 5000, 5, 'ms', 'One address in W0\'s frontier by its hash.'],
        'pf_fresh_mins' => ['Start the system: a service line older than this is tested again', 'number', 60, 5, 1440, 5, 'minutes', 'Before Start confirms a workflow or task, the outside services and workers it depends on are tested again when their pre-flight line is older than this, then its source-map needs always (decisions/0171). Lower is fresher and slower.'],
        // the System Control Board's ledger (decisions/0171 §4): every worker call and the records it touched, kept this long
        'control_ledger_days' => ['Control Board: each worker call is kept for', 'number', 90, 7, 400, 1, 'days', 'One row per call to /api/crawl/* and /api/video/*: what it received, stored, updated, sent to Review and refused. Trimmed in small batches.'],
        'control_records_days' => ['Control Board: the records each call touched are kept for', 'number', 30, 1, 400, 1, 'days', 'The ids of the listings and Review items a call stored or changed, so a run opens to what it brought in.'],
        // decisions/0171 §5 and §7 (Jason, 2026-11-18, release 3c): the system tries its own fix first; a trip goes to an admin
        'control_fix_tries' => ['Control Board: the system\'s own tries before a problem is a trip', 'number', 3, 1, 20, 1, 'tries', 'A crash (a server error, a failed run or job, a worker gone quiet) is a Warning while the system tries again by itself. After this many tries, or the time below, whichever comes first, it\'s a Trip: it goes to an admin, the system stops what it holds, and only an admin\'s Resolve clears it. A refusal or running out of budget trips at once.'],
        'w0_block_share' => ['W0: sites blocking us become a warning past this share of the day\'s reads', 'number', 5, 1, 50, 1, '%', 'Sites that refuse W0 are held and shown as one quiet notice. Past this share of the sites read in a day (with 20 read at least), it\'s a warning: that many points at our side (our pace, our address or how we ask).'],
        'control_pulse_ms' => ['Control Board: Pulse is slow when gathering its rows takes longer than', 'number', 2000, 200, 20000, 100, 'ms', 'The middle of its last 20 opens of the admin\'s Pulse. Over it, a warning on the board (never a trip): the fix is the Pulse store, a communications build.'],
        'control_crit_requests' => ['Control Board: a critical duty fails after this many server errors in 5 minutes', 'number', 3, 1, 50, 1, 'requests', 'Search, sign-in and payments (the board\'s sensor): this many visitors\' requests answered with a server error in the watch\'s 5 minutes, or a failed test, is the part failing, which trips at once (it\'s critical). Fewer are kept on the part\'s row as a notice.'],
        'control_fix_minutes' => ['Control Board: the system\'s own time to fix a problem before it\'s a trip', 'number', 60, 5, 1440, 5, 'minutes', 'The other limit on the system\'s own tries: from the first failure, after this long it\'s a Trip even if fewer tries were made (a back-off can wait hours between tries).'],
        'irs_floor_pct' => ['IRS load: keep last month\'s copy when this month\'s rows fall below', 'number', 90, 50, 100, 5, '% of last month', 'A month\'s IRS load that brings fewer rows than this share of last month\'s deletes nothing: last month\'s copy is kept and the load waits for a person (the control board\'s build, 02 row 38b; Jason, 2026-11-21: "Fix in the build"; the number marked des).'],
        'control_slow_factor' => ['Control Board: slow means this many times its usual time', 'number', 3, 1.5, 20, 0.5, '× usual', 'A worker\'s call or a site job is slow when it takes longer than its usual time (the middle of its last 50 calls, or 20 runs) times this, and at least a second for a call or a minute for a job. Slow is a Warning.'],
        // decisions/0171 §2.5 (release 4a; the session manager, 2026-11-18: a setting, not a constant)
        'control_readings_days' => ['Control Board: how long the historian keeps every reading', 'number', 30, 7, 90, 1, 'days', 'Each process\'s readings (its state, and each hour\'s counts) are kept this long for the trends; after that, one row per reading per day is kept 400 days.'],
        'control_maint_hours' => ['Control Board: how long Take out for maintenance keeps a process out', 'number', 2, 1, 72, 1, 'hours', 'A row\'s Take out for maintenance brings the process back by itself after this long (after its check). The Maintenance panel sets any other Back by.'],
        'control_slow_trips' => ['Control Board: no longer used (slow is never a trip)', 'number', 3, 1, 20, 1, 'times', 'Retired (Jason, 2026-11-19: "no dead ends, system fixes first"): a slow call or job is a warning that clears itself and never stops anything. Kept so nothing set earlier is lost.'],
        'control_records_per_call' => ['Control Board: records kept from one call, at most', 'number', 500, 0, 5000, 50, 'records', 'Beyond this, the call keeps the count only. A bulk load of thousands is counted, not listed.'],
        'licence_lookups_daily' => ['Licence lookups a day (a registry)', 'number', 200, 0, 2000, 10, 'lookups', 'Arizona Corporation Commission and New Mexico Secretary of State lookups, a few seconds apart; only where their terms allow it (W14 records the real use).'],
    ];

    /**
     * Where each of the register's caps was before it moved here (decisions/0163 §1): gate => [[env|state, its name, in
     * words]]. Until it's recorded, a reader gets that value, so nothing changes the day the register arrives; found()
     * records it once (Operations::seed), and from then the register is the one place.
     */
    public const FOUND = [
        'ai_month_usd' => [['env', 'AI_MONTHLY_CAP_USD', 'AI_MONTHLY_CAP_USD in .env']],
        'ses_month_usd' => [['env', 'SES_MONTHLY_CAP_USD', 'SES_MONTHLY_CAP_USD in .env']],
        'websearch_monthly' => [['state', 'websearch_monthly', 'SEARCH_MONTHLY on Railway, as the worker last sent it']],
        'places_monthly' => [['state', 'places_monthly', 'GOOGLE_PLACES_MONTHLY on Railway, as the worker last sent it']],
        'websearch_stop_pct' => [['env', 'WEBSEARCH_STOP_PCT', 'WEBSEARCH_STOP_PCT in .env']],
        'places_stop_pct' => [['env', 'PLACES_STOP_PCT', 'PLACES_STOP_PCT in .env']],
    ];

    public static function get(PDO $pdo, string $key)
    {
        $g = self::GATES[$key] ?? null;
        if (!$g) return null;
        if ($g[1] === 'rule') return $g[2];
        if ($g[1] === 'switch') { require_once __DIR__ . '/AiClient.php'; return (int) AiClient::feature($pdo, $key)['per_day']; }
        $s = self::stored($pdo, $key);
        if ($s === null && ($f = self::found($pdo, $key)) !== null) $s = ['v' => $f[0]];   // not recorded yet: its old place's value
        return $s !== null ? (is_float($g[2]) ? (float) $s['v'] : (int) $s['v']) : $g[2];
    }

    /** A moved cap's value where it was before (FOUND): [value, where] or null. */
    public static function found(PDO $pdo, string $key): ?array
    {
        foreach (self::FOUND[$key] ?? [] as [$kind, $name, $words]) {
            if ($kind === 'env') { $v = getenv($name); if (($v === false || $v === '') && is_array($_ENV) && isset($_ENV[$name])) $v = (string) $_ENV[$name]; }
            else { try { require_once __DIR__ . '/SearchIndex.php'; $v = SearchIndex::state($pdo, $name); } catch (Throwable $e) { $v = ''; } }
            if ($v !== false && $v !== '' && is_numeric($v) && ($kind === 'env' || (float) $v > 0)) return [(float) $v, $words];
        }
        return null;
    }

    /** Records a moved cap's found value once, as "found" (no person): true when it recorded one. */
    public static function recordFound(PDO $pdo, string $key): bool
    {
        $g = self::GATES[$key] ?? null;
        if (!$g || self::stored($pdo, $key) !== null) return false;
        $f = self::found($pdo, $key);
        $v = $f !== null ? $f[0] : $g[2];
        $v = is_float($g[2]) ? round((float) $v, 2) : (int) round((float) $v);
        $v = max($g[3], min($g[4], $v));
        require_once __DIR__ . '/SearchIndex.php';
        SearchIndex::setState($pdo, 'gate:' . $key, json_encode(['v' => $v, 'by' => 0, 'at' => date('Y-m-d H:i'), 'found' => $f !== null ? $f[1] : 'its default (nothing was set before)']));
        self::$memo[$key] = null; unset(self::$memo[$key]);
        self::log($pdo, $key, null, (string) $v, 0, 'Found: ' . ($f !== null ? $f[1] : 'its default'));
        return true;
    }

    /** The history of a gate's changes, newest first: [[old, new, by, at, note]]. */
    public static function history(PDO $pdo, string $key, int $n = 10): array
    {
        try {
            self::ensureLog($pdo);
            $st = $pdo->prepare('SELECT old_v, new_v, by_id, at, note FROM ' . Database::t('threshold_log') . ' WHERE gate = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $n)));
            $st->execute([$key]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }

    private static function ensureLog(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        require_once __DIR__ . '/AdminTables.php';
        AdminTables::make($pdo, ['threshold_log' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, gate VARCHAR(40) NOT NULL, old_v VARCHAR(40) NULL, new_v VARCHAR(40) NULL,
            by_id INT UNSIGNED NULL, at DATETIME NOT NULL, note VARCHAR(200) NOT NULL DEFAULT \'\', KEY gate_at (gate, id))']);
        $done = true;
    }

    private static function log(PDO $pdo, string $key, ?string $old, ?string $new, int $uid, string $note = ''): void
    {
        try {
            self::ensureLog($pdo);
            $pdo->prepare('INSERT INTO ' . Database::t('threshold_log') . ' (gate, old_v, new_v, by_id, at, note) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$key, $old, $new, $uid ?: null, date('Y-m-d H:i:s'), mb_substr($note, 0, 200)]);
        } catch (Throwable $e) { error_log('threshold log: ' . $e->getMessage()); }
    }

    /** What's stored for a tuned gate: ['v', 'by', 'at'] or null (the default). */
    private static array $memo = [];

    public static function stored(PDO $pdo, string $key): ?array
    {
        if (array_key_exists($key, self::$memo)) return self::$memo[$key];
        try { require_once __DIR__ . '/SearchIndex.php'; $j = json_decode(SearchIndex::state($pdo, 'gate:' . $key), true); } catch (Throwable $e) { $j = null; }
        return self::$memo[$key] = is_array($j) && isset($j['v']) ? $j : null;
    }

    /** A director changes a gate. Returns the value kept. */
    public static function set(PDO $pdo, string $key, string $value, int $uid)
    {
        $g = self::GATES[$key] ?? throw new InvalidArgumentException('That isn\'t a gate.');
        if ($g[1] === 'rule') throw new InvalidArgumentException('That one is a standard: it changes only by a decision, not here.');
        $value = trim(str_replace(['%', ','], ['', '.'], $value));
        if (!is_numeric($value)) throw new InvalidArgumentException('Enter a number.');
        $v = is_float($g[2]) ? round((float) $value, 2) : (int) round((float) $value);
        if ($v < $g[3] || $v > $g[4]) throw new InvalidArgumentException($g[0] . ': between ' . $g[3] . ' and ' . $g[4] . '.');
        if ($key === 'category_auto' && $v <= (int) self::get($pdo, 'category_review')) throw new InvalidArgumentException('It has to stay above the review line (' . self::get($pdo, 'category_review') . ').');
        if ($key === 'category_review' && $v >= (int) self::get($pdo, 'category_auto')) throw new InvalidArgumentException('It has to stay below the auto line (' . self::get($pdo, 'category_auto') . ').');
        if ($key === 'search_publish' && $v <= (float) self::get($pdo, 'search_may_help')) throw new InvalidArgumentException('Publishing has to stay above "May also help" (' . self::get($pdo, 'search_may_help') . ').');
        if ($key === 'search_may_help' && $v >= (float) self::get($pdo, 'search_publish')) throw new InvalidArgumentException('"May also help" has to stay below publishing (' . self::get($pdo, 'search_publish') . ').');
        if ($g[1] === 'switch') {
            require_once __DIR__ . '/AiClient.php';
            $f = AiClient::feature($pdo, $key);
            AiClient::setFeature($pdo, $key, ['enabled' => $f['enabled'], 'min_score' => $v], $uid);
        } else {
            require_once __DIR__ . '/SearchIndex.php';
            $was = self::stored($pdo, $key);
            SearchIndex::setState($pdo, 'gate:' . $key, json_encode(['v' => $v, 'by' => $uid, 'at' => date('Y-m-d H:i')]));
            unset(self::$memo[$key]);
            self::log($pdo, $key, $was !== null ? (string) $was['v'] : null, (string) $v, $uid);
        }
        return $v;
    }

    /** Back to its default (a tuned gate). */
    public static function reset(PDO $pdo, string $key, int $uid): void
    {
        $g = self::GATES[$key] ?? throw new InvalidArgumentException('That isn\'t a gate.');
        if ($g[1] === 'rule') return;
        if ($g[1] === 'switch') { self::set($pdo, $key, (string) $g[2], $uid); return; }
        $was = self::stored($pdo, $key);
        if (isset(self::FOUND[$key])) {   // a register cap: Reset goes back to its default, kept (its old place is retired)
            require_once __DIR__ . '/SearchIndex.php';
            SearchIndex::setState($pdo, 'gate:' . $key, json_encode(['v' => $g[2], 'by' => $uid, 'at' => date('Y-m-d H:i'), 'reset' => 1]));
        } else $pdo->prepare('DELETE FROM search_index_state WHERE k = ?')->execute(['gate:' . $key]);
        unset(self::$memo[$key]);
        self::log($pdo, $key, $was !== null ? (string) $was['v'] : null, (string) $g[2], $uid, 'Reset to its default');
    }
}
