<?php
// decisions/0176 release 1 (Jason, 2026-11-20, approved): the dispatcher presents every thread. One thread per thing,
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
$app = dirname($src, 2) . '/r1test-' . getmypid() . '/app';   // beside its root, so its .env is found the same four folders up
@mkdir(dirname($app), 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($app));
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg(dirname($app))));
file_put_contents($app . '/lib/Mailer.php', "<?php\nfinal class Mailer { public static array \$sent = []; public static bool \$routed = false;\n public static function send(\$to, \$subj, \$text, \$name = null, \$as = ''): void { self::\$sent[] = [\$to, \$subj, \$text]; } }\n");
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

// ---- 1. the shape, per surface (0176 §2) --------------------------------------------------------------------------------
$f = ['title' => 'Common Crawl isn\'t answering', 'level' => 'trip', 'pill' => 'Trip', 'status' => 'Paused by the system (a trip) since {t:2026-11-20T09:12:00Z}.',
      'affects' => ['Re-read websites (W2): also from the crawler reading directly'], 'doing' => 'It asks again at its check.', 'todo' => 'Nothing yet.',
      'buttons' => [['Acknowledge', 'ack', '5']], 'details' => [['Reading', 'HTTP 503 nginx <b>']]];
$board = Thread::html($f, 'board', null, '/admin/api/config/control'); $pulse = Thread::html($f, 'pulse', null, '/admin/api/config/control');
$t('the board: all six parts, in order, the details folded', preg_match('~tv-th-title.*Status:.*What it affects:.*What the system is doing:.*What to do:.*data-msg-act.*<details class="tv-th-details"><summary>Details ▾</summary>~s', $board) === 1, $board);
$t('…the raw evidence escaped, never drawn as markup', str_contains($board, 'HTTP 503 nginx &lt;b&gt;') && !str_contains($board, 'nginx <b>'));
$t('…a time is labelled, in the viewer\'s zone (tv-time)', str_contains($board, '<time datetime="2026-11-20T09:12:00Z" data-tv-time data-tz="UTC">Nov 20, 09:12 UTC</time>'));
$t('Pulse: everything but the details', str_contains($pulse, 'What it affects:') && str_contains($pulse, 'Acknowledge') && !str_contains($pulse, 'Details ▾'));
$p = Thread::push($f);
$t('push: the title and the status only, its time in UTC words', $p === ['title' => 'Common Crawl isn\'t answering', 'body' => 'Paused by the system (a trip) since Nov 20, 09:12 UTC.'], $p);
$lb = Thread::text(['level' => 'closed'] + $f, 'logbook');
$t('the logbook: the finished story (title, status, what it affected), no buttons or what to do', str_starts_with($lb, 'Common Crawl isn\'t answering. Status:') && str_contains($lb, 'What it affects:') && !str_contains($lb, 'What to do'), $lb);
$t('its look uses the palette\'s tokens, no literal red or amber', str_contains(Thread::css(), 'var(--tv-fail)') && str_contains(Thread::css(), 'var(--tv-hold)') && !preg_match('/#(a62b2b|8f5600)/i', Thread::css()));

// ---- 2. a fault: a warning on the board only (0171 §7's note; 0176 §4 step 1) --------------------------------------------
$raise = fn () => ControlAlarms::raise($pdo, 'task:failed:second_look', 'wf:W2', 'trip', 'crash', 'W2: its task second_look failed', 'Its work goes back to the queue.', 'Read why on its run.', 'boom: HTTP 500');
$id = $raise(); $a = ControlAlarms::byId($pdo, $id);
$fa = ControlAlarms::facts($pdo, $a);
$t('a first failure: a warning while the system tries (3c)', $a['level'] === 'warning', $a['level']);
$t('…its status says so, read from the alarm: "Warning since …, seen 1 time"', str_starts_with($fa['status'], 'Warning since {t:') && str_contains($fa['status'], 'seen 1 time.'), $fa['status']);
$t('…what the system is doing is its own words (trying again, n of 3)', str_contains($fa['doing'], 'trying again by itself (1 of 3'), $fa['doing']);
$t('…the evidence is in the details', in_array(['Reading', 'boom: HTTP 500'], $fa['details'], true));
$t('…and nobody is told: no message, no push', !$msgs() && !WebPush::$sent);

