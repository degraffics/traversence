<?php
// app/admin/operations-console.php — System Configuration → Service register, Costs, and Workflow board (decisions/0163
// §1–§5, §10; Release A). The register: every outside service, what uses it, where its key lives (named, never shown),
// its caps and paces (the one setting the code reads, with who and when, history and Reset), this month's use and cost,
// and its status with the reason; watching Railway and the sync (shipped off). Costs: this month by service and by
// workflow, against revenue and the community budget, with the AI cap's shares. The board: each workflow's switch, last
// run, output, cost, and its settings (tasks, shares, routes, budgets, gates, weights, retry ladder, person steps) saved
// as versions with "Go back to it"; the switch-on check. It shows and records: nothing here switches anything on.
declare(strict_types=1);

/** Who's acting and the posted form, shared by the three sections. [can, why, uid, who, csrf, tz, flash]. */
function tv_ops_ctx(PDO $pdo): array
{
    require_once __DIR__ . '/../lib/Operations.php';
    require_once __DIR__ . '/../lib/WorkflowSettings.php';
    [$can, $why] = Operations::can();
    $me = Access::staff(); $uid = (int) (Auth::currentUserId() ?? 0);
    $who = trim((string) ($me['name'] ?? ''));
    if ($who === '' && $uid) { try { $st = $pdo->prepare('SELECT display_name FROM users WHERE id = ?'); $st->execute([$uid]); $who = (string) ($st->fetchColumn() ?: ''); } catch (Throwable $x) {} }
    $csrf = (string) ($_SESSION['csrf_token'] ?? '');
    $tz = mb_substr(preg_replace('/[^A-Za-z0-9_\/+-]/', '', (string) ($_POST['tz'] ?? '')), 0, 64);
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['op_do']) && hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        try {
            $do = (string) $_POST['op_do']; $ref = (string) ($_POST['ref'] ?? '');
            // a service's Pause and Activate on its card (decisions/0173 §2): Automation's own switch and permission; Activate
            // passes 0163 §10's switch-on check first, as Automation's Resume does
            // Retire and Bring it back (decisions/0173 §2): Automation's permission, as Pause; Propose: the register's
            if (in_array($do, ['svc_retire', 'svc_bringback'], true)) {
                [$canAu, $whyAu] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
                if (!$canAu) throw new RuntimeException($whyAu);
                require_once __DIR__ . '/../lib/ServiceStages.php';
                $say = $do === 'svc_retire' ? ServiceStages::retire($pdo, $ref, (string) ($_POST['why'] ?? ''), (string) ($_POST['replaced'] ?? ''), $uid, $who) : ServiceStages::bringBack($pdo, $ref, $uid, $who);
                return [$can, $why, $uid, $who, $csrf, $tz, '<p data-tv-flash="ok">' . tv_w_e($say) . '</p>'];
            }
            if (in_array($do, ['prop_add', 'prop_drop', 'prop_back'], true)) {
                if (!$can) throw new RuntimeException($why);
                require_once __DIR__ . '/../lib/ServiceStages.php';
                if ($do === 'prop_add') { ServiceStages::propose($pdo, (string) ($_POST['name'] ?? ''), (string) ($_POST['link'] ?? ''), (string) ($_POST['why'] ?? ''), $uid, $who); $say = 'Proposed: it\'s on the register as a build request. Nothing calls it until a release adds it.'; }
                else { ServiceStages::setProposal($pdo, (int) $ref, $do === 'prop_drop', $who); $say = $do === 'prop_drop' ? 'Dropped: Propose again brings it back.' : 'Proposed again.'; }
                return [$can, $why, $uid, $who, $csrf, $tz, '<p data-tv-flash="ok">' . tv_w_e($say) . '</p>'];
            }
            if ($do === 'svc_pause' || $do === 'svc_activate') {
                [$canAu, $whyAu] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
                if (!$canAu) throw new RuntimeException($whyAu);
                $name = OutsideServices::COMPANIES[$ref][0] ?? $ref;
                if ($do === 'svc_activate') {
                    $red = Operations::blocking($pdo, $ref);
                    if ($red) throw new RuntimeException($name . ' stays paused: the switch-on check has ' . count($red) . ' red line' . (count($red) === 1 ? '' : 's') . ' for it, first "' . $red[0]['line'] . '" (' . $red[0]['why'] . ').');
                }
                Automation::set($pdo, 'service', $ref, $do === 'svc_pause', $do === 'svc_pause' ? (string) ($_POST['why'] ?? '') : 'Activated on the Service register', $uid, $who);
                $say = $do === 'svc_pause' ? $name . ' is paused: nothing of ours calls it until it\'s activated again.' : $name . ' is active: its check passed, and its workflows can call it.';
                return [$can, $why, $uid, $who, $csrf, $tz, '<p data-tv-flash="ok">' . tv_w_e($say) . '</p>'];
            }
            // a service's key on its card (decisions/0173 §3): EnvSettings' one writer holds the guards (the Founder, never on
            // staging, never logged); Check runs the service's own pre-flight test at once, and a pass forgets the old value
            if (in_array($do, ['key_set', 'key_remove', 'key_putback', 'key_forget', 'key_check'], true)) {
                require_once __DIR__ . '/../lib/EnvSettings.php'; require_once __DIR__ . '/../lib/Preflight.php';
                $svc = (string) ($_POST['svc'] ?? ''); $name = OutsideServices::COMPANIES[$svc][0] ?? $svc;
                if (!isset(OutsideServices::COMPANIES[$svc])) throw new RuntimeException('No such outside service.');
                if ($do !== 'key_check' && !in_array($ref, array_column(Operations::KEYS[$svc] ?? [], 0), true)) throw new RuntimeException('That isn\'t one of ' . $name . '\'s keys.');
                $check = function () use ($pdo, $svc, $who): array {
                    if (!isset(Preflight::lines($pdo)['svc:' . $svc])) return ['na', 'It has no check of its own yet.'];
                    Preflight::$now = true; try { $r = Preflight::run($pdo, 'svc:' . $svc, $who); } finally { Preflight::$now = false; }
                    return [(string) ($r['s'] ?? 'wait'), mb_substr((string) ($r['said'] ?? ''), 0, 300)];
                };
                switch ($do) {
                    case 'key_set':
                        EnvSettings::setKey($ref, (string) ($_POST['value'] ?? ''));
                        [$cs, $cw] = $check();
                        if ($cs === 'ok') { EnvSettings::forgetOld($ref); $say = $ref . ' is saved and Check passed: ' . $cw; }
                        else $say = $ref . ' is saved, but Check ' . ($cs === 'bad' ? 'failed' : 'couldn\'t confirm it') . ': ' . $cw . (EnvSettings::hasOld($ref) ? ' Put the old one back undoes it.' : ' Replace it, or Remove it.');
                        $bad = $cs === 'bad'; break;
                    case 'key_remove': EnvSettings::removeKey($ref); $say = $ref . ' is taken out (kept until you Forget it, so Put the old one back still works). Nothing can call ' . $name . ' with it now.'; $bad = false; break;
                    case 'key_putback': EnvSettings::putBack($ref); [$cs, $cw] = $check(); $say = 'The old ' . $ref . ' is back. Check: ' . $cw; $bad = $cs === 'bad'; break;
                    case 'key_forget':
                        require_once __DIR__ . '/../lib/Staging.php';
                        if (Staging::here()) throw new RuntimeException('Staging never writes .env.');
                        if ((Access::who()[0] ?? '') !== 'founder') throw new RuntimeException('Keys are set by the Founder.');
                        EnvSettings::forgetOld($ref); $say = 'The old ' . $ref . ' is forgotten.'; $bad = false; break;
                    default: [$okP, $whyP] = Preflight::can(); if (!$okP) throw new RuntimeException($whyP);
                        [$cs, $cw] = $check(); $say = $name . ': ' . Preflight::markOf('svc:' . $svc, $cs) . ': ' . $cw; $bad = $cs === 'bad';
                        if ($cs === 'ok') foreach (Operations::KEYS[$svc] ?? [] as [$kk]) if (EnvSettings::hasOld($kk)) EnvSettings::forgetOld($kk);
                }
                return [$can, $why, $uid, $who, $csrf, $tz, '<p data-tv-flash="' . ($bad ? 'error' : 'ok') . '">' . tv_w_e($say) . '</p>'];
            }
            if (!$can) throw new RuntimeException($why);
            switch ($do) {
                case 'cap_set':
                    if (!in_array($ref, array_merge(...array_values(Operations::CAPS)), true)) throw new RuntimeException('That isn\'t one of the register\'s caps.');
                    $old = Thresholds::get($pdo, $ref);
                    $v = Thresholds::set($pdo, $ref, (string) ($_POST['value'] ?? ''), $uid);
                    $say = Thresholds::GATES[$ref][0] . ': ' . Operations::num($v) . ' ' . Thresholds::GATES[$ref][6] . '.';
                    // after acting, the check runs on the spot (decisions/0176 §5, A5): its service's budget looked at again now;
                    // still over, it says what came back and offers the old value back (as 0173 release 2's keys do)
                    $svcOf = ''; foreach (Operations::CAPS as $sv => $gs) if (in_array($ref, $gs, true)) { $svcOf = (string) $sv; break; }
                    try { require_once __DIR__ . '/../lib/ControlAlarms.php'; Operations::alarms($pdo); $still = $svcOf !== '' ? ControlAlarms::openOf($pdo, 'ops:cap:' . $svcOf) : null; }
                    catch (Throwable $x) { $still = null; error_log('cap_set check: ' . $x->getMessage()); }
                    if ($still) $say .= ' Checked now: still ' . (string) $still['title'] . ' (' . (string) $still['reading'] . ').' . ($old !== null ? ' Put it back to ' . Operations::num($old) . ' with Set, or Reset to its default.' : '');
                    elseif ($svcOf !== '') $say .= ' Checked now: Fixed, its budget is under its warning level.';
                    break;
                case 'cap_reset':
                    if (!in_array($ref, array_merge(...array_values(Operations::CAPS)), true)) throw new RuntimeException('That isn\'t one of the register\'s caps.');
                    Thresholds::reset($pdo, $ref, $uid); $say = Thresholds::GATES[$ref][0] . ': back to its default.'; break;
                case 'watch_on': Operations::setWatching($pdo, true, $uid); $say = 'Watching is on: the hourly job looks at Railway and the sync.'; break;
                case 'watch_off': Operations::setWatching($pdo, false, $uid); $say = 'Watching is off: nothing calls Railway or GitHub from here.'; break;
                case 'wf_save':
                    if (!isset(Automation::WORKFLOWS[$ref])) throw new RuntimeException('No such workflow.');
                    [$n, $ch, $red] = WorkflowSettings::save($pdo, $ref, WorkflowSettings::fromForm($pdo, $ref, $_POST), $uid, (string) ($_POST['note'] ?? ''), $tz);
                    $say = $ref . ' saved as version ' . $n . ' (' . count($ch) . ' change' . (count($ch) === 1 ? '' : 's') . ').' . ($red ? ' The switch-on check has ' . $red . ' red line' . ($red === 1 ? '' : 's') . ' for it, so it stays paused until they\'re green.' : ''); break;
                case 'wf_back':
                    if (!isset(Automation::WORKFLOWS[$ref])) throw new RuntimeException('No such workflow.');
                    [$n] = WorkflowSettings::restore($pdo, $ref, (int) ($_POST['version'] ?? 0), $uid, $tz);
                    $say = $ref . ' is back to version ' . (int) $_POST['version'] . ', saved as version ' . $n . '.'; break;
                default: throw new RuntimeException('Unknown action.');
            }
            $flash = '<p data-tv-flash="ok">' . tv_w_e($say) . '</p>';
        } catch (Throwable $x) {
            $known = $x instanceof RuntimeException || $x instanceof InvalidArgumentException;
            if (!$known) error_log('operations: ' . $x->getMessage());
            $flash = '<p data-tv-flash="error">' . tv_w_e($known ? $x->getMessage() : 'That didn\'t work: try again.') . '</p>';
        }
    }
    return [$can, $why, $uid, $who, $csrf, $tz, $flash];
}

