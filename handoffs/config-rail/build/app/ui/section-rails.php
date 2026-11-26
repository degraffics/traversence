<?php
/**
 * includes/section-rails.php — the toolbar for each part of the site (decisions/0055 §1).
 *
 * Every page has the same layout: the toolbar full height on the left, the workspace on the right. Which toolbar a
 * page gets follows its part of the site (the header's funnels):
 *   explore  Let's Explore: Discover, regions and place pages, recreation places
 *   local    Get Local: the directory, listings, claiming a business
 *   social   Connect: community groups, people's profiles
 *   market   Market: posts to buy, sell or offer (decisions/0031; a placeholder until it's built)
 *   admin    Admin: staff tools (the crawler pages have their own, includes/admin-crawler.php)
 *   site     everything else: home, how Traversence works, privacy, terms
 * A page can name its part and active item (tv_page_start opts 'section', 'active'); otherwise both come from its path.
 */

/** The part of the site a path belongs to, and the item to light up. */
function tv_section_for(string $path): array
{
    $p = strtolower($path);
    if (str_starts_with($p, '/qr')) return ['go', str_starts_with($p, '/qr/cart') ? 'cart' : (str_starts_with($p, '/qr/manage') ? 'manage' : 'create')];   // GO Codes (decisions/0096)
    if (str_starts_with($p, '/shop') || str_starts_with($p, '/website/shop')) return ['go', 'shop'];   // the Shop (decisions/0154 §6): beside the cart, in the same menu
    if (str_starts_with($p, '/journeys/view')) return ['explore', 'stories'];   // a published journey is read on Let's Explore
    if (str_starts_with($p, '/user/journeys')) return ['member', 'journeys'];   // writing and managing them is Dashboard work
    if (str_starts_with($p, '/user/experiences')) return ['member', 'experiences'];
    if (str_starts_with($p, '/user/orders')) return ['member', 'orders'];
    if (str_starts_with($p, '/user/market')) return ['market', 'selling'];   // Your Marketplace (decisions/0135)
    if (str_starts_with($p, '/experiences/')) return ['explore', 'experiences'];   // an experience is read on Let's Explore (decisions/0059)
    if (str_starts_with($p, '/discovery')) return ['explore', 'overview'];
    if (str_starts_with($p, '/hub')) return ['explore', 'place'];
    if (str_starts_with($p, '/place/recreation')) return ['explore', 'outdoors'];
    if (str_starts_with($p, '/listing') || str_starts_with($p, '/directory')) return ['local', str_starts_with($p, '/listing') ? 'listing' : 'directory'];
    if (str_starts_with($p, '/claim')) return ['local', 'claim'];
    if (str_starts_with($p, '/community/group')) return ['social', 'group'];
    if (str_starts_with($p, '/community')) return ['social', 'groups'];
    if (str_starts_with($p, '/user/profile')) return ['social', 'profile'];
    if (str_starts_with($p, '/market')) return ['market', str_starts_with($p, '/market/cart') ? 'cart' : (($_GET['sort'] ?? '') === 'near' ? 'near' : 'browse')];
    if (str_starts_with($p, '/admin/reports')) return ['admin', 'reports'];
    if (str_starts_with($p, '/admin/journeys')) return ['admin', 'journeys'];
    if (str_starts_with($p, '/admin/experiences')) return ['admin', 'experiences'];
    if (str_starts_with($p, '/admin/landmarks')) return ['admin', 'landmarks'];
    if (str_starts_with($p, '/admin/nations')) return ['admin', 'nations'];
    if (str_starts_with($p, '/place/landmark')) return ['explore', 'outdoors'];
    if (str_starts_with($p, '/admin/insights')) return ['admin', 'insights'];
    if (str_starts_with($p, '/admin/search-misses')) return ['admin', 'misses'];
    if (str_starts_with($p, '/admin/how-guides')) return ['admin', 'howguides'];
    if (str_starts_with($p, '/admin/search-demand')) return ['admin', 'demand'];
    if (str_starts_with($p, '/admin/search-index')) return ['admin', 'searchindex'];
    if (str_starts_with($p, '/admin/resource-guides')) return ['admin', 'guides'];
    if (str_starts_with($p, '/admin/search-oversight')) return ['admin', 'oversight'];
    if (str_starts_with($p, '/admin/search-learning')) return ['admin', 'learning'];
    foreach (['/admin/claims' => 'claims', '/admin/identities' => 'identities', '/admin/auto-imports' => 'published', '/admin/review-item' => 'review',
              '/admin/suggestion' => 'review', '/admin/nexus' => 'nexus', '/admin/listing' => 'listings', '/admin/crawler-review' => 'review',
              '/admin/search.php' => 'search', '/admin/safety' => 'safety', '/admin/data-crawler' => 'data', '/admin/crawler' => 'data',
              '/admin/sources' => 'data', '/admin/cluster-tools' => 'data', '/admin/data.php' => 'databrowser', '/admin/geocode' => 'data',
              '/admin/stories' => 'data', '/admin/recreation' => 'data', '/admin/categories' => 'data',
              '/admin/people' => 'people', '/admin/staff' => 'people', '/admin/settings' => 'settings', '/admin/sales' => 'sales',
              '/admin/ecommerce' => 'ecommerce', '/admin/order.php' => 'ecommerce', '/admin/quickbooks' => 'ecommerce',
              '/admin/client-order' => 'ecommerce', '/admin/products' => 'ecommerce'] as $pre => $k) if (str_starts_with($p, $pre)) return ['admin', $k];
    if (str_starts_with($p, '/admin')) return ['admin', 'portal'];
    if (str_contains($p, 'faq')) return ['site', 'faq'];
    if (str_contains($p, 'our-approach')) return ['site', 'approach'];
    if (str_contains($p, 'privacy')) return ['site', 'privacy'];
    if (str_contains($p, 'terms')) return ['site', 'terms'];
    return ['site', ''];
}

