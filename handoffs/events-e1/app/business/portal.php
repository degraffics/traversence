<?php
// app/business/portal.php — the business portal (business-portal-architecture.md; decisions/0110, 0115): our admin's
// tools scoped to one business and opened by its plan. The modules (§2), and the one check every page and API call
// makes: the person's role on this listing (Team, AOS §9B, with Viewer), then the listing's plan (Plan). What someone
// may not open shows greyed with the reason: their role, or "Included in Core Partner and up: see plans".
declare(strict_types=1);

require_once __DIR__ . '/../lib/Team.php';
require_once __DIR__ . '/../lib/Plan.php';

/** The modules: key => [label, icon, the role permission it needs, the plan feature, the tool it shows (live) or what it will do (coming)]. */
function tv_biz_modules(): array
{
    return [
        'dashboard' => ['label' => 'Overview', 'icon' => 'home', 'perm' => '', 'feature' => 'inbox', 'live' => true],
        // Market 360° (decisions/0140) at /business/<id>/market (business/.htaccess takes letters only); the modules marked 'in' => 'market' sit under it
        'market' => ['label' => 'Market 360°', 'icon' => 'cart', 'perm' => '', 'feature' => 'inbox', 'live' => true, 'about' => 'Everything for selling in one place: set up in seven parts that tick themselves (profile, store, catalog, getting paid, shipping and tax, verification, test and launch), then its six modules: Business Identification, Money & Processing, Products & Inventory, E-commerce, Storefront and Marketing.'],
        'inbox' => ['label' => 'Inbox', 'icon' => 'inbox', 'perm' => 'inbox', 'feature' => 'inbox', 'about' => 'Customer messages, guest questions, offers and form entries in one place: reply, assign and note.'],
        'customers' => ['label' => 'Customers', 'icon' => 'users', 'perm' => 'customers', 'feature' => 'customers', 'live' => true, 'tool' => 'growth/customers', 'about' => 'Who buys from you, their orders and notes. Yours to keep and export.', 'in' => 'market'],
        'quotes' => ['label' => 'Quotes & invoices', 'icon' => 'pen', 'perm' => 'ledger', 'feature' => 'quotes', 'live' => true, 'tool' => 'business/quotes', 'about' => 'Build a quote line by line from your catalog, with terms (Net 15, Net 30), a deposit, tax and your pay link; the customer approves it online and it becomes the invoice.', 'in' => 'market'],
        'catalog' => ['label' => 'Catalog', 'icon' => 'store', 'perm' => 'catalog', 'feature' => 'catalog', 'live' => true, 'tool' => 'backoffice/products', 'about' => 'Your items and services with their prices, your private cost and markup, and options.', 'in' => 'market'],
        'marketplace' => ['label' => 'Marketplace', 'icon' => 'cart', 'perm' => 'catalog', 'feature' => 'catalog', 'live' => true, 'about' => 'Publish catalog items to the Marketplace and your Shop: drafts and published versions, photos, what buyers can do, and offers.', 'in' => 'market'],
        'checkout' => ['label' => 'E-commerce setup', 'icon' => 'cart', 'perm' => 'ledger', 'feature' => 'manual_pay', 'live' => true, 'about' => 'How you get paid on the Marketplace: connect Stripe or Square (Core and up) or take Zelle, transfers and checks; tax, shipping and pickup; then go live. Your Marketplace orders.', 'in' => 'market'],
        'orders' => ['label' => 'Orders', 'icon' => 'cart', 'perm' => 'orders', 'feature' => 'manual_pay', 'live' => true, 'tool' => 'business/orders', 'about' => 'Approved quotes as orders through their stages (new, in production, ready or shipped, done); ready and shipped email the customer.', 'in' => 'market'],
        'taxes' => ['label' => 'Sales tax', 'icon' => 'percent', 'perm' => 'ledger', 'feature' => 'tax_shipping', 'live' => true, 'tool' => 'backoffice/taxes', 'about' => 'Your sales tax rates by state, city or ZIP code; pick one on a quote, or set your usual one in Plan & settings.', 'in' => 'market'],
        'shipping' => ['label' => 'Shipping & pickup', 'icon' => 'truck', 'perm' => 'orders', 'feature' => 'tax_shipping', 'live' => true, 'tool' => 'backoffice/shipping', 'about' => 'How you deliver: flat rate, by weight, free over an amount, local pickup or local delivery.', 'in' => 'market'],
        'storefront' => ['label' => 'Storefront & pages', 'icon' => 'compass', 'perm' => 'theme', 'feature' => 'storefront', 'live' => true, 'about' => 'Your own shop at trvgo.us/your-name, or on your own domain: your items, your pages, your colors.', 'in' => 'market'],
        'discounts' => ['label' => 'Discount codes', 'icon' => 'percent', 'perm' => 'codes', 'feature' => 'discounts', 'live' => true, 'tool' => 'business/codes', 'about' => 'Your own codes: a percent or an amount off, with dates and limits if you want them. Customers type them on their quote page.', 'in' => 'market'],
        'marketing' => ['label' => 'Marketing', 'icon' => 'sparkle', 'perm' => 'codes', 'feature' => 'discounts', 'about' => 'QR codes and short links, and email campaigns.', 'in' => 'market'],
        // Market 360°'s six modules (decisions/0141 §1): its own pages for identity and trust, and the ones still coming, which open greyed with what they'll do
        'trust' => ['label' => 'Trust score', 'icon' => 'shield', 'perm' => '', 'feature' => 'inbox', 'live' => true, 'about' => 'What buyers can count on: each check you\'ve done (your phone, your payments, later your registration and ID), each with the button that does it, and the badge it shows.', 'in' => 'market'],
        'legal' => ['label' => 'Legal identity', 'icon' => 'book', 'perm' => 'listing', 'feature' => 'inbox', 'about' => 'Your legal name, DBA, kind of business (sole proprietor, LLC, corporation) and operating address. They fill your store policies and raise your Trust score.', 'in' => 'market'],
        'policies' => ['label' => 'Store policies', 'icon' => 'pen', 'perm' => 'ledger', 'feature' => 'manual_pay', 'live' => true, 'about' => 'Your returns, cancellations, deposits and house rules, written into a Store policies box on your items, storefront, checkout and receipts.', 'in' => 'market'],
        'bundles' => ['label' => 'Bundles', 'icon' => 'store', 'perm' => 'catalog', 'feature' => 'catalog', 'about' => 'Several of your items sold as one offer; each item\'s stock comes off when it sells.', 'in' => 'market'],
        'linkers' => ['label' => 'Linked people', 'icon' => 'users', 'perm' => 'customers', 'feature' => 'inbox', 'live' => true, 'about' => 'The people who linked your business, by name, with the contact details each one chooses to share with you. Live, never a copy, and no export.', 'in' => 'market'],
        'reports' => ['label' => 'Reports', 'icon' => 'chart', 'perm' => 'ledger', 'feature' => 'customers', 'about' => 'Sales, margin from your private cost, items turning over, and customers new and returning.', 'in' => 'market'],
        // Events (decisions/0134's minimum before Jan 1, the note of 2026-11-27): free events a claimed business posts, linking its own ticket items
        'events' => ['label' => 'Events', 'icon' => 'calendar', 'perm' => 'catalog', 'feature' => 'inbox', 'live' => true, 'about' => 'Post what\'s happening at your business: free to post, with your own Event tickets item linked when you sell tickets on the Marketplace.'],
        'domains' => ['label' => 'Domains', 'icon' => 'globe', 'perm' => 'domain', 'feature' => 'inbox', 'live' => true, 'about' => 'The business\'s own domains, bought in the Shop or owned elsewhere: renew, auto-renew, where each points, DNS and email records, transfer away; and its wallet of cards (decisions/0169 §1a).'],
        'settings' => ['label' => 'Plan & settings', 'icon' => 'settings', 'perm' => '', 'feature' => 'inbox', 'live' => true],
    ];
}

