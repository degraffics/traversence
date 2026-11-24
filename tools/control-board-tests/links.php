<?php
// control-board-links (2026-11-19): the last of 0171 §14 (Start's card and the switch-on check link to the board), and
// Jason's three from the board that morning: "Check and start again" checks first, so a trip whose check is still red
// stays open saying why (never "Resolved", then tripped again); a Resolve is also the acknowledgement, so the row leaves
// the queue for the logbook at once with nothing more to press; a group's header opens the group (its Holds line was a
// toggle that only unfolded its own short text).
// Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php links.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Preflight.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo); $pdo->exec('DROP TABLE IF EXISTS control_log'); ControlLog::ensure($pdo);
$trip = function (string $key, string $proc) use ($pdo) { $pdo->prepare("INSERT INTO control_alarms (akey, proc, level, kind, title, n_times, first_at, last_at, tripped_at) VALUES (?, ?, 'trip', 'check', 'old', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())")->execute([$key, $proc]); return (int) $pdo->lastInsertId(); };

// 1. a pre-flight trip whose check is still red: not resolved, and says which part (the vector index's case)
$lines = Preflight::lines($pdo); $red = null;
foreach (array_keys($lines) as $lid) { try { $r = Preflight::run($pdo, $lid, 'test', true); } catch (Throwable $x) { continue; } if (($r['s'] ?? '') === 'bad') { $red = $lid; break; } }
if ($red !== null) {
    $id = $trip('pf:' . $red, Preflight::procOf($red));
    [$ok, $msg] = ControlAlarms::resolve($pdo, $id, 1, 'Jason (staff)', '');
    $a = ControlAlarms::byId($pdo, $id);
    $t('Check and start again on a trip whose check is still red: not resolved, it says its check is red and what to do', !$ok && $a['cleared_at'] === null && str_contains($msg, 'its check is still red') && str_contains($msg, 'Fix that first'), $msg);
} else $t('(a red line to test with was found)', false);

// 2. a trip with nothing to check (an outside service's issue): resolved, and acknowledged by the same press
$id = $trip('x:test', 'svc:test');
[$ok, $msg] = ControlAlarms::resolve($pdo, $id, 7, 'Jason (staff)', '');
$a = ControlAlarms::byId($pdo, $id);
$t('a Resolve is also its acknowledgement: cleared, acknowledged by the same person, nothing more to press', $ok && $a['cleared_at'] !== null && $a['ack_name'] === 'Jason (staff)' && (int) $a['ack_uid'] === 7 && $a['ack_at'] !== null, [$ok, $a['ack_name'] ?? null]);
$t('…and it is in the logbook', (bool) array_filter(ControlLog::feed($pdo, 'svc:test'), fn ($e) => $e[2] === 'resolve' && $e[5] === 'Jason (staff)'));

// 3. the board: the resolved row leaves at once; the button greys when its own check is red; a group's header opens it
$js = file_get_contents(dirname($app) . '/website/js/admin-control.js'); $cc = file_get_contents($app . '/admin/control-console.php');
$t('the page takes a resolved row out of the queue at once and says it\'s in the logbook', str_contains($js, "(act === 'resolve' || (act === 'svccheck'") && str_contains($js, "&& !j.bad ? b.closest('li[id^=\"cb-alarm-\"]')") && str_contains($js, 's in the logbook.'));
$t('Check and start again is greyed while the trip\'s own check is red, saying which part', str_contains($cc, "'Its check is red: ' . \$redChecks[substr((string) \$a['akey'], 3)]"));
$t('a group\'s Holds line is plain words, so a press on its header opens the group', str_contains($cc, "'<b>Holds:</b> ' . \$e(implode(', ', \$nm))") /* task-trip (2026-11-24): a group of trips all started again reads "(nothing held: …)" instead */ && !str_contains($cc, "tv_cb_more(implode(', ', \$nm), 110"));

$api = file_get_contents(dirname($app) . '/website/admin/api.php'); $ca = file_get_contents($app . '/lib/ControlAlarms.php');
$t('Check it now on a service that isn\'t set up (az511, no key) closes its issue and says why; the watch does too', str_contains($api, "if ((\$r['s'] ?? '') === 'na') ServiceHealth::resolve(") && str_contains($api, "'Closed: '") && str_contains($ca, "if ((\$rr['s'] ?? '') === 'na') ServiceHealth::resolve("));
$t('…a check that leaves it open says "Still open", never "Success" alone; a closed one leaves the queue at once', str_contains($api, "'Still open: it hasn") && str_contains($api, "'bad' => !\$closed") && str_contains($js, "act === 'svccheck' && /^(Closed|It answered)/"));

// 4. 0171 §14: Start's card and the switch-on check link to the board
$ss = file_get_contents($app . '/lib/SystemStart.php'); $oc = file_get_contents($app . '/admin/operations-console.php');
$t('Start the system\'s card and the Workflow board\'s switch-on check link to the System Control Board', str_contains($ss, 'href="/admin/config/control">System Control Board</a>') && str_contains($oc, 'href="/admin/config/control">System Control Board</a>'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
