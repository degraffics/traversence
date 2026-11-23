<?php
// Jason's screenshot of the live board (2026-11-23), items 1 and 4, on MariaDB:
//   item 4: Google Cloud's cap alarm carries its reading in its title ("Google Cloud at 60%: 608 of 1,000 checks"), through
//           the real path (Services' count → Operations::alarms), and follows it as it climbs; one alarm, never a second;
//           its register entry is "Google Cloud in the register", its panel titled "Google Cloud";
//   item 1: the job lateness check (02 row 65) on seeded runs: a job silent for days stays late (14 days, not 2); a QUIET
//           job, a retired one and one taken out on purpose are never late; its times are in UTC, labelled; its button names
//           the job plainly; a job that starts again clears.
// (The cron line's own schedule is read through cPanel's API, which the bench hasn't: there the default schedule is used.)
// Run: mariadb < ../release-b-tests/start-schema.sql && php board-words.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Operations.php'; require_once $app . '/lib/Scheduler.php'; require_once $app . '/lib/SearchIndex.php'; require_once $app . '/lib/ControlConsistency.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n — " . mb_substr(is_string($got) ? $got : (string) json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 400) . "\n"; } };
$open = fn (string $k) => $pdo->query('SELECT * FROM control_alarms WHERE akey = ' . $pdo->quote($k) . ' AND cleared_at IS NULL')->fetch(PDO::FETCH_ASSOC) ?: null;

// ---- item 4 ----
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_costs (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME, service VARCHAR(30), wf VARCHAR(4), task VARCHAR(40), units INT, usd_micros BIGINT, ok TINYINT, run_id INT NULL, note VARCHAR(255))");
foreach (['places_month' => date('Y-m'), 'places_used' => '605', 'places_monthly' => '1000', 'places_seen' => '1'] as $k => $v) SearchIndex::setState($pdo, $k, $v);
Operations::alarms($pdo);
$g = $open('ops:cap:google');
$t('item 4: Google Cloud\'s cap alarm carries its reading: "Google Cloud at 60%: 605 of 1,000 checks"', $g && $g['title'] === 'Google Cloud at 60%: 605 of 1,000 checks', $g['title'] ?? null);
$fx = $g ? json_decode((string) $g['fix'], true) : [];
$reg = array_values(array_filter((array) $fx, fn ($f) => ($f[1] ?? '') === 'peek'));
$t('…its register entry: "Google Cloud in the register", its panel titled "Google Cloud" (item 5)', $reg && $reg[0][0] === 'Google Cloud in the register' && ($reg[0][3] ?? '') === 'Google Cloud', $reg);
SearchIndex::setState($pdo, 'places_used', '608'); Operations::alarms($pdo);
$g2 = $open('ops:cap:google');
$t('…as it climbs, the same alarm takes the new figure (608, 60%), never a second', $g2 && (int) $g2['id'] === (int) $g['id'] && $g2['title'] === 'Google Cloud at 60%: 608 of 1,000 checks' && (int) $pdo->query("SELECT COUNT(*) FROM control_alarms WHERE akey = 'ops:cap:google'")->fetchColumn() === 1, $g2['title'] ?? null);
SearchIndex::setState($pdo, 'places_used', '761'); Operations::alarms($pdo);
$g3 = $open('ops:cap:google');
$t('…past the next band: "at 76%: 761 of 1,000 checks", still a warning', $g3 && $g3['title'] === 'Google Cloud at 76%: 761 of 1,000 checks' && $g3['level'] === 'warning', [$g3['title'] ?? null, $g3['level'] ?? null]);
$t('…the board\'s watch leaves it as it is (its raiser keeps it current)', ControlAlarms::redrawTitles($pdo) === 0 || $open('ops:cap:google')['title'] === $g3['title']);
SearchIndex::setState($pdo, 'places_used', '120'); Operations::alarms($pdo);
$t('…under 50% it clears', $open('ops:cap:google') === null);

