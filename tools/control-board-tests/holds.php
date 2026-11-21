<?php
// The workflow audit of 2026-11-21 (decisions/0175 §3: "a trip stops only what it holds"; 0163's source map; Jason: "reroute
// to a back up service or trip"): a service's red gate line holds nothing when its needs still have a source that works,
// only the tasks that call it when others don't, and the whole workflow only when every task calls it. A red need pauses
// the tasks that call its services. The site's own check answering closes the crawler's back-off at that host (a fix to
// §3's rule, which wasn't true for crawler-held issues), never a ban and never a 429 in its wait. A service line that's
// amber is "Partly working". Paused now says "Its fix:" once.
// Run: mariadb < ../release-b-tests/start-schema.sql && php holds.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 700) : '') . "\n"; } };
require_once $app . '/lib/Operations.php'; require_once $app . '/lib/Preflight.php'; require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/ControlAlarms.php';
require_once $app . '/lib/Automation.php'; require_once $app . '/lib/ControlRegister.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, last_probe TEXT NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec('DELETE FROM service_issues'); $pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS preflight_results (id VARCHAR(40) NOT NULL PRIMARY KEY, v MEDIUMTEXT NOT NULL, at DATETIME NOT NULL)"); $pdo->exec('DELETE FROM preflight_results');
$cols = $pdo->query('SHOW COLUMNS FROM crawl_capture_tasks')->fetchAll(PDO::FETCH_COLUMN);
$task = function (string $k, string $wf) use ($pdo, $cols) { $row = ['task' => $k, 'wf' => $wf]; if (in_array('name', $cols, true)) $row['name'] = $k; if (in_array('enabled', $cols, true)) $row['enabled'] = 1;
    $pdo->prepare('INSERT INTO crawl_capture_tasks (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row)); };
$pdo->exec('DELETE FROM crawl_capture_tasks');
foreach ([['sources_read', 'W3'], ['commoncrawl', 'W3'], ['places', 'W2'], ['recheck', 'W2']] as [$k, $w]) $task($k, $w);
$res = function (string $id, string $s) use ($pdo) { $pdo->prepare('REPLACE INTO preflight_results (id, v, at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$id, json_encode(['s' => $s, 'said' => 'test'])]); };
$now = gmdate('Y-m-d H:i:s');
// Common Crawl held (a refusal at its data host, from the crawler): its gate line is red
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence) VALUES (35, 'commoncrawl', 'data.commoncrawl.org', 'crawler', 'ban', 'open', 1, '$now', '$now', '{}')");
$line = array_values(array_filter(Operations::switchOnCheck($pdo), fn ($l) => $l['scope'] === 'commoncrawl'));
$t('setup: Common Crawl held makes its gate line red ("Common Crawl (W3)")', $line && !$line[0]['ok'] && str_contains((string) $line[0]['line'], 'W3'), $line);

// 1. a backup down, its need still answered (W3's sources: ours, then direct, then Common Crawl)
$res('nd:sources', 'ok'); Operations::forgetGate();
$cc = fn (array $ls) => array_values(array_filter($ls, fn ($l) => $l['scope'] === 'commoncrawl'));
$ccg = function (array $g) use ($cc) { $o = []; foreach ($g as $k => $v) $o[$k] = $k === 'tasks' ? array_map($cc, array_filter($v, fn ($ls) => (bool) $cc($ls))) : $cc($v); $o['tasks'] = array_filter($o['tasks']); return $o; };
$g = $ccg(Operations::gateFor($pdo, 'W3'));
$t('W3\'s need for sources still answered: Common Crawl\'s line is covered, it holds nothing', count($g['covered']) === 1 && !$g['tasks'] && !$g['whole'], $g);
$t('…so it isn\'t among W3\'s blocking lines', !array_filter(Operations::blocking($pdo, 'W3'), fn ($l) => $l['scope'] === 'commoncrawl'));
$t('…and no task waits', Operations::taskHeld($pdo, 'commoncrawl', 'W3') === [] && Operations::taskHeld($pdo, 'sources_read', 'W3') === []);
$res('nd:sources', 'warn'); Operations::forgetGate();
$t('on a backup counts as answered too', count($ccg(Operations::gateFor($pdo, 'W3'))['covered']) === 1);

// 2. no source answers the need: only the task that calls Common Crawl waits
$res('nd:sources', 'bad'); Operations::forgetGate();
$g = $ccg(Operations::gateFor($pdo, 'W3'));
$t('no source answers: only W3\'s commoncrawl task waits, the rest of W3 runs', array_keys($g['tasks']) === ['commoncrawl'] && !$g['whole'], $g);
$t('…its line isn\'t one that holds W3 whole', !array_filter(Operations::blocking($pdo, 'W3'), fn ($l) => $l['scope'] === 'commoncrawl'));
$t('…taskHeld names the line for that task only', Operations::taskHeld($pdo, 'commoncrawl', 'W3') && !Operations::taskHeld($pdo, 'sources_read', 'W3'));
$cs = (string) file_get_contents($app . '/lib/crawler/CrawlService.php');
$t('…and the crawler\'s plan skips a held task, never its workflow', str_contains($cs, "if (Operations::taskHeld(\$pdo, (string) \$t, (string) \$r['wf'])) continue;"));
$pf = (string) file_get_contents($app . '/lib/Preflight.php');
$t('W3\'s line says it in amber: waiting while their source is down, the rest runs', str_contains($pf, "'Waiting while their source is down: '") && str_contains($pf, "\$states[] = 'warn'; \$parts[] = 'A source is down and covered: '"));

// 3. every task of a workflow calls the service: the workflow waits whole, as before
$pdo->exec("DELETE FROM crawl_capture_tasks WHERE task = 'sources_read'"); (function () { $p = new ReflectionProperty(ControlRegister::class, "tasks"); $p->setAccessible(true); $p->setValue(null, null); })(); Operations::forgetGate();
$g = $ccg(Operations::gateFor($pdo, 'W3'));
$t('every W3 task calls Common Crawl and no source answers: W3 waits whole', count($g['whole']) === 1 && !$g['tasks'], $g);
$t('…and the line is among W3\'s blocking lines', (bool) array_filter(Operations::blocking($pdo, 'W3'), fn ($l) => $l['scope'] === 'commoncrawl'));
$task('sources_read', 'W3'); (function () { $p = new ReflectionProperty(ControlRegister::class, "tasks"); $p->setAccessible(true); $p->setValue(null, null); })(); Operations::forgetGate();

// Supabase (W3's semantic search, a soft need: keyword search still works, so it reads On a backup): holds nothing of W3
$res('nd:semantic', 'warn'); Operations::forgetGate();
$sp = array_values(array_filter(Operations::gateFor($pdo, 'W3')['covered'], fn ($l) => $l['scope'] === 'supabase'));
$t('Supabase down while keyword search answers W3\'s semantic need: it holds nothing of W3', count($sp) === 1 && !array_filter(Operations::blocking($pdo, 'W3'), fn ($l) => $l['scope'] === 'supabase'), Operations::gateFor($pdo, 'W3'));
// a service none of W3's needs names and none of its tasks calls (Claude, reached only as the feature workflows' writer)
$an = array_values(array_filter(Operations::gateFor($pdo, 'W3')['apart'], fn ($l) => $l['scope'] === 'anthropic'));
$t('a service no need of W3 names and no W3 task calls (Claude here) holds nothing of W3', count($an) === 1 && !array_filter(Operations::blocking($pdo, 'W3'), fn ($l) => $l['scope'] === 'anthropic'), Operations::gateFor($pdo, 'W3'));

// 4. a red need's trip pauses the tasks that call its services, in every workflow it names
$pdo->exec("REPLACE INTO preflight_results (id, v, at) VALUES ('nd:sources', '{\"s\":\"bad\"}', UTC_TIMESTAMP())");
$aid = ControlAlarms::raise($pdo, 'pf:nd:sources', 'wf:W3', 'trip', 'check', 'Sources read regularly (W3): no source works', '', '', '', [], true, false);
$paused = $pdo->query("SELECT CONCAT(kind, ':', ref) FROM system_pauses WHERE cleared_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
$t('a red need trips: it pauses the commoncrawl task, not W3', in_array('task:commoncrawl', $paused, true) && !in_array('wf:W3', $paused, true), $paused);

// 5. the site's check answering closes the crawler's back-off (a fix to 0175 §3), never a ban, never a 429 in its wait
$pdo->exec('DELETE FROM service_issues');
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence, hold_until) VALUES
  (40, 'commoncrawl', 'index.commoncrawl.org', 'crawler', 'server', 'open', 6, '$now', '$now', '{}', '" . ServiceHealth::TRIP_HOLD . "'),
  (41, 'nps', 'developer.nps.gov', 'crawler', 'ban', 'open', 1, '$now', '$now', '{}', NULL),
  (42, 'wikidata', 'query.wikidata.org', 'crawler', 'rate', 'open', 2, '$now', '$now', '{}', '" . gmdate('Y-m-d H:i:s', time() + 1800) . "'),
  (43, 'wikidata', 'www.wikidata.org', 'crawler', 'rate', 'open', 2, '$now', '$now', '{}', '" . gmdate('Y-m-d H:i:s', time() - 60) . "')");
foreach (['index.commoncrawl.org', 'developer.nps.gov', 'query.wikidata.org', 'www.wikidata.org'] as $h) ServiceHealth::answered($pdo, $h, 'site');
$st = $pdo->query('SELECT id, state FROM service_issues ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$t('a 2xx to the site\'s check closes the crawler\'s server-error back-off at that host (#35\'s case)', ($st[40] ?? '') === 'resolved', $st);
$t('…never a refusal (it waits for a person)', ($st[41] ?? '') === 'open');
$t('…never a 429 still in the wait it asked for; one whose wait is over closes', ($st[42] ?? '') === 'open' && ($st[43] ?? '') === 'resolved', $st);

// 6. the words
$t('a service line that\'s amber is "Partly working"; a need\'s stays "On a backup"', Preflight::markOf('svc:commoncrawl', 'warn') === 'Partly working' && Preflight::markOf('nd:sources', 'warn') === 'On a backup' && Preflight::markOf('svc:x', 'ok') === 'Ready');
$cb = (string) file_get_contents($app . '/lib/ControlBoard.php');
$t('Paused now says "Its fix:" once', str_contains($cb, "\$fix = (string) preg_replace('/^Its fix:\\s*/i', '', \$fix);"));
$sh = (string) file_get_contents($app . '/lib/ServiceHealth.php');
$t('no "then Resolve" left: Check and start again', !str_contains($sh, 'then Resolve.') && str_contains($sh, 'then press Check and start again'));
$t('Common Crawl\'s check tests data.commoncrawl.org first (the bulk reads), the index server second', (bool) preg_match("~case 'commoncrawl': return self::reachAll\\(\\\$pdo, \\[\\['https://data\\.commoncrawl\\.org/crawl-data/index\\.html'.*\\['https://index\\.commoncrawl\\.org/collinfo\\.json'~s", $pf));
$t('the Census check tests the geocoder (W2\'s map points) first, and its key is optional', str_contains($pf, "['https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?") && str_contains((string) file_get_contents($app . '/lib/Operations.php'), "'.env (optional: the data API"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
