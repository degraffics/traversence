<?php
// decisions/0175 release 2b part B: Modules (the Service register). Its menu entry reads "Modules (Service register)", the
// note's own words; the Costs page is folded in whole at its top (#costs), its address leading there; Costs leaves the menu.
// Run: mariadb < ../release-b-tests/start-schema.sql && php modules.php <root>
declare(strict_types=1);
$root = $argv[1]; $app = $root . '/app';
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 300) : '') . "\n"; } };
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_costs (id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, wf VARCHAR(8) NOT NULL DEFAULT '', service VARCHAR(40) NOT NULL, units INT NOT NULL DEFAULT 0, usd_micros BIGINT NOT NULL DEFAULT 0)");
$man = (function () use ($app) { return require $app . '/admin/manifests.php'; })();
$t('the menu entry reads "Modules (Service register)"', ($man['config']['sections']['register']['label'] ?? '') === 'Modules (Service register)');
$t('Costs stays a section (drawn in its fold and on its own)', isset($man['config']['sections']['costs']));
$h = @tv_admin_mod_register($pdo);
$i = strpos($h, 'id="costs"');
$t('Modules opens with the Costs fold, before the first card', $i !== false && $i < (int) strpos($h, 'os-card'), $i);
$c = substr($h, (int) $i, 20000);
foreach (['Spent this month', 'Revenue last month', 'Community budget', 'By service', 'By workflow', 'The AI cap', 'opening budget'] as $w) $t('…with ' . $w, str_contains($c, $w));
$t('…opened by its address\'s #costs', str_contains($h, 'location.hash==="#costs"'));
$src = (string) file_get_contents($root . '/website/admin/router.php');
$t('/admin/config/costs leads to the Costs fold in Modules', str_contains($src, "const TV_ADMIN_FOLDED_INTO = ['config/costs' => 'config/register#costs'"));
$t('…not when drawn on its own (panel, embed)', str_contains($src, "if (isset(TV_ADMIN_FOLDED_INTO[\$mk . '/' . \$sk]) && !\$panel && empty(\$_GET['embed']))"));
$t('…and it leaves the sections menu', str_contains($src, "if (isset(TV_ADMIN_FOLDED_INTO[\$mk . '/' . \$k])) continue;"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
