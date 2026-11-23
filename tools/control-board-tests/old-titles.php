<?php
// Alarms raised before #258 keep the words they were raised with: a held trip's raiser doesn't fire again (the session
// manager, 2026-11-23: "words follow the state applies to old alarms too"). Seeded with the live alarms snapshot
// (site-audit/results/alarms.json, its old titles), ControlAlarms::redrawTitles (the board's watch) redraws each from its
// state: a service's issue by ServiceHealth::alarmTitle, a job by its plain name, a task by its component. Its Pulse thread
// takes the same title in place; nothing is sent, nothing made unread; a second pass changes nothing; the consistency check
// (ControlConsistency, the health report's) finds no raw name on any surface.
// Run: mariadb < ../release-b-tests/start-schema.sql && php old-titles.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/ControlConsistency.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n — " . mb_substr(is_string($got) ? $got : (string) json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) . "\n"; } };

$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, last_probe TEXT NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence) VALUES (41, 'commoncrawl', 'index.commoncrawl.org', 'crawler', 'server', 'open', 3, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{}')");
$snap = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/site-audit/results/alarms.json'), true);
foreach ($snap['open'] as $a) ControlAlarms::raise($pdo, $a['key'], $a['process'], $a['level'], $a['level'] === 'trip' ? 'crash' : 'retry', $a['title'], (string) ($a['means'] ?? ''), 'x', (string) ($a['reading'] ?? ''), [], false, false);
// the old-worded alarms as the snapshot of 2026-11-23 04:00 UTC had them (since rewritten live), seeded here, not read from the moving snapshot
foreach ([['jobslow:guide_finds_tick', 'job:guide_finds_tick', 'warning', 'slow', 'The job "guide_finds_tick" is slow', '64 s (usual 4)', ''],
          ['job:identity_recheck_tick:stuck', 'job:identity_recheck_tick', 'warning', 'crash', 'The job "identity_recheck_tick" never finished', 'started 2026-11-21 06:13:02', ''],
          ['task:failed:overture_bulk', 'wf:W1', 'trip', 'crash', 'W1: its task overture_bulk failed (run #1290)', "TimeoutError('The read operation timed out')", 'ep:crawl/loads']] as [$k, $pr, $lv, $kd, $ti, $rd, $cp])
    ControlAlarms::raise($pdo, $k, $pr, $lv, $kd, $ti, '', '', $rd, [], false, false, $cp);
$pdo->exec("UPDATE control_alarms SET title = 'The job \"identity_recheck_tick\" never finished' WHERE akey = 'job:identity_recheck_tick:stuck'");
$pdo->exec("UPDATE control_alarms SET tripped_at = UTC_TIMESTAMP() WHERE level = 'trip'");
ControlAlarms::raise($pdo, 'svc:issue:41', 'svc:commoncrawl', 'trip', 'crash', 'index.commoncrawl.org: Its server failed (the crawler, on Railway)', '', '', '504 Gateway Time-out', [], false, false);
$pdo->exec("UPDATE control_alarms SET title = 'index.commoncrawl.org: Its server failed (the crawler, on Railway)', level = 'trip', tripped_at = UTC_TIMESTAMP() WHERE akey = 'svc:issue:41'");
$id41 = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'svc:issue:41'")->fetchColumn();
// its Pulse thread, as the trip's email left it (old title), read
$pdo->exec("CREATE TABLE IF NOT EXISTS comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL)");
$pdo->prepare("INSERT INTO comms_messages (kind, lvl, to_user, thing, title, created_at, updated_at, read_at) VALUES ('alarm.trip', 'critical', 7, ?, 'SYSTEM ALARM NOTICE: index.commoncrawl.org: Its server failed (the crawler, on Railway): Paused by the system', NOW(), NOW(), NOW())")->execute(['alarm:' . $id41]);
$msgs0 = (int) $pdo->query('SELECT COUNT(*) FROM comms_messages')->fetchColumn();
$title = fn (string $k) => (string) $pdo->query('SELECT title FROM control_alarms WHERE akey = ' . $pdo->quote($k) . ' AND cleared_at IS NULL')->fetchColumn();
$before = array_column($pdo->query('SELECT akey, title FROM control_alarms WHERE cleared_at IS NULL')->fetchAll(PDO::FETCH_ASSOC), 'title', 'akey');

$n = ControlAlarms::redrawTitles($pdo);
$t('titles redrawn from their state (' . $n . ')', $n >= 4, $n);
$t('a service\'s issue: the part\'s name and what happened, never a host or a caller (02 §4.8 row 26)', $title('svc:issue:41') === ServiceHealth::alarmTitle('commoncrawl', 'index.commoncrawl.org', 'server') && !str_contains($title('svc:issue:41'), 'index.commoncrawl.org') && !str_contains($title('svc:issue:41'), 'crawler'), $title('svc:issue:41'));
$t('a slow job by its plain name (02 §5.4)', $title('jobslow:guide_finds_tick') === ControlAlarms::jobPlain('guide_finds_tick') . ' is slow', $title('jobslow:guide_finds_tick'));
$t('a stuck job by its plain name', $title('job:identity_recheck_tick:stuck') === ControlAlarms::jobPlain('identity_recheck_tick') . ': its run never finished', $title('job:identity_recheck_tick:stuck'));
// as CrawlService::report words it today: our endpoint's path when its part is ours ("/api/crawl/loads is slow"), else the task's own code with its run
$tk = $pdo->query("SELECT component, reading FROM control_alarms WHERE akey = 'task:failed:overture_bulk'")->fetch(PDO::FETCH_ASSOC);
$want = str_starts_with((string) $tk['component'], 'ep:') ? '/api/' . substr((string) $tk['component'], 3) . (preg_match('/timed? ?out|timeout/i', (string) $tk['reading']) ? ' is slow' : ' keeps failing') : 'overture_bulk failed in its own code (run #1290)';
$t('a task\'s failure as its raiser words it today (' . $want . ')', $title('task:failed:overture_bulk') === $want && !str_starts_with($title('task:failed:overture_bulk'), 'W1: its task'), $title('task:failed:overture_bulk'));
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, means, todo, reading, fix, component, first_at, last_at, tripped_at) SELECT 'task:failed:own_code', proc, level, kind, 'W1: its task own_code failed (run #1301)', means, todo, 'KeyError: name', fix, 'task:own_code', first_at, last_at, tripped_at FROM control_alarms WHERE akey = 'task:failed:overture_bulk'");
ControlAlarms::redrawTitles($pdo);
$t('…and one failing in its own code keeps its run', $title('task:failed:own_code') === 'own_code failed in its own code (run #1301)', $title('task:failed:own_code'));
$same = array_filter(array_keys($before), fn ($k) => !preg_match('/^(svc:issue:|jobslow:|joblate:|job:|task:failed:|pf:svc:loc$)/', $k));   // the rest of the snapshot, already in today's words (pf:svc:loc: its raw timeout title redrawn, 2026-11-23)
$t('the rest, already in today\'s words, unchanged', !array_filter($same, fn ($k) => $title($k) !== $before[$k]), array_map(fn ($k) => [$k, $title($k)], $same));
$m = $pdo->query("SELECT title, read_at FROM comms_messages WHERE thing = 'alarm:$id41'")->fetch(PDO::FETCH_ASSOC);
$a41 = ControlAlarms::byId($pdo, $id41);
$t('its Pulse thread takes today\'s notice title, in place, still read', $m['title'] === ControlAlarms::noticeTitle($pdo, $a41) && !str_contains((string) $m['title'], 'index.commoncrawl.org') && $m['read_at'] !== null, $m);
$t('nothing sent', (int) $pdo->query('SELECT COUNT(*) FROM comms_messages')->fetchColumn() === $msgs0);
// a check's raw transport error as a title (the Design reviewer, 2026-11-23): "Library of Congress: No answer: Operation timed
// out after 15004 milliseconds…" reads "Library of Congress isn't answering", the timeout kept in its reading
$loc = ControlAlarms::openOf($pdo, 'pf:svc:loc');
$t('a check that got no answer reads as the service\'s words, its timeout in the reading', $loc && $loc['title'] === 'Library of Congress isn\'t answering' && str_contains((string) $loc['reading'], 'timed out'), $loc ? [$loc['title'], $loc['reading']] : null);
$t('a second pass changes nothing', ControlAlarms::redrawTitles($pdo) === 0);
$c = ControlConsistency::check($pdo);
$names = array_values(array_filter($c['mismatches'], fn ($x) => in_array($x['check'], ['names', 'title'], true)));
$t('the consistency check: no raw name and the same title on the board, in Pulse and on the card', !$names, array_slice($names, 0, 3));
$jobRaw = array_filter($pdo->query('SELECT title FROM control_alarms WHERE cleared_at IS NULL')->fetchAll(PDO::FETCH_COLUMN), fn ($x) => preg_match('/The job "/', (string) $x));
$t('no title quotes a job\'s id ("The job \\"x\\"")', !$jobRaw, array_values($jobRaw));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
