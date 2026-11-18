<?php
// decisions/0172 §3 and §3.3's tests: listing cards' one actions helper (ListingActions::actions, ::managed) and Leave a
// message on an unclaimed listing (ListingMessages), on a real MySQL or MariaDB with the live libraries, and stand-ins only
// for what reaches outside or belongs to other builds (mail, Notify, CommsSetup, Pulse, Standing, Team, PlaceScope).
//   Run: mariadb -e 'CREATE DATABASE tvlm'; php listing-messages.php <a copy of app/ with the release laid over it>
//   The migration is read from <app>/migrations/*listing_messages*.sql if the release has one, else the copy below.
//   DB: TV_TEST_DSN (default mysql:host=localhost;dbname=tvlm), user tv / tv.
declare(strict_types=1);
$app = $argv[1] ?? '';
if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php listing-messages.php <app dir>\n"); exit(2); }

// ---- stand-ins -------------------------------------------------------------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO(getenv('TV_TEST_DSN') ?: 'mysql:host=localhost;dbname=tvlm;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
    public static function transaction(callable $f) { $p = self::connection(); $p->beginTransaction(); try { $r = $f($p); $p->commit(); return $r; } catch (Throwable $e) { $p->rollBack(); throw $e; } }
}
final class Mailer { public static array $sent = []; public static bool $fail = false;
    public static function send(string $to, string $subject, string $text, ?string $name = null, string $as = '', string $html = '', string $replyTo = ''): void {
        if (self::$fail) throw new RuntimeException('SMTP refused'); self::$sent[] = compact('to', 'subject', 'text', 'as', 'replyTo'); } }
final class Notify { public static array $sent = [];
    public static function send(PDO $p, string $kind, array $to, array $about, array $words): int { self::$sent[] = compact('kind', 'to', 'about', 'words'); return 1; }
    public static function markRead(PDO $p, int $u, array $ids = [], string $k = '', string $t = ''): void {} }
final class CommsSetup { public static array $paused = [];
    public static function paused(PDO $p, string $k): bool { return isset(self::$paused[$k]); }
    public static function pause(PDO $p, string $k, string $why, ?int $by): void { self::$paused[$k] = $why; }
    public static function prefixFor(string $m): string { return $m === 'listing_request' ? 'REQUESTS' : ''; } }
final class Standing { public static function guard(PDO $p, int $u): void { if ($u === 99) throw new InvalidArgumentException('Your account is limited.'); }
    public static function contentRemoved(PDO $p, int $u, string $w, array $r, ?int $s = null): void { $GLOBALS['standing'][] = $u; } public static function contentRestored(PDO $p, int $u): void {} }
