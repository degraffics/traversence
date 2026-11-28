<?php
// tools/pause-tests/pause.php — the pause on Jason's call, 2026-11-28 ("Pause it. Yes"; handoffs/pause-1a): auto-publish off
// while AutoImport::PAUSED holds words, whatever .env says, and back on when it's emptied (the way back); the reason shown
// where Auto-import is; every automatic publish passes through enabled() (tryImport, advance); the ingest endpoint
// refuses before it reads a record. Run: php tools/pause-tests/pause.php <build root> <mirror's sync-app/lib>
declare(strict_types=1);

[$root, $live] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/')];
if (!is_file("$root/app/lib/crawler/AutoImport.php") || !is_dir($live)) { fwrite(STDERR, "usage: php pause.php <build root> <sync-app/lib>\n"); exit(2); }
$fails = 0;
function check(string $n, bool $ok): void { global $fails; echo ($ok ? '  ok  ' : 'FAIL  ') . $n . "\n"; if (!$ok) $fails++; }

// a copy of the live lib with the build's AutoImport over it, loaded in a child php (one with the pause, one emptied)
$tmp = sys_get_temp_dir() . '/pause-' . getmypid();
@mkdir($tmp, 0777, true); exec('cp -r ' . escapeshellarg($live) . ' ' . escapeshellarg("$tmp/lib"));
$src = file_get_contents("$root/app/lib/crawler/AutoImport.php");
$ask = function (string $code, string $auto) use ($tmp): string {
    $f = "$tmp/ask.php";
    file_put_contents($f, '<?php putenv("AUTO_IMPORT=' . $auto . '"); require "' . $tmp . '/lib/Database.php"; require "' . $tmp . '/lib/crawler/AutoImport.php"; ' . $code);
    return trim((string) shell_exec('php ' . escapeshellarg($f) . ' 2>&1'));
};
file_put_contents("$tmp/lib/crawler/AutoImport.php", $src);
check('paused: auto-publish is off even with AUTO_IMPORT=on', $ask('echo AutoImport::enabled() ? "on" : "off";', 'on') === 'off');
check('…its reason quotes Jason and names the review', str_contains($ask('echo AutoImport::pausedWhy();', 'on'), 'Paused on Jason\'s call 2026-11-28: "Pause it. Yes"') && str_contains($ask('echo AutoImport::pausedWhy();', 'on'), 'data-capture-review-2026-12-02.md'));
$empty = preg_replace("/public const PAUSED = '(?:[^'\\\\]|\\\\.)*';/", "public const PAUSED = '';", $src, 1, $n);
check('the way back: the constant can be emptied in one line', $n === 1);
file_put_contents("$tmp/lib/crawler/AutoImport.php", $empty);
check('…emptied, auto-publish follows .env again: on', $ask('echo AutoImport::enabled() ? "on" : "off";', 'on') === 'on');
check('…and off when .env says off, with no pause reason', $ask('echo (AutoImport::enabled() ? "on" : "off") . "|" . var_export(AutoImport::pausedWhy(), true);', 'off') === 'off|NULL');
exec('rm -rf ' . escapeshellarg($tmp));

// every automatic publish asks enabled() first
preg_match('/public static function tryImport\(.*?\n    \}\n/s', $src, $t);
check('tryImport: enabled() before evaluate and approve', isset($t[0]) && strpos($t[0], 'self::enabled()') < strpos($t[0], 'IntakeStager::approve('));
preg_match('/public static function advance\(.*?\n    \}\n/s', $src, $a);
check('advance (listings_tick): enabled() before tryImport', isset($a[0]) && strpos($a[0], 'self::enabled()') !== false && strpos($a[0], 'self::enabled()') < strpos($a[0], 'self::tryImport('));
check('obvious merges (filling a listed one\'s gaps) still run: they come before the gate, as before', isset($t[0]) && strpos($t[0], 'self::obvious(') < strpos($t[0], 'self::enabled()'));
$ing = file_get_contents("$root/website/api/ingest.php");
check('ingest.php asks the pause before any record is written', strpos($ing, 'AutoImport::pausedWhy()') !== false && strpos($ing, 'AutoImport::pausedWhy()') < strpos($ing, 'upsertEntity('));
$dash = file_get_contents("$root/app/ui/admin-dashboard.php");
check('the dashboard reads AutoImport::enabled() (not .env alone) and shows the pause\'s reason', str_contains($dash, '$auto = AutoImport::enabled();') && str_contains($dash, 'AutoImport::pausedWhy()') && !str_contains($dash, "getenv('AUTO_IMPORT')"));
check('Auto-imports page shows the pause\'s reason, not ".env"', str_contains(file_get_contents("$root/website/admin/auto-imports.php"), 'AutoImport::pausedWhy()'));


