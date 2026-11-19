<?php
// control-board-itsdata (2026-11-19, Jason's answers in the pop-up): sites that blocked us as one quiet notice (a warning
// only past w0_block_share of the day's reads), "Its data" on each process's own page (the records its calls touched,
// ids and what happened only), W0 logging sites outside our region as "Outside our region" and never reading them, and
// Pulse's gathering time on the board (a warning past control_pulse_ms; never a trip).
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php itsdata.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/ControlSensor.php'; require_once $app . '/lib/crawler/Sources.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo); $pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);

// 1. W0: outside our region, logged and never read
$t('a city\'s .gov ending in another state\'s code is outside (lawrenceks.gov)', str_contains(Sources::offWhyHost($pdo, 'lawrenceks.gov'), 'Kansas'));
$t('…a state\'s own address and site (.ks.us, .ks.gov, maryland.gov)', Sources::offWhyHost($pdo, 'ci.lawrence.ks.us') !== '' && Sources::offWhyHost($pdo, 'kdhe.ks.gov') !== '' && str_contains(Sources::offWhyHost($pdo, 'maryland.gov'), 'Maryland'));
$t('…ours and federal sites aren\'t (tucsonaz.gov, santafenm.gov, nps.gov, parks.gov, recreation.gov)', Sources::offWhyHost($pdo, 'tucsonaz.gov') === '' && Sources::offWhyHost($pdo, 'santafenm.gov') === '' && Sources::offWhyHost($pdo, 'nps.gov') === '' && Sources::offWhyHost($pdo, 'parks.gov') === '' && Sources::offWhyHost($pdo, 'recreation.gov') === '');
require_once $app . '/lib/crawler/Frontier.php';
$T = Database::t('crawl_frontier');
// the frontier as W0's migration makes it, the columns this needs (its migration has run and gone from the mirror)
try { $pdo->exec("CREATE TABLE IF NOT EXISTS $T (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, url_hash CHAR(32) NOT NULL UNIQUE, url TEXT NULL, host VARCHAR(190) NOT NULL DEFAULT '', site VARCHAR(150) NOT NULL DEFAULT '',
    state VARCHAR(10) NOT NULL DEFAULT 'pending', reason VARCHAR(20) NULL, lease_until DATETIME NULL, next_at DATETIME NULL, first_at DATETIME NULL, seen_at DATETIME NULL, read_at DATETIME NULL, KEY host (host))"); } catch (Throwable $x) {}
$ready = false; try { $pdo->query("SELECT 1 FROM $T LIMIT 1"); $ready = true; } catch (Throwable $x) {}
if ($ready) {
    $pdo->exec("DELETE FROM $T WHERE host IN ('lawrenceks.gov', 'flagstaffaz.gov')");
    foreach ([['https://lawrenceks.gov/parks', 'lawrenceks.gov'], ['https://flagstaffaz.gov/parks', 'flagstaffaz.gov']] as $i => [$u, $h])
        $pdo->prepare("INSERT INTO $T (url_hash, url, host, site, first_at, seen_at) VALUES (?, ?, ?, ?, NOW(), NOW())")->execute([md5($u), $u, $h, $h]);
    $n = Frontier::markOutside($pdo, ['lawrenceks.gov', 'flagstaffaz.gov']);
    $st = $pdo->query("SELECT host, state, reason FROM $T WHERE host IN ('lawrenceks.gov', 'flagstaffaz.gov') ORDER BY host")->fetchAll(PDO::FETCH_ASSOC);
    $t('W0 logs an outside address as "outside" and keeps it (never read); ours stays to be read', $n === 1 && $st[1]['state'] === 'outside' && $st[1]['reason'] === 'OUTSIDE_REGION' && $st[0]['state'] === 'pending', $st);
    Sources::offRuleAdd($pdo, 'host', 'lawrenceks.gov');
    Frontier::sweepOutside($pdo);
    $back = $pdo->query("SELECT state FROM $T WHERE host = 'lawrenceks.gov'")->fetchColumn();
    $t('…a welcome rule brings it back to be read (Undo)', $back === 'pending', $back);
    try { require_once $app . '/lib/SearchIndex.php'; SearchIndex::setState($pdo, 'src:offregion_rules', '[]'); } catch (Throwable $x) {}
} else $t('the frontier table is there for the outside test', false, 'crawl_frontier missing in this schema');
$t('"Outside our region" is a state of its own, never handed out', isset(Frontier::STATES['outside']) && !in_array('outside', (new ReflectionClassConstant('Frontier', 'DUE'))->getValue(), true));

// 2. sites that blocked us: one quiet notice, each with its reason
$src = (string) file_get_contents($app . '/lib/crawler/Frontier.php');
$t('the blocked sites are one line, "N sites blocked us", each with its reason', str_contains($src, "\$out[] = ['held', count(\$held) . ' site'") && str_contains($src, 'EngineCase::blockReason((string) $s[\'held_why\'])'));
$ops = (string) file_get_contents($app . '/lib/Operations.php');
$t('…a notice, a warning only past the share of the day\'s reads (w0_block_share, 20 read at least)', str_contains($ops, "\$over ? 'warning' : 'notice'") && str_contains($ops, "\$over = \$nr >= 20 && 100 * \$nb / max(1, \$nr) > \$pct;"));
$t('…Reactivate in place, and every blocked site a tap away', str_contains($ops, "'Reactivate ' . \$hs['host'], 'ops', 'ops_w0_reactivate:'") && str_contains($ops, "'/admin/insights/index#held'"));
require_once $app . '/lib/Thresholds.php';
$t('…its share is a setting, 5% unless changed', (int) Thresholds::get($pdo, 'w0_block_share') === 5);

// 3. Its data: the records a process's calls touched
$pdo->exec('DELETE FROM control_records'); $pdo->exec('DELETE FROM control_ledger');
$pdo->exec("INSERT INTO control_ledger (id, endpoint, method, code, ms, at) VALUES (1, 'crawl/results', 'POST', 200, 40, UTC_TIMESTAMP()), (2, 'crawl/index', 'POST', 200, 10, UTC_TIMESTAMP())");
$pdo->exec("INSERT INTO control_records (ledger_id, at, kind, ref_id, what) VALUES (1, UTC_TIMESTAMP(), 'listing', 9, 'stored'), (1, UTC_TIMESTAMP(), 'review', 4, 'review'), (1, UTC_TIMESTAMP(), 'source', 7, 'refused'), (2, UTC_TIMESTAMP(), 'url', 5, 'stored')");
$p = ControlAlarms::procOfEndpoint($pdo, 'crawl/results');
[$rows, $total, $counts] = tv_cb_proc_records($pdo, ['proc' => str_replace(':', '-', $p)]);
$t('a process\'s data: its endpoints\' records only, newest first, with what happened', $total === 3 && count($rows) === 3 && $rows[0]['label'] === 'Source #7' && $rows[0]['what'] === 'Refused', $rows);
$t('…each links to its record, or to the call that touched it (never a dead end)', $rows[2]['href'] === '/admin/listing-edit.php?id=9' && $rows[1]['href'] === '/admin/listing-intake.php?row=4' && $rows[0]['href'] === '/admin/config/ledger#open=config/ledger:1');
$t('…counts by what happened for its filter; the filter narrows it', $counts === ['' => 3, 'refused' => 1, 'review' => 1, 'stored' => 1] && tv_cb_proc_records($pdo, ['proc' => str_replace(':', '-', $p), 'what' => 'stored'])[1] === 1, $counts);
$t('…a moment with its zone, for the time where it happened', str_ends_with($rows[0]['at'], '+00:00'));
$t('…ids and what happened only: no contents (0171 §4)', !array_diff(array_keys($rows[0]), ['id', 'label', 'href', 'what', 'call', 'at']));
$_GET['proc'] = 'wf-W1'; $own = tv_admin_mod_control($pdo); unset($_GET['proc']);
$t('its own page shows Its data as the admin\'s one table', str_contains($own, 'id="its-data"') && str_contains($own, 'config\/proc_records'));

// 4. Pulse's gathering time
ControlSensor::pulseTimed($pdo, 2400); for ($i = 0; $i < 5; $i++) ControlSensor::pulseTimed($pdo, 2600);
[$m, $n] = ControlSensor::pulseUsual($pdo);
$t('each open of Pulse is timed (the time only), its usual the middle of the last 20', $m === 2600 && $n === 6, [$m, $n]);
ControlSensor::pulseWatch($pdo);
$a = null; foreach (ControlAlarms::ofProc($pdo, 'site:pulse') as $x) $a = $x;
$t('over 2 s: a warning on the board naming the Pulse store, never a trip', $a && $a['level'] === 'warning' && str_contains($a['title'], 'Pulse is slow to gather (2.6 s). The fix is the Pulse store'), $a);
$pdo->exec("DELETE FROM control_readings WHERE proc = 'site:pulse'"); for ($i = 0; $i < 6; $i++) ControlSensor::pulseTimed($pdo, 300);
ControlSensor::pulseWatch($pdo);
$t('…back under the limit, it clears', !array_filter(ControlAlarms::ofProc($pdo, 'site:pulse'), fn ($x) => $x['cleared_at'] === null));
$t('Pulse is a part on the board, with its usual time where it stands', isset(ControlRegister::processes($pdo)['site:pulse']));
$pu = (string) file_get_contents($app . '/ui/pulse-ui.php');
$t('…timed around Pulse::items in pulse-ui (Pulse.php untouched)', str_contains($pu, 'ControlSensor::pulseTimed($pdo, (int) round((microtime(true) - $t0) * 1000))'));
$t('…and on the board\'s query timings', isset(ControlBoard::timings($pdo, [])['pulse']));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
