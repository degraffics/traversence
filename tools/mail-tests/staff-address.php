<?php
// A staff login's hidden address takes no mail (2026-11-26: "Staging is refreshed" went to <staff id>@staff.traversence.invalid).
// Mailer::send resolves such an address to the login's work email, as Notify does (staff_accounts.email WHERE user_id = the
// hidden record), and refuses and logs any other .invalid address; StagingSite::tellPerson sends staging's news to a staff
// login through Notify by its staff id and to a member by email. On a copy of app/ with Notify recording its sends; the
// rest is the real code. No mail leaves: SMTP points at a closed local port, so each try fails at once and is logged
// (comms_route), which shows where Mailer sent it.
// Run: php staff-address.php <root>/app   (it copies app/ to a temp folder and resets lv_main)
declare(strict_types=1);
$src = rtrim((string) realpath($argv[1] ?? ''), '/'); if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php staff-address.php <root>/app\n"); exit(2); }
$real = (string) file_get_contents($src . '/lib/Notify.php');
$tmp = sys_get_temp_dir() . '/sa-' . getmypid(); @mkdir($tmp . '/w/root', 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($tmp . '/w/root/app'));
copy(dirname($src, 3) . '/.env', $tmp . '/.env');
$app = $tmp . '/w/root/app';
file_put_contents($app . '/lib/Notify.php', "<?php\n// test stand-in: records each send\nfinal class Notify { public static array \$sent = []; public static function send(PDO \$p, string \$kind, array \$to, array \$about, array \$words): int { self::\$sent[] = [\$kind, \$to, \$about, \$words]; return 1; } }\n");
foreach (['SMTP_HOST=127.0.0.1', 'SMTP_PORT=9', 'SMTP_USERNAME=test@x.test', 'SMTP_PASSWORD=x', 'AWS_ACCESS_KEY_ID=', 'SES_ACCESS_KEY=', 'STAGING=0'] as $e) putenv($e);
require_once $app . '/lib/Database.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(80), is_suspended TINYINT DEFAULT 0, is_test TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, staff_id VARCHAR(20), name VARCHAR(80), email VARCHAR(255), status VARCHAR(20))");
$pdo->exec("CREATE TABLE comms_route (id INT AUTO_INCREMENT PRIMARY KEY, message_id INT, via VARCHAR(20), stage VARCHAR(20), detail VARCHAR(255), provider_id VARCHAR(120), happened_at DATETIME, sender_key VARCHAR(30), addr_hash VARCHAR(64), subject VARCHAR(255), mail_kind VARCHAR(30))");
$pdo->exec("INSERT INTO users (id, email, display_name) VALUES (6, 'trv-0001@staff.traversence.invalid', 'TRV-0001'), (7, 'trv-0002@staff.traversence.invalid', 'TRV-0002'), (8, 'trv-0003@staff.traversence.invalid', 'TRV-0003'), (20, 'member@member.test', 'A member')");
$pdo->exec("INSERT INTO staff_accounts (user_id, staff_id, name, email, status) VALUES (6, 'TRV-0001', 'Jason', 'jason@work.test', 'active'), (7, 'TRV-0002', 'Ended', 'gone@work.test', 'ended'), (8, 'TRV-0003', 'No email', '', 'active')");
require_once $app . '/lib/Mailer.php'; require_once $app . '/lib/StagingSite.php'; require_once $app . '/lib/Notify.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$last = fn () => $pdo->query('SELECT stage, detail FROM comms_route ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$try = function (string $to) { try { Mailer::send($to, 'Test', 'Body'); return ''; } catch (Throwable $e) { return $e->getMessage(); } };
// 1. a staff login's hidden address goes to its work email
$e = $try('trv-0001@staff.traversence.invalid'); $r = $last();
$t('a staff login\'s hidden address: sent to its work email (the try is logged to j***@work.test, never to the hidden address)', str_contains((string) ($r['detail'] ?? ''), '@work.test') && !str_contains((string) ($r['detail'] ?? ''), 'invalid'), $r);
// 2. an ended staff login, one with no work email, an unknown one, and any other .invalid: refused and logged
foreach (['trv-0002@staff.traversence.invalid' => 'an ended staff login', 'trv-0003@staff.traversence.invalid' => 'a staff login with no work email', 'trv-0099@staff.traversence.invalid' => 'an unknown staff login', 'someone@example.invalid' => 'another .invalid address'] as $addr => $what) {
    $e = $try($addr); $r = $last();
    $t("$what: refused with why, and logged as not sent", str_starts_with($e, 'Not sent:') && ($r['stage'] ?? '') === 'refused' && str_contains((string) $r['detail'], 'no deliverable address'), [$e, $r]);
}
// 3. a normal address: untouched
$e = $try('member@member.test'); $r = $last();
$t('a normal address goes where it was meant (logged to m***@member.test)', str_contains((string) ($r['detail'] ?? ''), '@member.test'), $r);
// 4. staging's news: a staff login through Notify by its staff id, never mailed to the hidden address
$n0 = (int) $pdo->query('SELECT COUNT(*) FROM comms_route')->fetchColumn();
StagingSite::tellPerson($pdo, 6, 'trv-0001@staff.traversence.invalid', 'refresh:abc', 'Staging is refreshed', 'Done.');
$s = end(Notify::$sent);
$t('staging\'s news to a staff login: Notify, kind staging.done, to staff id 6, with its title and Staging\'s page', $s && $s[0] === 'staging.done' && $s[1] === ['staff' => [6]] && $s[3]['title'] === 'Staging is refreshed' && $s[2]['url'] === '/admin/config/staging', $s);
$t('… and nothing mailed to the hidden address', (int) $pdo->query('SELECT COUNT(*) FROM comms_route')->fetchColumn() === $n0);
// 5. a member who pressed: their own address
$k = count(Notify::$sent);
try { StagingSite::tellPerson($pdo, 20, 'member@member.test', 'refresh:def', 'Staging is refreshed', 'Done.'); } catch (Throwable $x) {}
$t('staging\'s news to a member: their own email, not Notify', count(Notify::$sent) === $k && str_contains((string) ($last()['detail'] ?? ''), '@member.test'), $last());
// 6. the real Notify knows the kind
$t('the real Notify has the kind staging.done (for staff, email on)', str_contains($real, "'staging.done'") && preg_match("/'staging\\.done'\\s*=>\\s*\\[[^\\]]*'staff', 'Team', true/", $real) === 1);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