/** A small form: op_do, hidden fields, a button that waits for a yes when $confirm is set. */
function tv_ops_form(string $csrf, string $do, string $label, array $hidden = [], string $confirm = '', string $extra = '', bool $can = true, string $why = '', string $cls = 'ak-b'): string
{
    $h = '<form method="post" style="display:inline-flex;gap:.3rem;align-items:center;flex-wrap:wrap;margin:0"><input type="hidden" name="csrf" value="' . tv_w_e($csrf) . '"><input type="hidden" name="op_do" value="' . tv_w_e($do) . '"><input type="hidden" name="tz" value="" data-tz>';
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . tv_w_e($k) . '" value="' . tv_w_e((string) $v) . '">';
    return $h . $extra . '<button class="' . $cls . '" type="submit"' . ($confirm !== '' ? ' data-confirm="' . tv_w_e($confirm) . '" data-yes="' . tv_w_e($label) . '"' : '') . (!$can ? ' disabled title="' . tv_w_e($why) . '"' : '') . '>' . tv_w_e($label) . '</button></form>';
}

/** The device's time zone into each form (decisions: times where they happened). */
function tv_ops_tz_js(): string
{
    return '<script>(function(){var z="";try{z=Intl.DateTimeFormat().resolvedOptions().timeZone||""}catch(e){}document.querySelectorAll("input[data-tz]").forEach(function(i){i.value=z})})();</script>';
}

const TV_OPS_TONE = ['active' => 'ok', 'paced' => 'info', 'held' => 'bad', 'off' => 'info', 'out' => 'bad'];

