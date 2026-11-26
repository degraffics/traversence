<?php
// decisions/0180 R2's test (swimlanes and locks) on a real MySQL or MariaDB. Each ticket's lane by who does the work (the
// appendix's reporting lines; the session manager's review, 2026-11-26): orders Customer Service, billing Accounting, both
// Customer Service; data, addresses, nations, privacy, the system's own pages and audits the IT Manager's; marketplace Sales;
// content Editorial; SEO Marketing; anything else the Project Manager's. A Resolution keeps its work's lane. One owner at a
// time (a person's ticket never taken, another role's hold lapsing after the hour). Hand-offs: a note, one lane at a time,
// only the lane's manager, an executive above it or the Founder. The migration only adds and runs twice; its three tickets
// once each. The decision tree's fingerprint is the same before and after (lanes are routing, never in ruleData).
// Run: php r2.php <R2's root>/app <R1's migration> <piece2 seed dir> <R1's root>/app   (it resets lv_main)
declare(strict_types=1);
[$app, $r1mig, $seed, $r1app] = array_map(fn ($x) => rtrim((string) $x, '/'), array_slice($argv, 1, 4) + ['', '', '', '']);
if (!is_dir($app . '/lib') || !is_file($r1mig) || !is_dir($seed) || !is_dir($r1app . '/lib')) { fwrite(STDERR, "usage: php r2.php <root>/app <R1 migration> <seed dir> <R1 root>/app\n"); exit(2); }
foreach (['Database', 'ControlLog', 'TeamRoster', 'TeamRunner', 'GaugeTasks', 'DbUpdates'] as $c) require_once $app . '/lib/' . $c . '.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");   // as live runs: not strict (a too-long why is cut, as R1's two were)
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4'); $pdo->exec('USE lv_main');
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
$run = function (string $file) use ($pdo) { foreach (DbUpdates::statements((string) file_get_contents($file)) as $q) $pdo->exec($q); };
ControlLog::ensure($pdo); GaugeTasks::ensure($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(8) NOT NULL DEFAULT 'off', share_pct TINYINT UNSIGNED NOT NULL DEFAULT 0, killed TINYINT UNSIGNED NOT NULL DEFAULT 0, note VARCHAR(255) NOT NULL DEFAULT '', updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL)");
foreach (['2026-11-25_team_roster.sql', '2026-11-25_team_roster_seed.sql', '2026-11-25_team_staff_views.sql'] as $f) $run($seed . '/' . $f);
$run($r1mig);
$t('R1 seeded: the seven managers on the Team', count(array_intersect(TeamRoster::LANES, array_keys(TeamRoster::names($pdo)))) === 7);

// the migration: add-only, twice
$mig = $app . '/migrations/2026-11-26_0180_r2_lanes.sql';
foreach ([1, 2] as $i) $run($mig);
$cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'lv_main' AND TABLE_NAME = 'control_gauge_tasks' AND COLUMN_NAME IN ('lane', 'handoff') ORDER BY COLUMN_NAME")->fetchAll(PDO::FETCH_COLUMN);
$t('run twice: lane and handoff added', $cols === ['handoff', 'lane'], $cols);
$sqlm = (string) file_get_contents($mig); $ups = preg_match_all('/^UPDATE .*$/m', $sqlm, $um);
$t('… its structure only grows (no DROP, RENAME, MODIFY, CHANGE or TRUNCATE; a failed match never reads as a pass)', preg_match('/\b(DROP\s+(TABLE|COLUMN|INDEX|KEY)|RENAME\s+(TABLE|COLUMN|TO)|TRUNCATE|MODIFY\s+COLUMN|CHANGE\s+COLUMN|DELETE\s+FROM)\b/i', $sqlm) === 0);
$t('… its only UPDATEs fill goal where it\'s empty (R1\'s two cut reasons, with its data_reason)', $ups === 2 && count(array_filter($um[0], fn ($l) => str_starts_with($l, 'UPDATE control_gauge_tasks SET goal = ') && str_ends_with($l, "AND (goal IS NULL OR goal = '');"))) === 2, $ups);
$seeded = $pdo->query("SELECT item FROM control_gauge_tasks WHERE gauge = 'area:backlog' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$g = $pdo->query("SELECT item, CHAR_LENGTH(why) w, CHAR_LENGTH(goal) g FROM control_gauge_tasks WHERE item LIKE 'Two 6:13 jobs%' OR item LIKE 'Before-and-after check: false blocks%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$t('R1\'s two long tickets: their why cut at 400 as on live, their full reason now in goal', count($g) === 2 && $g[0]['w'] == 400 && $g[0]['g'] > 700 && $g[1]['w'] == 400 && $g[1]['g'] > 1100, $g);
$pdo->exec("UPDATE control_gauge_tasks SET goal = 'A person\'s own goal' WHERE item LIKE 'Two 6:13 jobs%'"); $run($mig);
$t('… a goal someone wrote is never written over', $pdo->query("SELECT goal FROM control_gauge_tasks WHERE item LIKE 'Two 6:13 jobs%'")->fetchColumn() === "A person's own goal");
$t('its three tickets once each (the Scheduler bundle, no Undo after an acknowledgement, deadlock detection)', count($seeded) === count(array_unique($seeded)) && count(array_filter($seeded, fn ($x) => str_starts_with($x, 'The Scheduler bundle') || str_starts_with($x, 'No Undo after') || str_starts_with($x, 'Deadlock detection'))) === 3, $seeded);

