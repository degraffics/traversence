<?php
// tools/w14-tests/terms.php — W14's terms read (decisions/0164 §7.1; handoffs/adhs-source.md, release A1) on a real MariaDB:
// the migration twice (ADHS staged once, columns once), the task added switched off, termsOff by each switch, termsDue,
// termsRead kept and refused when a person already read the terms, a disallow or a refusal raising the service's issue,
// the Pulse step's words in each state. Stubs stand in for Automation, EngineCase and ListingIdentity (their own tests
// cover them). Run: php tools/w14-tests/terms.php <build root> [socket]   (it makes and drops its own database, w14_test)
declare(strict_types=1);

$root = rtrim($argv[1] ?? '', '/'); $sock = $argv[2] ?? '/run/mysqld/mysqld.sock';
if (!is_file("$root/app/lib/HubOpening.php")) { fwrite(STDERR, "usage: php terms.php <build root with app/lib/HubOpening.php>\n"); exit(2); }
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }

$pdo = new PDO("mysql:unix_socket=$sock", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS w14_test'); $pdo->exec('CREATE DATABASE w14_test'); $pdo->exec('USE w14_test');
// the live tables this touches, as Release B made them (handoffs/release-b-pending/b2/change.diff)
$pdo->exec("CREATE TABLE authority_sources (id INT UNSIGNED NOT NULL AUTO_INCREMENT, state CHAR(2) NOT NULL, kind VARCHAR(20) NOT NULL, name VARCHAR(160) NOT NULL,
  url VARCHAR(500) NOT NULL, host VARCHAR(160) NOT NULL, service VARCHAR(20) DEFAULT NULL, real_use VARCHAR(10) NOT NULL DEFAULT 'lookup', proves VARCHAR(200) DEFAULT NULL,
  terms_url VARCHAR(500) DEFAULT NULL, terms_read_at DATETIME DEFAULT NULL, terms_by INT UNSIGNED DEFAULT NULL, reader_ok_at DATETIME DEFAULT NULL, provenance VARCHAR(500) DEFAULT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'staged', enabled TINYINT NOT NULL DEFAULT 0, on_by INT UNSIGNED DEFAULT NULL, on_at DATETIME DEFAULT NULL, note VARCHAR(500) DEFAULT NULL,
  added_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY uq_state_url (state, url(190))) DEFAULT CHARSET=utf8mb4");
$pdo->exec("INSERT INTO authority_sources (state, kind, name, url, host, service, real_use, added_at) VALUES ('AZ', 'company', 'Arizona Corporation Commission', 'https://arizonabusinesscenter.azcc.gov/', 'arizonabusinesscenter.azcc.gov', 'azcc', 'lookup', UTC_TIMESTAMP())");
$pdo->exec("CREATE TABLE hub_stages (hub_id INT PRIMARY KEY)"); $pdo->exec("CREATE TABLE crawl_workflows (wf VARCHAR(8) PRIMARY KEY, name VARCHAR(80), label VARCHAR(80), paused TINYINT, paused_at DATETIME, share_pct INT, why VARCHAR(200))"); $pdo->exec("CREATE TABLE hubs (id INT PRIMARY KEY, name VARCHAR(80), status VARCHAR(20))");
$pdo->exec("CREATE TABLE crawl_capture_tasks (task VARCHAR(40) PRIMARY KEY, enabled TINYINT NOT NULL DEFAULT 0, mode VARCHAR(8) NOT NULL DEFAULT 'run', max_fetches INT NOT NULL DEFAULT 0)");

// the migration, as DbUpdates runs it (split on ;), twice
$sql = file_get_contents("$root/app/migrations/2026-11-28_w14_adhs.sql");
$run = function () use ($pdo, $sql) { foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $q) $pdo->exec($q); };
$run(); $run();
$rows = $pdo->query("SELECT * FROM authority_sources WHERE service = 'azdhs'")->fetchAll(PDO::FETCH_ASSOC);
check('migration twice: ADHS staged once, switched off, lookup, its terms unread', count($rows) === 1 && (int) $rows[0]['enabled'] === 0 && $rows[0]['status'] === 'staged' && $rows[0]['real_use'] === 'lookup' && $rows[0]['terms_read_at'] === null);
$cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'w14_test' AND TABLE_NAME = 'authority_sources' AND COLUMN_NAME IN ('robots', 'terms_fetch', 'fetched_at')")->fetchAll(PDO::FETCH_COLUMN);
check('…its three columns added once', count($cols) === 3);
$adhs = (int) $rows[0]['id'];

