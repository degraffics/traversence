<?php
// Events P1 (decisions/0134's note #1589, participants) on a real MySQL or MariaDB: the host invites a claimed listing
// (its team told once, through Notify) or an unclaimed one (it waits, untold); the invited business takes part with an
// offer line (no links), or declines (the host may ask again); it leaves and Undo brings it back; the host removes and
// Undo restores what it was; nobody else moves it; nothing for a cancelled event or the host itself; History names each move.
// Run: php events-parts.php <root>/app <events .sql> <participants .sql>   (it resets lv_events3)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); $m1 = $argv[2] ?? ''; $m2 = $argv[3] ?? '';
if (!is_file($app . '/lib/Events.php') || !is_file($m1) || !is_file($m2)) { fwrite(STDERR, "usage: php events-parts.php <root>/app <events.sql> <participants.sql>\n"); exit(2); }
// the dispatcher, counted: a copy of the app's libraries with Notify.php in place of the site's, so what it would send is kept
$tmp = sys_get_temp_dir() . '/evparts-' . getmypid(); @mkdir($tmp);
foreach (glob($app . '/lib/*.php') as $f) if (basename($f) !== 'Notify.php') { if (basename($f) === 'Events.php') copy($f, $tmp . '/Events.php'); else @symlink($f, $tmp . '/' . basename($f)); }   // Events.php copied: a symlinked file's __DIR__ is its real folder
file_put_contents($tmp . '/Notify.php', '<?php final class Notify { public static array $sent = []; public static function send($pdo, string $k, array $to, array $about, array $w): int { self::$sent[] = [$k, $to, $about, $w]; return 1; } }');
require_once $tmp . '/Database.php'; require_once $tmp . '/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_events3'); $pdo->exec('CREATE DATABASE lv_events3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_events3');
foreach ([$m1, $m2, $m2] as $m) foreach (DbUpdates::statements((string) file_get_contents($m)) as $s) if (trim($s) !== '') $pdo->exec($s);
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(120), status VARCHAR(20), is_suppressed TINYINT DEFAULT 0, owner_user_id INT NULL, primary_zip VARCHAR(10), longitude DOUBLE NULL, micro_cluster_id INT NULL)");
$pdo->exec("CREATE TABLE zip_coordinates (zip VARCHAR(10), city VARCHAR(80), state VARCHAR(4), latitude DOUBLE, longitude DOUBLE)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(120), display_name VARCHAR(120))");
$pdo->exec("CREATE TABLE listing_memberships (listing_id INT, user_id INT, role VARCHAR(20), status VARCHAR(10))");
$pdo->exec("CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20))");
$pdo->exec("CREATE TABLE role_perms (role VARCHAR(20), perm VARCHAR(20), level VARCHAR(10))");
$pdo->exec("INSERT INTO zip_coordinates VALUES ('85936','St Johns','AZ',34.5,-109.36)");
$pdo->exec("INSERT INTO entities (id,name,status,primary_zip,longitude) VALUES (1,'Hometown Theater','verified','85936',-109.36),(2,'Diner on Main','verified','85936',-109.36),(3,'Unclaimed Grill','unclaimed','85936',-109.36),(4,'Hidden Bar','verified','85936',-109.36)");
$pdo->exec("UPDATE entities SET is_suppressed = 1 WHERE id = 4");
$pdo->exec("INSERT INTO users VALUES (1,'t@example.test','Theater Owner'),(2,'d@example.test','Diner Owner'),(3,'x@example.test','Stranger')");
$pdo->exec("INSERT INTO listing_memberships VALUES (1,1,'owner','active'),(2,2,'owner','active')");
require_once $tmp . '/Events.php';
$pass = 0; $fail = 0; $t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$err = function (callable $f) { try { $f(); return ''; } catch (InvalidArgumentException $x) { return $x->getMessage(); } };
$soon = (new DateTime('+15 days'))->format('Y-m-d');
$ev = Events::save($pdo, 1, 1, ['title' => 'Community night', 'kind' => 'arts', 'starts_at' => $soon . 'T19:00', 'age' => 'all'], 0); Events::act($pdo, 1, 1, $ev, 'publish');
$st = fn (int $e) => (string) $pdo->query("SELECT status FROM event_participants WHERE event_id = $ev AND entity_id = $e")->fetchColumn();
$pid = fn (int $e) => (int) $pdo->query("SELECT id FROM event_participants WHERE event_id = $ev AND entity_id = $e")->fetchColumn();
$t('ready once its migration ran (run twice)', Events::partsReady($pdo));
$t('finding businesses to invite: by name, with its town', Events::findListings($pdo, 'Diner', 1) === [['id' => 2, 'name' => 'Diner on Main', 'town' => 'St Johns, AZ']], Events::findListings($pdo, 'Diner', 1));
$t('never the host itself, nor a hidden listing', Events::findListings($pdo, 'Hometown', 1) === [] && Events::findListings($pdo, 'Hidden', 1) === []);
$w = Events::invite($pdo, 1, 1, $ev, 2);
$t('invite a claimed listing: Invited, its team told once', $st(2) === 'invited' && str_contains($w, 'Invited') && count(Notify::$sent) === 1 && Notify::$sent[0][0] === 'biz.event_invite' && Notify::$sent[0][1]['entity'] === 2);
$t('inviting again: says it\'s on the list, nobody told twice', str_contains(Events::invite($pdo, 1, 1, $ev, 2), 'already on the list') && count(Notify::$sent) === 1);
$w3 = Events::invite($pdo, 1, 1, $ev, 3);
$t('an unclaimed listing waits, untold, and the host is told why', $st(3) === 'waiting' && str_contains($w3, 'hasn\'t claimed') && count(Notify::$sent) === 1);
$t('the host can\'t invite itself', str_contains($err(fn () => Events::invite($pdo, 1, 1, $ev, 1)), 'host'));
$t('a hidden listing can\'t be invited', $err(fn () => Events::invite($pdo, 1, 1, $ev, 4)) !== '');
$t('someone not on the host\'s team can\'t invite', $err(fn () => Events::invite($pdo, 3, 1, $ev, 2)) !== '');
// the invited business answers
$t('a link in the offer is refused', str_contains($err(fn () => Events::answer($pdo, 2, 2, $pid(2), 'accept', 'See www.example.test')), 'No links'));
$t('the host can\'t answer for the diner', $err(fn () => Events::answer($pdo, 1, 1, $pid(2), 'accept')) !== '');
Events::answer($pdo, 2, 2, $pid(2), 'accept', '10% off dinner with your ticket');
$t('Take part, with the offer', $st(2) === 'accepted' && (string) $pdo->query("SELECT offer_text FROM event_participants WHERE id = " . $pid(2))->fetchColumn() === '10% off dinner with your ticket');
$t('the event lists it publicly, taking part only', array_column(Events::participants($pdo, $ev, true), 'name') === ['Diner on Main']);
$t('the diner sees its invitation', count(Events::invitationsFor($pdo, 2)) === 1);
Events::answer($pdo, 2, 2, $pid(2), 'offer', '15% off');
$t('changing the offer keeps the old one in History', in_array('Diner on Main changed its offer (was "10% off dinner with your ticket")', array_map([Events::class, 'historyWords'], Events::history($pdo, $ev)), true));
Events::answer($pdo, 2, 2, $pid(2), 'leave');
$t('leave, then Undo: taking part again', $st(2) === 'left' && Events::answer($pdo, 2, 2, $pid(2), 'rejoin') !== '' && $st(2) === 'accepted');
Events::hostMove($pdo, 1, 1, $pid(2), 'remove');
$t('the host removes it; Undo restores what it was (taking part)', $st(2) === 'removed' && Events::hostMove($pdo, 1, 1, $pid(2), 'unremove') !== '' && $st(2) === 'accepted');
Events::hostMove($pdo, 1, 1, $pid(3), 'remove');
$t('a waiting one removed and undone goes back to waiting', $st(3) === 'removed' && Events::hostMove($pdo, 1, 1, $pid(3), 'unremove') !== '' && $st(3) === 'waiting');
$t('the diner can\'t remove anyone', $err(fn () => Events::hostMove($pdo, 2, 2, $pid(3), 'remove')) !== '');
// decline, and asked again
$ev2 = Events::save($pdo, 1, 1, ['title' => 'Second night', 'kind' => 'arts', 'starts_at' => $soon . 'T20:00', 'age' => 'all'], 0); Events::act($pdo, 1, 1, $ev2, 'publish');
Events::invite($pdo, 1, 1, $ev2, 2); $p2 = (int) $pdo->query("SELECT id FROM event_participants WHERE event_id = $ev2 AND entity_id = 2")->fetchColumn();
Events::answer($pdo, 2, 2, $p2, 'decline');
$t('declined, then the host asks again: invited, told again', (string) $pdo->query("SELECT status FROM event_participants WHERE id = $p2")->fetchColumn() === 'declined' && str_contains(Events::invite($pdo, 1, 1, $ev2, 2), 'Invited') && count(Notify::$sent) === 3);
Events::act($pdo, 1, 1, $ev2, 'cancel');
$t('no invitations to a cancelled event', str_contains($err(fn () => Events::invite($pdo, 1, 1, $ev2, 3)), 'still on'));
$hw = array_map([Events::class, 'historyWords'], Events::history($pdo, $ev));
$t('History names each move', in_array('invited Diner on Main', $hw, true) && in_array('Diner on Main accepted', $hw, true) && in_array('Diner on Main left', $hw, true) && in_array('removed Diner on Main', $hw, true) && in_array('put Diner on Main back', $hw, true));
$pdo->exec('DROP DATABASE lv_events3'); array_map('unlink', glob($tmp . '/*')); @rmdir($tmp);
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
