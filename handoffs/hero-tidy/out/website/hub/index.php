<?php
// hub/index.php — the guide for every place on the six-tier funnel (decisions/0131), one template at every tier:
//   /hub/?hub=ancient-america (Regional guide) · ?geo=ancient-borderlands (Area) · ?cluster=1 (Community) ·
//   ?town=greer-az (City) · ?sub=12 (Neighborhood)
// Header and current weather; At a glance; Essentials (weather alerts, roads from AZ 511, who to call, nearest care);
// Getting there; Next stops; Landmarks; Eat & drink; Places to stay; Things to do; Events; Itineraries; Living here;
// Local; comments. The Chameleon dial (Visiting / Live here) reorders them; the filter picks each section's listings.
// A section with nothing in it isn't shown. Written sections come from the Guides tool (Let's Explore → Guides); every
// name in them links to something we have (GuideLinks).
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/lib/Database.php';
require_once __DIR__ . '/../../app/lib/PlaceScope.php';
if (is_file(__DIR__ . '/../../app/lib/PlaceProfile.php')) require_once __DIR__ . '/../../app/lib/PlaceProfile.php';
require_once __DIR__ . '/../../app/lib/SupportGroups.php';
require_once __DIR__ . '/../../app/lib/PageCache.php';
require_once __DIR__ . '/../../app/lib/Guides.php';
require_once __DIR__ . '/../../app/lib/GuideLinks.php';

/** Every ZIP (primary and associated) of the given clusters. */
function hub_zips_of(array $clusterRows): array
{
    $z = [];
    foreach ($clusterRows as $r) {
        foreach (array_merge([(string)$r['primary_zip']], (array)(json_decode((string)($r['associated_zips'] ?? ''), true) ?: [])) as $x) $z[$x] = true;
    }
    return array_keys($z);
}

/**
 * Census profile for this place plus comparison columns, or null when place profiles aren't loaded.
 * Returns ['cols' => [[label, figures], ...], 'ref' => [label, figures]|null]: the first column is this
 * place, then (for a cluster) its nearest neighbouring clusters, then its county/counties and state(s).
 * 'ref' is what the plain-words summary compares against: the county for a cluster, else the state.
 * @param array<string, list<string>> $neighbours label => ZIPs, nearest first
 */
function hub_profile(PDO $pdo, array $zips, string $label, bool $withCounty, array $neighbours = []): ?array
{
    if (!class_exists('PlaceProfile') || !$zips) return null;
    try {
        if (!PlaceProfile::tableExists($pdo)) return null;
        $here = PlaceProfile::forZips($pdo, $zips);
        if ($here === null) return null;
        $cols = [[$label, $here]];
        foreach ($neighbours as $n => $nz) {
            if ($p = PlaceProfile::forZips($pdo, $nz)) $cols[] = [$n, $p];
        }
        $ref = null;
        $cmp = PlaceProfile::comparisons($pdo, $zips);
        if ($withCounty) {
            foreach (array_slice($cmp['counties'], 0, 2) as $c) {
                if ($p = PlaceProfile::byName($pdo, 'county', $c)) { $cols[] = [preg_replace('/, .*$/', '', $c), $p]; $ref ??= ['the county', $p]; }
            }
        }
        foreach (array_slice($cmp['states'], 0, 2) as $st) {
            if ($p = PlaceProfile::byName($pdo, 'state', $st)) { $cols[] = [$st, $p]; $ref ??= [$st . ' as a whole', $p]; }
        }
        return ['cols' => $cols, 'ref' => $ref];
    } catch (Throwable $e) {
        error_log('hub/index.php profile: ' . $e->getMessage());
        return null;
    }
}

/** The nearest other clusters in the same geo-hub: [public name => ZIPs], nearest first. */
function hub_neighbours(PDO $pdo, int $clusterId, int $limit = 3): array
{
    $st = $pdo->prepare('SELECT mc.id, mc.name, mc.primary_zip, mc.associated_zips, COALESCE(mc.center_lat, z.latitude) AS lat, COALESCE(mc.center_lon, z.longitude) AS lon
                           FROM micro_clusters mc LEFT JOIN zip_coordinates z ON z.zip = mc.primary_zip
                          WHERE mc.geo_hub_id = (SELECT geo_hub_id FROM micro_clusters WHERE id = ?)');
    $st->execute([$clusterId]);
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int)$r['id']] ??= $r;   // zip_coordinates can repeat a ZIP
    $me = $rows[$clusterId] ?? null;
    if (!$me || $me['lat'] === null) return [];
    $d = [];
    foreach ($rows as $id => $r) {
        if ($id === $clusterId || $r['lat'] === null) continue;
        $dLat = deg2rad($r['lat'] - $me['lat']); $dLon = deg2rad($r['lon'] - $me['lon']);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($me['lat'])) * cos(deg2rad($r['lat'])) * sin($dLon / 2) ** 2;
        $d[$id] = 2 * 3959 * asin(min(1, sqrt($a)));
    }
    asort($d);
    $out = [];
    foreach (array_slice(array_keys($d), 0, $limit) as $id) $out[PlaceScope::publicName($rows[$id]['name'])] = hub_zips_of([$rows[$id]]);
    return $out;
}

/**
 * "Living here" in plain words: qualitative comparisons against $ref, no figures.
 * @return array{text:string, tags:list<string>}
 */
function hub_plain_words(string $name, array $p, ?array $ref): array
{
    $tags = [];
    $parts = [];
    [$refName, $r] = $ref ?? ['', null];
    if ($r && $p['median_age'] !== null && $r['median_age'] !== null) {
        $dAge = $p['median_age'] - $r['median_age'];
        if ($dAge <= -5)     { $parts[] = "a younger community than $refName"; $tags[] = 'Younger community'; }
        elseif ($dAge >= 5)  { $parts[] = "an older community than $refName"; $tags[] = 'Older community'; }
        else                 { $parts[] = "a community with an age mix much like $refName"; }
    }
    $cost = null;
    if ($r && $p['median_home'] !== null && $r['median_home']) $cost = $p['median_home'] / $r['median_home'];
    elseif ($r && $p['median_rent'] !== null && $r['median_rent']) $cost = $p['median_rent'] / $r['median_rent'];
    if ($cost !== null) {
        if ($cost < 0.75)      { $parts[] = 'with housing costs well below the area'; $tags[] = 'Lower-cost housing'; }
        elseif ($cost < 0.9)   { $parts[] = 'with housing costs somewhat below the area'; $tags[] = 'Lower-cost housing'; }
        elseif ($cost <= 1.1)  { $parts[] = 'with housing costs close to the area average'; }
        elseif ($cost <= 1.3)  { $parts[] = 'with housing costs somewhat above the area'; $tags[] = 'Higher-cost housing'; }
        else                   { $parts[] = 'with housing costs well above the area'; $tags[] = 'Higher-cost housing'; }
    }
    $sentences = [];
    if ($parts) $sentences[] = $name . ' is ' . implode(', ', $parts) . '.';
    if ($p['seasonal'] !== null) {
        if ($p['seasonal'] < 0.10)      { $sentences[] = 'Homes here are mostly lived in year-round.'; $tags[] = 'Year-round community'; }
        elseif ($p['seasonal'] < 0.30)  { $sentences[] = 'It is a mix of year-round residents and seasonal homes.'; $tags[] = 'Mixed year-round & seasonal'; }
        else                            { $sentences[] = 'Many homes are second homes and seasonal getaways, so the population swells in season.'; $tags[] = 'Seasonal & second-home area'; }
    }
    if ($p['short_commute'] !== null) {
        if ($p['short_commute'] >= 0.6)     { $sentences[] = 'Most people get to work in under 20 minutes.'; $tags[] = 'Short commutes'; }
        elseif ($p['short_commute'] < 0.4)  { $sentences[] = 'Longer commutes are common.'; $tags[] = 'Longer commutes'; }
    }
    return ['text' => implode(' ', $sentences), 'tags' => $tags];
}

/** Public listings in the support groups within the scope: [group => ['count' => n, 'top' => [[id, name], ...]]]. */
function hub_support(PDO $pdo, string $scopeSql): array
{
    $cat = SupportGroups::categoryMap();
    try {
        $st = $pdo->query("SELECT e.id, e.name, e.category_id FROM entities e
                            WHERE " . SupportGroups::PUBLIC_SQL . "
                              AND e.category_id IN (" . implode(',', array_keys($cat)) . ") AND $scopeSql
                            ORDER BY e.trust_score DESC, e.name");
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $g = $cat[(int)$r['category_id']];
            $out[$g]['count'] = ($out[$g]['count'] ?? 0) + 1;
            if (count($out[$g]['top'] ?? []) < 3) $out[$g]['top'][] = [(int)$r['id'], $r['name']];
        }
        return array_intersect_key(array_merge(array_fill_keys(array_keys(SupportGroups::GROUPS), null), $out), $out);
    } catch (Throwable $e) {
        error_log('hub/index.php support: ' . $e->getMessage());
        return [];
    }
}

