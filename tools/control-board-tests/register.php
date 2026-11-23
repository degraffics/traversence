<?php
// decisions/0173 release 1 (One home): the Service register takes in Outside services' cards; each service's stage with
// its button and its way back (Pause, Activate through the switch-on check), logged in service_stages; Overpass the first
// Retired record while OpenStreetMap stays Active; the board's Pause and Activate on a service; Watching on the board; the
// old address's links moved. Run: mariadb < ../release-b-tests/start-schema.sql && php register.php <root>/app
// (with app/migrations/2026-11-19_service_stages.sql in <root>/app/migrations).
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 400) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/lib/ServiceStages.php'; require_once $app . '/lib/ControlBoard.php';

// its database update: safe to run twice, Overpass recorded once
$sql = (string) file_get_contents($app . '/migrations/2026-11-19_service_stages.sql');
$pdo->exec('DROP TABLE IF EXISTS service_stages');
foreach ([1, 2] as $_) foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $q) $pdo->exec($q);
$t('the update runs twice cleanly; Overpass\'s retirement is recorded once', (int) $pdo->query("SELECT COUNT(*) FROM service_stages WHERE service = 'overpass' AND stage = 'retired'")->fetchColumn() === 1);
require_once $app . '/lib/staging-tables.php';
$t('service_stages names its staging class (records, like the switches)', StagingTables::classOf('service_stages') === 'records', StagingTables::classOf('service_stages'));

// the stages, from what's true now
$d = fn (array $keys, ?string $off, bool $ao, bool $ever) => ServiceStages::decide($keys, $off, $ao, $ever)[0];
$t('shipped off, no key yet: Added', $d([['K', '.env', false]], 'paused', true, false) === 'added');
$t('shipped off, key in place: Key in', $d([['K', '.env', true]], 'paused', true, false) === 'keyin');
$t('switched on once, now off: Paused', $d([['K', '.env', true]], 'paused', true, true) === 'paused' && $d([], 'paused', false, false) === 'paused');
$t('on with its key, or needing none: Active', $d([['K', '.env', true]], null, false, false) === 'active' && $d([], null, false, false) === 'active');
$t('on but its key missing: Added, and the words say it\'s on', $d([['K', '.env', false]], null, false, false) === 'added' && str_contains(ServiceStages::decide([['K', '.env', false]], null, false, false)[1], 'Switched on'));
$t('a key the site can\'t read (Railway) never counts as missing', $d([['K', 'Railway', null]], null, false, false) === 'active');
$t('every stage has its word and 0169\'s step', count(ServiceStages::STAGES) === 7 && ServiceStages::STAGES['active'][1] === 'Use' && ServiceStages::STAGES['retired'][1] === 'Remove');

// the register: one card a service, the register's parts first, the stage in the Status column and the filter
$html = tv_admin_mod_register($pdo);
preg_match_all('/class="os-card os-r" id="svc-([a-z0-9_-]+)"/', $html, $m);
$t('one card for each of the 44 services, the register\'s parts on each', count($m[1]) === count(OutsideServices::COMPANIES) && substr_count($html, 'class="os-stage"') === count(OutsideServices::COMPANIES), count($m[1]));
$t('…kept from the register: Used by, Its key, Known price, This month, Caps and paces', str_contains($html, '<dt>Used by</dt>') && str_contains($html, '<dt>Its key</dt>') && str_contains($html, '<dt>Known price</dt>') && str_contains($html, '<dt>This month</dt>') && str_contains($html, 'Caps and paces'));
// the four-zone card (03 §1, §3, §9): What it costs moved into the spend chip, the company's links into the slots and Its pages
$t('…kept from Outside services: Test, What it costs (the card\'s spend chip), the company\'s links (its slots), Terms and who to ask', str_contains($html, 'data-os-test=') && (str_contains($html, 'What it costs') || (str_contains($html, 'class="mc-spend') && str_contains($html, 'slot-'))) && str_contains($html, 'Terms and who to ask'));
$t('…its stage in the Status column, and Stage filters', (bool) preg_match('/class="os-rs">(Active|Added|Key in|Paused) · /', $html) && str_contains($html, 'data-fv="stage:active"') && str_contains($html, 'data-stage="'));
$t('the summary counts by stage, Retired among them', str_contains($html, '<span>Active</span>') && str_contains($html, '<span>Retired</span>'));
$t('Overpass is the first Retired record: why, what replaced it, the forum', str_contains($html, 'id="svc-overpass"') && str_contains($html, 'Overpass (OpenStreetMap&#039;s live query server): retired') && str_contains($html, 'our own copy from Geofabrik'));
$osm = substr($html, strpos($html, 'id="svc-osm"'), 3000);
$t('…and OpenStreetMap itself stays Active (our copy, the tiles, the credit)', (bool) preg_match('/class="os-rs">Active · /', $osm), mb_substr($osm, 0, 600));
$t('Watching Railway and the sync has moved to the board (a link to it)', str_contains($html, '/admin/config/control#cb-watch') && !str_contains($html, 'Switch watching on'));
$t('a business operation (Bluehost) offers no Pause, and says why', (bool) preg_match('/id="svc-bluehost".*?Not paused from here: its calls are business operations/s', $html));

