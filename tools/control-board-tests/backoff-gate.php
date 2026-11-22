<?php
// control-board-backoff-gate (2026-11-19): a back-off in its wait isn't a hold. decisions/0171 §6's 3c note: "Held or out
// (a refusal, a refused key, a used-up quota or budget) trips at once"; "a back-off trips at its case limit". Wikidata's
// 429 made Wikimedia "held", W1's switch-on line red, and W1 tripped. Now a back-off whose alarm hasn't tripped is
// "paced" (the gate stays green, its own issue a warning); a refusal, or a back-off tripped at its limit, still holds.
// Run: mariadb < ../release-b-tests/start-schema.sql && php backoff-gate.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Operations.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec('DROP TABLE IF EXISTS service_issues');
$pdo->exec("CREATE TABLE service_issues (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40) NOT NULL, host VARCHAR(190) NOT NULL, caller VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL DEFAULT 'open',
    hits INT NOT NULL DEFAULT 1, first_at DATETIME NULL, last_at DATETIME NULL, evidence TEXT NULL, hold_until DATETIME NULL, probe_url VARCHAR(255) NULL, probe_every_min INT NULL, probe_agent VARCHAR(40) NULL, next_probe_at DATETIME NULL, last_probe TEXT NULL,
    resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$add = function (string $kind) use ($pdo) { $pdo->exec("UPDATE service_issues SET state = 'resolved'"); $pdo->prepare("INSERT INTO service_issues (service, host, caller, kind, hits, first_at, last_at, hold_until) VALUES ('wikimedia', 'query.wikidata.org', 'site', ?, 3, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)")->execute([$kind, gmdate('Y-m-d H:i:s', time() + 1800)]); return (int) $pdo->lastInsertId(); };
$st = fn () => Operations::status($pdo, 'wikimedia', []);

// 1. a 429 in its wait, its alarm a warning: paced, not held; the gate's line for it isn't red for a hold
$id = $add('rate');
ServiceHealth::alarm($pdo, $id, 'wikimedia', 'query.wikidata.org', 'rate', ['status' => 429, 'error' => '', 'body' => 'Too Many Requests'], 'site');
$s = $st();
$t('a 429 in its wait (its alarm a warning): Wikimedia is paced, never held', $s[0] === 'paced' && str_contains($s[1], 'Backing off: issue #' . $id) && str_contains($s[1], 'by itself'), $s);
$t('…its issue still holds its own calls (it waits), only the gate is free', ServiceHealth::holding(ServiceHealth::openAll($pdo)[0] ?? []) === true);

// 2. a timeout in its wait, below its limit: paced too
$id = $add('timeout');
ServiceHealth::alarm($pdo, $id, 'wikimedia', 'query.wikidata.org', 'timeout', ['status' => 0, 'error' => 'timed out', 'body' => ''], 'site');
$t('a timeout below its case limit (a warning): paced', $st()[0] === 'paced', $st());

// 3. a back-off tripped when its tries run out: held (since the control board's build, the tries are the one engine's
// checks of the service, P3, not its own case limit; its trip holds)
require_once $app . '/lib/ControlEngine.php';
ControlEngine::$checker = fn (string $c) => ['pass' => false, 'proof' => 'query.wikidata.org didn\'t answer.', 'ms' => 15000];
foreach (ControlEngine::TRY_AT as $_) { $pdo->exec("UPDATE control_alarms SET next_try_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE akey = 'svc:issue:$id' AND cleared_at IS NULL"); ControlAlarms::expireTries($pdo); }
ControlEngine::$checker = null;
$a = ControlAlarms::openOf($pdo, 'svc:issue:' . $id);
$t('a back-off tripped when its tries ran out: its alarm a trip, and the service held', $a && $a['level'] === 'trip' && $st()[0] === 'held', [$a['level'] ?? null, $st()]);

// 4. a refusal: held at once
$id = $add('ban');
$t('a refusal holds at once (held or out trips at once)', $st()[0] === 'held', $st());

// 5. the switch-on check's service line reads the status: a paced service isn't a red line
$src = file_get_contents($app . '/lib/Operations.php');
$t('the switch-on check reds a service only when held or out, so a back-off in its wait leaves the gate green', str_contains($src, "if (\$r['status'][0] === 'held') \$why[] = 'held: '") && str_contains($src, 'if (!ServiceHealth::spentHold($pdo, $si))'));

// 6. a process with an open trip: its row offers Check and start again (its Resolve), never a bare Start; its Check's
// notice says the trip stays red until then (Jason, 2026-11-19: the notice read as cleared beside a red X)
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php';
$pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at, tripped_at) VALUES ('pf:wf:W1', 'wf:W1', 'trip', 'check', 'W1: its gate', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 2 HOUR)");
$aid = (int) $pdo->lastInsertId();
$btn = fn ($act, $label, $ok, $why, $id = '', $attr = []) => '<button data-cb="' . $act . '" data-cb-id="' . $id . '">' . $label . '</button>';
$h = tv_cb_controls('wf:W1', ['switch' => 'off', 'name' => 'W1 Listings'], [], $btn, true, '', true, '', true);
$t('a tripped process\'s row: Check and start again on its trip, no bare Start', str_contains($h, 'data-cb="resolve" data-cb-id="' . $aid . '">Check and start again') && !str_contains($h, 'data-cb="start1"'), $h);
$h2 = tv_cb_controls('wf:W2', ['switch' => 'off', 'name' => 'W2'], [], $btn, true, '', true, '', true);
$t('…a process with no trip keeps its Start', str_contains($h2, 'data-cb="start1"'), $h2);
$api = file_get_contents(dirname($app) . '/website/admin/api.php');
$t('…and a Check on a tripped process says its trip stays red until Check and start again', str_contains($api, "stays red until you press Check and start again on this row"));

// 7. the 10-minute release job quiet for 30 minutes: a warning on the board, cleared when it looks again
require_once $app . '/lib/Releases.php'; Releases::ensure($pdo);
$pdo->prepare("REPLACE INTO site_release_settings (k, v) VALUES ('git_seen_job', ?)")->execute([json_encode(['at' => time() - 3600, 'found' => 0])]);
try { ControlAlarms::tick($pdo); } catch (Throwable $x) {}
$q = ControlAlarms::openOf($pdo, 'rel:job:quiet');
$t('the release job not looking in git for 30 minutes: a warning naming when it last looked, with Check for builds beside it', $q && $q['level'] === 'warning' && str_contains((string) $q['title'], 'hasn\'t looked in git since') && str_contains((string) $q['fix'], '/admin/config/releases'), $q['title'] ?? null);
$pdo->prepare("REPLACE INTO site_release_settings (k, v) VALUES ('git_seen_job', ?)")->execute([json_encode(['at' => time() - 60, 'found' => 1])]);
try { ControlAlarms::tick($pdo); } catch (Throwable $x) {}
$t('…cleared when it looks again', ControlAlarms::openOf($pdo, 'rel:job:quiet') === null);
$t('health.php reports when the site last looked in git, by whom', str_contains(file_get_contents(dirname($app) . '/website/api/health.php'), "\$out['releases_looked']"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
