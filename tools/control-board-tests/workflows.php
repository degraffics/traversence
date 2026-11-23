<?php
// decisions/0175 release 2b part C: Workflows. The admin's stage workflows (0109), then the crawler's W1–W14: each one's own
// page on the board linked (its steps, services, backups and task switches), its spend this month (Jason, 2026-11-20: "By
// workflow also on Workflows"), and the Workflow board whole; /admin/config/board opens it (#crawler) and leaves the menu.
// Run: mariadb < ../release-b-tests/start-schema.sql && php workflows.php <root>
declare(strict_types=1);
$root = $argv[1]; $app = $root . '/app';
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 300) : '') . "\n"; } };
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_costs (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, wf VARCHAR(8) NOT NULL DEFAULT '', service VARCHAR(40) NOT NULL, units INT NOT NULL DEFAULT 0, usd_micros BIGINT NOT NULL DEFAULT 0)");
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_run_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id INT NOT NULL DEFAULT 0, wf VARCHAR(8) NOT NULL DEFAULT '', task VARCHAR(40) NOT NULL, outcome VARCHAR(20) NOT NULL, why VARCHAR(255) NULL, at DATETIME NOT NULL, allotted_s INT NOT NULL DEFAULT 0, seconds INT NOT NULL DEFAULT 0, fetches INT NOT NULL DEFAULT 0)");
$pdo->exec("INSERT INTO crawl_costs (at, wf, service, units, usd_micros) VALUES (NOW(), 'W1', 'tavily', 3, 1500000)");
$man = (function () use ($app) { return require $app . '/admin/manifests.php'; })();
$t('Workflows draws the stage workflows, then the crawler\'s part', ($man['config']['sections']['workflows']['widgets'] ?? []) === [['html', 'tv_admin_mod_workflows'], ['html', 'tv_admin_mod_workflows_crawler']]);
$t('the Workflow board stays a section (drawn there, and on its own)', isset($man['config']['sections']['board']));
$h = @tv_admin_mod_workflows_crawler($pdo);
// Ruling (step3/workflows-page.md §7, approved by Jason 2026-11-22): W0–W14 are all listed now, and the list of page
// buttons is dropped ("Replaced by the gauge cards, each opening its own page"): each card opens its page in the side panel,
// which links the workflow's own page on the board in a drawer. Was: "W1–W14" and a row of W1…W14 buttons.
$t('the crawler\'s part, anchored #crawler', str_starts_with($h, '<section id="crawler"') && str_contains($h, 'The crawler\'s workflows (W0–W14)'));
$t('…each workflow\'s own page on the board, in the side panel', str_contains($h, 'href="/admin/config/control/wf-W1" data-peek-frame') && str_contains($h, 'href="/admin/config/control/wf-W12" data-peek-frame') && str_contains($h, 'data-wfp-open="wfp-W1"'));
$t('…its spend this month by workflow', str_contains($h, 'By workflow, this month') && str_contains($h, '$1.50'), mb_substr(strip_tags($h), 0, 400));
// Ruling (§7): the Workflow board's parts are kept on each workflow's own page (its settings, versions and task table);
// Start the system and the switch-on check moved to the board's Start-up. Its old address draws the same page.
$b = @tv_admin_mod_board($pdo);
$t('…and the Workflow board\'s address draws the same page', $b !== '' && str_contains($b, 'id="wfp-grid"') && str_contains($h, 'name="op_do" value="wf_save"'));
$src = (string) file_get_contents($root . '/website/admin/router.php');
$t('/admin/config/board opens it, and Costs\'s move is kept', str_contains($src, "const TV_ADMIN_FOLDED_INTO = ['config/costs' => 'config/register#costs', 'config/board' => 'config/workflows#crawler'];"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
