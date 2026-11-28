<?php
// app/scripts/search_index_tick.php — the search index caught up with changed listings, places and stories, by its own job
// (SearchIndex::refresh), never inside a visitor's search: that catch-up was the 2-3 s first search after a quiet spell
// (the session manager, 2026-11-27, gate 1; decisions/0163 is the home of jobs' timing). Sets itself up in cPanel through the
// API (Scheduler::AUTO): every 15 minutes; refresh itself still skips a run within 15 minutes of the last.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
Scheduler::run('search_index_tick');   // its runs show in System Configuration → Scheduled tasks
require_once __DIR__ . '/../lib/SearchIndex.php';
$pdo = Database::connection();
SearchIndex::refresh($pdo);
echo 'Search index caught up: ' . (SearchIndex::state($pdo, 'refreshed_at') ?: 'not ready yet') . "\n";
