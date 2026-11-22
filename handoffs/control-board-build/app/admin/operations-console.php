<?php
// app/admin/operations-console.php — System Configuration → Service register, Costs, and Workflows' crawler part (decisions/0163
// §1–§5, §10; Release A). The register: every outside service, what uses it, where its key lives (named, never shown),
// its caps and paces (the one setting the code reads, with who and when, history and Reset), this month's use and cost,
// and its status with the reason; watching Railway and the sync (shipped off). Costs: this month by service and by
// workflow, against revenue and the community budget, with the AI cap's shares. Workflows (W0–W14, step3/workflows-page.md):
// a gauge card each, Paused now on the workflows, each workflow's own page in the side panel with its settings (tasks,
// shares, routes, budgets, gates, weights, retry ladder, person steps) saved as versions with "Go back to it". It shows
// and records: nothing here switches anything on.
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
    // the four-zone module card (control-board-plan step3/03-cards.md §1–§3, §9, §13; app/admin/module-cards.php) draws the
    // register's parts in its zones, every control kept; a card that can't draw falls back to the block below, so the
    // register still shows the service
    if (is_file(__DIR__ . '/module-cards.php')) {
        try { require_once __DIR__ . '/module-cards.php'; return tv_module_card($pdo, $k, ['r' => $r, 'stage' => $stage, 'csrf' => $csrf, 'tz' => $tz, 'can' => $can, 'why' => $why]); }
        catch (Throwable $x) { error_log('module card ' . $k . ': ' . $x->getMessage()); }
    }
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
    $h .= tv_admin_fold('The AI cap\'s shares', $c['ai_shares'] ? count($c['ai_shares']) . ' set' : 'None set', 'info', '', '<p class="ak-note">The monthly AI cap split into a share per workflow (decisions/0163 §7, X4), set on each workflow\'s own page under <a class="underline" href="/admin/config/workflows#crawler">Workflows</a>. The 50/75/95% warnings apply to each share; a share unused at the month\'s end passes on to the next month\'s pool once the engine reads the shares (Release B). W5\'s half of the daily allowance stays its own rule.</p>'
        . ($rows ? '<table class="ak-aa"><thead><tr><th>Workflow</th><th>Its share</th><th>Spent</th><th>Used</th></tr></thead><tbody>' . $rows . '</tbody></table>' : ''), false);
    $h .= tv_admin_fold('A hub\'s opening budget', 'None set', 'info', '', '<p class="ak-note">A one-time allowance per hub, separate from upkeep (0164 R17), set when the hub is Planned. None is set yet; the switch-on check names it for a hub that opens.</p>', false);
    return $h;
}

/**
 * The Workflows page: the crawler's W0–W14 on the System Control Board (handoffs/control-board-plan/step3/workflows-page.md,
 * approved by Jason, 2026-11-22; its mockup board/workflows.html). Top: Paused now, on the workflows, in three groups (R21,
 * R22) with counts that filter the cards (R11); the two clocks (§3); one Arc gauge card per workflow (§5; 03 §27); the case
 * drawn end to end (§8); what moved from the old page (§7); By workflow, this month (R18). Each card opens the workflow's
 * own page in the side panel, with its three paths beside it (Workflows ▸, Core Services ▸, Registered Services ▸), each
 * closing by ×, Esc and a click outside, with Back (03 §27; CLAUDE.md "everything that pops open closes").
 * §7, today's page (manifests.php:168, release #243): 8 kept, 2 moved, 3 dropped.
 *   Kept: the stage workflows' builder (sections.php, untouched); each workflow's own page (/admin/config/control/wf-W#,
 *         opened in a drawer, where a task's switch lives, R144); By workflow, this month (each card and its fold); each
 *         workflow's switch line (words from 0175: "Paused by the system (a trip): …", never "paused by a person"); its
 *         last run (labelled with its zone); Returns empty (R62); the settings form with its versions (R154, unchanged, on
 *         its own page); the task table (each task's limits, services and last 24 h, as "Its steps and their switches").
 *   Moved: Start the system → the board's Start-up only (R17, R146), linked; Check before switching on → each red line on
 *         the card of what it holds (R88, R99), the whole check in Start-up's pre-flight (R58–R60), linked.
 *   Dropped: the interlocks note; the list of W1–W14 page buttons (the cards open each page); the intro line "Each
 *         workflow, W1 to W13 (W14 from when it's built)…" (its promise moves to the settings fold).
 * Nothing here switches anything (R188): a task's switch has one home, its workflow's own page on the board (R119, R144).
 */

/** Who sends work to whom (workflows-page.md §4, from research/crawler-how-it-runs-2026-11-14.md:171–183): [from, to, why, line]. */
const TV_WFP_SENDS = [
    ['W5', 'W1', 'queues the town for W1', ':175'], ['W12', 'W1', 'queues W1 with gaps', ':182'], ['W13', 'W8', 'named places go to Find it', ':183'],
    ['W4', 'W6', 'a place with a guide starts W6', ':176'], ['W4', 'W7', 'a live guide starts W7', ':177'], ['W4', 'W10', 'a live guide starts W10', ':180'],
    ['W4', 'W8', 'a name in a guide starts Find it', ':178'], ['W8', 'W1', 'its listings pass W1\'s gate (the listing score)', ':178'],
    ['W1', 'W3', 'sites linked from pages W1 reads start W3', ':173'],
];
/** What a workflow that isn't built yet is waiting for (workflows-page.md §6 W14; 03 §26.9–26.10, §28.3). */
const TV_WFP_NOT_BUILT = ['W14' => 'licence_lookup (AZCC, NM SoS) isn\'t built yet'];
/** A list, not a gauge (workflows-page.md §5, §10: the session manager, 2026-11-22). */
const TV_WFP_LIST = ['W13' => 'W13 is a list, not a gauge: people write stories, so it has no machine steps to measure.'];
/** Jason's ruling on switches a closed trip left off (workflows-page.md §11, 2026-11-22: "After the re-read"), shown only while they're still off. */
const TV_WFP_HOLD_NOTE = ['W3' => [['source', 'commoncrawl'], 'Waiting on the re-read (Jason, 2026-11-22): they stay off until IR-0001\'s timings and Common Crawl\'s index are read (asks.md checks C1 and C7); nothing switches them back by itself (R98).']];

/** The workflows' gauges (ControlGauges::workflows, the build log §7's contract), wf => row; [] when it can't be read. */
function tv_ops_wf_gauges(PDO $pdo): array
{
    try {
        if (!class_exists('ControlGauges') && is_file(__DIR__ . '/../lib/ControlGauges.php')) require_once __DIR__ . '/../lib/ControlGauges.php';
        if (!class_exists('ControlGauges')) return [];
        $out = []; foreach (ControlGauges::workflows($pdo) as $g) if (isset($g['wf'])) $out[(string) $g['wf']] = $g;
        return $out;
    } catch (Throwable $x) { error_log('workflows gauges: ' . $x->getMessage()); return []; }
}

/** A gauge's colour: its stage's, or at Monitoring its reading against its target (score-gauge.php). */
function tv_ops_wf_gk(array $g, string $st): string
{
    return $st === 'monitor' ? (string) (($g['band'] ?? '') ?: 'none') : $st;
}

/** A stage as score-gauge.php's key (planning|phased|audit|impl|monitor), from a key or its word. */
function tv_ops_wf_stage(string $s): string
{
    if (isset(TV_SG_STAGES[$s])) return $s;
    foreach (TV_SG_STAGES as $k => [$w]) if (strcasecmp($w, $s) === 0) return $k;
    return 'planning';
}

/**
 * Everything the page reads, once: the unit register (R27: Automation::WORKFLOWS, Preflight::NEEDS, SystemStart::TASKS,
 * ControlRegister::of), the switches and the system's holds, the open alarms, Paused now, spend, last runs, the gauges.
 */
function tv_ops_wf_data(PDO $pdo): array
{
    require_once __DIR__ . '/../lib/Automation.php'; require_once __DIR__ . '/../lib/OutsideServices.php'; require_once __DIR__ . '/../lib/Operations.php';
    require_once __DIR__ . '/../lib/Preflight.php'; require_once __DIR__ . '/../lib/SystemStart.php'; require_once __DIR__ . '/../lib/ControlAlarms.php';
    require_once __DIR__ . '/../lib/ControlBoard.php'; require_once __DIR__ . '/../lib/ControlRegister.php'; require_once __DIR__ . '/../lib/WorkflowSettings.php';
    require_once __DIR__ . '/../lib/crawler/CrawlService.php'; require_once __DIR__ . '/../lib/Scheduler.php'; require_once __DIR__ . '/../ui/score-gauge.php';
    Automation::forget();
    $d = ['tasks' => [], 'stats' => [], 'spend' => [], 'empty' => [], 'alarms' => [], 'paused' => [], 'res' => [], 'failed' => null, 'reg' => [], 'jobs' => [], 'feat' => []];
    try { if (CrawlService::ready($pdo)) { $d['tasks'] = CrawlService::tasks($pdo); $d['stats'] = CrawlService::taskStats($pdo, 24); } } catch (Throwable $x) { error_log('workflows tasks: ' . $x->getMessage()); }
    try { $d['spend'] = Operations::costs($pdo)['workflows']; } catch (Throwable $x) { error_log('workflows costs: ' . $x->getMessage()); }
    try { $d['empty'] = Operations::empties($pdo); } catch (Throwable $x) {}
    try { $d['alarms'] = array_values(array_filter(ControlAlarms::list($pdo, 300), fn ($a) => $a['cleared_at'] === null)); } catch (Throwable $x) {}
    $d['paused'] = ControlBoard::pausedNow($pdo);
    try { $d['res'] = Preflight::results($pdo); } catch (Throwable $x) {}
    // D9 (handoffs/control-board-build.md §4; live-reads: Insights' "8 crawl jobs that failed"): failed crawl jobs belong to
    // the coverage task (CrawlService::retry: coverage puts them back), so the count sits on its workflow
    try { $d['failed'] = (int) $pdo->query("SELECT COUNT(*) FROM " . Database::t('crawl_jobs') . " WHERE status = 'failed'")->fetchColumn(); } catch (Throwable $x) { $d['failed'] = null; }
    $d['taskWf'] = []; foreach ($d['tasks'] as $tk => $t) $d['taskWf'][(string) $tk] = (string) $t['wf'];
    foreach (SystemStart::TASKS as $st) if ($st[1] !== '') foreach ((array) $st[4] as $tk) $d['taskWf'][(string) $tk] ??= (string) $st[1];
    foreach (Automation::JOBS as $j => $ws) foreach ($ws as $wf) $d['jobs'][$wf][] = (string) $j;
    try { require_once __DIR__ . '/../lib/AiClient.php'; foreach (CrawlService::FEATURE_WF as $f => $wf) if (!in_array($f, Automation::PERSON_FEATURES, true)) $d['feat'][$wf][] = (string) (AiClient::FEATURES[$f][0] ?? AiClient::SWITCHES[$f][0] ?? $f); } catch (Throwable $x) {}
    foreach (array_keys(Automation::WORKFLOWS) as $wf) { try { $d['reg'][$wf] = ControlRegister::of($pdo, $wf); } catch (Throwable $x) { $d['reg'][$wf] = ['jobs' => [], 'tasks' => [], 'needs' => [], 'services' => [], 'workers' => [], 'endpoints' => []]; } }
    $d['gauges'] = tv_ops_wf_gauges($pdo);
    $d['runtime'] = []; try { if (class_exists('ControlGauges')) $d['runtime'] = ControlGauges::runtime($pdo); } catch (Throwable $x) {}
    $d['master'] = Automation::master($pdo); $d['wfs'] = Automation::workflows($pdo); $d['pauses'] = Automation::pauses($pdo);
    $d['state'] = []; foreach (array_keys(Automation::WORKFLOWS) as $wf) $d['state'][$wf] = tv_ops_wf_state($pdo, $d, (string) $wf);
    return $d;
}

