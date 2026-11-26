<?php
// A release's record in git that didn't save (Release #301, 2026-11-26: published at 10:01, but "Release #301: Live" never
// reached git). DocsRepo::saveRecord against a fake GitHub (DocsRepo::$fake, cli with TV_TEST only): a stale read refused
// as 409 is read and written again; three refusals or another error say so; Releases::recordBehind, the 10-minute job's
// step, saves a record that's behind and leaves one that matches; the card's line says when it's still behind.
// On the bench's MySQL or MariaDB (it makes site_releases and site_release_settings if missing; its rows use ids 9301+).
//   php tools/release-tests/record.php <app folder>
$app = rtrim($argv[1] ?? '', '/');
putenv('TV_TEST=1'); putenv('GITHUB_DOCS_TOKEN=test-token-not-real');
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DocsRepo.php'; require_once $app . '/lib/Releases.php';
$pdo = Database::connection(); Releases::ensure($pdo);
$fails = 0; $n = 0;
function ok(bool $c, string $what): void { global $fails, $n; $n++; echo ($c ? '  ok  ' : 'FAIL  ') . $what . "\n"; if (!$c) $fails++; }

// a fake GitHub: files by path, each with a sha; $stale reads give the previous sha (as #301's second write met)
$files = []; $stale = 0; $calls = []; $putAnswer = null;
DocsRepo::$fake = function ($m, $url, $json) use (&$files, &$stale, &$calls, &$putAnswer) {
    $calls[] = $m; $path = preg_replace('#^.*/contents/|\?.*$#', '', $url);
    if ($m === 'GET') {
        if (!isset($files[$path])) return [404, '{"message":"Not Found"}'];
        $f = $files[$path]; $sha = $stale > 0 ? ($f['prev'] ?? $f['sha']) : $f['sha']; if ($stale > 0) $stale--;
        return [200, json_encode(['sha' => $sha, 'content' => base64_encode($stale >= 0 && $sha !== $f['sha'] ? ($f['prevText'] ?? '') : $f['text'])])];
    }
    if ($putAnswer !== null) return $putAnswer;
    $cur = $files[$path]['sha'] ?? null;
    if ($cur !== null && ($json['sha'] ?? null) !== $cur) return [409, '{"message":"is at ' . $cur . ' but expected ' . ($json['sha'] ?? '') . '"}'];
    $text = base64_decode($json['content']); $new = substr(sha1($text . microtime()), 0, 40);
    $files[$path] = ['sha' => $new, 'text' => $text, 'prev' => $cur, 'prevText' => $files[$path]['text'] ?? ''];
    return [201, json_encode(['commit' => ['html_url' => 'https://github.com/x/commit/' . $new]])];
};
$P = 'deliveries/releases/2026-11-26-9301-test.json';

// 1. a first write, then a second one whose read is stale: refused once, read again, written
DocsRepo::saveRecord($P, "{\"state\":\"planned\"}\n", 'Release #9301: Planned');
$r1 = DocsRepo::saveRecord($P, "{\"state\":\"staged\"}\n", 'Release #9301: On staging');
ok(!empty($r1['ok']) && ($files[$P]['text'] ?? '') === "{\"state\":\"staged\"}\n", 'a new record is written');
$stale = 1; $calls = [];
$r2 = DocsRepo::saveRecord($P, "{\"state\":\"live\"}\n", 'Release #9301: Live');
ok(!empty($r2['ok']) && ($r2['tries'] ?? 0) === 2, 'a stale read refused as 409 is read again and written on the second try (#301\'s case)');
ok(($files[$P]['text'] ?? '') === "{\"state\":\"live\"}\n", 'and git holds the Live record');
ok($calls === ['GET', 'PUT', 'GET', 'PUT'], 'read, write (refused), read, write: ' . implode(' ', $calls));

// 2. the same text again: nothing written
$calls = []; $r3 = DocsRepo::saveRecord($P, "{\"state\":\"live\"}\n", 'Release #9301: Live');
ok(!empty($r3['ok']) && $calls === ['GET'], 'a record that already matches is left alone (one read, no write)');

