<?php
// control-board-offregion (the session manager, 2026-11-19, from Jason: "no way for me to clearly see the issue and how to
// fix it"): sources read regularly outside our region, judged fairly (a country's address that isn't a generic one, or
// places mostly outside Arizona and New Mexico; never a link shortener or a generic ending like .io), recounted by the
// watch and cleared when none are left; the button opens them beside the board with Pause, Keep, Pause all, Undo for a
// day and the welcome rules; shorteners followed to their real site by the system (429 backs off). And acknowledging:
// the row leaves the open list at once; a group's acknowledged members hide under Open alarms too.
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php offregion.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/crawler/Sources.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/SearchIndex.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
foreach (['reference_sources', 'source_facts', 'control_alarms'] as $tb) $pdo->exec("DROP TABLE IF EXISTS $tb");
$pdo->exec("CREATE TABLE reference_sources (id INT AUTO_INCREMENT PRIMARY KEY, host VARCHAR(190) UNIQUE, home_url VARCHAR(500), kind VARCHAR(30), status VARCHAR(12), label VARCHAR(190) NULL, confirmations INT NOT NULL DEFAULT 0, last_listing VARCHAR(255) NULL, sample_url VARCHAR(500) NULL, added_by VARCHAR(12) NULL, last_seen DATETIME NULL, last_read_at DATETIME NULL, judged_by VARCHAR(12) NULL, judged_why VARCHAR(255) NULL, cc_crawl VARCHAR(24) NULL)");
$pdo->exec("CREATE TABLE source_facts (id INT AUTO_INCREMENT PRIMARY KEY, host VARCHAR(190), page_url VARCHAR(500), name VARCHAR(190), address VARCHAR(255) NULL, zip VARCHAR(10) NULL)");
ControlAlarms::ensure($pdo);
SearchIndex::setState($pdo, 'src:offregion_rules', ''); SearchIndex::setState($pdo, 'src:offregion_undo', '');
$add = fn (string $h, string $kind = 'other', string $st = 'read') => $pdo->prepare("INSERT INTO reference_sources (host, home_url, kind, status, confirmations, added_by) VALUES (?, ?, ?, ?, 3, 'crawler')")->execute([$h, 'https://' . $h . '/', $kind, $st]);
foreach (['visit-ireland.ie', 'goo.gl', 'maps.app.goo.gl', 'v1-identity.dudesolutions.io', 'flagstaffaz.gov', 'example.de', 'faraway-county.org'] as $h) $add($h, str_ends_with($h, '.gov') ? 'government' : 'other');
$add('bavaria.de', 'other', 'ignore');
// faraway-county.org: 6 facts, all in Ohio; flagstaffaz.gov: in Arizona
foreach (range(1, 6) as $i) $pdo->prepare("INSERT INTO source_facts (host, page_url, name, zip) VALUES ('faraway-county.org', ?, ?, '43001')")->execute(['https://faraway-county.org/' . $i, 'Ohio place ' . $i]);
foreach (range(1, 6) as $i) $pdo->prepare("INSERT INTO source_facts (host, page_url, name, zip) VALUES ('flagstaffaz.gov', ?, ?, '86001')")->execute(['https://flagstaffaz.gov/' . $i, 'Flagstaff place ' . $i]);
[$off, $short] = Sources::offRegion($pdo);
$hosts = array_map(fn ($x) => $x[0]['host'], $off);
sort($hosts);
$t('off-region: a country\'s address, or places mostly elsewhere; never a shortener, a generic .io, an in-region site, or one not read', $hosts === ['example.de', 'faraway-county.org', 'visit-ireland.ie'], $hosts);
$t('…each with why, in words', (bool) array_filter($off, fn ($x) => $x[0]['host'] === 'faraway-county.org' && str_contains($x[1], '6 of 6 outside Arizona and New Mexico')));
$t('…the shorteners are set aside to be followed, not judged', count($short) === 2);
$t('the last facts shown are names and ZIPs only', Sources::lastFacts($pdo, 'faraway-county.org')[0] === 'Ohio place 6 (43001)');
// rules
$msg = Sources::offRuleAdd($pdo, 'ending', 'ie');
$t('a welcome rule (ending .ie) takes its sources out of the count', str_contains($msg, 'Welcome now: .ie') && count(Sources::offRegion($pdo)[0]) === 2, $msg);
Sources::offRuleAdd($pdo, 'kind', 'other');
$t('…a kind rule too', count(Sources::offRegion($pdo)[0]) === 0);
Sources::offRuleDel($pdo, 1); Sources::offRuleDel($pdo, 0);
$t('…and removing the rules brings them back', count(Sources::offRegion($pdo)[0]) === 3);
try { Sources::offRuleAdd($pdo, 'kind', 'nonsense'); $t('a rule that isn\'t one is refused, saying what is', false); } catch (InvalidArgumentException $e) { $t('a rule that isn\'t one is refused, saying what is', str_contains($e->getMessage(), 'Say which')); }
// pause, pause all, undo
$ids = array_map(fn ($x) => (int) $x[0]['id'], Sources::offRegion($pdo)[0]);
$n = Sources::offPause($pdo, $ids, 1);
$t('Pause all: each not read any more', $n === 3 && count(Sources::offRegion($pdo)[0]) === 0 && (int) $pdo->query("SELECT COUNT(*) FROM reference_sources WHERE status = 'ignore'")->fetchColumn() === 4);
$t('…Undo for a day puts each back to read regularly', Sources::offUndo($pdo) === 3 && count(Sources::offRegion($pdo)[0]) === 3);
try { Sources::offUndo($pdo); $t('…and only once', false); } catch (InvalidArgumentException $e) { $t('…and only once', str_contains($e->getMessage(), 'Nothing to undo')); }
// the watch: the warning recounts, names its button, clears when none are left
$src = file_get_contents($app . '/lib/ControlAlarms.php');
$t('the watch recounts the warning hourly with the button that opens the sources, and clears it when none are left', str_contains($src, "'Open the ' . count(\$off) . ' sources', 'peek', '/admin/sources.php?show=offregion'") && str_contains($src, "self::clear(\$pdo, 'w3:offregion'") && str_contains($src, 'Sources::resolveShorteners($pdo)'));
$t('…and the seed no longer raises it once and leaves it', !str_contains(substr($src, strpos($src, 'public static function seed'), 6000), "'w3:offregion'"));
$sp = file_get_contents(dirname($app) . '/website/admin/sources.php');
$t('the sources page\'s off-region view: Pause, Keep, Pause all with a yes, Undo, the welcome rules', str_contains($sp, "\$filter === 'offregion'") && str_contains($sp, "'offpauseall'") && str_contains($sp, "'offkeep'") && str_contains($sp, "'offundo'") && str_contains($sp, 'Which outside sources are welcome'));
$ss = file_get_contents($app . '/lib/crawler/Sources.php');
$t('shorteners: one try per host an hour, a 429 backs off 20 minutes doubling, through the staging gate', str_contains($ss, "'next' => time() + 3600") && str_contains($ss, 'min(360, 20 * 2 ** ($hits - 1))') && str_contains($ss, 'StagingGate::hold($url'));
// acknowledging
$js = file_get_contents(dirname($app) . '/website/js/admin-control.js');
$t('Acknowledge takes the row out of the open list at once, saying where it went', str_contains($js, "act === 'ack'") && str_contains($js, 'under Acknowledged'));
$t('…and inside a group, acknowledged members hide under Open alarms too', str_contains($js, "ul.cb-al-in > li[data-astate]"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
