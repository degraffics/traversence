<?php
// Communications, the rest as one release (decisions/0167; 0172 §6 steps 3-6): the email engine's setup (CommsSetup),
// .env secrets kept and put back (EnvSettings), the seal (CommsCrypt), Notify's push channel, Critical always reaching email,
// message control's pauses, the six levels, flags and tags (PulseMarks), mailboxes in Pulse (Mailboxes) and SES receiving
// (SesInbound), on a real MySQL or MariaDB with the live libraries and stand-ins only for what reaches outside (mail
// servers, push services, cPanel, SES) or the session.
//   Run: mariadb -e 'CREATE DATABASE tvcomms'; php comms-engine.php <a copy of app/ with the release laid over it>
// The release's migration (app/migrations/2027-03-20_comms_engine.sql) and step 2's (2027-03-13_comms.sql) must be in it.
declare(strict_types=1);
$app = $argv[1] ?? '';
if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php comms-engine.php <app dir>\n"); exit(2); }
$env = sys_get_temp_dir() . '/tv-comms-test-' . getmypid() . '.env';
file_put_contents($env, "DB_NAME=x\nSMTP_PASSWORD=old-one\n");

// ---- stand-ins ---------------------------------------------------------------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvcomms;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
    public static function envPath(): string { return $GLOBALS['env']; }
    public static function readEnv(): void { foreach (preg_split('/\n/', (string) file_get_contents($GLOBALS['env'])) as $l) if (preg_match('/^([A-Z0-9_]+)="?(.*?)"?$/', $l, $m)) putenv($m[1] . '=' . $m[2]); }
}
final class Mailer { public static array $sent = []; public static bool $routed = false;
    public static function send(string $to, string $subject, string $text, ?string $name = null, string $as = '', string $html = '', string $replyTo = ''): void { self::$sent[] = compact('to', 'subject', 'text', 'as', 'replyTo'); } }
final class StagingGate { public static bool $here = false; public static function here(): bool { return self::$here; } public static function hold(string $u, string $w = '', string $r = '', bool $b = false): ?string { return self::$here ? 'Held on staging' : null; } }
final class WebPush { public static array $sent = [];
    public static function sendToUser(PDO $p, int $u, array $m): array { $n = (int) $p->query('SELECT COUNT(*) FROM push_subs WHERE user_id = ' . $u)->fetchColumn(); if (!$n) return ['sent' => 0, 'failed' => 0, 'held' => 0, 'none' => true]; self::$sent[] = ['u' => $u] + $m; return ['sent' => $n, 'failed' => 0, 'held' => 0, 'none' => false]; } }
final class MailImap { public static string $refuse = ''; public static array $box = []; public static array $moves = []; public static array $appended = [];
    public function __construct(array $c) {} public static function check(array $c): string { return self::$refuse; }
    public function login(): void { if (self::$refuse !== '') throw new RuntimeException(self::$refuse); }
    public function select(string $f, bool $ro = false): array { return ['exists' => count(self::$box), 'uidvalidity' => 7, 'uidnext' => count(self::$box) + 1]; }
    public function uidsSince(int $d): array { return array_keys(self::$box); } public function uidsAfter(int $u, int $m = 50): array { return array_values(array_filter(array_keys(self::$box), fn ($x) => $x > $u)); }
    public function fetch(array $u): array { return array_map(fn ($x) => ['uid' => $x, 'flags' => [], 'size' => 1, 'raw' => self::$box[$x]], $u); }
    public function flagsOf(array $u): array { return []; } public function setSeen(int $u, bool $on): void {} public function special(string $k): string { return $k; }
    public function move(int $u, string $f): void { self::$moves[] = [$u, $f]; } public function append(string $f, string $raw, array $fl = []): void { self::$appended[] = $f; } public function logout(): void {} }
