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
$t('… its lines, why, checks, way back; the Work queue its button only, never a second link in the words (the 0172 note)', str_contains($s['words']['body'], 'p3-1.zip: The before-and-after check') && str_contains($s['words']['body'], 'Checks on staging: beforeafter.test 19') && !str_contains($s['words']['body'], 'https://') && $s['about']['url'] === '/admin/config/control?tab=development' && str_contains($s['words']['title'], '1 release for your OK'), $s['words']);
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
// where a message's "Open it" goes (the session manager, 2026-11-27, option (a)): its link, else its ticket, else the Work queue
$t('… with no link or task: the Work queue, as before', $m['about']['url'] === '/admin/config/control?tab=development', $m['about']['url']);
$gauges([], [['id' => 'sm-l1', 'subject' => 'The comparison', 'body' => 'b', 'link' => 'https://github.com/degraffics/traversence/blob/main/research/x.pdf'],
    ['id' => 'sm-l2', 'subject' => 'A page', 'body' => 'b', 'link' => '/admin/config/releases#r322', 'task' => 9],
    ['id' => 'sm-l3', 'subject' => 'A ticket', 'body' => 'b', 'task' => 41],
    ['id' => 'sm-l4', 'subject' => 'Not ours', 'body' => 'b', 'link' => 'https://evil.example/x', 'task' => 7],
    ['id' => 'sm-l5', 'subject' => 'Another owner', 'body' => 'b', 'link' => 'https://github.com/someone/x'],
    ['id' => 'sm-l6', 'subject' => 'Two slashes', 'body' => 'b', 'link' => '//evil.example/x']]);
