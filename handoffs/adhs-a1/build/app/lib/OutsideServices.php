<?php
// app/lib/OutsideServices.php — every outside company the site runs on, in one place (decisions/0152; Jason, 2026-11-11:
// "I dont like things all over the place … they need to be consolidated into a easy to find one place", "by company").
// One card per company: what we use it for (its parts, each checked), what it costs (its plan, this month's use and
// cost from Services, how its pricing and fees work), its settings (the .env names, never the values) and its links.
// Read by System Configuration → Outside services. A new outside service is added here once.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class OutsideServices
{
    /**
     * key => [name, what we use it for (one line), pricing and fees in plain words, links [[label, url]],
     *         the Services::all() keys whose use and cost it carries, the Services::PLANS key of its flat plan ('' none)].
     * {bucket} and {region} in a link fill from .env. The parts (each checked) are in parts().
     */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const COMPANIES = [
        'cloudflare' => ['Cloudflare', 'File storage (R2) and the captcha on sign-in (Turnstile).',
            'R2: storage $0.015 a GB-month after 10 GB free; uploads and changes $4.50 a million after 1 million free; views and plays $0.36 a million after 10 million free; nothing for downloads (decisions/0151). Turnstile: free.',
            [['Dashboard', 'https://dash.cloudflare.com/'], ['R2 buckets', 'https://dash.cloudflare.com/?to=/:account/r2/overview'], ['Turnstile', 'https://dash.cloudflare.com/?to=/:account/turnstile'], ['Billing', 'https://dash.cloudflare.com/?to=/:account/billing']],
            [], ''],
        'aws' => ['Amazon Web Services', 'Email (SES), tables and forms (Textract), and file storage until the move to R2 is finished.',
            'SES: about $0.10 per 1,000 emails. Textract: per page read. S3: storage $0.023 a GB-month, and about $0.09 a GB every time a file is downloaded after 100 GB free a month (why files moved to R2). Billing lookups cost about a cent each.',
            [['Console', '{console}'], ['Billing', 'https://console.aws.amazon.com/billing/home'], ['SES', 'https://console.aws.amazon.com/ses/home#/get-set-up'], ['The site\'s key (IAM)', 'https://us-east-1.console.aws.amazon.com/iam/home#/users'], ['S3', 'https://s3.console.aws.amazon.com/s3/home']],
            ['ses'], ''],
        'anthropic' => ['Anthropic (Claude)', 'AI Assist, Find it, writing guides and journeys, reading business cards.',
            'Per word read and written (tokens); each answer\'s cost is worked out as it comes back. The site stops all AI at its monthly cap (AI use has each feature\'s own limit).',
            [['Console', 'https://console.anthropic.com/'], ['Usage', 'https://console.anthropic.com/usage'], ['Keys', 'https://console.anthropic.com/settings/keys'], ['Limits', 'https://console.anthropic.com/settings/limits']],
            ['claude'], ''],
        'google' => ['Google Cloud', 'Checking listings against Google Places, reading menus (Document AI), and Continue with Google.',
            'Places: about $0.032 a check, paced and stopped by the site, with a $10 budget alert at Google. Document AI: per page. Sign-in: free.',
            [['Console', 'https://console.cloud.google.com/'], ['Billing', 'https://console.cloud.google.com/billing'], ['Credentials', 'https://console.cloud.google.com/apis/credentials'], ['Document AI', 'https://console.cloud.google.com/ai/document-ai']],
            ['places'], ''],
        'tavily' => ['Tavily', 'The crawler\'s web search.',
            'A monthly allowance of searches on its plan; the site stops at its stop line and paces it. Keep PAYGO and auto-upgrade off in its dashboard.',
            [['Dashboard', 'https://app.tavily.com/']],
            ['websearch'], ''],
        'railway' => ['Railway', 'Runs the crawler worker every 5 minutes, and W0 Indexer all the time (it sleeps while W0 is off).',
            'Hobby plan: $5 a month including $5 of usage; more only if the worker uses over $5 in a month.',
            [['Dashboard', 'https://railway.com/dashboard'], ['Usage', 'https://railway.com/account/usage']],
            [], 'railway'],
        'bluehost' => ['Bluehost', 'The web host: the site itself, cPanel (jobs, mailboxes, through its API), and the site\'s own email (sign-in links, notices).',
            'A flat hosting plan. Its price goes in the monthly total once it\'s set (Settings: Bluehost plan, a month).',
            [['Account', 'https://my.bluehost.com/'], ['cPanel', 'https://my.bluehost.com/hosting/app#/cpanel']],
            [], 'bluehost'],
        'quickbooks' => ['QuickBooks (Intuit)', 'Invoices, card payments, refunds and disputes for our own orders.',
            'Our subscription is paid to Intuit. Each card payment carries a processing fee, ours on our own orders; it shows in QuickBooks (not recorded here yet).',
            [['QuickBooks', 'https://qbo.intuit.com/'], ['Developer app', 'https://developer.intuit.com/app/developer/dashboard']],
            [], ''],
        'stripe' => ['Stripe', 'Card checkout on the Marketplace for businesses on Stripe (decisions/0135).',
            'Per-payment card fees are the business\'s, taken by Stripe from their sale; Traversence takes 0% (commercial.md).',
            [['Dashboard', 'https://dashboard.stripe.com/'], ['Connected accounts', 'https://dashboard.stripe.com/connect/accounts/overview']],
            [], ''],
        'square' => ['Square', 'Card checkout and catalog sync on the Marketplace for businesses on Square (decisions/0135).',
            'Per-payment card fees are the business\'s, taken by Square from their sale; Traversence takes 0%.',
            [['Developer apps', 'https://developer.squareup.com/apps']],
            [], ''],
        'namecom' => ['Name.com', 'Domains anyone buys in Shop → Domains (Domain sales, decisions/0169 §1a), and businesses\' own domains on their storefronts (decisions/0139).',
            'Each domain\'s yearly price, paid back by the buyer with our markup (DOMAIN_MARKUP_CENTS).',
            [['API settings', 'https://www.name.com/account/settings/api'], ['Domains', 'https://www.name.com/account/domain']],
            [], ''],
        'fly' => ['Fly.io', 'The edge for businesses\' own domains on their storefronts (decisions/0139).',
            'Per machine and certificate; small while few businesses use their own domain.',
            [['Dashboard', 'https://fly.io/dashboard'], ['Billing', 'https://fly.io/dashboard/personal/billing']],
            [], ''],
        'github' => ['GitHub', 'The 3-way sync between the server and OneDrive, the site checks, and the docs.',
            'Actions minutes: within the free allowance on the current plan.',
            [['The sync', 'https://github.com/degraffics/traverse-3way-sync/actions'], ['Billing', 'https://github.com/settings/billing']],
            [], ''],
        'microsoft' => ['Microsoft', 'Sign in with Microsoft, and OneDrive (the working copy the sync keeps).',
            'Sign-in: free. OneDrive: part of the Microsoft 365 subscription.',
            [['Entra (sign-in app)', 'https://entra.microsoft.com/'], ['OneDrive', 'https://onedrive.live.com/']],
            [], ''],
        'apple' => ['Apple', 'Sign in with Apple.',
            'The Apple Developer Program, $99 a year, is needed for it.',
            [['Developer account', 'https://developer.apple.com/account/resources/identifiers/list/serviceId']],
            [], ''],
        'meta' => ['Meta (Facebook)', 'Sign in with Facebook.',
            'Free.',
            [['Apps', 'https://developers.facebook.com/apps/']],
            [], ''],
        'census' => ['U.S. Census Bureau', 'Populations and place profiles (Data loads), putting listings on the map (the geocoder), and tribal nations\' boundaries (TIGER AIANNH).',
            'Free ($0), with a key for the data API. The geocoder and the TIGER files need no key; it asks for reasonable use (one address at a time, or its batch files).',
            [['Key sign-up', 'https://api.census.gov/data/key_signup.html'], ['Geocoder', 'https://geocoding.geo.census.gov/geocoder/'], ['TIGER files', 'https://www.census.gov/geographies/mapping-files/time-series/geo/tiger-line-file.html']],
            [], ''],
        'osm' => ['OpenStreetMap', 'Where places are, by kind and town, read from our own copy (Geofabrik\'s weekly state files), and the map tiles on every map. Overpass is retired (2026-11-19): its public servers are never asked.',
            'Free ($0). Its data is ODbL: always credited "© OpenStreetMap contributors", kept in its own tables, never copied into our listings (decisions/0065). Overpass: retired, on its community\'s guidance that its public servers aren\'t a backend for apps (about 50 requests a day each at most). The tiles ask for light use with a real user agent (its tile usage policy).',
            [['Why Overpass is retired (the forum)', 'https://community.openstreetmap.org/t/150369'], ['Overpass usage', 'https://wiki.openstreetmap.org/wiki/Overpass_API#Public_Overpass_API_instances'], ['Tile usage policy', 'https://operations.osmfoundation.org/policies/tiles/'], ['Licence', 'https://www.openstreetmap.org/copyright']],
            [], ''],
        'wikimedia' => ['Wikimedia (Wikipedia, Wikidata, Commons)', 'Facts about places (Wikidata), a place\'s summary and landmarks\' articles (Wikipedia), and free photos with their credit (Commons).',
            'Free ($0). Wikidata is CC0 (facts stored); Wikipedia is CC BY-SA, so cited with a link, never copied (Jason, 2026-11-01); Commons photo by photo, each with its licence and credit. One request at a time, with a user agent that names us.',
            [['Wikidata query service', 'https://query.wikidata.org/'], ['API etiquette', 'https://www.mediawiki.org/wiki/API:Etiquette'], ['Terms', 'https://foundation.wikimedia.org/wiki/Policy:Terms_of_Use']],
            [], ''],
        'gemini' => ['Google AI Studio (Gemini)', 'Reading pages into facts for the Knowledge Engine (decisions/0164 §0.8, R5; decisions/0163 §11). Not called yet.',
            'Free ($0) on its free tier, inside its rate limits; nothing private goes to the free tier (decisions/0164 R5).',
            [['AI Studio', 'https://aistudio.google.com/'], ['Keys', 'https://aistudio.google.com/apikey'], ['Rate limits', 'https://ai.google.dev/gemini-api/docs/rate-limits']],
            [], ''],
        'supabase' => ['Supabase', 'The vector index for the Knowledge Engine (decisions/0164 §5.2). Not called yet.',
            'Free ($0) on its free tier; its upkeep task sits under this card (decisions/0163 §1).',
            [['Dashboard', 'https://supabase.com/dashboard'], ['Usage', 'https://supabase.com/dashboard/org/_/usage']],
            [], ''],
        'overture' => ['Overture Maps (Places)', 'The base layer of businesses by town for Arizona and New Mexico (decisions/0164 §1.5, 0163 §12\'s lead order): name, address, phone, website, category and confidence. Loaded monthly by the Railway worker, by town box, through the one identity check.',
            'Free ($0), no key: the monthly release on its public cloud buckets. Its licence is per record: CDLA-Permissive-2.0 (Meta, Microsoft, BrightQuery, DAC, Uberall, Overture), Apache-2.0 (Foursquare), CC0 (AllThePlaces): kept and shown with credit, "Overture Maps Foundation" plus the upstream, each record\'s source and licence kept with it. Its emails field is never imported.',
            [['Overture Maps', 'https://overturemaps.org/'], ['Places schema', 'https://docs.overturemaps.org/schema/reference/places/place/'], ['Releases', 'https://docs.overturemaps.org/release/latest/'], ['Attribution', 'https://docs.overturemaps.org/attribution/']],
            [], ''],
        'foursquare' => ['Foursquare open places', 'More businesses by town, where Overture doesn\'t already carry Foursquare\'s record (decisions/0164 §1.5): counted as the same source when it does.',
            'Free ($0). Apache-2.0: keep its licence and NOTICE.txt with any data we pass on, mark changes, credit Foursquare. On Hugging Face, gated: a signed-in account must accept Foursquare\'s terms, and its token (a network secret for huggingface.co) opens the files. Until then nothing is loaded.',
            [['On Hugging Face', 'https://huggingface.co/datasets/foursquare/fsq-os-places'], ['Documentation', 'https://docs.foursquare.com/data-products/docs/access-fsq-os-places'], ['Licence', 'https://www.apache.org/licenses/LICENSE-2.0']],
            [], ''],
        'geofabrik' => ['Geofabrik (OpenStreetMap extracts)', 'OpenStreetMap for Arizona, New Mexico, Utah, Colorado and Nevada as weekly state files (Utah, Colorado and Nevada added 2026-11-19), downloaded by the Railway worker for stage 8: our only OpenStreetMap source since Overpass was retired (2026-11-19).',
            'Free ($0), no key. OpenStreetMap\'s data (ODbL): credited "© OpenStreetMap contributors", kept in its own tables, never copied into our listings (decisions/0065). One download a week per state at most; a refused download is held for a person, never retried (a free mirror instead, by hand).',
            [['US extracts', 'https://download.geofabrik.de/north-america/us.html'], ['Arizona', 'https://download.geofabrik.de/north-america/us/arizona.html'], ['New Mexico', 'https://download.geofabrik.de/north-america/us/new-mexico.html']],
            [], ''],
        'azcc' => ['Arizona Corporation Commission', 'Companies registered in Arizona (the Arizona Business Center): a match on name and address establishes a listing (decisions/0164 §1.5, §3.5).',
            'Free ($0). Public records: the facts it states kept and shown with the source. It moved to arizonabusinesscenter.azcc.gov; its terms are read before any use, and its real use (bulk data, lookup only, blocked, a claim-time check) is recorded by W14. A lookup at most every few seconds.',
            [['Arizona Business Center', 'https://arizonabusinesscenter.azcc.gov/'], ['Corporation Commission', 'https://azcc.gov/']],
            [], ''],
        'azdhs' => ['Arizona Department of Health Services (licensed facilities)', 'Arizona\'s licensed health-care facilities (AZ Care Check): a licence\'s type and status set a health listing\'s true kind, hospital, urgent care or clinic (decisions/0164 §1.5, §7.1; Jason through the CEO, 2026-11-28).',
            'Free ($0). Public records: the facts it states kept and shown with the source and the date (R9). W14 reads its robots.txt and terms first, and a person decides from them; its real use (bulk data, lookup only, blocked) is recorded on its authority source. A read at most every few seconds.',
            [['AZ Care Check', 'https://azcarecheck.azdhs.gov/s/'], ['Licensing', 'https://www.azdhs.gov/licensing/index.php']],
            [], ''],
        'nmsos' => ['New Mexico Secretary of State (businesses)', 'Companies registered in New Mexico: a match on name and address establishes a listing (decisions/0164 §1.5).',
            'Free ($0). Public records, kept with the source; its terms read before use and its real use recorded by W14. A lookup at most every few seconds.',
            [['Business search', 'https://enterprise.sos.nm.gov/search'], ['Secretary of State', 'https://www.sos.nm.gov/']],
            [], ''],
        'azlicences' => ['Arizona licence lists (liquor, contractors, county health)', 'Licences that prove a business is real (decisions/0164 §1.5): the Department of Liquor Licenses and Control, the Registrar of Contractors, Apache and Navajo counties\' health permits.',
            'Free ($0) as public records. Their sites challenge every automated visit (Cloudflare), so they\'re never read by the crawler: their lists come as files from a public-records request, loaded by hand (W14 records each as "blocked: by hand"). The TPT lookup is a claim-time check of the number an owner gives.',
            [['Liquor (DLLC)', 'https://azliquor.gov/'], ['Contractors (ROC)', 'https://roc.az.gov/'], ['Apache County', 'https://www.apachecountyaz.gov/'], ['Navajo County', 'https://www.navajocountyaz.gov/']],
            [], ''],
        'heigit' => ['openrouteservice (HeiGIT)', 'Drive times between a guide\'s places for its suggested journeys, asked once per leg and kept.',
            'Free ($0) on its standard plan, with a key: 2,000 directions a day and 40 a minute. Its routes are OpenStreetMap data: credited "© OpenStreetMap contributors".',
            [['Dashboard', 'https://account.heigit.org/'], ['Plans and limits', 'https://openrouteservice.org/plans/']],
            [], ''],
        'nws' => ['National Weather Service (NOAA)', 'The weather and alerts on each guide and place (for the place, never a visitor\'s location).',
            'Free ($0), no key. Public domain. It asks for a user agent with a contact, and caching: the site keeps each forecast fresh every 15 minutes ahead of visitors.',
            [['API docs', 'https://www.weather.gov/documentation/services-web-api'], ['Disclaimer', 'https://www.weather.gov/disclaimer']],
            [], ''],
        'az511' => ['AZ 511 (ADOT)', 'Arizona road work and closures on guides (its WZDx feed), with a link to check AZ 511.',
            'Free ($0). The work-zone feed (WZDx) needs no key; the developer API (events and alerts) takes a free key (AZ511_API_KEY) and allows 10 calls a minute. Its terms: shown with credit and a link to AZ 511; each feed read every 10 minutes at most.',
            [['AZ 511', 'https://az511.gov/'], ['Developers (key)', 'https://az511.com/developers']],
            [], ''],
        'ridb' => ['Recreation.gov (RIDB)', 'Federal recreation areas, campgrounds and facilities: the monthly load (Recreation.gov) and its links on listings.',
            'Free ($0), with a key (RIDB_API_KEY on Railway): 50 requests a minute. Public domain; credited "Recreation.gov".',
            [['RIDB docs', 'https://ridb.recreation.gov/docs']],
            [], ''],
        'nps' => ['National Park Service', 'Linked as a source: the National Register\'s records (NPGallery) on a place\'s dossier.',
            'Free ($0). Linked, not called: nothing is fetched from it.',
            [['NPGallery', 'https://npgallery.nps.gov/']],
            [], ''],
        'usgs' => ['USGS (The National Map, GNIS)', 'The maps\' Terrain and Satellite layers (The National Map\'s tiles), and official names of places, landmarks and features (the GNIS files in the monthly landmark load; the names registry linked on a place).',
            'Free ($0), no key. Public domain, credited "USGS The National Map". Its tiles load in visitors\' browsers (up to zoom 16); the GNIS files by state, read once a year at most.',
            [['GNIS', 'https://www.usgs.gov/tools/geographic-names-information-system-gnis'], ['Downloads', 'https://www.usgs.gov/the-national-map-data-delivery']],
            [], ''],
        'cms' => ['NPI Registry (CMS)', 'Health providers\' names, addresses and phones: the monthly file, the second look and identity checks.',
            'Free ($0), no key. Public domain (an official registry). The monthly file from CMS; the API politely, one at a time.',
            [['NPI Registry', 'https://npiregistry.cms.hhs.gov/'], ['API help', 'https://npiregistry.cms.hhs.gov/api-page'], ['Monthly files', 'https://download.cms.gov/nppes/NPI_Files.html']],
            [], ''],
        'irs' => ['IRS (exempt organizations)', 'Confirms a nonprofit exists (the monthly EO BMF file); its search linked on a listing\'s identity record.',
            'Free ($0), no key. Public domain. Evidence only: never shown, never creates a listing.',
            [['EO BMF files', 'https://www.irs.gov/charities-non-profits/exempt-organizations-business-master-file-extract-eo-bmf'], ['Tax Exempt Organization Search', 'https://apps.irs.gov/app/eos/']],
            [], ''],
        'cpsc' => ['CPSC (SaferProducts.gov)', 'Recalls: a Marketplace item whose name matches a recall is held for a look (the Marketplace rules).',
            'Free ($0), no key. Public domain.',
            [['Recalls API', 'https://www.saferproducts.gov/'], ['CPSC recalls', 'https://www.cpsc.gov/Recalls']],
            [], ''],
        'loc' => ['Library of Congress', 'History for guides and the heritage catalog: historic newspapers (Chronicling America), photos and maps, and free photos for the Stock library.',
            'Free ($0), no key. Item by item: each one\'s rights statement decides; credited "Library of Congress". Its API limits, paced (a few requests a second at most, fewer for search).',
            [['loc.gov API', 'https://www.loc.gov/apis/'], ['Legal', 'https://www.loc.gov/legal/']],
            [], ''],
        'commoncrawl' => ['Common Crawl', 'Finds the pages a site has (its index), so the crawler reads a source without crawling it.',
            'Free ($0), no key. Its terms of use; polite use of the index (one query at a time).',
            [['Index', 'https://index.commoncrawl.org/'], ['Terms', 'https://commoncrawl.org/terms-of-use']],
            [], ''],
        'brave' => ['Brave Search', 'The crawler\'s web search when it has a Brave key instead of Tavily\'s.',
            'Its free plan or paid plan, per search; the site counts each one (about $0.005) inside the same web search allowance as Tavily (SEARCH_MONTHLY).',
            [['API dashboard', 'https://api-dashboard.search.brave.com/']],
            [], ''],
        'dbip' => ['DB-IP', 'The EU/EEA/UK address ranges for the sign-up region check (decisions/0027).',
            'Free ($0): its free country file, CC BY 4.0 (credited "IP Geolocation by DB-IP"), downloaded once a month.',
            [['Free downloads', 'https://db-ip.com/db/lite.php'], ['Licence', 'https://db-ip.com/db/lite.php']],
            [], ''],
        'pixabay' => ['Pixabay', 'Free photos for the Stock library (illustrative spots only, decisions/0143).',
            'Free ($0), with a key: 100 requests a minute; its content licence (no attribution needed, but never sold as is). Results kept 24 hours.',
            [['API docs', 'https://pixabay.com/api/docs/'], ['Licence', 'https://pixabay.com/service/license-summary/']],
            [], ''],
        'tailwind' => ['Tailwind CSS (its CDN)', 'Page styles on the older pages: the browser loads cdn.tailwindcss.com.',
            'Free ($0). Its own docs say the CDN is for development; the CSS rework (design/css-shells-brief.md) replaces it with our own file.',
            [['Docs', 'https://tailwindcss.com/docs/installation/play-cdn']],
            [], ''],
        'vimeo' => ['Vimeo', 'Videos from Vimeo in journeys, played only after Play (or with Videos allowed).',
            'Free ($0) to embed.',
            [['Vimeo', 'https://vimeo.com/']],
            [], ''],
        'onepassword' => ['1Password', 'The staff\'s logins and passwords (decisions/0155): each card\'s 1Password link opens the login there; the admin never holds a password.',
            'Its own subscription, billed by 1Password.',
            [['1Password', 'https://start.1password.com/']],
            [], ''],
    ];

    /**
     * Where each company is set up or used in our own admin (Jason, 2026-11-11: "can the configuration page for each service
     * be accessed here as well?"): [[label, /admin/<module>/<section>]], shown on its card beside the company's own links.
     */
    public const ADMIN = [
        'anthropic' => [['AI use (each feature\'s limit)', '/admin/config/ai'], ['AI Assist', '/admin/insights/aiassist'], ['Scan cards', '/admin/growth/cards']],
        'google' => [['Crawler (the Places check)', '/admin/insights/crawler'], ['Thresholds & gates', '/admin/config/thresholds']],
        'tavily' => [['Crawler (web search)', '/admin/insights/crawler'], ['Thresholds & gates', '/admin/config/thresholds']],
        'railway' => [['Crawler', '/admin/insights/crawler']],
        'bluehost' => [['Scheduled tasks', '/admin/config/scheduler'], ['Staff logins (mailboxes)', '/admin/backoffice/logins'], ['Support inbox', '/admin/pulse/mail'], ['Database updates', '/admin/config/dbupdates']],
        'quickbooks' => [['Payments', '/admin/backoffice/payments'], ['QuickBooks items', '/admin/backoffice/qb_items']],
        'stripe' => [['Gateways', '/admin/market/gateways']],
        'square' => [['Gateways', '/admin/market/gateways']],
        'namecom' => [['Custom domains', '/admin/market/domains']],
        'fly' => [['Custom domains', '/admin/market/domains']],
        'github' => [['Database updates', '/admin/config/dbupdates'], ['Test accounts (the site checks)', '/admin/config/testaccounts']],
        'aws' => [['Settings (the site\'s email)', '/admin/config/settings']],
        'cloudflare' => [['Thresholds & gates', '/admin/config/thresholds']],
        'osm' => [['Crawler (coverage, search targets)', '/admin/insights/crawler']],
        'wikimedia' => [['Crawler (landmarks)', '/admin/insights/crawler'], ['Stock library', '/admin/config/stock']],
        'heigit' => [['Scheduled tasks (Suggested journeys)', '/admin/config/scheduler#job-journeys_tick']],
        'nws' => [['Scheduled tasks (Guide weather and roads)', '/admin/config/scheduler#job-guide_live_tick']],
        'az511' => [['Scheduled tasks (Guide weather and roads)', '/admin/config/scheduler#job-guide_live_tick']],
        'ridb' => [['Crawler (Recreation.gov load)', '/admin/insights/crawler']],
        'usgs' => [['Crawler (landmarks)', '/admin/insights/crawler']],
        'cms' => [['Crawler (NPI)', '/admin/insights/crawler']],
        'irs' => [['Crawler (IRS)', '/admin/insights/crawler']],
        'loc' => [['Crawler (heritage)', '/admin/insights/crawler'], ['Stock library', '/admin/config/stock']],
        'commoncrawl' => [['Crawler (Common Crawl)', '/admin/insights/crawler']],
        'brave' => [['Crawler (web search)', '/admin/insights/crawler']],
        'pixabay' => [['Stock library', '/admin/config/stock']],
        'onepassword' => [['Vault', '/admin/config/vault']],
        'census' => [['Crawler (map points)', '/admin/insights/crawler']],
    ];

    /**
     * Every host the site, its pages and the crawler call or load, by company (Jason, 2026-11-13: "each service will be
     * searchable so I can easily find their cards"). The admin search finds a card by them; the site audit's R12 flags a
     * host the code calls that's on no card. A host matches itself and anything ending in "." + it. Hosts only linked
     * (a dashboard, a terms page) needn't be here: the card's links are counted too.
     */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const HOSTS = [
        'cloudflare' => ['r2.cloudflarestorage.com', 'challenges.cloudflare.com', 'cdnjs.cloudflare.com', 'cloudflare.com'],
        'aws' => ['amazonaws.com', 'cloudfront.net'],
        'anthropic' => ['api.anthropic.com', 'anthropic.com', 'claude.ai'],
        'google' => ['places.googleapis.com', 'googleapis.com', 'accounts.google.com', 'documentai.googleapis.com', 'www.youtube-nocookie.com', 'youtube.com', 'google.com'],
        'tavily' => ['api.tavily.com', 'tavily.com'],
        'railway' => ['railway.com', 'railway.app'],
        'bluehost' => [],
        'quickbooks' => ['api.intuit.com', 'sandbox.api.intuit.com', 'quickbooks.api.intuit.com', 'sandbox-quickbooks.api.intuit.com', 'oauth.platform.intuit.com', 'appcenter.intuit.com', 'developer.api.intuit.com'],
        'stripe' => ['api.stripe.com', 'js.stripe.com', 'checkout.stripe.com', 'connect.stripe.com', 'hooks.stripe.com', 'm.stripe.network', 'q.stripe.com', 'stripe.com'],
        'square' => ['connect.squareup.com', 'connect.squareupsandbox.com', 'web.squarecdn.com', 'sandbox.web.squarecdn.com', 'pci-connect.squareup.com', 'pci-connect.squareupsandbox.com', 'squarecdn.com'],
        'namecom' => ['api.name.com', 'api.dev.name.com'],
        'fly' => ['api.fly.io'],
        'github' => ['api.github.com', 'raw.githubusercontent.com', 'github.com'],
        'microsoft' => ['login.microsoftonline.com', 'graph.microsoft.com'],
        'apple' => ['appleid.apple.com'],
        'meta' => ['graph.facebook.com', 'www.facebook.com'],
        'census' => ['api.census.gov', 'geocoding.geo.census.gov', 'www2.census.gov', 'census.gov'],
        'osm' => ['osmfoundation.org', 'tile.openstreetmap.org', 'www.openstreetmap.org', 'openstreetmap.org'],
        'overpass' => ['overpass-api.de', 'overpass.private.coffee', 'overpass.kumi.systems', 'maps.mail.ru', 'overpass.openstreetmap.fr'],
        'wikimedia' => ['query.wikidata.org', 'www.wikidata.org', 'en.wikipedia.org', 'commons.wikimedia.org', 'upload.wikimedia.org', 'wikipedia.org', 'wikidata.org', 'wikimedia.org'],
        'gemini' => ['generativelanguage.googleapis.com'],
        'supabase' => ['supabase.co'],
        'overture' => ['overturemaps-us-west-2.s3.amazonaws.com', 'overturemaps.org'],
        'foursquare' => ['huggingface.co', 'foursquare.com'],
        'geofabrik' => ['download.geofabrik.de'],
        'azcc' => ['arizonabusinesscenter.azcc.gov', 'azcc.gov', 'ecorp.azcc.gov'],
        'azdhs' => ['azcarecheck.azdhs.gov', 'www.azdhs.gov', 'azdhs.gov'],
        'nmsos' => ['enterprise.sos.nm.gov', 'sos.nm.gov'],
        'azlicences' => ['azliquor.gov', 'roc.az.gov', 'apachecountyaz.gov', 'navajocountyaz.gov'],
        'heigit' => ['api.heigit.org', 'api.openrouteservice.org'],
        'nws' => ['api.weather.gov', 'forecast.weather.gov', 'weather.gov'],
        'az511' => ['az511.com', 'az511.gov'],
        'ridb' => ['ridb.recreation.gov', 'www.recreation.gov', 'recreation.gov'],
        'nps' => ['npgallery.nps.gov', 'developer.nps.gov', 'api.data.gov'],
        'usgs' => ['prd-tnm.s3.amazonaws.com', 'edits.nationalmap.gov', 'basemap.nationalmap.gov', 'nationalmap.gov', 'usgs.gov'],
        'cms' => ['npiregistry.cms.hhs.gov', 'download.cms.gov'],
        'irs' => ['www.irs.gov', 'apps.irs.gov', 'irs.gov'],
        'cpsc' => ['www.saferproducts.gov'],
        'loc' => ['www.loc.gov', 'loc.gov'],
        'commoncrawl' => ['index.commoncrawl.org', 'data.commoncrawl.org'],
        'brave' => ['api.search.brave.com'],
        'dbip' => ['download.db-ip.com', 'db-ip.com'],
        'pixabay' => ['pixabay.com'],
        'tailwind' => ['cdn.tailwindcss.com'],
        'vimeo' => ['player.vimeo.com'],
        'onepassword' => ['start.1password.com', '1password.com'],
    ];

    /** Its terms, and where to reach the company or its community when something's wrong: [[label, url]]. */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const CONTACT = [
        'cloudflare' => [['Support', 'https://dash.cloudflare.com/?to=/:account/support']],
        'anthropic' => [['Commercial terms', 'https://www.anthropic.com/legal/commercial-terms'], ['Support', 'https://support.anthropic.com/']],
        'google' => [['Maps Platform terms', 'https://cloud.google.com/maps-platform/terms'], ['Support', 'https://console.cloud.google.com/support']],
        'tavily' => [['Terms', 'https://tavily.com/terms']],
        'railway' => [['Help Station', 'https://station.railway.com/']],
        'census' => [['Terms of service', 'https://www.census.gov/data/developers/about/terms-of-service.html'], ['Contact', 'https://www.census.gov/about/contact-us.html']],
        'osm' => [['Copyright and licence', 'https://www.openstreetmap.org/copyright']],
        'overpass' => [['Community Forum: Help and support, tag overpass', 'https://community.openstreetmap.org/c/help/'], ['FOSSGIS (runs overpass-api.de)', 'https://www.fossgis.de/']],
        'wikimedia' => [['Wikidata: Contact the development team', 'https://www.wikidata.org/wiki/Wikidata:Contact_the_development_team'], ['Terms of use', 'https://foundation.wikimedia.org/wiki/Policy:Terms_of_Use'], ['User-Agent policy', 'https://foundation.wikimedia.org/wiki/Policy:Wikimedia_Foundation_User-Agent_Policy']],
        'heigit' => [['openrouteservice forum', 'https://ask.openrouteservice.org/'], ['Terms', 'https://openrouteservice.org/terms-of-service/']],
        'nws' => [['API: issues and questions (GitHub)', 'https://github.com/weather-gov/api/discussions'], ['Disclaimer', 'https://www.weather.gov/disclaimer']],
        'az511' => [['AZ 511 help', 'https://az511.gov/']],
        'ridb' => [['Docs and terms', 'https://ridb.recreation.gov/docs']],
        'usgs' => [['Copyrights and credits', 'https://www.usgs.gov/information-policies-and-instructions/copyrights-and-credits']],
        'cms' => [['API help', 'https://npiregistry.cms.hhs.gov/api-page']],
        'irs' => [['EO BMF', 'https://www.irs.gov/charities-non-profits/exempt-organizations-business-master-file-extract-eo-bmf']],
        'loc' => [['Ask a librarian', 'https://ask.loc.gov/'], ['Legal', 'https://www.loc.gov/legal/']],
        'commoncrawl' => [['Common Crawl group', 'https://groups.google.com/g/common-crawl'], ['Terms of use', 'https://commoncrawl.org/terms-of-use']],
        'dbip' => [['Licence (CC BY 4.0)', 'https://db-ip.com/db/lite.php']],
        'pixabay' => [['Content licence', 'https://pixabay.com/service/license-summary/'], ['Terms', 'https://pixabay.com/service/terms/']],
    ];

    /**
     * What controls part of it elsewhere in the admin, shown on its card with its state and a link there (Jason, 2026-11-13:
     * "linked [where it] is controlled by another section"): 'sources' its rows on Sources (licence, use, limits, terms
     * read: Sources::STANDING), 'tasks' the crawler's capture tasks that call it, 'jobs' the scheduled tasks, 'switches'
     * the switches. The card shows each and links there; it never holds a second copy of the control.
     */
    public const CONTROLS = [
        'osm' => ['sources' => ['openstreetmap.org'], 'tasks' => ['coverage', 'targets']],
        'wikimedia' => ['sources' => ['wikidata.org', 'wikipedia.org', 'commons.wikimedia.org'], 'tasks' => ['coverage', 'landmarks_bulk'], 'jobs' => ['landmark_photos_tick']],
        'google' => ['sources' => ['google.com'], 'tasks' => ['places'], 'jobs' => ['services_tick']],
        'tavily' => ['sources' => ['tavily.com'], 'tasks' => ['second_look', 'targets', 'refresh', 'identity', 'source', 'howto', 'learn'], 'jobs' => ['services_tick']],
        'brave' => ['tasks' => ['second_look', 'targets', 'refresh', 'identity', 'source', 'howto', 'learn']],
        'anthropic' => ['sources' => ['anthropic.com'], 'jobs' => ['ai_worker', 'guide_finds_tick', 'guide_ondemand_tick', 'heritage_tick', 'services_tick'], 'switches' => ['staging_ai']],
        'railway' => ['switches' => ['staging_crawler']],
        'irs' => ['sources' => ['irs.gov'], 'tasks' => ['irs_bulk']],
        'cms' => ['sources' => ['npiregistry.cms.hhs.gov'], 'tasks' => ['second_look', 'identity', 'npi_bulk', 'npi_town']],   // npi_town: NPI by town for Arizona and New Mexico (stage 8), with its two guards
        'supabase' => ['jobs' => ['vectors_tick', 'supabase_keepalive']], 'overture' => ['tasks' => ['overture_bulk']], 'foursquare' => ['tasks' => ['foursquare_bulk']], 'geofabrik' => ['tasks' => ['geofabrik_bulk']],
        'azcc' => ['tasks' => ['licence_lookup']], 'nmsos' => ['tasks' => ['licence_lookup']],
        'azdhs' => ['tasks' => ['w14_terms']],   // W14 reads its terms first (decisions/0164 §7.1; 2026-11-28)
        'ridb' => ['sources' => ['recreation.gov'], 'tasks' => ['rec_bulk']],
        'census' => ['sources' => ['census.gov'], 'tasks' => ['geocode', 'landmarks_bulk']],
        'usgs' => ['sources' => ['usgs.gov'], 'tasks' => ['landmarks_bulk']],
        'nws' => ['sources' => ['weather.gov'], 'jobs' => ['guide_live_tick']],
        'az511' => ['sources' => ['az511.gov'], 'jobs' => ['guide_live_tick']],
        'loc' => ['sources' => ['loc.gov'], 'tasks' => ['heritage'], 'jobs' => ['heritage_tick']],
        'commoncrawl' => ['tasks' => ['commoncrawl']],
        'heigit' => ['jobs' => ['journeys_tick']],
        'dbip' => ['jobs' => ['region_tick']],
        'github' => ['jobs' => ['audit_clock_tick'], 'switches' => ['audit_clock', 'site_health']],
        'aws' => ['jobs' => ['notice_digest', 'ses_setup_tick', 'files_tick']],
        'quickbooks' => ['jobs' => ['billing_tick', 'pay_wait_tick']],
        'namecom' => ['jobs' => ['domains_tick']],
        'fly' => ['jobs' => ['domains_tick']],
        'bluehost' => ['jobs' => ['staging_tick', 'staging_carry_tick']],
    ];

    /** The crawler's paid calls in its ledger (crawl_costs.service) that belong to each company. */
    /** The kinds, for Outside services' one filter (Jason, 2026-11-17): key => its name. A company may be more than one. */
    public const KIND_NAMES = ['storage' => 'Storage', 'email' => 'Email', 'payments' => 'Payments', 'ai' => 'AI', 'signin' => 'Sign-in',
        'data' => 'Data sources', 'domains' => 'Domains', 'workers' => 'Workers', 'hosting' => 'Hosting'];
    /** Each company's kinds (KIND_NAMES). */
    public const KINDS = [
        'cloudflare' => ['storage', 'signin'], 'aws' => ['email', 'storage'], 'anthropic' => ['ai'], 'google' => ['data', 'signin', 'ai'],
        'tavily' => ['data'], 'railway' => ['workers'], 'bluehost' => ['hosting', 'email'], 'quickbooks' => ['payments'], 'stripe' => ['payments'],
        'square' => ['payments'], 'namecom' => ['domains'], 'fly' => ['domains', 'hosting'], 'github' => ['workers', 'hosting'], 'microsoft' => ['signin', 'storage'],
        'apple' => ['signin'], 'meta' => ['signin'], 'census' => ['data'], 'osm' => ['data'], 'wikimedia' => ['data'], 'gemini' => ['ai'], 'supabase' => ['ai', 'storage'],
        'overture' => ['data'], 'foursquare' => ['data'], 'geofabrik' => ['data'], 'azcc' => ['data'], 'nmsos' => ['data'], 'azlicences' => ['data'], 'heigit' => ['data'],
        'nws' => ['data'], 'az511' => ['data'], 'ridb' => ['data'], 'nps' => ['data'], 'usgs' => ['data'], 'cms' => ['data'], 'irs' => ['data'], 'cpsc' => ['data'],
        'loc' => ['data'], 'commoncrawl' => ['data'], 'brave' => ['data'], 'dbip' => ['data'], 'pixabay' => ['data'], 'tailwind' => ['hosting'], 'vimeo' => ['hosting'], 'onepassword' => ['signin'],
    ];

    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const LEDGER = [
        'google' => ['google_places'],
        'tavily' => ['tavily', 'tavily_extract'],
        'railway' => ['railway'],
        'brave' => ['brave'],
    ];

    /**
     * What's known that the logs don't show yet: [since, tone ok|bad|info, what, link]. Taken off once it's settled or the
     * service's own counts show it (the crawler's counts by service, decisions/0160 note 2026-11-13).
     */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const NOTICES = [
        'overpass' => [['2026-11-13 16:51 UTC', 'bad', 'overpass-api.de answers 406 Not Acceptable to every crawler request from Railway (its maintainers\' ban on a user agent or address). It stays on the servers list below, held by its issue above until its daily probe answers (then it comes back by itself at half the budget for the ramp days, and Pulse says so). The crawler now asks the mirrors one kind at a time, in small queries, one at a time, and not again for a town and kind for a month. The statement for the OSM community and how to reach them are written up.', 'https://github.com/degraffics/traversence/blob/main/research/osm-overpass-2026-11-13.md']],
    ];

    /** The company a host belongs to ('' when none): the longest match wins (prd-tnm.s3.amazonaws.com is USGS, not ours). */
    public static function serviceOfHost(string $host): string
    {
        $host = strtolower(trim($host, '. ')); $best = ''; $len = 0;
        foreach (self::HOSTS as $k => $hs) foreach ($hs as $h) {
            if (($host === $h || str_ends_with($host, '.' . $h)) && strlen($h) > $len) { $best = $k; $len = strlen($h); }
        }
        return $best;
    }

    /** The words the admin search reads for a card: its name, key, hosts and linked sections. */
    public static function searchText(string $key): string
    {
        $c = self::COMPANIES[$key] ?? null; if (!$c) return '';
        $w = [$c[0], $key];   // not what it's for: "crawler" would list every service the crawler calls
        foreach (self::HOSTS[$key] ?? [] as $h) { $w[] = $h; $w[] = preg_replace('/^(www|api)\./', '', $h); }
        foreach (self::linked($key) as [$label]) $w[] = $label;
        return implode(' ', array_unique($w));
    }

    /**
     * Where it's controlled or used elsewhere, for the card and the search: [[label, url, section]]. From ADMIN and
     * CONTROLS: its rows on Sources, its crawler tasks (on the Crawler), its scheduled tasks and switches (each opens
     * its own card there).
     */
    public static function linked(string $key): array
    {
        $out = [];
        foreach (self::ADMIN[$key] ?? [] as [$l, $u]) $out[] = [$l, $u, 'admin'];
        $ctl = self::CONTROLS[$key] ?? [];
        foreach ($ctl['sources'] ?? [] as $h) $out[] = ['Sources: ' . self::sourceName($h), '/admin/sources.php#' . self::sourceAnchor($h), 'sources'];
        if (!empty($ctl['tasks']) && !array_filter($out, fn ($o) => $o[1] === '/admin/insights/crawler')) $out[] = ['Crawler', '/admin/insights/crawler', 'crawler'];
        if (!empty($ctl['jobs']) || !empty($ctl['switches'])) {
            if (!class_exists('Scheduler', false) && is_file(__DIR__ . '/Scheduler.php')) require_once __DIR__ . '/Scheduler.php';
            if (!class_exists('Switches', false) && is_file(__DIR__ . '/Switches.php')) require_once __DIR__ . '/Switches.php';
        }
        foreach ($ctl['jobs'] ?? [] as $j) $out[] = ['Scheduled task: ' . (class_exists('Scheduler', false) ? (Scheduler::JOBS[$j][0] ?? $j) : $j), '/admin/config/scheduler#job-' . $j, 'job'];
        foreach ($ctl['switches'] ?? [] as $s) $out[] = ['Switch: ' . (class_exists('Switches', false) ? (Switches::DEFS[$s][0] ?? $s) : $s), '/admin/config/switches#sw-' . $s, 'switch'];
        // the same page reached twice (an ADMIN link and a control) is shown once, the more specific label kept
        $seen = []; $keep = [];
        foreach ($out as $o) { if (isset($seen[$o[1]])) continue; $seen[$o[1]] = true; $keep[] = $o; }
        return $keep;
    }

    /** A source's name on the Sources page, and its row's anchor there (#src-openstreetmap-org). */
    public static function sourceName(string $host): string
    {
        if (!class_exists('Sources', false) && is_file(__DIR__ . '/crawler/Sources.php')) require_once __DIR__ . '/crawler/Sources.php';
        return class_exists('Sources', false) ? (string) (Sources::STANDING[$host][0] ?? $host) : $host;
    }
    public static function sourceAnchor(string $host): string { return 'src-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($host)), '-'); }

    /**
     * Is it working, from the site's and the crawler's own logs: [[what, says, tone ok|bad|info, link]]. The notices
     * known (NOTICES), the crawler's tasks that call it (their last outcome and the last 24 hours), its paid calls in the
     * crawler's ledger this month (failed ones included), and its scheduled tasks' last runs.
     */
    /** A failure's words as a person reads them: a server's error page becomes its title ("503 Service Unavailable"), never its HTML. */
    public static function plainWhy(string $w): string
    {
        $w = str_replace(['\\n', "\\'"], [' ', "'"], $w);
        if (preg_match('~<title>\s*([^<]{1,120})~i', $w, $m)) $w = (string) preg_replace('~b?[\'"]?<(!DOCTYPE|html).*$~is', trim($m[1]), $w);
        return mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', strip_tags($w))), 0, 200, '…');
    }

    public static function working(PDO $pdo, string $key): array
    {
        $out = [];
        // its open issues (ServiceHealth: each classified, its evidence, held or backing off, its case in Pulse)
        try {
            if (!class_exists('ServiceHealth', false) && is_file(__DIR__ . '/ServiceHealth.php')) require_once __DIR__ . '/ServiceHealth.php';
            if (class_exists('ServiceHealth', false)) foreach (ServiceHealth::openAll($pdo) as $si) {
                if ($si['service'] !== $key) continue;
                // the issue's thread, in a line: its title and status, linking to the issue on the card where it's acted on
                // (decisions/0176 §3; 0175 §6: the board and card are where you act); its case stays in Pulse
                require_once __DIR__ . '/Thread.php'; $sf = ServiceHealth::facts($pdo, $si);
                $out[] = [$sf['title'], Thread::plain((string) $sf['status']), ServiceHealth::holding($si) ? 'bad' : 'info', '/admin/config/register#issue-' . (int) $si['id'], null, 'keep'];
            }
        } catch (Throwable $x) {}
        foreach (self::NOTICES[$key] ?? [] as [$since, $tone, $what, $link]) $out[] = ['Since ' . $since, $what, $tone, $link];
        // the crawler's own calls to it, counted by the worker host by host (worker 2.4, decisions/0160 note 2026-11-13)
        try {
            $st = $pdo->prepare('SELECT host, SUM(calls) n, SUM(fails) f, SUM(CASE WHEN at >= NOW() - INTERVAL 1 DAY THEN calls ELSE 0 END) dn, SUM(CASE WHEN at >= NOW() - INTERVAL 1 DAY THEN fails ELSE 0 END) df, MAX(CASE WHEN fails > 0 THEN at END) bad_at, MAX(at) last_at FROM crawl_service_calls WHERE service = ? AND at >= ? GROUP BY host ORDER BY n DESC');
            $st->execute([$key, date('Y-m-01')]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $err = '';
                if ($r['bad_at']) { $e = $pdo->prepare('SELECT last_error FROM crawl_service_calls WHERE service = ? AND host = ? AND fails > 0 ORDER BY id DESC LIMIT 1'); $e->execute([$key, $r['host']]); $err = (string) $e->fetchColumn(); }
                $dn = (int) $r['dn']; $df = (int) $r['df'];
                $out[] = ['The crawler → ' . $r['host'], ($dn ? number_format($dn) . ' call' . ($dn === 1 ? '' : 's') . ' in the last 24 h, ' . ($df ? number_format($df) . ' failed' : 'none failed') : 'No calls in the last 24 h')
                    . '; this month ' . number_format((int) $r['n']) . ($r['f'] ? ', ' . number_format((int) $r['f']) . ' failed' : '') . '.'
                    . ($err !== '' ? ' Last error ' . date('M j, g:i a', strtotime((string) $r['bad_at'])) . ' (server time): ' . $err . '.' : ''),
                    $dn && $df >= $dn ? 'bad' : ($df ? 'info' : ($dn ? 'ok' : 'info')), '/admin/insights/crawler'];
            }
        } catch (Throwable $x) {}   // before its database update: the tasks and the ledger below still show
        $ctl = self::CONTROLS[$key] ?? [];
        if (!empty($ctl['tasks']) || isset(self::LEDGER[$key])) {
            if (!class_exists('CrawlService', false) && is_file(__DIR__ . '/crawler/CrawlService.php')) require_once __DIR__ . '/crawler/CrawlService.php';
            if (class_exists('CrawlService', false) && CrawlService::ready($pdo)) {
                try {
                    $tasks = CrawlService::tasks($pdo); $stats = CrawlService::taskStats($pdo, 24);
                    foreach ($ctl['tasks'] ?? [] as $t) {
                        if (!isset($tasks[$t])) continue;
                        $r = $tasks[$t]; $st = $stats[$t] ?? [];
                        $day = array_filter(['done' => (int) ($st['done'] ?? 0), 'failed' => (int) ($st['failed'] ?? 0), 'not reached' => (int) ($st['not_reached'] ?? 0), 'off' => (int) ($st['off'] ?? 0)]);
                        $last = (string) ($r['last_outcome'] ?? '');
                        $on = (int) $r['enabled'] === 1; $lw = self::plainWhy((string) ($r['last_why'] ?? ''));
                        $lastSaid = $last !== '' ? strtolower(CrawlService::OUTCOMES[$last] ?? $last) . ($lw !== '' ? ' (' . $lw . ')' : '') : '';
                        // switched off (Jason, 2026-11-19: "Not sure what it need to do?"): say so first, with its Switch on here;
                        // a failure from before it was switched off is history, not the reason it isn't running
                        $sp = null; if ($on) { require_once __DIR__ . '/Automation.php'; $sp = Automation::taskOff($pdo, $t) ?? Automation::sysOff($pdo, 'wf', (string) $r['wf']); }   // a trip pauses it; the switch stays on (decisions/0175)
                        $says = ($on ? ($sp !== null ? 'On · ' . $sp . ($lastSaid !== '' ? ' Last: ' . $lastSaid : '') : ($lastSaid !== '' ? 'Last: ' . $lastSaid : 'Not run yet')) : 'Switched off: nothing runs it until it\'s switched on.' . ($lastSaid !== '' ? ' Its last run, before that: ' . $lastSaid : ''))
                              . ($day ? '. Last 24 h: ' . implode(', ', array_map(fn ($k, $v) => $v . ' ' . $k, array_keys($day), $day)) : '') . '.';
                        $out[] = [(string) $r['wf'] . ' · ' . (string) $r['name'], $says, !$on || $sp !== null ? 'info' : ($last === 'failed' || (!empty($st['failed']) && empty($st['done'])) ? 'bad' : ($last === 'done' || $last === 'idle' ? 'ok' : 'info')),
                                  '/admin/config/control/wf-' . (string) $r['wf'] . '#task-' . $t, $on ? null : ['task_on', $t, 'Switch on']];   // the step it serves, linking to its one switch on its workflow's page (0175 §6, stage 4)
                    }
                    if (isset(self::LEDGER[$key])) {
                        $in = implode(',', array_map([$pdo, 'quote'], self::LEDGER[$key]));
                        $r = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(units), 0) u, COALESCE(SUM(ok = 0), 0) bad, COALESCE(SUM(usd_micros), 0) c FROM crawl_costs WHERE service IN ($in) AND at >= '" . date('Y-m-01') . "'")->fetch(PDO::FETCH_ASSOC) ?: [];
                        if ((int) ($r['n'] ?? 0) > 0) $out[] = ['The crawler this month', $key === 'railway' ? round((int) $r['u'] / 3600, 1) . ' hours of runs' : number_format((int) $r['u']) . ' paid calls' . ((int) $r['bad'] ? ', ' . (int) $r['bad'] . ' of them failed' : '') . ', about $' . number_format((int) $r['c'] / 1e6, 2) . ' (the ledger: failed calls included).', (int) $r['bad'] ? 'bad' : 'ok', '/admin/insights/crawler'];
                    }
                } catch (Throwable $x) { $out[] = ['The crawler', 'Couldn\'t read its logs: ' . $x->getMessage(), 'info', '']; }
            }
        }
        if (!empty($ctl['jobs'])) {
            if (!class_exists('Scheduler', false) && is_file(__DIR__ . '/Scheduler.php')) require_once __DIR__ . '/Scheduler.php';
            try {
                $runs = Scheduler::lastRuns($pdo);
                foreach ($ctl['jobs'] as $j) {
                    $name = Scheduler::JOBS[$j][0] ?? $j; $r = $runs[$j] ?? null;
                    if (!$r) { $out[] = ['Scheduled task: ' . $name, 'Hasn\'t run yet.', 'info', '/admin/config/scheduler#job-' . $j]; continue; }
                    $ok = $r['ok'] === null ? null : (int) $r['ok'] === 1;
                    $out[] = ['Scheduled task: ' . $name, 'Last run ' . date('M j, g:i a', strtotime((string) $r['started_at'])) . ' (server time): ' . ($ok === null ? 'still running or stopped' : ($ok ? 'finished' : 'stopped')) . (($r['note'] ?? '') !== '' ? ', "' . mb_strimwidth((string) $r['note'], 0, 140, '…') . '"' : '') . ((int) ($r['fails_today'] ?? 0) ? '. ' . (int) $r['fails_today'] . ' stopped in the last day.' : '.'),
                              $ok === false || (int) ($r['fails_today'] ?? 0) ? 'bad' : ($ok ? 'ok' : 'info'), '/admin/config/scheduler#job-' . $j];
                }
            } catch (Throwable $x) {}
        }
        return $out;
    }

    /**
     * Its settings owned by another section, shown (never changed) here: [[what, its value now, link]]. Its rows on Sources
     * (licence, use, limits, whether its terms were read) and its switches.
     */
    public static function controlled(PDO $pdo, string $key): array
    {
        $out = []; $ctl = self::CONTROLS[$key] ?? [];
        if (!empty($ctl['sources'])) {
            if (!class_exists('Sources', false) && is_file(__DIR__ . '/crawler/Sources.php')) require_once __DIR__ . '/crawler/Sources.php';
            foreach ($ctl['sources'] as $h) {
                try {
                    $r = Sources::rules($pdo, $h);
                    $out[] = ['Sources: ' . self::sourceName($h), (Sources::LICENCES[$r['licence']] ?? $r['licence']) . ' · ' . (Sources::USES[$r['use_rule']] ?? $r['use_rule'])
                        . (($r['limits'] ?? '') !== '' ? ' · limits: ' . $r['limits'] : '') . (($r['credit_line'] ?? '') !== '' ? ' · credit: ' . $r['credit_line'] : '')
                        . ' · terms ' . (!empty($r['terms_checked_at']) ? 'read ' . date('M j, Y', strtotime((string) $r['terms_checked_at'])) : 'not marked read yet') . ($r['from'] === 'set' ? ' (changed on Sources)' : ''),
                        '/admin/sources.php#' . self::sourceAnchor($h)];
                } catch (Throwable $x) {}
            }
        }
        if (!empty($ctl['switches'])) {
            if (!class_exists('Switches', false) && is_file(__DIR__ . '/Switches.php')) require_once __DIR__ . '/Switches.php';
            foreach ($ctl['switches'] as $sw) { try { $x = Switches::all($pdo)[$sw] ?? null; $out[] = ['Switch: ' . (Switches::DEFS[$sw][0] ?? $sw), !$x ? 'Not set' : (!empty($x['killed']) ? 'Stopped (kill switch)' : ucfirst((string) $x['state']) . ($x['state'] === 'share' ? ' (' . (int) $x['share_pct'] . '% of members)' : '')), '/admin/config/switches#sw-' . $sw]; } catch (Throwable $x) {} }
        }
        return $out;
    }

    /**
     * "How to sign in" on each card (Jason, 2026-11-11: "need a way to see credentials for outside services so login can be
     * easier"): the non-secret half only, field => [label, hint]. Passwords and keys live in the password manager
     * (decisions/0155), never here; a value that looks like one is refused. Owners and executives only. Kept in
     * staff_settings (the staff side of the data, decisions/0110), one short row a field.
     */
    public const NOTE_FIELDS = [
        'account' => ['Sign in as', 'Which account, e.g. "Root user: support@traversence.com"'],
        'vault' => ['1Password link', 'The login\'s private link: in 1Password, the item\'s ⋯ → Copy private link'],
        'how' => ['How', 'Which sign-in: e.g. "email and password", "Continue with Google", "account ID 573199225597"'],
        'step' => ['Second step', 'Where the code goes, e.g. "authenticator app on Jason\'s phone"'],
        'who' => ['Who holds it', 'e.g. "Jason, owner"'],
    ];
    /** Each company's own sign-in page (Jason, 2026-11-11: "where to sign in links to outside landing log in pages"). */
    public const LOGIN = [
        'cloudflare' => 'https://dash.cloudflare.com/login', 'aws' => 'https://signin.aws.amazon.com/console', 'anthropic' => 'https://console.anthropic.com/login',
        'google' => 'https://console.cloud.google.com/', 'tavily' => 'https://app.tavily.com/', 'railway' => 'https://railway.com/login',
        'bluehost' => 'https://my.bluehost.com/', 'quickbooks' => 'https://qbo.intuit.com/', 'stripe' => 'https://dashboard.stripe.com/login',
        'square' => 'https://squareup.com/login', 'namecom' => 'https://www.name.com/account/login', 'fly' => 'https://fly.io/app/sign-in',
        'github' => 'https://github.com/login', 'microsoft' => 'https://entra.microsoft.com/', 'apple' => 'https://developer.apple.com/account',
        'meta' => 'https://developers.facebook.com/apps/', 'census' => 'https://api.census.gov/data/key_signup.html',
        'heigit' => 'https://account.heigit.org/', 'ridb' => 'https://ridb.recreation.gov/', 'brave' => 'https://api-dashboard.search.brave.com/',
        'pixabay' => 'https://pixabay.com/accounts/login/', 'onepassword' => 'https://start.1password.com/', 'az511' => 'https://az511.com/developers',
    ];

    /** A 1Password private link: only 1Password's own addresses (the link holds no password; it opens the item for someone
     *  signed in to the vault). '' when empty or not one. */
    public static function vaultLink(string $u): string
    {
        $u = trim($u);
        return preg_match('#^https://([a-z0-9-]+\.)*1password\.(com|ca|eu)/#i', $u) ? $u : '';
    }

    /** What's known from the decisions, shown until someone saves the card's own (decisions/0122, 0151). */
    public const NOTE_KNOWN = [
        'aws' => ['account' => 'Root user: support@traversence.com (account Traversence, 573199225597); the site\'s key is the IAM user traversence-site', 'step' => 'MFA on'],
    ];

    /** A card's sign-in notes: [field => value], with 'known' => true when they're from the decisions, not yet saved. */
    public static function notes(PDO $pdo, string $key): array
    {
        $out = [];
        try {
            $st = $pdo->prepare('SELECT name, value FROM ' . Database::t('staff_settings') . ' WHERE name LIKE ?'); $st->execute(['svcnote:' . $key . ':%']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[substr((string) $r['name'], strlen('svcnote:' . $key . ':'))] = (string) $r['value'];
        } catch (Throwable) {}
        if (!$out && isset(self::NOTE_KNOWN[$key])) return self::NOTE_KNOWN[$key] + ['known' => true];
        return $out;
    }

    /** Saves a card's sign-in notes; throws when a value looks like a password or key. */
    // ---- requests we're waiting on from a company (Jason, 2026-11-17: "pulse this", for Name.com's ticket) ---------
    // Each open one is a Pulse case (outreq:<id>) until its answer is recorded. Their own table, outside_requests
    // (2026-11-18, the Control Board's 2c, as the pre-flight keeps its results): kept first in staff_settings as
    // 'outreq:<id>', squeezed into one short value, ticket #3113488 never stayed. Any kept there come over once, with
    // their ids, so their Pulse cases stay the same. Their answer goes on the Pulse case as a note, not here.

    /** Added if no request with its ticket number is there (checked each time, so a failed first try is made again). */
    public const REQ_SEED = [['namecom', 'Reseller pricing: tiers, deposit, per-ending prices (.com .net .co .us .shop .store .biz .app .ai)', '3113488', 'https://www.name.com/support', '2026-11-17 13:55:00']];

    /** Why the requests couldn't be read or saved, if they couldn't ('' when fine): shown on the card and as a Pulse alert, never silent. */
    public static string $reqError = '';

    private static bool $reqReady = false;

    /** The table, and any requests kept the old way brought over (ids kept). */
    private static function reqEnsure(PDO $pdo): void
    {
        if (self::$reqReady) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS outside_requests (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, svc VARCHAR(40) NOT NULL,
            what VARCHAR(255) NOT NULL, ticket VARCHAR(40) NOT NULL DEFAULT '', link VARCHAR(255) NOT NULL DEFAULT '', sent_at DATETIME NULL,
            by_uid INT UNSIGNED NOT NULL DEFAULT 0, closed_at DATETIME NULL, KEY svc (svc), KEY ticket (ticket)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try {
            $ins = $pdo->prepare('INSERT IGNORE INTO outside_requests (id, svc, what, ticket, link, sent_at, by_uid, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($pdo->query("SELECT name, value FROM " . Database::t('staff_settings') . " WHERE name LIKE 'outreq:%' AND name <> 'outreq:seeded'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $v = json_decode((string) $r['value'], true); $id = (int) substr((string) $r['name'], 7);
                if (!is_array($v) || $id < 1) continue;
                $at = fn ($x) => preg_match('/^\d{4}-\d\d-\d\d/', (string) $x) ? (string) $x : null;
                $ins->execute([$id, (string) ($v['s'] ?? $v['svc'] ?? ''), mb_substr((string) ($v['w'] ?? $v['what'] ?? ''), 0, 255), (string) ($v['t'] ?? $v['ticket'] ?? ''),
                    (string) ($v['l'] ?? $v['link'] ?? ''), $at($v['at'] ?? $v['sent'] ?? ''), (int) ($v['by'] ?? 0), $at($v['c'] ?? $v['closed'] ?? '')]);
            }
        } catch (Throwable $x) { error_log('outside requests, the old ones: ' . $x->getMessage()); }   // none kept the old way: nothing to bring
        self::$reqReady = true;
    }

    /** The requests: [id => [svc, what, ticket, link, sent, by, closed]], open ones only unless $all. */
    public static function requests(PDO $pdo, bool $all = false): array
    {
        try {
            self::reqEnsure($pdo);
            $read = function () use ($pdo): array {
                $out = [];
                foreach ($pdo->query('SELECT id, svc, what, ticket, link, sent_at, by_uid, closed_at FROM outside_requests ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $r)
                    $out[(int) $r['id']] = ['id' => (int) $r['id'], 'svc' => (string) $r['svc'], 'what' => (string) $r['what'], 'ticket' => (string) $r['ticket'], 'link' => (string) $r['link'],
                        'sent' => (string) ($r['sent_at'] ?? ''), 'by' => (int) $r['by_uid'], 'closed' => (string) ($r['closed_at'] ?? '')];
                return $out;
            };
            $have = $read(); $made = false;
            foreach (self::REQ_SEED as [$svc, $what, $ticket, $link, $sent]) {
                if (in_array($ticket, array_column($have, 'ticket'), true)) continue;
                try { self::store($pdo, ['svc' => $svc, 'what' => $what, 'ticket' => $ticket, 'link' => $link, 'sent' => $sent, 'by' => 0, 'closed' => '']); $made = true; }
                catch (Throwable $x) { error_log('outside requests seed ' . $ticket . ': ' . $x->getMessage()); self::$reqError = 'Couldn\'t save ticket #' . $ticket . ': ' . mb_substr($x->getMessage(), 0, 300); }
            }
            if ($made) $have = $read();
            return $all ? $have : array_filter($have, fn ($v) => $v['closed'] === '');
        } catch (Throwable $x) { error_log('outside requests: ' . $x->getMessage()); self::$reqError = 'Couldn\'t read the requests: ' . mb_substr($x->getMessage(), 0, 300); return []; }
    }

    /** Writes one: a new one when $id is 0, else that one. Returns its id. */
    private static function store(PDO $pdo, array $v, int $id = 0): int
    {
        self::reqEnsure($pdo);
        $row = [(string) $v['svc'], mb_substr((string) $v['what'], 0, 255), mb_substr((string) $v['ticket'], 0, 40), mb_substr((string) $v['link'], 0, 255),
                ((string) ($v['sent'] ?? '')) !== '' ? (string) $v['sent'] : null, (int) ($v['by'] ?? 0), ((string) ($v['closed'] ?? '')) !== '' ? (string) $v['closed'] : null];
        if ($id) { $pdo->prepare('UPDATE outside_requests SET svc = ?, what = ?, ticket = ?, link = ?, sent_at = ?, by_uid = ?, closed_at = ? WHERE id = ?')->execute(array_merge($row, [$id])); return $id; }
        $pdo->prepare('INSERT INTO outside_requests (svc, what, ticket, link, sent_at, by_uid, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($row);
        return (int) $pdo->lastInsertId();
    }

    /** A request sent to a company: what we asked, its ticket number and link. Returns its id (its Pulse case is outreq:<id>). */
    public static function addRequest(PDO $pdo, string $svc, string $what, string $ticket, string $link, int $uid): int
    {
        if (!isset(self::COMPANIES[$svc])) throw new InvalidArgumentException('No such service.');
        $what = trim(preg_replace('/\s+/', ' ', $what)); $ticket = trim(ltrim($ticket, '# ')); $link = trim($link);
        if ($what === '') throw new InvalidArgumentException('Say what you asked them.');
        if (mb_strlen($what) > 120 || mb_strlen($ticket) > 30 || strlen($link) > 90) throw new InvalidArgumentException('Keep it short: what you asked (120 characters), the ticket number (30), the link (90).');
        if ($link !== '' && !preg_match('#^https://[^\s]+$#', $link)) throw new InvalidArgumentException('The link starts https://');
        if (self::looksSecret($what . ' ' . $ticket)) throw new InvalidArgumentException('That looks like a password or key: leave those out.');
        return self::store($pdo, ['svc' => $svc, 'what' => $what, 'ticket' => $ticket, 'link' => $link, 'sent' => date('Y-m-d H:i:s'), 'by' => $uid, 'closed' => '']);
    }

    /** A Pulse step on outreq:<id>: out_answer closes it (the answer is the case's note); out_chase notes a chase. Returns the note. */
    public static function pulseDo(PDO $pdo, int $id, string $what, array $a, int $uid): string
    {
        $all = self::requests($pdo, true); $r = $all[$id] ?? null;
        if (!$r) throw new RuntimeException('That request isn\'t here.');
        $text = trim((string) ($a['text'] ?? ''));
        if ($what === 'out_answer') {
            if ($text === '') throw new RuntimeException('Write what they answered.');
            $r['closed'] = date('Y-m-d H:i:s');
            self::store($pdo, $r, $id);
            return 'Their answer: ' . mb_substr($text, 0, 3500);
        }
        if ($what === 'out_chase') return 'Chased' . ($text !== '' ? ': ' . mb_substr($text, 0, 1000) : '.');
        throw new RuntimeException('Unknown step.');
    }

    public static function saveNotes(PDO $pdo, string $key, array $in): void
    {
        if (!isset(self::COMPANIES[$key])) throw new InvalidArgumentException('No such service.');
        foreach (self::NOTE_FIELDS as $f => [$label]) {
            $v = trim(preg_replace('/\s+/', ' ', (string) ($in[$f] ?? '')));
            if (mb_strlen($v) > 200) throw new InvalidArgumentException($label . ': keep it under 200 characters.');
            if ($f === 'vault' && $v !== '' && self::vaultLink($v) === '') throw new InvalidArgumentException('1Password link: paste the item\'s private link (it starts https://start.1password.com/ or your 1Password address).');
            if ($f !== 'vault' && self::looksSecret($v)) throw new InvalidArgumentException($label . ': that looks like a password or key. Keep those in the password manager; here only which account and how.');
            $name = 'svcnote:' . $key . ':' . $f;
            if ($v === '') $pdo->prepare('DELETE FROM ' . Database::t('staff_settings') . ' WHERE name = ?')->execute([$name]);
            else $pdo->prepare('INSERT INTO ' . Database::t('staff_settings') . ' (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute([$name, $v]);
        }
    }

    /** Does this look like a password, a key or a token? (names, emails, links and account numbers are fine) */
    public static function looksSecret(string $v): bool
    {
        if ($v === '') return false;
        if (preg_match('/\b(password|passwd|pwd|passcode|pass|secret|token|api[ _-]?key)\s*[:=]/i', $v)) return true;   // "password: …"
        if (preg_match('/\b(AKIA|ASIA)[A-Z0-9]{16}\b|\bsk-[A-Za-z0-9_-]{10,}|\bghp_[A-Za-z0-9]{20,}|\bxox[abp]-|-----BEGIN/', $v)) return true;   // known key shapes
        foreach (preg_split('/\s+/', $v) as $w) {
            $w = trim($w, '.,;:()"\'');
            if (filter_var($w, FILTER_VALIDATE_EMAIL) || preg_match('#^https?://#i', $w) || preg_match('/^[\d-]+$/', $w)) continue;
            // a long run of letters and digits mixed, with no spaces, reads as a secret ("traversence-site" doesn't)
            if (mb_strlen($w) >= 12 && preg_match('/[A-Za-z]/', $w) && preg_match('/\d/', $w)) return true;
        }
        return false;
    }

    /** The settings changed here (EnvSettings::EDITABLE) that belong to a company, shown on its card, not in Settings. The
     *  storage ones go to whichever company keeps the files (Cloudflare with R2, else Amazon). */
    public const SETTINGS = [
        'aws' => ['AWS_CONSOLE_URL', 'AWS_REGION'],
        'railway' => ['RAILWAY_USD_MONTH'],
        'bluehost' => ['BLUEHOST_USD_MONTH'],
        'namecom' => ['DOMAIN_SALES_MODE', 'DOMAINS_MODE', 'NAMECOM_ENV'],
    ];
    public const STORAGE_SETTINGS = ['S3_BUCKET', 'S3_PUBLIC_BASE'];

    /** The settings shown on a company's card. */
    public static function settingsOf(string $key): array
    {
        $r2 = str_contains(self::env('S3_ENDPOINT'), 'r2.cloudflarestorage.com');
        return array_merge(self::SETTINGS[$key] ?? [], $key === ($r2 ? 'cloudflare' : 'aws') ? self::STORAGE_SETTINGS : []);
    }

    /** Every setting that's on a card (Settings shows the rest). */
    public static function onCards(): array { return array_merge(array_merge(...array_values(self::SETTINGS)), self::STORAGE_SETTINGS); }

    private static function env(string $k): string { return trim((string) (getenv($k) ?: '')); }
    private static function has(string ...$ks): bool { foreach ($ks as $k) if (self::env($k) === '') return false; return true; }

    /**
     * The company's parts, each checked: [[what, state words, tone ok|bad|info|'' , the .env names, a note]]. Storage is
     * checked for real (the bucket answers); the rest say whether their keys are set (Test is a later step).
     */
    public static function parts(PDO $pdo, string $key): array
    {
        $set = fn (bool $on, string $yes = 'Keys set', string $no = 'Not set up') => [$on ? $yes : $no, $on ? 'ok' : ''];
        $r2 = str_contains(self::env('S3_ENDPOINT'), 'r2.cloudflarestorage.com');
        $out = [];
        switch ($key) {
            case 'cloudflare':
                if ($r2) { require_once __DIR__ . '/Aws.php'; require_once __DIR__ . '/S3.php'; [$ok, $say] = Aws::checkBucket(); $cors = $ok ? S3::corsOk() : null;
                    $out[] = ['File storage (R2)', $say . ($ok ? ($cors === true ? ' · uploads ready' : ($cors === false ? ' · uploads need the CORS rule' : '')) : ''), $ok && $cors !== false ? 'ok' : 'bad',
                              'S3_ENDPOINT, S3_BUCKET, S3_ACCESS_KEY_ID, S3_SECRET_ACCESS_KEY, S3_REGION', 'Every file the site keeps, each in its owner\'s folder (decisions/0123, 0124, 0151).', 'storage'];
                } else $out[] = ['File storage (R2)', 'Not in use: files are on Amazon S3', '', 'S3_ENDPOINT', 'decisions/0151 has the move.'];
                [$w, $t] = $set(self::has('TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY'), 'On', 'Off: not set up');
                $out[] = ['Captcha on sign-in and sign-up (Turnstile)', $w, $t, 'TURNSTILE_SITE_KEY, TURNSTILE_SECRET_KEY', ''];
                $out[] = ['Page scripts (cdnjs)', 'Loaded by visitors\' browsers: the maps (Leaflet) and the PDF viewer (pdf.js)', 'info', 'built in', 'Free; allowed in the pages\' content security policy.'];
                break;
            case 'aws':
                require_once __DIR__ . '/Aws.php';
                if (is_file(__DIR__ . '/SesMail.php')) { require_once __DIR__ . '/SesMail.php'; $m = SesMail::mode();
                    $out[] = ['Email (SES)', $m === 'live' ? (SesMail::hasKeys('personal') ? 'Sending' : 'On, but its key is missing') : ($m === 'mock' ? 'Practice mode: recorded, nothing sent' : 'Off'), $m === 'live' ? (SesMail::hasKeys('personal') ? 'ok' : 'bad') : '',
                              'SES_MODE, SES_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY', 'Notices, the weekly digest and, later, businesses\' own email (decisions/0137 §8).'];
                }
                if (!$r2 && self::env('S3_ENDPOINT') === '') { require_once __DIR__ . '/S3.php'; [$ok, $say] = Aws::checkBucket(); $cors = $ok ? S3::corsOk() : null;
                    $out[] = ['File storage (S3)', $say . ($ok ? ($cors === true ? ' · uploads ready' : ($cors === false ? ' · uploads need the CORS rule' : '')) : ''), $ok && $cors !== false ? 'ok' : (Aws::on() ? 'bad' : ''),
                              'S3_BUCKET, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REGION', 'Every file the site keeps (decisions/0122).', 'storage'];
                } else $out[] = ['File storage (S3)', 'Not in use: files moved to Cloudflare R2', 'info', '', 'Once nothing is missing, empty the old bucket: Amazon still charges for storing it (decisions/0151).'];
                require_once __DIR__ . '/AiClient.php';
                $out[] = ['Tables and forms (Textract)', AiClient::on('aws') ? 'Keys set' : 'Not set up', AiClient::on('aws') ? 'ok' : '', 'AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REGION', 'With the Asset Digitizing Studio.'];
                break;
            case 'anthropic':
                require_once __DIR__ . '/AiClient.php';
                $out[] = ['Claude', AiClient::on('claude') ? 'Connected ' . AiClient::keyHint('claude') : 'Not set up', AiClient::on('claude') ? 'ok' : '', 'ANTHROPIC_API_KEY', ''];
                try { if (AiClient::ready($pdo)) { $q = (int) $pdo->query("SELECT COUNT(*) FROM " . Database::t('ai_jobs') . " WHERE status = 'queued' AND created_at < NOW() - INTERVAL 5 MINUTE")->fetchColumn();
                    $out[] = ['Jobs waiting', $q ? $q . ' waiting more than 5 minutes: is the AI worker\'s scheduled task running?' : 'Nothing waiting', $q ? 'bad' : 'ok', 'Scheduled tasks: the AI worker, every minute', '']; } } catch (Throwable) {}
                break;
            case 'google':
                require_once __DIR__ . '/AiClient.php';
                require_once __DIR__ . '/SearchIndex.php';
                $seen = SearchIndex::ready($pdo) ? SearchIndex::state($pdo, 'places_seen') : '';
                $out[] = ['Places check (the crawler)', $seen === '' ? 'Not running yet' : (strtolower(self::env('GOOGLE_PLACES')) === 'off' ? 'Off (GOOGLE_PLACES=off)' : 'Running'), $seen === '' ? '' : (strtolower(self::env('GOOGLE_PLACES')) === 'off' ? 'bad' : 'ok'),
                          'GOOGLE_PLACES_KEY and GOOGLE_PLACES_MONTHLY on Railway; GOOGLE_PLACES in .env', 'Keeps Google\'s place ID and puts listings Google shows as closed in Review (decisions/0085).'];
                $out[] = ['Reading menus (Document AI)', AiClient::on('google') ? 'Keys set' : 'Not set up', AiClient::on('google') ? 'ok' : '', 'GOOGLE_DOCAI_KEY_FILE, GOOGLE_DOCAI_PROCESSOR', ''];
                [$w, $t] = $set(self::has('GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'), 'On', 'Off: not set up');
                $out[] = ['Continue with Google', $w, $t, 'GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET', ''];
                $out[] = ['Directions and maps links', 'Links to Google Maps (no key, no cost)', 'info', 'built in', 'Counted as outbound taps, never who (decisions/0058).'];
                $out[] = ['YouTube videos', 'Played only after Play (youtube-nocookie.com)', 'info', 'built in', 'Free to embed.'];
                break;
            case 'tavily':
                require_once __DIR__ . '/SearchIndex.php';
                $eng = SearchIndex::ready($pdo) ? SearchIndex::state($pdo, 'websearch_engine') : '';
                $out[] = ['Web search', strtolower(self::env('WEBSEARCH')) === 'off' ? 'Off (WEBSEARCH=off)' : ($eng === '' ? 'No key on the crawler yet' : 'Running (' . ucfirst($eng) . ')'), $eng === '' ? '' : (strtolower(self::env('WEBSEARCH')) === 'off' ? 'bad' : 'ok'),
                          'TAVILY_API_KEY on Railway; the month\'s searches and the stop line in the Service register; WEBSEARCH in .env', 'If Tavily\'s dashboard shows more than the site counted, trust Tavily\'s.'];
                break;
            case 'railway':
                $out[] = ['The crawler worker', 'Runs on Railway', 'info', 'Its own settings are on Railway', 'When the crawler stops, look here first.'];
                break;
            case 'bluehost':
                $out[] = ['The web host', 'This site, PHP ' . PHP_VERSION, 'ok', 'The hosting account', 'Secrets live in .env, in the home folder above public_html (cPanel → File Manager).'];
                [$w, $t] = $set(self::has('CPANEL_HOST', 'CPANEL_USER', 'CPANEL_TOKEN'), 'Connected: jobs and mailboxes set up from here', 'Not connected: set up by hand in cPanel');
                $out[] = ['cPanel, through its API', $w, $t, 'CPANEL_HOST, CPANEL_USER, CPANEL_TOKEN, STAFF_EMAIL_DOMAIN', 'Scheduled tasks and staff mailboxes.'];
                $out[] = ['The site\'s own email', self::env('SMTP_HOST') !== '' ? 'Sending through ' . self::env('SMTP_HOST') . (self::env('SMTP_PORT') !== '' ? ':' . self::env('SMTP_PORT') : '') . (self::has('SMTP_PASSWORD') ? '' : ' (no password set)') : 'The server\'s own mail',
                          self::env('SMTP_HOST') !== '' && self::has('SMTP_PASSWORD') ? 'ok' : '', 'SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD', 'Sign-up links, claim codes and notices. Its sender name and address are in Settings.'];
                break;
            case 'quickbooks':
                $env = strtolower(self::env('QB_ENV') ?: 'sandbox');
                $live = self::has('QB_CLIENT_ID', 'QB_CLIENT_SECRET'); $sand = self::has('QB_SANDBOX_CLIENT_ID', 'QB_SANDBOX_CLIENT_SECRET');
                $out[] = ['Invoices and card payments', ($env === 'production' ? 'Production' : 'Sandbox') . ': ' . (($env === 'production' ? $live : $sand) ? 'keys set' : 'keys missing'), ($env === 'production' ? $live : $sand) ? 'ok' : 'bad',
                          'QB_ENV, QB_CLIENT_ID, QB_CLIENT_SECRET (QB_SANDBOX_… for the sandbox)', 'Connect or reconnect the company on E-Commerce → Payments. Test orders never reach a live gateway (decisions/0145).', 'payments'];
                break;
            case 'stripe':
            case 'square':
                // the names MarketPay reads (2c, 2026-11-18: the card read STRIPE_LIVE_…, which nothing uses, so a right live
                // setup said "keys missing"). Live: the plain set (STRIPE_SECRET_KEY…); test accounts on the live site, and
                // MARKET_PAY_ENV=sandbox: STRIPE_TEST_… / SQUARE_SANDBOX_… (MarketPay::keyName). Each set's missing names said.
                $mp = strtolower(self::env('MARKET_PAY_ENV') ?: 'off');
                $plain = $key === 'stripe' ? ['STRIPE_CLIENT_ID', 'STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_WEBHOOK_SECRET'] : ['SQUARE_APP_ID', 'SQUARE_APP_SECRET', 'SQUARE_WEBHOOK_KEY'];
                $test = array_map(fn ($k) => (string) preg_replace(['/^STRIPE_/', '/^SQUARE_/'], ['STRIPE_TEST_', 'SQUARE_SANDBOX_'], $k), $plain);
                $miss = fn (array $ks) => array_values(array_filter($ks, fn ($k) => self::env($k) === ''));
                $mLive = $miss($plain); $mTest = $miss($test);
                $isLive = $mp === 'live';
                // live: the plain set must be live keys; sandbox: the plain set (test values) or the test set
                $m = $isLive ? $mLive : (count($mLive) < count($plain) ? $mLive : $mTest);
                $words = ($isLive ? 'Live' : ($mp === 'sandbox' ? ($key === 'stripe' ? 'Test mode' : 'Sandbox') : 'Off')) . ': ' . ($m ? 'missing ' . implode(', ', $m) : 'keys set');
                if ($isLive) $words .= '; test accounts: ' . ($mTest ? 'missing ' . implode(', ', $mTest) . ' (they can\'t pay by card until set)' : 'test keys set');
                $out[] = [$key === 'stripe' ? 'Marketplace checkout' : 'Marketplace checkout and catalog', $words, $mp === 'off' ? '' : ($m ? 'bad' : 'ok'),
                          'MARKET_PAY_ENV; live: ' . implode(', ', $plain) . '; test accounts: ' . implode(', ', $test),
                          $key === 'stripe' ? 'Test accounts always pay in test mode with the test set; going live is the plain set with live keys and MARKET_PAY_ENV=live.' : 'Test accounts always use the sandbox set; going live is the plain set with live keys and MARKET_PAY_ENV=live.'];
                break;
            case 'namecom':
                $keys = self::has('NAMECOM_USER', 'NAMECOM_TOKEN');
                foreach (['DOMAIN_SALES_MODE' => 'Domain sales', 'DOMAINS_MODE' => 'Own domains on storefronts'] as $k => $label) {   // two switches (Jason, 2026-11-17): selling domains, and a storefront's own domain
                    $mode = strtolower(self::env($k) ?: 'off'); $mode = in_array($mode, ['live', 'test'], true) ? $mode : 'off';
                    $out[] = [$label, ucfirst($mode) . ($keys ? ' · keys set' : ' · no live keys'), $mode === 'live' ? ($keys ? 'ok' : 'bad') : '',
                              $k . ', NAMECOM_USER, NAMECOM_TOKEN (NAMECOM_TEST_… for the sandbox)' . ($k === 'DOMAIN_SALES_MODE' ? ', DOMAIN_MARKUP_CENTS' : ''), ''];
                }
                break;
            case 'fly':
                [$w, $t] = $set(self::has('FLY_API_TOKEN', 'FLY_APP'));
                $out[] = ['Storefront domains (the edge)', $w, $t, 'FLY_API_TOKEN, FLY_APP', 'workers/storefront-edge in the docs repo.'];
                break;
            case 'github':
                $out[] = ['The sync and the checks', 'Runs on GitHub Actions', 'info', 'Secrets live in the repo\'s settings', 'Run the sync by hand from its Actions page.'];
                break;
            case 'microsoft':
                [$w, $t] = $set(self::has('MICROSOFT_CLIENT_ID'), 'On', 'Off: not set up');
                $out[] = ['Sign in with Microsoft', $w, $t, 'MICROSOFT_CLIENT_ID, MICROSOFT_TENANT', ''];
                $out[] = ['OneDrive (the working copy)', 'Kept in step by the sync', 'info', 'The sync\'s secrets on GitHub', ''];
                break;
            case 'apple':
                [$w, $t] = $set(self::has('APPLE_CLIENT_ID'), 'On', 'Off: not set up');
                $out[] = ['Sign in with Apple', $w, $t, 'APPLE_CLIENT_ID', ''];
                break;
            case 'meta':
                [$w, $t] = $set(self::has('FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET'), 'On', 'Off: not set up');
                $out[] = ['Sign in with Facebook', $w, $t, 'FACEBOOK_APP_ID, FACEBOOK_APP_SECRET', ''];
                break;
            case 'census':
                [$w, $t] = $set(self::has('CENSUS_API_KEY'));
                $out[] = ['Population data', $w, $t, 'CENSUS_API_KEY', ''];
                $out[] = ['Map points (the geocoder)', 'Called by the site and the crawler\'s map points task', 'info', 'no key', 'Places listings without a point on the map (decisions/0160 W2).'];
                $out[] = ['Tribal nations\' boundaries (TIGER AIANNH)', 'Read by the monthly landmark load', 'info', 'LANDMARKS on Railway', 'decisions/0066.'];
                break;
            case 'osm':
                $out[] = ['Overpass: retired', 'No longer used, by the crawler or the site (2026-11-19)', 'info', 'never asked', 'OpenStreetMap\'s community asked apps not to use its public Overpass servers (community.openstreetmap.org/t/150369). Its servers stay listed below, marked retired, and are never asked, not even their status pages.'];
                $out[] = ['Our copy (Geofabrik)', 'Places by kind and town for the crawler, nearby places and the deep look on the site', 'info', 'built in', 'The weekly state files (Arizona, New Mexico, Utah, Colorado and Nevada) in open_places, with health care and guides since 2026-11-19: shown as OpenStreetMap\'s own layer, credited (decisions/0065). A town or kind it doesn\'t hold is reported as not in our copy.'];
                $out[] = ['Map tiles', 'Loaded by visitors\' browsers on every map', 'info', 'built in', 'tile.openstreetmap.org, credited "© OpenStreetMap contributors".'];
                break;
            case 'wikimedia':
                $out[] = ['Wikidata', 'Facts for places and landmarks (SPARQL)', 'info', 'no key', 'The site\'s place lookup and dossiers; the crawler\'s coverage and landmark load.'];
                $out[] = ['Wikipedia', 'A place\'s summary and landmarks\' articles, cited with a link', 'info', 'no key', 'Never copied (CC BY-SA).'];
                $out[] = ['Wikimedia Commons', 'Free photos with their credit', 'info', 'no key', 'Landmark photos (a scheduled task) and the Stock library.'];
                break;
            case 'heigit':
                [$w, $t] = $set(self::has('ORS_API_KEY'));
                $out[] = ['Drive times (openrouteservice)', $w, $t, 'ORS_API_KEY, ORS_API_URL', 'Without the key, journeys are built without drive times.'];
                break;
            case 'nws':
                $out[] = ['Forecasts and alerts', 'Called by the site (no key)', 'info', 'no key', 'Kept fresh by Guide weather and roads, every 15 minutes.'];
                break;
            case 'az511':
                $out[] = ['Work zones (WZDx)', 'Called by the site (no key)', 'info', 'no key', ''];
                [$w, $t] = $set(self::has('AZ511_API_KEY'), 'Key set: events and alerts too', 'No key: work zones only');
                $out[] = ['Events and alerts (the developer API)', $w, $t, 'AZ511_API_KEY', ''];
                break;
            case 'ridb':
                $out[] = ['Recreation areas and campgrounds', 'Read by the crawler\'s monthly load', 'info', 'RIDB_API_KEY on Railway', 'Its links on listings go to recreation.gov.'];
                break;
            case 'nps':
                $out[] = ['National Register records (NPGallery)', 'Linked on dossiers, not called', 'info', '', ''];
                break;
            case 'usgs':
                $out[] = ['Map layers: Terrain and Satellite', 'Loaded by visitors\' browsers when they choose the layer', 'info', 'built in', 'basemap.nationalmap.gov (js/map-view.js, the listing\'s location map).'];
                $out[] = ['Place names (GNIS files)', 'Read by the crawler\'s monthly landmark load', 'info', 'LANDMARKS on Railway', 'Downloaded from prd-tnm.s3.amazonaws.com (USGS\'s, not our storage).'];
                $out[] = ['The names registry', 'Linked on a place\'s identity record', 'info', '', 'edits.nationalmap.gov'];
                break;
            case 'cms':
                $out[] = ['NPI Registry API', 'Called by the crawler\'s second look and identity checks', 'info', 'no key', ''];
                $out[] = ['The monthly NPI file', 'Read by the crawler at night', 'info', 'NPI_BULK on Railway', ''];
                break;
            case 'irs':
                $out[] = ['The EO BMF file', 'Read by the crawler at night, by state', 'info', 'IRS_BULK on Railway', 'Evidence only.'];
                break;
            case 'cpsc':
                $out[] = ['Recall check', 'Called by the site for Marketplace items', 'info', 'no key', ''];
                break;
            case 'loc':
                $out[] = ['Historic newspapers, photos and maps', 'Called by the crawler (heritage) and the site (Stock library)', 'info', 'no key', 'Read into vetted facts by the heritage catalog.'];
                break;
            case 'commoncrawl':
                $out[] = ['The page index', 'Called by the crawler\'s Common Crawl task', 'info', 'CC_TIME on Railway', ''];
                break;
            case 'brave':
                require_once __DIR__ . '/SearchIndex.php';
                $eng = SearchIndex::ready($pdo) ? SearchIndex::state($pdo, 'websearch_engine') : '';
                $out[] = ['Web search', $eng === 'brave' ? 'Running (the crawler uses Brave)' : 'Not in use: the crawler uses ' . ($eng !== '' ? ucfirst($eng) : 'no search yet'), $eng === 'brave' ? 'ok' : '', 'BRAVE_API_KEY on Railway', 'Tavily is used when both keys are set.'];
                break;
            case 'dbip':
                $out[] = ['The country file', 'Downloaded by the site once a month', 'info', 'no key', 'Only its EU/EEA/UK ranges are kept.'];
                break;
            case 'pixabay':
                [$w, $t] = $set(self::has('PIXABAY_KEY'));
                $out[] = ['Stock photos', $w, $t, 'PIXABAY_KEY', ''];
                break;
            case 'tailwind':
                $out[] = ['Page styles (the CDN)', 'Loaded by visitors\' browsers on the older pages', 'info', 'built in', 'Allowed in the pages\' content security policy (app/ui/csp.php).'];
                break;
            case 'vimeo':
                $out[] = ['Video embeds', 'Played only after Play', 'info', 'built in', ''];
                break;
            case 'onepassword':
                $out[] = ['Staff logins', 'Each card\'s 1Password link (set with ✎)', 'info', '', 'decisions/0155.'];
                break;
        }
        return $out;
    }

    /** The files the site keeps in storage: [count, bytes, video count, video bytes] (photos and videos in Gallery). */
    public static function stored(PDO $pdo): array
    {
        try {
            $r = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(bytes), 0) AS b, SUM(kind = 'video') AS vn, COALESCE(SUM(CASE WHEN kind = 'video' THEN bytes ELSE 0 END), 0) AS vb FROM gallery_items WHERE source = 'gallery'")->fetch(PDO::FETCH_ASSOC) ?: [];
            return [(int) ($r['n'] ?? 0), (int) ($r['b'] ?? 0), (int) ($r['vn'] ?? 0), (int) ($r['vb'] ?? 0)];
        } catch (Throwable) { return [0, 0, 0, 0]; }
    }

    /** A link with {console} filled from .env. */
    public static function link(string $u): string
    {
        return strtr($u, ['{console}' => self::env('AWS_CONSOLE_URL') ?: 'https://console.aws.amazon.com/']);
    }

    /** Is any part of this company switched on? (cards of companies we use first) */
    public static function inUse(array $parts): bool
    {
        foreach ($parts as $p) if (in_array($p[2], ['ok', 'bad'], true) || ($p[2] === 'info' && !str_starts_with((string) $p[1], 'Not in use'))) return true;
        return false;
    }
}
