<?php
// app/admin/manifests.php — the 11 modules' manifests (decisions/0109 A2; Master AOS §8; admin-architecture.md Part B).
// Each module: its sections in menu order, and the tools from other modules kept at hand (A3a). Each section:
//   label, icon, status (live | partial | coming), and what it's made of:
//     'widgets' => [['metrics', tool], ['pipeline', tool], ['table', tool], ['html', fn(PDO): string], ['bridge', url]]
//       (a bridge shows one of today's tool pages inside the section until it's rebuilt from widgets)
//     coming: 'about' (what it will do), 'waits' (what it waits on), 'meanwhile' [[label, url]]
//   'replaces' => the old pages it replaces (the archive banner points them here).
declare(strict_types=1);

$B = fn (string $label, string $icon, string $url, string $status = 'live', array $replaces = []) => ['label' => $label, 'icon' => $icon, 'status' => $status, 'widgets' => [['bridge', $url]], 'replaces' => $replaces ?: [strtok($url, '?#')]];
$C = fn (string $label, string $icon, string $about, string $waits = '', array $meanwhile = []) => ['label' => $label, 'icon' => $icon, 'status' => 'coming', 'about' => $about, 'waits' => $waits, 'meanwhile' => $meanwhile];
$W = fn (string $label, string $icon, array $widgets, array $replaces = [], string $status = 'live') => ['label' => $label, 'icon' => $icon, 'status' => $status, 'widgets' => $widgets, 'replaces' => $replaces];

