<?php
// admin/api.php — the admin's one endpoint (decisions/0109 A5): /admin/api/<module>/<tool>. Every tool answers the same
// actions: list (rows, stage counts), get (the detail: fields, links, where it is in its workflow, the moves with who
// may make them, history), move (a workflow move), create (a quick task). System Configuration adds workflows (the
// builder: get, save a draft, check, publish, new, discard) and permissions (save a rule). The same access rule applies
// wherever the tool is opened (app/admin/Access.php).
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/crawler/IntakeStager.php';
require_once __DIR__ . '/../../app/admin/AdminModules.php';
require_once __DIR__ . '/../../app/admin/Workflow.php';

header('Cache-Control: no-store');
$pdo = Database::connection();
$me = Auth::currentUser();
$ok = $me && IntakeStager::canUseIntake($pdo, $me);
if ($me && !$ok) { try { $st = $pdo->prepare("SELECT 1 FROM " . Database::t('admin_access') . " WHERE user_id = ? AND tier = 'regional_admin' LIMIT 1"); $st->execute([(int) $me['id']]); $ok = (bool) $st->fetchColumn(); } catch (Throwable) {} }
if (!$ok) Response::error('Staff only.', 403, 'forbidden');
$uid = (int) $me['id'];
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!preg_match('#/admin/api/([a-z]+)/([a-z0-9_-]+)$#', $path, $x)) Response::error('Not an admin tool.', 404);
[, $mod, $t] = $x;
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$in = $post ? Response::jsonBody() : $_GET;
if ($post) Response::requireCsrf();
$act = (string) ($in['action'] ?? 'list');

/** An action's link with {id} and the item's own fields ({user_id}) filled in. */
function ak_link(string $u, int $id, array $row): string
{
    return $u === '' ? '' : preg_replace_callback('/\{([a-z_]+)\}/', fn ($x) => rawurlencode($x[1] === 'id' ? (string) $id : (string) ($row[$x[1]] ?? '')), $u);
}

/** The detail drawer's content for an item. */
function ak_detail(PDO $pdo, string $key, array $tool, int $id): array
{
    $d = isset($tool['get']) ? ($tool['get'])($pdo, $id) : null;
    if (!$d && empty($tool['workflow'])) throw new InvalidArgumentException('That item isn\'t here.');
    $d = (array) $d + ['title' => '#' . $id, 'subtitle' => '', 'fields' => [], 'links' => []];
    $d['fields'] = array_values(array_filter($d['fields'], fn ($f) => trim((string) $f[1]) !== ''));
    $d['id'] = $id;
    if (!empty($tool['workflow']) && Workflow::ready($pdo)) {
        $wk = $tool['workflow'];
        $it = Workflow::item($pdo, $wk, $id);
        $def = Workflow::def($pdo, $wk, (int) $it['version']);
        [$h, $sla, $late] = Workflow::clock($def, $it);
        $cur = $it['stage'];
        $order = array_column($def['stages'], 'key');
        $pos = array_search($cur, $order, true);
        $d['workflow'] = ['key' => $wk, 'stage' => $cur, 'version' => (int) $it['version'],
            'stages' => array_values(array_map(fn ($s, $i) => ['key' => $s['key'], 'label' => $s['label'], 'end' => ($s['kind'] ?? '') === 'end', 'state' => $s['key'] === $cur ? 'now' : ($pos !== false && $i < $pos && ($s['kind'] ?? '') !== 'end' ? 'done' : '')],
                $def['stages'], array_keys($def['stages']))),
            'hours' => $h, 'sla' => $sla, 'late' => $late,
            'moves' => array_map(fn ($m) => ['link' => ak_link((string) ($m['link'] ?? ''), $id, (array) ($it['row'] ?? []))] + $m, Workflow::moves($pdo, $wk, $id, $it)),
            'history' => array_map(fn ($r) => ['at' => date('M j, Y g:ia', strtotime((string) $r['created_at'])), 'move' => $r['move'], 'from' => $r['from_stage'], 'to' => $r['to_stage'],
                'by' => (string) ($r['display_name'] ?: $r['email'] ?: ''), 'note' => (string) $r['note'], 'effects' => (string) ($r['effects'] ?? '')], Workflow::history($pdo, $wk, $id)),
            'labels' => array_column($def['stages'], 'label', 'key')];
        if ($d['title'] === '#' . $id && !empty($it['title'])) $d['title'] = $it['title'];
    }
    return $d;
}

