<?php
require_once __DIR__ . '/../../app/ui/security-headers.php';
// listing/view.php  (Public business detail page — Tier 2.1's dependency root, prd.md)
//
//   /listing/view.php?id=123                  canonical lookup, always works
//   /listing/view.php?id=123&slug=joes-diner   slug is cosmetic only (SEO), never looked up
//
// `entities` has no slug column yet, so this can't be the clean `/listing/{slug}`
// path routes.md/architecture.md §19 describes — that needs a small follow-up
// migration (add entities.slug, backfill, unique index) plus a rewrite rule,
// the same "query-param placeholder now, clean path later" pattern already
// used by hub/index.php (?hub=/?geo=/?cluster= today, /{hub}/{geo}/{cluster}
// planned). Once that lands, this file's lookup only needs one extra WHERE
// clause; nothing else here changes.
//
// Same public-safety rules as api/search_listings.php: PUBLIC_STATUSES
// whitelist, is_suppressed excluded, only named columns selected.
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/lib/Database.php';
require_once __DIR__ . '/../../app/lib/PlaceScope.php';
require_once __DIR__ . '/../../app/lib/Auth.php';
require_once __DIR__ . '/../../app/lib/ListingSafety.php';
require_once __DIR__ . '/../../app/lib/SearchText.php';
// Read the visitor's sign-in (if any) before output, read-only: admins get an "Edit (admin)" link.
if (session_status() === PHP_SESSION_NONE && isset($_COOKIE['traversence_session'])) {
    session_name('traversence_session');
    @session_start(['read_and_close' => true]);
}

const PUBLIC_STATUSES = ['unclaimed', 'claimed-unverified', 'pending-verification', 'verified'];

$id = (int)($_GET['id'] ?? 0);
$biz = null;
$crumbTrail = null; // [[hub name, hub href], [geo-hub name, geo href], [cluster name, cluster href]]
$err = null;

