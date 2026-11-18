<?php
// app/admin/control-console.php — System Configuration → System Control Board (decisions/0171, release 2b; laid out again
// after Jason's example board, 2026-11-18: "adjust the page layout and presentation of data so I can better use and
// understand our system board"). From the top:
// - a status strip: what's held now, the three core hosts and when the pre-flight last ran;
// - the six stages, and where the system stands;
// - the system's own controls, with a filter and Open all / Close all;
// - two panels: what's holding now (each opens its process) and the four query timings against their limits;
// - each system a card, its processes rows in columns (Process, Where it stands, Last 24 hours). Each row opens under its
//   name to its whole process: Route, Its check, Needs (each source in order, ours first, which works), Last 24 hours
//   (what came in and where every one went), runs, Records touched, and its controls.
// Buttons answer in place (js/admin-control.js); the board draws itself again every 30 seconds while it's open. The data
// is ControlBoard's (app/lib/ControlBoard.php), read from the pre-flight's stored lines and the ledger: nothing is tested
// by opening the page.
declare(strict_types=1);

require_once __DIR__ . '/../lib/ControlBoard.php';
require_once __DIR__ . '/../lib/ControlAlarms.php';
if (!function_exists('tv_w_e')) require_once __DIR__ . '/widgets.php';

function tv_cb_mark(string $light): string
{
    $m = ['ok' => ['#DCFCE7', '#15803D', 'check', 'Working'], 'bad' => ['#FEE2E2', '#B91C1C', 'x', 'Needs a look'], 'warn' => ['#FEF3C7', '#B45309', 'check', 'On a backup or near a limit'],
          'off' => ['#F1EDE6', '#8a7a68', 'dash', 'Switched off'], 'wait' => ['#F1EDE6', '#8a7a68', 'clock', 'Not tested yet'], 'new' => ['#F1EDE6', '#8a7a68', 'clock', 'Not tested yet'], 'na' => ['#F1EDE6', '#8a7a68', 'dash', 'Nothing to do']][$light] ?? ['#F1EDE6', '#8a7a68', 'dash', ''];
    $ic = function_exists('tv_icon') ? tv_icon($m[2], 'cb-svg') : ($light === 'bad' ? '✗' : '✓');
    return '<span class="cb-m" role="img" aria-label="' . tv_w_e($m[3]) . '" title="' . tv_w_e($m[3]) . '" style="background:' . $m[0] . ';color:' . $m[1] . '">' . $ic . '</span>';
}

function tv_cb_record_link(string $kind, int $id, string $what = ''): string
{
    $u = $kind === 'listing' ? '/admin/listing-edit.php?id=' . $id : ($kind === 'review' ? '/admin/listing-intake.php?row=' . $id : '');
    $label = ($kind === 'listing' ? 'Listing #' : ($kind === 'review' ? 'Review item #' : ucfirst($kind) . ' #')) . $id . ($what !== '' ? ' (' . $what . ')' : '');
    return $u !== '' ? '<a href="' . tv_w_e($u) . '" data-peek-frame class="underline cb-rec">' . tv_w_e($label) . '</a>' : tv_w_e($label);
}

function tv_cb_time(int $at): string
{
    return $at ? '<time datetime="' . gmdate('c', $at) . '" data-tv-time>' . gmdate('M j, H:i', $at) . ' UTC</time>' : 'not run yet';
}

/** One line of a process's detail: its label, then what it says, on the same line (above it, on a phone). */
function tv_cb_kv(string $label, string $html): string
{
    return '<div class="cb-kv"><span class="cb-lb">' . tv_w_e($label) . ':</span><div class="cb-kd">' . $html . '</div></div>';
}

/**
 * A row's columns (laid out after Jason's example, 2026-11-18): the line under its name, its stage, where it stands in a
 * few words, the last 24 hours (runs; what came in and how much of it is accounted for), its speed, and its OK rate.
 */
function tv_cb_row(string $id, array $p, array $needs, string $sysStage = 'observe'): array
{
    require_once __DIR__ . '/../lib/Preflight.php';
    $c = $p['calls'];
    // under its name: the phases a workflow runs in (0164 §7), or what kind of thing it is
    $sub = '';
    if (str_starts_with($id, 'wf:')) { $ph = []; foreach (Preflight::PHASES as $n => [$pn, $wfs]) if (in_array(substr($id, 3), $wfs, true)) $ph[] = $n;
        $sub = $ph ? (count($ph) === 1 ? 'Phase ' . $ph[0] . ' · ' . Preflight::PHASES[$ph[0]][0] : 'Phases ' . implode(', ', $ph)) : 'Workflow'; }
    elseif (str_starts_with($id, 't:')) $sub = 'A task';
    elseif (str_starts_with($id, 'wk:')) $sub = 'A worker on Railway';
    elseif (str_starts_with($id, 'svc:')) $sub = 'An outside service';
    elseif (str_starts_with($id, 'job:')) $sub = 'A site job';
    // its stage
    $stage = $p['switch'] === 'off' ? ['off', 'Shut down'] : ($p['light'] === 'bad' ? ['bad', $p['switch'] === 'on' ? 'Holding' : 'Needs a look']
        : ($p['switch'] === 'on' ? ($sysStage === 'shutdown' ? ['new', 'Standby'] : ['ok', 'Observation']) : (in_array($p['light'], ['ok', 'warn'], true) ? ['ok', 'Working'] : ['new', 'Pre-flight'])));
    // where it stands, in a few words: what holds it, else why it's off, else its needs
    $where = '';
    foreach ((array) ($p['line']['extra']['parts'] ?? []) as [$txt, $st]) if ($st === 'bad') { $where = 'Holds it: ' . $txt; break; }
    if ($where === '') $where = (string) preg_replace('/^[^:]{0,40} is paused: /u', 'Paused: ', $p['words']);
    if ($needs) { $ok = count(array_filter($needs, fn ($x) => in_array($x[1], ['ok', 'warn'], true))); $where = rtrim($where, '.') . '. ' . $ok . ' of ' . count($needs) . ' needs answered.'; }
    // the last 24 hours
    $nRuns = array_sum($p['outcomes']) + $p['jobs'][0] + $p['jobs'][1];
    $sorted = $c['stored'] + $c['updated'] + $c['review'] + $c['refused'];
    $runs = $nRuns ? [number_format($nRuns) . ' run' . ($nRuns === 1 ? '' : 's'), '', false] : ($c['calls'] ? [number_format($c['calls']) . ' call' . ($c['calls'] === 1 ? '' : 's'), '', false] : ['—', '', false]);
    if ($c['received'] > 0) { $pct = (int) floor(100 * min($sorted, $c['received']) / $c['received']); $runs[1] = number_format($c['received']) . ' in · ' . $pct . '% accounted'; $runs[2] = $pct === 100; }
    $speed = $c['calls'] ? [($c['received'] > 0 ? round($c['ms'] * $c['calls'] / $c['received']) . ' ms/rec' : $c['ms'] . ' ms a call'), $c['received'] > 0 ? $c['ms'] . ' ms a call' : ''] : ['—', ''];
    // its OK rate: calls without a server error, runs without a failure
    $good = ($c['calls'] - $c['errors']) + $p['jobs'][0] + (array_sum($p['outcomes']) - (int) ($p['outcomes']['failed'] ?? 0));
    $all = $c['calls'] + $p['jobs'][0] + $p['jobs'][1] + array_sum($p['outcomes']);
    return ['sub' => $sub, 'stage' => $stage, 'where' => $where, 'runs' => $runs, 'speed' => $speed, 'ok' => $all ? round(100 * $good / $all, 1) . '%' : '—'];
}