/** @return array{label: string, items: list<array>} */
function tv_section_rail(string $section, array $extra = []): array
{
    // a place in the address carries along the Let's Explore toolbar (so its links stay in that place)
    $pq = [];
    foreach (['cluster', 'geo', 'hub'] as $k) if (!empty($_GET[$k]) && preg_match('/^[a-z0-9-]{1,100}$/i', (string) $_GET[$k])) { $pq = [$k => (string) $_GET[$k]]; break; }
    $x = fn (string $v) => '/discovery/' . (($q = http_build_query(array_merge($v === 'overview' ? [] : ['view' => $v], $pq))) !== '' ? '?' . $q : '');
    $in = class_exists('Auth') && Auth::currentUserId() !== null;
    switch ($section) {
        case 'explore':
            $items = [
                // always the default landing (North America), whatever place you're in
                ['key' => 'overview', 'label' => 'Discovery Dashboard', 'icon' => 'compass', 'url' => '/discovery/', 'what' => 'Back to the start: the big picture', 'section' => 'Explore'],
                ['key' => 'plan', 'label' => 'Plan a trip', 'icon' => 'route', 'url' => $x('plan'), 'what' => 'Where, when and what: trip ideas'],
                ['key' => 'map', 'label' => 'Map', 'icon' => 'map', 'url' => $x('map'), 'what' => 'Everything on a map, with a distance'],
                ['key' => 'regions', 'label' => 'Regions', 'icon' => 'globe', 'url' => $x('regions'), 'what' => 'Walk down from a region to its towns'],
                ['key' => 'experiences', 'label' => 'Experiences', 'icon' => 'bookmark', 'url' => $x('experiences'), 'what' => 'Things to do worth the trip (decisions/0059)'],
                ['key' => 'parks', 'label' => 'Parks & Landmarks', 'icon' => 'shield', 'url' => '/discovery/?view=parks', 'what' => 'Parks, monuments, historic sites and natural landmarks'],
            ];
            if ($pq) $items[] = ['key' => 'place', 'label' => 'This place', 'icon' => 'home', 'url' => '/hub/?' . http_build_query($pq), 'what' => 'The place page: living here, support, outdoors, groups'];
            $items = array_merge($items, [
                ['key' => 'outdoors', 'label' => 'Outdoors', 'icon' => 'tree', 'url' => $x('outdoors'), 'what' => 'Public lands, trails, lakes and campgrounds', 'section' => 'Things to do'],
                ['key' => 'food', 'label' => 'Food & drink', 'icon' => 'utensils', 'url' => $x('food'), 'what' => 'Places to eat, by what they serve'],
                ['key' => 'culture', 'label' => 'Culture & heritage', 'icon' => 'landmark', 'url' => $x('culture'), 'what' => 'Museums, history, arts, tribal nations'],
                ['key' => 'events', 'label' => 'Events', 'icon' => 'calendar', 'url' => '#', 'soon' => true, 'what' => 'Coming soon'],
                ['key' => 'stories', 'label' => 'Stories & guides', 'icon' => 'book', 'url' => $x('stories'), 'what' => 'Guides and journeys for each place'],
            ]);
            return ['label' => "Let's Explore", 'items' => $items];
        case 'local':
            $items = [
                ['key' => 'directory', 'label' => 'Search the directory', 'icon' => 'search', 'url' => '/directory/', 'what' => 'Local businesses and services', 'section' => 'Get Local'],
                ['key' => 'listing', 'label' => 'This listing', 'icon' => 'store', 'url' => (string) ($_SERVER['REQUEST_URI'] ?? '#'), 'what' => 'The listing you\'re on'],
                ['key' => 'claim', 'label' => 'Claim or add a business', 'icon' => 'shield', 'url' => '/claim.php', 'what' => 'Own it? Claim it, or add one that\'s missing', 'section' => 'Businesses'],
            ];
            if ($in) $items[] = ['key' => 'yours', 'label' => 'Your businesses', 'icon' => 'inbox', 'url' => '/user/dashboard.php#business', 'what' => 'Messages, insights and your team'];
            return ['label' => 'Get Local', 'items' => $items];
        case 'social':
            $items = [
                ['key' => 'groups', 'label' => 'Groups', 'icon' => 'users', 'url' => '/community/', 'what' => 'Find a group by place, pastime or cause', 'section' => 'Connect'],
                ['key' => 'group', 'label' => 'This group', 'icon' => 'home', 'url' => (string) ($_SERVER['REQUEST_URI'] ?? '#'), 'what' => 'The group you\'re in'],
                ['key' => 'start', 'label' => 'Start a group', 'icon' => 'link', 'url' => '/community/#cg-new', 'what' => 'Around a place, a pastime or a cause'],
            ];
            if ($in) $items = array_merge($items, [
                ['key' => 'mine', 'label' => 'Your groups', 'icon' => 'bookmark', 'url' => '/user/dashboard.php#community', 'what' => 'The groups you belong to', 'section' => 'Yours'],
                ['key' => 'people', 'label' => 'Address Book', 'icon' => 'contact', 'url' => '/user/dashboard.php#addressbook', 'what' => 'People you know; find people'],
                ['key' => 'pulse', 'label' => 'Your Pulse', 'icon' => 'pulse', 'url' => '/user/dashboard.php#pulse', 'what' => 'Posts from your groups and more'],
            ]);
            return ['label' => 'Connect', 'items' => $items];
        case 'market':
            $items = [
                ['key' => 'browse', 'label' => 'Browse all', 'icon' => 'store', 'url' => '/market/', 'what' => 'What people and businesses near you are offering', 'section' => 'Marketplace'],
                ['key' => 'near', 'label' => 'Near me', 'icon' => 'map', 'url' => '/market/?sort=near', 'what' => 'Nearest first, from your town'],   // town to town (the landing, 2026-11-03)
                ['key' => 'sell', 'label' => 'Post something', 'icon' => 'plus', 'url' => '/user/market/?new=1', 'what' => 'Sell, give away or offer a service', 'section' => 'Sell'],
                ['key' => 'selling', 'label' => 'Your posts', 'icon' => 'inbox', 'url' => '/user/market/', 'what' => 'Where each stands; mark sold; still available?'],
                ['key' => 'saved', 'label' => 'Saved & messages', 'icon' => 'bookmark', 'url' => '/user/market/?tab=buying', 'what' => 'What you saved, and the sellers you messaged', 'section' => 'Buy'],
            ];
            if ($in && is_file(__DIR__ . '/../lib/Market.php')) {   // how many of your posts are up, beside Your posts
                try { require_once __DIR__ . '/../lib/Market.php'; $pn = Market::outCount(Database::connection(), (int) Auth::currentUserId()); if ($pn) $items[3]['badge'] = $pn; } catch (Throwable) {}
            }
            if ($in) $items[] = ['key' => 'messages', 'label' => 'Messages', 'icon' => 'contact', 'url' => '/user/dashboard.php#addressbook:messages', 'what' => 'Where buyers and sellers connect'];
            if ($in && is_file(__DIR__ . '/../lib/MarketCheckout.php')) {   // the carts, one per business (decisions/0135 step 4)
                try { require_once __DIR__ . '/../lib/MarketCheckout.php'; $cn = MarketCheckout::cartCount(Database::connection(), (int) Auth::currentUserId());
                    $items[] = ['key' => 'cart', 'label' => 'Cart & checkout', 'icon' => 'cart', 'url' => '/market/cart.php', 'what' => 'What you\'re buying from businesses, and checkout'] + ($cn ? ['badge' => $cn] : []); } catch (Throwable) {}
            }
            return ['label' => 'Marketplace', 'items' => $items];
        case 'go':   // GO Codes: make them, the cart, your codes and orders (decisions/0096, 0098)
            $n = tv_go_cart_count();
            $items = [
                ['key' => 'create', 'label' => 'Create', 'icon' => 'qr', 'url' => '/qr/', 'what' => 'Make a GO Code: a link, phone, contact card, Wi-Fi…', 'section' => 'GO Codes'],
                ['key' => 'cart', 'label' => 'Cart', 'icon' => 'cart', 'url' => '/qr/cart.php', 'what' => 'Your cart and checkout'] + ($n ? ['badge' => $n] : []),
                ['key' => 'shop', 'label' => 'Shop', 'icon' => 'store', 'url' => '/shop/', 'what' => 'Plans, storage and services, for you or your business'],   // Jason, 2026-11-11: "accessible from the cart page"
                ['key' => 'manage', 'label' => 'My GO Codes', 'icon' => 'download', 'url' => '/qr/manage.php', 'what' => 'Download your codes, see their scans', 'section' => 'Yours'],
                ['key' => 'orders', 'label' => 'My orders', 'icon' => 'inbox', 'url' => '/qr/manage.php#go-orders-sec', 'what' => 'What you bought, and asking about an order'],
                ['key' => 'dynamic', 'label' => 'Dynamic GO Codes', 'icon' => 'link', 'url' => '/qr/manage.php#go-dyn-h', 'what' => 'Codes you can point somewhere new after printing'],
                ['key' => 'help', 'label' => 'GO Codes help', 'icon' => 'help', 'url' => '/faq.php#what-are-go-codes', 'what' => 'Prices, fixes, refunds, how scans are counted', 'section' => 'Help'],
            ];
            if ($in) $items[] = ['key' => 'dash', 'label' => 'Your Dashboard', 'icon' => 'home', 'url' => '/user/dashboard.php', 'what' => 'Back to your Dashboard'];
            return ['label' => 'GO Codes', 'items' => $items];
        case 'admin':
            return ['label' => 'Admin', 'items' => tv_admin_rail('')];
        case 'member':   // the member Dashboard's menu, on Dashboard pages that live outside user/dashboard.php (Your journeys, the editor)
            $d = fn (string $key, string $label, string $icon, string $section = '', array $more = []) => ['key' => $key, 'label' => $label, 'icon' => $icon,
                'url' => str_starts_with($key, '/') ? $key : '/user/dashboard.php#' . $key, 'section' => $section] + $more;
            return ['label' => 'Dashboard', 'items' => [
                $d('pulse', 'Dashboard', 'home', 'You'),   // the rest of your tools are on the Dashboard (same as user/dashboard.php)
                ['key' => 'experiences', 'label' => 'Your experiences', 'icon' => 'compass', 'url' => '/user/experiences/', 'what' => 'Post things to do worth the trip'],
                ['key' => 'gallery', 'label' => 'Gallery', 'icon' => 'camera', 'url' => '/gallery/', 'what' => 'Your photos, videos and albums'],
                ['key' => 'orders', 'label' => 'Your orders', 'icon' => 'inbox', 'url' => '/user/orders.php', 'what' => 'Orders we prepared for you: proofs, payment, files'],
                $d('profile', 'Profile', 'user', 'Settings'), $d('places', 'Default places', 'map'), $d('security', 'Privacy', 'shield'), $d('settings', 'App settings', 'settings'), $d('/plans.php', 'Plans', 'sparkle', '', ['what' => 'Your plan, storage and AI Assist credits']), $d('help', 'Help', 'book'),
            ]];
        default:
            $items = [
                // a page outside the sections (FAQ, Privacy, the 404): its tools are the help pages. Where to go is the compass's
                // menu, never this one (Jason, 2026-10-23: one place for where, one for what); on phones this list holds only you.
                ['key' => 'faq', 'label' => 'Help Center', 'icon' => 'help', 'url' => '/faq.php', 'section' => 'Help', 'what' => 'Answers about how Traversence works'],
                ['key' => 'approach', 'label' => 'Our approach', 'icon' => 'link', 'url' => '/our-approach.php'],
                ['key' => 'privacy', 'label' => 'Privacy', 'icon' => 'shield', 'url' => '/privacy.php'],
                ['key' => 'terms', 'label' => 'Terms', 'icon' => 'book', 'url' => '/terms.php'],
            ];
            return ['label' => 'Help', 'items' => $items, 'site' => true];
    }
}