if ($id > 0) {
    try {
        $pdo = Database::connection();
        $statusList = "'" . implode("','", PUBLIC_STATUSES) . "'";
        // staff can open a hidden listing (closed, merged, held) to check it, with a banner saying so (decisions/0072)
        $staffPeek = false; $teamPeek = false;
        if ($me0 = Auth::currentUser()) {
            $staffPeek = !empty($me0['is_admin']);
            if (!$staffPeek) try { $sp = $pdo->prepare('SELECT 1 FROM ' . Database::t('admin_access') . ' WHERE user_id = ? LIMIT 1'); $sp->execute([(int) $me0['id']]); $staffPeek = (bool) $sp->fetchColumn(); } catch (Throwable) {}
            // the business's own team sees it too, so "See it as visitors do" never says not found to them (site audit P11)
            if (!$staffPeek) try { require_once __DIR__ . '/../../app/lib/Team.php'; $teamPeek = Team::roleOf($pdo, (int) $me0['id'], $id) !== null; } catch (Throwable) {}
        }
        $st = $pdo->prepare(
            "SELECT e.id, e.name, e.status, e.is_suppressed, e.category_id, e.micro_cluster_id, e.latitude, e.longitude,
                    COALESCE(" . SearchText::catNameSql($pdo, 'c') . ", JSON_UNQUOTE(m_cat.meta_value))  AS category,
                    " . SearchText::catNameSql($pdo, 'g') . "        AS category_group,
                    JSON_UNQUOTE(m_addr.meta_value)                   AS address,
                    JSON_UNQUOTE(m_city.meta_value)                   AS city,
                    JSON_UNQUOTE(m_state.meta_value)                  AS state,
                    JSON_UNQUOTE(m_phone.meta_value)                  AS phone,
                    JSON_UNQUOTE(m_web.meta_value)                    AS website,
                    JSON_UNQUOTE(m_cu.meta_value)                     AS contact_url,
                    JSON_UNQUOTE(m_hours.meta_value)                  AS hours,
                    JSON_UNQUOTE(m_desc.meta_value)                   AS description,
                    e.owner_user_id
               FROM entities e
               LEFT JOIN categories c ON c.id = e.category_id
               LEFT JOIN categories g ON g.id = c.parent_id
               LEFT JOIN entity_metadata m_cat   ON m_cat.entity_id   = e.id AND m_cat.meta_key   = 'category'
               LEFT JOIN entity_metadata m_addr  ON m_addr.entity_id  = e.id AND m_addr.meta_key  = 'address'
               LEFT JOIN entity_metadata m_city  ON m_city.entity_id  = e.id AND m_city.meta_key  = 'city'
               LEFT JOIN entity_metadata m_state ON m_state.entity_id = e.id AND m_state.meta_key  = 'state'
               LEFT JOIN entity_metadata m_phone ON m_phone.entity_id = e.id AND m_phone.meta_key = 'phone'
               LEFT JOIN entity_metadata m_web   ON m_web.entity_id   = e.id AND m_web.meta_key   = 'website'
               LEFT JOIN entity_metadata m_cu    ON m_cu.entity_id    = e.id AND m_cu.meta_key    = 'contact_url'
               LEFT JOIN entity_metadata m_hours ON m_hours.entity_id = e.id AND m_hours.meta_key = 'hours'
               LEFT JOIN entity_metadata m_desc  ON m_desc.entity_id  = e.id AND m_desc.meta_key  = 'description'
              WHERE e.id = :id
                AND e.entity_type = 'merchant'
                AND ((e.is_suppressed = 0 AND e.status IN ($statusList)) OR :staff = 1)
              LIMIT 1"
        );
        $st->execute(['id' => $id, 'staff' => $staffPeek || $teamPeek ? 1 : 0]);
        $biz = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        // Domestic-violence shelters and safe houses keep their location private (decisions/0044, narrowed by 0076):
        // no address, map pin or directions; their phone and the 24-hour lines are the way in, and an office address
        // they publish themselves (office_address, set by staff from their own site) shows instead.
        $privateLocation = $biz && ListingSafety::isConfidential($biz['category_id'] !== null ? (int)$biz['category_id'] : null, (string)$biz['name']);
        $officeAddress = '';
        if ($privateLocation) {
            $biz['address'] = null;
            $biz['latitude'] = $biz['longitude'] = null;
            try { $oa = $pdo->prepare("SELECT JSON_UNQUOTE(meta_value) FROM entity_metadata WHERE entity_id = ? AND meta_key = 'office_address'"); $oa->execute([(int) $biz['id']]); $officeAddress = trim((string) $oa->fetchColumn()); } catch (Throwable) {}
        }

        if ($biz && $biz['micro_cluster_id']) {
            $ct = $pdo->prepare(
                'SELECT mc.id AS cid, mc.slug AS cslug, mc.name AS cname,
                        g.slug AS gslug, g.name AS gname,
                        h.slug AS hslug, h.name AS hname
                   FROM micro_clusters mc
                   JOIN geo_hubs g ON g.id = mc.geo_hub_id
                   JOIN hubs h ON h.id = g.hub_id
                  WHERE mc.id = ?'
            );
            $ct->execute([(int)$biz['micro_cluster_id']]);
            if ($c = $ct->fetch(PDO::FETCH_ASSOC)) {
                $crumbTrail = [
                    [$c['hname'], '/hub/?hub=' . urlencode($c['hslug'])],
                    [$c['gname'], '/hub/?geo=' . urlencode($c['gslug'])],
                    [PlaceScope::publicName($c['cname']), '/hub/?cluster=' . (int)$c['cid']],
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('listing/view.php: ' . $e->getMessage());
        $err = 'This listing could not load right now.';
    }
}

$dir = tv_route(['directory/index.php' => '/directory/']) ?: '/';
$viewerId = Auth::currentUserId();
$isOwner = $biz && $viewerId !== null && (int)($biz['owner_user_id'] ?? 0) === $viewerId;
if ($biz && $viewerId !== null && !$isOwner) {   // its team's Owner, too (Team, AOS §9B): the business's own people see "Manage", never "Ask to claim it"
    try { require_once __DIR__ . '/../../app/lib/Team.php'; $isOwner = Team::roleOf($pdo, $viewerId, (int) $biz['id']) === 'owner'; } catch (Throwable) {}
}

// a listing merged into another (decisions/0072): its old link opens the one kept
if (!$biz && $id > 0 && isset($pdo) && is_file(__DIR__ . '/../../app/lib/SuggestionActions.php')) {
    require_once __DIR__ . '/../../app/lib/SuggestionActions.php';
    if ($to = SuggestionActions::sameAs($pdo, (int) $id)) { header('Location: /listing/view.php?id=' . $to, true, 301); exit; }
}
if (!$biz) {
    tv_page_start('Listing not found', ['crumbs' => [['Home', '/'], ['Directory', $dir], ['Not found']]]);
    ?>
  <div class="text-center py-10">
    <h1 class="font-serif font-bold text-2xl mb-2"><?= $err ? 'Something went wrong' : 'Listing not found' ?></h1>
    <p class="text-sm text-[color:var(--tv-meta)] mb-4"><?= tv_e($err ?: "That business isn't listed, or it's no longer public.") ?></p>
    <a href="<?= tv_e($dir) ?>" class="inline-block bg-[var(--tv-btn)] hover:bg-[var(--tv-btn-hover)] text-[color:var(--tv-btn-text)] font-semibold text-sm rounded-full px-4 py-2">Browse the directory</a>
  </div>
<?php
    tv_page_end();
    exit;
}

$crumbs = [['Home', '/'], ['Directory', $dir]];
if ($crumbTrail) {
    $crumbs = array_merge($crumbs, $crumbTrail);
}
$crumbs[] = [$biz['name']];

$badges = [];
if ($biz['status'] === 'verified') {
    $badges[] = ['✓ Verified', 'lv-b-ok'];
} elseif ($biz['status'] === 'claimed-unverified' || $biz['status'] === 'pending-verification') {
    $badges[] = ['Managed by owner', 'lv-b-held'];   // claimed by email, not confirmed yet (decisions/0070)
} else {
    $badges[] = ['Unclaimed', 'bg-[var(--tv-card-b)] text-[color:var(--tv-ink)]'];
}

require_once __DIR__ . '/../../app/lib/ListingActions.php';
$mapHref = null;
$mailOnly = (string) $biz['address'] !== '' && ListingActions::isMailbox((string) $biz['address']);
if ($biz['latitude'] !== null && $biz['longitude'] !== null && !$mailOnly) {
    $mapHref = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($biz['latitude'] . ',' . $biz['longitude']);
} elseif ($biz['address'] && !ListingActions::isMailbox((string) $biz['address'])) {   // a mailbox has nowhere to drive to (2026-11-19)
    $addrParts = array_filter([$biz['address'], $biz['city'], $biz['state']]);
    $mapHref = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(implode(', ', $addrParts));
}

// crawled names in full words ("Visitor Ctr" -> "Visitor Center"), unless its owner claimed it; links keep the stored town (decisions/0058 §31)
require_once __DIR__ . '/../../app/lib/NameText.php';
$biz['name'] = NameText::listing((string) $biz['name'], (string) $biz['status']);
$cityState = trim(implode(', ', array_filter([NameText::full((string) $biz['city']), $biz['state']])));

// No breadcrumbs: the card says where it is, and its category, group and name each search for more like it (decisions/0058 §10)
tv_page_start($biz['name'], [
    'no_crumbs' => true,
    'description' => trim((string)($biz['description'] ?? '')) ?: ($biz['name'] . ($cityState ? ' — ' . $cityState : '')),
]);
$hiddenNow = (int) ($biz['is_suppressed'] ?? 0) === 1 || !in_array($biz['status'], PUBLIC_STATUSES, true);
if (!$hiddenNow) tv_count_item('listing:' . (int) $biz['id']);   // the platform's counts (decisions/0058 §13)
      // Message the business (decisions/0047 §3): only when someone manages the listing to read it. One person, one business.
      // "Managed" is the one test (decisions/0172 §3.1, gap 5): ListingActions::managed, as the search API and cards ask.
      require_once __DIR__ . '/../../app/lib/ListingActions.php';
      $managed = ListingActions::managed($pdo, (int) $biz['id']);
      // Admins get a shortcut to edit this listing (admin/listing-edit.php).
      $viewer = null;
      try {
          if (isset($_SESSION['user_id'])) {   // same check as Auth::currentUser(), without its sign-out side effects
              $st = $pdo->prepare('SELECT id, is_admin, session_version FROM users WHERE id = ? LIMIT 1');
              $st->execute([(int) $_SESSION['user_id']]);
              $u = $st->fetch(PDO::FETCH_ASSOC);
              if ($u && (int) $u['session_version'] === (int) ($_SESSION['session_version'] ?? -1)) $viewer = $u;
          }
      } catch (Throwable) { $viewer = null; }
      $isAdminViewer = false;
      if ($viewer) {
          $isAdminViewer = !empty($viewer['is_admin']);
          if (!$isAdminViewer) {
              try { $st = $pdo->prepare('SELECT 1 FROM ' . Database::t('admin_access') . ' WHERE user_id = ? LIMIT 1'); $st->execute([(int) $viewer['id']]); $isAdminViewer = (bool) $st->fetchColumn(); } catch (Throwable) {}
          }
      }
$in = $biz['city'] ? ' near ' . $biz['city'] : '';
$tel = $biz['phone'] ? preg_replace('/[^\d+]/', '', (string) $biz['phone']) : '';
$web = $biz['website'] ? (preg_match('#^https?://#i', (string) $biz['website']) ? (string) $biz['website'] : 'https://' . $biz['website']) : '';
$contact = !empty($biz['contact_url']) && preg_match('#^https?://#i', (string) $biz['contact_url']) ? (string) $biz['contact_url'] : '';
$hasPt = $biz['latitude'] !== null && $biz['longitude'] !== null && empty($privateLocation);
$eid = (int) $biz['id'];
$notBiz = PlaceScope::notABusiness($pdo, $eid);   // a drop box: listed with its pin, nothing to claim (Jason, 2026-11-15)
// what works for this listing and this viewer (decisions/0172 §3.1): Message when it's managed and you aren't its team;
// Leave a message when it isn't (§3.3); Email where its public email is known; Directions unless its location is private
$pubEmail = ListingActions::publicEmail($pdo, $eid);
$acts = ListingActions::actions($pdo, $eid, $viewer ? ['id' => (int) $viewer['id'], 'is_admin' => $isAdminViewer] : null,
    ['name' => (string) $biz['name'], 'status' => (string) $biz['status'], 'category_id' => $biz['category_id'], 'phone' => $biz['phone'], 'website' => $biz['website'],
     'contact_url' => $biz['contact_url'], 'email' => $pubEmail, 'latitude' => $biz['latitude'], 'longitude' => $biz['longitude'], 'address' => $biz['address'],
     'city' => $biz['city'], 'state' => $biz['state'], 'owner_user_id' => $biz['owner_user_id'], 'managed' => $managed], 'page');
if ($isOwner) unset($acts['message'], $acts['leave']);
$ic = fn (string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
?>
<style>
  .lv-card{position:relative;background:var(--tv-card-a);border:1px solid rgba(203,185,141,.75);border-radius:.8rem;padding:.8rem .9rem .9rem}
  .lv-help{margin-top:.6rem;background:var(--tv-card-a);border:1px solid color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-left:4px solid var(--tv-icon,#B45309);border-radius:.8rem;padding:.75rem .9rem}
  .lv-help-h{font:700 1.05rem var(--tv-font-head,Georgia,serif);color:var(--tv-ink);margin:0 0 .35rem}
  .lv-help-l{display:flex;justify-content:space-between;gap:.6rem;align-items:center;padding:.5rem .1rem;border-top:1px solid var(--tv-line);color:var(--tv-ink);text-decoration:none;font-size:.92rem}
  .lv-help-l:first-of-type{border-top:0}.lv-help-l b{color:var(--tv-link);white-space:nowrap}
  .lv-help-n{font-size:.75rem;color:var(--tv-meta);margin:.35rem 0 0}
  .lv-nx{font-size:.85rem;color:var(--tv-meta);margin:0 0 .25rem;padding-right:5.5rem}
  .lv-nx a{color:var(--tv-link);font-weight:700;text-decoration:underline}
  .nx-oth{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(17rem,100%),1fr));gap:.6rem}
  .nx-oc{position:relative;display:flex;justify-content:space-between;gap:.5rem;background:var(--tv-canvas);border:1px solid var(--tv-line);border-radius:.8rem;padding:.65rem .75rem}
  .nx-oc .n{font-weight:700;color:var(--tv-link);text-decoration:underline}
  .nx-oc small{display:block;color:var(--tv-meta);font-size:.8rem;margin-top:.1rem}
  .nx-oc .acts{display:flex;flex-direction:column;gap:.3rem;flex-shrink:0}
  .nx-oc .acts a{width:30px;height:30px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-icon,#B45309)}
  .nx-oc .acts svg{width:15px;height:15px}
  @media (max-width:767px){ .nx-oth{gap:3px} }
  .lv-kick{display:flex;flex-wrap:wrap;align-items:center;gap:.15rem .45rem;font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;color:var(--tv-meta);padding-right:5.5rem}
  .lv-q{background:none;border:0;padding:0;font:inherit;text-align:left;letter-spacing:inherit;text-transform:inherit;color:var(--tv-link);cursor:pointer;text-decoration:underline;text-decoration-color:rgba(146,64,14,.3);text-underline-offset:3px}
  .lv-q:hover{text-decoration-color:currentColor}
  .lv-kick{gap:.3rem}.lv-kick .lv-q{display:inline-flex;align-items:center;border:1px solid var(--tv-sec-edge);border-radius:999px;padding:.14rem .65rem;background:var(--tv-tag);color:var(--tv-link);text-decoration:none;text-transform:none;letter-spacing:0;font-size:.8rem;font-weight:600;line-height:1.3}
  .lv-kick .lv-q:hover{border-color:var(--tv-link);background:var(--tv-card-a)}.lv-kick>span[aria-hidden]{display:none}   /* kind and category as pills that search (CLAUDE.md: no breadcrumbs), 2026-11-19 */
  .lv-card h1{margin:.2rem 0 .15rem;padding-right:5.5rem;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:clamp(1.45rem,4.6vw,2.1rem);line-height:1.15;color:var(--tv-ink)}
  .lv-card h1 .lv-q{color:var(--tv-ink);text-transform:none;letter-spacing:0;text-decoration-color:rgba(62,43,31,.25)}
  /* actions: a column of round icons down the right edge, under the Link button; the details on the left */
  .lv-card{padding-right:.55rem}
  .lv-body{display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:.75rem;margin-top:.5rem;align-items:start}
  .lv-acts{grid-column:2;grid-row:1;display:flex;flex-direction:column;gap:.3rem}
  .lv-info{grid-column:1;grid-row:1}
  .lv-a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;color:var(--tv-icon,#B45309);background:var(--tv-field);border:1px solid var(--tv-line-strong);border-radius:999px;cursor:pointer;padding:0;text-decoration:none}
  .lv-a svg{width:17px;height:17px}.lv-a:hover{border-color:var(--tv-icon,#B45309);background:var(--tv-hold-bg)}
  .lv-info{font-size:.88rem;color:var(--tv-ink);line-height:1.45}.lv-info .m{color:var(--tv-meta)}.lv-info a{color:var(--tv-link)}
  .lv-badge{display:inline-flex;align-items:center;border-radius:999px;padding:.1rem .6rem;font-size:.72rem;font-weight:700;margin-top:.35rem}
  .lv-b-ok{background:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));color:#fff}.lv-b-held{background:var(--tv-hold);color:#fff}   /* white on Tailwind's amber was 3.2:1 */
  .lv-fix{font-size:.78rem;margin-top:.3rem}
  .lv-name{margin:.15rem 0 0;padding-right:5.5rem;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.15rem;color:var(--tv-ink)}
  .la{background:var(--tv-card-a);border:1px solid rgba(203,185,141,.75);border-radius:1rem;padding:.9rem 1rem}
  .la h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.15rem;color:var(--tv-ink);margin:0 0 .4rem}
  .la-lead{font-size:.92rem;line-height:1.55;color:var(--tv-ink);white-space:pre-line}.la-lead.clamp{display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden}
  .la-more{border:0;background:none;color:var(--tv-link);font:inherit;font-size:.85rem;font-weight:600;padding:.2rem 0;cursor:pointer;text-decoration:underline}
  .la-none{font-size:.88rem;color:var(--tv-meta);font-style:italic;margin:0}
  .la-x{border-top:1px solid var(--tv-line);margin-top:.6rem}.la-x summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;padding:.6rem 0 .3rem;font-weight:700;font-size:.9rem;color:var(--tv-ink)}
  .la-x summary::-webkit-details-marker{display:none}.la-x summary::after{content:"\25BE";color:var(--tv-icon,#B45309)}.la-x[open] summary::after{content:"\25B4"}
  .la-body{padding:.1rem 0 .4rem;font-size:.9rem;color:var(--tv-ink)}.la-pre{white-space:pre-line;margin:0}
  .la-chips{display:flex;flex-wrap:wrap;gap:.3rem;list-style:none;padding:0;margin:0}.la-chips li{font-size:.8rem;border:1px solid var(--tv-line);border-radius:999px;padding:.15rem .6rem;background:var(--tv-canvas)}
  .la-gal{display:grid;grid-template-columns:repeat(3,1fr);gap:3px}.la-gal a{aspect-ratio:1;border-radius:.4rem;background:var(--tv-umber) center/cover no-repeat}
  .la-up{font-size:.8rem;color:var(--tv-meta);background:var(--tv-canvas);border-radius:.6rem;padding:.5rem .65rem;margin:.7rem 0 0}.la-up a{color:var(--tv-link);font-weight:700}
  .lv-admin{position:absolute;right:3.2rem;top:.75rem;font-size:.72rem;font-weight:600;color:var(--tv-link);text-decoration:underline}
  /* phones: edge to edge, the phone standard (includes/app-shell.php) */
</style>
<?php   // the hero (decisions/0058 §21): the business's own photos and videos, chosen by its owner; the name, said once
require_once __DIR__ . '/../../app/ui/explore-hero.php';
require_once __DIR__ . '/../../app/ui/engage-ui.php';
require_once __DIR__ . '/../../app/lib/HeroMedia.php';
$canHero = empty($privateLocation) && HeroMedia::ready($pdo) && HeroMedia::canManage($pdo, $viewer ? (int) $viewer['id'] : null, 'listing', $eid);
// no photos of its own yet: journeys that tag it, and "Share a photo" (a journey with the business tagged); never for a confidential listing (decisions/0058 §30)
$shareBiz = empty($privateLocation) ? ['photos_of' => $biz['name'], 'tagged' => $eid, 'share_label' => 'Share a photo',
    'share' => '/user/journeys/compose.php?' . http_build_query(array_filter(['about' => $biz['name'], 'biz' => $eid, 'key' => !empty($biz['micro_cluster_id']) ? 'cluster:' . (int) $biz['micro_cluster_id'] : '',
                                                                               'pin' => $hasPt ? (float) $biz['latitude'] . ',' . (float) $biz['longitude'] : '']))] : ['empty' => ''];
if ($hiddenNow && empty($staffPeek)) {   // the business's team: their own page as it stands, and that visitors can't see it
    echo '<div class="tv-bleed" role="status" style="margin:.5rem 0;padding:.65rem .8rem;border:1px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-left:4px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-radius:.6rem;background:var(--tv-card-a);color:var(--tv-ink);font-size:.9rem">'
        . '<b>Hidden from the public.</b> Only you, your business\'s team and our staff can see this page. To ask why, or to make it public, write to '
        . '<a class="underline font-semibold text-[color:var(--tv-link)]" href="mailto:support@traversence.com">support@traversence.com</a>.</div>';
}
elseif ($hiddenNow) {   // only staff get here: say plainly it isn't public, and why
    $why = 'Hidden from the public site.';
    try {
        $hm = $pdo->prepare("SELECT meta_key, meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'same_as'");
        $hm->execute([(int) $biz['id']]);
        foreach ($hm->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            $v = json_decode((string) $v, true) ?: [];
            if ($k === 'same_as' && !empty($v['id'])) $why = 'Hidden from the public site: merged into <a class="underline" href="/listing/view.php?id=' . (int) $v['id'] . '">the listing kept</a>. Its link takes visitors there.';
        }
    } catch (Throwable) {}
    if ((int) ($biz['is_suppressed'] ?? 0) === 0) $why = 'Not public yet (' . tv_e((string) $biz['status']) . ').';
    echo '<div class="tv-bleed" role="status" style="margin:.5rem 0;padding:.65rem .8rem;border:1px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-left:4px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-radius:.6rem;background:var(--tv-card-a);color:var(--tv-ink);font-size:.9rem">'
        . '<b>Only staff can see this page.</b> ' . $why . ' <a class="underline font-semibold text-[color:var(--tv-link)]" href="/admin/listing-edit.php?id=' . (int) $biz['id'] . '">Open it in the editor</a> to put it back.</div>';
}
// closed (decisions/0072): still listed so people searching for it learn it's closed, said first and plainly
require_once __DIR__ . '/../../app/lib/SuggestionActions.php';
$closed = SuggestionActions::closedInfo($pdo, (int) $biz['id']);
require_once __DIR__ . '/../../app/lib/ListingCategories.php';
$alsoCats = ListingCategories::get($pdo, (int) $biz['id']);
require_once __DIR__ . '/../../app/lib/Nexus.php';
$nxInfo = Nexus::forListing($pdo, (int) $biz['id']);   // part of an organization with many locations (decisions/0054)
// for search engines (schema.org): what it is, its categories in order, and where; nothing private
$ld = array_filter(['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $biz['name'], 'url' => (getenv('APP_URL') ?: '') . '/listing/view.php?id=' . (int) $biz['id'],
    'keywords' => implode(', ', array_filter(array_merge([(string) $biz['category']], array_column($alsoCats, 'name')))),
    'telephone' => empty($privateLocation) ? (string) ($biz['phone'] ?? '') : '', 'sameAs' => $web ?: null,
    'address' => empty($privateLocation) ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => (string) ($biz['address'] ?? ''), 'addressLocality' => (string) ($biz['city'] ?? ''), 'addressRegion' => (string) ($biz['state'] ?? '')]) : null]);
echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
$closedBanner = '';   // shown under the hero, above the listing card (Jason, 2026-10-10)   // set first: the possibly-closed notice below was wiped by this line (fixed 2026-11-15)
// possibly closed (decisions/0133: one source says so; review B10): an amber notice, never a closing on its own
if (!$closed) {
    try {
        $cs = $pdo->prepare("SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = 'closed_signal'"); $cs->execute([(int) $biz['id']]);
        $sig = json_decode((string) $cs->fetchColumn(), true);
        if (is_array($sig) && !empty($sig['line'])) $closedBanner = '<div class="tv-bleed lv-closed" role="status" style="margin-top:.6rem;margin-bottom:.5rem;padding:.7rem .85rem;border:1px solid color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-left:4px solid var(--tv-icon,#B45309);border-radius:.6rem;background:var(--tv-card-a);color:var(--tv-ink);font-size:.92rem">'
            . '<b style="color:var(--tv-link)">Still open?</b> ' . tv_e(($sig['from'] ?? '') === 'cleanup' || ($sig['from'] ?? '') === 'refresh' || empty($sig['from']) ? 'Its own website says' : 'A public record says') . ': “' . tv_e(mb_strimwidth((string) $sig['line'], 0, 200, '…')) . '”' . (!empty($sig['at']) ? ' (seen ' . tv_e(date('M j, Y', strtotime((string) $sig['at']))) . ')' : '') . '. We\'re checking. '
            . '<span data-still-open="' . (int) $biz['id'] . '" data-csrf="' . tv_e((string) ($_SESSION['csrf_token'] ?? '')) . '" style="display:inline-flex;gap:.4rem;flex-wrap:wrap;vertical-align:middle">'
            . '<button type="button" class="underline font-semibold text-[color:var(--tv-link)]" data-so="open">Yes, it\'s open</button>'
            . '<button type="button" class="underline font-semibold text-[color:var(--tv-link)]" data-so="closed">It\'s closed</button>'
            . '<button type="button" class="underline text-[color:var(--tv-link)]" data-tv-suggest="' . tv_e(json_encode(['kind' => 'fix', 'entity_id' => (int) $biz['id'], 'target_name' => $biz['name']], JSON_UNESCAPED_UNICODE)) . '">Tell us more</button></span></div>'
            // the answers are counted per device a day, never who (P5-2); our team confirms a closing, never one answer
            . '<script>(function(){var w=document.querySelector("[data-still-open]");if(!w)return;w.addEventListener("click",function(e){var b=e.target.closest("[data-so]");if(!b)return;'
            . 'if(window.TvAsk)TvAsk.busy(b,true);fetch("/api/listing-confirm.php",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":w.getAttribute("data-csrf")||""},'
            . 'body:JSON.stringify({id:+w.getAttribute("data-still-open"),fact:b.getAttribute("data-so")})}).then(function(r){return r.json();}).then(function(j){if(window.TvAsk){TvAsk.busy(b,false);'
            . 'TvAsk.say(j.ok?"Thank you: our team checks before anything changes.":((j.error&&j.error.message)||"That didn\'t go through. Try again."),{anchor:b,bad:!j.ok});}}).catch(function(){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say("That didn\'t go through. Try again.",{anchor:b,bad:true});}});});})();</script>';
    } catch (Throwable $e) {}
}
if ($closed) {
    $closedBanner = '<div class="tv-bleed lv-closed" role="status" style="margin-top:.6rem;margin-bottom:.5rem;padding:.7rem .85rem;border:1px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-left:4px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-radius:.6rem;background:var(--tv-card-a);color:var(--tv-ink);font-size:.92rem">'
        . '<b style="color:var(--tv-fail)">Closed.</b> ' . tv_e($biz['name']) . ' has closed' . (!empty($closed['at']) ? ' (reported ' . tv_e(date('M Y', strtotime((string) $closed['at']))) . ')' : '') . '. '
        . 'We keep its listing so you know before you go. Reopened? <button type="button" class="underline font-semibold text-[color:var(--tv-link)]" data-tv-suggest="' . tv_e(json_encode(['kind' => 'fix', 'entity_id' => (int) $biz['id'], 'target_name' => $biz['name']], JSON_UNESCAPED_UNICODE)) . '">Tell us</button>.'
        . (!empty($isAdminViewer) ? ' <a class="underline" href="/admin/listing-edit.php?id=' . (int) $biz['id'] . '">Editor</a>' : '') . '</div>';
}
require_once __DIR__ . '/../../app/lib/StockLibrary.php';
echo tv_explore_hero($pdo, $shareBiz + ['name' => $biz['name'], 'heading' => $biz['name'], 'edit' => false,
    'slides' => empty($privateLocation) ? HeroMedia::slides($pdo, 'listing', $eid) : [],
    // a photo found for the place itself (a park, monument, visitor centre or historic site), credited, before any stock
    // photo (Jason, 2026-11-19; decisions/0143's note): never for a place kept private
    'fallback_slides' => empty($privateLocation) && is_file(__DIR__ . '/../../app/lib/PlacePhotos.php') ? ((require_once __DIR__ . '/../../app/lib/PlacePhotos.php') ? PlacePhotos::slides($pdo, 'listing', (string) $eid, (string) $biz['name']) : []) : [],
    'sub' => trim(implode(' · ', array_filter([$closed ? 'Closed' : '', (string) $biz['category'], $cityState]))), 'manage' => $canHero ? ['listing', $eid] : null,
    // no photos of its own yet: a stock photo of its kind of business, always "Representative" (never shown as this
    // business's own), until the owner adds theirs (decisions/0143)
    'stock' => ['subject' => (string) $biz['category'], 'place' => empty($privateLocation) ? StockLibrary::listingPlace($biz) : '', 'owner' => 'listing:' . $eid, 'representative' => true, 'fallback' => 'main street',
        'nudge' => $canHero ? '<button type="button" data-hm-kind="listing" data-hm-ref="' . $eid . '">Add your own photo</button>' : ''],
    // the corner: views, comments and ⋯ (Jason, 2026-11-19); the card's own column keeps Call, Directions, the map, Save
    // contact and QR, so ⋯ doesn't repeat them; Save to Address Book (private, decisions/0052 §2) moves into ⋯ in words
    'actions' => tv_engage_bar($pdo, 'listing', (string) $eid, ['title' => $biz['name'], 'likes' => false, 'fold' => true, 'qr' => false,
        'first' => $isOwner ? '' : '<span data-contact-save="business:' . $eid . '" data-link-label="' . tv_e($biz['name']) . '"></span>',
        'tools' => ['trip' => $hasPt ? ['lat' => (float) $biz['latitude'], 'lon' => (float) $biz['longitude']] : [],
                    'claim' => $isOwner ? ['', 'It\'s yours: Manage is in the corner.'] : ($biz['status'] === 'unclaimed' && $notBiz === '' ? '/claim.php?listing=' . $eid : ['', $notBiz !== '' ? 'Not a business: nothing to claim.' : 'Already claimed by its business.'])]]),   // ⋯: Link to, Claim, Add to a trip first (Jason, 2026-11-20)   // Suggest an edit: the page's own link under the card
    // Claim or Manage: the hero's top-right corner (decisions/0058 §34)
    'corner' => $isOwner ? '<a class="xh-a" href="/listing/businessportal.php">Manage</a>'
        : ($biz['status'] === 'unclaimed' && $notBiz === '' ? ((function () use ($pdo, $eid, $biz) { require_once __DIR__ . '/../../app/lib/Claims.php'; $kw = Claims::KIND_WORDS[Claims::kind($pdo, $eid)];   // short, and who it's for (Jason, 2026-11-19)
            return '<a class="xh-a go" href="/claim.php?listing=' . $eid . '" title="' . tv_e(sprintf($kw['tip'], $biz['name'])) . '" aria-label="' . tv_e(sprintf($kw['tip'], $biz['name'])) . '">' . tv_icon('store') . tv_e($kw['button']) . '</a>'; })()) : '')]); ?>
<div class="lv-wrap">
<?php require_once __DIR__ . '/../../app/lib/Aliases.php'; $aka = Aliases::get($pdo, $eid);   // its other names (decisions/0084)
if ($aka): ?><p class="lv-aka" style="margin:.5rem .25rem 0;font-size:.88rem;color:var(--tv-meta)">Also known as <?= implode(', ', array_map(fn ($a) => '<b>' . tv_e($a) . '</b>', $aka)) ?></p><?php endif; ?>
<?= $closedBanner ?>
<?php // how sure we are (decisions/0133): the ring heads the actions column; How we know opens under the card
require_once __DIR__ . '/../../app/ui/confidence-ui.php';
$conf = ListingConfidence::get($pdo, $eid);
?>
<article class="lv-card" data-link-host>
  <span data-link="business:<?= $eid ?>" data-link-label="<?= tv_e($biz['name']) ?>"></span>
  <?php if (isset($acts['message'])): ?>
  <a data-link-extra href="/user/dashboard.php#addressbook:biz=<?= $eid ?>" title="Message <?= tv_e($biz['name']) ?> in your Traversence inbox" aria-label="Message <?= tv_e($biz['name']) ?>" class="tv-extra">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></a>
  <?php elseif (isset($acts['leave'])): // not on Traversence yet: Leave a message, held for its claim (decisions/0172 §3.3) ?>
  <a data-link-extra href="#lm" data-lm-open title="Leave <?= tv_e($biz['name']) ?> a message: it isn't on Traversence yet" aria-label="Leave <?= tv_e($biz['name']) ?> a message" class="tv-extra">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v6M9 10h6"/></svg></a>
  <?php endif; ?>
  <?php if ($isAdminViewer): ?><a class="lv-admin" href="/admin/listing-edit.php?id=<?= $eid ?>">Edit (admin)</a><?php endif; ?>
  <?php if ($nxInfo): ?><p class="lv-nx">Part of <a href="/nexus.php?slug=<?= tv_e($nxInfo['nexus']['slug']) ?>"><?= tv_e($nxInfo['nexus']['name']) ?></a><?= $nxInfo['others'] ? ' · <a href="#nx-others" style="font-weight:400">' . count($nxInfo['others']) . ' other location' . (count($nxInfo['others']) === 1 ? '' : 's') . '</a>' : '' ?></p><?php endif; ?>
  <?php if ($biz['category']): ?>
  <p class="lv-kick">
    <?php if ($biz['category_group']): ?><button type="button" class="lv-q" data-q="<?= tv_e($biz['category_group'] . $in) ?>"><?= tv_e($biz['category_group']) ?></button><?php endif; ?>
    <button type="button" class="lv-q" data-q="<?= tv_e($biz['category'] . $in) ?>" title="More <?= tv_e(mb_strtolower((string) $biz['category'])) ?><?= tv_e($in) ?>"><?= tv_e($biz['category']) ?></button>
    <?php // also listed under (decisions/0073): each searches for more like it, after the main one
    foreach ($alsoCats as $ac): ?><span aria-hidden="true">·</span><button type="button" class="lv-q" data-q="<?= tv_e($ac['name'] . $in) ?>" title="More <?= tv_e(mb_strtolower($ac['name'])) ?><?= tv_e($in) ?>"><?= tv_e(SearchText::catLabel($ac['name'])) ?></button><?php endforeach; ?>
  </p>
  <?php endif; ?>
  <div class="lv-body">
    <div class="lv-acts" aria-label="Contact and directions">
      <?= $conf ? tv_confidence_ring($conf) : '' ?>
      <?php if ($tel): ?><a class="lv-a" href="tel:<?= tv_e($tel) ?>" title="Call" aria-label="Call" data-out="call" data-e="<?= $eid ?>"><?= $ic('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>') ?></a><?php endif; ?>
      <?php if (isset($acts['email'])): // its public email: opens the visitor's own mail app; only the tap is counted (decisions/0172 §3.3) ?><a class="lv-a" href="<?= tv_e($acts['email']['href']) ?>" title="Email them from your own mail app" aria-label="Email <?= tv_e($biz['name']) ?>" data-out="email" data-e="<?= $eid ?>"><?= $ic('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>') ?></a><?php endif; ?>
      <?php if ($mapHref && empty($privateLocation)): ?><a class="lv-a" href="<?= tv_e($mapHref) ?>" target="_blank" rel="noopener" title="Get directions" aria-label="Get directions" data-out="directions" data-e="<?= $eid ?>"><?= $ic('<polygon points="3 11 22 2 13 21 11 13 3 11"/>') ?></a><?php endif; ?>
      <?php if ($hasPt): ?><button type="button" class="lv-a" id="lv-map" title="See on map" aria-label="See on map"><?= $ic('<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><path d="M8 2v16M16 6v16"/>') ?></button><?php endif; ?>
      <?php if ($web): ?><a class="lv-a" href="<?= tv_e($web) ?>" target="_blank" rel="noopener nofollow" title="Website" aria-label="Website" data-out="website" data-e="<?= $eid ?>"><?= $ic('<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/>') ?></a><?php endif; ?>
      <?php if ($contact): ?><a class="lv-a" href="<?= tv_e($contact) ?>" target="_blank" rel="noopener nofollow" title="Contact or book online" aria-label="Contact or book online" data-out="contact" data-e="<?= $eid ?>"><?= $ic('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>') ?></a><?php endif; ?>
      <?php // Save contact (a .vcf the phone adds to Contacts; counted for the business by /api/vcard.php) and the page's QR code (decisions/0094) ?>
      <a class="lv-a" href="/api/vcard.php?listing=<?= $eid ?>" title="Save contact" aria-label="Save to your phone's contacts"><?= $ic('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>') ?></a>
      <button type="button" class="lv-a" data-qr data-qr-title="<?= tv_e($biz['name']) ?>" title="QR code" aria-label="QR code for this listing"><?= $ic('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>') ?></button>
    </div>
    <div class="lv-info">
      <?php if (!empty($privateLocation)): ?><div class="m">Its location is kept private for the safety of the people it shelters. Call to get help: they'll arrange where to meet.</div>
        <?php if ($officeAddress !== ''): ?><div>Office: <?= tv_e($officeAddress) ?></div><?php endif; ?>
      <?php elseif ($biz['address']): ?><div><?= tv_e($biz['address']) ?></div><?php endif; ?>
      <?php if ($cityState): ?><div class="m"><a href="/directory/?town=<?= rawurlencode((string) $biz['city']) ?>&amp;state=<?= rawurlencode((string) $biz['state']) ?>"><?= tv_e($cityState) ?></a></div><?php endif; ?>
      <?php if ($biz['phone']): ?><div class="m"><?= tv_e($biz['phone']) ?></div><?php endif; ?>
      <?php if (empty($privateLocation)): ?><div class="m lv-fix"><a href="/listing/location.php?id=<?= $eid ?>"><?= $isAdminViewer ? 'Location and map pin' : 'Wrong spot on the map? Fix the pin' ?></a></div><?php endif; ?>
      <?php foreach ($badges as [$label, $cls]): ?><span class="lv-badge <?= $cls ?>"><?= tv_e($label) ?></span><?php endforeach; ?>
    </div>
  </div>
</article>
<?php if ($conf) { require_once __DIR__ . '/../../app/lib/SuggestionActions.php';
    $ask = array_filter(['phone' => $biz['phone'] ? 'Phone ' . $biz['phone'] : '', 'address' => $biz['address'] && empty($privateLocation) ? (string) $biz['address'] : '',
                         'website' => $web !== '' ? 'Website ' . (parse_url($web, PHP_URL_HOST) ?: $web) : '', 'hours' => $biz['hours'] ? 'Its hours' : '',
                         'open' => SuggestionActions::closedInfo($pdo, $eid) === null ? 'It\'s open' : '']);
    echo tv_confidence_panel($conf, $eid, $ask, ['entity_id' => $eid, 'target_name' => (string) $biz['name']]) . tv_confidence_assets();
} ?>
<?php if (!empty($privateLocation)): // whoever needs it now: their line first, then the 24-hour ones ?>
<aside class="lv-help tv-bleed" aria-label="Get help now">
  <p class="lv-help-h">Need help now?</p>
  <?php if (!empty($biz['phone'])): ?><a class="lv-help-l" href="tel:<?= tv_e(preg_replace('/[^\d+]/', '', (string) $biz['phone'])) ?>" data-out="call" data-e="<?= $eid ?>"><span><?= tv_e($biz['name']) ?></span><b><?= tv_e($biz['phone']) ?></b></a><?php endif; ?>
  <?php foreach (ListingSafety::HELP_LINES as [$lab, $show, $dial]): ?><a class="lv-help-l" href="<?= str_starts_with($dial, 'sms:') ? tv_e($dial) : 'tel:' . tv_e($dial) ?>"><span><?= tv_e($lab) ?></span><b><?= tv_e($show) ?></b></a><?php endforeach; ?>
  <p class="lv-help-n">Calls and texts go straight from your phone. We don't see or keep them.</p>
</aside>
<?php endif; ?>
</div>
<script>
  (function () {
    // the group, the category and the name search for more like it, near here (the search panel)
    document.querySelector('.lv-card').addEventListener('click', function (e) { var b = e.target.closest('[data-q]'); if (b && window.TvSearch) TvSearch.open(b.getAttribute('data-q')); });
    var m = document.getElementById('lv-map'); if (!m) return;
    m.addEventListener('click', function () {   // See on map: this listing chosen, with what's around it (5 mi)
      if (!window.TvMap) return;
      var here = { lat: <?= $hasPt ? (float) $biz['latitude'] : 'null' ?>, lon: <?= $hasPt ? (float) $biz['longitude'] : 'null' ?>, radius: 5 }, id = <?= $eid ?>;
      TvMap.open({ panel: true, title: <?= json_encode($biz['name']) ?>, radius: 5, select: id,
        titleFor: function (d) { return 'Places around ' + <?= json_encode($biz['name']) ?>; },
        load: function (area, words) {
          var a = area || here, qs = new URLSearchParams({ pins: 1, kinds: 'listing,outdoors', lat: a.lat, lon: a.lon, radius: a.radius });
          if (words) qs.set('q', words);
          return fetch('/api/search.php?' + qs.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
            if (!(d.pins || []).some(function (p) { return p.id === id; })) (d.pins = d.pins || []).push({ id: id, lat: here.lat, lon: here.lon, name: <?= json_encode($biz['name']) ?>, category: <?= json_encode((string) $biz['category']) ?> });
            d.center = a; return d;
          });
        } });
    });
  })();
</script>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
  <?php
  // About (decisions/0058 §22): the description, then the add-ons a listing's plan opens, each a row that expands.
  // Free: About and hours. Core and up: what they offer. Strategic and up: an owner spotlight and a photo gallery.
  $tier = null;
  try { $st = $pdo->prepare('SELECT plan_tier FROM entities WHERE id = ?'); $st->execute([$eid]); $tier = (string) ($st->fetchColumn() ?: ''); } catch (Throwable) { $tier = ''; }
  $tier = $biz['status'] === 'unclaimed' ? '' : ($tier ?: 'free');
  $tierHas = fn (string $f) => in_array($tier, ['enhanced' => ['core', 'strategic', 'cornerstone'], 'spotlight' => ['strategic', 'cornerstone']][$f] ?? [], true);
  $meta = function (string $k) use ($pdo, $eid) {
      try { $st = $pdo->prepare('SELECT meta_value FROM entity_metadata WHERE entity_id = ? AND meta_key = ? LIMIT 1'); $st->execute([$eid, $k]); $v = $st->fetchColumn(); }
      catch (Throwable) { return null; }
      if ($v === false || $v === null) return null;
      $d = json_decode((string) $v, true);
      return $d ?? (string) $v;
  };
  $addons = [];
  if ($biz['hours']) {   // stored as a list (["Mon-Fri 9-5", "Sat 10-2"]) or as text: one line each, never the raw list
      $hj = json_decode((string) $biz['hours'], true);
      $addons[] = ['Hours', '<p class="la-pre">' . tv_e(is_array($hj) ? implode("\n", array_map('strval', $hj)) : (string) $biz['hours']) . '</p>', true];
  }
  if ($tierHas('enhanced') && ($off = $meta('offerings'))) {
      $items = is_array($off) ? array_filter(array_map('strval', $off)) : array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $off)));
      if ($items) $addons[] = ['What they offer', '<ul class="la-chips">' . implode('', array_map(fn ($x) => '<li>' . tv_e($x) . '</li>', array_slice($items, 0, 40))) . '</ul>', false];
  }
  if ($tierHas('spotlight') && is_string($sp = $meta('spotlight')) && trim($sp) !== '') $addons[] = ['Owner spotlight', '<p class="la-pre">' . tv_e($sp) . '</p>', false];
  if ($tierHas('spotlight') && HeroMedia::ready($pdo)) {
      $gal = array_filter(HeroMedia::list($pdo, 'listing', $eid), fn ($m) => $m['kind'] === 'photo');
      if (count($gal) > 1) $addons[] = ['Photos', '<div class="la-gal">' . implode('', array_map(fn ($m) => '<a href="' . tv_e($m['path']) . '" target="_blank" rel="noopener" style="background-image:url(\'' . tv_e($m['card_path']) . '\')" aria-label="Photo"></a>', $gal)) . '</div>', false];
  }
  $desc = trim((string) ($biz['description'] ?? ''));
  ?>
  <section class="sm:col-span-2 la tv-bleed" id="about">
    <h2>About</h2>
    <?php if ($desc !== ''): $long = mb_strlen($desc) > 320; ?>
      <div class="la-lead<?= $long ? ' clamp' : '' ?>" id="la-lead"><?= tv_e($desc) ?></div>
      <?php if ($long): ?><button type="button" class="la-more" onclick="var l=document.getElementById('la-lead');l.classList.toggle('clamp');this.textContent=l.classList.contains('clamp')?'Read more':'Show less'">Read more</button><?php endif; ?>
    <?php else: ?>
      <p class="la-none">No description yet<?= $isOwner ? ': add one from your business dashboard.' : '.' ?></p>
    <?php endif; ?>
    <?php foreach ($addons as [$label, $html, $open]): ?>
      <details class="la-x"<?= $open ? ' open' : '' ?>><summary><?= tv_e($label) ?></summary><div class="la-body"><?= $html ?></div></details>
    <?php endforeach; ?>
    <?php if ($isOwner && !$tierHas('spotlight')): ?>
      <p class="la-up">Grow this About section: <?= $tierHas('enhanced') ? '' : '<b>Core</b> adds what you offer; ' ?><b>Strategic</b> adds an owner spotlight and a photo gallery. <a href="/listing/businessportal.php">See plans</a></p>
    <?php endif; ?>
  </section>

  <aside class="bg-[var(--tv-canvas)] border border-[color:var(--tv-line-strong)]/70 rounded-2xl shadow-md p-5 space-y-3 text-sm">
    <?php // address, phone, website and the contact page are in the card at the top, with their actions ?>
    <?php if (!$isOwner): ?>
      <?php if ($managed): ?><div id="gq" data-e="<?= (int) $biz['id'] ?>" data-name="<?= tv_e($biz['name']) ?>" data-signed-in="<?= $viewerId !== null ? '1' : '0' ?>"></div><?php endif; // guest questions: decisions/0052 §5 ?>
      <?php if (isset($acts['leave'])): // Leave a message on an unclaimed listing, held for its claim or emailed to its public address (decisions/0172 §3.3)
        require_once __DIR__ . '/../../app/lib/ListingMessages.php';
        $lmRoute = ListingMessages::routeFor($pdo, $eid); [$lmBefore, $lmButton] = ListingMessages::words($lmRoute); ?>
      <div id="lm" data-e="<?= $eid ?>" data-name="<?= tv_e($biz['name']) ?>" data-route="<?= tv_e($lmRoute) ?>" data-before="<?= tv_e($lmBefore) ?>" data-button="<?= tv_e($lmButton) ?>"
           data-signed-in="<?= $viewerId !== null ? '1' : '0' ?>" data-call="<?= tv_e($tel !== '' ? 'tel:' . $tel : '') ?>"></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isOwner): // what we found about the business, offered to its owner (decisions/0062, 2026-10-04): their call ?>
      <style>
        .ev{border:1px solid var(--tv-line);border-radius:.5rem;background:var(--tv-card-a);padding:.7rem .75rem}
        .ev-h{font-weight:700;color:var(--tv-ink);margin:0}.ev-sub{font-size:12px;color:var(--tv-meta);margin:.15rem 0 .4rem}
        .ev-it{border-top:1px solid var(--tv-line);padding:.5rem 0 .2rem}.ev-l{font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--tv-meta);margin:0}
        .ev-v{font-weight:600;color:var(--tv-ink);margin:.1rem 0;word-break:break-word}.ev-n{font-size:12px;color:var(--tv-meta);margin:0}.ev-n a{color:var(--tv-link);text-decoration:underline}
        .ev-b{display:flex;gap:.4rem;margin:.4rem 0 0}.ev-b button{border-radius:999px;padding:.3rem .8rem;font-size:12px;font-weight:600;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink)}
        .ev-b .ev-use{background:var(--tv-btn);border-color:var(--tv-btn);color:var(--tv-btn-text)}
      </style>
      <div id="ev-offers" class="ev" data-e="<?= (int) $biz['id'] ?>" hidden></div>
      <script src="/js/evidence-offers.js?v=<?= (int) @filemtime(__DIR__ . '/../js/evidence-offers.js') ?>" defer></script>
    <?php endif; ?>
    <div class="pt-2 border-t border-[color:var(--tv-line-strong)]/60">
      <?php if ($isOwner): ?>
        <a href="/listing/businessportal.php" class="block text-center bg-[var(--tv-btn)] hover:bg-[var(--tv-btn-hover)] text-[color:var(--tv-btn-text)] font-semibold text-sm rounded-full px-4 py-2 transition">Manage this listing</a>
      <?php elseif ($notBiz !== ''): ?>
        <p class="text-[12px] text-[color:var(--tv-meta)]"><?= tv_e($notBiz) ?> Something wrong or missing, like its pickup times?</p>
        <a href="/claim.php?listing=<?= (int)$biz['id'] ?>&mode=contribute" class="block text-center border border-[color:var(--tv-line-strong)] hover:bg-[var(--tv-card-b)] text-[color:var(--tv-ink)] font-semibold text-sm rounded-xl px-4 py-2 mt-2 transition">Suggest an edit</a>
      <?php elseif ($biz['status'] === 'unclaimed'): ?>
        <p class="text-[12px] text-[color:var(--tv-meta)]">Are you the owner? <a class="underline font-semibold" href="/claim.php?listing=<?= (int)$biz['id'] ?>">Claim it</a> to add photos, hours and more.</p>
      <?php else: ?>
        <a href="/claim.php?listing=<?= (int)$biz['id'] ?>&mode=contribute" class="block text-center border border-[color:var(--tv-line-strong)] hover:bg-[var(--tv-card-b)] text-[color:var(--tv-ink)] font-semibold text-sm rounded-xl px-4 py-2 transition">Suggest an edit</a>
        <p class="text-[12px] text-[color:var(--tv-meta)] mt-2 text-center">Is this your business? <a class="underline font-semibold" href="/claim.php?listing=<?= (int)$biz['id'] ?>">Ask to claim it</a></p>
      <?php endif; ?>
      <?php if (!$isOwner): // closed, listed twice, wrong details: a person reviews it (decisions/0053) ?>
        <?php // freshness shown (decisions/0133): when it was listed, and when our crawler or its owner last checked it
          $lvF = $pdo->prepare("SELECT e.created_at, e.updated_at, (SELECT MAX(m.updated_at) FROM entity_metadata m WHERE m.entity_id = e.id AND m.meta_key = 'crawler_refreshed_at') AS checked FROM entities e WHERE e.id = ?");
          $lvF->execute([(int) $biz['id']]); $lvF = $lvF->fetch(PDO::FETCH_ASSOC) ?: []; ?>
        <?php if (!empty($lvF['created_at'])): ?><p class="text-[12px] text-[color:var(--tv-meta)] mt-2 text-center">Listed since <?= tv_e(date('F Y', strtotime((string) $lvF['created_at']))) ?> · checked <?= tv_e(date('M j, Y', max(strtotime((string) $lvF['updated_at']), strtotime((string) ($lvF['checked'] ?: '2000-01-01'))))) ?></p><?php endif; ?>
        <p class="text-[12px] text-[color:var(--tv-meta)] mt-2 text-center"><button type="button" class="underline" data-tv-suggest="<?= tv_e(json_encode(['entity_id' => (int) $biz['id'], 'entity_name' => (string) $biz['name']], JSON_UNESCAPED_UNICODE)) ?>">Closed, moved or listed twice? Tell us</button></p>
      <?php endif; ?>
    </div>
  </aside>
