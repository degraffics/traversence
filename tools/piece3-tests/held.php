<?php
// Piece 3, P3-5: the held names' locked read (Jason, 2026-11-25, Brief B: "a locked read against live-protected places rather
// than adding fake test records") on a real MySQL or MariaDB: HeldNames' hashes and /api/held-hashes.php served by php -S.
// Held (waiting, hidden, closed) names as hashes only; a name a visible or story-only landmark shares left out; one-word
// names left out; staging only; the checks' token, a wrong or missing one refused; GET only; never a name in the answer.
// Run: php held.php <root>   (<root>/app and <root>/website; its .env four folders above app/lib; resets lv_main)
declare(strict_types=1);
$src = rtrim((string) realpath($argv[1] ?? ''), '/'); if (!is_dir($src . '/app/lib')) { fwrite(STDERR, "usage: php held.php <root>\n"); exit(2); }
// a copy, so staging's viewing password (.htpasswd, beside the .env) is the test's own
$tmp = sys_get_temp_dir() . '/held-' . getmypid(); @mkdir($tmp . '/w', 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($tmp . '/w/root'));
copy(dirname($src, 2) . '/.env', $tmp . '/.env'); file_put_contents($tmp . '/.htpasswd', "viewer:" . password_hash('view-pass', PASSWORD_BCRYPT) . "\n");
$root = $tmp . '/w/root';
require_once $root . '/app/lib/Database.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$pdo->exec("CREATE TABLE landmarks (gnis_id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200), status VARCHAR(20))");
$pdo->exec("CREATE TABLE " . Database::t('landmark_loads') . " (id INT AUTO_INCREMENT PRIMARY KEY)");
$pdo->exec("INSERT INTO landmarks (name, status) VALUES ('Zz Burial Ground', 'waiting'), ('Zz Shrine Spring', 'closed'), ('Zz Hidden Copy Arch', 'hidden'), ('Zz Hidden Copy Arch', 'shown'),
    ('Zz Story Kiva Ruins', 'waiting'), ('Zz Story Kiva Ruins', 'story_only'), ('Petroglyphs', 'waiting'), ('Zz Public Arch', 'shown'), ('Zz Approved Falls', 'approved')");
require_once $root . '/app/lib/HeldNames.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$K = 'held-test-token';
$h = HeldNames::hashes($pdo, $K);
$want = [HeldNames::hash('Zz Burial Ground', $K), HeldNames::hash('Zz Shrine Spring', $K)]; sort($want);
$t('held: waiting and closed names, as hashes', $h['hashes'] === $want, $h);
$t('… a hidden name a shown landmark shares, and one a story-only place tells, are left out', !in_array(HeldNames::hash('Zz Hidden Copy Arch', $K), $h['hashes'], true) && !in_array(HeldNames::hash('Zz Story Kiva Ruins', $K), $h['hashes'], true));
$t('… a one-word name is left out and counted', $h['skipped_one_word'] === 1);
$t('… shown and approved names never', !in_array(HeldNames::hash('Zz Public Arch', $K), $h['hashes'], true));
$t('… another key, other hashes (keyed: the list says nothing without the token)', HeldNames::hashes($pdo, 'other')['hashes'] !== $h['hashes'] && HeldNames::hashes($pdo, '')['hashes'] === []);
$t('the read goes through Landmarks::visibleSql', str_contains((string) file_get_contents($root . '/app/lib/HeldNames.php'), 'Landmarks::visibleSql()'));
// the endpoint, served
$serve = function (bool $staging, string $token) use ($root) {
    $port = 9300 + random_int(0, 80);
    $env = 'HEALTH_TOKEN=' . escapeshellarg($token) . ' STAGING=' . ($staging ? '1' : '0');
    $pid = (int) exec("$env php -S 127.0.0.1:$port -t " . escapeshellarg($root . '/website') . ' > /dev/null 2>&1 & echo $!');
    usleep(500000); return [$port, $pid];
};
$get = function (int $port, array $hdr = [], string $method = 'GET') {
    $c = curl_init("http://127.0.0.1:$port/api/held-hashes.php"); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => array_merge(['Authorization: Basic ' . base64_encode('viewer:view-pass')], $hdr), CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20]);
    $b = (string) curl_exec($c); $s = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c); return [$s, $b];
};
[$p, $pid] = $serve(true, $K);
[$s, $b] = $get($p, ['X-Check-Token: ' . $K]); $j = json_decode($b, true);
$t('on staging with the checks\' token: 200, the hashes', $s === 200 && ($j['hashes'] ?? null) === $want, [$s, $b]);
$t('… and never a name in the answer', !preg_match('/zz|burial|shrine|spring|ground/i', $b), $b);
[$s, $b] = $get($p, ['X-Check-Token: wrong-token']); $t('a wrong token: refused (401), no hashes', $s === 401 && !str_contains($b, 'hashes'), [$s, $b]);
[$s, $b] = $get($p, []); $t('no token: refused (401)', $s === 401);
[$s, $b] = $get($p, ['X-Check-Token: ' . $K], 'POST'); $t('not GET: refused (405)', $s === 405, [$s, $b]);
exec("kill $pid");
[$p, $pid] = $serve(false, $K);
[$s, $b] = $get($p, ['X-Check-Token: ' . $K]); $t('on the live site, even with the token: nothing (404)', $s === 404 && !str_contains($b, 'hashes'), [$s, $b]);
exec("kill $pid");
[$p, $pid] = $serve(true, '');
[$s, $b] = $get($p, ['X-Check-Token: ']); $t('no HEALTH_TOKEN set: off (503)', $s === 503, [$s, $b]);
exec("kill $pid");
$src = (string) file_get_contents($root . '/website/api/held-hashes.php') . file_get_contents($root . '/app/lib/HeldNames.php');
$t('no outside call, no write (StagingGate has nothing to hold; no table, R14)', !preg_match('/curl_|file_get_contents\s*\(\s*[\'"]https?:|\b(INSERT|UPDATE|DELETE|CREATE)\b/', $src));
exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
