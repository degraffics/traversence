<?php
// For board-panels.cjs: PHP's server router (php -S … board-panels-router.php) drawing what Jason opened (2026-11-23): the
// board at /admin/config/control?tab=alarms#open=config/alarms:<id>, the alarm's drawer (config/alarms' own get, through
// the admin API's shape), the part's own page (/admin/config/control/svc-google) and its register card
// (/admin/config/register#svc-google, the module card). ROOT names the bench (app/ and website/). Never live.
$root = getenv('ROOT'); $app = $root . '/app';
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path']; parse_str($u['query'] ?? '', $q);
if (str_starts_with($path, '/js/')) { $f = $root . '/website' . $path; if (!is_file($f)) { http_response_code(404); return true; } header('Content-Type: text/javascript'); readfile($f); return true; }
require_once $app . '/lib/Database.php'; $pdo = Database::connection();
if (getenv('GAUGES_FILE') && is_file($app . '/lib/ControlGauges.php')) { require_once $app . '/lib/ControlGauges.php'; ControlGauges::$source = (string) getenv('GAUGES_FILE'); }   // the gauges from a local copy (the gauge panel's tests)
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/admin/widgets.php'; require_once $app . '/ui/app-shell.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start(); $_SESSION['csrf_token'] = 'x';
if ($path === '/admin/api/config/alarms' && ($q['action'] ?? '') === 'get') {
    $tools = require $app . '/admin/tools.php';
    $d = ($tools['config/alarms']['get'])($pdo, (int) $q['id']);
    $d = (array) $d + ['title' => '#' . $q['id'], 'subtitle' => '', 'fields' => [], 'links' => []];
    $d['fields'] = array_values(array_filter($d['fields'], fn ($f) => trim((string) $f[1]) !== '')); $d['id'] = (int) $q['id'];
    header('Content-Type: application/json'); echo json_encode(['ok' => true, 'item' => $d]); return true;
}
if ($path === '/admin/api/config/alarms' && ($q['action'] ?? 'list') === 'list') {   // the Alarms tab's table, as the admin API answers it
    $tools = require $app . '/admin/tools.php';
    [$rows, $total, $counts] = ($tools['config/alarms']['list'])($pdo, $q);
    header('Content-Type: application/json'); echo json_encode(['ok' => true, 'rows' => $rows, 'total' => $total, 'capped' => false, 'counts' => (object) $counts, 'per' => 25, 'sortable' => []]); return true;
}
if ($path === '/admin/api/config/control' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {   // a gauge panel's tasks, as api.php answers them (GaugeTasks; gauge-panel.cjs)
    $in = json_decode((string) file_get_contents('php://input'), true) ?: []; $act = (string) ($in['action'] ?? ''); $pid = (string) ($in['id'] ?? '');
    require_once $app . '/lib/GaugeTasks.php'; header('Content-Type: application/json');
    try {
        if ($act === 'gaugeundo') { echo json_encode(['ok' => true, 'message' => GaugeTasks::undo($pdo, (int) $pid, 'Jason (test)')]); return true; }
        if ($act === 'gaugefix') { $nid = GaugeTasks::fix($pdo, $pid, (string) $in['item'], (string) $in['b'], (string) $in['what'], (string) $in['build'], 'Jason (test)', 1);
            echo json_encode(['ok' => true, 'id' => $nid, 'line' => '<b>Requested</b> · waiting for a build to take it · Jason (test), now. It clears by itself once its check passes.']); return true; }
        if ($act === 'gaugeapprove') { $k = ($in['kind'] ?? '') === 'target' ? 'target' : 'scope'; $nid = GaugeTasks::approve($pdo, $pid, $k, (string) ($in['value'] ?? ''), (string) ($in['title'] ?? ''), 'Jason (test)', 1);
            echo json_encode(['ok' => true, 'id' => $nid, 'line' => '<b>' . ($k === 'scope' ? 'Scope approved' : 'Target approved') . '</b> by Jason (test), now.']); return true; }
        // the Work queue (work-queue.cjs), as api.php answers it
        $tid = (int) $pid; $W = 'Jason (test)';
        if ($act === 'qadd') { $nid = GaugeTasks::add($pdo, (string) ($in['gauge'] ?? ''), (string) ($in['what'] ?? ''), (string) ($in['why'] ?? ''), (string) ($in['source'] ?? ''), $W, 1); echo json_encode(['ok' => true, 'id' => $nid, 'message' => (time() - (int) strtotime((string) (GaugeTasks::task($pdo, $nid)['at'] ?? '') . ' UTC') > 10 ? 'It\'s already in the queue as task #' . $nid . ': the one task stands.' : 'Added as task #' . $nid . '. It\'s late if nobody takes it within a day.')]); return true; }
        if ($act === 'qhistory') { echo json_encode(['ok' => true, 'rows' => array_map(fn ($r) => ['at' => gmdate('c', (int) strtotime($r['at'] . ' UTC')), 'tz' => (string) $r['tz'], 'what' => (string) $r['what'], 'who' => (string) $r['who']], GaugeTasks::history($pdo, $tid))]); return true; }
        if ($act === 'qedit') { echo json_encode(['ok' => true, 'message' => GaugeTasks::edit($pdo, $tid, array_intersect_key($in, array_flip(['what', 'why', 'source', 'gauge', 'goal', 'in_scope', 'out_scope', 'done_when', 'priority'])), $W)]); return true; }
        if ($act === 'qstep') { $sid = GaugeTasks::stepAdd($pdo, $tid, (string) ($in['see'] ?? ''), (string) ($in['do_what'] ?? ''), (string) ($in['confirm'] ?? ''), $W); echo json_encode(['ok' => true, 'id' => $sid, 'message' => 'Step added.']); return true; }
        if ($act === 'qadr') { echo json_encode(['ok' => true, 'message' => GaugeTasks::adrAttach($pdo, $tid, (string) ($in['adr'] ?? ''), $W)]); return true; }
        if ($act === 'qadrkeep') { echo json_encode(['ok' => true, 'message' => GaugeTasks::adrKeep($pdo, $tid, (array) ($in['keep'] ?? []), $W)]); return true; }
        if ($act === 'qadrfind') { $q = mb_strtolower(trim((string) ($in['q'] ?? ''))); $have = array_column(GaugeTasks::adrs($pdo, $tid), 'adr'); $rows = [];
            foreach (GaugeTasks::decisions() as $n => $d) { if ($q !== '' && strpos((string) $n, $q) === false && strpos(mb_strtolower((string) $d['title']), $q) === false) continue;
                $rows[] = ['adr' => (string) $n, 'title' => (string) $d['title'], 'status' => (string) ($d['status'] ?? ''), 'have' => in_array((string) $n, $have, true)]; if (count($rows) >= 15) break; }
            echo json_encode(['ok' => true, 'rows' => $rows]); return true; }
        if ($act === 'qstepedit') { echo json_encode(['ok' => true, 'message' => GaugeTasks::stepEdit($pdo, $tid, array_intersect_key($in, array_flip(['see', 'do_what', 'confirm', 'done'])), $W)]); return true; }
        if ($act === 'qassign') { echo json_encode(['ok' => true, 'message' => GaugeTasks::assign($pdo, $tid, trim((string) ($in['owner'] ?? '')) !== '' ? (string) $in['owner'] : $W, $W)]); return true; }
        if ($act === 'qrelease') { echo json_encode(['ok' => true, 'message' => GaugeTasks::release($pdo, $tid, (string) ($in['rel'] ?? ''), $W)]); return true; }
        if ($act === 'qwithdraw') { echo json_encode(['ok' => true, 'message' => GaugeTasks::withdraw($pdo, $tid, (string) ($in['reason'] ?? ''), $W)]); return true; }
    } catch (Throwable $x) { echo json_encode(['ok' => false, 'error' => ['message' => $x->getMessage()]]); return true; }
}
if (str_starts_with($path, '/admin/api/')) { header('Content-Type: application/json'); echo '{"ok":true,"rows":[],"total":0,"counts":{}}'; return true; }
require_once $app . '/ui/theme-tokens.php'; ob_start(); include $app . '/admin/kit-css.php'; $css = tv_theme_tokens() . ob_get_clean();
$js = '<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script><script src="/js/admin-kit.js"></script><script src="/js/tv-copy.js"></script>';
$page = fn (string $title, string $body) => '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title>' . $css . '</head><body>' . $body . $js . '</body></html>';
if ($path === '/admin/config/register' && getenv('REGISTER') === 'full') {   // the whole Service register, its forms posting to its own handler (google-signin.cjs)
    require_once $app . '/lib/Auth.php'; require_once $app . '/admin/sections.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/control-console.php';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') $_POST['csrf'] = $_POST['csrf'] ?? '';
    echo $page('Service register', @tv_admin_mod_register($pdo)); return true;
}
if ($path === '/admin/config/register') {
    require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php'; require_once $app . '/admin/operations-console.php'; require_once $app . '/admin/module-cards.php';
    echo $page('Service register', '<h1>Service register</h1>' . tv_module_card($pdo, 'google', ['can' => true])); return true;
}
require_once $app . '/admin/preflight-console.php'; require_once $app . '/admin/control-console.php';
if (preg_match('~^/admin/config/control/([a-z]+)-(.+)$~', $path, $m)) $_GET['proc'] = $m[1] . ':' . $m[2];
foreach ($q as $k => $v) if ($k !== 'proc') $_GET[$k] = $v;
echo $page('System Control Board', tv_admin_mod_control($pdo)); return true;
