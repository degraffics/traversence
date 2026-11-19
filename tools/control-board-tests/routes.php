<?php
// control-board-routes (2026-11-19): Jason, "when something is wrong and I go to fix it I always hit a dead end. The card or
// section should always have a route to the specific issue." Every alarm the code raises (not a notice) carries a fix: an
// action done in place, or a link that lands on one item (an anchor, a query or an id), never a bare list to search.
// Also: the board's stage tiles route to their own parts, a landing hash opens its fold, the pre-flight line's own fix
// travels with its alarm, and the pre-flight's "Runs:" line says it only looks every 15 minutes.
// Run: php routes.php <root>/app   (static; no database needed)
declare(strict_types=1);
$app = rtrim($argv[1], '/'); $root = dirname($app);
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };

// 1. every ControlAlarms raise in the code: its arguments, read by the tokenizer
$calls = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $f = (string) $f; if (substr($f, -4) !== '.php') continue; $src = (string) file_get_contents($f); if (strpos($src, 'raise(') === false) continue;
    $tk = token_get_all($src); $c = count($tk); $inCA = basename($f) === 'ControlAlarms.php';
    for ($i = 2; $i < $c; $i++) {
        if (!is_array($tk[$i]) || $tk[$i][1] !== 'raise' || !is_array($tk[$i - 1]) || $tk[$i - 1][0] !== T_DOUBLE_COLON) continue;
        $cls = is_array($tk[$i - 2]) ? $tk[$i - 2][1] : '';
        if (!($cls === 'ControlAlarms' || ($cls === 'self' && $inCA))) continue;
        $j = $i + 1; while (is_array($tk[$j]) && $tk[$j][0] === T_WHITESPACE) $j++; if ($tk[$j] !== '(') continue;
        $d = 0; $args = ['']; for ($k = $j + 1; $k < $c; $k++) { $x = is_array($tk[$k]) ? $tk[$k][1] : $tk[$k];
            if (in_array($x, ['(', '[', '{'], true)) $d++; elseif (in_array($x, [')', ']', '}'], true)) { if ($d === 0) break; $d--; }
            if ($x === ',' && $d === 0) { $args[] = ''; continue; } $args[count($args) - 1] .= $x; }
        $calls[] = ['at' => basename($f) . ':' . $tk[$i][2], 'level' => trim($args[3] ?? ''), 'fix' => trim(preg_replace('/\s+/', ' ', $args[9] ?? ''))];
    }
}
$t('the scan finds the raises (' . count($calls) . ')', count($calls) >= 25, count($calls));
// fixes built elsewhere (a variable or a helper), each checked by its own test: the register's caps and "returns empty"
// (Operations, r3b), a service's issue (ServiceHealth, r3b/r4f), the pre-flight line's fix (below)
$built = ['$fx', '$fxc', '$afx'];
$single = ['/admin/config/releases'];   // a page that is the one thing to do (its Check for builds), not a list
foreach ($calls as $cl) {
    $notice = str_contains($cl['level'], "'notice'") && !str_contains($cl['level'], '?');
    if ($notice) continue;
    $fx = $cl['fix'];
    $ok = $fx !== '' && $fx !== '[]';
    if ($ok && $fx[0] === '$' ) $ok = in_array($fx, $built, true) || str_starts_with($fx, '$stop');
    if ($ok && str_starts_with($fx, '$stop')) $ok = str_contains($fx, 'rateFix') && str_contains($fx, 'Check it n');   // a refusal held: its own Mark resolved (svcresolve)
    $t('a fix on ' . $cl['at'], $ok, $fx);
    // each peek lands on one item: a hash, a query or an id after the path
    if (preg_match_all("/'peek',\s*'([^']*)'(\s*\.)?/", $fx, $m, PREG_SET_ORDER)) foreach ($m as $p) {
        $u = $p[1]; $spec = str_contains($u, '#') || str_contains($u, '?') || !empty($p[2]) || in_array($u, $single, true);
        $t('  its link lands on one item: ' . $u, $spec, $cl['at']);
    }
}