// ---- ingest.php while paused (the CEO, 2026-11-28): success "held for review", each record staged with a hold, nothing
// written through upsertEntity. The real endpoint, run with stand-ins for what it calls (each recorded).
$t = sys_get_temp_dir() . '/ingest-' . getmypid(); @mkdir("$t/app/lib/crawler", 0777, true); @mkdir("$t/website/api", 0777, true);
copy("$root/website/api/ingest.php", "$t/website/api/ingest.php");
file_put_contents("$t/app/bootstrap.php", '<?php final class Response { static function requireMethod($m) {} static function jsonBody() { return json_decode(getenv("BODY"), true); } static function error($m, $c, $k) { echo json_encode(["error" => $m, "code" => $c]); exit; } static function success($d, $c = 200) { echo json_encode(["ok" => $d, "code" => $c]); exit; } } final class Database { static function connection() { return new class { function prepare($q) { return new class { function execute($a = []) {} function fetchColumn() { return 1; } }; } }; } static function transaction($f) { return $f(new PDO("sqlite::memory:")); } }');
file_put_contents("$t/app/lib/CrawlerIngest.php", '<?php final class CrawlerIngest { static function normalizeZip($z) { return substr(preg_replace("/\\D/", "", (string) $z), 0, 5); } static function upsertEntity($p, $r, $g, $c) { file_put_contents(getenv("LOG"), "upsert\n", FILE_APPEND); return ["action" => "created", "entity_id" => 1, "composite_hash" => "h"]; } }');
file_put_contents("$t/app/lib/ListingIdentity.php", '<?php final class ListingIdentity { static function locked($p, $z, $f) { return $f(); } }');
file_put_contents("$t/app/lib/crawler/IntakeStager.php", '<?php final class IntakeStager { static function createSource($p, $u, $pu, $n, $id) { return 7; } static function stage($p, $sid, $c) { file_put_contents(getenv("LOG"), "stage " . json_encode($c) . "\n", FILE_APPEND); return ["staged" => $c, "auto_imported" => []]; } }');
file_put_contents("$t/app/lib/crawler/CrawlQueue.php", '<?php final class CrawlQueue { static function normalize($c) { return ($c["name"] ?? "") !== "" && preg_match("/^\\d{5}$/", $c["where"]["zip"] ?? "") ? $c : null; } }');
$runIngest = function (bool $paused) use ($t): array {
    file_put_contents("$t/app/lib/crawler/AutoImport.php", '<?php final class AutoImport { static function pausedWhy() { return ' . ($paused ? '"Paused on Jason\'s call"' : 'null') . '; } }');
    $log = "$t/log"; @unlink($log); touch($log);
    $body = json_encode([['name' => 'Test Clinic', 'zip' => '85901', 'phone' => '9285550100'], ['name' => '', 'zip' => '85901']]);
    $out = shell_exec('cd ' . escapeshellarg("$t/website/api") . ' && REQUEST_METHOD=POST CRAWLER_API_TOKEN=tok BODY=' . escapeshellarg($body) . ' LOG=' . escapeshellarg($log) . ' DEFAULT_GEO_HUB_SLUG=x php -d variables_order=EGPCS -r ' . escapeshellarg('$_SERVER["HTTP_AUTHORIZATION"] = "Bearer tok"; include "ingest.php";') . ' 2>&1');
    return [json_decode((string) $out, true) ?: ['raw' => $out], (string) file_get_contents($log)];
};
[$o, $log] = $runIngest(true);
check('ingest paused: answers success, "held for review", 1 of 2 records held (the nameless one skipped)', ($o['ok']['status'] ?? '') === 'held for review' && ($o['code'] ?? 0) === 200 && ($o['ok']['held_for_review'] ?? -1) === 1 && ($o['ok']['processed'] ?? 0) === 2);
check('…the record is staged for Review, with a hold, and nothing is written as a listing', str_contains($log, 'stage ') && str_contains($log, '"hold":"api\/ingest.php while paused"') && !str_contains($log, 'upsert'));
[$o, $log] = $runIngest(false);
check('ingest not paused: as before, through upsertEntity, nothing staged', str_contains($log, 'upsert') && !str_contains($log, 'stage '));
exec('rm -rf ' . escapeshellarg($t));

