<?php
// Builds that fail their checks on staging withdraw themselves (Jason, 2026-11-18: "having to require me to remove bad
// releases is not helpful. Since you create them, you need a way to deal with issues"). Tests Releases::selfWithdraw, the
// job's sweep, "replaces" in a fixed build's release.json, and Withdraw putting staging back.
// Run: mariadb -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main' && php self-withdraw.php <root>/app
// (the root: the mirror with the release laid over it; .env four folders up from lib/; no GITHUB_DOCS_TOKEN, so records
// aren't written to git).
declare(strict_types=1);
$app = $argv[1];
require $app . '/lib/Database.php'; require $app . '/lib/Releases.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
Releases::ensure($pdo);
$pdo->exec('CREATE TABLE IF NOT EXISTS staging_state (k VARCHAR(40) PRIMARY KEY, v MEDIUMTEXT)');
$pdo->exec('CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, email VARCHAR(190))');

// a staging site in a temporary folder, laid out as on the server
$base = sys_get_temp_dir() . '/tv-stg-' . getmypid(); $sroot = $base . '/public_html/traversence';
@mkdir($sroot . '/app/migrations', 0777, true); @mkdir($sroot . '/app/lib', 0777, true); file_put_contents($base . '/.env', "X=1\n");
$pdo->prepare('REPLACE INTO staging_state (k, v) VALUES (?, ?)')->execute(['setup', json_encode(['base' => $base])]);
$live = dirname($app);   // the live root: app/ beside website/

$make = function (string $zip, string $state, ?bool $ok, bool $git, array $files, string $first = 'Migrations ran on staging: 1064 near stored') use ($pdo): int {
    $log = [['at' => gmdate('c'), 'by' => 0, 'msg' => 'Uploaded ' . $zip . '.']];
    if ($git) $log[] = ['at' => gmdate('c'), 'by' => 0, 'msg' => 'Came from git: releases/inbox/' . $zip . ' (git abc).'];
    $pdo->prepare("INSERT INTO site_releases (title, why, proposals, switch_key, zip_name, tz, state, created_by, created_at, updated_at, files, checks, checks_ok, log, staged_at) VALUES (?, '', '[]', '', ?, '', ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?, ?, ?, UTC_TIMESTAMP())")
        ->execute(['T ' . $zip, $zip, $state, json_encode($files), json_encode(['ok' => (bool) $ok, 'first_fail' => $first]), $ok === null ? null : (int) $ok, json_encode($log)]);
    return (int) $pdo->lastInsertId();
};
$state = fn (int $id) => (string) $pdo->query('SELECT state FROM site_releases WHERE id = ' . $id)->fetchColumn();
$lastNote = function (int $id) use ($pdo) { $l = json_decode((string) $pdo->query('SELECT log FROM site_releases WHERE id = ' . $id)->fetchColumn(), true); return (string) end($l)['msg']; };

// a failed build from git, with a migration and a changed file on staging
file_put_contents($sroot . '/app/migrations/2026-11-18_control_ledger.sql', 'CREATE TABLE broken (stored INT);');
file_put_contents($sroot . '/app/lib/Releases.php', '<?php // the failed build\'s copy');
@mkdir($sroot . '/app/lib/new', 0777, true); file_put_contents($sroot . '/app/lib/new/Brand.php', '<?php // a new file');
$a = $make('control-board-2a.zip', 'staged', false, true, ['app/migrations/2026-11-18_control_ledger.sql' => [], 'app/lib/Releases.php' => [], 'app/lib/new/Brand.php' => []]);
$w = Releases::selfWithdraw($pdo, (array) Releases::get($pdo, $a));
$t('a build from git that failed on staging withdraws itself', $state($a) === 'withdrawn' && str_contains($w, 'withdrawn') === false && str_starts_with($w, 'Release #' . $a), [$state($a), $w]);
$t('…its record says why, and that nothing on live changed', str_contains($lastNote($a), 'Withdrawn by itself: its checks on staging failed (Migrations ran on staging: 1064 near stored)') && str_contains($lastNote($a), 'Nothing on the live site changed'), $lastNote($a));
$t('…its migration is off staging, so nothing waits behind it', !is_file($sroot . '/app/migrations/2026-11-18_control_ledger.sql'));
$t('…its changed file has live\'s copy back on staging', file_get_contents($sroot . '/app/lib/Releases.php') === file_get_contents($live . '/app/lib/Releases.php'));
$t('…its new file is off staging', !is_file($sroot . '/app/lib/new/Brand.php'));
$t('…and the note says what was put back', str_contains($lastNote($a), '1 file back to live\'s copy on staging') && str_contains($lastNote($a), '2 taken off staging'), $lastNote($a));

