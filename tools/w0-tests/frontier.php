<?php
// tools/w0-tests/frontier.php — W0 Indexer's frontier (app/lib/crawler/Frontier.php, decisions/0164 §1.4's note) against
// a real MySQL or MariaDB: normalizing, found batches and their receipts (a resend changes nothing), junk by address and
// remembered by hash, the machine's call on new sites (a nation's set aside for a person), reading priority and what's
// handed out, pages' answers (a block holds the site, gone twice is junk), verdicts and Undo, skip rules from junk, the
// size guard, the first pass over "Waiting for you", and the page log's links feeding the frontier with no cap.
//   php tools/w0-tests/frontier.php <app folder>
// The app folder's .env (four folders up from app/lib) points DB_DATABASE at an empty test database, whose tables the
// migration (app/migrations/2027-03-12_w0_frontier.sql) has made; this makes the few others it needs.
declare(strict_types=1);

$app = rtrim($argv[1] ?? '', '/');
if ($app === '' || !is_file($app . '/lib/crawler/Frontier.php')) { fwrite(STDERR, "usage: php frontier.php <app folder>\n"); exit(2); }
putenv('TV_TEST=1');
require_once $app . '/lib/Database.php';
require_once $app . '/lib/crawler/Frontier.php';
require_once $app . '/lib/crawler/SourceIndex.php';

$fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; echo ($c ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$c) $fails++; }
$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$one = fn (string $sql) => $pdo->query($sql)->fetchColumn();

