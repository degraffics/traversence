<?php
// control-board-critical-2 (2026-11-19, 0171 §5's note): the sensor for the site's own critical duties. Search, sign-in
// and payments are called by browsers and Intuit, so the ledger never saw them. The hook in Database.php notes a server
// error on their paths (path and status only, never a query string; never with the table or the connection missing);
// the watch judges each part: N or more requests in 5 minutes, or a failed test, is the part failing (a critical trip at
// once while the system keeps trying, then Recovered or the Escalation Notice); fewer is a notice on its row. The search
// test records nothing: no demand, no crawler, no view.
// Run: mariadb < ../release-b-tests/start-schema.sql && php sensor.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };

// 1. the hook, before any connection: nothing is written, nothing thrown (a database that's down)
require_once $app . '/lib/ControlSensor.php';
$t('no connection open: Database::open() is null and the hook writes nothing', Database::open() === null);
$_SERVER['REQUEST_URI'] = '/api/search.php?q=secret+words';
$threw = false; try { ControlSensor::note(500); } catch (Throwable $x) { $threw = true; }
$t('…and doesn\'t throw', !$threw);

$pdo = Database::connection();
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/SearchIndex.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo); $pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
try { SearchIndex::setState($pdo, 'control:duties', '[]'); } catch (Throwable $x) {}

// 2. the table missing: no write, no throw
$pdo->exec('DROP TABLE IF EXISTS control_errors');
$threw = false; try { ControlSensor::note(500); } catch (Throwable $x) { $threw = true; }
$t('the table missing: no throw', !$threw);
ControlSensor::ensure($pdo);
$t('…and nothing written', (int) $pdo->query('SELECT COUNT(*) FROM control_errors')->fetchColumn() === 0);

// 3. what's noted: a watched path, path and status only
ControlSensor::note(500);
$r = $pdo->query('SELECT path, code FROM control_errors')->fetch(PDO::FETCH_ASSOC);
$t('a server error on search\'s path is noted', $r && $r['path'] === '/api/search.php' && (int) $r['code'] === 500, $r);
$cols = array_column($pdo->query('SHOW COLUMNS FROM control_errors')->fetchAll(PDO::FETCH_ASSOC), 'Field');
$t('…path and status only: no query string, address or user', $cols === ['id', 'path', 'code', 'at'] && !str_contains((string) $r['path'], 'secret'), $cols);
$_SERVER['REQUEST_URI'] = '/explore/show-low'; ControlSensor::note(500);
$t('a path no duty watches isn\'t noted', (int) $pdo->query('SELECT COUNT(*) FROM control_errors')->fetchColumn() === 1);
$t('sign-in and payments\' paths are watched', ControlSensor::partOf('/api/auth/login.php') === 'site:signin' && ControlSensor::partOf('/api/qb-webhook.php') === 'site:payments' && ControlSensor::partOf('/q.php') === 'site:search');

// 4. the register: each part with its duty
require_once $app . '/lib/ControlRegister.php';
$t('Search, Sign-in and Checkout are parts with their duties', ControlRegister::duty($pdo, 'site:search') === 'search' && ControlRegister::duty($pdo, 'site:signin') === 'procedures' && ControlRegister::duty($pdo, 'site:payments') === 'payments' && isset(ControlRegister::processes($pdo)['site:search']));

// 5. one stray error: a notice on the row, no trip
ControlSensor::$searchProbe = fn () => [true, 'A test search answered in 40 ms (nothing recorded).'];
ControlSensor::$payProbe = fn () => ['na', 'QuickBooks isn\'t set up here.'];
ControlSensor::watch($pdo);
$open = ControlAlarms::ofProc($pdo, 'site:search');
$lv = array_column($open, 'level');
$t('one stray server error: kept as a notice on Search\'s row, no trip', $lv === ['notice'], $lv);

// 6. N in 5 minutes: the part fails, a critical trip at once, still retrying
$n0 = ControlSensor::threshold($pdo);
$t('the threshold is 3 unless changed (Thresholds & gates)', $n0 === 3);
$_SERVER['REQUEST_URI'] = '/api/search_listings.php'; for ($i = 0; $i < $n0; $i++) ControlSensor::note(503);
ControlSensor::watch($pdo);
$a = null; foreach (ControlAlarms::ofProc($pdo, 'site:search') as $x) if ($x['akey'] === 'crit:site:search') $a = $x;
$t('3 requests with server errors: "Search is failing", a trip at once', $a && $a['level'] === 'trip' && !empty($a['tripped_at']), $a);
$t('…still retrying, nothing stopped', $a && !empty($a['retrying_at']) && empty($a['stopped']));
$t('…its reading names the paths', $a && str_contains((string) $a['reading'], '/api/search_listings.php ×3'), $a['reading'] ?? null);
$fx = json_decode((string) ($a['fix'] ?? ''), true);
$t('…its fix opens the failing path and the error log', is_array($fx) && $fx[0][1] === 'peek' && $fx[0][2] === '/api/search_listings.php' && $fx[1][1] === 'link');

