<?php
// decisions/0172's row icons and opened rows in the member's and a business's Pulse (app/lib/PulseAct.php,
// /user/api/pulse-act.php): conversations opened and answered, ↗ Pass it on (to a connection, a teammate, by email,
// capped), 🛡 report, block and mute (never told; the viewer's own), ⊕ the Address Book, on a real MySQL or MariaDB with
// the live libraries and stand-ins only for what reaches outside (mail) or the session.
//   Run: mariadb -e 'CREATE DATABASE tvpact'; php pulse-act.php <a copy of app/ with the release laid over it>
//   The app dir is copied to a scratch folder first (the stand-ins below replace Database, Mailer, Standing and Tags there),
//   and 'pulse.forward' is added to that copy's Notify::KINDS if it isn't there yet.
declare(strict_types=1);
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php pulse-act.php <app dir>\n"); exit(2); }
$app = sys_get_temp_dir() . '/tv-pact-' . getmypid();
exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($app), $o, $rc);
if ($rc !== 0) { fwrite(STDERR, "couldn't copy the app dir\n"); exit(2); }
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg($app)));

// ---- stand-ins -------------------------------------------------------------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvpact;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
}
final class Mailer { public static array $sent = []; public static bool $routed = false;
    public static function send(string $to, string $subject, string $text, ?string $name = null, string $as = '', string $html = '', string $replyTo = ''): void { self::$sent[] = compact('to', 'subject', 'text'); } }
