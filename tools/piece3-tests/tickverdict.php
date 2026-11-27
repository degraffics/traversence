<?php
// The release pipeline's tick and verdict (2026-11-27, the session manager's release "release pipeline: tick and verdict"):
// #317 and #318 each waited an hour on the way to staging with their before verdict on main. (c) the verdict is read
// through GitHub's contents API (DocsRepo::now), never raw.githubusercontent.com's cache; a missing file is said once on
// the release's card past NOTE_MINUTES. (b) /api/releases-pull.php, which beforeafter.yml now calls right after it commits
// a verdict, moves a waiting release on (run for real in its own process). One advance at a time (a lock). (a) releases_tick's
// own cron line goes to every 10 minutes, once, and a person's later Change stays.
// Run: php tickverdict.php <root>   (<root> holds app/ and website/; it resets lv_main; its setup is pipeline.php's)
declare(strict_types=1);
$root = rtrim($argv[1] ?? '', '/'); $app = $root . '/app'; if (!is_dir($app . '/lib') || !is_file($root . '/website/api/releases-pull.php')) { fwrite(STDERR, "usage: php tickverdict.php <root>\n"); exit(2); }
putenv('TV_TEST=1');
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$T = dirname(__DIR__) . '/team-tests'; $H = dirname(__DIR__, 2) . '/handoffs/';
$run($T . '/before-schema.sql');
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql', $H . 'piece2-build/team-tickets/build/app/migrations/2026-11-25_tickets.sql', $H . 'piece2-build/team-runner/build/app/migrations/2026-11-25_team_flag.sql'] as $f) $run($f);
$pdo->exec("CREATE TABLE feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255), updated_by INT NULL, updated_at DATETIME)");
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, email VARCHAR(190) NOT NULL DEFAULT '', role VARCHAR(20) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'active')");
require_once $app . '/lib/Staging.php'; require_once $app . '/lib/Switches.php'; require_once $app . '/lib/Releases.php'; require_once $app . '/lib/BeforeAfter.php'; require_once $app . '/lib/GaugeTasks.php'; require_once $app . '/lib/DocsRepo.php';
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true);
$stage = function (bool $on) use ($here) { putenv('STAGING=' . ($on ? '1' : '0')); $here->setValue(null, null); };
$stage(false);
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$asks = [];
BeforeAfter::$askFn = function ($e, $p) use (&$asks) { $asks[] = [$e, $p]; return ''; };
$mk = function (string $name) use ($pdo) {
    $z = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(), 'tv') . '.zip'; $z->open($tmp, ZipArchive::CREATE);
    $z->addFromString('release.json', json_encode(['title' => $name])); $z->addFromString('app/lib/TvTest' . md5($name) . '.php', "<?php\n// a test file\n"); $z->close();
    $id = (int) Releases::fromUpload($pdo, 0, $tmp, $name, '')['id']; @unlink($tmp);
    Releases::noteFromCheck($pdo, $id, 'Came from git: releases/inbox/' . $name . ' (git 0123456789).');
    return $id; };
$sha = str_repeat('a', 40);
$rel = fn (int $id) => Releases::get($pdo, $id);
$ba = fn (int $id) => BeforeAfter::state($pdo, $id);
$msgs = fn (int $id, int $from) => array_column(array_slice((array) $rel($id)['log'], $from), 'msg');
$asked = function (int $id, int $minsAgo) use ($pdo, $ba) { $d = $ba($id); $d['asked_at'] = time() - $minsAgo * 60; $pdo->prepare('REPLACE INTO site_release_settings (k, v) VALUES (?, ?)')->execute(['ba:' . $id, json_encode($d)]); };
require_once $app . '/lib/StagingSite.php'; StagingSite::ensure($pdo);
Switches::set($pdo, 'before_after', 'test', 0, false, 'tickverdict test', 1);