// a temp lib: the build's HubOpening with stubs beside it
$lib = sys_get_temp_dir() . '/w14-lib-' . getmypid(); @mkdir($lib);
copy("$root/app/lib/HubOpening.php", "$lib/HubOpening.php");
file_put_contents("$lib/Database.php", '<?php ');
file_put_contents("$lib/Automation.php", '<?php final class Automation { public static array $off = []; public static function masterOff($p) { return self::$off["master"] ?? null; } public static function wfOff($p, $w) { return self::$off["wf:" . $w] ?? null; } public static function serviceOff($p, $s) { return self::$off["service:" . $s] ?? null; } public static function sysAny($p, array $k) { foreach ($k as $x) if (isset(self::$off["sys:" . $x])) return self::$off["sys:" . $x]; return null; } }');
file_put_contents("$lib/EngineCase.php", '<?php final class EngineCase { public static array $raised = []; public static function raise($p, $k, $r, $t, $d = []) { self::$raised[] = [$k, $r, $t, $d]; return 1; } }');
file_put_contents("$lib/ListingIdentity.php", '<?php final class ListingIdentity { public static function ensureTask($pdo, $task, $wf, $mode, $label, $what, $per, $every) { $pdo->prepare("INSERT IGNORE INTO crawl_capture_tasks (task, enabled, mode) VALUES (?, 0, ?)")->execute([$task, $mode]); } }');
require "$lib/HubOpening.php"; require_once "$lib/EngineCase.php"; require_once "$lib/Automation.php";

HubOpening::ensureWorkflow($pdo);
$tk = $pdo->query("SELECT * FROM crawl_capture_tasks WHERE task = 'w14_terms'")->fetch(PDO::FETCH_ASSOC);
check('the task w14_terms is added switched off, a run task (a bulk task gets no fetches), 6 fetches', $tk && (int) $tk['enabled'] === 0 && $tk['mode'] === 'run' && (int) $tk['max_fetches'] === 6);
$pdo->exec("UPDATE crawl_capture_tasks SET max_fetches = 9 WHERE task = 'w14_terms'"); HubOpening::ensureWorkflow($pdo);
check('…a ceiling a person set is kept', (int) $pdo->query("SELECT max_fetches FROM crawl_capture_tasks WHERE task = 'w14_terms'")->fetchColumn() === 9);
check('task off: termsOff says so, nothing due', str_contains((string) HubOpening::termsOff($pdo, 'azdhs'), 'switched off') && HubOpening::termsDue($pdo) === []);
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 1 WHERE task = 'w14_terms'");
foreach (['master' => 'Master off', 'wf:W14' => 'W14 paused', 'service:azdhs' => 'ADHS paused', 'sys:task:w14_terms' => 'tripped'] as $k => $w) {
    Automation::$off = [$k => $w];
    check("held by $k: termsOff gives its reason, nothing due", HubOpening::termsOff($pdo, 'azdhs') === $w && HubOpening::termsDue($pdo) === []);
}
Automation::$off = [];
$due = HubOpening::termsDue($pdo);
check('all on: only ADHS is due (a reader service), with its terms page', count($due) === 1 && $due[0]['id'] === $adhs && $due[0]['terms_url'] === 'https://www.azdhs.gov/licensing/index.php');

$r = fn () => $pdo->query("SELECT * FROM authority_sources WHERE id = $adhs")->fetch(PDO::FETCH_ASSOC);
check('words before any read: not read yet, switched off until a person', str_contains(HubOpening::termsWords($r()), "hasn't read it yet"));

// an allowed read
$kept = HubOpening::termsRead($pdo, [['id' => $adhs, 'robots' => 'allowed', 'crawl_delay' => 2, 'page_status' => 200, 'page_why' => 'empty', 'terms_status' => 200, 'terms_why' => '',
    'terms_text' => str_repeat('Licensing terms. ', 40), 'lists' => ['https://www.azdhs.gov/x/facilities.xlsx', 'http://insecure/x.csv', 'javascript:alert(1)']]]);
$row = $r(); $f = json_decode((string) $row['terms_fetch'], true);
check('allowed: kept, its robots, its time, no issue raised', $kept === 1 && $row['robots'] === 'allowed' && $row['fetched_at'] !== null && EngineCase::$raised === []);
check('…only https list links kept', $f['lists'] === ['https://www.azdhs.gov/x/facilities.xlsx']);
$w = HubOpening::termsWords($row);
check('…its words: robots allows, the pace, a page drawn by scripts, the list file, the terms quoted and cut at 400', str_contains($w, 'robots.txt allows it (one read every 2 s)') && str_contains($w, 'nothing without a browser') && str_contains($w, 'facilities.xlsx') && str_contains($w, 'Its terms page says: "Licensing') && str_contains($w, '…"'));
check('read within 30 days: not due again', HubOpening::termsDue($pdo) === []);