final class Standing { public static function guard(PDO $p, int $u): void {} }
final class Tags { public static function clean(PDO $p, string $b): array { return [$b, []]; } public static function plain(string $b): string { return $b; } }
final class Auth { public static ?int $id = null; public static function currentUserId(): ?int { return self::$id; } public static function currentUser(): ?array { return self::$id ? ['id' => self::$id] : null; } }
foreach (['Database', 'Mailer', 'Standing', 'Tags'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// test stand-in\n");

$nf = file_get_contents($app . '/lib/Notify.php');
if (!str_contains($nf, "'pulse.forward'")) {
    $nf = preg_replace('/(public const KINDS = \[\n)/', "$1        'pulse.forward' => ['Passed on to you', 'Someone on your team passed a message on to you in Pulse.', 'notice', 'any', 'Pulse', false, true, 'system', ''],\n", $nf, 1);
    file_put_contents($app . '/lib/Notify.php', $nf);
}

$pdo = Database::connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec("DROP TABLE `$t`");
$sql = <<<'SQL'
CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_suspended TINYINT DEFAULT 0, created_at DATETIME NULL);
CREATE TABLE user_settings (user_id INT, no_marketing TINYINT DEFAULT 0, message_policy VARCHAR(20) NULL, profile_private TINYINT DEFAULT 0);
CREATE TABLE user_blocks (user_id INT, blocked_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE user_links (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, target_type VARCHAR(20), target_id VARCHAR(40), label VARCHAR(255), url VARCHAR(255), muted TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE people_connections (id INT AUTO_INCREMENT PRIMARY KEY, requester_id INT, addressee_id INT, status VARCHAR(10) NOT NULL DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, responded_at DATETIME NULL);
CREATE TABLE contact_shares (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT, viewer_id INT, field VARCHAR(20), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL);
CREATE TABLE contact_requests (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT, requester_id INT, status VARCHAR(10), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE message_threads (id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME, last_message_at DATETIME);
CREATE TABLE message_members (thread_id INT, user_id INT, last_read_at DATETIME NULL, muted TINYINT DEFAULT 0, archived TINYINT DEFAULT 0, status VARCHAR(10) NOT NULL DEFAULT 'active');
CREATE TABLE messages (id INT AUTO_INCREMENT PRIMARY KEY, thread_id INT, sender_id INT, body TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(100), status VARCHAR(20) DEFAULT 'managed', owner_user_id INT NULL, is_suppressed TINYINT DEFAULT 0);
CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20));
CREATE TABLE listing_memberships (listing_id INT, user_id INT, role VARCHAR(20), status VARCHAR(10), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE role_grants (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, role VARCHAR(20), side VARCHAR(10), scope_type VARCHAR(10), scope_id VARCHAR(20), on_behalf_of INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL);
CREATE TABLE role_perms (role VARCHAR(20), perm VARCHAR(20), level VARCHAR(10));
CREATE TABLE support_grants (id INT AUTO_INCREMENT PRIMARY KEY, listing_id INT, user_id INT, role VARCHAR(20), revoked_at DATETIME NULL);
CREATE TABLE business_messages (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, user_id INT, from_business TINYINT, kind VARCHAR(20), body TEXT, big TINYINT, sent_by INT NULL, visible_at DATETIME, created_at DATETIME, msg_type VARCHAR(20) NULL, answered TINYINT DEFAULT 0);
CREATE TABLE business_message_reads (user_id INT, entity_id INT, person_read_at DATETIME NULL, biz_read_at DATETIME NULL);
CREATE TABLE business_message_mutes (user_id INT, entity_id INT, msg_type VARCHAR(20));
CREATE TABLE marketing_optins (user_id INT, entity_id VARCHAR(40), status VARCHAR(10), frequency VARCHAR(10));
CREATE TABLE guest_inquiries (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, name VARCHAR(80), contact_type VARCHAR(10), contact VARCHAR(255) NULL, code_hash CHAR(64), device_hash CHAR(64), ip_hash CHAR(64), status VARCHAR(10), guest_read_at DATETIME, biz_read_at DATETIME NULL, created_at DATETIME, last_at DATETIME, moved_user_id INT NULL);
CREATE TABLE guest_inquiry_messages (id INT AUTO_INCREMENT PRIMARY KEY, inquiry_id INT, from_business TINYINT, body TEXT, sent_by INT NULL, emailed TINYINT DEFAULT 0, created_at DATETIME);
CREATE TABLE content_reports (id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT NOT NULL, kind VARCHAR(20), target_id INT, reason VARCHAR(20), note VARCHAR(500), status VARCHAR(10) DEFAULT 'open', created_at DATETIME, route VARCHAR(10) DEFAULT 'staff', handled_by INT NULL, handled_at DATETIME NULL);
CREATE TABLE community_groups (id INT PRIMARY KEY, name VARCHAR(80), about TEXT, owner_id INT, join_mode VARCHAR(10), status VARCHAR(10) DEFAULT 'active', created_at DATETIME, owner_kind VARCHAR(10) DEFAULT 'person', owner_ref INT DEFAULT 0);
CREATE TABLE community_group_members (group_id INT, user_id INT, role VARCHAR(10), status VARCHAR(10), joined_at DATETIME, list_hidden TINYINT DEFAULT 0, charter_version INT NULL);
CREATE TABLE community_posts (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT, user_id INT, body TEXT, created_at DATETIME, removed_at DATETIME NULL, voice VARCHAR(10) DEFAULT 'person', as_listing INT NULL, held_at DATETIME NULL);
CREATE TABLE comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL, UNIQUE KEY comms_once (to_user, kind, thing), KEY comms_thread (to_user, kind, thread));
CREATE TABLE comms_route (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, via VARCHAR(12) NOT NULL, stage VARCHAR(12) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', provider_id VARCHAR(120) NOT NULL DEFAULT '', happened_at DATETIME NOT NULL, sender_key VARCHAR(30) NULL, addr_hash CHAR(64) NULL, subject VARCHAR(200) NULL, mail_kind VARCHAR(30) NULL);
CREATE TABLE comms_prefs (user_id INT UNSIGNED NOT NULL, kind VARCHAR(48) NOT NULL, via VARCHAR(12) NOT NULL, is_on TINYINT(1) NOT NULL DEFAULT 1, changed_at DATETIME NOT NULL, PRIMARY KEY (user_id, kind, via));
CREATE TABLE comms_quiet (user_id INT UNSIGNED NOT NULL PRIMARY KEY, quiet_from SMALLINT UNSIGNED NOT NULL, quiet_to SMALLINT UNSIGNED NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', changed_at DATETIME NOT NULL);
CREATE TABLE comms_controls (ckey VARCHAR(60) NOT NULL PRIMARY KEY, paused TINYINT NOT NULL DEFAULT 0, why VARCHAR(255) NOT NULL DEFAULT '', by_user INT NULL, changed_at DATETIME NOT NULL);
CREATE TABLE push_subs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, endpoint VARCHAR(600) NOT NULL, endpoint_hash CHAR(64) NOT NULL, p256dh VARCHAR(200) NOT NULL, auth_key VARCHAR(64) NOT NULL, device VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, last_ok_at DATETIME NULL, failed_at DATETIME NULL, fails INT NOT NULL DEFAULT 0);
CREATE TABLE notices_sent (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, kind VARCHAR(40), thing VARCHAR(80), title VARCHAR(200), body VARCHAR(400), url VARCHAR(255), created_at DATETIME, read_at DATETIME NULL, UNIQUE KEY (user_id, kind, thing));
CREATE TABLE notice_prefs (user_id INT, kind VARCHAR(40), off_since DATETIME, PRIMARY KEY (user_id, kind));
CREATE TABLE notice_delivery (user_id INT PRIMARY KEY, how VARCHAR(10), digest_sent_at DATETIME NULL);
CREATE TABLE email_suppress (addr_hash CHAR(64), scope VARCHAR(40), reason VARCHAR(20), created_at DATETIME, PRIMARY KEY (addr_hash, scope))
SQL;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) $pdo->exec($q);
$pdo->exec(file_get_contents($src . '/migrations/2027-03-21_pulse_forwards.sql') ?: '');
$now = date('Y-m-d H:i:s');
$pdo->exec("INSERT INTO users (id, email, display_name) VALUES (1,'ann@m.test','Ann Member'),(2,'bo@m.test','Bo Friend'),(3,'cy@m.test','Cy Stranger'),(4,'olive@b.test','Olive Owner'),(5,'gene@b.test','Gene Staff'),(6,'mo@m.test','Mo Customer')");
$pdo->exec("INSERT INTO people_connections (requester_id, addressee_id, status, responded_at) VALUES (1,2,'accepted','$now')");
$pdo->exec("INSERT INTO message_threads (id, created_at, last_message_at) VALUES (1,'$now','$now'),(2,'$now','$now')");
$pdo->exec("INSERT INTO message_members (thread_id, user_id) VALUES (1,1),(1,3),(2,2),(2,3)");
$pdo->exec("INSERT INTO messages (thread_id, sender_id, body, created_at) VALUES (1,3,'Selling cheap passes, DM me','$now'),(2,3,'hi bo','$now')");
$pdo->exec("INSERT INTO entities (id, name) VALUES (10,'Kayenta Trading Post')");
$pdo->exec("INSERT INTO listing_access VALUES (10,4,'owner'),(10,5,'owner')");
$pdo->exec("INSERT INTO business_messages (entity_id, user_id, from_business, kind, body, big, visible_at, created_at, msg_type) VALUES (10,6,0,'message','Are you open Saturday?',0,'$now','$now',NULL),(10,1,1,'message','Big sale: rugs 20% off',0,'$now','$now','support')");
$pdo->exec("INSERT INTO guest_inquiries (id, entity_id, name, contact_type, contact, status, created_at, last_at) VALUES (7,10,'Pat','none',NULL,'open','$now','$now')");
$pdo->exec("INSERT INTO guest_inquiry_messages (inquiry_id, from_business, body, created_at) VALUES (7,0,'Do you sell Two Grey Hills rugs?','$now')");
$pdo->exec("INSERT INTO community_groups (id, name, owner_id, join_mode, created_at) VALUES (20,'Kayenta Hikers',2,'open','$now')");
$pdo->exec("INSERT INTO community_group_members (group_id, user_id, role, status, joined_at) VALUES (20,1,'member','active','$now')");

foreach (['People', 'BizMessages', 'Notify', 'PulseAct', 'GroupNotices', 'Community'] as $c) require_once $app . '/lib/' . $c . '.php';

$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$one = fn (string $s) => $pdo->query($s)->fetchColumn();
$threw = function (callable $f): string { try { $f(); return ''; } catch (InvalidArgumentException | RuntimeException $x) { return $x->getMessage(); } };
$member = ['kind' => 'member', 'key' => 'u'];
$org = ['kind' => 'org', 'key' => 'org:10', 'scope' => ['entity' => 10]];
$people = ['ref' => ['kind' => 'people', 'thread' => 1, 'with' => 3]];

echo "A conversation, opened in place\n";
Auth::$id = 1;
$th = PulseAct::thread($pdo, 1, $member, $people['ref']);
$t('a member opens their own conversation: its messages', count($th['messages']) === 1 && $th['messages'][0]['body'] === 'Selling cheap passes, DM me', $th);
$t('…someone not in it can\'t read it', $threw(fn () => PulseAct::thread($pdo, 2, $member, $people['ref'])) !== '');
PulseAct::reply($pdo, 1, $member, $people['ref'], 'No thanks.', '');
$t('Reply sends through People (one more message, from them)', (int) $one("SELECT COUNT(*) FROM messages WHERE thread_id = 1 AND sender_id = 1") === 1);
$t('an empty reply is refused', $threw(fn () => PulseAct::reply($pdo, 1, $member, $people['ref'], '  ', '')) !== '');
$t('a business conversation can\'t be read from a member\'s own Pulse', $threw(fn () => PulseAct::thread($pdo, 4, $member, ['kind' => 'team', 'e' => 10, 'u' => 6])) !== '');
$t('…nor by a person not on its team, working as that business', $threw(fn () => PulseAct::thread($pdo, 1, $org, ['kind' => 'team', 'e' => 10, 'u' => 6])) !== '');
Auth::$id = 4;
$tb = PulseAct::thread($pdo, 4, $org, ['kind' => 'team', 'e' => 10, 'u' => 6]);
$t('the business\'s team opens a customer\'s conversation', $tb['with'] !== '' && $tb['messages'][0]['body'] === 'Are you open Saturday?', $tb);
PulseAct::reply($pdo, 4, $org, ['kind' => 'team', 'e' => 10, 'u' => 6], 'Yes, 9 to 5.', 'inquiry');
$t('…and answers it through BizMessages', (int) $one("SELECT COUNT(*) FROM business_messages WHERE entity_id = 10 AND user_id = 6 AND from_business = 1") === 1);
$g = PulseAct::thread($pdo, 4, $org, ['kind' => 'guest', 'e' => 10, 'id' => 7]);
$t('a guest question opens for the team', $g['messages'][0]['body'] === 'Do you sell Two Grey Hills rugs?', $g);
PulseAct::reply($pdo, 4, $org, ['kind' => 'guest', 'e' => 10, 'id' => 7], 'We do.', '');
$t('…and is answered through GuestInquiries', (int) $one("SELECT COUNT(*) FROM guest_inquiry_messages WHERE inquiry_id = 7 AND from_business = 1") === 1);

echo "↗ Pass it on\n";
Auth::$id = 1;
$tg = PulseAct::forwardTargets($pdo, 1, $member);
$t('a member passes on to their connections', array_column($tg['people'], 0) === [2] && $tg['left'] === PulseAct::FORWARD_DAY, $tg);
$me = ['id' => 1, 'display_name' => 'Ann Member'];
PulseAct::forward($pdo, $me, $member, $people, 'person', 2, '', 'Watch out for this');
$fw = $one("SELECT m.body FROM messages m JOIN message_members a ON a.thread_id = m.thread_id AND a.user_id = 2 JOIN message_members b ON b.thread_id = m.thread_id AND b.user_id = 1 ORDER BY m.id DESC LIMIT 1");
$t('…as a message that says who forwarded it and what it was', is_string($fw) && str_contains($fw, 'Forwarded by Ann Member') && str_contains($fw, 'Selling cheap passes'), $fw);
$t('…never to someone who isn\'t a connection', $threw(fn () => PulseAct::forward($pdo, $me, $member, $people, 'person', 3, '', '')) !== '');
PulseAct::forward($pdo, $me, $member, $people, 'email', 0, 'Friend@Example.com', '');
$m = end(Mailer::$sent);
$t('by email: sent with the "forwarded by" line and the words', $m['to'] === 'friend@example.com' && str_contains($m['text'], 'Forwarded by Ann Member') && str_contains($m['text'], 'Selling cheap passes'), $m);
$t('…the address kept only as its hash', $one("SELECT to_hash FROM pulse_forwards WHERE via = 'email'") === hash('sha256', 'friend@example.com'));
$t('…a bad address is refused', $threw(fn () => PulseAct::forward($pdo, $me, $member, $people, 'email', 0, 'not-an-address', '')) !== '');
$st = $pdo->prepare("INSERT INTO pulse_forwards (user_id, via, to_hash, what, created_at) VALUES (1, 'email', 'x', 'x', ?)");
for ($k = 1; $k < PulseAct::FORWARD_DAY; $k++) $st->execute([$now]);
$t('…capped a day per person: the 21st is refused, saying why', str_contains($threw(fn () => PulseAct::forward($pdo, $me, $member, $people, 'email', 0, 'b@example.com', '')), 'most for a day'));
$t('a thread you\'re not in can\'t be forwarded', $threw(fn () => PulseAct::forward($pdo, ['id' => 2, 'display_name' => 'Bo'], $member, $people, 'email', 0, 'c@example.com', '')) !== '');
Auth::$id = 4;
$tt = PulseAct::forwardTargets($pdo, 4, $org);
$t('a business passes on to its team (not itself)', array_column($tt['team'], 0) === [5], $tt);
PulseAct::forward($pdo, ['id' => 4, 'display_name' => 'Olive Owner'], $org, ['ref' => ['kind' => 'team', 'e' => 10, 'u' => 6]], 'team', 5, '', 'Can you take this?');
$t('…in the teammate\'s Pulse (pulse.forward), from who passed it on', $one("SELECT sender_name FROM comms_messages WHERE kind = 'pulse.forward' AND to_user = 5") === 'Olive Owner');
$t('…never to someone off the team', $threw(fn () => PulseAct::forward($pdo, ['id' => 4, 'display_name' => 'Olive'], $org, ['ref' => ['kind' => 'team', 'e' => 10, 'u' => 6]], 'team', 2, '', '')) !== '');
$t('what needs you can\'t be passed on (the site\'s own count), and says why', PulseAct::forwardWhy(['ref' => ['kind' => 'needs']]) !== '');

echo "🛡 Protect\n";
Auth::$id = 1;
PulseAct::report($pdo, 1, $member, $people, 'spam', 'selling');
$t('a conversation is reported through Reports (thread)', (int) $one("SELECT COUNT(*) FROM content_reports WHERE kind = 'thread' AND target_id = 1 AND reporter_id = 1") === 1);
$t('…the person reported isn\'t told', (int) $one('SELECT COUNT(*) FROM comms_messages WHERE to_user = 3') === 0);
PulseAct::mute($pdo, 1, $member, $people, true);
$t('mute is the viewer\'s own', (int) $one('SELECT muted FROM message_members WHERE thread_id = 1 AND user_id = 1') === 1 && (int) $one('SELECT muted FROM message_members WHERE thread_id = 1 AND user_id = 3') === 0);
PulseAct::block($pdo, 1, $member, $people);
$t('block: People::block, and they aren\'t told', (int) $one('SELECT COUNT(*) FROM user_blocks WHERE user_id = 1 AND blocked_id = 3') === 1 && (int) $one('SELECT COUNT(*) FROM comms_messages WHERE to_user = 3') === 0);
PulseAct::report($pdo, 1, $member, ['ref' => ['kind' => 'biz', 'e' => 10]], 'mislabelled', '');
$t('a business\'s message to you is reported (biz_message)', (int) $one("SELECT COUNT(*) FROM content_reports WHERE kind = 'biz_message' AND reporter_id = 1") === 1);
PulseAct::block($pdo, 1, $member, ['ref' => ['kind' => 'biz', 'e' => 10]]);
$t('blocking a business mutes every kind of message from it', (int) $one('SELECT COUNT(*) FROM business_message_mutes WHERE user_id = 1 AND entity_id = 10') === count(BizMessages::TYPES));
Auth::$id = 4; $before = count(Mailer::$sent);
PulseAct::report($pdo, 4, $org, ['ref' => ['kind' => 'team', 'e' => 10, 'u' => 6]], 'harassment', 'rude');
$t('a business reports a customer: our support inbox gets what they wrote', count(Mailer::$sent) === $before + 1 && str_contains(end(Mailer::$sent)['text'], 'Are you open Saturday?'));
$t('…a business can\'t block a customer, and says why', str_contains($threw(fn () => PulseAct::block($pdo, 4, $org, ['ref' => ['kind' => 'team', 'e' => 10, 'u' => 6]])), 'report it'));
$pr = PulseAct::protects(['comms' => 5], $member);
$t('a notice from the site: Report greyed with the reason', $pr[0][3] !== '');
Auth::$id = 1;
PulseAct::mute($pdo, 1, $member, ['ref' => ['kind' => 'feed', 'group' => 20]], true);
$t('mute a group: the viewer\'s own link, muted', GroupNotices::muted($pdo, 1, 20) && !GroupNotices::muted($pdo, 2, 20));

echo "⊕ Add to\n";
$pdo->exec('DELETE FROM user_blocks');
PulseAct::addressBook($pdo, 1, $member, ['ref' => ['kind' => 'people', 'thread' => 1, 'with' => 3]]);
$t('the sender to the Address Book asks to connect', $one('SELECT status FROM people_connections WHERE requester_id = 1 AND addressee_id = 3') === 'pending');
$t('a customer\'s details stay with the business: no Address Book from its team', $threw(fn () => PulseAct::addressBook($pdo, 4, $org, ['ref' => ['kind' => 'team', 'e' => 10, 'u' => 6]])) !== '');
$t('Keep is only for a post', $threw(fn () => PulseAct::keep($pdo, 1, ['ref' => ['kind' => 'people']])) !== '');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
