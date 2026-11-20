<?php
// app/ui/maps-send.php: Send to Maps (decisions/0174 §4, release 1). A whole route, every stop in order, sent to the
// visitor's own Google Maps or Apple Maps: a link they tap, no key and no call from our server. The link works without
// script (Google, the first leg); js/tv-maps.js turns it into the chooser: Google or Apple (remembered on the device),
// from My location or the first stop, and legs of at most eight stops between the start and the end ("Leg 1 of 3"), so nothing is cut silently.

/**
 * Stops ([['lat', 'lon', 'name']]) as the button's data: [[lat, lon, name], …], rounded. Left out, as Directions leaves
 * them out: no point, a location kept private ('private' or 'private_location'), a story-only place ('story'), a point
 * only at a ZIP's middle ('approx') and a mailbox address. The places that make the stops apply these rules first;
 * this is the last guard.
 */
function tv_maps_stops(array $stops): array
{
    require_once __DIR__ . '/../lib/ListingActions.php';
    $out = [];
    foreach ($stops as $s) {
        if (!isset($s['lat'], $s['lon']) || $s['lat'] === null || $s['lon'] === null) continue;
        if (!empty($s['private']) || !empty($s['private_location']) || !empty($s['story']) || !empty($s['approx'])) continue;
        if (ListingActions::isMailbox((string) ($s['address'] ?? ''))) continue;
        $out[] = [round((float) $s['lat'], 5), round((float) $s['lon'], 5), (string) ($s['name'] ?? $s['label'] ?? '')];
    }
    return $out;
}

/** Without script: Google Maps, the first ten stops (an origin, eight waypoints, a destination), as before. */
function tv_maps_fallback(array $pts): string
{
    $pts = array_map(fn ($p) => $p[0] . ',' . $p[1], array_slice($pts, 0, 10));
    if (count($pts) < 2) return '';
    $q = ['api' => 1, 'origin' => array_shift($pts), 'destination' => array_pop($pts), 'travelmode' => 'driving'];
    if ($pts) $q['waypoints'] = implode('|', $pts);
    return 'https://www.google.com/maps/dir/?' . http_build_query($q);
}

/**
 * The attributes a Send to Maps link carries: href (the fallback), data-maps (the stops) and the count (data-out).
 * $title names the route in the chooser ("Lyman Lake: a day out"). '' when there are fewer than two stops.
 */
function tv_maps_attrs(array $stops, string $title = ''): string
{
    $pts = tv_maps_stops($stops);
    $href = tv_maps_fallback($pts);
    if ($href === '') return '';
    $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    return 'href="' . $e($href) . '" target="_blank" rel="noopener" data-out="directions" data-maps="' . $e(json_encode($pts, JSON_UNESCAPED_UNICODE)) . '"'
        . ($title !== '' ? ' data-maps-title="' . $e($title) . '"' : '');
}

/** The script, once per page. */
function tv_maps_script(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    return '<script src="/js/tv-maps.js" defer></script>';
}
