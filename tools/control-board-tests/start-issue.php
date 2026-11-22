<?php
// start-issue (Jason, 2026-11-19, on Start the system's "Mark issue resolved": "If this was resolved by system or marked
// as resolved by admin it should be handled"): the Start card's service issue carries the board's buttons (Check and start
// again on a trip, else Check issue now, It's fixed: try it again only where nothing can check), never a bare Mark
// resolved; and whichever way it closes, the issue and its alarm close together, saying how: the system's check closes a
// tripped alarm too (through the board's resolve: it checks, closes, starts again), a person's Check and start again
// closes the issue. Run: mariadb < ../release-b-tests/start-schema.sql && php start-issue.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n"; } };
require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ServiceHealth.php'; require_once $app . '/lib/SystemStart.php'; require_once $app . '/lib/OutsideServices.php'; require_once $app . '/lib/Automation.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12) DEFAULT 'open', hits INT DEFAULT 1, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL)");
$pdo->exec('DELETE FROM service_issues');
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, hits, first_at, last_at, evidence, probe_url) VALUES
  (28, 'commoncrawl', 'index.commoncrawl.org', 'crawler', 'server', 6, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":503}}', 'https://index.commoncrawl.org/collinfo.json'),
  (30, 'tavily', 'api.tavily.com', 'site', 'auth', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":401}}', 'https://api.tavily.com/'),
  (31, 'nps', 'developer.nps.gov', 'site', 'auth', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":401}}', NULL)");
$aid = ControlAlarms::raise($pdo, 'svc:issue:28', 'svc:commoncrawl', 'trip', 'held', 'index.commoncrawl.org: Its server failed', '', '', '', [], true, false);
$form2 = fn (string $do, string $label, string $ref) => '<button name="au_do" value="' . $do . '" data-ref="' . $ref . '">' . htmlspecialchars($label) . '</button>';
$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$inline = (new ReflectionMethod('SystemStart', 'issueInline'));
$inline->setAccessible(true);
$h28 = $inline->invoke(null, $pdo, 28, 'W3', $form2, $e);
$t('a tripped issue on the Start card: Check and start again, on its alarm', str_contains($h28, 'value="start_issue_restart" data-ref="' . $aid . '">Check and start again</button>'), $h28);
$t('…no Mark resolved anywhere on the card', !str_contains($h28, 'Mark issue') && !str_contains($h28, 'Mark it resolved'), $h28);
$h30 = $inline->invoke(null, $pdo, 30, 'W3', $form2, $e);
$t('a refused key (not tripped): Check issue now, and it says a passing check closes it', str_contains($h30, 'value="start_issue_check" data-ref="30">Check issue #30 now</button>') && str_contains($h30, 'a passing check is what closes it'), $h30);
$h31 = $inline->invoke(null, $pdo, 31, 'W3', $form2, $e);
$t('nothing can check it: It\'s fixed: try it again', str_contains($h31, 'value="start_issue_resolve" data-ref="31">It&#039;s fixed: try it again</button>') || str_contains($h31, '>It\'s fixed: try it again</button>'), $h31);
// closed by the system's check: the tripped alarm closes too, saying how
ServiceHealth::resolve($pdo, 28, 0, 'It answered again.');
$a = $pdo->query("SELECT cleared_at, cleared_how FROM control_alarms WHERE id = $aid")->fetch(PDO::FETCH_ASSOC);
$t('the system\'s check closes the issue and its tripped alarm, saying how', $a['cleared_at'] !== null && str_starts_with((string) $a['cleared_how'], 'Recovered:'), $a);   // 02 §3c, row 90: Recovered, never "Resolved by the system"
// a person's Check and start again closes the issue too
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, hits, first_at, last_at, evidence, probe_url) VALUES (40, 'brave', 'api.search.brave.com', 'site', 'server', 6, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{}', 'https://api.search.brave.com/')");
$a2 = ControlAlarms::raise($pdo, 'svc:issue:40', 'svc:brave', 'trip', 'held', 'Brave: Its server failed', '', '', '', [], true, false);
[$ok, $msg] = ControlAlarms::resolve($pdo, $a2, 7, 'Jason', 'Checked and started again on Start the system');
$st = $pdo->query("SELECT state, resolved_how FROM service_issues WHERE id = 40")->fetch(PDO::FETCH_ASSOC);
$t('a person\'s Check and start again closes the alarm and its issue, saying who', $ok && $st['state'] === 'resolved' && str_contains((string) $st['resolved_how'], 'Jason'), [$ok, $msg, $st]);
$ac = (string) file_get_contents($app . '/admin/automation-console.php');
$t('the Start card\'s actions: Check and start again through the board\'s resolve; no "Marked resolved"', str_contains($ac, "case 'start_issue_restart':") && str_contains($ac, 'ControlAlarms::resolve($pdo, (int) $ref, $uid, $who') && !str_contains($ac, 'Marked resolved on Start the system'));
// a used-up quota whose probe answers again closes its trip as Recovered (the state model §3c; principle 1's exception, a
// person reactivating, is only for a site that refused or blocked us: a quota resets by itself, and live already did this)
$pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, hits, first_at, last_at, evidence, probe_url) VALUES (50, 'tavily', 'api.tavily.com', 'site', 'quota', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{}', 'https://api.tavily.com/')");
$a3 = ControlAlarms::raise($pdo, 'svc:issue:50', 'svc:tavily', 'trip', 'held', 'Tavily: over its quota', '', '', '', [], true, false);
ServiceHealth::resolve($pdo, 50, 0, 'Its probe answered 200.');
$t('a used-up quota answering again closes its trip by itself, as Recovered', ControlAlarms::openOf($pdo, 'svc:issue:50') === null);
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
