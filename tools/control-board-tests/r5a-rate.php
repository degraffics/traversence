<?php
// The Control Board's 5a, a 429 (the session manager, 2026-11-19, principles 1 and 2): "asked us to slow down" is a request
// to go slower, never a stop. It stays a warning however many times it comes, backs off twice as long as other kinds
// (20 minutes, doubling; Retry-After obeyed), and clears itself; a trip raised for one before 5a is made a warning by the
// watch, its calls back on their back-off and the change logged. Timeouts still trip at their limit.
// Run: mariadb < ../release-b-tests/start-schema.sql && php r5a-rate.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/Preflight.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
$pdo->exec('DROP TABLE IF EXISTS service_issues');
$pdo->exec("CREATE TABLE service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL, last_probe TEXT NULL,
    resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$add = function (string $kind, int $hits, int $ageH) use ($pdo) { $pdo->prepare("INSERT INTO service_issues (service, host, caller, kind, hits, first_at, last_at) VALUES ('wikimedia', 'query.wikidata.org', 'site', ?, ?, UTC_TIMESTAMP() - INTERVAL ? HOUR, UTC_TIMESTAMP())")->execute([$kind, $hits, $ageH]); return (int) $pdo->lastInsertId(); };
$ev = ['status' => 429, 'error' => '', 'body' => 'Too Many Requests'];
$mins = fn (?string $d) => $d === null ? null : (int) round((strtotime($d . ' UTC') - time()) / 60);

// 1. a 429 at its 9th time in a row, open 8 hours: still a warning, with Check it now, words saying it slows, never stops
$id = $add('rate', 9, 8);
ServiceHealth::alarm($pdo, $id, 'wikimedia', 'query.wikidata.org', 'rate', $ev, 'site');
$a = ControlAlarms::openOf($pdo, 'svc:issue:' . $id);
$t('a 429 past the back-off limit (9 in a row, 8 hours open) stays a warning, never a trip', $a && $a['level'] === 'warning' && empty($a['tripped_at']), [$a['level'] ?? null]);
$t('…its words: asked to slow down, twice the wait, never a stop', str_contains((string) $a['means'], 'asked us to slow down') && str_contains((string) $a['means'], 'never a stop'), $a['means'] ?? null);
$fx = json_decode((string) $a['fix'], true);
$t('…its Check it now is held by the system: greyed, with the reason and the next try\'s time, nothing to press', ($fx[1] ?? '') === 'svccheck' && str_starts_with((string) ($fx[3] ?? ''), '#wait:query.wikidata.org asked us to slow down; next try ') && str_contains((string) $fx[3], ', by itself. Nothing to press.'), $fx);
$hu = new ReflectionMethod('ServiceHealth', 'holdUntil'); $hu->setAccessible(true);
$f2 = ServiceHealth::rateFix('query.wikidata.org', gmdate('Y-m-d H:i:s', time() + 1200), 1);
$t('…the time is the wait\'s end, labelled UTC', str_contains($f2[3], 'next try at ' . gmdate('M j, g:i A', time() + 1200) . ' UTC'), $f2[3]);
$held = new ReflectionMethod('Preflight', 'held'); $held->setAccessible(true);
$pdo->prepare('UPDATE service_issues SET hold_until = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() + 1200), $id]);
Preflight::$now = true; $hh = $held->invoke(null, $pdo, 'query.wikidata.org'); Preflight::$now = false;
$t('…and even a press that reached the check would not go past a 429\'s wait', $hh !== null && str_contains($hh, 'Held by issue #' . $id), $hh);
$api = file_get_contents(dirname($app) . '/website/admin/api.php'); $cc = file_get_contents($app . '/admin/control-console.php');
$t('…the api refuses Check it now on a 429 with the same words; the board greys a #wait button and shows its reason as text beside it (a phone has no hover)', str_contains($api, "if (\$si && \$si['kind'] === 'rate') Response::error(") && str_contains($cc, "str_starts_with(\$f[3], '#wait:')") && str_contains($cc, "false, substr(\$f[3], 6)") && str_contains($cc, "'<span class=\"cb-wt\">' . \$e(substr(\$f[3], 6))"));

// 2. a timeout still trips when its tries run out (only 429 changes; the tries are the one engine's checks since the
// control board's build, P3)
$id2 = $add('timeout', 9, 8);
ServiceHealth::alarm($pdo, $id2, 'wikimedia', 'query.wikidata.org', 'timeout', ['status' => 0, 'error' => 'timed out', 'body' => ''], 'site');
require_once $app . '/lib/ControlEngine.php'; ControlEngine::$checker = fn (string $c) => ['pass' => false, 'proof' => 'no answer', 'ms' => 15000];
foreach (ControlEngine::TRY_AT as $_) { $pdo->exec("UPDATE control_alarms SET next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE akey = 'svc:issue:$id2' AND cleared_at IS NULL"); ControlAlarms::expireTries($pdo); }
ControlEngine::$checker = null;
$a2 = ControlAlarms::openOf($pdo, 'svc:issue:' . $id2);
$t('a timeout whose tries ran out still trips', $a2 && $a2['level'] === 'trip', [$a2['level'] ?? null]);
$pdo->prepare('UPDATE service_issues SET hold_until = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() + 1200), $id]);
$pdo->prepare("UPDATE service_issues SET state = 'resolved' WHERE id IN (?, ?)")->execute([$id, $id2]);
$id4 = $add('timeout', 2, 0); $pdo->prepare('UPDATE service_issues SET hold_until = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() + 1200), $id4]);
Preflight::$now = true; $h4 = $held->invoke(null, $pdo, 'query.wikidata.org'); Preflight::$now = false;
$t('a timeout\'s Check it now still goes past its wait (only timeouts and server errors)', $h4 === null, $h4);
$pdo->prepare("UPDATE service_issues SET state = 'resolved' WHERE id = ?")->execute([$id4]); $pdo->prepare("UPDATE service_issues SET state = 'open' WHERE id = ?")->execute([$id]);

// 3. the back-off: twice as long for a 429; Retry-After obeyed exactly
$t('a 429\'s first wait is 20 minutes (a timeout\'s is 10), so our pace to that host halves', $mins($hu->invoke(null, 'rate', 1)) === 20 && $mins($hu->invoke(null, 'timeout', 1)) === 10, [$mins($hu->invoke(null, 'rate', 1)), $mins($hu->invoke(null, 'timeout', 1))]);
$t('…doubling each time, at most the cap', $mins($hu->invoke(null, 'rate', 2)) === 40 && $mins($hu->invoke(null, 'rate', 20)) === 360);
$t('…a Retry-After the server sends is obeyed as it says', $mins($hu->invoke(null, 'rate', 3, '300')) === 5);

// 4. a 429 tripped before 5a (today's Wikidata trip): the watch makes it a warning, calls back on their back-off, logged
$id3 = $add('rate', 7, 10);
$pdo->prepare('UPDATE service_issues SET hold_until = ? WHERE id = ?')->execute(['2099-01-01 00:00:00', $id3]);
$pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, means, n_times, first_at, last_at, tripped_at, stopped) VALUES (?, 'svc:wikimedia', 'trip', 'retry', 'query.wikidata.org: asked us to slow down (the site)', 'Its back-off ran out (7 failures in a row): calls to query.wikidata.org are held until an admin resolves it.', 7, UTC_TIMESTAMP() - INTERVAL 10 HOUR, UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 4 HOUR, ?)")
    ->execute(['svc:issue:' . $id3, json_encode([['issue', (string) $id3, 'Calls to it']])]);
$n = ServiceHealth::liftRateTrips($pdo);
$a3 = ControlAlarms::openOf($pdo, 'svc:issue:' . $id3);
$hold = (string) $pdo->query('SELECT hold_until FROM service_issues WHERE id = ' . $id3)->fetchColumn();
$t('the watch makes a 429 trip a warning (nothing stopped, no trip time)', $n === 1 && $a3 && $a3['level'] === 'warning' && empty($a3['tripped_at']) && empty($a3['stopped']), [$n, $a3['level'] ?? null]);
$t('…its calls are back on their back-off, not held until 2099', $hold !== '' && strtotime($hold . ' UTC') < time() + (361) * 60, $hold);
$t('…and its Check it now is greyed with its next try', str_contains((string) $a3['fix'], '#wait:query.wikidata.org asked us to slow down; next try at '), $a3['fix'] ?? null);
$log = ControlLog::feed($pdo, 'svc:wikimedia');
$t('…and the logbook says so, by the system', (bool) array_filter($log, fn ($e) => str_contains($e[4], 'a rate limit is a warning, not a trip') && $e[5] === 'the system'));
$t('…once: a second watch finds nothing to lift, and the timeout\'s trip is left alone', ServiceHealth::liftRateTrips($pdo) === 0 && ControlAlarms::openOf($pdo, 'svc:issue:' . $id2)['level'] === 'trip');

// 5. it clears itself when the host answers
ServiceHealth::answered($pdo, 'query.wikidata.org', 'site');
$t('when the host answers, the 429 is resolved and its warning cleared by itself', (string) $pdo->query('SELECT state FROM service_issues WHERE id = ' . $id3)->fetchColumn() === 'resolved' || (string) $pdo->query('SELECT state FROM service_issues WHERE id = ' . $id)->fetchColumn() === 'resolved');
$src = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch runs it', str_contains($src, 'ServiceHealth::liftRateTrips($pdo)'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
