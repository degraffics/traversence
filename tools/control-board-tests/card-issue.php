<?php
// control-board-card (Jason, 2026-11-19, from an alarm's Open its card: "how am I supposed to fix this?"): a service's card in
// the Service register shows its open issue: what came back, how often, since when, what it means, what to do, and the
// buttons that do it (the board's own actions): Check and start again on a trip; else Check it now (never for a 429) and
// Mark resolved; and its alarm on the board. Run: mariadb < ../release-b-tests/start-schema.sql && php card-issue.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr((string) $got, 0, 300) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/lib/ControlAlarms.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec('DELETE FROM service_issues'); $pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence) VALUES
  (28, 'commoncrawl', 'index.commoncrawl.org', 'crawler', 'server', 'open', 6, '2026-11-19 16:02:00', '2026-11-19 17:10:00', '{\"latest\":{\"status\":503,\"body\":\"Service Unavailable\"}}'),
  (29, 'tavily', 'api.tavily.com', 'site', 'rate', 'open', 2, '2026-11-19 17:00:00', '2026-11-19 17:05:00', '{\"latest\":{\"status\":429,\"body\":\"Too many requests\"}}'),
  (30, 'claude', 'api.anthropic.com', 'site', 'auth', 'open', 1, '2026-11-19 17:00:00', '2026-11-19 17:00:00', '{\"latest\":{\"status\":401,\"error\":\"invalid x-api-key\"}}')");
$aid = ControlAlarms::raise($pdo, 'svc:issue:28', 'svc:commoncrawl', 'trip', 'held', 'index.commoncrawl.org: Its server failed', '', '', '', [], true, false);
$cc = tv_ops_issues($pdo, 'commoncrawl', 'x', '');
$t('a service with an open issue shows it on its card, with an anchor of its own', str_contains($cc, 'id="issue-28"') && str_contains($cc, 'Issue #28: Its server failed (tripped)'), $cc);
$t('…what came back, how often and from where, since when', str_contains($cc, 'HTTP 503 Service Unavailable') && str_contains($cc, '6 failures in a row, from the crawler, on Railway') && str_contains($cc, 'datetime="2026-11-19T16:02:00Z"'));
$t('…what it means and what to do (their server: nothing to change on our side)', str_contains($cc, 'What it means') && str_contains($cc, 'Nothing to change on our side: index.commoncrawl.org is failing on its own server'));
$t('…tripped: Check and start again (the board\'s resolve, on its alarm) and its alarm on the board', str_contains($cc, 'data-ops-cb="resolve" data-ops-id="' . $aid . '"') && str_contains($cc, 'href="/admin/config/control#cb-alarm-' . $aid . '"') && !str_contains($cc, 'data-ops-cb="svcresolve"'));
$tv = tv_ops_issues($pdo, 'tavily', 'x', '');
$t('a 429 has nothing to press: it waits its turn and says so', !str_contains($tv, 'data-ops-cb=') && str_contains($tv, 'after the wait it asked for; nothing to press'));
$cl = tv_ops_issues($pdo, 'claude', 'x', '');
$t('a refused key says it needs a person, what to do, then Check it now closes it', str_contains($cl, 'This one needs a person:') && str_contains($cl, 'press Check it now: a passing check is what closes it') && str_contains($cl, 'data-ops-cb="svccheck" data-ops-id="30"') && str_contains($cl, 'data-tv-status="bad"'));
$t('never Mark resolved on the card (Jason: "it should be the result of an issue being fixed")', !str_contains($cc . $tv . $cl, 'Mark resolved') && !str_contains($cc . $tv . $cl, 'svcresolve'));
$t('no open issue: nothing added to the card', tv_ops_issues($pdo, 'osm', 'x', '') === '');
$t('the buttons post the board\'s own actions with the page\'s token, busy while working, the answer beside the button', str_contains($cc, 'fetch("/admin/api/config/control"') && str_contains($cc, '"X-CSRF-Token":"x"') && str_contains($cc, 'TvAsk.busy(b,true)') && str_contains($cc, '{anchor:b,bad:!j.ok}'));
$src = (string) file_get_contents($app . '/admin/operations-console.php');
$t('the register draws it in each service\'s card', str_contains($src, '$b .= tv_ops_issues($pdo, (string) $k, $csrf, $tz);'));
// Pulse's steps match the board (the session manager, 2026-11-19): on a trip, Check and start again stands for Mark resolved
$s = fn ($l, $w = '', $act = null) => [$l, $act];
$labels = fn (int $id) => array_map(fn ($x) => $x[0], ControlAlarms::pulseSteps($pdo, $id, $s));
$tl = $labels($aid);
$t('a trip\'s Pulse case shows the board\'s buttons: Check and start again, no Mark resolved or Check it now', in_array('Check and start again', $tl, true) && !in_array('Mark resolved', $tl, true) && !in_array('Check it now', $tl, true), json_encode($tl));
$wid = ControlAlarms::raise($pdo, 'svc:issue:29', 'svc:tavily', 'warning', 'retry', 'api.tavily.com: asked us to slow down', '', '', '', [], true, false);
$wl = $labels($wid);
$t('…and a warning\'s case never offers Mark resolved either', !in_array('Mark resolved', $wl, true) && !in_array('Check and start again', $wl, true), json_encode($wl));
$bc = (string) file_get_contents($app . '/admin/control-console.php');
$t('the board no longer adds Mark resolved to a service issue\'s alarm', !str_contains($bc, "\$fxs[] = ['Mark resolved', 'svcresolve'"));
require_once $app . '/lib/ServiceHealth.php';
$st = ServiceHealth::steps(['service' => 'tavily', 'kind' => 'server', 'caller' => 'site', 'probe_url' => 'https://api.tavily.com/', 'host' => 'api.tavily.com']);
$t('a service case with a check of its own: Check it now, no Mark resolved', !in_array('Mark resolved', array_column($st, 't'), true) && in_array('Check it now', array_column($st, 't'), true), json_encode(array_column($st, 't')));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
