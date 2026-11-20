<?php
// tools/design-tests/send-to-maps/page.php: a test page for send-to-maps.cjs (decisions/0174 §4). Copied into the
// harness's website/test/maps.php; never shipped. ?n= stops on one suggested journey (default 12), drawn by the live
// tv_sj_card(), plus a "Make a day of it" link as landmark.php draws it.
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/ui/suggested-journeys.php';
$n = max(1, min(40, (int) ($_GET['n'] ?? 12)));
$stops = [];
for ($i = 0; $i < $n; $i++) {
    $stops[] = ['ref' => 'entity:' . (100 + $i), 'name' => 'Stop ' . ($i + 1), 'sub' => 'Test', 'url' => '/listing/view.php?id=' . (100 + $i),
        'lat' => 34.2 + $i * 0.011, 'lon' => -110.03 - $i * 0.013, 'address' => '', 'hours' => 'open', 'arrive' => 540 + $i * 45, 'stay' => 30,
        'leg' => $i ? ['minutes' => 8, 'miles' => 3.1, 'mode' => 'drive', 'source' => 'line'] : null];
}
$j = ['id' => 1, 'title' => 'A test day', 'why' => '', 'stops' => $stops, 'minutes' => $n * 45, 'miles' => $n * 3.1, 'sunset' => null];
tv_page_start('Send to Maps test');
echo '<div class="sj-list" style="margin-top:.6rem">' . tv_sj_card($j, true) . '</div>';
$day = ['stops' => [['label' => 'Lyman Lake', 'lat' => 34.36, 'lon' => -109.37], ['label' => 'Rattlesnake Point Pueblo', 'lat' => 34.35, 'lon' => -109.36], ['label' => 'A cafe', 'lat' => 34.3, 'lon' => -109.4]]];
require_once __DIR__ . '/../../app/ui/maps-send.php';
echo '<p id="day">' . tv_maps_script() . '<a ' . tv_maps_attrs($day['stops'], 'Lyman Lake: a day out') . ' aria-haspopup="dialog">Send to Maps</a></p>';
tv_page_end();
