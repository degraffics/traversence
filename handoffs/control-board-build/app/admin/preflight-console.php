<?php
// app/admin/preflight-console.php — System Configuration → Pre-flight (decisions/0163 §10's note of 2026-11-16; Jason,
// 2026-11-17). A queue log: one line per check in the order they run, each with its mark (a green ✓ ready, a red ✗ not
// ready, a spinner while it runs, a grey dash when not needed yet), its name, required or optional for launch, and when it
// last ran. A line opens (▾) to what was tested, the service's own answer, and its fix: a button where the site can fix it,
// the URL where Jason must. Groups fold the same way, each with its count; the count at the top opens the first red line.
// Run all goes line by line (js/admin-preflight.js through /admin/api/config/preflight); each line has its own Test.
// The tests live in app/lib/Preflight.php. Linked from Start the system and from Outside services.
declare(strict_types=1);

require_once __DIR__ . '/../lib/Preflight.php';
if (!function_exists('tv_w_e')) require_once __DIR__ . '/widgets.php';   // also drawn by /admin/api/config/preflight
if (!function_exists('tv_icon')) require_once __DIR__ . '/../ui/app-shell.php';

/** The marks: the brand's icons, never emoji. */
function tv_preflight_mark(string $s, string $id = ''): string
{
    $icon = ['ok' => 'check', 'warn' => 'check', 'bad' => 'x', 'na' => 'dash', 'wait' => 'clock', 'new' => 'dash'][$s] ?? 'dash';   // warn: on a backup (amber)
    return '<span class="pf-m pf-' . tv_w_e($s) . '" title="' . tv_w_e(Preflight::markOf($id, $s)) . '">' . tv_icon($icon, 'pf-svg') . '</span>';
}

function tv_preflight_when(int $ts): string
{
    return $ts ? '<time datetime="' . gmdate('c', $ts) . '" data-tv-time>' . gmdate('M j, g:i A', $ts) . ' UTC</time>' : '<small>not run yet</small>';
}

