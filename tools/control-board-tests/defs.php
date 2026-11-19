<?php
// decisions/0173 release 3 (defined once): every outside service is one entry in app/lib/service-defs.php and the lists the
// code reads are generated from it (tools/page-audit/service-defs.php; the zip check blocks drift); Overpass is its own
// retired entry while OpenStreetMap stays; Retire and Bring it back; Proposed only records words; the Removed list.
// Run: mariadb < ../release-b-tests/start-schema.sql (and the two 0173 migrations) && php defs.php <root>/app [<live app>]
// With a live app (the mirror's sync-app), every list is compared value for value: only Overpass's split may differ.
declare(strict_types=1);
$app = $argv[1]; $live = $argv[2] ?? '';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 400) : '') . "\n"; } };
$gen = __DIR__ . '/../page-audit/service-defs.php';

// the generator: the lists match the file; a hand edit is caught and the refusal names its fix
exec('php ' . escapeshellarg($gen) . ' --check ' . escapeshellarg($app) . ' 2>&1', $o, $rc);
$t('every list matches app/lib/service-defs.php', $rc === 0, implode("\n", $o));
$tmp = sys_get_temp_dir() . '/defs-' . getmypid(); @mkdir($tmp . '/lib', 0777, true);
foreach (['service-defs.php', 'OutsideServices.php', 'Operations.php', 'StagingGate.php', 'ServiceStages.php'] as $f) copy($app . '/lib/' . $f, $tmp . '/lib/' . $f);
file_put_contents($tmp . '/lib/OutsideServices.php', str_replace("'nps' => ['npgallery.nps.gov'", "'nps' => ['hand.example.org', 'npgallery.nps.gov'", (string) file_get_contents($tmp . '/lib/OutsideServices.php')));
$o = []; exec('php ' . escapeshellarg($gen) . ' --check ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
$t('a list edited by hand is refused, naming it and the fix', $rc === 1 && str_contains(implode(' ', $o), 'OutsideServices::HOSTS') && str_contains(implode(' ', $o), 'run: php tools/page-audit/service-defs.php --write'), implode("\n", $o));
$o = []; exec('php ' . escapeshellarg($gen) . ' --write ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc); $o2 = []; exec('php ' . escapeshellarg($gen) . ' --check ' . escapeshellarg($tmp) . ' 2>&1', $o2, $rc2);
$t('…and --write puts the file\'s version back', $rc2 === 0 && !str_contains((string) file_get_contents($tmp . '/lib/OutsideServices.php'), 'hand.example.org'));
array_map('unlink', glob($tmp . '/lib/*')); @rmdir($tmp . '/lib'); @rmdir($tmp);
$zc = (string) file_get_contents(__DIR__ . '/../page-audit/zipcheck.py');
$t('the zip check runs it on any zip carrying one of these files', str_contains($zc, "def service_defs_check") && str_contains($zc, "'--check'") && str_contains($zc, 'or defs_bad else 0)'));

// the lists, value for value against the live ones: only Overpass's split differs
$dump = fn (string $a) => json_decode((string) shell_exec('php -r ' . escapeshellarg('$a=' . var_export($a, true) . '; require_once "$a/lib/Database.php"; foreach (["OutsideServices","Operations","StagingGate","ServiceStages"] as $c) require_once "$a/lib/$c.php"; echo json_encode(["COMPANIES"=>OutsideServices::COMPANIES,"HOSTS"=>OutsideServices::HOSTS,"CONTACT"=>OutsideServices::CONTACT,"LEDGER"=>OutsideServices::LEDGER,"NOTICES"=>OutsideServices::NOTICES,"KEYS"=>Operations::KEYS,"CAPS"=>Operations::CAPS,"ADDED_OFF"=>Operations::ADDED_OFF,"ANSWERS"=>StagingGate::ANSWERS,"RETIRED"=>ServiceStages::RETIRED]);')), true);
$new = $dump($app);
if ($live !== '') {
    $old = $dump($live); $moved = [];
    foreach ($old as $c => $v) {
        if ($c === 'COMPANIES' || $c === 'ADDED_OFF' || $c === 'RETIRED') { $t($c . ': the same values, in the same order', $v === $new[$c]); continue; }
        foreach (array_unique(array_merge(array_keys($v), array_keys($new[$c]))) as $k) if (($v[$k] ?? null) !== ($new[$c][$k] ?? null)) $moved[] = $c . '.' . $k;
    }
    sort($moved);
    $t('every other list: only osm and overpass differ (Overpass\'s split)', !array_diff($moved, ['ANSWERS.overpass', 'CAPS.osm', 'CAPS.overpass', 'CONTACT.osm', 'CONTACT.overpass', 'HOSTS.osm', 'HOSTS.overpass', 'NOTICES.osm', 'NOTICES.overpass']), $moved);
    $t('…and nothing of Overpass\'s was lost: osm\'s old addresses are all still on a list', !array_diff($old['HOSTS']['osm'], array_merge($new['HOSTS']['osm'], $new['HOSTS']['overpass'])) && $old['CAPS']['osm'] === $new['CAPS']['overpass'] && $old['NOTICES']['osm'] === $new['NOTICES']['overpass']);
}
$seen = []; $dup = []; foreach ($new['HOSTS'] as $k => $hs) foreach ($hs as $h) { if (isset($seen[$h])) $dup[] = $h; $seen[$h] = $k; }
$t('no address belongs to two services (so the lists\' order changes nothing)', !$dup, $dup);

// Overpass is its own retired entry; OpenStreetMap stays
require_once $app . '/lib/Database.php'; require_once $app . '/lib/OutsideServices.php'; require_once $app . '/lib/StagingGate.php'; require_once $app . '/lib/ServiceStages.php'; require_once $app . '/lib/Operations.php';
$defs = include $app . '/lib/service-defs.php';
$t('Overpass is one entry, retired, with no card', ($defs['overpass']['stage'] ?? '') === 'retired' && !isset(OutsideServices::COMPANIES['overpass']));
$t('…its addresses, contacts, gates and notice are its own now', OutsideServices::serviceOfHost('overpass-api.de') === 'overpass' && OutsideServices::serviceOfHost('maps.mail.ru') === 'overpass' && !array_intersect(OutsideServices::HOSTS['osm'], ['overpass-api.de', 'overpass.kumi.systems']) && in_array('osm_daily_crawler', Operations::CAPS['overpass'], true) && !isset(Operations::CAPS['osm']) && isset(OutsideServices::NOTICES['overpass']) && !isset(OutsideServices::NOTICES['osm']));
$t('…held on staging, while OpenStreetMap\'s tiles are still read there', StagingGate::ANSWERS['overpass'] === 'hold' && StagingGate::ANSWERS['osm'] === 'read' && OutsideServices::serviceOfHost('tile.openstreetmap.org') === 'osm');
$t('…its story comes from its entry (ServiceStages::RETIRED, generated)', ServiceStages::RETIRED['overpass'] === $defs['overpass']['record'] && isset(ServiceStages::records()['overpass']));
$t('OpenStreetMap keeps its card and its credit', isset(OutsideServices::COMPANIES['osm']) && str_contains(json_encode(OutsideServices::CONTACT['osm']), 'copyright'));

// Retire and Bring it back, on a database
$pdo = Database::connection();
foreach (glob($app . '/migrations/2026-11-*_service_*.sql') as $f) foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', (string) file_get_contents($f))))) as $q) $pdo->exec($q);
$pdo->exec("DELETE FROM service_stages WHERE service <> 'overpass'"); $pdo->exec('DELETE FROM service_proposals'); $pdo->exec('DELETE FROM automation_switches'); $pdo->exec('DELETE FROM automation_log');
require_once $app . '/lib/Automation.php'; Automation::forget();
$say = ServiceStages::retire($pdo, 'wikimedia', 'Replaced by our own Wikidata dump', 'our own dump', 1, 'Tester'); Automation::forget();
$t('Retire: nothing of ours calls it (paused), its stage is Retired with why and what replaced it', str_contains($say, 'is retired') && Automation::serviceOff($pdo, 'wikimedia') !== null && (ServiceStages::all($pdo)['wikimedia'][0] ?? '') === 'retired' && str_contains(ServiceStages::all($pdo)['wikimedia'][1], 'Replaced by our own dump'), $say);
$err = ''; try { Automation::set($pdo, 'service', 'wikimedia', false, '', 1, 'Tester'); } catch (Throwable $x) { $err = $x->getMessage(); }
$t('…it can\'t be switched on anywhere (Automation, the board, its card) until it\'s brought back', str_contains($err, 'is retired: Bring it back'), $err);
$err = ''; try { ServiceStages::retire($pdo, 'bluehost', 'x', '', 1, 'T'); } catch (Throwable $x) { $err = $x->getMessage(); }
$t('a business operation (Bluehost) isn\'t retired from here', str_contains($err, 'business operations'), $err);
$err = ''; try { ServiceStages::retire($pdo, 'usgs', '', '', 1, 'T'); } catch (Throwable $x) { $err = $x->getMessage(); }
$t('Retire asks why', str_contains($err, 'Say why'), $err);
$say = ServiceStages::bringBack($pdo, 'wikimedia', 1, 'Tester'); Automation::forget();
$t('Bring it back: out of Retired, still paused until Activate', str_contains($say, 'still paused') && (ServiceStages::all($pdo)['wikimedia'][0] ?? '') === 'paused' && Automation::serviceOff($pdo, 'wikimedia') !== null, $say);
Automation::set($pdo, 'service', 'wikimedia', false, '', 1, 'Tester'); Automation::forget();
$t('…then it can be activated again', Automation::serviceOff($pdo, 'wikimedia') === null);
$h = array_column(ServiceStages::history($pdo, 'wikimedia', 10), 'stage');
$t('…each step in its history: retired, paused, active', in_array('retired', $h, true) && $h[0] === 'active');

// Proposed: only words
$before = OutsideServices::HOSTS;
$id = ServiceStages::propose($pdo, 'Example Trails API', 'https://trails.example.org/api', 'Trail closures for W9', 1, 'Tester');
$t('Propose records a build request: name, link, why, who', $id > 0 && (ServiceStages::proposals($pdo)[0]['name'] ?? '') === 'Example Trails API');
$t('…and adds no address, key or call', OutsideServices::HOSTS === $before && !isset(OutsideServices::COMPANIES['example']) && OutsideServices::serviceOfHost('trails.example.org') === '');
$err = ''; try { ServiceStages::propose($pdo, 'X', 'ftp://x', 'y', 1, 'T'); } catch (Throwable $x) { $err = $x->getMessage(); }
$t('…a link that isn\'t https:// is refused', str_contains($err, 'https://'), $err);
ServiceStages::setProposal($pdo, $id, true, 'Tester');
$t('Drop it, and Propose again is its way back', ServiceStages::proposals($pdo)[0]['state'] === 'dropped' && (function () use ($pdo, $id) { ServiceStages::setProposal($pdo, $id, false, 'T'); return ServiceStages::proposals($pdo)[0]['state'] === 'proposed'; })());

// the register: Retire folded on a card, Bring it back on a retired one, Proposed, Retired, Removed, the Retired filter
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php';
ServiceStages::retire($pdo, 'usgs', 'Testing the card', '', 1, 'Tester'); Automation::forget();
$html = tv_admin_mod_register($pdo);
$t('a card offers Retire it…, folded, asking why', str_contains($html, '<summary>Retire it…</summary>') && str_contains($html, 'value="svc_retire"') && str_contains($html, 'placeholder="Why it\'s retired"') && str_contains($html, 'data-yes="Retire" data-danger'));
$t('a retired card offers Bring it back, not Activate', (bool) preg_match('/id="svc-usgs".*?value="svc_bringback"/s', $html) && !preg_match('/id="stage-usgs".*?value="svc_activate".*?<\/div>/s', substr($html, (int) strpos($html, 'id="stage-usgs"'), 1500)));
$t('the Retired filter, and the summary counts both kinds of retired', str_contains($html, 'data-fv="stage:retired"') && (bool) preg_match('/<b>2<\/b><span>Retired<\/span>/', $html));
$t('Proposed: the form and the request, with Drop it', str_contains($html, 'id="proposed"') && str_contains($html, 'value="prop_add"') && str_contains($html, 'Example Trails API') && str_contains($html, '>Drop it</button>'));
$t('Removed: none yet, and what it will hold', str_contains($html, 'id="removed"') && str_contains($html, 'None yet'));
$t('Overpass\'s record is still in Retired', str_contains($html, 'id="svc-overpass"'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