/** Who you are on this business, read with our login before the business realm opens; null when you aren't on its team. */
function tv_biz_context(PDO $main, int $uid, int $listingId): ?array
{
    $role = Team::roleOf($main, $uid, $listingId);
    if ($role === null) return null;
    $st = $main->prepare('SELECT id, name, status, plan_tier FROM entities WHERE id = ?'); $st->execute([$listingId]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) return null;
    $team = Team::members($main, $listingId);
    if (!$team) $team = [['user_id' => $uid, 'role' => $role, 'display_name' => '', 'email' => '']];
    return ['id' => $listingId, 'name' => (string) $l['name'], 'status' => (string) $l['status'], 'tier' => Plan::tier((string) $l['plan_tier']), 'role' => $role, 'uid' => $uid, 'team' => $team,
        'people' => array_column(array_map(fn ($m) => [(int) $m['user_id'], trim((string) ($m['display_name'] ?: $m['email']))], $team), 1, 0)];
}

/** May this person open (view) or change (edit) a module? [yes, why not, 'role' | 'plan' | 'coming' | ''] */
function tv_biz_can(PDO $main, array $ctx, string $module, string $need = 'view'): array
{
    $m = tv_biz_modules()[$module] ?? null;
    if (!$m) return [false, 'Not part of the business portal', 'missing'];
    if ($m['perm'] !== '') {
        $level = Team::level($main, $ctx['role'], $m['perm']);
        $rank = ['none' => 0, 'view' => 1, 'draft' => 2, 'edit' => 3, 'full' => 4];
        if (($rank[$level] ?? 0) < ($rank[$need] ?? 1) && $ctx['role'] === 'white_glove') return [false, 'White Glove doesn\'t cover ' . ($need === 'view' ? 'opening ' : 'changing ') . $m['label'] . ' (see the White Glove terms)', 'role'];
        if (($rank[$level] ?? 0) < ($rank[$need] ?? 1)) return [false, 'Your role (' . Team::roleName($ctx['role']) . ') can\'t ' . ($need === 'view' ? 'open ' : 'change ') . $m['label'] . '. The owner can change your role.', 'role'];
    }
    [$ok, $start] = Plan::allows($main, $ctx['tier'], $m['feature']);
    if (!$ok) return [false, Plan::upgradeNote($start), 'plan'];
    if (empty($m['live'])) return [false, 'Coming', 'coming'];
    return [true, '', ''];
}

