<?php
// The Control Board's release 3f (cleanup fixes folded into the board, 2026-11-18): staging's check for live payment keys
// reads the names the code reads, by value; the release pipeline's guards ("none" as no switch, a git build left at
// Planned picked up again, why on its card); Supabase's setup locks its vectors to the service key; Sources lists in one
// grouped query; quiet and retired jobs aren't late. Run: mariadb < ../release-b-tests/start-schema.sql && php r3f.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/StagingGate.php'; require_once $app . '/lib/Releases.php'; require_once $app . '/lib/Vectors.php'; require_once $app . '/lib/Scheduler.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };

// 1. staging sees no live payment key under any name the code reads
$k = StagingGate::liveKeys(['STRIPE_SECRET_KEY' => 'sk_test_1', 'STRIPE_PUBLISHABLE_KEY' => 'pk_test_1', 'SQUARE_APP_ID' => 'sandbox-sq0idb-1', 'SQUARE_APP_SECRET' => 'sandbox-sq0csb-1', 'STRIPE_TEST_SECRET_KEY' => 'sk_test_2']);
$t('test keys everywhere: nothing flagged', $k === [], $k);
$k = StagingGate::liveKeys(['STRIPE_PUBLISHABLE_KEY' => 'pk_live_1']);
$t('a live publishable key under the plain name is caught (the old list named STRIPE_LIVE_*)', $k === ['STRIPE_PUBLISHABLE_KEY (a live key)'], $k);
$k = StagingGate::liveKeys(['STRIPE_TEST_SECRET_KEY' => 'sk_live_9']);
$t('a live key put under a _TEST_ name by mistake is caught', in_array('STRIPE_TEST_SECRET_KEY (a live key)', $k, true), $k);
$k = StagingGate::liveKeys(['SQUARE_APP_ID' => 'sq0idp-live', 'SQUARE_SANDBOX_APP_SECRET' => 'sq0csp-live']);
$t('Square\'s production app id and secret are caught, under either name', in_array('SQUARE_APP_ID (a live key)', $k, true) && in_array('SQUARE_SANDBOX_APP_SECRET (a live key)', $k, true), $k);
$k = StagingGate::liveKeys(['STRIPE_SECRET_KEY' => 'rk_live_x', 'QB_ENV' => 'production', 'STRIPE_LIVE_SECRET_KEY' => 'anything']);
$t('the old checks still hold: a restricted live key, QB production, the old names', in_array('STRIPE_SECRET_KEY (a live key)', $k, true) && in_array('QB_ENV=production', $k, true) && in_array('STRIPE_LIVE_SECRET_KEY', $k, true), $k);

// 2. the release pipeline: a git build is known by its log; "none" is no switch
$t('a build from git is known by its log line', Releases::fromGitBuild(['log' => [['msg' => 'Came from git: releases/inbox/x.zip (git abc).']]]) && !Releases::fromGitBuild(['log' => [['msg' => 'Uploaded by Jason.']]]));
$src = file_get_contents($app . '/lib/Releases.php');
$t('"none", "off" and "no" in release.json are no switch', str_contains($src, "in_array(\$sw, ['none', 'off', 'no', 'null', '-'], true)"));
$t('the 10-minute job takes a git build left at Planned or Plan approved', str_contains($src, "state IN ('planned', 'approved') AND updated_at < NOW() - INTERVAL 10 MINUTE") && str_contains($src, 'self::fromGitBuild($r)'));

// 3. Supabase: row level security on, the match function closed to the public roles
$sql = Vectors::setupSql();
$t('Supabase\'s setup: row level security, tv_match only for the service role', str_contains($sql, 'alter table tv_vectors enable row level security;') && str_contains($sql, 'from public, anon, authenticated;') && str_contains($sql, 'to service_role;'));

// 4. Sources: one row per site, grouped in the database, the newest crawl with its count
$pdo->exec('CREATE TABLE IF NOT EXISTS crawler_sources (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, url VARCHAR(500) NOT NULL, domain VARCHAR(190) NULL, engine VARCHAR(20) NULL, status VARCHAR(20) NULL,
    last_error TEXT NULL, last_pages_fetched INT NULL, last_crawled_at DATETIME NULL, last_log MEDIUMTEXT NULL)');
$pdo->exec('DELETE FROM crawler_sources');
$pdo->exec("INSERT INTO crawler_sources (url, domain, engine, status, last_pages_fetched, last_crawled_at, last_log) VALUES
    ('https://a.org/', 'a.org', 'directory', 'done', 3, NOW() - INTERVAL 2 DAY, REPEAT('x', 5000)), ('https://a.org/', 'a.org', 'directory', 'done', 9, NOW() - INTERVAL 1 DAY, 'y'),
    ('https://b.org/', 'b.org', 'discovery', 'done', 1, NOW(), 'z'), ('https://worker.traversence/1', 'worker.traversence', 'directory', 'done', 2, NOW(), 'w'), ('https://worker.traversence/2', 'worker.traversence', 'directory', 'done', 2, NOW(), 'w')");
$all = require $app . '/admin/tools.php';
[$rows] = ($all['insights/sources']['list'])($pdo, []);
$by = array_column($rows, null, 'title');
$t('Sources: one row per site, its newest crawl and how many, in one grouped query', count($rows) === 3 && isset($by['a.org · 2 crawls']) && (int) $by['a.org · 2 crawls']['pages'] === 9 && isset($by['The crawler\'s own jobs · 2 runs']), array_keys($by));
[$rows2] = ($all['insights/sources']['list'])($pdo, ['q' => 'www.a.org']);
$t('…a search by address still finds its site', count($rows2) === 1 && $rows2[0]['title'] === 'a.org · 2 crawls', $rows2);

// 5. jobs quiet by design are never late
$t('a job that logs only when it works, and a retired one, are quiet', in_array('staging_carry_tick', Scheduler::QUIET, true) && in_array('staging_tick', Scheduler::RETIRED, true) && !in_array('ai_worker', array_merge(Scheduler::QUIET, Scheduler::RETIRED), true));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
