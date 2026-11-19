<?php
// push-polish (2026-11-19, Chrome's "Possible spam" on Jason's phone): one notification per thing (its tag), alerting again
// only when the level rises or for Critical; a pace per person (past Notify::PUSH_PACE in PUSH_WINDOW minutes the rest fold
// into one quiet summary, Critical always on its own, each folded message's route saying so); the service worker's
// renotify only when asked and its status-bar badge; the site's app manifest and its icons, linked from every page head.
//   Run: mariadb -e 'CREATE DATABASE tvpush'; php push-pace.php <app dir: the release laid over sync-app, with the comms
//   migrations (2027-03-13_comms.sql, 2027-03-20_comms_engine.sql) in its migrations/> [<website dir>]
declare(strict_types=1);
$app = $argv[1] ?? ''; $web = $argv[2] ?? (dirname($app) . '/website');
if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php push-pace.php <app dir> [<website dir>]\n"); exit(2); }

// ---- stand-ins: the database, and WebPush (what would reach a phone) ----------------------------------------------------
final class Database {
    private static ?PDO $p = null;
    public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvpush;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public static function t(string $t): string { return $t; }
}
final class WebPush { public static array $sent = [];
    public static function sendToUser(PDO $p, int $u, array $m): array { self::$sent[] = ['u' => $u] + $m; return ['sent' => 1, 'failed' => 0, 'held' => 0]; } }

// the real WebPush.php is read for its checks, then stood in for while Notify runs, and put back at the end
$wpFile = $app . '/lib/WebPush.php'; $wp = (string) file_get_contents($wpFile);
file_put_contents($wpFile, "<?php\n// test stand-in (push-pace.php)\n");
register_shutdown_function(function () use ($wpFile, $wp) { file_put_contents($wpFile, $wp); });
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES) : '') . "\n"; } };