/**
 * Market 360°'s sections, in its dropdown's order (decisions/0141 §1): Get selling first, then the six modules by
 * level (platform, operations, storefront, growth). A module's pages not built yet stay listed, greyed with what
 * they'll do. '' => Get selling (no heading).
 */
function tv_biz_m360_sections(): array
{
    return ['' => ['market'],
            'Business Identification' => ['trust', 'legal'],
            'Money & Processing' => ['checkout', 'taxes', 'policies'],
            'Products & Inventory' => ['catalog', 'marketplace', 'bundles'],
            'E-commerce' => ['orders', 'customers', 'quotes', 'shipping', 'discounts', 'reports'],
            'Storefront' => ['storefront'],
            'Marketing' => ['linkers', 'marketing']];
}

function tv_biz_url(int $id, string $module = ''): string { return '/business/' . $id . ($module !== '' ? '/' . $module : ''); }

/**
 * The one menu for a business (Jason, 2026-10-23: "Business portal vs listing landing page?"): the same on its
 * dashboard (acting as the listing) and in its business portal. The listing (Pulse, messages, the page, editing it),
 * then its tools (the portal's modules, greyed with the reason when your role or plan doesn't open them), then its team.
 * $onDashboard: the dashboard's own sections open in place (data-go); elsewhere they're links to it.
 */
