<?php
// notify-founder's tests (Jason, 2026-11-25: "Can you use the system for emailing?"; FounderMail) on a real MySQL or MariaDB.
// The one dispatcher is a stand-in here (it records each send; its own tests are tools/notify-tests/), on a copy of app/; the
// rest is live code: CommsSetup::founders, ControlGauges reading the gauges file, GaugeTasks' briefs. Today's batch once, to
// the Founder only; an old batch never; each message id once, bad ones skipped; a decision when its brief is sent, once;
// nothing on staging; no Founder, nothing marked sent (it goes once there is one); the real Notify has the three kinds.
// Run: php founder.php <root>/app   (it copies app/ to a temp folder and resets lv_main)
declare(strict_types=1);
$src = rtrim((string) realpath($argv[1] ?? ''), '/'); if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php founder.php <root>/app\n"); exit(2); }
$real = (string) file_get_contents($src . '/lib/Notify.php');
$tmp = sys_get_temp_dir() . '/nf-' . getmypid(); @mkdir($tmp . '/w/root', 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($tmp . '/w/root/app'));
copy(dirname($src, 3) . '/.env', $tmp . '/.env');   // Database reads the .env four folders above app/lib, as on the server
$app = $tmp . '/w/root/app';
file_put_contents($app . '/lib/Notify.php', "<?php\n// test stand-in: records each send\nfinal class Notify { public static array \$sent = []; public static function send(PDO \$p, string \$kind, array \$to, array \$about, array \$words): int { self::\$sent[] = compact('kind', 'to', 'about', 'words'); return count(\$to['staff'] ?? []); } }\n");
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
$run = function (string $f) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); } };
$H = dirname(__DIR__, 2) . '/handoffs/';
$run(dirname(__DIR__) . '/team-tests/before-schema.sql');
foreach ([$H . 'task-scope/build/app/migrations/2026-11-24_task_scope.sql', $H . 'task-adrs/build/app/migrations/2026-11-24_task_adrs.sql', $H . 'piece2-build/team-tickets/build/app/migrations/2026-11-25_tickets.sql'] as $f) $run($f);
foreach ([1, 2] as $i) $run($src . '/migrations/2026-11-26_founder_mail.sql');
$pdo->exec("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, staff_id VARCHAR(20), name VARCHAR(80), email VARCHAR(255), role VARCHAR(20), level VARCHAR(20), department VARCHAR(10), status VARCHAR(10))");
$pdo->exec("CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)");
require_once $app . '/lib/Notify.php'; require_once $app . '/lib/Staging.php'; require_once $app . '/lib/FounderMail.php'; require_once $app . '/lib/ControlGauges.php'; require_once $app . '/lib/GaugeTasks.php';
$here = new ReflectionProperty(Staging::class, 'here'); $here->setAccessible(true);
$stage = function (bool $on) use ($here) { putenv('STAGING=' . ($on ? '1' : '0')); $here->setValue(null, null); };
$stage(false);
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$base = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/site-audit/results/gauges.json'), true);
$gf = $tmp . '/gauges.json';
$gauges = function (array $batch, array $messages) use ($base, $gf, $pdo) { $c = $base; $c['batch'] = $batch; $c['answers'] = []; $c['messages'] = $messages; file_put_contents($gf, json_encode($c)); ControlGauges::$source = $gf; $pdo->exec('DELETE FROM page_cache'); };
$line = ['release' => 'p3-1.zip', 'what' => 'The before-and-after check', 'why' => 'Piece 3', 'decision' => '0179', 'checks' => 'beforeafter.test 19', 'way_back' => 'Roll back', 'tickets' => []];
$today = gmdate('Y-m-d');
// no Founder yet
$gauges(['date' => $today, 'lines' => [$line]], []);
$t('no Founder: nothing sent, and nothing marked sent (it goes once there is one)', FounderMail::tick($pdo) === [] && !Notify::$sent && !FounderMail::sent($pdo, 'batch:' . $today));
$pdo->exec("INSERT INTO staff_accounts (user_id, staff_id, name, email, role, level, department, status) VALUES (6, 'S1', 'Jason', 'j@x.test', 'owner', 'founder', '', 'active'), (7, 'S2', 'Mo', 'mo@x.test', 'staff', 'manager', 'ops', 'active'), (8, 'S3', 'Old', 'o@x.test', 'owner', 'founder', '', 'ended')");
// today's batch
$out = FounderMail::tick($pdo); $s = Notify::$sent[0] ?? null;
$t('today\'s batch: emailed once, to the Founder alone (not staff, not an ended login)', count(Notify::$sent) === 1 && $s['kind'] === 'founder.batch' && $s['to'] === ['staff' => [6]], [$out, Notify::$sent]);
$t('… its lines, why, checks, way back and the Work queue\'s link', str_contains($s['words']['body'], 'p3-1.zip: The before-and-after check') && str_contains($s['words']['body'], 'Checks on staging: beforeafter.test 19') && str_contains($s['words']['body'], 'https://traversence.com/admin/config/control?tab=development') && str_contains($s['words']['title'], '1 release for your OK'), $s['words']);
$t('… on its own thread, batch:<date>', $s['about']['thread'] === 'batch:' . $today && $s['about']['thing'] === 'batch:' . $today);
$t('… the next run sends nothing again', FounderMail::tick($pdo) === [] && count(Notify::$sent) === 1);
// the latest batch until it's approved (the session manager, 2026-11-26: one merged late in the UTC day never showed)
$y = gmdate('Y-m-d', time() - 86400);
$gauges(['date' => $y, 'lines' => [$line]], []);
$t('yesterday\'s batch, not yet approved: shown on the strip (as "The latest batch") and emailed once', ControlGauges::latestBatch($pdo) !== [] && FounderMail::batch($pdo, ControlGauges::config($pdo, true)) !== '' && count(Notify::$sent) === 2 && str_starts_with(end(Notify::$sent)['words']['title'], 'The batch (') && FounderMail::batch($pdo, ControlGauges::config($pdo, true)) === '' && count(Notify::$sent) === 2, end(Notify::$sent));
GaugeTasks::approve($pdo, 'area:batch-' . $y, 'batch', 'p3-1.zip', 'the daily batch', 'Jason (test)', 6);
$pdo->exec("DELETE FROM founder_mail_sent WHERE k = 'batch:$y'");
$t('… once approved: neither shown nor sent (today\'s is shown on its day either way)', ControlGauges::latestBatch($pdo) === [] && FounderMail::batch($pdo, ControlGauges::config($pdo, true)) === '' && count(Notify::$sent) === 2);
$gauges(['date' => gmdate('Y-m-d', time() + 86400), 'lines' => [$line]], []);
$t('a batch dated in the future: not shown', ControlGauges::latestBatch($pdo) === []);
// the session manager's messages
$gauges([], [['id' => 'sm-2026-11-26-1', 'at' => '2026-11-26T09:00:00Z', 'subject' => 'Three clicks to start the Team', 'body' => "1) https://traversence.com/admin/config/staging#against\n2) …"],
    ['id' => 'bad id!', 'subject' => 'x', 'body' => 'x'], ['id' => 'sm-2', 'subject' => '', 'body' => 'no subject']]);