</div>

<?php if ($nxInfo && $nxInfo['others']): $oth = array_slice($nxInfo['others'], 0, 6); ?>
<section class="mt-6" id="nx-others">
  <h2 class="font-serif font-bold text-lg text-[color:var(--tv-ink)] mb-3">Other <?= tv_e($nxInfo['nexus']['name']) ?> locations</h2>
  <div class="nx-oth">
    <?php foreach ($oth as $o): $otel = preg_replace('/[^\d+]/', '', $o['phone']); $odir = $o['address'] !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($o['address'] . ', ' . $o['city'] . ' ' . $o['state']) : ''; ?>
    <div class="nx-oc tv-bleed"><div><a class="n" href="/listing/view.php?id=<?= $o['id'] ?>"><?= tv_e($o['name']) ?></a><?= $o['city'] !== '' && mb_stripos($o['name'], $o['city']) === false ? ' <b style="color:var(--tv-ink)">· ' . tv_e($o['city']) . '</b>' : '' ?>
      <small><?= tv_e(implode(' · ', array_filter([$o['closed'] ? 'Closed' : '', $o['role'] !== 'location' ? (Nexus::ROLES[$o['role']] ?? '') : '', $o['category']]))) ?></small>
      <small><?= tv_e(trim($o['address'] . ($o['city'] !== '' ? ', ' . $o['city'] : ''), ', ')) ?><?= $o['miles'] !== null ? ' · ' . ($o['miles'] < 1 ? '<1' : (string) round($o['miles'])) . ' mi' : '' ?></small></div>
      <div class="acts"><?php if ($otel): ?><a href="tel:<?= tv_e($otel) ?>" title="Call" aria-label="Call <?= tv_e($o['name']) ?>" data-out="call" data-e="<?= $o['id'] ?>"><?= $ic('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>') ?></a><?php endif; ?>
        <?php if ($odir): ?><a href="<?= tv_e($odir) ?>" target="_blank" rel="noopener" title="Directions" aria-label="Directions to <?= tv_e($o['name']) ?>" data-out="directions" data-e="<?= $o['id'] ?>"><?= $ic('<polygon points="3 11 22 2 13 21 11 13 3 11"/>') ?></a><?php endif; ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($nxInfo['others']) > count($oth)): ?><p class="mt-2 text-sm"><a class="underline text-[color:var(--tv-link)]" href="/nexus.php?slug=<?= tv_e($nxInfo['nexus']['slug']) ?>">See all <?= count($nxInfo['others']) + 1 ?> locations</a></p><?php endif; ?>
