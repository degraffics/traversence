<?php
// The outside heartbeat's records imported when the site is back (02 §0a ruling 6; ControlIncidents::importHeartbeat):
// a closed record becomes a trip row with its real times, closed as Recovered, and its IR (source heartbeat); once only;
// a record still open waits; nothing is sent.
// Run: php heartbeat-import.php <root>/app   (any lv_main; it makes its own tables)
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlIncidents.php'; require_once $app . '/lib/ControlAlarms.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got), 0, 400) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); $pdo->exec('DROP TABLE IF EXISTS control_incidents');
$b = json_encode(['records' => [
    ['id' => 'hb-20261123T0100Z-page', 'part' => 'page', 'opened_at' => '2026-11-23T01:00:00Z', 'closed_at' => '2026-11-23T01:40:00Z', 'what' => 'Page load: the outside heartbeat got 503', 'checks' => [['at' => '2026-11-23T01:00:00Z', 'url' => 'https://x/', 'status' => 503]]],
    ['id' => 'hb-20261123T0200Z-cron', 'part' => 'cron', 'opened_at' => '2026-11-23T02:00:00Z', 'closed_at' => null],
    ['id' => 'bad', 'part' => 'nonsense', 'opened_at' => '2026-11-23T02:00:00Z', 'closed_at' => '2026-11-23T02:10:00Z'],
]]);
$t('one closed record imported; the open one waits; an unknown part is skipped', ControlIncidents::importHeartbeat($pdo, $b) === 1);
$t('importing again adds nothing', ControlIncidents::importHeartbeat($pdo, $b) === 0);
$a = $pdo->query('SELECT * FROM control_alarms')->fetch(PDO::FETCH_ASSOC);
$t('a trip with its real times', $a['level'] === 'trip' && $a['first_at'] === '2026-11-23 01:00:00' && $a['cleared_at'] === '2026-11-23 01:40:00', $a);
$t('closed as Recovered with its minutes', str_starts_with((string) $a['cleared_how'], 'Recovered: Page load was down 40 min'), $a['cleared_how']);
$t('nothing sent: no mailed_at', $a['mailed_at'] === null);
$i = $pdo->query('SELECT * FROM control_incidents')->fetch(PDO::FETCH_ASSOC);
$t('its IR, source heartbeat, linked to the trip', $i['source'] === 'heartbeat' && (int) $a['incident_id'] === (int) $i['id'] && str_contains($i['title'], 'raised while the site was down, by the outside heartbeat'), $i);
$t('a file that isn\'t the records imports nothing', ControlIncidents::importHeartbeat($pdo, '<html>') === 0);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