// 7. it answers well again: Recovered
$pdo->exec('UPDATE control_errors SET at = UTC_TIMESTAMP() - INTERVAL 10 MINUTE');
ControlSensor::watch($pdo);
$c = ControlAlarms::byId($pdo, (int) $a['id']);
$t('good again: the trip closes as Recovered, nothing to press', $c['cleared_at'] !== null && str_starts_with((string) $c['cleared_how'], 'Recovered: Search was down') && $c['ack_at'] !== null, $c['cleared_how']);

// 8. a failed test alone is a failure; its tries running out is the Escalation Notice
ControlSensor::$searchProbe = fn () => [false, 'A test search failed: no answer.'];
[$max] = ControlAlarms::limits($pdo, 'crash');
for ($i = 0; $i < $max; $i++) ControlSensor::watch($pdo);
$a = null; foreach (ControlAlarms::ofProc($pdo, 'site:search') as $x) if ($x['akey'] === 'crit:site:search') $a = $x;
$t('a failed test: Search fails; after its tries, the Escalation Notice and only Resolve', $a && $a['level'] === 'trip' && empty($a['retrying_at']) && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Escalation Notice: retries unsuccessful, now admin required: Search, Search%'")->fetchColumn() === 1, $a);
[$bad, $words] = ControlSensor::checkOne($pdo, 'site:search');
$t('Check on its row says it\'s still failing, and that only Resolve clears it now', $bad && str_contains($words, 'Only Resolve clears it now'), $words);

// 9. QuickBooks refused: a trip at once (held)
ControlSensor::$payProbe = fn () => ['held', 'QuickBooks refused us: needs reconnecting.'];
ControlSensor::watch($pdo);
$q = null; foreach (ControlAlarms::ofProc($pdo, 'site:payments') as $x) if ($x['akey'] === 'crit:site:payments:token') $q = $x;
$t('QuickBooks\' token refused or expired: a trip at once', $q && $q['level'] === 'trip' && !empty($q['tripped_at']) && $q['title'] === 'QuickBooks needs reconnecting', $q);
ControlSensor::$payProbe = fn () => ['na', 'QuickBooks isn\'t set up here.'];

// 10. the real search test records nothing: no demand, no crawler, no view (row counts of every table unchanged)
ControlSensor::$searchProbe = null;
$counts = function () use ($pdo) { $o = []; foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tb) { if ($tb === 'control_errors' || $tb === 'search_index_state') continue; try { $o[$tb] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $tb . '`')->fetchColumn(); } catch (Throwable $x) {} } return $o; };
$before = $counts(); [$ok, $w] = ControlSensor::probeSearch($pdo); $after = $counts();
$t('the search test changes no table (demand, crawler, views): ' . ($ok ? 'it answered' : 'it failed here, as this test database has no search tables'), $before === $after, array_diff_assoc($after, $before));
$src = (string) file_get_contents($app . '/lib/ControlSensor.php');
$t('…it runs with no_side (no crawler, no demand, no help-find) and calls no AI', str_contains($src, "['no_side' => 1]") && !preg_match('/AiClient|Gemini|Claude::/', $src));
$t('…QuickBooks is asked at most once an hour, held on staging', str_contains($src, 'time() - $last < 3600') && str_contains($src, 'StagingGate::hold('));

// 11. kept 7 days; the hook is in Database.php and cheap on a good answer
$pdo->exec("INSERT INTO control_errors (path, code, at) VALUES ('/q.php', 500, UTC_TIMESTAMP() - INTERVAL 8 DAY)");
ControlSensor::watch($pdo);
$t('rows older than 7 days are removed', (int) $pdo->query("SELECT COUNT(*) FROM control_errors WHERE at < UTC_TIMESTAMP() - INTERVAL 7 DAY")->fetchColumn() === 0);
$db = (string) file_get_contents($app . '/lib/Database.php');
$t('the hook: nothing loaded on a good answer, never throws', str_contains($db, 'if ($c < 500 && !$fatal) return;') && str_contains($db, '} catch (Throwable $x) {}'));
$st = (string) file_get_contents($app . '/lib/staging-tables.php');
$t('its table is registered for staging (its own)', str_contains($st, "'control_errors'"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