final class Pulse { public static array $tickets = []; public static function openTicket(PDO $p, string $k, string $b): int { self::$tickets[] = [$k, $b]; return count(self::$tickets); } }
final class Team { public static function roleOf(PDO $p, int $u, int $e): ?string { $st = $p->prepare('SELECT access_level FROM listing_access WHERE user_id = ? AND entity_id = ?'); $st->execute([$u, $e]); return $st->fetchColumn() ?: null; } }
final class PlaceScope { public static function notABusiness(PDO $p, int $e): string { return $e === 40 ? 'A drop box.' : ''; } }
final class Auth { public static ?array $me = null; public static function currentUser(): ?array { return self::$me; } public static function currentUserId(): ?int { return self::$me ? (int) self::$me['id'] : null; } }
foreach (['Database', 'Mailer', 'Notify', 'CommsSetup', 'Standing', 'Pulse', 'Team', 'PlaceScope', 'StepUpAuth', 'TrustScore'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// test stand-in\n");
final class StepUpAuth {} final class TrustScore { public static function awardVerificationTier(...$a): void {} }

$pdo = Database::connection();
foreach (['users', 'entities', 'entity_metadata', 'listing_access', 'guest_inquiries', 'guest_inquiry_messages', 'guest_devices', 'email_suppress', 'listing_actions',
          'business_messages', 'business_message_reads', 'content_reports', 'admin_access', 'listing_requests', 'listing_request_sends', 'listing_claims', 'verification_attempts', 'claim_changes', 'marketing_optins', 'business_contact_shares'] as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_admin TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE entities (id INT PRIMARY KEY, name VARCHAR(100), status VARCHAR(30), is_suppressed TINYINT DEFAULT 0, entity_type VARCHAR(20) DEFAULT 'merchant', category_id INT NULL, latitude DECIMAL(9,6) NULL, longitude DECIMAL(9,6) NULL, owner_user_id INT NULL, verified_tier INT NULL, updated_by_type VARCHAR(10) NULL, updated_at DATETIME NULL)");
$pdo->exec("CREATE TABLE entity_metadata (entity_id INT, meta_key VARCHAR(40), meta_value TEXT, updated_at DATETIME NULL)");
$pdo->exec("CREATE TABLE listing_access (entity_id INT, user_id INT, access_level VARCHAR(20), granted_by_user_id INT NULL, PRIMARY KEY (entity_id, user_id))");
$pdo->exec("CREATE TABLE guest_inquiries (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, name VARCHAR(80), contact_type VARCHAR(10), contact VARCHAR(255) NULL, code_hash CHAR(64), device_hash CHAR(64), ip_hash CHAR(64), status VARCHAR(10), guest_read_at DATETIME, biz_read_at DATETIME NULL, created_at DATETIME, last_at DATETIME, moved_user_id INT NULL)");
$pdo->exec("CREATE TABLE guest_inquiry_messages (id INT AUTO_INCREMENT PRIMARY KEY, inquiry_id INT, from_business TINYINT, body TEXT, sent_by INT NULL, emailed TINYINT DEFAULT 0, created_at DATETIME)");
$pdo->exec("CREATE TABLE guest_devices (device_hash CHAR(64) PRIMARY KEY, spam_count INT DEFAULT 0, blocked_at DATETIME NULL)");
$pdo->exec("CREATE TABLE email_suppress (addr_hash CHAR(64), scope VARCHAR(40), reason VARCHAR(20), created_at DATETIME, PRIMARY KEY (addr_hash, scope))");
$pdo->exec("CREATE TABLE listing_actions (entity_id INT, action VARCHAR(20), day DATE, n INT, PRIMARY KEY (entity_id, action, day))");
$pdo->exec("CREATE TABLE business_messages (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, user_id INT, from_business TINYINT, kind VARCHAR(20), body TEXT, big TINYINT, sent_by INT NULL, visible_at DATETIME, created_at DATETIME, msg_type VARCHAR(20) NULL, answered TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE business_message_reads (user_id INT, entity_id INT, person_read_at DATETIME NULL, biz_read_at DATETIME NULL)");
$pdo->exec("CREATE TABLE marketing_optins (user_id INT, entity_id VARCHAR(40), status VARCHAR(10), frequency VARCHAR(10))");
$pdo->exec("CREATE TABLE content_reports (id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT NOT NULL, kind VARCHAR(20), target_id INT, reason VARCHAR(20), note VARCHAR(500), status VARCHAR(10) DEFAULT 'open', created_at DATETIME, route VARCHAR(10) DEFAULT 'staff', handled_by INT NULL, handled_at DATETIME NULL)");
$pdo->exec("CREATE TABLE admin_access (user_id INT, tier VARCHAR(20))");
$pdo->exec("CREATE TABLE listing_claims (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, user_id INT, group_id INT NULL, status VARCHAR(12), role_note VARCHAR(80), text_code VARCHAR(12), listed_phone VARCHAR(12), claimant_phone VARCHAR(12), due_at DATETIME, decided_by INT NULL, decided_at DATETIME NULL, admin_note TEXT NULL, doc_path VARCHAR(200) DEFAULT '', details TEXT NULL)");
$pdo->exec("CREATE TABLE verification_attempts (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, method VARCHAR(20), status VARCHAR(10), reviewed_by_user_id INT NULL, notes TEXT, created_at DATETIME, resolved_at DATETIME)");
$pdo->exec("CREATE TABLE claim_changes (id INT AUTO_INCREMENT PRIMARY KEY, entity_id INT, user_id INT, field VARCHAR(20), old_value TEXT, new_value TEXT, status VARCHAR(10) DEFAULT 'waiting', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
// community_posts etc. aren't needed: Reports' routeReady() looks for content_reports.route and community_posts.held_at
$pdo->exec("DROP TABLE IF EXISTS community_posts"); $pdo->exec("CREATE TABLE community_posts (id INT PRIMARY KEY, held_at DATETIME NULL)");

$mig = glob($app . '/migrations/*listing_messages*.sql');
$sql = $mig ? file_get_contents($mig[0]) : (string) file_get_contents(__DIR__ . '/listing-messages.sql');
foreach ([1, 2] as $_) foreach (array_filter(array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $sql)))) as $q) $pdo->exec($q);   // twice: safe to run again

$pdo->exec("INSERT INTO users VALUES (1,'owner@biz.test','Olive Owner',0),(4,'maria@member.test','Maria Baker',0),(5,'sam@member.test','Sam Smith',0),(6,'staff@trv.test','Stella Staff',1),(99,'lim@member.test','Lim Ited',0),(7,'claim@new.test','Clay Claimer',0)");
// 10 managed; 20 unclaimed with a public email; 30 unclaimed, no email; 40 a drop box; 50 a shelter (location private); 60 unclaimed, email, to claim
$pdo->exec("INSERT INTO entities (id, name, status, category_id, latitude, longitude, owner_user_id) VALUES (10,'Joe\\'s Diner','verified',NULL,36.1,-109.5,1),(20,'Kayenta Tire','unclaimed',NULL,36.7,-110.2,NULL),
            (30,'Red Mesa Feed','unclaimed',NULL,NULL,NULL,NULL),(40,'Book Drop','unclaimed',NULL,36.0,-110.0,NULL),(50,'Hope Women\\'s Shelter','unclaimed',NULL,36.2,-110.1,NULL),(60,'Mesa Cafe','unclaimed',NULL,36.3,-110.3,NULL)");
$meta = [[10,'phone','"928-555-0100"'],[10,'website','"joesdiner.test"'],[10,'email','"hello@joes.test"'],[20,'phone','"928-555-0120"'],[20,'email','"shop@kayentatire.test"'],[20,'website','"https://kayentatire.test"'],
         [30,'phone','"928-555-0130"'],[30,'address','"Hwy 160"'],[30,'city','"Red Mesa"'],[30,'state','"AZ"'],[50,'phone','"928-555-0150"'],[50,'email','"help@hope.test"'],[60,'email','"mesa@cafe.test"'],[60,'phone','"928-555-0160"']];
$ins = $pdo->prepare('INSERT INTO entity_metadata (entity_id, meta_key, meta_value) VALUES (?, ?, ?)'); foreach ($meta as $m) $ins->execute($m);
$pdo->exec("INSERT INTO listing_access (entity_id, user_id, access_level) VALUES (10, 1, 'owner')");
$pdo->exec("INSERT INTO admin_access VALUES (6, 'admin')");

$_SESSION = []; $_COOKIE['tv_gd'] = str_repeat('a', 32); $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
putenv('APP_URL=https://traversence.com');
require $app . '/lib/ListingActions.php'; require $app . '/lib/ListingMessages.php'; require $app . '/lib/Reports.php'; require $app . '/lib/Claims.php'; require $app . '/lib/Sales.php';

$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };
$one = fn (string $s) => $pdo->query($s)->fetchColumn();
$row = fn (int $id) => $pdo->query('SELECT * FROM guest_inquiries WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC) ?: [];
$no = function (callable $f) { try { $f(); return ''; } catch (InvalidArgumentException $x) { return $x->getMessage(); } };
$guest = function (array $in) { $c = GuestInquiries::challenge(); $_SESSION['gq'][$c['nonce']]['t'] = time() - 10; preg_match('/(\d+) \+ (\d+)/', $c['question'], $m);
                                return $in + ['nonce' => $c['nonce'], 'answer' => (string) ((int) $m[1] + (int) $m[2]), 'website' => '']; };
$device = function (string $d) { // a device id is 32 hex characters
    $_COOKIE['tv_gd'] = str_repeat($d, 32); };
$ago = function (int $id, int $days) use ($pdo) { $pdo->prepare('UPDATE guest_inquiries SET created_at = ?, last_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() - $days * 86400), date('Y-m-d H:i:s', time() - $days * 86400), $id]); };

$t('the migration ran twice without error; ready', ListingMessages::ready($pdo));

// ---- §3.1: one actions helper, one "managed" test ----------------------------------------------------------------
$k = fn (array $a) => implode(',', array_keys($a));
$t('managed is the one test: listing_access', ListingActions::managed($pdo, 10) && !ListingActions::managed($pdo, 20) && GuestInquiries::managed($pdo, 10) && !GuestInquiries::managed($pdo, 20));
$t('managedSet for a page of results', ListingActions::managedSet($pdo, [10, 20, 30]) === [10 => true]);
$a = ListingActions::actions($pdo, 10, ['id' => 4], [], 'page');
$t('managed, a member: Message, Call, Email, Directions, Website (no Ask, no Claim)', $k($a) === 'message,call,email,directions,website', $k($a));
$t('…Email is a mailto: link counted as email', $a['email']['href'] === 'mailto:hello@joes.test' && $a['email']['out'] === 'email');
$a = ListingActions::actions($pdo, 10, null, [], 'page');
$t('managed, signed out: Message and Ask a question', isset($a['message'], $a['ask']), $k($a));
$a = ListingActions::actions($pdo, 10, ['id' => 1], [], 'page');
$t('managed, its owner: no Message', !isset($a['message']) && !isset($a['claim']), $k($a));
$a = ListingActions::actions($pdo, 10, ['id' => 4], [], 'card');
$t('on a card (grid, map, search): no Message, Call etc. stay', !isset($a['message']) && isset($a['call']), $k($a));
$a = ListingActions::actions($pdo, 20, ['id' => 4], [], 'page');
$t('unclaimed, a member: Leave a message (not Message), Call, Email, Directions, Website, Claim', $k($a) === 'leave,call,email,directions,website,claim', $k($a));
$a = ListingActions::actions($pdo, 20, ['id' => 6], [], 'page');
$t('unclaimed, our staff: no Claim it, no Leave a message', !isset($a['claim']) && !isset($a['leave']), $k($a));
$a = ListingActions::actions($pdo, 30, null, [], 'page');
$t('no point but an address: Directions from the address; no email, no Email', isset($a['directions']) && !isset($a['email']) && str_contains($a['directions']['href'], 'Hwy+160'), $a['directions'] ?? null);
$t('a drop box: nothing to claim or leave', !isset(ListingActions::actions($pdo, 40, null, [], 'page')['claim']) && !isset(ListingActions::actions($pdo, 40, null, [], 'page')['leave']));
$a = ListingActions::actions($pdo, 50, null, [], 'page');
$t('a shelter (location private): no Directions; its phone stays', !isset($a['directions']) && isset($a['call']), $k($a));
$t('a hidden listing: no actions', ListingActions::actions($pdo, 999, null) === []);

// ---- §3.3: leaving one ------------------------------------------------------------------------------------------------
$t('the form says each route in Jason\'s words', ListingMessages::routeFor($pdo, 20) === 'email' && ListingMessages::routeFor($pdo, 30) === 'held'
    && ListingMessages::words('held')[1] === 'Leave it for them' && ListingMessages::words('email')[1] === 'Send by email');
$t('a managed listing takes no left message', str_contains($no(fn () => ListingMessages::leave($pdo, 10, 4, ['body' => 'Hello there'])), 'message it from its page'));
$t('a guest with no way to answer is refused', str_contains($no(fn () => ListingMessages::leave($pdo, 30, null, $guest(['name' => 'Ann', 'body' => 'Do you sell hay?']))), 'Choose how they can answer'));
$t('a guest by phone without the say-so is refused', str_contains($no(fn () => ListingMessages::leave($pdo, 30, null, $guest(['name' => 'Ann', 'body' => 'Do you sell hay?', 'answer_by' => 'phone', 'phone' => '928 555 0199']))), 'Tick the box'));
$t('a failed form check is refused, saying nothing a bot can learn', str_contains($no(fn () => ListingMessages::leave($pdo, 30, null, ['name' => 'Ann', 'body' => 'x', 'nonce' => 'nope'])), 'didn\'t go through'));
$t('a limited member can\'t leave one (Standing)', $no(fn () => ListingMessages::leave($pdo, 30, 99, ['body' => 'Hello there'])) !== '');

// a member, no email on the listing: held
$r = ListingMessages::leave($pdo, 30, 4, ['body' => 'Do you have alfalfa? See https://spam.test and www.x.test']);
$m1 = $r['id'];
$t('a member\'s message to a listing with no email: held, the words say so', $r['state'] === 'held' && str_contains($r['said'], 'deliver it only if the business claims it') && $r['code'] === '', $r);
$t('…links removed from the words', str_contains((string) $one("SELECT body FROM guest_inquiry_messages WHERE inquiry_id = $m1"), '[link removed]') && !str_contains((string) $one("SELECT body FROM guest_inquiry_messages WHERE inquiry_id = $m1"), 'spam.test'));
$t('…nothing emailed', !Mailer::$sent);
$mine = ListingMessages::forMember($pdo, 4);
$t('…in the member\'s own list (their Pulse), marked Waiting: not delivered', count($mine) === 1 && $mine[0]['state_label'] === 'Waiting: not delivered' && $mine[0]['listing'] === 'Red Mesa Feed', $mine);
$t('one waiting per sender per listing', str_contains($no(fn () => ListingMessages::leave($pdo, 30, 4, ['body' => 'Again?'])), 'already have a message waiting'));
$t('the business can\'t see it before a claim (bizList, bizThread)', GuestInquiries::bizList($pdo, 30) === [] && $no(fn () => GuestInquiries::bizThread($pdo, 30, $m1)) !== '');

// a guest by email to a listing with a public email: confirmed first, then the first request goes at once
$device('b');
$r = ListingMessages::leave($pdo, 20, null, $guest(['name' => 'Ann Lee', 'body' => 'Do you fix flats on Sundays?', 'answer_by' => 'email', 'email' => 'ann@guest.test']));
$g1 = $r['id']; $code1 = $r['code'];
$t('a guest by email: waits for the email to be confirmed, a reply code given', $r['state'] === 'confirm' && preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code1) === 1 && str_contains($r['said'], 'a***@guest.test'), $r);
$t('…the confirm email went to the guest only, with the code and "Keep this code"', count(Mailer::$sent) === 1 && Mailer::$sent[0]['to'] === 'ann@guest.test' && str_contains(Mailer::$sent[0]['text'], $code1) && str_contains(Mailer::$sent[0]['text'], 'only way back'), Mailer::$sent);
$t('…first name only is kept', $row($g1)['name'] === 'Ann');
preg_match('#confirm\.php\?t=(\S+)#', Mailer::$sent[0]['text'], $mm); $tok = $mm[1];
$t('…nothing reaches the business before the link is pressed', !array_filter(Mailer::$sent, fn ($m) => $m['to'] === 'shop@kayentatire.test'));
$t('a wrong confirm link does nothing', ListingMessages::confirm($pdo, $g1 . '.' . str_repeat('0', 32)) === null && $row($g1)['leave_state'] === 'confirm');
Mailer::$sent = [];
$c = ListingMessages::confirm($pdo, $tok);
$req = Mailer::$sent[0] ?? [];
$t('confirmed: the first request is emailed at once to the listing\'s public email', $c && $row($g1)['leave_state'] === 'emailed' && ($req['to'] ?? '') === 'shop@kayentatire.test', [$c, $row($g1)['leave_state'], Mailer::$sent]);
$t('…from its own sender (requests@), with the reply address', $req['as'] === 'REQUESTS' && preg_match('/^requests\+r' . $g1 . '-[a-f0-9]{16}@traversence\.com$/', $req['replyTo']) === 1, $req);
$t('…subject and words as decided: first name and words, typed General inquiry, never the visitor\'s contact', $req['subject'] === 'A visitor asked about Kayenta Tire on Traversence'
    && str_contains($req['text'], 'From: Ann (a visitor)') && str_contains($req['text'], 'General inquiry') && str_contains($req['text'], 'fix flats') && !str_contains($req['text'], 'ann@guest.test'));
$t('…Reply to answer, Claim your listing, Stop requests, Report as spam', str_contains($req['text'], 'Reply to this email') && str_contains($req['text'], 'Claim your listing (free)')
    && str_contains($req['text'], 'Stop requests to this address: https://traversence.com/listing/requests.php?t=s') && str_contains($req['text'], 'Report this as spam: https://traversence.com/listing/requests.php?t=p'));
$t('…the sender sees "Sent by email" under Check a reply', GuestInquiries::guestView($pdo, $code1)['state_label'] === 'Sent by email');
$t('the same link again: already confirmed, nothing sent twice', ($c2 = ListingMessages::confirm($pdo, $tok)) && $c2['again'] === true && count(Mailer::$sent) === 1);

// the business answers by email
Mailer::$sent = [];
$ok = ListingMessages::emailReply($pdo, $req['replyTo'], ['from' => ['addr' => 'shop@kayentatire.test', 'name' => 'Kayenta Tire'], 'subject' => 'Re: …',
    'text' => "Yes, 9 to 3 on Sundays.\n\nOn Tue, Nov 18, 2026 at 9:00 AM Traversence <requests@traversence.com> wrote:\n> A visitor on Traversence…"]);
$v = GuestInquiries::guestView($pdo, $code1);
$last = end($v['messages']);
$t('an email reply is stored on the guest\'s thread as the business\'s, the quote cut', $ok && $last['from_business'] && $last['body'] === 'Yes, 9 to 3 on Sundays.' && $v['state_label'] === 'Answered', $v);
$t('…and the guest is told by email, with the answer', count(Mailer::$sent) === 1 && Mailer::$sent[0]['to'] === 'ann@guest.test' && str_contains(Mailer::$sent[0]['text'], '9 to 3'), Mailer::$sent);
$t('a forged reply address is ignored', !ListingMessages::emailReply($pdo, 'requests+r' . $g1 . '-0000000000000000@traversence.com', ['text' => 'hi']));
Mailer::$sent = [];
GuestInquiries::guestReply($pdo, $code1, 'Great, see you Sunday.');
$t('after an answer, the guest writes again: it goes to the same address with the reply address', count(Mailer::$sent) === 1 && Mailer::$sent[0]['to'] === 'shop@kayentatire.test' && str_starts_with(Mailer::$sent[0]['subject'], 'Re: A visitor asked'), Mailer::$sent);

// the pace: later ones queue; 5 waiting → one notice; the count starts again
Mailer::$sent = [];
$q = [];
foreach (['c', 'd', 'e', 'f'] as $i => $d) { $device($d); $_SERVER['REMOTE_ADDR'] = '10.0.1.' . $i; $q[] = ListingMessages::leave($pdo, 20, null, $guest(['name' => 'G' . $d, 'body' => 'Question number ' . $d, 'answer_by' => 'phone', 'phone' => '928-555-01' . $i . '0', 'phone_ok' => 1])); }
$t('later messages to the same address wait in a queue: nothing emailed', !Mailer::$sent && array_column($q, 'state') === ['queued', 'queued', 'queued', 'queued'], array_column($q, 'state'));
$t('…and the sender is told plainly it waits', str_contains($q[0]['said'], 'we\'ll tell it messages are waiting'));
$r5 = ListingMessages::leave($pdo, 20, 5, ['body' => 'Do you sell chains?']);
$t('the 5th waiting: one notice email "5 visitors have left you messages", not the messages', count(Mailer::$sent) === 1 && Mailer::$sent[0]['subject'] === '5 visitors have left you messages on Traversence'
    && !str_contains(Mailer::$sent[0]['text'], 'chains') && str_contains(Mailer::$sent[0]['text'], 'needs an account'), Mailer::$sent);
$t('…those 5 are held for the claim now, and the count starts again', (int) $one("SELECT COUNT(*) FROM guest_inquiries WHERE entity_id = 20 AND leave_state = 'queued'") === 0
    && (int) $one("SELECT COUNT(*) FROM guest_inquiries WHERE entity_id = 20 AND leave_state = 'held' AND sent_at IS NOT NULL") === 5);
$t('…the member\'s own list says the business was told', str_contains(ListingMessages::forMember($pdo, 5)[0]['state_note'], 'emailed it that messages are waiting'));
// a queue under 5: one notice after 10 days
Mailer::$sent = [];
$device('1'); $_SERVER['REMOTE_ADDR'] = '10.0.2.1';
$r6 = ListingMessages::leave($pdo, 20, null, $guest(['name' => 'Hal', 'body' => 'Open on holidays?', 'answer_by' => 'phone', 'phone' => '928-555-0177', 'phone_ok' => 1]));
ListingMessages::tick($pdo);
$t('a queue of 1, under 10 days: no notice yet', !Mailer::$sent && $row($r6['id'])['leave_state'] === 'queued');
$ago($r6['id'], 11);
$d = ListingMessages::tick($pdo);
$t('…after 10 days from the oldest: one notice saying how many', $d['notices'] === 1 && count(Mailer::$sent) === 1 && Mailer::$sent[0]['subject'] === 'A visitor has left you a message on Traversence', [$d, Mailer::$sent]);

// Stop requests: messages are held for a claim after
Mailer::$sent = [];
$sid = (int) $one("SELECT MAX(id) FROM listing_request_sends WHERE entity_id = 20");
$tk = ListingMessages::fromToken($pdo, ListingMessages::token('s', $sid));
$t('a forged Stop link is refused', ListingMessages::fromToken($pdo, 's' . $sid . '-0000000000000000') === null && $tk !== null);
ListingMessages::stopRequests($pdo, $tk);
$t('Stop requests: the route falls back to held', ListingMessages::routeFor($pdo, 20) === 'held' && ListingMessages::fromToken($pdo, ListingMessages::token('s', $sid))['stopped']);
ListingMessages::resumeRequests($pdo, $tk);
$t('…and Send requests again undoes it', ListingMessages::routeFor($pdo, 20) === 'email');

// bounces: that address stops at once and the listing falls back to holding
$device('2'); $_SERVER['REMOTE_ADDR'] = '10.0.3.1';
Mailer::$sent = [];
$rb = ListingMessages::leave($pdo, 60, null, $guest(['name' => 'Ida', 'body' => 'Do you cater?', 'answer_by' => 'phone', 'phone' => '928-555-0188', 'phone_ok' => 1]));
$t('the first message to another listing goes at once', $rb['state'] === 'emailed' && Mailer::$sent[0]['to'] === 'mesa@cafe.test');
ListingMessages::bounced($pdo, 'Mesa@Cafe.test');
$t('a bounce stops that address (email_suppress) and its listing holds', ListingMessages::routeFor($pdo, 60) === 'held' && (int) $one("SELECT COUNT(*) FROM email_suppress WHERE scope = 'all' AND reason = 'bounce'") === 1);
$t('…its send is marked bounced', $one("SELECT outcome FROM listing_request_sends WHERE entity_id = 60 ORDER BY id DESC LIMIT 1") === 'bounced');

// Report this as spam: the existing report process, to Safety in Pulse
$sidReq = (int) $one("SELECT id FROM listing_request_sends WHERE inquiry_id = $g1 AND what = 'request' ORDER BY id LIMIT 1");
ListingMessages::reportSpam($pdo, ListingMessages::fromToken($pdo, ListingMessages::token('p', $sidReq)));
$t('Report this as spam files a report on that message, opening a Safety case', (int) $one("SELECT COUNT(*) FROM content_reports WHERE kind = 'listing_message' AND target_id = $g1 AND reporter_id = 0") === 1
    && count(array_filter(Pulse::$tickets, fn ($x) => str_contains($x[1], 'Message left on an unclaimed listing'))) >= 1, Pulse::$tickets);
$open = array_values(array_filter(Reports::open($pdo), fn ($x) => $x['kind'] === 'listing_message' && $x['id'] === $g1));
$t('…Safety sees it in the reports list (what was reported, and where)', $open && str_contains($open[0]['text'], 'fix flats') && str_contains($open[0]['where'], 'Kayenta Tire'), $open);
$t('…and it counts as a complaint for message control', $one("SELECT outcome FROM listing_request_sends WHERE id = $sidReq") === 'reported');

// message control: complaints over 0.1% of the last 1,000 pause the kind by itself
$pdo->exec("UPDATE listing_request_sends SET outcome = 'complained' WHERE id = (SELECT id FROM (SELECT MAX(id) id FROM listing_request_sends WHERE outcome = 'sent') x)");
$t('two complaints in the last sends: the kind pauses itself', ListingMessages::control($pdo) && isset(CommsSetup::$paused['kind:listing.request']), CommsSetup::$paused);
$t('…paused: the form says held, and new messages wait (paused), not emailed', ListingMessages::routeFor($pdo, 20) === 'held');
unset(CommsSetup::$paused['kind:listing.request']);

// screening: a spammy one is held for Safety, not sent
Mailer::$sent = []; $tickets = count(Pulse::$tickets);
$rs = ListingMessages::leave($pdo, 30, 5, ['body' => 'Get rich with CRYPTO now, click here!']);
$t('a message that looks like spam is held for Safety (a report and a case), not sent', $rs['state'] === 'screened' && !Mailer::$sent && count(Pulse::$tickets) === $tickets + 1
    && (int) $one("SELECT COUNT(*) FROM content_reports WHERE kind = 'listing_message' AND target_id = {$rs['id']}") === 1, $rs);
[, $why] = ListingMessages::screen('Visit our casino bonus page today');
$t('the site\'s own word check (ListingSafety::contentProblem) is part of the screen', str_contains($why, 'unsafe or spam wording'), $why);
$t('a message over the length cap is refused, saying the limit', str_contains($no(fn () => ListingMessages::screen(str_repeat('a ', 1100))), '2,000'));
$t('…its sender sees only that it waits', ListingMessages::forMember($pdo, 5, 30)[0]['state_label'] === 'Waiting: not delivered');
Reports::decide($pdo, 6, 'listing_message', $rs['id'], 'dismiss');
$t('Safety dismisses it: it goes on along its route (held here: no email)', $row($rs['id'])['leave_state'] === 'held');

// withdraw, any time before it's delivered
$device('3'); $_SERVER['REMOTE_ADDR'] = '10.0.4.1';
$rw = ListingMessages::leave($pdo, 30, null, $guest(['name' => 'Jo', 'body' => 'Delete me later', 'answer_by' => 'email', 'email' => 'jo@guest.test']));
ListingMessages::withdraw($pdo, null, 0, $rw['code']);
$t('a guest withdraws by code: deleted, with its email', $row($rw['id']) === [] && (int) $one("SELECT COUNT(*) FROM guest_inquiry_messages WHERE inquiry_id = {$rw['id']}") === 0);
$t('a member can\'t withdraw someone else\'s', str_contains($no(fn () => ListingMessages::withdraw($pdo, 5, $m1)), 'isn\'t yours'));

// the guest limits are shared with guest questions: 3 a day per device
$device('4'); $_SERVER['REMOTE_ADDR'] = '10.0.5.1';
foreach ([60, 30, 50] as $e) try { ListingMessages::leave($pdo, $e, null, $guest(['name' => 'Kit', 'body' => 'Hi', 'answer_by' => 'phone', 'phone' => '928-555-0111', 'phone_ok' => 1])); } catch (InvalidArgumentException $x) { echo "      ($e: " . $x->getMessage() . ")\n"; }
$t('a guest\'s 4th message today from one device is refused (3 a day)', str_contains($no(fn () => ListingMessages::leave($pdo, 20, null, $guest(['name' => 'Kit', 'body' => 'Hi', 'answer_by' => 'phone', 'phone' => '928-555-0111', 'phone_ok' => 1]))), 'today'));

// Sales: counts only
ListingActions::count($pdo, 20, 'call'); $_SESSION['tv_la'] = []; ListingActions::count($pdo, 20, 'email'); $_SESSION['tv_la'] = []; ListingActions::count($pdo, 20, 'call');
$pdo->exec("UPDATE listing_actions SET n = 36 WHERE entity_id = 20 AND action = 'call'");
$reach = Sales::reach($pdo, 20);
$t('Sales sees counts only: calls and emails this month, and messages waiting', $reach['call'] === 36 && $reach['email'] === 1 && $reach['messages'] === 7
    && str_contains($reach['line'], '37 people called or emailed you through Traversence this month.') && str_contains($reach['line'], '7 people have left you messages'), $reach);
$t('…never who or what', !preg_match('/Ann|flats|guest\.test/', json_encode($reach)));
$t('…nothing for a claimed listing (its own Insights have it)', Sales::reach($pdo, 10, 'verified') === []);

// the claim verified: every waiting message delivered into the business's Pulse; each sender told
$pdo->exec("UPDATE entities SET status = 'claimed-unverified', owner_user_id = 7 WHERE id = 20");
$pdo->exec("INSERT INTO listing_access (entity_id, user_id, access_level) VALUES (20, 7, 'owner')");
$pdo->exec("INSERT INTO listing_claims (entity_id, user_id, status, role_note, text_code, listed_phone, claimant_phone, due_at) VALUES (20, 7, 'managed', 'owner', 'TV-1', '', '', NOW())");
$t('claimed but not verified: still nothing shown to the claimant', GuestInquiries::bizList($pdo, 20) === []);
Notify::$sent = []; Mailer::$sent = [];
Claims::verify($pdo, (int) $one('SELECT MAX(id) FROM listing_claims'), 6, 'admin');
$held = array_values(array_filter(Notify::$sent, fn ($n) => $n['kind'] === 'listing.held'));
$t('verified: each waiting message is delivered to the team (listing.held, an Alert), with when it was left', count($held) === 7 && $held[0]['to'] === ['entity' => 20] && str_contains($held[0]['words']['body'], 'Left on'), count($held));
$t('…guest threads show in the business\'s Pulse (bizList) with their history', count(GuestInquiries::bizList($pdo, 20)) === 6 && count(GuestInquiries::bizThread($pdo, 20, $g1)['messages']) === 3);
$t('…a guest\'s email stays ours; a phone shows with the guest\'s say-so', GuestInquiries::bizThread($pdo, 20, $g1)['contact'] === '' && GuestInquiries::bizThread($pdo, 20, $q[0]['id'])['contact'] !== '');
$t('…the member\'s message became an ordinary conversation with the business', (int) $one("SELECT COUNT(*) FROM business_messages WHERE entity_id = 20 AND user_id = 5") === 1 && ListingMessages::forMember($pdo, 5, 20) === []);
$left = array_values(array_filter(Notify::$sent, fn ($n) => $n['kind'] === 'listing.left'));
$t('…the member is told (listing.left, a Notice)', count($left) === 1 && $left[0]['to'] === ['users' => [5]] && str_contains($left[0]['words']['title'], 'was delivered'), $left);
$t('…the guest who gave an email is emailed that it was delivered', count(array_filter(Mailer::$sent, fn ($m) => $m['to'] === 'ann@guest.test' && str_contains($m['subject'], 'was delivered'))) === 1, Mailer::$sent);
$t('…Check a reply now says Answered (it was) and the business can answer in Traversence', GuestInquiries::guestView($pdo, $code1)['state'] === 'answered' && GuestInquiries::bizReply($pdo, 7, 20, $g1, 'Anytime!', 'Kayenta Tire'));
$t('…and Leave a message is gone from its page: it\'s Message now', isset(ListingActions::actions($pdo, 20, ['id' => 4], [], 'page')['message']) && !isset(ListingActions::actions($pdo, 20, ['id' => 4], [], 'page')['leave']));

// 90 days, never claimed: expires, the sender told first, deleted with its email
Notify::$sent = [];
$ago($m1, 91);
$d = ListingMessages::tick($pdo);
$t('never claimed in 90 days: expired and deleted', $d['expired'] >= 1 && $row($m1) === []);
$t('…the sender was told it wasn\'t delivered', (bool) array_filter(Notify::$sent, fn ($n) => $n['kind'] === 'listing.left' && str_contains($n['words']['title'], 'wasn\'t delivered')), Notify::$sent);
// an unconfirmed guest email: deleted after 48 hours, nothing sent
$device('5'); $_SERVER['REMOTE_ADDR'] = '10.0.6.1';
$ru = ListingMessages::leave($pdo, 60, null, $guest(['name' => 'Lu', 'body' => 'Never confirmed', 'answer_by' => 'email', 'email' => 'lu@guest.test']));
$pdo->exec("UPDATE guest_inquiries SET created_at = NOW() - INTERVAL 3 DAY WHERE id = {$ru['id']}");
$d = ListingMessages::tick($pdo);
$t('an email never confirmed: deleted after 48 hours', $d['unconfirmed'] === 1 && $row($ru['id']) === []);

// a guest signs up: the code moves the thread into their account
$device('6'); $_SERVER['REMOTE_ADDR'] = '10.0.7.1';
$rm = ListingMessages::leave($pdo, 30, null, $guest(['name' => 'Max', 'body' => 'Keep this one', 'answer_by' => 'phone', 'phone' => '928-555-0122', 'phone_ok' => 1]));
GuestInquiries::moveToAccount($pdo, $rm['code'], 4);
$t('a guest signs up: their waiting message becomes theirs, in their Pulse, still on its route', ListingMessages::forMember($pdo, 4, 30)[0]['id'] === $rm['id'] && $row($rm['id'])['contact'] === null);

// guest questions on a managed listing are as they were (0052 step 6)
$device('7'); $_SERVER['REMOTE_ADDR'] = '10.0.8.1';
$gq = GuestInquiries::create($pdo, 10, $guest(['name' => 'Nia', 'body' => 'Open late?', 'contact_type' => 'email', 'contact' => 'nia@guest.test']));
$t('a guest question on a managed listing still works and shows to the business at once', $gq !== '' && count(GuestInquiries::bizList($pdo, 10)) === 1 && GuestInquiries::bizThread($pdo, 10, (int) GuestInquiries::bizList($pdo, 10)[0]['id'])['contact'] === 'nia@guest.test');
$t('…and an unmanaged listing still refuses a guest question, pointing to Leave a message', str_contains($no(fn () => GuestInquiries::create($pdo, 30, $guest(['name' => 'Nia', 'body' => 'Hi?']))), 'Leave a message'));
// Safety removes a member's reported message: its words go, and the member's standing follows Reports' rule (0079)
$rr = ListingMessages::leave($pdo, 50, 5, ['body' => 'Buy cheap viagra here']);
$GLOBALS['standing'] = [];
Reports::decide($pdo, 6, 'listing_message', $rr['id'], 'remove');
$t('Safety removes one: its words deleted, marked spam, the member\'s standing told', (int) $one("SELECT COUNT(*) FROM guest_inquiry_messages WHERE inquiry_id = {$rr['id']}") === 0 && $row($rr['id'])['status'] === 'spam' && $GLOBALS['standing'] === [5], $GLOBALS['standing']);
$t('…and a removed one is never routed or counted for Sales', ListingMessages::countWaiting($pdo, 50) === (int) $one("SELECT COUNT(*) FROM guest_inquiries WHERE entity_id = 50 AND status <> 'spam' AND leave_state IN ('held','queued','paused','emailed','answered')"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