/** System Configuration → Service register. */
function tv_admin_mod_register(PDO $pdo): string
{
    // the one home for every outside service (decisions/0173 §1): Outside services' cards (decisions/0152) with the
    // register's parts in each (0163 §1), and each service's stage (0173 §2). its old address comes here (router.php).
    [$can, $why, $uid, $who, $csrf, $tz, $flash] = tv_ops_ctx($pdo);
    $e = 'tv_w_e';
    Operations::seed($pdo);   // today's values, recorded once (nothing retyped)
    require_once __DIR__ . '/../lib/ServiceStages.php';
    $reg = Operations::register($pdo); $stages = ServiceStages::all($pdo);
    $h = $flash . '<p class="ak-note">Every outside service we call, in one place (decisions/0163 §1, 0173): what it\'s for and its stage, what uses it, where its key lives (named, never shown), its caps and paces, this month\'s use and cost by our count, whether it\'s working, its settings, its sign-in and who to ask. Each row opens to its card. Watching Railway and the sync is on the <a class="underline" href="/admin/config/control#cb-watch">System Control Board</a>.</p>';
    // the summary: by stage, then by status
    $by = []; foreach ($stages as [$sg]) $by[$sg] = ($by[$sg] ?? 0) + 1;
    $h .= '<div class="ak-metrics">';
    foreach (['active', 'paused', 'keyin', 'added'] as $sg) $h .= '<div class="ak-metric"><b>' . (int) ($by[$sg] ?? 0) . '</b><span>' . $e(ServiceStages::STAGES[$sg][0]) . '</span></div>';
    $h .= '<div class="ak-metric"><b>' . (count(ServiceStages::records()) + (int) ($by['retired'] ?? 0)) . '</b><span>Retired</span></div>';
    $sb = []; foreach ($reg as $r) $sb[$r['status'][0]] = ($sb[$r['status'][0]] ?? 0) + 1;
    foreach (['held', 'out'] as $k) if (!empty($sb[$k])) $h .= '<div class="ak-metric"><b>' . (int) $sb[$k] . '</b><span>' . $e(Operations::STATUSES[$k]) . '</span></div>';
    $h .= '</div>';
    // the Costs page, folded in whole at the top (decisions/0175's note "one control board in the menu": Modules' cards hold
    // their "caps, budget and cost (the Pre-flight and Costs pages today)"; release 2b part B): the month against revenue,
    // the community budget, by service, by workflow, the AI cap's shares and a hub's opening budget, nothing left out; its
    // old address (/admin/config/costs) opens it here (#costs)
    // a cost that can't be read says so in its fold, and the cards below still draw
    try { $cm = Operations::costs($pdo); $cs = 'Costs this month: $' . number_format((float) $cm['total'], 2) . ' spent, ' . ($cm['covered'] ? 'within' : 'past') . ' last month\'s revenue'; $cb = tv_admin_mod_costs($pdo); }
    catch (Throwable $x) { error_log('modules costs: ' . $x->getMessage()); $cs = 'Costs this month'; $cb = '<p data-tv-status="bad" data-tv-label="Couldn\'t read the costs:">' . $e($x->getMessage()) . ' The cards below still show each service\'s own cost.</p>'; }
    $h .= '<details class="ak-card tv-bleed" id="costs" style="margin:.6rem 0"><summary style="cursor:pointer;font-weight:600">' . $e($cs) . ' ▾</summary>'
        . $cb . '</details><script' . (function_exists('tv_nonce') ? tv_nonce() : '') . '>(function(){function o(){if(location.hash==="#costs"){var d=document.getElementById("costs");if(d){d.open=true;d.scrollIntoView({block:"start"});}}}o();window.addEventListener("hashchange",o);})();</script>';
    $blocks = [];
    foreach ($reg as $k => $r) $blocks[$k] = tv_ops_reg_block($pdo, (string) $k, $r, $stages[$k] ?? ['active', ''], $csrf, $tz, $can, $why);
    require_once __DIR__ . '/../ui/outside-services.php';
    $pf = '';
    try { require_once __DIR__ . '/../lib/Preflight.php'; $n = Preflight::summary($pdo); $pf = '<p class="ak-note">Each company\'s real test is on <a href="/admin/config/preflight#pf-g-services">Pre-flight</a>' . ($n['at'] ? ' (' . (int) $n['bad'] . ' ✗ at its last run)' : '') . '.</p>'; } catch (Throwable $x) {}
    return $h . $pf . tv_outside_services($pdo, ['blocks' => $blocks, 'stages' => $stages]) . tv_ops_retired($pdo) . tv_ops_proposed($pdo, $csrf, $can, (string) $why) . tv_ops_tz_js();
}

/**
 * A service's register parts, at the top of its card (decisions/0173 §1): its stage with its buttons (Pause, Activate,
 * through Automation and 0163 §10's gate), its status with the reason, what uses it, its key (named, never shown), its
 * price, this month, what it produced, its open issue with the buttons that fix it, its caps and paces, and its history.
 */