/** One line, drawn (the page, and each answer from the endpoint). */
function tv_preflight_line(PDO $pdo, string $id, ?array $r = null, ?array $line = null): string
{
    $line = $line ?? (Preflight::lines($pdo)[$id] ?? null);
    if (!$line) return '';
    $r = $r ?? Preflight::result($pdo, $id);
    $s = (string) ($r['s'] ?? 'new');
    [$can, $why] = Preflight::can();
    $dis = $can ? '' : ' disabled title="' . tv_w_e($why) . '"';
    $anchor = 'pf-' . str_replace(':', '-', $id);
    $ms = (int) ($r['ms'] ?? 0); $slow = $r && $ms > Preflight::slowMs($pdo);   // slow: its time in amber, to come back to (Jason, 2026-11-17)
    $h = '<li class="pf-l" id="' . tv_w_e($anchor) . '" data-pf="' . tv_w_e($id) . '" data-s="' . tv_w_e($s) . '"' . ($slow ? ' data-slow="' . $ms . '"' : '') . '><details' . ($s === 'bad' ? ' data-pf-red' : '') . '><summary>'
       . tv_preflight_mark($s, $id) . '<span class="pf-n" title="' . tv_w_e($line[1]) . '">' . tv_w_e($line[1]) . '</span>'
       . '<span class="pf-t ' . ($line[2] ? 'pf-req' : '') . '">' . ($line[2] ? 'Needed for launch' : 'Optional') . '</span>'
       . ($slow ? '<span class="pf-slow" title="Slow: this check took ' . tv_w_e(Preflight::took(null, $ms)) . '">' . tv_w_e(Preflight::took(null, $ms)) . '</span>' : '')
       . '<span class="pf-w">' . tv_preflight_when((int) ($r['at'] ?? 0)) . '</span>'
       . '<button type="button" class="ak-b pf-go" data-pf-run' . $dis . '>Test</button></summary><div class="pf-b">';
    if (!$r) $h .= '<p class="ak-note">Not run on this page yet: press Test, or Run all. Earlier runs are in the run log at the foot of the page, each with its report.</p>';
    else {
        $h .= '<p data-tv-status="' . ($s === 'ok' ? 'ok' : ($s === 'bad' ? 'bad' : 'info')) . '" data-tv-label="' . tv_w_e(Preflight::markOf($id, $s)) . ':">' . tv_w_e((string) $r['said']) . '</p>';
        if (($r['tested'] ?? '') !== '') $h .= '<p class="ak-note"><b>What was tested:</b> ' . tv_w_e((string) $r['tested']) . ' (' . (int) $r['ms'] . ' ms' . (($r['by'] ?? '') !== '' ? ', run by ' . tv_w_e((string) $r['by']) : '') . ').</p>';
        if (!$line[2] && $s === 'bad') $h .= '<p class="ak-note">Optional for launch: a red line here holds only what it\'s for.</p>';
        $fx = '';
        foreach ((array) ($r['fix'] ?? []) as $f) {
            if ($f[0] === 'do') $fx .= '<button type="button" class="ak-b go" data-pf-fix="' . tv_w_e((string) $f[1]) . '"' . $dis . '>' . tv_w_e((string) $f[2]) . '</button> ';
            elseif ($f[0] === 'url') $fx .= '<a class="ak-b" href="' . tv_w_e((string) $f[2]) . '" target="_blank" rel="noopener">' . tv_w_e((string) $f[1]) . '</a> ';
            elseif ($f[0] === 'tool') $fx .= '<a class="ak-b" href="' . tv_w_e((string) $f[2]) . '">' . tv_w_e((string) $f[1]) . '</a> ';
        }
        if ($line[3] !== '' && $line[0] === 'services') $fx .= '<a class="ak-b" href="/admin/config/register#svc-' . tv_w_e($line[3]) . '">Its card on the Service register</a> ';
        if ($fx !== '') $h .= '<p class="pf-fx">' . $fx . '</p>';
        $hist = array_slice((array) ($r['hist'] ?? []), -10);
        if (count($hist) > 1) $h .= '<p class="ak-note">Its last runs: ' . implode(' ', array_map(fn ($x) => tv_preflight_mark((string) $x[1]) . (isset($x[2]) ? '<small>' . tv_w_e(Preflight::took(null, (int) $x[2])) . '</small>' : ''), $hist)) . '</p>';
    }
    return $h . '</div></details></li>';
}

/** The counts at the top: "41 ✓ · 3 ✗", the ✗ opening the first red line. */
function tv_preflight_counts(PDO $pdo): string
{
    $n = Preflight::summary($pdo);
    $first = $n['bad_ids'] ? 'pf-' . str_replace(':', '-', (string) array_key_first($n['bad_ids'])) : '';
    return '<span class="pf-c">' . tv_preflight_mark('ok') . (int) $n['ok'] . '</span> · '
        . ($n['bad'] ? '<a class="pf-c" href="#' . tv_w_e($first) . '" data-pf-first>' . tv_preflight_mark('bad') . (int) $n['bad'] . '</a>' : '<span class="pf-c">' . tv_preflight_mark('bad') . '0</span>')
        . ' · <span class="pf-c">' . tv_preflight_mark('na') . (int) $n['na'] . ' not needed yet</span>' . ($n['wait'] ? ' · ' . (int) $n['wait'] . ' waiting' : '') . ($n['new'] ? ' · ' . (int) $n['new'] . ' not run yet' : '')
        . ($n['req_bad'] ? ' <span class="pf-t pf-req">' . count($n['req_bad']) . ' needed for launch</span>' : '')
        . ($n['slow'] ? ' · <span class="pf-slow">' . count($n['slow']) . ' slow</span>' : '')
        . '<br><small>Last full run: ' . ($n['start'] ? tv_preflight_when($n['start']) . ' to ' . tv_preflight_when((int) $n['at']) . ', ' . tv_w_e(Preflight::took($n['secs'])) : tv_preflight_when((int) $n['at'])) . ($n['by'] !== '' ? ' (' . tv_w_e($n['by']) . ')' : '') . '</small>';
}