// 2. the specific routes named in the build
$ca = (string) file_get_contents($app . '/lib/ControlAlarms.php'); $cl = (string) file_get_contents($app . '/lib/ControlLedger.php');
$cs = (string) file_get_contents($app . '/lib/crawler/CrawlService.php'); $pf = (string) file_get_contents($app . '/lib/Preflight.php');
$t('a server error opens the failing call in the Ledger', str_contains($cl, "'/admin/config/ledger#open=config/ledger:' . \$id") && str_contains($ca, "'/admin/config/ledger#open=config/ledger:' . (int) \$r['id']"));
$t('a stopped, stuck or slow job opens its own job', substr_count($ca, "'/admin/config/scheduler#job-' . \$job") === 3);
$t('a quiet worker: Railway as a link only (never a restart from the site)', substr_count($ca, "'link', 'https://railway.com/dashboard', 'railway'") === 2 && !preg_match('/railway[^\n]{0,80}(redeploy|restart)\'\s*,\s*\'ops/i', $ca));
$t('a failed run, a starved run and a failed task open their run', substr_count($cs, "'/admin/insights/crawler?run=' . \$runId") >= 3);
$t('a failed task: Try it first on the next run, or Pause the task, in place', str_contains($cs, "'ops_runnext:'") && str_contains($ca, "'/^task:failed:[a-z0-9_]+\$/' => ['', '/^ops_(runnext|task_off):[a-z0-9_]+\$/']"));
$t('the pre-flight line\'s own fix travels with its alarm, and its line', str_contains($pf, "'link', (string) (\$f[2] ?? '')") && str_contains($pf, "'/admin/config/preflight#pf-' . str_replace(':', '-', \$id)"));
$t('a pre-flight alarm the watch refreshes gets its fix too', str_contains($pf, "UPDATE control_alarms SET fix = ? WHERE akey = ? AND cleared_at IS NULL"));
$t('an outside link in Pulse names its service (a new tab, nothing changed)', str_contains($ca, "elseif (\$k === 'link') \$out[] = \$s(\$l, 'Opens on their site"));

// 3. Supabase catching up: measured, held only when its task is off after Start or nothing came off it for a day
$t('Supabase catching up says its rate and when it\'s done', str_contains($pf, "'vectors:pf_track'") && str_contains($pf, "an hour, done in about"));
$t('…held only when its task is off after Start, or stalled a day', str_contains($pf, '$ok = $locked && !$stall && !$offAfterStart;') && str_contains($pf, '86400'));
$t('…its route opens the vector index\'s own switch', str_contains($pf, "/admin/config/ai#ai-vector_index") && str_contains((string) file_get_contents($app . '/admin/sections.php'), "'<div id=\"ai-' . tv_w_e(\$f) . '\">'"));
require_once $app . '/lib/Database.php'; require_once $app . '/lib/Preflight.php';
$m = new ReflectionMethod('Preflight', 'eta'); $m->setAccessible(true);
$t('its time to done reads as words', $m->invoke(null, 0.5) === '30 min' && $m->invoke(null, 3.2) === '3 h' && $m->invoke(null, 72.0) === '3 days', [$m->invoke(null, 0.5), $m->invoke(null, 3.2), $m->invoke(null, 72.0)]);

// 4. the board: stage tiles route, a landing hash opens its fold, one pre-flight
$cc = (string) file_get_contents($app . '/admin/control-console.php');
foreach (['#preflight' => 'id="preflight"', '#cb-controls' => 'id="cb-controls"', '#cb-alarms' => 'id="cb-alarms"', '#maintenance' => 'id="maintenance"', '#log' => 'id="log"'] as $h => $id)
    $t('a stage tile goes to ' . $h . ', which is on the board', str_contains($cc, "['" . $h . "', ") && str_contains($cc, $id));
$t('the stage tiles are links, each saying what its part is', str_contains($cc, '<a role="listitem" href="') && str_contains($cc, 'class="cb-stw"'));
$t('running with alarms open says so (words follow the state)', str_contains($cc, "alarm' . (\$nHeld === 1 ? '' : 's') . ' open"));
$t('Open the report goes to the board\'s own pre-flight, not a second copy', str_contains($cc, '<a class="ak-b cb-xs" href="#preflight">Open the report</a>') && !str_contains($cc, 'href="/admin/config/preflight" data-peek-frame>Open the report'));
$kit = (string) file_get_contents($root . '/website/js/admin-kit.js');
$t('a landing hash opens its details and folds, and outlines it', str_contains($kit, 'ak-landed') && str_contains($kit, "hashchange") && str_contains($kit, "aria-controls"));
$t('…but leaves #open= to the drawer', (bool) preg_match("/indexOf\('='\)|includes\('='\)/", $kit));
$jl = (string) file_get_contents($app . '/admin/job-line.php');
$t('the pre-flight\'s Runs line: it looks every 15 minutes, runs every test only at night, after a .env change or Run all', str_contains($jl, "runs every test only at 2 am Arizona time, after a .env change, or when you press Run all") && str_contains($jl, "last full run"));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
