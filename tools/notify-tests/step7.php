<?php
// decisions/0172 step 7's tests: replies on comments and the comment notice's own buttons, booking reminders and
// changes, group posts, join requests and reports, on a real MySQL or MariaDB, with the live libraries and stand-ins only
// for what reaches outside (mail, SES) or the session.
//   Run: mariadb -e 'CREATE DATABASE tvstep7'; php step7.php <a copy of app/ with the release laid over it>
//   The app dir is copied to a scratch folder first (the stand-ins below replace Database, Mailer, SesMail, Standing,
//   AgeGate and Tags there), and if Notify::KINDS doesn't have step 7's kinds yet they're added to that copy only.
declare(strict_types=1);
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php step7.php <app dir>\n"); exit(2); }
$app = sys_get_temp_dir() . '/tv-step7-' . getmypid();
exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($app), $o, $rc);
if ($rc !== 0) { fwrite(STDERR, "couldn't copy the app dir\n"); exit(2); }
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg($app)));

// ---- stand-ins -------------------------------------------------------------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvstep7;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
}
final class Mailer { public static array $sent = []; public static bool $routed = false;
    public static function send(string $to, string $subject, string $text, ?string $name = null, string $as = '', string $html = '', string $replyTo = ''): void { self::$sent[] = compact('to', 'subject', 'text'); } }
final class SesMail { public static array $sent = [];
    public static function mode(): string { return 'live'; } public static function ready(PDO $p): bool { return true; }
    public static function send(PDO $p, array $m): void { self::$sent[] = $m; }
    public static function hash(string $e): string { return hash('sha256', mb_strtolower(trim($e))); }
    public static function suppressed(PDO $p, string $h, string $s): bool { return false; } }
final class Standing { public static function guard(PDO $p, int $u): void {} public static function contentRemoved(...$a): void {} public static function contentRestored(...$a): void {} }
final class AgeGate { public static array $ok = [];
    public static function require(PDO $p, int $u): void { if (empty(self::$ok[$u])) throw new InvalidArgumentException('Comments are for people 18 or older: confirm it first.'); }
    public static function confirmed(PDO $p, int $u): bool { return !empty(self::$ok[$u]); }
    public static function confirm(PDO $p, int $u): void { self::$ok[$u] = true; } }