// the tables it leans on, as the live site has them (the columns it reads)
$pdo->exec("CREATE TABLE IF NOT EXISTS search_index_state (k VARCHAR(100) PRIMARY KEY, v MEDIUMTEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS reference_sources (id INT AUTO_INCREMENT PRIMARY KEY, host VARCHAR(190) UNIQUE, home_url VARCHAR(500), kind VARCHAR(30), status VARCHAR(12),
            label VARCHAR(190) NULL, confirmations INT NOT NULL DEFAULT 0, last_listing VARCHAR(255) NULL, sample_url VARCHAR(500) NULL, added_by VARCHAR(12) NULL,
            last_seen DATETIME NULL, last_read_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS source_facts (id INT AUTO_INCREMENT PRIMARY KEY, host VARCHAR(190), page_url VARCHAR(500), name VARCHAR(190))");
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_workflows (wf VARCHAR(4) PRIMARY KEY, name VARCHAR(60) NOT NULL, label VARCHAR(60) NULL, paused TINYINT NOT NULL DEFAULT 0, paused_by INT NULL, paused_at DATETIME NULL, paused_why VARCHAR(255) NULL, share_pct INT NOT NULL DEFAULT 10, updated_at DATETIME NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS automation_switches (kind VARCHAR(12), ref VARCHAR(40), paused TINYINT, why VARCHAR(255), by_uid INT NULL, by_name VARCHAR(80) NULL, at DATETIME NULL, PRIMARY KEY (kind, ref))");
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, display_name VARCHAR(100))");
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_costs (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME, service VARCHAR(30), wf VARCHAR(4), task VARCHAR(40), units INT, usd_micros BIGINT, ok TINYINT, run_id INT NULL, note VARCHAR(255))");
$pdo->exec("CREATE TABLE IF NOT EXISTS entity_metadata (entity_id INT, meta_key VARCHAR(40), meta_value TEXT)");
$pdo->exec("INSERT INTO automation_switches VALUES ('master', 'all', 0, NULL, NULL, NULL, NOW())");

// ---- normalizing (§1.1) -------------------------------------------------------------------------------------------------
ok(Frontier::normalize('HTTPS://WWW.Example.COM/a/b/?utm_source=x&b=2&a=1#frag') === 'https://www.example.com/a/b?a=1&b=2', 'lowercase scheme and host, fragment and tracking off, keys sorted, trailing slash off');
ok(Frontier::normalize('https://example.com/') === 'https://example.com/', 'the root keeps its slash');
ok(Frontier::normalize('../c/d.html', 'https://example.com/a/b/page.html') === 'https://example.com/a/c/d.html', 'a relative link resolves against its page');
ok(Frontier::normalize('mailto:x@example.com') === null && Frontier::normalize('javascript:void(0)') === null, 'not web addresses');
ok(Frontier::normalize('http://127.0.0.1/admin') === null && Frontier::normalize('http://localhost/x') === null, 'private addresses never');
ok(Frontier::junkByAddress('https://example.com/wp-admin/edit.php')[0] === 'ADMIN_PATH', 'a sign-in path is junk by its address');
ok(Frontier::junkByAddress('https://example.com/events/?ical=1')[0] === 'TRAP', 'an iCal export is a trap');
ok(Frontier::junkByAddress('https://example.com/calendar/2031-05/')[0] === 'TRAP', 'a calendar years away is a trap');
ok(Frontier::junkByAddress('https://example.com/img/logo.png')[0] === 'MEDIA' && Frontier::junkByAddress('https://example.com/report.pdf') === null, 'images are not pages; PDFs are read');

// ---- W0's switch -----------------------------------------------------------------------------------------------------
Frontier::ensureWorkflow($pdo);
ok((int) $one("SELECT paused FROM crawl_workflows WHERE wf = 'W0'") === 1, 'W0 ships switched off');
ok(str_contains((string) Frontier::off($pdo), 'W0'), 'off says why: W0 is paused');

// ---- a found batch, and its receipt ------------------------------------------------------------------------------------
$pdo->exec("INSERT INTO reference_sources (host, home_url, kind, status, added_by) VALUES ('countyaz.gov', 'https://countyaz.gov/', 'government', 'read', 'person')");
$rows = [];
for ($i = 0; $i < 300; $i++) $rows[] = ['u' => "https://countyaz.gov/services/page-$i", 'f' => 'https://countyaz.gov/', 't' => "Service $i"];
$rows[] = ['u' => 'https://visitshowlow.com/things-to-do', 'f' => 'https://countyaz.gov/', 't' => 'Visit Show Low'];
$rows[] = ['u' => 'https://www.facebook.com/somebusiness', 'f' => 'https://countyaz.gov/', 't' => 'Facebook'];
$rows[] = ['u' => 'https://navajo-nsn.gov/', 'f' => 'https://countyaz.gov/', 't' => 'Navajo Nation'];
$rows[] = ['u' => 'https://countyaz.gov/wp-login.php', 'f' => 'https://countyaz.gov/', 't' => 'Log in'];
$rows[] = ['u' => 'mailto:clerk@countyaz.gov', 'f' => 'https://countyaz.gov/', 't' => 'Email'];
$r = Frontier::found($pdo, $rows);
$sent = array_map(fn ($x) => sha1($x['u']), $rows);
$rej = array_flip(array_column($r['rejected'], 0));
$mine = array_values(array_filter($sent, fn ($k) => !isset($rej[$k]))); sort($mine);
ok($r['received'] === 305 && count($r['rejected']) === 1, 'all 305 received; the mailto is rejected with its key');
ok($r['checksum'] === sha1(implode(',', $mine)) && $r['stored'] === 304, 'the receipt\'s checksum matches what the worker computes over what it sent');
ok((int) $one('SELECT COUNT(*) FROM crawl_frontier') === 303, 'no cap: 300 same-site links and 3 other sites logged (the sign-in page as junk)');
ok((int) $one('SELECT COUNT(*) FROM crawl_junk') === 1, 'the sign-in page is kept compact, as junk');
$r2 = Frontier::found($pdo, $rows);
ok($r2['checksum'] === $r['checksum'] && (int) $one('SELECT COUNT(*) FROM crawl_frontier') === 303 && (int) $one('SELECT MAX(links_in) FROM crawl_frontier') === 1, 'a resent batch changes nothing (no new rows, links not counted twice)');
ok((int) $one("SELECT COUNT(*) FROM reference_sources WHERE added_by = 'index'") === 3, 'each new site goes on the register (no cap of 40)');
ok((string) $one("SELECT status FROM reference_sources WHERE host = 'facebook.com'") === 'cite_only', 'Facebook: cite only, by the machine');
ok((string) $one("SELECT judged_by FROM reference_sources WHERE host = 'navajo-nsn.gov'") === 'nation' && (string) $one("SELECT status FROM reference_sources WHERE host = 'navajo-nsn.gov'") === 'suggested', 'a nation\'s site is set aside for a person (R7)');
ok($one("SELECT judged_by FROM reference_sources WHERE host = 'visitshowlow.com'") === null, 'a site its address can\'t settle waits for its home page to be read, not for a person');
ok((int) $one("SELECT depth FROM crawl_frontier WHERE url = 'https://countyaz.gov/services/page-5'") === 0, 'linked from a page not logged yet: depth 0');

// ---- reading priority and what's handed out --------------------------------------------------------------------------
Frontier::rescoreSite($pdo, 'countyaz.gov');
$pdo->exec("UPDATE crawl_workflows SET paused = 0 WHERE wf = 'W0'");
require_once $app . '/lib/Automation.php'; Automation::forget();
ok(Frontier::off($pdo) === null, 'W0 on: off says nothing');
$w = Frontier::work($pdo, 25);
ok(count($w) === 3 && count(array_unique(array_column($w, 'host'))) === 1, 'per site, 3 at a time (the pace is shared across sites)');
ok(!in_array('facebook.com', array_column($w, 'host'), true) && !in_array('navajo-nsn.gov', array_column($w, 'host'), true), 'Facebook and a nation\'s site are logged, never handed out');
ok(count(Frontier::work($pdo, 25)) === 3, 'leased ones aren\'t handed out twice; the next 3 come');

// ---- the home page read, then the machine's call -------------------------------------------------------------------------
Sources::describe($pdo, 'visitshowlow.com', ['site_name' => 'Visit Show Low', 'title' => 'Things to do in Show Low', 'description' => 'The Show Low convention and visitors bureau', 'types' => ['Organization']]);
Sources::sweep($pdo, 50);
ok((string) $one("SELECT status FROM reference_sources WHERE host = 'visitshowlow.com'") === 'read' && (int) $one("SELECT tier FROM reference_sources WHERE host = 'visitshowlow.com'") === 3, 'a tourism board: Read regularly at Tier 3, by the machine');
ok((int) $one("SELECT site_score FROM crawl_frontier WHERE host = 'visitshowlow.com'") > 0, 'its URLs may now be handed out');
Sources::describe($pdo, 'parkedthing.com', []);
$pdo->exec("INSERT INTO reference_sources (host, home_url, kind, status, added_by) VALUES ('parkedthing.com', 'https://parkedthing.com/', 'other', 'suggested', 'index'), ('tribalsite.org', 'https://tribalsite.org/', 'other', 'suggested', 'index')");
Sources::describe($pdo, 'parkedthing.com', ['title' => 'This domain is for sale', 'description' => 'Buy this domain']);
Sources::describe($pdo, 'tribalsite.org', ['title' => 'Welcome to the Pueblo', 'description' => 'Official site of the tribe']);
Sources::sweep($pdo, 50);
ok((string) $one("SELECT status FROM reference_sources WHERE host = 'parkedthing.com'") === 'ignore' && (int) $one("SELECT tier FROM reference_sources WHERE host = 'parkedthing.com'") === 5, 'a parked domain: Tier 5, never read');
ok((string) $one("SELECT judged_by FROM reference_sources WHERE host = 'tribalsite.org'") === 'nation', 'a site that says it\'s a tribe\'s is a person\'s call, whatever its address');
$pdo->exec("INSERT INTO reference_sources (host, home_url, kind, status, added_by) VALUES ('decided.org', 'https://decided.org/', 'organization', 'cite_only', 'person')");
$pdo->exec("UPDATE reference_sources SET judged_by = 'person' WHERE host = 'decided.org'");
Sources::sweep($pdo, 50);
ok((string) $one("SELECT status FROM reference_sources WHERE host = 'decided.org'") === 'cite_only', 'nothing already decided changes');

// ---- pages' answers --------------------------------------------------------------------------------------------------
$p = Frontier::pages($pdo, [['u' => $w[0]['u'], 's' => 200, 'ty' => 'text/html', 'b' => 1000, 'fp' => str_repeat('a', 40), 'ti' => 'Page'],
                            ['u' => $w[1]['u'], 's' => 404], ['u' => $w[2]['u'], 's' => 503]]);
ok($p['stored'] === 3 && (string) $one("SELECT state FROM crawl_frontier WHERE url = " . $pdo->quote($w[0]['u'])) === 'fetched', 'a 200 is read, logged in the page log, due again by how often it changes');
ok((int) $one("SELECT COUNT(*) FROM crawl_pages WHERE url = " . $pdo->quote($w[0]['u'])) === 1, 'the page log has it');
ok((string) $one("SELECT state FROM crawl_frontier WHERE url = " . $pdo->quote($w[1]['u'])) === 'gone', 'a 404 is gone, checked again in 30 days');
$pdo->exec("UPDATE crawl_frontier SET next_at = NOW() - INTERVAL 1 MINUTE WHERE url = " . $pdo->quote($w[1]['u']));
Frontier::pages($pdo, [['u' => $w[1]['u'], 's' => 410]]);
ok((int) $one("SELECT COUNT(*) FROM crawl_junk WHERE reason = 'GONE'") === 1 && (int) $one("SELECT COUNT(*) FROM crawl_frontier WHERE url = " . $pdo->quote($w[1]['u'])) === 0, 'gone twice: junk, kept as its hash');
ok((int) $one("SELECT tries FROM crawl_frontier WHERE url = " . $pdo->quote($w[2]['u'])) === 1, 'a 503 backs off and is tried again');
$f = Frontier::found($pdo, [['u' => $w[1]['u'], 'f' => 'https://countyaz.gov/']]);
ok($f['junk'] === 1 && (int) $one("SELECT COUNT(*) FROM crawl_frontier WHERE url = " . $pdo->quote($w[1]['u'])) === 0, 'found again: recognised as junk, never logged twice');
Frontier::pages($pdo, [['u' => 'https://visitshowlow.com/things-to-do', 's' => 403, 'why' => 'auth']]);
ok($one("SELECT held_at FROM reference_sources WHERE host = 'visitshowlow.com'") !== null && (int) $one("SELECT MAX(site_score) FROM crawl_frontier WHERE host = 'visitshowlow.com'") < -10000, 'a 403 holds the whole site, never retried by itself');
ok(count(Frontier::needs($pdo)) >= 1, 'and Pulse is asked');
Frontier::reactivate($pdo, 'visitshowlow.com', 1, 'Jason');
ok($one("SELECT held_at FROM reference_sources WHERE host = 'visitshowlow.com'") === null && (int) $one("SELECT MAX(site_score) FROM crawl_frontier WHERE host = 'visitshowlow.com'") > 0, 'Reactivate: one polite try');

// ---- verdicts and Undo --------------------------------------------------------------------------------------------------
$h = Frontier::hash((string) Frontier::normalize('https://countyaz.gov/services/page-9'));
$was = (int) $one("SELECT priority FROM crawl_frontier WHERE url_hash = '$h'");
$id = Frontier::verdict($pdo, 'url', $h, 'promoted', 'It gave places we kept.', 'facts_kept');
ok($id > 0 && (int) $one("SELECT priority FROM crawl_frontier WHERE url_hash = '$h'") === $was + 300, 'promoted: ranked up, in the audit log');
$id2 = Frontier::verdict($pdo, 'url', $h, 'junk', 'Not useful.', 'person', 1, 'Jason');
ok((int) $one("SELECT COUNT(*) FROM crawl_junk WHERE url_hash = '$h' AND reason = 'PERSON'") === 1, 'a person marks it junk');
ok(Frontier::verdict($pdo, 'url', $h, 'normal', 'machine says', 'machine') === 0, 'the machine never overrides a person\'s verdict');
Frontier::undo($pdo, $id2, 1, 'Jason');
ok((int) $one("SELECT COUNT(*) FROM crawl_junk WHERE url_hash = '$h'") === 0 && $one("SELECT undone_at FROM crawl_audit_log WHERE id = $id2") !== null, 'Undo: the junk record goes (logged again when found), the undo logged');
Frontier::found($pdo, [['u' => 'https://countyaz.gov/services/page-9', 'f' => 'https://countyaz.gov/']]);
ok((int) $one("SELECT COUNT(*) FROM crawl_frontier WHERE url_hash = '$h'") === 1, 'found again after the undo: logged again');

// ---- skip rules from junk seen often ---------------------------------------------------------------------------------
$t = [];
for ($i = 0; $i < 30; $i++) $t[] = ['u' => "https://countyaz.gov/calendar/?ical=$i", 'f' => 'https://countyaz.gov/'];
Frontier::found($pdo, $t);
ok(Frontier::mineRules($pdo) === 1 && (string) $one('SELECT pattern FROM crawl_skip_rules') === '?ical', 'junk seen 25 times becomes a rule');
$s = Frontier::found($pdo, [['u' => 'https://countyaz.gov/other/?ical=99', 'f' => 'https://countyaz.gov/']]);
ok($s['skipped'] === 1 && (int) $one('SELECT hits FROM crawl_skip_rules') === 1, 'the rule skips the family at the door');

// ---- the page log's links: every one into the frontier (W3's reads too) ---------------------------------------------------
$links = []; for ($i = 0; $i < 150; $i++) $links[] = "https://countyaz.gov/dir/$i";
SourceIndex::logPages($pdo, 'countyaz.gov', [['url' => 'https://countyaz.gov/dir', 'status' => 200, 'fingerprint' => str_repeat('b', 40), 'links' => $links, 'named' => 2]]);
ok((int) $one("SELECT COUNT(*) FROM crawl_frontier WHERE url LIKE 'https://countyaz.gov/dir/%'") === 150, 'a page log row\'s 150 links all reach the frontier (no cap of 60)');
ok(count(json_decode((string) $one("SELECT links FROM crawl_pages WHERE url = 'https://countyaz.gov/dir'"), true)) === 150, 'and the page log keeps them all as its way on');
ok((string) $one("SELECT verdict FROM crawl_frontier WHERE url = 'https://countyaz.gov/dir'") === 'promoted', 'a page whose places we kept is promoted');
ok((int) $one("SELECT depth FROM crawl_frontier WHERE url = 'https://countyaz.gov/dir/7'") === 1, 'a link from a trusted site\'s page is one away');

// ---- receipts and the size guard ------------------------------------------------------------------------------------------
Frontier::receipt($pdo, 'k1', 'found', 250, ['received' => 250, 'stored' => 250, 'rejected' => [], 'checksum' => 'x'], 1000, 10);
foreach (['k2', 'k3', 'k4'] as $k) Frontier::receipt($pdo, $k, 'found', 250, ['received' => 120, 'stored' => 120, 'rejected' => [], 'checksum' => 'x'], 1000, 10);
ok(Frontier::mismatches($pdo) === 3 && in_array('receipts', array_column(Frontier::needs($pdo), 0), true), 'three cut batches in a row: Pulse is asked');
Frontier::receipt($pdo, 'k4', 'found', 250, ['received' => 250, 'stored' => 250, 'rejected' => [], 'checksum' => 'x'], 1000, 10);
ok(Frontier::mismatches($pdo) === 0, 'resent whole: the same key, one row, matched');
$pdo->exec("UPDATE crawl_frontier SET first_at = NOW() - INTERVAL 200 DAY WHERE url LIKE 'https://countyaz.gov/services/page-1%'");
$c = Frontier::compress($pdo);
ok($c > 0 && (int) $one("SELECT COUNT(*) FROM crawl_frontier WHERE url IS NULL") === $c, 'never read in six months: kept as its hash');
Frontier::found($pdo, [['u' => 'https://countyaz.gov/services/page-12', 'f' => 'https://countyaz.gov/']]);
ok((string) $one("SELECT url FROM crawl_frontier WHERE url_hash = '" . sha1('https://countyaz.gov/services/page-12') . "'") === 'https://countyaz.gov/services/page-12', 'found again: recognised, and its address comes back');
$tk = Frontier::tick($pdo);
ok(isset($tk['classified']) && Frontier::size($pdo)['rows'] > 0 && count(Frontier::growth($pdo)) === 1, 'the hourly upkeep runs and records its size');

echo "\n" . ($n - $fails) . ' of ' . $n . " passed\n";
exit($fails ? 1 : 0);