$n0 = count(Notify::$sent); FounderMail::tick($pdo); $u = []; foreach (array_slice(Notify::$sent, $n0) as $x) $u[$x['about']['thing']] = $x['about']['url'];
$t('a GitHub file of ours: Open it is that link', ($u['message:sm-l1'] ?? '') === 'https://github.com/degraffics/traversence/blob/main/research/x.pdf', $u);
$t('a path on our site wins over its task', ($u['message:sm-l2'] ?? '') === '/admin/config/releases#r322');
$t('a task alone: its place on the Work queue (#wq-, as a decision\'s email)', ($u['message:sm-l3'] ?? '') === '/admin/config/control?tab=development#wq-41');
$t('a link not ours is dropped: its task instead', ($u['message:sm-l4'] ?? '') === '/admin/config/control?tab=development#wq-7');
$t('… another GitHub owner, or a protocol-relative //: dropped, the Work queue', ($u['message:sm-l5'] ?? '') === '/admin/config/control?tab=development' && ($u['message:sm-l6'] ?? '') === '/admin/config/control?tab=development');
$t('our own hosts pass: traversence.com and staging.trvgo.us', FounderMail::linkOf('https://traversence.com/admin/config/staging#against') !== '' && FounderMail::linkOf('https://staging.trvgo.us/admin/config/switches') !== '' && FounderMail::linkOf('https://traversence.com.evil.example/') === '' && FounderMail::linkOf('javascript:alert(1)') === '' && FounderMail::linkOf('/a b') === '' && FounderMail::linkOf('/x\\y') === '' && FounderMail::linkOf('/admin/x#y') === '/admin/x#y');
// the words, clean and on brand (Jason, 2026-11-27: "The push is clutter with code vs a clean on brand message")
$wb = "The comparison is ready: [Open the comparison](https://github.com/degraffics/traversence/blob/main/research/x.pdf). Run `gauges.py --write` first.\n\nApprove it on the Work queue: https://traversence.com/admin/config/control?tab=development\nOr https://evil.example/x.";
$pl = FounderMail::pushLine($wb);
$t('a push: one plain sentence, a phrase before a link is the link, no address and no code', $pl === 'The comparison is ready.', $pl);
$t('… never the title again: the body after it, its first letter capital (Design, 2026-11-27)', FounderMail::pushLine("Traversence Core against Wix and Squarespace: what each costs.\n\nMore.", 'Traversence Core against Wix and Squarespace') === 'What each costs.' && FounderMail::pushLine('Other words.', 'Title') === 'Other words.' && FounderMail::pushLine('The comparison is ready. More.', 'The comparison') === 'The comparison is ready.');
$pl2 = FounderMail::pushLine('See https://traversence.com/admin/x and `rm -rf` now, then ' . str_repeat('more words ', 30));
$t('… an address and code in it: stripped; at most 140 characters', !preg_match('#https?://|`|rm -rf#', $pl2) && mb_strlen($pl2) <= 140, $pl2);
[$tx, $hx] = FounderMail::emailParts('The comparison', $wb, 'https://traversence.com/admin/config/control?tab=development#wq-4', "The comparison\n\n" . $wb . "\n\nOpen it: https://traversence.com/admin/config/control?tab=development#wq-4\n\n-- \nfooter");
$t('an email\'s links are named: the phrase before each is the link ("The comparison is ready", "Approve it on the Work queue"), never an address as words', str_contains($hx, '>Approve it on the Work queue</a>') && str_contains($hx, '>The comparison is ready</a>. Run') && !str_contains($hx, 'Open the comparison') && !preg_match('#>https?://#', $hx), $hx);
$t('… a bare address with no phrase: named by where it goes', FounderMail::nameOf('https://github.com/degraffics/x') === 'Open it on GitHub' && str_contains(FounderMail::htmlOf('See this. https://staging.trvgo.us/admin/x'), '>Open it on staging</a>'));
$t('… its button names its place: "Open it on the Work queue"', str_contains($hx, 'href="https://traversence.com/admin/config/control?tab=development#wq-4"') && str_contains($hx, '>Open it on the Work queue</span></a>') && FounderMail::openLabel('https://github.com/degraffics/t/x.pdf') === 'Open it on GitHub' && FounderMail::openLabel('/admin/config/releases') === 'Open it on Traversence');
$t('… a link not ours keeps only its name, never an anchor', !str_contains($hx, 'evil.example/x') && !str_contains($tx, 'evil.example/x') && str_contains($hx, 'evil.example'));
$t('… the text part: each address after its phrase, one colon; Open it on the Work queue; the Founder\'s footer as in HTML; code as code in HTML', str_contains($tx, 'The comparison is ready: https://github.com/degraffics/') && !str_contains($tx, 'ready: Open') && str_contains($tx, 'Approve it on the Work queue: https://traversence.com/') && str_contains($tx, "Open it on the Work queue: https://traversence.com/admin/config/control?tab=development#wq-4") && str_contains($tx, "You get this email because you're the Founder. Change what reaches you: https://traversence.com/admin/dashboard/comms") && !str_contains($tx, 'your choices for') && str_contains($hx, "You get this email because you&#039;re the Founder.") && str_contains($hx, '<code>gauges.py --write</code>'), $tx);
[$tx2, $hx2] = FounderMail::emailParts('Traversence Core', "Traversence Core: what each costs.\n\nMore.", '/x');
$t('… in the text part an address ends its line: ". It\'s two pages" starts the next (Design\'s note)', FounderMail::textOf('Ready: [x](https://github.com/degraffics/t/a.pdf). It\'s two pages.') === "Ready: https://github.com/degraffics/t/a.pdf\nIt's two pages.", FounderMail::textOf('Ready: [x](https://github.com/degraffics/t/a.pdf). It\'s two pages.'));
$t('… the email\'s first paragraph never repeats its heading', str_contains($hx2, '>What each costs.</p>') && substr_count($hx2, 'Traversence Core') === 1 && str_contains($tx2, "Traversence Core\n\nWhat each costs."), $hx2);
$t('the one button is named for what it opens: today\'s batch, the decision, the message\'s own named link, else its place', FounderMail::buttonOf('batch:' . gmdate('Y-m-d'), '/x') === 'Open today\'s batch' && FounderMail::buttonOf('batch:2026-01-01', '/x') === 'Open the batch' && FounderMail::buttonOf('decision:7', '/x') === 'Open the decision' && FounderMail::buttonOf('message:a', 'https://github.com/degraffics/t/x.pdf', 'See [the comparison](https://github.com/degraffics/t/x.pdf).') === 'Open the comparison' && FounderMail::buttonOf('message:a', '/admin/config/control?tab=development#wq-4', 'Words.') === 'Open it on the Work queue');
[$tc, $hc] = FounderMail::emailParts('Pricing', 'What it costs.', 'https://github.com/degraffics/t/x.pdf', '', 'message:c1', 'ceo');
[$tn, $hn] = FounderMail::emailParts('Pricing', 'What it costs.', 'https://github.com/degraffics/t/x.pdf', '', 'message:c2', '');
$t('"From the CEO" under the title for the CEO\'s messages only, in both parts, at #5E4F3C (7.65:1 on the card; Design, 2026-11-27); no second Open it', str_contains($hc, 'color:#5E4F3C">From the CEO</td>') && !str_contains($hc, '#8A7355') && str_contains($tc, "Pricing\nFrom the CEO\n\nWhat it costs.") && !str_contains($hn, 'From the CEO') && !str_contains($tn, 'From the CEO') && substr_count($hc, '>Open it on GitHub</span>') === 1 && substr_count($tc, 'Open it on GitHub: https://') === 1, $tc);
[$t1, $h1] = FounderMail::emailParts('T', 'The comparison is ready: [the comparison](https://github.com/degraffics/t/x.pdf). Two pages.', 'https://github.com/degraffics/t/x.pdf', '', 'message:d1');
$t('one link to a place: a link in the words to the button\'s own address is words only; the button takes the link\'s own name', substr_count($h1, 'href="https://github.com/degraffics/t/x.pdf"') === 1 && str_contains($h1, '>Open the comparison</span>') && substr_count($t1, 'https://github.com/degraffics/t/x.pdf') === 1 && str_contains($t1, 'The comparison is ready. Two pages.'), $t1);
$pd = FounderMail::pulseBody($pdo, ['body' => 'Ready: [x](/admin/y).', 'url' => '/admin/y', 'thing' => 'message:z'], 1);
$t('… in Pulse too', substr_count($pd['html'], 'href="/admin/y"') === 1, $pd);
$gauges([], [['id' => 'sm-ceo', 'subject' => 'S', 'body' => 'b', 'task' => 3, 'from' => 'ceo'], ['id' => 'sm-sm', 'subject' => 'S', 'body' => 'b', 'task' => 3, 'from' => 'session-manager']]);
$t('… who it\'s from, read from batch.json\'s message by its id', FounderMail::fromOf($pdo, 'message:sm-ceo') === 'ceo' && FounderMail::fromOf($pdo, 'message:sm-sm') === '' && FounderMail::fromOf($pdo, 'batch:x') === '');
$pdo->exec("DELETE FROM founder_mail_sent WHERE k IN ('message:sm-ceo','message:sm-sm')"); FounderMail::tick($pdo);
$t('the choices name it for the email: "A message for the Founder", never "the session manager"', str_contains($real, "'founder.message'  => ['A message") && !preg_match("/'founder\\.message'\\s*=>\\s*\\['[^']*session manager/", (string) file_get_contents($src . '/lib/Notify.php')));
$pb = FounderMail::pulseBody($pdo, ['body' => $wb, 'url' => '/admin/config/control?tab=development#wq-4'], 1);
$t('Pulse: the same named links, and its button naming its place', str_contains($pb['html'], '>The comparison is ready</a>') && str_contains($pb['html'], '>Approve it on the Work queue</a>') && str_contains($pb['html'], 'class="tv-btn" href="/admin/config/control?tab=development#wq-4">Open it on the Work queue</a>'), $pb);
$nv = (string) file_get_contents($src . '/lib/Notify.php');
$t('the dispatcher hands only founder.* kinds to FounderMail: push line, email parts, Pulse body', substr_count($nv, "str_starts_with((string) \$m['kind'], 'founder.')") === 2 && str_contains($nv, "'founder.message' => ['FounderMail.php', 'FounderMail', 'pulseBody']"));
// a Tier 3 decision
$r = GaugeTasks::add($pdo, 'area:backlog', 'May staff text go to Gemini\'s paid key?', 'why', '0164 §0.8', 'Jason (test)', 1, '', 'resolution');
foreach (['why_you' => 'Privacy (0179 rule 3)', 'boundary' => 'privacy', 'a_text' => 'Paid key, checked', 'a_pros' => 'A second model', 'a_cons' => 'A privacy risk', 'b_text' => 'Public only', 'b_pros' => 'No new risk', 'b_cons' => 'One model', 'recommend' => 'b', 'delay_impact' => 'Safe on hold', 'cost' => 'None'] as $k => $v) GaugeTasks::briefEdit($pdo, $r, $k, $v, 'Jason (test)');
GaugeTasks::sendBrief($pdo, $r, 'Jason (test)'); $d = end(Notify::$sent);
$t('a decision reaching "Waiting for you": emailed with its brief, options, recommendation and its page\'s link', $d['kind'] === 'founder.decision' && str_contains($d['words']['body'], 'Option A: Paid key, checked') && str_contains($d['words']['body'], 'Recommended: Option B') && !str_contains($d['words']['body'], 'https://') && $d['about']['url'] === '/admin/config/control?tab=development#wq-' . $r && $d['about']['thread'] === 'task:' . $r, $d);
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
