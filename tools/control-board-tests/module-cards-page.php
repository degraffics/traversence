<?php
// For module-cards.cjs: `php module-cards-page.php <root>/app seed` puts the scenario state in the bench database (a refused
// key on Anthropic, a 5xx at Tavily, the crawler's trip on Railway); `… page` draws the cards (Railway, Anthropic, Tavily,
// Bluehost, the video worker) on one page with the site's tokens, kit CSS and tv-ask.js. Bench only, never live.
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php'; require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/lib/ControlAlarms.php';
if (($argv[2] ?? '') === 'seed') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_issues (id INT AUTO_INCREMENT PRIMARY KEY, service VARCHAR(40), host VARCHAR(190), caller VARCHAR(40), kind VARCHAR(20), state VARCHAR(12), hits INT, first_at DATETIME, last_at DATETIME, evidence TEXT, hold_until DATETIME NULL, probe_url TEXT, probe_every_min INT, probe_agent VARCHAR(40), next_probe_at DATETIME NULL, resolved_at DATETIME NULL, resolved_by INT NULL, resolved_how VARCHAR(255) NULL, last_probe TEXT NULL)");
    $pdo->exec('DELETE FROM service_issues'); $pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);
    $pdo->exec("INSERT INTO service_issues (id, service, host, caller, kind, state, hits, first_at, last_at, evidence) VALUES
      (40, 'anthropic', 'api.anthropic.com', 'site', 'auth', 'open', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":401,\"error\":\"invalid x-api-key\"}}'),
      (41, 'tavily', 'api.tavily.com', 'crawler', 'server', 'open', 2, UTC_TIMESTAMP(), UTC_TIMESTAMP(), '{\"latest\":{\"status\":503,\"body\":\"Service Unavailable\"}}')");
    $pdo->exec("INSERT INTO control_alarms (akey, proc, level, kind, title, first_at, last_at, tripped_at, stopped, component) VALUES ('run:failed', 'wk:crawler', 'trip', 'held', 'The crawler is down', UTC_TIMESTAMP() - INTERVAL 40 MINUTE, UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 5 MINUTE, '[[\"pause\",\"master:all\",\"All gathering\"]]', 'wk:crawler')");
    echo "seeded\n"; exit;
}
putenv('CPANEL_HOST=box1234.bluehost.com');
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/module-cards.php';
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '<style>body{margin:0;padding:0 3px;background:var(--tv-canvas)}</style></head><body>';
foreach (['railway', 'anthropic', 'tavily', 'bluehost', 'wk:video-worker'] as $k) echo tv_module_card($pdo, $k, ['csrf' => 'x']);
echo '<script src="/js/tv-ask.js"></script><script src="/js/admin-kit.js"></script></body></html>';
