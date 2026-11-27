<?php
// C.1 (the launch plan; decisions/0181): the device's place inside our areas is that area's town, outside stays outside. Run: php device-place.php <root> on the harness (handoffs/design-harness), with micro_clusters, geo_hubs, hubs and zip_coordinates seeded for Show Low (cluster 5, ZIPs 85901 85902 85935), Pinetop 85935 and Seattle 98101.
$root = $argv[1]; require $root . '/app/bootstrap.php'; require_once $root . '/app/lib/UniversalSearch.php';
$pdo = Database::connection(); $ok = 0; $bad = 0;
$t = function ($name, $ctx, $want) use ($pdo, &$ok, &$bad) {
  $p = UniversalSearch::scopePlace($pdo, $ctx);
  $got = $p === null ? 'null' : (empty($p['outside']) ? 'inside:' . ($p['level'] ?? '') . ':' . ($p['slug'] ?? '') . ':' . ($p['label'] ?? '') : 'outside:' . ($p['label'] ?? ''));
  $pass = $got === $want; $pass ? $ok++ : $bad++; echo ($pass ? 'PASS ' : 'FAIL ') . "$name => $got" . ($pass ? '' : "  (want $want)") . "\n";
};
$t('city Show Low', ['city' => 'Show Low', 'state' => 'AZ'], 'inside:cluster:show-low:Show Low, AZ');
$t('zip 85901', ['zip' => '85901'], 'inside:cluster:show-low:Show Low, AZ');
$t('associated zip Pinetop', ['zip' => '85935'], 'inside:cluster:show-low:Pinetop, AZ');
$t('point near Show Low', ['lat' => 34.25, 'lon' => -110.03], 'inside:cluster:show-low:Near you');
$t('Seattle stays outside', ['city' => 'Seattle', 'state' => 'WA'], 'outside:Seattle, WA');
$t('point in the sea stays outside', ['lat' => 30.0, 'lon' => -140.0], 'outside:Near you');
$t('unknown slug falls to the device place', ['level' => 'cluster', 'slug' => 'nope', 'city' => 'Show Low', 'state' => 'AZ'], 'inside:cluster:show-low:Show Low, AZ');
$p = UniversalSearch::scopePlace($pdo, ['zip' => '85901']); echo 'shape: ' . json_encode(array_intersect_key($p, array_flip(['label','sub','key','level','slug','zips','town','area','city','state','outside','lat','lon']))) . "\n";
echo "$ok passed, $bad failed\n";
