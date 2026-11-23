<?php
// decisions/0175 release 2a, part 1: the board as the one place to act. An alarm's main button opens the component that
// fixes it in the side panel, routed by the component (0175 §6: "The board opens the component or the workflow in a
// drawer, whichever the failure's source is"; 0176 release 3 routes by cause, later): a service's card, a pre-flight
// line, a workflow's or task's own page, a site job on Scheduled tasks. Not on that component's own page; never twice
// with its evidence. And a trip's words: it closes as Recovered when its check passes (0175 §3, ESCALATED_RECOVERS),
// never "waits for an admin's Resolve".
// Run: mariadb < ../release-b-tests/start-schema.sql && php one-place.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
$names = ['wf:W1' => 'W1 Listings', 't:osm' => 'OpenStreetMap from Geofabrik', 'wk:crawler' => 'The crawler', 'svc:aws' => 'AWS'];
$c = fn (string $proc, string $akey = 'x') => tv_cb_component(['proc' => $proc, 'akey' => $akey], $names);
$t('a service\'s alarm: its card', $c('svc:aws') === ['Open its card', '/admin/config/register#svc-aws']);
$t('…its pre-flight trip too (the card holds its line and key)', $c('svc:aws', 'pf:svc:aws') === ['Open its card', '/admin/config/register#svc-aws']);
$t('a pre-flight line of the site\'s: that line', $c('site:db', 'pf:site:db') === ['Open its pre-flight line', '/admin/config/preflight#pf-site-db'], $c('site:db', 'pf:site:db'));
$t('a workflow: its own page on the board', $c('wf:W1') === ['Open W1', '/admin/config/control/wf-W1']);
$t('a task, even by its pre-flight line: its own page (its row, check and switch)', $c('t:osm', 'pf:t:osm') === ['Open its page', '/admin/config/control/t-osm'], $c('t:osm', 'pf:t:osm'));
$t('a site job: its job on Scheduled tasks', $c('job:control_tick') === ['Open its job', '/admin/config/scheduler#job-control_tick']);
$t('the crawler: its own page', $c('wk:crawler') === ['Open its page', '/admin/config/control/wk-crawler']);
$t('something not on the board: no main button', $c('zz:none') === ['', '']);
// drawn on the board
require_once $app . '/lib/ControlAlarms.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
$ins = $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, means, todo, reading, n_times, first_at, last_at) VALUES (?, ?, 'warning', 'check', ?, 'm', 't', 'r', 1, UTC_TIMESTAMP() - INTERVAL 1 HOUR, UTC_TIMESTAMP())");
$ins->execute(['x:aws', 'svc:aws', 'AWS: something']); $ins->execute(['x:w1', 'wf:W1', 'W1: something']);
$al = array_values(array_filter(ControlAlarms::list($pdo), fn ($x) => $x['cleared_at'] === null));
$btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb="' . $act . '">' . $label . '</button>';
$h = tv_cb_alarms($al, ControlAlarms::counts($pdo), $names, fn ($id) => 'cb-p-' . str_replace(':', '-', $id), true, '', $btn);
$t('the board: each alarm\'s main button opens its component in the side panel', preg_match('#<a class="ak-b cb-xs go cb-main" href="/admin/config/register\#svc-aws" data-peek-frame>Open its card</a>#', $h) === 1 && preg_match('#class="ak-b cb-xs go cb-main" href="/admin/config/control/wf-W1" data-peek-frame>Open W1<#', $h) === 1);
$t('…first among its buttons', (bool) preg_match('#cb-acts[^>]*>\s*<a class="ak-b cb-xs go cb-main"#', $h), mb_substr($h, (int) strpos($h, 'cb-acts'), 200));
$t('…and the service\'s evidence, the same card, isn\'t a second button', substr_count($h, 'href="/admin/config/register#svc-aws"') === 1);
$t('the workflow\'s run stays its evidence, beside it', str_contains($h, 'Open its runs') || str_contains($h, 'Open its latest run'));
$hw = tv_cb_alarms(array_values(array_filter($al, fn ($x) => $x['proc'] === 'wf:W1')), ['open' => 1, 'trip' => 0, 'warning' => 1, 'notice' => 0], $names, fn ($id) => 'cb-p-' . str_replace(':', '-', $id), true, '', $btn, [], 'wf:W1');
$t('on W1\'s own page, its alarm doesn\'t offer W1 again', !str_contains($hw, 'cb-main'));
// the words
$src = (string) file_get_contents($app . '/lib/ControlAlarms.php') . (string) file_get_contents($app . '/lib/ControlMaintenance.php');
$t('no "waits for an admin\'s Resolve" or "only Resolve clears" left', !preg_match('/waits for an admin\\\\?\'s Resolve|only (an executive\\\\?\'s |an admin\\\\?\'s )?Resolve clears/', $src));
// 02 §5.3 removes "escalateTo(…, 2) at a trip" (R76: the Founder at once, the Manager at 2 h, the Director at 4 h by Pulse's
// clock), so a trip writes no Pulse escalation of its own; its words still say it closes as Recovered when its check passes
$t('a trip writes no Pulse escalation at the trip (R76), and its words say it closes as Recovered when its check passes', !str_contains($src, "Pulse::escalateTo(\$pdo, 'alarm:' . \$id, 2") && str_contains($src, 'It closes as Recovered when its check passes'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
