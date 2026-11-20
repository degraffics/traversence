<?php
// The Wikidata 429 fix (Jason, 2026-11-20, approved; board issue #26): Wdqs::ask on a real MySQL or MariaDB (page_cache,
// GET_LOCK). An answer is kept 7 days; a 429's Retry-After is honoured by every caller; one query at a time (a second caller
// skips while one is in flight); a 200 after the wait is asked and kept; other hosts aren't Wdqs's.
// Run: APP=<a site tree's app> DB=<a database> php wdqs.php   (it uses Database::connection(); set its .env or DB_* as the tree expects)
declare(strict_types=1);
$app = getenv('APP') ?: __DIR__ . '/../../app';
require $app . '/lib/Wdqs.php';
$pdo = Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)');
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'wdqs:%'");
$ok = 0; $bad = 0; $t = function ($n, $g, $w) use (&$ok, &$bad) { if ($g === $w) $ok++; else { $bad++; echo "FAIL $n\n  got:  " . var_export($g, true) . "\n  want: " . var_export($w, true) . "\n"; } };
$u = 'https://query.wikidata.org/sparql?format=json&query=' . rawurlencode('SELECT 1');
$calls = 0; $reply = [200, '{"ok":1}', ''];
$call = function () use (&$calls, &$reply) { $calls++; return $reply; };
$t('the query service is Wdqs\'s', Wdqs::is($u), true);
$t('Wikipedia isn\'t', Wdqs::is('https://en.wikipedia.org/api/rest_v1/page/summary/X'), false);
$t('asked once: its answer', Wdqs::ask($u, $call), '{"ok":1}');
$t('asked again: kept, not asked', [Wdqs::ask($u, $call), $calls], ['{"ok":1}', 1]);
$u2 = $u . 'x'; $reply = [429, 'Too many', '120'];
$t('a 429: nothing to show', Wdqs::ask($u2, $call), null);
$t('… and its wait is kept for every caller', Wdqs::waiting($pdo) !== '', true);
$reply = [200, '{"ok":2}', '']; $before = $calls;
$t('during the wait: not asked', [Wdqs::ask($u2, $call), $calls], [null, $before]);
$t('a kept answer still shows during the wait', Wdqs::ask($u, $call), '{"ok":1}');
$t('Retry-After in seconds', Wdqs::waitFor(429, '120'), 120);
$t('no Retry-After on a 429: a minute', Wdqs::waitFor(429, ''), 60);
$t('never more than an hour', Wdqs::waitFor(429, '99999'), 3600);
$t('Retry-After as a date', abs(Wdqs::waitFor(503, gmdate('D, d M Y H:i:s', time() + 300) . ' GMT') - 300) <= 2, true);
$pdo->exec("DELETE FROM page_cache WHERE cache_key = 'wdqs:wait-until'");   // the wait is over
$other = new PDO($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? (getenv('DSN') ?: 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=' . (getenv('DB') ?: 'lv_main')) : '', 'root', '');
$other->query("SELECT GET_LOCK('tv_wdqs', 0)");   // another caller's query in flight
$before = $calls; $t0 = microtime(true);
$t('one at a time: a second caller skips', [Wdqs::ask($u2, $call), $calls], [null, $before]);
$t('… after waiting at most 5 seconds', microtime(true) - $t0 < 6.5, true);
$other->query("SELECT RELEASE_LOCK('tv_wdqs')");
$t('the lock free and the wait over: asked and kept', [Wdqs::ask($u2, $call), Wdqs::ask($u2, $call), $calls], ['{"ok":2}', '{"ok":2}', $before + 1]);
$pdo->exec("DELETE FROM page_cache WHERE cache_key LIKE 'wdqs:%'");
echo "$ok passed, $bad failed\n";