/** The run log (Jason, 2026-11-17): one line per full run, newest first, each with its report as it stood then. */
function tv_preflight_log(PDO $pdo): string
{
    $runs = Preflight::runs($pdo);
    if (!$runs) return '<p class="ak-note">No full run logged yet: Run all, the nightly run and a .env change each add one here, with its report.</p>';
    $h = '<ol class="pf-runlog">';
    foreach ($runs as $r) {
        $c = $r['counts'];
        $st = (string) ($c['status'] ?? 'Finished');   // every run, partial ones included (Jason, 2026-11-17)
        $h .= '<li><span class="pf-rs' . (str_starts_with($st, 'Finished') ? '' : ' pf-slow') . '">' . tv_w_e($st) . '</span>'
            . '<span class="pf-rw">Started ' . tv_preflight_when($r['start']) . ' · ' . (str_starts_with($st, 'Running') ? 'last update ' : 'ended ') . tv_preflight_when($r['end']) . ($r['secs'] !== null ? ' <small>(' . tv_w_e(Preflight::took($r['secs'])) . ')</small>' : '') . '</span>'
            . '<span class="pf-rb">' . tv_w_e($r['by']) . '</span>'
            . '<span class="pf-c">' . tv_preflight_mark('ok') . (int) ($c['ok'] ?? 0) . ' ' . tv_preflight_mark('bad') . (int) ($c['bad'] ?? 0) . ' ' . tv_preflight_mark('na') . (int) ($c['na'] ?? 0)
            . (!empty($c['req_bad']) ? ' <span class="pf-t pf-req">' . (int) $c['req_bad'] . ' needed for launch</span>' : '') . (!empty($c['slow']) ? ' <span class="pf-slow">' . (int) $c['slow'] . ' slow</span>' : '') . '</span>'
            . '<a class="ak-b" href="/admin/api/config/preflight?action=report&amp;run=' . (int) $r['id'] . '" download>Report</a></li>';
    }
    return $h . '</ol>';
}

/**
 * Run all on the server (2026-11-17): what the page shows of it. The run's state, how far, the line being tested, who
 * started it and when, in words; the lines finished from $from on, drawn; when it has ended, the counts and the run log.
 */
function tv_preflight_live(PDO $pdo, int $from): array
{
    $l = Preflight::liveLines($pdo);
    if ($l['state'] === 'none') return ['state' => 'none'];
    $who = $l['by'] !== '' ? ', started by ' . $l['by'] : '';
    $when = $l['start'] ? ' at ' . gmdate('g:i A', $l['start']) . ' UTC' : '';
    $text = ['running' => $l['i'] < $l['total'] ? 'Testing ' . min($l['total'], $l['i'] + 1) . ' of ' . $l['total'] . ($l['current'] !== '' ? ': ' . $l['current'] : '') . $who . $when . '. It carries on if you leave this page.'
                                                 : 'Asking ' . $l['waiting'] . ' waiting line' . ($l['waiting'] === 1 ? '' : 's') . ' again (try ' . $l['round'] . ' of 5)' . $who . $when . '.',
             'interrupted' => (string) $l['note'], 'done' => '', 'cancelled' => ''][$l['state']] ?? '';
    $html = [];
    $res = Preflight::results($pdo); $lines = Preflight::lines($pdo);
    for ($k = max(0, $from); $k < min($l['i'], count($l['ids'])); $k++) { $id = (string) $l['ids'][$k]; if (isset($lines[$id])) $html[$id] = tv_preflight_line($pdo, $id, $res[$id] ?? [], $lines[$id]); }
    if ($l['round'] > 0) foreach ($l['ids'] as $id) if (($res[$id]['s'] ?? '') === 'wait' && isset($lines[$id])) $html[$id] = tv_preflight_line($pdo, (string) $id, $res[$id], $lines[$id]);   // asked again
    $end = in_array($l['state'], ['done', 'cancelled', 'interrupted'], true);
    return ['state' => $l['state'], 'start' => (int) $l['start'], 'i' => $l['i'], 'total' => $l['total'], 'text' => $text, 'note' => $l['note'], 'lines' => $html,
            'current' => $l['state'] === 'running' && $l['i'] < $l['total'] ? (string) ($l['ids'][$l['i']] ?? '') : '',
            'counts' => $end ? tv_preflight_counts($pdo) : '', 'log' => $end ? tv_preflight_log($pdo) : ''];
}