$pdo = Database::connection();
foreach (['comms_messages', 'comms_route', 'push_subs'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
foreach (['2027-03-13_comms.sql'] as $f) foreach (array_filter(array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', (string) file_get_contents($app . '/migrations/' . $f))))) as $q) { try { $pdo->exec($q); } catch (Throwable $e) {} }
require $app . '/lib/Notify.php';
$deliver = new ReflectionMethod('Notify', 'deliverPush'); $deliver->setAccessible(true);
$n = 0;
// a message as Notify::send writes it: once per person, kind and thing (comms_once), so the same alarm comes back as
// another kind (warning, trip, escalated, recovered), never as a second message of one kind
$msg = function (int $u, string $thing, string $lvl, string $title, string $kind = '') use ($pdo, &$n): int {
    $at = gmdate('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO comms_messages (kind, lvl, sender_type, to_user, thing, title, body, url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$kind !== '' ? $kind : 'test.k' . (++$n), $lvl, 'system', $u, $thing, $title, 'What happened.', '/admin/config/control', $at, $at]);
    return (int) $pdo->lastInsertId();
};
$push = function (int $mid) use ($deliver, $pdo) { $deliver->invoke(null, $pdo, $mid); return end(WebPush::$sent); };
$route = fn (int $mid) => (string) $pdo->query("SELECT detail FROM comms_route WHERE message_id = $mid AND via = 'push' ORDER BY id DESC LIMIT 1")->fetchColumn();

// ---- one notification per thing; it alerts again only when it gets worse ---------------------------------------------
$a = $push($msg(1, 'alarm:7', 'alert', 'W1 is held', 'alarm.warning'));
$t('a push carries a tag of its thing, not of the message', str_starts_with((string) $a['tag'], 't') && strlen((string) $a['tag']) === 13 && $a['renotify'] === false, $a);
$b = $push($msg(1, 'alarm:7', 'notice', 'W1 recovered', 'alarm.recovered'));
$t('…the same thing again, no worse (recovered): the same tag (it replaces the one there), and no second alert', $b['tag'] === $a['tag'] && $b['renotify'] === false, $b);
$pdo->exec('DELETE FROM comms_route');   // the pace below starts clear
$c = $push($msg(1, 'alarm:7', 'urgent', 'W1 tripped', 'alarm.trip'));
$t('…the level rises (notice to urgent): the same tag, and it alerts again', $c['tag'] === $a['tag'] && $c['renotify'] === true, $c);
$d = $push($msg(1, 'alarm:8', 'critical', 'Escalation: admin needed', 'alarm.escalated'));
$t('Critical (alarm.escalated) always alerts', $d['renotify'] === true && $d['level'] === 'critical', $d);
$e = $push($msg(2, 'alarm:7', 'alert', 'W1 is held', 'alarm.warning'));
$t('another person, the same thing: their own tag', $e['tag'] !== $a['tag']);

// ---- the pace: past 3 in 5 minutes, one quiet summary; Critical on its own; nothing lost ------------------------------
$pdo->exec('DELETE FROM comms_route'); WebPush::$sent = [];
$ids = []; foreach (['A', 'B', 'C'] as $i => $x) { $ids[] = $mid = $msg(3, 'alarm:2' . $i, 'alert', 'Alarm ' . $x); $push($mid); }
$t('the first three in five minutes go one by one', count(WebPush::$sent) === 3 && WebPush::$sent[2]['title'] === 'Alarm C');
$m4 = $msg(3, 'alarm:30', 'alert', 'Alarm D'); $s4 = $push($m4);
$t('the fourth becomes one summary: "1 new in your Pulse", the latest named, opening Pulse', $s4['tag'] === 'summary' && $s4['title'] === '1 new in your Pulse' && str_contains($s4['body'], 'Alarm D') && str_ends_with($s4['url'], '/user/dashboard.php#pulse') && $s4['renotify'] === false, $s4);
$m5 = $msg(3, 'alarm:31', 'urgent', 'Alarm E'); $s5 = $push($m5);
$t('…the next updates that same summary quietly ("2 new")', $s5['tag'] === 'summary' && $s5['title'] === '2 new in your Pulse' && $s5['renotify'] === false, $s5);
$t('…each folded message\'s route says it went in the summary (the Sent view stays true)', str_starts_with($route($m4), 'In a summary push (1 new in 5 minutes)') && str_starts_with($route($m5), 'In a summary push (2 new'));
$m6 = $msg(3, 'alarm:32', 'critical', 'Critical F'); $s6 = $push($m6);
$t('Critical still goes on its own, loud, during the pace', $s6['tag'] !== 'summary' && $s6['title'] === 'Critical F' && $s6['renotify'] === true, $s6);
$pdo->exec("UPDATE comms_route SET happened_at = '" . gmdate('Y-m-d H:i:s', time() - 600) . "'");
$s7 = $push($msg(3, 'alarm:33', 'alert', 'Alarm G'));
$t('once five minutes pass, pushes go one by one again', $s7['title'] === 'Alarm G' && $s7['tag'] !== 'summary');
$t('the pace is in one place (Notify::PUSH_PACE, PUSH_WINDOW)', Notify::PUSH_PACE === 3 && Notify::PUSH_WINDOW === 5);
$src = (string) file_get_contents($app . '/lib/Notify.php');
$t('the summary is sent only after quiet hours, Stop these and staging have had their say (deliverPush is reached only past them)', (bool) preg_match('/pausedFor\(\$pdo, \$kind, \'push\'\).*?Quiet hours.*?else self::deliverPush/s', $src) && str_contains($src, "'held', 'Held on staging'"));

// ---- the payload and the service worker -------------------------------------------------------------------------------
$t('the payload carries renotify, false unless the sender asks', str_contains($wp, "'renotify' => !empty(\$msg['renotify'])"));
$t('the test push asks no second alert (Test twice updates quietly)', (bool) preg_match("/function test\(.*?'tag' => 'push-test'(?:(?!renotify).)*?\]\);/s", $wp));
$sw = (string) file_get_contents($web . '/push-sw.js');
$t('the service worker alerts again only when asked', str_contains($sw, 'opts.renotify = !!d.renotify') && !str_contains($sw, 'opts.renotify = true'));
$t('…and its badge is the white star on clear, not the square tile', str_contains($sw, "badge: '/image/push-badge.png'"));
$img = function (string $f) use ($web): array { $s = @getimagesize($web . '/image/' . $f); $png = (string) @file_get_contents($web . '/image/' . $f, false, null, 0, 32); return [$s ? $s[0] : 0, $s ? $s[1] : 0, $png !== '' ? ord($png[25]) : -1]; };
[$w, $h, $ct] = $img('push-badge.png');
$t('push-badge.png is 96×96 with transparency (PNG colour type 6)', $w === 96 && $h === 96 && $ct === 6, [$w, $h, $ct]);

// ---- the app manifest ---------------------------------------------------------------------------------------------------
$mf = json_decode((string) @file_get_contents($web . '/manifest.json'), true) ?: [];
$t('manifest.json: the name, start_url, scope, standalone and the brand colour', ($mf['name'] ?? '') === 'Traversence' && ($mf['start_url'] ?? '') === '/' && ($mf['scope'] ?? '') === '/' && ($mf['display'] ?? '') === 'standalone' && ($mf['theme_color'] ?? '') === '#92400E');
$ok = true; $sizes = [];
foreach ((array) ($mf['icons'] ?? []) as $ic) { [$w, $h] = $img(basename((string) $ic['src'])); $sizes[] = $ic['sizes'] . ' ' . $ic['purpose']; $ok = $ok && $w . 'x' . $h === $ic['sizes']; }
$t('…its icons exist at their sizes: 192 and 512, and a maskable 512', $ok && in_array('192x192 any', $sizes, true) && in_array('512x512 any', $sizes, true) && in_array('512x512 maskable', $sizes, true), $sizes);
require_once $app . '/ui/favicon.php';
$t('every traversence.com page head links it (tv_favicon_links), with the theme colour', str_contains(tv_favicon_links(), '<link rel="manifest" href="/manifest.json">') && str_contains(tv_favicon_links(), 'name="theme-color" content="#92400E"'));
$t('…but not trvgo.us pages or a storefront (another site)', !str_contains(tv_favicon_links('https://traversence.com'), 'manifest') && !str_contains(tv_favicon_links('', '/x.png'), 'manifest'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