final class Tags { public static function clean(PDO $p, string $b): array { return [$b, []]; } public static function record(...$a): void {} public static function plain(string $b): string { return (string) preg_replace('/@\[([^\]]+)\]\([^)]*\)/', '@$1', $b); } }
final class Auth { public static ?int $id = null; public static function currentUserId(): ?int { return self::$id; } public static function currentUser(): ?array { return self::$id ? ['id' => self::$id] : null; } }
foreach (['Database', 'Mailer', 'SesMail', 'Standing', 'AgeGate', 'Tags'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// test stand-in\n");

// step 7's kinds, in this copy only, if Notify doesn't have them yet
$nf = file_get_contents($app . '/lib/Notify.php');
$kinds = [
    'comment.reply'   => ['A reply to your comment', 'Someone answered a comment you wrote.', 'general', 'member', 'Comments', false, true, 'system', ''],
    'content.comment' => ['A comment on something you made', 'Someone commented on an experience you wrote.', 'general', 'member', 'Comments', false, true, 'system', ''],
    'booking.tomorrow'=> ['A booking tomorrow', 'Your stay or rental starts tomorrow.', 'notice', 'member', 'Bookings', true, true, 'system', ''],
    'booking.soon'    => ['A booking in 2 hours', 'Check-in or pick-up is in two hours.', 'urgent', 'member', 'Bookings', true, true, 'system', ''],
    'booking.changed' => ['A booking changed', 'The business changed or cancelled your booking.', 'urgent', 'member', 'Bookings', true, true, 'system', ''],
    'biz.booking'     => ['A booking changed', 'A customer changed or cancelled a booking.', 'urgent', 'business', 'Your business', true, true, 'system', 'orders'],
    'group.post'      => ['A new post in your group', 'By your link settings.', 'general', 'member', 'Groups', false, true, 'system', ''],
    'group.join'      => ['A request to join your group', 'For moderators.', 'alert', 'member', 'Groups', false, true, 'system', ''],
    'group.report'    => ['A post reported in your group', 'For whoever handles reports.', 'alert', 'member', 'Groups', false, true, 'system', ''],
    'group.mention'   => ['Mentioned in a group', 'Someone mentioned you.', 'alert', 'member', 'Groups', false, true, 'system', ''],
];
$add = '';
foreach ($kinds as $k => $d) if (!str_contains($nf, "'" . $k . "'")) $add .= '        ' . var_export($k, true) . ' => ' . str_replace(["\n", '  '], '', var_export($d, true)) . ",\n";
if ($add !== '') { $nf = preg_replace('/(public const KINDS = \[\n)/', '$1' . str_replace('\\', '\\\\', $add), $nf, 1); file_put_contents($app . '/lib/Notify.php', $nf); }

$pdo = Database::connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec("DROP TABLE `$t`");
$sql = <<<'SQL'
CREATE TABLE comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL, UNIQUE KEY comms_once (to_user, kind, thing), KEY comms_thread (to_user, kind, thread));
CREATE TABLE comms_route (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, via VARCHAR(12) NOT NULL, stage VARCHAR(12) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', provider_id VARCHAR(120) NOT NULL DEFAULT '', happened_at DATETIME NOT NULL, sender_key VARCHAR(30) NULL, addr_hash CHAR(64) NULL, subject VARCHAR(200) NULL, mail_kind VARCHAR(30) NULL);
CREATE TABLE comms_prefs (user_id INT UNSIGNED NOT NULL, kind VARCHAR(48) NOT NULL, via VARCHAR(12) NOT NULL, is_on TINYINT(1) NOT NULL DEFAULT 1, changed_at DATETIME NOT NULL, PRIMARY KEY (user_id, kind, via));
CREATE TABLE comms_quiet (user_id INT UNSIGNED NOT NULL PRIMARY KEY, quiet_from SMALLINT UNSIGNED NOT NULL, quiet_to SMALLINT UNSIGNED NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', changed_at DATETIME NOT NULL);
CREATE TABLE comms_controls (ckey VARCHAR(60) NOT NULL PRIMARY KEY, paused TINYINT NOT NULL DEFAULT 0, why VARCHAR(255) NOT NULL DEFAULT '', by_user INT NULL, changed_at DATETIME NOT NULL);
CREATE TABLE push_subs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, endpoint VARCHAR(600) NOT NULL, endpoint_hash CHAR(64) NOT NULL, p256dh VARCHAR(200) NOT NULL, auth_key VARCHAR(64) NOT NULL, device VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, last_ok_at DATETIME NULL, failed_at DATETIME NULL, fails INT NOT NULL DEFAULT 0);
CREATE TABLE notices_sent (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, kind VARCHAR(40), thing VARCHAR(80), title VARCHAR(200), body VARCHAR(400), url VARCHAR(255), created_at DATETIME, read_at DATETIME NULL, UNIQUE KEY (user_id, kind, thing));
CREATE TABLE notice_prefs (user_id INT, kind VARCHAR(40), off_since DATETIME, PRIMARY KEY (user_id, kind));
CREATE TABLE notice_delivery (user_id INT PRIMARY KEY, how VARCHAR(10), digest_sent_at DATETIME NULL);
CREATE TABLE email_suppress (addr_hash CHAR(64), scope VARCHAR(40), reason VARCHAR(20), created_at DATETIME, PRIMARY KEY (addr_hash, scope));
CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_suspended TINYINT DEFAULT 0, created_at DATETIME NULL);
CREATE TABLE user_settings (user_id INT, no_marketing TINYINT DEFAULT 0, message_policy VARCHAR(20) NULL, profile_private TINYINT DEFAULT 0);
CREATE TABLE user_blocks (user_id INT, blocked_id INT);
CREATE TABLE user_links (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, target_type VARCHAR(20), target_id VARCHAR(40), label VARCHAR(255), url VARCHAR(255), muted TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(100), status VARCHAR(20) DEFAULT 'managed', owner_user_id INT NULL, is_suppressed TINYINT DEFAULT 0);
CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20));
CREATE TABLE listing_memberships (listing_id INT, user_id INT, role VARCHAR(20), status VARCHAR(10), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE role_perms (role VARCHAR(20), perm VARCHAR(20), level VARCHAR(10));
CREATE TABLE role_grants (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, role VARCHAR(20), side VARCHAR(10), scope_type VARCHAR(10), scope_id VARCHAR(20), on_behalf_of INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL);
CREATE TABLE support_grants (id INT AUTO_INCREMENT PRIMARY KEY, listing_id INT, user_id INT, role VARCHAR(20), revoked_at DATETIME NULL);
CREATE TABLE content_comments (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10), ref VARCHAR(40), user_id INT, body TEXT, status VARCHAR(10) NOT NULL DEFAULT 'visible', created_at DATETIME, parent_id INT NULL, hidden_by INT NULL, hidden_at DATETIME NULL);
CREATE TABLE content_likes (kind VARCHAR(10), ref VARCHAR(40), user_id INT, created_at DATETIME);
CREATE TABLE content_reports (id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT, kind VARCHAR(20), target_id INT, reason VARCHAR(20), note VARCHAR(500), status VARCHAR(10) NOT NULL DEFAULT 'open', handled_by INT NULL, handled_at DATETIME NULL, created_at DATETIME, route VARCHAR(10) NOT NULL DEFAULT 'staff');
CREATE TABLE experiences (id INT PRIMARY KEY, author_id INT, title VARCHAR(100), status VARCHAR(20) DEFAULT 'published');
CREATE TABLE business_messages (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, user_id INT, from_business TINYINT, kind VARCHAR(20), body TEXT, big TINYINT, sent_by INT NULL, visible_at DATETIME, created_at DATETIME, msg_type VARCHAR(20) NULL, answered TINYINT DEFAULT 0);
CREATE TABLE business_message_reads (user_id INT, entity_id INT, person_read_at DATETIME NULL, biz_read_at DATETIME NULL);
CREATE TABLE market_items (id INT PRIMARY KEY, entity_id INT, title VARCHAR(100), kind VARCHAR(20), kind_data TEXT, status VARCHAR(20) DEFAULT 'published', track_stock TINYINT DEFAULT 0, taxable TINYINT DEFAULT 0, stock INT DEFAULT 0);
CREATE TABLE market_orders (id INT PRIMARY KEY, token CHAR(32), entity_id INT, buyer_id INT, email VARCHAR(255), name VARCHAR(120), order_lines TEXT, ship_name VARCHAR(120) DEFAULT '', ship_address VARCHAR(400) DEFAULT '', subtotal_cents INT DEFAULT 0, ship_cents INT DEFAULT 0, tax_cents INT DEFAULT 0, total_cents INT DEFAULT 0, method VARCHAR(10) DEFAULT 'zelle', provider VARCHAR(10) DEFAULT '', provider_ref VARCHAR(120) DEFAULT '', env VARCHAR(10) DEFAULT 'live', status VARCHAR(10), hold_until DATETIME, note TEXT, created_at DATETIME, updated_at DATETIME, paid_at DATETIME NULL, biz_quote_id INT NULL, pay_now_cents INT NULL, pay_mode VARCHAR(20) NULL, sub_ref VARCHAR(120) NULL, policy_json TEXT NULL);
CREATE TABLE market_tickets (id INT); CREATE TABLE market_downloads (id INT);
CREATE TABLE market_gateways (entity_id INT, provider VARCHAR(10), status VARCHAR(12), env VARCHAR(10), account_id VARCHAR(60), expires_at DATETIME NULL, acct_checked_at DATETIME NULL);
CREATE TABLE market_checkout (entity_id INT PRIMARY KEY, settings TEXT, live TINYINT DEFAULT 1);
CREATE TABLE community_groups (id INT PRIMARY KEY, name VARCHAR(80), about TEXT, owner_id INT, join_mode VARCHAR(10), status VARCHAR(10) DEFAULT 'active', created_at DATETIME, owner_kind VARCHAR(10) DEFAULT 'person', owner_ref INT DEFAULT 0);
CREATE TABLE community_group_members (group_id INT, user_id INT, role VARCHAR(10), status VARCHAR(10), joined_at DATETIME, list_hidden TINYINT DEFAULT 0, charter_version INT NULL);
CREATE TABLE community_posts (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT, user_id INT, body TEXT, created_at DATETIME, removed_at DATETIME NULL, voice VARCHAR(10) DEFAULT 'person', as_listing INT NULL, held_at DATETIME NULL);
SQL;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) $pdo->exec($q);
$pdo->exec("INSERT INTO users (id, email, display_name) VALUES (1,'owner@biz.test','Olive Owner'),(2,'gen@biz.test','Gene General'),(3,'acc@biz.test','Ada Accountant'),(4,'maria@member.test','Maria B'),(9,'sam@member.test','Sam C'),(6,'jason@staff.test','Jason'),
    (30,'g30@t','Gia Owner'),(31,'g31@t','Mo Mod'),(32,'g32@t','Bea Member'),(33,'g33@t','Quiet Member'),(34,'g34@t','Blocker'),(35,'g35@t','Poster'),(36,'g36@t','Asker'),(37,'g37@t','Asker Two'),(38,'g38@t','Biz Asker'),(40,'g40@t','Sue Support'),(41,'a41@t','Author')");
$pdo->exec("INSERT INTO entities (id, name) VALUES (10,'Joe\\'s Diner'),(11,'Pine Cabins')");
$pdo->exec("INSERT INTO listing_access VALUES (10,1,'owner'),(11,1,'owner')");
$pdo->exec("INSERT INTO role_grants (user_id, role, side, scope_type, scope_id) VALUES (2,'general','org','entity','10'),(3,'accountant','org','entity','10'),(2,'general','org','entity','11'),(6,'owner','admin','platform','')");
$pdo->exec("INSERT INTO listing_memberships (listing_id, user_id, role, status) VALUES (10,40,'support','active')");
$pdo->exec("INSERT INTO experiences VALUES (5,41,'Paddle Lyman Lake','published')");

require $app . '/lib/Notify.php';
require $app . '/lib/Engage.php';
require $app . '/lib/CommentNotices.php';
require $app . '/lib/Bookings.php';
require $app . '/lib/MarketCheckout.php';
require $app . '/lib/GroupNotices.php';
require $app . '/lib/Reports.php';

$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$q = fn (string $s) => $pdo->query($s)->fetchAll(PDO::FETCH_ASSOC);
$one = fn (string $s) => $pdo->query($s)->fetchColumn();
$msg = fn (string $kind, int $to, string $like = '%') => ($r = $q("SELECT * FROM comms_messages WHERE kind = '$kind' AND to_user = $to AND thing LIKE '$like' ORDER BY id DESC LIMIT 1")) ? $r[0] : null;
$threw = function (callable $f): string { try { $f(); return ''; } catch (InvalidArgumentException $x) { return $x->getMessage(); } };
foreach ([1, 2, 3, 4, 9, 6, 40, 41] as $u) AgeGate::$ok[$u] = true;

echo "Comments\n";
$t('step 7\'s kinds are in Notify (or added to this copy for the test)', count(array_intersect(array_keys(Notify::kinds()), array_keys($kinds))) === count($kinds));
$c1 = Engage::comment($pdo, 4, 'listing', '10', 'Great pie, slow service.');
$to = array_map('intval', array_column($q("SELECT to_user FROM comms_messages WHERE kind = 'biz.comment' AND thing = 'comment:{$c1['id']}' ORDER BY to_user"), 'to_user'));
$t('a comment on a listing: the business\'s team hears (biz.comment, kept from step 2)', $to === [1, 2], $to);
$r1 = Engage::comment($pdo, 9, 'listing', '10', 'Agreed on the pie!', $c1['id']);
$t('a reply is stored under its comment', (int) $one("SELECT parent_id FROM content_comments WHERE id = {$r1['id']}") === $c1['id']);
$t('a reply tells the comment\'s writer (comment.reply)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4") === 1);
$t('…not the one who replied', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 9"));
$t('…and the business still hears, as a reply in its listing\'s comments (added to its unread comments message)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'biz.comment' AND thread = 'comments:10' AND title LIKE '%replied in the comments on%' AND times = 2") === 2, $q("SELECT to_user, title, times FROM comms_messages WHERE kind = 'biz.comment'"));
Engage::comment($pdo, 4, 'listing', '10', 'Thanks Sam', $c1['id']);
$t('replying in your own comment\'s thread tells no one of yourself', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4") === 1);
$r3 = Engage::comment($pdo, 9, 'listing', '10', 'Reply to a reply', $r1['id']);
$t('a reply to a reply joins its comment (one level)', (int) $one("SELECT parent_id FROM content_comments WHERE id = {$r3['id']}") === $c1['id']);
$t('…the writer hears once more, added to the unread one (thread), not a second row', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4") === 1 && (int) $one("SELECT times FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4") === 2);
$list = Engage::comments($pdo, 'listing', '10');
$t('the page\'s comments carry parent_id for replies', count(array_filter($list, fn ($c) => $c['parent_id'] === $c1['id'])) === 3, $list);
Engage::comment($pdo, 1, 'listing', '10', 'Sorry about the wait!', $c1['id']);
$list = Engage::comments($pdo, 'listing', '10');
$own = array_values(array_filter($list, fn ($c) => $c['user_id'] === 1))[0] ?? [];
$t('a team member\'s comment is named with the business', ($own['team'] ?? '') === 'Joe\'s Diner' && (array_values(array_filter($list, fn ($c) => $c['user_id'] === 9))[0]['team'] ?? 'x') === '', $own);
Engage::comment($pdo, 9, 'exp', '5', 'Loved this paddle.');
$t('a comment on an experience tells its writer (content.comment)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'content.comment' AND to_user = 41") === 1);
$pdo->exec("INSERT INTO user_blocks VALUES (4, 30)");
$pdo->exec("INSERT INTO users (id, email, display_name) VALUES (42,'b@t','Blocked Guy')"); AgeGate::$ok[42] = true; $pdo->exec("INSERT INTO user_blocks VALUES (4, 42)");
Engage::comment($pdo, 42, 'listing', '10', 'Hi Maria', $c1['id']);
$t('someone the writer blocked replying: the writer isn\'t told', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4 AND sender_id = 42") === 0 && (int) $one("SELECT times FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4") === 3);

// the biz.comment notice's own body and buttons
$mOwner = $msg('biz.comment', 1, 'comment:' . $c1['id']);
$b = CommentNotices::pulseBody($pdo, $mOwner, 1);
$t('biz.comment body shows the comment and the listing', str_contains($b['html'], 'Great pie, slow service.') && str_contains($b['html'], 'Joe&#039;s Diner'), $b['html']);
$t('…with Reply (a reply box to /api/comment-act.php)', str_contains($b['html'], 'data-msg-reply="/api/comment-act.php"') && str_contains($b['html'], '&quot;act&quot;') === false && str_contains($b['html'], '"act":"reply"'));
$t('…with Hide (confirmed first) for the Owner', (bool) preg_match('/<button[^>]*data-msg-act="\/api\/comment-act.php"[^>]*"act":"hide"[^>]*data-confirm=/', $b['html']), $b['html']);
$t('…and Report, the site\'s report reasons', str_contains($b['html'], '"act":"report"') && str_contains($b['html'], 'Spam or scam'));
$t('…its state, read live: shown, with its replies counted', str_starts_with($b['state'], 'Shown on the page') && str_contains($b['state'], 'replies'), $b['state']);
$mGen = $msg('biz.comment', 2, 'comment:' . $c1['id']);
$bg = CommentNotices::pulseBody($pdo, $mGen, 2);
$t('a General team member sees Hide greyed out, with why', str_contains($bg['html'], 'disabled') && str_contains($bg['html'], 'Owner and Managers hide comments'), $bg['html']);
$t('the hide endpoint refuses a General team member', $threw(fn () => CommentNotices::act($pdo, 2, ['act' => 'hide', 'comment' => $c1['id'], 'msg' => $mGen['id']])) !== '' && $one("SELECT status FROM content_comments WHERE id = {$c1['id']}") === 'visible');
$t('…and a member who isn\'t on the team', $threw(fn () => CommentNotices::act($pdo, 9, ['act' => 'hide', 'comment' => $c1['id']])) !== '');
[$said, $html] = CommentNotices::act($pdo, 1, ['act' => 'hide', 'comment' => $c1['id'], 'msg' => $mOwner['id']]);
$t('the Owner hides it: hidden, who and when kept', $one("SELECT status FROM content_comments WHERE id = {$c1['id']}") === 'hidden' && (int) $one("SELECT hidden_by FROM content_comments WHERE id = {$c1['id']}") === 1 && $one("SELECT hidden_at FROM content_comments WHERE id = {$c1['id']}") !== null);
$t('…the answer carries the body drawn again: "Hidden by you", with Show it again', str_contains($html, 'Hidden by you') && str_contains($html, '"act":"show"') && str_contains($said, 'Hidden'), $html);
$t('…to another team member it reads "Hidden by Olive Owner"', str_contains(CommentNotices::pulseBody($pdo, $mGen, 2)['state'], 'Hidden by Olive Owner'));
$t('…its replies leave the page with it', !array_filter(Engage::comments($pdo, 'listing', '10'), fn ($c) => $c['parent_id'] === $c1['id']) && Engage::commentCount($pdo, 'listing', '10') === 0);
[$said] = CommentNotices::act($pdo, 1, ['act' => 'show', 'comment' => $c1['id'], 'msg' => $mOwner['id']]);
$t('Show it again brings it back', $one("SELECT status FROM content_comments WHERE id = {$c1['id']}") === 'visible' && $one("SELECT hidden_by FROM content_comments WHERE id = {$c1['id']}") === null);
$t('a manager on the team (Team role) may hide too', Engage::canHide($pdo, 40, Engage::getComment($pdo, $c1['id']))[0] === false && ($pdo->exec("UPDATE listing_memberships SET role = 'manager' WHERE user_id = 40") !== false) && Engage::canHide($pdo, 40, Engage::getComment($pdo, $c1['id']))[0] === true);
[$said, $html] = CommentNotices::act($pdo, 1, ['act' => 'reply', 'comment' => $c1['id'], 'body' => 'We\'ve added staff on weekends.', 'msg' => $mOwner['id']]);
$t('Reply from the notice posts a public reply under the comment, as the person', (int) $one("SELECT COUNT(*) FROM content_comments WHERE user_id = 1 AND parent_id = {$c1['id']} AND body LIKE 'We%weekends.'") === 1 && str_contains($said, 'Replied'));
$t('…and its writer hears it', (int) $one("SELECT times FROM comms_messages WHERE kind = 'comment.reply' AND to_user = 4") === 4);
AgeGate::$ok[2] = false;
$t('a reply from someone not yet confirmed 18+ is refused with why', str_contains($threw(fn () => CommentNotices::act($pdo, 2, ['act' => 'reply', 'comment' => $c1['id'], 'body' => 'Hi'])), '18 or older'));
CommentNotices::act($pdo, 2, ['act' => 'reply', 'comment' => $c1['id'], 'body' => 'Hi from the team', 'age' => '1']);
$t('…and goes once they tick "I am 18 or older" in the box', AgeGate::$ok[2] === true && (int) $one("SELECT COUNT(*) FROM content_comments WHERE user_id = 2 AND parent_id = {$c1['id']}") === 1);
CommentNotices::act($pdo, 1, ['act' => 'report', 'comment' => $c1['id'], 'reason' => 'harassment', 'body' => 'Rude', 'msg' => $mOwner['id']]);
$t('Report files the site\'s report (content_comment) for our team', (int) $one("SELECT COUNT(*) FROM content_reports WHERE kind = 'content_comment' AND target_id = {$c1['id']} AND reporter_id = 1 AND reason = 'harassment'") === 1);
$t('…the body then says it\'s reported, and offers no second report', str_contains(($rb = CommentNotices::pulseBody($pdo, $mOwner, 1))['state'], 'you reported it') && !str_contains($rb['html'], '"act":"report"'));
Reports::decide($pdo, 6, 'content_comment', $c1['id'], 'remove');
$t('our team hides it after reports: who is kept', (int) $one("SELECT hidden_by FROM content_comments WHERE id = {$c1['id']}") === 6);
$t('…the Owner can\'t show it again, and is told why', str_contains($threw(fn () => CommentNotices::act($pdo, 1, ['act' => 'show', 'comment' => $c1['id']])), 'Our team hid it') && str_contains(CommentNotices::pulseBody($pdo, $mOwner, 1)['state'], 'Hidden by our team'));
$mRep = $msg('comment.reply', 4);
$rb = CommentNotices::pulseBody($pdo, $mRep, 4);
$t('comment.reply body shows my comment as "You", the reply, where; no Hide for a member', str_contains($rb['html'], '<b>You</b>') && !str_contains($rb['html'], '"act":"hide"'), $rb['html']);

echo "Bookings\n";
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$after = date('Y-m-d', strtotime('+3 days'));
$pdo->exec("INSERT INTO market_items VALUES (50, 11, 'Creek cabin', 'booking', '{\"checkin\":\"3 pm\",\"house_rules\":\"No pets\"}', 'published', 0, 0, 0), (51, 11, 'Log splitter', 'rental', '{}', 'published', 0, 0, 0)");
$line = fn (int $item, string $kind, string $from, string $to, int $n, int $g = 0) => json_encode([['item_id' => $item, 'product_id' => 0, 'title' => ($kind === 'booking' ? 'Creek cabin' : 'Log splitter') . ' (Dec 1 to Dec 3, 2 nights)', 'qty' => $n, 'unit_cents' => 10000, 'taxable' => 0, 'kind' => $kind, 'kx' => ['from' => $from, 'to' => $to, 'units' => 1] + ($g ? ['guests' => $g] : [])]]);
$ins = $pdo->prepare("INSERT INTO market_orders (id, token, entity_id, buyer_id, email, name, order_lines, total_cents, status, hold_until, note, created_at, updated_at, biz_quote_id) VALUES (?, ?, 11, ?, ?, ?, ?, 20000, ?, ?, '', ?, ?, ?)");
$old = date('Y-m-d H:i:s', strtotime('-3 days'));
$ins->execute([100, str_repeat('a', 32), 4, 'maria@member.test', 'Maria B', $line(50, 'booking', $tomorrow, $after, 2, 2), 'paid', $old, $old, $old, null]);
$ins->execute([101, str_repeat('b', 32), 9, 'sam@member.test', 'Sam C', $line(51, 'rental', $tomorrow, $after, 2), 'paid', $old, $old, $old, null]);
$ins->execute([102, str_repeat('c', 32), 99, 'gone@guest.test', 'Gone Guest', $line(50, 'booking', $tomorrow, $after, 2, 1), 'paid', $old, $old, $old, null]);
$start = strtotime($tomorrow . ' 3 pm');
$t('nothing is due at 8 am the day before', Bookings::remind($pdo, strtotime($tomorrow . ' 08:00') - 86400) === 0);
Bookings::remind($pdo, strtotime($tomorrow . ' 09:01') - 86400);
$t('a rental (a day, no hour): its 24 h reminder at 9 am the day before', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'booking.tomorrow' AND to_user = 9 AND thing = 'booking:101:24h'") === 1);
$t('…the stay (check-in 3 pm) not yet', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing LIKE 'booking:100:%'"));
Mailer::$sent = [];
$n = Bookings::remind($pdo, $start - 23 * 3600);
$t('24 h before check-in: the customer\'s reminder (booking.tomorrow)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'booking.tomorrow' AND to_user = 4 AND thing = 'booking:100:24h'") === 1);
$t('…a customer whose account is gone is emailed at the order\'s address, noted on the order', count(array_filter(Mailer::$sent, fn ($m) => $m['to'] === 'gone@guest.test')) === 1 && str_contains((string) $one('SELECT note FROM market_orders WHERE id = 102'), 'Reminder emailed (24 h)'), Mailer::$sent);
Mailer::$sent = [];
$t('the next run sends nothing again (each once)', Bookings::remind($pdo, $start - 22 * 3600) === 0 && !Mailer::$sent);
$n = Bookings::remind($pdo, $start - 110 * 60);
$t('2 h before check-in: booking.soon, Urgent', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'booking.soon' AND to_user = 4 AND thing = 'booking:100:2h' AND lvl = 'urgent'") === 1, $q("SELECT kind, thing, lvl FROM comms_messages WHERE thing LIKE 'booking:100%'"));
$t('…once', Bookings::remind($pdo, $start - 100 * 60) === 0);
$t('…no 2 h reminder for a rental (no hour)', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing = 'booking:101:2h'"));
$t('nothing after it has started', Bookings::remind($pdo, $start + 60) === 0);
$mb = $msg('booking.tomorrow', 4);
$bb = CommsBody::message($pdo, (int) $mb['id'], 4) ? Bookings::pulseBody($pdo, $mb, 4) : ['html' => '', 'state' => ''];
$t('booking body: the stay read live: check-in day and time, nights, guests, place, Your order', str_contains($bb['html'], 'Creek cabin') && str_contains($bb['html'], '3:00 PM') && str_contains($bb['html'], 'Pine Cabins') && str_contains($bb['html'], '<dt>Guests</dt><dd>2</dd>') && str_contains($bb['html'], 'Your order'), $bb['html']);
$t('…its state: booked, check-in day', str_starts_with($bb['state'], 'Booked · check-in'), $bb['state']);
$t('…someone not a party to it sees nothing of it', str_contains(Bookings::pulseBody($pdo, $mb, 9)['html'], 'between the customer and the business'));
// changed and cancelled: the other side
$ins->execute([103, str_repeat('d', 32), 4, 'maria@member.test', 'Maria B', $line(50, 'booking', $after, date('Y-m-d', strtotime('+5 days')), 2, 2), 'awaiting', date('Y-m-d H:i:s', strtotime('+2 days')), $old, $old, 7]);
$pdo->exec("INSERT INTO comms_messages (kind, lvl, to_user, thing, title, created_at, updated_at) VALUES ('booking.tomorrow', 'notice', 4, 'booking:103:24h', 'Your stay', NOW(), NOW())");
$m103 = $msg('booking.tomorrow', 4, 'booking:103:%');
$b103 = Bookings::pulseBody($pdo, $m103, 4);
$t('an unpaid booking\'s body carries Cancel it (confirmed first), its payment instructions', str_contains($b103['html'], '"act":"cancel"') && str_contains($b103['html'], 'data-confirm=') && str_starts_with($b103['state'], 'Waiting for payment'), $b103);
$t('the cancel endpoint refuses someone else', $threw(fn () => Bookings::act($pdo, 9, ['act' => 'cancel', 'order' => 103])) !== '');
[$said, $html] = Bookings::act($pdo, 4, ['act' => 'cancel', 'order' => 103, 'msg' => $m103['id']]);
$to = array_map('intval', array_column($q("SELECT to_user FROM comms_messages WHERE kind = 'biz.booking' AND thing LIKE 'booking:103:%' ORDER BY to_user"), 'to_user'));
$t('the customer cancels: the business\'s team is told (biz.booking: Owner, General, Accountant)', $to === [1, 2] || $to === [1, 2, 3], $to);
$t('…not also as a new order (no biz.order); the inbox note is kept', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'biz.order'") && (int) $one("SELECT COUNT(*) FROM business_messages WHERE body LIKE 'Order #103 was cancelled%'") === 1);
$t('…the customer isn\'t told of their own cancelling', !(int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'booking.changed' AND thing LIKE 'booking:103:%'"));
$t('…the body drawn again: Cancelled, no Cancel button', str_contains($html, 'Cancelled') && !str_contains($html, '"act":"cancel"') && str_contains($said, 'Cancelled'), $html);
$ins->execute([104, str_repeat('e', 32), 9, 'sam@member.test', 'Sam C', $line(51, 'rental', $after, date('Y-m-d', strtotime('+5 days')), 2), 'awaiting', date('Y-m-d H:i:s', strtotime('-1 hour')), $old, $old, null]);
MarketCheckout::tick($pdo);
$t('an unpaid rental whose hold ran out is released; the customer is told (booking.changed)', $one('SELECT status FROM market_orders WHERE id = 104') === 'expired' && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'booking.changed' AND to_user = 9 AND thing LIKE 'booking:104:%' AND title LIKE '%released%'") === 1);
$t('…and the business (biz.booking)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'biz.booking' AND to_user = 1 AND thing LIKE 'booking:104:%'") === 1);
$t('…once: the next pass tells no one again', MarketCheckout::tick($pdo) !== null && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing LIKE 'booking:104:%'") === (int) $one("SELECT COUNT(*) FROM comms_messages WHERE thing LIKE 'booking:104:%'") && (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'booking.changed' AND thing LIKE 'booking:104:%'") === 1);
$mbiz = $msg('biz.booking', 1, 'booking:104:%');
$t('the business\'s body: the customer, the dates, Open the order', str_contains(($bz = Bookings::pulseBody($pdo, $mbiz, 1))['html'], 'Sam C') && str_contains($bz['html'], 'Open the order') && str_starts_with($bz['state'], 'Expired'), $bz);

echo "Groups\n";
$now = date('Y-m-d H:i:s', strtotime('-30 days'));
$pdo->exec("INSERT INTO community_groups (id, name, about, owner_id, join_mode, created_at) VALUES (20,'Lyman Lake Paddlers','',30,'approval','$now')");
$pdo->exec("INSERT INTO community_groups (id, name, about, owner_id, join_mode, created_at, owner_kind, owner_ref) VALUES (21,'Joe\\'s Regulars','',1,'approval','$now','listing',10)");
$pdo->exec("INSERT INTO community_group_members (group_id, user_id, role, status, joined_at) VALUES (20,30,'owner','active','$now'),(20,31,'moderator','active','$now'),(20,32,'member','active','$now'),(20,33,'member','active','$now'),(20,34,'member','active','$now'),(20,35,'member','active','$now')");
$pdo->exec("INSERT INTO user_links (user_id, target_type, target_id, label, url, muted) VALUES (33,'group','20','Lyman Lake Paddlers','/community/group.php?id=20',1)");
$pdo->exec("INSERT INTO user_blocks VALUES (34, 35)");
Community::post($pdo, 35, 20, 'Paddle Saturday at 8? @[Bea Member](u:32)');
$pid = (int) $one('SELECT MAX(id) FROM community_posts');
$to = array_map('intval', array_column($q("SELECT to_user FROM comms_messages WHERE kind = 'group.post' AND thing = 'gpost:20:$pid' ORDER BY to_user"), 'to_user'));
$t('a new post reaches the members (group.post), not its author', $to === [30, 31], $to);
$t('…not a member who muted the group (link settings), nor one who blocked the author', !in_array(33, $to, true) && !in_array(34, $to, true));
$t('…a member mentioned in it gets group.mention instead (Alert)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'group.mention' AND to_user = 32 AND lvl = 'alert'") === 1 && !in_array(32, $to, true));
$t('…never by email unless chosen', !array_filter(Mailer::$sent, fn ($m) => str_contains($m['subject'], 'Lyman')));
Community::post($pdo, 32, 20, 'Count me in');
$t('the next post adds to the unread one for that group (one per group while unread)', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'group.post' AND to_user = 30") === 1 && (int) $one("SELECT times FROM comms_messages WHERE kind = 'group.post' AND to_user = 30") === 2);
$mp = $msg('group.post', 31);
$gb = GroupNotices::pulseBody($pdo, $mp, 31);
$t('group.post body: the post, the posts since, Open the group, Mute its posts', str_contains($gb['html'], 'Paddle Saturday') && str_contains($gb['html'], 'Count me in') && str_contains($gb['html'], 'Open the group') && str_contains($gb['html'], '"act":"mute"'), $gb['html']);
[$said, $html] = GroupNotices::act($pdo, 31, ['act' => 'mute', 'group' => 20, 'msg' => $mp['id']]);
$t('Mute from the message: no more of its posts; Unmute offered in its place', GroupNotices::muted($pdo, 31, 20) && str_contains($html, '"act":"unmute"'));
Community::post($pdo, 35, 20, 'Launch at the north ramp');
$t('…a muted member gets no new posts (others do)', (int) $one("SELECT times FROM comms_messages WHERE kind = 'group.post' AND to_user = 31") === 2 && (int) $one("SELECT times FROM comms_messages WHERE kind = 'group.post' AND to_user = 30") === 3);
GroupNotices::act($pdo, 31, ['act' => 'unmute', 'group' => 20]);
$t('Unmute brings them back', !GroupNotices::muted($pdo, 31, 20));
// join requests
Community::join($pdo, 36, 20);
$to = array_map('intval', array_column($q("SELECT to_user FROM comms_messages WHERE kind = 'group.join' AND thread = 'gjoin:20' ORDER BY to_user"), 'to_user'));
$t('a request to join reaches the owner and moderators (group.join, Alert)', $to === [30, 31], $to);
$mj = $msg('group.join', 31);
$jb = GroupNotices::pulseBody($pdo, $mj, 31);
$t('…its body: who asked, Accept and Decline', str_contains($jb['html'], 'Asker') && str_contains($jb['html'], '"act":"accept"') && str_contains($jb['html'], '"act":"decline"'), $jb['html']);
$t('a member who doesn\'t moderate can\'t Accept through the endpoint', $threw(fn () => GroupNotices::act($pdo, 32, ['act' => 'accept', 'group' => 20, 'member' => 36])) !== '' && Community::membership($pdo, 20, 36)['status'] === 'pending');
[$said, $html] = GroupNotices::act($pdo, 31, ['act' => 'accept', 'group' => 20, 'member' => 36, 'msg' => $mj['id']]);
$t('a moderator Accepts from the message: they\'re in, through Community::manage', Community::membership($pdo, 20, 36)['status'] === 'active' && str_contains($said, 'is in'));
$t('…the body drawn again: "Asker is in", no requests waiting', str_contains($html, 'Asker is in the group') && str_contains($html, 'No requests are waiting'), $html);
$t('…to the owner too, read live', str_contains(GroupNotices::pulseBody($pdo, $msg('group.join', 30), 30)['state'], 'Asker is in'));
$t('Accept again says they\'re in already (no second action)', str_contains($threw(fn () => GroupNotices::act($pdo, 31, ['act' => 'accept', 'group' => 20, 'member' => 36])), 'already'));
Community::join($pdo, 37, 20);
$mj2 = $msg('group.join', 30, 'gjoin:20:37:%') ?: $msg('group.join', 30);
GroupNotices::act($pdo, 30, ['act' => 'decline', 'group' => 20, 'member' => 37, 'msg' => $mj2['id']]);
$t('Decline: the request is closed, they\'re told (a notice)', Community::membership($pdo, 20, 37) === null && (int) $one("SELECT COUNT(*) FROM notices_sent WHERE user_id = 37 AND kind = 'group_join'") === 1);
// a business's group: its duties go to the business's Pulse, by role
Community::join($pdo, 38, 21);
$rows = $q("SELECT to_user, to_entity FROM comms_messages WHERE kind = 'group.join' AND thread = 'gjoin:21' ORDER BY to_user");
$t('a business\'s group: a request reaches its team members who moderate (Owner, Manager), in the business\'s Pulse', array_map('intval', array_column($rows, 'to_user')) === [1, 40] && !array_filter($rows, fn ($r) => (int) $r['to_entity'] !== 10), $rows);
// reports
Reports::file($pdo, 32, 'group_post', $pid, 'spam', 'Not about paddling');
$to = array_map('intval', array_column($q("SELECT to_user FROM comms_messages WHERE kind = 'group.report' ORDER BY to_user"), 'to_user'));
$t('a post reported to the group reaches whoever handles its reports (group.report), not its author', $to === [30, 31], $to);
$mr = $msg('group.report', 31);
$rb = GroupNotices::pulseBody($pdo, $mr, 31);
$t('…its body: the post, the reasons, Keep and Remove', str_contains($rb['html'], 'Paddle Saturday') && str_contains($rb['html'], 'Spam or scam') && str_contains($rb['html'], '"act":"keep"') && str_contains($rb['html'], '"act":"remove"'), $rb['html']);
$t('the keep/remove endpoint refuses a member who doesn\'t handle reports', $threw(fn () => GroupNotices::act($pdo, 32, ['act' => 'remove', 'post' => $pid])) !== '');
[$said, $html] = GroupNotices::act($pdo, 31, ['act' => 'keep', 'post' => $pid, 'msg' => $mr['id']]);
$t('Keep from the message: its reports closed (through Community::decideReport); "Kept by you"', $one("SELECT status FROM content_reports WHERE kind = 'group_post' AND target_id = $pid") === 'dismissed' && str_contains($html, 'Kept by you'), $html);
$t('…the owner\'s copy reads "Kept by Mo Mod"', str_contains(GroupNotices::pulseBody($pdo, $msg('group.report', 30), 30)['state'], 'Kept by Mo Mod'));
Reports::file($pdo, 35, 'group_post', (int) $one("SELECT id FROM community_posts WHERE body = 'Count me in'"), 'unsafe', '');
$t('a report of danger goes to our Safety team, not the group\'s moderators', (int) $one("SELECT COUNT(*) FROM comms_messages WHERE kind = 'group.report'") === 2);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
