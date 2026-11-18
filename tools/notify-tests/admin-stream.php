<?php
// decisions/0172's admin Pulse stream, after the gap review (Jason, 2026-11-18): the staff member's own dispatcher messages
// and the mail of the mailboxes they read as rows (tv_pulse_extra), an email's own icons (forward, block the sender, report
// phishing, make it a case, the sender to the Address Book) and ⊕ A task, through /admin/api/pulse.php, on a real MySQL or
// MariaDB with the live libraries; stand-ins only for what reaches outside (mail, push) or the session, and for Pulse.php
// (the Control Board session's: its items come from the database tables this test doesn't build).
//   Run: mariadb -e 'CREATE DATABASE tvadm'; php admin-stream.php <a copy of the site: app/ and website/, the release laid over>
//   (app/migrations needs 2027-03-20_comms_engine.sql beside the release's 2027-03-21_mail_blocks.sql: the live site deleted it once run)
//   The dir is copied to a scratch folder first, and the stand-ins replace Database, Mailer, SesMail, WebPush, StagingGate,
//   Pulse, Workspaces, StaffLogin and IntakeStager there.
declare(strict_types=1);
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src . '/app/lib') || !is_dir($src . '/website')) { fwrite(STDERR, "usage: php admin-stream.php <site dir with app/ and website/>\n"); exit(2); }
$root = sys_get_temp_dir() . '/tv-adm-' . getmypid();
exec('mkdir -p ' . escapeshellarg($root) . ' && cp -r ' . escapeshellarg($src . '/app') . ' ' . escapeshellarg($src . '/website') . ' ' . escapeshellarg($root), $o, $rc);
if ($rc !== 0) { fwrite(STDERR, "couldn't copy the site dir\n"); exit(2); }
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg($root)));
$app = $root . '/app';

// ---- stand-ins --------------------------------------------------------------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvadm;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
}
final class Mailer { public static array $sent = []; public static bool $routed = false;
    public static function send(string $to, string $subject, string $text, ?string $name = null, string $as = '', string $html = '', string $replyTo = ''): void { self::$sent[] = compact('to', 'subject'); }
    public static function failed(...$a): void {} }
final class SesMail { public static function mode(): string { return 'live'; } public static function ready(PDO $p): bool { return false; } public static function hash(string $e): string { return hash('sha256', $e); } public static function suppressed(...$a): bool { return false; } }
final class StagingGate { public static function here(): bool { return true; } public static function hold(...$a): bool { return true; } }
final class Pulse {
    public const SOURCES = ['support' => ['Support', 'contact', 'listings', 'ops'], 'system' => ['System', 'settings', 'data', 'eng'], 'team' => ['Team', 'users', 'admin', '']];
    public const SEVERITY = ['critical' => 'Critical', 'urgent' => 'Urgent', 'alert' => 'Alert', 'update' => 'Notice', 'event' => 'General', 'special' => 'Special'];
    public const FROM_LEVEL = ['critical' => 'critical', 'urgent' => 'urgent', 'alert' => 'alert', 'notice' => 'update', 'general' => 'event', 'special' => 'special'];
    public static array $items = [];
    public static function ready(PDO $p): bool { return true; }
    public static function items(PDO $p, ?array $s, int $u): array { return self::$items; }
    public static function playbook(array $i): array { return $i['steps'] ?? []; }
    public static function explain(PDO $p, array $i): array { return []; }
}
final class Workspaces { public const DEPARTMENTS = ['eng' => ['Engineering']]; public static function levelOf(?array $s): array { return ['founder', '']; } public static function allowsArea(...$a): bool { return true; } }
final class StaffLogin { public static function inSession(PDO $p): ?array { return null; } }
final class IntakeStager { public static function canUseIntake(PDO $p, array $m): bool { return true; } }
final class Auth { public static int $id = 2; public static function requireAuth(): array { return ['id' => self::$id]; } public static function currentUser(): ?array { return ['id' => self::$id]; } public static function currentUserId(): ?int { return self::$id; } }
final class Answer extends Exception { public array $j; public function __construct(array $j) { $this->j = $j; parent::__construct('answer'); } }
final class Response { public static array $in = [];
    public static function json(array $j, int $c = 200): void { throw new Answer($j); }
    public static function error(string $m, int $c = 400, string $code = ''): void { throw new Answer(['ok' => false, 'error' => ['message' => $m, 'code' => $code]]); }
    public static function jsonBody(): array { return self::$in; } }
