<?php
// tools/jurisdiction-tests/j1.php — J1 (2026-11-27): a region opens on its team-reviewed jurisdiction record (Jason, R8
// "advisory, not a mandate"; 0164 R8/R17 note), story only off nation land while a record is under review (Q8 "Show"),
// and HubOpening's R17 gate reading the reviewed record. On a real MariaDB: php j1.php <lib dir> <migration.sql>
// (<lib dir>: a copy of the live sync-app/lib with the build's Jurisdictions.php and HubOpening.php laid over). It drops and
// remakes its tables. Also: the reading rule (no review while a law is unread) and the landmark gate downstream of tier().
declare(strict_types=1);
[$lib, $mig] = [$argv[1] ?? '', $argv[2] ?? ''];
require_once $lib . '/Jurisdictions.php'; require_once $lib . '/HubOpening.php';
$pdo = new PDO('mysql:host=localhost;dbname=j1t;charset=utf8mb4', 't', 't', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ok = 0; $bad = 0;
$t = function (string $what, bool $pass) use (&$ok, &$bad) { $pass ? $ok++ : $bad++; echo ($pass ? 'ok   ' : 'FAIL ') . $what . "\n"; };
$throws = function (callable $f): string { try { $f(); return ''; } catch (Throwable $e) { return $e->getMessage(); } };
foreach (['jurisdictions', 'jurisdiction_laws', 'control_log'] as $x) $pdo->exec("DROP TABLE IF EXISTS $x");
// the live schema (live-read meta, 2026-11-27) and the live rows: US, US-AZ, US-NM drafts with their 9 laws
$pdo->exec("CREATE TABLE jurisdictions (code VARCHAR(24) NOT NULL PRIMARY KEY, level VARCHAR(10) NOT NULL, name VARCHAR(120) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'draft',
  reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, signed_by VARCHAR(160) NOT NULL DEFAULT '', signed_at DATE NULL, notes TEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE jurisdiction_laws (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(24) NOT NULL, name VARCHAR(190) NOT NULL, citation VARCHAR(190) NOT NULL DEFAULT '',
  protects VARCHAR(500) NOT NULL DEFAULT '', requires_us VARCHAR(500) NOT NULL DEFAULT '', effect VARCHAR(20) NOT NULL DEFAULT '', url VARCHAR(500) NOT NULL DEFAULT '', seed_key VARCHAR(40) NULL, added_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO jurisdictions (code, level, name, status) VALUES ('US','federal','United States (federal baseline)','draft'),('US-AZ','state','Arizona','draft'),('US-NM','state','New Mexico','draft')");
foreach ([['US', 'ARPA', '16 U.S.C. 470aa'], ['US', 'NHPA 304', '54 U.S.C. 307103'], ['US', 'NAGPRA', '25 U.S.C. 3001'], ['US', 'COPPA', '15 U.S.C. 6501'], ['US-AZ', 'Antiquities', 'A.R.S. 41-841'],
          ['US-AZ', 'Private land remains', 'A.R.S. 41-865'], ['US-AZ', 'Public records', 'A.R.S. 39-125'], ['US-NM', 'Cultural Properties Act', 'NMSA 18-6'], ['US-NM', 'Unmarked burials', 'NMSA 18-6-11.2']] as [$c, $n, $ci])
    $pdo->prepare('INSERT INTO jurisdiction_laws (code, name, citation, effect) VALUES (?, ?, ?, ?)')->execute([$c, $n, $ci, 'site_location']);
$run = function () use ($pdo, $mig) {   // as DbUpdates::autoRun: split on ';'
    $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($mig));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) $pdo->exec($q);
};
$run(); $run();   // safe to run twice
$cnt = fn (string $c) => (int) $pdo->query("SELECT COUNT(*) FROM jurisdiction_laws WHERE code = " . $pdo->quote($c))->fetchColumn();
$t('reviewed_as column added', (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jurisdictions' AND COLUMN_NAME = 'reviewed_as'")->fetchColumn());
$t('Colorado and Utah opened as drafts', $pdo->query("SELECT GROUP_CONCAT(code, ':', status ORDER BY code) FROM jurisdictions WHERE code IN ('US-CO','US-UT')")->fetchColumn() === 'US-CO:draft,US-UT:draft');
$t('laws after two runs: US 4, AZ 3, NM 3, CO 2, UT 3 (no duplicates)', [$cnt('US'), $cnt('US-AZ'), $cnt('US-NM'), $cnt('US-CO'), $cnt('US-UT')] === [4, 3, 3, 2, 3]);
$t('every new law says it is not yet read against its official text', (int) $pdo->query("SELECT COUNT(*) FROM jurisdiction_laws WHERE code IN ('US-CO','US-UT') OR citation LIKE '%18-6-11.1%'")->fetchColumn() === (int) $pdo->query("SELECT COUNT(*) FROM jurisdiction_laws WHERE requires_us LIKE '%not yet read against its official text%'")->fetchColumn());
$t('the migration reviewed nothing', (int) $pdo->query("SELECT COUNT(*) FROM jurisdictions WHERE status <> 'draft'")->fetchColumn() === 0);
// regions: AZ and NM open on OPENED while drafts; CO and UT closed
$t('AZ and NM stay open (OPENED, drafts)', Jurisdictions::regionOpen($pdo, 'AZ') && Jurisdictions::regionOpen($pdo, 'NM'));
$t('CO and UT closed while drafts', !Jurisdictions::regionOpen($pdo, 'CO') && !Jurisdictions::regionOpen($pdo, 'UT'));
$t('closedWhy names the team review, not an attorney', str_contains(Jurisdictions::closedWhy($pdo, 'CO'), 'reviewed by the team') && !str_contains(Jurisdictions::closedWhy($pdo, 'CO'), 'attorney'));
// Q8: story only off nation land while under review; on nation land restricted (unchanged until Q7's release)
$t('tier: sensitive off nation land in a draft state is story', Jurisdictions::tier($pdo, true, 'CO') === 'story' && Jurisdictions::tier($pdo, true, 'AZ') === 'story');
$t('tier: sensitive on nation land stays restricted', Jurisdictions::tier($pdo, true, 'AZ', 12) === 'restricted');
$t('tier: not sensitive is public', Jurisdictions::tier($pdo, false, 'UT') === 'public');
// review by a team role, no attorney
$role = 'Privacy & Decision 0066 Guards (the Data & Content session)';   // decisions/0180's role 25, in words
$t('review needs a person or a role', str_contains($throws(fn () => Jurisdictions::review($pdo, 'US-CO', 0)), 'Who reviewed it'));
// the reading rule (CEO's Tier 4, 2026-11-27): no review while a law is unread against its official text
$t('review refused while a law is unread, naming it', str_contains($m = $throws(fn () => Jurisdictions::review($pdo, 'US-CO', 0, '', '', $role)), 'read against the official text yet') && str_contains($m, 'Mark read') && Jurisdictions::get($pdo, 'US-CO')['status'] === 'draft');
$t('reviewing NM is refused too (its new burial-site law is unread)', $throws(fn () => Jurisdictions::review($pdo, 'US-NM', 7)) !== '');
$law = (int) $pdo->query("SELECT id FROM jurisdiction_laws WHERE code = 'US-CO' ORDER BY id LIMIT 1")->fetchColumn();
$t('Mark read needs the official link', str_contains($throws(fn () => Jurisdictions::readLaw($pdo, $law, 'not a link', 0, $role)), 'official text'));
foreach (['US-CO', 'US-UT'] as $c) foreach (Jurisdictions::get($pdo, $c)['laws'] as $l) if (Jurisdictions::unread($l)) Jurisdictions::readLaw($pdo, (int) $l['id'], 'https://leg.example.gov/' . $l['id'], 0, $role);
$l = $pdo->query("SELECT * FROM jurisdiction_laws WHERE id = $law")->fetch(PDO::FETCH_ASSOC);
$t('a law read: official link kept, the unread words gone, its rule kept, logged', $l['url'] === 'https://leg.example.gov/' . $law && !Jurisdictions::unread($l) && $l['requires_us'] !== '' && (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE '%read against its official text'")->fetchColumn() >= 3);
$t('reading it twice is refused with why', str_contains($throws(fn () => Jurisdictions::readLaw($pdo, $law, 'https://x.gov', 0, $role)), 'already read'));
$t('a role reviews Colorado with no attorney, every law read', $throws(fn () => Jurisdictions::review($pdo, 'US-CO', 0, '', '', $role)) === '');
$t('CO still closed until the federal record is reviewed too', !Jurisdictions::regionOpen($pdo, 'CO'));
Jurisdictions::review($pdo, 'US', 0, '', '', $role);
$t('CO opens on the team-reviewed records', Jurisdictions::regionOpen($pdo, 'CO'));
$j = Jurisdictions::get($pdo, 'US-CO');
$t('the reviewer shown is the role', Jurisdictions::reviewer($pdo, $j) === $role && $j['signed_by'] === '' && $j['signed_at'] === null);
$t('a person can still review, with an attorney named', $throws(fn () => Jurisdictions::review($pdo, 'US-UT', 7, 'A. Counsel', '2026-11-27')) === '' && Jurisdictions::get($pdo, 'US-UT')['signed_by'] === 'A. Counsel');
$t('the review is logged on the board\'s logbook', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE proc = 'jur:US-CO' AND what LIKE '%marked reviewed%'")->fetchColumn() === 1);
$t('a role given with its number and a date is shown in words, said once', Jurisdictions::reviewer($pdo, ['status' => 'reviewed', 'reviewed_as' => 'Privacy & Decision 0066 Guards (25), the Data & Content session, 2026-11-27']) === 'Privacy & Decision 0066 Guards, the Data & Content session');
// R17's gate (HubOpening::lawSigned) reads the reviewed record
$gate = new ReflectionMethod('HubOpening', 'lawSigned'); $gate->setAccessible(true);
$t('R17 gate: CO reviewed passes; NM (draft) does not', $gate->invoke(null, $pdo, 'CO') === true && $gate->invoke(null, $pdo, 'NM') === false);
// Undo the review: back to draft as if never reviewed
Jurisdictions::undoReview($pdo, 'US-CO', 0, $role);
$j = Jurisdictions::get($pdo, 'US-CO');
$t('Undo: CO back to draft, its reviewer cleared, closed again', $j['status'] === 'draft' && $j['reviewed_as'] === '' && $j['reviewed_at'] === null && !Jurisdictions::regionOpen($pdo, 'CO'));
$t('Undo of a draft is refused with why', str_contains($throws(fn () => Jurisdictions::undoReview($pdo, 'US-CO', 0, $role)), 'nothing to undo'));
$t('Undo is logged with who had reviewed it', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE proc = 'jur:US-CO' AND why LIKE 'was reviewed by Privacy%'")->fetchColumn() === 1);
// a changed record goes back to draft and loses its reviewer
Jurisdictions::review($pdo, 'US-CO', 0, '', '', $role);
Jurisdictions::addLaw($pdo, 'US-CO', ['name' => 'Colorado Privacy Act', 'effect' => 'privacy', 'citation' => 'C.R.S. 6-1-1301'], 7);
$j = Jurisdictions::get($pdo, 'US-CO');
$t('a law added sends the record back to draft, reviewer cleared', $j['status'] === 'draft' && $j['reviewed_as'] === '');
// downstream (CEO's Tier 4): whatever tier() returns, the landmark gate still holds a sensitive place no public source presents
if (is_file($lib . '/Landmarks.php')) {
    require_once $lib . '/Landmarks.php';
    $none = ['name' => 'Sacred Spring', 'wd_desc' => '', 'wiki_title' => '', 'extract' => '', 'nation' => '', 'fclass' => 'Spring'];
    $t('a sensitive place with no public source is held (Landmarks::decide), story tier or not', Jurisdictions::tier($pdo, true, 'CO') === 'story' && !Landmarks::publiclyKnown($none) && Landmarks::decide($none)[0] === 'waiting');
    $ruin = ['name' => 'Cliff Ruins', 'wd_desc' => '', 'wiki_title' => '', 'extract' => '', 'nation' => '', 'fclass' => 'Area'];
    $t('a ruin with no article and no visitor page is held too', Landmarks::decide($ruin)[0] === 'waiting');
    $t('on a nation\'s land, held even with an article', Landmarks::decide(['name' => 'Cliff Ruins', 'wiki_title' => 'Cliff Ruins', 'nation' => 'Zuni', 'fclass' => 'Area'])[0] === 'waiting');
} else $t('Landmarks.php beside the lib (copy the live one)', false);
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
