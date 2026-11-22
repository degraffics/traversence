<?php
// harness only (never shipped): the shared full-screen map opened with three pins and a 5-mile ring
require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../app/ui/page-shell.php';
tv_page_start('Map test', ['no_crumbs' => true]);
?>
<p>Map test</p>
<script>
window.addEventListener('load', function () { TvMap.open({ panel: true, select: 10, title: 'Things to do · Pinetop', radius: 5,
  load: function () { return Promise.resolve({ pins: [{id: 10, lat: 34.123, lon: -109.96, name: 'Pine Cafe & Mercantile', category: 'Cafe', city: 'Pinetop', state: 'AZ'},
    {id: 11, lat: 34.19, lon: -110.0, name: 'Show Low Lake', category: 'Lake', city: 'Show Low', state: 'AZ'}, {id: 12, lat: 34.15, lon: -110.02, name: 'Woodland Lake Park', category: 'Park', city: 'Pinetop', state: 'AZ'}],
    center: {lat: 34.14, lon: -109.98, radius: 5}, total: 3 }); } }); });
</script>
<?php tv_page_end();