</section>
<?php endif; ?>
<?php
// journeys from this business's team, and journeys that mention it (decisions/0058 §2): Let's Explore meets Get Local
require_once __DIR__ . '/../../app/lib/Journeys.php';
require_once __DIR__ . '/../../app/ui/journey-ui.php';
$lsFrom = Journeys::cardsFor($pdo, 'listing', (int) $biz['id'], 6);
$lsAbout = Journeys::cardsFor($pdo, 'mentions', (int) $biz['id'], 6);
if ($lsFrom || $lsAbout):
    echo tv_journey_css();
    foreach ([[$lsFrom, 'From ' . $biz['name']], [$lsAbout, 'Journeys that mention ' . $biz['name']]] as [$cards, $head]): if (!$cards) continue; ?>
<section class="mt-6">
  <h2 class="font-serif font-bold text-lg text-[color:var(--tv-ink)] mb-3"><?= tv_e($head) ?></h2>
  <div class="jr-grid"><?php foreach ($cards as $it) echo tv_journey_card($it); ?></div>
</section>
<?php endforeach; endif;
// its Shop (decisions/0135 step 3): the items it published to the Marketplace, on its own page, from Basic Partner up
require_once __DIR__ . '/../../app/lib/MarketBiz.php';
try {
    if (MarketBiz::ready($pdo)) {
        $shopTier = MarketBiz::tier($pdo, (int) $eid);
        [$shopOn] = Plan::allows($pdo, $shopTier, 'quotes');   // the Shop tab opens with Basic (business-portal-architecture.md §4)
        $shopTeam = Auth::currentUserId() && MarketBiz::onTeam($pdo, (int) Auth::currentUserId(), (int) $eid);
        $shop = $shopOn ? MarketBiz::browse($pdo, [], 24, (int) $eid) : [];
        if ($shop || ($shopTeam && $shopOn)) {
            require_once __DIR__ . '/../../app/ui/market-ui.php';
            echo tv_market_css() . '<section class="mt-6" id="shop"><div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:.4rem 1rem;margin:0 0 .6rem"><h2 class="font-serif font-bold text-lg text-[color:var(--tv-ink)]" style="margin:0">Shop</h2>'
                . (function () use ($pdo, $eid) {   // its storefront on trvgo.us, when it's open (decisions/0139)
                    try { require_once __DIR__ . '/../../app/lib/Storefront.php'; if (!Storefront::ready($pdo) || !($sf = Storefront::get($pdo, (int) $eid)) || !Storefront::showing($pdo, $sf)) return '';
                        return '<a class="underline text-sm" href="' . tv_e(Storefront::url($sf)) . '" target="_blank" rel="noopener">Visit the storefront ↗</a>'; } catch (Throwable) { return ''; }
                })()
                . ($shopTeam ? '<a class="underline text-sm" href="/business/' . (int) $eid . '/marketplace">Publish items from your catalog</a>' : '') . '</div>';
            echo $shop ? '<div class="mk-grid">' . implode('', array_map(fn ($c) => tv_market_card($c, false, false), $shop)) . '</div>' : '<p class="text-sm text-[color:var(--tv-meta)]">Nothing published yet. Items you publish from your catalog show here and on the Marketplace.</p>';
            echo '</section>';
        }
    }
} catch (Throwable $x) { error_log('listing shop: ' . $x->getMessage()); }
// the business's public albums (the gallery, decisions/0126 phase 3), and a way in for its team
require_once __DIR__ . '/../../app/lib/Gallery.php';
require_once __DIR__ . '/../../app/ui/gallery-ui.php';
try {
    $lgAlbums = Gallery::albums($pdo, 'businesses', (string) $eid, false);
    $lgTeam = Gallery::canManage($pdo, Auth::currentUserId(), 'businesses', (string) $eid);
    if ($lgAlbums || $lgTeam) {
        echo tv_gallery_css() . '<section class="mt-6" id="albums"><div class="gl-h" style="margin:0 0 .6rem"><h2 class="font-serif font-bold text-lg text-[color:var(--tv-ink)]" style="margin-right:auto">Albums</h2>'
            . ($lgTeam ? '<a class="gl-b" href="/gallery/?b=' . (int) $eid . '">Manage the gallery</a>' : '') . '</div>';
        if ($lgAlbums) { echo '<div class="gl-albums tv-bleed">'; foreach ($lgAlbums as $la) echo tv_gallery_album_card($la, '/gallery/album.php?a=' . (int) $la['id'], false); echo '</div>'; }
        else echo '<p class="text-sm text-[color:var(--tv-meta)]">No public albums yet. Albums you make public in the gallery show here.</p>';
        echo '</section>';
    }
} catch (Throwable $x) { error_log('listing albums: ' . $x->getMessage()); }
require_once __DIR__ . '/../../app/lib/LiveEvents.php';   // what's coming up here (Live events: this listing is the venue)
$tvEv = LiveEvents::forEntity($pdo, (int) $eid, 5);
if ($tvEv) echo '<section class="tv-fi tv-bleed" aria-labelledby="tv-ev-h"><h2 id="tv-ev-h" style="font:700 1.1rem var(--tv-font-head,Georgia,serif);color:var(--tv-ink);margin:0 0 .6rem">Coming up here</h2>' . LiveEvents::html($tvEv, false) . '<p style="font-size:.8rem;color:var(--tv-meta);margin:0">Dates come from each event\'s own page: check it before you go.</p></section>';
// its Videos: the business's Channel (kept videos from posts shared with everyone) and playlists (decisions/0153 §7)
try {
    require_once __DIR__ . '/../../app/ui/gallery-ui.php'; require_once __DIR__ . '/../../app/lib/MemberPosts.php';
    $lvTeam = MemberPosts::ready($pdo) && MemberPosts::canPostAs($pdo, (int) (Auth::currentUserId() ?? 0), (int) $eid);
    if ($lvTeam) MemberPosts::fillChannel($pdo, 'businesses', (string) $eid, (int) Auth::currentUserId());
    $lvH = tv_videos_section($pdo, 'businesses', (string) $eid, false, '/listing/view.php?id=' . (int) $eid, '/gallery/?b=' . (int) $eid);
    if ($lvH !== '') echo '<section class="mt-6">' . $lvH . '</section>';
} catch (Throwable $x) { error_log('listing videos: ' . $x->getMessage()); }
// the business's posts (decisions/0153): its team posts here as the business; visitors see what's shared with them
try {
    require_once __DIR__ . '/../../app/lib/MemberPosts.php';
    if (MemberPosts::ready($pdo)) {
        $lpTeam = MemberPosts::canPostAs($pdo, (int) (Auth::currentUserId() ?? 0), (int) $eid);
        echo '<section class="mt-6" id="posts"><div id="tvm-bcomp" style="margin:0 0 .6rem"></div><div id="tvm-bfeed"></div></section>'
            . '<script src="/js/tv-moments.js?v=' . (int) @filemtime(__DIR__ . '/../js/tv-moments.js') . '"></script><script>'
            . ($lpTeam ? 'TvMoments.composer(document.getElementById("tvm-bcomp"), ' . json_encode(['name' => (string) ($biz['name'] ?? 'the business'), 'as' => (int) $eid], JSON_HEX_TAG | JSON_HEX_AMP) . ');' : '')
            . 'TvMoments.feed(document.getElementById("tvm-bfeed"), ' . json_encode(['business' => (int) $eid, 'self' => $lpTeam, 'hideEmpty' => true]) . ');</script>';
    }
} catch (Throwable $x) { error_log('listing posts: ' . $x->getMessage()); }
require_once __DIR__ . '/../../app/ui/featured-in.php';
echo tv_featured_in($pdo, 'entity:' . (int) $eid);   // the guides (and later journeys, stories) that name it (decisions/0132)
echo tv_engage_comments($pdo, 'listing', (string) $eid, ['title' => (string) $biz['name'], 'starters' => ['Best thing to order', 'Parking', 'Good for kids', 'Accessible?']]);   // the box that invites a tip (2026-11-19)
echo '<script src="/js/link-button.js" defer></script>';
echo '<script src="/js/guest-inquiry.js" defer></script>';
if (isset($acts['leave'])) echo '<script src="/js/listing-leave.js?v=' . (int) @filemtime(__DIR__ . '/../js/listing-leave.js') . '" defer></script>';   // Leave a message (decisions/0172 §3.3)   // guest questions (decisions/0052 §5)   // the Link button (decisions/0047)
tv_page_end();