// lanes
$add = function (array $f) use ($pdo): array { $f += ['what' => '', 'why' => '', 'gauge' => 'area:backlog', 'ticket' => 'work']; $pdo->prepare('INSERT INTO control_gauge_tasks (at, gauge, item, kind, what, why, ticket, parent_id, owner, owner_at) VALUES (UTC_TIMESTAMP(), ?, ?, \'fix\', ?, ?, ?, ?, ?, ?)')->execute([$f['gauge'], $f['what'], $f['what'], $f['why'], $f['ticket'], $f['parent_id'] ?? null, $f['owner'] ?? '', $f['owner_at'] ?? null]); return GaugeTasks::task($pdo, (int) $pdo->lastInsertId()); };
$L = fn (array $f) => TeamRoster::lane($pdo, $add($f));
$t('an order page (/order.php): Customer Service', $L(['what' => 'The order page at 390', 'why' => '/order.php squeezes its totals']) === 'customer-service-manager');
$t('work orders (app/lib/Orders.php): Customer Service', $L(['what' => 'Work order reminders', 'why' => 'In app/lib/Orders.php']) === 'customer-service-manager');
$t('billing (app/lib/Billing.php, subscriptions): Accounting', $L(['what' => 'A renewal posts twice', 'why' => 'app/lib/Billing.php charges a subscription']) === 'accounting-manager');
$t('an order\'s payment (both): Customer Service', $L(['what' => 'An order\'s payment', 'why' => 'app/lib/Orders.php and app/lib/QuickBooksPay.php']) === 'customer-service-manager');
$t('privacy (app/lib/Auth.php): the IT Manager', $L(['what' => 'Sign-in keeps a stale session', 'why' => 'app/lib/Auth.php']) === 'it-manager');
$t('a tribal nation\'s land (app/lib/Landmarks.php): the IT Manager', $L(['what' => 'A held name', 'why' => 'app/lib/Landmarks.php']) === 'it-manager');
$t('the system\'s own pages (/admin/config/releases): the IT Manager', $L(['what' => 'Releases list', 'why' => 'On /admin/config/releases']) === 'it-manager');
$t('a job (its gauge job:market_tick): the IT Manager', $L(['what' => 'Late', 'gauge' => 'job:market_tick']) === 'it-manager');
$t('an audit ticket: the IT Manager', $L(['what' => 'Found', 'ticket' => 'audit']) === 'it-manager');
$t('the marketplace (/market/listing.php): Sales', $L(['what' => 'Storefront', 'why' => '/market/listing.php']) === 'sales-director');
$t('SEO words: Marketing', $L(['what' => 'Taxonomy gaps in SEO titles']) === 'marketing-director');
$t('the FAQ (app/ui/faq.md): Editorial', $L(['what' => 'FAQ entry', 'why' => 'app/ui/faq.md']) === 'editorial-director');
$t('nothing named: the Project Manager', $L(['what' => 'Tidy the queue']) === 'project-manager');
$p = $add(['what' => 'A renewal posts twice', 'why' => 'app/lib/Billing.php']);
$t('a Resolution keeps its work\'s lane (Accounting)', $L(['what' => 'Decide the refund', 'ticket' => 'resolution', 'parent_id' => (int) $p['id']]) === 'accounting-manager');

