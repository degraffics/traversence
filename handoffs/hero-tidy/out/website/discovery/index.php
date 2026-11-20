<?php
// discovery/index.php — Let's Explore (decisions/0055 §1 and step 5; decisions/0053 §4b).
//
// Toolbar on the left (full height): Overview, Map, Regions, Outdoors, Food & drink, Culture & heritage, Stories,
// then what's coming (Events, Plan a trip), then Your places. The workspace on the right shows the one chosen.
// Everything is for the visitor's place: ?cluster= / ?geo= / ?hub= in the address, or else the place they chose
// on the site (location-scope.js), which this page adopts once. With no place it's the cold start: North America,
// the big picture (the story, exploration guides, the trip planner, the regions), and choosing a place zooms in.
// Tribal lands are shown as sovereign nations; only public places are listed, never sacred or restricted sites
// (Recreation.gov places an admin hid stay hidden).
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/app-shell.php';
require_once __DIR__ . '/../../app/ui/collections.php';

$pdo = null; $hubs = []; $place = null; $indexed = false; $signedIn = false;
try {
    require_once __DIR__ . '/../../app/lib/PlaceScope.php';
    require_once __DIR__ . '/../../app/lib/UniversalSearch.php';
    $pdo = Database::connection();
    try {
        $hubs = $pdo->query("SELECT id, slug, name, hub_number, description, status FROM hubs ORDER BY (status = 'planned'), hub_number")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $hubs = $pdo->query('SELECT id, slug, name, hub_number, description FROM hubs ORDER BY hub_number')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($hubs as &$h) $h['status'] = 'active';
        unset($h);
    }
    if ($req = PlaceScope::requested($_GET)) {
        $s = PlaceScope::resolve($pdo, $_GET);
        if ($s && $s['found']) $place = UniversalSearch::scopePlace($pdo, ['level' => $req[0], 'slug' => $req[1]]);
    }
    $indexed = class_exists('SearchIndex') && SearchIndex::ready($pdo) && SearchIndex::state($pdo, 'built_listing') !== '';
    $signedIn = Auth::currentUserId() !== null;
} catch (Throwable $e) {
    error_log('discovery: ' . $e->getMessage());
}

const DV_VIEWS = ['overview', 'map', 'regions', 'experiences', 'destinations', 'parks', 'plan', 'outdoors', 'food', 'culture', 'stories'];
const DV_CULTURE = ['Museums & history' => ['museum', 'historic', 'history', 'heritage', 'monument', 'historical'],
                    'Tribal nations & cultural centers' => ['tribal', 'tribe=', 'tribes=', 'nation=', 'nations=', 'pueblo', 'cultural center', 'cultural centers', 'chapter house'],
                    'Arts & galleries' => ['gallery', 'galleries', 'art', 'arts=', 'art museum', 'theater', 'theatre', 'arts and crafts'],
                    'Libraries' => ['library', 'libraries']];
const DV_CUISINE = ['mexican' => 'Mexican', 'southwestern' => 'Southwestern', 'native foods' => 'Native foods', 'italian' => 'Italian', 'pizza' => 'Pizza',
    'chinese' => 'Chinese', 'japanese' => 'Japanese', 'thai' => 'Thai', 'vietnamese' => 'Vietnamese', 'bbq' => 'BBQ', 'burgers' => 'Burgers',
    'steak' => 'Steak', 'seafood' => 'Seafood', 'breakfast' => 'Breakfast', 'coffee' => 'Coffee', 'bakery' => 'Bakeries', 'dessert' => 'Sweets & ice cream',
    'sandwiches' => 'Delis & sandwiches', 'wings' => 'Wings', 'vegetarian' => 'Vegetarian', 'gluten free' => 'Gluten-free', 'catering' => 'Catering'];

$view = in_array($_GET['view'] ?? '', DV_VIEWS, true) ? (string) $_GET['view'] : 'overview';
if ($view === 'destinations') $view = 'experiences';   // Destinations became Experiences (decisions/0059)
$pq = $place ? [['cluster' => 'cluster', 'geo_hub' => 'geo', 'hub' => 'hub'][$place['level']] => $place['slug']] : [];
$url = fn (string $v) => '/discovery/' . (($q = http_build_query(array_merge($v === 'overview' ? [] : ['view' => $v], $pq))) !== '' ? '?' . $q : '');
$where = $place ? (string) $place['label'] : '';
// the town Auto-detect found, by its name (Jason, 2026-11-12: "Autodetect still shows greater st. johns"): ?town= names a
// town of this area (the anchors table); the page shows the town's name, with the area it's in under it
$areaLabel = '';
if ($place && $place['level'] === 'cluster' && ($tn = trim((string) ($_GET['town'] ?? ''))) !== '' && $pdo) {
    try {
        $st = $pdo->prepare('SELECT name FROM anchors WHERE cluster_id = ? AND (name = ? OR slug = ?) LIMIT 1');
        $st->execute([(int) explode(':', (string) $place['key'])[1], $tn, $tn]);
        if ($tw = $st->fetchColumn()) { $areaLabel = $where; $where = (string) $tw; }
    } catch (Throwable) {}
}
// outside our areas (2026-11-10, Jason: "Or at least parks and landmarks?"): the point the site's place gives (Auto-detect,
// or its ZIP's middle), sent by the page as ?near=lat,lon, so Parks & Landmarks shows what's near it
$near = null; $nearLabel = '';
if (!$place && preg_match('/^(-?\d{1,2}\.\d{1,6}),(-?\d{1,3}\.\d{1,6})$/', (string) ($_GET['near'] ?? ''), $nm)) {
    $near = [(float) $nm[1], (float) $nm[2]];
    $nearLabel = mb_substr(trim(strip_tags((string) ($_GET['near_label'] ?? ''))), 0, 60) ?: 'you';
}
$sg = $place ? ['place_name' => $where, 'place_key' => (string) $place['key']] : [];   // what "Tell us" is about (js/suggest.js)

/** Center points of the place, for distances and outdoors. */
function dv_points(?PDO $pdo, ?array $place): array
{
    if (!$pdo || !$place) return [];
    $sql = ['cluster' => 'mc.id = ?', 'geo_hub' => 'mc.geo_hub_id = ?', 'hub' => 'mc.geo_hub_id IN (SELECT id FROM geo_hubs WHERE hub_id = ?)'][$place['level']] ?? null;
    if (!$sql) return [];
    try {
        $st = $pdo->prepare('SELECT COALESCE(mc.center_lat, z.latitude) AS lat, COALESCE(mc.center_lon, z.longitude) AS lon FROM micro_clusters mc
                               LEFT JOIN zip_coordinates z ON z.zip = mc.primary_zip WHERE ' . $sql . ' LIMIT 400');
        $st->execute([(int) explode(':', (string) $place['key'])[1]]);
        $pts = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if ($r['lat'] !== null && $r['lon'] !== null) $pts[] = [(float) $r['lat'], (float) $r['lon']];
        return $pts;
    } catch (Throwable) { return []; }
}

/** Public recreation places near the place (hidden ones never). */
/**
 * The place guides that are live (Guides, on /hub/ pages) for a place and the towns and neighborhoods in it, or anywhere
 * with no place: [title, the place's name, its page, a line from it]. Jason, 2026-11-05: St. Johns's guide didn't show in
 * Stories & guides, which only read the older place_stories.
 */
function dv_guides(PDO $pdo, ?array $place, int $limit = 30): array
{
    try {
        require_once __DIR__ . '/../../app/lib/Guides.php';
        if (!Guides::ready($pdo)) return [];
        $keys = [];
        if ($place) {
            $keys[] = (string) $place['key'];
            $gp = Guides::place($pdo, (string) $place['key']);
            if ($gp) foreach (Guides::children($pdo, $gp) as $c) $keys[] = (string) $c[0];
        }
        $sql = "SELECT place_key FROM place_guides WHERE status = 'live'" . ($keys ? ' AND place_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ')' : '') . ' ORDER BY published_at DESC LIMIT ' . (int) $limit;
        $st = $pdo->prepare($sql); $st->execute($keys);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $k) {
            $gp = Guides::place($pdo, (string) $k); if (!$gp) continue;
            $g = Guides::shown($pdo, (string) $k); if (!$g['sections']) continue;
            $line = '';
            array_walk_recursive($g['sections'], function ($v) use (&$line) { if ($line === '' && is_string($v) && mb_strlen(trim(strip_tags($v))) > 40) $line = trim(strip_tags($v)); });
            $out[] = [$gp['name'] . ': a guide', (string) $gp['name'], (string) $gp['url'], $line];
        }
        return $out;
    } catch (Throwable $e) { error_log('discovery guides: ' . $e->getMessage()); return []; }
}

function dv_outdoors(?PDO $pdo, ?array $place, int $limit): array
{
    if (!$pdo || !$place || !is_file(__DIR__ . '/../../app/lib/crawler/RecBulk.php')) return [];
    require_once __DIR__ . '/../../app/lib/crawler/RecBulk.php';
    try {
        return array_map(fn ($r) => ['name' => RecBulk::displayName($r['name']), 'kind' => RecBulk::kindLabel($r), 'org' => (string) $r['org'],
            'miles' => (float) $r['miles'], 'url' => '/place/recreation.php?id=' . rawurlencode($r['ridb_id'])],
            RecBulk::near($pdo, dv_points($pdo, $place), $place['level'] === 'cluster' ? 35 : 25, $limit));
    } catch (Throwable) { return []; }
}

function dv_regions(array $hubs, int $max = 999): string
{
    $h = '<div class="dv-regions">';
    foreach (array_slice($hubs, 0, $max) as $r) {
        $live = ($r['status'] ?? 'active') !== 'planned';
        $h .= ($live ? '<a href="/hub/?hub=' . tv_e(urlencode($r['slug'])) . '" class="dv-region">' : '<div class="dv-region off" aria-disabled="true">')
            . '<em>' . ($live ? tv_e(ucfirst((string) ($r['status'] ?? 'active'))) : 'Coming soon') . '</em><b>' . tv_e($r['name']) . '</b>'
            . (!empty($r['description']) ? '<p>' . tv_e($r['description']) . '</p>' : '') . ($live ? '</a>' : '</div>');
    }
    return $h . '</div>';
}

/**
 * North America, the big picture: the published story (Admin → Stories → North America), or the built-in text with
 * its sources until a person reviews and publishes one. [title, html, sources, reviewed]
 */
function dv_na_story(?PDO $pdo): array
{
    if ($pdo && is_file(__DIR__ . '/../../app/lib/Stories.php')) {
        require_once __DIR__ . '/../../app/lib/Stories.php';
        try { if ($s = Stories::published($pdo, 'na')) return [(string) ($s['title'] ?: 'The big picture'), Stories::html((string) $s['body']), (string) $s['sources'], true]; } catch (Throwable) {}
    }
    $era = fn (string $when, string $title, string $text) => '<div class="dv-era"><em>' . $when . '</em><b>' . $title . '</b><p>' . $text . '</p></div>';
    return ['The big picture',
        '<div class="dv-eras">'
        . $era('15,000+ years ago', 'The first peoples',
            'People have lived on this continent for at least 15,000 years, and footprints at White Sands, New Mexico, are dated to more than 20,000 years ago. Archaeologists trace early arrivals across Beringia, the land that once joined Asia and Alaska, and down the Pacific coast. Many Indigenous nations tell their own histories of origin on this land; hundreds of sovereign tribal nations carry them forward today.')
        . $era('Around 1000 AD', 'Norse voyages',
            'Norse sailors, Leif Erikson among them, reached the coast of Newfoundland. The sagas call the land Vinland; the site at L&rsquo;Anse aux Meadows is the one confirmed Norse settlement in the Americas.')
        . $era('From 1492', 'The age of exploration',
            'Christopher Columbus reached the Caribbean in 1492. Explorers from Spain, Portugal, France, England and the Netherlands followed, mapping the coasts and the great river valleys. The encounter that followed brought disease, war and displacement to Indigenous peoples, and reshaped the continent.')
        . '</div>',
        'National Park Service, “Peopling of the Americas” · Bennett et al., “Evidence of humans in North America during the Last Glacial Maximum”, Science, 2021 (White Sands) · Parks Canada, L’Anse aux Meadows National Historic Site · Library of Congress, “Exploring the Early Americas”',
        false];
}

