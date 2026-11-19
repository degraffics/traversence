<?php
// control-board-landing (Jason, 2026-11-19: "i thought we fixed this. Open card shows the specific place to fix issues"):
// an alarm's evidence link lands on the one thing: a service's card (its anchor, kept by the side panel: peek-anchor.cjs),
// and for a workflow, task or the crawler, its run (the one its own fix names, else the run going when it was last seen,
// alarms in UTC, runs on the server's clock), the runs list only when there's no run yet.
// Run: mariadb < ../release-b-tests/start-schema.sql && php evidence.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
$pdo->exec('DELETE FROM crawl_runs');
$pdo->exec("INSERT INTO crawl_runs (id, started_at, status) VALUES (7, NOW() - INTERVAL 3 HOUR, 'done'), (8, NOW() - INTERVAL 1 HOUR, 'done'), (9, NOW() + INTERVAL 1 HOUR, 'running')");
$at = gmdate('Y-m-d H:i:s', time() - 1800);
$t('a service\'s alarm opens its own card in the register', tv_cb_evidence(['proc' => 'svc:aws', 'akey' => 'x']) === ['Open its card', '/admin/config/register#svc-aws']);
$t('a workflow\'s alarm opens the run going when it was last seen (UTC against the server\'s clock), never the list', tv_cb_evidence(['proc' => 'wf:W1', 'akey' => 'x', 'last_at' => $at, 'fix' => '']) === ['Open its latest run', '/admin/insights/crawler?run=8'], tv_cb_evidence(['proc' => 'wf:W1', 'akey' => 'x', 'last_at' => $at, 'fix' => '']));
$t('…or the run its own fix names (stored as JSON)', tv_cb_evidence(['proc' => 't:x', 'akey' => 'x', 'last_at' => $at, 'fix' => json_encode([['Its run', 'peek', '/admin/insights/crawler?run=7']])]) === ['Open its run', '/admin/insights/crawler?run=7']);
$t('…whether its fix comes as JSON or already read (the board\'s own list)', tv_cb_evidence(['proc' => 't:x', 'akey' => 'x', 'last_at' => $at, 'fix' => [['Its run', 'peek', '/admin/insights/crawler?run=7']]]) === ['Open its run', '/admin/insights/crawler?run=7']);
$pdo->exec('DELETE FROM crawl_runs');
$t('…the runs list only when there\'s no run yet', tv_cb_evidence(['proc' => 'wk:crawler', 'akey' => 'x', 'last_at' => $at, 'fix' => '']) === ['Open its runs', '/admin/insights/crawler']);
$ask = (string) file_get_contents(dirname($app) . '/website/js/tv-ask.js');
$t('the side panel puts its own settings before the # (the page inside lands on it)', str_contains($ask, "u = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'embed=1&peek=1' + (hi < 0 ? '' : url.slice(hi));"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
