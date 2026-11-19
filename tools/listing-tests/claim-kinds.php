<?php
// claim-kinds: Claims::kind, ::agency, ::domains and the domain check, on the same database as claim-kinds.cjs.
// Run from a site tree: php claim-kinds.php (it includes app/lib/Claims.php from APP, default ../../app).
declare(strict_types=1);
$app = getenv('APP') ?: __DIR__ . '/../../app';
$pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=' . (getenv('DB') ?: 'lv_main') . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require $app . '/lib/Claims.php';
$ok = 0; $bad = 0; $t = function ($n, $g, $w) use (&$ok, &$bad) { if ($g === $w) $ok++; else { $bad++; echo "FAIL $n\n  got:  " . var_export($g, true) . "\n  want: " . var_export($w, true) . "\n"; } };
$t('a state park is public', Claims::kind($pdo, 7101), 'public');
$t('a church is community', Claims::kind($pdo, 7102), 'community');
$t('a restaurant is a business', Claims::kind($pdo, 7003), 'business');
$a = Claims::agency($pdo, 7101);
$t('its agency, with the tree above it', $a['chain'] ?? null, 'State of Arizona › Arizona State Parks & Trails');
$t('the agency\'s domain', $a['domain'] ?? null, 'azstateparks.com');
$t('no Nexus: no agency', Claims::agency($pdo, 7103), null);
$t('a park with no website: its agency\'s domain confirms', Claims::domains($pdo, 7101, ''), ['azstateparks.com']);
$t('a park with its own site: both', Claims::domains($pdo, 7101, 'https://www.lymanlake.example'), ['lymanlake.example', 'azstateparks.com']);
$t('a business: only its own site', Claims::domains($pdo, 7003, 'https://example.org'), ['example.org']);
$t('a church: only its own site (no agency)', Claims::domains($pdo, 7102, ''), []);
$t('every kind has words and roles', count(array_intersect_key(Claims::KIND_WORDS, Claims::KIND_ROLES)), 4);
echo "$ok passed, $bad failed\n";