function tv_biz_rail(PDO $main, int $listingId, int $uid, bool $onDashboard = false, string $name = ''): array
{
    $go = fn (string $k, string $label, string $icon, string $section = '') => ['key' => $k, 'label' => $label, 'icon' => $icon, 'section' => $section,
        'url' => $onDashboard ? '#' . $k : '/user/dashboard.php?in=' . $k . '#' . $k, 'attrs' => $onDashboard ? 'data-go="' . $k . '"' : ''];
    $rail = [['key' => 'dashboard', 'label' => 'Overview', 'icon' => 'home', 'url' => tv_biz_url($listingId), 'section' => $name],   // the one landing for a business
        // its Pulse and its messages are on its Overview (Jason, 2026-11-11), so outside the dashboard they open there
        $onDashboard ? $go('pulse', 'Pulse', 'pulse') : ['key' => 'pulse', 'label' => 'Pulse', 'icon' => 'pulse', 'url' => tv_biz_url($listingId) . '#pulse'],
        $onDashboard ? $go('business', 'Messages & insights', 'inbox') : ['key' => 'business', 'label' => 'Messages & offers', 'icon' => 'inbox', 'url' => tv_biz_url($listingId) . '#messages'],
        ['key' => 'view', 'label' => 'The listing', 'icon' => 'store', 'url' => '/listing/view.php?id=' . $listingId, 'attrs' => 'data-peek-frame data-peek-title="The listing, as visitors see it"'],   // a preview over the portal, not a way out of it (Jason, 2026-11-05)
        ['key' => 'edit', 'label' => 'Edit listing', 'icon' => 'pen', 'url' => '/listing/businessportal.php?id=' . $listingId],
        ['key' => 'gallery', 'label' => 'Gallery', 'icon' => 'camera', 'url' => '/gallery/?b=' . $listingId]];   // its photos, videos and albums (decisions/0126)
    $ctx = tv_biz_context($main, $uid, $listingId);
    // its tools: one button each, as on the admin side; Market 360° is one button, its sections in its page's own
    // dropdown (decisions/0140; Jason, 2026-11-02: "like the admin: one button for Market 360")
    $first = true;
    foreach (tv_biz_modules() as $k => $x) {
        if ($k === 'settings' || $k === 'dashboard' || ($x['in'] ?? '') === 'market') continue;
        [$o, $w] = $ctx ? tv_biz_can($main, $ctx, $k) : [false, 'Not on its team'];
        $rail[] = ['key' => $k, 'label' => $x['label'], 'icon' => $x['icon'], 'url' => tv_biz_url($listingId, $k), 'what' => $o ? '' : $w, 'cls' => $o ? '' : 'bz-off', 'section' => $first ? 'Business tools' : ''];
        $first = false;
    }
    $rail[] = $go('team', 'Team & roles', 'users', 'Organization');
    $rail[] = ['key' => 'settings', 'label' => 'Plan & settings', 'icon' => 'settings', 'url' => tv_biz_url($listingId, 'settings')];
    $rail[] = $go('help', 'Help', 'book', 'Help');
    return $rail;
}

/**
 * The listing at a glance, at the top of its Overview (the one landing for a business, 2026-10-23): its status and
 * town, quick actions, how complete its profile is, and its numbers. Moved here from the dashboard's listing view.
 */