return [

    'dashboard' => ['sections' => [
        'today' => $W('Today', 'home', [['html', 'tv_admin_mod_today']], ['/admin/index.php', '/admin/adminportal.php', '/admin/w.php']),
        // Pulse, here since 2026-11-12 (Workspaces::MERGED): the same widgets as its own module's sections
        'stream' => $W('Pulse stream', 'pulse', [['html', 'tv_admin_mod_stream']]),
        'quick' => $W('Quick actions', 'sparkle', [['html', 'tv_admin_mod_quick']]),
        'helper' => $B('Helper', 'sparkle', '/admin/helper.php'),
        'mail' => $W('Support inbox', 'inbox', [['html', 'tv_admin_mod_support_mail']]),
        'inbox' => $W('My inbox', 'inbox', [['html', 'tv_admin_mod_inbox']]),   // every mailbox you read, together, in Pulse (decisions/0172 §4.7, 0167 §2a)
        'comms' => $W('Your communications', 'settings', [['html', 'tv_admin_mod_mycomms']]),   // the one Communications setting, for staff (0172 §4.4)
        'streamset' => $W('Stream settings', 'settings', [['html', 'tv_admin_mod_stream_settings']]),
    ], 'at_hand' => ['backoffice/production', 'growth/cards', 'backoffice/orders', 'local/listings', 'growth/calls']],

    'pulse' => ['sections' => [
        'stream' => $W('Stream', 'pulse', [['html', 'tv_admin_mod_stream']]),
        'settings' => $W('Stream settings', 'settings', [['html', 'tv_admin_mod_stream_settings']]),
        'mail' => $W('Support inbox', 'inbox', [['html', 'tv_admin_mod_support_mail']]),   // support@'s webmail, signed in through cPanel (Jason, 2026-11-08)
    ], 'at_hand' => ['backoffice/production', 'backoffice/orders', 'local/listings', 'growth/prospects']],

    'local' => ['sections' => [
        'listings' => $W('Listings', 'store', [['table', 'local/listings']], ['/admin/listing-edit.php', '/admin/listing-add.php']),
        'bulk' => $B('Find, merge & bulk edit', 'store', '/admin/listings.php', 'live', ['/admin/listings.php']),
        'intake' => $B('Intake from a website', 'download', '/admin/listing-intake.php'),
        'review' => $W('Review', 'inbox', [['table', 'local/suggestions'], ['bridge', '/admin/crawler-review.php']], ['/admin/crawler-review.php', '/admin/review-item.php', '/admin/suggestion.php', '/admin/listing-new.php']),
        'claims' => $W('Claims', 'shield', [['pipeline', 'local/claims']], ['/admin/claims.php']),
        'nexus' => $W('Nexus', 'link', [['table', 'local/nexus']], ['/admin/nexus.php', '/admin/rebuild.php']),
        'identities' => $B('Identities', 'contact', '/admin/identities.php'),
        'places' => $W('Landmarks', 'landmark', [['pipeline', 'local/places']], ['/admin/landmarks.php']),
        'recreation' => $B('Recreation sites', 'tree', '/admin/recreation.php'),
        'pins' => $B('Map pins', 'map', '/admin/geocode.php'),
        'nations' => $W('Nations', 'compass', [['table', 'local/nations']], ['/admin/nations.php']),
        'hidden' => $B('Hidden places', 'shield', '/admin/safety.php?sec=hidden'),
        'categories' => $W('Categories', 'store', [['html', 'tv_admin_mod_categories']]),
        'taxonomy' => $B('Taxonomy & clusters', 'map', '/admin/cluster-tools.php?view=places', 'partial'),
        'homes' => $C('Single Home Rule', 'home', 'Each listing has one home anchor in the six tiers; conflicts come here to settle.', 'The rule to be defined (Jason).', [['Taxonomy & clusters', '/admin/local/taxonomy']]),
    ], 'at_hand' => ['growth/cards', 'growth/prospects', 'growth/promotions']],

    'explore' => ['sections' => [
        'journeys' => $W('Journeys', 'compass', [['pipeline', 'explore/journeys']], ['/admin/journeys.php']),
        'experiences' => $W('Experiences', 'sparkle', [['pipeline', 'explore/experiences']], ['/admin/experiences.php']),
        'stories' => $W('Stories', 'pen', [['table', 'explore/stories']], ['/admin/stories.php']),
        'place_guides' => $W('Guides', 'compass', [['table', 'explore/place_guides']])
            + ['item' => ['/admin/guide-edit.php?id={id}', 'Guide']],   // a guide for every place on the funnel (decisions/0131)
        'events' => $W('Live events', 'calendar', [['html', 'tv_admin_mod_events']]),   // what's coming up in each place (the Live events template, decisions/0133)
        'guides' => $W('Who to call', 'bookmark', [['table', 'explore/guides']], ['/admin/resource-guides.php']),   // the resource lines each guide's Essentials shows
        'howto' => $W('How-to guides', 'bookmark', [['pipeline', 'explore/howto']], ['/admin/how-guides.php']),
        'routes' => $C('Waypoints & routes', 'map', 'Interactive map waypoints and GPX/KML route files for journeys and guides.', 'A later wave.'),
    ], 'at_hand' => ['local/listings']],

    // Market 360° (decisions/0135 §1): the Marketplace and Storefronts are two display models, managed independently;
    // 'part' heads each group in the section menu. GO Codes and our own pages sit beside them.
    'market' => ['sections' => [
        'items' => $W('Items', 'store', [['metrics', 'market/items'], ['table', 'market/items']]) + ['part' => 'Marketplace'],
        'rules' => $W('Marketplace rules', 'settings', [['html', 'tv_admin_mod_market_rules']]) + ['part' => 'Marketplace'],
        'bizitems' => $W('Business items', 'store', [['metrics', 'market/bizitems'], ['table', 'market/bizitems']]) + ['part' => 'Marketplace'],
        'exchange' => $W('Offers & disputes', 'users', [['metrics', 'market/offers'], ['table', 'market/offers']]) + ['part' => 'Marketplace'],
        'storefronts' => $W('Storefronts', 'store', [['metrics', 'market/storefronts'], ['table', 'market/storefronts']]) + ['part' => 'Storefronts'],
        'catalog' => $C('Catalog rules', 'settings', 'Pricing rules for businesses\' catalogs. The caps by plan (commercial.md §1.1) already apply to publishing: Free 10, Basic 25, Core 100, Strategic 250, Cornerstone 1,000.', 'A later step.') + ['part' => 'Storefronts'],
        'gateways' => $W('Gateways', 'cart', [['metrics', 'market/gateways'], ['table', 'market/gateways']]) + ['part' => 'Storefronts'],
        'compliance' => $W('Seller compliance', 'shield', [['metrics', 'market/inform'], ['table', 'market/inform']]) + ['part' => 'Storefronts'],   // the INFORM Consumers Act (decisions/0142)
        'domains' => $W('Custom domains', 'link', [['metrics', 'market/domains'], ['table', 'market/domains']]) + ['part' => 'Storefronts'],
        'codes' => $W('GO Codes & short links', 'qr', [['table', 'market/codes']], ['/admin/short-codes.php']) + ['part' => 'GO Codes & our pages'],
        'pages' => $W('Our pages', 'store', [['table', 'market/pages']]) + ['part' => 'GO Codes & our pages'],
        'leads' => $W('Form entries', 'inbox', [['table', 'market/leads']]) + ['part' => 'GO Codes & our pages'],
    ], 'at_hand' => ['local/listings', 'backoffice/orders', 'social/reports']],

    'social' => ['sections' => [
        'reports' => $W('Reports', 'shield', [['pipeline', 'social/reports']], ['/admin/reports.php']),
        'standing' => $W('Standing', 'shield', [['pipeline', 'social/standing']], ['/admin/safety.php']),
        'takedowns' => $W('Takedowns', 'shield', [['html', 'tv_admin_mod_takedowns']]),
        'galleries' => $W('Galleries', 'camera', [['metrics', 'social/gallery_albums'], ['table', 'social/gallery_albums'], ['table', 'social/gallery_items']]),
        'procedures' => $B('Procedures', 'bookmark', '/admin/safety.php?sec=procedures'),
        'groups' => $W('Groups', 'users', [['metrics', 'social/groups'], ['table', 'social/groups']]),   // every group, chapters under their parent (decisions/0136); hosts run their groups (0102 §4b)
        'events' => $C('Events', 'calendar', 'Group and business events, and a public calendar.', 'Its definition (Jason).'),
    ], 'at_hand' => ['local/listings']],

    'growth' => ['sections' => [
        'calls' => $W('Calls today', 'contact', [['table', 'growth/calls']], ['/admin/sales.php']),
        'prospects' => $W('Prospects', 'users', [['pipeline', 'growth/prospects']]),
        'customers' => $W('Customers', 'users', [['metrics', 'growth/customers'], ['table', 'growth/customers']]),
        'cards' => $W('Scan cards', 'camera', [['html', 'tv_admin_mod_cards']]),
        'field' => $B('Add from the field', 'store', '/admin/sales.php?sec=field'),
        'promotions' => $W('Promotions', 'sparkle', [['html', 'tv_admin_mod_promos']], ['/admin/promotions.php']),
        'territories' => $W('Territories', 'map', [['html', 'tv_admin_mod_territories']]),
        'funnel' => $W('Funnel', 'chart', [['html', 'tv_admin_mod_funnel']]),
        'quotes' => $C('Quotes & deals', 'pen', 'Quotes and proposals for a business: lines, terms, send, accept. Winning a deal starts its subscription, its order and its White Glove engagement.', 'Its own build, after this one.', [['New client order', '/admin/client-order.php?new=1']]),
    ], 'at_hand' => ['local/listings', 'backoffice/orders', 'backoffice/products', 'backoffice/plans', 'market/codes']],

    'concierge' => ['sections' => [
        'clients' => $C('Clients', 'users', 'Every client with a White Glove engagement: their plan, rep, services, progress and the opportunities still open.', 'The White Glove build (after this one).'),
        'engagement' => $C('Engagements', 'sparkle', 'Opened by a new subscription or a won deal. The client and their rep choose the services together, from what the plan includes and beyond; each service has a light stage, an owner and a due date, with suggested (never required) tasks, and opportunities kept in view.', 'The White Glove build.'),
        'catalog' => $C('Service catalog', 'bookmark', 'The top-level services, which plans include them, their price outside a plan, and suggested task lists.', 'The White Glove build.'),
        'studio' => $C('Asset studio', 'camera', 'Menus and brochures read by AI into catalog drafts, checked by a person.', 'The studio build.', [['Scan cards', '/admin/growth/cards']]),
        'imports' => $B('Imports', 'inbox', '/admin/auto-imports.php', 'partial'),
    ], 'at_hand' => ['local/listings', 'backoffice/orders', 'backoffice/products', 'growth/prospects', 'growth/customers']],

    'backoffice' => ['sections' => [
        'orders' => $W('Orders', 'inbox', [['primary', 'backoffice/orders'], ['metrics', 'backoffice/orders'], ['table', 'backoffice/orders'], ['table', 'backoffice/site_orders'], ['html', 'tv_admin_mod_qb_pull'], ['html', 'tv_admin_mod_sandbox']], ['/admin/ecommerce.php', '/admin/order.php', '/admin/manifest.php'])   // the board (M1): its tiles filter it by the order's state, so it's a table, not the workflow's stage pipeline
            + ['item' => ['/admin/client-order.php?id={id}', 'Order #{id}']],   // /admin/backoffice/orders/9: the order itself, inside Back-Office
        'products' => $W('Products', 'store', [['table', 'backoffice/products'], ['html', 'tv_admin_mod_cost_list']], ['/admin/products.php']),   // one catalog: what we sell, and the materials and services it's made of (decisions/0130)
        'production' => $W('Order management', 'orders', [['table', 'backoffice/production']]),   // every printed item: in the queue, in production, shipped, delivered (decisions/0101)
        'work_orders' => $W('Work orders', 'inbox', [['table', 'backoffice/work_orders']]),
        'clients' => $W('Clients', 'store', [['table', 'backoffice/clients'], ['table', 'backoffice/client_match']]),   // client businesses are listings: tagged or private, their contacts and orders (decisions/0101)
        'qb_items' => $W('QuickBooks items', 'link', [['html', 'tv_admin_mod_qb_items']]),   // who does what with the money; QuickBooks' items mapped to our products (decisions/0128)
        'collateral' => $W('Collateral', 'camera', [['table', 'backoffice/collateral']]),   // each client's pieces: see, release, reorder (decisions/0101)
        'vendor_list' => $W('Vendors', 'store', [['html', 'tv_admin_mod_vendor_list']]),   // who makes our printed work; work orders go to them (decisions/0101)
        'cost_list' => $W('Materials & services', 'chart', [['html', 'tv_admin_mod_cost_list']]),   // paper at the market's best price, binding and materials: what an item's COGS is worked out from
        'vendors' => $W('Vendor orders', 'inbox', [['table', 'backoffice/vendors']], ['/admin/vendors.php']),
        'pricelists' => $W('Price lists', 'chart', [['table', 'backoffice/pricelists'], ['html', 'tv_admin_mod_pricetest']]),
        'taxes' => $W('Taxes & shipping', 'cart', [['table', 'backoffice/taxes'], ['table', 'backoffice/shipping'], ['html', 'tv_admin_mod_taxship']]),
        'plans' => $W('Plans & subscriptions', 'chart', [['metrics', 'backoffice/plans'], ['pipeline', 'backoffice/plans'], ['html', 'tv_admin_mod_planfeatures']]),
        'payments' => $B('Payments', 'cart', '/admin/quickbooks.php'),
        'money' => $B('Money reports', 'chart', '/admin/ecommerce.php?sec=reports', 'partial', ['/admin/ecommerce.php']),
        'people' => $W('People', 'users', [['table', 'backoffice/members'], ['table', 'backoffice/staff']], ['/admin/people.php', '/admin/staff.php', '/admin/staff-profile.php']),
        'logins' => $B('Staff logins', 'shield', '/admin/people.php?sec=logins'),
        'levels' => $B('Staff levels', 'users', '/admin/staff-levels.php'),
        'fulfillment' => $C('Fulfillment', 'inbox', 'Packs, lots and shipments across orders, and multi-channel splitting. Shipping papers are on each order today.', 'A later step.', [['Orders', '/admin/backoffice/orders']]),
        'imports' => $B('Import & export', 'inbox', '/admin/auto-imports.php', 'partial'),
        'inventory' => $C('Inventory & COGS', 'chart', 'The master inventory ledger and cost of goods.', 'A later wave.'),
        'consent' => $C('Consent hub', 'shield', 'Members\' consents in one place.', 'Its definition (Jason).'),
        'dmca' => $C('DMCA queue', 'shield', 'Take-down requests and counter-notices, with their timers.', 'A later wave.'),
        'vault' => $C('Verification vault', 'shield', 'Verification documents, purged 72 hours after the decision.', 'A later wave.', [['Claims', '/admin/local/claims']]),
    ], 'at_hand' => ['local/listings', 'growth/prospects', 'growth/promotions', 'growth/customers']],

    'insights' => ['sections' => [
        'numbers' => $B('Insights', 'chart', '/admin/insights.php'),
        'search' => $B('Search workbench', 'search', '/admin/search.php?sec=workbench', 'live', ['/admin/search.php', '/admin/search-misses.php', '/admin/search-learning.php', '/admin/search-oversight.php', '/admin/search-index.php', '/admin/search-demand.php']),
        // the crawler console (decisions/0160 §4; Jason, 2026-11-13: in Insights): each workflow's tasks, runs, spend, pause and retry
        'crawler' => $W('Crawler', 'compass', [['html', 'tv_admin_mod_crawler']], ['/admin/crawler.php', '/admin/auto-imports.php', '/admin/data-crawler.php']),
        'sources' => $W('Sources', 'link', [['table', 'insights/sources'], ['bridge', '/admin/sources.php']], ['/admin/sources.php']),
        // W0 Indexer's frontier (decisions/0164 §1.4's note, 2026-11-17): every URL found, verdicts with Undo, blocks, its size
        'index' => $W('The index (W0)', 'search', [['html', 'tv_admin_mod_index']]),
        // the data cleanup tool (decisions/0163 §8 stage 1b, 0164 §6.1b; Jason, 2026-11-14): what the old crawler made, brought into line
        'cleanup' => $W('Data cleanup', 'shield', [['html', 'tv_admin_mod_cleanup']]),
        'seeker' => $W('The Seeker', 'search', [['html', 'tv_admin_mod_seeker']]),
        'topics' => $W('Topic index', 'bookmark', [['html', 'tv_admin_mod_topics']]),
        'audit' => $W('Quarterly audit', 'shield', [['html', 'tv_admin_mod_audit']]),
        'aiassist' => $W('AI Assist', 'sparkle', [['html', 'tv_admin_mod_aiassist']]),
        'data' => $B('Data browser', 'inbox', '/admin/data.php'),
        'fivew' => $C('5 W\'s lexicon', 'search', 'Who, What, Where, When, Why: the lexicon and what the parser makes of a search.', 'The parser\'s output (Jason).', [['Search workbench', '/admin/insights/search']]),
        'emergency' => $C('Emergency resources', 'shield', 'Shelters, hotlines and closures on a map.', 'Its definition (Jason).'),
        'telemetry' => $C('Telemetry', 'chart', 'Errors, jobs, AI spend and the file count against the server\'s ceiling.', 'Its trends are on the System Control Board (decisions/0171 §6): each process and system, open it to see the last 24 hours, 7 days or 30 days, with uptime.', [['System Control Board: trends and uptime', '/admin/config/control'], ['Settings', '/admin/config/settings']]),
    ], 'at_hand' => ['local/listings']],

    'config' => ['sections' => [
        // the System Control Board (decisions/0171): every system and process, its stages and controls; Pre-flight and Start in it
        'control' => $W('System Control Board', 'chart', [['html', 'tv_admin_mod_control']]),
        // its tables (decisions/0171 §12): every alarm, the logbook, the ledger of the workers' calls, and the upkeep calendar
        'alarms' => $W('Control Board: Alarms', 'bell', [['table', 'config/alarms']]),
        'log' => $W('Control Board: Logbook', 'book', [['table', 'config/log']]),
        'ledger' => $W('Control Board: Ledger', 'route', [['table', 'config/ledger']]),
        'upkeep' => $W('Control Board: Upkeep', 'calendar', [['table', 'config/upkeep']]),
        'workflows' => $W('Workflows', 'settings', [['html', 'tv_admin_mod_workflows'], ['html', 'tv_admin_mod_workflows_crawler']]),   // the admin's stage workflows (0109), then the crawler's W1–W14 with the Workflow board (decisions/0175's note, release 2b part C)
        'permissions' => $W('Permissions', 'shield', [['html', 'tv_admin_mod_permissions']]),
        'team' => $W('Team', 'users', [['html', 'tv_admin_mod_team'], ['table', 'config/team']]),   // the roles that work the Work queue: operators, keepers, auditors and advocates (decisions/0179's Piece 2, handoffs/piece2-team.md §4.3)
        'datacheck' => $W('Data separation', 'shield', [['html', 'tv_admin_mod_datacheck']]),
        'scheduler' => $W('Scheduled tasks', 'calendar', [['html', 'tv_admin_mod_scheduler']]),
        'preflight' => $W('Pre-flight', 'check', [['html', 'tv_admin_mod_preflight']]),   // every service, setting, workflow and task proved working before Start the system (decisions/0163 §10's note of 2026-11-16)
        'automation' => $W('Automation', 'settings', [['html', 'tv_admin_mod_automation']]),   // the master switch, workflows, tasks and services (Jason, 2026-11-14; decisions/0163 §9)
        'register' => $W('Modules (Service register)', 'link', [['html', 'tv_admin_mod_register']]),   // every outside service, one card each: its stage, status, caps and paces, use, cost, key, sign-in, settings (decisions/0163 §1, 0173; Outside services, 0152, is part of it: its old address comes here (router.php))
        'costs' => $W('Costs', 'chart', [['html', 'tv_admin_mod_costs']]),   // this month by service and workflow, against revenue and the community budget (decisions/0163 §2)
        'board' => $W('Workflow board', 'chart', [['html', 'tv_admin_mod_board']]),   // each workflow's settings as versions, the task schedule, the switch-on check (decisions/0163 §3, §10)
        'ai' => $W('AI use', 'sparkle', [['html', 'tv_admin_mod_ai']]),
        'communications' => $W('Communications', 'inbox', [['html', 'tv_admin_mod_comms']]),   // senders, mailboxes, routing, domain health, message control (decisions/0167, 0172 §4.3)
        'vault' => $W('Vault', 'lock', [['html', 'tv_admin_mod_vault']]),   // logins sealed in the owner's browser, opened with a passkey (decisions/0156)
        'thresholds' => $W('Thresholds & gates', 'settings', [['html', 'tv_admin_mod_thresholds']]),
        'content' => $W('Content templates', 'pen', [['html', 'tv_admin_mod_content']]) + ['item' => ['/admin/content-template.php?id={id}', 'Template']],   // the AI Content Crawler's templates (decisions/0133)
        'stock' => $W('Stock library', 'camera', [['html', 'tv_admin_mod_stock'], ['table', 'config/stock']]),   // photos no one on the site made, for illustrative spots only (decisions/0143)
        'jurisdictions' => $W('Jurisdictions', 'shield', [['html', 'tv_admin_mod_jurisdictions']]),
        'dbupdates' => $W('Database updates', 'inbox', [['html', 'tv_admin_mod_dbupdates']]),
        'switches' => $W('Switches', 'settings', [['html', 'tv_admin_mod_switches']]),   // feature switches (decisions/0145 §6)
        'testaccounts' => $W('Test accounts', 'shield', [['html', 'tv_admin_mod_testaccounts']]),   // the accounts the site checks sign in as (decisions/0145 §3)
        'staging' => $W('Staging', 'shield', [['html', 'tv_admin_mod_staging']]),
        'releases' => $W('Releases', 'calendar', [['html', 'tv_admin_mod_releases']]),   // planned, phased releases: staging first, steps, turn off, roll back (decisions/0145 Phase 5)   // the staging copy at staging.trvgo.us: set up, refresh, held email (decisions/0145 Phase 3)
        'settings' => $W('Settings', 'settings', [['html', 'tv_admin_mod_settings']], ['/admin/settings.php']),   // rebuilt in the new admin (decisions/0152)
        'logs' => $W('Logs', 'bookmark', [['table', 'config/logs']], ['/admin/logs.php']),
        'sitemap' => $B('Site map', 'map', '/admin/sitemap.php'),
        'decisions' => $W('Decisions', 'book', [['html', 'tv_admin_mod_decisions'], ['table', 'config/decisions']]),   // the ADRs on GitHub: read, edit, notes, new; saved straight to main (2026-11-12)
        'templates' => $C('Templates', 'pen', 'The emails and notices the site sends, with their fields.', 'A later step.'),
        'branding' => $C('Branding & SEO', 'sparkle', 'Logo, colours and icons; permalink rules and schema builders.', 'A later wave.'),
        'notify' => $C('Text messages', 'inbox', 'Texts (Twilio): claim codes, customers\' order texts, a business\'s texts to people who opted in. Email and push are built: set up in Communications.', 'Its own decision first (decisions/0172 §5), then its build, only where revenue covers it.', [['Communications', '/admin/config/communications']]),
        'storage' => $C('Storage & backups', 'inbox', 'S3, daily encrypted backups kept 30 days, and the restore drill.', 'A later step.', [['Service register', '/admin/config/register']]),
    ], 'at_hand' => []],
];