/**
 * The pre-flight page, or one group of it on its own ($only): Check before switching on, drawn on the Workflow board under
 * Start the system (Jason, 2026-11-17: "act like the preflight queue"), with its latest answers, Run the check (a run of that
 * group alone, on the server), its filters and the run log. One engine: the same lines, run and results as the pre-flight.
 */
function tv_admin_mod_preflight(PDO $pdo, string $only = ''): string
{
    [$can, $why] = Preflight::can();
    $csrf = (string) ($_SESSION['csrf_token'] ?? '');
    $only = isset(Preflight::GROUPS[$only]) ? $only : '';
    $lines = Preflight::lines($pdo); $res = [];   // the page opens fresh (Jason, 2026-11-17): earlier runs are in the run log, each with its report
    if ($only !== '') { $lines = array_filter($lines, fn ($l) => $l[0] === $only); $all0 = Preflight::results($pdo); foreach ($lines as $id => $l) if (isset($all0[$id])) $res[$id] = $all0[$id]; }   // one group: its latest answers (the check is read each time the board opens)
    // except while a run is going on the server (2026-11-17): the lines it has done so far, the rest queued, its progress
    $live = Preflight::liveLines($pdo); $going = in_array($live['state'], ['running', 'interrupted'], true);   // interrupted: the page asks Continue or Cancel
    if ($going && $only === '') { $all = Preflight::results($pdo); foreach (array_slice($live['ids'], 0, $live['i']) as $id) if (isset($all[$id])) $res[$id] = $all[$id]; }
    $liveNow = $going ? tv_preflight_live($pdo, $live['i']) : null;
    $css = '<style>
#pf .pf-log{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:minmax(0,1fr);gap:2px}
#pf .pf-l{min-width:0}
#pf .pf-l details>summary{display:flex;align-items:center;gap:.5rem;padding:.2rem .5rem;min-height:2rem;cursor:pointer;list-style:none;border-radius:.4rem;background:#fff;border:1px solid #eadfce}
#pf .pf-l details>summary::-webkit-details-marker{display:none}
#pf .pf-l details>summary::after{content:"▾";color:#92400E;margin-left:.2rem}
#pf .pf-l details[open]>summary::after{content:"▴"}
#pf .pf-n{flex:1 1 0;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#pf .pf-l summary>.tv-note,#pf .pf-l summary>.tv-ask{display:none}
#pf .pf-w{font-size:.8rem;color:#6b5a48;white-space:nowrap}
#pf .pf-slow{font-size:.75rem;font-weight:600;color:#B45309;background:#FEF3C7;border-radius:1rem;padding:.05rem .45rem;white-space:nowrap}
#pf .pf-t{font-size:.72rem;padding:.05rem .4rem;border-radius:1rem;border:1px solid #d6c7b2;color:#6b5a48;white-space:nowrap}
#pf .pf-t.pf-req{border-color:#92400E;color:#92400E}
#pf .pf-b{padding:.3rem .6rem .45rem 2.1rem;font-size:.9rem}
#pf .pf-b>p,#pf .pf-b>.tv-note{margin:.2rem 0}
#pf .pf-go{padding:.1rem .5rem;font-size:.8rem}
#pf .pf-m{display:inline-flex;width:1.35rem;height:1.35rem;border-radius:50%;align-items:center;justify-content:center;flex:0 0 auto}
#pf .pf-svg{width:.9rem;height:.9rem}
#pf .pf-ok{background:#DCFCE7;color:#15803D}
#pf .pf-bad{background:#FEE2E2;color:#B91C1C}
#pf .pf-na,#pf .pf-new{background:#F1EDE6;color:#8a7a68}
#pf .pf-wait{background:#FEF3C7;color:#B45309}
#pf .pf-warn{background:#FEF3C7;color:#B45309;box-shadow:inset 0 0 0 1.5px #F59E0B}
#pf .pf-l[data-busy] .pf-m{background:#F1EDE6;color:transparent;border:2px solid #B45309;border-top-color:transparent;animation:pf-spin .8s linear infinite}
@keyframes pf-spin{to{transform:rotate(360deg)}}
#pf .pf-g{margin:.6rem 0}
#pf .pf-runs>summary{font-weight:600;color:#92400E;cursor:pointer;margin:.3rem 0}
#pf .pf-runlog{list-style:none;margin:0;padding:0;display:grid;gap:2px;max-height:16rem;overflow:auto}
#pf .pf-runlog li{display:flex;flex-wrap:wrap;align-items:center;gap:.3rem .7rem;padding:.25rem .5rem;border:1px solid #eadfce;border-radius:.4rem;background:#fff;font-size:.85rem}
#pf .pf-runlog .pf-rb{color:#6b5a48;flex:1;min-width:8rem}#pf .pf-runlog .pf-rs{font-weight:600;font-size:.8rem}#pf .pf-runlog .pf-m{width:1.1rem;height:1.1rem}
#pf .pf-prog{margin:.2rem 0;font-size:.85rem;font-weight:600;color:#B45309}
#pf .pf-g>summary{font-weight:600;color:#92400E;cursor:pointer;padding:.3rem 0}
#pf .pf-c{display:inline-flex;align-items:center;gap:.25rem}
#pf .pf-fx{display:flex;flex-wrap:wrap;gap:.35rem}
@media (max-width:767px){#pf .pf-w,#pf .pf-t{display:none}#pf .pf-b{padding-left:.6rem}}
</style>';
    $h = $css . '<div id="pf" data-csrf="' . tv_w_e($csrf) . '"' . ($only !== '' ? ' data-pf-group="' . tv_w_e($only) . '"' : '') . ($going ? ' data-pf-live="' . tv_w_e($live['state']) . '" data-pf-from="' . (int) $live['i'] . '"' : '') . '><div class="ak-card tv-bleed">'
        . ($only !== '' ? '<p class="ak-note">' . tv_w_e(Preflight::GROUPS[$only]) . '. Each line is the switch-on check\'s, as Start the system reads it: ✓ or ✗, opening to its answer and fix. It\'s read each time this page opens and at each Start; Run the check tests every line again, on the server, one by one, and logs the run. Lines that hold everything are needed for launch; the rest hold only their part. The same lines are a group of the <a class="underline" href="/admin/config/preflight#pf-g-switchon">Pre-flight</a>.</p>' : '')
        . ($only !== '' ? '' : '<h3 class="ak-h" style="margin-top:0">Pre-flight</h3>')
        . ($only !== '' ? '' : '<p class="ak-note">Proves every service, setting, workflow and task works before <a href="/admin/config/automation#start">Start the system</a>, and diagnoses while it runs. Each test is real, free and harmless: a read-only call, a sign-in without sending, a known test value. It never spends money, never sends to a real person, never writes anyone\'s data, and never starts, pauses or changes anything. It runs again by itself each night and after a .env change; red lines become one Pulse case. Each company\'s line is its card\'s Test on <a href="/admin/config/register">the Service register</a>.</p>')
        . '<p data-pf-counts><span class="pf-c">' . tv_preflight_mark('ok') . '0</span> · <span class="pf-c">' . tv_preflight_mark('bad') . '0</span> · <span class="pf-c">' . tv_preflight_mark('na') . '0 not needed yet</span> · ' . count($lines) . ' to test</p>'
        . (function () use ($pdo) {   // the page opens fresh (Jason, 2026-11-17) and says the last full run beside it, so the board's
            // strip and this agree (words follow the state; the session manager, 2026-11-19): both truths, the report a tap away
            try { $n = Preflight::summary($pdo); } catch (Throwable $x) { return ''; }
            if (empty($n['at'])) return '';
            $names = array_values($n['bad_ids']); $k = count($names);
            return '<p data-tv-status="' . ($k ? 'info' : 'ok') . '" data-tv-label="Not run in this view yet:">Last full run ' . tv_preflight_when((int) $n['at']) . ': '
                . ($k ? $k . ' not ready (' . tv_w_e(implode(', ', array_slice($names, 0, 3))) . ($k > 3 ? ' and ' . ($k - 3) . ' more' : '') . ')' : 'every line passed or is on a backup')
                . '. <a class="underline" href="#pf-runs">Its report is in the run log</a>; Test or Run all shows each line here.</p>';
        })()
        . '<p class="pf-prog" data-pf-prog' . ($liveNow && $liveNow['text'] !== '' ? '' : ' hidden') . ' aria-live="polite">' . tv_w_e($liveNow['text'] ?? '') . '</p>'   // during Run all: "Testing 34 of 87: OpenStreetMap, started by …"
        . '<p><button type="button" class="ak-b go" data-pf-all' . ($can ? '' : ' disabled title="' . tv_w_e($why) . '"') . '>' . ($only !== '' ? 'Run the check' : 'Run all') . '</button> '
        . '<button type="button" class="ak-b" data-pf-red>Show only what\'s red</button> '
        . '<button type="button" class="ak-b" data-pf-slow>Show the slow ones</button> '
        . '</p>'
        . ($can ? '' : '<p data-tv-status="info" data-tv-label="Read only:">' . tv_w_e($why) . ' to run tests.</p>') . '</div>';
    foreach (Preflight::GROUPS as $g => $label) {
        if ($only !== '' && $g !== $only) continue;
        $ids = array_keys(array_filter($lines, fn ($l) => $l[0] === $g));
        if (!$ids) continue;
        $c = ['ok' => 0, 'bad' => 0, 'na' => 0];
        foreach ($ids as $id) { $s = $res[$id]['s'] ?? 'new'; if (isset($c[$s])) $c[$s]++; }
        $h .= '<details class="pf-g ak-card tv-bleed" id="pf-g-' . tv_w_e($g) . '" open><summary>' . tv_w_e($label) . ' <small class="pf-c" data-pf-gc>' . tv_preflight_mark('ok') . $c['ok'] . ' ' . tv_preflight_mark('bad') . $c['bad'] . ' ' . tv_preflight_mark('na') . $c['na'] . '</small></summary><ol class="pf-log">';
        foreach ($ids as $id) $h .= tv_preflight_line($pdo, $id, $res[$id] ?? [], $lines[$id]);   // [] = not run on this page yet (or not yet in this run)
        $h .= '</ol></details>';
    }
    // the run log at the foot of the page, under the checklist (Jason, 2026-11-17)
    $h .= '<details class="pf-runs ak-card tv-bleed" id="pf-runs" open><summary>Run log</summary><div data-pf-runs>' . tv_preflight_log($pdo) . '</div></details>';
    return $h . '</div><script src="/js/admin-preflight.js?v=' . (int) @filemtime(__DIR__ . '/../../website/js/admin-preflight.js') . '" defer></script>';
}
