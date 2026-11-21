<?php
// decisions/0176 release 4 (§6, A6; Jason 2026-11-20: "Keep post first", "I'll set the limits"): uploads name their
// staff thread and landing spot. A place's photo is a journey, live at once: the safety team gets one thread per journey,
// "New photo of <place>", raised when its photos go live (it publishes, or a photo is added to a published journey), never
// for a draft, updated in place; a report joins the same thread, unread again, and its own Pulse row folds in (a matter of
// safety keeps its Critical row). Hide (with a reason), Show again, Take it down and Leave it up go through their
// workflows. An order file: the order's team, never the uploader, gets "New artwork on Order #N", one thread per order,
// with Open the order and the parts to come greyed with their reason. Both kinds never push, whatever a person chose.
// Run: mariadb < ../release-b-tests/start-schema.sql && php r4.php <root>/app
//   (<root>/app: the mirror's sync-app with the release's app/ laid over it; a .env four folders above its lib/.)
// Email and push are stood in for (a copy of app/ with Mailer.php and WebPush.php replaced), so nothing leaves.
declare(strict_types=1);
$src = rtrim($argv[1] ?? '', '/');
if (!is_dir($src . '/lib')) { fwrite(STDERR, "usage: php r4.php <root>/app\n"); exit(2); }
$app = dirname($src, 2) . '/r4test-' . getmypid() . '/app';
@mkdir(dirname($app), 0777, true); exec('cp -r ' . escapeshellarg($src) . ' ' . escapeshellarg($app));
@mkdir(dirname($app) . '/website', 0777, true);
register_shutdown_function(fn () => exec('rm -rf ' . escapeshellarg(dirname($app))));
file_put_contents($app . '/lib/Mailer.php', "<?php\nfinal class Mailer { public static array \$sent = []; public static \$thread = null; public static function send(\$to, \$subj, \$text, \$name = null, \$as = '', \$html = ''): void { self::\$sent[] = [\$to, \$subj]; } }\n");
file_put_contents($app . '/lib/WebPush.php', "<?php\nfinal class WebPush { public static array \$sent = []; public static function sendToUser(PDO \$p, int \$u, array \$m): array { self::\$sent[] = ['u' => \$u] + \$m; return ['sent' => 1, 'failed' => 0]; } public static function ready(PDO \$p): bool { return true; } }\n");
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require_once $app . '/lib/Notify.php'; require_once $app . '/lib/UploadThreads.php'; require_once $app . '/lib/Journeys.php'; require_once $app . '/lib/Reports.php'; require_once $app . '/lib/Pulse.php';
require_once $app . '/lib/Mailer.php'; require_once $app . '/lib/WebPush.php';
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n"; } };