try {
    // ---- System Configuration: the workflow builder ----------------------------------------------------------------
    if ($mod === 'config' && $t === 'workflows') {
        [$can, $why] = Access::can('config.workflows.edit', ['min' => 'manager', 'depts' => []]);
        $key = (string) ($in['key'] ?? '');
        if ($act === 'get') {
            $w = Workflow::meta($pdo, $key);
            $pub = $w['version'] ? Workflow::def($pdo, $key) : ['stages' => [], 'moves' => [], 'any' => []];
            $draft = $w['draft'] !== null ? json_decode((string) $w['draft'], true) : null;
            $counts = [];
            $st = $pdo->prepare('SELECT stage, COUNT(*) n FROM ' . Database::t('wf_items') . ' WHERE wf_key = ? GROUP BY stage'); $st->execute([$key]);
            foreach ($st as $r) $counts[$r['stage']] = (int) $r['n'];
            $b = Workflow::builtins()[$key]['def'] ?? null;
            $lock = ['stages' => [], 'moves' => []];
            if ($b) { foreach ($b['stages'] as $s) if (!empty($s['op']) || !empty($s['locked'])) $lock['stages'][] = $s['key']; foreach ($b['moves'] as $m) if (!empty($m['op'])) $lock['moves'][] = $m['key']; }
            $by = '';
            if ($w['draft_by']) { $u = $pdo->prepare('SELECT COALESCE(display_name, email) FROM users WHERE id = ?'); $u->execute([(int) $w['draft_by']]); $by = (string) $u->fetchColumn(); }
            $vs = $pdo->prepare('SELECT version, note, published_at FROM ' . Database::t('wf_versions') . ' WHERE wf_key = ? ORDER BY version DESC LIMIT 10'); $vs->execute([$key]);
            Response::success(['workflow' => ['key' => $key, 'name' => $w['name'], 'module' => $w['module'], 'builtin' => (bool) $w['builtin'], 'version' => (int) $w['version'],
                'draft_by' => $by, 'draft_at' => $w['draft_at']], 'def' => $draft ?? $pub, 'published' => $pub, 'has_draft' => $draft !== null, 'counts' => $counts, 'lock' => $lock,
                'problems' => Workflow::validate($key, $draft ?? $pub), 'versions' => $vs->fetchAll(PDO::FETCH_ASSOC), 'can' => $can, 'why' => $why]);
        }
        if (!$post) Response::error('Send it as a POST.', 405);
        if (!$can) Response::error($why . '.', 403);
        if ($act === 'save') {
            $def = (array) ($in['def'] ?? []);
            $def = ['stages' => array_values((array) ($def['stages'] ?? [])), 'moves' => array_values((array) ($def['moves'] ?? [])), 'any' => array_values((array) ($def['any'] ?? []))];
            if (strlen(json_encode($def)) > 60000) Response::error('That workflow is too big.');
            Workflow::saveDraft($pdo, $key, $def, $uid);
            Response::success(['problems' => Workflow::validate($key, $def)]);
        }
        if ($act === 'publish') {
            $v = Workflow::publish($pdo, $key, (string) ($in['note'] ?? ''), ($in['carry'] ?? '') === 'move' ? 'move' : 'keep', (array) ($in['map'] ?? []), $uid);
            Response::success(['version' => $v, 'message' => 'Published as version ' . $v . '.']);
        }
        if ($act === 'discard') { $pdo->prepare('UPDATE ' . Database::t('wf_workflows') . ' SET draft = NULL, draft_by = NULL, draft_at = NULL WHERE wf_key = ?')->execute([$key]); Response::success(['message' => 'Draft thrown away.']); }
        if ($act === 'create') {
            $m = (string) ($in['module'] ?? '');
            if (!isset(Workspaces::ALL[$m])) Response::error('Choose the module it belongs to.');
            Response::success(['key' => Workflow::create($pdo, (string) ($in['name'] ?? ''), $m, $uid)]);
        }
        Response::error('Unknown action.');
    }

    // ---- System Configuration: permissions -------------------------------------------------------------------------
    if ($mod === 'config' && $t === 'permissions') {
        if (!$post) Response::error('Send it as a POST.', 405);
        [$can, $why] = Access::can('config.permissions.edit', ['min' => 'director', 'depts' => []]);
        if (!$can) Response::error($why . '.', 403);
        $action = (string) ($in['rule'] ?? '');
        $min = (string) ($in['min'] ?? '');
        if (!preg_match('/^[a-z0-9_\/.-]{3,120}$/', $action) || !isset(Access::LEVEL_NAMES[$min])) Response::error('That rule isn\'t right.');
        $depts = array_values(array_intersect((array) ($in['depts'] ?? []), array_keys(Access::DEPT_NAMES)));
        Access::save($pdo, $action, $min, $depts, $uid);
        Response::success(['message' => 'Saved.']);
    }

    // ---- System Configuration: the stock library (decisions/0143): search a source, add a photo, choose a door's photo.
    // Its list, retagging and retiring are the config/stock tool, below.
    if ($mod === 'config' && $t === 'stock' && in_array($act, ['search', 'add', 'use'], true)) {
        require_once __DIR__ . '/../../app/lib/StockLibrary.php';
        [$sok, $swhy] = Modules::canTool('config/stock');
        if (!$sok) Response::error($swhy . '.', 403);
        if ($act === 'search') {
            [$items, $more] = StockLibrary::search($pdo, (string) ($in['source'] ?? ''), (string) ($in['q'] ?? ''), (int) ($in['page'] ?? 1));
            Response::success(['items' => $items, 'more' => $more]);
        }
        if (!$post) Response::error('Send it as a POST.', 405);
        [$eok, $ewhy] = Access::can('config/stock.edit', ['min' => 'staff', 'depts' => ['con', 'ops', 'eng']]);
        if (!$eok) Response::error($ewhy . '.', 403);
        if ($act === 'add') {
            $img = StockLibrary::add($pdo, (string) ($in['source'] ?? ''), (string) ($in['id'] ?? ''), ['subjects' => (string) ($in['subjects'] ?? ''), 'place' => (string) ($in['place'] ?? ''), 'scope' => (string) ($in['scope'] ?? '')], $uid);
            Response::success(['id' => (int) ($img['id'] ?? 0), 'message' => 'Added to the library, with its credit.']);
        }
        $slot = (string) ($in['slot'] ?? '');   // the admin chooses only for the site's own slots (the home page's doors)
        if ($slot !== 'home_door' || !isset(StockLibrary::DOORS[(string) ($in['owner'] ?? '')])) Response::error('Choose that where it\'s edited.');
        StockLibrary::setUse($pdo, $slot, (string) $in['owner'], (int) ($in['stock'] ?? 0), $uid);
        Response::success(['message' => (int) ($in['stock'] ?? 0) ? 'Chosen: the door shows it until you choose another.' : 'Back to automatic.']);
    }

    // ---- System Configuration: Pre-flight (decisions/0163 §10's note of 2026-11-16). run: one line's real, free, read-only
    // test, answered with the line drawn again and the counts; fix: a fix the site can make (the scheduler's jobs, database
    // updates); done: a Run all finished (kept for Start the system); start, status, cancel: Run all on the server (2026-11-17).
    // Executives (config.preflight); it changes nothing else.
    // Start the system's run log: a session's report as text, downloaded or opened as a page (Jason, 2026-11-18: "Report is
    // not downloadable"); run=<the session's start time>
    if ($mod === 'config' && $t === 'automation' && !$post && $act === 'report') {
        require_once __DIR__ . '/../../app/lib/SystemStart.php';
        [$can, $why] = SystemStart::can();
        if (!$can) Response::error($why . '.', 403);
        $one = SystemStart::report($pdo, (int) ($in['run'] ?? 0));
        if (!$one) Response::error('That Start isn\'t in the run log any more (the last 20 are kept).', 404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: ' . (!empty($in['view']) ? 'inline' : 'attachment') . '; filename="start-the-system-' . gmdate('Y-m-d-Hi', $one[1]) . '.txt"');
        echo $one[0];
        exit;
    }
    // ---- System Configuration: System Control Board (decisions/0171, release 2b). check: a process's pre-flight line run
    // now; start1 / stop: one process (started only when its check isn't red; shut down tasks first); start: Start the
    // system; shutdown: the whole system in order; pauseall: the emergency stop; preflight: the pre-flight's Run all on the
    // (the board's Run the pre-flight calls config/preflight's own start, which works on after it answers). Each answers with words for the page's notice; the page draws the board again in place.
    if ($mod === 'config' && $t === 'control') {
        require_once __DIR__ . '/../../app/lib/ControlBoard.php';
        [$okV, $whyV] = ControlBoard::can();
        if (!$okV) Response::error($whyV . '.', 403);
        if (!$post) Response::error('Send it as a POST.', 405);
        require_once __DIR__ . '/../../app/lib/SystemStart.php'; require_once __DIR__ . '/../../app/lib/Preflight.php';
        $who = (string) (Access::staff()['name'] ?? $me['display_name'] ?? '');
        $pid = (string) ($in['id'] ?? '');
        try {
            // the logbook (0171 §10, release 5a): the feed (read only, 50 at a time, a system's or a process's), and Log an entry
            if ($act === 'logfeed') { require_once __DIR__ . '/../../app/admin/control-console.php';
                $sc = (string) ($in['scope'] ?? ''); if ($sc !== '' && !preg_match('/^(sys|wf|t|wk|svc|job|site):[A-Za-z0-9_.-]{1,50}$/', $sc)) $sc = '';
                Response::success(['html' => tv_cb_log_rows($pdo, $sc, (int) ($in['before'] ?? 0))]); }
            // a report's downloads (0171 §11, release 5b): read only, built on demand from what's kept; CSV or the page as a file
            if ($act === 'report') { require_once __DIR__ . '/../../app/lib/ControlReport.php';
                $sc = (string) ($in['scope'] ?? ''); if ($sc !== '' && !preg_match('/^(sys|wf|t|wk|svc|job|site):[A-Za-z0-9_.-]{1,50}$/', $sc)) $sc = '';
                $r = ControlReport::build($pdo, $sc, (string) ($in['range'] ?? 'day'));
                $csv = ($in['format'] ?? '') === 'csv';
                $fn = 'traversence-report-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($r['name'])), '-') . '-' . $r['range'] . '-' . gmdate('Y-m-d', $r['to']) . ($csv ? '.csv' : '.html');
                Response::success(['name' => $fn, 'type' => $csv ? 'text/csv' : 'text/html', 'body' => $csv ? ControlReport::csv($r) : ControlReport::file($r), 'message' => 'Downloaded ' . $fn . '.']); }
            if ($act === 'lognote') { require_once __DIR__ . '/../../app/lib/ControlLog.php';
                $sc = (string) ($in['scope'] ?? ''); if ($sc !== '' && !preg_match('/^(wf|t|wk|svc|job|site):[A-Za-z0-9_.-]{1,50}$/', $sc)) $sc = '';
                Response::success(['message' => ControlLog::note($pdo, $sc, (string) ($in['text'] ?? ''), $who, (string) ($in['tz'] ?? ''))]); }
            // each control pressed on the board, in the logbook (the ones that don't log themselves)
            if (in_array($act, ['check', 'start', 'pauseall', 'svccheck', 'opsdo'], true) && (in_array($act, ['check', 'svccheck'], true) ? Preflight::can()[0] : ($act === 'opsdo' ? true : SystemStart::can()[0]))) { require_once __DIR__ . '/../../app/lib/ControlLog.php';
                ControlLog::add($pdo, 'control', $act === 'opsdo' ? '' : (str_contains($pid, ':') ? $pid : ''), ['check' => 'Run its check', 'start' => 'Start the system', 'pauseall' => 'Pause everything', 'svccheck' => 'Check it now', 'opsdo' => 'A step on an alarm'][$act] . ($pid !== '' && !str_contains($pid, ':') ? ' (#' . $pid . ')' : ''), $who, '', (string) ($in['tz'] ?? '')); }
            // a trend (the historian, 0171 §6, release 4b): read only, for whoever may view the board; a process, or sys:<key>
            if ($act === 'trend') { require_once __DIR__ . '/../../app/lib/ControlHistorian.php';
                $rg = isset(ControlHistorian::RANGES[(string) ($in['range'] ?? '')]) ? (string) $in['range'] : '24h';
                if (!preg_match('/^(sys|wf|t|wk|svc|job|site):[A-Za-z0-9_.-]{1,50}$/', $pid)) Response::error('Which process?', 422);
                Response::success(['html' => ControlHistorian::html(ControlHistorian::trend($pdo, $pid, $rg), $pid)]); }
            // an alarm acknowledged (0171 §7): stops its repeat email; it stays open until its reading is good
            if ($act === 'ack') { require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
                if (!ControlAlarms::ack($pdo, (int) $pid, $uid, $who)) Response::error('It was already acknowledged, or it isn\'t here any more.', 422);
                Response::success(['message' => 'Acknowledged: its email won\'t repeat. It stays open until its reading is good again.']); }
            if ($act === 'ackgroup') { require_once __DIR__ . '/../../app/lib/ControlAlarms.php';   // a group row's Acknowledge all (0171 §8's note)
                $n = ControlAlarms::ackGroup($pdo, $pid, $uid, $who);
                if (!$n) Response::error('They were already acknowledged, or the group has changed: the board shows it again.', 422);
                Response::success(['message' => 'Acknowledged ' . $n . ': their emails won\'t repeat. Each stays open until its reading is good, or until a trip is resolved.']); }
            if ($act === 'unack') { require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
                if (!ControlAlarms::unack($pdo, (int) $pid)) Response::error('It wasn\'t acknowledged.', 422);
                Response::success(['message' => 'Undone: it\'s open and unacknowledged again.']); }
            // a gauge panel's tasks (the gauge panel, approved by Jason 2026-11-23; CLAUDE.md "Every action is a task"): Fix it on
            // an item (sent to its gauge's build: the session manager's ruling on Q1), Approve the scope or the target (his own
            // decisions, Executive only: the ruling on Q3), and Undo within a day. Each answers with the line that replaces its button.
            if (in_array($act, ['gaugefix', 'gaugeapprove', 'gaugeundo'], true)) { require_once __DIR__ . '/../../app/lib/GaugeTasks.php';
                $tz = (string) ($in['tz'] ?? ''); $when = '<time datetime="' . gmdate('c') . '" data-tv-time>' . gmdate('M j, H:i') . ' UTC</time>';
                $h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
                if ($act === 'gaugeundo') Response::success(['message' => GaugeTasks::undo($pdo, (int) $pid, $who, $tz)]);
                if ($act === 'gaugefix') {
                    $build = trim((string) ($in['build'] ?? '')); $what = trim((string) ($in['what'] ?? ''));
                    $nid = GaugeTasks::fix($pdo, $pid, (string) ($in['item'] ?? ''), (string) ($in['b'] ?? ''), $what, $build, $who, $uid, $tz);
                    Response::success(['id' => $nid, 'message' => 'Sent to the ' . $build . ' build.',
                        'line' => '<b>Fixing:</b> ' . $h($what) . ' · sent to the ' . $h($build) . ' build · ' . $h($who) . ', ' . $when . '. It clears itself once its check passes.']); }
                [$okA, $whyA] = Access::can('config.gauges.approve', ['min' => 'executive', 'depts' => []]);
                if (!$okA) Response::error($whyA . '.', 403);
                $kind = (string) ($in['kind'] ?? '') === 'target' ? 'target' : 'scope';
                $nid = GaugeTasks::approve($pdo, $pid, $kind, (string) ($in['value'] ?? ''), (string) ($in['title'] ?? ''), $who, $uid, $tz);
                Response::success(['id' => $nid, 'message' => $kind === 'scope' ? 'Scope approved.' : 'Target approved.',
                    'line' => '<b>' . ($kind === 'scope' ? 'Scope approved' : 'Target approved') . '</b> by ' . $h($who) . ', ' . $when . ($kind === 'target' ? ': ' . $h($in['value'] ?? '') : '') . '.']); }
            // Mark resolved (svcresolve) is removed everywhere (02 §5.3, row 95): a passing check closes an issue, Check it now or,
            // on a trip, Check and start again
            // an Incident Report's root cause, written by a person (02 §2.6, P12: "Unconfirmed until a person writes it"):
            // id = the incident's number, value = the cause in words; the board's own permission (ControlBoard::can, above)
            if ($act === 'ircause') { require_once __DIR__ . '/../../app/lib/ControlIncidents.php'; require_once __DIR__ . '/../../app/lib/ControlLog.php';
                $cause = trim((string) ($in['value'] ?? ''));
                if ($cause === '') Response::error('Write the root cause in words: it\'s kept on the Incident Report with your name.', 422);
                $ir = ControlIncidents::get($pdo, (int) $pid);
                if (!$ir) Response::error('That Incident Report isn\'t here any more.', 422);
                if (!ControlIncidents::setCause($pdo, (int) $pid, $cause, $who)) Response::error('It wasn\'t written: the same words were already its root cause.', 422);
                ControlLog::add($pdo, 'control', '', 'Root cause written on ' . ControlIncidents::number((int) $pid) . ': ' . mb_substr($cause, 0, 200), $who, '', (string) ($in['tz'] ?? ''));
                Response::success(['message' => 'Root cause written: ' . mb_substr($cause, 0, 300)]); }
            // a cap or "returns empty" alarm's steps, done in place as their old Pulse case did them (Operations::pulseDo, its own permission)
            if ($act === 'opsdo') { require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
                [$akey, $what] = array_pad(explode('|', $pid, 2), 2, '');
                Response::success(['message' => ControlAlarms::opsDo($pdo, $akey, $what, ['value' => (string) ($in['value'] ?? '')], $uid, $who)]); }
            // a trip resolved by an admin (decisions/0171 §7's note, release 3c): what fixed it, then what it stopped starts again
            // once its check passes (Automation's permission); a check still red keeps it open with the reason. Undo for a day.
            if ($act === 'resolve' || $act === 'unresolve') { require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
                $a = ControlAlarms::byId($pdo, (int) $pid);
                if ($a && json_decode((string) ($a['stopped'] ?? ''), true)) { [$okS, $whyS] = SystemStart::can(); if (!$okS) Response::error($whyS . ': it would start again what the trip stopped.', 403); }
                [$done, $msg] = $act === 'resolve' ? ControlAlarms::resolve($pdo, (int) $pid, $uid, $who, (string) ($in['value'] ?? '')) : ControlAlarms::unresolve($pdo, (int) $pid, $uid, $who);
                if (!$done) Response::error($msg, 422);
                Response::success(['message' => $msg]); }
            // a service issue's "Check it now": its light probe goes out with the next call or run
            if ($act === 'svccheck') { require_once __DIR__ . '/../../app/lib/ServiceHealth.php'; [$okP, $whyP] = Preflight::can(); if (!$okP) Response::error($whyP . '.', 403);
                // the site's own issue that backs off (a timeout, a server error): its service's check runs now, past the wait,
                // and an answer closes it (4f, 2026-11-19: "with the next call" never came while nothing called it)
                $si = $pdo->prepare("SELECT * FROM service_issues WHERE id = ? AND state = 'open'"); $si->execute([(int) $pid]); $si = $si->fetch(PDO::FETCH_ASSOC);
                // a 429 asked us to slow down: never called past its wait or its Retry-After (the session manager, 2026-11-19)
                if ($si && $si['kind'] === 'rate') Response::error(substr(ServiceHealth::rateFix((string) $si['host'], (string) $si['hold_until'], (int) $si['id'])[3], 6), 409);
                if ($si && $si['caller'] === 'site' && (ServiceHealth::KINDS[$si['kind']][1] ?? '') === 'back' && isset(Preflight::lines($pdo)['svc:' . $si['service']])) {
                    Preflight::$now = true; $r = Preflight::run($pdo, 'svc:' . $si['service'], $who); Preflight::$now = false;
                    // not set up (no key) or not used: nothing calls it, so nothing waits on this issue; the system closes it and
                    // says so (Jason, 2026-11-19: az511's "Success: Not needed yet" left its warning standing)
                    if (($r['s'] ?? '') === 'na') ServiceHealth::resolve($pdo, (int) $pid, 0, 'Not set up, so nothing calls it: ' . mb_substr((string) $r['said'], 0, 160) . ' A new failure opens a new issue.');
                    $st = $pdo->prepare("SELECT state FROM service_issues WHERE id = ?"); $st->execute([(int) $pid]); $closed = $st->fetchColumn() !== 'open';
                    if (($r['s'] ?? '') === 'na' && $closed) Response::success(['message' => 'Closed: ' . (string) $si['host'] . ' isn\'t set up (' . mb_substr((string) $r['said'], 0, 160) . '), so nothing calls it and nothing waits. Issue #' . (int) $pid . ' is closed; a new failure opens a new one.']);
                    Response::success(['message' => ($closed ? 'It answered: issue #' . (int) $pid . ' is closed, and what waited on it carries on. ' : 'Still open: it hasn\'t answered yet, so the issue waits and tries again by itself. ') . (Preflight::MARKS[$r['s']] ?? $r['s']) . ': ' . mb_substr((string) $r['said'], 0, 280), 'bad' => !$closed]);
                }
                Response::success(['message' => ServiceHealth::probeSoon($pdo, (int) $pid) ? 'Its check goes out with the next call or crawler run; the alarm clears by itself if it answers.' : 'No check to send: it\'s resolved already, or it\'s an OpenStreetMap server, which we don\'t contact while it\'s held (a person marks it resolved).']); }
            if ($act === 'check') { [$okP, $whyP] = Preflight::can(); if (!$okP) Response::error($whyP . '.', 403); }
            else { [$okS, $whyS] = SystemStart::can(); if (!$okS) Response::error($whyS . '.', 403); }
            if ($act === 'check' && is_file(__DIR__ . '/../../app/lib/ControlSensor.php')) {   // a duty the sensor watches (search, sign-in, payments): judged now
                require_once __DIR__ . '/../../app/lib/ControlSensor.php';
                if (isset(ControlSensor::PARTS[$pid])) { [$bad, $words] = ControlSensor::checkOne($pdo, $pid); Response::success(['message' => $words, 'bad' => $bad]); }
            }
            // a worker with no pre-flight line (the video worker) and each of our endpoints: the one engine's own check
            // (control-board-plan step3/02 §2.7: a worker's heartbeat or last run; an endpoint's no-work request inside its
            // speed gate), the same permission and logbook line as above, the same answer shape
            if ($act === 'check' && preg_match('/^(wk|ep):(.+)$/', $pid, $pm)) {
                require_once __DIR__ . '/../../app/lib/ControlRegister.php'; require_once __DIR__ . '/../../app/lib/ControlEngine.php';
                $known = $pm[1] === 'ep' ? isset(ControlRegister::ENDPOINTS[$pm[2]]) : isset(ControlRegister::WORKERS[$pm[2]]);
                if (!$known) Response::error('That part isn\'t on the board.', 422);
                $wline = $pm[1] === 'wk' ? (string) (ControlRegister::WORKERS[$pm[2]][3] ?? '') : '';
                if ($wline === '' || !isset(Preflight::lines($pdo)[$wline])) {
                    $ck = ControlEngine::check($pdo, $pid, $who);
                    if ($ck['pass'] === null) Response::error('It has no check of its own yet.', 422);
                    $tw = ''; require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
                    foreach (ControlAlarms::ofProc($pdo, $pid) as $al) if ($al['level'] === 'trip' && !empty($al['tripped_at'])) { $tw = 'Checked: its trip from ' . gmdate('M j, g:i A', (int) strtotime($al['tripped_at'] . ' UTC')) . ' UTC stays red until you press Check and start again on this row' . ($ck['pass'] ? '; it would start now' : ' once this passes') . '. '; break; }
                    Response::success(['message' => $tw . ($ck['pass'] ? 'Passed: ' : 'Failed: ') . mb_substr((string) $ck['proof'], 0, 280), 'bad' => !$ck['pass']]);
                }
            }
            if ($act === 'check') {
                $lid =$pid === 't:W14' ? 'wf:W14' : (str_starts_with($pid, 'wk:') ? (string) (ControlRegister::WORKERS[substr($pid, 3)][3] ?? '') : $pid);
                if ($lid === '' || !isset(Preflight::lines($pdo)[$lid])) Response::error('It has no check of its own yet.', 422);
                Preflight::refreshFor($pdo, $lid, time() + 30, $who);
                $r = Preflight::run($pdo, $lid, $who);
                // a check never clears a trip (0171 §7's 3c note): say so, and what does, so the notice can't read as cleared
                $tw = ''; require_once __DIR__ . '/../../app/lib/ControlAlarms.php';
                foreach (ControlAlarms::ofProc($pdo, $pid) as $al) if ($al['level'] === 'trip' && !empty($al['tripped_at'])) { $tw = 'Checked: its trip from ' . gmdate('M j, g:i A', (int) strtotime($al['tripped_at'] . ' UTC')) . ' UTC stays red until you press Check and start again on this row' . ($r['s'] === 'bad' ? ' once this passes' : '; it would start now') . '. '; break; }
                Response::success(['message' => $tw . Preflight::markOf((string) $lid, (string) $r['s']) . ': ' . mb_substr((string) $r['said'], 0, 300 - min(200, mb_strlen($tw))), 'bad' => $r['s'] === 'bad']);
            }
            // watching Railway and the sync, on the board since the register took in Outside services (decisions/0173 §1): its
            // switch, the register's permission (Operations::can); the hourly job only reads their status (free)
            if ($act === 'watch') { require_once __DIR__ . '/../../app/lib/Operations.php'; [$okO, $whyO] = Operations::can(); if (!$okO) Response::error($whyO . '.', 403);
                Operations::setWatching($pdo, $pid === 'on', $uid);
                Response::success(['message' => $pid === 'on' ? 'Watching is on: the hourly job looks at Railway and the sync.' : 'Watching is off: nothing calls Railway or GitHub from here.']); }
            if ($act === 'start1') Response::success(['message' => ControlBoard::startOne($pdo, $pid, $uid, $who)]);
            if ($act === 'stop') Response::success(['message' => ControlBoard::stopOne($pdo, $pid, $uid, $who)]);
            // a task's one switch, on its workflow's page (decisions/0175 §6): Automation's own switch and permission, with
            // who and why (Automation::setTask, kept in its Changes); a reason is asked when it goes off
            if ($act === 'taskon' || $act === 'taskoff') { [$okA, $whyA] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]); if (!$okA) Response::error($whyA . '.', 403);
                require_once __DIR__ . '/../../app/lib/Automation.php'; require_once __DIR__ . '/../../app/lib/crawler/CrawlService.php';
                $tk = CrawlService::ready($pdo) ? (CrawlService::tasks($pdo)[$pid] ?? null) : null;
                if (!$tk) Response::error('There\'s no task called ' . $pid . '.', 422);
                $why = trim((string) ($in['value'] ?? ''));
                if ($act === 'taskoff' && $why === '') Response::error('Say why it\'s going off: it\'s shown with it.', 422);
                Automation::setTask($pdo, $pid, $act === 'taskon', $why, $uid, $who);
                Response::success(['message' => $act === 'taskon' ? (string) $tk['name'] . ' is switched on: it\'s planned from the next run.' : (string) $tk['name'] . ' is switched off: no run takes it until it\'s on again.']); }
            // maintenance (0171 §2.5, release 4a), Automation's permission: maintout, a row's Take out (why asked; back after Thresholds' control_maint_hours);
            // maintplan, the Maintenance form (process, from, Back by, why; times as moments); maintback, Put back (its check
            // first); maintcancel, a window not yet started
            if (in_array($act, ['maintout', 'maintplan', 'maintback', 'maintcancel'], true)) { require_once __DIR__ . '/../../app/lib/ControlMaintenance.php';
                if ($act === 'maintout') Response::success(['message' => ControlMaintenance::take($pdo, $pid, time(), time() + 3600 * ControlMaintenance::defaultHours($pdo), (string) ($in['value'] ?? ''), $uid, $who)]);
                if ($act === 'maintplan') { $from = (int) ($in['from'] ?? 0); $until = (int) ($in['until'] ?? 0);
                    if (!$until) Response::error('Say when it\'s back by.', 422);
                    Response::success(['message' => ControlMaintenance::take($pdo, (string) ($in['proc'] ?? ''), $from ?: time(), $until, (string) ($in['why'] ?? ''), $uid, $who)]); }
                if ($act === 'maintback') { [$okB, $msgB] = ControlMaintenance::putBack($pdo, (int) $pid, $uid, $who); if (!$okB) Response::error($msgB, 422); Response::success(['message' => $msgB]); }
                Response::success(['message' => ControlMaintenance::cancel($pdo, (int) $pid, $who)]); }
            if ($act === 'start') { ControlBoard::cleared($pdo); $st = SystemStart::start($pdo, $uid, $who); Response::success(['message' => 'Starting: each workflow and task is confirmed, then switched on in order. ' . (string) ($st['items']['verify']['why'] ?? '')]); }
            if ($act === 'shutdown') Response::success(['message' => ControlBoard::shutDown($pdo, $uid, $who)]);
            if ($act === 'pauseall') Response::success(['message' => SystemStart::pauseAll($pdo, $uid, $who)]);
        } catch (InvalidArgumentException | RuntimeException $x) { Response::error($x->getMessage(), 422); }
        Response::error('That can\'t be done from the board.', 400);
    }
    if ($mod === 'config' && $t === 'preflight') {
        require_once __DIR__ . '/../../app/lib/Preflight.php';
        require_once __DIR__ . '/../../app/admin/preflight-console.php';
        [$can, $why] = Preflight::can();
        if (!$can) Response::error($why . '.', 403);
        if (!$post && $act === 'report') {   // the plain-text report, to read or send to Claude (Jason, 2026-11-17); run=<id>: a logged run's, as it stood then
            $rid = (int) ($in['run'] ?? 0); $one = $rid ? Preflight::runReport($pdo, $rid) : null;
            if ($rid && !$one) Response::error('That run isn\'t in the log any more (the last 50 are kept).', 404);
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="preflight-' . gmdate('Y-m-d-Hi', $one ? $one[1] : time()) . '.txt"');
            echo $one ? $one[0] : Preflight::report($pdo);
            exit;
        }
        if (!$post) Response::error('Send it as a POST.', 405);
        $who = (string) (Access::staff()['name'] ?? $me['display_name'] ?? '');
        try {
            if ($act === 'run') { $id = (string) ($in['id'] ?? ''); $r = Preflight::run($pdo, $id, $who); Response::success(['s' => $r['s'], 'said' => $r['said'], 'at' => $r['at'], 'html' => tv_preflight_line($pdo, $id, $r), 'counts' => tv_preflight_counts($pdo)]); }   // said, at: Outside services' card Test
            if ($act === 'fix') Response::success(['message' => Preflight::fix($pdo, (string) ($in['fix'] ?? ''), $uid), 'counts' => tv_preflight_counts($pdo)]);
            if ($act === 'done') { Preflight::finished($pdo, $who, (int) ($in['start'] ?? 0)); Response::success(['counts' => tv_preflight_counts($pdo), 'log' => tv_preflight_log($pdo), 'message' => 'The pre-flight ran from start to finish.']); }
            // Run all on the server (Jason, 2026-11-17: "Preflight only works if I stay on that page"): start answers at once
            // with the run's state, then this request carries on working through the lines after the page has its answer,
            // whether the page stays or not; status: how far it is, and the lines finished since the page last asked
            if ($act === 'cancel') { Preflight::cancelRun($pdo, $who); Response::success(['live' => tv_preflight_live($pdo, 0), 'log' => tv_preflight_log($pdo)]); }
            if ($act === 'start') {
                // a run that stopped part way waits for the person's answer (Jason, 2026-11-17): mode continue or new; without
                // one, the page is told and asks (Continue, Start a new run, Cancel)
                $mode = in_array((string) ($in['mode'] ?? ''), ['continue', 'new'], true) ? (string) $in['mode'] : '';
                $l = Preflight::startRun($pdo, $who, $mode, (string) ($in['group'] ?? '') === 'switchon' ? 'switchon' : '');   // group: Check before switching on alone (the Workflow board's Run the check)
                if (($l['state'] ?? '') !== 'running') Response::success(['live' => tv_preflight_live($pdo, 0)]);
                $out = json_encode(['ok' => true, 'live' => tv_preflight_live($pdo, 0)], JSON_UNESCAPED_SLASHES);
                if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // the person's other pages never wait for this request
                ignore_user_abort(true);
                while (ob_get_level() > 0) ob_end_clean();
                header('Content-Type: application/json'); header('Content-Length: ' . strlen($out)); header('Connection: close');
                echo $out;
                // the answer reaches the page now, on PHP-FPM or on LiteSpeed (Bluehost), so the page starts following the run
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); elseif (function_exists('litespeed_finish_request')) litespeed_finish_request(); else flush();
                Preflight::work($pdo, 0);   // to the end, or until the server stops it (then preflight_tick or Run all picks it up)
                exit;
            }
            if ($act === 'status') Response::success(['live' => tv_preflight_live($pdo, max(0, (int) ($in['from'] ?? 0)))]);
        } catch (RuntimeException | InvalidArgumentException $x) { Response::error($x->getMessage(), 422); }
        Response::error('Unknown action.');
    }

    // ---- System Configuration: staging (decisions/0145 Phase 3). Founder only. setup: makes or completes staging.trvgo.us
    // and gives the test accounts new sign-ins (the shown-once block, with staging's viewing password), then starts the
    // first refresh; refresh: starts or carries on a refresh, a step at a time (the page asks again until it's done);
    // wayin: Check the way in, which asks staging several ways and says which layer answered (decisions/0159 step 1).
    if ($mod === 'config' && $t === 'staging') {
        require_once __DIR__ . '/../../app/lib/StagingSite.php';
        if ($act === 'status') Response::success(['progress' => StagingSite::progressNow($pdo)]);
        // staging as a whole (decisions/0165 §5): keep caught up, its jobs, preview, publish, conflicts, held, roll back.
        // Each checks its own permission (StagingApi); reading (summary, held, conflicts, plan, tosend, carry) may be a GET.
        require_once __DIR__ . '/../../app/lib/StagingApi.php';
        if (in_array($act, StagingApi::ACTS, true)) {
            if (!$post && !in_array($act, ['summary', 'held', 'conflicts', 'plan', 'tosend', 'carry', 'startover_losses'], true)) Response::error('Send it as a POST.', 405);
            $tz0 = preg_match('#^[A-Za-z_]+(/[A-Za-z0-9_+-]+)*$#', (string) ($in['tz'] ?? '')) ? (string) $in['tz'] : '';
            try { Response::success(StagingApi::act($pdo, $act, $in, [$uid, (string) ($me['display_name'] ?? ''), (string) ($me['email'] ?? ''), $tz0])); }
            catch (RuntimeException | InvalidArgumentException $x) { Response::error($x->getMessage(), 422); }
        }
        if (!$post) Response::error('Send it as a POST.', 405);
        [$can, $why] = StagingSite::canManage();
        if (!$can) Response::error($why, 403);
        $tz = preg_match('#^[A-Za-z_]+(/[A-Za-z0-9_+-]+)*$#', (string) ($in['tz'] ?? '')) ? (string) $in['tz'] : '';
        if ($act === 'setup') {
            $done = StagingSite::setup($pdo, $uid, $tz);
            require_once __DIR__ . '/../../app/lib/TestAccounts.php';
            $block = ''; $made = [];
            if (TestAccounts::ready($pdo)) { $me2 = Access::staff(); $r = TestAccounts::makeAll($pdo, (int) ($me2['id'] ?? 0), $uid); $block = $r['block']; $made = $r['made']; }
            else { $v = StagingSite::newViewing($pdo); $block = json_encode(['made_at' => date('c'), 'staging' => $v], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); $made = ['a staging viewing password (the test accounts aren\'t made yet: their database update runs first)']; }
            $p = StagingSite::start($pdo, $uid, $tz);
            StagingSite::kick($pdo);   // the server carries it on, page open or not
            Response::success(['log' => $done['log'], 'block' => $block, 'made' => $made, 'progress' => $p,
                'message' => 'Staging is set up. Copy the sign-ins below into CHECK_ACCOUNTS now (they\'re shown once); the copy of the live site is going in.']);
        }
        if ($act === 'wayin') {   // Check the way in (decisions/0159 step 1): which layer asks for the password on Open staging
            $me4 = Access::staff();
            try { [$rows, $text] = StagingSite::checkWayIn($pdo, $uid, (string) ($me4['name'] ?? $me['display_name'] ?? ''), (int) ($me4['id'] ?? 0)); }
            catch (RuntimeException $x) { Response::error($x->getMessage(), 422); }
            Response::success(['rows' => $rows, 'text' => $text, 'message' => 'Checked: the answers are below. Copy them and send them to Claude.']);
        }
        if ($act === 'refresh') {
            $r = StagingSite::running($pdo);
            if (!$r) StagingSite::start($pdo, $uid, $tz);
            if (!$r || time() - (int) ($r['updated'] ?? 0) > 120) StagingSite::kick($pdo);   // started, or nothing moved for two minutes
            Response::success(['progress' => StagingSite::step($pdo, 20)]);
        }
        Response::error('Unknown action.');
    }

    // ---- System Configuration: releases (decisions/0145 Phase 5). Each action checks its own permission (Releases::can):
    // releases.publish, .schedule, .turn_off, .rollback, Founder only until a role is given one. GET download: the release's
    // zip or its rollback zip, for an upload by FTP.
    if ($mod === 'config' && $t === 'releases') {
        require_once __DIR__ . '/../../app/lib/Releases.php';
        require_once __DIR__ . '/../../app/lib/Switches.php';
        if (!Switches::on($pdo, 'releases')) Response::error('The Releases page isn\'t on for you yet (Switches → Releases page).', 403);
        try {
            if ($act === 'enter') {   // Open on staging: past its viewing password, as a test account, with the staging bar
                $me3 = Access::staff();
                $url = Releases::enterUrl($pdo, (int) ($in['id'] ?? 0), (string) ($in['as'] ?? ''), (string) ($in['to'] ?? '/'), $uid, (string) ($me3['name'] ?? $me['display_name'] ?? ''), (int) ($me3['id'] ?? 0));
                header('Location: ' . $url, true, 302);
                exit;
            }
            if ($act === 'download') {
                [$file, $name] = Releases::zip($pdo, (int) ($in['id'] ?? 0), ($in['which'] ?? '') === 'rollback' ? 'rollback' : 'release');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Content-Length: ' . (string) filesize($file));
                header('Cache-Control: no-store');
                readfile($file);
                exit;
            }
            if (!$post) Response::error('Send it as a POST.', 405);
            if ($act === 'pull') Response::success(['message' => Releases::pullNow($pdo, $uid)]);   // Check for builds (git's inbox, now)
            if ($act === 'settings') Response::success(['message' => Releases::saveSettings($pdo, (array) ($in['settings'] ?? []))]);
            Response::success(['message' => Releases::act($pdo, (int) ($in['id'] ?? 0), $act, (array) ($in['args'] ?? []), $uid)]);
        } catch (RuntimeException $x) { Response::error($x->getMessage(), 422); }
    }

    // ---- a tool --------------------------------------------------------------------------------------------------------
    $key = $mod . '/' . $t;
    $tool = Modules::tool($key);
    if (!$tool) Response::error('Not an admin tool.', 404);
    [$ok, $why] = Modules::canTool($key);
    if (!$ok) Response::error($why . '.', 403);

    if ($act === 'list') {
        $per = in_array((int) ($in['per'] ?? 50), [10, 25, 50, 100], true) ? (int) $in['per'] : 50;   // so many a page (the lists' standard)
        $in['per'] = $per;
        [$rows, $total, $counts] = ($tool['list'])($pdo, $in, $uid);
        $stage = (string) ($in['stage'] ?? '');
        $paged = $total > count($rows) && empty($tool['pages_all']);   // the tool pages itself
        $capped = !$paged && $stage === '' && in_array(count($rows), [200, 300, 400, 500, 600], true);   // its query's cap reached: more exist (T4)
        if (!$counts && !empty($tool['workflow'])) foreach ($rows as $r) if (isset($r['stage'])) $counts[$r['stage']] = ($counts[$r['stage']] ?? 0) + 1;
        if (!$paged) {
            if ($stage !== '') { $set = explode(',', $stage); $rows = array_values(array_filter($rows, fn ($r) => in_array((string) ($r['stage'] ?? ''), $set, true))); }   // a metric can stand for several stages ("To make")
            $sort = (string) ($in['sort'] ?? '');
            if ($sort !== '' && $rows) {   // all the rows are here: any column sorts, inside groups too (T4)
                $d = ($in['dir'] ?? '') === 'desc' ? -1 : 1;
                $key = function ($v): string {   // a status by its level, then its word; anything else as text
                    if (!is_array($v)) return (string) $v;
                    return isset($v['tone']) ? (['bad' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3][$v['tone']] ?? 4) . ' ' . (string) ($v['word'] ?? '') : (string) ($v['word'] ?? $v[0] ?? '');
                };
                $cmp = fn ($x, $y) => $d * strnatcasecmp($key($x[$sort] ?? ''), $key($y[$sort] ?? ''));
                if (array_filter($rows, fn ($r) => isset($r['_group']))) {
                    $kind = ''; foreach ((array) ($tool['columns'] ?? []) as $c) if ((string) $c[0] === $sort) $kind = (string) ($c[2] ?? '');
                    foreach ($rows as &$g) if (isset($g['_group']['rows'])) usort($g['_group']['rows'], $cmp);
                    unset($g);
                    $gv = fn ($r) => isset($r['_group']) ? ($kind === 'name' ? (string) ($r['_group']['label'] ?? '') : ($kind === 'status' ? $r['_group']['status'] ?? '' : null)) : ($r[$sort] ?? '');
                    if ($kind === 'name' || $kind === 'status') usort($rows, fn ($x, $y) => $d * strnatcasecmp($key($gv($x)), $key($gv($y))));
                } elseif (array_key_exists($sort, $rows[0])) usort($rows, $cmp);
            }
            $total = count($rows);
            $page = max(0, (int) ($in['page'] ?? 0));
            $rows = array_slice($rows, $page * $per, $per);
        }
        $sortable = $paged ? (array) ($tool['sortable'] ?? []) : array_map(fn ($c) => (string) $c[0], (array) ($tool['columns'] ?? []));
        Response::success(['rows' => $rows, 'total' => $total, 'capped' => $capped, 'counts' => (object) $counts, 'per' => $per, 'sortable' => $sortable]);
    }
    if ($act === 'get') Response::success(['item' => ak_detail($pdo, $key, $tool, (int) ($in['id'] ?? 0))]);
    if (!$post) Response::error('Send it as a POST.', 405);
    if ($act === 'move') {
        if (empty($tool['workflow'])) Response::error('This tool has no workflow.');
        $id = (int) ($in['id'] ?? 0);
        Workflow::move($pdo, $tool['workflow'], $id, (string) ($in['move'] ?? ''), (array) ($in['input'] ?? []), $uid);
        Response::success(['item' => ak_detail($pdo, $key, $tool, $id), 'message' => 'Done.']);
    }
    if ($act === 'act') {   // an action from the detail drawer (a note, tags, an edit): the tool says what it does
        if (empty($tool['act'])) Response::error('Nothing to do here.');
        [$aok, $awhy] = Access::can($key . '.edit', $tool['edit'] ?? ['min' => 'staff', 'depts' => []]);
        if (!$aok) Response::error($awhy . '.', 403);
        $id = (int) ($in['id'] ?? 0);
        $msg = ($tool['act'])($pdo, $id, (string) ($in['act'] ?? ''), (array) ($in['input'] ?? []), $uid);
        if (is_array($msg) && isset($msg['draft'])) Response::success(['draft' => (string) $msg['draft'], 'message' => (string) ($msg['message'] ?? '')]);   // written words for a box, not saved
        if (is_array($msg) && !empty($msg['ask'])) Response::success(['ask' => $msg['ask']]);   // a question first (a licensed item: is it, and its conditions); the page sends the answer
        if (is_array($msg) && isset($msg['copy'])) Response::success(['message' => (string) ($msg['message'] ?? ''), 'bad' => !empty($msg['bad']), 'copy' => (string) $msg['copy'], 'copy_label' => (string) ($msg['copy_label'] ?? 'Copy the link')]);   // words with the one thing to do: a link to copy (never a dead end)
        if (is_array($msg) && !empty($msg['gone'])) Response::success(['gone' => true, 'message' => (string) ($msg['message'] ?? 'Deleted.')]);   // the record is gone: the drawer closes
        Response::success(['item' => ak_detail($pdo, $key, $tool, $id), 'message' => is_string($msg) ? $msg : 'Done.']);
    }
    if ($act === 'create') {
        $c = $tool['create'] ?? null;
        if (!$c || empty($c['do'])) Response::error('Nothing to make here.');
        [$cok, $cwhy] = Access::can($key . '.create', $c['rule'] ?? ['min' => 'staff', 'depts' => []]);
        if (!$cok) Response::error($cwhy . '.', 403);
        $vals = (array) ($in['input'] ?? []);
        foreach ((is_callable($c['fields'] ?? null) ? ($c['fields'])($pdo) : (array) ($c['fields'] ?? [])) as $f) if (($f[4] ?? '') !== 'optional' && trim((string) ($vals[$f[0]] ?? '')) === '') Response::error('Fill in ' . strtolower($f[1]) . '.');
        Response::success(($c['do'])($pdo, $vals, $uid));
    }
    Response::error('Unknown action.');
} catch (InvalidArgumentException | RuntimeException $e) {
    Response::error($e->getMessage());
} catch (Throwable $e) {
    error_log('admin api ' . $path . ': ' . $e->getMessage());
    Response::error('Something went wrong. Try again.', 500);
}