// ---- 3. the tries run out: a trip, Paused by the system, one message (0176 §4 step 2) -----------------------------------
$raise(); $raise(); $a = ControlAlarms::byId($pdo, $id); $m = $msgs();
$t('the third failure: a trip that paused W2 (0175 §3)', $a['level'] === 'trip' && str_contains((string) $a['stopped'], '"pause","wf:W2"'), $a);
$fa = ControlAlarms::facts($pdo, $a);
$t('…its status: "Paused by the system (a trip) since …", closing as Recovered when its check passes', str_starts_with($fa['status'], 'Paused by the system (a trip) since {t:') && str_contains($fa['status'], 'closes as Recovered when its check passes'), $fa['status']);
$t('…what it affects: what it paused', in_array('Paused by the system: W2 (its tasks wait with it)', $fa['affects'], true), $fa['affects']);
$t('…one message to the founder, thing alarm:<id>, shown by its Pulse item (no second row)', count($m) === 1 && $m[0]['kind'] === 'alarm.trip' && $m[0]['thing'] === 'alarm:' . $id && $m[0]['pulse_ref'] === 'alarm:' . $id, $m);
$t('…its words begin with its status', str_starts_with((string) $m[0]['body'], 'Status: Paused by the system (a trip) since '), $m[0]['body'] ?? '');
$t('…one push: its title and status only', count(WebPush::$sent) === 1 && WebPush::$sent[0]['title'] === 'W2: its task second_look failed' && str_starts_with(WebPush::$sent[0]['body'], 'Status: Paused by the system (a trip) since ') && !str_contains(WebPush::$sent[0]['body'], 'What it means'), WebPush::$sent);
$t('…and an email (0171 §7: every trip emails the founder at once)', count(Mailer::$sent) === 1);
$raise(); $t('the same fault again: still one message, nothing re-sent', count($msgs()) === 1 && count(WebPush::$sent) === 1 && count(Mailer::$sent) === 1);

// Pulse's item: the shape's words, everything but the details (0176 §2)
require_once $app . '/lib/Pulse.php';
$src = (string) file_get_contents($app . '/lib/Pulse.php');
$t('Pulse\'s alarm item reads the shape\'s words (Thread::text, surface pulse)', str_contains($src, "Thread::text(ControlAlarms::facts(\$pdo, \$al), 'pulse')"));
$pt = Thread::text(ControlAlarms::facts($pdo, ControlAlarms::byId($pdo, $id)), 'pulse');
$t('…which say its status, what it affects and what to do, and no raw details', str_contains($pt, 'Status: Paused by the system (a trip) since ') && str_contains($pt, 'What it affects: Paused by the system: W2') && str_contains($pt, 'What to do: Read why on its run.') && !str_contains($pt, 'boom'), $pt);
$pb = ControlAlarms::pulseBody($pdo, ['thing' => 'alarm:' . $id], 7);
$t('the message\'s body in Pulse: the shape, Acknowledge posting to the board, no details', str_contains($pb['html'], 'class="tv-th"') && str_contains($pb['html'], 'data-msg-act="/admin/api/config/control"') && str_contains($pb['html'], '&quot;ack&quot;') && !str_contains($pb['html'], 'Details ▾') && $pb['state'] === 'Open', $pb);

