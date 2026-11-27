<?php
// tools/e1-tests/st-johns-renew.php: E.1's St. Johns renew on a real MariaDB (php st-johns-renew.php <migration.sql>), run
// twice the way DbUpdates::autoRun does. place_guides with live's columns (live read, 2026-11-27).
declare(strict_types=1);
$sql = (string) file_get_contents($argv[1] ?? '');
$pdo = new PDO('mysql:host=localhost;dbname=ts4;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP TABLE IF EXISTS place_guides');
$pdo->exec("CREATE TABLE place_guides (place_key VARCHAR(40) PRIMARY KEY, sections MEDIUMTEXT, elevation_ft INT, status VARCHAR(8) NOT NULL, published_at DATETIME, linked_at DATETIME, updated_by INT UNSIGNED, updated_at DATETIME, draft_status VARCHAR(10) NOT NULL, draft_at DATETIME, draft_info MEDIUMTEXT, field_notes MEDIUMTEXT)");
$di = fn (array $x) => json_encode($x + ['at' => '2026-11-02T00:01:24-07:00', 'confidence' => 55, 'hash' => ['glance' => 'a'], 'check' => ['x', 'y'], 'v' => 4]);
$ins = $pdo->prepare("INSERT INTO place_guides (place_key, sections, status, draft_status, draft_info) VALUES (?, '{}', ?, ?, ?)");
$ins->execute(['cluster:1', 'draft', 'done', $di([])]);
$ins->execute(['cluster:2', 'draft', 'done', $di([])]);
$ok = 0; $bad = 0; $t = function (string $w, bool $p) use (&$ok, &$bad) { $p ? $ok++ : $bad++; echo ($p ? 'ok   ' : 'FAIL ') . "$w\n"; };
$run = function () use ($pdo, $sql) { foreach (array_filter(array_map('trim', explode(';', $sql))) as $s) { $s = trim(preg_replace('/^--.*$/m', '', $s)); if ($s !== '') $pdo->exec($s); } };
$get = fn (string $k) => json_decode((string) $pdo->query("SELECT draft_info FROM place_guides WHERE place_key = " . $pdo->quote($k))->fetchColumn(), true);
$run(); $run();
$d = $get('cluster:1');
$t('St. Johns waits to be written again (renew set, once)', is_array($d['renew'] ?? null) && count($d['renew']) === 1 && str_contains($d['renew'][0], 'gate 3'));
$t('the rest of its draft_info kept (confidence, hash, checks)', $d['confidence'] === 55 && $d['hash'] === ['glance' => 'a'] && $d['check'] === ['x', 'y']);
$raw = (string) $pdo->query("SELECT draft_info FROM place_guides WHERE place_key = 'cluster:1'")->fetchColumn();
$t('written compact, as the guide job finds it (LIKE \'%"renew":[%\' and "v":4 kept)', str_contains($raw, '"renew":[') && str_contains($raw, '"v":4') && json_decode($raw) !== null);
$t('no other guide touched', !isset($get('cluster:2')['renew']));
// a kept draft, a live guide and one already waiting are left alone
foreach ([['live', 'done', []], ['draft', 'done', ['kept' => true]], ['draft', 'done', ['renew' => ['older reason']]]] as $i => [$st, $ds, $x]) {
    $pdo->exec("DELETE FROM place_guides WHERE place_key = 'cluster:1'"); $ins->execute(['cluster:1', $st, $ds, $di($x)]); $run();
    $d = $get('cluster:1');
    $t(['a live guide', 'a guide a person kept', 'one already waiting'][$i] . ' is left as it was', $i === 2 ? $d['renew'] === ['older reason'] : !isset($d['renew']));
}
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