// (c) the read: GitHub's contents API on main, decoded; a 404 is null with no other read; GitHub away: the raw read
$calls = []; $answer = [];
DocsRepo::$fake = function ($m, $u, $j) use (&$calls, &$answer) { $calls[] = $u; foreach ($answer as $pre => $a) if (str_starts_with($u, $pre)) return $a; return [404, '']; };
$api = 'https://api.github.com/repos/degraffics/traversence/contents/site-audit/results/beforeafter/9.json?ref=main';
$rawU = 'https://raw.githubusercontent.com/degraffics/traversence/main/site-audit/results/beforeafter/9.json';
$answer = [$api => [200, json_encode(['encoding' => 'base64', 'content' => base64_encode('{"release":"9"}')])]];
$got = DocsRepo::now('site-audit/results/beforeafter/9.json');
$t('(c) now(): read through the contents API on main, decoded', $got === '{"release":"9"}' && $calls === [$api], [$got, $calls]);
$calls = []; $answer = [];
$t('(c) not on main (404): null, and raw.githubusercontent.com isn\'t asked', DocsRepo::now('site-audit/results/beforeafter/9.json') === null && $calls === [$api], $calls);
$calls = []; $answer = [$api => [503, ''], $rawU => [200, '{"release":"9","via":"raw"}']];
$t('(c) GitHub\'s API away (503): the fresh raw read instead, as before', DocsRepo::now('site-audit/results/beforeafter/9.json') === '{"release":"9","via":"raw"}' && $calls === [$api, $rawU], $calls);
$calls = []; $answer = [$api => [200, '{"message":"too large"}'], $rawU => [200, '{"release":"9"}']];
$t('(c) an answer with no content (a file too large for it): the raw read', DocsRepo::now('site-audit/results/beforeafter/9.json') === '{"release":"9"}', $calls);
$t('(c) a path with .. is refused, nothing asked', ($calls = []) === [] && DocsRepo::now('site-audit/../.env') === null && $calls === [], $calls);
$v = BeforeAfter::verdict(9);   // $readFn unset: the live path
$answer = [$api => [200, json_encode(['encoding' => 'base64', 'content' => base64_encode(json_encode(['release' => '9', 'phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 12]))])]];
$calls = []; $v = BeforeAfter::verdict(9);
$t('(c) verdict() reads through now(): the before-done verdict, from the contents API', ($v['phase'] ?? '') === 'before-done' && $calls === [$api], [$v, $calls]);

// the note: no verdict file yet, said once past NOTE_MINUTES on the release's card, never before
BeforeAfter::$readFn = fn ($p) => null;
$a = $mk('tv-a.zip'); BeforeAfter::begin($pdo, $a, 'tv-a.zip', $sha, false);
$asked($a, 10); $n = count($rel($a)['log']); BeforeAfter::advance($pdo);
$t('no verdict, asked 10 minutes ago: nothing written (the run takes 15 to 25)', !preg_grep('/no verdict file/', $msgs($a, $n)), $msgs($a, $n));
$asked($a, BeforeAfter::NOTE_MINUTES + 1); $n = count($rel($a)['log']); BeforeAfter::advance($pdo);
$m = $msgs($a, $n);
$t('no verdict past ' . BeforeAfter::NOTE_MINUTES . ' minutes: said on its card, with the file it reads', count(preg_grep('#Waiting for its before look: no verdict file on main yet \(site-audit/results/beforeafter/' . $a . '\.json\), asked 3\d minutes ago#', $m)) === 1, $m);
$t('… and its card\'s line says it', str_contains(Releases::standing($rel($a))[0], 'no verdict file on main yet'), Releases::standing($rel($a)));
$n = count($rel($a)['log']); BeforeAfter::advance($pdo); BeforeAfter::advance($pdo);
$t('… once: the next runs write nothing more', !preg_grep('/no verdict file/', $msgs($a, $n)), $msgs($a, $n));
$t('… and nothing else moved: still waiting for its before look, the late re-ask not yet', $ba($a)['phase'] === 'before' && (int) ($ba($a)['tries'] ?? 1) === 1, $ba($a));

// the lock: an advance already going, a second does nothing
$env = parse_ini_file(dirname($app, 3) . '/.env', false, INI_SCANNER_RAW) ?: []; $other2 = new PDO('mysql:host=' . ($env['DB_HOST'] ?? 'localhost') . ';dbname=lv_main', (string) ($env['DB_USERNAME'] ?? ''), (string) ($env['DB_PASSWORD'] ?? ''));
$other2->query("SELECT GET_LOCK('tv_rel_ba', 0)");
$files = []; BeforeAfter::$readFn = function ($p) use (&$files) { return $files[$p] ?? null; };
$files['site-audit/results/beforeafter/' . $a . '.json'] = json_encode(['release' => (string) $a, 'phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 60]);
$n = count($rel($a)['log']); $out = BeforeAfter::advance($pdo);
$t('another advance holding the lock (the ping and the job at once): this one does nothing', $out === [] && $ba($a)['phase'] === 'before' && count($rel($a)['log']) === $n, [$out, $msgs($a, $n)]);
$other2->query("SELECT RELEASE_LOCK('tv_rel_ba')"); $other2 = null;
$n = count($rel($a)['log']); $out = BeforeAfter::advance($pdo);
$t('… once it\'s free: its verdict is read and it goes on (here staging isn\'t set up, so it says so)', (bool) array_filter($out, fn ($l) => str_contains($l, 'Release #' . $a)) && (bool) preg_grep('/Plan approved|not on staging yet|staging/i', $msgs($a, $n)), [$out, $msgs($a, $n)]);
$t('… and the lock is let go after it', (int) $pdo->query("SELECT IS_FREE_LOCK('tv_rel_ba')")->fetchColumn() === 1);

// (b) the endpoint the workflow pings, run for real: it moves a waiting release on, reading the verdict through the API
BeforeAfter::$readFn = null;
$b = $mk('tv-b.zip'); BeforeAfter::begin($pdo, $b, 'tv-b.zip', $sha, false); $asked($b, 20);
$pdo->exec("DELETE FROM site_release_settings WHERE k = 'git_seen_ping'");
$n = count($rel($b)['log']);
$cmd = 'TV_TEST=1 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/tickverdict-pull.php') . ' ' . escapeshellarg($root) . ' ' . $b . ' 2>&1';
$res = (string) shell_exec($cmd);
preg_match('/\{"ok":.*\}/', $res, $mm); $j = json_decode($mm[0] ?? '', true);
$t('(b) releases-pull.php answers ok, with the before-and-after step\'s line for the release', is_array($j) && !empty($j['ok']) && (bool) array_filter((array) $j['lines'], fn ($l) => str_contains((string) $l, 'Release #' . $b)), $res);
$t('(b) … it read the verdict through the contents API on main', str_contains($res, 'contents/site-audit/results/beforeafter/' . $b . '.json?ref=main') && !str_contains($res, 'raw.githubusercontent.com'), $res);
$t('(b) … and the release moved on from waiting: its plan approved and the staging step tried', (bool) preg_grep('/Plan approved|not on staging yet|staging/i', $msgs($b, $n)) && !preg_grep('/^Waiting for its before look: the advocates/', $msgs($b, $n)), $msgs($b, $n));
$res2 = (string) shell_exec($cmd); preg_match('/\{"ok":.*\}/', $res2, $mm2); $j2 = json_decode($mm2[0] ?? '', true);
$t('(b) a second ping within the minute: 429 too_soon, as before (the workflow waits 65 s and asks again)', is_array($j2) && ($j2['code'] ?? $j2['error']['code'] ?? '') === 'too_soon' || str_contains($res2, 'too_soon'), $res2);
$stage(true);
$t('never on staging: advance and tickLine do nothing', BeforeAfter::advance($pdo) === [] && BeforeAfter::tickLine($pdo) === '');
$stage(false);

// the far timeouts on the release's card (the CEO's Tier 4 condition, 2026-11-27): each "after the look" note, once
$files = []; BeforeAfter::$readFn = function ($p) use (&$files) { return $files[$p] ?? null; };
$c = $mk('tv-c.zip'); BeforeAfter::begin($pdo, $c, 'tv-c.zip', $sha, false);
$pdo->prepare("UPDATE site_releases SET state = 'staged', checks_ok = 1, checks = ? WHERE id = ?")->execute([json_encode(['ok' => true, 'items' => [['PHP files parse', true, '1 checked']]]), $c]);
$files['site-audit/results/beforeafter/' . $c . '.json'] = json_encode(['release' => (string) $c, 'phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 60]);
BeforeAfter::advance($pdo);
$far = 'executive|/admin/crawler-review.php|phone: far timeout, noted: timed out, then opened when tried again after the look (reached only through website/js/tv-ask.js; staging is slow there, the IT Manager\'s lane)';
$files['site-audit/results/beforeafter/' . $c . '.json'] = json_encode(['release' => (string) $c, 'phase' => 'after-done', 'verdict' => 'pass', 'seconds' => 120, 'views' => 174, 'notes' => ['executive|/admin/data.php|phone: already missed "No sideways scroll at 390px (0058 §11)" before (Site health\'s, not this change\'s)', $far], 'run' => 'https://github.com/degraffics/traversence/actions/runs/4']);
$n = count($rel($c)['log']); BeforeAfter::advance($pdo); $m = $msgs($c, $n);
$t('a pass with a far timeout: "far timeout, noted" written on its card, once, as the check wrote it', count(preg_grep('/^The before-and-after check noted: executive\|\/admin\/crawler-review\.php\|phone: far timeout, noted/', $m)) === 1, $m);
$t('… and only the far timeouts: Site health\'s other notes aren\'t copied there', !preg_grep('/already missed/', $m), $m);
$t('… then its verdict as before', (bool) preg_grep('/before-and-after check: passed/', $m), $m);

// (a) releases_tick's cron line: to every 10 minutes once; a person's later Change stays
$lines = [['line' => 7, 'linekey' => 'k', 'minute' => '*/24', 'hour' => '*', 'day' => '*', 'month' => '*', 'weekday' => '*', 'command' => '/usr/local/bin/php /home/x/app/scripts/releases_tick.php >/dev/null 2>&1']];
$changes = [];
BeforeAfter::$linesFn = function () use (&$lines) { return $lines; };
BeforeAfter::$changeFn = function ($job, $preset) use (&$changes, &$lines) { $changes[] = [$job, $preset]; $lines[0]['minute'] = '*/10'; };
$l = BeforeAfter::tickLine($pdo);
$t('(a) live\'s line read */24: changed to every 10 minutes, through Scheduler::change\'s preset', $changes === [['releases_tick', '10min']] && $l === 'releases_tick: its cron line moved from */24 to */10.', [$changes, $l]);
$st = $pdo->query("SELECT v FROM site_release_settings WHERE k = 'tick_line_10min'")->fetchColumn();
$t('(a) … remembered, with what it was', ($w = json_decode((string) $st, true)) && $w['was'] === '*/24', $st);
$lines[0]['minute'] = '*/15'; $changes = [];
$t('(a) a person changes it later on Scheduled tasks: it stays theirs', BeforeAfter::tickLine($pdo) === '' && $changes === [], $changes);
$pdo->exec("DELETE FROM site_release_settings WHERE k = 'tick_line_10min'"); $lines[0]['minute'] = '*/10'; $changes = [];
$t('(a) already every 10 minutes: nothing changed, only remembered', BeforeAfter::tickLine($pdo) === '' && $changes === [] && $pdo->query("SELECT COUNT(*) FROM site_release_settings WHERE k = 'tick_line_10min'")->fetchColumn() == 1, $changes);
$pdo->exec("DELETE FROM site_release_settings WHERE k = 'tick_line_10min'"); BeforeAfter::$linesFn = null; BeforeAfter::$changeFn = null;
putenv('CPANEL_HOST=');
$t('(a) cPanel not connected: waits, saying nothing, and isn\'t remembered (tried next run)', BeforeAfter::tickLine($pdo) === '' && $pdo->query("SELECT COUNT(*) FROM site_release_settings WHERE k = 'tick_line_10min'")->fetchColumn() == 0);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
