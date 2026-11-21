<?php
// decisions/0176 release 2 part 2, the alarm notice; harness from r1.php (Jason, 2026-11-20, approved): the dispatcher presents every thread. One thread per thing,
// updated in place, never re-sent (A1); the owner supplies facts and buttons and Thread draws the one shape: Title,
// Status, What it affects, What the system is doing, Buttons, Details ▾ (A2); per surface: push the title and status,
// Pulse everything but the details, the board all of it, the logbook the finished story (§2); the trip's walk-through
// (§4): a warning on the board only, a trip reaching a person once, its Escalation Notice the same message raised, Recovered
// the same message closing quietly, the logbook's story.
// Run: mariadb < ../control-board-tests/r2a-schema.sql && php r1.php <root>/app
//   (<root>/app: the mirror's sync-app with the release's app/ laid over it; a .env four folders above its lib/.)
// Email and push are stood in for (a copy of app/ with Mailer.php and WebPush.php replaced), so nothing leaves.
declare(strict_types=1);
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php r1.php <root>/app\n"); exit(2); }
$app = dirname($src, 2) . '/r2ntest-' . getmypid() . '/app';   // beside its root, so its .env is found the same four folders up
@mkdir(dirname($app), 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($app));
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg(dirname($app))));
file_put_contents($app . '/lib/Mailer.php', "<?php\nfinal class Mailer { public static array \$sent = []; public static bool \$routed = false; public static ?array \$thread = null;\n public static function send(\$to, \$subj, \$text, \$name = null, \$as = ''): void { self::\$sent[] = [\$to, \$subj, \$text, self::\$thread]; } }\n");
file_put_contents($app . '/lib/WebPush.php', "<?php\nfinal class WebPush { public static array \$sent = [];\n public static function sendToUser(PDO \$p, int \$u, array \$m): array { self::\$sent[] = ['u' => \$u] + \$m; return ['sent' => 1, 'failed' => 0, 'held' => 0]; } }\n");
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlAlarms.php'; require_once $app . '/lib/ControlLog.php';
require_once $app . '/lib/Thread.php'; require_once $app . '/lib/Notify.php'; require_once $app . '/lib/Mailer.php'; require_once $app . '/lib/WebPush.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n"; } };

