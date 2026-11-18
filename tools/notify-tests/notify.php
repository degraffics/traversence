<?php
// decisions/0172 step 2's tests: the one dispatcher (Notify) on a real MySQL or MariaDB, with the live libraries and
// stand-ins only for what reaches outside (mail, SES) or the session.
//   Run: mariadb -e 'CREATE DATABASE tvtest'; php notify.php <a copy of app/ with the release laid over it>
//   (the stand-ins for Database, Mailer, SesMail, Standing, AgeGate and Auth are below; the copy's own are replaced.)
declare(strict_types=1);
$app = $argv[1] ?? '';
if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php notify.php <app dir>\n"); exit(2); }

// ---- stand-ins -------------------------------------------------------------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvtest;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
}
final class Mailer { public static array $sent = []; public static bool $fail = false;
    public static function send(string $to, string $subject, string $text, ?string $name = null, string $as = '', string $html = ''): void { if (self::$fail) throw new RuntimeException('SMTP refused'); self::$sent[] = compact('to', 'subject', 'text'); } }
final class SesMail { public static array $sent = [];
    public static function mode(): string { return 'live'; } public static function ready(PDO $p): bool { return true; }
    public static function send(PDO $p, array $m): void { self::$sent[] = $m; }
    public static function hash(string $e): string { return hash('sha256', mb_strtolower(trim($e))); }
    public static function suppressed(PDO $p, string $h, string $s): bool { $st = $p->prepare("SELECT 1 FROM email_suppress WHERE addr_hash = ? AND (scope = 'all' OR scope = ?)"); $st->execute([$h, $s]); return $st->fetchColumn() !== false; } }
