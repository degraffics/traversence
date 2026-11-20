<?php
// control-board-routes (2026-11-19, 0171 §5's note): a critical part, by the duty it carries ("Critical is that our site
// can perform its duties. Search, page load, payment processing, database connections, system critical processing or
// procedures"), trips on its first failure while the system keeps trying; the system fixing it closes the trip as
// Recovered (a notice, nothing to press, the bell left alone); its tries running out is the Escalation Notice, an ordinary
// trip only Resolve clears. Any other part keeps 3c: a warning while the system tries.
// Run: mariadb < ../release-b-tests/start-schema.sql && php critical.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlRegister.php'; require_once $app . '/lib/ControlLog.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo); $pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
require_once $app . '/lib/SearchIndex.php'; try { SearchIndex::setState($pdo, 'control:duties', '[]'); } catch (Throwable $x) {}

// the register: duties by default, changed and put back
$t('payments, page load, databases and procedures are critical by default', ControlRegister::duty($pdo, 'svc:quickbooks') === 'payments' && ControlRegister::duty($pdo, 'svc:bluehost') === 'page' && ControlRegister::duty($pdo, 'site:db') === 'database' && ControlRegister::duty($pdo, 'svc:github') === 'procedures');
$t('the crawler, W0, the video worker and Railway are not critical by themselves', ControlRegister::duty($pdo, 'wk:crawler') === '' && ControlRegister::duty($pdo, 'wk:w0-indexer') === '' && ControlRegister::duty($pdo, 'wk:video-worker') === '' && ControlRegister::duty($pdo, 'svc:railway') === '');
ControlRegister::setDuty($pdo, 'svc:osm', 'search', 'Jason (staff)');
$t('a part can be given a duty (Thresholds & gates)', ControlRegister::duty($pdo, 'svc:osm') === 'search');
ControlRegister::setDuty($pdo, 'svc:osm', '', 'Jason (staff)');
$t('…and taken off again', ControlRegister::duty($pdo, 'svc:osm') === '');
$bad = false; try { ControlRegister::setDuty($pdo, 'svc:osm', 'parties', 'x'); } catch (InvalidArgumentException $x) { $bad = true; }
$t('only the five duties', $bad);
$log = (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Critical: Search%' OR what LIKE 'Not critical now%'")->fetchColumn();
$t('each change is in the logbook with who', $log === 2, $log);

// a non-critical part: 3c unchanged
$id = ControlAlarms::raise($pdo, 'x:osm', 'svc:osm', 'trip', 'crash', 'OSM failed');
$a = ControlAlarms::byId($pdo, $id);
$t('a part that isn\'t critical: a warning while the system tries (3c)', $a['level'] === 'warning' && empty($a['tripped_at']), $a['level']);

// a critical part's first failure
$id = ControlAlarms::raise($pdo, 'x:qb', 'svc:quickbooks', 'trip', 'crash', 'QuickBooks failed', 'Payments fail.', 'Check its key.');
$a = ControlAlarms::byId($pdo, $id);
$t('a critical part\'s first failure: a trip at once', $a['level'] === 'trip' && !empty($a['tripped_at']), $a);
$t('…still retrying, nothing stopped', !empty($a['retrying_at']) && empty($a['stopped']) && str_contains((string) $a['means'], 'the system keeps trying'));
$t('…its words name the duty', str_contains((string) $a['means'], 'Payments'));
$id2 = ControlAlarms::raise($pdo, 'x:aws', 'svc:aws', 'warning', 'retry', 'SES retrying');
$t('a critical part\'s retry warning is a trip too', ControlAlarms::byId($pdo, $id2)['level'] === 'trip');

// the system fixes it: Recovered
$ok = ControlAlarms::clear($pdo, 'x:qb', 'It answered at 14:02 UTC.');
$a = ControlAlarms::byId($pdo, $id);
$t('the system fixes it: the trip closes itself', $ok && $a['cleared_at'] !== null, $a['cleared_how']);
$t('…as "Recovered: <part> was down <n> min, the system fixed it."', str_starts_with((string) $a['cleared_how'], 'Recovered: QuickBooks (Intuit) was down ') && str_contains((string) $a['cleared_how'], 'the system fixed it'), $a['cleared_how']);
$t('…nothing to press: it doesn\'t hold the bell', $a['ack_at'] !== null && ControlAlarms::counts($pdo)['unseen'] === 0);
$t('…and it\'s in the logbook', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Cleared: QuickBooks failed%'")->fetchColumn() === 1);

// its tries run out: the Escalation Notice
$id = ControlAlarms::raise($pdo, 'x:gh', 'svc:github', 'trip', 'crash', 'GitHub failed');
[$max] = ControlAlarms::limits($pdo, 'crash');
for ($i = 1; $i < $max; $i++) ControlAlarms::raise($pdo, 'x:gh', 'svc:github', 'trip', 'crash', 'GitHub failed');
$a = ControlAlarms::byId($pdo, $id);
$t('its tries run out: no longer retrying, an ordinary trip', $a['level'] === 'trip' && empty($a['retrying_at']) && !empty($a['tripped_at']), $a);
$t('…the Escalation Notice in the logbook', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Escalation Notice: retries unsuccessful, now admin required: GitHub, System-critical procedures%'")->fetchColumn() === 1);
$ok = ControlAlarms::clear($pdo, 'x:gh', 'It answered.');
$t('…and once its check passes, it closes as Recovered (Jason, 2026-11-20: "Close as Recovered")', $ok && str_starts_with((string) ControlAlarms::byId($pdo, $id)['cleared_how'], 'Recovered:'));

// only trips reach a person
$pl = (string) file_get_contents($app . '/lib/Pulse.php');
$t('Pulse lists open trips only', str_contains($pl, "\$al['level'] !== 'trip') continue;"));
require_once $app . '/lib/Notify.php';
$t('the dispatcher knows Recovered (a notice) and the Escalation Notice (critical)', (Notify::KINDS['alarm.recovered'][2] ?? '') === 'notice' && (Notify::KINDS['alarm.escalated'][2] ?? '') === 'critical');

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
