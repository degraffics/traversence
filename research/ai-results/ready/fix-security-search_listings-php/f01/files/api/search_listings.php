<?php
// api/search_listings.php
//
// Public directory search. Reads `entities` (entity_type = 'merchant') plus the
// descriptive fields stored in `entity_metadata`, NOT the legacy `listings`
// table (every row there is still 'pending', and it returned every column).
//
// Public-safety rules enforced HERE, not in the browser:
//   * Visibility is a WHITELIST of entity statuses (PUBLIC_STATUSES). A status
//     added to the enum later stays hidden until it is added below on purpose.
//   * is_suppressed = 1 rows never appear.
//   * Only an explicit column list leaves this file (no SELECT *), so internal
//     fields — owner_user_id, trust_score, composite_hash, created_by_type,
//     contact names, etc. — cannot leak.
//   * Results are paginated server-side; per_page is capped.
//   * Errors are logged, never echoed: the client gets a fixed message.
//   * Confidential listings (ListingSafety::isConfidential, decisions/0044) never
//     give out a point: no address or lat/lon on cards, and no pin on the map
//     (review f01: the map view used to send their exact point and phone).
//
// Filter panel (decisions/0053 §4): `category` may be several slugs, comma-separated; `has=phone,website`;
// `open=now` with `at=DOW,MINUTES` (the visitor's own local day and time) or `open=sun|mon|…|sat`. Hours are read by
// api/lib/Hours.php; unreadable hours never count as open.
//
// Categories: `category` is a categories.slug (a browse GROUP such as
// 'food-and-dining', or a specific leaf). A group includes all of its leaves.
// Requires migrations/2026-09-19_categories.sql (categories table +
// entities.category_id).

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../app/lib/NameText.php';
require_once __DIR__ . '/../../app/lib/CategoryAlso.php';
require_once __DIR__ . '/../../app/lib/Database.php';
require_once __DIR__ . '/../../app/lib/PlaceScope.php';
require_once __DIR__ . '/../../app/lib/SearchText.php';
require_once __DIR__ . '/../../app/lib/ListingSafety.php';
require_once __DIR__ . '/../../app/lib/Hours.php';

// Statuses the public directory may show. Unclaimed (crawler-prepopulated)
// listings ARE public — that is what the claim flow is for. Deliberately NOT
// listed: 'disputed', 'pending-opt-out', 'opted-out'.
const PUBLIC_STATUSES = ['unclaimed', 'claimed-unverified', 'pending-verification', 'verified'];

const MAX_PER_PAGE = 100;   // the lists' standard offers 10, 25, 50 or 100 a page (2026-11-12)
const DEFAULT_PER_PAGE = 9;
const MAX_PINS = 5000; // map view: most pins one response carries (the map clusters them)

function fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// LIKE-escape with '!' (declared via ESCAPE '!') so user input can't inject
// wildcards and the same SQL means the same thing on every engine.
function like_contains(string $s): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s) . '%';
}

