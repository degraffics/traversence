<?php
// decisions/0176 release 2 (Jason, 2026-11-20: "Restart R2"): a service's issue is one thread (§3, A3). The card's issue
// box, Pulse's svc: case, Outside services' line and the Start card's issue line read the same facts (ServiceHealth::facts),
// drawn by Thread in the one shape; Status from the state now (waitWords, never a stored date: §7); no × that looks like it
// dismisses it; no "Its alarm on the board"; the buttons kept (Check it now, Check and start again); its Pulse case reachable.
// Run: mariadb < ../release-b-tests/start-schema.sql && php r2.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/Thread.php'; require_once $app . '/lib/ServiceHealth.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, last_probe TEXT NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec('DELETE FROM service_issues'); $pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$past = gmdate('Y-m-d H:i:s', time() - 3600); $soon = gmdate('Y-m-d H:i:s', time() + 1800);
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence, hold_until) VALUES
  (28, 'commoncrawl', 'index.commoncrawl.org', 'crawler', 'server', 'open', 6, '2026-11-20 01:01:00', '2026-11-20 07:06:00', '{\"latest\":{\"status\":504,\"body\":\"<html><head><title>504 Gateway Time-out</title></head><body>nginx</body></html>\"}}', '" . ServiceHealth::TRIP_HOLD . "'),
  (31, 'geofabrik', 'download.geofabrik.de', 'crawler', 'timeout', 'open', 3, '2026-11-20 09:00:00', '2026-11-20 09:20:00', '{\"latest\":{\"error\":\"connection reset\"}}', '$past'),
  (32, 'tavily', 'api.tavily.com', 'site', 'rate', 'open', 2, '2026-11-20 10:00:00', '2026-11-20 10:05:00', '{\"latest\":{\"status\":429}}', '$soon'),
  (30, 'anthropic', 'api.anthropic.com', 'site', 'auth', 'open', 1, '2026-11-20 10:00:00', '2026-11-20 10:00:00', '{\"latest\":{\"status\":401,\"error\":\"invalid x-api-key\"}}', NULL)");