// 3. refused three times: says so, in words for records
$stale = 99; $r4 = DocsRepo::saveRecord($P, "{\"state\":\"off\"}\n", 'Release #9301: Off'); $stale = 0;
ok(empty($r4['ok']) && ($r4['tries'] ?? 0) === 3 && str_contains((string) $r4['error'], 'three tries'), 'three stale refusals stop and say so: ' . ($r4['error'] ?? ''));
// 4. another error stops at once, with its own words
$putAnswer = [401, '{"message":"Bad credentials"}']; $calls = [];
$r5 = DocsRepo::saveRecord($P, "{\"state\":\"x\"}\n", 'x'); $putAnswer = null;
ok(empty($r5['ok']) && $calls === ['GET', 'PUT'] && str_contains((string) $r5['error'], 'GITHUB_DOCS_TOKEN'), 'a refused key stops at once and names the key');

// 5. Releases: a release whose record never saved (as #301) is saved by the 10-minute step, then left alone
$pdo->exec("DELETE FROM site_releases WHERE id >= 9301"); $pdo->exec("DELETE FROM site_release_settings WHERE k LIKE 'rec:93%'");
$log = json_encode([['at' => '2026-11-26T10:01:48+00:00', 'by' => 0, 'msg' => 'Put on staging (4 files); its checks passed.'], ['at' => '2026-11-26T10:01:50+00:00', 'by' => 0, 'msg' => 'Published: 4 files; everyone at once (no switch).']]);
$pdo->prepare("INSERT INTO site_releases (id, title, state, zip_name, files, checks, log, created_at, updated_at, live_at) VALUES (9301, 'Test release', 'live', 'test.zip', '{}', '{}', ?, '2026-11-26 10:01:44', NOW(), NOW())")->execute([$log]);
$files = []; $calls = [];
$out = Releases::recordBehind($pdo);
$recPath = array_keys($files)[0] ?? '';
ok(count($out) === 1 && str_contains($out[0], 'caught up (Live)'), 'the 10-minute step saves a record that was never kept: ' . ($out[0] ?? 'nothing'));
ok(str_contains($recPath, '-9301-') && str_contains($files[$recPath]['text'] ?? '', '"state": "live"'), 'git now holds the release as Live');
$calls = []; $out2 = Releases::recordBehind($pdo);
ok($out2 === [] && $calls === [], 'a record that matches is left alone the next time (no call to GitHub)');
ok(Releases::recordLine($pdo, 9301) === '', 'its card says nothing about its record');

// 6. one that won't save: remembered, its card says so, and the next step tries again
$pdo->prepare("UPDATE site_releases SET log = ?, updated_at = NOW() WHERE id = 9301")->execute([json_encode(array_merge(json_decode($log, true), [['at' => '2026-11-26T10:20:00+00:00', 'by' => 0, 'msg' => 'Rolled back.']]))]);
$putAnswer = [401, '{"message":"Bad credentials"}'];
$out3 = Releases::recordBehind($pdo);
ok(count($out3) === 1 && str_contains($out3[0], 'still behind'), 'a record that won\'t save is said to be behind: ' . ($out3[0] ?? ''));
$line = Releases::recordLine($pdo, 9301);
ok(str_contains($line, "Its record in git didn't save") && str_contains($line, 'every 10 minutes'), 'its card says so, and when it is tried again: ' . $line);
$putAnswer = null;
$out4 = Releases::recordBehind($pdo);
ok(count($out4) === 1 && str_contains($out4[0], 'caught up') && Releases::recordLine($pdo, 9301) === '', 'the next step saves it and the card\'s line goes');

// 7. never from staging, never without the key
putenv('GITHUB_DOCS_TOKEN'); ok(Releases::recordBehind($pdo) === [], 'with no GitHub key, the step does nothing');

$pdo->exec("DELETE FROM site_releases WHERE id >= 9301"); $pdo->exec("DELETE FROM site_release_settings WHERE k LIKE 'rec:93%'");
echo "\n" . ($n - $fails) . " of $n checks pass\n"; exit($fails ? 1 : 0);