/** Its check in a line: what holds it first, else that every part passes; its parts on a toggle. */
function tv_cb_check(array $line): string
{
    $e = 'tv_w_e'; $s = (string) ($line['s'] ?? 'new'); $parts = (array) ($line['extra']['parts'] ?? []);
    $head = tv_cb_mark($s) . ' ';
    if ($parts) {
        $bad = array_values(array_filter($parts, fn ($x) => ($x[1] ?? null) === 'bad'));
        $head .= $bad ? '<b class="cb-bad">Holds it:</b> ' . $e(implode(' ', array_column($bad, 0))) : $e('Every part passes: ' . implode(', ', array_map(fn ($x) => strtolower((string) strtok((string) $x[0], ':')), array_filter($parts, fn ($x) => ($x[1] ?? null) !== null)))) . '.';
        usort($parts, fn ($a, $b) => (($b[1] ?? '') === 'bad') <=> (($a[1] ?? '') === 'bad'));
        $li = ''; foreach ($parts as [$txt, $st]) $li .= '<li>' . ($st !== null ? tv_cb_mark((string) $st) : '<span class="cb-m" aria-hidden="true"></span>') . '<span>' . $e((string) $txt) . '</span></li>';
        return $head . ' <small>' . tv_cb_time((int) ($line['at'] ?? 0)) . '</small><details class="cb-more"><summary>Its ' . count($parts) . ' parts</summary><ul class="cb-parts">' . $li . '</ul></details>';
    }
    $said = (string) ($line['said'] ?? '');
    if (mb_strlen($said) <= 160) return $head . $e($said) . ' <small>' . tv_cb_time((int) ($line['at'] ?? 0)) . '</small>';
    $cut = (string) preg_replace('/\s+\S*$/u', '', mb_substr($said, 0, 160));
    return $head . $e($cut) . ' … <small>' . tv_cb_time((int) ($line['at'] ?? 0)) . '</small><details class="cb-more"><summary>All of it</summary><p>' . $e($said) . '</p></details>';
}

/** The needs as chips: each need, ✓ or ✗, and the source that answered it (all its sources in order on hover); a chip tests it. */
function tv_cb_needs(array $needs, bool $canTest, string $whyTest): string
{
    $e = 'tv_w_e'; $h = '<div class="cb-chips">';
    foreach ($needs as $k => [$name, $s, $src, $fallback, $soft, $at]) {
        $by = ''; foreach ($src as $x) if ($x[2] === true) { $by = (string) $x[0]; break; }
        $what = $s === 'new' ? 'not tested' : ($by !== '' ? ($s === 'warn' ? 'on a backup: ' : '') . preg_replace('/^Ours \((.*)\)$/u', '$1', $by) : 'nothing works: ' . $fallback);
        $title = 'Its sources, ours first: ' . implode(' → ', array_map(fn ($i, $x) => ($i + 1) . '. ' . $x[0] . ($x[2] === null ? '' : ($x[2] ? ' ✓' : ' ✗')), array_keys($src), $src)) . ($canTest ? '. Press to test it.' : '');
        $mk = ['ok' => '✓', 'warn' => '✓', 'bad' => '✗'][$s] ?? '…';
        $h .= '<button type="button" class="cb-chip cb-chip-' . $e(in_array($s, ['ok', 'warn', 'bad'], true) ? $s : 'new') . '" id="cb-nd-' . $e($k) . '" data-cb="check" data-cb-id="nd:' . $e($k) . '" title="' . $e($title) . '" aria-label="' . $e($name . ': ' . $what . '. ' . $title) . '"' . ($canTest ? '' : ' disabled') . '>'
            . $e($name) . ' <b>' . $mk . '</b> <small>(' . $e($what) . ')</small></button>';
    }
    return $h . '</div>';
}

/** A process's controls: short on its row (Check, Start, Shut down), named in full under it. */
function tv_cb_controls(string $id, array $p, array $res, callable $btn, bool $canPf, string $whyPf, bool $canRun, string $wNo, bool $short): string
{
    $x = ['class' => $short ? 'ak-b cb-xs' : 'ak-b'];
    $a = '';
    if (isset($res[$id]) || str_starts_with($id, 'wf:') || str_starts_with($id, 't:') || str_starts_with($id, 'svc:') || str_starts_with($id, 'site:') || (str_starts_with($id, 'wk:') && ControlRegister::WORKERS[substr($id, 3)][3] !== ''))
        $a .= $btn('check', $short ? 'Check' : 'Run its check', $canPf, $whyPf, $id, $x);
    if ($p['switch'] === 'on') $a .= $btn('stop', $short ? 'Shut down' : 'Shut it down', $canRun, $wNo, $id, $x + ['data-confirm' => 'Shut ' . $p['name'] . ' down? Its tasks first, then the workflow. What\'s running finishes.', 'data-yes' => 'Shut it down']);
    if ($p['switch'] === 'off') $a .= $btn('start1', $short ? 'Start' : 'Start it', $canRun, $wNo, $id, $x);
    if (!$short && str_starts_with($id, 'svc:')) $a .= '<a class="ak-b" href="/admin/config/register#svc-' . tv_w_e(substr($id, 4)) . '" data-peek-frame>Its register entry</a>';
    return $a;
}