function tv_ops_reg_block(PDO $pdo, string $k, array $r, array $stage, string $csrf, string $tz, bool $can, string $why): string
{
    $e = 'tv_w_e';
    [$st, $sw] = $r['status']; [$sg, $sgw] = $stage;
    [$canAu, $whyAu] = Access::can('config.automation', ['min' => 'executive', 'depts' => []]);
    $np = Automation::NOT_PAUSABLE[$k] ?? null;
    $b = '<div class="os-stage" id="stage-' . $e($k) . '"><p data-tv-status="' . (ServiceStages::TONE[$sg] ?? 'info') . '" data-tv-label="' . $e(ServiceStages::STAGES[$sg][0] . ':') . '">' . $e($sgw) . '</p>';
    if ($np !== null) $b .= '<p class="ak-note">Not paused from here: its calls are business operations (' . $e($np) . ').</p>';
    elseif ($sg === 'retired') $b .= tv_ops_form($csrf, 'svc_bringback', 'Bring it back', ['ref' => $k], 'Bring ' . $r['name'] . ' back? It stays paused until it\'s activated, through its check.', '', $canAu, (string) $whyAu);
    elseif (Automation::serviceOff($pdo, $k) === null) $b .= tv_ops_form($csrf, 'svc_pause', 'Pause', ['ref' => $k], '', '<input class="ak-in" name="why" maxlength="200" required placeholder="Why pause it" data-spell>', $canAu, (string) $whyAu);   // on: its way back is Pause
    else $b .= tv_ops_form($csrf, 'svc_activate', 'Activate', ['ref' => $k], 'Activate ' . $r['name'] . '? The switch-on check runs first; a red line keeps it paused, with the reason.', '', $canAu && $sg !== 'added', $sg === 'added' ? 'It needs its key first.' : (string) $whyAu, 'ak-b go');
    // Retire (decisions/0173 §2): its way back is Bring it back; folded, so it's never pressed by the way
    if ($np === null && $sg !== 'retired') $b .= '<details class="os-ed os-retire"><summary>Retire it…</summary>' . str_replace(' data-yes="Retire"', ' data-yes="Retire" data-danger', tv_ops_form($csrf, 'svc_retire', 'Retire', ['ref' => $k], 'Retire ' . $r['name'] . '? Nothing of ours calls it; its record and data are kept, and Bring it back undoes it.',
        '<input class="ak-in" name="why" maxlength="250" required placeholder="Why it\'s retired" data-spell><input class="ak-in" name="replaced" maxlength="250" placeholder="What replaced it (optional)" data-spell>', $canAu, (string) $whyAu)) . '</details>';
    $b .= '</div>';
    // its status with the reason, unless it only repeats the stage (off: no key, or switched off)
    if ($st !== 'off') $b .= '<p data-tv-status="' . (TV_OPS_TONE[$st] ?? 'info') . '" data-tv-label="' . $e(Operations::STATUSES[$st] . ':') . '">' . $e($sw) . '</p>';
    $b .= '<dl class="ak-note os-dl">'
        . '<dt>Used by</dt><dd>' . ($r['workflows'] ? $e(implode(', ', array_map(fn ($wf) => $wf . ' ' . (Automation::WORKFLOWS[$wf][0] ?? ''), $r['workflows']))) : 'No workflow: people\'s own presses and business operations') . ($r['tasks'] ? '<br><small>Tasks: ' . $e(implode(', ', $r['tasks'])) . '</small>' : '') . ($r['jobs'] ? '<br><small>Jobs: ' . $e(implode(', ', $r['jobs'])) . '</small>' : '') . '</dd>'
        . '<dt id="svc-' . $e($k) . '-key">Its key</dt><dd>' . ($r['keys'] ? tv_ops_keys($k, $r['keys'], $csrf) : 'None needed') . '</dd>'
        . '<dt>Known price</dt><dd>' . $e($r['price']) . '</dd>'
        . '<dt' . ($r['caps'] ? '' : ' id="svc-' . $e($k) . '-caps"') . '>This month</dt><dd>' . ($r['use'] ? implode('<br>', array_map(fn ($u) => $e($u['what']) . ': ' . ($u['money'] ? '$' . number_format($u['used'], 2) . ($u['cap'] > 0 ? ' of $' . number_format($u['cap'], 2) : '') : number_format($u['used']) . ($u['cap'] > 0 ? ' of ' . number_format($u['cap']) : '') . ' ' . $u['unit']), $r['use'])) . '<br><small>' . $e($r['provider']) . '</small>' : 'Nothing counted yet') . '</dd>'
        . ($r['produced'] !== '' ? '<dt>Produced</dt><dd>' . $e($r['produced']) . '</dd>' : '')
        . '</dl>';
    $b .= tv_ops_issues($pdo, $k, $csrf, $tz);   // its open issue: what failed, what it means, and the buttons that fix it here
    if ($r['caps']) {
        $b .= '<h3 class="os-h" id="svc-' . $e($k) . '-caps">Caps and paces</h3><table class="ak-aa"><thead><tr><th>Setting</th><th>Now</th><th>Set it</th><th>History</th></tr></thead><tbody>';
        foreach ($r['caps'] as $c) {
            $hist = Thresholds::history($pdo, $c['gate'], 6);
            $b .= '<tr id="cap-' . $e($c['gate']) . '"><td><b>' . $e($c['label']) . '</b><br><small>' . $e($c['where']) . '</small></td><td>' . $e(Operations::num($c['value']) . ' ' . $c['unit']) . '<br><small>' . $e($c['how']) . '</small></td><td>'
                . tv_ops_form($csrf, 'cap_set', 'Set', ['ref' => $c['gate']], '', '<input class="ak-in" style="width:7rem" type="text" inputmode="decimal" name="value" value="' . $e((string) $c['value']) . '" aria-label="' . $e($c['label']) . '">', $can, $why)
                . ' ' . tv_ops_form($csrf, 'cap_reset', 'Reset', ['ref' => $c['gate']], 'Back to its default (' . Operations::num(Thresholds::GATES[$c['gate']][2]) . ')?', '', $can, $why) . '</td><td><small>'
                . ($hist ? implode('<br>', array_map(fn ($x) => $e(($x['old_v'] ?? '–') . ' → ' . $x['new_v'] . ', ' . $x['at'] . ($x['note'] !== '' ? ' (' . $x['note'] . ')' : '')), $hist)) : 'No changes yet') . '</small></td></tr>';
        }
        $b .= '</tbody></table>';
    }
    if ($hs = ServiceStages::history($pdo, $k)) {
        $b .= '<h3 class="os-h">Its stages</h3><ul class="os-hist">';
        foreach ($hs as $x) $b .= '<li><b>' . $e(ServiceStages::STAGES[$x['stage']][0] ?? $x['stage']) . '</b> <time datetime="' . $e(str_replace(' ', 'T', (string) $x['at']) . 'Z') . '" data-tv-time data-tz="UTC">' . $e((string) $x['at']) . ' UTC</time>'
            . ((string) $x['by_name'] !== '' ? ', ' . $e((string) $x['by_name']) : '') . ((string) $x['why'] !== '' ? ': ' . $e((string) $x['why']) : '') . '</li>';
        $b .= '</ul>';
    }
    return $b;
}

/**
 * A service's keys on its card (decisions/0173 §3): each named, never shown. One in .env: Add its key (or Replace),
 * Remove the key, and while an old value is kept, Put the old one back and Forget it; each save runs Check at once. One
 * kept on Railway: where to set it, with the link, and Check (the worker reports which keys it has, never their values).
 * Greyed with the reason for anyone but the Founder, and on staging.
 */
function tv_ops_keys(string $svc, array $keys, string $csrf): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/EnvSettings.php'; require_once __DIR__ . '/../lib/Staging.php';
    $okK = (Access::who()[0] ?? '') === 'founder'; $whyK = $okK ? '' : 'Keys are set by the Founder';   // as EnvSettings' writer asks
    if (Staging::here()) { $okK = false; $whyK = 'Staging never writes .env: set it on the live site'; }
    $mine = EnvSettings::secrets(); $h = ''; $railway = false;
    foreach ($keys as [$k, $where, $has]) {
        $note = str_starts_with($where, '.env') ? (str_contains($where, '(') ? substr($where, strpos($where, '(')) : '') : 'on ' . $where;   // .env is where the card writes; Railway's says so
        $h .= '<div class="os-key" id="key-' . $e($k) . '"><code>' . $e($k) . '</code> ' . $e($note) . ': '
            . ($has === null ? '<b class="os-ks">can\'t be read from the site</b>' : ($has ? '<b class="os-ks ok">in place</b>' : '<b class="os-ks ' . (str_contains($where, '(') ? '' : 'bad') . '">missing</b>'));   // a word in the line, not a notice
        if (in_array($k, $mine, true)) {
            $old = EnvSettings::hasOld($k);
            $h .= '<div class="os-keyf">' . tv_ops_form($csrf, 'key_set', $has ? 'Replace' : 'Add its key', ['ref' => $k, 'svc' => $svc], $has ? 'Replace ' . $k . '? The old one is kept until Check passes.' : '',
                    '<input class="ak-in" type="password" name="value" autocomplete="off" spellcheck="false" required maxlength="2000" placeholder="Paste the key" aria-label="' . $e($k) . '">', $okK, (string) $whyK, $has ? 'ak-b' : 'ak-b go')
                . ($has ? ' ' . tv_ops_form($csrf, 'key_remove', 'Remove the key', ['ref' => $k, 'svc' => $svc], 'Take ' . $k . ' out of .env? Nothing can call it with this key; Put the old one back undoes it until you Forget it.', '', $okK, (string) $whyK) : '')
                . ($old ? ' ' . tv_ops_form($csrf, 'key_putback', 'Put the old one back', ['ref' => $k, 'svc' => $svc], 'Put the old ' . $k . ' back? Check runs at once.', '', $okK, (string) $whyK)
                       . ' ' . tv_ops_form($csrf, 'key_forget', 'Forget the old one', ['ref' => $k, 'svc' => $svc], 'Forget the old ' . $k . '? Put the old one back won\'t be possible after this.', '', $okK, (string) $whyK) : '')
                . '</div>';
        } elseif (str_starts_with($where, 'Railway') || str_contains($where, 'Railway')) {
            $railway = true;
            $h .= ' <a class="os-go" href="https://railway.com/project/a445f0be-b6de-46bb-a422-51ff835ca6e4/service/8d254c0d-abcb-4520-b9ae-dfbdd362b243/variables" target="_blank" rel="noopener">Set it on Railway: the crawler\'s Variables ↗</a>';
        }
        $h .= '</div>';
    }
    // Check: the service's own pre-flight test now (for Railway's keys, the worker's report of what it has, then the test)
    $h .= '<div class="os-keyf">' . tv_ops_form($csrf, 'key_check', 'Check', ['svc' => $svc], '', '', true, '') . '<small class="ak-note">' . ($railway ? 'Railway restarts the crawler when a variable changes; the crawler reports its keys (never their values) on its next run.' : 'Runs its pre-flight test with the key in place: real, free, read only.') . '</small></div>';
    return $h;
}

