<?php
// app/admin/automation-console.php — System Configuration → Automation (Jason, 2026-11-14; decisions/0163 §9): the master
// switch over everything that gathers or creates content, each workflow W1–W13, each capture task, and each outside
// service, with who set each switch, when and why. The rule (app/lib/Automation.php): a call happens only when the master,
// its workflow, its task and its service are all on. A paused thing shows as paused, never as a failure.
declare(strict_types=1);

function tv_admin_mod_automation(PDO $pdo): string
{
    require_once __DIR__ . '/../lib/Automation.php';
    require_once __DIR__ . '/../lib/OutsideServices.php';
    require_once __DIR__ . '/../lib/Scheduler.php';
    require_once __DIR__ . '/../lib/AiClient.php';
    require_once __DIR__ . '/../lib/crawler/CrawlService.php';
    require_once __DIR__ . '/../lib/Operations.php';
    require_once __DIR__ . '/../lib/SystemStart.php';
    $e = 'tv_w_e';
    // the switch-on check (decisions/0163 §10): a red line keeps its part paused; the master waits for every line
    $gate = function (string $scope, string $what) use ($pdo): void {
        $red = Operations::blocking($pdo, $scope);
        if ($red) throw new RuntimeException($what . ' stays off: the switch-on check has ' . count($red) . ' red line' . (count($red) === 1 ? '' : 's') . ($scope === 'all' ? '' : ' for it') . ', first "' . $red[0]['line'] . '" (' . $red[0]['why'] . '). See Check before switching on, on the Workflow board.');
    };
    [$can, $why] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
    $me = Access::staff(); $uid = (int) (Auth::currentUserId() ?? 0);
    $who = trim((string) ($me['name'] ?? ''));
    if ($who === '' && $uid) { try { $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$uid]); $who = (string) ($st->fetchColumn() ?: ''); } catch (Throwable $x) {} }
    $csrf = (string) ($_SESSION['csrf_token'] ?? ''); $msg = '';
    if (!Automation::ready($pdo)) return '<p data-tv-status="info" data-tv-label="Paused:">Everything that gathers or creates content is paused until the switches\' database update runs (within the hour, or open System Configuration → Database updates). Then the switches show here.</p>';

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['au_do']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            if (!$can) throw new RuntimeException($why);
            $do = (string) $_POST['au_do']; $ref = (string) ($_POST['ref'] ?? ''); $w = (string) ($_POST['why'] ?? '');
            switch ($do) {
                case 'start': $p = SystemStart::start($pdo, $uid, $who); $say = 'Start the system is under way: ' . ($p['state'] === 'done' ? 'everything is on.' : 'it carries on by itself on the server; anything only you can fix is in Pulse, "Starting the system".'); break;
                case 'pause_all': $say = SystemStart::pauseAll($pdo, $uid, $who); break;
                // on a step of Start the system (Jason, 2026-11-17: fixes inline with the start): look again now, or do its fix
                case 'start_tick': $say = SystemStart::pulseDo($pdo, 'ops_start_tick', $uid, $who); break;
                case 'start_do': if (!in_array($ref, ['ops_start_junk', 'ops_start_go', 'ops_start_skip'], true)) throw new RuntimeException('Unknown step.'); $say = SystemStart::pulseDo($pdo, $ref, $uid, $who); break;
                case 'start_hub_terms':   // a hub's source's terms read, on Start's card (Jason, 2026-11-18: fixes done on the page)
                    require_once __DIR__ . '/../lib/HubOpening.php'; require_once __DIR__ . '/Access.php';
                    [$hok, $hwhy] = Access::can('hubs.open', ['min' => 'director', 'depts' => []]);
                    if (!$hok) throw new RuntimeException($hwhy . '.');
                    $say = HubOpening::pulseDo($pdo, 'hub_terms:' . (int) $ref, [], $uid); SystemStart::tick($pdo); break;
                case 'start_issue_check': require_once __DIR__ . '/../lib/ServiceHealth.php'; $say = ServiceHealth::probeSoon($pdo, (int) $ref) ? 'Issue #' . (int) $ref . ' is checked again with the next call; then Check again carries on if it\'s clear.' : 'Issue #' . (int) $ref . ' has nothing to check now (it may be resolved already).'; SystemStart::tick($pdo); break;
                case 'start_issue_restart':   // its tripped alarm: Check and start again (the board's resolve: checks, closes the trip and its issue, starts again what it stopped)
                    require_once __DIR__ . '/../lib/ControlAlarms.php';
                    [$rdone, $rmsg] = ControlAlarms::resolve($pdo, (int) $ref, $uid, $who, 'Checked and started again on Start the system');
                    if (!$rdone) throw new RuntimeException($rmsg);
                    $say = $rmsg; SystemStart::tick($pdo); break;
                case 'start_issue_resolve': require_once __DIR__ . '/../lib/ServiceHealth.php'; $say = ServiceHealth::resolve($pdo, (int) $ref, $uid, 'Fixed by ' . $who . ' on Start the system: its calls start again, and the next call is its check') ? 'Issue #' . (int) $ref . ': its calls start again, and the next call is its check (a new failure opens a new issue).' : 'Issue #' . (int) $ref . ' was resolved already.'; SystemStart::tick($pdo); break;
                case 'master_off': Automation::set($pdo, 'master', 'all', true, $w, $uid, $who); $say = 'Automation is paused: nothing gathers or creates content until it\'s switched on.'; break;
                case 'master_on': $gate('all', 'Automation'); Automation::set($pdo, 'master', 'all', false, $w, $uid, $who); $say = 'Automation is on: each workflow, task and service runs by its own switch.'; break;
                case 'wf_off': Automation::setWorkflow($pdo, $ref, true, $w, $uid, $who); $say = $ref . ' is paused.'; break;
                case 'wf_on': $gate($ref, $ref); Automation::setWorkflow($pdo, $ref, false, $w, $uid, $who); $say = $ref . ' runs again' . (Automation::master($pdo) ? ' once automation is on.' : '.'); break;
                case 'task_off': Automation::setTask($pdo, $ref, false, $w, $uid, $who); $say = 'Switched off: no run takes it until it\'s on again.'; break;
                case 'task_on': Automation::setTask($pdo, $ref, true, $w, $uid, $who); $say = 'Switched on: it\'s planned from the next run.'; break;
                case 'svc_off': Automation::set($pdo, 'service', $ref, true, $w, $uid, $who); $say = (OutsideServices::COMPANIES[$ref][0] ?? $ref) . ' is paused: nothing calls it until it\'s resumed.'; break;
                case 'svc_on': $gate($ref, OutsideServices::COMPANIES[$ref][0] ?? $ref); Automation::set($pdo, 'service', $ref, false, $w, $uid, $who); $say = (OutsideServices::COMPANIES[$ref][0] ?? $ref) . ' can be called again.'; break;
                default: throw new RuntimeException('Unknown action.');
            }
            $msg = '<p data-tv-flash="ok">' . $e($say) . '</p>';
            SystemStart::$said = [true, $say];   // shown on the Start card too, where the press was
        } catch (Throwable $x) { $msg = '<p data-tv-flash="error">' . $e($x instanceof RuntimeException || $x instanceof InvalidArgumentException ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>'; SystemStart::$said = [false, $x instanceof RuntimeException || $x instanceof InvalidArgumentException ? $x->getMessage() : 'That didn\'t work: try again.']; if (!($x instanceof RuntimeException || $x instanceof InvalidArgumentException)) error_log('automation: ' . $x->getMessage()); }
        Automation::forget();
    }
    $dis = $can ? '' : ' disabled title="' . $e($why) . '"';
    // one switch: a form with its reason (asked when pausing, optional when switching on) and a button that waits for a yes
    $sw = function (string $do, string $ref, string $label, bool $pausing, string $confirm) use ($e, $csrf, $dis): string {
        return '<form method="post" class="au-f"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="au_do" value="' . $e($do) . '"><input type="hidden" name="ref" value="' . $e($ref) . '">'
            . '<input class="ak-in" type="text" name="why" maxlength="255" data-spell placeholder="' . ($pausing ? 'Why (shown with it)' : 'Why (optional, for the log)') . '"' . ($pausing ? ' required' : '') . $dis . '>'
            . '<button class="ak-b' . ($pausing ? '' : ' go') . '" type="submit" data-confirm="' . $e($confirm) . '" data-yes="' . $e($label) . '"' . ($pausing ? ' data-danger' : '') . $dis . '>' . $e($label) . '</button></form>';
    };
    $state = fn (string $tone, string $label, string $text) => '<p data-tv-status="' . $tone . '" data-tv-label="' . $e($label) . '">' . $e($text) . '</p>';
    $info = fn (string $title, string $html) => '<details class="au-i"><summary aria-label="' . $e($title) . '" title="' . $e($title) . '">ⓘ</summary><div>' . $html . '</div></details>';

    $master = Automation::master($pdo);
    $h = $msg . '<p class="ak-note">One place to pause what the site and the crawler gather and create, and to start it again. ' . ($can ? '' : '<b>' . $e($why) . '</b>') . '</p>';
    $h .= $info('How the switches fit together', '<p><b>Four layers, one rule:</b> a call happens only when the master switch, its workflow, its task and its service are all on.</p><ol>'
        . '<li><b>Workflow</b> (W1–W13, decisions/0160): a job of work start to finish, such as Listings or Guides on demand. It runs on the crawler, on the site, or both.</li>'
        . '<li><b>Task</b>: one piece of a workflow\'s work, such as the crawler\'s coverage task, a scheduled job on the site, or an AI feature.</li>'
        . '<li><b>Route</b>: a task\'s use of one outside service. AI use routes each feature to its providers (a guide draft to Claude, a menu to Google Document AI, then AWS, then Claude); the crawler\'s coverage task routes to OpenStreetMap and Wikidata. Pausing a service closes every route to it.</li>'
        . '<li><b>Service</b>: the outside company or registry called (the Service register has its card). A service paused here is a status, like a held one: callers skip it and say "paused", never "failed".</li></ol>'
        . '<p><b>The master switch never stops business operations:</b> orders, billing and payments, email, releases and staging, Moments, members\' and businesses\' own actions (a card scan, Write it for me), the admin itself, guides\' weather and roads. Staff pressing a button for one thing (Write a draft, Check my facts) still works; what a workflow gathers on its own waits.</p>'
        . '<p><b>Visitors</b> never meet a dead end: while guides on demand are paused, a search shows what we already list, and asking for a guide keeps the ask (built when it\'s back on, most asked first) with Notify me.</p>');

    // what's paused now, by the system or a person, and why, each opening its fix: the board's top line, here too (0175 §6;
    // release 2a, item 4: it replaces Start's "Now: Everything is on", untrue while a task is off)
    require_once __DIR__ . '/../lib/ControlBoard.php';
    $h .= '<div class="ak-card tv-bleed au-m">' . ControlBoard::pausedHtml($pdo, 'au-paused') . '</div>';
    // Start the system (Jason, 2026-11-15): the whole switch-on in one press, carried on by the server
    $h .= SystemStart::panel($pdo, $csrf, $can, (string) $why);
    // the switch-on check, in short (the lines are on the Workflow board)
    $chk = Operations::switchOnCheck($pdo); $red = array_values(array_filter($chk, fn ($l) => !$l['ok']));
    // what each red line holds, as it is now (2026-11-18: it said "Switch automation on is refused … until Release B" while
    // the master was on and Release B long shipped): a line for everything holds the master; any other holds only its part
    $redAll = array_values(array_filter($red, fn ($l) => $l['scope'] === 'all'));
    $holds = fn ($l) => $l['scope'] === 'all' ? 'holds everything' : (in_array($l['scope'], ['keys', 'schedule'], true) ? 'Start sees to it' : 'holds only ' . $l['scope']);
    $see = '<p class="ak-note"><a class="underline" href="/admin/config/board#check">See each line and its fix</a></p>';
    // "Check before switching on" only before Start or while the master is off; once the system is on, the same lines are its
    // diagnostics (0175's note: "once something is active it is not longer preflight, it is diagnostics")
    $before = $master || !in_array((string) (SystemStart::state($pdo)['state'] ?? 'idle'), ['done', 'running'], true);
    $ckL = $before ? 'Check before switching on:' : 'Diagnostics:';
    if (!$red) $h .= $state('ok', $ckL, 'All ' . count($chk) . ' lines green.');
    elseif ($redAll) $h .= $state('bad', $ckL, count($redAll) . ' of ' . count($chk) . ' lines hold everything' . ($master ? ', so the master switch stays off until they\'re green' : ': the master is on, but a new Start waits for them') . '. First: ' . $redAll[0]['line'] . ' (' . rtrim((string) $redAll[0]['why'], '.') . ').') . $see;
    else $h .= $state('info', $ckL, count($red) . ' of ' . count($chk) . ' lines red, each holding only its own part; everything else runs. ' . implode('; ', array_map(fn ($l) => $l['line'] . ' (' . $holds($l) . ')', array_slice($red, 0, 3))) . (count($red) > 3 ? '; and ' . (count($red) - 3) . ' more' : '') . '.') . $see;
    // the master switch
    if ($master) $h .= $state('info', 'Paused:', Automation::line($master, 'Automation is paused') . '. Nothing gathers or creates content: the crawler\'s runs end at once, the site\'s workflow jobs skip, their AI features and outside calls wait.');
    else $h .= $state('ok', 'On:', 'Automation runs, each workflow, task and service by its own switch below.' . (($r = Automation::masterRow($pdo)) && !empty($r['at']) ? ' Last switched on ' . Automation::line($r, '') . '.' : ''));
    $h .= '<div class="ak-card tv-bleed au-m"><h3 class="ak-h" style="margin-top:0">Master switch: ' . ($master ? 'paused' : 'on') . '</h3>'
        . ($master ? ($red && $can ? str_replace('<button ', '<button disabled title="' . $e('The switch-on check has ' . count($red) . ' red lines: ' . $red[0]['line']) . '" ', $sw('master_on', 'all', 'Switch automation on', false, 'Switch automation on? Every workflow, task and service that\'s on starts again (the crawler from its next run, if Railway\'s schedule is set).')) : $sw('master_on', 'all', 'Switch automation on', false, 'Switch automation on? Every workflow, task and service that\'s on starts again (the crawler from its next run, if Railway\'s schedule is set).'))
                   : $sw('master_off', 'all', 'Pause all automation', true, 'Pause everything that gathers or creates content? Business operations carry on.'))
        . '<p class="ak-note">Stops: the crawler on Railway (each run ends at once, even if its schedule comes back); the site\'s jobs ' . $e(implode(', ', array_map(fn ($j) => Scheduler::JOBS[$j][0] ?? $j, array_keys(Automation::JOBS)))) . '; the AI features that serve workflows; and their outside calls.</p></div>';

    // what serves each workflow: its jobs, its AI features, its tasks
    $tasks = CrawlService::ready($pdo) ? CrawlService::tasks($pdo) : [];
    $wfs = Automation::workflows($pdo);
    $feat = []; foreach (CrawlService::FEATURE_WF as $f => $wf) if (!in_array($f, Automation::PERSON_FEATURES, true)) $feat[$wf][] = AiClient::FEATURES[$f][0] ?? AiClient::SWITCHES[$f][0] ?? $f;
    $jobs = []; foreach (Automation::JOBS as $j => $ws) foreach ($ws as $wf) $jobs[$wf][] = Scheduler::JOBS[$j][0] ?? $j;
    $svcTasks = []; foreach (OutsideServices::CONTROLS as $svc => $c) foreach ((array) ($c['tasks'] ?? []) as $t) $svcTasks[$t][] = OutsideServices::COMPANIES[$svc][0] ?? $svc;

    $wbody = '<p class="ak-note">Pause one workflow and its crawler tasks, its jobs on the site, its AI features and its on-demand paths stop; the rest carry on.</p><div class="ak-aa"><table><thead><tr><th>Workflow</th><th>Where</th><th>What serves it</th><th>State</th><th></th></tr></thead><tbody>';
    foreach (Automation::WORKFLOWS as $wf => [$name, $what, $where]) {
        $r = $wfs[$wf] ?? null; $p = $r && (int) $r['paused'];
        $mine = array_filter($tasks, fn ($t) => $t['wf'] === $wf);
        $serves = array_filter(['Crawler tasks: ' . implode(', ', array_map(fn ($t) => (string) $t['name'], $mine)) => (bool) $mine, 'Jobs: ' . implode(', ', $jobs[$wf] ?? []) => isset($jobs[$wf]), 'AI: ' . implode(', ', $feat[$wf] ?? []) => isset($feat[$wf])]);
        $st = !$r ? ['info', 'Not set up yet (the database update adds it)'] : ($p ? ['info', 'Paused: ' . Automation::line(['why' => $r['paused_why'] ?? '', 'by_user' => $r['by_user'] ?? ''], 'paused') . (!empty($r['paused_at']) ? ', since ' . date('M j', (int) strtotime((string) $r['paused_at'])) : '')]
              : ($master ? ['info', 'On, held by the master switch'] : (($sp = Automation::sysOff($pdo, 'wf', $wf) ?? Automation::sysOff($pdo, 'master', 'all')) !== null ? ['info', 'On · ' . $sp] : ['ok', 'Running'])));
        $act = !$r || $wf === 'W13' ? '' : ($p ? $sw('wf_on', $wf, 'Resume ' . $wf, false, 'Resume ' . $wf . ' ' . $name . '?') : $sw('wf_off', $wf, 'Pause ' . $wf, true, 'Pause ' . $wf . ' ' . $name . '? Its work waits, untouched.'));
        $wbody .= '<tr><td><b>' . $e($wf . ' ' . $name) . '</b><br><small>' . $e($what) . '</small></td><td>' . $e($where) . '</td><td><small>' . ($serves ? $e(implode(' · ', array_keys($serves))) : ($wf === 'W13' ? 'People write stories: nothing to pause.' : 'Nothing yet')) . '</small></td>'
            . '<td><span data-tv-status="' . $st[0] . '">' . $e($st[1]) . '</span></td><td>' . $act . '</td></tr>';
    }
    $paused = count(array_filter($wfs, fn ($r) => (int) $r['paused']));
    $h .= tv_admin_fold('Workflows', $paused . ' of ' . count(Automation::WORKFLOWS) . ' paused' . ($master ? ' · all held by the master' : ''), $master || $paused ? 'info' : 'ok', '', $wbody . '</tbody></table></div>', false);

    // the crawler's capture tasks: one line per workflow, each opening its workflow's page on the board, where each task's
    // switch is with the services it uses and their backups (decisions/0175 §6, Note for Jason: 0163 §9 amended; "W1: 6 tasks,
    // 5 on. Open W1"). An old link to a task's row (#au-task-<key>) lands on its workflow's line.
    if ($tasks) {
        $tbody = '<p class="ak-note">A task is a step of its workflow: its switch is on the workflow\'s own page on the System Control Board, with the services each step uses and their backups. The crawler\'s plan leaves out a task that\'s off.</p><ul class="au-wl">';
        $off = 0; $byWf = []; foreach ($tasks as $t => $r) { $byWf[(string) $r['wf']][$t] = $r; if ((int) $r['enabled'] !== 1) $off++; }
        foreach ($byWf as $wf => $list) {
            $nOn = count(array_filter($list, fn ($r) => (int) $r['enabled'] === 1)); $offN = array_values(array_map(fn ($r) => (string) $r['name'], array_filter($list, fn ($r) => (int) $r['enabled'] !== 1)));
            $tbody .= '<li id="au-wf-' . $e($wf) . '">' . implode('', array_map(fn ($t) => '<span id="au-task-' . $e((string) $t) . '"></span>', array_keys($list)))
                . '<b>' . $e($wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? '')) . ':</b> ' . count($list) . ' task' . (count($list) === 1 ? '' : 's') . ', ' . $nOn . ' on' . ($offN ? ' (off: ' . $e(implode(', ', $offN)) . ')' : '') . '. '
                . '<a class="ak-b" href="/admin/config/control/wf-' . $e($wf) . '#cb-steps" data-peek-frame>Open ' . $e($wf) . '</a></li>';
        }
        $h .= tv_admin_fold('Tasks', $off . ' of ' . count($tasks) . ' off', $off || $master ? 'info' : 'ok', '', $tbody . '</ul>', false);
    }

    // the outside services, each with its routes
    $routes = [];
    foreach (AiClient::ROUTES as $kind => $provs) foreach ($provs as $pv) if (isset(Automation::PROVIDER_SERVICE[$pv])) $routes[Automation::PROVIDER_SERVICE[$pv]][] = 'AI: ' . (AiClient::FEATURES[$kind][0] ?? $kind);
    $rows = Automation::services($pdo);
    $sbody = '<p class="ak-note">Pause a service and nothing of ours calls it: the crawler holds its hosts (a task that needs it reports "held"), the site\'s callers skip it, an AI feature routed to it waits or takes its next provider. Services that business operations depend on aren\'t paused from here: the reason is on each.</p><div class="ak-aa"><table><thead><tr><th>Service</th><th>Routes to it</th><th>State</th><th></th></tr></thead><tbody>';
    $np = 0;
    foreach (OutsideServices::COMPANIES as $k => $c) {
        $ctl = OutsideServices::CONTROLS[$k] ?? [];
        $rt = array_merge(array_map(fn ($t) => 'task: ' . (string) ($tasks[$t]['name'] ?? $t), (array) ($ctl['tasks'] ?? [])), array_map(fn ($j) => 'job: ' . (Scheduler::JOBS[$j][0] ?? $j), (array) ($ctl['jobs'] ?? [])), array_unique($routes[$k] ?? []));
        $r = $rows[$k] ?? null; $p = $r && (int) $r['paused'];
        if ($p) $np++;
        if (isset(Automation::NOT_PAUSABLE[$k])) { $st = ['ok', 'Not paused from here: ' . Automation::NOT_PAUSABLE[$k]]; $act = '<button class="ak-b" type="button" disabled title="' . $e('Business operations depend on it: ' . Automation::NOT_PAUSABLE[$k]) . '">Pause</button>'; }
        elseif ($p) { $st = ['info', 'Paused: ' . Automation::line($r, 'paused')]; $act = $sw('svc_on', $k, 'Resume', false, 'Resume ' . $c[0] . '? Calls to it start again where everything else is on.'); }
        else { $st = $master ? ['info', 'On, its automatic calls held by the master switch'] : ['ok', 'On']; $act = $sw('svc_off', $k, 'Pause', true, 'Pause ' . $c[0] . '? Nothing of ours calls it until it\'s resumed.'); }
        $sbody .= '<tr id="au-svc-' . $e($k) . '"><td><b><a class="underline" href="/admin/config/register#svc-' . $e($k) . '">' . $e((string) $c[0]) . '</a></b><br><small>' . $e((string) $c[1]) . '</small></td><td><small>' . ($rt ? $e(implode(' · ', $rt)) : '–') . '</small></td>'
            . '<td><span data-tv-status="' . $st[0] . '">' . $e($st[1]) . '</span></td><td>' . $act . '</td></tr>';
    }
    $h .= tv_admin_fold('Services', $np . ' paused', $np ? 'info' : 'ok', '', $sbody . '</tbody></table></div><p class="ak-note">OpenStreetMap\'s servers can each be paused on its card in the Service register.</p>', false);

    // the history
    if ($log = Automation::history($pdo, 30)) {
        $lbody = '<div class="ak-aa"><table><thead><tr><th>When</th><th>Switch</th><th>To</th><th>Why</th><th>Who</th></tr></thead><tbody>';
        foreach ($log as $l) {
            $what = ['master' => 'Master', 'workflow' => 'Workflow ' . $l['ref'], 'task' => 'Task ' . $l['ref'], 'service' => (OutsideServices::COMPANIES[$l['ref']][0] ?? $l['ref'])][$l['kind']] ?? $l['kind'] . ' ' . $l['ref'];
            $lbody .= '<tr><td><time datetime="' . $e(gmdate('c', (int) strtotime($l['at'] . ' UTC'))) . '" data-tv-time>' . $e(Automation::fmt((string) $l['at'])) . '</time></td><td>' . $e($what) . '</td><td>' . ((int) $l['paused'] ? 'paused' : 'on') . '</td><td>' . $e((string) ($l['why'] ?? '')) . '</td><td>' . $e((string) ($l['by_name'] ?? '')) . '</td></tr>';
        }
        $h .= tv_admin_fold('Changes', count($log) . ' latest', 'info', '', $lbody . '</tbody></table></div>', false);
    }
    return '<div>' . $h . '</div><style>.au-f{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;margin:.2rem 0}.au-f .ak-in{min-width:10rem;flex:1 1 10rem;max-width:16rem}.au-m{margin:.6rem 0}'
        . '.au-wl{list-style:none;margin:.4rem 0;padding:0;display:grid;gap:.3rem}.au-wl li{overflow-wrap:anywhere}.au-i{display:inline-block;margin:.2rem 0 .5rem}.au-i summary{cursor:pointer;list-style:none;display:inline-flex;width:1.5rem;height:1.5rem;align-items:center;justify-content:center;border-radius:999px;border:1px solid #CBB98D;color:#92400E;font-weight:700}'
        . '.au-i summary::-webkit-details-marker{display:none}.au-i[open] div{background:#FFFBF2;border:1px solid #EADFC4;border-radius:.6rem;padding:.6rem .8rem;margin-top:.35rem;font-size:.86rem;color:#3E2B1F}.au-i ol{margin:.3rem 0 .3rem 1.1rem}'
        . '.ak-aa{overflow-x:auto;margin:.6rem 0}.ak-aa table{width:100%;border-collapse:collapse;font-size:.85rem}.ak-aa th,.ak-aa td{padding:.35rem .5rem;border-bottom:1px solid #E8DCC6;text-align:left;vertical-align:top}'
        // phones (2026-11-18: the State column squeezed to a letter a line): each row a card, its cells one under another
        . '@media (max-width:767px){.ak-aa{overflow:visible}.ak-aa table,.ak-aa tbody,.ak-aa tr,.ak-aa td{display:block;width:auto}.ak-aa thead{display:none}.ak-aa tr{background:#fff;border:1px solid #E8DCC6;border-radius:.5rem;margin:0 0 .35rem;padding:.25rem .2rem}.ak-aa td{border:0;padding:.2rem .45rem}.ak-aa td:empty{display:none}.ak-aa td [data-tv-status],.ak-aa td .tv-note{display:block;width:auto}}</style>';
}