// ---- item 1 ----
Scheduler::ensure($pdo);
$jr = Database::t('job_runs'); $js = Database::t('job_setup');
$run = fn (string $job, string $ago, bool $done = true) => $pdo->exec("INSERT INTO $jr (job, started_at, finished_at, ok, note) VALUES (" . $pdo->quote($job) . ", NOW() - INTERVAL $ago, " . ($done ? "NOW() - INTERVAL $ago" : 'NULL') . ", " . ($done ? 1 : 'NULL') . ", 'done')");
$run('sales_tick', '36 HOUR');            // hourly, silent for a day and a half (live: Lead scores since Nov 22)
$run('work_orders_tick', '5 DAY');        // hourly, silent for 5 days: dropped out of the old 2-day window
$run('guide_ondemand_tick', '2 MINUTE');  // 5min, on time
$run('mail_sync', '25 MINUTE');           // 5min, late
$run('releases_tick', '3 DAY');           // QUIET: logs only when it has work
$run('staging_tick', '3 DAY');            // RETIRED
$run('like_watch_tick', '4 HOUR');        // hourly, but taken out on purpose
$pdo->exec("REPLACE INTO $js (job, state, at) VALUES ('like_watch_tick', 'removed', NOW())");
ControlAlarms::tick($pdo);
$late = array_column($pdo->query("SELECT akey, title, reading, fix FROM control_alarms WHERE akey LIKE 'joblate:%' AND cleared_at IS NULL")->fetchAll(PDO::FETCH_ASSOC), null, 'akey');
$t('item 1: Lead scores, silent 36 hours, is late', isset($late['joblate:sales_tick']) && str_starts_with($late['joblate:sales_tick']['title'], 'Lead scores (job) is late: it was due at '), array_keys($late));
$t('…a job silent 5 days is still late (14 days of runs, not 2)', isset($late['joblate:work_orders_tick']), array_keys($late));
$t('…a 5-minute job 25 minutes quiet is late; one on time isn\'t', isset($late['joblate:mail_sync']) && !isset($late['joblate:guide_ondemand_tick']), array_keys($late));
$t('…never a QUIET job, a retired one, or one taken out on purpose', !isset($late['joblate:releases_tick']) && !isset($late['joblate:staging_tick']) && !isset($late['joblate:like_watch_tick']), array_keys($late));
$r = (string) ($late['joblate:sales_tick']['reading'] ?? '');
$t('…its reading is in UTC, labelled ("last started Nov 21, 22:17 UTC"), never the server\'s own clock unlabelled', (bool) preg_match('/^last started [A-Z][a-z]{2} \d{1,2}, \d{2}:\d{2} UTC$/', $r) && $r === 'last started ' . gmdate('M j, H:i', (int) strtotime((string) $pdo->query("SELECT MAX(started_at) FROM $jr WHERE job = 'sales_tick'")->fetchColumn())) . ' UTC', $r);
$f = json_decode((string) ($late['joblate:sales_tick']['fix'] ?? '[]'), true);
$t('…its button names the job plainly ("Its job: Lead scores (job)"), its panel titled by it', ($f[0][0] ?? '') === 'Its job: ' . ControlAlarms::jobName('sales_tick') && ($f[0][3] ?? '') === ControlAlarms::jobName('sales_tick') && !str_contains((string) $f[0][0], 'sales_tick'), $f);
$c = ControlConsistency::check($pdo);
$names = array_values(array_filter($c['mismatches'], fn ($x) => $x['check'] === 'names' && str_starts_with($x['key'], 'joblate:')));
$t('…no raw id on the board or in Pulse for any of them', !$names, array_slice($names, 0, 2));
$run('sales_tick', '1 MINUTE'); ControlAlarms::tick($pdo);
$t('…a job that starts again clears', $open('joblate:sales_tick') === null && $open('joblate:work_orders_tick') !== null);
$run('identity_recheck_tick', '30 HOUR', false); ControlAlarms::tick($pdo);
$st = $open('job:identity_recheck_tick:stuck');
$t('a hung run: its title and reading in UTC, labelled, its button by the job\'s name', $st && preg_match('/ UTC never finished/', (string) $st['title']) && preg_match('/^started [A-Z][a-z]{2} \d{1,2}, \d{2}:\d{2} UTC$/', (string) $st['reading']) && str_contains((string) $st['fix'], 'Its job: ' . ControlAlarms::jobName('identity_recheck_tick')), $st ? [$st['title'], $st['reading']] : null);
// ---- the Sync chip (03 §27): Railway and the sync's watch in its own chip, never also under Warnings ----
ControlAlarms::raise($pdo, 'ops:watch:sync', 'svc:github', 'warning', 'check', 'The sync: needs a look', 'Its last run is late.', 'Open its runs.', 'late', [], false, false);
ControlAlarms::raise($pdo, 'svc:other:warn', 'svc:tavily', 'warning', 'retry', 'Tavily asked us to slow down', '', '', '', [], false, false);
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php';
$openA = array_values(array_filter(ControlAlarms::list($pdo, 500), fn ($a) => $a['cleared_at'] === null));
$q = tv_cb_qbar($pdo, $openA, [], [], 'observe');
$chip = function (string $label) use ($q) { return preg_match('~<summary class="cb-qgs"><span>' . preg_quote($label, '~') . '</span><b>(\d+)</b>.*?</details>~s', $q, $m) ? $m[0] : ''; };
$t('the Sync chip: the watch\'s alarm in its own chip, with its count', (bool) preg_match('~<span>Sync</span><b>1</b>~', $q) && str_contains($chip('Sync'), 'The sync: needs a look'), mb_substr($q, 0, 300));
$t('…never also under Warnings', !str_contains($chip('Warnings'), 'The sync: needs a look') && str_contains($chip('Warnings'), 'Tavily asked us to slow down'), mb_substr($chip('Warnings'), 0, 300));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
