<?php
// app/scripts/search_index_tick.php — the search index's catch-up (SearchIndex::refresh), every 15 minutes from cron
// (Scheduler::JOBS 'search_index_tick'). It used to run inside a visitor's search; a search now does it only when this
// job hasn't run in 30 minutes. Database only: no outside call.
declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Scheduler.php';
require_once __DIR__ . '/../lib/SearchIndex.php';

Scheduler::run('search_index_tick');

$pdo = Database::connection();
if (!SearchIndex::ready($pdo)) { echo "No search index yet: nothing to catch up.\n"; exit(0); }
$t = microtime(true);
SearchIndex::refresh($pdo, true, true);
echo 'Search index caught up (mark ' . SearchIndex::state($pdo, 'listing_mark') . ') in ' . round(microtime(true) - $t, 1) . " s\n";