// locks: one owner at a time
$pm = TeamRoster::bySlug($pdo, 'project-manager'); $sa = TeamRoster::bySlug($pdo, 'system-architect');
$t('a person\'s ticket is never a role\'s to work', !TeamRunner::mayWork(['owner' => 'Jason', 'owner_at' => gmdate('Y-m-d H:i:s', time() - 7200)], $pm));
$t('free: any role may take it', TeamRunner::mayWork(['owner' => '', 'owner_at' => null], $pm));
$t('its own: yes', TeamRunner::mayWork(['owner' => TeamRoster::actor($pm), 'owner_at' => gmdate('Y-m-d H:i:s')], $pm));
$t('another role\'s, within the hour: no (one owner at a time)', !TeamRunner::mayWork(['owner' => TeamRoster::actor($sa), 'owner_at' => gmdate('Y-m-d H:i:s', time() - 600)], $pm));
$t('another role\'s, over the hour: it lapses, so a stalled role never keeps it', TeamRunner::mayWork(['owner' => TeamRoster::actor($sa), 'owner_at' => gmdate('Y-m-d H:i:s', time() - 3700)], $pm));

// hand-offs
$tk = $add(['what' => 'A renewal posts twice', 'why' => 'app/lib/Billing.php', 'owner' => 'system · Finance, Tax & Billing Ops', 'owner_at' => gmdate('Y-m-d H:i:s')]); $id = (int) $tk['id'];
$try = function (callable $f) { try { return [true, $f()]; } catch (RuntimeException $x) { return [false, $x->getMessage()]; } };
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, $id, 'customer-service-manager', 'short', 'x', 'founder'));
$t('a note is required (a sentence)', !$ok && str_contains($m, 'a sentence'), $m);
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, $id, 'customer-service-manager', 'The order this renewal belongs to needs its page checked.', 'Sales Director', 'sales-director'));
$t('another lane\'s manager can\'t hand it off, and says who can', !$ok && str_contains($m, 'Accounting Manager') && str_contains($m, 'Founder'), $m);
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, $id, 'customer-service-manager', 'The order this renewal belongs to needs its page checked.', 'Chief Operating Officer', 'coo'));
$t('an executive not above that lane (the COO, over operations) can\'t', !$ok, $m);
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, $id, 'customer-service-manager', 'The order this renewal belongs to needs its page checked.', 'Chief Financial Officer', 'cfo'));
$t('the executive above it (the CFO) can', $ok && str_contains($m, 'Handed off to'), $m);
$after = GaugeTasks::task($pdo, $id);
$t('… now in Customer Service\'s lane, stored, with the note', TeamRoster::lane($pdo, $after) === 'customer-service-manager' && $after['lane'] === 'customer-service-manager' && str_contains((string) $after['handoff'], 'needs its page checked'));
$t('… the role\'s hold released, so the receiving lane takes it up', (string) $after['owner'] === '', $after['owner']);
$t('… logged', (int) $pdo->query("SELECT COUNT(*) FROM control_log WHERE what LIKE 'Task #" . $id . " handed off from %'")->fetchColumn() === 1);
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, $id, 'customer-service-manager', 'Again, the same lane it is in now.', 'x', 'founder'));
$t('to the lane it\'s in: says so, nothing written', $ok && str_starts_with($m, 'It\'s in'), $m);
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, $id, 'accounting-manager', 'Back for the books: the refund record needs preparing.', 'Customer Service Manager', 'customer-service-manager'));
$t('the holding lane\'s own manager can hand it on (one lane at a time)', $ok && GaugeTasks::task($pdo, $id)['lane'] === 'accounting-manager', $m);
$pt = $add(['what' => 'Mine', 'owner' => 'Jason']);
GaugeTasks::handOff($pdo, (int) $pt['id'], 'it-manager', 'For the engineering lane to look at, please.', 'Jason', 'founder');
$t('the Founder always can, and a person\'s ownership stays theirs', GaugeTasks::task($pdo, (int) $pt['id'])['owner'] === 'Jason' && GaugeTasks::task($pdo, (int) $pt['id'])['lane'] === 'it-manager');
[$ok, $m] = $try(fn () => GaugeTasks::handOff($pdo, (int) $pt['id'], 'nowhere', 'A lane that does not exist at all.', 'x', 'founder'));
$t('only one of the seven lanes', !$ok, $m);

// the decision tree's approval survives R2: lanes and locks are routing, never in ruleData
$fp = fn (string $a) => trim((string) shell_exec('php -r ' . escapeshellarg('require "' . $a . '/lib/TeamRunner.php"; echo hash("sha256", json_encode(TeamRunner::ruleData() + ["reach" => "backend"]));')));
$t('the tree\'s fingerprint is the same before and after R2', $fp($r1app) !== '' && $fp($r1app) === $fp($app), [$fp($r1app), $fp($app)]);
$t('… and lanes aren\'t in it', !str_contains(json_encode(TeamRunner::ruleData()), 'lane'));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
