<?php
// The Control Board's release 3d (decisions/0171 §8's note; Jason, 2026-11-18, "Board and Pulse"): alarms sharing a
// system, level and kind are one group row that opens to them, on the board and in Pulse; red only for a trip or a core
// host down, amber for what's held. Run: mariadb < ../release-b-tests/start-schema.sql
//      && mariadb lv_main < <2a's ledger migration> && php r3d.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms');

// the key: system, level, kind's family
$t('workflows and tasks are the engine', ControlAlarms::systemOf('wf:W5') === 'engine' && ControlAlarms::systemOf('t:recheck') === 'engine');
$t('workers, and the site\'s own parts', ControlAlarms::systemOf('wk:crawler') === 'workers' && ControlAlarms::systemOf('job:daily_report') === 'site' && ControlAlarms::systemOf('stage:start') === 'site');
$t('a service a workflow reads is the engine\'s; one only the site uses is a service', ControlAlarms::systemOf('svc:overture') === 'engine' && ControlAlarms::systemOf('svc:bluehost') === 'services', [ControlAlarms::systemOf('svc:overture'), ControlAlarms::systemOf('svc:bluehost')]);
$t('families: held, failing, slow, check', ControlAlarms::familyOf('held') === 'held' && ControlAlarms::familyOf('silent') === 'held' && ControlAlarms::familyOf('crash') === 'failing' && ControlAlarms::familyOf('retry') === 'failing' && ControlAlarms::familyOf('slow') === 'slow' && ControlAlarms::familyOf('count') === 'check');

// five trips of one cause: one group; a lone one stays its own row. 02 row 127 (§5.3: "groups and groupKey group by cause or
// incident"; the board-system grouping "engine feeds" is removed): the five share their cause, Overture held, so they're one
// Incident Report and one group named by it (the test asserted the replaced "5 engine feeds held" grouping until this build)
ControlAlarms::raise($pdo, 'svc:overture:held', 'svc:overture', 'trip', 'held', 'Overture Maps is held', '', '', '', [], true, true, 'svc:overture');   // OpenStreetMap's Overpass is retired (2026-11-19): Overture stands in as an engine feed
foreach (['W5', 'W6', 'W8', 'W9'] as $w) ControlAlarms::raise($pdo, 'x:' . $w, 'wf:' . $w, 'trip', 'held', $w . ' is held: its source is deferred', '', '', '', [], true, true, 'svc:overture');
ControlAlarms::raise($pdo, 'job:daily_report', 'job:daily_report', 'warning', 'crash', 'The job "daily_report" stopped');
// the bench has no founder, so the trips also raised the dispatcher's own (02 P11; scenario S-B80 tests it): not part of this fixture
$pdo->exec("DELETE FROM control_alarms WHERE akey = 'dispatch:nobody'");
$g = ControlAlarms::groups(ControlAlarms::list($pdo)); $gk = (string) array_key_first($g);
$t('five trips of one cause: one group (their incident), worst first', count($g) === 1 && str_starts_with($gk, 'ir:') && str_contains($g[$gk]['title'], 'Correlated · 5 parts') && count($g[$gk]['ids']) === 5, $g);
$t('…the lone site job stays its own row', !array_filter($g, fn ($x) => in_array((int) ControlAlarms::openOf($pdo, 'job:daily_report')['id'], $x['ids'], true)));
$t('…its members, read back by key', count(ControlAlarms::groupMembers($pdo, 'alarms:' . $gk)) === 5);
$n = ControlAlarms::ackGroup($pdo, $gk, 7, 'Jason');
$t('Acknowledge all: each acknowledged, each still open', $n === 5 && ControlAlarms::openOf($pdo, 'x:W5')['ack_at'] !== null && ControlAlarms::openOf($pdo, 'x:W5')['cleared_at'] === null);
$t('…a second press acknowledges none', ControlAlarms::ackGroup($pdo, $gk, 7, 'Jason') === 0);