// a disallow, then a refusal, each raising the service's issue; robots never taken as permission when unreadable
foreach ([['disallowed', [], "doesn't allow us"], ['unreadable', [], "couldn't be read"], ['allowed', ['page_why' => 'refused', 'page_status' => 403], 'refused the read (403)']] as [$rb, $extra, $say]) {
    $pdo->exec("UPDATE authority_sources SET fetched_at = NULL WHERE id = $adhs"); EngineCase::$raised = [];
    HubOpening::termsRead($pdo, [['id' => $adhs, 'robots' => $rb] + $extra]);
    $iss = EngineCase::$raised[0] ?? null;
    check("$rb" . ($extra ? ' and refused' : '') . ": the ADHS issue raised, held for a person, the records-request step", $iss && $iss[1] === 'azdhs' && str_contains($iss[2], $say) && str_contains($iss[2], 'A.R.S. §39-121') && $iss[3]['host'] === 'azcarecheck.azdhs.gov');
    check('…and the Pulse words say the same', str_contains(HubOpening::termsWords($r()), $say));
}
check('a robots value it doesn\'t know is kept as unreadable', (function () use ($pdo, $adhs, $r) { $pdo->exec("UPDATE authority_sources SET fetched_at = NULL WHERE id = $adhs"); HubOpening::termsRead($pdo, [['id' => $adhs, 'robots' => 'maybe']]); return $r()['robots'] === 'unreadable'; })());

// once a person read the terms, a late read never overwrites what they saw
$pdo->exec("UPDATE authority_sources SET terms_read_at = UTC_TIMESTAMP(), robots = 'allowed', terms_fetch = '{\"text\":\"kept\"}' WHERE id = $adhs");
check('terms read by a person: a late read is refused, nothing changed', HubOpening::termsRead($pdo, [['id' => $adhs, 'robots' => 'disallowed']]) === 0 && $r()['robots'] === 'allowed' && HubOpening::termsDue($pdo) === []);
check('at most 3 items a post, a row without an id skipped', HubOpening::termsRead($pdo, [['robots' => 'allowed'], [], 'x']) === 0);

// the Pulse step carries the words for a reader service only
$pdo->exec("UPDATE authority_sources SET terms_read_at = NULL WHERE id = $adhs");
$steps = HubOpening::pulseSteps($pdo, fn ($t, $h, $a) => ['t' => $t, 'h' => $h, 'act' => $a]);
$mine = array_values(array_filter($steps, fn ($s) => str_contains((string) $s['t'], 'Health Services')));
$cc = array_values(array_filter($steps, fn ($s) => str_contains((string) $s['t'], 'Corporation Commission')));
check('Pulse: ADHS\'s step shows the crawler\'s read, with Terms read: allowed', $mine && str_contains($mine[0]['h'], 'Read by the crawler') && $mine[0]['act'][2] === 'Terms read: allowed');
check('…the Corporation Commission\'s step is as before (not a reader service)', $cc && !str_contains($cc[0]['h'], 'crawler'));

// the verdict (the session manager's ruling, 2026-11-28)
$V = fn (string $text, string $rb = 'allowed') => HubOpening::verdict($rb, ['text' => $text]);
check('verdict: a sentence forbidding automated access is NOT ALLOWED, the clause quoted', $V('Welcome. Use of automated scripts, robots or scrapers to access this site is prohibited. Thanks.') === ['not allowed', 'Use of automated scripts, robots or scrapers to access this site is prohibited.']);
check('…asking permission first is NOT ALLOWED', $V('You may not crawl this site without prior written permission.')[0] === 'not allowed');
check('…a sentence permitting it is ALLOWED, quoted', $V('Automated access to the public facility list is permitted at a reasonable rate.') === ['allowed', 'Automated access to the public facility list is permitted at a reasonable rate.']);
check('…a forbidding clause wins over a permitting one', $V('Bots are allowed on the news pages. Scraping the facility search is prohibited.')[0] === 'not allowed');
check('…nothing about automated reading is UNCLEAR (silence is never permission)', $V('These records are public. Contact us with questions.') === ['unclear', '']);
check('…a mention without yes or no is UNCLEAR, quoted', $V('Our robots policy is under review.') === ['unclear', 'Our robots policy is under review.']);
check('…no words at all: UNCLEAR', $V('') === ['unclear', '']);
check('…a block is NOT ALLOWED with its reason', $V('anything', 'disallowed')[0] === 'not allowed');
$pdo->exec("UPDATE authority_sources SET robots = 'allowed', fetched_at = UTC_TIMESTAMP(), terms_fetch = '{\"text\":\"Scraping this site is prohibited.\",\"page_status\":200}' WHERE id = $adhs");
$w = HubOpening::termsWords($r());
check('…the Pulse words start with the verdict, the terms URL and the clause', str_starts_with($w, 'Terms: NOT ALLOWED (https://www.azdhs.gov/licensing/index.php says: "Scraping this site is prohibited.")'));

$pdo->exec('DROP DATABASE w14_test');
array_map('unlink', glob("$lib/*.php")); @rmdir($lib);
echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