final class MailSmtp { public static array $sent = []; public static function send(array $c, string $from, array $r, string $raw): void { self::$sent[] = compact('from', 'r'); } public static function check(array $c): string { return $c['password'] === 'right' ? '' : 'The mail server refused the password for ' . $c['login'] . '.'; } }
final class ListingMessages { public static array $replies = []; public static array $bounced = [];
    public static function emailReply(PDO $p, string $to, array $parsed): bool { self::$replies[] = $to; return true; } public static function bounced(PDO $p, string $a): void { self::$bounced[] = $a; } }
final class Operations { public const KEYS = []; public static function costs(PDO $p): array { return ['revenue' => 100.0]; } }   // KEYS: EnvSettings::secrets() reads it (decisions/0173 §3)
final class Notices { const KINDS = []; public static function ready(PDO $p): bool { return false; } public static function emailOn(PDO $p): bool { return false; } public static function delivery(PDO $p, int $u): string { return 'pulse'; } public static function deliveryReady(PDO $p): bool { return false; } }
foreach (['Database', 'Mailer', 'StagingGate', 'WebPush', 'MailImap', 'MailSmtp', 'ListingMessages', 'Operations', 'Notices'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// test stand-in\n");

$pdo = Database::connection();
foreach (['comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet', 'comms_routing', 'comms_overseers', 'comms_setup_log', 'comms_controls', 'pulse_flags', 'pulse_tags', 'comms_mailboxes', 'comms_mail', 'push_subs',
          'users', 'staff_accounts', 'content_comments', 'role_grants', 'entities'] as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_suspended TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(100))");
$pdo->exec("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, staff_id VARCHAR(20), name VARCHAR(80), email VARCHAR(255), role VARCHAR(20), level VARCHAR(20), department VARCHAR(10), status VARCHAR(10))");
$pdo->exec("CREATE TABLE content_comments (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10), ref VARCHAR(40), user_id INT, body TEXT, created_at DATETIME, hidden TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE role_grants (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, role VARCHAR(20), side VARCHAR(10), scope_type VARCHAR(10), scope_id VARCHAR(20), on_behalf_of INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL)");
foreach (['2027-03-13_comms.sql', '2027-03-20_comms_engine.sql'] as $f) {
    $sql = file_get_contents($app . '/migrations/' . $f);
    for ($i = 0; $i < 2; $i++) foreach (array_filter(array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $sql)))) as $q) $pdo->exec($q);   // twice: safe to run again
}
$pdo->exec("INSERT INTO users VALUES (1,'jason@x.test','Jason',0),(2,'mo@x.test','Mo',0),(3,'sam@x.test','Sam',0),(4,'maria@member.test','Maria',0)");
$pdo->exec("INSERT INTO staff_accounts (user_id, staff_id, name, email, role, level, department, status) VALUES (1,'S1','Jason','jason@trvgo.test','admin','founder','','active'),(2,'S2','Mo','mo@trvgo.test','staff','manager','ops','active'),(3,'S3','Sam','sam@trvgo.test','staff','staff','ops','active')");

require $app . '/lib/Notify.php'; require $app . '/lib/CommsSetup.php'; require $app . '/lib/PulseMarks.php'; require $app . '/lib/Mailboxes.php'; require $app . '/lib/EnvSettings.php'; require $app . '/lib/CommsCrypt.php'; require $app . '/lib/Pulse.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$one = fn (string $s) => $pdo->query($s)->fetchColumn();
$stages = fn (int $mid) => array_map(fn ($r) => $r['via'] . ':' . $r['stage'], Notify::routeOf($pdo, $mid));

$t('both migrations ran twice without error; everything ready', Notify::ready($pdo) && CommsSetup::ready($pdo) && PulseMarks::ready($pdo) && Mailboxes::ready($pdo));
$cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('comms_route','content_comments')")->fetchAll(PDO::FETCH_COLUMN);
$t('…the new columns are there (the route\'s sender and subject; comments\' parent and who hid them)', !array_diff(['sender_key', 'addr_hash', 'subject', 'mail_kind', 'parent_id', 'hidden_by', 'hidden_at'], $cols));

