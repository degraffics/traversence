<?php
// tools/design-tests/hero-tidy-seed.php <root>: for hero-tidy.cjs (harness only): geo hub 1 gets the slug show-low-geo and
// three areas all named "Twin Town" (ids 990041-3), so the Communities list has a name to show once.
declare(strict_types=1);
require_once ($argv[1] ?? '') . '/app/bootstrap.php';
$pdo = Database::connection();
$pdo->exec("UPDATE geo_hubs SET slug = 'show-low-geo' WHERE id = 1");
$pdo->exec('DELETE FROM micro_clusters WHERE id BETWEEN 990041 AND 990043');
foreach ([990041, 990042, 990043] as $i => $id) $pdo->prepare("INSERT INTO micro_clusters (id, slug, name, geo_hub_id, status, primary_zip) VALUES (?, ?, 'Twin Town', 1, 'active', '85901')")->execute([$id, 'twin-town-' . $i]);
try { $pdo->exec("DELETE FROM page_cache WHERE k LIKE 'guides:%'"); } catch (Throwable $e) {}
echo "seeded\n";