/** National parks: National Park Service units from the Recreation.gov data (hidden places stay hidden). */
function dv_parks(?PDO $pdo, ?array $near = null, string $state = '', array $pts = []): array
{
    if (!$pdo || !is_file(__DIR__ . '/../../app/lib/crawler/RecBulk.php')) return [];
    require_once __DIR__ . '/../../app/lib/crawler/RecBulk.php';
    if ($pts && !$near) {   // a place being explored: the parks within 150 miles of any of its towns, nearest first (2026-11-19: never the whole country)
        try {
            $rows = $pdo->query("SELECT ridb_id, name, state, city, summary, lat, lon FROM rec_places WHERE hidden = 0 AND kind = 'recarea' AND org LIKE '%National Park Service%' AND lat IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) $r['miles'] = dv_min_miles((float) $r['lat'], (float) $r['lon'], $pts);
            unset($r);
            usort($rows, fn ($a, $b) => $a['miles'] <=> $b['miles']);
            $in = array_values(array_filter($rows, fn ($r) => $r['miles'] <= 150));
            return array_slice($in ?: $rows, 0, $in ? 24 : 6);   // none that close: the nearest few, each with its miles
        } catch (Throwable) {}
    }
    if ($near) {   // near a point: the nearest first (2026-11-10)
        try {
            $rows = $pdo->query("SELECT ridb_id, name, state, city, summary, lat, lon FROM rec_places WHERE hidden = 0 AND kind = 'recarea' AND org LIKE '%National Park Service%' AND lat IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) { $x = deg2rad((float) $r['lon'] - $near[1]) * cos(deg2rad(((float) $r['lat'] + $near[0]) / 2)); $y = deg2rad((float) $r['lat'] - $near[0]); $r['miles'] = 3959 * sqrt($x * $x + $y * $y); }
            unset($r);
            usort($rows, fn ($a, $b) => $a['miles'] <=> $b['miles']);
            return array_slice($rows, 0, 24);
        } catch (Throwable) {}
    }
    try {
        $st = $pdo->prepare("SELECT ridb_id, name, state, city, summary FROM rec_places WHERE hidden = 0 AND kind = 'recarea'
                              AND org LIKE '%National Park Service%'" . ($state !== '' ? ' AND state = ?' : '') . " ORDER BY state, name LIMIT 300");
        $st->execute($state !== '' ? [$state] : []);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { return []; }
}

/** Miles from a point to the nearest of a place's points. */
function dv_min_miles(float $lat, float $lon, array $pts): float
{
    $best = INF;
    foreach ($pts as $p) { $x = deg2rad($lon - $p[1]) * cos(deg2rad(($lat + $p[0]) / 2)); $y = deg2rad($lat - $p[0]); $best = min($best, 3959 * sqrt($x * $x + $y * $y)); }
    return $best;
}

/**
 * The landing pages' cards (decisions/0058 §18): a photo from the place's journeys, else the brand's art; the name,
 * a line about it and what's there. Coming-soon places show, quieter, without a link.
 */
function dv_card(array $c): string
{
    return tv_explore_card($c);
}

/** A journey photo for a place (scope key), or ''. */
function dv_cover(?PDO $pdo, string $scope): string
{
    if (!$pdo || $scope === '' || !class_exists('Journeys') || !Journeys::ready($pdo)) return '';
    try { return (string) (Journeys::hero($pdo, $scope, 1)[0]['image'] ?? ''); } catch (Throwable) { return ''; }
}

/** How many published journeys mention a place (and the places in it). */
function dv_journeys(?PDO $pdo, string $scope): int
{
    if (!$pdo || !class_exists('Journeys') || !Journeys::ready($pdo)) return 0;
    try { $st = $pdo->prepare("SELECT COUNT(*) FROM journeys WHERE status = 'published' AND scope_keys LIKE ?"); $st->execute(['%|' . $scope . '|%']); return (int) $st->fetchColumn(); } catch (Throwable) { return 0; }
}

function dv_n(int $n, string $one, string $many): string { return '<b>' . number_format($n) . '</b> ' . ($n === 1 ? $one : $many); }

function dv_region_cards(?PDO $pdo, array $hubs): string
{
    $h = '';
    foreach ($hubs as $r) {
        $live = ($r['status'] ?? 'active') !== 'planned';
        $id = (int) ($r['id'] ?? 0);
        $stats = [];
        if ($live && $pdo && $id) {
            try {
                $q = $pdo->prepare('SELECT (SELECT COUNT(*) FROM geo_hubs WHERE hub_id = ?) AS areas,
                                           (SELECT COUNT(*) FROM micro_clusters mc JOIN geo_hubs g ON g.id = mc.geo_hub_id WHERE g.hub_id = ?) AS towns');
                $q->execute([$id, $id]);
                $c = $q->fetch(PDO::FETCH_ASSOC) ?: [];
                if ((int) ($c['areas'] ?? 0)) $stats[] = dv_n((int) $c['areas'], 'area', 'areas');
                if ((int) ($c['towns'] ?? 0)) $stats[] = dv_n((int) $c['towns'], 'town area', 'town areas');
                if ($j = dv_journeys($pdo, 'hub:' . $id)) $stats[] = dv_n($j, 'journey', 'journeys');
            } catch (Throwable) {}
        }
        $h .= dv_card(['name' => (string) $r['name'], 'url' => $live ? '/hub/?hub=' . urlencode((string) $r['slug']) : '', 'image' => $live && $id ? dv_cover($pdo, 'hub:' . $id) : '',
                       'badge' => $live ? ucfirst((string) ($r['status'] ?? 'Open')) : 'Coming soon', 'icon' => 'globe', 'kicker' => '', 'text' => (string) ($r['description'] ?? ''),
                       'stats' => $stats, 'chips' => []]);
    }
    return $h;
}

/** National parks and monuments: Recreation.gov's National Park Service units, and the parks, monuments and landmarks we list. */
function dv_park_cards(?PDO $pdo, array $parks, string $state = '', string $placeKey = '', array $pts = []): array
{
    $cards = [];
    foreach ($parks as $pk) {
        $cards[] = ['name' => RecBulk::displayName((string) $pk['name']), 'url' => '/place/recreation.php?id=' . rawurlencode((string) $pk['ridb_id']), 'rid' => (string) $pk['ridb_id'], 'state' => (string) $pk['state'], 'image' => '', 'badge' => '',
                    'icon' => 'tree', 'kicker' => trim(implode(', ', array_filter([(string) $pk['city'], (string) $pk['state']]))),
                    'text' => mb_strimwidth((string) ($pk['summary'] ?? ''), 0, 140, '…'), 'stats' => isset($pk['miles']) ? ['<b>' . number_format((float) $pk['miles'], 0) . '</b> mi'] : [], 'chips' => []];
    }
    if ($pdo) {
        try {
            $box = '';
            if ($pts) {   // near the place: a box 150 miles round its towns, then each one's miles (2026-11-19: a Kansas visitor centre in an Arizona strip)
                $la = array_column($pts, 0); $lo = array_column($pts, 1); $dl = 150 / 69.0; $dn = 150 / max(1.0, 69.0 * cos(deg2rad(array_sum($la) / count($la))));
                $box = sprintf(' AND e.latitude BETWEEN %.6F AND %.6F AND e.longitude BETWEEN %.6F AND %.6F', min($la) - $dl, max($la) + $dl, min($lo) - $dn, max($lo) + $dn);
            }
            $st = $pdo->query("SELECT e.id, e.name, e.category_id, c.name AS cat, e.latitude AS lat, e.longitude AS lon,
                                      (SELECT meta_value FROM entity_metadata m WHERE m.entity_id = e.id AND m.meta_key = 'city' LIMIT 1) AS city,
                                      (SELECT meta_value FROM entity_metadata m WHERE m.entity_id = e.id AND m.meta_key = 'state' LIMIT 1) AS state
                                 FROM entities e JOIN categories c ON c.id = e.category_id
                                WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.status IN ('unclaimed','claimed-unverified','pending-verification','verified')
                                  AND (c.name LIKE 'National Park%' OR c.name LIKE 'National Landmark%' OR c.name = 'Monuments' OR c.name = 'State Parks' OR c.name LIKE 'State Recreation%')" . ($state !== '' ? " AND EXISTS (SELECT 1 FROM entity_metadata ms WHERE ms.entity_id = e.id AND ms.meta_key = 'state' AND JSON_UNQUOTE(ms.meta_value) = " . $pdo->quote($state) . ')' : '') . "
                                " . $box . " ORDER BY (c.name LIKE 'National%') DESC, e.name LIMIT " . ($pts ? 400 : 60));
            $v = fn ($x) => is_string($d = json_decode((string) $x, true)) ? $d : (string) $x;
            $have = array_map(fn ($c) => mb_strtolower($c['name']), $cards);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($pts) {   // within 150 miles, nearest first, each with its miles
                foreach ($rows as &$r) $r['miles'] = ($r['lat'] !== null && $r['lon'] !== null) ? dv_min_miles((float) $r['lat'], (float) $r['lon'], $pts) : INF;
                unset($r);
                $rows = array_values(array_filter($rows, fn ($r) => $r['miles'] <= 150)); usort($rows, fn ($a, $b) => $a['miles'] <=> $b['miles']); $rows = array_slice($rows, 0, 60);
            }
            require_once __DIR__ . '/../../app/lib/NameText.php'; require_once __DIR__ . '/../../app/lib/ListingSafety.php';
            foreach ($rows as $r) {
                $r['name'] = NameText::listing((string) $r['name']);   // "Visitor Ctr" → "Visitor Center", as its page says
                if (ListingSafety::isConfidential($r['category_id'] !== null ? (int) $r['category_id'] : null, (string) $r['name'])) continue;   // a place kept private never shows here
                if (in_array(mb_strtolower((string) $r['name']), $have, true)) continue;
                $cards[] = ['name' => (string) $r['name'], 'url' => '/listing/view.php?id=' . (int) $r['id'], 'eid' => (int) $r['id'], 'state' => $v($r['state']), 'image' => '', 'badge' => '', 'icon' => str_contains((string) $r['cat'], 'State') ? 'tree' : 'shield',
                            'kicker' => trim(implode(', ', array_filter([ucwords(mb_strtolower($v($r['city']))), $v($r['state'])]))), 'text' => (string) $r['cat'], 'stats' => isset($r['miles']) ? ['<b>' . number_format((float) $r['miles'], 0) . '</b> mi'] : [], 'chips' => []];
            }
        } catch (Throwable $e) { error_log('dv parks: ' . $e->getMessage()); }
    }
    return $pdo ? dv_card_photos($pdo, $cards, $placeKey) : $cards;
}

/**
 * Each park card's photo (Jason, 2026-11-19: the cards showed only the pattern), in decisions/0143's order: the park's
 * own photos (a listing's hero photos), then the Commons photo of the landmark with the same name (credited; its licence
 * was checked when it was kept, Landmarks::bestPhoto, decisions/0164 "the licence decides the use"; only landmarks
 * people may see, Landmarks::visibleSql, so a held landmark's photo never shows, decisions/0066), then a labelled stock
 * photo picked by 0143 §5 (this place, its region, then the subject). The brand's pattern only when there's none.
 * Never another place's own photo. Three queries for the whole set, none per card.
 */
function dv_card_photos(PDO $pdo, array $cards, string $placeKey = ''): array
{
    $eids = array_values(array_filter(array_map(fn ($c) => (int) ($c['eid'] ?? 0), $cards)));
    $hero = [];
    if ($eids) {
        try {
            require_once __DIR__ . '/../../app/lib/HeroMedia.php';
            if (HeroMedia::ready($pdo)) {
                $st = $pdo->prepare("SELECT owner_ref, path FROM hero_media WHERE owner_kind = 'listing' AND kind = 'photo' AND path <> '' AND owner_ref IN (" . implode(',', array_fill(0, count($eids), '?')) . ') ORDER BY sort, id');
                $st->execute($eids);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $hero[(int) $r['owner_ref']] ??= (string) $r['path'];
            }
        } catch (Throwable $e) { error_log('dv card photos (own): ' . $e->getMessage()); }
    }
    $named = [];
    $names = array_values(array_unique(array_map(fn ($c) => mb_strtolower((string) $c['name']), array_filter($cards, fn ($c) => !isset($hero[(int) ($c['eid'] ?? 0)])))));
    if ($names && is_file(__DIR__ . '/../../app/lib/Landmarks.php')) {
        try {
            require_once __DIR__ . '/../../app/lib/Landmarks.php';
            $st = $pdo->prepare("SELECT LOWER(name) AS n, state, image_url, image_credit FROM landmarks WHERE " . Landmarks::visibleSql() . " AND image_url <> '' AND LOWER(name) IN ("
                                . implode(',', array_fill(0, count($names), '?')) . ')');
            $st->execute($names);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $named[$r['n']][] = $r;
        } catch (Throwable $e) { error_log('dv card photos (landmarks): ' . $e->getMessage()); }
    }
    // a photo found for the place itself (app/lib/PlacePhotos.php: Commons, credited), before any stock photo (Jason, 2026-11-19)
    $found = []; $foundRec = [];
    if (is_file(__DIR__ . '/../../app/lib/PlacePhotos.php')) {
        require_once __DIR__ . '/../../app/lib/PlacePhotos.php';
        $found = PlacePhotos::for($pdo, 'listing', array_map('strval', $eids));
        $foundRec = PlacePhotos::for($pdo, 'rec', array_values(array_filter(array_map(fn ($c) => (string) ($c['rid'] ?? ''), $cards))));
    }
    foreach ($cards as &$c) {
        $eid = (int) ($c['eid'] ?? 0);
        if ($eid && isset($hero[$eid])) { $c['image'] = $hero[$eid]; continue; }
        // the landmark of the same name, in the same state when the card knows its state (two "Fort Union"s are two places)
        $same = array_values(array_filter($named[mb_strtolower((string) $c['name'])] ?? [], fn ($r) => ($c['state'] ?? '') === '' || strcasecmp((string) $r['state'], (string) $c['state']) === 0));
        if ($same) {
            $c['image'] = (string) $same[0]['image_url'];
            if ((string) $same[0]['image_credit'] !== '') $c['stats'][] = 'Photo: ' . tv_e(mb_strimwidth((string) $same[0]['image_credit'], 0, 60, '…')) . ', Wikimedia Commons';
            continue;
        }
        $fp = $eid ? ($found[(string) $eid] ?? null) : (($c['rid'] ?? '') !== '' ? ($foundRec[(string) $c['rid']] ?? null) : null);
        if ($fp) { $c['image'] = $fp['image']; $c['stats'][] = 'Photo: ' . tv_e(mb_strimwidth($fp['credit'] !== '' ? $fp['credit'] : 'Wikimedia Commons', 0, 60, '…')) . ($fp['credit'] !== '' ? ', Wikimedia Commons' : ''); continue; }
        try {
            require_once __DIR__ . '/../../app/lib/StockLibrary.php';
            $stk = StockLibrary::forSlot($pdo, 'card', 'park:' . ($eid ?: md5((string) $c['url'])), 'national park', $placeKey, ['representative' => true, 'fallback' => 'landscape']);
        } catch (Throwable $e) { error_log('dv card photos (stock): ' . $e->getMessage()); $stk = null; }
        if ($stk) {   // labelled on the card: it's a stock photo, of the kind of place, not this one (decisions/0143)
            $c['image'] = (string) ($stk['card'] ?: $stk['url']);
            $c['stats'][] = 'Stock photo (representative) · ' . tv_e(trim((string) $stk['author']) !== '' ? $stk['author'] . ' on ' . $stk['source_name'] : (string) $stk['source_name']);
        }
    }
    unset($c);
    return $cards;
}

/**
 * Landmarks for Parks & Landmarks (decisions/0058 §32): the ones near the place being explored, else the best-known
 * ones with photos. Only what people can see (Landmarks::visibleSql: never waiting or closed; on a nation's land only
 * what the nation opened). Each card: its photo (credited) or the brand's art, its kind and the town it's near.
 */
function dv_landmark_cards(?PDO $pdo, array $points, int $limit = 24, string $state = ''): array
{
    if (!$pdo || !is_file(__DIR__ . '/../../app/lib/Landmarks.php')) return [];
    require_once __DIR__ . '/../../app/lib/Landmarks.php';
    try {
        if ($state !== '') {   // one state's, the best known first (2026-11-12)
            $st = $pdo->prepare("SELECT gnis_id, name, fclass, state, near_town, lat, lon, nation, wd_desc, image_url, image_credit, wiki_title FROM landmarks
                                  WHERE " . Landmarks::visibleSql() . " AND state = ? ORDER BY (image_url <> '') DESC, (wiki_title <> '') DESC, (wd_desc <> '') DESC, name LIMIT " . max(1, min(400, $limit)));
            $st->execute([$state]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } else
        $rows = $points ? Landmarks::near($pdo, $points, Landmarks::NEAR_MILES, $limit) : Landmarks::top($pdo, $limit);
    } catch (Throwable $e) { error_log('dv landmarks: ' . $e->getMessage()); return []; }
    return array_map(fn ($r) => ['name' => (string) $r['name'], 'url' => '/place/landmark.php?id=' . (int) $r['gnis_id'], 'image' => (string) $r['image_url'], 'badge' => '',
        'icon' => 'compass', 'kicker' => Landmarks::kindLabel($r) . (($t = trim(implode(', ', array_filter([(string) ($r['near_town'] ?? ''), (string) ($r['state'] ?? '')])))) !== '' ? ' · near ' . $t : ''),
        'text' => mb_strimwidth(ucfirst((string) $r['wd_desc']), 0, 140, '…'),
        'stats' => array_values(array_filter([isset($r['miles']) ? '<b>' . number_format((float) $r['miles'], 0) . '</b> mi' : '',
                                              ($r['image_url'] ?? '') !== '' && ($r['image_credit'] ?? '') !== '' ? 'Photo: ' . tv_e(mb_strimwidth((string) $r['image_credit'], 0, 60, '…')) . ', Wikimedia Commons' : ''])),
        'chips' => []], $rows);
}

/**
 * Photos for a hero with no journey or stock photo: the landmarks' own Commons photos around the place (or in a state, or the
 * best known), credited, each a caption with its name. [['image', 'caption']]; empty when there are none.
 */
function dv_hero_photos(?PDO $pdo, array $points = [], string $state = '', int $n = 6, string $page = ''): array
{
    if (!$pdo || !is_file(__DIR__ . '/../../app/lib/Landmarks.php')) return [];
    require_once __DIR__ . '/../../app/lib/Landmarks.php';
    try {
        if ($points) { $rows = array_values(array_filter(Landmarks::near($pdo, $points, Landmarks::NEAR_MILES, 60), fn ($r) => (string) ($r['image_url'] ?? '') !== ''));
                       if ($page !== '') { mt_srand(crc32($page)); shuffle($rows); mt_srand(); } }   // each page its own few
        else {
            $st = $pdo->prepare("SELECT gnis_id, name, state, near_town, lat, lon, image_url, image_credit FROM landmarks WHERE " . Landmarks::visibleSql() . " AND image_url <> ''" . ($state !== '' ? ' AND state = ?' : '')
                                . " ORDER BY (wiki_title <> '') DESC, (wd_desc <> '') DESC, RAND(" . (int) sprintf('%u', crc32($page . '|' . date('Ymd'))) % 2000000000 . ") LIMIT " . max(1, $n));   // a different few each day
            $st->execute($state !== '' ? [$state] : []); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) { error_log('dv hero photos: ' . $e->getMessage()); return []; }
    // each photo names its place for the viewer: its page, its point (a nation's land: its public place, Nations::pin) and its counts (2026-11-19)
    if (!class_exists('Nations') && is_file(__DIR__ . '/../../app/lib/Nations.php')) require_once __DIR__ . '/../../app/lib/Nations.php';
    $subj = function (array $r) use ($pdo) {
        if (empty($r['gnis_id'])) return [];
        $la = isset($r['lat']) ? (float) $r['lat'] : null; $lo = isset($r['lon']) ? (float) $r['lon'] : null;
        if ($la !== null && class_exists('Nations')) { try { [$la, $lo] = Nations::pin($pdo, $la, $lo); } catch (Throwable $e) { $la = $lo = null; } }
        return ['subject' => (string) $r['name'], 'href' => '/place/landmark.php?id=' . (int) $r['gnis_id'], 'kind' => 'lm', 'ref' => (string) (int) $r['gnis_id']] + ($la !== null && $lo !== null ? ['lat' => $la, 'lon' => $lo] : []);
    };
    return array_map(fn ($r) => $subj($r) + ['image' => (string) $r['image_url'], 'caption' => trim((string) $r['name'] . (($t = trim(implode(', ', array_filter([(string) ($r['near_town'] ?? ''), (string) ($r['state'] ?? '')])))) !== '' ? ', ' . $t : '')
        . ((string) ($r['image_credit'] ?? '') !== '' ? ' · Photo: ' . mb_strimwidth((string) $r['image_credit'], 0, 50, '…') . ', Wikimedia Commons' : ''))], array_slice($rows, 0, $n));
}

/** The states with national parks or landmarks, and how many of each: [[code, name, parks, landmarks]], by name. */
function dv_park_states(?PDO $pdo): array
{
    if (!$pdo) return [];
    $n = [];
    try { foreach ($pdo->query("SELECT state, COUNT(*) AS n FROM rec_places WHERE hidden = 0 AND kind = 'recarea' AND org LIKE '%National Park Service%' GROUP BY state")->fetchAll(PDO::FETCH_ASSOC) as $r) $n[strtoupper((string) $r['state'])][0] = (int) $r['n']; } catch (Throwable) {}
    try { require_once __DIR__ . '/../../app/lib/Landmarks.php'; foreach ($pdo->query('SELECT state, COUNT(*) AS n FROM landmarks WHERE ' . Landmarks::visibleSql() . ' GROUP BY state')->fetchAll(PDO::FETCH_ASSOC) as $r) $n[strtoupper((string) $r['state'])][1] = (int) $r['n']; } catch (Throwable) {}
    $out = [];
    foreach ($n as $sc => $c) if (isset(Jurisdictions::STATES[$sc])) $out[] = [$sc, Jurisdictions::STATES[$sc], (int) ($c[0] ?? 0), (int) ($c[1] ?? 0)];
    usort($out, fn ($a, $b) => strcmp($a[1], $b[1]));
    return $out;
}

/**
 * The trip planner: where, when, how long, budget, themes, features. Where, when and what become a search (the
 * search panel's 5W reading); the rest is kept with the plan on this device until trip plans (save, share, day by
 * day) arrive.
 */
function dv_planner(array $hubs, string $where): string
{
    $opt = fn (array $o) => implode('', array_map(fn ($k, $v) => '<option value="' . tv_e((string) $k) . '">' . tv_e($v) . '</option>', array_keys($o), $o));
    // a label may carry a brand icon: [label, icon] (site audit P4: no emoji)
    $chips = fn (string $name, array $o) => implode('', array_map(fn ($k, $v) => '<label class="dv-pk"><input type="checkbox" name="' . $name . '" value="' . tv_e($k) . '"><span>' . (is_array($v) ? tv_icon($v[1], 'tv-ei') . ' ' . tv_e($v[0]) : tv_e($v)) . '</span></label>', array_keys($o), $o));
    $months = ['' => 'Any time', 'today' => 'Today', 'this weekend' => 'This weekend'];
    for ($i = 0; $i < 12; $i++) { $m = date('F Y', strtotime("first day of +$i month")); $months['m:' . $m] = $m; }
    return '<form class="dv-card dv-plan" id="dv-plan">'
        // where: one field that finds our places as you type (regions, areas, towns, local names); no region menu
        . '<div class="dv-pw"><label for="dv-where">Where would you like to go?</label><input id="dv-where" name="where" class="dv-in" value="' . tv_e($where) . '" placeholder="A town, area, park or region" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="dv-where-list" aria-expanded="false">'
        . '<input type="hidden" name="where_slug" value=""><input type="hidden" name="where_level" value=""><div class="dv-wl" id="dv-where-list" role="listbox" hidden></div></div>'
        . '<div class="dv-pg dv-pg3"><label>When would you like to go?<select name="when" class="dv-in">' . $opt($months) . '</select></label>'
        . '<label>Duration<select name="days" class="dv-in">' . $opt(['' => 'Any length', 'day' => 'A day trip', 'weekend' => 'A weekend', '3-5' => '3 to 5 days', 'week' => 'A week or more']) . '</select></label>'
        . '<label>Price range<select name="price" class="dv-in">' . $opt(['' => 'Any budget', '$' => '$ · thrifty', '$$' => '$$ · moderate', '$$$' => '$$$ · comfortable', '$$$$' => '$$$$ · splurge']) . '</select></label></div>'
        . '<p class="dv-pl">Themes &amp; trip types</p><div class="dv-pks">' . $chips('theme', ['discovery' => ['Discovery', 'compass'], 'outdoors' => ['Outdoors', 'tree'], 'history' => ['History & heritage', 'landmark'],
            'culture' => ['Arts & culture', 'camera'], 'food' => ['Food & drink', 'utensils'], 'road' => ['Road trip', 'car'], 'family' => ['Family', 'users'], 'stars' => ['Night skies', 'sparkle']]) . '</div>'
        . '<p class="dv-pl">Features &amp; offers</p><div class="dv-pks">' . $chips('feature', ['free' => 'Free entry', 'pets' => 'Pet friendly', 'access' => 'Accessible',
            'late' => 'Open late', 'deals' => 'Deals & offers']) . '</div>'
        . '<div class="dv-prow"><button class="dv-btn" type="submit">Find trip ideas</button><span class="dv-muted" id="dv-plan-note">Where, when and what search now. Length, budget and features stay with your plan; save places with ⋯ → Add to a trip.</span>'
        . '<button class="dv-btn" type="button" id="dv-plan-save" style="background:transparent;color:#92400E;border:1px solid #CBB98D">Save as a trip</button>'
        . '<a href="/user/trips/" style="font-weight:600;color:#92400E;text-decoration:underline">Your trips</a></div>'
        . '</form>';
}

$rows = fn (array $o) => ($pdo && $indexed) ? SearchIndex::rows($pdo, $place, null, $o) : [];
$item = fn (array $r) => ['name' => $r['label'], 'url' => $r['url'], 'sub' => $r['sub'],
                          'peek' => $r['kind'] === 'listing' ? $r['item_id'] : ($r['kind'] === 'outdoors' ? 'rec:' . $r['item_id'] : '')];

require_once __DIR__ . '/../../app/ui/section-rails.php';
$rail = tv_section_rail('explore')['items'];   // the same toolbar as every Let's Explore page
require_once __DIR__ . '/../../app/ui/explore-hero.php';   // the Explore hero (decisions/0058 §18)
$xTabs = [['regions', 'Regions', 'globe', $url('regions')], ['experiences', 'Experiences', 'bookmark', $url('experiences')], ['parks', 'Parks & Landmarks', 'shield', $url('parks')],
          ['map', 'Map', 'map', $url('map')]];   // the ribbon under the hero (Map: Jason, 2026-11-10, "Discovery should also have the map as an option")

$titles = ['overview' => "Let's Explore", 'map' => 'Map', 'regions' => 'Regions', 'experiences' => 'Experiences', 'destinations' => 'Experiences', 'parks' => 'Parks & landmarks',
           'plan' => 'Plan a trip', 'outdoors' => 'Outdoors & public lands', 'food' => 'Food & drink', 'culture' => 'Culture & heritage', 'stories' => 'Stories & guides'];
$wide = in_array($view, ['regions', 'experiences', 'parks'], true);   // views about the whole continent
$heading = $titles[$view] . ($where !== '' && !$wide ? ($view === 'overview' ? ' around ' : ' · ') . $where : ($view === 'overview' ? ' North America' : ''));

tv_app_start("Let's Explore", ['rail' => $rail, 'active' => $view, 'rail_label' => "Let's Explore", 'crumbs' => [], 'heading' => $heading,   // no crumbs: the toolbar shows where you are
    'no_title' => $view === 'overview' || $wide,   // their title is in the hero
    'description' => 'Explore a place: journeys from people who were there, outdoors, food, culture and heritage, stories, and the local businesses around them.']);
tv_count_item('explore:' . $view);   // which Let's Explore view (the platform's counts)
echo tv_collections_css();
?>
<style>
  .dv-card{background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:1rem;box-shadow:0 6px 16px -10px rgba(62,43,31,.4);padding:1rem 1.1rem}
  .dv-grid{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fill,minmax(210px,1fr))}
  .dv-tile{display:block;text-decoration:none;color:var(--tv-ink);background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:1rem;padding:.9rem 1rem}
  .dv-tile:hover{border-color:var(--tv-line-strong);box-shadow:0 8px 18px -12px rgba(62,43,31,.5)}
  .dv-tile .ta-svg{color:var(--tv-icon,#B45309)}
  .dv-tile b{display:block;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.05rem;margin-top:.35rem}
  .dv-tile span{display:block;font-size:.8rem;color:var(--tv-meta);margin-top:.15rem}
  .dv-h2{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.15rem;color:var(--tv-ink);margin:1.4rem 0 .6rem}
  .dv-chips{display:flex;flex-wrap:wrap;gap:.4rem}
  .dv-chip{border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.3rem .8rem;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none}
  .dv-chip small{color:var(--tv-meta);font-weight:400}
  .dv-chip:hover{border-color:var(--tv-line-strong)}
  .dv-note{font-size:.88rem;color:var(--tv-meta);margin:0}
  .dv-muted{font-size:.78rem;color:var(--tv-meta)}
  .dv-place{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem .9rem;margin-bottom:.8rem}
  .dv-place .ta-svg{color:var(--tv-icon,#B45309)}
  .dv-btn{display:inline-flex;align-items:center;gap:.4rem;background:var(--tv-umber);color:var(--tv-on-umber);border:0;border-radius:.7rem;padding:.5rem 1rem;font-size:.88rem;font-weight:600;cursor:pointer;text-decoration:none}
  .dv-btn.ghost{background:var(--tv-card-a);color:var(--tv-ink);border:1px solid var(--tv-line-strong)}
  .dv-regions{display:grid;gap:.6rem;grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
  .dv-region{display:block;text-decoration:none;color:var(--tv-ink);background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.9rem;padding:.75rem .9rem}
  .dv-region:hover{border-color:var(--tv-line-strong)}
  .dv-region.off{opacity:.6}
  .dv-region b{font-family:var(--tv-font-head,ui-serif,Georgia,serif)}
  .dv-region em{float:right;font-style:normal;font-size:.62rem;text-transform:uppercase;letter-spacing:.05em;border-radius:.3rem;padding:.1rem .4rem;background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}
  .dv-region.off em{background:var(--tv-card-b);color:var(--tv-meta)}
  .dv-region p{font-size:.78rem;color:var(--tv-meta);margin:.25rem 0 0}
  .dv-gap{margin-top:.8rem}
  .dv-states{display:grid;gap:.5rem;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));margin-top:.6rem}
  .dv-state{display:block;text-decoration:none;color:var(--tv-ink);background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.8rem;padding:.65rem .8rem}
  .dv-state:hover{border-color:var(--tv-line-strong);box-shadow:0 8px 18px -12px rgba(62,43,31,.5)}
  .dv-state b{display:block;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.02rem;color:var(--tv-link)}.dv-state span{display:block;font-size:.78rem;color:var(--tv-meta);margin-top:.1rem}
  .xe-types{display:flex;gap:.35rem;overflow-x:auto;scrollbar-width:none;margin:0 0 .8rem;padding-bottom:2px}.xe-types::-webkit-scrollbar{display:none}
  .xe-types a{flex:0 0 auto;border:1px solid var(--tv-line-strong);border-radius:999px;padding:.3rem .8rem;font-size:.82rem;font-weight:600;color:var(--tv-ink);text-decoration:none;background:var(--tv-card-a)}
  .xe-types a.on{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber)}
  .xe-post{font-size:.9rem;color:var(--tv-meta);margin:1rem 0}.xe-post a{color:var(--tv-link);font-weight:600}
  /* the main column 4/5, the tools (Trip Planner, Top Contributors) 1/5 (Jason, 2026-11-12, after 2/3 and 1/3) */
  .mg{display:grid;grid-template-columns:minmax(0,4fr) minmax(250px,1fr);gap:1.2rem;align-items:start}
  .mg-main{min-width:0}   /* its hero and carousels stay inside the column (they ran under the Trip Planner) */
  /* desktop: a taller hero (Jason, 2026-11-12: "for desktop view make it taller") */
  @media (min-width:1024px){.ta-work .xh:not(.xh-nophoto){height:clamp(380px,62vh,640px)}.ta-work .xh.xh-nophoto{height:auto;min-height:260px}}
  /* the default place, offered in one slim line (Jason, 2026-11-20) */
  .dv-ask{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem .6rem;margin:0 0 .7rem;padding:.4rem .45rem .4rem .7rem;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-left:3px solid var(--tv-icon,#B45309);border-radius:.6rem;font-size:.88rem;color:var(--tv-ink)}
  .dv-ask[hidden]{display:none}.dv-ask>svg{width:16px;height:16px;flex:none;color:var(--tv-icon,#B45309)}.dv-ask-t{flex:1 1 14rem;min-width:0}.dv-ask-t b{font-weight:700}
  .dv-ask-go{border:0;border-radius:999px;background:var(--tv-btn);color:var(--tv-btn-text);font:700 .82rem/1.2 inherit;padding:.32rem .8rem;cursor:pointer}.dv-ask-go:hover{background:var(--tv-btn-hover)}
  .dv-ask-l{border:0;background:none;color:var(--tv-link,#92400E);text-decoration:underline;font:600 .82rem/1.2 inherit;cursor:pointer;padding:.3rem .2rem}
  .dv-ask-x{width:28px;height:28px;flex:none;border-radius:999px;border:1px solid var(--tv-line-strong);background:transparent;color:var(--tv-meta);font-size:1rem;line-height:1;cursor:pointer}

  .mg-more{display:block;width:100%;margin-top:.8rem;background:var(--tv-btn);color:var(--tv-btn-text);border:0;border-radius:.6rem;padding:.6rem;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.1rem;cursor:pointer}
  .mg-side{display:grid;gap:.9rem;position:sticky;top:calc(var(--ta-top,64px) + 12px)}
  .mg-box{background:var(--tv-canvas);border:1px solid var(--tv-line-strong);border-radius:1rem;overflow:hidden}
  .mg-plan>summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:.5rem;padding:.8rem 1rem;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.1rem;color:var(--tv-ink)}
  .mg-plan>summary::-webkit-details-marker{display:none}.mg-plan>summary::after{content:"\25BE";margin-left:auto;color:var(--tv-icon,#B45309)}.mg-plan[open]>summary::after{content:"\25B4"}
  .mg-in .dv-plan{border:0;border-radius:0;box-shadow:none;background:transparent;padding:.2rem 1rem 1rem}
  .mg-side .dv-pg,.mg-side .dv-pg3{grid-template-columns:1fr}
  .mg-h3{margin:0;background:var(--tv-umber);color:var(--tv-on-umber-meta);font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.05rem;padding:.6rem 1rem}
  .mg-ct{display:flex;align-items:center;gap:.55rem;padding:.45rem 1rem;border-top:1px solid var(--tv-line);color:var(--tv-ink);text-decoration:none;font-size:.9rem}
  .mg-ct:first-of-type{border-top:0}.mg-ct b{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mg-ct:hover{background:var(--tv-card-a)}
  .mg-mk{display:flex;gap:.25rem}.mg-mk span{width:24px;height:24px;border:1px solid var(--tv-line-strong);border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;color:var(--tv-link)}
  .mg-join{display:block;text-align:center;background:var(--tv-btn);color:var(--tv-btn-text);font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;letter-spacing:.03em;padding:.6rem;text-decoration:none}
  .mg-tool{display:none}
  @media (max-width:1100px){.mg{grid-template-columns:minmax(0,1fr)}.mg-side{position:static}
    /* the Trip Planner tool, in the brand's flat style (like the top bar's icons): cream at rest, sage when the planner is open */
    .mg-tool{display:flex;position:fixed;right:14px;bottom:16px;z-index:40;width:52px;height:52px;border-radius:999px;border:3px solid var(--tv-taupe);background:var(--tv-card-a);color:var(--tv-ink);align-items:center;justify-content:center;box-shadow:0 8px 18px -10px rgba(0,0,0,.55);cursor:pointer;padding:0}
    .mg-tool svg{width:24px;height:24px}.mg-tool:hover{border-color:var(--tv-line-strong)}
    .mg-tool[aria-pressed="true"]{background:var(--tv-sage);box-shadow:0 0 0 2px rgba(143,188,168,.35),0 0 12px rgba(143,188,168,.85)}}
  .mg-plan>summary .ta-svg{width:22px;height:22px;color:var(--tv-icon,#B45309)}
  .dv-eras{display:grid;gap:.7rem;grid-template-columns:repeat(auto-fit,minmax(230px,1fr))}
  .dv-era{background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:1rem;padding:.85rem 1rem}
  .dv-era em{display:block;font-style:normal;font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-link);font-weight:700}
  .dv-era b{display:block;font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-size:1.05rem;color:var(--tv-ink);margin:.15rem 0 .3rem}
  .dv-era p{font-size:.86rem;color:var(--tv-ink);line-height:1.5;margin:0}
  .dv-src{font-size:.72rem;color:var(--tv-meta);margin-top:.5rem}
  .dv-in{width:100%;box-sizing:border-box;border:1px solid var(--tv-line-strong);background:var(--tv-field);border-radius:.6rem;padding:.45rem .6rem;font-size:.88rem;color:var(--tv-ink);margin-top:.2rem}
  .dv-plan label{display:block;font-size:.78rem;font-weight:600;color:var(--tv-ink)}
  .dv-pg{display:grid;grid-template-columns:2fr 1fr;gap:.6rem}.dv-pg3{grid-template-columns:1fr 1fr 1fr;margin-top:.6rem}
  .dv-pw{position:relative}.dv-pw>label{font-size:.82rem;font-weight:600;color:var(--tv-ink)}
  .dv-wl{position:absolute;left:0;right:0;top:100%;z-index:30;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);border-radius:.6rem;margin-top:.2rem;box-shadow:0 12px 24px -12px rgba(0,0,0,.4);overflow:hidden}
  .dv-wl[hidden]{display:none}.dv-wl button{display:flex;justify-content:space-between;gap:.6rem;width:100%;text-align:left;background:none;border:0;border-top:1px solid var(--tv-line);padding:.5rem .7rem;font-size:.88rem;cursor:pointer;color:var(--tv-ink)}
  .dv-wl button:first-child{border-top:0}.dv-wl button:hover,.dv-wl button.on{background:var(--tv-canvas)}.dv-wl small{color:var(--tv-meta);white-space:nowrap}.dv-wl p{margin:0;padding:.5rem .7rem;font-size:.84rem;color:var(--tv-meta)}
  .dv-pl{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:var(--tv-meta);margin:.8rem 0 .3rem}
  .dv-pks{display:flex;flex-wrap:wrap;gap:.35rem}
  .dv-pk{cursor:pointer}.dv-pk input{position:absolute;opacity:0;pointer-events:none}
  .dv-pk span{display:inline-block;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:999px;padding:.28rem .7rem;font-size:.82rem;font-weight:500}
  .dv-pk input:checked+span{background:var(--tv-umber);color:var(--tv-on-umber);border-color:var(--tv-umber)}.dv-pk input:focus-visible+span{outline:2px solid var(--tv-line-strong)}
  .dv-prow{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem .9rem;margin-top:.9rem}
  .dv-more summary{cursor:pointer;font-size:.85rem;color:var(--tv-link);font-weight:600;margin-top:.7rem}
  @media (max-width:640px){.dv-pg,.dv-pg3{grid-template-columns:1fr}}
</style>

<?php // your default place picks nothing by itself: the page asks first (Jason, 2026-11-12), filled by the script below ?>
<div class="dv-ask tv-bleed" id="dv-ask" hidden role="region" aria-label="Your default place">
  <?= tv_icon('pin') ?><span class="dv-ask-t" id="dv-ask-t"></span>
  <button type="button" class="dv-ask-go" id="dv-ask-go"></button>
  <button type="button" class="dv-ask-l" id="dv-ask-other">Another place</button>
  <button type="button" class="dv-ask-x" id="dv-ask-no" aria-label="<?= $place ? 'No thanks: stay here' : 'No thanks: stay with North America' ?>" title="<?= $place ? 'No thanks: stay here' : 'No thanks: stay with North America' ?>">&times;</button>
</div>
<?php if (!$place && !$wide && !in_array($view, ['overview', 'plan'], true)): ?>
  <div class="dv-card dv-place" id="dv-noplace">
    <?= tv_icon('compass') ?>
    <span class="dv-note">Where to explore: type a town or ZIP, or tap the target to use where you are.</span>
    <span data-place-box class="tv-pbox"></span><!-- a town or ZIP, or the target (Jason, 2026-11-11); filled by js/location-scope.js -->
    <a class="dv-btn ghost" href="<?= tv_e($url('regions')) ?>">Browse regions</a>
  </div>
<?php endif; ?>

<?php if ($view === 'overview'):
    // The magazine (decisions/0056 §9): the hero (top photos for this level, credited), the guide tabs, the feed (journeys and
    // Traversence guides, always labelled), then the reference guide below; the right column holds the Trip Planner, Top
    // Contributors and Join the Team. The same at every level, from North America down to a town cluster.
    require_once __DIR__ . '/../../app/lib/Journeys.php';
    require_once __DIR__ . '/../../app/ui/journey-ui.php';
    $scopeKey = $place ? (string) $place['key'] : '';
    $jOn = $pdo && Journeys::ready($pdo);
    $hero = $jOn ? Journeys::hero($pdo, $scopeKey, 5) : [];
    $feed = $jOn ? Journeys::feed($pdo, $scopeKey, 10) : [];   // 10 a page, the lists' standard (2026-11-12)
    $feedTotal = $feed ? Journeys::feedTotal($pdo, $scopeKey) : 0;
    $top = $jOn ? Journeys::topContributors($pdo, $scopeKey, 9) : [];
    $live = array_values(array_filter($hubs, fn ($h) => ($h['status'] ?? 'active') !== 'planned'));
    $soon = array_values(array_filter($hubs, fn ($h) => ($h['status'] ?? 'active') === 'planned'));
    $title = $place ? $where : 'North America';
    $makes = ['video' => [tv_icon('play', 'tv-ei'), 'Video'], 'photo' => [tv_icon('camera', 'tv-ei'), 'Photos'], 'writing' => [tv_icon('pen', 'tv-ei'), 'Writing']];
    echo tv_journey_css(); ?>
<?php // the hero across the whole page, square, then the two columns under it (Jason, 2026-11-12: "hero should be full width and no rounded corners") ?>
    <?= tv_explore_hero($pdo, ['name' => $title, 'scope' => $scopeKey, 'tabs' => $xTabs, 'active' => 'overview', 'carousel' => $hubs ? dv_region_cards($pdo, $hubs) : '', 'carousel_label' => 'Regions',
                               'stock' => ['subject' => 'landscape', 'place' => $scopeKey, 'owner' => 'explore:' . ($scopeKey ?: 'na'), 'representative' => true, 'fallback' => 'landscape'], 'fallback_slides' => dv_hero_photos($pdo, dv_points($pdo, $place), '', 6, 'overview')]   // no journey photos yet: the stock library's, labelled (decisions/0143)
                          + ($areaLabel !== '' ? ['sub' => 'In ' . $areaLabel . ': what follows is from the whole area.'] : [])) ?>
<div class="mg">
  <div class="mg-main">

    <h2 class="dv-h2">Journeys &amp; guides</h2>
    <?php // the place's own guide, where it is (Jason, 2026-11-10: "Did we not create a guide for phoenix? Where do I find it?")
    $guideUrl = $place && !empty($place['url']) && !str_starts_with((string) $place['url'], '/discovery') ? (string) $place['url'] : '';
    if ($guideUrl !== ''): ?>
      <a href="<?= tv_e($guideUrl) ?>" class="tv-bleed" style="display:flex;justify-content:space-between;align-items:center;gap:.8rem;background:var(--tv-card-a);border:1px solid rgba(203,185,141,.75);border-radius:.5rem;padding:.75rem .9rem;margin:0 0 .7rem;text-decoration:none;color:var(--tv-ink)">
        <span><b style="color:var(--tv-link);text-decoration:underline;text-underline-offset:3px">The guide to <?= tv_e($where) ?></b><small style="display:block;color:var(--tv-meta);font-size:.82rem;margin-top:.15rem">Its towns, places to go, the outdoors and local businesses, in one place.</small></span>
        <span aria-hidden="true" style="color:var(--tv-icon,#B45309);font-size:1.3rem">&rsaquo;</span></a>
    <?php endif; ?>
    <?php if ($feed): ?>
      <div class="jr-grid" id="mg-feed" data-scope="<?= tv_e($scopeKey) ?>" data-total="<?= (int) $feedTotal ?>"><?php foreach ($feed as $it) echo tv_journey_card($it); ?></div>
      <div id="mg-pg"></div>
    <?php else: ?>
      <div class="jr-empty">No journeys <?= $place ? 'around ' . tv_e($where) . ' ' : '' ?>yet. What was your trip like? <a class="underline" href="/user/journeys/">Share a journey</a>
        and it shows here, with your name.<?= $guideUrl !== '' ? ' Meanwhile, the guide above has the places, the outdoors and the local businesses.' : '' ?></div>
    <?php endif; ?>

    <?php if (!$place): [$naTitle, $naHtml, $naSrc, $naReviewed] = dv_na_story($pdo); ?>
      <h2 class="dv-h2"><?= tv_e($naTitle) ?> <span class="jr-voice t">Traversence</span></h2>
      <?= $naHtml ?>
      <?php if (trim($naSrc) !== ''): ?><p class="dv-src">Sources: <?= tv_e($naSrc) ?><?= $naReviewed ? '' : ' · Traversence summary, being reviewed' ?></p><?php endif; ?>
      <h2 class="dv-h2">Regions</h2>
      <?= dv_regions($live) ?>
      <?php if ($soon): ?><details class="dv-more"><summary><?= count($soon) ?> more regions coming soon</summary><div class="dv-gap"><?= dv_regions($soon) ?></div></details><?php endif; ?>
    <?php else:
      $presets = [];
      if ($pdo && is_file(__DIR__ . '/../../app/lib/SearchPresets.php')) {
          require_once __DIR__ . '/../../app/lib/SearchPresets.php';
          try { $presets = SearchPresets::forPlace($pdo, $place, [(int) date('w'), (int) date('G') * 60 + (int) date('i')]); } catch (Throwable) {}
      }
      if ($presets): ?>
      <h2 class="dv-h2">Ask about <?= tv_e($where) ?></h2>
      <div class="dv-chips" id="dv-presets">
        <?php foreach ($presets as $p): ?><button type="button" class="dv-chip" data-q="<?= tv_e($p['q']) ?>"><?= tv_e($p['label']) ?><?= $p['count'] ? ' <small>' . (int) $p['count'] . '</small>' : '' ?></button><?php endforeach; ?>
      </div>
    <?php endif;
      $nOut = count(dv_outdoors($pdo, $place, 300));
      $nFood = count($rows(['kinds' => ['listing'], 'why' => 'eat', 'limit' => 999]));
      $nCult = count($rows(['kinds' => ['listing', 'outdoors'], 'words' => array_merge(...array_values(DV_CULTURE)), 'limit' => 999]));
      $nStory = 0;
      try { $st = $pdo->prepare("SELECT COUNT(*) FROM place_stories WHERE status = 'published' AND place_key = ?"); $st->execute([(string) $place['key']]); $nStory = (int) $st->fetchColumn(); } catch (Throwable) {}
      $nStory += count(dv_guides($pdo, $place));   // the place's guides, and its towns'
      $tiles = [['outdoors', 'tree', 'Outdoors & public lands', $nOut ? $nOut . ' public places nearby' : 'Public lands nearby'],
                ['food', 'utensils', 'Food & drink', $nFood ? $nFood . ' places to eat' : 'Places to eat'],
                ['culture', 'landmark', 'Culture & heritage', $nCult ? $nCult . ' places' : 'Museums, history, arts'],
                ['stories', 'book', 'Stories & guides', $nStory ? $nStory . ' published' : 'No guide yet'],
                ['map', 'map', 'Map', 'Everything here on a map']]; ?>
      <h2 class="dv-h2">What's here</h2>
      <div class="dv-grid">
        <?php foreach ($tiles as [$v, $ic, $label, $sub]): ?>
          <a class="dv-tile" href="<?= tv_e($url($v)) ?>"><?= tv_icon($ic) ?><b><?= tv_e($label) ?></b><span><?= tv_e($sub) ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <aside class="mg-side" aria-label="Plan and people">
    <details class="mg-box mg-plan" id="mg-plan"<?= $view === 'overview' ? '' : ' open' ?>>
      <summary><?= tv_icon('route') ?> Trip Planner</summary>
      <div class="mg-in"><?= dv_planner($hubs, $where) ?></div>
    </details>
    <div class="mg-box">
      <h3 class="mg-h3">Top Contributors</h3>
      <?php if (!$top): ?><p class="dv-note" style="padding:.2rem .9rem .8rem">No contributors <?= $place ? 'here ' : '' ?>yet. Share a journey and you&rsquo;re the first.</p><?php endif; ?>
      <?php foreach ($top as $c): ?>
        <a class="mg-ct" href="/user/profile.php?id=<?= (int) $c['id'] ?>"><span class="jr-av" aria-hidden="true"><?= tv_e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span><b><?= tv_e($c['name']) ?></b>
          <span class="mg-mk"><?php foreach ($c['makes'] as $mk): ?><span title="<?= tv_e($makes[$mk][1]) ?>"><?= $makes[$mk][0] ?></span><?php endforeach; ?></span></a>
      <?php endforeach; ?>
      <a class="mg-join" href="/user/journeys/" data-needs-account="Contributors write as themselves, so joining the team starts with a free account. It takes a minute; then you carry on to becoming a contributor.">Join the team · Become a contributor</a>
    </div>
  </aside>
</div>
<button type="button" class="mg-tool" id="mg-tool" aria-label="Trip Planner" title="Trip Planner" aria-pressed="false"><?= tv_icon('route') ?></button>

<?php elseif ($view === 'experiences'):
    require_once __DIR__ . '/../../app/ui/experience-ui.php';
    $xType = isset(Experiences::TYPES[$_GET['type'] ?? '']) ? (string) $_GET['type'] : '';
    $xAll = $pdo ? Experiences::feed($pdo, $place ? (string) $place['key'] : '', $xType, 60) : [];
    $xWide = false;
    if (!$xAll && $place && $pdo) { $xAll = Experiences::feed($pdo, '', $xType, 60); $xWide = true; }   // none here yet: everywhere
    $xCards = implode('', array_map(fn ($e) => tv_experience_card($pdo, $e), array_slice($xAll, 0, 12))); ?>
  <?= tv_explore_hero($pdo, ['name' => 'Experiences', 'photos_of' => $place ? $where : 'North America', 'tabs' => $xTabs, 'active' => 'experiences', 'scope' => $place ? (string) $place['key'] : '', 'stock' => ['subject' => 'outdoor experience', 'place' => $place ? (string) $place['key'] : '', 'owner' => 'explore:experiences', 'representative' => true, 'fallback' => 'landscape'], 'fallback_slides' => dv_hero_photos($pdo, dv_points($pdo, $place), '', 6, 'experiences'),
                              'sub' => 'Things to do worth the trip' . ($place && !$xWide ? ' around ' . $where : '') . '. In season first.',
                              'carousel' => $xCards, 'carousel_label' => 'Experiences']) ?>
  <?php if ($xWide): ?><p class="dv-note">No experiences around <?= tv_e($where) ?> yet. These are from across the open regions.</p><?php endif; ?>
  <nav class="xe-types" data-more aria-label="Kinds of experience"><a href="<?= tv_e($url('experiences')) ?>" class="<?= $xType === '' ? 'on' : '' ?>">All</a><?php foreach (Experiences::TYPES as $k => $l): ?><a href="<?= tv_e($url('experiences') . (str_contains($url('experiences'), '?') ? '&' : '?') . 'type=' . $k) ?>" class="<?= $xType === $k ? 'on' : '' ?>"><?= tv_e($l) ?></a><?php endforeach; ?></nav>
  <?php if (count($xAll) > 12): ?><div class="xc-grid tv-bleed" data-tv-pager="explore-experiences"><?php foreach (array_slice($xAll, 12) as $e) echo tv_experience_card($pdo, $e); ?></div><?php endif; ?>
  <?php if (!$xAll): ?><p class="dv-note dv-gap">No experiences <?= $xType ? 'of this kind ' : '' ?>yet.</p><?php endif; ?>
  <p class="xe-post">Know something worth the trip? <a href="/user/experiences/">Post an experience</a>. Our editors review each one before it shows.</p>

<?php elseif ($view === 'parks'):
      // no place set: by state (Jason, 2026-11-12: "maybe the parks and landmarks should have a by state card if no location is set?");
      // ?state=AZ shows that state's parks and landmarks
      require_once __DIR__ . '/../../app/lib/Jurisdictions.php';
      $pkState = !$place && !$near && isset(Jurisdictions::STATES[strtoupper((string) ($_GET['state'] ?? ''))]) ? strtoupper((string) $_GET['state']) : '';
      $pkStates = !$place && !$near && $pkState === '' ? dv_park_states($pdo) : [];
      $pkPts = $place ? dv_points($pdo, $place) : [];   // the place's towns: the strip is what's near them (2026-11-19)
      $parks = dv_park_cards($pdo, dv_parks($pdo, $place ? null : $near, $pkState, $pkPts), $pkState, $place ? (string) $place['key'] : '', $pkPts ?: ($near ? [$near] : [])); $lmPts = dv_points($pdo, $place) ?: ($near ? [$near] : []); $lmN = max(100, min(300, (int) ($_GET['lm'] ?? 100))); $marks = $pkState !== '' ? dv_landmark_cards($pdo, [], $lmN + 1, $pkState) : dv_landmark_cards($pdo, $lmPts, $lmN + 1); $lmMore = count($marks) > $lmN; $marks = array_slice($marks, 0, $lmN);   // 24 at a time, Show more for the next (Jason, 2026-11-11)
      $where = $place ? $where : ($pkState !== '' ? Jurisdictions::STATES[$pkState] : $nearLabel);   // "Landmarks near Glendale, AZ" outside our areas ?>
  <?= tv_explore_hero($pdo, ['name' => 'Parks & Landmarks' . ($pkState !== '' ? ': ' . Jurisdictions::STATES[$pkState] : ''), 'photos_of' => 'North America', 'tabs' => $xTabs, 'active' => 'parks', 'stock' => ['subject' => 'national park', 'place' => $place ? (string) $place['key'] : '', 'owner' => 'explore:parks', 'representative' => true, 'fallback' => 'landscape'], 'fallback_slides' => dv_hero_photos($pdo, $place ? dv_points($pdo, $place) : ($near ? [$near] : []), $pkState, 6, 'parks'), 'scope' => '',   // the whole continent: journeys from everywhere
                               'sub' => 'National parks, monuments and historic sites, then natural landmarks. Tribal parks are run by sovereign nations: follow each nation’s guidance.',
                              'carousel' => implode('', array_map('dv_card', $parks)), 'carousel_label' => $near && !$place ? 'National parks nearest you' : 'National parks']) ?>
  <?php if (!$parks): ?><p class="dv-note dv-gap">Parks appear here as Recreation.gov places and park listings are added.</p><?php endif; ?>
  <?php if ($pkState !== ''): ?><p class="dv-gap"><a class="dv-btn ghost" href="<?= tv_e($url('parks')) ?>">&lsaquo; All states</a></p><?php endif; ?>
  <?php if ($pkStates): ?>
  <section class="dv-gap" aria-labelledby="dv-st-h">
    <h2 id="dv-st-h" class="dv-h2">By state</h2>
    <p class="dv-note">Choose a state for its national parks, monuments and landmarks, or set your place for the ones near you.</p>
    <div class="dv-states" data-tv-pager="explore-states"><?php foreach ($pkStates as [$sc, $sn, $np, $nl]): ?>
      <a class="dv-state" href="<?= tv_e($url('parks') . (str_contains($url('parks'), '?') ? '&' : '?') . 'state=' . $sc) ?>"><b><?= tv_e($sn) ?></b><span><?= $np ? (int) $np . ' park' . ($np === 1 ? '' : 's') : '' ?><?= $np && $nl ? ' · ' : '' ?><?= $nl ? number_format($nl) . ' landmark' . ($nl === 1 ? '' : 's') : '' ?></span></a>
    <?php endforeach; ?></div>
  </section>
  <?php endif; ?>
  <section class="dv-gap" aria-labelledby="dv-lm-h">
    <h2 id="dv-lm-h" class="dv-h2"><?= $lmPts ? 'Landmarks near ' . tv_e($where) : ($pkState !== '' ? 'Landmarks in ' . tv_e($where) : 'Natural landmarks') ?></h2>
    <?php if ($marks): ?>
      <p class="dv-note"><?= $lmPts ? 'Arches, buttes, canyons, falls and other natural features around ' . tv_e($where) . ', ones with a photo first.' : 'Arches, buttes, canyons, falls and other natural features across the open regions. Choose a place to see the ones near it.' ?> On a nation&rsquo;s land, only the places the nation lists for visitors.</p>
      <div class="xc-grid tv-bleed" id="dv-lm-grid" data-tv-pager="explore-landmarks"><?= implode('', array_map('dv_card', $marks)) ?></div>
      <?php if ($lmMore && $lmN < 300): ?>
        <!-- more landmarks than shown (Jason, 2026-11-11: "the cards just seem to end... if there are more we need a see more button"):
             the next 24 added here; without scripts, the link opens the page with them -->
        <p class="dv-gap" style="text-align:center"><a class="dv-btn" id="dv-lm-more" href="<?= tv_e($url('parks') . (str_contains($url('parks'), '?') ? '&' : '?') . 'lm=' . ($lmN + 100)) ?>#dv-lm-grid">Bring in more landmarks</a></p>
        <script>
          (function () {
            var b = document.getElementById('dv-lm-more'); if (!b) return;
            b.addEventListener('click', function (e) {
              e.preventDefault();
              if (window.TvAsk && TvAsk.busy) TvAsk.busy(b, true);
              fetch(b.href.split('#')[0], { credentials: 'include' }).then(function (r) { return r.text(); }).then(function (h) {
                var doc = new DOMParser().parseFromString(h, 'text/html'), g = document.getElementById('dv-lm-grid'), ng = doc.getElementById('dv-lm-grid');
                var have = g.children.length, nb = doc.getElementById('dv-lm-more');
                if (ng) Array.prototype.slice.call(ng.children, have).forEach(function (c) { g.appendChild(document.importNode(c, true)); });
                if (window.TvAsk && TvAsk.busy) TvAsk.busy(b, false);
                if (nb) b.href = nb.getAttribute('href'); else b.parentNode.remove();   // no more after these
              }).catch(function () { if (window.TvAsk && TvAsk.busy) TvAsk.busy(b, false); if (window.TvAsk) TvAsk.say('Couldn\u2019t load more just now. Try again.', { anchor: b, bad: true }); });
            });
          })();
        </script>
      <?php endif; ?>
      <p class="dv-muted dv-gap">Sources: USGS place names (GNIS), Wikidata and Wikipedia. Photos from Wikimedia Commons, credited on each.</p>
    <?php else: ?>
      <p class="dv-note">No landmarks are listed <?= $lmPts ? 'near ' . tv_e($where) . ' ' : '' ?>yet.</p>
    <?php endif; ?>
  </section>

<?php elseif ($view === 'plan'): ?>
  <p class="dv-note">Tell us where, when and what you&rsquo;re after. We&rsquo;ll search the places, stories and local businesses that fit.</p>
  <div class="dv-gap"><?= dv_planner($hubs, $where) ?></div>

<?php elseif ($view === 'regions'): ?>
  <?= tv_explore_hero($pdo, ['name' => 'Regions', 'photos_of' => 'North America', 'tabs' => $xTabs, 'active' => 'regions', 'stock' => ['subject' => 'landscape', 'place' => '', 'owner' => 'explore:regions', 'representative' => true, 'fallback' => 'landscape'], 'fallback_slides' => dv_hero_photos($pdo, [], '', 6, 'regions'), 'scope' => '',   // the whole continent: journeys from everywhere
                               'sub' => 'Start in one, then walk down through its areas and towns.',
                              'carousel' => $hubs ? dv_region_cards($pdo, $hubs) : '', 'carousel_label' => 'Regions']) ?>
  <?php if (!$hubs): ?><p class="dv-note">No regions are available yet.</p><?php endif; ?>

<?php elseif ($view === 'map' && !$place): ?>
  <div class="dv-card dv-place">
    <?= tv_icon('map') ?><span class="dv-note">The map around you: what's near where you've set your place (or where you are, with Auto-detect). Nothing set yet? The map asks whether to use your location, or you can choose a place.</span>
    <button type="button" class="dv-btn" id="dv-map-open">Open the map</button>
    <a class="dv-btn ghost" href="<?= tv_e($url('overview')) ?>">Back to Let's Explore</a>
  </div>
<?php elseif ($view === 'map' && $place): ?>
  <div class="dv-card dv-place">
    <?= tv_icon('map') ?><span class="dv-note">The map fills the screen. Choose what to show and how far; tap a pin for its card. Exit brings you back to <?= tv_e($where) ?>.</span>
    <button type="button" class="dv-btn" id="dv-map-open">Open the map</button>
    <a class="dv-btn ghost" href="<?= tv_e($url('overview')) ?>">Back to <?= tv_e($where) ?></a>
  </div>

<?php elseif ($view === 'outdoors' && $place): ?>
  <?php $out = dv_outdoors($pdo, $place, 80); ?>
  <p class="dv-note">Public campgrounds, trails, lakes, recreation areas and visitor centers near <?= tv_e($where) ?>. A place with several parts is one entry.</p>
  <div class="dv-gap"><?= $out ? tv_outdoor_collections($out, ['open' => 3, 'suggest' => $sg + ['topic' => 'outdoors']]) : '<p class="dv-note">No public recreation places are listed near here yet. <button type="button" class="dv-link" data-tv-suggest="' . tv_e(json_encode($sg + ['topic' => 'outdoors', 'kind' => 'missing'])) . '">Know one? Tell us</button></p>' ?></div>
  <p class="dv-muted dv-gap">Source: Recreation.gov (RIDB). Check hours, fees and permits with the managing agency before you go.</p>

<?php elseif ($view === 'food' && $place): ?>
  <?php
    $groups = [];
    foreach ($rows(['kinds' => ['listing'], 'why' => 'eat', 'limit' => 600]) as $r) {
        $cs = SearchIndex::cuisines((string) $r['terms']);
        $g = $cs ? (DV_CUISINE[$cs[0]] ?? ucfirst($cs[0])) : (preg_match('/\b(bars?|pubs?|brew\w*|taverns?|saloons?|wine)\b/i', (string) $r['sub']) ? 'Bars & breweries' : 'More places to eat');
        $groups[$g][] = $item($r);
    }
    uksort($groups, fn ($a, $b) => (($a === 'More places to eat') <=> ($b === 'More places to eat')) ?: (count($groups[$b]) <=> count($groups[$a])));
  ?>
  <p class="dv-note">Places to eat in <?= tv_e($where) ?>, grouped by what they serve (read from their descriptions and menus). Tap one for its card.</p>
  <div class="dv-gap"><?= $groups ? tv_grouped_list($groups, ['open' => 3, 'suggest' => $sg + ['topic' => 'food']]) : '<p class="dv-note">' . ($indexed ? 'No places to eat are listed here yet.' : 'The search index hasn\'t been built yet.') . '</p>' ?></div>

<?php elseif ($view === 'culture' && $place): ?>
  <?php
    $groups = []; $seen = [];
    foreach (DV_CULTURE as $title => $words) {
        foreach ($rows(['kinds' => ['listing', 'outdoors'], 'words' => $words, 'limit' => 200]) as $r) {
            $k = $r['kind'] . ':' . $r['item_id'];
            if (isset($seen[$k])) continue;   // one entry, once, in its first group
            $seen[$k] = true;
            $groups[$title][] = $item($r);
        }
    }
  ?>
  <p class="dv-note">Museums, history, arts and cultural centers in and around <?= tv_e($where) ?>. Tribal nations are sovereign nations: visit their public places and events, and follow each nation's guidance for visitors.</p>
  <div class="dv-gap"><?= $groups ? tv_grouped_list($groups, ['open' => 2, 'suggest' => $sg + ['topic' => 'culture']]) : '<p class="dv-note">Nothing is listed here yet.</p>' ?></div>

<?php elseif ($view === 'stories'): ?>
  <?php
    $stories = [];
    try {
        $st = $pdo->prepare("SELECT place_key, title, body FROM place_stories WHERE status = 'published'" . ($place ? ' AND place_key = ?' : '') . ' ORDER BY updated_at DESC LIMIT 300');   // paged on the page, 10 at a time (2026-11-12)
        $st->execute($place ? [(string) $place['key']] : []);
        $stories = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
    $guides = dv_guides($pdo, $place);
  ?>
  <?php if ($guides): ?>
    <h2 class="dv-h2">Guides</h2>
    <div class="dv-grid" style="margin-bottom:1rem" data-tv-pager="explore-guides">
      <?php foreach ($guides as [$gt, $gn, $gu, $gl]): ?>
        <a class="dv-tile" href="<?= tv_e($gu) ?>"><?= tv_icon('book') ?><b><?= tv_e($gt) ?></b><span><?= tv_e(mb_strimwidth($gl !== '' ? $gl : 'The guide to ' . $gn . ': what to see, where to eat and stay, and what to know.', 0, 120, '…')) ?></span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (!$stories && !$guides): ?>
    <div class="dv-card"><p class="dv-note">No guide stories are published <?= $place ? 'for ' . tv_e($where) . ' ' : '' ?>yet. Each place gets a guide written for it, checked, and published from the admin's Stories page.</p></div>
  <?php else: ?>
    <div class="dv-grid" data-tv-pager="explore-stories">
      <?php foreach ($stories as $s): try { [$pl, $u] = Links::resolve($pdo, 'place', (string) $s['place_key']); } catch (Throwable) { continue; } ?>
        <a class="dv-tile" href="<?= tv_e($u . '#story-h') ?>"><?= tv_icon('book') ?><b><?= tv_e($s['title']) ?></b><span><?= tv_e($pl) ?> · <?= tv_e(mb_strimwidth(trim(strip_tags((string) $s['body'])), 0, 110, '…')) ?></span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php
// journeys in this section (decisions/0058 §7: each category belongs to a magazine section), under its listings
if (in_array($view, ['outdoors', 'food', 'culture'], true) && $place && $pdo):
    require_once __DIR__ . '/../../app/lib/Journeys.php';
    require_once __DIR__ . '/../../app/ui/journey-ui.php';
    $secJ = Journeys::ready($pdo) ? Journeys::feed($pdo, (string) $place['key'], 6, 0, $view) : [];
    if ($secJ): echo tv_journey_css(); ?>
  <h2 class="font-serif font-bold text-xl text-[color:var(--tv-ink)]" style="margin:1.6rem 0 .7rem">Journeys: <?= tv_e($titles[$view]) ?> around <?= tv_e($where) ?></h2>
  <div class="jr-grid"><?php foreach ($secJ as $it) echo tv_journey_card($it); ?></div>
<?php endif; endif; ?>

<script src="/js/tv-pager.js?v=<?= (int) @filemtime(__DIR__ . '/../js/tv-pager.js') ?>"></script>
<script>
  // This page follows the place the visitor chose on the site (the menu's location), once, unless the address names one.
  (function () {
    var hasPlace = <?= $place ? 'true' : 'false' ?>, noParam = <?= PlaceScope::requested($_GET) ? 'false' : 'true' ?>;
    var KEY = { cluster: 'cluster', geo_hub: 'geo', hub: 'hub' };
    function loc() { return (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null; }
    function go(replace) {
      var l = loc(), k = l && l.scope && KEY[l.scope.level];
      if (hasPlace && !replace && l && l.source === 'anywhere') { location.href = '/discovery/'; return; }   // North America: the big picture
      if ((!k || !l.scope.slug) && l && (l.zip || (l.lat != null && l.lon != null))) {
        // a ZIP, or a device location (Auto-detect): open the town area it belongs to
        var q = l.zip ? 'zip=' + encodeURIComponent(l.zip) : 'lat=' + l.lat + '&lon=' + l.lon;
        fetch('/api/place.php?' + q).then(function (r) { return r.json(); }).then(function (d) {
          var c = d && d.success && d.place && d.place.cluster;
          if (replace && l.source === 'default' && c && c.slug) { ask(l, c, function () { goCluster(c, true); }); return; }
          if (c && l.townName) c.town = l.townName;   // Auto-detect's town: the page shows its name
          if (!c || !c.slug) {   // not one of our areas: Parks & Landmarks still shows what's near the point (2026-11-10)
            var v = new URL(location.href), pl = d && d.place, la = l.lat != null ? +l.lat : (pl && pl.lat != null ? +pl.lat : null), lo = l.lon != null ? +l.lon : (pl && pl.lon != null ? +pl.lon : null);
            if (v.searchParams.get('view') !== 'parks' || la === null || lo === null) return;
            v.searchParams.set('near', la.toFixed(3) + ',' + lo.toFixed(3)); v.searchParams.set('near_label', pl ? [pl.city, pl.state].filter(Boolean).join(', ') : '');
            if (v.toString() !== location.href) { if (replace) location.replace(v.toString()); else location.href = v.toString(); }
            return;
          }
          goCluster(c, replace);
        }).catch(function () {});
        return;
      }
      if (!k || !l.scope.slug) return;
      if (replace && l.source === 'default') { ask(l, { slug: l.scope.slug, label: l.scope.label || '' }, function () { goTo(k, l.scope.slug, true); }); return; }
      goTo(k, l.scope.slug, replace);
    }
    function goCluster(c, replace) { goTo('cluster', c.slug, replace, c.town || ''); }
    function goTo(k, slug, replace, town) {
      var u = new URL(location.href); ['cluster', 'geo', 'hub', 'town'].forEach(function (x) { u.searchParams.delete(x); }); u.searchParams.set(k, slug);
      if (town) u.searchParams.set('town', town);
      if (u.toString() !== location.href) { if (replace) location.replace(u.toString()); else location.href = u.toString(); }
    }
    // Your default place never picks the page by itself (Jason, 2026-11-12: "Ask first"): it's offered first, with another
    // place or staying put beside it. Said once a visit; a place chosen here (or in the menu) is followed as before.
    function ask(l, c, yes) {
      var box = document.getElementById('dv-ask'); if (!box) return;
      try { if (sessionStorage.getItem('dv-ask-no') === '1') return; } catch (e) {}
      var name = String(c.label || '').split('·')[0].trim() || (l.city || ('ZIP ' + l.zip));
      var t = document.getElementById('dv-ask-t'); t.textContent = 'Near your default place' + (l.zip ? ' (ZIP ' + l.zip + ')' : '') + ': ';
      var bn = document.createElement('b'); bn.textContent = name; t.appendChild(bn);
      var go = document.getElementById('dv-ask-go'); go.textContent = 'Explore it';
      go.onclick = function () { if (window.TvAsk && TvAsk.busy) TvAsk.busy(go, true); yes(); };
      document.getElementById('dv-ask-other').onclick = function () { box.hidden = true; if (typeof openLocationModal === 'function') openLocationModal(); };
      document.getElementById('dv-ask-no').onclick = function () { box.hidden = true; try { sessionStorage.setItem('dv-ask-no', '1'); } catch (e) {} };
      box.hidden = false;
    }
    if (!hasPlace && noParam) {
      var tried = false;
      try { tried = sessionStorage.getItem('dv-adopted') === location.pathname + location.search; sessionStorage.setItem('dv-adopted', location.pathname + location.search); } catch (e) {}
      if (!tried) setTimeout(function () { go(true); }, 250);
    }
    // choosing a new place in the menu moves this page to it
    var lbl = document.getElementById('menu-location-label'), first = null;
    setTimeout(function () { if (lbl) first = lbl.textContent; }, 800);
    if (lbl && window.MutationObserver) new MutationObserver(function () {
      if (first === null || lbl.textContent === first) return; first = lbl.textContent; go(false);
    }).observe(lbl, { childList: true, characterData: true, subtree: true });
    // presets ask in the search panel, in place
    var pr = document.getElementById('dv-presets');
    if (pr) pr.addEventListener('click', function (e) { var b = e.target.closest('[data-q]'); if (b && window.TvSearch) TvSearch.open(b.getAttribute('data-q')); });
  })();
</script>
<?php if ($view === 'map'): ?>
<script>
  // The map of this place (decisions/0053 §4b): things to do, places to eat or to stay, or everything; with a distance.
  window.addEventListener('load', function () {
    // opens on everything, then narrowed (Jason, 2026-11-10: "Initial open as things to do is limiting options vs open with all")
    var why = 'all', names = { all: 'Everything', explore: 'Things to do', eat: 'Places to eat', stay: 'Places to stay', hike: 'Hike', walk: 'Walk', drive: 'Drive', exhibits: 'Exhibits', water: 'On the water' };
    var ACTS = { hike: 'hiking', walk: 'walk', drive: 'drive', exhibits: 'exhibits', water: 'water' };   // what you'd do (2026-11-10): the words its places are found by
    var base = <?= json_encode(array_merge($pq, ['pins' => 1])) ?>, where = <?= json_encode($where) ?>, ours = <?= $place ? 'true' : 'false' ?>, around = null, asked = false;
    function tools() {
      return Object.keys(names).map(function (k) { return '<button type="button" class="tvm-b' + (k === why ? ' go' : '') + '" data-act="' + k + '">' + names[k] + '</button>'; }).join('');
    }
    // not one of our areas: the map around the place set on the site (its point, or its ZIP's), 10 mi; none set: choose one
    function center(then) {
      var l = (typeof currentLocation !== 'undefined' && currentLocation) ? currentLocation : null;
      if (l && l.lat != null && l.lon != null) { around = { lat: +l.lat, lon: +l.lon, radius: 10 }; where = l.city || 'you'; then(); return; }
      if (l && l.zip) { fetch('/api/place.php?zip=' + encodeURIComponent(l.zip)).then(function (r) { return r.json(); }).then(function (d) {
        var p = d && d.place; if (p && p.lat != null) { around = { lat: +p.lat, lon: +p.lon, radius: 10 }; where = p.city || ('ZIP ' + l.zip); then(); } else if (typeof openLocationModal === 'function') openLocationModal();
      }).catch(function () {}); return; }
      // no place set: the map opens as the directory's does and asks there, Use my location or Choose a place (Jason, 2026-11-11:
      // "from discovery the map button behaves completely different? why?"); never the device without a yes
      asked = true; where = where || 'this area'; then();
    }
    function open() {
      if (!window.TvMap) { setTimeout(open, 200); return; }
      if (!ours && !around && !asked) { center(open); return; }
      TvMap.open({ panel: true, pagePlace: ours ? { label: where } : null,   // the area this page is about, said under the title
        title: names[why] + ' · ' + where, radius: ours ? null : 10, tools: tools(),
        // the places people go that we don't hold yet: OpenStreetMap's, credited, lighter pins (2026-11-10)
        leadsFor: function () { return { all: 'todo', explore: 'todo', exhibits: 'todo', eat: 'food', stay: 'lodging', hike: 'trail', walk: 'park', water: 'park', drive: 'viewpoint' }[why] || ''; },
        titleFor: function (d) { return d.total + ' · ' + names[why] + ' · ' + where; },
        load: function (area, words) {
          var q = new URLSearchParams(base);
          if (words) q.set('q', words);   // typed in the header search while the map is open: looks across everything
          else if (ACTS[why]) q.set('q', ACTS[why]);
          q.set('kinds', !words && (why === 'eat' || why === 'stay') ? 'listing' : 'listing,outdoors');
          if (why !== 'all' && !ACTS[why] && !words) q.set('why', why);
          var a = area || (ours ? null : around);
          if (a) { q.set('lat', a.lat); q.set('lon', a.lon); q.set('radius', a.radius); }
          return fetch('/api/search.php?' + q.toString(), { credentials: 'include' }).then(function (r) { return r.json(); });
        },
        onTool: function (k) {
          why = k;
          TvMap.reload();
        },
        // closed (Exit, or the phone's back): back where they came from, never the bare map page (2026-11-10)
        onList: function () {
          // Exit always lands on a page (Jason, 2026-11-12: "exit from map feels lost"): the page it came from on the site, else
          // this place's Let's Explore, never this bare map page again
          var ref = ''; try { ref = document.referrer && new URL(document.referrer).origin === location.origin ? document.referrer : ''; } catch (x) {}
          var to = ref && ref.split('#')[0] !== location.href.split('#')[0] && !/[?&]view=map\b/.test(ref) ? ref : <?= json_encode($url('overview')) ?>;
          // the map steps back in history as it closes; going on before that lands finishes would be undone by it, leaving
          // this bare map page (Jason, 2026-11-12: "when exiting falls back to weird page"): go once the step back is done
          var gone = false, go = function () { if (gone) return; gone = true; window.removeEventListener('popstate', go); setTimeout(function () { location.replace(to); }, 0); };
          window.addEventListener('popstate', go); setTimeout(go, 450);
        }
      });
    }
    var b = document.getElementById('dv-map-open'); if (b) b.addEventListener('click', open);
    window.addEventListener('tv:location', function () { if (ours) return; around = null; try { if (TvMap.isOpen && TvMap.isOpen()) TvMap.close(); } catch (x) {} setTimeout(open, 50); });   // a place chosen: the map opens there
    open();
  });
</script>
<?php endif; ?>
<script>
  // The trip planner (dv_planner): where, when and what become a search in the search panel; length, budget and
  // features are kept with the plan on this device until trip plans arrive.
  (function () {
    var f = document.getElementById('dv-plan'); if (!f) return;
    var THEME = { discovery: 'things to do', outdoors: 'outdoors', history: 'history and heritage', culture: 'museums and galleries', food: 'places to eat',
                  road: 'scenic drives', family: 'things to do for families', stars: 'stargazing' };
    try { var old = JSON.parse(localStorage.getItem('tv_trip_plan') || 'null'); } catch (x) { old = null; }
    if (old && !f.where.value) {   // pick up where they left off
      ['when', 'days', 'price'].forEach(function (k) { if (old[k] && f[k]) f[k].value = old[k]; });
      (old.theme || []).forEach(function (v) { var i = f.querySelector('[name="theme"][value="' + v + '"]'); if (i) i.checked = true; });
      (old.feature || []).forEach(function (v) { var i = f.querySelector('[name="feature"][value="' + v + '"]'); if (i) i.checked = true; });
    }
    // Where: our places as you type (regions, areas, towns, local names; /api/place.php?action=find), forgivingly
    var wi = f.where, wl = document.getElementById('dv-where-list'), wt = null, wseq = 0, wopts = [], wsel = -1;
    var wesc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    function wshow(list) {
      wopts = list; wsel = -1;
      wl.innerHTML = list.length ? list.map(function (o, i) { return '<button type="button" role="option" data-i="' + i + '"><span>' + wesc(o.label) + '</span><small>' + wesc(o.sub || '') + '</small></button>'; }).join('')
        : '<p>No place by that name yet. Try a nearby town or the area.</p>';
      wl.hidden = false; wi.setAttribute('aria-expanded', 'true');
    }
    function wclose() { wl.hidden = true; wi.setAttribute('aria-expanded', 'false'); }
    function wpick(o) { wi.value = o.label; f.where_slug.value = o.slug; f.where_level.value = o.level; wclose(); }
    wi.addEventListener('input', function () {
      f.where_slug.value = ''; f.where_level.value = '';   // typed again: no longer the place picked
      clearTimeout(wt); var q = wi.value.trim(); if (q.length < 2) { wclose(); return; }
      wt = setTimeout(function () {
        var my = ++wseq;
        fetch('/api/place.php?action=find&q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (d) { if (my === wseq) wshow((d.places || []).slice(0, 8)); }).catch(function () {});
      }, 200);
    });
    wi.addEventListener('keydown', function (e) {
      if (wl.hidden || !wopts.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); wsel = (wsel + (e.key === 'ArrowDown' ? 1 : -1) + wopts.length) % wopts.length; [].forEach.call(wl.querySelectorAll('button'), function (b, i) { b.classList.toggle('on', i === wsel); }); }
      else if (e.key === 'Enter' && wsel >= 0) { e.preventDefault(); wpick(wopts[wsel]); }
      else if (e.key === 'Escape') wclose();
    });
    wl.addEventListener('click', function (e) { var b = e.target.closest('[data-i]'); if (b) wpick(wopts[+b.getAttribute('data-i')]); });
    document.addEventListener('click', function (e) { if (!wl.contains(e.target) && e.target !== wi) wclose(); });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var all = function (n) { return [].map.call(f.querySelectorAll('[name="' + n + '"]:checked'), function (i) { return i.value; }); };
      var plan = { where: f.where.value.trim(), where_slug: f.where_slug.value, where_level: f.where_level.value, when: f.when.value, days: f.days.value, price: f.price.value, theme: all('theme'), feature: all('feature'), at: Date.now() };
      try { localStorage.setItem('tv_trip_plan', JSON.stringify(plan)); } catch (x) {}
      var what = plan.theme.length ? THEME[plan.theme[0]] : 'things to do';
      var when = plan.when === 'today' || plan.when === 'this weekend' ? ' ' + plan.when : '';
      var where = plan.where;
      var q = what + when + (where ? ' in ' + where : '');
      var kept = [plan.when.indexOf('m:') === 0 ? plan.when.slice(2) : '', f.days.selectedIndex ? f.days.options[f.days.selectedIndex].text : '', plan.price,
                  plan.theme.length > 1 ? plan.theme.length - 1 + ' more themes' : '', plan.feature.length ? plan.feature.length + ' features' : ''].filter(Boolean);
      document.getElementById('dv-plan-note').textContent = 'Searching “' + q + '”.' + (kept.length ? ' Kept with your plan: ' + kept.join(' · ') + '.' : '');
      if (window.TvSearch) TvSearch.open(q); else location.href = '/directory/?q=' + encodeURIComponent(q);
    });
    // Save as a trip (decisions/0174 §3): where, when and how long become a trip; its page offers the place's ready-made journeys
    var sv = document.getElementById('dv-plan-save');
    if (sv) sv.addEventListener('click', function () {
      var body = { action: 'from_plan', where: f.where.value.trim(), where_slug: f.where_slug.value, where_level: f.where_level.value, when: f.when.value, days: f.days.value, tz: (function () { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (x) { return ''; } })() };
      if (window.TvAsk) TvAsk.busy(sv, true);
      fetch('/api/trips.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.signed_in) { if (window.TvAsk) TvAsk.busy(sv, false); if (window.TvJoin) TvJoin.open({ why: 'An account keeps your trips: days, times and a map, on any device.', next: location.pathname + location.search }); return; }
        return fetch('/api/trips.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': d.csrf || '' }, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })
          .then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) throw j; location.href = j.trip.url; });
      }).catch(function (e) { if (window.TvAsk) { TvAsk.busy(sv, false); TvAsk.say((e && e.message) || 'That didn\'t work: try again.', { anchor: sv, bad: true }); } });
    });
  })();
</script>
<script>
  // The magazine: hero arrows, "More" (the next 12), and the Trip Planner tool icon on phones.
  (function () {
    var tool = document.getElementById('mg-tool'), plan = document.getElementById('mg-plan');
    if (tool && plan) {
      tool.addEventListener('click', function () { plan.open = true; plan.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
      var lit = function () { tool.setAttribute('aria-pressed', String(plan.open)); };   // sage while the planner is open
      plan.addEventListener('toggle', lit); lit();
    }
    var more = document.getElementById('mg-pg'), feed = document.getElementById('mg-feed');
    if (!more || !feed) return;
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var IC = { eye: '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>', chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
               clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>' };
    var ic = function (k) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + IC[k] + '</svg>'; };
    function card(it) {   // the same card as tv_journey_card() (decisions/0058 §29)
      var d = it.date ? new Date(it.date.replace(' ', 'T')).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';
      var c = '<a class="jr-card" href="' + esc(it.url) + '"><div class="jr-head"><b>' + esc(it.category) + ' <small>|</small> ' + esc(it.title) + '</b></div>'
        + (it.mature ? '<div class="jr-img none jr-mat"><span class="jr-t">Mature themes<br><small>Open to read</small></span>' : it.image ? '<div class="jr-img" style="background-image:url(\'' + esc(it.image) + '\')">' : '<div class="jr-img none"><span class="jr-t">' + esc(it.place || it.title) + '</span>')
        + (d ? '<span class="jr-posted">Posted ' + esc(d) + '</span>' : '') + '<span class="jr-pill r">' + ic('clock') + (+it.minutes) + ' min read</span>' + (it.video ? '<span class="jr-play">▶</span>' : '') + '</div>'
        + '<div class="jr-foot"><span class="jr-av">' + esc(String(it.author).charAt(0).toUpperCase()) + '</span><span class="jr-by">' + esc(it.author) + (it.place ? ' <small>· ' + esc(it.place) + '</small>' : '') + '</span>'
        + (it.voice === 'traversence' ? '<span class="jr-voice t">Traversence</span>' : '<span class="jr-voice c">' + (it.author_kind === 'listing' ? 'Business' : 'Contributor') + '</span>')
        + (it.type === 'journey' ? '<span class="jr-stats" title="Views">' + ic('eye') + (+it.views) + '</span><span class="jr-stats" title="Comments">' + ic('chat') + (+it.comments) + '</span><span class="jr-like" title="Likes">' + ic('heart') + (+it.likes) + '</span>' : '<span class="jr-stats">Guide</span>') + '</div></a>';
      return it.type === 'journey' && it.id ? '<div class="jr-wrap" data-link-host>' + c + '<span data-link="place:jr:' + (+it.id) + '" data-link-label="' + esc(it.title) + '"></span></div>' : c;
    }
    // the lists' standard (2026-11-12, js/tv-pager.js): 10 a page or the visitor's choice, Show more, and the pages
    var total = +feed.getAttribute('data-total') || 0, first = 0, shown = feed.children.length;
    var pg = TvPager.mount(more, { key: 'explore-feed', top: feed, grid: feed,
      onMore: function () { pg.busy(true); get(first * pg.size() + shown, true); },
      onPage: function (x) { first = x; get(x * pg.size(), false); },
      onPer: function () { first = 0; get(0, false); } });
    function get(off, add) {
      return fetch('/api/journeys.php?feed=1&limit=' + pg.size() + '&offset=' + off + '&scope=' + encodeURIComponent(feed.getAttribute('data-scope') || '')).then(function (r) { return r.json(); }).then(function (d) {
        var h = (d.items || []).map(card).join('');
        if (add) { feed.insertAdjacentHTML('beforeend', h); shown += (d.items || []).length; } else { feed.innerHTML = h; shown = (d.items || []).length; }
        if (d.total != null) total = +d.total;
        pg.busy(false); pg.draw({ total: total, page: first, shown: shown });
      }).catch(function () { pg.busy(false); });
    }
    if (pg.size() !== shown && shown < total) get(0, false); else pg.draw({ total: total, page: 0, shown: shown });   // whole rows, the visitor's own choice
  })();
</script>
<script src="/js/link-button.js?v=<?= (int) @filemtime(__DIR__ . '/../js/link-button.js') ?>" defer></script>
<?php tv_app_end();
