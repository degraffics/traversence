<?php
// The on-the-spot match (Jason, 2026-11-19, approved): a photo taken within 1.5 km needs only the place's own words; kind
// words (Landmarks::KIND_WORDS) may be missing only there. Landmarks::bestPhoto with Commons stood in; no database.
// Run: APP=<a site tree's app> php onspot.php
declare(strict_types=1);
$app = getenv('APP') ?: __DIR__ . '/../../app';
require $app . '/lib/Landmarks.php';
$ok = 0; $bad = 0; $t = function ($n, $g, $w) use (&$ok, &$bad) { if ($g === $w) $ok++; else { $bad++; echo "FAIL $n\n  got:  " . var_export($g, true) . "\n  want: " . var_export($w, true) . "\n"; } };
$ph = fn ($id, $title, $lat, $lon, $w = 1200) => ['id' => $id, 'title' => $title, 'lat' => $lat, 'lon' => $lon, 'width' => $w, 'licence' => 'CC BY-SA 4.0', 'rights' => '',
    'full' => 'https://upload.wikimedia.org/x/' . $id, 'about' => '', 'tags' => '', 'author' => 'Tester', 'source_url' => 'https://commons.wikimedia.org/wiki/File:' . $id];
$wm = new class { public array $near = []; public array $named = []; public function nearby($la, $lo, $r = 1500, $l = 40): array { return $this->near; } public function named($q, $l = 20): array { return $this->named; } };
$park = ['name' => 'Lyman Lake State Park', 'lat' => 34.3617, 'lon' => -109.3746, 'state' => 'AZ', 'county' => 'Apache', 'near_town' => 'Saint Johns'];
$best = fn () => ($p = Landmarks::bestPhoto($wm, $park)) ? $p['id'] : null;

$wm->near = [$ph('Lyman_Lake_Arizona.jpg', 'Lyman Lake, Arizona', 34.365, -109.372)];   // ~0.3 mi away, no "State Park"
$t('on the spot: "Lyman Lake" counts for Lyman Lake State Park', $best(), 'Lyman_Lake_Arizona.jpg');
$wm->near = [$ph('Lyman_Lake_far.jpg', 'Lyman Lake', 34.40, -109.37)];   // ~2.7 mi: not on the spot
$t('farther than 1.5 km: every word still needed', $best(), null);
$wm->near = [$ph('Lyman_reservoir.jpg', 'Lyman reservoir', 34.365, -109.372)];
$t('the place\'s own words stay required ("lake")', $best(), null);
$wm->near = [$ph('Lyman_Lake_SP_tiny.jpg', 'Lyman Lake', 34.365, -109.372, 300)];
$t('still at least 640 px', $best(), null);
$wm->near = []; $wm->named = [$ph('Lyman_Lake_noloc.jpg', 'Lyman Lake', null, null)];
$t('no location: the whole name still needed', $best(), null);
$wm->named = [$ph('Lyman_Lake_State_Park_noloc.jpg', 'Lyman Lake State Park, Saint Johns, Arizona', null, null)];
$t('no location, whole name and its town: as before', $best(), 'Lyman_Lake_State_Park_noloc.jpg');
$wm->named = [];
$vc = ['name' => 'Painted Desert Visitor Center', 'lat' => 35.0656, 'lon' => -109.7826, 'state' => 'AZ', 'county' => '', 'near_town' => ''];
$wm->near = [$ph('KU_Visitor_Center.jpg', 'KU Visitor Center', 38.9586, -95.2475)];
$t('Kansas stays out (1,000 miles away)', ($p = Landmarks::bestPhoto($wm, $vc)) ? $p['id'] : null, null);
$wm->near = [$ph('Painted_Desert_from_the_rim.jpg', 'Painted Desert from the rim', 35.066, -109.783)];
$t('on the spot: "Painted Desert" counts for Painted Desert Visitor Center', ($p = Landmarks::bestPhoto($wm, $vc)) ? $p['id'] : null, 'Painted_Desert_from_the_rim.jpg');
$only = ['name' => 'Visitor Center', 'lat' => 35.0656, 'lon' => -109.7826, 'state' => 'AZ', 'county' => '', 'near_town' => ''];
$wm->near = [$ph('Parking_lot.jpg', 'Parking lot', 35.066, -109.783)];
$t('a name of kind words only still needs them all', ($p = Landmarks::bestPhoto($wm, $only)) ? $p['id'] : null, null);
$wm->near = [$ph('Lyman_Lake_State_Park.jpg', 'Lyman Lake State Park', 34.365, -109.372), $ph('Lyman_Lake.jpg', 'Lyman Lake', 34.3618, -109.3747)];
$t('the nearest of those that fit wins', $best(), 'Lyman_Lake.jpg');
echo "$ok passed, $bad failed\n";