// the tables: alarms, logbook, pauses (0175), the dispatcher's own, a founder with a phone
foreach (['control_alarms', 'control_log', 'system_pauses', 'comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet', 'comms_controls', 'push_subs', 'users', 'staff_accounts'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
ControlAlarms::ensure($pdo); ControlLog::ensure($pdo);
$pdo->exec("CREATE TABLE system_pauses (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL, ref VARCHAR(60) NOT NULL, alarm_id INT UNSIGNED NULL, why VARCHAR(255) NOT NULL DEFAULT '', at DATETIME NOT NULL, cleared_at DATETIME NULL, cleared_how VARCHAR(255) NULL, KEY open_ref (kind, ref, cleared_at), KEY alarm (alarm_id))");
$pdo->exec("CREATE TABLE comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL, UNIQUE KEY comms_once (to_user, kind, thing), KEY comms_thread (to_user, kind, thread))");
$pdo->exec("CREATE TABLE comms_route (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, via VARCHAR(12) NOT NULL, stage VARCHAR(12) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', provider_id VARCHAR(120) NOT NULL DEFAULT '', happened_at DATETIME NOT NULL, sender_key VARCHAR(30) NULL, addr_hash CHAR(64) NULL, subject VARCHAR(200) NULL, mail_kind VARCHAR(30) NULL)");
$pdo->exec("CREATE TABLE comms_prefs (user_id INT UNSIGNED NOT NULL, kind VARCHAR(48) NOT NULL, via VARCHAR(12) NOT NULL, is_on TINYINT(1) NOT NULL DEFAULT 1, changed_at DATETIME NOT NULL, PRIMARY KEY (user_id, kind, via))");
$pdo->exec("CREATE TABLE comms_quiet (user_id INT UNSIGNED NOT NULL PRIMARY KEY, quiet_from SMALLINT UNSIGNED NOT NULL, quiet_to SMALLINT UNSIGNED NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', changed_at DATETIME NOT NULL)");
$pdo->exec("CREATE TABLE push_subs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, endpoint VARCHAR(500) NOT NULL DEFAULT '', failed_at DATETIME NULL)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), is_suspended TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20))");
$pdo->exec("INSERT INTO users VALUES (7, 'founder@example.test', 'Founder', 0)");
$pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (7, 'founder@example.test', 'Founder', 'active', 'founder', '', '')");
$pdo->exec("INSERT INTO push_subs (user_id, endpoint) VALUES (7, 'https://push.example.test/x')");
require_once $app . '/lib/SearchIndex.php'; try { SearchIndex::setState($pdo, 'control:duties', '[]'); } catch (Throwable $x) {}
$msgs = fn () => $pdo->query("SELECT * FROM comms_messages ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// decisions/0176's notes of 2026-11-20 (release 2 part 2): the alarm notice, one thread on every channel.
// ---- a trip: SYSTEM ALARM NOTICE, at once, even in quiet hours ----------------------------------------------------------
$pdo->exec("INSERT INTO comms_quiet (user_id, quiet_from, quiet_to, tz, changed_at) VALUES (7, 0, 1439, 'UTC', NOW())");   // quiet all day
$raise = fn () => ControlAlarms::raise($pdo, 'task:failed:second_look', 'wf:W2', 'trip', 'crash', 'W2: its task second_look failed', 'Its work goes back to the queue.', 'Read why on its run.', 'boom: HTTP 500');
$id = $raise(); $raise(); $raise();
$t('a trip, in the founder\'s quiet hours: emailed and pushed at once ("any hour")', count(Mailer::$sent) === 1 && count(WebPush::$sent) === 1, [count(Mailer::$sent), count(WebPush::$sent)]);
$e1 = Mailer::$sent[0] ?? ['', '', '', null];
$t('the email\'s subject: "SYSTEM ALARM NOTICE: <item>: Paused by the system"', $e1[1] === 'SYSTEM ALARM NOTICE: W2 Keeping listings current: Paused by the system', $e1[1]);
// the link is the thread's Pulse row since 0176 R3 (§5: "From a push, it opens the app at the thread's Pulse row")
$t('…its body: the action and status, what\'s going on, what it means, what to do, and the link', (bool) preg_match("~^SYSTEM ALARM NOTICE: .*\n\nPaused by the system \(a trip\) since .*\nWhat's going on: W2: its task second_look failed\nWhat it means: Its work goes back to the queue\.\nWhat to do: Read why on its run\..*Open it: https://traversence\.com/admin/dashboard/stream#case=alarm:" . $id . '~s', $e1[2]), $e1[2]);
$t('…it starts the alarm\'s email thread (its own Message-ID, no parent)', is_array($e1[3]) && ($e1[3]['id'] ?? '') !== '' && empty($e1[3]['parent']), $e1[3]);
$t('the push: "SYSTEM ALARM: <item>", its body starting with the action', (WebPush::$sent[0]['title'] ?? '') === 'SYSTEM ALARM: W2 Keeping listings current' && str_starts_with((string) (WebPush::$sent[0]['body'] ?? ''), 'Paused by the system'), WebPush::$sent);
// ---- the Escalation Notice: a reply in the same email thread ----------------------------------------------------------
$qb = fn () => ControlAlarms::raise($pdo, 'x:qb', 'svc:quickbooks', 'trip', 'crash', 'QuickBooks failed', 'Payments fail.', 'Check its key.');
Mailer::$sent = []; WebPush::$sent = [];
$q = $qb(); $qb(); $qb();
$mails = array_values(array_filter(Mailer::$sent, fn ($x) => str_contains((string) $x[1], 'QuickBooks')));
$t('a critical part: its first email "…: Paused by the system", then its Escalation Notice "…: Retries unsuccessful, now admin required"', count($mails) === 2 && str_ends_with((string) $mails[0][1], ': Paused by the system') && str_ends_with((string) $mails[1][1], ': Retries unsuccessful, now admin required'), array_column($mails, 1));
$t('…the second replies in the first\'s thread (In-Reply-To and References to its Message-ID)', count($mails) === 2 && ($mails[1][3]['parent'] ?? '') === ($mails[0][3]['id'] ?? 'x') && ($mails[1][3]['id'] ?? '') !== ($mails[0][3]['id'] ?? ''), array_column($mails, 3));
$m = array_values(array_filter($msgs(), fn ($x) => $x['thing'] === 'alarm:' . $q));
$t('…still one message in Pulse, its row the notice', count($m) === 1 && str_starts_with((string) $m[0]['title'], 'SYSTEM ALARM NOTICE: QuickBooks'), $m);
$pq = array_values(array_filter(WebPush::$sent, fn ($x) => str_contains((string) $x['title'], 'QuickBooks')));
$t('…its pushes: "SYSTEM ALARM: QuickBooks (Intuit)", the Escalation Notice\'s body starting with its action', $pq && end($pq)['title'] === 'SYSTEM ALARM: QuickBooks (Intuit)' && str_starts_with((string) end($pq)['body'], 'Retries unsuccessful, now admin required'), $pq);
// ---- Pulse's row, and the real Mailer's headers -----------------------------------------------------------------------
$psrc = (string) file_get_contents($src . '/lib/Pulse.php');
$t('Pulse\'s alarm row is the notice (ControlAlarms::noticeTitle)', str_contains($psrc, "ControlAlarms::noticeTitle(\$pdo, \$al)"));
$a = ControlAlarms::byId($pdo, $q);
$t('…which reads its state now: Retries unsuccessful once escalated', ControlAlarms::noticeTitle($pdo, $a) === 'SYSTEM ALARM NOTICE: QuickBooks (Intuit): Retries unsuccessful, now admin required', ControlAlarms::noticeTitle($pdo, $a));
$mailer = (string) file_get_contents($src . '/lib/Mailer.php');
$t('the site\'s Mailer writes the thread\'s Message-ID, In-Reply-To and References when Notify sets them, else a random Message-ID', str_contains($mailer, 'public static ?array $thread = null;') && str_contains($mailer, "'In-Reply-To: ' . (string) self::\$thread['parent'], 'References: ' . (string) self::\$thread['parent']") && str_contains($mailer, "bin2hex(random_bytes(16))"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