// what isn't withdrawn
$p = $make('jasons.zip', 'staged', false, false, []);
$t('a zip a person uploaded stays for them', Releases::selfWithdraw($pdo, (array) Releases::get($pdo, $p)) === '' && $state($p) === 'staged');
$n = $make('not-checked.zip', 'staged', null, true, []);
$t('a build not checked yet stays', Releases::selfWithdraw($pdo, (array) Releases::get($pdo, $n)) === '' && $state($n) === 'staged');
$g = $make('good.zip', 'staged', true, true, []);
$t('a build whose checks passed stays', Releases::selfWithdraw($pdo, (array) Releases::get($pdo, $g)) === '' && $state($g) === 'staged');

// the job sweeps a failed build already sitting on staging (like #121)
$old = $make('control-board-2a-old.zip', 'staged', false, true, []);
$out = Releases::tick($pdo);
$t('the 10-minute job withdraws a failed build left on staging', $state($old) === 'withdrawn' && (bool) array_filter($out, fn ($l) => str_starts_with($l, 'Release #' . $old)), $out);
$t('…and leaves the rest', $state($p) === 'staged' && $state($n) === 'staged' && $state($g) === 'staged');

// a fixed build names what it replaces
$r1 = $make('fix-me.zip', 'staged', true, true, []);
$r2 = $make('fix-me-2.zip', 'planned', null, true, []);
$m = new ReflectionMethod('Releases', 'replaceEarlier'); $m->setAccessible(true);
$out = $m->invoke(null, $pdo, $r2, ['fix-me']);
$t('"replaces": the earlier build (by zip name) is withdrawn, naming the fix', $state($r1) === 'withdrawn' && str_contains($lastNote($r1), 'Replaced by release #' . $r2) && $out === ['Release #' . $r1 . ' withdrawn: replaced by #' . $r2 . '.'], [$state($r1), $out]);
$t('…the fix itself isn\'t touched', $state($r2) === 'planned');
$r3 = $make('by-number.zip', 'staged', true, true, []);
$m->invoke(null, $pdo, $r2, ['#' . $r3]);
$t('…or by its release number', $state($r3) === 'withdrawn');
$liveOne = $make('was-live.zip', 'live', true, true, []);
$m->invoke(null, $pdo, $r2, ['was-live']);
$t('a live release is never withdrawn by a fix (it rolls back instead)', $state($liveOne) === 'live');

// a person's Withdraw puts staging back too
file_put_contents($sroot . '/app/lib/Releases.php', '<?php // a person\'s build');
$h = $make('by-hand.zip', 'staged', true, false, ['app/lib/Releases.php' => []]);
$act = Releases::act($pdo, $h, 'withdraw', [], 0);
$t('a person\'s Withdraw puts live\'s copy back on staging and says so', $state($h) === 'withdrawn' && file_get_contents($sroot . '/app/lib/Releases.php') === file_get_contents($live . '/app/lib/Releases.php') && str_contains($act, 'back to live'), $act);
// a file a later release on staging carries stays
file_put_contents($sroot . '/app/lib/Releases.php', '<?php // the later build');
$e = $make('earlier.zip', 'staged', true, false, ['app/lib/Releases.php' => []]);
$l2 = $make('later.zip', 'staged', true, false, ['app/lib/Releases.php' => []]);
Releases::act($pdo, $e, 'withdraw', [], 0);
$t('a file a later release on staging carries keeps that release\'s copy', file_get_contents($sroot . '/app/lib/Releases.php') === '<?php // the later build' && str_contains($lastNote($e), 'kept for a later release'), $lastNote($e));

exec('rm -rf ' . escapeshellarg($base));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