function tv_biz_glance(PDO $main, int $listingId, string $name): string
{
    $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
    $status = ''; $town = ''; $have = []; $nums = [];
    try {
        $q = $main->prepare('SELECT status, category_id FROM entities WHERE id = ?'); $q->execute([$listingId]); $er = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $status = ['verified' => '✓ Verified', 'claimed-unverified' => 'Managed by owner · confirm by text to verify', 'pending-verification' => 'Claimed · verification pending', 'unclaimed' => 'Unclaimed'][$er['status'] ?? ''] ?? ucfirst((string) ($er['status'] ?? ''));
        $m = [];
        $q = $main->prepare("SELECT meta_key, meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key IN ('phone','address','website','hours','description','city','state')");
        $q->execute([$listingId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $m[$r['meta_key']] = json_decode((string) $r['meta_value'], true) ?? $r['meta_value'];
        $town = trim(implode(', ', array_filter([is_string($m['city'] ?? null) ? $m['city'] : '', is_string($m['state'] ?? null) ? $m['state'] : ''])));
        $has = fn ($k) => !empty($m[$k]) && $m[$k] !== [] && $m[$k] !== '';
        $have = ['Phone' => $has('phone'), 'Address' => $has('address'), 'Website' => $has('website'), 'Hours' => $has('hours'), 'Description' => $has('description'), 'Category' => !empty($er['category_id'])];
        if (is_file(__DIR__ . '/../lib/Marketing.php') && is_file(__DIR__ . '/../lib/Links.php')) {
            require_once __DIR__ . '/../lib/Marketing.php'; require_once __DIR__ . '/../lib/Links.php';
            if (Marketing::tableExists($main) && Links::tableExists($main)) { $c = Marketing::counts($main, $listingId); $nums = ['Linked' => (int) $c['linked'], 'Subscribers' => (int) $c['subscribed'], 'New links (30 days)' => (int) $c['new_30d']]; }
        }
    } catch (Throwable $x) { error_log('biz glance: ' . $x->getMessage()); }
    $done = count(array_filter($have)); $of = max(1, count($have));
    $miss = array_keys(array_filter($have, fn ($x) => !$x));
    $h = '<div class="bo tv-bleed"><p class="bo-k">Your listing at a glance</p><div class="bo-top"><div><h2 class="bo-n">' . $e($name) . '</h2><p class="bo-s">' . $e(implode(' · ', array_filter([$status, $town]))) . '</p></div><div class="bo-acts">'
        . '<a class="ak-b go" href="#messages">Reply to messages</a><a class="ak-b" href="/listing/businessportal.php?id=' . $listingId . '">Edit listing</a>'
        . '<a class="ak-b" href="/listing/view.php?id=' . $listingId . '">See it as visitors do</a><a class="ak-b" href="/listing/location.php?id=' . $listingId . '">Location and map pin</a></div></div>';
    if (isset($m['address']) && is_string($m['address']) && (require_once __DIR__ . '/../lib/ListingActions.php') && ListingActions::isMailbox($m['address']))   // the owner's way forward (2026-11-19)
        $h .= '<p data-tv-status="info" data-tv-label="Mailing address only:">Your address reads as a mailbox, so visitors see no Directions and your pin shows the town. <a class="underline" href="/listing/businessportal.php?id=' . $listingId . '">Add a street address</a> to show Directions.</p>';
    if ($have) $h .= '<div class="bo-prof"><div class="bo-bar"><span style="width:' . round(100 * $done / $of) . '%"></span></div><p class="bo-s">Profile ' . $done . ' of ' . $of . ' complete'
        . ($miss ? ': add ' . $e(mb_strtolower(implode(', ', $miss))) . ' so more searches find you' : '') . '.</p></div>';
    if ($nums) { $h .= '<div class="bo-nums">'; foreach ($nums as $l => $v) $h .= '<div><b>' . (int) $v . '</b><span>' . $e($l) . '</span></div>'; $h .= '</div>'; }
    return $h . '</div><style>.bo{background:var(--tv-sec,#F7EFDC);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:1rem;padding:.9rem 1rem;margin:.2rem 0 .8rem}.bo-top{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.6rem 1rem}.bo-k{font:700 .72rem system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:var(--tv-meta,#8A7355);margin:0 0 .25rem}'
        . '.bo-n{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.3rem;margin:0;color:var(--tv-ink,#3E2B1F)}.bo-s{font-size:.82rem;color:var(--tv-meta,#8A7355);margin:.15rem 0 0}.bo-acts{display:flex;flex-wrap:wrap;gap:.35rem;align-items:flex-start}'
        . '.bo-prof{margin-top:.7rem}.bo-bar{height:8px;border-radius:999px;background:var(--tv-sec,#EADFC4);overflow:hidden}.bo-bar span{display:block;height:100%;background:#15803D}'
        . '.bo-nums{display:grid;gap:.5rem;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));margin-top:.8rem}.bo-nums div{background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.7rem;padding:.5rem .7rem}'
        . '.bo-nums b{display:block;font-size:1.25rem;color:var(--tv-ink,#3E2B1F)}.bo-nums span{font-size:.74rem;color:var(--tv-meta,#8A7355)}@media (max-width:767px){.bo{border-radius:.5rem}}</style>';
}
