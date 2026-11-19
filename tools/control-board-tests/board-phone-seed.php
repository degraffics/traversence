<?php
// For board-phone.cjs: alarms that make the board's widest cards: a warning whose title carries a long address (Arizona
// Corporation Commission's), grouped with another feed's, and a trip. `php board-phone-seed.php <root>/app`
$app = $argv[1]; require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php'; $pdo = Database::connection();
ControlAlarms::raise($pdo, 'svc:issue:91', 'svc:azcc', 'warning', 'retry', 'Arizona Corporation Commission: the Commission\'s new site (https://arizonabusinesscenter.azcc.gov/businesssearch/entitysearch?searchterm=kayenta) blocks us until our records request is answered', 'It waits.', 'Nothing.', '403', [], false, false);
ControlAlarms::raise($pdo, 'svc:issue:92', 'svc:commoncrawl', 'warning', 'retry', 'data.commoncrawl.org: SlowDown, please reduce your request rate (https://data.commoncrawl.org/crawl-data/CC-MAIN-2024-33/segments/1722640353668.0/warc/)', 'It waits.', 'Nothing.', '503', [], false, false);
ControlAlarms::raise($pdo, 'run:failed', 'wk:crawler', 'trip', 'crash', 'A crawler run failed (#412)', 'Its work goes back.', 'Open the crawler\'s run.', '', [], false, false);
echo "seeded\n";