/** The workflows an open alarm belongs to (its part: the workflow, a task, a job, a Start task, an endpoint's tasks, W0's worker), and the task it holds. */
function tv_ops_wf_alarm_of(array $d, array $a): array
{
    $proc = (string) $a['proc']; $key = (string) $a['akey']; $comp = (string) ($a['component'] ?? '');
    $out = [];
    if (str_starts_with($proc, 'wf:')) $out[substr($proc, 3)] = '';
    foreach ([$proc, $comp] as $p) if (str_starts_with($p, 'task:') && isset($d['taskWf'][substr($p, 5)])) $out[$d['taskWf'][substr($p, 5)]] = substr($p, 5);
    if (preg_match('/^task:failed:(.+)$/', $key, $m) && isset($d['taskWf'][$m[1]])) $out[$d['taskWf'][$m[1]]] = $m[1];
    if (str_starts_with($proc, 'job:')) {
        $job = substr($proc, 4); $task = '';
        // D8: a job that is a workflow's task (SystemStart::TASKS: identity_recheck_tick is W2's recheck) holds only that task
        foreach (SystemStart::TASKS as $st) if (in_array($job, (array) $st[3], true) && $st[1] !== '') { $out[(string) $st[1]] = (string) ($st[4][0] ?? ''); $task = (string) ($st[4][0] ?? ''); }
        if ($task === '') foreach ((array) (Automation::JOBS[$job] ?? []) as $wf) $out[$wf] = $out[$wf] ?? '';
    }
    if (str_starts_with($proc, 't:') && isset(SystemStart::TASKS[substr($proc, 2)]) && SystemStart::TASKS[substr($proc, 2)][1] !== '') $out[SystemStart::TASKS[substr($proc, 2)][1]] = (string) (SystemStart::TASKS[substr($proc, 2)][4][0] ?? '');
    if ($proc === 'wk:w0-indexer' || $comp === 'wk:w0-indexer') $out['W0'] = '';
    return $out;
}

/**
 * One workflow's state in words (0175, R86, R101, R105, R107–R110): [k (r red, a amber, n grey, g green), words, why,
 * groups (att, warn, off, none), its alarms [alarm, task], what the system holds of it, switches a closed trip left off,
 * what's off on purpose, its red switch-on lines]. Red only for a trip of its own; a part held by another's trip is amber
 * (R108); off on purpose is never a failure (R109).
 */
function tv_ops_wf_state(PDO $pdo, array $d, string $wf): array
{
    $mine = []; foreach ($d['alarms'] as $a) { $of = tv_ops_wf_alarm_of($d, $a); if (array_key_exists($wf, $of)) $mine[] = [$a, $of[$wf]]; }
    $trips = array_values(array_filter($mine, fn ($x) => $x[0]['level'] === 'trip'));
    $warns = array_values(array_filter($mine, fn ($x) => $x[0]['level'] === 'warning'));
    $held = []; $left = []; $purpose = [];
    foreach ($d['paused'] as $x) {
        if (!preg_match('#/wf-(W\d+)(?:$|[\#?])#', (string) $x[3], $m) || $m[1] !== $wf) continue;
        if (($x['g'] ?? '') === 'purpose') $purpose[] = $x; elseif (isset($x['left'])) $left[] = $x; else $held[] = $x;
    }
    $red = []; try { $red = Operations::blocking($pdo, $wf); } catch (Throwable $x) {}
    $tasks = array_filter($d['tasks'], fn ($t) => (string) $t['wf'] === $wf);
    $on = array_filter($tasks, fn ($t) => (int) $t['enabled'] === 1);
    $g = $d['gauges'][$wf] ?? []; $kind = (string) ($g['kind'] ?? '');
    $serves = $tasks || !empty($d['jobs'][$wf]) || !empty($d['feat'][$wf]);
    $isList = $kind === 'list' || isset(TV_WFP_LIST[$wf]);
    $notBuilt = !$isList && ($kind === 'not_built' || (!$serves && $wf !== 'W0'));
    $r = $d['wfs'][$wf] ?? null; $pp = $r && (int) $r['paused'];
    $groups = [];
    $failed = $d['failed'] && ($d['taskWf']['coverage'] ?? 'W1') === $wf ? (int) $d['failed'] : 0;
    $empty = array_values(array_filter($d['empty'], fn ($e) => ($e['wf'] ?? '') === $wf));
    $heldWords = fn (array $xs) => count($xs) === 1 ? preg_replace('/ \(W\d+\)$/', '', (string) $xs[0][0]) : count($xs) . ' parts';
    if ($trips) {   // its own trip: red (R108), the words the engine gives (R77)
        $sw = isset($d['pauses']['wf:' . $wf]) ? 'the whole workflow' : ($held ? $heldWords($held) : '');
        [$k, $words] = ['r', $sw !== '' ? 'Paused by the system (a trip): ' . $sw : 'Tripped: ' . rtrim((string) $trips[0][0]['title'], '. ')];
        $why = rtrim((string) $trips[0][0]['title'], '. ') . (count($trips) > 1 ? ', and ' . (count($trips) - 1) . ' more trip' . (count($trips) > 2 ? 's' : '') : '') . '.';
        $groups[] = 'att';
    } elseif ($held) {   // held by another part's trip: amber, named (R87, R108)
        [$k, $words, $why] = ['a', 'Paused by the system (a trip): ' . $heldWords($held), implode(' ', array_map(fn ($x) => (string) $x[0] . ': ' . (string) $x[2] . '.', $held))];
        $groups[] = 'att';
    } elseif ($left) {   // switches a closed trip left off (R98): amber, under Needs attention
        [$k, $words, $why] = ['a', 'Off by the system: ' . count($left) . ' switch' . (count($left) === 1 ? '' : 'es') . ', left by a closed trip', 'Left off by a trip that has closed (' . (string) $left[0]['left'] . '). Nobody had set these switches before, so they stay off until someone switches them on (R98).'];
        $groups[] = 'att';
    } elseif ($isList) {
        [$k, $words, $why] = ['n', 'Nothing to switch: people write', 'W13 is people\'s own: no crawler task, no site job, no switch (SystemStart.php: "W13 is people\'s own").'];
        $groups[] = 'off';
    } elseif ($notBuilt) {   // W14 (live-reads C6: it said "Running" with nothing serving it)
        [$k, $words, $why] = ['n', 'Not built' . (isset(TV_WFP_NOT_BUILT[$wf]) ? ': ' . strtok(TV_WFP_NOT_BUILT[$wf], ' ') : ''), 'Nothing carries it yet: no crawler task, site job or AI feature names ' . $wf . '.' . (isset(TV_WFP_NOT_BUILT[$wf]) ? ' Its ' . TV_WFP_NOT_BUILT[$wf] . ' (03 §26.9–26.10).' : '')];
        $groups[] = 'off';
    } elseif ($red) {   // a red switch-on line that holds it whole (moved onto its card: §7, R88, R99)
        [$k, $words, $why] = ['a', 'Held by its check: ' . (string) $red[0]['line'], rtrim((string) $red[0]['why'], '. ') . '.'];
        $groups[] = 'att';
    } elseif ($warns) {
        [$k, $words, $why] = ['a', 'Warning: ' . rtrim((string) $warns[0][0]['title'], '. '), count($warns) . ' warning' . (count($warns) === 1 ? '' : 's') . ' open: the system is trying; nothing is held.'];
    } elseif ($d['master']) {
        [$k, $words, $why] = ['n', 'On, held by the master switch', Automation::line($d['master'], 'Automation is paused') . '.'];
        $groups[] = 'off';
    } elseif ($pp) {   // a person's pause: paused, never a failure (R109)
        [$k, $words, $why] = ['n', 'Paused: ' . rtrim(trim((string) ($r['paused_why'] ?? '')) ?: 'paused', '. '), 'Set by ' . (trim((string) ($r['by_user'] ?? '')) ?: 'a person') . (!empty($r['paused_at']) ? ', ' . date('M j', (int) strtotime((string) $r['paused_at'])) : '') . '.'];
        $groups[] = 'off';
    } elseif ($tasks && !$on) {
        $p0 = $purpose[0] ?? null;
        [$k, $words, $why] = ['n', 'Off' . ($p0 ? ': ' . rtrim((string) $p0[2], '. ') : ''), 'Every task is switched off on purpose; nothing runs until one is switched on, on its own page.'];
        $groups[] = 'off';
    } else {
        [$k, $words, $why] = ['g', 'Running', ($tasks ? count($on) . ' of ' . count($tasks) . ' tasks on' : 'Its site jobs and AI features run by their own switches') . ($purpose ? '; ' . count($purpose) . ' off on purpose' : '') . '.'];
    }
    if ($warns || $failed || $empty) $groups[] = 'warn';
    if ($purpose && !in_array('off', $groups, true)) $groups[] = 'off';
    if (!$groups) $groups[] = 'none';
    return ['k' => $k, 'words' => $words, 'why' => $why, 'groups' => array_values(array_unique($groups)), 'alarms' => $mine, 'trips' => $trips, 'warns' => $warns, 'held' => $held, 'left' => $left,
            'purpose' => $purpose, 'red' => $red, 'failed' => $failed, 'empty' => $empty, 'list' => $isList, 'notbuilt' => $notBuilt, 'tasks' => $tasks];
}

/** A light and its words (the board's marks: R107; colour is never the only signal, the words carry it). */
function tv_ops_wf_lt(string $k, string $words): string
{
    return '<span class="wfp-lt wfp-' . tv_w_e($k) . '"><i aria-hidden="true"></i>' . tv_w_e($words) . '</span>';
}