$aid = ControlAlarms::raise($pdo, 'svc:issue:28', 'svc:commoncrawl', 'trip', 'held', 'index.commoncrawl.org: Its server failed', '', '', '', [], true, false);
$row = fn (int $id) => $pdo->query('SELECT * FROM service_issues WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
$f28 = ServiceHealth::facts($pdo, $row(28)); $f31 = ServiceHealth::facts($pdo, $row(31)); $f32 = ServiceHealth::facts($pdo, $row(32)); $f30 = ServiceHealth::facts($pdo, $row(30));
$t('the title says what failed, in plain words, with the service\'s name', $f28['title'] === 'Common Crawl: its server failed at index.commoncrawl.org', $f28['title']);
$t('a trip\'s status: Paused by the system, since when, and it closes as Recovered (never "Backing off until Jan 1")', str_starts_with($f28['status'], 'Paused by the system (a trip) since {t:') && str_contains($f28['status'], 'closes as Recovered') && !str_contains($f28['status'] . Thread::text($f28), '2099') && !str_contains($f28['status'], 'Jan 1'), $f28['status']);
$t('a back-off whose wait is over says so, not a past date as the next try', str_contains($f31['status'], 'Its wait ended') && str_contains($f31['status'], 'the next call tries it again'), $f31['status']);
$t('a back-off still waiting: Backing off until its time', str_contains($f32['status'], 'Backing off until {t:'), $f32['status']);
$t('a stop kind is held for a person', str_starts_with($f30['status'], 'Held since {t:') && $f30['pill'] === 'Held', $f30['status']);
$t('what it affects: each need that uses the service, and its other sources', (bool) array_filter($f28['affects'], fn ($x) => str_contains($x, 'also from') || str_contains($x, 'no backup')), $f28['affects']);
$t('details: what came back, in words (a server\'s page by its title), how often, since, its number', in_array(['What came back', 'HTTP 504 504 Gateway Time-out'], $f28['details'], true) || str_contains(json_encode($f28['details']), '504 Gateway Time-out'), $f28['details']);
// the card
$cc = tv_ops_issues($pdo, 'commoncrawl', 'x', '');
$t('the card draws the thread: its title, status, what it affects, what the system is doing, what to do, Details ▾', str_contains($cc, 'class="tv-th"') && str_contains($cc, 'Common Crawl: its server failed at index.commoncrawl.org') && str_contains($cc, 'Status:') && str_contains($cc, 'What it affects:') && str_contains($cc, 'What the system is doing:') && str_contains($cc, 'What to do:') && str_contains($cc, 'Details ▾'), $cc);
$t('…no × and no status line that would get one', !str_contains($cc, 'data-tv-status') && !str_contains($cc, '×'));
$t('…no "Its alarm on the board" and no target="_top"', !str_contains($cc, 'Its alarm on the board') && !str_contains($cc, 'target="_top"'));
$t('…its buttons kept: Check and start again on a trip, its case in Pulse', str_contains($cc, 'data-ops-cb="resolve" data-ops-id="' . $aid . '"') && str_contains($cc, 'case=alarm%3A' . $aid));
$c31 = tv_ops_issues($pdo, 'geofabrik', 'x', '');
$t('…Check it now on a back-off; its case in Pulse is its own', str_contains($c31, 'data-ops-cb="svccheck" data-ops-id="31"') && str_contains($c31, 'case=svc%3A31'));
$t('…the box keeps its anchor for links', str_contains($cc, 'id="issue-28"') && str_contains($c31, 'id="issue-31"'));
// Pulse, Outside services, Start: the same words
require_once $app . '/lib/OutsideServices.php';
$w = array_values(array_filter(OutsideServices::working($pdo, 'geofabrik'), fn ($x) => str_contains((string) $x[0], 'download.geofabrik.de')));
$t('Outside services\' line: the thread\'s title and status, linking to the issue on the card, without ×', $w && $w[0][0] === $f31['title'] && $w[0][1] === Thread::plain($f31['status']) && $w[0][3] === '/admin/config/register#issue-31' && ($w[0][5] ?? '') === 'keep', $w);
$reg = tv_admin_mod_register($pdo); $gi = substr($reg, (int) strpos($reg, 'id="svc-geofabrik"'), 20000);
$t('…drawn on the card with data-tv-keep and "Its issue" landing in the same card', str_contains($gi, 'data-tv-keep data-tv-label="' . htmlspecialchars($f31['title'], ENT_QUOTES) . ':"') && str_contains($gi, 'href="#issue-31">Its issue</a>'), mb_substr(strip_tags($gi), 0, 300));
$ask = (string) file_get_contents(dirname($app) . '/website/js/tv-ask.js');
$t('tv-ask draws a data-tv-keep line without its ×', str_contains($ask, "if (m.hasAttribute('data-tv-keep')) box.querySelector('.tv-note-x').remove();"));
require_once $app . '/lib/Pulse.php';
$ps = (string) file_get_contents($app . '/lib/Pulse.php');
$t('Pulse\'s svc: case reads the thread\'s title and words (all but the details)', str_contains($ps, '$sf_ = ServiceHealth::facts($pdo, $si); $st_ = $sf_[\'title\']; $sw_ = Thread::text($sf_, \'pulse\');'));
$tx = Thread::text($f31, 'pulse');
$t('…which say the same as the card, without the details', str_contains($tx, 'Status: Since') && str_contains($tx, 'Its wait ended') && !str_contains($tx, 'connection reset'), $tx);
$ss = (string) file_get_contents($app . '/lib/SystemStart.php');
$t('the Start card\'s issue line reads the same facts, links to the card\'s issue, and has no ×', str_contains($ss, '$sf = ServiceHealth::facts($pdo, $r)') && str_contains($ss, 'href="/admin/config/register#issue-') && str_contains($ss, 'data-tv-keep data-tv-label="What to do:"'));
$sh = (string) file_get_contents($app . '/lib/ServiceHealth.php');
$t('no "mark resolved" left in the issue\'s words (a passing check closes it)', !preg_match('/(?<!no )mark(ed)? resolved/i', $sh), preg_match('/.{60}mark(ed)? resolved.{20}/i', $sh, $mm) ? $mm[0] : '');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
