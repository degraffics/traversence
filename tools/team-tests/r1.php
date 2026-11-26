<?php
// decisions/0180 R1 (rename and roster) on a real MariaDB: Piece 2's roster seed (and Piece 3's Staff Views advocate), then
// R1's migration twice. The 32 roles all active, renamed in place (a name a person changed is kept), the 12 advocates
// A1–A12 active and views only, the roles 0180 replaced retired: never in all(), the counts, the filters or the routing,
// still named for a past run. The stored tier 3 is 5. A role on standby: its step goes up its reports-to line, past a
// retired one, to the Founder (null) at the top. Roll back (app/rollbacks/…, run by Releases::rollBack) puts the roster and
// the tiers back as Piece 2 left them, byte for byte.
// Run: php r1.php <R1's root>/app <piece2 seed dir> (the bench .env four folders above app/lib; it resets lv_main)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); $seed = rtrim($argv[2] ?? '', '/');
if (!is_dir($app . '/lib') || !is_dir($seed)) { fwrite(STDERR, "usage: php r1.php <root>/app <seed dir>\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/ControlLog.php'; require_once $app . '/lib/TeamRoster.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4'); $pdo->exec('USE lv_main');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$run = function (string $file) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($file)) as $q) $pdo->exec($q); };
ControlLog::ensure($pdo);
$pdo->exec("CREATE TABLE control_gauge_tasks (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL DEFAULT 'fix', what VARCHAR(250) NOT NULL DEFAULT '', tier TINYINT NULL, owner VARCHAR(80) NOT NULL DEFAULT '', undone_at DATETIME NULL, cleared_at DATETIME NULL)");
foreach (['2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_staff_views.sql'] as $f) $run($seed . '/' . $f);
$pdo->exec("UPDATE team_roster SET name = 'Our Orders Desk' WHERE slug = 'orders'");   // a person's own name for a role
$pdo->exec("INSERT INTO control_gauge_tasks (what, tier) VALUES ('a money finding', 3), ('a fix', 2), ('polish', 1), ('no tier', NULL)");
$dump = fn () => json_encode([$pdo->query('SELECT slug, name, department, kind, spec_no, reports_to, tier_max, status, sort FROM team_roster ORDER BY slug')->fetchAll(PDO::FETCH_ASSOC), $pdo->query('SELECT id, tier FROM control_gauge_tasks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]);
$before = $dump();
$t('Piece 2 and 3 seeded: 34 rows', (int) $pdo->query('SELECT COUNT(*) FROM team_roster')->fetchColumn() === 34);

// R1, twice
foreach ([1, 2] as $i) $run($app . '/migrations/2026-11-26_0180_tiers_roster.sql');
$all = TeamRoster::all($pdo); $by = array_column($all, null, 'slug'); $c = TeamRoster::counts($all);
$roles = array_filter($all, fn ($r) => $r['kind'] !== 'advocate'); $adv = array_filter($all, fn ($r) => $r['kind'] === 'advocate');
$t('run twice: the Team shows 44, the 32 roles and the 12 advocates', count($all) === 44 && count($roles) === 32 && count($adv) === 12, [count($all), count($roles), count($adv)]);
$t('the 32 are numbered 1–32 once each', array_map('intval', array_column($roles, 'spec_no')) == range(1, 32), array_column($roles, 'spec_no'));
$t('every role and advocate is active (Jason, 2026-11-26: "run it now")', $c['active'] === 44 && $c['standby'] === 0, $c);
$t('the advocates are A1–A12, views only', array_column($adv, 'spec_no') === array_map(fn ($i) => 'A' . $i, range(1, 12)) && !array_filter($adv, fn ($r) => $r['acts_level'] !== ''));
$t('the layers: 1 Founder, 6 executives, 7 managers, 18 specialists', array_count_values(array_column($roles, 'department')) == ['founder' => 1, 'executive' => 6, 'managers' => 7, 'specialists' => 18], array_count_values(array_column($roles, 'department')));
$t('renamed in place: Customer Support Specialists (#18), Build & QA Sessions (#21), Front-End Content Writers (#26)', ($by['member-support']['name'] ?? '') === 'Customer Support Specialists' && $by['member-support']['spec_no'] === '18' && $by['builder']['name'] === 'Build & QA Sessions' && $by['content-editor']['name'] === 'Front-End Content Writers');
$t('a name a person changed is kept (Our Orders Desk, #17)', ($by['orders']['name'] ?? '') === 'Our Orders Desk' && $by['orders']['spec_no'] === '17');
$t('new: the CEO reports to the Founder, the COO to the CEO, the Accounting Manager to the CFO', $by['ceo']['reports_to'] === 'founder' && $by['coo']['reports_to'] === 'ceo' && $by['accounting-manager']['reports_to'] === 'cfo');
$t('new roles have no permissions until the Founder gives them (0180 §2)', $by['ceo']['acts_level'] === '' && str_starts_with(TeamRoster::actsAs($by['ceo']), 'No permissions yet'));
$t('today\'s permissions unchanged (Project Manager manager · ops)', $by['project-manager']['acts_level'] === 'manager' && $by['project-manager']['acts_dept'] === 'ops');
$ret = $pdo->query("SELECT slug FROM team_roster WHERE status = 'inactive' ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
$t('retired: the six 0180 names (4, 5, 9, 14, 18, 20) and the two folded rows (QA, Senior Designer)', $ret === ['directory-curator', 'guide-auditor', 'historian', 'legal-risk', 'mission-auditor', 'print-vendor', 'qa-security', 'senior-designer'], $ret);
$t('… never in all() or the counts', !array_intersect($ret, array_keys($by)) && $c['all'] === 44);
$t('… still named for a past run (names())', (TeamRoster::names($pdo)['historian'] ?? '') === 'Historian');
$t('… and none can be made active or put on standby from the Team page', (function () use ($pdo) { $id = (int) $pdo->query("SELECT id FROM team_roster WHERE slug = 'historian'")->fetchColumn(); try { TeamRoster::setStatus($pdo, $id, 'active', 'test'); return false; } catch (RuntimeException $x) { return str_contains($x->getMessage(), 'replaced'); } })());
$t('Retired isn\'t offered for a role of the 32', (function () use ($pdo, $by) { try { TeamRoster::setStatus($pdo, (int) $by['ceo']['id'], 'inactive', 'test'); return false; } catch (RuntimeException $x) { return true; } })());
$tiers = $pdo->query('SELECT tier FROM control_gauge_tasks ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$t('the stored tier: 3 is 5 now, the others unchanged', $tiers == [5, 2, 1, null], $tiers);
$t('the Founder\'s highest tier is 5; executives 4, managers 3, specialists 2', (int) $by['founder']['tier_max'] === 5 && (int) $by['cfo']['tier_max'] === 4 && (int) $by['it-manager']['tier_max'] === 3 && (int) $by['schema-guards']['tier_max'] === 2);

// a role on standby: its step goes up its line
$d = TeamRoster::doer($pdo, 'system-architect'); $t('active: a step\'s own role does it', $d && $d[0]['slug'] === 'system-architect' && $d[1] === '');
$pdo->exec("UPDATE team_roster SET status = 'standby' WHERE slug IN ('system-architect', 'it-manager')");
$d = TeamRoster::doer($pdo, 'system-architect'); $t('the Architect and the IT Manager on standby: the CTO does it', $d && $d[0]['slug'] === 'cto' && $d[1] === 'System Architect', $d ? [$d[0]['slug'], $d[1]] : null);
$pdo->exec("UPDATE team_roster SET status = 'standby' WHERE slug IN ('cto', 'ceo')");
$t('… and with the CTO and CEO on standby too: it waits for the Founder (null), nothing dropped', TeamRoster::doer($pdo, 'system-architect') === null);
$pdo->exec("UPDATE team_roster SET reports_to = 'historian' WHERE slug = 'translator'");
$pdo->exec("UPDATE team_roster SET status = 'standby' WHERE slug = 'translator'");
$d = TeamRoster::doer($pdo, 'translator'); $t('a retired role on the line is passed over (Historian → Editorial Director)', $d && $d[0]['slug'] === 'editorial-director', $d ? $d[0]['slug'] : null);
$c2 = TeamRoster::counts(TeamRoster::all($pdo)); $t('standby counted, retired still not', $c2['standby'] === 5 && $c2['all'] === 44, $c2);
$pdo->exec("UPDATE team_roster SET status = 'active', reports_to = 'editorial-director' WHERE slug IN ('system-architect', 'it-manager', 'cto', 'ceo', 'translator')");

// Roll back
$run($app . '/rollbacks/2026-11-26_0180_tiers_roster.sql');
$after = $dump(); if ($after !== $before) { $a = json_decode($after, true); $b = json_decode($before, true); foreach ($b[0] as $i => $row) if (($a[0][$i] ?? null) !== $row) echo '   differs: ' . json_encode([$row, $a[0][$i] ?? null]) . "\n"; }
$t('Roll back: the roster and the tiers exactly as Piece 2 left them', $after === $before, null);
$run($app . '/rollbacks/2026-11-26_0180_tiers_roster.sql');
$t('… run twice, still the same', $dump() === $before);
$t('… the rollback file never runs as a migration (it isn\'t in migrations/)', !is_file($app . '/migrations/' . basename($app . '/rollbacks/2026-11-26_0180_tiers_roster.sql')) || file_get_contents($app . '/migrations/2026-11-26_0180_tiers_roster.sql') !== file_get_contents($app . '/rollbacks/2026-11-26_0180_tiers_roster.sql'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