/**
 * The records of services with no card (decisions/0173 §4: service-defs.php's stage retired or removed): why, what replaced
 * it, when. Retired ones with a card stay in the table under the Retired filter. Nothing to press.
 */
function tv_ops_retired(PDO $pdo): string
{
    $e = 'tv_w_e'; $h = ['retired' => '', 'removed' => ''];
    foreach (ServiceStages::records() as $k => [$stage, $rec]) {
        [$name, $why, $repl, $when, $url] = array_pad((array) $rec, 5, '');
        $h[$stage] .= '<div class="os-part" id="svc-' . $e($k) . '"><p data-tv-status="info" data-tv-label="' . $e($name) . ': ' . $stage . ' ' . $e($when !== '' ? date('M j, Y', strtotime($when)) : '') . '.">' . $e($why) . ($repl !== '' ? ' Replaced by ' . $e($repl) . '.' : '') . ' '
            . ($url !== '' ? '<a class="os-go" href="' . $e($url) . '" target="_blank" rel="noopener">Why ↗</a>' : '') . '</p></div>';
    }
    return ($h['retired'] !== '' ? '<h2 class="hb-h" id="retired">Retired</h2>' . $h['retired'] : '')
        . '<h2 class="hb-h" id="removed">Removed</h2>' . ($h['removed'] !== '' ? $h['removed'] : '<p class="ak-note">None yet. A service whose code and key are gone keeps its record here (a release removes it, after Jason\'s OK).</p>');
}

/**
 * Proposed (decisions/0173 §2, 0169 §5's Choose): a service someone would like added, as a build request for a session.
 * Only words: it never adds an address, a key or a call. Drop it; Propose again is its way back.
 */
function tv_ops_proposed(PDO $pdo, string $csrf, bool $can, string $why): string
{
    $e = 'tv_w_e'; $rows = ServiceStages::proposals($pdo);
    $h = '<h2 class="hb-h" id="proposed">Proposed</h2><p class="ak-note">A service you\'d like added: its name, its site and what it would be for. It\'s a build request: nothing calls it, and it has no key or address, until a release adds it (then its card shows Added).</p>'
        . tv_ops_form($csrf, 'prop_add', 'Propose it', [], '', '<input class="ak-in" name="name" maxlength="120" required placeholder="The service\'s name" data-spell><input class="ak-in" name="link" type="url" maxlength="255" placeholder="https://… its site (optional)" spellcheck="false"><input class="ak-in" name="why" maxlength="400" required placeholder="What it would be for" data-spell>', $can, $why, 'ak-b go');
    foreach ($rows as $r) {
        $drop = $r['state'] === 'dropped';
        $h .= '<div class="os-part" id="prop-' . (int) $r['id'] . '"><p data-tv-status="info" data-tv-label="' . $e((string) $r['name']) . ($drop ? ' (dropped)' : '') . ':">' . $e((string) $r['why'])
            . ((string) $r['link'] !== '' ? ' <a class="os-go" href="' . $e((string) $r['link']) . '" target="_blank" rel="noopener">Its site ↗</a>' : '')
            . ' <small>Proposed by ' . $e((string) ($r['by_name'] ?: 'someone')) . ', <time datetime="' . $e(str_replace(' ', 'T', (string) $r['at']) . 'Z') . '" data-tv-time data-tz="UTC">' . $e((string) $r['at']) . ' UTC</time></small></p>'
            . tv_ops_form($csrf, $drop ? 'prop_back' : 'prop_drop', $drop ? 'Propose again' : 'Drop it', ['ref' => (int) $r['id']], $drop ? '' : 'Drop the proposal for ' . $r['name'] . '? Propose again brings it back.', '', $can, $why) . '</div>';
    }
    return $h;
}

/**
 * A service's open issues on its card (Jason, 2026-11-19, from an alarm's Open its card: "how am I supposed to fix this?"):
 * the card is where its alarm sends you, so it says what failed (the answer, how often, since when, from where), what that
 * means, what to do, and carries the buttons that do it, the board's own: Check and start again on a trip, else Check it
 * now (not for a 429: it waits its turn) and Mark resolved; and its alarm on the board. The answer shows beside the button.
 */
function tv_ops_issues(PDO $pdo, string $svc, string $csrf, string $tz): string
{
    require_once __DIR__ . '/../lib/ServiceHealth.php'; require_once __DIR__ . '/../lib/ControlAlarms.php';
    $e = 'tv_w_e'; $iss = array_values(array_filter(ServiceHealth::openAll($pdo), fn ($i) => (string) $i['service'] === $svc));
    if (!$iss) return '';
    $h = '';
    require_once __DIR__ . '/../lib/Thread.php';
    foreach ($iss as $i) {
        // one thread per issue, in the dispatcher's one shape, all of it on the card (decisions/0176 §2–3, A3): no × that
        // looks like it dismisses it (a passing check closes it), and no "Its alarm on the board": the thread is the alarm
        $id = (int) $i['id']; $f = ServiceHealth::facts($pdo, $i); $al = $f['alarm']; $trip = $f['trip'];
        $btns = $trip ? '<button type="button" class="ak-b go" data-ops-cb="resolve" data-ops-id="' . (int) $al['id'] . '">Check and start again</button>'
            : ($i['kind'] !== 'rate' ? '<button type="button" class="ak-b" data-ops-cb="svccheck" data-ops-id="' . $id . '">Check it now</button>' : '');   // resolved is what a passing check does, never a button (Jason, 2026-11-19)
        // its case in Pulse stays reachable (the alarm's item when it has one, else the issue's own case)
        // Fix it first (decisions/0176 §5, A5): on the card its spot is on this page, so it lands there (no panel); the
        // refusal's spot is this issue itself, where Check it now is, so there it's not drawn twice
        $fx = Thread::fixOf($f); if ($fx && str_starts_with($fx['url'], '/admin/config/register#')) $fx['url'] = substr($fx['url'], 22);
        if ($fx && $fx['url'] !== '#issue-' . $id) $btns = Thread::fixButton($fx, 'ak-b go') . $btns;
        // their own trouble: no Fix it; Pause it (its stage's Pause, above on this card) and Use backup, greyed until it's built
        if (!empty($f['theirs'])) $btns .= (Automation::NOT_PAUSABLE[$svc] ?? null) === null && Automation::serviceOff($pdo, $svc) === null ? ' <a class="ak-b" href="#stage-' . $e($svc) . '">Pause it</a>' : '';
        if (!empty($f['theirs'])) $btns .= ' <button type="button" class="ak-b" disabled title="' . $e(Thread::BACKUP_SOON) . '">Use backup</button><small class="tv-fix-soon">' . $e(Thread::BACKUP_SOON) . '.</small>';
        $btns .= ' <a class="ak-b" href="/admin/pulse/stream#case=' . rawurlencode($al ? 'alarm:' . (int) $al['id'] : 'svc:' . $id) . '" data-peek-frame>Its case in Pulse</a>';
        $h .= '<div class="ops-issue" id="issue-' . $id . '" style="border:1px solid var(--tv-line-strong);border-radius:.6rem;padding:.6rem .75rem;margin:.5rem 0;background:var(--tv-card-a);min-width:0">'
            . Thread::html($f + ['pill_class' => 'tv-th-pill tv-th-' . ($f['level'] === 'trip' ? 'trip' : 'warning')], 'card', $btns) . '</div>';
    }
    static $js = false;
    if (!$js) { $js = true;   // the board's own actions (/admin/api/config/control), busy while it works, the answer beside the button, then the page again
        $h .= '<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest("[data-ops-cb]");if(!b||b.disabled)return;'
            . 'if(window.TvAsk)TvAsk.busy(b,true);var body={action:b.getAttribute("data-ops-cb"),id:b.getAttribute("data-ops-id")};'
            . 'fetch("/admin/api/config/control",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":' . json_encode($csrf) . '},body:JSON.stringify(window.tvSafe?tvSafe(body):body)})'
            . '.then(function(r){return r.json().catch(function(){return{ok:false,error:{message:"The server didn’t answer clearly. Try again; still stuck? Write to support@traversence.com."}};});})'
            . '.then(function(j){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say(j.ok?(j.message||"Done."):((j.error&&j.error.message)||"That didn’t work."),{anchor:b,bad:!j.ok});}if(j.ok)setTimeout(function(){location.reload();},2200);})'
            . '.catch(function(){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say("That didn’t reach the site. Try again.",{anchor:b,bad:true});}});});})();</script>';
    }
    return $h;
}

