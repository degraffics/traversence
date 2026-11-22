<?php
// For board-views.cjs and board-views.php: what the board's views draw from. A trip on W1 (opened as an Incident Report,
// then acknowledged: D5, it stays in the Open list), a warning, Site health switched on with two findings read (kept in
// page_cache, as SiteHealth::load keeps them, so nothing is fetched), and the tables those need. Never live.
// `php board-views-seed.php <root>/app`
$app = $argv[1]; require $app . '/lib/Database.php'; require $app . '/lib/ControlAlarms.php'; require $app . '/lib/ControlIncidents.php'; $pdo = Database::connection();
$pdo->exec('CREATE TABLE IF NOT EXISTS page_cache (cache_key VARCHAR(190) PRIMARY KEY, body MEDIUMTEXT, expires_at DATETIME)');
$pdo->exec('CREATE TABLE IF NOT EXISTS feature_switches (switch_key VARCHAR(60) PRIMARY KEY, state VARCHAR(10), share_pct INT DEFAULT 0, killed TINYINT DEFAULT 0, note VARCHAR(255) NULL, updated_at DATETIME NULL)');
$pdo->exec("REPLACE INTO feature_switches (switch_key, state) VALUES ('site_health', 'on')");
$pdo->exec('CREATE TABLE IF NOT EXISTS site_health_marks (finding_id VARCHAR(48) PRIMARY KEY, state VARCHAR(10), sig VARCHAR(64), note VARCHAR(255), by_user INT NULL, at DATETIME)');
$f = fn ($id, $title, $blocks, $sev) => ['id' => $id, 'sig' => 'sig' . $id, 'kind' => 'rendered', 'rule' => 'R07', 'title' => $title, 'detail' => 'The page answered 500 at 390px for a guest.', 'page' => '/get-local.php',
    'who' => ['guest'], 'paths' => ['p-find-a-cafe'], 'blocks' => $blocks, 'severity' => $sev, 'shot_url' => '', 'proposal' => '', 'proposal_url' => '', 'verdict' => '', 'decided' => false, 'first_seen' => '2026-11-21T10:00:00Z', 'last_seen' => '2026-11-22T10:00:00Z'];
$data = ['ok' => true, 'at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600), 'run_url' => 'https://github.com/degraffics/traversence/actions', 'error' => '',
    'findings' => [$f('f-aaaaaa01', 'A slow card list', 0, 'update'), $f('f-bbbbbb02', 'Get Local doesn\'t open for a guest', 2, 'alert')]];
$pdo->prepare('REPLACE INTO page_cache (cache_key, body, expires_at) VALUES (?, ?, ?)')->execute(['sitehealth:now', json_encode(['v' => $data]), date('Y-m-d H:i:s', time() + 3600)]);
ControlAlarms::raise($pdo, 'wf:W1:test', 'wf:W1', 'trip', 'crash', 'W1 Listings: its runs failed', 'Its work waits.', 'Check and start again.', 'TimeoutError after 600 s', [], false, false);
ControlAlarms::raise($pdo, 'svc:issue:92', 'svc:commoncrawl', 'warning', 'retry', 'data.commoncrawl.org: SlowDown, please reduce your request rate', 'It waits.', 'Nothing.', '503', [], false, false);
$id = (int) $pdo->query("SELECT id FROM control_alarms WHERE akey = 'wf:W1:test'")->fetchColumn();
$pdo->exec("UPDATE control_alarms SET tripped_at = COALESCE(tripped_at, UTC_TIMESTAMP()) WHERE id = $id");
$ir = ControlIncidents::forTrip($pdo, $id); ControlIncidents::radius($pdo, $ir, ['all of W1']);
ControlAlarms::ack($pdo, $id, 1, 'Jason');
echo "seeded (trip $id, IR $ir)\n";