/** The section: the crawler's workflows on the System Control Board (manifests.php:168, under the stage workflows' builder). */
function tv_admin_mod_workflows_crawler(PDO $pdo): string
{
    return '<section id="crawler" style="margin-top:1.2rem"><h2 class="ak-h">The crawler\'s workflows (W0–W14)</h2>' . tv_ops_workflows_view($pdo) . '</section>'
        . '<script' . (function_exists('tv_nonce') ? tv_nonce() : '') . '>(function(){if(location.hash==="#crawler"){var s=document.getElementById("crawler");if(s)s.scrollIntoView({block:"start"});}})();</script>';
}

/** The old Workflow board's address (manifests.php 'board'; router.php folds /admin/config/board into Workflows#crawler): the same page. */
function tv_admin_mod_board(PDO $pdo): string
{
    return tv_admin_mod_workflows_crawler($pdo);
}

/** The Workflows view: also what the board's Workflows tab draws. */
function tv_ops_workflows_view(PDO $pdo): string
{
    [$can, $why, $uid, $who, $csrf, $tz, $flash] = tv_ops_ctx($pdo);
    $e = 'tv_w_e';
    try { $d = tv_ops_wf_data($pdo); }
    catch (Throwable $x) { error_log('workflows view: ' . $x->getMessage()); return $flash . '<p data-tv-status="bad" data-tv-label="Couldn\'t read the workflows:">' . $e($x->getMessage()) . ' Each workflow\'s own page still opens on the <a class="underline" href="/admin/config/control">System Control Board</a>.</p>'; }
    // the switch-on check, read as the old board read it each time it opened, so its lines stay fresh (§7 Moved: its red
    // lines now show on what they hold; the whole check is in Start-up's pre-flight)
    try { require_once __DIR__ . '/preflight-console.php'; Preflight::recordSwitchOn($pdo, Operations::switchOnCheck($pdo), 'Workflows'); } catch (Throwable $x) { error_log('workflows switch-on: ' . $x->getMessage()); }
    $h = $flash . tv_score_gauge_css() . tv_ops_wf_css();
    if (class_exists('Thread')) $h .= Thread::css(); else { require_once __DIR__ . '/../lib/Thread.php'; $h .= Thread::css(); }
    // the layer line (R140, R141, R88); the master's state as it is now
    $h .= '<p class="ak-note">W0–W14 from <code>Automation::WORKFLOWS</code>, each a gauge under the approved lifecycle: setup % through Planning to Implementation, its live reading against its target at Monitoring (Jason, 2026-11-22). A call happens only when the master switch, its workflow, its task and its service are all on; a source down holds only what uses it. '
        . ($d['master'] ? '<b>The master switch is paused</b>: ' . $e(Automation::line($d['master'], 'paused')) . '. ' : '')
        . 'Start the system and the whole switch-on check are in the board\'s <a class="underline" href="/admin/config/control?tab=startup" data-peek-frame>Start-up</a> and <a class="underline" href="/admin/config/control?tab=preflight#pf-g-switchon" data-peek-frame>Pre-flight</a>.' . ($can ? '' : ' <b>' . $e($why) . '</b>') . '</p>';
    $h .= tv_ops_wf_paused($d);
    $h .= tv_ops_wf_clocks();
    $h .= '<div class="wfp-grid" id="wfp-grid">';
    foreach (Automation::WORKFLOWS as $wf => $w) $h .= tv_ops_wf_card($d, (string) $wf);
    $h .= '</div>' . tv_ops_wf_case() . tv_ops_wf_moved();
    // By workflow, this month (kept: R18, R162): spend by workflow and service
    $rows = ''; $sp = $d['spend']; ksort($sp, SORT_NATURAL);
    foreach ($sp as $wf => $bySvc) $rows .= '<tr><td><b>' . $e((string) $wf) . '</b> ' . $e(Automation::WORKFLOWS[$wf][0] ?? ($wf === 'run' ? 'The crawler\'s runs' : ($wf === 'email' ? 'Email' : 'Not a workflow\'s'))) . '</td><td>' . $e(implode(' · ', array_map(fn ($s, $v) => (OutsideServices::COMPANIES[$s][0] ?? $s) . ' $' . number_format((float) $v, 2), array_keys($bySvc), $bySvc))) . '</td><td>$' . number_format((float) array_sum($bySvc), 2) . '</td></tr>';
    $h .= '<div id="wfp-spend">' . tv_admin_fold('By workflow, this month', $rows ? 'spend by workflow' : 'nothing spent', 'info', '', $rows ? '<table class="ak-aa"><thead><tr><th>Workflow</th><th>Services</th><th>This month</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '<p class="ak-note">Nothing spent this month.</p>', false) . '</div>';
    // the side panels: each workflow's own page and its three paths
    $h .= '<div class="wfp-scrim" data-wfp-scrim hidden></div>';
    foreach (Automation::WORKFLOWS as $wf => $w) $h .= tv_ops_wf_panel($pdo, $d, (string) $wf, $csrf, $can, (string) $why) . tv_ops_wf_paths($d, (string) $wf);
    return '<div id="wfp" data-csrf="' . $e($csrf) . '">' . $h . '</div>' . tv_ops_wf_js($csrf) . tv_ops_tz_js();
}

/** Paused now, on the workflows (R21, R22, R105): Needs attention, Waiting on those, Warnings, Off on purpose; the counts filter the cards (R11). */
function tv_ops_wf_paused(array $d): string
{
    $e = 'tv_w_e';
    $att = []; $wait = []; $warn = []; $off = [];
    $open = fn (string $wf, string $label) => '<a href="#wfp-' . $e($wf) . '" data-wfp-open="wfp-' . $e($wf) . '" class="underline">' . $e($label) . '</a>';
    foreach (Automation::WORKFLOWS as $wf => [$name]) {
        $s = $d['state'][$wf]; $nm = $wf . ' ' . $name;
        foreach ($s['trips'] as [$a, $tk]) $att[] = [$wf, tv_ops_wf_lt('r', $wf) . ' ' . $open($wf, $name) . ': ' . $e(rtrim((string) $a['title'], '. ')) . '. ' . $e(trim((string) ($a['todo'] ?? '')) !== '' ? 'Its fix: ' . preg_replace('/^Its fix:\s*/i', '', rtrim((string) $a['todo'], '. ')) . '.' : 'Its fix is on its page.')];
        foreach ($s['left'] as $x) $att[] = [$wf, tv_ops_wf_lt('a', $wf) . ' ' . $open($wf, $name) . ': ' . $e((string) $x[0]) . ': Off by the system, left by a closed trip (' . $e((string) $x['left']) . '). Nothing switches it back by itself (R98); switch it on on its page when it\'s ready.'
            . (isset(TV_WFP_HOLD_NOTE[$wf]) && preg_match('/#task-(' . implode('|', array_map('preg_quote', TV_WFP_HOLD_NOTE[$wf][0])) . ')$/', (string) $x[3]) ? ' ' . $e(TV_WFP_HOLD_NOTE[$wf][1]) : '')];
        foreach ($s['red'] as $l) $att[] = [$wf, tv_ops_wf_lt('a', $wf) . ' ' . $open($wf, $name) . ': held by its check, "' . $e((string) $l['line']) . '" (' . $e(rtrim((string) $l['why'], '. ')) . ').'];
        foreach ($s['held'] as $x) $wait[] = $open($wf, (string) $x[0]) . ': ' . $e((string) $x[2]) . '.';
        foreach ($s['warns'] as [$a, $tk]) $warn[] = [$wf, tv_ops_wf_lt('a', $wf) . ' ' . $open($wf, $name) . ': ' . $e(rtrim((string) $a['title'], '. ')) . ($tk !== '' ? ' (it holds only ' . $e($tk) . ')' : '') . '.'];
        if ($s['failed']) $warn[] = [$wf, tv_ops_wf_lt('a', $wf) . ' ' . $open($wf, $name) . ': ' . (int) $s['failed'] . ' crawl job' . ($s['failed'] === 1 ? '' : 's') . ' failed (coverage); Try again is on its page.'];
        foreach ($s['empty'] as $em) $warn[] = [$wf, tv_ops_wf_lt('a', $wf) . ' ' . $open($wf, $name) . ': returns empty: ' . $e((string) $em['name']) . ' had work and got answers, but nothing came back.'];
        foreach ($s['purpose'] as $x) $off[] = [$wf, tv_ops_wf_lt('n', $wf) . ' ' . $open($wf, $name) . ': ' . $e((string) $x[0]) . ', ' . $e($x[4] ?? 'switched off') . ' by ' . $e((string) $x[1]) . ' (' . $e((string) $x[2]) . ').'];
        if ($s['list'] || $s['notbuilt'] || ($s['k'] === 'n' && !$s['purpose'])) $off[] = [$wf, tv_ops_wf_lt('n', $wf) . ' ' . $open($wf, $name) . ': ' . $e(lcfirst($s['words'])) . '.'];
    }
    $count = function (string $g) use ($d): int { $n = 0; foreach ($d['state'] as $s) if (in_array($g, $s['groups'], true)) $n++; return $n; };
    $li = fn (array $xs) => '<ul class="wfp-l">' . implode('', array_map(fn ($x) => '<li>' . $x[1] . '</li>', $xs)) . '</ul>';
    $h = '<div class="wfp-now ak-card tv-bleed" id="wfp-now" role="status"><h3 class="wfp-h">Paused now, on the workflows</h3><div class="wfp-cnt">';
    foreach (['att' => 'Needs attention', 'warn' => 'Warnings', 'off' => 'Off on purpose', 'none' => 'No open alarm'] as $g => $lab)
        $h .= '<button type="button" class="wfp-f" data-wfp-filter="' . $g . '" aria-pressed="false" title="Show only these cards; press again to show every card">' . $e($lab) . ' <b>' . $count($g) . '</b></button>';
    $h .= '</div>';
    if (!$att && !$warn && !$off) return $h . '<p data-tv-status="ok" data-tv-label="Paused now:">Nothing. Every workflow\'s switches are on, and the system holds nothing of them.</p></div>';
    $h .= '<h4 class="wfp-g">Needs attention</h4>' . ($att ? $li($att) : '<p class="ak-note">Nothing: no trip holds a workflow.</p>');
    if ($wait) $h .= '<details class="wfp-more"><summary>Waiting on those: ' . count($wait) . '</summary><ul class="wfp-l"><li>' . implode('</li><li>', $wait) . '</li></ul></details>';
    if ($warn) $h .= '<h4 class="wfp-g">Warnings</h4>' . $li($warn);
    if ($off) $h .= '<h4 class="wfp-g">Off on purpose</h4>' . $li($off);
    return $h . '</div>';
}

/** The two clocks (workflows-page.md §3; 02, model 1.2): a scheduled run is not a try (R67, R68). */
function tv_ops_wf_clocks(): string
{
    return '<details class="wfp-more wfp-clk"><summary>The two clocks</summary><div class="wfp-2"><div><h4 class="wfp-g">The run schedule: when work happens</h4>'
        . '<p class="ak-note">Each workflow\'s "What starts it" (on its own page); a task\'s every N minutes and its share of a run; the bulk loads\' night window (1–5 AM Arizona); the site\'s jobs; the 2 AM pre-flight; the watch every 5 minutes.</p></div>'
        . '<div><h4 class="wfp-g">The diagnostic clock: how a fault is judged</h4><p class="ak-note">A failing component\'s check: 3 tries inside the hour (control_fix_tries, control_fix_minutes: "Keep the hour"); slow is never a trip; the back-off ladder; late and stuck (a job past its time + 5 minutes; a run unfinished after 2 hours; W0 silent over 5 minutes); Pulse\'s escalation. <b>A scheduled run is not a try.</b></p></div></div></details>';
}

/** One gauge card (§5; 03 §27): its name, its Arc gauge with its stage, setup n of 4, its state in words, the approved measure, its month's spend, its three paths. W13 a list (§10). */
function tv_ops_wf_card(array $d, string $wf): string
{
    $e = 'tv_w_e'; $name = (string) Automation::WORKFLOWS[$wf][0]; $s = $d['state'][$wf]; $g = $d['gauges'][$wf] ?? null;
    $cost = array_sum($d['spend'][$wf] ?? []);
    $h = '<article class="wfp-card wfp-s-' . $e($s['k']) . '" id="wfp-card-' . $e($wf) . '" data-wfp-g="' . $e(implode(' ', $s['groups'])) . '">'
        . '<button type="button" class="wfp-open" data-wfp-open="wfp-' . $e($wf) . '" aria-label="' . $e('Open ' . $wf . ' ' . $name . ': ' . $s['words']) . '"><span class="wfp-nm"><b>' . $e($wf) . '</b> ' . $e($name) . '</span>';
    if ($s['list']) $h .= '<span class="wfp-list">' . $e(TV_WFP_LIST[$wf] ?? 'A list, not a gauge.') . '</span>';
    elseif ($g && ($g['stage'] ?? null) !== null) {
        $st = tv_ops_wf_stage((string) $g['stage']);
        $su = $g['setup'] ?? null; $n = is_array($su) ? count(array_filter($su)) : (is_numeric($su) ? (int) $su : null);
        $sc = isset($g['score']) && $g['score'] !== null ? (float) $g['score'] : null;
        $h .= tv_score_gauge($sc, $wf . ' ' . $name . ': ' . ($sc === null ? 'no score' : round($sc) . ' percent') . ', ' . TV_SG_STAGES[$st][0], tv_ops_wf_gk($g, $st))
            . tv_score_stage_pill($st) . ($n !== null && $st !== 'monitor' ? '<span class="wfp-su">setup: ' . $n . ' of 4</span>' : (($g['reading_text'] ?? '') !== '' ? '<span class="wfp-su">' . $e((string) $g['reading_text']) . '</span>' : ''));
    } else $h .= tv_score_gauge(null, $wf . ' ' . $name . ': its gauge isn\'t checked yet', 'none') . '<span class="wfp-su">Its gauge: ' . $e((string) ($g['reading_text'] ?? 'not checked yet')) . '</span>';
    $h .= tv_ops_wf_lt($s['k'], $s['words']);
    if ($s['failed']) $h .= tv_ops_wf_lt('a', $s['failed'] . ' crawl job' . ($s['failed'] === 1 ? '' : 's') . ' failed');
    if ($s['empty']) $h .= tv_ops_wf_lt('a', 'Returns empty: ' . implode(', ', array_column($s['empty'], 'name')));
    if ($g && !$s['list'] && ($g['measure'] ?? '') !== '') $h .= '<span class="wfp-m"><span class="wfp-appr">approved</span> ' . $e((string) $g['measure']) . '</span>';
    if ($cost > 0) $h .= '<span class="wfp-m">This month: $' . number_format($cost, 2) . '</span>';
    return $h . '</button>' . tv_ops_wf_pathbtns($wf) . '</article>';
}

function tv_ops_wf_pathbtns(string $wf): string
{
    $e = 'tv_w_e';
    return '<div class="wfp-btns"><button type="button" class="ak-b wfp-path" data-wfp-open="wfp-' . $e($wf) . '-a">Workflows ▸</button><button type="button" class="ak-b wfp-path" data-wfp-open="wfp-' . $e($wf) . '-c">Core Services ▸</button><button type="button" class="ak-b wfp-path" data-wfp-open="wfp-' . $e($wf) . '-r">Registered Services ▸</button></div>';
}

/** A side panel's frame: ×, its crumb, its title, Back (03 §27: each a side panel with Back; closing by ×, Esc, a click outside). */
function tv_ops_wf_aside(string $id, string $label, string $crumb, string $title, string $body): string
{
    $e = 'tv_w_e';
    return '<aside class="wfp-panel" id="' . $e($id) . '" hidden role="dialog" aria-modal="true" aria-label="' . $e($label) . '"><button type="button" class="wfp-x" data-wfp-close aria-label="Close">×</button>'
        . '<p class="wfp-crumb">' . $crumb . '</p><h3 class="wfp-t">' . $e($title) . '</h3>' . $body
        . '<p class="wfp-btns"><button type="button" class="ak-b" data-wfp-back>← Back</button></p></aside>';
}

/**
 * A workflow's own page, in the side panel (§6, §7 Kept): what it does and where it runs, its state with the reason, its
 * alarms in the one thread shape (R66, R102), its gauge and setup (§5), what starts it (the run schedule), its steps
 * (each task's switch on the board, R119, R144; its last 24 h; D8, D9), what a source down holds (R88), steps that need
 * a person, its pre-flight line's parts (a roll-up that raises nothing: W1's "Needs: …", W14's route kept), and its
 * settings with their versions (R154, unchanged). Also drawn on its own page on the board (tv_ops_wf_page).
 */
function tv_ops_wf_page(PDO $pdo, string $wf, ?array $d = null, string $csrf = '', ?bool $can = null, string $why = ''): string
{
    $e = 'tv_w_e';
    if ($d === null) $d = tv_ops_wf_data($pdo);
    if ($can === null) { [$can, $why] = Operations::can(); $csrf = (string) ($_SESSION['csrf_token'] ?? ''); }
    if (!isset(Automation::WORKFLOWS[$wf])) return '';
    [$name, $what, $where] = Automation::WORKFLOWS[$wf]; $s = $d['state'][$wf]; $g = $d['gauges'][$wf] ?? null;
    $h = '<p class="ak-note">' . $e($what) . ' Runs on: ' . $e($where) . '.</p>';
    $h .= '<div class="wfp-st">' . tv_ops_wf_lt($s['k'], $s['words']) . '<p class="ak-note">' . $e($s['why']) . '</p></div>';
    // its red switch-on lines, each with its fix (moved here from "Check before switching on": §7, R60)
    foreach ($s['red'] as $l) $h .= '<p data-tv-status="info" data-tv-label="Held by its check:">' . $e((string) $l['line']) . ': ' . $e(rtrim((string) $l['why'], '. ')) . '.'
        . (!empty($l['fix'][1]) ? ' <a class="underline" href="' . $e((string) $l['fix'][1]) . '" data-peek-frame>' . $e((string) $l['fix'][0]) . '</a>' : '') . '</p>';
    // left off by a closed trip (R98), with Jason's ruling where one applies (§11)
    foreach ($s['left'] as $x) $h .= '<p data-tv-status="info" data-tv-label="Off by the system:">' . $e((string) $x[0]) . ', left by a closed trip (' . $e((string) $x['left']) . '). It stays off until someone switches it on <a class="underline" href="' . $e((string) $x[3]) . '" data-peek-frame>on its page</a>.'
        . (isset(TV_WFP_HOLD_NOTE[$wf]) && preg_match('/#task-(' . implode('|', array_map('preg_quote', TV_WFP_HOLD_NOTE[$wf][0])) . ')$/', (string) $x[3]) ? ' ' . $e(TV_WFP_HOLD_NOTE[$wf][1]) : '') . '</p>';
    if ($wf === 'W1') $h .= '<p class="ak-note"><b>/api/crawl/loads.php is a Core Services component with its own check</b> (workflows-page.md §3, R87): it answers within the worker\'s 120 s, read from its calls in the ledger; when it fails it holds only overture_bulk, foursquare_bulk and geofabrik_bulk. coverage, second_look, targets, npi_bulk and irs_bulk don\'t call it and carry on.</p>';
    if (isset(TV_WFP_NOT_BUILT[$wf]) && $s['notbuilt']) $h .= '<p data-tv-status="info" data-tv-label="Not built:">Nothing carries ' . $e($wf) . ' yet: its ' . $e(TV_WFP_NOT_BUILT[$wf]) . ' (03 §26.9–26.10). Its switches, gauge and alarms come with it.</p>';
    // its alarms, each in the one thread shape (R66, R102); its first button opens its part (R116); Fix it where a person can fix it (R117, R118)
    if ($s['alarms']) {
        require_once __DIR__ . '/../lib/Thread.php';
        [$canB, $whyB] = ControlBoard::can();
        $h .= '<h4 class="wfp-g">Its alarms</h4>';
        foreach ($s['alarms'] as [$a, $tk]) {
            try { $f = ControlAlarms::facts($pdo, $a); } catch (Throwable $x) { $f = ['title' => (string) $a['title'], 'level' => (string) $a['level'], 'pill' => ucfirst((string) $a['level']), 'status' => '', 'doing' => '', 'todo' => (string) ($a['todo'] ?? ''), 'details' => []]; }
            if ($tk !== '' && str_starts_with((string) $a['proc'], 'job:')) $f['doing'] = 'It is ' . $wf . '\'s ' . $tk . ' task: its hang holds only ' . $tk . '; the rest of ' . $wf . ' runs. ' . (string) ($f['doing'] ?? '');   // D8
            $btn = '<a class="ak-b" href="/admin/config/control/' . $e(str_replace(':', '-', (string) $a['proc'])) . '" data-peek-frame>Open it</a>';
            $fx = null; try { $fx = Thread::fixOf($f); } catch (Throwable $x) {}
            if ($fx) $btn = Thread::fixButton($fx, 'ak-b go') . ' ' . $btn;
            if ($a['level'] === 'trip' && !empty($a['tripped_at'])) $btn .= ' <button type="button" class="ak-b" data-wfp-cb="resolve" data-wfp-id="' . (int) $a['id'] . '"' . ($canB ? '' : ' disabled title="' . $e((string) $whyB) . '"') . '>Check and start again</button>';
            foreach ((array) ($a['fix'] ? (json_decode((string) $a['fix'], true) ?: []) : []) as $fz) if (is_array($fz) && ($fz[1] ?? '') === 'peek' && !empty($fz[2])) $btn .= ' <a class="ak-b" href="' . $e((string) $fz[2]) . '" data-peek-frame>' . $e((string) $fz[0]) . '</a>';
            $h .= '<div class="wfp-th">' . Thread::html($f + ['pill_class' => 'tv-th-pill tv-th-' . ($a['level'] === 'trip' ? 'trip' : 'warning')], 'card', $btn) . '</div>';
        }
    }
    // its gauge (§5)
    if ($s['list']) $h .= '<h4 class="wfp-g">A list, not a gauge</h4><p class="ak-note">' . $e(TV_WFP_LIST[$wf] ?? '') . '</p>';
    elseif ($g && ($g['stage'] ?? null) !== null) {
        $st = tv_ops_wf_stage((string) $g['stage']); $sc = isset($g['score']) && $g['score'] !== null ? (float) $g['score'] : null;
        $h .= '<h4 class="wfp-g">Its gauge</h4><div class="wfp-gz">' . tv_score_gauge($sc, $wf . ': ' . ($sc === null ? 'no score' : round($sc) . ' percent') . ', ' . TV_SG_STAGES[$st][0], tv_ops_wf_gk($g, $st), ['big' => true]) . tv_score_stage_pill($st) . '</div>'
            . '<p class="ak-note">Measure: ' . $e((string) ($g['measure'] ?? '')) . '; target: ' . $e((string) ($g['target'] ?? 'set by Jason')) . ' <span class="wfp-appr">approved (Jason, 2026-11-22)</span>.' . (($g['reading_text'] ?? '') !== '' ? ' Reading: ' . $e((string) $g['reading_text']) . '.' : '') . '</p>';
        if (is_array($g['setup'] ?? null)) { $h .= '<ul class="wfp-l wfp-su2">'; foreach (['probe' => 'The probe built', 'target' => 'Its target set', 'check' => 'Its check passing', 'alarm' => 'Its alarm wired to the board'] as $sk => $sl) if (array_key_exists($sk, $g['setup'])) $h .= '<li>' . ($g['setup'][$sk] ? '✓' : '·') . ' ' . $e($sl) . (!empty($g['setup_why'][$sk]) ? ': ' . $e((string) $g['setup_why'][$sk]) : '') . '</li>'; $h .= '</ul>'; }
    }
    elseif (!$s['list']) $h .= '<h4 class="wfp-g">Its gauge</h4><p class="ak-note">' . $e((string) ($g['reading_text'] ?? 'Not checked yet')) . ': its setup and reading come from the gauges\' file (ControlGauges).</p>';
    // what starts it (the run schedule) and what serves it (R27, R142, R164)
    $spec = null; try { $spec = WorkflowSettings::current($pdo, $wf); } catch (Throwable $x) {}
    if ($spec) $h .= '<h4 class="wfp-g">What starts it</h4><p class="ak-note">' . $e((string) $spec['starts']['schedule']) . ((string) ($spec['starts']['triggers'] ?? '') !== '' ? ' ' . $e((string) $spec['starts']['triggers']) : '') . '</p>';
    $sv = array_filter(['Site jobs: ' . implode(', ', array_map(fn ($j) => Scheduler::JOBS[$j][0] ?? $j, $d['jobs'][$wf] ?? [])) => !empty($d['jobs'][$wf]), 'AI features: ' . implode(', ', $d['feat'][$wf] ?? []) => !empty($d['feat'][$wf])]);
    if ($sv) $h .= '<p class="ak-note">' . $e(implode(' · ', array_keys($sv))) . '. Pausing ' . $e($wf) . ' stops its tasks, its jobs\' part for it and its AI features (R142).</p>';
    // its steps (the task table, kept as "Its steps and their switches"): the switch itself has one home, its own page on the board
    $tasks = $s['tasks'];
    $h .= '<h4 class="wfp-g">Its steps and their switches</h4>';
    if (!$tasks) $h .= '<p class="ak-note">' . ($s['list'] ? 'No tasks: people write.' : ($s['notbuilt'] ? 'No tasks yet: nothing is built to run.' : 'No crawler tasks: it runs on the site\'s jobs above.')) . '</p>';
    else {
        $gate = []; try { $gate = Operations::gateFor($pdo, $wf)['tasks']; } catch (Throwable $x) {}
        $h .= '<ul class="wfp-steps">';
        foreach ($tasks as $tk => $t) {
            $tk = (string) $tk; $st = $d['stats'][$tk] ?? [];
            $sw = (int) $t['enabled'] === 1 ? (($p = Automation::taskOff($pdo, $tk)) !== null ? ['a', $p] : ['g', 'On']) : ['n', 'Off: ' . Automation::line(['why' => (string) ($t['off_why'] ?? ''), 'by_user' => '', 'at' => $t['off_at'] ?? null], 'switched off')];
            foreach ($s['left'] as $x) if (str_ends_with((string) $x[3], '#task-' . $tk)) $sw = ['a', 'Off by the system, left by a closed trip'];
            $svcs = []; foreach (OutsideServices::CONTROLS as $svc => $c) if (in_array($tk, (array) ($c['tasks'] ?? []), true)) $svcs[] = '<a class="underline" href="/admin/config/register#svc-' . $e($svc) . '" data-peek-frame>' . $e((string) (OutsideServices::COMPANIES[$svc][0] ?? $svc)) . '</a>';
            $h .= '<li id="wfp-' . $e($wf) . '-task-' . $e($tk) . '"><div><b>' . $e((string) $t['name']) . '</b> <small>' . $e($tk) . ((string) ($t['what'] ?? '') !== '' ? ': ' . $e((string) $t['what']) : '') . '</small></div>'
                . tv_ops_wf_lt($sw[0], $sw[1])
                . '<div><small>Uses: ' . ($svcs ? implode(', ', $svcs) : 'no outside service') . ' · Last 24 h: ' . $e(implode(', ', array_map(fn ($k, $v) => $v . ' ' . str_replace('_', ' ', $k), array_keys(array_diff_key($st, ['seconds' => 1])), array_diff_key($st, ['seconds' => 1]))) ?: 'no runs')
                . ($t['last_served_at'] ? ' · Last run: <time datetime="' . $e(date('c', (int) strtotime((string) $t['last_served_at']))) . '" data-tv-time>' . $e(date('M j, g:i A T', (int) strtotime((string) $t['last_served_at']))) . '</time>, ' . $e(strtolower(CrawlService::OUTCOMES[(string) $t['last_outcome']] ?? (string) $t['last_outcome'])) . ((string) ($t['last_why'] ?? '') !== '' ? ' (' . $e((string) $t['last_why']) . ')' : '') : '') . '</small></div>';
            foreach ((array) ($gate[$tk] ?? []) as $l) $h .= '<p data-tv-status="info" data-tv-label="Held by its check:">' . $e((string) $l['line']) . ': ' . $e(rtrim((string) $l['why'], '. ')) . '. It holds only this step; the rest of ' . $e($wf) . ' runs.</p>';
            foreach ($s['alarms'] as [$a, $atk]) if ($atk === $tk) $h .= '<p data-tv-status="' . ($a['level'] === 'trip' ? 'bad' : 'info') . '" data-tv-label="' . ($a['level'] === 'trip' ? 'Trip:' : 'Warning:') . '">' . $e(rtrim((string) $a['title'], '. ')) . '. It holds only ' . $e($tk) . '.</p>';
            // D9: failed crawl jobs on the task that puts them back (coverage), Try again here: Insights → Crawler's own Retry (cr_do=retry)
            if ($tk === 'coverage' && $s['failed']) {
                [$canR, $whyR] = Access::can('insights.crawler.edit', ['min' => 'staff', 'depts' => ['ops']]);
                $h .= '<p data-tv-status="info" data-tv-label="' . (int) $s['failed'] . ' crawl job' . ($s['failed'] === 1 ? '' : 's') . ' failed:">They wait for a person: Try again puts them back in the queue with their tries reset, and the crawler takes them on its next runs. '
                    . '<button type="button" class="ak-b go" data-wfp-retry="coverage"' . ($canR ? '' : ' disabled title="' . $e((string) $whyR) . '"') . '>Try again</button></p>';
            }
            $h .= '<div class="wfp-btns"><a class="ak-b" href="/admin/config/control/wf-' . $e($wf) . '#task-' . $e($tk) . '" data-peek-frame>Its switch</a></div></li>';
        }
        $h .= '</ul><p class="ak-note">One switch per task, on ' . $e($wf) . '\'s own page on the board, recorded with who and why (R119, R143, R144).</p>';
    }
    // what a source down holds (R88, R90): each need's sources in order, ours first (R61), and what waits without them
    $nk = (array) ($d['reg'][$wf]['needs'] ?? []);
    if ($nk) {
        $nd = ControlBoard::needs($d['res'], $nk);
        $h .= '<h4 class="wfp-g">What a source down holds</h4><ul class="wfp-l">';
        foreach ($nd as $k => [$nn, $ns, $srcs, $fallback]) {
            $names = array_map(fn ($x) => (string) $x[0], $srcs);
            $users = []; foreach ((array) Preflight::NEEDS[$k][3] as $sr) if (($sr[0] ?? '') === 'svc') foreach ((array) (OutsideServices::CONTROLS[(string) $sr[1]]['tasks'] ?? []) as $tk) if (isset($tasks[$tk])) $users[$tk] = true;
            $then = count($names) > 1 ? 'its next source carries on (On a backup: ' . $names[1] . '); nothing is held' : 'no backup: ' . ($users ? implode(', ', array_keys($users)) : 'what uses it') . ' wait' . (count($users) === 1 ? 's' : '') . '; the rest of ' . $wf . ' runs';
            $now = ['ok' => 'answered by its first source', 'warn' => 'on a backup (working)', 'bad' => 'no source works: ' . $fallback, 'new' => 'not tested yet'][$ns] ?? $ns;
            $h .= '<li><b>' . $e($nn) . '</b>: ' . $e(implode(' → ', $names)) . '. If ' . $e($names[0] ?? 'its first source') . ' fails, ' . $e($then) . '. <small>Now: ' . $e($now) . '.</small></li>';
        }
        $h .= '</ul>';
    }
    // steps that need a person (WorkflowSettings::PERSON)
    if ($spec && $spec['person']) $h .= '<h4 class="wfp-g">Steps that need a person</h4><ul class="wfp-l">' . implode('', array_map(fn ($p) => '<li>' . $e((string) $p) . '</li>', (array) $spec['person'])) . '</ul>';
    // its pre-flight lines, read as they stand: a workflow's line is a roll-up that raises nothing (02 §2.2), so the facts its
    // closed notices carried show here, never lost ("W1 Listings: Needs: …"; "The route of W14: Nothing carries it yet")
    $pf = [];
    foreach (['wf:' . $wf, 'rt:wf:' . $wf] as $lid) {
        $r = $d['res'][$lid] ?? null; if (!$r) continue;
        $parts = array_values(array_filter((array) ($r['extra']['parts'] ?? []), fn ($x) => in_array($x[1] ?? '', ['bad', 'warn'], true)));
        $words = $parts ? implode(' ', array_map(fn ($x) => (string) $x[0], $parts)) : (string) ($r['said'] ?? '');
        $pf[] = '<li>' . tv_ops_wf_lt(['ok' => 'g', 'warn' => 'a', 'bad' => 'a', 'na' => 'n'][(string) ($r['s'] ?? '')] ?? 'n', Preflight::markOf($lid, (string) ($r['s'] ?? 'new'))) . ' <b>' . ($lid[0] === 'r' ? 'Its route' : 'Its line') . '</b>: ' . $e($words) . ' <a class="underline" href="/admin/config/control?tab=preflight#pf-' . $e(str_replace(':', '-', $lid)) . '" data-peek-frame>Its line in the pre-flight</a></li>';
    }
    if ($pf) $h .= '<h4 class="wfp-g">Its pre-flight lines</h4><p class="ak-note">A workflow\'s line adds up its parts and raises nothing itself: a red part holds only what uses it.</p><ul class="wfp-l">' . implode('', $pf) . '</ul>';
    // its board: Check and start again, Shut down, maintenance, its data, logbook and report (R42, R145, R147, R149, R150, R170, R172)
    $pr = rawurlencode('wf:' . $wf);
    $h .= '<p class="wfp-btns"><a class="ak-b" href="/admin/config/control/wf-' . $e($wf) . '" data-peek-frame>Its own page on the board</a><a class="ak-b" href="/admin/config/control/wf-' . $e($wf) . '#log" data-peek-frame>Its logbook</a>'
        . '<a class="ak-b" href="/admin/config/control?report=' . $pr . '&amp;range=day" data-peek-frame>Its report</a></p><p class="ak-note">Its own page holds its controls (Check and start again, Shut it down, Take out for maintenance), its data and its task switches.</p>';
    // its settings (kept, unchanged; §7: one save as a version, and its versions; R154)
    $h .= tv_ops_wf_settings($pdo, $wf, $d, $csrf, (bool) $can, $why);
    return $h;
}

/** The settings form and its versions (kept from the Workflow board, unchanged: decisions/0163 §3, §10; R154). Posts to this page, back to its panel. */
function tv_ops_wf_settings(PDO $pdo, string $wf, array $d, string $csrf, bool $can, string $why): string
{
    $e = 'tv_w_e';
    try { $spec = WorkflowSettings::current($pdo, $wf); } catch (Throwable $x) { return '<p data-tv-status="bad" data-tv-label="Couldn\'t read its settings:">' . $e($x->getMessage()) . '</p>'; }
    $routeChoices = array_keys(array_filter(OutsideServices::CONTROLS, fn ($c) => !empty($c['tasks'])));
    $tasks = $d['tasks']; $stats = $d['stats'];
    $f = '<form method="post" action="#wfp-' . $e($wf) . '"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="op_do" value="wf_save"><input type="hidden" name="ref" value="' . $e($wf) . '"><input type="hidden" name="tz" value="" data-tz>';
    $f .= '<p class="ak-note">Every setting is edited here, never only in code; each save is a version (who, when, what changed) with "Go back to it", and the switch-on check\'s red lines keep it paused until they\'re green (decisions/0163 §3, §10).</p>';
    $f .= '<h4 class="ak-h">What starts it</h4><p class="ak-note">' . $e($spec['starts']['schedule']) . '</p><label class="ak-note">Its triggers (what else starts it, in words) <textarea class="ak-in" name="triggers" rows="2" data-spell>' . $e((string) $spec['starts']['triggers']) . '</textarea></label>';
    if ($spec['tasks']) {
        $f .= '<h4 class="ak-h">The task schedule</h4>' . ($spec['share_pct'] !== null ? '<label class="ak-note">Its share of a crawler run <input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="share_pct" value="' . (int) $spec['share_pct'] . '">%</label>' : '');
        $f .= '<div class="wfp-tw"><table class="ak-aa"><thead><tr><th>Task</th>';
        foreach (WorkflowSettings::TASK_FIELDS as [$label]) $f .= '<th>' . $e($label) . '</th>';
        $f .= '<th>Services it may use</th><th>Last 24 h</th></tr></thead><tbody>';
        foreach ($spec['tasks'] as $t => $task) {
            $st = $stats[$t] ?? [];
            $f .= '<tr><td><b>' . $e($task['name']) . '</b><br><small>' . $e($t) . ((int) ($tasks[$t]['enabled'] ?? 1) ? '' : ' · switched off') . '</small></td>';
            foreach (WorkflowSettings::TASK_FIELDS as $col => [$label, $lo, $hi]) $f .= '<td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="t[' . $e($t) . '][' . $col . ']" value="' . (int) $task[$col] . '" aria-label="' . $e($task['name'] . ': ' . $label) . '" title="' . $lo . ' to ' . $hi . '"></td>';
            $f .= '<td><input type="hidden" name="t[' . $e($t) . '][routes][]" value=""><small style="display:grid;gap:.1rem">';
            foreach ($routeChoices as $rs) $f .= '<label style="white-space:nowrap"><input type="checkbox" name="t[' . $e($t) . '][routes][]" value="' . $e($rs) . '"' . (in_array($rs, (array) $task['routes'], true) ? ' checked' : '') . '> ' . $e(OutsideServices::COMPANIES[$rs][0] ?? $rs) . '</label>';
            $f .= '</small></td><td><small>' . $e(implode(', ', array_map(fn ($k, $v) => $v . ' ' . str_replace('_', ' ', $k), array_keys(array_diff_key($st, ['seconds' => 1])), array_diff_key($st, ['seconds' => 1]))) ?: 'No runs') . '</small></td></tr>';
        }
        $f .= '</tbody></table></div><p class="ak-note">A task\'s services (its routes) are read by the crawler service.</p>';
    }
    $f .= '<h4 class="ak-h">Budgets</h4><label class="ak-note">Its share of the AI cap <input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="ai_share_pct" value="' . (int) ($spec['budgets']['ai_share_pct'] ?? 0) . '">%</label> '
        . '<label class="ak-note">Its monthly budget, all services <input class="ak-in" style="width:6rem" type="text" inputmode="decimal" name="month_usd" value="' . $e((string) ($spec['budgets']['month_usd'] ?? 0)) . '"> dollars (0: none of its own)</label>';
    if ($spec['gates']) { $f .= '<h4 class="ak-h">Its gates</h4>'; foreach ($spec['gates'] as $gk => $v) $f .= '<label class="ak-note" style="display:block">' . $e(Thresholds::GATES[$gk][0]) . ' <input class="ak-in" style="width:6rem" type="text" inputmode="decimal" name="g[' . $e($gk) . ']" value="' . $e((string) $v) . '"> ' . $e(Thresholds::GATES[$gk][6]) . '</label>'; }
    if ($spec['weights']) {
        $f .= '<details><summary class="ak-h" style="cursor:pointer">The collaborative score\'s weights</summary><p class="ak-note">Not a gate (decisions/0163 §12): what each independent source adds to a listing\'s confidence, once per origin.</p><table class="ak-aa"><tbody>';
        foreach ($spec['weights'] as $k => $v) $f .= '<tr><td>' . $e(WorkflowSettings::WEIGHTS[$k][0] ?? $k) . '</td><td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="w[' . $e($k) . ']" value="' . (int) $v . '"></td></tr>';
        $f .= '</tbody></table></details>';
    }
    if ($spec['ladder']) {
        $f .= '<details><summary class="ak-h" style="cursor:pointer">The retry ladder</summary><p class="ak-note">For what the engine retries for itself; never a site that blocks us (that waits for a person\'s Reactivate). New evidence starts it again.</p><table class="ak-aa"><thead><tr><th>After fails in a row</th><th>Next try in days</th></tr></thead><tbody>';
        foreach ($spec['ladder'] as $i => [$n, $dd]) $f .= '<tr><td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="ladder[' . $i . '][0]" value="' . (int) $n . '"></td><td><input class="ak-in" style="width:5rem" type="text" inputmode="numeric" name="ladder[' . $i . '][1]" value="' . (int) $dd . '"></td></tr>';
        $f .= '</tbody></table></details>';
    }
    $f .= '<h4 class="ak-h">Steps that need a person</h4><textarea class="ak-in" name="person" rows="3" data-spell aria-label="Steps that need a person, one a line">' . $e(implode("\n", (array) $spec['person'])) . '</textarea>';
    $f .= '<p style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-top:.5rem"><input class="ak-in" type="text" name="note" maxlength="300" data-spell placeholder="What this change is for (kept with the version)" style="flex:1;min-width:12rem">'
        . '<button class="ak-b go" type="submit" data-confirm="Save ' . $e($wf) . '\'s settings as a new version? The settings the code reads change now; nothing is switched on." data-yes="Save ' . $e($wf) . ' settings"' . (!$can ? ' disabled title="' . $e($why) . '"' : '') . '>Save ' . $e($wf) . ' settings</button></p></form>';
    $vs = []; try { $vs = WorkflowSettings::versions($pdo, $wf, 12); } catch (Throwable $x) {}
    if ($vs) {
        $f .= '<h4 class="ak-h">Versions</h4><ul style="list-style:none;padding:0;margin:0;display:grid;gap:.35rem">';
        foreach ($vs as $i => $v) {
            $ch = json_decode((string) $v['changes'], true) ?: [];
            $f .= '<li><b>Version ' . (int) $v['version'] . '</b> · ' . $e(($v['by_name'] ?? '') !== '' ? (string) $v['by_name'] : ((int) $v['saved_by'] ? 'staff' : 'found')) . ' · <time datetime="' . $e(str_replace(' ', 'T', (string) $v['saved_at'])) . '" data-tv-time' . ((string) $v['tz'] !== '' ? ' data-tz="' . $e((string) $v['tz']) . '"' : '') . '>' . $e((string) $v['saved_at']) . '</time>'
                . ((string) $v['note'] !== '' ? ' · ' . $e((string) $v['note']) : '') . ((int) $v['check_red'] ? ' · <span data-tv-status="info">' . (int) $v['check_red'] . ' red on the check</span>' : '')
                . ($ch ? '<br><small>' . $e(implode('; ', array_slice($ch, 0, 6))) . (count($ch) > 6 ? '; and ' . (count($ch) - 6) . ' more' : '') . '</small>' : '')
                . ($i > 0 ? ' ' . str_replace('<form method="post"', '<form method="post" action="#wfp-' . $e($wf) . '"', tv_ops_form($csrf, 'wf_back', 'Go back to it', ['ref' => $wf, 'version' => (int) $v['version']], 'Go back to version ' . (int) $v['version'] . '? It\'s saved as a new version, so nothing is lost.', '', $can, $why)) : ' <small>(current)</small>') . '</li>';
        }
        $f .= '</ul>';
    }
    return '<details class="wfp-more wfp-set" id="wfp-' . $e($wf) . '-settings"><summary>Its settings (each save a version' . ($vs ? '; now version ' . (int) $vs[0]['version'] : '') . ')</summary>' . $f . '</details>';
}

/** The workflow's page panel. */
function tv_ops_wf_panel(PDO $pdo, array $d, string $wf, string $csrf, bool $can, string $why): string
{
    $e = 'tv_w_e'; $name = (string) Automation::WORKFLOWS[$wf][0];
    try { $body = tv_ops_wf_page($pdo, $wf, $d, $csrf, $can, $why); }
    catch (Throwable $x) { error_log('workflow page ' . $wf . ': ' . $x->getMessage()); $body = '<p data-tv-status="bad" data-tv-label="Couldn\'t draw it:">' . $e($x->getMessage()) . '</p>'; }
    return tv_ops_wf_aside('wfp-' . $wf, $wf . ' ' . $name . ': its page', 'Workflows · its own page (<a class="underline" href="/admin/config/control/wf-' . $e($wf) . '" data-peek-frame>/admin/config/control/wf-' . $e($wf) . '</a>)', $wf . ' ' . $name, $body . tv_ops_wf_pathbtns($wf));
}

/** The three paths from a workflow (§4): what it feeds and what feeds it, our own parts it rests on, its needs' sources in order. */
function tv_ops_wf_paths(array $d, string $wf): string
{
    $e = 'tv_w_e'; $name = (string) Automation::WORKFLOWS[$wf][0];
    $crumb = 'Workflows · <a class="underline" href="#wfp-' . $e($wf) . '" data-wfp-open="wfp-' . $e($wf) . '">' . $e($wf . ' ' . $name) . '</a>';
    $wn = fn (string $w) => '<a class="underline" href="#wfp-' . $e($w) . '" data-wfp-open="wfp-' . $e($w) . '">' . $e($w . ' ' . (Automation::WORKFLOWS[$w][0] ?? '')) . '</a>';
    $ul = fn (array $xs, string $none) => $xs ? '<ul class="wfp-l"><li>' . implode('</li><li>', $xs) . '</li></ul>' : '<p class="ak-note">' . $e($none) . '</p>';
    // Workflows ▸: the board's gauges it feeds (ControlGauges::runtime paths), who it sends work to and who sends it work (§4)
    $feeds = [];
    foreach ((array) ($d['gauges'][$wf]['feeds'] ?? []) as $fd) $feeds[] = '<b>' . $e(is_array($fd) ? (string) ($fd[1] ?? $fd['title'] ?? $fd[0] ?? '') : (string) (class_exists('ControlGauges') ? (ControlGauges::RT_FALLBACK[$fd] ?? $fd) : $fd)) . '</b>';
    foreach ($d['runtime'] as $rg) foreach ((array) ($rg['paths']['workflows'] ?? []) as $p) if ((string) ($p[0] ?? '') === $wf || (string) ($p[0] ?? '') === 'wf:' . $wf) $feeds[] = '<b>' . $e((string) ($rg['title'] ?? $rg['id'] ?? '')) . '</b>';
    $to = []; $from = [];
    foreach (TV_WFP_SENDS as [$a, $b, $why, $ln]) { if ($a === $wf) $to[] = $wn($b) . ': ' . $e($why) . ' <small>(research/crawler-how-it-runs-2026-11-14.md' . $e($ln) . ')</small>'; if ($b === $wf) $from[] = $wn($a) . ': ' . $e($why) . ' <small>(research/crawler-how-it-runs-2026-11-14.md' . $e($ln) . ')</small>'; }
    $pa = '<h4 class="wfp-g">The board\'s gauges it feeds</h4>' . $ul(array_values(array_unique($feeds)), 'No gauge on the board names it yet.')
        . '<h4 class="wfp-g">It sends work to</h4>' . $ul($to, 'None.') . '<h4 class="wfp-g">Work comes from</h4>' . $ul($from, 'None.');
    // Core Services ▸: the databases (always), the workers, the scheduled jobs, the dispatcher (§4); W1's loads endpoint (§3)
    $rg = $d['reg'][$wf] ?? []; $core = ['<a class="underline" href="/admin/config/control/site-db" data-peek-frame>The databases</a>'];
    foreach ((array) ($rg['workers'] ?? []) as $wk) $core[] = '<a class="underline" href="/admin/config/control/wk-' . $e((string) $wk) . '" data-peek-frame>' . $e((string) (ControlRegister::WORKERS[$wk][0] ?? $wk)) . '</a> <small>(' . $e((string) (ControlRegister::WORKERS[$wk][1] ?? '')) . ')</small>';
    if (!empty($rg['jobs'])) $core[] = '<a class="underline" href="/admin/config/control/site-sched" data-peek-frame>The scheduler</a>: ' . $e(implode(', ', array_map(fn ($j) => Scheduler::JOBS[$j][0] ?? $j, (array) $rg['jobs'])));
    elseif (!empty($rg['tasks'])) $core[] = 'The scheduled runs: the crawler every 5 minutes, its schedule set by Start the system';
    if (in_array('crawl/loads', (array) ($rg['endpoints'] ?? []), true) || ($wf === 'W1' && !empty($rg['tasks']))) $core[] = '<b>/api/crawl/loads.php</b>: its own check, holding only overture_bulk, foursquare_bulk and geofabrik_bulk when it fails (workflows-page.md §3, R87); coverage, second_look, targets, npi_bulk and irs_bulk don\'t call it and carry on.';
    if (!isset(TV_WFP_LIST[$wf])) $core[] = 'The dispatcher: its alarms to the board, Pulse and email, one thread each';
    // Registered Services ▸: its needs, each with its sources in order and its fallback (Preflight::NEEDS); each source its card
    $rs = [];
    foreach ((array) ($rg['needs'] ?? []) as $k) {
        $n = Preflight::NEEDS[$k] ?? null; if (!$n) continue;
        $src = array_map(function ($sr) use ($e) {
            if (($sr[0] ?? '') === 'svc') return '<a class="underline" href="/admin/config/register#svc-' . $e((string) $sr[1]) . '" data-peek-frame>' . $e((string) (OutsideServices::COMPANIES[$sr[1]][0] ?? $sr[1])) . '</a>';
            if (($sr[0] ?? '') === 'paid') return $e('a paid backup (a director\'s override only)');
            return $e((string) ($sr[2] ?? $sr[1] ?? ''));
        }, (array) $n[3]);
        $rs[] = '<b>' . $e(preg_replace('/ \((W[^)]*|the site itself)\)$/', '', (string) $n[1])) . '</b>: ' . implode(' → ', $src) . ' <small>· else ' . $e((string) $n[4]) . ($n[5] ? ' (its fallback always works)' : '') . '</small>';
    }
    return tv_ops_wf_aside('wfp-' . $wf . '-a', $wf . ': Workflows', $crumb, 'Workflows: what it feeds, and what feeds it', $pa)
        . tv_ops_wf_aside('wfp-' . $wf . '-c', $wf . ': Core Services', $crumb, 'Core Services: our own parts it rests on', $ul($core, 'None.'))
        . tv_ops_wf_aside('wfp-' . $wf . '-r', $wf . ': Registered Services', $crumb, 'Registered Services: its needs, sources in order', '<p class="ak-note">Ours first, then outside free before paid; the first that works wins (R61).</p>' . $ul($rs, 'No need in the source map.'));
}

/** One case, end to end (workflows-page.md §8, from 03 §28.1): drawn as six steps, what isn't real today marked illustration. */
function tv_ops_wf_case(): string
{
    $steps = [
        ['r', 'Fault', 'real', 'index.commoncrawl.org answers 504: Issue #35, "Common Crawl: its server failed at index.commoncrawl.org" (Held, 09:00 UTC Nov 20).'],
        ['a', 'What it holds', 'real code; the drawn state is an illustration', 'Only W3\'s per-site page copy waits (and W2\'s Re-read websites fallback). W3\'s bulk reads go to data.commoncrawl.org and carry on. The gate checks data.commoncrawl.org first, then the index as a second part: a failing second part is amber, "Partly working".'],
        ['a', 'The system tries', 'illustration', 'On the diagnostic clock: the component\'s check, 3 tries inside the hour. A scheduled W3 run is not a try. Status: "Trying again (1 of 3)", then "Checked: still 504. Next check …".'],
        ['a', 'Its card, from the alarm', 'illustration', 'The alarm\'s first button opens Common Crawl\'s card. A 504 is their trouble, so the card shows no Fix it: only Check it now and Pause it, with "the system is waiting on them". Fix it shows only for a cause a person can fix (a key, a quota, a block).'],
        ['g', 'Recovered', 'illustration', 'The check passes: the pause lifts and the thread closes as "Recovered: Common Crawl\'s index was down n min, the system fixed it.", a notice with nothing to press.'],
        ['a', 'Switches back', 'real today: still off', 'A trip places its pause beside the switches, never in them, so nothing is left to restore. W3\'s two switches today were written by an older trip: Off by the system, left by a closed trip. With no person\'s setting to go back to they stay off, under Needs attention with their switch, until a person presses Switch on (who and when logged).'],
    ];
    $h = '<details class="wfp-more wfp-case" id="wfp-case"><summary>One case, end to end: Common Crawl\'s index down holds only W3\'s per-site copy</summary><p class="ak-note">A source down holds only what uses it (R88). Stages that aren\'t real today are marked illustration.</p><ol class="wfp-csl">';
    foreach ($steps as [$k, $t, $tag, $x]) $h .= '<li>' . tv_ops_wf_lt($k, $t) . ' <small class="wfp-tag">' . tv_w_e($tag) . '</small><p class="ak-note">' . tv_w_e($x) . '</p></li>';
    return $h . '</ol></details>';
}

/** What moved from the old Workflows page, with where (§7): one home for one button. */
function tv_ops_wf_moved(): string
{
    return '<details class="wfp-more" id="wfp-moved"><summary>Moved from this page</summary><ul class="wfp-l">'
        . '<li><b>Start the system</b> is in the board\'s <a class="underline" href="/admin/config/control?tab=startup" data-peek-frame>Start-up</a> only (R17, R146): one home for one button.</li>'
        . '<li><b>Check before switching on</b>: each red line now shows on the card of what it holds (R88, R99); the whole check is in Start-up\'s <a class="underline" href="/admin/config/control?tab=preflight#pf-g-switchon" data-peek-frame>pre-flight</a> (R58–R60).</li>'
        . '<li>Each workflow\'s settings, its task schedule and its versions are on its own page: press its card.</li></ul></details>';
}

function tv_ops_wf_css(): string
{
    return '<style>'
        . '#wfp .wfp-now{margin:.6rem 0;padding:.5rem .7rem}#wfp .wfp-h{margin:0 0 .3rem;font-size:1rem}#wfp .wfp-g{margin:.6rem 0 .2rem;font-size:.85rem;color:var(--tv-copper,#92400E);font-weight:700}'
        . '#wfp .wfp-cnt{display:flex;flex-wrap:wrap;gap:.3rem;margin:.2rem 0 .4rem}#wfp .wfp-f{border:1px solid var(--tv-line-strong,#CBB98D);background:var(--tv-card-a,#fff);border-radius:999px;padding:.15rem .6rem;font:inherit;font-size:.8rem;cursor:pointer;color:inherit}'
        . '#wfp .wfp-f b{margin-left:.3rem}#wfp .wfp-f[aria-pressed="true"]{background:var(--tv-copper,#92400E);color:#fff;border-color:var(--tv-copper,#92400E)}'
        . '#wfp .wfp-l{margin:.2rem 0 .4rem 1rem;padding:0;font-size:.85rem;display:flex;flex-direction:column;gap:.25rem;overflow-wrap:anywhere}'
        . '#wfp .wfp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem;margin:.6rem 0}'
        . '#wfp .wfp-card{display:flex;flex-direction:column;background:var(--tv-card-a,#fff);border:1px solid var(--tv-line-strong,#E3DBC6);border-radius:.5rem;padding:.5rem;min-width:0}#wfp .wfp-card[hidden]{display:none}'
        . '#wfp .wfp-s-r{border-color:var(--tv-fail,#a62b2b)}#wfp .wfp-open{display:flex;flex-direction:column;align-items:center;gap:.2rem;background:none;border:0;font:inherit;color:inherit;cursor:pointer;text-align:center;padding:0;min-width:0}'
        . '#wfp .wfp-nm{font-size:.85rem;line-height:1.25;min-height:2.5em;overflow-wrap:anywhere}#wfp .wfp-su,#wfp .wfp-m{font-size:.75rem;color:var(--tv-meta,#6b5d49);line-height:1.3;overflow-wrap:anywhere}#wfp .wfp-list{font-size:.8rem;color:var(--tv-meta,#6b5d49);line-height:1.35;margin:.3rem 0}'
        . '#wfp .wfp-appr{font-size:.68rem;border:1px solid #3d6b52;color:#3d6b52;border-radius:3px;padding:0 4px}'
        . '#wfp .wfp-lt{display:inline-flex;gap:.3rem;align-items:baseline;font-size:.8rem;font-weight:600;overflow-wrap:anywhere;text-align:left}#wfp .wfp-lt i{flex:0 0 auto;width:.6rem;height:.6rem;border-radius:50%;background:var(--tv-taupe,#7A6A52);display:inline-block}'
        . '#wfp .wfp-r i{background:var(--tv-fail,#a62b2b)}#wfp .wfp-r{color:var(--tv-fail,#a62b2b)}#wfp .wfp-a i{background:var(--tv-hold,#8f5600)}#wfp .wfp-a{color:var(--tv-hold,#8f5600)}#wfp .wfp-g i{background:#3d6b52}#wfp .wfp-n i{background:var(--tv-taupe,#B8AC97)}'
        . '#wfp .wfp-btns{display:flex;flex-wrap:wrap;gap:.3rem;justify-content:center;margin:.35rem 0 0}#wfp .wfp-path{font-size:.72rem;padding:.1rem .5rem}'
        . '#wfp .wfp-more{margin:.6rem 0}#wfp .wfp-more>summary{cursor:pointer;font-weight:600;color:var(--tv-copper,#92400E)}#wfp .wfp-2{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}'
        . '#wfp .wfp-csl{margin:.3rem 0 0 1.1rem;padding:0;display:flex;flex-direction:column;gap:.45rem}#wfp .wfp-tag{font-size:.7rem;color:var(--tv-meta,#6b5d49);border:1px solid currentColor;border-radius:999px;padding:0 .35rem}'
        . '#wfp .wfp-scrim{position:fixed;inset:0;background:rgba(40,28,18,.35);z-index:80}'
        . '#wfp .wfp-panel{position:fixed;top:0;right:0;bottom:0;width:min(640px,100vw);background:var(--tv-bg,#FFFBF2);border-left:1px solid var(--tv-line-strong,#CBB98D);z-index:81;overflow-y:auto;overflow-x:hidden;padding:.8rem 1rem 1.2rem;box-shadow:-6px 0 18px rgba(0,0,0,.12)}'
        . '#wfp .wfp-panel[hidden]{display:none}#wfp .wfp-x{position:sticky;top:0;float:right;font-size:1.4rem;line-height:1;border:0;background:var(--tv-bg,#FFFBF2);cursor:pointer;padding:.1rem .4rem;color:inherit}'
        . '#wfp .wfp-crumb{font-size:.75rem;color:var(--tv-meta,#6b5d49);margin:0;overflow-wrap:anywhere}#wfp .wfp-t{margin:.2rem 0 .4rem;font-size:1.1rem}#wfp .wfp-panel .wfp-btns{justify-content:flex-start}'
        . '#wfp .wfp-gz{display:flex;flex-direction:column;align-items:center;gap:.2rem}#wfp .wfp-steps{list-style:none;margin:.2rem 0;padding:0;display:grid;gap:.4rem}#wfp .wfp-steps>li{border:1px solid var(--tv-line-strong,#E3DBC6);border-radius:.4rem;padding:.35rem .5rem;min-width:0;overflow-wrap:anywhere}'
        . '#wfp .wfp-th{border:1px solid var(--tv-line-strong,#E3DBC6);border-radius:.5rem;padding:.5rem .6rem;margin:.4rem 0;background:var(--tv-card-a,#fff);min-width:0}#wfp .wfp-tw{overflow-x:auto;max-width:100%}#wfp .wfp-su2{list-style:none;margin-left:0}'
        . 'body.wfp-on{overflow:hidden}'
        . '@media (max-width:767px){#wfp .wfp-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}#wfp .wfp-card{padding:.4rem .25rem}#wfp .wfp-2{grid-template-columns:1fr}#wfp .wfp-path{font-size:.68rem;padding:.1rem .35rem}#wfp .wfp-panel{padding:.6rem .5rem 1rem}'
        . '#wfp .wfp-tw table,#wfp .wfp-tw tbody,#wfp .wfp-tw tr,#wfp .wfp-tw td{display:block;width:auto}#wfp .wfp-tw thead{display:none}}'
        . '</style>';
}

/**
 * The panels (one at a time, Back through what was opened, closing by ×, Esc and a click outside: CLAUDE.md), the filters
 * (a count shows only its cards; pressed again, every card: R11), Try again on failed crawl jobs (D9: Insights → Crawler's
 * own Retry, cr_do=retry, its answer beside the button) and Check and start again (the board's resolve).
 */
function tv_ops_wf_js(string $csrf): string
{
    return '<script' . (function_exists('tv_nonce') ? tv_nonce() : '') . '>(function(){if(window.__wfpInit)return;window.__wfpInit=1;var trail=[],cur=null;'
        . 'function scrim(){return document.querySelector("[data-wfp-scrim]");}'
        . 'function show(id,push){var p=document.getElementById(id);if(!p)return;if(push&&cur&&cur!==id)trail.push(cur);document.querySelectorAll(".wfp-panel").forEach(function(q){q.hidden=q.id!==id;});var s=scrim();if(s)s.hidden=false;document.body.classList.add("wfp-on");cur=id;p.scrollTop=0;var x=p.querySelector("[data-wfp-close]");if(x)x.focus({preventScroll:true});}'
        . 'function close(){document.querySelectorAll(".wfp-panel").forEach(function(q){q.hidden=true;});var s=scrim();if(s)s.hidden=true;document.body.classList.remove("wfp-on");trail=[];cur=null;if(/^#wfp-/.test(location.hash))try{history.replaceState(null,"",location.pathname+location.search);}catch(e){}}'
        . 'document.addEventListener("click",function(e){var t=e.target;'
        . 'var f=t.closest("[data-wfp-filter]");if(f){var k=f.getAttribute("data-wfp-filter"),on=f.getAttribute("aria-pressed")!=="true";document.querySelectorAll("[data-wfp-filter]").forEach(function(x){x.setAttribute("aria-pressed",x===f&&on?"true":"false");});document.querySelectorAll(".wfp-card").forEach(function(c){c.hidden=on&&(" "+c.getAttribute("data-wfp-g")+" ").indexOf(" "+k+" ")<0;});return;}'
        . 'var o=t.closest("[data-wfp-open]");if(o){e.preventDefault();show(o.getAttribute("data-wfp-open"),!!o.closest(".wfp-panel"));return;}'
        . 'if(t.closest("[data-wfp-close]")||t.hasAttribute("data-wfp-scrim")){close();return;}'
        . 'if(t.closest("[data-wfp-back]")){e.preventDefault();var p=trail.pop();if(p){cur=null;show(p,false);}else close();return;}'
        . 'var b=t.closest("[data-wfp-retry],[data-wfp-cb]");if(!b||b.disabled)return;e.preventDefault();if(window.TvAsk)TvAsk.busy(b,true);'
        . 'var done=function(ok,msg){if(window.TvAsk){TvAsk.busy(b,false);TvAsk.say(msg,{anchor:b,bad:!ok});}if(ok)setTimeout(function(){location.reload();},2200);};'
        . 'if(b.hasAttribute("data-wfp-retry")){var fd=new URLSearchParams();fd.set("csrf",' . json_encode($csrf) . ');fd.set("cr_do","retry");fd.set("task",b.getAttribute("data-wfp-retry"));'
        . 'fetch("/admin/insights/crawler",{method:"POST",credentials:"include",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:fd.toString()}).then(function(r){return r.text();}).then(function(tx){var m=/data-tv-flash="(ok|error)"[^>]*>([^<]*)</.exec(tx);'
        . 'if(!m)return done(false,"The crawler page didn’t say it was done, so nothing is claimed: open Insights → Crawler to check, or try again.");var ta=document.createElement("textarea");ta.innerHTML=m[2];done(m[1]==="ok",ta.value);}).catch(function(){done(false,"That didn’t reach the site. Try again.");});return;}'
        . 'var body={action:b.getAttribute("data-wfp-cb"),id:b.getAttribute("data-wfp-id")};'
        . 'fetch("/admin/api/config/control",{method:"POST",credentials:"include",headers:{"Content-Type":"application/json","X-CSRF-Token":' . json_encode($csrf) . '},body:JSON.stringify(window.tvSafe?tvSafe(body):body)})'
        . '.then(function(r){return r.json().catch(function(){return{ok:false,error:{message:"The server didn’t answer clearly. Try again; still stuck? Write to support@traversence.com."}};});})'
        . '.then(function(j){done(!!j.ok,j.ok?(j.message||"Done."):((j.error&&j.error.message)||"That didn’t work."));}).catch(function(){done(false,"That didn’t reach the site. Try again.");});});'
        // Esc closes the panel, unless a pop-up over it (a peek or a question) is open: that one closes first (capture, registered first)
        . 'document.addEventListener("keydown",function(e){if(e.key!=="Escape"||!cur)return;if(document.querySelector(".tv-ask-lb,.tv-pk"))return;close();},true);'
        . 'function fromHash(){var m=/^#(wfp-W\d+(?:-[acr])?)$/.exec(location.hash);if(m&&document.getElementById(m[1]))show(m[1],false);}fromHash();window.addEventListener("hashchange",fromHash);})();</script>';
}