/** System Configuration → Costs. */
function tv_admin_mod_costs(PDO $pdo): string
{
    [$can, $why] = tv_ops_ctx($pdo);
    $e = 'tv_w_e';
    $c = Operations::costs($pdo); $cm = $c['community'];
    $usd = fn ($v) => '$' . number_format((float) $v, 2);
    $h = '<p class="ak-note">This month\'s spend in one place (decisions/0163 §2): the crawler\'s ledger, Claude\'s (ai_jobs), email\'s and the monthly plans, by service and by workflow, set against revenue and the community budget, so "no income, no expense" is checked by the system. Our counts; failed calls included.</p>';
    $h .= '<div class="ak-metrics"><div class="ak-metric"><b>' . $usd($c['total']) . '</b><span>Spent this month</span></div><div class="ak-metric"><b>' . $usd($c['revenue']) . '</b><span>Revenue last month</span></div>'
        . '<div class="ak-metric"><b>' . $usd($cm['spent']) . ' of ' . $usd($cm['budget']) . '</b><span>Community budget (' . (int) $cm['pct'] . '%)</span></div></div>';
    $h .= $c['covered'] ? '<p data-tv-status="ok" data-tv-label="Covered:">This month\'s spend so far is within last month\'s revenue.</p>'
        : '<p data-tv-status="bad" data-tv-label="Not covered:">This month\'s spend (' . $usd($c['total']) . ') is past last month\'s revenue (' . $usd($c['revenue']) . '). Nothing new should spend until revenue covers it (no income, no expense); the AI cap and the services\' caps are in the <a class="underline" href="/admin/config/register">Service register</a>.</p>';
    $rows = '';
    foreach ($c['services'] as $k => $v) $rows .= '<tr><td>' . $e(OutsideServices::COMPANIES[$k][0] ?? $k) . '</td><td>' . $usd($v) . '</td></tr>';
    $h .= tv_admin_fold('By service', count($c['services']) . ' with spend', 'info', '', $rows ? '<table class="ak-aa"><thead><tr><th>Service</th><th>This month</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '<p class="ak-note">Nothing spent this month.</p>', true);
    $rows = '';
    ksort($c['workflows'], SORT_NATURAL);
    foreach ($c['workflows'] as $wf => $bySvc) $rows .= '<tr><td><b>' . $e($wf) . '</b> ' . $e(Automation::WORKFLOWS[$wf][0] ?? ($wf === 'run' ? 'The crawler\'s runs' : ($wf === 'email' ? 'Email' : 'Not a workflow\'s'))) . '</td><td>' . $e(implode(' · ', array_map(fn ($s, $v) => (OutsideServices::COMPANIES[$s][0] ?? $s) . ' $' . number_format($v, 2), array_keys($bySvc), $bySvc))) . '</td><td>' . $usd(array_sum($bySvc)) . '</td></tr>';
    $h .= tv_admin_fold('By workflow', count($c['workflows']) . ' with spend', 'info', '', $rows ? '<table class="ak-aa"><thead><tr><th>Workflow</th><th>Services</th><th>This month</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '<p class="ak-note">Nothing spent this month.</p>', false);
    $rows = '';
    foreach ($c['ai_shares'] as $wf => $a) $rows .= '<tr><td><b>' . $e($wf) . '</b> ' . $e(Automation::WORKFLOWS[$wf][0] ?? '') . '</td><td>' . (int) $a['share_pct'] . '% (' . $usd($a['cap_usd']) . ')</td><td>' . $usd($a['spent']) . '</td><td><span data-tv-status="' . ($a['pct'] >= 95 ? 'bad' : ($a['pct'] >= 50 ? 'info' : 'ok')) . '">' . (int) $a['pct'] . '%</span></td></tr>';
    $h .= tv_admin_fold('The AI cap\'s shares', $c['ai_shares'] ? count($c['ai_shares']) . ' set' : 'None set', 'info', '', '<p class="ak-note">The monthly AI cap split into a share per workflow (decisions/0163 §7, X4), set on the <a class="underline" href="/admin/config/board">Workflow board</a>. The 50/75/95% warnings apply to each share; a share unused at the month\'s end passes on to the next month\'s pool once the engine reads the shares (Release B). W5\'s half of the daily allowance stays its own rule.</p>'
        . ($rows ? '<table class="ak-aa"><thead><tr><th>Workflow</th><th>Its share</th><th>Spent</th><th>Used</th></tr></thead><tbody>' . $rows . '</tbody></table>' : ''), false);
    $h .= tv_admin_fold('A hub\'s opening budget', 'None set', 'info', '', '<p class="ak-note">A one-time allowance per hub, separate from upkeep (0164 R17), set when the hub is Planned. None is set yet; the switch-on check names it for a hub that opens.</p>', false);
    return $h;
}

/** System Configuration → Workflow board (and the task schedule, the switch-on check). */
function tv_admin_mod_board(PDO $pdo): string
{
    [$can, $why, $uid, $who, $csrf, $tz, $flash] = tv_ops_ctx($pdo);
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/crawler/CrawlService.php';
    $crawl = CrawlService::ready($pdo);
    $tasks = $crawl ? CrawlService::tasks($pdo) : []; $stats = $crawl ? CrawlService::taskStats($pdo, 24) : [];
    $spend = Operations::costs($pdo)['workflows'];
    $empty = Operations::empties($pdo);
    $routeChoices = array_keys(array_filter(OutsideServices::CONTROLS, fn ($c) => !empty($c['tasks'])));   // the services a crawler task can be routed to
    $h = $flash . '<p class="ak-note">Each workflow, W1 to W13 (W14 from when it\'s built): its switch, its last run and output, its cost this month, and its settings. Every setting is edited here, never only in code; each save is a version (who, when, what changed) with "Go back to it", and the switch-on check\'s red lines keep it paused until they\'re green (decisions/0163 §3, §10). Switches stay on the <a class="underline" href="/admin/config/automation">Automation</a> page. ' . ($can ? '' : '<b>' . $e($why) . '</b>') . '</p>';
    // Start the system (Jason, 2026-11-15), the same card as on the Automation page; its buttons post there
    require_once __DIR__ . '/../lib/SystemStart.php';
    [$canStart, $whyStart] = SystemStart::can();
    $h .= SystemStart::panel($pdo, $csrf, $canStart, (string) $whyStart, '/admin/config/automation');
    // the switch-on check, read each time this page opens, drawn as the pre-flight's group (Jason, 2026-11-17: "act like the
    // preflight queue"): ✓ or ✗ lines that open to their answer and fix, Run the check on the server, the filters, the run log
    $chk = Operations::switchOnCheck($pdo); $red = array_filter($chk, fn ($l) => !$l['ok']);
    $redAll = array_filter($red, fn ($l) => $l['scope'] === 'all');
    require_once __DIR__ . '/preflight-console.php';
    Preflight::recordSwitchOn($pdo, $chk, 'the Workflow board');
    // the board's interlocks (decisions/0171 §14): these lines hold what's red there too, process by process
    $h .= '<p class="ak-note">Each line also holds its process on the <a class="underline" href="/admin/config/control">System Control Board</a>, where a red one shows beside what it holds, with its alarm.</p>';
    $h .= '<div id="check">' . tv_admin_fold('Check before switching on', $red ? count($red) . ' of ' . count($chk) . ' red' . ($redAll ? ', ' . count($redAll) . ' holding everything: the master switch waits' : ': each holds only its own part') : 'All green', $redAll ? 'bad' : ($red ? 'info' : 'ok'), '', tv_admin_mod_preflight($pdo, 'switchon'), false) . '</div>';
    foreach (Automation::WORKFLOWS as $wf => [$name, $what, $where]) {
        $spec = WorkflowSettings::current($pdo, $wf);
        $off = Automation::wfOff($pdo, $wf); $master = Automation::master($pdo);
        $mine = array_filter($tasks, fn ($t) => (string) $t['wf'] === $wf);
        $last = null; foreach ($mine as $t) if ($t['last_served_at'] && (!$last || $t['last_served_at'] > $last['last_served_at'])) $last = $t;
        $cost = array_sum($spend[$wf] ?? []);
        $b = '<p class="ak-note">' . $e($what) . ' Runs on: ' . $e($where) . '.</p>';
        $b .= '<p data-tv-status="' . ($off || $master ? 'info' : 'ok') . '" data-tv-label="Switch:">' . $e($off !== null ? 'Paused: ' . $off : ($master ? 'On, held by the master switch' : 'Running')) . '</p>';
        $b .= '<p class="ak-note">Last run: ' . ($last ? $e($last['name'] . ', ' . $last['last_served_at'] . ' (server time): ' . strtolower(CrawlService::OUTCOMES[(string) $last['last_outcome']] ?? (string) $last['last_outcome']) . ((string) ($last['last_why'] ?? '') !== '' ? ' (' . $last['last_why'] . ')' : '')) : 'none on the crawler') . '. Cost this month: $' . number_format($cost, 2) . '. Where work waits, by phase (0164 §1–§6): shown from Release B.</p>';
        $emptyHere = array_filter($empty, fn ($r) => $r['wf'] === $wf);
        if ($emptyHere) $b .= '<p data-tv-status="bad" data-tv-label="Returns empty:">' . $e(implode(', ', array_column($emptyHere, 'name'))) . ': had work and got answers, but nothing came back (a Pulse case).</p>';
        // the form: every setting, one save
        $f = '<form method="post"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="op_do" value="wf_save"><input type="hidden" name="ref" value="' . $e($wf) . '"><input type="hidden" name="tz" value="" data-tz>';
        $f .= '<h4 class="ak-h">What starts it</h4><p class="ak-note">' . $e($spec['starts']['schedule']) . '</p><label class="ak-note">Its triggers (what else starts it, in words) <textarea class="ak-in" name="triggers" rows="2" data-spell>' . $e((string) $spec['starts']['triggers']) . '</textarea></label>';
        if ($spec['tasks']) {
            $f .= '<h4 class="ak-h">The task schedule</h4>' . ($spec['share_pct'] !== null ? '<label class="ak-note">Its share of a crawler run <input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="share_pct" value="' . (int) $spec['share_pct'] . '">%</label>' : '');
            $f .= '<div style="overflow-x:auto"><table class="ak-aa"><thead><tr><th>Task</th>';
            foreach (WorkflowSettings::TASK_FIELDS as [$label]) $f .= '<th>' . $e($label) . '</th>';
            $f .= '<th>Services it may use</th><th>Last 24 h</th></tr></thead><tbody>';
            foreach ($spec['tasks'] as $t => $task) {
                $st = $stats[$t] ?? [];
                $f .= '<tr><td><b>' . $e($task['name']) . '</b><br><small>' . $e($t) . ((int) ($tasks[$t]['enabled'] ?? 1) ? '' : ' · switched off') . '</small></td>';
                foreach (WorkflowSettings::TASK_FIELDS as $col => [$label, $lo, $hi]) $f .= '<td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="t[' . $e($t) . '][' . $col . ']" value="' . (int) $task[$col] . '" aria-label="' . $e($task['name'] . ': ' . $label) . '" title="' . $lo . ' to ' . $hi . '"></td>';
                $f .= '<td><input type="hidden" name="t[' . $e($t) . '][routes][]" value=""><small style="display:grid;gap:.1rem">';
                foreach ($routeChoices as $rs) $f .= '<label style="white-space:nowrap"><input type="checkbox" name="t[' . $e($t) . '][routes][]" value="' . $e($rs) . '"' . (in_array($rs, (array) $task['routes'], true) ? ' checked' : '') . '> ' . $e(OutsideServices::COMPANIES[$rs][0] ?? $rs) . '</label>';
                $f .= '</small></td>'
                    . '<td><small>' . $e(implode(', ', array_map(fn ($k, $v) => $v . ' ' . str_replace('_', ' ', $k), array_keys(array_diff_key($st, ['seconds' => 1])), array_diff_key($st, ['seconds' => 1]))) ?: 'No runs') . '</small></td></tr>';
            }
            $f .= '</tbody></table></div><p class="ak-note">A task\'s services (its routes) are read by the crawler service from Release B; until then they\'re recorded here.</p>';
        }
        $f .= '<h4 class="ak-h">Budgets</h4><label class="ak-note">Its share of the AI cap <input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="ai_share_pct" value="' . (int) ($spec['budgets']['ai_share_pct'] ?? 0) . '">%</label> '
            . '<label class="ak-note">Its monthly budget, all services <input class="ak-in" style="width:6rem" type="text" inputmode="decimal" name="month_usd" value="' . $e((string) ($spec['budgets']['month_usd'] ?? 0)) . '"> dollars (0: none of its own)</label>';
        if ($spec['gates']) {
            $f .= '<h4 class="ak-h">Its gates</h4>';
            foreach ($spec['gates'] as $g => $v) $f .= '<label class="ak-note" style="display:block">' . $e(Thresholds::GATES[$g][0]) . ' <input class="ak-in" style="width:6rem" type="text" inputmode="decimal" name="g[' . $e($g) . ']" value="' . $e((string) $v) . '"> ' . $e(Thresholds::GATES[$g][6]) . '</label>';
        }
        if ($spec['weights']) {
            $f .= '<details><summary class="ak-h" style="cursor:pointer">The collaborative score\'s weights</summary><p class="ak-note">Not a gate (decisions/0163 §12): what each independent source adds to a listing\'s confidence, once per origin. Read by the engine from Release B.</p><table class="ak-aa"><tbody>';
            foreach ($spec['weights'] as $k => $v) $f .= '<tr><td>' . $e(WorkflowSettings::WEIGHTS[$k][0] ?? $k) . '</td><td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="w[' . $e($k) . ']" value="' . (int) $v . '"></td></tr>';
            $f .= '</tbody></table></details>';
        }
        if ($spec['ladder']) {
            $f .= '<details><summary class="ak-h" style="cursor:pointer">The retry ladder</summary><p class="ak-note">For what the engine retries for itself; never a site that blocks us (that waits for a person\'s Reactivate). New evidence starts it again. Read by the engine from Release B.</p><table class="ak-aa"><thead><tr><th>After fails in a row</th><th>Next try in days</th></tr></thead><tbody>';
            foreach ($spec['ladder'] as $i => [$n, $d]) $f .= '<tr><td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="ladder[' . $i . '][0]" value="' . (int) $n . '"></td><td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="ladder[' . $i . '][1]" value="' . (int) $d . '"></td></tr>';
            $f .= '</tbody></table></details>';
        }
        $f .= '<h4 class="ak-h">Steps that need a person</h4><textarea class="ak-in" name="person" rows="3" data-spell aria-label="Steps that need a person, one a line">' . $e(implode("\n", (array) $spec['person'])) . '</textarea>';
        $f .= '<p style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-top:.5rem"><input class="ak-in" type="text" name="note" maxlength="300" data-spell placeholder="What this change is for (kept with the version)" style="flex:1;min-width:12rem">'
            . '<button class="ak-b go" type="submit" data-confirm="Save ' . $e($wf) . '\'s settings as a new version? The settings the code reads change now; nothing is switched on." data-yes="Save ' . $e($wf) . ' settings"' . (!$can ? ' disabled title="' . $e($why) . '"' : '') . '>Save ' . $e($wf) . ' settings</button></p></form>';
        $b .= $f;
        // versions
        $vs = WorkflowSettings::versions($pdo, $wf, 12);
        if ($vs) {
            $b .= '<h4 class="ak-h">Versions</h4><ul style="list-style:none;padding:0;margin:0;display:grid;gap:.35rem">';
            foreach ($vs as $i => $v) {
                $ch = json_decode((string) $v['changes'], true) ?: [];
                $b .= '<li><b>Version ' . (int) $v['version'] . '</b> · ' . $e(($v['by_name'] ?? '') !== '' ? (string) $v['by_name'] : ((int) $v['saved_by'] ? 'staff' : 'found')) . ' · <time datetime="' . $e(str_replace(' ', 'T', (string) $v['saved_at'])) . '" data-tv-time' . ((string) $v['tz'] !== '' ? ' data-tz="' . $e((string) $v['tz']) . '"' : '') . '>' . $e((string) $v['saved_at']) . '</time>'
                    . ((string) $v['note'] !== '' ? ' · ' . $e((string) $v['note']) : '') . ((int) $v['check_red'] ? ' · <span data-tv-status="info">' . (int) $v['check_red'] . ' red on the check</span>' : '')
                    . ($ch ? '<br><small>' . $e(implode('; ', array_slice($ch, 0, 6))) . (count($ch) > 6 ? '; and ' . (count($ch) - 6) . ' more' : '') . '</small>' : '')
                    . ($i > 0 ? ' ' . tv_ops_form($csrf, 'wf_back', 'Go back to it', ['ref' => $wf, 'version' => (int) $v['version']], 'Go back to version ' . (int) $v['version'] . '? It\'s saved as a new version, so nothing is lost.', '', $can, $why) : ' <small>(current)</small>') . '</li>';
            }
            $b .= '</ul>';
        }
        $h .= '<div id="wf-' . $e($wf) . '">' . tv_admin_fold($wf . ' ' . $name, ($off !== null ? 'Paused' : ($master ? 'Held by the master' : 'Running')) . ($cost > 0 ? ' · $' . number_format($cost, 2) : '') . ($vs ? ' · version ' . (int) $vs[0]['version'] : '') . ($emptyHere ? ' · returns empty' : ''), $emptyHere ? 'bad' : ($off !== null || $master ? 'info' : 'ok'), '', $b, false) . '</div>';
    }
    return $h . tv_ops_tz_js();
}

/**
 * Workflows' second part, the crawler's W1–W14 (decisions/0175's note "one control board in the menu", release 2b part C:
 * "the Workflows and Workflow board pages merge. Each workflow's page has its steps, services, backups and task switches"):
 * each workflow's own page on the board linked, its spend this month (Jason, 2026-11-20: Costs at the top of Modules, "By
 * workflow also on Workflows"), and the Workflow board whole (each workflow's settings as versions, the task schedule, the
 * switch-on check). Its old address, /admin/config/board, opens it here (#crawler).
 */
function tv_admin_mod_workflows_crawler(PDO $pdo): string
{
    $e = 'tv_w_e';
    require_once __DIR__ . '/../lib/Automation.php'; require_once __DIR__ . '/../lib/OutsideServices.php'; require_once __DIR__ . '/../lib/Operations.php';
    $pages = '';
    foreach (Automation::WORKFLOWS as $wf => [$name]) $pages .= '<a class="ak-b" href="/admin/config/control/wf-' . $e($wf) . '" data-peek-frame>' . $e($wf . ' ' . $name) . '</a> ';
    $rows = '';
    try { $c = Operations::costs($pdo); ksort($c['workflows'], SORT_NATURAL);
        foreach ($c['workflows'] as $wf => $bySvc) $rows .= '<tr><td><b>' . $e($wf) . '</b> ' . $e(Automation::WORKFLOWS[$wf][0] ?? ($wf === 'run' ? 'The crawler\'s runs' : ($wf === 'email' ? 'Email' : 'Not a workflow\'s'))) . '</td><td>' . $e(implode(' · ', array_map(fn ($s, $v) => (OutsideServices::COMPANIES[$s][0] ?? $s) . ' $' . number_format((float) $v, 2), array_keys($bySvc), $bySvc))) . '</td><td>$' . number_format((float) array_sum($bySvc), 2) . '</td></tr>';
    } catch (Throwable $x) { $rows = ''; error_log('workflows costs: ' . $x->getMessage()); }
    return '<section id="crawler" style="margin-top:1.2rem"><h2 class="ak-h">The crawler\'s workflows (W1–W14)</h2>'
        . '<p class="ak-note">Each workflow\'s own page on the System Control Board has its steps, the services each uses and their backups, and its task switches:</p><p style="display:flex;flex-wrap:wrap;gap:.35rem">' . $pages . '</p>'
        . tv_admin_fold('By workflow, this month', $rows ? 'spend by workflow' : 'nothing spent', 'info', '', $rows ? '<table class="ak-aa"><thead><tr><th>Workflow</th><th>Services</th><th>This month</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '<p class="ak-note">Nothing spent this month.</p>', false)
        . (function () use ($pdo, $e) { try { return tv_admin_mod_board($pdo); } catch (Throwable $x) { error_log('workflows board: ' . $x->getMessage()); return '<p data-tv-status="bad" data-tv-label="Couldn\'t draw the Workflow board:">' . $e($x->getMessage()) . ' Each workflow\'s own page above still opens.</p>'; } })() . '</section><script' . (function_exists('tv_nonce') ? tv_nonce() : '') . '>(function(){if(location.hash==="#crawler"){var s=document.getElementById("crawler");if(s)s.scrollIntoView({block:"start"});}})();</script>';
}