// ---- 4. its check passes: Recovered, the same message closing quietly; the logbook's story (§4 steps 4a, 5) ---------------
WebPush::$sent = []; Mailer::$sent = [];
ControlAlarms::clear($pdo, 'task:failed:second_look', 'Its next run finished.');
$m = $msgs(); $a = ControlAlarms::byId($pdo, $id);
$t('its check passes: the trip closes as Recovered (0175\'s note)', $a['cleared_at'] !== null && str_starts_with((string) $a['cleared_how'], 'Recovered:'), $a['cleared_how']);
$t('…the same message, now Recovered: a Notice, still one row', count($m) === 1 && $m[0]['kind'] === 'alarm.recovered' && $m[0]['lvl'] === 'notice' && (int) $m[0]['times'] === 2, $m);
$t('…shown in Pulse as its own row now that the trip\'s item is gone (pulse_ref cleared)', $m[0]['pulse_ref'] === null);
$t('…its words: the finished status ("Closed … Recovered")', str_starts_with((string) $m[0]['body'], 'Status: Closed '), $m[0]['body']);
$t('…quietly: no push (a Notice; staff push by default at Alert and up)', !WebPush::$sent, WebPush::$sent);
$route = (string) $pdo->query("SELECT GROUP_CONCAT(detail SEPARATOR ' | ') FROM comms_route WHERE message_id = " . (int) $m[0]['id'])->fetchColumn();
$t('…its route says it was updated in place', str_contains($route, 'Updated in place: A part recovered by itself'), $route);
$lg = $pdo->query("SELECT what, why FROM control_log WHERE kind = 'clear' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$t('the logbook keeps the finished story: title, how it closed, what it affected', $lg && str_starts_with((string) $lg['why'], 'W2: its task second_look failed. Status: Closed ') && str_contains((string) $lg['why'], 'Recovered'), $lg);

// ---- 5. a critical part: tripped at once, its Escalation Notice the same message raised (§4 steps 2, 4b) ---------------
WebPush::$sent = []; Mailer::$sent = [];
$qb = fn () => ControlAlarms::raise($pdo, 'x:qb', 'svc:quickbooks', 'trip', 'crash', 'QuickBooks failed', 'Payments fail.', 'Check its key.');
$q = $qb(); $m = array_values(array_filter($msgs(), fn ($x) => $x['thing'] === 'alarm:' . $q));
$t('a critical part\'s first failure: one message at once, its status "Tripped at once … still trying"', count($m) === 1 && str_contains((string) $m[0]['body'], 'Status: Tripped at once ') && count(WebPush::$sent) === 1, $m);
$fq = ControlAlarms::facts($pdo, ControlAlarms::byId($pdo, $q));
$t('…what it affects: each need that uses QuickBooks, with its backup or "no backup"', (bool) array_filter($fq['affects'], fn ($l) => str_starts_with($l, 'Our orders\' payments: no backup (wire or check)')), $fq['affects']);
$qb(); $qb(); $m = array_values(array_filter($msgs(), fn ($x) => $x['thing'] === 'alarm:' . $q));
$t('its tries run out: the Escalation Notice is the same message, raised (not a second row)', count($m) === 1 && $m[0]['kind'] === 'alarm.escalated' && $m[0]['lvl'] === 'critical' && str_starts_with((string) $m[0]['title'], 'Escalation Notice:'), $m);
$t('…still shown by the trip\'s own Pulse item', ($m[0]['pulse_ref'] ?? '') === 'alarm:' . $q);
$t('…unread again (it\'s Critical)', $m[0]['read_at'] === null);
$t('…one more push, for the level\'s rise (Critical renotifies, 0172\'s push-polish note), on the same tag', count(WebPush::$sent) === 2 && WebPush::$sent[1]['tag'] === WebPush::$sent[0]['tag'] && WebPush::$sent[1]['renotify'] === true, WebPush::$sent);
$fq = ControlAlarms::facts($pdo, ControlAlarms::byId($pdo, $q));
$t('…its status says the Escalation Notice went, read from the logbook', str_starts_with($fq['status'], 'Escalation Notice {t:') && str_contains($fq['status'], 'retries unsuccessful, an admin is needed'), $fq['status']);

// ---- 6. the board's row: the shape, keeping everything the row had --------------------------------------------------------
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; require_once $app . '/admin/control-console.php';
$btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb="' . $act . '" data-cb-id="' . htmlspecialchars($id) . '"' . ($ok ? '' : ' disabled title="' . htmlspecialchars($why) . '"') . '>' . htmlspecialchars($label) . '</button>';
$all = ControlAlarms::list($pdo);
$html = tv_cb_alarms($all, ControlAlarms::counts($pdo), ['wf:W2' => 'W2 Upkeep', 'svc:quickbooks' => 'QuickBooks'], fn ($x) => 'cb-p-' . str_replace(':', '-', $x), true, '', $btn);
$row = substr($html, (int) strpos($html, 'id="cb-alarm-' . $q . '"'));
$row = substr($row, 0, (int) strpos($row, '</li>'));
$t('the board\'s row keeps its id, level and state for the filter and the redraw', str_contains($html, 'id="cb-alarm-' . $q . '" class="cb-al-trip" data-astate="open" data-alevel="trip"'));
$t('…its pill, title, Holds link to its part', str_contains($row, 'class="cb-pill cb-pill-bad">Trip</span>') && str_contains($row, '<b>QuickBooks failed</b>') && str_contains($row, 'data-cb-go="cb-p-svc-quickbooks"'), $row);
$t('…Status, What it affects, What the system is doing, What to do, in that order', preg_match('~Status:.*What it affects:</b> <b>Holds:</b>.*What the system is doing:.*What to do:~s', $row) === 1, $row);
$t('…its buttons as before: Check and start again, its evidence (Open its card), Acknowledge', str_contains($row, 'data-cb="resolve"') && str_contains($row, 'Open its card') && str_contains($row, 'data-cb="ack"') && str_contains($row, 'class="tv-th-acts cb-acts"'), $row);
$t('…its reading and counts in Details ▾', str_contains($row, '<summary>Details ▾</summary>') && str_contains($row, '<dt>Seen</dt>'), $row);
$cl = substr($html, (int) strpos($html, 'id="cb-alarm-' . $id . '"'));
$t('a cleared alarm\'s row (the filter\'s Cleared): Closed and how, no What to do, Undo-able buttons kept', str_contains($html, 'id="cb-alarm-' . $id . '" class="cb-al-cleared"') && str_contains(substr($cl, 0, (int) strpos($cl, '</li>')), 'Status:</b> Closed ') && !str_contains(substr($cl, 0, (int) strpos($cl, '</li>')), 'What to do:'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