$out = FounderMail::tick($pdo); $m = end(Notify::$sent);
$t('a message: emailed once by its id, its subject and plain-text body with links, to the Founder', count(Notify::$sent) === 3 && $m['kind'] === 'founder.message' && $m['words']['title'] === 'Three clicks to start the Team' && str_contains($m['words']['body'], 'https://traversence.com/admin/config/staging#against') && $m['to'] === ['staff' => [6]], [$out, $m]);
$t('… a bad id and one with no subject are skipped', count($out) === 1);
$t('… again: nothing', FounderMail::tick($pdo) === [] && count(Notify::$sent) === 3);
// a Tier 3 decision
$r = GaugeTasks::add($pdo, 'area:backlog', 'May staff text go to Gemini\'s paid key?', 'why', '0164 §0.8', 'Jason (test)', 1, '', 'resolution');
foreach (['why_you' => 'Privacy (0179 rule 3)', 'boundary' => 'privacy', 'a_text' => 'Paid key, checked', 'a_pros' => 'A second model', 'a_cons' => 'A privacy risk', 'b_text' => 'Public only', 'b_pros' => 'No new risk', 'b_cons' => 'One model', 'recommend' => 'b', 'delay_impact' => 'Safe on hold', 'cost' => 'None'] as $k => $v) GaugeTasks::briefEdit($pdo, $r, $k, $v, 'Jason (test)');
GaugeTasks::sendBrief($pdo, $r, 'Jason (test)'); $d = end(Notify::$sent);
$t('a decision reaching "Waiting for you": emailed with its brief, options, recommendation and its page\'s link', $d['kind'] === 'founder.decision' && str_contains($d['words']['body'], 'Option A: Paid key, checked') && str_contains($d['words']['body'], 'Recommended: Option B') && str_contains($d['words']['body'], '#wq-' . $r) && $d['about']['thread'] === 'task:' . $r, $d);
$n = count(Notify::$sent); FounderMail::decision($pdo, $r);
$t('… once: asked again, nothing more', count(Notify::$sent) === $n);
$t('… only a Resolution waiting: a Work ticket never', FounderMail::decision($pdo, GaugeTasks::add($pdo, 'area:backlog', 'A work ticket', 'w', 's', 'J', 1)) === '');
// staging
$stage(true); $gauges(['date' => $today, 'lines' => [['release' => 'other.zip', 'what' => 'x', 'why' => '', 'decision' => '', 'checks' => '', 'way_back' => '', 'tickets' => []]]], [['id' => 'sm-st', 'subject' => 'S', 'body' => 'b']]);
$pdo->exec("DELETE FROM founder_mail_sent WHERE k = 'batch:$today'"); $n = count(Notify::$sent);
$t('on staging: nothing is sent', FounderMail::tick($pdo) === [] && count(Notify::$sent) === $n && !FounderMail::sent($pdo, 'message:sm-st'));
$stage(false);
// the real dispatcher has the kinds: email on, not one that can be turned off, the system's own email
$k = [];
if (preg_match_all("/'(founder\\.[a-z]+)'\\s*=>\\s*\\[([^\\]]+)\\]/", $real, $m, PREG_SET_ORDER)) foreach ($m as $x) $k[$x[1]] = $x[2];
$t('Notify has founder.batch, founder.decision and founder.message: staff, email on, not turned off, the system\'s email', count($k) === 3 && !array_filter($k, fn ($v) => !preg_match("/'staff', 'Team', true, false, 'system'/", $v)), array_keys($k));
$t('the job: live only (exits on staging, NOT_ON_STAGING), logs only when it sends (QUIET), set up by itself (AUTO)', (function () use ($src, $app) { require_once $app . '/lib/Scheduler.php'; return isset(Scheduler::JOBS['founder_mail_tick']) && in_array('founder_mail_tick', Scheduler::NOT_ON_STAGING, true) && in_array('founder_mail_tick', Scheduler::QUIET, true) && in_array('founder_mail_tick', Scheduler::AUTO, true); })()
    && str_contains((string) file_get_contents($src . '/scripts/founder_mail_tick.php'), 'if (Staging::here()) exit(0);'));
exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