/** The alarm list (0171 §7): one line each, worst first, its fix and Acknowledge in place; closed on load. */
function tv_cb_alarms(array $alarms, array $ac, array $names, callable $pid, bool $canPf, string $whyPf, callable $btn): string
{
    $e = 'tv_w_e';
    $open = array_values(array_filter($alarms, fn ($x) => $x['cleared_at'] === null));
    $sum = $ac['trip'] + $ac['warning'] ? implode(', ', array_filter([$ac['trip'] ? $ac['trip'] . ' trip' . ($ac['trip'] === 1 ? '' : 's') : '', $ac['warning'] ? $ac['warning'] . ' warning' . ($ac['warning'] === 1 ? '' : 's') : ''])) : 'none open';
    if ($ac['unseen']) $sum .= '; ' . $ac['unseen'] . ' cleared before anyone saw ' . ($ac['unseen'] === 1 ? 'it' : 'them');
    $h = '<details class="ak-card tv-bleed cb-panel cb-alarms" id="cb-alarms"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($ac['trip'] ? 'bad' : ($ac['warning'] ? 'warn' : 'ok')) . '<b>Alarms</b> <small>' . $e($sum) . '</small></summary>';
    if (!$alarms) return $h . '<p data-tv-status="ok" data-tv-label="All clear:">No alarm is open. A problem raises one the moment it happens; it clears by itself when the reading is good again.</p></details>';
    $h .= '<ul class="cb-al">';
    foreach ($alarms as $a) {
        $cl = $a['cleared_at'] !== null; $lv = (string) $a['level'];
        $fx = (array) $a['fix']; $fixB = '';
        if ($fx && !$cl) $fixB = $btn((string) $fx[1], (string) $fx[0], $canPf, $whyPf, (string) ($fx[2] ?? ''), ['class' => 'ak-b cb-xs']);
        $proc = (string) $a['proc'];
        $h .= '<li class="cb-al-' . $e($cl ? 'cleared' : $lv) . '">' . tv_cb_mark($cl ? 'ok' : ($lv === 'trip' ? 'bad' : 'warn'))
            . '<div><span class="cb-pill cb-pill-' . ($cl ? 'ok' : ($lv === 'trip' ? 'bad' : 'warn')) . '">' . $e($cl ? 'Cleared' : ControlAlarms::LEVELS[$lv]) . '</span> <b>' . $e((string) $a['title']) . '</b>'
            . (isset($names[$proc]) ? ' <small>· <a href="#' . $e($pid($proc)) . '" data-cb-go="' . $e($pid($proc)) . '" class="underline">' . $e($names[$proc]) . '</a></small>' : '')
            . '<br><small>' . ($cl ? 'Cleared ' . tv_cb_time((int) strtotime($a['cleared_at'] . ' UTC')) . ': ' . $e((string) $a['cleared_how']) . ' Not seen while it was open.' : $e(trim((string) $a['means'] . ' ' . (string) $a['todo'])))
            . '</small><br><small class="cb-mono">' . ControlAlarms::KINDS[$a['kind']] . ' · ×' . (int) $a['n_times'] . ' · since ' . tv_cb_time((int) strtotime($a['first_at'] . ' UTC')) . ($a['reading'] !== '' ? ' · ' . $e((string) $a['reading']) : '')
            . ($a['ack_at'] !== null ? ' · acknowledged by ' . $e((string) ($a['ack_name'] ?? 'staff')) : '') . '</small>'
            . '<div class="cb-acts">' . $fixB . ($a['ack_at'] === null ? $btn('ack', $cl ? 'Seen' : 'Acknowledge', true, '', (string) $a['id'], ['class' => 'ak-b cb-xs']) : '') . '</div></div></li>';
    }
    return $h . '</ul></details>';
}

