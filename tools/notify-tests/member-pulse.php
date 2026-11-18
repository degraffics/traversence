<?php
// decisions/0172's last two items, on a real MySQL or MariaDB: MemberPulse::stream with what needs you as rows, and a member's own listing messages in their Pulse
// Run: mariadb -e 'CREATE DATABASE tvmp'; php member-pulse.php <a copy of app/ with the release laid over it>
declare(strict_types=1);
$app = $argv[1];
final class Database { private static ?PDO $p = null; public static function connection(): PDO { return self::$p ??= new PDO('mysql:host=localhost;dbname=tvmp;charset=utf8mb4', 'tv', 'tv', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } public static function t(string $t): string { return $t; } }
final class Standing { public static function current(PDO $p, int $u): ?array { return ['status' => 'open', 'step' => 1]; } }
final class ListingMessages { public static function forMember(PDO $p, int $u): array { return [['listing' => 'Kayenta Trading Post', 'state_label' => 'Waiting: not delivered', 'last' => 'Do you sell rugs?', 'url' => '/listing/view.php?id=5', 'last_at' => '2026-11-18 10:00:00', 'created_at' => '2026-11-18 10:00:00']]; } }
foreach (['Database', 'Standing', 'ListingMessages'] as $c) file_put_contents($app . '/lib/' . $c . '.php', "<?php\n// stand-in\n");
require $app . '/lib/MemberPulse.php';
$pdo = Database::connection();
$items = MemberPulse::stream($pdo, 4, ['kind' => 'member', 'scope' => []]);
$needs = array_values(array_filter($items, fn ($i) => $i['source'] === 'needs'));
$lm = array_values(array_filter($items, fn ($i) => $i['title'] === 'Kayenta Trading Post'));
$ok = 0; $bad = 0; $t = function ($n, $c, $g = null) use (&$ok, &$bad) { if ($c) { $ok++; echo "  ok  $n\n"; } else { $bad++; echo "FAIL  $n " . json_encode($g) . "\n"; } };
$t('a notice on the account is a row under Needs you, Critical', $needs && $needs[0]['level'] === 'critical' && $needs[0]['url'] === '/user/notice.php', $needs);
$t('…with an item name the marks accept', (bool) preg_match('/^needs:[a-f0-9]{12}$/', (string) ($needs[0]['item'] ?? '')));
$t('a message the member left on an unclaimed listing shows in their Pulse, marked where it stands', $lm && $lm[0]['source'] === 'messages' && str_starts_with($lm[0]['body'], 'Waiting: not delivered'), $lm);
$t('Needs you is the first source', MemberPulse::sources(['kind' => 'member'])[0] === 'needs' && isset(MemberPulse::SOURCES['needs']));
echo "\n$ok passed, $bad failed\n";