/** Who to call for a place and every place above it, nearest first: [[guide title, place name, entries]]. */
function hub_resources(PDO $pdo, array $chain): array
{
    if (!is_file(__DIR__ . '/../../app/lib/ResourceGuides.php')) return [];
    require_once __DIR__ . '/../../app/lib/ResourceGuides.php';
    if (!ResourceGuides::ready($pdo)) return [];
    $out = []; $seen = [];
    $keys = array_map(fn ($c) => [$c['level'] === 'sub' ? 'sub:' . $c['id'] : $c['level'] . ':' . $c['slug'], $c['name']], $chain);
    $keys[] = ['all', 'Everywhere we cover'];
    foreach ($keys as [$k, $label]) {
        $g = ResourceGuides::guideByArea($pdo, $k);
        if (!$g || $g['status'] !== 'live') continue;
        $rows = array_values(array_filter($g['entries'], fn ($e) => $e['status'] === 'live' && (int) $e['verified'] === 1 && !isset($seen[preg_replace('/\D/', '', (string) $e['phone'])])));
        foreach ($rows as $e) $seen[preg_replace('/\D/', '', (string) $e['phone'])] = 1;
        if ($rows) $out[] = [(string) $g['title'], $label, $rows];
    }
    return $out;
}

/** A listing as a card (decisions/0058 §11): the name is the link; Link top right; Call, Directions, Website down the right. */
function hub_card(array $r, string $sub = ''): string
{
    $ic = fn (string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    $tel = preg_replace('/[^\d+]/', '', (string) ($r['phone'] ?? ''));
    $addr = trim((string) ($r['address'] ?? '') . ', ' . (string) ($r['city'] ?? '') . ' ' . (string) ($r['state'] ?? ''), ', ');
    $dir = $addr !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($addr) : (!empty($r['latitude']) ? 'https://www.google.com/maps/dir/?api=1&destination=' . $r['latitude'] . ',' . $r['longitude'] : '');
    $web = (string) ($r['website'] ?? ''); if ($web !== '' && !preg_match('~^https?://~i', $web)) $web = 'https://' . $web;
    $id = (int) $r['id']; $name = tv_e((string) $r['name']);
    $cat = preg_replace('/\s*\([^)]*$/', '', (string) ($r['category'] ?? ''));   // a category name cut off mid-bracket ("… (Hospit") loses the stub
    // what we hold about it, on the card (Jason, 2026-11-10: "Can't our crawler pull the listing details?" It does; the
    // card didn't show them): what it is in a line, its street, and open or closed now from its hours
    $desc = trim((string) ($r['description'] ?? '')); if (mb_strlen($desc) > 150) $desc = rtrim(mb_substr($desc, 0, 147), " ,.;:") . '…';
    $street = trim((string) ($r['address'] ?? ''));
    $openNow = null;
    if (!class_exists('Hours') && is_file(__DIR__ . '/../../app/lib/Hours.php')) require_once __DIR__ . '/../../app/lib/Hours.php';
    if (trim((string) ($r['hours'] ?? '')) !== '' && class_exists('Hours')) { try { $wk = Hours::parse((string) $r['hours']); $openNow = $wk ? Hours::openAt($wk, (int) date('w'), (int) date('G') * 60 + (int) date('i')) : null; } catch (Throwable) {} }
    $more = ($desc !== '' ? '<p class="gd-d">' . tv_e($desc) . '</p>' : '')
          . ($street !== '' || $openNow !== null ? '<p class="gd-x">' . ($openNow !== null ? '<b style="color:' . ($openNow ? '#3F7A5E' : '#9A3412') . '">' . ($openNow ? 'Open now' : 'Closed now') . '</b>' . ($street !== '' ? ' · ' : '') : '') . tv_e($street) . '</p>' : '');
    $line = implode(' · ', array_filter([$sub !== '' ? $sub : $cat, (string) ($r['city'] ?? ''), isset($r['miles']) && $r['miles'] !== null ? ($r['miles'] < 1 ? 'in town' : round((float) $r['miles']) . ' mi') : '']));
    return '<div class="gd-card tv-bleed" data-link-host><span data-link="business:' . $id . '" data-link-label="' . $name . '"></span><div class="gd-cb"><a class="gd-n" href="/listing/view.php?id=' . $id . '">' . $name . '</a>' . ($line !== '' ? '<small>' . tv_e($line) . '</small>' : '') . $more . '</div>'
        . '<div class="gd-acts">'
        . ($tel !== '' ? '<a href="tel:' . tv_e($tel) . '" title="Call" aria-label="Call ' . $name . '" data-out="call" data-e="' . $id . '">' . $ic('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>') . '</a>' : '')
        . ($dir !== '' ? '<a href="' . tv_e($dir) . '" target="_blank" rel="noopener" title="Directions" aria-label="Directions to ' . $name . '" data-out="directions" data-e="' . $id . '">' . $ic('<polygon points="3 11 22 2 13 21 11 13 3 11"/>') . '</a>' : '')
        . ($web !== '' ? '<a href="' . tv_e($web) . '" target="_blank" rel="noopener nofollow" title="Website" aria-label="Website of ' . $name . '" data-out="website" data-e="' . $id . '">' . $ic('<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/>') . '</a>' : '')
        . '</div></div>';
}

/** A section's listings, in their groups (Eat & drink: quick bites, sit-down, drinks). */
/** An essentials row: the name (its page), what and where, and Call / Directions as round icons on the right. */
function hub_row(array $r): string
{
    $ic = fn (string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    $tel = preg_replace('/[^\d+]/', '', (string) ($r['phone'] ?? ''));
    $addr = trim((string) ($r['address'] ?? '') . ', ' . (string) ($r['city'] ?? '') . ' ' . (string) ($r['state'] ?? ''), ', ');
    $dir = $addr !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($addr) : (!empty($r['latitude']) ? 'https://www.google.com/maps/dir/?api=1&destination=' . $r['latitude'] . ',' . $r['longitude'] : '');
    $id = (int) $r['id']; $name = tv_e((string) $r['name']);
    $cat = preg_replace('/\s*\([^)]*$/', '', (string) ($r['category'] ?? ''));
    $line = implode(' · ', array_filter([$cat, (string) ($r['city'] ?? ''), $r['miles'] !== null ? ($r['miles'] < 1 ? 'in town' : round((float) $r['miles']) . ' mi') : '']));
    return '<li><div><a class="gd-n" href="/listing/view.php?id=' . $id . '">' . $name . '</a><small>' . tv_e($line) . '</small></div><span class="gd-acts gd-acts-row">'
        . ($tel !== '' ? '<a href="tel:' . tv_e($tel) . '" title="Call" aria-label="Call ' . $name . '" data-out="call" data-e="' . $id . '">' . $ic('<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>') . '</a>' : '')
        . ($dir !== '' ? '<a href="' . tv_e($dir) . '" target="_blank" rel="noopener" title="Directions" aria-label="Directions to ' . $name . '" data-out="directions" data-e="' . $id . '">' . $ic('<polygon points="3 11 22 2 13 21 11 13 3 11"/>') . '</a>' : '')
        . '</span></li>';
}

function hub_cards(array $rows): string
{
    if (!$rows) return '';
    $groups = [];
    foreach ($rows as $r) $groups[(string) ($r['kind'] ?? '')][] = $r;
    $h = '';
    foreach ($groups as $label => $rs) $h .= ($label !== '' && count($groups) > 1 ? '<h3 class="gd-h3">' . tv_e($label) . '</h3>' : '') . '<div class="gd-grid">' . implode('', array_map(fn ($r) => hub_card($r), $rs)) . '</div>';
    return $h;
}

$err = null; $p = null;
try {
    $pdo = Database::connection();
    if (!array_intersect(['hub', 'geo', 'cluster', 'town', 'sub'], array_keys(array_filter($_GET, fn ($v) => $v !== '')))) { header('Location: /discovery/'); exit; }
    Guides::syncTowns($pdo);
    $p = Guides::fromQuery($pdo, $_GET);
    if (!$p) http_response_code(404);
    // a community of one town (Farmington) is that town: its guide opens, unless our team has written one for the community or is previewing
    if ($p && empty($_GET['preview']) && ($one = Guides::sole($pdo, $p)) && !array_filter(Guides::get($pdo, $p['key'])['sections'], fn ($v) => trim((string) $v) !== '')) { header('Location: ' . $one['url'], true, 302); exit; }
} catch (Throwable $e) {
    error_log('hub/index.php: ' . $e->getMessage());
    $err = 'This page could not load its place data.';
}

$title = $p ? $p['name'] . ($p['level'] === 'town' && $p['state'] ? ', ' . $p['state'] : '') . ' · ' . $p['kind'] : 'Place not found';
tv_page_start($title, ['no_crumbs' => true, 'description' => $p ? $p['kind'] . ' to ' . $p['name'] . ': things to do, where to eat and stay, who to call, weather and roads.' : '']);
require_once __DIR__ . '/../../app/ui/explore-hero.php';
require_once __DIR__ . '/../../app/ui/engage-ui.php';

if ($err || !$p): ?>
  <div class="text-center py-10">
    <h1 class="font-serif font-bold text-2xl mb-2"><?= $err ? 'Something went wrong' : 'Place not found' ?></h1>
    <p class="text-sm text-[color:var(--tv-meta)] mb-4"><?= tv_e($err ?: 'That place does not exist.') ?></p>
    <a href="/discovery/" class="inline-block bg-[var(--tv-btn)] hover:bg-[var(--tv-btn-hover)] text-[color:var(--tv-btn-text)] font-semibold text-sm rounded-full px-4 py-2">Browse regions</a>
  </div>
<?php tv_page_end(); return; endif;

$key = $p['key'];
tv_count_item('place:' . $key);   // the platform's counts
$preview = false;   // our team sees a draft's written sections with ?preview=1 (the Guides tool links here)
if (!empty($_GET['preview'])) { require_once __DIR__ . '/../../app/lib/QuickBooksPay.php'; $preview = QuickBooksPay::staffHere($pdo); }
$g = $preview ? Guides::get($pdo, $key) : Guides::shown($pdo, $key);
$sec = $g['sections'];
$mode = in_array($_GET['mode'] ?? '', ['v', 'l'], true) ? (string) $_GET['mode'] : (($_COOKIE['tv_guide_mode'] ?? '') === 'l' ? 'l' : 'v');
// the weather right now, from what's stored (guide_live_tick keeps it fresh; never a wait): it decides the season when
// it's snowing, and what leads in Things to do (Weather::conditions)
require_once __DIR__ . '/../../app/lib/Weather.php';
$wxNow = null; try { if ($p['level'] !== 'hub' && $p['lat'] !== null) $wxNow = Weather::stored($pdo, (float) $p['lat'], (float) $p['lon']); } catch (Throwable $e) {}
// no forecast to show (the service is down and the last good one is too old): a plain link to the weather service's own
// forecast for the place in the card's place (decisions/0163's source map: "hidden, with a link")
$wxLink = $p['level'] !== 'hub' && $p['lat'] !== null ? 'https://forecast.weather.gov/MapClick.php?lat=' . round((float) $p['lat'], 3) . '&lon=' . round((float) $p['lon'], 3) : '';
$season = Guides::season($wxNow);
$wxCond = Weather::conditions($wxNow);
$chain = Guides::chain($pdo, $key);
$parents = array_slice($chain, 1);
$kids = Guides::children($pdo, $p);
$count = (int) PageCache::remember($pdo, 'guides:count2:' . $key, 3600, fn () => (int) $pdo->query("SELECT COUNT(*) FROM entities e WHERE e.entity_type = 'merchant' AND e.is_suppressed = 0 AND e.status IN ('unclaimed','claimed-unverified','pending-verification','verified') AND " . PlaceScope::businessOnly('e') . " AND " . $p['sql'])->fetchColumn());   // drop-offs aren't places (Release A; a new cache key, so the old count isn't served for an hour)
$html = fn (string $s) => isset($sec[$s]) ? '<div class="gd-w">' . Guides::html($pdo, $key, $s, (string) $sec[$s]) . '</div>' : '';
$sg = ['place_name' => $p['name'], 'place_key' => in_array($p['level'], ['hub', 'geo_hub', 'cluster'], true) ? $key : 'cluster:' . $p['cluster']];   // "Tell us" (js/suggest.js)
$blocks = [];

// ---- At a glance: what we wrote, the story, the facts --------------------------------------------------------------
$story = null;
if (is_file(__DIR__ . '/../../app/lib/Stories.php')) { require_once __DIR__ . '/../../app/lib/Stories.php'; try { $story = Stories::published($pdo, $key); } catch (Throwable) { $story = null; } }
$facts = [];
require_once __DIR__ . '/../../app/lib/Dossier.php';
$dfx = in_array($p['level'], ['town', 'sub'], true) ? Dossier::facts($pdo, $key) : [];   // Wikidata's facts, kept with the place (CC0)
if (!$g['elevation_ft'] && !empty($dfx['elevation_ft'])) $g['elevation_ft'] = (int) $dfx['elevation_ft'];
if ($g['elevation_ft']) $facts[] = ['Elevation', number_format((int) $g['elevation_ft']) . ' ft'];
if (!empty($dfx['founded'])) $facts[] = ['Founded', (int) $dfx['founded']];
if (!empty($dfx['population'])) $facts[] = ['Population', number_format((int) $dfx['population'])];
if ($p['level'] === 'hub') {
    $towns = 0; foreach ($kids as $k) $towns += count(Guides::children($pdo, Guides::place($pdo, $k[0]) ?: $p));
    $facts[] = ['Areas', count($kids)]; $facts[] = ['Communities', $towns]; $facts[] = ['Listings', number_format($count)];
}
$glance = $html('glance') . ($p['about'] !== '' && !isset($sec['glance']) ? '<div class="gd-w"><p>' . tv_e($p['about']) . '</p></div>' : '');
if ($facts) $glance .= '<dl class="gd-facts">' . implode('', array_map(fn ($f) => '<div><dt>' . tv_e($f[0]) . '</dt><dd>' . tv_e((string) $f[1]) . '</dd></div>', $facts)) . '</dl>';
if ($story) {
    $glance .= '<details class="gd-more"><summary>' . tv_e($story['title'] !== '' ? $story['title'] : 'The story of ' . $p['name']) . '</summary><div class="gd-w">' . GuideLinks::linkify($pdo, 'story:' . $key, 'story', Stories::html((string) $story['body'])) . '</div>'
        . (trim((string) $story['sources']) !== '' ? '<p class="gd-src">Sources: ' . tv_e(preg_replace('/\s*\n+\s*/', ' · ', trim((string) $story['sources']))) . '</p>' : '') . '</details>';
}
$written = $g['status'] === 'live' && array_filter($sec, fn ($v) => trim((string) $v) !== '');   // a written guide says who compiled it (decisions/0131: Claude and our crawler, corrected by our team)
$gdi = json_decode((string) ($g['draft_info'] ?? ''), true) ?: [];   // freshness shown (decisions/0133): first published, and last written or checked
$gFresh = max(strtotime((string) ($g['updated_at'] ?: 'now')), strtotime((string) ($gdi['checked_at'] ?? '2000-01-01')));
if ($g['status'] === 'live' && !empty($gdi['first_look'])) $glance = '<p data-tv-status="info" data-tv-label="First look:">We\'re still researching ' . tv_e($p['name']) . '. This first look was gathered and written quickly from public sources; the full guide, with its history and more places, follows within a day or two.</p>' . $glance;   // a guide built on demand (decisions/0147 §1a)
$glance .= '<p class="gd-ask">' . ($written ? 'Compiled by Traversence from public sources and local knowledge · first published ' . tv_e(date('M j, Y', strtotime((string) ($g['published_at'] ?: $g['updated_at'] ?: 'now')))) . ' · checked ' . tv_e(date('M j, Y', $gFresh)) . '. ' : '')
    . 'Know ' . tv_e($p['name']) . ' well? <button type="button" data-tv-suggest="' . tv_e(json_encode($sg + ['kind' => 'story'])) . '">' . ($written ? 'Suggest a change' : 'Tell us what this guide should say') . '</button></p>';
$blocks['glance'] = $glance;

$dirQ = ['hub' => 'hub=' . rawurlencode($p['slug']), 'geo_hub' => 'geo=' . rawurlencode($p['slug']), 'cluster' => 'cluster=' . rawurlencode($p['slug']),
         'town' => http_build_query(['town' => $p['name'], 'state' => $p['state']]), 'sub' => http_build_query(['cluster' => Guides::place($pdo, 'cluster:' . $p['cluster'])['slug'] ?? '', 'local' => $p['name']])][$p['level']];
// ---- Essentials: alerts and roads (filled in live), who to call, nearest care ---------------------------------------
$res = hub_resources($pdo, $chain);
$eg = in_array($p['level'], ['town', 'sub', 'cluster', 'geo_hub'], true) ? Guides::essentials($pdo, $p) : [];
$care = $eg;
$ess = '<div id="wx-alerts" hidden></div>' . $html('essentials') . '<div id="roads" hidden><h3 class="gd-h3">Roads</h3><ul class="gd-roads"></ul><p class="gd-src">Source: <a href="https://az511.com" target="_blank" rel="noopener">AZ 511</a>. Check before you drive.</p></div>';
if ($res) {
    $ess .= '<div id="who-to-call"><h3 class="gd-h3">Who to call</h3><p class="gd-note">For an emergency, call 911.</p>';
    foreach ($res as [$gt, $pl, $rows]) {
        $ess .= '<p class="gd-from">' . tv_e($pl) . '</p><ul class="gd-calls">';
        foreach ($rows as $e) $ess .= '<li><a href="tel:' . tv_e(preg_replace('/[^\d+]/', '', (string) $e['phone'])) . '" data-out="call"><b>' . tv_e((string) $e['name']) . '</b> <span>' . tv_e((string) $e['phone']) . '</span></a>'
            . ($e['purpose'] !== '' ? '<small>' . tv_e((string) $e['purpose']) . ($e['hours'] !== '' ? ' · ' . tv_e((string) $e['hours']) : '') . '</small>' : '') . '</li>';
        $ess .= '</ul>';
    }
    $ess .= '</div>';
}
if ($eg) {
    // by who needs them: everyone's first, then the visitor's or the resident's (the dial shows one or the other)
    $ordV = $ordL = 0; $ess .= '<div class="gd-eg">';
    foreach ($eg as [$k, $label, $who, $rows]) {
        $oV = $who === 'l' ? 99 : $ordV++;
        $oL = $k === 'care' ? -1 : ($who === 'all' ? 50 + $oV : ($who === 'l' ? $ordL++ : 99));   // Live here: care, then the resident's, then everyone's
        $ess .= '<div class="gd-eg1' . ($k === 'care' ? ' gd-eg-wide' : '') . '" data-aud="' . tv_e($who) . '" style="--ov:' . $oV . ';--ol:' . $oL . '"><h3 class="gd-h3">' . tv_e($label) . '</h3>'
            . ($k === 'care' ? '<div class="gd-grid">' . implode('', array_map(fn ($r) => hub_card($r), $rows)) . '</div>' : '<ul class="gd-erows">' . implode('', array_map('hub_row', $rows)) . '</ul>')
            . '<a class="gd-all" href="/directory/?' . tv_e($dirQ . '&q=' . rawurlencode($label)) . '">See more</a></div>';
    }
    $ess .= '</div>';
}
$blocks['essentials'] = $ess;
$essWait = !$res && !$care && !isset($sec['essentials']);   // only the live parts: shown when an alert or a road report arrives
if ($essWait && $p['level'] === 'hub') unset($blocks['essentials']);

// ---- Getting there & around ------------------------------------------------------------------------------------------
if (isset($sec['getting'])) {
    require_once __DIR__ . '/../../app/lib/Roads511.php';
    $named = Roads511::roadsIn((string) $sec['getting']);
    $blocks['getting'] = $html('getting') . ($named ? '<p class="gd-note">Conditions on ' . tv_e(implode(', ', $named)) . ' are under Essentials.</p>' : '');
}

// ---- Next stops: the places one step down -------------------------------------------------------------------------------
if ($kids) {
    $nh = '<div class="gd-next">';
    foreach ($kids as [$kk, $kn, $ku, $kl, $kc, $planned])
        $nh .= '<a class="gd-stop tv-bleed' . ($planned ? ' soon' : '') . '" href="' . tv_e($ku) . '"><b>' . tv_e($kn) . '</b>' . ($kl !== '' ? '<small>' . tv_e(mb_strimwidth($kl, 0, 120, '…')) . '</small>' : '')
            . '<span>' . ($planned ? 'Coming soon' : ($kc ? number_format($kc) . ' listing' . ($kc === 1 ? '' : 's') : Guides::TIERS[Guides::place($pdo, $kk)['level'] ?? 'sub'][1])) . '</span></a>';
    $blocks['next'] = $nh . '</div>';
}

// ---- Landmarks & heritage -----------------------------------------------------------------------------------------------
$lms = Guides::landmarks($pdo, $p, 8);
$lmh = '';
if ($lms) {
    $lmh = '<div class="gd-grid">';
    foreach ($lms as $l) $lmh .= '<div class="gd-card tv-bleed"><div class="gd-cb"><a class="gd-n" href="/place/landmark.php?id=' . (int) $l['gnis_id'] . '">' . tv_e((string) $l['name']) . '</a><small>' . tv_e(implode(' · ', array_filter([ucfirst((string) $l['fclass']), (string) $l['near_town'], round((float) $l['miles']) . ' mi']))) . '</small>'
        . ($l['wd_desc'] ? '<small>' . tv_e(mb_strimwidth((string) $l['wd_desc'], 0, 110, '…')) . '</small>' : '') . '</div>' . ($l['image_url'] ? '<img class="gd-thumb" src="' . tv_e((string) $l['image_url']) . '" alt="" loading="lazy">' : '') . '</div>';
    $lmh .= '</div>';
}
if (isset($sec['landmarks']) || $lmh !== '') $blocks['landmarks'] = $html('landmarks') . $lmh;

// ---- Eat & drink, Places to stay ----------------------------------------------------------------------------------------
$eat = Guides::picks($pdo, $p, 'eat', in_array($p['level'], ['town', 'sub'], true) ? 9 : 6, $mode);
if (isset($sec['eat']) || $eat) $blocks['eat'] = $html('eat') . hub_cards($eat);
$stay = Guides::picks($pdo, $p, 'stay', 6, $mode);
if (isset($sec['stay']) || $stay) $blocks['stay'] = $html('stay') . hub_cards($stay);

// ---- Things to do: what we wrote, outdoors and public lands, and the filter's picks (the season leads) ------------------
$do = Guides::picks($pdo, $p, 'do', 8, $mode, $season, $wxCond['lead']);
$shop = Guides::picks($pdo, $p, 'shop', 4, $mode, '', $wxCond['lead']);
$rec = '';
if ($p['level'] !== 'hub' && $p['lat'] !== null && is_file(__DIR__ . '/../../app/lib/crawler/RecBulk.php')) {
    require_once __DIR__ . '/../../app/lib/crawler/RecBulk.php';
    try {
        $pts = [[$p['lat'], $p['lon']]];
        if ($p['level'] === 'geo_hub') { $pts = []; foreach ($kids as $k) { $c = Guides::place($pdo, $k[0]); if ($c && $c['lat'] !== null) $pts[] = [$c['lat'], $c['lon']]; } }
        $recPlaces = RecBulk::near($pdo, $pts, ['geo_hub' => 25, 'cluster' => 35, 'town' => 25, 'sub' => 20][$p['level']] ?? 25, 40);
        if ($recPlaces) {
            require_once __DIR__ . '/../../app/ui/collections.php';
            $rec = '<h3 class="gd-h3">Outdoors &amp; public lands</h3>' . tv_collections_css() . tv_outdoor_collections(array_map(fn ($rp) => ['name' => RecBulk::displayName($rp['name']), 'kind' => RecBulk::kindLabel($rp),
                'org' => (string) $rp['org'], 'miles' => (float) $rp['miles'], 'url' => '/place/recreation.php?id=' . rawurlencode($rp['ridb_id'])], $recPlaces), ['open' => 1, 'suggest' => $sg + ['topic' => 'outdoors']])
                . '<p class="gd-src">Source: Recreation.gov. Check hours, fees and permits with the managing agency before you go.</p>';
        }
    } catch (Throwable) { $rec = ''; }
}
$doH = $html('do') . ($do ? hub_cards($do) : '') . $rec . ($shop ? '<h3 class="gd-h3">Shopping &amp; local goods</h3><div class="gd-grid">' . implode('', array_map(fn ($r) => hub_card($r), $shop)) . '</div>' : '');
if (trim(strip_tags($doH)) !== '') $blocks['do'] = ($wxCond['why'] !== '' ? '<p data-tv-status="' . ($wxCond['warn'] ? 'bad' : 'info') . '" data-tv-label="Right now:">' . tv_e($wxCond['why']) . '</p>' : '') . $doH;   // what the weather means today (Weather::conditions)

// ---- Events, Itineraries: what we wrote ---------------------------------------------------------------------------------
if (isset($sec['footsteps'])) { $blocks['footsteps'] = $html('footsteps'); require_once __DIR__ . '/../../app/lib/Heritage.php'; $cr = Heritage::creditsFor($pdo, $key); if ($cr) $blocks['footsteps'] .= '<p class="gd-src">Historic records: ' . tv_e(implode(', ', $cr)) . '.</p>'; }   // credit where a source asks for it (the source register)   // the place's story where you can stand today (decisions/0133)
require_once __DIR__ . '/../../app/lib/LiveEvents.php';   // what's coming up (Live events), then what we wrote about the year here
$evRows = LiveEvents::forPlace($pdo, $p, 8);
$evH = $evRows ? '<h3 class="gd-h3">Coming up</h3>' . LiveEvents::html($evRows) . '<p class="gd-note">Dates come from each event\'s own page: check it before you go.</p>' : '';
if ($evH !== '' || isset($sec['events'])) $blocks['events'] = $evH . (isset($sec['events']) ? ($evH !== '' ? '<h3 class="gd-h3">The year here</h3>' : '') . $html('events') : '');
// ---- Suggested journeys: routes built by rules from this guide's own places (decisions/0146) ------------------------------
if (is_file(__DIR__ . '/../../app/lib/JourneyPlan.php')) {
    require_once __DIR__ . '/../../app/lib/JourneyPlan.php';
    require_once __DIR__ . '/../../app/ui/suggested-journeys.php';
    try { $sjs = JourneyPlan::forPlace($pdo, $key); } catch (Throwable) { $sjs = []; }
    if ($sjs) $blocks['journeys'] = tv_sj_css() . '<div class="sj-list">' . implode('', array_map(fn ($j) => tv_sj_card($j), $sjs)) . '</div>'
        . '<p class="gd-note">Built from the places in this guide, timed for a Saturday. <a href="/discovery/?view=plan">Plan your own trip</a> with the Trip Planner.</p>';
}
if (isset($sec['itineraries'])) $blocks['itineraries'] = $html('itineraries') . '<p class="gd-note"><a href="/discovery/?view=plan">Plan your own trip</a> with the Trip Planner.</p>';

// ---- Living here: the Census profile ------------------------------------------------------------------------------------
$zips = $p['zips'];
if (!$zips && in_array($p['level'], ['hub', 'geo_hub'], true)) {
    $st = $pdo->prepare($p['level'] === 'hub' ? 'SELECT mc.primary_zip, mc.associated_zips FROM micro_clusters mc JOIN geo_hubs g ON g.id = mc.geo_hub_id WHERE g.hub_id = ?' : 'SELECT primary_zip, associated_zips FROM micro_clusters WHERE geo_hub_id = ?');
    $st->execute([$p['id']]); $zips = hub_zips_of($st->fetchAll(PDO::FETCH_ASSOC));
}
$profile = $p['level'] === 'hub' ? null : PageCache::remember($pdo, 'hub:profile:' . $key, 86400, fn () => hub_profile($pdo, $zips, $p['name'], in_array($p['level'], ['cluster', 'town', 'sub'], true), $p['level'] === 'cluster' ? hub_neighbours($pdo, $p['id']) : []));
if ($profile) {
    $cols = $profile['cols']; $here = $cols[0][1];
    $words = hub_plain_words($p['name'], $here, $profile['ref']);
    $money = fn ($v) => $v === null ? '—' : '$' . number_format($v);
    $pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 0) . '%';
    $num = fn ($v) => $v === null ? '—' : number_format($v);
    $pm = fn ($m, $money = false, $dec = 0) => $m === null ? '' : ' <span class="text-[color:var(--tv-meta)]">± ' . ($money ? '$' : '') . number_format($m, $dec) . '</span>';
    $approx = fn ($q) => $q['approx'] ? '≈ ' : '';
    $rowsP = [
        ['Population', fn ($q) => $num($q['population']) . $pm($q['population_moe'])],
        ['Median age', fn ($q) => $q['median_age'] === null ? '—' : $approx($q) . number_format($q['median_age'], 1) . $pm($q['median_age_moe'], false, 1)],
        ['Median household income', fn ($q) => $q['median_income'] === null ? '—' : $approx($q) . $money($q['median_income']) . $pm($q['median_income_moe'], true)],
        ['Median home value', fn ($q) => $q['median_home'] === null ? '—' : $approx($q) . $money($q['median_home']) . $pm($q['median_home_moe'], true)],
        ['Median rent', fn ($q) => $q['median_rent'] === null ? '—' : $approx($q) . $money($q['median_rent'])],
        ['Seasonal / second homes', fn ($q) => $pct($q['seasonal'])],
        ['Commute under 20 minutes', fn ($q) => $pct($q['short_commute'])],
        ["Bachelor's degree or higher", fn ($q) => $pct($q['bachelors'])],
        ['Unemployment', fn ($q) => $pct($q['unemployment'])],
        ['Below poverty line', fn ($q) => $pct($q['poverty'])],
        ['Housing units', fn ($q) => $num($q['housing_units'])],
        ['Top industries', fn ($q) => $q['industries'] ? implode('<br>', array_map(fn ($i) => tv_e($i[0]) . ' ' . round($i[1] * 100) . '%', $q['industries'])) : '—'],
    ];
    $lh = ($words['text'] !== '' ? '<div class="gd-w"><p>' . tv_e($words['text']) . '</p></div>' : '')
        . ($words['tags'] ? '<ul class="gd-tags">' . implode('', array_map(fn ($t) => '<li>' . tv_e($t) . '</li>', $words['tags'])) . '</ul>' : '')
        . '<details class="gd-more"><summary>See the numbers</summary><div class="gd-tbl"><table><thead><tr><th></th>' . implode('', array_map(fn ($c) => '<th>' . tv_e($c[0]) . '</th>', $cols)) . '</tr></thead><tbody>';
    foreach ($rowsP as [$lbl, $fmt]) $lh .= '<tr><th>' . tv_e($lbl) . '</th>' . implode('', array_map(fn ($c) => '<td>' . $fmt($c[1]) . '</td>', $cols)) . '</tr>';
    $lh .= '</tbody></table></div><p class="gd-src">Source: ' . tv_e(PlaceProfile::CITATION) . ' Small areas can have wide margins of error.' . ($here['approx'] ? ' ≈ marks medians combined across ZIP areas (population-weighted).' : '') . '</p></details>';
    $blocks['living'] = $lh;
}

// ---- Local: browse businesses, Support & wellness, community groups -----------------------------------------------------
$support = PageCache::remember($pdo, 'hub:support:' . $key, 86400, fn () => hub_support($pdo, $p['sql']));
$loc = '<div class="gd-local"><p><b>' . number_format($count) . '</b> public listing' . ($count === 1 ? '' : 's') . ' in ' . tv_e($p['name']) . '.</p><a class="gd-btn" href="/directory/?' . tv_e($dirQ) . '">Browse local businesses</a></div>';
if ($support) {
    $loc .= '<h3 class="gd-h3">Support &amp; wellness</h3><div class="gd-sup">';
    foreach ($support as $grp => $info) {
        $top = []; $seenN = [];
        foreach ($info['top'] as [$lid, $ln]) { $nk = strtolower(preg_replace('/\W+/', '', $ln)); if (isset($seenN[$nk])) continue; $seenN[$nk] = 1; $top[] = '<a href="/listing/view.php?id=' . (int) $lid . '">' . tv_e($ln) . '</a>'; }
        $loc .= '<div class="tv-bleed"><a class="gd-sg" href="/directory/?' . tv_e($dirQ . '&q=' . rawurlencode($grp)) . '">' . tv_e($grp) . ' <span>' . (int) $info['count'] . '</span></a>' . ($p['level'] !== 'hub' && $top ? '<small>' . implode(' · ', $top) . '</small>' : '') . '</div>';
    }
    $loc .= '</div>';
}
if (is_file(__DIR__ . '/../../app/lib/Community.php')) {
    require_once __DIR__ . '/../../app/lib/Community.php';
    $gk = in_array($p['level'], ['hub', 'geo_hub', 'cluster'], true) ? $key : 'cluster:' . $p['cluster'];
    try { $pgs = Community::ready($pdo) ? Community::directory($pdo, '', 6, $gk) : []; } catch (Throwable) { $pgs = []; }
    if ($pgs) $loc .= '<h3 class="gd-h3">Community groups</h3><div class="gd-next">' . implode('', array_map(fn ($pg) => '<a class="gd-stop tv-bleed" href="/community/group.php?id=' . (int) $pg['id'] . '&src=place"><b>' . tv_e($pg['name']) . '</b><span>' . (int) $pg['members'] . ' member' . ((int) $pg['members'] === 1 ? '' : 's') . (!empty($pg['parent']) ? ' · part of ' . tv_e($pg['parent']['name']) : '') . '</span></a>', $pgs)) . '</div>';
    // groups that name this place among the places they're about (decisions/0136 §3): their name and members, nothing more
    try { $tgs = Community::growReady($pdo) ? GroupGrowth::about($pdo, $gk, 6) : []; } catch (Throwable) { $tgs = []; }
    if ($tgs) $loc .= '<h3 class="gd-h3">Talked about by</h3><div class="gd-next">' . implode('', array_map(fn ($tg) => '<a class="gd-stop tv-bleed" href="/community/group.php?id=' . (int) $tg['id'] . '&src=place"><b>' . tv_e($tg['name']) . '</b><span>' . (int) $tg['members'] . ' member' . ((int) $tg['members'] === 1 ? '' : 's') . '</span></a>', $tgs)) . '</div>';
}
// businesses we know only by name here: "Help us find this business" (decisions/0164 §3.5), a request, never a listing
if (is_file(__DIR__ . '/../../app/lib/HelpFind.php')) {
    require_once __DIR__ . '/../../app/lib/HelpFind.php';
    try { $loc .= HelpFind::block($pdo, $p, $sg); } catch (Throwable $e) { error_log('hub help find: ' . $e->getMessage()); }
}
$blocks['local'] = $loc;

// ---- the page ----------------------------------------------------------------------------------------------------------
$orderV = array_flip(array_keys(Guides::SECTIONS));
$orderL = array_flip(Guides::LIVE_ORDER);
$heroScope = in_array($p['level'], ['hub', 'geo_hub', 'cluster'], true) ? $key : '';
?>
<style>
  .gd{display:flex;flex-direction:column;gap:.9rem;margin-top:.6rem}
  /* a wide screen: the guide in two thirds, the places one step down (towns…) in the third beside it; narrower, one column in the dial's order */
  .gd-cols{display:flex;flex-direction:column;gap:.9rem;margin-top:.6rem}.gd-cols>.gd,.gd-cols>.gd-side{display:contents}
  @media (min-width:1024px){ .gd-cols{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start}
    .gd-cols>.gd{display:flex;margin-top:0}.gd-cols>.gd-side{display:flex;flex-direction:column;gap:.9rem}
    .gd-side .gd-next{grid-template-columns:repeat(auto-fill,minmax(min(9.5rem,100%),1fr))} }
  .gd-sec{order:var(--ov);background:var(--tv-canvas);border:1px solid rgba(203,185,141,.7);border-radius:1rem;padding:1rem 1.1rem;box-shadow:0 1px 3px rgba(62,43,31,.08)}
  body.gd-live .gd-sec{order:var(--ol)}
  .gd-sec>h2{font:700 1.25rem/1.2 var(--tv-font-head,Georgia,serif);color:var(--tv-ink);margin:0 0 .45rem}
  .gd-h3{font:700 1rem var(--tv-font-head,Georgia,serif);color:var(--tv-ink);margin:.9rem 0 .4rem}
  /* essentials by who needs them: everyone's, then the visitor's (Visiting) or the resident's (Live here), two to a row when there's room */
  .gd-eg{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(19rem,100%),1fr));gap:0 1rem}.gd-eg1{order:var(--ov)}body.gd-live .gd-eg1{order:var(--ol)}
  .gd-eg-wide{grid-column:1/-1}
  body.gd-live .gd-eg1[data-aud="v"],body:not(.gd-live) .gd-eg1[data-aud="l"]{display:none}
  .gd-erows{list-style:none;margin:0;padding:0;display:grid;gap:3px}.gd-erows li{display:flex;justify-content:space-between;align-items:center;gap:.5rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.6rem;padding:.4rem .55rem}
  .gd-erows li>div{min-width:0}.gd-erows .gd-n{font-size:.92rem}.gd-erows small{display:block;color:var(--tv-meta);font-size:.76rem}
  .gd-acts.gd-acts-row{flex-direction:row;align-items:center}.gd-all{display:inline-block;font-size:.78rem;color:var(--tv-link);text-decoration:underline;margin-top:.25rem}
  .gd-w{color:var(--tv-ink);font-size:.95rem;line-height:1.6;max-width:48rem}.gd-w p{margin:0 0 .6rem}.gd-w h3{font:700 1.02rem var(--tv-font-head,Georgia,serif);margin:.8rem 0 .3rem}.gd-w ul{margin:0 0 .6rem 1.1rem;list-style:disc}
  .gd-w a.gl{color:var(--tv-link);text-decoration:underline}
  .gd-note,.gd-src{font-size:.8rem;color:var(--tv-meta);margin:.4rem 0 0}.gd-note a,.gd-src a{text-decoration:underline}
  .gd-td{font-size:.72rem;margin:.5rem 0 0;text-align:right}.gd-td a{color:var(--tv-meta);text-decoration:underline}
  .gd-ask{font-size:.85rem;color:var(--tv-meta);margin:.6rem 0 0}.gd-ask button{font-weight:700;color:var(--tv-link);text-decoration:underline}
  .gd-more{margin-top:.6rem}.gd-more>summary{cursor:pointer;font-weight:700;color:var(--tv-link);font-size:.9rem}
  .gd-facts{display:flex;flex-wrap:wrap;gap:.4rem .6rem;margin:.5rem 0 0}.gd-facts div{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.6rem;padding:.3rem .65rem}
  .gd-facts dt{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--tv-meta)}.gd-facts dd{margin:0;font:700 1.05rem var(--tv-font-head,Georgia,serif);color:var(--tv-ink)}
  .gd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(17rem,100%),1fr));gap:.55rem}
  .gd-card{position:relative;display:flex;justify-content:space-between;gap:.5rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.8rem;padding:.65rem .75rem}
  .gd-card[data-link-host] .gd-cb{padding-right:.2rem}.gd-card[data-link-host] .gd-acts{margin-top:2.2rem}   /* the Link button is the corner; the actions run down under it */
  .gd-cb{min-width:0}.gd-n{font-weight:700;font-size:.98rem;line-height:1.3;color:var(--tv-link);text-decoration:underline;text-wrap:balance;overflow-wrap:break-word}.gd-cb small{display:block;color:var(--tv-meta);font-size:.8rem;margin-top:.12rem}.gd-cb .gd-d{font-size:.86rem;line-height:1.45;color:var(--tv-ink);margin:.35rem 0 0}.gd-cb .gd-x{font-size:.8rem;color:var(--tv-meta);margin:.3rem 0 0}
  .gd-acts{display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;flex-shrink:0}
  .gd-acts>a{width:30px;height:30px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--tv-line-strong);background:var(--tv-field);color:var(--tv-icon,#B45309)}.gd-acts svg{width:15px;height:15px}
  .gd-thumb{width:64px;height:64px;object-fit:cover;border-radius:.5rem;flex-shrink:0}
  .gd-next{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(14rem,100%),1fr));gap:.55rem}
  .gd-stop{display:flex;flex-direction:column;gap:.15rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.8rem;padding:.65rem .8rem;text-decoration:none;color:var(--tv-ink)}
  .gd-stop b{font:700 1.02rem var(--tv-font-head,Georgia,serif);color:var(--tv-link);text-decoration:underline}.gd-stop small{color:var(--tv-meta);font-size:.8rem}.gd-stop span{font-size:.75rem;color:var(--tv-meta)}
  .gd-stop.soon{opacity:.7}.gd-stop:hover{border-color:var(--tv-icon,#B45309)}
  .gd-compact .gd-next{grid-template-columns:repeat(auto-fill,minmax(min(10.5rem,48%),1fr))}.gd-compact .gd-stop{padding:.45rem .6rem}.gd-compact .gd-stop b{font-size:.92rem}
  .gd-calls{list-style:none;margin:0;padding:0;display:grid;gap:.35rem}.gd-calls li{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.6rem;padding:.45rem .65rem}
  .gd-calls a{display:flex;justify-content:space-between;gap:.6rem;color:var(--tv-ink);text-decoration:none}.gd-calls a span{color:var(--tv-link);font-weight:700;white-space:nowrap}.gd-calls small{display:block;color:var(--tv-meta);font-size:.8rem}
  .gd-from{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--tv-meta);margin:.5rem 0 .25rem}
  .gd-roads{list-style:none;margin:0;padding:0;display:grid;gap:.35rem}.gd-roads li{background:var(--tv-card-a);border:1px solid var(--tv-line);border-left:4px solid var(--tv-line-strong);border-radius:.5rem;padding:.45rem .65rem;font-size:.88rem}
  .gd-roads li.closed{border-left-color:color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas))}.gd-roads b{color:var(--tv-ink)}.gd-roads small{display:block;color:var(--tv-meta)}
  .gd-alert{background:var(--tv-fail-bg);border:1px solid color-mix(in srgb,var(--tv-fail) 35%,var(--tv-canvas));border-radius:.7rem;padding:.6rem .8rem;margin-bottom:.6rem;color:var(--tv-fail)}.gd-alert b{display:block}.gd-alert details{font-size:.85rem;margin-top:.25rem}
  .gd-local{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.6rem}.gd-local p{margin:0}
  .gd-btn{display:inline-flex;align-items:center;background:var(--tv-btn);color:var(--tv-btn-text);font-weight:700;border-radius:.75rem;padding:.5rem 1rem;text-decoration:none}
  .gd-sup{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(16rem,100%),1fr));gap:.5rem}.gd-sup>div{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.7rem;padding:.5rem .7rem}
  .gd-sg{font-weight:700;color:var(--tv-link);text-decoration:underline}.gd-sg span{color:var(--tv-meta);font-weight:400;text-decoration:none}.gd-sup small{display:block;font-size:.78rem;margin-top:.15rem}.gd-sup small a{color:var(--tv-meta);text-decoration:underline}
  .gd-tags{display:flex;flex-wrap:wrap;gap:.35rem;list-style:none;margin:.5rem 0 0;padding:0}.gd-tags li{font-size:.75rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);border-radius:999px;padding:.15rem .6rem}
  .gd-tbl{overflow-x:auto;margin-top:.4rem}.gd-tbl table{font-size:.85rem;width:100%}.gd-tbl th,.gd-tbl td{text-align:left;padding:.3rem .8rem .3rem 0;border-top:1px solid rgba(203,185,141,.5);vertical-align:top}.gd-tbl th{font-weight:400;color:var(--tv-meta)}
  /* the guide bar: kind of guide, the Chameleon dial, the weather */
  .gd-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.6rem;margin-top:.6rem}
  .gd-bar .gd-wx{margin-left:auto}   /* the weather sits beside the dial: the dial stays at the right */
  @media (max-width:767px){ .gd-bar .gd-dial{margin-left:auto} }
  .gd-kind{font:700 .74rem system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:var(--tv-meta)}
  .gd-dial{display:inline-flex;border:1px solid var(--tv-line-strong);border-radius:999px;background:var(--tv-card-a);padding:3px}
  .gd-dial button{border-radius:999px;padding:.3rem .85rem;font-weight:700;font-size:.85rem;color:var(--tv-meta)}
  .gd-dial button[aria-pressed="true"]{color:#fff;background:var(--tv-copper);box-shadow:0 0 0 3px rgba(200,90,23,.18)}
  body.gd-live .gd-dial button[aria-pressed="true"]{background:#5E8F77;box-shadow:0 0 0 3px rgba(146,190,165,.35)}
  body.gd-live .gd-sec{border-top:3px solid var(--tv-sage)} body:not(.gd-live) .gd-sec{border-top:3px solid rgba(200,90,23,.55)}
  .gd-wx{display:flex;align-items:center;gap:.6rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.8rem;padding:.4rem .7rem;color:var(--tv-ink);text-decoration:none;font:inherit;text-align:left;cursor:pointer}
  .gd-wx i{font-style:normal;color:var(--tv-icon,#B45309);font-size:.8rem;transition:transform .2s}.gd-wx[aria-expanded="true"] i{transform:rotate(180deg)}
  .gd-wx svg{width:30px;height:30px;color:var(--tv-icon,#B45309)}.gd-wx b{font:700 1.35rem var(--tv-font-head,Georgia,serif)}.gd-wx small{display:block;font-size:.75rem;color:var(--tv-meta);line-height:1.25}
  .gd-wxs{display:flex;gap:.4rem;overflow-x:auto;padding:.1rem 0;margin-top:.5rem;scrollbar-width:thin}
  .gd-wxs>div,.gd-wxs>a{flex:0 0 auto;min-width:6.2rem;background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.7rem;padding:.4rem .55rem;font-size:.78rem;color:var(--tv-ink);text-decoration:none}
  .gd-wxs svg{width:20px;height:20px;color:var(--tv-icon,#B45309);display:block}.gd-wxs b{font:700 .95rem var(--tv-font-head,Georgia,serif)}
  .gd-wx[hidden],.gd-wxs[hidden],.gd-fc[hidden]{display:none!important}
  /* a placeholder until the weather arrives: soft bars in its place, so nothing jumps when it fills in */
  .gd-sk{color:transparent!important;background:linear-gradient(90deg,#EFE6D3 25%,#F8F1E2 50%,#EFE6D3 75%);background-size:200% 100%;animation:gd-sk 1.3s ease-in-out infinite;border-radius:.3rem}
  .gd-sk-i{display:inline-block;width:30px;height:30px;border-radius:999px}.gd-wxs .gd-sk-i{display:block;width:20px;height:20px}
  .gd-wx.gd-wait{cursor:default}
  .gd-bar .gd-wx-out{margin-left:auto;font-size:.85rem;color:var(--tv-link);text-decoration:underline}   /* no forecast: the link in the card's place */
  @keyframes gd-sk{0%{background-position:100% 0}100%{background-position:-100% 0}}
  @media (prefers-reduced-motion:reduce){.gd-sk{animation:none}}
  /* the forecast: 24 hours as a small graph (temperature, rain, wind) over seven days, like a weather card, not a page of it */
  .gd-fc{background:var(--tv-card-a);border:1px solid var(--tv-line);border-radius:.8rem;padding:.5rem .7rem .4rem;margin-top:.5rem;color:var(--tv-ink)}
  .gd-fc-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.3rem .6rem;font-size:.78rem;color:var(--tv-meta)}
  .gd-fc-x{flex:0 0 auto;display:inline-flex;align-items:center;gap:.25rem;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-meta);border-radius:999px;padding:.1rem .6rem;font:600 .74rem system-ui,sans-serif;cursor:pointer}
  .gd-fc-x:hover{border-color:var(--tv-icon,#B45309);color:var(--tv-icon,#B45309)}.gd-fc-x b{font-size:1rem;line-height:1}
  .gd-fc-r{display:inline-flex;flex-wrap:wrap;align-items:center;gap:.3rem}
  .gd-fc-tabs{display:inline-flex;gap:.2rem}.gd-fc-tabs button{font:600 .74rem system-ui,sans-serif;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-meta);border-radius:999px;padding:.1rem .55rem;cursor:pointer}
  .gd-fc-tabs button[aria-pressed="true"]{background:var(--tv-icon,#B45309);border-color:var(--tv-icon,#B45309);color:#fff}
  .gd-fc-row{display:grid;grid-template-columns:repeat(var(--n),1fr);text-align:center;font-size:.7rem;color:var(--tv-meta)}
  .gd-fc-v{color:var(--tv-meta);font-weight:600;margin-top:.3rem}
  .gd-fc svg.gd-fc-g{display:block;width:100%;height:40px}
  .gd-fc-days{display:grid;grid-template-columns:repeat(var(--n),1fr);gap:2px;margin-top:.35rem;border-top:1px solid var(--tv-line);padding-top:.35rem;text-align:center;font-size:.74rem}
  .gd-fc-days div{border-radius:.5rem;padding:.15rem 0}.gd-fc-days div:first-child{background:var(--tv-canvas)}
  .gd-fc-days svg{width:20px;height:20px;color:var(--tv-icon,#B45309);display:block;margin:.1rem auto}.gd-fc-days b{font-weight:700}.gd-fc-days span{color:var(--tv-meta)}
  .gd-fc .gd-src{margin:.3rem 0 0;font-size:.7rem;text-align:right}.gd-fc .gd-src a{color:var(--tv-link)}
  .gd-draft{background:var(--tv-hold-bg);border:1px dashed color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-radius:.6rem;padding:.4rem .7rem;font-size:.85rem;margin-top:.5rem}
  @media (max-width:767px){ .gd,.gd-cols{gap:3px} .gd-sec{border-radius:.5rem;padding:.8rem .7rem} .gd-grid,.gd-next,.gd-sup{gap:3px} .gd-wx b{font-size:1.15rem} }
</style>
<?= tv_explore_hero($pdo, ['name' => $p['name'], 'heading' => $p['name'] . ($p['level'] === 'town' && $p['state'] ? ', ' . $p['state'] : ''),
    'scope' => $heroScope, 'near' => $heroScope === '' && $p['lat'] !== null ? [$p['lat'], $p['lon']] : null,
    'trail' => array_reverse(array_map(fn ($c) => [$c['name'], $c['url']], $parents)),
    'link' => '<span data-link="place:' . tv_e($key) . '" data-link-label="' . tv_e($p['name']) . '"></span>',
    'stock' => ['subject' => 'landscape', 'place' => $key, 'owner' => 'place:' . $key, 'fallback' => 'landscape'],   // no journey photos yet: the library's, labelled (decisions/0143)
    'actions' => tv_engage_bar($pdo, 'place', $key, ['title' => $p['name'], 'fold' => true, 'tools' => ['map' => $p['lat'] !== null ? ['lat' => (float) $p['lat'], 'lon' => (float) $p['lon'], 'name' => $p['name']] : '', 'trip' => ['key' => $key], 'suggest' => $sg + ['kind' => 'story']]])]) /* ⋯ (2026-11-19) */ ?>
<script>
  window.onLocationChanged = function () {   // the hero's pencil: another place opens its guide
    var l = (typeof currentLocation !== 'undefined' && currentLocation) || {}, sc = l.scope;
    if (sc && sc.slug) { location.href = '/hub/?' + ({ cluster: 'cluster', geo_hub: 'geo', hub: 'hub' }[sc.level] || 'cluster') + '=' + encodeURIComponent(sc.slug); return; }
    location.href = '/discovery/';
  };
</script>
<div class="gd-bar">
  <span class="gd-kind"><?= tv_e($p['kind']) ?></span>
  <?php // the weather is there at once: what's stored (guide_live_tick keeps it fresh), else a placeholder until /api/guide-live.php answers
  $wn = $wxNow['now'] ?? null; ?>
  <button type="button" class="gd-wx<?= $wn ? '' : ' gd-wait' ?>" id="gd-wx" aria-expanded="false" aria-controls="gd-fc"<?= $wn ? '' : ' aria-busy="true"' ?><?= $p['level'] === 'hub' ? ' hidden' : '' ?>>
    <?php if ($wn): ?><?= Weather::icon((string) $wn['text'], !empty($wxNow['periods'][0]['day'])) ?><span><b><?= (int) $wn['temp_f'] ?>°</b><small><?= tv_e((string) $wn['text']) ?><?= $wn['wind'] !== '' ? ' · wind ' . tv_e((string) $wn['wind']) : '' ?></small></span><i aria-hidden="true">▾</i>
    <?php else: ?><span class="gd-sk gd-sk-i"></span><span><b class="gd-sk">00°</b><small class="gd-sk">Weather loading</small></span><?php endif; ?>
  </button>
  <div class="gd-dial" role="group" aria-label="Show this guide for"><button type="button" data-mode="v" aria-pressed="<?= $mode === 'v' ? 'true' : 'false' ?>">Visiting</button><button type="button" data-mode="l" aria-pressed="<?= $mode === 'l' ? 'true' : 'false' ?>">Live here</button></div>
</div>
<div class="gd-fc" id="gd-fc" hidden></div>
<?php $strip = []; try { foreach (Guides::stripTowns($pdo, $p) as $c) $strip[] = [$c, Weather::stored($pdo, (float) $c['lat'], (float) $c['lon'])['now'] ?? null]; } catch (Throwable $e) {} ?>
<div class="gd-wxs" id="gd-wxs"<?= $strip ? '' : ' hidden' ?>><?php foreach ($strip as [$c, $n]): ?><a href="<?= tv_e($c['url']) ?>"<?= $n ? '' : ' class="gd-wait"' ?>><?= $n ? Weather::icon((string) $n['text']) : '<span class="gd-sk gd-sk-i"></span>' ?><b<?= $n ? '' : ' class="gd-sk"' ?>><?= $n ? (int) $n['temp_f'] . '°' : '00°' ?></b> <?= tv_e($c['name']) ?><br><?= $n ? tv_e((string) $n['text']) : '<span class="gd-sk">Loading</span>' ?></a><?php endforeach; ?></div>
<?php if ($preview && $g['status'] !== 'live'): ?><p class="gd-draft">Preview of a draft: visitors don't see the written sections until it's live.</p><?php endif; ?>
<?php // the third column beside the guide (a wide screen): at a glance, the places one step down, landmarks & heritage
$side = array_values(array_filter(['glance', 'next', 'landmarks'], fn ($k) => isset($blocks[$k]) && trim($blocks[$k]) !== '')); ?>
<?php if ($side): ?><div class="gd-cols"><?php endif; ?>
<?php foreach ($side ? ['main', 'side'] : ['main'] as $col): ?>
<?= $col === 'main' ? '<div class="gd" id="gd">' : '<aside class="gd-side" aria-label="About ' . tv_e($p['name']) . '">' ?>
  <?php foreach (Guides::SECTIONS as $k => [$label]): if (!isset($blocks[$k]) || trim($blocks[$k]) === '' || in_array($k, $side, true) !== ($col === 'side')) continue; ?>
  <section class="gd-sec tv-bleed<?= $k === 'next' && $p['level'] === 'town' || $k === 'next' && count($kids) > 8 ? ' gd-compact' : '' ?>" id="g-<?= tv_e($k) ?>"<?= $k === 'essentials' && $essWait ? ' hidden data-gd-wait' : '' ?> style="--ov:<?= (int) $orderV[$k] ?>;--ol:<?= (int) ($orderL[$k] ?? 20) ?>" aria-labelledby="gh-<?= tv_e($k) ?>">
    <h2 id="gh-<?= tv_e($k) ?>"><?= tv_e($k === 'next' ? (Guides::TIERS[$p['level']][2] ?: 'Next stops') : ($k === 'local' && $p['level'] !== 'hub' ? 'Local' : $label)) ?></h2>
    <?= $blocks[$k] ?>
    <?php if ($g['status'] === 'live' && trim((string) ($sec[$k] ?? '')) !== ''): ?><p class="gd-td"><a href="/takedown.php?<?= tv_e(http_build_query(['kind' => 'guide', 'ref' => $key, 'section' => $k])) ?>" rel="nofollow">Ask us to take this down</a></p><?php endif; ?>
  </section>
  <?php endforeach; ?>
<?= $col === 'main' ? '</div>' : '</aside>' ?>
<?php endforeach; ?>
<?php if ($side): ?></div><?php endif; ?>
<?= tv_engage_comments($pdo, 'place', $key) ?>
<script>
(function () {
  var body = document.body, set = function (m, save) {
    body.classList.toggle('gd-live', m === 'l');
    document.querySelectorAll('.gd-dial button').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-mode') === m ? 'true' : 'false'); });
    if (save) try { document.cookie = 'tv_guide_mode=' + m + ';path=/;max-age=' + 31536000 + ';samesite=lax'; } catch (x) {}
  };
  var m = <?= json_encode($mode) ?>;
  // arriving from Get Local (the directory) with no choice yet: Live here
  if (!/(^|;\s*)tv_guide_mode=/.test(document.cookie) && /\/directory\//.test(document.referrer || '') && !/[?&]mode=/.test(location.search)) m = 'l';
  set(m, false);
  document.querySelectorAll('.gd-dial button').forEach(function (b) { b.addEventListener('click', function () { set(b.getAttribute('data-mode'), true); }); });
  // the live parts: weather, alerts, roads (decisions/0131 §5)
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var noWx = function (c) {   // no forecast to show: the card goes, a link to the weather service's forecast for the place in its place
    var u = <?= json_encode($wxLink) ?>;
    if (!u) { c.hidden = true; return; }
    var a = document.createElement('a'); a.className = 'gd-wx-out'; a.href = u; a.target = '_blank'; a.rel = 'noopener';
    a.textContent = 'Weather: the National Weather Service forecast'; c.parentNode.insertBefore(a, c); c.hidden = true;
  };
  var unwait = function () {   // the call failed: placeholders go, anything already drawn from what's stored stays
    var c = document.getElementById('gd-wx'), st = document.getElementById('gd-wxs');
    if (c.querySelector('.gd-sk')) noWx(c); c.removeAttribute('aria-busy'); c.classList.remove('gd-wait');
    st.querySelectorAll('a.gd-wait').forEach(function (x) { x.remove(); }); if (!st.children.length) st.hidden = true;
  };
  fetch('/api/guide-live.php?place=' + encodeURIComponent(<?= json_encode($key) ?>)).then(function (r) { return r.json(); }).then(function (j) {
    if (!j || !j.ok) { unwait(); return; }
    var w = j.weather, card = document.getElementById('gd-wx'), strip = document.getElementById('gd-wxs');
    card.removeAttribute('aria-busy'); card.classList.remove('gd-wait');
    if (!(w && w.now && w.now.temp_f != null) && card.querySelector('.gd-sk')) noWx(card);   // no weather after all: the placeholder goes, the link shows
    if (!(j.strip && j.strip.length) && strip.querySelector('.gd-sk')) strip.hidden = true;
    if (w && w.now && w.now.temp_f != null) {
      // the card opens the forecast here, on the page (the weather service is a link inside it, never the first tap)
      card.title = 'The forecast';
      card.innerHTML = w.now.icon + '<span><b>' + esc(w.now.temp_f) + '°</b><small>' + esc(w.now.text) + (w.now.wind ? ' · wind ' + esc(w.now.wind) : '') + (w.now.forecast ? ' · forecast for this hour' : '') + '</small></span><i aria-hidden="true">▾</i>';
      card.hidden = false;
      var fc = document.getElementById('gd-fc'), hrs = w.hours || [], days = w.days || [];
      var facts = [w.now.pop != null ? 'Precipitation ' + w.now.pop + '%' : '', w.now.humidity != null ? 'Humidity ' + w.now.humidity + '%' : '', w.now.wind ? 'Wind ' + w.now.wind : ''].filter(Boolean).join(' · ');
      var graph = function (k) {   // values over the line, times under it; the line is drawn to fit, the numbers never stretch
        var n = hrs.length, v = hrs.map(function (h) { return +h[k] || 0; }), lo = k === 'pop' ? 0 : Math.min.apply(null, v), hi = k === 'pop' ? 100 : Math.max.apply(null, v);
        var pts = v.map(function (x, i) { return [((i + .5) / n * 800).toFixed(1), (36 - (x - lo) / ((hi - lo) || 1) * 30).toFixed(1)]; });
        var line = 'M' + pts.map(function (p) { return p.join(','); }).join(' L');
        var col = { temp: '#D97706', pop: '#4A7FA8', mph: '#8A7355' }[k], fill = { temp: '#FBEBC8', pop: '#DCE8F1', mph: '#EFE6D6' }[k];
        return '<div class="gd-fc-row gd-fc-v" style="--n:' + n + '">' + v.map(function (x) { return '<span>' + x + (k === 'temp' ? '°' : k === 'pop' ? '%' : '') + '</span>'; }).join('') + '</div>'
          + '<svg class="gd-fc-g" viewBox="0 0 800 40" preserveAspectRatio="none" aria-hidden="true"><path d="' + line + ' L' + pts[n - 1][0] + ',40 L' + pts[0][0] + ',40Z" fill="' + fill + '"/><path d="' + line + '" fill="none" stroke="' + col + '" stroke-width="2.5" vector-effect="non-scaling-stroke"/></svg>'
          + '<div class="gd-fc-row" style="--n:' + n + '">' + hrs.map(function (h) { return '<span>' + esc(h.t) + '</span>'; }).join('') + '</div>';
      };
      // a Close button of its own on the forecast (Jason, 2026-11-12: "close the weather section is not intuitive"): the ▾ on the
      // weather card still opens and folds it too
      fc.innerHTML = '<div class="gd-fc-head"><span>' + esc(facts) + '</span><span class="gd-fc-r">' + (hrs.length > 1 ? '<span class="gd-fc-tabs" role="group" aria-label="Show"><button type="button" data-k="temp" aria-pressed="true">Temperature</button><button type="button" data-k="pop" aria-pressed="false">Rain</button><button type="button" data-k="mph" aria-pressed="false">Wind</button></span>' : '')
        + '<button type="button" class="gd-fc-x" data-fc-x aria-label="Close the forecast" title="Close the forecast"><b aria-hidden="true">×</b> Close</button></span></div>'
        + (hrs.length > 1 ? '<div class="gd-fc-graph">' + graph('temp') + '</div>' : '')
        + (days.length ? '<div class="gd-fc-days" style="--n:' + days.length + '">' + days.map(function (d) { return '<div title="' + esc(d.short) + (d.pop ? ' · ' + d.pop + '% chance' : '') + '"><small>' + esc(d.day) + '</small>' + d.icon + (d.hi != null ? '<b>' + esc(d.hi) + '°</b> ' : '') + (d.lo != null ? '<span>' + esc(d.lo) + '°</span>' : '') + '</div>'; }).join('') + '</div>' : '')
        + '<p class="gd-src">Forecast: <a href="' + esc(w.link) + '" target="_blank" rel="noopener">National Weather Service</a></p>';
      fc.querySelectorAll('.gd-fc-tabs button').forEach(function (b) { b.addEventListener('click', function () {
        fc.querySelectorAll('.gd-fc-tabs button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        fc.querySelector('.gd-fc-graph').innerHTML = graph(b.getAttribute('data-k'));
      }); });
      // folded when the page loads; the card opens and folds it
      card.addEventListener('click', function () { fc.hidden = !fc.hidden; card.setAttribute('aria-expanded', fc.hidden ? 'false' : 'true'); });
      fc.querySelector('[data-fc-x]').addEventListener('click', function () { fc.hidden = true; card.setAttribute('aria-expanded', 'false'); card.focus(); });
    } else if (j.strip && j.strip.length) {
      strip.innerHTML = j.strip.map(function (t) { return '<a href="' + esc(t.url) + '"' + (t.forecast ? ' title="No station has reported lately: the forecast for this hour"' : '') + '>' + t.icon + '<b>' + esc(t.temp) + '°' + (t.forecast ? '*' : '') + '</b> ' + esc(t.name) + '<br>' + esc(t.text) + '</a>'; }).join('')
        + (j.strip.some(function (t) { return t.forecast; }) ? '<p class="gd-src" style="flex-basis:100%">* the forecast for this hour: no station there has reported lately.</p>' : '');
      strip.hidden = false;
    }
    var show = function () { var e = document.querySelector('[data-gd-wait]'); if (e) e.hidden = false; };
    var al = document.getElementById('wx-alerts');
    if (al && j.alerts && j.alerts.length) {
      al.innerHTML = j.alerts.map(function (a) { return '<div class="gd-alert" role="alert"><b>' + esc(a.event) + '</b>' + esc(a.headline) + (a.instruction || a.description ? '<details><summary>What to do</summary>' + esc(a.instruction || a.description) + '</details>' : '') + '</div>'; }).join('');
      al.hidden = false; show();
    }
    var rd = document.getElementById('roads');
    if (rd && j.roads && j.roads.length) {
      rd.querySelector('ul').innerHTML = j.roads.map(function (r) { return '<li class="' + (r.closed ? 'closed' : '') + '"><b>' + esc(r.type) + ' · ' + esc(r.road) + '</b> ' + esc(r.what) + (r.where ? '<small>' + esc(r.where) + '</small>' : '') + '</li>'; }).join('');
      rd.hidden = false; show();
    }
  }).catch(unwait);
})();
</script>
<?php
echo '<script src="/js/link-button.js" defer></script>';   // the Link button (decisions/0047)
echo '<script src="/js/tv-tagcard.js" defer></script>';   // the preview card of a highlighted name (decisions/0132)
tv_page_end();