// Pulse: the group stands in for its alarm items, which ride along as children with their own keys
$items = [];
foreach (ControlAlarms::list($pdo) as $a) $items[] = ['key' => 'alarm:' . $a['id'], 'severity' => $a['level'] === 'trip' ? 'critical' : 'alert', 'title' => (string) $a['title'], 'why' => '', 'when' => (string) $a['first_at'], 'url' => '/admin/config/control#cb-alarm-' . $a['id'], 'state' => '', 'dept' => 'ops', 'esc' => 2, 'esc_to_me' => false, 'over' => false, 'sla_due' => null];
$items[] = ['key' => 'paid:9', 'severity' => 'event', 'title' => 'Paid', 'state' => '', 'when' => ''];
$out = ControlAlarms::pulseGroups($pdo, $items);
$grp = array_values(array_filter($out, fn ($i) => str_starts_with((string) $i['key'], 'alarms:')));
$t('Pulse: one item for the five, critical, its children the five alarm items', count($grp) === 1 && $grp[0]['key'] === 'alarms:' . $gk && $grp[0]['severity'] === 'critical' && count($grp[0]['children']) === 5 && str_starts_with((string) $grp[0]['children'][0]['key'], 'alarm:'), $grp);
$t('…the lone alarm and other items unchanged', count($out) === 3 && in_array('paid:9', array_column($out, 'key'), true));
$items[0]['state'] = 'done';
$out2 = ControlAlarms::pulseGroups($pdo, $items);
$t('…a child marked done leaves the group (four left)', count(array_values(array_filter($out2, fn ($i) => str_starts_with((string) $i['key'], 'alarms:')))[0]['children']) === 4);

// the board: the group row, its Acknowledge all, the alarms inside with their own buttons; amber for the held
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; require_once $app . '/admin/control-console.php';
$btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb="' . $act . '" data-cb-id="' . htmlspecialchars($id) . '">' . htmlspecialchars($label) . '</button>';
$pdo->exec("UPDATE control_alarms SET ack_at = NULL WHERE akey = 'x:W9'");
$html = tv_cb_alarms(ControlAlarms::list($pdo), ControlAlarms::counts($pdo), ['wf:W5' => 'W5 Events', 'svc:overture' => 'Overture Maps'], fn ($id) => 'cb-p-' . str_replace(':', '-', $id), true, '', $btn);
$t('the board: one group row with its title, opening to its five alarms', substr_count($html, 'class="cb-al-grp') === 1 && str_contains($html, 'Correlated · 5 parts') && substr_count($html, 'id="cb-alarm-') === 6, substr_count($html, 'id="cb-alarm-'));
$t('…Acknowledge all for the one still unacknowledged', str_contains($html, 'data-cb="ackgroup" data-cb-id="' . $gk . '"'));
$t('…each alarm inside keeps its own buttons (Acknowledge, Resolve)', substr_count($html, 'data-cb="resolve"') >= 5);
$t('…its fold has an id, so the 30-second redraw keeps it open', str_contains($html, 'id="cb-grpd-' . str_replace(':', '-', $gk) . '"'));
$t('marks: a trip red, a warning or a hold amber, from the tokens (no literal)', str_contains(tv_cb_mark('bad'), 'var(--cb-fail)') && str_contains(tv_cb_mark('hold'), 'var(--cb-hold)') && str_contains(tv_cb_mark('warn'), 'var(--cb-hold)'));
$css = file_get_contents($app . '/admin/control-console.php');
$t('…the tokens fall back to the palette\'s values; no other literal red or amber', str_contains($css, '--cb-fail:var(--tv-fail,#a62b2b)') && str_contains($css, '--cb-hold:var(--tv-hold,#8f5600)') && substr_count($css, '#a62b2b') === 1 && substr_count($css, '#8f5600') === 1 && !preg_match('/#(FBEDED|FBF1DE|FEF6F6|E6B8B8|E5CFA3|B91C1C)/i', $css));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