function tv_admin_mod_control(PDO $pdo): string
{
    $e = 'tv_w_e';
    [$can, $why] = ControlBoard::can();
    if (!$can) return '<p data-tv-status="info" data-tv-label="Not for your role:">' . $e((string) $why) . '. The System Control Board is for executives: ask one, or write to support@traversence.com.</p>';
    require_once __DIR__ . '/../lib/SystemStart.php';
    require_once __DIR__ . '/../lib/Preflight.php';
    require_once __DIR__ . '/../lib/Staging.php';
    [$canRun, $whyRun] = SystemStart::can();
    [$canPf, $whyPf] = Preflight::can();
    $staging = Staging::here();
    $csrf = (string) ($_SESSION['csrf_token'] ?? '');
    $btn = function (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) use ($e): string {
        $cls = $attr['class'] ?? 'ak-b'; unset($attr['class']);
        $a = ''; foreach ($attr as $k => $v) $a .= ' ' . $k . '="' . $e($v) . '"';
        return '<button type="button" class="' . $e($cls) . '" data-cb="' . $e($act) . '"' . ($id !== '' ? ' data-cb-id="' . $e($id) . '"' : '') . ($ok ? $a : ' disabled title="' . $e($why) . '"') . '>' . $e($label) . '</button>';
    };
    ControlBoard::settle($pdo);
    [$stage, $stageWords] = ControlBoard::stage($pdo);
    $wNo = $staging ? 'Nothing runs on staging: these work on the live site.' : $whyRun;

    // every process, read once
    $res = Preflight::results($pdo);
    $by = []; $held = [];
    foreach (ControlRegister::processes($pdo) as $id => $p) {
        $by[$p[0]][$id] = $row = ControlBoard::process($pdo, $id, $p, $res);
        if ($row['light'] === 'bad') $held[$id] = $row;
    }
    $siteNeeds = ControlBoard::needs($res, array_keys(array_filter(Preflight::NEEDS, fn ($n) => $n[0] === 'siteneeds')));
    $core = ControlBoard::core($res);
    $lastAt = 0; foreach ($res as $r) $lastAt = max($lastAt, (int) ($r['at'] ?? 0));
    $pid = fn (string $id) => 'cb-p-' . str_replace(':', '-', $id);

    $h = '<div id="cb" data-csrf="' . $e($csrf) . '">';

    // ---- the status strip: what's held, the core hosts, the last test
    $coreBad = array_filter($core, fn ($c) => $c[1] === 'bad');
    $beacon = $stage === 'shutdown' ? ['off', 'Stopped'] : ($coreBad ? ['bad', 'A core host is down: everything holds'] : ($held ? ['bad', count($held) . ' held: ' . implode(', ', array_slice(array_map(fn ($r) => $r['name'], $held), 0, 3)) . (count($held) > 3 ? ' and ' . (count($held) - 3) . ' more' : '')] : ['ok', 'All clear: nothing held']));
    $h .= '<div class="cb-top cb-strip" role="status"><span class="cb-beacon cb-b-' . $beacon[0] . '">' . tv_cb_mark($beacon[0]) . $e($beacon[1]) . '</span>'
        . '<span class="cb-core"><b>Core hosts</b> ' . implode(' ', array_map(fn ($c) => '<span class="cb-ch">' . tv_cb_mark($c[1]) . $e($c[0]) . '</span>', $core)) . '</span>'
        . '<span class="cb-last">Last tested ' . tv_cb_time($lastAt) . '</span></div>';

    // ---- the six stages, and where the system stands
    $h .= '<div class="cb-stages" role="list" aria-label="Stages">';
    $i = 0;
    foreach (ControlBoard::STAGES as $k => $label) {
        $i++; $soon = in_array($k, ['maintenance', 'history'], true);
        $h .= '<div role="listitem" class="cb-st' . ($k === $stage ? ' on' : '') . ($soon ? ' soon' : '') . '"' . ($k === $stage ? ' aria-current="step"' : '') . ($soon ? ' title="Coming in the board\'s release 4"' : '') . '><small>' . $i . ($soon ? ' · coming' : '') . '</small>' . $e($label) . '</div>';
    }
    $h .= '</div><p class="cb-stage" data-tv-status="' . ($stage === 'observe' ? 'ok' : 'info') . '" data-tv-label="' . $e(ControlBoard::STAGES[$stage]) . ':">' . $e($stageWords) . '</p>';

    // ---- the system's own controls; find, open all, close all
    $h .= '<div class="cb-bar"><div class="cb-acts">'
        . $btn('preflight', 'Run the pre-flight', $canPf && !$staging, $staging ? $wNo : $whyPf)
        . $btn('start', 'Start the system', $canRun && !$staging, $wNo, '', ['data-confirm' => 'Start the system? It confirms each workflow and task, then switches them on in order.', 'data-yes' => 'Start it'])
        . $btn('shutdown', 'Shut down in order', $canRun && !$staging, $wNo, '', ['data-confirm' => 'Shut the whole system down in order? The tasks, then the workflows in reverse order, then the master switch and the crawler\'s schedule. What\'s running finishes.', 'data-yes' => 'Shut it down'])
        . $btn('pauseall', 'Pause everything', $canRun && !$staging, $wNo, '', ['class' => 'ak-b cb-danger', 'data-confirm' => 'Pause everything at once? The emergency stop: the master switch off and the crawler\'s schedule removed.', 'data-yes' => 'Pause everything', 'data-danger' => '1'])
        . '</div><div class="cb-acts cb-find"><input type="search" class="ak-q" data-cb-filter placeholder="Find a process, need or source" aria-label="Find a process, need or source">'
        . '<button type="button" class="ak-b" data-cb-all="1">Open all</button><button type="button" class="ak-b" data-cb-all="0">Close all</button></div></div>';

    // ---- two panels: holding now; the query timings
    // ---- the alarms (decisions/0171 §7, release 3a): one list, worst first; closed on load with its count
    $alarms = ControlAlarms::list($pdo); $ac = ControlAlarms::counts($pdo);
    $names = []; foreach ($by as $rowsN) foreach ($rowsN as $idN => $pN) $names[$idN] = $pN['name'];
    $h .= tv_cb_alarms($alarms, $ac, $names, $pid, $canPf, (string) $whyPf, $btn);
    // a process with an open trip is held too, said by its alarm
    foreach ($alarms as $x) if ($x['level'] === 'trip' && $x['cleared_at'] === null && !isset($held[$x['proc']]))
        foreach ($by as $rowsN) if (isset($rowsN[$x['proc']])) { $held[$x['proc']] = ['name' => $rowsN[$x['proc']]['name'], 'sys' => $rowsN[$x['proc']]['sys'], 'words' => 'Trip: ' . $x['title']] + $rowsN[$x['proc']]; break; }
    // both start closed, a line each saying what's in them (Jason, 2026-11-18: "these need to be collapsed on load")
    $hn = array_values(array_map(fn ($r) => $r['name'], $held));
    $h .= '<div class="cb-panels"><details class="ak-card tv-bleed cb-panel" id="cb-held"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($held ? 'bad' : 'ok') . '<b>Holding now</b> <small>' . ($held ? count($held) . ': ' . $e(implode(', ', array_slice($hn, 0, 2)) . (count($hn) > 2 ? ' and ' . (count($hn) - 2) . ' more' : '')) : 'nothing held') . '</small></summary>';
    if (!$held) $h .= '<p data-tv-status="ok" data-tv-label="All clear:">Nothing is held. Every process is working, switched off by choice, or not tested yet.</p>';
    else {
        $h .= '<ul class="cb-hl">';
        foreach (array_slice($held, 0, 8, true) as $id => $r) $h .= '<li>' . tv_cb_mark('bad') . '<span><a href="#' . $e($pid($id)) . '" data-cb-go="' . $e($pid($id)) . '" class="underline">' . $e($r['name']) . '</a> <small>' . $e(ControlRegister::SYSTEMS[$r['sys']] ?? '') . '</small><br><small>' . $e(mb_strlen($r['words']) > 160 ? preg_replace('/\s+\S*$/u', '', mb_substr($r['words'], 0, 160)) . ' …' : $r['words']) . '</small></span></li>';
        $h .= '</ul>' . (count($held) > 8 ? '<p class="ak-note">And ' . (count($held) - 8) . ' more, each marked with a red X below.</p>' : '');
    }
    $tms = ControlBoard::timings($pdo, $res); $slow = count(array_filter($tms, fn ($x) => $x[1] !== null && $x[3] === 'bad')); $untested = count(array_filter($tms, fn ($x) => $x[1] === null));
    $h .= '</details><details class="ak-card tv-bleed cb-panel" id="cb-times"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($slow ? 'bad' : ($untested === count($tms) ? 'new' : 'ok')) . '<b>Query timings</b> <small>' . ($slow ? $slow . ' of ' . count($tms) . ' over its limit' : ($untested === count($tms) ? 'not run yet' : 'all ' . count($tms) . ' within their limits')) . '</small></summary><table class="cb-tt"><thead><tr><th>Query</th><th>Took</th><th><a class="underline" href="/admin/config/thresholds" title="Change a limit on Thresholds">Limit</a></th><th></th></tr></thead><tbody>';
    foreach ($tms as $k => [$what, $ms, $lim, $s, $at])
        $h .= '<tr><td>' . $e(preg_replace('/ \(.*$/', '', $what)) . '<br><small>' . $e((string) (preg_match('/\((.*)\)/', $what, $mm) ? $mm[1] : '')) . '</small></td><td class="cb-num">' . ($ms === null ? '—' : number_format($ms) . ' ms') . '</td><td class="cb-num">' . ($lim ? number_format($lim) . ' ms' : '—') . '</td><td>' . tv_cb_mark($ms === null ? 'new' : $s) . '</td></tr>';
    $h .= '</tbody></table><div class="cb-acts">' . $btn('timings', 'Re-test the four', $canPf, (string) $whyPf) . '<small class="ak-note">Read only; a slow one is noted and holds nothing.</small></div></details></div>';

    // ---- the systems, each process a row in columns
    foreach (ControlRegister::SYSTEMS as $sk => $sname) {
        $rows = $by[$sk] ?? [];
        $n = array_count_values(array_column($rows, 'light'));
        $worst = !empty($n['bad']) ? 'bad' : (!empty($n['warn']) ? 'warn' : (!empty($n['ok']) ? 'ok' : 'wait'));
        $sum = [];
        foreach (['ok' => 'working', 'bad' => 'held', 'warn' => 'on a backup', 'off' => 'off', 'wait' => 'not tested'] as $k => $w) if (!empty($n[$k])) $sum[] = $n[$k] . ' ' . $w;
        $h .= '<details class="ak-card tv-bleed cb-sys" id="cb-' . $e($sk) . '" data-cb-sys="' . $e($sk) . '">'   // closed on load, held or not (Jason, 2026-11-18): its header counts the held
            . '<summary class="cb-sh"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($worst) . '<h3 class="cb-title">' . $e($sname) . '</h3><small class="cb-sum">' . $e(implode(' · ', $sum)) . '</small></summary>'
            . '<div class="cb-row cb-head" aria-hidden="true"><span></span><span></span><span>Process</span><span>Stage</span><span>Where it stands</span><span>Last 24 hours</span><span>Speed</span><span>OK</span><span></span></div>';
        foreach ($rows as $id => $p) {
            $c = $p['calls'];
            $needs = str_starts_with($id, 'wf:') ? ControlBoard::needs($res, (array) ($p['parts']['needs'] ?? [])) : [];
            $q = mb_strtolower($p['name'] . ' ' . $p['words'] . ' ' . implode(' ', array_map(fn ($x) => $x[0] . ' ' . implode(' ', array_column($x[2], 0)), $needs)));
            // its open alarms: a trip marks it red, and the alarm is where it stands
            $mine = array_values(array_filter($alarms, fn ($x) => $x['proc'] === $id && $x['cleared_at'] === null));
            if ($mine && $mine[0]['level'] === 'trip') $p['light'] = 'bad';
            $r = tv_cb_row($id, $p, $needs, $stage);
            if ($mine) $r['where'] = ControlAlarms::LEVELS[$mine[0]['level']] . ': ' . $mine[0]['title'] . (count($mine) > 1 ? ' (and ' . (count($mine) - 1) . ' more)' : '') . '. ' . $r['where'];   // switched on while the system is stopped: Standby, not Observation
            $h .= '<details class="cb-p" id="' . $e($pid($id)) . '" data-cb-p="' . $e($id) . '" data-q="' . $e($q) . '">'
                . '<summary class="cb-row"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($p['light'])
                . '<span class="cb-nm"><b class="cb-n">' . $e($p['name']) . '</b>' . ($r['sub'] !== '' ? '<small>' . $e($r['sub']) . '</small>' : '') . '</span>'
                . '<span class="cb-pill cb-pill-' . $e($r['stage'][0]) . '">' . $e($r['stage'][1]) . '</span>'
                . '<span class="cb-c">' . $e($r['where']) . '</span>'
                . '<span class="cb-k' . ($r['runs'][0] === '—' ? ' cb-none0' : '') . '"><b>' . $e($r['runs'][0]) . '</b>' . ($r['runs'][1] !== '' ? '<small class="' . ($r['runs'][2] ? 'cb-good' : '') . '">' . $e($r['runs'][1]) . '</small>' : '') . '</span>'
                . '<span class="cb-k' . ($r['speed'][0] === '—' ? ' cb-none0' : '') . '"><b>' . $e($r['speed'][0]) . '</b>' . ($r['speed'][1] !== '' ? '<small>' . $e($r['speed'][1]) . '</small>' : '') . '</span>'
                . '<span class="cb-k cb-okr"><b>' . $e($r['ok']) . '</b></span>'
                . '<span class="cb-ra">' . tv_cb_controls($id, $p, $res, $btn, $canPf, (string) $whyPf, $canRun && !$staging, $wNo, true) . '</span></summary>'
                . '<div class="cb-b cb-l-' . $e($p['light']) . '">';
            // Route
            if ($p['route']) $h .= tv_cb_kv('Route', '<code class="cb-route">' . $e((string) $p['route']['said']) . '</code>');
            if ($mine) $h .= tv_cb_kv('Alarms', implode('<br>', array_map(fn ($x) => tv_cb_mark($x['level'] === 'trip' ? 'bad' : 'warn') . ' ' . $e($x['title']) . ' <small>(' . ControlAlarms::KINDS[$x['kind']] . ', ×' . (int) $x['n_times'] . ', since ' . tv_cb_time((int) strtotime($x['first_at'] . ' UTC')) . ')</small>', $mine)) . ' <a class="underline" href="#cb-alarms" data-cb-go="cb-alarms">The alarm list</a>');
            // Its check: one line, what holds it first; its parts on a toggle
            if ($p['line']) $h .= tv_cb_kv('Its check', tv_cb_check($p['line']));
            elseif (!str_starts_with($id, 'wk:') || ControlRegister::WORKERS[substr($id, 3)][3] !== '') $h .= tv_cb_kv('Its check', 'Not run yet: press Check.');
            // Needs, as chips
            if ($needs) $h .= tv_cb_kv('Needs', tv_cb_needs($needs, $canPf, (string) $whyPf));
            if (str_starts_with($id, 'svc:') && ($u = ControlBoard::usedBy(substr($id, 4)))) $h .= tv_cb_kv('Used by', $e(implode(', ', $u)) . '.');
            // Accounting: every one accounted for
            if ($c['calls']) {
                $rest = $c['received'] - ($c['stored'] + $c['updated'] + $c['review'] + $c['refused']);
                $h .= tv_cb_kv('Accounting', '<span class="cb-acct">Received <b>' . number_format($c['received']) . '</b> = Stored <b class="cb-good">' . number_format($c['stored']) . '</b> + Updated <b>' . number_format($c['updated']) . '</b> + To Review <b>' . number_format($c['review']) . '</b> + Refused <b>' . number_format($c['refused']) . '</b>'
                    . ($rest > 0 ? ' + No change <b>' . number_format($rest) . '</b>' : '') . ' <small>(' . number_format($c['calls']) . ' worker call' . ($c['calls'] === 1 ? '' : 's') . ', ' . $c['ms'] . ' ms each' . ($c['errors'] ? ', <b class="cb-bad">' . $c['errors'] . ' server error' . ($c['errors'] === 1 ? '' : 's') . '</b>' : '') . ')</small></span>');
            }
            $runs = [];
            if ($p['outcomes']) foreach ($p['outcomes'] as $k => $v) $runs[] = $v . ' ' . str_replace('_', ' ', (string) $k);
            if ($p['jobs'][0] + $p['jobs'][1]) $runs[] = 'site jobs ' . $p['jobs'][0] . ' ran' . ($p['jobs'][1] ? ', ' . $p['jobs'][1] . ' stopped' : '');
            if ($runs) $h .= tv_cb_kv('Runs, 24 h', $e(implode(' · ', $runs)));
            $dry = ''; foreach ((array) ($p['line']['extra']['parts'] ?? []) as [$txt, $sst]) if (str_starts_with((string) $txt, 'Dry run: ')) $dry = (string) $txt;
            if ($dry !== '') $h .= tv_cb_kv('Dry run', $e(substr($dry, 9)));
            // Records touched lately
            $eps = $p['parts']['endpoints'] ?? [];
            if (str_starts_with($id, 'wk:')) { $k = substr($id, 3); $eps = ControlRegister::WORKERS[$k][2]; foreach (ControlRegister::ENDPOINTS as $ep => [, $for]) if (in_array('@' . $k, $for, true) || ($k === 'crawler' && !array_filter($for, fn ($f) => $f[0] === '@' || $f === 'W0'))) $eps[] = $ep; }
            $recs = ControlBoard::records($pdo, array_values(array_filter($eps, fn ($ep) => !in_array($ep, ['crawl/service', 'crawl/usage'], true))), 12);
            if ($recs) $h .= tv_cb_kv('Records touched lately', implode(' · ', array_map(fn ($r) => tv_cb_record_link((string) $r[0], (int) $r[1], (string) $r[2]), $recs)));
            if ($id === 'wk:crawler') {
                $rr = '';
                foreach (ControlBoard::runs($pdo, 5) as [$run, $calls, $rrs]) {
                    $sumc = ['stored' => 0, 'review' => 0, 'updated' => 0, 'refused' => 0]; foreach ($calls as $cl) foreach ($sumc as $k => $_) $sumc[$k] += (int) $cl['n_' . $k];
                    $rr .= '<details class="cb-run"><summary>Run #' . (int) $run['id'] . ' · ' . $e((string) $run['status']) . ' · ' . tv_cb_time((int) strtotime((string) $run['started_at'])) . ' · ' . count($calls) . ' calls: ' . $sumc['stored'] . ' stored, ' . $sumc['updated'] . ' updated, ' . $sumc['review'] . ' to Review, ' . $sumc['refused'] . ' refused</summary>'
                        . ($rrs ? '<p class="ak-note">' . implode(' · ', array_map(fn ($r) => tv_cb_record_link((string) $r['kind'], (int) $r['ref_id'], (string) $r['what']), $rrs)) . '</p>' : '<p class="ak-note">No records touched.</p>') . '</details>';
                }
                if ($rr !== '') $h .= tv_cb_kv('Its last runs', $rr);
            }
            // its controls, named in full
            $acts = tv_cb_controls($id, $p, $res, $btn, $canPf, (string) $whyPf, $canRun && !$staging, $wNo, false);
            if ($acts !== '') $h .= '<div class="cb-acts">' . $acts . '</div>';
            $h .= '</div></details>';
        }
        // the site: what it needs from outside, each with its sources
        if ($sk === 'site' && $siteNeeds) {
            $sn = array_count_values(array_column($siteNeeds, 1));
            $sl = !empty($sn['bad']) ? 'bad' : (!empty($sn['warn']) ? 'warn' : (!empty($sn['ok']) ? 'ok' : 'wait'));
            $h .= '<details class="cb-p" id="cb-p-site-needs" data-cb-p="site:needs" data-q="' . $e(mb_strtolower('what the site needs ' . implode(' ', array_map(fn ($x) => $x[0] . ' ' . implode(' ', array_column($x[2], 0)), $siteNeeds)))) . '">'
                . '<summary class="cb-row"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($sl) . '<span class="cb-nm"><b class="cb-n">What the site needs from outside</b><small>The site\'s own needs</small></span><span class="cb-pill cb-pill-' . $sl . '">' . ($sl === 'bad' ? 'Needs a look' : 'Working') . '</span><span class="cb-c">' . count($siteNeeds) . ' needs: ' . (int) ($sn['ok'] ?? 0) . ' answered first, ' . (int) ($sn['warn'] ?? 0) . ' on a backup or their fallback, ' . (int) ($sn['bad'] ?? 0) . ' with nothing working. None of them holds the engine.</span><span class="cb-k"><b>—</b></span><span class="cb-k"><b>—</b></span><span class="cb-k cb-okr"><b>—</b></span><span class="cb-ra"></span></summary>'
                . '<div class="cb-b cb-l-' . $sl . '">' . tv_cb_kv('Needs', tv_cb_needs($siteNeeds, $canPf, (string) $whyPf)) . '</div></details>';
        }
        $h .= '</details>';
    }

    // ---- the pre-flight, every line (its own section stays: /admin/config/preflight)
    $h .= '<details class="ak-card tv-bleed" id="preflight" style="margin-top:.6rem"><summary class="cb-sh"><span class="cb-caret" aria-hidden="true">▾</span><span></span><h3 class="cb-title">Pre-flight: every line, as tested last</h3></summary>'
        . '<p class="ak-note">The same lines as <a class="underline" href="/admin/config/preflight">Pre-flight</a>, with its Run all and each line\'s Test.</p>'
        . (function_exists('tv_admin_mod_preflight') ? tv_admin_mod_preflight($pdo) : '') . '</details>';
    $h .= '<div class="cb-none" hidden><p data-tv-status="info" data-tv-label="Nothing found:">No process, need or source has those words. Clear the box to see them all.</p></div></div>';

    $h .= '<style>'
        . '#cb .cb-m{display:inline-flex;width:1.35rem;height:1.35rem;border-radius:.3rem;align-items:center;justify-content:center;flex:0 0 auto;vertical-align:middle}#cb .cb-svg{width:.9rem;height:.9rem;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}'
        . '#cb summary{list-style:none;cursor:pointer}#cb summary::-webkit-details-marker{display:none}#cb details:not([open])>summary .cb-caret{display:inline-block;transform:rotate(-90deg)}#cb .cb-caret{color:#8a7a68;font-size:12px}'
        . '#cb .cb-strip{display:flex;flex-wrap:wrap;gap:.4rem .9rem;align-items:center;padding:.45rem .7rem;border:1px solid #E5D9C3;border-radius:.5rem;background:#F6F0E4;font-size:13px}'
        . '#cb .cb-beacon{display:inline-flex;gap:.4rem;align-items:center;font-weight:700;padding:.15rem .55rem .15rem .2rem;border-radius:.4rem}#cb .cb-b-ok{background:#E8F3EC;color:#15803D}#cb .cb-b-bad{background:#FEE2E2;color:#B91C1C}#cb .cb-b-off{background:#F1EDE6;color:#6b5d4f}'
        . '#cb .cb-core{display:inline-flex;gap:.5rem;align-items:center;flex-wrap:wrap}#cb .cb-ch{display:inline-flex;gap:.25rem;align-items:center}#cb .cb-core .cb-m,#cb .cb-beacon .cb-m{width:1.1rem;height:1.1rem}#cb .cb-last{color:#6b5d4f;margin-left:auto}'
        . '#cb .cb-stages{display:grid;grid-template-columns:repeat(6,1fr);gap:.35rem;margin:.6rem 0 .4rem}#cb .cb-st{border:1px solid #E5D9C3;border-radius:.4rem;padding:.4rem .55rem;font-size:13px;background:#FFFBF2}#cb .cb-st small{display:block;color:#8a7a68;font-size:11px}'
        . '#cb .cb-st.on{background:#E8F3EC;border-color:#8FBCA8;box-shadow:0 0 6px #8FBCA8;font-weight:600}#cb .cb-st.soon{opacity:.55}'
        . '#cb .cb-bar{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.4rem;margin:.5rem 0}#cb .cb-acts{display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;margin:.4rem 0}#cb .cb-bar .cb-acts{margin:0}#cb .cb-find .ak-q{width:15rem;max-width:100%}'
        . '#cb .cb-danger{color:#B91C1C;border-color:#E6B8B8}#cb .cb-xs{padding:.15rem .5rem;font-size:12px;min-height:0}'
        . '#cb .cb-panels{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin:.4rem 0 .6rem}#cb .cb-panel{padding:.6rem .8rem;margin:0}#cb .cb-ph{font-size:14px;display:flex;gap:.45rem;align-items:center;flex-wrap:wrap}#cb .cb-panel[open]>.cb-ph{margin:0 0 .4rem;padding-bottom:.3rem;border-bottom:1px solid #EFE6D6}#cb .cb-panels{align-items:start}#cb .cb-ph small{font-weight:400;color:#8a7a68;margin-left:.3rem}'
        . '#cb .cb-hl{list-style:none;margin:0;padding:0;display:grid;gap:.4rem}#cb .cb-hl li{display:flex;gap:.45rem;align-items:flex-start;font-size:13.5px}'
        . '#cb .cb-tt{width:100%;border-collapse:collapse;font-size:13px}#cb .cb-tt th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#8a7a68;padding:.2rem .3rem;border-bottom:1px solid #EFE6D6}#cb .cb-tt td{padding:.3rem;border-bottom:1px solid #F3ECE0;vertical-align:top}#cb .cb-tt small{color:#8a7a68}#cb .cb-num{white-space:nowrap;font-variant-numeric:tabular-nums}'
        . '#cb .cb-sys{margin:.0 0 .6rem;padding:0;overflow:hidden}#cb .cb-sh{display:flex;gap:.55rem;align-items:center;padding:.55rem .8rem;background:#F6F0E4}#cb .cb-title{margin:0;font-size:1.05rem;font-weight:700}#cb .cb-sum{margin-left:auto;color:#6b5d4f;text-align:right}'
        . '#cb .cb-row{display:grid;grid-template-columns:.9rem 1.35rem minmax(8rem,13rem) 6.6rem minmax(0,1fr) 9.5rem 6.5rem 3.6rem 10rem;gap:.2rem .7rem;align-items:center;padding:.5rem .8rem}#cb .cb-nm{display:flex;flex-direction:column}#cb .cb-nm small{color:#8a7a68;font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}#cb .cb-pill{justify-self:start;font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;padding:.12rem .45rem;border-radius:.3rem;border:1px solid;white-space:nowrap}#cb .cb-pill-ok{background:#E8F3EC;border-color:#B6D9C4;color:#14532D}#cb .cb-pill-bad{background:#FEF2F2;border-color:#E6B8B8;color:#7F1D1D}#cb .cb-pill-off,#cb .cb-pill-new,#cb .cb-pill-warn,#cb .cb-pill-wait{background:#F6F0E4;border-color:#E5D9C3;color:#6b5d4f}#cb .cb-c{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}#cb .cb-k{display:flex;flex-direction:column;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;font-variant-numeric:tabular-nums}#cb .cb-k b{font-weight:600}#cb .cb-k small{font-size:11.5px;color:#6b5d4f}#cb .cb-k small.cb-good{color:#15803D}#cb .cb-ra{display:flex;gap:.3rem;justify-content:flex-end}'
        . '#cb .cb-head{padding:.35rem .8rem;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8a7a68;border-bottom:1px solid #EFE6D6;background:#FFFBF2}'
        . '#cb .cb-p{border-top:1px solid #EFE6D6}#cb .cb-p:first-of-type{border-top:0}#cb .cb-p>summary:hover{background:#FBF6EC}#cb .cb-c{font-size:13.5px;color:#4a3f35}#cb .cb-nc{color:#6b5d4f;white-space:nowrap}'
        . '#cb .cb-b{margin:0 .8rem .7rem 2.9rem;padding:.5rem .8rem;border-left:3px solid #8FBCA8;display:grid;gap:.4rem;background:#FAF5EA;border-radius:0 .4rem .4rem 0}#cb .cb-l-bad{border-left-color:#E07A7A}#cb .cb-l-warn{border-left-color:#E5B567}#cb .cb-l-off,#cb .cb-l-wait,#cb .cb-l-na{border-left-color:#D6CBB8}'
        . '#cb .cb-kv{display:flex;flex-wrap:wrap;gap:.2rem .5rem;align-items:baseline;font-size:13.5px}#cb .cb-kd{flex:1 1 20rem;min-width:0}#cb .cb-lb{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:#6b5d4f;white-space:nowrap}#cb .cb-chips{display:flex;flex-wrap:wrap;gap:.35rem}#cb .cb-chip{font:inherit;font-size:13px;padding:.2rem .55rem;border-radius:.35rem;border:1px solid #E5D9C3;background:#FFFDF8;color:#2b241d;cursor:pointer;text-align:left}#cb .cb-chip small{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;color:#6b5d4f}#cb .cb-chip-ok b,#cb .cb-chip-warn b{color:#15803D}#cb .cb-chip-bad{border-color:#E6B8B8;background:#FEF6F6}#cb .cb-chip-bad b{color:#B91C1C}#cb .cb-chip-warn{border-color:#E5CFA3}#cb .cb-chip:hover{border-color:#B45309}#cb .cb-more>summary{font-size:12.5px;color:#92400E;text-decoration:underline;margin-top:.15rem}#cb .cb-rec{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;color:#15803D}'
        . '#cb .cb-route{font-size:12.5px;background:#FFFBF2;border:1px solid #EFE6D6;border-radius:.3rem;padding:.15rem .45rem;display:inline-block;white-space:normal}'
        . '#cb .cb-parts{list-style:none;margin:.3rem 0 0;padding:0;display:grid;gap:.3rem}#cb .cb-parts li{display:flex;gap:.45rem;align-items:flex-start}#cb .cb-parts .cb-m{width:1.15rem;height:1.15rem;margin-top:.1rem}#cb .cb-alarms{margin:.4rem 0}#cb .cb-al{list-style:none;margin:.3rem 0 0;padding:0;display:grid;gap:.5rem}#cb .cb-al li{display:flex;gap:.5rem;align-items:flex-start;padding:.45rem .55rem;border:1px solid #EFE6D6;border-radius:.4rem;background:#FFFDF8}#cb .cb-al li.cb-al-trip{border-color:#E6B8B8;background:#FEF6F6}#cb .cb-al li.cb-al-warning{border-color:#E5CFA3}#cb .cb-al .cb-acts{margin:.3rem 0 0}#cb .cb-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;color:#6b5d4f}#cb a{color:#92400E}#cb a.ak-b{color:inherit}#cb .cb-good{color:#15803D}#cb .cb-bad{color:#B91C1C}#cb .cb-acct{font-variant-numeric:tabular-nums}#cb .cb-run>summary{font-size:13px;color:#4a3f35}'
        . '@media (max-width:900px){#cb .cb-panels{grid-template-columns:1fr}#cb .cb-stages{grid-template-columns:repeat(3,1fr)}#cb .cb-row{grid-template-columns:.9rem 1.35rem minmax(0,1fr) auto auto auto}#cb .cb-c{grid-column:3/-1;order:9}#cb .cb-head{display:none}}'
        . '@media (max-width:767px){#cb .cb-row{grid-template-columns:.9rem 1.35rem minmax(0,1fr) auto;padding:.5rem .6rem;row-gap:.3rem}#cb .cb-pill{grid-column:4;grid-row:1;justify-self:end}#cb .cb-c,#cb .cb-k,#cb .cb-ra{grid-column:3/-1}#cb .cb-c{order:0}#cb .cb-k{flex-direction:row;gap:.4rem;flex-wrap:wrap}#cb .cb-okr{display:none}#cb .cb-none0{display:none}#cb .cb-ra{justify-content:flex-start}#cb .cb-head{display:none}#cb .cb-b{margin:0 .4rem .6rem .4rem}#cb .cb-sh{flex-wrap:wrap;padding:.5rem .6rem}#cb .cb-sum{margin-left:0;flex-basis:100%;text-align:left}#cb .cb-last{margin-left:0}#cb .cb-find .ak-q{flex:1 1 100%;width:auto}}'
        . '</style>'
        . '<script src="/js/tv-time.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-time.js') . '" defer></script>'
        . '<script src="/js/admin-control.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-control.js') . '" defer></script>';
    return $h;
}
