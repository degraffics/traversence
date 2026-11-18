<?php
// The Control Board's release 2c (the service cards' fixes): requests to outside companies in their own table (the old
// ones brought over with their ids, so their Pulse cases stay), Name.com's ticket seeded once; the Stripe and Square
// cards read the names MarketPay reads; the "Added switched off" words reworded by the migration, a person's own reason
// left alone. Run: mariadb < ../release-b-tests/start-schema.sql && php r2c.php <root>/app
declare(strict_types=1);
$app = $argv[1];
require_once $app . '/lib/Database.php'; require $app . '/lib/OutsideServices.php'; require $app . '/lib/Automation.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$ss = Database::t('staff_settings');
$pdo->exec("CREATE TABLE IF NOT EXISTS $ss (name VARCHAR(100) NOT NULL PRIMARY KEY, value VARCHAR(255) NOT NULL)");
$pdo->exec('DROP TABLE IF EXISTS outside_requests');
$pdo->exec("DELETE FROM $ss WHERE name LIKE 'outreq:%'");
$pdo->prepare("INSERT INTO $ss (name, value) VALUES ('outreq:seeded', '1'), ('outreq:4', ?)")->execute([json_encode(['s' => 'aws', 'w' => 'Raise the SES sending limit', 't' => '9911', 'l' => '', 'at' => '2026-11-16 10:00:00', 'by' => 7, 'c' => ''])]);

// the requests
$r = OutsideServices::requests($pdo);
$t('an old request comes over with its id (its Pulse case outreq:4 stays)', isset($r[4]) && $r[4]['svc'] === 'aws' && $r[4]['ticket'] === '9911' && $r[4]['by'] === 7 && $r[4]['sent'] === '2026-11-16 10:00:00', $r[4] ?? $r);
$nc = array_values(array_filter($r, fn ($x) => $x['ticket'] === '3113488'));
$t('Name.com\'s ticket #3113488 is kept, its words whole', count($nc) === 1 && $nc[0]['svc'] === 'namecom' && str_ends_with($nc[0]['what'], '.app .ai)') && OutsideServices::$reqError === '', [$nc, OutsideServices::$reqError]);
$r2 = OutsideServices::requests($pdo);
$t('read again: nothing doubled', count($r2) === 2, array_keys($r2));
$id = OutsideServices::addRequest($pdo, 'namecom', 'Webhooks for contact verification', '3115000', 'https://www.name.com/support', 7);
$t('a new request gets the next id after the old ones (4 brought over, 5 the seed)', $id === 6 && $nc[0]['id'] === 5, [$id, $nc[0]['id']]);
OutsideServices::pulseDo($pdo, $id, 'out_answer', ['text' => 'Yes, from December.'], 7);
$open = OutsideServices::requests($pdo); $all = OutsideServices::requests($pdo, true);
$t('their answer closes it: gone from the open ones, kept in all', !isset($open[$id]) && $all[$id]['closed'] !== '', $all[$id]);

// the cards' key names
$card = function (string $k) { foreach (OutsideServices::parts($GLOBALS['pdo'], $k) as $p) if (str_starts_with((string) $p[0], 'Marketplace checkout')) return $p; return null; };
foreach (['STRIPE_LIVE_SECRET_KEY', 'STRIPE_LIVE_CLIENT_ID'] as $k) putenv($k);
putenv('MARKET_PAY_ENV=live'); foreach (['STRIPE_CLIENT_ID' => 'ca_1', 'STRIPE_SECRET_KEY' => 'sk_live_x', 'STRIPE_PUBLISHABLE_KEY' => 'pk_live_x', 'STRIPE_WEBHOOK_SECRET' => 'whsec_x'] as $k => $v) putenv("$k=$v");
$c = $card('stripe');
$t('Stripe live, set the way MarketPay reads it: keys set', $c && $c[2] === 'ok' && str_starts_with($c[1], 'Live: keys set'), $c);
$t('…and it says the test accounts\' set is missing, by name', $c && str_contains($c[1], 'missing STRIPE_TEST_CLIENT_ID'), $c);
putenv('STRIPE_WEBHOOK_SECRET');
$c = $card('stripe');
$t('a live key missing: named', $c && $c[2] === 'bad' && str_contains($c[1], 'missing STRIPE_WEBHOOK_SECRET'), $c);
putenv('MARKET_PAY_ENV=sandbox'); foreach (['SQUARE_SANDBOX_APP_ID' => 'sandbox-x', 'SQUARE_SANDBOX_APP_SECRET' => 'y', 'SQUARE_SANDBOX_WEBHOOK_KEY' => 'z'] as $k => $v) putenv("$k=$v");
$c = $card('square');
$t('Square in the sandbox, its sandbox set: keys set', $c && $c[2] === 'ok' && str_starts_with($c[1], 'Sandbox: keys set'), $c);

// the switched-off words
Automation::forget();
$pdo->exec("DELETE FROM automation_switches WHERE kind = 'service'");
$pdo->exec("INSERT INTO automation_switches (kind, ref, paused, why, at) VALUES ('service', 'azcc', 1, 'Added switched off (Jason, 2026-11-15): nothing calls it until Release B and a person''s switch', UTC_TIMESTAMP()),
    ('service', 'gemini', 1, 'Added switched off (Jason, 2026-11-15): nothing calls it until Release B and a person''s switch', UTC_TIMESTAMP()), ('service', 'nmsos', 1, 'Jason: held for the records request', UTC_TIMESTAMP())");
$sql = file_get_contents(dirname($app) . '/app/migrations/2026-11-18_added_off_words.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $q) { $pdo->exec($q); $pdo->exec($q); }
$w = $pdo->query("SELECT ref, why FROM automation_switches WHERE kind = 'service'")->fetchAll(PDO::FETCH_KEY_PAIR);
$t('AZCC gets its real reason', str_contains($w['azcc'], 'records request') && str_starts_with($w['azcc'], 'Off until a person switches it on'), $w);
$t('another gets the plain reason', $w['gemini'] === 'Off until a person switches it on: added switched off (2026-11-15)', $w);
$t('a person\'s own reason is left alone, run twice', $w['nmsos'] === 'Jason: held for the records request', $w);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