/**
 * The admin menu, the same on every admin page (decisions/0078): Today, then one entry per area. $active: the area or a
 * tool's key ('review', 'reports', 'crawler:sources' …), lit as the area it belongs to. $waiting: the Review badge
 * before the counts are available. ($onDashboard is kept for old callers; it no longer changes anything.)
 */
function tv_admin_rail(string $active, bool $onDashboard = false, int $waiting = 0): array
{
    // The admin menu: the 11 workspaces (decisions/0104), each one a module with its sections (admin/router.php, decisions/0109;
    // the Dashboard is admin/index.php). A tool opened on its own page lights the workspace that is its home.
    $area = tv_admin_area($active);
    $C = [];
    if (class_exists('Database')) { require_once __DIR__ . '/admin-dashboard.php'; try { $C = tv_admin_counts(Database::connection()); } catch (Throwable) {} }
    $n = fn (string ...$k) => array_sum(array_map(fn ($x) => (int) ($C[$x] ?? 0), $k));
    $waiting = $C ? $n('suggestions', 'listings', 'sources', 'names', 'clusters') : $waiting;
    $page = fn (string $key, string $label, string $icon, string $url, string $what, int $badge = 0, string $section = '') => ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $url, 'what' => $what,
        'section' => $section, 'on' => $key === $area] + ($badge ? ['badge' => $badge] : []);
    // the 11 workspaces (decisions/0104, Master AOS §8); a staff login sees only those its level and department open
    require_once __DIR__ . '/../lib/Workspaces.php';
    $staff = null; $pdo = null;
    try { require_once __DIR__ . '/../lib/StaffLogin.php'; if (class_exists('Database')) { $pdo = Database::connection(); $staff = StaffLogin::inSession($pdo) ?: null; } } catch (Throwable) {}
    $opens = Workspaces::opens($staff, $pdo);
    $ws = isset(Workspaces::ALL[$active]) ? $active : Workspaces::homeOfArea($area);
    if (isset(Workspaces::MERGED[$ws])) $ws = Workspaces::MERGED[$ws][0];
    $sales = (function (): int { try { require_once __DIR__ . '/../lib/Sales.php'; $p = Database::connection(); if (!Sales::ready($p)) return 0; $st = $p->prepare("SELECT COUNT(*) FROM " . Database::t('prospects') . " WHERE next_at IS NOT NULL AND next_at <= ? AND stage NOT IN ('no','closed')"); $st->execute([date('Y-m-d')]); return (int) $st->fetchColumn(); } catch (Throwable) { return 0; } })();
    $shop = (function (): int { try { require_once __DIR__ . '/../lib/Orders.php'; $p = Database::connection(); if (!Orders::ready($p)) return 0; $n = (int) $p->query("SELECT COUNT(*) FROM orders WHERE status = 'disputed'")->fetchColumn(); try { $n += count(Orders::inbox($p)); } catch (Throwable) {} return $n; } catch (Throwable) { return 0; } })();
    $badge = ['local' => $waiting + $n('claims', 'claims_held', 'claims_changes', 'claims_disputes') + $n('conflicts', 'found', 'experiences'),
        'growth' => $sales, 'backoffice' => $shop, 'social' => $n('reports', 'standing'),
        'insights' => $n('missed', 'left', 'drafts', 'guides', 'due', 'gaps', 'failed')];
    $out = [];
    foreach (Workspaces::ALL as $k => [$num, $name, $plain, $icon, $what]) {
        if (!in_array($k, $opens, true) || isset(Workspaces::MERGED[$k])) continue;   // Pulse is in the Dashboard now
        $out[] = ['key' => $k, 'label' => $num . '. ' . $name, 'icon' => $icon, 'url' => '/admin/' . $k,   // the module (decisions/0109)
           
            'what' => $plain !== $name ? $plain . ': ' . $what : $what, 'section' => $k === 'dashboard' ? 'Admin' : '', 'on' => $k === $ws]
            + (!empty($badge[$k]) ? ['badge' => (int) $badge[$k]] : []);
        if ($k === 'config' && $k === $ws) { require_once __DIR__ . '/../admin/AdminModules.php'; $out[count($out) - 1]['children'] = tv_config_rail_children(); }   // its pages, while it's open
    }
    return $out;
}