$q        = trim((string)($_GET['q'] ?? ''));
$state    = trim((string)($_GET['state'] ?? 'all'));
$city     = trim((string)($_GET['city'] ?? 'all'));
$zip      = trim((string)($_GET['zip'] ?? ''));
$category = trim((string)($_GET['category'] ?? 'all'));
$has      = array_values(array_intersect(explode(',', (string)($_GET['has'] ?? '')), ['phone', 'website']));
$open     = strtolower(trim((string)($_GET['open'] ?? '')));
$openAt   = array_map('intval', explode(',', (string)($_GET['at'] ?? '')));
$sort     = (string)($_GET['sort'] ?? '');
$radius   = max(1, min(250, (int)($_GET['radius'] ?? 10)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = max(1, min(MAX_PER_PAGE, (int)($_GET['per_page'] ?? DEFAULT_PER_PAGE)));
$view     = (string)($_GET['view'] ?? '');
$wantFacets = (($_GET['facets'] ?? '') === '1');
$inTown   = mb_substr(trim((string)($_GET['in_town'] ?? '')), 0, 80);   // one town of the results (the finder's Town list, 2026-11-12)   // 'map' = lightweight pins instead of a page of cards
$latIn    = $_GET['lat'] ?? '';
$lonIn    = $_GET['lon'] ?? '';

if (mb_strlen($q) > 100 || mb_strlen($category) > 400 || mb_strlen($city) > 100 || mb_strlen($zip) > 10) {
    fail(400, 'Invalid search.');
}

try {
    $pdo = Database::connection();

    // ---- Region scope (hub / geo-hub / cluster) ----
    // A region is an area, not a point: when one is requested it replaces the
    // distance search entirely (no radius, no center). See api/lib/PlaceScope.php.
    $scope = PlaceScope::resolve($pdo, $_GET);

    // ---- Resolve the search center (unchanged precedence: GPS > zip > city) ----
    $centerLat = null;
    $centerLon = null;
    $locationResolved = true;

    if ($scope !== null) {
        // region scope: nothing to resolve here
    } elseif ($latIn !== '' && $lonIn !== '' && is_numeric($latIn) && is_numeric($lonIn)) {
        $centerLat = (float)$latIn;
        $centerLon = (float)$lonIn;
    } elseif ($zip !== '') {
        $locationResolved = false;
        $stmt = $pdo->prepare('SELECT latitude, longitude FROM zip_coordinates WHERE zip = TRIM(?) LIMIT 1');
        $stmt->execute([$zip]);
        if ($coord = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $centerLat = (float)$coord['latitude'];
            $centerLon = (float)$coord['longitude'];
            $locationResolved = true;
        }
    } elseif ($city !== 'all' && $city !== '') {
        $locationResolved = false;
        $sql = 'SELECT latitude, longitude FROM zip_coordinates WHERE city = TRIM(?)';
        $args = [$city];
        if ($state !== 'all' && $state !== '') {
            $sql .= ' AND LOWER(TRIM(state)) = LOWER(TRIM(?))';
            $args[] = $state;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($args);
        if ($coord = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $centerLat = (float)$coord['latitude'];
            $centerLon = (float)$coord['longitude'];
            $locationResolved = true;
        }
    }

    $hasLocation = ($centerLat !== null && $centerLon !== null);

    // ---- Never dump the whole directory ----
    // With no place, no words and no category there is nothing to search for.
    // The page shows its browse view instead (categories + places to narrow to).
    $hasCriteria = $scope !== null || $hasLocation || $q !== ''
        || ($category !== 'all' && $category !== '') || $has || $open !== ''
        || $zip !== '' || ($city !== 'all' && $city !== '') || ($state !== 'all' && $state !== '');
    if (!$hasCriteria) {
        echo json_encode([
            'success' => true, 'needs_filter' => true, 'listings' => [], 'total' => 0, 'page' => 1,
            'per_page' => $perPage, 'total_pages' => 1, 'location_resolved' => true,
            'location_filtered' => false, 'scope' => null,
        ]);
        exit;
    }

    // ---- Resolve the category filter (slug -> id) ----
    $categoryIds = [];
    $categoryUnknown = false;
    if ($category !== 'all' && $category !== '') {
        $slugs = array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', $category))))), 0, 12);
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1');
        foreach ($slugs as $sl) {
            $stmt->execute([$sl]);
            $found = $stmt->fetchColumn();
            if ($found !== false) $categoryIds[] = (int)$found;
        }
        if (!$categoryIds) $categoryUnknown = true; // unknown/inactive slug(s): match nothing, don't silently ignore it
        // the categories that share a plain name are one kind to a visitor ("Self Storage" is three of ours) (decisions/0068)
        if ($categoryIds && SearchText::catNameSql($pdo, 'c') !== 'c.name') {
            try {
                $in = implode(',', array_map('intval', $categoryIds));
                foreach ($pdo->query("SELECT s.id FROM categories c JOIN categories s ON s.display_name = c.display_name AND s.is_active = 1
                                      WHERE c.id IN ($in) AND c.display_name IS NOT NULL AND c.display_name <> ''")->fetchAll(PDO::FETCH_COLUMN) as $sid) $categoryIds[] = (int) $sid;
                $categoryIds = array_values(array_unique($categoryIds));
            } catch (Throwable) {}
        }
    }
    $categoryId = count($categoryIds) === 1 ? $categoryIds[0] : null;

    // ---- Inner query: public entities + their metadata, box-prefiltered ----
    // Placeholders are never reused (non-emulated prepares reject that), hence
    // :lat1/:lat2. The status list is built from the constant above, never
    // from input.
    $statusList = "'" . implode("','", PUBLIC_STATUSES) . "'";
    $params = [];

    if ($hasLocation) {
        $distanceExpr = '3959 * acos(LEAST(1, GREATEST(-1,
              cos(radians(:lat1)) * cos(radians(e.latitude)) *
              cos(radians(e.longitude) - radians(:lon)) +
              sin(radians(:lat2)) * sin(radians(e.latitude)))))';
        $params += [':lat1' => $centerLat, ':lat2' => $centerLat, ':lon' => $centerLon];
        $distParams = [':lat1' => $centerLat, ':lat2' => $centerLat, ':lon' => $centerLon];

        // Bounding box first (uses idx_entities_lat_lon), exact distance after.
        $latDelta = $radius / 69.0;
        $lonDivisor = 69.0 * cos(deg2rad($centerLat));
        $lonDelta = (abs($lonDivisor) > 0.0001) ? $radius / abs($lonDivisor) : 180.0;
        $boxSql = ' AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
                    AND e.latitude BETWEEN :lat_min AND :lat_max
                    AND e.longitude BETWEEN :lon_min AND :lon_max';
        $params += [
            ':lat_min' => $centerLat - $latDelta, ':lat_max' => $centerLat + $latDelta,
            ':lon_min' => $centerLon - $lonDelta, ':lon_max' => $centerLon + $lonDelta,
        ];
    } else {
        $distanceExpr = 'NULL';
        $distParams = [];
        $boxSql = ($scope !== null) ? ' AND ' . $scope['sql'] : '';
    }

    // Two passes keep the sort light: pass 1 filters, sorts and pages on short
    // columns only (the long description/address/phone are not carried through the
    // sort, which can exhaust MySQL's sort memory on shared hosting); pass 2 loads
    // the full rows for just the ids on this page.
    $catNm = SearchText::catNameSql($pdo, 'c'); $grpNm = SearchText::catNameSql($pdo, 'g');   // plain names (decisions/0068)
    $buildInner = function (bool $full, bool $phone = false) use ($distanceExpr, $statusList, $boxSql, $q, $catNm, $grpNm) {
        $withDesc = $full || $q !== '';
        $withOffer = $q !== '';   // typed words also match the listing's offerings (Listing Intake §31)
        $withPhone = $full || $phone;
        return "SELECT e.id, e.name AS business_name, e.status, e.category_id,
                     " . ($full ? "JSON_UNQUOTE(m_city.meta_value)" : "CAST(JSON_UNQUOTE(m_city.meta_value) AS CHAR(120))") . "  AS city,
                     " . ($full ? "JSON_UNQUOTE(m_state.meta_value)" : "CAST(JSON_UNQUOTE(m_state.meta_value) AS CHAR(40))") . " AS state,
                     COALESCE($catNm, " . ($full ? "JSON_UNQUOTE(m_cat.meta_value)" : "CAST(JSON_UNQUOTE(m_cat.meta_value) AS CHAR(120))") . ") COLLATE utf8mb4_general_ci AS category,
                     $grpNm AS category_group,
                     e.latitude AS lat, e.longitude AS lon,
                     " . ($full ? "JSON_UNQUOTE(m_addr.meta_value)  AS address," : '') . "
                     " . ($withPhone ? "JSON_UNQUOTE(m_phone.meta_value) AS phone," : '') . "
                     " . ($withDesc ? "JSON_UNQUOTE(m_desc.meta_value)  AS description," : '') . "
                     " . ($withOffer ? "REPLACE(REPLACE(REPLACE(REPLACE(CONCAT_WS(' ', CAST(m_offer.meta_value AS CHAR(2000)), CAST(m_aka.meta_value AS CHAR(1000))), '[', ' '), ']', ' '), '\"', ' '), ',', ' ') COLLATE utf8mb4_general_ci AS offerings," : '') . "
                     $distanceExpr AS distance
              FROM entities e
              LEFT JOIN entity_metadata m_city  ON m_city.entity_id  = e.id AND m_city.meta_key  = 'city'
              LEFT JOIN entity_metadata m_state ON m_state.entity_id = e.id AND m_state.meta_key = 'state'
              LEFT JOIN entity_metadata m_cat   ON m_cat.entity_id   = e.id AND m_cat.meta_key   = 'category'
              LEFT JOIN categories c ON c.id = e.category_id
              LEFT JOIN categories g ON g.id = c.parent_id
              " . ($full ? "LEFT JOIN entity_metadata m_addr  ON m_addr.entity_id  = e.id AND m_addr.meta_key  = 'address'" : '') . "
              " . ($withPhone ? "LEFT JOIN entity_metadata m_phone ON m_phone.entity_id = e.id AND m_phone.meta_key = 'phone'" : '') . "
              " . ($withDesc ? "LEFT JOIN entity_metadata m_desc  ON m_desc.entity_id  = e.id AND m_desc.meta_key  = 'description'" : '') . "
              " . ($withOffer ? "LEFT JOIN entity_metadata m_offer ON m_offer.entity_id = e.id AND m_offer.meta_key = 'offerings'
                                 LEFT JOIN entity_metadata m_aka ON m_aka.entity_id = e.id AND m_aka.meta_key = 'aka'" : '') . "
              WHERE e.entity_type = 'merchant'
                AND e.is_suppressed = 0
                AND e.status IN ($statusList)
                " . ($full ? '' : $boxSql);
    };
    $inner = $buildInner(false);

    // ---- Outer filters (on the aliased columns) ----
    $where = ' WHERE 1=1';
    // browsing businesses: places that aren't businesses (drop boxes, PlaceScope::NOT_A_BUSINESS) stay out; typed words, the
    // map and choosing their kind still find them (Jason, 2026-11-15)
    if ($q === '' && $view !== 'map' && !$categoryIds && !isset($_GET['ids'])) $where .= ' AND ' . PlaceScope::businessOnly('x');
    if ($hasLocation) {
        $where .= ' AND x.distance <= :radius';
        $params[':radius'] = $radius;
    }
    // Text comparisons name their collation. Columns read from JSON metadata come back
    // utf8mb4_bin and COALESCE/CAST mixes give "no collation", so an unqualified LIKE
    // or = against a bound value fails with MySQL error 1267 (illegal mix of collations).
    // The collation goes on the COLUMN only: a bound value is a binary string, and
    // MySQL rejects COLLATE on it (error 1253).
    $ci = ' COLLATE utf8mb4_general_ci';
    if ($state !== 'all' && $state !== '') {
        $where .= " AND x.state$ci = :state";
        $params[':state'] = $state;
    }
    foreach ($has as $h) {
        $where .= " AND EXISTS (SELECT 1 FROM entity_metadata hx WHERE hx.entity_id = x.id AND hx.meta_key = '$h' AND COALESCE(JSON_UNQUOTE(hx.meta_value), '') NOT IN ('', 'null', '[]'))";
    }
    $dayIdx = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
    if ($open === 'now' || isset($dayIdx[$open])) {
        // Read every listing's hours once and keep the ones open then; unreadable hours never count as open.
        $okIds = [];
        foreach ($pdo->query("SELECT entity_id, meta_value FROM entity_metadata WHERE meta_key = 'hours'")->fetchAll(PDO::FETCH_ASSOC) as $hr) {
            $week = Hours::parse(json_decode((string)$hr['meta_value'], true) ?? (string)$hr['meta_value']);
            $isOpen = $open === 'now'
                ? Hours::openAt($week, max(0, min(6, $openAt[0] ?? (int)date('w'))), max(0, min(1439, $openAt[1] ?? ((int)date('G') * 60 + (int)date('i')))))
                : Hours::openOn($week, $dayIdx[$open]);
            if ($isOpen === true) $okIds[] = (int)$hr['entity_id'];
        }
        $where .= $okIds ? ' AND x.id IN (' . implode(',', $okIds) . ')' : ' AND 1 = 0';   // integers only
        $where .= " AND NOT EXISTS (SELECT 1 FROM entity_metadata cx WHERE cx.entity_id = x.id AND cx.meta_key = 'closed')";   // marked closed: never open
    }
    // Arrange → Show (Jason, 2026-11-19): closed for good shows by default, marked Closed (never hidden by us); a person may hide it for themselves
    if (($_GET['closed'] ?? '') === 'hide') $where .= " AND NOT EXISTS (SELECT 1 FROM entity_metadata cz WHERE cz.entity_id = x.id AND cz.meta_key = 'closed')";
    // Favorites: only these listings (the person's own saved and linked businesses; the page sends their ids)
    if (isset($_GET['ids'])) {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$_GET['ids']))))), 0, 500);
        $where .= $ids ? ' AND x.id IN (' . implode(',', $ids) . ')' : ' AND 1 = 0';   // integers only
    }
    // Typed words: STRICT (each word starts a word in the name/category/town; descriptions
    // for 4+ letter words). If that finds fewer than SearchText::LOOSE_BELOW results the
    // search is repeated LOOSE (the word anywhere), so nothing is lost. See api/lib/SearchText.php.
    $matchMode = 'strict';
    $qParams = [];
    $qSql = ($q !== '') ? SearchText::where($q, true, $qParams, ['x.offerings']) : '';
    $catSql = '';
    $catParams = [];
    if ($categoryUnknown) {
        $catSql = ' AND 1 = 0';
    } elseif ($categoryIds) {
        // A group id matches its leaves (parent_id); a leaf id matches itself. Several categories: any of them.
        $or = [];
        foreach ($categoryIds as $ci_ => $cid) {
            $or[] = "id = :cat_a$ci_ OR parent_id = :cat_b$ci_" . CategoryAlso::sqlFor($pdo, (int) $cid);   // and kinds also shown under it (decisions/0082)
            $catParams[":cat_a$ci_"] = $cid; $catParams[":cat_b$ci_"] = $cid;
        }
        $catSql = ' AND x.category_id IN (SELECT id FROM categories WHERE ' . implode(' OR ', $or) . ')';
    }
    // Staff's words for kinds (category_terms, decisions/0075): "mental health" also finds Counselors, Psychologists and
    // the rest, the same as universal search, so its "See them all in the directory" shows them all here too
    $termCats = []; $meantNames = [];
    if ($q !== '') {
        $tq = trim(preg_replace('/\s+/', ' ', mb_strtolower($q)));
        try {
            $ts = $pdo->prepare('SELECT DISTINCT t.category_id FROM category_terms t JOIN categories c ON c.id = t.category_id WHERE c.is_active = 1 AND t.term IN (?, ?)');
            $ts->execute([$tq, preg_replace('/(?<=[a-z]{3})s$/', '', $tq)]);
            $termCats = array_map('intval', $ts->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) { $termCats = []; }   // no table yet: the words alone
        // what the words mean, as the site's search reads them (Jason, 2026-11-12: "directory search needs to be as smart as main
        // search just focused on listing data"): "trash collection" is Garbage Collection, "lunch" Restaurants. The kinds it's
        // sure of, else its best if it's close
        // a name is read first (decisions/0157 §4.3; review E26): words that are part of the name of a listing we show or a
        // place named on a page we read are a name, and no kind is guessed from them ("blue hills" isn't Propane Tank Kiosks)
        $asName = false;
        if (mb_strlen($tq) >= 4 && !$termCats) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tq) . '%';
            try { $nm = $pdo->prepare("SELECT 1 FROM entities WHERE entity_type = 'merchant' AND is_suppressed = 0 AND name LIKE ? LIMIT 1"); $nm->execute([$like]); $asName = (bool) $nm->fetchColumn(); } catch (\Throwable $e) {}
            if (!$asName) { try { $nm = $pdo->prepare('SELECT 1 FROM ' . Database::t('source_facts') . ' WHERE name LIKE ? AND held IS NULL LIMIT 1'); $nm->execute([$like]); $asName = (bool) $nm->fetchColumn(); } catch (\Throwable $e) {} }
        }
        if (!$asName) try {
            require_once __DIR__ . '/../../app/lib/Concepts.php';
            $ks = Concepts::kinds($pdo, $q, 6); $meant = [];
            foreach ($ks as $k) if (!empty($k['sure'])) $meant[] = $k;
            if (!$meant && $ks && (float) $ks[0]['score'] >= 0.4) $meant[] = $ks[0];
            foreach ($meant as $k) { $termCats[] = (int) $k['id']; $meantNames[] = (string) $k['name']; }
            $termCats = array_values(array_unique($termCats));
        } catch (\Throwable $e) { error_log('search_listings meaning: ' . $e->getMessage()); }
    }
    $preTownWhere = $where; $preTownParams = $params;   // the towns' counts: before a town is chosen
    if ($inTown !== '') { $where .= " AND x.city$ci = :in_town"; $params[':in_town'] = $inTown; }
    $baseWhere = $where;
    $baseParams = $params;
    $applyText = function (bool $strict) use (&$where, &$params, $baseWhere, $baseParams, $q, $catSql, $catParams, &$qSql, &$qParams, $termCats) {
        $qp = [];
        $sql = ($q !== '') ? SearchText::where($q, $strict, $qp, ['x.offerings']) : '';
        if ($q !== '' && $sql === '') { $sql = '1 = 0'; } // only symbols typed: match nothing
        if ($sql !== '' && $termCats) $sql = "($sql) OR x.category_id IN (" . implode(',', $termCats) . ')';   // integers only
        $where = $baseWhere . ($sql !== '' ? " AND ($sql)" : '') . $catSql;
        $params = $baseParams + $qp + $catParams;
        $qSql = $sql; $qParams = $qp;
    };
    $applyText(true);

    // ---- Total (strict first; loose fallback when strict finds few) ----
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ($inner) x $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    // (not when the words are staff's words for kinds: they're known, and loose would find "mental" in "environmental")
    if ($q !== '' && !$termCats && $total < SearchText::LOOSE_BELOW && SearchText::tokens($q)) {
        $strictTotal = $total;
        $applyText(false);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ($inner) x $where");
        $stmt->execute($params);
        $looseTotal = (int)$stmt->fetchColumn();
        if ($looseTotal > $strictTotal) {
            $total = $looseTotal;
            $matchMode = 'loose';
        } else {
            $applyText(true); // nothing gained: keep the tighter match
        }
    }

    // ---- Sort (whitelisted). Typed words default to Best match. ----
    $orderParams = [];
    $rankSql = '0';
    // Best match: the words first, then how sure we are of a listing (decisions/0133's confidence score: Jason, 2026-11-19,
    // "Unclaimed businesses are guesses, so we should push the ones we are more confident in. once claimed that pushes
    // them to the top"), then the nearest. With no words, Best match is the score, then the nearest.
    $confSql = '';
    try { $pdo->query('SELECT 1 FROM listing_confidence LIMIT 0'); $confSql = 'COALESCE((SELECT lc.score FROM listing_confidence lc WHERE lc.entity_id = x.id), 0) DESC, '; } catch (Throwable $e) { $confSql = ''; }
    if ($q !== '' && SearchText::tokens($q)) {
        [$rankSql, $orderParams] = SearchText::rank($q, [], ['x.offerings']);
    } elseif ($sort === 'best' && $confSql === '') {
        $sort = '';
    }
    if ($sort === '') {
        $sort = ($q !== '' && SearchText::tokens($q)) ? 'best' : ($hasLocation ? 'distance' : 'name');
    }
    switch ($sort) {
        case 'best':
            $order = ($rankSql !== '0' ? "$rankSql ASC, " : '') . $confSql . ($hasLocation ? '(x.distance IS NULL) ASC, x.distance ASC, ' : '') . 'x.business_name ASC, x.id ASC';   // no words: no rank (ORDER BY 0 is a column number)
            break;
        case 'city':
            $order = 'x.city ASC, x.business_name ASC, x.id ASC';
            $orderParams = [];
            break;
        case 'distance':
            $order = $hasLocation
                ? '(x.distance IS NULL) ASC, x.distance ASC, x.business_name ASC, x.id ASC'
                : 'x.business_name ASC, x.id ASC';
            $orderParams = [];
            break;
        default: // 'name'
            $sort = 'name';
            $order = 'x.business_name ASC, x.id ASC';
            $orderParams = [];
    }

    // marked closed (decisions/0072): still found, shown as Closed, and last whatever the sort (Jason, 2026-11-12: El Camino,
    // marked closed, still showed as a normal result here; the site's search already did this)
    $order = "EXISTS (SELECT 1 FROM entity_metadata cm WHERE cm.entity_id = x.id AND cm.meta_key = 'closed') ASC, " . $order;

    // ---- Map view: every match that has coordinates, as light pins ----
    if ($view === 'map') {
        $pinInner = $buildInner(false, true);
        $pinOrder = $hasLocation ? '(x.distance IS NULL) ASC, x.distance ASC, x.id ASC' : 'x.business_name ASC, x.id ASC';
        $stmt = $pdo->prepare("SELECT x.id, x.business_name, x.lat, x.lon, x.city, x.state, x.category, x.category_id, x.phone, x.status
                                 FROM ($pinInner) x $where AND x.lat IS NOT NULL AND x.lon IS NOT NULL
                                ORDER BY $pinOrder LIMIT " . MAX_PINS);
        $stmt->execute($params);
        $pins = [];
        $pinRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pinFetched = count($pinRows);   // before confidential rows are dropped: what the LIMIT cut, for 'truncated'
        // Confidential listings (decisions/0044; review f01) are never a pin: no point, no phone, nothing on the map.
        // Checked on the raw stored name and category, the same test the cards use. Dropped whole, not nulled, so the
        // map can't place them and the client can't fall back to some other point for them.
        $pinRows = array_values(array_filter($pinRows, fn ($r) => !ListingSafety::isConfidential(
            ($r['category_id'] ?? null) !== null ? (int)$r['category_id'] : null,
            (string)($r['business_name'] ?? '')
        )));
        require_once __DIR__ . '/../../app/lib/ListingActions.php';
        $pinManaged = ListingActions::managedSet($pdo, array_column($pinRows, 'id'));   // the one test (decisions/0172 §3.1)
        foreach ($pinRows as $r) {
            $pins[] = [
                'id' => (int)$r['id'], 'name' => NameText::listing((string) $r['business_name'], (string) $r['status']),
                'lat' => (float)$r['lat'], 'lon' => (float)$r['lon'],
                'city' => $r['city'], 'state' => $r['state'], 'category' => $r['category'], 'phone' => $r['phone'],
                'is_claimed' => ($r['status'] !== 'unclaimed'), 'is_verified' => ($r['status'] === 'verified'), 'managed' => isset($pinManaged[(int) $r['id']]),
            ];
        }
        echo json_encode([
            'success' => true, 'view' => 'map', 'pins' => $pins, 'total' => $total,
            'truncated' => ($total > $pinFetched && $pinFetched >= MAX_PINS),
            'center' => $hasLocation ? ['lat' => $centerLat, 'lon' => $centerLon, 'radius' => $radius] : null,
            'scope' => ($scope === null) ? null : ['level' => $scope['level'], 'slug' => $scope['slug'], 'name' => $scope['name']],
        ]);
        exit;
    }

    // ---- The requested page ----
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are validated integers computed above, never raw input.
    $stmt = $pdo->prepare("SELECT x.id FROM ($inner) x $where ORDER BY $order LIMIT $perPage OFFSET $offset");
    $stmt->execute($params + $orderParams);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $listings = [];
    if ($ids) {
        $idList = implode(',', $ids); // integers only
        $stmt = $pdo->prepare($buildInner(true) . " AND e.id IN ($idList)");
        $stmt->execute($distParams);
        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $byId[(int)$row['id']] = $row; }
        require_once __DIR__ . '/../../app/lib/ListingActions.php';
        $managedIds = ListingActions::managedSet($pdo, $ids);   // managed: someone holds listing_access (decisions/0172 §3.1, gap 5)
        foreach ($ids as $id) {
            if (!isset($byId[$id])) continue;
            $row = $byId[$id];
            $listings[] = [
                'id'            => (int)$row['id'],
                'business_name' => NameText::listing((string) $row['business_name'], (string) $row['status']),   // crawled names in full words (decisions/0058 §31)
                'city'          => NameText::full((string) $row['city']),
                'state'         => $row['state'],
                'category'      => $row['category'],
                'category_group' => $row['category_group'],
                'address'       => ListingSafety::isConfidential($row['category_id'] !== null ? (int)$row['category_id'] : null, (string)$row['business_name']) ? null : $row['address'],   // decisions/0044
                'phone'         => $row['phone'],
                'description'   => $row['description'],
                'distance'      => $row['distance'] !== null ? round((float)$row['distance'], 1) : null,
                'is_verified'   => ($row['status'] === 'verified'),
                'is_claimed'    => ($row['status'] !== 'unclaimed'),
                'managed'       => isset($managedIds[(int)$row['id']]),   // someone reads its messages; is_claimed is only the listing's status
                'lat'           => $row['lat'] !== null ? (float)$row['lat'] : null,
                'lon'           => $row['lon'] !== null ? (float)$row['lon'] : null,
            ];
        }
        // Website and today's hours for the page of cards (the directory's "Open · Closes 9 PM", decisions/0053)
        if ($listings) {
            require_once __DIR__ . '/../../app/lib/Hours.php';
            $meta = [];
            foreach ($pdo->query("SELECT entity_id, meta_key, meta_value FROM entity_metadata WHERE entity_id IN ($idList) AND meta_key IN ('website', 'hours', 'closed', 'email')")->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $meta[(int)$m['entity_id']][$m['meta_key']] = json_decode((string)$m['meta_value'], true) ?? $m['meta_value'];
            }
            [$dow, $min] = count($openAt) === 2 && $openAt[0] >= 0 && $openAt[0] <= 6 && $openAt[1] >= 0 && $openAt[1] < 1440 ? $openAt : [(int)date('w'), (int)date('G') * 60 + (int)date('i')];
            foreach ($listings as &$l) {
                $mm = $meta[$l['id']] ?? [];
                $br = $byId[$l['id']] ?? [];
                $private = ListingSafety::isConfidential(isset($br['category_id']) && $br['category_id'] !== null ? (int)$br['category_id'] : null, (string)($br['business_name'] ?? ''));   // phone only (decisions/0044)
                $l['private_location'] = $private;
                $l['website'] = !$private && is_string($mm['website'] ?? null) ? $mm['website'] : null;
                $l['email'] = !$private && is_string($mm['email'] ?? null) ? (ListingActions::cleanEmail($mm['email']) ?: null) : null;   // its public email, for Email on cards (decisions/0172 §3.3)
                $hrs = $mm['hours'] ?? null;
                $st = Hours::status($hrs !== null && $hrs !== '' && $hrs !== [] ? Hours::parse(is_string($hrs) ? $hrs : json_encode($hrs)) : null, $dow, $min);
                $l['open_now'] = $st['open'];
                $l['hours_text'] = $st['text'];
                if (array_key_exists('closed', $mm)) { $l['closed'] = true; $l['open_now'] = false; $l['hours_text'] = 'Closed · marked closed'; }   // decisions/0072
                if ($private) { $l['lat'] = null; $l['lon'] = null; }
            }
            unset($l);
            // no point of its own: the middle of its ZIP area, like the map's pins. A point that is exactly the ZIP's
            // middle (how most listings were imported) is marked approximate too, until it's geocoded (api/lib/Geocoder.php).
            $pub = array_values(array_filter(array_map(fn ($l) => !$l['private_location'] ? $l['id'] : null, $listings)));
            if ($pub) {
                $zc = [];
                foreach ($pdo->query('SELECT e.id, z.latitude, z.longitude FROM entities e JOIN zip_coordinates z ON TRIM(z.zip) = TRIM(e.primary_zip) WHERE e.id IN (' . implode(',', array_map('intval', $pub)) . ')')->fetchAll(PDO::FETCH_ASSOC) as $r) $zc[(int)$r['id']] = $r;
                foreach ($listings as &$l) {
                    if (!isset($zc[$l['id']])) continue;
                    $zl = (float)$zc[$l['id']]['latitude']; $zo = (float)$zc[$l['id']]['longitude'];
                    if ($l['lat'] === null) { $l['lat'] = $zl; $l['lon'] = $zo; $l['approx'] = true; }
                    elseif (abs((float)$l['lat'] - $zl) < 0.0002 && abs((float)$l['lon'] - $zo) < 0.0002) $l['approx'] = true;
                }
                unset($l);
            }
        }
    }

    // ---- Category counts for the current search (ignores the category filter itself) ----
    $facets = null;
    if ($wantFacets) {
        try {
            $fw = $baseWhere . ($qSql !== '' ? " AND ($qSql)" : '');
            $fp = $baseParams + $qParams;
            $stmt = $pdo->prepare("SELECT x.category_id AS cid, COUNT(*) AS n FROM ($inner) x $fw GROUP BY x.category_id");
            $stmt->execute($fp);
            $byCat = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $byCat[(int)$r['cid']] = (int)$r['n']; }
            $groups = [];
            $cats = $pdo->query('SELECT id, parent_id, slug FROM categories WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
            $groupOf = []; $slugOf = [];
            foreach ($cats as $c) {
                $slugOf[(int)$c['id']] = $c['slug'];
                $groupOf[(int)$c['id']] = ($c['parent_id'] === null) ? (int)$c['id'] : (int)$c['parent_id'];
            }
            $facets = ['total' => array_sum($byCat), 'groups' => new stdClass()];
            $groupCounts = [];
            foreach ($byCat as $cid => $n) {
                $gid = $groupOf[$cid] ?? null;
                if ($gid === null) continue;
                $gs = $slugOf[$gid] ?? null;
                if ($gs !== null) $groupCounts[$gs] = ($groupCounts[$gs] ?? 0) + $n;
            }
            if ($groupCounts) $facets['groups'] = $groupCounts;
            // the finder's lists (directory, 2026-11-12): each kind of business here with how many, and each town
            $names = [];
            if ($byCat) foreach ($pdo->query('SELECT id, slug, name FROM categories WHERE id IN (' . implode(',', array_map('intval', array_keys($byCat))) . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) $names[(int)$c['id']] = [$c['slug'], $c['name']];
            $kinds = [];
            foreach ($byCat as $cid => $n) if (isset($names[$cid])) $kinds[] = ['slug' => $names[$cid][0], 'name' => $names[$cid][1], 'n' => $n];
            // A to Z, all of them (Jason, 2026-11-12: "is there not a hotel catagory?": sorted by count and cut at 80, Hotels & Motels
            // wasn't where he looked)
            usort($kinds, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            $facets['kinds'] = array_slice($kinds, 0, 400);
            $tw = $preTownWhere . ($qSql !== '' ? " AND ($qSql)" : '') . $catSql;
            $stmt = $pdo->prepare("SELECT x.city AS city, COUNT(*) AS n FROM ($inner) x $tw AND x.city IS NOT NULL AND x.city <> '' GROUP BY x.city ORDER BY n DESC LIMIT 40");
            $stmt->execute($preTownParams + $qParams + $catParams);
            $facets['towns'] = array_map(fn ($r) => ['name' => ucwords(mb_strtolower((string)$r['city'])), 'value' => (string)$r['city'], 'n' => (int)$r['n']], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            error_log('search_listings.php facets: ' . $e->getMessage());
            $facets = null;
        }
    }

    // ---- "Did you mean ...?" when a typed search finds (almost) nothing ----
    $didYouMean = null; $offers = [];
    if ($q !== '' && $total < SearchText::DYM_BELOW) {
        // the site's search's own spelling offers (offered, never changed: decisions/0064), else the simpler one
        try { require_once __DIR__ . '/../../app/lib/UniversalSearch.php'; $offers = array_values(array_filter(array_map(fn ($o) => (string) ($o['q'] ?? ''), UniversalSearch::spellOffers($pdo, $q)), fn ($x) => $x !== '' && mb_strtolower($x) !== mb_strtolower($q))); } catch (\Throwable $e) { $offers = []; }
        try { $didYouMean = $offers[0] ?? SearchText::didYouMean($pdo, $q); } catch (\Throwable $e) { $didYouMean = null; }
        if (!$offers && $didYouMean) $offers = [$didYouMean];
    }

    // ---- Places named on pages we read that we don't list yet (decisions/0157 §3): when ours are few, first page only ----
    $read = [];
    if ($hasLocation && $page === 1 && $total < 10 && ($q !== '' || $categoryIds) && is_file(__DIR__ . '/../../app/lib/crawler/SourceIndex.php')) {
        try {
            require_once __DIR__ . '/../../app/lib/crawler/SourceIndex.php';
            $read = array_map([SourceIndex::class, 'card'], SourceIndex::search($pdo, ['text' => $q, 'cats' => $categoryIds, 'lat' => $centerLat, 'lon' => $centerLon,
                                                                                     'miles' => max(10, min(50, $radius * 2)), 'limit' => 8]));
        } catch (\Throwable $e) { error_log('search_listings.php read: ' . $e->getMessage()); $read = []; }
    }

    echo json_encode([
        'success'           => true,
        'match_mode'        => $matchMode,
        'sort_used'         => $sort,
        'facets'            => $facets,
        'did_you_mean'      => $didYouMean,
        'offers'            => array_slice($offers, 0, 3),
        'meant'             => $meantNames,
        'listings'          => $listings,
        'center'            => $hasLocation ? ['lat' => $centerLat, 'lon' => $centerLon, 'radius' => $radius] : null,   // the list beside the map: its distance ring (2026-11-19)
        'read'              => $read,
        'total'             => $total,
        'page'              => $page,
        'per_page'          => $perPage,
        'total_pages'       => $totalPages,
        'location_resolved' => $locationResolved,
        'location_filtered' => $hasLocation,
        'scope'             => ($scope === null) ? null : [
            'level' => $scope['level'], 'slug' => $scope['slug'],
            'found' => $scope['found'], 'name' => $scope['name'],
        ],
    ]);
} catch (\Throwable $e) {
    error_log('search_listings.php: ' . $e->getMessage());
    $why = '';   // the real reason goes to the error log only (review E26: the ?debug switch that showed it is gone)
    fail(500, 'Search is temporarily unavailable. Please try again.' . $why);
}