// Pause and Activate on a card: the switch, its history, the way back
$pdo->exec('DELETE FROM automation_switches'); $pdo->exec("DELETE FROM automation_log"); $pdo->exec("DELETE FROM service_stages WHERE service <> 'overpass'");
Automation::forget();
$_SERVER['REQUEST_METHOD'] = 'POST';
$post = function (array $p) use ($pdo) { $_POST = $p + ['csrf' => 'x']; $r = tv_ops_ctx($pdo); $_POST = []; return $r[6]; };
$f = $post(['op_do' => 'svc_pause', 'ref' => 'wikimedia']);
$t('Pause needs a why (as Automation\'s own)', str_contains($f, 'data-tv-flash="error"'), $f);
$f = $post(['op_do' => 'svc_pause', 'ref' => 'wikimedia', 'why' => 'Testing the register']);
Automation::forget();
$t('Pause: the service is paused, and the card says so (the notice just confirms: "paused.")', str_contains($f, ': paused.') && Automation::serviceOff($pdo, 'wikimedia') !== null, $f);
$h = ServiceStages::history($pdo, 'wikimedia');
$t('…its stage logged with who and why, never a key', ($h[0]['stage'] ?? '') === 'paused' && ($h[0]['why'] ?? '') === 'Testing the register');
$st = ServiceStages::all($pdo);
$t('…its stage is now Paused (it was on before)', ($st['wikimedia'][0] ?? '') === 'paused', $st['wikimedia'] ?? null);
$f = $post(['op_do' => 'svc_activate', 'ref' => 'wikimedia']);
Automation::forget();
$red = Operations::blocking($pdo, 'wikimedia');
$t('Activate: the switch-on check first; on when it has no red line, else held with the reason', $red ? (str_contains($f, 'stays paused') && Automation::serviceOff($pdo, 'wikimedia') !== null) : (str_contains($f, ': activated, its check passed.') && Automation::serviceOff($pdo, 'wikimedia') === null), $f);
$t('…a red line keeps it paused and logs nothing new', $red ? count(ServiceStages::history($pdo, 'wikimedia')) === 1 : (ServiceStages::history($pdo, 'wikimedia')[0]['stage'] ?? '') === 'active');
$f = $post(['op_do' => 'svc_pause', 'ref' => 'bluehost', 'why' => 'x']);
$t('Bluehost can\'t be paused (business operations)', str_contains($f, 'data-tv-flash="error"'), $f);

// the board: a service's switch, Pause and Activate in its words
Automation::forget();
require_once $app . '/lib/ControlRegister.php'; $procs = ControlRegister::processes($pdo);
$p = ControlBoard::process($pdo, 'svc:usgs', $procs['svc:usgs'], Preflight::results($pdo));
$t('on the board a service has its own switch', in_array($p['switch'] ?? null, ['on', 'off'], true), $p['switch'] ?? null);
$pb = ControlBoard::process($pdo, 'svc:bluehost', $procs['svc:bluehost'], Preflight::results($pdo));
$t('…but not a business operation (Bluehost)', ($pb['switch'] ?? 'x') === '');
$say = ControlBoard::stopOne($pdo, 'svc:usgs', 1, 'Tester'); Automation::forget();
$t('Pause on the board: paused, its key stays, logged', str_contains($say, 'is paused') && Automation::serviceOff($pdo, 'usgs') !== null && (ServiceStages::history($pdo, 'usgs')[0]['stage'] ?? '') === 'paused', $say);
require_once $app . '/admin/control-console.php';
$row = tv_cb_row('svc:usgs', ['switch' => 'off'] + $p, []);
$t('…its stage pill says Paused, not Shut down', ($row['stage'][1] ?? '') === 'Paused', $row['stage'] ?? null);
$js = (string) file_get_contents($app . '/../website/js/admin-control.js');
$t('…the ⋯ menu asks "Pause …?" for a service', str_contains($js, "/^svc:/.test(r.id || '')") && str_contains($js, "'Pause it'"));
$w = tv_cb_watch($pdo, fn ($a, $l, $ok, $why, $id = '', $x = []) => '<button data-cb="' . $a . '" data-cb-id="' . $id . '">' . $l . '</button>');
$t('the board\'s Watching panel: each key named, never shown, and its switch', str_contains($w, 'id="cb-watch"') && str_contains($w, 'RAILWAY_API_TOKEN') && str_contains($w, 'data-cb="watch" data-cb-id="on"'));
$api = (string) file_get_contents($app . '/../website/admin/api.php');
$t('…its switch answers on the board\'s endpoint, with the register\'s permission', str_contains($api, "if (\$act === 'watch')") && str_contains($api, 'Operations::can()'));

// one address: the old one's links moved (Pulse's own lines go through the redirect); the old section is gone
$left = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app . '/..', FilesystemIterator::SKIP_DOTS)) as $fi) {
    $pth = (string) $fi; if (!preg_match('/\.(php|js)$/', $pth) || str_contains($pth, '/lib/Pulse.php') || str_contains($pth, 'router.php')) continue;
    if (preg_match('~/admin/config/services|\'config/services\'~', (string) file_get_contents($pth))) $left[] = basename($pth);
}
$t('no page links to /admin/config/services any more (Pulse.php\'s go through the redirect)', !$left, implode(', ', $left));
$t('the broken #amazon link goes to #svc-aws', !str_contains((string) file_get_contents($app . '/admin/comms-console.php'), '#amazon') && str_contains((string) file_get_contents($app . '/admin/comms-console.php'), 'register#svc-aws'));
require $app . '/admin/manifests.php';
$t('the menu has the Service register, and Outside services no more', isset(Modules::module('config')['sections']['register']) && !isset(Modules::module('config')['sections']['services']));
$t('alarms that pointed at the register\'s old Watching now open the board\'s', !str_contains((string) file_get_contents($app . '/lib/Operations.php'), 'register#watch'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