/**
 * System Configuration's pages in the side menu (Jason, 2026-11-25: "the team should be a page in the side menu for the
 * system configuration … Development, board, workflow, services, team"): listed under System Configuration while it's the
 * open module, the section dropdown kept. One list, here: [key, label, icon, address, module, section, what it is].
 */
const TV_CONFIG_RAIL = [
    ['cfg:development', 'Development', 'inbox', '/admin/config/control?tab=development', 'config', 'control', 'The Work queue: everything found that needs doing'],
    ['cfg:board', 'Board', 'chart', '/admin/config/control', 'config', 'control', 'The System Control Board: every system, its alarms and gauges'],
    ['cfg:workflows', 'Workflows', 'settings', '/admin/config/workflows', 'config', 'workflows', 'The stage workflows, and the crawler\'s W0 to W14'],
    ['cfg:services', 'Services', 'link', '/admin/config/register', 'config', 'register', 'The Service register: every outside service, its stage, keys and costs'],
    ['cfg:team', 'Team', 'users', '/admin/config/team', 'config', 'team', 'The Team: its roles, the decision tree and how far agents work'],
];

/** TV_CONFIG_RAIL as the menu's children: the one open now marked current; one Access refuses greyed with why. */
function tv_config_rail_children(): array
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? ''); $path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/'); parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
    $out = [];
    foreach (TV_CONFIG_RAIL as [$key, $label, $icon, $url, $mod, $sec, $what]) {
        $why = '';
        try {
            if (class_exists('Modules')) {
                [$ok, $why] = Modules::canSection($mod, $sec);
                if ($ok) foreach ((array) (Modules::all()[$mod]['sections'][$sec]['widgets'] ?? []) as $w) if (in_array($w[0], ['table', 'pipeline'], true)) { [$ok, $why] = Modules::canTool($w[1]); if (!$ok) break; }
                if ($ok) $why = '';
            }
        } catch (Throwable) { $why = ''; }
        $u = (string) parse_url($url, PHP_URL_PATH); parse_str((string) parse_url($url, PHP_URL_QUERY), $uq);
        $on = $path === rtrim($u, '/') && (($uq['tab'] ?? '') === ($q['tab'] ?? ''));   // the Board is the page with no tab; Development its tab
        $out[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $url, 'what' => $what, 'on' => $on] + ($why !== '' ? ['off' => $why] : []);
    }
    return $out;
}