final class Standing { public static function guard(PDO $p, int $u): void {} }
final class AgeGate { public static function require(PDO $p, int $u): void {} }
final class Auth { public static ?array $me = null; public static function currentUser(): ?array { return self::$me; } }
foreach (['Database', 'Mailer', 'SesMail', 'Standing', 'AgeGate'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// test stand-in\n");

$pdo = Database::connection();
foreach (['comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet', 'users', 'entities', 'listing_access', 'notices_sent', 'notice_prefs', 'notice_delivery', 'email_suppress',
          'business_messages', 'business_message_reads', 'guest_inquiries', 'guest_inquiry_messages', 'content_comments', 'staff_accounts', 'pulse_marks', 'pulse_notes', 'role_grants', 'user_links', 'marketing_optins', 'user_settings', 'business_message_mutes', 'guest_devices'] as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
$pdo->exec("CREATE TABLE user_links (user_id INT, target_type VARCHAR(20), target_id VARCHAR(40), muted TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE marketing_optins (user_id INT, entity_id VARCHAR(40), status VARCHAR(10), frequency VARCHAR(10))");
$pdo->exec("CREATE TABLE user_settings (user_id INT, no_marketing TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE business_message_mutes (user_id INT, entity_id INT, msg_type VARCHAR(20))");
$pdo->exec("CREATE TABLE guest_devices (device_hash CHAR(64), blocked_at DATETIME NULL, kind VARCHAR(20) NULL)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_suspended TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(100), status VARCHAR(20) DEFAULT 'managed')");
$pdo->exec("CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20))");
$pdo->exec("CREATE TABLE notices_sent (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, kind VARCHAR(40), thing VARCHAR(80), title VARCHAR(200), body VARCHAR(400), url VARCHAR(255), created_at DATETIME, read_at DATETIME NULL, UNIQUE KEY (user_id, kind, thing))");
$pdo->exec("CREATE TABLE notice_prefs (user_id INT, kind VARCHAR(40), off_since DATETIME, PRIMARY KEY (user_id, kind))");
$pdo->exec("CREATE TABLE notice_delivery (user_id INT PRIMARY KEY, how VARCHAR(10), digest_sent_at DATETIME NULL)");
$pdo->exec("CREATE TABLE email_suppress (addr_hash CHAR(64), scope VARCHAR(40), reason VARCHAR(20), created_at DATETIME, PRIMARY KEY (addr_hash, scope))");
$pdo->exec("CREATE TABLE business_messages (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, user_id INT, from_business TINYINT, kind VARCHAR(20), body TEXT, big TINYINT, sent_by INT NULL, visible_at DATETIME, created_at DATETIME, msg_type VARCHAR(20) NULL, answered TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE business_message_reads (user_id INT, entity_id INT, person_read_at DATETIME NULL, biz_read_at DATETIME NULL)");
$pdo->exec("CREATE TABLE guest_inquiries (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, name VARCHAR(80), contact_type VARCHAR(10), contact VARCHAR(255) NULL, code_hash CHAR(64), device_hash CHAR(64), ip_hash CHAR(64), status VARCHAR(10), guest_read_at DATETIME, biz_read_at DATETIME NULL, created_at DATETIME, last_at DATETIME, moved_user_id INT NULL)");
$pdo->exec("CREATE TABLE guest_inquiry_messages (id INT AUTO_INCREMENT PRIMARY KEY, inquiry_id INT, from_business TINYINT, body TEXT, created_at DATETIME)");
$pdo->exec("CREATE TABLE content_comments (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10), ref VARCHAR(40), user_id INT, body TEXT, created_at DATETIME, hidden TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, staff_id VARCHAR(20), name VARCHAR(80), email VARCHAR(255), role VARCHAR(20), level VARCHAR(20), department VARCHAR(10), status VARCHAR(10))");
$pdo->exec("CREATE TABLE pulse_marks (item_key VARCHAR(90) PRIMARY KEY, case_no INT AUTO_INCREMENT UNIQUE, assigned_to INT NULL, done_at DATETIME NULL, done_by INT NULL, esc_level TINYINT DEFAULT 0, esc_at DATETIME NULL, data TEXT NULL)");
$pdo->exec("CREATE TABLE pulse_notes (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(90), user_id INT, kind VARCHAR(20), body TEXT, to_email VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$sql = file_get_contents($app . '/migrations/2027-03-13_comms.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $sql)))) as $q) $pdo->exec($q);
foreach (array_filter(array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $sql)))) as $q) $pdo->exec($q);   // twice: safe to run again
$pdo->exec("INSERT INTO users VALUES (1,'owner@biz.test','Olive Owner',0),(2,'gen@biz.test','Gene General',0),(3,'acc@biz.test','Ada Accountant',0),(4,'maria@member.test','Maria B',0),(5,'sus@member.test','Sus',1),(6,'jason@staff.test','Jason',0),(7,'mgr@staff.test','Mo Manager',0),(8,'dir@staff.test','Di Director',0)");
$pdo->exec("INSERT INTO entities VALUES (10,'Joe\\'s Diner','managed')");
$pdo->exec("INSERT INTO listing_access VALUES (10,1,'owner')");
$pdo->exec("CREATE TABLE role_grants (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, role VARCHAR(20), side VARCHAR(10), scope_type VARCHAR(10), scope_id VARCHAR(20), on_behalf_of INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL)");
$pdo->exec("INSERT INTO role_grants (user_id, role, side, scope_type, scope_id) VALUES (2,'general','org','entity','10'),(3,'accountant','org','entity','10')");
$pdo->exec("INSERT INTO staff_accounts (user_id, staff_id, name, email, role, level, department, status) VALUES (6,'S1','Jason','jason@trvgo.test','admin','founder','','active'),(7,'S2','Mo','mo@trvgo.test','staff','manager','ops','active'),(8,'S3','Di','di@trvgo.test','staff','director','ops','active')");

require $app . '/lib/Notify.php'; require $app . '/lib/Notices.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$q = fn (string $s) => $pdo->query($s)->fetchAll(PDO::FETCH_ASSOC);
$one = fn (string $s) => $pdo->query($s)->fetchColumn();
$last = fn (array $a) => $a ? $a[count($a) - 1] : '';
$stages = fn (int $mid) => array_map(fn ($r) => $r['via'] . ':' . $r['stage'], Notify::routeOf($pdo, $mid));

$t('the migration ran twice without error; ready', Notify::ready($pdo));
$t('every kind has a level of the six and a name', !array_filter(Notify::kinds(), fn ($d) => !isset(Notify::LEVELS[$d[2]]) || $d[0] === ''));

// a business's team, by role (0102 §4: RBAC): a message goes to Owner and General (messages), not the Accountant
$n = Notify::send($pdo, 'biz.message', ['entity' => 10, 'except' => [4]], ['thing' => 'test:1', 'thread' => 'bizmsg:10:4', 'url' => '/business/10#messages', 'pulse_ref' => 'bizmsg:10:4', 'from' => ['user', 4, 'Maria B']], ['title' => 'Maria B wrote to Joe\'s Diner', 'body' => 'Read it.']);
$to = array_map('intval', array_column($q("SELECT to_user FROM comms_messages WHERE kind = 'biz.message' ORDER BY to_user"), 'to_user'));
$t('biz.message reaches the Owner and the General member, not the Accountant', $n === 2 && $to === [1, 2], $to);
$t('…each emailed by system email, the words saying what happened', count(Mailer::$sent) === 2 && str_contains(Mailer::$sent[0]['subject'], 'wrote to Joe') && str_contains(Mailer::$sent[0]['text'], 'Stop these: https://traversence.com/email/stop.php?t='), Mailer::$sent);
$mid = (int) $one("SELECT id FROM comms_messages WHERE kind = 'biz.message' AND to_user = 1");
$t('…its route: created, addressed, delivered to Pulse, sent by email', $stages($mid) === ['pulse:created', 'pulse:addressed', 'pulse:delivered', 'email:sent'], $stages($mid));
$t('…the route keeps the address masked', str_contains((string) $one("SELECT detail FROM comms_route WHERE message_id = $mid AND via = 'email'"), 'o***@biz.test'));

// one email per conversation while unread
Mailer::$sent = [];
Notify::send($pdo, 'biz.message', ['entity' => 10, 'except' => [4]], ['thing' => 'test:2', 'thread' => 'bizmsg:10:4', 'pulse_ref' => 'bizmsg:10:4'], ['title' => 'Maria B wrote again', 'body' => '']);
$t('a second message in the same unread conversation adds to it: no second email', !Mailer::$sent && (int) $one("SELECT times FROM comms_messages WHERE id = $mid") === 2);
Notify::markRead($pdo, 1, [], 'biz.message', 'bizmsg:10:4');
Notify::send($pdo, 'biz.message', ['entity' => 10, 'except' => [4]], ['thing' => 'test:3', 'thread' => 'bizmsg:10:4', 'pulse_ref' => 'bizmsg:10:4'], ['title' => 'Maria B wrote a third time', 'body' => '']);
$t('once read, the next message emails again (to the one who read it; the other still has it unread)', count(Mailer::$sent) === 1 && Mailer::$sent[0]['to'] === 'owner@biz.test', Mailer::$sent);
$t('…and its read is on the route', in_array('pulse:read', $stages($mid), true));

// once per thing
Mailer::$sent = [];
Notify::send($pdo, 'biz.message', ['users' => [1]], ['thing' => 'bizmsg:once'], ['title' => 'first']);
Mailer::$sent = [];
$t('the same thing is sent once', Notify::send($pdo, 'biz.message', ['users' => [1]], ['thing' => 'bizmsg:once'], ['title' => 'again']) === 0 && !Mailer::$sent);

// an order: Urgent, to roles with messages or billing
Mailer::$sent = [];
$n = Notify::send($pdo, 'biz.order', ['entity' => 10, 'except' => [4]], ['thing' => 'order:5:a', 'thread' => 'bizmsg:10:4', 'pulse_ref' => 'bizmsg:10:4'], ['title' => 'Joe\'s Diner: New order #5', 'body' => '']);
$t('biz.order (Urgent) reaches Owner, General and the Accountant (billing)', $n === 3 && count(Mailer::$sent) === 3, Mailer::$sent);

// the person's choices
Notify::setChannel($pdo, 2, 'biz.message', 'email', false);
Mailer::$sent = [];
Notify::send($pdo, 'biz.message', ['entity' => 10], ['thing' => 'test:9', 'thread' => 'bizmsg:10:9'], ['title' => 'Someone new']);
$t('email switched off for a kind: Pulse only for that person', count(Mailer::$sent) === 1 && Mailer::$sent[0]['to'] === 'owner@biz.test', Mailer::$sent);
$m2 = (int) $one("SELECT id FROM comms_messages WHERE thing = 'test:9' AND to_user = 2");
$t('…said on the route', str_contains((string) $one("SELECT detail FROM comms_route WHERE message_id = $m2 AND stage = 'addressed'"), 'email off'));
$no = ''; try { Notify::setChannel($pdo, 1, 'biz.message', 'pulse', false); } catch (InvalidArgumentException $x) { $no = $x->getMessage(); }
$t('Pulse can\'t be switched off for messages, and says why', str_contains($no, 'always reach your Pulse'), $no);
$s = Notify::settings($pdo, 2, ['member', 'business']);
$row = array_values(array_filter($s['kinds'], fn ($k) => $k['kind'] === 'biz.message'))[0];
$t('the setting shows the choice and the fixed Pulse with its reason', $row['email'] === false && $row['pulse'] === true && $row['pulse_fixed'] !== '', $row);
$t('a member without a business sees no business kinds', !array_filter(Notify::settings($pdo, 4, ['member'])['kinds'], fn ($k) => str_starts_with($k['kind'], 'biz.')));
$t('biz.comment is off by email until chosen (0172 §2.3: by choice)', !Notify::emailOn($pdo, 1, 'biz.comment') && Notify::emailOn($pdo, 1, 'biz.message'));

// Stop these
$mid1 = (int) $one("SELECT id FROM comms_messages WHERE thing = 'test:9' AND to_user = 1");
$url = Notify::stopUrl($mid1, 1); parse_str((string) parse_url($url, PHP_URL_QUERY), $qs);
$r = Notify::fromStop($pdo, $qs['t']);
$t('a stop link names its kind', $r && $r['kind'] === 'biz.message' && $r['to_user'] === 1, $r);
$t('a tampered stop link is refused', Notify::fromStop($pdo, $mid1 . '-' . str_repeat('a', 24)) === null && Notify::fromStop($pdo, ($mid1 + 1) . '-' . substr($qs['t'], -24)) === null);
Notify::stop($pdo, $r);
$t('Stop these: that kind stops by email; Pulse keeps it', !Notify::emailOn($pdo, 1, 'biz.message') && in_array('email:stopped', $stages($mid1), true));
Notify::setChannel($pdo, 1, 'biz.message', 'email', true);
$t('…and the setting switches it back on', Notify::emailOn($pdo, 1, 'biz.message'));

// quiet hours: Notice, General and Special wait; Alert and above don't
$no = ''; try { Notify::setQuiet($pdo, 1, '22:00', '22:00', 'America/Denver'); } catch (InvalidArgumentException $x) { $no = $x->getMessage(); }
$t('quiet hours need a different start and end', $no !== '');
$now = new DateTime('now', new DateTimeZone('America/Denver')); $m = (int) $now->format('G') * 60 + (int) $now->format('i');
$hm = fn (int $x) => sprintf('%02d:%02d', intdiv(($x + 1440) % 1440, 60), (($x + 1440) % 1440) % 60);
Notify::setQuiet($pdo, 1, $hm($m - 30), $hm($m + 30), 'America/Denver');
$t('quiet now, in their own time zone', Notify::quietNow($pdo, 1));
$t('…not two hours later', !Notify::quietNow($pdo, 1, time() + 7200));
Notify::setChannel($pdo, 1, 'biz.comment', 'email', true);
Mailer::$sent = [];
Notify::send($pdo, 'biz.comment', ['entity' => 10], ['thing' => 'comment:1'], ['title' => 'A comment']);
$mc = (int) $one("SELECT id FROM comms_messages WHERE thing = 'comment:1' AND to_user = 1");
$t('a General message waits in quiet hours: held on its route, not emailed', !Mailer::$sent && $last($stages($mc)) === 'email:held', $stages($mc));
Notify::send($pdo, 'biz.message', ['entity' => 10], ['thing' => 'test:20', 'thread' => 'bizmsg:10:20'], ['title' => 'An Alert']);
$t('an Alert goes at once, quiet hours or not', count(Mailer::$sent) === 1, Mailer::$sent);
Mailer::$sent = [];
$t('the release job leaves it while quiet', Notify::releaseHeld($pdo) === 0 && !Mailer::$sent);
Notify::setQuiet($pdo, 1, null, null, '');
$t('…and sends it once quiet hours end (here: switched off)', Notify::releaseHeld($pdo) === 1 && count(Mailer::$sent) === 1 && $last($stages($mc)) === 'email:sent', $stages($mc));
$t('…only once', Notify::releaseHeld($pdo) === 0);
Notify::setQuiet($pdo, 1, $hm($m - 30), $hm($m + 30), 'America/Denver');
Notify::send($pdo, 'biz.comment', ['entity' => 10], ['thing' => 'comment:2'], ['title' => 'Another comment']);
$mc2 = (int) $one("SELECT id FROM comms_messages WHERE thing = 'comment:2' AND to_user = 1");
Notify::markRead($pdo, 1, [$mc2]);
Notify::setQuiet($pdo, 1, null, null, '');
Mailer::$sent = [];
Notify::releaseHeld($pdo);
$t('held, then read in Pulse: not emailed, said on the route', !Mailer::$sent && $last($stages($mc2)) === 'email:skipped', $stages($mc2));

// a failed send is written, never thrown
Mailer::$fail = true;
$n = Notify::send($pdo, 'biz.message', ['entity' => 10], ['thing' => 'test:30', 'thread' => 'bizmsg:10:30'], ['title' => 'Fails']);
Mailer::$fail = false;
$mf = (int) $one("SELECT id FROM comms_messages WHERE thing = 'test:30' AND to_user = 1");
$t('SMTP refusing: the message is still in Pulse, the failure on its route', $n === 2 && $last($stages($mf)) === 'email:failed', $stages($mf));

// suspended accounts and unknown kinds
$t('a suspended account gets nothing', Notify::send($pdo, 'notice.post_comment', ['users' => [5]], ['thing' => 'x'], ['title' => 'x']) === 0);
$t('an unknown kind sends nothing', Notify::send($pdo, 'no.such', ['users' => [4]], ['thing' => 'x'], ['title' => 'x']) === 0);

// notices through the dispatcher: as before
$pdo->exec("INSERT INTO notice_delivery VALUES (4,'email',NULL)");
SesMail::$sent = [];
$n = Notices::send($pdo, [4, 4, 0], 'post_comment', 'mp:1', 'Sam commented on your post', 'Nice!', '/post.php?id=1');
$t('Notices::send: one notice, in notices_sent as before', $n === 1 && (int) $one("SELECT COUNT(*) FROM notices_sent WHERE user_id = 4") === 1);
$t('…emailed by SES to someone who chose "by email as they happen"', count(SesMail::$sent) === 1 && SesMail::$sent[0]['purpose'] === 'notice', SesMail::$sent);
$mn = (int) $one("SELECT id FROM comms_messages WHERE kind = 'notice.post_comment' AND to_user = 4");
$t('…recorded once by the dispatcher, shown in Pulse by notices_sent', (string) $one("SELECT pulse_ref FROM comms_messages WHERE id = $mn") !== '' && str_starts_with((string) $one("SELECT pulse_ref FROM comms_messages WHERE id = $mn"), 'notices:'));
$t('…not listed twice in Pulse', !array_filter(Notify::forUser($pdo, 4), fn ($r) => $r['kind'] === 'notice.post_comment'));
SesMail::$sent = [];
$t('…once per person, kind and thing', Notices::send($pdo, [4], 'post_comment', 'mp:1', 'again') === 0 && !SesMail::$sent);
$pdo->exec("INSERT INTO notice_prefs VALUES (4,'group_near',NOW())");
$t('…a kind switched off sends nothing', Notices::send($pdo, [4], 'group_near', 'g:1', 'A group near you') === 0);
Notify::setChannel($pdo, 4, 'notice.post_comment', 'email', false);
SesMail::$sent = [];
Notices::send($pdo, [4], 'post_comment', 'mp:2', 'Another comment');
$t('per-kind email off for a notice: Pulse only', !SesMail::$sent && (int) $one("SELECT COUNT(*) FROM notices_sent WHERE user_id = 4") === 2);
$pdo->exec("INSERT INTO email_suppress VALUES ('" . SesMail::hash('maria@member.test') . "','notices','unsubscribe',NOW())");
$t('"Stop these" in a notice email shows in the setting as off', !Notify::emailOn($pdo, 4, 'notice.guide_ready'));
Notify::setChannel($pdo, 4, 'notice.guide_ready', 'email', true);
$t('…switching one back on, signed in, lifts it', Notify::emailOn($pdo, 4, 'notice.guide_ready') && !(int) $one("SELECT COUNT(*) FROM email_suppress"));
Notify::setChannel($pdo, 4, 'notice.group_near', 'pulse', true);
$t('a notice kind\'s Pulse switch is its old off switch', !(int) $one("SELECT COUNT(*) FROM notice_prefs WHERE user_id = 4 AND kind = 'group_near'"));
Notify::setDigest($pdo, 4, true);
$t('the weekly email on: earlier "by email" choices kept per kind', (string) $one("SELECT how FROM notice_delivery WHERE user_id = 4") === 'digest' && Notify::emailOn($pdo, 4, 'notice.guide_ready') && !Notify::emailOn($pdo, 4, 'notice.post_comment'));

// the bell: Alert or above, unread, from the dispatcher's own rows (others count in their own tables)
$pdo->exec("UPDATE comms_messages SET read_at = NULL WHERE to_user = 1");
$t('the bell counts unread Alert and above that Pulse shows from here', Notify::unreadForBell($pdo, 1) === (int) $one("SELECT COUNT(*) FROM comms_messages WHERE to_user = 1 AND pulse_ref IS NULL AND lvl IN ('critical','urgent','alert')"));
$t('…a General comment isn\'t on the bell', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE to_user = 1 AND kind = 'biz.comment'") > 0 && Notify::unreadForBell($pdo, 1) === (int) $one("SELECT COUNT(*) FROM comms_messages WHERE to_user = 1 AND pulse_ref IS NULL AND kind <> 'biz.comment'"));
$f = Notify::forUser($pdo, 1, 10);
$t('a business\'s Pulse lists what it sent from here (comments), not what its conversations show', $f && !array_filter($f, fn ($r) => $r['kind'] === 'biz.order'), array_column($f, 'kind'));

// the callers
require $app . '/lib/BizMessages.php'; require $app . '/lib/GuestInquiries.php';
$pdo->exec("UPDATE comms_messages SET read_at = NOW() WHERE thread = 'bizmsg:10:4'");   // earlier tests left these unread
Mailer::$sent = [];
BizMessages::personSend($pdo, 4, 10, 'Are you open Saturday?');
$t('BizMessages::personSend tells the team (gap 1)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'biz.message' AND thread = 'bizmsg:10:4' AND sender_id = 4 AND thing LIKE 'bizmsg:%'") >= 2 && Mailer::$sent);
$before = (int) $one("SELECT COUNT(*) FROM comms_messages");
BizMessages::personSend($pdo, 4, 10, "New order #7 from the Marketplace: \$12.00\nhttps://traversence.com/business/10/quotes", 'biz.order');
$t('…an order\'s note is told as an order (gap 2), not also as a message', (int) $one("SELECT COUNT(*) FROM comms_messages") - $before === 3 && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'biz.order' AND title LIKE 'Joe%New order #7%'") === 3);
Auth::$me = ['id' => 1];
$pdo->exec("UPDATE comms_messages SET read_at = NULL WHERE to_user = 1");
BizMessages::bizThread($pdo, 10, 4);
$t('opening the conversation reads its messages for that person', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE to_user = 1 AND thread = 'bizmsg:10:4' AND read_at IS NULL"));
$_SESSION['gq']['n1'] = ['t' => time() - 60, 'sum' => 7];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9'; $_SERVER['HTTP_USER_AGENT'] = 'test';
Mailer::$sent = [];
try { GuestInquiries::create($pdo, 10, ['nonce' => 'n1', 'answer' => '7', 'name' => 'Pat', 'contact_type' => 'email', 'contact' => 'pat@guest.test', 'body' => 'Is there parking?']); $gq = ''; } catch (Throwable $x) { $gq = $x->getMessage(); }
$t('a guest question tells the team (biz.question), the guest\'s email never in it', $gq === '' && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'biz.question'") === 2 && Mailer::$sent && !str_contains(json_encode(Mailer::$sent), 'pat@guest.test'), [$gq, Mailer::$sent]);
$gid = (int) $one('SELECT MAX(id) FROM guest_inquiries');
GuestInquiries::bizThread($pdo, 10, $gid);
$t('…opening it reads it', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE to_user = 1 AND kind = 'biz.question' AND read_at IS NULL"));

// staff: assigned and escalated (gap 4)
require $app . '/lib/Pulse.php';
Mailer::$sent = [];
Pulse::mark($pdo, 'dispute:1', 'assign', 6, 7);
$t('a case assigned: the person is told by email, to their staff mailbox', count(Mailer::$sent) === 1 && Mailer::$sent[0]['to'] === 'mo@trvgo.test' && str_contains(Mailer::$sent[0]['subject'], 'assigned you case #'), Mailer::$sent);
$t('…its Pulse row is the case itself (no second row)', str_starts_with((string) $one("SELECT pulse_ref FROM comms_messages WHERE kind = 'case.assigned'"), 'case:'));
Mailer::$sent = [];
Pulse::mark($pdo, 'dispute:1', 'assign', 7, 7);
$t('…taking it yourself tells no one', !Mailer::$sent);
Pulse::mark($pdo, 'dispute:1', 'escalate', 7);
$to = array_column(Mailer::$sent, 'to');
$t('escalated by the only Manager: the Founder is told, not the one who escalated', $to === ['jason@trvgo.test'], $to);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