function tv_icon(string $n, string $c = ''): string { return '<svg data-i="' . $n . '"></svg>'; }
function tv_admin_embed_url(string $u): string { return $u; }
foreach (['Database', 'Mailer', 'SesMail', 'StagingGate', 'Pulse', 'Workspaces', 'StaffLogin'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// test stand-in\n");
@mkdir($app . '/lib/crawler', 0777, true); file_put_contents($app . '/lib/crawler/IntakeStager.php', "<?php\n// test stand-in\n");
file_put_contents($app . '/ui/app-shell.php', "<?php\n// test stand-in\n");
file_put_contents($app . '/bootstrap.php', "<?php\n// test stand-in\n");
$_SESSION = ['csrf_token' => 'tok'];

// ---- the database ------------------------------------------------------------------------------------------------------
$pdo = Database::connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec("DROP TABLE `$t`");
$sql = <<<'SQL'
CREATE TABLE comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL, UNIQUE KEY comms_once (to_user, kind, thing), KEY comms_thread (to_user, kind, thread));
CREATE TABLE comms_route (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, via VARCHAR(12) NOT NULL, stage VARCHAR(12) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', provider_id VARCHAR(120) NOT NULL DEFAULT '', happened_at DATETIME NOT NULL, sender_key VARCHAR(30) NULL, addr_hash CHAR(64) NULL, subject VARCHAR(200) NULL, mail_kind VARCHAR(30) NULL);
CREATE TABLE comms_prefs (user_id INT UNSIGNED NOT NULL, kind VARCHAR(48) NOT NULL, via VARCHAR(12) NOT NULL, is_on TINYINT(1) NOT NULL DEFAULT 1, changed_at DATETIME NOT NULL, PRIMARY KEY (user_id, kind, via));
CREATE TABLE comms_quiet (user_id INT UNSIGNED NOT NULL PRIMARY KEY, quiet_from SMALLINT UNSIGNED NOT NULL, quiet_to SMALLINT UNSIGNED NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', changed_at DATETIME NOT NULL);
CREATE TABLE push_subs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, endpoint VARCHAR(600) NOT NULL, endpoint_hash CHAR(64) NOT NULL, p256dh VARCHAR(200) NOT NULL, auth_key VARCHAR(64) NOT NULL, device VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, last_ok_at DATETIME NULL, failed_at DATETIME NULL, fails INT NOT NULL DEFAULT 0);
CREATE TABLE notices_sent (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, kind VARCHAR(40), thing VARCHAR(80), title VARCHAR(200), body VARCHAR(400), url VARCHAR(255), created_at DATETIME, read_at DATETIME NULL, UNIQUE KEY (user_id, kind, thing));
CREATE TABLE notice_prefs (user_id INT, kind VARCHAR(40), off_since DATETIME, PRIMARY KEY (user_id, kind));
CREATE TABLE notice_delivery (user_id INT PRIMARY KEY, how VARCHAR(10), digest_sent_at DATETIME NULL);
CREATE TABLE email_suppress (addr_hash CHAR(64), scope VARCHAR(40), reason VARCHAR(20), created_at DATETIME, PRIMARY KEY (addr_hash, scope));
CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_suspended TINYINT DEFAULT 0, created_at DATETIME NULL);
CREATE TABLE user_settings (user_id INT, no_marketing TINYINT DEFAULT 0, message_policy VARCHAR(20) NULL, profile_private TINYINT DEFAULT 0);
CREATE TABLE user_blocks (user_id INT, blocked_id INT);
CREATE TABLE content_comments (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10), ref VARCHAR(40), user_id INT, body TEXT, status VARCHAR(10) NOT NULL DEFAULT 'visible', created_at DATETIME, parent_id INT NULL);
CREATE TABLE people_connections (id INT AUTO_INCREMENT PRIMARY KEY, requester_id INT, addressee_id INT, status VARCHAR(10) NOT NULL DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, responded_at DATETIME NULL);
CREATE TABLE staff_accounts (user_id INT PRIMARY KEY, staff_id VARCHAR(40), name VARCHAR(100), email VARCHAR(190) DEFAULT '', status VARCHAR(10) DEFAULT 'active', role VARCHAR(20) DEFAULT 'staff');
SQL;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) $pdo->exec($q);
// the release's own tables, from its migrations (each split on ';' as DbUpdates does)
foreach (glob($app . '/migrations/2027-03-2*_{comms_engine,mail_blocks}.sql', GLOB_BRACE) ?: [] as $f) {
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', (string) file_get_contents($f))))) as $q) { try { $pdo->exec($q); } catch (Throwable $x) { if (!str_contains($x->getMessage(), 'already exists')) fwrite(STDERR, basename($f) . ': ' . $x->getMessage() . "\n"); } }
}
$now = date('Y-m-d H:i:s');
$pdo->exec("INSERT INTO users (id, email, display_name) VALUES (2,'mo@traversence.com','Mo'),(3,'ann@traversence.com','Ann'),(4,'bo@traversence.com','Bo'),(9,'maria@member.test','Maria B')");
$pdo->exec("INSERT INTO staff_accounts (user_id, staff_id, name, email) VALUES (2,'mo','Mo','mo@traversence.com'),(3,'ann','Ann','ann@traversence.com'),(4,'bo','Bo','bo@traversence.com')");
$pdo->exec("INSERT INTO comms_mailboxes (id, owner_type, owner_id, address, provider, status, created_at, updated_at) VALUES (1,'system',0,'support@traversence.com','cpanel','active','$now','$now')");
$pdo->exec("INSERT INTO comms_overseers (mailbox, user_id, added_at) VALUES ('support@traversence.com',2,'$now'),('support@traversence.com',4,'$now')");
$mail = fn (int $id, string $from, string $subj, int $ago) => $pdo->exec("INSERT INTO comms_mail (id, mailbox_id, folder, uid, msg_id, thread_key, direction, from_addr, from_name, subject, sent_at, snippet, body_text, seen, flagged, attach, created_at)
    VALUES ($id, 1, 'INBOX', '$id', '<m$id@x>', 't$id', 'in', '$from', '', '$subj', DATE_SUB(NOW(), INTERVAL $ago HOUR), 'Hello there', 'Hello there', 0, 0, '[]', NOW())");
$mail(11, 'maria@member.test', 'Is the trading post open Sunday?', 1);
$mail(12, 'win@prize.example', 'You won a prize, click here', 2);
$pdo->exec("INSERT INTO comms_messages (kind, lvl, sender_name, to_user, thing, title, body, url, created_at, updated_at) VALUES
    ('mail.failure','alert','Mailboxes',2,'mbfail:1:x','support@ refused its password','Reconnect it.','/admin/config/communications','$now','$now'),
    ('case.assigned','notice','Ann',2,'task:x','Task: call Joe','A task a teammate gave you.','/admin/dashboard/stream','$now','$now'),
    ('case.assigned','notice','Ann',3,'task:y','Task: for Ann only','','/','$now','$now')");

require $app . '/lib/Notify.php';
require $app . '/lib/PulseMarks.php';
require $app . '/lib/Mailboxes.php';
require $app . '/ui/pulse-ui.php';

$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$one = fn (string $s) => $pdo->query($s)->fetchColumn();
$api = function (array $in, int $as = 2) use ($root): array {
    Auth::$id = $as; Response::$in = $in; $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['HTTP_X_CSRF_TOKEN'] = 'tok';
    try { (function () use ($root) { require $root . '/website/admin/api/pulse.php'; })(); } catch (Answer $a) { return $a->j; }
    return ['ok' => false, 'error' => ['message' => 'no answer']];
};
$keys = fn (int $u) => array_column(tv_pulse_extra($pdo, $u), 'key');

echo "The staff member's own messages as rows (G6)\n";
$x = tv_pulse_extra($pdo, 2);
$comms = array_values(array_filter($x, fn ($r) => $r['kind'] === 'comms'));
$t('their two dispatcher messages are rows', count($comms) === 2, array_column($comms, 'title'));
$t('…not someone else\'s', !in_array('Task: for Ann only', array_column($comms, 'title'), true));
$t('a notice becomes Pulse\'s Notice level (update), an alert stays Alert', in_array('update', array_column($comms, 'severity'), true) && in_array('alert', array_column($comms, 'severity'), true));
$t('each names its sender and is keyed comms:<id>', $comms[0]['from'] !== '' && str_starts_with($comms[0]['key'], 'comms:'));

echo "The mail of the mailboxes they read as rows (G7)\n";
$mails = array_values(array_filter($x, fn ($r) => $r['kind'] === 'mail'));
$t('an overseer of support@ sees its two conversations', count($mails) === 2, array_column($mails, 'title'));
$t('…labelled with the mailbox, unread, keyed mail:<id>', $mails[0]['mailbox'] === 'support@traversence.com' && $mails[0]['unread'] && str_starts_with($mails[0]['key'], 'mail:'));
$t('someone who doesn\'t read support@ sees none of it', !array_filter(tv_pulse_extra($pdo, 3), fn ($r) => $r['kind'] === 'mail'));
$html = tv_pulse_board($pdo, ['name' => 'Mo'], 2);
$t('the board draws them as rows with their kind', substr_count($html, 'data-kind="mail"') === 2 && substr_count($html, 'data-kind="comms"') === 2);
$t('the Mail chip shows, counting what\'s unread', str_contains($html, 'data-chip="mail"') && preg_match('/Mail \(<b>2<\/b>\)/', $html) === 1);
$t('a message from the site can\'t be reported: 🛡 greyed with its reason', str_contains($html, 'aria-disabled="true"') && str_contains($html, 't be reported: a message from the site'));

echo "An email's own icons\n";
$j = $api(['do' => 'mail_block', 'item' => 'mail:12']);
$t('🛡 Block the sender answers', $j['ok'] && str_contains($j['message'], 'win@prize.example'), $j);
$t('…their mail leaves this person\'s stream', !in_array('mail:12', $keys(2), true) && in_array('mail:11', $keys(2), true));
$t('…but not another overseer\'s (a block is the person\'s own)', in_array('mail:12', $keys(4), true));
$t('…and the sender isn\'t told (nothing sent to them)', !array_filter(Mailer::$sent, fn ($m) => str_contains($m['to'], 'prize.example')));
$j = $api(['do' => 'mail_phish', 'item' => 'mail:12'], 4);
$t('🛡 Report phishing moves it to Junk', $j['ok'] && $one('SELECT folder FROM comms_mail WHERE id = 12') === 'Junk', $j);
$t('…and tells our team (support@\'s other overseers), not the reporter', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing = 'phish:12' AND to_user = 2") === 1 && !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing = 'phish:12' AND to_user = 4"));
$j = $api(['do' => 'mail_contact', 'item' => 'mail:12'], 2);
$t('⊕ The sender to the Address Book: someone not on Traversence is said plainly', !$j['ok'] && str_contains($j['error']['message'], 'isn\'t on Traversence'), $j);
$j = $api(['do' => 'mail_contact', 'item' => 'mail:11'], 2);
$t('…someone who is: a connection request', $j['ok'] && (int) $one('SELECT COUNT(*) FROM people_connections WHERE requester_id = 2 AND addressee_id = 9') === 1, $j);
$j = $api(['do' => 'mail_case', 'item' => 'mail:11'], 2);
$t('⊕ Make it a case: a case message in their Pulse', $j['ok'] && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing = 'mailcase:11' AND to_user = 2") === 1, $j);
$j = $api(['do' => 'mail_block', 'item' => 'mail:11'], 3);
$t('nobody acts on mail they don\'t read', !$j['ok'] && str_contains($j['error']['message'], 'isn\'t one you read'), $j);
$j = $api(['do' => 'mail_forward', 'item' => 'mail:11', 'to' => 'x@example.com', 'note' => 'FYI']);
$t('↗ forward goes from the mailbox, so staging refuses it (never sends from a mailbox)', !$j['ok'] && str_contains($j['error']['message'], 'Staging never sends'), $j);

echo "⊕ A task (G9)\n";
$j = $api(['do' => 'team']);
$t('the team list leaves out the asker', $j['ok'] && !in_array(2, array_column($j['team'], 'id'), true) && in_array(3, array_column($j['team'], 'id'), true));
$j = $api(['do' => 'task', 'key' => 'order:9', 'item' => 'pulse:order:9', 'to' => 3, 'due' => 'tomorrow', 'tz' => 'America/Phoenix', 'what' => 'Ship order 9']);
$id = (int) $one("SELECT id FROM comms_messages WHERE to_user = 3 AND title = 'Task: Ship order 9'");
$t('a task for a teammate arrives in their Pulse', $j['ok'] && $id > 0, $j);
$t('…flagged Follow up tomorrow, so it comes back to them then', $one("SELECT flag_state FROM pulse_flags WHERE user_id = 3 AND item = 'comms:$id'") === 'open' && $one("SELECT due_at FROM pulse_flags WHERE user_id = 3 AND item = 'comms:$id'") !== null);
$t('…linking back to the case', str_contains((string) $one("SELECT url FROM comms_messages WHERE id = $id"), 'case=order%3A9'));
$j = $api(['do' => 'task', 'key' => 'x', 'item' => 'x', 'to' => 0, 'due' => 'today', 'what' => 'Mine']);
$t('a task for yourself (to: 0)', $j['ok'] && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE to_user = 2 AND title = 'Task: Mine'") === 1, $j);
$j = $api(['do' => 'task', 'key' => 'x', 'to' => 9, 'due' => 'today', 'what' => 'Nope']);
$t('only to our team', !$j['ok'] && str_contains($j['error']['message'], 'isn\'t on our team'), $j);
$j = $api(['do' => 'task', 'key' => 'x', 'to' => 3, 'due' => 'someday', 'what' => 'x']);
$t('only the four days offered', !$j['ok']);

echo "Grouped alarms (the Control Board's shape, 0171 §8 note)\n";
Pulse::$items = [['key' => 'alarms:engine:warning:held', 'source' => 'system', 'severity' => 'alert', 'title' => '3 engine source feeds held', 'why' => 'Overpass; W5; W6', 'when' => $now, 'state' => 'open', 'dept' => 'eng',
    'mine' => false, 'esc_to_me' => false, 'esc' => 0, 'who' => '', 'over' => false, 'sla_due' => null, 'action' => 'Open', 'url' => '/admin/config/control#cb-alarms',
    'children' => [['key' => 'alarm:1', 'title' => 'Overpass held', 'severity' => 'alert', 'why' => 'A 406.', 'url' => '/admin/config/control#a1', 'steps' => []], ['key' => 'alarm:2', 'title' => 'W5 held', 'severity' => 'alert', 'why' => '', 'url' => '', 'steps' => []], ['key' => 'alarm:3', 'title' => 'W6 held', 'severity' => 'alert', 'why' => '', 'url' => '', 'steps' => []]]]];
$html = tv_pulse_board($pdo, ['name' => 'Mo'], 2);
$t('one row for the group, with its count', substr_count($html, 'data-group="3"') === 1);
$t('its three alarms as rows under it, each its own key', substr_count($html, 'data-parent="alarms:engine:warning:held"') === 3 && str_contains($html, 'data-key="alarm:2"'));
$t('…each with a case of its own in the page\'s data', (bool) preg_match('/"alarm:3":\{/', $html));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