// ---- the tables ------------------------------------------------------------------------------------------------------
foreach (['comms_messages', 'comms_route', 'comms_prefs', 'comms_quiet', 'comms_controls', 'push_subs', 'users', 'staff_accounts', 'journeys', 'journey_media', 'content_reports', 'orders', 'order_files', 'wf_workflows', 'wf_versions', 'wf_items', 'wf_history', 'admin_permissions'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
$pdo->exec("CREATE TABLE comms_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(48) NOT NULL, lvl VARCHAR(12) NOT NULL DEFAULT 'notice', sender_type VARCHAR(10) NOT NULL DEFAULT 'system', sender_id INT UNSIGNED NULL, sender_name VARCHAR(120) NOT NULL DEFAULT '', to_user INT UNSIGNED NOT NULL, to_entity INT UNSIGNED NULL, thing VARCHAR(120) NOT NULL, thread VARCHAR(120) NOT NULL DEFAULT '', title VARCHAR(200) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '', pulse_ref VARCHAR(120) NULL, times INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME NULL, UNIQUE KEY comms_once (to_user, kind, thing), KEY comms_thread (to_user, kind, thread))
");
$pdo->exec("CREATE TABLE comms_route (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, via VARCHAR(12) NOT NULL, stage VARCHAR(12) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', provider_id VARCHAR(120) NOT NULL DEFAULT '', happened_at DATETIME NOT NULL, sender_key VARCHAR(30) NULL, addr_hash CHAR(64) NULL, subject VARCHAR(200) NULL, mail_kind VARCHAR(30) NULL)");
$pdo->exec("CREATE TABLE comms_prefs (user_id INT UNSIGNED NOT NULL, kind VARCHAR(48) NOT NULL, via VARCHAR(12) NOT NULL, is_on TINYINT(1) NOT NULL DEFAULT 1, changed_at DATETIME NOT NULL, PRIMARY KEY (user_id, kind, via))");
$pdo->exec("CREATE TABLE comms_quiet (user_id INT UNSIGNED NOT NULL PRIMARY KEY, quiet_from SMALLINT UNSIGNED NOT NULL, quiet_to SMALLINT UNSIGNED NOT NULL, tz VARCHAR(64) NOT NULL DEFAULT '', changed_at DATETIME NOT NULL)");
$pdo->exec("CREATE TABLE push_subs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, endpoint VARCHAR(500) NOT NULL DEFAULT '', failed_at DATETIME NULL)");
$pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(100), created_at DATETIME NULL, is_suspended TINYINT DEFAULT 0)");
$pdo->exec("CREATE TABLE staff_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, email VARCHAR(255), name VARCHAR(100), status VARCHAR(20), level VARCHAR(20), department VARCHAR(20), role VARCHAR(20), assists INT NULL, workspaces VARCHAR(255) NULL)");
$pdo->exec("CREATE TABLE journeys (id INT AUTO_INCREMENT PRIMARY KEY, author_id INT, voice VARCHAR(20) DEFAULT 'contributor', category VARCHAR(40) DEFAULT 'Exploring', title VARCHAR(160), place_key VARCHAR(40) DEFAULT '', place_label VARCHAR(160) DEFAULT '', scope_keys VARCHAR(255) DEFAULT '', status VARCHAR(20), hidden_reason VARCHAR(200) NULL, created_at DATETIME, updated_at DATETIME, published_at DATETIME NULL)");
$pdo->exec("CREATE TABLE journey_media (id INT AUTO_INCREMENT PRIMARY KEY, journey_id INT, path VARCHAR(255) DEFAULT '', tag_place VARCHAR(160) DEFAULT '', sort INT DEFAULT 0)");
$pdo->exec("CREATE TABLE content_reports (id INT AUTO_INCREMENT PRIMARY KEY, reporter_id INT, kind VARCHAR(20), target_id INT, reason VARCHAR(40), note VARCHAR(500) DEFAULT '', status VARCHAR(20) DEFAULT 'open', route VARCHAR(10) DEFAULT 'staff', handled_by INT NULL, handled_at DATETIME NULL, created_at DATETIME)");
$pdo->exec("CREATE TABLE orders (id INT PRIMARY KEY, order_no INT NULL, test_no INT NULL, is_test TINYINT DEFAULT 0, status VARCHAR(20) DEFAULT 'open', user_id INT NULL)");
$pdo->exec("CREATE TABLE order_files (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT, line_id INT DEFAULT 0, kind VARCHAR(20), file_name VARCHAR(255), mime VARCHAR(80) DEFAULT '', created_by INT NULL, created_at DATETIME NULL)");
$pdo->exec("CREATE TABLE wf_workflows (wf_key VARCHAR(40) PRIMARY KEY, name VARCHAR(80), module VARCHAR(40), entity VARCHAR(40), builtin TINYINT, version INT)");
$pdo->exec("CREATE TABLE wf_versions (wf_key VARCHAR(40), version INT, def MEDIUMTEXT, note VARCHAR(255), PRIMARY KEY (wf_key, version))");
$pdo->exec("CREATE TABLE wf_items (wf_key VARCHAR(40), item_id INT, version INT, stage VARCHAR(40), entered_at DATETIME, owner_id INT NULL, title VARCHAR(160), client_email VARCHAR(255) NULL, PRIMARY KEY (wf_key, item_id))");
$pdo->exec("CREATE TABLE wf_history (id INT AUTO_INCREMENT PRIMARY KEY, wf_key VARCHAR(40), item_id INT, version INT, move VARCHAR(40), from_stage VARCHAR(40), to_stage VARCHAR(40), actor INT NULL, note TEXT, at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE admin_permissions (action VARCHAR(80) PRIMARY KEY, min_level VARCHAR(20), departments VARCHAR(255), updated_by INT NULL)");
// people: an author (5), a reporter (6); staff: a safety person (10, Operations), an orders person (11, Finance), the uploader on the order team (12, Sales), a content writer (13)
$pdo->exec("INSERT INTO users (id, email, display_name, is_suspended) VALUES (5, 'a@x.test', 'Ana', 0), (6, 'r@x.test', 'Rae', 0), (10, 's@x.test', 'Sam', 0), (11, 'o@x.test', 'Omar', 0), (12, 'u@x.test', 'Uma', 0), (13, 'c@x.test', 'Cy', 0)");
$pdo->exec("INSERT INTO staff_accounts (user_id, email, name, status, level, department, role) VALUES (10, 's@x.test', 'Sam', 'active', 'staff', 'ops', ''), (11, 'o@x.test', 'Omar', 'active', 'staff', 'fin', ''), (12, 'u@x.test', 'Uma', 'active', 'staff', 'sal', ''), (13, 'c@x.test', 'Cy', 'active', 'staff', 'mkt', '')");
foreach ([10, 11, 12, 13] as $u) $pdo->exec("INSERT INTO push_subs (user_id, endpoint) VALUES ($u, 'https://push.example.test/$u')");
$q = fn (string $s) => $pdo->query($s)->fetchAll(PDO::FETCH_ASSOC);
$now = date('Y-m-d H:i:s');

// ---- who's told: today's recipients ----------------------------------------------------------------------------------
$safe = UploadThreads::safetyTeam($pdo); $ord = UploadThreads::orderTeam($pdo, 12);
require_once $app . '/lib/Workspaces.php';
$t('the safety team is who sees Pulse\'s Reports rows today (Workspaces::allowsArea safety)', $safe === array_values(array_filter([10, 11, 12, 13], fn ($u) => Workspaces::allowsArea($pdo->query("SELECT * FROM staff_accounts WHERE user_id = $u")->fetch(PDO::FETCH_ASSOC), (string) Pulse::SOURCES['safety'][2], $pdo))), $safe);
$t('the order\'s team is who may change orders (fin, svc, sal by its rule), never the uploader', $ord === [11], $ord);

// ---- a photo of a place -----------------------------------------------------------------------------------------------
$pdo->exec("INSERT INTO journeys (id, author_id, title, place_label, status, created_at, updated_at) VALUES (40, 5, 'Sunrise at the arch', 'Delicate Arch', 'draft', '$now', '$now')");
$pdo->exec("INSERT INTO journey_media (journey_id, tag_place) VALUES (40, '')");
$t('a draft\'s photo raises no thread (it isn\'t live)', UploadThreads::placePhoto($pdo, 40, 5) === 0 && !$q('SELECT * FROM comms_messages'));
$pdo->exec("UPDATE journeys SET status = 'published', published_at = '$now' WHERE id = 40");
$n = UploadThreads::placePhoto($pdo, 40, 5);
$m = $q("SELECT * FROM comms_messages WHERE kind = 'upload.place_photo' ORDER BY to_user");
$t('published: one thread for each of the safety team, titled "New photo of <place>"', $n === count($safe) && count($m) === count($safe) && $m[0]['title'] === 'New photo of Delicate Arch' && $m[0]['thing'] === 'journey:40' && $m[0]['lvl'] === 'notice', $m);
$pdo->exec("INSERT INTO journey_media (journey_id, tag_place) VALUES (40, '')");
UploadThreads::placePhoto($pdo, 40, 5);
$m2 = $q("SELECT * FROM comms_messages WHERE thing = 'journey:40' ORDER BY to_user");
$t('another photo: the same message, updated in place (one thread per journey)', count($m2) === count($safe) && $m2[0]['title'] === 'New photos of Delicate Arch (2)' && (int) $m2[0]['times'] === 2, $m2);
$t('…never pushed, whatever the person chose', !WebPush::$sent);
$pdo->exec("INSERT INTO comms_prefs (user_id, kind, via, is_on, changed_at) VALUES (10, 'upload.place_photo', 'push', 1, '$now')");
$t('…even when they switched push on for it', !Notify::pushOn($pdo, 10, 'upload.place_photo') && !Notify::pushOn($pdo, 10, 'upload.place_photo.reported') && !Notify::pushOn($pdo, 10, 'upload.order_file'));
$pr = array_values(array_filter(Notify::settings($pdo, 10, ['staff'])['kinds'] ?? [], fn ($k) => $k['kind'] === 'upload.place_photo'));
$t('…and Your communications says so, its push box off and fixed', $pr && $pr[0]['push'] === false && str_contains((string) $pr[0]['push_fixed'], 'never comes by push'), $pr);
$t('email is off unless chosen (Pulse first)', !Mailer::$sent, Mailer::$sent);
$body = UploadThreads::pulseBody($pdo, $m2[0], (int) $m2[0]['to_user'])['html'];
$t('its body: the one shape (title, status Live since, posted by), Open it in the side panel, Hide with a reason', str_contains($body, 'class="tv-th"') && str_contains($body, 'Live since') && str_contains($body, 'posted by Ana') && str_contains($body, 'href="/journeys/view.php?id=40" data-peek-frame') && str_contains($body, 'name="body" maxlength="200" required') && str_contains($body, '>Hide</button>'), $body);
$t('the place falls back to a photo\'s tagged place, then the title', UploadThreads::placeOf($pdo, ['id' => 99, 'place_label' => '', 'title' => 'A walk']) === 'A walk');

// a report joins the thread
Reports::file($pdo, 6, 'journey', 40, array_key_first(array_filter(Reports::REASONS, fn ($k) => !preg_match('/safety|danger|threat|harass|sacred|restricted|private|privacy|personal/', (string) $k), ARRAY_FILTER_USE_KEY)), 'Not this place');
$m3 = $q("SELECT * FROM comms_messages WHERE thing = 'journey:40' ORDER BY to_user");
$t('a report joins the same thread: the same message, its title says reported, unread again (Alert)', count($m3) === count($safe) && str_ends_with($m3[0]['title'], ': reported') && $m3[0]['kind'] === 'upload.place_photo.reported' && $m3[0]['lvl'] === 'alert' && $m3[0]['read_at'] === null, $m3[0] ?? null);
$t('…still never pushed', !WebPush::$sent);
$b3 = UploadThreads::pulseBody($pdo, $m3[0], (int) $m3[0]['to_user'])['html'];
$t('…its body lists the report, with Take it down and Leave it up (asking first)', str_contains($b3, '1 open report') && str_contains($b3, 'Not this place') && str_contains($b3, '>Take it down</button>') && str_contains($b3, '>Leave it up</button>') && str_contains($b3, 'data-confirm="Take it down?'), $b3);
$t('UploadThreads knows the journey has a thread (Pulse folds its report row in)', UploadThreads::hasPlaceThread($pdo, 40) && !UploadThreads::hasPlaceThread($pdo, 41));
$ps = (string) file_get_contents($app . '/lib/Pulse.php');
$rid0 = (int) $q("SELECT id FROM content_reports WHERE target_id = 40 ORDER BY id")[0]['id'];
$t('…one row: while reported, the thread rides on the report\'s own Pulse case (pulse_ref), which keeps its clock, assign and escalate', count(array_filter($m3, fn ($r) => $r['pulse_ref'] === 'report:' . $rid0)) === count($m3), array_column($m3, 'pulse_ref'));
$t('…Pulse still lists every open report as its case (nothing folded away)', !str_contains($ps, 'hasPlaceThread') && str_contains($ps, "\$add('report:' . \$r['id'], 'safety', \$crit ? 'critical' : 'alert'"));
$s0 = fn (string $tt, string $h, ?array $act = null, bool $auto = false, string $ext = '') => ['t' => $tt, 'h' => $h, 'act' => $act, 'auto' => $auto];
$pb = Pulse::playbook(['key' => 'report:' . $rid0, 'url' => '/admin/reports.php', 'reply_to' => null]);
$t('…the report\'s case carries the thread\'s steps: see the photos, Take it down, Leave it up, Hide with a reason, then Resolve', $pb[0]['act'] === ['tool', '/journeys/view.php?id=40'] && $pb[1]['act'] === ['do', 'upload_report_remove', 'Take it down'] && $pb[2]['act'] === ['do', 'upload_report_dismiss', 'Leave it up'] && $pb[3]['act'][0] === 'form' && $pb[3]['act'][1] === 'upload_hide' && end($pb)['act'] === ['resolve'], $pb);
$pdo->exec("INSERT INTO content_reports (reporter_id, kind, target_id, reason, status, created_at) VALUES (6, 'group', 3, 'spam', 'open', '$now')"); $ridG = (int) $pdo->lastInsertId();
$t('…any other report keeps its own steps', Pulse::playbook(['key' => 'report:' . $ridG, 'url' => '/admin/reports.php', 'reply_to' => null])[0]['t'] === 'Read the report and see what it\'s about');
$pdo->exec("DELETE FROM content_reports WHERE id = $ridG");

// its buttons, through their workflows
$rid = (int) $q("SELECT id FROM content_reports WHERE target_id = 40")[0]['id'];
$msg = (int) $m3[0]['id']; $viewer = (int) $m3[0]['to_user'];
[$said, $html] = UploadThreads::act($pdo, $viewer, ['act' => 'report_dismiss', 'report' => $rid, 'msg' => $msg]);
$t('Leave it up: the report workflow closes the report, and the body is drawn again', str_starts_with($said, 'Left up') && $q("SELECT status FROM content_reports WHERE id = $rid")[0]['status'] === 'dismissed' && str_contains($html, 'class="tv-th"') && !str_contains($html, 'Take it down'), [$said, $q("SELECT status FROM content_reports WHERE id = $rid")]);
try { UploadThreads::act($pdo, $viewer, ['act' => 'hide', 'journey' => 40, 'body' => '  ', 'msg' => $msg]); $t('Hide asks for its reason', false); }
catch (InvalidArgumentException $x) { $t('Hide asks for its reason', str_contains($x->getMessage(), 'Say why')); }
[$said, $html] = UploadThreads::act($pdo, $viewer, ['act' => 'hide', 'journey' => 40, 'body' => 'Not taken here', 'msg' => $msg]);
$j = $q('SELECT status, hidden_reason FROM journeys WHERE id = 40')[0];
$t('Hide: the journey workflow hides it with the reason its author sees; the thread says Hidden, with Show again', $j['status'] === 'hidden' && $j['hidden_reason'] === 'Not taken here' && str_contains($html, 'Hidden since') && str_contains($html, '>Show again</button>') && !str_contains($html, '>Hide</button>'), [$j, $said]);
$t('…its title follows: "…: hidden", on every staff member\'s Pulse', count(array_filter($q("SELECT title FROM comms_messages WHERE thing = 'journey:40'"), fn ($r) => str_ends_with($r['title'], ': hidden'))) === count($safe));
$t('…kept in the workflow\'s history, with who', (bool) $q("SELECT 1 FROM wf_history WHERE wf_key = 'journey' AND item_id = 40 AND move = 'hide' AND actor = $viewer AND note = 'Not taken here'"));
[$said] = UploadThreads::act($pdo, $viewer, ['act' => 'show', 'journey' => 40, 'msg' => $msg]);
$t('Show again: its way back, through the workflow\'s Restore', $q('SELECT status FROM journeys WHERE id = 40')[0]['status'] === 'published' && str_contains($said, 'on the site again'));
$t('…and back to its plain title once it\'s live again with no open report', !array_filter($q("SELECT title FROM comms_messages WHERE thing = 'journey:40'"), fn ($r) => str_contains($r['title'], ':')));
try { UploadThreads::act($pdo, $viewer, ['act' => 'show', 'journey' => 40, 'msg' => $msg]); $t('a move that doesn\'t fit where it is says why', false); }
catch (InvalidArgumentException $x) { $t('a move that doesn\'t fit where it is says why', $x->getMessage() !== ''); }
try { UploadThreads::act($pdo, $viewer, ['act' => 'nope']); $t('an unknown step is refused', false); } catch (InvalidArgumentException $x) { $t('an unknown step is refused', true); }

$t('after Leave it up, the thread is its own row again (no open report to ride on)', !array_filter($q("SELECT pulse_ref FROM comms_messages WHERE thing = 'journey:40'"), fn ($r) => $r['pulse_ref'] !== null));
// the same steps from the report's case in Pulse (Pulse::manage), and noted on the case
$pdo->exec("UPDATE journeys SET status = 'published' WHERE id = 40");
$pdo->exec("INSERT INTO content_reports (reporter_id, kind, target_id, reason, status, created_at) VALUES (6, 'journey', 40, 'spam', 'open', '$now')"); $rid2 = (int) $pdo->lastInsertId();
$pdo->exec("CREATE TABLE IF NOT EXISTS pulse_notes (id INT AUTO_INCREMENT PRIMARY KEY, item_key VARCHAR(120), user_id INT, kind VARCHAR(20), body TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
try { $said2 = Pulse::manage($pdo, 'report:' . $rid2, 'upload_report_remove', [], $viewer); } catch (Throwable $x) { $said2 = 'ERR ' . $x->getMessage(); }
$t('the report\'s case: Take it down works there too (the report workflow), its answer said', str_starts_with($said2, 'Taken down') && $q("SELECT status FROM journeys WHERE id = 40")[0]['status'] === 'hidden', $said2);
$pb2 = Pulse::playbook(['key' => 'report:' . $rid2, 'url' => '/admin/reports.php', 'reply_to' => null]);
$t('…and its steps then offer Show again', (bool) array_filter($pb2, fn ($x) => ($x['act'] ?? null) === ['do', 'upload_show', 'Show again']), $pb2);
try { Pulse::manage($pdo, 'report:' . $rid2, 'upload_show', [], $viewer); } catch (Throwable $x) {}
$t('…which puts it back', $q("SELECT status FROM journeys WHERE id = 40")[0]['status'] === 'published');

// publishing tells staff once; a staff journey's own uploader isn't told
$src2 = (string) file_get_contents($app . '/lib/Journeys.php');
$t('Journeys::save raises the thread only on the publish itself (not on each save)', str_contains($src2, "if (\$publish && (\$cur['status'] ?? '') !== 'published' && (self::get(\$pdo, \$id)['status'] ?? '') === 'published') { require_once __DIR__ . '/UploadThreads.php'; UploadThreads::placePhoto(\$pdo, \$id, \$uid); }"));
$ut = (string) file_get_contents($app . '/lib/upload-targets.php');
$t('the upload\'s answer says where it stands: live now, or when you publish', str_contains($ut, "'it\\'s live now.' : 'it goes live when you publish.'") && str_contains($ut, 'if ($live) { require_once __DIR__ . \'/UploadThreads.php\'; UploadThreads::placePhoto($pdo, $id, $uid); }'));
$t('both targets name their thread and their landing spot', substr_count($ut, "'thread' => ['kind' => 'upload.") === 2 && str_contains($ut, "'land' => '/journeys/view.php?id={journey}'") && str_contains($ut, "'land' => '/admin/backoffice/orders/{order}#docs'"));

// ---- an order file -----------------------------------------------------------------------------------------------------
$pdo->exec("INSERT INTO orders (id, order_no) VALUES (9, 1009)");
$pdo->exec("INSERT INTO order_files (order_id, kind, file_name, created_by, created_at) VALUES (9, 'artwork', 'logo.pdf', 12, '$now')");
$t('a shipping label tells no one', UploadThreads::orderFile($pdo, 9, 'label', 12) === 0);
$no = UploadThreads::orderFile($pdo, 9, 'artwork', 12);
$om = $q("SELECT * FROM comms_messages WHERE kind = 'upload.order_file'");
$t('artwork: the order\'s team (not the uploader) gets "New artwork on Order #N"', $no === 1 && count($om) === 1 && (int) $om[0]['to_user'] === 11 && $om[0]['title'] === 'New artwork on Order #1009' && $om[0]['thing'] === 'order:9:files', $om);
$pdo->exec("INSERT INTO order_files (order_id, kind, file_name, created_by, created_at) VALUES (9, 'print', 'print.pdf', 12, '$now')");
UploadThreads::orderFile($pdo, 9, 'print', 12);
$om2 = $q("SELECT * FROM comms_messages WHERE kind = 'upload.order_file'");
$t('another file: the same thread, updated in place', count($om2) === 1 && (int) $om2[0]['times'] === 2);
$ob = UploadThreads::pulseBody($pdo, $om2[0], 11)['html'];
$t('its body: the files in words, Open the order on its Files in the side panel', str_contains($ob, 'logo.pdf') && str_contains($ob, 'print.pdf') && str_contains($ob, 'href="/admin/backoffice/orders/9#docs" data-peek-frame'), $ob);
$t('…Ask for a new file and the file check greyed, each with its reason shown', str_contains($ob, 'disabled title="Ask for a new file is to come') && str_contains($ob, '>Check the files</button><small class="tv-fix-soon">To come: Jason sets the file check') && str_contains($ob, 'tv-fix-soon">Ask for a new file is to come'));
$t('…never pushed', !WebPush::$sent);

// the endpoint: staff only, CSRF, JSON
$ep = (string) file_get_contents(dirname($src) . '/website/api/upload-act.php');
$t('/api/upload-act.php: signed in, staff only (as /admin/api/), POST with its CSRF token', str_contains($ep, "Response::error('Staff only.', 403, 'forbidden')") && str_contains($ep, 'Response::requireCsrf()') && str_contains($ep, 'IntakeStager::canUseIntake'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