/** The admin area a page or tool belongs to (decisions/0078): its menu entry. */
function tv_admin_area(string $active): string
{
    // a tool page opened on its own: its address says where it belongs (tv_section_for), unless the page said
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (str_starts_with(strtolower($path), '/admin')) { $k = tv_section_for($path)[1]; if ($k !== 'portal' && ($active === '' || str_starts_with($active, 'crawler:') || $active === 'listings')) $active = $k; }
    static $AREA = [
        'listings' => ['listings', 'review', 'published', 'edit', 'experiences', 'journeys', 'landmarks', 'nations', 'crawler:crawl', 'claims', 'identities', 'nexus'],
        'search' => ['search', 'misses', 'learning', 'oversight', 'guides', 'howguides', 'searchindex', 'demand'],
        'safety' => ['safety', 'reports', 'procedures'],
        'data' => ['data', 'crawler', 'databrowser'],
        'insights' => ['insights'], 'people' => ['people', 'team'], 'settings' => ['settings'], 'sales' => ['sales'], 'ecommerce' => ['ecommerce'],
    ];
    foreach ($AREA as $area => $keys) if (in_array($active, $keys, true)) return $area;
    if (str_starts_with($active, 'crawler:')) return 'data';
    return 'admin';   // Today, the Helper, and anything not placed yet
}

/** How many GO Codes are in this person's cart (a member's, or a guest's by their browser key); 0 when none or unknown. */
function tv_go_cart_count(): int
{
    static $n = null;
    if ($n !== null) return $n;
    $n = 0;
    try {
        if (!class_exists('Database')) return $n;
        require_once __DIR__ . '/../lib/QrIssue.php';
        $pdo = Database::connection();
        if (!QrIssue::ready($pdo)) return $n;
        $me = class_exists('Auth') ? Auth::currentUser() : null;
        if (!$me && QrIssue::guestKey() === '') return $n;   // a visitor with nothing in a cart
        $n = count(QrIssue::cart($pdo, QrIssue::owner($me))['items']);
        if ($me && is_file(__DIR__ . '/../lib/CartPlans.php')) { require_once __DIR__ . '/../lib/CartPlans.php'; foreach (CartPlans::groups($pdo, (int) $me['id']) as $g) $n += count($g['lines']); }   // plans and packs: one cart (decisions/0154 §6)
    } catch (Throwable) {}
    return $n;
}