// ---- the six levels
$t('Pulse has the six levels, the stored keys kept (Update shows as Notice, Event as General)', array_keys(Pulse::SEVERITY) === ['critical', 'urgent', 'alert', 'update', 'event', 'special'] && Pulse::SEVERITY['update'] === 'Notice' && Pulse::SEVERITY['event'] === 'General');
$t('…no clock on the new levels', Pulse::SLA_HOURS['urgent'] === 0 && Pulse::SLA_HOURS['special'] === 0 && Pulse::SLA_HOURS['critical'] === 2);
$t('every kind has one of the six levels, a name, and a body only where its owner draws one', !array_filter(Notify::kinds(), fn ($d) => !isset(Notify::LEVELS[$d[2]]) || $d[0] === '') && !array_diff_key(Notify::BODIES, Notify::kinds()));

// ---- the seal and .env
$s = CommsCrypt::seal('app-pass-1');
$t('a secret is sealed (never kept in the clear) and opens again', str_starts_with($s, 'v1:') && !str_contains($s, 'app-pass') && CommsCrypt::open($s) === 'app-pass-1');
$t('…its key was made and written to .env by the site', preg_match('/^COMMS_SEAL_KEY=\S{40,}$/m', (string) file_get_contents($env)) === 1);
$t('…a tampered seal opens to nothing (then the person is asked to reconnect)', CommsCrypt::open(substr($s, 0, -4) . 'AAAA') === '');
EnvSettings::setMailSecret('SMTP_PASSWORD', 'new-one');
$tx = (string) file_get_contents($env);
$t('a mailbox password written to .env keeps the old one as a dated comment', str_contains($tx, 'SMTP_PASSWORD=new-one') && preg_match('/^# OLD SMTP_PASSWORD \d{4}-\d\d-\d\d: old-one$/m', $tx) === 1 && EnvSettings::hasOld('SMTP_PASSWORD'));
EnvSettings::putBack('SMTP_PASSWORD');
$tx = (string) file_get_contents($env);
$t('…Undo puts it back and the comment goes', str_contains($tx, 'SMTP_PASSWORD=old-one') && !EnvSettings::hasOld('SMTP_PASSWORD'));
$err = ''; try { EnvSettings::setMailSecret('DB_PASSWORD', 'x'); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…only the mail secrets can be written that way', $err !== '');

// ---- routing, overseers, pauses
$t('a kind of email asks for its sender: requests to unclaimed listings go from requests@ (REQUESTS)', CommsSetup::prefixFor('listing_request', $pdo) === 'REQUESTS' && CommsSetup::prefixFor('codes', $pdo) === '');
CommsSetup::setRoute($pdo, 'work_orders', 'system', 1);
$t('…the routing table changes it, and it\'s logged', CommsSetup::prefixFor('work_orders', $pdo) === '' && (int) $one("SELECT COUNT(*) FROM comms_setup_log WHERE what = 'route'") === 1);
$err = ''; try { CommsSetup::setRoute($pdo, 'campaigns', 'ses_bulk', 1); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…a sender that isn\'t built can\'t be chosen', str_contains($err, 'isn\'t built'));
CommsSetup::setOverseer($pdo, 'support@traversence.com', 2, true, 1);
$t('an overseer reads the mailbox; their own staff mailbox is always theirs', CommsSetup::oversees($pdo, 2) === ['support@traversence.com', 'mo@trvgo.test']);
$err = ''; try { CommsSetup::setOverseer($pdo, 'support@traversence.com', 4, true, 1); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…only active staff can oversee one', $err !== '');
putenv('SMTP_FROM_EMAIL=donotreply@traversence.com'); putenv('SMTP_USERNAME=support@traversence.com');
CommsSetup::sendFailed($pdo, 'system', 'Authentication failed');
$m = $pdo->query("SELECT * FROM comms_messages WHERE kind = 'mail.failure'")->fetch(PDO::FETCH_ASSOC);
$t('a failed send reaches the sender mailbox\'s overseers in Pulse, once an hour', $m && (int) $m['to_user'] === 2 && str_contains($m['body'], 'Authentication failed'));
CommsSetup::sendFailed($pdo, 'system', 'Authentication failed again');
$t('…not twice in the hour', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'mail.failure'") === 1);
$b = Notify::body($pdo, $m, 2);
$t('…its body carries Check again and the sender\'s card, and where it stands', $b && str_contains($b['html'], 'Check again') && str_contains($b['html'], 'data-msg-act="/admin/api/comms.php"') && $b['state'] !== '');

// ---- Notify: Critical always, push, quiet hours, pauses
$pdo->exec("INSERT INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (4, 'listing.left', 'email', 0, NOW())");
Mailer::$sent = [];
Notify::send($pdo, 'listing.left', ['users' => [4]], ['thing' => 'left:1:sent'], ['title' => 'Sent by email', 'body' => 'x']);
$t('a Notice whose email the person turned off reaches Pulse only', !Mailer::$sent);
$t('no device: no push, and nothing on the route says push', !WebPush::$sent && !in_array('push:sent', $stages((int) $one("SELECT MAX(id) FROM comms_messages")), true));
$pdo->exec("INSERT INTO push_subs (user_id, endpoint, endpoint_hash, p256dh, auth_key, created_at) VALUES (4, 'https://fcm.googleapis.com/x', SHA2('x',256), 'p', 'a', NOW())");
Notify::send($pdo, 'group.join', ['users' => [4]], ['thing' => 'j:1'], ['title' => 'Someone asked to join', 'body' => '']);
$mid = (int) $one("SELECT MAX(id) FROM comms_messages");
$t('an Alert with a device: pushed (on by default for Alert and up), with its Pulse link and Stop these', count(WebPush::$sent) === 1 && str_contains(WebPush::$sent[0]['stop'], '/email/stop.php?t=') && in_array('push:sent', $stages($mid), true));
WebPush::$sent = [];
Notify::send($pdo, 'comment.reply', ['users' => [4]], ['thing' => 'r:1'], ['title' => 'A reply', 'body' => '']);
$t('…a General one isn\'t pushed unless chosen', !WebPush::$sent);
Notify::setChannel($pdo, 4, 'comment.reply', 'push', true);
Notify::send($pdo, 'comment.reply', ['users' => [4]], ['thing' => 'r:2'], ['title' => 'Another reply', 'body' => '']);
$t('…and is once the person turns push on for it', count(WebPush::$sent) === 1);
WebPush::$sent = [];
Notify::setQuiet($pdo, 4, '00:00', '23:59', 'UTC');
Notify::send($pdo, 'group.join', ['users' => [4]], ['thing' => 'j:2'], ['title' => 'Another join request', 'body' => '']);
$mid = (int) $one("SELECT MAX(id) FROM comms_messages");
$t('quiet hours: an Alert\'s push waits, its email goes (0172: Alert holds only push)', !WebPush::$sent && in_array('push:held', $stages($mid), true) && in_array('email:sent', $stages($mid), true), $stages($mid));
Notify::setQuiet($pdo, 4, null, null, '');
$n = Notify::releaseHeld($pdo);
$t('…once they end, the held push goes', $n >= 1 && count(WebPush::$sent) === 1 && in_array('push:sent', $stages($mid), true));
$pdo->exec("INSERT INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (4, 'mail.failure', 'email', 0, NOW())");
$err = ''; try { Notify::setChannel($pdo, 4, 'mail.failure', 'push', false); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('push can be turned off for an Alert kind', $err === '');
CommsSetup::pause($pdo, 'kind:group.join', 'Testing a pause', 1);
Mailer::$sent = []; WebPush::$sent = [];
Notify::send($pdo, 'group.join', ['users' => [4]], ['thing' => 'j:3'], ['title' => 'A third join request', 'body' => '']);
$mid = (int) $one("SELECT MAX(id) FROM comms_messages");
$t('message control: a paused kind still reaches Pulse; its email and push are held with the reason', !Mailer::$sent && !WebPush::$sent && in_array('pulse:delivered', $stages($mid), true) && in_array('email:held', $stages($mid), true) && str_contains((string) $one("SELECT detail FROM comms_route WHERE message_id = $mid AND via = 'email'"), 'Paused'));
CommsSetup::resume($pdo, 'kind:group.join', 1);
$t('…resumed', !CommsSetup::paused($pdo, 'kind:group.join'));
$set = Notify::settings($pdo, 4, ['member']);
$row = array_values(array_filter($set['kinds'], fn ($k) => $k['kind'] === 'comment.reply'))[0] ?? [];
$t('the Communications setting shows push per kind and whether a device is on', ($row['push'] ?? null) === true && $set['devices'] === true);
$t('…a kind for anyone (a follow-up) shows to a member', (bool) array_filter($set['kinds'], fn ($k) => $k['kind'] === 'followup.due'));

// ---- flags and tags
$r = PulseMarks::set($pdo, 4, 'comms:5', ['follow' => 'tomorrow', 'tz' => 'America/Phoenix']);
$t('Follow up tomorrow: flagged, with a time', $r['marks']['flag'] === 'open' && $r['marks']['due'] !== null);
$t('…it\'s in the Follow up view', isset(PulseMarks::view($pdo, 4, 'flagged')['comms:5']));
$pdo->exec("UPDATE pulse_flags SET due_at = NOW() - INTERVAL 1 MINUTE WHERE item = 'comms:5'");
$n = PulseMarks::due($pdo);
$t('…at its time it comes back unread and the person is told once (followup.due, a Notice)', $n === 1 && (int) $one("SELECT unread_again FROM pulse_flags WHERE item = 'comms:5'") === 1 && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'followup.due' AND to_user = 4") === 1);
$t('…and only once', PulseMarks::due($pdo) === 0);
$fm = $pdo->query("SELECT * FROM comms_messages WHERE kind = 'followup.due'")->fetch(PDO::FETCH_ASSOC);
$b = Notify::body($pdo, $fm, 4);
$t('…its message carries Mark complete and Clear flag', str_contains($b['html'], 'Mark complete') && str_contains($b['html'], 'Clear flag'));
PulseMarks::set($pdo, 4, 'comms:5', ['flag' => 'done']);
$t('Mark complete takes it out of Follow up', !isset(PulseMarks::view($pdo, 4, 'flagged')['comms:5']));
$tag = PulseMarks::addTag($pdo, 4, 'Trip', '#B45309');
PulseMarks::set($pdo, 4, 'mail:9', ['tags' => [$tag['id']], 'importance' => 'high', 'pinned' => true]);
$t('tags, importance and pins are the person\'s own', isset(PulseMarks::view($pdo, 4, 'tag', (string) $tag['id'])['mail:9']) && isset(PulseMarks::view($pdo, 4, 'high')['mail:9']) && isset(PulseMarks::view($pdo, 4, 'pinned')['mail:9']));
$err = ''; try { PulseMarks::set($pdo, 3, 'mail:9', ['tags' => [$tag['id']]]); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…no one else can use someone\'s tag', $err !== '');
$t('…and no one else sees the marks', PulseMarks::of($pdo, 3, ['mail:9']) === []);
$st = PulseMarks::addTag($pdo, 2, 'refund', '#B91C1C', 'staff:0');
$t('staff share team tags', (bool) array_filter(PulseMarks::tags($pdo, 3), fn ($x) => $x['id'] === $st['id'] && $x['shared']));
PulseMarks::set($pdo, 4, 'mail:9', ['folder' => 'Trips']);
$t('filed in a folder: out of the list, in its folder', isset(PulseMarks::view($pdo, 4, 'folder', 'Trips')['mail:9']) && PulseMarks::folders($pdo, 4) === ['Trips' => 1]);
PulseMarks::deleteTag($pdo, 4, $tag['id']);
$t('a tag removed comes off the items that carried it', PulseMarks::of($pdo, 4, ['mail:9'])['mail:9']['tags'] === []);
$err = ''; try { PulseMarks::set($pdo, 4, 'not an item', []); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…an item name is checked', $err !== '');

// ---- mailboxes in Pulse
$err = ''; try { Mailboxes::connect($pdo, 4, 'staff', ['provider' => 'imap', 'address' => 'm@yahoo.com', 'password' => 'p']); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('members don\'t connect mailboxes yet (the App store, 0169)', $err !== '');
MailImap::$refuse = 'The mail server refused the password for sam@yahoo.com.';
$err = ''; try { Mailboxes::connect($pdo, 3, 'staff', ['provider' => 'imap', 'address' => 'sam@yahoo.com', 'password' => 'wrong']); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('a wrong password shows at once, in the server\'s words', str_contains($err, 'refused the password'));
MailImap::$refuse = '';
[$bid, $say] = Mailboxes::connect($pdo, 3, 'staff', ['provider' => 'imap', 'address' => 'sam@yahoo.com', 'password' => 'right']);
$bx = $pdo->query("SELECT * FROM comms_mailboxes WHERE id = $bid")->fetch(PDO::FETCH_ASSOC);
$t('an outside mailbox of a staff member waits for their manager; Yahoo\'s server filled in; the password sealed', $bx['status'] === 'pending' && $bx['host'] === 'imap.mail.yahoo.com' && str_starts_with((string) $bx['secret'], 'v1:') && !str_contains((string) $bx['secret'], 'right'));
$ask = $pdo->query("SELECT * FROM comms_messages WHERE kind = 'mailbox.reconnect' AND thing = 'mbapprove:$bid'")->fetch(PDO::FETCH_ASSOC);
$t('…their Manager is asked, with Approve and Decline in the message', $ask && (int) $ask['to_user'] === 2 && str_contains(Notify::body($pdo, $ask, 2)['html'], 'Approve'));
$err = ''; try { Mailboxes::approve($pdo, 3, $bid, true); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…they can\'t approve their own', $err !== '');
Mailboxes::approve($pdo, 2, $bid, true);
$t('…the Manager approves it: active, and the person is told', $one("SELECT status FROM comms_mailboxes WHERE id = $bid") === 'active' && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing LIKE 'mbapproved:$bid:%' AND to_user = 3") === 1);
$raw = fn (string $id, string $from, string $subj, string $refs = '') => "Message-ID: <$id>\r\nFrom: Ann <$from>\r\nTo: sam@yahoo.com\r\nSubject: $subj\r\nDate: Wed, 18 Nov 2026 10:00:00 +0000\r\n" . ($refs ? "In-Reply-To: <$refs>\r\nReferences: <$refs>\r\n" : '') . "Content-Type: text/html; charset=UTF-8\r\n\r\n<p>Hello</p><script>alert(1)</script><img src=\"https://t.example/p.gif\">";
MailImap::$box = [1 => $raw('a1@x', 'ann@x.test', 'Trip'), 2 => $raw('a2@x', 'ann@x.test', 'Re: Trip', 'a1@x'), 3 => $raw('b1@x', 'bob@x.test', 'Hello')];
$n = Mailboxes::sync($pdo, $bid);
$t('its mail comes in: three emails', $n === 3);
$t('…and only new ones the next time', Mailboxes::sync($pdo, $bid) === 0);
$ib = Mailboxes::inbox($pdo, 3);
$t('the inbox shows one row per conversation, the newest first, labelled with its mailbox', count($ib['rows']) === 2 && $ib['rows'][0]['mailbox'] === 'sam@yahoo.com' && in_array(2, array_column($ib['rows'], 'count'), true));
$err = ''; try { Mailboxes::inbox($pdo, 2, $bid); Mailboxes::thread($pdo, 2, (int) $ib['rows'][0]['id']); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('…no one else reads it (not even the Manager who approved it)', $err !== '' && Mailboxes::inbox($pdo, 2, $bid)['rows'] === []);
$tid = (int) $one("SELECT id FROM comms_mail WHERE uid = '2'");
$th = Mailboxes::thread($pdo, 3, $tid);
$t('opened: the conversation shown safely (no script, outside pictures held)', count($th['messages']) === 2 && !str_contains($th['messages'][0]['html'], '<script') && !preg_match('/\ssrc="https:/', $th['messages'][0]['html']) && $th['messages'][0]['held_pictures']);
$t('…and read', (int) $one("SELECT COUNT(*) FROM comms_mail WHERE mailbox_id = $bid AND seen = 0") === 1);
$t('…From offers only the mailboxes they read', array_column($th['from_choices'], 'address') === ['sam@yahoo.com']);
Mailboxes::send($pdo, 3, ['from' => $bid, 'to' => 'ann@x.test', 'text' => 'Thanks!', 'reply_to' => $tid]);
$t('a reply goes from that mailbox\'s own SMTP and lands in its Sent folder and ours', count(MailSmtp::$sent) === 1 && MailSmtp::$sent[0]['from'] === 'sam@yahoo.com' && MailImap::$appended === ['Sent'] && (int) $one("SELECT COUNT(*) FROM comms_mail WHERE direction = 'out' AND subject = 'Re: Re: Trip'") + (int) $one("SELECT COUNT(*) FROM comms_mail WHERE direction = 'out' AND subject = 'Re: Trip'") === 1);
$t('…in the same conversation', count(Mailboxes::thread($pdo, 3, $tid)['messages']) === 3);
Mailboxes::act($pdo, 3, (int) $one("SELECT id FROM comms_mail WHERE uid = '3'"), 'archive');
$t('Archive moves it in the mailbox too', MailImap::$moves === [[3, 'Archive']]);
$err = ''; try { Mailboxes::send($pdo, 3, ['from' => $bid, 'to' => 'not an address', 'text' => 'x']); } catch (InvalidArgumentException $x) { $err = $x->getMessage(); }
$t('a wrong address is said plainly', str_contains($err, 'isn\'t an email address'));
Mailboxes::makeCase($pdo, 3, $tid);
$t('Make it a case: one of ours, the email linked', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing = 'mailcase:$tid' AND to_user = 3") === 1);
MailImap::$refuse = 'The mailbox is full.';
Mailboxes::sync($pdo, $bid);
$fm = $pdo->query("SELECT * FROM comms_messages WHERE thing LIKE 'mbfail:$bid:%'")->fetch(PDO::FETCH_ASSOC);
$t('a refusal marks it and becomes an Alert in its owner\'s Pulse with Reconnect in it', $one("SELECT status FROM comms_mailboxes WHERE id = $bid") === 'failed' && $fm && str_contains(Notify::body($pdo, $fm, 3)['html'], 'Reconnect'));
MailImap::$refuse = '';
Mailboxes::reconnect($pdo, 3, $bid, 'right2');
$t('…Reconnect with a new password: active again', $one("SELECT status FROM comms_mailboxes WHERE id = $bid") === 'active' && str_contains(Notify::body($pdo, $fm, 3)['html'], 'reading again'));
[$sys] = Mailboxes::connect($pdo, 1, 'system', ['provider' => 'cpanel', 'address' => 'support@traversence.com', 'password' => 'p']);
$t('one of our mailboxes: read by its overseers, at once', $one("SELECT status FROM comms_mailboxes WHERE id = $sys") === 'active' && (bool) array_filter(Mailboxes::forUser($pdo, 2), fn ($b) => (int) $b['id'] === $sys) && !array_filter(Mailboxes::forUser($pdo, 3), fn ($b) => (int) $b['id'] === $sys));
Mailboxes::store($pdo, $sys, 'INBOX', 'r1', ['to' => [['addr' => 'requests+r12-0123456789abcdef@traversence.com', 'name' => '']], 'from' => ['addr' => 'owner@diner.test', 'name' => ''], 'subject' => 'Re: A visitor asked', 'text' => 'Yes', 'html' => ''], false);
$t('a reply to a listing request (requests+r…@) goes to the visitor\'s thread', ListingMessages::$replies === ['requests+r12-0123456789abcdef@traversence.com']);
Mailboxes::store($pdo, $sys, 'INBOX', 'r2', ['to' => [['addr' => 'requests@traversence.com', 'name' => '']], 'from' => ['addr' => 'mailer-daemon@mx.test', 'name' => ''], 'subject' => 'Undelivered', 'text' => "Your message from requests@traversence.com to gone@diner.test failed", 'html' => ''], false);
$t('…a bounce of one stops email to that address', ListingMessages::$bounced === ['gone@diner.test']);
[$gok] = Mailboxes::gmailGate($pdo);
$t('Sign in with Google waits until a year\'s revenue covers its assessment', $gok === false);
StagingGate::$here = true;
$err = ''; try { Mailboxes::connect($pdo, 3, 'staff', ['provider' => 'imap', 'address' => 'x@yahoo.com', 'password' => 'p']); } catch (RuntimeException $x) { $err = $x->getMessage(); }
$t('staging never connects to a mailbox', str_contains($err, 'Staging'));
StagingGate::$here = false;
Mailboxes::disconnect($pdo, 3, $bid);
$t('Disconnect deletes our copy and the sealed password', (int) $one("SELECT COUNT(*) FROM comms_mail WHERE mailbox_id = $bid") === 0 && (int) $one("SELECT COUNT(*) FROM comms_mailboxes WHERE id = $bid") === 0);

// ---- SES receiving
require $app . '/lib/SesInbound.php';
$pdo->exec("INSERT INTO comms_mailboxes (owner_type, owner_id, label, address, provider, status, created_at, updated_at) VALUES ('system', 0, 'hello', 'hello@in.traversence.com', 'ses', 'active', NOW(), NOW())");
$sesId = (int) $pdo->lastInsertId();
$said = SesInbound::received($pdo, ['notificationType' => 'Received', 'mail' => ['messageId' => 'ses-1', 'destination' => ['hello@in.traversence.com']], 'receipt' => ['recipients' => ['hello@in.traversence.com'], 'spamVerdict' => ['status' => 'PASS']],
    'content' => base64_encode($raw('c1@x', 'cara@x.test', 'Hi there'))]);
$t('mail received by Amazon SES lands in its mailbox, counted on the route', $said === 'stored' && (int) $one("SELECT COUNT(*) FROM comms_mail WHERE mailbox_id = $sesId") === 1 && (int) $one("SELECT COUNT(*) FROM comms_route WHERE via = 'ses-in'") === 1);
SesInbound::received($pdo, ['notificationType' => 'Received', 'mail' => ['messageId' => 'ses-2'], 'receipt' => ['recipients' => ['hello@in.traversence.com'], 'spamVerdict' => ['status' => 'FAIL']], 'content' => base64_encode($raw('c2@x', 'spam@x.test', 'WIN'))]);
$t('…spam SES flags goes to Junk', $one("SELECT folder FROM comms_mail WHERE uid = 'ses-2'") === 'Junk');

// ---- the Sent view
$pdo->exec("INSERT INTO comms_route (message_id, via, stage, detail, provider_id, happened_at, sender_key, addr_hash, subject, mail_kind) VALUES (0, 'email', 'failed', 'Bluehost refused', '', NOW(), 'orders', 'h', 'Work order #4', 'orders')");
$sent = CommsSetup::sent($pdo, 'Work order');
$t('the Sent view reads the one route: a Bluehost send with its sender, subject and failure', count($sent) === 1 && $sent[0]['sender_key'] === 'orders' && $sent[0]['stage'] === 'failed');
$t('…and a sender\'s last failure shows on its card', CommsSetup::lastOf($pdo, 'orders')['failed'] !== null);

@unlink($env);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
