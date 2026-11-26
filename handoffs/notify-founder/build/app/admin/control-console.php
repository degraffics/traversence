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
require_once __DIR__ . '/../lib/ControlMaintenance.php';
require_once __DIR__ . '/../lib/ControlIncidents.php';
require_once __DIR__ . '/../ui/score-gauge.php';
// the gauges' one reader (the build log §7's contract: runtime, development, workflows, source), written beside this view
if (!class_exists('ControlGauges') && is_file(__DIR__ . '/../lib/ControlGauges.php')) require_once __DIR__ . '/../lib/ControlGauges.php';
if (!function_exists('tv_w_e')) require_once __DIR__ . '/widgets.php';

function tv_cb_mark(string $light): string
{
    // red only for a trip or a core host down; amber for what's held, timing out, on a backup or near a limit (Jason,
    // 2026-11-18: --tv-fail var(--cb-fail), --tv-hold var(--cb-hold), the palette's tokens shared with Pulse)
    // blue: out for maintenance on purpose (0171 §2.5), never a failure; a wrench
    if ($light === 'maint') return '<span class="cb-m" role="img" aria-label="In maintenance: out on purpose" title="In maintenance: out on purpose" style="background:var(--cb-maint-bg);color:var(--cb-maint)"><svg class="cb-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z"/></svg></span>';
    // an open warning: amber with "!", its level's mark, never a ✓ beside a warning (0175 R2a, item 7)
    if ($light === 'alarm') return '<span class="cb-m" role="img" aria-label="Warning: the system is handling it" title="Warning: the system is handling it" style="background:var(--cb-hold-bg);color:var(--cb-hold)"><svg class="cb-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M12 6v8M12 18.5v.01"/></svg></span>';
    if ($light === 'hold') return '<span class="cb-m" role="img" aria-label="Held: waiting on what it needs" title="Held: waiting on what it needs" style="background:var(--cb-hold-bg);color:var(--cb-hold)"><svg class="cb-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M9 6v12M15 6v12"/></svg></span>';
    $m = ['ok' => ['#DCFCE7', '#15803D', 'check', 'Working'], 'bad' => ['var(--cb-fail-bg)', 'var(--cb-fail)', 'power', 'Stopped: needs an admin'], 'warn' => ['var(--cb-hold-bg)', 'var(--cb-hold)', 'check', 'On a backup or near a limit'],
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
    // a critical part says the duty it carries (0171 §5's note, 2026-11-19): "Search: critical"
    try { require_once __DIR__ . '/../lib/ControlRegister.php'; $du = ControlRegister::duty(Database::connection(), $id); if ($du !== '') $sub = ControlRegister::DUTIES[$du][0] . ': critical' . ($sub !== '' ? ' · ' . $sub : ''); } catch (Throwable $x) {}
    // its stage
    $stage = $p['light'] === 'bad' ? ['bad', 'Tripped'] : ($p['light'] === 'maint' ? ['maint', 'In maintenance'] : ($p['switch'] === 'off' ? ['off', str_starts_with($id, 'svc:') ? 'Paused' : 'Shut down'] : ($p['light'] === 'hold' ? ['hold', $p['switch'] === 'on' ? 'Holding' : 'Held']
        : ($p['switch'] === 'on' ? ($sysStage === 'shutdown' ? ['new', 'Standby'] : ['ok', 'Observation']) : (in_array($p['light'], ['ok', 'warn'], true) ? ['ok', 'Working'] : ['new', 'Pre-flight'])))));
    // where it stands, in a few words: what holds it, else why it's off, else its needs
    $where = '';
    foreach ((array) ($p['line']['extra']['parts'] ?? []) as [$txt, $st]) if ($st === 'bad') { $where = 'Holds it: ' . $txt; break; }
    if ($where === '') $where = (string) preg_replace('/^[^:]{0,40} is paused: /u', 'Paused: ', $p['words']);
    if ($needs) { $ok = count(array_filter($needs, fn ($x) => in_array($x[1], ['ok', 'warn'], true))); $where = rtrim($where, '.') . '. ' . $ok . ' of ' . count($needs) . ' needs answered.'; }
    if ($id === 'site:pulse') {   // its usual gathering time (2026-11-19): the middle of its last 20 opens, against its limit
        try { require_once __DIR__ . '/../lib/ControlSensor.php'; $pdo0 = Database::connection(); [$pm, $pn] = ControlSensor::pulseUsual($pdo0); $pl = ControlSensor::pulseLimit($pdo0);
            $where = $pm === null ? 'No opens timed yet: it\'s timed each time staff open Pulse.' : 'Usually gathers in ' . number_format($pm / 1000, 1) . ' s (the last ' . $pn . ' opens; the limit is ' . number_format($pl / 1000, 1) . ' s).'; } catch (Throwable $x) {} }
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

/**
 * Text that might be long: one line, and More opens the same line in place (Jason, 2026-11-18: "show a line of text with a
 * toggle or see more to expand its presentation, not repeat it in full"). Short text is just the text.
 */
function tv_cb_more(string $text, int $short = 140, string $lead = ''): string
{
    $e = 'tv_w_e';
    if (mb_strlen($text) <= $short) return $lead . $e($text);
    // a toggle, not "…" and More (Jason, 2026-11-18): closed, the line runs to the edge and fades; ▸ opens it in place
    return '<span class="cb-tg" data-cb-clamp><button type="button" class="cb-tgb" data-cb-more aria-expanded="false" aria-label="Show all of it" title="Show all of it">'
        . '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>'
        . '<span class="cb-tgt" data-cb-more>' . $lead . $e($text) . '</span></span>';
}

/** Its check in a line: what holds it first, else that every part passes; the other parts on a toggle, never repeated. */
function tv_cb_check(array $line): string
{
    $e = 'tv_w_e'; $s = (string) ($line['s'] ?? 'new'); $parts = (array) ($line['extra']['parts'] ?? []);
    $at = ' <small>' . tv_cb_time((int) ($line['at'] ?? 0)) . '</small>';
    if ($parts) {
        $bad = array_values(array_filter($parts, fn ($x) => ($x[1] ?? null) === 'bad'));
        $rest = array_values(array_filter($parts, fn ($x) => ($x[1] ?? null) !== 'bad'));
        $head = $bad ? tv_cb_more(implode(' ', array_column($bad, 0)), 140, '<b class="cb-bad">Holds it:</b> ') : $e('Every part passes: ' . implode(', ', array_map(fn ($x) => strtolower((string) strtok((string) $x[0], ':')), array_filter($parts, fn ($x) => ($x[1] ?? null) !== null)))) . '.';
        $li = ''; foreach ($rest as [$txt, $st]) $li .= '<li>' . ($st !== null ? tv_cb_mark((string) $st) : '<span class="cb-m" aria-hidden="true"></span>') . '<span>' . tv_cb_more((string) $txt) . '</span></li>';
        return '<div class="cb-ck">' . tv_cb_mark($s) . (str_starts_with($head, '<span class="cb-tg"') ? $head : '<span class="cb-ckt">' . $head . '</span>') . '</div>' . $at . ($bad && $rest ? '<details class="cb-more"><summary>Its other ' . count($rest) . ' part' . (count($rest) === 1 ? '' : 's') . '</summary><ul class="cb-parts">' . $li . '</ul></details>' : '');
    }
    // a line from before checks were kept part by part: said so, with the way to run it again
    $said = (string) preg_replace('/^What holds it:\s*/u', '', (string) ($line['said'] ?? ''));
    $said = tv_cb_more($said);
    return '<div class="cb-ck">' . tv_cb_mark($s) . (str_starts_with($said, '<span class="cb-tg"') ? $said : '<span class="cb-ckt">' . $said . '</span>') . '</div>' . $at . ($s === 'bad' && str_contains((string) ($line['said'] ?? ''), 'What holds it:') ? ' <small class="cb-old">Tested before checks were shown part by part: press Check to see which part holds it.</small>' : '');
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

/**
 * #maintenance (0171 §2.5, §12; release 4a): what's out and planned, with Put back and Cancel; a window set ahead (any
 * workflow, or a task with its own switch: from, Back by, why); and the upkeep calendar, what's due from what the code
 * already schedules. Its list and calendar are drawn again with the board; the form isn't, so what's typed stays.
 */
function tv_cb_maintenance(PDO $pdo, array $names, callable $btn, bool $canRun, string $wNo): string
{
    $e = 'tv_w_e';
    $rows = ControlMaintenance::listing($pdo);
    $out = array_values(array_filter($rows, fn ($r) => $r['state'] === 'out'));
    $plan = array_values(array_filter($rows, fn ($r) => $r['state'] === 'planned'));
    $nm = fn (string $p) => $names[$p] ?? $p;
    $at = fn (?string $d) => $d ? tv_cb_time((int) strtotime($d . ' UTC')) : '';
    $sum = $out ? count($out) . ' out' . ($plan ? ', ' . count($plan) . ' planned' : '') : ($plan ? count($plan) . ' planned' : 'nothing out');
    $h = '<details class="ak-card tv-bleed cb-panel" id="maintenance" data-copy="Maintenance"' . ($out ? ' open' : '') . '><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($out ? 'maint' : 'na') . '<b>Maintenance</b> <small>' . $e($sum) . '</small></summary><div class="cb-mt">';
    // what's out, planned, and back in the last two days
    $li = '';
    foreach ($rows as $r) {
        $st = (string) $r['state'];
        $words = $st === 'out' ? 'Out since ' . $at($r['out_at']) . ', back by ' . $at($r['until_at'])
            : ($st === 'planned' ? 'Goes out ' . $at($r['from_at']) . ', back by ' . $at($r['until_at'])
            : ($st === 'back' ? 'Back ' . $at($r['back_at']) . ($r['back_by'] ? ' (' . $e((string) $r['back_by']) . ')' : '') : 'Cancelled'));
        $b = $st === 'out' ? $btn('maintback', 'Put back', $canRun, $wNo, (string) $r['id'], ['class' => 'ak-b cb-xs', 'data-confirm' => 'Put it back? Its check runs first; it starts again only if the check isn\'t red.', 'data-yes' => 'Put it back'])
            : ($st === 'planned' ? $btn('maintcancel', 'Cancel', $canRun, $wNo, (string) $r['id'], ['class' => 'ak-b cb-xs', 'data-confirm' => 'Cancel this window? It won\'t go out.', 'data-yes' => 'Cancel it']) : '');
        $li .= '<li id="cb-mt-' . (int) $r['id'] . '"><span class="cb-pill cb-pill-' . ($st === 'out' ? 'maint' : 'new') . '">' . $e(['out' => 'Out', 'planned' => 'Planned', 'back' => 'Back', 'cancelled' => 'Cancelled'][$st] ?? $st) . '</span>'
            . '<b>' . $e($nm((string) $r['proc'])) . '</b><span>' . $words . '</span><span>Why: ' . $e((string) $r['why']) . ($r['by_name'] ? ' (' . $e((string) $r['by_name']) . ')' : '') . '</span>'
            . ($r['note'] ? '<span data-tv-status="bad" data-tv-label="Still out:">' . $e((string) $r['note']) . '</span>' : '') . ($b !== '' ? '<span class="cb-acts">' . $b . '</span>' : '') . '</li>';
    }
    $h .= '<div class="cb-mlist">' . ($li !== '' ? '<ul class="cb-ml">' . $li . '</ul>' : '<p class="ak-note">Nothing is out for maintenance, and no window is planned.</p>') . '</div>';
    // a window set ahead (or now): any workflow, or a task with its own switch
    $opts = '';
    foreach ($names as $pid => $n) if (ControlMaintenance::canTake($pid)) $opts .= '<option value="' . $e($pid) . '">' . $e($n) . '</option>';
    if ($opts !== '') $h .= '<form id="cb-mt-form"><label>Process<select name="proc">' . $opts . '</select></label>'
        . '<label>Goes out<input type="datetime-local" name="from" aria-label="When it goes out (empty: now)"></label>'
        . '<label>Back by<input type="datetime-local" name="until" required aria-label="When it comes back by itself"></label>'
        . '<label class="cb-why">Why<input type="text" name="why" maxlength="255" required data-spell placeholder="New keys for its service"></label>'
        . $btn('maintplan', 'Take it out', $canRun, $wNo, '', ['data-cb-form' => 'cb-mt-form']) . '</form>'
        . '<p class="ak-note">Empty "Goes out" takes it out now. It\'s shut down in order (its tasks, then the workflow), shows blue, its alarms are notices and Start skips it. At its Back by it comes back by itself, after its check; a red check keeps it out and raises a warning. Times are in your time zone.</p>';
    // the upkeep calendar
    $up = '';
    // each opens in place: its process's row on the board, or its scheduled task in a drawer (the session manager's note)
    foreach (ControlMaintenance::upkeep($pdo) as [$what, $when, $often, $note, $proc]) {
        $rid = 'cb-p-' . str_replace(':', '-', $proc);
        $link = isset($names[$proc]) ? '<a class="underline" href="#' . $e($rid) . '" data-cb-go="' . $e($rid) . '">' . $e($what) . '</a>'
            : (str_starts_with($proc, 'job:') ? '<a class="underline" href="/admin/config/scheduler#job-' . $e(substr($proc, 4)) . '" data-peek-frame>' . $e($what) . '</a>' : $e($what));
        $up .= '<li><span class="cb-when">' . ($when ? tv_cb_time($when) : 'not scheduled') . '</span><b>' . $link . '</b><span>' . $e($often) . '</span><small class="ak-note">' . $e($note) . '</small></li>';
    }
    $h .= '<div class="cb-uplist"><h4 style="margin:.3rem 0 .2rem">Upkeep calendar: what\'s due</h4>' . ($up !== '' ? '<ul class="cb-up">' . $up . '</ul>' : '<p class="ak-note">Nothing scheduled.</p>') . '</div>';
    return $h . '</div></details>';
}

/**
 * The alarm list's one filter: a dropdown button like Shortcuts (icon, current choice, count, ▾), moved up into the page's
 * header by admin-kit.js (data-ak-cmd; it stays here inside a drawer). Its choices and their counts are kept by
 * admin-control.js through each redraw (CLAUDE.md "Filters on a list or queue are one dropdown button"; the session
 * manager, 2026-11-18: it was a native select and a gear on their own row).
 */
function tv_cb_afilter(): string
{
    $ic = function_exists('tv_icon') ? tv_icon('filter', 'ak-svg') : '';
    $h = '<span class="ak-fw cb-afw" data-ak-cmd><button type="button" class="ak-fb" aria-expanded="false" aria-haspopup="true" title="Which alarms to show" data-cb-afb>' . $ic . '<span data-cb-afname>Open alarms</span> <b data-cb-afcount>0</b></button><span class="ak-fl" hidden>';
    foreach (['open' => 'Open alarms', 'ack' => 'Acknowledged', 'trip' => 'Trips', 'warning' => 'Warnings', 'notice' => 'Notices'] as $k => $l)   // cleared ones are in the logbook
        $h .= '<button type="button" data-cb-afv="' . $k . '"' . ($k === 'open' ? ' class="on"' : '') . '>' . tv_w_e($l) . '<i data-cb-afn="' . $k . '">0</i></button>';
    return $h . '</span></span>';
}

/**
 * The logbook's rows (0171 §10, release 5a): newest first, 50 at a time, each with its time in the zone where it happened
 * (labelled), what happened, who (staff, or the system) and why; "Show older" carries on from the last one.
 */
function tv_cb_log_rows(PDO $pdo, string $scope = '', int $before = 0): string
{
    require_once __DIR__ . '/../lib/ControlLog.php';
    $e = 'tv_w_e'; $rows = ControlLog::feed($pdo, $scope, $before, 50);
    if (!$rows) return $before ? '<li class="cb-lg-end"><small class="ak-note">Nothing older.</small></li>' : '<li class="cb-lg-end"><small class="ak-note">Nothing logged yet' . ($scope !== '' ? ' for this' : '') . '.</small></li>';
    $h = '';
    foreach ($rows as [$at, $tz, $kind, $proc, $what, $who, $why])
        $h .= '<li><time datetime="' . gmdate('c', $at) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . gmdate('M j, H:i', $at) . ' UTC</time>'
            . '<span class="cb-pill cb-pill-new">' . $e(ControlLog::KINDS[$kind] ?? $kind) . '</span><span class="cb-lg-w">' . $e($what) . ($why !== '' ? ' <small>' . $e($why) . '</small>' : '') . '</span><small class="cb-lg-who">' . $e($who) . '</small></li>';
    $last = end($rows)[0];
    return $h . (count($rows) >= 50 ? '<li class="cb-lg-more"><button type="button" class="ak-b cb-xs" data-cb-logmore="' . (int) $last . '">Show older</button></li>' : '');
}

/** A report's cell: a moment in the reader's zone (labelled), else its words. */
function tv_cb_rcell($c): string
{
    return is_array($c) && isset($c['t']) ? tv_cb_time((int) $c['t']) : tv_w_e((string) $c);
}

/** #reports: open a report for the system, a system or one process, over a day or a week (decisions/0171 §11, 5b). */
function tv_cb_reports_panel(PDO $pdo): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/ControlReport.php';
    $opts = ''; foreach (ControlReport::scopes($pdo) as $k => $n) $opts .= '<option value="' . $e($k) . '">' . $e($n) . '</option>';
    $sum = ControlReport::summary($pdo);
    return '<details class="ak-card tv-bleed cb-panel" id="reports" data-copy="Reports"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark('na') . '<b>Reports</b> <small>uptime, what ran, records, alarms, spend and switches, for any system or process</small></summary>'
        . ($sum ? '<ul class="cb-rsum">' . implode('', array_map(fn ($l) => '<li>' . $e($l) . '</li>', $sum)) . '</ul>' : '')
        . '<form class="cb-rf" method="get" action="/admin/config/control"><label>Report on<select name="report">' . $opts . '</select></label>'
        . '<label>Over<select name="range"><option value="day">the last 24 hours</option><option value="week">the last 7 days</option></select></label>'
        . '<button type="submit" class="ak-b">Open the report</button></form>'
        . '<p class="ak-note">Made from what the site already keeps, so it costs nothing. Each opens as a page with Download (CSV, and the page as a file); the daily report at 5 am carries the summary, and Monday\'s the week.</p></details>';
}

/** The report page's style, shared with an Incident Report's page. */
function tv_cb_report_css(): string
{
    return '<style>#cb.cb-report{display:grid;gap:.6rem}#cb .cb-rsec{padding:.6rem .8rem}#cb .cb-title{margin:0 0 .4rem;font-size:15px}#cb .cb-rsum{margin:0;padding-left:1.1rem}#cb .cb-rtw{overflow-x:auto}'
        . '#cb .cb-acts{display:flex;flex-wrap:wrap;gap:.4rem}#cb .cb-acts .on{background:#8FBCA8}#cb .cb-tt{width:100%;border-collapse:collapse;font-size:13px}#cb .cb-tt th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#8a7a68;padding:.2rem .3rem;border-bottom:1px solid #EFE6D6}#cb .cb-tt td{padding:.3rem;border-bottom:1px solid #F3ECE0;vertical-align:top}'
        . '@media (max-width:767px){#cb .cb-tt thead{display:none}#cb .cb-tt tr{display:block;padding:.35rem 0;border-bottom:1px solid #EFE6D6}#cb .cb-tt td{display:flex;gap:.5rem;border:0;padding:.1rem 0}#cb .cb-tt td::before{content:attr(data-th);flex:0 0 7.5rem;font-size:11px;text-transform:uppercase;color:#8a7a68}}</style>';
}

/** The report as a page on the board (?report=<scope>&range=day|week): its summary, each section a table, its downloads. */
function tv_cb_report_page(PDO $pdo, string $scope, string $range, string $csrf): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/ControlReport.php';
    $r = ControlReport::build($pdo, $scope, $range);
    $q = fn (string $rg) => '/admin/config/control?report=' . rawurlencode($r['scope']) . '&range=' . $rg;
    $h = '<div id="cb" class="cb-report" data-csrf="' . $e($csrf) . '"><p class="cb-rback"><a href="/admin/config/control#reports" class="underline">← The board</a></p>'
        . '<h2 class="ak-h">' . $e($r['name']) . ': ' . $e($r['label']) . '</h2>'
        . '<p class="ak-note">' . tv_cb_time($r['from']) . ' to ' . tv_cb_time($r['to']) . '</p>'
        . '<div class="cb-acts"><a class="ak-b' . ($r['range'] === 'day' ? ' on' : '') . '" href="' . $e($q('day')) . '">Last 24 hours</a><a class="ak-b' . ($r['range'] === 'week' ? ' on' : '') . '" href="' . $e($q('week')) . '">Last 7 days</a>'
        . '<button type="button" class="ak-b" data-cb-dl="csv" data-cb-scope="' . $e($r['scope']) . '" data-cb-range="' . $e($r['range']) . '">Download CSV</button>'
        . '<button type="button" class="ak-b" data-cb-dl="file" data-cb-scope="' . $e($r['scope']) . '" data-cb-range="' . $e($r['range']) . '">Download the page</button></div>'
        . '<section class="ak-card tv-bleed cb-rsec" id="r-summary" data-copy="Summary"><h3 class="cb-title">Summary</h3><ul class="cb-rsum">' . implode('', array_map(fn ($l) => '<li>' . $e($l) . '</li>', $r['summary'])) . '</ul></section>';
    $h .= tv_cb_report_folded($r);   // folded (D16): the summary and spend first, uptime below 100%, alarms grouped
    return $h . tv_cb_report_css()
        . '<script src="/js/tv-time.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-time.js') . '" defer></script>'
        . '<script src="/js/admin-control.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-control.js') . '" defer></script></div>' . tv_cb_view_assets();
}

/** #log: the logbook, its one filter (the system, a system, or one process) and Log an entry (spell-checked, with its zone). */
function tv_cb_logbook(PDO $pdo, array $names, bool $canNote): string
{
    $e = 'tv_w_e';
    // the one filter, a dropdown button like Shortcuts (icon, current choice, ▾): the whole system, a system, or a process
    $ic = function_exists('tv_icon') ? tv_icon('filter', 'ak-svg') : '';
    // grouped by system, the parts with entries this week first (with their count), a box to find one (Jason, 2026-11-19:
    // "this drop down is very messy": every part in one flat list)
    $procs = ControlRegister::processes($pdo); $n7 = [];
    try { foreach ($pdo->query("SELECT proc, COUNT(*) FROM control_log WHERE at >= UTC_TIMESTAMP() - INTERVAL 7 DAY AND proc <> '' GROUP BY proc")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) $n7[(string) $k] = (int) $v; } catch (Throwable $x) {}
    $groups = []; foreach ($names as $pid => $n) $groups[(string) ($procs[$pid][0] ?? 'other')][$pid] = (string) $n;
    $sysNames = ControlRegister::SYSTEMS + ['other' => 'Other parts'];
    $opts = '<span class="cb-lgf"><input type="search" class="ak-q" placeholder="Find a part" aria-label="Find a part" data-cb-logq></span>'
          . '<button type="button" data-cb-logv="" data-label="The whole system" class="on cb-lgh">The whole system</button>';
    $nopts = '<option value="">The whole system</option>';
    foreach ($sysNames as $sk => $sn) {
        if (empty($groups[$sk])) continue;
        $g = $groups[$sk];
        uksort($g, fn ($a, $b) => (empty($n7[$a]) <=> empty($n7[$b])) ?: strnatcasecmp($g[$a], $g[$b]));   // with entries first, then A to Z (W2 before W10)
        $busy = count(array_filter(array_keys($g), fn ($p) => !empty($n7[$p])));
        $opts .= '<span class="cb-lgs" data-cb-lgs>' . ($sk !== 'other' ? '<button type="button" class="cb-lgh" data-cb-logv="sys:' . $e($sk) . '" data-label="' . $e($sn) . '" data-q="' . $e(mb_strtolower($sn)) . '">' . $e($sn) . ' <small>' . count($g) . ' parts' . ($busy ? ', ' . $busy . ' with entries this week' : '') . '</small></button>' : '<span class="cb-lgh cb-lght">' . $e($sn) . '</span>');
        $nopts .= '<optgroup label="' . $e($sn) . '">';
        foreach ($g as $pid => $n) {
            $c = $n7[$pid] ?? 0;
            $opts .= '<button type="button" class="cb-lgp' . ($c ? '' : ' quiet') . '" data-cb-logv="' . $e($pid) . '" data-label="' . $e($n) . '" data-q="' . $e(mb_strtolower($n . ' ' . $pid)) . '"><span>' . $e($n) . '</span>' . ($c ? '<i title="Entries in the last 7 days">' . $c . '</i>' : '') . '</button>';
            $nopts .= '<option value="' . $e($pid) . '">' . $e($n) . '</option>';
        }
        $opts .= '</span>'; $nopts .= '</optgroup>';
    }
    $opts .= '<p class="ak-note cb-lgnone" hidden>No part has those words.</p>';
    return '<details class="ak-card tv-bleed cb-panel" id="log" data-copy="Logbook"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark('na') . '<b>Logbook</b> <small>every switch, control, alarm and note, newest first</small></summary>'
        . '<div class="cb-lg"><span class="ak-fw cb-lgw"><button type="button" class="ak-fb" aria-expanded="false" aria-haspopup="true" title="Whose log to show" data-cb-logb>' . $ic . '<span data-cb-logname>The whole system</span></button><span class="ak-fl cb-lgfl" hidden>' . $opts . '</span></span>'
        . '<ul class="cb-lgl" data-cb-loglist>' . tv_cb_log_rows($pdo) . '</ul>'
        . '<form class="cb-lg-n" id="cb-log-form"><label class="cb-why">Log an entry<textarea name="text" rows="2" maxlength="400" required data-spell placeholder="What happened, or what you did"></textarea></label>'
        . '<label>On<select name="scope">' . $nopts . '</select></label>'
        . '<button type="button" class="ak-b" data-cb-lognote' . ($canNote ? '' : ' disabled title="Only staff who can view the board log an entry."') . '>Log it</button></form>'
        . '<p class="ak-note">Read from where each is kept (switches, Start\'s sessions, the pre-flight\'s runs, outside services\' issues, site jobs that stopped, releases), and the board\'s own log. Who is a staff member or the system; times are where they happened.</p></div></details>';
}

/** Every process, read once, by system: the board and its table share it (2026-11-19). */
function tv_cb_by_system(PDO $pdo, array $res): array
{
    $by = [];
    // red is for a trip or a core host down only (Jason, 2026-11-18); a process held by its check, a timeout or a deferred
    // source is amber, "held"
    $tripped = []; foreach (ControlAlarms::list($pdo) as $x) if ($x['cleared_at'] === null && $x['level'] === 'trip') $tripped[(string) $x['proc']] = true;
    foreach (ControlRegister::processes($pdo) as $id => $p) {
        $row = ControlBoard::process($pdo, $id, $p, $res);
        // the board's light rules, shared with the historian so its trend shows what the board showed (release 4b)
        [$row['light'], $row['means']] = ControlBoard::boardLight($pdo, $id, $row, $tripped);
        if ($row['light'] === 'maint' && ($mt = ControlMaintenance::outFor($pdo, $id))) { $row['maint'] = $mt; $row['words'] = ControlMaintenance::words($mt) . '.'; }
        $by[$p[0]][$id] = $row;
    }
    return $by;
}

/** The board's tables and pages as tabs beside its stages (decisions/0175's note, release 2b part A): key => [label, what]. */
const TV_CB_TABS = ['alarms' => ['Alarms', 'Every alarm, open or cleared, with its drawer'], 'log' => ['Logbook', 'Every switch, alarm and note, by time'],
    'ledger' => ['Ledger', 'Every worker call and the records it touched'], 'upkeep' => ['Upkeep', 'Maintenance windows, set ahead and done'],
    'reports' => ['Reports', 'The whole system, a system or a part, over a period'], 'scheduler' => ['Scheduled tasks', 'Every scheduled job, its line and its runs']];

/**
 * W13 a list and W14 "Not built", wherever the board names a workflow's state (workflows-page.md §6 and §5's table; the
 * session manager, 2026-11-22: "W13 is a list, not a gauge"; the build log's hand-off item 2: W14's "Nothing carries it
 * yet" notice closes, its fact shown on its own page). [stage, where] or null.
 */
function tv_cb_wf_kind(string $id): ?array
{
    if ($id === 'wf:W13') return ['A list', 'People write stories: nothing to switch and no machine steps to measure (W13 is a list, not a gauge).'];
    if ($id === 'wf:W14') return ['Not built', 'Not built: licence_lookup (the states\' licence lists, AZCC and NM SoS) isn\'t built yet, so nothing carries it.'];
    return null;
}

/** A light as the table shows it: [tone, word] (colour with its word, never colour alone). */
const TV_CB_LIGHTS = ['bad' => ['bad', 'Tripped'], 'alarm' => ['warn', 'Warning'], 'hold' => ['warn', 'Held'], 'waitrun' => ['warn', 'Waiting for its run'], 'warn' => ['warn', 'On a backup'], 'maint' => ['info', 'In maintenance'],
    'off' => ['info', 'Off'], 'wait' => ['info', 'Not tested'], 'ok' => ['ok', 'Working']];

/**
 * The board's process table (design/table-standard.md, T1; the session manager, 2026-11-19): a group row per system, its
 * parts under it; each part's status, stage, where it stands, last 24 hours, speed and OK; Check, and ⋯ with words (a
 * greyed item says why). A part opens its own page in a drawer (the standard's rule 6). $q: q (words), show (a light).
 * Returns [rows, total, counts by light].
 */
function tv_cb_table_rows(PDO $pdo, array $q): array
{
    require_once __DIR__ . '/../lib/Preflight.php'; require_once __DIR__ . '/../lib/SystemStart.php'; require_once __DIR__ . '/../lib/Staging.php';
    $res = Preflight::results($pdo); $by = tv_cb_by_system($pdo, $res);
    $alarms = array_values(array_filter(ControlAlarms::list($pdo), fn ($x) => $x['cleared_at'] === null));
    [$sysStage] = ControlBoard::stage($pdo);
    [$canRun, $whyRun] = SystemStart::can(); [$canPf, $whyPf] = Preflight::can();
    $staging = Staging::here(); $wNo = $staging ? 'Nothing runs on staging: these work on the live site.' : (string) $whyRun; $canRun = $canRun && !$staging;
    $words = array_values(array_filter(explode(' ', mb_strtolower(trim((string) ($q['q'] ?? ''))))));
    $show = (string) ($q['show'] ?? '');
    $out = []; $counts = ['' => 0];
    foreach (ControlRegister::SYSTEMS as $sk => $sname) {
        $kids = []; $n = [];
        foreach ($by[$sk] ?? [] as $id => $p) {
            $needs = str_starts_with($id, 'wf:') ? ControlBoard::needs($res, (array) ($p['parts']['needs'] ?? [])) : [];
            $mine = array_values(array_filter($alarms, fn ($x) => $x['proc'] === $id));
            // its trips all started again for their next run (task-trip-3): waiting for that run, amber, nothing held, as its
            // cards and boardLight say; never "Tripped" (words follow the state; W1's row, 2026-11-24)
            $myTrips = array_values(array_filter($mine, fn ($x) => $x['level'] === 'trip'));
            $waiting = $myTrips && !array_filter($myTrips, fn ($x) => ControlAlarms::startedAgain($x) === '');
            if ($waiting) $p['light'] = 'waitrun';
            elseif ($mine && $mine[0]['level'] === 'trip') $p['light'] = 'bad';
            // an open warning shows its level's mark on the row, never a green ✓ beside it (playbook §2, principle 4: words
            // follow the state); a held, out or off part keeps its own
            elseif ($mine && in_array((string) $p['light'], ['ok', 'warn', 'wait'], true) && array_filter($mine, fn ($x) => $x['level'] === 'warning')) $p['light'] = 'alarm';
            $lt = (string) $p['light']; $counts[$lt] = ($counts[$lt] ?? 0) + 1; $counts['']++; $n[$lt] = ($n[$lt] ?? 0) + 1;
            if ($show !== '' && $lt !== $show) continue;
            $hay = mb_strtolower($p['name'] . ' ' . $p['words'] . ' ' . $id . ' ' . implode(' ', array_map(fn ($x) => $x[0] . ' ' . implode(' ', array_column((array) ($x[2] ?? []), 0)), $needs)));   // its needs and their sources, as the old Find box
            foreach ($words as $w) if (!str_contains($hay, $w)) continue 2;
            $r = tv_cb_row($id, $p, $needs, $sysStage);
            if ($waiting) $r['stage'] = ['hold', 'Waiting for its run'];
            $where = $mine ? ($waiting ? 'Started again' : ControlAlarms::LEVELS[$mine[0]['level']]) . ': ' . $mine[0]['title'] . (count($mine) > 1 ? ' (and ' . (count($mine) - 1) . ' more)' : '') . '.' : (string) $r['where'];
            if ($wk = tv_cb_wf_kind($id)) { $r['stage'][1] = $wk[0]; if (!$mine) $where = $wk[1]; }
            if (preg_match('/^(working|observation|' . preg_quote(mb_strtolower($r['stage'][1]), '/') . ')\.?$/u', mb_strtolower(trim($where)))) $where = '';   // "Working." doesn't repeat the pill
            // its actions: Check, and ⋯ with words; one not open to it says why
            $hasCheck = isset($res[$id]) || str_starts_with($id, 'wf:') || str_starts_with($id, 't:') || str_starts_with($id, 'svc:') || str_starts_with($id, 'site:') || (str_starts_with($id, 'wk:') && (string) (ControlRegister::WORKERS[substr($id, 3)][3] ?? '') !== '');
            $trip = null; foreach ($mine as $al) if ($al['level'] === 'trip' && !empty($al['tripped_at'])) { $trip = $al; break; }
            $mt = $p['maint'] ?? null; $off = [];
            if (!$hasCheck) $off['check'] = 'It has no check of its own yet.'; elseif (!$canPf) $off['check'] = (string) $whyPf;
            if (!$trip) $off['resolve'] = 'Nothing to resolve: it isn\'t tripped.'; elseif ($waiting) $off['resolve'] = 'Already started again: its next run is the check.'; elseif (!$canRun) $off['resolve'] = $wNo;
            if ($trip && $waiting) $off['start'] = 'It\'s waiting for its run: its trips were started again.';
            elseif ($trip) $off['start'] = 'It\'s tripped: Check and start again is the way back.'; elseif (($p['switch'] ?? '') !== 'off') $off['start'] = ($p['switch'] ?? '') === 'on' ? 'It\'s already on.' : 'It has no switch of its own.'; elseif (!$canRun) $off['start'] = $wNo;
            if (($p['switch'] ?? '') !== 'on') $off['stop'] = 'It isn\'t on.'; elseif (!$canRun) $off['stop'] = $wNo;
            if ($mt) $off['maintout'] = 'It\'s already out for maintenance.'; elseif (!ControlMaintenance::canTake($id)) $off['maintout'] = 'Only a workflow, or a task with its own switch, is taken out.'; elseif (!$canRun) $off['maintout'] = $wNo;
            if (!$mt) $off['maintback'] = 'It isn\'t out for maintenance.'; elseif (!$canRun) $off['maintback'] = $wNo;
            [$tone, $word] = TV_CB_LIGHTS[$lt] ?? ['info', 'Not tested'];
            $kids[] = ['id' => $id, 'ref' => str_replace(':', '-', $id), 'name' => (string) $p['name'], 'sub' => (string) $r['sub'], 'light' => ['tone' => $tone, 'word' => $word],
                'stage' => (string) $r['stage'][1], 'where' => $where,
                'runs' => $r['runs'][0] === '—' ? null : $r['runs'][0] . ($r['runs'][1] !== '' ? ' · ' . $r['runs'][1] : ''),
                'speed' => $r['speed'][0] === '—' ? null : (string) $r['speed'][0], 'ok' => $r['ok'] === '—' ? null : (string) $r['ok'],
                'maint_id' => $mt ? (int) $mt['id'] : 0, 'trip_id' => $trip ? (int) $trip['id'] : 0, '_off' => $off];
        }
        if (!$kids) continue;
        $worst = !empty($n['bad']) ? 'bad' : (!empty($n['alarm']) ? 'alarm' : (!empty($n['hold']) ? 'hold' : (!empty($n['waitrun']) ? 'waitrun' : (!empty($n['warn']) ? 'warn' : (!empty($n['ok']) ? 'ok' : 'wait')))));
        $sum = []; foreach (TV_CB_LIGHTS as $k => [, $w]) if (!empty($n[$k])) $sum[] = $n[$k] . ' ' . mb_strtolower($w);
        [$tone, $word] = TV_CB_LIGHTS[$worst];
        $out[] = ['id' => 'sys:' . $sk, '_group' => ['label' => $sname, 'n' => count($kids), 'status' => ['tone' => $tone, 'word' => $word], 'sub' => implode(' · ', $sum), 'rows' => $kids]];
    }
    return [$out, count($out), $counts];
}

/**
 * A process's own data (Jason, 2026-11-19): every record the calls of its endpoints created or changed, newest first, from
 * control_records (ids and what happened, never contents: 0171 §4). $q: proc (its ref, wf-W1), what, q, page, per.
 * Returns [rows, total, counts by what]; paged in SQL so a long history loads a page at a time.
 */
function tv_cb_proc_records(PDO $pdo, array $q): array
{
    $proc = (string) preg_replace('/^([a-z]{1,5})-/', '$1:', (string) ($q['proc'] ?? ''), 1);
    $eps = [];
    foreach (array_keys(ControlRegister::ENDPOINTS) as $ep) if (ControlAlarms::procOfEndpoint($pdo, $ep) === $proc) $eps[] = $ep;
    if (str_starts_with($proc, 'wk:')) foreach ((array) (ControlRegister::WORKERS[substr($proc, 3)][2] ?? []) as $ep) $eps[] = $ep;
    $eps = array_values(array_unique($eps));
    if (!$eps) return [[], 0, ['' => 0]];
    $w = ['l.endpoint IN (' . implode(',', array_fill(0, count($eps), '?')) . ')']; $a = $eps;
    $counts = ['' => 0];
    try { $st = $pdo->prepare('SELECT r.what, COUNT(*) FROM control_records r JOIN control_ledger l ON l.id = r.ledger_id WHERE ' . implode(' AND ', $w) . ' GROUP BY r.what'); $st->execute($a);
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $n) { $counts[(string) $k] = (int) $n; $counts[''] += (int) $n; } } catch (Throwable $x) { return [[], 0, ['' => 0]]; }
    $what = (string) ($q['what'] ?? ''); if ($what !== '') { $w[] = 'r.what = ?'; $a[] = $what; }
    $t = trim((string) ($q['q'] ?? '')); if ($t !== '') { $w[] = '(r.kind LIKE ? OR r.ref_id = ?)'; $a[] = '%' . $t . '%'; $a[] = (int) preg_replace('/\D/', '', $t); }
    $per = max(10, min(100, (int) ($q['per'] ?? 25))); $page = max(0, (int) ($q['page'] ?? 0));
    $total = 0; $rows = [];
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM control_records r JOIN control_ledger l ON l.id = r.ledger_id WHERE ' . implode(' AND ', $w)); $st->execute($a); $total = (int) $st->fetchColumn();
        $st = $pdo->prepare('SELECT r.id, r.kind, r.ref_id, r.what, r.at, r.ledger_id, l.endpoint, l.run_id FROM control_records r JOIN control_ledger l ON l.id = r.ledger_id WHERE ' . implode(' AND ', $w) . ' ORDER BY r.id DESC LIMIT ' . $per . ' OFFSET ' . ($page * $per));
        $st->execute($a);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = (string) $r['kind']; $id = (int) $r['ref_id'];
            $href = $k === 'listing' ? '/admin/listing-edit.php?id=' . $id : ($k === 'review' ? '/admin/listing-intake.php?row=' . $id : '/admin/config/ledger#open=config/ledger:' . (int) $r['ledger_id']);   // no page of its own: the call that touched it, with its records
            $rows[] = ['id' => (int) $r['id'], 'label' => ($k === 'listing' ? 'Listing' : ($k === 'review' ? 'Review item' : ucfirst(str_replace('_', ' ', $k)))) . ' #' . $id, 'href' => $href,
                'what' => ['stored' => 'Stored', 'updated' => 'Updated', 'review' => 'Sent to Review', 'refused' => 'Refused'][(string) $r['what']] ?? (string) $r['what'],
                'call' => '/api/' . $r['endpoint'] . ($r['run_id'] ? ' · run #' . (int) $r['run_id'] : ''), 'at' => gmdate('c', (int) strtotime((string) $r['at'] . ' UTC'))];   // a moment, labelled where it's shown
        }
    } catch (Throwable $x) { error_log('proc records: ' . $x->getMessage()); }
    return [$rows, $total, $counts];
}

/**
 * Watching Railway and the sync (decisions/0163 §5), on the board since the Service register took in Outside services
 * (decisions/0173 §1's table): what it looks at, each key named (never shown), its last look, and its switch.
 */
function tv_cb_watch(PDO $pdo, callable $btn): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/Operations.php';
    [$can, $why] = Operations::can();
    $w = Operations::watching($pdo); $seen = Operations::watched($pdo); $on = !empty($w['on']);
    $b = '<p class="ak-note">The site looks at Railway\'s latest deploy and the sync\'s runs itself, every hour, and raises an alarm here when one fails or stalls, so it no longer depends on a Claude session. Free status reads only. Shipped off: it needs its keys in .env first, never shown here.</p><table class="cb-tt"><thead><tr><th>What</th><th>Its key</th><th>Where it looks</th><th>Last look</th></tr></thead><tbody>';
    foreach (Operations::WATCH as $k => [$name, $key, $where]) {
        $s = $seen[$k] ?? null; $has = getenv($key) !== false && getenv($key) !== '';
        $b .= '<tr><td><b>' . $e($name) . '</b></td><td><code>' . $e($key) . '</code> ' . ($has ? '<span data-tv-status="ok">in .env</span>' : '<span data-tv-status="info">not in .env yet</span>') . '</td><td><small>' . $e($where) . '</small></td><td>'
            . ($s ? '<span data-tv-status="' . (($s['ok'] ?? null) === true ? 'ok' : (($s['ok'] ?? null) === false ? 'bad' : 'info')) . '">' . $e((string) $s['what']) . '</span> <time datetime="' . $e(str_replace(' ', 'T', (string) $s['at']) . ':00Z') . '" data-tv-time data-tz="UTC">' . $e((string) $s['at']) . ' UTC</time>' : '<small>Never looked</small>') . '</td></tr>';
    }
    $b .= '</tbody></table><div class="cb-acts">' . ($on ? $btn('watch', 'Switch watching off', $can, (string) $why, 'off', ['data-confirm' => 'Stop watching Railway and the sync? Their alarms stop too.', 'data-yes' => 'Switch it off'])
        : $btn('watch', 'Switch watching on', $can, (string) $why, 'on', ['data-confirm' => 'Start watching Railway and the sync? The hourly job reads their status (free); nothing else changes.', 'data-yes' => 'Switch it on'])) . '</div>';
    return '<details class="ak-card tv-bleed cb-panel" id="cb-watch" data-copy="Watching Railway and the sync"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($on ? 'ok' : 'new') . '<b>Watching Railway and the sync</b> <small>' . ($on ? 'on' : 'off (shipped off)') . '</small></summary>' . $b . '</details>';
}

/** The board's process table, drawn by the admin kit's one table (tv_table: rows from config/processes). */
function tv_cb_process_table(): string
{
    require_once __DIR__ . '/kit-table.php';
    $show = ['' => 'Every part']; foreach (TV_CB_LIGHTS as $k => [, $w]) $show[$k] = $w;
    return '<div id="cb-systems" class="cb-systems" data-copy="The parts of the system">' . tv_table(['tool' => 'config/processes', 'title' => 'The parts of the system', 'open' => '/admin/config/control?proc={ref}',
        'columns' => [['name', 'Process', 'name'], ['light', 'Status', 'status'], ['stage', 'Stage', 'pill'], ['where', 'Where it stands', 'text'], ['runs', 'Last 24 hours', 'text'], ['speed', 'Speed', 'text'], ['ok', 'OK', 'text']],
        'sub' => 'sub', 'act' => ['check', 'Check'],
        'menu' => [['page', 'Its own page'], ['resolve', 'Check and start again'], ['start', 'Start it'], ['stop', 'Shut it down'], ['maintout', 'Take out for maintenance'], ['maintback', 'Put back']],
        'filters' => [['show', 'Show', array_map(null, array_keys($show), array_values($show))]], 'countBy' => 'show', 'noData' => 'no history yet'])
        . '<p class="cb-lfon"><button type="button" class="cb-lf" data-cb-all="1">Open every system</button> · <button type="button" class="cb-lf" data-cb-all="0">Close them</button></p></div>';   // the old board's Open all / Close all
}

/** A process's controls: short on its row (Check, Start, Shut down), named in full under it. */
function tv_cb_controls(string $id, array $p, array $res, callable $btn, bool $canPf, string $whyPf, bool $canRun, string $wNo, bool $short): string
{
    $x = ['class' => $short ? 'ak-b cb-xs' : 'ak-b'];
    $a = '';
    if (isset($res[$id]) || str_starts_with($id, 'wf:') || str_starts_with($id, 't:') || str_starts_with($id, 'svc:') || str_starts_with($id, 'site:') || (str_starts_with($id, 'wk:') && ControlRegister::WORKERS[substr($id, 3)][3] !== ''))
        $a .= $btn('check', $short ? 'Check' : 'Run its check', $canPf, $whyPf, $id, $x);
    // maintenance (0171 §2.5): Put back when it's out; Take out for a workflow or a task with its own switch
    $mt = $p['maint'] ?? null;
    if ($mt) return $a . $btn('maintback', 'Put back', $canRun, $wNo, (string) $mt['id'], $x + ['data-confirm' => 'Put ' . $p['name'] . ' back? Its check runs first; it starts again only if the check isn\'t red.', 'data-yes' => 'Put it back']);
    $isSvc = str_starts_with($id, 'svc:');   // an outside service's switch is Pause and Activate (decisions/0173 §2)
    if ($p['switch'] === 'on') $a .= $isSvc ? $btn('stop', 'Pause', $canRun, $wNo, $id, $x + ['data-confirm' => 'Pause ' . $p['name'] . '? Nothing of ours calls it until it\'s activated again; its key stays.', 'data-yes' => 'Pause it'])
        : $btn('stop', $short ? 'Shut down' : 'Shut it down', $canRun, $wNo, $id, $x + ['data-confirm' => 'Shut ' . $p['name'] . ' down? Its tasks first, then the workflow. What\'s running finishes.', 'data-yes' => 'Shut it down']);
    // an open trip on it (0171 §7's 3c note): only its Resolve starts it again, so the row offers that, never a bare Start
    // that would leave the trip red (Jason, 2026-11-19: "why is the red x still present if the notice says cleared?")
    $trip = null; try { foreach (ControlAlarms::ofProc(Database::connection(), $id) as $al) if ($al['level'] === 'trip' && !empty($al['tripped_at'])) { $trip = $al; break; } } catch (Throwable $e2) {}
    if ($trip) return $a . $btn('resolve', 'Check and start again', $canRun, $wNo, (string) $trip['id'], $x);
    if ($p['switch'] === 'off') $a .= $btn('start1', $isSvc ? 'Activate' : ($short ? 'Start' : 'Start it'), $canRun, $wNo, $id, $x);
    static $mh = null; if ($mh === null) { try { $mh = ControlMaintenance::defaultHours(Database::connection()); } catch (Throwable $x) { $mh = 2; } }
    if (!$short && ControlMaintenance::canTake($id)) $a .= $btn('maintout', 'Take out for maintenance', $canRun, $wNo, $id, $x + ['data-cb-ask' => 'Why is it going out? (It comes back by itself in ' . $mh . ' hour' . ($mh === 1 ? '' : 's') . '; set other times under Maintenance.)', 'data-cb-val' => '', 'data-cb-yes' => 'Take it out']);
    if (!$short && str_starts_with($id, 'svc:')) $a .= '<a class="ak-b" href="/admin/config/register#svc-' . tv_w_e(substr($id, 4)) . '" data-peek-frame>Its card</a>';
    return $a;
}

/**
 * The component an alarm's main button opens, in the side panel (decisions/0175 §6: "The board opens the component or the
 * workflow in a drawer, whichever the failure's source is"; release 2a, routed by the component; 0176 release 3 routes a
 * cause to its exact spot inside it): an outside service's card, a pre-flight line, a task's or workflow's own page (where
 * its row, its check and its switch are), a site job on Scheduled tasks; anything else its own page on the board. [label, url].
 */
function tv_cb_component(array $a, array $names = []): array
{
    $p = (string) $a['proc']; $k = (string) $a['akey'];
    if (str_starts_with($p, 'svc:')) return ['Open its card', '/admin/config/register#svc-' . substr($p, 4)];
    if (str_starts_with($k, 'pf:') && !str_starts_with($p, 'wf:') && !str_starts_with($p, 't:')) return ['Open its pre-flight line', '/admin/config/preflight#pf-' . str_replace(':', '-', substr($k, 3))];
    if (str_starts_with($p, 'wf:')) return ['Open ' . substr($p, 3), '/admin/config/control/wf-' . substr($p, 3)];
    if (str_starts_with($p, 't:')) return ['Open its page', '/admin/config/control/t-' . substr($p, 2)];   // its row, its check and its switch (0171 §12)
    if (str_starts_with($p, 'job:')) return ['Open its job', '/admin/config/scheduler#job-' . substr($p, 4)];
    if (preg_match('/^([a-z]{1,5}):([A-Za-z0-9_.-]{1,60})$/', $p, $m) && isset($names[$p])) return ['Open its page', '/admin/config/control/' . $m[1] . '-' . $m[2]];
    return ['', ''];
}

/**
 * A workflow's steps on its own page (decisions/0175 §6, Jason 2026-11-20: "Workflows are multi-step tasks or procedures
 * that use services…"; "A task is a step of its workflow, so its switch is on the workflow's own page on the board, with the
 * services each step uses and its backup"): each crawler task, what it does, the services it uses and each one's backup
 * (the workflow's needs that name it, Preflight::NEEDS), where it stands, and its one switch (Automation::setTask, with who
 * and why). Anchored #task-<key> so the card, Automation and Insights → Crawler land on it.
 */
function tv_cb_steps(PDO $pdo, string $wf, array $res, callable $btn): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/Automation.php'; require_once __DIR__ . '/../lib/OutsideServices.php'; require_once __DIR__ . '/Access.php';
    try { require_once __DIR__ . '/../lib/crawler/CrawlService.php'; $tasks = CrawlService::ready($pdo) ? array_filter(CrawlService::tasks($pdo), fn ($t) => (string) $t['wf'] === $wf) : []; } catch (Throwable $x) { $tasks = []; }
    if (!$tasks) return '';
    [$canA, $whyA] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
    $needKeys = array_keys(array_filter(Preflight::NEEDS, fn ($n) => in_array($wf, $n[2], true)));
    $needs = ControlBoard::needs($res, $needKeys);
    $on = count(array_filter($tasks, fn ($t) => (int) $t['enabled']));
    $h = '<details class="ak-card tv-bleed cb-panel" id="cb-steps" data-copy="Its steps and their switches" open><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($on === count($tasks) ? 'ok' : 'off') . '<b>Its steps and their switches</b> <small>' . count($tasks) . ' task' . (count($tasks) === 1 ? '' : 's') . ', ' . $on . ' on</small></summary><ul class="cb-steps">';
    foreach ($tasks as $tk => $t) {
        $svcs = []; foreach (OutsideServices::CONTROLS as $svc => $c) if (in_array($tk, (array) ($c['tasks'] ?? []), true)) $svcs[] = $svc;
        $uses = [];
        foreach ($svcs as $svc) {
            $nm = (string) (OutsideServices::COMPANIES[$svc][0] ?? $svc); $bk = [];
            foreach ($needKeys as $nk) if (isset($needs[$nk]) && array_filter(Preflight::NEEDS[$nk][3], fn ($sr) => $sr[0] === 'svc' && $sr[1] === $svc))
                foreach ($needs[$nk][2] as [$sn]) if ($sn !== $nm) $bk[(string) $sn] = true;
            $uses[] = '<a class="underline" href="/admin/config/register#svc-' . $e($svc) . '" data-peek-frame>' . $e($nm) . '</a>' . ($bk ? ' <small>(its backup: ' . $e(implode(', then ', array_keys($bk))) . ')</small>' : ' <small>(no backup named)</small>');
        }
        $isOn = (int) $t['enabled'] === 1;
        // where it stands, as Automation's Tasks table said it (kept: 0175 R2a's list): off with why, who and when; on, held by
        // the master switch or its paused workflow; on and paused by the system; on
        $wfr = Automation::workflows($pdo)[$wf] ?? null; $master = Automation::master($pdo);
        $sp = Automation::taskOff($pdo, (string) $tk) ?? Automation::sysOff($pdo, 'wf', $wf) ?? Automation::sysOff($pdo, 'master', 'all');
        $offBy = ''; if (!empty($t['off_by'])) { try { $q = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $q->execute([(int) $t['off_by']]); $offBy = (string) ($q->fetchColumn() ?: ''); } catch (Throwable $x) {} }
        $said = !$isOn ? Automation::line(['why' => (string) ($t['off_why'] ?? ''), 'by_user' => $offBy, 'at' => $t['off_at'] ?? null], 'no run takes it until it\'s on again') . '.'
              : ($master ? 'held by the master switch.' : ($wfr && (int) $wfr['paused'] ? 'held by ' . $wf . ' (paused).' : ($sp !== null ? $sp : 'the next runs take it.')));
        $sw = $isOn ? $btn('taskoff', 'Switch off', $canA, (string) $whyA, (string) $tk, ['class' => 'ak-b cb-xs', 'data-cb-ask' => 'Why is ' . (string) $t['name'] . ' going off? (Shown with it; no run takes it until it\'s on again.)', 'data-cb-val' => '', 'data-cb-yes' => 'Switch it off'])
                    : $btn('taskon', 'Switch on', $canA, (string) $whyA, (string) $tk, ['class' => 'ak-b cb-xs go']);
        $h .= '<li id="task-' . $e((string) $tk) . '"><div><b>' . $e((string) $t['name']) . '</b> <small>' . $e((string) $t['what']) . '</small></div>'
            . '<div><small>Uses:</small> ' . ($uses ? implode('; ', $uses) : '<small>no outside service (our own data and the site)</small>') . '</div>'
            . '<p data-tv-status="' . ($isOn && $sp === null && !$master && !($wfr && (int) $wfr['paused']) ? 'ok' : 'info') . '" data-tv-label="' . ($isOn ? 'On:' : 'Off:') . '">' . $e($said) . '</p>'
            . '<div class="cb-acts">' . $sw . '</div></li>';
    }
    return $h . '</ul><p class="ak-note">One switch per task, here: the service\'s card, Automation and Insights → Crawler link to it. Who pressed it and why is kept in Automation\'s Changes. Its share, timing and last runs: <a class="underline" href="/admin/insights/crawler" data-peek-frame>Insights → Crawler</a>.</p></details>';
}

/** Where an alarm's evidence is, in a drawer: its runs, its card, its jobs, or its pre-flight line. */
function tv_cb_evidence(array $a): array
{
    $p = (string) $a['proc']; $k = (string) $a['akey'];
    if (str_starts_with($k, 'pf:')) return ['Open its line', '/admin/config/preflight#pf-' . str_replace(':', '-', substr($k, 3))];
    if ($p === 'wk:crawler' || str_starts_with($p, 'wf:') || str_starts_with($p, 't:')) {   // its run, never the list (Jason, 2026-11-19: "Open card shows the specific place to fix issues")
        if (preg_match('#/admin/insights/crawler\?run=(\d+)#', str_replace('\\/', '/', is_array($a['fix'] ?? null) ? json_encode($a['fix']) : (string) ($a['fix'] ?? '')), $m)) return ['Open its run', '/admin/insights/crawler?run=' . $m[1]];   // the run its own fix names
        try { $st = Database::connection()->prepare('SELECT id FROM crawl_runs WHERE started_at <= ? + INTERVAL TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) SECOND ORDER BY started_at DESC, id DESC LIMIT 1');   // else the run going when it was last seen (alarms keep UTC, runs the server's clock)
            $st->execute([(string) ($a['last_at'] ?? gmdate('Y-m-d H:i:s'))]); if ($rid = (int) $st->fetchColumn()) return ['Open its latest run', '/admin/insights/crawler?run=' . $rid]; } catch (Throwable $x) {}
        return ['Open its runs', '/admin/insights/crawler'];   // no run yet: the runs list says so
    }
    if (str_starts_with($p, 'svc:')) return ['Open its card', '/admin/config/register#svc-' . substr($p, 4)];
    if (str_starts_with($p, 'job:')) return ['', ''];   // its runs are on its job's own row: Open its job is the one button (the design review, 2026-11-23)
    return ['Open its process', ''];
}

/**
 * The queue (0171 §7, release 3a): one row per issue, never per process, Trip then Warning then Notice, newest first;
 * what happened, what it means, what to do, its fix in place, its evidence in a drawer, Acknowledge (Undo), its count and
 * last seen, and the processes it holds. One dropdown filters it (Open, Acknowledged, Cleared; by level). Open on load
 * whenever anything is open.
 */
function tv_cb_alarms(array $alarms, array $ac, array $names, callable $pid, bool $canPf, string $whyPf, callable $btn, array $redChecks = [], string $focus = ''): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/Operations.php'; [$canOps, $whyOps] = Operations::can();   // the cap and empty steps' own permission
    [$canBoard, $whyBoard] = ControlBoard::can();   // an admin's Resolve (executive, 0171 §13)
    $nOpen = $ac['trip'] + $ac['warning'] + $ac['notice'];
    $sum = $nOpen ? implode(', ', array_filter([$ac['trip'] ? $ac['trip'] . ' trip' . ($ac['trip'] === 1 ? '' : 's') : '', $ac['warning'] ? $ac['warning'] . ' warning' . ($ac['warning'] === 1 ? '' : 's') : '', $ac['notice'] ? $ac['notice'] . ' notice' . ($ac['notice'] === 1 ? '' : 's') : ''])) . ' open' : 'nothing open';
    $h = '<details class="ak-card tv-bleed cb-panel cb-alarms" id="cb-alarms" data-copy="Alarms"' . ($nOpen ? ' open' : '') . '><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($ac['trip'] ? 'bad' : ($ac['warning'] ? 'hold' : 'ok')) . '<b>Alarms</b> <small>' . $e($sum) . '</small></summary>';
    if (!$alarms) return $h . '<p data-tv-status="ok" data-tv-label="All clear:">No alarm is open. A problem raises one the moment it happens; a warning clears by itself when its reading is good again, and moves to the <a class="underline" href="#log">logbook</a>.</p></details>';
    $h .= '<ul class="cb-al">';   // its filter is one dropdown button in the page's header (tv_cb_afilter)
    // one alarm's row; two or more sharing a system, level and kind are one group row that opens to them (0171 §8's
    // note, Jason 2026-11-18: "Board and Pulse"), each keeping its own buttons
    $one = function (array $a) use ($e, $btn, $canPf, $whyPf, $canOps, $whyOps, $canBoard, $whyBoard, $names, $pid, $focus): string {
        $cl = $a['cleared_at'] !== null; $lv = (string) $a['level']; $ack = $a['ack_at'] !== null; $id = (int) $a['id'];
        $state = $cl ? 'cleared' : ($ack ? 'ack' : 'open');
        $fx = (array) $a['fix']; $proc = (string) $a['proc'];
        $ev = tv_cb_evidence($a);
        $fxs = $fx && isset($fx[0]) && is_array($fx[0]) ? $fx : ($fx ? [$fx] : []);
        // no Mark resolved (Jason, 2026-11-19: "it should be the result of an issue being fixed"): a passing check closes it   // as its old Pulse case had
        $isTrip = $lv === 'trip' && !empty($a['tripped_at']);
        if ($isTrip && !$cl) $fxs = array_values(array_filter($fxs, fn ($f) => !in_array($f[1] ?? '', ['svcresolve', 'svccheck', 'check'], true)));   // an admin's Resolve stands for them
        // started again for its next run (ControlAlarms::startedAgain): the press's state replaces the trip's: no Try it first
        // (it is first), no Check and start again, nothing held, amber while it waits for that run (Jason, 2026-11-24)
        $started = ControlAlarms::startedAgain($a) !== '';
        if ($started) $fxs = array_values(array_filter($fxs, fn ($f) => !str_starts_with((string) ($f[2] ?? ''), 'ops_runnext:')));
        // another trip still holding its workflow: that trip's own Check and start again, right on this card (the Design reviewer)
        $holdBtn = '';
        if ($started) foreach (ControlAlarms::stillHolding(Database::connection(), $a, $id) as $o)
            $holdBtn .= $btn('resolve', 'Check and start again: ' . ControlAlarms::plainTrip(Database::connection(), $o), $canBoard, $whyBoard, (string) (int) $o['id'], ['class' => 'ak-b cb-xs go', 'style' => 'white-space:normal;text-align:left;max-width:100%;height:auto']);
        // a task's trip (the toolkit rule): Check and start again the one action; its run an evidence icon; Try it first and
        // Pause the task under ⋯ (the session manager, 2026-11-24)
        $more = ''; $evIc = '';
        if ($isTrip && !$cl && ControlAlarms::wholeWorkflowTrip($a)) {
            $keep = [];
            foreach ($fxs as $f) {
                if (($f[1] ?? '') === 'peek' && str_starts_with((string) ($f[0] ?? ''), 'Run #')) $evIc .= '<a class="cb-ic" href="' . $e((string) $f[2]) . '" data-peek-frame title="' . $e('Open ' . $f[0] . ': what it did and why') . '" aria-label="' . $e('Open ' . $f[0] . ': what it did and why') . '">' . (function_exists('tv_icon') ? tv_icon('eye', 'cb-svg') : '↗') . '</a>';
                elseif (in_array($f[1] ?? '', ['ops', 'opsask'], true)) $more .= $btn('opsdo', (string) $f[0], $canOps, $whyOps, (string) $a['akey'] . '|' . (string) $f[2], ['class' => 'cb-mi'] + (is_string($f[3] ?? null) ? ['data-confirm' => $f[3], 'data-yes' => (string) $f[0]] : []));
                else $keep[] = $f;
            }
            $fxs = $keep;
        }
        $fb = ''; foreach ($fxs as $f) $fb .= ($f[1] ?? '') === 'link' ? '<a class="ak-b cb-xs" href="' . $e((string) $f[2]) . '" target="_blank" rel="noopener">' . $e((string) $f[0]) . ' ↗</a>'
            : (($f[1] ?? '') === 'peek' ? '<a class="ak-b cb-xs" href="' . $e((string) $f[2]) . '" data-peek-frame="' . $e(is_string($f[3] ?? null) && $f[3] !== '' ? (string) $f[3] : (string) $f[0]) . '">' . $e((string) $f[0]) . '</a>'   // its panel titled by the part's name when the fix names it
            : (in_array($f[1] ?? '', ['ops', 'opsask'], true)   // the old Pulse case's steps, acting in place (0171 §7, §8): [label, 'ops'|'opsask', 'what', confirm|[ask label, value]]
                ? $btn('opsdo', (string) $f[0], $canOps, $whyOps, (string) $a['akey'] . '|' . (string) $f[2], ['class' => 'ak-b cb-xs']
                    + ($f[1] === 'opsask' ? ['data-cb-ask' => (string) ($f[3][0] ?? ''), 'data-cb-val' => (string) ($f[3][1] ?? '')] : (is_string($f[3] ?? null) ? ['data-confirm' => $f[3], 'data-yes' => (string) $f[0]] : [])))
            : (is_string($f[3] ?? null) && str_starts_with($f[3], '#wait:')   // a button the system holds for now, greyed with its reason (a 429's Check it now)
                ? $btn((string) $f[1], (string) $f[0], false, substr($f[3], 6), (string) ($f[2] ?? ''), ['class' => 'ak-b cb-xs']) . '<span class="cb-wt">' . $e(substr($f[3], 6)) . '</span>'   // shown, not only on hover (a phone has none)
            : $btn((string) $f[1], (string) $f[0], $canPf, $whyPf, (string) ($f[2] ?? ''), ['class' => 'ak-b cb-xs'] + (($f[1] ?? '') === 'svcresolve' ? ['data-confirm' => 'Mark it resolved? Calls to it start again; a new failure opens a new issue.', 'data-yes' => 'Mark resolved'] : [])))));
        // a trip waits for an admin (decisions/0171 §7's note, release 3c): Resolve asks what fixed it; Undo for a day after
        $sw = ControlAlarms::stoppedWords($a);
        // the system does it: check, then start again what the trip stopped; never a question to get past (Jason, 2026-11-19)
        if ($isTrip && !$cl && !$started) {
            $redAt = ''; foreach ((array) json_decode((string) ($a['stopped'] ?? ''), true) as $sx) if (($sx[0] ?? '') === 'proc' && isset($redChecks[(string) $sx[1]])) { $redAt = (string) $sx[2] . '\'s check is red: ' . $redChecks[(string) $sx[1]]; break; }
            // its own check red (a pre-flight trip): greyed with the part, never a press that reads "Resolved" and trips again
            if ($redAt === '' && str_starts_with((string) $a['akey'], 'pf:') && isset($redChecks[substr((string) $a['akey'], 3)])) $redAt = 'Its check is red: ' . $redChecks[substr((string) $a['akey'], 3)];
            $fb = $btn('resolve', 'Check and start again', $canBoard && $redAt === '', $redAt !== '' ? $redAt . ' Fix that first (its Check is beside it); the system starts it again once it passes.' : $whyBoard, (string) $id, ['class' => 'ak-b cb-xs' . (ControlAlarms::wholeWorkflowTrip($a) ? ' go' : '')]) . $fb;
        }
        $undo = $cl && !empty($a['resolved_at']) && strtotime($a['resolved_at'] . ' UTC') > time() - 86400 ? $btn('unresolve', 'Undo the Resolve', $canBoard, $whyBoard, (string) $id, ['class' => 'ak-b cb-xs', 'data-confirm' => 'Open the trip again' . ($sw !== '' ? ' and stop ' . $sw . ' again' : '') . '?', 'data-yes' => 'Undo it']) : '';
        // its main button: the component that fixes it, in the side panel, one panel at a time (0175 §6, release 2a); not on
        // that component's own page, and never twice when its evidence is the same place
        $co = tv_cb_component($a, $names); $here = $co[1] !== '' && $focus !== '' && $co[1] === '/admin/config/control/' . str_replace(':', '-', $focus);
        // one action, one button (the design review, 2026-11-23): a fix that already opens the same place is the button
        $dupe = in_array($co[1], array_map(fn ($f) => (string) ($f[2] ?? ''), $cl ? [] : $fxs), true);
        $main = $co[1] !== '' && !$cl && !$here && !$dupe ? '<a class="ak-b cb-xs go cb-main" href="' . $e($co[1]) . '" data-peek-frame>' . $e($co[0]) . '</a>' : '';
        // Fix it (decisions/0176 §5, A5): where its cause names one spot, Fix it opens that spot in the same side panel and
        // stands first, in place of its component; its component's page stays only when it's another page
        require_once __DIR__ . '/../lib/Thread.php';
        $fix = $cl ? null : Thread::fixOf(['fix' => ControlAlarms::fixOf(Database::connection(), $a)]);
        if ($fix && $focus !== '' && strtok($fix['url'], '#') === '/admin/config/control/' . str_replace(':', '-', $focus)) $fix['url'] = substr($fix['url'], strpos($fix['url'], '#'));   // already on that page: it lands there
        if ($fix) { $fpage = strtok($fix['url'], '#'); $main = Thread::fixButton($fix, 'ak-b cb-xs go cb-main') . ($co[1] !== '' && !$here && strtok($co[1], '#') !== $fpage ? '<a class="ak-b cb-xs" href="' . $e($co[1]) . '" data-peek-frame>' . $e($co[0]) . '</a>' : ''); }
        $acts = $holdBtn . $main . (!$cl ? $fb : $undo) . $evIc . ($more !== '' ? '<details class="cb-pop cb-more"><summary class="cb-ic" title="More for this trip" aria-label="More for this trip">⋯</summary><div class="cb-menu" role="menu">' . $more . '</div></details>' : '')
            . ($evIc === '' && $ev[1] !== '' && ($main === '' || $ev[1] !== $co[1]) && !in_array($ev[1], array_map(fn ($f) => (string) ($f[2] ?? ''), $cl ? [] : $fxs), true) ? '<a class="ak-b cb-xs" href="' . $e($ev[1]) . '" data-peek-frame>' . $e($ev[0]) . '</a>' : '')
            // no Acknowledge on a warning: the system is handling it, and it clears itself or becomes a trip (0171 §7's 3c note,
            // "only a trip waits for a person"; Jason, 2026-11-19: "This is a notification, not need for me to acknowledge")
            . (($lv === 'warning' || $started) && !$cl ? '' : ($ack ? (ControlAlarms::wholeWorkflowTrip($a) ? '' : $btn('unack', 'Undo', true, '', (string) $id, ['class' => 'ak-b cb-xs'])) : $btn('ack', $cl ? 'Seen' : 'Acknowledge', true, '', (string) $id, ['class' => 'ak-b cb-xs'])));
        $holds = isset($names[$proc]) ? '<a href="#' . $e($pid($proc)) . '" data-cb-go="' . $e($pid($proc)) . '" class="underline">' . $e($names[$proc]) . '</a>' : $e(ControlAlarms::partName(Database::connection(), $proc));   // a part with no row: its plain name (02 §5.4)
        // the dispatcher's one shape (decisions/0176 §2: the board shows all of it): Title; Status; What it affects (Holds,
        // what its trip paused, an outside service's needs and their backups); What the system is doing; What to do and
        // the buttons; Details ▾ (its reading, kind and counts), folded. Every part read from the alarm as it is now.
        require_once __DIR__ . '/../lib/Thread.php';
        $f = ControlAlarms::facts(Database::connection(), $a);
        // only a trip holds its part; anything else names it and says nothing is held (words true to state; the design review, 2026-11-23)
        $aff = ($lv === 'trip' && !empty($a['tripped_at']) && !$cl && !$started ? '<b>Holds:</b> ' . $holds : $holds . ' <small>(nothing held)</small>') . ($f['affects'] ? '; ' . $e(implode('; ', $f['affects'])) : '');
        $th = Thread::html(['buttons' => [], 'pill_class' => 'cb-pill cb-pill-' . ($cl ? 'ok' : ($lv === 'trip' && !$started ? 'bad' : 'warn')), 'acts_class' => 'cb-acts'] + $f, 'board', $acts, '', $aff);
        $li = '<li id="cb-alarm-' . $id . '" class="cb-al-' . $e($cl ? 'cleared' : $lv) . '" data-astate="' . ($started ? 'started' : $state) . '" data-alevel="' . $e($lv) . '">' . tv_cb_mark($cl ? 'ok' : ($lv === 'trip' && !$started ? 'bad' : ($lv === 'warning' || $started ? 'hold' : 'new')))
            . '<div class="cb-am">' . $th . ($lv === 'trip' && $ack && !$cl && !$started && !ControlAlarms::wholeWorkflowTrip($a) ? '<p class="cb-seen" data-tv-status="bad" data-tv-label="Seen:">by ' . $e((string) ($a['ack_name'] ?: 'staff')) . '. It stays open while it holds (the light stays red); Check and start again is still here.</p>' : '') . '</div></li>';
        return $li;
    };
    $groups = ControlAlarms::groups($alarms); $of = []; foreach ($groups as $gk => $g) foreach ($g['ids'] as $gid) $of[$gid] = $gk;
    $byId = array_column($alarms, null, 'id'); $done = [];
    foreach ($alarms as $a) {
        $id = (int) $a['id'];
        if (!isset($of[$id])) { $h .= $one($a); continue; }
        $gk = $of[$id]; if (isset($done[$gk])) continue; $done[$gk] = true; $g = $groups[$gk];
        $members = array_map(fn ($x) => $byId[$x], $g['ids']); $lv = $g['level'];
        $acked = !array_filter($members, fn ($x) => $x['ack_at'] === null);
        $nm = array_values(array_unique(array_map(fn ($x) => $names[$x['proc']] ?? (string) preg_replace('/:.*$/', '', (string) $x['title']), $members)));
        // every trip in it started again for its next run: the group follows their state (amber, nothing held), never still "Holds:"
        $allStarted = $lv === 'trip' && !array_filter($members, fn ($x) => (string) $x['level'] === 'trip' && ControlAlarms::startedAgain($x) === '');
        $h .= '<li id="cb-group-' . $e(str_replace(':', '-', $gk)) . '" class="cb-al-grp cb-al-' . $e($lv) . '" data-astate="' . ($allStarted ? 'started' : ($acked ? 'ack' : 'open')) . '" data-alevel="' . $e($lv) . '">' . tv_cb_mark($lv === 'trip' && !$allStarted ? 'bad' : ($lv === 'warning' || $allStarted ? 'hold' : 'new'))
            . '<details class="cb-grp" id="cb-grpd-' . $e(str_replace(':', '-', $gk)) . '"><summary><span class="cb-pill cb-pill-' . ($lv === 'trip' && !$allStarted ? 'bad' : 'hold') . '">' . $e(ControlAlarms::LEVELS[$lv]) . '</span> <b>' . $e($lv === 'trip' ? (string) preg_replace('/: (\d+) parts$/', ' · $1 trips', (string) $g['title']) : (string) $g['title']) . '</b>' . ($allStarted ? ' <small>(nothing held: each waits for its next run)</small>' : '') . ' <span class="cb-caret" aria-hidden="true">▾</span></summary>'
            . '<div class="cb-acts">' . (!$acked && $lv !== 'warning' ? $btn('ackgroup', 'Acknowledge all ' . count($members), true, '', $gk, ['class' => 'ak-b cb-xs']) : '') . '</div>'
            . '<ul class="cb-al cb-al-in">' . implode('', array_map($one, $members)) . '</ul></details></li>';
    }
    return $h . '</ul><p class="ak-note cb-anone" hidden>Nothing here for this filter.</p><p class="ak-note">An alarm that clears leaves this list for the <a class="underline" href="#log">logbook</a>.</p></details>';
}

/**
 * The statement to the OpenStreetMap community, opened in the board's drawer from the Overpass alarm (Jason, 2026-11-18:
 * a step never leaves the page). Its source is research/osm-overpass-2026-11-13.md §2 in the docs repo; kept the same.
 */
function tv_cb_statement_osm(): string
{
    $e = 'tv_w_e';
    $subject = 'Traversence: our Overpass requests get 406. What we did wrong, what we\'ve changed, and a question';
    $body = "Hello. I run Traversence (traversence.com), a local directory and travel guide for rural towns in Arizona and New Mexico. Our crawler uses the Overpass API to find the places in a town (cafés, garages, clinics, campgrounds) so we can check each one on its own website. Since 2026-11-13 our requests to overpass-api.de get 406 Not Acceptable. Our User-Agents were TraversenceCrawler/1.0 (+https://traversence.com; local directory of community resources) (the crawler, on Railway) and Traversence/1.0 (+https://traversence.com) (our site, when a visitor's search finds little nearby); both now end with our contact, support@traversence.com.\n\n"
        . "How we use OpenStreetMap. It's a layer of its own on our site, always credited \"© OpenStreetMap contributors\" with a link to the copyright page. We don't copy its data into our listings: a place OpenStreetMap shows tells us where to look, and what we list comes from the business's own website or official records.\n\n"
        . "How we recognized our failures. Our crawler's logs show every request to overpass-api.de answered 406 from 16:51 UTC on 2026-11-13, and again at 12:54 UTC on 2026-11-14. Reading your guidance, we saw that the fault was ours:\n- our queries were large: every kind of place in a town in one query, over a box around all its ZIP codes, with [timeout:110];\n- our crawler ran every 5 minutes and retried failed towns run after run, with no back-off;\n- when your server answered 406, our code asked again at once with the same query as a GET;\n- our site also asked overpass-api.de first, before any other instance;\n- nothing told us a server had refused us: the 406s were logged as ordinary failures, and nothing stopped.\n\n"
        . "What we've changed so it won't happen again.\n- We've stopped asking overpass-api.de, from the crawler and from our site, until we hear from you. We won't change our User-Agent or address to get round the block.\n- We no longer use Overpass for bulk reading. Our crawler reads OpenStreetMap from Geofabrik's weekly state extracts (Arizona and New Mexico), kept in a table of its own on our site. Overpass is only a fallback: for a town or kind our copy doesn't hold, and on our site when a visitor's search finds little near a place; and only on a server that hasn't refused us.\n- Each server has a circuit breaker. A 406, 403 or 401 stops every request to that server at once and alerts a person on our team. We never retry a refused server on our own; only a person turns it back on, after you tell us we may. While it's blocked, we don't contact it at all, not even its /api/status page.\n- A 429 makes us follow your Retry-After, or without one wait twice as long. A timeout or server error backs off (10 minutes, doubling to 6 hours).\n- Our queries are split by kind (one kind of place per query), kept to a small area, with [timeout:25], and sent one at a time.\n- A town and kind that has been answered isn't asked again for 3 months.\n- Our own daily budget is a quarter of the published guidance for the public instances (2,500 queries and 250 MB a day, crawler and site together), and half that while any server blocks us and for two weeks after.\n\n"
        . "Could you tell us whether we're banned, and why, and what you'd like us to change to be allowed back? Our crawler's bulk reading has already moved to Geofabrik's extracts. If even single lookups are more than you'd like on the public instance, we'll keep to the extracts or run our own instance.\n\nJason, Traversence, support@traversence.com";
    return '<div id="cb" class="cb-statement"><h2 class="ak-h">The statement to the OpenStreetMap community</h2>'
        . '<p data-tv-status="info" data-tv-label="Where to send it:">Post it on the OpenStreetMap Community Forum, in <b>Help and support</b> with the tag <b>overpass</b>: <a class="underline" href="https://community.openstreetmap.org/c/help/" target="_blank" rel="noopener">community.openstreetmap.org</a> (sign in with an OpenStreetMap account). FOSSGIS runs overpass-api.de; its terms are on fossgis.de. When they answer, come back to the alarm: Reactivate on OpenStreetMap\'s card, then Mark resolved.</p>'
        . '<p><b>Subject:</b> <span data-cb-copy-src="s">' . $e($subject) . '</span> <button type="button" class="ak-b cb-xs" data-cb-copy="s">Copy the subject</button></p>'
        . '<pre class="cb-stmt" data-cb-copy-src="b">' . $e($body) . '</pre><p><button type="button" class="ak-b" data-cb-copy="b">Copy the statement</button></p>'
        . '<p class="ak-note">Its source, with the evidence and where each fix stands: research/osm-overpass-2026-11-13.md in the docs repo.</p></div>'
        . '<style>.cb-statement{max-width:52rem}.cb-stmt{white-space:pre-wrap;font:14px/1.5 system-ui,sans-serif;background:#FFFDF8;border:1px solid #EFE6D6;border-radius:.4rem;padding:.7rem .8rem}</style>'
        . '<script>document.addEventListener("click",function(e){var b=e.target.closest("[data-cb-copy]");if(!b)return;var t=document.querySelector("[data-cb-copy-src=\'"+b.getAttribute("data-cb-copy")+"\']");if(!t)return;'
        . 'navigator.clipboard.writeText(t.textContent).then(function(){if(window.TvAsk)TvAsk.say("Copied.",{anchor:b});},function(){if(window.TvAsk)TvAsk.say("Your browser didn\'t allow copying: select the text and copy it.",{anchor:b,bad:true});});});</script>';
}

// ===================================================================================================================
// The board's views (the control board's build, step 4: plan §2.1 rows 32 and 38; §4 D5, D15, D16, D17, D22's page).
// "Is it delivering?" first (03-cards.md §4: "The board's first view: healthy, held, needs me"; §27: "each snapshot … is a
// single gauge card that i can click to drill down", Jason, 2026-11-22), then Development (gauge-lifecycle.md §1–§6), then
// Incidents (IR-####, 03 §7; 02 P12). Arc only (03 §27, Jason's choice, 2026-11-22). Every section and card carries
// data-copy, for js/tv-copy.js's Copy (D15; Jason, live-reads C6: "Probably need a copy by section feature").
// ===================================================================================================================

/** The views as tabs, before the stages (03 §27: the Board view is the gauge cards; "Is it delivering?" is the first view). */
const TV_CB_VIEWS = ['deliver' => ['Is it delivering?', 'Healthy, held, needs me; every snapshot a gauge card'],
    'development' => ['Development', 'The 16 development gauges, from Planning to Monitoring; Site health\'s list'],
    'incidents' => ['Incidents', 'Every Incident Report, IR-####, with its members, radius and timeline']];

/** A moment, labelled with its zone (CLAUDE.md "Times where they happened": shown in the viewer's zone when none is stored). */
function tv_cb_at(?int $at): string
{
    return $at ? '<time datetime="' . gmdate('c', $at) . '" data-tv-time>' . gmdate('M j, H:i', $at) . ' UTC</time>' : '';
}

/** The three questions, in one line at the top (03 §4; Jason's answers §10.1: "as drawn"). The light stays red while any trip holds, seen or not (g4 F1; 02 row 15). */
function tv_cb_three(PDO $pdo, array $alarms, array $ac, array $names): string
{
    $e = 'tv_w_e';
    $trips = array_values(array_filter($alarms, fn ($a) => $a['level'] === 'trip' && $a['cleared_at'] === null));
    $seen = array_values(array_filter($trips, fn ($a) => $a['ack_at'] !== null));
    $off = 0; try { foreach (ControlBoard::pausedNow($pdo) as $x) if (($x['g'] ?? '') === 'sys' && isset($x['left'])) $off++; } catch (Throwable $x) {}   // switches still off, left by a closed trip (02 CS-3)
    $parts = [];
    if ($ac['trip']) $parts[] = $ac['trip'] . ' trip' . ($ac['trip'] === 1 ? '' : 's') . ($seen ? ' (' . (count($seen) === count($trips) ? 'seen by ' . implode(', ', array_unique(array_map(fn ($a) => (string) ($a['ack_name'] ?: 'staff'), $seen))) : count($seen) . ' seen') . ')' : '');
    if ($ac['warning']) $parts[] = $ac['warning'] . ' warning' . ($ac['warning'] === 1 ? '' : 's');
    if ($off) $parts[] = $off . ' switch' . ($off === 1 ? '' : 'es') . ' still off';
    $light = $ac['trip'] ? 'bad' : ($ac['warning'] || $off ? 'info' : 'ok');
    $healthy = '<p data-tv-status="' . $light . '" data-tv-label="' . ($ac['trip'] ? 'Not healthy:' : ($parts ? 'Mostly:' : 'Healthy:')) . '">' . $e($parts ? implode(' · ', $parts) : 'Nothing open: no trip, no warning, no switch left off.') . '</p>';
    // what's held and why: each hold by its cause, with its Incident Report (03 §4)
    $held = '';
    foreach ($trips as $a) {
        $sw = ControlAlarms::stoppedWords($a);
        $ir = !empty($a['incident_id']) ? ' <a class="underline" href="/admin/config/control?ir=' . (int) $a['incident_id'] . '" data-peek-frame>' . $e(ControlIncidents::number((int) $a['incident_id'])) . '</a>' : '';
        $held .= '<li><a class="underline" href="#cb-alarm-' . (int) $a['id'] . '" data-cb-go="cb-alarm-' . (int) $a['id'] . '">' . $e(rtrim((string) $a['title'], '. ')) . '</a>' . $ir . ': '
            . $e($sw !== '' ? 'holds ' . $sw . '.' : (empty($a['tripped_at']) ? 'the system is still trying; nothing is held yet.' : 'it holds what uses ' . ($names[$a['proc']] ?? $a['proc']) . '.')) . '</li>';
    }
    $held = $held !== '' ? '<ul class="cb-q3l">' . $held . '</ul>' : '<p data-tv-status="ok" data-tv-label="Nothing held:">No trip holds anything now.</p>';
    $need = $ac['trip'] + $off;
    $me = $need ? '<p data-tv-status="bad" data-tv-label="Waiting on you:">' . $e(implode(' and ', array_filter([$ac['trip'] ? $ac['trip'] . ' trip' . ($ac['trip'] === 1 ? '' : 's') : '', $off ? $off . ' switch' . ($off === 1 ? '' : 'es') . ' still off' : '']))) . '. '
        . '<a class="underline" href="#cb-alarms" data-cb-go="cb-alarms">The Open list</a>' . ($off ? ' · <a class="underline" href="#cb-paused" data-cb-go="cb-paused">Paused now</a>' : '') . '</p>'
        : '<p data-tv-status="ok" data-tv-label="Nothing:">Nothing waits on you.</p>';
    return '<div class="ak-card tv-bleed cb-q3" id="cb-q3" data-copy="Is it delivering: healthy, held, needs me">'
        . '<div class="cb-q"><h3 class="cb-qh">Is it healthy</h3>' . $healthy . '</div>'
        . '<div class="cb-q"><h3 class="cb-qh">What\'s held and why</h3>' . $held . '</div>'
        . '<div class="cb-q"><h3 class="cb-qh">What needs me</h3>' . $me . '</div></div>';
}

/**
 * "What's wrong now" (step3/board/index.html's qbar; Jason, 2026-11-23: the chips in place of the alarm text), as one
 * Alerts dropdown (Jason, 2026-11-24, his 390px screenshot: "1 full width dropdown for alerts … instead of this visual
 * chaos"): its count is the board's one alarm count (ControlAlarms::counts: every open trip, and the warnings not yet seen;
 * 02-state-model P9: a number shown is computed the same way everywhere it appears), and inside it the groups, Trips,
 * Warnings, Caps, Checks, Sync, Hosts and Paused now, each with its count and its items; each item opens its part's own page
 * in the one side panel, titled by the part's name. Paused now isn't an alarm: its count is its own, never added to Alerts'.
 * Nothing open: one green All clear. On phones the board's pages sit beside it in their own ☰ (tv_cb_pages_menu).
 */
function tv_cb_live_alarms(array $open): array
{
    // the alarms the one count counts (ControlAlarms::counts): open trips, seen or not; open warnings not yet seen
    return array_values(array_filter($open, fn ($a) => $a['cleared_at'] === null && ($a['level'] === 'trip' || ($a['level'] === 'warning' && empty($a['ack_at'])))));
}
function tv_cb_qbar(PDO $pdo, array $open, array $names, array $core, string $stage, string $pagesMenu = ''): string
{
    $e = 'tv_w_e';
    $part = function (array $a) use ($pdo, $names): array { $p = (string) $a['proc']; return [$names[$p] ?? ControlAlarms::partName($pdo, $p), '/admin/config/control/' . str_replace(':', '-', $p)]; };
    // each item says its thread's title, the one its card and Pulse show (decisions/0176 §2: one title per thread on every
    // surface; the Design reviewer, 2026-11-24: Alerts said the stored title, the card "Overture Maps Places' read failed")
    $title = function (array $a) use ($pdo): string { try { $t = (string) (ControlAlarms::facts($pdo, $a)['title'] ?? ''); if ($t !== '') return $t; } catch (Throwable $x) {} return (string) $a['title']; };
    $item = function (array $a, string $tone, string $b) use ($e, $part, $title): string { [$nm, $u] = $part($a);
        return '<a class="cb-qj ' . $tone . '" href="' . $e($u) . '" data-peek-frame="' . $e($nm) . '"><span>' . $e(rtrim($title($a), '. ')) . '</span>' . ($b !== '' ? '<b>' . $e($b) . '</b>' : '') . '</a>'; };
    $live = tv_cb_live_alarms($open);
    $cap = fn ($a) => str_starts_with((string) $a['akey'], 'ops:cap:'); $pf = fn ($a) => str_starts_with((string) $a['akey'], 'pf:');
    $sync = fn ($a) => str_starts_with((string) $a['akey'], 'ops:watch:');   // Railway and the sync, watched (the mockup's Sync chip, 03 §27)
    $groups = [
        ['Trips', 'r', array_values(array_filter($live, fn ($a) => $a['level'] === 'trip' && ControlAlarms::startedAgain($a) === '')), fn ($a) => !empty($a['incident_id']) ? ControlIncidents::number((int) $a['incident_id']) : (empty($a['tripped_at']) ? 'trying' : 'trip')],
        // trips started again for their next run (task-trip-3): amber, "Waiting for its run", as their cards; red only if it fails again
        ['Waiting for its run', 'a', array_values(array_filter($live, fn ($a) => $a['level'] === 'trip' && ControlAlarms::startedAgain($a) !== '')), fn ($a) => !empty($a['incident_id']) ? ControlIncidents::number((int) $a['incident_id']) : ''],
        ['Warnings', 'a', array_values(array_filter($live, fn ($a) => $a['level'] === 'warning' && !$cap($a) && !$pf($a) && !$sync($a))), fn ($a) => (int) $a['n_times'] > 1 ? (int) $a['n_times'] . '×' : ''],
        ['Caps', 'a', array_values(array_filter($live, fn ($a) => $a['level'] === 'warning' && $cap($a))), fn ($a) => (string) ($a['reading'] ?: 'cap')],
        ['Checks', 'a', array_values(array_filter($live, fn ($a) => $a['level'] === 'warning' && $pf($a))), fn ($a) => 'pre-flight'],
        ['Sync', 'n', array_values(array_filter($live, fn ($a) => $a['level'] === 'warning' && $sync($a))), fn ($a) => ''],
    ];
    $grp = fn (string $label, string $tone, int $n, string $body) => '<section class="cb-ag ' . $tone . '" aria-label="' . $e($label) . '"><h3 class="cb-agh"><span>' . $e($label) . '</span><b>' . $n . '</b></h3>' . $body . '</section>';
    $body = ''; $nTrip = 0; $nWarn = 0; $parts = [];
    $word = ['Trips' => ['trip', 'trips'], 'Waiting for its run' => ['waiting', 'waiting'], 'Warnings' => ['warning', 'warnings'], 'Caps' => ['cap', 'caps'], 'Checks' => ['check', 'checks'], 'Sync' => ['sync watch', 'sync watches']];
    foreach ($groups as [$label, $tone, $list, $badge]) {
        if (!$list) continue;
        if ($label === 'Trips') $nTrip = count($list); else $nWarn += count($list);
        $parts[] = count($list) . ' ' . $word[$label][count($list) === 1 ? 0 : 1];
        $more = count($list) > 30 ? '<button type="button" class="cb-qj cb-qall" data-cb-tab="alarms"><span>All ' . count($list) . ' in the alarm list</span><b>open</b></button>' : '';
        $body .= $grp($label, $tone, count($list), implode('', array_map(fn ($a) => $item($a, $tone, (string) $badge($a)), array_slice($list, 0, 30))) . $more);
    }
    // the core hosts: a group only when one is down (everything holds)
    $bad = array_values(array_filter($core, fn ($c) => $c[1] === 'bad'));
    if ($bad) $body .= $grp('Hosts', 'r', count($bad), implode('', array_map(fn ($c) => '<button type="button" class="cb-qj r" data-cb-tab="paused"><span>' . $e($c[0]) . ' is down: everything holds</span><b>host</b></button>', $bad)));
    // Paused now: its count and the first few, then its whole page (the three groups, the core hosts, healthy, held, needs me)
    $pz = []; try { $pz = ControlBoard::pausedNow($pdo); } catch (Throwable $x) {}
    $li = ''; foreach (array_slice($pz, 0, 8) as $x) { $w = (string) ($x[0] ?? 'Paused'); $bg = ['sys' => 'by a trip', 'purpose' => 'off on purpose'][$x['g'] ?? ''] ?? 'paused';
        $li .= (string) ($x[3] ?? '') !== '' ? '<a class="cb-qj n" href="' . $e((string) $x[3]) . '" data-peek-frame="' . $e($w) . '"><span>' . $e($w) . '</span><b>' . $e($bg) . '</b></a>'
            : '<button type="button" class="cb-qj n" data-cb-tab="paused"><span>' . $e($w) . '</span><b>' . $e($bg) . '</b></button>'; }
    $body .= $grp('Paused now', 'n', count($pz), $li . '<button type="button" class="cb-qj cb-qall" data-cb-tab="paused"><span>' . ($pz ? 'All of Paused now, with healthy, held and needs me' : 'Nothing is paused: its page, with healthy, held and needs me') . '</span><b>open</b></button>');
    // the warnings someone has seen: listed, never counted (the system is still handling them; ControlAlarms::counts)
    $seen = array_values(array_filter($open, fn ($a) => $a['cleared_at'] === null && $a['level'] === 'warning' && !empty($a['ack_at'])));
    if ($seen) $body .= $grp('Seen', 'n', count($seen), '<p class="cb-agn">Not counted: seen, and the system is still handling them.</p>' . implode('', array_map(fn ($a) => $item($a, 'n', ''), array_slice($seen, 0, 30))));
    $n = $nTrip + $nWarn;
    $say = $n ? implode(' · ', $parts) : 'All clear';
    $tone = $nTrip ? 'r' : ($nWarn ? 'a' : 'g');
    $alerts = '<details class="cb-pop cb-alerts ' . $tone . '"><summary class="cb-qgs" title="' . $e($n ? $say . ' open' : 'No alarm open') . '"><span class="cb-alw">Alerts</span><b>' . $n . '</b><small class="cb-als">' . $e($say) . '</small><span aria-hidden="true" class="cb-alc">▾</span></summary>'
        . '<div class="cb-popb" role="dialog" aria-label="Alerts"><button class="cb-px" type="button" aria-label="Close">×</button>' . $body . '</div></details>';
    return '<nav class="cb-qbar" aria-label="What\'s wrong now">' . $pagesMenu . $alerts . '</nav>';
}

/**
 * The rail of views (step3/board/index.html; Jason's ruling of 2026-11-23, 03 §27: "Development · Board · Modules · Workflows
 * · Diagnostics", Development first so it's against the board fast): Development and Board are the board's own views,
 * Modules (the Service register) and Workflows their own pages in System Configuration, Diagnostics the stages and the
 * tables (Alarms, Logbook, Ledger, Upkeep, Reports, Scheduled tasks, Incidents). Sticky on the left at 768px and wider;
 * on phones the same entries are a ☰ menu beside Alerts (Jason, 2026-11-24: "Or a hamburger menu for pages like we've
 * used before?"; CLAUDE.md, phone menus: ☰ tools on the left), never a wrapped row. Board's count is the one alarm count.
 */
function tv_cb_rail_items(array $open, array $names, string $stage): string
{
    $e = 'tv_w_e';
    $ic = ['development' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>', 'deliver' => '<path d="M3 3h7v7H3zM14 3h7v4h-7zM14 10h7v11h-7zM3 13h7v8H3z"/>',
        'modules' => '<path d="M4 4h16v6H4zM4 14h16v6H4z"/>', 'workflows' => '<path d="M5 6h6M5 12h14M5 18h9"/><circle cx="15" cy="6" r="2"/><circle cx="18" cy="18" r="2"/>',
        'diagnostics' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>'];
    $svg = fn (string $k) => '<svg class="cb-ric" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round">' . $ic[$k] . '</svg>';
    $nOpen = count(tv_cb_live_alarms($open));
    $nMod = 0; try { require_once __DIR__ . '/../lib/OutsideServices.php'; $nMod = count(OutsideServices::COMPANIES); } catch (Throwable $x) {}
    $nWf = count(array_filter(array_keys($names), fn ($k) => str_starts_with((string) $k, 'wf:')));
    $cnt = fn (int $n, string $what) => $n > 0 ? '<span class="cb-rcnt" title="' . $e($what) . '">' . $n . '</span>' : '';
    return '<button type="button" data-cb-tab="development" data-cb-rail="development">' . $svg('development') . 'Development</button>'
        . '<button type="button" data-cb-tab="deliver" data-cb-rail="deliver">' . $svg('deliver') . 'Board' . $cnt($nOpen, $nOpen . ' alarm' . ($nOpen === 1 ? '' : 's') . ' open') . '</button>'
        . '<a href="/admin/config/register" data-cb-rail="modules">' . $svg('modules') . 'Modules' . $cnt($nMod, $nMod . ' modules') . '</a>'
        . '<a href="/admin/config/workflows" data-cb-rail="workflows">' . $svg('workflows') . 'Workflows' . $cnt($nWf, $nWf . ' workflows') . '</a>'
        . '<button type="button" data-cb-tab="' . $e($stage) . '" data-cb-rail="diagnostics">' . $svg('diagnostics') . 'Diagnostics' . $cnt(count($names), count($names) . ' parts watched') . '</button>';
}
function tv_cb_rail(array $open, array $names, string $stage): string
{
    return '<nav class="cb-rail" aria-label="Board views">' . tv_cb_rail_items($open, $names, $stage) . '</nav>';
}
/** The board's pages as the phone's ☰ (below 768px only): the rail's entries, the current one marked and named on the button. */
function tv_cb_pages_menu(array $open, array $names, string $stage): string
{
    return '<details class="cb-pop cb-pmenu"><summary class="cb-qgs" aria-label="The board\'s pages"><span class="cb-hb" aria-hidden="true">☰</span><span data-cb-pname>Board</span><span aria-hidden="true" class="cb-alc">▾</span></summary>'
        . '<div class="cb-popb cb-pml" role="dialog" aria-label="The board\'s pages"><button class="cb-px" type="button" aria-label="Close">×</button>' . str_replace(' data-cb-rail="', ' data-cb-prail="', tv_cb_rail_items($open, $names, $stage)) . '</div></details>';
}

/** A gauge's stage as a key (the reader gives the word, "Phased Development", and its key 'k'). */
function tv_cb_gstage(array $g): string
{
    $st = (string) ($g['stage'] ?? 'planning');
    if (isset(TV_SG_STAGES[$st])) return $st;
    foreach (TV_SG_STAGES as $k => [$w]) if ($w === $st) return $k;
    return 'planning';
}

/** A gauge's key for tv_score_gauge: its stage below Monitoring, its band at it (gauge-lifecycle.md §1, §7). */
function tv_cb_gk(array $g): string
{
    $st = tv_cb_gstage($g);
    return $st === 'monitor' ? (in_array($g['band'] ?? '', ['good', 'below', 'poor', 'none'], true) ? (string) $g['band'] : 'none') : $st;
}

/** A share on a drill path: real where the readings hold it, else marked scenario (03 §27). */
function tv_cb_share($s): string
{
    if ($s === null || $s === '' || $s === []) return '';
    [$basis, $txt] = is_array($s) ? [(string) ($s[0] ?? ''), (string) ($s[1] ?? '')] : ['', (string) $s];
    $scn = $basis === 'scn' || $basis === 'scenario' || preg_match('/^scenario\b/i', $txt);
    $txt = (string) preg_replace('/^scenario:?\s*/i', '', $txt);
    return ' <span class="cb-share">' . ($scn ? '<span class="cb-scn">scenario</span> ' : '<span class="cb-live">live</span> ') . tv_w_e($txt) . '</span>';
}

/** One drill path's rows: [id, name, state, share|null, href]; a link the map can't give says "association not mapped" (03 §27). */
function tv_cb_path_rows(array $rows, string $none): string
{
    $e = 'tv_w_e';
    if (!$rows) return '<p class="ak-note">' . $e($none) . '</p>';
    $h = '<ul class="cb-pathl">';
    foreach ($rows as $r) {
        $r = array_values((array) $r); [$id, $name, $state, $share, $href] = $r + ['', '', '', null, ''];
        $nm = (string) $name !== '' ? (string) $name : (string) $id;
        $h .= '<li><span class="cb-pathn">' . ((string) $href !== '' ? '<a class="underline" href="' . $e((string) $href) . '" data-peek-frame>' . $e($nm) . '</a>' : $e($nm) . ' <span class="cb-unm">association not mapped</span>') . '</span>'
            . ' <span class="cb-pill cb-pill-' . ((string) $state === '' ? 'ok' : (str_starts_with((string) $state, 'Trip') ? 'bad' : 'warn')) . '">' . $e((string) $state !== '' ? (string) $state : 'no open alarm') . '</span>' . tv_cb_share($share) . '</li>';
    }
    return $h . '</ul>';
}

/** The three drill buttons (Jason, 2026-11-22: "Workflows: what feeds the item. Core Services: the platform specific dependencies. Registered Services: the outside specific dependencies."). */
function tv_cb_drill_btns(string $pid): string
{
    $b = fn (string $k, string $l) => '<button type="button" class="ak-b cb-xs cb-drill" data-cb-sp="' . tv_w_e($pid . '-' . $k) . '">' . $l . '</button>';
    return $b('wf', 'Workflows ▸') . $b('cs', 'Core Services ▸') . $b('rs', 'Registered Services ▸');
}

/** A gauge's three path panels, each a side panel with Back (to its snapshot, or none for a development gauge), × and a click outside. */
function tv_cb_path_panels(string $pid, string $title, array $paths, string $back): string
{
    $e = 'tv_w_e'; $h = '';
    $kinds = ['wf' => ['workflows', 'Workflows', 'None: no workflow makes this.'], 'cs' => ['core', 'Core Services', 'association not mapped'], 'rs' => ['registered', 'Registered Services', 'association not mapped']];
    foreach ($kinds as $k => [$key, $label, $none]) {
        $nav = ($back !== '' ? '<button type="button" class="ak-b cb-xs" data-cb-sp="' . $e($back) . '">← Back</button>' : '');
        foreach ($kinds as $k2 => [, $l2]) if ($k2 !== $k) $nav .= '<button type="button" class="ak-b cb-xs" data-cb-sp="' . $e($pid . '-' . $k2) . '">' . $e($l2) . ' ▸</button>';
        $h .= '<aside class="cb-sp" id="' . $e($pid . '-' . $k) . '" role="dialog" aria-label="' . $e($title . ': ' . $label) . '" hidden data-copy="' . $e($title . ': ' . $label) . '">'
            . '<button type="button" class="cb-spx" data-cb-spx aria-label="Close">×</button><p class="cb-crumb">' . $e($title) . ' · the ' . $e($label) . ' path</p>'
            . '<h3 class="cb-title">' . $e($label) . '</h3>'
            . ($k === 'cs' ? '<p class="ak-note">Our own platform: the databases, jobs, staging, the sync, the workers on Railway and the dispatcher.</p>' : ($k === 'rs' ? '<p class="ak-note">The outside companies in the Service register, in the order each need reads them.</p>' : '<p class="ak-note">What feeds it: the workflows and their tasks, each with its state and its share.</p>'))
            . tv_cb_path_rows((array) ($paths[$key] ?? []), $none) . '<div class="cb-acts">' . $nav . '</div></aside>';
    }
    return $h;
}

/** One runtime gauge card and its panels (03 §27; gauge-lifecycle.md §7). Returns [card, panels]. */
function tv_cb_gauge_card(array $g, ?PDO $pdo = null): array
{
    $e = 'tv_w_e';
    $id = (string) preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($g['id'] ?? 'g')); $pid = 'cb-sp-' . $id;
    $title = (string) ($g['title'] ?? $id); $stage = tv_cb_gstage($g);
    $reading = $g['reading'] ?? null; $live = !empty($g['live']); $at = isset($g['at']) ? (int) $g['at'] : 0;
    $unchecked = $reading === null && !$live;   // no reading: "not checked yet", once (03 §27)
    $k = tv_cb_gk($g); $score = isset($g['score']) ? (float) $g['score'] : null;
    // at Monitoring with no reading: the empty grey dial (03 §27 "Not checked yet: an empty grey dial")
    if ($stage === 'monitor' && $unchecked) { $score = null; $k = 'none'; }
    $sw = TV_SG_STAGES[$stage][0];
    $label = $title . ': ' . ($score === null ? 'no reading yet' : number_format($score, 0) . ' percent') . ', ' . $sw . ($stage === 'monitor' && $k !== 'none' ? ' (' . ['good' => 'on target', 'below' => 'below target', 'poor' => 'poor'][$k] . ')' : '');
    $target = $g['target'] ?? null;
    $tgt = $target === null || $target === '' ? 'target: set by Jason' : 'target: ' . (string) $target;   // 03 §27: "Until Jason sets a target, the edges read 'target: set by Jason'"
    // the reading in a pill with its time ("live · 2:40 AM MST"), "scenario" where not real, or "not checked yet" once (03 §27)
    $rd = $unchecked ? '<span class="cb-rdp cb-rdn">not checked yet</span>'
        : '<span class="cb-rdp">' . $e((string) ($g['reading_text'] ?? '')) . '</span> ' . ($live ? '<span class="cb-live">live' . ($at ? ' · ' . tv_cb_at($at) : '') . '</span>' : '<span class="cb-scn">scenario</span>');
    $trend = trim((string) ($g['trend'] ?? '')) !== '' ? (string) $g['trend'] : 'No history yet';
    $su = (array) ($g['setup'] ?? []);
    $card = '<article class="cb-gc" id="cb-g-' . $e($id) . '" data-copy="' . $e($title) . '" data-stage="' . $e($stage) . '">'
        . '<button type="button" class="cb-gopen" data-cb-sp="' . $e($pid . '-snap') . '" aria-label="' . $e('Open ' . $title . ': ' . $sw) . '">'
        . '<span class="cb-gh">' . $e($title) . '</span>' . tv_score_gauge($score, $label, $k) . '<span class="cb-gst">' . tv_score_stage_pill($stage) . '</span>'
        . '<span class="cb-rd">' . $rd . '</span><span class="cb-gtr">' . $e($trend) . '</span><span class="cb-gtg">' . $e($tgt) . '</span></button>'
        . '<div class="cb-gb">' . ($unchecked ? '<button type="button" class="ak-b cb-xs cb-setup" data-cb-sp="' . $e($pid . '-snap') . '" data-cb-spat="' . $e($pid . '-setup') . '">Set up its check</button>' : '')
        . tv_cb_drill_btns($pid) . '</div>' . (empty($g['paths']['workflows']) && empty($g['paths']['core']) && empty($g['paths']['registered']) ? '<small class="cb-unm">association not mapped</small>' : '') . '<div class="cb-gcopy" data-copy-at></div></article>';
    // its panel as a drill-down (the gauge panel, approved by Jason 2026-11-23): What moves it, What you can do (each action in
    // one place, the first filled), its four setup steps with their state, its needs folded, the rest under Details. Each
    // task is one cycle: Request … → "Requested: the … build has it · who · when · Undo" → cleared once that step holds (Fix it only acts now); Approve the target is his.
    $gk = 'rt:' . $id;
    $steps = ['probe' => ['Its probe built', 'building its probe'], 'target' => ['Its target set', 'proposing its target'], 'check' => ['Its check passing', 'getting its check to pass'], 'alarm' => ['Its alarm wired to the board', 'wiring its alarm to the board']];
    $now = []; foreach ($steps as $sk => $_) $now[$steps[$sk][0]] = !empty($su[$sk]) ? 'ok' : 'build';
    $tasks = []; $apT = null;
    try { require_once __DIR__ . '/../lib/GaugeTasks.php'; if ($pdo) { $tasks = GaugeTasks::forGauge($pdo, $gk, $now); $apT = GaugeTasks::approvals($pdo)[$gk]['target'] ?? null; } } catch (Throwable $x) {}
    [$canAp, $whyAp] = class_exists('Access') ? Access::can('config.gauges.approve', ['min' => 'executive', 'depts' => []]) : [true, ''];
    $sw2 = (array) ($g['setup_why'] ?? []);
    $fixBtn = function (string $sk, string $label) use ($e, $gk, $steps, $title, $tasks, $pdo): string {
        $t = $tasks[$steps[$sk][0]] ?? null;
        $b = '<button type="button" class="cbg-b" data-cbg="fix" data-g="' . $e($gk) . '" data-item="' . $e($steps[$sk][0]) . '" data-b="build" data-what="' . $e($steps[$sk][1]) . '" data-build="' . $e($title) . '"' . ($t && $t['cleared_at'] === null ? ' hidden' : '') . '>' . $e($label) . '</button>';
        if ($t && $t['cleared_at'] === null) $b .= tv_cbg_req($t, 'It clears by itself once this step holds.', $pdo);
        return $b;
    };
    $cards = [];
    if (empty($su['target'])) {
        $prop = trim((string) preg_replace('/^target: set by Jason\s*\(?|\)$/', '', (string) ($g['target_proposed'] ?? '')));
        if ($apT) $cards[] = ['tgt', 'sign', '', '<div class="cbg-done"><span><b>Target approved</b> by ' . $e($apT['who']) . ', ' . tv_cbg_when($apT['at']) . ': ' . $e($apT['value']) . '.</span>' . tv_cbg_undo((int) $apT['id'], $apT['at']) . '</div>'];
        elseif ($prop !== '') $cards[] = ['tgt', 'sign', 'Its target isn\'t set. Proposed: ' . $prop . '. Today: ' . ($unchecked ? 'not checked yet' : (string) ($g['reading_text'] ?? '')) . '.',
            '<button type="button" class="cbg-b" data-cbg="target" data-g="' . $e($gk) . '" data-value="' . $e($prop) . '" data-title="' . $e($title) . '"' . ($canAp ? '' : ' disabled title="' . $e($whyAp) . '"') . '>Approve the target</button>'];
        else $cards[] = ['tgt', 'build', 'Its target isn\'t set, and none is proposed yet.', $fixBtn('target', 'Request a target')];
    }
    if (empty($su['probe'])) $cards[] = ['prb', 'build', 'Its probe isn\'t built' . (!empty($g['needs_probe']) ? ': ' . (is_array($g['needs_probe']) ? implode('; ', array_map('strval', $g['needs_probe'])) : (string) $g['needs_probe']) : '') . '.', $fixBtn('probe', 'Request the probe')];
    if (empty($su['check']) && !empty($su['probe'])) $cards[] = ['chk', 'fail', 'Its check isn\'t passing: ' . (trim((string) ($g['check_why'] ?? $sw2['check'] ?? '')) !== '' ? (string) ($g['check_why'] ?? $sw2['check']) : 'not checked yet') . '. It clears itself once it passes.', $fixBtn('check', 'Request a fix')];
    if (empty($su['alarm'])) $cards[] = ['alm', 'build', 'Its alarm isn\'t wired to the board yet: its reading doesn\'t raise one.', $fixBtn('alarm', 'Request its alarm')];
    foreach ((array) ($g['fix'] ?? []) as $f) { $f = array_values((array) $f); if ((string) ($f[1] ?? '') !== '') $cards[] = ['', 'ok', preg_replace('/^Fix it: /', '', (string) $f[0]) . ' feeds it. Its card shows its state and keys.', '<a class="cbg-b" href="' . $e((string) $f[1]) . '" data-peek-frame>Open its card</a>']; }
    $ch = ''; $first = true;
    foreach (array_slice($cards, 0, 4) as [$aid, $col, $txt, $btn]) {
        if (!$first) $btn = str_replace('class="cbg-b"', 'class="cbg-b cbg-o"', $btn);
        if (str_contains($btn, 'class="cbg-b')) $first = false;
        $ch .= '<div class="cbg-ac cbg-ac-' . $col . '"' . ($aid !== '' ? ' id="' . $e($pid . '-ac-' . $aid) . '"' : '') . '>' . ($txt !== '' ? '<p>' . $e($txt) . '</p>' : '') . $btn . '</div>';
    }
    // its setup: each step's state in words, a small link to its card above (each action in one place: the Design reviewer's change 5)
    $rows = '';
    foreach ($steps as $sk => [$sl]) {
        $ok = !empty($su[$sk]); $t = $tasks[$sl] ?? null;
        $state = $ok ? '✓ done' : ($t && $t['cleared_at'] === null ? 'fixing' : ($sk === 'target' ? 'needs you' : ($sk === 'check' ? 'an issue' : 'not yet')));
        $cls = $ok ? 'ok' : ($sk === 'check' ? 'fail' : ($sk === 'target' ? 'sign' : 'build'));
        $aid = ['probe' => 'prb', 'target' => 'tgt', 'check' => 'chk', 'alarm' => 'alm'][$sk];
        $link = $ok ? ($sk === 'alarm' ? tv_cbg_ic('bell', 'Open its alarms', '/admin/config/alarms', false, ' data-peek-frame') : '') : '<a class="cbg-sl" href="#' . $e($pid . '-ac-' . $aid) . '" data-cbg-to>' . ($sk === 'target' && !$apT ? 'Approve the target' : 'Fix it') . ' ↑</a>';
        $rows .= '<li class="cbg-it"><div class="cbg-ih"><span class="cbg-bk cbg-bk-' . $cls . '"></span><span class="cbg-nm2">' . $e($sl) . '</span><span class="cbg-sw cbg-sw-' . $cls . '">' . $e($state) . '</span>' . $link . '</div>'
            . (trim((string) ($sw2[$sk] ?? '')) !== '' ? '<p class="ak-note">' . $e((string) $sw2[$sk]) . '</p>' : '') . '</li>';
    }
    $done = 0; foreach ($steps as $sk => $_) if (!empty($su[$sk])) $done++;
    $panels = '<aside class="cb-sp" id="' . $e($pid . '-snap') . '" role="dialog" aria-label="' . $e($title) . '" hidden data-copy="' . $e($title) . '">'
        . '<button type="button" class="cb-spx" data-cb-spx aria-label="Close">×</button><p class="cb-crumb">Board · ' . $e((string) ($g['box'] ?? '')) . '</p><h3 class="cb-title">' . $e($title) . '</h3>'
        . '<div class="cb-gbig">' . tv_score_gauge($score, $label, $k, ['big' => true]) . tv_score_stage_pill($stage) . '</div>'
        . '<p class="cbg-top">' . ($stage === 'monitor' ? 'Its reading: ' . $rd . ' against its target, ' . $e($target === null || $target === '' ? 'set by Jason' : (string) $target) : 'What moves it: <b>' . $done . ' of 4 setup steps done</b>, then its reading') . '</p>'
        . ($ch !== '' ? '<h4 class="cb-h4">What you can do</h4><div class="cbg-acs">' . $ch . '</div>' : '')
        . '<h4 id="' . $e($pid . '-setup') . '" class="cb-h4">Its setup</h4><ul class="cbg-items cbg-all">' . $rows . '</ul>'
        . tv_cb_gd_slot('runtime', (string) ($g['id'] ?? $id))
        . '<details class="cbg-src"><summary>Details: what it measures</summary><dl class="cb-dl"><dt>Measure</dt><dd>' . $e((string) ($g['measure'] ?? '')) . '</dd><dt>Target</dt><dd>' . $e($target === null || $target === '' ? 'set by Jason' : (string) $target) . '</dd>'
        . '<dt>Reading</dt><dd>' . $rd . '</dd><dt>Trend</dt><dd>' . $e($trend) . '</dd></dl>' . (trim((string) ($g['why'] ?? '')) !== '' ? '<p class="ak-note">Why ' . $e($sw) . ': ' . $e((string) $g['why']) . '</p>' : '') . '</details>'
        . '<div class="cb-acts">' . tv_cb_drill_btns($pid) . '</div></aside>'
        . tv_cb_path_panels($pid, $title, (array) ($g['paths'] ?? []), $pid . '-snap');
    return [$card, $panels];
}

/* ---- The gauge panel as a drill-down (approved by Jason, 2026-11-23; handoffs/gauge-panel.md; CLAUDE.md "Every action is
 * a task"). Each item is one cycle: its evidence in place, Request a fix, "Requested: the … build has it · who · when · Undo", cleared by its check
 * (Jason: "1. I need to view the issue. 2. then press a button to fix it. 3. once fixed it should clear the issue"). No
 * sign-off on items; Approve the scope and Approve the target are his decisions (the session manager's rulings, 2026-11-23). */

/** Plain names for the check register's ids (the Design reviewer, 2026-11-23: ids stay in "Where this comes from"). */
const TV_CBG_NAMES = ['RENDER' => 'The page check', 'PATHS' => 'The path check', 'SEARCH' => 'The search check', 'LAYOUT' => 'The phone layout check',
    'PHOTOS' => 'The photos check', 'PHOTOS-SHOWN' => 'Photos shown', 'LOAD' => 'Page speed', 'BRAND' => 'Brand standards', 'TOOLS' => 'The tools check',
    'BOARD' => 'The board\'s readings', 'AUDIT' => 'The audit\'s own load'];
/** Where an item stands, in the lifecycle's order (gauge-lifecycle §3): key => words. */
const TV_CBG_BUCKETS = ['fail' => 'Issues to fix', 'build' => 'Not built yet', 'check' => 'No check yet', 'ok' => 'Done'];
const TV_CBG_STEPS = ['decided' => 'decided', 'built' => 'built', 'live' => 'live', 'checked' => 'checked', 'reviewed' => 'reviewed', 'proved' => 'proved'];
/** Rows shown before Show all (table-standard §9; the Design reviewer's change 9). */
const TV_CBG_FIRST = 10;

/** An inline action as an icon button, its title and aria-label naming the action and its target (Jason, 2026-11-23: "action icons with tool tips over text saying action urls"; 0058 §11). */
function tv_cbg_ic(string $icon, string $label, string $href, bool $out = false, string $extra = ''): string
{
    $e = 'tv_w_e';
    return '<a class="cbg-ic" href="' . $e($href) . '" title="' . $e($label) . '" aria-label="' . $e($label) . '"' . ($out ? ' target="_blank" rel="noopener"' : '') . $extra . '>' . (function_exists('tv_icon') ? tv_icon($icon, 'cbg-svg') : '↗') . '</a>';
}

/** An open request's line (the Design reviewer, 2026-11-23: name the real session): "Requested · waiting for a build to take
 * it" until a session takes it, then "Requested: the <session> has it", and "Being fixed in <release>" once named; past a day
 * untaken, "Not picked up yet" (the session manager alerted by its hourly read of build-requests.json). */
function tv_cbg_req(array $t, string $tail, ?PDO $pdo = null): string
{
    $e = 'tv_w_e'; $age = time() - (int) strtotime((string) $t['at'] . ' UTC');
    $pk = null; try { if (!$pdo && class_exists('Database')) $pdo = Database::connection(); if ($pdo && class_exists('ControlGauges')) $pk = ControlGauges::pickup($pdo, (int) $t['id']); } catch (Throwable $x) {}
    $who = ' · ' . $e((string) $t['who']) . ', ' . tv_cbg_when((string) $t['at']) . '. ';
    $head = $pk ? '<b>Requested:</b> the ' . $e($pk[0]) . ' has it' . ($pk[1] !== '' ? ' · <b>Being fixed in ' . $e($pk[1]) . '</b>' : '') . $who
        : '<b>Requested</b> · waiting for a build to take it' . $who . ($age > 86400 ? '<b class="cbg-late">Not picked up yet:</b> the session manager has been told. ' : '');
    return '<div class="cbg-done"><span>' . $head . $e($tail) . '</span><button type="button" class="cbg-sl" data-cbg="undo" data-id="' . (int) $t['id'] . '">Undo</button></div>';
}

function tv_cbg_name(string $cid): string { return TV_CBG_NAMES[$cid] ?? (preg_match('/^R\d/', $cid) ? 'Code checks' : $cid); }

/** An open finding's id on an item, if Site health has it: its review line, else a failing check's own finding. */
function tv_cbg_finding(array $it, array $find): ?string
{
    if (preg_match('/open finding: (f-[0-9a-f]+)/', (string) ($it['review'] ?? ''), $m) && isset($find[$m[1]])) return $m[1];
    foreach ((array) ($it['checks'] ?? []) as $c) if (is_array($c) && ($c[1] ?? '') === 'fail' && isset($c[2], $find[(string) $c[2]])) return (string) $c[2];
    return null;
}

/** Where an item stands now: fail (an issue), build (decided or held), check (no check reaches it), ok (done). */
function tv_cbg_bucket(array $it, array $find): string
{
    foreach ((array) ($it['checks'] ?? []) as $c) if (is_array($c) && ($c[1] ?? '') === 'fail') return 'fail';
    if (tv_cbg_finding($it, $find) !== null || str_starts_with((string) ($it['review'] ?? ''), 'open finding')) return 'fail';
    if (in_array((string) ($it['status'] ?? ''), ['decided', 'held', 'proposed'], true)) return 'build';
    if (!empty($it['no_check'])) return 'check';
    return 'ok';
}

/** One step's state on an item's row: met, fail (an issue), nocheck, no. */
function tv_cbg_dot(array $it, string $k, bool $issue): string
{
    if (in_array($k, (array) ($it['met'] ?? []), true)) return 'met';
    if ($k === 'checked' && $issue) return 'fail';
    if ($k === 'checked' && !empty($it['no_check'])) return 'nocheck';
    if ($k === 'reviewed' && $issue) return 'fail';
    return 'no';
}

/** A time as the site shows it: the moment, painted in the viewer's zone and labelled by TvTime. */
function tv_cbg_when(string $utc): string { $t = strtotime($utc . ' UTC'); return $t ? tv_cb_at($t) : ''; }

/**
 * A development gauge's drill-down, for its panel (the ?gauge= fragment): the bar of its items by where they stand (each
 * part filters the list), "What you can do", the items (what needs you first, 10 then Show all), each opening in place with
 * its evidence and its one Request a fix; a finding or a check opens over the panel with Back. $g: the gauge's row
 * (ControlGauges::development), $d: its detail (ControlGauges::detail).
 */
function tv_cbg_dev(PDO $pdo, array $g, array $d): string
{
    $e = 'tv_w_e';
    $gid = 'dev:' . (string) $g['id']; $title = (string) $g['title'];
    $find = [];
    try { require_once __DIR__ . '/../lib/SiteHealth.php'; foreach (SiteHealth::open($pdo) as $f) $find[(string) $f['id']] = $f; } catch (Throwable $x) {}
    $reg = ControlGauges::checks($pdo);
    $items = array_values(array_filter((array) ($d['list'] ?? []), 'is_array'));
    $now = []; foreach ($items as $it) $now[(string) $it['name']] = tv_cbg_bucket($it, $find);
    $tasks = GaugeTasks::forGauge($pdo, $gid, $now);   // an open Fix it whose item has moved on clears here, by its check
    $n = count($items);
    if (!$n) return '<p data-tv-status="info" data-tv-label="No items yet:">This gauge has no configured items, so nothing moves it yet.</p>';
    $cnt = array_fill_keys(array_keys(TV_CBG_BUCKETS), 0); foreach ($now as $b) $cnt[$b]++;
    // the bar: one part per group, each a button that filters the list (pressed again: all)
    $bar = '';
    foreach (TV_CBG_BUCKETS as $k => $w)
        $bar .= '<button type="button" class="cbg-seg cbg-s-' . $k . '" style="flex:' . $cnt[$k] . '" data-cbg-f="' . $k . '" aria-pressed="false" aria-label="' . $e($w . ': ' . $cnt[$k] . ' of ' . $n) . '" title="' . $e($w . ': ' . $cnt[$k]) . '"' . ($cnt[$k] ? '' : ' hidden') . '>' . ($cnt[$k] * 100 / $n >= 7 ? $cnt[$k] : '') . '</button>';
    $leg = '';
    foreach (TV_CBG_BUCKETS as $k => $w) $leg .= '<button type="button" class="cbg-lg" data-cbg-f="' . $k . '" aria-pressed="false"' . ($cnt[$k] ? '' : ' hidden') . '><span class="cbg-bk cbg-bk-' . $k . '"></span>' . $e($w) . ' <b data-cbg-n="' . $k . '">' . $cnt[$k] . '</b></button>';
    // what you can do: up to five, one filled button (the first), the rest outlined
    $cards = [];
    $ap = GaugeTasks::approvals($pdo)[$gid]['scope'] ?? null;
    [$canAp, $whyAp] = class_exists('Access') ? Access::can('config.gauges.approve', ['min' => 'executive', 'depts' => []]) : [true, ''];
    if (empty($g['signed']) || $ap) {
        $cards[] = ['sign', $ap ? '' : 'It stays at 0 in Planning until you approve what 100 means: these ' . $n . ' things, each with its six steps.',
            $ap ? '<div class="cbg-done"><span><b>Scope approved</b> by ' . $e($ap['who']) . ', ' . tv_cbg_when($ap['at']) . '.</span>' . tv_cbg_undo((int) $ap['id'], $ap['at']) . '</div>'
                : '<button type="button" class="cbg-b" data-cbg="scope" data-g="' . $e($gid) . '" data-title="' . $e($title) . '"' . ($canAp ? '' : ' disabled title="' . $e($whyAp) . '"') . '>Approve the scope</button>'];
    }
    if ($cnt['fail']) $cards[] = ['fail', '<span data-cbg-n="fail">' . $cnt['fail'] . '</span> issue' . ($cnt['fail'] === 1 ? '' : 's') . ' to fix. Each opens its evidence, with Request a fix; it clears by itself once its check passes.',
        '<button type="button" class="cbg-b" data-cbg-f="fail">Show them</button>', true];
    $nc = []; foreach ($items as $it) if ($now[(string) $it['name']] === 'check') foreach ((array) $it['no_check'] as $c) $nc[(string) $c] = ($nc[(string) $c] ?? 0) + 1;
    arsort($nc);
    foreach (array_slice($nc, 0, 1, true) as $cid => $k) {
        $built = str_starts_with((string) ($reg[$cid]['status'] ?? ''), 'built');
        $cards[] = ['check', tv_cbg_name($cid) . ($built ? ' runs, but doesn\'t reach ' . $k . ' of these yet.' : ' isn\'t built yet, and ' . $k . ' of these wait on it.'),
            '<button type="button" class="cbg-b" data-cbg-f="check">Show them</button>', true];
    }
    if ($cnt['build']) $cards[] = ['build', $cnt['build'] . ' thing' . ($cnt['build'] === 1 ? '' : 's') . ' decided or held, not live yet.', '<button type="button" class="cbg-b" data-cbg-f="build">Show them</button>', true];
    $ch = ''; $first = true;
    foreach (array_slice($cards, 0, 5) as $c) {
        $btn = $c[2];
        if (!$first) $btn = str_replace('class="cbg-b"', 'class="cbg-b cbg-o"', $btn);
        if (str_contains($btn, 'class="cbg-b')) $first = false;
        $ch .= '<div class="cbg-ac cbg-ac-' . $c[0] . '">' . ($c[1] !== '' ? '<p>' . $c[1] . '</p>' : '') . $btn . '</div>';
    }
    // the items, what needs you first; a sub view per finding and check they name
    $order = array_flip(array_keys(TV_CBG_BUCKETS));
    $idx = array_keys($items); usort($idx, fn ($a, $b) => [$order[$now[(string) $items[$a]['name']]], $a] <=> [$order[$now[(string) $items[$b]['name']]], $b]);
    $rows = ''; $subs = []; $j = 0;
    foreach ($idx as $i) { $rows .= tv_cbg_row($items[$i], $now[(string) $items[$i]['name']], $j++, $gid, $title, $find, $reg, $tasks[(string) $items[$i]['name']] ?? null, $subs); }
    $sub = '';
    foreach ($subs as $key => $_) $sub .= tv_cbg_sub($key, $find, $reg, $gid, $title);
    $leg2 = '<button type="button" class="cbg-info" aria-expanded="false" title="What the dots mean" aria-label="What the dots mean">ⓘ</button>'
        . '<div class="cbg-dleg" hidden><button type="button" class="cbg-dlx" aria-label="Close">×</button><p><b>Each dot is one step</b>: decided · built · live · checked · reviewed · proved.</p>'
        . '<ul><li><i class="cbg-d cbg-d-met"></i> met</li><li><i class="cbg-d cbg-d-fail"></i> an issue</li><li><i class="cbg-d cbg-d-nocheck"></i> no check yet</li><li><i class="cbg-d cbg-d-no"></i> not yet</li></ul></div>';
    return '<div class="cbg" data-cbg-g="' . $e($gid) . '" data-cbg-n0="' . $n . '">'
        . '<div class="cbg-bar" role="group" aria-label="' . $e('Its ' . $n . ' things by where they stand') . '">' . $bar . '</div><div class="cbg-legend">' . $leg . '</div>'
        . '<h4 class="cb-h4">What you can do</h4><div class="cbg-acs">' . $ch . '</div>'
        . '<div class="cbg-lh"><h4 class="cb-h4" data-cbg-h>What needs you first</h4><button type="button" class="cbg-b cbg-o cbg-sm" data-cbg-f="all" hidden>Show all</button></div>'
        . '<div class="cbg-key"><span>Steps: decided · built · live · checked · reviewed · proved</span>' . $leg2 . '</div>'
        . '<ul class="cbg-items">' . $rows . '</ul>' . ($n > TV_CBG_FIRST ? '<button type="button" class="cbg-b cbg-o cbg-sm cbg-more" data-cbg-more>Show all ' . $n . '</button>' : '')
        . '<details class="cbg-src"><summary>Details: where these numbers come from</summary><p><a class="underline" href="https://github.com/degraffics/traversence/blob/main/site-audit/results/gauges-items.json" target="_blank" rel="noopener">gauges-items.json ↗</a> '
        . '<a class="underline" href="https://github.com/degraffics/traversence/blob/main/handoffs/control-board-plan/step3/sections/gauge-lifecycle.md" target="_blank" rel="noopener">the rule: gauge-lifecycle.md ↗</a></p></details>'
        . $sub . '</div>';
}

/** Undo, within a day (GaugeTasks::UNDO_HOURS); after that the line stands and the logbook keeps it. */
function tv_cbg_undo(int $id, string $at): string
{
    return strtotime($at . ' UTC') > time() - GaugeTasks::UNDO_HOURS * 3600 ? '<button type="button" class="cbg-sl" data-cbg="undo" data-id="' . $id . '">Undo</button>' : '';
}

/** One item's row: its name (the page itself, Peek; else where it's decided), its six dots, and in place its evidence and its one task. */
function tv_cbg_row(array $it, string $b, int $j, string $gid, string $title, array $find, array $reg, ?array $task, array &$subs): string
{
    $e = 'tv_w_e';
    $name = (string) $it['name']; $route = (string) ($it['route'] ?? '');
    $src = (string) ($it['source'] ?? '');
    $srcLink = tv_cbg_links($src);
    $nm = $route !== '' && str_starts_with($route, '/')
        ? '<a class="cbg-nm" href="' . $e($route) . '" data-peek-frame title="Open the page beside the board">' . $e($name) . '</a>'
        : ($srcLink ? '<a class="cbg-nm" href="' . $e($srcLink[0][1]) . '" target="_blank" rel="noopener" title="Where it\'s decided">' . $e($name) . '</a>' : '<span class="cbg-nm">' . $e($name) . '</span>');
    $issue = $b === 'fail';
    $dots = ''; $lab = []; $firstOpen = null;
    foreach (TV_CBG_STEPS as $k => $w) {
        $st = tv_cbg_dot($it, $k, $issue);
        $sw = ['met' => 'met', 'fail' => 'an issue', 'nocheck' => 'no check yet', 'no' => 'not yet'][$st];
        $dots .= '<i class="cbg-d cbg-d-' . $st . '" title="' . $e($w . ': ' . $sw) . '"></i>'; $lab[] = $w . ': ' . $sw;
        if ($firstOpen === null && $st !== 'met') $firstOpen = ucfirst($sw) . ' at ' . $w;
    }
    $f = tv_cbg_finding($it, $find);
    $fails = []; foreach ((array) ($it['checks'] ?? []) as $c) if (is_array($c) && ($c[1] ?? '') === 'fail') $fails[] = (string) $c[0];
    // what it is, in one sentence; once cleared, what it was (the Design reviewer's build note: words follow the state)
    if ($task && $task['cleared_at'] !== null) $say = 'Was: ' . $task['what'] . '.';
    elseif ($b === 'fail') $say = 'The issue: ' . ($f !== null ? rtrim((string) $find[$f]['title'], '.') . '. In the way of ' . (int) $find[$f]['blocks'] . ' path' . ((int) $find[$f]['blocks'] === 1 ? '' : 's') : tv_cbg_name($fails[0] ?? '') . ' failed on this page on its last run' . (preg_match('/\(([^()]*UTC)\)/', (string) ($reg[$fails[0] ?? '']['latest'] ?? ''), $m) && ($ts = strtotime($m[1])) ? ' (@@T' . $ts . '@@)' : '')) . '. It clears by itself once it passes again.';
    elseif ($b === 'build') $say = ($it['status'] ?? '') === 'held' ? 'Built and held: it goes live with its release.' : 'Decided, not built yet. It moves the gauge once a build makes it.';
    elseif ($b === 'check') $say = 'Live, and every check that reaches it passes. Not yet reached by: ' . implode(', ', array_map(fn ($c) => strtolower(tv_cbg_name((string) $c)), (array) $it['no_check'])) . ', so it isn\'t done until a check is added.';
    else $say = 'Done: every step met.';
    // its evidence: a chip per check, each leading to its result, its finding or what it needs
    $chips = '';
    foreach ((array) ($it['checks'] ?? []) as $c) {
        if (!is_array($c)) continue;
        $cid = (string) $c[0]; $r = (string) ($c[1] ?? ''); $nmC = tv_cbg_name($cid); $rg = $reg[$cid] ?? ($reg['R01'] ?? []);
        if ($r === 'pass') { $ev = (string) ($rg['evidence'] ?? ''); $chips .= '<a class="cbg-chip cbg-c-pass" href="' . $e(str_starts_with($ev, 'https://') ? $ev : 'https://github.com/degraffics/traversence/blob/main/site-audit/scorecard.md') . '" target="_blank" rel="noopener" title="' . $e('Open ' . $nmC . '\'s latest run') . '">' . $e($nmC) . ': passes</a>'; }
        elseif ($r === 'fail') {
            if ($task && $task['cleared_at'] !== null) continue;
            $fid = isset($c[2], $find[(string) $c[2]]) ? (string) $c[2] : $f;
            if ($fid !== null) { $subs['f:' . $fid] = 1; $chips .= '<button type="button" class="cbg-chip cbg-c-fail" data-cbg-open="f:' . $e($fid) . '">' . $e($nmC) . ': failed · see the evidence</button>'; }
            else { $subs['c:' . $cid] = 1; $chips .= '<button type="button" class="cbg-chip cbg-c-fail" data-cbg-open="c:' . $e($cid) . '">' . $e($nmC) . ': failed · see what it found</button>'; }
        } elseif ($r === 'partial') { $subs['c:' . $cid] = 1; $chips .= '<button type="button" class="cbg-chip cbg-c-part" data-cbg-open="c:' . $e($cid) . '">' . $e($nmC) . ': partly · what\'s missing</button>'; }
        else { $k2 = str_starts_with((string) ($rg['status'] ?? ''), 'built') ? 'r:' : 'c:'; $subs[$k2 . $cid] = 1; $chips .= '<button type="button" class="cbg-chip cbg-c-none" data-cbg-open="' . $e($k2 . $cid) . '">' . $e($nmC) . ($k2 === 'r:' ? ': doesn\'t reach it yet · why' : ': no check yet · what it needs') . '</button>'; }
    }
    if ($chips === '') $chips = '<span class="cbg-chip cbg-c-none cbg-plain">No check applies until it\'s built</span>';
    // its one task, or its line once done
    $acts = ''; $more = [];
    $what = $b === 'fail' ? ($f !== null ? rtrim((string) $find[$f]['title'], '.') : tv_cbg_name($fails[0] ?? '') . ' failed') : ($b === 'build' ? 'building it' : ($b === 'check' ? 'adding ' . strtolower(tv_cbg_name((string) ($it['no_check'][0] ?? ''))) : ''));
    $fixA = ' data-cbg="fix" data-g="' . $e($gid) . '" data-item="' . $e($name) . '" data-b="' . $e($b) . '" data-what="' . $e($what) . '" data-build="' . $e($title) . '"';
    if ($b === 'fail') {
        $acts .= '<button type="button" class="cbg-b"' . $fixA . '>Request a fix</button>';
        if ($f !== null) $more[] = '<button type="button" role="menuitem" data-cbg-open="f:' . $e($f) . '">See the evidence</button>';
        elseif ($fails) $more[] = '<button type="button" role="menuitem" data-cbg-open="c:' . $e($fails[0]) . '">See what ' . $e(strtolower(tv_cbg_name($fails[0]))) . ' found</button>';
    } elseif ($b === 'build') $acts .= '<button type="button" class="cbg-b"' . $fixA . '>Request the build</button>';
    elseif ($b === 'check') {
        $c0 = (string) ($it['no_check'][0] ?? '');
        $acts .= '<button type="button" class="cbg-b"' . $fixA . '>Request the check</button>';
        $more[] = '<button type="button" role="menuitem" data-cbg-open="' . (str_starts_with((string) ($reg[$c0]['status'] ?? ''), 'built') ? 'r:' : 'c:') . $e($c0) . '">Why it isn\'t checked</button>';
    }
    if ($route !== '' && str_starts_with($route, '/')) $more[] = '<a role="menuitem" href="' . $e($route) . '" data-peek-frame>Open the page</a>';
    if ($f !== null) $more[] = '<a role="menuitem" href="#site-health-' . $e($f) . '" data-cb-go="site-health">Its row in Site health</a>';
    $more[] = '<button type="button" role="menuitem" data-cbg-src>Where this comes from</button>';
    $acts .= '<span class="akt-rm cbg-rm"><button type="button" class="akt-rmb" data-cbg-rm aria-haspopup="menu" aria-expanded="false" aria-label="' . $e('More for ' . $name) . '" title="' . $e('More for ' . $name) . '">⋯</button><span class="akt-rml" role="menu" hidden>' . implode('', $more) . '</span></span>';
    $done = '';
    if ($task) {
        if ($task['cleared_at'] !== null) { $done = '<div class="cbg-done"><span><b class="cbg-ok">Cleared:</b> its check passed, ' . tv_cbg_when((string) $task['cleared_at']) . '.</span></div>'; $acts = preg_replace('/<button type="button" class="cbg-b"' . preg_quote($fixA, '/') . '>[^<]*<\/button>/', '', $acts); }
        else { $done = tv_cbg_req($task, 'It clears by itself once its check passes.');
            $acts = str_replace('<button type="button" class="cbg-b"' . $fixA . '>', '<button type="button" class="cbg-b"' . $fixA . ' hidden>', $acts); }
    }
    $links = ''; foreach ($srcLink as [$t, $u]) $links .= '<a class="underline" href="' . $e($u) . '" target="_blank" rel="noopener">' . $e($t) . ' ↗</a> ';
    $ids = implode(', ', array_map(fn ($c) => (string) $c[0], array_filter((array) ($it['checks'] ?? []), 'is_array')));
    return '<li class="cbg-it" data-cbg-b="' . $e($b) . '"' . ($j >= TV_CBG_FIRST ? ' data-cbg-more-row' : '') . '><div class="cbg-ih"><span class="cbg-bk cbg-bk-' . $e($b) . '" title="' . $e(TV_CBG_BUCKETS[$b]) . '"></span>' . $nm
        . '<span class="cbg-dots" role="img" aria-label="' . $e(implode('; ', $lab)) . '">' . $dots . '</span><span class="cbg-dword">' . $e($firstOpen ?? 'Every step met') . '</span>'
        . '<button type="button" class="cbg-ex" aria-expanded="false" aria-label="' . $e('Open ' . $name) . '">▾</button></div>'
        . '<div class="cbg-ib" hidden><p class="cbg-st">' . preg_replace_callback('/@@T(\d+)@@/', fn ($mm) => tv_cb_at((int) $mm[1]), $e($say)) . '</p><div class="cbg-chips">' . $chips . '</div>' . ($acts !== '' ? '<div class="cbg-acts">' . $acts . '</div>' : '') . $done
        . '<details class="cbg-src"><summary>Where this comes from</summary><p>' . ($links !== '' ? $links : $e($src)) . '</p><p class="ak-note">' . $e($src) . ($ids !== '' ? ' · check ids: ' . $e($ids) : '') . '</p></details></div></li>';
}

/** Every reference in an item's source line as a link: a .md file and its line, a release record, a decision. */
function tv_cbg_links(string $src): array
{
    $gh = 'https://github.com/degraffics/traversence/blob/main/'; $out = [];
    if (preg_match_all('~([\w./-]+\.md)(?::(\d+))?~', $src, $m, PREG_SET_ORDER)) foreach ($m as $x) $out[$x[1] . (isset($x[2]) && $x[2] !== '' ? ':' . $x[2] : '')] = $gh . (str_contains($x[1], '/') ? $x[1] : 'handoffs/control-board-plan/step3/sections/' . $x[1]) . (isset($x[2]) && $x[2] !== '' ? '#L' . $x[2] : '');
    if (preg_match_all('~release #(\d+)~', $src, $m)) foreach ($m[1] as $r) $out['release #' . $r] = $gh . 'deliveries/releases';
    if (preg_match_all('~(?:decisions/)?\b(0\d{3})\b~', $src, $m)) foreach ($m[1] as $d) $out['decisions/' . $d] = 'https://github.com/degraffics/traversence/tree/main/decisions';
    return array_map(fn ($k, $v) => [$k, $v], array_keys($out), array_values($out));
}

/** A finding or a check, opened over the panel with Back: its evidence, and its one task (Request a fix, or Request the check). */
function tv_cbg_sub(string $key, array $find, array $reg, string $gid, string $title): string
{
    $e = 'tv_w_e';
    [$k, $id] = explode(':', $key, 2);
    $h = '<div class="cbg-sub" data-cbg-sub="' . $e($key) . '" hidden><button type="button" class="cbg-b cbg-o cbg-sm" data-cbg-back>← Back</button>';
    if ($k === 'f' && isset($find[$id])) {
        $f = $find[$id]; $page = str_starts_with((string) $f['page'], '/') ? (string) $f['page'] : '';
        $h .= '<p class="cb-crumb">Site health</p><h3 class="cb-title">' . $e((string) $f['title']) . '</h3><p>' . $e((string) $f['detail']) . '</p>'
            . ((string) $f['shot_url'] !== '' ? '<a class="cbg-shot" href="' . $e((string) $f['shot_url']) . '" target="_blank" rel="noopener">The screenshot from the audit run ↗</a>' : '<p class="ak-note">The run that found it took no screenshot. Open the page to see it.</p>')
            . '<p class="ak-note">In the way of ' . (int) $f['blocks'] . ' path' . ((int) $f['blocks'] === 1 ? '' : 's') . '. It clears by itself once the next audit run no longer finds it.</p>'
            . '<div class="cbg-acts"><button type="button" class="cbg-b" data-cbg-proxy>Request a fix</button>' . ($page !== '' ? '<a class="cbg-b cbg-o" href="' . $e($page) . '" data-peek-frame>Open the page</a>' : '')
            . ((string) $f['proposal_url'] !== '' ? tv_cbg_ic('link', 'Open its fix: the proposal', (string) $f['proposal_url'], true) : '') . '</div>'
            . '<details class="cbg-src"><summary>Where this comes from</summary><p class="ak-note">Finding ' . $e($id) . ', rule ' . $e((string) $f['rule']) . '</p></details>';
    } elseif (isset($reg[$id]) || $k !== 'f') {
        $c = $reg[$id] ?? ['name' => $id, 'status' => '', 'latest' => '', 'target' => '', 'evidence' => ''];
        $built = str_starts_with((string) ($c['status'] ?? ''), 'built'); $ev = (string) ($c['evidence'] ?? '');
        $h .= '<p class="cb-crumb">The checks</p><h3 class="cb-title">' . $e(tv_cbg_name($id) . ': ' . (string) ($c['name'] ?? '')) . '</h3>'
            . '<p><b>' . ($built ? 'Its last run:' : 'Not built yet.') . '</b> ' . $e($built ? (string) ($c['latest'] ?? '') : '') . '</p>'
            . ((string) ($c['target'] ?? '') !== '' ? '<p class="ak-note">Passes when: ' . $e((string) $c['target']) . '</p>' : '')
            . ($k === 'r' ? '<p><b>It doesn\'t reach this item yet.</b> A build maps the item to what the check runs on: the item\'s Request the check sends it.</p>'
                : (!$built ? '<p><b>What building it needs:</b> ' . $e($ev) . '.</p>' : ''))
            . '<div class="cbg-acts"><button type="button" class="cbg-b" data-cbg-proxy>' . ($k === 'r' || !$built ? 'Request the check' : 'Request a fix') . '</button>' . (str_starts_with($ev, 'https://') ? tv_cbg_ic('link', 'Open ' . tv_cbg_name($id) . '\'s full result', $ev, true) : '') . '</div>'
            . '<details class="cbg-src"><summary>Where this comes from</summary><p class="ak-note">Check id ' . $e($id) . ' in the check register</p></details>';
    }
    return $h . '</div>';
}

/** A gauge's detail, loaded when its panel opens (ControlGauges::detail; the session, 2026-11-22: "lazy-load it in the
 * drawer … so the board's first draw doesn't fetch the detail"): the placeholder the panel fills in. */
function tv_cb_gd_slot(string $kind, string $id): string
{
    return '<div class="cb-gdl" data-cb-gd="' . tv_w_e($kind . ':' . $id) . '"><p class="ak-note">Its items, criteria and setup words load as this opens.</p></div>';
}

/**
 * ?gauge=<kind>:<id> — a gauge's detail as a fragment (#cb-gd) for its panel: a development gauge's items by status, each
 * with its criteria met, its checks, its review and its source (gauge-lifecycle.md §4; board/README.md: "A gauge opens its
 * items by status … each item's source"); a runtime gauge's setup words, what it counts, its needs with their sources in
 * order and its fallback (03 §27: "Services: needs → sources in order (first choice, backup)"), and what isn't mapped.
 */
function tv_cb_gauge_detail(PDO $pdo, string $ref): string
{
    $e = 'tv_w_e';
    [$kind, $id] = array_pad(explode(':', $ref, 2), 2, '');
    $d = class_exists('ControlGauges') ? ControlGauges::detail($pdo, $kind, $id) : null;
    $h = '<div id="cb"><div id="cb-gd">';
    if (!$d) return $h . '<p data-tv-status="info" data-tv-label="No detail:">This gauge\'s detail (its items and setup words) couldn\'t be read from the gauges\' detail file. Its score and stage above are as configured.</p></div></div>';
    if ($kind === 'development') {
        // the drill-down (the gauge panel, approved by Jason 2026-11-23): replaces the items listed by status, each a dead end
        $g = null; foreach (ControlGauges::development($pdo) as $x) if ((string) $x['id'] === $id) $g = $x;
        return $h . tv_cbg_dev($pdo, $g ?? ['id' => $id, 'title' => $id, 'signed' => false], $d) . '</div></div>';
    }
    $sw = (array) ($d['setup_why'] ?? []);
    // its setup's words are on the panel's setup rows (the gauge panel); here, what it counts, its needs and its tasks
    if (!empty($d['records'])) $h .= '<p><b>What it counts:</b> ' . $e((string) $d['records']) . '</p>';
    $needs = array_values(array_filter((array) ($d['needs'] ?? []), 'is_array'));
    if ($needs) {
        $ns = 0; foreach ($needs as $n) $ns += count((array) ($n['sources'] ?? []));
        $h .= '<details class="cbg-nd"><summary><b>What it needs:</b> ' . count($needs) . ' need' . (count($needs) === 1 ? '' : 's') . ', ' . $ns . ' source' . ($ns === 1 ? '' : 's') . ' ▸</summary><ul class="cb-pathl">';
        foreach ($needs as $n) {
            $chips = '';
            foreach (array_values((array) ($n['sources'] ?? [])) as $i => $x) {
                $key = is_array($x) ? (string) ($x[0] ?? '') : ''; $nm = is_array($x) ? (string) ($x[1] ?? $x[2] ?? $x[0] ?? '') : (string) $x;
                $chips .= preg_match('/^[a-z0-9_-]{1,40}$/', $key) ? '<a class="cbg-chip cbg-c-pass" href="/admin/config/register#svc-' . $e($key) . '" data-peek-frame>' . ($i === 0 ? 'first: ' : 'backup: ') . $e($nm) . '</a>' : '<span class="cbg-chip">' . ($i === 0 ? 'first: ' : 'backup: ') . $e($nm) . '</span>';
            }
            $h .= '<li><b>' . $e(trim((string) preg_replace('/\s*\(W[^)]*\)/', '', (string) ($n['label'] ?? $n['need'] ?? '')))) . '</b><div class="cbg-chips">' . ($chips !== '' ? $chips : '<span class="cbg-chip">no outside source</span>') . '</div>' . (!empty($n['fallback']) ? '<small class="ak-note">Else: ' . $e((string) $n['fallback']) . '</small>' : '') . '</li>';
        }
        $h .= '</ul></details>';
    }
    $tasks = (array) ($d['tasks'] ?? []);
    if ($tasks) { $h .= '<h4 class="cb-h4">Its workflows\' tasks</h4><ul class="cb-pathl">'; foreach ($tasks as $w => $ts) $h .= '<li><b>' . $e((string) $w) . '</b>: ' . $e(implode(', ', array_map('strval', (array) $ts))) . '</li>'; $h .= '</ul>'; }
    // a line no need names: its Request the mapping sends it to a build (never "association not mapped" left inert)
    foreach ((array) ($d['unmapped'] ?? []) as $u) { $u = is_array($u) ? implode(' ', array_map('strval', $u)) : (string) $u;
        $h .= '<div class="cbg-it"><div class="cbg-ih"><span class="cbg-bk cbg-bk-build"></span><span class="cbg-nm2">' . $e($u) . '</span></div><p class="ak-note">Not mapped to a need yet: a build maps it.</p>'
            . '<div class="cbg-acts"><button type="button" class="cbg-b" data-cbg="fix" data-g="rt:' . $e($id) . '" data-item="' . $e($u) . '" data-b="build" data-what="mapping it to its need" data-build="' . $e($id) . '">Request the mapping</button></div></div>'; }
    return $h . '</div></div>';
}

/** "Is it delivering?": the three questions, then the runtime gauges by their promise box (03 §4, §27; board/README.md "Board: Is it delivering?"). */
function tv_cb_deliver(PDO $pdo, array $alarms, array $ac, array $names): string
{
    $e = 'tv_w_e';
    $h = '<section class="cb-view" id="cb-deliver">';   // healthy, held, needs me: on the Paused now page, its chips at the top (2026-11-23)
    if (!class_exists('ControlGauges')) return $h . '<p data-tv-status="bad" data-tv-label="Gauges not installed:">The gauges\' reader (app/lib/ControlGauges.php) isn\'t on the site, so no gauge can be drawn. The rest of the board works.</p></section>';
    try { $gs = ControlGauges::runtime($pdo); $src = ControlGauges::source($pdo); }
    catch (Throwable $x) { error_log('board gauges: ' . $x->getMessage()); return $h . '<p data-tv-status="bad" data-tv-label="Couldn\'t read the gauges:">' . $e($x->getMessage()) . ' The rest of the board works; reload the page to try again.</p></section>'; }
    $h .= !empty($src['ok']) ? '<p class="ak-note cb-gsrc">Each gauge\'s setup is read from ' . (function ($f) use ($e) {   // its file's name, linked: a raw address never sets the page's width (Jason's screenshot, 2026-11-23)
        $f = (string) $f; if ($f === '') return 'its configuration';
        $name = basename((string) (parse_url($f, PHP_URL_PATH) ?: $f));
        return preg_match('#^https://#', $f) ? '<a class="underline" href="' . $e($f) . '" target="_blank" rel="noopener">' . $e($name) . ' ↗</a>' : $e($name);
    })($src['from'] ?? '') . (!empty($src['at']) ? ', made ' . tv_cb_at(is_numeric($src['at']) ? (int) $src['at'] : ((int) strtotime((string) $src['at']) ?: null)) : '') . (!empty($src['kept']) ? ' (the last good copy: ' . $e((string) ($src['error'] ?? '')) . ')' : '') . '. Press a card for its snapshot; its three paths lead to what feeds it.</p>'
        : '<p data-tv-status="info" data-tv-label="Configuration not read:">' . $e((string) ($src['from'] ?? 'The gauges\' configuration file')) . ' couldn\'t be read, so each gauge shows what the board itself knows.</p>';
    $boxes = []; foreach ($gs as $g) $boxes[(string) ($g['box'] ?? 'Other')][] = $g;
    $panels = '';
    foreach ($boxes as $box => $list) {
        $cards = ''; foreach ($list as $g) { [$c, $p] = tv_cb_gauge_card($g, $pdo); $cards .= $c; $panels .= $p; }
        $n = count($list); $mon = count(array_filter($list, fn ($g) => ($g['stage'] ?? '') === 'monitor'));
        $h .= '<section class="ak-card tv-bleed cb-box" data-copy="' . $e($box) . '"><h3 class="cb-title cb-boxh"><span>' . $e($box) . ' <small>' . $n . ' gauge' . ($n === 1 ? '' : 's') . ', ' . $mon . ' at Monitoring</small></span></h3><div class="cb-gg">' . $cards . '</div></section>';
    }
    if (!$gs) $h .= '<p data-tv-status="info" data-tv-label="No gauges yet:">No runtime gauge is configured.</p>';
    return $h . '<div class="cb-sps">' . $panels . '</div></section>';
}

/** Development: the 16 development gauges (gauge-lifecycle.md §1–§6; 03 §27), then Site health's list (check-register.md). */
/**
 * The Work queue on Development (Jason, 2026-11-24: "Make a gauge for this and have its queue from the development board. We
 * need to be able to add tasks to the queue and manage them so we don't fall out of score or forget what we identified as
 * needing to be done"). Its gauge (ControlGauges::queueGauge, gauge-lifecycle §7), Add a task, and the list: a queue (CLAUDE.md
 * "A list or a process?"), late first, each row its own cycle: status in place, ✎ Edit with History, ⋯ for the other moves.
 * A task is done when its release is live (GaugeTasks::queue) or, a fix request, when its check passes (GaugeTasks::forGauge).
 */
function tv_cb_wq_places(PDO $pdo, array $tasks): array
{
    $p = [];
    try { foreach (ControlGauges::development($pdo) as $g) $p['dev:' . $g['id']] = ['Development gauges', (string) ($g['title'] ?? $g['id'])]; } catch (Throwable $x) {}
    try { foreach (ControlGauges::runtime($pdo) as $g) $p['rt:' . $g['id']] = ['Runtime gauges', (string) ($g['title'] ?? $g['id'])]; } catch (Throwable $x) {}
    $p['rt:' . ControlGauges::QUEUE['id']] = ['Runtime gauges', ControlGauges::QUEUE['title']];
    foreach ($tasks as $t) { $g = (string) $t['gauge'];
        if (!isset($p[$g]) && str_starts_with($g, 'area:')) $p[$g] = ['Areas', ucfirst(str_replace('-', ' ', substr($g, 5)))]; }
    return $p;
}

function tv_cb_wq_row(array $t, array $places, bool $grouped = false): string
{
    $e = 'tv_w_e'; $id = (int) $t['id']; $st = (string) $t['state'];
    $place = $places[(string) $t['gauge']][1] ?? (string) $t['gauge'];
    $what = (string) ($t['what'] !== '' ? $t['what'] : $t['item']);
    $when = fn ($d, $tz = '') => '<time datetime="' . $e(gmdate('c', (int) strtotime($d . ' UTC'))) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . $e(gmdate('M j, H:i', (int) strtotime($d . ' UTC'))) . ' UTC</time>';
    $pill = ['open' => ['Open', 'n'], 'taken' => ['Taken', 'a'], 'fixing' => ['Being fixed', 'r'], 'late' => ['Late', 'late'], 'done' => ['Done', 'g'], 'withdrawn' => ['Withdrawn', 'w'], 'waiting' => ['Waiting for you', 'late']][$st];
    $owner = (string) $t['owner_now']; $rel = (string) $t['rel_now'];
    $line = [
        'waiting' => 'A Tier 3 decision only the Founder makes: its brief is ready. Open it to answer (it\'s in the daily summary too).',
        'open' => 'Nobody has it yet · added ' . $when($t['at'], (string) $t['tz']) . '. Late if nobody takes it within a day.',
        'late' => 'Nobody has taken it · added ' . $when($t['at'], (string) $t['tz']) . ', over a day ago.',
        'taken' => 'With ' . $e($owner) . ($t['owner_at'] ? ' since ' . $when($t['owner_at']) : '') . '. It\'s being fixed once its release is named.',
        'fixing' => 'Being fixed in <b>' . $e($rel) . '</b>' . ($owner !== '' ? ' by ' . $e($owner) : '') . '. Done by itself when that release is live.',
        'done' => $e((string) ($t['done_how'] ?: 'Done: its check passed')) . ', ' . $when((string) $t['cleared_at']) . '.',
        'withdrawn' => $e((string) $t['done_how']) . ', ' . $when((string) $t['undone_at']) . '.',
    ][$st];
    $closed = in_array($st, ['done', 'withdrawn'], true);
    $ic = fn ($act, $icon, $title, $extra = '') => '<button type="button" class="cbg-ic" data-wq="' . $act . '" title="' . $e($title) . '" aria-label="' . $e($title) . '"' . $extra . '>' . tv_icon($icon, 'cbg-svg') . '</button>';
    $acts = '';
    if (!$closed) {
        $acts .= in_array($st, ['open', 'late'], true) ? '<button type="button" class="cbg-b cbg-sm cbg-o" data-wq="take">Take it</button>'
            : ($st === 'taken' ? '<button type="button" class="cbg-b cbg-sm cbg-o" data-wq="rel">Name its release</button>' : '');
        $acts .= $ic('edit', 'pen', 'Edit task #' . $id . ': ' . $what);
    }
    $acts .= $ic('hist', 'clock', 'History of task #' . $id);
    if (!$closed) {
        $g = fn ($act, $label, $why = '') => '<button type="button" role="menuitem" data-wq="' . $act . '"' . ($why !== '' ? ' disabled title="' . $e($why) . '"' : '') . '>' . $e($label) . ($why !== '' ? '<small>' . $e($why) . '</small>' : '') . '</button>';
        $more = [$g('take', 'Take it', in_array($st, ['taken', 'fixing'], true) ? 'It\'s with ' . $owner . ': Move it instead' : ''),
            $g('move', 'Move to a session…'), $g('rel', $st === 'fixing' ? 'Change its release…' : 'Name its release…'), $g('withdraw', 'Withdraw, with a reason…')];
        // Give it to the Team (the session manager's ruling, 2026-11-25): a person decides which of their tasks agents work
        foreach ((array) ($GLOBALS['tv_wq_team'] ?? []) as [$rname, $why]) $more[] = '<button type="button" role="menuitem" data-wq="team" data-owner="' . $e('system · ' . $rname) . '"' . ($why !== '' || $owner === 'system · ' . $rname ? ' disabled title="' . $e($why !== '' ? $why : 'It\'s with it already') . '"' : '') . '>' . $e('Give it to the Team: ' . $rname) . ($why !== '' ? '<small>' . $e($why) . '</small>' : '') . '</button>';
        $acts .= '<span class="akt-rm cbg-rm"><button type="button" class="akt-rmb" data-cbg-rm aria-haspopup="menu" aria-expanded="false" aria-label="' . $e('More for task #' . $id) . '" title="' . $e('More for task #' . $id) . '">⋯</button><span class="akt-rml" role="menu" hidden>' . implode('', $more) . '</span></span>';
    }
    $src = trim((string) $t['source']);
    // its type and stage (Piece 2, handoffs/piece2-team.md §4.1): the type a quiet tag, the stage with what it needs next
    $tk = (string) ($t['ticket'] ?? 'work') ?: 'work'; $sg = (string) ($t['stage'] ?? '');
    $sgName = GaugeTasks::stagesOf($tk)[$sg][0] ?? '';
    $need = ''; if ($sgName !== '' && !$closed && $st !== 'waiting' && !empty($t['_gate'])) $need = ' · needs ' . $t['_gate'][0];
    $stg = $sgName !== '' && $st !== 'waiting' ? '<span class="wq-stg" title="' . $e('Stage: ' . $sgName) . '">' . $e($sgName) . $e($need) . '</span>' : '';
    $ag = (array) ($t['_agent'] ?? []); $agw = array_filter([!empty($ag['found']) ? 'Found by the audit' : '', !empty($ag['sug']) ? $ag['sug'] . ' suggestion' . ($ag['sug'] === 1 ? '' : 's') : '', !empty($ag['flag']) ? 'Words suggest a Tier 3 boundary: a person checks' : '']);
    return '<li class="wq-it wq-' . $e($st) . '" id="wq-' . $id . '" data-wq-id="' . $id . '" data-wq-st="' . $e($st) . '" data-wq-tk="' . $e($tk) . '" data-wq-stage="' . $e($sg) . '" data-wq-agent="' . (!empty($ag['found']) ? 'found ' : '') . (!empty($ag['sug']) ? 'suggest ' : '') . (!empty($ag['flag']) ? 'flag' : '') . '" data-gauge="' . $e($t['gauge']) . '" data-what="' . $e($what) . '" data-why="' . $e((string) $t['why']) . '" data-source="' . $e($src) . '">'
        . '<div class="wq-h"><span class="wq-tk">' . $e(GaugeTasks::TICKETS[$tk][0]) . '</span><span class="wq-pill wq-p-' . $pill[1] . '">' . $pill[0] . '</span>' . $stg . ((int) ($t['priority'] ?? 0) ? '<span class="wq-pri" title="' . $e(GaugeTasks::PRIORITY[(int) $t['priority']][0] . ': ' . GaugeTasks::PRIORITY[(int) $t['priority']][1]) . '">P' . (int) $t['priority'] . '</span>' : '')
        . '<button type="button" class="wq-nm wq-go" data-wq="page" title="' . $e('Open task #' . $id . ': its scope, priority and steps') . '">' . $e($what) . '</button><span class="wq-acts">' . $acts . '</span></div>'
        . ($agw ? '<p class="wq-ag">' . $e(implode(' · ', $agw)) . '</p>' : '')
        . '<p class="wq-meta">#' . $id . ' · ' . $e($place) . (!$t['task'] ? ' · a fix request from its gauge' : '') . ($t['n_steps'] ?? 0 ? ' · ' . (int) $t['n_done'] . ' of ' . (int) $t['n_steps'] . ' steps confirmed' : '') . (!empty($t['adrs']) ? ' · decisions ' . $e(implode(', ', $t['adrs'])) : '') . '</p>'
        . (trim((string) ($t['goal'] ?? '')) !== '' ? '<p class="wq-goal"><b>Goal:</b> ' . $e(mb_strimwidth(trim((string) $t['goal']), 0, 160, '…')) . '</p>' : '')
        . ($grouped ? '' : '<p class="wq-st">' . $line . '</p>')
        . '<details class="wq-ws"><summary>Why and source</summary>'
        . ((string) $t['why'] !== '' ? '<p class="wq-why"><b>Why:</b> ' . $e((string) $t['why']) . '</p>' : '')
        . ($src !== '' ? '<p class="wq-src"><b>Source:</b> ' . $e($src) . '</p>' : '<p class="wq-src"><b>Source:</b> not given (✎ to add it)</p>') . '</details>'
        . '<div class="wq-slot"></div></li>';
}

/**
 * A ticket's stages (Piece 2, handoffs/piece2-team.md §4.2; Jason's four stages, approved 2026-11-25): the strip with where it
 * is, what moves it on in words, one button for the next move a person makes (greyed with what it needs), ✎ to send it back
 * a stage with a reason. Stages the system moves by their own check say so; nothing here claims a move it didn't check.
 */
function tv_cb_wq_stages(PDO $pdo, array $r, bool $closed): string
{
    $e = 'tv_w_e'; $tk = (string) ($r['ticket'] ?? 'work') ?: 'work'; $sg = (string) ($r['stage'] ?? '');
    $st = GaugeTasks::stagesOf($tk); $keys = array_keys($st);
    if ($sg === '' || !isset($st[$sg])) return '';
    $i = array_search($sg, $keys, true);
    $h = '<ol class="wqp-strip" aria-label="' . $e(GaugeTasks::TICKETS[$tk][0] . ' ticket, stage ' . ($i + 1) . ' of ' . count($keys)) . '">';
    foreach ($keys as $j => $k) $h .= '<li class="' . ($closed || $j < $i ? 'wqp-sd' : ($j === $i ? 'wqp-sn' : '')) . '"' . ($j === $i && !$closed ? ' aria-current="step"' : '') . '>' . $e($st[$k][0]) . '</li>';
    $h .= '</ol>';
    $fl = (string) ($r['flag'] ?? '');
    if ($fl !== '' && !$closed) $h .= '<div class="wqp-flag"><p data-tv-status="info" data-tv-label="A person checks:">Words suggest a Tier 3 boundary (' . $e(GaugeTasks::BOUNDARIES[$fl] ?? $fl) . '), but nothing it touches is in that area. It stays Tier ' . (int) ($r['tier'] ?? 2) . ' until a person checks.</p>'
        . '<button type="button" class="cbg-b cbg-sm" data-wqp="flag" data-v="tier2">It\'s Tier ' . (int) ($r['tier'] ?? 2) . '</button><button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="flag" data-v="tier3">It\'s Tier 3' . ($tk === 'work' ? ': open its Resolution' : '') . '</button></div>';
    if ($closed) return $h;
    $next = GaugeTasks::nextStage($tk, $sg); $miss = GaugeTasks::gate($pdo, $r);
    $byPerson = ($tk === 'work' && in_array($sg, ['planning', 'building'], true)) || ($tk === 'resolution' && $sg === 'brief');
    $words = $next === '' ? 'Its last stage: ' . $st[$sg][1] . '.' : ($byPerson
        ? ($miss ? '<b>To move to ' . $e($st[$next][0]) . ':</b> ' . $e(implode(', ', $miss)) . '.' : '<b>Ready to move to ' . $e($st[$next][0]) . '.</b>')
        : '<b>Next, ' . $e($st[$next][0]) . ':</b> once ' . $e(implode(', ', $miss)) . '.');
    $h .= '<div class="wqp-gate"><p data-tv-status="info" data-tv-label="Next:">' . $words . '</p>';
    if ($byPerson && $next !== '') {
        $label = $tk === 'resolution' ? 'Send to the Founder' : 'Move to ' . $st[$next][0];
        $h .= '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="' . ($tk === 'resolution' ? 'send' : 'move') . '" data-to="' . $e($next) . '"' . ($miss ? ' disabled title="' . $e('It needs ' . implode(', ', $miss) . ' first') . '"' : '') . '>' . $e($label) . '</button>';
    }
    if ($i > 0) $h .= '<button type="button" class="cbg-ic" data-wqp="back" data-stages="' . $e(json_encode(array_map(fn ($k) => [$k, $st[$k][0]], array_slice($keys, 0, $i)))) . '" title="' . $e('Move task #' . $r['id'] . ' back a stage, with a reason') . '" aria-label="' . $e('Move task #' . $r['id'] . ' back a stage, with a reason') . '">' . tv_icon('pen', 'cbg-svg') . '</button>';
    return $h . '</div>';
}

/**
 * A Resolution ticket's Decision Brief (decisions/0179 rule 3; Jason's Q3 A, 2026-11-25): The Decision, Why it needs you,
 * Options A and B with pros, cons and the recommendation, Impact if delayed, and Cost. Each field ✎ Edit with History while
 * it's a draft; Send to the Founder once it's whole. Waiting: Choose A, Choose B or Answer in my words, on the page (never a
 * pop-up; the same answer can come back through the session manager from the daily summary). Decided: who, when, and the
 * precedent ticket that records it.
 */
function tv_cb_wq_brief(PDO $pdo, array $r, bool $closed): string
{
    $e = 'tv_w_e'; $id = (int) $r['id']; $b = GaugeTasks::brief($pdo, $id) ?? []; $sg = (string) $r['stage'];
    $draft = $sg === 'brief' && !$closed;
    $ic = fn ($k, $lb, $v) => $draft ? '<button type="button" class="cbg-ic" data-wqp="edit" data-brief="1" data-f="' . $e($k) . '" data-v="' . $e($v) . '" data-l="' . $e($lb) . '" title="' . $e('Edit ' . strtolower($lb)) . '" aria-label="' . $e('Edit ' . strtolower($lb)) . '">' . tv_icon('pen', 'cbg-svg') . '</button>' : '';
    $val = function (string $k) use ($b, $e) { $v = trim((string) ($b[$k] ?? '')); return $v !== '' ? '<span class="wqp-v">' . nl2br($e($v)) . '</span>' : '<span class="wqp-none">Not set yet</span>'; };
    $f = fn ($k, $short = '') => '<div class="wqp-f" data-wqp-f="' . $k . '"><dt>' . $e($short !== '' ? $short : GaugeTasks::BRIEF[$k][0]) . '</dt><dd>' . $val($k) . $ic($k, GaugeTasks::BRIEF[$k][0], (string) ($b[$k] ?? '')) . '</dd></div>';   // inside an option's box, its fields say only "The option", "Pros", "Cons"
    $h = '<h4 class="wqp-h">Decision Brief <small>' . ($sg === 'brief' ? 'a draft' : ($sg === 'waiting' ? 'with the Founder' : 'decided')) . '</small></h4><dl class="wqp-scope">' . $f('decision') . $f('why_you');
    $bd = (string) ($b['boundary'] ?? '');
    $h .= '<div class="wqp-f" data-wqp-f="boundary"><dt>Its Tier 3 boundary</dt><dd>' . ($bd !== '' ? '<span class="wqp-v">' . $e(GaugeTasks::BOUNDARIES[$bd] ?? $bd) . '</span>' : '<span class="wqp-none">Not set yet · which line it crosses (decisions/0179)</span>')
        . ($draft ? '<button type="button" class="cbg-ic" data-wqp="bchoose" data-f="boundary" data-v="' . $e($bd) . '" data-opts="' . $e(json_encode(array_map(fn ($k, $v) => [$k, $v], array_keys(GaugeTasks::BOUNDARIES), GaugeTasks::BOUNDARIES))) . '" data-l="Its Tier 3 boundary" title="Edit its Tier 3 boundary" aria-label="Edit its Tier 3 boundary">' . tv_icon('pen', 'cbg-svg') . '</button>' : '') . '</dd></div></dl>';
    $rec = (string) ($b['recommend'] ?? '');
    $opt = function (string $x) use ($b, $e, $rec, $f) {
        return '<div' . ($rec === $x ? ' class="wqp-rec"' : '') . '><b>Option ' . strtoupper($x) . '</b>' . ($rec === $x ? ' <span class="wqp-tag">Recommended</span>' : '') . '<dl>' . $f($x . '_text', 'The option') . $f($x . '_pros', 'Pros') . $f($x . '_cons', 'Cons') . '</dl></div>';
    };
    $h .= '<div class="wqp-opt">' . $opt('a') . $opt('b') . '</div>';
    $h .= '<dl class="wqp-scope"><div class="wqp-f" data-wqp-f="recommend"><dt>Recommended</dt><dd>' . ($rec !== '' ? '<span class="wqp-v">Option ' . strtoupper($rec) . '</span>' : '<span class="wqp-none">Not set yet</span>')
        . ($draft ? '<button type="button" class="cbg-ic" data-wqp="bchoose" data-f="recommend" data-v="' . $e($rec) . '" data-opts="' . $e(json_encode([['a', 'Option A'], ['b', 'Option B']])) . '" data-l="Recommended" title="Edit the recommendation" aria-label="Edit the recommendation">' . tv_icon('pen', 'cbg-svg') . '</button>' : '') . '</dd></div>'
        . $f('delay_impact') . $f('cost') . '</dl>';
    if ($sg === 'waiting' && !$closed) {
        require_once __DIR__ . '/Access.php';
        $no = Access::isFounder() ? '' : ' disabled title="Only the Founder answers a Tier 3 decision, signed in with the Founder\'s own login (decisions/0179). It\'s in the daily summary for them."';
        $lab = fn ($x) => 'Choose ' . strtoupper($x) . ($rec === $x ? ' (recommended)' : '');
        $order = $rec === 'b' ? ['b', 'a'] : ['a', 'b'];
        $h .= '<div class="wqp-ans">' . implode('', array_map(fn ($x) => '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="ans" data-a="' . $x . '"' . $no . '>' . $e($lab($x)) . '</button>', $order))
            . '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="answords"' . $no . '>Answer in my words</button></div>';
    }
    if ($sg === 'decided' || ($closed && ($b['answer'] ?? '') !== '')) {
        $a = (string) ($b['answer'] ?? ''); $w = $a === 'o' ? 'in their words: ' . (string) $b['answer_words'] : 'Option ' . strtoupper($a);
        $tz = (string) ($b['answer_tz'] ?? '');
        $at = $b['answered_at'] ? '<time datetime="' . $e(gmdate('c', (int) strtotime($b['answered_at'] . ' UTC'))) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . $e(gmdate('M j, H:i', (int) strtotime($b['answered_at'] . ' UTC'))) . ' UTC</time>' : '';
        $st = $pdo->prepare("SELECT id FROM control_gauge_tasks WHERE parent_id = ? ORDER BY id LIMIT 1"); $st->execute([(int) $r['id']]); $pid = (int) $st->fetchColumn();
        $h .= '<p data-tv-status="ok" data-tv-label="Decided:"><b>Decided:</b> ' . $e($w) . ' · ' . $e((string) $b['answered_by']) . ', ' . $at . '.' . ($pid ? ' Task #' . $pid . ' records it as precedent; this one closes when that\'s done.' : '') . '</p>';
        if (!$closed) $h .= '<div class="wqp-ans"><button type="button" class="cbg-ic" data-wqp="answords" data-edit="1" title="Correct the answer (kept in its History)" aria-label="Correct the answer (kept in its History)">' . tv_icon('pen', 'cbg-svg') . '</button><small>Correct the answer while its precedent isn\'t recorded</small></div>';
    }
    return $h;
}

/**
 * Today's batch (decisions/0179 rule 1; Piece 2, P2-5; piece2-team.md §4.4): the session manager's daily summary as it wrote it
 * (gauges.json's "batch", from its file), each release with what it changes, why, its decision, its checks on staging and its
 * way back, and the tickets it serves; the Tier 3 decisions waiting are counted with it. Approve the batch is the Founder's,
 * recorded as an approval (GaugeTasks::approve, kind batch) that reaches the session manager in the hourly report. Shown only
 * when there's a batch or a decision waiting.
 */
function tv_cb_wq_batch(PDO $pdo, array $active): string
{
    $e = 'tv_w_e';
    $b = []; try { $b = ControlGauges::latestBatch($pdo); } catch (Throwable $x) {}   // today's, or the latest while it waits for its OK
    $waits = array_values(array_filter($active, fn ($t) => $t['state'] === 'waiting')); $wait = count($waits);
    $lines = (array) ($b['lines'] ?? []); $date = (string) ($b['date'] ?? '');
    $head = ($b ? ($lines ? count($lines) . ' release' . (count($lines) === 1 ? '' : 's') . ' waiting for your OK' : 'No releases waiting') : 'No batch from the session manager today') . ($wait ? ' · ' . $wait . ' decision' . ($wait === 1 ? '' : 's') . ' waiting for you' : '');
    $h = '<div class="wq-batch"><p data-tv-status="' . ($lines || $wait ? 'bad' : 'info') . '" data-tv-keep data-tv-label="' . ($date !== '' && $date !== gmdate('Y-m-d') ? 'The latest batch' : 'Today\'s batch') . ($date !== '' ? ' (' . $e(date('M j', (int) strtotime($date))) . ')' : '') . ':">' . $e($head) . '</p>';   // it carries the day's tasks: no × (CLAUDE.md, lines that carry tasks)
    if ($waits) {   // the Tier 3 decisions waiting for the Founder, first (decisions/0179: Tier 3 always comes to them)
        $h .= '<ul class="wq-bl wq-bw">';
        foreach ($waits as $t) $h .= '<li><b>Decision for you</b> · <a href="#wq-' . (int) $t['id'] . '" class="wq-btk">#' . (int) $t['id'] . '</a> ' . $e((string) $t['what']) . '</li>';
        $h .= '</ul>';
    }
    if ($lines) {
        $h .= '<ol class="wq-bl">';
        foreach ($lines as $l) {
            $tk = implode(' ', array_map(fn ($id) => '<a href="#wq-' . (int) $id . '" class="wq-btk">#' . (int) $id . '</a>', (array) $l['tickets']));
            $h .= '<li><b>' . $e($l['release']) . '</b> · ' . $e($l['what']) . ($l['why'] !== '' ? '<br><small><b>Why:</b> ' . $e($l['why']) . ($l['decision'] !== '' ? ' · <b>Decision:</b> ' . $e($l['decision']) : '') . '</small>' : '')
                . ($l['checks'] !== '' || $l['way_back'] !== '' ? '<br><small>' . ($l['checks'] !== '' ? '<b>Checks on staging:</b> ' . $e($l['checks']) : '') . ($l['way_back'] !== '' ? ' · <b>Way back:</b> ' . $e($l['way_back']) : '') . '</small>' : '')
                . ($tk !== '' ? '<br><small>Tickets: ' . $tk . '</small>' : '') . '</li>';
        }
        $h .= '</ol>';
        $ap = GaugeTasks::approvals($pdo)['area:batch-' . $date]['batch'] ?? null;
        if ($ap) $h .= '<p data-tv-status="ok" data-tv-label="Approved:"><b>Approved</b> by ' . $e($ap['who']) . ', <time datetime="' . $e(gmdate('c', (int) strtotime($ap['at'] . ' UTC'))) . '" data-tv-time' . ($ap['tz'] !== '' ? ' data-tz="' . $e($ap['tz']) . '"' : '') . '>' . $e(gmdate('M j, H:i', (int) strtotime($ap['at'] . ' UTC'))) . ' UTC</time>. The session manager has it at the next hourly report; each release then goes live through its own session.</p>';
        else {
            require_once __DIR__ . '/Access.php'; $f = Access::isFounder();
            $h .= '<div class="cbg-acts"><button type="button" class="cbg-b cbg-sm cbg-o" data-wq="batchok" data-date="' . $e($date) . '" data-value="' . $e(implode(', ', array_map(fn ($l) => $l['release'], $lines))) . '"' . ($f ? '' : ' disabled title="Only the Founder approves the batch (decisions/0179 rule 1)"') . '>Approve the batch</button>'
                . '<small>Or reply to the daily summary\'s email.</small></div>';
        }
    }
    return $h . '</div>';
}

/**
 * A ticket's build brief and the Team's runs on it (Piece 2, P2-3; handoffs/piece2-team.md §4.2, §5): each run a line (who,
 * what it did, when) with Undo where it did something, said as undone once undone; a proposal's words with Accept (applied as
 * an Edit, History kept) and Dismiss. Runs happen on staging only, so on live this shows only what Publish carried.
 */
function tv_cb_wq_runs(PDO $pdo, array $r, bool $closed): string
{
    $e = 'tv_w_e'; $h = '';
    if (trim((string) ($r['build_brief'] ?? '')) !== '') $h .= '<h4 class="wqp-h">Build brief <small>with the session manager (the hourly report)</small></h4><div class="wqp-f"><span class="wqp-v">' . nl2br($e((string) $r['build_brief'])) . '</span></div>';
    if (!is_file(__DIR__ . '/../lib/TeamRunner.php')) return $h;
    require_once __DIR__ . '/../lib/TeamRunner.php';
    $runs = TeamRunner::runs($pdo, (int) $r['id']); if (!$runs) return $h;
    $names = []; foreach (TeamRoster::all($pdo) as $x) $names[$x['slug']] = $x['name'];
    $h .= '<h4 class="wqp-h">The Team\'s runs <small>on staging · each one step, logged</small></h4><ol class="wqp-runs">';
    foreach ($runs as $u) {
        $at = '<time datetime="' . $e(gmdate('c', (int) strtotime($u['at'] . ' UTC'))) . '" data-tv-time>' . $e(gmdate('M j, H:i', (int) strtotime($u['at'] . ' UTC'))) . ' UTC</time>';
        $who = 'system · ' . ($names[$u['role']] ?? $u['role']);
        $what = in_array($u['state'], ['done', 'proposed'], true) ? (string) $u['did'] : ucfirst((string) $u['state']) . ': ' . (string) $u['why'];
        $h .= '<li class="wqp-run" data-rid="' . (int) $u['id'] . '"><div class="wqp-rh"><span><b>' . $e($who) . '</b> · ' . $e($what) . ' · ' . $at . '</span>';
        if ($u['state'] === 'done' && $u['undone_at'] === null && !$closed) $h .= '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="runundo" title="' . $e('Undo run #' . $u['id'] . ': ' . $u['undo_how']) . '">Undo</button>';
        $h .= '</div>';
        if ($u['undone_at'] !== null) $h .= '<p class="wqp-none">' . ($u['state'] === 'proposed' ? 'Dismissed' : 'Undone') . ' by ' . $e((string) $u['undone_by']) . '.</p>';
        if ($u['state'] === 'proposed') {
            $p = json_decode((string) $u['proposal'], true) ?: [];
            foreach ($p as $k => $v) $h .= '<p><b>' . $e(GaugeTasks::BRIEF[$k][0] ?? $k) . ':</b> ' . $e((string) $v) . '</p>';
            if ($u['settled_at'] === null && !$closed) $h .= '<div class="cbg-acts"><button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="runaccept">Accept</button><button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="rundismiss">Dismiss</button></div>';
            elseif ($u['undone_at'] === null) $h .= '<p class="wqp-ok">Accepted by ' . $e((string) $u['settled_by']) . '.</p>';
        }
        $h .= '</li>';
    }
    return $h . '</ol>';
}

/**
 * A Work queue task's own page, in the board's side panel (Jason, 2026-11-24: "Each task must open and allow me to scope and
 * set priorities and work that needs to be done"; "I need to be able to scope the tasks from the queue, so scope editor"):
 * its scope (goal, in scope, out of scope, done when), its priority and its steps, each see → do → confirm. Every field is
 * ✎ Edit with its History (CLAUDE.md "Edit or Undo"); nothing here has Undo. Fetched as the panel opens (?wqtask=<id>).
 */
function tv_cb_wq_page(PDO $pdo, int $id): string
{
    $e = 'tv_w_e';
    $h = '<div id="cb"><div id="cb-wqp">';
    require_once __DIR__ . '/../lib/GaugeTasks.php';
    $r = GaugeTasks::task($pdo, $id);
    if (!$r) return $h . '<p data-tv-status="info" data-tv-label="Not found:">Task #' . $id . ' isn\'t in the queue: it may have been a gauge\'s approval, not a task. Close this and pick it again from the list.</p></div></div>';
    $q = null; foreach (GaugeTasks::queue($pdo) as $t) if ((int) $t['id'] === $id) $q = $t;
    $st = $q['state'] ?? ($r['undone_at'] !== null ? 'withdrawn' : 'done');
    $closed = in_array($st, ['done', 'withdrawn'], true);
    $pill = ['open' => ['Open', 'n'], 'taken' => ['Taken', 'a'], 'fixing' => ['Being fixed', 'r'], 'late' => ['Late', 'late'], 'done' => ['Done', 'g'], 'withdrawn' => ['Withdrawn', 'w'], 'waiting' => ['Waiting for you', 'late']][$st];
    $when = fn ($d, $tz = '') => '<time datetime="' . $e(gmdate('c', (int) strtotime($d . ' UTC'))) . '" data-tv-time' . ($tz !== '' ? ' data-tz="' . $e($tz) . '"' : '') . '>' . $e(gmdate('M j, H:i', (int) strtotime($d . ' UTC'))) . ' UTC</time>';
    $what = (string) ($r['what'] !== '' ? $r['what'] : $r['item']);
    $line = ['waiting' => 'A Tier 3 decision only the Founder makes. Answer below, or in reply to the daily summary.', 'open' => 'Nobody has it yet.', 'late' => 'Nobody has taken it, over a day old.', 'taken' => 'With ' . $e((string) ($q['owner_now'] ?? '')) . '.',
        'fixing' => 'Being fixed in <b>' . $e((string) ($q['rel_now'] ?? '')) . '</b>. Done by itself when that release is live.',
        'done' => $e((string) ($r['done_how'] ?: 'Done')) . ($r['cleared_at'] ? ', ' . $when((string) $r['cleared_at']) : '') . '.',
        'withdrawn' => $e((string) $r['done_how']) . ($r['undone_at'] ? ', ' . $when((string) $r['undone_at']) : '') . '.'][$st];
    $ic = fn ($attrs, $icon, $title) => '<button type="button" class="cbg-ic" ' . $attrs . ' title="' . $e($title) . '" aria-label="' . $e($title) . '">' . tv_icon($icon, 'cbg-svg') . '</button>';
    $h .= '<div class="wqp" data-wqp-id="' . $id . '"><p class="cb-crumb">Work queue · #' . $id . '</p><h3 class="cb-title">' . $e($what) . '</h3>'
        . '<p class="wqp-st"><span class="wq-pill wq-p-' . $pill[1] . '">' . $pill[0] . '</span> <span>' . $line . '</span>'
        . (in_array($st, ['open', 'late'], true) ? '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="take">Take it</button>' : ($st === 'taken' ? '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="rel">Name its release</button>' : '')) . '</p>'
        . ($closed ? '<p data-tv-status="info" data-tv-label="Kept as it was:">A task that\'s done or withdrawn keeps its scope and steps as they were; its History has every change.</p>' : '')
        . tv_cb_wq_stages($pdo, $r, $closed);
    if (($r['ticket'] ?? '') === 'resolution') $h .= tv_cb_wq_brief($pdo, $r, $closed);
    // the scope (goal, in, out, done when): each a field with ✎
    $labels = ['goal' => ['Goal', 'What it achieves, in a sentence'], 'in_scope' => ['In scope', 'What this task covers'], 'out_scope' => ['Out of scope', 'What it leaves for another task'], 'done_when' => ['Done when', 'The check that says it\'s finished']];
    $h .= '<h4 class="wqp-h">Scope</h4><dl class="wqp-scope">';
    foreach ($labels as $k => [$lb, $hint]) {
        $v = trim((string) ($r[$k] ?? ''));
        $h .= '<div class="wqp-f" data-wqp-f="' . $k . '"><dt>' . $e($lb) . '</dt><dd>' . ($v !== '' ? '<span class="wqp-v">' . nl2br($e($v)) . '</span>' : '<span class="wqp-none">Not set yet · ' . $e($hint) . '</span>')
            . (!$closed ? $ic('data-wqp="edit" data-f="' . $k . '" data-v="' . $e($v) . '" data-l="' . $e($lb) . '"', 'pen', 'Edit ' . strtolower($lb) . ' of task #' . $id) : '') . '</dd></div>';
    }
    $p = (int) ($r['priority'] ?? 0);
    $h .= '</dl><h4 class="wqp-h">Priority</h4><div class="wqp-f wqp-pr"><span>' . ($p ? '<b>' . $e(GaugeTasks::PRIORITY[$p][0]) . '</b> · ' . $e(GaugeTasks::PRIORITY[$p][1]) : '<span class="wqp-none">Not set yet</span>') . '</span>'
        . (!$closed ? $ic('data-wqp="prio" data-v="' . ($p ?: '') . '"', 'pen', 'Edit the priority of task #' . $id) : '') . '</div>';
    // the decisions it rests on (Jason, 2026-11-24: "need task to show what ADR is attached to it or have the ability to look and
    // assign adrs"): each its number and title, opening on System Configuration → Decisions beside the board (Peek); Attach a
    // decision searches that page's own list; ✎ detaches, with History
    $ad = GaugeTasks::adrs($pdo, $id); $all = $ad ? GaugeTasks::decisions() : [];
    $h .= '<h4 class="wqp-h">Decisions <small>' . ($ad ? count($ad) . ' attached' : 'none attached yet') . '</small>' . ($ad && !$closed ? $ic('data-wqp="adredit"', 'pen', 'Edit the decisions attached to task #' . $id) : '') . '</h4><ul class="wqp-adrs">';
    foreach ($ad as $a) { $d = $all[$a['adr']] ?? null;
        if ($a['adr'] === 'none') { $h .= '<li data-adr="none"><a>No decision applies</a> <small>said by ' . $e((string) $a['who']) . '</small></li>'; continue; }   // the Planning gate's other answer (Piece 2)
        $h .= '<li data-adr="' . $e($a['adr']) . '"><a href="/admin/config/decisions?d=' . $e($a['adr']) . '" data-peek-frame title="' . $e('Open decision ' . $a['adr'] . ' beside the board') . '"><b>' . $e($a['adr']) . '</b> · ' . $e($d['title'] ?? 'not in the decisions list') . '</a>'
            . (!empty($d['status']) ? ' <small>' . $e($d['status']) . '</small>' : '') . '</li>'; }
    $h .= '</ul>';
    if (!$closed && !$ad) $h .= '<button type="button" class="cbg-b cbg-o cbg-sm" data-wqp="noadr">No decision applies</button> ';
    if (!$closed) $h .= '<button type="button" class="cbg-b cbg-o cbg-sm" data-wqp="adradd">' . tv_icon('plus', 'cbg-svg') . ' Attach a decision</button>'
        . '<form class="wq-form wqp-af" data-wqp-aform hidden><button type="button" class="cb-spx wq-x" data-wqp="acancel" aria-label="Close">×</button>'
        . '<label>Find a decision (its number or words in its title)<input name="q" maxlength="80" autocomplete="off" spellcheck="false" placeholder="e.g. 0175 or lifecycle"></label><ul class="wqp-ares" aria-live="polite"></ul></form>';
    // its steps: each see → do → confirm (CLAUDE.md "Every action is a task"); a step's confirmation is a record, corrected by ✎
    $steps = GaugeTasks::steps($pdo, $id); $n = count($steps); $nd = count(array_filter($steps, fn ($s) => $s['done_at'] !== null));
    $h .= '<h4 class="wqp-h">Steps <small>' . ($n ? $nd . ' of ' . $n . ' confirmed' : 'none yet') . '</small></h4><ol class="wqp-steps">';
    foreach ($steps as $s) {
        $done = $s['done_at'] !== null;
        $h .= '<li class="wqp-step' . ($done ? ' wqp-done' : '') . '" data-sid="' . (int) $s['id'] . '" data-see="' . $e($s['see']) . '" data-do="' . $e($s['do_what']) . '" data-confirm="' . $e($s['confirm']) . '" data-done="' . ($done ? '1' : '') . '">'
            . '<div class="wqp-sh"><span class="wqp-n">' . (int) $s['pos'] . '</span><span class="wqp-do">' . $e($s['do_what']) . '</span>'
            . (!$closed ? $ic('data-wqp="sedit"', 'pen', 'Edit step ' . (int) $s['pos']) : '') . '</div>'
            // read in order: what to see, how it's confirmed, then the press (the Design reviewer, 2026-11-24)
            . '<p><b>See:</b> ' . ($s['see'] !== '' ? $e($s['see']) : '<span class="wqp-none">Not set yet</span>') . '</p>'
            . '<p><b>Confirm:</b> ' . ($s['confirm'] !== '' ? $e($s['confirm']) : '<span class="wqp-none">Not set yet</span>') . '</p>'
            . ($done ? '<p class="wqp-ok">Confirmed by ' . $e($s['done_by']) . ', ' . $when((string) $s['done_at'], (string) $s['tz']) . '.</p>'
                : (!$closed ? '<button type="button" class="cbg-b cbg-sm cbg-o" data-wqp="confirm">Confirm it\'s done</button>' : '')) . '<div class="wqp-slot"></div></li>';
    }
    $h .= '</ol>';
    if (!$closed) $h .= '<button type="button" class="cbg-b cbg-o cbg-sm" data-wqp="sadd">' . tv_icon('plus', 'cbg-svg') . ' Add a step</button>'
        . '<form class="wq-form wqp-sform" data-wqp-sform hidden><button type="button" class="cb-spx wq-x" data-wqp="scancel" aria-label="Close">×</button>'
        . '<label>See (what shows it needs doing)<input name="see" maxlength="600" data-spell></label><label>Do<input name="do_what" maxlength="600" required data-spell></label>'
        . '<label>Confirm (how we know it\'s done)<input name="confirm" maxlength="600" data-spell></label>'
        . '<div class="cbg-acts"><button type="submit" class="cbg-b">Add the step</button><button type="button" class="cbg-b cbg-o" data-wqp="scancel">Cancel</button></div></form>';
    $h .= tv_cb_wq_runs($pdo, $r, $closed);
    $h .= '<div class="wqp-foot">' . $ic('data-wqp="hist"', 'clock', 'History of task #' . $id) . '<span>History: every change, with who and when</span></div><div class="wqp-hslot"></div>';
    return $h . '</div></div></div>';
}

function tv_cb_workqueue(PDO $pdo): string
{
    $e = 'tv_w_e';
    try { $q = ControlGauges::queueGauge($pdo); } catch (Throwable $x) {
        return '<section class="ak-card tv-bleed cb-box" id="cb-wq"><h3 class="cb-title">Work queue</h3><p data-tv-status="bad" data-tv-label="Couldn\'t read the queue:">' . $e($x->getMessage()) . ' Reload the page to try again.</p></section>'; }
    $c = $q['counts']; $tasks = $q['tasks']; $places = tv_cb_wq_places($pdo, $tasks);
    $order = ['waiting' => -1, 'late' => 0, 'open' => 1, 'taken' => 2, 'fixing' => 3, 'done' => 4, 'withdrawn' => 5];   // a Tier 3 waiting for the Founder first (Piece 2)
    foreach ($tasks as &$tk) $tk['_gate'] = in_array($tk['state'], ['done', 'withdrawn'], true) ? [] : GaugeTasks::gate($pdo, $tk); unset($tk);
    // what the Team's agents found and suggested (Jason, 2026-11-25: "The que should reflect the work that agents have found and suggested")
    $GLOBALS['tv_wq_team'] = []; $sug = [];
    if (is_file(__DIR__ . '/../lib/TeamRunner.php')) { require_once __DIR__ . '/../lib/TeamRunner.php';
        try { $cl = TeamRunner::ceiling($pdo); $sug = TeamRunner::openSuggestions($pdo);
            foreach (TeamRunner::STEPS as $k => [$slug]) { if ($k === 'audit') continue; $ro = TeamRoster::bySlug($pdo, $slug); if (!$ro || $ro['status'] !== 'active') continue;
                $GLOBALS['tv_wq_team'][$slug] = [(string) $ro['name'], TeamRunner::STEP_AT[$k][$cl] === 'off' ? 'Waits for agents to work past ' . TeamRunner::CEILINGS[$cl][0] : '']; }
        } catch (Throwable $x) {} }
    $types = []; foreach ($tasks as $x) $types[(int) $x['id']] = (string) ($x['ticket'] ?? '');
    foreach ($tasks as &$tk) $tk['_agent'] = ['found' => ($tk['ticket'] ?? '') === 'audit' || ($types[(int) ($tk['parent_id'] ?? 0)] ?? '') === 'audit', 'sug' => $sug[(int) $tk['id']] ?? 0, 'flag' => (string) ($tk['flag'] ?? '') !== '']; unset($tk);
    // late and open by priority first (1, 2, 3, then not set), as they were added within each (Jason, 2026-11-24: "set priorities")
    $pk = fn ($t) => in_array($t['state'], ['late', 'open'], true) ? ((int) ($t['priority'] ?? 0) ?: 4) : 0;
    usort($tasks, fn ($a, $b) => [$order[$a['state']], $pk($a), (int) $a['id']] <=> [$order[$b['state']], $pk($b), (int) $b['id']]);
    // each task's steps, counted for its row
    try { $sc = []; foreach ($pdo->query('SELECT task_id, COUNT(*) n, SUM(done_at IS NOT NULL) d FROM control_gauge_task_steps GROUP BY task_id')->fetchAll(PDO::FETCH_ASSOC) as $x) $sc[(int) $x['task_id']] = [(int) $x['n'], (int) $x['d']];
        foreach ($tasks as &$tk) { $tk['n_steps'] = $sc[(int) $tk['id']][0] ?? 0; $tk['n_done'] = $sc[(int) $tk['id']][1] ?? 0; } unset($tk); } catch (Throwable $x) {}
    // and the decisions attached to each, by number, for its row
    try { $ad = []; foreach ($pdo->query('SELECT task_id, adr FROM control_gauge_task_adrs WHERE removed_at IS NULL ORDER BY adr')->fetchAll(PDO::FETCH_ASSOC) as $x) $ad[(int) $x['task_id']][] = $x['adr'];
        foreach ($tasks as &$tk) $tk['adrs'] = $ad[(int) $tk['id']] ?? []; unset($tk); } catch (Throwable $x) {}
    $active = array_filter($tasks, fn ($t) => !in_array($t['state'], ['done', 'withdrawn'], true));
    $closed = array_filter($tasks, fn ($t) => in_array($t['state'], ['done', 'withdrawn'], true));
    $stage = $q['k']; $score = (float) $q['score'];
    $label = 'Work queue: ' . number_format($score, 0) . ' percent, ' . TV_SG_STAGES[$stage][0];
    $sum = ($c['waiting'] ?? 0 ? $c['waiting'] . ' waiting for you · ' : '') . $c['late'] . ' late · ' . $c['open'] . ' open · ' . $c['taken'] . ' taken · ' . $c['fixing'] . ' being fixed · ' . $c['done'] . ' done this week';
    $target = $q['target_approved']
        ? '<p class="wq-tg">Target: <b>' . $e((string) $q['target']) . '</b> (approved by ' . $e((string) $q['target_approved']['who']) . ').</p>'
        : '<div class="cbg-acts wq-tg"><span>Target: not set · proposed: <b>' . $e(ControlGauges::QUEUE['target_proposed']) . '</b></span>'
          . '<button type="button" class="cbg-b cbg-sm cbg-o" data-cbg="target" data-g="rt:' . $e(ControlGauges::QUEUE['id']) . '" data-value="' . $e(ControlGauges::QUEUE['target_proposed']) . '" data-title="Work queue">Approve the target</button></div>';
    // the filter: one dropdown button (CLAUDE.md "Filters on a list or queue are one dropdown button like Shortcuts")
    $f = [['all', 'Everything open', count($active)], ['waiting', 'Waiting for you', $c['waiting'] ?? 0], ['late', 'Late', $c['late']], ['open', 'Open', $c['open']], ['taken', 'Taken', $c['taken']], ['fixing', 'Being fixed', $c['fixing']]];
    // and by type and stage (Piece 2, handoffs/piece2-team.md §4.1): the same one button, each with its count
    foreach (GaugeTasks::TICKETS as $k => [$nm]) $f[] = ['tk:' . $k, $nm . ' tickets', count(array_filter($active, fn ($t) => ((string) ($t['ticket'] ?? 'work') ?: 'work') === $k))];
    foreach (GaugeTasks::STAGES['work'] as $k => [$nm]) $f[] = ['stage:' . $k, 'Work at ' . $nm, count(array_filter($active, fn ($t) => (string) ($t['stage'] ?? '') === $k))];
    $f[] = ['agent:found', 'Found by agents', count(array_filter($active, fn ($t) => !empty($t['_agent']['found'])))];
    $f[] = ['agent:suggest', 'Has suggestions', count(array_filter($active, fn ($t) => !empty($t['_agent']['sug'])))];
    $f[] = ['agent:flag', 'Words suggest Tier 3', count(array_filter($active, fn ($t) => !empty($t['_agent']['flag'])))];
    $fmenu = implode('', array_map(fn ($x) => '<button type="button" role="menuitemradio" aria-checked="' . ($x[0] === 'all' ? 'true' : 'false') . '" data-wq-f="' . $x[0] . '">' . $e($x[1]) . ' <span>' . (int) $x[2] . '</span></button>', $f));
    // Add a task: where (a gauge or an area), what, why, source (Jason's four fields, 2026-11-24)
    $opts = ''; $grp = '';
    foreach ($places as $k => [$gname, $title]) { if ($gname !== $grp) { $opts .= ($grp !== '' ? '</optgroup>' : '') . '<optgroup label="' . $e($gname) . '">'; $grp = $gname; } $opts .= '<option value="' . $e($k) . '">' . $e($title) . '</option>'; }
    $opts .= ($grp !== '' ? '</optgroup>' : '') . '<option value="area:new">A new area…</option>';
    $form = '<form class="wq-form" data-wq-form hidden><button type="button" class="cb-spx wq-x" data-wq="cancel" aria-label="Close">×</button>'
        . '<label>Gauge or area<select name="gauge" required><option value="">Choose…</option>' . $opts . '</select></label>'
        . '<label class="wq-newarea" hidden>The new area\'s name<input name="area" maxlength="60" data-spell placeholder="e.g. Marketplace"></label>'
        . '<label>Type<select name="ticket">' . implode('', array_map(fn ($k, $v) => '<option value="' . $e($k) . '"' . ($k === 'work' ? ' selected' : '') . '>' . $e($v[0] . ': ' . lcfirst($v[1])) . '</option>', array_keys(GaugeTasks::TICKETS), GaugeTasks::TICKETS)) . '</select></label>'
        . '<label>What needs to be done<input name="what" maxlength="400" required data-spell></label>'
        . '<label>Why (what it fixes, or who asked)<textarea name="why" rows="2" maxlength="400" required data-spell></textarea></label>'
        . '<label>Source<input name="source" maxlength="255" spellcheck="false" placeholder="a decision, a hand-off, or whose words and when"></label>'
        . '<div class="cbg-acts"><button type="submit" class="cbg-b">Add the task</button><button type="button" class="cbg-b cbg-o" data-wq="cancel">Cancel</button></div></form>';
    // the first 10, then Show all (the gauge panel's paging, handoffs/gauge-panel.md); a filter shows all its rows
    $active = array_values($active); $top = []; $grp = [];
    foreach ($active as $t) {
        if ($t['state'] !== 'taken') { $top[] = tv_cb_wq_row($t, $places); continue; }
        $o = (string) $t['owner_now']; if (!isset($grp[$o])) { $grp[$o] = ['rows' => '', 'n' => 0, 'since' => (string) $t['owner_at']]; $top[] = ['grp' => $o]; }
        $grp[$o]['rows'] .= tv_cb_wq_row($t, $places, true); $grp[$o]['n']++;
        if ((string) $t['owner_at'] !== '' && ($grp[$o]['since'] === '' || $t['owner_at'] < $grp[$o]['since'])) $grp[$o]['since'] = (string) $t['owner_at'];
    }
    $rows = '';
    foreach ($top as $i => $r) {
        if (is_array($r)) { $g = $grp[$r['grp']]; $sn = $g['since'] !== '' ? ' · since <time datetime="' . $e(gmdate('c', (int) strtotime($g['since'] . ' UTC'))) . '">' . $e(gmdate('M j', (int) strtotime($g['since'] . ' UTC'))) . '</time>' : '';
            $r = '<li class="wq-grp" data-wq-grp="' . $e($r['grp']) . '"><details><summary><span class="wq-gc" aria-hidden="true">▾</span><span class="wq-gn">' . $e($r['grp']) . '</span> · <b>' . $g['n'] . '</b> taken' . $sn . '</summary>'
                . '<p class="wq-gl">With ' . $e($r['grp']) . '. Each is being fixed once its release is named (Name its release).</p><ul class="wq-list wq-in">' . $g['rows'] . '</ul></details></li>'; }
        $rows .= $i >= 10 ? preg_replace('/^<li /', '<li data-wq-more ', $r) : $r;
    }
    $nTop = count($top);
    $list = $active ? '<ul class="wq-list wq-main">' . $rows . '</ul>' . ($nTop > 10 ? '<button type="button" class="cbg-b cbg-o cbg-sm wq-all" data-wq="all">Show ' . ($nTop - 10) . ' more</button>' : '')
        : '<p data-tv-status="ok" data-tv-label="All clear:">Nothing in the queue. Add a task when something needs doing.</p>';
    $list .= '<p class="wq-none" hidden>Nothing here for this filter.</p>';
    $done = $closed ? '<details class="wq-closed"><summary>Done and withdrawn this week (' . count($closed) . ')</summary><ul class="wq-list">' . implode('', array_map(fn ($t) => tv_cb_wq_row($t, $places), $closed)) . '</ul></details>' : '';
    static $css = false; $style = $css ? '' : '<style>#cb .cb-wq .wq-top{display:flex;gap:.9rem;align-items:center;flex-wrap:wrap;margin:.3rem 0 .6rem}#cb .wq-read{flex:1 1 16rem;min-width:0}#cb .wq-read p{margin:.2rem 0;font-size:13.5px}#cb .wq-why2{color:var(--tv-meta,#6b5d49)}#cb .wq-tg{display:flex;flex-wrap:wrap;gap:.4rem .6rem;align-items:center;font-size:13.5px}#cb .wq-bar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;margin:.4rem 0}#cb .wq-bar .cbg-b[hidden]{display:none}#cb .wq-fm{position:relative}#cb .wq-fm .akt-rml{right:0;left:auto;min-width:13rem}#cb .wq-fm .akt-rml button,#cb .wq-it .akt-rml button{display:flex;justify-content:space-between;gap:.6rem;width:100%;border:0;background:none;text-align:left;padding:.42rem .55rem;border-radius:.4rem;font:500 .9rem var(--tv-font-ui,inherit);color:var(--tv-ink,#2A1D14);cursor:pointer}#cb .wq-fm .akt-rml button[aria-checked=true]{font-weight:700;background:var(--tv-card-b,#EFE7D3)}#cb .wq-it .akt-rml button:disabled{opacity:.55;cursor:not-allowed;flex-direction:column}#cb .wq-it .akt-rml small{font-size:11.5px;color:var(--tv-meta,#6b5d49)}#cb .wq-form{position:relative;display:grid;gap:.45rem;background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.7rem 2.2rem .7rem .7rem;margin:.4rem 0}#cb .wq-form[hidden],#cb .wq-form label[hidden]{display:none}#cb .wq-form label{display:grid;gap:.2rem;font-size:13px;font-weight:600}#cb .wq-form input,#cb .wq-form select,#cb .wq-form textarea{font:400 14px var(--tv-font-ui,inherit);padding:.4rem .5rem;border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.4rem;background:#FFFBF2;color:var(--tv-ink,#2A1D14);max-width:100%;min-width:0}#cb .wq-x{position:absolute;top:.3rem;right:.4rem}#cb .wq-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:minmax(0,1fr);gap:3px}#cb .wq-it{background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-left:4px solid var(--tv-taupe,#7A6A52);border-radius:.5rem;padding:.45rem .6rem}#cb .wq-it[hidden],#cb .wq-main:not(.wq-showall) > [data-wq-more],#cb .wq-grp[hidden],#cb .wq-all[hidden]{display:none}#cb .wq-all{margin-top:.4rem}#cb .wq-go{border:0;background:none;padding:0;text-align:left;font:600 14px var(--tv-font-ui,inherit);color:var(--tv-link,#92400E);text-decoration:underline;cursor:pointer}#cb .wq-pri{font:700 11.5px var(--tv-font-ui,inherit);border-radius:999px;padding:.05rem .45rem;background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#E4DCC5)}#cb .wq-goal{color:var(--tv-ink,#2A1D14)}#cb .wqp-st{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;font-size:13.5px}#cb .wqp-h{margin:.9rem 0 .35rem;font:700 14px var(--tv-font-ui,inherit)}#cb .wqp-h small{font-weight:400;color:var(--tv-meta,#6b5d49)}#cb .wqp-scope{margin:0;display:grid;gap:.4rem}#cb .wqp-f{background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.45rem .6rem}#cb .wqp-f dt{font:600 12.5px var(--tv-font-ui,inherit);color:var(--tv-meta,#6b5d49)}#cb .wqp-f dd{margin:.15rem 0 0;display:flex;gap:.5rem;align-items:flex-start;justify-content:space-between;font-size:13.5px}#cb .wqp-v{overflow-wrap:anywhere}#cb .wqp-none{color:var(--tv-meta,#6b5d49);font-style:italic}#cb .wqp-pr{display:flex;gap:.5rem;align-items:center;justify-content:space-between;font-size:13.5px}#cb .wqp-steps{margin:0;padding:0;list-style:none;display:grid;gap:3px}#cb .wqp-step{background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-left:4px solid var(--tv-taupe,#7A6A52);border-radius:.5rem;padding:.45rem .6rem;font-size:13px}#cb .wqp-step.wqp-done{border-left-color:#3f6b55}#cb .wqp-step p{margin:.15rem 0}#cb .wqp-sh{display:flex;gap:.45rem;align-items:center;flex-wrap:wrap}#cb .wqp-n{font-weight:700;min-width:1.2rem}#cb .wqp-do{flex:1 1 12rem;font-weight:600}#cb .wqp-ok{color:#3f6b55}#cb .wqp-foot{display:flex;gap:.5rem;align-items:center;margin-top:.9rem;font-size:12.5px;color:var(--tv-meta,#6b5d49)}#cb .wqp-body .cbg-b[data-wqp=sadd]{margin-top:.4rem}#cb .wqp-ef{margin-top:.35rem}#cb .wqp-h .cbg-ic{margin-left:.4rem;vertical-align:middle}#cb .wqp-adrs{list-style:none;margin:0 0 .4rem;padding:0;display:grid;gap:3px}#cb .wqp-adrs li{background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.4rem .6rem;font-size:13.5px}#cb .wqp-adrs a{color:var(--tv-link,#92400E)}#cb .wqp-adrs small{color:var(--tv-meta,#6b5d49)}#cb .wqp-ares{list-style:none;margin:0;padding:0;display:grid;gap:2px;max-height:16rem;overflow:auto}#cb .wqp-ares button{width:100%;text-align:left;border:1px solid var(--tv-card-edge,#E3DBC6);background:#FFFBF2;border-radius:.4rem;padding:.35rem .5rem;font:13px var(--tv-font-ui,inherit);color:var(--tv-ink,#2A1D14);cursor:pointer}#cb .wqp-ares button:disabled{opacity:.6;cursor:default}#cb .wqp-ch{border:0;margin:0;padding:0;display:grid;gap:.3rem}#cb .wqp-ch legend{font:600 13px var(--tv-font-ui,inherit);margin-bottom:.2rem}#cb .wqp-ch label{display:flex;gap:.4rem;align-items:baseline;font-weight:400;flex-wrap:wrap}#cb .wqp-ch small{color:var(--tv-meta,#6b5d49)}#cb .wqp-ck{display:flex!important;gap:.4rem;align-items:center;font-weight:400!important}#cb .wqp-ef textarea{width:100%}#cb .wq-grp{border:1px solid var(--tv-card-edge,#E3DBC6);border-left:4px solid #8a5a00;border-radius:.5rem;background:#FFFBF2}#cb .wq-grp summary{cursor:pointer;padding:.5rem .6rem;font-size:14px}#cb .wq-gn{font-weight:700}#cb .wq-grp summary{list-style:none;display:flex;flex-wrap:wrap;gap:.25rem;align-items:baseline}#cb .wq-grp summary::-webkit-details-marker{display:none}#cb .wq-gc{display:inline-block;width:1rem;color:var(--tv-meta,#6b5d49);transform:rotate(-90deg);transition:transform 150ms ease-out}#cb .wq-grp details[open] .wq-gc{transform:none}#cb .wq-gl{margin:0 .6rem .4rem;font-size:13px;color:var(--tv-meta,#6b5d49)}#cb .wq-in{padding:0 .3rem .3rem}#cb .wq-it.wq-new{box-shadow:0 0 0 2px var(--tv-btn,#92400E)}#cb details.wq-ws{margin:.1rem 0 0}#cb details.wq-ws summary{cursor:pointer;font-size:12.5px;color:var(--tv-link,#92400E)}#cb .wq-late{border-left-color:var(--cb-hold)}#cb .wq-fixing{border-left-color:var(--tv-river,#3B5A6B)}#cb .wq-done{border-left-color:#3f6b55}#cb .wq-withdrawn{opacity:.8}#cb .wq-it.cbg-flash{box-shadow:0 0 0 2px var(--tv-btn,#92400E)}#cb .wq-h{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}#cb .wq-nm{flex:1 1 12rem;min-width:0;font-weight:600;font-size:14px}#cb .wq-acts{display:flex;gap:.3rem;align-items:center;margin-left:auto}#cb .wq-pill{border-radius:999px;padding:.05rem .5rem;font:600 11.5px var(--tv-font-ui,inherit);border:1px solid currentColor;white-space:nowrap}#cb .wq-p-late{color:var(--cb-hold)}#cb .wq-p-n{color:var(--tv-taupe,#7A6A52)}#cb .wq-p-a{color:#8a5a00}#cb .wq-p-r{color:var(--tv-river,#3B5A6B)}#cb .wq-p-g{color:#3f6b55}#cb .wq-p-w{color:var(--tv-meta,#6b5d49)}#cb .wq-it p{margin:.15rem 0;font-size:13px}#cb .wq-meta{color:var(--tv-meta,#6b5d49);font-size:12.5px}#cb .wq-why,#cb .wq-src{color:var(--tv-ink,#2A1D14)}#cb .wq-src{overflow-wrap:anywhere}#cb .wq-hist{border-top:1px dashed var(--tv-card-edge,#E3DBC6);margin-top:.35rem;padding-top:.3rem;font-size:12.5px}#cb .wq-hist ol{margin:0;padding-left:1.1rem}#cb .wq-hist span{color:var(--tv-meta,#6b5d49)}#cb .wq-hh{font-weight:700}#cb details.wq-closed{margin-top:.6rem}#cb details.wq-closed summary{cursor:pointer;font-size:13.5px}#cb .wq-tk{font:600 11.5px var(--tv-font-ui,inherit);border-radius:999px;padding:.05rem .5rem;background:var(--tv-tag,#EDE6D5);border:1px solid var(--tv-card-edge,#E3DBC6);color:var(--tv-ink,#2A1D14)}#cb .wq-stg{font-size:12.5px;color:var(--tv-meta,#6b5d49)}#cb .wq-bar2{display:flex;gap:.4rem;align-items:center}#cb .wq-waiting{border-left-color:var(--cb-hold)}#cb .wqp-strip{display:flex;margin:.5rem 0 .3rem;padding:0;list-style:none}#cb .wqp-strip li{flex:1;text-align:center;font-size:12px;padding:.3rem .2rem;border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#F6F1E4);min-width:0;overflow-wrap:anywhere}#cb .wqp-strip li:first-child{border-radius:999px 0 0 999px}#cb .wqp-strip li:last-child{border-radius:0 999px 999px 0}#cb .wqp-strip li+li{border-left:0}#cb .wqp-strip .wqp-sd{background:color-mix(in srgb,var(--tv-sage,#92BEA5) 35%,var(--tv-card-a,#F6F1E4))}#cb .wqp-strip .wqp-sn{background:var(--tv-umber,#3E2B1F);color:var(--tv-on-umber,#EFE3C8);font-weight:600}#cb .wqp-gate{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin:.3rem 0}#cb .wqp-gate p{flex:1 1 100%;margin:0}.wqp-flag{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin:.3rem 0}.wqp-flag p{flex:1 1 100%;margin:0}#cb .wqp-opt{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}#cb .wqp-opt>div{border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.5rem .6rem;background:var(--tv-card-a,#F6F1E4)}#cb .wqp-opt .wqp-rec{border:2px solid var(--tv-btn,#92400E)}#cb .wqp-opt dl{margin:.2rem 0 0}#cb .wqp-ans{display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0}#cb .wq-ag{color:var(--tv-hold,#8f5600);font-size:12.5px;font-weight:600}#cb .wq-batch{margin:.3rem 0 .5rem}#cb .wq-bl{margin:.3rem 0;padding-left:1.2rem;font-size:13px}#cb .wq-bl li{margin:.25rem 0}#cb .wq-bl small{color:var(--tv-meta,#6b5d49)}#cb .wq-batch .cbg-acts{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}#cb .wq-batch small{font-size:12.5px}#cb .wqp-runs{list-style:none;margin:0;padding:0;display:grid;gap:3px}#cb .wqp-run{background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.45rem .6rem;font-size:13px}#cb .wqp-run p{margin:.15rem 0}#cb .wqp-rh{display:flex;gap:.5rem;align-items:flex-start;justify-content:space-between}#cb .wqp-tag{font:600 11.5px var(--tv-font-ui,inherit);border-radius:999px;padding:.05rem .45rem;background:var(--tv-tag,#EDE6D5);border:1px solid var(--tv-card-edge,#E3DBC6)}@media (max-width:767px){#cb .wq-acts{margin-left:0;flex-basis:100%;justify-content:flex-start}#cb .wq-fm .akt-rml{right:auto;left:0}#cb .wqp-opt{grid-template-columns:1fr}#cb .wqp-strip li{font-size:11px}}</style>'; $css = true;
    return $style . '<section class="ak-card tv-bleed cb-box cb-wq" id="cb-wq" data-copy="Work queue">'
        . '<h3 class="cb-title">Work queue <small>what we found needs doing, so nothing is forgotten</small></h3>'
        . '<div class="wq-top">' . tv_score_gauge($score, $label, $stage) . '<div class="wq-read">' . tv_score_stage_pill($stage)
        . '<p class="wq-r1"><b>' . $e($q['reading_text']) . '</b> · ' . $e($sum) . '</p>' . ($q['target_approved'] ? '<p class="wq-why2">' . $e(ucfirst($q['stage']) . ': ' . $q['why']) . '.</p>' : '') . $target . '</div></div>'
        . tv_cb_wq_batch($pdo, $active)
        . '<div class="wq-bar"><button type="button" class="cbg-b" data-wq="add">' . tv_icon('plus', 'cbg-svg') . ' Add a task</button>'
        . '<span class="wq-bar2"><a class="cbg-ic wq-team" href="/admin/config/team" data-peek-frame title="Open the Team: the roles that work this queue" aria-label="Open the Team: the roles that work this queue">' . tv_icon('users', 'cbg-svg') . '</a>'
        . '<span class="akt-rm wq-fm"><button type="button" class="ak-b cb-xs wq-fb" data-wq-fb aria-haspopup="menu" aria-expanded="false">' . tv_icon('filter', 'cbg-svg') . ' <span data-wq-fl>Everything open</span> <span data-wq-fn>' . count($active) . '</span> ▾</button><span class="akt-rml" role="menu" hidden>' . $fmenu . '</span></span></span></div>'
        . $form . $list . $done . '</section>'
        // a task's page: its panel beside the section, so reading the section again leaves an open page in place
        . '<aside class="cb-sp wqp-sp" id="cb-sp-wq" role="dialog" aria-label="A Work queue task" hidden><button type="button" class="cb-spx" data-cb-spx aria-label="Close">×</button><div class="wqp-body"><p class="ak-note">Its scope, priority and steps load as this opens.</p></div></aside>';
}

function tv_cb_development(PDO $pdo, callable $btn): string
{
    $e = 'tv_w_e';
    $h = '<section class="cb-view" id="cb-dev">';
    $panels = '';
    if (!class_exists('ControlGauges')) $h .= '<p data-tv-status="bad" data-tv-label="Gauges not installed:">The gauges\' reader (app/lib/ControlGauges.php) isn\'t on the site.</p>';
    else {
        try { $ds = ControlGauges::development($pdo); } catch (Throwable $x) { $ds = null; $h .= '<p data-tv-status="bad" data-tv-label="Couldn\'t read the gauges:">' . $e($x->getMessage()) . ' Reload the page to try again.</p>'; }
        if (is_array($ds)) {
            $cards = []; $side = [];
            foreach ($ds as $g) {
                $id = (string) preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($g['id'] ?? 'd')); $pid = 'cb-sp-d-' . $id; $title = (string) ($g['title'] ?? $id);
                $signed = !empty($g['signed']);
                // Planning at 0 until Jason signs off the configuration ("initially 0", Jason, 2026-11-22; gauge-lifecycle.md §3, the rule's first step)
                $stage = $signed ? tv_cb_gstage($g) : 'planning';
                $score = $signed ? (float) ($g['score'] ?? 0) : 0.0;
                $items = (int) ($g['items'] ?? 0); $crit = (int) ($g['criteria'] ?? 0); $met = (int) ($g['met'] ?? 0);
                $line = trim((string) ($g['text'] ?? '')) !== '' ? (string) $g['text'] : (!$signed ? ($items || $crit ? 'configuration drafted: ' . $items . ' item' . ($items === 1 ? '' : 's') . ', ' . $crit . ' criteri' . ($crit === 1 ? 'on' : 'a') . ', awaiting sign-off' : 'no configuration yet')
                    : $met . ' of ' . $crit . ' criteri' . ($crit === 1 ? 'on' : 'a') . ' met, over ' . $items . ' item' . ($items === 1 ? '' : 's'));
                $prev = $g['preview'] ?? null;
                $nc = (array) ($g['no_check'] ?? []);
                $sd = trim((string) ($g['side'] ?? '')); $side[$sd] = true;
                $glabel = $title . ': ' . number_format($score, 0) . ' percent, ' . TV_SG_STAGES[$stage][0];
                $cards[$sd] = ($cards[$sd] ?? '') . '<article class="cb-gc cb-dc" id="cb-d-' . $e($id) . '" data-copy="' . $e($title) . '" data-stage="' . $e($stage) . '">'
                    . '<button type="button" class="cb-gopen" data-cb-sp="' . $e($pid . '-snap') . '" aria-label="' . $e('Open ' . $title . ': ' . TV_SG_STAGES[$stage][0] . ', its items') . '">'
                    . '<span class="cb-gh">' . $e($title) . '</span>' . tv_score_gauge($score, $glabel, $stage) . '<span class="cb-gst">' . tv_score_stage_pill($stage) . '</span>'
                    . '<span class="cb-dl1">' . $e($line) . '</span></button>'
                    . (!$signed && $prev !== null ? '<details class="cb-prev"><summary>Preview with the draft configuration</summary><p>' . $e(number_format((float) $prev, 0)) . '% if the draft were signed off' . (!empty($g['preview_stage']) ? ' (' . $e((string) $g['preview_stage']) . ')' : '') . ', with the criteria from the page audit. A preview only: the gauge stays at 0 until Jason signs it off.</p></details>' : '')
                    . ($nc ? '<span class="cb-nochk">no check yet: ' . $e(implode(', ', $nc)) . '</span>' : '')
                    . '<div class="cb-gb">' . tv_cb_drill_btns($pid) . '</div><div class="cb-gcopy" data-copy-at></div></article>';
                // its panel: its stage and why, its items by status (loaded as it opens), its three paths (gauge-lifecycle.md §3, §4)
                $panels .= '<aside class="cb-sp" id="' . $e($pid . '-snap') . '" role="dialog" aria-label="' . $e($title) . '" hidden data-copy="' . $e($title) . '">'
                    . '<button type="button" class="cb-spx" data-cb-spx aria-label="Close">×</button><p class="cb-crumb">Development' . ($sd !== '' ? ' · ' . $e($sd) : '') . '</p><h3 class="cb-title">' . $e($title) . '</h3>'
                    . '<div class="cb-gbig">' . tv_score_gauge($score, $glabel, $stage, ['big' => true]) . tv_score_stage_pill($stage) . '</div>'
                    . '<p class="cbg-top">' . ($signed ? $e($line) : ($prev !== null ? $e(number_format((float) $prev, 0)) . '% once you approve the draft' : 'no configuration yet')) . '<br>What moves it: <b>' . $items . ' thing' . ($items === 1 ? '' : 's') . '</b></p>'
                    . tv_cb_gd_slot('development', (string) ($g['id'] ?? $id)) . '<div class="cb-acts">' . tv_cb_drill_btns($pid) . '</div></aside>'
                    . tv_cb_path_panels($pid, $title, (array) ($g['paths'] ?? []), $pid . '-snap');
            }
            $h .= tv_cb_workqueue($pdo);   // the Work queue first: what needs doing (Jason, 2026-11-24)
            $h .= '<section class="ak-card tv-bleed cb-box" data-copy="Development: the 16 gauges"><h3 class="cb-title">Development <small>' . count($ds) . ' gauges; each starts at 0 in Planning, its configuration signed off by Jason</small></h3>'
                . '<p class="ak-note">The score is the share of the gauge\'s configured criteria that are met; the word under it is the stage of the work. A criterion with no check yet counts as unmet. Site health\'s findings are <a class="underline" href="#site-health" data-cb-go="site-health">below</a>: the audit\'s view, which feeds each gauge\'s "checked".</p>'
                . implode('', array_map(fn ($sd) => ($sd !== '' && count($side) > 1 ? '<h4 class="cb-h4">' . $e($sd) . '</h4>' : '') . '<div class="cb-gg">' . $cards[$sd] . '</div>', array_keys($cards))) . '</section>';   // Front end (6) and Back end (10), as the inventory has them (board/README.md "Development gauges")
        }
    }
    return $h . tv_cb_site_health($pdo) . '<div class="cb-sps">' . $panels . '</div></section>';
}

/**
 * Site health's list on the board (check-register.md, "Site health: one notification path", Jason 2026-11-22: "its list
 * stays as the audit's view, reached from a gauge's drill-down"): moved from Pulse's Site health case (SiteHealth::steps).
 * Each open finding, most paths blocked first (SiteHealth::open), with the case's actions kept: Mark fixed, Set aside (with
 * why), Check again (reads the newest), Run the checks now; through Pulse's own manage actions for sitehealth:open.
 */
function tv_cb_site_health(PDO $pdo): string
{
    $e = 'tv_w_e';
    $h = '<section class="ak-card tv-bleed cb-panel" id="site-health" data-copy="Site health"><h3 class="cb-title">Site health <small>the site audit\'s open findings, most paths blocked first</small></h3>';
    $b = fn (string $what, string $label, bool $ok, string $why, array $at = []) => '<button type="button" class="ak-b cb-xs" data-cb-sh="' . $e($what) . '"' . ($ok ? '' : ' disabled title="' . $e($why) . '"')
        . implode('', array_map(fn ($k, $v) => ' ' . $k . '="' . $e($v) . '"', array_keys($at), $at)) . '>' . $e($label) . '</button>';
    try {
        require_once __DIR__ . '/../lib/SiteHealth.php';
        if (!SiteHealth::on($pdo)) return $h . '<p data-tv-status="info" data-tv-label="Switched off:">Site health is off (its switch, site_health, on <a class="underline" href="/admin/config/switches" data-peek-frame>Switches</a>), so the audit\'s findings aren\'t read here.</p></section>';
        $d = SiteHealth::load($pdo);
    } catch (Throwable $x) { $d = ['ok' => false, 'findings' => [], 'error' => $x->getMessage(), 'at' => '', 'run_url' => '']; }
    $canMark = true; $whyMark = '';
    try { require_once __DIR__ . '/Access.php'; [$canMark, $whyMark] = Access::can('insights/site_health.decide', ['min' => 'staff', 'depts' => ['eng', 'ops']]); $whyMark = ($whyMark ?: 'Your role can\'t record site health findings') . '.'; } catch (Throwable $x) {}
    $again = $b('sh_refresh', 'Check again', true, '');
    if (!$d['ok'] && !$d['findings']) return $h . '<p data-tv-status="bad" data-tv-label="Couldn\'t read the findings:">' . $e((string) $d['error']) . ' The list fills in once they can be read.</p><div class="cb-acts">' . $again . '</div></section>';
    $open = SiteHealth::open($pdo, $d);
    $h .= '<p class="ak-note">From the site audit' . ($d['at'] !== '' ? ' of ' . tv_cb_at((int) strtotime((string) $d['at'])) : '') . ($d['error'] !== '' ? '. Newer findings couldn\'t be read: ' . $e((string) $d['error']) : '') . '. Each finding was sent once to Pulse, with no push; a check that stopped running is a warning in the alarms.</p>';
    if (!$open) $h .= '<p data-tv-status="ok" data-tv-label="All clear:">No open finding.</p>';
    else {
        $h .= '<ul class="cb-shl">';
        foreach ($open as $f) {
            $page = str_starts_with((string) $f['page'], '/') ? (string) $f['page'] : '';
            $links = ($page !== '' ? '<a class="underline" href="' . $e($page) . '" data-peek-frame>the page</a>' : '') . ($f['shot_url'] !== '' ? ' <a class="underline" href="' . $e($f['shot_url']) . '" target="_blank" rel="noopener">the screenshot ↗</a>' : '')
                . ($f['proposal_url'] !== '' ? ' <a class="underline" href="' . $e($f['proposal_url']) . '" target="_blank" rel="noopener">proposal ' . $e((string) $f['proposal']) . ' ↗</a>' : '');
            $h .= '<li id="site-health-' . $e((string) $f['id']) . '"><div><span class="cb-pill cb-pill-' . ($f['severity'] === 'update' ? 'new' : ($f['severity'] === 'critical' ? 'bad' : 'warn')) . '">' . $e(ucfirst((string) $f['severity'])) . '</span> '
                . '<b>' . $e(($f['rule'] !== '' ? $f['rule'] . ': ' : '') . $f['title']) . '</b></div>'
                . '<div class="cb-shd">' . $e((string) $f['detail']) . '</div>'
                . '<div><small>' . ($f['blocks'] ? 'In the way of ' . (int) $f['blocks'] . ' path' . ((int) $f['blocks'] === 1 ? '' : 's') . ($f['paths'] ? ' (' . $e(implode(', ', array_slice((array) $f['paths'], 0, 4))) . ')' : '') . ' · ' : 'No path blocked · ')
                . ($f['first_seen'] !== '' ? 'first seen ' . tv_cb_at((int) strtotime((string) $f['first_seen'])) : 'first seen: not said') . ($f['who'] ? ' · seen as ' . $e(implode(', ', (array) $f['who'])) : '') . '</small></div>'
                . ($links !== '' ? '<div><small>' . $links . '</small></div>' : '')
                . '<div class="cb-acts">' . $b('sh_mark:' . $f['id'], 'Mark fixed', (bool) $canMark, (string) $whyMark, ['data-state' => 'fixed', 'data-confirm' => 'Mark it fixed? It stays off the list unless the next audit run still finds it.', 'data-yes' => 'Mark fixed'])
                . $b('sh_mark:' . $f['id'], 'Set aside', (bool) $canMark, (string) $whyMark, ['data-state' => 'aside', 'data-ask' => 'Why is it fine as it is? (The next review reads it.)']) . '</div></li>';
        }
        $h .= '</ul>';
    }
    return $h . '<div class="cb-acts">' . $again . $b('sh_run:site-checks', 'Run the checks now', true, '', ['data-confirm' => 'Ask GitHub to run the browser checks and the paths now? About 15 minutes.', 'data-yes' => 'Run them'])
        . '<a class="ak-b cb-xs" href="' . $e(SiteHealth::PATHS_PAGE) . '" target="_blank" rel="noopener">Every path ↗</a></div></section>';
}

/** Incidents: every Incident Report (02 P12; 03 §7), newest first, each opening its page. */
function tv_cb_incidents(PDO $pdo, array $names): string
{
    $e = 'tv_w_e';
    $list = ControlIncidents::list($pdo, 100);
    $h = '<section class="cb-view" id="cb-incidents"><section class="ak-card tv-bleed cb-panel" id="cb-irs" data-copy="Incident Reports"><h3 class="cb-title">Incident Reports <small>' . count($list) . ' kept; every trip opens one or joins an open one; kept ' . ControlIncidents::KEEP_DAYS . ' days</small></h3>';
    if (!$list) return $h . '<p data-tv-status="ok" data-tv-label="None yet:">No trip has opened an Incident Report.</p></section></section>';
    $h .= '<table class="cb-tt"><thead><tr><th>Report</th><th>Opened</th><th>State</th><th>Root cause</th></tr></thead><tbody>';
    foreach ($list as $r) {
        $num = ControlIncidents::number((int) $r['id']);
        $h .= '<tr id="cb-ir-' . (int) $r['id'] . '"><td data-th="Report"><a class="underline" href="/admin/config/control?ir=' . (int) $r['id'] . '" data-peek-frame>' . $e($num) . '</a> ' . $e((string) $r['title']) . '</td>'
            . '<td data-th="Opened">' . tv_cb_at((int) strtotime($r['opened_at'] . ' UTC')) . '</td>'
            . '<td data-th="State">' . ($r['closed_at'] ? 'Closed ' . tv_cb_at((int) strtotime($r['closed_at'] . ' UTC')) : '<span class="cb-pill cb-pill-bad">Open</span>') . '</td>'
            . '<td data-th="Root cause">' . ($r['root_cause_state'] === 'confirmed' ? 'Confirmed' : 'Unconfirmed') . '</td></tr>';
    }
    return $h . '</tbody></table></section></section>';
}

/**
 * An Incident Report's page (?ir=<n>; 03 §7, mockup e for IR-0001; 02 P12): its title ("IR-0001 · Correlated · 2 parts: … ·
 * <state>"), its root cause ("Unconfirmed" in its own row until a person writes it), its members with their warning and trip
 * ids and real times, its timeline, its radius and its evidence.
 */
function tv_cb_ir_page(PDO $pdo, int $id, string $csrf): string
{
    $e = 'tv_w_e';
    $r = ControlIncidents::get($pdo, $id);
    $h = '<div id="cb" class="cb-report cb-irpage" data-csrf="' . $e($csrf) . '"><p class="cb-rback"><a href="/admin/config/control?tab=incidents" class="underline">← Incident Reports</a></p>';
    if (!$r) return $h . '<p data-tv-status="info" data-tv-label="Not found:">There\'s no ' . $e(ControlIncidents::number($id)) . '. It may be older than ' . ControlIncidents::KEEP_DAYS . ' days. <a class="underline" href="/admin/config/control?tab=incidents">Every Incident Report</a></p></div>' . tv_cb_report_css() . tv_cb_view_assets();
    $names = []; try { foreach (ControlRegister::processes($pdo) as $pid => $p) $names[$pid] = (string) $p[1]; } catch (Throwable $x) {}
    $members = (array) $r['members'];
    $parts = []; foreach ($members as $m) { $st = $pdo->prepare('SELECT proc FROM control_alarms WHERE id = ?'); $st->execute([(int) $m['id']]); $pr = (string) $st->fetchColumn(); $parts[] = $names[$pr] ?? ($pr !== '' ? $pr : (string) $m['akey']); }
    $parts = array_values(array_unique($parts));
    $holding = array_filter($members, fn ($m) => $m['cleared_at'] === null && !empty($m['tripped_at']));
    $state = $r['closed_at'] ? 'Closed' : ($holding ? 'Paused by the system' : 'Open: the system is still trying');
    $title = ControlIncidents::number((int) $r['id']) . (count($members) > 1 ? ' · Correlated' : '') . ' · ' . count($parts) . ' part' . (count($parts) === 1 ? '' : 's') . ': ' . implode(', ', $parts) . ' · ' . $state;
    $h .= '<h2 class="ak-h">' . $e($title) . '</h2><p class="ak-note">Opened ' . tv_cb_at((int) strtotime($r['opened_at'] . ' UTC')) . ($r['closed_at'] ? ' · closed ' . tv_cb_at((int) strtotime($r['closed_at'] . ' UTC')) : ' · open') . ' · ' . $e((string) $r['title']) . '</p>';
    $confirmed = $r['root_cause_state'] === 'confirmed' && trim((string) $r['root_cause']) !== '';
    $h .= '<section class="ak-card tv-bleed cb-rsec" data-copy="Root cause"><h3 class="cb-title">Root cause</h3>'
        // its line stands while it's true (data-tv-keep: no × on a fact), in .cb-rc so the button's answer redraws it in place
        . '<div class="cb-rc">' . ($confirmed ? '<p data-tv-status="ok" data-tv-label="Confirmed:" data-tv-keep>' . $e((string) $r['root_cause']) . ($r['root_cause_by'] ? ' <small>(' . $e((string) $r['root_cause_by']) . ')</small>' : '') . '</p>'
            : '<p data-tv-status="info" data-tv-label="Unconfirmed:" data-tv-keep>No one has written its root cause yet, so this report doesn\'t name one.</p>') . '</div>'
        . (function () use ($r, $e, $confirmed) {   // a person writes it (02 §2.6, P12: "Unconfirmed until a person writes it"); greyed with the reason for anyone who can't
            [$okC, $whyC] = ControlBoard::can();
            return '<p><button type="button" class="ak-b" data-cb-ircause="' . (int) $r['id'] . '" data-cb-cur="' . $e($confirmed ? (string) $r['root_cause'] : '') . '"'
                . ($okC ? '' : ' disabled title="' . $e((string) $whyC) . '"') . '>' . ($confirmed ? 'Change the root cause' : 'Write the root cause') . '</button>'
                . ($okC ? '' : ' <small class="ak-note">' . $e((string) $whyC) . '</small>') . '</p>';
        })()
        . '<p class="ak-note">Signature: ' . $e((string) $r['signature']) . ' (its host and kind; trips with the same signature within ' . ControlIncidents::WINDOW_MIN . ' minutes join it).</p></section>';
    $mh = '';
    foreach ($members as $m) {
        $aid = (int) $m['id'];
        $mh .= '<tr><td data-th="Part">' . $e((string) $m['title']) . '</td><td data-th="Warning">' . $e(ControlIncidents::warningId($aid)) . '</td><td data-th="First failure">' . tv_cb_at((int) strtotime($m['first_at'] . ' UTC')) . '</td>'
            . '<td data-th="Trip">' . $e(ControlIncidents::tripId($aid)) . '</td><td data-th="Tripped">' . ($m['tripped_at'] ? tv_cb_at((int) strtotime($m['tripped_at'] . ' UTC')) : 'not yet') . '</td>'
            . '<td data-th="Closed">' . ($m['cleared_at'] ? tv_cb_at((int) strtotime($m['cleared_at'] . ' UTC')) . ($m['cleared_how'] ? ' <small>' . $e((string) $m['cleared_how']) . '</small>' : '') : 'open') . '</td></tr>';
    }
    $h .= '<section class="ak-card tv-bleed cb-rsec" data-copy="Members"><h3 class="cb-title">Members, warning and trip ids</h3>' . ($mh !== '' ? '<div class="cb-rtw"><table class="cb-tt"><thead><tr><th>Part</th><th>Warning</th><th>First failure</th><th>Trip</th><th>Tripped</th><th>Closed</th></tr></thead><tbody>' . $mh . '</tbody></table></div>' : '<p class="ak-note">No member is kept (its alarms are older than the alarms\' record).</p>') . '</section>';
    $rad = (array) $r['radius'];
    $h .= '<section class="ak-card tv-bleed cb-rsec" data-copy="Incident Radius"><h3 class="cb-title">Incident Radius, as recorded</h3><dl class="cb-dl"><dt>Held</dt><dd>' . $e(((array) ($rad['held'] ?? [])) ? implode('; ', (array) $rad['held']) : 'nothing recorded as held') . '</dd>'
        . '<dt>On a backup</dt><dd>' . $e(((array) ($rad['on_backup'] ?? [])) ? implode('; ', (array) $rad['on_backup']) : 'none') . '</dd></dl></section>';
    $tl = ''; foreach ((array) $r['timeline'] as $t) { $t = array_values((array) $t); $tl .= '<li>' . tv_cb_at((int) strtotime((string) ($t[0] ?? '') . ' UTC')) . ' ' . $e((string) ($t[1] ?? '')) . '</li>'; }
    $h .= '<section class="ak-card tv-bleed cb-rsec" data-copy="Timeline"><h3 class="cb-title">Timeline</h3>' . ($tl !== '' ? '<ul class="cb-tl">' . $tl . '</ul>' : '<p class="ak-note">Nothing on its timeline yet.</p>') . '</section>';
    $h .= '<section class="ak-card tv-bleed cb-rsec" data-copy="Evidence"><h3 class="cb-title">Evidence</h3><p>' . $e(trim((string) $r['evidence']) !== '' ? (string) $r['evidence'] : 'No reading was kept with its first trip.') . '</p></section>';
    // admin-control.js for Write the root cause (on this page it does only that: no redraw)
    return $h . '</div>' . tv_cb_report_css() . '<script src="/js/tv-time.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-time.js') . '" defer></script>'
        . '<script src="/js/admin-control.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-control.js') . '" defer></script>' . tv_cb_view_assets();
}

/** The report folded (D16; Jason on the 7-day report, live-reads C3: "this page is ridiculously long"): the summary and spend first; uptime only below 100% (the rest one line); alarms grouped by part and alarm with a count, opened to the rows on tap; switches as they are; the CSV and the file unchanged. */
function tv_cb_report_folded(array $r): string
{
    $e = 'tv_w_e';
    $sec = $r['sections'];
    $table = function (array $cols, array $rows) use ($e): string {
        $h = '<div class="cb-rtw"><table class="cb-tt"><thead><tr>' . implode('', array_map(fn ($c) => '<th>' . $e($c) . '</th>', $cols)) . '</tr></thead><tbody>';
        foreach ($rows as $row) $h .= '<tr>' . implode('', array_map(fn ($c, $i) => '<td data-th="' . $e($cols[$i] ?? '') . '">' . tv_cb_rcell($c) . '</td>', $row, array_keys($row))) . '</tr>';
        return $h . '</tbody></table></div>';
    };
    $one = function (string $k, string $title, string $body, bool $fold = false, string $sum = '') use ($e): string {
        return $fold ? '<details class="ak-card tv-bleed cb-rsec" id="r-' . $e($k) . '" data-copy="' . $e($title) . '"><summary class="cb-title">' . $e($title) . ($sum !== '' ? ' <small>' . $e($sum) . '</small>' : '') . '</summary>' . $body . '</details>'
            : '<section class="ak-card tv-bleed cb-rsec" id="r-' . $e($k) . '" data-copy="' . $e($title) . '"><h3 class="cb-title">' . $e($title) . ($sum !== '' ? ' <small>' . $e($sum) . '</small>' : '') . '</h3>' . $body . '</section>';
    };
    $note = fn (string $n) => $n !== '' ? '<p class="ak-note">' . $e($n) . '</p>' : '';
    $h = '';
    // spend, as it is
    if (isset($sec['spend'])) { [$t, $c, $rows, $n] = $sec['spend']; $h .= $one('spend', $t, ($rows ? $table($c, $rows) : '') . $note($n)); }
    // uptime: only what was below 100%; the rest in one line
    if (isset($sec['uptime'])) {
        [$t, $c, $rows, $n] = $sec['uptime'];
        $below = array_values(array_filter($rows, fn ($x) => preg_match('/^([\d.]+)%$/', (string) $x[2], $m) && (float) $m[1] < 100));
        $full = count(array_filter($rows, fn ($x) => (string) $x[2] === '100%')); $nd = count($rows) - count($below) - $full;
        usort($below, fn ($a, $b) => (float) $a[2] <=> (float) $b[2]);
        $rest = $rows ? ($full ? $full . ' part' . ($full === 1 ? '' : 's') . ' at 100%' : '') . ($nd ? ($full ? '; ' : '') . $nd . ' not due to be on' : '') . '.' : '';
        $h .= $one('uptime', $t, ($below ? $table($c, $below) : ($rows ? '<p data-tv-status="ok" data-tv-label="All up:">Every part was up whenever it was due.</p>' : '')) . ($rest !== '.' && $rest !== '' ? '<p class="ak-note">And ' . $e($rest) . '</p>' : '') . $note($n), false, $below ? count($below) . ' below 100%' : '');
    }
    // alarms: grouped by part and alarm, with a count, each opened to its rows on tap
    if (isset($sec['alarms'])) {
        [$t, $c, $rows, $n] = $sec['alarms'];
        $g = []; foreach ($rows as $row) $g[(string) $row[2] . '|' . (string) $row[3]][] = $row;
        uasort($g, fn ($a, $b) => count($b) <=> count($a));
        $body = '';
        foreach ($g as $rs) {
            $first = min(array_map(fn ($x) => (int) ($x[0]['t'] ?? 0), $rs)); $openN = count(array_filter($rs, fn ($x) => $x[6] === 'open'));
            $body .= '<details class="cb-rg"><summary><b>' . $e((string) $rs[0][3]) . '</b> <small>' . $e((string) $rs[0][2]) . ' · ' . $e((string) $rs[0][1]) . '</small> ×' . count($rs) . ' <small>· first ' . tv_cb_at($first) . ($openN ? ' · ' . $openN . ' open now' : '') . '</small></summary>' . $table($c, $rs) . '</details>';
        }
        $h .= $one('alarms', $t, $body . $note($n), false, $rows ? count($rows) . ' alarm' . (count($rows) === 1 ? '' : 's') . ' in ' . count($g) . ' group' . (count($g) === 1 ? '' : 's') : '');
    }
    // switches, as they are
    if (isset($sec['changes'])) { [$t, $c, $rows, $n] = $sec['changes']; $h .= $one('changes', $t, ($rows ? $table($c, $rows) : '') . $note($n)); }
    // the rest, folded with what they hold in their line
    foreach (['ran', 'faults', 'trend'] as $k) if (isset($sec[$k])) { [$t, $c, $rows, $n] = $sec[$k]; $h .= $one($k, $t, ($rows ? $table($c, $rows) : '') . $note($n), true, count($rows) . ' row' . (count($rows) === 1 ? '' : 's')); }
    foreach ($sec as $k => [$t, $c, $rows, $n]) if (!in_array($k, ['spend', 'uptime', 'alarms', 'changes', 'ran', 'faults', 'trend'], true)) $h .= $one((string) $k, $t, ($rows ? $table($c, $rows) : '') . $note($n), true, count($rows) . ' rows');   // a section added later is never dropped
    return $h;
}

/** The views' style and scripts (Copy, the side panels), once per page. */
function tv_cb_view_assets(): string
{
    static $done = false; if ($done) return ''; $done = true;
    return tv_score_gauge_css() . '<style>'
        . '#cb .cb-views{grid-template-columns:repeat(3,1fr)}#cb .cb-view{display:grid;grid-template-columns:minmax(0,1fr);gap:.6rem;margin:.4rem 0}'
        // a view's one column shrinks to the window; a long word or address wraps, never widens the page (Jason, 2026-11-23)
        . '#cb .ak-note,#cb .cb-q{overflow-wrap:anywhere}#cb .cb-mt{grid-template-columns:minmax(0,1fr)}#cb-mt-form{min-width:0}#cb-mt-form label{min-width:0;max-width:100%}#cb-mt-form select{max-width:100%;min-width:0}#cb .cb-rf label{min-width:0;max-width:100%}#cb .cb-rf select{max-width:100%;min-width:0}'
        . '#cb .cb-q3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;padding:.6rem .8rem}#cb .cb-qh{margin:0 0 .25rem;font-size:13px;text-transform:uppercase;letter-spacing:.03em;color:var(--tv-meta,#6b5d49)}#cb .cb-q p{margin:0}#cb .cb-q3l{margin:0;padding-left:1.1rem;font-size:13.5px}'
        . '#cb .cb-box{padding:.6rem .8rem}#cb .cb-box>.cb-title small{font-weight:400;color:var(--tv-meta,#6b5d49);font-size:12px}#cb .cb-gg{display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:.5rem;margin-top:.4rem}'
        . '#cb .cb-gc{border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;background:#FFFDF8;padding:.45rem;display:flex;flex-direction:column;gap:.3rem;min-width:0}'
        . '#cb .cb-gopen{display:flex;flex-direction:column;align-items:center;gap:.2rem;background:none;border:0;padding:.2rem;font:inherit;color:inherit;cursor:pointer;text-align:center;border-radius:.4rem;width:100%}#cb .cb-gopen:hover{background:#FBF6EC}#cb .cb-gopen:focus-visible{outline:2px solid var(--tv-river,#3B5A6B);outline-offset:1px}'
        . '#cb .cb-gcopy{display:flex;justify-content:center;margin-top:.3rem}#cb .cb-gcopy>.tv-copy-b{float:none;margin:0}'
        . '#cb .cb-gh{font-weight:700;font-size:13.5px;text-align:center}#cb .cb-dc .cb-gh{display:block}#cb .cb-gst,#cb .cb-dc>.cb-gst{display:block;text-align:center}#cb .cb-rd{font-size:12.5px;display:flex;flex-wrap:wrap;gap:.25rem;justify-content:center}#cb .cb-rdp{border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:999px;padding:.05rem .45rem;background:#fff}#cb .cb-rdn{color:var(--tv-meta,#6b5d49)}'
        . '#cb .cb-live{font-size:11.5px;color:#3d6b52}#cb .cb-scn{font-size:11px;color:var(--tv-copper,#C85A17);border:1px dotted currentColor;border-radius:.3rem;padding:0 .3rem}#cb .cb-gtr,#cb .cb-gtg,#cb .cb-dl1,#cb .cb-nochk{font-size:11.5px;color:var(--tv-meta,#6b5d49);text-align:center}#cb .cb-nochk{color:var(--cb-hold,var(--tv-hold));overflow-wrap:anywhere;white-space:normal;display:block}#cb .cb-gc>*{min-width:0;max-width:100%}#cb .cb-gc .cb-gh{padding:0 .2rem}'
        . '#cb .cb-gb{display:flex;flex-wrap:wrap;gap:.25rem;justify-content:center}#cb .cb-unm{font-size:11px;color:var(--tv-meta,#6b5d49);font-style:italic;text-align:center}#cb .cb-prev{font-size:12px;text-align:center}#cb .cb-prev summary{cursor:pointer;text-decoration:underline}#cb .cb-prev p{margin:.2rem 0}'
        . '#cb .cbg-top{margin:.2rem 0 .4rem;font-size:13.5px}#cb .cbg-bar{display:flex;height:22px;border-radius:.5rem;overflow:hidden;margin:.5rem 0 .35rem;gap:2px;background:var(--tv-card-edge,#E3DBC6)}#cb .cbg-seg{border:0;padding:0;cursor:pointer;color:#fff;font:600 11.5px var(--tv-font-ui,system-ui);min-width:6px;transition:filter 150ms ease-out}#cb .cbg-seg[hidden],#cb .cbg-lg[hidden],#cb .cbg-b[hidden]{display:none}#cb .cbg-seg:hover{filter:brightness(1.12)}#cb .cbg-seg[aria-pressed=true]{outline:3px solid var(--tv-ink,#2A1D14);outline-offset:-3px}#cb .cbg-s-fail{background:var(--cb-hold)}#cb .cbg-s-build{background:var(--tv-taupe,#7A6A52)}#cb .cbg-s-check{background:var(--tv-river,#3B5A6B)}#cb .cbg-s-ok{background:#3f6b55}#cb .cbg-legend{display:flex;flex-wrap:wrap;gap:.3rem}#cb .cbg-lg{display:inline-flex;align-items:center;gap:.3rem;border:1px solid var(--tv-card-edge,#E3DBC6);background:var(--tv-card-a,#F6F1E4);border-radius:999px;padding:.15rem .55rem;font-size:12.5px;cursor:pointer;color:var(--tv-ink,#2A1D14)}#cb .cbg-lg[aria-pressed=true]{border-color:var(--tv-ink,#2A1D14);box-shadow:inset 0 0 0 1px var(--tv-ink,#2A1D14)}#cb .cbg-bk{display:inline-block;width:10px;height:10px;border-radius:3px;flex:none}#cb .cbg-bk-fail{background:var(--cb-hold)}#cb .cbg-bk-build{background:var(--tv-taupe,#7A6A52)}#cb .cbg-bk-check{background:var(--tv-river,#3B5A6B)}#cb .cbg-bk-ok{background:#3f6b55}#cb .cbg-bk-sign{background:var(--tv-amethyst,#7D6B8D)}#cb .cbg-acs{display:grid;gap:.4rem}#cb .cbg-ac{display:flex;flex-wrap:wrap;gap:.4rem .6rem;align-items:center;justify-content:space-between;background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-left:4px solid var(--tv-taupe,#7A6A52);border-radius:.5rem;padding:.5rem .6rem;transition:box-shadow 150ms ease-out}#cb .cbg-ac p{margin:0;font-size:13.5px;flex:1 1 14rem}#cb .cbg-ac.cbg-flash{box-shadow:0 0 0 2px var(--tv-btn,#92400E)}#cb .cbg-ac-fail{border-left-color:var(--cb-hold)}#cb .cbg-ac-sign{border-left-color:var(--tv-amethyst,#7D6B8D)}#cb .cbg-ac-check{border-left-color:var(--tv-river,#3B5A6B)}#cb .cbg-ac-ok{border-left-color:#3f6b55}#cb .cbg-b{display:inline-flex;align-items:center;gap:.25rem;border:1px solid var(--tv-btn,#92400E);background:var(--tv-btn,#92400E);color:var(--tv-btn-text,#E4DCC5);border-radius:.45rem;padding:.3rem .6rem;font:600 12.5px var(--tv-font-ui,system-ui);cursor:pointer;text-decoration:none;white-space:nowrap}#cb .cbg-b.cbg-o{background:transparent;color:var(--tv-btn,#92400E)}#cb .cbg-b.cbg-sm{padding:.2rem .5rem;font-size:12px}#cb .cbg-b:disabled{opacity:.55;cursor:not-allowed}#cb .cbg-lh{display:flex;align-items:center;justify-content:space-between;gap:.5rem}#cb .cbg-key{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin:.1rem 0 .3rem;font-size:12.5px;color:var(--tv-meta,#6b5d49)}#cb .cbg-info{border:1px solid var(--tv-card-edge,#E3DBC6);background:var(--tv-card-a,#F6F1E4);border-radius:50%;width:22px;height:22px;cursor:pointer;color:var(--tv-btn,#92400E);font-size:13px;line-height:1;padding:0}#cb .cbg-dleg{position:relative;flex-basis:100%;background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.4rem 1.8rem .4rem .6rem;color:var(--tv-ink,#2A1D14)}#cb .cbg-dleg[hidden]{display:none}#cb .cbg-dleg p{margin:0 0 .3rem}#cb .cbg-dleg ul{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.3rem .9rem}#cb .cbg-dleg li{display:flex;align-items:center;gap:.3rem}#cb .cbg-dlx{position:absolute;top:.1rem;right:.2rem;font-size:18px;line-height:1;background:none;border:0;cursor:pointer;color:var(--tv-meta,#6b5d49)}#cb .cbg-items{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:minmax(0,1fr);gap:3px}#cb .cbg-it{background:var(--tv-card-a,#F6F1E4);border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.5rem;padding:.4rem .5rem}#cb .cbg-it[hidden],#cb .cbg-items:not(.cbg-all) .cbg-it[data-cbg-more-row]{display:none}#cb .cbg-ih{display:flex;align-items:center;gap:.45rem}#cb .cbg-nm{flex:1;min-width:0;color:var(--tv-link,#92400E);text-decoration:underline;font-weight:600;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#cb .cbg-nm2{flex:1;min-width:0;font-weight:600;font-size:13.5px}#cb .cbg-ex{border:0;background:none;cursor:pointer;font-size:15px;color:var(--tv-meta,#6b5d49);padding:.1rem .3rem;transition:transform 150ms ease-out}#cb .cbg-ex[aria-expanded=true]{transform:rotate(180deg)}#cb .cbg-dots{display:inline-flex;gap:3px;flex:none}#cb .cbg-dword{display:none;font-size:12px;color:var(--tv-meta,#6b5d49)}#cb .cbg-d{width:9px;height:9px;border-radius:50%;display:inline-block;border:1.5px solid var(--tv-taupe,#7A6A52);flex:none}#cb .cbg-d-met{background:#3f6b55;border-color:#3f6b55}#cb .cbg-d-fail{background:var(--cb-hold);border-color:var(--cb-hold)}#cb .cbg-d-nocheck{border-color:var(--tv-river,#3B5A6B);border-style:dashed}#cb .cbg-ib{margin-top:.4rem;border-top:1px dashed var(--tv-card-edge,#E3DBC6);padding-top:.4rem}#cb .cbg-ib[hidden]{display:none}#cb .cbg-st{margin:0 0 .35rem;font-size:13px}#cb .cbg-chips{display:flex;flex-wrap:wrap;gap:.25rem;margin:.2rem 0}#cb .cbg-chip{border-radius:999px;padding:.1rem .5rem;font-size:12px;border:1px solid var(--tv-card-edge,#E3DBC6);background:#FFFBF2;cursor:pointer;text-decoration:none;color:var(--tv-ink,#2A1D14);font-family:inherit}#cb .cbg-c-pass{border-color:#3f6b55;color:#3f6b55}#cb .cbg-c-fail{border-color:var(--cb-hold);color:var(--cb-hold);font-weight:600}#cb .cbg-c-none{border-color:var(--tv-river,#3B5A6B);border-style:dashed;color:var(--tv-river,#3B5A6B)}#cb .cbg-c-part{border-color:var(--tv-river,#3B5A6B);color:var(--tv-river,#3B5A6B)}#cb .cbg-plain{cursor:default}#cb .cbg-sw{font-size:12px;font-weight:600}#cb .cbg-sw-ok{color:#3f6b55}#cb .cbg-sw-fail{color:var(--cb-hold)}#cb .cbg-sw-sign{color:#6B5A7C}#cb .cbg-sw-build{color:var(--tv-taupe,#7A6A52)}#cb .cbg-ic{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;border:1px solid #CBB98D;background:var(--tv-card-a,#F6F1E4);color:#B45309;flex:none;text-decoration:none}#cb .cbg-ic:hover,#cb .cbg-ic:focus-visible{border-color:var(--tv-btn,#92400E)}#cb .cbg-ic .cbg-svg{width:16px;height:16px}#cb .cbg-rm .akt-rml.lft{left:0;right:auto}#cb .cbg-rm .akt-rml a{display:flex;padding:.42rem .55rem;border-radius:.4rem;font:500 .9rem var(--tv-font-ui);color:var(--tv-ink);text-decoration:none}#cb .cbg-rm .akt-rml a:hover,#cb .cbg-rm .akt-rml button:hover{background:var(--tv-card-b,#EFE7D3)}#cb .cb-ic{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;border:1px solid #CBB98D;background:var(--tv-card-a,#F6F1E4);color:#B45309;text-decoration:none;cursor:pointer;list-style:none;flex:none}#cb .cb-ic::-webkit-details-marker{display:none}#cb .cb-ic .cb-svg{width:16px;height:16px}#cb .cb-more{display:inline-block;vertical-align:top;align-self:flex-start}#cb .cb-acts:has(.cb-more[open]){align-items:flex-start}#cb .cb-more .cb-menu{position:static;margin-top:.25rem;min-width:13rem;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line-strong,#CBB98D);border-radius:.6rem;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:.3rem;display:flex;flex-direction:column}#cb .cb-more .cb-mi{border:0;background:none;text-align:left;padding:.45rem .55rem;border-radius:.4rem;font:500 .9rem var(--tv-font-ui,inherit);color:var(--tv-ink,#2b2118);cursor:pointer}#cb .cb-more .cb-mi:hover{background:#EFE7D3}#cb .cbg-done b.cbg-late{color:var(--cb-hold)}#cb .cbg-sl{font-size:12px;color:var(--tv-link,#92400E);text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;white-space:nowrap;font-family:inherit}#cb .cbg-done{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;font-size:13px;margin-top:.35rem}#cb .cbg-done b{color:#6B5A7C}#cb .cbg-done b.cbg-ok{color:#3f6b55}#cb .cbg-acts{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem}#cb .cbg-acts[hidden]{display:none}#cb .cbg-more{margin-top:.4rem}#cb details.cbg-src,#cb details.cbg-nd{margin-top:.5rem;font-size:12.5px}#cb details.cbg-src summary,#cb details.cbg-nd summary{cursor:pointer;color:var(--tv-meta,#6b5d49)}#cb details.cbg-nd summary{font-size:13.5px;color:var(--tv-ink,#2A1D14)}#cb .cbg-sub{position:absolute;inset:0;background:#FFFBF2;padding:.8rem 1rem 1.4rem;overflow:auto;z-index:2}#cb .cbg-sub[hidden]{display:none}#cb .cbg-shot{display:block;margin:.4rem 0;padding:1.2rem .6rem;text-align:center;background:var(--tv-sec,#EDE6D5);border:1px dashed var(--tv-card-edge,#E3DBC6);border-radius:.5rem;color:var(--tv-link,#92400E)}#cb .cb-sp{position:fixed}#cb .cb-sp .cbg{position:static}@media (max-width:767px){#cb .cbg-nm{white-space:normal}#cb .cbg-ih{flex-wrap:wrap}#cb .cbg-dots{display:none}#cb .cbg-dword{display:inline;order:3;flex-basis:100%;padding-left:calc(10px + .45rem)}#cb .cbg-ih .cbg-ex{margin-left:auto}#cb .cbg-ac .cbg-b{margin-left:auto}}'
        . '#cb .cb-spbg{position:fixed;inset:0;background:rgba(40,30,20,.28);z-index:9990}#cb .cb-sp{position:fixed;top:0;right:0;bottom:0;width:min(30rem,calc(100vw - 6px));overflow:auto;z-index:9991;background:#FFFBF2;border-left:1px solid var(--tv-card-edge,#E3DBC6);box-shadow:-6px 0 18px rgba(0,0,0,.12);padding:.8rem 1rem 1.4rem;box-sizing:border-box}'
        . '#cb .cb-sp[hidden]{display:none}#cb .cb-spx{position:absolute;top:.35rem;right:.5rem;font-size:22px;line-height:1;background:none;border:0;cursor:pointer;color:var(--tv-meta,#6b5d49);padding:.2rem .4rem}#cb .cb-crumb{margin:0 2rem .2rem 0;font-size:12px;color:var(--tv-meta,#6b5d49)}'
        . '#cb .cb-gbig{display:flex;flex-direction:column;align-items:center;gap:.3rem;margin:.3rem 0}#cb .cb-dl{display:grid;grid-template-columns:7rem minmax(0,1fr);gap:.25rem .6rem;font-size:13.5px;margin:.4rem 0}#cb .cb-dl dt{color:var(--tv-meta,#6b5d49)}#cb .cb-dl dd{margin:0;min-width:0;overflow-wrap:anywhere}'
        . '#cb .cb-h4{margin:.6rem 0 .2rem;font-size:13.5px}#cb .cb-su{list-style:none;margin:0;padding:0;font-size:13.5px}#cb .cb-su li.ok{color:#3d6b52}#cb .cb-pathl{list-style:none;margin:.3rem 0;padding:0;display:grid;gap:.35rem;font-size:13.5px}#cb .cb-pathl li{overflow-wrap:anywhere}#cb .cb-share{font-size:12px;color:var(--tv-meta,#6b5d49)}'
        . '#cb .cb-shl{list-style:none;margin:.3rem 0;padding:0;display:grid;gap:.5rem}#cb .cb-shl li{border:1px solid #EFE6D6;border-radius:.4rem;padding:.45rem .55rem;background:#FFFDF8;overflow-wrap:anywhere}#cb .cb-shd{font-size:13.5px}'
        . '#cb .cb-rg{border-top:1px solid #EFE6D6;padding:.3rem 0}#cb .cb-rg>summary{cursor:pointer;overflow-wrap:anywhere}#cb details.cb-rsec>summary{cursor:pointer}#cb .cb-tl{margin:0;padding-left:1.1rem;font-size:13.5px}'
        . '@media (max-width:767px){#cb .cb-q3{grid-template-columns:1fr}#cb .cb-gg{grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}#cb .cb-box{padding:.5rem .4rem}#cb .cb-dl{grid-template-columns:1fr}#cb .cb-views{grid-template-columns:repeat(3,minmax(0,1fr))}#cb .cb-gb .ak-b{font-size:11.5px;padding:.1rem .35rem}}'
        . '</style><script src="/js/tv-copy.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-copy.js') . '" defer></script>';
}

function tv_admin_mod_control(PDO $pdo): string
{
    if (($_GET['show'] ?? '') === 'osm-statement') return tv_cb_statement_osm();
    $e = 'tv_w_e';
    [$can, $why] = ControlBoard::can();
    if (!$can) return '<p data-tv-status="info" data-tv-label="Not for your role:">' . $e((string) $why) . '. The System Control Board is for executives: ask one, or write to support@traversence.com.</p>';
    // a process's own page (decisions/0171 §12): /admin/config/control/<kind>-<name> (wf-W1, svc-anthropic), opened as a
    // drawer from its row; the board drawn for that one process, with its alarms, its logbook and its report
    $focus = '';
    if (preg_match('/^([a-z]{1,5})[-:]([A-Za-z0-9_.-]{1,60})$/', (string) ($_GET['proc'] ?? ''), $gm)) $focus = $gm[1] . ':' . $gm[2];   // a part opened from the table, in a drawer
    if (preg_match('#^/admin/config/control/([a-z]{1,5})[-:]([A-Za-z0-9_.-]{1,60})(?:[/?\#]|$)#', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) . '/', $fm)) $focus = $fm[1] . ':' . $fm[2];
    // a report, as a section of the board (decisions/0171 §11, release 5b): a query on config/control, never a page of its own
    if (isset($_GET['wqtask'])) return tv_cb_wq_page($pdo, (int) $_GET['wqtask']);   // a Work queue task's page for its panel, fetched as it opens
    if (isset($_GET['gauge'])) return tv_cb_gauge_detail($pdo, (string) $_GET['gauge']);   // a gauge's detail for its panel, fetched as it opens
    if (isset($_GET['ir'])) return tv_cb_ir_page($pdo, (int) $_GET['ir'], (string) ($_SESSION['csrf_token'] ?? ''));   // an Incident Report's page (03 §7)
    if (isset($_GET['report'])) return tv_cb_report_page($pdo, (string) $_GET['report'], (string) ($_GET['range'] ?? 'day'), (string) ($_SESSION['csrf_token'] ?? ''));
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
    $by = tv_cb_by_system($pdo, $res);
    $siteNeeds = ControlBoard::needs($res, array_keys(array_filter(Preflight::NEEDS, fn ($n) => $n[0] === 'siteneeds')));
    $core = ControlBoard::core($res);
    $lastAt = 0; foreach ($res as $r) $lastAt = max($lastAt, (int) ($r['at'] ?? 0));
    $pid = fn (string $id) => 'cb-p-' . str_replace(':', '-', $id);

    $h = '<div id="cb" data-csrf="' . $e($csrf) . '"' . ($focus === '' ? ' data-tab="deliver"' : '') . '>';

    // ---- the alarms first: the board's queue (decisions/0171 §7, release 3a), and what the result line says
    // open alarms only (Jason, 2026-11-19: "if alarms are cleared they should not be present, moved to log"): a cleared one
    // is in the logbook (written when it clears) and in the Alarms table; nothing waits to be marked Seen
    $alarms = array_values(array_filter(ControlAlarms::list($pdo), fn ($x) => $x['cleared_at'] === null)); $ac = ControlAlarms::counts($pdo);
    $names = []; foreach ($by as $rowsN) foreach ($rowsN as $idN => $pN) $names[$idN] = $pN['name'];
    $open = array_values(array_filter($alarms, fn ($x) => $x['cleared_at'] === null));
    if ($focus !== '' && !isset($names[$focus])) return $h . '<p data-tv-status="info" data-tv-label="Not found:">There\'s no process called ' . $e($focus) . ' on the board. <a class="underline" href="/admin/config/control">Open the board</a> to see every process.</p></div>';
    if ($focus !== '') {   // the process's page: its name, then its alarms, its row (open), its logbook and its report
        $mineAll = array_values(array_filter($alarms, fn ($x) => $x['proc'] === $focus && $x['cleared_at'] === null));
        $h .= '<p class="cb-rback"><a href="/admin/config/control" class="underline">← The board</a></p><h2 class="ak-h">' . $e($names[$focus]) . '</h2>'
            . ($mineAll ? tv_cb_alarms($mineAll, ['open' => count($mineAll)] + array_count_values(array_column($mineAll, 'level')) + ['trip' => 0, 'warning' => 0, 'notice' => 0], $names, $pid, $canPf, (string) $whyPf, $btn, [], $focus) : '<p data-tv-status="ok" data-tv-label="No alarm:">Nothing is open for it now.</p>');
        if (str_starts_with($focus, 'wf:')) $h .= tv_cb_steps($pdo, substr($focus, 3), $res, $btn);   // its steps and their switches (0175 §6)
    }
    if ($focus === '') {
    // ---- the result line (Jason, 2026-11-18: "a result notice from a procedure where within the notice I should be able
    // to go to the report or section to further review"): the procedure and its time, what it found, each name opening its
    // alarm, the report in a drawer, and running it again in place; older than an hour, it says so
    $coreBad = array_filter($core, fn ($c) => $c[1] === 'bad');
    $runAt = 0; try { $runAt = (int) (Preflight::summary($pdo)['at'] ?? 0); } catch (Throwable $x) {}
    $pfOpen = array_values(array_filter($open, fn ($x) => str_starts_with((string) $x['akey'], 'pf:') && $x['level'] !== 'notice'));
    $go = fn (array $x) => '<a href="#cb-alarm-' . (int) $x['id'] . '" data-cb-go="cb-alarm-' . (int) $x['id'] . '" class="underline">' . $e($names[$x['proc']] ?? preg_replace('/:.*$/', '', (string) $x['title'])) . '</a>';
    $pfNames = []; foreach ($pfOpen as $x) { $nm = $names[$x['proc']] ?? (string) $x['proc']; if (!isset($pfNames[$nm])) $pfNames[$nm] = $go($x); }
    $old = $runAt && time() - $runAt > 3600;
    $result = $stage === 'shutdown' ? ['off', 'Stopped: the master switch is off.']
        : ($coreBad ? ['bad', 'A core host is down: everything holds.'] : null);
    // the top line is live diagnostics: what's paused now, by the system or a person, and why, each opening its fix in the
    // side panel (0175's note: "once something is active it is not longer preflight, it is diagnostics"; "The board's top
    // line becomes live diagnostics … No stale snapshot"). The pre-flight's result moves to the Start-up stage, below.
    // the opening view (step3/board/index.html, approved 2026-11-22; Jason's ruling of 2026-11-23, 03 §27): "What's wrong now"
    // as one row of chips, each a small list whose items open their part; the rail of views on the left; the board's parts
    // in the main column. The strip and the three boxes that stood here are the Paused now page, reached from its chip.
    $h .= tv_cb_qbar($pdo, $open, $names, $core, $stage, tv_cb_pages_menu($open, $names, $stage)) . '<div class="cb-shell">' . tv_cb_rail($open, $names, $stage) . '<div class="cb-main">';
    $h .= '<section class="cb-view" id="cb-paused-page" data-copy="Paused now"><h2 class="cb-vh">Paused now</h2>';
    $h .= '<div class="cb-top cb-strip" role="status" data-copy="Paused now and the core hosts">';
    if ($result) $h .= '<span class="cb-beacon cb-b-' . $result[0] . '">' . tv_cb_mark($result[0]) . $e($result[1]) . '</span>';
    $lit = []; foreach ($by as $rowsL) foreach ($rowsL as $pid0 => $r0) $lit[$pid0] = (string) $r0['light'];
    $h .= ControlBoard::pausedHtml($pdo, 'cb-paused', $lit);
    $pfres = '';
    // the worst real state (the session manager, 2026-11-18, against css-theme-brand.md): red only for a line really
    // tripped (tripped_at: the system tried and gave up) or a core host down; a line only held is amber with the pause mark
    $pfL = $pfOpen ? (array_filter($pfOpen, fn ($x) => $x['level'] === 'trip' && !empty($x['tripped_at'])) || $coreBad ? 'bad' : 'hold') : '';
    $pfres .= '<span class="cb-result cb-b-' . ($pfOpen ? $pfL : ($runAt ? 'ok' : 'off')) . '">' . tv_cb_mark($pfOpen ? $pfL : ($runAt ? 'ok' : 'new')) . '<span class="cb-rt"><b>Pre-flight, ' . ($runAt ? tv_cb_time($runAt) : 'not run yet') . ':</b> '
        . ($pfOpen ? '<a href="#cb-alarms" data-cb-go="cb-alarms" class="underline">' . count($pfNames) . ' held</a>: ' . implode(', ', array_slice(array_values($pfNames), 0, 3)) . (count($pfNames) > 3 ? ' and <a href="#cb-alarms" data-cb-go="cb-alarms" class="underline">' . (count($pfNames) - 3) . ' more</a>' : '') . '.' : ($runAt ? 'every line passed or is on a backup.' : 'press Run it again.'))
        . ($old ? ' <small class="cb-age">This result is over an hour old.</small>' : '') . '</span>'
        . '<span class="cb-ra"><a class="ak-b cb-xs" href="#preflight">Open the report</a>' . $btn('preflight', 'Run it again', $canPf && !$staging, $staging ? $wNo : (string) $whyPf, '', ['class' => 'ak-b cb-xs']) . '</span></span>';
    $h .= '<span class="cb-core"><b>Core hosts</b> ' . implode(' ', array_map(fn ($c) => '<span class="cb-ch">' . tv_cb_mark($c[1]) . $e($c[0]) . '</span>', $core)) . '</span></div>';
    $h .= tv_cb_three($pdo, $alarms, $ac, $names) . '</section>';   // healthy, held, needs me: kept whole, on this page

    // ---- the six stages, and where the system stands
    $stageTo = [
        'preflight' => ['#preflight', 'Tests every service and setting, read only. Run it before Start.'],
        'startup' => ['#cb-controls', 'Start the system: checks each workflow and task, then switches them on in order.'],
        'observe' => ['#cb-alarms', 'Running and watched: what needs you is in Alarms.'],
        'shutdown' => ['#cb-controls', 'Shut down in order, or Pause everything to stop at once.'],
        'maintenance' => ['#maintenance', 'Take a part out on purpose, and put it back after its check.'],
        'history' => ['#log', 'The logbook: every switch, alarm and note; Reports for a period.'],
    ];
    // the stage's words follow the state: running with something held says so, and where (words follow the state)
    if ($stage === 'observe') { $nHeld = (int) $ac['trip'] + (int) $ac['warning'];   // one count on every surface (D5; g4 F1): ControlAlarms::counts, an acknowledged trip still counted
        if ($nHeld) $stageWords = 'Running, watched. ' . $nHeld . ' alarm' . ($nHeld === 1 ? '' : 's') . ' open: the rest runs; each one\'s fix is under Observation and reporting.'; }
    // the six stages are tabs (Jason, 2026-11-19: "these are jump links, not good design. When clicked that information is
    // what is on page, everything else goes away"): a tile shows only its part of the board; the one the system is in opens
    // first and says "now"; a link to a part (#maintenance, an alarm) opens its tab (admin-control.js)
    // the views first, "Is it delivering?" the first of them (03 §4, §27): each a tab like the stages
    // the views are the rail's (Development, Board; Modules and Workflows their own pages); these two rows are Diagnostics'
    $h .= '<h2 class="cb-vh cb-diagnav">Diagnostics</h2><div class="cb-stages cb-diagnav" role="tablist" aria-label="The board\'s stages: each shows its part">';
    $i = 0;
    foreach (ControlBoard::STAGES as $k => $label) {
        $i++;   // each stage is a route to its own part of the board, with what it is (Jason, 2026-11-19: "I don't understand how the section works")
        [$to, $what] = $stageTo[$k] ?? ['#cb', ''];
        $h .= '<button type="button" role="tab" data-cb-tab="' . $k . '" aria-selected="' . ($k === $stage ? 'true' : 'false') . '" class="cb-st' . ($k === $stage ? ' on now' : '') . '"' . ($k === $stage ? ' aria-current="step"' : '') . ' title="' . $e($what) . '"><small>' . $i . ($k === $stage ? ' · now' : '') . '</small>' . $e($label) . '<span class="cb-stw">' . $e($what) . '</span></button>';
    }
    $h .= '</div>';
    // its tables and Scheduled tasks as tabs (decisions/0175's note "one control board in the menu", release 2b part A: "its
    // tabs Alarms, Logbook, Ledger, Upkeep and Reports"; "Scheduled tasks as a tab"): one place, nothing hunted for
    $h .= '<div class="cb-stages cb-tabs2 cb-diagnav" role="tablist" aria-label="The board\'s tables">';
    $h .= '<button type="button" role="tab" data-cb-tab="incidents" aria-selected="false" class="cb-st cb-st2" title="' . $e(TV_CB_VIEWS['incidents'][1]) . '">' . $e(TV_CB_VIEWS['incidents'][0]) . '<span class="cb-stw">' . $e(TV_CB_VIEWS['incidents'][1]) . '</span></button>';
    foreach (TV_CB_TABS as $k => [$label, $what]) $h .= '<button type="button" role="tab" data-cb-tab="' . $k . '" aria-selected="false" class="cb-st cb-st2" title="' . $e($what) . '">' . $e($label) . '<span class="cb-stw">' . $e($what) . '</span></button>';
    $h .= '</div><div class="cb-diagnav"><p class="cb-stage" data-tv-status="' . ($stage === 'observe' ? 'ok' : 'info') . '" data-tv-label="' . $e(ControlBoard::STAGES[$stage]) . ':">' . $e($stageWords) . '</p></div>';   // its own wrapper: tv-ask redraws the line as a new box, without its class
    // each tab's part: the tables drawn here (the admin's one table, rows loaded as it opens); a page with its own forms
    // (Scheduled tasks, Automation, Pre-flight's runs) loaded in place when its tab first opens, its links using the side panel
    require_once __DIR__ . '/widgets.php';
    foreach (['alarms' => 'config/alarms', 'log' => 'config/log', 'ledger' => 'config/ledger', 'upkeep' => 'config/upkeep'] as $k => $tool)
        $h .= '<div class="cb-tabp" id="cb-tab-' . $k . '" data-copy="' . $e(TV_CB_TABS[$k][0]) . '">' . tv_w_table($pdo, $tool, false) . '</div>';
    $h .= '<div class="cb-tabp" id="cb-tab-scheduler" data-cb-lazy="/admin/config/scheduler?panel=1&amp;peek=1" data-cb-lazyt="Scheduled tasks"><p class="ak-note">Opens with its tab: every scheduled job, its line and its last runs.</p></div>';
    // the Start-up stage holds Automation: its master switch, Start the system, Pause everything, the switch-on check, its
    // workflows, services and changes (the note: "Automation's master switch, Start the system and Pause everything as its
    // Start-up and Shut-down stages"); release 2b's parts B and C move its services to Modules and its workflows to Workflows
    $h .= '<div class="cb-tabp" id="cb-tab-startup" data-cb-lazy="/admin/config/automation?panel=1&amp;peek=1" data-cb-lazyt="Start-up: the master switch and Start the system"><p class="ak-note">Opens with its stage: the master switch, Start the system and Pause everything.</p></div>';
    $h .= '<div class="cb-tabp" id="cb-tab-pfpage" data-cb-lazy="/admin/config/preflight?panel=1&amp;peek=1" data-cb-lazyt="The system pre-flight"><p class="ak-note">Opens with its stage: Run all, each line\'s Test, its runs and its report.</p></div>';
    // the system pre-flight's result: in the Start-up stage (and Pre-flight's), with Run it again, never the top line (0175's
    // note: "The system pre-flight runs at Start the system … It's shown in the Start-up stage, with Run it again")
    $h .= '<div class="cb-top cb-pfres" id="cb-pfres">' . $pfres . '</div>';
    // the views' parts (03 §4, §27; gauge-lifecycle.md; 03 §7)
    // Board: "Is it delivering?" alone, full width; Development is its own page from the rail (Jason, 2026-11-23:
    // "development is its own page, not side by side on control board"; 03 §27)
    $h .= '<div class="cb-landh"><h2 class="cb-vh">Is it delivering?</h2></div>'
        . tv_cb_deliver($pdo, $alarms, $ac, $names) . tv_cb_development($pdo, $btn) . tv_cb_incidents($pdo, $names);

    // ---- the system's own controls; find, open all, close all
    $h .= '<div class="cb-bar" id="cb-controls"><div class="cb-acts">'
        . $btn('preflight', 'Run the pre-flight', $canPf && !$staging, $staging ? $wNo : $whyPf)
        . $btn('start', 'Start the system', $canRun && !$staging, $wNo, '', ['data-confirm' => 'Start the system? It confirms each workflow and task, then switches them on in order.', 'data-yes' => 'Start it'])
        . $btn('shutdown', 'Shut down in order', $canRun && !$staging, $wNo, '', ['data-confirm' => 'Shut the whole system down in order? The tasks, then the workflows in reverse order, then the master switch and the crawler\'s schedule. What\'s running finishes.', 'data-yes' => 'Shut it down'])
        . $btn('pauseall', 'Pause everything', $canRun && !$staging, $wNo, '', ['class' => 'ak-b cb-danger', 'data-confirm' => 'Pause everything at once? The emergency stop: the master switch off and the crawler\'s schedule removed.', 'data-yes' => 'Pause everything', 'data-danger' => '1'])
        . '</div></div>';   // finding a part is the parts table's own search now (its rows open in a drawer: nothing to open or close all)

    // ---- the queue: the alarm list, one row per issue (it replaces "Holding now": Jason, 2026-11-18, "does the system board
    // have a queue where issues can be found and addressed"); open on load whenever anything is open
    $h .= tv_cb_afilter();
    // a process whose check is red, with the part that holds it: Check and start again waits for it, greyed with the reason
    // (the session manager, 2026-11-19: never a silent restart)
    $redChecks = [];
    foreach ($res as $rid => $rr) if (($rr['s'] ?? '') === 'bad') { $hold = ''; foreach ((array) ($rr['extra']['parts'] ?? []) as [$pt, $ps]) if ($ps === 'bad') { $hold = (string) $pt; break; }
        $redChecks[(string) $rid] = ControlBoard::sentence($hold !== '' ? $hold : (string) ($rr['said'] ?? '')); }
    $h .= tv_cb_alarms($alarms, $ac, $names, $pid, $canPf, (string) $whyPf, $btn, $redChecks);
    $tms = ControlBoard::timings($pdo, $res); $slow = count(array_filter($tms, fn ($x) => $x[1] !== null && $x[3] === 'bad')); $untested = count(array_filter($tms, fn ($x) => $x[1] === null));
    $h .= '<div class="cb-panels"><details class="ak-card tv-bleed cb-panel" id="cb-times" data-copy="Query timings"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($slow ? 'bad' : ($untested === count($tms) ? 'new' : 'ok')) . '<b>Query timings</b> <small>' . ($slow ? $slow . ' of ' . count($tms) . ' over its limit' : ($untested === count($tms) ? 'not run yet' : 'all ' . count($tms) . ' within their limits')) . '</small></summary><table class="cb-tt"><thead><tr><th>Query</th><th>Took</th><th><a class="underline" href="/admin/config/thresholds" title="Change a limit on Thresholds">Limit</a></th><th></th></tr></thead><tbody>';
    foreach ($tms as $k => [$what, $ms, $lim, $s, $at])
        $h .= '<tr><td>' . $e(preg_replace('/ \(.*$/', '', $what)) . '<br><small>' . $e((string) (preg_match('/\((.*)\)/', $what, $mm) ? $mm[1] : '')) . '</small></td><td class="cb-num">' . ($ms === null ? '—' : number_format($ms) . ' ms') . '</td><td class="cb-num">' . ($lim ? number_format($lim) . ' ms' : '—') . '</td><td>' . tv_cb_mark($ms === null ? 'new' : $s) . '</td></tr>';
    $h .= '</tbody></table><div class="cb-acts">' . $btn('timings', 'Re-test the four', $canPf, (string) $whyPf) . '<small class="ak-note">Read only; a slow one is noted and holds nothing.</small></div></details>' . tv_cb_watch($pdo, $btn) . '</div>';
    $h .= tv_cb_maintenance($pdo, $names, $btn, $canRun && !$staging, $wNo);
    $h .= tv_cb_logbook($pdo, $names, (bool) $can);
    $h .= tv_cb_reports_panel($pdo);
    }

    // ---- the parts of the system: the admin's one table (design/table-standard.md, T1; the session manager, 2026-11-19):
    // a group row per system, each part opening its own page in a drawer (rule 6, in place of opening under itself, 0171
    // §2's note); a process's own page keeps its row opened in place, below
    if ($focus === '') {
        $h .= tv_cb_process_table();
        if ($siteNeeds) {   // the site's own needs, each with its sources: a panel of their own
            $sn = array_count_values(array_column($siteNeeds, 1)); $sl = !empty($sn['bad']) ? 'bad' : (!empty($sn['warn']) ? 'warn' : (!empty($sn['ok']) ? 'ok' : 'wait'));
            $h .= '<details class="ak-card tv-bleed cb-panel" id="cb-site-needs" data-copy="What the site needs from outside"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($sl) . '<b>What the site needs from outside</b> <small>' . count($siteNeeds) . ' needs, each with its sources</small></summary>'
                . tv_cb_kv('Needs', tv_cb_needs($siteNeeds, $canPf, (string) $whyPf)) . '</details>';
        }
        $tr = ''; foreach (ControlRegister::SYSTEMS as $sk => $sname) $tr .= tv_cb_kv($sname, '<div class="cb-trend" data-cb-trend="sys:' . $e($sk) . '"><small class="ak-note">Loads when this opens: its processes summed.</small></div>');
        $h .= '<details class="ak-card tv-bleed cb-panel cb-trends" id="cb-trends" data-copy="Trends by system"><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark('na') . '<b>Trends by system</b> <small>each system\'s processes summed, over 24 hours, 7 or 30 days</small></summary>' . $tr . '</details>';
    }
    foreach ($focus !== '' ? ControlRegister::SYSTEMS : [] as $sk => $sname) {
        $rows = $by[$sk] ?? [];
        if ($focus !== '') { if (!isset($rows[$focus])) continue; $rows = [$focus => $rows[$focus]]; }
        $n = array_count_values(array_column($rows, 'light'));
        $worst = !empty($n['bad']) ? 'bad' : (!empty($n['alarm']) ? 'alarm' : (!empty($n['hold']) ? 'hold' : (!empty($n['waitrun']) ? 'waitrun' : (!empty($n['warn']) ? 'warn' : (!empty($n['ok']) ? 'ok' : 'wait')))));
        $sum = [];
        foreach (['ok' => 'working', 'bad' => 'tripped', 'hold' => 'held', 'warn' => 'on a backup', 'maint' => 'in maintenance', 'off' => 'off', 'wait' => 'not tested'] as $k => $w) if (!empty($n[$k])) $sum[] = '<button type="button" class="cb-lf" data-cb-lf="' . $k . '" title="Show only the ' . $e($w) . ' ones in ' . $e($sname) . '">' . $n[$k] . ' ' . $e($w) . '</button>';   // each count filters the system's rows (Jason, 2026-11-19)
        $h .= '<details class="ak-card tv-bleed cb-sys" id="cb-' . $e($sk) . '" data-cb-sys="' . $e($sk) . '"' . ($focus !== '' ? ' open' : '') . '>'   // closed on load, held or not (Jason, 2026-11-18): its header counts the held
            . '<summary class="cb-sh"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($worst) . '<h3 class="cb-title">' . $e($sname) . '</h3><small class="cb-sum">' . implode(' · ', $sum) . '</small></summary>'
            . '<p class="cb-lfon" hidden><span data-cb-lfw></span> <button type="button" class="ak-b cb-xs" data-cb-lf="">Show all</button></p>'
            . '<div class="cb-sys-tr">' . tv_cb_kv('Trend', '<div class="cb-trend" data-cb-trend="sys:' . $e($sk) . '"><small class="ak-note">Opens with the system: its processes summed.</small></div>') . '</div>'   // the historian (0171 §6, 4b), loaded when the system opens
            . '<div class="cb-row cb-head" aria-hidden="true"><span></span><span></span><span>Process</span><span>Stage</span><span>Where it stands</span><span>Last 24 hours</span><span>Speed</span><span>OK</span><span></span></div>';
        foreach ($rows as $id => $p) {
            $c = $p['calls']; $lt0 = (string) $p['light'];   // the light its system's header counted
            $needs = str_starts_with($id, 'wf:') ? ControlBoard::needs($res, (array) ($p['parts']['needs'] ?? [])) : [];
            $q = mb_strtolower($p['name'] . ' ' . $p['words'] . ' ' . implode(' ', array_map(fn ($x) => $x[0] . ' ' . implode(' ', array_column($x[2], 0)), $needs)));
            // its open alarms: a trip marks it red, and the alarm is where it stands
            $mine = array_values(array_filter($alarms, fn ($x) => $x['proc'] === $id && $x['cleared_at'] === null));
            if ($mine && $mine[0]['level'] === 'trip') $p['light'] = 'bad';
            // an open warning shows its level's mark on the row, never a green ✓ beside it (playbook §2, principle 4: words
            // follow the state); a held, out or off part keeps its own
            elseif ($mine && in_array((string) $p['light'], ['ok', 'warn', 'wait'], true) && array_filter($mine, fn ($x) => $x['level'] === 'warning')) $p['light'] = 'alarm';
            $r = tv_cb_row($id, $p, $needs, $stage);
            if ($mine) $r['where'] = ControlAlarms::LEVELS[$mine[0]['level']] . ': ' . $mine[0]['title'] . (count($mine) > 1 ? ' (and ' . (count($mine) - 1) . ' more in the alarm list)' : '') . '.';
            if ($wk = tv_cb_wf_kind($id)) { $r['stage'] = ['new', $wk[0]]; if (!$mine) $r['where'] = $wk[1]; }   // W13 a list, W14 Not built   // switched on while the system is stopped: Standby, not Observation
            $h .= '<details class="cb-p" id="' . $e($pid($id)) . '" data-cb-p="' . $e($id) . '" data-light="' . $e($lt0) . '" data-q="' . $e($q) . '"' . ($focus !== '' ? ' open' : '') . '>'
                . '<summary class="cb-row"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($p['light'])
                . '<span class="cb-nm"><b class="cb-n">' . $e($p['name']) . '</b>' . ($r['sub'] !== '' ? '<small>' . $e($r['sub']) . '</small>' : '') . '</span>'
                . '<span class="cb-pill cb-pill-' . $e($r['stage'][0]) . '">' . $e($r['stage'][1]) . '</span>'
                . '<span class="cb-c">' . $e($r['where']) . (!empty($p['means']) ? ' <small class="cb-means">' . $e($p['means']) . '</small>' : '') . '</span>'
                . '<span class="cb-k' . ($r['runs'][0] === '—' ? ' cb-none0' : '') . '"><b>' . $e($r['runs'][0]) . '</b>' . ($r['runs'][1] !== '' ? '<small class="' . ($r['runs'][2] ? 'cb-good' : '') . '">' . $e($r['runs'][1]) . '</small>' : '') . '</span>'
                . '<span class="cb-k' . ($r['speed'][0] === '—' ? ' cb-none0' : '') . '"><b>' . $e($r['speed'][0]) . '</b>' . ($r['speed'][1] !== '' ? '<small>' . $e($r['speed'][1]) . '</small>' : '') . '</span>'
                . '<span class="cb-k cb-okr"><b>' . $e($r['ok']) . '</b></span>'
                . '<span class="cb-ra">' . tv_cb_controls($id, $p, $res, $btn, $canPf, (string) $whyPf, $canRun && !$staging, $wNo, true) . '</span></summary>'
                . '<div class="cb-b cb-l-' . $e($p['light']) . '">'
                . ($focus === '' ? '<p class="cb-own"><a class="ak-b cb-xs" href="/admin/config/control/' . $e(str_replace(':', '-', $id)) . '" data-peek-frame>Its own page</a> <small class="ak-note">its alarms, logbook, trend and report in one place</small></p>' : '');
            // Route
            if ($p['route']) $h .= tv_cb_kv('Route', '<code class="cb-route">' . $e((string) $p['route']['said']) . '</code>');
            if ($mine) $h .= tv_cb_kv('Alarms', implode('<br>', array_map(fn ($x) => tv_cb_mark($x['level'] === 'trip' ? 'bad' : 'warn') . ' ' . '<a class="underline" href="#cb-alarm-' . (int) $x['id'] . '" title="Its alarm, with its fix">' . $e($x['title']) . '</a>' . ' <small>(' . ControlAlarms::KINDS[$x['kind']] . ', ×' . (int) $x['n_times'] . ', since ' . tv_cb_time((int) strtotime($x['first_at'] . ' UTC')) . ')</small>', $mine)));
            // Its check: one line, what holds it first; its parts on a toggle
            $h .= tv_cb_kv('Trend', '<div class="cb-trend" data-cb-trend="' . $e($id) . '"><small class="ak-note">Loads when the row opens.</small></div>');   // the historian (0171 §6, 4b)
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
            if (!empty($c['last_error'])) { $le = $c['last_error'];
                $h .= tv_cb_kv('Last error', '<b class="cb-bad">' . $e('/api/' . $le['endpoint'] . ($le['code'] >= 500 ? ' answered ' . $le['code'] : ' set a record aside')) . ':</b> ' . $e((string) preg_replace('/^answered \d+: /', '', $le['said'])) . ' <small>' . tv_cb_time($le['at']) . '</small>'); }
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
        if ($sk === 'site' && $siteNeeds && $focus === '') {
            $sn = array_count_values(array_column($siteNeeds, 1));
            $sl = !empty($sn['bad']) ? 'bad' : (!empty($sn['warn']) ? 'warn' : (!empty($sn['ok']) ? 'ok' : 'wait'));
            $h .= '<details class="cb-p" id="cb-p-site-needs" data-cb-p="site:needs" data-q="' . $e(mb_strtolower('what the site needs ' . implode(' ', array_map(fn ($x) => $x[0] . ' ' . implode(' ', array_column($x[2], 0)), $siteNeeds)))) . '">'
                . '<summary class="cb-row"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($sl) . '<span class="cb-nm"><b class="cb-n">What the site needs from outside</b><small>The site\'s own needs</small></span><span class="cb-pill cb-pill-' . $sl . '">' . ($sl === 'bad' ? 'Needs a look' : 'Working') . '</span><span class="cb-c">' . count($siteNeeds) . ' needs: ' . (int) ($sn['ok'] ?? 0) . ' answered first, ' . (int) ($sn['warn'] ?? 0) . ' on a backup or their fallback, ' . (int) ($sn['bad'] ?? 0) . ' with nothing working. None of them holds the engine.</span><span class="cb-k"><b>—</b></span><span class="cb-k"><b>—</b></span><span class="cb-k cb-okr"><b>—</b></span><span class="cb-ra"></span></summary>'
                . '<div class="cb-b cb-l-' . $sl . '">' . tv_cb_kv('Needs', tv_cb_needs($siteNeeds, $canPf, (string) $whyPf)) . '</div></details>';
        }
        $h .= '</details>';
    }

    if ($focus === 'wf:W0' && is_file(__DIR__ . '/../lib/crawler/Frontier.php')) {   // the sites that blocked W0, each with Reactivate: the source's rule on its process page (decisions/0176 §5; 0175 §6, the board is the one place to act; Jason, 2026-11-20)
        try {
            require_once __DIR__ . '/../lib/crawler/Frontier.php'; require_once __DIR__ . '/../lib/EngineCase.php'; require_once __DIR__ . '/Access.php';
            $held = Frontier::heldSites($pdo, 100);
            [$canR, $whyR] = Access::can('insights.crawler.edit', ['min' => 'staff', 'depts' => ['ops']]);   // Reactivate's own permission (Operations::pulseDo)
            $hr = '';
            foreach ($held as $r) $hr .= '<li id="' . $e(ControlAlarms::heldAnchor((string) $r['host'])) . '"><div><b>' . $e((string) $r['host']) . '</b> <small>' . $e(EngineCase::blockReason((string) $r['held_why'])) . ', since ' . tv_cb_time((int) strtotime((string) $r['held_at'])) . '</small></div>'
                . '<div><small>' . $e(mb_strimwidth((string) $r['held_why'], 0, 200, '…')) . '</small></div>'
                . '<div class="cb-acts">' . $btn('opsdo', 'Reactivate', (bool) $canR, (string) $whyR, 'ops:w0:held|ops_w0_reactivate:' . (string) $r['host'], ['class' => 'ak-b cb-xs go', 'data-confirm' => 'Reactivate ' . $r['host'] . '? One polite try when W0 next reads it; still blocked, it\'s held again.', 'data-yes' => 'Reactivate']) . '</div></li>';
            $h .= '<details class="ak-card tv-bleed cb-panel" id="cb-held" data-copy="Sites that blocked us"' . ($held ? ' open' : '') . '><summary class="cb-ph"><span class="cb-caret" aria-hidden="true">▾</span>' . tv_cb_mark($held ? 'hold' : 'ok') . '<b>Sites that blocked us</b> <small>' . ($held ? count($held) . ' held' : 'none') . '</small></summary>'
                . ($held ? '<ul class="cb-steps">' . $hr . '</ul>' : '<p data-tv-status="ok" data-tv-label="None:">No site has blocked the indexer.</p>')
                . '<p class="ak-note">A site that blocks us (a sign-in wall, 403, 406 or 451, a challenge) is never retried by itself (decisions/0164 §1.2, 5). Reactivate gives it one polite try.</p></details>';
        } catch (Throwable $x) { error_log('W0 held sites: ' . $x->getMessage()); }
    }
    if ($focus !== '') {   // its data (Jason, 2026-11-19: "How do I see what data the workflow is creating"): the records its calls touched
        require_once __DIR__ . '/kit-table.php';
        $ref = str_replace(':', '-', $focus); $idx = '';
        if ($focus === 'wf:W0' && is_file(__DIR__ . '/../lib/crawler/Frontier.php')) {   // W0's own data is its index: every address it logged
            try { require_once __DIR__ . '/../lib/crawler/Frontier.php'; $sc = []; foreach ($pdo->query('SELECT state, COUNT(*) FROM ' . Database::t('crawl_frontier') . ' GROUP BY state')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) $sc[] = number_format((int) $v) . ' ' . mb_strtolower(explode(' (', Frontier::STATES[$k] ?? $k)[0]);
                $idx = '<p class="ak-note">Its index: ' . $e(implode(' · ', $sc)) . '. <a class="underline" href="/admin/insights/index" data-peek-frame>Every address it logged</a></p>'; } catch (Throwable $x) {}
        }
        $h .= '<section class="ak-card tv-bleed cb-panel" id="its-data" data-copy="Its data">'   // one heading: the table's own
            . tv_table(['tool' => 'config/proc_records', 'src' => '/admin/api/config/proc_records?proc=' . rawurlencode($ref), 'title' => 'Its data', 'open' => '',
                'columns' => [['label', 'Record', 'name'], ['what', 'What happened', 'pill'], ['call', 'Its call', 'text'], ['at', 'When', 'when']],
                'filters' => [['what', 'Show', [['', 'Everything'], ['stored', 'Stored'], ['updated', 'Updated'], ['review', 'Sent to Review'], ['refused', 'Refused']]]], 'countBy' => 'what',
                'noData' => 'nothing yet']) . '<p class="ak-note">Every record its calls created or changed, newest first: which record and what happened, never its contents.</p>' . $idx . '</section>';
    }
    if ($focus !== '') {   // its logbook and its report, then the board's style and scripts
        $h .= '<section class="ak-card tv-bleed cb-panel" id="log" data-copy="Logbook"><h3 class="cb-title" style="margin:.5rem .8rem">Its logbook</h3><ul class="cb-lgl" data-cb-loglist>' . tv_cb_log_rows($pdo, $focus) . '</ul></section>'
            . '<p class="cb-acts" style="margin:.6rem 0"><a class="ak-b" href="/admin/config/control?report=' . $e(rawurlencode($focus)) . '&amp;range=day">Its report</a><a class="ak-b" href="/admin/config/control?report=' . $e(rawurlencode($focus)) . '&amp;range=week">Its week</a></p>';
    } else {
    // ---- the pre-flight, every line (its own section stays: /admin/config/preflight)
    $h .= '<details class="ak-card tv-bleed" id="preflight" data-copy="Pre-flight" style="margin-top:.6rem"><summary class="cb-sh"><span class="cb-caret" aria-hidden="true">▾</span><span></span><h3 class="cb-title">Pre-flight: every line, as tested last</h3></summary>'
        . '<p class="ak-note">Each line as tested last, with its Test; Run all, the runs and the report are <a class="underline" href="#cb-tab-pfpage">below</a>.</p>'
        . (function_exists('tv_admin_mod_preflight') ? tv_admin_mod_preflight($pdo) : '') . '</details>';
    $h .= '<div class="cb-none" hidden><p data-tv-status="info" data-tv-label="Nothing found:">No process, need or source has those words. Clear the box to see them all.</p></div></div></div></div>';   // cb-none, cb-main, cb-shell, #cb
    }

    require_once __DIR__ . '/../lib/Thread.php';
    $h .= Thread::css();   // the threads' look with the board's own, never left to the first Thread::html of the request (it may be a Pulse or email copy, thrown away)
    $h .= tv_cb_view_assets() . '<style>'
        // the opening view (step3/board/index.html: .qbar, .shell, .rail, .seg; 2026-11-23), on the site's tokens
        . '#cb .cb-qbar{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;padding:.5rem .6rem;margin:0 0 .5rem;background:var(--tv-card-b,#F6F0E4);border:1px solid var(--tv-line,#E5D9C3);border-radius:.5rem}'
        . '#cb .cb-pop{position:relative;display:inline-block}#cb .cb-pop>summary{list-style:none;cursor:pointer}#cb .cb-pop>summary::-webkit-details-marker{display:none}'
        . '#cb .cb-qgs{display:flex;gap:.4rem;align-items:center;border:1px solid var(--tv-line,#E5D9C3);background:var(--tv-card-a,#FFFBF2);border-radius:.5rem;padding:.3rem .6rem;font-size:12.5px;color:var(--tv-ink,#231D17)}'
        . '#cb .cb-qgs b,#cb .cb-qj b{font-size:11.5px;border-radius:999px;padding:0 .45rem;color:#fff;background:var(--tv-meta,#6b5d49);white-space:nowrap}'
        . '#cb .cb-qg.r .cb-qgs b,#cb .cb-qj.r b{background:var(--tv-fail,#B3261E)}#cb .cb-qg.a .cb-qgs b,#cb .cb-qj.a b{background:var(--tv-amber,#B45309)}#cb .cb-qg.g .cb-qgs b{background:#3d6b52}'
        . '#cb .cb-popb{position:absolute;z-index:40;left:0;top:110%;width:max-content;min-width:240px;max-width:min(380px,calc(100vw - 24px));max-height:60vh;overflow:auto;background:var(--tv-card-a,#FFFBF2);border:1px solid var(--tv-line,#E5D9C3);border-radius:.5rem;padding:.5rem;box-shadow:0 6px 18px #231D1733;display:flex;flex-direction:column;gap:.3rem}'
        . '#cb .cb-px{align-self:flex-end;border:0;background:none;font-size:18px;line-height:1;cursor:pointer;color:var(--tv-meta,#6b5d49)}'
        . '#cb .cb-qj{display:flex;gap:.45rem;align-items:center;justify-content:space-between;width:100%;text-align:left;border:1px solid var(--tv-line,#E5D9C3);background:var(--tv-card-a,#FFFBF2);border-radius:.5rem;padding:.3rem .55rem;font:inherit;font-size:12.5px;cursor:pointer;color:var(--tv-ink,#231D17);text-decoration:none}'
        . '#cb .cb-qj span{flex:1 1 auto;min-width:0;overflow-wrap:anywhere}#cb .cb-qj b{flex:none}#cb .cb-qj:hover{border-color:var(--tv-amber,#B45309)}#cb .cb-qall{font-weight:600}#cb .cb-qlink{border:0;background:none;text-decoration:underline;cursor:pointer;font-size:12.5px;color:var(--tv-link,#92400E)}'
        . '#cb .cb-shell{display:grid;grid-template-columns:190px minmax(0,1fr);gap:0 .8rem}#cb .cb-main{min-width:0}'
        . '#cb .cb-rail{position:sticky;top:.5rem;align-self:start;display:flex;flex-direction:column;gap:.2rem;padding:.4rem 0}'
        . '#cb .cb-rail>button,#cb .cb-rail>a{display:flex;gap:.5rem;align-items:center;width:100%;text-align:left;border:0;background:none;padding:.45rem .55rem;border-radius:.45rem;font:inherit;font-size:14px;color:var(--tv-ink,#231D17);cursor:pointer;text-decoration:none}'
        . '#cb .cb-rail>[aria-current="page"]{background:var(--tv-card-a,#FFFBF2);box-shadow:0 0 0 1px var(--tv-line,#E5D9C3);font-weight:600}#cb .cb-rail>*:hover{background:var(--tv-card-a,#FFFBF2)}'
        . '#cb .cb-ric{flex:none}#cb .cb-rcnt{margin-left:auto;font-size:12px;color:var(--tv-meta,#6b5d49);border:1px solid var(--tv-line,#E5D9C3);border-radius:999px;padding:0 .45rem}'
        . '#cb .cb-vh{font:700 1.15rem var(--tv-font-head,inherit);margin:.2rem 0 .4rem}#cb .cb-landh{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between}'
        . '#cb .cb-landh{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}'
        . '@media (max-width:767px){#cb .cb-shell{grid-template-columns:minmax(0,1fr)}#cb .cb-rail{position:static;flex-direction:row;flex-wrap:wrap;gap:.2rem;padding:.2rem 0}#cb .cb-rail>button,#cb .cb-rail>a{width:auto;flex:0 0 auto;padding:.35rem .5rem}#cb .cb-rcnt{margin-left:.3rem}'
        . '#cb .cb-qbar{padding:.4rem;gap:.3rem}#cb .cb-popb{position:fixed;left:8px;right:8px;top:auto;width:auto;max-width:none;min-width:0}}'
        // one Alerts dropdown and, on phones, the pages' ☰ beside it (Jason, 2026-11-24): one line, never a wrapped row of chips
        . '#cb .cb-qbar{flex-wrap:nowrap}#cb .cb-alerts>summary{min-height:40px}#cb .cb-alw{font-weight:700}#cb .cb-als{color:var(--tv-meta,#6b5d49);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}'
        . '#cb .cb-alerts.r>summary{border-color:var(--tv-fail,#B3261E)}#cb .cb-alerts.r .cb-qgs>b{background:var(--tv-fail,#B3261E)}#cb .cb-alerts.a .cb-qgs>b{background:var(--tv-amber,#B45309)}#cb .cb-alerts.g .cb-qgs>b{background:#3d6b52}'
        . '#cb .cb-alerts>.cb-popb{min-width:min(420px,calc(100vw - 24px));gap:.5rem}#cb .cb-ag{display:flex;flex-direction:column;gap:.3rem}#cb .cb-ag+.cb-ag{border-top:1px solid var(--tv-line,#E5D9C3);padding-top:.5rem}'
        . '#cb .cb-agh{display:flex;align-items:center;gap:.4rem;margin:0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tv-meta,#6b5d49)}#cb .cb-agh b{font-size:11.5px;border-radius:999px;padding:0 .45rem;color:#fff;background:var(--tv-meta,#6b5d49);letter-spacing:0}'
        . '#cb .cb-boxh{display:flex;align-items:flex-start;gap:.4rem}#cb .cb-boxh>span{flex:1 1 auto;min-width:0}#cb .cb-boxh>.tv-copy-b{float:none;margin:0;flex:none}#cb .cb-agn{margin:0;font-size:12px;color:var(--tv-meta,#6b5d49)}#cb .cb-ag.r .cb-agh b{background:var(--tv-fail,#B3261E)}#cb .cb-ag.a .cb-agh b{background:var(--tv-amber,#B45309)}'
        . '#cb .cb-pmenu{display:none}#cb .cb-hb{font-size:16px;line-height:1}#cb .cb-alc{margin-left:auto;color:var(--tv-meta,#6b5d49)}'
        . '#cb .cb-pml{gap:.15rem}#cb .cb-pml>button:not(.cb-px),#cb .cb-pml>a{display:flex;gap:.5rem;align-items:center;width:100%;min-height:40px;text-align:left;border:0;background:none;padding:.45rem .55rem;border-radius:.45rem;font:inherit;font-size:15px;color:var(--tv-ink,#231D17);cursor:pointer;text-decoration:none}'
        . '#cb .cb-pml>[aria-current="page"]{background:var(--tv-card-b,#F6F0E4);box-shadow:inset 3px 0 0 var(--tv-amber,#B45309);font-weight:600}#cb .cb-pml .cb-rcnt{margin-left:auto}'
        . '@media (min-width:768px){#cb .cb-qbar{background:none;border:0;padding:0}}'
        . '@media (max-width:767px){#cb .cb-rail{display:none}#cb .cb-pmenu{display:block;flex:none}#cb .cb-pmenu>summary,#cb .cb-alerts>summary{box-sizing:border-box;height:42px;min-height:0}#cb .cb-alerts{display:block;flex:1 1 auto;min-width:0}#cb .cb-alerts>summary{width:100%;box-sizing:border-box}#cb .cb-alw,#cb .cb-alerts .cb-qgs>b,#cb .cb-alc{flex:none}#cb .cb-alerts>.cb-popb{min-width:0}}'
        . '#cb .cb-m{display:inline-flex;width:1.35rem;height:1.35rem;border-radius:.3rem;align-items:center;justify-content:center;flex:0 0 auto;vertical-align:middle}#cb .cb-svg{width:.9rem;height:.9rem;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}'
        . '#cb summary{list-style:none;cursor:pointer}#cb summary::-webkit-details-marker{display:none}#cb details:not([open])>summary .cb-caret{display:inline-block;transform:rotate(-90deg)}#cb .cb-caret{color:#8a7a68;font-size:12px}'
        . '#cb .cb-strip{display:flex;flex-wrap:wrap;gap:.4rem .9rem;align-items:center;padding:.45rem .7rem;border:1px solid #E5D9C3;border-radius:.5rem;background:#F6F0E4;font-size:13px}#cb .cb-paused{flex:1 1 30rem;margin:0;min-width:0}#cb .cb-pl{margin:.2rem 0 0 1.1rem;padding:0}.cb-health{margin:0 0 .3rem}.cb-pn{margin:0}.cb-pg{margin:.3rem 0 0}#cb .cb-pl li{margin:.1rem 0;overflow-wrap:anywhere}#cb .cb-plm summary{cursor:pointer;font-weight:600}#cb .cb-pfres{display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0;font-size:13px}#cb .cb-pfres:empty{display:none}#cb .cb-tabs2{margin-top:.3rem}#cb .cb-st2 .cb-stw{display:none}#cb .cb-tabp{margin:.6rem 0;min-width:0}#cb .cb-lazyf{width:100%;border:0;min-height:12rem;display:block}#cb .cb-steps{list-style:none;margin:.3rem 0;padding:0;display:grid;gap:.4rem}#cb .cb-steps>li{border:1px solid #E5D9C3;border-radius:.5rem;padding:.45rem .6rem;min-width:0;overflow-wrap:anywhere}#cb .cb-steps p{margin:.25rem 0}'
        . '#cb .cb-wt{display:block;flex-basis:100%;margin-top:.15rem;font-size:.8rem;color:var(--tv-muted,#6B5B4B)}#cb .cb-beacon{display:inline-flex;gap:.4rem;align-items:center;font-weight:700;padding:.15rem .55rem .15rem .2rem;border-radius:.4rem}#cb .cb-b-ok{background:#E8F3EC;color:#15803D}#cb{--cb-fail:var(--tv-fail,#a62b2b);--cb-hold:var(--tv-hold,#8f5600);--cb-fail-bg:color-mix(in srgb,var(--cb-fail) 8%,#fff);--cb-fail-line:color-mix(in srgb,var(--cb-fail) 30%,#fff);--cb-hold-bg:color-mix(in srgb,var(--cb-hold) 11%,#fff);--cb-hold-line:color-mix(in srgb,var(--cb-hold) 35%,#fff);--cb-maint:var(--tv-maint,var(--tv-river,#3B5A6B));--cb-up:var(--tv-ok,#15803D);--cb-bar:var(--tv-copper,#B45309);--cb-none:var(--tv-line,#E5D9C3);--cb-maint-bg:color-mix(in srgb,var(--cb-maint) 10%,#fff);--cb-maint-line:color-mix(in srgb,var(--cb-maint) 32%,#fff)}#cb .cb-pill-maint{background:var(--cb-maint-bg);border-color:var(--cb-maint-line);color:var(--cb-maint)}#cb .cb-b.cb-l-maint{border-left-color:var(--cb-maint-line)}#cb .cb-sys-tr{padding:.3rem .8rem 0}#cb .cb-tr{display:grid;gap:.25rem;min-width:0}#cb .cb-trb{display:flex;gap:.25rem}#cb .cb-trb .on{background:var(--tv-sage,#8FBCA8);border-color:var(--tv-sage,#8FBCA8)}#cb .cb-trs{width:100%;max-width:32rem;height:3.4rem;display:block}#cb .cb-trw{margin:0;font-size:13px}#cb .cb-tre{margin:0;font-size:12.5px;color:#6b5d4f}#cb .cb-lg{display:grid;gap:.5rem;min-width:0}#cb .cb-lg>*{min-width:0;max-width:100%}#cb .cb-lgl{list-style:none;margin:0;padding:0;display:grid;gap:.3rem}#cb .cb-lgl li{display:flex;flex-wrap:wrap;gap:.2rem .55rem;align-items:baseline;padding:.3rem .5rem;border:1px solid #EFE6D6;border-radius:.4rem;background:#FFFDF8;min-width:0}#cb .cb-lgl time{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:#6b5d4f;white-space:nowrap}#cb .cb-lg-w{flex:1 1 14rem;min-width:0}#cb .cb-lg-w small,#cb .cb-lg-who{color:#6b5d4f}#cb .cb-lg-more,#cb .cb-lg-end{border:0 !important;background:none !important}#cb .cb-lg-n{display:flex;flex-wrap:wrap;gap:.4rem .6rem;align-items:flex-end}#cb .cb-lg-n label{display:flex;flex-direction:column;font-size:12.5px;color:#6b5d4f;min-width:0}#cb .cb-lg-n textarea,#cb .cb-lg-n select{font:inherit;font-size:13px;border:1px solid #E5D9C3;border-radius:.35rem;padding:.25rem .4rem;background:#FFFDF8;max-width:100%}#cb .cb-lg-n .cb-why{flex:1 1 16rem}#cb .cb-trw small{color:#6b5d4f}#cb .cb-mt{display:grid;gap:.5rem}#cb .cb-mt form{display:flex;flex-wrap:wrap;gap:.4rem .6rem;align-items:flex-end}#cb .cb-mt label{display:flex;flex-direction:column;font-size:12.5px;color:#6b5d4f;min-width:0}#cb .cb-mt input,#cb .cb-mt select{font:inherit;font-size:13px;border:1px solid #E5D9C3;border-radius:.35rem;padding:.2rem .4rem;background:#FFFDF8;max-width:100%}#cb .cb-mt .cb-why{flex:1 1 14rem}#cb .cb-ml,#cb .cb-up{list-style:none;margin:0;padding:0;display:grid;gap:.35rem}#cb .cb-ml li,#cb .cb-up li{display:flex;flex-wrap:wrap;gap:.2rem .6rem;align-items:baseline;padding:.35rem .5rem;border:1px solid #EFE6D6;border-radius:.4rem;background:#FFFDF8;min-width:0}#cb .cb-ml li>span,#cb .cb-up li>span{min-width:0}#cb .cb-up .cb-when{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;min-width:9.5rem}#cb .cb-b-bad{background:var(--cb-fail-bg);color:var(--cb-fail)}#cb .cb-b-hold{background:var(--cb-hold-bg);color:var(--cb-hold)}#cb .cb-b-off{background:#F1EDE6;color:#6b5d4f}'
        . '#cb .cb-core{display:inline-flex;gap:.5rem;align-items:center;flex-wrap:wrap}#cb .cb-ch{display:inline-flex;gap:.25rem;align-items:center}#cb .cb-core .cb-m,#cb .cb-beacon .cb-m{width:1.1rem;height:1.1rem}#cb .cb-last{color:#6b5d4f;margin-left:auto}#cb .cb-means{display:block;color:#6b5d4f;font-size:12px}#cb .cb-result{display:flex;flex-wrap:wrap;gap:.3rem .6rem;align-items:center;padding:.25rem .5rem;border-radius:.4rem;flex:1 1 30rem}#cb .cb-result .cb-rt{flex:1 1 16rem}#cb .cb-afl{display:flex;gap:.5rem;align-items:center;margin:.2rem 0 .4rem}#cb .cb-dd select{font:inherit;font-size:13px;border:1px solid #E5D9C3;border-radius:.35rem;padding:.15rem .4rem;background:#FFFDF8}#cb .cb-am{display:grid;gap:.15rem;font-size:13px;margin-top:.2rem}#cb .cb-panels{grid-template-columns:1fr}'
        . '#cb .cb-stages{display:grid;grid-template-columns:repeat(6,1fr);gap:.35rem;margin:.6rem 0 .4rem}#cb .cb-st{border:1px solid #E5D9C3;border-radius:.4rem;padding:.4rem .55rem;font-size:13px;background:#FFFBF2}#cb .cb-st small{display:block;color:#8a7a68;font-size:11px}#cb button.cb-st{display:block;width:100%;text-align:left;color:inherit;font:inherit;cursor:pointer}#cb button.cb-st:hover{border-color:#B45309}#cb button.cb-st[aria-selected=true]{background:#92400E;border-color:#92400E;color:#FFFBF2}#cb button.cb-st[aria-selected=true] small,#cb button.cb-st[aria-selected=true] .cb-stw{color:#F7EFDC}#cb .cb-offtab{display:none!important}@media (min-width:768px){#cb-systems td.ak-tn{width:22%;min-width:12rem}#cb-systems td[data-col=where]{min-width:16rem;width:34%}#cb-systems td[data-col=light],#cb-systems td[data-col=stage]{white-space:nowrap}}#cb .cb-lgfl{width:min(24rem,calc(100vw - 2.5rem));padding:.3rem}#cb .cb-lgfl button.cb-lgh.on,#cb .cb-lgfl button.cb-lgp.on,#cb .cb-lgfl button.on small{color:#FFFBF2}#cb .cb-lgf{display:block;position:sticky;top:-.3rem;background:var(--tv-card-a,#FFFBF2);padding:.2rem 0 .3rem;z-index:1}#cb .cb-lgf .ak-q{width:100%}#cb .cb-lgs{display:block;border-top:1px solid #EFE6D6;margin-top:.25rem;padding-top:.25rem}#cb .cb-lgfl button.cb-lgh,#cb .cb-lght{display:block;width:100%;font-weight:700;color:#92400E}#cb .cb-lgh small{font-weight:400;color:#8a7a68;margin-left:.3rem}#cb .cb-lght{padding:.35rem .6rem;font-size:.85rem}#cb .cb-lgfl button.cb-lgp{display:flex;justify-content:space-between;align-items:center;gap:.5rem;width:100%;padding-left:1.4rem;font-weight:500}#cb .cb-lgp.quiet{color:#8a7a68}#cb .cb-lgp i{font-style:normal;font-size:.75rem;background:#F7EFDC;border-radius:1rem;padding:0 .45rem;color:#5C4632}#cb .cb-stw{display:block;color:#8a7a68;font-size:11px;line-height:1.3;margin-top:.15rem}'
        . '#cb .cb-st.on{background:#E8F3EC;border-color:#8FBCA8;box-shadow:0 0 6px #8FBCA8;font-weight:600}#cb .cb-st.soon{opacity:.55}'
        . '#cb .cb-bar{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.4rem;margin:.5rem 0}#cb .cb-acts{display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;margin:.4rem 0}#cb .cb-bar .cb-acts{margin:0}#cb .cb-find .ak-q{width:15rem;max-width:100%}'
        . '#cb .cb-danger{color:var(--cb-fail);border-color:var(--cb-fail-line)}#cb .cb-xs{padding:.15rem .5rem;font-size:12px;min-height:0}'
        . '#cb .cb-panels{display:grid;grid-template-columns:1fr;gap:.6rem;margin:.4rem 0 .6rem}#cb .cb-panel{padding:.6rem .8rem;margin:0}#cb .cb-ph{font-size:14px;display:flex;gap:.45rem;align-items:center;flex-wrap:wrap}#cb .cb-panel[open]>.cb-ph{margin:0 0 .4rem;padding-bottom:.3rem;border-bottom:1px solid #EFE6D6}#cb .cb-panels{align-items:start}#cb .cb-ph small{font-weight:400;color:#8a7a68;margin-left:.3rem}'
        . '#cb .cb-hl{list-style:none;margin:0;padding:0;display:grid;gap:.4rem}#cb .cb-hl li{display:flex;gap:.45rem;align-items:flex-start;font-size:13.5px}'
        . '#cb .cb-tt{width:100%;border-collapse:collapse;font-size:13px}#cb .cb-tt th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#8a7a68;padding:.2rem .3rem;border-bottom:1px solid #EFE6D6}#cb .cb-tt td{padding:.3rem;border-bottom:1px solid #F3ECE0;vertical-align:top}#cb .cb-tt small{color:#8a7a68}#cb .cb-num{white-space:nowrap;font-variant-numeric:tabular-nums}'
        . '#cb .cb-sys{margin:.0 0 .6rem;padding:0;overflow:hidden}#cb .cb-sh{display:flex;gap:.55rem;align-items:center;padding:.55rem .8rem;background:#F6F0E4}#cb .cb-title{margin:0;font-size:1.05rem;font-weight:700}#cb .cb-sum{margin-left:auto;color:#6b5d4f;text-align:right}#cb .cb-lf{background:none;border:0;padding:.1rem .15rem;color:inherit;font:inherit;text-decoration:underline;text-underline-offset:2px;cursor:pointer;border-radius:.3rem}#cb .cb-lf.on{background:#8FBCA8;color:#1f2a24;text-decoration:none}#cb .cb-lfon{margin:.3rem .8rem;font-size:13px;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}'
        . '#cb .cb-row{display:grid;grid-template-columns:.9rem 1.35rem minmax(8rem,13rem) 6.6rem minmax(0,1fr) 9.5rem 6.5rem 3.6rem 10rem;gap:.2rem .7rem;align-items:center;padding:.5rem .8rem}#cb .cb-nm{display:flex;flex-direction:column}#cb .cb-nm small{color:#8a7a68;font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}#cb .cb-pill{justify-self:start;font-size:11.5px;font-family:inherit;padding:.12rem .45rem;border-radius:.3rem;border:1px solid;white-space:nowrap}#cb .cb-pill-ok{background:#E8F3EC;border-color:#B6D9C4;color:#14532D}#cb .cb-pill-bad{background:var(--cb-fail-bg);border-color:var(--cb-fail-line);color:var(--cb-fail)}#cb .cb-pill-hold{background:var(--cb-hold-bg);border-color:var(--cb-hold-line);color:var(--cb-hold)}#cb .cb-pill-off,#cb .cb-pill-new,#cb .cb-pill-warn,#cb .cb-pill-wait{background:#F6F0E4;border-color:#E5D9C3;color:#6b5d4f}#cb .cb-c{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}#cb .cb-k{display:flex;flex-direction:column;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;font-variant-numeric:tabular-nums}#cb .cb-k b{font-weight:600}#cb .cb-k small{font-size:11.5px;color:#6b5d4f}#cb .cb-k small.cb-good{color:#15803D}#cb .cb-ra{display:flex;gap:.3rem;justify-content:flex-end}'
        . '#cb .cb-head{padding:.35rem .8rem;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8a7a68;border-bottom:1px solid #EFE6D6;background:#FFFBF2}'
        . '#cb .cb-p{border-top:1px solid #EFE6D6}#cb .cb-p:first-of-type{border-top:0}#cb .cb-p>summary:hover{background:#FBF6EC}#cb .cb-c{font-size:13.5px;color:#4a3f35}#cb .cb-nc{color:#6b5d4f;white-space:nowrap}'
        . '#cb .cb-b{margin:0 .8rem .7rem 2.9rem;padding:.5rem .8rem;border-left:3px solid #8FBCA8;display:grid;gap:.4rem;background:#FAF5EA;border-radius:0 .4rem .4rem 0}#cb .cb-l-bad{border-left-color:var(--cb-fail)}#cb .cb-l-hold{border-left-color:var(--cb-hold-line)}#cb .cb-l-warn{border-left-color:var(--cb-hold-line)}#cb .cb-l-off,#cb .cb-l-wait,#cb .cb-l-na{border-left-color:#D6CBB8}'
        . '#cb .cb-kv{display:flex;flex-wrap:wrap;gap:.2rem .5rem;align-items:baseline;font-size:13.5px}#cb .cb-kd{flex:1 1 20rem;min-width:0}#cb .cb-lb{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:#6b5d4f;white-space:nowrap}#cb .cb-chips{display:flex;flex-wrap:wrap;gap:.35rem}#cb .cb-chip{font:inherit;font-size:13px;padding:.2rem .55rem;border-radius:.35rem;border:1px solid #E5D9C3;background:#FFFDF8;color:#2b241d;cursor:pointer;text-align:left}#cb .cb-chip small{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;color:#6b5d4f}#cb .cb-chip-ok b,#cb .cb-chip-warn b{color:#15803D}#cb .cb-chip-bad{border-color:var(--cb-hold-line);background:var(--cb-hold-bg)}#cb .cb-chip-bad b{color:var(--cb-hold)}#cb .cb-chip-warn{border-color:var(--cb-hold-line)}#cb .cb-chip:hover{border-color:#B45309}#cb .cb-more>summary{font-size:12.5px;color:#92400E;text-decoration:underline;margin-top:.15rem}#cb .cb-rec{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;color:#15803D}'
        . '#cb .cb-route{font-size:12.5px;background:#FFFBF2;border:1px solid #EFE6D6;border-radius:.3rem;padding:.15rem .45rem;display:inline-block;white-space:normal}'
        . '#cb .cb-parts{list-style:none;margin:.3rem 0 0;padding:0;display:grid;gap:.3rem}#cb .cb-parts li{display:flex;gap:.45rem;align-items:flex-start}#cb .cb-parts .cb-m{width:1.15rem;height:1.15rem;margin-top:.1rem}#cb .cb-alarms{margin:.4rem 0}#cb .cb-al{list-style:none;margin:.3rem 0 0;padding:0;display:grid;gap:.5rem}#cb .cb-al li{display:flex;gap:.5rem;align-items:flex-start;padding:.45rem .55rem;border:1px solid #EFE6D6;border-radius:.4rem;background:#FFFDF8}#cb .cb-al li.cb-al-trip{border-color:var(--cb-fail-line);background:var(--cb-fail-bg)}#cb .cb-al li.cb-al-warning{border-color:var(--cb-hold-line)}#cb .cb-al li.cb-al-grp{display:flex}#cb .cb-grp{flex:1 1 auto;min-width:0}#cb .cb-grp>summary{cursor:pointer;list-style:none}#cb .cb-grp>summary::-webkit-details-marker{display:none}#cb .cb-grp[open]>summary .cb-caret{transform:rotate(180deg)}#cb .cb-al-in{margin:.5rem 0 0}#cb .cb-al-in li{background:#fff}#cb .cb-al .cb-acts{margin:.3rem 0 0}#cb .cb-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;color:#6b5d4f}#cb .cb-b>*,#cb .cb-kd,#cb .cb-al li>div,#cb .cb-am>span{min-width:0}#cb .cb-al li>div{flex:1 1 auto}#cb .cb-ck{display:flex;gap:.35rem;align-items:flex-start;min-width:0}#cb .cb-ck>.cb-m{flex:none}#cb .cb-ck>.cb-tg,#cb .cb-ck>.cb-ckt{flex:1 1 auto;min-width:0}#cb .cb-tg{display:flex;gap:.3rem;align-items:flex-start;min-width:0;max-width:100%}#cb .cb-tgb{flex:none;display:inline-flex;align-items:center;justify-content:center;width:1.2rem;height:1.2rem;margin-top:.12rem;padding:0;border:1px solid #E5D9C3;border-radius:50%;background:#FFFDF8;color:#B45309;cursor:pointer;transition:transform .15s}#cb .cb-tgb:hover{border-color:#B45309}#cb .cb-tgt{flex:1 1 auto;min-width:0;overflow:hidden;white-space:nowrap;cursor:pointer;-webkit-mask-image:linear-gradient(90deg,#000 80%,transparent);mask-image:linear-gradient(90deg,#000 80%,transparent)}#cb .cb-tg.open .cb-tgb{transform:rotate(90deg)}#cb .cb-tg.open .cb-tgt{white-space:normal;-webkit-mask-image:none;mask-image:none}#cb .cb-old{display:block;color:#6b5d4f}#cb a{color:#92400E}#cb a.ak-b{color:inherit}#cb .cb-good{color:#15803D}#cb .cb-bad{color:var(--cb-fail)}#cb .cb-acct{font-variant-numeric:tabular-nums}#cb .cb-run>summary{font-size:13px;color:#4a3f35}'
        . '#cb .cb-al,#cb .cb-al li,#cb .cb-al li>*,#cb .cb-grp,#cb .cb-grp>summary,#cb .cb-am,#cb .cb-row>*,#cb .cb-c,#cb a,#cb .cb-lgl li,#cb .cb-lgl li>*{min-width:0;overflow-wrap:anywhere}@media (max-width:767px){#cb .cb-panels>*{min-width:0}#cb .cb-tt{table-layout:fixed}#cb .cb-tt th,#cb .cb-tt td{overflow-wrap:anywhere}#cb #cb-systems td,#cb #cb-systems .akt-sub{overflow-wrap:anywhere}#cb .cb-panel code{word-break:break-all}}@media (max-width:900px){#cb .cb-panels{grid-template-columns:1fr}#cb .cb-stages{grid-template-columns:repeat(3,1fr)}}@media (max-width:600px){#cb .cb-stages{grid-template-columns:repeat(2,1fr)}#cb .cb-row{grid-template-columns:.9rem 1.35rem minmax(0,1fr) auto auto auto}#cb .cb-c{grid-column:3/-1;order:9}#cb .cb-head{display:none}}'
        . '@media (max-width:767px){#cb .cb-row{grid-template-columns:.9rem 1.35rem minmax(0,1fr) auto;padding:.5rem .6rem;row-gap:.3rem}#cb .cb-pill{grid-column:4;grid-row:1;justify-self:end}#cb .cb-c,#cb .cb-k,#cb .cb-ra{grid-column:3/-1}#cb .cb-c{order:0}#cb .cb-k{flex-direction:row;gap:.4rem;flex-wrap:wrap}#cb .cb-okr{display:none}#cb .cb-none0{display:none}#cb .cb-ra{justify-content:flex-start}#cb .cb-head{display:none}#cb .cb-b{margin:0 .4rem .6rem .4rem}#cb .cb-sh{flex-wrap:wrap;padding:.5rem .6rem}#cb .cb-sum{margin-left:0;flex-basis:100%;text-align:left}#cb .cb-last{margin-left:0}#cb .cb-find .ak-q{flex:1 1 100%;width:auto}}'
        . '#cb .cb-rsum{margin:.4rem .8rem;padding-left:1.1rem;font-size:13.5px}#cb .cb-rf{display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin:.3rem .8rem}#cb .cb-rf label{display:grid;gap:.15rem;font-size:12.5px;min-width:0;max-width:100%}#cb .cb-rf select{max-width:100%}#cb #reports .ak-note{margin:.3rem .8rem .6rem}'
        . '</style>'
        . '<script src="/js/tv-time.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/tv-time.js') . '" defer></script>'
        . '<script src="/js/admin-control.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-control.js') . '" defer></script>';
    return $h;
}
