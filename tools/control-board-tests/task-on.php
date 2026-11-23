<?php
// A service's task switched off, said and fixed on its card (Jason, 2026-11-19, on Geofabrik's card: "Not sure what it need
// to do?"; it read "Switched off. Last: failed (RuntimeError(… 503: b'<!DOCTYPE HTML …" with only Open): the card says the
// task is off first, with Switch on there (the Automation page's own switch, its permission and log); a failure from before
// is history, in words (a server's error page by its title, never its HTML); and the Automation page's row has an anchor.
// Run: mariadb < ../release-b-tests/start-schema.sql && php task-on.php <root>/app
declare(strict_types=1);
$app = $argv[1]; if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Auth.php'; require_once $app . '/lib/Database.php'; $pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . mb_substr(is_string($got) ? $got : json_encode($got), 0, 500) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/lib/crawler/CrawlService.php'; require_once $app . '/lib/Automation.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS crawl_run_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id INT NOT NULL, wf VARCHAR(8) NOT NULL, task VARCHAR(40) NOT NULL, outcome VARCHAR(20) NOT NULL, allotted_s INT NOT NULL DEFAULT 0, seconds INT NOT NULL DEFAULT 0, fetches INT NOT NULL DEFAULT 0, searches INT NOT NULL DEFAULT 0, counts TEXT NULL, why VARCHAR(500) NULL, at DATETIME NOT NULL)");
foreach (['last_outcome VARCHAR(20) DEFAULT NULL', 'last_why VARCHAR(255) DEFAULT NULL'] as $c) { try { $pdo->exec('ALTER TABLE crawl_capture_tasks ADD COLUMN ' . $c); } catch (Throwable $x) {} }
$why = "RuntimeError('site /api/crawl/loads.php said 503: b\\'<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\">\\\\n<html><head>\\\\n<title>503 Service Unavailable</title>\\\\n</head><body>\\\\n<h1>Service Unavailable</h1>\\\\n<p>The";
$pdo->exec("DELETE FROM crawl_capture_tasks WHERE task = 'geofabrik_bulk'");
$pdo->prepare("INSERT INTO crawl_capture_tasks (task, wf, mode, enabled, label, name, what, last_outcome, last_why, updated_at) VALUES ('geofabrik_bulk', 'W1', 'bulk', 0, 'Geofabrik', 'Geofabrik: OpenStreetMap by state', 'The state files, weekly.', 'failed', ?, NOW())")->execute([mb_substr($why, 0, 255)]);
// the card whole, to the next service's row: the four-zone card (control-board-plan step3/03-cards.md §1) put the register's
// parts first and made the card longer than the 9,000 characters this read before
$card = function () use ($pdo): string { $h = tv_admin_mod_register($pdo); $i = strpos($h, 'id="svc-geofabrik"'); if ($i === false) return ''; $j = strpos($h, 'class="os-card os-r"', $i + 20); return $j === false ? substr($h, $i) : substr($h, $i, $j - $i); };

$g = $card();
$line = preg_match('~<p data-tv-status="(\w+)" data-tv-label="W1 · Geofabrik: OpenStreetMap by state:">(.*?)</p>~s', $g, $m) ? $m : ['', '', ''];
$t('the task\'s line says it\'s switched off, first, and what that means', str_starts_with($line[2], 'Switched off: nothing runs it until it&#039;s switched on.'), $line[2]);
$t('…an amber notice, not a red failure (off is a choice, not a fault)', $line[1] === 'info', $line[1]);
$t('…its last run from before, in words: the error page by its title, no HTML', str_contains($line[2], 'Its last run, before that: failed (RuntimeError(&#039;site /api/crawl/loads.php said 503: 503 Service Unavailable)') && !str_contains($line[2], 'DOCTYPE') && !str_contains($line[2], '&lt;'), $line[2]);
$t('…and Switch on right there, asking a yes first', str_contains($line[2], 'name="svc_task_on" value="geofabrik_bulk"') && str_contains($line[2], 'data-confirm="Switch on W1 · Geofabrik: OpenStreetMap by state?') && str_contains($line[2], '>Switch on</button>'), $line[2]);
$t('…its other link opens its one switch, on its workflow\'s page, in the side panel (0175 §6)', str_contains($line[2], 'href="/admin/config/control/wf-W1#task-geofabrik_bulk" data-peek-frame>Its switch</a>'), $line[2]);
$t('OutsideServices::plainWhy leaves ordinary words alone', OutsideServices::plainWhy('timed out after 30 s') === 'timed out after 30 s');

// pressing it: the Automation page's own switch and log
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['csrf' => 'x', 'svc' => 'geofabrik', 'svc_task_on' => 'geofabrik_bulk'];
$g = $card(); $_POST = []; $_SERVER['REQUEST_METHOD'] = 'GET';
$t('Switch on switches the task on', (int) $pdo->query("SELECT enabled FROM crawl_capture_tasks WHERE task = 'geofabrik_bulk'")->fetchColumn() === 1);
$t('…logged as the Automation page logs it (the way back: Switch off there)', (int) $pdo->query("SELECT COUNT(*) FROM automation_log WHERE kind = 'task' AND ref = 'geofabrik_bulk' AND paused = 0")->fetchColumn() >= 1);
$g = $card();
$t('…and the card shows the last run again, no button', (bool) preg_match('~data-tv-label="W1 · Geofabrik: OpenStreetMap by state:">Last: failed~', $g) && !str_contains($g, 'svc_task_on'));
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['csrf' => 'x', 'svc' => 'geofabrik', 'svc_task_on' => 'overture_bulk'];
$pdo->exec("UPDATE crawl_capture_tasks SET enabled = 0 WHERE task = 'overture_bulk'"); $card(); $_POST = []; $_SERVER['REQUEST_METHOD'] = 'GET';
$t('a task that isn\'t the card\'s own is refused', (int) $pdo->query("SELECT COALESCE(MAX(enabled), 0) FROM crawl_capture_tasks WHERE task = 'overture_bulk'")->fetchColumn() === 0);

require_once $app . '/admin/automation-console.php';
$au = tv_admin_mod_automation($pdo);
$t('Automation\'s Tasks: one line per workflow, opening its page, and an old link to the task\'s row lands on its workflow\'s line', (bool) preg_match('~<li id="au-wf-W1">(<span id="au-task-[a-z0-9_]+"></span>)*<span id="au-task-geofabrik_bulk"></span>~', $au) && str_contains($au, 'href="/admin/config/control/wf-W1#cb-steps" data-peek-frame>Open W1</a>'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