// ---- About text and hours held for Review, never written into a published listing by the system (the IT Manager's gap)
$ai = file_get_contents("$root/app/lib/crawler/AutoImport.php");
preg_match('/public static function fillGaps\(.*?\n    \}\n/s', $ai, $fg);
check('fillGaps: with no person and the pause on, description and hours are taken out of what it writes', isset($fg[0]) && preg_match('/\$adminId === null && self::pausedWhy\(\) !== null\).*?array_intersect\(array_keys\(\$choices\), \[\'description\', \'hours\'\]\).*?array_diff_key\(\$choices/s', $fg[0]) === 1);
check('…and held as a suggestion for Review, before the merge writes', isset($fg[0]) && strpos($fg[0], 'self::holdForReview(') < strpos($fg[0], 'IntakeStager::merge('));
check('…a person\'s own merge (an admin id) is untouched: the hold asks $adminId === null', isset($fg[0]) && str_contains($fg[0], '$adminId === null && self::pausedWhy()'));
$rf = file_get_contents("$root/app/lib/crawler/Refresh.php");
check('Refresh: the website\'s description and hours held for Review while paused, phone and offerings still filled', preg_match("/in_array\(\\\$key, \['description', 'hours'\], true\) && AutoImport::pausedWhy\(\) !== null\) \{ \\\$held\[\\\$key\] = \\\$value; continue; \}/", $rf) === 1 && str_contains($rf, "AutoImport::holdForReview(\$pdo, \$entityId, \$held, 'crawler-refresh-paused'"));
check('…Common Crawl\'s copy\'s description held too, before it would be written', strpos($rf, "AutoImport::pausedWhy() !== null) {\n                AutoImport::holdForReview(") !== false && strpos($rf, 'AutoImport::holdForReview($pdo, $entityId, [\'description\' => $d]') < strpos($rf, "CrawlerIngest::upsertMetadataKey(\$pdo, \$entityId, 'description', \$d"));

// holdForReview itself, on SQLite: one suggestion, the fields as text, one open at a time, nothing when there's nothing
$t = sys_get_temp_dir() . '/hold-' . getmypid(); @mkdir("$t/lib/crawler", 0777, true);
file_put_contents("$t/lib/crawler/AutoImport.php", $ai);
foreach (['IntakeStager', 'ListingExtractor'] as $c) file_put_contents("$t/lib/crawler/$c.php", '<?php final class ' . $c . ' {}');
foreach (['ListingSafety', 'TrustScore', 'Identity', 'WebIdentity', 'Aliases', 'Database'] as $c) file_put_contents("$t/lib/$c.php", '<?php final class ' . $c . ' {}');
file_put_contents("$t/lib/Suggestions.php", '<?php final class Suggestions { static function create($pdo, $in, $u, $d) { $pdo->prepare("INSERT INTO suggestions (entity_id, kind, status, device_hash, fields, note) VALUES (?, ?, \'new\', ?, ?, ?)")->execute([$in["entity_id"], $in["kind"], $d, json_encode($in["fields"]), $in["note"]]); return 1; } }');
file_put_contents("$t/run.php", '<?php require "' . $t . '/lib/crawler/AutoImport.php"; $pdo = new PDO("sqlite::memory:"); $pdo->exec("CREATE TABLE suggestions (id INTEGER PRIMARY KEY, entity_id INT, kind TEXT, status TEXT, device_hash TEXT, fields TEXT, note TEXT)");
$a = AutoImport::holdForReview($pdo, 5, ["description" => "Board certified specialists in Lakeside.", "hours" => ["Mon-Fri 8-5", "Sat 9-1"]], "crawler-refresh-paused", "Its own website gives");
$b = AutoImport::holdForReview($pdo, 5, ["description" => "Again"], "crawler-refresh-paused", "x");
$c = AutoImport::holdForReview($pdo, 6, ["description" => "", "hours" => []], "crawler-merge", "x");
$r = $pdo->query("SELECT * FROM suggestions")->fetchAll(PDO::FETCH_ASSOC); echo json_encode([$a, $b, $c, $r]);');
$j = json_decode((string) shell_exec('php ' . escapeshellarg("$t/run.php") . ' 2>&1'), true);
check('holdForReview: one suggestion (kind fix), the hours as one line, the note naming the pause', is_array($j) && $j[0] === true && count($j[3]) === 1 && $j[3][0]['kind'] === 'fix' && json_decode($j[3][0]['fields'], true)['hours'] === 'Mon-Fri 8-5; Sat 9-1' && str_contains($j[3][0]['note'], 'Held for a person while the pause holds'));
check('…one open at a time per listing and source; nothing asked for empty values', is_array($j) && $j[1] === false && $j[2] === false);
exec('rm -rf ' . escapeshellarg($t));

echo $fails ? "$fails failed\n" : "all passed\n";
exit($fails ? 1 : 0);
