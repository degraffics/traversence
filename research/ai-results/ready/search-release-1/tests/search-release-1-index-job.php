<?php
// tools/search-tests/search-release-1-index-job.php — plain PHP, prints PASS/FAIL. SQLite in memory; no cPanel call, no
// network. Run from the repo: php tools/search-tests/search-release-1-index-job.php  (or set TV_ROOT).
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
require_once $root . '/app/lib/Scheduler.php';
require_once $root . '/app/lib/SearchIndex.php';

$fails = 0;
function check(string $what, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . '  ' . $what . "\n"; if (!$ok) $fails++; }

// (c) the job is in Scheduler::JOBS, sets itself up, and its script is there
check('search_index_tick in JOBS', isset(Scheduler::JOBS['search_index_tick']));
check('its preset exists', isset(Scheduler::PRESETS[Scheduler::JOBS['search_index_tick'][2] ?? '']));
check('late is at least its schedule', (int) (Scheduler::JOBS['search_index_tick'][3] ?? 0) >= 15);
check('in AUTO (sets itself up in cPanel)', in_array('search_index_tick', Scheduler::AUTO, true));
check('not kept off staging', !in_array('search_index_tick', Scheduler::NOT_ON_STAGING, true));
check('script exists', is_file($root . '/app/scripts/search_index_tick.php'));
check('job key fits job_runs.job (40)', strlen('search_index_tick') <= 40);

// a search skips the catch-up while the job has run in the last 30 minutes
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE search_index (kind TEXT, item_id TEXT)');
$pdo->exec('CREATE TABLE search_index_state (k TEXT PRIMARY KEY, v TEXT)');
$old = date('Y-m-d H:i:s', time() - 7200);
SearchIndex::setState($pdo, 'refreshed_at', $old);
SearchIndex::setState($pdo, 'refresh_job_at', date('Y-m-d H:i:s', time() - 60));
SearchIndex::refresh($pdo);   // as a visitor's search calls it
check('a search leaves it to the job', SearchIndex::state($pdo, 'refreshed_at') === $old);

echo $fails ? "FAILED: $fails\n" : "ALL PASS\n";
exit($fails ? 1 : 0);
